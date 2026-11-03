<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Bounded, non-executing LaTeX reader for document-oriented source files.
 *
 * This deliberately parses a useful publishing subset rather than attempting
 * to implement TeX expansion, package loading, or a layout engine.
 */
final class LatexReader
{
    private const DEFAULT_MAX_SOURCE_BYTES = 16_777_216;
    private const DEFAULT_MAX_INCLUDE_DEPTH = 8;
    private const DEFAULT_MAX_INCLUDED_BYTES = 16_777_216;

    /** @var array<string, array{arity:int, template:string}> */
    private array $macros = [];

    /** @var array<string, array{id:string, display:string, kind:string}> */
    private array $labels = [];

    /** @var array<string, true> */
    private array $diagnosticSet = [];

    /** @var list<string> */
    private array $diagnostics = [];

    /** @var list<array{code:string,source:string,line:int,column:int,command?:string,environment?:string}> */
    private array $diagnosticDetails = [];

    /** @var array<string, true> */
    private array $diagnosticDetailSet = [];

    /** @var list<string> */
    private array $includedFiles = [];

    /** @var list<array{path:string, bytes:string}> */
    private array $bibliographyFiles = [];

    /** @var list<AstNode> */
    private array $abstractBlocks = [];

    /** @var list<AstNode> */
    private array $preambleRawBlocks = [];

    /** @var array{title:string, author:list<string>, date:string, documentClass:string, packages:list<string>, affiliations:list<string>, authorNotes:list<string>, keywords:list<string>} */
    private array $preambleMetadata = [
        'title' => '',
        'author' => [],
        'date' => '',
        'documentClass' => '',
        'packages' => [],
        'affiliations' => [],
        'authorNotes' => [],
        'keywords' => [],
    ];

    /** @var array<string, array{kind:string,label:string,counter:string,numbered:bool}> */
    private array $scholarlyEnvironments = [];

    private ?string $sourceDirectory = null;
    private ?string $includeRootDirectory = null;
    private string $activeSourceName = '<input>';
    private string $activeSourceText = '';
    private int $activeSourceLineOffset = 0;
    private int $includedBytes = 0;
    private bool $hasChapter = false;
    private bool $bibliographyRequested = false;
    private string $bibliographyStyle = '';

    /** @var array<string, int> */
    private array $referenceCounters = [
        'figure' => 0,
        'table' => 0,
        'equation' => 0,
        'theorem' => 0,
        'definition' => 0,
        'lemma' => 0,
        'proposition' => 0,
        'corollary' => 0,
        'remark' => 0,
        'example' => 0,
    ];

    /**
     * @param array{
     *     sourcePath?: string,
     *     sourceDirectory?: string,
     *     resourceBasePath?: string,
     *     maxSourceBytes?: int,
     *     maxIncludeDepth?: int,
     *     maxIncludedBytes?: int,
     *     resolveIncludes?: bool,
     *     resolveBibliography?: bool
     * } $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $source): AstNode
    {
        $this->resetState();
        $sourceBytes = strlen($source);
        if ($sourceBytes > $this->maxSourceBytes()) {
            throw new \InvalidArgumentException('LaTeX input exceeds the configured source-byte limit.');
        }

        $this->sourceDirectory = $this->configuredSourceDirectory();
        $this->includeRootDirectory = $this->sourceDirectory;
        $source = $this->stripUtf8Bom($source);
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $source = $this->stripCommentsOutsideLiteralEnvironments($source);
        $this->hasChapter = preg_match('/(?<!\\\\)\\\\chapter\*?\s*(?:\[[^\]]*\])?\s*\{/u', $source) === 1;
        $this->collectLabelIds($source);

        $this->activeSourceName = $this->sourceDisplayName($this->options['sourcePath'] ?? null);
        $this->activeSourceText = $source;
        $this->activeSourceLineOffset = 0;
        [$preamble, $body, $hasDocumentEnvironment, $bodyOffset] = $this->splitDocument($source);
        if (!$hasDocumentEnvironment) {
            $this->diagnostic('latex-document-environment-missing');
        }
        $preambleMetadata = $this->parsePreamble($preamble);
        $this->preambleMetadata = $preambleMetadata;
        $blocks = $this->parseBlocks(
            $body,
            0,
            $this->activeSourceName,
            substr_count(substr($source, 0, $bodyOffset), "\n")
        );
        if ($this->preambleRawBlocks !== []) {
            $blocks = array_merge($this->preambleRawBlocks, $blocks);
        }

        $meta = [
            'sourceFormat' => 'latex',
            'reader' => self::class,
            'readerScope' => 'bounded-native-latex-reader-semantic-document-import',
        ];
        foreach (['title', 'author', 'date', 'documentClass', 'packages', 'affiliations', 'authorNotes', 'keywords'] as $name) {
            if (($preambleMetadata[$name] ?? null) !== null && $preambleMetadata[$name] !== '' && $preambleMetadata[$name] !== []) {
                $meta[$name] = $preambleMetadata[$name];
            }
        }
        if ($this->abstractBlocks !== []) {
            $meta['abstract'] = $this->plainBlocksText($this->abstractBlocks);
        }

        $document = new AstNode('document', [
            'sourceFormat' => 'latex',
            'meta' => $meta,
            'latex' => [
                'reader' => self::class,
                'readerScope' => 'bounded-native-latex-reader-semantic-document-import',
                'sourceBytes' => $sourceBytes,
                'documentEnvironmentPresent' => $hasDocumentEnvironment,
                'documentClass' => $preambleMetadata['documentClass'] ?? '',
                'packages' => $preambleMetadata['packages'] ?? [],
                'includedFiles' => $this->includedFiles,
                'bibliographyFiles' => array_map(static fn (array $file): string => $file['path'], $this->bibliographyFiles),
                'bibliographyStyle' => $this->bibliographyStyle,
                'diagnostics' => $this->diagnostics,
                'diagnosticDetails' => $this->diagnosticDetails,
                'executionPolicy' => 'parse-only-no-tex-engine-package-loading-shell-escape-or-arbitrary-macro-execution',
            ],
        ], $blocks);

        $document = $this->replaceTocPlaceholders($document);
        $document = $this->resolveReferences($document);
        $document = $this->decorateMathWithLatexContext($document);
        $document = $this->applyResolvedBibliography($document);

        return $this->documentWithFinalDiagnostics($document);
    }

    private function resetState(): void
    {
        $this->macros = [];
        $this->labels = [];
        $this->diagnosticSet = [];
        $this->diagnostics = [];
        $this->diagnosticDetails = [];
        $this->diagnosticDetailSet = [];
        $this->includedFiles = [];
        $this->bibliographyFiles = [];
        $this->abstractBlocks = [];
        $this->preambleRawBlocks = [];
        $this->preambleMetadata = [
            'title' => '',
            'author' => [],
            'date' => '',
            'documentClass' => '',
            'packages' => [],
            'affiliations' => [],
            'authorNotes' => [],
            'keywords' => [],
        ];
        $this->scholarlyEnvironments = [];
        $this->sourceDirectory = null;
        $this->includeRootDirectory = null;
        $this->activeSourceName = '<input>';
        $this->activeSourceText = '';
        $this->activeSourceLineOffset = 0;
        $this->includedBytes = 0;
        $this->hasChapter = false;
        $this->bibliographyRequested = false;
        $this->bibliographyStyle = '';
        $this->referenceCounters = [
            'figure' => 0,
            'table' => 0,
            'equation' => 0,
            'theorem' => 0,
            'definition' => 0,
            'lemma' => 0,
            'proposition' => 0,
            'corollary' => 0,
            'remark' => 0,
            'example' => 0,
        ];
    }

    private function maxSourceBytes(): int
    {
        return max(1, (int) ($this->options['maxSourceBytes'] ?? self::DEFAULT_MAX_SOURCE_BYTES));
    }

    private function maxIncludeDepth(): int
    {
        return max(0, (int) ($this->options['maxIncludeDepth'] ?? self::DEFAULT_MAX_INCLUDE_DEPTH));
    }

    private function maxIncludedBytes(): int
    {
        return max(0, (int) ($this->options['maxIncludedBytes'] ?? self::DEFAULT_MAX_INCLUDED_BYTES));
    }

    private function includesEnabled(): bool
    {
        return ($this->options['resolveIncludes'] ?? true) !== false;
    }

    private function bibliographyResolutionEnabled(): bool
    {
        return ($this->options['resolveBibliography'] ?? true) !== false;
    }

    private function configuredSourceDirectory(): ?string
    {
        $candidate = $this->options['sourceDirectory']
            ?? $this->options['resourceBasePath']
            ?? $this->options['sourcePath']
            ?? null;
        if (!is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = trim($candidate);
        if (!is_dir($candidate)) {
            $candidate = dirname($candidate);
        }
        $resolved = realpath($candidate);

        return is_string($resolved) && is_dir($resolved) ? $resolved : null;
    }

    private function stripUtf8Bom(string $source): string
    {
        return str_starts_with($source, "\xEF\xBB\xBF") ? substr($source, 3) : $source;
    }

    private function stripCommentsOutsideLiteralEnvironments(string $source): string
    {
        $literalEnvironments = ['verbatim' => true, 'verbatim*' => true, 'lstlisting' => true, 'minted' => true];
        $environment = '';
        $result = '';
        $length = strlen($source);
        for ($offset = 0; $offset < $length;) {
            if ($source[$offset] === '\\') {
                $command = $this->commandAt($source, $offset);
                if ($command !== null && ($command['name'] === 'begin' || $command['name'] === 'end')) {
                    $cursor = $command['next'];
                    $group = $this->readRequiredGroup($source, $cursor);
                    if ($group !== null) {
                        $name = strtolower(trim($group['value']));
                        if ($command['name'] === 'begin' && isset($literalEnvironments[$name])) {
                            $environment = $name;
                        } elseif ($command['name'] === 'end' && $environment === $name) {
                            $environment = '';
                        }
                    }
                }
                $result .= $source[$offset];
                ++$offset;
                continue;
            }
            if ($environment === '' && $source[$offset] === '%' && !$this->isEscaped($source, $offset)) {
                while ($offset < $length && $source[$offset] !== "\n") {
                    ++$offset;
                }
                continue;
            }
            $result .= $source[$offset];
            ++$offset;
        }

        return $result;
    }

    /**
     * @return array{0:string, 1:string, 2:bool, 3:int}
     */
    private function splitDocument(string $source): array
    {
        $begin = $this->findEnvironmentToken($source, 'document', 0, 'begin');
        if ($begin === null) {
            return ['', $source, false, 0];
        }
        $end = $this->findEnvironmentToken($source, 'document', $begin['next'], 'end');
        if ($end === null) {
            $this->diagnostic('latex-document-environment-unclosed');

            return [substr($source, 0, $begin['offset']), substr($source, $begin['next']), true, $begin['next']];
        }

        return [
            substr($source, 0, $begin['offset']),
            substr($source, $begin['next'], $end['offset'] - $begin['next']),
            true,
            $begin['next'],
        ];
    }

    /**
     * @return array{offset:int, next:int}|null
     */
    private function findEnvironmentToken(string $source, string $environment, int $offset, string $kind): ?array
    {
        $length = strlen($source);
        while ($offset < $length) {
            $next = strpos($source, '\\', $offset);
            if ($next === false) {
                return null;
            }
            $command = $this->commandAt($source, $next);
            if ($command === null || $command['name'] !== $kind) {
                $offset = $next + 1;
                continue;
            }
            $cursor = $command['next'];
            $group = $this->readRequiredGroup($source, $cursor);
            if ($group !== null && strtolower(trim($group['value'])) === strtolower($environment)) {
                return ['offset' => $next, 'next' => $group['next']];
            }
            $offset = $next + 1;
        }

        return null;
    }

