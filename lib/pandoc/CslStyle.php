<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CslStyle
{
    private const CSL_NS = 'http://purl.org/net/xbiblio/csl';
    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /** @var array{punctuationInQuote:bool, limitDayOrdinalsToDay1:bool} */
    private const DEFAULT_LOCALE_OPTIONS = [
        'punctuationInQuote' => false,
        'limitDayOrdinalsToDay1' => false,
    ];

    /** @var list<string> */
    private const TERM_FORMS = ['long', 'short', 'verb', 'verb-short', 'symbol'];

    /** @var array<string, array{single:string, multiple:string, match?:string, gender?:string}> */
    private const DEFAULT_TERMS = [
        'and|long' => ['single' => 'and', 'multiple' => 'and'],
        'and|symbol' => ['single' => '&', 'multiple' => '&'],
        'et-al|long' => ['single' => 'et al.', 'multiple' => 'et al.'],
        'and others|long' => ['single' => 'and others', 'multiple' => 'and others'],
        'ellipsis|long' => ['single' => "\u{2026}", 'multiple' => "\u{2026}"],
        'no date|long' => ['single' => 'n.d.', 'multiple' => 'n.d.'],
        'accessed|long' => ['single' => 'Accessed', 'multiple' => 'Accessed'],
        'circa|long' => ['single' => 'circa', 'multiple' => 'circa'],
        'circa|short' => ['single' => 'c.', 'multiple' => 'cc.'],
        'season-01|long' => ['single' => 'Spring', 'multiple' => 'Spring'],
        'season-02|long' => ['single' => 'Summer', 'multiple' => 'Summer'],
        'season-03|long' => ['single' => 'Autumn', 'multiple' => 'Autumn'],
        'season-04|long' => ['single' => 'Winter', 'multiple' => 'Winter'],
        'event|long' => ['single' => 'Event', 'multiple' => 'Events'],
        'event-title-addon|long' => ['single' => 'Event addendum', 'multiple' => 'Event addenda'],
        'event-type|long' => ['single' => 'Event type', 'multiple' => 'Event types'],
        'event-organizer|long' => ['single' => 'Event organizer', 'multiple' => 'Event organizers'],
        'event-organizer|short' => ['single' => 'org.', 'multiple' => 'orgs.'],
        'event-organizer|verb' => ['single' => 'organized by', 'multiple' => 'organized by'],
        'event-organizer|verb-short' => ['single' => 'org. by', 'multiple' => 'org. by'],
        'event-place|long' => ['single' => 'Event place', 'multiple' => 'Event places'],
        'event-date|long' => ['single' => 'Event date', 'multiple' => 'Event dates'],
        'author|long' => ['single' => '', 'multiple' => ''],
        'chair|long' => ['single' => 'chair', 'multiple' => 'chairs'],
        'chair|verb' => ['single' => 'chaired by', 'multiple' => 'chaired by'],
        'collection-editor|long' => ['single' => 'editor', 'multiple' => 'editors'],
        'collection-editor|short' => ['single' => 'ed.', 'multiple' => 'eds.'],
        'collection-editor|verb' => ['single' => 'edited by', 'multiple' => 'edited by'],
        'collection-editor|verb-short' => ['single' => 'ed. by', 'multiple' => 'ed. by'],
        'composer|long' => ['single' => '', 'multiple' => ''],
        'composer|verb' => ['single' => 'composed by', 'multiple' => 'composed by'],
        'composer|verb-short' => ['single' => 'comp. by', 'multiple' => 'comp. by'],
        'compiler|long' => ['single' => 'compiler', 'multiple' => 'compilers'],
        'compiler|short' => ['single' => 'comp.', 'multiple' => 'comps.'],
        'compiler|verb' => ['single' => 'compiled by', 'multiple' => 'compiled by'],
        'compiler|verb-short' => ['single' => 'comp. by', 'multiple' => 'comp. by'],
        'container-author|long' => ['single' => '', 'multiple' => ''],
        'container-author|verb' => ['single' => 'by', 'multiple' => 'by'],
        'contributor|long' => ['single' => 'contributor', 'multiple' => 'contributors'],
        'contributor|short' => ['single' => 'contrib.', 'multiple' => 'contribs.'],
        'contributor|verb' => ['single' => 'with', 'multiple' => 'with'],
        'curator|long' => ['single' => 'curator', 'multiple' => 'curators'],
        'curator|short' => ['single' => 'cur.', 'multiple' => 'curs.'],
        'curator|verb' => ['single' => 'curated by', 'multiple' => 'curated by'],
        'curator|verb-short' => ['single' => 'cur. by', 'multiple' => 'cur. by'],
        'director|long' => ['single' => 'director', 'multiple' => 'directors'],
        'director|short' => ['single' => 'dir.', 'multiple' => 'dirs.'],
        'director|verb' => ['single' => 'directed by', 'multiple' => 'directed by'],
        'director|verb-short' => ['single' => 'dir. by', 'multiple' => 'dir. by'],
        'editor|long' => ['single' => 'editor', 'multiple' => 'editors'],
        'editor|short' => ['single' => 'ed.', 'multiple' => 'eds.'],
        'editor|verb' => ['single' => 'edited by', 'multiple' => 'edited by'],
        'editor|verb-short' => ['single' => 'ed.', 'multiple' => 'eds.'],
        'editortranslator|long' => ['single' => 'editor & translator', 'multiple' => 'editors & translators'],
        'editortranslator|short' => ['single' => 'ed. & trans.', 'multiple' => 'eds. & trans.'],
        'editortranslator|verb' => ['single' => 'edited & translated by', 'multiple' => 'edited & translated by'],
        'editortranslator|verb-short' => ['single' => 'ed. & trans.', 'multiple' => 'eds. & trans.'],
        'editorial-director|long' => ['single' => 'editor', 'multiple' => 'editors'],
        'editorial-director|short' => ['single' => 'ed.', 'multiple' => 'eds.'],
        'editorial-director|verb' => ['single' => 'edited by', 'multiple' => 'edited by'],
        'editorial-director|verb-short' => ['single' => 'ed. by', 'multiple' => 'ed. by'],
        'translator|long' => ['single' => 'translator', 'multiple' => 'translators'],
        'translator|short' => ['single' => 'trans.', 'multiple' => 'trans.'],
        'translator|verb' => ['single' => 'translated by', 'multiple' => 'translated by'],
        'translator|verb-short' => ['single' => 'trans.', 'multiple' => 'trans.'],
        'executive-producer|long' => ['single' => 'executive producer', 'multiple' => 'executive producers'],
        'executive-producer|short' => ['single' => 'exec. prod.', 'multiple' => 'exec. prods.'],
        'executive-producer|verb' => ['single' => 'executive produced by', 'multiple' => 'executive produced by'],
        'executive-producer|verb-short' => ['single' => 'exec. prod. by', 'multiple' => 'exec. prod. by'],
        'guest|long' => ['single' => 'guest', 'multiple' => 'guests'],
        'guest|short' => ['single' => 'guest', 'multiple' => 'guests'],
        'guest|verb' => ['single' => 'featuring', 'multiple' => 'featuring'],
        'guest|verb-short' => ['single' => 'feat.', 'multiple' => 'feat.'],
        'host|long' => ['single' => 'host', 'multiple' => 'hosts'],
        'host|short' => ['single' => 'host', 'multiple' => 'hosts'],
        'host|verb' => ['single' => 'hosted by', 'multiple' => 'hosted by'],
        'host|verb-short' => ['single' => 'hosted by', 'multiple' => 'hosted by'],
        'illustrator|long' => ['single' => 'illustrator', 'multiple' => 'illustrators'],
        'illustrator|short' => ['single' => 'ill.', 'multiple' => 'ills.'],
        'illustrator|verb' => ['single' => 'illustrated by', 'multiple' => 'illustrated by'],
        'illustrator|verb-short' => ['single' => 'ill. by', 'multiple' => 'ill. by'],
        'interviewer|verb' => ['single' => 'interview by', 'multiple' => 'interview by'],
        'narrator|long' => ['single' => 'narrator', 'multiple' => 'narrators'],
        'narrator|short' => ['single' => 'narr.', 'multiple' => 'narrs.'],
        'narrator|verb' => ['single' => 'narrated by', 'multiple' => 'narrated by'],
        'narrator|verb-short' => ['single' => 'narr. by', 'multiple' => 'narr. by'],
        'original-author|long' => ['single' => '', 'multiple' => ''],
        'original-author|verb' => ['single' => 'by', 'multiple' => 'by'],
        'performer|long' => ['single' => 'performer', 'multiple' => 'performers'],
        'performer|short' => ['single' => 'perf.', 'multiple' => 'perfs.'],
        'performer|verb' => ['single' => 'performed by', 'multiple' => 'performed by'],
        'performer|verb-short' => ['single' => 'perf. by', 'multiple' => 'perf. by'],
        'producer|long' => ['single' => 'producer', 'multiple' => 'producers'],
        'producer|short' => ['single' => 'prod.', 'multiple' => 'prods.'],
        'producer|verb' => ['single' => 'produced by', 'multiple' => 'produced by'],
        'producer|verb-short' => ['single' => 'prod. by', 'multiple' => 'prod. by'],
        'recipient|verb' => ['single' => 'to', 'multiple' => 'to'],
        'reviewed-author|verb' => ['single' => 'by', 'multiple' => 'by'],
        'redactor|long' => ['single' => 'redactor', 'multiple' => 'redactors'],
        'founder|long' => ['single' => 'founder', 'multiple' => 'founders'],
        'continuator|long' => ['single' => 'continuator', 'multiple' => 'continuators'],
        'reviser|long' => ['single' => 'reviser', 'multiple' => 'revisers'],
        'collaborator|long' => ['single' => 'collaborator', 'multiple' => 'collaborators'],
        'commentator|long' => ['single' => 'commentator', 'multiple' => 'commentators'],
        'annotator|long' => ['single' => 'annotator', 'multiple' => 'annotators'],
        'introduction|long' => ['single' => 'introduction', 'multiple' => 'introductions'],
        'foreword|long' => ['single' => 'foreword', 'multiple' => 'forewords'],
        'afterword|long' => ['single' => 'afterword', 'multiple' => 'afterwords'],
        'script-writer|long' => ['single' => 'script writer', 'multiple' => 'script writers'],
        'script-writer|short' => ['single' => 'script', 'multiple' => 'scripts'],
        'script-writer|verb' => ['single' => 'written by', 'multiple' => 'written by'],
        'script-writer|verb-short' => ['single' => 'writ. by', 'multiple' => 'writ. by'],
        'open-quote|long' => ['single' => "\u{201C}", 'multiple' => "\u{201C}"],
        'close-quote|long' => ['single' => "\u{201D}", 'multiple' => "\u{201D}"],
        'page|long' => ['single' => 'page', 'multiple' => 'pages'],
        'page|short' => ['single' => 'p.', 'multiple' => 'pp.'],
        'article-locator|long' => ['single' => 'article', 'multiple' => 'articles'],
        'article-locator|short' => ['single' => 'art.', 'multiple' => 'arts.'],
        'appendix|long' => ['single' => 'appendix', 'multiple' => 'appendices'],
        'appendix|short' => ['single' => 'app.', 'multiple' => 'apps.'],
        'book|long' => ['single' => 'book', 'multiple' => 'books'],
        'book|short' => ['single' => 'bk.', 'multiple' => 'bks.'],
        'canon|long' => ['single' => 'canon', 'multiple' => 'canons'],
        'canon|short' => ['single' => 'c.', 'multiple' => 'cc.'],
        'chapter|long' => ['single' => 'chapter', 'multiple' => 'chapters'],
        'chapter|short' => ['single' => 'chap.', 'multiple' => 'chaps.'],
        'elocation|long' => ['single' => 'e-location', 'multiple' => 'e-locations'],
        'elocation|short' => ['single' => 'e-loc.', 'multiple' => 'e-locs.'],
        'equation|long' => ['single' => 'equation', 'multiple' => 'equations'],
        'equation|short' => ['single' => 'eq.', 'multiple' => 'eqs.'],
        'figure|long' => ['single' => 'figure', 'multiple' => 'figures'],
        'figure|short' => ['single' => 'fig.', 'multiple' => 'figs.'],
        'folio|long' => ['single' => 'folio', 'multiple' => 'folios'],
        'folio|short' => ['single' => 'fol.', 'multiple' => 'fols.'],
        'opus|long' => ['single' => 'opus', 'multiple' => 'opera'],
        'opus|short' => ['single' => 'op.', 'multiple' => 'opp.'],
        'part|long' => ['single' => 'part', 'multiple' => 'parts'],
        'part|short' => ['single' => 'pt.', 'multiple' => 'pts.'],
        'rule|long' => ['single' => 'rule', 'multiple' => 'rules'],
        'rule|short' => ['single' => 'r.', 'multiple' => 'rr.'],
        'sub-verbo|long' => ['single' => 'sub verbo', 'multiple' => 'sub verbis'],
        'sub-verbo|short' => ['single' => 's.v.', 'multiple' => 's.vv.'],
        'supplement|long' => ['single' => 'supplement', 'multiple' => 'supplements'],
        'supplement|short' => ['single' => 'supp.', 'multiple' => 'supps.'],
        'table|long' => ['single' => 'table', 'multiple' => 'tables'],
        'table|short' => ['single' => 'tbl.', 'multiple' => 'tbls.'],
        'timestamp|long' => ['single' => 'timestamp', 'multiple' => 'timestamps'],
        'timestamp|short' => ['single' => 'ts.', 'multiple' => 'ts.'],
        'title|long' => ['single' => 'title', 'multiple' => 'titles'],
        'title|short' => ['single' => 'ttl.', 'multiple' => 'ttls.'],
        'note|long' => ['single' => 'note', 'multiple' => 'notes'],
        'note|short' => ['single' => 'n.', 'multiple' => 'nn.'],
        'section|long' => ['single' => 'section', 'multiple' => 'sections'],
        'section|short' => ['single' => 'sec.', 'multiple' => 'secs.'],
        'section|symbol' => ['single' => "\u{00A7}", 'multiple' => "\u{00A7}\u{00A7}"],
        'column|long' => ['single' => 'column', 'multiple' => 'columns'],
        'column|short' => ['single' => 'col.', 'multiple' => 'cols.'],
        'line|long' => ['single' => 'line', 'multiple' => 'lines'],
        'line|short' => ['single' => 'l.', 'multiple' => 'll.'],
        'paragraph|long' => ['single' => 'paragraph', 'multiple' => 'paragraphs'],
        'paragraph|short' => ['single' => 'para.', 'multiple' => 'paras.'],
        'paragraph|symbol' => ['single' => "\u{00B6}", 'multiple' => "\u{00B6}\u{00B6}"],
        'page-range-delimiter|long' => ['single' => "\u{2013}", 'multiple' => "\u{2013}"],
        'verse|long' => ['single' => 'verse', 'multiple' => 'verses'],
        'verse|short' => ['single' => 'v.', 'multiple' => 'vv.'],
        'volume|long' => ['single' => 'volume', 'multiple' => 'volumes'],
        'volume|short' => ['single' => 'vol.', 'multiple' => 'vols.'],
        'issue|long' => ['single' => 'issue', 'multiple' => 'issues'],
        'issue|short' => ['single' => 'no.', 'multiple' => 'nos.'],
        'number|long' => ['single' => 'number', 'multiple' => 'numbers'],
        'number|short' => ['single' => 'no.', 'multiple' => 'nos.'],
        'printing-number|long' => ['single' => 'printing number', 'multiple' => 'printing numbers'],
        'printing-number|short' => ['single' => 'printing no.', 'multiple' => 'printing nos.'],
        'supplement-number|long' => ['single' => 'supplement number', 'multiple' => 'supplement numbers'],
        'supplement-number|short' => ['single' => 'supp. no.', 'multiple' => 'supp. nos.'],
        'edition|long' => ['single' => 'edition', 'multiple' => 'editions'],
        'edition|short' => ['single' => 'ed.', 'multiple' => 'eds.'],
        'version|long' => ['single' => 'version', 'multiple' => 'versions'],
        'version|short' => ['single' => 'ver.', 'multiple' => 'vers.'],
        'ordinal|long' => ['single' => 'th', 'multiple' => 'th'],
        'ordinal-01|long' => ['single' => 'st', 'multiple' => 'st'],
        'ordinal-02|long' => ['single' => 'nd', 'multiple' => 'nd'],
        'ordinal-03|long' => ['single' => 'rd', 'multiple' => 'rd'],
        'ordinal-11|long' => ['single' => 'th', 'multiple' => 'th'],
        'ordinal-12|long' => ['single' => 'th', 'multiple' => 'th'],
        'ordinal-13|long' => ['single' => 'th', 'multiple' => 'th'],
        'long-ordinal-01|long' => ['single' => 'first', 'multiple' => 'first'],
        'long-ordinal-02|long' => ['single' => 'second', 'multiple' => 'second'],
        'long-ordinal-03|long' => ['single' => 'third', 'multiple' => 'third'],
        'long-ordinal-04|long' => ['single' => 'fourth', 'multiple' => 'fourth'],
        'long-ordinal-05|long' => ['single' => 'fifth', 'multiple' => 'fifth'],
        'long-ordinal-06|long' => ['single' => 'sixth', 'multiple' => 'sixth'],
        'long-ordinal-07|long' => ['single' => 'seventh', 'multiple' => 'seventh'],
        'long-ordinal-08|long' => ['single' => 'eighth', 'multiple' => 'eighth'],
        'long-ordinal-09|long' => ['single' => 'ninth', 'multiple' => 'ninth'],
        'long-ordinal-10|long' => ['single' => 'tenth', 'multiple' => 'tenth'],
        'month-01|long' => ['single' => 'January', 'multiple' => 'January'],
        'month-01|short' => ['single' => 'Jan.', 'multiple' => 'Jan.'],
        'month-02|long' => ['single' => 'February', 'multiple' => 'February'],
        'month-02|short' => ['single' => 'Feb.', 'multiple' => 'Feb.'],
        'month-03|long' => ['single' => 'March', 'multiple' => 'March'],
        'month-03|short' => ['single' => 'Mar.', 'multiple' => 'Mar.'],
        'month-04|long' => ['single' => 'April', 'multiple' => 'April'],
        'month-04|short' => ['single' => 'Apr.', 'multiple' => 'Apr.'],
        'month-05|long' => ['single' => 'May', 'multiple' => 'May'],
        'month-05|short' => ['single' => 'May', 'multiple' => 'May'],
        'month-06|long' => ['single' => 'June', 'multiple' => 'June'],
        'month-06|short' => ['single' => 'Jun.', 'multiple' => 'Jun.'],
        'month-07|long' => ['single' => 'July', 'multiple' => 'July'],
        'month-07|short' => ['single' => 'Jul.', 'multiple' => 'Jul.'],
        'month-08|long' => ['single' => 'August', 'multiple' => 'August'],
        'month-08|short' => ['single' => 'Aug.', 'multiple' => 'Aug.'],
        'month-09|long' => ['single' => 'September', 'multiple' => 'September'],
        'month-09|short' => ['single' => 'Sep.', 'multiple' => 'Sep.'],
        'month-10|long' => ['single' => 'October', 'multiple' => 'October'],
        'month-10|short' => ['single' => 'Oct.', 'multiple' => 'Oct.'],
        'month-11|long' => ['single' => 'November', 'multiple' => 'November'],
        'month-11|short' => ['single' => 'Nov.', 'multiple' => 'Nov.'],
        'month-12|long' => ['single' => 'December', 'multiple' => 'December'],
        'month-12|short' => ['single' => 'Dec.', 'multiple' => 'Dec.'],
    ];

    /** @var array{citation:array<string, mixed>, bibliography:array<string, mixed>} */
    private const DEFAULT_NAME_RENDERING = [
        'citation' => [
            'delimiter' => ', ',
            'and' => 'text',
            'form' => 'long',
            'etAlMin' => 3,
            'etAlUseFirst' => 1,
            'etAlUseLast' => false,
            'etAlSubsequentMin' => null,
            'etAlSubsequentUseFirst' => null,
            'delimiterPrecedesEtAl' => 'contextual',
            'delimiterPrecedesLast' => 'contextual',
            'delimiterPrecedesLastExplicit' => false,
            'etAl' => [
                'term' => 'et-al',
                'prefix' => '',
                'suffix' => '',
                'textCase' => '',
                'stripPeriods' => false,
                'quotes' => false,
            ],
            'initialize' => true,
            'initializeWith' => null,
            'initializeWithHyphen' => true,
            'nameAsSortOrder' => 'first',
            'nameAsSortOrderExplicit' => false,
            'sortSeparator' => ', ',
            'demoteNonDroppingParticle' => 'never',
            'nameParts' => [],
            'institution' => null,
            'label' => null,
        ],
        'bibliography' => [
            'delimiter' => '; ',
            'and' => 'text',
            'form' => 'long',
            'etAlMin' => null,
            'etAlUseFirst' => 1,
            'etAlUseLast' => false,
            'etAlSubsequentMin' => null,
            'etAlSubsequentUseFirst' => null,
            'delimiterPrecedesEtAl' => 'contextual',
            'delimiterPrecedesLast' => 'contextual',
            'delimiterPrecedesLastExplicit' => false,
            'etAl' => [
                'term' => 'et-al',
                'prefix' => '',
                'suffix' => '',
                'textCase' => '',
                'stripPeriods' => false,
                'quotes' => false,
            ],
            'initialize' => true,
            'initializeWith' => null,
            'initializeWithHyphen' => true,
            'nameAsSortOrder' => 'all',
            'nameAsSortOrderExplicit' => false,
            'sortSeparator' => ', ',
            'demoteNonDroppingParticle' => 'never',
            'nameParts' => [],
            'institution' => null,
            'label' => null,
        ],
    ];

    /**
     * @param array{prefix:string, suffix:string, delimiter:string} $citationLayout
     * @param array{prefix:string, suffix:string, delimiter:string} $bibliographyLayout
     * @param array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string} $bibliographyOptions
     * @param array{disambiguateAddYearSuffix:bool, disambiguateAddNames:bool, disambiguateAddGivenName:bool, givenNameDisambiguationRule:string, collapse:string, nearNoteDistance:int, citeGroupDelimiter:string, yearSuffixDelimiter:string, afterCollapseDelimiter:string|null} $citationOptions
     * @param list<array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool}> $citationSortKeys
     * @param list<array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool}> $bibliographySortKeys
     * @param list<array<string, mixed>> $citationRenderingElements
     * @param list<array<string, mixed>> $bibliographyRenderingElements
     * @param array<string, list<array<string, mixed>>> $macros
     * @param array{citation:array<string, mixed>, bibliography:array<string, mixed>} $nameRendering
     * @param array<string, array{single:string, multiple:string, match?:string, gender?:string}> $terms
     * @param array{punctuationInQuote:bool, limitDayOrdinalsToDay1:bool} $localeOptions
     * @param array{title:string, id:string, class:string, defaultLocale:string, pageRangeFormat:string} $metadata
     */
    private function __construct(
        private readonly array $citationLayout,
        private readonly array $bibliographyLayout,
        private readonly array $bibliographyOptions,
        private readonly array $citationOptions,
        private readonly array $citationSortKeys,
        private readonly array $bibliographySortKeys,
        private readonly array $citationRenderingElements,
        private readonly array $bibliographyRenderingElements,
        private readonly array $macros,
        private readonly array $nameRendering,
        private readonly array $terms,
        private readonly array $localeOptions,
        private readonly array $metadata,
    ) {
    }

    public static function default(): self
    {
        return new self(
            ['prefix' => '(', 'suffix' => ')', 'delimiter' => '; '],
            ['prefix' => '', 'suffix' => '', 'delimiter' => ' '],
            ['hangingIndent' => false, 'entrySpacing' => null, 'lineSpacing' => null, 'secondFieldAlign' => '', 'subsequentAuthorSubstitute' => '', 'subsequentAuthorSubstituteRule' => 'complete-all'],
            ['disambiguateAddYearSuffix' => false, 'disambiguateAddNames' => false, 'disambiguateAddGivenName' => false, 'givenNameDisambiguationRule' => 'by-cite', 'collapse' => '', 'nearNoteDistance' => 5, 'citeGroupDelimiter' => ', ', 'yearSuffixDelimiter' => ',', 'afterCollapseDelimiter' => null],
            [],
            [],
            [],
            [],
            [],
            self::DEFAULT_NAME_RENDERING,
            self::DEFAULT_TERMS,
            self::DEFAULT_LOCALE_OPTIONS,
            ['title' => '', 'id' => '', 'class' => 'in-text', 'defaultLocale' => '', 'pageRangeFormat' => '']
        );
    }

    /**
     * @param list<string> $localeXmls
     */
    public static function fromXml(string $styleXml, array $localeXmls = []): self
    {
        $dom = self::loadXml($styleXml, 'CSL style XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'style') {
            throw new \InvalidArgumentException('CSL style XML root element must be style');
        }

        if ($root->namespaceURI !== self::CSL_NS) {
            throw new \InvalidArgumentException('CSL style XML root element must use the CSL namespace');
        }

        if ($root->getAttribute('version') !== '1.0') {
            throw new \InvalidArgumentException('CSL style XML must declare version 1.0');
        }

        $defaultLocale = trim($root->getAttribute('default-locale'));
        $globalNameRenderingOverrides = self::globalNameRenderingOverrides($root);
        $macros = self::parseMacros($root);
        $terms = self::DEFAULT_TERMS;
        $localeOptions = self::DEFAULT_LOCALE_OPTIONS;
        foreach ($localeXmls as $index => $localeXml) {
            if (!is_string($localeXml)) {
                throw new \InvalidArgumentException('CSL locale XML at index ' . $index . ' must be a string');
            }

            $locale = self::parseLocaleXml($localeXml);
            $terms = self::applyLocaleElementTerms($locale, $terms);
            $localeOptions = self::applyLocaleElementOptions($locale, $localeOptions);
        }

        foreach (self::matchingStyleLocales(self::directChildren($root, 'locale'), $defaultLocale) as $locale) {
            $terms = self::applyLocaleElementTerms($locale, $terms);
            $localeOptions = self::applyLocaleElementOptions($locale, $localeOptions);
        }

        $citation = self::directChild($root, 'citation');
        if (!$citation instanceof \DOMElement) {
            throw new \InvalidArgumentException('CSL style XML must contain a citation element');
        }

        $layout = self::directChild($citation, 'layout');
        if (!$layout instanceof \DOMElement) {
            throw new \InvalidArgumentException('CSL citation element must contain a layout element');
        }

        $bibliography = self::directChild($root, 'bibliography');
        $bibliographyLayoutElement = null;
        if ($bibliography instanceof \DOMElement) {
            $bibliographyLayoutElement = self::directChild($bibliography, 'layout');
            if (!$bibliographyLayoutElement instanceof \DOMElement) {
                throw new \InvalidArgumentException('CSL bibliography element must contain a layout element when present');
            }
        }

        $info = self::directChild($root, 'info');
        $metadata = [
            'title' => $info instanceof \DOMElement ? self::childText($info, 'title') : '',
            'id' => $info instanceof \DOMElement ? self::childText($info, 'id') : '',
            'class' => trim($root->getAttribute('class')),
            'defaultLocale' => $defaultLocale,
            'pageRangeFormat' => self::pageRangeFormatAttribute($root),
        ];

        $citationRenderingElements = self::renderingElements($layout, 'citation');
        $bibliographyRenderingElements = $bibliographyLayoutElement instanceof \DOMElement
            ? self::renderingElements($bibliographyLayoutElement, 'bibliography')
            : [];
        self::validateMacroReferences($citationRenderingElements, $bibliographyRenderingElements, $macros);
        $citationNameRendering = self::firstAuthorEditorNamesElement($layout) instanceof \DOMElement
            ? self::nameRenderingOptions($layout, 'citation')
            : (self::nameRenderingOptionsForRenderingElements($citationRenderingElements, 'citation', $macros) ?? self::DEFAULT_NAME_RENDERING['citation']);
        $bibliographyNameRendering = $bibliographyLayoutElement instanceof \DOMElement
            ? (
                self::firstAuthorEditorNamesElement($bibliographyLayoutElement) instanceof \DOMElement
                    ? self::nameRenderingOptions($bibliographyLayoutElement, 'bibliography')
                    : (self::nameRenderingOptionsForRenderingElements($bibliographyRenderingElements, 'bibliography', $macros) ?? self::DEFAULT_NAME_RENDERING['bibliography'])
            )
            : self::DEFAULT_NAME_RENDERING['bibliography'];
        $citationNameRendering = self::mergeNameRenderingOptions($citationNameRendering, $globalNameRenderingOverrides);
        $bibliographyNameRendering = self::mergeNameRenderingOptions($bibliographyNameRendering, $globalNameRenderingOverrides);

        return new self(
            self::layoutAttributes($layout, '; '),
            $bibliographyLayoutElement instanceof \DOMElement
                ? self::layoutAttributes($bibliographyLayoutElement, ' ')
                : ['prefix' => '', 'suffix' => '', 'delimiter' => ' '],
            $bibliography instanceof \DOMElement
                ? self::parseBibliographyOptions($bibliography)
                : ['hangingIndent' => false, 'entrySpacing' => null, 'lineSpacing' => null, 'secondFieldAlign' => '', 'subsequentAuthorSubstitute' => '', 'subsequentAuthorSubstituteRule' => 'complete-all'],
            self::parseCitationOptions($citation),
            self::sortKeys($citation, 'citation'),
            $bibliography instanceof \DOMElement ? self::sortKeys($bibliography, 'bibliography') : [],
            $citationRenderingElements,
            $bibliographyRenderingElements,
            $macros,
            [
                'citation' => $citationNameRendering,
                'bibliography' => $bibliographyNameRendering,
            ],
            $terms,
            $localeOptions,
            $metadata
        );
    }

    public function citationPrefix(): string
    {
        return $this->citationLayout['prefix'];
    }

    public function citationSuffix(): string
    {
        return $this->citationLayout['suffix'];
    }

    public function citationDelimiter(): string
    {
        return $this->citationLayout['delimiter'];
    }

    public function bibliographyDelimiter(): string
    {
        return $this->bibliographyLayout['delimiter'];
    }

    public function formatBibliographyEntry(string $entry): string
    {
        if ($entry === '') {
            return '';
        }

        return $this->bibliographyLayout['prefix'] . $entry . $this->bibliographyLayout['suffix'];
    }

    /**
     * @return array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string}
     */
    public function bibliographyOptions(): array
    {
        return $this->bibliographyOptions;
    }

    /**
     * @return array{disambiguateAddYearSuffix:bool, disambiguateAddNames:bool, disambiguateAddGivenName:bool, givenNameDisambiguationRule:string, collapse:string, nearNoteDistance:int, citeGroupDelimiter:string, yearSuffixDelimiter:string, afterCollapseDelimiter:string|null}
     */
    public function citationOptions(): array
    {
        return $this->citationOptions;
    }

    public function term(string $name, string $form = 'long', bool $plural = false, string $genderForm = ''): string
    {
        return $this->termOrNull($name, $form, $plural, $genderForm) ?? $name;
    }

    public function termOrNull(string $name, string $form = 'long', bool $plural = false, string $genderForm = ''): ?string
    {
        foreach (self::termFallbackKeys($name, $form, $genderForm) as $key) {
            $term = $this->terms[$key] ?? null;
            if ($term !== null) {
                return $plural ? $term['multiple'] : $term['single'];
            }
        }

        return null;
    }

    public function termGender(string $name, string $form = 'long'): string
    {
        foreach (self::termFallbackKeys($name, $form) as $key) {
            $term = $this->terms[$key] ?? null;
            $gender = is_array($term) ? (string) ($term['gender'] ?? '') : '';
            if ($gender !== '') {
                return $gender;
            }
        }

        return '';
    }

    public function ordinalSuffixTerm(int $number, string $genderForm = ''): ?string
    {
        $absolute = abs($number);
        $lastTwo = $absolute % 100;
        if ($lastTwo >= 10) {
            $term = $this->ordinalSuffixCandidate($absolute, $lastTwo, $genderForm);
            if ($term !== null) {
                return $term;
            }
        }

        return $this->ordinalSuffixCandidate($absolute, $absolute % 10, $genderForm)
            ?? $this->termOrNull('ordinal', 'long', false, $genderForm);
    }

    /**
     * @return list<array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool}>
     */
    public function citationSortKeys(): array
    {
        return $this->citationSortKeys;
    }

    /**
     * @return list<array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool}>
     */
    public function bibliographySortKeys(): array
    {
        return $this->bibliographySortKeys;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function citationRenderingElements(): array
    {
        return $this->citationRenderingElements;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bibliographyRenderingElements(): array
    {
        return $this->bibliographyRenderingElements;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function macros(): array
    {
        return $this->macros;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function macroRenderingElements(string $name): ?array
    {
        return $this->macros[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function citationNameRendering(): array
    {
        return $this->nameRendering['citation'];
    }

    /**
     * @return array<string, mixed>
     */
    public function bibliographyNameRendering(): array
    {
        return $this->nameRendering['bibliography'];
    }

    public function defaultLocale(): string
    {
        return $this->metadata['defaultLocale'];
    }

    public function styleClass(): string
    {
        return $this->metadata['class'];
    }

    public function pageRangeFormat(): string
    {
        return $this->metadata['pageRangeFormat'];
    }

    public function punctuationInQuote(): bool
    {
        return $this->localeOptions['punctuationInQuote'];
    }

    public function limitDayOrdinalsToDay1(): bool
    {
        return $this->localeOptions['limitDayOrdinalsToDay1'];
    }

    /**
     * @return array{title:string, id:string, class:string, defaultLocale:string, pageRangeFormat:string, citationLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyOptions:array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string}, citationOptions:array{disambiguateAddYearSuffix:bool, disambiguateAddNames:bool, disambiguateAddGivenName:bool, givenNameDisambiguationRule:string, collapse:string, nearNoteDistance:int, citeGroupDelimiter:string, yearSuffixDelimiter:string, afterCollapseDelimiter:string|null}, citationSort:list<array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool}>, bibliographySort:list<array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool}>, citationRendering:list<array<string, mixed>>, bibliographyRendering:list<array<string, mixed>>, macros:array<string, list<array<string, mixed>>>, nameRendering:array{citation:array<string, mixed>, bibliography:array<string, mixed>}, localeOptions:array{punctuationInQuote:bool, limitDayOrdinalsToDay1:bool}, terms:array<string, string>}
     */
    public function summary(): array
    {
        return [
            ...$this->metadata,
            'citationLayout' => $this->citationLayout,
            'bibliographyLayout' => $this->bibliographyLayout,
            'bibliographyOptions' => $this->bibliographyOptions,
            'citationOptions' => $this->citationOptions,
            'citationSort' => $this->citationSortKeys,
            'bibliographySort' => $this->bibliographySortKeys,
            'citationRendering' => $this->citationRenderingElements,
            'bibliographyRendering' => $this->bibliographyRenderingElements,
            'macros' => $this->macros,
            'nameRendering' => $this->nameRendering,
            'localeOptions' => $this->localeOptions,
            'terms' => [
                'and' => $this->term('and'),
                'etAl' => $this->term('et-al'),
                'noDate' => $this->term('no date'),
                'accessed' => $this->term('accessed'),
                'event' => $this->term('event'),
                'eventTitleAddon' => $this->term('event-title-addon'),
                'eventType' => $this->term('event-type'),
                'eventOrganizer' => $this->term('event-organizer'),
                'eventPlace' => $this->term('event-place'),
                'eventDate' => $this->term('event-date'),
            ],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function parseMacros(\DOMElement $root): array
    {
        $macros = [];
        foreach (self::directChildren($root, 'macro') as $macro) {
            $name = trim($macro->getAttribute('name'));
            if ($name === '') {
                throw new \InvalidArgumentException('CSL macro element is missing a name');
            }

            if (array_key_exists($name, $macros)) {
                throw new \InvalidArgumentException('Duplicate CSL macro name: ' . $name);
            }

            $macros[$name] = self::renderingElements($macro, 'macro ' . $name);
        }

        return $macros;
    }

    private static function pageRangeFormatAttribute(\DOMElement $style): string
    {
        $format = strtolower(trim($style->getAttribute('page-range-format')));
        if ($format === '') {
            return '';
        }

        if (!in_array($format, ['chicago', 'expanded', 'minimal', 'minimal-two'], true)) {
            throw new \InvalidArgumentException('CSL style page-range-format must be chicago, expanded, minimal, or minimal-two');
        }

        return $format;
    }

    /**
     * @param list<array<string, mixed>> $citationElements
     * @param list<array<string, mixed>> $bibliographyElements
     * @param array<string, list<array<string, mixed>>> $macros
     */
    private static function validateMacroReferences(array $citationElements, array $bibliographyElements, array $macros): void
    {
        self::validateRenderingMacroReferences($citationElements, $macros, [], 'citation');
        self::validateRenderingMacroReferences($bibliographyElements, $macros, [], 'bibliography');
        foreach (array_keys($macros) as $name) {
            self::validateRenderingMacroReferences($macros[$name], $macros, [$name], 'macro ' . $name);
        }
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, list<array<string, mixed>>> $macros
     * @param list<string> $stack
     */
    private static function validateRenderingMacroReferences(array $elements, array $macros, array $stack, string $context): void
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            if (($element['type'] ?? '') === 'text' && array_key_exists('macro', $element)) {
                $name = (string) $element['macro'];
                if (!array_key_exists($name, $macros)) {
                    throw new \InvalidArgumentException('CSL ' . $context . ' references undefined macro: ' . $name);
                }

                if (in_array($name, $stack, true)) {
                    throw new \InvalidArgumentException('CSL macro recursion detected: ' . implode(' -> ', [...$stack, $name]));
                }

                self::validateRenderingMacroReferences($macros[$name], $macros, [...$stack, $name], 'macro ' . $name);
            }

            if (($element['type'] ?? '') === 'names' && isset($element['substitute']) && is_array($element['substitute'])) {
                self::validateRenderingMacroReferences($element['substitute'], $macros, $stack, $context);
            }

            if (($element['type'] ?? '') === 'group' && isset($element['children']) && is_array($element['children'])) {
                self::validateRenderingMacroReferences($element['children'], $macros, $stack, $context);
            }

            if (($element['type'] ?? '') === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (is_array($branch) && isset($branch['children']) && is_array($branch['children'])) {
                        self::validateRenderingMacroReferences($branch['children'], $macros, $stack, $context);
                    }
                }

                if (isset($element['else']) && is_array($element['else'])) {
                    self::validateRenderingMacroReferences($element['else'], $macros, $stack, $context);
                }
            }
        }
    }

    /**
     * @return array{prefix:string, suffix:string, delimiter:string}
     */
    private static function layoutAttributes(\DOMElement $layout, string $defaultDelimiter): array
    {
        return [
            'prefix' => $layout->hasAttribute('prefix') ? $layout->getAttribute('prefix') : '',
            'suffix' => $layout->hasAttribute('suffix') ? $layout->getAttribute('suffix') : '',
            'delimiter' => $layout->hasAttribute('delimiter') ? $layout->getAttribute('delimiter') : $defaultDelimiter,
        ];
    }

    /**
     * @return array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string}
     */
    private static function parseBibliographyOptions(\DOMElement $bibliography): array
    {
        $substituteRule = trim($bibliography->getAttribute('subsequent-author-substitute-rule'));
        if ($substituteRule === '') {
            $substituteRule = 'complete-all';
        }
        if (!in_array($substituteRule, ['complete-all', 'complete-each', 'partial-each', 'partial-first'], true)) {
            throw new \InvalidArgumentException('CSL bibliography attribute subsequent-author-substitute-rule must be complete-all, complete-each, partial-each, or partial-first');
        }

        $secondFieldAlign = trim($bibliography->getAttribute('second-field-align'));
        if ($secondFieldAlign !== '' && !in_array($secondFieldAlign, ['flush', 'margin'], true)) {
            throw new \InvalidArgumentException('CSL bibliography attribute second-field-align must be flush or margin');
        }

        return [
            'hangingIndent' => self::booleanAttribute($bibliography, 'hanging-indent', false),
            'entrySpacing' => self::integerAttribute($bibliography, 'entry-spacing'),
            'lineSpacing' => self::integerAttribute($bibliography, 'line-spacing'),
            'secondFieldAlign' => $secondFieldAlign,
            'subsequentAuthorSubstitute' => $bibliography->hasAttribute('subsequent-author-substitute') ? $bibliography->getAttribute('subsequent-author-substitute') : '',
            'subsequentAuthorSubstituteRule' => $substituteRule,
        ];
    }

    /**
     * @return array{disambiguateAddYearSuffix:bool, disambiguateAddNames:bool, disambiguateAddGivenName:bool, givenNameDisambiguationRule:string, collapse:string, nearNoteDistance:int, citeGroupDelimiter:string, yearSuffixDelimiter:string, afterCollapseDelimiter:string|null}
     */
    private static function parseCitationOptions(\DOMElement $citation): array
    {
        $collapse = trim($citation->getAttribute('collapse'));
        if ($collapse !== '' && !in_array($collapse, ['citation-number', 'year', 'year-suffix', 'year-suffix-ranged'], true)) {
            throw new \InvalidArgumentException('CSL citation attribute collapse must be citation-number, year, year-suffix, or year-suffix-ranged');
        }

        $givenNameDisambiguationRule = trim($citation->getAttribute('givenname-disambiguation-rule'));
        if ($givenNameDisambiguationRule === '') {
            $givenNameDisambiguationRule = 'by-cite';
        }
        if (!in_array($givenNameDisambiguationRule, ['all-names', 'all-names-with-initials', 'primary-name', 'primary-name-with-initials', 'by-cite'], true)) {
            throw new \InvalidArgumentException('CSL citation attribute givenname-disambiguation-rule must be all-names, all-names-with-initials, primary-name, primary-name-with-initials, or by-cite');
        }

        $nearNoteDistance = 5;
        if ($citation->hasAttribute('near-note-distance')) {
            $value = trim($citation->getAttribute('near-note-distance'));
            if (preg_match('/^\d+$/', $value) !== 1) {
                throw new \InvalidArgumentException('CSL citation attribute near-note-distance must be a non-negative integer');
            }
            $nearNoteDistance = (int) $value;
        }

        return [
            'disambiguateAddYearSuffix' => self::booleanAttribute($citation, 'disambiguate-add-year-suffix', false, 'citation'),
            'disambiguateAddNames' => self::booleanAttribute($citation, 'disambiguate-add-names', false, 'citation'),
            'disambiguateAddGivenName' => self::booleanAttribute($citation, 'disambiguate-add-givenname', false, 'citation'),
            'givenNameDisambiguationRule' => $givenNameDisambiguationRule,
            'collapse' => $collapse,
            'nearNoteDistance' => $nearNoteDistance,
            'citeGroupDelimiter' => $citation->hasAttribute('cite-group-delimiter') ? $citation->getAttribute('cite-group-delimiter') : ', ',
            'yearSuffixDelimiter' => $citation->hasAttribute('year-suffix-delimiter') ? $citation->getAttribute('year-suffix-delimiter') : ',',
            'afterCollapseDelimiter' => $citation->hasAttribute('after-collapse-delimiter') ? $citation->getAttribute('after-collapse-delimiter') : null,
        ];
    }

    private static function booleanAttribute(\DOMElement $element, string $name, bool $default, string $context = 'bibliography'): bool
    {
        if (!$element->hasAttribute($name)) {
            return $default;
        }

        $value = strtolower(trim($element->getAttribute($name)));
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        throw new \InvalidArgumentException('CSL ' . $context . ' attribute ' . $name . ' must be true or false');
    }

    private static function integerAttribute(\DOMElement $element, string $name): ?int
    {
        if (!$element->hasAttribute($name)) {
            return null;
        }

        $value = trim($element->getAttribute($name));
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new \InvalidArgumentException('CSL bibliography attribute ' . $name . ' must be an integer');
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function nameRenderingOptions(\DOMElement $layout, string $scope): array
    {
        $names = self::firstAuthorEditorNamesElement($layout);
        if (!$names instanceof \DOMElement) {
            return self::DEFAULT_NAME_RENDERING[$scope];
        }

        return self::nameRenderingOptionsFromNames($names, $scope);
    }

    /**
     * @return array<string, mixed>
     */
    private static function nameRenderingOptionsFromNames(\DOMElement $names, string $scope): array
    {
        $defaults = self::DEFAULT_NAME_RENDERING[$scope];
        $overrides = self::nameRenderingOverridesFromNames($names, $scope);

        return self::mergeNameRenderingOptions($defaults, $overrides);
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, list<array<string, mixed>>> $macros
     * @param list<string> $stack
     * @return array<string, mixed>|null
     */
    private static function nameRenderingOptionsForRenderingElements(array $elements, string $scope, array $macros, array $stack = []): ?array
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            if ($type === 'names' && self::renderingNamesElementIncludesAuthorEditor($element)) {
                $overrides = is_array($element['nameRendering'] ?? null) ? $element['nameRendering'] : [];

                return self::mergeNameRenderingOptions(self::DEFAULT_NAME_RENDERING[$scope], $overrides);
            }

            if ($type === 'group' && isset($element['children']) && is_array($element['children'])) {
                $options = self::nameRenderingOptionsForRenderingElements($element['children'], $scope, $macros, $stack);
                if ($options !== null) {
                    return $options;
                }
            }

            if ($type === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (!is_array($branch) || !isset($branch['children']) || !is_array($branch['children'])) {
                        continue;
                    }

                    $options = self::nameRenderingOptionsForRenderingElements($branch['children'], $scope, $macros, $stack);
                    if ($options !== null) {
                        return $options;
                    }
                }

                if (isset($element['else']) && is_array($element['else'])) {
                    $options = self::nameRenderingOptionsForRenderingElements($element['else'], $scope, $macros, $stack);
                    if ($options !== null) {
                        return $options;
                    }
                }
            }

            if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
                $name = $element['macro'];
                if (isset($macros[$name]) && !in_array($name, $stack, true)) {
                    $options = self::nameRenderingOptionsForRenderingElements($macros[$name], $scope, $macros, [...$stack, $name]);
                    if ($options !== null) {
                        return $options;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $element
     */
    private static function renderingNamesElementIncludesAuthorEditor(array $element): bool
    {
        $variable = strtolower(trim((string) ($element['variable'] ?? 'author editor')));
        if ($variable === '') {
            return true;
        }

        $variables = preg_split('/\s+/', $variable) ?: [];

        return in_array('author', $variables, true) || in_array('editor', $variables, true);
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function mergeNameRenderingOptions(array $defaults, array $overrides): array
    {
        return [
            'delimiter' => is_string($overrides['delimiter'] ?? null) ? $overrides['delimiter'] : $defaults['delimiter'],
            'and' => is_string($overrides['and'] ?? null) ? $overrides['and'] : $defaults['and'],
            'form' => is_string($overrides['form'] ?? null) ? $overrides['form'] : ($defaults['form'] ?? 'long'),
            'etAlMin' => is_int($overrides['etAlMin'] ?? null) ? $overrides['etAlMin'] : $defaults['etAlMin'],
            'etAlUseFirst' => is_int($overrides['etAlUseFirst'] ?? null) ? $overrides['etAlUseFirst'] : $defaults['etAlUseFirst'],
            'etAlUseLast' => is_bool($overrides['etAlUseLast'] ?? null) ? $overrides['etAlUseLast'] : (bool) ($defaults['etAlUseLast'] ?? false),
            'etAlSubsequentMin' => is_int($overrides['etAlSubsequentMin'] ?? null) ? $overrides['etAlSubsequentMin'] : ($defaults['etAlSubsequentMin'] ?? null),
            'etAlSubsequentUseFirst' => is_int($overrides['etAlSubsequentUseFirst'] ?? null) ? $overrides['etAlSubsequentUseFirst'] : ($defaults['etAlSubsequentUseFirst'] ?? null),
            'delimiterPrecedesEtAl' => is_string($overrides['delimiterPrecedesEtAl'] ?? null) ? $overrides['delimiterPrecedesEtAl'] : $defaults['delimiterPrecedesEtAl'],
            'delimiterPrecedesLast' => is_string($overrides['delimiterPrecedesLast'] ?? null) ? $overrides['delimiterPrecedesLast'] : ($defaults['delimiterPrecedesLast'] ?? 'contextual'),
            'delimiterPrecedesLastExplicit' => ($overrides['delimiterPrecedesLastExplicit'] ?? false) === true || ($defaults['delimiterPrecedesLastExplicit'] ?? false) === true,
            'etAl' => self::mergeEtAlRenderingOptions(
                is_array($defaults['etAl'] ?? null) ? $defaults['etAl'] : [],
                is_array($overrides['etAl'] ?? null) ? $overrides['etAl'] : []
            ),
            'initialize' => is_bool($overrides['initialize'] ?? null) ? $overrides['initialize'] : (bool) ($defaults['initialize'] ?? true),
            'initializeWith' => is_string($overrides['initializeWith'] ?? null) ? $overrides['initializeWith'] : $defaults['initializeWith'],
            'initializeWithHyphen' => is_bool($overrides['initializeWithHyphen'] ?? null) ? $overrides['initializeWithHyphen'] : ($defaults['initializeWithHyphen'] ?? true),
            'nameAsSortOrder' => is_string($overrides['nameAsSortOrder'] ?? null) ? $overrides['nameAsSortOrder'] : $defaults['nameAsSortOrder'],
            'nameAsSortOrderExplicit' => ($overrides['nameAsSortOrderExplicit'] ?? false) === true || ($defaults['nameAsSortOrderExplicit'] ?? false) === true,
            'sortSeparator' => is_string($overrides['sortSeparator'] ?? null) ? $overrides['sortSeparator'] : ($defaults['sortSeparator'] ?? ', '),
            'demoteNonDroppingParticle' => is_string($overrides['demoteNonDroppingParticle'] ?? null) ? $overrides['demoteNonDroppingParticle'] : ($defaults['demoteNonDroppingParticle'] ?? 'never'),
            'nameParts' => is_array($overrides['nameParts'] ?? null) ? $overrides['nameParts'] : ($defaults['nameParts'] ?? []),
            'institution' => is_array($overrides['institution'] ?? null) ? $overrides['institution'] : ($defaults['institution'] ?? null),
            'label' => is_array($overrides['label'] ?? null) ? $overrides['label'] : ($defaults['label'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function globalNameRenderingOverrides(\DOMElement $style): array
    {
        $overrides = [];
        $demote = trim($style->getAttribute('demote-non-dropping-particle'));
        if ($demote !== '' && !in_array($demote, ['never', 'sort-only', 'display-and-sort'], true)) {
            throw new \InvalidArgumentException('CSL style demote-non-dropping-particle must be never, sort-only, or display-and-sort');
        }
        if ($demote !== '') {
            $overrides['demoteNonDroppingParticle'] = $demote;
        }

        if ($style->hasAttribute('initialize-with-hyphen')) {
            $overrides['initializeWithHyphen'] = self::booleanAttribute($style, 'initialize-with-hyphen', true, 'style');
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $overrides
     * @return array{term:string, prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}
     */
    private static function mergeEtAlRenderingOptions(array $defaults, array $overrides): array
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
     * @return array<string, mixed>
     */
    private static function nameRenderingOverridesFromNames(\DOMElement $names, string $scope): array
    {
        $name = self::directChild($names, 'name');
        $overrides = [];
        if ($names->hasAttribute('delimiter')) {
            $overrides['delimiter'] = $names->getAttribute('delimiter');
        }

        $and = self::optionalNameAttribute($name, $names, 'and');
        if ($and !== null) {
            $overrides['and'] = $and;
        }
        if (!in_array($and, ['text', 'symbol', 'none'], true)) {
            if ($and !== null) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' name and attribute must be text, symbol, or none');
            }
        }

        $nameAsSortOrder = self::optionalNameAttribute($name, $names, 'name-as-sort-order');
        if ($nameAsSortOrder !== null) {
            $overrides['nameAsSortOrder'] = $nameAsSortOrder;
            $overrides['nameAsSortOrderExplicit'] = true;
        }
        if ($nameAsSortOrder !== null && !in_array($nameAsSortOrder, ['first', 'all'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' name-as-sort-order must be first or all');
        }

        $form = self::nameFormAttribute($name, $names, $scope);
        if ($form !== null) {
            $overrides['form'] = $form;
        }

        $sortSeparator = null;
        if ($name instanceof \DOMElement && $name->hasAttribute('sort-separator')) {
            $sortSeparator = $name->getAttribute('sort-separator');
        } elseif ($names->hasAttribute('sort-separator')) {
            $sortSeparator = $names->getAttribute('sort-separator');
        }
        if ($sortSeparator !== null) {
            $overrides['sortSeparator'] = $sortSeparator;
        }

        $etAlMin = self::positiveIntegerNameAttribute($names, $name, 'et-al-min', $scope);
        $etAlUseFirst = self::positiveIntegerNameAttribute($names, $name, 'et-al-use-first', $scope);
        $etAlUseLast = self::optionalBooleanNameAttribute($name, $names, 'et-al-use-last', $scope);
        $etAlSubsequentMin = self::positiveIntegerNameAttribute($names, $name, 'et-al-subsequent-min', $scope);
        $etAlSubsequentUseFirst = self::positiveIntegerNameAttribute($names, $name, 'et-al-subsequent-use-first', $scope);
        if ($etAlMin !== null) {
            $overrides['etAlMin'] = $etAlMin;
        }
        if ($etAlUseFirst !== null) {
            $overrides['etAlUseFirst'] = $etAlUseFirst;
        }
        if ($etAlUseLast !== null) {
            $overrides['etAlUseLast'] = $etAlUseLast;
        }
        if ($etAlSubsequentMin !== null) {
            $overrides['etAlSubsequentMin'] = $etAlSubsequentMin;
        }
        if ($etAlSubsequentUseFirst !== null) {
            $overrides['etAlSubsequentUseFirst'] = $etAlSubsequentUseFirst;
        }

        $delimiterPrecedesEtAl = self::optionalNameAttribute($name, $names, 'delimiter-precedes-et-al');
        if ($delimiterPrecedesEtAl !== null) {
            if (!in_array($delimiterPrecedesEtAl, ['contextual', 'after-inverted-name', 'always', 'never'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' delimiter-precedes-et-al must be contextual, after-inverted-name, always, or never');
            }

            $overrides['delimiterPrecedesEtAl'] = $delimiterPrecedesEtAl;
        }

        $delimiterPrecedesLast = self::optionalNameAttribute($name, $names, 'delimiter-precedes-last');
        if ($delimiterPrecedesLast !== null) {
            if (!in_array($delimiterPrecedesLast, ['contextual', 'after-inverted-name', 'always', 'never'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' delimiter-precedes-last must be contextual, after-inverted-name, always, or never');
            }

            $overrides['delimiterPrecedesLast'] = $delimiterPrecedesLast;
            $overrides['delimiterPrecedesLastExplicit'] = true;
        }

        $etAl = self::etAlRenderingOptions($names, $scope);
        if ($etAl !== []) {
            $overrides['etAl'] = $etAl;
        }

        if ($name instanceof \DOMElement && $name->hasAttribute('initialize-with')) {
            $overrides['initializeWith'] = $name->getAttribute('initialize-with');
        }
        $initialize = self::optionalBooleanNameAttribute($name, $names, 'initialize', $scope);
        if ($initialize !== null) {
            $overrides['initialize'] = $initialize;
        }
        $initializeWithHyphen = self::optionalBooleanNameAttribute($name, $names, 'initialize-with-hyphen', $scope);
        if ($initializeWithHyphen !== null) {
            $overrides['initializeWithHyphen'] = $initializeWithHyphen;
        }
        if ($name instanceof \DOMElement) {
            $nameParts = self::namePartRenderingOptions($name, $scope);
            if ($nameParts !== []) {
                $overrides['nameParts'] = $nameParts;
            }
        }
        $institution = self::institutionRenderingOptions($names, $scope);
        if ($institution !== []) {
            $overrides['institution'] = $institution;
        }
        $label = self::namesLabelRenderingOptions($names, $name, $scope);
        if ($label !== []) {
            $overrides['label'] = $label;
        }

        return $overrides;
    }

    /**
     * @return array{position:string, form:string, plural:string, prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}|array{}
     */
    private static function namesLabelRenderingOptions(\DOMElement $names, ?\DOMElement $name, string $scope): array
    {
        $labels = self::directChildren($names, 'label');
        if ($labels === []) {
            return [];
        }

        if (count($labels) > 1) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' names element may contain at most one label element');
        }

        $label = $labels[0];
        if ($label->hasAttribute('variable') && trim($label->getAttribute('variable')) !== '') {
            throw new \InvalidArgumentException('CSL ' . $scope . ' names label must not declare a variable');
        }

        $form = strtolower(trim($label->getAttribute('form')));
        if ($form === '') {
            $form = 'long';
        }
        if (!in_array($form, ['long', 'short', 'verb', 'verb-short', 'symbol'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' names label form must be long, short, verb, verb-short, or symbol');
        }

        $plural = strtolower(trim($label->getAttribute('plural')));
        if ($plural === '') {
            $plural = 'contextual';
        }
        if (!in_array($plural, ['contextual', 'always', 'never'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' names label plural must be contextual, always, or never');
        }

        return [
            'position' => $name instanceof \DOMElement && self::elementPrecedes($label, $name) ? 'before' : 'after',
            'form' => $form,
            'plural' => $plural,
            'prefix' => self::optionalAttribute($label, 'prefix'),
            'suffix' => self::optionalAttribute($label, 'suffix'),
            'textCase' => self::textCaseAttribute($label, $scope),
            'stripPeriods' => self::booleanRenderingAttribute($label, 'strip-periods', false, $scope),
            'quotes' => self::booleanRenderingAttribute($label, 'quotes', false, $scope),
        ];
    }

    /**
     * @return array{institutionParts:string, delimiter:string, parts:array<string, array{prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}>}|array{}
     */
    private static function institutionRenderingOptions(\DOMElement $names, string $scope): array
    {
        $institutions = self::directChildren($names, 'institution');
        if ($institutions === []) {
            return [];
        }

        if (count($institutions) > 1) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' names element may contain at most one institution element');
        }

        $institution = $institutions[0];
        $institutionParts = strtolower(trim($institution->getAttribute('institution-parts')));
        if ($institutionParts === '') {
            $institutionParts = 'long';
        }
        $supportedInstitutionParts = ['long', 'short', 'long-short', 'short-long'];
        if (!in_array($institutionParts, $supportedInstitutionParts, true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' institution-parts must be long, short, long-short, or short-long');
        }

        $parts = [];
        foreach (self::directChildren($institution, 'institution-part') as $part) {
            $partName = strtolower(trim($part->getAttribute('name')));
            if (!in_array($partName, ['long', 'short'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' institution-part name must be long or short');
            }

            if (array_key_exists($partName, $parts)) {
                throw new \InvalidArgumentException('Duplicate CSL ' . $scope . ' institution-part formatter: ' . $partName);
            }

            $parts[$partName] = [
                'prefix' => self::optionalAttribute($part, 'prefix'),
                'suffix' => self::optionalAttribute($part, 'suffix'),
                'textCase' => self::textCaseAttribute($part, $scope),
                'stripPeriods' => self::booleanRenderingAttribute($part, 'strip-periods', false, $scope),
                'quotes' => self::booleanRenderingAttribute($part, 'quotes', false, $scope),
            ];
        }

        return [
            'institutionParts' => $institutionParts,
            'delimiter' => self::optionalAttribute($institution, 'delimiter'),
            'parts' => $parts,
        ];
    }

    /**
     * @return array{term:string, prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}|array{}
     */
    private static function etAlRenderingOptions(\DOMElement $names, string $scope): array
    {
        $etAlElements = self::directChildren($names, 'et-al');
        if ($etAlElements === []) {
            return [];
        }

        if (count($etAlElements) > 1) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' names element may contain at most one et-al element');
        }

        $etAl = $etAlElements[0];
        $term = trim($etAl->getAttribute('term'));
        if ($term === '') {
            $term = 'et-al';
        }
        if (!in_array($term, ['et-al', 'and others'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' et-al term must be et-al or and others');
        }

        return [
            'term' => $term,
            'prefix' => self::optionalAttribute($etAl, 'prefix'),
            'suffix' => self::optionalAttribute($etAl, 'suffix'),
            'textCase' => self::textCaseAttribute($etAl, $scope),
            'stripPeriods' => self::booleanRenderingAttribute($etAl, 'strip-periods', false, $scope),
            'quotes' => self::booleanRenderingAttribute($etAl, 'quotes', false, $scope),
        ];
    }

    /**
     * @return array<string, array{prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}>
     */
    private static function namePartRenderingOptions(\DOMElement $name, string $scope): array
    {
        $parts = [];
        foreach (self::directChildren($name, 'name-part') as $namePart) {
            $partName = strtolower(trim($namePart->getAttribute('name')));
            if (!in_array($partName, ['family', 'given'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' name-part name must be family or given');
            }

            if (array_key_exists($partName, $parts)) {
                throw new \InvalidArgumentException('Duplicate CSL ' . $scope . ' name-part formatter: ' . $partName);
            }

            $parts[$partName] = [
                'prefix' => self::optionalAttribute($namePart, 'prefix'),
                'suffix' => self::optionalAttribute($namePart, 'suffix'),
                'textCase' => self::textCaseAttribute($namePart, $scope),
                'stripPeriods' => self::booleanRenderingAttribute($namePart, 'strip-periods', false, $scope),
                'quotes' => self::booleanRenderingAttribute($namePart, 'quotes', false, $scope),
            ];
        }

        return $parts;
    }

    private static function optionalNameAttribute(?\DOMElement $name, \DOMElement $names, string $attribute): ?string
    {
        if ($name instanceof \DOMElement && $name->hasAttribute($attribute)) {
            return trim($name->getAttribute($attribute));
        }

        if ($names->hasAttribute($attribute)) {
            return trim($names->getAttribute($attribute));
        }

        return null;
    }

    private static function nameFormAttribute(?\DOMElement $name, \DOMElement $names, string $scope): ?string
    {
        if ($name instanceof \DOMElement && $name->hasAttribute('form')) {
            $form = strtolower(trim($name->getAttribute('form')));
        } else {
            return null;
        }

        if (!in_array($form, ['long', 'short', 'count'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' name form must be long, short, or count');
        }

        return $form;
    }

    private static function optionalBooleanNameAttribute(?\DOMElement $name, \DOMElement $names, string $attribute, string $scope): ?bool
    {
        $value = self::optionalNameAttribute($name, $names, $attribute);
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        throw new \InvalidArgumentException('CSL ' . $scope . ' name attribute ' . $attribute . ' must be true or false');
    }

    private static function positiveIntegerNameAttribute(\DOMElement $names, ?\DOMElement $name, string $attribute, string $scope): ?int
    {
        $source = null;
        if ($names->hasAttribute($attribute)) {
            $source = $names;
        } elseif ($name instanceof \DOMElement && $name->hasAttribute($attribute)) {
            $source = $name;
        }

        if (!$source instanceof \DOMElement) {
            return null;
        }

        $value = trim($source->getAttribute($attribute));
        if (preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' name attribute ' . $attribute . ' must be a positive integer');
        }

        return (int) $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function renderingElements(\DOMElement $container, string $scope): array
    {
        $elements = [];
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $element = self::renderingElement($child, $scope);
            if ($element !== null) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function renderingElement(\DOMElement $element, string $scope): ?array
    {
        return match ($element->localName) {
            'group' => self::groupRenderingElement($element, $scope),
            'text' => self::textRenderingElement($element, $scope),
            'date' => self::dateRenderingElement($element, $scope),
            'number' => self::numberRenderingElement($element, $scope),
            'names' => self::namesRenderingElement($element, $scope),
            'label' => self::labelRenderingElement($element, $scope),
            'choose' => self::chooseRenderingElement($element, $scope),
            default => null,
        };
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, delimiter:string, children:list<array<string, mixed>>}
     */
    private static function groupRenderingElement(\DOMElement $group, string $scope): array
    {
        $element = [
            'type' => 'group',
            'prefix' => self::optionalAttribute($group, 'prefix'),
            'suffix' => self::optionalAttribute($group, 'suffix'),
            'delimiter' => self::optionalAttribute($group, 'delimiter'),
            'display' => self::displayAttribute($group, $scope),
            'children' => self::renderingElements($group, $scope),
        ];

        return self::withRenderingFormatting($element, $group, $scope);
    }

    /**
     * @return array<string, mixed>
     */
    private static function textRenderingElement(\DOMElement $text, string $scope): array
    {
        $variable = trim($text->getAttribute('variable'));
        $term = trim($text->getAttribute('term'));
        $hasValue = $text->hasAttribute('value');
        $value = $hasValue ? $text->getAttribute('value') : '';
        $macro = trim($text->getAttribute('macro'));
        $declared = array_filter([
            $variable !== '',
            $term !== '',
            $hasValue,
            $macro !== '',
        ]);
        if (count($declared) !== 1) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' text element must declare exactly one variable, term, value, or macro');
        }

        $form = $term !== ''
            ? self::termFormAttribute($text, $scope . ' text term')
            : (self::optionalAttribute($text, 'form') !== '' ? self::optionalAttribute($text, 'form') : 'long');

        $element = [
            'type' => 'text',
            'prefix' => self::optionalAttribute($text, 'prefix'),
            'suffix' => self::optionalAttribute($text, 'suffix'),
            'form' => $form,
            'plural' => self::booleanRenderingAttribute($text, 'plural', false, $scope),
            'quotes' => self::booleanRenderingAttribute($text, 'quotes', false, $scope),
            'stripPeriods' => self::booleanRenderingAttribute($text, 'strip-periods', false, $scope),
            'textCase' => self::textCaseAttribute($text, $scope),
            'display' => self::displayAttribute($text, $scope),
        ];
        if ($variable !== '') {
            $element['variable'] = $variable;
        } elseif ($term !== '') {
            $element['term'] = $term;
        } elseif ($hasValue) {
            $element['value'] = $value;
        } else {
            $element['macro'] = $macro;
        }

        return self::withRenderingFormatting($element, $text, $scope);
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, variable:string, form:string, datePartsSelection:string, delimiter:string, dateParts:list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}>}
     */
    private static function dateRenderingElement(\DOMElement $date, string $scope): array
    {
        $variable = trim($date->getAttribute('variable'));
        if ($variable === '') {
            throw new \InvalidArgumentException('CSL ' . $scope . ' date element must declare a variable');
        }

        $dateForm = strtolower(trim($date->getAttribute('form')));
        if ($dateForm !== '' && !in_array($dateForm, ['text', 'numeric'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' date form must be text or numeric');
        }

        $datePartsSelection = strtolower(trim($date->getAttribute('date-parts')));
        if ($datePartsSelection !== '' && !in_array($datePartsSelection, ['year', 'year-month', 'year-month-day'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' date-parts must be year, year-month, or year-month-day');
        }

        $dateParts = [];
        foreach (self::directChildren($date, 'date-part') as $datePart) {
            $name = strtolower(trim($datePart->getAttribute('name')));
            if (!in_array($name, ['year', 'month', 'day'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' date-part name must be year, month, or day');
            }

            $partForm = strtolower(trim($datePart->getAttribute('form')));
            if ($partForm !== '' && !self::datePartFormIsSupported($name, $partForm)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' date-part ' . $name . ' form is not supported: ' . $partForm);
            }

            $dateParts[] = [
                'name' => $name,
                'prefix' => self::optionalAttribute($datePart, 'prefix'),
                'suffix' => self::optionalAttribute($datePart, 'suffix'),
                'form' => $partForm,
                'rangeDelimiter' => self::optionalAttribute($datePart, 'range-delimiter'),
                'stripPeriods' => self::booleanRenderingAttribute($datePart, 'strip-periods', false, $scope),
                'textCase' => self::textCaseAttribute($datePart, $scope),
            ];
        }

        $element = [
            'type' => 'date',
            'prefix' => self::optionalAttribute($date, 'prefix'),
            'suffix' => self::optionalAttribute($date, 'suffix'),
            'variable' => $variable,
            'form' => $dateForm,
            'datePartsSelection' => $datePartsSelection,
            'delimiter' => self::optionalAttribute($date, 'delimiter'),
            'dateParts' => $dateParts,
            'textCase' => self::textCaseAttribute($date, $scope),
            'display' => self::displayAttribute($date, $scope),
        ];

        return self::withRenderingFormatting($element, $date, $scope);
    }

    private static function datePartFormIsSupported(string $name, string $form): bool
    {
        return match ($name) {
            'day' => in_array($form, ['numeric', 'numeric-leading-zeros', 'ordinal'], true),
            'month' => in_array($form, ['long', 'short', 'numeric', 'numeric-leading-zeros'], true),
            'year' => in_array($form, ['long', 'short'], true),
            default => false,
        };
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, variable:string, form:string}
     */
    private static function numberRenderingElement(\DOMElement $number, string $scope): array
    {
        $variable = strtolower(trim($number->getAttribute('variable')));
        if ($variable === '') {
            throw new \InvalidArgumentException('CSL ' . $scope . ' number element must declare a variable');
        }

        if (!in_array($variable, self::supportedNumberVariables(), true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' number variable is not supported: ' . $variable);
        }

        $form = strtolower(trim($number->getAttribute('form')));
        if ($form === '') {
            $form = 'numeric';
        }
        if (!in_array($form, ['numeric', 'ordinal', 'long-ordinal', 'roman'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' number form must be numeric, ordinal, long-ordinal, or roman');
        }

        $element = [
            'type' => 'number',
            'prefix' => self::optionalAttribute($number, 'prefix'),
            'suffix' => self::optionalAttribute($number, 'suffix'),
            'variable' => $variable,
            'form' => $form,
            'textCase' => self::textCaseAttribute($number, $scope),
            'display' => self::displayAttribute($number, $scope),
        ];

        return self::withRenderingFormatting($element, $number, $scope);
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, variable:string}
     */
    private static function namesRenderingElement(\DOMElement $names, string $scope): array
    {
        $variable = trim($names->getAttribute('variable'));
        if ($variable === '') {
            $variable = 'author editor';
        }

        $element = [
            'type' => 'names',
            'prefix' => self::optionalAttribute($names, 'prefix'),
            'suffix' => self::optionalAttribute($names, 'suffix'),
            'variable' => $variable,
            'display' => self::displayAttribute($names, $scope),
        ];
        $overrides = self::nameRenderingOverridesFromNames($names, $scope);
        if ($overrides !== []) {
            $element['nameRendering'] = $overrides;
        }
        $substitute = self::directChild($names, 'substitute');
        if ($substitute instanceof \DOMElement) {
            $element['substitute'] = self::renderingElements($substitute, $scope);
        }

        return self::withRenderingFormatting($element, $names, $scope);
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, variable:string, form:string, plural:string}
     */
    private static function labelRenderingElement(\DOMElement $label, string $scope): array
    {
        $variable = strtolower(trim($label->getAttribute('variable')));
        if ($variable === '') {
            throw new \InvalidArgumentException('CSL ' . $scope . ' label element must declare a variable');
        }

        if (!in_array($variable, self::supportedLabelVariables(), true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' label variable is not supported: ' . $variable);
        }

        $form = strtolower(trim($label->getAttribute('form')));
        if ($form === '') {
            $form = 'long';
        }
        if (!in_array($form, ['long', 'short', 'symbol'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' label form must be long, short, or symbol');
        }

        $plural = strtolower(trim($label->getAttribute('plural')));
        if ($plural === '') {
            $plural = 'contextual';
        }
        if (!in_array($plural, ['contextual', 'always', 'never'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' label plural must be contextual, always, or never');
        }

        $element = [
            'type' => 'label',
            'prefix' => self::optionalAttribute($label, 'prefix'),
            'suffix' => self::optionalAttribute($label, 'suffix'),
            'variable' => $variable,
            'form' => $form,
            'plural' => $plural,
            'stripPeriods' => self::booleanRenderingAttribute($label, 'strip-periods', false, $scope),
            'textCase' => self::textCaseAttribute($label, $scope),
            'display' => self::displayAttribute($label, $scope),
        ];

        return self::withRenderingFormatting($element, $label, $scope);
    }

    /**
     * @return list<string>
     */
    private static function supportedLabelVariables(): array
    {
        return self::supportedNumberVariables();
    }

    /**
     * @return list<string>
     */
    private static function supportedNumberVariables(): array
    {
        return [
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
            'numberofpages',
            'pagetotal',
            'page-total',
            'num-pages',
            'numpages',
            'total-pages',
            'totalpages',
            'number-of-volumes',
            'numberofvolumes',
            'volumes',
            'volume-count',
            'volumecount',
            'num-volumes',
            'numvolumes',
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
        ];
    }

    /**
     * @return array{type:string, branches:list<array{match:string, variables:list<string>, types:list<string>, locators:list<string>, positions:list<string>, disambiguate:bool, isCreator:list<string>, isNumeric:list<string>, isDate:list<string>, isUncertainDate:list<string>, isCircaDate:list<string>, children:list<array<string, mixed>>}>, else:list<array<string, mixed>>}
     */
    private static function chooseRenderingElement(\DOMElement $choose, string $scope): array
    {
        $branches = [];
        $else = [];
        $seenIf = false;
        $seenElse = false;

        foreach ($choose->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = $child->localName;
            if ($name === 'if') {
                if ($seenIf || $seenElse) {
                    throw new \InvalidArgumentException('CSL ' . $scope . ' choose element must start with a single if branch');
                }

                $seenIf = true;
                $branches[] = self::conditionalRenderingBranch($child, $scope);
                continue;
            }

            if ($name === 'else-if') {
                if (!$seenIf || $seenElse) {
                    throw new \InvalidArgumentException('CSL ' . $scope . ' choose else-if branch must follow if and precede else');
                }

                $branches[] = self::conditionalRenderingBranch($child, $scope);
                continue;
            }

            if ($name === 'else') {
                if (!$seenIf || $seenElse) {
                    throw new \InvalidArgumentException('CSL ' . $scope . ' choose else branch must follow if and appear once');
                }

                $seenElse = true;
                $else = self::renderingElements($child, $scope);
                if ($else === []) {
                    throw new \InvalidArgumentException('CSL ' . $scope . ' choose else branch must contain at least one rendering element');
                }
                continue;
            }

            throw new \InvalidArgumentException('CSL ' . $scope . ' choose element may only contain if, else-if, or else branches');
        }

        if ($branches === []) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' choose element must contain an if branch');
        }

        return [
            'type' => 'choose',
            'branches' => $branches,
            'else' => $else,
        ];
    }

    /**
     * @return array{match:string, variables:list<string>, types:list<string>, locators:list<string>, positions:list<string>, disambiguate:bool, isCreator:list<string>, isNumeric:list<string>, isDate:list<string>, isUncertainDate:list<string>, isCircaDate:list<string>, children:list<array<string, mixed>>}
     */
    private static function conditionalRenderingBranch(\DOMElement $branch, string $scope): array
    {
        $match = trim($branch->getAttribute('match'));
        if ($match === '') {
            $match = 'all';
        }
        if (!in_array($match, ['all', 'any', 'none'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch match must be all, any, or none');
        }

        $variables = self::spaceSeparatedAttribute($branch, 'variable');
        $types = self::spaceSeparatedAttribute($branch, 'type');
        $locators = array_map(
            static fn (string $locator): string => self::normalizeLocatorCondition($locator),
            self::spaceSeparatedAttribute($branch, 'locator')
        );
        $positions = self::spaceSeparatedAttribute($branch, 'position');
        $disambiguate = false;
        if ($branch->hasAttribute('disambiguate')) {
            $disambiguateValue = strtolower(trim($branch->getAttribute('disambiguate')));
            if ($disambiguateValue !== 'true') {
                throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch disambiguate must be true');
            }

            $disambiguate = true;
        }
        $isCreator = array_map(
            static fn (string $variable): string => self::normalizeCreatorConditionVariable($variable),
            self::spaceSeparatedAttribute($branch, 'is-creator')
        );
        $isNumeric = self::spaceSeparatedAttribute($branch, 'is-numeric');
        $isDate = self::spaceSeparatedAttribute($branch, 'is-date');
        $isUncertainDate = self::spaceSeparatedAttribute($branch, 'is-uncertain-date');
        $isCircaDate = self::spaceSeparatedAttribute($branch, 'is-circa-date');
        foreach ($locators as $locator) {
            if (!in_array($locator, self::supportedLocatorConditions(), true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch locator is not supported: ' . $locator);
            }
        }
        foreach ($positions as $position) {
            if (!in_array($position, ['first', 'subsequent', 'ibid', 'ibid-with-locator', 'near-note'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch position is not supported: ' . $position);
            }
        }
        foreach ($isCreator as $variable) {
            if (!in_array($variable, self::supportedCreatorConditionVariables(), true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch is-creator variable is not supported: ' . $variable);
            }
        }

        if ($variables === [] && $types === [] && $locators === [] && $positions === [] && !$disambiguate && $isCreator === [] && $isNumeric === [] && $isDate === [] && $isUncertainDate === [] && $isCircaDate === []) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch must declare variable, type, locator, position, disambiguate, is-creator, is-numeric, is-date, is-uncertain-date, or is-circa-date');
        }

        return [
            'match' => $match,
            'variables' => $variables,
            'types' => $types,
            'locators' => $locators,
            'positions' => $positions,
            'disambiguate' => $disambiguate,
            'isCreator' => $isCreator,
            'isNumeric' => $isNumeric,
            'isDate' => $isDate,
            'isUncertainDate' => $isUncertainDate,
            'isCircaDate' => $isCircaDate,
            'children' => self::renderingElements($branch, $scope),
        ];
    }

    private static function normalizeLocatorCondition(string $locator): string
    {
        return str_replace(['_', ' '], '-', strtolower(trim($locator)));
    }

    private static function normalizeCreatorConditionVariable(string $variable): string
    {
        return str_replace(['_', ' '], '-', strtolower(trim($variable)));
    }

    /**
     * @return list<string>
     */
    private static function supportedCreatorConditionVariables(): array
    {
        return [
            'short-author',
            'short-editor',
            'author',
            'editor',
            'holder',
            'authority',
            'translator',
            'chair',
            'container-author',
            'collection-editor',
            'composer',
            'contributor',
            'editor-translator',
            'executive-producer',
            'event-organizer',
            'guest',
            'host',
            'narrator',
            'organizer',
            'original-author',
            'performer',
            'producer',
            'recipient',
            'script-writer',
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
            'introduction',
            'foreword',
            'afterword',
            'namea',
            'nameb',
            'namec',
        ];
    }

    /**
     * @return list<string>
     */
    private static function supportedLocatorConditions(): array
    {
        return [
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
        ];
    }

    /**
     * @return list<string>
     */
    private static function spaceSeparatedAttribute(\DOMElement $element, string $name): array
    {
        $value = trim($element->getAttribute($name));
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/', $value) ?: [],
            static fn (string $part): bool => $part !== ''
        ));
    }

    private static function optionalAttribute(\DOMElement $element, string $name): string
    {
        return $element->hasAttribute($name) ? $element->getAttribute($name) : '';
    }

    private static function termFormAttribute(\DOMElement $element, string $label): string
    {
        $form = strtolower(trim($element->getAttribute('form')));
        if ($form === '') {
            return 'long';
        }

        if (!in_array($form, self::TERM_FORMS, true)) {
            throw new \InvalidArgumentException('CSL ' . $label . ' form must be long, short, verb, verb-short, or symbol');
        }

        return $form;
    }

    private static function booleanRenderingAttribute(\DOMElement $element, string $name, bool $default, string $scope): bool
    {
        if (!$element->hasAttribute($name)) {
            return $default;
        }

        $value = strtolower(trim($element->getAttribute($name)));
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        throw new \InvalidArgumentException('CSL ' . $scope . ' rendering attribute ' . $name . ' must be true or false');
    }

    private static function textCaseAttribute(\DOMElement $element, string $scope): string
    {
        $value = strtolower(trim($element->getAttribute('text-case')));
        if ($value === '') {
            return '';
        }

        if (!in_array($value, ['lowercase', 'uppercase', 'capitalize-first', 'capitalize-all', 'sentence', 'title'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' text-case must be lowercase, uppercase, capitalize-first, capitalize-all, sentence, or title');
        }

        return $value;
    }

    private static function displayAttribute(\DOMElement $element, string $scope): string
    {
        $value = strtolower(trim($element->getAttribute('display')));
        if ($value === '') {
            return '';
        }

        if (!in_array($value, ['block', 'left-margin', 'right-inline', 'indent'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' display must be block, left-margin, right-inline, or indent');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>
     */
    private static function withRenderingFormatting(array $element, \DOMElement $source, string $scope): array
    {
        $formatting = self::renderingFormattingAttributes($source, $scope);
        if ($formatting !== []) {
            $element['formatting'] = $formatting;
        }

        return $element;
    }

    /**
     * @return array<string, string>
     */
    private static function renderingFormattingAttributes(\DOMElement $element, string $scope): array
    {
        $attributes = [];
        $supported = [
            'font-style' => ['fontStyle', ['normal', 'italic', 'oblique']],
            'font-variant' => ['fontVariant', ['normal', 'small-caps']],
            'font-weight' => ['fontWeight', ['normal', 'bold', 'light']],
            'text-decoration' => ['textDecoration', ['none', 'underline']],
            'vertical-align' => ['verticalAlign', ['baseline', 'sup', 'sub']],
        ];

        foreach ($supported as $attribute => [$key, $values]) {
            $value = strtolower(trim($element->getAttribute($attribute)));
            if ($value === '') {
                continue;
            }

            if (!in_array($value, $values, true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' ' . $attribute . ' must be ' . self::commaSeparatedList($values));
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * @param list<string> $values
     */
    private static function commaSeparatedList(array $values): string
    {
        if (count($values) < 2) {
            return implode('', $values);
        }

        $last = array_pop($values);

        return implode(', ', $values) . ', or ' . $last;
    }

    /**
     * @return list<array{sort:string, variable?:string, macro?:string, namesMin?:int, namesUseFirst?:int, namesUseLast?:bool}>
     */
    private static function sortKeys(\DOMElement $container, string $label): array
    {
        $sort = self::directChild($container, 'sort');
        if (!$sort instanceof \DOMElement) {
            return [];
        }

        $keys = [];
        foreach (self::directChildren($sort, 'key') as $keyElement) {
            $variable = trim($keyElement->getAttribute('variable'));
            $macro = trim($keyElement->getAttribute('macro'));
            if (($variable === '') === ($macro === '')) {
                throw new \InvalidArgumentException('CSL ' . $label . ' sort key must declare exactly one variable or macro');
            }

            $order = trim($keyElement->getAttribute('sort'));
            if ($order === '') {
                $order = 'ascending';
            }
            if ($order !== 'ascending' && $order !== 'descending') {
                throw new \InvalidArgumentException('CSL ' . $label . ' sort key sort must be ascending or descending');
            }

            $key = ['sort' => $order];
            if ($variable !== '') {
                $key['variable'] = $variable;
            } else {
                $key['macro'] = $macro;
            }
            $namesMin = self::positiveIntegerSortKeyAttribute($keyElement, 'names-min', $label);
            if ($namesMin !== null) {
                $key['namesMin'] = $namesMin;
            }
            $namesUseFirst = self::positiveIntegerSortKeyAttribute($keyElement, 'names-use-first', $label);
            if ($namesUseFirst !== null) {
                $key['namesUseFirst'] = $namesUseFirst;
            }
            if ($keyElement->hasAttribute('names-use-last')) {
                $key['namesUseLast'] = self::booleanAttribute($keyElement, 'names-use-last', false, $label . ' sort key');
            }
            $keys[] = $key;
        }

        if ($keys === []) {
            throw new \InvalidArgumentException('CSL ' . $label . ' sort element must contain at least one key');
        }

        return $keys;
    }

    private static function positiveIntegerSortKeyAttribute(\DOMElement $key, string $attribute, string $label): ?int
    {
        if (!$key->hasAttribute($attribute)) {
            return null;
        }

        $value = trim($key->getAttribute($attribute));
        if (preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1) {
            throw new \InvalidArgumentException('CSL ' . $label . ' sort key attribute ' . $attribute . ' must be a positive integer');
        }

        return (int) $value;
    }

    /**
     * @param array<string, array{single:string, multiple:string}> $terms
     * @return array<string, array{single:string, multiple:string}>
     */
    private static function parseLocaleXml(string $localeXml): \DOMElement
    {
        $dom = self::loadXml($localeXml, 'CSL locale XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'locale') {
            throw new \InvalidArgumentException('CSL locale XML root element must be locale');
        }

        if ($root->namespaceURI !== self::CSL_NS) {
            throw new \InvalidArgumentException('CSL locale XML root element must use the CSL namespace');
        }

        if ($root->getAttribute('version') !== '1.0') {
            throw new \InvalidArgumentException('CSL locale XML must declare version 1.0');
        }

        return $root;
    }

    /**
     * @param array<string, array{single:string, multiple:string, match?:string, gender?:string}> $terms
     * @return array<string, array{single:string, multiple:string, match?:string, gender?:string}>
     */
    private static function applyLocaleElementTerms(\DOMElement $locale, array $terms): array
    {
        $termsElement = self::directChild($locale, 'terms');
        if (!$termsElement instanceof \DOMElement) {
            return $terms;
        }

        $termElements = self::directChildren($termsElement, 'term');
        foreach ($termElements as $termElement) {
            if (self::isOrdinalSuffixTerm(trim($termElement->getAttribute('name')))) {
                $terms = self::withoutDefaultOrdinalSuffixTerms($terms);
                break;
            }
        }

        foreach ($termElements as $termElement) {
            $name = trim($termElement->getAttribute('name'));
            if ($name === '') {
                throw new \InvalidArgumentException('CSL locale term is missing a name');
            }

            $form = self::termFormAttribute($termElement, 'locale term');
            $gender = self::termGenderAttribute($termElement, 'gender', $name);
            $genderForm = self::termGenderAttribute($termElement, 'gender-form', $name);
            if ($genderForm !== '' && !self::isGenderFormOrdinalTerm($name)) {
                throw new \InvalidArgumentException('CSL locale term gender-form is only supported on ordinal terms');
            }

            $single = self::directChild($termElement, 'single');
            $multiple = self::directChild($termElement, 'multiple');
            if ($single instanceof \DOMElement || $multiple instanceof \DOMElement) {
                $singleText = $single instanceof \DOMElement ? self::elementText($single) : '';
                $multipleText = $multiple instanceof \DOMElement ? self::elementText($multiple) : '';
                $term = [
                    'single' => $singleText !== '' ? $singleText : $multipleText,
                    'multiple' => $multipleText !== '' ? $multipleText : $singleText,
                ];
                if ($gender !== '') {
                    $term['gender'] = $gender;
                }
                $terms[self::termKey($name, $form, $genderForm)] = self::withOrdinalTermMatch($termElement, $name, $term);
                continue;
            }

            $text = self::elementText($termElement);
            $term = ['single' => $text, 'multiple' => $text];
            if ($gender !== '') {
                $term['gender'] = $gender;
            }
            $terms[self::termKey($name, $form, $genderForm)] = self::withOrdinalTermMatch($termElement, $name, $term);
        }

        return $terms;
    }

    /**
     * @param array{single:string, multiple:string, gender?:string} $term
     * @return array{single:string, multiple:string, match?:string, gender?:string}
     */
    private static function withOrdinalTermMatch(\DOMElement $termElement, string $name, array $term): array
    {
        if (!self::isOrdinalSuffixTerm($name) || !$termElement->hasAttribute('match')) {
            return $term;
        }

        $match = trim($termElement->getAttribute('match'));
        if ($match === '') {
            return $term;
        }

        if (!in_array($match, ['last-digit', 'last-two-digits', 'whole-number'], true)) {
            throw new \InvalidArgumentException('CSL locale ordinal term match must be last-digit, last-two-digits, or whole-number');
        }

        $term['match'] = $match;

        return $term;
    }

    private static function termGenderAttribute(\DOMElement $termElement, string $attribute, string $termName): string
    {
        if (!$termElement->hasAttribute($attribute)) {
            return '';
        }

        $value = strtolower(trim($termElement->getAttribute($attribute)));
        if ($value === '') {
            return '';
        }

        if (!in_array($value, ['feminine', 'masculine'], true)) {
            throw new \InvalidArgumentException('CSL locale term ' . $attribute . ' must be feminine or masculine');
        }

        if ($attribute === 'gender' && self::isGenderFormOrdinalTerm($termName)) {
            throw new \InvalidArgumentException('CSL locale ordinal terms must use gender-form instead of gender');
        }

        return $value;
    }

    /**
     * @param array{punctuationInQuote:bool, limitDayOrdinalsToDay1:bool} $options
     * @return array{punctuationInQuote:bool, limitDayOrdinalsToDay1:bool}
     */
    private static function applyLocaleElementOptions(\DOMElement $locale, array $options): array
    {
        $styleOptions = self::directChild($locale, 'style-options');
        if (!$styleOptions instanceof \DOMElement) {
            return $options;
        }

        if ($styleOptions->hasAttribute('punctuation-in-quote')) {
            $options['punctuationInQuote'] = self::styleOptionBooleanAttribute($styleOptions, 'punctuation-in-quote');
        }
        if ($styleOptions->hasAttribute('limit-day-ordinals-to-day-1')) {
            $options['limitDayOrdinalsToDay1'] = self::styleOptionBooleanAttribute($styleOptions, 'limit-day-ordinals-to-day-1');
        }

        return $options;
    }

    private static function styleOptionBooleanAttribute(\DOMElement $styleOptions, string $name): bool
    {
        $value = strtolower(trim($styleOptions->getAttribute($name)));
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        throw new \InvalidArgumentException('CSL locale style-options attribute ' . $name . ' must be true or false');
    }

    private static function isOrdinalSuffixTerm(string $name): bool
    {
        return preg_match('/^ordinal(?:-\d{2})?$/', $name) === 1;
    }

    private static function isGenderFormOrdinalTerm(string $name): bool
    {
        return preg_match('/^(?:ordinal(?:-\d{2})?|long-ordinal-\d{2})$/', $name) === 1;
    }

    /**
     * @param array<string, array{single:string, multiple:string, match?:string, gender?:string}> $terms
     * @return array<string, array{single:string, multiple:string, match?:string, gender?:string}>
     */
    private static function withoutDefaultOrdinalSuffixTerms(array $terms): array
    {
        foreach (array_keys($terms) as $key) {
            if (preg_match('/^ordinal(?:-\d{2})?\|/', $key) === 1) {
                unset($terms[$key]);
            }
        }

        return $terms;
    }

    private function ordinalSuffixCandidate(int $absoluteNumber, int $candidate, string $genderForm = ''): ?string
    {
        foreach (self::termFallbackKeys('ordinal-' . sprintf('%02d', $candidate), 'long', $genderForm) as $key) {
            $term = $this->terms[$key] ?? null;
            if ($term === null) {
                continue;
            }

            $match = $term['match'] ?? self::defaultOrdinalTermMatch($candidate);
            if (self::ordinalTermMatches($absoluteNumber, $candidate, $match)) {
                return $term['single'];
            }
        }

        return null;
    }

    private static function defaultOrdinalTermMatch(int $candidate): string
    {
        return $candidate >= 10 ? 'last-two-digits' : 'last-digit';
    }

    private static function ordinalTermMatches(int $absoluteNumber, int $candidate, string $match): bool
    {
        return match ($match) {
            'whole-number' => $absoluteNumber === $candidate,
            'last-two-digits' => $absoluteNumber % 100 === $candidate,
            default => $absoluteNumber % 10 === $candidate % 10,
        };
    }

    /**
     * @param list<\DOMElement> $locales
     * @return list<\DOMElement>
     */
    private static function matchingStyleLocales(array $locales, string $defaultLocale): array
    {
        if ($defaultLocale === '') {
            return $locales;
        }

        $unqualified = [];
        $languageFallbacks = [];
        $exact = [];
        $defaultLocale = strtolower($defaultLocale);
        $defaultLanguage = strtok($defaultLocale, '-');
        foreach ($locales as $locale) {
            $lang = self::localeLanguage($locale);
            if ($lang === '') {
                $unqualified[] = $locale;
                continue;
            }

            if ($lang === $defaultLocale) {
                $exact[] = $locale;
                continue;
            }

            if (strtok($lang, '-') === $defaultLanguage) {
                $languageFallbacks[] = $locale;
            }
        }

        return [...$unqualified, ...$languageFallbacks, ...$exact];
    }

    private static function localeLanguage(\DOMElement $locale): string
    {
        $lang = trim($locale->getAttributeNS(self::XML_NS, 'lang'));
        if ($lang === '') {
            $lang = trim($locale->getAttribute('xml:lang'));
        }

        return strtolower($lang);
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $detail = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
            throw new \InvalidArgumentException('Invalid ' . $label . ': ' . $detail);
        }

        return $dom;
    }

    private static function directChild(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private static function elementPrecedes(\DOMElement $left, \DOMElement $right): bool
    {
        if ($left->parentNode !== $right->parentNode || !($left->parentNode instanceof \DOMNode)) {
            return false;
        }

        foreach ($left->parentNode->childNodes as $child) {
            if ($child === $left) {
                return true;
            }
            if ($child === $right) {
                return false;
            }
        }

        return false;
    }

    private static function firstAuthorEditorNamesElement(\DOMElement $element): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'names' && self::namesElementIncludesAuthorEditor($child)) {
                return $child;
            }

            $match = self::firstAuthorEditorNamesElement($child);
            if ($match instanceof \DOMElement) {
                return $match;
            }
        }

        return null;
    }

    private static function namesElementIncludesAuthorEditor(\DOMElement $names): bool
    {
        $variable = strtolower(trim($names->getAttribute('variable')));
        if ($variable === '') {
            return true;
        }

        $variables = preg_split('/\s+/', $variable) ?: [];

        return in_array('author', $variables, true) || in_array('editor', $variables, true);
    }

    /**
     * @return list<\DOMElement>
     */
    private static function directChildren(\DOMElement $element, string $localName): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private static function childText(\DOMElement $element, string $localName): string
    {
        $child = self::directChild($element, $localName);

        return $child instanceof \DOMElement ? self::elementText($child) : '';
    }

    private static function elementText(\DOMElement $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
    }

    private static function termKey(string $name, string $form, string $genderForm = ''): string
    {
        $key = strtolower(trim($name)) . '|' . strtolower(trim($form));
        $genderForm = strtolower(trim($genderForm));

        return $genderForm === '' ? $key : $key . '|' . $genderForm;
    }

    /**
     * @return list<string>
     */
    private static function termFallbackKeys(string $name, string $form, string $genderForm = ''): array
    {
        $form = strtolower(trim($form));
        $genderForm = strtolower(trim($genderForm));
        $forms = match ($form) {
            'verb-short' => ['verb-short', 'verb', 'short', 'long'],
            'verb' => ['verb', 'long'],
            'symbol' => ['symbol', 'short', 'long'],
            'short' => ['short', 'long'],
            'long', '' => ['long'],
            default => [$form, 'long'],
        };

        $keys = [];
        foreach ($forms as $candidate) {
            if ($genderForm !== '') {
                $key = self::termKey($name, $candidate, $genderForm);
                if (!in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
            $key = self::termKey($name, $candidate);
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
