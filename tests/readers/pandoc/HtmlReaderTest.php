<?php

declare(strict_types=1);

use PortLibs\Pandoc\Html5Dom;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PandocHtmlTagSoupReader;
use PortLibs\Pandoc\TagSoupParser;
use PortLibs\Pandoc\WordPressBlockWriter;

$tests = [];

$fixture = static function (string $name): string {
    $bytes = file_get_contents(__DIR__ . '/../fixtures/' . $name);
    if ($bytes === false) {
        throw new RuntimeException("Unable to read fixture {$name}");
    }

    return $bytes;
};

$methodBody = static function (string $source, string $methodName): string {
    $tokens = token_get_all($source);
    $count = count($tokens);
    for ($index = 0; $index < $count; ++$index) {
        $token = $tokens[$index];
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        $nameIndex = $index + 1;
        while ($nameIndex < $count) {
            $nameToken = $tokens[$nameIndex];
            if (is_array($nameToken) && $nameToken[0] === T_STRING) {
                break;
            }
            if ($nameToken === '(') {
                $nameIndex = $count;
                break;
            }
            ++$nameIndex;
        }
        if ($nameIndex >= $count || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][1] !== $methodName) {
            continue;
        }

        while ($nameIndex < $count && $tokens[$nameIndex] !== '{') {
            ++$nameIndex;
        }
        if ($nameIndex >= $count) {
            break;
        }

        $depth = 0;
        $body = '';
        for ($bodyIndex = $nameIndex; $bodyIndex < $count; ++$bodyIndex) {
            $bodyToken = $tokens[$bodyIndex];
            $text = is_array($bodyToken) ? $bodyToken[1] : $bodyToken;
            if ($text === '{') {
                ++$depth;
                if ($depth === 1) {
                    continue;
                }
            }
            if ($text === '}') {
                --$depth;
                if ($depth === 0) {
                    return $body;
                }
            }
            if ($depth >= 1) {
                $body .= $text;
            }
        }
    }

    throw new RuntimeException("Unable to find method body for {$methodName}");
};

$tests['routes public html reader through TagSoup Pandoc backend by default'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read('<p><strong>one</strong> two</p>');
        $meta = $document->attr('meta');

        $t->same(PandocHtmlTagSoupReader::class, $meta['reader'] ?? null);
        $t->same('tagsoup-pandoc-html-reader-port', $meta['readerScope'] ?? null);
        $t->same(TagSoupParser::class, $meta['htmlTokenizer'] ?? null);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['strong', 'text'], array_map(static fn ($node): string => $node->type, $document->children[0]->children));
    };

$tests['routes explicit legacy html reader parsing through HTMLDocument when available'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<!doctype html><html><body><p>one<section><p>two</section>three</body></html>'
        );
        $meta = $document->attr('meta');
        $t->true(class_exists('Dom\\HTMLDocument'), 'Dom\\HTMLDocument is required for HTML reader tree construction');

        $t->same('Dom\\HTMLDocument', $meta['htmlTreeConstruction'] ?? null);

        $t->same(
            ['paragraph', 'div', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('one', $document->children[0]->attr('text'));
        $t->same(['section'], $document->children[1]->attr('classes'));
        $t->same('two', $document->children[1]->children[0]->attr('text'));
        $t->same('three', $document->children[2]->attr('text'));
    };

$tests['routes explicit legacy html reader HTML5 repairs through HTMLDocument when available'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<!doctype html><html><body><p><b>one<p>two</b>three<table>loose<tr><td>A</td></tr></table>tail</body></html>'
        );
        $meta = $document->attr('meta');
        $t->true(class_exists('Dom\\HTMLDocument'), 'Dom\\HTMLDocument is required for HTML reader tree construction');

        $t->same('Dom\\HTMLDocument', $meta['htmlTreeConstruction'] ?? null);

        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['one', 'twothree', 'loose', 'A', 'tail'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $document->children[0]->children));
        $t->same(['strong', 'text'], array_map(static fn ($node): string => $node->type, $document->children[1]->children));
        $t->same('two', $document->children[1]->children[0]->children[0]->attr('text'));
        $t->same('three', $document->children[1]->children[1]->attr('text'));
    };

