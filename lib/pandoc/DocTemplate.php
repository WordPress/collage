<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * @internal
 */
final class DocTemplateBlockOutput
{
    /**
     * @param list<string> $lines
     */
    public function __construct(private array $lines, private string $blankLine)
    {
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function blankLine(): string
    {
        return $this->blankLine;
    }

    public function mapText(callable $callback): self
    {
        return new self(
            array_map(static fn (string $line): string => $callback($line), $this->lines),
            $callback($this->blankLine),
        );
    }
}

/**
 * @internal
 */
final class DocTemplateRelativeLocationException extends \UnexpectedValueException
{
    public function __construct(string $message, private int $relativeOffset, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function relativeOffset(): int
    {
        return $this->relativeOffset;
    }
}

final class DocTemplate
{
    private const MAX_PARTIAL_DEPTH = 50;
    private const BREAKABLE_SPACE_MARKER = "\x1F";
    private const BREAKABLE_SPACE_INDENT_MARKER = "\x1E";
    private const BLOCK_PIPE_MARKER_START = "\x1D";
    private const BLOCK_PIPE_MARKER_END = "\x1C";
    private const MAX_FILESYSTEM_RESOURCE_FILES = 512;
    private const MAX_FILESYSTEM_RESOURCE_BYTES = 1048576;
    private const MAX_FILESYSTEM_RESOURCE_TOTAL_BYTES = 4194304;
    private const DEFAULT_PARTIAL_FALLBACK_SENTINEL = "\0default-partial-fallback";

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     */
    public function render(string $template, array $context, array $partials = []): string
    {
        $partials = $this->normalizePartialMap($partials);

        return $this->stripRootTemplateRedundantFinalBlankLine(
            $template,
            $this->renderTemplate($template, $context, $partials, $this->partialSourceMap($partials), [], false, '<template>'),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     */
    public function renderWrapped(string $template, array $context, int $lineLength, array $partials = []): string
    {
        $this->validateLineLength($lineLength);
        $partials = $this->normalizePartialMap($partials);

        return $this->wrapBreakableSpaces(
            $this->stripRootTemplateRedundantFinalBlankLine(
                $template,
                $this->renderTemplate($template, $context, $partials, $this->partialSourceMap($partials), [], true, '<template>'),
            ),
            $lineLength,
        );
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, mixed> $context
     */
    public function renderResource(string $templatePath, array $resources, array $context, ?string $userDataDirectory = null, ?string $format = null): string
    {
        $templatePath = $this->normalizeTemplateResourcePath($templatePath);
        $resources = $this->normalizeTemplateResourceMap($resources);
        $templatePath = $this->resolveTemplateResourcePath($templatePath, $resources, $format, $userDataDirectory);
        $resources = $this->withDefaultTemplateResource($templatePath, $resources);
        if (!array_key_exists($templatePath, $resources)) {
            throw new \UnexpectedValueException("Missing doctemplate resource {$templatePath}");
        }

        $partialResources = $this->partialResourcesForTemplateResource($templatePath, $resources, $userDataDirectory);

        return $this->stripRootTemplateRedundantFinalBlankLine(
            $resources[$templatePath],
            $this->renderTemplate(
                $resources[$templatePath],
                $context,
                $partialResources['partials'],
                $partialResources['sources'],
                [],
                false,
                $templatePath,
            ),
        );
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, mixed> $context
     */
    public function renderResourceWrapped(string $templatePath, array $resources, array $context, int $lineLength, ?string $userDataDirectory = null, ?string $format = null): string
    {
        $this->validateLineLength($lineLength);
        $templatePath = $this->normalizeTemplateResourcePath($templatePath);
        $resources = $this->normalizeTemplateResourceMap($resources);
        $templatePath = $this->resolveTemplateResourcePath($templatePath, $resources, $format, $userDataDirectory);
        $resources = $this->withDefaultTemplateResource($templatePath, $resources);
        if (!array_key_exists($templatePath, $resources)) {
            throw new \UnexpectedValueException("Missing doctemplate resource {$templatePath}");
        }

        $partialResources = $this->partialResourcesForTemplateResource($templatePath, $resources, $userDataDirectory);

        return $this->wrapBreakableSpaces(
            $this->stripRootTemplateRedundantFinalBlankLine(
                $resources[$templatePath],
                $this->renderTemplate(
                    $resources[$templatePath],
                    $context,
                    $partialResources['partials'],
                    $partialResources['sources'],
                    [],
                    true,
                    $templatePath,
                ),
            ),
            $lineLength,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderFilesystemResource(string $templatePath, string $rootDirectory, array $context, ?string $userDataDirectory = null, ?string $format = null): string
    {
        return $this->renderResource(
            $this->normalizeFilesystemResourcePath($templatePath, 'Doctemplate filesystem template path'),
            $this->loadFilesystemTemplateResources($rootDirectory),
            $context,
            $userDataDirectory === null ? null : $this->normalizeFilesystemResourcePath($userDataDirectory, 'Doctemplate filesystem user-data path'),
            $format,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function renderFilesystemResourceWrapped(string $templatePath, string $rootDirectory, array $context, int $lineLength, ?string $userDataDirectory = null, ?string $format = null): string
    {
        $this->validateLineLength($lineLength);

        return $this->renderResourceWrapped(
            $this->normalizeFilesystemResourcePath($templatePath, 'Doctemplate filesystem template path'),
            $this->loadFilesystemTemplateResources($rootDirectory),
            $context,
            $lineLength,
            $userDataDirectory === null ? null : $this->normalizeFilesystemResourcePath($userDataDirectory, 'Doctemplate filesystem user-data path'),
            $format,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function renderTemplate(
        string $template,
        array $context,
        array $partials,
        array $partialSources,
        array $partialStack,
        bool $preserveBreakableSpaces,
        string $sourceName,
        bool $initialBreakableSpaces = false,
    ): string
    {
        $tokens = $this->tokenize($template, $sourceName, $initialBreakableSpaces);
        $this->validateTokenRange($tokens, 0, count($tokens), $partials, $partialSources, $partialStack);

        return $this->renderRange($tokens, 0, count($tokens), $context, $partials, $partialSources, $partialStack, $preserveBreakableSpaces);
    }

    /**
     * @param array<string, string> $resources
     * @return array<string, string>
     */
    private function normalizeTemplateResourceMap(array $resources): array
    {
        $normalized = [];
        foreach ($resources as $path => $source) {
            if (!is_string($path)) {
                throw new \InvalidArgumentException('Doctemplate resource paths must be strings');
            }

            if (!is_string($source)) {
                throw new \InvalidArgumentException("Doctemplate resource {$path} must be a string");
            }

            $normalized[$this->normalizeTemplateResourcePath($path)] = $source;
        }

        return $normalized;
    }

    private function normalizeFilesystemResourcePath(string $path, string $label): string
    {
        $normalized = $this->normalizeTemplateResourcePath($path);
        if ($this->isAbsoluteTemplateResourcePath($normalized)) {
            throw new \InvalidArgumentException($label . ' must be relative to the resource root');
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function loadFilesystemTemplateResources(string $rootDirectory): array
    {
        if ($rootDirectory === '' || str_contains($rootDirectory, "\0")) {
            throw new \InvalidArgumentException('Invalid doctemplate filesystem root');
        }

        $rootPath = realpath($rootDirectory);
        if ($rootPath === false || !is_dir($rootPath)) {
            throw new \InvalidArgumentException('Doctemplate filesystem root must be an existing directory');
        }

        if ($this->isFilesystemRootDirectory($rootPath)) {
            throw new \InvalidArgumentException('Doctemplate filesystem root is too broad');
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || $fileInfo->isLink() || !$fileInfo->isFile()) {
                continue;
            }

            $filePath = $fileInfo->getRealPath();
            if ($filePath === false || !$this->filesystemPathIsInsideDirectory($filePath, $rootPath)) {
                throw new \UnexpectedValueException('Doctemplate filesystem resource escaped the resource root');
            }

            $relative = substr($filePath, strlen($this->filesystemDirectoryPrefix($rootPath)));
            $resourcePath = $this->normalizeTemplateResourcePath(str_replace('\\', '/', $relative));
            $files[$resourcePath] = $filePath;
        }

        ksort($files, SORT_STRING);

        $resources = [];
        $totalBytes = 0;
        foreach ($files as $resourcePath => $filePath) {
            if (count($resources) >= self::MAX_FILESYSTEM_RESOURCE_FILES) {
                throw new \UnexpectedValueException('Too many doctemplate filesystem resources');
            }

            $bytes = filesize($filePath);
            if ($bytes === false) {
                throw new \UnexpectedValueException("Unable to inspect doctemplate filesystem resource {$resourcePath}");
            }

            if ($bytes > self::MAX_FILESYSTEM_RESOURCE_BYTES) {
                throw new \UnexpectedValueException("Doctemplate filesystem resource {$resourcePath} is too large");
            }

            $totalBytes += $bytes;
            if ($totalBytes > self::MAX_FILESYSTEM_RESOURCE_TOTAL_BYTES) {
                throw new \UnexpectedValueException('Doctemplate filesystem resources exceed bounded byte limit');
            }

            $source = file_get_contents($filePath);
            if (!is_string($source)) {
                throw new \UnexpectedValueException("Unable to read doctemplate filesystem resource {$resourcePath}");
            }

            $resources[$resourcePath] = $source;
        }

        return $resources;
    }

    private function filesystemPathIsInsideDirectory(string $path, string $directory): bool
    {
        return str_starts_with($path, $this->filesystemDirectoryPrefix($directory));
    }

    private function filesystemDirectoryPrefix(string $directory): string
    {
        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function isFilesystemRootDirectory(string $path): bool
    {
        return dirname($path) === $path;
    }

    /**
     * @param array<string, string> $resources
     */
    private function resolveTemplateResourcePath(string $templatePath, array $resources, ?string $format, ?string $userDataDirectory): string
    {
        if (array_key_exists($templatePath, $resources)) {
            return $templatePath;
        }

        if ($format === null || $format === '' || $this->templateResourceExtension($this->templateResourceBasename($templatePath)) !== '') {
            $userDataTemplatePath = $this->userDataDefaultTemplateResourcePath($templatePath, $resources, $userDataDirectory);
            if ($userDataTemplatePath !== null) {
                return $userDataTemplatePath;
            }

            return $templatePath;
        }

        $formatBase = $this->normalizeTemplateOutputFormat($format);
        $candidate = $templatePath . '.' . $format;
        if (array_key_exists($candidate, $resources)) {
            return $candidate;
        }
        $userDataTemplatePath = $this->userDataDefaultTemplateResourcePath($candidate, $resources, $userDataDirectory);
        if ($userDataTemplatePath !== null) {
            return $userDataTemplatePath;
        }
        if ($this->defaultTemplateResource($candidate) !== null) {
            return $candidate;
        }

        if ($formatBase !== $format) {
            $baseCandidate = $templatePath . '.' . $formatBase;
            if (array_key_exists($baseCandidate, $resources)) {
                return $baseCandidate;
            }
            $userDataTemplatePath = $this->userDataDefaultTemplateResourcePath($baseCandidate, $resources, $userDataDirectory);
            if ($userDataTemplatePath !== null) {
                return $userDataTemplatePath;
            }
            if ($this->defaultTemplateResource($baseCandidate) !== null) {
                return $baseCandidate;
            }
        }

        $defaultCandidate = $this->defaultTemplateResourcePathFor($templatePath, $formatBase);
        if ($defaultCandidate !== null) {
            if (array_key_exists($defaultCandidate, $resources)) {
                return $defaultCandidate;
            }
            $userDataTemplatePath = $this->userDataDefaultTemplateResourcePath($defaultCandidate, $resources, $userDataDirectory);
            if ($userDataTemplatePath !== null) {
                return $userDataTemplatePath;
            }
            if ($this->defaultTemplateResource($defaultCandidate) !== null) {
                return $defaultCandidate;
            }
        }

        return $templatePath;
    }

    /**
     * @param array<string, string> $resources
     */
    private function userDataDefaultTemplateResourcePath(string $candidate, array $resources, ?string $userDataDirectory): ?string
    {
        if ($userDataDirectory === null || $this->isAbsoluteTemplateResourcePath($candidate)) {
            return null;
        }

        $basename = $this->templateResourceBasename($candidate);
        if ($this->defaultTemplateResourceForBasename($basename) === null) {
            return null;
        }

        $path = $this->joinTemplateResourcePath(
            $this->joinTemplateResourcePath($this->normalizeTemplateResourcePath($userDataDirectory), 'templates'),
            $basename,
        );

        return array_key_exists($path, $resources) ? $path : null;
    }

    private function normalizeTemplateOutputFormat(string $format): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_]*(?:[+-][A-Za-z0-9_]+)*$/', $format)) {
            throw new \InvalidArgumentException('Invalid doctemplate output format');
        }

        $extensionOffset = strcspn($format, '+-');
        if ($extensionOffset === 0) {
            throw new \InvalidArgumentException('Invalid doctemplate output format');
        }

        return substr($format, 0, $extensionOffset);
    }

    /**
     * @param array<string, string> $resources
     * @return array<string, string>
     */
    private function withDefaultTemplateResource(string $templatePath, array $resources): array
    {
        if (!array_key_exists($templatePath, $resources)) {
            $default = $this->defaultTemplateResource($templatePath);
            if ($default !== null) {
                $resources[$templatePath] = $default;
            }
        }

        foreach ($this->defaultPartialResourcesFor($templatePath) as $path => $source) {
            if (!array_key_exists($path, $resources)) {
                $resources[$path] = $source;
            }
        }

        return $resources;
    }

    /**
     * @return array<string, string>
     */
    private function defaultPartialResourcesFor(string $templatePath): array
    {
        return match ($templatePath) {
            'templates/default.html4', 'templates/default.html5' => [
                'templates/styles.html' => $this->defaultHtmlStylesTemplate(),
                'templates/styles.citations.html' => $this->defaultHtmlCitationStylesTemplate(),
            ],
            'templates/default.jats_archiving', 'templates/default.jats_publishing' => [
                'templates/article.jats_publishing' => $this->defaultJatsPublishingArticleTemplate(),
                'templates/affiliations.jats' => $this->defaultJatsAffiliationsTemplate(),
            ],
            'templates/default.jats_articleauthoring' => [
                'templates/affiliations.jats' => $this->defaultJatsAffiliationsTemplate(),
            ],
            'templates/default.typst' => [
                'templates/template.typst' => $this->defaultTypstConfTemplate(),
                'templates/definitions.typst' => $this->defaultTypstDefinitionsTemplate(),
            ],
            default => [],
        };
    }

    private function defaultTemplateResourcePathFor(string $templatePath, string $format): ?string
    {
        if ($templatePath !== 'templates/default') {
            return null;
        }

        $format = $this->canonicalDefaultTemplateFormat($format);
        if ($format === null || $format === '') {
            return null;
        }

        return 'templates/default.' . $format;
    }

    private function canonicalDefaultTemplateFormat(string $format): ?string
    {
        return match ($format) {
            'html' => 'html5',
            'pdf' => 'latex',
            'docx' => 'openxml',
            'odt' => 'opendocument',
            'epub' => 'epub3',
            'docbook' => 'docbook5',
            'jats' => 'jats_archiving',
            'markdown_strict', 'multimarkdown', 'markdown_github', 'markdown_mmd', 'markdown_phpextra' => 'markdown',
            'gfm', 'commonmark_x' => 'commonmark',
            'bbcode_phpbb', 'bbcode_fluxbb', 'bbcode_steam', 'bbcode_hubzilla', 'bbcode_xenforo' => 'bbcode',
            'asciidoctor', 'asciidoc_legacy' => 'asciidoc',
            'native', 'csljson', 'json', 'xml', 'fb2', 'pptx', 'ipynb' => '',
            default => $format,
        };
    }

    private function defaultTemplateResource(string $path): ?string
    {
        if ($this->isAbsoluteTemplateResourcePath($path)) {
            return null;
        }

        return $this->defaultTemplateResourceForBasename($this->templateResourceBasename($path));
    }

    private function defaultTemplateResourceForBasename(string $basename): ?string
    {
        return match ($basename) {
            'default.html4' => $this->defaultHtml4Template(),
            'default.html5' => $this->defaultHtml5Template(),
            'default.chunkedhtml' => $this->defaultChunkedHtmlTemplate(),
            'default.plain' => $this->defaultPlainTemplate(),
            'default.ansi' => $this->defaultAnsiTemplate(),
            'default.markdown', 'default.commonmark' => $this->defaultMarkdownTemplate(),
            'default.rst' => $this->defaultRstTemplate(),
            'default.rtf' => $this->defaultRtfTemplate(),
            'default.bbcode' => $this->defaultBbcodeTemplate(),
            'default.jira' => $this->defaultJiraTemplate(),
            'default.dokuwiki', 'default.mediawiki' => $this->defaultWikiTocTemplate(),
            'default.vimdoc' => $this->defaultVimdocTemplate(),
            'default.opml' => $this->defaultOpmlTemplate(),
            'default.djot' => $this->defaultDjotTemplate(),
            'default.textile' => $this->defaultTextileTemplate(),
            'default.markua' => $this->defaultMarkuaTemplate(),
            'default.haddock' => $this->defaultHaddockTemplate(),
            'default.tei' => $this->defaultTeiTemplate(),
            'default.xwiki' => $this->defaultXWikiTemplate(),
            'default.zimwiki' => $this->defaultZimWikiTemplate(),
            'default.asciidoc' => $this->defaultAsciiDocTemplate(),
            'default.muse' => $this->defaultMuseTemplate(),
            'default.org' => $this->defaultOrgTemplate(),
            'default.texinfo' => $this->defaultTexinfoTemplate(),
            'default.latex' => $this->defaultLatexTemplate(),
            'after-header-includes.latex' => $this->defaultLatexAfterHeaderIncludesTemplate(),
            'common.latex' => $this->defaultLatexCommonTemplate(),
            'document-metadata.latex' => $this->defaultLatexDocumentMetadataTemplate(),
            'font-settings.latex' => $this->defaultLatexFontSettingsTemplate(),
            'fonts.latex' => $this->defaultLatexFontsTemplate(),
            'hypersetup.latex' => $this->defaultLatexHypersetupTemplate(),
            'passoptions.latex' => $this->defaultLatexPassOptionsTemplate(),
            'default.beamer' => $this->defaultBeamerTemplate(),
            'default.biblatex', 'default.bibtex' => $this->defaultBibliographyTemplate(),
            'default.revealjs' => $this->defaultRevealJsTemplate(),
            'default.s5' => $this->defaultS5Template(),
            'default.slidy' => $this->defaultSlidyTemplate(),
            'default.slideous' => $this->defaultSlideousTemplate(),
            'default.dzslides' => $this->defaultDzslidesTemplate(),
            'default.context' => $this->defaultContextTemplate(),
            'default.man' => $this->defaultManTemplate(),
            'default.ms' => $this->defaultMsTemplate(),
            'default.openxml' => $this->defaultOpenXmlTemplate(),
            'default.opendocument' => $this->defaultOpenDocumentTemplate(),
            'default.epub2' => $this->defaultEpub2Template(),
            'default.epub3' => $this->defaultEpub3Template(),
            'default.icml' => $this->defaultIcmlTemplate(),
            'default.docbook4' => $this->defaultDocbook4Template(),
            'default.docbook5' => $this->defaultDocbook5Template(),
            'default.jats_archiving' => $this->defaultJatsArchivingTemplate(),
            'default.jats_publishing' => $this->defaultJatsPublishingTemplate(),
            'default.jats_articleauthoring' => $this->defaultJatsArticleAuthoringTemplate(),
            'default.typst' => $this->defaultTypstTemplate(),
            'article.jats_publishing' => $this->defaultJatsPublishingArticleTemplate(),
            'affiliations.jats' => $this->defaultJatsAffiliationsTemplate(),
            'styles.html' => $this->defaultHtmlStylesTemplate(),
            'styles.citations.html' => $this->defaultHtmlCitationStylesTemplate(),
            'template.typst' => $this->defaultTypstConfTemplate(),
            'definitions.typst' => $this->defaultTypstDefinitionsTemplate(),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function defaultTemplateResourceBasenames(): array
    {
        return [
            'default.html5',
            'default.html4',
            'default.chunkedhtml',
            'default.plain',
            'default.ansi',
            'default.markdown',
            'default.commonmark',
            'default.rst',
            'default.rtf',
            'default.bbcode',
            'default.jira',
            'default.dokuwiki',
            'default.mediawiki',
            'default.vimdoc',
            'default.opml',
            'default.djot',
            'default.textile',
            'default.markua',
            'default.haddock',
            'default.tei',
            'default.xwiki',
            'default.zimwiki',
            'default.asciidoc',
            'default.muse',
            'default.org',
            'default.texinfo',
            'default.latex',
            'after-header-includes.latex',
            'common.latex',
            'document-metadata.latex',
            'font-settings.latex',
            'fonts.latex',
            'hypersetup.latex',
            'passoptions.latex',
            'default.beamer',
            'default.biblatex',
            'default.bibtex',
            'default.revealjs',
            'default.s5',
            'default.slidy',
            'default.slideous',
            'default.dzslides',
            'default.context',
            'default.man',
            'default.ms',
            'default.openxml',
            'default.opendocument',
            'default.epub2',
            'default.epub3',
            'default.icml',
            'default.docbook4',
            'default.docbook5',
            'default.jats_archiving',
            'default.jats_publishing',
            'default.jats_articleauthoring',
            'default.typst',
            'article.jats_publishing',
            'affiliations.jats',
            'styles.html',
            'styles.citations.html',
            'template.typst',
            'definitions.typst',
        ];
    }

    private function defaultMarkdownTemplate(): string
    {
        return <<<'MD'
$if(titleblock)$
$titleblock$

$endif$
$for(header-includes)$
$header-includes$

$endfor$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
$table-of-contents$

$endif$
$body$
$for(include-after)$

$include-after$
$endfor$
MD;
    }

    private function defaultJiraTemplate(): string
    {
        return <<<'JIRA'
$for(include-before)$ $include-before$ $endfor$ $body$ $for(include-after)$ $include-after$ $endfor$
JIRA;
    }

    private function defaultWikiTocTemplate(): string
    {
        return <<<'WIKI'
$for(include-before)$ $include-before$ $endfor$ $if(toc)$ __TOC__ $endif$ $body$ $for(include-after)$ $include-after$ $endfor$
WIKI;
    }

    private function defaultVimdocTemplate(): string
    {
        return <<<'VIMDOC'
$if(filename)$*${filename}* $endif$$if(abstract)$${abstract}$endif$$if(filename)$ $endif$$if(combined-title)$${combined-title} $endif$$toc-reminder$ $if(toc)$ $toc$ $endif$ $body$ $modeline$
VIMDOC;
    }

    private function defaultOpmlTemplate(): string
    {
        return <<<'OPML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head>
    <title>$title$</title>
    <dateModified>$date$</dateModified>
    <ownerName>$for(author)$$author$$sep$; $endfor$</ownerName>
  </head>
  <body>
$body$
  </body>
</opml>
OPML;
    }

    private function defaultDjotTemplate(): string
    {
        return <<<'DJOT'
$if(title)$
# $title$

$endif$
$if(author)$
$for(author)$
$author$
$endfor$

$endif$
$if(date)$
$date$

$endif$
$for(header-includes)$
$header-includes$

$endfor$
$for(include-before)$
$include-before$

$endfor$
$body$
$for(include-after)$
$include-after$

$endfor$
DJOT;
    }

    private function defaultTextileTemplate(): string
    {
        return <<<'TEXTILE'
$for(include-before)$
$include-before$

$endfor$
$body$
$for(include-after)$

$include-after$
$endfor$
TEXTILE;
    }

    private function defaultMarkuaTemplate(): string
    {
        return <<<'MARKUA'
$if(titleblock)$
$titleblock$

$endif$
$for(header-includes)$
$header-includes$

$endfor$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
$table-of-contents$

$endif$
$body$
$for(include-after)$

$include-after$
$endfor$
MARKUA;
    }

    private function defaultHaddockTemplate(): string
    {
        return <<<'HADDOCK'
$body$
HADDOCK;
    }

    private function defaultTeiTemplate(): string
    {
        return <<<'TEI'
<?xml version="1.0" encoding="utf-8"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0"$if(lang)$ xml:lang="$lang$"$endif$>
<teiHeader>
  <fileDesc>
    <titleStmt>
      <title>$title$</title>
$for(author)$
      <author>$author$</author>
$endfor$
    </titleStmt>
    <publicationStmt>
$if(publicationStmt)$
      <p>$if(publicationStmt)$$publicationStmt$$endif$</p>
$endif$
$if(license)$
      <availability><licence>$license$</licence></availability>
$endif$
$if(publisher)$
      <publisher>$publisher$</publisher>
$endif$
$if(pubPlace)$
      <pubPlace>$pubPlace$</pubPlace>
$endif$
$if(address)$
      <address>$address$</address>
$endif$
$if(date)$
      <date>$date$</date>
$endif$
    </publicationStmt>
    <sourceDesc>
$if(sourceDesc)$
      $sourceDesc$
$else$
      <p>Produced by pandoc.</p>
$endif$
    </sourceDesc>
  </fileDesc>
</teiHeader>
<text>
$for(include-before)$
$include-before$
$endfor$
<body>
$body$
</body>
$for(include-after)$
$include-after$
$endfor$
</text>
</TEI>
TEI;
    }

    private function defaultXWikiTemplate(): string
    {
        return <<<'XWIKI'
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
{{toc /}}

$endif$
$body$
$for(include-after)$

$include-after$
$endfor$
XWIKI;
    }

    private function defaultZimWikiTemplate(): string
    {
        return <<<'ZIMWIKI'
Content-Type: text/x-zim-wiki
Wiki-Format: zim 0.4

$for(include-before)$
$include-before$

$endfor$
$if(toc)$
__TOC__

$endif$
$body$
$for(include-after)$

$include-after$
$endfor$
ZIMWIKI;
    }

    private function defaultRstTemplate(): string
    {
        return <<<'RST'
$if(titleblock)$
$titleblock$

$for(author)$
:Author: $^$$author$
$endfor$
$if(authors)$
:Authors:
   $author$
$endif$
$if(date)$
:Date: $^$$date$
$endif$
$if(address)$
:Address: $^$$address$
$endif$
$if(contact)$
:Contact: $^$$contact$
$endif$
$if(copyright)$
:Copyright: $^$$copyright$
$endif$
$if(dedication)$
:Dedication: $^$$dedication$
$endif$
$if(organization)$
:Organization: $^$$organization$
$endif$
$if(revision)$
:Revision: $^$$revision$
$endif$
$if(status)$
:Status: $^$$status$
$endif$
$if(version)$
:Version: $^$$version$
$endif$
$if(abstract)$
:Abstract:
   $abstract$
$endif$

$endif$
$if(rawtex)$
.. role:: raw-latex(raw)
   :format: latex
..

$endif$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
.. contents::
   :depth: $toc-depth$
..

$endif$
$if(number-sections)$
.. section-numbering::

$endif$
$for(header-includes)$
$header-includes$

$endfor$
$body$
$for(include-after)$

$include-after$
$endfor$
RST;
    }

    private function defaultAsciiDocTemplate(): string
    {
        return <<<'ASCIIDOC'
$if(titleblock)$
= $title$
$if(author)$
$for(author)$$author$$sep$; $endfor$
$if(date)$
$date$
$endif$
$elseif(date)$
:revdate: $date$
$endif$
$if(keywords)$
:keywords: $for(keywords)$$keywords$$sep$, $endfor$
$endif$
$if(lang)$
:lang: $lang$
$endif$
$if(toc)$
:toc:
$endif$
$if(math)$
:stem: latexmath
$endif$

$endif$
$if(abstract)$
[abstract]
== Abstract
$abstract$

$endif$
$for(header-includes)$
$header-includes$

$endfor$
$for(include-before)$
$include-before$

$endfor$
$body$
$for(include-after)$

$include-after$
$endfor$
ASCIIDOC;
    }

    private function defaultLatexTemplate(): string
    {
        return <<<'LATEX'
\documentclass$if(classoption)$[$for(classoption)$$classoption$$sep$, $endfor$]$endif${$if(documentclass)$$documentclass$$else$article$endif$}
$if(geometry)$
\usepackage[$for(geometry)$$geometry$$sep$,$endfor$]{geometry}
$endif$
\usepackage{amsmath,amssymb}
$if(linestretch)$
\usepackage{setspace}
$endif$
$for(header-includes)$
$header-includes$

$endfor$
$if(title)$
\title{$title$$if(thanks)$\thanks{$thanks$}$endif$}
$endif$
\author{$for(author)$$author$$sep$ \and $endfor$}
\date{$date$}
\begin{document}
$if(has-frontmatter)$
\frontmatter
$endif$
$if(title)$
\maketitle
$if(abstract)$
\begin{abstract}
$abstract$
\end{abstract}
$endif$
$endif$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
$if(toc-title)$
\renewcommand*\contentsname{$toc-title$}
$endif$
\setcounter{tocdepth}{$if(toc-depth)$$toc-depth$$else$3$endif$}
\tableofcontents
$endif$
$if(lof)$
\listoffigures
$endif$
$if(lot)$
\listoftables
$endif$
$if(linestretch)$
\setstretch{$linestretch$}
$endif$
$if(has-frontmatter)$
\mainmatter
$endif$
$body$
$if(has-frontmatter)$
\backmatter
$endif$
$if(nocite-ids)$
\nocite{$for(nocite-ids)$$it$$sep$, $endfor$}
$endif$
$if(natbib)$
$if(bibliography)$
$if(biblio-title)$
$if(has-chapters)$
\renewcommand\bibname{$biblio-title$}
$else$
\renewcommand\refname{$biblio-title$}
$endif$
$endif$
\bibliography{$for(bibliography)$$bibliography$$sep$,$endfor$}
$endif$
$endif$
$if(biblatex)$
\printbibliography$if(biblio-title)$[title=$biblio-title$]$endif$
$endif$
$for(include-after)$

$include-after$
$endfor$
\end{document}
LATEX;
    }

    private function defaultLatexDocumentMetadataTemplate(): string
    {
        return <<<'LATEX_METADATA'
$--
$-- PDF standard support (PDF/A, PDF/UA, PDF/X)
$-- Requires LuaLaTeX and recent LaTeX (2023+)
$--
$if(pdfstandard)$
\DocumentMetadata{
$if(pdfstandard.version)$
  pdfversion=$pdfstandard.version$,
$endif$
$if(pdfstandard.standards)$
  pdfstandard={$for(pdfstandard.standards)$$it$$sep$,$endfor$},
$endif$
$if(pdfstandard.tagging)$
  tagging=on,
$endif$
$if(lang)$
  lang=$lang$,
$endif$
  xmp=true}
$endif$
LATEX_METADATA;
    }

    private function defaultLatexPassOptionsTemplate(): string
    {
        return <<<'LATEX_PASSOPTIONS'
% Options for packages loaded elsewhere
\PassOptionsToPackage{unicode$for(hyperrefoptions)$,$hyperrefoptions$$endfor$}{hyperref}
\PassOptionsToPackage{hyphens}{url}
$if(colorlinks)$
\PassOptionsToPackage{dvipsnames,svgnames,x11names}{xcolor}
$endif$
$if(CJKmainfont)$
\PassOptionsToPackage{space}{xeCJK}
$endif$
LATEX_PASSOPTIONS;
    }

    private function defaultLatexFontsTemplate(): string
    {
        return <<<'LATEX_FONTS'
\usepackage{iftex}
\ifPDFTeX
  \usepackage[$if(fontenc)$$fontenc$$else$T1$endif$]{fontenc}
  \usepackage[utf8]{inputenc}
  \usepackage{textcomp} % provide euro and other symbols
\else % if luatex or xetex
$if(mathspec)$
  \ifXeTeX
    \usepackage{mathspec} % this also loads fontspec
  \else
    \usepackage{unicode-math} % this also loads fontspec
  \fi
$else$
  \usepackage{unicode-math} % this also loads fontspec
$endif$
  \defaultfontfeatures{Scale=MatchLowercase}$-- must come before Beamer theme
  \defaultfontfeatures[\rmfamily]{Ligatures=TeX,Scale=1}
\fi
$if(fontfamily)$
$else$
$-- Set default font before Beamer theme so the theme can override it
\usepackage{lmodern}
$endif$
LATEX_FONTS;
    }

    private function defaultLatexFontSettingsTemplate(): string
    {
        return <<<'LATEX_FONT_SETTINGS'
$-- User font settings (must come after default font and Beamer theme)
$if(fontfamily)$
\usepackage[$for(fontfamilyoptions)$$fontfamilyoptions$$sep$,$endfor$]{$fontfamily$}
$endif$
\ifPDFTeX\else
  % xetex/luatex font selection
$if(mainfont)$
$if(mainfontfallback)$
  \ifLuaTeX
    \usepackage{luaotfload}
    \directlua{luaotfload.add_fallback("mainfontfallback",{
      $for(mainfontfallback)$"$mainfontfallback$"$sep$,$endfor$
    })}
  \fi
$endif$
  \setmainfont[$for(mainfontoptions)$$mainfontoptions$$sep$,$endfor$$if(mainfontfallback)$,RawFeature={fallback=mainfontfallback}$endif$]{$mainfont$}
$endif$
$if(sansfont)$
$if(sansfontfallback)$
  \ifLuaTeX
    \usepackage{luaotfload}
    \directlua{luaotfload.add_fallback("sansfontfallback",{
      $for(sansfontfallback)$"$sansfontfallback$"$sep$,$endfor$
    })}
  \fi
$endif$
  \setsansfont[$for(sansfontoptions)$$sansfontoptions$$sep$,$endfor$$if(sansfontfallback)$,RawFeature={fallback=sansfontfallback}$endif$]{$sansfont$}
$endif$
$if(monofont)$
$if(monofontfallback)$
  \ifLuaTeX
    \usepackage{luaotfload}
    \directlua{luaotfload.add_fallback("monofontfallback",{
      $for(monofontfallback)$"$monofontfallback$"$sep$,$endfor$
    })}
  \fi
$endif$
  \setmonofont[$for(monofontoptions)$$monofontoptions$$sep$,$endfor$$if(monofontfallback)$,RawFeature={fallback=monofontfallback}$endif$]{$monofont$}
$endif$
$for(fontfamilies)$
  \newfontfamily{$fontfamilies.name$}[$for(fontfamilies.options)$$fontfamilies.options$$sep$,$endfor$]{$fontfamilies.font$}
$endfor$
$if(mathfont)$
$if(mathspec)$
  \ifXeTeX
    \setmathfont(Digits,Latin,Greek)[$for(mathfontoptions)$$mathfontoptions$$sep$,$endfor$]{$mathfont$}
  \else
    \setmathfont[$for(mathfontoptions)$$mathfontoptions$$sep$,$endfor$]{$mathfont$}
  \fi
$else$
  \setmathfont[$for(mathfontoptions)$$mathfontoptions$$sep$,$endfor$]{$mathfont$}
$endif$
$endif$
$if(CJKmainfont)$
  \ifXeTeX
    \usepackage{xeCJK}
    \setCJKmainfont[$for(CJKoptions)$$CJKoptions$$sep$,$endfor$]{$CJKmainfont$}
$if(CJKsansfont)$
    \setCJKsansfont[$for(CJKoptions)$$CJKoptions$$sep$,$endfor$]{$CJKsansfont$}
$endif$
$if(CJKmonofont)$
    \setCJKmonofont[$for(CJKoptions)$$CJKoptions$$sep$,$endfor$]{$CJKmonofont$}
$endif$
  \fi
$endif$
$if(luatexjapresetoptions)$
  \ifLuaTeX
    \usepackage[$for(luatexjapresetoptions)$$luatexjapresetoptions$$sep$,$endfor$]{luatexja-preset}
  \fi
$endif$
$if(CJKmainfont)$
  \ifLuaTeX
    \usepackage[$for(luatexjafontspecoptions)$$luatexjafontspecoptions$$sep$,$endfor$]{luatexja-fontspec}
    \setmainjfont[$for(CJKoptions)$$CJKoptions$$sep$,$endfor$]{$CJKmainfont$}
  \fi
$endif$
\fi
$if(zero-width-non-joiner)$
%% Support for zero-width non-joiner characters.
\makeatletter
\def\zerowidthnonjoiner{%
  % Prevent ligatures and adjust kerning, but still support hyphenating.
  \texorpdfstring{%
    \TextOrMath{\nobreak\discretionary{-}{}{\kern.03em}%
      \ifvmode\else\nobreak\hskip\z@skip\fi}{}%
  }{}%
}
\makeatother
\ifPDFTeX
  \DeclareUnicodeCharacter{200C}{\zerowidthnonjoiner}
\else
  \catcode`^^^^200c=\active
  \protected\def ^^^^200c{\zerowidthnonjoiner}
\fi
%% End of ZWNJ support
$endif$
% Use upquote if available, for straight quotes in verbatim environments
\IfFileExists{upquote.sty}{\usepackage{upquote}}{}
\IfFileExists{microtype.sty}{% use microtype if available
  \usepackage[$for(microtypeoptions)$$microtypeoptions$$sep$,$endfor$]{microtype}
  \UseMicrotypeSet[protrusion]{basicmath} % disable protrusion for tt fonts
}{}
LATEX_FONT_SETTINGS;
    }

    private function defaultLatexCommonTemplate(): string
    {
        return <<<'LATEX_COMMON'
$if(linestretch)$
\usepackage{setspace}
$endif$
$--
$-- paragraph formatting
$--
$if(indent)$
$else$
\makeatletter
\@ifundefined{KOMAClassName}{% if non-KOMA class
  \IfFileExists{parskip.sty}{%
    \usepackage{parskip}
  }{% else
    \setlength{\parindent}{0pt}
    \setlength{\parskip}{6pt plus 2pt minus 1pt}}
}{% if KOMA class
  \KOMAoptions{parskip=half}}
\makeatother
$endif$
$if(beamer)$
$else$
$if(block-headings)$
% Make \paragraph and \subparagraph free-standing
\makeatletter
\ifx\paragraph\undefined\else
  \let\oldparagraph\paragraph
  \renewcommand{\paragraph}{
    \@ifstar
      \xxxParagraphStar
      \xxxParagraphNoStar
  }
  \newcommand{\xxxParagraphStar}[1]{\oldparagraph*{#1}\mbox{}}
  \newcommand{\xxxParagraphNoStar}[1]{\oldparagraph{#1}\mbox{}}
\fi
\ifx\subparagraph\undefined\else
  \let\oldsubparagraph\subparagraph
  \renewcommand{\subparagraph}{
    \@ifstar
      \xxxSubParagraphStar
      \xxxSubParagraphNoStar
  }
  \newcommand{\xxxSubParagraphStar}[1]{\oldsubparagraph*{#1}\mbox{}}
  \newcommand{\xxxSubParagraphNoStar}[1]{\oldsubparagraph{#1}\mbox{}}
\fi
\makeatother
$endif$
$endif$
$--
$-- verbatim in notes
$--
$if(verbatim-in-note)$
\usepackage{fancyvrb}
$endif$
$-- highlighting
$if(listings)$
\usepackage{listings}
\newcommand{\passthrough}[1]{#1}
\lstset{defaultdialect=[5.3]Lua}
\lstset{defaultdialect=[x86masm]Assembler}
$endif$
$if(lhs)$
\lstnewenvironment{code}{\lstset{language=Haskell,basicstyle=\small\ttfamily}}{}
$endif$
$if(highlighting-macros)$
$highlighting-macros$
$endif$
$--
$-- tables
$--
$if(tables)$
\usepackage{longtable,booktabs,array}
\newcounter{none} % for unnumbered tables
$if(multirow)$
\usepackage{multirow}
$endif$
\usepackage{calc} % for calculating minipage widths
$if(beamer)$
\usepackage{caption}
% Make caption package work with longtable
\makeatletter
\def\fnum@table{\tablename~\thetable}
\makeatother
$else$
% Correct order of tables after \paragraph or \subparagraph
\usepackage{etoolbox}
\makeatletter
\patchcmd\longtable{\par}{\if@noskipsec\mbox{}\fi\par}{}{}
\makeatother
% Allow footnotes in longtable head/foot
\IfFileExists{footnotehyper.sty}{\usepackage{footnotehyper}}{\usepackage{footnote}}
\makesavenoteenv{longtable}
$endif$
$endif$
$--
$-- graphics
$--
$if(graphics)$
\usepackage{graphicx}
\makeatletter
\newsavebox\pandoc@box
\newcommand*\pandocbounded[1]{% scales image to fit in text height/width
  \sbox\pandoc@box{#1}%
  \Gscale@div\@tempa{\textheight}{\dimexpr\ht\pandoc@box+\dp\pandoc@box\relax}%
  \Gscale@div\@tempb{\linewidth}{\wd\pandoc@box}%
  \ifdim\@tempb\p@<\@tempa\p@\let\@tempa\@tempb\fi% select the smaller of both
  \ifdim\@tempa\p@<\p@\scalebox{\@tempa}{\usebox\pandoc@box}%
  \else\usebox{\pandoc@box}%
  \fi%
}
% Set default figure placement to htbp
\def\fps@figure{htbp}
\makeatother
$endif$
$if(svg)$
\usepackage{svg}
$endif$
$--
$-- strikeout/underline
$--
$if(strikeout)$
\ifLuaTeX
  \usepackage{luacolor}
  \usepackage[soul]{lua-ul}
\else
  \usepackage{soul}
$if(beamer)$
  \makeatletter
  \let\HL\hl
  \renewcommand\hl{% fix for beamer highlighting
    \let\set@color\beamerorig@set@color
    \let\reset@color\beamerorig@reset@color
    \HL}
  \makeatother
$endif$
$if(CJKmainfont)$
  \ifXeTeX
    % soul's \st doesn't work for CJK:
    \usepackage{xeCJKfntef}
    \renewcommand{\st}[1]{\sout{#1}}
  \fi
$endif$
\fi
$endif$
$--
$-- CSL citations
$--
$if(csl-refs)$
% definitions for citeproc citations
\NewDocumentCommand\citeproctext{}{}
\NewDocumentCommand\citeproc{mm}{%
  \begingroup\def\citeproctext{#2}\cite{#1}\endgroup}
\makeatletter
 % allow citations to break across lines
 \let\@cite@ofmt\@firstofone
 % avoid brackets around text for \cite:
 \def\@biblabel#1{}
 \def\@cite#1#2{{#1\if@tempswa , #2\fi}}
\makeatother
\newlength{\cslhangindent}
\setlength{\cslhangindent}{1.5em}
\newlength{\csllabelwidth}
\setlength{\csllabelwidth}{3em}
\newenvironment{CSLReferences}[2] % #1 hanging-indent, #2 entry-spacing
 {\begin{list}{}{%
  \setlength{\itemindent}{0pt}
  \setlength{\leftmargin}{0pt}
  \setlength{\parsep}{0pt}
  % turn on hanging indent if param 1 is 1
  \ifodd #1
   \setlength{\leftmargin}{\cslhangindent}
   \setlength{\itemindent}{-1\cslhangindent}
  \fi
  % set entry spacing
  \setlength{\itemsep}{#2\baselineskip}}}
 {\end{list}}
\usepackage{calc}
\newcommand{\CSLBlock}[1]{\hfill\break\parbox[t]{\linewidth}{\strut\ignorespaces#1\strut}}
\newcommand{\CSLLeftMargin}[1]{\parbox[t]{\csllabelwidth}{\strut#1\strut}}
\newcommand{\CSLRightInline}[1]{\parbox[t]{\linewidth - \csllabelwidth}{\strut#1\strut}}
\newcommand{\CSLIndent}[1]{\hspace{\cslhangindent}#1}
$endif$
$--
$-- Babel language support
$--
$if(lang)$
\ifLuaTeX
\usepackage[bidi=basic$if(shorthands)$$else$,shorthands=off$endif$$for(babeloptions)$,$babeloptions$$endfor$]{babel}
\else
\usepackage[bidi=default$if(shorthands)$$else$,shorthands=off$endif$$for(babeloptions)$,$babeloptions$$endfor$]{babel}
\fi
$if(babel-lang)$
$if(mainfont)$
\ifPDFTeX
\else
\babelfont{rm}[$for(mainfontoptions)$$mainfontoptions$$sep$,$endfor$$if(mainfontfallback)$,RawFeature={fallback=mainfontfallback}$endif$]{$mainfont$}
\fi
$endif$
$endif$
$for(babelfonts/pairs)$
\babelfont[$babelfonts.key$]{rm}{$babelfonts.value$}
$endfor$
\ifLuaTeX
  \usepackage{selnolig} % disable illegal ligatures
\fi
$endif$
$--
$-- pagestyle
$--
$if(pagestyle)$
\pagestyle{$pagestyle$}
$endif$
$--
$-- prevent overfull lines
$--
\setlength{\emergencystretch}{3em} % prevent overfull lines
$--
$-- tight lists
$--
\providecommand{\tightlist}{%
  \setlength{\itemsep}{0pt}\setlength{\parskip}{0pt}}
$--
$-- subfigure support
$--
$if(subfigure)$
\usepackage{subcaption}
$endif$
$--
$-- text direction support for pdftex
$--
$if(dir)$
\ifPDFTeX
  \TeXXeTstate=1
  \newcommand{\RL}[1]{\beginR #1\endR}
  \newcommand{\LR}[1]{\beginL #1\endL}
  \newenvironment{RTL}{\beginR}{\endR}
  \newenvironment{LTR}{\beginL}{\endL}
\fi
\ifluatex
  \newcommand{\RL}[1]{\bgroup\textdir TRT#1\egroup}
  \newcommand{\LR}[1]{\bgroup\textdir TLT#1\egroup}
  \newenvironment{RTL}{\textdir TRT\pardir TRT\bodydir TRT}{}
  \newenvironment{LTR}{\textdir TLT\pardir TLT\bodydir TLT}{}
\fi
$endif$
$--
$-- bibliography support support for natbib and biblatex
$--
$if(natbib)$
\usepackage[$natbiboptions$]{natbib}
\bibliographystyle{$if(biblio-style)$$biblio-style$$else$plainnat$endif$}
$endif$
$if(biblatex)$
\usepackage[$if(biblio-style)$style=$biblio-style$,$endif$$for(biblatexoptions)$$biblatexoptions$$sep$,$endfor$]{biblatex}
$for(bibliography)$
\addbibresource{$bibliography$}
$endfor$
$endif$
$--
$-- csquotes
$--
$if(csquotes)$
\usepackage[$for(csquotesoptions)$$csquotesoptions$$sep$,$endfor$]{csquotes}
$endif$
LATEX_COMMON;
    }

    private function defaultLatexAfterHeaderIncludesTemplate(): string
    {
        return <<<'LATEX_AFTER_HEADER_INCLUDES'
\usepackage{bookmark}
\IfFileExists{xurl.sty}{\usepackage{xurl}}{} % add URL line breaks if available
\urlstyle{$if(urlstyle)$$urlstyle$$else$same$endif$}
$if(links-as-notes)$
% Make links footnotes instead of hotlinks:
\DeclareRobustCommand{\href}[2]{#2\footnote{\url{#1}}}
$endif$
$if(verbatim-in-note)$
\VerbatimFootnotes % allow verbatim text in footnotes
$endif$
LATEX_AFTER_HEADER_INCLUDES;
    }

    private function defaultLatexHypersetupTemplate(): string
    {
        return <<<'LATEX_HYPERSETUP'
% fallback for those not using the hyperref driver hyperxmp:
\makeatletter
\@ifundefined{xmpquote}{\newcommand{\xmpquote}[1]{#1}}{}
\makeatother
\hypersetup{
$if(title-meta)$
  pdftitle={$title-meta$},
$endif$
$if(author-meta)$
  pdfauthor={$author-meta$},
$endif$
$if(lang)$
  pdflang={$lang$},
$endif$
$if(subject)$
  pdfsubject={$subject$},
$endif$
$if(keywords)$
  pdfkeywords={$for(keywords)$\xmpquote{$keywords$}$sep$, $endfor$},
$endif$
$if(colorlinks)$
  colorlinks=true,
  linkcolor={$if(linkcolor)$$linkcolor$$else$Maroon$endif$},
  filecolor={$if(filecolor)$$filecolor$$else$Maroon$endif$},
  citecolor={$if(citecolor)$$citecolor$$else$Blue$endif$},
  urlcolor={$if(urlcolor)$$urlcolor$$else$Blue$endif$},
$else$
$if(boxlinks)$
$else$
  hidelinks,
$endif$
$endif$
  pdfcreator={LaTeX via pandoc}}
LATEX_HYPERSETUP;
    }

    private function defaultContextTemplate(): string
    {
        return <<<'CONTEXT'
$if(tagging)$
\setupbackend[format=pdf/ua-2]
\enabledirectives[backend.usetags=mkiv]
\setuptagging[state=start]
$endif$
$if(context-lang)$
\mainlanguage[$context-lang$]
$endif$
$if(context-dir)$
\setupalign[$context-dir$]
\setupdirections[bidi=on,method=two]
$endif$
\setupinteraction
  [state=start,
$if(title)$
  title={$title$},
$endif$
$if(subtitle)$
  subtitle={$subtitle$},
$endif$
$if(author)$
  author={$for(author)$$author$$sep$; $endfor$},
$endif$
$if(keywords)$
  keyword={$for(keywords)$$keywords$$sep$; $endfor$},
$endif$
  style=$linkstyle$,
  color=$linkcolor$,
  contrastcolor=$linkcontrastcolor$]
\setupurl[style=$urlstyle$]
\placebookmarks[chapter, section, subsection, subsubsection][chapter, section]
\setupinteractionscreen[option={bookmark,title}]
$if(papersize)$
\setuppapersize[$for(papersize)$$papersize$$sep$,$endfor$]
$endif$
$if(layout)$
\setuplayout[$for(layout)$$layout$$sep$,$endfor$]
$endif$
$if(pagenumbering)$
\setuppagenumbering[$for(pagenumbering)$$pagenumbering$$sep$,$endfor$]
$else$
\setuppagenumbering[location={footer,middle}]
$endif$
$if(pdfa)$
\setupbackend
  [format=PDF/A-$pdfa$,
   profile={$if(pdfaiccprofile)$$for(pdfaiccprofile)$$pdfaiccprofile$$sep$,$endfor$$else$sRGB.icc$endif$},
   intent=$if(pdfaintent)$$pdfaintent$$else$sRGB IEC61966-2.1$endif$]
$endif$
\setupstructure[state=start,method=auto]
$for(mainfontfallback)$
\definefallbackfamily[mainface][rm][$mainfontfallback/nowrap$][range=0x0000-0xFFFF, check=yes, force=no]
$endfor$
\definefontfamily[mainface][rm][$if(mainfont)$$mainfont/nowrap$$else$Latin Modern Roman$endif$]
\definefontfamily[mainface][mm][$if(mathfont)$$mathfont/nowrap$$else$Latin Modern Math$endif$]
\definefontfamily[mainface][ss][$if(sansfont)$$sansfont/nowrap$$else$Latin Modern Sans$endif$]
\definefontfamily[mainface][tt][$if(monofont)$$monofont/nowrap$$else$Latin Modern Typewriter$endif$][features=none]
\setupbodyfont[mainface$if(fontsize)$,$fontsize$$endif$]
\setupwhitespace[$if(whitespace)$$whitespace$$else$medium$endif$]
$if(indenting)$
\setupindenting[$for(indenting)$$indenting$$sep$,$endfor$]
$endif$
$if(interlinespace)$
\setupinterlinespace[$for(interlinespace)$$interlinespace$$sep$,$endfor$]
$endif$
$if(headertext)$
\setupheadertexts$for(headertext)$[$headertext$]$endfor$
$endif$
$if(footertext)$
\setupfootertexts$for(footertext)$[$footertext$]$endfor$
$endif$
$if(number-sections)$
$else$
\setuphead[chapter, section, subsection, subsubsection][number=no]
$endif$
$if(emphasis-commands)$
$emphasis-commands$
$endif$
$if(highlighting-commands)$
$highlighting-commands$
$endif$
$if(csl-refs)$
\definemeasure[cslhangindent][1.5em]
\definenarrower[hangingreferences][left=\measure{cslhangindent}]
\definestartstop [cslreferences] [
$if(csl-hanging-indent)$
  before={\starthangingreferences[left]},
  after=\stophangingreferences,
$endif$
]
$endif$
$if(includesource)$
$for(sourcefile)$
\attachment[file=$curdir$/$sourcefile$,method=hidden]
$endfor$
$endif$
$for(header-includes)$
$header-includes$
$endfor$

\starttext
$if(title)$
\startalignment[middle]
  {\tfd\setupinterlinespace $title$}
$if(subtitle)$
  \smallskip
  {\tfa\setupinterlinespace $subtitle$}
$endif$
$if(author)$
  \smallskip
  {\tfa\setupinterlinespace $for(author)$$author$$sep$\crlf $endfor$}
$endif$
$if(date)$
  \smallskip
  {\tfa\setupinterlinespace $date$}
$endif$
  \bigskip
\stopalignment
$endif$
$if(abstract)$
\midaligned{\it Abstract}
\startnarrower[2*middle]
$abstract$
\stopnarrower
\blank[big]
$endif$
$for(include-before)$
$include-before$
$endfor$
$if(toc)$
\completecontent
$endif$
$if(lof)$
\completelistoffigures
$endif$
$if(lot)$
\completelistoftables
$endif$

$body$

$for(include-after)$
$include-after$
$endfor$
\stoptext
CONTEXT;
    }

    private function defaultManTemplate(): string
    {
        return <<<'MAN'
$if(has-tables)$
'\" t
$endif$
$if(pandoc-version)$
.\" Automatically generated by Pandoc $pandoc-version$
.\"
$endif$
$if(adjusting)$
.ad $adjusting$
$endif$
.TH "$title/nowrap$" "$section/nowrap$" "$date/nowrap$" "$footer/nowrap$"$if(header)$ "$header/nowrap$"$endif$
$for(header-includes)$
$header-includes$
$endfor$
$for(include-before)$
$include-before$
$endfor$
$body$
$for(include-after)$
$include-after$
$endfor$
$if(author)$
.SH AUTHORS
$for(author)$$author$$sep$; $endfor$.
$endif$
MAN;
    }

    private function defaultRtfTemplate(): string
    {
        return <<<'RTF'
{\rtf1\ansi\deff0{\fonttbl{\f0 \fswiss Helvetica;}{\f1 \fmodern Courier;}} {\colortbl;\red255\green0\blue0;\red0\green0\blue255;} \widowctrl\hyphauto $for(header-includes)$ $header-includes$ $endfor$ $if(title)$ {\pard \qc \f0 \sa180 \li0 \fi0 \b \fs36 $title$\par} $endif$ $for(author)$ {\pard \qc \f0 \sa180 \li0 \fi0 $author$\par} $endfor$ $if(date)$ {\pard \qc \f0 \sa180 \li0 \fi0 $date$\par} $endif$ $if(spacer)$ {\pard \ql \f0 \sa180 \li0 \fi0 \par} $endif$ $if(toc)$ $table-of-contents$ $endif$ $for(include-before)$ $include-before$ $endfor$ $body$ $for(include-after)$ $include-after$ $endfor$ }
RTF;
    }

    private function defaultMsTemplate(): string
    {
        return <<<'MS'
$if(pandoc-version)$
.\" Automatically generated by Pandoc $pandoc-version$
.\"
$endif$
.\" **** Custom macro definitions *********************************
.\" * Super/subscript
.ds { \v'-0.3m'\s[\n[.s]*9u/12u]
.ds } \s0\v'0.3m'
.ds < \v'0.3m'\s[\n[.s]*9u/12u]
.ds > \s0\v'-0.3m'
.\" * Horizontal line
.de HLINE
.LP
.ce
\l'20'
..
$if(highlighting-macros)$
.\" * Syntax highlighting macros
$highlighting-macros$
$endif$
.\" **** Settings *************************************************
.\" text width
.nr LL 5.5i
.\" left margin
.nr PO 1.25i
.\" top margin
.nr HM 1.25i
.\" bottom margin
.nr FM 1.25i
.\" header/footer width
.nr LT \n[LL]
.\" point size
.nr PS $if(pointsize)$$pointsize$$else$10p$endif$
.\" line height
.nr VS $if(lineheight)$$lineheight$$else$12p$endif$
.\" font family: A, BM, H, HN, N, P, T, ZCM
.fam $if(fontfamily)$$fontfamily$$else$T$endif$
.\" paragraph indent
.nr PI $if(indent)$$indent$$else$0m$endif$
.\" interparagraph space
.nr PD 0.4v
.\" footnote width
.nr FL \n[LL]
.\" footnote point size
.nr FPS (\n[PS] - 2000)
$if(papersize)$
.\" paper size
.ds paper $papersize$
$endif$
.\" color used for strikeout
.defcolor strikecolor rgb 0.7 0.7 0.7
.\" point size difference between heading levels
.nr PSINCR 1p
.\" heading level above which point size no longer changes
.nr GROWPS 2
.\" page numbers in footer, centered
.ds CH
.ds CF %
$if(adjusting)$
.ad $adjusting$
$endif$
$if(hyphenate)$
.hy
$else$
.nh
$endif$
$if(has-inline-math)$
.EQ
delim @@
.EN
$endif$
$if(pdf-engine)$
.\" color for links (rgb)
.ds PDFHREF.COLOUR 0.35 0.00 0.60
.\" border for links (default none)
.ds PDFHREF.BORDER 0 0 0
.\" pdf outline fold level
.nr PDFOUTLINE.FOLDLEVEL 3
.\" start out in outline view
.pdfview /PageMode /UseOutlines
.\" ***************************************************************
.\" PDF metadata
.pdfinfo /Title "$title-meta$"
.pdfinfo /Author "$author-meta$"
$endif$
$for(header-includes)$
$header-includes$
$endfor$
$if(title)$
.TL
$title$
$endif$
$for(author)$
.AU
$author$
$endfor$
$if(date)$
.AU
.sp 0.5
.ft R
$date$
$endif$
$if(abstract)$
.AB
$abstract$
.AE
$endif$
.\" 1 column (use .2C for two column)
.1C
$for(include-before)$
$include-before$
$endfor$
$body$
$if(toc)$
.TC
$endif$
$for(include-after)$
$include-after$
$endfor$
.pdfsync
MS;
    }

    private function defaultBeamerTemplate(): string
    {
        return <<<'BEAMER'
\documentclass[$if(fontsize)$$fontsize$, $endif$ignorenonframetext$if(handout)$, handout$endif$$if(aspectratio)$, aspectratio=$aspectratio$$endif$$for(classoption)$, $classoption$$endfor$]{$if(documentclass)$$documentclass$$else$beamer$endif$}
$if(geometry)$
\geometry{$for(geometry)$$geometry$$sep$,$endfor$}
$endif$
\newif\ifbibliography
$if(background-image)$
\usebackgroundtemplate{%
\includegraphics[width=\paperwidth]{$background-image$}%
}
$endif$
\usepackage{pgfpages}
$if(linestretch)$
\usepackage{setspace}
$endif$
\setbeamertemplate{caption}[numbered]
\setbeamertemplate{caption label separator}{: }
\setbeamercolor{caption name}{fg=normal text.fg}
\beamertemplatenavigationsymbols$if(navigation)$$navigation$$else$empty$endif$
$if(numbersections)$
$else$
\setbeamertemplate{section page}{\centering\insertsection\par}
\setbeamertemplate{subsection page}{\centering\insertsubsection\par}
$endif$
$for(beameroption)$
\setbeameroption{$beameroption$}
$endfor$
\widowpenalties 1 10000
\raggedbottom
$if(section-titles)$
\AtBeginPart{\frame{\partpage}}
\AtBeginSection{
\ifbibliography
\else
\frame{\sectionpage}
\fi
}
\AtBeginSubsection{\frame{\subsectionpage}}
$endif$
$if(theme)$
\usetheme$if(themeoptions)$[$for(themeoptions)$$themeoptions$$sep$,$endfor$]$endif${$theme$}
$endif$
$if(colortheme)$
\usecolortheme$if(colorthemeoptions)$[$for(colorthemeoptions)$$colorthemeoptions$$sep$,$endfor$]$endif${$colortheme$}
$endif$
$if(fonttheme)$
\usefonttheme$if(fontthemeoptions)$[$for(fontthemeoptions)$$fontthemeoptions$$sep$,$endfor$]$endif${$fonttheme$}
$endif$
$if(innertheme)$
\useinnertheme$if(innerthemeoptions)$[$for(innerthemeoptions)$$innerthemeoptions$$sep$,$endfor$]$endif${$innertheme$}
$endif$
$if(outertheme)$
\useoutertheme$if(outerthemeoptions)$[$for(outerthemeoptions)$$outerthemeoptions$$sep$,$endfor$]$endif${$outertheme$}
$endif$
$for(header-includes)$
$header-includes$

$endfor$
$if(title)$
\title$if(shorttitle)$[$shorttitle$]$endif${$title$$if(thanks)$\thanks{$thanks$}$endif$}
$endif$
$if(subtitle)$
\subtitle$if(shortsubtitle)$[$shortsubtitle$]$endif${$subtitle$}
$endif$
\author$if(shortauthor)$[$shortauthor$]$endif${$for(author)$$author$$sep$ \and $endfor$}
\date$if(shortdate)$[$shortdate$]$endif${$date$}
$if(institute)$
\institute$if(shortinstitute)$[$shortinstitute$]$endif${$for(institute)$$institute$$sep$ \and $endfor$}
$endif$
$if(titlegraphic)$
\titlegraphic{$for(titlegraphic)$\includegraphics$if(titlegraphicoptions)$[$for(titlegraphicoptions)$$titlegraphicoptions$$sep$, $endfor$]$endif${$titlegraphic$}$sep$\enspace $endfor$}
$endif$
$if(logo)$
\logo{\includegraphics$if(logooptions)$[$for(logooptions)$$logooptions$$sep$, $endfor$]$endif${$logo$}}
$endif$
\begin{document}
$if(title)$
\frame{\titlepage}
$if(abstract)$
\begin{abstract}
$abstract$
\end{abstract}
$endif$
$endif$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
$if(toc-title)$
\renewcommand*\contentsname{$toc-title$}
$endif$
\begin{frame}[allowframebreaks]
$if(toc-title)$
\frametitle{$toc-title$}
$endif$
\setcounter{tocdepth}{$if(toc-depth)$$toc-depth$$else$3$endif$}
\tableofcontents
\end{frame}
$endif$
$if(lof)$
\listoffigures
$endif$
$if(lot)$
\listoftables
$endif$
$if(linestretch)$
\setstretch{$linestretch$}
$endif$
$body$
$if(natbib)$
$if(bibliography)$
$if(biblio-title)$
\renewcommand\refname{$biblio-title$}
$endif$
\begin{frame}[allowframebreaks]{$biblio-title$}
$if(nocite-ids)$
\nocite{$for(nocite-ids)$$it$$sep$, $endfor$}
$endif$
\bibliographytrue
\bibliography{$for(bibliography)$$bibliography$$sep$,$endfor$}
\end{frame}
$endif$
$endif$
$if(biblatex)$
\begin{frame}[allowframebreaks]{$biblio-title$}
$if(nocite-ids)$
\nocite{$for(nocite-ids)$$it$$sep$, $endfor$}
$endif$
\bibliographytrue
\printbibliography[heading=none]
\end{frame}
$endif$
$for(include-after)$
$include-after$

$endfor$
\end{document}
BEAMER;
    }

    private function defaultRevealJsTemplate(): string
    {
        return <<<'REVEALJS'
<!doctype html>
<html$if(lang)$ lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
<head>
<meta charset="utf-8">
<meta name="generator" content="pandoc $pandoc-version$">
$for(author-meta)$<meta name="author" content="$it$">
$endfor$$if(date-meta)$<meta name="dcterms.date" content="$date-meta$">
$endif$$if(keywords)$<meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$">
$endif$$if(description-meta)$<meta name="description" content="$description-meta$">
$endif$<title>$if(pagetitle)$$pagetitle$$elseif(title)$$title$$endif$</title>
<link rel="stylesheet" href="$if(revealjs-url)$$revealjs-url$$else$reveal.js$endif$/dist/reveal.css">
<link rel="stylesheet" href="$if(revealjs-url)$$revealjs-url$$else$reveal.js$endif$/dist/theme/$if(theme)$$theme$$else$black$endif$.css" id="theme">
$for(css)$<link rel="stylesheet" href="$it$">
$endfor$$for(header-includes)$$it$
$endfor$</head>
<body>
<div class="reveal">
<div class="slides">
$if(title)$<section id="title-slide">
<h1 class="title">$title$</h1>
$if(subtitle)$<p class="subtitle">$subtitle$</p>
$endif$$for(author)$<p class="author">$it$</p>
$endfor$$if(date)$<p class="date">$date$</p>
$endif$</section>
$endif$$for(include-before)$$it$
$endfor$$if(toc)$<nav id="$idprefix$TOC" role="doc-toc">
$if(toc-title)$<h2 id="$idprefix$toc-title">$toc-title$</h2>
$endif$$table-of-contents$
</nav>
$endif$$body$
$for(include-after)$$it$
$endfor$</div>
</div>
<script src="$if(revealjs-url)$$revealjs-url$$else$reveal.js$endif$/dist/reveal.js"></script>
$for(revealjs-plugins/pairs)$<script src="$it.value$"></script>
$endfor$<script>
Reveal.initialize({
  hash: $if(hash)$$hash$$else$true$endif$,
  controls: $if(controls)$$controls$$else$true$endif$,
  progress: $if(progress)$$progress$$else$false$endif$,
  slideNumber: $if(slideNumber)$"$slideNumber$"$else$false$endif$,
  transition: "$if(transition)$$transition$$else$slide$endif$",
  backgroundTransition: "$if(background-transition)$$background-transition$$else$fade$endif$",
  history: $if(history)$$history$$else$false$endif$,
  keyboard: $if(keyboard)$$keyboard$$else$true$endif$,
  overview: $if(overview)$$overview$$else$true$endif$,
  center: $if(center)$$center$$else$false$endif$,
  touch: $if(touch)$$touch$$else$true$endif$,
  loop: $if(loop)$$loop$$else$false$endif$,
  rtl: $if(rtl)$$rtl$$else$false$endif$,
$if(navigationMode)$
  navigationMode: "$navigationMode$",
$endif$$if(fragments)$
  fragments: $fragments$,
$else$
  fragments: false,
$endif$$if(embedded)$
  embedded: $embedded$,
$else$
  embedded: false,
$endif$$if(width)$
  width: $width$,
$endif$$if(height)$
  height: $height$,
$endif$$if(margin)$
  margin: $margin$,
$endif$$if(minScale)$
  minScale: $minScale$,
$endif$$if(maxScale)$
  maxScale: $maxScale$,
$endif$$if(parallaxBackgroundImage)$
  parallaxBackgroundImage: "$parallaxBackgroundImage$",
$endif$$if(parallaxBackgroundSize)$
  parallaxBackgroundSize: "$parallaxBackgroundSize$",
$endif$$if(parallaxBackgroundHorizontal)$
  parallaxBackgroundHorizontal: $parallaxBackgroundHorizontal$,
$endif$$if(parallaxBackgroundVertical)$
  parallaxBackgroundVertical: $parallaxBackgroundVertical$,
$endif$$if(revealjs-plugins)$
  plugins: [ $for(revealjs-plugins/pairs)$$it.key$$sep$, $endfor$ ]
$endif$});
</script>
</body>
</html>
REVEALJS;
    }

    private function defaultS5Template(): string
    {
        return <<<'S5'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"$if(lang)$ lang="$lang$" xml:lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta name="generator" content="pandoc" />
$for(author-meta)$
  <meta name="version" content="S5 1.1" />
  <meta name="author" content="$author-meta$" />
$endfor$
$if(date-meta)$
  <meta name="date" content="$date-meta$" />
$endif$
$if(keywords)$
  <meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$" />
$endif$
  <title>$if(title-prefix)$$title-prefix$ – $endif$$pagetitle$</title>
  <style type="text/css">
    $styles.html()$
  </style>
  <meta name="defaultView" content="slideshow" />
  <meta name="controlVis" content="hidden" />
$for(css)$
  <link rel="stylesheet" href="$css$" type="text/css" />
$endfor$
  <link rel="stylesheet" href="$s5-url$/slides.css" type="text/css" media="projection" id="slideProj" />
  <link rel="stylesheet" href="$s5-url$/outline.css" type="text/css" media="screen" id="outlineStyle" />
  <link rel="stylesheet" href="$s5-url$/print.css" type="text/css" media="print" id="slidePrint" />
  <link rel="stylesheet" href="$s5-url$/opera.css" type="text/css" media="projection" id="operaFix" />
  <script src="$s5-url$/slides.js" type="text/javascript"></script>
$if(math)$
  $math$
$endif$
$for(header-includes)$
  $header-includes$
$endfor$
</head>
<body>
$for(include-before)$
$include-before$
$endfor$
<div class="layout">
<div id="controls"></div>
<div id="currentSlide"></div>
<div id="header"></div>
<div id="footer">
  <h1>$date$</h1>
  <h2>$title$</h2>
</div>
</div>
<div class="presentation">
$if(title)$
<div class="title-slide slide">
  <h1 class="title">$title$</h1>
$if(subtitle)$
  <h2 class="subtitle">$subtitle$</h2>
$endif$
$if(author)$
  <h3 class="author">$for(author)$$author$$sep$<br/>$endfor$</h3>
$endif$
$if(institute)$
  <h3 class="institute">$for(institute)$$institute$$sep$<br/>$endfor$</h3>
$endif$
$if(date)$
  <h4 class="date">$date$</h4>
$endif$
</div>
$endif$
$if(toc)$
<div class="slide" id="$idprefix$TOC">
$table-of-contents$
</div>
$endif$
$body$
$for(include-after)$
$include-after$
$endfor$
</div>
</body>
</html>
S5;
    }

    private function defaultSlidyTemplate(): string
    {
        return <<<'SLIDY'
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"$if(lang)$ lang="$lang$" xml:lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta name="generator" content="pandoc" />
$for(author-meta)$
  <meta name="author" content="$author-meta$" />
$endfor$
$if(date-meta)$
  <meta name="date" content="$date-meta$" />
$endif$
$if(keywords)$
  <meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$" />
$endif$
  <title>$if(title-prefix)$$title-prefix$ – $endif$$pagetitle$</title>
  <style type="text/css">
    $styles.html()$
  </style>
  <link rel="stylesheet" type="text/css" media="screen, projection, print"
    href="$slidy-url$/styles/slidy.css" />
$for(css)$
  <link rel="stylesheet" type="text/css" media="screen, projection, print"
   href="$css$" />
$endfor$
$if(math)$
  $math$
$endif$
$for(header-includes)$
  $header-includes$
$endfor$
  <script src="$slidy-url$/scripts/slidy.js"
    charset="utf-8" type="text/javascript"></script>
$if(duration)$
  <meta name="duration" content="$duration$" />
$endif$
</head>
<body>
$for(include-before)$
$include-before$
$endfor$
$if(title)$
<div class="slide titlepage">
  <h1 class="title">$title$</h1>
$if(subtitle)$
  <p class="subtitle">$subtitle$</p>
$endif$
$if(author)$
  <p class="author">
$for(author)$$author$$sep$<br/>$endfor$
  </p>
$endif$
$if(institute)$
  <p class="institute">
$for(institute)$$institute$$sep$<br/>$endfor$
  </p>
$endif$
$if(date)$
  <p class="date">$date$</p>
$endif$
</div>
$endif$
$if(toc)$
<div class="slide" id="$idprefix$TOC">
$table-of-contents$
</div>
$endif$
$body$
$for(include-after)$
$include-after$
$endfor$
</body>
</html>
SLIDY;
    }

    private function defaultSlideousTemplate(): string
    {
        return <<<'SLIDEOUS'
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"$if(lang)$ lang="$lang$" xml:lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta name="generator" content="pandoc" />
$for(author-meta)$
  <meta name="author" content="$author-meta$" />
$endfor$
$if(date-meta)$
  <meta name="date" content="$date-meta$" />
$endif$
$if(keywords)$
  <meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$" />
$endif$
  <title>$if(title-prefix)$$title-prefix$ – $endif$$pagetitle$</title>
  <style type="text/css">
    $styles.html()$
  </style>
  <link rel="stylesheet" type="text/css" media="screen, projection, print"
    href="$slideous-url$/slideous.css" />
$for(css)$
  <link rel="stylesheet" type="text/css" media="screen, projection, print"
   href="$css$" />
$endfor$
$if(math)$
  $math$
$endif$
$for(header-includes)$
  $header-includes$
$endfor$
  <script src="$slideous-url$/slideous.js"
    charset="utf-8" type="text/javascript"></script>
$if(duration)$
  <meta name="duration" content="$duration$" />
$endif$
</head>
<body>
$for(include-before)$
$include-before$
$endfor$
<div id="statusbar">
<span style="float:right;">
<span style="margin-right:4em;font-weight:bold;"><span id="slideidx"></span> of {$$slidecount}</span>
<button id="homebutton" title="first slide">1</button>
<button id="prevslidebutton" title="previous slide">&laquo;</button>
<button id="previtembutton" title="previous item">&lsaquo;</button>
<button id="nextitembutton" title="next item">&rsaquo;</button>
<button id="nextslidebutton" title="next slide">&raquo;</button>
<button id="endbutton" title="last slide">{$$slidecount}</button>
<button id="incfontbutton" title="content">A+</button>
<button id="decfontbutton" title="first slide">A-</button>
<select id="tocbox" size="1"><option></option></select>
</span>
<span id="eos">&frac12;</span>
<span title="{$$location}, {$$date}">{$$title}, {$$author}</span>
</div>
$if(title)$
<div class="slide titlepage">
  <h1 class="title">$title$</h1>
$if(subtitle)$
  <h1 class="subtitle">$subtitle$</h1>
$endif$
$if(author)$
  <p class="author">
$for(author)$$author$$sep$<br/>$endfor$
  </p>
$endif$
$if(institute)$
  <p class="institute">
$for(institute)$$institute$$sep$<br/>$endfor$
  </p>
$endif$
$if(date)$
  <p class="date">$date$</p>
$endif$
</div>
$endif$
$if(toc)$
<div class="slide" id="$idprefix$TOC">
$table-of-contents$
</div>
$endif$
$body$
$for(include-after)$
$include-after$
$endfor$
</body>
</html>
SLIDEOUS;
    }

    private function defaultDzslidesTemplate(): string
    {
        return <<<'DZSLIDES'
<!DOCTYPE html>
<head$if(lang)$ lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
  <meta charset="utf-8">
  <meta name="generator" content="pandoc">
$for(author-meta)$
  <meta name="author" content="$author-meta$">
$endfor$
$if(date-meta)$
  <meta name="dcterms.date" content="$date-meta$">
$endif$
$if(keywords)$
  <meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$">
$endif$
  <title>$if(title-prefix)$$title-prefix$ – $endif$$pagetitle$</title>
  <style>
    $styles.html()$
  </style>
$if(css)$
$for(css)$
  <link rel="stylesheet" href="$css$">
$endfor$
$else$
<link href='https://fonts.googleapis.com/css?family=Oswald' rel='stylesheet'>

<style>
  html, .view body { background-color: black; counter-reset: slideidx; }
  body, .view section { background-color: white; border-radius: 12px }
  /* A section is a slide. It's size is 800x600, and this will never change */
  section, .view head > title {
      /* The font from Google */
      font-family: 'Oswald', arial, serif;
      font-size: 30px;
  }

  .view section:after {
    counter-increment: slideidx;
    content: counter(slideidx, decimal-leading-zero);
    position: absolute; bottom: -80px; right: 100px;
    color: white;
  }

  .view head > title {
    color: white;
    text-align: center;
    margin: 1em 0 1em 0;
  }

  h1, h2 {
    margin-top: 200px;
    text-align: center;
    font-size: 80px;
  }
  h3 {
    margin: 100px 0 50px 100px;
  }

  ul {
      margin: 50px 200px;
  }
  li > ul {
      margin: 15px 50px;
  }

  p {
    margin: 75px;
    font-size: 50px;
  }

  blockquote {
    height: 100%;
    background-color: black;
    color: white;
    font-size: 60px;
    padding: 50px;
  }
  blockquote:before {
    content: open-quote;
  }
  blockquote:after {
    content: close-quote;
  }

  /* Figures are displayed full-page, with the caption
     on top of the image/video */
  figure {
    background-color: black;
    width: 100%;
    height: 100%;
  }
  figure > * {
    position: absolute;
  }
  figure > img, figure > video {
    width: 100%; height: 100%;
  }
  figcaption {
    margin: 70px;
    font-size: 50px;
  }

  footer {
    position: absolute;
    bottom: 0;
    width: 100%;
    padding: 40px;
    text-align: right;
    background-color: #F3F4F8;
    border-top: 1px solid #CCC;
  }

  /* Transition effect */
  /* Feel free to change the transition effect for original
     animations. See here:
     https://developer.mozilla.org/en/CSS/CSS_transitions
     How to use CSS3 Transitions: */
  section {
    -moz-transition: left 400ms linear 0s;
    -webkit-transition: left 400ms linear 0s;
    -ms-transition: left 400ms linear 0s;
    transition: left 400ms linear 0s;
  }
  .view section {
    -moz-transition: none;
    -webkit-transition: none;
    -ms-transition: none;
    transition: none;
  }

  .view section[aria-selected] {
    border: 5px red solid;
  }

  /* Before */
  section { left: -150%; }
  /* Now */
  section[aria-selected] { left: 0; }
  /* After */
  section[aria-selected] ~ section { left: +150%; }

  /* Incremental elements */

  /* By default, visible */
  .incremental > * { opacity: 1; }

  /* The current item */
  .incremental > *[aria-selected] { opacity: 1; }

  /* The items to-be-selected */
  .incremental > *[aria-selected] ~ * { opacity: 0; }

  /* The progressbar, at the bottom of the slides, show the global
     progress of the presentation. */
  #progress-bar {
    height: 2px;
    background: #AAA;
  }
</style>
$endif$
$if(math)$
  $math$
$endif$
$for(header-includes)$
  $header-includes$
$endfor$
</head>
<body>
$if(title)$
<section class="title">
  <h1 class="title">$title$</h1>
$if(subtitle)$
  <h1 class="subtitle">$subtitle$</h1>
$endif$
  <footer>
    $if(author)$<span class="author">$for(author)$$author$$sep$, $endfor$</span> · $endif$$if(institute)$<span class="institute">$for(institute)$$institute$$sep$, $endfor$</span> · $endif$$if(date)$<span class="date">$date$</span>$endif$
  </footer>
</section>
$endif$
$if(toc)$
<section id="$idprefix$TOC">
$table-of-contents$
</section>
$endif$
$for(include-before)$
$include-before$
$endfor$
$body$
$for(include-after)$
$include-after$
$endfor$
$dzslides-core$
</body>
</html>
DZSLIDES;
    }

    private function defaultOpenXmlTemplate(): string
    {
        return <<<'OPENXML'
$if(title)$
$title$

$endif$
$if(subtitle)$
$subtitle$

$endif$
$for(author)$
$author$

$endfor$
$if(date)$
$date$

$endif$
$if(abstract)$
$if(abstract-title)$
$abstract-title$

$endif$
$abstract$

$endif$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
$toc$

$endif$
$if(lof)$
$lof$

$endif$
$if(lot)$
$lot$

$endif$
$body$
$for(include-after)$

$include-after$
$endfor$
$sectpr$
OPENXML;
    }

    private function defaultOpenDocumentTemplate(): string
    {
        return <<<'OPENDOCUMENT'
$automatic-styles$
$for(header-includes)$
$header-includes$

$endfor$
$if(title)$
$title$

$endif$
$if(subtitle)$
$subtitle$

$endif$
$for(author)$
$author$

$endfor$
$if(date)$
$date$

$endif$
$if(abstract)$
$abstract$

$endif$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
$toc-title$

$endif$
$body$
$for(include-after)$

$include-after$
$endfor$
OPENDOCUMENT;
    }

    private function defaultEpub3Template(): string
    {
        return <<<'EPUB3'
$for(css)$

$endfor$
$for(header-includes)$
$header-includes$

$endfor$
$if(titlepage)$
$for(title)$
$if(title.type)$
# $title.text$

$else$
# $title$

$endif$
$endfor$
$if(subtitle)$
$subtitle$

$endif$
$for(author)$
$author$

$endfor$
$for(creator)$
$creator.text$

$endfor$
$if(publisher)$
$publisher$

$endif$
$if(date)$
$date$

$endif$
$if(rights)$
$rights$

$endif$
$if(abstract)$
$abstract-title$

$abstract$

$endif$
$else$
$if(coverpage)$
$else$
$for(include-before)$
$include-before$

$endfor$
$body$
$for(include-after)$

$include-after$
$endfor$
$endif$
$endif$
EPUB3;
    }

    private function defaultEpub2Template(): string
    {
        return <<<'EPUB2'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"$if(lang)$ lang="$lang$" xml:lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta name="generator" content="pandoc" />
  <title>$pagetitle$</title>
  <style type="text/css">
$if(csl-css)$
    $styles.citations.html()$
$endif$
$if(highlighting-css)$
    /* CSS for syntax highlighting */
    $highlighting-css$
$endif$
  </style>
$for(css)$
  <link rel="stylesheet" type="text/css" href="$css$" />
$endfor$
$for(header-includes)$
  $header-includes$
$endfor$
</head>
<body$if(coverpage)$ id="cover"$endif$>
$if(titlepage)$
$for(title)$
$if(title.text)$
  <h1 class="$title.type$">$title.text$</h1>
$else$
  <h1 class="title">$title$</h1>
$endif$
$endfor$
$if(subtitle)$
  <p class="subtitle">$subtitle$</p>
$endif$
$for(author)$
  <p class="author">$author$</p>
$endfor$
$for(creator)$
  <p class="$creator.role$">$creator.text$</p>
$endfor$
$if(publisher)$
  <p class="publisher">$publisher$</p>
$endif$
$if(date)$
  <p class="date">$date$</p>
$endif$
$if(rights)$
  <div class="rights">$rights$</div>
$endif$
$if(abstract)$
<div class="abstract">
<div class="abstract-title">$abstract-title$</div>
$abstract$
</div>
$endif$
$else$
$if(coverpage)$
<div id="cover-image">
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="100%" height="100%" viewBox="0 0 $cover-image-width$ $cover-image-height$" preserveAspectRatio="xMidYMid">
<image width="$cover-image-width$" height="$cover-image-height$" xlink:href="../media/$cover-image$" />
</svg>
</div>
$else$
$for(include-before)$
$include-before$
$endfor$
$body$
$for(include-after)$
$include-after$
$endfor$
$endif$
$endif$
</body>
</html>
EPUB2;
    }

    private function defaultIcmlTemplate(): string
    {
        return <<<'ICML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<?aid style="50" type="snippet" readerVersion="6.0" featureSet="513" product="8.0(370)" ?>
<?aid SnippetType="InCopyInterchange"?>
<Document DOMVersion="8.0" Self="pandoc_doc">
    <RootCharacterStyleGroup Self="pandoc_character_styles">
      <CharacterStyle Self="$$ID/NormalCharacterStyle" Name="Default" />
      $charStyles$
    </RootCharacterStyleGroup>
    <RootParagraphStyleGroup Self="pandoc_paragraph_styles">
      <ParagraphStyle Self="$$ID/NormalParagraphStyle" Name="$$ID/NormalParagraphStyle"
          SpaceBefore="6" SpaceAfter="6"> <!-- paragraph spacing -->
        <Properties>
          <TabList type="list">
            <ListItem type="record">
              <Alignment type="enumeration">LeftAlign</Alignment>
              <AlignmentCharacter type="string">.</AlignmentCharacter>
              <Leader type="string"></Leader>
              <Position type="unit">10</Position> <!-- first tab stop -->
            </ListItem>
          </TabList>
        </Properties>
      </ParagraphStyle>
      $parStyles$
    </RootParagraphStyleGroup>
    <RootTableStyleGroup Self="pandoc_table_styles">
      <TableStyle Self="TableStyle/Table" Name="Table" />
    </RootTableStyleGroup>
    <RootCellStyleGroup Self="pandoc_cell_styles">
      <CellStyle Self="CellStyle/Cell" AppliedParagraphStyle="ParagraphStyle/$$ID/[No paragraph style]" Name="Cell" />
    </RootCellStyleGroup>
$if(objectStyles)$
    <RootObjectStyleGroup Self="pandoc_object_styles">
      $objectStyles$
    </RootObjectStyleGroup>
$endif$
  <Story Self="pandoc_story"
      TrackChanges="false"
      StoryTitle="$if(title-prefix)$$title-prefix$ – $endif$$pagetitle$"
      AppliedTOCStyle="n"
      AppliedNamedGrid="n" >
    <StoryPreference OpticalMarginAlignment="true" OpticalMarginSize="12" />

<!-- body needs to be non-indented, otherwise code blocks are indented too far -->
$body$

  </Story>
  $hyperlinks$
</Document>
ICML;
    }

    private function defaultDocbook5Template(): string
    {
        return <<<'DOCBOOK5'
$if(article)$
<article xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.0" xml:lang="en">
$else$
<chapter xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.0" xml:lang="en">
$endif$
$if(title)$
  <title>$title$</title>
$endif$
$if(subtitle)$
  <subtitle>$subtitle$</subtitle>
$endif$
$for(author)$
  <author>
    $author$
  </author>
$endfor$
$if(date)$
  <date>$date$</date>
$endif$
$if(abstract)$
  <abstract>
    $abstract$
  </abstract>
$endif$
$for(include-before)$
$include-before$
$endfor$
$body$
$for(include-after)$
$include-after$
$endfor$
$if(article)$
</article>
$else$
</chapter>
$endif$
DOCBOOK5;
    }

    private function defaultDocbook4Template(): string
    {
        return <<<'DOCBOOK4'
<?xml version="1.0" encoding="utf-8" ?>
$if(mathml)$
<!DOCTYPE article PUBLIC "-//OASIS//DTD DocBook EBNF Module V1.1CR1//EN"
                  "http://www.oasis-open.org/docbook/xml/mathml/1.1CR1/dbmathml.dtd">
$else$
<!DOCTYPE article PUBLIC "-//OASIS//DTD DocBook XML V4.5//EN"
                  "http://www.oasis-open.org/docbook/xml/4.5/docbookx.dtd">
$endif$
<article>
  <articleinfo>
    <title>$title$</title>
$if(author)$
    <authorgroup>
$for(author)$
      <author>
        $author$
      </author>
$endfor$
    </authorgroup>
$endif$
$if(date)$
    <date>$date$</date>
$endif$
  </articleinfo>
$for(include-before)$
  $include-before$
$endfor$
  $body$
$for(include-after)$
  $include-after$
$endfor$
</article>
DOCBOOK4;
    }

    private function defaultJatsArchivingTemplate(): string
    {
        return <<<'JATS_ARCHIVING'
<?xml version="1.0" encoding="utf-8" ?>
$if(xml-stylesheet)$
<?xml-stylesheet type="text/xsl" href="$xml-stylesheet$"?>
$endif$
<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Archiving and Interchange DTD v1.2 20190208//EN"
                  "JATS-archivearticle1.dtd">
${ article.jats_publishing() }
JATS_ARCHIVING;
    }

    private function defaultJatsPublishingTemplate(): string
    {
        return <<<'JATS_PUBLISHING'
<?xml version="1.0" encoding="utf-8" ?>
$if(xml-stylesheet)$
<?xml-stylesheet type="text/xsl" href="$xml-stylesheet$"?>
$endif$
<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Publishing DTD v1.2 20190208//EN"
                  "JATS-publishing1.dtd">
${ article.jats_publishing() }
JATS_PUBLISHING;
    }

    private function defaultJatsPublishingArticleTemplate(): string
    {
        return <<<'JATS_ARTICLE'
$if(article.type)$
<article xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:xlink="http://www.w3.org/1999/xlink" dtd-version="1.2" article-type="$article.type$">
$else$
<article xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:xlink="http://www.w3.org/1999/xlink" dtd-version="1.2" article-type="other">
$endif$
<front>
<journal-meta>
$if(journal.publisher-id)$
<journal-id journal-id-type="publisher-id">$journal.publisher-id$</journal-id>
$endif$
$if(journal.nlm-ta)$
<journal-id journal-id-type="nlm-ta">$journal.nlm-ta$</journal-id>
$endif$
$if(journal.pmc)$
<journal-id journal-id-type="pmc">$journal.pmc$</journal-id>
$endif$
$if(journal.publisher-id)$
$elseif(journal.nlm-ta)$
$elseif(journal.pmc)$
$else$
<journal-id></journal-id>
$endif$
<journal-title-group>
$if(journal.title)$
<journal-title>$journal.title$</journal-title>
$endif$
$if(journal.abbrev-title)$
<abbrev-journal-title>$journal.abbrev-title$</abbrev-journal-title>
$endif$
</journal-title-group>
$if(journal.pissn)$
<issn publication-format="print">$journal.pissn$</issn>
$endif$
$if(journal.eissn)$
<issn publication-format="electronic">$journal.eissn$</issn>
$endif$
$if(journal.pissn)$
$elseif(journal.eissn)$
$else$
<issn></issn>
$endif$
<publisher>
<publisher-name>$journal.publisher-name$</publisher-name>
$if(journal.publisher-loc)$
<publisher-loc>$journal.publisher-loc$</publisher-loc>
$endif$
</publisher>
</journal-meta>
<article-meta>
$if(article.publisher-id)$
<article-id pub-id-type="publisher-id">$article.publisher-id$</article-id>
$endif$
$if(article.doi)$
<article-id pub-id-type="doi">$article.doi$</article-id>
$endif$
$if(article.pmid)$
<article-id pub-id-type="pmid">$article.pmid$</article-id>
$endif$
$if(article.pmcid)$
<article-id pub-id-type="pmcid">$article.pmcid$</article-id>
$endif$
$if(article.art-access-id)$
<article-id pub-id-type="art-access-id">$article.art-access-id$</article-id>
$endif$
$if(article.heading)$
<article-categories>
<subj-group subj-group-type="heading">
<subject>$article.heading$</subject>
</subj-group>
$if(article.categories)$
<subj-group subj-group-type="categories">
$for(article.categories)$
<subject>$article.categories$</subject>
$endfor$
</subj-group>
$endif$
</article-categories>
$endif$
$if(title)$
<title-group>
<article-title>$title$</article-title>
$if(subtitle)$
<subtitle>${subtitle}</subtitle>
$endif$
</title-group>
$endif$
$if(author)$
<contrib-group>
$for(author)$
<contrib contrib-type="author"$if(author.equal-contrib)$ equal-contrib="yes"$endif$$if(author.cor-id)$ corresp="yes"$endif$>
$if(author.orcid)$
<contrib-id contrib-id-type="orcid">$author.orcid$</contrib-id>
$endif$
$if(author.surname)$
<name>
<surname>$if(author.non-dropping-particle)$${author.non-dropping-particle} $endif$$author.surname$</surname>
<given-names>$author.given-names$$if(author.dropping-particle)$ ${author.dropping-particle}$endif$</given-names>
$if(author.prefix)$
<prefix>${author.prefix}</prefix>
$endif$
$if(author.suffix)$
<suffix>${author.suffix}</suffix>
$endif$
</name>
$elseif(author.name)$
<string-name>$author.name$</string-name>
$else$
<string-name>$author$</string-name>
$endif$
$for(author.roles)$
$if(it.credit)$
<role vocab="credit"$if(it.degree)$ degree-contribution="$it.degree$"$endif$ vocab-identifier="https://credit.niso.org/" vocab-term-identifier="https://credit.niso.org/contributor-roles/$it.credit$/" vocab-term="$it.credit-name$">$if(it.name)$$it.name$$else$$it.credit-name$$endif$</role>
$elseif(it.name)$
<role>$it.name$</role>
$endif$
$endfor$
$if(author.email)$
<email>$author.email$</email>
$endif$
$if(affiliation)$
$for(author.affiliation)$
<xref ref-type="aff" rid="aff-$author.affiliation$"/>
$endfor$
$else$
$for(author.affiliation)$
${ it:affiliations.jats() }
$endfor$
$endif$
$if(author.cor-id)$
<xref ref-type="corresp" rid="cor-$author.cor-id$"><sup>*</sup></xref>
$endif$
</contrib>
$endfor$
$for(affiliation)$
${ it:affiliations.jats() }
$endfor$
</contrib-group>
$endif$
$if(article.author-notes)$
<author-notes>
$for(article.author-notes.corresp)$
<corresp id="cor-$article.author-notes.corresp.id$">* E-mail: <email>$article.author-notes.corresp.email$</email></corresp>
$endfor$
$if(article.author-notes.conflict)$
<fn fn-type="conflict"><p>$article.author-notes.conflict$</p></fn>
$endif$
$if(article.author-notes.con)$
<fn fn-type="con"><p>$article.author-notes.con$</p></fn>
$endif$
</author-notes>
$endif$
$if(date)$
<pub-date date-type="$if(date.type)$$date.type$$else$pub$endif$" publication-format="electronic"$if(date.iso-8601)$ iso-8601-date="$date.iso-8601$"$endif$>
$if(date.day)$
<day>$date.day$</day>
$endif$
$if(date.month)$
<month>$date.month$</month>
$endif$
<year>$date.year$</year>
</pub-date>
$endif$
$if(article.volume)$
<volume>$article.volume$</volume>
$endif$
$if(article.issue)$
<issue>$article.issue$</issue>
$endif$
$if(article.fpage)$
<fpage>$article.fpage$</fpage>
$endif$
$if(article.lpage)$
<lpage>$article.lpage$</lpage>
$endif$
$if(article.elocation-id)$
<elocation-id>$article.elocation-id$</elocation-id>
$endif$
$if(history)$
<history>
</history>
$endif$
<permissions>
$for(copyright.statement)$
<copyright-statement>$copyright.statement$</copyright-statement>
$endfor$
$for(copyright.year)$
<copyright-year>$copyright.year$</copyright-year>
$endfor$
$for(copyright.holder)$
<copyright-holder>$copyright.holder$</copyright-holder>
$endfor$
$if(copyright.text)$
<license license-type="$copyright.type$" xlink:href="$copyright.link$">
<license-p>$copyright.text$</license-p>
</license>
$endif$
$for(license)$
<license$if(it.type)$ license-type="${it.type}"$endif$>
$if(it.link)$
<ali:license_ref xmlns:ali="http://www.niso.org/schemas/ali/1.0/">${it.link}</ali:license_ref>
$endif$
<license-p>$if(it.text)$${it.text}$else$${it}$endif$</license-p>
</license>
$endfor$
</permissions>
$if(abstract)$
<abstract>
$abstract$
</abstract>
$endif$
$if(tags)$
<kwd-group kwd-group-type="author">
$for(tags)$
<kwd>$tags$</kwd>
$endfor$
</kwd-group>
$endif$
$if(article.funding-statement)$
<funding-group>
<funding-statement>$article.funding-statement$</funding-statement>
</funding-group>
$endif$
</article-meta>
$if(notes)$
<notes>$notes$</notes>
$endif$
</front>
<body>
$body$
</body>
<back>
$if(back)$
$back$
$endif$
</back>
$if(floats-group)$
<floats-group>
$floats-group$
</floats-group>
$endif$
</article>
JATS_ARTICLE;
    }

    private function defaultJatsArticleAuthoringTemplate(): string
    {
        return <<<'JATS_AUTHORING'
<?xml version="1.0" encoding="utf-8" ?>
$if(xml-stylesheet)$
<?xml-stylesheet type="text/xsl" href="$xml-stylesheet$"?>
$endif$
<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Article Authoring DTD v1.2 20190208//EN"
                  "JATS-articleauthoring1.dtd">
$if(article.type)$
<article xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:xlink="http://www.w3.org/1999/xlink" dtd-version="1.2" article-type="$article.type$">
$else$
<article xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:xlink="http://www.w3.org/1999/xlink" dtd-version="1.2" article-type="other">
$endif$
<front>
<article-meta>
$if(title)$
<title-group>
<article-title>$title$</article-title>
$if(subtitle)$
<subtitle>${subtitle}</subtitle>
$endif$
</title-group>
$endif$
$if(author)$
<contrib-group>
$for(author)$
<contrib contrib-type="author"$if(author.equal-contrib)$ equal-contrib="yes"$endif$$if(author.cor-id)$ corresp="yes"$endif$>
$if(author.orcid)$
<contrib-id contrib-id-type="orcid">$author.orcid$</contrib-id>
$endif$
$if(author.surname)$
<name>
<surname>$if(author.non-dropping-particle)$${author.non-dropping-particle} $endif$${author.surname}</surname>
<given-names>${author.given-names}$if(author.dropping-particle)$ ${author.dropping-particle}$endif$</given-names>
</name>
$elseif(author.name)$
<string-name>$author.name$</string-name>
$else$
<string-name>$author$</string-name>
$endif$
$for(author.affiliation)$
${ it:affiliations.jats() }
$endfor$
$if(author.email)$
<email>$author.email$</email>
$endif$
$if(author.cor-id)$
<xref ref-type="corresp" rid="cor-$author.cor-id$"><sup>*</sup></xref>
$endif$
</contrib>
$endfor$
</contrib-group>
$endif$
<permissions>
$for(copyright.statement)$
<copyright-statement>$copyright.statement$</copyright-statement>
$endfor$
$for(copyright.year)$
<copyright-year>$copyright.year$</copyright-year>
$endfor$
$for(copyright.holder)$
<copyright-holder>$copyright.holder$</copyright-holder>
$endfor$
$if(copyright.text)$
<license license-type="$copyright.type$" xlink:href="$copyright.link$">
<license-p>$copyright.text$</license-p>
</license>
$endif$
$for(license)$
<license$if(it.type)$ license-type="${it.type}"$endif$$if(it.link)$ xlink:href="${it.link}"$endif$>
<license-p>$if(it.text)$${it.text}$else$${it}$endif$</license-p>
</license>
$endfor$
</permissions>
$if(abstract)$
<abstract>
$abstract$
</abstract>
$endif$
$if(tags)$
<kwd-group kwd-group-type="author">
$for(tags)$
<kwd>$tags$</kwd>
$endfor$
</kwd-group>
$endif$
$if(article.funding-statement)$
<funding-group>
<funding-statement>$article.funding-statement$</funding-statement>
</funding-group>
$endif$
$if(supplementary-material)$
<supplementary-material>
$supplementary-material$
</supplementary-material>
$endif$
</article-meta>
</front>
<body>
$body$
</body>
<back>
$if(back)$
$back$
$endif$
</back>
</article>
JATS_AUTHORING;
    }

    private function defaultJatsAffiliationsTemplate(): string
    {
        return <<<'JATS_AFFILIATIONS'
<aff id="aff-$it.id$">
$if(it.group)$
<institution content-type="group">${it.group}</institution>
$endif$
$if(it.department)$
<institution content-type="dept">${it.department}</institution>
$endif$
<institution-wrap>
$if(it.organization)$
<institution>${it.organization}</institution>
$else$
<institution>${it.name}</institution>
$endif$
$if(it.isni)$
<institution-id institution-id-type="ISNI">${it.isni}</institution-id>
$endif$
$if(it.ringgold)$
<institution-id institution-id-type="Ringgold">${it.ringgold}</institution-id>
$endif$
$if(it.ror)$
<institution-id institution-id-type="ROR">${it.ror}</institution-id>
$endif$
$for(it.pid)$
<institution-id institution-id-type="${it.type}">${it.id}</institution-id>
$endfor$
</institution-wrap>$if(it.street-address)$,
$for(it.street-address)$
<addr-line>${it}</addr-line>$sep$,
$endfor$
$else$$if(it.city)$, <city>$it.city$</city>$endif$$endif$$if(it.country)$,
<country$if(it.country-code)$ country="$it.country-code$"$endif$>$it.country$</country>$endif$
</aff>
JATS_AFFILIATIONS;
    }

    private function defaultTypstTemplate(): string
    {
        return <<<'TYPST'
#let horizontalrule = line(start: (25%,0%), end: (75%,0%))
#show terms.item: it => block(breakable: false)[
  #text(weight: "bold")[#it.term]
  #block(inset: (left: 1.5em, top: -0.4em))[#it.description]
]
#set table(inset: 6pt, stroke: none)
#show figure.where(kind: table): set figure.caption(position: $if(table-caption-position)$$table-caption-position$$else$top$endif$)
#show figure.where(kind: image): set figure.caption(position: $if(figure-caption-position)$$figure-caption-position$$else$bottom$endif$)
$if(highlighting-definitions)$
// syntax highlighting functions from skylighting:
$highlighting-definitions$
$endif$
$if(template)$
#import "$template$": conf
$else$
$template.typst()$
$endif$
$if(smart)$
$else$
#set smartquote(enabled: false)
$endif$
$for(header-includes)$
$header-includes$
$endfor$
#show: doc => conf(
$if(title)$
  title: [$title$],
$endif$
$if(subtitle)$
  subtitle: [$subtitle$],
$endif$
$if(author)$
  authors: (
$for(author)$
$if(author.name)$
    (name: [$author.name$], affiliation: [$author.affiliation$], email: [$author.email$]),
$else$
    (name: [$author$], affiliation: "", email: ""),
$endif$
$endfor$
  ),
$endif$
$if(keywords)$
  keywords: ($for(keywords)$$keywords$$sep$,$endfor$),
$endif$
$if(date)$
  date: [$date$],
$endif$
$if(lang)$
  lang: "$lang$",
$endif$
$if(region)$
  region: "$region$",
$endif$
$if(abstract-title)$
  abstract-title: [$abstract-title$],
$endif$
$if(abstract)$
  abstract: [$abstract$],
$endif$
$if(thanks)$
  thanks: [$thanks$],
$endif$
$if(margin)$
  margin: ($for(margin/pairs)$$margin.key$: $margin.value$,$endfor$),
$endif$
$if(papersize)$
  paper: "$papersize$",
$endif$
$if(mainfont)$
  font: ("$mainfont$",),
$endif$
$if(fontsize)$
  fontsize: $fontsize$,
$endif$
$if(mathfont)$
  mathfont: ($for(mathfont)$"$mathfont$",$endfor$),
$endif$
$if(codefont)$
  codefont: ($for(codefont)$"$codefont$",$endfor$),
$endif$
$if(linestretch)$
  linestretch: $linestretch$,
$endif$
$if(section-numbering)$
  sectionnumbering: "$section-numbering$",
$endif$
  pagenumbering: $if(page-numbering)$"$page-numbering$"$else$none$endif$,
$if(linkcolor)$
  linkcolor: [$linkcolor$],
$endif$
$if(citecolor)$
  citecolor: [$citecolor$],
$endif$
$if(filecolor)$
  filecolor: [$filecolor$],
$endif$
  cols: $if(columns)$$columns$$else$1$endif$,
  doc,
)
$for(include-before)$
$include-before$
$endfor$
$if(toc)$
#outline(title: auto, depth: $toc-depth$);
$endif$
$body$
$if(citations)$
$for(nocite-ids)$
#cite(label("$it$"), form: none)
$endfor$
$if(csl)$
#set bibliography(style: "$csl$")
$elseif(bibliographystyle)$
#set bibliography(style: "$bibliographystyle$")
$endif$
$if(bibliography)$
#bibliography(($for(bibliography)$"$bibliography$"$sep$,$endfor$)$if(full-bibliography)$, full: true$endif$)
$endif$
$endif$
$for(include-after)$
$include-after$
$endfor$
TYPST;
    }

    private function defaultTypstConfTemplate(): string
    {
        return <<<'TYPST_CONF'
#let content-to-string(content) = {
  if content.has("text") {
    content.text
  } else if content.has("children") {
    content.children.map(content-to-string).join("")
  } else if content.has("body") {
    content-to-string(content.body)
  } else if content == [ ] {
    " "
  }
}

#let conf(
  title: none,
  subtitle: none,
  authors: (),
  keywords: (),
  date: none,
  abstract-title: none,
  abstract: none,
  thanks: none,
  cols: 1,
  margin: (x: 1.25in, y: 1.25in),
  paper: "us-letter",
  lang: "en",
  region: "US",
  font: none,
  fontsize: 11pt,
  mathfont: none,
  codefont: none,
  linestretch: 1,
  sectionnumbering: none,
  linkcolor: none,
  citecolor: none,
  filecolor: none,
  pagenumbering: "1",
  doc,
) = {
  set document(title: title, keywords: keywords)
  set document(author: authors.map(author => content-to-string(author.name)).join(", ", last: " & "))
  if authors != none and authors != () {
    set page(paper: paper, margin: margin, numbering: pagenumbering, columns: cols)
  }
  set par(justify: true, leading: linestretch * 0.65em)
  set text(lang: lang, region: region, size: fontsize)
  set text(font: font) if font != none
  show math.equation: set text(font: mathfont) if mathfont != none
  show raw: set text(font: codefont) if codefont != none
  set heading(numbering: sectionnumbering)
  show link: set text(fill: rgb(content-to-string(linkcolor))) if linkcolor != none
  show ref: set text(fill: rgb(content-to-string(citecolor))) if citecolor != none
  show link: this => {
    if filecolor != none and type(this.dest) == label {
      text(this, fill: rgb(content-to-string(filecolor)))
    } else {
      text(this)
    }
  }
  if title != none {
    place(top, float: true, scope: "parent", clearance: 4mm, block(below: 1em, width: 100%)[
      #align(center, block[
        #text(weight: "bold", size: 1.5em, hyphenate: false)[#title]
        #(if subtitle != none { parbreak() text(weight: "bold", size: 1.25em, hyphenate: false)[#subtitle] })
      ])
      #if authors != none and authors != [] {
        let count = authors.len()
        let ncols = calc.min(count, 3)
        grid(columns: (1fr,) * ncols, row-gutter: 1.5em, ..authors.map(author => align(center)[
          #author.name \
          #author.affiliation \
          #author.email
        ]))
      }
      #if date != none { align(center)[#block(inset: 1em)[#date]] }
      #if abstract != none {
        block(inset: 2em)[#text(weight: "semibold")[#abstract-title] #h(1em) #abstract]
      }
    ])
  }
  doc
}
TYPST_CONF;
    }

    private function defaultTypstDefinitionsTemplate(): string
    {
        return <<<'TYPST_DEFINITIONS'
// Some definitions presupposed by pandoc's typst output.
#let horizontalrule = [
  #line(start: (25%,0%), end: (75%,0%))
]

#let endnote(num, contents) = [
  #stack(dir: ltr, spacing: 3pt, super[#num], contents)
]
TYPST_DEFINITIONS;
    }

    private function defaultHtml5Template(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"$if(lang)$ lang="$lang$" xml:lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
<head>
<meta charset="utf-8" />
<meta name="generator" content="pandoc $pandoc-version$" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
$for(author-meta)$<meta name="author" content="$it$" />
$endfor$$if(date-meta)$<meta name="dcterms.date" content="$date-meta$" />
$endif$$if(keywords)$<meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$" />
$endif$$if(description-meta)$<meta name="description" content="$description-meta$" />
$endif$<title>$if(title-prefix)$$title-prefix$ &ndash; $endif$$if(pagetitle)$$pagetitle$$elseif(title)$$title$$endif$</title>
<style>
    $styles.html()$
</style>
$for(css)$<link rel="stylesheet" href="$it$" />
$endfor$$for(header-includes)$$it$
$endfor$$if(math)$$math$
$endif$</head>
<body>
$for(include-before)$$it$
$endfor$$if(title)$<header id="title-block-header">
<h1 class="title">$title$</h1>
$if(subtitle)$<p class="subtitle">$subtitle$</p>
$endif$$for(author)$<p class="author">$it$</p>
$endfor$$if(date)$<p class="date">$date$</p>
$endif$$if(abstract)$<div class="abstract">
<div class="abstract-title">$abstract-title$</div>
$abstract$
</div>
$endif$</header>
$endif$$if(toc)$<nav id="$idprefix$TOC" role="doc-toc">
$if(toc-title)$<h2 id="$idprefix$toc-title">$toc-title$</h2>
$endif$$table-of-contents$
</nav>
$endif$$body$
$for(include-after)$
$it$$endfor$
</body>
</html>
HTML;
    }

    private function defaultHtml4Template(): string
    {
        return <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"$if(lang)$ lang="$lang$" xml:lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta name="generator" content="pandoc $pandoc-version$" />
$for(author-meta)$
  <meta name="author" content="$author-meta$" />
$endfor$
$if(date-meta)$
  <meta name="date" content="$date-meta$" />
$endif$
$if(keywords)$
  <meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$" />
$endif$
$if(description-meta)$
  <meta name="description" content="$description-meta$" />
$endif$
  <title>$if(title-prefix)$$title-prefix$ – $endif$$pagetitle$</title>
  <style type="text/css">
    $styles.html()$
  </style>
$for(css)$
  <link rel="stylesheet" href="$css$" type="text/css" />
$endfor$
$for(header-includes)$
  $header-includes$
$endfor$
$if(math)$
  $math$
$endif$
</head>
<body>
$for(include-before)$
$include-before$
$endfor$
$if(title)$
<div id="$idprefix$header">
<h1 class="title">$title$</h1>
$if(subtitle)$
<h1 class="subtitle">$subtitle$</h1>
$endif$
$for(author)$
<h2 class="author">$author$</h2>
$endfor$
$if(date)$
<h3 class="date">$date$</h3>
$endif$
$if(abstract)$
<div class="abstract">
<div class="abstract-title">$abstract-title$</div>
$abstract$
</div>
$endif$
</div>
$endif$
$if(toc)$
<div id="$idprefix$TOC">
$if(toc-title)$
<h2 id="$idprefix$toc-title">$toc-title$</h2>
$endif$
$table-of-contents$
</div>
$endif$
$body$
$for(include-after)$
$include-after$
$endfor$
</body>
</html>
HTML;
    }

    private function defaultChunkedHtmlTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="$lang$" xml:lang="$lang$"$if(dir)$ dir="$dir$"$endif$>
<head>
  <meta charset="utf-8" />
  <meta name="generator" content="pandoc" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
$for(author-meta)$
  <meta name="author" content="$author-meta$" />
$endfor$
$if(date-meta)$
  <meta name="dcterms.date" content="$date-meta$" />
$endif$
$if(keywords)$
  <meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$" />
$endif$
$if(description-meta)$
  <meta name="description" content="$description-meta$" />
$endif$
  <title>$if(title-prefix)$$title-prefix$ – $endif$$pagetitle$</title>
  <style>
    div.sitenav { display: flex; flex-direction: row; flex-wrap: wrap; }
    span.navlink { flex: 1; }
    span.navlink-label { display: inline-block; min-width: 4em; }
    $styles.html()$
  </style>
$for(css)$
  <link rel="stylesheet" href="$css$" />
$endfor$
$for(header-includes)$
  $header-includes$
$endfor$
$if(math)$
  $math$
$endif$
</head>
<body>
$for(include-before)$
$include-before$
$endfor$
<nav id="sitenav">
<div class="sitenav">
<span class="navlink">
$if(up.url)$
<span class="navlink-label">Up:</span> <a href="$up.url$" accesskey="u" rel="up">$up.title$</a>
$endif$
</span>
<span class="navlink">
$if(top)$
<span class="navlink-label">Top:</span> <a href="$top.url$" accesskey="t" rel="top">$top.title$</a>
$endif$
</span>
</div>
<div class="sitenav">
<span class="navlink">
$if(next.url)$
<span class="navlink-label">Next:</span> <a href="$next.url$" accesskey="n" rel="next">$next.title$</a>
$endif$
</span>
<span class="navlink">
$if(previous.url)$
<span class="navlink-label">Previous:</span> <a href="$previous.url$" accesskey="p" rel="previous">$previous.title$</a>
$endif$
</span>
</div>
</nav>
$if(top)$
$-- only print title block if this is NOT the top page
$else$
$if(title)$
<header id="title-block-header">
<h1 class="title">$title$</h1>
$if(subtitle)$
<p class="subtitle">$subtitle$</p>
$endif$
$for(author)$
<p class="author">$author$</p>
$endfor$
$if(date)$
<p class="date">$date$</p>
$endif$
$if(abstract)$
<div class="abstract">
<div class="abstract-title">$abstract-title$</div>
$abstract$
</div>
$endif$
$endif$
</header>
$endif$
$if(toc)$
<nav id="$idprefix$TOC" role="doc-toc">
$if(toc-title)$
<h2 id="$idprefix$toc-title">$toc-title$</h2>
$endif$
$table-of-contents$
</nav>
$endif$
$body$
$for(include-after)$
$include-after$
$endfor$
</body>
</html>
HTML;
    }

    private function defaultPlainTemplate(): string
    {
        return <<<'PLAIN'
$if(titleblock)$
$titleblock$
$endif$
$for(header-includes)$
$header-includes$
$endfor$
$for(include-before)$
$include-before$
$endfor$
$if(toc)$
$table-of-contents$
$endif$
$body$
$for(include-after)$
$include-after$
$endfor$
PLAIN;
    }

    private function defaultAnsiTemplate(): string
    {
        return <<<'ANSI'
$if(titleblock)$
$titleblock$
$endif$
$for(header-includes)$
$header-includes$
$endfor$
$for(include-before)$
$include-before$
$endfor$
$if(toc)$
$table-of-contents$
$endif$
$body$
$for(include-after)$
$include-after$
$endfor$
ANSI;
    }

    private function defaultBibliographyTemplate(): string
    {
        return <<<'BIBLIOGRAPHY'
$if(titleblock)$
$titleblock$
$endif$
$for(header-includes)$
$header-includes$
$endfor$
$for(include-before)$
$include-before$
$endfor$
$if(toc)$
$table-of-contents$
$endif$
$body$
$for(include-after)$
$include-after$
$endfor$
BIBLIOGRAPHY;
    }

    private function defaultMuseTemplate(): string
    {
        return <<<'MUSE'
$if(author)$
#author $for(author)$$author$$sep$; $endfor$
$endif$
$if(title)$
#title $title$
$endif$
$if(lang)$
#lang $lang$
$endif$
$if(LISTtitle)$
#LISTtitle $LISTtitle$
$endif$
$if(subtitle)$
#subtitle $subtitle$
$endif$
$if(SORTauthors)$
#SORTauthors $SORTauthors$
$endif$
$if(SORTtopics)$
#SORTtopics $SORTtopics$
$endif$
$if(date)$
#date $date$
$endif$
$if(notes)$
#notes $notes$
$endif$
$if(source)$
#source $source$
$endif$

$for(header-includes)$
$header-includes$

$endfor$
$for(include-before)$
$include-before$

$endfor$
$body$
$for(include-after)$

$include-after$
$endfor$
MUSE;
    }

    private function defaultOrgTemplate(): string
    {
        return <<<'ORG'
$if(title)$
#+title: $title$

$endif$
$if(author)$
#+author: $for(author)$$author$$sep$; $endfor$
$endif$
$if(date)$
#+date: $date$

$endif$
$if(options/pairs)$
$for(options/pairs)$
#+options: ${it.key}:${it.value}
$endfor$

$endif$
$for(header-includes)$
$header-includes$

$endfor$
$if(abstract)$
#+begin_abstract
$abstract$
#+end_abstract
$endif$
$for(include-before)$
$include-before$

$endfor$
$body$
$for(include-after)$

$include-after$
$endfor$
ORG;
    }

    private function defaultTexinfoTemplate(): string
    {
        return <<<'TEXINFO'
\input texinfo  @c -*-texinfo-*-
$if(filename)$
@setfilename $filename$
$endif$
$if(title)$
@settitle $title$$if(version)$ $version$$endif$
$endif$

@documentencoding UTF-8
$for(header-includes)$
$header-includes$
$endfor$

$if(strikeout)$
@macro textstrikeout{text}
~~\text\~~
@end macro

$endif$
@ifnottex
@paragraphindent 0
@end ifnottex
$if(titlepage)$
@titlepage
@title $title$
$if(version)$
@subtitle $version$
$endif$
$for(author)$
@author $author$
$endfor$
$if(date)$
$date$
$endif$
@end titlepage

$endif$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
@contents

$endif$
$body$
$for(include-after)$

$include-after$
$endfor$

@bye
TEXINFO;
    }

    private function defaultBbcodeTemplate(): string
    {
        return '$body$';
    }

    private function defaultHtmlStylesTemplate(): string
    {
        return <<<'CSS'
/* Default styles provided by pandoc.
** See https://pandoc.org/MANUAL.html#variables-for-html for config info.
*/
$if(document-css)$
html {
$if(mainfont)$
  font-family: $mainfont$;
$endif$
$if(fontsize)$
  font-size: $fontsize$;
$endif$
$if(linestretch)$
  line-height: $linestretch$;
$endif$
  color: $if(fontcolor)$$fontcolor$$else$#1a1a1a$endif$;
  background-color: $if(backgroundcolor)$$backgroundcolor$$else$#fdfdfd$endif$;
}
body {
  margin: 0 auto;
  max-width: $if(maxwidth)$$maxwidth$$else$36em$endif$;
  padding-left: $if(margin-left)$$margin-left$$else$50px$endif$;
  padding-right: $if(margin-right)$$margin-right$$else$50px$endif$;
  padding-top: $if(margin-top)$$margin-top$$else$50px$endif$;
  padding-bottom: $if(margin-bottom)$$margin-bottom$$else$50px$endif$;
  hyphens: auto;
  overflow-wrap: break-word;
  text-rendering: optimizeLegibility;
  font-kerning: normal;
}
@media (max-width: 600px) {
  body {
    font-size: 0.9em;
    padding: 12px;
  }
  h1 {
    font-size: 1.8em;
  }
}
@media print {
  html {
    background-color: $if(backgroundcolor)$$backgroundcolor$$else$white$endif$;
  }
  body {
    background-color: transparent;
    color: black;
    font-size: 12pt;
  }
  p, h2, h3 {
    orphans: 3;
    widows: 3;
  }
  h2, h3, h4 {
    page-break-after: avoid;
  }
}
p {
  margin: 1em 0;
}
a {
  color: $if(linkcolor)$$linkcolor$$else$#1a1a1a$endif$;
}
a:visited {
  color: $if(linkcolor)$$linkcolor$$else$#1a1a1a$endif$;
}
img {
  max-width: 100%;
}
svg {
  height: auto;
  max-width: 100%;
}
h1, h2, h3, h4, h5, h6 {
  margin-top: 1.4em;
}
h5, h6 {
  font-size: 1em;
  font-style: italic;
}
h6 {
  font-weight: normal;
}
ol, ul {
  padding-left: 1.7em;
  margin-top: 1em;
}
li > ol, li > ul {
  margin-top: 0;
}
blockquote {
  margin: 1em 0 1em 1.7em;
  padding-left: 1em;
  border-left: 2px solid #e6e6e6;
  color: #606060;
}
$if(abstract)$
div.abstract {
  margin: 2em 2em 2em 2em;
  text-align: left;
  font-size: 85%;
}
div.abstract-title {
  font-weight: bold;
  text-align: center;
  padding: 0;
  margin-bottom: 0.5em;
}
$endif$
code {
  white-space: pre-wrap;
  font-family: $if(monofont)$$monofont$$else$Menlo, Monaco, Consolas, 'Lucida Console', monospace$endif$;
$if(monobackgroundcolor)$
  background-color: $monobackgroundcolor$;
  padding: .2em .4em;
$endif$
  font-size: 85%;
  margin: 0;
  hyphens: manual;
}
pre {
  margin: 1em 0;
$if(monobackgroundcolor)$
  background-color: $monobackgroundcolor$;
  padding: 1em;
$endif$
  overflow: auto;
}
pre code {
  padding: 0;
  overflow: visible;
  overflow-wrap: normal;
}
.sourceCode {
 background-color: transparent;
 overflow: visible;
}
hr {
  border: none;
  border-top: 1px solid #1a1a1a;
  height: 1px;
  margin: 1em 0;
}
table {
  margin: 1em 0;
  border-collapse: collapse;
  width: 100%;
  overflow-x: auto;
  display: block;
  font-variant-numeric: lining-nums tabular-nums;
}
table caption {
$if(table-caption-below)$
  caption-side: bottom;
  margin-top: 0.75em;
$else$
  margin-bottom: 0.75em;
$endif$
}
tbody {
  margin-top: 0.5em;
  border-top: 1px solid $if(fontcolor)$$fontcolor$$else$#1a1a1a$endif$;
  border-bottom: 1px solid $if(fontcolor)$$fontcolor$$else$#1a1a1a$endif$;
}
th {
  border-top: 1px solid $if(fontcolor)$$fontcolor$$else$#1a1a1a$endif$;
  padding: 0.25em 0.5em 0.25em 0.5em;
}
td {
  padding: 0.125em 0.5em 0.25em 0.5em;
}
header {
  margin-bottom: 4em;
  text-align: center;
}
#TOC li {
  list-style: none;
}
#TOC ul {
  padding-left: 1.3em;
}
#TOC > ul {
  padding-left: 0;
}
#TOC a:not(:hover) {
  text-decoration: none;
}
$endif$
span.smallcaps{font-variant: small-caps;}
div.columns{display: flex; gap: 1.5em;}
div.column{flex: auto;}
@media screen {
div.columns{gap: min(4vw, 1.5em);}
div.column{overflow-x: auto;}
}
div.hanging-indent{margin-left: 1.5em; text-indent: -1.5em;}
/* The extra [class] is a hack that increases specificity enough to
   override a similar rule in reveal.js */
ul.task-list[class]{list-style: none;}
ul.task-list li input[type="checkbox"] {
  font-size: inherit;
  width: 0.8em;
  margin: 0 0.8em 0.2em -1.6em;
  vertical-align: middle;
}
$if(quotes)$
q { quotes: "\201C" "\201D" "\2018" "\2019"; }
$endif$
$if(displaymath-css)$
.display.math{display: block; text-align: center; margin: 0.5rem auto;}
$endif$
$if(highlighting-css)$
/* CSS for syntax highlighting */
$highlighting-css$
$endif$
$if(csl-css)$
$styles.citations.html()$
$endif$
CSS;
    }

    private function defaultHtmlCitationStylesTemplate(): string
    {
        return <<<'CSS'
/* CSS for citations */
div.csl-bib-body { }
div.csl-entry {
  clear: both;
$if(csl-entry-spacing)$
  margin-bottom: $csl-entry-spacing$;
$endif$
}
.hanging-indent div.csl-entry {
  margin-left:2em;
  text-indent:-2em;
}
div.csl-left-margin {
  min-width:2em;
  float:left;
}
div.csl-right-inline {
  margin-left:2em;
  padding-left:1em;
}
div.csl-indent {
  margin-left: 2em;
}
CSS;
    }

    /**
     * @param array<string, string> $partials
     * @return array<string, string>
     */
    private function normalizePartialMap(array $partials): array
    {
        $normalized = [];
        foreach ($partials as $name => $source) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Doctemplate partial names must be strings');
            }

            if (!is_string($source)) {
                throw new \InvalidArgumentException("Doctemplate partial {$name} must be a string");
            }

            $normalized[$this->normalizePartialName($name)] = $source;
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $partials
     * @return array<string, string>
     */
    private function partialSourceMap(array $partials): array
    {
        $sources = [];
        foreach ($partials as $name => $_source) {
            $sources[$name] = $name;
        }

        return $sources;
    }

    private function normalizeTemplateResourcePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Invalid doctemplate resource path');
        }

        $path = str_replace('\\', '/', $path);
        $absolute = str_starts_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Doctemplate resource paths must not contain parent-directory segments');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \InvalidArgumentException('Invalid doctemplate resource path');
        }

        return ($absolute ? '/' : '') . implode('/', $segments);
    }

    private function normalizePartialName(string $name): string
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('Invalid doctemplate partial name');
        }

        $name = str_replace('\\', '/', $name);
        if (str_starts_with($name, '/')) {
            throw new \InvalidArgumentException('Doctemplate partial names must be relative paths');
        }

        $segments = [];
        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Doctemplate partial names must not contain empty, current-directory, or parent-directory segments');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * @param array<string, string> $resources
     * @return array{partials:array<string, string>, sources:array<string, string>}
     */
    private function partialResourcesForTemplateResource(string $templatePath, array $resources, ?string $userDataDirectory): array
    {
        $mainDirectory = $this->templateResourceDirectory($templatePath);
        $mainExtension = $this->templateResourceExtension($this->templateResourceBasename($templatePath));
        $partialExtensionFallbacks = $this->partialResourceExtensionFallbacks($mainExtension);
        $searchDirectories = [$mainDirectory];
        if ($userDataDirectory !== null && !$this->isAbsoluteTemplateResourcePath($templatePath)) {
            $searchDirectories[] = $this->joinTemplateResourcePath(
                $this->normalizeTemplateResourcePath($userDataDirectory),
                'templates',
            );
        }

        $partials = [];
        $sources = [];
        $this->registerPartialResources(
            $resources,
            $templatePath,
            $searchDirectories,
            $partialExtensionFallbacks,
            $partials,
            $sources,
        );
        $this->registerPartialResources(
            $this->defaultPartialResourceFallbackMap($resources, $searchDirectories),
            $templatePath,
            $searchDirectories,
            $partialExtensionFallbacks,
            $partials,
            $sources,
        );
        $sources[self::DEFAULT_PARTIAL_FALLBACK_SENTINEL] = '1';

        return [
            'partials' => $partials,
            'sources' => $sources,
        ];
    }

    /**
     * @param array<string, string> $resources
     * @param list<string> $searchDirectories
     * @param list<string> $partialExtensionFallbacks
     * @param array<string, string> $partials
     * @param array<string, string> $sources
     */
    private function registerPartialResources(array $resources, string $templatePath, array $searchDirectories, array $partialExtensionFallbacks, array &$partials, array &$sources): void
    {
        foreach ($searchDirectories as $directory) {
            $availableRelativePaths = $this->availableRelativePartialResourcePaths($resources, $templatePath, $directory);
            foreach ($partialExtensionFallbacks as $extension) {
                foreach ($resources as $resourcePath => $source) {
                    if ($resourcePath === $templatePath) {
                        continue;
                    }

                    $relativePath = $this->relativeTemplateResourceChild($resourcePath, $directory);
                    if ($relativePath === null) {
                        continue;
                    }

                    foreach ($this->partialAliasesForResourcePath($relativePath, $extension, $availableRelativePaths) as $alias) {
                        if (!array_key_exists($alias, $partials)) {
                            $partials[$alias] = $source;
                            $sources[$alias] = $resourcePath;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array<string, string> $resources
     * @return array<string, true>
     */
    private function availableRelativePartialResourcePaths(array $resources, string $templatePath, string $directory): array
    {
        $paths = [];
        foreach ($resources as $resourcePath => $_source) {
            if ($resourcePath === $templatePath) {
                continue;
            }

            $relativePath = $this->relativeTemplateResourceChild($resourcePath, $directory);
            if ($relativePath !== null) {
                $paths[$relativePath] = true;
            }
        }

        return $paths;
    }

    /**
     * @param array<string, string> $resources
     * @param list<string> $searchDirectories
     * @return array<string, string>
     */
    private function defaultPartialResourceFallbackMap(array $resources, array $searchDirectories): array
    {
        $defaults = [];
        foreach ($searchDirectories as $directory) {
            foreach ($this->defaultTemplateResourceBasenames() as $basename) {
                $resourcePath = $this->joinTemplateResourcePath($directory, $basename);
                if (array_key_exists($resourcePath, $resources)) {
                    continue;
                }

                $default = $this->defaultTemplateResourceForBasename($basename);
                if ($default !== null) {
                    $defaults[$resourcePath] = $default;
                }
            }
        }

        return $defaults;
    }

    /**
     * @return list<string>
     */
    private function partialResourceExtensionFallbacks(string $mainExtension): array
    {
        $extensions = [$mainExtension];
        $baseExtension = $this->extensionQualifiedResourceBaseExtension($mainExtension);
        if ($baseExtension !== null && $baseExtension !== $mainExtension) {
            $extensions[] = $baseExtension;
        }

        return $extensions;
    }

    private function extensionQualifiedResourceBaseExtension(string $extension): ?string
    {
        if ($extension === '') {
            return null;
        }

        $featureOffset = strcspn($extension, '+-');
        if ($featureOffset <= 1 || $featureOffset >= strlen($extension)) {
            return null;
        }

        return substr($extension, 0, $featureOffset);
    }

    private function isAbsoluteTemplateResourcePath(string $path): bool
    {
        return str_starts_with($path, '/');
    }

    private function templateResourceDirectory(string $path): string
    {
        $slash = strrpos($path, '/');
        if ($slash === false) {
            return '';
        }

        if ($slash === 0) {
            return '/';
        }

        return substr($path, 0, $slash);
    }

    private function templateResourceBasename(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? $path : substr($path, $slash + 1);
    }

    private function templateResourceExtension(string $basename): string
    {
        $dot = strrpos($basename, '.');
        if ($dot === false || $dot === 0) {
            return '';
        }

        return substr($basename, $dot);
    }

    private function joinTemplateResourcePath(string $directory, string $basename): string
    {
        if ($directory === '') {
            return $basename;
        }

        if ($directory === '/') {
            return '/' . $basename;
        }

        return $directory . '/' . $basename;
    }

    private function relativeTemplateResourceChild(string $path, string $directory): ?string
    {
        if ($directory === '') {
            return $this->isAbsoluteTemplateResourcePath($path) ? null : $path;
        }

        if ($directory === '/') {
            if (!str_starts_with($path, '/')) {
                return null;
            }

            $relative = substr($path, 1);

            return $relative !== '' ? $relative : null;
        }

        $prefix = $directory . '/';
        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = substr($path, strlen($prefix));

        return $relative !== '' ? $relative : null;
    }

    /**
     * @param array<string, true> $availableRelativePaths
     * @return list<string>
     */
    private function partialAliasesForResourcePath(string $relativePath, string $mainExtension, array $availableRelativePaths = []): array
    {
        $basename = $this->templateResourceBasename($relativePath);
        $extension = $this->templateResourceExtension($basename);
        if ($extension === '') {
            return $mainExtension === '' ? [$relativePath] : [];
        }

        $aliases = [$relativePath];
        if ($extension === $mainExtension) {
            $baseExtension = $this->extensionQualifiedResourceBaseExtension($extension);
            if ($baseExtension !== null) {
                $baseAlias = substr($relativePath, 0, -strlen($extension)) . $baseExtension;
                if (!array_key_exists($baseAlias, $availableRelativePaths)) {
                    $aliases[] = $baseAlias;
                }
            }
        }

        if ($extension === $mainExtension) {
            $aliases[] = substr($relativePath, 0, -strlen($extension));
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tokenize(string $template, string $sourceName, bool $initialBreakableSpaces = false): array
    {
        $tokens = [];
        $buffer = '';
        $breakableSpaces = $initialBreakableSpaces;
        $breakableSpaceStart = $initialBreakableSpaces ? 0 : null;
        $length = strlen($template);

        for ($index = 0; $index < $length; $index++) {
            $char = $template[$index];
            if ($char !== '$') {
                $buffer .= $char;
                continue;
            }

            if (substr($template, $index, 3) === '$--') {
                $lineEnding = $this->findCommentLineEnding($template, $index + 3);
                if ($lineEnding === null) {
                    break;
                }

                if ($this->commentStartsInFirstColumn($buffer)) {
                    $buffer = $this->dropStandaloneCommentLinePrefix($buffer);
                } else {
                    $buffer .= $lineEnding['value'];
                }
                $index = $lineEnding['start'] + $lineEnding['length'] - 1;
                continue;
            }

            if (($template[$index + 1] ?? '') === '$') {
                $buffer .= '$';
                $index++;
                continue;
            }

            if (($template[$index + 1] ?? '') === '{') {
                $unclosedSeparatorOffset = null;
                $unclosedQuoteOffset = null;
                $closing = $this->findBracedDirectiveClosing($template, $index + 2, $unclosedSeparatorOffset, $unclosedQuoteOffset);
                if ($closing === null) {
                    if ($unclosedSeparatorOffset !== null) {
                        $this->throwTemplateError('Unclosed doctemplate separator', $template, $unclosedSeparatorOffset, $sourceName);
                    }

                    if ($unclosedQuoteOffset !== null) {
                        $this->throwTemplateError('Unclosed doctemplate pipe quoted string', $template, $unclosedQuoteOffset, $sourceName);
                    }

                    $this->throwTemplateError('Unclosed doctemplate ${...} directive', $template, $index, $sourceName);
                }

                $rawDirective = substr($template, $index + 2, $closing - $index - 2);
                $directive = trim($rawDirective, " \t");
                if ($directive === '~') {
                    $this->appendTextToken($tokens, $buffer, $breakableSpaces);
                    $buffer = '';
                    $breakableSpaceStart = $breakableSpaces ? null : $index;
                    $breakableSpaces = !$breakableSpaces;
                    $index = $this->skipDirectiveLineTrailingHorizontalWhitespace($template, $closing);
                    continue;
                }

                $lineTrailingOffset = $this->skipDirectiveLineTrailingHorizontalWhitespace($template, $closing);
                if (
                    $this->isStandaloneControlDirective($directive)
                    && $this->offsetIsBeforeLineEndingOrEnd($template, $lineTrailingOffset)
                    && $this->sourceLinePrefixIsHorizontalWhitespace($template, $index)
                ) {
                    $buffer = $this->dropStandaloneControlLinePrefix($buffer);
                }

                $this->appendTextToken($tokens, $buffer, $breakableSpaces);
                $buffer = '';
                $location = $this->sourceLocation($template, $index);
                $directiveLocation = $this->sourceLocation($template, $index + 2 + strspn($rawDirective, " \t"));
                $tokens[] = [
                    'type' => 'directive',
                    'value' => $directive,
                    'source' => $sourceName,
                    'line' => $location['line'],
                    'column' => $location['column'],
                    'directiveLine' => $directiveLocation['line'],
                    'directiveColumn' => $directiveLocation['column'],
                    'breakable' => $breakableSpaces,
                ];
                $index = $lineTrailingOffset;
                continue;
            }

            $unclosedSeparatorOffset = null;
            $unclosedQuoteOffset = null;
            $closing = $this->findDollarDirectiveClosing($template, $index + 1, $unclosedSeparatorOffset, $unclosedQuoteOffset);
            if ($closing === null) {
                if ($unclosedSeparatorOffset !== null) {
                    $this->throwTemplateError('Unclosed doctemplate separator', $template, $unclosedSeparatorOffset, $sourceName);
                }

                if ($unclosedQuoteOffset !== null) {
                    $this->throwTemplateError('Unclosed doctemplate pipe quoted string', $template, $unclosedQuoteOffset, $sourceName);
                }

                $this->throwTemplateError('Unclosed doctemplate $...$ directive', $template, $index, $sourceName);
            }

            $rawDirective = substr($template, $index + 1, $closing - $index - 1);
            $directive = trim($rawDirective, " \t");
            if ($directive === '~') {
                $this->appendTextToken($tokens, $buffer, $breakableSpaces);
                $buffer = '';
                $breakableSpaceStart = $breakableSpaces ? null : $index;
                $breakableSpaces = !$breakableSpaces;
                $index = $this->skipDirectiveLineTrailingHorizontalWhitespace($template, $closing);
                continue;
            }

            $lineTrailingOffset = $this->skipDirectiveLineTrailingHorizontalWhitespace($template, $closing);
            if (
                $this->isStandaloneControlDirective($directive)
                && $this->offsetIsBeforeLineEndingOrEnd($template, $lineTrailingOffset)
                && $this->sourceLinePrefixIsHorizontalWhitespace($template, $index)
            ) {
                $buffer = $this->dropStandaloneControlLinePrefix($buffer);
            }

            $this->appendTextToken($tokens, $buffer, $breakableSpaces);
            $buffer = '';
            $location = $this->sourceLocation($template, $index);
            $directiveLocation = $this->sourceLocation($template, $index + 1 + strspn($rawDirective, " \t"));
            $tokens[] = [
                'type' => 'directive',
                'value' => $directive,
                'source' => $sourceName,
                'line' => $location['line'],
                'column' => $location['column'],
                'directiveLine' => $directiveLocation['line'],
                'directiveColumn' => $directiveLocation['column'],
                'breakable' => $breakableSpaces,
            ];
            $index = $lineTrailingOffset;
        }

        if ($breakableSpaces !== $initialBreakableSpaces) {
            $this->throwTemplateError(
                'Unclosed doctemplate breakable-space region',
                $template,
                $breakableSpaceStart ?? $length,
                $sourceName,
            );
        }

        $this->appendTextToken($tokens, $buffer, $breakableSpaces);

        return $tokens;
    }

    private function skipDirectiveLineTrailingHorizontalWhitespace(string $template, int $closingOffset): int
    {
        $offset = $closingOffset;
        $length = strlen($template);
        while ($offset + 1 < $length && ($template[$offset + 1] === ' ' || $template[$offset + 1] === "\t")) {
            $offset++;
        }

        if ($offset + 1 >= $length || $template[$offset + 1] === "\r" || $template[$offset + 1] === "\n") {
            return $offset;
        }

        return $closingOffset;
    }

    private function offsetIsBeforeLineEndingOrEnd(string $template, int $offset): bool
    {
        if ($offset + 1 >= strlen($template)) {
            return true;
        }

        return $template[$offset + 1] === "\r" || $template[$offset + 1] === "\n";
    }

    private function sourceLinePrefixIsHorizontalWhitespace(string $template, int $offset): bool
    {
        $lineStart = 0;
        for ($index = $offset - 1; $index >= 0; $index--) {
            if ($template[$index] === "\n" || $template[$index] === "\r") {
                $lineStart = $index + 1;
                break;
            }
        }

        $prefix = substr($template, $lineStart, max(0, $offset - $lineStart));

        return strspn($prefix, " \t") === strlen($prefix);
    }

    private function findBracedDirectiveClosing(string $template, int $start, ?int &$unclosedSeparatorOffset = null, ?int &$unclosedQuoteOffset = null): ?int
    {
        $unclosedSeparatorOffset = null;
        $unclosedQuoteOffset = null;
        $inQuote = false;
        $escape = false;
        $quoteOffset = null;
        $length = strlen($template);

        for ($index = $start; $index < $length; $index++) {
            $char = $template[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $separatorClosing = strpos($template, ']', $index + 1);
                if ($separatorClosing === false) {
                    $unclosedSeparatorOffset = $index;

                    return null;
                }

                $index = $separatorClosing;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $quoteOffset = $inQuote ? $index : null;
                continue;
            }

            if (!$inQuote && $char === '}') {
                return $index;
            }
        }

        if ($inQuote && $quoteOffset !== null) {
            $unclosedQuoteOffset = $quoteOffset;
        }

        return null;
    }

    private function findDollarDirectiveClosing(string $template, int $start, ?int &$unclosedSeparatorOffset = null, ?int &$unclosedQuoteOffset = null): ?int
    {
        $unclosedSeparatorOffset = null;
        $unclosedQuoteOffset = null;
        $inQuote = false;
        $escape = false;
        $quoteOffset = null;
        $length = strlen($template);

        for ($index = $start; $index < $length; $index++) {
            $char = $template[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $quoteOffset = $inQuote ? $index : null;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $separatorClosing = strpos($template, ']', $index + 1);
                if ($separatorClosing === false) {
                    $unclosedSeparatorOffset = $index;

                    return null;
                }

                $index = $separatorClosing;
                continue;
            }

            if (!$inQuote && $char === '$') {
                return $index;
            }
        }

        if ($inQuote && $quoteOffset !== null) {
            $unclosedQuoteOffset = $quoteOffset;
        }

        return null;
    }

    /**
     * @return array{line:int, column:int}
     */
    private function sourceLocation(string $source, int $offset): array
    {
        $offset = max(0, min($offset, strlen($source)));
        $line = 1;
        $lineStart = 0;

        for ($index = 0; $index < $offset; $index++) {
            $char = $source[$index];
            if ($char === "\r") {
                $line++;
                if (($source[$index + 1] ?? '') === "\n" && $index + 1 < $offset) {
                    $index++;
                }
                $lineStart = $index + 1;
                continue;
            }

            if ($char === "\n") {
                $line++;
                $lineStart = $index + 1;
            }
        }

        return [
            'line' => $line,
            'column' => 1 + $this->sourceCharacterCount(substr($source, $lineStart, $offset - $lineStart)),
        ];
    }

    private function sourceCharacterCount(string $source): int
    {
        $count = preg_match_all('/./us', $source, $matches);
        if ($count !== false) {
            return $count;
        }

        return strlen($source);
    }

    private function throwTemplateError(string $message, string $source, int $offset, string $sourceName): never
    {
        $location = $this->sourceLocation($source, $offset);

        throw new \UnexpectedValueException(
            $message . ' at ' . $sourceName . ':' . $location['line'] . ':' . $location['column'],
        );
    }

    /**
     * @param array<string, mixed> $token
     */
    private function withTokenLocation(\UnexpectedValueException $exception, array $token): \UnexpectedValueException
    {
        if ($this->messageHasTemplateLocation($exception->getMessage())) {
            return $exception;
        }

        if ($exception instanceof DocTemplateRelativeLocationException) {
            $relativeLocation = $this->sourceLocation((string) $token['value'], $exception->relativeOffset());
            $line = (int) ($token['directiveLine'] ?? $token['line']) + $relativeLocation['line'] - 1;
            $column = $relativeLocation['line'] === 1
                ? (int) ($token['directiveColumn'] ?? $token['column']) + $relativeLocation['column'] - 1
                : $relativeLocation['column'];

            return new \UnexpectedValueException(
                $exception->getMessage() . ' at ' . $token['source'] . ':' . $line . ':' . $column,
                0,
                $exception,
            );
        }

        return new \UnexpectedValueException(
            $exception->getMessage() . ' at ' . $token['source'] . ':' . $token['line'] . ':' . $token['column'],
            0,
            $exception,
        );
    }

    /**
     * @param array<string, mixed> $token
     */
    private function withPartialIncludeLocation(\UnexpectedValueException $exception, array $token): \UnexpectedValueException
    {
        if (!$this->messageHasTemplateLocation($exception->getMessage())) {
            return $exception;
        }

        return new \UnexpectedValueException(
            $exception->getMessage() . ' included from ' . $token['source'] . ':' . $token['line'] . ':' . $token['column'],
            0,
            $exception,
        );
    }

    private function messageHasTemplateLocation(string $message): bool
    {
        return preg_match('/ at [^\\r\\n]+:\\d+:\\d+(?: included from [^\\r\\n]+:\\d+:\\d+)*$/', $message) === 1;
    }

    /**
     * @return array{start:int, length:int, value:string}|null
     */
    private function findCommentLineEnding(string $template, int $start): ?array
    {
        $length = strlen($template);
        for ($index = $start; $index < $length; $index++) {
            $char = $template[$index];
            if ($char === "\n") {
                return ['start' => $index, 'length' => 1, 'value' => "\n"];
            }

            if ($char === "\r") {
                if (($template[$index + 1] ?? '') === "\n") {
                    return ['start' => $index, 'length' => 2, 'value' => "\r\n"];
                }

                return ['start' => $index, 'length' => 1, 'value' => "\r"];
            }
        }

        return null;
    }

    private function commentStartsInFirstColumn(string $buffer): bool
    {
        $lineStart = $this->lastLineEndingByteOffset($buffer);
        $linePrefix = $lineStart === null ? $buffer : substr($buffer, $lineStart + 1);

        return $linePrefix === '';
    }

    private function dropStandaloneCommentLinePrefix(string $buffer): string
    {
        $lineStart = $this->lastLineEndingByteOffset($buffer);

        return $lineStart === null ? '' : substr($buffer, 0, $lineStart + 1);
    }

    private function dropStandaloneControlLinePrefix(string $buffer): string
    {
        $lineStart = $this->lastLineEndingByteOffset($buffer);
        $linePrefix = $lineStart === null ? $buffer : substr($buffer, $lineStart + 1);
        if (trim($linePrefix, " \t") !== '') {
            return $buffer;
        }

        return $lineStart === null ? '' : substr($buffer, 0, $lineStart + 1);
    }

    private function lastLineEndingByteOffset(string $buffer): ?int
    {
        $lastLf = strrpos($buffer, "\n");
        $lastCr = strrpos($buffer, "\r");
        if ($lastLf === false && $lastCr === false) {
            return null;
        }

        if ($lastLf === false) {
            return $lastCr;
        }

        if ($lastCr === false) {
            return $lastLf;
        }

        return max($lastLf, $lastCr);
    }

    private function firstLineEndingByteOffset(string $buffer): ?int
    {
        $firstLf = strpos($buffer, "\n");
        $firstCr = strpos($buffer, "\r");
        if ($firstLf === false && $firstCr === false) {
            return null;
        }

        if ($firstLf === false) {
            return $firstCr;
        }

        if ($firstCr === false) {
            return $firstLf;
        }

        return min($firstLf, $firstCr);
    }

    /**
     * @param list<array<string, mixed>> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function renderRange(array $tokens, int $start, int $end, array $context, array $partials, array $partialSources, array $partialStack, bool $preserveBreakableSpaces): string
    {
        $output = '';
        $explicitNestColumn = null;
        $explicitNestSourceColumn = null;
        $pendingBlockStart = null;
        $pendingBlockLines = null;
        $pendingBlockBlankLine = null;

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] === 'text') {
                $text = $token['value'];
                if (($token['breakable'] ?? false) === true) {
                    $text = $this->normalizeBreakableSpaces(
                        $text,
                        $preserveBreakableSpaces ? self::BREAKABLE_SPACE_MARKER : ' ',
                    );
                }

                $this->appendRenderedChunk($output, $text, $explicitNestColumn, $explicitNestSourceColumn, true, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
                continue;
            }

            $directive = $token['value'];
            $directiveBreakableSpaces = (bool) ($token['breakable'] ?? false);
            if ($directive === '~') {
                continue;
            }

            if ($directive === '^') {
                $explicitNestColumn = $this->currentColumn($output);
                $explicitNestSourceColumn = (int) $token['column'];
                continue;
            }

            if ($explicitNestColumn !== null && $explicitNestSourceColumn !== null && $this->directiveStartsBeforeExplicitNestColumn($token, $explicitNestSourceColumn, $output)) {
                $explicitNestColumn = null;
                $explicitNestSourceColumn = null;
            }

            $ifVariable = $this->controlVariable($directive, 'if');
            if ($ifVariable !== null) {
                try {
                    [$rendered, $nextIndex, $skipFollowingLineEnding] = $this->renderIf($tokens, $index + 1, $end, $ifVariable, $context, $partials, $partialSources, $partialStack, $preserveBreakableSpaces);
                } catch (\UnexpectedValueException $exception) {
                    throw $this->withTokenLocation($exception, $token);
                }
                $this->appendRenderedChunk($output, $rendered, $explicitNestColumn, $explicitNestSourceColumn, false, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
                if ($skipFollowingLineEnding) {
                    $this->dropLeadingLineEndingAt($tokens, $nextIndex, $end);
                }
                $index = $nextIndex - 1;
                continue;
            }

            $forVariable = $this->controlVariable($directive, 'for');
            if ($forVariable !== null) {
                try {
                    [$rendered, $nextIndex, $skipFollowingLineEnding] = $this->renderFor($tokens, $index + 1, $end, $forVariable, $context, $partials, $partialSources, $partialStack, $preserveBreakableSpaces);
                } catch (\UnexpectedValueException $exception) {
                    throw $this->withTokenLocation($exception, $token);
                }
                $this->appendRenderedChunk($output, $rendered, $explicitNestColumn, $explicitNestSourceColumn, false, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
                if ($skipFollowingLineEnding) {
                    $this->dropLeadingLineEndingAt($tokens, $nextIndex, $end);
                }
                $index = $nextIndex - 1;
                continue;
            }

            if (in_array($directive, ['elseif', 'else', 'endif', 'sep', 'endfor'], true) || $this->controlVariable($directive, 'elseif') !== null) {
                throw $this->withTokenLocation(
                    new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}"),
                    $token,
                );
            }

            try {
                $isBarePartial = $this->parsePartialDirective($directive) !== null;
                $rendered = $this->renderDirective(
                    $directive,
                    $context,
                    $partials,
                    $partialSources,
                    $partialStack,
                    $preserveBreakableSpaces,
                    $directiveBreakableSpaces,
                );
            } catch (\UnexpectedValueException $exception) {
                throw $this->withTokenLocation($exception, $token);
            }
            if ($explicitNestColumn === null) {
                $autoNestPrefix = $this->automaticNestPrefix($tokens, $index, $end, $output);
                if ($autoNestPrefix !== null) {
                    if ($isBarePartial && $rendered === '') {
                        $this->dropStandaloneDirectiveLine($tokens, $index + 1, $end, $output, $autoNestPrefix);
                        continue;
                    }

                    if ($isBarePartial && $rendered === '(loop)') {
                        $this->dropLeadingLineEndingAt($tokens, $index + 1, $end);
                    }

                    $rendered = $this->nestMultiline($rendered, $autoNestPrefix);
                    if ($isBarePartial && $this->endsWithLineEnding($rendered)) {
                        $this->dropLeadingLineEndingAt($tokens, $index + 1, $end);
                    }
                }
            }

            $this->appendRenderedChunk($output, $rendered, $explicitNestColumn, $explicitNestSourceColumn, false, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
        }

        $this->finalizePendingBlockOutput($output, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);

        return $output;
    }

    /**
     * @param list<array<string, mixed>> $tokens
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function validateTokenRange(array $tokens, int $start, int $end, array $partials, array $partialSources, array $partialStack): void
    {
        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] !== 'directive') {
                continue;
            }

            $directive = $token['value'];
            $directiveBreakableSpaces = (bool) ($token['breakable'] ?? false);
            if ($directive === '~' || $directive === '^') {
                continue;
            }

            $ifVariable = $this->controlVariable($directive, 'if');
            if ($ifVariable !== null) {
                try {
                    [$branches, $nextIndex] = $this->collectIfBranches($tokens, $index + 1, $end, $ifVariable);
                    foreach ($branches as $branch) {
                        if ($branch['variable'] !== null) {
                            $this->parseVariableExpression($branch['variable']);
                        }

                        $this->validateTokenRange($tokens, $branch['start'], $branch['end'], $partials, $partialSources, $partialStack);
                    }
                } catch (\UnexpectedValueException $exception) {
                    throw $this->withTokenLocation($exception, $token);
                }

                $index = $nextIndex - 1;
                continue;
            }

            $forVariable = $this->controlVariable($directive, 'for');
            if ($forVariable !== null) {
                try {
                    $this->parseVariableExpression($forVariable);
                    [$bodyStart, $bodyEnd, $separatorStart, $separatorEnd, $nextIndex] = $this->collectForSlices($tokens, $index + 1, $end);
                    $this->validateTokenRange($tokens, $bodyStart, $bodyEnd, $partials, $partialSources, $partialStack);
                    if ($separatorStart !== null) {
                        $this->validateTokenRange($tokens, $separatorStart, (int) $separatorEnd, $partials, $partialSources, $partialStack);
                    }
                } catch (\UnexpectedValueException $exception) {
                    throw $this->withTokenLocation($exception, $token);
                }

                $index = $nextIndex - 1;
                continue;
            }

            if (in_array($directive, ['elseif', 'else', 'endif', 'sep', 'endfor'], true) || $this->controlVariable($directive, 'elseif') !== null) {
                throw $this->withTokenLocation(
                    new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}"),
                    $token,
                );
            }

            try {
                $partial = $this->parsePartialDirective($directive);
                if ($partial !== null) {
                    try {
                        $this->validatePartial(
                            $partial['name'],
                            $partials,
                            $partialSources,
                            $partialStack,
                            $directiveBreakableSpaces,
                        );
                    } catch (\UnexpectedValueException $exception) {
                        throw $this->withPartialIncludeLocation($exception, $token);
                    }
                    continue;
                }

                $appliedPartial = $this->parseAppliedPartialDirective($directive);
                if ($appliedPartial !== null) {
                    try {
                        $this->validatePartial(
                            $appliedPartial['partial']['name'],
                            $partials,
                            $partialSources,
                            $partialStack,
                            $directiveBreakableSpaces,
                        );
                    } catch (\UnexpectedValueException $exception) {
                        throw $this->withPartialIncludeLocation($exception, $token);
                    }
                    continue;
                }

                $expression = $this->parseVariableExpression($directive);
                if (in_array($expression['name'], ['if', 'else', 'elseif', 'endif', 'for', 'sep', 'endfor'], true)) {
                    throw new \UnexpectedValueException("Reserved doctemplate keyword {$expression['name']} cannot be rendered as a variable");
                }
            } catch (\UnexpectedValueException $exception) {
                throw $this->withTokenLocation($exception, $token);
            }
        }
    }

    /**
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function validatePartial(
        string $name,
        array $partials,
        array $partialSources,
        array $partialStack,
        bool $initialBreakableSpaces = false,
    ): void
    {
        if (count($partialStack) >= self::MAX_PARTIAL_DEPTH) {
            return;
        }

        if (array_key_exists($name, $partials) && is_string($partials[$name])) {
            $source = $partials[$name];
            $sourceName = $partialSources[$name] ?? $name;
        } else {
            $fallback = $this->defaultPartialFallbackFor($name, $partialSources);
            if ($fallback === null) {
                throw new \UnexpectedValueException("Missing doctemplate partial {$name}");
            }

            [$source, $sourceName] = $fallback;
        }

        $tokens = $this->tokenize($source, $sourceName, $initialBreakableSpaces);
        $this->validateTokenRange($tokens, 0, count($tokens), $partials, $partialSources, [...$partialStack, $name]);
    }

    /**
     * @param list<array<string, mixed>> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     * @return array{0:string, 1:int, 2:bool}
     */
    private function renderIf(array $tokens, int $start, int $end, string $firstVariable, array $context, array $partials, array $partialSources, array $partialStack, bool $preserveBreakableSpaces): array
    {
        [$branches, $nextIndex, $blockMultiline] = $this->collectIfBranches($tokens, $start, $end, $firstVariable);

        foreach ($branches as $branch) {
            if ($branch['variable'] === null || $this->isTruthy($this->resolveExpression($branch['variable'], $context)['value'])) {
                return [
                    $this->renderRangeDroppingLeadingLineEnding(
                        $tokens,
                        $branch['start'],
                        $branch['end'],
                        $context,
                        $partials,
                        $partialSources,
                        $partialStack,
                        $branch['trimLeadingLineEnding'],
                        $preserveBreakableSpaces,
                    ),
                    $nextIndex,
                    $blockMultiline,
                ];
            }
        }

        return ['', $nextIndex, $blockMultiline];
    }

    /**
     * @param list<array<string, mixed>> $tokens
     * @return array{0:list<array{variable:?string, start:int, end:int, trimLeadingLineEnding:bool}>, 1:int, 2:bool}
     */
    private function collectIfBranches(array $tokens, int $start, int $end, string $firstVariable): array
    {
        $branches = [];
        $branchVariable = $firstVariable;
        $branchStart = $start;
        $blockMultiline = $this->tokenStartsWithLineEnding($tokens, $start, $end);
        $branchTrimLeadingLineEnding = $blockMultiline;
        $currentControlMultiline = $blockMultiline;
        $depth = 0;
        $seenElse = false;

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] !== 'directive') {
                continue;
            }

            $directive = $token['value'];
            if ($this->startsControlBlock($directive)) {
                $depth++;
                continue;
            }

            if ($this->endsControlBlock($directive)) {
                if ($depth > 0) {
                    $depth--;
                    continue;
                }

                if ($directive === 'endif') {
                    $branches[] = [
                        'variable' => $branchVariable,
                        'start' => $branchStart,
                        'end' => $index,
                        'trimLeadingLineEnding' => $branchTrimLeadingLineEnding,
                    ];

                    return [$branches, $index + 1, $blockMultiline];
                }

                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            if ($depth !== 0) {
                continue;
            }

            $elseifVariable = $this->controlVariable($directive, 'elseif');
            if ($elseifVariable !== null) {
                if ($seenElse) {
                    throw new \UnexpectedValueException(
                        'Unexpected doctemplate conditional branch elseif after else at '
                        . $token['source'] . ':' . $token['line'] . ':' . $token['column'],
                    );
                }

                $branches[] = [
                    'variable' => $branchVariable,
                    'start' => $branchStart,
                    'end' => $index,
                    'trimLeadingLineEnding' => $branchTrimLeadingLineEnding,
                ];
                $branchVariable = $elseifVariable;
                $branchStart = $index + 1;
                $branchTrimLeadingLineEnding = $this->tokenStartsWithLineEnding($tokens, $branchStart, $end);
                $currentControlMultiline = $branchTrimLeadingLineEnding;
                continue;
            }

            if ($directive === 'else') {
                if ($seenElse) {
                    throw new \UnexpectedValueException(
                        'Unexpected doctemplate conditional branch else after else at '
                        . $token['source'] . ':' . $token['line'] . ':' . $token['column'],
                    );
                }

                $branches[] = [
                    'variable' => $branchVariable,
                    'start' => $branchStart,
                    'end' => $index,
                    'trimLeadingLineEnding' => $branchTrimLeadingLineEnding,
                ];
                $branchVariable = null;
                $branchStart = $index + 1;
                $branchTrimLeadingLineEnding = $currentControlMultiline;
                $seenElse = true;
            }
        }

        throw new \UnexpectedValueException('Unclosed doctemplate if block');
    }

    /**
     * @param list<array<string, mixed>> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     * @return array{0:string, 1:int, 2:bool}
     */
    private function renderFor(array $tokens, int $start, int $end, string $variable, array $context, array $partials, array $partialSources, array $partialStack, bool $preserveBreakableSpaces): array
    {
        [$bodyStart, $bodyEnd, $separatorStart, $separatorEnd, $nextIndex, $blockMultiline] = $this->collectForSlices($tokens, $start, $end);
        $expression = $this->parseVariableExpression($variable);
        $baseExists = $this->resolve($expression['name'], $context)['exists'];
        $resolved = $this->resolveParsedExpression($expression, $context);
        $iterations = $this->loopIterations($resolved['exists'], $resolved['value']);
        $rendered = [];

        foreach ($iterations as $item) {
            $iterationContext = $this->contextForLoopIteration($context, $expression['name'], $item, $baseExists);
            $rendered[] = $this->renderRangeDroppingLeadingLineEnding(
                $tokens,
                $bodyStart,
                $bodyEnd,
                $iterationContext,
                $partials,
                $partialSources,
                $partialStack,
                $blockMultiline,
                $preserveBreakableSpaces,
            );
        }

        if ($rendered === []) {
            return ['', $nextIndex, $blockMultiline];
        }

        $separator = $separatorStart === null
            ? ''
            : $this->renderRangeDroppingLeadingLineEnding(
                $tokens,
                $separatorStart,
                (int) $separatorEnd,
                $context,
                $partials,
                $partialSources,
                $partialStack,
                $blockMultiline,
                $preserveBreakableSpaces,
            );

        return [implode($separator, $rendered), $nextIndex, $blockMultiline];
    }

    /**
     * @param list<array<string, mixed>> $tokens
     * @return array{0:int, 1:int, 2:?int, 3:?int, 4:int, 5:bool}
     */
    private function collectForSlices(array $tokens, int $start, int $end): array
    {
        $depth = 0;
        $separatorStart = null;
        $separatorEnd = null;
        $blockMultiline = $this->tokenStartsWithLineEnding($tokens, $start, $end);

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] !== 'directive') {
                continue;
            }

            $directive = $token['value'];
            if ($this->startsControlBlock($directive)) {
                $depth++;
                continue;
            }

            if ($this->endsControlBlock($directive)) {
                if ($depth > 0) {
                    $depth--;
                    continue;
                }

                if ($directive === 'endfor') {
                    $bodyEnd = $separatorStart === null ? $index : $separatorStart - 1;
                    if ($separatorStart !== null) {
                        $separatorEnd = $index;
                    }

                    return [$start, $bodyEnd, $separatorStart, $separatorEnd, $index + 1, $blockMultiline];
                }

                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            if ($depth === 0 && $directive === 'sep' && $separatorStart === null) {
                $separatorStart = $index + 1;
            }
        }

        throw new \UnexpectedValueException('Unclosed doctemplate for block');
    }

    /**
     * @param list<array<string, mixed>> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function renderRangeDroppingLeadingLineEnding(
        array $tokens,
        int $start,
        int $end,
        array $context,
        array $partials,
        array $partialSources,
        array $partialStack,
        bool $dropLeadingLineEnding,
        bool $preserveBreakableSpaces,
    ): string
    {
        if ($dropLeadingLineEnding) {
            $this->dropLeadingLineEndingAt($tokens, $start, $end);
        }

        return $this->renderRange($tokens, $start, $end, $context, $partials, $partialSources, $partialStack, $preserveBreakableSpaces);
    }

    /**
     * @param list<array<string, mixed>> $tokens
     */
    private function tokenStartsWithLineEnding(array $tokens, int $index, int $end): bool
    {
        if ($index >= $end || !isset($tokens[$index]) || $tokens[$index]['type'] !== 'text') {
            return false;
        }

        return $this->leadingLineEndingLength($tokens[$index]['value']) !== null;
    }

    /**
     * @param list<array<string, mixed>> $tokens
     */
    private function dropLeadingLineEndingAt(array &$tokens, int $index, int $end): void
    {
        if ($index >= $end || !isset($tokens[$index]) || $tokens[$index]['type'] !== 'text') {
            return;
        }

        $length = $this->leadingLineEndingLength($tokens[$index]['value']);
        if ($length === null) {
            return;
        }

        $tokens[$index]['value'] = substr($tokens[$index]['value'], $length);
    }

    /**
     * @param list<array<string, mixed>> $tokens
     */
    private function dropStandaloneDirectiveLine(array &$tokens, int $index, int $end, string &$output, string $prefix): void
    {
        if ($prefix !== '' && str_ends_with($output, $prefix)) {
            $output = substr($output, 0, -strlen($prefix));
        }

        if ($index >= $end || !isset($tokens[$index]) || $tokens[$index]['type'] !== 'text') {
            return;
        }

        if (preg_match('/^[ \t]*(?:\r\n|\n|\r)/', $tokens[$index]['value'], $matches) !== 1) {
            return;
        }

        $tokens[$index]['value'] = substr($tokens[$index]['value'], strlen($matches[0]));
    }

    private function leadingLineEndingLength(string $value): ?int
    {
        if (str_starts_with($value, "\r\n")) {
            return 2;
        }

        if (str_starts_with($value, "\n") || str_starts_with($value, "\r")) {
            return 1;
        }

        return null;
    }

    private function endsWithLineEnding(string $value): bool
    {
        return str_ends_with($value, "\n") || str_ends_with($value, "\r");
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function renderDirective(
        string $directive,
        array $context,
        array $partials,
        array $partialSources,
        array $partialStack,
        bool $preserveBreakableSpaces,
        bool $initialBreakableSpaces = false,
    ): string
    {
        $partial = $this->parsePartialDirective($directive);
        if ($partial !== null) {
            return $this->renderPartialDirective(
                $partial,
                $context,
                $partials,
                $partialSources,
                $partialStack,
                $preserveBreakableSpaces,
                $initialBreakableSpaces,
            );
        }

        $appliedPartial = $this->parseAppliedPartialDirective($directive);
        if ($appliedPartial !== null) {
            return $this->renderAppliedPartialDirective(
                $appliedPartial,
                $context,
                $partials,
                $partialSources,
                $partialStack,
                $preserveBreakableSpaces,
                $initialBreakableSpaces,
            );
        }

        return $this->renderVariableDirective($directive, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderVariableDirective(string $directive, array $context): string
    {
        $expression = $this->parseVariableExpression($directive);
        $name = $expression['name'];
        if (in_array($name, ['if', 'else', 'elseif', 'endif', 'for', 'sep', 'endfor'], true)) {
            throw new \UnexpectedValueException("Reserved doctemplate keyword {$name} cannot be rendered as a variable");
        }

        $resolved = $this->resolveParsedExpression($expression, $context);
        if (!$resolved['exists']) {
            return '';
        }

        return $this->renderValue($resolved['value'], $expression['separator']);
    }

    /**
     * @param array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>} $partial
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function renderPartialDirective(
        array $partial,
        array $context,
        array $partials,
        array $partialSources,
        array $partialStack,
        bool $preserveBreakableSpaces,
        bool $initialBreakableSpaces,
    ): string
    {
        $value = $this->renderPartial(
            $partial['name'],
            $context,
            $partials,
            $partialSources,
            $partialStack,
            $preserveBreakableSpaces,
            $initialBreakableSpaces,
        );
        foreach ($partial['pipes'] as $pipe) {
            $value = $this->applyPipe($pipe, $value);
        }

        return $this->renderPartialValue($value, $partial['separator']);
    }

    /**
     * @param array{variable:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}, partial:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}} $appliedPartial
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function renderAppliedPartialDirective(
        array $appliedPartial,
        array $context,
        array $partials,
        array $partialSources,
        array $partialStack,
        bool $preserveBreakableSpaces,
        bool $initialBreakableSpaces,
    ): string
    {
        $resolved = $this->resolveParsedExpression($appliedPartial['variable'], $context);
        $iterations = $this->loopIterations($resolved['exists'], $resolved['value']);
        if ($iterations === []) {
            return '';
        }

        $baseExists = $this->resolve($appliedPartial['variable']['name'], $context)['exists'];
        $rendered = [];
        foreach ($iterations as $item) {
            $iterationContext = $this->contextForLoopIteration($context, $appliedPartial['variable']['name'], $item, $baseExists);
            $value = $this->renderPartial(
                $appliedPartial['partial']['name'],
                $iterationContext,
                $partials,
                $partialSources,
                $partialStack,
                $preserveBreakableSpaces,
                $initialBreakableSpaces,
            );
            foreach ($appliedPartial['partial']['pipes'] as $pipe) {
                $value = $this->applyPipe($pipe, $value);
            }

            $rendered[] = $this->renderPartialValue($value, null);
        }

        return implode($appliedPartial['partial']['separator'] ?? '', $rendered);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param array<string, string> $partialSources
     * @param list<string> $partialStack
     */
    private function renderPartial(
        string $name,
        array $context,
        array $partials,
        array $partialSources,
        array $partialStack,
        bool $preserveBreakableSpaces,
        bool $initialBreakableSpaces = false,
    ): string
    {
        if (count($partialStack) >= self::MAX_PARTIAL_DEPTH) {
            return '(loop)';
        }

        if (!array_key_exists($name, $partials) || !is_string($partials[$name])) {
            $fallback = $this->defaultPartialFallbackFor($name, $partialSources);
            if ($fallback === null) {
                throw new \UnexpectedValueException("Missing doctemplate partial {$name}");
            }

            [$source, $sourceName] = $fallback;
            $rendered = $this->renderTemplate(
                $source,
                $context,
                $partials,
                $partialSources,
                [...$partialStack, $name],
                $preserveBreakableSpaces,
                $sourceName,
                $initialBreakableSpaces,
            );

            return $this->stripIncludedPartialFinalNewline($rendered);
        }

        $rendered = $this->renderTemplate(
            $partials[$name],
            $context,
            $partials,
            $partialSources,
            [...$partialStack, $name],
            $preserveBreakableSpaces,
            $partialSources[$name] ?? $name,
            $initialBreakableSpaces,
        );

        return $this->stripIncludedPartialFinalNewline($rendered);
    }

    /**
     * @param array<string, string> $partialSources
     * @return array{0:string, 1:string}|null
     */
    private function defaultPartialFallbackFor(string $name, array $partialSources): ?array
    {
        if (!array_key_exists(self::DEFAULT_PARTIAL_FALLBACK_SENTINEL, $partialSources)) {
            return null;
        }

        $basename = $this->templateResourceBasename(str_replace('\\', '/', $name));
        $source = $this->defaultTemplateResourceForBasename($basename);
        if ($source === null) {
            return null;
        }

        return [$source, 'templates/' . $basename];
    }

    private function stripIncludedPartialFinalNewline(string $value): string
    {
        return $this->stripSingleFinalNewline($value);
    }

    /**
     * @return ?string
     */
    private function controlVariable(string $directive, string $name): ?string
    {
        if (!preg_match('/^' . preg_quote($name, '/') . '\\((.+)\\)$/s', $directive, $matches)) {
            return null;
        }

        $expression = trim($matches[1], " \t");

        return $expression === '' ? null : $expression;
    }

    private function startsControlBlock(string $directive): bool
    {
        return $this->controlVariable($directive, 'if') !== null || $this->controlVariable($directive, 'for') !== null;
    }

    private function endsControlBlock(string $directive): bool
    {
        return $directive === 'endif' || $directive === 'endfor';
    }

    private function isStandaloneControlDirective(string $directive): bool
    {
        return $this->startsControlBlock($directive)
            || $this->controlVariable($directive, 'elseif') !== null
            || in_array($directive, ['else', 'endif', 'sep', 'endfor'], true);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolve(string $path, array $context): array
    {
        $segments = explode('.', $path);
        $value = $context;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            return ['exists' => false, 'value' => null];
        }

        return ['exists' => true, 'value' => $value];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolveExpression(string $expression, array $context): array
    {
        return $this->resolveParsedExpression($this->parseVariableExpression($expression), $context);
    }

    /**
     * @param array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>} $expression
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolveParsedExpression(array $expression, array $context): array
    {
        $resolved = $this->resolve($expression['name'], $context);
        if (!$resolved['exists'] && $expression['pipes'] === []) {
            return $resolved;
        }

        $value = $resolved['exists'] ? $resolved['value'] : null;
        foreach ($expression['pipes'] as $pipe) {
            $value = $this->applyPipe($pipe, $value);
        }

        return ['exists' => $resolved['exists'] || $expression['pipes'] !== [], 'value' => $value];
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>>|null
     */
    private function parsePartialDirective(string $expression): ?array
    {
        $partial = $this->parsePartialCallExpression($expression);
        if ($partial === null) {
            return null;
        }

        return $partial;
    }

    /**
     * @return array{variable:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}, partial:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}}|null
     */
    private function parseAppliedPartialDirective(string $expression): ?array
    {
        foreach ($this->findAppliedPartialColons($expression) as $colon) {
            $variableSource = trim(substr($expression, 0, $colon), " \t");
            $partialSource = trim(substr($expression, $colon + 1), " \t");
            if ($variableSource === '' || $partialSource === '') {
                continue;
            }

            $partial = $this->parsePartialCallExpression($partialSource);
            if ($partial === null) {
                continue;
            }

            try {
                $variable = $this->parseVariableExpression($variableSource);
                if ($variable['separator'] !== null) {
                    throw new DocTemplateRelativeLocationException(
                        "Doctemplate applied partial separators must follow the partial call in {$expression}",
                        0,
                    );
                }
            } catch (DocTemplateRelativeLocationException $exception) {
                throw $exception;
            } catch (\UnexpectedValueException) {
                continue;
            }

            return [
                'variable' => $variable,
                'partial' => $partial,
            ];
        }

        return null;
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>>|null
     */
    private function parsePartialCallExpression(string $expression): ?array
    {
        if (!preg_match('/^([\\p{L}\\p{N}_.\\/\\\\-]+)\\(\\)(.*)$/su', $expression, $matches)) {
            return null;
        }

        $suffix = $matches[2] ?? '';
        $separator = null;
        if ($suffix !== '' && $suffix[0] === '[') {
            $separatorEnd = strpos($suffix, ']');
            if ($separatorEnd === false) {
                return null;
            }

            $separator = substr($suffix, 1, $separatorEnd - 1);
            $suffix = substr($suffix, $separatorEnd + 1);
            if ($suffix !== '' && $suffix[0] !== '/') {
                throw new \UnexpectedValueException("Malformed doctemplate separator in {$expression}");
            }
        }

        if ($suffix !== '' && $suffix[0] !== '/') {
            return null;
        }

        $firstPipeOffset = $suffix === '' ? strlen($expression) : strlen($expression) - strlen($suffix);
        $pipeSource = $suffix === '' ? '' : substr($suffix, 1);
        [$pipeSource, $trailingSeparator] = $this->extractTrailingSeparator($pipeSource);
        if ($trailingSeparator !== null) {
            if ($separator !== null) {
                throw new \UnexpectedValueException('Conflicting doctemplate partial separators');
            }

            $separator = $trailingSeparator;
        }

        return [
            'name' => $this->normalizePartialName($matches[1]),
            'separator' => $separator,
            'pipes' => $this->parsePipeSuffix($pipeSource, $expression, $firstPipeOffset),
        ];
    }

    /**
     * @return list<array{name:string, args:list<int|string>}>
     */
    private function parsePipeSuffix(string $pipeSource, string $expression, int $firstPipeOffset): array
    {
        if ($pipeSource === '') {
            return [];
        }

        return $this->parsePipeSpecs($this->splitPipeExpression($pipeSource), $expression, $firstPipeOffset);
    }

    /**
     * @return list<int>
     */
    private function findAppliedPartialColons(string $expression): array
    {
        $colons = [];
        $bracketDepth = 0;
        $inQuote = false;
        $escape = false;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $char = $expression[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $bracketDepth++;
                continue;
            }

            if (!$inQuote && $char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                continue;
            }

            if (!$inQuote && $char === ':' && $bracketDepth === 0) {
                $colons[] = $index;
            }
        }

        return $colons;
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}
     */
    private function parseVariableExpression(string $expression): array
    {
        [$expressionWithoutTrailingSeparator, $trailingSeparator] = $this->extractTrailingSeparator($expression);
        $parts = $this->splitPipeExpression($expressionWithoutTrailingSeparator);
        $base = array_shift($parts);
        if ($base === null || !preg_match('/^(.+?)(?:\\[(.*)\\])?$/s', $base, $matches)) {
            throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
        }

        $name = $matches[1];
        if (array_key_exists(2, $matches) && $parts !== []) {
            throw new \UnexpectedValueException("Doctemplate variable separators must follow pipe suffixes in {$expression}");
        }

        $separator = $trailingSeparator ?? (array_key_exists(2, $matches) ? $matches[2] : null);
        $this->validateSeparatorPayload($separator, $expression);
        $this->validateVariableName($name, $expression);

        return [
            'name' => $name,
            'separator' => $separator,
            'pipes' => $this->parsePipeSpecs($parts, $expression, strlen($base)),
        ];
    }

    private function validateSeparatorPayload(?string $separator, string $expression): void
    {
        if ($separator !== null && str_contains($separator, ']')) {
            throw new \UnexpectedValueException("Malformed doctemplate separator in {$expression}");
        }
    }

    /**
     * @return array{0:string, 1:?string}
     */
    private function extractTrailingSeparator(string $expression): array
    {
        $source = rtrim($expression, " \t");
        if ($source === '' || !str_ends_with($source, ']')) {
            return [$expression, null];
        }

        $inQuote = false;
        $escape = false;
        $separatorStart = null;
        $length = strlen($source);
        for ($index = 0; $index < $length; $index++) {
            $char = $source[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $separatorStart = $index;
                break;
            }
        }

        if ($separatorStart === null) {
            return [$expression, null];
        }

        $separator = substr($source, $separatorStart + 1, $length - $separatorStart - 2);
        if (str_contains($separator, ']')) {
            return [$expression, null];
        }

        return [rtrim(substr($source, 0, $separatorStart), " \t"), $separator];
    }

    private function validateVariableName(string $name, string $expression): void
    {
        $segments = explode('.', $name);
        if ($segments === [] || in_array('', $segments, true)) {
            throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
        }

        foreach ($segments as $offset => $segment) {
            if ($offset === 0) {
                if ($segment === 'it') {
                    continue;
                }

                if (!$this->isVariableIdentifierPart($segment, true, false)) {
                    throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
                }

                continue;
            }

            if (!$this->isVariableIdentifierPart($segment, true, true)) {
                throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
            }
        }
    }

    private function isVariableIdentifierPart(string $part, bool $allowLeadingDigit = false, bool $allowReserved = false): bool
    {
        if ($part === '') {
            return false;
        }

        if (!$allowReserved && in_array($part, ['if', 'else', 'endif', 'elseif', 'for', 'endfor', 'sep', 'it'], true)) {
            return false;
        }

        $pattern = $allowLeadingDigit
            ? '/^[\\p{L}\\p{N}][\\p{L}\\p{N}_-]*(?::[\\p{L}\\p{N}][\\p{L}\\p{N}_-]*)*$/u'
            : '/^\\p{L}[\\p{L}\\p{N}_-]*(?::\\p{L}[\\p{L}\\p{N}_-]*)*$/u';

        return preg_match($pattern, $part) === 1;
    }

    /**
     * @param list<string> $pipeSpecs
     * @return list<array{name:string, args:list<int|string>}>
     */
    private function parsePipeSpecs(array $pipeSpecs, string $expression, int $firstPipeOffset): array
    {
        $pipes = [];
        $pipeOffset = $firstPipeOffset;
        foreach ($pipeSpecs as $pipeSpec) {
            $rawPipeSpec = $pipeSpec;
            $pipeNameOffset = $pipeOffset + 1 + strspn($rawPipeSpec, " \t");
            $pipeSpec = trim($pipeSpec, " \t");
            if ($pipeSpec === '') {
                throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
            }

            if (!preg_match('/^([A-Za-z][A-Za-z0-9_-]*)(?:\\s+(.+))?$/s', $pipeSpec, $pipeMatches)) {
                throw new DocTemplateRelativeLocationException("Unsupported doctemplate pipe {$pipeSpec}", $pipeNameOffset);
            }

            $pipeName = $pipeMatches[1];
            if (!$this->isSupportedPipeName($pipeName)) {
                throw new DocTemplateRelativeLocationException("Unsupported doctemplate pipe {$pipeName}", $pipeNameOffset);
            }

            $argumentSource = isset($pipeMatches[2]) ? trim($pipeMatches[2]) : '';
            if (in_array($pipeName, ['left', 'right', 'center'], true)) {
                $pipes[] = [
                    'name' => $pipeName,
                    'args' => $this->parseBlockPipeArguments($pipeName, $argumentSource),
                ];
                $pipeOffset += 1 + strlen($rawPipeSpec);
                continue;
            }

            if ($argumentSource !== '') {
                throw new \UnexpectedValueException("Unsupported parameterized doctemplate pipe {$pipeName}");
            }

            $pipes[] = [
                'name' => $pipeName,
                'args' => [],
            ];
            $pipeOffset += 1 + strlen($rawPipeSpec);
        }

        return $pipes;
    }

    private function isSupportedPipeName(string $pipeName): bool
    {
        return in_array($pipeName, [
            'pairs',
            'uppercase',
            'lowercase',
            'length',
            'reverse',
            'first',
            'last',
            'rest',
            'allbutlast',
            'chomp',
            'nowrap',
            'alpha',
            'roman',
            'left',
            'right',
            'center',
        ], true);
    }

    /**
     * @return list<int|string>
     */
    private function parseBlockPipeArguments(string $pipeName, string $source): array
    {
        if ($source === '') {
            throw new \UnexpectedValueException("Missing integer parameter for doctemplate pipe {$pipeName}");
        }

        if (!preg_match('/^([0-9]+)(.*)$/s', $source, $matches)) {
            throw new \UnexpectedValueException("Expected integer parameter for doctemplate pipe {$pipeName}");
        }

        $width = (int) $matches[1];
        if ($width < 0) {
            throw new \UnexpectedValueException("Expected non-negative integer parameter for doctemplate pipe {$pipeName}");
        }

        $offset = 0;
        $remaining = ltrim($matches[2], " \t\r\n");
        $borders = [];
        while ($remaining !== '') {
            if ($remaining[0] !== '"') {
                throw new \UnexpectedValueException("Expected quoted border parameter for doctemplate pipe {$pipeName}");
            }

            $borders[] = $this->parseQuotedPipeString($remaining, $offset);
            $remaining = ltrim(substr($remaining, $offset), " \t\r\n");
            $offset = 0;
            if (count($borders) > 2) {
                throw new \UnexpectedValueException("Too many border parameters for doctemplate pipe {$pipeName}");
            }
        }

        return [$width, $borders[0] ?? '', $borders[1] ?? ''];
    }

    private function parseQuotedPipeString(string $source, int &$offset): string
    {
        $offset = 1;
        $value = '';
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '"') {
                $offset++;

                return $value;
            }

            if ($char === '\\') {
                $offset++;
                if ($offset >= $length) {
                    throw new \UnexpectedValueException('Unclosed doctemplate pipe quoted string');
                }

                $value .= $source[$offset];
                $offset++;
                continue;
            }

            $value .= $char;
            $offset++;
        }

        throw new \UnexpectedValueException('Unclosed doctemplate pipe quoted string');
    }

    /**
     * @return list<string>
     */
    private function splitPipeExpression(string $expression): array
    {
        $parts = [];
        $buffer = '';
        $bracketDepth = 0;
        $inQuote = false;
        $escape = false;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $char = $expression[$index];
            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $buffer .= $char;
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $bracketDepth++;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === '/' && $bracketDepth === 0) {
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
     * @param array{name:string, args:list<int|string>} $pipe
     */
    private function applyPipe(array $pipe, mixed $value): mixed
    {
        return match ($pipe['name']) {
            'pairs' => $this->pipePairs($value),
            'uppercase' => $this->mapTextualValue($value, fn (string $text): string => $this->uppercase($text)),
            'lowercase' => $this->mapTextualValue($value, fn (string $text): string => $this->lowercase($text)),
            'length' => $this->pipeLength($value),
            'reverse' => $this->pipeReverse($value),
            'first' => is_array($value) && array_is_list($value) && $value !== [] ? $value[0] : $value,
            'last' => is_array($value) && array_is_list($value) && $value !== [] ? $value[array_key_last($value)] : $value,
            'rest' => is_array($value) && array_is_list($value) && $value !== [] ? array_slice($value, 1) : $value,
            'allbutlast' => is_array($value) && array_is_list($value) && $value !== [] ? array_slice($value, 0, -1) : $value,
            'chomp' => $this->pipeChomp($value),
            'nowrap' => $this->pipeNowrap($value),
            'alpha' => $this->mapTextualValue($value, fn (string $text): string => $this->pipeAlphaText($text)),
            'roman' => $this->mapTextualValue($value, fn (string $text): string => $this->pipeRomanText($text)),
            'left', 'right', 'center' => $this->pipeBlock($pipe['name'], $pipe['args'], $value),
            default => throw new \UnexpectedValueException("Unsupported doctemplate pipe {$pipe['name']}"),
        };
    }

    private function mapTextualValue(mixed $value, callable $callback): mixed
    {
        if ($value instanceof DocTemplateBlockOutput) {
            return $value->mapText($callback);
        }

        if (is_array($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->mapTextualValue($item, $callback);
            }

            return $mapped;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return $callback((string) $value);
        }

        return $value;
    }

    private function pipeAlphaText(string $value): string
    {
        if (!preg_match('/^[0-9]+$/', $value)) {
            return $value;
        }

        $number = (int) $value;
        if ($number < 1) {
            return $value;
        }

        return chr(ord('a') + (($number - 1) % 26));
    }

    private function pipeRomanText(string $value): string
    {
        if (!preg_match('/^[0-9]+$/', $value)) {
            return $value;
        }

        $number = (int) $value;
        if ($number === 0) {
            return '';
        }

        if ($number < 1 || $number >= 4000) {
            return $value;
        }

        $roman = '';
        foreach ([
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
        ] as $decimal => $glyph) {
            while ($number >= $decimal) {
                $roman .= $glyph;
                $number -= $decimal;
            }
        }

        return $roman;
    }

    /**
     * @param list<int|string> $args
     */
    private function pipeBlock(string $alignment, array $args, mixed $value): mixed
    {
        if ($value instanceof DocTemplateBlockOutput) {
            $value = $this->blockOutputToString($value);
        }

        if (!is_string($value) && !is_int($value) && !is_float($value) && $value !== null) {
            return $value;
        }

        $width = (int) ($args[0] ?? 0);
        if ($width < 0) {
            throw new \UnexpectedValueException("Missing integer parameter for doctemplate pipe {$alignment}");
        }

        $leftBorder = is_string($args[1] ?? null) ? $args[1] : '';
        $rightBorder = is_string($args[2] ?? null) ? $args[2] : '';
        $lines = preg_split('/\r\n|\n|\r/', $this->replaceBreakableSpaceMarkers($value === null ? '' : (string) $value, ' '));
        if ($lines === false) {
            $lines = [$value === null ? '' : (string) $value];
        }

        $padded = [];
        $blankContentWidth = 0;
        foreach ($lines as $line) {
            $effectiveWidth = $this->effectiveBlockPipeWidth($width, $line);
            $blankContentWidth = max($blankContentWidth, $effectiveWidth);
            foreach ($this->blockPipeLines($line, $width) as $blockLine) {
                $padded[] = $leftBorder . $this->padBlockLine($blockLine, $effectiveWidth, $alignment) . $rightBorder;
            }
        }

        return new DocTemplateBlockOutput($padded, $leftBorder . str_repeat(' ', $blankContentWidth) . $rightBorder);
    }

    /**
     * @return list<string>
     */
    private function blockPipeLines(string $line, int $width): array
    {
        $effectiveWidth = $this->effectiveBlockPipeWidth($width, $line);
        if ($effectiveWidth < 1 || UnicodeText::displayWidth($line) <= $effectiveWidth) {
            return [$line];
        }

        $chunks = [];
        $chunk = '';
        foreach ($this->unicodeCharacters($line) as $character) {
            $candidate = $chunk . $character;
            if ($chunk !== '' && UnicodeText::displayWidth($candidate) > $effectiveWidth) {
                $chunks[] = $chunk;
                $chunk = $character;
                continue;
            }

            $chunk = $candidate;
        }

        if ($chunk !== '' || $chunks === []) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    private function effectiveBlockPipeWidth(int $width, string $line): int
    {
        return $width < 1 && $line !== '' ? 1 : $width;
    }

    /**
     * @return list<string>
     */
    private function unicodeCharacters(string $value): array
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return str_split($value);
        }

        return $characters;
    }

    private function padBlockLine(string $line, int $width, string $alignment): string
    {
        return UnicodeText::padDisplay($line, $width, $alignment);
    }

    private function pipePairs(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $pairs = [];
        if (array_is_list($value)) {
            foreach ($value as $index => $item) {
                $pairs[] = ['key' => $index + 1, 'value' => $item];
            }

            return $pairs;
        }

        $ordered = $value;
        ksort($ordered, SORT_STRING);

        foreach ($ordered as $key => $item) {
            $pairs[] = ['key' => $key, 'value' => $item];
        }

        return $pairs;
    }

    private function pipeLength(mixed $value): int
    {
        if ($value instanceof DocTemplateBlockOutput) {
            return $this->stringLength($this->blockOutputToString($value));
        }

        if (is_string($value)) {
            return $this->stringLength($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }

    private function pipeReverse(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->reverseString($value);
        }

        if (is_array($value) && array_is_list($value)) {
            return array_reverse($value);
        }

        return $value;
    }

    private function pipeChomp(mixed $value): mixed
    {
        if ($value instanceof DocTemplateBlockOutput) {
            return $value;
        }

        if (is_array($value)) {
            $chomped = [];
            foreach ($value as $key => $item) {
                $chomped[$key] = $this->pipeChomp($item);
            }

            return $chomped;
        }

        if (is_string($value)) {
            return $this->chompBreakableText($value);
        }

        return $value;
    }

    private function pipeNowrap(mixed $value): mixed
    {
        if ($value instanceof DocTemplateBlockOutput) {
            return $value;
        }

        if (is_array($value)) {
            $nowrap = [];
            foreach ($value as $key => $item) {
                $nowrap[$key] = $this->pipeNowrap($item);
            }

            return $nowrap;
        }

        if (is_string($value)) {
            return $this->replaceBreakableSpaceMarkers($value, ' ');
        }

        return $value;
    }

    private function uppercase(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function lowercase(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        $count = preg_match_all('/./us', $value, $matches);
        if ($count !== false) {
            return $count;
        }

        return strlen($value);
    }

    private function reverseString(string $value): string
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return strrev($value);
        }

        return implode('', array_reverse($characters));
    }

    private function renderValue(mixed $value, ?string $separator): string
    {
        if ($value instanceof DocTemplateBlockOutput) {
            return $this->encodeBlockOutput($value);
        }

        if (is_array($value)) {
            if (!array_is_list($value)) {
                return 'true';
            }

            $parts = [];
            foreach ($value as $item) {
                $parts[] = $this->renderValue($item, null);
            }

            return implode($separator ?? '', $parts);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return $this->stripSingleFinalNewline($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    private function renderPartialValue(mixed $value, ?string $separator): string
    {
        if ($value instanceof DocTemplateBlockOutput) {
            return $this->encodeBlockOutput($value);
        }

        if (is_string($value)) {
            return $value;
        }

        return $this->renderValue($value, $separator);
    }

    private function stripSingleFinalNewline(string $value): string
    {
        if (str_ends_with($value, "\r\n")) {
            return substr($value, 0, -2);
        }

        if (str_ends_with($value, "\n") || str_ends_with($value, "\r")) {
            return substr($value, 0, -1);
        }

        return $value;
    }

    private function stripRootTemplateRedundantFinalBlankLine(string $template, string $value): string
    {
        $templateWithoutFinalLineEnding = $this->stripSingleFinalNewline($template);
        if ($templateWithoutFinalLineEnding === $template || !$this->endsWithLineEnding($templateWithoutFinalLineEnding)) {
            return $value;
        }

        $withoutFinalLineEnding = $this->stripSingleFinalNewline($value);
        if ($withoutFinalLineEnding !== $value && $this->endsWithLineEnding($withoutFinalLineEnding)) {
            return $withoutFinalLineEnding;
        }

        return $value;
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value instanceof DocTemplateBlockOutput) {
            return $value->lines() !== [];
        }

        if (is_array($value)) {
            if (!array_is_list($value)) {
                return true;
            }

            foreach ($value as $item) {
                if ($this->isTruthy($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value !== '';
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<mixed>
     */
    private function loopIterations(bool $exists, mixed $value): array
    {
        if (!$exists || $value === null) {
            return [];
        }

        if (is_array($value)) {
            if ($value === []) {
                return [];
            }

            return array_is_list($value) ? $value : [$value];
        }

        return [$value];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function contextForLoopIteration(array $context, string $path, mixed $item, bool $rebindPath): array
    {
        $next = $context;
        $next['it'] = $item;

        $segments = explode('.', $path);
        if (!$rebindPath || $segments[0] === 'it') {
            return $next;
        }

        $cursor = &$next;
        foreach ($segments as $offset => $segment) {
            if ($offset === count($segments) - 1) {
                $cursor[$segment] = $item;
                break;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        unset($cursor);

        return $next;
    }

    private function currentColumn(string $output): int
    {
        $lineStart = $this->lastLineEndingByteOffset($output);
        $line = $lineStart === null ? $output : substr($output, $lineStart + 1);

        return UnicodeText::displayWidth(str_replace("\t", ' ', $line));
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function automaticNestPrefix(array $tokens, int $index, int $end, string $output): ?string
    {
        $lineStart = $this->lastLineEndingByteOffset($output);
        $prefix = $lineStart === null ? $output : substr($output, $lineStart + 1);
        if (trim($prefix, " \t") !== '') {
            return null;
        }

        for ($next = $index + 1; $next < $end; $next++) {
            $token = $tokens[$next];
            if ($token['type'] !== 'text') {
                return null;
            }

            $newline = $this->firstLineEndingByteOffset($token['value']);
            $beforeNewline = $newline === null ? $token['value'] : substr($token['value'], 0, $newline);
            if (trim($beforeNewline, " \t\r") !== '') {
                return null;
            }

            if ($newline !== null) {
                return $prefix;
            }
        }

        return $prefix;
    }

    /**
     * @param array<string, mixed> $token
     */
    private function directiveStartsBeforeExplicitNestColumn(array $token, int $sourceColumn, string $output): bool
    {
        if ((int) $token['column'] >= $sourceColumn) {
            return false;
        }

        $lineStart = $this->lastLineEndingByteOffset($output);
        $prefix = $lineStart === null ? $output : substr($output, $lineStart + 1);

        return trim($prefix, " \t") === '';
    }

    /**
     * @param ?list<string> $pendingBlockLines
     */
    private function appendRenderedChunk(
        string &$output,
        string $chunk,
        ?int &$explicitNestColumn,
        ?int &$explicitNestSourceColumn,
        bool $templateText,
        ?int &$pendingBlockStart,
        ?array &$pendingBlockLines,
        ?string &$pendingBlockBlankLine,
    ): void {
        if ($explicitNestColumn !== null) {
            $activeNestColumn = $explicitNestColumn;
            if (str_contains($chunk, self::BLOCK_PIPE_MARKER_START)) {
                $chunk = $this->expandBlockOutputMarkers($chunk);
            }

            if (strpbrk($chunk, "\r\n") !== false) {
                if ($templateText) {
                    [$chunk, $stillNested] = $this->nestTemplateTextChunk($chunk, $explicitNestColumn, $explicitNestSourceColumn);
                    if (!$stillNested) {
                        $explicitNestColumn = null;
                        $explicitNestSourceColumn = null;
                    }
                } else {
                    $chunk = $this->nestMultiline($chunk, str_repeat(' ', $explicitNestColumn));
                }
            }

            if (str_contains($chunk, self::BREAKABLE_SPACE_MARKER)) {
                $chunk = $this->annotateBreakableSpaceIndent($chunk, $activeNestColumn);
            }

            $output .= $chunk;
            $this->clearPendingBlockOutput($pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
            return;
        }

        $this->appendBlockAwareChunk($output, $chunk, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
    }

    /**
     * @param ?list<string> $pendingBlockLines
     */
    private function appendBlockAwareChunk(string &$output, string $chunk, ?int &$pendingBlockStart, ?array &$pendingBlockLines, ?string &$pendingBlockBlankLine): void
    {
        if ($chunk === '') {
            return;
        }

        if (!str_contains($chunk, self::BLOCK_PIPE_MARKER_START)) {
            $this->appendPlainRenderedText($output, $chunk, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
            return;
        }

        $offset = 0;
        $length = strlen($chunk);
        while ($offset < $length) {
            $markerStart = strpos($chunk, self::BLOCK_PIPE_MARKER_START, $offset);
            if ($markerStart === false) {
                $tail = substr($chunk, $offset);
                if ($tail !== '') {
                    $this->appendPlainRenderedText($output, $tail, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
                }

                return;
            }

            if ($markerStart > $offset) {
                $text = substr($chunk, $offset, $markerStart - $offset);
                $this->appendPlainRenderedText($output, $text, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
            }

            $decoded = $this->decodeBlockOutputAt($chunk, $markerStart);
            if ($decoded === null) {
                $this->appendPlainRenderedText($output, self::BLOCK_PIPE_MARKER_START, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
                $offset = $markerStart + strlen(self::BLOCK_PIPE_MARKER_START);
                continue;
            }

            [$block, $offset] = $decoded;
            $this->appendBlockOutput($output, $block, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
        }
    }

    /**
     * @param ?list<string> $pendingBlockLines
     */
    private function appendPlainRenderedText(string &$output, string $text, ?int &$pendingBlockStart, ?array &$pendingBlockLines, ?string &$pendingBlockBlankLine): void
    {
        if ($text !== '' && ($text[0] === "\n" || $text[0] === "\r")) {
            $this->finalizePendingBlockOutput($output, $pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
        }

        $output .= $text;
        $this->clearPendingBlockOutput($pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
    }

    /**
     * @param ?list<string> $pendingBlockLines
     */
    private function appendBlockOutput(string &$output, DocTemplateBlockOutput $block, ?int &$pendingBlockStart, ?array &$pendingBlockLines, ?string &$pendingBlockBlankLine): void
    {
        if ($pendingBlockStart !== null && $pendingBlockLines !== null && $pendingBlockBlankLine !== null) {
            [$combinedLines, $combinedBlankLine] = $this->composeBlockOutputs($pendingBlockLines, $pendingBlockBlankLine, $block);
            $output = substr($output, 0, $pendingBlockStart) . implode("\n", $combinedLines);
            $pendingBlockLines = $combinedLines;
            $pendingBlockBlankLine = $combinedBlankLine;
            return;
        }

        $pendingBlockStart = strlen($output);
        $pendingBlockLines = $block->lines();
        $pendingBlockBlankLine = $block->blankLine();
        $output .= implode("\n", $pendingBlockLines);
    }

    /**
     * @param list<string> $leftLines
     * @return array{0:list<string>, 1:string}
     */
    private function composeBlockOutputs(array $leftLines, string $leftBlankLine, DocTemplateBlockOutput $right): array
    {
        $rightLines = $right->lines();
        $rightBlankLine = $right->blankLine();
        $lineCount = max(count($leftLines), count($rightLines));
        $lines = [];

        for ($index = 0; $index < $lineCount; $index++) {
            $lines[] = ($leftLines[$index] ?? $leftBlankLine) . ($rightLines[$index] ?? $rightBlankLine);
        }

        return [$lines, $leftBlankLine . $rightBlankLine];
    }

    /**
     * @param ?list<string> $pendingBlockLines
     */
    private function clearPendingBlockOutput(?int &$pendingBlockStart, ?array &$pendingBlockLines, ?string &$pendingBlockBlankLine): void
    {
        $pendingBlockStart = null;
        $pendingBlockLines = null;
        $pendingBlockBlankLine = null;
    }

    /**
     * @param ?list<string> $pendingBlockLines
     */
    private function finalizePendingBlockOutput(string &$output, ?int &$pendingBlockStart, ?array &$pendingBlockLines, ?string &$pendingBlockBlankLine): void
    {
        if ($pendingBlockStart === null || $pendingBlockLines === null) {
            return;
        }

        $trimmedLines = array_map(static fn (string $line): string => rtrim($line, ' '), $pendingBlockLines);
        $output = substr($output, 0, $pendingBlockStart) . implode("\n", $trimmedLines);
        $this->clearPendingBlockOutput($pendingBlockStart, $pendingBlockLines, $pendingBlockBlankLine);
    }

    private function encodeBlockOutput(DocTemplateBlockOutput $block): string
    {
        $json = json_encode([
            'lines' => $block->lines(),
            'blankLine' => $block->blankLine(),
        ], JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \UnexpectedValueException('Unable to encode doctemplate block pipe output');
        }

        return self::BLOCK_PIPE_MARKER_START . base64_encode($json) . self::BLOCK_PIPE_MARKER_END;
    }

    private function expandBlockOutputMarkers(string $chunk): string
    {
        $expanded = '';
        $offset = 0;
        $length = strlen($chunk);
        while ($offset < $length) {
            $markerStart = strpos($chunk, self::BLOCK_PIPE_MARKER_START, $offset);
            if ($markerStart === false) {
                $expanded .= substr($chunk, $offset);
                break;
            }

            $expanded .= substr($chunk, $offset, $markerStart - $offset);
            $decoded = $this->decodeBlockOutputAt($chunk, $markerStart);
            if ($decoded === null) {
                $expanded .= self::BLOCK_PIPE_MARKER_START;
                $offset = $markerStart + strlen(self::BLOCK_PIPE_MARKER_START);
                continue;
            }

            [$block, $offset] = $decoded;
            $expanded .= $this->blockOutputToString($block);
        }

        return $expanded;
    }

    /**
     * @return null|array{0:DocTemplateBlockOutput, 1:int}
     */
    private function decodeBlockOutputAt(string $chunk, int $offset): ?array
    {
        if (substr($chunk, $offset, strlen(self::BLOCK_PIPE_MARKER_START)) !== self::BLOCK_PIPE_MARKER_START) {
            return null;
        }

        $payloadStart = $offset + strlen(self::BLOCK_PIPE_MARKER_START);
        $payloadEnd = strpos($chunk, self::BLOCK_PIPE_MARKER_END, $payloadStart);
        if ($payloadEnd === false) {
            return null;
        }

        $payload = substr($chunk, $payloadStart, $payloadEnd - $payloadStart);
        $json = base64_decode($payload, true);
        if (!is_string($json)) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['lines'], $data['blankLine']) || !is_array($data['lines']) || !is_string($data['blankLine']) || !array_is_list($data['lines'])) {
            return null;
        }

        $lines = [];
        foreach ($data['lines'] as $line) {
            if (!is_string($line)) {
                return null;
            }

            $lines[] = $line;
        }

        return [new DocTemplateBlockOutput($lines, $data['blankLine']), $payloadEnd + strlen(self::BLOCK_PIPE_MARKER_END)];
    }

    private function blockOutputToString(DocTemplateBlockOutput $block): string
    {
        return implode("\n", $block->lines());
    }

    private function nestMultiline(string $value, string $indent): string
    {
        if (str_contains($value, self::BLOCK_PIPE_MARKER_START)) {
            $value = $this->expandBlockOutputMarkers($value);
        }

        if ($indent === '' || strpbrk($value, "\r\n") === false) {
            return $value;
        }

        $output = '';
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            if (preg_match('/\r\n|\n|\r/', $value, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                $output .= substr($value, $offset);
                break;
            }

            $lineEnding = $matches[0][0];
            $lineEndingStart = $matches[0][1];
            $afterLineEnding = $lineEndingStart + strlen($lineEnding);
            $output .= substr($value, $offset, $afterLineEnding - $offset);

            if ($afterLineEnding >= $length || $value[$afterLineEnding] === "\n" || $value[$afterLineEnding] === "\r") {
                $offset = $afterLineEnding;
                continue;
            }

            $output .= $indent;
            $offset = $afterLineEnding;
        }

        return $output;
    }

    /**
     * @return array{0:string, 1:bool}
     */
    private function nestTemplateTextChunk(string $value, int $column, ?int $sourceColumn): array
    {
        $indent = str_repeat(' ', $column);
        $sourceIndentColumn = $sourceColumn === null ? $column : max(0, $sourceColumn - 1);
        $output = '';
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            if (preg_match('/\r\n|\n|\r/', $value, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                $output .= substr($value, $offset);
                break;
            }

            $lineEnding = $matches[0][0];
            $lineEndingStart = $matches[0][1];
            $afterLineEnding = $lineEndingStart + strlen($lineEnding);
            $output .= substr($value, $offset, $afterLineEnding - $offset);

            if ($afterLineEnding >= $length) {
                break;
            }

            $indentEnd = $afterLineEnding;
            while ($indentEnd < $length && ($value[$indentEnd] === ' ' || $value[$indentEnd] === "\t")) {
                $indentEnd++;
            }

            $sourceIndent = substr($value, $afterLineEnding, $indentEnd - $afterLineEnding);
            if ($indentEnd < $length && ($value[$indentEnd] === "\r" || $value[$indentEnd] === "\n")) {
                if ($this->containsOnlyLineEndings(substr($value, $indentEnd))) {
                    $output .= substr($value, $indentEnd);

                    return [$output, true];
                }

                $output .= $indent;
                $offset = $indentEnd;
                continue;
            }

            if (strlen($sourceIndent) < $sourceIndentColumn) {
                $output .= substr($value, $afterLineEnding);
                return [$output, false];
            }

            // Upstream doctemplates treats source-aligned continuation text
            // as nested at the active column, not as nested plus surplus
            // template-source indentation.
            $output .= $indent;
            $offset = $indentEnd;
        }

        return [$output, true];
    }

    private function containsOnlyLineEndings(string $value): bool
    {
        return $value !== '' && strspn($value, "\r\n") === strlen($value);
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function appendTextToken(array &$tokens, string $text, bool $breakableSpaces = false): void
    {
        if ($text !== '') {
            $tokens[] = ['type' => 'text', 'value' => $text, 'breakable' => $breakableSpaces];
        }
    }

    private function validateLineLength(int $lineLength): void
    {
        if ($lineLength < 1) {
            throw new \InvalidArgumentException('Doctemplate wrapped rendering requires a positive line length');
        }
    }

    private function annotateBreakableSpaceIndent(string $value, int $indent): string
    {
        $marker = self::BREAKABLE_SPACE_MARKER;
        $indentMarker = self::BREAKABLE_SPACE_INDENT_MARKER . $indent . ';';
        $output = '';
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            $markerOffset = strpos($value, $marker, $offset);
            if ($markerOffset === false) {
                $output .= substr($value, $offset);
                break;
            }

            $output .= substr($value, $offset, $markerOffset - $offset + 1);
            $afterMarker = $markerOffset + 1;
            if (!$this->readBreakableSpaceIndent($value, $afterMarker)[1]) {
                $output .= $indentMarker;
            }

            $offset = $afterMarker;
        }

        return $output;
    }

    /**
     * @return array{0:?int, 1:bool, 2:int}
     */
    private function readBreakableSpaceIndent(string $value, int $offset): array
    {
        if (($value[$offset] ?? '') !== self::BREAKABLE_SPACE_INDENT_MARKER) {
            return [null, false, $offset];
        }

        $start = $offset + 1;
        $end = strpos($value, ';', $start);
        if ($end === false) {
            return [null, false, $offset];
        }

        $digits = substr($value, $start, $end - $start);
        if ($digits === '' || !ctype_digit($digits)) {
            return [null, false, $offset];
        }

        return [(int) $digits, true, $end + 1];
    }

    private function replaceBreakableSpaceMarkers(string $value, string $replacement): string
    {
        return preg_replace(
            '/' . preg_quote(self::BREAKABLE_SPACE_MARKER, '/') . '(?:' . preg_quote(self::BREAKABLE_SPACE_INDENT_MARKER, '/') . '[0-9]+;)?/',
            $replacement,
            $value,
        ) ?? $value;
    }

    private function chompBreakableText(string $value): string
    {
        return preg_replace(
            '/(?:\r\n|\n|\r|' . preg_quote(self::BREAKABLE_SPACE_MARKER, '/') . '(?:' . preg_quote(self::BREAKABLE_SPACE_INDENT_MARKER, '/') . '[0-9]+;)?)++$/',
            '',
            $value,
        ) ?? $value;
    }

    private function wrapBreakableSpaces(string $value, int $lineLength): string
    {
        if (!str_contains($value, self::BREAKABLE_SPACE_MARKER)) {
            return $value;
        }

        $output = '';
        $column = 0;
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            $markerOffset = strpos($value, self::BREAKABLE_SPACE_MARKER, $offset);
            if ($markerOffset === false) {
                $this->appendWrappedSegment($output, $column, substr($value, $offset));
                break;
            }

            $this->appendWrappedSegment($output, $column, substr($value, $offset, $markerOffset - $offset));
            [$continuationIndent, $_hasIndent, $afterMarker] = $this->readBreakableSpaceIndent($value, $markerOffset + 1);
            $nextMarker = strpos($value, self::BREAKABLE_SPACE_MARKER, $afterMarker);
            $part = $nextMarker === false
                ? substr($value, $afterMarker)
                : substr($value, $afterMarker, $nextMarker - $afterMarker);
            $nextWidth = $this->leadingSegmentWidth($part);

            if ($column > 0 && $column + 1 + $nextWidth > $lineLength) {
                $output .= "\n";
                $column = 0;
                if ($continuationIndent !== null && $continuationIndent > 0) {
                    $output .= str_repeat(' ', $continuationIndent);
                    $column = $continuationIndent;
                }
            } elseif ($column > 0) {
                $output .= ' ';
                $column++;
            }

            $offset = $afterMarker;
        }

        return $output;
    }

    private function leadingSegmentWidth(string $value): int
    {
        if (preg_match('/\r\n|\n|\r/', $value, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $value = substr($value, 0, $matches[0][1]);
        }

        return UnicodeText::displayWidth(str_replace("\t", ' ', $value));
    }

    private function appendWrappedSegment(string &$output, int &$column, string $segment): void
    {
        $offset = 0;
        $length = strlen($segment);

        while ($offset < $length) {
            if (preg_match('/\r\n|\n|\r/', $segment, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                $chunk = substr($segment, $offset);
                $output .= $chunk;
                $column += UnicodeText::displayWidth(str_replace("\t", ' ', $chunk));
                return;
            }

            $lineEnding = $matches[0][0];
            $lineEndingStart = $matches[0][1];
            $chunk = substr($segment, $offset, $lineEndingStart - $offset);
            $output .= $chunk . $lineEnding;
            $column = 0;
            $offset = $lineEndingStart + strlen($lineEnding);
        }
    }

    private function normalizeBreakableSpaces(string $text, string $replacement = ' '): string
    {
        return preg_replace('/[ \t\r\n]+/', $replacement, $text) ?? $text;
    }
}