    /**
     * @return array{title:string, author:list<string>, date:string, documentClass:string, packages:list<string>, affiliations:list<string>, authorNotes:list<string>, keywords:list<string>}
     */
    private function parsePreamble(string $preamble): array
    {
        $metadata = [
            'title' => '',
            'author' => [],
            'date' => '',
            'documentClass' => '',
            'packages' => [],
            'affiliations' => [],
            'authorNotes' => [],
            'keywords' => [],
        ];
        $length = strlen($preamble);
        for ($offset = 0; $offset < $length;) {
            if ($preamble[$offset] !== '\\') {
                ++$offset;
                continue;
            }
            $command = $this->commandAt($preamble, $offset);
            if ($command === null) {
                ++$offset;
                continue;
            }
            $cursor = $command['next'];
            if (in_array($command['name'], ['newcommand', 'renewcommand', 'providecommand'], true)) {
                $definition = $this->readMacroDefinition(
                    $preamble,
                    $offset,
                    $command,
                    $this->sourceLocationAtOffset($offset, 'command', $command['name'])
                );
                if ($definition !== null) {
                    $this->registerMacro($definition);
                    $offset = $definition['next'];
                    continue;
                }
                $rawDefinition = $this->readRawMacroDefinition($preamble, $offset, $command);
                if ($rawDefinition !== null) {
                    $location = $this->sourceLocationAtOffset($offset, 'command', $command['name']);
                    $this->preambleRawBlocks[] = new AstNode('raw_tex', [
                        'tex' => $rawDefinition['source'],
                        'command' => $command['name'],
                        'preamble' => true,
                        'latexSourceLocation' => $location,
                    ]);
                    $offset = $rawDefinition['next'];
                    continue;
                }
            }
            if ($command['name'] === 'newtheorem') {
                $definition = $this->readScholarlyEnvironmentDefinition($preamble, $offset, $command);
                if ($definition !== null) {
                    $this->registerScholarlyEnvironment($definition);
                    $offset = $definition['next'];
                    continue;
                }
            }
            if ($command['name'] === 'documentclass') {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    $metadata['documentClass'] = $this->plainInlineText($this->parseInlines($arguments['groups'][0]));
                    $offset = $arguments['next'];
                    continue;
                }
            }
            if ($command['name'] === 'usepackage') {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    foreach (preg_split('/\s*,\s*/u', $arguments['groups'][0]) ?: [] as $package) {
                        $package = trim($package);
                        if ($package !== '') {
                            $metadata['packages'][] = $package;
                        }
                    }
                    $offset = $arguments['next'];
                    continue;
                }
            }
            if (in_array($command['name'], ['title', 'author', 'addauthor', 'date'], true)) {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    if (in_array($command['name'], ['author', 'addauthor'], true)) {
                        $authorSourceWithNotes = $arguments['groups'][0];
                        foreach ($this->thanksNotesFromSource($authorSourceWithNotes) as $note) {
                            $metadata['authorNotes'][] = $note;
                        }
                        $authorSourceWithNotes = $this->withoutThanksCommands($authorSourceWithNotes);
                        foreach (preg_split('/\\\\and\b/u', $authorSourceWithNotes) ?: [] as $authorSource) {
                            $author = trim($this->plainInlineText($this->parseInlines($authorSource)));
                            if ($author !== '') {
                                $metadata['author'][] = $author;
                            }
                        }
                    } else {
                        $metadata[$command['name']] = trim($this->plainInlineText($this->parseInlines($arguments['groups'][0])));
                    }
                    $offset = $arguments['next'];
                    continue;
                }
            }
            if (in_array($command['name'], ['affiliation', 'institute'], true)) {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    $affiliation = trim($this->plainInlineText($this->parseInlines($arguments['groups'][0])));
                    if ($affiliation !== '') {
                        $metadata['affiliations'][] = $affiliation;
                    }
                    $offset = $arguments['next'];
                    continue;
                }
            }
            if (in_array($command['name'], ['thanks', 'authornote'], true)) {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    $note = trim($this->plainInlineText($this->parseInlines($arguments['groups'][0])));
                    if ($note !== '') {
                        $metadata['authorNotes'][] = $note;
                    }
                    $offset = $arguments['next'];
                    continue;
                }
            }
            if (in_array($command['name'], ['keywords', 'keyword'], true)) {
                $arguments = $this->readCommandArguments($preamble, $cursor, 1);
                if ($arguments !== null) {
                    foreach ($this->keywordValues($arguments['groups'][0]) as $keyword) {
                        $metadata['keywords'][] = $keyword;
                    }
                    $offset = $arguments['next'];
                    continue;
                }
            }
            $offset = max($offset + 1, $cursor);
        }

        $metadata['packages'] = array_values(array_unique($metadata['packages']));
        $metadata['affiliations'] = array_values(array_unique($metadata['affiliations']));
        $metadata['authorNotes'] = array_values(array_unique($metadata['authorNotes']));
        $metadata['keywords'] = array_values(array_unique($metadata['keywords']));

        return $metadata;
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlocks(
        string $source,
        int $includeDepth,
        ?string $sourceName = null,
        int $sourceLineOffset = 0
    ): array
    {
        $previousSourceName = $this->activeSourceName;
        $previousSourceText = $this->activeSourceText;
        $previousSourceLineOffset = $this->activeSourceLineOffset;
        if ($sourceName !== null) {
            $this->activeSourceName = $sourceName;
            $this->activeSourceText = $source;
            $this->activeSourceLineOffset = $sourceLineOffset;
        }

        try {
            $blocks = [];
            $offset = 0;
            $length = strlen($source);
            while ($offset < $length) {
                $this->skipBlockWhitespace($source, $offset);
                if ($offset >= $length) {
                    break;
                }
                if ($source[$offset] === '\\') {
                    $command = $this->commandAt($source, $offset);
                    if ($command !== null) {
                        $handled = $this->parseBlockCommand($source, $offset, $command, $blocks, $includeDepth);
                        if ($handled) {
                            continue;
                        }
                    }
                }
                $paragraphOffset = $offset;
                $paragraph = $this->readParagraph($source, $offset);
                if ($paragraph === '') {
                    // An unrecognized command at a block boundary still needs to
                    // advance so malformed source cannot stall the reader.
                    $paragraph = $source[$offset] ?? '';
                    ++$offset;
                }
                $inlines = $this->parseInlines($paragraph, 0, $paragraphOffset);
                if ($this->plainInlineText($inlines) !== '' || $this->containsNonTextInline($inlines)) {
                    $blocks[] = new AstNode('paragraph', [
                        'text' => $this->plainInlineText($inlines),
                    ], $inlines);
                }
            }

            return $blocks;
        } finally {
            if ($sourceName !== null) {
                $this->activeSourceName = $previousSourceName;
                $this->activeSourceText = $previousSourceText;
                $this->activeSourceLineOffset = $previousSourceLineOffset;
            }
        }
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function parseBlockCommand(string $source, int &$offset, array $command, array &$blocks, int $includeDepth): bool
    {
        $name = $command['name'];
        $cursor = $command['next'];
        if (isset($this->headingLevels()[$name])) {
            $heading = $this->parseHeadingCommand($source, $offset, $command);
            if ($heading !== null) {
                $blocks[] = $heading['node'];
                $offset = $heading['next'];

                return true;
            }
        }
        if ($name === 'label') {
            $label = $this->readRequiredGroup($source, $cursor);
            if ($label !== null) {
                $this->applyLabelToPreviousBlock($blocks, $label['value']);
                $offset = $label['next'];

                return true;
            }
        }
        if ($name === 'newtheorem') {
            $definition = $this->readScholarlyEnvironmentDefinition($source, $offset, $command);
            if ($definition !== null) {
                $this->registerScholarlyEnvironment($definition);
                $offset = $definition['next'];

                return true;
            }
        }
        if (in_array($name, ['newcommand', 'renewcommand', 'providecommand'], true)) {
            $definition = $this->readMacroDefinition(
                $source,
                $offset,
                $command,
                $this->sourceLocationAtOffset($offset, 'command', $name)
            );
            if ($definition !== null) {
                $this->registerMacro($definition);
                $offset = $definition['next'];

                return true;
            }
            $rawDefinition = $this->readRawMacroDefinition($source, $offset, $command);
            if ($rawDefinition !== null) {
                $location = $this->sourceLocationAtOffset($offset, 'command', $name);
                $blocks[] = new AstNode('raw_tex', [
                    'tex' => $rawDefinition['source'],
                    'command' => $name,
                    'latexSourceLocation' => $location,
                ]);
                $offset = $rawDefinition['next'];

                return true;
            }
        }
        if ($name === 'maketitle') {
            $blocks[] = $this->titleBlock();
            $offset = $cursor;

            return true;
        }
        if ($name === 'tableofcontents') {
            $blocks[] = new AstNode('div', [
                'classes' => ['latex-table-of-contents'],
                'latexTocPlaceholder' => true,
            ]);
            $offset = $cursor;

            return true;
        }
        if (in_array($name, ['input', 'include'], true)) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument === null) {
                return false;
            }
            $offset = $argument['next'];
            array_push($blocks, ...$this->readIncludedBlocks($argument['value'], $includeDepth, $name));

            return true;
        }
        if ($name === 'addbibresource') {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument === null) {
                return false;
            }
            $this->bibliographyRequested = true;
            $this->loadBibliographyResources($argument['value']);
            $offset = $argument['next'];

            return true;
        }
        if ($name === 'bibliography') {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument === null) {
                return false;
            }
            $this->bibliographyRequested = true;
            $this->loadBibliographyResources($argument['value']);
            $blocks[] = $this->bibliographyPlaceholder('bibliography', []);
            $offset = $argument['next'];

            return true;
        }
        if ($name === 'printbibliography') {
            $this->bibliographyRequested = true;
            $options = [];
            while (($option = $this->readOptionalGroup($source, $cursor)) !== null) {
                $options[] = $option['value'];
                $cursor = $option['next'];
            }
            $blocks[] = $this->bibliographyPlaceholder('printbibliography', $options);
            $offset = $cursor;

            return true;
        }
        if ($name === 'bibliographystyle') {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                $this->bibliographyStyle = trim($argument['value']);
                $offset = $argument['next'];

                return true;
            }
        }
        if (in_array($name, ['keywords', 'keyword'], true)) {
            while (($option = $this->readOptionalGroup($source, $cursor)) !== null) {
                $cursor = $option['next'];
            }
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                $keywords = $this->keywordValues($argument['value']);
                if ($keywords !== []) {
                    $blocks[] = $this->keywordsBlock($keywords);
                }
                $offset = $argument['next'];

                return true;
            }
        }
        if ($name === 'par') {
            $offset = $cursor;

            return true;
        }
        if ($name === '[') {
            $end = $this->findUnescapedDelimiter($source, '\\]', $cursor);
            if ($end !== null) {
                $blocks[] = $this->displayMathBlock(substr($source, $cursor, $end - $cursor), 'displaymath');
                $offset = $end + 2;

                return true;
            }
        }
        if (in_array($name, ['newpage', 'clearpage', 'pagebreak', 'clear doublepage', 'cleardoublepage'], true)) {
            $offset = $cursor;

            return true;
        }
        if ($name === 'begin') {
            $environment = $this->readEnvironmentAt($source, $offset, $command);
            if ($environment === null) {
                return false;
            }
            $offset = $environment['next'];
            array_push($blocks, ...$this->blocksFromEnvironment($environment, $includeDepth));

            return true;
        }

        return false;
    }

    /**
     * @return array{node:AstNode, next:int}|null
     */
    private function parseHeadingCommand(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        $arguments = $this->readCommandArguments($source, $cursor, 1);
        if ($arguments === null) {
            return null;
        }
        $titleInlines = $this->parseInlines($arguments['groups'][0]);
        $title = $this->plainInlineText($titleInlines);
        $after = $arguments['next'];
        $label = $this->readFollowingLabel($source, $after);
        if ($label !== null) {
            $after = $label['next'];
        }
        $id = $label === null ? $this->headingId($title) : $this->registerLabel($label['value'], $title, 'heading');

        return [
            'node' => new AstNode('heading', [
                'level' => $this->headingLevel($command['name']),
                'id' => $id,
                'text' => $title,
                'latexCommand' => $command['name'],
                'latexStarred' => $command['starred'],
            ], $titleInlines),
            'next' => $after,
        ];
    }

    /**
     * @return array{environment:string, content:string, raw:string, options:list<string>, next:int, sourceLocation:array{source:string,line:int,column:int,environment:string}}|null
     */
    private function readEnvironmentAt(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        $nameGroup = $this->readRequiredGroup($source, $cursor);
        if ($nameGroup === null) {
            return null;
        }
        $environment = strtolower(trim($nameGroup['value']));
        if ($environment === '') {
            return null;
        }
        $sourceLocation = $this->sourceLocationAtOffset($offset, 'environment', $environment);
        $options = [];
        $bodyStart = $nameGroup['next'];
        while (true) {
            $option = $this->readOptionalGroup($source, $bodyStart);
            if ($option === null) {
                break;
            }
            $options[] = $option['value'];
            $bodyStart = $option['next'];
        }

        $literal = in_array($environment, ['verbatim', 'verbatim*', 'lstlisting', 'minted'], true);
        if ($literal) {
            $closing = '\\end{' . $environment . '}';
            $endOffset = strpos($source, $closing, $bodyStart);
            if ($endOffset === false) {
                $this->diagnostic('latex-environment-unclosed:' . $environment);

                return [
                    'environment' => $environment,
                    'content' => substr($source, $bodyStart),
                    'raw' => substr($source, $offset),
                    'options' => $options,
                    'next' => strlen($source),
                    'sourceLocation' => $sourceLocation,
                ];
            }

            return [
                'environment' => $environment,
                'content' => substr($source, $bodyStart, $endOffset - $bodyStart),
                'raw' => substr($source, $offset, $endOffset + strlen($closing) - $offset),
                'options' => $options,
                'next' => $endOffset + strlen($closing),
                'sourceLocation' => $sourceLocation,
            ];
        }

        $depth = 1;
        $scan = $bodyStart;
        $length = strlen($source);
        while ($scan < $length) {
            $next = strpos($source, '\\', $scan);
            if ($next === false) {
                break;
            }
            $candidate = $this->commandAt($source, $next);
            if ($candidate === null || !in_array($candidate['name'], ['begin', 'end'], true)) {
                $scan = $next + 1;
                continue;
            }
            $candidateCursor = $candidate['next'];
            $candidateName = $this->readRequiredGroup($source, $candidateCursor);
            if ($candidateName === null || strtolower(trim($candidateName['value'])) !== $environment) {
                $scan = $next + 1;
                continue;
            }
            if ($candidate['name'] === 'begin') {
                ++$depth;
                $scan = $candidateName['next'];
                continue;
            }
            --$depth;
            if ($depth === 0) {
                return [
                    'environment' => $environment,
                    'content' => substr($source, $bodyStart, $next - $bodyStart),
                    'raw' => substr($source, $offset, $candidateName['next'] - $offset),
                    'options' => $options,
                    'next' => $candidateName['next'],
                    'sourceLocation' => $sourceLocation,
                ];
            }
            $scan = $candidateName['next'];
        }

        $this->diagnostic('latex-environment-unclosed:' . $environment);

        return [
            'environment' => $environment,
            'content' => substr($source, $bodyStart),
            'raw' => substr($source, $offset),
            'options' => $options,
            'next' => strlen($source),
            'sourceLocation' => $sourceLocation,
        ];
    }

    /**
     * @param array{environment:string, content:string, raw:string, options:list<string>, next:int} $environment
     * @return list<AstNode>
     */
    private function blocksFromEnvironment(array $environment, int $includeDepth): array
    {
        $name = $environment['environment'];
        $content = $environment['content'];
        if ($name === 'document') {
            return $this->parseBlocks($content, $includeDepth);
        }
        if ($name === 'abstract') {
            $blocks = $this->parseBlocks($content, $includeDepth);
            $this->abstractBlocks = $blocks;

            return [new AstNode('div', ['classes' => ['latex-abstract']], array_merge([
                new AstNode('heading', [
                    'level' => 2,
                    'id' => 'abstract',
                    'text' => 'Abstract',
                ], [new AstNode('text', ['text' => 'Abstract'])]),
            ], $blocks))];
        }
        if (in_array($name, ['itemize', 'enumerate', 'description'], true)) {
            return [$this->listFromEnvironment($name, $content, $includeDepth)];
        }
        if (in_array($name, ['quote', 'quotation'], true)) {
            return [new AstNode('blockquote', [], $this->parseBlocks($content, $includeDepth))];
        }
        if ($name === 'verse') {
            return [$this->lineBlockFromVerse($content)];
        }
        if (in_array($name, ['verbatim', 'verbatim*', 'lstlisting', 'minted'], true)) {
            return [$this->codeBlockFromEnvironment($environment)];
        }
        if (in_array($name, [
            'equation', 'equation*', 'align', 'align*', 'aligned', 'alignedat', 'alignedat*',
            'alignat', 'alignat*', 'flalign', 'flalign*', 'gather', 'gather*', 'gathered',
            'multline', 'multline*', 'split', 'displaymath',
        ], true)) {
            return [$this->displayMathBlock($content, $name)];
        }
        if (in_array($name, ['figure', 'figure*'], true)) {
            return [$this->figureFromEnvironment($content)];
        }
        if (in_array($name, ['table', 'table*'], true)) {
            $table = $this->tableFromContainerEnvironment($content);
            if ($table instanceof AstNode) {
                return [$table];
            }
        }
        if (in_array($name, ['tabular', 'tabular*', 'tabularx', 'longtable', 'array'], true)) {
            $table = $this->tableFromTabularEnvironment($name, $content, $environment['options']);
            if ($table instanceof AstNode) {
                return [$table];
            }
        }
        if ($name === 'thebibliography') {
            return $this->manualBibliographyBlocks($content);
        }
        if (in_array($name, ['refsection', 'refsegment'], true)) {
            return [new AstNode('div', [
                'classes' => ['latex-' . $name],
                'latexBibliographyScope' => true,
            ], $this->parseBlocks($content, $includeDepth))];
        }
        $scholarly = $this->scholarlyEnvironmentDefinition($name);
        if ($scholarly !== null) {
            return [$this->scholarlyBlockFromEnvironment($environment, $scholarly, $includeDepth)];
        }
        if (in_array($name, ['center', 'flushleft', 'flushright', 'minipage'], true)) {
            return [new AstNode('div', ['classes' => ['latex-' . $name]], $this->parseBlocks($content, $includeDepth))];
        }

        $diagnostic = 'latex-unsupported-environment:' . $name;
        $this->diagnostic($diagnostic, $environment['sourceLocation']);

        return [new AstNode('raw_tex', [
            'tex' => $environment['raw'],
            'environment' => $name,
            'latexDiagnostic' => $diagnostic,
            'latexSourceLocation' => $environment['sourceLocation'],
        ])];
    }

    /**
     * @return array<string, int>
     */
    private function headingLevels(): array
    {
        $sectionLevel = $this->hasChapter ? 2 : 1;

        return [
            'part' => 1,
            'chapter' => 1,
            'section' => $sectionLevel,
            'subsection' => min(6, $sectionLevel + 1),
            'subsubsection' => min(6, $sectionLevel + 2),
            'paragraph' => min(6, $sectionLevel + 3),
            'subparagraph' => min(6, $sectionLevel + 4),
        ];
    }

    private function headingLevel(string $command): int
    {
        return $this->headingLevels()[$command] ?? 2;
    }

    private function headingId(string $title): string
    {
        $base = $this->slug($title);
        if ($base === '') {
            $base = 'section';
        }
        $id = $base;
        $suffix = 2;
        $taken = array_map(static fn (array $label): string => $label['id'], $this->labels);
        while (in_array($id, $taken, true)) {
            $id = $base . '-' . $suffix;
            ++$suffix;
        }

        return $id;
    }

    private function collectLabelIds(string $source): void
    {
        if (preg_match_all('/(?<!\\\\)\\\\label\s*\{((?:\\\\.|[^{}\\\\])*)\}/u', $source, $matches) !== 1) {
            return;
        }
        foreach ($matches[1] as $raw) {
            $label = $this->cleanLabel((string) $raw);
            if ($label === '' || isset($this->labels[$label])) {
                continue;
            }
            $base = 'latex-' . $this->slug($label);
            if ($base === 'latex-') {
                $base = 'latex-anchor';
            }
            $id = $base;
            $suffix = 2;
            while (in_array($id, array_map(static fn (array $entry): string => $entry['id'], $this->labels), true)) {
                $id = $base . '-' . $suffix;
                ++$suffix;
            }
            $this->labels[$label] = [
                'id' => $id,
                'display' => $label,
                'kind' => 'unknown',
            ];
        }
    }

    private function registerLabel(string $raw, string $display, string $kind): string
    {
        $label = $this->cleanLabel($raw);
        if ($label === '') {
            return $this->headingId($display);
        }
        if (!isset($this->labels[$label])) {
            $this->collectLabelIds('\\label{' . $label . '}');
        }
        $entry = $this->labels[$label] ?? [
            'id' => 'latex-' . ($this->slug($label) ?: 'anchor'),
            'display' => $label,
            'kind' => 'unknown',
        ];
        $entry['display'] = $display === '' ? $entry['display'] : $display;
        $entry['kind'] = $kind;
        $this->labels[$label] = $entry;

        return $entry['id'];
    }

    private function nextReferenceNumber(string $kind): int
    {
        if (!isset($this->referenceCounters[$kind])) {
            $this->referenceCounters[$kind] = 0;
        }

        ++$this->referenceCounters[$kind];

        return $this->referenceCounters[$kind];
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function applyLabelToPreviousBlock(array &$blocks, string $rawLabel): void
    {
        $key = $this->cleanLabel($rawLabel);
        if ($key === '') {
            return;
        }
        $lastIndex = array_key_last($blocks);
        if ($lastIndex === null) {
            $this->diagnostic('latex-orphan-label:' . $key);

            return;
        }
        $last = $blocks[$lastIndex];
        $referenceNumber = $last->attr('latexReferenceNumber');
        $display = is_int($referenceNumber) || (is_string($referenceNumber) && ctype_digit($referenceNumber))
            ? (string) $referenceNumber
            : trim((string) $last->attr('text', ''));
        if ($display === '') {
            $display = $this->plainBlocksText($last->children);
        }
        $kind = (string) $last->attr('latexReferenceKind', $last->type);
        $id = $this->registerLabel($key, $display, $kind);
        $attrs = array_replace($last->attrs, [
            'id' => $id,
            'latexLabel' => $key,
        ]);
        $blocks[$lastIndex] = new AstNode($last->type, $attrs, $last->children);
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readFollowingLabel(string $source, int $offset): ?array
    {
        $cursor = $offset;
        $this->skipWhitespace($source, $cursor);
        $command = $this->commandAt($source, $cursor);
        if ($command === null || $command['name'] !== 'label') {
            return null;
        }
        $next = $command['next'];

        return $this->readRequiredGroup($source, $next);
    }

    private function titleBlock(): AstNode
    {
        $children = [];
        $title = trim($this->preambleMetadata['title']);
        if ($title !== '') {
            $inlines = $this->parseInlines($title);
            $children[] = new AstNode('heading', [
                'level' => 1,
                'id' => 'title',
                'text' => $this->plainInlineText($inlines),
                'latexCommand' => 'maketitle',
            ], $inlines);
        }
        foreach ($this->preambleMetadata['author'] as $author) {
            $inlines = $this->parseInlines($author);
            $children[] = new AstNode('paragraph', [
                'classes' => ['latex-author'],
                'text' => $this->plainInlineText($inlines),
            ], $inlines);
        }
        foreach ($this->preambleMetadata['affiliations'] as $affiliation) {
            $inlines = $this->parseInlines($affiliation);
            $children[] = new AstNode('paragraph', [
                'classes' => ['latex-affiliation'],
                'text' => $this->plainInlineText($inlines),
            ], $inlines);
        }
        $date = trim($this->preambleMetadata['date']);
        if ($date !== '') {
            $inlines = $this->parseInlines($date);
            $children[] = new AstNode('paragraph', [
                'classes' => ['latex-date'],
                'text' => $this->plainInlineText($inlines),
            ], $inlines);
        }
        foreach ($this->preambleMetadata['authorNotes'] as $note) {
            $inlines = $this->parseInlines($note);
            $children[] = new AstNode('paragraph', [
                'classes' => ['latex-author-note'],
                'text' => $this->plainInlineText($inlines),
            ], $inlines);
        }
        if ($this->preambleMetadata['keywords'] !== []) {
            $children[] = $this->keywordsBlock($this->preambleMetadata['keywords']);
        }
        if ($children === []) {
            $this->diagnostic('latex-maketitle-without-preamble-metadata');

            return new AstNode('div', ['classes' => ['latex-title-block']]);
        }

        return new AstNode('div', ['classes' => ['latex-title-block']], $children);
    }

    /**
     * @param list<string> $keywords
     */
    private function keywordsBlock(array $keywords): AstNode
    {
        $inlines = [new AstNode('strong', [], [new AstNode('text', ['text' => 'Keywords:'])])];
        foreach ($keywords as $index => $keyword) {
            if ($index > 0) {
                $inlines[] = new AstNode('text', ['text' => ', ']);
            } else {
                $inlines[] = new AstNode('text', ['text' => ' ']);
            }
            array_push($inlines, ...$this->parseInlines($keyword));
        }

        return new AstNode('paragraph', [
            'classes' => ['latex-keywords'],
            'text' => 'Keywords: ' . implode(', ', $keywords),
        ], $inlines);
    }

    /**
     * @return list<string>
     */
    private function thanksNotesFromSource(string $source): array
    {
        $notes = [];
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'thanks') {
                continue;
            }
            $cursor = $command['next'];
            $group = $this->readRequiredGroup($source, $cursor);
            if ($group === null) {
                continue;
            }
            $note = trim($this->plainInlineText($this->parseInlines($group['value'])));
            if ($note !== '') {
                $notes[] = $note;
            }
            $offset = $group['next'] - 1;
        }

        return $notes;
    }

    private function withoutThanksCommands(string $source): string
    {
        $result = '';
        $length = strlen($source);
        for ($offset = 0; $offset < $length;) {
            if ($source[$offset] !== '\\') {
                $result .= $source[$offset];
                ++$offset;
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'thanks') {
                $result .= $source[$offset];
                ++$offset;
                continue;
            }
            $cursor = $command['next'];
            $group = $this->readRequiredGroup($source, $cursor);
            if ($group === null) {
                $result .= $source[$offset];
                ++$offset;
                continue;
            }
            $offset = $group['next'];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function keywordValues(string $source): array
    {
        $keywords = [];
        foreach ($this->splitTopLevel(str_replace('\\and', ',', $source), ',') as $rawKeyword) {
            $keyword = trim($this->plainInlineText($this->parseInlines(trim($rawKeyword))));
            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }

    /**
     * @return array{name:string,kind:string,label:string,counter:string,numbered:bool,next:int}|null
     */
    private function readScholarlyEnvironmentDefinition(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        $name = $this->readRequiredGroup($source, $cursor);
        if ($name === null) {
            return null;
        }
        $environment = strtolower(trim($name['value']));
        if ($environment === '' || preg_match('/^[a-z@][a-z0-9@*-]*$/u', $environment) !== 1) {
            return null;
        }
        $cursor = $name['next'];
        $sharedCounter = $this->readOptionalGroup($source, $cursor);
        if ($sharedCounter !== null) {
            $cursor = $sharedCounter['next'];
        }
        $title = $this->readRequiredGroup($source, $cursor);
        if ($title === null) {
            return null;
        }
        $cursor = $title['next'];
        $within = $this->readOptionalGroup($source, $cursor);
        if ($within !== null) {
            $cursor = $within['next'];
        }
        $label = trim($this->plainInlineText($this->parseInlines($title['value'])));
        if ($label === '') {
            return null;
        }
        $counter = $sharedCounter === null ? $environment : $this->cleanLabel($sharedCounter['value']);
        if ($counter === '') {
            $counter = $environment;
        }

        return [
            'name' => $environment,
            'kind' => $environment,
            'label' => $label,
            'counter' => $counter,
            'numbered' => !$command['starred'],
            'next' => $cursor,
        ];
    }

    /**
     * @param array{name:string,kind:string,label:string,counter:string,numbered:bool,next:int} $definition
     */
    private function registerScholarlyEnvironment(array $definition): void
    {
        $this->scholarlyEnvironments[$definition['name']] = [
            'kind' => $definition['kind'],
            'label' => $definition['label'],
            'counter' => $definition['counter'],
            'numbered' => $definition['numbered'],
        ];
    }

    /**
     * @return array{kind:string,label:string,counter:string,numbered:bool}|null
     */
    private function scholarlyEnvironmentDefinition(string $name): ?array
    {
        if (isset($this->scholarlyEnvironments[$name])) {
            return $this->scholarlyEnvironments[$name];
        }

        return [
            'theorem' => ['kind' => 'theorem', 'label' => 'Theorem', 'counter' => 'theorem', 'numbered' => true],
            'lemma' => ['kind' => 'lemma', 'label' => 'Lemma', 'counter' => 'lemma', 'numbered' => true],
            'proposition' => ['kind' => 'proposition', 'label' => 'Proposition', 'counter' => 'proposition', 'numbered' => true],
            'corollary' => ['kind' => 'corollary', 'label' => 'Corollary', 'counter' => 'corollary', 'numbered' => true],
            'definition' => ['kind' => 'definition', 'label' => 'Definition', 'counter' => 'definition', 'numbered' => true],
            'remark' => ['kind' => 'remark', 'label' => 'Remark', 'counter' => 'remark', 'numbered' => true],
            'example' => ['kind' => 'example', 'label' => 'Example', 'counter' => 'example', 'numbered' => true],
            'proof' => ['kind' => 'proof', 'label' => 'Proof', 'counter' => '', 'numbered' => false],
            'acknowledgments' => ['kind' => 'acknowledgments', 'label' => 'Acknowledgments', 'counter' => '', 'numbered' => false],
            'acknowledgements' => ['kind' => 'acknowledgments', 'label' => 'Acknowledgments', 'counter' => '', 'numbered' => false],
        ][$name] ?? null;
    }

    /**
     * @param array{environment:string, content:string, raw:string, options:list<string>, next:int} $environment
     * @param array{kind:string,label:string,counter:string,numbered:bool} $definition
     */
    private function scholarlyBlockFromEnvironment(array $environment, array $definition, int $includeDepth): AstNode
    {
        $label = $this->firstLabel($environment['content']);
        $bodySource = preg_replace('/\\\\label\s*\{(?:\\\\.|[^{}])*\}\s*/u', '', $environment['content']) ?? $environment['content'];
        $body = $this->parseBlocks($bodySource, $includeDepth);
        $title = trim((string) ($environment['options'][0] ?? ''));
        $titleText = $title === '' ? '' : $this->plainInlineText($this->parseInlines($title));
        $number = $definition['numbered'] ? $this->nextReferenceNumber($definition['counter']) : null;
        $headingText = $definition['label'] . ($number === null ? '' : ' ' . $number);
        if ($titleText !== '') {
            $headingText .= ' (' . $titleText . ')';
        }
        $attrs = [
            'classes' => ['latex-scholarly', 'latex-' . $definition['kind']],
            'latexScholarlyKind' => $definition['kind'],
        ];
        if ($number !== null) {
            $attrs['latexReferenceKind'] = $definition['kind'];
            $attrs['latexReferenceNumber'] = $number;
        }
        if ($label !== '') {
            $attrs['id'] = $this->registerLabel(
                $label,
                $number === null ? $headingText : (string) $number,
                $definition['kind']
            );
            $attrs['latexLabel'] = $this->cleanLabel($label);
        }

        return new AstNode('div', $attrs, [
            new AstNode('heading', [
                'level' => $definition['kind'] === 'acknowledgments' ? 2 : 3,
                'text' => $headingText,
            ], $this->parseInlines($headingText)),
            ...$body,
        ]);
    }

    private function listFromEnvironment(string $environment, string $content, int $includeDepth): AstNode
    {
        $items = $this->splitListItems($content);
        if ($environment === 'description') {
            $definitions = [];
            foreach ($items as $item) {
                $termInlines = $this->parseInlines($item['label'] === '' ? 'Item' : $item['label']);
                $itemBlocks = $this->parseBlocks($item['content'], $includeDepth);
                if ($itemBlocks === []) {
                    $itemBlocks = [new AstNode('paragraph', ['text' => ''], [])];
                }
                $definitions[] = new AstNode('definition_item', [
                    'term' => $this->plainInlineText($termInlines),
                ], [
                    new AstNode('definition_term', ['text' => $this->plainInlineText($termInlines)], $termInlines),
                    new AstNode('definition', [], $itemBlocks),
                ]);
            }

            return new AstNode('definition_list', ['classes' => ['latex-description']], $definitions);
        }

        $listItems = [];
        foreach ($items as $item) {
            $itemBlocks = $this->parseBlocks($item['content'], $includeDepth);
            if ($itemBlocks === []) {
                $itemBlocks = [new AstNode('plain', ['text' => ''], [])];
            }
            $listItems[] = new AstNode('list_item', ['loose' => $this->blocksNeedLooseList($itemBlocks)], $itemBlocks);
        }
        if ($environment === 'enumerate') {
            return new AstNode('ordered_list', [
                'start' => 1,
                'style' => 'default',
                'delimiter' => 'default',
                'loose' => $this->listItemsNeedLooseList($listItems),
            ], $listItems);
        }

        return new AstNode('bullet_list', [
            'loose' => $this->listItemsNeedLooseList($listItems),
        ], $listItems);
    }

    /**
     * @return list<array{label:string, content:string}>
     */
    private function splitListItems(string $content): array
    {
        $items = [];
        $offsets = [];
        $depth = 0;
        $length = strlen($content);
        for ($offset = 0; $offset < $length; ++$offset) {
            $char = $content[$offset];
            if ($char === '\\') {
                $command = $this->commandAt($content, $offset);
                if ($command !== null && $command['name'] === 'item' && $depth === 0) {
                    $offsets[] = ['offset' => $offset, 'next' => $command['next']];
                }
                ++$offset;
                continue;
            }
            if ($char === '{' && !$this->isEscaped($content, $offset)) {
                ++$depth;
            } elseif ($char === '}' && !$this->isEscaped($content, $offset) && $depth > 0) {
                --$depth;
            }
        }
        foreach ($offsets as $index => $itemOffset) {
            $cursor = $itemOffset['next'];
            $label = '';
            $optional = $this->readOptionalGroup($content, $cursor);
            if ($optional !== null) {
                $label = $optional['value'];
                $cursor = $optional['next'];
            }
            $end = $offsets[$index + 1]['offset'] ?? $length;
            $items[] = [
                'label' => $label,
                'content' => trim(substr($content, $cursor, $end - $cursor)),
            ];
        }
        if ($items === [] && trim($content) !== '') {
            $items[] = ['label' => '', 'content' => trim($content)];
        }

        return $items;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function blocksNeedLooseList(array $blocks): bool
    {
        return count($blocks) > 1 || (($blocks[0]->type ?? '') !== 'plain');
    }

    /**
     * @param list<AstNode> $items
     */
    private function listItemsNeedLooseList(array $items): bool
    {
        foreach ($items as $item) {
            if ($item->attr('loose') === true) {
                return true;
            }
        }

        return false;
    }

    private function lineBlockFromVerse(string $content): AstNode
    {
        $lines = [];
        foreach (preg_split('/\R/u', trim($content)) ?: [] as $line) {
            $lines[] = new AstNode('line', [], $this->parseInlines($line));
        }

        return new AstNode('line_block', ['classes' => ['latex-verse']], $lines);
    }

    /**
     * @param array{environment:string, content:string, raw:string, options:list<string>, next:int} $environment
     */
    private function codeBlockFromEnvironment(array $environment): AstNode
    {
        $language = '';
        if ($environment['environment'] === 'minted') {
            $language = trim((string) ($environment['options'][0] ?? ''));
        }
        if ($environment['environment'] === 'lstlisting') {
            foreach ($environment['options'] as $option) {
                if (preg_match('/(?:^|,)\s*language\s*=\s*([^,]+)/iu', $option, $match) === 1) {
                    $language = trim($match[1]);
                    break;
                }
            }
        }
        $classes = ['latex-' . $environment['environment']];
        if ($language !== '') {
            $classes[] = strtolower($language);
        }

        return new AstNode('code_block', [
            'text' => trim($environment['content'], "\n"),
            'classes' => $classes,
        ]);
    }

    private function displayMathBlock(string $content, string $environment): AstNode
    {
        $label = $this->firstLabel($content);
        $math = trim($content);
        if ($this->mathEnvironmentNeedsWrapper($environment) && !preg_match('/^\\\\begin\s*\{/u', $math)) {
            $math = '\\begin{' . $environment . "}\n" . $math . "\n\\end{" . $environment . '}';
        }
        $numbered = $this->numberedMathEnvironment($environment);
        $number = $numbered ? $this->nextReferenceNumber('equation') : null;
        $attrs = [
            'classes' => ['latex-display-math'],
            'text' => $math,
            'latexEnvironment' => $environment,
        ];
        if ($number !== null) {
            $attrs['latexReferenceKind'] = 'equation';
            $attrs['latexReferenceNumber'] = $number;
        }
        if ($label !== '') {
            $attrs['id'] = $this->registerLabel($label, $number === null ? '' : (string) $number, 'equation');
            $attrs['latexLabel'] = $this->cleanLabel($label);
        }

        return new AstNode('paragraph', $attrs, [new AstNode('math', [
            'display' => true,
            'text' => $math,
            'latexEnvironment' => $environment,
        ])]);
    }

    private function numberedMathEnvironment(string $environment): bool
    {
        return !str_ends_with($environment, '*')
            && !in_array($environment, ['aligned', 'displaymath'], true);
    }

    private function mathEnvironmentNeedsWrapper(string $environment): bool
    {
        return in_array($environment, [
            'equation', 'equation*', 'align', 'align*', 'aligned', 'gather', 'gather*',
            'multline', 'multline*', 'split', 'alignedat', 'alignedat*', 'gathered',
            'flalign', 'flalign*', 'alignat', 'alignat*',
        ], true);
    }

    private function figureFromEnvironment(string $content): AstNode
    {
        $image = $this->firstIncludeGraphics($content);
        $caption = $this->firstCaption($content);
        $label = $this->firstLabel($content);
        $number = $this->nextReferenceNumber('figure');
        $captionInlines = $this->parseInlines($caption['text']);
        $attrs = [
            'caption' => $this->plainInlineText($captionInlines),
            'captionInlines' => $captionInlines,
            'classes' => ['latex-figure'],
            'latexReferenceKind' => 'figure',
            'latexReferenceNumber' => $number,
        ];
        if ($caption['short'] !== '') {
            $attrs['shortCaption'] = $caption['short'];
            $attrs['shortCaptionInlines'] = $this->parseInlines($caption['short']);
        }
        if ($label !== '') {
            $attrs['id'] = $this->registerLabel($label, (string) $number, 'figure');
            $attrs['latexLabel'] = $this->cleanLabel($label);
        }
        if ($image === null) {
            $this->diagnostic('latex-figure-without-includegraphics');

            return new AstNode('figure', $attrs, [new AstNode('raw_tex', ['tex' => $content])]);
        }
        $imageAttrs = [
            'url' => $image['url'],
            'alt' => $caption['text'],
            'title' => '',
            'latexOptions' => $image['options'],
        ];
        foreach ($this->imageDimensionsFromOptions($image['options']) as $name => $value) {
            $imageAttrs[$name] = $value;
        }

        return new AstNode('figure', $attrs, [new AstNode('image', $imageAttrs, $captionInlines)]);
    }

    private function tableFromContainerEnvironment(string $content): ?AstNode
    {
        $caption = $this->firstCaption($content);
        $label = $this->firstLabel($content);
        $tabular = $this->firstNestedTabularEnvironment($content);
        if ($tabular === null) {
            $this->diagnostic('latex-table-without-tabular');

            return null;
        }
        $table = $this->tableFromTabularEnvironment($tabular['environment'], $tabular['content'], $tabular['options']);
        if (!$table instanceof AstNode) {
            return null;
        }
        $attrs = $table->attrs;
        $number = (int) ($attrs['latexReferenceNumber'] ?? $this->nextReferenceNumber('table'));
        $attrs['caption'] = $caption['text'];
        $attrs['captionInlines'] = $this->parseInlines($caption['text']);
        $attrs['latexReferenceKind'] = 'table';
        $attrs['latexReferenceNumber'] = $number;
        if ($caption['short'] !== '') {
            $attrs['shortCaption'] = $caption['short'];
            $attrs['shortCaptionInlines'] = $this->parseInlines($caption['short']);
        }
        if ($label !== '') {
            $attrs['id'] = $this->registerLabel($label, (string) $number, 'table');
            $attrs['latexLabel'] = $this->cleanLabel($label);
        }

        return new AstNode('table', $attrs, $table->children);
    }

    /**
     * @param list<string> $options
     */
    private function tableFromTabularEnvironment(string $environment, string $content, array $options): ?AstNode
    {
        $caption = $this->firstCaption($content);
        $label = $this->firstLabel($content);
        [$content, $preambleArguments] = $this->tabularPreambleArguments($content, $environment);
        $alignmentSpec = in_array($environment, ['tabular*', 'tabularx'], true)
            ? (string) ($preambleArguments[1] ?? $preambleArguments[0] ?? $options[1] ?? $options[0] ?? '')
            : (string) ($preambleArguments[0] ?? $options[0] ?? '');
        $alignments = $this->tabularAlignments($alignmentSpec);
        $longtableSections = $environment === 'longtable' ? $this->longtableSections($content) : null;
        if ($longtableSections === null) {
            $rows = $this->tabularRows($content);
            $headerCount = $this->tabularHeaderRowCount($content, $rows);
            $headRows = array_slice($rows, 0, $headerCount);
            $bodyRows = array_slice($rows, $headerCount);
            $footRows = [];
        } else {
            $headRows = $this->tabularRows($longtableSections['head']);
            $bodyRows = $this->tabularRows($longtableSections['body']);
            $footRows = $this->tabularRows($longtableSections['foot']);
            $rows = [...$headRows, ...$bodyRows, ...$footRows];
        }
        if ($rows === []) {
            $this->diagnostic('latex-empty-table:' . $environment);

            return null;
        }
        $maxColumns = max(count($alignments), ...array_map(static fn (array $row): int => $row['columnCount'], $rows));
        while (count($alignments) < $maxColumns) {
            $alignments[] = 'default';
        }
        $head = $this->tableRowsToAst($headRows, true);
        $body = $this->tableRowsToAst($bodyRows, false);
        $foot = $this->tableRowsToAst($footRows, false);
        $children = [
            new AstNode('table_head', [], $head),
            new AstNode('table_body', [], $body),
        ];
        if ($foot !== []) {
            $children[] = new AstNode('table_foot', [], $foot);
        }

        $number = $this->nextReferenceNumber('table');
        $attrs = [
            'alignments' => $alignments,
            'classes' => ['latex-' . $environment],
            'latexReferenceKind' => 'table',
            'latexReferenceNumber' => $number,
        ];
        if ($caption['text'] !== '') {
            $attrs['caption'] = $caption['text'];
            $attrs['captionInlines'] = $this->parseInlines($caption['text']);
        }
        if ($caption['short'] !== '') {
            $attrs['shortCaption'] = $caption['short'];
            $attrs['shortCaptionInlines'] = $this->parseInlines($caption['short']);
        }
        if ($label !== '') {
            $attrs['id'] = $this->registerLabel($label, (string) $number, 'table');
            $attrs['latexLabel'] = $this->cleanLabel($label);
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @param list<array{cells:list<array{text:string, colspan:int, rowspan:int, align:string}>, columnCount:int}> $rows
     * @return list<AstNode>
     */
    private function tableRowsToAst(array $rows, bool $header): array
    {
        $tableRows = [];
        /** @var array<int, int> $activeRowspans */
        $activeRowspans = [];
        foreach ($rows as $row) {
            $cells = [];
            $sourceIndex = 0;
            $logicalColumn = 0;
            $sourceCells = $row['cells'];
            while ($sourceIndex < count($sourceCells) || isset($activeRowspans[$logicalColumn])) {
                if (isset($activeRowspans[$logicalColumn])) {
                    if (isset($sourceCells[$sourceIndex]) && $this->tableCellIsRowspanPlaceholder($sourceCells[$sourceIndex])) {
                        ++$sourceIndex;
                    }
                    --$activeRowspans[$logicalColumn];
                    if ($activeRowspans[$logicalColumn] <= 0) {
                        unset($activeRowspans[$logicalColumn]);
                    }
                    ++$logicalColumn;
                    continue;
                }
                if (!isset($sourceCells[$sourceIndex])) {
                    break;
                }
                $cell = $sourceCells[$sourceIndex];
                ++$sourceIndex;
                $inlines = $this->parseInlines($cell['text']);
                $attrs = [
                    'text' => $this->plainInlineText($inlines),
                    'header' => $header,
                ];
                if ($cell['colspan'] > 1) {
                    $attrs['colspan'] = $cell['colspan'];
                }
                if ($cell['rowspan'] > 1) {
                    $attrs['rowspan'] = $cell['rowspan'];
                }
                if ($cell['align'] !== 'default') {
                    $attrs['align'] = $cell['align'];
                }
                $cells[] = new AstNode('table_cell', $attrs, $inlines);
                if ($cell['rowspan'] > 1) {
                    for ($column = $logicalColumn; $column < $logicalColumn + $cell['colspan']; ++$column) {
                        $activeRowspans[$column] = max($activeRowspans[$column] ?? 0, $cell['rowspan'] - 1);
                    }
                }
                $logicalColumn += $cell['colspan'];
            }
            $tableRows[] = new AstNode('table_row', ['header' => $header], $cells);
        }

        return $tableRows;
    }

    /**
     * @param array{text:string, colspan:int, rowspan:int, align:string} $cell
     */
    private function tableCellIsRowspanPlaceholder(array $cell): bool
    {
        return trim($cell['text']) === ''
            && $cell['colspan'] === 1
            && $cell['rowspan'] === 1
            && $cell['align'] === 'default';
    }

    /**
     * @return array{0:string, 1:list<string>}
     */
    private function tabularPreambleArguments(string $content, string $environment): array
    {
        $cursor = 0;
        $arguments = [];
        $required = in_array($environment, ['tabular*', 'tabularx'], true) ? 2 : 1;
        while (count($arguments) < $required) {
            $group = $this->readRequiredGroup($content, $cursor);
            if ($group === null) {
                break;
            }
            $arguments[] = $group['value'];
            $cursor = $group['next'];
        }

        return [substr($content, $cursor), $arguments];
    }

    /**
     * @return array{environment:string, content:string, options:list<string>}|null
     */
    private function firstNestedTabularEnvironment(string $source): ?array
    {
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'begin') {
                continue;
            }
            $environment = $this->readEnvironmentAt($source, $offset, $command);
            if ($environment === null) {
                continue;
            }
            if (in_array($environment['environment'], ['tabular', 'tabular*', 'tabularx', 'longtable', 'array'], true)) {
                return [
                    'environment' => $environment['environment'],
                    'content' => $environment['content'],
                    'options' => $environment['options'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{url:string, options:string}|null
     */
    private function firstIncludeGraphics(string $source): ?array
    {
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'includegraphics') {
                continue;
            }
            $cursor = $command['next'];
            $option = $this->readOptionalGroup($source, $cursor);
            if ($option !== null) {
                $cursor = $option['next'];
            }
            $path = $this->readRequiredGroup($source, $cursor);
            if ($path === null) {
                continue;
            }
            $url = $this->normalizeImageReference($path['value']);
            if ($url === '') {
                $this->diagnostic('latex-includegraphics-invalid-path');

                return null;
            }

            return [
                'url' => $url,
                'options' => $option['value'] ?? '',
            ];
        }

        return null;
    }

    /**
     * @return array{text:string, short:string}
     */
    private function firstCaption(string $source): array
    {
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'caption') {
                continue;
            }
            $cursor = $command['next'];
            $short = $this->readOptionalGroup($source, $cursor);
            if ($short !== null) {
                $cursor = $short['next'];
            }
            $caption = $this->readRequiredGroup($source, $cursor);
            if ($caption === null) {
                continue;
            }

            return [
                'text' => trim($this->plainInlineText($this->parseInlines($caption['value']))),
                'short' => $short === null ? '' : trim($this->plainInlineText($this->parseInlines($short['value']))),
            ];
        }

        return ['text' => '', 'short' => ''];
    }

    private function firstLabel(string $source): string
    {
        $length = strlen($source);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($source[$offset] !== '\\') {
                continue;
            }
            $command = $this->commandAt($source, $offset);
            if ($command === null || $command['name'] !== 'label') {
                continue;
            }
            $cursor = $command['next'];
            $label = $this->readRequiredGroup($source, $cursor);
            if ($label !== null) {
                return $label['value'];
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function imageDimensionsFromOptions(string $options): array
    {
        $attrs = [];
        foreach (preg_split('/\s*,\s*/u', $options) ?: [] as $option) {
            if (preg_match('/^(width|height)\s*=\s*([0-9]+(?:\.[0-9]+)?)\\\\(?:textwidth|linewidth|columnwidth)$/iu', trim($option), $match) === 1) {
                $attrs[strtolower($match[1])] = rtrim(rtrim(number_format((float) $match[2] * 100, 3, '.', ''), '0'), '.') . '%';
                continue;
            }
            if (preg_match('/^(width|height)\s*=\s*([0-9]+(?:\.[0-9]+)?(?:px|pt|pc|in|cm|mm|em|rem|%))$/iu', trim($option), $match) === 1) {
                $attrs[strtolower($match[1])] = $match[2];
            }
        }

        return $attrs;
    }

    private function normalizeImageReference(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, "\0") || preg_match('/^[a-z][a-z0-9+.-]*:/iu', $path) === 1) {
            return '';
        }
        $path = ltrim($path, '/');
        $path = preg_replace('#^(?:\./)+#', '', $path) ?? $path;
        if ($path === '' || in_array('..', explode('/', $path), true)) {
            return '';
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    private function tabularAlignments(string $specification): array
    {
        $alignments = [];
        $length = strlen($specification);
        for ($offset = 0; $offset < $length; ++$offset) {
            $char = strtolower($specification[$offset]);
            if ($char === '\\') {
                $command = $this->commandAt($specification, $offset);
                if ($command !== null) {
                    $offset = $command['next'] - 1;
                }
                continue;
            }
            if (in_array($char, ['@', '!', '>', '<'], true) && ($specification[$offset + 1] ?? '') === '{') {
                $group = $this->readBalancedGroup($specification, $offset + 1, '{', '}');
                if ($group !== null) {
                    $offset = $group['next'] - 1;
                }
                continue;
            }
            if ($char === '*' && ($specification[$offset + 1] ?? '') === '{') {
                $count = $this->readBalancedGroup($specification, $offset + 1, '{', '}');
                $template = $count === null ? null : $this->readBalancedGroup($specification, $count['next'], '{', '}');
                if ($count !== null && $template !== null && preg_match('/^\d+$/', trim($count['value'])) === 1) {
                    $repetitions = min(64, (int) trim($count['value']));
                    for ($repeat = 0; $repeat < $repetitions; ++$repeat) {
                        array_push($alignments, ...$this->tabularAlignments($template['value']));
                    }
                    $offset = $template['next'] - 1;
                    continue;
                }
            }
            if ($char === 'l') {
                $alignments[] = 'left';
                continue;
            }
            if ($char === 'c') {
                $alignments[] = 'center';
                continue;
            }
            if ($char === 'x') {
                $alignments[] = 'left';
                continue;
            }
            if ($char === 'r') {
                $alignments[] = 'right';
                continue;
            }
            if (in_array($char, ['p', 'm', 'b'], true)) {
                $alignments[] = 'left';
                $cursor = $offset + 1;
                $group = $this->readRequiredGroup($specification, $cursor);
                if ($group !== null) {
                    $offset = $group['next'] - 1;
                }
            }
        }

        return $alignments;
    }

    /**
     * @return list<array{cells:list<array{text:string, colspan:int, rowspan:int, align:string}>, columnCount:int}>
     */
    private function tabularRows(string $content): array
    {
        $content = preg_replace('/\\\\(?:hline|toprule|midrule|bottomrule|addlinespace)\b(?:\s*\[[^\]]*\])?/u', "\n", $content) ?? $content;
        $content = preg_replace('/\\\\cline\s*\{[^}]*\}/u', "\n", $content) ?? $content;
        $content = preg_replace('/\\\\(?:endfirsthead|endhead|endfoot|endlastfoot)\b/u', "\n", $content) ?? $content;
        $rows = [];
        foreach ($this->splitTopLevel($content, '\\\\') as $rawRow) {
            $rawRow = trim($rawRow);
            if ($rawRow === '') {
                continue;
            }
            $rawRow = preg_replace('/\\\\(?:caption|label)\s*(?:\[[^\]]*\])?\s*\{(?:\\\\.|[^{}])*\}/us', '', $rawRow) ?? $rawRow;
            if (trim($rawRow) === '') {
                continue;
            }
            $cells = [];
            $columnCount = 0;
            foreach ($this->splitTopLevel($rawRow, '&') as $rawCell) {
                $cell = $this->tabularCell($rawCell);
                $cells[] = $cell;
                $columnCount += max(1, $cell['colspan']);
            }
            if ($cells !== []) {
                $rows[] = ['cells' => $cells, 'columnCount' => $columnCount];
            }
        }

        return $rows;
    }

    /**
     * @return array{text:string, colspan:int, rowspan:int, align:string}
     */
    private function tabularCell(string $source): array
    {
        $source = trim($source);
        $cell = [
            'text' => $source,
            'colspan' => 1,
            'rowspan' => 1,
            'align' => 'default',
        ];
        $cursor = 0;
        while (($command = $this->commandAt($cell['text'], $cursor)) !== null
            && in_array($command['name'], ['multicolumn', 'multirow'], true)) {
            $argumentCursor = $command['next'];
            $first = $this->readRequiredGroup($cell['text'], $argumentCursor);
            $second = $first === null ? null : $this->readRequiredGroup($cell['text'], $first['next']);
            $third = $second === null ? null : $this->readRequiredGroup($cell['text'], $second['next']);
            if ($first === null || $second === null || $third === null) {
                break;
            }
            if (trim(substr($cell['text'], $third['next'])) !== '') {
                break;
            }
            if ($command['name'] === 'multicolumn') {
                $cell['colspan'] = max(1, (int) trim($first['value']));
                $cell['align'] = $this->alignmentFromSpec($second['value']);
            } else {
                $rowspan = trim($first['value']);
                if (preg_match('/^\d+$/', $rowspan) === 1) {
                    $cell['rowspan'] = max(1, (int) $rowspan);
                }
            }
            $cell['text'] = trim($third['value']);
            $cursor = 0;
        }

        return $cell;
    }

    private function alignmentFromSpec(string $specification): string
    {
        $specification = strtolower($specification);
        if (str_contains($specification, 'r')) {
            return 'right';
        }
        if (str_contains($specification, 'c')) {
            return 'center';
        }
        if (str_contains($specification, 'l') || preg_match('/[pmb]/', $specification) === 1) {
            return 'left';
        }

        return 'default';
    }

    /**
     * @return array{head:string, body:string, foot:string}|null
     */
    private function longtableSections(string $content): ?array
    {
        if (preg_match('/\\\\end(?:firsthead|head|foot|lastfoot)\b/u', $content) !== 1) {
            return null;
        }
        $parts = preg_split(
            '/\\\\(endfirsthead|endhead|endfoot|endlastfoot)\b/u',
            $content,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        if (!is_array($parts) || $parts === []) {
            return null;
        }
        $initial = (string) ($parts[0] ?? '');
        $after = [];
        for ($index = 1, $count = count($parts); $index + 1 < $count; $index += 2) {
            $after[(string) $parts[$index]] = (string) $parts[$index + 1];
        }
        $hasFirstHead = array_key_exists('endfirsthead', $after);
        $hasHead = array_key_exists('endhead', $after);
        $head = $hasFirstHead || $hasHead ? $initial : '';
        if ($head === '' && $hasFirstHead) {
            $head = (string) $after['endfirsthead'];
        }
        $body = $after['endlastfoot']
            ?? $after['endfoot']
            ?? $after['endhead']
            ?? $after['endfirsthead']
            ?? $initial;
        $foot = $after['endfoot'] ?? '';
        if ($foot === '' && isset($after['endhead'], $after['endfoot'])) {
            $foot = $after['endhead'];
        }

        return [
            'head' => $head,
            'body' => $body,
            'foot' => $foot,
        ];
    }

    /**
     * @param list<array{cells:list<array{text:string, colspan:int, rowspan:int, align:string}>, columnCount:int}> $rows
     */
    private function tabularHeaderRowCount(string $content, array $rows): int
    {
        if (str_contains($content, '\\endfirsthead')) {
            $before = substr($content, 0, strpos($content, '\\endfirsthead'));

            return min(count($rows), count($this->tabularRows($before)));
        }
        $midrule = strpos($content, '\\midrule');
        if ($midrule === false) {
            return 0;
        }
        $before = substr($content, 0, $midrule);

        return min(count($rows), count($this->tabularRows($before)));
    }

    /**
     * Split by a literal top-level delimiter, keeping nested braces intact.
     *
     * @return list<string>
     */
    private function splitTopLevel(string $source, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $length = strlen($source);
        $delimiterLength = strlen($delimiter);
        for ($offset = 0; $offset < $length; ++$offset) {
            if ($depth === 0 && substr($source, $offset, $delimiterLength) === $delimiter) {
                $parts[] = substr($source, $start, $offset - $start);
                $offset += $delimiterLength - 1;
                $start = $offset + 1;
                continue;
            }
            if ($source[$offset] === '\\') {
                ++$offset;
                continue;
            }
            if ($source[$offset] === '{') {
                ++$depth;
                continue;
            }
            if ($source[$offset] === '}' && $depth > 0) {
                --$depth;
                continue;
            }
        }
        $parts[] = substr($source, $start);

        return $parts;
    }

    /**
     * @return list<AstNode>
     */
    private function manualBibliographyBlocks(string $content): array
    {
        $entries = [];
        $matches = [];
        if (preg_match_all('/\\\\bibitem(?:\[[^\]]*\])?\s*\{([^}]*)\}/u', $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [new AstNode('raw_tex', ['tex' => $content, 'environment' => 'thebibliography'])];
        }
        foreach ($matches[0] as $index => $match) {
            $start = $match[1];
            $after = $start + strlen($match[0]);
            $next = $matches[0][$index + 1][1] ?? strlen($content);
            $id = $this->cleanLabel((string) ($matches[1][$index][0] ?? ''));
            $text = trim($this->plainInlineText($this->parseInlines(substr($content, $after, $next - $after))));
            $term = $id === '' ? 'Reference ' . ($index + 1) : $id;
            $termInlines = $this->parseInlines($term);
            $valueInlines = $this->parseInlines($text);
            $entries[] = new AstNode('definition_item', ['term' => $term], [
                new AstNode('definition_term', ['text' => $term], $termInlines),
                new AstNode('definition', [], [
                    new AstNode('paragraph', ['text' => $this->plainInlineText($valueInlines)], $valueInlines),
                ]),
            ]);
        }

        return $entries === [] ? [] : [new AstNode('definition_list', ['classes' => ['latex-bibliography']], $entries)];
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $source, int $macroDepth = 0, ?int $sourceOffset = null): array
    {
        $nodes = [];
        $text = '';
        $length = strlen($source);
        for ($offset = 0; $offset < $length;) {
            $char = $source[$offset];
            if ($char === '$' && !$this->isEscaped($source, $offset)) {
                $display = ($source[$offset + 1] ?? '') === '$';
                $delimiter = $display ? '$$' : '$';
                $end = $this->findUnescapedDelimiter($source, $delimiter, $offset + strlen($delimiter));
                if ($end !== null) {
                    $this->appendText($nodes, $text);
                    $math = trim(substr($source, $offset + strlen($delimiter), $end - $offset - strlen($delimiter)));
                    $nodes[] = new AstNode('math', ['display' => $display, 'text' => $math]);
                    $offset = $end + strlen($delimiter);
                    continue;
                }
            }
            if ($char === '\\') {
                $command = $this->commandAt($source, $offset);
                if ($command !== null) {
                    $parsed = $this->parseInlineCommand($source, $offset, $command, $macroDepth, $sourceOffset);
                    if ($parsed !== null) {
                        $this->appendText($nodes, $text);
                        foreach ($parsed['nodes'] as $node) {
                            $nodes[] = $node;
                        }
                        $offset = $parsed['next'];
                        continue;
                    }
                }
            }
            if ($char === '{' && !$this->isEscaped($source, $offset)) {
                $group = $this->readBalancedGroup($source, $offset, '{', '}');
                if ($group !== null) {
                    $this->appendText($nodes, $text);
                        foreach ($this->parseInlines(
                            $group['value'],
                            $macroDepth,
                            $sourceOffset === null ? null : $sourceOffset + $offset + 1
                        ) as $node) {
                        $nodes[] = $node;
                    }
                    $offset = $group['next'];
                    continue;
                }
            }
            if ($char === '~') {
                $text .= ' ';
                ++$offset;
                continue;
            }
            if (ctype_space($char)) {
                $text .= ' ';
                while ($offset < $length && ctype_space($source[$offset])) {
                    ++$offset;
                }
                continue;
            }
            $text .= $char;
            ++$offset;
        }
        $this->appendText($nodes, $text);

        return $nodes;
    }

    /**
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function parseInlineCommand(
        string $source,
        int $offset,
        array $command,
        int $macroDepth,
        ?int $sourceOffset = null
    ): ?array
    {
        $name = $command['name'];
        $cursor = $command['next'];
        if ($name === '[') {
            $end = $this->findUnescapedDelimiter($source, '\\]', $cursor);
            if ($end !== null) {
                return [
                    'nodes' => [new AstNode('math', [
                        'display' => true,
                        'text' => trim(substr($source, $cursor, $end - $cursor)),
                    ])],
                    'next' => $end + 2,
                ];
            }
        }
        if ($name === '(') {
            $end = $this->findUnescapedDelimiter($source, '\\)', $cursor);
            if ($end !== null) {
                return [
                    'nodes' => [new AstNode('math', [
                        'display' => false,
                        'text' => trim(substr($source, $cursor, $end - $cursor)),
                    ])],
                    'next' => $end + 2,
                ];
            }
        }
        if ($name === 'verb') {
            return $this->parseVerbInline($source, $offset, $cursor);
        }
        if (in_array($name, ['\\', 'newline', 'linebreak'], true)) {
            return ['nodes' => [new AstNode('linebreak')], 'next' => $cursor];
        }
        if (in_array($name, [' ', ',', ';', ':', '!', 'quad', 'qquad'], true)) {
            return ['nodes' => [new AstNode('space')], 'next' => $cursor];
        }
        if (in_array($name, ['%', '_', '#', '$', '&', '{', '}'], true)) {
            return ['nodes' => [new AstNode('text', ['text' => $name])], 'next' => $cursor];
        }
        if (isset($this->macros[$name])) {
            return $this->expandInlineMacro($source, $offset, $cursor, $name, $macroDepth);
        }
        $legacyFormatDeclarations = [
            'em' => 'emph',
            'it' => 'emph',
            'sl' => 'emph',
            'bf' => 'strong',
            'sc' => 'small_caps',
        ];
        if (isset($legacyFormatDeclarations[$name])) {
            $remaining = preg_replace('/^\s+/u', '', substr($source, $cursor)) ?? substr($source, $cursor);
            if (trim($remaining) === '') {
                return ['nodes' => [], 'next' => $cursor];
            }

            return [
                'nodes' => [new AstNode($legacyFormatDeclarations[$name], [], $this->parseInlines($remaining, $macroDepth))],
                'next' => strlen($source),
            ];
        }
        $formatCommands = [
            'textbf' => 'strong',
            'mathbf' => 'strong',
            'textit' => 'emph',
            'textsl' => 'emph',
            'emph' => 'emph',
            'textsc' => 'small_caps',
            'underline' => 'underline',
            'uline' => 'underline',
            'sout' => 'strikeout',
            'st' => 'strikeout',
            'textsuperscript' => 'superscript',
            'textsubscript' => 'subscript',
        ];
        if (isset($formatCommands[$name])) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                return [
                    'nodes' => [new AstNode($formatCommands[$name], [], $this->parseInlines($argument['value'], $macroDepth))],
                    'next' => $argument['next'],
                ];
            }
        }
        if (in_array($name, ['texttt', 'verbinline'], true)) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                return [
                    'nodes' => [new AstNode('code', ['text' => $this->stripSimpleTexEscapes($argument['value'])])],
                    'next' => $argument['next'],
                ];
            }
        }
        if ($name === 'ensuremath') {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                return [
                    'nodes' => [new AstNode('math', ['display' => false, 'text' => trim($argument['value'])])],
                    'next' => $argument['next'],
                ];
            }
        }
        if ($name === 'href') {
            $url = $this->readRequiredGroup($source, $cursor);
            if ($url !== null) {
                $textCursor = $url['next'];
                $label = $this->readRequiredGroup($source, $textCursor);
                if ($label !== null) {
                    return [
                        'nodes' => [new AstNode('link', [
                            'url' => trim($url['value']),
                            'title' => '',
                        ], $this->parseInlines($label['value'], $macroDepth))],
                        'next' => $label['next'],
                    ];
                }
            }
        }
        if ($name === 'url') {
            $url = $this->readRequiredGroup($source, $cursor);
            if ($url !== null) {
                $value = trim($url['value']);

                return [
                    'nodes' => [new AstNode('link', ['url' => $value, 'title' => ''], [new AstNode('text', ['text' => $value])])],
                    'next' => $url['next'],
                ];
            }
        }
        if ($name === 'hyperref') {
            $target = $this->readOptionalGroup($source, $cursor);
            if ($target !== null) {
                $labelCursor = $target['next'];
                $label = $this->readRequiredGroup($source, $labelCursor);
                if ($label !== null) {
                    $key = $this->cleanLabel($target['value']);

                    return [
                        'nodes' => [new AstNode('link', [
                            'url' => '#' . $this->labelId($key),
                            'title' => '',
                            'latexReferenceKey' => $key,
                        ], $this->parseInlines($label['value'], $macroDepth))],
                        'next' => $label['next'],
                    ];
                }
            }
        }
        if (in_array($name, ['ref', 'eqref', 'pageref', 'autoref', 'nameref'], true)) {
            $target = $this->readRequiredGroup($source, $cursor);
            if ($target !== null) {
                $key = $this->cleanLabel($target['value']);

                return [
                    'nodes' => [new AstNode('link', [
                        'url' => '#' . $this->labelId($key),
                        'title' => '',
                        'latexReferenceKey' => $key,
                        'latexReferenceCommand' => $name,
                    ], [new AstNode('text', ['text' => $key])])],
                    'next' => $target['next'],
                ];
            }
        }
        if ($name === 'footnote') {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument !== null) {
                $blocks = $this->parseBlocks($argument['value'], 0);
                if ($blocks === []) {
                    $inlines = $this->parseInlines($argument['value'], $macroDepth);
                    $blocks = [new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines)];
                }

                return [
                    'nodes' => [new AstNode('note', [], $blocks)],
                    'next' => $argument['next'],
                ];
            }
        }
        if (in_array($name, [
            'cite', 'citep', 'citet', 'citealp', 'citealt', 'citeauthor', 'citefullauthor',
            'citeyear', 'citeyearpar', 'citefull', 'parencite', 'textcite', 'autocite',
            'smartcite', 'footcite', 'footcitetext', 'supercite',
        ], true)) {
            $citation = $this->parseCitationCommand($source, $offset, $command);
            if ($citation !== null) {
                if (in_array($name, ['footcite', 'footcitetext'], true)) {
                    $inlines = $citation['nodes'];

                    return [
                        'nodes' => [new AstNode('note', [], [
                            new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines),
                        ])],
                        'next' => $citation['next'],
                    ];
                }
                if ($name === 'supercite') {
                    return [
                        'nodes' => [new AstNode('superscript', [], $citation['nodes'])],
                        'next' => $citation['next'],
                    ];
                }

                return $citation;
            }
        }
        if ($name === 'includegraphics') {
            $option = $this->readOptionalGroup($source, $cursor);
            if ($option !== null) {
                $cursor = $option['next'];
            }
            $path = $this->readRequiredGroup($source, $cursor);
            if ($path !== null) {
                $url = $this->normalizeImageReference($path['value']);
                if ($url !== '') {
                    $attrs = [
                        'url' => $url,
                        'alt' => '',
                        'title' => '',
                        'latexOptions' => $option['value'] ?? '',
                    ];
                    foreach ($this->imageDimensionsFromOptions($option['value'] ?? '') as $attribute => $value) {
                        $attrs[$attribute] = $value;
                    }

                    return ['nodes' => [new AstNode('image', $attrs)], 'next' => $path['next']];
                }
            }
        }
        $namedSymbols = [
            'latex' => 'LaTeX',
            'tex' => 'TeX',
            'bibtex' => 'BibTeX',
            'and' => ' ',
            'ldots' => '...',
            'dots' => '...',
            'textbackslash' => '\\',
            'textasciitilde' => '~',
            'textasciicircum' => '^',
            'qed' => '□',
            'qedhere' => '□',
        ];
        if (isset($namedSymbols[$name])) {
            return ['nodes' => [new AstNode('text', ['text' => $namedSymbols[$name]])], 'next' => $cursor];
        }
        if ($name === 'label') {
            $label = $this->readRequiredGroup($source, $cursor);
            if ($label !== null) {
                $this->registerLabel($label['value'], '', 'inline');

                return ['nodes' => [], 'next' => $label['next']];
            }
        }
        if (in_array($name, ['newcommand', 'renewcommand', 'providecommand'], true)) {
            $definition = $this->readMacroDefinition(
                $source,
                $offset,
                $command,
                $sourceOffset === null ? null : $this->sourceLocationAtOffset($sourceOffset + $offset, 'command', $name)
            );
            if ($definition !== null) {
                $this->registerMacro($definition);

                return ['nodes' => [], 'next' => $definition['next']];
            }
            $rawDefinition = $this->readRawMacroDefinition($source, $offset, $command);
            if ($rawDefinition !== null) {
                $location = $sourceOffset === null
                    ? null
                    : $this->sourceLocationAtOffset($sourceOffset + $offset, 'command', $name);
                return [
                    'nodes' => [new AstNode('raw_tex_inline', [
                        'tex' => $rawDefinition['source'],
                        'command' => $name,
                        'latexSourceLocation' => $location,
                    ])],
                    'next' => $rawDefinition['next'],
                ];
            }
        }

        $raw = $this->readRawInlineCommand($source, $offset, $command);
        $location = $sourceOffset === null
            ? null
            : $this->sourceLocationAtOffset($sourceOffset + $offset, 'command', $name);
        $diagnostic = 'latex-unsupported-command:' . ($name === '' ? 'unknown' : $name);
        $this->diagnostic($diagnostic, $location);

        return [
            'nodes' => [new AstNode('raw_tex_inline', [
                'tex' => $raw['source'],
                'command' => $name,
                'latexDiagnostic' => $diagnostic,
                'latexSourceLocation' => $location,
            ])],
            'next' => $raw['next'],
        ];
    }

    /**
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function parseVerbInline(string $source, int $offset, int $cursor): ?array
    {
        if (($source[$cursor] ?? '') === '*') {
            ++$cursor;
        }
        $delimiter = $source[$cursor] ?? '';
        if ($delimiter === '' || ctype_space($delimiter)) {
            return null;
        }
        $end = strpos($source, $delimiter, $cursor + 1);
        if ($end === false) {
            return null;
        }

        return [
            'nodes' => [new AstNode('code', ['text' => substr($source, $cursor + 1, $end - $cursor - 1)])],
            'next' => $end + 1,
        ];
    }

    /**
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function expandInlineMacro(string $source, int $offset, int $cursor, string $name, int $macroDepth): ?array
    {
        if ($macroDepth >= 8) {
            $this->diagnostic('latex-macro-expansion-depth:' . $name);

            return null;
        }
        $definition = $this->macros[$name];
        $arguments = [];
        for ($index = 0; $index < $definition['arity']; ++$index) {
            $argument = $this->readRequiredGroup($source, $cursor);
            if ($argument === null) {
                return null;
            }
            $arguments[] = $argument['value'];
            $cursor = $argument['next'];
        }
        $expanded = $definition['template'];
        foreach ($arguments as $index => $argument) {
            $expanded = str_replace('#' . ($index + 1), $argument, $expanded);
        }

        return [
            'nodes' => $this->parseInlines($expanded, $macroDepth + 1),
            'next' => $cursor,
        ];
    }

    /**
     * @return array{nodes:list<AstNode>, next:int}|null
     */
    private function parseCitationCommand(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        $options = [];
        while (($option = $this->readOptionalGroup($source, $cursor)) !== null) {
            $options[] = $option['value'];
            $cursor = $option['next'];
        }
        $keys = $this->readRequiredGroup($source, $cursor);
        if ($keys === null) {
            return null;
        }
        $ids = array_values(array_filter(array_map([$this, 'cleanLabel'], preg_split('/\s*,\s*/u', $keys['value']) ?: []), static fn (string $id): bool => $id !== ''));
        if ($ids === []) {
            return null;
        }
        $sourceText = substr($source, $offset, $keys['next'] - $offset);
        $mode = in_array($command['name'], ['citet', 'citefull', 'textcite'], true)
            ? 'author_in_text'
            : (in_array($command['name'], ['citeauthor', 'citefullauthor'], true)
                ? 'author_only'
                : (in_array($command['name'], ['citeyear', 'citeyearpar'], true) ? 'suppress_author' : 'normal'));
        $suppressWrapper = in_array($command['name'], ['citealp', 'citealt'], true);
        if ($command['name'] === 'citealt') {
            $mode = 'author_in_text';
        }
        $entries = [];
        foreach ($ids as $index => $id) {
            $attrs = [
                'id' => $id,
                'text' => $sourceText,
                'mode' => $mode,
                'sourceFormat' => 'latex',
                'sourceCommand' => $command['name'],
                'sourceStarred' => $command['starred'],
            ];
            if ($suppressWrapper) {
                $attrs['suppressWrapper'] = true;
            }
            if ($options !== []) {
                $suffix = trim((string) $options[array_key_last($options)]);
                if ($suffix !== '' && $index === count($ids) - 1) {
                    $attrs['suffix'] = $suffix;
                }
                if (count($options) > 1 && trim($options[0]) !== '' && $index === 0) {
                    $attrs['prefix'] = trim($options[0]);
                }
            }
            $entries[] = new AstNode('citation', $attrs, [new AstNode('text', ['text' => $sourceText])]);
        }
        if (count($entries) === 1) {
            return ['nodes' => [$entries[0]], 'next' => $keys['next']];
        }

        return [
            'nodes' => [new AstNode('citation_group', ['text' => $sourceText, 'sourceFormat' => 'latex'], $entries)],
            'next' => $keys['next'],
        ];
    }

    /**
     * @return array{source:string, next:int}
     */
    private function readRawInlineCommand(string $source, int $offset, array $command): array
    {
        $cursor = $command['next'];
        $limit = 2;
        while ($limit > 0) {
            $next = $cursor;
            $optional = $this->readOptionalGroup($source, $next);
            if ($optional !== null) {
                $cursor = $optional['next'];
                --$limit;
                continue;
            }
            $required = $this->readRequiredGroup($source, $next);
            if ($required !== null) {
                $cursor = $required['next'];
                --$limit;
                continue;
            }
            break;
        }

        return ['source' => substr($source, $offset, $cursor - $offset), 'next' => $cursor];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function appendText(array &$nodes, string &$text): void
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if ($text === '') {
            return;
        }
        $lastIndex = array_key_last($nodes);
        $last = $lastIndex === null ? null : $nodes[$lastIndex];
        if ($last instanceof AstNode && $last->type === 'text') {
            $nodes[$lastIndex] = new AstNode('text', ['text' => (string) $last->attr('text', '') . $text]);
        } else {
            $nodes[] = new AstNode('text', ['text' => $text]);
        }
        $text = '';
    }

    private function stripSimpleTexEscapes(string $text): string
    {
        return strtr($text, [
            '\\%' => '%',
            '\\_' => '_',
            '\\#' => '#',
            '\\$' => '$',
            '\\&' => '&',
            '\\{' => '{',
            '\\}' => '}',
            '\\textbackslash' => '\\',
        ]);
    }

    private function stripMathLabels(string $text): string
    {
        return preg_replace('/\\\\label\s*\{(?:\\\\.|[^{}])*\}/u', '', $text) ?? $text;
    }

    private function containsNonTextInline(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!in_array($node->type, ['text', 'space', 'softbreak'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (in_array($node->type, ['text', 'code', 'math'], true)) {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
                $text .= ' ';
                continue;
            }
            if ($node->type === 'image') {
                $text .= (string) $node->attr('alt', '');
                continue;
            }
            $text .= $this->plainInlineText($node->children);
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return list<AstNode>
     */
    private function readIncludedBlocks(string $rawPath, int $depth, string $command = 'input'): array
    {
        $source = '\\' . $command . '{' . $rawPath . '}';
        if (!$this->includesEnabled()) {
            $this->diagnostic('latex-include-disabled:' . $this->diagnosticPath($rawPath));

            return [new AstNode('raw_tex', ['tex' => $source, 'command' => $command])];
        }
        if ($depth >= $this->maxIncludeDepth()) {
            $this->diagnostic('latex-include-depth-limit:' . $this->diagnosticPath($rawPath));

            return [new AstNode('raw_tex', ['tex' => $source, 'command' => $command])];
        }
        $file = $this->readLocalSourceFile($rawPath, ['tex']);
        if ($file === null) {
            return [new AstNode('raw_tex', ['tex' => $source, 'command' => $command])];
        }
        if ($this->includedBytes + strlen($file['bytes']) > $this->maxIncludedBytes()) {
            $this->diagnostic('latex-include-byte-limit:' . $this->diagnosticPath($rawPath));

            return [new AstNode('raw_tex', ['tex' => $source, 'command' => $command])];
        }
        $this->includedBytes += strlen($file['bytes']);
        $this->includedFiles[] = $file['relative'];

        return $this->parseBlocks(
            $this->stripCommentsOutsideLiteralEnvironments(str_replace(["\r\n", "\r"], "\n", $file['bytes'])),
            $depth + 1,
            $file['relative'],
            0
        );
    }

    private function loadBibliographyResources(string $rawPaths): void
    {
        foreach (preg_split('/\s*,\s*/u', $rawPaths) ?: [] as $rawPath) {
            $rawPath = trim($rawPath);
            if ($rawPath === '') {
                continue;
            }
            $file = $this->readLocalSourceFile($rawPath, ['bib']);
            if ($file === null) {
                continue;
            }
            foreach ($this->bibliographyFiles as $existing) {
                if ($existing['path'] === $file['relative']) {
                    continue 2;
                }
            }
            $this->bibliographyFiles[] = [
                'path' => $file['relative'],
                'bytes' => $file['bytes'],
            ];
        }
    }

    /**
     * @param list<string> $allowedExtensions
     * @return array{path:string, relative:string, bytes:string}|null
     */
    private function readLocalSourceFile(string $rawPath, array $allowedExtensions): ?array
    {
        if ($this->includeRootDirectory === null) {
            $this->diagnostic('latex-local-source-unavailable:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $path = trim(str_replace('\\', '/', $rawPath));
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/iu', $path) === 1) {
            $this->diagnostic('latex-local-source-invalid:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $path = preg_replace('#^(?:\./)+#', '', $path) ?? $path;
        $segments = explode('/', $path);
        if ($path === '' || in_array('..', $segments, true)) {
            $this->diagnostic('latex-local-source-outside-root:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === '') {
            $path .= '.' . $allowedExtensions[0];
            $extension = $allowedExtensions[0];
        }
        if (!in_array($extension, $allowedExtensions, true)) {
            $this->diagnostic('latex-local-source-extension:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $candidate = $this->includeRootDirectory . DIRECTORY_SEPARATOR . $path;
        $resolved = realpath($candidate);
        $prefix = rtrim($this->includeRootDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_string($resolved) || !is_file($resolved) || !str_starts_with($resolved, $prefix)) {
            $this->diagnostic('latex-local-source-missing:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $bytes = file_get_contents($resolved);
        if (!is_string($bytes)) {
            $this->diagnostic('latex-local-source-unreadable:' . $this->diagnosticPath($rawPath));

            return null;
        }
        if (strlen($bytes) > $this->maxIncludedBytes()) {
            $this->diagnostic('latex-local-source-too-large:' . $this->diagnosticPath($rawPath));

            return null;
        }
        $relative = ltrim(str_replace('\\', '/', substr($resolved, strlen($this->includeRootDirectory))), '/');

        return ['path' => $resolved, 'relative' => $relative, 'bytes' => $bytes];
    }

    /**
     * @return array{name:string, arity:int, template:string, next:int}|null
     */
    private function readMacroDefinition(string $source, int $offset, array $command, ?array $location = null): ?array
    {
        $cursor = $command['next'];
        if (($source[$cursor] ?? '') === '*') {
            ++$cursor;
        }
        $macro = $this->readMacroTarget($source, $cursor);
        if ($macro === null) {
            return null;
        }
        $name = trim($macro['value']);
        if (preg_match('/^\\\\([A-Za-z@]+)$/', $name, $match) !== 1) {
            $this->diagnostic('latex-macro-name-unsupported', $location);

            return null;
        }
        $cursor = $macro['next'];
        $arity = 0;
        $arityOption = $this->readOptionalGroup($source, $cursor);
        if ($arityOption !== null) {
            if (preg_match('/^\d+$/', trim($arityOption['value'])) !== 1) {
                $this->diagnostic('latex-macro-optional-default-unsupported:' . $match[1], $location);

                return null;
            }
            $arity = (int) trim($arityOption['value']);
            $cursor = $arityOption['next'];
        }
        $body = $this->readRequiredGroup($source, $cursor);
        if ($body === null || $arity > 4) {
            $this->diagnostic('latex-macro-arity-unsupported:' . $match[1], $location);

            return null;
        }
        if (preg_match('/\\\\(?:input|include|write|openout|read|catcode|csname|expandafter|directlua|shellescape)\b/iu', $body['value']) === 1) {
            $this->diagnostic('latex-macro-unsafe-body:' . $match[1], $location);

            return null;
        }

        return [
            'name' => strtolower($match[1]),
            'arity' => $arity,
            'template' => $body['value'],
            'next' => $body['next'],
        ];
    }

    /**
     * Consume a macro declaration without interpreting it, so unsupported
     * declarations remain intact in the imported document.
     *
     * @return array{source:string, next:int}|null
     */
    private function readRawMacroDefinition(string $source, int $offset, array $command): ?array
    {
        $cursor = $command['next'];
        if (($source[$cursor] ?? '') === '*') {
            ++$cursor;
        }
        $macro = $this->readMacroTarget($source, $cursor);
        if ($macro === null) {
            return null;
        }
        while (($option = $this->readOptionalGroup($source, $cursor)) !== null) {
            $cursor = $option['next'];
        }
        $body = $this->readRequiredGroup($source, $cursor);
        if ($body === null) {
            return null;
        }

        return [
            'source' => substr($source, $offset, $body['next'] - $offset),
            'next' => $body['next'],
        ];
    }

    /**
     * Read either the braced or standard unbraced command target accepted by
     * LaTeX macro declarations.
     *
     * @return array{value:string, next:int}|null
     */
    private function readMacroTarget(string $source, int &$offset): ?array
    {
        $this->skipWhitespace($source, $offset);
        $group = $this->readBalancedGroup($source, $offset, '{', '}');
        if ($group !== null) {
            $offset = $group['next'];

            return $group;
        }
        $command = $this->commandAt($source, $offset);
        if ($command === null || preg_match('/^[A-Za-z@]+$/', $command['name']) !== 1) {
            return null;
        }
        $offset = $command['next'];

        return [
            'value' => '\\' . $command['name'],
            'next' => $offset,
        ];
    }

    /**
     * @param array{name:string, arity:int, template:string, next:int} $definition
     */
    private function registerMacro(array $definition): void
    {
        $this->macros[$definition['name']] = [
            'arity' => $definition['arity'],
            'template' => $definition['template'],
        ];
    }

    /**
     * @return array{name:string, starred:bool, next:int}|null
     */
    private function commandAt(string $source, int $offset): ?array
    {
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }
        $cursor = $offset + 1;
        $first = $source[$cursor] ?? '';
        if ($first === '') {
            return null;
        }
        if (preg_match('/[A-Za-z@]/', $first) === 1) {
            $start = $cursor;
            while (($char = $source[$cursor] ?? '') !== '' && preg_match('/[A-Za-z@]/', $char) === 1) {
                ++$cursor;
            }
            $name = strtolower(substr($source, $start, $cursor - $start));
        } else {
            $name = $first;
            ++$cursor;
        }
        $starred = false;
        if (($source[$cursor] ?? '') === '*') {
            $starred = true;
            ++$cursor;
        }

        return ['name' => $name, 'starred' => $starred, 'next' => $cursor];
    }

    /**
     * @return array{options:list<string>, groups:list<string>, next:int}|null
     */
    private function readCommandArguments(string $source, int $offset, int $requiredGroups): ?array
    {
        $cursor = $offset;
        $options = [];
        while (($optional = $this->readOptionalGroup($source, $cursor)) !== null) {
            $options[] = $optional['value'];
            $cursor = $optional['next'];
        }
        $groups = [];
        for ($index = 0; $index < $requiredGroups; ++$index) {
            $group = $this->readRequiredGroup($source, $cursor);
            if ($group === null) {
                return null;
            }
            $groups[] = $group['value'];
            $cursor = $group['next'];
        }

        return ['options' => $options, 'groups' => $groups, 'next' => $cursor];
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readRequiredGroup(string $source, int &$offset): ?array
    {
        $this->skipWhitespace($source, $offset);
        $group = $this->readBalancedGroup($source, $offset, '{', '}');
        if ($group !== null) {
            $offset = $group['next'];
        }

        return $group;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readOptionalGroup(string $source, int &$offset): ?array
    {
        $this->skipWhitespace($source, $offset);
        $group = $this->readBalancedGroup($source, $offset, '[', ']');
        if ($group !== null) {
            $offset = $group['next'];
        }

        return $group;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readBalancedGroup(string $source, int $offset, string $open, string $close): ?array
    {
        if (($source[$offset] ?? '') !== $open) {
            return null;
        }
        $depth = 1;
        $cursor = $offset + 1;
        $start = $cursor;
        $length = strlen($source);
        while ($cursor < $length) {
            if ($source[$cursor] === '\\') {
                $cursor += 2;
                continue;
            }
            if ($source[$cursor] === $open) {
                ++$depth;
            } elseif ($source[$cursor] === $close) {
                --$depth;
                if ($depth === 0) {
                    return [
                        'value' => substr($source, $start, $cursor - $start),
                        'next' => $cursor + 1,
                    ];
                }
            }
            ++$cursor;
        }

        return null;
    }

    private function skipOptionalArguments(string $source, int $offset): int
    {
        $cursor = $offset;
        while (($group = $this->readOptionalGroup($source, $cursor)) !== null) {
            $cursor = $group['next'];
        }

        return $cursor;
    }

    private function skipWhitespace(string $source, int &$offset): void
    {
        $length = strlen($source);
        while ($offset < $length && ctype_space($source[$offset])) {
            ++$offset;
        }
    }

    private function skipBlockWhitespace(string $source, int &$offset): void
    {
        $this->skipWhitespace($source, $offset);
    }

    private function readParagraph(string $source, int &$offset): string
    {
        $start = $offset;
        $cursor = $offset;
        $braceDepth = 0;
        $length = strlen($source);
        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($char === '\\' && !$this->isEscaped($source, $cursor) && $braceDepth === 0) {
                $command = $this->commandAt($source, $cursor);
                if ($command !== null && $this->isBlockCommand($command['name'])) {
                    break;
                }
            }
            if ($char === "\n" && $braceDepth === 0 && $this->blankLineAt($source, $cursor)) {
                break;
            }
            if ($char === '{' && !$this->isEscaped($source, $cursor)) {
                ++$braceDepth;
            } elseif ($char === '}' && !$this->isEscaped($source, $cursor) && $braceDepth > 0) {
                --$braceDepth;
            }
            ++$cursor;
        }
        $offset = $cursor;

        return trim(substr($source, $start, $cursor - $start));
    }

    private function isBlockCommand(string $name): bool
    {
        return isset($this->headingLevels()[$name])
            || in_array($name, [
                'begin', 'end', 'label', 'newcommand', 'renewcommand', 'providecommand', 'newtheorem',
                'maketitle', 'tableofcontents', 'input', 'include', 'bibliography',
                'addbibresource', 'printbibliography', 'bibliographystyle', 'keywords', 'keyword', 'par', '[',
                'newpage', 'clearpage', 'cleardoublepage', 'pagebreak',
            ], true);
    }

    private function blankLineAt(string $source, int $offset): bool
    {
        if (($source[$offset] ?? '') !== "\n") {
            return false;
        }
        $cursor = $offset + 1;
        $length = strlen($source);
        while ($cursor < $length && ($source[$cursor] === ' ' || $source[$cursor] === "\t")) {
            ++$cursor;
        }

        return ($source[$cursor] ?? '') === "\n";
    }

    private function findUnescapedDelimiter(string $source, string $delimiter, int $offset): ?int
    {
        while (($candidate = strpos($source, $delimiter, $offset)) !== false) {
            if (!$this->isEscaped($source, $candidate)) {
                return $candidate;
            }
            $offset = $candidate + 1;
        }

        return null;
    }

    private function isEscaped(string $source, int $offset): bool
    {
        $slashes = 0;
        for ($cursor = $offset - 1; $cursor >= 0 && $source[$cursor] === '\\'; --$cursor) {
            ++$slashes;
        }

        return ($slashes % 2) === 1;
    }

    private function cleanLabel(string $label): string
    {
        $label = trim($this->stripSimpleTexEscapes($label));

        return preg_replace('/\s+/u', ' ', $label) ?? $label;
    }

    private function labelId(string $label): string
    {
        $label = $this->cleanLabel($label);
        if ($label !== '' && isset($this->labels[$label])) {
            return $this->labels[$label]['id'];
        }

        return 'latex-' . ($this->slug($label) ?: 'reference');
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}_.:-]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value;
    }

    private function diagnosticPath(string $path): string
    {
        $path = trim(str_replace(['\\', '/'], '-', $path));
        $path = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $path) ?? $path;

        return trim($path, '-') === '' ? 'unnamed' : trim($path, '-');
    }

    private function sourceDisplayName(mixed $sourcePath): string
    {
        if (!is_string($sourcePath) || trim($sourcePath) === '') {
            return '<input>';
        }
        $sourcePath = trim(str_replace('\\', '/', $sourcePath));
        if ($this->includeRootDirectory !== null) {
            $root = rtrim(str_replace('\\', '/', $this->includeRootDirectory), '/') . '/';
            if (str_starts_with($sourcePath, $root)) {
                $sourcePath = substr($sourcePath, strlen($root));
            }
        }

        return $sourcePath === '' ? '<input>' : basename($sourcePath);
    }

    /**
     * @return array{source:string,line:int,column:int,command?:string,environment?:string}
     */
    private function sourceLocationAtOffset(int $offset, string $kind = '', string $name = ''): array
    {
        $offset = max(0, min($offset, strlen($this->activeSourceText)));
        $before = substr($this->activeSourceText, 0, $offset);
        $line = $this->activeSourceLineOffset + substr_count($before, "\n") + 1;
        $lineStart = strrpos($before, "\n");
        $linePrefix = substr($before, $lineStart === false ? 0 : $lineStart + 1);
        $column = (function_exists('mb_strlen') ? mb_strlen($linePrefix, 'UTF-8') : strlen($linePrefix)) + 1;
        $location = [
            'source' => $this->activeSourceName,
            'line' => $line,
            'column' => $column,
        ];
        if ($kind === 'command' && $name !== '') {
            $location['command'] = $name;
        }
        if ($kind === 'environment' && $name !== '') {
            $location['environment'] = $name;
        }

        return $location;
    }

    /**
     * @param array{source:string,line:int,column:int,command?:string,environment?:string}|null $location
     */
    private function diagnostic(string $code, ?array $location = null): void
    {
        if ($code === '') {
            return;
        }
        if (!isset($this->diagnosticSet[$code])) {
            $this->diagnosticSet[$code] = true;
            $this->diagnostics[] = $code;
        }
        if ($location === null) {
            return;
        }
        $detail = ['code' => $code] + $location;
        $signature = json_encode($detail, JSON_UNESCAPED_SLASHES);
        if (!is_string($signature) || isset($this->diagnosticDetailSet[$signature])) {
            return;
        }
        $this->diagnosticDetailSet[$signature] = true;
        $this->diagnosticDetails[] = $detail;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlocksText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if (in_array($block->type, ['paragraph', 'plain', 'heading', 'term', 'definition_term', 'line'], true)) {
                $text = $this->plainInlineText($block->children);
                if ($text !== '') {
                    $parts[] = $text;
                }
                continue;
            }
            $childText = $this->plainBlocksText($block->children);
            if ($childText !== '') {
                $parts[] = $childText;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function replaceTocPlaceholders(AstNode $document): AstNode
    {
        $headings = [];
        $this->collectHeadings($document, $headings);

        return $this->mapNode($document, function (AstNode $node) use ($headings): AstNode {
            if ($node->type !== 'div' || $node->attr('latexTocPlaceholder') !== true) {
                return $node;
            }

            return $this->tocBlock($headings);
        });
    }

    /**
     * @param list<array{id:string, text:string, level:int}> $headings
     */
    private function collectHeadings(AstNode $node, array &$headings): void
    {
        if ($node->type === 'heading' && $node->attr('latexCommand') !== 'maketitle') {
            $id = trim((string) $node->attr('id', ''));
            $text = trim((string) $node->attr('text', $this->plainInlineText($node->children)));
            if ($id !== '' && $text !== '' && $id !== 'abstract') {
                $headings[] = [
                    'id' => $id,
                    'text' => $text,
                    'level' => max(1, min(6, (int) $node->attr('level', 1))),
                ];
            }
        }
        foreach ($node->children as $child) {
            $this->collectHeadings($child, $headings);
        }
    }

    /**
     * @param list<array{id:string, text:string, level:int}> $headings
     */
    private function tocBlock(array $headings): AstNode
    {
        $items = [];
        foreach ($headings as $heading) {
            $items[] = new AstNode('list_item', ['loose' => false], [
                new AstNode('plain', ['text' => $heading['text']], [
                    new AstNode('link', ['url' => '#' . $heading['id'], 'title' => ''], [
                        new AstNode('text', ['text' => $heading['text']]),
                    ]),
                ]),
            ]);
        }
        $children = [new AstNode('heading', [
            'level' => 2,
            'id' => 'table-of-contents',
            'text' => 'Contents',
        ], [new AstNode('text', ['text' => 'Contents'])])];
        if ($items !== []) {
            $children[] = new AstNode('bullet_list', ['loose' => false, 'classes' => ['latex-toc-list']], $items);
        }

        return new AstNode('div', [
            'id' => 'table-of-contents',
            'classes' => ['latex-table-of-contents'],
        ], $children);
    }

    private function resolveReferences(AstNode $document): AstNode
    {
        return $this->mapNode($document, function (AstNode $node): AstNode {
            if ($node->type !== 'link') {
                return $node;
            }
            $key = $node->attr('latexReferenceKey');
            if (!is_string($key) || $key === '') {
                return $node;
            }
            $entry = $this->labels[$key] ?? null;
            if ($entry === null) {
                $this->diagnostic('latex-unresolved-reference:' . $this->diagnosticPath($key));

                return $node;
            }
            $display = $entry['display'] === '' ? $key : $entry['display'];
            $command = (string) $node->attr('latexReferenceCommand', 'ref');
            if ($command === 'autoref' && !in_array($entry['kind'], ['unknown', 'inline', 'heading', 'paragraph'], true)) {
                $display = ucfirst($entry['kind']) . ' ' . $display;
            }
            if ($command === 'eqref') {
                $display = '(' . $display . ')';
            }
            if ($command === 'pageref') {
                $display = 'page ' . $display;
            }

            return new AstNode('link', array_replace($node->attrs, [
                'url' => '#' . $entry['id'],
                'title' => '',
            ]), [new AstNode('text', ['text' => $display])]);
        });
    }

    private function decorateMathWithLatexContext(AstNode $document): AstNode
    {
        $references = [];
        foreach ($this->labels as $label => $entry) {
            if ($entry['kind'] !== 'equation') {
                continue;
            }
            $references[$label] = [
                'label' => $label,
                'id' => $entry['id'],
                'reference' => $entry['display'],
                'tag' => null,
                'tagStarred' => false,
            ];
        }
        if ($references === [] && $this->macros === []) {
            return $document;
        }

        $converter = new MathTexConverter();

        return $this->mapNode($document, function (AstNode $node) use ($converter, $references): AstNode {
            if ($node->type !== 'math') {
                return $node;
            }
            try {
                $mathml = $converter->texToMathMl(
                    (string) $node->attr('text', ''),
                    $node->attr('display') === true,
                    $this->macros,
                    $references
                );
            } catch (\InvalidArgumentException) {
                return $node;
            }

            return new AstNode('math', array_replace($node->attrs, ['mathml' => $mathml]), $node->children);
        });
    }

    /**
     * @param list<string> $optionGroups
     */
    private function bibliographyPlaceholder(string $command, array $optionGroups): AstNode
    {
        $options = [];
        foreach ($optionGroups as $group) {
            foreach ($this->splitTopLevel($group, ',') as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }
                $parts = explode('=', $entry, 2);
                $key = strtolower(trim($parts[0]));
                if ($key === '') {
                    continue;
                }
                $options[$key] = isset($parts[1]) ? trim($parts[1]) : 'true';
            }
        }

        return new AstNode('div', [
            'classes' => ['latex-bibliography-placeholder'],
            'latexBibliographyPlaceholder' => true,
            'latexBibliographyCommand' => $command,
            'latexBibliographyOptions' => $options,
        ]);
    }

    private function applyResolvedBibliography(AstNode $document): AstNode
    {
        if (!$this->bibliographyRequested || $this->bibliographyFiles === []) {
            if ($this->bibliographyRequested && $this->bibliographyFiles === []) {
                $this->diagnostic('latex-bibliography-unresolved');
            }

            return $document;
        }
        if (!$this->bibliographyResolutionEnabled()) {
            $this->diagnostic('latex-bibliography-resolution-disabled');

            return $document;
        }
        $items = [];
        foreach ($this->bibliographyFiles as $file) {
            try {
                array_push($items, ...CitationCslProcessor::bibtexItems($file['bytes']));
            } catch (\Throwable) {
                $this->diagnostic('latex-bibliography-invalid:' . $this->diagnosticPath($file['path']));
            }
        }
        if ($items === []) {
            $this->diagnostic('latex-bibliography-empty');

            return $document;
        }
        $processor = CitationCslProcessor::fromItems($items);
        $document = $processor->apply($document);
        [$document, $placedBibliographies] = $this->placeResolvedBibliographies($document, $processor);
        if (!$placedBibliographies) {
            $document = new AstNode('document', $document->attrs, [
                ...$document->children,
                ...$this->bibliographySection($processor->bibliographyBlocks($document)),
            ]);
        }
        $attrs = $document->attrs;
        $attrs['cslItems'] = CitationCslProcessor::normalizeItems($items);
        $attrs['cslItemCount'] = count($attrs['cslItems']);
        $latex = is_array($attrs['latex'] ?? null) ? $attrs['latex'] : [];
        $latex['bibliographyResolved'] = true;
        $latex['bibliographyItemCount'] = $attrs['cslItemCount'];
        $attrs['latex'] = $latex;

        return new AstNode('document', $attrs, $document->children);
    }

    /**
     * @return array{0:AstNode, 1:bool}
     */
    private function placeResolvedBibliographies(AstNode $document, CitationCslProcessor $processor): array
    {
        $placed = false;
        $children = $this->placeResolvedBibliographiesInChildren(
            $document->children,
            $processor,
            $document,
            $placed
        );

        return [new AstNode('document', $document->attrs, $children), $placed];
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function placeResolvedBibliographiesInChildren(
        array $children,
        CitationCslProcessor $processor,
        AstNode $scope,
        bool &$placed
    ): array {
        $result = [];
        foreach ($children as $child) {
            if ($child->attr('latexBibliographyPlaceholder') === true) {
                $placed = true;
                array_push($result, ...$this->bibliographyBlocksForPlaceholder($processor, $scope, $child));
                continue;
            }

            if ($child->children === []) {
                $result[] = $child;
                continue;
            }

            $childScope = $scope;
            if ($child->attr('latexBibliographyScope') === true) {
                $childScope = new AstNode('document', $child->attrs, $child->children);
            }
            $nested = $this->placeResolvedBibliographiesInChildren($child->children, $processor, $childScope, $placed);
            $result[] = new AstNode($child->type, $child->attrs, $nested);
        }

        return $result;
    }

    /**
     * @return list<AstNode>
     */
    private function bibliographyBlocksForPlaceholder(
        CitationCslProcessor $processor,
        AstNode $scope,
        AstNode $placeholder
    ): array {
        $options = $placeholder->attr('latexBibliographyOptions', []);
        $options = is_array($options) ? $options : [];
        $title = '';
        if (isset($options['title']) && is_scalar($options['title'])) {
            $title = trim((string) $options['title']);
            $title = trim($title, '{}');
            $title = $this->plainInlineText($this->parseInlines($title));
        }
        $blocks = $processor->bibliographyBlocks($scope, $title === '' ? 'References' : $title);
        if (strtolower((string) ($options['heading'] ?? '')) === 'none' && $blocks !== []) {
            array_shift($blocks);
        }

        return $this->bibliographySection($blocks);
    }

    /**
     * Keep a generated bibliography heading and its entries as a single
     * editable semantic section. This also gives comparison code a stable
     * boundary when a reference renderer intentionally omits bibliography
     * output in fragment mode.
     *
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function bibliographySection(array $blocks): array
    {
        if ($blocks === []) {
            return [];
        }

        return [new AstNode('div', [
            'classes' => ['pandoc-csl-bibliography'],
        ], $blocks)];
    }

    private function documentWithFinalDiagnostics(AstNode $document): AstNode
    {
        $attrs = $document->attrs;
        $latex = is_array($attrs['latex'] ?? null) ? $attrs['latex'] : [];
        $latex['diagnostics'] = $this->diagnostics;
        $latex['diagnosticDetails'] = $this->diagnosticDetails;
        $attrs['latex'] = $latex;
        $meta = is_array($attrs['meta'] ?? null) ? $attrs['meta'] : [];
        if ($this->diagnostics !== []) {
            $meta['latexDiagnostics'] = $this->diagnostics;
        }
        if ($this->diagnosticDetails !== []) {
            $meta['latexDiagnosticDetails'] = $this->diagnosticDetails;
        }
        $attrs['meta'] = $meta;

        return new AstNode('document', $attrs, $document->children);
    }

    private function mapNode(AstNode $node, callable $mapper): AstNode
    {
        $children = [];
        foreach ($node->children as $child) {
            $children[] = $this->mapNode($child, $mapper);
        }
        $node = new AstNode($node->type, $node->attrs, $children);

        return $mapper($node);
    }
}
