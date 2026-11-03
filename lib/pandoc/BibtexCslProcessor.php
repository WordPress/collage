<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibtexCslProcessor
{
    /** @var list<string> */
    private const BIBLATEX_CUSTOM_FIELDS = ['usera', 'userb', 'userc', 'userd', 'usere', 'userf', 'verba', 'verbb', 'verbc'];

    /** @var list<string> */
    private const BIBLATEX_CUSTOM_LIST_FIELDS = ['lista', 'listb', 'listc', 'listd', 'liste', 'listf'];

    /** @var list<string> */
    private const BIBLATEX_CUSTOM_NAME_FIELDS = ['namea', 'nameb', 'namec'];

    /** @var list<string> */
    private const BIBLATEX_NAME_ANNOTATION_FIELDS = [
        'afterword',
        'annotator',
        'author',
        'authority',
        'authority-list',
        'authoritylist',
        'bookauthor',
        'chair',
        'collaborator',
        'collection-editor',
        'collectioneditor',
        'commentator',
        'compiler',
        'composer',
        'container-author',
        'continuator',
        'contributor',
        'curator',
        'director',
        'editor',
        'editor-translator',
        'editorial-director',
        'editorialdirector',
        'editortranslator',
        'editora',
        'editorb',
        'editorc',
        'event-organizer',
        'eventorganizer',
        'executive-producer',
        'executiveproducer',
        'foreword',
        'founder',
        'guest',
        'holder',
        'host',
        'illustrator',
        'introduction',
        'interviewer',
        'issuing-authority',
        'issuing-authority-list',
        'issuingauthority',
        'issuingauthoritylist',
        'namea',
        'nameb',
        'namec',
        'narrator',
        'origauthor',
        'organizer',
        'organization',
        'original-author',
        'originalauthor',
        'performer',
        'producer',
        'recipient',
        'redactor',
        'reviewed-author',
        'reviewedauthor',
        'reviser',
        'script-writer',
        'scriptwriter',
        'series-creator',
        'seriescreator',
        'series-editor',
        'serieseditor',
        'short-author',
        'shortauthor',
        'short-editor',
        'shorteditor',
        'translator',
    ];

    /** @var array<string, string> */
    private const MONTH_MACROS = [
        'jan' => '1',
        'feb' => '2',
        'mar' => '3',
        'apr' => '4',
        'may' => '5',
        'jun' => '6',
        'jul' => '7',
        'aug' => '8',
        'sep' => '9',
        'oct' => '10',
        'nov' => '11',
        'dec' => '12',
    ];

    /**
     * @return array<string, array{id:string, type:string, fields:array<string, string>, csl:array<string, mixed>}>
     */
    public function parseBibtex(string $source): array
    {
        $rawEntries = [];
        $macros = self::MONTH_MACROS;
        $offset = 0;

        while (($at = strpos($source, '@', $offset)) !== false) {
            $cursor = $at + 1;
            $type = strtolower($this->readIdentifier($source, $cursor));
            if ($type === '') {
                $offset = $cursor;
                continue;
            }

            $this->skipWhitespace($source, $cursor);
            $open = $source[$cursor] ?? '';
            if ($open !== '{' && $open !== '(') {
                $offset = $cursor;
                continue;
            }

            $close = $open === '{' ? '}' : ')';
            $cursor++;
            $bodyStart = $cursor;
            $bodyEnd = $this->findBalancedEnd($source, $cursor, $open, $close);
            if ($bodyEnd === null) {
                break;
            }

            $body = substr($source, $bodyStart, $bodyEnd - $bodyStart);
            $offset = $bodyEnd + 1;

            if ($type === 'comment' || $type === 'preamble') {
                continue;
            }

            [$key, $fields] = $this->parseEntryBody($body, $macros);
            if ($type === 'string') {
                foreach ($fields as $name => $value) {
                    $macros[strtolower($name)] = $value;
                }
                continue;
            }

            if ($key === '') {
                continue;
            }

            $rawEntries[$key] = [
                'id' => $key,
                'type' => $type,
                'fields' => $fields,
            ];
        }

        $entries = [];
        foreach ($rawEntries as $key => $entry) {
            $fields = $this->resolveInheritedFields($entry, $rawEntries);
            $entries[$key] = [
                'id' => $entry['id'],
                'type' => $entry['type'],
                'fields' => $fields,
                'csl' => $this->withBiblatexRelationMetadata(
                    $this->toCslItem($key, $entry['type'], $fields),
                    $fields,
                    $rawEntries
                ),
            ];
        }
        $this->augmentReferenceProvenance($entries);

        return $entries;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function cslItems(string $source): array
    {
        $items = [];
        foreach ($this->parseBibtex($source) as $key => $entry) {
            $items[$key] = $entry['csl'];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public function citedKeys(AstNode $document): array
    {
        $keys = [];
        $seen = [];
        $walk = function (AstNode $node) use (&$walk, &$keys, &$seen): void {
            if ($node->type === 'citation') {
                $nodeKeys = $node->attr('ids');
                if (!is_array($nodeKeys)) {
                    $nodeKeys = [(string) $node->attr('id', '')];
                }

                foreach ($nodeKeys as $key) {
                    $key = (string) $key;
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $keys[] = $key;
                }
            }

            foreach ($node->children as $child) {
                $walk($child);
            }
        };
        $walk($document);

        return $keys;
    }

    /**
     * @return array{citedKeys:list<string>, missingKeys:list<string>, items:list<array<string, mixed>>, bibliography:AstNode}
     */
    public function citationHandoff(AstNode $document, string $bibtex): array
    {
        $itemsByKey = $this->cslItems($bibtex);
        $itemsByCitationKey = $this->itemsByCitationKey($itemsByKey);
        $citedKeys = $this->citedKeys($document);
        $items = [];
        $missing = [];
        $includedItemIds = [];

        foreach ($citedKeys as $key) {
            if (!isset($itemsByCitationKey[$key])) {
                $missing[] = $key;
                continue;
            }

            $item = $itemsByCitationKey[$key];
            $itemId = (string) ($item['id'] ?? '');
            if ($itemId !== '' && isset($includedItemIds[$itemId])) {
                continue;
            }

            if ($itemId !== '') {
                $includedItemIds[$itemId] = true;
            }
            $items[] = $item;
        }

        return [
            'citedKeys' => $citedKeys,
            'missingKeys' => $missing,
            'items' => $items,
            'bibliography' => $this->bibliographyNode($items, $missing),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     */
    public function bibliographyNode(array $items, array $missing = []): AstNode
    {
        $children = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            $text = $this->renderBibliographyText($item);
            $children[] = new AstNode('definition_item', [
                'term' => $id,
                'cslItem' => $item,
            ], [
                new AstNode('term', ['text' => $id], [
                    new AstNode('text', ['text' => $id]),
                ]),
                new AstNode('definition', [], [
                    new AstNode('paragraph', ['text' => $text], [
                        new AstNode('text', ['text' => $text]),
                    ]),
                ]),
            ]);
        }

        foreach ($missing as $key) {
            $text = 'Missing bibliography entry: ' . $key;
            $children[] = new AstNode('definition_item', [
                'term' => $key,
                'missing' => true,
            ], [
                new AstNode('term', ['text' => $key], [
                    new AstNode('text', ['text' => $key]),
                ]),
                new AstNode('definition', [], [
                    new AstNode('paragraph', ['text' => $text], [
                        new AstNode('text', ['text' => $text]),
                    ]),
                ]),
            ]);
        }

        return new AstNode('definition_list', [
            'class' => 'csl-bibliography',
            'missingCitationKeys' => $missing,
        ], $children);
    }

    /**
     * @param array<string, mixed> $item
     */
    public function renderBibliographyText(array $item): string
    {
        $parts = [];
        $authority = $this->renderNames($item['authority'] ?? []);
        $authors = $this->renderNames($item['author'] ?? []);
        if ($authors === '' && $this->authorityActsAsAuthor($item)) {
            $authors = $authority;
        }
        if ($authors !== '') {
            $parts[] = $authors;
        }
        if (($item['title'] ?? '') !== '') {
            $parts[] = (string) $item['title'];
        }
        $seriesCreators = $this->renderNames($item['series-creator'] ?? []);
        if ($seriesCreators !== '') {
            $parts[] = 'Series creator: ' . $seriesCreators;
        }
        $citationAliases = $item['citation-aliases'] ?? [];
        if (is_array($citationAliases) && $citationAliases !== []) {
            $parts[] = 'Citation aliases: ' . implode('; ', array_map('strval', $citationAliases));
        }
        $translatedTitle = (string) ($item['translated-title'] ?? '');
        if ($translatedTitle !== '') {
            $translatedSubtitle = (string) ($item['translated-subtitle'] ?? '');
            if ($translatedSubtitle !== '') {
                $translatedTitle .= ': ' . $translatedSubtitle;
            }
            $parts[] = 'Translated title: ' . $translatedTitle;
        }

        $container = (string) ($item['container-title'] ?? '');
        $volume = (string) ($item['volume'] ?? '');
        $issue = (string) ($item['issue'] ?? '');
        if ($container !== '') {
            $containerPart = $container;
            if ($volume !== '') {
                $containerPart .= ' ' . $volume;
                if ($issue !== '') {
                    $containerPart .= '(' . $issue . ')';
                }
            }
            $parts[] = $containerPart;
        } elseif (($item['publisher'] ?? '') !== '') {
            $parts[] = (string) $item['publisher'];
        }

        if (($item['issue-title'] ?? '') !== '') {
            $parts[] = 'Issue title: ' . (string) $item['issue-title'];
        }
        if (($item['issue-title-addon'] ?? '') !== '') {
            $parts[] = 'Issue title addendum: ' . (string) $item['issue-title-addon'];
        }

        $year = $this->issuedYear($item);
        if ($year !== '') {
            $parts[] = $year;
        }
        foreach ([
            'available-date' => 'Available date',
            'accepted-date' => 'Accepted date',
            'revised-date' => 'Revised date',
            'submitted' => 'Submitted date',
            'label-date' => 'Label date',
        ] as $field => $label) {
            $date = $this->dateDisplay($item, $field);
            if ($date !== '') {
                $parts[] = $label . ': ' . $date;
            }
        }
        if (($item['page'] ?? '') !== '') {
            $parts[] = (string) $item['page'];
        }
        foreach ([
            'pagination' => 'Pagination',
            'book-pagination' => 'Book pagination',
            'number-of-volumes' => 'Number of volumes',
            'chapter-number' => 'Chapter number',
            'number-of-pages' => 'Number of pages',
            'part' => 'Part',
            'printing-number' => 'Printing number',
            'supplement-number' => 'Supplement number',
            'references' => 'References',
            'dimensions' => 'Dimensions',
            'division' => 'Division',
            'scale' => 'Scale',
            'entry-subtype' => 'Entry subtype',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $parts[] = $label . ': ' . (string) $item[$field];
            }
        }
        $containerTitleShort = (string) ($item['container-title-short'] ?? $item['journal-abbreviation'] ?? '');
        if ($containerTitleShort !== '') {
            $parts[] = 'Journal abbreviation: ' . rtrim($containerTitleShort, '.');
        }
        if (($item['article-number'] ?? '') !== '') {
            $parts[] = 'Article number: ' . (string) $item['article-number'];
        }
        if (($item['number'] ?? '') !== '') {
            $parts[] = 'Number: ' . (string) $item['number'];
        }
        if (($item['thesis-type'] ?? '') !== '') {
            $parts[] = 'Thesis type: ' . (string) $item['thesis-type'];
        }
        if (($item['source'] ?? '') !== '') {
            $parts[] = 'Source: ' . (string) $item['source'];
        }
        if (($item['section'] ?? '') !== '') {
            $parts[] = 'Section: ' . (string) $item['section'];
        }
        if (($item['supplement'] ?? '') !== '') {
            $parts[] = 'Supplement: ' . (string) $item['supplement'];
        }
        foreach ([
            'citation-label' => 'Citation label',
            'shorthand-intro' => 'Shorthand intro',
            'sort-shorthand' => 'Sort shorthand',
            'shorthand-list-sort-key' => 'Shorthand list sort key',
            'presort' => 'Presort',
            'sort-key' => 'Sort key',
            'sort-name' => 'Sort name',
            'sort-title' => 'Sort title',
            'index-title' => 'Index title',
            'index-sort-title' => 'Index sort title',
            'sort-year' => 'Sort year',
            'sort-initial' => 'Sort initial',
            'sort-initial-hash' => 'Sort initial hash',
            'label-prefix' => 'Label prefix',
            'label-alpha' => 'Label alpha',
            'label-title' => 'Label title',
            'extra-alpha' => 'Extra alpha',
            'extra-date' => 'Extra date',
            'extra-title' => 'Extra title',
            'date-addon' => 'Date addendum',
            'original-date-addon' => 'Original date addendum',
            'original-genre' => 'Original genre',
            'original-edition' => 'Original edition',
            'original-isbn' => 'Original ISBN',
            'original-issn' => 'Original ISSN',
            'original-doi' => 'Original DOI',
            'original-url' => 'Original URL',
            'reprint-date-addon' => 'Reprint date addendum',
            'event-date-addon' => 'Event date addendum',
            'accessed-date-addon' => 'Accessed date addendum',
            'original-collection-title' => 'Original collection title',
            'original-collection-number' => 'Original collection number',
            'collection-title' => 'Collection title',
            'collection-title-short' => 'Collection title abbreviation',
            'collection-number' => 'Collection number',
            'version' => 'Version',
            'status' => 'Status',
            'medium' => 'Medium',
            'note' => 'Note',
            'addendum' => 'Addendum',
            'name-addon' => 'Name addendum',
            'author-type' => 'Author type',
            'container-author-type' => 'Container author type',
            'related' => 'Related',
            'related-type' => 'Related type',
            'related-string' => 'Related string',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $value = (string) $item[$field];
                if (
                    $field === 'shorthand-list-sort-key'
                    && ($value === (string) ($item['sort-shorthand'] ?? '') || $value === (string) ($item['shorthand'] ?? ''))
                ) {
                    continue;
                }
                if ($field === 'volume-title-short' || $field === 'collection-title-short') {
                    $value = rtrim($value, '.');
                }
                $parts[] = $label . ': ' . $value;
            }
        }
        if (($item['rights'] ?? '') !== '') {
            $parts[] = 'Rights: ' . (string) $item['rights'];
        }
        $customFieldSummary = $this->biblatexCustomFieldSummary($item['biblatex-custom-fields'] ?? []);
        if ($customFieldSummary !== '') {
            $parts[] = 'BibLaTeX custom fields: ' . $customFieldSummary;
        }
        $customListSummary = $this->biblatexCustomListSummary($item['biblatex-custom-lists'] ?? []);
        if ($customListSummary !== '') {
            $parts[] = 'BibLaTeX custom lists: ' . $customListSummary;
        }
        $customNameSummary = $this->biblatexCustomNameSummary($item['biblatex-custom-names'] ?? []);
        if ($customNameSummary !== '') {
            $parts[] = 'BibLaTeX custom names: ' . $customNameSummary;
        }
        $fieldAnnotationSummary = $this->biblatexFieldAnnotationSummary($item['biblatex-field-annotations'] ?? []);
        if ($fieldAnnotationSummary !== '') {
            $parts[] = 'BibLaTeX field annotations: ' . $fieldAnnotationSummary;
        }
        $editorialRoleSummary = $this->biblatexEditorialRoleSummary($item['editorial-roles'] ?? []);
        if ($editorialRoleSummary !== '') {
            $parts[] = 'BibLaTeX editorial roles: ' . $editorialRoleSummary;
        }
        $optionSummary = $this->biblatexOptionSummary($item['biblatex-options'] ?? []);
        if ($optionSummary !== '') {
            $parts[] = 'BibLaTeX options: ' . $optionSummary;
        }
        $languageOptionSummary = $this->biblatexOptionSummary($item['biblatex-language-options'] ?? []);
        if ($languageOptionSummary !== '') {
            $parts[] = 'BibLaTeX language options: ' . $languageOptionSummary;
        }
        $relatedSummary = trim((string) ($item['relatedSummary'] ?? $item['related-summary'] ?? ''));
        if ($relatedSummary !== '') {
            $parts[] = $relatedSummary;
        }
        $relatedOptionSummary = $this->biblatexOptionSummary($item['relatedOptions'] ?? []);
        if ($relatedOptionSummary !== '') {
            $parts[] = 'Related options: ' . $relatedOptionSummary;
        }
        $emittedXrefSummary = false;
        $crossrefSummary = trim((string) ($item['crossrefSummary'] ?? ''));
        $xrefSummary = trim((string) ($item['xrefSummary'] ?? $item['xref-summary'] ?? ''));
        $shouldRenderXrefSummary = $crossrefSummary === '' || ($item['related'] ?? '') !== '';
        if ($xrefSummary !== '' && $shouldRenderXrefSummary) {
            $parts[] = $xrefSummary;
            $emittedXrefSummary = true;
        } elseif (($item['xref'] ?? '') !== '' && $shouldRenderXrefSummary) {
            $parts[] = 'Xref: ' . (string) $item['xref'];
            $emittedXrefSummary = true;
        }
        $referenceContextSummary = $this->biblatexReferenceContextSummary(
            (string) ($item['biblatex-refsection'] ?? ''),
            (string) ($item['biblatex-refsegment'] ?? '')
        );
        if ($referenceContextSummary !== '') {
            $parts[] = 'BibLaTeX reference context: ' . $referenceContextSummary;
        }
        if (($item['entrySetSummary'] ?? '') !== '') {
            $parts[] = 'BibLaTeX entry set: ' . (string) $item['entrySetSummary'];
        }
        if ($crossrefSummary !== '' && !$emittedXrefSummary) {
            $parts[] = 'BibLaTeX crossref parent: ' . $crossrefSummary;
        }
        if (($item['xdataSummary'] ?? '') !== '') {
            $parts[] = 'BibLaTeX xdata packets: ' . (string) $item['xdataSummary'];
        }
        if (($item['gender'] ?? '') !== '') {
            $parts[] = 'BibLaTeX gender: ' . (string) $item['gender'];
        }
        if ($authority !== '' && $authority !== $authors) {
            $parts[] = 'Authority: ' . $authority;
        }
        if (($item['jurisdiction'] ?? '') !== '') {
            $parts[] = 'Jurisdiction: ' . (string) $item['jurisdiction'];
        }
        if (($item['annotation'] ?? '') !== '') {
            $parts[] = 'Annotation: ' . rtrim((string) $item['annotation'], '.');
        }
        if (($item['call-number'] ?? '') !== '') {
            $parts[] = 'Call number: ' . (string) $item['call-number'];
        }
        if (($item['DOI'] ?? '') !== '') {
            $parts[] = 'doi:' . (string) $item['DOI'];
        }
        foreach ([
            'ISAN' => 'ISAN',
            'ISMN' => 'ISMN',
            'ISRN' => 'ISRN',
            'ISWC' => 'ISWC',
            'PMID' => 'PMID',
            'PMCID' => 'PMCID',
            'MRNumber' => 'MR',
            'MRClass' => 'MR class',
            'Zbl' => 'Zbl',
            'JSTOR' => 'JSTOR',
            'HDL' => 'HDL',
            'LCCN' => 'LCCN',
            'OCLC' => 'OCLC',
            'ORCID' => 'ORCID',
            'ISNI' => 'ISNI',
            'VIAF' => 'VIAF',
            'ROR' => 'ROR',
            'Wikidata' => 'Wikidata',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $parts[] = $label . ' ' . (string) $item[$field];
            }
        }
        if (($item['URL'] ?? '') !== '') {
            if (($item['URL-label'] ?? '') !== '') {
                $parts[] = 'URL label: ' . (string) $item['URL-label'];
            }
            $parts[] = (string) $item['URL'];
        }
        foreach ([
            'reviewed-title' => 'Reviewed title',
            'reviewed-genre' => 'Reviewed genre',
            'main-title' => 'Main title',
            'main-title-addon' => 'Main title addendum',
            'volume-title' => 'Volume title',
            'volume-title-short' => 'Volume title abbreviation',
            'part-title' => 'Part title',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $value = (string) $item[$field];
                if ($field === 'volume-title-short') {
                    $value = rtrim($value, '.');
                }
                $parts[] = $label . ': ' . $value;
            }
        }

        return implode('. ', $parts) . ($parts === [] ? '' : '.');
    }

    /**
     * @param array<string, string> $macros
     * @return array{0:string, 1:array<string, string>}
     */
    private function parseEntryBody(string $body, array $macros): array
    {
        $cursor = 0;
        $length = strlen($body);
        $firstComma = strpos($body, ',');
        $firstEquals = strpos($body, '=');
        $key = '';

        if ($firstEquals === false || ($firstComma !== false && $firstComma < $firstEquals)) {
            while ($cursor < $length && $body[$cursor] !== ',') {
                $key .= $body[$cursor];
                $cursor++;
            }
            $key = trim($key);
            if (($body[$cursor] ?? '') === ',') {
                $cursor++;
            }
        }

        $fields = [];
        while ($cursor < $length) {
            $this->skipWhitespace($body, $cursor);
            if (($body[$cursor] ?? '') === ',') {
                $cursor++;
                continue;
            }

            $name = strtolower($this->readFieldName($body, $cursor));
            if ($name === '') {
                break;
            }

            $this->skipWhitespace($body, $cursor);
            if (($body[$cursor] ?? '') !== '=') {
                break;
            }
            $cursor++;

            $fields[$name] = $this->readFieldValue($body, $cursor, $macros);
            $this->skipWhitespace($body, $cursor);
            if (($body[$cursor] ?? '') === ',') {
                $cursor++;
            }
        }

        return [$key, $fields];
    }

    /**
     * @param array<string, string> $macros
     */
    private function readFieldValue(string $body, int &$cursor, array $macros): string
    {
        $parts = [];
        $length = strlen($body);

        while ($cursor < $length) {
            $this->skipWhitespace($body, $cursor);
            $char = $body[$cursor] ?? '';

            if ($char === '{') {
                $end = $this->findBalancedEnd($body, $cursor + 1, '{', '}');
                if ($end === null) {
                    break;
                }
                $parts[] = $this->cleanValue(substr($body, $cursor + 1, $end - $cursor - 1), false);
                $cursor = $end + 1;
            } elseif ($char === '"') {
                $parts[] = $this->cleanValue($this->readQuotedValue($body, $cursor), false);
            } else {
                $bare = $this->readBareValue($body, $cursor);
                $parts[] = $macros[strtolower($bare)] ?? $this->cleanValue($bare);
            }

            $this->skipWhitespace($body, $cursor);
            if (($body[$cursor] ?? '') !== '#') {
                break;
            }
            $cursor++;
        }

        return trim(implode('', $parts));
    }

    private function readQuotedValue(string $body, int &$cursor): string
    {
        $cursor++;
        $start = $cursor;
        $depth = 0;
        $length = strlen($body);

        while ($cursor < $length) {
            $char = $body[$cursor];
            if ($char === '\\') {
                $cursor += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth = max(0, $depth - 1);
            } elseif ($char === '"' && $depth === 0) {
                $value = substr($body, $start, $cursor - $start);
                $cursor++;

                return $value;
            }
            $cursor++;
        }

        return substr($body, $start);
    }

    private function readBareValue(string $body, int &$cursor): string
    {
        $start = $cursor;
        $length = strlen($body);
        while ($cursor < $length && !str_contains(",# \t\r\n", $body[$cursor])) {
            $cursor++;
        }

        return substr($body, $start, $cursor - $start);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function toCslItem(string $key, string $type, array $fields): array
    {
        $item = [
            'id' => $key,
            'type' => $this->cslTypeForEntry($type, $fields),
            'rawBibtex' => [
                'type' => $type,
                'fields' => $fields,
            ],
        ];

        $title = $this->composedTitle($fields, ['title'], ['subtitle']);
        if ($title !== null && $title !== '') {
            $item['title'] = $title;
        }

        $containerTitle = $this->composedTitle($fields, [
            'journaltitle',
            'journal-title',
            'journal',
            'booktitle',
            'book-title',
            'container-title',
            'container-title-text',
            'containertitle',
            'containertitletext',
            'publication-title',
            'publicationtitle',
        ], [
            'journalsubtitle',
            'journal-subtitle',
            'booksubtitle',
            'book-subtitle',
            'container-subtitle',
            'containersubtitle',
            'publication-subtitle',
            'publicationsubtitle',
        ]);
        if ($containerTitle !== null && $containerTitle !== '') {
            $item['container-title'] = $containerTitle;
        }

        $reviewedTitle = $this->composedTitle($fields, ['reviewtitle', 'reviewedtitle', 'reviewed-title'], ['reviewsubtitle', 'reviewedsubtitle', 'reviewed-subtitle']);
        if ($reviewedTitle !== null && $reviewedTitle !== '') {
            $item['reviewed-title'] = $reviewedTitle;
        }

        $mainTitle = $this->composedTitle($fields, ['maintitle', 'main-title', 'maintitletext', 'main-title-text'], ['mainsubtitle', 'main-subtitle']);
        if ($mainTitle !== null && $mainTitle !== '') {
            $item['main-title'] = $mainTitle;
        }

        $volumeTitle = $this->composedTitle($fields, ['volumetitle', 'volume-title', 'volumetitletext', 'volume-title-text'], ['volumesubtitle', 'volume-subtitle']);
        if ($volumeTitle !== null && $volumeTitle !== '') {
            $item['volume-title'] = $volumeTitle;
        }

        $partTitle = $this->composedTitle($fields, ['parttitle', 'part-title', 'parttitletext', 'part-title-text'], ['partsubtitle', 'part-subtitle']);
        if ($partTitle !== null && $partTitle !== '') {
            $item['part-title'] = $partTitle;
        }

        $issueTitle = $this->composedTitle($fields, ['issuetitle', 'issue-title', 'issuetitletext', 'issue-title-text'], ['issuesubtitle', 'issue-subtitle']);
        if ($issueTitle !== null && $issueTitle !== '') {
            $item['issue-title'] = $issueTitle;
        }

        $originalTitle = $this->composedTitle($fields, ['origtitle', 'originaltitle', 'original-title'], ['origsubtitle', 'originalsubtitle', 'original-subtitle']);
        if ($originalTitle !== null && $originalTitle !== '') {
            $item['original-title'] = $originalTitle;
        }

        $translatedSubtitleFields = [
            'subtitletranslation',
            'subtitle-translation',
            'translatedsubtitle',
            'translated-subtitle',
            'titletranslationsubtitle',
            'title-translation-subtitle',
        ];
        $translatedTitle = $this->firstField($fields, ['titletranslation', 'title-translation', 'translatedtitle', 'translated-title']);
        if ($translatedTitle !== null && $translatedTitle !== '') {
            $item['translated-title'] = $translatedTitle;
        }

        $translatedSubtitle = $this->firstField($fields, $translatedSubtitleFields);
        if ($translatedSubtitle !== null && $translatedSubtitle !== '') {
            $item['translated-subtitle'] = $translatedSubtitle;
        }

        $stringFields = [
            'citation-label' => ['shorthand', 'label'],
            'shorthand' => ['shorthand'],
            'shorthand-intro' => ['shorthandintro', 'shorthand-intro'],
            'sort-shorthand' => ['sortshorthand', 'sort-shorthand'],
            'presort' => ['presort'],
            'sort-key' => ['sortkey', 'sort-key'],
            'sort-name' => ['sortname', 'sort-name'],
            'sort-title' => ['sorttitle', 'sort-title'],
            'index-title' => ['indextitle', 'index-title'],
            'index-sort-title' => ['indexsorttitle', 'index-sort-title'],
            'sort-year' => ['sortyear', 'sort-year'],
            'sort-initial' => ['sortinit', 'sort-initial', 'sortinitial', 'sort-initials'],
            'sort-initial-hash' => ['sortinithash', 'sort-initial-hash'],
            'label-prefix' => ['labelprefix', 'label-prefix'],
            'label-alpha' => ['labelalpha', 'label-alpha'],
            'label-title' => ['labeltitle', 'label-title'],
            'extra-alpha' => ['extraalpha', 'extra-alpha'],
            'extra-date' => ['extradate', 'extra-date'],
            'extra-title' => ['extratitle', 'extra-title'],
            'date-addon' => ['dateaddon', 'date-addon', 'dateaddendum', 'date-addendum'],
            'original-date-addon' => ['origdateaddon', 'origdate-addon', 'orig-date-addon', 'originaldateaddon', 'original-date-addon'],
            'reprint-date-addon' => ['reprintdateaddon', 'reprintdate-addon', 'reprint-date-addon', 'reprintdateaddendum', 'reprint-date-addendum'],
            'event-date-addon' => ['eventdateaddon', 'eventdate-addon', 'event-date-addon'],
            'accessed-date-addon' => ['urldateaddon', 'urldate-addon', 'url-date-addon', 'accesseddateaddon', 'accessed-date-addon'],
            'short-title' => ['shorttitle', 'short-title', 'title-short'],
            'title-addon' => ['titleaddon', 'title-addon'],
            'container-title-addon' => [
                'journaltitleaddon',
                'booktitleaddon',
                'journal-title-addon',
                'book-title-addon',
                'container-title-addon',
                'containertitleaddon',
                'publication-title-addon',
                'publicationtitleaddon',
            ],
            'main-title-addon' => ['maintitleaddon', 'main-title-addon'],
            'reviewed-genre' => ['reviewedgenre', 'reviewed-genre', 'reviewgenre', 'review-genre'],
            'volume-title-short' => ['shortvolumetitle', 'short-volume-title', 'volumetitleshort', 'volume-title-short'],
            'issue-title-addon' => ['issuetitleaddon', 'issue-title-addon', 'issuetitle-addon'],
            'container-title-short' => [
                'shortjournal',
                'short-journal',
                'shortjournaltitle',
                'short-journal-title',
                'shortjournal-title',
                'journaltitle-short',
                'journaltitleshort',
                'journal-title-short',
                'journalabbreviation',
                'journal-abbreviation',
                'container-title-short',
                'containertitleshort',
            ],
            'journal-abbreviation' => [
                'shortjournal',
                'short-journal',
                'shortjournaltitle',
                'short-journal-title',
                'shortjournal-title',
                'journaltitle-short',
                'journaltitleshort',
                'journal-title-short',
                'journalabbreviation',
                'journal-abbreviation',
                'container-title-short',
                'containertitleshort',
            ],
            'event' => ['eventtitle', 'event-title', 'event'],
            'event-title-addon' => ['eventtitleaddon', 'event-title-addon'],
            'event-place' => ['venue', 'eventvenue', 'eventlocation', 'eventplace', 'event-place', 'event-location'],
            'event-type' => ['eventtype', 'event-type'],
            'edition' => ['edition'],
            'number' => ['number'],
            'volume' => ['volume'],
            'number-of-volumes' => ['volumes'],
            'issue' => ['issue'],
            'page' => ['pages', 'page'],
            'pagination' => ['pagination', 'page-label'],
            'book-pagination' => ['bookpagination', 'book-pagination'],
            'article-number' => ['eid', 'article-number', 'articlenumber'],
            'references' => ['references'],
            'dimensions' => ['dimensions', 'dimension'],
            'scale' => ['scale'],
            'number-of-pages' => ['pagetotal', 'numpages', 'numberofpages', 'number-of-pages'],
            'chapter-number' => ['chapter'],
            'division' => ['division', 'subdivision', 'sub-division', 'sub_division'],
            'source' => ['source', 'sourcetitle', 'source-title'],
            'section' => ['section'],
            'part' => ['part', 'part-number', 'partnumber'],
            'printing-number' => ['printingnumber', 'printing-number', 'printnumber', 'print-number', 'printing'],
            'supplement-number' => ['supplementnumber', 'supplement-number'],
            'supplement' => ['supplement'],
            'DOI' => ['doi'],
            'URL' => ['url'],
            'URL-label' => ['urldescription', 'urltitle', 'urllabel', 'url-label'],
            'rights' => ['rights', 'copyright', 'license', 'licence'],
            'publisher' => ['publisher', 'institution', 'school', 'organization'],
            'publisher-place' => ['address', 'location', 'publisher-place'],
            'collection-title' => ['series', 'series-title', 'seriestitle', 'series-title-text', 'seriestitletext', 'collection-title', 'collectiontitle'],
            'collection-title-short' => ['shortseries', 'short-series', 'series-short', 'series-title-short', 'seriestitleshort', 'shortcollection', 'collection-title-short', 'collectiontitleshort'],
            'collection-number' => ['seriesnumber', 'series-number', 'collectionnumber', 'collection-number'],
            'original-collection-title' => ['origseries', 'orig-series', 'originalseries', 'original-series', 'original-collection-title', 'originalcollectiontitle'],
            'original-collection-number' => ['origseriesnumber', 'orig-series-number', 'originalseriesnumber', 'original-series-number', 'original-collection-number', 'originalcollectionnumber'],
            'version' => ['version'],
            'status' => ['status', 'publication-status', 'publicationstatus', 'pubstate'],
            'medium' => ['howpublished', 'medium'],
            'ISBN' => ['isbn'],
            'ISSN' => ['issn'],
            'ISAN' => ['isan'],
            'ISMN' => ['ismn'],
            'ISRN' => ['isrn'],
            'ISWC' => ['iswc'],
            'PMID' => ['pmid', 'pubmed', 'pubmedid', 'pubmed-id'],
            'PMCID' => ['pmcid', 'pmc', 'pmc-id', 'pmcid-id'],
            'MRNumber' => ['mrnumber', 'mr-number', 'mr', 'mathscinet'],
            'MRClass' => ['mrclass', 'mr-class'],
            'Zbl' => ['zbl', 'zbmath'],
            'JSTOR' => ['jstor', 'jstorid', 'jstor-id'],
            'HDL' => ['hdl', 'handle', 'hdlid', 'hdl-id', 'handleid', 'handle-id'],
            'LCCN' => ['lccn', 'lccnnumber', 'lccn-number'],
            'OCLC' => ['oclc', 'oclcnumber', 'oclc-number'],
            'ORCID' => ['orcid', 'orcidid', 'orcid-id'],
            'ISNI' => ['isni'],
            'VIAF' => ['viaf'],
            'ROR' => ['ror'],
            'Wikidata' => ['wikidata', 'wikidataid', 'wikidata-id', 'wd'],
            'archive' => ['archiveprefix', 'eprinttype', 'archive'],
            'archive-collection' => ['archivecollection', 'archive-collection', 'archive_collection'],
            'archive-place' => ['eprintclass', 'eprint-class', 'primaryclass', 'primary-class', 'primary_class', 'archiveplace', 'archive-place'],
            'archive_location' => ['eprint', 'archive-location', 'archive_location', 'archivelocation'],
            'call-number' => ['callnumber', 'call-number', 'library', 'shelfmark', 'shelf-mark'],
            'language' => ['language', 'langid', 'hyphenation'],
            'original-title-addon' => ['origtitleaddon', 'origtitle-addon', 'originaltitleaddon', 'original-title-addon'],
            'original-publisher' => ['origpublisher', 'originalpublisher', 'original-publisher'],
            'original-publisher-place' => ['origlocation', 'origaddress', 'originalpublisherplace', 'original-publisher-place'],
            'original-language' => ['origlanguage', 'originallanguage', 'original-language'],
            'original-genre' => ['origtype', 'origgenre', 'originaltype', 'original-type', 'originalgenre', 'original-genre'],
            'original-edition' => ['origedition', 'orig-edition', 'originaledition', 'original-edition'],
            'original-isbn' => ['origisbn', 'orig-isbn', 'originalisbn', 'original-isbn'],
            'original-issn' => ['origissn', 'orig-issn', 'originalissn', 'original-issn'],
            'original-doi' => ['origdoi', 'orig-doi', 'originaldoi', 'original-doi'],
            'original-url' => ['origurl', 'orig-url', 'originalurl', 'original-url'],
            'abstract' => ['abstract', 'annotation', 'annote'],
            'annotation' => ['annotation', 'annote'],
            'note' => ['note'],
            'addendum' => ['addendum'],
            'name-addon' => ['nameaddon', 'name-addon'],
            'author-type' => ['authortype', 'author-type'],
            'container-author-type' => ['bookauthortype', 'bookauthor-type', 'container-author-type'],
            'genre' => ['type', 'entrysubtype'],
            'entry-subtype' => ['entrysubtype', 'entry-subtype'],
            'related' => ['related'],
            'related-type' => ['relatedtype', 'related-type'],
            'related-string' => ['relatedstring', 'related-string'],
            'related-options' => ['relatedoptions', 'related-options'],
            'xref' => ['xref', 'crossref'],
            'gender' => ['gender'],
        ];

        foreach ($stringFields as $target => $names) {
            $value = $this->firstField($fields, $names);
            if ($value === null || $value === '') {
                continue;
            }
            $item[$target] = $target === 'page' ? str_replace('--', '-', $value) : $value;
        }
        if (($item['index-title'] ?? '') !== '' && ($item['index-sort-title'] ?? '') === '') {
            $item['index-sort-title'] = (string) $item['index-title'];
        }
        $this->applyLiteralListField($item, $fields, 'publisher', ['publisher', 'institution', 'school', 'organization'], 'publisher-list');
        $this->applyLiteralListField($item, $fields, 'publisher-place', ['address', 'location', 'publisher-place'], 'publisher-place-list');
        $this->applyLiteralListField($item, $fields, 'event-place', ['venue', 'eventvenue', 'eventlocation', 'eventplace', 'event-place', 'event-location'], 'event-place-list');
        $this->applyLiteralListField($item, $fields, 'language', ['language', 'langid', 'hyphenation'], 'language-list');
        $this->applyLiteralListField($item, $fields, 'original-publisher', ['origpublisher', 'originalpublisher', 'original-publisher'], 'original-publisher-list');
        $this->applyLiteralListField($item, $fields, 'original-publisher-place', ['origlocation', 'origaddress', 'originalpublisherplace', 'original-publisher-place'], 'original-publisher-place-list');
        $this->applyLiteralListField($item, $fields, 'original-language', ['origlanguage', 'originallanguage', 'original-language'], 'original-language-list');
        $jurisdiction = $this->firstField($fields, ['jurisdiction']);
        if (($jurisdiction === null || $jurisdiction === '') && $this->itemTypeCarriesLegalJurisdiction((string) $item['type'])) {
            $jurisdiction = $this->firstField($fields, ['location', 'address']);
        }
        if ($jurisdiction !== null && $jurisdiction !== '') {
            $item['jurisdiction'] = $jurisdiction;
        }
        $number = $this->firstField($fields, ['number']);
        if (($item['issue'] ?? '') === '' && $number !== null && $number !== '' && $this->entryNumberActsAsIssue($type)) {
            $item['issue'] = $number;
            unset($item['number']);
        }
        $this->normalizeIdentifierFields($item);

        $thesisType = $this->thesisTypeForEntry($type, $fields);
        if ($thesisType !== null && $thesisType !== '') {
            $item['thesis-type'] = $thesisType;
        }

        $shorthandListSortKey = $this->firstField($fields, ['listshorthand', 'list-shorthand', 'shorthandlistsortkey', 'shorthand-list-sort-key']);
        if ($shorthandListSortKey === null || $shorthandListSortKey === '') {
            $shorthandListSortKey = $this->firstField($fields, ['sortshorthand', 'sort-shorthand']);
        }
        if ($shorthandListSortKey === null || $shorthandListSortKey === '') {
            $shorthandListSortKey = $this->firstField($fields, ['shorthand']);
        }
        if ($shorthandListSortKey !== null && $shorthandListSortKey !== '') {
            $item['shorthand-list-sort-key'] = $shorthandListSortKey;
        }

        $nameFields = [
            'author' => ['author'],
            'editor' => ['editor'],
            'short-author' => ['shortauthor', 'short-author'],
            'short-editor' => ['shorteditor', 'short-editor'],
            'holder' => ['holder'],
            'authority' => ['authority', 'authority-list', 'authoritylist', 'issuingauthority', 'issuing-authority', 'issuingauthoritylist', 'issuing-authority-list'],
            'translator' => ['translator'],
            'chair' => ['chair'],
            'container-author' => ['bookauthor', 'container-author'],
            'editor-translator' => ['editortranslator', 'editor-translator'],
            'original-author' => ['origauthor', 'originalauthor', 'original-author'],
            'recipient' => ['recipient'],
            'reviewed-author' => ['reviewedauthor', 'reviewed-author'],
            'interviewer' => ['interviewer'],
            'compiler' => ['compiler'],
            'composer' => ['composer'],
            'contributor' => ['contributor'],
            'producer' => ['producer'],
            'performer' => ['performer'],
            'narrator' => ['narrator'],
            'host' => ['host'],
            'guest' => ['guest'],
            'executive-producer' => ['executiveproducer', 'executive-producer'],
            'script-writer' => ['scriptwriter', 'script-writer'],
            'director' => ['director'],
            'editorial-director' => ['editorialdirector', 'editorial-director'],
            'illustrator' => ['illustrator'],
            'curator' => ['curator'],
            'collection-editor' => ['serieseditor', 'series-editor', 'collectioneditor', 'collection-editor'],
            'redactor' => ['redactor'],
            'commentator' => ['commentator'],
            'annotator' => ['annotator'],
            'founder' => ['founder'],
            'continuator' => ['continuator'],
            'reviser' => ['reviser'],
            'collaborator' => ['collaborator'],
            'series-creator' => ['seriescreator', 'series-creator'],
            'introduction' => ['introduction'],
            'foreword' => ['foreword'],
            'afterword' => ['afterword'],
        ];

        foreach ($nameFields as $target => $names) {
            $value = $this->namesFromFirstField($fields, $names);
            if ($value !== []) {
                $item[$target] = $value;
            }
        }

        $eventOrganizer = $this->eventOrganizerNames($type, $fields);
        if ($eventOrganizer !== []) {
            $item['event-organizer'] = $eventOrganizer;
        }

        $editorialRoles = $this->editorialRolesFromFields($fields);
        foreach ($editorialRoles as $role) {
            $cslVariable = $this->editorialRoleCslNameVariable((string) $role['type']);
            if ($cslVariable === null) {
                continue;
            }

            $existing = $item[$cslVariable] ?? [];
            $item[$cslVariable] = [
                ...(is_array($existing) ? $existing : []),
                ...$role['names'],
            ];
        }
        if ($editorialRoles !== []) {
            $item['editorial-roles'] = $editorialRoles;
        }

        $citationAliases = $this->citationAliases($fields);
        if ($citationAliases !== []) {
            $item['citation-aliases'] = $citationAliases;
        }

        $date = $this->dateVariable($fields);
        if ($date !== null) {
            $item['issued'] = $date;
        }

        $accessed = $this->dateVariableFromFields(
            $fields,
            ['urldate', 'accessed', 'accessdate'],
            [['urlyear', 'url-year'], ['urlmonth', 'url-month'], ['urlday', 'url-day']],
            [['urlendyear', 'url-end-year'], ['urlendmonth', 'url-end-month'], ['urlendday', 'url-end-day']]
        )
            ?? $this->dateVariableFromFields($fields, [], ['accessedyear', 'accessedmonth', 'accessedday'])
            ?? $this->dateVariableFromFields($fields, [], ['accessyear', 'accessmonth', 'accessday']);
        if ($accessed !== null) {
            $item['accessed'] = $accessed;
        }

        $originalDate = $this->dateVariableFromFields(
            $fields,
            ['origdate', 'originaldate', 'original-date'],
            [['origyear', 'orig-year', 'originalyear', 'original-year'], ['origmonth', 'orig-month', 'originalmonth', 'original-month'], ['origday', 'orig-day', 'originalday', 'original-day']],
            [['origendyear', 'orig-end-year', 'originalendyear', 'original-end-year'], ['origendmonth', 'orig-end-month', 'originalendmonth', 'original-end-month'], ['origendday', 'orig-end-day', 'originalendday', 'original-end-day']]
        );
        if ($originalDate !== null) {
            $item['original-date'] = $originalDate;
        }

        $reprintDate = $this->dateVariableFromFields(
            $fields,
            ['reprintdate', 'reprint-date'],
            [['reprintyear', 'reprint-year'], ['reprintmonth', 'reprint-month'], ['reprintday', 'reprint-day']],
            [['reprintendyear', 'reprint-end-year'], ['reprintendmonth', 'reprint-end-month'], ['reprintendday', 'reprint-end-day']]
        );
        if ($reprintDate !== null) {
            $item['reprint-date'] = $reprintDate;
        }

        $eventDate = $this->dateVariableFromFields(
            $fields,
            ['eventdate', 'event-date'],
            [['eventyear', 'event-year'], ['eventmonth', 'event-month'], ['eventday', 'event-day']],
            [['eventendyear', 'event-end-year'], ['eventendmonth', 'event-end-month'], ['eventendday', 'event-end-day']]
        );
        if ($eventDate !== null) {
            $item['event-date'] = $eventDate;
        }

        $availableDate = $this->dateVariableFromFields(
            $fields,
            ['availabledate', 'available-date', 'available'],
            [['availableyear', 'available-year'], ['availablemonth', 'available-month'], ['availableday', 'available-day']],
            [['availableendyear', 'available-end-year'], ['availableendmonth', 'available-end-month'], ['availableendday', 'available-end-day']]
        );
        if ($availableDate !== null) {
            $item['available-date'] = $availableDate;
        }

        $acceptedDate = $this->dateVariableFromFields(
            $fields,
            ['accepteddate', 'accepted-date', 'dateaccepted', 'date-accepted'],
            [['acceptedyear', 'accepted-year'], ['acceptedmonth', 'accepted-month'], ['acceptedday', 'accepted-day']],
            [['acceptedendyear', 'accepted-end-year'], ['acceptedendmonth', 'accepted-end-month'], ['acceptedendday', 'accepted-end-day']]
        );
        if ($acceptedDate !== null) {
            $item['accepted-date'] = $acceptedDate;
        }

        $revisedDate = $this->dateVariableFromFields(
            $fields,
            ['reviseddate', 'revised-date', 'revisiondate', 'revision-date', 'daterevised', 'date-revised', 'revdate'],
            [['revisedyear', 'revised-year'], ['revisedmonth', 'revised-month'], ['revisedday', 'revised-day']],
            [['revisedendyear', 'revised-end-year'], ['revisedendmonth', 'revised-end-month'], ['revisedendday', 'revised-end-day']]
        );
        if ($revisedDate !== null) {
            $item['revised-date'] = $revisedDate;
        }

        $submittedDate = $this->dateVariableFromFields(
            $fields,
            ['submitteddate', 'submitted-date', 'submitted'],
            [['submittedyear', 'submitted-year'], ['submittedmonth', 'submitted-month'], ['submittedday', 'submitted-day']],
            [['submittedendyear', 'submitted-end-year'], ['submittedendmonth', 'submitted-end-month'], ['submittedendday', 'submitted-end-day']]
        );
        if ($submittedDate !== null) {
            $item['submitted'] = $submittedDate;
        }

        $labelDate = $this->dateVariableFromFields(
            $fields,
            ['labeldate', 'label-date'],
            [['labelyear', 'label-year'], ['labelmonth', 'label-month'], ['labelday', 'label-day']],
            [['labelendyear', 'label-end-year'], ['labelendmonth', 'label-end-month'], ['labelendday', 'label-end-day']]
        );
        if ($labelDate !== null) {
            $item['label-date'] = $labelDate;
        }

        $keywords = $this->keywordList($this->firstField($fields, ['keywords', 'keyword', 'keyword-list', 'keywordlist']));
        if ($keywords !== []) {
            $item['keyword'] = $keywords;
        }

        $categories = $this->keywordList($this->firstField($fields, ['categories', 'category', 'category-list', 'categorylist']));
        if ($categories !== []) {
            $item['categories'] = $categories;
        }

        $sourceFileField = $this->combinedSourceFileField($fields);
        $sourceFiles = $this->sourceFilesFromField($sourceFileField);
        if ($sourceFiles !== []) {
            $item['sourceFiles'] = $sourceFiles;
        }
        $sourceFileDiagnostics = $this->sourceFileDiagnosticsFromField($sourceFileField);
        if ($sourceFileDiagnostics !== []) {
            $item['sourceFileDiagnostics'] = $sourceFileDiagnostics;
        }

        $customFields = $this->biblatexCustomFieldsFromFields($fields);
        if ($customFields !== []) {
            $item['biblatex-custom-fields'] = $customFields;
        }

        $customLists = $this->biblatexCustomListsFromFields($fields);
        if ($customLists !== []) {
            $item['biblatex-custom-lists'] = $customLists;
        }

        $customNames = $this->biblatexCustomNamesFromFields($fields);
        if ($customNames !== []) {
            $item['biblatex-custom-names'] = $customNames;
        }

        $fieldAnnotations = $this->biblatexFieldAnnotationsFromFields($fields);
        if ($fieldAnnotations !== []) {
            $item['biblatex-field-annotations'] = $fieldAnnotations;
        }

        $options = $this->biblatexOptionList($fields['options'] ?? '');
        if ($options !== []) {
            $item['biblatex-options'] = $options;
        }

        $languageOptions = $this->biblatexOptionList($fields['langidopts'] ?? '');
        if ($languageOptions !== []) {
            $item['biblatex-language-options'] = $languageOptions;
        }

        $refsection = $this->firstField($fields, ['refsection', 'ref-section']);
        if ($refsection !== null && $refsection !== '') {
            $item['biblatex-refsection'] = $refsection;
        }

        $refsegment = $this->firstField($fields, ['refsegment', 'ref-segment']);
        if ($refsegment !== null && $refsegment !== '') {
            $item['biblatex-refsegment'] = $refsegment;
        }

        if (($item['archive'] ?? '') !== '' || ($item['archive-collection'] ?? '') !== '' || ($item['archive_location'] ?? '') !== '') {
            $summaryParts = [];
            foreach (['archive', 'archive-collection', 'archive-place', 'archive_location'] as $field) {
                if (($item[$field] ?? '') !== '') {
                    $summaryParts[] = (string) $item[$field];
                }
            }
            $item['archive-summary'] = implode(':', $summaryParts);
        }

        return $item;
    }

    /**
     * @param array<string, array{id:string, type:string, fields:array<string, string>, csl:array<string, mixed>}> $entries
     */
    private function augmentReferenceProvenance(array &$entries): void
    {
        foreach ($entries as $key => $entry) {
            $fields = $entry['fields'];
            $relatedKeys = $this->fieldKeyList($fields['related'] ?? '');
            if ($relatedKeys !== []) {
                $references = $this->cslReferenceItemsForKeys($relatedKeys, $entries);
                $entries[$key]['csl']['related-keys'] = $relatedKeys;
                $entries[$key]['csl']['relatedItems'] = $references['items'];
                if ($references['missing'] !== []) {
                    $entries[$key]['csl']['missing-related-keys'] = $references['missing'];
                }

                $relatedOptions = $this->fieldKeyList($this->firstRawField($fields, ['relatedoptions', 'related-options']));
                if ($relatedOptions !== []) {
                    $entries[$key]['csl']['relatedOptions'] = $relatedOptions;
                }

                $summary = $this->biblatexRelatedSummary(
                    $references['items'],
                    $references['missing'],
                    (string) ($entries[$key]['csl']['related-type'] ?? ''),
                    (string) ($entries[$key]['csl']['related-string'] ?? '')
                );
                if ($summary !== '') {
                    $entries[$key]['csl']['relatedSummary'] = $summary;
                    $entries[$key]['csl']['related-summary'] = $summary;
                }
            }

            $xrefKeys = $this->fieldKeyList($this->firstRawField($fields, ['xref', 'crossref']));
            if ($xrefKeys === []) {
                continue;
            }

            $references = $this->cslReferenceItemsForKeys($xrefKeys, $entries);
            $entries[$key]['csl']['xref-keys'] = $xrefKeys;
            $entries[$key]['csl']['xrefItems'] = $references['items'];
            if ($references['missing'] !== []) {
                $entries[$key]['csl']['missing-xref-keys'] = $references['missing'];
            }

            $summary = $this->biblatexXrefSummary($references['items'], $references['missing'], $xrefKeys);
            if ($summary !== '') {
                $entries[$key]['csl']['xrefSummary'] = $summary;
                $entries[$key]['csl']['xref-summary'] = $summary;
            }
        }
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>, csl:array<string, mixed>}> $entries
     * @return array{items:list<array<string, mixed>>, missing:list<string>}
     */
    private function cslReferenceItemsForKeys(array $keys, array $entries): array
    {
        $items = [];
        $missing = [];
        $seen = [];

        foreach ($keys as $key) {
            $key = trim($key);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $entry = $this->cslReferenceEntryForKey($key, $entries);
            if ($entry === null) {
                $missing[] = $key;
                continue;
            }

            $items[] = $this->cslReferenceItemSummary($key, $entry['csl']);
        }

        return ['items' => $items, 'missing' => $missing];
    }

    /**
     * @param array<string, array{id:string, type:string, fields:array<string, string>, csl:array<string, mixed>}> $entries
     * @return array{id:string, type:string, fields:array<string, string>, csl:array<string, mixed>}|null
     */
    private function cslReferenceEntryForKey(string $key, array $entries): ?array
    {
        if (isset($entries[$key])) {
            return $entries[$key];
        }

        foreach ($entries as $entry) {
            $aliases = $entry['csl']['citation-aliases'] ?? [];
            if (!is_array($aliases)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (trim((string) $alias) === $key) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function cslReferenceItemSummary(string $requestedKey, array $item): array
    {
        $summary = [
            'id' => (string) ($item['id'] ?? ''),
            'citationKey' => $requestedKey,
            'type' => (string) ($item['type'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
        ];

        if (is_array($item['issued'] ?? null)) {
            $summary['issued'] = $item['issued'];
        }

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     */
    private function biblatexRelatedSummary(array $items, array $missing, string $type, string $label): string
    {
        $values = $this->biblatexReferenceSummaryValues($items, $missing, []);
        if ($values === '') {
            return '';
        }

        $type = trim($type);
        $label = trim($label);
        $hasExplicitLabel = $label !== '';
        if (!$hasExplicitLabel) {
            $label = $this->defaultBiblatexRelatedTypeLabel($type);
        }
        if ($type !== '' && ($hasExplicitLabel || $label === 'Related source')) {
            $label .= ' (' . $type . ')';
        }

        return $label . ': ' . $values;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     * @param list<string> $fallbackKeys
     */
    private function biblatexXrefSummary(array $items, array $missing, array $fallbackKeys): string
    {
        $values = $this->biblatexReferenceSummaryValues($items, $missing, $fallbackKeys);

        return $values === '' ? '' : 'Xref: ' . $values;
    }

    private function defaultBiblatexRelatedTypeLabel(string $type): string
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
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     * @param list<string> $fallbackKeys
     */
    private function biblatexReferenceSummaryValues(array $items, array $missing, array $fallbackKeys): string
    {
        $values = [];
        foreach ($items as $item) {
            $display = $this->biblatexReferenceItemDisplay($item);
            if ($display !== '') {
                $values[] = $display;
            }
        }

        foreach ($missing as $key) {
            $key = trim($key);
            if ($key !== '') {
                $values[] = 'missing: ' . $key;
            }
        }

        if ($values === []) {
            $values = array_values(array_filter(
                array_map(static fn (string $key): string => trim($key), $fallbackKeys),
                static fn (string $key): bool => $key !== ''
            ));
        }

        return implode('; ', $values);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexReferenceItemDisplay(array $item): string
    {
        $label = trim((string) ($item['title'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($item['id'] ?? ''));
        }
        if ($label === '') {
            return '';
        }

        $date = $this->biblatexReferenceItemIssuedDisplay($item['issued'] ?? null);

        return $date === '' ? $label : $label . ' (' . $date . ')';
    }

    private function biblatexReferenceItemIssuedDisplay(mixed $issued): string
    {
        if (!is_array($issued)) {
            return '';
        }

        $dateParts = $issued['date-parts'] ?? null;
        if (!is_array($dateParts) || !isset($dateParts[0]) || !is_array($dateParts[0])) {
            return '';
        }

        $parts = array_values(array_filter(
            array_map(
                static fn (mixed $part): string => is_int($part) || is_string($part) ? trim((string) $part) : '',
                $dateParts[0]
            ),
            static fn (string $part): bool => $part !== ''
        ));

        return implode('-', $parts);
    }

    /**
     * @param array<string, array<string, mixed>> $itemsByKey
     * @return array<string, array<string, mixed>>
     */
    private function itemsByCitationKey(array $itemsByKey): array
    {
        $itemsByCitationKey = $itemsByKey;
        foreach ($itemsByKey as $key => $item) {
            $aliases = $item['citation-aliases'] ?? [];
            if (!is_array($aliases)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (!is_scalar($alias)) {
                    continue;
                }

                $alias = trim((string) $alias);
                if ($alias === '' || $alias === $key || isset($itemsByCitationKey[$alias])) {
                    continue;
                }

                $itemsByCitationKey[$alias] = $item;
            }
        }

        return $itemsByCitationKey;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $fields
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return array<string, mixed>
     */
    private function withBiblatexRelationMetadata(array $item, array $fields, array $entriesByKey): array
    {
        $xdata = $this->fieldKeyList($fields['xdata'] ?? '');
        if ($xdata !== []) {
            $xdataItems = $this->referencedXdataEntrySummaries($xdata, $entriesByKey);
            $missing = $this->missingXdataReferenceKeys($xdata, $entriesByKey);

            $item['xdataKeys'] = $xdata;
            $item['xdataItems'] = $xdataItems;
            $item['xdataSummary'] = $this->summarizedReferenceValues($xdataItems, $missing);
            if ($missing !== []) {
                $item['missingXdataKeys'] = $missing;
            }
        }

        $entrySet = $this->fieldKeyList($fields['entryset'] ?? '');
        if ($entrySet !== []) {
            $entrySetItems = $this->referencedEntrySummaries($entrySet, $entriesByKey);
            $missing = $this->missingReferenceKeys($entrySet, $entriesByKey);

            $item['entrySet'] = $entrySet;
            $item['entrySetItems'] = $entrySetItems;
            $item['entrySetSummary'] = $this->summarizedReferenceValues($entrySetItems, $missing);
            if ($missing !== []) {
                $item['missingEntrySetKeys'] = $missing;
            }
        }

        $crossref = $this->fieldKeyList($fields['crossref'] ?? '');
        if ($crossref !== []) {
            $crossrefItems = $this->referencedEntrySummaries($crossref, $entriesByKey);
            $missing = $this->missingReferenceKeys($crossref, $entriesByKey);

            $item['crossrefKeys'] = $crossref;
            $item['crossrefItems'] = $crossrefItems;
            $item['crossrefSummary'] = $this->summarizedReferenceValues($crossrefItems, $missing);
            if ($missing !== []) {
                $item['missingCrossrefKeys'] = $missing;
            }
        }

        return $item;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return list<array<string, mixed>>
     */
    private function referencedEntrySummaries(array $keys, array $entriesByKey): array
    {
        $summaries = [];
        foreach ($keys as $key) {
            $entry = $entriesByKey[$key] ?? null;
            if ($entry === null) {
                continue;
            }

            $fields = $this->resolveInheritedFields($entry, $entriesByKey);
            $summary = $this->toCslItem($entry['id'], $entry['type'], $fields);
            unset($summary['rawBibtex']);
            if ($entry['type'] === 'xdata' || $this->hasDataOnlyOption($entry['fields']['options'] ?? '')) {
                $summary['dataOnly'] = true;
            }
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return list<array<string, mixed>>
     */
    private function referencedXdataEntrySummaries(array $keys, array $entriesByKey): array
    {
        $summaries = [];
        foreach ($keys as $key) {
            $entry = $entriesByKey[$key] ?? null;
            if ($entry === null || $entry['type'] !== 'xdata') {
                continue;
            }

            $fields = $this->resolveInheritedFields($entry, $entriesByKey);
            $summary = $this->toCslItem($entry['id'], $entry['type'], $fields);
            unset($summary['rawBibtex']);
            $summary['dataOnly'] = true;
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return list<string>
     */
    private function missingReferenceKeys(array $keys, array $entriesByKey): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => !isset($entriesByKey[$key])
        ));
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return list<string>
     */
    private function missingXdataReferenceKeys(array $keys, array $entriesByKey): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => !isset($entriesByKey[$key]) || $entriesByKey[$key]['type'] !== 'xdata'
        ));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     */
    private function summarizedReferenceValues(array $items, array $missing): string
    {
        $values = [];
        foreach ($items as $item) {
            $label = trim((string) ($item['title'] ?? $item['id'] ?? ''));
            if ($label === '') {
                continue;
            }

            $date = $this->referenceDateLabel($item);
            $values[] = $date === '' ? $label : $label . ' (' . $date . ')';
        }

        foreach ($missing as $key) {
            $values[] = 'missing: ' . $key;
        }

        return implode('; ', $values);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function referenceDateLabel(array $item): string
    {
        $parts = $item['issued']['date-parts'][0] ?? null;
        if (!is_array($parts) || $parts === []) {
            return '';
        }

        $formatted = [];
        foreach (array_values($parts) as $index => $part) {
            if (!is_int($part) && !is_numeric($part)) {
                continue;
            }
            $formatted[] = $index === 0 ? (string) (int) $part : str_pad((string) (int) $part, 2, '0', STR_PAD_LEFT);
        }

        return implode('-', $formatted);
    }

    private function hasDataOnlyOption(string $options): bool
    {
        foreach ($this->biblatexOptionList($options) as $option) {
            $name = strtolower(trim(explode('=', $option, 2)[0]));
            if ($name === 'dataonly') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{id:string, type:string, fields:array<string, string>} $entry
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @param list<string> $stack
     * @return array<string, string>
     */
    private function resolveInheritedFields(array $entry, array $entriesByKey, array $stack = []): array
    {
        if (in_array($entry['id'], $stack, true)) {
            throw new \InvalidArgumentException('BibTeX inheritance cycle involving entry: ' . $entry['id']);
        }

        $stack[] = $entry['id'];
        $fields = $this->resolveXdataFields($entry, $entriesByKey, $stack);
        $crossref = trim($fields['crossref'] ?? '');
        if ($crossref === '' || !isset($entriesByKey[$crossref])) {
            return $fields;
        }

        $parentFields = $this->resolveInheritedFields($entriesByKey[$crossref], $entriesByKey, $stack);
        foreach ($this->crossrefInheritedFields($entry['type'], $fields, $parentFields) as $field => $value) {
            if (($fields[$field] ?? '') === '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param array{id:string, type:string, fields:array<string, string>} $entry
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @param list<string> $stack
     * @return array<string, string>
     */
    private function resolveXdataFields(array $entry, array $entriesByKey, array $stack): array
    {
        $fields = $entry['fields'];
        foreach ($this->fieldKeyList($fields['xdata'] ?? '') as $key) {
            $parent = $entriesByKey[$key] ?? null;
            if ($parent === null || $parent['type'] !== 'xdata') {
                continue;
            }

            $parentFields = $this->resolveInheritedFields($parent, $entriesByKey, $stack);
            unset($parentFields['crossref'], $parentFields['xdata']);
            foreach ($parentFields as $field => $value) {
                if (($fields[$field] ?? '') === '') {
                    $fields[$field] = $value;
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string, string> $childFields
     * @param array<string, string> $parentFields
     * @return array<string, string>
     */
    private function crossrefInheritedFields(string $childType, array $childFields, array $parentFields): array
    {
        $inherited = $parentFields;
        unset($inherited['crossref']);

        $containerField = $this->crossrefTitleContainerField($childType);
        if ($containerField !== null && !$this->hasAnyField($childFields, ['booktitle', 'journaltitle', 'journal'])) {
            $containerParts = $this->crossrefParentContainerTitleParts($containerField, $parentFields);
            if ($containerParts['title'] !== '') {
                $inherited[$containerField] = $containerParts['title'];
            }

            $subtitleField = $this->crossrefContainerSubtitleField($containerField);
            if ($containerParts['subtitle'] !== '' && !$this->hasAnyField($childFields, [$subtitleField])) {
                $inherited[$subtitleField] = $containerParts['subtitle'];
            }

            $titleAddonField = $this->crossrefContainerTitleAddonField($containerField);
            if ($containerParts['titleAddon'] !== '' && !$this->hasAnyField($childFields, [$titleAddonField])) {
                $inherited[$titleAddonField] = $containerParts['titleAddon'];
            }
        }

        unset($inherited['title'], $inherited['subtitle'], $inherited['titleaddon']);

        return $inherited;
    }

    private function crossrefTitleContainerField(string $childType): ?string
    {
        return match (strtolower($childType)) {
            'article' => 'journal',
            'bookinbook',
            'conference',
            'inbook',
            'incollection',
            'inproceedings',
            'inreference',
            'suppbook',
            'suppcollection' => 'booktitle',
            default => null,
        };
    }

    /**
     * @param array<string, string> $parentFields
     * @return array{title:string, subtitle:string, titleAddon:string}
     */
    private function crossrefParentContainerTitleParts(string $containerField, array $parentFields): array
    {
        $subtitleFields = $containerField === 'journal'
            ? ['journalsubtitle', 'booksubtitle', 'subtitle']
            : ['booksubtitle', 'journalsubtitle', 'subtitle'];
        $titleAddonFields = $containerField === 'journal'
            ? ['journaltitleaddon', 'booktitleaddon', 'titleaddon']
            : ['booktitleaddon', 'journaltitleaddon', 'titleaddon'];

        return [
            'title' => $this->firstRawField($parentFields, ['booktitle', 'journaltitle', 'journal', 'title']),
            'subtitle' => $this->firstRawField($parentFields, $subtitleFields),
            'titleAddon' => $this->firstRawField($parentFields, $titleAddonFields),
        ];
    }

    private function crossrefContainerSubtitleField(string $containerField): string
    {
        return $containerField === 'journal' ? 'journalsubtitle' : 'booksubtitle';
    }

    private function crossrefContainerTitleAddonField(string $containerField): string
    {
        return $containerField === 'journal' ? 'journaltitleaddon' : 'booktitleaddon';
    }

    /**
     * @param array<string, string> $fields
     * @return list<array{field:string, type:string, label:string, names:list<array<string, mixed>>}>
     */
    private function editorialRolesFromFields(array $fields): array
    {
        $roles = [];
        $primaryEditorType = $this->normalizedEditorialRoleType($fields['editortype'] ?? '');
        if ($primaryEditorType !== '' && $primaryEditorType !== 'editor') {
            $editorNames = $this->namesFromField($fields, 'editor');
            if ($editorNames !== []) {
                $roles[] = [
                    'field' => 'editor',
                    'type' => $primaryEditorType,
                    'label' => $this->editorialRoleLabel($primaryEditorType),
                    'names' => $editorNames,
                ];
            }
        }

        foreach ([
            ['editora', 'editoratype'],
            ['editorb', 'editorbtype'],
            ['editorc', 'editorctype'],
        ] as [$nameField, $typeField]) {
            $names = $this->namesFromField($fields, $nameField);
            if ($names === []) {
                continue;
            }

            $type = $this->normalizedEditorialRoleType($fields[$typeField] ?? 'editor');
            $roles[] = [
                'field' => $nameField,
                'type' => $type,
                'label' => $this->editorialRoleLabel($type),
                'names' => $names,
            ];
        }

        return $roles;
    }

    /**
     * @param array<string, string> $fields
     * @return list<array<string, mixed>>
     */
    private function eventOrganizerNames(string $type, array $fields): array
    {
        $organizer = $this->namesFromFirstField($fields, ['eventorganizer', 'event-organizer', 'organizer']);
        if ($organizer !== []) {
            return $organizer;
        }

        if (!in_array(strtolower($type), ['conference', 'inproceedings', 'proceedings'], true)) {
            return [];
        }

        return $this->namesFromField($fields, 'organization');
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
            'executiveproducer', 'executive-producer' => 'executive-producer',
            'reviewedauthor', 'reviewed-author' => 'reviewed-author',
            'scriptwriter', 'script-writer' => 'script-writer',
            default => $type,
        };
    }

    private function editorialRoleCslNameVariable(string $type): ?string
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

    private function editorialRoleLabel(string $type): string
    {
        $type = $this->normalizedEditorialRoleType($type);

        return match ($type) {
            'editor' => 'Editor',
            'compiler' => 'Compiler',
            'curator' => 'Curator',
            'director' => 'Director',
            'editorial-director' => 'Editorial director',
            'illustrator' => 'Illustrator',
            'interviewer' => 'Interviewer',
            'reviewed-author' => 'Reviewed author',
            'redactor' => 'Redactor',
            'founder' => 'Founder',
            'continuator' => 'Continuator',
            'reviser' => 'Reviser',
            'collaborator' => 'Collaborator',
            'organizer' => 'Organizer',
            'commentator' => 'Commentator',
            'annotator' => 'Annotator',
            'executive-producer' => 'Executive producer',
            'guest' => 'Guest',
            'host' => 'Host',
            'narrator' => 'Narrator',
            'performer' => 'Performer',
            'producer' => 'Producer',
            'script-writer' => 'Script writer',
            'introduction' => 'Introduction',
            'foreword' => 'Foreword',
            'afterword' => 'Afterword',
            default => ucfirst(strtolower(str_replace('-', ' ', $type))),
        };
    }

    /**
     * @return list<string>
     */
    private function fieldKeyList(string $value): array
    {
        $keys = [];
        foreach (preg_split('/[,;]+/', $value) ?: [] as $key) {
            $key = trim($key);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private function firstRawField(array $fields, array $names): string
    {
        foreach ($names as $name) {
            if (($fields[$name] ?? '') !== '') {
                return $fields[$name];
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private function hasAnyField(array $fields, array $names): bool
    {
        foreach ($names as $name) {
            if (($fields[$name] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function entryNumberActsAsIssue(string $type): bool
    {
        return in_array(strtolower($type), ['article', 'periodical', 'review', 'suppperiodical'], true);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function authorityActsAsAuthor(array $item): bool
    {
        return (string) ($item['type'] ?? '') === 'report';
    }

    private function itemTypeCarriesLegalJurisdiction(string $type): bool
    {
        return in_array($type, ['patent', 'legislation', 'legal_case'], true);
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $titleNames
     * @param list<string> $subtitleNames
     */
    private function composedTitle(array $fields, array $titleNames, array $subtitleNames): ?string
    {
        $title = $this->firstField($fields, $titleNames);
        if ($title === null || $title === '') {
            return null;
        }

        $subtitle = $this->firstField($fields, $subtitleNames);
        if ($subtitle === null || $subtitle === '') {
            return $title;
        }

        return $title . ': ' . $subtitle;
    }

    private function cslType(string $type): string
    {
        return match ($type) {
            'article',
            'periodical',
            'suppperiodical' => 'article-journal',
            'review' => 'review',
            'book',
            'collection',
            'manual',
            'mvbook',
            'mvcollection',
            'mvproceedings',
            'mvreference',
            'reference' => 'book',
            'bookinbook',
            'inbook',
            'incollection',
            'suppbook',
            'suppcollection' => 'chapter',
            'inreference' => 'entry-encyclopedia',
            'booklet' => 'pamphlet',
            'letter' => 'personal_communication',
            'misc' => 'document',
            'standard' => 'standard',
            'software' => 'software',
            'dataset' => 'dataset',
            'movie', 'video' => 'motion_picture',
            'audio', 'music' => 'song',
            'report', 'techreport' => 'report',
            'patent' => 'patent',
            'set' => 'entry',
            'legislation', 'legal' => 'legislation',
            'jurisdiction' => 'legal_case',
            'unpublished' => 'manuscript',
            'talk',
            'lecture',
            'presentation' => 'speech',
            'inproceedings', 'conference', 'proceedings' => 'paper-conference',
            'phdthesis', 'mastersthesis', 'thesis' => 'thesis',
            'mathesis' => 'thesis',
            'online', 'electronic', 'www' => 'webpage',
            default => 'article',
        };
    }

    /**
     * @param array<string, string> $fields
     */
    private function thesisTypeForEntry(string $type, array $fields): ?string
    {
        $explicit = $this->firstField($fields, ['thesistype', 'thesis-type']);
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        $entryType = strtolower($type);
        if (in_array($entryType, ['thesis', 'phdthesis', 'mastersthesis', 'mathesis'], true)) {
            $fieldType = $this->firstField($fields, ['type']);
            if ($fieldType !== null && $fieldType !== '') {
                return $fieldType;
            }
        }

        return match ($entryType) {
            'phdthesis' => 'phdthesis',
            'mastersthesis', 'mathesis' => 'mathesis',
            default => null,
        };
    }

    /**
     * @param array<string, string> $fields
     */
    private function cslTypeForEntry(string $type, array $fields): string
    {
        $type = strtolower($type);
        if ($type === 'unpublished' && ($this->firstField($fields, ['eventtitle', 'event-title', 'event']) ?? '') !== '') {
            return 'speech';
        }

        return $this->cslType($type);
    }

    /**
     * @param array<string, string> $fields
     */
    private function combinedSourceFileField(array $fields): ?string
    {
        $values = [];
        foreach (['file', 'pdf'] as $name) {
            $value = trim($fields[$name] ?? '');
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values === [] ? null : implode('; ', $values);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private function applyLiteralListField(array &$item, array $fields, string $target, array $names, string $listTarget): void
    {
        $value = $this->firstField($fields, $names);
        if ($value === null || $value === '') {
            return;
        }

        $values = $this->literalList($value);
        if (count($values) < 2) {
            return;
        }

        $item[$target] = implode('; ', $values);
        $item[$listTarget] = $values;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private function firstField(array $fields, array $names): ?string
    {
        foreach ($names as $name) {
            if (($fields[$name] ?? '') !== '') {
                return $fields[$name];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function normalizeIdentifierFields(array &$item): void
    {
        foreach ([
            'DOI' => [$this, 'normalizeDoiIdentifier'],
            'URL' => [$this, 'normalizeUrlIdentifier'],
            'ISBN' => [$this, 'normalizeIsbnIdentifier'],
            'ISSN' => [$this, 'normalizeIssnIdentifier'],
            'original-isbn' => [$this, 'normalizeIsbnIdentifier'],
            'original-issn' => [$this, 'normalizeIssnIdentifier'],
            'original-doi' => [$this, 'normalizeDoiIdentifier'],
            'original-url' => [$this, 'normalizeUrlIdentifier'],
            'PMID' => [$this, 'normalizePmidIdentifier'],
            'PMCID' => [$this, 'normalizePmcidIdentifier'],
        ] as $field => $normalizer) {
            $value = $item[$field] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            $normalized = $normalizer($value);
            if ($normalized !== '') {
                $item[$field] = $normalized;
            }
        }
    }

    private function normalizeDoiIdentifier(string $value): string
    {
        $value = trim($value);
        $value = trim($value, "<> \t\r\n");
        $value = preg_replace('~\A(?:doi:\s*|https?://(?:dx\.)?doi\.org/)~i', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return strtolower(trim($value));
    }

    private function normalizeUrlIdentifier(string $value): string
    {
        return trim(trim($value), '<>');
    }

    private function normalizeIsbnIdentifier(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\A(?:e-?isbn|isbn(?:-1[03])?)\s*:?\s*/i', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return strtoupper(trim($value));
    }

    private function normalizeIssnIdentifier(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\A(?:p-?|e-?|print\s+|online\s+|electronic\s+)?issn\s*:?\s*/i', '', $value) ?? $value;
        $compact = strtoupper(preg_replace('/[\s-]+/u', '', $value) ?? $value);
        if (preg_match('/\A\d{4}\d{3}[\dX]\z/', $compact) === 1) {
            return substr($compact, 0, 4) . '-' . substr($compact, 4);
        }

        return strtoupper(trim($value));
    }

    private function normalizePmidIdentifier(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : trim($value);
    }

    private function normalizePmcidIdentifier(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\Apmc(?:id)?\s*:?\s*/i', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        if (preg_match('/\A\d+\z/', $value) === 1) {
            return 'PMC' . $value;
        }

        if (preg_match('/\Apmc/i', $value) === 1) {
            return 'PMC' . substr($value, 3);
        }

        return strtoupper($value);
    }

    /**
     * @param array<string, string> $fields
     * @return array{date-parts:list<list<int>>, raw?:string, open-ended?:string}|null
     */
    private function dateVariable(array $fields): ?array
    {
        return $this->dateVariableFromFields(
            $fields,
            ['date'],
            [['year'], ['month'], ['day']],
            [['endyear', 'end-year'], ['endmonth', 'end-month'], ['endday', 'end-day']]
        );
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $dateFields
     * @param list<string|list<string>> $ymdFields
     * @param list<string|list<string>> $endYmdFields
     * @return array{date-parts:list<list<int>>, raw?:string, open-ended?:string}|null
     */
    private function dateVariableFromFields(array $fields, array $dateFields, array $ymdFields, array $endYmdFields = []): ?array
    {
        $date = '';
        foreach ($dateFields as $field) {
            if (($fields[$field] ?? '') !== '') {
                $date = $fields[$field];
                break;
            }
        }

        if ($date !== '') {
            $fromDateField = $this->dateVariableFromRawValue($date);
            if ($fromDateField !== null) {
                return $fromDateField;
            }
        }

        if ($ymdFields === []) {
            return null;
        }

        $parts = $this->datePartsFromSplitFields($fields, $ymdFields);
        if ($parts === null) {
            return null;
        }

        $dateParts = [$parts];
        if ($endYmdFields !== []) {
            $endParts = $this->datePartsFromSplitFields($fields, $endYmdFields);
            if ($endParts !== null) {
                $dateParts[] = $endParts;
            }
        }

        return ['date-parts' => $dateParts];
    }

    /**
     * @param array<string, string> $fields
     * @param list<string|list<string>> $partFields
     * @return list<int>|null
     */
    private function datePartsFromSplitFields(array $fields, array $partFields): ?array
    {
        $year = $this->firstField($fields, $this->splitDateFieldNames($partFields, 0));
        if ($year === null || $year === '') {
            return null;
        }

        $parts = [(int) $year];
        $month = $this->firstField($fields, $this->splitDateFieldNames($partFields, 1));
        if ($month !== null && $month !== '' && ctype_digit($month)) {
            $parts[] = (int) $month;
        }

        $day = $this->firstField($fields, $this->splitDateFieldNames($partFields, 2));
        if ($day !== null && $day !== '' && ctype_digit($day)) {
            if (count($parts) === 1) {
                $parts[] = 1;
            }
            $parts[] = (int) $day;
        }

        return $parts;
    }

    /**
     * @param list<string|list<string>> $partFields
     * @return list<string>
     */
    private function splitDateFieldNames(array $partFields, int $index): array
    {
        $names = $partFields[$index] ?? [];
        if (is_string($names)) {
            return [$names];
        }
        if (!is_array($names)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $name): string => is_scalar($name) ? trim((string) $name) : '', $names),
            static fn (string $name): bool => $name !== ''
        ));
    }

    /**
     * @return array{date-parts:list<list<int>>, raw?:string, open-ended?:string}|null
     */
    private function dateVariableFromRawValue(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!str_contains($value, '/')) {
            $parts = $this->datePartListFromRawValue($value, false);

            return $parts === null ? null : ['date-parts' => [$parts]];
        }

        $rangeParts = explode('/', $value, 2);
        $start = trim($rangeParts[0] ?? '');
        $end = trim($rangeParts[1] ?? '');
        if ($start === '' && $end === '') {
            return null;
        }

        $parts = [];
        $openEnded = '';
        if ($start !== '') {
            $startParts = $this->datePartListFromRawValue($start, true);
            if ($startParts === null) {
                return null;
            }
            $parts[] = $startParts;
        } else {
            $openEnded = 'start';
        }

        if ($end !== '') {
            $endParts = $this->datePartListFromRawValue($end, true);
            if ($endParts === null) {
                return null;
            }
            $parts[] = $endParts;
        } else {
            $openEnded = 'end';
        }

        if ($parts === []) {
            return null;
        }

        $date = [
            'date-parts' => $parts,
            'raw' => $value,
        ];
        if ($openEnded !== '') {
            $date['open-ended'] = $openEnded;
        }

        return $date;
    }

    /**
     * @return list<int>|null
     */
    private function datePartListFromRawValue(string $value, bool $requireFullMatch): ?array
    {
        $pattern = $requireFullMatch
            ? '/^(-?\d{1,4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?$/'
            : '/^(-?\d{1,4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?/';
        if (preg_match($pattern, $value, $m) !== 1) {
            return null;
        }

        $parts = [(int) $m[1]];
        if (($m[2] ?? '') !== '') {
            $parts[] = (int) $m[2];
        }
        if (($m[3] ?? '') !== '') {
            $parts[] = (int) $m[3];
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function keywordList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $keywords = [];
        foreach (preg_split('/[,;]+/', $value) ?: [] as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string}>
     */
    private function sourceFilesFromField(?string $value): array
    {
        return array_map(
            static fn (array $entry): array => [
                'label' => $entry['label'],
                'path' => $entry['normalizedPath'],
                'mediaType' => $entry['mediaType'],
            ],
            array_values(array_filter(
                $this->sourceFileEntriesFromField($value),
                static fn (array $entry): bool => $entry['reason'] === ''
            ))
        );
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>
     */
    private function sourceFileDiagnosticsFromField(?string $value): array
    {
        return array_map(
            static fn (array $entry): array => [
                'label' => $entry['label'],
                'path' => $entry['path'],
                'mediaType' => $entry['mediaType'],
                'reason' => $entry['reason'],
                'importable' => false,
            ],
            array_values(array_filter(
                $this->sourceFileEntriesFromField($value),
                static fn (array $entry): bool => $entry['reason'] !== ''
            ))
        );
    }

    /**
     * @return list<array{label:string, path:string, normalizedPath:string, mediaType:string, reason:string}>
     */
    private function sourceFileEntriesFromField(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $files = [];
        foreach (explode(';', $value) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $parsed = $this->parseSourceFileEntry($entry);
            $policy = $this->sourceFilePathPolicy($parsed['path']);
            $files[] = [
                'label' => $parsed['label'],
                'path' => $parsed['path'],
                'normalizedPath' => $policy['path'],
                'mediaType' => $parsed['mediaType'],
                'reason' => $policy['reason'],
            ];
        }

        return $files;
    }

    /**
     * @return array{label:string, path:string, mediaType:string}
     */
    private function parseSourceFileEntry(string $entry): array
    {
        $parts = array_map('trim', explode(':', $entry));
        if (count($parts) >= 3) {
            $label = array_shift($parts) ?? '';
            $mediaType = array_pop($parts) ?? '';
            $path = implode(':', $parts);
        } elseif (count($parts) === 2) {
            $label = '';
            [$path, $mediaType] = $parts;
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

    /**
     * @return array{path:string, reason:string}
     */
    private function sourceFilePathPolicy(string $path): array
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
     * @param array<string, string> $fields
     * @return list<string>
     */
    private function citationAliases(array $fields): array
    {
        $value = $this->firstField($fields, ['ids', 'citation-aliases', 'citationaliases', 'citation-alias', 'citationalias']);
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $alias): string => trim($alias), preg_split('/[,;]+/', $value) ?: []),
            static fn (string $alias): bool => $alias !== ''
        ));
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private function biblatexCustomFieldsFromFields(array $fields): array
    {
        $custom = [];
        foreach (self::BIBLATEX_CUSTOM_FIELDS as $field) {
            $value = trim($fields[$field] ?? '');
            if ($value !== '') {
                $custom[$field] = $value;
            }
        }

        return $custom;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<string>>
     */
    private function biblatexCustomListsFromFields(array $fields): array
    {
        $custom = [];
        foreach (self::BIBLATEX_CUSTOM_LIST_FIELDS as $field) {
            $value = trim($fields[$field] ?? '');
            if ($value === '') {
                continue;
            }

            $values = $this->literalList($value);
            if ($values !== []) {
                $custom[$field] = $values;
            }
        }

        return $custom;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<array<string, mixed>>>
     */
    private function biblatexCustomNamesFromFields(array $fields): array
    {
        $custom = [];
        foreach (self::BIBLATEX_CUSTOM_NAME_FIELDS as $field) {
            $value = trim($fields[$field] ?? '');
            if ($value === '') {
                continue;
            }

            $names = $this->namesFromField($fields, $field);
            if ($names !== []) {
                $custom[$field] = $names;
            }
        }

        return $custom;
    }

    /**
     * @return list<string>
     */
    private function biblatexOptionList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (string $option): string => trim($option),
                $this->splitTopLevel($value, ',')
            ),
            static fn (string $option): bool => $option !== ''
        ));
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<array{name:string, value:string}>>
     */
    private function biblatexFieldAnnotationsFromFields(array $fields): array
    {
        $annotations = [];
        foreach ($fields as $field => $value) {
            if (preg_match('/^([A-Za-z0-9_.-]+)\+an(?::([A-Za-z][A-Za-z0-9_-]*))?$/u', $field, $matches) !== 1) {
                continue;
            }

            $baseField = strtolower($matches[1]);
            if (in_array($baseField, self::BIBLATEX_NAME_ANNOTATION_FIELDS, true)) {
                continue;
            }

            $defaultName = $this->normalizedBiblatexFieldAnnotationName((string) ($matches[2] ?? ''));
            foreach ($this->biblatexFieldAnnotationEntries($value, $defaultName) as $annotation) {
                $annotations[$baseField][] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * @return list<array{name:string, value:string}>
     */
    private function biblatexFieldAnnotationEntries(string $value, string $defaultName): array
    {
        if (trim($value) === '') {
            return [];
        }

        $separator = str_contains($value, ';') ? ';' : ',';
        $entries = [];
        foreach ($this->splitTopLevel($value, $separator) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $name = $defaultName;
            $text = $entry;
            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)?\s*=\s*(.+)$/u', $entry, $matches) === 1) {
                if (($matches[1] ?? '') !== '') {
                    $name = $this->normalizedBiblatexFieldAnnotationName($matches[1]);
                }
                $text = $matches[2];
            }

            $text = trim($text);
            if ($text === '') {
                continue;
            }

            $entries[] = [
                'name' => $name === '' ? 'default' : $name,
                'value' => $text,
            ];
        }

        return $entries;
    }

    private function normalizedBiblatexFieldAnnotationName(string $name): string
    {
        return strtolower(str_replace('_', '-', trim($name)));
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $fieldNames
     * @return list<array<string, mixed>>
     */
    private function namesFromFirstField(array $fields, array $fieldNames): array
    {
        foreach ($fieldNames as $field) {
            if (!isset($fields[$field]) || trim($fields[$field]) === '') {
                continue;
            }

            return $this->namesFromField($fields, $field);
        }

        return [];
    }

    /**
     * @param array<string, string> $fields
     * @return list<array<string, mixed>>
     */
    private function namesFromField(array $fields, string $field): array
    {
        $names = $this->parseNames($fields[$field] ?? '');
        if ($names === []) {
            return [];
        }

        return $this->withBiblatexNameAnnotations($names, $this->biblatexNameAnnotationsForField($fields, $field));
    }

    /**
     * @param array<string, string> $fields
     * @return list<array{index:int, part:string, value:string}>
     */
    private function biblatexNameAnnotationsForField(array $fields, string $field): array
    {
        $annotations = [];
        $pattern = '/^' . preg_quote($field, '/') . '\\+an(?::([A-Za-z][A-Za-z0-9_-]*))?$/u';
        foreach ($fields as $name => $value) {
            if (preg_match($pattern, $name, $matches) !== 1) {
                continue;
            }

            $defaultPart = strtolower(str_replace('_', '-', trim((string) ($matches[1] ?? ''))));
            foreach ($this->biblatexNameAnnotationEntries($value, $defaultPart) as $annotation) {
                $annotations[] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * @return list<array{index:int, part:string, value:string}>
     */
    private function biblatexNameAnnotationEntries(string $value, string $defaultPart = ''): array
    {
        if (trim($value) === '') {
            return [];
        }

        $defaultPart = strtolower(str_replace('_', '-', trim($defaultPart)));
        $separator = str_contains($value, ';') ? ';' : ',';
        $annotations = [];
        foreach ($this->splitTopLevel($value, $separator) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*(?::\s*([A-Za-z][A-Za-z0-9_-]*))?\s*=\s*(.+)$/u', $entry, $matches) !== 1) {
                throw new \InvalidArgumentException('BibLaTeX name annotation is malformed: ' . $this->cleanValue($entry));
            }

            $index = (int) $matches[1];
            if ($index < 1) {
                throw new \InvalidArgumentException('BibLaTeX name annotation index must be one-based');
            }

            $text = $this->cleanValue($matches[3]);
            if ($text === '') {
                continue;
            }

            $part = strtolower(str_replace('_', '-', trim((string) ($matches[2] ?? ''))));
            $annotations[] = [
                'index' => $index,
                'part' => $part === '' ? ($defaultPart === '' ? 'name' : $defaultPart) : $part,
                'value' => $text,
            ];
        }

        return $annotations;
    }

    /**
     * @param list<array<string, mixed>> $names
     * @param list<array{index:int, part:string, value:string}> $annotations
     * @return list<array<string, mixed>>
     */
    private function withBiblatexNameAnnotations(array $names, array $annotations): array
    {
        foreach ($annotations as $annotation) {
            $index = $annotation['index'] - 1;
            if (!isset($names[$index])) {
                continue;
            }

            $existing = $names[$index]['annotations'] ?? [];
            $names[$index]['annotations'] = [
                ...(is_array($existing) ? $existing : []),
                [
                    'part' => $annotation['part'],
                    'value' => $annotation['value'],
                ],
            ];
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            $char = $value[$cursor];
            if ($char === '{' || $char === '(' || $char === '[') {
                $depth++;
            } elseif (($char === '}' || $char === ')' || $char === ']') && $depth > 0) {
                $depth--;
            }

            if ($char === $separator && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function literalList(string $value): array
    {
        $values = [];
        foreach ($this->splitNames($value) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $values[] = $part;
            }
        }

        return $values;
    }

    /**
     * @param mixed $fields
     */
    private function biblatexCustomFieldSummary(mixed $fields): string
    {
        if (!is_array($fields)) {
            return '';
        }

        $parts = [];
        foreach (self::BIBLATEX_CUSTOM_FIELDS as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $field . ': ' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param mixed $lists
     */
    private function biblatexCustomListSummary(mixed $lists): string
    {
        if (!is_array($lists)) {
            return '';
        }

        $parts = [];
        foreach (self::BIBLATEX_CUSTOM_LIST_FIELDS as $field) {
            $values = $lists[$field] ?? [];
            if (!is_array($values)) {
                continue;
            }

            $values = array_values(array_filter(
                array_map(static fn (mixed $value): string => trim((string) $value), $values),
                static fn (string $value): bool => $value !== ''
            ));
            if ($values !== []) {
                $parts[] = $field . ': ' . implode('; ', $values);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param mixed $namesByField
     */
    private function biblatexCustomNameSummary(mixed $namesByField): string
    {
        if (!is_array($namesByField)) {
            return '';
        }

        $parts = [];
        foreach (self::BIBLATEX_CUSTOM_NAME_FIELDS as $field) {
            $names = $namesByField[$field] ?? [];
            if (!is_array($names)) {
                continue;
            }

            $values = array_values(array_filter(
                array_map(fn (mixed $name): string => $this->biblatexCustomNameDisplay($name), $names),
                static fn (string $value): bool => $value !== ''
            ));
            if ($values !== []) {
                $parts[] = $field . ': ' . implode('; ', $values);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param mixed $annotations
     */
    private function biblatexFieldAnnotationSummary(mixed $annotations): string
    {
        if (!is_array($annotations)) {
            return '';
        }

        $parts = [];
        foreach ($annotations as $field => $fieldAnnotations) {
            if (!is_array($fieldAnnotations)) {
                continue;
            }

            foreach ($fieldAnnotations as $annotation) {
                if (!is_array($annotation)) {
                    continue;
                }

                $value = trim((string) ($annotation['value'] ?? ''));
                if ($value === '') {
                    continue;
                }

                $name = trim((string) ($annotation['name'] ?? 'default'));
                $parts[] = (string) $field . ' ' . ($name === '' ? 'default' : $name) . ': ' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param mixed $roles
     */
    private function biblatexEditorialRoleSummary(mixed $roles): string
    {
        if (!is_array($roles)) {
            return '';
        }

        $parts = [];
        foreach ($roles as $role) {
            if (!is_array($role)) {
                continue;
            }

            $names = $role['names'] ?? [];
            if (!is_array($names)) {
                continue;
            }

            $renderedNames = $this->renderNames($names);
            if ($renderedNames === '') {
                continue;
            }

            $field = trim((string) ($role['field'] ?? ''));
            $label = trim((string) ($role['label'] ?? $role['type'] ?? 'Editor'));
            $parts[] = ($field !== '' ? $field . ' ' : '') . ($label === '' ? 'Editor' : $label) . ': ' . $renderedNames;
        }

        return implode('; ', $parts);
    }

    /**
     * @param mixed $options
     */
    private function biblatexOptionSummary(mixed $options): string
    {
        if (!is_array($options)) {
            return '';
        }

        $values = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $options),
            static fn (string $value): bool => $value !== ''
        ));

        return implode('; ', $values);
    }

    private function biblatexReferenceContextSummary(string $refsection, string $refsegment): string
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

    private function biblatexCustomNameDisplay(mixed $name): string
    {
        if (!is_array($name)) {
            return '';
        }

        $literal = trim((string) ($name['literal'] ?? ''));
        if ($literal !== '') {
            return $literal;
        }

        $family = trim((string) ($name['family'] ?? ''));
        $given = trim((string) ($name['given'] ?? ''));
        if ($family !== '' && $given !== '') {
            return $family . ', ' . $given;
        }

        return $family !== '' ? $family : $given;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseNames(string $value): array
    {
        $names = [];
        foreach ($this->splitNames($value) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (strtolower($name) === 'others') {
                $names[] = ['literal' => 'et al.'];
                continue;
            }
            if (str_contains($name, ',')) {
                [$family, $given] = array_map('trim', explode(',', $name, 2));
                $names[] = array_filter(['family' => $family, 'given' => $given], static fn (string $part): bool => $part !== '');
                continue;
            }

            $parts = preg_split('/\s+/', $name) ?: [];
            if (count($parts) === 1) {
                $names[] = ['family' => $name];
                continue;
            }
            $family = array_pop($parts);
            $names[] = ['family' => (string) $family, 'given' => implode(' ', $parts)];
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function splitNames(string $value): array
    {
        $names = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);

        for ($cursor = 0; $cursor < $length; $cursor++) {
            $char = $value[$cursor];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth = max(0, $depth - 1);
            }

            if ($depth === 0 && substr($value, $cursor, 5) === ' and ') {
                $names[] = $buffer;
                $buffer = '';
                $cursor += 4;
                continue;
            }

            $buffer .= $char;
        }
        $names[] = $buffer;

        return $names;
    }

    /**
     * @param mixed $names
     */
    private function renderNames(mixed $names): string
    {
        if (!is_array($names) || $names === []) {
            return '';
        }

        $parts = [];
        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }
            if (($name['literal'] ?? '') !== '') {
                $parts[] = (string) $name['literal'];
                continue;
            }
            $given = (string) ($name['given'] ?? '');
            $family = (string) ($name['family'] ?? '');
            $parts[] = trim($given . ' ' . $family);
        }

        return $this->joinHumanList(array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param list<string> $parts
     */
    private function joinHumanList(array $parts): string
    {
        return match (count($parts)) {
            0 => '',
            1 => $parts[0],
            2 => $parts[0] . ' and ' . $parts[1],
            default => implode(', ', array_slice($parts, 0, -1)) . ', and ' . end($parts),
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function issuedYear(array $item): string
    {
        $parts = $item['issued']['date-parts'][0] ?? [];
        if (!is_array($parts) || !isset($parts[0])) {
            return '';
        }

        return (string) $parts[0];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateDisplay(array $item, string $field): string
    {
        $dateParts = $item[$field]['date-parts'] ?? [];
        if (!is_array($dateParts) || $dateParts === []) {
            return '';
        }

        $displays = [];
        foreach ($dateParts as $parts) {
            if (!is_array($parts)) {
                return '';
            }

            $display = $this->datePartDisplay($parts);
            if ($display === '') {
                return '';
            }
            $displays[] = $display;
        }

        if ($displays === []) {
            return '';
        }

        $openEnded = trim((string) ($item[$field]['open-ended'] ?? ''));
        if ($openEnded === 'start') {
            return '/' . $displays[0];
        }
        if ($openEnded === 'end') {
            return $displays[0] . '/';
        }

        return implode('/', $displays);
    }

    /**
     * @param list<int|string> $parts
     */
    private function datePartDisplay(array $parts): string
    {
        if ($parts === []) {
            return '';
        }

        $displayParts = [];
        foreach (array_values($parts) as $index => $part) {
            if (!is_int($part) && (!is_string($part) || !ctype_digit($part))) {
                return '';
            }

            $value = (string) ((int) $part);
            $displayParts[] = $index === 0 ? $value : str_pad($value, 2, '0', STR_PAD_LEFT);
        }

        return implode('-', $displayParts);
    }

    private function cleanValue(string $value, bool $trim = true): string
    {
        $value = $trim ? trim($value) : $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = strtr($value, [
            '\\&' => '&',
            '\\%' => '%',
            '\\_' => '_',
            '\\#' => '#',
            '\\$' => '$',
            '\\{' => '{',
            '\\}' => '}',
            '~' => ' ',
        ]);
        $value = preg_replace('/[{}]/', '', $value) ?? $value;

        return $trim ? trim($value) : $value;
    }

    private function readIdentifier(string $source, int &$cursor): string
    {
        $start = $cursor;
        $length = strlen($source);
        while ($cursor < $length && preg_match('/[A-Za-z0-9_-]/', $source[$cursor]) === 1) {
            $cursor++;
        }

        return substr($source, $start, $cursor - $start);
    }

    private function readFieldName(string $source, int &$cursor): string
    {
        $start = $cursor;
        $length = strlen($source);
        while ($cursor < $length && preg_match('/[A-Za-z0-9_.:+-]/', $source[$cursor]) === 1) {
            $cursor++;
        }

        return substr($source, $start, $cursor - $start);
    }

    private function skipWhitespace(string $source, int &$cursor): void
    {
        $length = strlen($source);
        while ($cursor < $length && ctype_space($source[$cursor])) {
            $cursor++;
        }
    }

    private function findBalancedEnd(string $source, int $cursor, string $open, string $close): ?int
    {
        $depth = 1;
        $length = strlen($source);

        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($char === '\\') {
                $cursor += 2;
                continue;
            }
            if ($char === '"') {
                $this->readQuotedValue($source, $cursor);
                continue;
            }
            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
                if ($depth === 0) {
                    return $cursor;
                }
            }
            $cursor++;
        }

        return null;
    }
}
