<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CitationCslProcessor
{
    /** @var array<string, array<string, mixed>> */
    private array $itemsById;

    /** @var list<string> */
    private array $primaryIds;

    /** @var array<string, string> */
    private array $canonicalIdsById;

    /** @var array<string, array<string, string>> */
    private array $cslAbbreviations;

    private CslStyle $style;

    /** @var array{etAlMin?:int, etAlUseFirst?:int, etAlUseLast?:bool}|null */
    private ?array $sortKeyNameRenderingOverrides = null;

    /**
     * @param array<string, array<string, mixed>> $itemsById
     * @param list<string> $primaryIds
     * @param array<string, string> $canonicalIdsById
     * @param array<string, mixed> $cslAbbreviations
     */
    private function __construct(array $itemsById, ?CslStyle $style = null, array $primaryIds = [], array $canonicalIdsById = [], array $cslAbbreviations = [])
    {
        $this->itemsById = $itemsById;
        $this->primaryIds = $primaryIds === [] ? array_keys($itemsById) : $primaryIds;
        $this->canonicalIdsById = $canonicalIdsById;
        foreach ($this->primaryIds as $id) {
            $this->canonicalIdsById[$id] = $this->canonicalIdsById[$id] ?? $id;
        }
        $this->style = $style ?? CslStyle::default();
        $this->cslAbbreviations = self::normalizeCslAbbreviations($cslAbbreviations);
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid CSL JSON: ' . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('CSL JSON bibliography must be an item object or a list of item objects');
        }
        if (!array_is_list($decoded)) {
            $decoded = [$decoded];
        }

        return self::fromItems(self::sanitizeCslJsonInputItems($decoded));
    }

    /**
     * @param list<mixed> $items
     * @return list<mixed>
     */
    public static function sanitizeCslJsonInputItems(array $items): array
    {
        return array_map(
            static fn (mixed $item): mixed => is_array($item) ? self::sanitizeCslJsonInputItem($item) : $item,
            $items
        );
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function sanitizeCslJsonInputItem(array $item): array
    {
        foreach (self::cslJsonNameFields() as $field) {
            if (!array_key_exists($field, $item) || !is_array($item[$field]) || !array_is_list($item[$field])) {
                continue;
            }

            $names = [];
            foreach ($item[$field] as $name) {
                if (is_array($name) && $name === []) {
                    continue;
                }
                $names[] = $name;
            }

            if ($names === []) {
                unset($item[$field]);
            } else {
                $item[$field] = $names;
            }
        }

        return $item;
    }

    /**
     * @return list<string>
     */
    private static function cslJsonNameFields(): array
    {
        return [
            'author',
            'editor',
            'short-author',
            'short-editor',
            'holder',
            'translator',
            'chair',
            'container-author',
            'collection-editor',
            'composer',
            'contributor',
            'director',
            'illustrator',
            'interviewer',
            'original-author',
            'recipient',
            'reviewed-author',
        ];
    }

    public static function fromBibtex(string $bibtex): self
    {
        return self::fromItems(self::bibtexItems($bibtex));
    }

    public static function fromRis(string $ris): self
    {
        return self::fromItems(self::risItems($ris));
    }

    public static function fromEndnoteXml(string $xml): self
    {
        return self::fromItems(self::endnoteXmlItems($xml));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function bibtexItems(string $bibtex): array
    {
        return BibtexCslParser::parse($bibtex, allowDuplicateKeys: true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function risItems(string $ris): array
    {
        $records = [];
        $fields = [];
        $lastTag = null;
        foreach (preg_split('/\r\n|\n|\r/', $ris) ?: [] as $line) {
            $line = ltrim($line, "\xEF\xBB\xBF");
            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z0-9]{2})\s*-\s?(.*)$/', $line, $matches) !== 1) {
                if ($lastTag !== null && $fields !== []) {
                    $lastIndex = count($fields[$lastTag]) - 1;
                    $fields[$lastTag][$lastIndex] = trim($fields[$lastTag][$lastIndex] . ' ' . trim($line));
                }
                continue;
            }

            $tag = strtoupper($matches[1]);
            $value = trim($matches[2]);
            if ($tag === 'ER') {
                if ($fields !== []) {
                    $records[] = self::risRecordToItem($fields, count($records) + 1);
                }
                $fields = [];
                $lastTag = null;
                continue;
            }

            $fields[$tag][] = $value;
            $lastTag = $tag;
        }

        if ($fields !== []) {
            $records[] = self::risRecordToItem($fields, count($records) + 1);
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function endnoteXmlItems(string $xml): array
    {
        $document = XmlHtmlDom::loadXmlDocument($xml, 'EndNote XML', false);
        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $records = $root->localName === 'record'
            ? [$root]
            : XmlHtmlDom::descendantElements($root, 'record');
        $items = [];
        foreach ($records as $index => $record) {
            $items[] = self::endnoteRecordToItem($record, $index + 1);
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public static function fromItems(array $items): self
    {
        $itemsById = [];
        $primaryIds = [];
        $canonicalIdsById = [];
        $normalizedItems = self::normalizeItems($items);
        foreach ($normalizedItems as $normalized) {
            $id = (string) $normalized['id'];
            $itemsById[$id] = $normalized;
            $primaryIds[] = $id;
            $canonicalIdsById[$id] = $id;
        }

        foreach ($normalizedItems as $normalized) {
            $id = (string) $normalized['id'];
            $aliases = $normalized['citationAliases'] ?? [];
            if (!is_array($aliases)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (!is_scalar($alias)) {
                    continue;
                }

                $alias = trim((string) $alias);
                if ($alias === '' || $alias === $id) {
                    continue;
                }

                if (isset($itemsById[$alias]) || isset($canonicalIdsById[$alias])) {
                    throw new \InvalidArgumentException('Duplicate CSL citation alias: ' . $alias);
                }

                $itemsById[$alias] = [
                    ...$normalized,
                    'citationAlias' => $alias,
                ];
                $canonicalIdsById[$alias] = $id;
            }
        }

        return new self($itemsById, null, $primaryIds, $canonicalIdsById);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function normalizeItems(array $items): array
    {
        $normalizedItems = [];
        $ids = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('CSL item at index ' . $index . ' must be an object');
            }

            $normalized = self::normalizeItem($item, $index);
            $id = (string) $normalized['id'];
            if (isset($ids[$id])) {
                $baseId = $id;
                $suffix = $ids[$baseId] + 1;
                do {
                    $id = $baseId . '-' . $suffix;
                    $suffix++;
                } while (isset($ids[$id]));
                $ids[$baseId] = $suffix - 1;
                $normalized['id'] = $id;
                $normalized['originalId'] = $baseId;
                $normalized['idDisambiguation'] = [
                    'source' => 'duplicate-csl-item-id',
                    'originalId' => $baseId,
                    'disambiguatedId' => $id,
                ];
            }

            $ids[$id] = $ids[$id] ?? 1;
            $normalizedItems[] = $normalized;
        }

        return $normalizedItems;
    }

    /**
     * @param list<string> $localeXmls
     */
    public function withCslStyle(string $styleXml, array $localeXmls = []): self
    {
        return new self($this->itemsById, CslStyle::fromXml($styleXml, $localeXmls), $this->primaryIds, $this->canonicalIdsById, $this->cslAbbreviations);
    }

    /**
     * @param array<string, mixed> $abbreviations
     */
    public function withCslAbbreviations(array $abbreviations): self
    {
        return new self($this->itemsById, $this->style, $this->primaryIds, $this->canonicalIdsById, $abbreviations);
    }

    public function withCslAbbreviationsJson(string $json): self
    {
        return $this->withCslAbbreviations(self::cslAbbreviationsFromJson($json));
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function cslAbbreviationsFromJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid CSL abbreviations JSON: ' . json_last_error_msg());
        }

        if (!is_array($decoded) || self::decodedJsonIsList($decoded, $json)) {
            throw new \InvalidArgumentException('CSL abbreviations JSON must be an object map');
        }

        return self::normalizeCslAbbreviations($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    public function cslStyleSummary(): array
    {
        $summary = $this->style->summary();
        if ($this->cslAbbreviations !== []) {
            $summary['abbreviations'] = $this->cslAbbreviations;
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $abbreviations
     * @return array<string, array<string, string>>
     */
    private static function normalizeCslAbbreviations(array $abbreviations): array
    {
        if ($abbreviations === []) {
            return [];
        }

        $source = $abbreviations;
        if (array_key_exists('default', $source)) {
            if (!is_array($source['default'])) {
                throw new \InvalidArgumentException('CSL abbreviations default must be a map');
            }

            $source = $source['default'];
        }

        $normalized = [];
        foreach ($source as $category => $entries) {
            $category = str_replace('_', '-', strtolower(trim((string) $category)));
            if ($category === '') {
                continue;
            }

            if (!is_array($entries)) {
                throw new \InvalidArgumentException('CSL abbreviations category ' . $category . ' must be a map');
            }

            foreach ($entries as $full => $short) {
                if (!is_scalar($short)) {
                    throw new \InvalidArgumentException('CSL abbreviations category ' . $category . ' values must be scalar');
                }

                $full = trim((string) $full);
                $short = trim((string) $short);
                if ($full === '' || $short === '') {
                    continue;
                }

                $normalized[$category][$full] = $short;
            }
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $decoded
     */
    private static function decodedJsonIsList(array $decoded, string $json): bool
    {
        if ($decoded === []) {
            return str_starts_with(ltrim($json), '[');
        }

        return array_is_list($decoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function item(string $id): ?array
    {
        return $this->itemsById[$id] ?? null;
    }

    /**
     * @return list<string>
     */
    public function citationIds(AstNode $node): array
    {
        $ids = [];
        $this->collectCitationIds($node, $ids);

        return $ids;
    }

    /**
     * @return list<string>
     */
    public function missingCitationIds(AstNode $node): array
    {
        $missing = [];
        foreach ($this->citationIds($node) as $id) {
            if (!isset($this->itemsById[$id]) && !in_array($id, $missing, true)) {
                $missing[] = $id;
            }
        }

        return $missing;
    }

    /**
     * @return list<array{id:string, source:string, rawLocator:string, rawLocatorLabel:string, locatorLabel:string, locatorValue:string, reason:string, severity:string}>
     */
    public function citationLocatorDiagnostics(AstNode $node): array
    {
        $diagnostics = [];
        $this->collectCitationLocatorDiagnostics($node, $diagnostics);

        return $diagnostics;
    }

    public function normalizeCitation(AstNode $citation): AstNode
    {
        if ($citation->type !== 'citation') {
            throw new \InvalidArgumentException('Expected citation AST node');
        }

        $locatorDiagnostics = $this->citationLocatorDiagnosticsForCitation($citation);
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null) {
            $attrs = [
                ...$citation->attrs,
                ...$this->citationAffixReviewAttrs($citation),
                ...$this->citationLocatorReviewAttrs($citation, $locatorDiagnostics),
                'cslStyleClass' => $this->style->styleClass(),
                'rendered' => $this->sourceCitationText($citation),
                'missingCslItem' => true,
            ];

            return new AstNode(
                'citation',
                $attrs,
                $citation->children
            );
        }

        $item = $this->itemWithCitationContext($item, $citation);

        return new AstNode(
            'citation',
            array_filter([
                ...$citation->attrs,
                ...$this->citationAffixReviewAttrs($citation),
                ...$this->citationLocatorReviewAttrs($citation, $locatorDiagnostics),
                'cslStyleClass' => $this->style->styleClass(),
                'rendered' => $this->renderCitationCluster([$citation]),
                'cslInlineParts' => $this->citationClusterInlineParts([$citation]) ?: null,
                'cslLabel' => $this->citationAuthorLabel($item, $citation),
                'cslYear' => $this->citationYear($item),
                'cslItem' => $item,
            ], static fn (mixed $value): bool => $value !== null),
            $citation->children
        );
    }

    public function normalizeCitationGroup(AstNode $group): AstNode
    {
        if ($group->type !== 'citation_group') {
            throw new \InvalidArgumentException('Expected citation_group AST node');
        }

        $citations = [];
        $missing = [];
        foreach ($group->children as $child) {
            if ($child->type !== 'citation') {
                throw new \InvalidArgumentException('Citation group entries must be citation AST nodes');
            }

            $citations[] = $child;
            $id = (string) $child->attr('id', '');
            if ($id !== '' && !isset($this->itemsById[$id]) && !in_array($id, $missing, true)) {
                $missing[] = $id;
            }
        }

        $citations = $this->ensureClusterCitationPositions($citations);
        $citations = $this->annotateCitationGivenNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationYearSuffixesForCluster($citations);
        $citations = $this->annotateCitationDisambiguationForCluster($citations);
        $attrs = [
            ...$group->attrs,
            'rendered' => $this->renderCitationCluster($citations),
        ];
        $inlineParts = $this->citationClusterInlineParts($citations);
        if ($inlineParts !== []) {
            $attrs['cslInlineParts'] = $inlineParts;
        }
        if ($missing !== []) {
            $attrs['missingCslItems'] = $missing;
        }
        $citationAffixes = $this->citationAffixesForCitations($citations);
        if ($citationAffixes !== []) {
            $attrs['cslCitationAffixes'] = $citationAffixes;
            $attrs['cslCitationAffixSummary'] = $this->citationAffixSummaryForRows($citationAffixes);
        }
        $locatorDiagnostics = $this->citationLocatorDiagnostics(new AstNode('citation_group', $group->attrs, $citations));
        if ($locatorDiagnostics !== []) {
            $attrs['cslLocatorDiagnostics'] = $locatorDiagnostics;
            $attrs['cslLocatorDiagnosticSummary'] = $this->citationLocatorDiagnosticSummaryForDiagnostics($locatorDiagnostics);
            $attrs['cslLocatorDiagnosticReasons'] = $this->citationLocatorDiagnosticReasonsForDiagnostics($locatorDiagnostics);
        }

        return new AstNode(
            'citation_group',
            $attrs,
            array_map(fn (AstNode $citation): AstNode => $this->normalizeCitation($citation), $citations)
        );
    }

    public function apply(AstNode $document): AstNode
    {
        $document = $this->coalesceMarkdownCitationClusters($document);
        $state = $this->emptyCitationPositionState();
        $positioned = $this->annotateCitationPositions($document, $state);
        $firstReferenceNoteNumbers = $this->firstReferenceNoteNumbersForDocument($positioned);
        $citationNumbers = $this->citationNumbersForIds($this->sortBibliographyIds($this->uniqueKnownCitationIds($positioned), $firstReferenceNoteNumbers));
        $numbered = $this->annotateCitationNumbers($positioned, $citationNumbers);
        $ids = $this->uniqueKnownCitationIds($numbered);
        $givenNameDisambiguationModes = $this->givenNameDisambiguationModesForIds($ids);
        $annotated = $this->annotateCitationGivenNameDisambiguation($numbered, $givenNameDisambiguationModes);
        $nameCounts = $this->nameDisambiguationCountsForIds($ids, $givenNameDisambiguationModes);
        $annotated = $this->annotateCitationNameDisambiguation($annotated, $nameCounts);
        $yearSuffixes = $this->yearSuffixesForIds($ids, $nameCounts, $givenNameDisambiguationModes);
        $annotated = $this->annotateCitationYearSuffixes($annotated, $yearSuffixes);
        $disambiguatingIds = $this->disambiguatingCitationIdsForIds($ids, $nameCounts, $givenNameDisambiguationModes);
        $annotated = $this->annotateCitationDisambiguation($annotated, $disambiguatingIds);

        return $this->mapNode($annotated);
    }

    public function appendBibliography(AstNode $document, string $headingText = 'References'): AstNode
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Expected document AST node');
        }

        $processed = $this->apply($document);
        $blocks = $this->bibliographyBlocks($processed, $headingText);
        if ($blocks === []) {
            return $processed;
        }

        return new AstNode('document', $processed->attrs, [
            ...$processed->children,
            ...$blocks,
        ]);
    }

    /**
     * @return list<AstNode>
     */
    public function bibliographyBlocks(AstNode $document, string $headingText = 'References'): array
    {
        $ids = $this->uniqueKnownCitationIds($document);
        $yearSuffixes = $this->yearSuffixesForIds($ids);
        $firstReferenceNoteNumbers = $this->firstReferenceNoteNumbersForDocument($document);
        $ids = array_values(array_filter(
            $this->sortBibliographyIds($ids, $firstReferenceNoteNumbers),
            fn (string $id): bool => !$this->itemSkipsBibliography($id)
        ));
        if ($ids === []) {
            return [];
        }

        return [
            new AstNode('heading', [
                'level' => 2,
                'id' => $this->slugify($headingText),
                'text' => $headingText,
            ], [
                new AstNode('text', ['text' => $headingText]),
            ]),
            $this->bibliographyDefinitionList($ids, $yearSuffixes, $firstReferenceNoteNumbers),
        ];
    }

    public function appendShorthandList(AstNode $document, string $headingText = 'List of Shorthands'): AstNode
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Expected document AST node');
        }

        $blocks = $this->shorthandListBlocks($headingText);
        if ($blocks === []) {
            return $document;
        }

        return new AstNode('document', $document->attrs, [
            ...$document->children,
            ...$blocks,
        ]);
    }

    /**
     * @return list<AstNode>
     */
    public function shorthandListBlocks(string $headingText = 'List of Shorthands'): array
    {
        $ids = $this->shorthandListIds();
        if ($ids === []) {
            return [];
        }

        return [
            new AstNode('heading', [
                'level' => 2,
                'id' => $this->slugify($headingText),
                'text' => $headingText,
            ], [
                new AstNode('text', ['text' => $headingText]),
            ]),
            $this->shorthandDefinitionList($ids),
        ];
    }

    /**
     * @param list<string> $ids
     */
    public function shorthandDefinitionList(array $ids = []): AstNode
    {
        $ids = $ids === [] ? $this->shorthandListIds() : $ids;
        $filteredIds = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            $item = $this->itemsById[$id] ?? null;
            if ($item === null || trim((string) ($item['shorthand'] ?? '')) === '') {
                continue;
            }

            $filteredIds[] = $id;
        }

        $items = [];
        foreach ($this->sortShorthandListIds($filteredIds) as $id) {
            $items[] = $this->shorthandDefinitionItem($this->itemsById[$id]);
        }

        return new AstNode('definition_list', [
            'classes' => ['pandoc-csl-shorthand-list'],
        ], $items);
    }

    /**
     * @param list<AstNode> $citations
     */
    public function renderCitationCluster(array $citations): string
    {
        $citations = $this->ensureClusterCitationPositions($citations);
        $citations = $this->sortCitationCluster($citations);
        $citations = $this->annotateCitationNumbersForCluster($citations);
        $citations = $this->annotateCitationGivenNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationYearSuffixesForCluster($citations);
        $citations = $this->annotateCitationDisambiguationForCluster($citations);
        $collapsedEntries = $this->renderCollapsedCitationEntries($citations);
        if ($collapsedEntries !== null) {
            if ($collapsedEntries === []) {
                return '';
            }

            return $this->style->citationPrefix()
                . $this->joinCollapsedCitationEntries($collapsedEntries)
                . $this->style->citationSuffix();
        }

        $entries = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                throw new \InvalidArgumentException('Citation cluster entries must be citation AST nodes');
            }

            $entries[] = $this->renderCitationEntry($citation);
        }

        if ($entries === []) {
            return '';
        }

        if ($this->citationClusterSuppressesWrapper($citations)) {
            return implode($this->style->citationDelimiter(), $entries);
        }

        return $this->style->citationPrefix()
            . implode($this->style->citationDelimiter(), $entries)
            . $this->style->citationSuffix();
    }

    /**
     * @param list<AstNode> $citations
     * @return list<array{text:string, formatting?:array<string, string>}>
     */
    private function citationClusterInlineParts(array $citations): array
    {
        $citations = $this->ensureClusterCitationPositions($citations);
        $citations = $this->sortCitationCluster($citations);
        $citations = $this->annotateCitationNumbersForCluster($citations);
        $citations = $this->annotateCitationGivenNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationYearSuffixesForCluster($citations);
        $citations = $this->annotateCitationDisambiguationForCluster($citations);

        if ($this->renderCollapsedCitationEntries($citations) !== null) {
            return [];
        }

        $entries = [];
        $hasFormattedPart = false;
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                throw new \InvalidArgumentException('Citation cluster entries must be citation AST nodes');
            }

            $entryParts = $this->citationEntryInlineParts($citation);
            if ($entryParts === []) {
                continue;
            }

            foreach ($entryParts as $part) {
                if (isset($part['formatting']) && $part['formatting'] !== []) {
                    $hasFormattedPart = true;
                    break;
                }
            }

            $entries[] = $entryParts;
        }

        if ($entries === [] || !$hasFormattedPart) {
            return [];
        }

        if ($this->citationClusterSuppressesWrapper($citations)) {
            $parts = [];
            foreach ($entries as $index => $entryParts) {
                if ($index > 0) {
                    $this->appendCitationInlinePart($parts, $this->style->citationDelimiter());
                }
                foreach ($entryParts as $part) {
                    $this->appendCitationInlinePart($parts, $part['text'], $part['formatting'] ?? []);
                }
            }

            return $parts;
        }

        $parts = [];
        $this->appendCitationInlinePart($parts, $this->style->citationPrefix());
        foreach ($entries as $index => $entryParts) {
            if ($index > 0) {
                $this->appendCitationInlinePart($parts, $this->style->citationDelimiter());
            }

            foreach ($entryParts as $part) {
                $this->appendCitationInlinePart($parts, $part['text'], $part['formatting'] ?? []);
            }
        }
        $this->appendCitationInlinePart($parts, $this->style->citationSuffix());

        return $parts;
    }

    /**
     * @param list<AstNode> $citations
     */
    private function citationClusterSuppressesWrapper(array $citations): bool
    {
        if ($citations === []) {
            return false;
        }
        if (count($citations) === 1 && in_array(
            (string) $citations[0]->attr('mode', 'normal'),
            ['author_in_text', 'author_only'],
            true
        )) {
            return true;
        }

        foreach ($citations as $citation) {
            if ($citation->attr('suppressWrapper') !== true) {
                return false;
            }
        }

        return true;
    }

    public function renderBibliographyEntry(string $id): string
    {
        $item = $this->itemsById[$id] ?? null;
        if ($item === null) {
            throw new \InvalidArgumentException('Unknown CSL item id: ' . $id);
        }

        $canonicalId = $this->canonicalCitationId($id);
        $yearSuffixes = $this->yearSuffixesForIds($this->primaryIds);
        $item = $this->itemWithYearSuffix($item, $yearSuffixes[$canonicalId] ?? '');
        $item = $this->itemWithCitationNumber($item, $this->citationNumberForId($canonicalId));

        return $this->renderBibliographyEntryForItem($item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderBibliographyEntryForItem(array $item, ?array &$bibliographyState = null): string
    {
        $customEntry = $this->renderCustomBibliographyEntry($item, $bibliographyState);
        if ($customEntry !== null) {
            return $customEntry;
        }

        $parts = [];
        $authors = $this->bibliographyAuthors($item, $bibliographyState);
        if ($authors !== '') {
            $parts[] = rtrim($authors, '.') . '.';
        }

        $title = (string) $item['title'];
        if ($title !== '') {
            $parts[] = $title . '.';
        }

        $titleAddon = (string) $item['titleAddon'];
        if ($titleAddon !== '') {
            $parts[] = $titleAddon . '.';
        }

        $container = (string) $item['containerTitle'];
        if ($container !== '') {
            $parts[] = $container . '.';
        }

        $containerTitleAddon = (string) $item['containerTitleAddon'];
        if ($containerTitleAddon !== '') {
            $parts[] = $containerTitleAddon . '.';
        }

        foreach ($this->publicationDetailBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        foreach ($this->eventBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        $publisher = (string) $item['publisher'];
        $year = $this->citationYear($item);
        $hasDate = $this->hasIssuedDate($item);
        if ($publisher !== '' && $hasDate) {
            $parts[] = $publisher . ', ' . $year . '.';
        } elseif ($publisher !== '') {
            $parts[] = $publisher . '.';
        } elseif ($hasDate) {
            $parts[] = $year . '.';
        }

        $publisherPlaceList = $item['publisherPlaceList'] ?? [];
        if (is_array($publisherPlaceList) && count($publisherPlaceList) > 1) {
            $places = array_values(array_filter(
                array_map(static fn (mixed $place): string => trim((string) $place), $publisherPlaceList),
                static fn (string $place): bool => $place !== ''
            ));
            if ($places !== []) {
                $parts[] = 'Publisher places: ' . implode('; ', $places) . '.';
            }
        }

        $page = (string) $item['page'];
        if ($page !== '') {
            $parts[] = $page . '.';
        }

        $legalPatentMetadata = $this->legalPatentBibliographyParts($item);
        foreach ($legalPatentMetadata as $part) {
            $parts[] = $part;
        }

        foreach ($this->reviewMetadataBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        $entrySetSummary = $this->entrySetSummary($item);
        if ($entrySetSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($entrySetSummary);
        }

        $missingXdataKeys = $item['missingXdataKeys'] ?? [];
        $xdataSummary = is_array($missingXdataKeys) && $missingXdataKeys !== []
            ? $this->xdataSummary($item)
            : '';
        if ($xdataSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($xdataSummary);
        }

        $source = trim((string) ($item['source'] ?? ''));
        if ($source !== '') {
            $parts[] = 'Source: ' . rtrim($source, '.') . '.';
        }

        foreach ($this->relatedBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        foreach ($this->crossrefBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        foreach ($this->xrefBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        foreach ($this->nameMetadataBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        $translators = $this->bibliographyTranslators($item);
        if ($translators !== '') {
            $parts[] = 'Translated by ' . rtrim($translators, '.') . '.';
        }

        foreach ($this->bibliographyRoleNameParts($item) as $part) {
            $parts[] = $part;
        }

        $originalTitle = (string) $item['originalTitle'];
        if ($originalTitle !== '') {
            $parts[] = 'Original title: ' . $originalTitle . '.';
        }

        $originalTitleAddon = (string) ($item['originalTitleAddon'] ?? '');
        if ($originalTitleAddon !== '') {
            $parts[] = 'Original title addendum: ' . $originalTitleAddon . '.';
        }

        $originalGenre = (string) ($item['originalGenre'] ?? '');
        if ($originalGenre !== '') {
            $parts[] = 'Original genre: ' . $originalGenre . '.';
        }

        $originalCollectionTitle = (string) ($item['originalCollectionTitle'] ?? '');
        $originalCollectionNumber = (string) ($item['originalCollectionNumber'] ?? '');
        if ($originalCollectionTitle !== '' && $originalCollectionNumber !== '') {
            $parts[] = 'Original series: ' . $originalCollectionTitle . ', ' . $this->style->term('number', 'short') . ' ' . $originalCollectionNumber . '.';
        } elseif ($originalCollectionTitle !== '') {
            $parts[] = 'Original series: ' . rtrim($originalCollectionTitle, '.') . '.';
        } elseif ($originalCollectionNumber !== '') {
            $parts[] = 'Original series ' . $this->style->term('number', 'short') . ' ' . $originalCollectionNumber . '.';
        }

        $originalDate = $item['originalDate'] ?? null;
        if (is_array($originalDate) && (string) ($originalDate['display'] ?? '') !== '') {
            $parts[] = 'Original work published ' . (string) $originalDate['display'] . '.';
        }

        $originalPublisher = (string) $item['originalPublisher'];
        $originalPublisherPlace = (string) $item['originalPublisherPlace'];
        if ($originalPublisher !== '' && $originalPublisherPlace !== '') {
            $parts[] = 'Original publisher: ' . $originalPublisher . ', ' . $originalPublisherPlace . '.';
        } elseif ($originalPublisher !== '') {
            $parts[] = 'Original publisher: ' . $originalPublisher . '.';
        } elseif ($originalPublisherPlace !== '') {
            $originalPublisherPlaceList = $item['originalPublisherPlaceList'] ?? [];
            $places = is_array($originalPublisherPlaceList)
                ? array_values(array_filter(
                    array_map(static fn (mixed $place): string => trim((string) $place), $originalPublisherPlaceList),
                    static fn (string $place): bool => $place !== ''
                ))
                : [];
            $plural = count($places) > 1 || str_contains($originalPublisherPlace, ';');
            $parts[] = ($plural ? 'Original publisher places: ' : 'Original publisher place: ') . $originalPublisherPlace . '.';
        }

        $originalLanguage = (string) $item['originalLanguage'];
        if ($originalLanguage !== '') {
            $parts[] = 'Original language: ' . $originalLanguage . '.';
        }

        $originalIsbn = (string) ($item['originalIsbn'] ?? '');
        if ($originalIsbn !== '') {
            $parts[] = 'Original ISBN: ' . $originalIsbn . '.';
        }

        $originalIssn = (string) ($item['originalIssn'] ?? '');
        if ($originalIssn !== '') {
            $parts[] = 'Original ISSN: ' . $originalIssn . '.';
        }

        $originalDoi = (string) ($item['originalDoi'] ?? '');
        if ($originalDoi !== '') {
            $parts[] = 'Original DOI: ' . $originalDoi . '.';
        }

        $originalUrl = (string) ($item['originalUrl'] ?? '');
        if ($originalUrl !== '') {
            $parts[] = 'Original URL: ' . $originalUrl . '.';
        }

        $doi = (string) $item['doi'];
        if ($doi !== '') {
            $parts[] = 'DOI ' . $doi . '.';
        }

        $url = (string) $item['url'];
        if ($url !== '') {
            $urlLabel = trim((string) ($item['urlLabel'] ?? ''));
            if ($urlLabel !== '') {
                $parts[] = 'URL label: ' . $this->withTerminalPunctuation($urlLabel);
            }

            $parts[] = $url . '.';
        }

        foreach ($this->identifierBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        $accessedDate = $item['accessedDate'] ?? null;
        if (is_array($accessedDate) && (string) ($accessedDate['display'] ?? '') !== '') {
            $parts[] = $this->style->term('accessed') . ' ' . (string) $accessedDate['display'] . '.';
        }

        return $this->style->formatBibliographyEntry(implode($this->style->bibliographyDelimiter(), $parts));
    }

    /**
     * @param list<string> $ids
     * @param array<string, string> $yearSuffixes
     * @param array<string, string> $firstReferenceNoteNumbers
     */
    public function bibliographyDefinitionList(array $ids, array $yearSuffixes = [], array $firstReferenceNoteNumbers = []): AstNode
    {
        $ids = array_values(array_filter(
            $ids,
            fn (string $id): bool => !$this->itemSkipsBibliography($id)
        ));

        if ($yearSuffixes === []) {
            $yearSuffixes = $this->yearSuffixesForIds($ids);
        }

        $citationNumbers = $this->citationNumbersForIds($ids);
        $items = [];
        $bibliographyState = $this->emptyBibliographySubstitutionState();
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            $item = $this->itemsById[$canonicalId] ?? null;
            if ($item === null) {
                continue;
            }

            $item = $this->itemWithYearSuffix($item, $yearSuffixes[$id] ?? $yearSuffixes[$canonicalId] ?? '');
            $item = $this->itemWithCitationNumber($item, $citationNumbers[$canonicalId] ?? '');
            $item = $this->itemWithFirstReferenceNoteNumber($item, $firstReferenceNoteNumbers[$canonicalId] ?? $firstReferenceNoteNumbers[$id] ?? '');
            $label = $this->citationLabel($item);
            $this->resetBibliographySubstitutionEntry($bibliographyState);
            $entry = $this->renderBibliographyEntryForItem($item, $bibliographyState);
            $attrs = ['term' => $label, 'cslId' => $id];
            $displayParts = $this->bibliographyDisplayParts($item);
            if ($displayParts !== []) {
                $attrs['cslDisplayParts'] = $displayParts;
            }

            $items[] = new AstNode('definition_item', $attrs, [
                new AstNode('term', ['text' => $label], [
                    new AstNode('text', [
                        'text' => $label,
                        'preserveSmartPunctuation' => true,
                    ]),
                ]),
                new AstNode('definition', [], [
                    new AstNode('paragraph', [], [
                        new AstNode('text', [
                            'text' => $entry,
                            'preserveSmartPunctuation' => true,
                        ]),
                    ]),
                ]),
            ]);
        }

        $options = $this->style->bibliographyOptions();
        $attrs = [
            'classes' => ['pandoc-csl-bibliography'],
        ];
        if ($options['hangingIndent']) {
            $attrs['hangingIndent'] = true;
        }
        if ($options['entrySpacing'] !== null) {
            $attrs['entrySpacing'] = $options['entrySpacing'];
        }
        if ($options['lineSpacing'] !== null) {
            $attrs['lineSpacing'] = $options['lineSpacing'];
        }
        if ($options['secondFieldAlign'] !== '') {
            $attrs['secondFieldAlign'] = $options['secondFieldAlign'];
        }

        return new AstNode('definition_list', $attrs, $items);
    }

    /**
     * @return array{previousRenderedNames:string, previousRenderedNameParts:list<string>, entrySubstitutionChecked:bool}
     */
    private function emptyBibliographySubstitutionState(): array
    {
        return [
            'previousRenderedNames' => '',
            'previousRenderedNameParts' => [],
            'entrySubstitutionChecked' => false,
        ];
    }

    /**
     * @param array{previousRenderedNames:string, previousRenderedNameParts:list<string>, entrySubstitutionChecked:bool} $state
     */
    private function resetBibliographySubstitutionEntry(array &$state): void
    {
        $state['entrySubstitutionChecked'] = false;
    }

    /**
     * @param list<string> $renderedNameParts
     * @param array{previousRenderedNames:string, previousRenderedNameParts:list<string>, entrySubstitutionChecked:bool} $state
     * @return array{type:string, value?:string, parts?:list<string>}
     */
    private function bibliographySubsequentAuthorSubstitutionPlan(array $renderedNameParts, string $renderedNames, array &$state): array
    {
        if ($renderedNames === '' || ($state['entrySubstitutionChecked'] ?? false) === true) {
            return ['type' => 'none'];
        }

        $state['entrySubstitutionChecked'] = true;
        $previous = (string) ($state['previousRenderedNames'] ?? '');
        $previousParts = is_array($state['previousRenderedNameParts'] ?? null)
            ? array_values(array_map('strval', $state['previousRenderedNameParts']))
            : [];
        $state['previousRenderedNames'] = $renderedNames;
        $state['previousRenderedNameParts'] = array_values($renderedNameParts);

        $options = $this->style->bibliographyOptions();
        $substitute = (string) ($options['subsequentAuthorSubstitute'] ?? '');
        $rule = (string) ($options['subsequentAuthorSubstituteRule'] ?? 'complete-all');
        if ($substitute === '' || $previous === '') {
            return ['type' => 'none'];
        }

        $completeMatch = $this->normalizedRenderedNameKey($renderedNames) === $this->normalizedRenderedNameKey($previous);
        if ($rule === 'complete-all') {
            return $completeMatch ? ['type' => 'full', 'value' => $substitute] : ['type' => 'none'];
        }

        if ($rule === 'complete-each') {
            return $completeMatch && $renderedNameParts !== []
                ? ['type' => 'parts', 'parts' => array_fill(0, count($renderedNameParts), $substitute)]
                : ['type' => 'none'];
        }

        if ($rule !== 'partial-each' && $rule !== 'partial-first') {
            return ['type' => 'none'];
        }

        $matchCount = 0;
        $limit = min(count($renderedNameParts), count($previousParts));
        for ($index = 0; $index < $limit; $index++) {
            if ($this->normalizedRenderedNameKey($renderedNameParts[$index]) !== $this->normalizedRenderedNameKey($previousParts[$index])) {
                break;
            }

            $matchCount++;
        }

        if ($matchCount === 0) {
            return ['type' => 'none'];
        }

        $substituted = $renderedNameParts;
        $substitutionCount = $rule === 'partial-first' ? 1 : $matchCount;
        for ($index = 0; $index < $substitutionCount; $index++) {
            $substituted[$index] = $substitute;
        }

        return ['type' => 'parts', 'parts' => $substituted];
    }

    private function normalizedRenderedNameKey(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function normalizeItem(array $item, int $index): array
    {
        $id = $item['id'] ?? null;
        if (!is_string($id) && !is_int($id)) {
            $id = self::derivedCslItemId($item, $index);
            $item['id'] = $id;
        }

        $id = trim((string) $id);
        if ($id === '') {
            throw new \InvalidArgumentException('CSL item at index ' . $index . ' has an empty id');
        }

        $issuedDate = self::dateVariable(self::firstPresentField($item, ['issued', 'issuedDate', 'issued-date', 'issueddate', 'date']), $id, 'issued');
        $sourceFilePolicy = self::sourceFilePolicy($item, $id);
        $sourceFileDiagnostics = [
            ...$sourceFilePolicy['diagnostics'],
            ...self::sourceFileDiagnostics($item['sourceFileDiagnostics'] ?? [], $id),
        ];
        $page = self::firstStringField($item, ['page', 'pages']);
        $containerTitleShort = self::firstStringField($item, [
            'container-title-short',
            'containerTitleShort',
            'containertitleshort',
            'book-title-short',
            'bookTitleShort',
            'booktitleshort',
            'container-title-abbreviation',
            'containerTitleAbbreviation',
            'containertitleabbreviation',
            'journalAbbreviation',
            'journal-abbreviation',
            'journalabbreviation',
            'shortjournal',
            'shortJournal',
            'short-journal',
            'shortbooktitle',
            'shortBookTitle',
            'short-book-title',
            'shortjournaltitle',
            'shortJournalTitle',
            'short-journal-title',
            'journaltitleshort',
            'journalTitleShort',
            'journal-title-short',
        ]);
        $publisher = self::firstStringField($item, ['publisher', 'institution', 'organization', 'school']);
        $publisherPlace = self::firstStringField($item, [
            'publisher-place',
            'publisherPlace',
            'publisherplace',
            'publisher-location',
            'publisherLocation',
            'publisherlocation',
            'publication-place',
            'publicationPlace',
            'publicationplace',
            'pubplace',
            'pubPlace',
            'address',
            'location',
        ]);
        $originalPublisher = self::firstStringField($item, ['original-publisher', 'originalPublisher', 'originalpublisher', 'origpublisher', 'origPublisher']);
        $originalPublisherPlace = self::firstStringField($item, ['original-publisher-place', 'originalPublisherPlace', 'originalpublisherplace', 'origlocation', 'origLocation', 'origaddress', 'origAddress']);
        $originalCollectionTitle = self::firstStringField($item, ['original-collection-title', 'originalCollectionTitle', 'originalcollectiontitle', 'origseries', 'origSeries', 'orig-series', 'original-series', 'originalSeries', 'originalseries']);
        $originalCollectionNumber = self::firstStringField($item, ['original-collection-number', 'originalCollectionNumber', 'originalcollectionnumber', 'origseriesnumber', 'origSeriesNumber', 'orig-series-number', 'original-series-number', 'originalSeriesNumber', 'originalseriesnumber']);
        $originalEdition = self::firstStringField($item, ['original-edition', 'originalEdition', 'originaledition', 'origedition', 'origEdition', 'orig-edition']);
        $originalIsbn = self::firstStringField($item, ['original-isbn', 'originalISBN', 'originalIsbn', 'originalisbn', 'original-ISBN', 'origisbn', 'origIsbn', 'origISBN', 'orig-isbn']);
        $originalIssn = self::firstStringField($item, ['original-issn', 'originalISSN', 'originalIssn', 'originalissn', 'original-ISSN', 'origissn', 'origIssn', 'origISSN', 'orig-issn']);
        $originalDoi = self::firstStringField($item, ['original-doi', 'originalDOI', 'originalDoi', 'originaldoi', 'original-DOI', 'origdoi', 'origDoi', 'origDOI', 'orig-doi']);
        $originalUrl = self::firstStringField($item, ['original-url', 'originalURL', 'originalUrl', 'originalurl', 'original-URL', 'origurl', 'origUrl', 'origURL', 'orig-url']);
        $archive = self::firstStringField($item, ['archive', 'archiveprefix', 'archive-prefix', 'archivePrefix', 'eprinttype', 'eprint-type', 'eprintType']);
        $archiveCollection = self::firstStringField($item, ['archive_collection', 'archive-collection', 'archiveCollection', 'archivecollection']);
        $archivePlace = self::firstStringField($item, ['archive-place', 'archivePlace', 'archiveplace', 'eprintclass', 'eprint-class', 'eprintClass', 'primaryclass', 'primary-class', 'primary_class', 'primaryClass']);
        $archiveLocation = self::firstStringField($item, ['archive_location', 'archive-location', 'archiveLocation', 'archivelocation', 'eprint']);
        $archiveSummary = self::firstStringField($item, ['archive-summary', 'archiveSummary', 'archivesummary', 'eprint-summary', 'eprintSummary', 'eprintsummary'])
            ?: self::archiveSummary($archive, $archiveCollection, $archivePlace, $archiveLocation);
        $publisherList = self::stringListFromFirstField($item, [
            'publisher-list',
            'publisherList',
            'publisherlist',
            'institution-list',
            'institutionList',
            'institutionlist',
            'organization-list',
            'organizationList',
            'organizationlist',
            'school-list',
            'schoolList',
            'schoollist',
        ]);
        if ($publisher === '' && $publisherList !== []) {
            $publisher = implode('; ', $publisherList);
        }
        $publisherPlaceList = self::stringListFromFirstField($item, [
            'publisher-place-list',
            'publisherPlaceList',
            'publisherplacelist',
            'publisher-location-list',
            'publisherLocationList',
            'publisherlocationlist',
            'publication-place-list',
            'publicationPlaceList',
            'publicationplacelist',
            'pubplace-list',
            'pubPlaceList',
            'pubplacelist',
            'address-list',
            'addressList',
            'addresslist',
            'location-list',
            'locationList',
            'locationlist',
        ]);
        if ($publisherPlace === '' && $publisherPlaceList !== []) {
            $publisherPlace = implode('; ', $publisherPlaceList);
        }
        $originalPublisherList = self::stringListFromFirstField($item, ['original-publisher-list', 'originalPublisherList', 'originalpublisherlist', 'origpublisherlist', 'origPublisherList']);
        $originalPublisherPlaceList = self::stringListFromFirstField($item, ['original-publisher-place-list', 'originalPublisherPlaceList', 'originalpublisherplacelist', 'origlocationlist', 'origLocationList', 'origaddresslist', 'origAddressList']);
        $languageList = self::stringListFromFirstField($item, ['language-list', 'languageList', 'languagelist']);
        $language = self::firstStringField($item, ['language', 'langid', 'language-id', 'languageId', 'languageid', 'hyphenation']);
        if ($language === '' && $languageList !== []) {
            $language = implode('; ', $languageList);
        }
        $originalLanguageList = self::stringListFromFirstField($item, ['original-language-list', 'originalLanguageList', 'originallanguagelist', 'origlanguagelist', 'origLanguageList']);
        $originalLanguage = self::firstStringField($item, ['original-language', 'originalLanguage', 'originallanguage', 'origlanguage', 'origLanguage']);
        if ($originalLanguage === '' && $originalLanguageList !== []) {
            $originalLanguage = implode('; ', $originalLanguageList);
        }
        $originalGenre = self::firstStringField($item, ['original-genre', 'originalGenre', 'origtype', 'origType', 'origgenre', 'origGenre']);
        $eventPlace = self::firstStringField($item, ['event-place', 'eventPlace', 'eventplace', 'event-location', 'eventLocation', 'eventlocation', 'event-venue', 'eventVenue', 'eventvenue', 'venue']);
        $eventPlaceList = self::stringListFromFirstField($item, ['event-place-list', 'eventPlaceList', 'eventplacelist', 'event-location-list', 'eventLocationList', 'eventlocationlist', 'event-venue-list', 'eventVenueList', 'eventvenuelist', 'venue-list', 'venueList']);
        if ($eventPlace === '' && $eventPlaceList !== []) {
            $eventPlace = implode('; ', $eventPlaceList);
        }
        $authorityValue = self::firstPresentField($item, [
            'authority-list',
            'authorityList',
            'authoritylist',
            'issuing-authority-list',
            'issuingAuthorityList',
            'issuingauthoritylist',
            'authority',
            'issuing-authority',
            'issuingAuthority',
            'issuingauthority',
        ]);
        $authorityNames = self::namesOrLiteral($authorityValue, $id, 'authority');
        $authority = self::stringOrNamesValue($authorityValue, 'authority', $id, $authorityNames);
        $accessedDate = self::dateVariable(self::firstPresentField($item, [
            'accessed',
            'accessedDate',
            'accessed-date',
            'accesseddate',
            'accessDate',
            'access-date',
            'accessdate',
            'URLDate',
            'URL-date',
            'URLDATE',
            'urlDate',
            'url-date',
            'urldate',
            'lastchecked',
            'lastaccessed',
            'visited',
        ]), $id, 'accessed');
        $availableDate = self::dateVariable(self::firstPresentField($item, ['available-date', 'availableDate', 'availabledate']), $id, 'available-date');
        $acceptedDate = self::dateVariable(self::firstPresentField($item, ['accepted-date', 'acceptedDate', 'accepteddate', 'date-accepted', 'dateAccepted', 'dateaccepted']), $id, 'accepted-date');
        $revisedDate = self::dateVariable(self::firstPresentField($item, ['revised-date', 'revisedDate', 'reviseddate', 'revision-date', 'revisionDate', 'revisiondate', 'date-revised', 'dateRevised', 'daterevised', 'revdate']), $id, 'revised-date');
        $originalDate = self::dateVariable(self::firstPresentField($item, ['original-date', 'originalDate', 'originaldate', 'origdate', 'origDate']), $id, 'original-date');
        $reprintDate = self::dateVariable(self::firstPresentField($item, ['reprint-date', 'reprintDate', 'reprintdate']), $id, 'reprint-date');
        $submittedDate = self::dateVariable(self::firstPresentField($item, ['submitted', 'submitted-date', 'submittedDate', 'submitteddate']), $id, 'submitted');
        $eventDate = self::dateVariable(self::firstPresentField($item, ['event-date', 'eventDate', 'eventdate']), $id, 'event-date');
        $labelDate = self::dateVariable(self::firstPresentField($item, ['label-date', 'labelDate', 'labeldate']), $id, 'label-date');
        $dateMarkerSummary = self::dateMarkerSummary([
            'issued' => $issuedDate,
            'accessed' => $accessedDate,
            'available-date' => $availableDate,
            'accepted-date' => $acceptedDate,
            'revised-date' => $revisedDate,
            'original-date' => $originalDate,
            'reprint-date' => $reprintDate,
            'submitted' => $submittedDate,
            'event-date' => $eventDate,
            'label-date' => $labelDate,
        ]);
        $dateTimeSummary = self::dateTimeSummary([
            'issued' => $issuedDate,
            'accessed' => $accessedDate,
            'available-date' => $availableDate,
            'accepted-date' => $acceptedDate,
            'revised-date' => $revisedDate,
            'original-date' => $originalDate,
            'reprint-date' => $reprintDate,
            'submitted' => $submittedDate,
            'event-date' => $eventDate,
            'label-date' => $labelDate,
        ]);
        $dateSeasonSummary = self::dateSeasonSummary([
            'issued' => $issuedDate,
            'accessed' => $accessedDate,
            'available-date' => $availableDate,
            'accepted-date' => $acceptedDate,
            'revised-date' => $revisedDate,
            'original-date' => $originalDate,
            'reprint-date' => $reprintDate,
            'submitted' => $submittedDate,
            'event-date' => $eventDate,
            'label-date' => $labelDate,
        ]);
        $dateEraSummary = self::dateEraSummary([
            'issued' => $issuedDate,
            'accessed' => $accessedDate,
            'available-date' => $availableDate,
            'accepted-date' => $acceptedDate,
            'revised-date' => $revisedDate,
            'original-date' => $originalDate,
            'reprint-date' => $reprintDate,
            'submitted' => $submittedDate,
            'event-date' => $eventDate,
            'label-date' => $labelDate,
        ]);
        $keywords = self::stringListFromFirstField($item, ['keyword', 'keywords', 'keyword-list', 'keywordList', 'keywordlist']);
        $categories = self::stringListFromFirstField($item, ['categories', 'category', 'category-list', 'categoryList', 'categorylist']);
        $biblatexOptions = self::stringListFromFirstField($item, ['biblatexOptions', 'biblatex-options', 'biblatexoptions']);
        $biblatexSkipBibliography = self::biblatexSkipBibliography($biblatexOptions);
        $biblatexLanguageOptions = self::stringListFromFirstField($item, [
            'biblatexLanguageOptions',
            'biblatex-language-options',
            'biblatexlanguageoptions',
            'langidopts',
            'language-options',
            'languageOptions',
        ]);
        $biblatexGender = self::firstStringField($item, ['biblatexGender', 'biblatex-gender', 'gender']);
        $biblatexCustomFields = self::biblatexCustomFields($item, $id);
        $biblatexCustomLists = self::biblatexCustomLists($item, $id);
        $biblatexCustomNames = self::biblatexCustomNames($item, $id);
        $biblatexFieldAnnotations = self::biblatexFieldAnnotations($item, $id);
        $biblatexRefsection = self::firstStringField($item, ['biblatexRefsection', 'biblatex-refsection', 'refsection', 'ref-section']);
        $biblatexRefsegment = self::firstStringField($item, ['biblatexRefsegment', 'biblatex-refsegment', 'refsegment', 'ref-segment']);
        $citationAliases = self::stringListFromFirstField($item, ['citation-aliases', 'citationAliases', 'citation-alias', 'citationAlias', 'citationalias', 'ids']);
        $xdataKeys = self::stringListFromFirstField($item, ['xdataKeys', 'xdata-keys', 'xdata']);
        $xdataItems = self::relatedItemSummaries($item['xdataItems'] ?? $item['xdata-items'] ?? [], $id, 'xdataItems');
        $missingXdataKeys = self::stringListFromFirstField($item, ['missingXdataKeys', 'missing-xdata-keys']);
        $entrySetKeys = self::stringListFromFirstField($item, ['entrySet', 'entry-set', 'entryset']);
        $entrySetItems = self::relatedItemSummaries($item['entrySetItems'] ?? $item['entry-set-items'] ?? [], $id, 'entrySetItems');
        $missingEntrySetKeys = self::stringListFromFirstField($item, ['missingEntrySetKeys', 'missing-entry-set-keys', 'missing-entryset-keys']);
        $crossrefKeys = self::stringListFromFirstField($item, ['crossrefKeys', 'crossref-keys', 'crossref']);
        $crossrefItems = self::relatedItemSummaries($item['crossrefItems'] ?? $item['crossref-items'] ?? [], $id, 'crossrefItems');
        $missingCrossrefKeys = self::stringListFromFirstField($item, ['missingCrossrefKeys', 'missing-crossref-keys']);
        $itemType = self::stringField($item, 'type');
        $genre = self::stringField($item, 'genre');
        $patentType = self::firstStringField($item, ['patent-type', 'patentType', 'patenttype']);
        if ($patentType === '' && $itemType === 'patent') {
            $patentType = $genre;
        }
        $patentTypeLabel = self::firstStringField($item, ['patent-type-label', 'patentTypeLabel', 'patenttypelabel']);
        if ($patentTypeLabel === '') {
            $patentTypeLabel = self::patentTypeLabel($patentType);
        }
        $reviewedTitle = self::composedStringField(
            $item,
            ['reviewed-title', 'reviewedTitle', 'reviewedtitle', 'reviewtitle'],
            ['reviewed-subtitle', 'reviewedSubtitle', 'reviewedsubtitle', 'reviewsubtitle']
        );
        $reviewedGenre = self::firstStringField($item, ['reviewed-genre', 'reviewedGenre', 'reviewedgenre', 'reviewgenre']);
        $risFieldProvenance = is_array($item['risFieldProvenance'] ?? null) ? array_values($item['risFieldProvenance']) : [];
        $orcid = self::firstStringField($item, ['ORCID', 'orcid', 'orcid-id', 'orcidId', 'orcidid']);
        $isni = self::firstStringField($item, ['ISNI', 'isni']);
        $viaf = self::firstStringField($item, ['VIAF', 'viaf']);
        $ror = self::firstStringField($item, ['ROR', 'ror']);
        $wikidata = self::firstStringField($item, ['Wikidata', 'wikidata', 'wikidata-id', 'wikidataId', 'wikidataid', 'wd']);
        $translatedTitleKeys = ['translated-title', 'translatedTitle', 'translatedtitle', 'title-translation', 'titleTranslation', 'titletranslation'];
        $translatedSubtitleKeys = [
            'translated-subtitle',
            'translatedSubtitle',
            'translatedsubtitle',
            'title-translation-subtitle',
            'titleTranslationSubtitle',
            'titletranslationsubtitle',
            'subtitle-translation',
            'subtitleTranslation',
            'subtitletranslation',
        ];
        $originalTitleKeys = ['original-title', 'originalTitle', 'originaltitle', 'origtitle', 'origTitle'];
        $originalSubtitleKeys = ['original-subtitle', 'originalSubtitle', 'originalsubtitle', 'origsubtitle', 'origSubtitle'];

        return [
            'id' => $id,
            'type' => $itemType,
            'source' => self::firstStringField($item, ['source', 'source-title', 'sourceTitle', 'sourcetitle']),
            'endnoteTitleVariantSummary' => self::firstStringField($item, ['endnote-title-variant-summary', 'endnoteTitleVariantSummary', 'endnotetitlevariantsummary']),
            'endnotePublicationTypeHintSummary' => self::firstStringField($item, ['endnote-publication-type-hint-summary', 'endnotePublicationTypeHintSummary', 'endnotepublicationtypehintsummary']),
            'endnoteDateDiagnosticSummary' => self::firstStringField($item, ['endnote-date-diagnostic-summary', 'endnoteDateDiagnosticSummary', 'endnotedatediagnosticsummary']),
            'endnoteUnsupportedFieldSummary' => self::firstStringField($item, ['endnote-unsupported-field-summary', 'endnoteUnsupportedFieldSummary', 'endnoteunsupportedfieldsummary']),
            'risFieldProvenance' => $risFieldProvenance,
            'risFieldProvenanceSummary' => self::firstStringField($item, ['risFieldProvenanceSummary', 'ris-field-provenance-summary', 'risProvenanceSummary', 'ris-provenance-summary']),
            'risFieldDuplicateSummary' => self::firstStringField($item, ['risFieldDuplicateSummary', 'ris-field-duplicate-summary', 'risDuplicateSummary', 'ris-duplicate-summary']),
            'risFieldConflictSummary' => self::firstStringField($item, ['risFieldConflictSummary', 'ris-field-conflict-summary', 'risConflictSummary', 'ris-conflict-summary']),
            'citationAliases' => $citationAliases,
            'citationAliasSummary' => implode('; ', $citationAliases),
            'citationLabel' => self::firstStringField($item, ['citation-label', 'citationLabel', 'shorthand', 'label']),
            'shorthand' => self::firstStringField($item, ['shorthand']),
            'shorthandIntro' => self::firstStringField($item, ['shorthand-intro', 'shorthandIntro', 'shorthandintro']),
            'sortShorthand' => self::firstStringField($item, ['sort-shorthand', 'sortShorthand', 'sortshorthand']),
            'shorthandListSortKey' => self::firstStringField($item, ['shorthand-list-sort-key', 'shorthandListSortKey', 'list-shorthand', 'listshorthand'])
                ?: self::shorthandListSortKey($item),
            'presort' => self::firstStringField($item, ['presort']),
            'sortKey' => self::firstStringField($item, ['sort-key', 'sortKey', 'sortkey']),
            'sortName' => self::firstStringField($item, ['sort-name', 'sortName', 'sortname']),
            'sortTitle' => self::firstStringField($item, ['sort-title', 'sortTitle', 'sorttitle']),
            'sortYear' => self::firstStringField($item, ['sort-year', 'sortYear', 'sortyear']),
            'sortInitial' => self::firstStringField($item, ['sort-initial', 'sortInitial', 'sortinit', 'sortinitial']),
            'sortInitialHash' => self::firstStringField($item, ['sort-initial-hash', 'sortInitialHash', 'sortinithash']),
            'indexTitle' => self::firstStringField($item, ['index-title', 'indexTitle', 'indextitle']),
            'indexSortTitle' => self::firstStringField($item, ['index-sort-title', 'indexSortTitle', 'indexsorttitle']),
            'labelPrefix' => self::firstStringField($item, ['label-prefix', 'labelPrefix', 'labelprefix']),
            'labelAlpha' => self::firstStringField($item, ['label-alpha', 'labelAlpha', 'labelalpha']),
            'labelTitle' => self::firstStringField($item, ['label-title', 'labelTitle', 'labeltitle']),
            'extraAlpha' => self::firstStringField($item, ['extra-alpha', 'extraAlpha', 'extraalpha']),
            'extraDate' => self::firstStringField($item, ['extra-date', 'extraDate', 'extradate']),
            'extraTitle' => self::firstStringField($item, ['extra-title', 'extraTitle', 'extratitle']),
            'title' => self::stringField($item, 'title'),
            'shortTitle' => self::firstStringField($item, ['short-title', 'title-short', 'shortTitle', 'titleShort']),
            'titleAddon' => self::firstStringField($item, ['title-addon', 'titleAddon', 'titleaddon']),
            'translatedTitle' => self::composedStringField($item, $translatedTitleKeys, $translatedSubtitleKeys),
            'translatedSubtitle' => self::firstStringField($item, $translatedSubtitleKeys),
            'reviewedTitle' => $reviewedTitle,
            'reviewedGenre' => $reviewedGenre,
            'reprintTitle' => self::firstStringField($item, ['reprint-title', 'reprintTitle', 'reprinttitle']),
            'containerTitle' => self::composedStringField(
                $item,
                [
                    'container-title',
                    'containerTitle',
                    'containertitle',
                    'book-title',
                    'bookTitle',
                    'booktitle',
                    'container',
                    'container-title-text',
                    'containerTitleText',
                    'containertitletext',
                    'journal-title',
                    'journalTitle',
                    'journaltitle',
                    'journal',
                    'publication-title',
                    'publicationTitle',
                    'publicationtitle',
                ],
                [
                    'container-subtitle',
                    'containerSubtitle',
                    'containersubtitle',
                    'book-subtitle',
                    'bookSubtitle',
                    'booksubtitle',
                    'journal-subtitle',
                    'journalSubtitle',
                    'journalsubtitle',
                    'publication-subtitle',
                    'publicationSubtitle',
                    'publicationsubtitle',
                ]
            ),
            'containerTitleShort' => $containerTitleShort,
            'journalAbbreviation' => $containerTitleShort,
            'containerTitleAddon' => self::firstStringField($item, [
                'container-title-addon',
                'containerTitleAddon',
                'containertitleaddon',
                'book-title-addon',
                'bookTitleAddon',
                'booktitleaddon',
                'journal-title-addon',
                'journalTitleAddon',
                'journaltitleaddon',
                'publication-title-addon',
                'publicationTitleAddon',
                'publicationtitleaddon',
            ]),
            'mainTitle' => self::composedStringField(
                $item,
                ['main-title', 'mainTitle', 'maintitle', 'main-title-text', 'mainTitleText', 'maintitletext'],
                ['main-subtitle', 'mainSubtitle', 'mainsubtitle']
            ),
            'mainTitleAddon' => self::firstStringField($item, ['main-title-addon', 'mainTitleAddon', 'maintitleaddon']),
            'volumeTitle' => self::composedStringField(
                $item,
                ['volume-title', 'volumeTitle', 'volumetitle', 'volume-title-text', 'volumeTitleText', 'volumetitletext'],
                ['volume-subtitle', 'volumeSubtitle', 'volumesubtitle']
            ),
            'volumeTitleShort' => self::firstStringField($item, ['volume-title-short', 'volumeTitleShort', 'volumetitleshort']),
            'partTitle' => self::composedStringField(
                $item,
                ['part-title', 'partTitle', 'parttitle', 'part-title-text', 'partTitleText', 'parttitletext'],
                ['part-subtitle', 'partSubtitle', 'partsubtitle']
            ),
            'eventTitle' => self::firstStringField($item, ['event', 'event-title', 'eventTitle', 'eventtitle']),
            'eventTitleAddon' => self::firstStringField($item, ['event-title-addon', 'eventTitleAddon', 'eventtitleaddon']),
            'eventPlace' => $eventPlace,
            'eventPlaceList' => $eventPlaceList !== [] ? $eventPlaceList : ($eventPlace !== '' ? [$eventPlace] : []),
            'eventType' => self::firstStringField($item, ['event-type', 'eventType', 'eventtype']),
            'publisher' => $publisher,
            'publisherPlace' => $publisherPlace,
            'publisherList' => $publisherList !== [] ? $publisherList : ($publisher !== '' ? [$publisher] : []),
            'publisherPlaceList' => $publisherPlaceList !== [] ? $publisherPlaceList : ($publisherPlace !== '' ? [$publisherPlace] : []),
            'page' => $page,
            'pageFirst' => self::firstStringField($item, ['page-first', 'pageFirst']) ?: self::firstPageFromRange($page),
            'pagination' => self::firstStringField($item, ['pagination', 'page-label', 'pageLabel']),
            'bookPagination' => self::firstStringField($item, ['book-pagination', 'bookPagination', 'bookpagination']),
            'thesisType' => self::firstStringField($item, ['thesis-type', 'thesisType', 'thesistype']),
            'articleNumber' => self::firstStringField($item, ['article-number', 'articleNumber', 'articlenumber', 'eid']),
            'references' => self::firstStringField($item, ['references']),
            'dimensions' => self::firstStringField($item, ['dimensions', 'dimension']),
            'scale' => self::firstStringField($item, ['scale']),
            'number' => self::stringField($item, 'number'),
            'volume' => self::stringField($item, 'volume'),
            'issue' => self::firstStringField($item, ['issue', 'issue-number', 'issueNumber', 'issuenumber']),
            'issueTitle' => self::composedStringField(
                $item,
                ['issue-title', 'issueTitle', 'issuetitle', 'issue-title-text', 'issueTitleText', 'issuetitletext'],
                ['issue-subtitle', 'issueSubtitle', 'issuesubtitle']
            ),
            'issueTitleAddon' => self::firstStringField($item, ['issue-title-addon', 'issueTitleAddon', 'issuetitleaddon']),
            'edition' => self::stringField($item, 'edition'),
            'collectionTitle' => self::firstStringField($item, ['collection-title', 'collectionTitle', 'collectiontitle', 'collection', 'collection-title-text', 'collectionTitleText', 'collectiontitletext', 'series', 'series-title', 'seriesTitle', 'seriestitle', 'series-title-text', 'seriesTitleText', 'seriestitletext']),
            'collectionTitleShort' => self::firstStringField($item, ['collection-title-short', 'collectionTitleShort', 'collectiontitleshort', 'shortseries', 'short-series', 'series-short', 'seriesShort', 'seriesshort', 'series-title-short', 'seriesTitleShort', 'seriestitleshort']),
            'collectionNumber' => self::firstStringField($item, ['collection-number', 'collectionNumber', 'collectionnumber', 'seriesnumber', 'series-number', 'seriesNumber']),
            'numberOfVolumes' => self::firstStringField($item, ['number-of-volumes', 'numberOfVolumes', 'numberofvolumes', 'volumes', 'volume-count', 'volumeCount', 'volumecount', 'num-volumes', 'numVolumes', 'numvolumes']),
            'numberOfPages' => self::firstStringField($item, ['number-of-pages', 'numberOfPages', 'numberofpages', 'pagetotal', 'page-total', 'pageTotal', 'num-pages', 'numPages', 'numpages', 'total-pages', 'totalPages', 'totalpages']),
            'chapterNumber' => self::firstStringField($item, ['chapter-number', 'chapterNumber', 'chapternumber', 'chapter']),
            'division' => self::firstStringField($item, ['division', 'subdivision', 'subDivision', 'sub-division', 'sub_division']),
            'section' => self::stringField($item, 'section'),
            'part' => self::firstStringField($item, ['part', 'part-number', 'partNumber', 'partnumber']),
            'printingNumber' => self::firstStringField($item, ['printing-number', 'printingNumber', 'printingnumber', 'printing']),
            'supplement' => self::stringField($item, 'supplement'),
            'supplementNumber' => self::firstStringField($item, ['supplement-number', 'supplementNumber', 'supplementnumber']) ?: self::stringField($item, 'supplement'),
            'genre' => $genre,
            'patentType' => $patentType,
            'patentTypeLabel' => $patentTypeLabel,
            'entrySubtype' => self::firstStringField($item, ['entry-subtype', 'entrySubtype', 'entrysubtype']),
            'gender' => $biblatexGender,
            'biblatexGender' => $biblatexGender,
            'biblatexGenderSummary' => $biblatexGender,
            'authority' => $authority,
            'jurisdiction' => self::stringField($item, 'jurisdiction'),
            'status' => self::firstStringField($item, ['status', 'publication-status', 'publicationStatus', 'publicationstatus', 'pubstate']),
            'version' => self::stringField($item, 'version'),
            'rights' => self::firstStringField($item, ['rights', 'copyright', 'license', 'licence']),
            'doi' => self::firstStringField($item, ['DOI', 'doi']),
            'url' => self::firstStringField($item, ['URL', 'url']),
            'urlLabel' => self::firstStringField($item, ['URL-label', 'url-label', 'URLLabel', 'urlLabel', 'urldescription', 'urltitle', 'urllabel', 'url-description']),
            'isbn' => self::firstStringField($item, [
                'ISBN',
                'isbn',
                'ISBN-13',
                'isbn-13',
                'ISBN13',
                'isbn13',
                'ISBN-10',
                'isbn-10',
                'ISBN10',
                'isbn10',
                'eISBN',
                'eisbn',
                'e-isbn',
                'electronicISBN',
                'electronic-isbn',
                'electronicisbn',
            ]),
            'issn' => self::firstStringField($item, [
                'ISSN',
                'issn',
                'printISSN',
                'print-issn',
                'printissn',
                'pISSN',
                'p-issn',
                'pissn',
                'eISSN',
                'eissn',
                'e-issn',
                'electronicISSN',
                'electronic-issn',
                'electronicissn',
                'onlineISSN',
                'online-issn',
                'onlineissn',
                'issnOnline',
                'issn-online',
            ]),
            'isan' => self::firstStringField($item, ['ISAN', 'isan']),
            'ismn' => self::firstStringField($item, ['ISMN', 'ismn']),
            'isrn' => self::firstStringField($item, ['ISRN', 'isrn']),
            'iswc' => self::firstStringField($item, ['ISWC', 'iswc']),
            'pmid' => self::firstStringField($item, ['PMID', 'pmid', 'pubmed', 'pubmedid', 'pubmed-id']),
            'pmcid' => self::firstStringField($item, ['PMCID', 'pmcid', 'pmc', 'pmc-id', 'pmcid-id']),
            'mrNumber' => self::firstStringField($item, ['MRNumber', 'mrNumber', 'mrnumber', 'mr-number', 'mr', 'mathscinet']),
            'mrClass' => self::firstStringField($item, ['MRClass', 'mrClass', 'mrclass', 'mr-class']),
            'zbl' => self::firstStringField($item, ['Zbl', 'zbl', 'zbmath']),
            'jstor' => self::firstStringField($item, ['JSTOR', 'jstor', 'jstorid', 'jstor-id']),
            'hdl' => self::firstStringField($item, ['HDL', 'hdl', 'handle', 'hdlid', 'hdl-id', 'handleid', 'handle-id']),
            'lccn' => self::firstStringField($item, ['LCCN', 'lccn', 'lccnnumber', 'lccn-number']),
            'oclc' => self::firstStringField($item, ['OCLC', 'oclc', 'oclcnumber', 'oclc-number']),
            'orcid' => $orcid,
            'isni' => $isni,
            'viaf' => $viaf,
            'ror' => $ror,
            'wikidata' => $wikidata,
            'authorityIdentifierSummary' => self::labeledIdentifierSummary([
                ['ORCID', $orcid],
                ['ISNI', $isni],
                ['VIAF', $viaf],
                ['ROR', $ror],
                ['Wikidata', $wikidata],
            ]),
            'archive' => $archive,
            'archiveCollection' => $archiveCollection,
            'archivePlace' => $archivePlace,
            'archiveLocation' => $archiveLocation,
            'archiveSummary' => $archiveSummary,
            'callNumber' => self::firstStringField($item, ['call-number', 'callNumber', 'callnumber', 'library', 'shelfmark', 'shelf-mark', 'shelfMark']),
            'language' => $language,
            'languageList' => $languageList !== [] ? $languageList : ($language !== '' ? [$language] : []),
            'abstract' => self::firstStringField($item, ['abstract', 'abstract-note', 'abstractNote', 'abstractnote']),
            'annotation' => self::firstStringField($item, ['annotation', 'annotation-text', 'annotationText', 'annotationtext', 'annote']),
            'medium' => self::firstStringField($item, ['medium', 'howPublished', 'how-published', 'howpublished']),
            'note' => self::firstStringField($item, ['note', 'note-text', 'noteText', 'notetext', 'notes']),
            'addendum' => self::stringField($item, 'addendum'),
            'nameAddon' => self::firstStringField($item, ['name-addon', 'nameAddon']),
            'authorType' => self::firstStringField($item, ['author-type', 'authorType', 'authortype']),
            'containerAuthorType' => self::firstStringField($item, [
                'container-author-type',
                'containerAuthorType',
                'bookauthor-type',
                'bookAuthorType',
                'bookauthortype',
            ]),
            'dateAddon' => self::firstStringField($item, ['date-addon', 'dateAddon', 'dateaddendum', 'date-addendum']),
            'categories' => $categories,
            'categorySummary' => implode('; ', $categories),
            'keywords' => $keywords,
            'keywordSummary' => implode('; ', $keywords),
            'sourceFiles' => $sourceFilePolicy['files'],
            'sourceFileDiagnostics' => $sourceFileDiagnostics,
            'xdataKeys' => $xdataKeys,
            'xdataItems' => $xdataItems,
            'missingXdataKeys' => $missingXdataKeys,
            'xdataSummary' => self::summarizedReferenceValues($xdataItems, $missingXdataKeys, $xdataKeys),
            'entrySetKeys' => $entrySetKeys,
            'entrySetItems' => $entrySetItems,
            'missingEntrySetKeys' => $missingEntrySetKeys,
            'entrySetSummary' => self::summarizedReferenceValues($entrySetItems, $missingEntrySetKeys, $entrySetKeys),
            'relatedKeys' => self::stringListFromFirstField($item, ['relatedKeys', 'related-keys', 'related']),
            'relatedType' => self::firstStringField($item, ['relatedType', 'related-type', 'relatedtype']),
            'relatedString' => self::firstStringField($item, ['relatedString', 'related-string', 'relatedstring']),
            'relatedOptions' => self::stringListFromFirstField($item, ['relatedOptions', 'related-options', 'relatedoptions']),
            'relatedItems' => self::relatedItemSummaries($item['relatedItems'] ?? [], $id),
            'missingRelatedKeys' => self::stringListFromFirstField($item, ['missingRelatedKeys', 'missing-related-keys']),
            'crossrefKeys' => $crossrefKeys,
            'crossrefItems' => $crossrefItems,
            'missingCrossrefKeys' => $missingCrossrefKeys,
            'crossrefSummary' => self::summarizedReferenceValues($crossrefItems, $missingCrossrefKeys, $crossrefKeys),
            'xrefKeys' => self::stringListFromFirstField($item, ['xrefKeys', 'xref-keys', 'xref']),
            'xrefItems' => self::relatedItemSummaries($item['xrefItems'] ?? [], $id, 'xrefItems'),
            'missingXrefKeys' => self::stringListFromFirstField($item, ['missingXrefKeys', 'missing-xref-keys']),
            'issuedDate' => $issuedDate,
            'accessedDate' => $accessedDate,
            'availableDate' => $availableDate,
            'acceptedDate' => $acceptedDate,
            'revisedDate' => $revisedDate,
            'originalTitle' => self::composedStringField($item, $originalTitleKeys, $originalSubtitleKeys),
            'originalSubtitle' => self::firstStringField($item, $originalSubtitleKeys),
            'originalTitleAddon' => self::firstStringField($item, ['original-title-addon', 'originalTitleAddon', 'originaltitleaddon', 'original-titleaddon', 'origtitleaddon', 'origTitleAddon', 'origtitle-addon']),
            'originalPublisher' => $originalPublisher,
            'originalPublisherPlace' => $originalPublisherPlace,
            'originalPublisherList' => $originalPublisherList !== [] ? $originalPublisherList : ($originalPublisher !== '' ? [$originalPublisher] : []),
            'originalPublisherPlaceList' => $originalPublisherPlaceList !== [] ? $originalPublisherPlaceList : ($originalPublisherPlace !== '' ? [$originalPublisherPlace] : []),
            'originalLanguage' => $originalLanguage,
            'originalLanguageList' => $originalLanguageList !== [] ? $originalLanguageList : ($originalLanguage !== '' ? [$originalLanguage] : []),
            'originalGenre' => $originalGenre,
            'originalEdition' => $originalEdition,
            'originalIsbn' => $originalIsbn,
            'originalIssn' => $originalIssn,
            'originalDoi' => $originalDoi,
            'originalUrl' => $originalUrl,
            'originalCollectionTitle' => $originalCollectionTitle,
            'originalCollectionNumber' => $originalCollectionNumber,
            'originalDate' => $originalDate,
            'originalDateAddon' => self::firstStringField($item, ['original-date-addon', 'originalDateAddon', 'origdateaddon', 'origDateAddon', 'orig-date-addon']),
            'reprintDate' => $reprintDate,
            'reprintDateAddon' => self::firstStringField($item, ['reprint-date-addon', 'reprintDateAddon', 'reprintdateaddon', 'reprintdateaddendum', 'reprint-date-addendum']),
            'submittedDate' => $submittedDate,
            'eventDate' => $eventDate,
            'labelDate' => $labelDate,
            'eventDateAddon' => self::firstStringField($item, ['event-date-addon', 'eventDateAddon', 'eventdateaddon']),
            'accessedDateAddon' => self::firstStringField($item, ['accessed-date-addon', 'accessedDateAddon', 'URLDateAddon', 'URL-date-addon', 'URLDATEADDON', 'urldateaddon', 'url-date-addon']),
            'dateMarkerSummary' => $dateMarkerSummary,
            'dateTimeSummary' => $dateTimeSummary,
            'dateSeasonSummary' => $dateSeasonSummary,
            'dateEraSummary' => $dateEraSummary,
            'biblatexOptions' => $biblatexOptions,
            'biblatexOptionSummary' => implode('; ', $biblatexOptions),
            'biblatexSkipBibliography' => $biblatexSkipBibliography,
            'biblatexBibliographyVisibility' => $biblatexSkipBibliography ? 'omit' : 'include',
            'biblatexLanguageOptions' => $biblatexLanguageOptions,
            'biblatexLanguageOptionSummary' => implode('; ', $biblatexLanguageOptions),
            'biblatexFieldAnnotations' => $biblatexFieldAnnotations,
            'biblatexFieldAnnotationSummary' => self::biblatexFieldAnnotationSummary($biblatexFieldAnnotations),
            'biblatexCustomFields' => $biblatexCustomFields,
            'biblatexCustomFieldSummary' => self::biblatexCustomFieldSummary($biblatexCustomFields),
            'biblatexCustomLists' => $biblatexCustomLists,
            'biblatexCustomListSummary' => self::biblatexCustomListSummary($biblatexCustomLists),
            'biblatexCustomNames' => $biblatexCustomNames,
            'biblatexCustomNameSummary' => self::biblatexCustomNameSummary($biblatexCustomNames),
            'biblatexRefsection' => $biblatexRefsection,
            'biblatexRefsegment' => $biblatexRefsegment,
            'biblatexReferenceContextSummary' => self::biblatexReferenceContextSummary($biblatexRefsection, $biblatexRefsegment),
            'issuedYear' => $issuedDate['year'],
            'yearSuffix' => self::firstStringField($item, ['year-suffix', 'yearSuffix', 'yearsuffix']),
            'authors' => self::namesFromFirstItemField($item, $id, 'author', ['author', 'authors']),
            'editors' => self::namesFromFirstItemField($item, $id, 'editor', ['editor', 'editors']),
            'shortAuthors' => self::namesFromFirstItemField($item, $id, 'short-author', ['short-author', 'shortAuthor', 'short-authors', 'shortAuthors']),
            'shortEditors' => self::namesFromFirstItemField($item, $id, 'short-editor', ['short-editor', 'shortEditor', 'short-editors', 'shortEditors']),
            'holders' => self::namesFromFirstItemField($item, $id, 'holder', ['holder', 'holders']),
            'authorities' => $authorityNames,
            'translators' => self::namesFromFirstItemField($item, $id, 'translator', ['translator', 'translators']),
            'chairs' => self::namesFromFirstItemField($item, $id, 'chair', ['chair', 'chairs']),
            'containerAuthors' => self::namesFromFirstItemField($item, $id, 'container-author', ['container-author', 'containerAuthor', 'container-authors', 'containerAuthors', 'bookauthor', 'bookAuthor', 'book-author', 'bookauthors', 'bookAuthors', 'book-authors']),
            'collectionEditors' => self::namesFromFirstItemField($item, $id, 'collection-editor', ['collection-editor', 'collectionEditor', 'collection-editors', 'collectionEditors']),
            'seriesCreators' => self::namesFromFirstItemField($item, $id, 'series-creator', ['series-creator', 'seriesCreator', 'series-creators', 'seriesCreators']),
            'composers' => self::namesFromFirstItemField($item, $id, 'composer', ['composer', 'composers']),
            'contributors' => self::namesFromFirstItemField($item, $id, 'contributor', ['contributor', 'contributors']),
            'editorTranslators' => self::namesFromFirstItemField($item, $id, 'editor-translator', ['editor-translator', 'editorTranslator', 'editor-translators', 'editorTranslators']),
            'executiveProducers' => self::namesFromFirstItemField($item, $id, 'executive-producer', ['executive-producer', 'executiveProducer', 'executive-producers', 'executiveProducers']),
            'eventOrganizers' => self::namesFromFirstItemField($item, $id, 'event-organizer', ['event-organizer', 'eventOrganizer', 'event-organizers', 'eventOrganizers', 'organizer', 'organizers']),
            'guests' => self::namesFromFirstItemField($item, $id, 'guest', ['guest', 'guests']),
            'hosts' => self::namesFromFirstItemField($item, $id, 'host', ['host', 'hosts']),
            'narrators' => self::namesFromFirstItemField($item, $id, 'narrator', ['narrator', 'narrators']),
            'originalAuthors' => self::namesFromFirstItemField($item, $id, 'original-author', ['original-author', 'originalAuthor', 'original-authors', 'originalAuthors']),
            'performers' => self::namesFromFirstItemField($item, $id, 'performer', ['performer', 'performers']),
            'producers' => self::namesFromFirstItemField($item, $id, 'producer', ['producer', 'producers']),
            'recipients' => self::namesFromFirstItemField($item, $id, 'recipient', ['recipient', 'recipients']),
            'scriptWriters' => self::namesFromFirstItemField($item, $id, 'script-writer', ['script-writer', 'scriptWriter', 'script-writers', 'scriptWriters']),
            'compilers' => self::namesFromFirstItemField($item, $id, 'compiler', ['compiler', 'compilers']),
            'curators' => self::namesFromFirstItemField($item, $id, 'curator', ['curator', 'curators']),
            'directors' => self::namesFromFirstItemField($item, $id, 'director', ['director', 'directors']),
            'editorialDirectors' => self::namesFromFirstItemField($item, $id, 'editorial-director', ['editorial-director', 'editorialDirector', 'editorial-directors', 'editorialDirectors']),
            'illustrators' => self::namesFromFirstItemField($item, $id, 'illustrator', ['illustrator', 'illustrators']),
            'interviewers' => self::namesFromFirstItemField($item, $id, 'interviewer', ['interviewer', 'interviewers']),
            'reviewedAuthors' => self::namesFromFirstItemField($item, $id, 'reviewed-author', ['reviewed-author', 'reviewedAuthor', 'reviewed-authors', 'reviewedAuthors']),
            'redactors' => self::namesFromFirstItemField($item, $id, 'redactor', ['redactor', 'redactors']),
            'founders' => self::namesFromFirstItemField($item, $id, 'founder', ['founder', 'founders']),
            'continuators' => self::namesFromFirstItemField($item, $id, 'continuator', ['continuator', 'continuators']),
            'revisers' => self::namesFromFirstItemField($item, $id, 'reviser', ['reviser', 'revisers']),
            'collaborators' => self::namesFromFirstItemField($item, $id, 'collaborator', ['collaborator', 'collaborators']),
            'commentators' => self::namesFromFirstItemField($item, $id, 'commentator', ['commentator', 'commentators']),
            'annotators' => self::namesFromFirstItemField($item, $id, 'annotator', ['annotator', 'annotators']),
            'introductionAuthors' => self::namesFromFirstItemField($item, $id, 'introduction', ['introduction', 'introductions', 'introductionAuthor', 'introductionAuthors']),
            'forewordAuthors' => self::namesFromFirstItemField($item, $id, 'foreword', ['foreword', 'forewords', 'forewordAuthor', 'forewordAuthors']),
            'afterwordAuthors' => self::namesFromFirstItemField($item, $id, 'afterword', ['afterword', 'afterwords', 'afterwordAuthor', 'afterwordAuthors']),
            'editorialRoles' => self::editorialRoles($item['editorial-roles'] ?? [], $id),
            'raw' => $item,
        ];
    }

    /**
     * @param array<string, list<string>> $fields
     * @return array<string, mixed>
     */
    private static function risRecordToItem(array $fields, int $index): array
    {
        $type = strtoupper(trim($fields['TY'][0] ?? 'GEN'));
        $id = self::risFirst($fields, ['ID']);
        if ($id === '') {
            $id = self::risFirst($fields, ['AN', 'DO', 'UR']);
        }
        if ($id === '') {
            $id = 'ris-' . $index;
        }
        $provenance = self::risFieldMappingProvenance($fields);

        $item = [
            'id' => $id,
            'type' => self::risCslType($type),
            'title' => self::risFirst($fields, ['TI', 'T1', 'CT']),
            'short-title' => self::risFirst($fields, ['ST']),
            'container-title' => self::risFirst($fields, ['T2', 'JF', 'JO', 'JA', 'BT']),
            'container-title-short' => self::risFirst($fields, ['J2']),
            'collection-title' => self::risFirst($fields, ['T3']),
            'translated-title' => self::risFirst($fields, ['TT']),
            'reviewed-title' => self::risFirst($fields, ['RI']),
            'original-title' => self::risFirst($fields, ['OP']),
            'publisher' => self::risFirst($fields, ['PB']),
            'publisher-place' => self::risFirst($fields, ['CY', 'PP']),
            'volume' => self::risFirst($fields, ['VL']),
            'issue' => self::risFirst($fields, ['IS']),
            'number-of-volumes' => self::risFirst($fields, ['NV']),
            'number' => self::risFirst($fields, ['M1']),
            'page' => self::risPageRange($fields),
            'edition' => self::risFirst($fields, ['ET']),
            'section' => self::risFirst($fields, ['SE']),
            'call-number' => self::risFirst($fields, ['CN']),
            'doi' => self::risFirst($fields, ['DO']),
            'url' => self::risFirst($fields, ['UR']),
            'language' => self::risFirst($fields, ['LA']),
            'abstract' => self::risFirst($fields, ['AB', 'N2']),
            'note' => self::risFirst($fields, ['N1', 'NT']),
            'medium' => self::risFirst($fields, ['M3']),
            'status' => self::risFirst($fields, ['RP']),
            'source' => self::risFirst($fields, ['DB']),
            'issued' => self::risDate($fields, ['Y1', 'PY', 'DA']),
            'accessed' => self::risDate($fields, ['Y2']),
            'author' => self::risNames($fields, ['A1', 'AU']),
            'editor' => self::risNames($fields, ['A2', 'ED']),
            'translator' => self::risNames($fields, ['A3']),
            'collaborator' => self::risNames($fields, ['A4']),
            'keyword' => self::risValues($fields, ['KW']),
            'biblatex-custom-fields' => self::risCustomFields($fields),
            'sourceFiles' => self::risSourceFiles($fields),
            'rawRis' => [
                'type' => $type,
                'fields' => $fields,
            ],
            'risFieldProvenance' => $provenance['rows'],
            'risFieldProvenanceSummary' => $provenance['summary'],
            'risFieldDuplicateSummary' => $provenance['duplicateSummary'],
            'risFieldConflictSummary' => $provenance['conflictSummary'],
        ];

        $serialNumber = self::risFirst($fields, ['SN']);
        if ($serialNumber !== '') {
            $item[self::risSerialIdentifierField($serialNumber)] = $serialNumber;
        }

        return array_filter($item, static fn (mixed $value): bool => $value !== '' && $value !== [] && $value !== null);
    }

    /**
     * @param array<string, list<string>> $fields
     * @return array{rows:list<array<string, mixed>>, summary:string, duplicateSummary:string, conflictSummary:string}
     */
    private static function risFieldMappingProvenance(array $fields): array
    {
        $rows = [];
        $summary = [];
        $duplicates = [];
        $conflicts = [];
        $reviewFields = self::risReviewProvenanceFields();

        foreach (self::risFieldMappingSpecs() as $field => $tags) {
            $sources = self::risFieldSources($fields, $tags);
            if ($sources === []) {
                continue;
            }

            $tagCounts = [];
            $uniqueValues = [];
            foreach ($sources as $source) {
                $tag = $source['tag'];
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                $valueKey = preg_replace('/\s+/u', ' ', $source['value']) ?? $source['value'];
                $uniqueValues[$valueKey] = $source['value'];
            }

            $hasDuplicate = count($sources) > 1;
            $hasConflict = count($uniqueValues) > 1;
            if (!in_array($field, $reviewFields, true) && !$hasDuplicate && !$hasConflict) {
                continue;
            }

            $selected = $sources[0];
            $sourceSummary = self::risTagCountSummary($tagCounts);
            $row = [
                'field' => $field,
                'selectedTag' => $selected['tag'],
                'selectedValue' => $selected['value'],
                'sourceTags' => array_keys($tagCounts),
                'sourceCount' => count($sources),
                'sourceSummary' => $sourceSummary,
            ];

            if ($hasDuplicate) {
                $row['duplicate'] = true;
                $duplicates[] = $field . ': ' . $sourceSummary;
            }

            if ($hasConflict) {
                $row['conflict'] = true;
                $row['conflictingValues'] = array_values($uniqueValues);
                $conflicts[] = $field . ': ' . implode(' | ', array_values($uniqueValues));
            }

            $summary[] = $field . '<=' . $selected['tag'] . ($hasDuplicate ? '[' . $sourceSummary . ']' : '');
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'summary' => implode('; ', $summary),
            'duplicateSummary' => implode('; ', $duplicates),
            'conflictSummary' => implode('; ', $conflicts),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function risFieldMappingSpecs(): array
    {
        return [
            'title' => ['TI', 'T1', 'CT'],
            'short-title' => ['ST'],
            'container-title' => ['T2', 'JF', 'JO', 'JA', 'BT'],
            'container-title-short' => ['J2'],
            'collection-title' => ['T3'],
            'translated-title' => ['TT'],
            'reviewed-title' => ['RI'],
            'original-title' => ['OP'],
            'publisher' => ['PB'],
            'publisher-place' => ['CY', 'PP'],
            'volume' => ['VL'],
            'issue' => ['IS'],
            'number-of-volumes' => ['NV'],
            'edition' => ['ET'],
            'section' => ['SE'],
            'call-number' => ['CN'],
            'doi' => ['DO'],
            'url' => ['UR'],
            'language' => ['LA'],
            'abstract' => ['AB', 'N2'],
            'note' => ['N1', 'NT'],
            'medium' => ['M3'],
            'source' => ['DB'],
            'usera' => ['U1'],
            'userb' => ['U2'],
            'userc' => ['U3'],
            'userd' => ['U4'],
            'usere' => ['U5'],
            'verba' => ['C1'],
            'verbb' => ['C2'],
            'verbc' => ['C3'],
            'issued' => ['Y1', 'PY', 'DA'],
            'accessed' => ['Y2'],
            'serial-number' => ['SN'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function risReviewProvenanceFields(): array
    {
        return [
            'translated-title',
            'reviewed-title',
            'original-title',
            'number-of-volumes',
            'call-number',
            'usera',
            'userb',
            'userc',
            'userd',
            'usere',
            'verba',
            'verbb',
            'verbc',
        ];
    }

    /**
     * @param array<string, list<string>> $fields
     * @param list<string> $tags
     * @return list<array{tag:string, value:string}>
     */
    private static function risFieldSources(array $fields, array $tags): array
    {
        $sources = [];
        foreach ($tags as $tag) {
            foreach ($fields[$tag] ?? [] as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $sources[] = ['tag' => $tag, 'value' => $value];
                }
            }
        }

        return $sources;
    }

    /**
     * @param array<string, int> $tagCounts
     */
    private static function risTagCountSummary(array $tagCounts): string
    {
        $parts = [];
        foreach ($tagCounts as $tag => $count) {
            $parts[] = $tag . ($count > 1 ? 'x' . $count : '');
        }

        return implode('+', $parts);
    }

    private static function risCslType(string $type): string
    {
        return match ($type) {
            'JOUR', 'JFULL', 'EJOUR' => 'article-journal',
            'MGZN' => 'article-magazine',
            'NEWS' => 'article-newspaper',
            'BLOG' => 'post-weblog',
            'BOOK', 'EBOOK', 'EDBOOK', 'SER' => 'book',
            'CHAP', 'ECHAP' => 'chapter',
            'CONF', 'CPAPER', 'ABST' => 'paper-conference',
            'RPRT', 'GOVDOC', 'GRANT' => 'report',
            'THES' => 'thesis',
            'PAT' => 'patent',
            'DATA', 'DBASE' => 'dataset',
            'ELEC', 'WEB' => 'webpage',
            'CASE' => 'legal_case',
            'BILL', 'UNBILL' => 'bill',
            'HEAR' => 'hearing',
            'STAT' => 'legislation',
            'STAND' => 'standard',
            'UNPB' => 'manuscript',
            'ART', 'CHART', 'FIGURE', 'SLIDE' => 'graphic',
            'MAP' => 'map',
            'COMP' => 'software',
            'DICT' => 'entry-dictionary',
            'ENCYC' => 'entry-encyclopedia',
            'ICOMM', 'PCOMM' => 'personal_communication',
            'MUSIC', 'SOUND' => 'song',
            'ADVS', 'MPCT', 'VIDEO' => 'motion_picture',
            default => 'document',
        };
    }

    /**
     * @param array<string, list<string>> $fields
     * @param list<string> $tags
     */
    private static function risFirst(array $fields, array $tags): string
    {
        foreach ($tags as $tag) {
            foreach ($fields[$tag] ?? [] as $value) {
                $value = trim($value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, list<string>> $fields
     * @param list<string> $tags
     * @return list<string>
     */
    private static function risValues(array $fields, array $tags): array
    {
        $values = [];
        foreach ($tags as $tag) {
            foreach ($fields[$tag] ?? [] as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * @param array<string, list<string>> $fields
     * @return list<array{label:string, path:string, mediaType:string}>
     */
    private static function risSourceFiles(array $fields): array
    {
        $files = [];
        foreach (['L1', 'L2', 'L3', 'L4'] as $tag) {
            foreach (self::risValues($fields, [$tag]) as $path) {
                $files[] = [
                    'label' => 'RIS ' . $tag,
                    'path' => $path,
                    'mediaType' => '',
                ];
            }
        }

        return $files;
    }

    /**
     * @param array<string, list<string>> $fields
     * @return array<string, string>
     */
    private static function risCustomFields(array $fields): array
    {
        $customFields = [];
        foreach ([
            'usera' => 'U1',
            'userb' => 'U2',
            'userc' => 'U3',
            'userd' => 'U4',
            'usere' => 'U5',
            'verba' => 'C1',
            'verbb' => 'C2',
            'verbc' => 'C3',
        ] as $field => $tag) {
            $value = self::risFirst($fields, [$tag]);
            if ($value !== '') {
                $customFields[$field] = $value;
            }
        }

        return $customFields;
    }

    /**
     * @param array<string, list<string>> $fields
     * @param list<string> $tags
     * @return list<array<string, mixed>>
     */
    private static function risNames(array $fields, array $tags): array
    {
        $names = [];
        foreach (self::risValues($fields, $tags) as $value) {
            $names[] = self::risName($value);
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private static function risName(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['literal' => ''];
        }

        if (str_contains($value, ',')) {
            [$family, $given] = array_map('trim', explode(',', $value, 2));
            return array_filter([
                'family' => $family,
                'given' => $given,
            ], static fn (string $part): bool => $part !== '');
        }

        return ['literal' => $value];
    }

    /**
     * @param array<string, list<string>> $fields
     */
    private static function risPageRange(array $fields): string
    {
        $start = self::risFirst($fields, ['SP']);
        $end = self::risFirst($fields, ['EP']);
        if ($start !== '' && $end !== '') {
            return $start . '-' . $end;
        }

        return $start !== '' ? $start : self::risFirst($fields, ['PG']);
    }

    /**
     * @param array<string, list<string>> $fields
     * @param list<string> $tags
     * @return array<string, mixed>|null
     */
    private static function risDate(array $fields, array $tags): ?array
    {
        $value = self::risFirst($fields, $tags);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace('.', '-', trim($value));
        if (preg_match('/^(-?\d{1,4})(?:[\/-](\d{1,2})(?:[\/-](\d{1,2}))?)?/', $normalized, $matches) === 1) {
            $parts = [(int) $matches[1]];
            if (isset($matches[2]) && $matches[2] !== '' && (int) $matches[2] > 0) {
                $parts[] = (int) $matches[2];
            }
            if (isset($matches[3]) && $matches[3] !== '' && (int) $matches[3] > 0) {
                $parts[] = (int) $matches[3];
            }

            return ['date-parts' => [$parts], 'raw' => $value];
        }

        return ['literal' => $value, 'raw' => $value];
    }

    private static function risSerialIdentifierField(string $value): string
    {
        $compact = strtoupper(str_replace(['-', ' '], '', trim($value)));

        return preg_match('/^\d{4}\d{3}[\dX]$/', $compact) === 1 ? 'ISSN' : 'ISBN';
    }

    /**
     * @return array<string, mixed>
     */
    private static function endnoteRecordToItem(\DOMElement $record, int $index): array
    {
        $refType = self::endnoteRefType($record);
        $titleFields = self::endnoteTitleFields($record);
        $datePacket = self::endnoteDatePacket($record);
        $publicationHints = self::endnotePublicationTypeHints($record, $refType);
        $unsupportedFields = self::endnoteUnsupportedFields($record);
        $electronicResource = self::endnoteFirstText($record, ['electronic-resource-num', 'doi']);
        $url = self::endnoteFirstRelatedUrl($record);
        $doi = preg_match('/^(?:doi:\s*)?10\.\S+/i', $electronicResource) === 1
            ? preg_replace('/^doi:\s*/i', '', $electronicResource)
            : '';
        if ($url === '' && preg_match('/^https?:\/\//i', $electronicResource) === 1) {
            $url = $electronicResource;
        }

        $authorGroup = self::endnoteContributorNames($record, ['authors'], 'author');
        $editorGroup = self::endnoteContributorNames($record, ['secondary-authors', 'editors'], 'editor');
        $translatorGroup = self::endnoteContributorNames($record, ['tertiary-authors', 'translators'], 'translator');
        $nameDiagnostics = [
            ...$authorGroup['diagnostics'],
            ...$editorGroup['diagnostics'],
            ...$translatorGroup['diagnostics'],
        ];
        $nameGroups = [
            ...$authorGroup['raw'],
            ...$editorGroup['raw'],
            ...$translatorGroup['raw'],
        ];

        $item = [
            'id' => self::endnoteRecordId($record, $index),
            'type' => self::endnoteCslType($refType),
            'title' => $titleFields['title'],
            'short-title' => $titleFields['shortTitle'],
            'container-title' => $titleFields['secondaryTitle'] !== ''
                ? $titleFields['secondaryTitle']
                : self::endnoteFirstText($record, ['full-title', 'periodical-title']),
            'container-title-short' => self::endnoteFirstText($record, ['abbr-1', 'abbr-2', 'abbr-3']),
            'collection-title' => $titleFields['tertiaryTitle'],
            'publisher' => self::endnoteFirstText($record, ['publisher']),
            'publisher-place' => self::endnoteFirstText($record, ['pub-location', 'place-published', 'city']),
            'volume' => self::endnoteFirstText($record, ['volume']),
            'issue' => self::endnoteFirstText($record, ['number', 'issue']),
            'page' => self::endnoteFirstText($record, ['pages']),
            'edition' => self::endnoteFirstText($record, ['edition']),
            'section' => self::endnoteFirstText($record, ['section']),
            'doi' => $doi,
            'url' => $url,
            'language' => self::endnoteFirstText($record, ['language']),
            'abstract' => self::endnoteFirstText($record, ['abstract']),
            'note' => self::endnoteFirstText($record, ['notes']),
            'issued' => $datePacket['issued'],
            'author' => $authorGroup['names'],
            'editor' => $editorGroup['names'],
            'translator' => $translatorGroup['names'],
            'keyword' => self::endnoteTextList($record, ['keyword']),
            'sourceFileDiagnostics' => self::endnoteSourceFileDiagnostics($record),
            'endnoteTitleVariantSummary' => $titleFields['summary'],
            'endnotePublicationTypeHintSummary' => $publicationHints['summary'],
            'endnoteDateDiagnosticSummary' => self::endnoteReasonSummary($datePacket['diagnostics']),
            'endnoteUnsupportedFieldSummary' => self::endnoteUnsupportedFieldSummary($unsupportedFields),
            'rawEndnoteXml' => [
                'refType' => $refType,
                'recordNumber' => self::endnoteFirstText($record, ['rec-number']),
                'accessionNumber' => self::endnoteFirstText($record, ['accession-num']),
                'database' => self::endnoteDatabaseName($record),
                'titleFields' => $titleFields['fields'],
                'titleVariantSummary' => $titleFields['summary'],
                'publicationTypeHints' => $publicationHints['hints'],
                'publicationTypeHintSummary' => $publicationHints['summary'],
                'dateFields' => $datePacket['fields'],
                'dateDiagnostics' => $datePacket['diagnostics'],
                'dateDiagnosticSummary' => self::endnoteReasonSummary($datePacket['diagnostics']),
                'nameGroups' => $nameGroups,
                'nameGroupDiagnostics' => $nameDiagnostics,
                'nameGroupDiagnosticSummary' => self::endnoteNameDiagnosticSummary($nameDiagnostics),
                'unsupportedFields' => $unsupportedFields,
                'unsupportedFieldSummary' => self::endnoteUnsupportedFieldSummary($unsupportedFields),
            ],
        ];

        if ($doi === '' && $electronicResource !== '' && $electronicResource !== $url) {
            $item['rawEndnoteXml']['electronicResourceNumber'] = $electronicResource;
            $item['rawEndnoteXml']['unsupportedFields'][] = [
                'field' => 'electronic-resource-num',
                'value' => $electronicResource,
                'reason' => 'endnote-electronic-resource-preserved-raw-only',
            ];
        }

        return array_filter($item, static fn (mixed $value): bool => $value !== '' && $value !== [] && $value !== null);
    }

    private static function endnoteRecordId(\DOMElement $record, int $index): string
    {
        $id = self::endnoteFirstText($record, ['accession-num', 'label', 'rec-number']);
        if ($id === '') {
            return 'endnote-' . $index;
        }

        $id = preg_replace('/\s+/u', '-', trim($id)) ?? trim($id);

        return $id === '' ? 'endnote-' . $index : $id;
    }

    private static function endnoteRefType(\DOMElement $record): string
    {
        $element = self::endnoteFirstElement($record, ['ref-type']);
        if (!$element instanceof \DOMElement) {
            return '';
        }

        $name = trim($element->getAttribute('name'));

        return $name !== '' ? $name : XmlHtmlDom::normalizedText($element);
    }

    private static function endnoteCslType(string $type): string
    {
        $normalized = strtolower(trim($type));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return match ($normalized) {
            'journal-article', 'electronic-article' => 'article-journal',
            'magazine-article' => 'article-magazine',
            'newspaper-article' => 'article-newspaper',
            'book' => 'book',
            'book-section', 'book-chapter' => 'chapter',
            'conference-paper', 'conference-proceedings' => 'paper-conference',
            'report' => 'report',
            'thesis' => 'thesis',
            'patent' => 'patent',
            'dataset' => 'dataset',
            'web-page', 'webpage' => 'webpage',
            'film-or-broadcast', 'video-recording' => 'motion_picture',
            'artwork', 'figure' => 'graphic',
            default => 'document',
        };
    }

    /**
     * @param list<string> $localNames
     */
    private static function endnoteFirstElement(\DOMElement $record, array $localNames): ?\DOMElement
    {
        foreach ($localNames as $localName) {
            foreach (XmlHtmlDom::descendantElements($record, $localName) as $element) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @param list<string> $localNames
     */
    private static function endnoteFirstText(\DOMElement $record, array $localNames): string
    {
        foreach ($localNames as $localName) {
            foreach (XmlHtmlDom::descendantElements($record, $localName) as $element) {
                $value = XmlHtmlDom::normalizedText($element);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param list<string> $localNames
     * @return list<string>
     */
    private static function endnoteTextList(\DOMElement $record, array $localNames): array
    {
        $values = [];
        foreach ($localNames as $localName) {
            foreach (XmlHtmlDom::descendantElements($record, $localName) as $element) {
                $value = XmlHtmlDom::normalizedText($element);
                if ($value !== '' && !in_array($value, $values, true)) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * @return array{
     *     title:string,
     *     secondaryTitle:string,
     *     tertiaryTitle:string,
     *     alternateTitle:string,
     *     shortTitle:string,
     *     fields:list<array{field:string, value:string, parent:string}>,
     *     summary:string
     * }
     */
    private static function endnoteTitleFields(\DOMElement $record): array
    {
        $fieldNames = ['title', 'secondary-title', 'tertiary-title', 'alternate-title', 'alt-title', 'short-title'];
        $fields = self::endnoteRawTextFields($record, $fieldNames);
        $title = self::endnoteFirstRawField($fields, ['title']);
        $secondaryTitle = self::endnoteFirstRawField($fields, ['secondary-title']);
        $tertiaryTitle = self::endnoteFirstRawField($fields, ['tertiary-title']);
        $alternateTitle = self::endnoteFirstRawField($fields, ['alternate-title', 'alt-title']);
        $shortTitle = self::endnoteFirstRawField($fields, ['short-title', 'alternate-title', 'alt-title']);

        return [
            'title' => $title,
            'secondaryTitle' => $secondaryTitle,
            'tertiaryTitle' => $tertiaryTitle,
            'alternateTitle' => $alternateTitle,
            'shortTitle' => $shortTitle,
            'fields' => $fields,
            'summary' => self::endnoteFieldSummary($fields),
        ];
    }

    /**
     * @return array{hints:list<array{field:string, value:string, cslType?:string, reason:string}>, summary:string}
     */
    private static function endnotePublicationTypeHints(\DOMElement $record, string $refType): array
    {
        $hints = [];
        if ($refType !== '') {
            $hints[] = [
                'field' => 'ref-type',
                'value' => $refType,
                'cslType' => self::endnoteCslType($refType),
                'reason' => 'endnote-ref-type-mapped',
            ];
        }

        foreach (self::endnoteRawTextFields($record, ['work-type', 'publication-type', 'pub-type', 'type', 'medium', 'genre', 'thesis-type', 'report-type']) as $field) {
            $hints[] = [
                'field' => $field['field'],
                'value' => $field['value'],
                'reason' => 'endnote-publication-hint-preserved',
            ];
        }

        $parts = [];
        foreach ($hints as $hint) {
            $value = (string) $hint['value'];
            if (isset($hint['cslType']) && $hint['cslType'] !== '') {
                $value .= ' -> ' . $hint['cslType'];
            }
            $parts[] = $hint['field'] . ': ' . $value;
        }

        return ['hints' => $hints, 'summary' => implode('; ', $parts)];
    }

    /**
     * @param list<string> $localNames
     * @return list<array{field:string, value:string, parent:string}>
     */
    private static function endnoteRawTextFields(\DOMElement $record, array $localNames): array
    {
        $fields = [];
        foreach ($localNames as $localName) {
            foreach (XmlHtmlDom::descendantElements($record, $localName) as $element) {
                $value = XmlHtmlDom::normalizedText($element);
                if ($value === '') {
                    continue;
                }

                $parent = $element->parentNode instanceof \DOMElement ? $element->parentNode->localName : '';
                $fields[] = ['field' => $localName, 'value' => $value, 'parent' => $parent];
            }
        }

        return $fields;
    }

    /**
     * @param list<array{field:string, value:string, parent:string}> $fields
     * @param list<string> $fieldNames
     */
    private static function endnoteFirstRawField(array $fields, array $fieldNames): string
    {
        foreach ($fieldNames as $fieldName) {
            foreach ($fields as $field) {
                if ($field['field'] === $fieldName && $field['value'] !== '') {
                    return $field['value'];
                }
            }
        }

        return '';
    }

    /**
     * @param list<array{field:string, value:string, parent:string}> $fields
     */
    private static function endnoteFieldSummary(array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $parts[] = $field['field'] . ': ' . $field['value'];
        }

        return implode('; ', $parts);
    }

    /**
     * @param list<string> $groupNames
     * @return array{names:list<array<string, mixed>>, diagnostics:list<array<string, mixed>>, raw:list<array<string, mixed>>}
     */
    private static function endnoteContributorNames(\DOMElement $record, array $groupNames, string $role): array
    {
        $names = [];
        $diagnostics = [];
        $raw = [];
        foreach ($groupNames as $groupName) {
            foreach (XmlHtmlDom::descendantElements($record, $groupName) as $group) {
                $children = XmlHtmlDom::childElements($group);
                if ($children === []) {
                    $parsed = self::endnoteContributorName($group, $group->localName, $role, count($raw));
                    $raw[] = [
                        'group' => $group->localName,
                        'role' => $role,
                        'element' => $group->localName,
                        'value' => XmlHtmlDom::normalizedText($group),
                        'parsedAs' => $parsed['parsedAs'],
                    ];
                    $diagnostics = [...$diagnostics, ...$parsed['diagnostics']];
                    if (is_array($parsed['name'])) {
                        $names[] = $parsed['name'];
                    }
                    continue;
                }

                foreach ($children as $child) {
                    if (!in_array($child->localName, ['author', 'editor', 'translator', 'name', 'corporate-author'], true)) {
                        $value = XmlHtmlDom::normalizedText($child);
                        if ($value !== '') {
                            $diagnostics[] = self::endnoteNameDiagnostic($group->localName, $role, count($raw), $child->localName, $value, 'endnote-name-unsupported-child');
                        }
                        continue;
                    }

                    $parsed = self::endnoteContributorName($child, $group->localName, $role, count($raw));
                    $raw[] = [
                        'group' => $group->localName,
                        'role' => $role,
                        'element' => $child->localName,
                        'value' => XmlHtmlDom::normalizedText($child),
                        'parsedAs' => $parsed['parsedAs'],
                    ];
                    $diagnostics = [...$diagnostics, ...$parsed['diagnostics']];
                    if (is_array($parsed['name'])) {
                        $names[] = $parsed['name'];
                    }
                }
            }
        }

        return ['names' => $names, 'diagnostics' => $diagnostics, 'raw' => $raw];
    }

    /**
     * @return array{name:array<string, mixed>|null, diagnostics:list<array<string, mixed>>, parsedAs:string}
     */
    private static function endnoteContributorName(\DOMElement $element, string $group, string $role, int $entryIndex): array
    {
        $raw = XmlHtmlDom::normalizedText($element);
        $diagnostics = [];
        $family = self::endnoteFirstText($element, ['last-name', 'surname', 'family', 'family-name']);
        $given = self::endnoteFirstText($element, ['first-name', 'given', 'given-name', 'forename']);
        $suffix = self::endnoteFirstText($element, ['suffix']);
        $literal = self::endnoteFirstText($element, ['corporate-name', 'organization', 'organisation', 'institution', 'literal']);
        $hasStructuredParts = self::endnoteHasAnyDescendant($element, [
            'last-name',
            'surname',
            'family',
            'family-name',
            'first-name',
            'given',
            'given-name',
            'forename',
            'suffix',
            'corporate-name',
            'organization',
            'organisation',
            'institution',
            'literal',
        ]);
        $isCorporate = $literal !== '' || self::endnoteNameElementIsCorporate($element);
        if ($isCorporate) {
            $literal = $literal !== '' ? $literal : $raw;
            if ($literal === '') {
                $diagnostics[] = self::endnoteNameDiagnostic($group, $role, $entryIndex, $element->localName, $raw, 'endnote-name-empty-corporate');

                return ['name' => null, 'diagnostics' => $diagnostics, 'parsedAs' => 'skipped'];
            }

            return ['name' => ['literal' => $literal], 'diagnostics' => $diagnostics, 'parsedAs' => 'corporate'];
        }

        if ($hasStructuredParts) {
            if ($family === '' && $given === '') {
                $diagnostics[] = self::endnoteNameDiagnostic($group, $role, $entryIndex, $element->localName, $raw, 'endnote-name-empty-structured-parts');
                if ($raw === '' || $suffix !== '') {
                    return ['name' => null, 'diagnostics' => $diagnostics, 'parsedAs' => 'skipped'];
                }

                return ['name' => self::risName($raw), 'diagnostics' => $diagnostics, 'parsedAs' => 'personal-fallback'];
            }

            if ($family === '') {
                $diagnostics[] = self::endnoteNameDiagnostic($group, $role, $entryIndex, 'last-name', $raw, 'endnote-name-missing-family');
            }
            if ($given === '') {
                $diagnostics[] = self::endnoteNameDiagnostic($group, $role, $entryIndex, 'first-name', $raw, 'endnote-name-missing-given');
            }

            $name = [];
            if ($family !== '') {
                $name['family'] = $family;
            }
            if ($given !== '') {
                $name['given'] = $given;
            }
            if ($suffix !== '') {
                $name['suffix'] = $suffix;
            }

            return ['name' => $name, 'diagnostics' => $diagnostics, 'parsedAs' => 'personal-parts'];
        }

        if ($raw === '') {
            $diagnostics[] = self::endnoteNameDiagnostic($group, $role, $entryIndex, $element->localName, $raw, 'endnote-name-empty-entry');

            return ['name' => null, 'diagnostics' => $diagnostics, 'parsedAs' => 'skipped'];
        }

        if (str_contains($raw, ',')) {
            [$rawFamily, $rawGiven] = array_map('trim', explode(',', $raw, 2));
            if ($rawFamily === '' || $rawGiven === '') {
                $diagnostics[] = self::endnoteNameDiagnostic($group, $role, $entryIndex, $element->localName, $raw, 'endnote-name-malformed-comma-parts');
            }
        }

        $name = self::risName($raw);
        $parsedAs = array_key_exists('literal', $name) ? 'corporate' : 'personal-comma';

        return ['name' => $name, 'diagnostics' => $diagnostics, 'parsedAs' => $parsedAs];
    }

    /**
     * @param list<string> $localNames
     */
    private static function endnoteHasAnyDescendant(\DOMElement $element, array $localNames): bool
    {
        foreach ($localNames as $localName) {
            if (XmlHtmlDom::descendantElements($element, $localName) !== []) {
                return true;
            }
        }

        return false;
    }

    private static function endnoteNameElementIsCorporate(\DOMElement $element): bool
    {
        if ($element->localName === 'corporate-author') {
            return true;
        }

        foreach (['corporate', 'name-type', 'type', 'role'] as $attribute) {
            $value = strtolower(trim($element->getAttribute($attribute)));
            if (in_array($value, ['1', 'true', 'yes', 'corporate', 'organization', 'organisation', 'institution'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{group:string, role:string, entryIndex:int, field:string, value:string, reason:string, severity:string}
     */
    private static function endnoteNameDiagnostic(string $group, string $role, int $entryIndex, string $field, string $value, string $reason): array
    {
        return [
            'group' => $group,
            'role' => $role,
            'entryIndex' => $entryIndex,
            'field' => $field,
            'value' => $value,
            'reason' => $reason,
            'severity' => 'warning',
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function endnoteNameDiagnosticSummary(array $diagnostics): string
    {
        return self::endnoteReasonSummary($diagnostics);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function endnoteReasonSummary(array $diagnostics): string
    {
        $counts = [];
        foreach ($diagnostics as $diagnostic) {
            $reason = trim((string) ($diagnostic['reason'] ?? ''));
            if ($reason === '') {
                continue;
            }

            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }
        ksort($counts);

        $parts = [];
        foreach ($counts as $reason => $count) {
            $parts[] = $reason . ': ' . $count;
        }

        return implode('; ', $parts);
    }

    /**
     * @return array{
     *     issued:array<string, mixed>|null,
     *     fields:list<array{field:string, value:string, parent:string}>,
     *     diagnostics:list<array{field:string, value:string, parent:string, reason:string, severity:string}>
     * }
     */
    private static function endnoteDatePacket(\DOMElement $record): array
    {
        $fields = [];
        $diagnostics = [];
        $candidates = [];
        foreach (['year', 'date', 'pub-date', 'publication-date', 'issue-date'] as $fieldName) {
            foreach (XmlHtmlDom::descendantElements($record, $fieldName) as $element) {
                $value = XmlHtmlDom::normalizedText($element);
                $parent = $element->parentNode instanceof \DOMElement ? $element->parentNode->localName : '';
                $fields[] = ['field' => $fieldName, 'value' => $value, 'parent' => $parent];
                if ($value === '') {
                    $diagnostics[] = self::endnoteDateDiagnostic($fieldName, $value, $parent, 'endnote-date-empty-field');
                    continue;
                }

                $parsed = self::endnoteParsedDate($value);
                if ($parsed['malformed']) {
                    $diagnostics[] = self::endnoteDateDiagnostic($fieldName, $value, $parent, 'endnote-date-malformed-field');
                }

                $candidates[] = ['field' => $fieldName, 'date' => $parsed['date'], 'rank' => self::endnoteDateRank($parsed['date'])];
            }
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => ((int) $right['rank']) <=> ((int) $left['rank'])
        );

        return [
            'issued' => isset($candidates[0]['date']) && is_array($candidates[0]['date']) ? $candidates[0]['date'] : null,
            'fields' => $fields,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{date:array<string, mixed>, malformed:bool}
     */
    private static function endnoteParsedDate(string $value): array
    {
        $normalized = str_replace(['.', '/'], '-', trim($value));
        if (preg_match('/^(-?\d{1,4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?$/', $normalized, $matches) === 1) {
            $parts = [(int) $matches[1]];
            $month = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : null;
            $day = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : null;
            if ($month !== null) {
                if ($month < 1 || $month > 12) {
                    return ['date' => ['literal' => $value, 'raw' => $value], 'malformed' => true];
                }
                $parts[] = $month;
            }
            if ($day !== null) {
                if ($day < 1 || $day > 31) {
                    return ['date' => ['literal' => $value, 'raw' => $value], 'malformed' => true];
                }
                $parts[] = $day;
            }

            return ['date' => ['date-parts' => [$parts], 'raw' => $value], 'malformed' => false];
        }

        if (preg_match('/\b(\d{4})\b/', $value, $matches) === 1) {
            return ['date' => ['date-parts' => [[(int) $matches[1]]], 'raw' => $value], 'malformed' => false];
        }

        return ['date' => ['literal' => $value, 'raw' => $value], 'malformed' => true];
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function endnoteDateRank(array $date): int
    {
        $parts = $date['date-parts'][0] ?? null;
        if (is_array($parts) && $parts !== []) {
            return 10 + count($parts);
        }

        return trim((string) ($date['literal'] ?? '')) !== '' ? 1 : 0;
    }

    /**
     * @return array{field:string, value:string, parent:string, reason:string, severity:string}
     */
    private static function endnoteDateDiagnostic(string $field, string $value, string $parent, string $reason): array
    {
        return [
            'field' => $field,
            'value' => $value,
            'parent' => $parent,
            'reason' => $reason,
            'severity' => 'warning',
        ];
    }

    private static function endnoteFirstRelatedUrl(\DOMElement $record): string
    {
        foreach (self::endnoteUrlRecordsByParent($record, ['related-urls', 'web-urls']) as $entry) {
            return $entry['url'];
        }

        foreach (self::endnoteUrlRecordsByParent($record, ['urls']) as $entry) {
            return $entry['url'];
        }

        return '';
    }

    /**
     * @param list<string> $parentNames
     * @return list<array{url:string, parent:string}>
     */
    private static function endnoteUrlRecordsByParent(\DOMElement $record, array $parentNames): array
    {
        $records = [];
        foreach (XmlHtmlDom::descendantElements($record, 'url') as $urlElement) {
            $parent = $urlElement->parentNode;
            if (!$parent instanceof \DOMElement || !in_array($parent->localName, $parentNames, true)) {
                continue;
            }

            $url = XmlHtmlDom::normalizedText($urlElement);
            if ($url !== '') {
                $records[] = ['url' => $url, 'parent' => $parent->localName];
            }
        }

        return $records;
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>
     */
    private static function endnoteSourceFileDiagnostics(\DOMElement $record): array
    {
        $diagnostics = [];
        foreach (self::endnoteUrlRecordsByParent($record, ['pdf-urls', 'image-urls']) as $entry) {
            $isPdf = $entry['parent'] === 'pdf-urls';
            $diagnostics[] = [
                'label' => $isPdf ? 'EndNote PDF URL' : 'EndNote image URL',
                'path' => $entry['url'],
                'mediaType' => $isPdf ? 'application/pdf' : 'image/*',
                'reason' => 'endnote-attachment-not-imported',
                'importable' => false,
            ];
        }

        return $diagnostics;
    }

    private static function endnoteDatabaseName(\DOMElement $record): string
    {
        $database = self::endnoteFirstElement($record, ['database']);
        if (!$database instanceof \DOMElement) {
            return '';
        }

        $name = trim($database->getAttribute('name'));

        return $name !== '' ? $name : XmlHtmlDom::normalizedText($database);
    }

    /**
     * @return list<array{field:string, value:string, reason:string}>
     */
    private static function endnoteUnsupportedFields(\DOMElement $record): array
    {
        $fields = [];
        $fieldNames = [
            'caption',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'custom5',
            'custom6',
            'custom7',
            'custom8',
            'remote-database-name',
            'research-notes',
        ];
        foreach ($fieldNames as $fieldName) {
            foreach (XmlHtmlDom::descendantElements($record, $fieldName) as $element) {
                $value = XmlHtmlDom::normalizedText($element);
                if ($value !== '') {
                    $fields[] = [
                        'field' => $fieldName,
                        'value' => $value,
                        'reason' => 'endnote-field-preserved-raw-only',
                    ];
                }
            }
        }

        return $fields;
    }

    /**
     * @param list<array{field:string, value:string, reason:string}> $fields
     */
    private static function endnoteUnsupportedFieldSummary(array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $parts[] = $field['field'] . ': ' . $field['value'];
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $keys
     * @return list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}>
     */
    private static function namesFromFirstItemField(array $item, string $id, string $field, array $keys): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item) || $item[$key] === null || $item[$key] === []) {
                continue;
            }

            return self::names($item[$key], $id, $field);
        }

        return [];
    }

    /**
     * @param list<array{0:string, 1:string}> $identifiers
     */
    private static function labeledIdentifierSummary(array $identifiers): string
    {
        $parts = [];
        foreach ($identifiers as [$label, $value]) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $parts[] = $label . ' ' . $value;
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function stringField(array $item, string $key): string
    {
        $value = $item[$key] ?? '';
        if ($value === null) {
            return '';
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('CSL field ' . $key . ' must be scalar when present');
        }

        return trim((string) $value);
    }

    /**
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}> $names
     */
    private static function stringOrNamesField(array $item, string $key, string $id, array $names): string
    {
        return self::stringOrNamesValue($item[$key] ?? '', $key, $id, $names);
    }

    /**
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}> $names
     */
    private static function stringOrNamesValue(mixed $value, string $key, string $id, array $names): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value) && array_is_list($value)) {
            return implode('; ', array_values(array_filter(
                array_map(
                    static fn (array $name): string => self::plainNameDisplay($name),
                    $names
                ),
                static fn (string $name): bool => $name !== ''
            )));
        }

        throw new \InvalidArgumentException('CSL field ' . $key . ' must be scalar or a list of names when present on item ' . $id);
    }

    private static function firstPageFromRange(string $pages): string
    {
        $pages = trim($pages);
        if ($pages === '') {
            return '';
        }

        $parts = preg_split('/\s*(?:[-\x{2010}-\x{2015}]|,|&|\band\b)\s*/u', $pages, 2);

        return trim((string) ($parts[0] ?? $pages));
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $keys
     */
    private static function firstStringField(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $value = self::firstScalarValue($value);
                if ($value === null) {
                    continue;
                }
            }

            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('CSL field ' . $key . ' must be scalar when present');
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function firstScalarValue(array $values): mixed
    {
        foreach ($values as $value) {
            if (is_scalar($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $titleKeys
     * @param list<string> $subtitleKeys
     */
    private static function composedStringField(array $item, array $titleKeys, array $subtitleKeys): string
    {
        $title = self::firstStringField($item, $titleKeys);
        $subtitle = self::firstStringField($item, $subtitleKeys);
        if ($title === '') {
            return $subtitle;
        }

        if ($subtitle === '') {
            return $title;
        }

        $separator = preg_match('/[.?!:]\z/u', $title) === 1 ? ' ' : ': ';

        return $title . $separator . $subtitle;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $keys
     */
    private static function firstPresentField(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item)) {
                continue;
            }

            $value = $item[$key];
            if ($value === null || $value === []) {
                continue;
            }

            return $value;
        }

        return null;
    }

    private static function patentTypeLabel(string $type): string
    {
        $type = trim($type);
        if ($type === '') {
            return '';
        }

        $normalized = strtolower(str_replace(['_', ' '], '', $type));

        return match ($normalized) {
            'patent' => 'Patent',
            'patentde' => 'German patent',
            'patenteu' => 'European patent',
            'patentfr' => 'French patent',
            'patentuk', 'patentgb' => 'British patent',
            'patentus' => 'U.S. patent',
            'patreq' => 'Patent request',
            'patreqde' => 'German patent request',
            'patreqeu' => 'European patent request',
            'patreqfr' => 'French patent request',
            'patrequk', 'patreqgb' => 'British patent request',
            'patrequs' => 'U.S. patent request',
            default => strtoupper($type[0]) . substr($type, 1),
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function shorthandListSortKey(array $item): string
    {
        $sortShorthand = self::firstStringField($item, ['sort-shorthand', 'sortShorthand', 'sortshorthand']);
        if ($sortShorthand !== '') {
            return $sortShorthand;
        }

        return self::firstStringField($item, ['shorthand']);
    }

    private static function archiveSummary(string $archive, string $archiveCollection, string $archivePlace, string $archiveLocation): string
    {
        if ($archiveCollection !== '') {
            $summary = implode(':', array_values(array_filter(
                [$archive, $archiveCollection, $archiveLocation],
                static fn (string $value): bool => $value !== ''
            )));

            return $summary . ($archivePlace !== '' ? ' [' . $archivePlace . ']' : '');
        }

        if ($archive !== '' && $archiveLocation !== '') {
            return $archive . ':' . $archiveLocation . ($archivePlace !== '' ? ' [' . $archivePlace . ']' : '');
        }

        if ($archiveLocation !== '') {
            return $archiveLocation . ($archivePlace !== '' ? ' [' . $archivePlace . ']' : '');
        }

        return implode(' ', array_values(array_filter([$archive, $archivePlace], static fn (string $value): bool => $value !== '')));
    }

    /**
     * @return list<string>
     */
    private static function stringListField(array $item, string $key): array
    {
        return self::stringListValue($item[$key] ?? [], $key);
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $keys
     * @return list<string>
     */
    private static function stringListFromFirstField(array $item, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            return self::stringListValue($value, $key);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function stringListValue(mixed $value, string $key): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_scalar($value)) {
            $parts = preg_split('/\s*[,;]\s*/', (string) $value) ?: [];

            return array_values(array_filter(
                array_map(static fn (string $part): string => trim($part), $parts),
                static fn (string $part): bool => $part !== ''
            ));
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL field ' . $key . ' must be scalar or a list when present');
        }

        $strings = [];
        foreach ($value as $index => $part) {
            if (!is_scalar($part)) {
                throw new \InvalidArgumentException('CSL field ' . $key . '[' . $index . '] must be scalar');
            }

            $part = trim((string) $part);
            if ($part !== '') {
                $strings[] = $part;
            }
        }

        return $strings;
    }

    /**
     * @param list<string> $options
     */
    private static function biblatexSkipBibliography(array $options): bool
    {
        $skip = false;
        foreach ($options as $option) {
            $option = strtolower(trim(str_replace('_', '-', $option)));
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
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private static function biblatexCustomFields(array $item, string $id): array
    {
        $fields = [];
        $value = $item['biblatex-custom-fields'] ?? $item['biblatexCustomFields'] ?? null;
        if ($value !== null && $value !== []) {
            if (!is_array($value) || array_is_list($value)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-fields must be an object map');
            }

            foreach ($value as $field => $fieldValue) {
                $field = strtolower(trim((string) $field));
                if (!in_array($field, self::biblatexCustomFieldNames(), true)) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-fields contains unsupported field ' . $field);
                }

                if (!is_scalar($fieldValue) && $fieldValue !== null) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-fields.' . $field . ' must be scalar');
                }

                $fieldValue = trim((string) ($fieldValue ?? ''));
                if ($fieldValue !== '') {
                    $fields[$field] = $fieldValue;
                }
            }
        }

        foreach (self::biblatexCustomFieldNames() as $field) {
            if (!array_key_exists($field, $item)) {
                continue;
            }

            $fieldValue = $item[$field];
            if (!is_scalar($fieldValue) && $fieldValue !== null) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . ' must be scalar when present');
            }

            $fieldValue = trim((string) ($fieldValue ?? ''));
            if ($fieldValue !== '') {
                $fields[$field] = $fieldValue;
            }
        }

        return self::orderedBiblatexCustomFields($fields);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private static function orderedBiblatexCustomFields(array $fields): array
    {
        $ordered = [];
        foreach (self::biblatexCustomFieldNames() as $field) {
            if (isset($fields[$field]) && $fields[$field] !== '') {
                $ordered[$field] = $fields[$field];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function biblatexCustomFieldNames(): array
    {
        return ['usera', 'userb', 'userc', 'userd', 'usere', 'userf', 'verba', 'verbb', 'verbc'];
    }

    /**
     * @param array<string, string> $fields
     */
    private static function biblatexCustomFieldSummary(array $fields): string
    {
        $parts = [];
        foreach (self::orderedBiblatexCustomFields($fields) as $field => $value) {
            $parts[] = $field . ': ' . $value;
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, list<array{name:string, value:string}>>
     */
    private static function biblatexFieldAnnotations(array $item, string $id): array
    {
        $value = $item['biblatex-field-annotations'] ?? $item['biblatexFieldAnnotations'] ?? null;
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations must be an object map');
        }

        $annotations = [];
        foreach ($value as $field => $fieldValue) {
            $field = self::biblatexFieldAnnotationFieldName((string) $field, $id);
            $fieldAnnotations = self::biblatexFieldAnnotationValues($fieldValue, $id, $field);
            if ($fieldAnnotations !== []) {
                $annotations[$field] = $fieldAnnotations;
            }
        }

        return $annotations;
    }

    private static function biblatexFieldAnnotationFieldName(string $field, string $id): string
    {
        $field = strtolower(str_replace('_', '-', trim($field)));
        if ($field === '' || preg_match('/^[a-z][a-z0-9_.-]*$/', $field) !== 1) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations contains invalid field ' . $field);
        }

        return $field;
    }

    /**
     * @return list<array{name:string, value:string}>
     */
    private static function biblatexFieldAnnotationValues(mixed $value, string $id, string $field): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_scalar($value)) {
            $text = trim((string) $value);
            return $text === '' ? [] : [['name' => 'default', 'value' => $text]];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . ' must be scalar, list, or object map');
        }

        if (!array_is_list($value) && (array_key_exists('value', $value) || array_key_exists('text', $value))) {
            $entry = self::biblatexFieldAnnotationObject($value, $id, $field);
            return $entry === null ? [] : [$entry];
        }

        $annotations = [];
        if (array_is_list($value)) {
            foreach ($value as $index => $entryValue) {
                if ($entryValue === null || $entryValue === '') {
                    continue;
                }

                if (is_scalar($entryValue)) {
                    $text = trim((string) $entryValue);
                    if ($text !== '') {
                        $annotations[] = ['name' => 'default', 'value' => $text];
                    }
                    continue;
                }

                if (!is_array($entryValue)) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . '[' . $index . '] must be scalar or object');
                }

                $entry = self::biblatexFieldAnnotationObject($entryValue, $id, $field);
                if ($entry !== null) {
                    $annotations[] = $entry;
                }
            }

            return $annotations;
        }

        foreach ($value as $name => $entryValue) {
            if (!is_scalar($entryValue) && $entryValue !== null) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . '.' . (string) $name . ' must be scalar');
            }

            $text = trim((string) ($entryValue ?? ''));
            if ($text === '') {
                continue;
            }

            $annotations[] = [
                'name' => self::biblatexFieldAnnotationName((string) $name),
                'value' => $text,
            ];
        }

        return $annotations;
    }

    /**
     * @param array<string, mixed> $value
     * @return array{name:string, value:string}|null
     */
    private static function biblatexFieldAnnotationObject(array $value, string $id, string $field): ?array
    {
        $name = $value['name'] ?? 'default';
        if (!is_scalar($name) && $name !== null) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . '.name must be scalar');
        }

        $text = $value['value'] ?? $value['text'] ?? '';
        if (!is_scalar($text) && $text !== null) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . '.value must be scalar');
        }

        $text = trim((string) ($text ?? ''));
        if ($text === '') {
            return null;
        }

        return [
            'name' => self::biblatexFieldAnnotationName((string) ($name ?? '')),
            'value' => $text,
        ];
    }

    private static function biblatexFieldAnnotationName(string $name): string
    {
        $name = strtolower(str_replace(['_', ' '], '-', trim($name)));

        return $name === '' ? 'default' : $name;
    }

    /**
     * @param array<string, list<array{name:string, value:string}>> $annotations
     */
    private static function biblatexFieldAnnotationSummary(array $annotations): string
    {
        $parts = [];
        foreach ($annotations as $field => $fieldAnnotations) {
            foreach ($fieldAnnotations as $annotation) {
                $value = trim($annotation['value']);
                if ($value === '') {
                    continue;
                }

                $name = trim($annotation['name']);
                $parts[] = $field . ' ' . ($name === '' ? 'default' : $name) . ': ' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, list<string>>
     */
    private static function biblatexCustomLists(array $item, string $id): array
    {
        $lists = [];
        $value = $item['biblatex-custom-lists'] ?? $item['biblatexCustomLists'] ?? null;
        if ($value !== null && $value !== []) {
            if (!is_array($value) || array_is_list($value)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-lists must be an object map');
            }

            foreach ($value as $field => $fieldValue) {
                $field = strtolower(trim((string) $field));
                if (!in_array($field, self::biblatexCustomListNames(), true)) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-lists contains unsupported field ' . $field);
                }

                $fieldValues = self::stringListValue($fieldValue, 'biblatex-custom-lists.' . $field);
                if ($fieldValues !== []) {
                    $lists[$field] = $fieldValues;
                }
            }
        }

        foreach (self::biblatexCustomListNames() as $field) {
            if (!array_key_exists($field, $item)) {
                continue;
            }

            $fieldValues = self::stringListValue($item[$field], $field);
            if ($fieldValues !== []) {
                $lists[$field] = $fieldValues;
            }
        }

        return self::orderedBiblatexCustomLists($lists);
    }

    /**
     * @param array<string, list<string>> $lists
     * @return array<string, list<string>>
     */
    private static function orderedBiblatexCustomLists(array $lists): array
    {
        $ordered = [];
        foreach (self::biblatexCustomListNames() as $field) {
            if (isset($lists[$field]) && $lists[$field] !== []) {
                $ordered[$field] = $lists[$field];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function biblatexCustomListNames(): array
    {
        return ['lista', 'listb', 'listc', 'listd', 'liste', 'listf'];
    }

    /**
     * @param array<string, list<string>> $lists
     */
    private static function biblatexCustomListSummary(array $lists): string
    {
        $parts = [];
        foreach (self::orderedBiblatexCustomLists($lists) as $field => $values) {
            $parts[] = $field . ': ' . implode('; ', $values);
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, list<array<string, mixed>>>
     */
    private static function biblatexCustomNames(array $item, string $id): array
    {
        $names = [];
        $value = $item['biblatex-custom-names'] ?? $item['biblatexCustomNames'] ?? null;
        if ($value !== null && $value !== []) {
            if (!is_array($value) || array_is_list($value)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-names must be an object map');
            }

            foreach ($value as $field => $fieldValue) {
                $field = strtolower(trim((string) $field));
                if (!in_array($field, self::biblatexCustomNameNames(), true)) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-names contains unsupported field ' . $field);
                }

                $fieldNames = self::names($fieldValue, $id, 'biblatex-custom-names.' . $field);
                if ($fieldNames !== []) {
                    $names[$field] = $fieldNames;
                }
            }
        }

        foreach (self::biblatexCustomNameNames() as $field) {
            if (!array_key_exists($field, $item)) {
                continue;
            }

            $fieldNames = self::names($item[$field], $id, $field);
            if ($fieldNames !== []) {
                $names[$field] = $fieldNames;
            }
        }

        return self::orderedBiblatexCustomNames($names);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $names
     * @return array<string, list<array<string, mixed>>>
     */
    private static function orderedBiblatexCustomNames(array $names): array
    {
        $ordered = [];
        foreach (self::biblatexCustomNameNames() as $field) {
            if (isset($names[$field]) && $names[$field] !== []) {
                $ordered[$field] = $names[$field];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function biblatexCustomNameNames(): array
    {
        return ['namea', 'nameb', 'namec'];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $names
     */
    private static function biblatexCustomNameSummary(array $names): string
    {
        $parts = [];
        foreach (self::orderedBiblatexCustomNames($names) as $field => $fieldNames) {
            $values = array_values(array_filter(
                array_map(
                    static fn (array $name): string => self::biblatexCustomNameDisplay($name),
                    $fieldNames
                ),
                static fn (string $value): bool => $value !== ''
            ));
            if ($values !== []) {
                $parts[] = $field . ': ' . implode('; ', $values);
            }
        }

        return implode('; ', $parts);
    }

    private static function biblatexReferenceContextSummary(string $refsection, string $refsegment): string
    {
        $parts = [];
        if ($refsection !== '') {
            $parts[] = 'refsection ' . $refsection;
        }

        if ($refsegment !== '') {
            $parts[] = 'refsegment ' . $refsegment;
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $name
     */
    private static function biblatexCustomNameDisplay(array $name): string
    {
        $literal = trim((string) ($name['literal'] ?? ''));
        if ($literal !== '') {
            return $literal;
        }

        $given = trim((string) ($name['given'] ?? ''));
        $family = trim(implode(' ', array_values(array_filter([
            trim((string) ($name['nonDroppingParticle'] ?? '')),
            trim((string) ($name['family'] ?? '')),
        ], static fn (string $part): bool => $part !== ''))));
        $suffix = trim((string) ($name['suffix'] ?? ''));

        if ($family !== '' && $given !== '') {
            $display = $family . ', ' . $given;
        } else {
            $display = $family !== '' ? $family : $given;
        }

        return $suffix !== '' && $display !== '' ? $display . ', ' . $suffix : $display;
    }

    /**
     * @return array{files:list<array{label:string, path:string, mediaType:string}>, diagnostics:list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>}
     */
    private static function sourceFilesWithDiagnostics(mixed $value, string $id): array
    {
        if ($value === null || $value === []) {
            return ['files' => [], 'diagnostics' => []];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles must be a list');
        }

        $files = [];
        $diagnostics = [];
        foreach ($value as $index => $file) {
            if (is_scalar($file)) {
                $path = trim((string) $file);
                if ($path !== '') {
                    $policy = self::sourceFilePathPolicy($path);
                    if ($policy['reason'] === '') {
                        $files[] = [
                            'label' => '',
                            'path' => $policy['path'],
                            'mediaType' => '',
                        ];
                    } else {
                        $diagnostics[] = self::sourceFilePolicyDiagnostic('', $path, '', $policy['reason']);
                    }
                }
                continue;
            }

            if (!is_array($file)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles[' . $index . '] must be an object or path string');
            }

            $path = self::sourceFileString($file['path'] ?? '', $id, $index, 'path');
            if ($path === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles[' . $index . '] is missing path');
            }

            $label = self::sourceFileString($file['label'] ?? '', $id, $index, 'label');
            $mediaType = self::sourceFileString($file['mediaType'] ?? '', $id, $index, 'mediaType');
            $policy = self::sourceFilePathPolicy($path);
            if ($policy['reason'] === '') {
                $files[] = [
                    'label' => $label,
                    'path' => $policy['path'],
                    'mediaType' => $mediaType,
                ];
            } else {
                $diagnostics[] = self::sourceFilePolicyDiagnostic($label, $path, $mediaType, $policy['reason']);
            }
        }

        return ['files' => $files, 'diagnostics' => $diagnostics];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{files:list<array{label:string, path:string, mediaType:string}>, diagnostics:list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>}
     */
    private static function sourceFilePolicy(array $item, string $id): array
    {
        if (array_key_exists('sourceFiles', $item)) {
            return self::sourceFilesWithDiagnostics($item['sourceFiles'], $id);
        }

        $value = self::firstPresentField($item, [
            'source-files',
            'sourcefiles',
            'sourceFile',
            'source-file',
            'sourcefile',
            'source-attachments',
            'sourceAttachments',
            'sourceattachments',
            'attachments',
            'attachment',
            'file',
            'pdf',
        ]);

        if (is_scalar($value)) {
            return self::compactSourceFilesWithDiagnostics((string) $value);
        }

        return self::sourceFilesWithDiagnostics($value, $id);
    }

    /**
     * @return array{files:list<array{label:string, path:string, mediaType:string}>, diagnostics:list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>}
     */
    private static function compactSourceFilesWithDiagnostics(string $value): array
    {
        $files = [];
        $diagnostics = [];

        foreach (explode(';', $value) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $parsed = self::compactSourceFileEntry($entry);
            $policy = self::sourceFilePathPolicy($parsed['path']);
            if ($policy['reason'] === '') {
                $files[] = [
                    'label' => $parsed['label'],
                    'path' => $policy['path'],
                    'mediaType' => $parsed['mediaType'],
                ];
            } else {
                $diagnostics[] = self::sourceFilePolicyDiagnostic(
                    $parsed['label'],
                    $parsed['path'],
                    $parsed['mediaType'],
                    $policy['reason']
                );
            }
        }

        return ['files' => $files, 'diagnostics' => $diagnostics];
    }

    /**
     * @return array{label:string, path:string, mediaType:string}
     */
    private static function compactSourceFileEntry(string $entry): array
    {
        $parts = array_map('trim', explode(':', $entry));
        if (count($parts) >= 3) {
            $mediaType = array_pop($parts) ?? '';
            if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*$/', $parts[0]) === 1 && str_starts_with($parts[1] ?? '', '//')) {
                $label = '';
                $path = implode(':', $parts);
            } else {
                $label = array_shift($parts) ?? '';
                $path = implode(':', $parts);
            }
        } elseif (count($parts) === 2) {
            $label = '';
            if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*$/', $parts[0]) === 1 && str_starts_with($parts[1], '//')) {
                $path = $parts[0] . ':' . $parts[1];
                $mediaType = '';
            } else {
                [$path, $mediaType] = $parts;
            }
        } else {
            $label = '';
            $path = $entry;
            $mediaType = '';
        }

        return [
            'label' => $label,
            'path' => trim($path),
            'mediaType' => $mediaType,
        ];
    }

    private static function sourceFileString(mixed $value, string $id, int $index, string $field): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles[' . $index . '].' . $field . ' must be scalar');
        }

        return trim((string) $value);
    }

    /**
     * @return list<array{id:string, type:string, title:string, issuedDate:array{year:?int, parts:list<int>, display:string, literal:string, rangeParts?:list<list<int>>}, display:string}>
     */
    private static function relatedItemSummaries(mixed $value, string $id, string $field = 'relatedItems'): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' must be a list');
        }

        $summaries = [];
        foreach ($value as $index => $related) {
            if (!is_array($related)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . '[' . $index . '] must be an object');
            }

            $relatedId = self::firstStringField($related, ['id', 'citation-key']);
            $title = self::stringField($related, 'title');
            if ($relatedId === '' && $title === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . '[' . $index . '] must include id or title');
            }

            $issuedDate = self::dateVariable($related['issued'] ?? null, $id, $field . '[' . $index . '].issued');
            $summaries[] = [
                'id' => $relatedId,
                'type' => self::stringField($related, 'type'),
                'title' => $title,
                'issuedDate' => $issuedDate,
                'display' => self::relatedItemSummaryDisplay($relatedId, $title, $issuedDate),
            ];
        }

        return $summaries;
    }

    /**
     * @param array{display:string, literal:string} $issuedDate
     */
    private static function relatedItemSummaryDisplay(string $id, string $title, array $issuedDate): string
    {
        $label = $title !== '' ? $title : $id;
        $date = trim((string) ($issuedDate['display'] ?? ''));

        return $date !== '' ? $label . ' (' . $date . ')' : $label;
    }

    /**
     * @param list<array{display:string}> $referencedItems
     * @param list<string> $missingKeys
     * @param list<string> $fallbackKeys
     */
    private static function summarizedReferenceValues(array $referencedItems, array $missingKeys, array $fallbackKeys): string
    {
        $values = [];
        foreach ($referencedItems as $referencedItem) {
            $display = trim((string) ($referencedItem['display'] ?? ''));
            if ($display !== '') {
                $values[] = $display;
            }
        }

        foreach ($missingKeys as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $values[] = 'missing: ' . $key;
            }
        }

        if ($values === []) {
            $values = array_values(array_filter(
                array_map(static fn (mixed $key): string => trim((string) $key), $fallbackKeys),
                static fn (string $key): bool => $key !== ''
            ));
        }

        return implode('; ', $values);
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>
     */
    private static function sourceFileDiagnostics(mixed $value, string $id): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFileDiagnostics must be a list');
        }

        $diagnostics = [];
        foreach ($value as $index => $diagnostic) {
            if (!is_array($diagnostic)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFileDiagnostics[' . $index . '] must be an object');
            }

            $reason = self::sourceFileString($diagnostic['reason'] ?? '', $id, $index, 'reason');
            if ($reason === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFileDiagnostics[' . $index . '] is missing reason');
            }

            $diagnostics[] = [
                'label' => self::sourceFileString($diagnostic['label'] ?? '', $id, $index, 'label'),
                'path' => self::sourceFileString($diagnostic['path'] ?? '', $id, $index, 'path'),
                'mediaType' => self::sourceFileString($diagnostic['mediaType'] ?? '', $id, $index, 'mediaType'),
                'reason' => $reason,
                'importable' => self::boolField($diagnostic, 'importable', false),
            ];
        }

        return $diagnostics;
    }

    /**
     * @return array{path:string, reason:string}
     */
    private static function sourceFilePathPolicy(string $path): array
    {
        if ($path === '') {
            return ['path' => '', 'reason' => 'missing-path'];
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return ['path' => $path, 'reason' => 'control-character'];
        }

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            return ['path' => $path, 'reason' => 'windows-drive-path'];
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) === 1) {
            return ['path' => $path, 'reason' => 'remote-uri'];
        }

        if (str_starts_with($path, '//')) {
            return ['path' => $path, 'reason' => 'uri-authority-path'];
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return ['path' => $path, 'reason' => 'absolute-path'];
        }

        if (str_contains($path, '\\')) {
            return ['path' => $path, 'reason' => 'backslash-separator'];
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $path) === 1) {
            return ['path' => $path, 'reason' => 'malformed-percent-escape'];
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            $decoded = rawurldecode($segment);
            if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1 || str_contains($decoded, '/') || str_contains($decoded, '\\')) {
                return ['path' => $path, 'reason' => 'unsafe-percent-encoded-path-byte'];
            }

            if ($decoded === '..') {
                return ['path' => $path, 'reason' => 'path-traversal'];
            }

            $segments[] = $decoded;
        }

        if ($segments === []) {
            return ['path' => $path, 'reason' => 'missing-path'];
        }

        return ['path' => implode('/', $segments), 'reason' => ''];
    }

    /**
     * @return array{label:string, path:string, mediaType:string, reason:string, importable:bool}
     */
    private static function sourceFilePolicyDiagnostic(string $label, string $path, string $mediaType, string $reason): array
    {
        return [
            'label' => $label,
            'path' => $path,
            'mediaType' => $mediaType,
            'reason' => $reason,
            'importable' => false,
        ];
    }

    /**
     * @return list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}>
     */
    private static function names(mixed $value, string $id, string $field): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . ' must be a list of names');
        }

        $names = [];
        foreach ($value as $index => $name) {
            if (!is_array($name)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $index . '] must be an object');
            }

            $literal = self::nameString($name['literal'] ?? '');
            $short = self::nameString($name['short'] ?? $name['short-form'] ?? $name['literal-short'] ?? $name['shortLiteral'] ?? '');
            $family = self::nameString($name['family'] ?? '');
            $given = self::nameString($name['given'] ?? '');
            $nonDroppingParticle = self::nameString($name['non-dropping-particle'] ?? '');
            $droppingParticle = self::nameString($name['dropping-particle'] ?? '');
            $suffix = self::nameString($name['suffix'] ?? '');
            $etAl = self::boolField($name, 'csl-et-al', false)
                || self::boolField($name, 'etAl', false)
                || self::boolField($name, 'et-al', false);
            if (!$etAl && $literal === '' && $family === '' && $given === '' && $nonDroppingParticle === '' && $droppingParticle === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $index . '] must include a name value');
            }

            $names[] = [
                'family' => $family,
                'given' => $given,
                'literal' => $literal,
                'short' => $short,
                'nonDroppingParticle' => $nonDroppingParticle,
                'droppingParticle' => $droppingParticle,
                'suffix' => $suffix,
                'commaSuffix' => self::boolField($name, 'comma-suffix', false),
                'staticOrdering' => self::boolField($name, 'static-ordering', false),
                'parseNames' => self::boolField($name, 'parse-names', true),
                'etAl' => $etAl,
                'annotations' => self::nameAnnotations($name['annotations'] ?? [], $id, $field, $index),
            ];
        }

        return $names;
    }

    /**
     * @return list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}>
     */
    private static function namesOrLiteral(mixed $value, string $id, string $field): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_scalar($value)) {
            $literal = trim((string) $value);

            return $literal === '' ? [] : self::names([['literal' => $literal]], $id, $field);
        }

        if (is_array($value) && array_is_list($value)) {
            $literalNames = [];
            foreach ($value as $name) {
                if (!is_scalar($name)) {
                    return self::names($value, $id, $field);
                }

                $literal = trim((string) $name);
                if ($literal !== '') {
                    $literalNames[] = ['literal' => $literal];
                }
            }

            return $literalNames === [] ? [] : self::names($literalNames, $id, $field);
        }

        return self::names($value, $id, $field);
    }

    /**
     * @param array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     */
    private static function plainNameDisplay(array $name): string
    {
        $literal = trim((string) ($name['literal'] ?? ''));
        if ($literal !== '') {
            return $literal;
        }

        $given = trim((string) ($name['given'] ?? ''));
        if (self::nameUsesFamilyGivenDisplayOrder($name)) {
            $family = trim((string) ($name['family'] ?? ''));
            $separator = self::nameUsesCompactFamilyGivenScript($name) ? '' : ' ';
            $display = implode($separator, array_values(array_filter(
                [$family, $given],
                static fn (string $part): bool => $part !== ''
            )));
        } else {
            $family = trim(implode(' ', array_values(array_filter([
                trim((string) ($name['nonDroppingParticle'] ?? '')),
                trim((string) ($name['family'] ?? '')),
            ], static fn (string $part): bool => $part !== ''))));
            $display = trim($given . ($given !== '' && $family !== '' ? ' ' : '') . $family);
        }
        $suffix = trim((string) ($name['suffix'] ?? ''));

        return $suffix !== '' && $display !== '' ? $display . ', ' . $suffix : $display;
    }

    /**
     * @return list<array{part:string, value:string}>
     */
    private static function nameAnnotations(mixed $value, string $id, string $field, int $nameIndex): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $nameIndex . '].annotations must be a list');
        }

        $annotations = [];
        foreach ($value as $index => $annotation) {
            if (!is_array($annotation)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $nameIndex . '].annotations[' . $index . '] must be an object');
            }

            $part = self::nameString($annotation['part'] ?? 'name');
            $part = strtolower(str_replace('_', '-', $part));
            $text = self::nameString($annotation['value'] ?? '');
            if ($text === '') {
                continue;
            }

            $annotations[] = [
                'part' => $part === '' ? 'name' : $part,
                'value' => $text,
            ];
        }

        return $annotations;
    }

    /**
     * @return list<array{field:string, type:string, label:string, names:list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}>}>
     */
    private static function editorialRoles(mixed $value, string $id): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' editorial-roles must be a list');
        }

        $roles = [];
        foreach ($value as $index => $role) {
            if (!is_array($role)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' editorial-roles[' . $index . '] must be an object');
            }

            $names = self::names($role['names'] ?? [], $id, 'editorial-roles[' . $index . '].names');
            if ($names === []) {
                continue;
            }

            $type = self::nameString($role['type'] ?? '');
            $label = self::nameString($role['label'] ?? '');
            $roles[] = [
                'field' => self::nameString($role['field'] ?? ''),
                'type' => $type === '' ? 'editor' : $type,
                'label' => $label === '' ? self::editorialRoleDefaultLabel($type) : $label,
                'names' => $names,
            ];
        }

        return $roles;
    }

    private static function editorialRoleDefaultLabel(string $type): string
    {
        $type = str_replace(['_', '-'], ' ', strtolower(trim($type)));
        if ($type === '') {
            return 'Editor';
        }

        return ucfirst($type);
    }

    private static function nameString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('CSL name fields must be scalar');
        }

        return trim((string) $value);
    }

    /**
     * @return array{year:?int, parts:list<int>, display:string, literal:string, raw?:string, time?:string, endTime?:string, era?:string, circa?:bool, uncertain?:bool, season?:int, seasonName?:string, openEnded?:string, rangeParts?:list<list<int>>}
     */
    private static function dateVariable(mixed $date, string $id, string $field): array
    {
        if ($date === null || $date === []) {
            return [
                'year' => null,
                'parts' => [],
                'display' => '',
                'literal' => '',
            ];
        }

        if (!is_array($date)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' field must be an object');
        }

        $literal = self::stringField($date, 'literal');
        $raw = self::stringField($date, 'raw');
        $time = self::dateTimeField($date, 'time', $id, $field);
        $endTime = self::firstDateTimeField($date, ['end-time', 'endTime'], $id, $field);
        $era = self::dateEraField($date, $id, $field);
        $circa = self::boolField($date, 'circa', false);
        $uncertain = self::boolField($date, 'uncertain', false);
        $openEnded = self::openEndedDateBoundary($date, $id, $field);
        $season = self::dateSeasonField($date, $id, $field);
        $dateParts = $date['date-parts'] ?? null;
        if ($dateParts === null || $dateParts === []) {
            if ($openEnded !== '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' open-ended date must include date-parts');
            }

            if ($season !== null) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' season date must include date-parts');
            }

            $normalized = [
                'year' => null,
                'parts' => [],
                'display' => $literal,
                'literal' => $literal,
            ];
            if ($raw !== '') {
                $normalized['raw'] = $raw;
            }

            if ($time !== '') {
                $normalized['time'] = $time;
            }

            if ($endTime !== '') {
                $normalized['endTime'] = $endTime;
            }

            if ($era !== '') {
                $normalized['era'] = $era;
            }

            if ($circa) {
                $normalized['circa'] = true;
            }

            if ($uncertain) {
                $normalized['uncertain'] = true;
            }

            return $normalized;
        }

        if (!is_array($dateParts) || !isset($dateParts[0]) || !is_array($dateParts[0]) || !isset($dateParts[0][0])) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' date-parts must contain a year');
        }

        $rangeParts = [];
        foreach (array_slice($dateParts, 0, 2) as $rangeIndex => $rangePart) {
            if (!is_array($rangePart) || !isset($rangePart[0])) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' date-parts[' . $rangeIndex . '] must contain a year');
            }

            $rangeParts[] = self::normalizedDatePartList($rangePart, $id, $field);
        }

        $parts = $rangeParts[0];
        if ($season !== null) {
            foreach ($rangeParts as $rangeIndex => $rangePart) {
                if (count($rangePart) !== 1) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' season date-parts[' . $rangeIndex . '] must contain only a year');
                }
            }
        }

        $normalized = [
            'year' => $parts[0],
            'parts' => $parts,
            'display' => self::formatDatePartsRange($rangeParts, $season),
            'literal' => '',
        ];
        if ($season !== null) {
            $normalized['season'] = $season;
            $normalized['seasonName'] = self::dateSeasonName($season);
        }

        if (count($rangeParts) > 1) {
            if ($openEnded !== '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' open-ended date-parts must contain one endpoint');
            }

            $normalized['rangeParts'] = $rangeParts;
        }

        if ($openEnded !== '') {
            $normalized['openEnded'] = $openEnded;
            $normalized['display'] = self::formatOpenEndedDatePartsRange($rangeParts, $openEnded, $season);
        }

        if ($raw !== '') {
            $normalized['raw'] = $raw;
        }

        if ($time !== '') {
            $normalized['time'] = $time;
        }

        if ($endTime !== '') {
            $normalized['endTime'] = $endTime;
        }

        if ($era !== '') {
            $normalized['era'] = $era;
        }

        if ($circa) {
            $normalized['circa'] = true;
        }

        if ($uncertain) {
            $normalized['uncertain'] = true;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function dateEraField(array $date, string $id, string $field): string
    {
        foreach (['era', 'date-era', 'dateEra'] as $key) {
            if (!array_key_exists($key, $date) || $date[$key] === null || $date[$key] === '') {
                continue;
            }

            if (!is_scalar($date[$key])) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' era field must be scalar');
            }

            return strtolower(str_replace('_', '-', trim((string) $date[$key])));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function openEndedDateBoundary(array $date, string $id, string $field): string
    {
        foreach (['open-ended', 'openEnded'] as $key) {
            if (!array_key_exists($key, $date) || $date[$key] === null || $date[$key] === '') {
                continue;
            }

            if (!is_scalar($date[$key])) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' open-ended field must be scalar');
            }

            $boundary = strtolower(trim(str_replace('_', '-', (string) $date[$key])));
            if ($boundary === 'start' || $boundary === 'end') {
                return $boundary;
            }

            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' open-ended field must be start or end');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function dateSeasonField(array $date, string $id, string $field): ?int
    {
        if (!array_key_exists('season', $date) || $date['season'] === null || $date['season'] === '') {
            return null;
        }

        $season = $date['season'];
        if (!is_int($season) && !(is_string($season) && preg_match('/^\d+$/', $season) === 1)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' season must be numeric');
        }

        $number = (int) $season;
        if ($number < 1 || $number > 4) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' season must be between 1 and 4');
        }

        return $number;
    }

    /**
     * @param array<string, array<string, mixed>> $dates
     */
    private static function dateMarkerSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            $status = self::dateMarkerStatus($date);
            if ($status === '') {
                continue;
            }

            $raw = trim((string) ($date['raw'] ?? ''));
            $parts[] = $label . ' ' . $status . ($raw === '' ? '' : ' (' . $raw . ')');
        }

        return $parts === [] ? '' : 'Date markers: ' . implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function dateMarkerStatus(array $date): string
    {
        $circa = ($date['circa'] ?? false) === true;
        $uncertain = ($date['uncertain'] ?? false) === true;
        if ($circa && $uncertain) {
            return 'circa and uncertain';
        }

        if ($circa) {
            return 'circa';
        }

        return $uncertain ? 'uncertain' : '';
    }

    /**
     * @param array<string, array<string, mixed>> $dates
     */
    private static function dateTimeSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            $time = trim((string) ($date['time'] ?? ''));
            $endTime = trim((string) ($date['endTime'] ?? ''));
            if ($time === '' && $endTime === '') {
                continue;
            }

            $parts[] = $label . ' ' . ($time !== '' ? $time : '?') . ($endTime === '' ? '' : '/' . $endTime);
        }

        return $parts === [] ? '' : 'Date times: ' . implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, mixed>> $dates
     */
    private static function dateSeasonSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            $season = $date['season'] ?? null;
            if (!is_int($season)) {
                continue;
            }

            $parts[] = $label . ' ' . self::dateSeasonName($season);
        }

        return $parts === [] ? '' : 'Date seasons: ' . implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, mixed>> $dates
     */
    private static function dateEraSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            $era = trim((string) ($date['era'] ?? ''));
            if ($era === '') {
                continue;
            }

            $parts[] = $label . ' ' . $era;
        }

        return $parts === [] ? '' : 'Date eras: ' . implode('; ', $parts);
    }

    private static function dateSeasonName(int $season): string
    {
        return match ($season) {
            1 => 'Spring',
            2 => 'Summer',
            3 => 'Autumn',
            4 => 'Winter',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function firstDateTimeField(array $date, array $keys, string $id, string $field): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $date)) {
                continue;
            }

            return self::dateTimeField($date, $key, $id, $field);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function dateTimeField(array $date, string $key, string $id, string $field): string
    {
        $value = $date[$key] ?? '';
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . '.' . $key . ' must be scalar');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}(?::\d{2}(?::\d{2})?)?(?:Z|[+-]\d{2}:\d{2})?$/', $value) !== 1) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . '.' . $key . ' must be a normalized time string');
        }

        return $value;
    }

    /**
     * @param list<mixed> $parts
     * @return list<int>
     */
    private static function normalizedDatePartList(array $parts, string $id, string $field): array
    {
        $normalized = [];
        foreach (array_slice($parts, 0, 3) as $partIndex => $part) {
            if (!is_int($part) && !(is_string($part) && preg_match('/^-?\d+$/', $part) === 1)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' date-parts must be numeric');
            }

            $number = (int) $part;
            if ($partIndex === 1 && ($number < 1 || $number > 12)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' month must be between 1 and 12');
            }

            if ($partIndex === 2 && ($number < 1 || $number > 31)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' day must be between 1 and 31');
            }

            $normalized[] = $number;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function boolField(array $item, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $item) || $item[$key] === null) {
            return $default;
        }

        if (!is_bool($item[$key])) {
            throw new \InvalidArgumentException('CSL field ' . $key . ' must be boolean when present');
        }

        return $item[$key];
    }

    /**
     * @param list<int> $parts
     */
    private static function formatDateParts(array $parts, ?int $season = null): string
    {
        if ($season !== null) {
            return self::dateSeasonName($season) . ' ' . sprintf('%04d', $parts[0]);
        }

        if (count($parts) >= 3) {
            return sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
        }

        if (count($parts) === 2) {
            return sprintf('%04d-%02d', $parts[0], $parts[1]);
        }

        return (string) $parts[0];
    }

    /**
     * @param list<list<int>> $rangeParts
     */
    private static function formatDatePartsRange(array $rangeParts, ?int $season = null): string
    {
        $formatted = array_map(
            static fn (array $parts): string => self::formatDateParts($parts, $season),
            $rangeParts
        );

        return implode('/', $formatted);
    }

    /**
     * @param list<list<int>> $rangeParts
     */
    private static function formatOpenEndedDatePartsRange(array $rangeParts, string $boundary, ?int $season = null): string
    {
        $display = self::formatDatePartsRange($rangeParts, $season);

        return $boundary === 'start' ? '/' . $display : $display . '/';
    }

    /**
     * @return array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>}
     */
    private function emptyCitationPositionState(): array
    {
        return [
            'seenIds' => [],
            'previousUnit' => null,
            'noteCounter' => 0,
            'firstNoteById' => [],
            'lastNoteById' => [],
        ];
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>} $state
     */
    private function annotateCitationPositions(AstNode $node, array &$state, ?array $noteContext = null): AstNode
    {
        if ($node->type === 'note') {
            $noteContext = $this->citationNoteContext($node, $state);
        }

        if ($node->type === 'citation') {
            [$annotated, $info] = $this->annotateCitationPosition($node, $state, null, true, $noteContext);
            $this->recordCitationPositionUnit($state, [$info]);

            return $annotated;
        }

        if ($node->type === 'citation_group') {
            $children = [];
            $unit = [];
            $previousInUnit = null;
            foreach ($node->children as $child) {
                if ($child->type !== 'citation') {
                    throw new \InvalidArgumentException('Citation group entries must be citation AST nodes');
                }

                [$annotated, $info] = $this->annotateCitationPosition($child, $state, $previousInUnit, $children === [], $noteContext);
                $children[] = $annotated;
                $unit[] = $info;
                $previousInUnit = $info;
            }

            $this->recordCitationPositionUnit($state, $unit);

            return new AstNode($node->type, $node->attrs, $children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationPositions($child, $state, $noteContext);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>} $state
     * @param array<string, mixed>|null $previousInUnit
     * @return array{AstNode, array<string, mixed>}
     */
    private function annotateCitationPosition(AstNode $citation, array &$state, ?array $previousInUnit, bool $firstInUnit, ?array $noteContext): array
    {
        $info = $this->citationPositionInfo($citation, $noteContext);
        $position = $this->citationPositionForInfo($info, $state, $previousInUnit, $firstInUnit);
        $attrs = [
            ...$citation->attrs,
            'cslStyleClass' => $this->style->styleClass(),
            'cslPosition' => $position['position'],
            'cslPositionTests' => $position['tests'],
        ];
        if (is_int($info['noteIndex'])) {
            $attrs['cslNoteIndex'] = $info['noteIndex'];
            $attrs['cslNoteType'] = $info['noteType'];
            if ($info['id'] !== '' && !array_key_exists('cslFirstReferenceNoteNumber', $attrs)) {
                $attrs['cslFirstReferenceNoteNumber'] = $this->firstReferenceNoteNumberForInfo($info, $state);
            }
        }

        $annotated = new AstNode('citation', $attrs, $citation->children);

        if ($info['id'] !== '') {
            $state['seenIds'][$info['id']] = true;
            if (is_int($info['noteIndex']) && !isset($state['firstNoteById'][$info['id']])) {
                $state['firstNoteById'][$info['id']] = (int) $info['noteIndex'];
            }
        }

        return [$annotated, $info];
    }

    /**
     * @param array<string, mixed> $info
     * @param array{firstNoteById:array<string, int>} $state
     */
    private function firstReferenceNoteNumberForInfo(array $info, array $state): int
    {
        $id = (string) ($info['id'] ?? '');
        $noteIndex = $info['noteIndex'] ?? null;
        if ($id === '' || !is_int($noteIndex)) {
            return 0;
        }

        return $state['firstNoteById'][$id] ?? (int) $noteIndex;
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>} $state
     * @param list<array<string, mixed>> $unit
     */
    private function recordCitationPositionUnit(array &$state, array $unit): void
    {
        $known = array_values(array_filter(
            $unit,
            static fn (array $info): bool => $info['id'] !== ''
        ));
        $state['previousUnit'] = [
            'single' => count($known) === 1,
            'first' => $known[0] ?? null,
        ];

        foreach ($known as $info) {
            if (!is_int($info['noteIndex'] ?? null)) {
                continue;
            }

            if (!isset($state['firstNoteById'][(string) $info['id']])) {
                $state['firstNoteById'][(string) $info['id']] = (int) $info['noteIndex'];
            }

            $state['lastNoteById'][(string) $info['id']] = [
                'index' => (int) $info['noteIndex'],
                'type' => (string) ($info['noteType'] ?? 'footnote'),
            ];
        }
    }

    /**
     * @return array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string, noteIndex:int|null, noteType:string}
     */
    private function citationPositionInfo(AstNode $citation, ?array $noteContext = null): array
    {
        $id = (string) $citation->attr('id', '');
        if ($id !== '' && !isset($this->itemsById[$id])) {
            $id = '';
        } elseif ($id !== '') {
            $id = $this->canonicalCitationId($id);
        }

        $locator = $this->citationLocatorParts($citation);
        $noteContext = $this->citationExplicitNoteContext($citation) ?? $noteContext;

        return [
            'id' => $id,
            'locatorLabel' => $locator['label'],
            'locatorValue' => $locator['value'],
            'locatorKey' => $locator['label'] . "\n" . $locator['value'],
            'noteIndex' => is_array($noteContext) ? (int) ($noteContext['index'] ?? 0) : null,
            'noteType' => is_array($noteContext) ? (string) ($noteContext['type'] ?? 'footnote') : '',
        ];
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>} $state
     * @return array{index:int, type:string}
     */
    private function citationNoteContext(AstNode $note, array &$state): array
    {
        $explicit = $this->explicitNoteIndexFromNode($note);
        if ($explicit !== null) {
            $state['noteCounter'] = max((int) ($state['noteCounter'] ?? 0), $explicit);

            return [
                'index' => $explicit,
                'type' => $this->normalizedNoteType((string) $note->attr('sourceType', $note->attr('noteType', 'footnote'))),
            ];
        }

        $state['noteCounter'] = (int) ($state['noteCounter'] ?? 0) + 1;

        return [
            'index' => $state['noteCounter'],
            'type' => $this->normalizedNoteType((string) $note->attr('sourceType', $note->attr('noteType', 'footnote'))),
        ];
    }

    /**
     * @return array{index:int, type:string}|null
     */
    private function citationExplicitNoteContext(AstNode $citation): ?array
    {
        $index = $this->explicitNoteIndexFromNode($citation);
        if ($index === null) {
            return null;
        }

        return [
            'index' => $index,
            'type' => $this->normalizedNoteType((string) $citation->attr('cslNoteType', $citation->attr('noteType', $citation->attr('sourceType', 'footnote')))),
        ];
    }

    private function explicitNoteIndexFromNode(AstNode $node): ?int
    {
        foreach (['cslNoteIndex', 'noteIndex', 'noteNumber'] as $attribute) {
            $value = $node->attr($attribute);
            if (is_int($value) && $value >= 1) {
                return $value;
            }

            if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1 && (int) $value >= 1) {
                return (int) $value;
            }
        }

        return null;
    }

    private function normalizedNoteType(string $type): string
    {
        $type = strtolower(trim($type));

        return in_array($type, ['footnote', 'endnote'], true) ? $type : 'footnote';
    }

    /**
     * @param array<string, mixed> $info
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, lastNoteById:array<string, array{index:int, type:string}>} $state
     * @param array<string, mixed>|null $previousInUnit
     * @return array{position:string, tests:list<string>}
     */
    private function citationPositionForInfo(array $info, array $state, ?array $previousInUnit, bool $firstInUnit): array
    {
        if ($info['id'] === '' || !isset($state['seenIds'][$info['id']])) {
            return ['position' => 'first', 'tests' => ['first']];
        }

        $preceding = null;
        if (is_array($previousInUnit) && $previousInUnit['id'] === $info['id']) {
            $preceding = $previousInUnit;
        } elseif ($firstInUnit) {
            $previousUnit = $state['previousUnit'] ?? null;
            if (
                is_array($previousUnit)
                && ($previousUnit['single'] ?? false) === true
                && is_array($previousUnit['first'] ?? null)
                && $previousUnit['first']['id'] === $info['id']
            ) {
                $preceding = $previousUnit['first'];
            }
        }

        if ($preceding === null) {
            return $this->withNearNotePositionTest(['position' => 'subsequent', 'tests' => ['subsequent']], $info, $state);
        }

        $precedingHasLocator = $preceding['locatorValue'] !== '';
        $currentHasLocator = $info['locatorValue'] !== '';
        if (!$precedingHasLocator) {
            return $this->withNearNotePositionTest($currentHasLocator
                ? ['position' => 'ibid-with-locator', 'tests' => ['subsequent', 'ibid', 'ibid-with-locator']]
                : ['position' => 'ibid', 'tests' => ['subsequent', 'ibid']], $info, $state);
        }

        if (!$currentHasLocator) {
            return $this->withNearNotePositionTest(['position' => 'subsequent', 'tests' => ['subsequent']], $info, $state);
        }

        if ($preceding['locatorKey'] === $info['locatorKey']) {
            return $this->withNearNotePositionTest(['position' => 'ibid', 'tests' => ['subsequent', 'ibid']], $info, $state);
        }

        return $this->withNearNotePositionTest(['position' => 'ibid-with-locator', 'tests' => ['subsequent', 'ibid', 'ibid-with-locator']], $info, $state);
    }

    /**
     * @param array{position:string, tests:list<string>} $position
     * @param array<string, mixed> $info
     * @param array{lastNoteById:array<string, array{index:int, type:string}>} $state
     * @return array{position:string, tests:list<string>}
     */
    private function withNearNotePositionTest(array $position, array $info, array $state): array
    {
        if (!$this->citationInfoIsNearNote($info, $state)) {
            return $position;
        }

        if (!in_array('subsequent', $position['tests'], true)) {
            $position['tests'][] = 'subsequent';
        }
        if (!in_array('near-note', $position['tests'], true)) {
            $position['tests'][] = 'near-note';
        }

        return $position;
    }

    /**
     * @param array<string, mixed> $info
     * @param array{lastNoteById:array<string, array{index:int, type:string}>} $state
     */
    private function citationInfoIsNearNote(array $info, array $state): bool
    {
        $id = (string) ($info['id'] ?? '');
        if ($id === '' || !is_int($info['noteIndex'] ?? null)) {
            return false;
        }

        $previous = $state['lastNoteById'][$id] ?? null;
        if (!is_array($previous)) {
            return false;
        }

        $currentIndex = (int) $info['noteIndex'];
        $previousIndex = (int) ($previous['index'] ?? 0);
        if ($previousIndex < 1 || $currentIndex < $previousIndex) {
            return false;
        }

        if ((string) ($previous['type'] ?? 'footnote') !== (string) ($info['noteType'] ?? 'footnote')) {
            return false;
        }

        $options = $this->style->citationOptions();
        $nearNoteDistance = max(0, (int) ($options['nearNoteDistance'] ?? 5));

        return ($currentIndex - $previousIndex) <= $nearNoteDistance;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function ensureClusterCitationPositions(array $citations): array
    {
        if ($citations === []) {
            return [];
        }

        $hasPositions = true;
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            if ((string) $citation->attr('cslPosition', '') === '') {
                $hasPositions = false;
            }
        }

        if ($hasPositions) {
            return $citations;
        }

        $state = $this->emptyCitationPositionState();
        $annotated = [];
        $previousInUnit = null;
        foreach ($citations as $citation) {
            [$node, $info] = $this->annotateCitationPosition($citation, $state, $previousInUnit, $annotated === [], null);
            $annotated[] = $node;
            $previousInUnit = $info;
        }

        return $annotated;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationYearSuffixesForCluster(array $citations): array
    {
        if (!$this->style->citationOptions()['disambiguateAddYearSuffix']) {
            return $citations;
        }

        $ids = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            if ($id !== '' && isset($this->itemsById[$id])) {
                $canonicalId = $this->canonicalCitationId($id);
                if (!in_array($canonicalId, $ids, true)) {
                    $ids[] = $canonicalId;
                }
            }
        }

        $yearSuffixes = $this->yearSuffixesForIds(
            $ids,
            $this->nameDisambiguationCountsForCitationList($citations),
            $this->givenNameDisambiguationModesForCitationList($citations)
        );
        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslYearSuffix', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslYearSuffix' => $yearSuffixes[$canonicalId] ?? '',
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationNameDisambiguationForCluster(array $citations): array
    {
        if (($this->style->citationOptions()['disambiguateAddNames'] ?? false) !== true) {
            return $citations;
        }

        $ids = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            if ($id !== '' && isset($this->itemsById[$id])) {
                $canonicalId = $this->canonicalCitationId($id);
                if (!in_array($canonicalId, $ids, true)) {
                    $ids[] = $canonicalId;
                }
            }
        }

        return $this->annotateCitationListNameDisambiguation(
            $citations,
            $this->nameDisambiguationCountsForIds($ids, $this->givenNameDisambiguationModesForCitationList($citations))
        );
    }

    /**
     * @param list<AstNode> $citations
     * @return array<string, string>
     */
    private function givenNameDisambiguationModesForCitationList(array $citations): array
    {
        $ids = [];
        $modes = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return [];
            }

            $id = (string) $citation->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                continue;
            }

            $canonicalId = $this->canonicalCitationId($id);
            if (!in_array($canonicalId, $ids, true)) {
                $ids[] = $canonicalId;
            }

            $mode = strtolower(trim((string) $citation->attr('cslGivenNameDisambiguation', '')));
            if (in_array($mode, ['initial', 'full'], true)) {
                $modes[$canonicalId] = $mode;
            }
        }

        foreach ($this->givenNameDisambiguationModesForIds($ids) as $id => $mode) {
            $modes[$id] = $modes[$id] ?? $mode;
        }

        return $modes;
    }

    /**
     * @param list<AstNode> $citations
     * @return array<string, int>
     */
    private function nameDisambiguationCountsForCitationList(array $citations): array
    {
        $ids = [];
        $counts = [];
        $givenModes = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return [];
            }

            $id = (string) $citation->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                continue;
            }

            $canonicalId = $this->canonicalCitationId($id);
            if (!in_array($canonicalId, $ids, true)) {
                $ids[] = $canonicalId;
            }

            $mode = strtolower(trim((string) $citation->attr('cslGivenNameDisambiguation', '')));
            if (in_array($mode, ['initial', 'full'], true)) {
                $givenModes[$canonicalId] = $mode;
            }

            $count = $citation->attr('cslDisambiguateNameCount');
            if (is_int($count) && $count > 1) {
                $counts[$canonicalId] = $count;
            }
        }

        foreach ($this->nameDisambiguationCountsForIds($ids, $givenModes) as $id => $count) {
            $counts[$id] = $counts[$id] ?? $count;
        }

        return $counts;
    }

    /**
     * @param list<AstNode> $citations
     * @param array<string, int> $counts
     * @return list<AstNode>
     */
    private function annotateCitationListNameDisambiguation(array $citations, array $counts): array
    {
        if ($counts === []) {
            return $citations;
        }

        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslDisambiguateNameCount', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $count = $counts[$canonicalId] ?? 0;
            if ($count < 2) {
                $annotated[] = $citation;
                continue;
            }

            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslDisambiguateNameCount' => $count,
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param array<string, int> $counts
     */
    private function annotateCitationNameDisambiguation(AstNode $node, array $counts): AstNode
    {
        if ($node->type === 'citation') {
            if (array_key_exists('cslDisambiguateNameCount', $node->attrs)) {
                return $node;
            }

            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $count = $counts[$this->canonicalCitationId($id)] ?? 0;
            if ($count < 2) {
                return $node;
            }

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslDisambiguateNameCount' => $count,
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationNameDisambiguation($child, $counts);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationGivenNameDisambiguationForCluster(array $citations): array
    {
        if (!$this->style->citationOptions()['disambiguateAddGivenName']) {
            return $citations;
        }

        $ids = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            if ($id !== '' && isset($this->itemsById[$id])) {
                $canonicalId = $this->canonicalCitationId($id);
                if (!in_array($canonicalId, $ids, true)) {
                    $ids[] = $canonicalId;
                }
            }
        }

        $modes = $this->givenNameDisambiguationModesForIds($ids);
        if ($modes === []) {
            return $citations;
        }

        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslGivenNameDisambiguation', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $mode = $modes[$canonicalId] ?? '';
            if ($mode === '') {
                $annotated[] = $citation;
                continue;
            }

            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslGivenNameDisambiguation' => $mode,
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param array<string, string> $modes
     */
    private function annotateCitationGivenNameDisambiguation(AstNode $node, array $modes): AstNode
    {
        if ($node->type === 'citation') {
            if (array_key_exists('cslGivenNameDisambiguation', $node->attrs)) {
                return $node;
            }

            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $mode = $modes[$this->canonicalCitationId($id)] ?? '';
            if ($mode === '') {
                return $node;
            }

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslGivenNameDisambiguation' => $mode,
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationGivenNameDisambiguation($child, $modes);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationDisambiguationForCluster(array $citations): array
    {
        if ($citations === []) {
            return [];
        }

        $ids = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            if ($id !== '' && isset($this->itemsById[$id])) {
                $canonicalId = $this->canonicalCitationId($id);
                if (!in_array($canonicalId, $ids, true)) {
                    $ids[] = $canonicalId;
                }
            }
        }

        $givenModes = $this->givenNameDisambiguationModesForCitationList($citations);
        $nameCounts = $this->nameDisambiguationCountsForCitationList($citations);
        $disambiguatingIds = $this->disambiguatingCitationIdsForIds($ids, $nameCounts, $givenModes);
        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslDisambiguate', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $needsDisambiguation = ($disambiguatingIds[$canonicalId] ?? false)
                || trim((string) $citation->attr('cslYearSuffix', '')) !== '';
            if (!$needsDisambiguation) {
                $annotated[] = $citation;
                continue;
            }

            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslDisambiguate' => true,
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param array<string, string> $yearSuffixes
     */
    private function annotateCitationYearSuffixes(AstNode $node, array $yearSuffixes): AstNode
    {
        if (!$this->style->citationOptions()['disambiguateAddYearSuffix']) {
            return $node;
        }

        if ($node->type === 'citation') {
            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $canonicalId = $this->canonicalCitationId($id);

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslYearSuffix' => $yearSuffixes[$canonicalId] ?? '',
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationYearSuffixes($child, $yearSuffixes);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param array<string, bool> $disambiguatingIds
     */
    private function annotateCitationDisambiguation(AstNode $node, array $disambiguatingIds): AstNode
    {
        if ($node->type === 'citation') {
            if (array_key_exists('cslDisambiguate', $node->attrs)) {
                return $node;
            }

            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $canonicalId = $this->canonicalCitationId($id);
            $needsDisambiguation = ($disambiguatingIds[$canonicalId] ?? false)
                || trim((string) $node->attr('cslYearSuffix', '')) !== '';
            if (!$needsDisambiguation) {
                return $node;
            }

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslDisambiguate' => true,
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationDisambiguation($child, $disambiguatingIds);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param array<string, string> $citationNumbers
     */
    private function annotateCitationNumbers(AstNode $node, array $citationNumbers): AstNode
    {
        if ($node->type === 'citation') {
            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $canonicalId = $this->canonicalCitationId($id);

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslCitationNumber' => $citationNumbers[$canonicalId] ?? '',
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationNumbers($child, $citationNumbers);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationNumbersForCluster(array $citations): array
    {
        if ($citations === []) {
            return [];
        }

        $ids = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            if ($id !== '' && isset($this->itemsById[$id])) {
                $canonicalId = $this->canonicalCitationId($id);
                if (!in_array($canonicalId, $ids, true)) {
                    $ids[] = $canonicalId;
                }
            }
        }

        $numbers = $this->citationNumbersForIds($this->sortBibliographyIds($ids));
        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslCitationNumber', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslCitationNumber' => $numbers[$canonicalId] ?? '',
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function citationNumbersForIds(array $ids): array
    {
        $numbers = [];
        $number = 1;
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$canonicalId]) || array_key_exists($canonicalId, $numbers)) {
                continue;
            }

            $numbers[$canonicalId] = (string) $number;
            $number++;
        }

        return $numbers;
    }

    private function citationNumberForId(string $id): string
    {
        $canonicalId = $this->canonicalCitationId($id);
        $numbers = $this->citationNumbersForIds($this->sortBibliographyIds($this->primaryIds));

        return $numbers[$canonicalId] ?? '';
    }

    private function primaryCitationNumberForId(string $id): string
    {
        $canonicalId = $this->canonicalCitationId($id);
        foreach ($this->primaryIds as $index => $primaryId) {
            if ($this->canonicalCitationId($primaryId) === $canonicalId) {
                return (string) ($index + 1);
            }
        }

        return '';
    }

    private function mapNode(AstNode $node): AstNode
    {
        if ($node->type === 'citation') {
            return $this->normalizeCitation($node);
        }

        if ($node->type === 'citation_group') {
            return $this->normalizeCitationGroup($node);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $mapped = $this->mapNode($child);
            $children[] = $mapped;
            $changed = $changed || $mapped !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    private function coalesceMarkdownCitationClusters(AstNode $node): AstNode
    {
        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $mapped = $this->coalesceMarkdownCitationClusters($child);
            $children[] = $mapped;
            $changed = $changed || $mapped !== $child;
        }

        $coalesced = $this->coalesceMarkdownCitationClusterChildren($children);
        $changed = $changed || $coalesced !== $children;

        return $changed ? new AstNode($node->type, $node->attrs, $coalesced) : $node;
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function coalesceMarkdownCitationClusterChildren(array $children): array
    {
        $coalesced = [];
        $count = count($children);
        for ($index = 0; $index < $count; $index++) {
            $node = $children[$index];
            if ($node->type !== 'text') {
                $coalesced[] = $node;
                continue;
            }

            $text = (string) $node->attr('text', '');
            if (!str_ends_with($text, '[')) {
                $coalesced[] = $node;
                continue;
            }

            $cluster = $this->readMarkdownCitationCluster($children, $index + 1);
            if ($cluster === null) {
                $coalesced[] = $node;
                continue;
            }

            $before = substr($text, 0, -1);
            if ($before !== '') {
                $coalesced[] = new AstNode('text', ['text' => $before]);
            }
            $coalesced[] = new AstNode('citation_group', ['text' => $cluster['source']], $cluster['citations']);
            if ($cluster['after'] !== '') {
                $coalesced[] = new AstNode('text', ['text' => $cluster['after']]);
            }
            $index = $cluster['end'];
        }

        return $coalesced;
    }

    /**
     * @param list<AstNode> $children
     * @return array{end:int, source:string, after:string, citations:list<AstNode>}|null
     */
    private function readMarkdownCitationCluster(array $children, int $offset): ?array
    {
        $citations = [];
        $source = '[';
        $index = $offset;
        $count = count($children);

        while ($index < $count) {
            $citation = $children[$index] ?? null;
            if (!$citation instanceof AstNode || $citation->type !== 'citation' || (string) $citation->attr('mode', '') !== 'author_in_text') {
                return null;
            }

            $citations[] = $this->markdownCitationClusterEntry($citation);
            $source .= (string) $citation->attr('text', $this->plainInlineText($citation->children));
            $index++;

            $separator = $children[$index] ?? null;
            if (!$separator instanceof AstNode || $separator->type !== 'text') {
                return null;
            }

            $separatorText = (string) $separator->attr('text', '');
            if (str_starts_with($separatorText, ']')) {
                return [
                    'end' => $index,
                    'source' => $source . ']',
                    'after' => substr($separatorText, 1),
                    'citations' => $citations,
                ];
            }

            if (preg_match('/^\s*;\s*$/', $separatorText) !== 1) {
                return null;
            }

            $source .= $separatorText;
            $index++;
        }

        return null;
    }

    private function markdownCitationClusterEntry(AstNode $citation): AstNode
    {
        return new AstNode(
            'citation',
            [
                ...$citation->attrs,
                'mode' => 'normal',
            ],
            $citation->children
        );
    }

    /**
     * @param list<string> $ids
     */
    private function collectCitationIds(AstNode $node, array &$ids): void
    {
        if ($node->type === 'citation') {
            $id = (string) $node->attr('id', '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        foreach ($node->children as $child) {
            $this->collectCitationIds($child, $ids);
        }
    }

    /**
     * @param list<array{id:string, source:string, rawLocator:string, rawLocatorLabel:string, locatorLabel:string, locatorValue:string, reason:string, severity:string}> $diagnostics
     */
    private function collectCitationLocatorDiagnostics(AstNode $node, array &$diagnostics): void
    {
        if ($node->type === 'citation') {
            foreach ($this->citationLocatorDiagnosticsForCitation($node) as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
        }

        foreach ($node->children as $child) {
            $this->collectCitationLocatorDiagnostics($child, $diagnostics);
        }
    }

    /**
     * @return list<array{id:string, source:string, rawLocator:string, rawLocatorLabel:string, locatorLabel:string, locatorValue:string, reason:string, severity:string}>
     */
    private function citationLocatorDiagnosticsForCitation(AstNode $citation): array
    {
        $rawLocator = $this->inlineValue($citation->attr('locator', ''));
        $explicitValue = $this->inlineValue($citation->attr('locatorValue', ''));
        $rawSuffix = $this->inlineValue($citation->attr('suffix', ''));
        $diagnosticRawLocator = ($rawLocator !== '' || $explicitValue !== '') ? $rawLocator : $rawSuffix;
        $rawLabel = trim((string) $citation->attr('locatorLabel', ''));
        $parts = $this->citationLocatorParts($citation);
        if ($parts['value'] === '') {
            if ($rawLabel === '') {
                return [];
            }

            $normalizedLabel = $this->normalizedLocatorLabel($rawLabel);
            $diagnostics = [[
                'id' => (string) $citation->attr('id', ''),
                'source' => $this->sourceCitationText($citation),
                'rawLocator' => $diagnosticRawLocator,
                'rawLocatorLabel' => $rawLabel,
                'locatorLabel' => $normalizedLabel,
                'locatorValue' => '',
                'reason' => 'citation-locator-label-without-value',
                'severity' => 'warning',
            ]];

            if (!$this->supportedCitationLocatorLabel($normalizedLabel)) {
                $diagnostics[] = [
                    'id' => (string) $citation->attr('id', ''),
                    'source' => $this->sourceCitationText($citation),
                    'rawLocator' => $diagnosticRawLocator,
                    'rawLocatorLabel' => $rawLabel,
                    'locatorLabel' => $normalizedLabel,
                    'locatorValue' => '',
                    'reason' => 'citation-locator-unsupported-label',
                    'severity' => 'warning',
                ];
            }

            return $diagnostics;
        }

        $base = [
            'id' => (string) $citation->attr('id', ''),
            'source' => $this->sourceCitationText($citation),
            'rawLocator' => $diagnosticRawLocator,
            'rawLocatorLabel' => $rawLabel,
            'locatorLabel' => $parts['label'],
            'locatorValue' => $parts['value'],
        ];

        $diagnostics = [];
        if ($rawLocator === '' && $explicitValue === '' && $rawSuffix !== '') {
            $diagnostics[] = [
                ...$base,
                'reason' => 'citation-locator-suffix-inferred',
                'severity' => 'info',
            ];
        }

        if (!$this->supportedCitationLocatorLabel($parts['label'])) {
            $diagnostics[] = [
                ...$base,
                'reason' => 'citation-locator-unsupported-label',
                'severity' => 'warning',
            ];
        }

        if ($rawLocator === '' && $explicitValue !== '' && $rawLabel === '') {
            $diagnostics[] = [
                ...$base,
                'reason' => 'citation-locator-explicit-value-defaulted-page',
                'severity' => 'info',
            ];
        }

        if (
            $rawLabel !== ''
            && $explicitValue === ''
            && $this->normalizedLocatorLabel($rawLabel) !== $parts['label']
        ) {
            $diagnostics[] = [
                ...$base,
                'reason' => 'citation-locator-label-without-explicit-value',
                'severity' => 'warning',
            ];
        }

        if (
            $rawLabel !== ''
            && $this->normalizedLocatorLabel($rawLabel) !== $parts['label']
            && !$this->supportedCitationLocatorLabel($rawLabel)
        ) {
            $diagnostics[] = [
                ...$base,
                'reason' => 'citation-locator-unsupported-label',
                'severity' => 'warning',
            ];
        }

        if (
            $diagnosticRawLocator !== ''
            && $parts['label'] === 'page'
            && !$this->citationLocatorTextHasKnownLabel($diagnosticRawLocator)
        ) {
            $diagnostics[] = [
                ...$base,
                'reason' => 'citation-locator-unlabeled-page-fallback',
                'severity' => 'info',
            ];
        }

        return $diagnostics;
    }

    private function citationLocatorTextHasKnownLabel(string $locator): bool
    {
        $locator = trim(preg_replace('/\s+/u', ' ', $locator) ?? $locator);
        if ($locator === '') {
            return false;
        }

        $parts = $this->inferCitationLocatorParts($locator);

        return !($parts['label'] === 'page' && $parts['value'] === $locator);
    }

    private function supportedCitationLocatorLabel(string $label): bool
    {
        return in_array($this->normalizedLocatorLabel($label), [
            'appendix',
            'article-locator',
            'book',
            'canon',
            'chapter',
            'column',
            'elocation',
            'equation',
            'figure',
            'folio',
            'issue',
            'line',
            'note',
            'number',
            'opus',
            'page',
            'paragraph',
            'part',
            'rule',
            'section',
            'sub-verbo',
            'supplement',
            'table',
            'timestamp',
            'title',
            'verse',
            'volume',
        ], true);
    }

    /**
     * @return list<string>
     */
    private function uniqueKnownCitationIds(AstNode $document): array
    {
        $ids = [];
        foreach ($this->citationIds($document) as $id) {
            if (!isset($this->itemsById[$id])) {
                continue;
            }

            $canonicalId = $this->canonicalCitationId($id);
            if (!in_array($canonicalId, $ids, true)) {
                $ids[] = $canonicalId;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, string>
     */
    private function firstReferenceNoteNumbersForDocument(AstNode $document): array
    {
        $numbers = [];
        $this->collectFirstReferenceNoteNumbers($document, $numbers);

        return $numbers;
    }

    /**
     * @param array<string, string> $numbers
     */
    private function collectFirstReferenceNoteNumbers(AstNode $node, array &$numbers): void
    {
        if ($node->type === 'citation') {
            $id = (string) $node->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            if ($id !== '' && isset($this->itemsById[$canonicalId])) {
                foreach (['cslFirstReferenceNoteNumber', 'firstReferenceNoteNumber'] as $attribute) {
                    $number = self::positiveIntegerString($node->attr($attribute, ''));
                    if ($number === '') {
                        continue;
                    }

                    if (!isset($numbers[$canonicalId]) || (int) $number < (int) $numbers[$canonicalId]) {
                        $numbers[$canonicalId] = $number;
                    }

                    break;
                }
            }
        }

        foreach ($node->children as $child) {
            $this->collectFirstReferenceNoteNumbers($child, $numbers);
        }
    }

    private function canonicalCitationId(string $id): string
    {
        return $this->canonicalIdsById[$id] ?? $id;
    }

    private function itemSkipsBibliography(string $id): bool
    {
        $canonicalId = $this->canonicalCitationId($id);
        $item = $this->itemsById[$canonicalId] ?? null;

        return is_array($item) && ($item['biblatexSkipBibliography'] ?? false) === true;
    }

    /**
     * @param list<string> $ids
     * @return array<string, int>
     */
    private function nameDisambiguationCountsForIds(array $ids, array $givenNameModes = []): array
    {
        if (($this->style->citationOptions()['disambiguateAddNames'] ?? false) !== true) {
            return [];
        }

        $groups = [];
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$canonicalId])) {
                continue;
            }

            $item = $this->itemsById[$canonicalId];
            if (!$this->citationAuthorLabelUsesEtAl($item)) {
                continue;
            }

            $key = $this->yearSuffixDisambiguationKey($item, 0, $givenNameModes[$canonicalId] ?? '');
            $groups[$key][] = $canonicalId;
        }

        $counts = [];
        foreach ($groups as $groupIds) {
            $groupIds = array_values(array_unique($groupIds));
            if (count($groupIds) < 2) {
                continue;
            }

            $baseCount = 1;
            $maximumCount = 1;
            foreach ($groupIds as $id) {
                $baseCount = max($baseCount, $this->citationAuthorLabelVisibleNameCount($this->itemsById[$id]));
                $maximumCount = max($maximumCount, count($this->citationAuthorLabelNames($this->itemsById[$id])));
            }

            $baseLabels = [];
            foreach ($groupIds as $id) {
                $baseLabels[$id] = $this->citationAuthorLabelWithDisambiguationContext($this->itemsById[$id], 0, $givenNameModes[$id] ?? '');
            }

            $bestCandidate = 0;
            $bestLabels = [];
            $bestDistinctCount = $this->renderedLabelDistinctCount($baseLabels);
            for ($candidate = $baseCount + 1; $candidate <= $maximumCount; $candidate++) {
                $labels = [];
                foreach ($groupIds as $id) {
                    $labels[$id] = $this->citationAuthorLabelWithNameDisambiguationCount($this->itemsById[$id], $candidate, $givenNameModes[$id] ?? '');
                }

                if ($this->renderedLabelsAreUnique($labels)) {
                    $this->addNameDisambiguationCountsForChangedLabels($counts, $groupIds, $labels, $candidate, $baseLabels);
                    $bestCandidate = 0;
                    break;
                }

                $distinctCount = $this->renderedLabelDistinctCount($labels);
                if ($distinctCount > $bestDistinctCount) {
                    $bestCandidate = $candidate;
                    $bestLabels = $labels;
                    $bestDistinctCount = $distinctCount;
                }
            }

            if ($bestCandidate > 0) {
                $this->addNameDisambiguationCountsForChangedLabels($counts, $groupIds, $bestLabels, $bestCandidate, $baseLabels);
            }
        }

        return $counts;
    }

    /**
     * @param array<string, int> $counts
     * @param list<string> $groupIds
     * @param array<string, string> $labels
     * @param array<string, string> $baseLabels
     */
    private function addNameDisambiguationCountsForChangedLabels(array &$counts, array $groupIds, array $labels, int $candidate, array $baseLabels = []): void
    {
        foreach ($groupIds as $id) {
            $baseLabel = $baseLabels[$id] ?? $this->citationAuthorLabel($this->itemsById[$id]);
            if (($labels[$id] ?? '') !== $baseLabel) {
                $counts[$id] = $candidate;
            }
        }
    }

    /**
     * @param array<string, string> $labels
     */
    private function renderedLabelDistinctCount(array $labels): int
    {
        $normalized = [];
        foreach ($labels as $label) {
            $key = $this->normalizedRenderedNameKey($label);
            if (!in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return count($normalized);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelUsesEtAl(array $item): bool
    {
        $names = $this->citationAuthorLabelNames($item);
        $nameCount = count($names);
        if ($nameCount < 2) {
            return false;
        }

        $options = $this->style->citationNameRendering();
        $etAlMin = $options['etAlMin'] ?? null;
        if (!is_int($etAlMin) || $nameCount < $etAlMin) {
            return false;
        }

        return $this->citationAuthorLabelVisibleNameCount($item) < $nameCount;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelVisibleNameCount(array $item): int
    {
        $nameCount = count($this->citationAuthorLabelNames($item));
        if ($nameCount === 0) {
            return 0;
        }

        $options = $this->style->citationNameRendering();
        $etAlMin = $options['etAlMin'] ?? null;
        if (!is_int($etAlMin) || $nameCount < $etAlMin) {
            return $nameCount;
        }

        $etAlUseFirst = $options['etAlUseFirst'] ?? 1;
        if (!is_int($etAlUseFirst)) {
            $etAlUseFirst = 1;
        }

        return max(1, min($etAlUseFirst, $nameCount));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelWithNameDisambiguationCount(array $item, int $count, string $givenNameMode = ''): string
    {
        return $this->citationAuthorLabelWithDisambiguationContext($item, $count, $givenNameMode);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelWithDisambiguationContext(array $item, int $nameCount = 0, string $givenNameMode = ''): string
    {
        $attrs = [];
        if ($nameCount > 1) {
            $attrs['cslDisambiguateNameCount'] = $nameCount;
        }
        $givenNameMode = strtolower(trim($givenNameMode));
        if (in_array($givenNameMode, ['initial', 'full'], true)) {
            $attrs['cslGivenNameDisambiguation'] = $givenNameMode;
        }

        return $this->citationAuthorLabel(
            $item,
            $attrs === [] ? null : new AstNode('citation', $attrs)
        );
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function givenNameDisambiguationModesForIds(array $ids): array
    {
        $options = $this->style->citationOptions();
        if (($options['disambiguateAddGivenName'] ?? false) !== true) {
            return [];
        }

        $rule = (string) ($options['givenNameDisambiguationRule'] ?? 'by-cite');
        $groups = [];
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$canonicalId])) {
                continue;
            }

            $item = $this->itemsById[$canonicalId];
            $key = $rule === 'by-cite'
                ? $this->yearSuffixDisambiguationKey($item)
                : $this->citationAuthorLabel($item);
            $groups[$key][] = $canonicalId;
        }

        $modes = [];
        foreach ($groups as $groupIds) {
            $groupIds = array_values(array_unique($groupIds));
            if (count($groupIds) < 2) {
                continue;
            }

            $candidateModes = str_ends_with($rule, '-with-initials')
                ? ['initial']
                : ['initial', 'full'];
            $baseLabels = [];
            foreach ($groupIds as $id) {
                $baseLabels[$id] = $this->citationAuthorLabel($this->itemsById[$id]);
            }
            $bestMode = '';
            $bestLabels = [];
            $bestDistinctCount = $this->renderedLabelDistinctCount($baseLabels);
            foreach ($candidateModes as $mode) {
                $labels = [];
                foreach ($groupIds as $id) {
                    $labels[$id] = $this->citationAuthorLabelWithGivenNameDisambiguation($this->itemsById[$id], $mode);
                }

                if ($this->renderedLabelsAreUnique($labels)) {
                    foreach ($groupIds as $id) {
                        if ($labels[$id] !== $baseLabels[$id]) {
                            $modes[$id] = $mode;
                        }
                    }
                    $bestMode = '';
                    break;
                }

                $distinctCount = $this->renderedLabelDistinctCount($labels);
                if ($distinctCount > $bestDistinctCount) {
                    $bestMode = $mode;
                    $bestLabels = $labels;
                    $bestDistinctCount = $distinctCount;
                }
            }

            if ($bestMode !== '') {
                foreach ($groupIds as $id) {
                    if (($bestLabels[$id] ?? '') !== $baseLabels[$id]) {
                        $modes[$id] = $bestMode;
                    }
                }
            }
        }

        return $modes;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelWithGivenNameDisambiguation(array $item, string $mode): string
    {
        return $this->citationAuthorLabel(
            $item,
            new AstNode('citation', ['cslGivenNameDisambiguation' => $mode])
        );
    }

    /**
     * @param array<string, string> $labels
     */
    private function renderedLabelsAreUnique(array $labels): bool
    {
        $normalized = array_map(
            fn (string $label): string => $this->normalizedRenderedNameKey($label),
            array_values($labels)
        );

        return count(array_unique($normalized)) === count($normalized);
    }

    /**
     * @param list<string> $ids
     * @return array<string, bool>
     */
    private function disambiguatingCitationIdsForIds(array $ids, array $nameCounts = [], array $givenNameModes = []): array
    {
        $groups = [];
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$canonicalId])) {
                continue;
            }

            $groups[$this->yearSuffixDisambiguationKey(
                $this->itemsById[$canonicalId],
                $nameCounts[$canonicalId] ?? 0,
                $givenNameModes[$canonicalId] ?? ''
            )][] = $canonicalId;
        }

        $disambiguating = [];
        foreach ($groups as $groupIds) {
            $groupIds = array_values(array_unique($groupIds));
            if (count($groupIds) < 2) {
                continue;
            }

            foreach ($groupIds as $id) {
                $disambiguating[$id] = true;
            }
        }

        return $disambiguating;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function yearSuffixesForIds(array $ids, ?array $nameCounts = null, array $givenNameModes = []): array
    {
        if (!$this->style->citationOptions()['disambiguateAddYearSuffix']) {
            return [];
        }

        if ($givenNameModes === []) {
            $givenNameModes = $this->givenNameDisambiguationModesForIds($ids);
        }
        $nameCounts = $nameCounts ?? $this->nameDisambiguationCountsForIds($ids, $givenNameModes);
        $suffixes = [];
        $groups = [];
        foreach ($ids as $id) {
            $id = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$id]) || array_key_exists($id, $suffixes)) {
                continue;
            }

            $item = $this->itemsById[$id];
            $suffixes[$id] = '';
            $groups[$this->yearSuffixDisambiguationKey($item, $nameCounts[$id] ?? 0, $givenNameModes[$id] ?? '')][] = $id;
        }

        foreach ($groups as $groupIds) {
            if (count($groupIds) < 2) {
                continue;
            }

            foreach (array_values($groupIds) as $index => $id) {
                $suffixes[$id] = $this->yearSuffixForIndex($index);
            }
        }

        return $suffixes;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function yearSuffixDisambiguationKey(array $item, int $nameCount = 0, string $givenNameMode = ''): string
    {
        $withoutSuffix = $this->itemWithYearSuffix($item, '', false);
        $renderedKey = $this->renderedCitationDisambiguationKey($withoutSuffix, $nameCount, $givenNameMode);
        if ($renderedKey !== '') {
            return $renderedKey;
        }

        $attrs = [];
        if ($nameCount > 1) {
            $attrs['cslDisambiguateNameCount'] = $nameCount;
        }
        $givenNameMode = strtolower(trim($givenNameMode));
        if (in_array($givenNameMode, ['initial', 'full'], true)) {
            $attrs['cslGivenNameDisambiguation'] = $givenNameMode;
        }
        $citation = $attrs !== []
            ? new AstNode('citation', $attrs)
            : null;

        return $this->citationAuthorLabel($withoutSuffix, $citation) . "\n" . $this->citationYear($withoutSuffix);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderedCitationDisambiguationKey(array $item, int $nameCount = 0, string $givenNameMode = ''): string
    {
        $elements = $this->style->citationRenderingElements();
        if ($elements === [] || !$this->hasNonNameRenderingElement($elements)) {
            return '';
        }

        $citationAttrs = [
            'id' => (string) ($item['id'] ?? ''),
            'mode' => 'normal',
            'cslYearSuffix' => '',
            'cslDisambiguate' => false,
        ];
        if ($nameCount > 1) {
            $citationAttrs['cslDisambiguateNameCount'] = $nameCount;
        }
        $givenNameMode = strtolower(trim($givenNameMode));
        if (in_array($givenNameMode, ['initial', 'full'], true)) {
            $citationAttrs['cslGivenNameDisambiguation'] = $givenNameMode;
        }

        $citation = new AstNode('citation', $citationAttrs);
        $rendered = $this->renderRenderingElements($elements, $item, 'citation', '', $citation);

        return $this->normalizedRenderedNameKey($rendered);
    }

    private function yearSuffixForIndex(int $index): string
    {
        $suffix = '';
        do {
            $suffix = chr(ord('a') + ($index % 26)) . $suffix;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $suffix;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function itemWithYearSuffix(array $item, string $suffix, bool $preserveExisting = true): array
    {
        return [
            ...$item,
            'yearSuffix' => $suffix !== '' ? $suffix : ($preserveExisting ? (string) ($item['yearSuffix'] ?? '') : ''),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function itemWithCitationNumber(array $item, string $number): array
    {
        return [
            ...$item,
            'citationNumber' => $number,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function itemWithFirstReferenceNoteNumber(array $item, string $number): array
    {
        if ($number === '') {
            return $item;
        }

        return [
            ...$item,
            'firstReferenceNoteNumber' => $number,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function itemWithCitationContext(array $item, ?AstNode $citation): array
    {
        if (!$citation instanceof AstNode) {
            return $item;
        }

        if (array_key_exists('cslYearSuffix', $citation->attrs)) {
            $item = $this->itemWithYearSuffix($item, (string) $citation->attr('cslYearSuffix', ''), false);
        }

        if (array_key_exists('cslCitationNumber', $citation->attrs)) {
            $item = $this->itemWithCitationNumber($item, (string) $citation->attr('cslCitationNumber', ''));
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function derivedCslItemId(array $item, int $index): string
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

    /**
     * @param list<string> $ids
     * @param array<string, string> $firstReferenceNoteNumbers
     * @return list<string>
     */
    private function sortBibliographyIds(array $ids, array $firstReferenceNoteNumbers = []): array
    {
        $sortKeys = $this->style->bibliographySortKeys();
        if ($sortKeys === [] || count($ids) < 2) {
            return $ids;
        }

        $entries = [];
        foreach ($ids as $index => $id) {
            $canonicalId = $this->canonicalCitationId($id);
            $item = $this->itemsById[$canonicalId] ?? null;
            if ($item !== null) {
                $item = $this->itemWithFirstReferenceNoteNumber($item, $firstReferenceNoteNumbers[$canonicalId] ?? $firstReferenceNoteNumbers[$id] ?? '');
            }

            $entries[] = [
                'index' => $index,
                'id' => $id,
                'item' => $item,
                'fallback' => $id,
            ];
        }

        usort($entries, fn (array $left, array $right): int => $this->compareSortEntries($left, $right, $sortKeys, 'bibliography'));

        return array_map(static fn (array $entry): string => (string) $entry['id'], $entries);
    }

    /**
     * @return list<string>
     */
    private function shorthandListIds(): array
    {
        $ids = [];
        foreach ($this->primaryIds as $id) {
            $item = $this->itemsById[$id] ?? null;
            if ($item === null || trim((string) ($item['shorthand'] ?? '')) === '') {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    private function sortShorthandListIds(array $ids): array
    {
        if (count($ids) < 2) {
            return $ids;
        }

        $entries = [];
        foreach ($ids as $index => $id) {
            $entries[] = [
                'index' => $index,
                'id' => $id,
                'item' => $this->itemsById[$id],
            ];
        }

        usort($entries, fn (array $left, array $right): int => $this->compareShorthandListEntries($left, $right));

        return array_map(static fn (array $entry): string => (string) $entry['id'], $entries);
    }

    /**
     * @param array{index:int, id:string, item:array<string, mixed>} $left
     * @param array{index:int, id:string, item:array<string, mixed>} $right
     */
    private function compareShorthandListEntries(array $left, array $right): int
    {
        $leftValues = [
            $this->shorthandListSortValue($left['item']),
            $this->shorthandSortValue($left['item']),
            $this->titleSortValue($left['item']),
            (string) $left['id'],
        ];
        $rightValues = [
            $this->shorthandListSortValue($right['item']),
            $this->shorthandSortValue($right['item']),
            $this->titleSortValue($right['item']),
            (string) $right['id'],
        ];

        foreach ($leftValues as $index => $leftValue) {
            $comparison = $leftValue <=> $rightValues[$index];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left['index'] <=> $right['index'];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function shorthandListSortValue(array $item): string
    {
        $sortKey = trim((string) ($item['shorthandListSortKey'] ?? ''));
        if ($sortKey === '') {
            $sortKey = trim((string) ($item['shorthand'] ?? ''));
        }

        return $this->normalizeSortText($sortKey);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function shorthandSortValue(array $item): string
    {
        return $this->normalizeSortText((string) ($item['shorthand'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function titleSortValue(array $item): string
    {
        return $this->normalizeSortText((string) ($item['title'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function shorthandDefinitionItem(array $item): AstNode
    {
        $term = trim((string) ($item['shorthand'] ?? ''));
        $definitionText = $this->shorthandDefinitionText($item);
        $attrs = [
            'term' => $term,
            'cslId' => (string) ($item['id'] ?? ''),
        ];
        $sortKey = trim((string) ($item['shorthandListSortKey'] ?? ''));
        if ($sortKey !== '') {
            $attrs['shorthandListSortKey'] = $sortKey;
        }
        $intro = trim((string) ($item['shorthandIntro'] ?? ''));
        if ($intro !== '') {
            $attrs['shorthandIntro'] = $intro;
        }

        return new AstNode('definition_item', $attrs, [
            new AstNode('term', ['text' => $term], [
                new AstNode('text', ['text' => $term]),
            ]),
            new AstNode('definition', [], [
                new AstNode('paragraph', ['text' => $definitionText], [
                    new AstNode('text', ['text' => $definitionText]),
                ]),
            ]),
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function shorthandDefinitionText(array $item): string
    {
        $parts = [];
        $intro = trim((string) ($item['shorthandIntro'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));
        if ($intro !== '') {
            $parts[] = $this->withTerminalPunctuation($intro);
        }
        if ($title !== '' && $this->normalizeSortText($title) !== $this->normalizeSortText($intro)) {
            $parts[] = $this->withTerminalPunctuation($title);
        }
        if ($parts === []) {
            $parts[] = $this->withTerminalPunctuation((string) ($item['id'] ?? ''));
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function sortCitationCluster(array $citations): array
    {
        $sortKeys = $this->style->citationSortKeys();
        if ($sortKeys === [] || count($citations) < 2) {
            return $citations;
        }

        $entries = [];
        foreach ($citations as $index => $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            $entries[] = [
                'index' => $index,
                'node' => $citation,
                'item' => $this->itemsById[$id] ?? null,
                'fallback' => $this->sourceCitationText($citation),
            ];
        }

        usort($entries, fn (array $left, array $right): int => $this->compareSortEntries($left, $right, $sortKeys, 'citation'));

        return array_map(static fn (array $entry): AstNode => $entry['node'], $entries);
    }

    /**
     * @param array{index:int, item:array<string, mixed>|null, fallback:string} $left
     * @param array{index:int, item:array<string, mixed>|null, fallback:string} $right
     * @param list<array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool}> $sortKeys
     */
    private function compareSortEntries(array $left, array $right, array $sortKeys, string $scope): int
    {
        $leftItem = $left['item'];
        $rightItem = $right['item'];
        if ($leftItem === null || $rightItem === null) {
            if ($leftItem === null && $rightItem === null) {
                return $left['index'] <=> $right['index'];
            }

            return $leftItem === null ? 1 : -1;
        }

        foreach ($sortKeys as $key) {
            $leftValue = $this->sortValue($leftItem, $key, (string) $left['fallback'], $scope);
            $rightValue = $this->sortValue($rightItem, $key, (string) $right['fallback'], $scope);
            $comparison = $leftValue <=> $rightValue;
            if ($comparison !== 0) {
                return ($key['sort'] ?? 'ascending') === 'descending' ? -$comparison : $comparison;
            }
        }

        return $left['index'] <=> $right['index'];
    }

    /**
     * @param array<string, mixed> $item
     * @param array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool} $key
     */
    private function sortValue(array $item, array $key, string $fallback, string $scope): string
    {
        $macro = trim((string) ($key['macro'] ?? ''));
        if ($macro !== '') {
            return $this->sortMacroValue($item, $macro, $scope, $key);
        }

        $variable = self::pageExtentCountVariableAlias($this->sortVariable($key));

        return match ($variable) {
            'presort' => $this->normalizeSortText((string) ($item['presort'] ?? '')),
            'sort-key' => $this->normalizeSortText((string) ($item['sortKey'] ?? '')),
            'sort-name' => $this->normalizeSortText((string) ($item['sortName'] ?? '')),
            'sort-title' => $this->normalizeSortText((string) ($item['sortTitle'] ?? '')),
            'sort-year' => $this->sortYearSortValue($item),
            'sort-initial', 'sortinit', 'sortinitial' => $this->normalizeSortText((string) ($item['sortInitial'] ?? '')),
            'sort-initial-hash', 'sortinithash' => $this->normalizeSortText((string) ($item['sortInitialHash'] ?? '')),
            'author' => $this->normalizeSortText($this->sortNameValue($item) !== '' ? $this->sortNameValue($item) : $this->namesSortValue($item['authors'] ?? [], $item['editors'] ?? [], $key)),
            'editor' => $this->normalizeSortText($this->sortNameValue($item) !== '' ? $this->sortNameValue($item) : $this->namesSortValue($item['editors'] ?? [], [], $key)),
            'container-author' => $this->normalizeSortText($this->namesSortValue($item['containerAuthors'] ?? [], [], $key)),
            'issued', 'date' => $this->sortYearSortValue($item) !== '' ? $this->sortYearSortValue($item) : $this->issuedSortValue($item),
            'accessed', 'accessed-date', 'accesseddate', 'access-date', 'accessdate',
            'url-date', 'urldate', 'visited', 'lastchecked', 'lastaccessed' => $this->dateSortValue($item, 'accessed'),
            'available-date' => $this->dateSortValue($item, 'available-date'),
            'accepted-date', 'accepteddate', 'date-accepted', 'dateaccepted' => $this->dateSortValue($item, 'accepted-date'),
            'revised-date', 'reviseddate', 'revision-date', 'revisiondate', 'date-revised', 'daterevised' => $this->dateSortValue($item, 'revised-date'),
            'event-date' => $this->dateSortValue($item, 'event-date'),
            'original-date' => $this->dateSortValue($item, 'original-date'),
            'reprint-date' => $this->dateSortValue($item, 'reprint-date'),
            'submitted' => $this->dateSortValue($item, 'submitted'),
            'label-date', 'labeldate' => $this->dateSortValue($item, 'label-date'),
            'title' => $this->normalizeSortText($this->sortTitleValue($item) !== '' ? $this->sortTitleValue($item) : (string) $item['title']),
            'short-title' => $this->normalizeSortText($this->sortTitleValue($item) !== '' ? $this->sortTitleValue($item) : (string) $item['shortTitle']),
            'citation-label' => $this->normalizeSortText((string) $item['citationLabel']),
            'label-prefix', 'labelprefix' => $this->normalizeSortText((string) ($item['labelPrefix'] ?? '')),
            'extra-alpha', 'extraalpha' => $this->normalizeSortText((string) ($item['extraAlpha'] ?? '')),
            'shorthand' => $this->normalizeSortText((string) $item['shorthand']),
            'sort-shorthand', 'sortshorthand', 'list-shorthand', 'listshorthand', 'shorthand-list-sort-key' => $this->normalizeSortText((string) ($item['shorthandListSortKey'] ?? $item['sortShorthand'] ?? $item['shorthand'] ?? '')),
            'translated-title', 'translatedtitle', 'title-translation', 'titletranslation' => $this->normalizeSortText((string) ($item['translatedTitle'] ?? '')),
            'translated-subtitle', 'translatedsubtitle', 'title-translation-subtitle', 'titletranslationsubtitle', 'subtitle-translation', 'subtitletranslation' => $this->normalizeSortText((string) ($item['translatedSubtitle'] ?? '')),
            'reviewed-title', 'reviewedtitle', 'reviewtitle' => $this->normalizeSortText((string) ($item['reviewedTitle'] ?? '')),
            'original-title', 'originaltitle', 'origtitle' => $this->normalizeSortText((string) ($item['originalTitle'] ?? '')),
            'original-subtitle', 'originalsubtitle', 'origsubtitle' => $this->normalizeSortText((string) ($item['originalSubtitle'] ?? '')),
            'original-title-addon', 'originaltitleaddon', 'original-titleaddon', 'origtitleaddon', 'origtitle-addon' => $this->normalizeSortText((string) ($item['originalTitleAddon'] ?? '')),
            'container-title', 'containertitle', 'container', 'container-title-text', 'containertitletext' => $this->normalizeSortText((string) $item['containerTitle']),
            'collection-title', 'collectiontitle', 'collection', 'collection-title-text', 'collectiontitletext', 'series', 'series-title', 'seriestitle', 'series-title-text', 'seriestitletext' => $this->normalizeSortText((string) ($item['collectionTitle'] ?? '')),
            'collection-title-short', 'collectiontitleshort', 'shortseries', 'short-series', 'shortcollection', 'short-collection', 'series-short', 'seriesshort', 'series-title-short', 'seriestitleshort' => $this->normalizeSortText((string) ($item['collectionTitleShort'] ?? '')),
            'original-collection-title', 'originalcollectiontitle', 'origseries', 'orig-series', 'original-series', 'originalseries' => $this->normalizeSortText((string) ($item['originalCollectionTitle'] ?? '')),
            'main-title', 'maintitle', 'main-title-text', 'maintitletext' => $this->normalizeSortText((string) ($item['mainTitle'] ?? '')),
            'main-title-addon', 'maintitleaddon' => $this->normalizeSortText((string) ($item['mainTitleAddon'] ?? '')),
            'volume-title', 'volumetitle', 'volume-title-text', 'volumetitletext' => $this->normalizeSortText((string) ($item['volumeTitle'] ?? '')),
            'volume-title-short', 'volumetitleshort', 'shortvolumetitle', 'short-volume-title' => $this->normalizeSortText((string) ($item['volumeTitleShort'] ?? '')),
            'part-title', 'parttitle', 'part-title-text', 'parttitletext' => $this->normalizeSortText((string) ($item['partTitle'] ?? '')),
            'issue-title', 'issuetitle', 'issue-title-text', 'issuetitletext' => $this->normalizeSortText((string) ($item['issueTitle'] ?? '')),
            'event', 'event-title' => $this->normalizeSortText((string) $item['eventTitle']),
            'event-place' => $this->normalizeSortText((string) $item['eventPlace']),
            'publisher' => $this->normalizeSortText((string) $item['publisher']),
            'language', 'langid', 'language-id', 'languageid', 'hyphenation', 'language-list', 'languagelist' => $this->normalizeSortText($this->renderVariableValue($item, $variable, $scope)),
            'source', 'source-title', 'sourcetitle' => $this->normalizeSortText((string) ($item['source'] ?? '')),
            'status', 'publication-status', 'publicationstatus', 'pubstate',
            'keyword-list', 'keywordlist', 'category-list', 'categorylist',
            'citation-alias', 'citationalias', 'citation-aliases', 'citationaliases',
            'citation-alias-summary', 'citation-aliases-summary', 'citationaliassummary', 'citationaliasessummary' => $this->normalizeSortText($this->renderVariableValue($item, $variable, $scope)),
            'archive', 'archive-place', 'archiveplace', 'eprintclass', 'eprint-class', 'primaryclass', 'primary-class', 'primary_class', 'archive_collection', 'archive-collection', 'archivecollection',
            'archive_location', 'archive-location', 'archivelocation', 'archive-summary', 'archivesummary' => $this->normalizeSortText($this->renderVariableValue($item, $variable, $scope)),
            'type' => $this->normalizeSortText((string) $item['type']),
            'citation-number' => sprintf('%08d', (int) $this->primaryCitationNumberForId((string) ($item['id'] ?? ''))),
            'first-reference-note-number' => $this->numberVariableSortValue($item, $variable, $scope),
            'page', 'page-first', 'number', 'article-number', 'edition', 'volume', 'issue', 'issue-number', 'issuenumber', 'chapter-number', 'number-of-pages',
            'number-of-volumes', 'collection-number', 'series-number', 'seriesnumber', 'original-collection-number', 'originalcollectionnumber',
            'origseriesnumber', 'orig-series-number', 'original-series-number', 'originalseriesnumber', 'section', 'part-number', 'part', 'printing-number',
            'supplement', 'supplement-number', 'version' => $this->numberVariableSortValue($item, $variable, $scope),
            'id' => $this->normalizeSortText((string) $item['id']),
            default => $this->normalizeSortText($fallback),
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function numberVariableSortValue(array $item, string $variable, string $scope): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $this->renderVariableValue($item, $variable, $scope)) ?? '');
        if ($value === '') {
            return '';
        }

        if (!$this->cslNumberValueIsNumeric($value)) {
            return 'T|' . $this->normalizeSortText($value);
        }

        preg_match_all('/\d+/u', $value, $matches);
        $numbers = $matches[0] ?? [];
        if ($numbers === []) {
            return 'T|' . $this->normalizeSortText($value);
        }

        $parts = array_map(static fn (string $number): string => sprintf('%012d', (int) $number), $numbers);

        return 'N|' . implode('|', $parts);
    }

    /**
     * @param array<string, mixed> $item
     * @param array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool} $key
     */
    private function sortMacroValue(array $item, string $macro, string $scope, array $key): string
    {
        $elements = $this->style->macroRenderingElements($macro);
        if ($elements === null) {
            throw new \InvalidArgumentException('CSL references undefined macro: ' . $macro);
        }

        $bibliographyState = null;
        $substitutedVariables = [];
        $previousSortKeyNameRenderingOverrides = $this->sortKeyNameRenderingOverrides;
        $this->sortKeyNameRenderingOverrides = $this->nameRenderingOverridesForSortKey($key);
        try {
            $value = $this->renderRenderingElementsWithMacroStack(
                $elements,
                $item,
                $scope === 'bibliography' ? 'bibliography' : 'citation',
                '',
                [$macro],
                null,
                $bibliographyState,
                $substitutedVariables
            );
        } finally {
            $this->sortKeyNameRenderingOverrides = $previousSortKeyNameRenderingOverrides;
        }

        return $this->normalizeSortText($value);
    }

    /**
     * @param array{sort?:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool} $key
     * @return array{etAlMin?:int, etAlUseFirst?:int, etAlUseLast?:bool}|null
     */
    private function nameRenderingOverridesForSortKey(array $key): ?array
    {
        $overrides = [];
        if (is_int($key['namesMin'] ?? null)) {
            $overrides['etAlMin'] = $key['namesMin'];
        }
        if (is_int($key['namesUseFirst'] ?? null)) {
            $overrides['etAlUseFirst'] = $key['namesUseFirst'];
        }
        if (is_bool($key['namesUseLast'] ?? null)) {
            $overrides['etAlUseLast'] = $key['namesUseLast'];
        }

        return $overrides === [] ? null : $overrides;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function nameRenderingOptionsWithSortKeyOverrides(array $options): array
    {
        if ($this->sortKeyNameRenderingOverrides === null) {
            return $options;
        }

        return [...$options, ...$this->sortKeyNameRenderingOverrides];
    }

    /**
     * @param array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool} $key
     */
    private function sortVariable(array $key): string
    {
        $variable = strtolower(trim((string) ($key['variable'] ?? '')));
        if ($variable !== '') {
            return $variable;
        }

        $macro = strtolower(trim((string) ($key['macro'] ?? '')));
        if (str_contains($macro, 'author') || str_contains($macro, 'creator') || str_contains($macro, 'name')) {
            return 'author';
        }
        if (str_contains($macro, 'date') || str_contains($macro, 'issued') || str_contains($macro, 'year')) {
            return 'issued';
        }
        if (str_contains($macro, 'title')) {
            return 'title';
        }

        return 'id';
    }

    /**
     * @param mixed $primary
     * @param mixed $fallback
     * @param array{sort?:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool} $key
     */
    private function namesSortValue(mixed $primary, mixed $fallback, array $key = []): string
    {
        $names = is_array($primary) && $primary !== [] ? $primary : $fallback;
        if (!is_array($names) || $names === []) {
            return '';
        }

        $names = $this->sortKeyVisibleNames($names, $key);

        $parts = [];
        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }

            if (($name['etAl'] ?? false) === true) {
                continue;
            }

            if ((string) ($name['literal'] ?? '') !== '') {
                $parts[] = (string) $name['literal'];
                continue;
            }

            $parts[] = $this->nameSortValue($name);
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<mixed> $names
     * @param array{sort?:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool} $key
     * @return list<mixed>
     */
    private function sortKeyVisibleNames(array $names, array $key): array
    {
        $renderableNames = [];
        foreach ($names as $name) {
            if (!is_array($name) || ($name['etAl'] ?? false) === true) {
                continue;
            }

            $renderableNames[] = $name;
        }

        if ($renderableNames === []) {
            return [];
        }

        $namesMin = $key['namesMin'] ?? null;
        $namesUseFirst = $key['namesUseFirst'] ?? null;
        $count = count($renderableNames);
        if (!is_int($namesMin) || !is_int($namesUseFirst) || $count < $namesMin) {
            return $renderableNames;
        }

        $visibleCount = max(1, min($namesUseFirst, $count));
        $visible = array_slice($renderableNames, 0, $visibleCount);
        if ($this->sortKeyUsesLastName($key, $visibleCount, $count)) {
            $visible[] = $renderableNames[$count - 1];
        }

        return $visible;
    }

    /**
     * @param array{sort?:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool} $key
     */
    private function sortKeyUsesLastName(array $key, int $visibleCount, int $count): bool
    {
        if (($key['namesUseLast'] ?? false) !== true) {
            return false;
        }

        $namesMin = $key['namesMin'] ?? null;
        $namesUseFirst = $key['namesUseFirst'] ?? null;
        if (!is_int($namesMin) || !is_int($namesUseFirst) || $namesUseFirst > $namesMin - 2) {
            return false;
        }

        return $visibleCount + 1 < $count;
    }

    /**
     * @param array<string, mixed> $name
     */
    private function nameSortValue(array $name): string
    {
        $demote = (string) ($this->style->bibliographyNameRendering()['demoteNonDroppingParticle'] ?? 'never');
        $sortParts = in_array($demote, ['sort-only', 'display-and-sort'], true)
            ? [
                (string) ($name['family'] ?? ''),
                (string) ($name['droppingParticle'] ?? ''),
                (string) ($name['nonDroppingParticle'] ?? ''),
                (string) ($name['given'] ?? ''),
                (string) ($name['suffix'] ?? ''),
            ]
            : [
                (string) ($name['nonDroppingParticle'] ?? ''),
                (string) ($name['family'] ?? ''),
                (string) ($name['given'] ?? ''),
                (string) ($name['droppingParticle'] ?? ''),
                (string) ($name['suffix'] ?? ''),
            ];

        return trim(implode(' ', array_filter($sortParts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sortNameValue(array $item): string
    {
        return trim((string) ($item['sortName'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sortTitleValue(array $item): string
    {
        return trim((string) ($item['sortTitle'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function issuedSortValue(array $item): string
    {
        return $this->dateSortValue($item, 'issued');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateSortValue(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        $parts = is_array($date) && isset($date['parts']) && is_array($date['parts']) ? $date['parts'] : [];
        if ($parts !== []) {
            return sprintf('%+08d-%02d-%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
        }

        return $this->normalizeSortText(is_array($date) ? (string) ($date['display'] ?? $date['literal'] ?? '') : '');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sortYearSortValue(array $item): string
    {
        $sortYear = trim((string) ($item['sortYear'] ?? ''));
        if ($sortYear === '') {
            return '';
        }

        if (preg_match('/^-?\d+$/', $sortYear) === 1) {
            return sprintf('%+08d-00-00', (int) $sortYear);
        }

        return $this->normalizeSortText($sortYear);
    }

    private function normalizeSortText(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return strtolower($value);
    }

    /**
     * @param list<AstNode> $citations
     * @return list<array{text:string, collapsed:bool}>|null
     */
    private function renderCollapsedCitationEntries(array $citations): ?array
    {
        $mode = $this->citationCollapseMode();
        if ($mode === '' || count($citations) < 2) {
            return null;
        }

        if ($mode === 'citation-number') {
            return $this->renderCollapsedCitationNumberEntries($citations);
        }

        if (!$this->citationCollapseLayoutIsAuthorDateLike()) {
            return null;
        }

        $entries = [];
        $run = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                throw new \InvalidArgumentException('Citation cluster entries must be citation AST nodes');
            }

            $entry = $this->collapseableCitationEntry($citation);
            if ($entry === null) {
                $this->appendCollapsedCitationRun($entries, $run, $mode);
                $run = [];
                $entries[] = ['text' => $this->renderCitationEntry($citation), 'collapsed' => false];
                continue;
            }

            if ($run !== [] && (string) $entry['prefix'] !== '') {
                $this->appendCollapsedCitationRun($entries, $run, $mode);
                $run = [$entry];
                continue;
            }

            if ($run === [] || $this->citationCollapseEntriesMatch($run[0], $entry, $mode)) {
                $run[] = $entry;
                continue;
            }

            $this->appendCollapsedCitationRun($entries, $run, $mode);
            $run = [$entry];
        }

        $this->appendCollapsedCitationRun($entries, $run, $mode);

        return $entries;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<array{text:string, collapsed:bool}>|null
     */
    private function renderCollapsedCitationNumberEntries(array $citations): ?array
    {
        if (!$this->citationNumberCollapseLayoutIsPure()) {
            return null;
        }

        $entries = [];
        $run = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                throw new \InvalidArgumentException('Citation cluster entries must be citation AST nodes');
            }

            $entry = $this->collapseableCitationNumberEntry($citation);
            if ($entry === null) {
                $this->appendCollapsedCitationNumberRun($entries, $run);
                $run = [];
                $entries[] = ['text' => $this->renderCitationEntry($citation), 'collapsed' => false];
                continue;
            }

            if ($run === [] || (int) $entry['number'] === (int) $run[count($run) - 1]['number'] + 1) {
                $run[] = $entry;
                continue;
            }

            $this->appendCollapsedCitationNumberRun($entries, $run);
            $run = [$entry];
        }

        $this->appendCollapsedCitationNumberRun($entries, $run);

        return $entries;
    }

    private function citationCollapseMode(): string
    {
        $options = $this->style->citationOptions();
        $mode = (string) ($options['collapse'] ?? '');

        return in_array($mode, ['citation-number', 'year', 'year-suffix', 'year-suffix-ranged'], true) ? $mode : '';
    }

    private function citationNumberCollapseLayoutIsPure(): bool
    {
        $elements = $this->style->citationRenderingElements();
        if ($elements === []) {
            return false;
        }

        $shape = $this->citationNumberCollapseRenderingShape($elements);

        return $shape['citationNumbers'] === 1 && !$shape['unsupported'];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param list<string> $macroStack
     * @return array{citationNumbers:int, unsupported:bool}
     */
    private function citationNumberCollapseRenderingShape(array $elements, array $macroStack = []): array
    {
        $shape = ['citationNumbers' => 0, 'unsupported' => false];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $childShape = $this->citationNumberCollapseRenderingElementShape($element, $macroStack);
            $shape['citationNumbers'] += $childShape['citationNumbers'];
            $shape['unsupported'] = $shape['unsupported'] || $childShape['unsupported'];
        }

        return $shape;
    }

    /**
     * @param array<string, mixed> $element
     * @param list<string> $macroStack
     * @return array{citationNumbers:int, unsupported:bool}
     */
    private function citationNumberCollapseRenderingElementShape(array $element, array $macroStack): array
    {
        $type = (string) ($element['type'] ?? '');
        if ($type === 'group') {
            if ($this->renderingElementHasLocalDecoration($element)) {
                return ['citationNumbers' => 0, 'unsupported' => true];
            }

            $children = $element['children'] ?? [];

            return is_array($children)
                ? $this->citationNumberCollapseRenderingShape($children, $macroStack)
                : ['citationNumbers' => 0, 'unsupported' => true];
        }

        if ($type === 'number') {
            $variable = strtolower(trim((string) ($element['variable'] ?? '')));
            $form = strtolower(trim((string) ($element['form'] ?? 'numeric')));

            return [
                'citationNumbers' => $variable === 'citation-number' ? 1 : 0,
                'unsupported' => $variable !== 'citation-number'
                    || $form !== 'numeric'
                    || $this->renderingElementHasLocalDecoration($element),
            ];
        }

        if ($type === 'text') {
            if (isset($element['macro']) && is_string($element['macro'])) {
                $name = $element['macro'];
                if (in_array($name, $macroStack, true) || $this->renderingElementHasLocalDecoration($element)) {
                    return ['citationNumbers' => 0, 'unsupported' => true];
                }

                $macro = $this->style->macroRenderingElements($name);

                return is_array($macro)
                    ? $this->citationNumberCollapseRenderingShape($macro, [...$macroStack, $name])
                    : ['citationNumbers' => 0, 'unsupported' => true];
            }

            $variable = strtolower(trim((string) ($element['variable'] ?? '')));

            return [
                'citationNumbers' => $variable === 'citation-number' ? 1 : 0,
                'unsupported' => $variable !== 'citation-number' || $this->renderingElementHasLocalDecoration($element),
            ];
        }

        return ['citationNumbers' => 0, 'unsupported' => true];
    }

    /**
     * @param array<string, mixed> $element
     */
    private function renderingElementHasLocalDecoration(array $element): bool
    {
        return (string) ($element['prefix'] ?? '') !== ''
            || (string) ($element['suffix'] ?? '') !== ''
            || (string) ($element['display'] ?? '') !== ''
            || (string) ($element['textCase'] ?? '') !== ''
            || $this->renderingFormatting($element) !== []
            || ($element['quotes'] ?? false) === true
            || ($element['stripPeriods'] ?? false) === true;
    }

    private function citationCollapseLayoutIsAuthorDateLike(): bool
    {
        $elements = $this->style->citationRenderingElements();
        if ($elements === []) {
            return true;
        }

        $shape = $this->citationCollapseRenderingShape($elements);

        return $shape['hasCreator'] && $shape['hasYear'] && !$shape['unsupported'];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param list<string> $macroStack
     * @return array{hasCreator:bool, hasYear:bool, unsupported:bool}
     */
    private function citationCollapseRenderingShape(array $elements, array $macroStack = []): array
    {
        $shape = ['hasCreator' => false, 'hasYear' => false, 'unsupported' => false];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $childShape = $this->citationCollapseRenderingElementShape($element, $macroStack);
            $shape['hasCreator'] = $shape['hasCreator'] || $childShape['hasCreator'];
            $shape['hasYear'] = $shape['hasYear'] || $childShape['hasYear'];
            $shape['unsupported'] = $shape['unsupported'] || $childShape['unsupported'];
        }

        return $shape;
    }

    /**
     * @param array<string, mixed> $element
     * @param list<string> $macroStack
     * @return array{hasCreator:bool, hasYear:bool, unsupported:bool}
     */
    private function citationCollapseRenderingElementShape(array $element, array $macroStack): array
    {
        $empty = ['hasCreator' => false, 'hasYear' => false, 'unsupported' => false];
        $type = (string) ($element['type'] ?? '');
        if ($type === 'group') {
            $children = $element['children'] ?? [];

            return is_array($children)
                ? $this->citationCollapseRenderingShape($children, $macroStack)
                : ['hasCreator' => false, 'hasYear' => false, 'unsupported' => true];
        }

        if ($type === 'names') {
            $variable = strtolower(trim((string) ($element['variable'] ?? 'author editor')));
            $variables = preg_split('/\s+/', $variable) ?: [];
            $hasCreator = in_array('author', $variables, true) || in_array('editor', $variables, true);

            return ['hasCreator' => $hasCreator, 'hasYear' => false, 'unsupported' => !$hasCreator];
        }

        if ($type === 'date') {
            $variable = strtolower(trim((string) ($element['variable'] ?? '')));
            $dateParts = is_array($element['dateParts'] ?? null) ? $element['dateParts'] : [];
            $hasYear = false;
            foreach ($dateParts as $datePart) {
                if (is_array($datePart) && strtolower(trim((string) ($datePart['name'] ?? ''))) === 'year') {
                    $hasYear = true;
                    break;
                }
            }

            return [
                'hasCreator' => false,
                'hasYear' => in_array($variable, ['issued', 'date'], true) && $hasYear,
                'unsupported' => !in_array($variable, ['issued', 'date'], true) || !$hasYear,
            ];
        }

        if ($type === 'text') {
            if (isset($element['macro']) && is_string($element['macro'])) {
                $name = $element['macro'];
                if (in_array($name, $macroStack, true)) {
                    return ['hasCreator' => false, 'hasYear' => false, 'unsupported' => true];
                }

                $macro = $this->style->macroRenderingElements($name);

                return is_array($macro)
                    ? $this->citationCollapseRenderingShape($macro, [...$macroStack, $name])
                    : ['hasCreator' => false, 'hasYear' => false, 'unsupported' => true];
            }

            $variable = strtolower(trim((string) ($element['variable'] ?? '')));
            if ($variable === 'year-suffix') {
                return $empty;
            }
        }

        return ['hasCreator' => false, 'hasYear' => false, 'unsupported' => true];
    }

    /**
     * @return array{citation:AstNode, number:int, text:string}|null
     */
    private function collapseableCitationNumberEntry(AstNode $citation): ?array
    {
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null || (string) $citation->attr('mode', 'normal') !== 'normal') {
            return null;
        }

        if ($this->citationPrefix($citation) !== '' || $this->citationSuffix($citation) !== '') {
            return null;
        }

        $item = $this->itemWithCitationContext($item, $citation);
        $number = $this->citationNumberValue($item, $citation);
        if (preg_match('/^\d+$/', $number) !== 1) {
            return null;
        }

        return [
            'citation' => $citation,
            'number' => (int) $number,
            'text' => $number,
        ];
    }

    /**
     * @return array{citation:AstNode, author:string, year:string, yearBase:string, yearSuffix:string, prefix:string}|null
     */
    private function collapseableCitationEntry(AstNode $citation): ?array
    {
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null || (string) $citation->attr('mode', 'normal') !== 'normal') {
            return null;
        }

        if ($this->citationSuffix($citation) !== '') {
            return null;
        }

        $item = $this->itemWithCitationContext($item, $citation);
        $withoutSuffix = $this->itemWithYearSuffix($item, '', false);

        return [
            'citation' => $citation,
            'author' => $this->citationAuthorLabel($item, $citation),
            'year' => $this->citationYear($item),
            'yearBase' => $this->citationYear($withoutSuffix),
            'yearSuffix' => (string) ($item['yearSuffix'] ?? ''),
            'prefix' => $this->citationPrefix($citation),
        ];
    }

    /**
     * @param array{author:string, yearBase:string} $left
     * @param array{author:string, yearBase:string} $right
     */
    private function citationCollapseEntriesMatch(array $left, array $right, string $mode): bool
    {
        if ((string) $left['author'] !== (string) $right['author']) {
            return false;
        }

        if ($mode === 'year') {
            return true;
        }

        return (string) $left['yearBase'] === (string) $right['yearBase'];
    }

    /**
     * @param list<array{text:string, collapsed:bool}> $entries
     * @param list<array{citation:AstNode, number:int, text:string}> $run
     */
    private function appendCollapsedCitationNumberRun(array &$entries, array $run): void
    {
        if ($run === []) {
            return;
        }

        if (count($run) === 1) {
            $entries[] = ['text' => $this->renderCitationEntry($run[0]['citation']), 'collapsed' => false];

            return;
        }

        $entries[] = ['text' => $this->collapsedCitationNumberRunText($run), 'collapsed' => false];
    }

    /**
     * @param list<array{number:int, text:string}> $run
     */
    private function collapsedCitationNumberRunText(array $run): string
    {
        return (string) $run[0]['text'] . "\u{2013}" . (string) $run[count($run) - 1]['text'];
    }

    /**
     * @param list<array{text:string, collapsed:bool}> $entries
     * @param list<array{citation:AstNode, author:string, year:string, yearBase:string, yearSuffix:string, prefix:string}> $run
     */
    private function appendCollapsedCitationRun(array &$entries, array $run, string $mode): void
    {
        if ($run === []) {
            return;
        }

        if (count($run) === 1) {
            $entries[] = ['text' => $this->renderCitationEntry($run[0]['citation']), 'collapsed' => false];

            return;
        }

        $entries[] = ['text' => $this->collapsedCitationRunText($run, $mode), 'collapsed' => true];
    }

    /**
     * @param list<array{author:string, year:string, yearBase:string, yearSuffix:string, prefix:string}> $run
     */
    private function collapsedCitationRunText(array $run, string $mode): string
    {
        $author = (string) $run[0]['author'];
        $prefix = (string) $run[0]['prefix'];
        if ($mode === 'year-suffix' || $mode === 'year-suffix-ranged') {
            $baseYear = (string) $run[0]['yearBase'];
            $suffixes = [];
            $allSameBase = true;
            $allHaveSuffixes = true;
            foreach ($run as $entry) {
                $allSameBase = $allSameBase && (string) $entry['yearBase'] === $baseYear;
                $suffix = (string) $entry['yearSuffix'];
                $allHaveSuffixes = $allHaveSuffixes && $suffix !== '';
                if (!in_array($suffix, $suffixes, true)) {
                    $suffixes[] = $suffix;
                }
            }

            if ($allSameBase && $allHaveSuffixes) {
                $entry = $author . ' ' . $baseYear . $this->collapsedYearSuffixes($suffixes, $mode === 'year-suffix-ranged');

                return $prefix === '' ? $entry : $prefix . ' ' . $entry;
            }
        }

        $years = [];
        foreach ($run as $entry) {
            $year = (string) $entry['year'];
            if (!in_array($year, $years, true)) {
                $years[] = $year;
            }
        }

        $entry = $author . ' ' . implode($this->citationCollapseGroupDelimiter(), $years);

        return $prefix === '' ? $entry : $prefix . ' ' . $entry;
    }

    /**
     * @param list<string> $suffixes
     */
    private function collapsedYearSuffixes(array $suffixes, bool $ranged): string
    {
        if ($suffixes === []) {
            return '';
        }

        if ($ranged && count($suffixes) > 1 && $this->yearSuffixesAreSequentialSingleLetters($suffixes)) {
            return $suffixes[0] . '-' . $suffixes[count($suffixes) - 1];
        }

        return implode($this->citationYearSuffixDelimiter(), $suffixes);
    }

    /**
     * @param list<array{text:string, collapsed:bool}> $entries
     */
    private function joinCollapsedCitationEntries(array $entries): string
    {
        $body = '';
        foreach ($entries as $index => $entry) {
            if ($index > 0) {
                $previous = $entries[$index - 1];
                $delimiter = ((bool) ($previous['collapsed'] ?? false) && $this->hasExplicitAfterCollapseDelimiter())
                    ? $this->citationAfterCollapseDelimiter()
                    : $this->style->citationDelimiter();
                $body .= $delimiter;
            }

            $body .= (string) ($entry['text'] ?? '');
        }

        return $body;
    }

    private function citationCollapseGroupDelimiter(): string
    {
        $options = $this->style->citationOptions();

        return (string) ($options['citeGroupDelimiter'] ?? ', ');
    }

    private function citationYearSuffixDelimiter(): string
    {
        $options = $this->style->citationOptions();

        return (string) ($options['yearSuffixDelimiter'] ?? ',');
    }

    private function hasExplicitAfterCollapseDelimiter(): bool
    {
        $options = $this->style->citationOptions();

        return array_key_exists('afterCollapseDelimiter', $options) && $options['afterCollapseDelimiter'] !== null;
    }

    private function citationAfterCollapseDelimiter(): string
    {
        $options = $this->style->citationOptions();

        return (string) ($options['afterCollapseDelimiter'] ?? $this->style->citationDelimiter());
    }

    /**
     * @param list<string> $suffixes
     */
    private function yearSuffixesAreSequentialSingleLetters(array $suffixes): bool
    {
        $expected = null;
        foreach ($suffixes as $suffix) {
            if (preg_match('/^[a-z]$/', $suffix) !== 1) {
                return false;
            }

            $ordinal = ord($suffix);
            if ($expected === null) {
                $expected = $ordinal;
                continue;
            }

            if ($ordinal !== $expected + 1) {
                return false;
            }

            $expected = $ordinal;
        }

        return true;
    }

    private function renderCitationEntry(AstNode $citation): string
    {
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null) {
            return $this->missingCitationEntryText($citation);
        }

        $item = $this->itemWithCitationContext($item, $citation);
        $customEntry = $this->renderCustomCitationEntry($citation, $item);
        if ($customEntry !== null) {
            return $customEntry;
        }

        $mode = (string) $citation->attr('mode', 'normal');
        $year = $this->citationYear($item);
        $author = $this->citationAuthorLabel($item, $citation);
        $suffix = $this->citationSuffix($citation);
        $prefix = $this->citationPrefix($citation);
        $citationLabel = $this->standaloneCitationLabel($item);

        if ($citationLabel !== '') {
            $entry = $citationLabel . ($suffix === '' ? '' : ', ' . $suffix);

            return $prefix === '' ? $entry : $prefix . ' ' . $entry;
        }

        if ($mode === 'author_in_text') {
            $entry = $author . ' (' . $year . ($suffix === '' ? '' : ', ' . $suffix) . ')';
        } elseif ($mode === 'author_only') {
            $entry = $author;
        } elseif ($mode === 'suppress_author') {
            $entry = $year . ($suffix === '' ? '' : ', ' . $suffix);
        } else {
            $entry = $author . ' ' . $year . ($suffix === '' ? '' : ', ' . $suffix);
        }

        return $prefix === '' ? $entry : $prefix . ' ' . $entry;
    }

    private function missingCitationEntryText(AstNode $citation): string
    {
        $entry = $this->sourceCitationText($citation);
        $suffix = $this->citationSuffix($citation);
        if ($suffix !== '') {
            $entry .= ', ' . $suffix;
        }

        $prefix = $this->citationPrefix($citation);
        if ($prefix !== '' && preg_match('/^' . preg_quote($prefix, '/') . '(?:\s+|$)/u', $entry) === 1) {
            return $entry;
        }

        return $prefix === '' ? $entry : $prefix . ' ' . $entry;
    }

    /**
     * @return list<array{text:string, formatting?:array<string, string>}>
     */
    private function citationEntryInlineParts(AstNode $citation): array
    {
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null) {
            return [['text' => $this->missingCitationEntryText($citation)]];
        }

        $item = $this->itemWithCitationContext($item, $citation);
        $customParts = $this->customCitationEntryInlineParts($citation, $item);
        if ($customParts !== []) {
            return $customParts;
        }

        return [['text' => $this->renderCitationEntry($citation)]];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{text:string, formatting?:array<string, string>}>
     */
    private function customCitationEntryInlineParts(AstNode $citation, array $item): array
    {
        if ((string) $citation->attr('mode', 'normal') !== 'normal') {
            return [];
        }

        $elements = $this->style->citationRenderingElements();
        if (!$this->hasNonNameRenderingElement($elements)) {
            return [];
        }

        $entry = $this->renderCustomCitationEntry($citation, $item);
        if ($entry === null || $entry === '') {
            return [];
        }

        $formatting = $this->singleRenderedCitationElementFormatting($elements, $item, $citation);
        if ($formatting === []) {
            return [['text' => $entry]];
        }

        return [[
            'text' => $entry,
            'formatting' => $formatting,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private function singleRenderedCitationElementFormatting(array $elements, array $item, AstNode $citation): array
    {
        $renderedElements = [];
        $substitutedVariables = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $bibliographyState = null;
            $value = $this->renderRenderingElement($element, $item, 'citation', [], $citation, $bibliographyState, $substitutedVariables);
            if ($value !== '') {
                $renderedElements[] = $element;
            }
        }

        if (count($renderedElements) !== 1) {
            return [];
        }

        return $this->renderingFormatting($renderedElements[0]);
    }

    /**
     * @param list<array{text:string, formatting?:array<string, string>}> $parts
     * @param array<string, string> $formatting
     */
    private function appendCitationInlinePart(array &$parts, string $text, array $formatting = []): void
    {
        if ($text === '') {
            return;
        }

        $lastIndex = count($parts) - 1;
        if ($lastIndex >= 0 && ($parts[$lastIndex]['formatting'] ?? []) === $formatting) {
            $parts[$lastIndex]['text'] .= $text;

            return;
        }

        $part = ['text' => $text];
        if ($formatting !== []) {
            $part['formatting'] = $formatting;
        }

        $parts[] = $part;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderCustomBibliographyEntry(array $item, ?array &$bibliographyState = null): ?string
    {
        $elements = $this->style->bibliographyRenderingElements();
        if (!$this->hasNonNameRenderingElement($elements)) {
            return null;
        }

        return $this->style->formatBibliographyEntry(
            $this->renderRenderingElements($elements, $item, 'bibliography', $this->style->bibliographyDelimiter(), null, $bibliographyState)
        );
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{display:string, text:string, formatting?:array<string, string>}>
     */
    private function bibliographyDisplayParts(array $item): array
    {
        $substitutedVariables = [];

        return $this->bibliographyDisplayPartsForElements($this->style->bibliographyRenderingElements(), $item, [], $substitutedVariables);
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<array{display:string, text:string, formatting?:array<string, string>}>
     */
    private function bibliographyDisplayPartsForElements(array $elements, array $item, array $macroStack = [], array &$substitutedVariables = []): array
    {
        $parts = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            foreach ($this->bibliographyDisplayPartsForElement($element, $item, $macroStack, $substitutedVariables) as $part) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<array{display:string, text:string, formatting?:array<string, string>}>
     */
    private function bibliographyDisplayPartsForElement(array $element, array $item, array $macroStack = [], array &$substitutedVariables = []): array
    {
        if ($this->renderingElementSuppressedBySubstitute($element, $substitutedVariables)) {
            return [];
        }

        $display = $this->renderingDisplay($element);
        if ($display !== '') {
            $bibliographyState = null;
            $value = $this->renderRenderingElement($element, $item, 'bibliography', $macroStack, null, $bibliographyState, $substitutedVariables);

            if ($value === '') {
                return [];
            }

            $part = ['display' => $display, 'text' => $value];
            $formatting = $this->renderingFormatting($element);
            if ($formatting !== []) {
                $part['formatting'] = $formatting;
            }

            return [$part];
        }

        $type = (string) ($element['type'] ?? '');
        if ($type === 'group') {
            $children = $element['children'] ?? [];

            return is_array($children)
                ? $this->bibliographyDisplayPartsForElements($children, $item, $macroStack, $substitutedVariables)
                : [];
        }

        if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
            $name = $element['macro'];
            if (in_array($name, $macroStack, true)) {
                throw new \InvalidArgumentException('CSL macro recursion detected: ' . implode(' -> ', [...$macroStack, $name]));
            }

            $children = $this->style->macroRenderingElements($name);

            return is_array($children)
                ? $this->bibliographyDisplayPartsForElements($children, $item, [...$macroStack, $name], $substitutedVariables)
                : [];
        }

        if ($type === 'names') {
            [$names] = $this->namesForRenderingVariableWithSource(
                $item,
                (string) ($element['variable'] ?? 'author editor')
            );
            if ($names !== []) {
                return [];
            }

            $substitute = $element['substitute'] ?? [];
            if (!is_array($substitute)) {
                return [];
            }

            foreach ($substitute as $substituteElement) {
                if (!is_array($substituteElement)) {
                    continue;
                }

                $branchSubstitutedVariables = $substitutedVariables;
                $bibliographyState = null;
                $value = ((string) ($substituteElement['type'] ?? '')) === 'names'
                    ? $this->renderNamesElementValue($substituteElement, $item, 'bibliography', false, $bibliographyState, null, $branchSubstitutedVariables)
                    : $this->renderRenderingElement($substituteElement, $item, 'bibliography', $macroStack, null, $bibliographyState, $branchSubstitutedVariables);
                if ($value === '') {
                    continue;
                }

                $parts = $this->bibliographyDisplayPartsForElement($substituteElement, $item, $macroStack, $substitutedVariables);
                $this->markSubstituteRenderedVariables($substituteElement, $item, 'bibliography', $substitutedVariables, $macroStack);
                foreach ($branchSubstitutedVariables as $variable => $rendered) {
                    if ($rendered) {
                        $substitutedVariables[$variable] = true;
                    }
                }

                return $parts;
            }
        }

        if ($type === 'choose') {
            foreach (($element['branches'] ?? []) as $branch) {
                if (!is_array($branch) || !$this->chooseBranchMatches($branch, $item, 'bibliography')) {
                    continue;
                }

                $children = $branch['children'] ?? [];

                return is_array($children)
                    ? $this->bibliographyDisplayPartsForElements($children, $item, $macroStack, $substitutedVariables)
                    : [];
            }

            $children = $element['else'] ?? [];

            return is_array($children)
                ? $this->bibliographyDisplayPartsForElements($children, $item, $macroStack, $substitutedVariables)
                : [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $element
     */
    private function renderingDisplay(array $element): string
    {
        $display = strtolower(trim((string) ($element['display'] ?? '')));

        return in_array($display, ['block', 'left-margin', 'right-inline', 'indent'], true)
            ? $display
            : '';
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, string>
     */
    private function renderingFormatting(array $element): array
    {
        $formatting = $element['formatting'] ?? [];
        if (!is_array($formatting)) {
            return [];
        }

        $attributes = [];
        foreach ($formatting as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderCustomCitationEntry(AstNode $citation, array $item): ?string
    {
        if ((string) $citation->attr('mode', 'normal') !== 'normal') {
            return null;
        }

        $elements = $this->style->citationRenderingElements();
        if (!$this->hasNonNameRenderingElement($elements)) {
            return null;
        }

        $entry = $this->renderRenderingElements($elements, $item, 'citation', '', $citation);
        if ($entry === '') {
            return null;
        }

        $suffix = $this->citationSuffix($citation);
        if ($suffix !== '' && !$this->hasLocatorRenderingElement($elements) && !$this->hasCitationSuffixRenderingElement($elements)) {
            $entry .= ', ' . $suffix;
        }

        $prefix = $this->citationPrefix($citation);

        return $prefix === '' || $this->hasCitationPrefixRenderingElement($elements) ? $entry : $prefix . ' ' . $entry;
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function hasNonNameRenderingElement(array $elements): bool
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            if ($type === 'group') {
                if (isset($element['children']) && is_array($element['children']) && $this->hasNonNameRenderingElement($element['children'])) {
                    return true;
                }

                continue;
            }

            if ($type === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (is_array($branch) && isset($branch['children']) && is_array($branch['children']) && $this->hasNonNameRenderingElement($branch['children'])) {
                        return true;
                    }
                }

                if (isset($element['else']) && is_array($element['else']) && $this->hasNonNameRenderingElement($element['else'])) {
                    return true;
                }

                continue;
            }

            if ($type === 'names') {
                if (!$this->namesVariableIsDefaultCreatorFallback((string) ($element['variable'] ?? 'author editor'))) {
                    return true;
                }

                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationLabel(array $item): string
    {
        $citationLabel = $this->standaloneCitationLabel($item);
        if ($citationLabel !== '') {
            return $citationLabel;
        }

        return $this->citationAuthorLabel($item) . ' ' . $this->citationYear($item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function standaloneCitationLabel(array $item): string
    {
        return trim((string) ($item['citationLabel'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabel(array $item, ?AstNode $citation = null): string
    {
        $names = $this->citationAuthorLabelNames($item);
        if ($names === []) {
            $title = (string) $item['title'];

            return $title === '' ? (string) $item['id'] : $title;
        }

        return $this->renderNameList($names, $this->style->citationNameRendering(), false, $citation);
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array<string, mixed>>
     */
    private function citationAuthorLabelNames(array $item): array
    {
        $names = $item['shortAuthors'];
        if ($names === []) {
            $names = $item['authors'];
        }
        if ($names === []) {
            $names = $item['shortEditors'];
        }
        if ($names === []) {
            $names = $item['editors'];
        }
        if ($names === []) {
            $names = $item['translators'];
        }
        if ($names === [] && $this->authorityFallbackApplies($item)) {
            $names = $item['authorities'] ?? [];
        }

        return is_array($names) ? array_values($names) : [];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function authorityFallbackApplies(array $item): bool
    {
        return (string) ($item['type'] ?? '') === 'report';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationYear(array $item): string
    {
        $date = $item['issuedDate'] ?? null;
        if (is_array($date) && isset($date['year']) && $date['year'] !== null && isset($date['openEnded'])) {
            $year = (string) $date['year'];
            $boundary = (string) $date['openEnded'];
            if ($boundary === 'start' || $boundary === 'end') {
                return $this->appendYearSuffix($boundary === 'start' ? '/' . $year : $year . '/', $item);
            }
        }

        if (is_array($date) && isset($date['rangeParts']) && is_array($date['rangeParts'])) {
            $yearRange = $this->citationYearRange($date['rangeParts']);
            if ($yearRange !== '') {
                return $this->appendYearSuffix($yearRange, $item);
            }
        }

        if (isset($item['issuedYear']) && $item['issuedYear'] !== null) {
            return $this->appendYearSuffix((string) $item['issuedYear'], $item);
        }

        if (is_array($date) && (string) ($date['literal'] ?? '') !== '') {
            return $this->appendYearSuffix((string) $date['literal'], $item);
        }

        return $this->appendYearSuffix($this->style->term('no date'), $item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function appendYearSuffix(string $year, array $item): string
    {
        return $year . (string) ($item['yearSuffix'] ?? '');
    }

    /**
     * @param list<list<int>> $rangeParts
     */
    private function citationYearRange(array $rangeParts): string
    {
        if (count($rangeParts) < 2 || !isset($rangeParts[0][0], $rangeParts[1][0])) {
            return '';
        }

        $start = (int) $rangeParts[0][0];
        $end = (int) $rangeParts[1][0];

        return $start === $end ? (string) $start : $start . '/' . $end;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function hasIssuedDate(array $item): bool
    {
        if (isset($item['issuedYear']) && $item['issuedYear'] !== null) {
            return true;
        }

        $date = $item['issuedDate'] ?? null;

        return is_array($date) && (string) ($date['literal'] ?? '') !== '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function bibliographyAuthors(array $item, ?array &$bibliographyState = null): string
    {
        $names = $item['authors'];
        if ($names === []) {
            $names = $item['editors'];
        }
        if ($names === [] && $this->authorityFallbackApplies($item)) {
            $names = $item['authorities'] ?? [];
        }

        if (!is_array($names) || $names === []) {
            return '';
        }

        return $this->renderNameList($names, $this->style->bibliographyNameRendering(), true, null, $bibliographyState);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function bibliographyTranslators(array $item): string
    {
        $names = $item['translators'] ?? [];
        if (!is_array($names) || $names === []) {
            return '';
        }

        return $this->renderNameList($names, $this->style->bibliographyNameRendering(), true);
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function bibliographyRoleNameParts(array $item): array
    {
        $parts = [];
        $renderedRoleVariables = [];
        $editorialRoles = $item['editorialRoles'] ?? [];
        if (is_array($editorialRoles)) {
            foreach ($editorialRoles as $role) {
                if (!is_array($role) || !is_array($role['names'] ?? null) || $role['names'] === []) {
                    continue;
                }

                $parts[] = $this->editorialRoleBibliographyPart(
                    (string) ($role['type'] ?? ''),
                    (string) ($role['label'] ?? ''),
                    $role['names']
                );

                $variable = $this->editorialRoleNameVariable((string) ($role['type'] ?? ''));
                if ($variable !== null) {
                    $renderedRoleVariables[$variable] = true;
                }
            }
        }

        $roles = [
            ['chairs', 'Chaired by', 'chair'],
            ['collectionEditors', 'Collection edited by', 'collection-editor'],
            ['composers', 'Composed by', 'composer'],
            ['contributors', 'Contributed by', 'contributor'],
            ['editorTranslators', 'Edited and translated by', 'editor-translator'],
            ['executiveProducers', 'Executive produced by', 'executive-producer'],
            ['guests', 'Guest:', 'guest'],
            ['hosts', 'Hosted by', 'host'],
            ['narrators', 'Narrated by', 'narrator'],
            ['performers', 'Performed by', 'performer'],
            ['producers', 'Produced by', 'producer'],
            ['recipients', 'Recipient:', 'recipient'],
            ['scriptWriters', 'Script written by', 'script-writer'],
            ['compilers', 'Compiled by', 'compiler'],
            ['curators', 'Curated by', 'curator'],
            ['directors', 'Directed by', 'director'],
            ['editorialDirectors', 'Editorial direction by', 'editorial-director'],
            ['illustrators', 'Illustrated by', 'illustrator'],
            ['interviewers', 'Interview by', 'interviewer'],
            ['reviewedAuthors', 'Reviewed author:', 'reviewed-author'],
            ['redactors', 'Redacted by', 'redactor'],
            ['founders', 'Founded by', 'founder'],
            ['continuators', 'Continued by', 'continuator'],
            ['revisers', 'Revised by', 'reviser'],
            ['collaborators', 'Collaboration by', 'collaborator'],
            ['commentators', 'Commentary by', 'commentator'],
            ['annotators', 'Annotated by', 'annotator'],
            ['containerAuthors', 'Container author:', 'container-author'],
            ['seriesCreators', 'Series created by', 'series-creator'],
            ['introductionAuthors', 'Introduction by', 'introduction'],
            ['forewordAuthors', 'Foreword by', 'foreword'],
            ['afterwordAuthors', 'Afterword by', 'afterword'],
            ['originalAuthors', 'Original author:'],
        ];

        foreach ($roles as $role) {
            [$key, $label] = $role;
            $variable = $role[2] ?? null;
            if (is_string($variable) && isset($renderedRoleVariables[$variable])) {
                continue;
            }

            $names = $item[$key] ?? [];
            if (!is_array($names) || $names === []) {
                continue;
            }

            $parts[] = $label . ' ' . rtrim($this->renderNameList($names, $this->style->bibliographyNameRendering(), true), '.') . '.';
        }

        return $parts;
    }

    /**
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
     */
    private function editorialRoleBibliographyPart(string $type, string $label, array $names): string
    {
        $prefix = match ($this->normalizedEditorialRoleType($type)) {
            'editor' => 'Edited by',
            'compiler' => 'Compiled by',
            'curator' => 'Curated by',
            'director' => 'Directed by',
            'editorial-director' => 'Editorial direction by',
            'illustrator' => 'Illustrated by',
            'interviewer' => 'Interview by',
            'reviewed-author' => 'Reviewed author:',
            'redactor' => 'Redacted by',
            'founder' => 'Founded by',
            'continuator' => 'Continued by',
            'reviser' => 'Revised by',
            'collaborator' => 'Collaboration by',
            'organizer' => 'Organized by',
            'commentator' => 'Commentary by',
            'annotator' => 'Annotated by',
            'executive-producer' => 'Executive produced by',
            'guest' => 'Guest:',
            'host' => 'Hosted by',
            'narrator' => 'Narrated by',
            'performer' => 'Performed by',
            'producer' => 'Produced by',
            'script-writer' => 'Script written by',
            'introduction' => 'Introduction by',
            'foreword' => 'Foreword by',
            'afterword' => 'Afterword by',
            default => rtrim($label !== '' ? $label : self::editorialRoleDefaultLabel($type), ':') . ':',
        };

        return $prefix . ' ' . rtrim($this->renderNameList($names, $this->style->bibliographyNameRendering(), true), '.') . '.';
    }

    private function editorialRoleNameVariable(string $type): ?string
    {
        return match ($this->normalizedEditorialRoleType($type)) {
            'editor',
            'compiler',
            'curator',
            'director',
            'editorial-director',
            'illustrator',
            'interviewer',
            'reviewed-author',
            'redactor',
            'founder',
            'continuator',
            'reviser',
            'collaborator',
            'commentator',
            'annotator',
            'executive-producer',
            'guest',
            'host',
            'narrator',
            'performer',
            'producer',
            'script-writer',
            'introduction',
            'foreword',
            'afterword' => $this->normalizedEditorialRoleType($type),
            default => null,
        };
    }

    private function normalizedEditorialRoleType(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === '') {
            return 'editor';
        }

        $type = str_replace(['_', ' '], '-', $type);

        return match ($type) {
            'editorialdirector', 'editorial-director' => 'editorial-director',
            'reviewedauthor', 'reviewed-author' => 'reviewed-author',
            'executiveproducer', 'executive-producer' => 'executive-producer',
            'scriptwriter', 'script-writer' => 'script-writer',
            default => $type,
        };
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function legalPatentBibliographyParts(array $item): array
    {
        $type = (string) ($item['type'] ?? '');
        if (!in_array($type, ['patent', 'legislation', 'legal_case'], true)) {
            return [];
        }

        $parts = [];
        $genre = (string) ($item['genre'] ?? '');
        $number = (string) ($item['number'] ?? '');
        if ($genre !== '' || $number !== '') {
            $label = $this->legalPatentTypeLabel($item, $type, $genre);
            $parts[] = trim($label . ' ' . $number) . '.';
        }

        $authority = (string) ($item['authority'] ?? '');
        if ($authority !== '') {
            $parts[] = 'Authority: ' . rtrim($authority, '.') . '.';
        }

        $jurisdiction = (string) ($item['jurisdiction'] ?? '');
        if ($jurisdiction !== '') {
            $parts[] = 'Jurisdiction: ' . rtrim($jurisdiction, '.') . '.';
        }

        $holders = $item['holders'] ?? [];
        if (is_array($holders) && $holders !== []) {
            $parts[] = 'Holder: ' . rtrim($this->renderNameList($holders, $this->style->bibliographyNameRendering(), true), '.') . '.';
        }

        $eventDate = $item['eventDate'] ?? null;
        if (is_array($eventDate) && (string) ($eventDate['display'] ?? '') !== '') {
            $parts[] = 'Event date ' . (string) $eventDate['display'] . '.';
        }

        $status = (string) ($item['status'] ?? '');
        if ($status !== '') {
            $parts[] = 'Status: ' . rtrim($status, '.') . '.';
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function legalPatentTypeLabel(array $item, string $type, string $genre): string
    {
        if ($type === 'patent') {
            $label = (string) ($item['patentTypeLabel'] ?? '');
            if ($label !== '') {
                return $label;
            }
        }

        return $genre !== '' ? ucfirst($genre) : ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function eventBibliographyParts(array $item): array
    {
        $eventTitle = trim((string) ($item['eventTitle'] ?? ''));
        $eventTitleAddon = trim((string) ($item['eventTitleAddon'] ?? ''));
        $eventPlace = trim((string) ($item['eventPlace'] ?? ''));
        $eventPlaceList = $item['eventPlaceList'] ?? [];
        $multipleEventPlaces = is_array($eventPlaceList) && count(array_filter(
            array_map(static fn (mixed $place): string => trim((string) $place), $eventPlaceList),
            static fn (string $place): bool => $place !== ''
        )) > 1;
        $eventType = trim((string) ($item['eventType'] ?? ''));
        $eventDate = $item['eventDate'] ?? null;
        $eventOrganizers = $item['eventOrganizers'] ?? [];

        $legalType = in_array((string) ($item['type'] ?? ''), ['patent', 'legislation', 'legal_case'], true);
        if (
            $legalType
            && $eventTitle === ''
            && $eventTitleAddon === ''
            && $eventPlace === ''
            && $eventType === ''
            && (!is_array($eventOrganizers) || $eventOrganizers === [])
        ) {
            return [];
        }

        $parts = [];
        if ($eventTitle !== '') {
            $parts[] = $this->localizedEventBibliographyPart('event', 'Event', $eventTitle);
        }
        if ($eventTitleAddon !== '') {
            $parts[] = $this->localizedEventBibliographyPart('event-title-addon', 'Event addendum', $eventTitleAddon);
        }
        if ($eventType !== '') {
            $parts[] = $this->localizedEventBibliographyPart('event-type', 'Event type', $eventType);
        }
        if (is_array($eventOrganizers) && $eventOrganizers !== []) {
            $parts[] = $this->localizedEventBibliographyLabel('event-organizer', 'Event organizer') . ': ' . rtrim($this->renderNameList($eventOrganizers, $this->style->bibliographyNameRendering(), true), '.') . '.';
        }
        if ($eventPlace !== '') {
            $parts[] = $this->localizedEventBibliographyPart('event-place', $multipleEventPlaces ? 'Event places' : 'Event place', $eventPlace, $multipleEventPlaces);
        }
        if (is_array($eventDate) && (string) ($eventDate['display'] ?? '') !== '') {
            $parts[] = $this->localizedEventBibliographyLabel('event-date', 'Event date') . ' ' . (string) $eventDate['display'] . '.';
        }

        return $parts;
    }

    private function localizedEventBibliographyPart(string $termName, string $fallbackLabel, string $value, bool $plural = false): string
    {
        return $this->localizedEventBibliographyLabel($termName, $fallbackLabel, $plural) . ': ' . $this->withTerminalPunctuation($value);
    }

    private function localizedEventBibliographyLabel(string $termName, string $fallbackLabel, bool $plural = false): string
    {
        $label = trim($this->style->term($termName, 'long', $plural));

        return $label !== '' ? $label : $fallbackLabel;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function publicationDetailBibliographyParts(array $item): array
    {
        $parts = [];
        $mainTitle = (string) ($item['mainTitle'] ?? '');
        if ($mainTitle !== '') {
            $parts[] = 'Main title: ' . rtrim($mainTitle, '.') . '.';
        }

        $mainTitleAddon = (string) ($item['mainTitleAddon'] ?? '');
        if ($mainTitleAddon !== '') {
            $parts[] = 'Main title addendum: ' . rtrim($mainTitleAddon, '.') . '.';
        }

        $volumeTitle = (string) ($item['volumeTitle'] ?? '');
        if ($volumeTitle !== '') {
            $parts[] = 'Volume title: ' . rtrim($volumeTitle, '.') . '.';
        }

        $volumeTitleShort = (string) ($item['volumeTitleShort'] ?? '');
        if ($volumeTitleShort !== '') {
            $parts[] = 'Volume title abbreviation: ' . rtrim($volumeTitleShort, '.') . '.';
        }

        $partTitle = (string) ($item['partTitle'] ?? '');
        if ($partTitle !== '') {
            $parts[] = 'Part title: ' . rtrim($partTitle, '.') . '.';
        }

        $containerTitleShort = (string) ($item['containerTitleShort'] ?? '');
        if ($containerTitleShort !== '') {
            $parts[] = 'Journal abbreviation: ' . rtrim($containerTitleShort, '.') . '.';
        }

        $issueTitle = (string) ($item['issueTitle'] ?? '');
        if ($issueTitle !== '') {
            $parts[] = 'Issue title: ' . rtrim($issueTitle, '.') . '.';
        }

        $issueTitleAddon = (string) ($item['issueTitleAddon'] ?? '');
        if ($issueTitleAddon !== '') {
            $parts[] = 'Issue title addendum: ' . rtrim($issueTitleAddon, '.') . '.';
        }

        $volume = (string) ($item['volume'] ?? '');
        $issue = (string) ($item['issue'] ?? '');
        $numberOfVolumes = (string) ($item['numberOfVolumes'] ?? '');
        if ($volume !== '' || $issue !== '') {
            $details = [];
            if ($volume !== '') {
                $volumeDetail = ucfirst($this->style->term('volume', 'short')) . ' ' . $volume;
                if ($numberOfVolumes !== '') {
                    $volumeDetail .= ' of ' . $numberOfVolumes;
                }

                $details[] = $volumeDetail;
            }
            if ($issue !== '') {
                $details[] = $this->style->term('issue', 'short') . ' ' . $issue;
            }

            $parts[] = implode(', ', $details) . '.';
        } elseif ($numberOfVolumes !== '') {
            $parts[] = $numberOfVolumes . ' ' . $this->style->term('volume', 'short', true);
        }

        $edition = (string) ($item['edition'] ?? '');
        if ($edition !== '') {
            $parts[] = $edition . ' ' . $this->style->term('edition', 'short');
        }

        $collectionTitle = (string) ($item['collectionTitle'] ?? '');
        $collectionTitleShort = (string) ($item['collectionTitleShort'] ?? '');
        $collectionNumber = (string) ($item['collectionNumber'] ?? '');
        if ($collectionTitle !== '' && $collectionNumber !== '') {
            $parts[] = $collectionTitle . ', ' . $this->style->term('number', 'short') . ' ' . $collectionNumber . '.';
        } elseif ($collectionTitle !== '') {
            $parts[] = 'Series: ' . rtrim($collectionTitle, '.') . '.';
        } elseif ($collectionNumber !== '') {
            $parts[] = 'Series ' . $this->style->term('number', 'short') . ' ' . $collectionNumber . '.';
        }
        if ($collectionTitleShort !== '') {
            $parts[] = 'Series abbreviation: ' . rtrim($collectionTitleShort, '.') . '.';
        }

        $part = (string) ($item['part'] ?? '');
        if ($part !== '') {
            $parts[] = 'Part ' . $part . '.';
        }

        $chapterNumber = (string) ($item['chapterNumber'] ?? '');
        if ($chapterNumber !== '') {
            $parts[] = ucfirst($this->style->term('chapter', 'short')) . ' ' . $chapterNumber . '.';
        }

        $numberOfPages = (string) ($item['numberOfPages'] ?? '');
        if ($numberOfPages !== '') {
            $parts[] = $numberOfPages . ' ' . $this->style->term('page', 'short', true);
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function identifierBibliographyParts(array $item): array
    {
        $parts = [];
        $isbn = (string) ($item['isbn'] ?? '');
        if ($isbn !== '') {
            $parts[] = 'ISBN ' . $isbn . '.';
        }

        $issn = (string) ($item['issn'] ?? '');
        if ($issn !== '') {
            $parts[] = 'ISSN ' . $issn . '.';
        }

        $isan = (string) ($item['isan'] ?? '');
        if ($isan !== '') {
            $parts[] = 'ISAN ' . $isan . '.';
        }

        $ismn = (string) ($item['ismn'] ?? '');
        if ($ismn !== '') {
            $parts[] = 'ISMN ' . $ismn . '.';
        }

        $isrn = (string) ($item['isrn'] ?? '');
        if ($isrn !== '') {
            $parts[] = 'ISRN ' . $isrn . '.';
        }

        $iswc = (string) ($item['iswc'] ?? '');
        if ($iswc !== '') {
            $parts[] = 'ISWC ' . $iswc . '.';
        }

        $pmid = (string) ($item['pmid'] ?? '');
        if ($pmid !== '') {
            $parts[] = 'PMID ' . $pmid . '.';
        }

        $pmcid = (string) ($item['pmcid'] ?? '');
        if ($pmcid !== '') {
            $parts[] = 'PMCID ' . $pmcid . '.';
        }

        $mrNumber = (string) ($item['mrNumber'] ?? '');
        if ($mrNumber !== '') {
            $parts[] = 'MR ' . $mrNumber . '.';
        }

        $mrClass = (string) ($item['mrClass'] ?? '');
        if ($mrClass !== '') {
            $parts[] = 'MR class ' . $mrClass . '.';
        }

        $zbl = (string) ($item['zbl'] ?? '');
        if ($zbl !== '') {
            $parts[] = 'Zbl ' . $zbl . '.';
        }

        $jstor = (string) ($item['jstor'] ?? '');
        if ($jstor !== '') {
            $parts[] = 'JSTOR ' . $jstor . '.';
        }

        $hdl = (string) ($item['hdl'] ?? '');
        if ($hdl !== '') {
            $parts[] = 'HDL ' . $hdl . '.';
        }

        $lccn = (string) ($item['lccn'] ?? '');
        if ($lccn !== '') {
            $parts[] = 'LCCN ' . $lccn . '.';
        }

        $oclc = (string) ($item['oclc'] ?? '');
        if ($oclc !== '') {
            $parts[] = 'OCLC ' . $oclc . '.';
        }

        foreach ([
            ['orcid', 'ORCID'],
            ['isni', 'ISNI'],
            ['viaf', 'VIAF'],
            ['ror', 'ROR'],
            ['wikidata', 'Wikidata'],
        ] as [$key, $label]) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $label . ' ' . $value . '.';
            }
        }

        $archive = array_values(array_filter([
            (string) ($item['archive'] ?? ''),
            (string) ($item['archiveCollection'] ?? ''),
            (string) ($item['archivePlace'] ?? ''),
            (string) ($item['archiveLocation'] ?? ''),
        ], static fn (string $value): bool => $value !== ''));
        if ($archive !== []) {
            $parts[] = 'Archive: ' . implode(' ', $archive) . '.';
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function reviewMetadataBibliographyParts(array $item): array
    {
        $parts = [];
        foreach ([
            ['reviewedTitle', 'Reviewed title'],
            ['reviewedGenre', 'Reviewed genre'],
            ['reprintTitle', 'Reprint title'],
            ['translatedTitle', 'Translated title'],
            ['translatedSubtitle', 'Translated subtitle'],
            ['originalSubtitle', 'Original subtitle'],
            ['categorySummary', 'Categories'],
            ['citationAliasSummary', 'Citation aliases'],
            ['sortShorthand', 'Sort shorthand'],
            ['presort', 'Presort'],
            ['indexTitle', 'Index title'],
            ['indexSortTitle', 'Index sort title'],
            ['labelPrefix', 'Label prefix'],
            ['labelAlpha', 'Label alpha'],
            ['labelTitle', 'Label title'],
            ['extraAlpha', 'Extra alpha'],
            ['extraDate', 'Extra date'],
            ['extraTitle', 'Extra title'],
            ['sortInitial', 'Sort initial'],
            ['sortInitialHash', 'Sort initial hash'],
            ['references', 'References'],
            ['dimensions', 'Dimensions'],
            ['division', 'Division'],
            ['scale', 'Scale'],
            ['version', 'Version'],
            ['rights', 'Rights'],
            ['medium', 'Medium'],
            ['callNumber', 'Call number'],
            ['pagination', 'Pagination'],
            ['bookPagination', 'Book pagination'],
            ['thesisType', 'Thesis type'],
            ['articleNumber', 'Article number'],
            ['entrySubtype', 'Entry subtype'],
            ['biblatexGender', 'BibLaTeX gender'],
            ['status', 'Status'],
            ['annotation', 'Annotation'],
            ['note', 'Note'],
            ['addendum', 'Addendum'],
            ['authorType', 'Author type'],
            ['containerAuthorType', 'Container author type'],
            ['dateAddon', 'Date addendum'],
            ['originalDateAddon', 'Original date addendum'],
            ['reprintDateAddon', 'Reprint date addendum'],
            ['eventDateAddon', 'Event date addendum'],
            ['accessedDateAddon', 'Accessed date addendum'],
            ['risFieldProvenanceSummary', 'RIS field provenance'],
            ['risFieldDuplicateSummary', 'RIS duplicate fields'],
            ['risFieldConflictSummary', 'RIS conflicting fields'],
        ] as [$key, $label]) {
            if ($key === 'status' && in_array((string) ($item['type'] ?? ''), ['patent', 'legislation', 'legal_case'], true)) {
                continue;
            }

            $value = trim((string) ($item[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($key === 'addendum' && $value === trim((string) ($item['note'] ?? ''))) {
                continue;
            }

            $parts[] = $label . ': ' . $this->withTerminalPunctuation($value);
        }

        $reprintDate = $item['reprintDate'] ?? null;
        if (is_array($reprintDate) && (string) ($reprintDate['display'] ?? '') !== '') {
            $parts[] = 'Reprint date: ' . (string) $reprintDate['display'] . '.';
        }

        $availableDate = $item['availableDate'] ?? null;
        if (is_array($availableDate) && (string) ($availableDate['display'] ?? '') !== '') {
            $parts[] = 'Available date: ' . (string) $availableDate['display'] . '.';
        }

        $submittedDate = $item['submittedDate'] ?? null;
        if (is_array($submittedDate) && (string) ($submittedDate['display'] ?? '') !== '') {
            $parts[] = 'Submitted date: ' . (string) $submittedDate['display'] . '.';
        }

        $labelDate = $item['labelDate'] ?? null;
        if (is_array($labelDate) && (string) ($labelDate['display'] ?? '') !== '') {
            $parts[] = 'Label date: ' . (string) $labelDate['display'] . '.';
        }

        $dateMarkerSummary = trim((string) ($item['dateMarkerSummary'] ?? ''));
        if ($dateMarkerSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($dateMarkerSummary);
        }

        $dateTimeSummary = trim((string) ($item['dateTimeSummary'] ?? ''));
        if ($dateTimeSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($dateTimeSummary);
        }

        $dateSeasonSummary = trim((string) ($item['dateSeasonSummary'] ?? ''));
        if ($dateSeasonSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($dateSeasonSummary);
        }

        $dateEraSummary = trim((string) ($item['dateEraSummary'] ?? ''));
        if ($dateEraSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($dateEraSummary);
        }

        $fieldAnnotationSummary = trim((string) ($item['biblatexFieldAnnotationSummary'] ?? ''));
        if ($fieldAnnotationSummary !== '') {
            $parts[] = 'BibLaTeX field annotations: ' . $this->withTerminalPunctuation($fieldAnnotationSummary);
        }

        $biblatexOptionSummary = trim((string) ($item['biblatexOptionSummary'] ?? ''));
        if ($biblatexOptionSummary !== '') {
            $parts[] = 'BibLaTeX options: ' . $this->withTerminalPunctuation($biblatexOptionSummary);
        }

        $biblatexLanguageOptionSummary = trim((string) ($item['biblatexLanguageOptionSummary'] ?? ''));
        if ($biblatexLanguageOptionSummary !== '') {
            $parts[] = 'BibLaTeX language options: ' . $this->withTerminalPunctuation($biblatexLanguageOptionSummary);
        }

        $referenceContextSummary = trim((string) ($item['biblatexReferenceContextSummary'] ?? ''));
        if ($referenceContextSummary !== '') {
            $parts[] = 'BibLaTeX reference context: ' . $this->withTerminalPunctuation($referenceContextSummary);
        }

        $keywordSummary = trim((string) ($item['keywordSummary'] ?? ''));
        if ($keywordSummary !== '') {
            $parts[] = 'Keywords: ' . $this->withTerminalPunctuation($keywordSummary);
        }

        $customFieldSummary = trim((string) ($item['biblatexCustomFieldSummary'] ?? ''));
        if ($customFieldSummary !== '') {
            $parts[] = 'BibLaTeX custom fields: ' . $this->withTerminalPunctuation($customFieldSummary);
        }

        $customListSummary = trim((string) ($item['biblatexCustomListSummary'] ?? ''));
        if ($customListSummary !== '') {
            $parts[] = 'BibLaTeX custom lists: ' . $this->withTerminalPunctuation($customListSummary);
        }

        $customNameSummary = trim((string) ($item['biblatexCustomNameSummary'] ?? ''));
        if ($customNameSummary !== '') {
            $parts[] = 'BibLaTeX custom names: ' . $this->withTerminalPunctuation($customNameSummary);
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function relatedBibliographyParts(array $item): array
    {
        $summary = $this->relatedSummary($item);
        $parts = $summary === '' ? [] : [$this->withTerminalPunctuation($summary)];
        $options = $item['relatedOptions'] ?? [];
        if (is_array($options)) {
            $options = array_values(array_filter(
                array_map(static fn (mixed $option): string => trim((string) $option), $options),
                static fn (string $option): bool => $option !== ''
            ));
            if ($options !== []) {
                $parts[] = 'Related options: ' . implode('; ', $options) . '.';
            }
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function crossrefBibliographyParts(array $item): array
    {
        $summary = $this->crossrefSummary($item);

        return $summary === '' ? [] : [$this->withTerminalPunctuation($summary)];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function xrefBibliographyParts(array $item): array
    {
        $summary = $this->xrefSummary($item);

        return $summary === '' ? [] : [$this->withTerminalPunctuation($summary)];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function nameMetadataBibliographyParts(array $item): array
    {
        $parts = [];
        $nameAddon = trim((string) ($item['nameAddon'] ?? ''));
        if ($nameAddon !== '') {
            $parts[] = 'Name addendum: ' . $this->withTerminalPunctuation($nameAddon);
        }

        $annotations = $this->nameAnnotationSummary($item);
        if ($annotations !== '') {
            $parts[] = 'Name annotations: ' . $this->withTerminalPunctuation($annotations);
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function nameAnnotationSummary(array $item): string
    {
        $entries = [];
        foreach ($this->nameAnnotationSources() as [$key, $label]) {
            $names = $item[$key] ?? [];
            if (!is_array($names)) {
                continue;
            }

            foreach ($names as $index => $name) {
                if (!is_array($name) || !is_array($name['annotations'] ?? null)) {
                    continue;
                }

                foreach ($name['annotations'] as $annotation) {
                    if (!is_array($annotation)) {
                        continue;
                    }

                    $value = trim((string) ($annotation['value'] ?? ''));
                    if ($value === '') {
                        continue;
                    }

                    $part = strtolower(trim((string) ($annotation['part'] ?? 'name')));
                    $entries[] = $label . ' ' . ((int) $index + 1) . ($part !== '' && $part !== 'name' ? ' ' . $part : '') . ': ' . $value;
                }
            }
        }

        return implode('; ', $entries);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function relatedSummary(array $item): string
    {
        $values = $this->relatedSummaryValues($item);
        if ($values === '') {
            return '';
        }

        $type = trim((string) ($item['relatedType'] ?? ''));
        $label = trim((string) ($item['relatedString'] ?? ''));
        $hasExplicitLabel = $label !== '';
        if (!$hasExplicitLabel) {
            $label = self::defaultRelatedTypeLabel($type);
        }

        if ($type !== '' && ($hasExplicitLabel || $label === 'Related source')) {
            $label .= ' (' . $type . ')';
        }

        return $label . ': ' . $values;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function entrySetSummary(array $item): string
    {
        $values = $this->entrySetSummaryValues($item);

        return $values === '' ? '' : 'Entry set: ' . $values;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function entrySetSummaryValues(array $item): string
    {
        $summary = trim((string) ($item['entrySetSummary'] ?? ''));
        if ($summary !== '') {
            return $summary;
        }

        $entrySetItems = $item['entrySetItems'] ?? [];
        $missing = $item['missingEntrySetKeys'] ?? [];
        $keys = $item['entrySetKeys'] ?? [];

        return self::summarizedReferenceValues(
            is_array($entrySetItems) ? $entrySetItems : [],
            is_array($missing) ? array_values(array_map('strval', $missing)) : [],
            is_array($keys) ? array_values(array_map('strval', $keys)) : []
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function xdataSummary(array $item): string
    {
        $values = $this->xdataSummaryValues($item);

        return $values === '' ? '' : 'Xdata packets: ' . $values;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function xdataSummaryValues(array $item): string
    {
        $summary = trim((string) ($item['xdataSummary'] ?? ''));
        if ($summary !== '') {
            return $summary;
        }

        $xdataItems = $item['xdataItems'] ?? [];
        $missing = $item['missingXdataKeys'] ?? [];
        $keys = $item['xdataKeys'] ?? [];

        return self::summarizedReferenceValues(
            is_array($xdataItems) ? $xdataItems : [],
            is_array($missing) ? array_values(array_map('strval', $missing)) : [],
            is_array($keys) ? array_values(array_map('strval', $keys)) : []
        );
    }

    private static function defaultRelatedTypeLabel(string $type): string
    {
        return match (strtolower(str_replace('_', '-', trim($type)))) {
            'license' => 'License',
            'translationof', 'translation-of' => 'Translation of',
            'translatedas', 'translated-as' => 'Translated as',
            'reprintof', 'reprint-of' => 'Reprint of',
            'reprintas', 'reprint-as' => 'Reprinted as',
            'reviewof', 'review-of' => 'Review of',
            'reviewas', 'review-as' => 'Reviewed as',
            'commentaryof', 'commentary-of' => 'Commentary on',
            'commentaryas', 'commentary-as' => 'Commentary published as',
            'annotationof', 'annotation-of' => 'Annotation of',
            'annotatedby', 'annotated-by' => 'Annotated by',
            'updateof', 'update-of' => 'Update of',
            'updatedby', 'updated-by' => 'Updated by',
            'supplementto', 'supplement-to' => 'Supplement to',
            'supplementedby', 'supplemented-by' => 'Supplemented by',
            'partof', 'part-of' => 'Part of',
            'continuedby', 'continued-by' => 'Continued by',
            'continues' => 'Continues',
            default => 'Related source',
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function relatedSummaryValues(array $item): string
    {
        $values = [];
        $relatedItems = $item['relatedItems'] ?? [];
        if (is_array($relatedItems)) {
            foreach ($relatedItems as $relatedItem) {
                if (!is_array($relatedItem)) {
                    continue;
                }

                $display = trim((string) ($relatedItem['display'] ?? ''));
                if ($display !== '') {
                    $values[] = $display;
                }
            }
        }

        $missing = $item['missingRelatedKeys'] ?? [];
        if (is_array($missing)) {
            foreach ($missing as $key) {
                $key = trim((string) $key);
                if ($key !== '') {
                    $values[] = 'missing: ' . $key;
                }
            }
        }

        if ($values === []) {
            $relatedKeys = $item['relatedKeys'] ?? [];
            if (is_array($relatedKeys)) {
                $values = array_values(array_filter(
                    array_map(static fn (mixed $key): string => trim((string) $key), $relatedKeys),
                    static fn (string $key): bool => $key !== ''
                ));
            }
        }

        if ($values === []) {
            return '';
        }

        return implode('; ', $values);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function crossrefSummary(array $item): string
    {
        $values = $this->crossrefSummaryValues($item);

        return $values === '' ? '' : 'Crossref: ' . $values;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function crossrefSummaryValues(array $item): string
    {
        $values = [];
        $crossrefItems = $item['crossrefItems'] ?? [];
        if (is_array($crossrefItems)) {
            foreach ($crossrefItems as $crossrefItem) {
                if (!is_array($crossrefItem)) {
                    continue;
                }

                $display = trim((string) ($crossrefItem['display'] ?? ''));
                if ($display !== '') {
                    $values[] = $display;
                }
            }
        }

        $missing = $item['missingCrossrefKeys'] ?? [];
        if (is_array($missing)) {
            foreach ($missing as $key) {
                $key = trim((string) $key);
                if ($key !== '') {
                    $values[] = 'missing: ' . $key;
                }
            }
        }

        if ($values === []) {
            $crossrefKeys = $item['crossrefKeys'] ?? [];
            if (is_array($crossrefKeys)) {
                $values = array_values(array_filter(
                    array_map(static fn (mixed $key): string => trim((string) $key), $crossrefKeys),
                    static fn (string $key): bool => $key !== ''
                ));
            }
        }

        if ($values === []) {
            return '';
        }

        return implode('; ', $values);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function xrefSummary(array $item): string
    {
        $values = $this->xrefSummaryValues($item);

        return $values === '' ? '' : 'Xref: ' . $values;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function xrefSummaryValues(array $item): string
    {
        $values = [];
        $xrefItems = $item['xrefItems'] ?? [];
        if (is_array($xrefItems)) {
            foreach ($xrefItems as $xrefItem) {
                if (!is_array($xrefItem)) {
                    continue;
                }

                $display = trim((string) ($xrefItem['display'] ?? ''));
                if ($display !== '') {
                    $values[] = $display;
                }
            }
        }

        $missing = $item['missingXrefKeys'] ?? [];
        if (is_array($missing)) {
            foreach ($missing as $key) {
                $key = trim((string) $key);
                if ($key !== '') {
                    $values[] = 'missing: ' . $key;
                }
            }
        }

        if ($values === []) {
            $xrefKeys = $item['xrefKeys'] ?? [];
            if (is_array($xrefKeys)) {
                $values = array_values(array_filter(
                    array_map(static fn (mixed $key): string => trim((string) $key), $xrefKeys),
                    static fn (string $key): bool => $key !== ''
                ));
            }
        }

        if ($values === []) {
            return '';
        }

        return implode('; ', $values);
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private function nameAnnotationSources(): array
    {
        return [
            ['authors', 'Author'],
            ['editors', 'Editor'],
            ['holders', 'Holder'],
            ['translators', 'Translator'],
            ['chairs', 'Chair'],
            ['containerAuthors', 'Container author'],
            ['collectionEditors', 'Collection editor'],
            ['seriesCreators', 'Series creator'],
            ['composers', 'Composer'],
            ['contributors', 'Contributor'],
            ['editorTranslators', 'Editor-translator'],
            ['executiveProducers', 'Executive producer'],
            ['eventOrganizers', 'Event organizer'],
            ['guests', 'Guest'],
            ['hosts', 'Host'],
            ['narrators', 'Narrator'],
            ['originalAuthors', 'Original author'],
            ['performers', 'Performer'],
            ['producers', 'Producer'],
            ['recipients', 'Recipient'],
            ['scriptWriters', 'Script writer'],
            ['compilers', 'Compiler'],
            ['curators', 'Curator'],
            ['directors', 'Director'],
            ['editorialDirectors', 'Editorial director'],
            ['illustrators', 'Illustrator'],
            ['interviewers', 'Interviewer'],
            ['reviewedAuthors', 'Reviewed author'],
            ['redactors', 'Redactor'],
            ['founders', 'Founder'],
            ['continuators', 'Continuator'],
            ['revisers', 'Reviser'],
            ['collaborators', 'Collaborator'],
            ['commentators', 'Commentator'],
            ['annotators', 'Annotator'],
            ['introductionAuthors', 'Introduction'],
            ['forewordAuthors', 'Foreword'],
            ['afterwordAuthors', 'Afterword'],
        ];
    }

    private function withTerminalPunctuation(string $value): string
    {
        return preg_match('/[.!?]\z/u', $value) === 1 ? $value : $value . '.';
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     */
    private function renderRenderingElements(array $elements, array $item, string $scope, string $delimiter, ?AstNode $citation = null, ?array &$bibliographyState = null): string
    {
        $substitutedVariables = [];

        return $this->renderRenderingElementsWithMacroStack($elements, $item, $scope, $delimiter, [], $citation, $bibliographyState, $substitutedVariables);
    }

    /**
     * @param list<string> $rendered
     */
    private function joinRenderedElements(array $rendered, string $delimiter): string
    {
        if ($rendered === []) {
            return '';
        }

        if ($delimiter === '') {
            return implode('', $rendered);
        }

        $output = array_shift($rendered);
        foreach ($rendered as $value) {
            $separator = $delimiter;
            [$output, $separator] = $this->moveFollowingPunctuationInsideClosingQuote($output, $separator);
            if ($separator !== '' && $output !== '' && preg_match('/^[.,;:!?]/', $separator) === 1) {
                $punctuation = $separator[0];
                if (str_ends_with($output, $punctuation)) {
                    $rest = substr($separator, 1);
                    $separator = trim($rest) === '' && $rest !== '' ? ' ' : ltrim($rest);
                }
            }

            $output .= $separator . $value;
        }

        return $output;
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     */
    private function renderRenderingElementsWithMacroStack(array $elements, array $item, string $scope, string $delimiter, array $macroStack, ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        $rendered = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $value = $this->renderRenderingElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables);
            if ($value !== '') {
                $rendered[] = $value;
            }
        }

        return $this->joinRenderedElements($rendered, $delimiter);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, bool> $substitutedVariables
     */
    private function renderingElementSuppressedBySubstitute(array $element, array $substitutedVariables): bool
    {
        if ($substitutedVariables === []) {
            return false;
        }

        $type = (string) ($element['type'] ?? '');
        if (!in_array($type, ['text', 'date', 'number', 'names', 'label'], true)) {
            return false;
        }

        if (!isset($element['variable']) || !is_string($element['variable'])) {
            return false;
        }

        return $this->renderingVariableIsSuppressedBySubstitute($element['variable'], $substitutedVariables);
    }

    /**
     * @param array<string, bool> $substitutedVariables
     */
    private function renderingVariableIsSuppressedBySubstitute(string $variable, array $substitutedVariables): bool
    {
        $variables = $this->renderingVariableNames($variable);
        if ($variables === []) {
            return false;
        }

        foreach ($variables as $name) {
            if (!isset($substitutedVariables[$name])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function renderingVariableNames(string $variable): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                fn (string $part): string => $this->normalizedRenderingVariableName($part),
                preg_split('/\s+/', trim($variable)) ?: []
            ),
            static fn (string $part): bool => $part !== ''
        )));
    }

    private function normalizedRenderingVariableName(string $variable): string
    {
        return str_replace('_', '-', strtolower(trim($variable)));
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, bool> $substitutedVariables
     * @param list<string> $macroStack
     */
    private function markSubstituteRenderedVariables(array $element, array $item, string $scope, array &$substitutedVariables, array $macroStack = [], ?AstNode $citation = null): void
    {
        foreach ($this->substituteRenderedElementVariables($element, $item, $scope, $macroStack, $citation, $substitutedVariables) as $variable) {
            $substitutedVariables[$variable] = true;
        }
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<string>
     */
    private function substituteRenderedElementVariables(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, array $substitutedVariables = []): array
    {
        if ($this->renderingElementSuppressedBySubstitute($element, $substitutedVariables)) {
            return [];
        }

        $type = (string) ($element['type'] ?? '');
        if ($type === 'names') {
            return $this->substituteRenderedNamesElementVariables($element, $item, $scope, $macroStack, $citation, $substitutedVariables);
        }

        if (in_array($type, ['text', 'date', 'number', 'label'], true) && isset($element['variable']) && is_string($element['variable'])) {
            $probeSubstitutedVariables = $substitutedVariables;
            $bibliographyState = null;
            $value = $this->renderRenderingElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $probeSubstitutedVariables);

            if ($value === '') {
                return [];
            }

            return $this->renderingVariableNames($element['variable']);
        }

        if ($type === 'group') {
            return $this->substituteRenderedElementsVariables(is_array($element['children'] ?? null) ? $element['children'] : [], $item, $scope, $macroStack, $citation, $substitutedVariables);
        }

        if ($type === 'choose') {
            foreach (($element['branches'] ?? []) as $branch) {
                if (!is_array($branch) || !$this->chooseBranchMatches($branch, $item, $scope, $citation)) {
                    continue;
                }

                $children = $branch['children'] ?? [];

                return is_array($children)
                    ? $this->substituteRenderedElementsVariables($children, $item, $scope, $macroStack, $citation, $substitutedVariables)
                    : [];
            }

            if (isset($element['else']) && is_array($element['else'])) {
                return $this->substituteRenderedElementsVariables($element['else'], $item, $scope, $macroStack, $citation, $substitutedVariables);
            }

            return [];
        }

        if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
            $macro = $element['macro'];
            if (in_array($macro, $macroStack, true)) {
                return [];
            }

            $elements = $this->style->macroRenderingElements($macro);

            return is_array($elements)
                ? $this->substituteRenderedElementsVariables($elements, $item, $scope, [...$macroStack, $macro], $citation, $substitutedVariables)
                : [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<string>
     */
    private function substituteRenderedNamesElementVariables(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, array $substitutedVariables = []): array
    {
        $variable = (string) ($element['variable'] ?? 'author editor');
        $nameGroups = $this->renderableNameGroupsForRenderingVariable($item, $variable, $substitutedVariables);
        if ($nameGroups !== []) {
            $probeSubstitutedVariables = $substitutedVariables;
            $bibliographyState = null;
            $value = $this->renderNamesElementValue($element, $item, $scope, false, $bibliographyState, $citation, $probeSubstitutedVariables);

            if ($value === '') {
                return [];
            }

            if ($this->rendersIndependentNamesVariableGroups($element, $nameGroups)) {
                return array_values(array_map(
                    static fn (array $group): string => $group['variable'],
                    $nameGroups
                ));
            }

            if ($this->rendersCombinedEditorTranslatorNameGroup($variable, $nameGroups)) {
                return array_values(array_map(
                    static fn (array $group): string => $group['variable'],
                    $nameGroups
                ));
            }

            return [(string) ($nameGroups[0]['variable'] ?? '')];
        }

        $substitute = $element['substitute'] ?? [];
        if (!is_array($substitute)) {
            return [];
        }

        foreach ($substitute as $substituteElement) {
            if (!is_array($substituteElement)) {
                continue;
            }

            $probeSubstitutedVariables = $substitutedVariables;
            $bibliographyState = null;
            $value = ((string) ($substituteElement['type'] ?? '')) === 'names'
                ? $this->renderNamesElementValue($substituteElement, $item, $scope, false, $bibliographyState, $citation, $probeSubstitutedVariables)
                : $this->renderRenderingElement($substituteElement, $item, $scope, $macroStack, $citation, $bibliographyState, $probeSubstitutedVariables);
            if ($value === '') {
                continue;
            }

            return $this->substituteRenderedElementVariables($substituteElement, $item, $scope, $macroStack, $citation, $substitutedVariables);
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<string>
     */
    private function substituteRenderedElementsVariables(array $elements, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, array $substitutedVariables = []): array
    {
        $variables = [];
        foreach ($elements as $element) {
            if (is_array($element)) {
                $variables = [...$variables, ...$this->substituteRenderedElementVariables($element, $item, $scope, $macroStack, $citation, $substitutedVariables)];
            }
        }

        return array_values(array_unique($variables));
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     * @return list<array{variable:string, names:list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>}>
     */
    private function renderableNameGroupsForRenderingVariable(array $item, string $variable, array $substitutedVariables = []): array
    {
        $nameGroups = $this->namesForRenderingVariableGroups($item, $variable);
        if ($nameGroups === [] || $substitutedVariables === []) {
            return $nameGroups;
        }

        return array_values(array_filter(
            $nameGroups,
            fn (array $group): bool => !isset($substitutedVariables[$this->normalizedRenderingVariableName((string) ($group['variable'] ?? ''))])
        ));
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     */
    private function renderRenderingElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        if ($this->renderingElementSuppressedBySubstitute($element, $substitutedVariables)) {
            return '';
        }

        $type = (string) ($element['type'] ?? '');
        $value = match ($type) {
            'group' => $this->renderGroupElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables),
            'text' => $this->renderTextElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables),
            'date' => $this->renderDateElement($element, $item, $scope),
            'number' => $this->renderNumberElement($element, $item, $scope, $citation),
            'names' => $this->renderNamesElement($element, $item, $scope, $bibliographyState, $citation, $substitutedVariables),
            'label' => $this->renderLabelElement($element, $item, $scope, $citation),
            'choose' => $this->renderChooseElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables),
            default => '',
        };

        $value = $this->stripRenderingPeriods($value, $element);
        $value = $this->applyTextCase($value, $element, $item);
        $value = $this->applyRenderingQuotes($value, $element);

        return $this->applyRenderingAffixes($value, $element);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     */
    private function renderGroupElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        $children = $element['children'] ?? [];
        if (!is_array($children)) {
            return '';
        }

        $rendered = [];
        $hasVariableChild = false;
        $hasRenderedVariableChild = false;
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $isVariableChild = $this->isVariableRenderingElement($child);
            $hasVariableChild = $hasVariableChild || $isVariableChild;
            $value = $this->renderRenderingElement($child, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables);
            if ($value === '') {
                continue;
            }

            $rendered[] = $value;
            $hasRenderedVariableChild = $hasRenderedVariableChild || $isVariableChild;
        }

        if ($hasVariableChild && !$hasRenderedVariableChild) {
            return '';
        }

        return $this->joinRenderedElements($rendered, (string) ($element['delimiter'] ?? ''));
    }

    /**
     * @param array<string, mixed> $element
     */
    private function isVariableRenderingElement(array $element): bool
    {
        $type = (string) ($element['type'] ?? '');
        if ($type === 'date' || $type === 'number' || $type === 'names' || $type === 'label') {
            return true;
        }

        if ($type === 'text' && array_key_exists('variable', $element)) {
            return true;
        }

        if ($type === 'text' && array_key_exists('macro', $element)) {
            $macro = $this->style->macroRenderingElements((string) $element['macro']);
            return is_array($macro) && $this->hasVariableRenderingElement($macro);
        }

        if ($type === 'group' && isset($element['children']) && is_array($element['children'])) {
            foreach ($element['children'] as $child) {
                if (is_array($child) && $this->isVariableRenderingElement($child)) {
                    return true;
                }
            }
        }

        if ($type === 'choose') {
            foreach (($element['branches'] ?? []) as $branch) {
                if (is_array($branch) && isset($branch['children']) && is_array($branch['children']) && $this->hasVariableRenderingElement($branch['children'])) {
                    return true;
                }
            }

            return isset($element['else']) && is_array($element['else']) && $this->hasVariableRenderingElement($element['else']);
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function hasVariableRenderingElement(array $elements): bool
    {
        foreach ($elements as $element) {
            if (is_array($element) && $this->isVariableRenderingElement($element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function hasLocatorRenderingElement(array $elements): bool
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            $variable = strtolower(trim((string) ($element['variable'] ?? '')));
            if (($type === 'text' || $type === 'label') && in_array($variable, [
                'locator',
                'citation-locator-label',
                'locator-label',
                'citation-locator-value',
                'locator-value',
                'citation-locator-source',
                'locator-source',
                'citation-locator-raw',
                'locator-raw',
                'citation-locator-diagnostic-summary',
                'citation-locator-diagnostics',
                'locator-diagnostic-summary',
                'locator-diagnostics',
                'citation-locator-diagnostic-reasons',
                'locator-diagnostic-reasons',
            ], true)) {
                return true;
            }

            if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
                $macro = $this->style->macroRenderingElements($element['macro']);
                if (is_array($macro) && $this->hasLocatorRenderingElement($macro)) {
                    return true;
                }
            }

            if ($type === 'group' && isset($element['children']) && is_array($element['children']) && $this->hasLocatorRenderingElement($element['children'])) {
                return true;
            }

            if ($type === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (is_array($branch) && isset($branch['children']) && is_array($branch['children']) && $this->hasLocatorRenderingElement($branch['children'])) {
                        return true;
                    }
                }

                if (isset($element['else']) && is_array($element['else']) && $this->hasLocatorRenderingElement($element['else'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function hasCitationPrefixRenderingElement(array $elements): bool
    {
        return $this->hasCitationAffixRenderingElement($elements, ['citation-prefix']);
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function hasCitationSuffixRenderingElement(array $elements): bool
    {
        return $this->hasCitationAffixRenderingElement($elements, ['citation-suffix']);
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param list<string> $variables
     */
    private function hasCitationAffixRenderingElement(array $elements, array $variables): bool
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            $variable = strtolower(trim((string) ($element['variable'] ?? '')));
            if (($type === 'text' || $type === 'label') && in_array($variable, $variables, true)) {
                return true;
            }

            if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
                $macro = $this->style->macroRenderingElements($element['macro']);
                if (is_array($macro) && $this->hasCitationAffixRenderingElement($macro, $variables)) {
                    return true;
                }
            }

            if ($type === 'group' && isset($element['children']) && is_array($element['children']) && $this->hasCitationAffixRenderingElement($element['children'], $variables)) {
                return true;
            }

            if ($type === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (is_array($branch) && isset($branch['children']) && is_array($branch['children']) && $this->hasCitationAffixRenderingElement($branch['children'], $variables)) {
                        return true;
                    }
                }

                if (isset($element['else']) && is_array($element['else']) && $this->hasCitationAffixRenderingElement($element['else'], $variables)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     */
    private function renderTextElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        if (array_key_exists('macro', $element)) {
            return $this->renderMacroReference((string) $element['macro'], $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables);
        }

        if (array_key_exists('variable', $element)) {
            return $this->renderTextVariableValue(
                $item,
                (string) $element['variable'],
                (string) ($element['form'] ?? 'long'),
                $scope,
                $citation
            );
        }

        if (array_key_exists('term', $element)) {
            return $this->style->term(
                (string) $element['term'],
                (string) ($element['form'] ?? 'long'),
                (bool) ($element['plural'] ?? false)
            );
        }

        return (string) ($element['value'] ?? '');
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     */
    private function renderMacroReference(string $name, array $item, string $scope, array $macroStack, ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        $elements = $this->style->macroRenderingElements($name);
        if ($elements === null) {
            throw new \InvalidArgumentException('CSL references undefined macro: ' . $name);
        }

        if (in_array($name, $macroStack, true)) {
            throw new \InvalidArgumentException('CSL macro recursion detected: ' . implode(' -> ', [...$macroStack, $name]));
        }

        return $this->renderRenderingElementsWithMacroStack($elements, $item, $scope, '', [...$macroStack, $name], $citation, $bibliographyState, $substitutedVariables);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     */
    private function renderChooseElement(array $element, array $item, string $scope, array $macroStack, ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        foreach (($element['branches'] ?? []) as $branch) {
            if (!is_array($branch) || !$this->chooseBranchMatches($branch, $item, $scope, $citation)) {
                continue;
            }

            $children = $branch['children'] ?? [];

            return is_array($children)
                ? $this->renderRenderingElementsWithMacroStack($children, $item, $scope, '', $macroStack, $citation, $bibliographyState, $substitutedVariables)
                : '';
        }

        $else = $element['else'] ?? [];

        return is_array($else)
            ? $this->renderRenderingElementsWithMacroStack($else, $item, $scope, '', $macroStack, $citation, $bibliographyState, $substitutedVariables)
            : '';
    }

    /**
     * @param array<string, mixed> $branch
     * @param array<string, mixed> $item
     */
    private function chooseBranchMatches(array $branch, array $item, string $scope, ?AstNode $citation = null): bool
    {
        $conditions = [];
        $variables = $branch['variables'] ?? [];
        if (is_array($variables)) {
            foreach ($variables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsPresent($item, (string) $variable, $scope, $citation);
                }
            }
        }

        $types = $branch['types'] ?? [];
        if (is_array($types)) {
            $itemType = strtolower(trim((string) ($item['type'] ?? '')));
            foreach ($types as $type) {
                if (is_scalar($type)) {
                    $conditions[] = $itemType !== '' && $itemType === strtolower(trim((string) $type));
                }
            }
        }

        $locators = $branch['locators'] ?? [];
        if (is_array($locators)) {
            foreach ($locators as $locator) {
                if (is_scalar($locator)) {
                    $conditions[] = $this->citationLocatorMatches([$this->normalizedLocatorLabel((string) $locator)], $scope, $citation);
                }
            }
        }

        $positions = $branch['positions'] ?? [];
        if (is_array($positions)) {
            foreach ($positions as $position) {
                if (is_scalar($position)) {
                    $conditions[] = $this->citationPositionMatches((string) $position, $scope, $citation);
                }
            }
        }

        if (($branch['disambiguate'] ?? false) === true) {
            $conditions[] = $this->citationDisambiguateMatches($item, $scope, $citation);
        }

        $isCreatorVariables = $branch['isCreator'] ?? [];
        if (is_array($isCreatorVariables)) {
            foreach ($isCreatorVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsCreator($item, (string) $variable);
                }
            }
        }

        $isNumericVariables = $branch['isNumeric'] ?? [];
        if (is_array($isNumericVariables)) {
            foreach ($isNumericVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsNumeric($item, (string) $variable, $scope, $citation);
                }
            }
        }

        $isDateVariables = $branch['isDate'] ?? [];
        if (is_array($isDateVariables)) {
            foreach ($isDateVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsDate($item, (string) $variable);
                }
            }
        }

        $isUncertainDateVariables = $branch['isUncertainDate'] ?? [];
        if (is_array($isUncertainDateVariables)) {
            foreach ($isUncertainDateVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsUncertainDate($item, (string) $variable);
                }
            }
        }

        $isCircaDateVariables = $branch['isCircaDate'] ?? [];
        if (is_array($isCircaDateVariables)) {
            foreach ($isCircaDateVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsCircaDate($item, (string) $variable);
                }
            }
        }

        if ($conditions === []) {
            return false;
        }

        return match ((string) ($branch['match'] ?? 'all')) {
            'any' => in_array(true, $conditions, true),
            'none' => !in_array(true, $conditions, true),
            default => !in_array(false, $conditions, true),
        };
    }

    /**
     * @param list<string> $locators
     */
    private function citationLocatorMatches(array $locators, string $scope, ?AstNode $citation): bool
    {
        if ($scope !== 'citation' || !$citation instanceof AstNode) {
            return false;
        }

        $parts = $this->citationLocatorParts($citation);
        if ($parts['value'] === '') {
            return false;
        }

        return in_array($this->normalizedLocatorLabel($parts['label']), $locators, true);
    }

    private function citationPositionMatches(string $position, string $scope, ?AstNode $citation): bool
    {
        if ($scope !== 'citation' || !$citation instanceof AstNode) {
            return false;
        }

        $position = strtolower(trim($position));
        $tests = $citation->attr('cslPositionTests', []);
        if (is_array($tests)) {
            $normalized = array_map(static fn (mixed $test): string => strtolower(trim((string) $test)), $tests);

            return in_array($position, $normalized, true);
        }

        $actual = strtolower(trim((string) $citation->attr('cslPosition', '')));
        if ($actual === '') {
            return false;
        }

        if ($position === 'subsequent' && in_array($actual, ['subsequent', 'ibid', 'ibid-with-locator'], true)) {
            return true;
        }

        if ($position === 'ibid' && $actual === 'ibid-with-locator') {
            return true;
        }

        return $actual === $position;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationDisambiguateMatches(array $item, string $scope, ?AstNode $citation): bool
    {
        if ($scope === 'citation' && $citation instanceof AstNode) {
            $marker = $citation->attr('cslDisambiguate', null);
            if (is_bool($marker)) {
                return $marker;
            }

            if (is_scalar($marker)) {
                $normalized = strtolower(trim((string) $marker));
                if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                    return true;
                }
                if ($normalized === '' || in_array($normalized, ['0', 'false', 'no'], true)) {
                    return false;
                }
            }

            return trim((string) $citation->attr('cslYearSuffix', '')) !== '';
        }

        return ($item['cslDisambiguate'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsNumeric(array $item, string $variable, string $scope, ?AstNode $citation = null): bool
    {
        $normalized = strtolower(trim($variable));
        if ($normalized === '') {
            return false;
        }

        $value = $this->renderVariableValue($item, $normalized, $scope, $citation);

        return $value !== '' && $this->cslNumberValueIsNumeric($value);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsDate(array $item, string $variable): bool
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date)) {
            return false;
        }

        return (isset($date['parts']) && is_array($date['parts']) && $date['parts'] !== [])
            || (string) ($date['display'] ?? '') !== ''
            || (string) ($date['literal'] ?? '') !== '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsUncertainDate(array $item, string $variable): bool
    {
        $date = $this->dateVariableForRendering($item, $variable);

        return is_array($date) && (($date['uncertain'] ?? false) === true || ($date['circa'] ?? false) === true);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsCircaDate(array $item, string $variable): bool
    {
        $date = $this->dateVariableForRendering($item, $variable);

        return is_array($date) && ($date['circa'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderDateElement(array $element, array $item, string $scope): string
    {
        $variable = strtolower((string) ($element['variable'] ?? ''));
        $date = $this->dateVariableForRendering($item, $variable);
        if ($date === null) {
            return '';
        }

        $form = strtolower(trim((string) ($element['form'] ?? '')));
        $datePartsSelection = strtolower(trim((string) ($element['datePartsSelection'] ?? '')));
        $dateParts = $element['dateParts'] ?? [];
        if ($form === 'text' || $form === 'numeric') {
            return $this->renderDateForm(
                $date,
                $form,
                $scope,
                $variable,
                $datePartsSelection,
                is_array($dateParts) ? $dateParts : [],
                $item
            );
        }

        if (is_array($dateParts) && $dateParts !== []) {
            return $this->renderSelectedDateParts(
                $date,
                $dateParts,
                $scope,
                $variable,
                (string) ($element['delimiter'] ?? ''),
                $item
            );
        }

        if ($datePartsSelection !== '') {
            return $this->renderDateForm($date, 'text', $scope, $variable, $datePartsSelection);
        }

        return $this->renderDateVariable($date, $scope, $variable);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     */
    private function renderNamesElement(array $element, array $item, string $scope, ?array &$bibliographyState = null, ?AstNode $citation = null, array &$substitutedVariables = []): string
    {
        return $this->renderNamesElementValue($element, $item, $scope, true, $bibliographyState, $citation, $substitutedVariables);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     */
    private function renderNamesElementValue(array $element, array $item, string $scope, bool $allowImplicitTitleFallback, ?array &$bibliographyState = null, ?AstNode $citation = null, array &$substitutedVariables = []): string
    {
        $variable = (string) ($element['variable'] ?? 'author editor');
        $nameGroups = $this->renderableNameGroupsForRenderingVariable($item, $variable, $substitutedVariables);
        if ($nameGroups === []) {
            $substitute = $element['substitute'] ?? [];
            if (is_array($substitute) && $substitute !== []) {
                foreach ($substitute as $substituteElement) {
                    if (!is_array($substituteElement)) {
                        continue;
                    }

                    $value = ((string) ($substituteElement['type'] ?? '')) === 'names'
                        ? $this->renderNamesElementValue($substituteElement, $item, $scope, false, $bibliographyState, $citation, $substitutedVariables)
                        : $this->renderRenderingElement($substituteElement, $item, $scope, [], $citation, $bibliographyState, $substitutedVariables);
                    if ($value !== '') {
                        $this->markSubstituteRenderedVariables($substituteElement, $item, $scope, $substitutedVariables, [], $citation);

                        return $value;
                    }
                }
            }

            if ($allowImplicitTitleFallback && $scope === 'citation' && $this->namesVariableAllowsTitleFallback($variable)) {
                $title = (string) ($item['title'] ?? '');

                return $title === '' ? (string) ($item['id'] ?? '') : $title;
            }

            return '';
        }

        $elementOptions = $element['nameRendering'] ?? null;
        $options = is_array($elementOptions)
            ? $this->normalizedNameRenderingOptions($elementOptions, $scope)
            : ($scope === 'bibliography' ? $this->style->bibliographyNameRendering() : $this->style->citationNameRendering());
        $options = $this->nameRenderingOptionsWithSortKeyOverrides($options);

        if ($this->rendersCombinedEditorTranslatorNameGroup($variable, $nameGroups)) {
            $names = $nameGroups[0]['names'];
            $rendered = $this->renderNameList(
                $names,
                $options,
                $scope === 'bibliography',
                $citation,
                $bibliographyState
            );

            return $this->applyNamesLabel($rendered, 'editortranslator', $names, $options);
        }

        if (!$this->rendersIndependentNamesVariableGroups($element, $nameGroups)) {
            $names = $nameGroups[0]['names'];
            $selectedVariable = $nameGroups[0]['variable'];
            $rendered = $this->renderNameList(
                $names,
                $options,
                $scope === 'bibliography',
                $citation,
                $bibliographyState
            );

            return $this->applyNamesLabel($rendered, $selectedVariable, $names, $options);
        }

        $renderedGroups = [];
        foreach ($nameGroups as $nameGroup) {
            $names = $nameGroup['names'];
            $groupBibliographyState = null;
            $rendered = $this->renderNameList(
                $names,
                $options,
                $scope === 'bibliography',
                $citation,
                $groupBibliographyState
            );
            $rendered = $this->applyNamesLabel($rendered, $nameGroup['variable'], $names, $options);
            if ($rendered !== '') {
                $renderedGroups[] = $rendered;
            }
        }

        return $this->joinRenderedElements($renderedGroups, (string) ($options['delimiter'] ?? ''));
    }

    /**
     * @param list<array{variable:string, names:list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>}> $nameGroups
     */
    private function rendersCombinedEditorTranslatorNameGroup(string $variable, array $nameGroups): bool
    {
        $variables = preg_split('/\s+/', strtolower(trim($variable))) ?: [];
        $variables = array_values(array_filter($variables, static fn (string $value): bool => $value !== ''));
        if ($variables !== ['editor', 'translator'] || count($nameGroups) !== 2) {
            return false;
        }

        if (($nameGroups[0]['variable'] ?? '') !== 'editor' || ($nameGroups[1]['variable'] ?? '') !== 'translator') {
            return false;
        }

        return $this->nameListsMatch($nameGroups[0]['names'], $nameGroups[1]['names']);
    }

    /**
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $left
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $right
     */
    private function nameListsMatch(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        foreach ($left as $index => $leftName) {
            if (!isset($right[$index]) || $this->nameIdentityKey($leftName) !== $this->nameIdentityKey($right[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     */
    private function nameIdentityKey(array $name): string
    {
        return json_encode([
            'family' => (string) ($name['family'] ?? ''),
            'given' => (string) ($name['given'] ?? ''),
            'literal' => (string) ($name['literal'] ?? ''),
            'short' => (string) ($name['short'] ?? ''),
            'nonDroppingParticle' => (string) ($name['nonDroppingParticle'] ?? ''),
            'droppingParticle' => (string) ($name['droppingParticle'] ?? ''),
            'suffix' => (string) ($name['suffix'] ?? ''),
            'commaSuffix' => ($name['commaSuffix'] ?? false) === true,
            'staticOrdering' => ($name['staticOrdering'] ?? false) === true,
            'parseNames' => ($name['parseNames'] ?? true) === true,
            'annotations' => $name['annotations'] ?? [],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array{variable:string, names:list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>}> $nameGroups
     */
    private function rendersIndependentNamesVariableGroups(array $element, array $nameGroups): bool
    {
        if (count($nameGroups) < 2) {
            return false;
        }

        $options = $element['nameRendering'] ?? [];

        return is_array($options) && is_array($options['label'] ?? null);
    }

    private function namesVariableAllowsTitleFallback(string $variable): bool
    {
        $variables = preg_split('/\s+/', strtolower(trim($variable))) ?: [];
        if ($variables === [] || $variables === ['']) {
            return true;
        }

        foreach ($variables as $nameVariable) {
            if (in_array($nameVariable, ['author', 'editor'], true)) {
                return true;
            }
        }

        return false;
    }

    private function namesVariableIsDefaultCreatorFallback(string $variable): bool
    {
        $variables = preg_split('/\s+/', strtolower(trim($variable))) ?: [];
        if ($variables === [] || $variables === ['']) {
            return true;
        }

        foreach ($variables as $nameVariable) {
            if (!in_array($nameVariable, ['author', 'editor'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderNumberElement(array $element, array $item, string $scope, ?AstNode $citation = null): string
    {
        $variable = strtolower(trim((string) ($element['variable'] ?? '')));
        if ($variable === '') {
            return '';
        }

        $value = $this->renderVariableValue($item, $variable, $scope, $citation);
        if ($value === '') {
            return '';
        }

        $form = (string) ($element['form'] ?? 'numeric');
        $gender = in_array(strtolower(trim($form)), ['ordinal', 'long-ordinal'], true)
            ? $this->ordinalGenderForNumberVariable($variable, $item, $citation)
            : '';
        $value = $this->formatCslNumber($value, $form, $gender);

        if ($variable === 'locator') {
            return $this->formatCslLocatorRanges($value);
        }

        return $variable === 'page' ? $this->formatCslPageRanges($value) : $value;
    }

    private function formatCslNumber(string $value, string $form, string $gender = ''): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '' || !$this->cslNumberValueCanBeFormatted($value)) {
            return $value;
        }

        preg_match_all('/\d+|[-\x{2010}-\x{2015}]|,|&/u', $value, $matches);
        $tokens = $matches[0] ?? [];
        $rendered = '';
        foreach ($tokens as $token) {
            if (preg_match('/^\d+$/', $token) === 1) {
                $rendered .= $this->formatCslNumberToken((int) $token, $form, $gender);
                continue;
            }

            $rendered .= match ($token) {
                ',' => ', ',
                '&' => ' & ',
                default => '-',
            };
        }

        return trim(preg_replace('/\s+/', ' ', $rendered) ?? $rendered);
    }

    private function cslNumberValueIsNumeric(string $value): bool
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return false;
        }

        $token = '[\p{L}\p{N}]*\d[\p{L}\p{N}]*';

        return preg_match('/^' . $token . '\s*(?:(?:[-\x{2010}-\x{2015}]|,|&)\s*' . $token . '\s*)*$/u', $value) === 1;
    }

    private function cslNumberValueCanBeFormatted(string $value): bool
    {
        return preg_match('/^\d+\s*(?:(?:[-\x{2010}-\x{2015}]|,|&)\s*\d+\s*)*$/u', $value) === 1;
    }

    private function formatCslNumberToken(int $number, string $form, string $gender = ''): string
    {
        $numeric = (string) $number;

        return match ($form) {
            'ordinal' => $numeric . $this->ordinalSuffix($number, $gender),
            'long-ordinal' => $this->longOrdinalNumber($number, $gender),
            'roman' => $this->romanNumber($number) ?? $numeric,
            default => $numeric,
        };
    }

    private function ordinalSuffix(int $number, string $gender = ''): string
    {
        return $this->style->ordinalSuffixTerm($number, $gender) ?? 'th';
    }

    private function longOrdinalNumber(int $number, string $gender = ''): string
    {
        if ($number >= 1 && $number <= 10) {
            $term = $this->style->termOrNull('long-ordinal-' . sprintf('%02d', $number), 'long', false, $gender);
            if ($term !== null) {
                return $term;
            }
        }

        return (string) $number . $this->ordinalSuffix($number, $gender);
    }

    private function romanNumber(int $number): ?string
    {
        if ($number < 1 || $number > 3999) {
            return null;
        }

        $map = [
            1000 => 'm',
            900 => 'cm',
            500 => 'd',
            400 => 'cd',
            100 => 'c',
            90 => 'xc',
            50 => 'l',
            40 => 'xl',
            10 => 'x',
            9 => 'ix',
            5 => 'v',
            4 => 'iv',
            1 => 'i',
        ];

        $roman = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderLabelElement(array $element, array $item, string $scope, ?AstNode $citation = null): string
    {
        $variable = strtolower(trim((string) ($element['variable'] ?? '')));
        if ($variable === '') {
            return '';
        }

        if ($variable === 'locator') {
            $parts = $this->citationLocatorParts($citation);
            if ($parts['value'] === '') {
                return '';
            }

            $termName = $parts['label'];
            $value = $parts['value'];
        } else {
            $value = $this->renderVariableValue($item, $variable, $scope, $citation);
            if ($value === '') {
                return '';
            }

            $termName = $this->labelTermName($variable, $item);
        }

        $plural = match ((string) ($element['plural'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            default => $this->labelValueLooksPlural($value, $variable),
        };

        return $this->style->term($termName, (string) ($element['form'] ?? 'long'), $plural);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function labelTermName(string $variable, array $item): string
    {
        $variable = self::pageExtentCountVariableAlias($variable);

        if ($variable === 'page' || $variable === 'page-first') {
            $pagination = trim((string) ($item['pagination'] ?? ''));
            if ($pagination !== '') {
                return $this->paginationTermName($pagination);
            }
        }

        return match ($variable) {
            'page-first' => 'page',
            'number-of-pages' => 'page',
            'number-of-volumes' => 'volume',
            'chapter-number' => 'chapter',
            'article-number' => 'article-locator',
            'collection-number', 'series-number', 'seriesnumber' => 'number',
            'original-collection-number', 'originalcollectionnumber', 'origseriesnumber', 'orig-series-number', 'original-series-number', 'originalseriesnumber' => 'number',
            'issue-number', 'issuenumber' => 'issue',
            'part-number' => 'part',
            default => $variable,
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function ordinalGenderForNumberVariable(string $variable, array $item, ?AstNode $citation = null): string
    {
        $variable = strtolower(trim($variable));
        if ($variable === '') {
            return '';
        }

        $termName = $variable === 'locator'
            ? $this->citationLocatorParts($citation)['label']
            : $this->labelTermName($variable, $item);

        return $this->style->termGender($termName);
    }

    private function paginationTermName(string $pagination): string
    {
        $pagination = strtolower(trim($pagination));
        $pagination = str_replace(['_', ' '], '-', $pagination);

        return match ($pagination) {
            'p', 'pp', 'page', 'pages' => 'page',
            'col', 'cols', 'column', 'columns' => 'column',
            'l', 'll', 'line', 'lines' => 'line',
            'para', 'paras', 'paragraph', 'paragraphs' => 'paragraph',
            'sec', 'secs', 'section', 'sections' => 'section',
            'v', 'vv', 'verse', 'verses' => 'verse',
            default => $pagination,
        };
    }

    private function labelValueLooksPlural(string $value, string $variable = ''): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (in_array(self::pageExtentCountVariableAlias($variable), ['number-of-pages', 'number-of-volumes'], true)) {
            return !$this->labelCountValueLooksSingular($value);
        }

        if (preg_match('/\d\s*(?:[-\x{2010}-\x{2015}]|&|,|;|and)\s*\d/iu', $value) === 1) {
            return true;
        }

        if (preg_match('/\S\s*;\s*\S/u', $value) === 1) {
            return true;
        }

        return preg_match('/\band\b/iu', $value) === 1;
    }

    private function labelCountValueLooksSingular(string $value): bool
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        $normalized = is_string($normalized) ? $normalized : trim($value);

        return preg_match('/^(?:1|one|i)$/iu', $normalized) === 1;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function normalizedNameRenderingOptions(array $options, string $scope): array
    {
        $defaults = $scope === 'bibliography'
            ? $this->style->bibliographyNameRendering()
            : $this->style->citationNameRendering();

        return [
            'delimiter' => is_string($options['delimiter'] ?? null) ? $options['delimiter'] : $defaults['delimiter'],
            'and' => is_string($options['and'] ?? null) ? $options['and'] : $defaults['and'],
            'form' => is_string($options['form'] ?? null) ? $options['form'] : ($defaults['form'] ?? 'long'),
            'etAlMin' => is_int($options['etAlMin'] ?? null) ? $options['etAlMin'] : $defaults['etAlMin'],
            'etAlUseFirst' => is_int($options['etAlUseFirst'] ?? null) ? $options['etAlUseFirst'] : $defaults['etAlUseFirst'],
            'etAlUseLast' => is_bool($options['etAlUseLast'] ?? null) ? $options['etAlUseLast'] : (bool) ($defaults['etAlUseLast'] ?? false),
            'etAlSubsequentMin' => is_int($options['etAlSubsequentMin'] ?? null) ? $options['etAlSubsequentMin'] : ($defaults['etAlSubsequentMin'] ?? null),
            'etAlSubsequentUseFirst' => is_int($options['etAlSubsequentUseFirst'] ?? null) ? $options['etAlSubsequentUseFirst'] : ($defaults['etAlSubsequentUseFirst'] ?? null),
            'delimiterPrecedesEtAl' => is_string($options['delimiterPrecedesEtAl'] ?? null) ? $options['delimiterPrecedesEtAl'] : $defaults['delimiterPrecedesEtAl'],
            'delimiterPrecedesLast' => is_string($options['delimiterPrecedesLast'] ?? null) ? $options['delimiterPrecedesLast'] : ($defaults['delimiterPrecedesLast'] ?? 'contextual'),
            'delimiterPrecedesLastExplicit' => ($options['delimiterPrecedesLastExplicit'] ?? false) === true || ($defaults['delimiterPrecedesLastExplicit'] ?? false) === true,
            'etAl' => $this->normalizedEtAlRenderingOptions(
                is_array($defaults['etAl'] ?? null) ? $defaults['etAl'] : [],
                is_array($options['etAl'] ?? null) ? $options['etAl'] : []
            ),
            'initialize' => is_bool($options['initialize'] ?? null) ? $options['initialize'] : (bool) ($defaults['initialize'] ?? true),
            'initializeWith' => is_string($options['initializeWith'] ?? null) ? $options['initializeWith'] : $defaults['initializeWith'],
            'initializeWithHyphen' => is_bool($options['initializeWithHyphen'] ?? null) ? $options['initializeWithHyphen'] : ($defaults['initializeWithHyphen'] ?? true),
            'nameAsSortOrder' => is_string($options['nameAsSortOrder'] ?? null) ? $options['nameAsSortOrder'] : $defaults['nameAsSortOrder'],
            'nameAsSortOrderExplicit' => ($options['nameAsSortOrderExplicit'] ?? false) === true || ($defaults['nameAsSortOrderExplicit'] ?? false) === true,
            'sortSeparator' => is_string($options['sortSeparator'] ?? null) ? $options['sortSeparator'] : ($defaults['sortSeparator'] ?? ', '),
            'demoteNonDroppingParticle' => is_string($options['demoteNonDroppingParticle'] ?? null) ? $options['demoteNonDroppingParticle'] : ($defaults['demoteNonDroppingParticle'] ?? 'never'),
            'nameParts' => array_key_exists('nameParts', $options) && is_array($options['nameParts']) ? $options['nameParts'] : [],
            'institution' => is_array($options['institution'] ?? null) ? $options['institution'] : ($defaults['institution'] ?? null),
            'label' => is_array($options['label'] ?? null) ? $options['label'] : ($defaults['label'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $overrides
     * @return array{term:string, prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}
     */
    private function normalizedEtAlRenderingOptions(array $defaults, array $overrides): array
    {
        return [
            'term' => is_string($overrides['term'] ?? null) ? $overrides['term'] : (is_string($defaults['term'] ?? null) ? $defaults['term'] : 'et-al'),
            'prefix' => is_string($overrides['prefix'] ?? null) ? $overrides['prefix'] : (is_string($defaults['prefix'] ?? null) ? $defaults['prefix'] : ''),
            'suffix' => is_string($overrides['suffix'] ?? null) ? $overrides['suffix'] : (is_string($defaults['suffix'] ?? null) ? $defaults['suffix'] : ''),
            'textCase' => is_string($overrides['textCase'] ?? null) ? $overrides['textCase'] : (is_string($defaults['textCase'] ?? null) ? $defaults['textCase'] : ''),
            'stripPeriods' => is_bool($overrides['stripPeriods'] ?? null) ? $overrides['stripPeriods'] : (is_bool($defaults['stripPeriods'] ?? null) ? $defaults['stripPeriods'] : false),
            'quotes' => is_bool($overrides['quotes'] ?? null) ? $overrides['quotes'] : (is_bool($defaults['quotes'] ?? null) ? $defaults['quotes'] : false),
        ];
    }

    /**
     * @param array<string, mixed> $element
     */
    private function applyRenderingAffixes(string $value, array $element): string
    {
        if ($value === '') {
            return '';
        }

        $suffix = (string) ($element['suffix'] ?? '');
        [$value, $suffix] = $this->moveFollowingPunctuationInsideClosingQuote($value, $suffix);

        return (string) ($element['prefix'] ?? '') . $value . $suffix;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function moveFollowingPunctuationInsideClosingQuote(string $value, string $following): array
    {
        if (!$this->style->punctuationInQuote() || $value === '' || $following === '') {
            return [$value, $following];
        }

        $punctuation = $following[0];
        if ($punctuation !== '.' && $punctuation !== ',') {
            return [$value, $following];
        }

        $closeQuote = $this->style->term('close-quote');
        if ($closeQuote === '' || !str_ends_with($value, $closeQuote)) {
            return [$value, $following];
        }

        $inside = substr($value, 0, -strlen($closeQuote));
        if (preg_match('/[.,!?]\z/u', $inside) !== 1) {
            $inside .= $punctuation;
        }

        $rest = substr($following, 1);
        $following = trim($rest) === '' && $rest !== '' ? ' ' : ltrim($rest);

        return [$inside . $closeQuote, $following];
    }

    /**
     * @param array<string, mixed> $element
     */
    private function stripRenderingPeriods(string $value, array $element): string
    {
        if ($value === '' || ($element['stripPeriods'] ?? false) !== true) {
            return $value;
        }

        return str_replace('.', '', $value);
    }

    /**
     * @param array<string, mixed> $element
     */
    private function applyRenderingQuotes(string $value, array $element): string
    {
        if ($value === '' || ($element['quotes'] ?? false) !== true) {
            return $value;
        }

        return $this->style->term('open-quote') . $value . $this->style->term('close-quote');
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function applyTextCase(string $value, array $element, array $item): string
    {
        if ($value === '') {
            return '';
        }

        $textCase = strtolower(trim((string) ($element['textCase'] ?? '')));

        return match ($textCase) {
            'lowercase' => mb_strtolower($value, 'UTF-8'),
            'uppercase' => mb_strtoupper($value, 'UTF-8'),
            'capitalize-first' => $this->capitalizeFirstLowercaseWord($value),
            'capitalize-all' => $this->capitalizeAllLowercaseWords($value),
            'sentence' => $this->sentenceCaseText($value),
            'title' => $this->titleCaseText($value, $item),
            default => $value,
        };
    }

    private function capitalizeFirstLowercaseWord(string $value): string
    {
        return preg_replace_callback(
            '/[\p{L}\p{N}][\p{L}\p{M}\p{N}\']*/u',
            fn (array $matches): string => $this->wordIsLowercase($matches[0])
                ? $this->capitalizeWord($matches[0])
                : $matches[0],
            $value,
            1
        ) ?? $value;
    }

    private function capitalizeAllLowercaseWords(string $value): string
    {
        return preg_replace_callback(
            '/[\p{L}\p{N}][\p{L}\p{M}\p{N}\']*/u',
            fn (array $matches): string => $this->wordIsLowercase($matches[0])
                ? $this->capitalizeWord($matches[0])
                : $matches[0],
            $value
        ) ?? $value;
    }

    private function sentenceCaseText(string $value): string
    {
        if ($this->textIsUppercase($value)) {
            return $this->capitalizeWord(mb_strtolower($value, 'UTF-8'));
        }

        return $this->capitalizeFirstLowercaseWord($value);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function titleCaseText(string $value, array $item): string
    {
        if (!$this->titleCaseAppliesToItem($item)) {
            return $value;
        }

        $uppercase = $this->textIsUppercase($value);
        $source = $uppercase ? mb_strtolower($value, 'UTF-8') : $value;
        $matchCount = preg_match_all('/[\p{L}\p{N}][\p{L}\p{M}\p{N}\']*/u', $source, $matches, PREG_OFFSET_CAPTURE);
        if ($matchCount === false || $matchCount === 0) {
            return $source;
        }

        $words = $matches[0];
        $output = '';
        $offset = 0;
        $count = count($words);
        foreach ($words as $index => $match) {
            $word = (string) $match[0];
            $wordOffset = (int) $match[1];
            $separator = substr($source, $offset, $wordOffset - $offset);
            $output .= $separator;

            $isFirst = $index === 0;
            $isLast = $index === $count - 1;
            $followsColon = str_contains($separator, ':');
            $lowerWord = mb_strtolower($word, 'UTF-8');
            if (!$isFirst && !$isLast && !$followsColon && $this->isEnglishTitleStopWord($lowerWord)) {
                $output .= $lowerWord;
            } elseif ($uppercase || $this->wordIsLowercase($word)) {
                $output .= $this->capitalizeWord($lowerWord);
            } else {
                $output .= $word;
            }

            $offset = $wordOffset + strlen($word);
        }

        return $output . substr($source, $offset);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function titleCaseAppliesToItem(array $item): bool
    {
        $language = strtolower(trim((string) ($item['language'] ?? '')));
        if ($language !== '') {
            return str_starts_with($language, 'en');
        }

        $defaultLocale = strtolower(trim($this->style->defaultLocale()));

        return $defaultLocale === '' || str_starts_with($defaultLocale, 'en');
    }

    private function isEnglishTitleStopWord(string $word): bool
    {
        return in_array($word, [
            'a',
            'an',
            'and',
            'as',
            'at',
            'but',
            'by',
            'for',
            'from',
            'in',
            'into',
            'nor',
            'of',
            'on',
            'or',
            'over',
            'so',
            'the',
            'to',
            'up',
            'v',
            'via',
            'vs',
            'with',
            'yet',
        ], true);
    }

    private function wordIsLowercase(string $word): bool
    {
        return $word === mb_strtolower($word, 'UTF-8') && $word !== mb_strtoupper($word, 'UTF-8');
    }

    private function textIsUppercase(string $value): bool
    {
        return $value === mb_strtoupper($value, 'UTF-8') && $value !== mb_strtolower($value, 'UTF-8');
    }

    private function capitalizeWord(string $word): string
    {
        return preg_replace_callback(
            '/\p{L}/u',
            static fn (array $matches): string => mb_strtoupper($matches[0], 'UTF-8'),
            $word,
            1
        ) ?? $word;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderVariableValue(array $item, string $variable, string $scope, ?AstNode $citation = null): string
    {
        $normalized = strtolower(trim($variable));
        $pageExtentCountValue = self::pageExtentCountVariableValue($item, $normalized);
        if ($pageExtentCountValue !== null) {
            return $pageExtentCountValue;
        }

        return match ($normalized) {
            'locator' => $this->formatCslLocatorValue($this->citationLocatorParts($citation)),
            'citation-locator-label', 'locator-label' => $this->citationLocatorLabelValue($citation),
            'citation-locator-value', 'locator-value' => $this->formatCslLocatorValue($this->citationLocatorParts($citation)),
            'citation-locator-source', 'locator-source' => $this->citationLocatorSourceClass($citation),
            'citation-locator-raw', 'locator-raw' => $this->citationLocatorRawValue($citation),
            'citation-locator-diagnostic-summary', 'citation-locator-diagnostics', 'locator-diagnostic-summary', 'locator-diagnostics' => $this->citationLocatorDiagnosticSummary($citation),
            'citation-locator-diagnostic-reasons', 'locator-diagnostic-reasons' => $this->citationLocatorDiagnosticReasons($citation),
            'citation-prefix' => $this->citationPrefixValue($citation),
            'citation-suffix' => $this->citationSuffixValue($citation),
            'citation-affix-summary', 'citation-affixes' => $this->citationAffixSummaryForCitation($citation),
            'citation-number' => $this->citationNumberValue($item, $citation),
            'first-reference-note-number' => $this->firstReferenceNoteNumberValue($citation, $item),
            'id', 'citation-key' => (string) $item['id'],
            'type' => (string) $item['type'],
            'source', 'source-title', 'sourcetitle' => (string) ($item['source'] ?? ''),
            'endnote-title-variant-summary', 'endnote-title-variants' => (string) ($item['endnoteTitleVariantSummary'] ?? ''),
            'endnote-publication-type-hint-summary', 'endnote-publication-type-hints' => (string) ($item['endnotePublicationTypeHintSummary'] ?? ''),
            'endnote-date-diagnostic-summary', 'endnote-date-diagnostics' => (string) ($item['endnoteDateDiagnosticSummary'] ?? ''),
            'endnote-unsupported-field-summary', 'endnote-unsupported-fields' => (string) ($item['endnoteUnsupportedFieldSummary'] ?? ''),
            'source-file-summary', 'source-files-summary', 'source-files', 'source-attachment-summary', 'source-attachments' => $this->sourceFileSummary($item),
            'source-file-paths', 'source-file-path' => $this->sourceFilePaths($item),
            'source-file-labels', 'source-file-label' => $this->sourceFileLabels($item),
            'source-file-media-types', 'source-file-media-type' => $this->sourceFileMediaTypes($item),
            'source-file-diagnostic-summary', 'source-file-diagnostics', 'source-file-policy-summary' => $this->sourceFileDiagnosticSummary($item),
            'source-file-diagnostic-reasons', 'source-file-policy-reasons' => $this->sourceFileDiagnosticReasons($item),
            'ris-field-provenance', 'ris-field-provenance-summary', 'ris-provenance-summary' => (string) ($item['risFieldProvenanceSummary'] ?? ''),
            'ris-field-duplicates', 'ris-field-duplicate-summary', 'ris-duplicate-summary' => (string) ($item['risFieldDuplicateSummary'] ?? ''),
            'ris-field-conflicts', 'ris-field-conflict-summary', 'ris-conflict-summary' => (string) ($item['risFieldConflictSummary'] ?? ''),
            'citation-aliases', 'citation-alias', 'citationaliases', 'citationalias' => implode(', ', is_array($item['citationAliases'] ?? null) ? $item['citationAliases'] : []),
            'citation-alias-summary', 'citation-aliases-summary', 'citationaliassummary', 'citationaliasessummary' => (string) ($item['citationAliasSummary'] ?? ''),
            'citation-label' => (string) $item['citationLabel'],
            'shorthand' => (string) $item['shorthand'],
            'shorthand-intro' => (string) $item['shorthandIntro'],
            'sort-shorthand', 'sortshorthand' => (string) ($item['sortShorthand'] ?? ''),
            'list-shorthand', 'listshorthand', 'shorthand-list-sort-key', 'shorthand-list-sort' => (string) ($item['shorthandListSortKey'] ?? ''),
            'presort' => (string) ($item['presort'] ?? ''),
            'sort-key' => (string) $item['sortKey'],
            'sort-name' => (string) $item['sortName'],
            'sort-title' => (string) $item['sortTitle'],
            'sort-year' => (string) $item['sortYear'],
            'sort-initial', 'sortinit', 'sortinitial' => (string) ($item['sortInitial'] ?? ''),
            'sort-initial-hash', 'sortinithash' => (string) ($item['sortInitialHash'] ?? ''),
            'index-title', 'indextitle' => (string) ($item['indexTitle'] ?? ''),
            'index-sort-title', 'indexsorttitle' => (string) ($item['indexSortTitle'] ?? ''),
            'label-prefix', 'labelprefix' => (string) ($item['labelPrefix'] ?? ''),
            'label-alpha', 'labelalpha' => (string) ($item['labelAlpha'] ?? ''),
            'label-title', 'labeltitle' => (string) ($item['labelTitle'] ?? ''),
            'extra-alpha', 'extraalpha' => (string) ($item['extraAlpha'] ?? ''),
            'extra-date', 'extradate' => (string) ($item['extraDate'] ?? ''),
            'extra-title', 'extratitle' => (string) ($item['extraTitle'] ?? ''),
            'title' => (string) $item['title'],
            'short-title', 'title-short' => (string) $item['shortTitle'],
            'title-addon' => (string) $item['titleAddon'],
            'translated-title', 'translatedtitle', 'title-translation', 'titletranslation' => (string) ($item['translatedTitle'] ?? ''),
            'translated-subtitle', 'translatedsubtitle', 'title-translation-subtitle', 'titletranslationsubtitle', 'subtitle-translation', 'subtitletranslation' => (string) ($item['translatedSubtitle'] ?? ''),
            'translated-title-raw', 'translatedtitleraw', 'title-translation-raw', 'titletranslationraw' => $this->rawAliasedVariableValue($item, $variable, ['translated-title', 'translatedTitle', 'translatedtitle', 'title-translation', 'titleTranslation', 'titletranslation']),
            'translated-subtitle-raw', 'translatedsubtitleraw', 'title-translation-subtitle-raw', 'titletranslationsubtitleraw', 'subtitle-translation-raw', 'subtitletranslationraw' => $this->rawAliasedVariableValue($item, $variable, ['translated-subtitle', 'translatedSubtitle', 'translatedsubtitle', 'title-translation-subtitle', 'titleTranslationSubtitle', 'titletranslationsubtitle', 'subtitle-translation', 'subtitleTranslation', 'subtitletranslation']),
            'reviewed-title', 'reviewedtitle', 'reviewtitle' => (string) ($item['reviewedTitle'] ?? ''),
            'reviewed-title-raw', 'reviewedtitleraw', 'review-title-raw', 'reviewtitleraw' => $this->rawAliasedVariableValue($item, $variable, ['reviewed-title', 'reviewedTitle', 'reviewedtitle', 'reviewtitle', 'reviewTitle']),
            'reviewed-genre', 'reviewedgenre' => (string) ($item['reviewedGenre'] ?? ''),
            'reprint-title', 'reprinttitle' => (string) ($item['reprintTitle'] ?? ''),
            'original-title', 'originaltitle', 'origtitle' => (string) ($item['originalTitle'] ?? ''),
            'original-subtitle', 'originalsubtitle', 'origsubtitle' => (string) ($item['originalSubtitle'] ?? ''),
            'original-title-raw', 'originaltitleraw', 'orig-title-raw', 'origtitleraw' => $this->rawAliasedVariableValue($item, $variable, ['original-title', 'originalTitle', 'originaltitle', 'origtitle', 'origTitle']),
            'original-subtitle-raw', 'originalsubtitleraw', 'orig-subtitle-raw', 'origsubtitleraw' => $this->rawAliasedVariableValue($item, $variable, ['original-subtitle', 'originalSubtitle', 'originalsubtitle', 'origsubtitle', 'origSubtitle']),
            'original-title-addon', 'originaltitleaddon', 'original-titleaddon', 'origtitleaddon', 'origtitle-addon' => (string) ($item['originalTitleAddon'] ?? ''),
            'original-genre', 'origtype', 'origgenre' => (string) ($item['originalGenre'] ?? ''),
            'original-collection-title', 'originalcollectiontitle', 'origseries', 'orig-series', 'original-series', 'originalseries' => (string) ($item['originalCollectionTitle'] ?? ''),
            'original-collection-number', 'originalcollectionnumber', 'origseriesnumber', 'orig-series-number', 'original-series-number', 'originalseriesnumber' => (string) ($item['originalCollectionNumber'] ?? ''),
            'container-title', 'containertitle', 'container', 'container-title-text', 'containertitletext', 'book-title', 'booktitle', 'journal-title', 'journaltitle', 'journal', 'publication-title', 'publicationtitle' => (string) $item['containerTitle'],
            'container-title-short', 'containertitleshort', 'book-title-short', 'booktitleshort', 'container-title-abbreviation', 'containertitleabbreviation' => (string) $item['containerTitleShort'],
            'journalabbreviation', 'journal-abbreviation', 'shortjournal', 'short-journal', 'shortjournaltitle', 'short-journal-title', 'journaltitleshort', 'journal-title-short' => (string) $item['journalAbbreviation'],
            'container-title-addon', 'containertitleaddon', 'book-title-addon', 'booktitleaddon', 'journal-title-addon', 'journaltitleaddon', 'publication-title-addon', 'publicationtitleaddon' => (string) $item['containerTitleAddon'],
            'main-title', 'maintitle', 'main-title-text', 'maintitletext' => (string) $item['mainTitle'],
            'main-title-addon', 'maintitleaddon' => (string) $item['mainTitleAddon'],
            'volume-title-short', 'volumetitleshort', 'shortvolumetitle', 'short-volume-title' => (string) ($item['volumeTitleShort'] ?? ''),
            'event', 'event-title', 'eventtitle' => (string) $item['eventTitle'],
            'event-title-addon', 'eventtitleaddon' => (string) $item['eventTitleAddon'],
            'event-place', 'eventplace', 'event-location', 'eventlocation', 'event-venue', 'eventvenue', 'venue' => (string) $item['eventPlace'],
            'event-place-list', 'eventplacelist', 'event-location-list', 'eventlocationlist', 'event-venue-list', 'eventvenuelist', 'venue-list' => implode('; ', is_array($item['eventPlaceList'] ?? null) ? $item['eventPlaceList'] : []),
            'event-type', 'eventtype' => (string) $item['eventType'],
            'publisher', 'institution', 'organization', 'school' => (string) $item['publisher'],
            'publisher-place', 'publisherplace', 'publisher-location', 'publisherlocation', 'publication-place', 'publicationplace', 'pubplace', 'address', 'location' => (string) $item['publisherPlace'],
            'publisher-list', 'publisherlist', 'institution-list', 'institutionlist', 'organization-list', 'organizationlist', 'school-list', 'schoollist' => implode('; ', is_array($item['publisherList'] ?? null) ? $item['publisherList'] : []),
            'publisher-place-list', 'publisherplacelist', 'publisher-location-list', 'publisherlocationlist', 'publication-place-list', 'publicationplacelist', 'pubplace-list', 'pubplacelist', 'address-list', 'addresslist', 'location-list', 'locationlist' => implode('; ', is_array($item['publisherPlaceList'] ?? null) ? $item['publisherPlaceList'] : []),
            'page' => $this->formatCslPageRanges((string) $item['page']),
            'page-first' => (string) $item['pageFirst'],
            'pagination', 'page-label' => (string) $item['pagination'],
            'book-pagination', 'bookpagination' => (string) $item['bookPagination'],
            'thesis-type', 'thesistype' => (string) ($item['thesisType'] ?? ''),
            'article-number', 'articlenumber', 'eid' => (string) $item['articleNumber'],
            'references' => (string) ($item['references'] ?? ''),
            'dimensions', 'dimension' => (string) ($item['dimensions'] ?? ''),
            'scale' => (string) ($item['scale'] ?? ''),
            'number' => (string) $item['number'],
            'volume' => (string) $item['volume'],
            'volume-title', 'volumetitle', 'volume-title-text', 'volumetitletext' => (string) ($item['volumeTitle'] ?? ''),
            'issue', 'issue-number', 'issuenumber' => (string) $item['issue'],
            'issue-title', 'issuetitle', 'issue-title-text', 'issuetitletext' => (string) ($item['issueTitle'] ?? ''),
            'issue-title-addon', 'issuetitleaddon' => (string) ($item['issueTitleAddon'] ?? ''),
            'edition' => (string) $item['edition'],
            'collection-title', 'collectiontitle', 'collection', 'collection-title-text', 'collectiontitletext', 'series', 'series-title', 'seriestitle', 'series-title-text', 'seriestitletext' => (string) $item['collectionTitle'],
            'collection-title-short', 'collectiontitleshort', 'shortseries', 'short-series', 'shortcollection', 'short-collection', 'series-short', 'seriesshort', 'series-title-short', 'seriestitleshort' => (string) ($item['collectionTitleShort'] ?? ''),
            'collection-number', 'series-number', 'seriesnumber' => (string) $item['collectionNumber'],
            'number-of-volumes' => (string) $item['numberOfVolumes'],
            'number-of-pages' => (string) $item['numberOfPages'],
            'chapter-number' => (string) $item['chapterNumber'],
            'division', 'subdivision', 'sub-division', 'sub_division' => (string) ($item['division'] ?? ''),
            'section' => (string) $item['section'],
            'part-title', 'parttitle', 'part-title-text', 'parttitletext' => (string) ($item['partTitle'] ?? ''),
            'part', 'part-number' => (string) $item['part'],
            'printing', 'printing-number' => (string) ($item['printingNumber'] ?? ''),
            'supplement' => (string) ($item['supplement'] ?? ''),
            'supplement-number' => (string) ($item['supplementNumber'] ?? ''),
            'genre' => (string) $item['genre'],
            'patent-type', 'patenttype' => (string) ($item['patentType'] ?? ''),
            'patent-type-label', 'patenttypelabel' => (string) ($item['patentTypeLabel'] ?? ''),
            'entry-subtype', 'entrysubtype' => (string) $item['entrySubtype'],
            'gender', 'biblatex-gender', 'biblatexgender' => (string) ($item['biblatexGender'] ?? $item['gender'] ?? ''),
            'biblatex-gender-summary', 'biblatexgendersummary' => (string) ($item['biblatexGenderSummary'] ?? ''),
            'authority', 'authority-list', 'authoritylist', 'issuing-authority', 'issuingauthority', 'issuing-authority-list', 'issuingauthoritylist' => (string) $item['authority'],
            'jurisdiction' => (string) $item['jurisdiction'],
            'status', 'publication-status', 'publicationstatus', 'pubstate' => (string) $item['status'],
            'version' => (string) $item['version'],
            'rights', 'copyright', 'license', 'licence' => (string) ($item['rights'] ?? ''),
            'doi' => (string) $item['doi'],
            'url' => (string) $item['url'],
            'url-label', 'url-description', 'urldescription', 'urltitle', 'urllabel' => (string) ($item['urlLabel'] ?? ''),
            'isbn', 'isbn-13', 'isbn13', 'isbn-10', 'isbn10', 'eisbn', 'e-isbn', 'electronicisbn', 'electronic-isbn' => (string) $item['isbn'],
            'issn', 'printissn', 'print-issn', 'pissn', 'p-issn', 'eissn', 'e-issn', 'electronicissn', 'electronic-issn', 'onlineissn', 'online-issn', 'issnonline', 'issn-online' => (string) $item['issn'],
            'isan' => (string) ($item['isan'] ?? ''),
            'ismn' => (string) ($item['ismn'] ?? ''),
            'isrn' => (string) ($item['isrn'] ?? ''),
            'iswc' => (string) ($item['iswc'] ?? ''),
            'pmid', 'pubmed', 'pubmedid', 'pubmed-id' => (string) ($item['pmid'] ?? ''),
            'pmcid', 'pmc', 'pmc-id', 'pmcid-id' => (string) ($item['pmcid'] ?? ''),
            'mrnumber', 'mr-number', 'mathscinet' => (string) ($item['mrNumber'] ?? ''),
            'mrclass', 'mr-class' => (string) ($item['mrClass'] ?? ''),
            'zbl', 'zbmath' => (string) ($item['zbl'] ?? ''),
            'jstor', 'jstorid', 'jstor-id' => (string) ($item['jstor'] ?? ''),
            'hdl', 'handle', 'hdlid', 'hdl-id', 'handleid', 'handle-id' => (string) ($item['hdl'] ?? ''),
            'lccn', 'lccnnumber', 'lccn-number' => (string) ($item['lccn'] ?? ''),
            'oclc', 'oclcnumber', 'oclc-number' => (string) ($item['oclc'] ?? ''),
            'orcid', 'orcid-id', 'orcidid' => (string) ($item['orcid'] ?? ''),
            'isni' => (string) ($item['isni'] ?? ''),
            'viaf' => (string) ($item['viaf'] ?? ''),
            'ror' => (string) ($item['ror'] ?? ''),
            'wikidata', 'wikidata-id', 'wikidataid', 'wd' => (string) ($item['wikidata'] ?? ''),
            'authority-identifiers', 'authority-identifier-summary', 'creator-identifiers', 'creator-identifier-summary' => (string) ($item['authorityIdentifierSummary'] ?? ''),
            'archive' => (string) $item['archive'],
            'archive_collection', 'archive-collection', 'archivecollection' => (string) ($item['archiveCollection'] ?? ''),
            'archive-place', 'archiveplace', 'eprintclass', 'eprint-class', 'primaryclass', 'primary-class', 'primary_class' => (string) $item['archivePlace'],
            'archive_location', 'archive-location', 'archivelocation' => (string) $item['archiveLocation'],
            'archive-summary', 'archive-summary-text', 'archivesummary', 'eprint-summary', 'eprintsummary' => (string) ($item['archiveSummary'] ?? ''),
            'call-number', 'callnumber' => (string) $item['callNumber'],
            'language', 'langid', 'language-id', 'languageid', 'hyphenation' => (string) $item['language'],
            'language-list', 'languagelist' => implode('; ', is_array($item['languageList'] ?? null) ? $item['languageList'] : []),
            'abstract' => (string) $item['abstract'],
            'annotation', 'annote' => (string) ($item['annotation'] ?? ''),
            'medium', 'howpublished', 'how-published' => (string) $item['medium'],
            'note' => (string) $item['note'],
            'addendum' => (string) $item['addendum'],
            'name-addon' => (string) $item['nameAddon'],
            'author-type', 'authortype' => (string) ($item['authorType'] ?? ''),
            'container-author-type', 'bookauthor-type', 'bookauthortype' => (string) ($item['containerAuthorType'] ?? ''),
            'date-addon', 'dateaddendum', 'date-addendum' => (string) ($item['dateAddon'] ?? ''),
            'category', 'categories' => implode(', ', is_array($item['categories'] ?? null) ? $item['categories'] : []),
            'category-list', 'categorylist' => (string) ($item['categorySummary'] ?? ''),
            'category-summary', 'categories-summary' => (string) ($item['categorySummary'] ?? ''),
            'original-date-addon', 'origdateaddon', 'orig-date-addon', 'originaldateaddon' => (string) ($item['originalDateAddon'] ?? ''),
            'reprint-date-addon', 'reprintdateaddon', 'reprintdateaddendum', 'reprint-date-addendum' => (string) ($item['reprintDateAddon'] ?? ''),
            'event-date-addon', 'eventdateaddon' => (string) ($item['eventDateAddon'] ?? ''),
            'accessed-date-addon', 'urldateaddon', 'url-date-addon', 'accesseddateaddon' => (string) ($item['accessedDateAddon'] ?? ''),
            'name-annotation-summary' => $this->nameAnnotationSummary($item),
            'date-marker-summary', 'date-status', 'date-status-summary' => (string) ($item['dateMarkerSummary'] ?? ''),
            'date-time-summary', 'time-summary' => (string) ($item['dateTimeSummary'] ?? ''),
            'date-season-summary', 'season-summary' => (string) ($item['dateSeasonSummary'] ?? ''),
            'date-era-summary', 'era-summary' => (string) ($item['dateEraSummary'] ?? ''),
            'issued-time', 'date-time' => $this->dateTimeForVariable($item, 'issued'),
            'issued-end-time', 'date-end-time' => $this->dateEndTimeForVariable($item, 'issued'),
            'accessed-time' => $this->dateTimeForVariable($item, 'accessed'),
            'accessed-end-time' => $this->dateEndTimeForVariable($item, 'accessed'),
            'available-time', 'available-date-time' => $this->dateTimeForVariable($item, 'available-date'),
            'available-end-time', 'available-date-end-time' => $this->dateEndTimeForVariable($item, 'available-date'),
            'accepted-time', 'accepted-date-time' => $this->dateTimeForVariable($item, 'accepted-date'),
            'accepted-end-time', 'accepted-date-end-time' => $this->dateEndTimeForVariable($item, 'accepted-date'),
            'revised-time', 'revised-date-time', 'revision-time', 'revision-date-time' => $this->dateTimeForVariable($item, 'revised-date'),
            'revised-end-time', 'revised-date-end-time', 'revision-end-time', 'revision-date-end-time' => $this->dateEndTimeForVariable($item, 'revised-date'),
            'submitted-time', 'submitted-date-time' => $this->dateTimeForVariable($item, 'submitted'),
            'submitted-end-time', 'submitted-date-end-time' => $this->dateEndTimeForVariable($item, 'submitted'),
            'event-time' => $this->dateTimeForVariable($item, 'event-date'),
            'event-end-time' => $this->dateEndTimeForVariable($item, 'event-date'),
            'original-time' => $this->dateTimeForVariable($item, 'original-date'),
            'original-end-time' => $this->dateEndTimeForVariable($item, 'original-date'),
            'label-date-time', 'label-time' => $this->dateTimeForVariable($item, 'label-date'),
            'label-date-end-time', 'label-end-time' => $this->dateEndTimeForVariable($item, 'label-date'),
            'biblatex-field-annotations', 'biblatex-field-annotation-summary', 'biblatex-field-annotations-summary', 'field-annotation-summary' => (string) ($item['biblatexFieldAnnotationSummary'] ?? ''),
            'biblatex-options', 'biblatexoptions' => implode(', ', is_array($item['biblatexOptions'] ?? null) ? $item['biblatexOptions'] : []),
            'biblatex-option-summary', 'biblatex-options-summary', 'biblatexoptionssummary' => (string) ($item['biblatexOptionSummary'] ?? ''),
            'skipbib', 'biblatex-skipbib', 'biblatex-skip-bibliography' => ($item['biblatexSkipBibliography'] ?? false) === true ? 'true' : '',
            'biblatex-bibliography-visibility' => (string) ($item['biblatexBibliographyVisibility'] ?? ''),
            'biblatex-language-options', 'biblatexlanguageoptions', 'langidopts', 'language-options' => implode(', ', is_array($item['biblatexLanguageOptions'] ?? null) ? $item['biblatexLanguageOptions'] : []),
            'biblatex-language-option-summary', 'biblatex-language-options-summary', 'biblatexlanguageoptionssummary', 'language-option-summary', 'language-options-summary' => (string) ($item['biblatexLanguageOptionSummary'] ?? ''),
            'refsection', 'ref-section', 'biblatex-refsection', 'biblatexrefsection' => (string) ($item['biblatexRefsection'] ?? ''),
            'refsegment', 'ref-segment', 'biblatex-refsegment', 'biblatexrefsegment' => (string) ($item['biblatexRefsegment'] ?? ''),
            'reference-context', 'biblatex-reference-context', 'biblatex-reference-context-summary', 'biblatexreferencecontextsummary' => (string) ($item['biblatexReferenceContextSummary'] ?? ''),
            'biblatex-custom-fields', 'biblatex-custom-field-summary', 'biblatex-custom-summary' => (string) ($item['biblatexCustomFieldSummary'] ?? ''),
            'usera', 'userb', 'userc', 'userd', 'usere', 'userf', 'verba', 'verbb', 'verbc' => $this->biblatexCustomFieldValue($item, $normalized),
            'biblatex-custom-lists', 'biblatex-custom-list-summary', 'biblatex-custom-lists-summary' => (string) ($item['biblatexCustomListSummary'] ?? ''),
            'lista', 'listb', 'listc', 'listd', 'liste', 'listf' => $this->biblatexCustomListValue($item, $normalized),
            'biblatex-custom-names', 'biblatex-custom-name-summary', 'biblatex-custom-names-summary' => (string) ($item['biblatexCustomNameSummary'] ?? ''),
            'namea', 'nameb', 'namec' => $this->biblatexCustomNameValue($item, $normalized),
            'xdata' => $this->xdataSummaryValues($item),
            'xdata-summary' => (string) ($item['xdataSummary'] ?? ''),
            'xdata-keys' => implode(', ', is_array($item['xdataKeys'] ?? null) ? $item['xdataKeys'] : []),
            'missing-xdata-keys' => implode(', ', is_array($item['missingXdataKeys'] ?? null) ? $item['missingXdataKeys'] : []),
            'entry-set', 'entryset' => $this->entrySetSummaryValues($item),
            'entry-set-summary', 'entryset-summary' => (string) ($item['entrySetSummary'] ?? ''),
            'entry-set-keys', 'entryset-keys' => implode(', ', is_array($item['entrySetKeys'] ?? null) ? $item['entrySetKeys'] : []),
            'missing-entry-set-keys', 'missing-entryset-keys' => implode(', ', is_array($item['missingEntrySetKeys'] ?? null) ? $item['missingEntrySetKeys'] : []),
            'issued-status', 'issued-date-status' => $this->dateMarkerStatusForVariable($item, 'issued'),
            'accessed-status', 'accessed-date-status' => $this->dateMarkerStatusForVariable($item, 'accessed'),
            'available-status', 'available-date-status' => $this->dateMarkerStatusForVariable($item, 'available-date'),
            'accepted-status', 'accepted-date-status' => $this->dateMarkerStatusForVariable($item, 'accepted-date'),
            'revised-status', 'revised-date-status', 'revision-status', 'revision-date-status' => $this->dateMarkerStatusForVariable($item, 'revised-date'),
            'submitted-status', 'submitted-date-status' => $this->dateMarkerStatusForVariable($item, 'submitted'),
            'event-date-status' => $this->dateMarkerStatusForVariable($item, 'event-date'),
            'original-date-status' => $this->dateMarkerStatusForVariable($item, 'original-date'),
            'reprint-date-status' => $this->dateMarkerStatusForVariable($item, 'reprint-date'),
            'label-date-status', 'labeldate-status' => $this->dateMarkerStatusForVariable($item, 'label-date'),
            'issued-raw', 'issued-date-raw' => $this->dateRawForVariable($item, 'issued'),
            'accessed-raw', 'accessed-date-raw' => $this->dateRawForVariable($item, 'accessed'),
            'available-raw', 'available-date-raw' => $this->dateRawForVariable($item, 'available-date'),
            'accepted-raw', 'accepted-date-raw' => $this->dateRawForVariable($item, 'accepted-date'),
            'revised-raw', 'revised-date-raw', 'revision-raw', 'revision-date-raw' => $this->dateRawForVariable($item, 'revised-date'),
            'submitted-raw', 'submitted-date-raw' => $this->dateRawForVariable($item, 'submitted'),
            'event-date-raw' => $this->dateRawForVariable($item, 'event-date'),
            'original-date-raw' => $this->dateRawForVariable($item, 'original-date'),
            'reprint-date-raw' => $this->dateRawForVariable($item, 'reprint-date'),
            'label-date-raw', 'labeldate-raw' => $this->dateRawForVariable($item, 'label-date'),
            'issued-season', 'date-season' => $this->dateSeasonForVariable($item, 'issued'),
            'issued-season-name', 'date-season-name' => $this->dateSeasonNameForVariable($item, 'issued'),
            'accessed-season' => $this->dateSeasonForVariable($item, 'accessed'),
            'accessed-season-name' => $this->dateSeasonNameForVariable($item, 'accessed'),
            'available-season', 'available-date-season' => $this->dateSeasonForVariable($item, 'available-date'),
            'available-season-name', 'available-date-season-name' => $this->dateSeasonNameForVariable($item, 'available-date'),
            'accepted-season', 'accepted-date-season' => $this->dateSeasonForVariable($item, 'accepted-date'),
            'accepted-season-name', 'accepted-date-season-name' => $this->dateSeasonNameForVariable($item, 'accepted-date'),
            'revised-season', 'revised-date-season', 'revision-season', 'revision-date-season' => $this->dateSeasonForVariable($item, 'revised-date'),
            'revised-season-name', 'revised-date-season-name', 'revision-season-name', 'revision-date-season-name' => $this->dateSeasonNameForVariable($item, 'revised-date'),
            'submitted-season', 'submitted-date-season' => $this->dateSeasonForVariable($item, 'submitted'),
            'submitted-season-name', 'submitted-date-season-name' => $this->dateSeasonNameForVariable($item, 'submitted'),
            'event-date-season' => $this->dateSeasonForVariable($item, 'event-date'),
            'event-date-season-name' => $this->dateSeasonNameForVariable($item, 'event-date'),
            'original-date-season' => $this->dateSeasonForVariable($item, 'original-date'),
            'original-date-season-name' => $this->dateSeasonNameForVariable($item, 'original-date'),
            'reprint-date-season' => $this->dateSeasonForVariable($item, 'reprint-date'),
            'reprint-date-season-name' => $this->dateSeasonNameForVariable($item, 'reprint-date'),
            'label-date-season', 'labeldate-season' => $this->dateSeasonForVariable($item, 'label-date'),
            'label-date-season-name', 'labeldate-season-name' => $this->dateSeasonNameForVariable($item, 'label-date'),
            'related' => $this->relatedSummaryValues($item),
            'related-summary' => $this->relatedSummary($item),
            'related-keys' => implode(', ', is_array($item['relatedKeys'] ?? null) ? $item['relatedKeys'] : []),
            'related-type', 'relatedtype' => (string) ($item['relatedType'] ?? ''),
            'related-string', 'relatedstring' => (string) ($item['relatedString'] ?? ''),
            'related-options', 'relatedoptions' => implode(', ', is_array($item['relatedOptions'] ?? null) ? $item['relatedOptions'] : []),
            'missing-related-keys' => implode(', ', is_array($item['missingRelatedKeys'] ?? null) ? $item['missingRelatedKeys'] : []),
            'crossref' => $this->crossrefSummaryValues($item),
            'crossref-summary' => $this->crossrefSummary($item),
            'crossref-keys' => implode(', ', is_array($item['crossrefKeys'] ?? null) ? $item['crossrefKeys'] : []),
            'missing-crossref-keys' => implode(', ', is_array($item['missingCrossrefKeys'] ?? null) ? $item['missingCrossrefKeys'] : []),
            'xref' => $this->xrefSummaryValues($item),
            'xref-summary' => $this->xrefSummary($item),
            'xref-keys' => implode(', ', is_array($item['xrefKeys'] ?? null) ? $item['xrefKeys'] : []),
            'missing-xref-keys' => implode(', ', is_array($item['missingXrefKeys'] ?? null) ? $item['missingXrefKeys'] : []),
            'original-publisher', 'originalpublisher', 'origpublisher' => (string) ($item['originalPublisher'] ?? ''),
            'original-publisher-place', 'originalpublisherplace', 'origlocation', 'origaddress' => (string) ($item['originalPublisherPlace'] ?? ''),
            'original-publisher-list', 'originalpublisherlist', 'origpublisherlist' => implode('; ', is_array($item['originalPublisherList'] ?? null) ? $item['originalPublisherList'] : []),
            'original-publisher-place-list', 'originalpublisherplacelist', 'origlocationlist', 'origaddresslist' => implode('; ', is_array($item['originalPublisherPlaceList'] ?? null) ? $item['originalPublisherPlaceList'] : []),
            'original-language', 'originallanguage', 'origlanguage' => (string) ($item['originalLanguage'] ?? ''),
            'original-language-list', 'originallanguagelist', 'origlanguagelist' => implode('; ', is_array($item['originalLanguageList'] ?? null) ? $item['originalLanguageList'] : []),
            'original-edition', 'originaledition', 'origedition', 'orig-edition' => (string) ($item['originalEdition'] ?? ''),
            'original-isbn', 'originalisbn', 'origisbn', 'orig-isbn' => (string) ($item['originalIsbn'] ?? ''),
            'original-issn', 'originalissn', 'origissn', 'orig-issn' => (string) ($item['originalIssn'] ?? ''),
            'original-doi', 'originaldoi', 'origdoi', 'orig-doi' => (string) ($item['originalDoi'] ?? ''),
            'original-url', 'originalurl', 'origurl', 'orig-url' => (string) ($item['originalUrl'] ?? ''),
            'keyword', 'keywords' => implode(', ', is_array($item['keywords'] ?? null) ? $item['keywords'] : []),
            'keyword-list', 'keywordlist' => (string) ($item['keywordSummary'] ?? ''),
            'keyword-summary', 'keywords-summary' => (string) ($item['keywordSummary'] ?? ''),
            'issued', 'issued-date', 'issueddate', 'date' => $this->renderDateVariable($item['issuedDate'] ?? null, $scope, 'issued'),
            'year-suffix' => (string) ($item['yearSuffix'] ?? ($citation instanceof AstNode ? $citation->attr('cslYearSuffix', '') : '')),
            'available-date', 'availabledate' => $this->renderDateVariable($item['availableDate'] ?? null, $scope, 'available-date'),
            'accepted-date', 'accepteddate', 'date-accepted', 'dateaccepted' => $this->renderDateVariable($item['acceptedDate'] ?? null, $scope, 'accepted-date'),
            'revised-date', 'reviseddate', 'revision-date', 'revisiondate', 'date-revised', 'daterevised' => $this->renderDateVariable($item['revisedDate'] ?? null, $scope, 'revised-date'),
            'submitted', 'submitted-date', 'submitteddate' => $this->renderDateVariable($item['submittedDate'] ?? null, $scope, 'submitted'),
            'event-date', 'eventdate' => $this->renderDateVariable($item['eventDate'] ?? null, $scope, 'event-date'),
            'label-date', 'labeldate' => $this->renderDateVariable($item['labelDate'] ?? null, $scope, 'label-date'),
            'accessed', 'accessed-date', 'accesseddate', 'access-date', 'accessdate',
            'url-date', 'urldate', 'visited', 'lastchecked', 'lastaccessed' => $this->renderDateVariable($item['accessedDate'] ?? null, $scope, 'accessed'),
            'original-date', 'originaldate', 'origdate' => $this->renderDateVariable($item['originalDate'] ?? null, $scope, 'original-date'),
            'reprint-date', 'reprintdate' => $this->renderDateVariable($item['reprintDate'] ?? null, $scope, 'reprint-date'),
            'short-author' => $this->renderNamesElement(['variable' => 'short-author'], $item, $scope),
            'short-editor' => $this->renderNamesElement(['variable' => 'short-editor'], $item, $scope),
            'author' => $this->renderNamesElement(['variable' => 'author'], $item, $scope),
            'editor' => $this->renderNamesElement(['variable' => 'editor'], $item, $scope),
            'holder' => $this->renderNamesElement(['variable' => 'holder'], $item, $scope),
            'translator' => $this->renderNamesElement(['variable' => 'translator'], $item, $scope),
            'chair' => $this->renderNamesElement(['variable' => 'chair'], $item, $scope),
            'container-author' => $this->renderNamesElement(['variable' => 'container-author'], $item, $scope),
            'collection-editor' => $this->renderNamesElement(['variable' => 'collection-editor'], $item, $scope),
            'composer' => $this->renderNamesElement(['variable' => 'composer'], $item, $scope),
            'contributor' => $this->renderNamesElement(['variable' => 'contributor'], $item, $scope),
            'editor-translator' => $this->renderNamesElement(['variable' => 'editor-translator'], $item, $scope),
            'executive-producer' => $this->renderNamesElement(['variable' => 'executive-producer'], $item, $scope),
            'event-organizer', 'organizer' => $this->renderNamesElement(['variable' => 'event-organizer'], $item, $scope),
            'guest' => $this->renderNamesElement(['variable' => 'guest'], $item, $scope),
            'host' => $this->renderNamesElement(['variable' => 'host'], $item, $scope),
            'narrator' => $this->renderNamesElement(['variable' => 'narrator'], $item, $scope),
            'original-author' => $this->renderNamesElement(['variable' => 'original-author'], $item, $scope),
            'performer' => $this->renderNamesElement(['variable' => 'performer'], $item, $scope),
            'producer' => $this->renderNamesElement(['variable' => 'producer'], $item, $scope),
            'recipient' => $this->renderNamesElement(['variable' => 'recipient'], $item, $scope),
            'script-writer' => $this->renderNamesElement(['variable' => 'script-writer'], $item, $scope),
            'compiler' => $this->renderNamesElement(['variable' => 'compiler'], $item, $scope),
            'curator' => $this->renderNamesElement(['variable' => 'curator'], $item, $scope),
            'director' => $this->renderNamesElement(['variable' => 'director'], $item, $scope),
            'editorial-director' => $this->renderNamesElement(['variable' => 'editorial-director'], $item, $scope),
            'illustrator' => $this->renderNamesElement(['variable' => 'illustrator'], $item, $scope),
            'interviewer' => $this->renderNamesElement(['variable' => 'interviewer'], $item, $scope),
            'reviewed-author' => $this->renderNamesElement(['variable' => 'reviewed-author'], $item, $scope),
            'series-creator' => $this->renderNamesElement(['variable' => 'series-creator'], $item, $scope),
            'redactor' => $this->renderNamesElement(['variable' => 'redactor'], $item, $scope),
            'founder' => $this->renderNamesElement(['variable' => 'founder'], $item, $scope),
            'continuator' => $this->renderNamesElement(['variable' => 'continuator'], $item, $scope),
            'reviser' => $this->renderNamesElement(['variable' => 'reviser'], $item, $scope),
            'collaborator' => $this->renderNamesElement(['variable' => 'collaborator'], $item, $scope),
            'commentator' => $this->renderNamesElement(['variable' => 'commentator'], $item, $scope),
            'annotator' => $this->renderNamesElement(['variable' => 'annotator'], $item, $scope),
            'introduction' => $this->renderNamesElement(['variable' => 'introduction'], $item, $scope),
            'foreword' => $this->renderNamesElement(['variable' => 'foreword'], $item, $scope),
            'afterword' => $this->renderNamesElement(['variable' => 'afterword'], $item, $scope),
            'editorial-role-summary' => implode(' ', $this->bibliographyRoleNameParts($item)),
            default => $this->rawVariableValue($item, $variable),
        };
    }

    private function textVariableAcceptsNumberForm(string $variable): bool
    {
        $variable = self::pageExtentCountVariableAlias($variable);

        return in_array($variable, [
            'citation-number',
            'first-reference-note-number',
            'locator',
            'page',
            'page-first',
            'number',
            'article-number',
            'edition',
            'volume',
            'issue',
            'issue-number',
            'issuenumber',
            'chapter-number',
            'number-of-pages',
            'number-of-volumes',
            'collection-number',
            'series-number',
            'seriesnumber',
            'original-collection-number',
            'originalcollectionnumber',
            'origseriesnumber',
            'orig-series-number',
            'original-series-number',
            'originalseriesnumber',
            'section',
            'part-number',
            'part',
            'printing-number',
            'supplement',
            'supplement-number',
            'version',
        ], true);
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function pageExtentCountVariableValue(array $item, string $variable): ?string
    {
        return match (self::pageExtentCountVariableAlias($variable)) {
            'number-of-pages' => (string) ($item['numberOfPages'] ?? ''),
            'number-of-volumes' => (string) ($item['numberOfVolumes'] ?? ''),
            default => null,
        };
    }

    private static function pageExtentCountVariableAlias(string $variable): string
    {
        $normalized = str_replace(['_', ' '], '-', strtolower(trim($variable)));

        return match ($normalized) {
            'number-of-pages', 'numberofpages', 'pagetotal', 'page-total', 'num-pages', 'numpages', 'total-pages', 'totalpages' => 'number-of-pages',
            'number-of-volumes', 'numberofvolumes', 'volumes', 'volume-count', 'volumecount', 'num-volumes', 'numvolumes' => 'number-of-volumes',
            default => $normalized,
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexCustomFieldValue(array $item, string $field): string
    {
        $fields = $item['biblatexCustomFields'] ?? [];
        if (!is_array($fields)) {
            return '';
        }

        $value = $fields[$field] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexCustomListValue(array $item, string $field): string
    {
        $lists = $item['biblatexCustomLists'] ?? [];
        if (!is_array($lists)) {
            return '';
        }

        $values = $lists[$field] ?? [];
        if (!is_array($values)) {
            return '';
        }

        return implode('; ', array_values(array_filter(
            array_map(
                static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
                $values
            ),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexCustomNameValue(array $item, string $field): string
    {
        $namesByField = $item['biblatexCustomNames'] ?? [];
        if (!is_array($namesByField)) {
            return '';
        }

        $names = $namesByField[$field] ?? [];
        if (!is_array($names)) {
            return '';
        }

        return implode('; ', array_values(array_filter(
            array_map(
                static fn (mixed $name): string => is_array($name) ? self::biblatexCustomNameDisplay($name) : '',
                $names
            ),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderTextVariableValue(array $item, string $variable, string $form, string $scope, ?AstNode $citation = null): string
    {
        $normalizedForm = strtolower(trim($form));
        $normalizedVariable = strtolower(trim($variable));

        if (
            in_array($normalizedForm, ['numeric', 'ordinal', 'long-ordinal', 'roman'], true)
            && $this->textVariableAcceptsNumberForm($normalizedVariable)
        ) {
            $gender = in_array($normalizedForm, ['ordinal', 'long-ordinal'], true)
                ? $this->ordinalGenderForNumberVariable($normalizedVariable, $item, $citation)
                : '';

            return $this->formatCslNumber($this->renderVariableValue($item, $variable, $scope, $citation), $normalizedForm, $gender);
        }

        if ($normalizedForm !== 'short') {
            return $this->renderVariableValue($item, $variable, $scope, $citation);
        }

        $directShort = match ($normalizedVariable) {
            'title' => (string) ($item['shortTitle'] ?? ''),
            'container-title', 'containertitle', 'container', 'container-title-text', 'containertitletext', 'book-title', 'booktitle', 'journal-title', 'journaltitle', 'journal', 'publication-title', 'publicationtitle' => (string) ($item['containerTitleShort'] ?? ''),
            'collection-title', 'collectiontitle', 'collection', 'collection-title-text', 'collectiontitletext', 'series', 'series-title', 'seriestitle', 'series-title-text', 'seriestitletext' => (string) ($item['collectionTitleShort'] ?? ''),
            'volume-title', 'volumetitle', 'volume-title-text', 'volumetitletext' => (string) ($item['volumeTitleShort'] ?? ''),
            default => '',
        };
        if ($directShort !== '') {
            return $directShort;
        }

        $abbreviation = $this->shortFormAbbreviation($item, $normalizedVariable, $scope, $citation);
        if ($abbreviation !== null) {
            return $abbreviation;
        }

        return $this->renderVariableValue($item, $variable, $scope, $citation);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function shortFormAbbreviation(array $item, string $variable, string $scope, ?AstNode $citation = null): ?string
    {
        if ($this->cslAbbreviations === []) {
            return null;
        }

        $longValue = trim($this->renderVariableValue($item, $variable, $scope, $citation));
        if ($longValue === '') {
            return null;
        }

        foreach ($this->abbreviationCategoriesForVariable($variable) as $category) {
            $abbreviation = $this->cslAbbreviations[$category][$longValue] ?? null;
            if (is_string($abbreviation) && $abbreviation !== '') {
                return $abbreviation;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function abbreviationCategoriesForVariable(string $variable): array
    {
        $variable = str_replace('_', '-', strtolower(trim($variable)));

        return match ($variable) {
            'publisher-place', 'publisherplace', 'publisher-location', 'publisherlocation', 'publication-place', 'publicationplace', 'pubplace', 'address', 'location' => ['publisher-place', 'place'],
            'archive-place', 'event-place', 'original-publisher-place' => [$variable, 'place'],
            'publisher', 'institution', 'organization', 'school', 'original-publisher', 'authority', 'authority-list', 'authoritylist', 'issuing-authority', 'issuingauthority', 'issuing-authority-list', 'issuingauthoritylist' => [$variable, 'institution'],
            default => [$variable],
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationNumberValue(array $item, ?AstNode $citation): string
    {
        if ($citation instanceof AstNode) {
            $number = trim((string) $citation->attr('cslCitationNumber', ''));
            if ($number !== '') {
                return $number;
            }
        }

        $number = trim((string) ($item['citationNumber'] ?? ''));
        if ($number !== '') {
            return $number;
        }

        return $this->citationNumberForId((string) ($item['id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function firstReferenceNoteNumberValue(?AstNode $citation, array $item = []): string
    {
        if ($citation instanceof AstNode) {
            foreach (['cslFirstReferenceNoteNumber', 'firstReferenceNoteNumber'] as $attribute) {
                $number = self::positiveIntegerString($citation->attr($attribute, ''));
                if ($number !== '') {
                    return $number;
                }
            }
        }

        foreach (['firstReferenceNoteNumber', 'cslFirstReferenceNoteNumber', 'first-reference-note-number'] as $field) {
            $number = self::positiveIntegerString($item[$field] ?? null);
            if ($number !== '') {
                return $number;
            }
        }

        return '';
    }

    private static function positiveIntegerString(mixed $value): string
    {
        if (is_int($value) && $value >= 1) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1 && (int) $value >= 1) {
            return (string) ((int) $value);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsPresent(array $item, string $variable, string $scope, ?AstNode $citation = null): bool
    {
        $normalized = strtolower(trim($variable));
        if ($normalized === '') {
            return false;
        }

        if ($normalized === 'locator') {
            return $this->citationLocatorParts($citation)['value'] !== '';
        }

        if ($normalized === 'citation-number') {
            return $this->citationNumberValue($item, $citation) !== '';
        }

        if ($normalized === 'first-reference-note-number') {
            return $this->firstReferenceNoteNumberValue($citation, $item) !== '';
        }

        if ($normalized === 'year-suffix') {
            return $this->renderVariableValue($item, $variable, $scope, $citation) !== '';
        }

        if (in_array($normalized, ['short-author', 'short-editor', 'author', 'editor', 'holder', 'authority', 'authority-list', 'authoritylist', 'issuing-authority', 'issuingauthority', 'issuing-authority-list', 'issuingauthoritylist', 'translator', 'chair', 'container-author', 'collection-editor', 'series-creator', 'composer', 'contributor', 'editor-translator', 'executive-producer', 'event-organizer', 'organizer', 'guest', 'host', 'narrator', 'original-author', 'performer', 'producer', 'recipient', 'script-writer', 'compiler', 'curator', 'director', 'editorial-director', 'illustrator', 'interviewer', 'reviewed-author', 'redactor', 'founder', 'continuator', 'reviser', 'collaborator', 'commentator', 'annotator', 'introduction', 'foreword', 'afterword', 'namea', 'nameb', 'namec'], true)) {
            return $this->namesForRenderingVariable($item, $normalized) !== [];
        }

        if (in_array($normalized, ['issued', 'issued-date', 'issueddate', 'date', 'accessed', 'accessed-date', 'accesseddate', 'access-date', 'accessdate', 'url-date', 'urldate', 'visited', 'lastchecked', 'lastaccessed', 'available-date', 'availabledate', 'accepted-date', 'accepteddate', 'date-accepted', 'dateaccepted', 'revised-date', 'reviseddate', 'revision-date', 'revisiondate', 'date-revised', 'daterevised', 'event-date', 'eventdate', 'label-date', 'labeldate', 'original-date', 'originaldate', 'origdate', 'reprint-date', 'reprintdate', 'submitted', 'submitted-date', 'submitteddate'], true)) {
            $date = $this->dateVariableForRendering($item, $normalized);
            if (!is_array($date)) {
                return false;
            }

            return (isset($date['parts']) && is_array($date['parts']) && $date['parts'] !== [])
                || (string) ($date['display'] ?? '') !== ''
                || (string) ($date['literal'] ?? '') !== '';
        }

        return $this->renderVariableValue($item, $variable, $scope, $citation) !== '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsCreator(array $item, string $variable): bool
    {
        $normalized = str_replace(['_', ' '], '-', strtolower(trim($variable)));
        if ($normalized === '') {
            return false;
        }

        return $this->namesForRenderingVariable($item, $normalized) !== [];
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $aliases
     */
    private function rawAliasedVariableValue(array $item, string $variable, array $aliases): string
    {
        $raw = $item['raw'] ?? [];
        if (!is_array($raw)) {
            return '';
        }

        $base = trim($variable);
        $lowerBase = strtolower($base);
        if (str_ends_with($base, '-raw') || str_ends_with($base, '_raw')) {
            $base = substr($base, 0, -4);
        } elseif (str_ends_with($base, 'Raw') || str_ends_with($lowerBase, 'raw')) {
            $base = substr($base, 0, -3);
        }

        $candidates = array_values(array_unique([
            $base,
            str_replace('_', '-', $base),
            str_replace('-', '', $base),
            strtolower($base),
            strtoupper($base),
        ]));
        foreach ($candidates as $key) {
            $value = $raw[$key] ?? null;
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        $normalizedBase = str_replace(['-', '_', ' '], '', strtolower($base));
        foreach ($aliases as $alias) {
            if ($normalizedBase !== str_replace(['-', '_', ' '], '', strtolower((string) $alias))) {
                continue;
            }

            $value = $raw[$alias] ?? null;
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function rawVariableValue(array $item, string $variable): string
    {
        $raw = $item['raw'] ?? [];
        if (!is_array($raw)) {
            return '';
        }

        foreach ([$variable, strtolower($variable), strtoupper($variable)] as $key) {
            $value = $raw[$key] ?? null;
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourceFileSummary(array $item): string
    {
        return implode('; ', array_values(array_filter(
            array_map(
                fn (array $file): string => $this->sourceFileDisplay($file),
                $this->sourceFilesForRendering($item)
            ),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourceFilePaths(array $item): string
    {
        return $this->sourceFileListValue($item, 'path');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourceFileLabels(array $item): string
    {
        return $this->sourceFileListValue($item, 'label');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourceFileMediaTypes(array $item): string
    {
        return $this->sourceFileListValue($item, 'mediaType');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourceFileDiagnosticSummary(array $item): string
    {
        return implode('; ', array_values(array_filter(
            array_map(
                fn (array $diagnostic): string => $this->sourceFileDiagnosticDisplay($diagnostic),
                $this->sourceFileDiagnosticsForRendering($item)
            ),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourceFileDiagnosticReasons(array $item): string
    {
        return $this->sourceFileDiagnosticListValue($item, 'reason');
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{label:string, path:string, mediaType:string}>
     */
    private function sourceFilesForRendering(array $item): array
    {
        $files = $item['sourceFiles'] ?? [];
        if (!is_array($files)) {
            return [];
        }

        $normalized = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $path = trim((string) ($file['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $normalized[] = [
                'label' => trim((string) ($file['label'] ?? '')),
                'path' => $path,
                'mediaType' => trim((string) ($file['mediaType'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>
     */
    private function sourceFileDiagnosticsForRendering(array $item): array
    {
        $diagnostics = $item['sourceFileDiagnostics'] ?? [];
        if (!is_array($diagnostics)) {
            return [];
        }

        $normalized = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $reason = trim((string) ($diagnostic['reason'] ?? ''));
            if ($reason === '') {
                continue;
            }

            $normalized[] = [
                'label' => trim((string) ($diagnostic['label'] ?? '')),
                'path' => trim((string) ($diagnostic['path'] ?? '')),
                'mediaType' => trim((string) ($diagnostic['mediaType'] ?? '')),
                'reason' => $reason,
                'importable' => ($diagnostic['importable'] ?? false) === true,
            ];
        }

        return $normalized;
    }

    /**
     * @param array{label:string, path:string, mediaType:string} $file
     */
    private function sourceFileDisplay(array $file): string
    {
        $path = trim($file['path']);
        if ($path === '') {
            return '';
        }

        $label = trim($file['label']);
        $mediaType = trim($file['mediaType']);
        $display = $label !== '' ? $label . ': ' . $path : $path;

        return $mediaType !== '' ? $display . ' (' . $mediaType . ')' : $display;
    }

    /**
     * @param array{label:string, path:string, mediaType:string, reason:string, importable:bool} $diagnostic
     */
    private function sourceFileDiagnosticDisplay(array $diagnostic): string
    {
        $reason = trim($diagnostic['reason']);
        if ($reason === '') {
            return '';
        }

        $label = trim($diagnostic['label']);
        $path = trim($diagnostic['path']);
        $display = $label !== '' ? $label . ': ' . $reason : $reason;

        return $path !== '' ? $display . ' (' . $path . ')' : $display;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourceFileListValue(array $item, string $key): string
    {
        return implode('; ', $this->uniqueSourceFileValues($this->sourceFilesForRendering($item), $key));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourceFileDiagnosticListValue(array $item, string $key): string
    {
        return implode('; ', $this->uniqueSourceFileValues($this->sourceFileDiagnosticsForRendering($item), $key));
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<string>
     */
    private function uniqueSourceFileValues(array $records, string $key): array
    {
        $values = [];
        foreach ($records as $record) {
            $value = trim((string) ($record[$key] ?? ''));
            if ($value !== '' && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function formatCslLocatorRanges(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return preg_replace('/(?<=[\p{L}\p{N}])\s*[-\x{2010}-\x{2015}]\s*(?=[\p{L}\p{N}])/u', "\u{2013}", $value) ?? $value;
    }

    /**
     * @param array{label:string, value:string} $locator
     */
    private function formatCslLocatorValue(array $locator): string
    {
        $value = $locator['value'];
        if ($value === '') {
            return '';
        }

        if ($locator['label'] === 'page' && $this->style->pageRangeFormat() !== '') {
            return $this->formatCslPageRanges($value);
        }

        return $this->formatCslLocatorRanges($value);
    }

    private function citationLocatorLabelValue(?AstNode $citation): string
    {
        $parts = $this->citationLocatorParts($citation);

        return $parts['value'] === '' ? '' : $parts['label'];
    }

    private function citationLocatorSourceClass(?AstNode $citation): string
    {
        if (!$citation instanceof AstNode) {
            return '';
        }

        $parts = $this->citationLocatorParts($citation);
        if ($parts['value'] === '' && $this->citationLocatorDiagnosticsForCitation($citation) === []) {
            return '';
        }

        $explicitValue = $this->inlineValue($citation->attr('locatorValue', ''));
        if ($explicitValue !== '') {
            return 'explicit';
        }

        $rawLocator = $this->inlineValue($citation->attr('locator', ''));
        if ($rawLocator === '') {
            $rawLocator = $this->inlineValue($citation->attr('suffix', ''));
        }
        if ($rawLocator === '') {
            return 'none';
        }

        $normalizedRaw = trim(preg_replace('/\s+/u', ' ', $rawLocator) ?? $rawLocator);
        if (
            $parts['label'] === 'page'
            && $parts['value'] === $normalizedRaw
            && $this->citationLocatorUnsupportedLabelCandidate($normalizedRaw) !== ''
        ) {
            return 'defaulted';
        }

        return $parts['label'] === 'page' && $parts['value'] === $normalizedRaw ? 'unlabeled' : 'inferred';
    }

    private function citationLocatorUnsupportedLabelCandidate(string $locator): string
    {
        if (preg_match('/^([\p{L}][\p{L}\p{N}._-]{0,31})\s+\S/u', trim($locator), $match) !== 1) {
            return '';
        }

        $candidate = $this->normalizedLocatorLabel((string) $match[1]);

        return $this->supportedCitationLocatorLabel($candidate) ? '' : $candidate;
    }

    private function citationLocatorRawValue(?AstNode $citation): string
    {
        if (!$citation instanceof AstNode) {
            return '';
        }

        $rawLocator = $this->inlineValue($citation->attr('locator', ''));
        if ($rawLocator !== '') {
            return $rawLocator;
        }

        $explicitValue = $this->inlineValue($citation->attr('locatorValue', ''));
        if ($explicitValue !== '') {
            return $explicitValue;
        }

        return $this->inlineValue($citation->attr('suffix', ''));
    }

    private function citationPrefixValue(?AstNode $citation): string
    {
        return $citation instanceof AstNode ? $this->citationPrefix($citation) : '';
    }

    private function citationSuffixValue(?AstNode $citation): string
    {
        return $citation instanceof AstNode ? $this->citationSuffix($citation) : '';
    }

    /**
     * @return array{cslCitationPrefix?:string, cslCitationSuffix?:string, cslCitationAffixSummary?:string}
     */
    private function citationAffixReviewAttrs(AstNode $citation): array
    {
        $attrs = [];
        $prefix = $this->citationPrefix($citation);
        if ($prefix !== '') {
            $attrs['cslCitationPrefix'] = $prefix;
        }

        $suffix = $this->citationSuffix($citation);
        if ($suffix !== '') {
            $attrs['cslCitationSuffix'] = $suffix;
        }

        $summary = $this->citationAffixSummaryForCitation($citation);
        if ($summary !== '') {
            $attrs['cslCitationAffixSummary'] = $summary;
        }

        return $attrs;
    }

    private function citationAffixSummaryForCitation(?AstNode $citation): string
    {
        if (!$citation instanceof AstNode) {
            return '';
        }

        $parts = [];
        $prefix = $this->citationPrefix($citation);
        if ($prefix !== '') {
            $parts[] = 'prefix: ' . $prefix;
        }

        $suffix = $this->citationSuffix($citation);
        if ($suffix !== '') {
            $parts[] = 'suffix: ' . $suffix;
        }

        return implode('; ', $parts);
    }

    /**
     * @param list<AstNode> $citations
     * @return list<array{id:string, source:string, prefix:string, suffix:string, summary:string}>
     */
    private function citationAffixesForCitations(array $citations): array
    {
        $rows = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                continue;
            }

            $summary = $this->citationAffixSummaryForCitation($citation);
            if ($summary === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) $citation->attr('id', ''),
                'source' => $this->sourceCitationText($citation),
                'prefix' => $this->citationPrefix($citation),
                'suffix' => $this->citationSuffix($citation),
                'summary' => $summary,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{id:string, source:string, prefix:string, suffix:string, summary:string}> $rows
     */
    private function citationAffixSummaryForRows(array $rows): string
    {
        return implode('; ', array_values(array_filter(
            array_map(
                static function (array $row): string {
                    $label = $row['id'] !== '' ? $row['id'] : $row['source'];

                    return $label === '' ? $row['summary'] : $label . ' [' . $row['summary'] . ']';
                },
                $rows
            ),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>
     */
    private function citationLocatorReviewAttrs(AstNode $citation, array $diagnostics): array
    {
        if ($diagnostics === []) {
            return [];
        }

        $attrs = [
            'cslLocatorDiagnostics' => $diagnostics,
            'cslLocatorDiagnosticSummary' => $this->citationLocatorDiagnosticSummaryForDiagnostics($diagnostics),
            'cslLocatorDiagnosticReasons' => $this->citationLocatorDiagnosticReasonsForDiagnostics($diagnostics),
        ];
        $parts = $this->citationLocatorParts($citation);
        if ($parts['value'] !== '') {
            $attrs['cslLocator'] = [
                'label' => $parts['label'],
                'value' => $parts['value'],
                'formattedValue' => $this->formatCslLocatorValue($parts),
                'raw' => $this->citationLocatorRawValue($citation),
                'source' => (string) ($diagnostics[0]['source'] ?? $this->sourceCitationText($citation)),
                'sourceClass' => $this->citationLocatorSourceClass($citation),
            ];
        }

        return $attrs;
    }

    private function citationLocatorDiagnosticSummary(?AstNode $citation): string
    {
        return $citation instanceof AstNode
            ? $this->citationLocatorDiagnosticSummaryForDiagnostics($this->citationLocatorDiagnosticsForCitation($citation))
            : '';
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private function citationLocatorDiagnosticSummaryForDiagnostics(array $diagnostics): string
    {
        return implode('; ', array_values(array_filter(
            array_map(fn (array $diagnostic): string => $this->citationLocatorDiagnosticDisplay($diagnostic), $diagnostics),
            static fn (string $value): bool => $value !== ''
        )));
    }

    private function citationLocatorDiagnosticReasons(?AstNode $citation): string
    {
        return $citation instanceof AstNode
            ? $this->citationLocatorDiagnosticReasonsForDiagnostics($this->citationLocatorDiagnosticsForCitation($citation))
            : '';
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private function citationLocatorDiagnosticReasonsForDiagnostics(array $diagnostics): string
    {
        return implode('; ', array_values(array_unique(array_filter(
            array_map(
                static fn (array $diagnostic): string => trim((string) ($diagnostic['reason'] ?? '')),
                $diagnostics
            ),
            static fn (string $value): bool => $value !== ''
        ))));
    }

    /**
     * @param array<string, mixed> $diagnostic
     */
    private function citationLocatorDiagnosticDisplay(array $diagnostic): string
    {
        $label = trim((string) ($diagnostic['locatorLabel'] ?? ''));
        $value = trim((string) ($diagnostic['locatorValue'] ?? ''));
        $formatted = $value === '' ? '' : $this->formatCslLocatorValue(['label' => $label, 'value' => $value]);
        $display = trim($label . ' ' . $formatted);
        $reason = trim((string) ($diagnostic['reason'] ?? ''));
        if ($display === '') {
            $display = $reason;
        }
        if ($display === '') {
            return '';
        }

        $details = [];
        $rawLocator = trim((string) ($diagnostic['rawLocator'] ?? ''));
        if ($rawLocator !== '' && $rawLocator !== $formatted) {
            $details[] = 'raw: ' . $rawLocator;
        }
        $rawLabel = trim((string) ($diagnostic['rawLocatorLabel'] ?? ''));
        if ($rawLabel !== '' && $this->normalizedLocatorLabel($rawLabel) !== $label) {
            $details[] = 'label: ' . $rawLabel;
        }
        if ($reason !== '') {
            $severity = trim((string) ($diagnostic['severity'] ?? ''));
            $details[] = $severity === '' ? $reason : $reason . '/' . $severity;
        }

        return $details === [] ? $display : $display . ' [' . implode('; ', $details) . ']';
    }

    private function formatCslPageRanges(string $value): string
    {
        $format = $this->style->pageRangeFormat();
        if ($value === '' || $format === '') {
            return $value;
        }

        $delimiter = $this->style->term('page-range-delimiter');
        if ($delimiter === '' || $delimiter === 'page-range-delimiter') {
            $delimiter = "\u{2013}";
        }

        return preg_replace_callback(
            '/(?<![\p{L}\p{N}])(\d+)\s*[-\x{2010}-\x{2015}]\s*(\d+)(?![\p{L}\p{N}])/u',
            fn (array $matches): string => $this->formatCslPageRangePair((string) $matches[1], (string) $matches[2], $format, $delimiter),
            $value
        ) ?? $value;
    }

    private function formatCslPageRangePair(string $startText, string $endText, string $format, string $delimiter): string
    {
        if ($startText === '' || $endText === '') {
            return $startText . $delimiter . $endText;
        }

        $start = (int) $startText;
        $end = $this->expandedCslPageRangeEnd($start, $endText);
        if ($end === null || $end <= $start) {
            return $startText . $delimiter . $endText;
        }

        $expandedEndText = (string) $end;

        return match ($format) {
            'expanded' => $startText . $delimiter . $expandedEndText,
            'minimal' => $startText . $delimiter . $this->collapsedCslPageRangeEnd($startText, $expandedEndText, 1),
            'minimal-two' => $startText . $delimiter . $this->collapsedCslPageRangeEnd($startText, $expandedEndText, 2),
            'chicago' => $startText . $delimiter . $this->chicagoCslPageRangeEnd($start, $startText, $end, $expandedEndText),
            default => $startText . $delimiter . $expandedEndText,
        };
    }

    private function expandedCslPageRangeEnd(int $start, string $endText): ?int
    {
        if ($endText === '' || preg_match('/^\d+$/', $endText) !== 1) {
            return null;
        }

        $end = (int) $endText;
        $digits = strlen($endText);
        if ($digits >= strlen((string) $start)) {
            return $end;
        }

        $power = 10 ** $digits;
        $candidate = intdiv($start, $power) * $power + $end;
        while ($candidate < $start) {
            $candidate += $power;
        }

        return $candidate;
    }

    private function collapsedCslPageRangeEnd(string $startText, string $endText, int $minimumDigits): string
    {
        $minimumDigits = max(1, $minimumDigits);
        $limit = min(strlen($startText), strlen($endText));
        $offset = 0;
        while ($offset < $limit && $startText[$offset] === $endText[$offset]) {
            $offset++;
        }

        $changed = substr($endText, $offset);
        if ($changed === '') {
            return $endText;
        }

        if (strlen($changed) < $minimumDigits && strlen($endText) >= $minimumDigits) {
            return substr($endText, -$minimumDigits);
        }

        return $changed;
    }

    private function chicagoCslPageRangeEnd(int $start, string $startText, int $end, string $endText): string
    {
        if ($start < 100 || $start % 100 === 0) {
            return $endText;
        }

        if (strlen((string) $start) === 4 && intdiv($start, 100) !== intdiv($end, 100)) {
            return $endText;
        }

        $lastTwoDigits = $start % 100;
        if ($lastTwoDigits >= 1 && $lastTwoDigits <= 9) {
            return $this->collapsedCslPageRangeEnd($startText, $endText, 1);
        }

        return $this->collapsedCslPageRangeEnd($startText, $endText, 2);
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>
     */
    private function namesForRenderingVariable(array $item, string $variable): array
    {
        return $this->namesForRenderingVariableWithSource($item, $variable)[0];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{0:list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>, 1:string}
     */
    private function namesForRenderingVariableWithSource(array $item, string $variable): array
    {
        $groups = $this->namesForRenderingVariableGroups($item, $variable);
        if ($groups === []) {
            return [[], ''];
        }

        return [$groups[0]['names'], $groups[0]['variable']];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{variable:string, names:list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>}>
     */
    private function namesForRenderingVariableGroups(array $item, string $variable): array
    {
        $variables = preg_split('/\s+/', strtolower(trim($variable))) ?: [];
        if ($variables === []) {
            $variables = ['author', 'editor'];
        }

        $groups = [];
        foreach ($variables as $nameVariable) {
            $names = $this->namesForRenderingSingleVariable($item, $nameVariable);
            if ($names !== []) {
                $groups[] = ['variable' => $nameVariable, 'names' => $names];
            }
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>
     */
    private function namesForRenderingSingleVariable(array $item, string $nameVariable): array
    {
        $names = match ($nameVariable) {
            'short-author' => $item['shortAuthors'] ?? [],
            'short-editor' => $item['shortEditors'] ?? [],
            'author' => $item['authors'] ?? [],
            'editor' => $item['editors'] ?? [],
            'holder' => $item['holders'] ?? [],
            'authority', 'authority-list', 'authoritylist', 'issuing-authority', 'issuingauthority', 'issuing-authority-list', 'issuingauthoritylist' => $item['authorities'] ?? [],
            'translator' => $item['translators'] ?? [],
            'chair' => $item['chairs'] ?? [],
            'container-author' => $item['containerAuthors'] ?? [],
            'collection-editor' => $item['collectionEditors'] ?? [],
            'series-creator' => $item['seriesCreators'] ?? [],
            'composer' => $item['composers'] ?? [],
            'contributor' => $item['contributors'] ?? [],
            'editor-translator' => $item['editorTranslators'] ?? [],
            'executive-producer' => $item['executiveProducers'] ?? [],
            'event-organizer', 'organizer' => $item['eventOrganizers'] ?? [],
            'guest' => $item['guests'] ?? [],
            'host' => $item['hosts'] ?? [],
            'narrator' => $item['narrators'] ?? [],
            'original-author' => $item['originalAuthors'] ?? [],
            'performer' => $item['performers'] ?? [],
            'producer' => $item['producers'] ?? [],
            'recipient' => $item['recipients'] ?? [],
            'script-writer' => $item['scriptWriters'] ?? [],
            'compiler' => $item['compilers'] ?? [],
            'curator' => $item['curators'] ?? [],
            'director' => $item['directors'] ?? [],
            'editorial-director' => $item['editorialDirectors'] ?? [],
            'illustrator' => $item['illustrators'] ?? [],
            'interviewer' => $item['interviewers'] ?? [],
            'reviewed-author' => $item['reviewedAuthors'] ?? [],
            'redactor' => $item['redactors'] ?? [],
            'founder' => $item['founders'] ?? [],
            'continuator' => $item['continuators'] ?? [],
            'reviser' => $item['revisers'] ?? [],
            'collaborator' => $item['collaborators'] ?? [],
            'commentator' => $item['commentators'] ?? [],
            'annotator' => $item['annotators'] ?? [],
            'introduction' => $item['introductionAuthors'] ?? [],
            'foreword' => $item['forewordAuthors'] ?? [],
            'afterword' => $item['afterwordAuthors'] ?? [],
            'namea', 'nameb', 'namec' => is_array($item['biblatexCustomNames'][$nameVariable] ?? null) ? $item['biblatexCustomNames'][$nameVariable] : [],
            default => [],
        };

        return is_array($names) ? $names : [];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateMarkerStatusForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);

        return is_array($date) ? self::dateMarkerStatus($date) : '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateRawForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date)) {
            return '';
        }

        return trim((string) ($date['raw'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateTimeForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date)) {
            return '';
        }

        return trim((string) ($date['time'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateEndTimeForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date)) {
            return '';
        }

        return trim((string) ($date['endTime'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateSeasonForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date) || !is_int($date['season'] ?? null)) {
            return '';
        }

        return (string) $date['season'];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateSeasonNameForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date) || !is_int($date['season'] ?? null)) {
            return '';
        }

        return self::dateSeasonName((int) $date['season']);
    }

    /**
     * @param array<string, mixed> $item
     * @return array{year:?int, parts:list<int>, display:string, literal:string, raw?:string, time?:string, endTime?:string, circa?:bool, uncertain?:bool, season?:int, seasonName?:string, openEnded?:string, rangeParts?:list<list<int>>}|null
     */
    private function dateVariableForRendering(array $item, string $variable): ?array
    {
        $normalized = strtolower(trim($variable));

        return match ($normalized) {
            'issued', 'issued-date', 'issueddate', 'date' => is_array($item['issuedDate'] ?? null) ? $item['issuedDate'] : null,
            'accessed', 'accessed-date', 'accesseddate', 'access-date', 'accessdate',
            'url-date', 'urldate', 'visited', 'lastchecked', 'lastaccessed' => is_array($item['accessedDate'] ?? null) ? $item['accessedDate'] : null,
            'available-date', 'availabledate' => is_array($item['availableDate'] ?? null) ? $item['availableDate'] : null,
            'accepted-date', 'accepteddate', 'date-accepted', 'dateaccepted' => is_array($item['acceptedDate'] ?? null) ? $item['acceptedDate'] : null,
            'revised-date', 'reviseddate', 'revision-date', 'revisiondate', 'date-revised', 'daterevised' => is_array($item['revisedDate'] ?? null) ? $item['revisedDate'] : null,
            'event-date', 'eventdate' => is_array($item['eventDate'] ?? null) ? $item['eventDate'] : null,
            'label-date', 'labeldate' => is_array($item['labelDate'] ?? null) ? $item['labelDate'] : null,
            'original-date', 'originaldate', 'origdate' => is_array($item['originalDate'] ?? null) ? $item['originalDate'] : null,
            'reprint-date', 'reprintdate' => is_array($item['reprintDate'] ?? null) ? $item['reprintDate'] : null,
            'submitted', 'submitted-date', 'submitteddate' => is_array($item['submittedDate'] ?? null) ? $item['submittedDate'] : null,
            default => null,
        };
    }

    /**
     * @param array{year:?int, parts:list<int>, display:string, literal:string, openEnded?:string, rangeParts?:list<list<int>>}|mixed $date
     */
    private function renderDateVariable(mixed $date, string $scope, string $variable): string
    {
        if (!is_array($date)) {
            return $scope === 'citation' && ($variable === 'issued' || $variable === 'date')
                ? $this->style->term('no date')
                : '';
        }

        $display = (string) ($date['display'] ?? '');
        if ($display !== '') {
            return $display;
        }

        $literal = (string) ($date['literal'] ?? '');
        if ($literal !== '') {
            return $literal;
        }

        return $scope === 'citation' && ($variable === 'issued' || $variable === 'date')
            ? $this->style->term('no date')
            : '';
    }

    /**
     * @param array{year:?int, parts:list<int>, display:string, literal:string, openEnded?:string, rangeParts?:list<list<int>>} $date
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}|string> $dateParts
     * @param array<string, mixed> $item
     */
    private function renderSelectedDateParts(array $date, array $dateParts, string $scope, string $variable, string $delimiter, array $item): string
    {
        $rangeParts = is_array($date['rangeParts'] ?? null) ? $date['rangeParts'] : [];
        $singleParts = is_array($date['parts'] ?? null) ? $date['parts'] : [];
        if ($rangeParts === [] && $singleParts === []) {
            return $this->renderDateVariable($date, $scope, $variable);
        }

        $specs = $this->normalizedDatePartRenderingSpecs($dateParts);
        if ($specs === []) {
            return $this->renderDateVariable($date, $scope, $variable);
        }

        $parts = $rangeParts !== [] ? $rangeParts : [$singleParts];
        $values = [];
        foreach ($parts as $rangePart) {
            if (!is_array($rangePart)) {
                continue;
            }

            $value = $this->renderDatePartSequence($rangePart, $specs, $delimiter, $item);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return '';
        }

        $values = array_values(array_unique($values));

        return $this->applyOpenEndedDateBoundary(implode($this->dateRangeDelimiter($parts, $specs), $values), $date);
    }

    /**
     * @param array{year:?int, parts:list<int>, display:string, literal:string, openEnded?:string, rangeParts?:list<list<int>>} $date
     */
    private function renderDateForm(array $date, string $form, string $scope, string $variable, string $datePartsSelection = '', array $datePartOverrides = [], array $item = []): string
    {
        $rangeParts = is_array($date['rangeParts'] ?? null) ? $date['rangeParts'] : [];
        $singleParts = is_array($date['parts'] ?? null) ? $date['parts'] : [];
        if ($rangeParts === [] && $singleParts === []) {
            return $this->renderDateVariable($date, $scope, $variable);
        }

        $parts = $rangeParts !== [] ? $rangeParts : [$singleParts];
        $season = is_int($date['season'] ?? null) && $datePartsSelection !== 'year' ? (int) $date['season'] : null;
        $overrideSpecs = $this->localizedDatePartOverrideSpecs($datePartOverrides);
        $values = [];
        $selectedParts = [];
        foreach ($parts as $dateParts) {
            if (!is_array($dateParts)) {
                continue;
            }

            $dateParts = $this->dateFormPartsForSelection($dateParts, $datePartsSelection);
            $selectedParts[] = $dateParts;
            $value = $form === 'numeric'
                ? $this->renderNumericDateFormParts($dateParts, $season, $overrideSpecs, $item)
                : $this->renderTextDateFormParts($dateParts, $season, $overrideSpecs, $item);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return '';
        }

        $rangeDelimiter = $overrideSpecs !== []
            ? $this->dateRangeDelimiter($selectedParts, array_values($overrideSpecs))
            : '/';

        return $this->applyOpenEndedDateBoundary(implode($rangeDelimiter, array_values(array_unique($values))), $date);
    }

    /**
     * @param list<int> $parts
     * @return list<int>
     */
    private function dateFormPartsForSelection(array $parts, string $datePartsSelection): array
    {
        return match ($datePartsSelection) {
            'year' => array_slice($parts, 0, 1),
            'year-month' => array_slice($parts, 0, 2),
            default => array_slice($parts, 0, 3),
        };
    }

    /**
     * @param array<string, mixed> $date
     */
    private function applyOpenEndedDateBoundary(string $value, array $date): string
    {
        if ($value === '') {
            return '';
        }

        $boundary = (string) ($date['openEnded'] ?? '');
        if ($boundary === 'start') {
            return '/' . $value;
        }

        return $boundary === 'end' ? $value . '/' : $value;
    }

    /**
     * @param list<int> $parts
     */
    private function renderTextDateFormParts(array $parts, ?int $season = null, array $overrideSpecs = [], array $item = []): string
    {
        $year = $parts[0] ?? null;
        if ($year === null) {
            return '';
        }

        $month = $parts[1] ?? null;
        $day = $parts[2] ?? null;
        $yearText = $this->renderLocalizedDatePartFromParts($parts, 'year', 'long', $overrideSpecs, $item);
        if ($season !== null && $month === null) {
            $seasonText = $this->localizedSeasonName($season);

            return $seasonText === '' ? $yearText : $seasonText . ' ' . $yearText;
        }

        if ($month === null) {
            return $yearText;
        }

        $monthText = $this->renderLocalizedDatePartFromParts($parts, 'month', 'long', $overrideSpecs, $item);
        if ($monthText === '') {
            return $yearText;
        }

        if ($day === null) {
            return $monthText . ' ' . $yearText;
        }

        return $monthText . ' ' . $this->renderLocalizedDatePartFromParts($parts, 'day', 'numeric', $overrideSpecs, $item) . ', ' . $yearText;
    }

    /**
     * @param list<int> $parts
     */
    private function renderNumericDateFormParts(array $parts, ?int $season = null, array $overrideSpecs = [], array $item = []): string
    {
        $year = $parts[0] ?? null;
        if ($year === null) {
            return '';
        }

        $month = $parts[1] ?? null;
        $day = $parts[2] ?? null;
        $yearText = $this->renderLocalizedDatePartFromParts($parts, 'year', 'long', $overrideSpecs, $item);
        if ($season !== null && $month === null) {
            $seasonText = $this->localizedSeasonName($season);

            return $seasonText === '' ? $yearText : $seasonText . ' ' . $yearText;
        }

        if ($month === null) {
            return $yearText;
        }

        $monthText = $this->renderLocalizedDatePartFromParts($parts, 'month', 'numeric', $overrideSpecs, $item);
        if ($day === null) {
            return $monthText . '/' . $yearText;
        }

        return $monthText . '/' . $this->renderLocalizedDatePartFromParts($parts, 'day', 'numeric', $overrideSpecs, $item) . '/' . $yearText;
    }

    /**
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}|string> $datePartOverrides
     * @return array<string, array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}>
     */
    private function localizedDatePartOverrideSpecs(array $datePartOverrides): array
    {
        $specs = [];
        foreach ($this->normalizedDatePartRenderingSpecs($datePartOverrides) as $spec) {
            $spec['prefix'] = '';
            $spec['suffix'] = '';
            $specs[$spec['name']] = $spec;
        }

        return $specs;
    }

    /**
     * @param list<int> $parts
     * @param array<string, array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}> $overrideSpecs
     * @param array<string, mixed> $item
     */
    private function renderLocalizedDatePartFromParts(array $parts, string $name, string $defaultForm, array $overrideSpecs, array $item): string
    {
        return $this->renderDatePartValue(
            $parts,
            $this->localizedDatePartSpec($name, $defaultForm, $overrideSpecs),
            $item
        );
    }

    /**
     * @param array<string, array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}> $overrideSpecs
     * @return array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}
     */
    private function localizedDatePartSpec(string $name, string $defaultForm, array $overrideSpecs): array
    {
        $override = $overrideSpecs[$name] ?? [];
        $form = strtolower(trim((string) ($override['form'] ?? '')));

        return [
            'name' => $name,
            'prefix' => '',
            'suffix' => '',
            'form' => $form !== '' ? $form : $defaultForm,
            'rangeDelimiter' => (string) ($override['rangeDelimiter'] ?? ''),
            'stripPeriods' => ($override['stripPeriods'] ?? false) === true,
            'textCase' => strtolower(trim((string) ($override['textCase'] ?? ''))),
        ];
    }

    private function localizedSeasonName(int $season): string
    {
        $term = $this->style->term(sprintf('season-%02d', $season));

        return str_starts_with($term, 'season-') ? self::dateSeasonName($season) : $term;
    }

    /**
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}|string> $dateParts
     * @return list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}>
     */
    private function normalizedDatePartRenderingSpecs(array $dateParts): array
    {
        $specs = [];
        foreach ($dateParts as $part) {
            if (is_string($part)) {
                $name = strtolower(trim($part));
                if (!in_array($name, ['year', 'month', 'day'], true)) {
                    continue;
                }

                $specs[] = [
                    'name' => $name,
                    'prefix' => '',
                    'suffix' => '',
                    'form' => '',
                    'rangeDelimiter' => '',
                    'stripPeriods' => false,
                    'textCase' => '',
                ];
                continue;
            }

            if (!is_array($part)) {
                continue;
            }

            $name = strtolower(trim((string) ($part['name'] ?? '')));
            if (!in_array($name, ['year', 'month', 'day'], true)) {
                continue;
            }

            $specs[] = [
                'name' => $name,
                'prefix' => (string) ($part['prefix'] ?? ''),
                'suffix' => (string) ($part['suffix'] ?? ''),
                'form' => strtolower(trim((string) ($part['form'] ?? ''))),
                'rangeDelimiter' => (string) ($part['rangeDelimiter'] ?? ''),
                'stripPeriods' => ($part['stripPeriods'] ?? false) === true,
                'textCase' => strtolower(trim((string) ($part['textCase'] ?? ''))),
            ];
        }

        return $specs;
    }

    /**
     * @param list<int> $parts
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}> $specs
     * @param array<string, mixed> $item
     */
    private function renderDatePartSequence(array $parts, array $specs, string $delimiter, array $item): string
    {
        $values = [];
        foreach ($specs as $spec) {
            $value = $this->renderDatePartValue($parts, $spec, $item);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $this->joinRenderedElements($values, $delimiter !== '' ? $delimiter : '-');
    }

    /**
     * @param list<int> $parts
     * @param array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string} $spec
     * @param array<string, mixed> $item
     */
    private function renderDatePartValue(array $parts, array $spec, array $item): string
    {
        $name = $spec['name'];
        $number = match ($name) {
            'year' => $parts[0] ?? null,
            'month' => $parts[1] ?? null,
            'day' => $parts[2] ?? null,
            default => null,
        };
        if ($number === null) {
            return '';
        }

        $value = match ($name) {
            'year' => $this->formatCslDateYearPart((int) $number, $spec['form']),
            'month' => $this->formatCslDateMonthPart((int) $number, $spec['form']),
            'day' => $this->formatCslDateDayPart((int) $number, $spec['form'], isset($parts[1]) ? (int) $parts[1] : null),
            default => '',
        };
        if ($value === '') {
            return '';
        }

        if ($spec['stripPeriods']) {
            $value = str_replace('.', '', $value);
        }
        $value = $this->applyTextCase($value, $spec, $item);

        return (string) $spec['prefix'] . $value . (string) $spec['suffix'];
    }

    private function formatCslDateYearPart(int $year, string $form): string
    {
        if ($form === 'short') {
            return sprintf('%02d', abs($year) % 100);
        }

        return sprintf('%04d', $year);
    }

    private function formatCslDateMonthPart(int $month, string $form): string
    {
        if ($month < 1 || $month > 12) {
            return '';
        }

        return match ($form) {
            'numeric' => (string) $month,
            'numeric-leading-zeros' => sprintf('%02d', $month),
            'short' => $this->style->term('month-' . sprintf('%02d', $month), 'short'),
            default => $this->style->term('month-' . sprintf('%02d', $month), 'long'),
        };
    }

    private function formatCslDateDayPart(int $day, string $form, ?int $month = null): string
    {
        if ($day < 1 || $day > 31) {
            return '';
        }

        return match ($form) {
            'numeric-leading-zeros' => sprintf('%02d', $day),
            'ordinal' => $this->formatCslDayOrdinal($day, $month),
            default => (string) $day,
        };
    }

    private function formatCslDayOrdinal(int $day, ?int $month = null): string
    {
        if ($this->style->limitDayOrdinalsToDay1() && $day !== 1) {
            return (string) $day;
        }

        $gender = $month === null ? '' : $this->style->termGender('month-' . sprintf('%02d', $month));

        return $day . $this->ordinalSuffix($day, $gender);
    }

    /**
     * @param list<list<int>> $parts
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}> $specs
     */
    private function dateRangeDelimiter(array $parts, array $specs): string
    {
        if (count($parts) < 2 || !is_array($parts[0] ?? null) || !is_array($parts[1] ?? null)) {
            return '/';
        }

        $start = $parts[0];
        $end = $parts[1];
        $largestDifferingPart = null;
        foreach ([0 => 'year', 1 => 'month', 2 => 'day'] as $index => $name) {
            if (($start[$index] ?? null) !== ($end[$index] ?? null)) {
                $largestDifferingPart = $name;
                break;
            }
        }

        if ($largestDifferingPart === null) {
            return '/';
        }

        foreach ($specs as $spec) {
            if ($spec['name'] === $largestDifferingPart && $spec['rangeDelimiter'] !== '') {
                return $spec['rangeDelimiter'];
            }
        }

        return '/';
    }

    /**
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
     * @param array<string, mixed> $options
     */
    private function applyNamesLabel(string $renderedNames, string $selectedVariable, array $names, array $options): string
    {
        $label = $options['label'] ?? null;
        if ($renderedNames === '' || $selectedVariable === '' || !is_array($label)) {
            return $renderedNames;
        }

        $plural = match ((string) ($label['plural'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            default => count($names) !== 1,
        };
        $term = $this->style->term(
            $this->nameLabelTermName($selectedVariable),
            (string) ($label['form'] ?? 'long'),
            $plural
        );
        if (($label['stripPeriods'] ?? false) === true) {
            $term = str_replace('.', '', $term);
        }
        $term = $this->applyNamePartTextCase($term, $label);
        if (($label['quotes'] ?? false) === true) {
            $term = $this->style->term('open-quote') . $term . $this->style->term('close-quote');
        }
        if ($term === '') {
            return $renderedNames;
        }

        $prefix = (string) ($label['prefix'] ?? '');
        $suffix = (string) ($label['suffix'] ?? '');
        $renderedLabel = $prefix . $term . $suffix;
        if (($label['position'] ?? 'after') === 'before') {
            $separator = $suffix !== '' || preg_match('/^\s|^[.,;:!?]/u', $renderedNames) === 1 ? '' : ' ';

            return trim($renderedLabel . $separator . $renderedNames);
        }

        $separator = $prefix !== '' || preg_match('/\s\z/u', $renderedNames) === 1 ? '' : ' ';

        return trim($renderedNames . $separator . $renderedLabel);
    }

    private function nameLabelTermName(string $variable): string
    {
        return match (strtolower(trim($variable))) {
            'short-author' => 'author',
            'short-editor' => 'editor',
            'editor-translator', 'editortranslator' => 'editortranslator',
            'event-organizer', 'organizer' => 'event-organizer',
            default => strtolower(trim($variable)),
        };
    }

    /**
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
     * @param array<string, mixed> $options
     */
    private function renderNameList(array $names, array $options, bool $bibliography, ?AstNode $citation = null, ?array &$bibliographyState = null): string
    {
        if (!$bibliography) {
            $options = $this->citationNameRenderingOptionsWithGivenNameDisambiguation($options, $citation);
        }

        $forceEtAl = false;
        $renderableNames = [];
        foreach ($names as $name) {
            if (($name['etAl'] ?? false) === true) {
                $forceEtAl = true;
                break;
            }

            $renderableNames[] = $name;
        }

        if (!$forceEtAl) {
            $renderableNames = $names;
        }

        $count = count($renderableNames);
        if (strtolower(trim((string) ($options['form'] ?? 'long'))) === 'count') {
            return (string) $count;
        }

        $etAlMin = $options['etAlMin'];
        $etAlUseFirst = $options['etAlUseFirst'];
        if (!$bibliography && $this->citationUsesSubsequentNameOptions($citation)) {
            if (is_int($options['etAlSubsequentMin'] ?? null)) {
                $etAlMin = $options['etAlSubsequentMin'];
            }
            if (is_int($options['etAlSubsequentUseFirst'] ?? null)) {
                $etAlUseFirst = $options['etAlSubsequentUseFirst'];
            }
        }
        $useEtAl = $forceEtAl || (is_int($etAlMin) && $count >= $etAlMin);
        $visibleCount = $forceEtAl
            ? $count
            : ($useEtAl ? max(1, min((int) $etAlUseFirst, $count)) : $count);
        if (!$forceEtAl && $useEtAl && $visibleCount >= $count && isset($options['disambiguateNameCount'])) {
            $useEtAl = false;
        }
        $visible = array_slice($renderableNames, 0, $visibleCount);

        $rendered = [];
        foreach ($visible as $index => $name) {
            $rendered[] = $bibliography
                ? $this->renderBibliographyName($name, $options, $index)
                : $this->renderCitationName($name, $this->citationNameRenderingOptionsForVisibleName($options, $index), $index);
        }

        if ($useEtAl) {
            if ($this->usesEtAlLastName($options, $forceEtAl, $etAlMin, $etAlUseFirst, $visibleCount, $count)) {
                $lastName = $renderableNames[$count - 1];
                $lastRendered = $bibliography
                    ? $this->renderBibliographyName($lastName, $options, $count - 1)
                    : $this->renderCitationName($lastName, $this->citationNameRenderingOptionsForVisibleName($options, $count - 1), $count - 1);

                if ($bibliography && is_array($bibliographyState)) {
                    $substitution = $this->bibliographySubsequentAuthorSubstitutionPlan(
                        [...$rendered, $lastRendered],
                        $this->joinNamesWithEtAlUseLast($rendered, $lastRendered, $options),
                        $bibliographyState
                    );
                    if (($substitution['type'] ?? '') === 'full') {
                        return (string) ($substitution['value'] ?? '');
                    }
                    if (($substitution['type'] ?? '') === 'parts' && is_array($substitution['parts'] ?? null)) {
                        $substituted = $substitution['parts'];
                        $lastRendered = array_pop($substituted) ?? $lastRendered;
                        $rendered = $substituted;
                    }
                }

                return $this->joinNamesWithEtAlUseLast($rendered, $lastRendered, $options);
            }

            $term = $this->renderEtAlTerm($options);
            if ($rendered === []) {
                if ($bibliography && is_array($bibliographyState)) {
                    $substitution = $this->bibliographySubsequentAuthorSubstitutionPlan([], $term, $bibliographyState);
                    if (($substitution['type'] ?? '') === 'full') {
                        return (string) ($substitution['value'] ?? '');
                    }
                }

                return $term;
            }

            if ($bibliography && is_array($bibliographyState)) {
                $substitution = $this->bibliographySubsequentAuthorSubstitutionPlan(
                    $rendered,
                    $this->joinNamesWithEtAl($rendered, $term, $options, $bibliography, $visibleCount - 1),
                    $bibliographyState
                );
                if (($substitution['type'] ?? '') === 'full') {
                    return (string) ($substitution['value'] ?? '');
                }
                if (($substitution['type'] ?? '') === 'parts' && is_array($substitution['parts'] ?? null)) {
                    $rendered = $substitution['parts'];
                }
            }

            return $this->joinNamesWithEtAl($rendered, $term, $options, $bibliography, $visibleCount - 1);
        }

        if ($bibliography && is_array($bibliographyState)) {
            $renderedNames = $this->joinNamesWithLastDelimiter($rendered, $options, true);
            $substitution = $this->bibliographySubsequentAuthorSubstitutionPlan(
                $rendered,
                $renderedNames,
                $bibliographyState
            );
            if (($substitution['type'] ?? '') === 'full') {
                return (string) ($substitution['value'] ?? '');
            }
            if (($substitution['type'] ?? '') === 'parts' && is_array($substitution['parts'] ?? null)) {
                $rendered = $substitution['parts'];
            }
        }

        return $bibliography
            ? $this->joinNamesWithLastDelimiter($rendered, $options, true)
            : $this->joinNamesWithLastDelimiter($rendered, $options, false);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function citationNameRenderingOptionsWithGivenNameDisambiguation(array $options, ?AstNode $citation): array
    {
        if (!$citation instanceof AstNode) {
            return $options;
        }

        $count = $citation->attr('cslDisambiguateNameCount');
        if (is_int($count) || (is_string($count) && preg_match('/^\d+$/', $count) === 1)) {
            $count = (int) $count;
            if ($count > 1) {
                $etAlUseFirst = $options['etAlUseFirst'] ?? 1;
                if (!is_int($etAlUseFirst)) {
                    $etAlUseFirst = 1;
                }

                $options['etAlUseFirst'] = max($etAlUseFirst, $count);
                if (is_int($options['etAlSubsequentUseFirst'] ?? null)) {
                    $options['etAlSubsequentUseFirst'] = max($options['etAlSubsequentUseFirst'], $count);
                }
                $options['disambiguateNameCount'] = $count;
            }
        }

        $mode = strtolower(trim((string) $citation->attr('cslGivenNameDisambiguation', '')));
        if (!in_array($mode, ['initial', 'full'], true)) {
            return $options;
        }

        return [
            ...$options,
            'givenNameDisambiguationMode' => $mode,
            'givenNameDisambiguationScope' => $this->givenNameDisambiguationScope(),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function citationNameRenderingOptionsForVisibleName(array $options, int $index): array
    {
        if (!isset($options['givenNameDisambiguationMode'])) {
            return $options;
        }

        if (($options['givenNameDisambiguationScope'] ?? 'primary') === 'all' || $index === 0) {
            return $options;
        }

        unset($options['givenNameDisambiguationMode'], $options['givenNameDisambiguationScope']);

        return $options;
    }

    private function givenNameDisambiguationScope(): string
    {
        return str_starts_with((string) ($this->style->citationOptions()['givenNameDisambiguationRule'] ?? 'by-cite'), 'all-names')
            ? 'all'
            : 'primary';
    }

    private function citationUsesSubsequentNameOptions(?AstNode $citation): bool
    {
        if (!$citation instanceof AstNode) {
            return false;
        }

        $tests = $citation->attr('cslPositionTests', []);
        if (is_array($tests)) {
            foreach ($tests as $test) {
                if (strtolower(trim((string) $test)) === 'subsequent') {
                    return true;
                }
            }
        }

        return in_array(
            strtolower(trim((string) $citation->attr('cslPosition', ''))),
            ['subsequent', 'ibid', 'ibid-with-locator', 'near-note'],
            true
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function usesEtAlLastName(array $options, bool $forceEtAl, mixed $etAlMin, mixed $etAlUseFirst, int $visibleCount, int $count): bool
    {
        if ($forceEtAl || ($options['etAlUseLast'] ?? false) !== true) {
            return false;
        }

        if (!is_int($etAlMin) || !is_int($etAlUseFirst) || $etAlUseFirst > $etAlMin - 2) {
            return false;
        }

        return $visibleCount + 1 < $count;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function renderEtAlTerm(array $options): string
    {
        $etAl = is_array($options['etAl'] ?? null) ? $options['etAl'] : [];
        $termName = (string) ($etAl['term'] ?? 'et-al');
        if (!in_array($termName, ['et-al', 'and others'], true)) {
            $termName = 'et-al';
        }

        $term = $this->style->term($termName);
        if (($etAl['stripPeriods'] ?? false) === true) {
            $term = str_replace('.', '', $term);
        }

        $term = $this->applyNamePartTextCase($term, $etAl);
        if (($etAl['quotes'] ?? false) === true) {
            $term = $this->style->term('open-quote') . $term . $this->style->term('close-quote');
        }

        return (string) ($etAl['prefix'] ?? '') . $term . (string) ($etAl['suffix'] ?? '');
    }

    /**
     * @param list<string> $rendered
     * @param array<string, mixed> $options
     */
    private function joinNamesWithEtAl(array $rendered, string $term, array $options, bool $bibliography, int $lastVisibleIndex): string
    {
        $names = implode((string) $options['delimiter'], $rendered);
        $separator = $this->delimiterBeforeEtAl($rendered, $options, $bibliography, $lastVisibleIndex)
            ? (string) $options['delimiter']
            : ' ';

        if ($separator === '') {
            $separator = ' ';
        }

        return $names . $separator . $term;
    }

    /**
     * @param list<string> $rendered
     * @param array<string, mixed> $options
     */
    private function joinNamesWithEtAlUseLast(array $rendered, string $lastRendered, array $options): string
    {
        $delimiter = (string) $options['delimiter'];

        return implode($delimiter, [...$rendered, $this->style->term('ellipsis'), $lastRendered]);
    }

    /**
     * @param list<string> $rendered
     * @param array<string, mixed> $options
     */
    private function delimiterBeforeEtAl(array $rendered, array $options, bool $bibliography, int $lastVisibleIndex): bool
    {
        return match ((string) ($options['delimiterPrecedesEtAl'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            'after-inverted-name' => $bibliography && $this->visibleNameWasInvertedForEtAl($options, $lastVisibleIndex),
            default => count($rendered) > 1,
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function visibleNameWasInvertedForEtAl(array $options, int $lastVisibleIndex): bool
    {
        $nameAsSortOrder = (string) ($options['nameAsSortOrder'] ?? '');
        if ($nameAsSortOrder === 'all') {
            return true;
        }

        return $nameAsSortOrder === 'first' && $lastVisibleIndex === 0;
    }

    /**
     * @param array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array<string, mixed> $options
     */
    private function renderCitationName(array $name, array $options, int $index): string
    {
        if ($name['literal'] !== '') {
            return $this->renderInstitutionName($name, $options);
        }

        if ($this->citationNameUsesExplicitSortOrder($options, $index)) {
            return $this->renderBibliographyName($name, $options, $index);
        }

        $givenDisambiguationMode = (string) ($options['givenNameDisambiguationMode'] ?? '');
        if (in_array($givenDisambiguationMode, ['initial', 'full'], true) && trim((string) $name['given']) !== '') {
            return $this->renderGivenNameDisambiguatedCitationName($name, $options, $givenDisambiguationMode);
        }

        $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
        if ($family !== '') {
            return $this->formatNamePart('family', $family, $options);
        }

        return $this->formatNamePart('given', $this->renderGivenName((string) $name['given'], $options), $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function citationNameUsesExplicitSortOrder(array $options, int $index): bool
    {
        if (($options['nameAsSortOrderExplicit'] ?? false) !== true) {
            return false;
        }

        $nameAsSortOrder = (string) ($options['nameAsSortOrder'] ?? '');

        return $nameAsSortOrder === 'all' || ($nameAsSortOrder === 'first' && $index === 0);
    }

    /**
     * @param array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array<string, mixed> $options
     */
    private function renderGivenNameDisambiguatedCitationName(array $name, array $options, string $mode): string
    {
        $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
        $family = $this->formatNamePart('family', $family, $options);
        $given = $mode === 'full'
            ? trim((string) $name['given'])
            : $this->renderGivenName((string) $name['given'], $options);
        $given = $this->formatNamePart('given', $given, $options);

        return trim(implode(' ', array_values(array_filter(
            [$given, $family],
            static fn (string $part): bool => $part !== ''
        ))));
    }

    /**
     * @param array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array<string, mixed> $options
     */
    private function renderBibliographyName(array $name, array $options, int $index): string
    {
        if ($name['literal'] !== '') {
            return $this->renderInstitutionName($name, $options);
        }

        $nonDroppingParticle = (string) $name['nonDroppingParticle'];
        if (strtolower(trim((string) ($options['form'] ?? 'long'))) === 'short') {
            $family = self::nameUsesFamilyGivenDisplayOrder($name)
                ? trim((string) $name['family'])
                : trim($nonDroppingParticle . ' ' . (string) $name['family']);
            if ($family !== '') {
                return $this->formatNamePart('family', $family, $options);
            }

            $given = self::nameUsesFamilyGivenDisplayOrder($name)
                ? (string) $name['given']
                : $this->renderGivenName((string) $name['given'], $options);

            return $this->formatNamePart('given', $given, $options);
        }

        if (self::nameUsesFamilyGivenDisplayOrder($name)) {
            return $this->renderFamilyGivenBibliographyName($name, $options);
        }

        $sortOrdered = $options['nameAsSortOrder'] === 'all' || ($options['nameAsSortOrder'] === 'first' && $index === 0);
        $demoteForDisplay = $sortOrdered && (string) ($options['demoteNonDroppingParticle'] ?? 'never') === 'display-and-sort';
        $family = trim($demoteForDisplay
            ? (string) $name['family']
            : trim($nonDroppingParticle . ' ' . (string) $name['family']));
        $family = $this->formatNamePart('family', $family, $options);
        $givenParts = [];
        $given = $this->renderGivenName((string) $name['given'], $options);
        if ($given !== '') {
            $givenParts[] = $given;
        }
        $droppingParticle = (string) $name['droppingParticle'];
        if ($demoteForDisplay && $droppingParticle !== '') {
            $givenParts[] = $droppingParticle;
        }
        if ($demoteForDisplay && $nonDroppingParticle !== '') {
            $givenParts[] = $nonDroppingParticle;
        }
        $given = $this->formatNamePart('given', implode(' ', $givenParts), $options);
        $suffix = (string) $name['suffix'];

        if ($sortOrdered) {
            if ($family !== '' && $given !== '') {
                $entry = $family . (string) ($options['sortSeparator'] ?? ', ') . $given;
            } else {
                $entry = $family !== '' ? $family : $given;
            }
        } else {
            $entry = trim(($given !== '' ? $given . ' ' : '') . $family);
        }

        if (!$demoteForDisplay && $droppingParticle !== '') {
            $entry = trim($entry . ' ' . $droppingParticle);
        }

        if ($suffix !== '') {
            $entry .= ($name['commaSuffix'] ? ', ' : ' ') . $suffix;
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $name
     * @param array<string, mixed> $options
     */
    private function renderInstitutionName(array $name, array $options): string
    {
        $literal = trim((string) ($name['literal'] ?? ''));
        if ($literal === '') {
            return '';
        }

        $institution = $options['institution'] ?? null;
        if (!is_array($institution)) {
            return $literal;
        }

        $parts = $institution['parts'] ?? [];
        $longPart = is_array($parts) && is_array($parts['long'] ?? null) ? $parts['long'] : null;
        $shortPart = is_array($parts) && is_array($parts['short'] ?? null) ? $parts['short'] : null;
        $institutionParts = (string) ($institution['institutionParts'] ?? 'long');
        $delimiter = (string) ($institution['delimiter'] ?? ' ');
        $short = trim((string) ($name['short'] ?? ''));
        if ($short === '') {
            $short = $this->institutionShortAbbreviation($literal);
        }

        $longRendered = $this->formatInstitutionPart($literal, $longPart);
        $shortRendered = $this->formatInstitutionPart($short !== '' ? $short : $literal, $shortPart);

        if ($institutionParts === 'short') {
            return $shortRendered;
        }

        if ($institutionParts === 'long') {
            return $longRendered;
        }

        $hasDistinctShort = $short !== '' && $short !== $literal;
        if ($institutionParts === 'short-long') {
            $rendered = $hasDistinctShort ? [$shortRendered, $longRendered] : [$longRendered];

            return $this->joinRenderedElements(array_values(array_filter(
                $rendered,
                static fn (string $part): bool => $part !== ''
            )), $delimiter);
        }

        $rendered = $hasDistinctShort ? [$longRendered, $shortRendered] : [$longRendered];

        return $this->joinRenderedElements(array_values(array_filter(
            $rendered,
            static fn (string $part): bool => $part !== ''
        )), $delimiter);
    }

    private function institutionShortAbbreviation(string $literal): string
    {
        $literal = trim($literal);
        if ($literal === '' || $this->cslAbbreviations === []) {
            return '';
        }

        $abbreviation = $this->cslAbbreviations['institution'][$literal] ?? null;

        return is_string($abbreviation) ? trim($abbreviation) : '';
    }

    /**
     * @param array<string, mixed>|null $format
     */
    private function formatInstitutionPart(string $value, ?array $format): string
    {
        $value = trim($value);
        if ($value === '' || $format === null) {
            return $value;
        }

        if (($format['stripPeriods'] ?? false) === true) {
            $value = str_replace('.', '', $value);
        }

        $value = $this->applyNamePartTextCase($value, $format);

        if (($format['quotes'] ?? false) === true) {
            $value = $this->style->term('open-quote') . $value . $this->style->term('close-quote');
        }

        return (string) ($format['prefix'] ?? '') . $value . (string) ($format['suffix'] ?? '');
    }

    /**
     * @param array<string, mixed> $name
     * @param array<string, mixed> $options
     */
    private function renderFamilyGivenBibliographyName(array $name, array $options): string
    {
        $family = $this->formatNamePart('family', trim((string) ($name['family'] ?? '')), $options);
        $given = $this->formatNamePart('given', trim((string) ($name['given'] ?? '')), $options);
        $separator = self::nameUsesCompactFamilyGivenScript($name) ? '' : ' ';
        $parts = array_values(array_filter([$family, $given], static fn (string $part): bool => $part !== ''));
        $entry = implode($separator, $parts);

        $suffix = trim((string) ($name['suffix'] ?? ''));
        if ($suffix !== '') {
            $entry .= (($name['commaSuffix'] ?? false) ? ', ' : ' ') . $suffix;
        }

        return trim($entry);
    }

    /**
     * @param array<string, mixed> $name
     */
    private static function nameUsesFamilyGivenDisplayOrder(array $name): bool
    {
        if (($name['staticOrdering'] ?? false) === true) {
            return true;
        }

        return self::nameUsesCompactFamilyGivenScript($name);
    }

    /**
     * @param array<string, mixed> $name
     */
    private static function nameUsesCompactFamilyGivenScript(array $name): bool
    {
        $text = trim(implode('', [
            (string) ($name['family'] ?? ''),
            (string) ($name['given'] ?? ''),
            (string) ($name['nonDroppingParticle'] ?? ''),
            (string) ($name['droppingParticle'] ?? ''),
        ]));
        if ($text === '' || preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $text) !== 1) {
            return false;
        }

        return preg_match('/\p{Latin}/u', $text) !== 1;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function formatNamePart(string $part, string $value, array $options): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $nameParts = $options['nameParts'] ?? [];
        $format = is_array($nameParts) && is_array($nameParts[$part] ?? null) ? $nameParts[$part] : null;
        if ($format === null) {
            return $value;
        }

        if (($format['stripPeriods'] ?? false) === true) {
            $value = str_replace('.', '', $value);
        }

        $value = $this->applyNamePartTextCase($value, $format);

        if (($format['quotes'] ?? false) === true) {
            $value = $this->style->term('open-quote') . $value . $this->style->term('close-quote');
        }

        return (string) ($format['prefix'] ?? '') . $value . (string) ($format['suffix'] ?? '');
    }

    /**
     * @param array<string, mixed> $format
     */
    private function applyNamePartTextCase(string $value, array $format): string
    {
        $textCase = strtolower(trim((string) ($format['textCase'] ?? '')));

        return match ($textCase) {
            'lowercase' => mb_strtolower($value, 'UTF-8'),
            'uppercase' => mb_strtoupper($value, 'UTF-8'),
            'capitalize-first' => $this->capitalizeFirstLowercaseWord($value),
            'capitalize-all' => $this->capitalizeAllLowercaseWords($value),
            'sentence' => $this->sentenceCaseText($value),
            'title' => $this->titleCaseText($value, []),
            default => $value,
        };
    }

    /**
     * @param list<string> $names
     * @param array<string, mixed> $options
     */
    private function joinNamesWithLastDelimiter(array $names, array $options, bool $bibliography): string
    {
        $count = count($names);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $names[0];
        }

        if ($bibliography && ($options['delimiterPrecedesLastExplicit'] ?? false) !== true) {
            return implode($options['delimiter'], $names);
        }

        $and = $this->andJoiner($options);
        if ($and === '') {
            return implode($options['delimiter'], $names);
        }

        $delimiter = (string) ($options['delimiter'] ?? '');
        if ($count === 2) {
            $separator = $this->delimiterBeforeLastName($options, $bibliography, 0) ? $delimiter : ' ';
            if ($separator === '') {
                $separator = ' ';
            }

            return $names[0] . $separator . $and . ' ' . $names[1];
        }

        $previousNameIndex = $count - 2;
        $separator = $this->delimiterBeforeLastName($options, $bibliography, $previousNameIndex) ? $delimiter : ' ';
        if ($separator === '') {
            $separator = ' ';
        }

        return implode($delimiter, array_slice($names, 0, -1))
            . $separator
            . $and
            . ' '
            . $names[$count - 1];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function delimiterBeforeLastName(array $options, bool $bibliography, int $previousNameIndex): bool
    {
        return match ((string) ($options['delimiterPrecedesLast'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            'after-inverted-name' => $bibliography && $this->visibleNameWasInvertedForEtAl($options, $previousNameIndex),
            default => $previousNameIndex > 0,
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function andJoiner(array $options): string
    {
        return match ($options['and']) {
            'symbol' => $this->style->term('and', 'symbol'),
            'none' => '',
            default => $this->style->term('and'),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function renderGivenName(string $given, array $options): string
    {
        $given = trim($given);
        $initializeWith = $options['initializeWith'] ?? null;
        if ($given === '' || $initializeWith === null) {
            return $given;
        }

        $initializeWith = (string) $initializeWith;
        $initializeWithHyphen = ($options['initializeWithHyphen'] ?? true) !== false;
        if (($options['initialize'] ?? true) === false) {
            return $this->renderGivenNameWithoutInitialization($given, $initializeWith, $initializeWithHyphen);
        }

        $tokens = preg_split('/(\s+|-+)/u', $given, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $rendered = '';
        $separator = '';
        foreach ($tokens as $token) {
            if (preg_match('/^\s+$/u', $token) === 1) {
                $separator = 'space';
                continue;
            }

            if (preg_match('/^-+$/u', $token) === 1) {
                $separator = 'hyphen';
                continue;
            }

            if (preg_match('/^./us', $token, $match) !== 1) {
                continue;
            }

            $initial = $this->uppercaseInitial($match[0]) . $initializeWith;
            if ($rendered !== '' && $separator === 'hyphen' && $initializeWithHyphen) {
                $rendered = rtrim($rendered) . '-' . $initial;
            } else {
                $rendered .= $initial;
            }
            $separator = '';
        }

        return rtrim($rendered);
    }

    private function renderGivenNameWithoutInitialization(string $given, string $initializeWith, bool $initializeWithHyphen): string
    {
        if ($initializeWith === '') {
            return $given;
        }

        $tokens = preg_split('/(\s+|-+)/u', $given, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $rendered = '';
        $separator = '';
        $lastTokenWasInitial = false;
        foreach ($tokens as $token) {
            if (preg_match('/^\s+$/u', $token) === 1) {
                $separator = $token;
                continue;
            }

            if (preg_match('/^-+$/u', $token) === 1) {
                $separator = 'hyphen';
                continue;
            }

            [$renderedToken, $tokenWasInitial] = $this->punctuateExistingGivenInitial($token, $initializeWith);
            if ($rendered === '') {
                $rendered = $renderedToken;
                $lastTokenWasInitial = $tokenWasInitial;
                $separator = '';
                continue;
            }

            if ($separator === 'hyphen') {
                if (!$lastTokenWasInitial && !$tokenWasInitial) {
                    $rendered .= '-' . $renderedToken;
                } elseif ($initializeWithHyphen) {
                    $rendered = rtrim($rendered) . '-' . $renderedToken;
                } else {
                    $rendered = rtrim($rendered) . ' ' . ltrim($renderedToken);
                }
            } elseif ($separator !== '') {
                $rendered = rtrim($rendered) . $separator . ltrim($renderedToken);
            } else {
                $rendered .= $renderedToken;
            }

            $lastTokenWasInitial = $tokenWasInitial;
            $separator = '';
        }

        return rtrim($rendered);
    }

    /**
     * @return array{0:string, 1:bool}
     */
    private function punctuateExistingGivenInitial(string $token, string $initializeWith): array
    {
        $trimmed = trim($token);
        if (preg_match('/^\p{L}\.?$/u', $trimmed) !== 1) {
            return [$token, false];
        }

        return [$this->uppercaseInitial(rtrim($trimmed, '.')) . $initializeWith, true];
    }

    private function uppercaseInitial(string $initial): string
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($initial, 'UTF-8');
        }

        return strtoupper($initial);
    }

    private function citationPrefix(AstNode $citation): string
    {
        return $this->inlineValue($citation->attr('prefix', ''));
    }

    private function citationSuffix(AstNode $citation): string
    {
        $suffix = $citation->attr('suffix', null);
        $locator = $citation->attr('locator', '');
        $renderedSuffix = $this->inlineValue($suffix);
        $renderedLocator = $this->inlineValue($locator);

        if ($renderedLocator !== '' && $renderedSuffix !== '') {
            return $renderedLocator . ' ' . $renderedSuffix;
        }

        return $renderedSuffix !== '' ? $renderedSuffix : $renderedLocator;
    }

    /**
     * @return array{label:string, value:string}
     */
    private function citationLocatorParts(?AstNode $citation): array
    {
        if (!$citation instanceof AstNode) {
            return ['label' => 'page', 'value' => ''];
        }

        $explicitValue = $this->inlineValue($citation->attr('locatorValue', ''));
        if ($explicitValue !== '') {
            $label = $this->normalizedLocatorLabel((string) $citation->attr('locatorLabel', ''));

            return ['label' => $label, 'value' => $explicitValue];
        }

        $locator = $this->inlineValue($citation->attr('locator', ''));
        if ($locator !== '') {
            return $this->inferCitationLocatorParts($locator);
        }

        $suffix = $this->inlineValue($citation->attr('suffix', ''));
        if ($suffix !== '') {
            return $this->inferCitationLocatorParts($suffix);
        }

        return ['label' => 'page', 'value' => ''];
    }

    /**
     * @return array{label:string, value:string}
     */
    private function inferCitationLocatorParts(string $locator): array
    {
        $locator = trim(preg_replace('/\s+/u', ' ', $locator) ?? $locator);
        $patterns = [
            'page' => '/^(?:p(?:p)?\.?|pages?)\s+(.+)$/iu',
            'article-locator' => '/^(?:art(?:icles?|s)?\.?|article(?:s)?)\s+(.+)$/iu',
            'appendix' => '/^(?:app(?:endices|endixes|s)?\.?|appendix|appendices|appendixes)\s+(.+)$/iu',
            'book' => '/^(?:bks?\.?|books?)\s+(.+)$/iu',
            'canon' => '/^(?:cc?\.?|canons?)\s+(.+)$/iu',
            'chapter' => '/^(?:chap(?:ters?|s)?\.?|chapter(?:s)?)\s+(.+)$/iu',
            'column' => '/^(?:col(?:umns?|s)?\.?|column(?:s)?)\s+(.+)$/iu',
            'elocation' => '/^(?:e-?loc(?:ations?|s)?\.?|elocations?|e-locations?)\s+(.+)$/iu',
            'equation' => '/^(?:eq(?:uations?|s)?\.?|equation(?:s)?)\s+(.+)$/iu',
            'figure' => '/^(?:fig(?:ures?|s)?\.?|figure(?:s)?)\s+(.+)$/iu',
            'folio' => '/^(?:fol(?:ios?|s)?\.?|folio(?:s)?)\s+(.+)$/iu',
            'line' => '/^(?:l(?:ines?|s)?\.?|line(?:s)?)\s+(.+)$/iu',
            'note' => '/^(?:n(?:otes?|s)?\.?|note(?:s)?)\s+(.+)$/iu',
            'opus' => '/^(?:opp?\.?|opus|opera)\s+(.+)$/iu',
            'part' => '/^(?:pts?\.?|part(?:s)?)\s+(.+)$/iu',
            'rule' => '/^(?:rr?\.?|rule(?:s)?)\s+(.+)$/iu',
            'section' => '/^(?:sec(?:tions?|s)?\.?|section(?:s)?|\x{00A7}\x{00A7}?)\s+(.+)$/iu',
            'paragraph' => '/^(?:para(?:graphs?|s)?\.?|paragraph(?:s)?|\x{00B6}\x{00B6}?)\s+(.+)$/iu',
            'sub-verbo' => '/^(?:s\.?\s*vv?\.?|sub[-\s]?verb[aois]+|sub-verbo|sub-verbum)\s+(.+)$/iu',
            'supplement' => '/^(?:supp(?:lements?|s)?\.?|supplement(?:s)?)\s+(.+)$/iu',
            'table' => '/^(?:tbl(?:s)?\.?|table(?:s)?)\s+(.+)$/iu',
            'timestamp' => '/^(?:timestamps?|ts\.?)\s+(.+)$/iu',
            'title' => '/^(?:tit(?:les?|s)?\.?|title(?:s)?)\s+(.+)$/iu',
            'verse' => '/^(?:v(?:erses?|s)?\.?|verse(?:s)?)\s+(.+)$/iu',
            'volume' => '/^(?:vol(?:umes?|s)?\.?|volume(?:s)?)\s+(.+)$/iu',
            'issue' => '/^(?:iss(?:ues?|s)?\.?|issue(?:s)?)\s+(.+)$/iu',
            'number' => '/^(?:no(?:s)?\.?|number(?:s)?)\s+(.+)$/iu',
        ];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $locator, $match) === 1) {
                return ['label' => $label, 'value' => trim($match[1])];
            }
        }

        return ['label' => 'page', 'value' => $locator];
    }

    private function normalizedLocatorLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = str_replace(['_', ' '], '-', $label);
        $label = rtrim($label, '.');

        return match ($label) {
            'p', 'pp', 'page', 'pages' => 'page',
            'art', 'arts', 'article', 'articles', 'article-locator', 'article-locators' => 'article-locator',
            'app', 'apps', 'appendix', 'appendices', 'appendixes' => 'appendix',
            'bk', 'bks', 'book', 'books' => 'book',
            'c', 'cc', 'canon', 'canons' => 'canon',
            'chap', 'chaps', 'chapter', 'chapters' => 'chapter',
            'col', 'cols', 'column', 'columns' => 'column',
            'e-loc', 'e-locs', 'eloc', 'elocs', 'elocation', 'elocations', 'e-location', 'e-locations' => 'elocation',
            'eq', 'eqs', 'equation', 'equations' => 'equation',
            'fig', 'figs', 'figure', 'figures' => 'figure',
            'fol', 'fols', 'folio', 'folios' => 'folio',
            'l', 'll', 'line', 'lines' => 'line',
            'n', 'nn', 'note', 'notes' => 'note',
            'op', 'opp', 'opus', 'opera' => 'opus',
            'pt', 'pts', 'part', 'parts' => 'part',
            'r', 'rr', 'rule', 'rules' => 'rule',
            'sec', 'secs', 'section', 'sections', "\u{00A7}", "\u{00A7}\u{00A7}" => 'section',
            'para', 'paras', 'paragraph', 'paragraphs', "\u{00B6}", "\u{00B6}\u{00B6}" => 'paragraph',
            's.v', 's.vv', 's.-v', 's.-vv', 'sv', 'svv', 'sub-verbo', 'sub-verbum', 'sub-verba', 'sub-verbis' => 'sub-verbo',
            'supp', 'supps', 'suppl', 'suppls', 'supplement', 'supplements' => 'supplement',
            'tbl', 'tbls', 'table', 'tables' => 'table',
            'ts', 'timestamp', 'timestamps' => 'timestamp',
            'tit', 'tits', 'ttl', 'ttls', 'title', 'titles' => 'title',
            'v', 'vv', 'verse', 'verses' => 'verse',
            'vol', 'vols', 'volume', 'volumes' => 'volume',
            'iss', 'isses', 'issue', 'issues' => 'issue',
            'no', 'nos', 'number', 'numbers' => 'number',
            default => $label === '' ? 'page' : $label,
        };
    }

    private function inlineValue(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        if ($value instanceof AstNode) {
            return $this->plainInlineText([$value]);
        }

        if (is_array($value)) {
            return $this->plainInlineText($value);
        }

        return '';
    }

    /**
     * @param list<mixed> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                $text .= is_scalar($node) ? (string) $node : '';
                continue;
            }

            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'space', 'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function sourceCitationText(AstNode $citation): string
    {
        $text = (string) $citation->attr('text', '');
        if ($text !== '') {
            return $text;
        }

        return $this->plainInlineText($citation->children);
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? $text, '-'));

        return $slug === '' ? 'references' : $slug;
    }
}