$tests['uses HTMLDocument for parser-hard html5 tree construction cases'] =
    static function (TestRunner $t): void {
        $source = '<!doctype html><html><body><p><a><b>one</a>two</b><table><tr><td>A</td></tr>loose</table><select><option>One<option>Two</select><p>tail</body></html>';
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($source);
        $meta = $document->attr('meta');

        $t->same('Dom\\HTMLDocument', $meta['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['onetwo', 'loose', 'A', 'OneTwo', 'tail'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
        $t->same(['strong', 'strong'], array_map(static fn ($node): string => $node->type, $document->children[0]->children));
        $t->same('one', $document->children[0]->children[0]->children[0]->attr('text'));
        $t->same('two', $document->children[0]->children[1]->children[0]->attr('text'));
    };

$tests['does not route explicit legacy html input through markdown raw html tokenization'] =
    static function (TestRunner $t): void {
        $source = '<!doctype html><html><body><p><b>one<p>two</b>three<table>loose<tr><td>A</td></tr></table>tail</body></html>';
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($source);
        $markdownDocument = (new MarkdownReader())->read($source);

        $t->same('Dom\\HTMLDocument', $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['one', 'twothree', 'loose', 'A', 'tail'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
        $t->true(
            in_array('table', array_map(static fn ($node): string => $node->type, $markdownDocument->children), true),
            'The Markdown source reader must remain distinguishable from the explicit legacy HTML bridge reader'
        );
    };

$tests['maps invalid html input c1 bytes like pandoc text parsing'] =
    static function (TestRunner $t): void {
        $bytes = '<p>A' . chr(0x80) . 'B' . chr(0x81) . 'C' . chr(0x82) . 'D' . chr(0x85) . 'E' . chr(0x8d) . 'F' . chr(0x9f) . 'G</p>';
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($bytes);
        $paragraph = $document->children[0];

        $t->same('Dom\\HTMLDocument', $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('A€B?C‚D…E?FŸG', $paragraph->attr('text'));
        $t->same('A€B?C‚D…E?FŸG', $paragraph->children[0]->attr('text'));
    };

$tests['keeps html tree construction centralized through Html5Dom'] =
    static function (TestRunner $t) use ($methodBody): void {
        $sourceRoot = dirname(__DIR__, 3) . '/lib/pandoc';
        $htmlReaderSource = (string) file_get_contents($sourceRoot . '/HtmlReader.php');
        $markdownReaderSource = (string) file_get_contents($sourceRoot . '/MarkdownReader.php');
        $html5DomSource = (string) file_get_contents($sourceRoot . '/Html5Dom.php');
        $xmlHtmlDomSource = (string) file_get_contents($sourceRoot . '/XmlHtmlDom.php');
        $xmlHtmlDomFragmentSource = (string) file_get_contents($sourceRoot . '/XmlHtmlDomFragment.php');
        $html5DomFragmentSource = (string) file_get_contents($sourceRoot . '/Html5DomFragment.php');
        $sourceFiles = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $path = $fileInfo->getPathname();
            $sourceFiles[str_replace($sourceRoot . '/', '', $path)] = (string) file_get_contents($path);
        }

        foreach ([
            'HtmlReader.php' => $htmlReaderSource,
            'MarkdownReader.php' => $markdownReaderSource,
        ] as $file => $source) {
            $t->true(!str_contains($source, '->loadHTML('), "{$file} must not bypass Html5Dom with DOMDocument::loadHTML");
            $t->true(!str_contains($source, 'HTMLDocument::createFromString'), "{$file} must not bypass Html5Dom with Dom\\HTMLDocument");
            $t->true(!str_contains($source, 'new \\DOMDocument'), "{$file} must not construct DOMDocument directly for HTML parsing");
            $t->true(!str_contains($source, 'new DOMDocument'), "{$file} must not construct DOMDocument directly for HTML parsing");
        }
        foreach ($sourceFiles as $file => $source) {
            if ($file === 'Html5Dom.php') {
                continue;
            }

            $t->true(!str_contains($source, '->loadHTML('), "{$file} must not bypass Html5Dom with DOMDocument::loadHTML");
            $t->true(!str_contains($source, 'HTMLDocument::createFromString'), "{$file} must not bypass Html5Dom with Dom\\HTMLDocument");
        }
        foreach (['preg_', 'parseXmlFragment(', 'rawHtmlOpeningTagAt', 'rawHtmlClosingTagAt', 'markdownRawHtml'] as $parserFragment) {
            $t->true(!str_contains($htmlReaderSource, $parserFragment), "HtmlReader must not parse HTML source via {$parserFragment}");
        }

        $t->true(!str_contains($xmlHtmlDomSource, '->loadHTML('), 'XmlHtmlDom.php must route HTML fragment parsing through Html5Dom');
        $t->true(!str_contains($xmlHtmlDomFragmentSource, '->loadHTML('), 'XmlHtmlDomFragment.php must route HTML fragment parsing through Html5Dom');
        $t->true(!str_contains($html5DomFragmentSource, '->loadHTML('), 'Html5DomFragment.php must route HTML fragment parsing through Html5Dom');
        $t->true(!str_contains($html5DomFragmentSource, 'HTMLDocument::createFromString'), 'Html5DomFragment.php must not bypass Html5Dom with Dom\\HTMLDocument');
        $t->true(!str_contains($html5DomFragmentSource, 'treeConstructedHtmlSource($source)'), 'Html5DomFragment.php must not preparse fragments before Html5Dom');

        $t->contains('HTMLDocument::createFromString', $html5DomSource);
        $t->contains('insertAdjacentHTML', $html5DomSource);
        $t->contains('AdjacentPosition::BeforeEnd', $html5DomSource);
        $t->contains('public const HTML_DOCUMENT_PARSE_OPTIONS = LIBXML_NOERROR | LIBXML_COMPACT;', $html5DomSource);
        $t->contains('function parseHtmlFragment', $html5DomSource);
        $t->contains('function parseHtmlDocument', $html5DomSource);
        $t->contains('function htmlFragmentTreeConstructionContext', $html5DomSource);
        $t->contains('function htmlTreeConstructionInput', $html5DomSource);
        $t->contains('Html5Dom::parseHtmlFragment(', $html5DomFragmentSource);
        $t->contains('function rawHtmlOpeningTagAt', $html5DomSource);
        $t->contains('HTMLDocument::createFromString', $html5DomSource);
        $t->contains('Html5Dom::markdownRawHtmlOpeningTagBoundary', $markdownReaderSource);
        $t->contains('Html5Dom::rawHtmlOpeningTagAt', $markdownReaderSource);

        foreach ([
            'HtmlReader::read' => $methodBody($htmlReaderSource, 'read'),
            'MarkdownReader::readHtml' => $methodBody($markdownReaderSource, 'readHtml'),
            'MarkdownReader::parseHtmlDocument' => $methodBody($markdownReaderSource, 'parseHtmlDocument'),
            'MarkdownReader::parseHtmlFragmentBodyElement' => $methodBody($markdownReaderSource, 'parseHtmlFragmentBodyElement'),
            'MarkdownReader::parseHtmlFullDocumentDom' => $methodBody($markdownReaderSource, 'parseHtmlFullDocumentDom'),
        ] as $method => $body) {
            foreach (['preg_', 'parseXmlFragment(', 'rawHtmlOpeningTagAt', 'rawHtmlClosingTagAt', 'markdownRawHtml'] as $parserFragment) {
                $t->true(!str_contains($body, $parserFragment), "{$method} must not parse HTML source via {$parserFragment}");
            }
        }

        $t->contains('Html5Dom::parseHtmlFragment(', $methodBody($markdownReaderSource, 'parseHtmlFragmentBodyElement'));
        $t->contains('Html5Dom::parseHtmlDocument(', $methodBody($markdownReaderSource, 'parseHtmlFullDocumentDom'));
        $t->contains('self::loadHtml(', $methodBody($html5DomSource, 'parseHtmlDocument'));
        $t->contains('self::requireNativeHtmlDocument(', $methodBody($html5DomSource, 'loadHtml'));
        $t->contains('self::html5TreeConstructedBridgeSource(', $methodBody($html5DomSource, 'loadHtml'));
        $t->contains('HTMLDocument::createFromString', $methodBody($html5DomSource, 'html5TreeConstructedBridgeSource'));
        $t->same('return $html;', trim($methodBody($html5DomSource, 'html5ProtectedLiteralTreeInput')));
        $t->true(
            !str_contains($methodBody($html5DomSource, 'html5ProtectedLiteralTreeInput'), 'protectHtmlRcdataElements'),
            'Html5Dom must pass original source bytes into HTMLDocument tree construction'
        );
        $t->same(
            substr_count($html5DomSource, 'HTMLDocument::createFromString'),
            substr_count($html5DomSource, 'self::HTML_DOCUMENT_PARSE_OPTIONS'),
            'Every native HTMLDocument parse must use the centralized native parse options'
        );

        $delegatingHtmlParserEntrypoints = [
            'HtmlReader::parseHtmlFragmentBody' => [
                'body' => $methodBody($htmlReaderSource, 'parseHtmlFragmentBody'),
                'delegates' => ['Html5Dom::parseHtmlFragment('],
            ],
            'HtmlReader::parseHtmlRewriteSource' => [
                'body' => $methodBody($htmlReaderSource, 'parseHtmlRewriteSource'),
                'delegates' => ['Html5Dom::parseHtmlDocument(', 'Html5Dom::parseHtmlFragment('],
            ],
            'MarkdownReader::parseHtmlFragmentBodyElement' => [
                'body' => $methodBody($markdownReaderSource, 'parseHtmlFragmentBodyElement'),
                'delegates' => ['Html5Dom::parseHtmlFragment('],
            ],
            'MarkdownReader::parseHtmlFullDocumentDom' => [
                'body' => $methodBody($markdownReaderSource, 'parseHtmlFullDocumentDom'),
                'delegates' => ['Html5Dom::parseHtmlDocument('],
            ],
            'Html5DomFragment::loadHtmlFragmentRoot' => [
                'body' => $methodBody($html5DomFragmentSource, 'loadHtmlFragmentRoot'),
                'delegates' => ['Html5Dom::parseHtmlFragment('],
            ],
            'XmlHtmlDom::loadHtmlFragment' => [
                'body' => $methodBody($xmlHtmlDomSource, 'loadHtmlFragment'),
                'delegates' => ['Html5Dom::parseHtmlFragment('],
            ],
            'XmlHtmlDomFragment::parseHtml' => [
                'body' => $methodBody($xmlHtmlDomFragmentSource, 'parseHtml'),
                'delegates' => ['Html5Dom::parseHtmlFragment('],
            ],
        ];
        foreach ($delegatingHtmlParserEntrypoints as $method => $entrypoint) {
            foreach ($entrypoint['delegates'] as $delegate) {
                $t->contains($delegate, $entrypoint['body']);
            }
            foreach (['preg_', 'parseXmlFragment(', 'rawHtmlOpeningTagAt', 'rawHtmlClosingTagAt', 'markdownRawHtml', '->loadHTML(', 'HTMLDocument::createFromString'] as $parserFragment) {
                $t->true(!str_contains($entrypoint['body'], $parserFragment), "{$method} must delegate HTML parsing instead of using {$parserFragment}");
            }
        }
        foreach ([
            'HtmlReader::plainBlockFromHtmlInlineSource' => $methodBody($htmlReaderSource, 'plainBlockFromHtmlInlineSource'),
            'HtmlReader::standaloneTransparentInlineFragmentChildren' => $methodBody($htmlReaderSource, 'standaloneTransparentInlineFragmentChildren'),
        ] as $method => $body) {
            $t->contains('->readHtml(', $body);
            $t->true(!str_contains($body, '->read('), "{$method} must parse HTML fragments through MarkdownReader::readHtml, not MarkdownReader::read");
        }

        $html5TreeConstructionBodies = [
            'Html5Dom::parseHtmlFragment' => $methodBody($html5DomSource, 'parseHtmlFragment'),
            'Html5Dom::parseHtmlDocument' => $methodBody($html5DomSource, 'parseHtmlDocument'),
            'Html5Dom::loadHtml' => $methodBody($html5DomSource, 'loadHtml'),
            'Html5Dom::html5TreeConstructedBridgeSource' => $methodBody($html5DomSource, 'html5TreeConstructedBridgeSource'),
            'Html5Dom::html5TreeConstructedFragment' => $methodBody($html5DomSource, 'html5TreeConstructedFragment'),
            'Html5Dom::html5BodyContextFragment' => $methodBody($html5DomSource, 'html5BodyContextFragment'),
            'Html5Dom::html5TableContextFragment' => $methodBody($html5DomSource, 'html5TableContextFragment'),
            'Html5Dom::html5ProtectedLiteralTreeInput' => $methodBody($html5DomSource, 'html5ProtectedLiteralTreeInput'),
        ];
        foreach ($html5TreeConstructionBodies as $method => $body) {
            foreach ([
                'preg_',
                'parseXmlFragment(',
                'rawHtmlOpeningTagAt',
                'rawHtmlClosingTagAt',
                'markdownRawHtml',
                'htmlDoctypeHtmlAt',
                'xmlDeclarationEndAt',
                'isHtmlAttributeName',
                'isHtmlUnquotedAttributeValueChar',
                'isHtmlTagNameChar',
            ] as $parserFragment) {
                $t->true(!str_contains($body, $parserFragment), "{$method} must not rebuild HTML parsing with {$parserFragment}");
            }
        }

        $loadHtmlBody = $html5TreeConstructionBodies['Html5Dom::loadHtml'];
        $bridgeOffset = strpos($loadHtmlBody, 'self::html5TreeConstructedBridgeSource(');
        $legacyOffset = strpos($loadHtmlBody, 'self::loadLegacyHtml(');
        $t->true($bridgeOffset !== false, 'Html5Dom::loadHtml must start from HTMLDocument tree construction');
        $t->true($legacyOffset !== false, 'Html5Dom::loadHtml may only use legacy DOMDocument as a post-HTMLDocument bridge');
        $t->true($bridgeOffset !== false && $legacyOffset !== false && $bridgeOffset < $legacyOffset, 'Html5Dom::loadHtml must tree-construct through HTMLDocument before the legacy bridge');

        foreach ([
            "preg_match('/^ {0,3}<",
            '[A-Za-z_:][A-Za-z0-9_.:-]',
            '~\\G<',
            '<t[dh]',
            '</?table\\b[^>]*',
        ] as $removedRawHtmlParserFragment) {
            $t->true(
                !str_contains($markdownReaderSource, $removedRawHtmlParserFragment),
                "MarkdownReader must not restore raw HTML tag parsing via {$removedRawHtmlParserFragment}"
            );
        }
        foreach ([
            'htmlTagTokens',
            'wrapOrphanTableScopeRuns',
            'hasOrphanTableScopeFragment',
            'isOrphanTableScopeElementName',
        ] as $removedScanner) {
            $t->true(!str_contains($html5DomSource, $removedScanner), "Html5Dom must not restore source-scanned HTML tree construction via {$removedScanner}");
        }
        foreach ([
            'htmlSourceContainsAnyStartTagName',
            'orphanTableFragmentSourceElementNames',
            'hasHtmlTableScopeElement',
            'htmlStartTagNames',
            'htmlTagSourceEndOffset',
            'htmlClosingTagSourceName',
            'isHtmlTagNameChar',
            'flattenInvalidTableChildrenSourceOrder',
            'serializeInvalidTableRepair',
            'invalidTableSourceOrder',
            'invalidTableModelElementBlockSources',
            'isInvalidTableAllowedDirectChildName',
        ] as $removedScanner) {
            $t->true(!str_contains($htmlReaderSource, $removedScanner), "HtmlReader must not restore source-scanned HTML tree construction via {$removedScanner}");
            $t->true(!str_contains($html5DomFragmentSource, $removedScanner), "Html5DomFragment must not restore source-scanned HTML tree construction via {$removedScanner}");
        }
    };

$tests['reports html document tree construction backend for table-scope fragments'] =
    static function (TestRunner $t): void {
        $t->same(
            Html5Dom::htmlDocumentTreeConstructionBackend(),
            Html5Dom::htmlFragmentTreeConstructionBackend('<p>one<section><p>two</section>three')
        );

        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<td>A</td><td>B</td><tr><td>C</td></tr><p>after</p>');
        $meta = $document->attr('meta');

        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $meta['htmlTreeConstruction'] ?? null);
        $t->same(
            ['A', 'B', 'C', 'after'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
    };

$tests['imports upstream html xml lang metadata from root element'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-xml-lang-metadata.html'));
        $meta = $document->attr('meta');

        $t->same('es', $meta['lang']);
        $t->same('html', $meta['sourceFormat']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('hola', $document->children[0]->attr('text'));
    };

$tests['imports html body lang metadata with pandoc body precedence'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-body-lang-overrides-html.html'));
        $meta = $document->attr('meta');

        $t->same('fr', $meta['lang'] ?? null);
        $t->same('html', $meta['sourceFormat']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('bonjour', $document->children[0]->attr('text'));

        $bodyOnly = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<!doctype html><html><body lang="de"><p>hallo</p></body></html>');
        $bodyOnlyMeta = $bodyOnly->attr('meta');
        $t->same('de', $bodyOnlyMeta['lang'] ?? null);
        $t->same('hallo', $bodyOnly->children[0]->attr('text'));

        $bodyXmlLang = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<!doctype html><html lang="es"><body xml:lang="pt-BR"><p>ola</p></body></html>');
        $bodyXmlLangMeta = $bodyXmlLang->attr('meta');
        $t->same('pt-BR', $bodyXmlLangMeta['lang'] ?? null);
        $t->same('ola', $bodyXmlLang->children[0]->attr('text'));
    };

$tests['appends html meta name fields without trimming or lowercasing like upstream pandoc'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(<<<'HTML'
<html><head>
<meta name="keywords" content="one">
<meta name="keywords" content="two">
<meta name="Empty" content="">
<meta name="spaced" content="  keep  ">
</head><body><p>x</p></body></html>
HTML);
        $meta = $document->attr('meta');

        $t->same(['one', 'two'], $meta['keywords'] ?? null);
        $t->same('', $meta['Empty'] ?? null);
        $t->same('  keep  ', $meta['spaced'] ?? null);
        $t->true(!array_key_exists('empty', $meta));
        $t->same('x', $document->children[0]->attr('text'));
    };

$tests['imports direct pandoc html meta refresh boundary fixture metadata'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-meta-refresh-boundary.html'));
        $meta = $document->attr('meta');

        $t->same('HTML Meta Refresh Import', $meta['title']);
        $t->same('Keep me', $meta['description']);
        $t->true(!array_key_exists('refresh', $meta));
        $t->same('html', $meta['sourceFormat']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Visible.', $document->children[0]->attr('text'));
    };

$tests['imports upstream html sup and sub inline nodes'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-sup-sub-inline.html'));
        $paragraph = $document->children[0];

        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['text', 'subscript', 'text', 'superscript', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Formula H', $paragraph->children[0]->attr('text'));
        $t->same('2', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('O and release note', $paragraph->children[2]->attr('text'));
        $t->same('review', $paragraph->children[3]->children[0]->attr('text'));
        $t->same(' stay inline.', $paragraph->children[4]->attr('text'));
    };

$tests['imports direct pandoc html standalone sup and sub fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-sup-sub-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['subscript', 'text', 'superscript'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same('2', $plain->children[0]->children[0]->attr('text'));
        $t->same(' and ', $plain->children[1]->attr('text'));
        $t->same('review', $plain->children[2]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone time fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-time-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['strong', 'text'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('handoff', $plain->children[0]->children[0]->attr('text'));
        $t->same(' day', $plain->children[1]->attr('text'));
    };

$tests['imports direct pandoc html standalone progress fragment as plain blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-progress-inline.html'));
        $progress = $document->children[0];
        $after = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain', 'plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $progress->children));
        $t->same('70%', $progress->children[0]->attr('text'));
        $t->same('import complete.', $after->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $after->children));
    };

$tests['imports direct pandoc html standalone media fallback fragments as plain blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $video = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-video-inline.html'));
        $audio = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-audio-inline.html'));

        $t->same('html', $video->attr('sourceFormat'));
        $t->same(['plain', 'plain'], array_map(static fn ($node): string => $node->type, $video->children));
        $t->same('Video fallback', $video->children[0]->attr('text'));
        $t->same('remains visible after import.', $video->children[1]->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $video->children[0]->children));

        $t->same('html', $audio->attr('sourceFormat'));
        $t->same(['plain', 'plain'], array_map(static fn ($node): string => $node->type, $audio->children));
        $t->same('Audio fallback', $audio->children[0]->attr('text'));
        $t->same('remains playable after import.', $audio->children[1]->attr('text'));

        $paragraph = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<p><video><source src="movie.mp4"><track src="captions.vtt">fallback</video> remains visible.</p>'
        )->children[0];
        $t->same('paragraph', $paragraph->type);
        $t->same('fallback remains visible.', $paragraph->attr('text'));
        $t->same(['text', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
    };

$tests['imports direct pandoc html progress in paragraph as fallback text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-progress-in-paragraph.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('before fallback after', $paragraph->attr('text'));
        $t->same(['text', 'text', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same(
            ['before ', 'fallback', ' after'],
            array_map(static fn ($node): string => $node->attr('text'), $paragraph->children)
        );
    };

$tests['imports direct pandoc html inline time without raw wrappers'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<p>At <time datetime="2026-07-04"><strong>noon</strong></time>.</p>');
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('At noon.', $paragraph->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('At ', $paragraph->children[0]->attr('text'));
        $t->same('noon', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('.', $paragraph->children[2]->attr('text'));
    };

$tests['imports direct pandoc html inline button fallback without raw wrappers'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-button-inline-fallback.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Press me now', $paragraph->attr('text'));
        $t->same(['strong', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('Press', $paragraph->children[0]->children[0]->attr('text'));
        $t->same(' me now', $paragraph->children[1]->attr('text'));
    };

$tests['imports direct pandoc html block fallback content containers without raw wrappers'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-fallback-content-containers.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlFragmentTreeConstructionBackend($fixture('upstream-html-fallback-content-containers.html')), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['Script fallback bold', 'Object fallback content', 'Frame fallback code', 'After fallback.'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $document->children[0]->children));
        $t->same(['text', 'emph'], array_map(static fn ($node): string => $node->type, $document->children[1]->children));
        $t->same(['text', 'code'], array_map(static fn ($node): string => $node->type, $document->children[2]->children));
        $t->same('bold', $document->children[0]->children[1]->children[0]->attr('text'));
        $t->same('content', $document->children[1]->children[1]->children[0]->attr('text'));
        $t->same('code', $document->children[2]->children[1]->attr('text'));
    };

$tests['imports direct pandoc html inline fallback content containers without raw wrappers'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-inline-fallback-content-containers.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Before Frame fallback, script fallback, and object fallback.', $paragraph->attr('text'));
        $t->same('After fallback.', $document->children[1]->attr('text'));
        $t->same(
            ['text', 'strong', 'text', 'emph', 'text', 'code', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Before Frame ', $paragraph->children[0]->attr('text'));
        $t->same('fallback', $paragraph->children[1]->children[0]->attr('text'));
        $t->same(', script ', $paragraph->children[2]->attr('text'));
        $t->same('fallback', $paragraph->children[3]->children[0]->attr('text'));
        $t->same(', and ', $paragraph->children[4]->attr('text'));
        $t->same('object', $paragraph->children[5]->attr('text'));
        $t->same(' fallback.', $paragraph->children[6]->attr('text'));
    };

$tests['can preserve html inline raw wrappers for epub-compatible content reads'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE, 'htmlRawHtml' => true, 'htmlStripRawInlineWrappers' => false]))
            ->read('<p>At <time class="release">TBD</time>.</p>');
        $paragraph = $document->children[0];

        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['text', 'raw_html_inline', 'text', 'raw_html_inline', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('<time class="release">', $paragraph->children[1]->attr('html'));
        $t->same('TBD', $paragraph->children[2]->attr('text'));
        $t->same('</time>', $paragraph->children[3]->attr('html'));
    };

$tests['preserves textarea raw block source newline after HTMLDocument tree construction'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE, 'htmlRawHtml' => true]))->read($fixture('upstream-html-textarea-raw-block.html'));
        $rawBlock = $document->children[1];
        $expected = '<textarea id="legacy-packet" class="source-payload" data-source="batch-42">'
            . "\n"
            . 'Legacy shortcode [gallery ids="10,11"] and review notes stay literal.'
            . "\n"
            . '</textarea>';

        $t->same('Dom\\HTMLDocument', $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(['paragraph', 'raw_html', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same($expected, $rawBlock->attr('html'));
        $t->same($expected, $rawBlock->attr('text'));
    };

$tests['imports direct pandoc html standalone keyboard fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-kbd-inline.html'));
        $plain = $document->children[0];
        $kbd = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['span'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same(['kbd'], $kbd->attr('classes'));
        $t->same('Cmd', $kbd->children[0]->attr('text'));
    };

$tests['imports upstream html standalone code aliases as plain code inlines'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-code-aliases-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('Dom\\HTMLDocument', $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['code', 'code', 'code', 'code'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same(
            ['Answer is 42', 'Answer is 43', 'Missing alt text', 'result'],
            array_map(static fn ($node): string => $node->attr('text'), $plain->children)
        );
        $t->same([], $plain->children[0]->attr('classes', []));
        $t->same([], $plain->children[1]->attr('classes', []));
        $t->same(['sample'], $plain->children[2]->attr('classes'));
        $t->same(['variable'], $plain->children[3]->attr('classes'));
    };

$tests['imports direct pandoc html standalone output fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-output-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Result ready', $plain->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('Result ', $plain->children[0]->attr('text'));
        $t->same('ready', $plain->children[1]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone underline fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-underline-inline.html'));
        $plain = $document->children[0];
        $underline = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('underline', $underline->type);
        $t->same(
            ['text', 'strong'],
            array_map(static fn ($node): string => $node->type, $underline->children)
        );
        $t->same('under ', $underline->children[0]->attr('text'));
        $t->same('review', $underline->children[1]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone semantic inline fragments as plain'] =
    static function (TestRunner $t): void {
        $cases = [
            '<abbr title="Hypertext">HTML</abbr>' => ['span', 'HTML', ['abbr']],
            '<b>bold</b>' => ['strong', 'bold', []],
            '<i>italics</i>' => ['emph', 'italics', []],
            '<small>fine print</small>' => ['span', 'fine print', ['small']],
            '<dfn>term</dfn>' => ['span', 'term', ['dfn']],
            '<del>old</del>' => ['strikeout', 'old', []],
            '<ins>new</ins>' => ['underline', 'new', []],
            '<s>gone</s>' => ['strikeout', 'gone', []],
            '<strike>gone</strike>' => ['strikeout', 'gone', []],
        ];

        foreach ($cases as $html => [$expectedType, $expectedText, $expectedClasses]) {
            $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html);
            $plain = $document->children[0];
            $inline = $plain->children[0];

            $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children), $html);
            $t->same([$expectedType], array_map(static fn ($node): string => $node->type, $plain->children), $html);
            $t->same($expectedText, $plain->attr('text'), $html);
            $t->same($expectedText, $inline->children[0]->attr('text'), $html);
            $t->same($expectedClasses, $inline->attr('classes', []), $html);
        }

        $abbr = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<abbr title="Hypertext">HTML</abbr>')->children[0]->children[0];
        $t->same('Hypertext', $abbr->attr('attributes')['title'] ?? null);
    };

$tests['imports direct pandoc html standalone b and i aliases fixture as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-b-i-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['strong', 'emph'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('bolditalics', $plain->attr('text'));
        $t->same('bold', $plain->children[0]->children[0]->attr('text'));
        $t->same('italics', $plain->children[1]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone abbr and dfn fixture as plain spans'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-abbr-dfn-inline.html'));
        $plain = $document->children[0];
        $abbr = $plain->children[0];
        $separator = $plain->children[1];
        $dfn = $plain->children[2];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('HTML and term', $plain->attr('text'));
        $t->same(['span', 'text', 'span'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same(['abbr'], $abbr->attr('classes'));
        $t->same('Hypertext', $abbr->attr('attributes')['title'] ?? null);
        $t->same('HTML', $abbr->children[0]->attr('text'));
        $t->same(' and ', $separator->attr('text'));
        $t->same(['dfn'], $dfn->attr('classes'));
        $t->same('term', $dfn->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone bdo mark q fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-bdo-mark-q-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['span', 'span', 'quoted'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same(['dir' => 'rtl'], $plain->children[0]->attr('attributes'));
        $t->same('abc', $plain->children[0]->children[0]->attr('text'));
        $t->same(['mark'], $plain->children[1]->attr('classes'));
        $t->same('hi', $plain->children[1]->children[0]->attr('text'));
        $t->same('double', $plain->children[2]->attr('kind'));
        $t->same('/source', $plain->children[2]->children[0]->attr('attributes')['cite'] ?? null);
        $t->same('quote', $plain->children[2]->children[0]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone bdi fragment as visible text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-bdi-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('abc and name', $plain->attr('text'));
        $t->same(
            ['text', 'text', 'text'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same('abc', $plain->children[0]->attr('text'));
        $t->same(' and ', $plain->children[1]->attr('text'));
        $t->same('name', $plain->children[2]->attr('text'));
    };

$tests['imports upstream html self closing anchor without href as span'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<a name="anchor"/>');
        $plain = $document->children[0];
        $anchor = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['span'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('anchor', $anchor->attr('id'));
        $t->same([], $anchor->children);
    };

$tests['repairs paired unresolved html fragment links by visible label'] =
    static function (TestRunner $t): void {
        $html = '<h1>Notes</h1>'
            . '<p>Reference <a href="#target-a">(1)</a> and <a href="#target-long">(long)</a>.</p>'
            . '<p><a href="#return-a">(1)</a> First note body.</p>'
            . '<p><a href="#return-long">(long)</a> Long note body.</p>';
        foreach ([
            'tagsoup' => new HtmlReader(),
            'bridge' => new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]),
        ] as $label => $reader) {
            $document = $reader->read($html);
            $htmlOutput = (new HtmlWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(4, $document->attr('htmlLocalFragmentLinkRepairCount'), "{$label} should repair both paired links.");
            $t->same(true, $document->children[1]->children[1]->attr('htmlLocalFragmentLinkRepair'));
            $t->contains('<a href="#target-a" id="return-a">(1)</a>', $htmlOutput);
            $t->contains('<a href="#return-a" id="target-a">(1)</a>', $htmlOutput);
            $t->contains('<a href="#target-long" id="return-long">(long)</a>', $blocks);
            $t->contains('<a href="#return-long" id="target-long">(long)</a>', $blocks);
        }
    };

$tests['imports upstream html standalone inline code aliases as plain blocks'] =
    static function (TestRunner $t): void {
        $cases = [
            '<code>Answer is 42</code>' => [[], 'Answer is 42'],
            '<tt>Answer is 42</tt>' => [[], 'Answer is 42'],
            '<samp>Answer is 42</samp>' => [['sample'], 'Answer is 42'],
            '<var>result</var>' => [['variable'], 'result'],
        ];

        foreach ($cases as $html => [$classes, $text]) {
            $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html);
            $plain = $document->children[0];
            $code = $plain->children[0];

            $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children), $html);
            $t->same(['code'], array_map(static fn ($node): string => $node->type, $plain->children), $html);
            $t->same($classes, $code->attr('classes', []), $html);
            $t->same($text, $code->attr('text'), $html);
        }
    };

$tests['imports upstream html base absolute image without rewriting absolute url'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-base-absolute-image.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $image = $paragraph->children[0];

        $t->same('HTML Base Absolute Image Import', $meta['title']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('http://example.com/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('The title', $image->attr('title'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports upstream html standalone image fragments as plain images'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-image-data-external.html'));
        $plain = $document->children[0];
        $image = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('', $plain->attr('text'));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('http://example.com/stickman.gif', $image->attr('url'));
        $t->same('', $image->attr('alt'));
        $t->same(['external' => '1'], $image->attr('attributes'));
        $t->same('1', $image->attr('htmlAttributes')['data-external'] ?? null);
        $t->same([], $image->children);

        $titled = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<img title="The title" src="http://example.com/stickman.gif">');
        $titledImage = $titled->children[0]->children[0];
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $titled->children));
        $t->same('http://example.com/stickman.gif', $titledImage->attr('url'));
        $t->same('', $titledImage->attr('alt'));
        $t->same('The title', $titledImage->attr('title'));
        $t->same([], $titledImage->children);
    };

$tests['imports html figure with single image body in pandoc native shape'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<figure id="chart-figure"><img src="images/chart.png" alt="Quarterly chart"><figcaption>Quarterly chart caption.</figcaption></figure>'
        );
        $figure = $document->children[0];
        $plain = $figure->children[0];
        $image = $plain->children[0];

        $t->same(['figure'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $figure->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('chart-figure', $figure->attr('id'));
        $t->same('Quarterly chart caption.', $figure->attr('caption'));
        $t->same('images/chart.png', $image->attr('url'));
        $t->same('Quarterly chart', $image->attr('alt'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $image->children));
        $t->same('Quarterly chart', $image->children[0]->attr('text'));
    };

$tests['imports upstream html picture fallback images without source text leakage'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-picture-fallback-image.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $image = $paragraph->children[1];
        $after = $document->children[1];

        $t->same('HTML Picture Fallback Import', $meta['title']);
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Hero Hero frame selected.', $paragraph->attr('text'));
        $t->same(['text', 'image', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('Hero ', $paragraph->children[0]->attr('text'));
        $t->same('hero-small.jpg', $image->attr('url'));
        $t->same('Hero frame', $image->attr('alt'));
        $t->same('Fallback title', $image->attr('title'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $image->children));
        $t->same('Hero frame', $image->children[0]->attr('text'));
        $t->same(' selected.', $paragraph->children[2]->attr('text'));
        $t->same('After picture.', $after->attr('text'));

        $standalone = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<picture><source srcset="large.jpg"><img src="small.jpg" alt="Small" title="Title"></picture>'
        );
        $standaloneImage = $standalone->children[0]->children[0];
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $standalone->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $standalone->children[0]->children));
        $t->same('small.jpg', $standaloneImage->attr('url'));
        $t->same('Small', $standaloneImage->attr('alt'));
        $t->same('Title', $standaloneImage->attr('title'));
    };

$tests['imports direct pandoc html standalone emphasis strong span fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-emph-strong-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['strong', 'emph', 'span'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same('bold', $plain->children[0]->children[0]->attr('text'));
        $t->same('em', $plain->children[1]->children[0]->attr('text'));
        $t->same(['x'], $plain->children[2]->attr('classes'));
        $t->same('sp', $plain->children[2]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html standalone s inline as strikeout'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-s-inline.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Before obsolete after.', $paragraph->attr('text'));
        $t->same(
            ['text', 'strikeout', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Before ', $paragraph->children[0]->attr('text'));
        $t->same('obsolete', $paragraph->children[1]->children[0]->attr('text'));
        $t->same(' after.', $paragraph->children[2]->attr('text'));
    };

$tests['imports direct pandoc html data value inline as visible children'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-data-value-inline.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Answer forty two.', $paragraph->attr('text'));
        $t->same(
            ['text', 'strong', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Answer ', $paragraph->children[0]->attr('text'));
        $t->same('forty two', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('.', $paragraph->children[2]->attr('text'));
    };

$tests['imports direct pandoc html meter inline as visible children'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-meter-inline.html'));
        $paragraph = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Load sixty percent complete.', $paragraph->attr('text'));
        $t->same(
            ['text', 'strong', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Load ', $paragraph->children[0]->attr('text'));
        $t->same('sixty percent', $paragraph->children[1]->children[0]->attr('text'));
        $t->same(' complete.', $paragraph->children[2]->attr('text'));
    };

$tests['imports direct pandoc html inline-only main body as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-main-inline-plain.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('hello', $plain->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('hello', $plain->children[0]->attr('text'));

        $explicitParagraph = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<main><p>hello</p></main>');
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $explicitParagraph->children));
        $t->same('hello', $explicitParagraph->children[0]->attr('text'));
    };

$tests['imports direct pandoc html main that closes paragraph through HTML5 tree construction'] =
    static function (TestRunner $t) use ($fixture): void {
        $html = $fixture('upstream-html-main-closes-paragraph.html');
        $body = Html5Dom::parseHtmlFragment($html);
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html);
        $meta = $document->attr('meta');
        $plain = $document->children[0];

        $t->same('<p>hello</p><main>main content</main>', trim(Html5Dom::serializeHtmlChildren($body)));
        $t->same(Html5Dom::htmlFragmentTreeConstructionBackend($html), $meta['htmlTreeConstruction'] ?? null);
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('main content', $plain->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('main content', $plain->children[0]->attr('text'));

        $trailingText = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-main-followed-by-text.html'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $trailingText->children));
        $t->same('main content', $trailingText->children[0]->attr('text'));
    };

$tests['imports upstream html titlepage blocks as skipped content'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-titlepage-skip.html'));
        $meta = $document->attr('meta');

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('HTML Titlepage Skip Import', $meta['title']);
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $meta['htmlTreeConstruction'] ?? null);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Real main.', $document->children[0]->attr('text'));

        $paragraphTitlepage = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<p type="titlepage">Skip.</p><p>Body.</p>');
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $paragraphTitlepage->children));
        $t->same('Body.', $paragraphTitlepage->children[0]->attr('text'));
    };

$tests['imports upstream html main explicit role as native div'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-main-role-native-divs.html'));
        $div = $document->children[0];
        $plain = $div->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['div'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['role' => 'foobar'], $div->attr('attributes'));
        $t->same(['role' => 'foobar'], $div->attr('htmlAttributes'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $div->children));
        $t->same('hello', $plain->attr('text'));
    };

$tests['imports direct pandoc html transparent inline fragments as plain text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-transparent-inline-fragment.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('answer and half with isolated text', $plain->attr('text'));
        $t->same(
            ['text', 'text', 'text', 'text', 'text', 'text'],
            array_map(static fn ($node): string => $node->type, $plain->children)
        );
        $t->same(
            ['answer', ' and ', 'half', ' with ', 'isolated', ' text'],
            array_map(static fn ($node): string => $node->attr('text'), $plain->children)
        );
    };

$tests['imports upstream html head body fragment base relative image as plain image'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-base-relative-image.html'));
        $plain = $document->children[0];
        $image = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('http://www.w3schools.com/images/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports upstream html head body fragment base trailing slash image as plain image'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-base-trailing-slash-image.html'));
        $plain = $document->children[0];
        $image = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('http://www.w3schools.com/images/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports upstream html head body fragment base root-relative image as plain image'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-base-root-relative-image.html'));
        $plain = $document->children[0];
        $image = $plain->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('http://www.w3schools.com/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports html file base fragment query and scheme relative urls like pandoc'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<!doctype html><html><head>'
            . '<base href="https://source.example.test/import/posts/post.html">'
            . '</head><body><p>'
            . '<a href="#frag">frag</a>'
            . '<a href="?edition=full">query</a>'
            . '<a href="//cdn.example.test/a.png">cdn</a>'
            . '<img src="?cover=1" alt="Cover">'
            . '</p></body></html>'
        );
        $paragraph = $document->children[0];
        $fragmentLink = $paragraph->children[0];
        $queryLink = $paragraph->children[1];
        $schemeRelativeLink = $paragraph->children[2];
        $image = $paragraph->children[3];

        $t->same('https://source.example.test/import/posts/post.html#frag', $fragmentLink->attr('url'));
        $t->same('https://source.example.test/import/posts/post.html?edition=full', $queryLink->attr('url'));
        $t->same('https://cdn.example.test/a.png', $schemeRelativeLink->attr('url'));
        $t->same('https://source.example.test/import/posts/post.html?cover=1', $image->attr('url'));
    };

$tests['imports generated current html inline quote cites resolved against base'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-inline-quote-cite-base.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $outerQuote = $paragraph->children[1];
        $outerSpan = $outerQuote->children[0];
        $innerQuote = $outerSpan->children[1];
        $innerSpan = $innerQuote->children[0];

        $t->same('HTML Inline Quote Cite Base Import', $meta['title']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text', 'quoted', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('double', $outerQuote->attr('kind'));
        $t->same('single', $innerQuote->attr('kind'));
        $t->same(
            'https://source.example.test/import/posts/quotes/source.html#frag',
            $outerSpan->attr('attributes')['cite'] ?? null
        );
        $t->same(
            'https://source.example.test/import/shared/context.html',
            $innerSpan->attr('attributes')['cite'] ?? null
        );
        $t->same(['text', 'quoted'], array_map(static fn ($node): string => $node->type, $outerSpan->children));
        $t->same('outer ', $outerSpan->children[0]->attr('text'));
        $t->same('inner source', $innerSpan->children[0]->attr('text'));
        $t->same(' stays linked.', $paragraph->children[2]->attr('text'));
    };

$tests['imports generated current html blockquote fixture as native blockquote'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-blockquote.html'));
        $meta = $document->attr('meta');
        $quote = $document->children[0];
        $quoteParagraph = $quote->children[0];
        $after = $document->children[1];

        $t->same('HTML Blockquote Import', $meta['title']);
        $t->same(['blockquote', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same([], $quote->attrs);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $quote->children));
        $t->same('Quoted source paragraph.', $quoteParagraph->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $quoteParagraph->children));
        $t->same('Quoted ', $quoteParagraph->children[0]->attr('text'));
        $t->same('source', $quoteParagraph->children[1]->children[0]->attr('text'));
        $t->same(' paragraph.', $quoteParagraph->children[2]->attr('text'));
        $t->same('After quote.', $after->attr('text'));
    };

$tests['imports generated current html definition list fixture as native definition list'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-definition-list.html'));
        $meta = $document->attr('meta');
        $list = $document->children[0];
        $item = $list->children[0];
        $term = $item->children[0];
        $primary = $item->children[1]->children[0];
        $secondary = $item->children[2]->children[0];

        $t->same('HTML Definition List Import', $meta['title']);
        $t->same(['definition_list', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('definition_item', $item->type);
        $t->same('Packet source', $item->attr('term'));
        $t->same('term', $term->type);
        $t->same('Packet source', $term->attr('text'));
        $t->same(['definition', 'definition'], [$item->children[1]->type, $item->children[2]->type]);
        $t->same('paragraph', $primary->type);
        $t->same('plain', $secondary->type);
        $t->same('Definition primary.', $primary->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $primary->children));
        $t->same('primary', $primary->children[1]->children[0]->attr('text'));
        $t->same('Secondary note.', $secondary->attr('text'));
        $t->same(['text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $secondary->children));
        $t->same('note', $secondary->children[1]->children[0]->attr('text'));
        $t->same('After glossary.', $document->children[1]->attr('text'));
    };

$tests['imports direct pandoc html optional definition-list end tags as tight definitions'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-optional-definition-list-tree-construction.html'));
        $list = $document->children[0];
        $firstItem = $list->children[0];
        $secondItem = $list->children[1];
        $firstDefinition = $firstItem->children[1]->children[0];
        $secondDefinition = $secondItem->children[1]->children[0];
        $after = $document->children[1];

        $t->same(['definition_list', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['definition_item', 'definition_item'], array_map(static fn ($node): string => $node->type, $list->children));
        $t->same('Term alpha', $firstItem->attr('term'));
        $t->same('Term beta', $secondItem->attr('term'));
        $t->same('plain', $firstDefinition->type);
        $t->same('Definition alpha', $firstDefinition->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $secondDefinition->children));
        $t->same('Definition beta', $secondDefinition->attr('text'));
        $t->same('beta', $secondDefinition->children[1]->children[0]->attr('text'));
        $t->same('After definitions.', $after->attr('text'));
    };

$tests['imports direct pandoc html paragraph definition-list tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-definition-list-tree-construction.html'));
        $list = $document->children[1];
        $item = $list->children[0];
        $definition = $item->children[1]->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'definition_list', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same('Term', $item->attr('term'));
        $t->same('plain', $definition->type);
        $t->same('Definition one', $definition->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $definition->children));
        $t->same('one', $definition->children[1]->children[0]->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html multi-term definition-list optional end tags'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-multi-term-definition-list.html'));
        $list = $document->children[0];
        $firstItem = $list->children[0];
        $firstTerm = $firstItem->children[0];
        $firstDefinition = $firstItem->children[1]->children[0];
        $secondDefinition = $firstItem->children[2];
        $secondItem = $list->children[1];

        $t->same(['definition_list', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same("Term alpha\nAlias alpha", $firstItem->attr('term'));
        $t->same(['text', 'linebreak', 'text'], array_map(static fn ($node): string => $node->type, $firstTerm->children));
        $t->same('Term alpha', $firstTerm->children[0]->attr('text'));
        $t->same('Alias alpha', $firstTerm->children[2]->attr('text'));
        $t->same('plain', $firstDefinition->type);
        $t->same('First definition', $firstDefinition->attr('text'));
        $t->same(['text', 'emph'], array_map(static fn ($node): string => $node->type, $firstDefinition->children));
        $t->same('definition', $firstDefinition->children[1]->children[0]->attr('text'));
        $t->same(['paragraph', 'bullet_list'], array_map(static fn ($node): string => $node->type, $secondDefinition->children));
        $t->same('Second block', $secondDefinition->children[0]->attr('text'));
        $t->same('Nested note', $secondDefinition->children[1]->children[0]->attr('text'));
        $t->same('Term beta', $secondItem->attr('term'));
        $t->same('Final definition', $secondItem->children[1]->children[0]->attr('text'));
        $t->same('After glossary.', $document->children[1]->attr('text'));
    };

$tests['imports generated current html details summary fixture as visible blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-details-summary-raw-block.html'));
        $summary = $document->children[0];
        $firstBody = $document->children[1];
        $secondBody = $document->children[2];
        $after = $document->children[3];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph', 'paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Show imported source notes', $summary->attr('text'));
        $t->same('Details body keeps emphasis inside the raw disclosure.', $firstBody->attr('text'));
        $t->same(['text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $firstBody->children));
        $t->same('emphasis', $firstBody->children[1]->children[0]->attr('text'));
        $t->same('Second note keeps strong context.', $secondBody->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $secondBody->children));
        $t->same('strong', $secondBody->children[1]->children[0]->attr('text'));
        $t->same('After disclosure.', $after->attr('text'));

        $inlineSummary = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<details><summary><strong>Sum</strong></summary><p>Body</p></details>');
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $inlineSummary->children));
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $inlineSummary->children[0]->children));
        $t->same('Sum', $inlineSummary->children[0]->children[0]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html template content as visible blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-template-raw-boundary.html'));
        $fallback = $document->children[0];
        $after = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Fallback content', $fallback->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $fallback->children));
        $t->same('Fallback ', $fallback->children[0]->attr('text'));
        $t->same('content', $fallback->children[1]->children[0]->attr('text'));
        $t->same('After template.', $after->attr('text'));
    };

$tests['imports standalone text html assets as visible paragraphs'] =
    static function (TestRunner $t): void {
        $source = <<<'HTML'
/* Template styles */
$if(document-css)$
body { max-width: 36em; }
$endif$
HTML;
        $document = (new HtmlReader())->read($source);
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = (new HtmlWriter())->write($document);

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->contains('Template styles', $document->children[0]->attr('text'));
        $t->contains('body { max-width: 36em; }', $document->children[0]->attr('text'));
        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->true(!str_contains($blocks, '<!-- wp:html -->'), 'Standalone text fallback should not require a Custom HTML block.');
        $t->contains('<p>/* Template styles */', $html);
    };

$tests['imports direct pandoc html xmp rawtext fallback as parsed blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-xmp-rawtext-fallback.html'));
        $before = $document->children[0];
        $fallback = $document->children[1];
        $list = $document->children[2];
        $after = $document->children[3];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('HTML XMP Rawtext Fallback Import', $document->attr('meta')['title']);
        $t->same(
            ['paragraph', 'paragraph', 'bullet_list', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before legacy literal.', $before->attr('text'));
        $t->same('Escaped source becomes parsed fallback.', $fallback->attr('text'));
        $t->same(['strong', 'text'], array_map(static fn ($node): string => $node->type, $fallback->children));
        $t->same('Escaped source', $fallback->children[0]->children[0]->attr('text'));
        $t->same(' becomes parsed fallback.', $fallback->children[1]->attr('text'));
        $t->same('alpha', $list->children[0]->attr('text'));
        $t->same('beta', $list->children[1]->attr('text'));
        $t->same('After legacy literal.', $after->attr('text'));
    };

$tests['imports direct pandoc html rawtext fallback containers as parsed blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-rawtext-fallback-containers.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            [
                'Before raw fallback.',
                'Noembed fallback',
                'Noframes fallback',
                'Plain fallback',
                'After plaintext.',
            ],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $document->children[1]->children));
        $t->same('Noembed ', $document->children[1]->children[0]->attr('text'));
        $t->same('fallback', $document->children[1]->children[1]->children[0]->attr('text'));
        $t->same(['text', 'emph'], array_map(static fn ($node): string => $node->type, $document->children[2]->children));
        $t->same('Noframes ', $document->children[2]->children[0]->attr('text'));
        $t->same('fallback', $document->children[2]->children[1]->children[0]->attr('text'));
        $t->same(['text', 'code'], array_map(static fn ($node): string => $node->type, $document->children[3]->children));
        $t->same('Plain ', $document->children[3]->children[0]->attr('text'));
        $t->same('fallback', $document->children[3]->children[1]->attr('text'));
    };

$tests['extracts html reader microdata item metadata with itemref and nested item scopes'] =
    static function (TestRunner $t): void {
        $html = '<!doctype html><html><head><title>Microdata Dispatch</title></head><body>'
            . '<article id="post" itemscope itemtype="https://schema.org/Article" itemid="/posts/42" itemref="extra missing">'
            . '<h1 itemprop="headline">Launch Notes</h1>'
            . '<a itemprop="url mainEntityOfPage" href="/posts/42">Canonical</a>'
            . '<img itemprop="image" src="/media/cover.jpg" alt="Cover">'
            . '<time itemprop="datePublished" datetime="2026-06-25">today</time>'
            . '<div id="author" itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name">Ada Lovelace</span></div>'
            . '<section id="comment" itemscope itemtype="https://schema.org/Comment"><span itemprop="text">Nested unrelated</span></section>'
            . '</article>'
            . '<p id="extra"><span itemprop="keywords">migration, html</span></p>'
            . '</body></html>';

        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html);
        $meta = $document->attr('meta');
        $items = $meta['htmlMicrodataItems'];
        $article = $items[0];
        $articleProperties = $article['properties'];
        $authorItem = $items[1];
        $commentItem = $items[2];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('Microdata Dispatch', $meta['title']);
        $t->same('parsed', $meta['htmlMicrodataParseStatus']);
        $t->same('html-microdata-metadata-only', $meta['htmlMicrodataReviewPolicy']);
        $t->same(3, $meta['htmlMicrodataItemCount']);
        $t->same(3, $meta['htmlMicrodataReportedItemCount']);
        $t->same(1, $meta['htmlMicrodataTopLevelItemCount']);
        $t->same([0], $meta['htmlMicrodataTopLevelItemIndexes']);
        $t->same(8, $meta['htmlMicrodataPropertyCount']);
        $t->same(
            ['headline', 'url', 'mainEntityOfPage', 'image', 'datePublished', 'author', 'keywords', 'name', 'text'],
            $meta['htmlMicrodataPropertyNames']
        );
        $t->same(['missing-itemref:missing'], $meta['htmlMicrodataDiagnostics']);

        $t->same('article', $article['elementName']);
        $t->same('post', $article['elementId']);
        $t->same(['https://schema.org/Article'], $article['itemTypes']);
        $t->same('/posts/42', $article['itemId']);
        $t->same(['extra', 'missing'], $article['itemrefIds']);
        $t->same(['missing'], $article['missingItemrefIds']);
        $t->same(6, $article['propertyCount']);
        $t->same(
            ['headline', 'url', 'mainEntityOfPage', 'image', 'datePublished', 'author', 'keywords'],
            $article['propertyNames']
        );
        $t->same([
            'headline' => 1,
            'url' => 1,
            'mainEntityOfPage' => 1,
            'image' => 1,
            'datePublished' => 1,
            'author' => 1,
            'keywords' => 1,
        ], $article['propertyNameCounts']);

        $t->same(['headline'], $articleProperties[0]['names']);
        $t->same('Launch Notes', $articleProperties[0]['value']);
        $t->same('text', $articleProperties[0]['valueSource']);
        $t->same(['url', 'mainEntityOfPage'], $articleProperties[1]['names']);
        $t->same('/posts/42', $articleProperties[1]['value']);
        $t->same('href', $articleProperties[1]['valueSource']);
        $t->same('/media/cover.jpg', $articleProperties[2]['value']);
        $t->same('src', $articleProperties[2]['valueSource']);
        $t->same('2026-06-25', $articleProperties[3]['value']);
        $t->same('datetime', $articleProperties[3]['valueSource']);
        $t->same('item', $articleProperties[4]['valueType']);
        $t->same(['https://schema.org/Person'], $articleProperties[4]['item']['itemTypes']);
        $t->same('author', $articleProperties[4]['item']['elementId']);
        $t->same('Ada Lovelace', $articleProperties[4]['item']['text']);
        $t->same('migration, html', $articleProperties[5]['value']);
        $t->same('text', $articleProperties[5]['valueSource']);

        $t->same('div', $authorItem['elementName']);
        $t->same(['name'], $authorItem['propertyNames']);
        $t->same('Ada Lovelace', $authorItem['properties'][0]['value']);
        $t->same('section', $commentItem['elementName']);
        $t->same(['text'], $commentItem['propertyNames']);
        $t->same('Nested unrelated', $commentItem['properties'][0]['value']);
    };

$tests['resolves html reader microdata url values against document base'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<!doctype html><html><head><base href="https://source.example.test/import/posts/"></head><body>'
            . '<article itemscope>'
            . '<a itemprop="url" href="../review/source.html">source</a>'
            . '<img itemprop="image" src="media/cover.jpg" alt="cover">'
            . '<object itemprop="downloadUrl" data="/exports/archive.zip"></object>'
            . '<source itemprop="contentUrl" src="//cdn.example.test/video.mp4">'
            . '<track itemprop="caption" src="#captions">'
            . '<a itemprop="variant" href="?edition=full">variant</a>'
            . '</article>'
            . '</body></html>'
        );
        $properties = $document->attr('meta')['htmlMicrodataItems'][0]['properties'];

        $t->same('https://source.example.test/import/review/source.html', $properties[0]['value']);
        $t->same('href', $properties[0]['valueSource']);
        $t->same('url', $properties[0]['valueType']);
        $t->same('https://source.example.test/import/posts/media/cover.jpg', $properties[1]['value']);
        $t->same('src', $properties[1]['valueSource']);
        $t->same('url', $properties[1]['valueType']);
        $t->same('https://source.example.test/exports/archive.zip', $properties[2]['value']);
        $t->same('data', $properties[2]['valueSource']);
        $t->same('url', $properties[2]['valueType']);
        $t->same('https://cdn.example.test/video.mp4', $properties[3]['value']);
        $t->same('src', $properties[3]['valueSource']);
        $t->same('url', $properties[3]['valueType']);
        $t->same('https://source.example.test/import/posts/#captions', $properties[4]['value']);
        $t->same('src', $properties[4]['valueSource']);
        $t->same('url', $properties[4]['valueType']);
        $t->same('https://source.example.test/import/posts/?edition=full', $properties[5]['value']);
        $t->same('href', $properties[5]['valueSource']);
        $t->same('url', $properties[5]['valueType']);
    };

$tests['consumes upstream html doc-endnotes container after resolving doc-noteref note'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-doc-noteref-footnotes.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $note = $paragraph->children[1];
        $noteParagraph = $note->children[0];

        $t->same('doc-endnotes-containers-consumed-after-note-resolution', $meta['htmlFootnoteContainerPolicy']);
        $t->same(1, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text', 'note', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $note->children));
        $t->same('Editor note with source context.', $noteParagraph->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $noteParagraph->children));
        $t->same('source context', $noteParagraph->children[1]->children[0]->attr('text'));
    };

$tests['can preserve resolved html doc-endnotes containers for epub-compatible content reads'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE, 'htmlConsumeFootnoteContainers' => false]))
            ->read($fixture('upstream-html-doc-noteref-footnotes.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $container = $document->children[1];

        $t->same(0, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(['paragraph', 'div'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text', 'note', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('footnotes', $container->attr('id'));
        $t->same('doc-endnotes', $container->attr('htmlAttributes')['role'] ?? null);
    };

$tests['resolves upstream html doc-noteref notes in table placements and consumes endnotes'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-doc-noteref-table-placement.html'));
        $meta = $document->attr('meta');
        $firstParagraphNote = $document->children[1]->children[1];
        $table = $document->children[2];
        $captionInlines = $table->attr('captionInlines');
        $headCell = $table->children[0]->children[0]->children[0];
        $bodyCell = $table->children[1]->children[0]->children[0];
        $lastParagraphNote = $document->children[4]->children[1];

        $t->same(1, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(
            ['heading', 'paragraph', 'table', 'heading', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('doc footnote', $firstParagraphNote->children[0]->attr('text'));
        $t->same(['center'], $table->attr('alignments'));
        $t->same('width:17%', $table->attr('attributes')['style'] ?? null);
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $captionInlines));
        $t->same('caption footnote', $captionInlines[1]->children[0]->attr('text'));
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $headCell->children));
        $t->same('header footnote', $headCell->children[1]->children[0]->attr('text'));
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $bodyCell->children));
        $t->same('table cell footnote', $bodyCell->children[1]->children[0]->attr('text'));
        $t->same('doc footnote', $lastParagraphNote->children[0]->attr('text'));
    };

$tests['imports generated current html table foot rows'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-table-foot.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $foot = $table->children[2];

        $t->same(['table', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Quarterly totals', $table->attr('caption'));
        $t->same(['audit-grid'], $table->attr('classes'));
        $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same(['Quarter', 'Total'], array_map(static fn ($cell): string => $cell->attr('text'), $head->children[0]->children));
        $t->same(2, count($body->children));
        $t->same(['Q2', '18'], array_map(static fn ($cell): string => $cell->attr('text'), $body->children[1]->children));
        $t->same(['Combined', '30'], array_map(static fn ($cell): string => $cell->attr('text'), $foot->children[0]->children));
        $t->same('After table.', $document->children[1]->attr('text'));
    };

$tests['imports generated current html multiple tbody row header columns'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-multi-tbody-row-header-table.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $firstBody = $table->children[1];
        $secondBody = $table->children[2];
        $geometry = $table->attr('tableGeometry');

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['table_head', 'table_body', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same(['Region', 'Metric', 'Q1', 'Q2'], array_map(static fn ($cell): string => $cell->attr('text'), $head->children[0]->children));
        $t->same(2, $firstBody->attr('rowHeadColumns'));
        $t->same(1, $secondBody->attr('rowHeadColumns'));
        $t->same(['North', '12', '15'], array_map(static fn ($cell): string => $cell->attr('text'), $firstBody->children[0]->children));
        $t->same(2, $firstBody->children[0]->children[0]->attr('colspan'));
        $t->same(['Ops', 'Latency', '4', '3'], array_map(static fn ($cell): string => $cell->attr('text'), $secondBody->children[1]->children));
        $t->same(2, $geometry['summary']['rowHeadGroupCount'] ?? null);
        $t->same([2, 1], $geometry['summary']['rowHeadColumnCounts'] ?? null);
        $t->same(true, $geometry['summary']['hasDifferingRowHeadColumns'] ?? null);
    };

$tests['imports direct pandoc html implicit tbody row header table'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-implicit-tbody-table.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $row = $body->children[0];

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same([], $head->children);
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same(['Item', 'Count'], array_map(static fn ($cell): string => $cell->attr('text'), $row->children));
    };

$tests['imports direct pandoc html table row and column spans'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-table-row-col-span.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $headRow = $head->children[0];
        $firstBodyRow = $body->children[0];
        $secondBodyRow = $body->children[1];

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same('Span audit', $table->attr('caption'));
        $t->same(1, $body->attr('rowHeadColumns'));
        $t->same(['Region', 'Totals'], array_map(static fn ($cell): string => $cell->attr('text'), $headRow->children));
        $t->same(2, $headRow->children[1]->attr('colspan'));
        $t->same(['North', 'Q1', '12'], array_map(static fn ($cell): string => $cell->attr('text'), $firstBodyRow->children));
        $t->same(2, $firstBodyRow->children[0]->attr('rowspan'));
        $t->same(['Q2', '18'], array_map(static fn ($cell): string => $cell->attr('text'), $secondBodyRow->children));
    };

$tests['imports direct pandoc html colgroup column widths'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-colgroup-width-table.html'));
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $bodyRow = $body->children[0];
        $valueCell = $bodyRow->children[1];
        $columnSpecs = $table->attr('columnSpecs');
        $columnSources = $table->attr('columnSources');

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Column layout', $table->attr('caption'));
        $t->same(['default', 'default'], $table->attr('alignments'));
        $t->same([0.25, 0.75], $table->attr('widths'));
        $t->same([0.25, 0.75], array_map(static fn (array $spec): float => $spec['width'], $columnSpecs));
        $t->same(['col', 'col'], array_map(static fn (array $source): string => $source['kind'], $columnSources));
        $t->same('width: 25%', $columnSources[0]['colAttributes']['attributes']['style'] ?? null);
        $t->same('width: 75%', $columnSources[1]['colAttributes']['attributes']['style'] ?? null);
        $t->same(['Metric', 'Value'], array_map(static fn ($cell): string => $cell->attr('text'), $head->children[0]->children));
        $t->same(['Latency', '42 ms'], array_map(static fn ($cell): string => $cell->attr('text'), $bodyRow->children));
        $t->same(['strong', 'text'], array_map(static fn ($node): string => $node->type, $valueCell->children));
    };

$tests['imports upstream html block children inside table cells'] =
    static function (TestRunner $t): void {
        $html = '<table><tr><td><ul><li>one</li><li>two</li></ul></td><td><blockquote><p>quote</p></blockquote></td></tr></table>';
        $fullDocument = '<!doctype html><html><body>' . $html . '</body></html>';
        $assertTable = static function ($document) use ($t): void {
            $table = $document->children[0];
            $body = $table->children[1];
            $row = $body->children[0];
            $listCell = $row->children[0];
            $quoteCell = $row->children[1];
            $list = $listCell->children[0];
            $quote = $quoteCell->children[0];

            $t->same('table', $table->type);
            $t->same('bullet_list', $list->type);
            $t->same(['one', 'two'], array_map(static fn ($item): string => $item->attr('text'), $list->children));
            $t->same('blockquote', $quote->type);
            $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $quote->children));
            $t->same('quote', $quote->children[0]->attr('text'));
        };

        $assertTable((new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html));
        $assertTable((new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fullDocument));
    };

$tests['imports direct pandoc html invalid table children as visible blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $html = $fixture('upstream-html-invalid-table-children.html');
        $assertBlocks = static function ($document, string $label) use ($t): void {
            $t->same(
                ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'],
                array_map(static fn ($node): string => $node->type, $document->children),
                $label
            );
            $t->same(
                ['loose', 'A', 'tail', 'B', 'after'],
                array_map(static fn ($node): string => $node->attr('text'), $document->children),
                $label
            );
        };

        $assertBlocks((new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html), 'fragment');
        $assertBlocks((new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<!doctype html><html><body>' . $html . '</body></html>'), 'document');
    };

$tests['imports direct pandoc html foster-parented table text in source order'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-foster-parent-table-text.html'));

        $t->same(
            ['paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['A', 'loose', 'tail'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
    };

$tests['imports direct pandoc html orphan table fragment tree construction as visible blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-orphan-table-fragment-tree-construction.html'));

        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['A', 'B', 'C', 'after'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
    };

$tests['imports direct pandoc html paragraph table tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-table-tree-construction.html'));
        $table = $document->children[1];
        $body = $table->children[1];
        $row = $body->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'table', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
        $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same(['Cell'], array_map(static fn ($cell): string => $cell->attr('text'), $row->children));
    };

$tests['imports direct pandoc html paragraph blockquote tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-blockquote-tree-construction.html'));
        $quote = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'blockquote', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $quote->children));
        $t->same('Quoted', $quote->children[0]->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html nested paragraph tree construction as repaired paragraphs'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-nested-paragraph-tree-construction.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['one', 'two', 'three'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
    };

$tests['imports direct pandoc html paragraph heading tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-heading-tree-construction.html'));
        $heading = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'heading', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same(2, $heading->attr('level'));
        $t->same('title', $heading->attr('id'));
        $t->same('Title', $heading->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph hr tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-hr-tree-construction.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'horizontal_rule', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same([], $document->children[1]->attrs);
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph pre tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-pre-tree-construction.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'code_block', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same('code', $document->children[1]->attr('text'));
        $t->same([], $document->children[1]->attr('attributes'));
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph fieldset tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-fieldset-tree-construction.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['one', 'Legend', 'body', 'tail'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
    };

$tests['imports direct pandoc html paragraph list tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-block-tree-construction.html'));
        $list = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'bullet_list', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same(['one', 'two'], array_map(static fn ($item): string => $item->attr('text'), $list->children));
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $list->children[1]->children));
        $t->same('two', $list->children[1]->children[0]->children[0]->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html menu item tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-menu-item-tree-construction.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['Before', 'Alpha one', 'Beta two', 'After'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
        $t->same(['text', 'emph'], array_map(static fn ($node): string => $node->type, $document->children[1]->children));
        $t->same('Alpha ', $document->children[1]->children[0]->attr('text'));
        $t->same('one', $document->children[1]->children[1]->children[0]->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $document->children[2]->children));
        $t->same('Beta ', $document->children[2]->children[0]->attr('text'));
        $t->same('two', $document->children[2]->children[1]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html paragraph section tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-section-tree-construction.html'));
        $section = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('Dom\\HTMLDocument', $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'div', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('one', $document->children[0]->attr('text'));
        $t->same(['section'], $section->attr('classes'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $section->children));
        $t->same('two', $section->children[0]->attr('text'));
        $t->same('three', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph article tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-article-tree-construction.html'));
        $inside = $document->children[1];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same('Inside article', $inside->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $inside->children));
        $t->same('Inside ', $inside->children[0]->attr('text'));
        $t->same('article', $inside->children[1]->children[0]->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph aside tree construction as repaired native div'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-aside-tree-construction.html'));
        $aside = $document->children[1];
        $asideParagraph = $aside->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'div', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same(['aside'], $aside->attr('classes'));
        $t->same(['class' => 'aside'], $aside->attr('htmlAttributes'));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $aside->children));
        $t->same('Note inside', $asideParagraph->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $asideParagraph->children));
        $t->same('inside', $asideParagraph->children[1]->children[0]->attr('text'));
        $t->same('After', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html paragraph nav tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-nav-tree-construction.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['Before', 'Links', 'After'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
    };

$tests['imports direct pandoc html paragraph transparent block tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-paragraph-transparent-block-tree-construction.html'));
        $searchBody = $document->children[1];
        $heading = $document->children[3];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'heading', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before', $document->children[0]->attr('text'));
        $t->same('Find term', $searchBody->attr('text'));
        $t->same(['text', 'strong'], array_map(static fn ($node): string => $node->type, $searchBody->children));
        $t->same('term', $searchBody->children[1]->children[0]->attr('text'));
        $t->same('Middle', $document->children[2]->attr('text'));
        $t->same(1, $heading->attr('level'));
        $t->same('Title', $heading->attr('text'));
        $t->same('After', $document->children[4]->attr('text'));
    };

$tests['imports direct pandoc html button scope paragraph tree construction as repaired blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-button-scope-tree-construction.html'));

        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('one', $document->children[0]->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $document->children[0]->children));
        $t->same('twothree', $document->children[1]->attr('text'));
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $document->children[1]->children));
    };

