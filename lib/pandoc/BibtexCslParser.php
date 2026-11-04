<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibtexCslParser
{
    // These are internal byte tokens, not user-visible sentinels. The
    // protection pass byte-stuffs every source NUL, so no valid input can
    // collide with them while later LaTeX normalization rewrites the text.
    private const BIBTEX_PROTECTED_TOKEN_PREFIX = "\0";
    private const BIBTEX_PROTECTED_NUL_TOKEN = "\0\0";
    private const BIBTEX_PROTECTED_OPEN_BRACE_TOKEN = "\0\x01";
    private const BIBTEX_PROTECTED_CLOSE_BRACE_TOKEN = "\0\x02";
    private const BIBTEX_GENERATED_BACKSLASH_TOKEN = "\0\x03";
    private const BIBLATEX_CUSTOM_FIELDS = ['usera', 'userb', 'userc', 'userd', 'usere', 'userf', 'verba', 'verbb', 'verbc'];
    private const BIBLATEX_CUSTOM_LIST_FIELDS = ['lista', 'listb', 'listc', 'listd', 'liste', 'listf'];
    private const BIBLATEX_CUSTOM_NAME_FIELDS = ['namea', 'nameb', 'namec'];
    private const BIBLATEX_NAME_ANNOTATION_FIELDS = [
        'author',
        'editor',
        'shortauthor',
        'shorteditor',
        'holder',
        'translator',
        'bookauthor',
        'chair',
        'collection-editor',
        'collectioneditor',
        'series-editor',
        'serieseditor',
        'series-creator',
        'seriescreator',
        'compiler',
        'composer',
        'contributor',
        'curator',
        'director',
        'editor-translator',
        'editorial-director',
        'editorialdirector',
        'editortranslator',
        'eventorganizer',
        'executive-producer',
        'executiveproducer',
        'guest',
        'host',
        'illustrator',
        'interviewer',
        'narrator',
        'organizer',
        'organization',
        'origauthor',
        'original-author',
        'originalauthor',
        'performer',
        'producer',
        'recipient',
        'redactor',
        'founder',
        'continuator',
        'reviser',
        'collaborator',
        'reviewed-author',
        'reviewedauthor',
        'script-writer',
        'scriptwriter',
        'commentator',
        'annotator',
        'introduction',
        'foreword',
        'afterword',
        'editora',
        'editorb',
        'editorc',
        'namea',
        'nameb',
        'namec',
    ];

    private int $offset = 0;
    private readonly int $length;

    /** @var array<string, string> */
    private array $strings;

    private function __construct(
        private readonly string $input,
        private readonly bool $allowDuplicateKeys = false,
    )
    {
        $this->length = strlen($input);
        $this->strings = self::standardStrings();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parse(string $bibtex, bool $allowDuplicateKeys = false): array
    {
        return (new self($bibtex, $allowDuplicateKeys))->parseEntries();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseEntries(): array
    {
        $entries = [];
        while (true) {
            $at = strpos($this->input, '@', $this->offset);
            if ($at === false) {
                break;
            }

            $this->offset = $at + 1;
            $this->skipWhitespace();
            $type = strtolower($this->readIdentifier());
            if ($type === '') {
                throw new \InvalidArgumentException('BibTeX entry missing entry type at byte ' . $at);
            }

            $this->skipWhitespace();
            $open = $this->peek();
            if ($open !== '{' && $open !== '(') {
                throw new \InvalidArgumentException('BibTeX entry ' . $type . ' must open with { or (');
            }

            $close = $open === '{' ? '}' : ')';
            $this->offset++;

            if ($type === 'comment') {
                $this->skipBalancedEntry($open, $close);
                continue;
            }

            if ($type === 'preamble') {
                $this->parseValue($close);
                $this->skipWhitespace();
                $this->expect($close);
                continue;
            }

            if ($type === 'string') {
                $this->parseStringEntry($close);
                continue;
            }

            $key = trim($this->readUntilTopLevel([',', $close]));
            if ($key === '') {
                throw new \InvalidArgumentException('BibTeX entry ' . $type . ' is missing a citation key');
            }

            $fields = [];
            if ($this->peek() === $close) {
                $this->offset++;
            } else {
                $this->expect(',');
                $fields = $this->parseFields($type, $key, $close);
            }

            $entries[] = [
                'type' => $type,
                'key' => $key,
                'fields' => $fields,
            ];
        }

        return self::entriesToCslItems($entries, $this->allowDuplicateKeys);
    }

    /**
     * @return array<string, string>
     */
    private function parseFields(string $type, string $key, string $close): array
    {
        $fields = [];
        while (true) {
            $this->skipWhitespace();
            if ($this->peek() === $close) {
                $this->offset++;
                break;
            }

            $field = strtolower($this->readIdentifier());
            if ($field === '') {
                throw new \InvalidArgumentException('BibTeX entry ' . $key . ' has a malformed field name');
            }

            $this->skipWhitespace();
            $this->expect('=');
            $fields[$field] = $this->parseValue($close);
            $this->skipWhitespace();

            $next = $this->peek();
            if ($next === ',') {
                $this->offset++;
                continue;
            }

            if ($next === $close) {
                $this->offset++;
                break;
            }

            throw new \InvalidArgumentException('BibTeX entry ' . $type . ':' . $key . ' field ' . $field . ' must end with comma or ' . $close);
        }

        return $fields;
    }

    private function parseStringEntry(string $close): void
    {
        while (true) {
            $this->skipWhitespace();
            if ($this->peek() === $close) {
                $this->offset++;
                return;
            }

            $name = strtolower($this->readIdentifier());
            if ($name === '') {
                throw new \InvalidArgumentException('BibTeX @string entry has a malformed name');
            }

            $this->skipWhitespace();
            $this->expect('=');
            $this->strings[$name] = self::cleanBibtexText($this->parseValue($close));
            $this->skipWhitespace();

            $next = $this->peek();
            if ($next === ',') {
                $this->offset++;
                continue;
            }

            if ($next === $close) {
                $this->offset++;
                return;
            }

            throw new \InvalidArgumentException('BibTeX @string entry must end with comma or ' . $close);
        }
    }

    private function parseValue(string $entryClose): string
    {
        $this->skipWhitespace();
        $value = $this->parseValueAtom($entryClose);

        while (true) {
            $this->skipWhitespace();
            if ($this->peek() !== '#') {
                break;
            }

            $this->offset++;
            $this->skipWhitespace();
            $value .= $this->parseValueAtom($entryClose);
        }

        return trim($value);
    }

    private function parseValueAtom(string $entryClose): string
    {
        $char = $this->peek();
        if ($char === null) {
            throw new \InvalidArgumentException('Unexpected end of BibTeX value');
        }

        if ($char === '{') {
            return $this->readBracedValue();
        }

        if ($char === '"') {
            return $this->readQuotedValue();
        }

        $token = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if ($char === '#' || $char === ',' || $char === $entryClose || ctype_space($char)) {
                break;
            }

            $token .= $char;
            $this->offset++;
        }

        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Unexpected empty BibTeX value atom');
        }

        $lookup = strtolower($token);

        return $this->strings[$lookup] ?? $token;
    }

    private function readBracedValue(): string
    {
        $this->expect('{');
        $depth = 1;
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset++];
            if ($char === '\\' && $this->offset < $this->length) {
                // A backslash protects the following delimiter in braced
                // BibTeX values just as it does in quoted values.  Counting
                // an escaped brace as structure prematurely closes the value
                // and lets the remaining entry bytes be parsed as syntax.
                $value .= $char . $this->input[$this->offset++];
                continue;
            }

            if ($char === '{') {
                $depth++;
                $value .= $char;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $value;
                }

                $value .= $char;
                continue;
            }

            $value .= $char;
        }

        throw new \InvalidArgumentException('Unterminated BibTeX braced value');
    }

    private function readQuotedValue(): string
    {
        $this->expect('"');
        $depth = 0;
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset++];
            if ($char === '\\' && $this->offset < $this->length) {
                $value .= $char . $this->input[$this->offset++];
                continue;
            }

            if ($char === '{') {
                $depth++;
                $value .= $char;
                continue;
            }

            if ($char === '}' && $depth > 0) {
                $depth--;
                $value .= $char;
                continue;
            }

            if ($char === '"' && $depth === 0) {
                return $value;
            }

            $value .= $char;
        }

        throw new \InvalidArgumentException('Unterminated BibTeX quoted value');
    }

    /**
     * @param list<string> $delimiters
     */
    private function readUntilTopLevel(array $delimiters): string
    {
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (in_array($char, $delimiters, true)) {
                return $value;
            }

            $value .= $char;
            $this->offset++;
        }

        return $value;
    }

    private function readIdentifier(): string
    {
        $identifier = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (!preg_match('/[A-Za-z0-9_:\\.\\+-]/', $char)) {
                break;
            }

            $identifier .= $char;
            $this->offset++;
        }

        return $identifier;
    }

    private function skipWhitespace(): void
    {
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (ctype_space($char)) {
                $this->offset++;
                continue;
            }

            if ($char === '%') {
                while ($this->offset < $this->length && !in_array($this->input[$this->offset], ["\n", "\r"], true)) {
                    $this->offset++;
                }
                continue;
            }

            return;
        }
    }

    private function skipBalancedEntry(string $open, string $close): void
    {
        $depth = 1;
        $inQuote = false;
        while ($this->offset < $this->length && $depth > 0) {
            $char = $this->input[$this->offset++];
            if ($char === '\\' && $inQuote && $this->offset < $this->length) {
                $this->offset++;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if ($inQuote) {
                continue;
            }

            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
            }
        }

        if ($depth !== 0) {
            throw new \InvalidArgumentException('Unterminated BibTeX comment entry');
        }
    }

    private function expect(string $expected): void
    {
        if ($this->peek() !== $expected) {
            throw new \InvalidArgumentException('Expected BibTeX token ' . $expected . ' at byte ' . $this->offset);
        }

        $this->offset++;
    }

    private function peek(): ?string
    {
        return $this->offset < $this->length ? $this->input[$this->offset] : null;
    }

    /**
     * @param list<array{type:string, key:string, fields:array<string, string>}> $entries
     * @return list<array<string, mixed>>
     */
    private static function entriesToCslItems(array $entries, bool $allowDuplicateKeys = false): array
    {
        $entriesByKey = [];
        foreach ($entries as $entry) {
            if (isset($entriesByKey[$entry['key']])) {
                if (!$allowDuplicateKeys) {
                    throw new \InvalidArgumentException('Duplicate BibTeX entry key: ' . $entry['key']);
                }

                continue;
            }

            $entriesByKey[$entry['key']] = $entry;
        }

        $items = [];
        foreach ($entries as $entry) {
            if (self::isDataEntryType($entry['type']) || self::isDataOnlyEntry($entry)) {
                continue;
            }

            $fields = self::resolveInheritedFields($entry, $entriesByKey);
            $items[] = self::withBiblatexRelationMetadata(
                self::entryToCslItem($entry['type'], $entry['key'], $fields),
                $fields,
                $entriesByKey
            );
        }

        return $items;
    }

    private static function isDataEntryType(string $type): bool
    {
        return in_array(strtolower($type), ['xdata'], true);
    }

    /**
     * @param array{type:string, key:string, fields:array<string, string>} $entry
     */
    private static function isDataOnlyEntry(array $entry): bool
    {
        return self::hasDataOnlyOption($entry['fields']['options'] ?? '');
    }

    private static function hasDataOnlyOption(string $options): bool
    {
        foreach (self::splitTopLevel(self::cleanBibtexText($options), ',') as $option) {
            $option = strtolower(trim($option));
            if ($option === '') {
                continue;
            }

            $name = trim(explode('=', $option, 2)[0]);
            if ($name === 'dataonly') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{type:string, key:string, fields:array<string, string>} $entry
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @param list<string> $stack
     * @return array<string, string>
     */
    private static function resolveInheritedFields(array $entry, array $entriesByKey, array $stack = []): array
    {
        if (in_array($entry['key'], $stack, true)) {
            throw new \InvalidArgumentException('BibTeX inheritance cycle involving entry: ' . $entry['key']);
        }

        $stack[] = $entry['key'];
        $fields = self::resolveXdataFields($entry, $entriesByKey, $stack);
        $crossref = self::cleanBibtexText($fields['crossref'] ?? '');
        if ($crossref === '' || !isset($entriesByKey[$crossref])) {
            return $fields;
        }

        $parent = $entriesByKey[$crossref];
        $parentFields = self::resolveInheritedFields($parent, $entriesByKey, $stack);
        $inherited = self::crossrefInheritedFields($entry['type'], $fields, $parentFields);
        foreach ($inherited as $field => $value) {
            if (!isset($fields[$field]) || trim($fields[$field]) === '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param array{type:string, key:string, fields:array<string, string>} $entry
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @param list<string> $stack
     * @return array<string, string>
     */
    private static function resolveXdataFields(array $entry, array $entriesByKey, array $stack): array
    {
        $fields = $entry['fields'];
        $xdata = self::biblatexKeyList($fields['xdata'] ?? '');
        if ($xdata === []) {
            return $fields;
        }

        foreach ($xdata as $key) {
            $parent = $entriesByKey[$key] ?? null;
            if ($parent === null || strtolower($parent['type']) !== 'xdata') {
                continue;
            }

            $parentFields = self::resolveInheritedFields($parent, $entriesByKey, $stack);
            unset($parentFields['xdata'], $parentFields['crossref']);
            foreach ($parentFields as $field => $value) {
                if (!isset($fields[$field]) || trim($fields[$field]) === '') {
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
    private static function crossrefInheritedFields(string $childType, array $childFields, array $parentFields): array
    {
        $inherited = $parentFields;
        unset($inherited['crossref'], $inherited['options']);

        $containerField = self::crossrefTitleContainerField($childType);
        if ($containerField !== null && !self::hasAnyField($childFields, ['booktitle', 'journaltitle', 'journal'])) {
            $containerParts = self::crossrefParentContainerTitleParts($containerField, $parentFields);
            if (trim($containerParts['title']) !== '') {
                $inherited[$containerField] = $containerParts['title'];
            }

            $subtitleField = self::crossrefContainerSubtitleField($containerField);
            if (trim($containerParts['subtitle']) !== '' && !self::hasAnyField($childFields, [$subtitleField])) {
                $inherited[$subtitleField] = $containerParts['subtitle'];
            }

            $titleAddonField = self::crossrefContainerTitleAddonField($containerField);
            if (trim($containerParts['titleAddon']) !== '' && !self::hasAnyField($childFields, [$titleAddonField])) {
                $inherited[$titleAddonField] = $containerParts['titleAddon'];
            }
        }

        unset($inherited['title'], $inherited['subtitle'], $inherited['titleaddon']);

        return $inherited;
    }

    private static function crossrefTitleContainerField(string $childType): ?string
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
    private static function crossrefParentContainerTitleParts(string $containerField, array $parentFields): array
    {
        $subtitleFields = $containerField === 'journal'
            ? ['journalsubtitle', 'booksubtitle', 'subtitle']
            : ['booksubtitle', 'journalsubtitle', 'subtitle'];
        $titleAddonFields = $containerField === 'journal'
            ? ['journaltitleaddon', 'booktitleaddon', 'titleaddon']
            : ['booktitleaddon', 'journaltitleaddon', 'titleaddon'];

        return [
            'title' => self::firstRawField($parentFields, ['booktitle', 'journaltitle', 'journal', 'title']),
            'subtitle' => self::firstRawField($parentFields, $subtitleFields),
            'titleAddon' => self::firstRawField($parentFields, $titleAddonFields),
        ];
    }

    private static function crossrefContainerSubtitleField(string $containerField): string
    {
        return $containerField === 'journal' ? 'journalsubtitle' : 'booksubtitle';
    }

    private static function crossrefContainerTitleAddonField(string $containerField): string
    {
        return $containerField === 'journal' ? 'journaltitleaddon' : 'booktitleaddon';
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private static function firstRawField(array $fields, array $names): string
    {
        foreach ($names as $name) {
            if (isset($fields[$name]) && trim($fields[$name]) !== '') {
                return $fields[$name];
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private static function hasAnyField(array $fields, array $names): bool
    {
        foreach ($names as $name) {
            if (isset($fields[$name]) && trim($fields[$name]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function sourceFileFieldNames(): array
    {
        return [
            'file',
            'pdf',
            'sourcefile',
            'source-file',
            'sourcefiles',
            'source-files',
            'sourceattachment',
            'source-attachment',
            'sourceattachments',
            'source-attachments',
            'attachment',
            'attachments',
        ];
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private static function entryToCslItem(string $type, string $key, array $fields): array
    {
        $page = self::normalizePages(self::firstField($fields, ['pages', 'page']));
        $publisherList = self::literalListFromFirstField($fields, ['publisher', 'institution', 'school', 'organization']);
        $publisherPlaceList = self::literalListFromFirstField($fields, self::publisherPlaceFieldNames($type, $fields));
        $originalPublisherList = self::literalListFromFirstField($fields, ['origpublisher', 'originalpublisher', 'original-publisher']);
        $originalPublisherPlaceList = self::literalListFromFirstField($fields, ['origlocation', 'origaddress', 'originalpublisherplace', 'original-publisher-place']);
        $languageList = self::literalListFromFirstField($fields, ['language']);
        $originalLanguageList = self::literalListFromFirstField($fields, ['origlanguage', 'originallanguage', 'original-language']);
        $eventPlaceList = self::literalListFromFirstField($fields, ['eventvenue', 'event-venue', 'eventlocation', 'event-location', 'eventplace', 'event-place', 'venue']);
        $authorityFieldNames = [
            'authority-list',
            'authoritylist',
            'issuing-authority-list',
            'issuingauthoritylist',
            'authority',
            'court',
            'institution',
            'organization',
            'issuing-authority',
            'issuingauthority',
        ];
        $authorityList = self::literalListFromFirstField($fields, $authorityFieldNames);
        $eventPlace = self::literalListDisplay($eventPlaceList);
        if ($eventPlace === '') {
            $eventPlace = self::firstField($fields, ['venue', 'eventvenue', 'event-venue', 'eventlocation', 'event-location', 'eventplace', 'event-place']);
        }
        $archive = self::firstField($fields, ['archiveprefix', 'eprinttype', 'archive']);
        $archiveCollection = self::firstField($fields, ['archivecollection', 'archive-collection', 'archive_collection']);
        $archivePlace = self::firstField($fields, ['eprintclass', 'eprint-class', 'primaryclass', 'primary-class', 'primary_class', 'archiveplace', 'archive-place']);
        $archiveLocation = self::firstField($fields, ['eprint', 'archive_location', 'archive-location', 'archivelocation']);
        $patentType = self::patentType($type, $fields);
        $item = [
            'id' => $key,
            'type' => self::cslTypeForEntry($type, $fields),
            'source' => self::firstField($fields, ['source', 'sourcetitle', 'source-title']),
            'citation-aliases' => self::biblatexKeyList($fields['ids'] ?? ''),
            'citation-label' => self::firstField($fields, ['shorthand', 'label']),
            'label' => self::firstField($fields, ['label']),
            'shorthand' => self::firstField($fields, ['shorthand']),
            'shorthand-intro' => self::firstField($fields, ['shorthandintro', 'shorthand-intro']),
            'sort-shorthand' => self::firstField($fields, ['sortshorthand', 'sort-shorthand']),
            'shorthand-list-sort-key' => self::shorthandListSortKey($fields),
            'presort' => self::firstField($fields, ['presort']),
            'sort-key' => self::firstField($fields, ['sortkey', 'sort-key']),
            'sort-name' => self::firstField($fields, ['sortname', 'sort-name']),
            'sort-title' => self::firstField($fields, ['sorttitle', 'sort-title']),
            'sort-year' => self::firstField($fields, ['sortyear', 'sort-year']),
            'sort-initial' => self::firstField($fields, ['sortinit', 'sort-initial', 'sortinitial', 'sort-initials']),
            'sort-initial-hash' => self::firstField($fields, ['sortinithash', 'sort-initial-hash']),
            'index-title' => self::firstField($fields, ['indextitle', 'index-title']),
            'index-sort-title' => self::indexSortTitle($fields),
            'label-prefix' => self::firstField($fields, ['labelprefix', 'label-prefix']),
            'label-alpha' => self::firstField($fields, ['labelalpha', 'label-alpha']),
            'label-title' => self::firstField($fields, ['labeltitle', 'label-title']),
            'extra-alpha' => self::firstField($fields, ['extraalpha', 'extra-alpha']),
            'extra-date' => self::firstField($fields, ['extradate', 'extra-date']),
            'extra-title' => self::firstField($fields, ['extratitle', 'extra-title']),
            'title' => self::composedTitle($fields, ['title'], ['subtitle']),
            'short-title' => self::firstField($fields, ['shorttitle', 'short-title', 'title-short']),
            'title-addon' => self::firstField($fields, ['titleaddon', 'title-addon']),
            'translated-title' => self::firstField($fields, ['titletranslation', 'title-translation', 'translatedtitle', 'translated-title']),
            'translated-subtitle' => self::firstField($fields, ['subtitletranslation', 'subtitle-translation', 'translatedsubtitle', 'translated-subtitle', 'titletranslationsubtitle', 'title-translation-subtitle']),
            'reviewed-title' => self::composedTitle($fields, ['reviewtitle', 'reviewedtitle', 'reviewed-title'], ['reviewsubtitle', 'reviewedsubtitle', 'reviewed-subtitle']),
            'reviewed-genre' => self::firstField($fields, ['reviewedgenre', 'reviewed-genre', 'reviewgenre', 'review-genre']),
            'container-title' => self::composedTitle(
                $fields,
                [
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
                ],
                ['journalsubtitle', 'journal-subtitle', 'booksubtitle', 'book-subtitle', 'container-subtitle', 'containersubtitle', 'publication-subtitle', 'publicationsubtitle']
            ),
            'container-title-short' => self::firstField($fields, ['shortjournal', 'shortjournaltitle', 'shortjournal-title', 'journaltitle-short', 'journalabbreviation', 'journal-abbreviation', 'container-title-short', 'containertitleshort']),
            'journalAbbreviation' => self::firstField($fields, ['shortjournal', 'shortjournaltitle', 'shortjournal-title', 'journaltitle-short', 'journalabbreviation', 'journal-abbreviation', 'container-title-short', 'containertitleshort']),
            'container-title-addon' => self::firstField($fields, ['journaltitleaddon', 'booktitleaddon', 'journal-title-addon', 'book-title-addon', 'container-title-addon', 'containertitleaddon']),
            'main-title' => self::composedTitle($fields, ['maintitle', 'main-title', 'maintitletext', 'main-title-text'], ['mainsubtitle', 'main-subtitle']),
            'main-title-addon' => self::firstField($fields, ['maintitleaddon', 'main-title-addon']),
            'volume-title' => self::composedTitle($fields, ['volumetitle', 'volume-title', 'volumetitletext', 'volume-title-text'], ['volumesubtitle', 'volume-subtitle']),
            'volume-title-short' => self::firstField($fields, ['shortvolumetitle', 'short-volume-title', 'volumetitleshort', 'volume-title-short']),
            'part-title' => self::composedTitle($fields, ['parttitle', 'part-title', 'parttitletext', 'part-title-text'], ['partsubtitle', 'part-subtitle']),
            'event' => self::firstField($fields, ['eventtitle', 'event-title', 'event']),
            'event-title-addon' => self::firstField($fields, ['eventtitleaddon', 'event-title-addon']),
            'event-place' => $eventPlace,
            'event-type' => self::firstField($fields, ['eventtype', 'event-type']),
            'publisher' => self::literalListDisplay($publisherList),
            'publisher-place' => self::literalListDisplay($publisherPlaceList),
            'page' => $page,
            'page-first' => self::firstPageFromRange($page),
            'pagination' => self::firstField($fields, ['pagination']),
            'book-pagination' => self::firstField($fields, ['bookpagination', 'book-pagination']),
            'thesis-type' => self::thesisType($type, $fields),
            'article-number' => self::firstField($fields, ['eid', 'article-number', 'articlenumber']),
            'references' => self::firstField($fields, ['references']),
            'dimensions' => self::firstField($fields, ['dimensions', 'dimension']),
            'scale' => self::firstField($fields, ['scale']),
            'division' => self::firstField($fields, ['division', 'subdivision']),
            'number' => self::firstField($fields, ['number']),
            'volume' => self::firstField($fields, ['volume']),
            'issue' => self::issueField($type, $fields),
            'issue-title' => self::composedTitle($fields, ['issuetitle', 'issue-title', 'issuetitletext', 'issue-title-text'], ['issuesubtitle', 'issue-subtitle']),
            'issue-title-addon' => self::firstField($fields, ['issuetitleaddon', 'issue-title-addon', 'issuetitle-addon']),
            'edition' => self::firstField($fields, ['edition']),
            'collection-title' => self::firstField($fields, ['series', 'series-title', 'seriestitle', 'series-title-text', 'seriestitletext', 'collection-title', 'collectiontitle', 'collection-title-text', 'collectiontitletext']),
            'collection-title-short' => self::firstField($fields, ['shortseries', 'short-series', 'series-short', 'series-title-short', 'seriestitleshort', 'shortcollection', 'collection-title-short', 'collectiontitleshort']),
            'collection-number' => self::firstField($fields, ['seriesnumber', 'series-number', 'collectionnumber', 'collection-number']),
            'number-of-volumes' => self::firstField($fields, ['volumes']),
            'number-of-pages' => self::firstField($fields, ['pagetotal', 'numpages', 'numberofpages', 'number-of-pages']),
            'chapter-number' => self::firstField($fields, ['chapter']),
            'section' => self::firstField($fields, ['section']),
            'part' => self::firstField($fields, ['part', 'part-number', 'partnumber']),
            'printing-number' => self::firstField($fields, ['printingnumber', 'printing-number', 'printnumber', 'print-number']),
            'supplement' => self::firstField($fields, ['supplement']),
            'supplement-number' => self::firstField($fields, ['supplementnumber', 'supplement-number']),
            'genre' => self::firstField($fields, ['type', 'entrysubtype']),
            'patent-type' => $patentType,
            'patent-type-label' => self::patentTypeLabel($patentType),
            'entry-subtype' => self::firstField($fields, ['entrysubtype', 'entry-subtype']),
            'gender' => self::firstField($fields, ['gender']),
            'authority' => self::literalListDisplay($authorityList) ?: self::firstField($fields, $authorityFieldNames),
            'jurisdiction' => self::firstField($fields, ['jurisdiction', 'location', 'address']),
            'status' => self::firstField($fields, ['status', 'publication-status', 'publicationstatus', 'pubstate']),
            'version' => self::firstField($fields, ['version']),
            'rights' => self::firstField($fields, ['rights', 'copyright', 'license', 'licence']),
            'DOI' => self::firstField($fields, ['doi']),
            'URL' => self::firstField($fields, ['url']),
            'URL-label' => self::firstField($fields, ['urldescription', 'urltitle', 'urllabel', 'url-label', 'url-description']),
            'ISBN' => self::firstField($fields, ['isbn', 'isbn13', 'isbn-13', 'isbn10', 'isbn-10', 'eisbn', 'e-isbn', 'electronicisbn', 'electronic-isbn']),
            'ISSN' => self::firstField($fields, ['issn', 'printissn', 'print-issn', 'pissn', 'p-issn', 'eissn', 'e-issn', 'electronicissn', 'electronic-issn', 'onlineissn', 'online-issn', 'issnonline', 'issn-online']),
            'ISAN' => self::firstField($fields, ['isan']),
            'ISMN' => self::firstField($fields, ['ismn']),
            'ISRN' => self::firstField($fields, ['isrn']),
            'ISWC' => self::firstField($fields, ['iswc']),
            'PMID' => self::firstField($fields, ['pmid', 'pubmed', 'pubmedid', 'pubmed-id']),
            'PMCID' => self::firstField($fields, ['pmcid', 'pmc', 'pmc-id', 'pmcid-id']),
            'MRNumber' => self::firstField($fields, ['mrnumber', 'mr-number', 'mr', 'mathscinet']),
            'MRClass' => self::firstField($fields, ['mrclass', 'mr-class']),
            'Zbl' => self::firstField($fields, ['zbl', 'zbmath']),
            'JSTOR' => self::firstField($fields, ['jstor', 'jstorid', 'jstor-id']),
            'HDL' => self::firstField($fields, ['hdl', 'handle', 'hdlid', 'hdl-id', 'handleid', 'handle-id']),
            'LCCN' => self::firstField($fields, ['lccn', 'lccnnumber', 'lccn-number']),
            'OCLC' => self::firstField($fields, ['oclc', 'oclcnumber', 'oclc-number']),
            'ORCID' => self::firstField($fields, ['orcid', 'orcidid', 'orcid-id']),
            'ISNI' => self::firstField($fields, ['isni']),
            'VIAF' => self::firstField($fields, ['viaf']),
            'ROR' => self::firstField($fields, ['ror']),
            'Wikidata' => self::firstField($fields, ['wikidata', 'wikidataid', 'wikidata-id', 'wd']),
            'archive' => $archive,
            'archive-collection' => $archiveCollection,
            'archive-place' => $archivePlace,
            'archive_location' => $archiveLocation,
            'archive-summary' => self::archiveSummary($archive, $archiveCollection, $archivePlace, $archiveLocation),
            'call-number' => self::firstField($fields, ['callnumber', 'call-number', 'library', 'shelfmark', 'shelf-mark']),
            'language' => self::literalListDisplay($languageList) ?: self::firstField($fields, ['langid', 'hyphenation']),
            'abstract' => self::firstField($fields, ['abstract', 'annote', 'annotation']),
            'annotation' => self::firstField($fields, ['annotation', 'annote']),
            'medium' => self::firstField($fields, ['howpublished', 'medium']),
            'note' => self::noteField($fields),
            'addendum' => self::firstField($fields, ['addendum']),
            'name-addon' => self::firstField($fields, ['nameaddon', 'name-addon']),
            'author-type' => self::firstField($fields, ['authortype', 'author-type']),
            'container-author-type' => self::firstField($fields, ['bookauthortype', 'bookauthor-type', 'container-author-type']),
            'date-addon' => self::firstField($fields, ['dateaddon', 'date-addon', 'dateaddendum', 'date-addendum']),
            'original-title' => self::composedTitle($fields, ['origtitle', 'originaltitle', 'original-title'], ['origsubtitle', 'originalsubtitle', 'original-subtitle']),
            'original-title-addon' => self::firstField($fields, ['origtitleaddon', 'origtitle-addon', 'originaltitleaddon', 'original-title-addon']),
            'original-genre' => self::firstField($fields, ['origtype', 'origgenre', 'originaltype', 'original-type', 'originalgenre', 'original-genre']),
            'original-collection-title' => self::firstField($fields, ['origseries', 'orig-series', 'originalseries', 'original-series', 'original-collection-title', 'originalcollectiontitle']),
            'original-collection-number' => self::firstField($fields, ['origseriesnumber', 'orig-series-number', 'originalseriesnumber', 'original-series-number', 'original-collection-number', 'originalcollectionnumber']),
            'original-isbn' => self::firstField($fields, ['origisbn', 'orig-isbn', 'originalisbn', 'original-isbn']),
            'original-issn' => self::firstField($fields, ['origissn', 'orig-issn', 'originalissn', 'original-issn']),
            'original-doi' => self::firstField($fields, ['origdoi', 'orig-doi', 'originaldoi', 'original-doi']),
            'original-url' => self::firstField($fields, ['origurl', 'orig-url', 'originalurl', 'original-url']),
            'original-date-addon' => self::firstField($fields, ['origdateaddon', 'origdate-addon', 'orig-date-addon', 'originaldateaddon', 'original-date-addon']),
            'original-publisher' => self::literalListDisplay($originalPublisherList),
            'original-publisher-place' => self::literalListDisplay($originalPublisherPlaceList),
            'original-language' => self::literalListDisplay($originalLanguageList),
            'reprint-title' => self::firstField($fields, ['reprinttitle', 'reprint-title']),
            'reprint-date-addon' => self::firstField($fields, ['reprintdateaddon', 'reprintdate-addon', 'reprint-date-addon', 'reprintdateaddendum', 'reprint-date-addendum']),
            'event-date-addon' => self::firstField($fields, ['eventdateaddon', 'eventdate-addon', 'event-date-addon']),
            'accessed-date-addon' => self::firstField($fields, ['urldateaddon', 'urldate-addon', 'url-date-addon', 'accesseddateaddon', 'accessed-date-addon']),
            'xdata' => self::firstField($fields, ['xdata']),
            'entryset' => self::firstField($fields, ['entryset', 'entry-set']),
            'related' => self::firstField($fields, ['related', 'related-keys', 'relatedkeys']),
            'related-type' => self::firstField($fields, ['relatedtype', 'related-type']),
            'related-string' => self::firstField($fields, ['relatedstring', 'related-string']),
            'related-options' => self::firstField($fields, ['relatedoptions', 'related-options']),
            'crossref' => self::firstField($fields, ['crossref']),
            'xref' => self::firstField($fields, ['xref']),
            'rawBibtex' => [
                'type' => $type,
                'key' => $key,
                'fields' => $fields,
            ],
        ];

        if ($publisherList !== []) {
            $item['publisher-list'] = $publisherList;
        }

        if ($publisherPlaceList !== []) {
            $item['publisher-place-list'] = $publisherPlaceList;
        }

        if ($originalPublisherList !== []) {
            $item['original-publisher-list'] = $originalPublisherList;
        }

        if ($originalPublisherPlaceList !== []) {
            $item['original-publisher-place-list'] = $originalPublisherPlaceList;
        }

        if ($originalLanguageList !== []) {
            $item['original-language-list'] = $originalLanguageList;
        }

        if ($eventPlaceList !== []) {
            $item['event-place-list'] = $eventPlaceList;
        }

        if ($languageList !== []) {
            $item['language-list'] = $languageList;
        }

        if (count($authorityList) > 1) {
            $item['authority-list'] = $authorityList;
        }

        $biblatexCustomFields = self::biblatexCustomFieldsFromFields($fields);
        if ($biblatexCustomFields !== []) {
            $item['biblatex-custom-fields'] = $biblatexCustomFields;
        }

        $biblatexCustomLists = self::biblatexCustomListsFromFields($fields);
        if ($biblatexCustomLists !== []) {
            $item['biblatex-custom-lists'] = $biblatexCustomLists;
        }

        $biblatexCustomNames = self::biblatexCustomNamesFromFields($fields);
        if ($biblatexCustomNames !== []) {
            $item['biblatex-custom-names'] = $biblatexCustomNames;
        }

        $biblatexFieldAnnotations = self::biblatexFieldAnnotationsFromFields($fields);
        if ($biblatexFieldAnnotations !== []) {
            $item['biblatex-field-annotations'] = $biblatexFieldAnnotations;
        }

        $biblatexOptions = self::biblatexOptionList($fields['options'] ?? '');
        if ($biblatexOptions !== []) {
            $item['biblatex-options'] = $biblatexOptions;
        }

        $biblatexLanguageOptions = self::biblatexOptionList($fields['langidopts'] ?? '');
        if ($biblatexLanguageOptions !== []) {
            $item['biblatex-language-options'] = $biblatexLanguageOptions;
        }

        $refsection = self::firstField($fields, ['refsection', 'ref-section']);
        if ($refsection !== '') {
            $item['biblatex-refsection'] = $refsection;
        }

        $refsegment = self::firstField($fields, ['refsegment', 'ref-segment']);
        if ($refsegment !== '') {
            $item['biblatex-refsegment'] = $refsegment;
        }

        $keywords = self::keywordList(self::firstField($fields, ['keywords', 'keyword', 'keyword-list', 'keywordlist']));
        if ($keywords !== []) {
            $item['keyword'] = $keywords;
        }

        $categories = self::keywordList(self::firstField($fields, ['categories', 'category', 'category-list', 'categorylist']));
        if ($categories !== []) {
            $item['categories'] = $categories;
        }

        $sourceFileField = self::firstField($fields, self::sourceFileFieldNames());
        $sourceFiles = self::sourceFilesFromField($sourceFileField);
        if ($sourceFiles !== []) {
            $item['sourceFiles'] = $sourceFiles;
        }
        $sourceFileDiagnostics = self::sourceFileDiagnosticsFromField($sourceFileField);
        if ($sourceFileDiagnostics !== []) {
            $item['sourceFileDiagnostics'] = $sourceFileDiagnostics;
        }

        $author = self::namesFromBibtexField($fields, 'author');
        if ($author !== []) {
            $item['author'] = $author;
        }

        $editor = self::namesFromBibtexField($fields, 'editor');
        if ($editor !== []) {
            $item['editor'] = $editor;
        }

        $shortAuthor = self::namesFromBibtexField($fields, 'shortauthor');
        if ($shortAuthor !== []) {
            $item['short-author'] = $shortAuthor;
        }

        $shortEditor = self::namesFromBibtexField($fields, 'shorteditor');
        if ($shortEditor !== []) {
            $item['short-editor'] = $shortEditor;
        }

        $holder = self::namesFromBibtexField($fields, 'holder');
        if ($holder !== []) {
            $item['holder'] = $holder;
        }

        $translator = self::namesFromBibtexField($fields, 'translator');
        if ($translator !== []) {
            $item['translator'] = $translator;
        }

        $containerAuthor = self::namesFromBibtexField($fields, 'bookauthor');
        if ($containerAuthor !== []) {
            $item['container-author'] = $containerAuthor;
        }

        $director = self::namesFromBibtexField($fields, 'director');
        if ($director !== []) {
            $item['director'] = $director;
        }

        foreach ([
            'chair' => ['chair'],
            'collection-editor' => ['collectioneditor', 'collection-editor', 'serieseditor', 'series-editor'],
            'compiler' => ['compiler'],
            'composer' => ['composer'],
            'contributor' => ['contributor'],
            'curator' => ['curator'],
            'editor-translator' => ['editortranslator', 'editor-translator'],
            'editorial-director' => ['editorialdirector', 'editorial-director'],
            'illustrator' => ['illustrator'],
            'interviewer' => ['interviewer'],
            'recipient' => ['recipient'],
            'redactor' => ['redactor'],
            'founder' => ['founder'],
            'continuator' => ['continuator'],
            'reviser' => ['reviser'],
            'collaborator' => ['collaborator'],
            'reviewed-author' => ['reviewedauthor', 'reviewed-author'],
            'series-creator' => ['seriescreator', 'series-creator'],
        ] as $cslNameVariable => $fieldNames) {
            $names = self::namesFromFirstBibtexField($fields, $fieldNames);
            if ($names !== []) {
                $item[$cslNameVariable] = $names;
            }
        }

        $eventOrganizer = self::eventOrganizerNames($type, $fields);
        if ($eventOrganizer !== []) {
            $item['event-organizer'] = $eventOrganizer;
        }

        $executiveProducer = self::namesFromFirstBibtexField($fields, ['executiveproducer', 'executive-producer']);
        if ($executiveProducer !== []) {
            $item['executive-producer'] = $executiveProducer;
        }

        $guest = self::namesFromBibtexField($fields, 'guest');
        if ($guest !== []) {
            $item['guest'] = $guest;
        }

        $host = self::namesFromBibtexField($fields, 'host');
        if ($host !== []) {
            $item['host'] = $host;
        }

        $narrator = self::namesFromBibtexField($fields, 'narrator');
        if ($narrator !== []) {
            $item['narrator'] = $narrator;
        }

        $originalAuthor = self::namesFromFirstBibtexField($fields, ['origauthor', 'originalauthor', 'original-author']);
        if ($originalAuthor !== []) {
            $item['original-author'] = $originalAuthor;
        }

        $performer = self::namesFromBibtexField($fields, 'performer');
        if ($performer !== []) {
            $item['performer'] = $performer;
        }

        $producer = self::namesFromBibtexField($fields, 'producer');
        if ($producer !== []) {
            $item['producer'] = $producer;
        }

        $scriptWriter = self::namesFromFirstBibtexField($fields, ['scriptwriter', 'script-writer']);
        if ($scriptWriter !== []) {
            $item['script-writer'] = $scriptWriter;
        }

        $commentator = self::namesFromBibtexField($fields, 'commentator');
        if ($commentator !== []) {
            $item['commentator'] = $commentator;
        }

        $annotator = self::namesFromBibtexField($fields, 'annotator');
        if ($annotator !== []) {
            $item['annotator'] = $annotator;
        }

        $introduction = self::namesFromBibtexField($fields, 'introduction');
        if ($introduction !== []) {
            $item['introduction'] = $introduction;
        }

        $foreword = self::namesFromBibtexField($fields, 'foreword');
        if ($foreword !== []) {
            $item['foreword'] = $foreword;
        }

        $afterword = self::namesFromBibtexField($fields, 'afterword');
        if ($afterword !== []) {
            $item['afterword'] = $afterword;
        }

        $editorialRoles = self::editorialRolesFromFields($fields);
        foreach ($editorialRoles as $role) {
            $cslVariable = self::editorialRoleCslNameVariable($role['type']);
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

        $issued = self::dateFromFields($fields, ['date'], ['year', 'month', 'day'], [
            'hour' => 'hour',
            'minute' => 'minute',
            'second' => 'second',
            'timezone' => 'timezone',
            'endhour' => 'endhour',
            'endminute' => 'endminute',
            'endsecond' => 'endsecond',
            'endtimezone' => 'endtimezone',
        ], ['endyear', 'endmonth', 'endday']);
        if ($issued !== null) {
            $issued = self::dateWithEra($issued, $fields, ['dateera']);
            $item['issued'] = $issued;
        }

        $originalDate = self::dateFromFields($fields, ['origdate', 'originaldate', 'original-date'], ['origyear', 'origmonth', 'origday'], [
            'hour' => 'orighour',
            'minute' => 'origminute',
            'second' => 'origsecond',
            'timezone' => 'origtimezone',
            'endhour' => 'origendhour',
            'endminute' => 'origendminute',
            'endsecond' => 'origendsecond',
            'endtimezone' => 'origendtimezone',
        ], ['origendyear', 'origendmonth', 'origendday']);
        if ($originalDate === null) {
            $originalDate = self::dateFromFields($fields, [], ['originalyear', 'originalmonth', 'originalday'], [], ['originalendyear', 'originalendmonth', 'originalendday']);
        }
        if ($originalDate !== null) {
            $originalDate = self::dateWithEra($originalDate, $fields, ['origdateera', 'originaldateera', 'original-date-era']);
            $item['original-date'] = $originalDate;
        }

        $reprintDate = self::dateFromFields($fields, ['reprintdate', 'reprint-date'], ['reprintyear', 'reprintmonth', 'reprintday'], [
            'hour' => 'reprinthour',
            'minute' => 'reprintminute',
            'second' => 'reprintsecond',
            'timezone' => 'reprinttimezone',
            'endhour' => 'reprintendhour',
            'endminute' => 'reprintendminute',
            'endsecond' => 'reprintendsecond',
            'endtimezone' => 'reprintendtimezone',
        ], ['reprintendyear', 'reprintendmonth', 'reprintendday']);
        if ($reprintDate !== null) {
            $reprintDate = self::dateWithEra($reprintDate, $fields, ['reprintdateera', 'reprint-date-era']);
            $item['reprint-date'] = $reprintDate;
        }

        $eventDate = self::dateFromFields($fields, ['eventdate', 'event-date'], ['eventyear', 'eventmonth', 'eventday'], [
            'hour' => 'eventhour',
            'minute' => 'eventminute',
            'second' => 'eventsecond',
            'timezone' => 'eventtimezone',
            'endhour' => 'eventendhour',
            'endminute' => 'eventendminute',
            'endsecond' => 'eventendsecond',
            'endtimezone' => 'eventendtimezone',
        ], ['eventendyear', 'eventendmonth', 'eventendday']);
        if ($eventDate !== null) {
            $eventDate = self::dateWithEra($eventDate, $fields, ['eventdateera', 'event-date-era']);
            $item['event-date'] = $eventDate;
        }

        $availableDate = self::dateFromFields($fields, ['availabledate', 'available-date'], ['availableyear', 'availablemonth', 'availableday'], [
            'hour' => 'availablehour',
            'minute' => 'availableminute',
            'second' => 'availablesecond',
            'timezone' => 'availabletimezone',
            'endhour' => 'availableendhour',
            'endminute' => 'availableendminute',
            'endsecond' => 'availableendsecond',
            'endtimezone' => 'availableendtimezone',
        ], ['availableendyear', 'availableendmonth', 'availableendday']);
        if ($availableDate !== null) {
            $availableDate = self::dateWithEra($availableDate, $fields, ['availabledateera', 'available-date-era']);
            $item['available-date'] = $availableDate;
        }

        $acceptedDate = self::dateFromFields($fields, ['accepteddate', 'accepted-date', 'dateaccepted', 'date-accepted'], ['acceptedyear', 'acceptedmonth', 'acceptedday'], [
            'hour' => 'acceptedhour',
            'minute' => 'acceptedminute',
            'second' => 'acceptedsecond',
            'timezone' => 'acceptedtimezone',
            'endhour' => 'acceptedendhour',
            'endminute' => 'acceptedendminute',
            'endsecond' => 'acceptedendsecond',
            'endtimezone' => 'acceptedendtimezone',
        ], ['acceptedendyear', 'acceptedendmonth', 'acceptedendday']);
        if ($acceptedDate !== null) {
            $acceptedDate = self::dateWithEra($acceptedDate, $fields, ['accepteddateera', 'accepted-date-era', 'dateacceptedera', 'date-accepted-era']);
            $item['accepted-date'] = $acceptedDate;
        }

        $revisedDate = self::dateFromFields($fields, ['reviseddate', 'revised-date', 'revisiondate', 'revision-date', 'daterevised', 'date-revised', 'revdate'], ['revisedyear', 'revisedmonth', 'revisedday'], [
            'hour' => 'revisedhour',
            'minute' => 'revisedminute',
            'second' => 'revisedsecond',
            'timezone' => 'revisedtimezone',
            'endhour' => 'revisedendhour',
            'endminute' => 'revisedendminute',
            'endsecond' => 'revisedendsecond',
            'endtimezone' => 'revisedendtimezone',
        ], ['revisedendyear', 'revisedendmonth', 'revisedendday']);
        if ($revisedDate !== null) {
            $revisedDate = self::dateWithEra($revisedDate, $fields, ['reviseddateera', 'revised-date-era', 'revisiondateera', 'revision-date-era', 'daterevisedera', 'date-revised-era']);
            $item['revised-date'] = $revisedDate;
        }

        $submittedDate = self::dateFromFields($fields, ['submitteddate', 'submitted-date', 'submitted'], ['submittedyear', 'submittedmonth', 'submittedday'], [
            'hour' => 'submittedhour',
            'minute' => 'submittedminute',
            'second' => 'submittedsecond',
            'timezone' => 'submittedtimezone',
            'endhour' => 'submittedendhour',
            'endminute' => 'submittedendminute',
            'endsecond' => 'submittedendsecond',
            'endtimezone' => 'submittedendtimezone',
        ], ['submittedendyear', 'submittedendmonth', 'submittedendday']);
        if ($submittedDate !== null) {
            $submittedDate = self::dateWithEra($submittedDate, $fields, ['submitteddateera', 'submitted-date-era']);
            $item['submitted'] = $submittedDate;
        }

        $labelDate = self::dateFromFields($fields, ['labeldate', 'label-date'], ['labelyear', 'labelmonth', 'labelday'], [], ['labelendyear', 'labelendmonth', 'labelendday']);
        if ($labelDate !== null) {
            $labelDate = self::dateWithEra($labelDate, $fields, ['labeldateera', 'label-date-era']);
            $item['label-date'] = $labelDate;
        }

        $accessed = self::dateFromFields($fields, ['urldate', 'accessed', 'accessdate', 'lastchecked', 'lastaccessed', 'visited'], ['urlyear', 'urlmonth', 'urlday'], [
            'hour' => 'urlhour',
            'minute' => 'urlminute',
            'second' => 'urlsecond',
            'timezone' => 'urltimezone',
            'endhour' => 'urlendhour',
            'endminute' => 'urlendminute',
            'endsecond' => 'urlendsecond',
            'endtimezone' => 'urlendtimezone',
        ], ['urlendyear', 'urlendmonth', 'urlendday']);
        if ($accessed !== null) {
            $accessed = self::dateWithEra($accessed, $fields, ['urldateera', 'url-date-era', 'accesseddateera', 'accessed-date-era']);
            $item['accessed'] = $accessed;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $fields
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @return array<string, mixed>
     */
    private static function withBiblatexRelationMetadata(array $item, array $fields, array $entriesByKey): array
    {
        $xdata = self::biblatexKeyList($fields['xdata'] ?? '');
        if ($xdata !== []) {
            $xdataItems = self::referencedXdataEntrySummaries($xdata, $entriesByKey);
            $missing = self::missingXdataReferenceKeys($xdata, $entriesByKey);
            $item['xdataKeys'] = $xdata;
            $item['xdataItems'] = $xdataItems;
            $summary = self::summarizedReferenceValues($xdataItems, $missing, $xdata);
            if ($summary !== '') {
                $item['xdataSummary'] = $summary;
            }
            if ($missing !== []) {
                $item['missingXdataKeys'] = $missing;
            }
        }

        $entrySet = self::biblatexKeyList($fields['entryset'] ?? '');
        if ($entrySet !== []) {
            $entrySetItems = self::referencedEntrySummaries($entrySet, $entriesByKey);
            $missing = self::missingReferenceKeys($entrySet, $entriesByKey);
            $item['entrySet'] = $entrySet;
            $item['entrySetItems'] = $entrySetItems;
            $summary = self::summarizedReferenceValues($entrySetItems, $missing, $entrySet);
            if ($summary !== '') {
                $item['entrySetSummary'] = $summary;
            }
            if ($missing !== []) {
                $item['missingEntrySetKeys'] = $missing;
            }
        }

        $related = self::biblatexKeyList(self::firstRawField($fields, ['related', 'related-keys', 'relatedkeys']));
        if ($related !== []) {
            $relatedItems = self::referencedEntrySummaries($related, $entriesByKey);
            $missing = self::missingReferenceKeys($related, $entriesByKey);
            $item['relatedKeys'] = $related;
            $item['relatedItems'] = $relatedItems;
            if ($missing !== []) {
                $item['missingRelatedKeys'] = $missing;
            }

            $relatedType = self::firstField($fields, ['relatedtype', 'related-type']);
            if ($relatedType !== '') {
                $item['relatedType'] = $relatedType;
            }

            $relatedString = self::firstField($fields, ['relatedstring', 'related-string']);
            if ($relatedString !== '') {
                $item['relatedString'] = $relatedString;
            }

            $relatedOptions = self::biblatexOptionList(self::firstRawField($fields, ['relatedoptions', 'related-options']));
            if ($relatedOptions !== []) {
                $item['related-options'] = $relatedOptions;
                $item['relatedOptions'] = $relatedOptions;
            }

            $summary = self::biblatexRelatedSummary(
                $relatedItems,
                $missing,
                (string) ($item['relatedType'] ?? ''),
                (string) ($item['relatedString'] ?? '')
            );
            if ($summary !== '') {
                $item['relatedSummary'] = $summary;
                $item['related-summary'] = $summary;
            }
        }

        $crossref = self::biblatexKeyList($fields['crossref'] ?? '');
        if ($crossref !== []) {
            $crossrefItems = self::referencedEntrySummaries($crossref, $entriesByKey);
            $missing = self::missingReferenceKeys($crossref, $entriesByKey);
            $item['crossrefKeys'] = $crossref;
            $item['crossrefItems'] = $crossrefItems;
            $summary = self::summarizedReferenceValues($crossrefItems, $missing, $crossref);
            if ($summary !== '') {
                $item['crossrefSummary'] = $summary;
            }
            if ($missing !== []) {
                $item['missingCrossrefKeys'] = $missing;
            }
        }

        $xref = self::biblatexKeyList($fields['xref'] ?? '');
        if ($xref !== []) {
            $xrefItems = self::referencedEntrySummaries($xref, $entriesByKey);
            $missing = self::missingReferenceKeys($xref, $entriesByKey);
            $item['xrefKeys'] = $xref;
            $item['xrefItems'] = $xrefItems;
            $summary = self::summarizedReferenceValues($xrefItems, $missing, $xref);
            if ($summary !== '') {
                $item['xrefSummary'] = 'Xref: ' . $summary;
                $item['xref-summary'] = 'Xref: ' . $summary;
            }
            if ($missing !== []) {
                $item['missingXrefKeys'] = $missing;
            }
        }

        return $item;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @return list<array<string, mixed>>
     */
    private static function referencedEntrySummaries(array $keys, array $entriesByKey): array
    {
        $summaries = [];
        foreach ($keys as $key) {
            $entry = $entriesByKey[$key] ?? null;
            if ($entry === null) {
                continue;
            }

            $fields = self::resolveInheritedFields($entry, $entriesByKey);
            $summary = self::entryToCslItem($entry['type'], $entry['key'], $fields);
            unset($summary['rawBibtex']);
            if (self::isDataEntryType($entry['type']) || self::isDataOnlyEntry($entry)) {
                $summary['dataOnly'] = true;
            }

            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @return list<array<string, mixed>>
     */
    private static function referencedXdataEntrySummaries(array $keys, array $entriesByKey): array
    {
        $summaries = [];
        foreach ($keys as $key) {
            $entry = $entriesByKey[$key] ?? null;
            if ($entry === null || strtolower($entry['type']) !== 'xdata') {
                continue;
            }

            $fields = self::resolveInheritedFields($entry, $entriesByKey);
            $summary = self::entryToCslItem($entry['type'], $entry['key'], $fields);
            unset($summary['rawBibtex']);
            $summary['dataOnly'] = true;
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @return list<string>
     */
    private static function missingReferenceKeys(array $keys, array $entriesByKey): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => !isset($entriesByKey[$key])
        ));
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @return list<string>
     */
    private static function missingXdataReferenceKeys(array $keys, array $entriesByKey): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => !isset($entriesByKey[$key]) || strtolower($entriesByKey[$key]['type']) !== 'xdata'
        ));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     * @param list<string> $fallbackKeys
     */
    private static function summarizedReferenceValues(array $items, array $missing, array $fallbackKeys = []): string
    {
        $values = [];
        foreach ($items as $item) {
            $display = self::referenceItemDisplay($item);
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
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     */
    private static function biblatexRelatedSummary(array $items, array $missing, string $type, string $label): string
    {
        $values = self::summarizedReferenceValues($items, $missing);
        if ($values === '') {
            return '';
        }

        $type = trim($type);
        $label = trim($label);
        $hasExplicitLabel = $label !== '';
        if (!$hasExplicitLabel) {
            $label = self::defaultBiblatexRelatedTypeLabel($type);
        }
        if ($type !== '' && ($hasExplicitLabel || $label === 'Related source')) {
            $label .= ' (' . $type . ')';
        }

        return $label . ': ' . $values;
    }

    private static function defaultBiblatexRelatedTypeLabel(string $type): string
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
    private static function referenceItemDisplay(array $item): string
    {
        $label = trim((string) ($item['title'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($item['id'] ?? ''));
        }
        if ($label === '') {
            return '';
        }

        $date = self::referenceDateLabel($item['issued'] ?? null);

        return $date === '' ? $label : $label . ' (' . $date . ')';
    }

    private static function referenceDateLabel(mixed $issued): string
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

    private static function cslType(string $type): string
    {
        return match (strtolower($type)) {
            'article', 'periodical', 'suppperiodical' => 'article-journal',
            'review' => 'review',
            'inproceedings', 'conference' => 'paper-conference',
            'talk', 'lecture', 'presentation' => 'speech',
            'bookinbook', 'inbook', 'incollection', 'suppbook', 'suppcollection' => 'chapter',
            'inreference' => 'entry-encyclopedia',
            'set' => 'entry',
            'booklet' => 'pamphlet',
            'letter' => 'personal_communication',
            'misc' => 'document',
            'collection', 'manual', 'mvcollection', 'mvbook', 'mvproceedings', 'mvreference', 'proceedings', 'reference' => 'book',
            'phdthesis', 'mastersthesis', 'mathesis' => 'thesis',
            'report', 'techreport' => 'report',
            'patent' => 'patent',
            'legislation', 'legal' => 'legislation',
            'jurisdiction' => 'legal_case',
            'standard' => 'standard',
            'movie', 'video' => 'motion_picture',
            'audio', 'music' => 'song',
            'artwork', 'image' => 'graphic',
            'software' => 'software',
            'dataset' => 'dataset',
            'online', 'www', 'electronic' => 'webpage',
            'unpublished' => 'manuscript',
            default => strtolower($type),
        };
    }

    /**
     * @param array<string, string> $fields
     */
    private static function cslTypeForEntry(string $type, array $fields): string
    {
        $entryType = strtolower($type);
        if ($entryType === 'unpublished' && self::firstField($fields, ['eventtitle']) !== '') {
            return 'speech';
        }

        return self::cslType($type);
    }

    /**
     * @param array<string, string> $fields
     * @return list<string>
     */
    private static function publisherPlaceFieldNames(string $type, array $fields): array
    {
        if (self::entryUsesVenueAsEventPlace($type, $fields)) {
            return ['location', 'address'];
        }

        return ['location', 'address', 'venue'];
    }

    /**
     * @param array<string, string> $fields
     */
    private static function entryUsesVenueAsEventPlace(string $type, array $fields): bool
    {
        $entryType = strtolower($type);
        if (in_array($entryType, ['talk', 'lecture', 'presentation'], true)) {
            return true;
        }

        return $entryType === 'unpublished' && self::firstField($fields, ['eventtitle']) !== '';
    }

    /**
     * @param array<string, string> $fields
     */
    private static function thesisType(string $entryType, array $fields): string
    {
        $explicit = self::firstField($fields, ['thesistype', 'thesis-type']);
        if ($explicit !== '') {
            return $explicit;
        }

        $entryType = strtolower($entryType);
        if (in_array($entryType, ['thesis', 'phdthesis', 'mastersthesis', 'mathesis'], true)) {
            $type = self::firstField($fields, ['type']);
            if ($type !== '') {
                return $type;
            }
        }

        return match ($entryType) {
            'phdthesis' => 'phdthesis',
            'mastersthesis', 'mathesis' => 'mathesis',
            default => '',
        };
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private static function firstField(array $fields, array $names): string
    {
        foreach ($names as $name) {
            if (isset($fields[$name])) {
                $value = self::cleanBibtexText($fields[$name]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $fields
     */
    private static function noteField(array $fields): string
    {
        $note = self::firstField($fields, ['note']);
        if ($note !== '') {
            return $note;
        }

        return self::firstField($fields, ['addendum']);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function patentType(string $entryType, array $fields): string
    {
        if (strtolower($entryType) !== 'patent') {
            return '';
        }

        return self::firstField($fields, ['type']);
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
     * @param array<string, string> $fields
     */
    private static function issueField(string $type, array $fields): string
    {
        $issue = self::firstField($fields, ['issue']);
        if ($issue !== '') {
            return $issue;
        }

        if (in_array(strtolower($type), ['article', 'periodical', 'review', 'suppperiodical'], true)) {
            return self::firstField($fields, ['number']);
        }

        return '';
    }

    /**
     * @param array<string, string> $fields
     */
    private static function indexSortTitle(array $fields): string
    {
        $indexSortTitle = self::firstField($fields, ['indexsorttitle', 'index-sort-title']);
        if ($indexSortTitle !== '') {
            return $indexSortTitle;
        }

        return self::firstField($fields, ['indextitle', 'index-title']);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function shorthandListSortKey(array $fields): string
    {
        $sortShorthand = self::firstField($fields, ['sortshorthand', 'sort-shorthand']);
        if ($sortShorthand !== '') {
            return $sortShorthand;
        }

        return self::firstField($fields, ['shorthand']);
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $titleFields
     * @param list<string> $subtitleFields
     */
    private static function composedTitle(array $fields, array $titleFields, array $subtitleFields): string
    {
        $title = self::firstField($fields, $titleFields);
        $subtitle = self::firstField($fields, $subtitleFields);
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
     * @return list<string>
     */
    private static function biblatexKeyList(string $value): array
    {
        $value = self::cleanBibtexText($value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (string $key): string => trim($key),
                self::splitTopLevel($value, ',')
            ),
            static fn (string $key): bool => $key !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private static function keywordList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $keywords = preg_split('/\s*[,;]\s*/', $value) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $keyword): string => trim($keyword), $keywords),
            static fn (string $keyword): bool => $keyword !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private static function biblatexOptionList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $parts = [];
        foreach (self::splitTopLevel($value, ',') as $part) {
            array_push($parts, ...self::splitTopLevel($part, ';'));
        }

        return array_values(array_filter(
            array_map(
                static fn (string $option): string => self::cleanBibtexText($option),
                $parts
            ),
            static fn (string $option): bool => $option !== ''
        ));
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private static function biblatexCustomFieldsFromFields(array $fields): array
    {
        $customFields = [];
        foreach (self::BIBLATEX_CUSTOM_FIELDS as $field) {
            if (!isset($fields[$field])) {
                continue;
            }

            $value = self::cleanBibtexText($fields[$field]);
            if ($value !== '') {
                $customFields[$field] = $value;
            }
        }

        return $customFields;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<string>>
     */
    private static function biblatexCustomListsFromFields(array $fields): array
    {
        $customLists = [];
        foreach (self::BIBLATEX_CUSTOM_LIST_FIELDS as $field) {
            if (!isset($fields[$field]) || trim($fields[$field]) === '') {
                continue;
            }

            $values = self::literalListFromText($fields[$field]);
            if ($values !== []) {
                $customLists[$field] = $values;
            }
        }

        return $customLists;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<array<string, mixed>>>
     */
    private static function biblatexCustomNamesFromFields(array $fields): array
    {
        $customNames = [];
        foreach (self::BIBLATEX_CUSTOM_NAME_FIELDS as $field) {
            if (!isset($fields[$field]) || trim($fields[$field]) === '') {
                continue;
            }

            $names = self::namesFromBibtexField($fields, $field);
            if ($names !== []) {
                $customNames[$field] = $names;
            }
        }

        return $customNames;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<array{name:string, value:string}>>
     */
    private static function biblatexFieldAnnotationsFromFields(array $fields): array
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

            $defaultName = self::normalizedBiblatexFieldAnnotationName((string) ($matches[2] ?? ''));
            foreach (self::biblatexFieldAnnotationEntries($value, $defaultName) as $annotation) {
                $annotations[$baseField][] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * @return list<array{name:string, value:string}>
     */
    private static function biblatexFieldAnnotationEntries(string $value, string $defaultName): array
    {
        if (trim($value) === '') {
            return [];
        }

        $separator = str_contains($value, ';') ? ';' : ',';
        $entries = [];
        foreach (self::splitTopLevel($value, $separator) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $name = $defaultName;
            $text = $entry;
            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)?\s*=\s*(.+)$/u', $entry, $matches) === 1) {
                if (($matches[1] ?? '') !== '') {
                    $name = self::normalizedBiblatexFieldAnnotationName($matches[1]);
                }
                $text = $matches[2];
            }

            $text = self::cleanBibtexText($text);
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

    private static function normalizedBiblatexFieldAnnotationName(string $name): string
    {
        return strtolower(str_replace('_', '-', trim($name)));
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     * @return list<string>
     */
    private static function literalListFromFirstField(array $fields, array $names): array
    {
        foreach ($names as $name) {
            if (!isset($fields[$name]) || trim($fields[$name]) === '') {
                continue;
            }

            $values = self::literalListFromText($fields[$name]);
            if ($values !== []) {
                return $values;
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function literalListFromText(string $value): array
    {
        $values = [];
        foreach (self::splitBiblatexLiteralList($value) as $part) {
            $part = self::cleanBibtexText($part);
            if ($part !== '') {
                $values[] = $part;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function splitBiblatexLiteralList(string $value): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '{') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($depth === 0 && preg_match('/\G\s+and\s+/i', $value, $match, 0, $i) === 1) {
                $parts[] = $buffer;
                $buffer = '';
                $i += strlen($match[0]) - 1;
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    /**
     * @param list<string> $values
     */
    private static function literalListDisplay(array $values): string
    {
        return implode('; ', $values);
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
     * @param array<string, string> $fields
     * @return list<array{field:string, type:string, label:string, names:list<array<string, mixed>>}>
     */
    private static function editorialRolesFromFields(array $fields): array
    {
        $roles = [];
        $primaryEditorType = self::normalizedEditorialRoleType(self::cleanBibtexText($fields['editortype'] ?? ''));
        if ($primaryEditorType !== '' && $primaryEditorType !== 'editor') {
            $editorNames = self::namesFromBibtexField($fields, 'editor');
            if ($editorNames !== []) {
                $roles[] = [
                    'field' => 'editor',
                    'type' => $primaryEditorType,
                    'label' => self::editorialRoleLabel($primaryEditorType),
                    'names' => $editorNames,
                ];
            }
        }

        foreach ([
            ['editora', 'editoratype'],
            ['editorb', 'editorbtype'],
            ['editorc', 'editorctype'],
        ] as [$nameField, $typeField]) {
            $names = self::namesFromBibtexField($fields, $nameField);
            if ($names === []) {
                continue;
            }

            $type = self::normalizedEditorialRoleType(self::cleanBibtexText($fields[$typeField] ?? 'editor'));
            $roles[] = [
                'field' => $nameField,
                'type' => $type,
                'label' => self::editorialRoleLabel($type),
                'names' => $names,
            ];
        }

        return $roles;
    }

    /**
     * @param array<string, string> $fields
     * @return list<array<string, mixed>>
     */
    private static function eventOrganizerNames(string $type, array $fields): array
    {
        $organizer = self::namesFromFirstBibtexField($fields, ['eventorganizer', 'organizer']);
        if ($organizer !== []) {
            return $organizer;
        }

        if (!in_array(strtolower($type), ['conference', 'inproceedings', 'proceedings'], true)) {
            return [];
        }

        return self::namesFromBibtexField($fields, 'organization');
    }

    private static function normalizedEditorialRoleType(string $type): string
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

    private static function editorialRoleCslNameVariable(string $type): ?string
    {
        return match (self::normalizedEditorialRoleType($type)) {
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
            'afterword' => self::normalizedEditorialRoleType($type),
            default => null,
        };
    }

    private static function editorialRoleLabel(string $type): string
    {
        $type = self::normalizedEditorialRoleType($type);

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
     * @return list<array{label:string, path:string, mediaType:string}>
     */
    private static function sourceFilesFromField(string $value): array
    {
        return array_map(
            static fn (array $entry): array => [
                'label' => $entry['label'],
                'path' => $entry['normalizedPath'],
                'mediaType' => $entry['mediaType'],
            ],
            array_values(array_filter(
                self::sourceFileEntriesFromField($value),
                static fn (array $entry): bool => $entry['reason'] === ''
            ))
        );
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>
     */
    private static function sourceFileDiagnosticsFromField(string $value): array
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
                self::sourceFileEntriesFromField($value),
                static fn (array $entry): bool => $entry['reason'] !== ''
            ))
        );
    }

    /**
     * @return list<array{label:string, path:string, normalizedPath:string, mediaType:string, reason:string}>
     */
    private static function sourceFileEntriesFromField(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $files = [];
        foreach (self::splitTopLevel($value, ';') as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $parsed = self::parseSourceFileEntry($entry);
            $policy = self::sourceFilePathPolicy($parsed['path']);
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
    private static function parseSourceFileEntry(string $entry): array
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
     * @return list<array<string, mixed>>
     */
    private static function namesFromBibtexField(array $fields, string $field): array
    {
        $names = self::namesFromBibtex($fields[$field] ?? '');
        if ($names === []) {
            return [];
        }

        return self::withBiblatexNameAnnotations($names, self::biblatexNameAnnotationsForField($fields, $field));
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $fieldNames
     * @return list<array<string, mixed>>
     */
    private static function namesFromFirstBibtexField(array $fields, array $fieldNames): array
    {
        foreach ($fieldNames as $field) {
            if (!isset($fields[$field]) || trim($fields[$field]) === '') {
                continue;
            }

            return self::namesFromBibtexField($fields, $field);
        }

        return [];
    }

    /**
     * @param array<string, string> $fields
     * @return list<array{index:int, part:string, value:string}>
     */
    private static function biblatexNameAnnotationsForField(array $fields, string $field): array
    {
        $annotations = [];
        $pattern = '/^' . preg_quote($field, '/') . '\\+an(?::([A-Za-z][A-Za-z0-9_-]*))?$/u';
        foreach ($fields as $name => $value) {
            if (preg_match($pattern, $name, $matches) !== 1) {
                continue;
            }

            $defaultPart = strtolower(str_replace('_', '-', trim((string) ($matches[1] ?? ''))));
            foreach (self::biblatexNameAnnotations($value, $defaultPart) as $annotation) {
                $annotations[] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * @param list<array<string, mixed>> $names
     * @param list<array{index:int, part:string, value:string}> $annotations
     * @return list<array<string, mixed>>
     */
    private static function withBiblatexNameAnnotations(array $names, array $annotations): array
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
     * @return list<array{index:int, part:string, value:string}>
     */
    private static function biblatexNameAnnotations(string $value, string $defaultPart = ''): array
    {
        if (trim($value) === '') {
            return [];
        }

        $defaultPart = strtolower(str_replace('_', '-', trim($defaultPart)));
        $separator = str_contains($value, ';') ? ';' : ',';
        $annotations = [];
        foreach (self::splitTopLevel($value, $separator) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*(?::\s*([A-Za-z][A-Za-z0-9_-]*))?\s*=\s*(.+)$/u', $entry, $matches) !== 1) {
                throw new \InvalidArgumentException('BibLaTeX name annotation is malformed: ' . self::cleanBibtexText($entry));
            }

            $index = (int) $matches[1];
            if ($index < 1) {
                throw new \InvalidArgumentException('BibLaTeX name annotation index must be one-based');
            }

            $value = self::cleanBibtexText($matches[3]);
            if ($value === '') {
                continue;
            }

            $part = strtolower(str_replace('_', '-', trim((string) ($matches[2] ?? ''))));
            $annotations[] = [
                'index' => $index,
                'part' => $part === '' ? ($defaultPart === '' ? 'name' : $defaultPart) : $part,
                'value' => $value,
            ];
        }

        return $annotations;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function namesFromBibtex(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $names = [];
        foreach (self::splitBibtexNames($value) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $names[] = self::nameToCsl($name);
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function splitBibtexNames(string $value): array
    {
        $names = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '{') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($depth === 0 && preg_match('/\G\s+and\s+/i', $value, $match, 0, $i) === 1) {
                $names[] = $buffer;
                $buffer = '';
                $i += strlen($match[0]) - 1;
                continue;
            }

            $buffer .= $char;
        }

        $names[] = $buffer;

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private static function nameToCsl(string $name): array
    {
        $literal = self::outerBraced($name);
        if ($literal !== null) {
            return [
                'literal' => self::cleanBibtexText($literal),
            ];
        }

        if (self::isEtAlNameSentinel($name)) {
            return [
                'literal' => 'others',
                'csl-et-al' => true,
            ];
        }

        $parts = self::splitTopLevel($name, ',');
        if (count($parts) >= 2) {
            [$particle, $family] = self::splitLeadingParticle(self::cleanBibtexText($parts[0]));
            $given = self::cleanBibtexText($parts[1]);
            $suffix = isset($parts[2]) ? self::cleanBibtexText($parts[2]) : '';
            if ($suffix !== '' && self::isBibtexNameSuffix($given)) {
                [$given, $suffix] = [$suffix, $given];
            }
            [$given, $droppingParticle] = self::splitTrailingParticle($given);

            $name = [
                'family' => $family,
                'given' => $given,
            ];

            if ($particle !== '') {
                $name['non-dropping-particle'] = $particle;
            }

            if ($droppingParticle !== '') {
                $name['dropping-particle'] = $droppingParticle;
            }

            if ($suffix !== '') {
                $name['suffix'] = $suffix;
                $name['comma-suffix'] = true;
            }

            return $name;
        }

        $tokens = preg_split('/\s+/', self::cleanBibtexText($name)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return ['literal' => ''];
        }

        if (count($tokens) === 1) {
            return ['family' => $tokens[0]];
        }

        $family = array_pop($tokens);
        $particle = [];
        while ($tokens !== [] && self::isParticle($tokens[count($tokens) - 1])) {
            array_unshift($particle, array_pop($tokens));
        }

        $name = [
            'family' => $family,
            'given' => implode(' ', $tokens),
        ];
        if ($particle !== []) {
            $name['non-dropping-particle'] = implode(' ', $particle);
        }

        return $name;
    }

    private static function isEtAlNameSentinel(string $name): bool
    {
        return strtolower(self::cleanBibtexText($name)) === 'others';
    }

    private static function isBibtexNameSuffix(string $value): bool
    {
        $normalized = strtolower(trim(self::cleanBibtexText($value), ". \t\n\r\0\x0B"));
        if (in_array($normalized, ['jr', 'junior', 'sr', 'senior'], true)) {
            return true;
        }

        return preg_match('/^(?:[ivxlcdm]+|\d+(?:st|nd|rd|th))$/i', $normalized) === 1;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '{') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === $separator && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return array_map('trim', $parts);
    }

    private static function outerBraced(string $value): ?string
    {
        $value = trim($value);
        if (!str_starts_with($value, '{') || !str_ends_with($value, '}')) {
            return null;
        }

        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            if ($value[$i] === '{') {
                $depth++;
            } elseif ($value[$i] === '}') {
                $depth--;
                if ($depth === 0 && $i < $length - 1) {
                    return null;
                }
            }
        }

        return $depth === 0 ? substr($value, 1, -1) : null;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function splitLeadingParticle(string $family): array
    {
        $tokens = preg_split('/\s+/', trim($family)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if (count($tokens) < 2) {
            return ['', $family];
        }

        $particle = [];
        while (count($tokens) > 1 && self::isParticle($tokens[0])) {
            $particle[] = array_shift($tokens);
        }

        return [implode(' ', $particle), implode(' ', $tokens)];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function splitTrailingParticle(string $given): array
    {
        $tokens = preg_split('/\s+/', trim($given)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if (count($tokens) < 2) {
            return [$given, ''];
        }

        $particle = [];
        while (count($tokens) > 1 && self::isParticle($tokens[count($tokens) - 1])) {
            array_unshift($particle, array_pop($tokens));
        }

        if ($particle === []) {
            return [$given, ''];
        }

        return [implode(' ', $tokens), implode(' ', $particle)];
    }

    private static function isParticle(string $token): bool
    {
        $token = trim($token, "{}~");
        $lower = strtolower($token);
        if (in_array($lower, ['da', 'de', 'del', 'della', 'der', 'di', 'dos', 'du', 'la', 'le', 'van', 'von', 'ten', 'ter'], true)) {
            return true;
        }

        return preg_match('/^[a-z][a-z\'.-]*$/', $token) === 1;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $dateFields
     * @param list<string> $partFields
     * @param array<string, string> $timeFields
     * @param list<string> $endPartFields
     * @return array<string, mixed>|null
     */
    private static function dateFromFields(array $fields, array $dateFields, array $partFields, array $timeFields = [], array $endPartFields = []): ?array
    {
        foreach ($dateFields as $field) {
            if (isset($fields[$field]) && trim($fields[$field]) !== '') {
                return self::dateWithTimeParts(
                    self::dateFromText(self::cleanBibtexDateText($fields[$field]), $field),
                    $fields,
                    $timeFields,
                    $field
                );
            }
        }

        $hasEndPartField = $endPartFields !== [] && self::hasAnyField($fields, $endPartFields);
        if ($partFields === [] || !isset($fields[$partFields[0]]) || trim($fields[$partFields[0]]) === '') {
            if ($hasEndPartField) {
                throw new \InvalidArgumentException('BibTeX end date fields require ' . ($partFields[0] ?? 'year') . ' to be present');
            }

            return null;
        }

        $year = self::cleanBibtexText($fields[$partFields[0]]);
        if (!preg_match('/^-?\d+$/', $year)) {
            if ($hasEndPartField) {
                throw new \InvalidArgumentException('BibTeX split date range fields require a numeric ' . $partFields[0] . ' field');
            }

            return self::dateWithTimeParts(['literal' => $year], $fields, $timeFields, $partFields[0]);
        }

        $startDatePart = self::datePartInfoFromSplitFields($fields, $partFields, true);
        if ($startDatePart === null) {
            throw new \InvalidArgumentException('BibTeX split date fields require ' . ($partFields[0] ?? 'year') . ' to be present');
        }

        $dateParts = [$startDatePart['parts']];
        $season = $startDatePart['season'];
        if ($hasEndPartField) {
            $endDatePart = self::datePartInfoFromSplitFields($fields, $endPartFields, false);
            if ($endDatePart === null) {
                throw new \InvalidArgumentException('BibTeX split date range fields require ' . ($endPartFields[0] ?? 'endyear') . ' to be present');
            }
            if ($season !== null || $endDatePart['season'] !== null) {
                throw new \InvalidArgumentException('BibTeX split date range fields do not support season month codes');
            }

            $dateParts[] = $endDatePart['parts'];
        }

        $date = ['date-parts' => $dateParts];
        if ($season !== null) {
            $date['season'] = $season;
        }

        return self::dateWithTimeParts($date, $fields, $timeFields, $partFields[0]);
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $partFields
     * @return array{parts:list<int>, season:int|null}|null
     */
    private static function datePartInfoFromSplitFields(array $fields, array $partFields, bool $required): ?array
    {
        $yearField = $partFields[0] ?? null;
        if ($yearField === null || !isset($fields[$yearField]) || trim($fields[$yearField]) === '') {
            return $required ? ['parts' => [], 'season' => null] : null;
        }

        $year = self::cleanBibtexText($fields[$yearField]);
        if (!preg_match('/^-?\d+$/', $year)) {
            throw new \InvalidArgumentException('BibTeX ' . $yearField . ' field must be numeric');
        }

        $parts = [(int) $year];
        $season = null;
        if (isset($partFields[1], $fields[$partFields[1]]) && trim($fields[$partFields[1]]) !== '') {
            $month = self::monthNumber(self::cleanBibtexText($fields[$partFields[1]]), $partFields[1], true);
            $season = self::seasonFromBiblatexDateMonthCode($month);
            if ($season !== null) {
                if (isset($partFields[2], $fields[$partFields[2]]) && trim($fields[$partFields[2]]) !== '') {
                    throw new \InvalidArgumentException('BibTeX ' . $partFields[1] . ' season date must not include a day');
                }

                return ['parts' => $parts, 'season' => $season];
            }

            $parts[] = $month;
        }

        if (isset($partFields[2], $fields[$partFields[2]]) && trim($fields[$partFields[2]]) !== '') {
            $day = self::cleanBibtexText($fields[$partFields[2]]);
            if (!preg_match('/^\d+$/', $day)) {
                throw new \InvalidArgumentException('BibTeX ' . $partFields[2] . ' field must be numeric');
            }

            $parts[] = (int) $day;
        }

        return ['parts' => $parts, 'season' => $season];
    }

    /**
     * @param array<string, mixed> $date
     * @param array<string, string> $fields
     * @param array<string, string> $timeFields
     * @return array<string, mixed>
     */
    private static function dateWithTimeParts(array $date, array $fields, array $timeFields, string $field): array
    {
        if ($timeFields === []) {
            return $date;
        }

        $time = self::timeFromDatePartFields($fields, $timeFields, '', $field);
        if ($time !== '') {
            $date['time'] = $time;
        }

        $endTime = self::timeFromDatePartFields($fields, $timeFields, 'end', $field);
        if ($endTime !== '') {
            $date['end-time'] = $endTime;
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $date
     * @param array<string, string> $fields
     * @param list<string> $eraFields
     * @return array<string, mixed>
     */
    private static function dateWithEra(array $date, array $fields, array $eraFields): array
    {
        foreach ($eraFields as $field) {
            $era = self::cleanBibtexText($fields[$field] ?? '');
            if ($era === '') {
                continue;
            }

            $date['era'] = strtolower(str_replace('_', '-', $era));

            return $date;
        }

        return $date;
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $timeFields
     */
    private static function timeFromDatePartFields(array $fields, array $timeFields, string $prefix, string $field): string
    {
        $hourKey = $timeFields[$prefix . 'hour'] ?? null;
        $minuteKey = $timeFields[$prefix . 'minute'] ?? null;
        $secondKey = $timeFields[$prefix . 'second'] ?? null;
        $timezoneKey = $timeFields[$prefix . 'timezone'] ?? null;

        $hour = $hourKey === null ? '' : self::cleanBibtexText($fields[$hourKey] ?? '');
        $minute = $minuteKey === null ? '' : self::cleanBibtexText($fields[$minuteKey] ?? '');
        $second = $secondKey === null ? '' : self::cleanBibtexText($fields[$secondKey] ?? '');
        $timezone = $timezoneKey === null ? '' : self::cleanBibtexText($fields[$timezoneKey] ?? '');

        if ($hour === '' && $minute === '' && $second === '' && $timezone === '') {
            return '';
        }

        if ($hour === '') {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' time requires an hour field');
        }

        $display = self::twoDigitDateTimePart($hour, $hourKey ?? 'hour', 0, 23);
        if ($minute !== '' || $second !== '') {
            $display .= ':' . self::twoDigitDateTimePart($minute === '' ? '0' : $minute, $minuteKey ?? 'minute', 0, 59);
        }

        if ($second !== '') {
            $display .= ':' . self::twoDigitDateTimePart($second, $secondKey ?? 'second', 0, 59);
        }

        if ($timezone !== '') {
            $display .= self::normalizedDateTimeZone($timezone, $timezoneKey ?? 'timezone');
        }

        return $display;
    }

    private static function twoDigitDateTimePart(string $value, string $field, int $min, int $max): string
    {
        if (!preg_match('/^\d{1,2}$/', $value)) {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' field must be numeric');
        }

        $number = (int) $value;
        if ($number < $min || $number > $max) {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' field must be between ' . $min . ' and ' . $max);
        }

        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }

    private static function normalizedDateTimeZone(string $value, string $field): string
    {
        $value = strtoupper(trim($value));
        if ($value === 'Z') {
            return 'Z';
        }

        if (preg_match('/^([+-])(\d{2})(?::?(\d{2}))?$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' field must be Z or a numeric timezone offset');
        }

        $hour = (int) $matches[2];
        $minute = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
        if ($hour > 23 || $minute > 59) {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' timezone offset is out of range');
        }

        return $matches[1] . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private static function dateFromText(string $date, string $field): array
    {
        $date = self::dateTextWithoutIsoTime(trim($date));
        $range = self::dateRangeFromText($date, $field);
        if ($range !== null) {
            return $range;
        }

        if (preg_match('/^(-?\d{1,6})(?:[-\/](\d{1,2})(?:[-\/](\d{1,2}))?)?([?~%])?$/', $date, $matches) !== 1) {
            return ['literal' => $date];
        }

        $parts = [(int) $matches[1]];
        if (isset($matches[2]) && $matches[2] !== '') {
            $month = (int) $matches[2];
            $season = self::seasonFromBiblatexDateMonthCode($month);
            if ($season !== null) {
                if (isset($matches[3]) && $matches[3] !== '') {
                    throw new \InvalidArgumentException('BibTeX ' . $field . ' season date must not include a day');
                }

                $dateObject = self::dateObjectWithMarkers([[(int) $matches[1]]], (string) ($matches[4] ?? ''), $date);
                $dateObject['season'] = $season;

                return $dateObject;
            }

            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' month must be between 1 and 12');
            }

            $parts[] = $month;
        }

        if (isset($matches[3]) && $matches[3] !== '') {
            $day = (int) $matches[3];
            if ($day < 1 || $day > 31) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' day must be between 1 and 31');
            }

            $parts[] = $day;
        }

        return self::dateObjectWithMarkers([$parts], (string) ($matches[4] ?? ''), $date);
    }

    private static function dateTextWithoutIsoTime(string $date): string
    {
        if (!str_contains($date, 'T') && !str_contains($date, 't')) {
            return $date;
        }

        $parts = explode('/', $date);
        if (count($parts) > 2) {
            return $date;
        }

        return implode('/', array_map(
            static fn (string $part): string => self::dateRangeSideWithoutIsoTime(trim($part)),
            $parts
        ));
    }

    private static function dateRangeSideWithoutIsoTime(string $side): string
    {
        if ($side === '') {
            return $side;
        }

        if (preg_match(
            '/^(-?\d{1,6}(?:-\d{1,2}(?:-\d{1,2})?)?)([?~%]?)[Tt]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?$/',
            $side,
            $matches
        ) !== 1) {
            return $side;
        }

        return $matches[1] . (string) ($matches[2] ?? '');
    }

    private static function seasonFromBiblatexDateMonthCode(int $month): ?int
    {
        return match ($month) {
            21 => 1,
            22 => 2,
            23 => 3,
            24 => 4,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function dateRangeFromText(string $date, string $field): ?array
    {
        $rangeParts = self::dateRangePartsFromText($date, $field);
        if ($rangeParts === null) {
            return self::openEndedDateRangeFromText($date, $field);
        }

        return self::dateObjectWithMarkers($rangeParts, self::dateRangeMarker($date), $date);
    }

    /**
     * @return list<list<int>>|null
     */
    private static function dateRangePartsFromText(string $date, string $field): ?array
    {
        if (substr_count($date, '/') !== 1) {
            return null;
        }

        [$start, $end] = array_map('trim', explode('/', $date, 2));
        if ($start === '' || $end === '') {
            return null;
        }

        if (!self::looksLikeDateRangeSide($start) || !self::looksLikeDateRangeSide($end)) {
            return null;
        }

        return [
            self::dateRangeSideParts($start, $field),
            self::dateRangeSideParts($end, $field),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function openEndedDateRangeFromText(string $date, string $field): ?array
    {
        if (substr_count($date, '/') !== 1) {
            return null;
        }

        [$start, $end] = array_map('trim', explode('/', $date, 2));
        if (($start === '' && $end === '') || ($start !== '' && $end !== '')) {
            return null;
        }

        $endpoint = $start === '' ? $end : $start;
        if (!self::looksLikeDateRangeSide($endpoint)) {
            return null;
        }

        $range = self::dateObjectWithMarkers([self::dateRangeSideParts($endpoint, $field)], self::dateRangeMarker($date), $date);
        $range['open-ended'] = $start === '' ? 'start' : 'end';
        $range['raw'] = $date;

        return $range;
    }

    private static function looksLikeDateRangeSide(string $value): bool
    {
        return preg_match('/^-?\d{3,6}(?:-\d{1,2}(?:-\d{1,2})?)?[?~%]?$/', $value) === 1;
    }

    /**
     * @return list<int>
     */
    private static function dateRangeSideParts(string $value, string $field): array
    {
        if (preg_match('/^(-?\d{1,6})(?:-(\d{1,2})(?:-(\d{1,2}))?)?([?~%])?$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' date range endpoint is malformed');
        }

        $parts = [(int) $matches[1]];
        if (isset($matches[2]) && $matches[2] !== '') {
            $month = (int) $matches[2];
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' month must be between 1 and 12');
            }

            $parts[] = $month;
        }

        if (isset($matches[3]) && $matches[3] !== '') {
            $day = (int) $matches[3];
            if ($day < 1 || $day > 31) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' day must be between 1 and 31');
            }

            $parts[] = $day;
        }

        return $parts;
    }

    private static function dateRangeMarker(string $date): string
    {
        $circa = false;
        $uncertain = false;
        foreach (array_map('trim', explode('/', $date, 2)) as $side) {
            if (preg_match('/([?~%])$/', $side, $matches) !== 1) {
                continue;
            }

            [$sideCirca, $sideUncertain] = self::dateMarkerFlags($matches[1]);
            $circa = $circa || $sideCirca;
            $uncertain = $uncertain || $sideUncertain;
        }

        if ($circa && $uncertain) {
            return '%';
        }

        return $circa ? '~' : ($uncertain ? '?' : '');
    }

    /**
     * @param list<list<int>> $dateParts
     * @return array<string, mixed>
     */
    private static function dateObjectWithMarkers(array $dateParts, string $marker, string $raw): array
    {
        $date = ['date-parts' => $dateParts];
        [$circa, $uncertain] = self::dateMarkerFlags($marker);
        if ($circa) {
            $date['circa'] = true;
        }

        if ($uncertain) {
            $date['uncertain'] = true;
        }

        if ($circa || $uncertain) {
            $date['raw'] = $raw;
        }

        return $date;
    }

    /**
     * @return array{0:bool, 1:bool}
     */
    private static function dateMarkerFlags(string $marker): array
    {
        return match ($marker) {
            '~' => [true, false],
            '?' => [false, true],
            '%' => [true, true],
            default => [false, false],
        };
    }

    private static function monthNumber(string $value, string $field, bool $allowSeasonCode = false): int
    {
        $lookup = strtolower(substr($value, 0, 3));
        $months = [
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'may' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'oct' => 10,
            'nov' => 11,
            'dec' => 12,
        ];

        if (preg_match('/^\d+$/', $value) === 1) {
            $month = (int) $value;
        } elseif (isset($months[$lookup])) {
            $month = $months[$lookup];
        } else {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' field must be a month name or number');
        }

        if ($month >= 1 && $month <= 12) {
            return $month;
        }
        if ($allowSeasonCode && self::seasonFromBiblatexDateMonthCode($month) !== null) {
            return $month;
        }

        $range = $allowSeasonCode ? 'between 1 and 12 or a BibLaTeX season code 21 through 24' : 'between 1 and 12';
        throw new \InvalidArgumentException('BibTeX ' . $field . ' month must be ' . $range);
    }

    private static function normalizePages(string $pages): string
    {
        return trim(preg_replace('/\s*--+\s*/', '-', $pages) ?? $pages);
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

    private static function cleanBibtexText(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = self::protectEscapedBibtexBraces($value);
        $value = self::decodeLatexText($value);
        $value = str_replace('~', ' ', $value);
        $value = self::restoreLatexLiteralTilde($value);
        $value = preg_replace('/\\\\([&%$#_])/', '$1', $value) ?? $value;
        $value = self::stripLatexTextWrappers($value);
        $value = preg_replace('/\\\\(?:textendash|textminus)\b/', '-', $value) ?? $value;
        $value = self::stripBibtexGroupingBraces($value);
        $value = self::restoreProtectedBibtexTokens($value);

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private static function cleanBibtexDateText(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = self::protectEscapedBibtexBraces($value);
        $value = self::decodeLatexText($value);
        $value = preg_replace('/\\\\([&%$#_])/', '$1', $value) ?? $value;
        $value = self::stripLatexTextWrappers($value);
        $value = preg_replace('/\\\\(?:textendash|textminus)\b/', '-', $value) ?? $value;
        $value = self::stripBibtexGroupingBraces($value);
        $value = self::restoreProtectedBibtexTokens($value);
        $value = preg_replace('/~(?!(?:\s*\/|\s*\z))/', ' ', $value) ?? $value;
        $value = self::restoreLatexLiteralTilde($value);

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * Mark literal source braces before LaTeX expansion. A macro such as
     * \textbackslash can itself produce a slash, which must never be treated
     * as a source escape for the grouping brace that follows it.
     */
    private static function protectEscapedBibtexBraces(string $value): string
    {
        $out = '';
        $pendingBackslashes = 0;
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                ++$pendingBackslashes;
                continue;
            }

            if ($char === '{' || $char === '}') {
                if (($pendingBackslashes % 2) === 1) {
                    if ($pendingBackslashes > 1) {
                        $out .= str_repeat('\\', $pendingBackslashes - 1);
                    }
                    $out .= $char === '{'
                        ? self::BIBTEX_PROTECTED_OPEN_BRACE_TOKEN
                        : self::BIBTEX_PROTECTED_CLOSE_BRACE_TOKEN;
                    $pendingBackslashes = 0;
                    continue;
                }
            }

            if ($pendingBackslashes > 0) {
                $out .= str_repeat('\\', $pendingBackslashes);
                $pendingBackslashes = 0;
            }

            $out .= $char === self::BIBTEX_PROTECTED_TOKEN_PREFIX
                ? self::BIBTEX_PROTECTED_NUL_TOKEN
                : $char;
        }

        if ($pendingBackslashes > 0) {
            $out .= str_repeat('\\', $pendingBackslashes);
        }

        return $out;
    }

    /**
     * Remove the remaining BibTeX grouping braces. Source-literal braces and
     * decoder-produced punctuation are carried as protected byte tokens so
     * this pass only reasons about source grouping syntax.
     */
    private static function stripBibtexGroupingBraces(string $value): string
    {
        $out = '';
        $pendingBackslashes = 0;
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                ++$pendingBackslashes;
                continue;
            }
            if ($char === self::BIBTEX_PROTECTED_TOKEN_PREFIX) {
                if ($pendingBackslashes > 0) {
                    $out .= str_repeat('\\', $pendingBackslashes);
                    $pendingBackslashes = 0;
                }
                $out .= $char;
                if ($index + 1 < $length) {
                    $out .= $value[++$index];
                }
                continue;
            }
            if ($char !== '{' && $char !== '}') {
                if ($pendingBackslashes > 0) {
                    $out .= str_repeat('\\', $pendingBackslashes);
                    $pendingBackslashes = 0;
                }
                $out .= $char;
                continue;
            }

            if ($pendingBackslashes === 0) {
                continue;
            }

            // The final slash is the brace escape candidate. For an odd run
            // the brace is literal; for an even run it remains a grouping
            // brace and is discarded after collapsing that candidate slash.
            // The remaining slashes precede this brace, not the next source
            // byte, so flush them now and reset the run before continuing.
            $escaped = ($pendingBackslashes % 2) === 1;
            --$pendingBackslashes;
            if ($pendingBackslashes > 0) {
                $out .= str_repeat('\\', $pendingBackslashes);
                $pendingBackslashes = 0;
            }
            if ($escaped) {
                $out .= $char;
            }
        }

        if ($pendingBackslashes > 0) {
            $out .= str_repeat('\\', $pendingBackslashes);
        }

        return $out;
    }

    private static function restoreProtectedBibtexTokens(string $value): string
    {
        $out = '';
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char !== self::BIBTEX_PROTECTED_TOKEN_PREFIX || $index + 1 >= $length) {
                $out .= $char;
                continue;
            }

            $token = $value[++$index];
            $out .= match ($token) {
                "\0" => "\0",
                "\x01" => '{',
                "\x02" => '}',
                "\x03" => '\\',
                default => self::BIBTEX_PROTECTED_TOKEN_PREFIX . $token,
            };
        }

        return $out;
    }

    private static function decodeLatexText(string $value): string
    {
        $value = self::decodeLatexAccentCommands($value);
        $value = self::decodeLatexPunctuationCommands($value);

        return self::decodeLatexSpecialLetters($value);
    }

    private static function decodeLatexPunctuationCommands(string $value): string
    {
        $map = [
            'BibLaTeX' => 'BibLaTeX',
            'BibTeX' => 'BibTeX',
            'LaTeX' => 'LaTeX',
            'TeX' => 'TeX',
            'dots' => "\u{2026}",
            'ldots' => "\u{2026}",
            'textasciicircum' => '^',
            'textasciitilde' => "\u{E000}",
            // This backslash was created by a macro rather than present in
            // source, so keep it opaque until grouping braces are removed.
            'textbackslash' => self::BIBTEX_GENERATED_BACKSLASH_TOKEN,
            'textbar' => '|',
            'textcopyright' => "\u{00A9}",
            'textdegree' => "\u{00B0}",
            'textellipsis' => "\u{2026}",
            'textemdash' => "\u{2014}",
            'textgreater' => '>',
            'textless' => '<',
            'textnumero' => "\u{2116}",
            'textquoteleft' => "\u{2018}",
            'textquoteright' => "\u{2019}",
            'textquotedblleft' => "\u{201C}",
            'textquotedblright' => "\u{201D}",
            'textquotesingle' => "'",
            'textquotedbl' => '"',
            'textregistered' => "\u{00AE}",
            'texttrademark' => "\u{2122}",
        ];
        $macros = array_keys($map);
        usort($macros, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $alternation = implode('|', array_map(static fn (string $macro): string => preg_quote($macro, '/'), $macros));

        $quoteMacros = [
            'textquoteleft' => true,
            'textquoteright' => true,
            'textquotedblleft' => true,
            'textquotedblright' => true,
        ];

        $value = preg_replace_callback(
            '/\\\\(' . $alternation . ')(?:(\s*\{\s*\})|(\s+)|(?![A-Za-z]))/u',
            static function (array $matches) use ($map, $quoteMacros): string {
                $macro = $matches[1];
                $suffix = (string) ($matches[3] ?? '');
                $replacement = $map[$macro] ?? $matches[0];
                if ($suffix !== '' && !isset($quoteMacros[$macro])) {
                    return $replacement . $suffix;
                }

                return $replacement;
            },
            $value
        ) ?? $value;

        $value = preg_replace('/\s+---\s+/u', ' — ', $value) ?? $value;

        return str_replace(['``', "''"], ["\u{201C}", "\u{201D}"], $value);
    }

    private static function restoreLatexLiteralTilde(string $value): string
    {
        return str_replace("\u{E000}", '~', $value);
    }

    private static function stripLatexTextWrappers(string $value): string
    {
        $commands = [
            'emph',
            'enquote',
            'mkbibbold',
            'mkbibbrackets',
            'mkbibemph',
            'mkbibitalic',
            'mkbibparens',
            'mkbibquote',
            'textbf',
            'textit',
            'textnormal',
            'textrm',
            'textsc',
            'textsf',
            'textsl',
            'textsubscript',
            'textsuperscript',
            'texttt',
        ];
        $alternation = implode('|', array_map(static fn (string $command): string => preg_quote($command, '/'), $commands));

        do {
            $previous = $value;
            $value = preg_replace('/\\\\(?:' . $alternation . ')\s*\{([^{}]*)\}/u', '$1', $value) ?? $value;
        } while ($value !== $previous);

        return $value;
    }

    private static function decodeLatexAccentCommands(string $value): string
    {
        $patterns = [
            '/\{\\\\([`\\\'"^~=\.uvHrcbk])\s*\{?\s*([A-Za-z])\s*\}?\}/u',
            '/\\\\([`\\\'"^~=\.uvHrcbk])\s*\{\s*([A-Za-z])\s*\}/u',
            '/\\\\([`\\\'"^~=\.])\s*([A-Za-z])/u',
            '/\\\\([uvHrcbk])\s+([A-Za-z])/u',
        ];

        foreach ($patterns as $pattern) {
            $value = preg_replace_callback(
                $pattern,
                static fn (array $matches): string => self::accentedLatinLetter($matches[1], $matches[2], $matches[0]),
                $value
            ) ?? $value;
        }

        return $value;
    }

    private static function accentedLatinLetter(string $accent, string $letter, string $original): string
    {
        $map = self::latexAccentMap();

        return $map[$accent][$letter] ?? $original;
    }

    private static function decodeLatexSpecialLetters(string $value): string
    {
        $map = self::latexSpecialLetterMap();
        $macros = array_keys($map);
        usort($macros, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $alternation = implode('|', array_map(static fn (string $macro): string => preg_quote($macro, '/'), $macros));
        $pattern = '/\{\\\\(' . $alternation . ')\}|\\\\(' . $alternation . ')(?![A-Za-z])/u';

        return preg_replace_callback(
            $pattern,
            static function (array $matches) use ($map): string {
                $macro = ($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? '');

                return $map[$macro] ?? $matches[0];
            },
            $value
        ) ?? $value;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function latexAccentMap(): array
    {
        return [
            '\'' => [
                'A' => "\u{00C1}", 'C' => "\u{0106}", 'E' => "\u{00C9}", 'I' => "\u{00CD}", 'L' => "\u{0139}", 'N' => "\u{0143}", 'O' => "\u{00D3}", 'R' => "\u{0154}", 'S' => "\u{015A}", 'U' => "\u{00DA}", 'Y' => "\u{00DD}", 'Z' => "\u{0179}",
                'a' => "\u{00E1}", 'c' => "\u{0107}", 'e' => "\u{00E9}", 'i' => "\u{00ED}", 'l' => "\u{013A}", 'n' => "\u{0144}", 'o' => "\u{00F3}", 'r' => "\u{0155}", 's' => "\u{015B}", 'u' => "\u{00FA}", 'y' => "\u{00FD}", 'z' => "\u{017A}",
            ],
            '`' => [
                'A' => "\u{00C0}", 'E' => "\u{00C8}", 'I' => "\u{00CC}", 'O' => "\u{00D2}", 'U' => "\u{00D9}",
                'a' => "\u{00E0}", 'e' => "\u{00E8}", 'i' => "\u{00EC}", 'o' => "\u{00F2}", 'u' => "\u{00F9}",
            ],
            '^' => [
                'A' => "\u{00C2}", 'C' => "\u{0108}", 'E' => "\u{00CA}", 'G' => "\u{011C}", 'H' => "\u{0124}", 'I' => "\u{00CE}", 'J' => "\u{0134}", 'O' => "\u{00D4}", 'S' => "\u{015C}", 'U' => "\u{00DB}", 'W' => "\u{0174}", 'Y' => "\u{0176}",
                'a' => "\u{00E2}", 'c' => "\u{0109}", 'e' => "\u{00EA}", 'g' => "\u{011D}", 'h' => "\u{0125}", 'i' => "\u{00EE}", 'j' => "\u{0135}", 'o' => "\u{00F4}", 's' => "\u{015D}", 'u' => "\u{00FB}", 'w' => "\u{0175}", 'y' => "\u{0177}",
            ],
            '"' => [
                'A' => "\u{00C4}", 'E' => "\u{00CB}", 'I' => "\u{00CF}", 'O' => "\u{00D6}", 'U' => "\u{00DC}", 'Y' => "\u{0178}",
                'a' => "\u{00E4}", 'e' => "\u{00EB}", 'i' => "\u{00EF}", 'o' => "\u{00F6}", 'u' => "\u{00FC}", 'y' => "\u{00FF}",
            ],
            '~' => [
                'A' => "\u{00C3}", 'N' => "\u{00D1}", 'O' => "\u{00D5}",
                'a' => "\u{00E3}", 'n' => "\u{00F1}", 'o' => "\u{00F5}",
            ],
            '=' => [
                'A' => "\u{0100}", 'E' => "\u{0112}", 'I' => "\u{012A}", 'O' => "\u{014C}", 'U' => "\u{016A}",
                'a' => "\u{0101}", 'e' => "\u{0113}", 'i' => "\u{012B}", 'o' => "\u{014D}", 'u' => "\u{016B}",
            ],
            '.' => [
                'C' => "\u{010A}", 'E' => "\u{0116}", 'G' => "\u{0120}", 'I' => "\u{0130}", 'Z' => "\u{017B}",
                'c' => "\u{010B}", 'e' => "\u{0117}", 'g' => "\u{0121}", 'z' => "\u{017C}",
            ],
            'u' => [
                'A' => "\u{0102}", 'E' => "\u{0114}", 'G' => "\u{011E}", 'I' => "\u{012C}", 'O' => "\u{014E}", 'U' => "\u{016C}",
                'a' => "\u{0103}", 'e' => "\u{0115}", 'g' => "\u{011F}", 'i' => "\u{012D}", 'o' => "\u{014F}", 'u' => "\u{016D}",
            ],
            'v' => [
                'C' => "\u{010C}", 'D' => "\u{010E}", 'E' => "\u{011A}", 'L' => "\u{013D}", 'N' => "\u{0147}", 'R' => "\u{0158}", 'S' => "\u{0160}", 'T' => "\u{0164}", 'Z' => "\u{017D}",
                'c' => "\u{010D}", 'd' => "\u{010F}", 'e' => "\u{011B}", 'l' => "\u{013E}", 'n' => "\u{0148}", 'r' => "\u{0159}", 's' => "\u{0161}", 't' => "\u{0165}", 'z' => "\u{017E}",
            ],
            'H' => [
                'O' => "\u{0150}", 'U' => "\u{0170}",
                'o' => "\u{0151}", 'u' => "\u{0171}",
            ],
            'r' => [
                'A' => "\u{00C5}", 'U' => "\u{016E}",
                'a' => "\u{00E5}", 'u' => "\u{016F}",
            ],
            'c' => [
                'C' => "\u{00C7}", 'G' => "\u{0122}", 'K' => "\u{0136}", 'L' => "\u{013B}", 'N' => "\u{0145}", 'R' => "\u{0156}", 'S' => "\u{015E}", 'T' => "\u{0162}",
                'c' => "\u{00E7}", 'g' => "\u{0123}", 'k' => "\u{0137}", 'l' => "\u{013C}", 'n' => "\u{0146}", 'r' => "\u{0157}", 's' => "\u{015F}", 't' => "\u{0163}",
            ],
            'k' => [
                'A' => "\u{0104}", 'E' => "\u{0118}", 'I' => "\u{012E}", 'U' => "\u{0172}",
                'a' => "\u{0105}", 'e' => "\u{0119}", 'i' => "\u{012F}", 'u' => "\u{0173}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function latexSpecialLetterMap(): array
    {
        return [
            'AE' => "\u{00C6}",
            'ae' => "\u{00E6}",
            'OE' => "\u{0152}",
            'oe' => "\u{0153}",
            'AA' => "\u{00C5}",
            'aa' => "\u{00E5}",
            'O' => "\u{00D8}",
            'o' => "\u{00F8}",
            'L' => "\u{0141}",
            'l' => "\u{0142}",
            'SS' => 'SS',
            'ss' => "\u{00DF}",
            'DH' => "\u{00D0}",
            'dh' => "\u{00F0}",
            'TH' => "\u{00DE}",
            'th' => "\u{00FE}",
            'NG' => "\u{014A}",
            'ng' => "\u{014B}",
            'i' => "\u{0131}",
            'j' => "\u{0237}",
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function standardStrings(): array
    {
        return [
            'jan' => 'January',
            'feb' => 'February',
            'mar' => 'March',
            'apr' => 'April',
            'may' => 'May',
            'jun' => 'June',
            'jul' => 'July',
            'aug' => 'August',
            'sep' => 'September',
            'oct' => 'October',
            'nov' => 'November',
            'dec' => 'December',
        ];
    }
}