$tests['preserves upstream html omitted table cell closures as structured table'] =
    static function (TestRunner $t): void {
        $html = '<table><tbody><tr><td>A<td>B</tbody></table><p>after</p>';
        $explicit = '<table><tbody><tr><td>A</td><td>B</td></tr></tbody></table>';
        $assertBlocks = static function ($document, string $label) use ($t): void {
            $t->same(
                ['table', 'paragraph'],
                array_map(static fn ($node): string => $node->type, $document->children),
                $label
            );

            $table = $document->children[0];
            $body = $table->children[1];
            $row = $body->children[0];
            $t->same(['table_head', 'table_body'], array_map(static fn ($node): string => $node->type, $table->children), $label);
            $t->same(['table_row'], array_map(static fn ($node): string => $node->type, $body->children), $label);
            $t->same(['A', 'B'], array_map(static fn ($node): string => $node->attr('text'), $row->children), $label);
            $t->same('after', $document->children[1]->attr('text'), $label);
        };

        $assertBlocks((new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html), 'fragment');
        $assertBlocks((new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<!doctype html><html><body>' . $html . '</body></html>'), 'document');
        $t->same('table', (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($explicit)->children[0]->type);
        $t->same('table', (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<!doctype html><html><body>' . $explicit . '</body></html>')->children[0]->type);
    };

$tests['skips upstream html reader empty tables after HTMLDocument tree construction'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-empty-tables.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(Html5Dom::htmlDocumentTreeConstructionBackend(), $document->attr('meta')['htmlTreeConstruction'] ?? null);
        $t->same(
            ['heading', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Empty Tables', $document->children[0]->attr('text'));
        $t->same('This section should be empty.', $document->children[1]->attr('text'));
    };

$tests['imports upstream html transparent block containers as structural children'] =
    static function (TestRunner $t): void {
        $tags = ['address', 'article', 'center', 'dialog', 'dir', 'fieldset', 'footer', 'form', 'hgroup', 'menu', 'nav', 'search', 'summary'];
        $assertBlocks = static function ($document, string $label) use ($t): void {
            $t->same(['heading', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children), $label);
            $t->same('Title', $document->children[0]->attr('text'), $label);
            $t->same('Body text.', $document->children[1]->attr('text'), $label);
            $t->same(['text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $document->children[1]->children), $label);
            $t->same('text', $document->children[1]->children[1]->children[0]->attr('text'), $label);
            $t->same('After', $document->children[2]->attr('text'), $label);
        };

        foreach ($tags as $tag) {
            $html = '<' . $tag . ' id="wrapper"><h1>Title</h1><p>Body <em>text</em>.</p></' . $tag . '><p>After</p>';
            $assertBlocks((new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html), $tag . ' fragment');
            $assertBlocks((new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<!doctype html><html><body>' . $html . '</body></html>'), $tag . ' document');
        }
    };

$tests['imports generated current html thematic break as horizontal rule'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-thematic-break.html'));

        $t->same(
            ['paragraph', 'horizontal_rule', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Before rule.', $document->children[0]->attr('text'));
        $t->same([], $document->children[1]->attrs);
        $t->same('After rule.', $document->children[2]->attr('text'));
    };

$tests['imports html fragment hr variants as horizontal rules'] =
    static function (TestRunner $t): void {
        foreach (['<hr>', '<hr />', '<hr class="x" id="y">'] as $html) {
            $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($html);

            $t->same(['horizontal_rule'], array_map(static fn ($node): string => $node->type, $document->children), $html);
            $t->same([], $document->children[0]->attrs, $html);
        }
    };

$tests['imports direct pandoc html omitted heading end-tag fixture'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-omitted-heading-end-tags.html'));

        $t->same(['heading', 'heading', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('Title', $document->children[0]->attr('text'));
        $t->same(2, $document->children[1]->attr('level'));
        $t->same('Next heading', $document->children[1]->attr('text'));
        $t->same(['text', 'emph'], array_map(static fn ($node): string => $node->type, $document->children[1]->children));
        $t->same('Body.', $document->children[2]->attr('text'));
    };

$tests['imports html ordered lists with signed start values'] =
    static function (TestRunner $t): void {
        foreach ([['0', 0], ['-2', -2], ['+3', 3]] as [$rawStart, $expectedStart]) {
            $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read('<ol type="1" start="' . $rawStart . '"><li>Item</li></ol>');
            $list = $document->children[0];

            $t->same('ordered_list', $list->type, $rawStart);
            $t->same($expectedStart, $list->attr('start'), $rawStart);
            $t->same('decimal', $list->attr('style'), $rawStart);
            $t->same('Item', $list->children[0]->attr('text'), $rawStart);
        }
    };

$tests['imports direct pandoc html ordered list type and start fixture'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-ordered-list-type-start.html'));
        $list = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['ordered_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(3, $list->attr('start'));
        $t->same('upper_alpha', $list->attr('style'));
        $t->same(['Alpha', 'Beta'], array_map(static fn ($item): string => $item->attr('text'), $list->children));
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $list->children[1]->children));
        $t->same('Beta', $list->children[1]->children[0]->children[0]->attr('text'));
    };

$tests['imports direct pandoc html optional list item end-tag tree construction'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-optional-list-item-tree-construction.html'));
        $list = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['bullet_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['alpha', 'beta', 'gamma'],
            array_map(static fn ($item): string => $item->attr('text'), $list->children)
        );
        $t->same(['text'], array_map(static fn ($node): string => $node->type, $list->children[0]->children));
        $t->same(['strong'], array_map(static fn ($node): string => $node->type, $list->children[1]->children));
        $t->same('beta', $list->children[1]->children[0]->children[0]->attr('text'));
    };

$tests['imports generated current html ruby annotation text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-ruby-annotation.html'));
        $paragraph = $document->children[0];

        $t->same(
            ['paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('HTML Ruby Annotation Import', $document->attr('meta')['title']);
        $t->same('Japanese ' . "\u{6F22}" . '(kan) annotation.', $paragraph->attr('text'));
        $t->same(
            ['Japanese ', "\u{6F22}", '(', 'kan', ')', ' annotation.'],
            array_map(static fn ($node): string => $node->attr('text'), $paragraph->children)
        );
        $t->same('After ruby.', $document->children[1]->attr('text'));
    };

$tests['imports generated current html keyboard sample and variable inline semantics'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-kbd-samp-var-inline.html'));
        $paragraph = $document->children[0];

        $t->same(
            ['paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('HTML Kbd Samp Var Import', $document->attr('meta')['title']);
        $t->same('Press Cmd and inspect stdout with name.', $paragraph->attr('text'));
        $t->same(
            ['text', 'span', 'text', 'code', 'text', 'code', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same(['kbd'], $paragraph->children[1]->attr('classes'));
        $t->same('Cmd', $paragraph->children[1]->children[0]->attr('text'));
        $t->same(['sample'], $paragraph->children[3]->attr('classes'));
        $t->same('stdout', $paragraph->children[3]->attr('text'));
        $t->same(['variable'], $paragraph->children[5]->attr('classes'));
        $t->same('name', $paragraph->children[5]->attr('text'));
        $t->same('After inline semantics.', $document->children[1]->attr('text'));
    };

$tests['imports generated current html form control visible text semantics'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-form-controls.html'));

        $t->same(
            ['paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('HTML Form Controls Import', $document->attr('meta')['title']);
        $t->same('Title', $document->children[0]->attr('text'));
        $t->same('DraftReady', $document->children[1]->attr('text'));
        $t->same(
            ['text', 'text'],
            array_map(static fn ($node): string => $node->type, $document->children[1]->children)
        );
        $t->same('Draft', $document->children[1]->children[0]->attr('text'));
        $t->same('Ready', $document->children[1]->children[1]->attr('text'));
        $t->same('After form.', $document->children[2]->attr('text'));
    };

$tests['imports direct pandoc html nested form tree construction as sibling paragraphs'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-form-in-form-tree-construction.html'));

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(
            ['paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['one', 'two', 'three'],
            array_map(static fn ($node): string => $node->attr('text'), $document->children)
        );
    };

$tests['imports direct pandoc html standalone select optgroup fragment as plain'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-standalone-select-optgroup-inline.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('DraftReady done', $plain->attr('text'));
        $t->same(['text', 'text', 'text'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same(['Draft', 'Ready', ' done'], array_map(static fn ($node): string => $node->attr('text'), $plain->children));
    };

$tests['imports direct pandoc html omitted option end tags as repaired select text'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-optional-option-tree-construction.html'));
        $plain = $document->children[0];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same(['plain'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('DraftReady done', $plain->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $plain->children));
        $t->same('Draft', $plain->children[0]->attr('text'));
        $t->same('Ready', $plain->children[1]->children[0]->attr('text'));
        $t->same(' done', $plain->children[2]->attr('text'));
    };

$tests['imports html result control fragments without swallowing following block boundary'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<output name="total">Calculated <strong>total</strong></output><p>After output.</p>' . "\n"
                . '<select name="status"><option>Draft</option><option selected>Ready</option></select><p>After select.</p>'
        );

        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('Calculated total', $document->children[0]->attr('text'));
        $t->same(
            ['text', 'strong'],
            array_map(static fn ($node): string => $node->type, $document->children[0]->children)
        );
        $t->same('Calculated ', $document->children[0]->children[0]->attr('text'));
        $t->same('total', $document->children[0]->children[1]->children[0]->attr('text'));
        $t->same('After output.', $document->children[1]->attr('text'));
        $t->same('DraftReady', $document->children[2]->attr('text'));
        $t->same(
            ['text', 'text'],
            array_map(static fn ($node): string => $node->type, $document->children[2]->children)
        );
        $t->same('Draft', $document->children[2]->children[0]->attr('text'));
        $t->same('Ready', $document->children[2]->children[1]->attr('text'));
        $t->same('After select.', $document->children[3]->attr('text'));
    };

$tests['imports generated current html address block as paragraph content'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read($fixture('upstream-html-address-block.html'));
        $contact = $document->children[0];
        $link = $contact->children[2];

        $t->same(
            ['paragraph', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('HTML Address Block Import', $document->attr('meta')['title']);
        $t->same('Migration Desk migration@example.test', $contact->attr('text'));
        $t->same(
            ['strong', 'linebreak', 'link'],
            array_map(static fn ($node): string => $node->type, $contact->children)
        );
        $t->same('Migration Desk', $contact->children[0]->children[0]->attr('text'));
        $t->same('mailto:migration@example.test', $link->attr('url'));
        $t->same('migration@example.test', $link->children[0]->attr('text'));
        $t->same('After contact.', $document->children[1]->attr('text'));
    };

$tests['preserves html doc-endnotes container when no noteref was resolved'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<section role="doc-endnotes"><ol><li id="fn1"><p>Loose footnote body.</p></li></ol></section>'
        );
        $meta = $document->attr('meta');
        $container = $document->children[0];

        $t->same(0, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(['div'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('doc-endnotes', $container->attr('htmlAttributes')['role'] ?? null);
        $t->same('ordered_list', $container->children[0]->type);
        $t->same('Loose footnote body.', $container->children[0]->children[0]->attr('text'));
    };

$tests['imports presentation mathml as pandoc math nodes'] =
    static function (TestRunner $t): void {
        $html = <<<'HTML'
<p><math><msup><mi>x</mi><mn>2</mn></msup></math></p>
<p><math display="block"><mfrac><mn>1</mn><mn>2</mn></mfrac></math></p>
<p><math><msub><mi>a</mi><mi>i</mi></msub><mo>+</mo><msubsup><mi>b</mi><mn>1</mn><mn>2</mn></msubsup></math></p>
<p><math><msqrt><mi>x</mi></msqrt><mo>+</mo><mroot><mi>y</mi><mn>3</mn></mroot></math></p>
<p><math><mo>∫</mo><msubsup><mi>x</mi><mn>0</mn><mn>1</mn></msubsup><mo>+</mo><mi>α</mi><mo>×</mo><mi>ω</mi></math></p>
<p><math><mfenced open="[" close="]" separators=";"><mi>a</mi><mi>b</mi></mfenced></math></p>
<p><math><munderover><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover></math></p>
<p><math><mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable></math></p>
HTML;
        $matrix = trim(<<<'TEX'
\begin{matrix}
a & b \\
c & d
\end{matrix}
TEX);

        foreach ([
            'tagsoup' => new HtmlReader(),
            'html-document-bridge' => new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]),
        ] as $label => $reader) {
            $document = $reader->read($html);
            $mathNodes = [];
            foreach ($document->children as $block) {
                foreach ($block->children as $inline) {
                    if ($inline->type === 'math') {
                        $mathNodes[] = $inline;
                        break;
                    }
                }
            }

            $t->same([
                'x^{2}',
                '\frac{1}{2}',
                'a_{i} + b_{1}^{2}',
                '\sqrt{x} + \sqrt[3]{y}',
                '\int x_{0}^{1} + \alpha \times \omega',
                '\left\lbrack {a;b} \right\rbrack',
                '\sum\limits_{i = 1}^{n}',
                $matrix,
            ], array_map(static fn ($node): string => (string) $node->attr('text'), $mathNodes), $label);
            $t->same(
                [false, true, false, false, false, false, false, false],
                array_map(static fn ($node): bool => (bool) $node->attr('display'), $mathNodes),
                $label
            );
        }
    };

$valueSourceCases = [
    'data value attribute' => [
        '<data itemprop="ratingValue" value="4.5">four and half</data>',
        'ratingValue',
        '4.5',
        'value',
        'string',
    ],
    'meter value attribute' => [
        '<meter itemprop="confidence" value="0.82">82%</meter>',
        'confidence',
        '0.82',
        'value',
        'string',
    ],
    'object data url' => [
        '<object itemprop="downloadUrl" data="/review.bin"></object>',
        'downloadUrl',
        '/review.bin',
        'data',
        'url',
    ],
    'time text fallback' => [
        '<time itemprop="reviewedAt">June 25</time>',
        'reviewedAt',
        'June 25',
        'text',
        'string',
    ],
    'meta empty content' => [
        '<meta itemprop="empty" content="">',
        'empty',
        '',
        'content',
        'string',
    ],
];

foreach ($valueSourceCases as $name => [$markup, $propertyName, $expectedValue, $expectedSource, $expectedType]) {
    $tests['extracts html reader microdata property value source ' . $name] =
        static function (TestRunner $t) use ($expectedSource, $expectedType, $expectedValue, $markup, $propertyName): void {
            $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
                '<!doctype html><html><body><section itemscope itemtype="https://schema.org/Review">'
                . $markup
                . '</section></body></html>'
            );
            $property = $document->attr('meta')['htmlMicrodataItems'][0]['properties'][0];

            $t->same([$propertyName], $property['names']);
            $t->same($expectedValue, $property['value']);
            $t->same($expectedSource, $property['valueSource']);
            $t->same($expectedType, $property['valueType']);
        };
}

$tests['keeps html reader imports alive when microdata dom parse is unavailable'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader(['htmlReaderBackend' => HtmlReader::BACKEND_HTML_DOCUMENT_MARKDOWN_BRIDGE]))->read(
            '<!doctype html [<!ENTITY review SYSTEM "file:///etc/passwd">]><html><body><p>Unsafe declaration source.</p></body></html>'
        );
        $meta = $document->attr('meta');

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('unavailable', $meta['htmlMicrodataParseStatus']);
        $t->same(0, $meta['htmlMicrodataItemCount']);
        $t->same(0, $meta['htmlMicrodataPropertyCount']);
        $t->same(['html-microdata-dom-parse-failed'], $meta['htmlMicrodataDiagnostics']);
    };

$tests['records html reader microdata metadata mapped-case count'] =
    static function (TestRunner $t) use ($valueSourceCases): void {
        $t->same(11, 1 + 4 + count($valueSourceCases) + 1);
    };

if (PHP_VERSION_ID < 80500) {
    $php85FragmentBridgeTests = [
        'reports html document tree construction backend for table-scope fragments',
        'imports direct pandoc html standalone sup and sub fragment as plain',
        'imports direct pandoc html standalone time fragment as plain',
        'imports direct pandoc html standalone progress fragment as plain blocks',
        'imports direct pandoc html standalone media fallback fragments as plain blocks',
        'imports direct pandoc html block fallback content containers without raw wrappers',
        'imports direct pandoc html inline fallback content containers without raw wrappers',
        'imports direct pandoc html standalone keyboard fragment as plain',
        'imports upstream html standalone code aliases as plain code inlines',
        'imports direct pandoc html standalone output fragment as plain',
        'imports direct pandoc html standalone underline fragment as plain',
        'imports direct pandoc html standalone semantic inline fragments as plain',
        'imports direct pandoc html standalone b and i aliases fixture as plain',
        'imports direct pandoc html standalone abbr and dfn fixture as plain spans',
        'imports direct pandoc html standalone bdo mark q fragment as plain',
        'imports direct pandoc html standalone bdi fragment as visible text',
        'imports upstream html self closing anchor without href as span',
        'imports upstream html standalone inline code aliases as plain blocks',
        'imports upstream html standalone image fragments as plain images',
        'imports upstream html picture fallback images without source text leakage',
        'imports direct pandoc html standalone emphasis strong span fragment as plain',
        'imports direct pandoc html main that closes paragraph through HTML5 tree construction',
        'imports direct pandoc html transparent inline fragments as plain text',
        'imports generated current html details summary fixture as visible blocks',
        'imports direct pandoc html template content as visible blocks',
        'imports direct pandoc html xmp rawtext fallback as parsed blocks',
        'imports direct pandoc html rawtext fallback containers as parsed blocks',
        'imports direct pandoc html orphan table fragment tree construction as visible blocks',
        'imports direct pandoc html standalone select optgroup fragment as plain',
        'imports direct pandoc html omitted option end tags as repaired select text',
    ];
    foreach ($php85FragmentBridgeTests as $name) {
        unset($tests[$name]);
    }
}

return $tests;
