<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocConverter
{
    /** @var array<string, string> */
    private const EXTRA_INPUT_ALIASES = [
        'md' => 'markdown',
        'htm' => 'html',
        'tab' => 'tsv',
    ];

    /** @var array<string, string> */
    private const EXTRA_OUTPUT_ALIASES = [
        'blocks' => 'wordpress',
        'wp' => 'wordpress',
        'md' => 'markdown',
        'htm' => 'html',
    ];

    /**
     * @param array<string, mixed> $options
     */
    public static function read(
        string $bytes,
        string $format,
        array $options = [],
        ?string $outputFormat = null
    ): AstNode
    {
        $options = self::readerOptionsForOutputPreparation($format, $outputFormat, $options);
        $requestedFormat = self::normalizeFormat($format);
        $canonical = self::canonicalInputFormat($requestedFormat);
        $entry = self::inputSupport($canonical);
        if ($entry['implementation'] === DelimitedTextReader::class) {
            return (new DelimitedTextReader())->read($bytes, $canonical, $options);
        }

        // The historical Pandoc alias has one list-boundary behavior that is
        // not represented by the canonical GFM registry key. Keep the modern
        // GFM feature profile while passing that narrow reader distinction.
        if (
            $entry['implementation'] === MarkdownReader::class
            && preg_match('/^markdown(?:_|-)github(?:[+-]|$)/', $requestedFormat) === 1
            && !isset($options['pandocMarkdownGithubMixedBulletMarkers'])
        ) {
            $options['pandocMarkdownGithubMixedBulletMarkers'] = true;
        }

        $options = self::readerOptionsForRequestedFormat($entry['implementation'], $requestedFormat, $options);
        $reader = self::reader($entry['implementation'], $canonical, $options);
        if ($reader instanceof MarkdownReader) {
            $encoding = self::stringReaderOption($options, ['sourceEncoding', 'inputEncoding', 'encoding']);
            $normalization = self::stringReaderOption($options, ['unicodeNormalization', 'normalizationForm']);

            return $reader->readBytes($bytes, $encoding, $normalization);
        }

        return $reader->read($bytes);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function readFile(
        string $path,
        string $format,
        array $options = [],
        ?string $outputFormat = null
    ): AstNode
    {
        $options = self::readerOptionsForOutputPreparation($format, $outputFormat, $options);
        if (self::canonicalInputFormat($format) === 'epub') {
            if (!isset($options['sourcePath'])) {
                $options['sourcePath'] = $path;
            }

            return (new EpubReader($options))->readEpubFile($path);
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }
        if (!isset($options['sourcePath'])) {
            $options['sourcePath'] = $path;
        }

        $requestedFormat = self::normalizeFormat($format);
        $canonical = self::canonicalInputFormat($requestedFormat);
        if ($canonical === 'pdf') {
            $entry = self::inputSupport($canonical);
            $readerOptions = self::readerOptionsForRequestedFormat(
                $entry['implementation'],
                $requestedFormat,
                $options
            );
            $reader = self::reader($entry['implementation'], $canonical, $readerOptions);
            if (!$reader instanceof PdfReader) {
                throw new \LogicException('The registered PDF reader must be a PdfReader.');
            }

            return $reader->readOwned($bytes);
        }

        return self::read($bytes, $format, $options, $outputFormat);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function write(AstNode $document, string $format, array $options = []): string
    {
        $canonical = self::canonicalOutputFormat($format);
        if ($canonical === 'wordpress') {
            return (new WordPressBlockWriter($options))->write($document);
        }

        $entry = self::outputSupport($canonical);
        $writer = self::writer($entry['implementation'], $canonical, $options);

        return $writer->write($document);
    }

    /**
     * @param callable(string): void $sink
     * @param array<string, mixed> $options
     */
    public static function writeTo(AstNode $document, string $format, callable $sink, array $options = []): void
    {
        $canonical = self::canonicalOutputFormat($format);
        if ($canonical === 'wordpress') {
            (new WordPressBlockWriter($options))->writeTo($document, $sink);

            return;
        }

        $entry = self::outputSupport($canonical);
        $writer = self::writer($entry['implementation'], $canonical, $options);
        $sink($writer->write($document));
    }

    /**
     * @param array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>} $options
     */
    public static function convert(string $bytes, string $from, string $to, array $options = []): string
    {
        $readerOptions = isset($options['readerOptions']) && is_array($options['readerOptions']) ? $options['readerOptions'] : [];
        $writerOptions = isset($options['writerOptions']) && is_array($options['writerOptions']) ? $options['writerOptions'] : [];

        return self::write(self::read($bytes, $from, $readerOptions, $to), $to, $writerOptions);
    }

    /**
     * Convert into a caller-owned output sink. EPUB-to-WordPress uses its
     * spine generator when no media rewrite or metadata review is requested.
     *
     * @param callable(string): void $sink
     * @param array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>, extractMedia?: string|array<string, mixed>, extract-media?: string|array<string, mixed>} $options
     */
    public static function convertToSink(string $bytes, string $from, string $to, callable $sink, array $options = []): void
    {
        $readerOptions = isset($options['readerOptions']) && is_array($options['readerOptions']) ? $options['readerOptions'] : [];
        $writerOptions = isset($options['writerOptions']) && is_array($options['writerOptions']) ? $options['writerOptions'] : [];
        $input = self::canonicalInputFormat($from);
        $output = self::canonicalOutputFormat($to);

        if (
            $input === 'epub'
            && $output === 'wordpress'
            && self::extractMediaOptions($options) === null
            && !(bool) ($writerOptions['includeMetadata'] ?? false)
        ) {
            (new WordPressBlockWriter($writerOptions))->writeNodesTo(
                (new EpubReader($readerOptions))->streamNodes($bytes),
                $sink
            );

            return;
        }

        self::writeTo(self::read($bytes, $from, $readerOptions, $to), $to, $sink, $writerOptions);
    }

    /**
     * @param array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>, extractMedia?: string|array<string, mixed>, extract-media?: string|array<string, mixed>} $options
     * @return array{output:string, media:list<array<string, mixed>>, diagnostics:list<string>, sourceIntegrity?:array<string,bool|int|null>}
     */
    public static function convertWithMedia(string $bytes, string $from, string $to, array $options = []): array
    {
        $readerOptions = isset($options['readerOptions']) && is_array($options['readerOptions']) ? $options['readerOptions'] : [];
        $writerOptions = isset($options['writerOptions']) && is_array($options['writerOptions']) ? $options['writerOptions'] : [];
        $extractOptions = self::extractMediaOptions($options);

        // PDF image placement depends on page geometry which is intentionally
        // discarded by the normal text-only AST. Ask the reader to retain a
        // compact, opt-in anchor map only when this conversion will also run
        // the media pass.
        if (self::canonicalInputFormat($from) === 'pdf'
            && $extractOptions !== null
            && !array_key_exists('pdfCollectImagePlacements', $readerOptions)
            && !array_key_exists('collectPdfImagePlacements', $readerOptions)) {
            $readerOptions['pdfCollectImagePlacements'] = true;
        }

        $document = self::read($bytes, $from, $readerOptions, $to);
        $entries = [];
        $diagnostics = [];
        if ($extractOptions !== null) {
            if (!isset($extractOptions['sourcePath']) && isset($readerOptions['sourcePath']) && is_string($readerOptions['sourcePath'])) {
                $extractOptions['sourcePath'] = $readerOptions['sourcePath'];
            }
            $extracted = (new PandocMediaExtractor())->extract($document, $bytes, $from, $extractOptions);
            $document = $extracted['document'];
            $entries = $extracted['entries'];
            $diagnostics = $extracted['diagnostics'];
            if (isset($extractOptions['outputDirectory']) && is_string($extractOptions['outputDirectory']) && $extractOptions['outputDirectory'] !== '') {
                self::writeMediaEntries($entries, $extractOptions['outputDirectory']);
            }
        }

        $result = [
            'output' => self::write($document, $to, $writerOptions),
            'media' => self::publicMediaEntries($entries),
            'diagnostics' => $diagnostics,
        ];
        $sourceIntegrity = self::documentSourceIntegrity($document);
        if ($sourceIntegrity !== null) {
            $result['sourceIntegrity'] = $sourceIntegrity;
        }

        return $result;
    }

    /**
     * Expose only the bounded publication-critical PDF coverage and ledger summary. This
     * lets out-of-process showcase and review tooling fail closed without
     * serializing the reader's potentially very large diagnostic metadata.
     *
     * @return array<string, mixed>|null
     */
    private static function documentSourceIntegrity(AstNode $document): ?array
    {
        $meta = $document->attr('meta', []);
        if (!is_array($meta)
            || (!array_key_exists('pdfSemanticTextComplete', $meta)
                && !array_key_exists('pdfSourceBindingComplete', $meta))) {
            return null;
        }

        $disposition = is_array($meta['pdfSourceDisposition'] ?? null)
            ? $meta['pdfSourceDisposition']
            : [];
        $documentComplete = ($meta['pdfDocumentComplete'] ?? null) === true;
        $semanticTextComplete = ($meta['pdfSemanticTextComplete'] ?? null) === true;
        $sourceBindingComplete = ($meta['pdfSourceBindingComplete'] ?? null) === true;
        $sourceEdgeMappingComplete = ($disposition['sourceEdgeMappingComplete'] ?? null) === true;
        $orderedCharactersPreserved = ($disposition['orderedSignificantCharactersPreserved'] ?? null) === true;
        $unresolved = is_int($disposition['unresolvedOccurrenceCount'] ?? null)
            ? $disposition['unresolvedOccurrenceCount']
            : null;
        $textLayerStatus = is_string($meta['pdfTextLayerStatus'] ?? null)
            ? $meta['pdfTextLayerStatus']
            : null;
        $needsOcr = ($meta['pdfNeedsOcr'] ?? null) === true;
        $pageRepresentationComplete = ($meta['pdfPageRepresentationComplete'] ?? null) === true;

        return [
            'complete' => $documentComplete
                && $semanticTextComplete
                && $sourceBindingComplete
                && $sourceEdgeMappingComplete
                && $orderedCharactersPreserved
                && $unresolved === 0
                && $pageRepresentationComplete,
            'pdfDocumentComplete' => $documentComplete,
            'pdfTextComplete' => ($meta['pdfTextComplete'] ?? null) === true,
            'pdfNoTextClassificationComplete' => ($meta['pdfNoTextClassificationComplete'] ?? null) === true,
            'pdfSemanticTextComplete' => $semanticTextComplete,
            'pdfSourceBindingComplete' => $sourceBindingComplete,
            'pdfSourceEdgeMappingComplete' => $sourceEdgeMappingComplete,
            'pdfOrderedSignificantCharactersPreserved' => $orderedCharactersPreserved,
            'pdfUnresolvedSourceOccurrences' => $unresolved,
            'pdfTextLayerStatus' => $textLayerStatus,
            'pdfNeedsOcr' => $needsOcr,
            'pdfPageCount' => max(0, (int) ($meta['pdfPageCount'] ?? 0)),
            'pdfTextRepresentedPageNumbers' => is_array($meta['pdfTextRepresentedPageNumbers'] ?? null)
                ? array_values(array_map('intval', $meta['pdfTextRepresentedPageNumbers']))
                : [],
            'pdfPagesNeedingImageRepresentation' => is_array(
                $meta['pdfPagesNeedingImageRepresentation'] ?? null
            )
                ? array_values(array_map('intval', $meta['pdfPagesNeedingImageRepresentation']))
                : [],
            'pdfRepresentedPageNumbers' => is_array($meta['pdfRepresentedPageNumbers'] ?? null)
                ? array_values(array_map('intval', $meta['pdfRepresentedPageNumbers']))
                : [],
            'pdfPageRepresentationComplete' => $pageRepresentationComplete,
        ];
    }

    /**
     * @param array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>} $options
     */
    public static function convertFile(string $path, string $from, string $to, array $options = []): string
    {
        if (self::canonicalInputFormat($from) === 'epub') {
            $readerOptions = isset($options['readerOptions']) && is_array($options['readerOptions']) ? $options['readerOptions'] : [];
            $writerOptions = isset($options['writerOptions']) && is_array($options['writerOptions']) ? $options['writerOptions'] : [];
            if (!isset($readerOptions['sourcePath'])) {
                $readerOptions['sourcePath'] = $path;
            }

            return self::write(self::readFile($path, $from, $readerOptions, $to), $to, $writerOptions);
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }
        if (!isset($options['readerOptions']) || !is_array($options['readerOptions'])) {
            $options['readerOptions'] = [];
        }
        if (!isset($options['readerOptions']['sourcePath'])) {
            $options['readerOptions']['sourcePath'] = $path;
        }

        return self::convert($bytes, $from, $to, $options);
    }

    /**
     * @param callable(string): void $sink
     * @param array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>, extractMedia?: string|array<string, mixed>, extract-media?: string|array<string, mixed>} $options
     */
    public static function convertFileToSink(string $path, string $from, string $to, callable $sink, array $options = []): void
    {
        $readerOptions = isset($options['readerOptions']) && is_array($options['readerOptions']) ? $options['readerOptions'] : [];
        $writerOptions = isset($options['writerOptions']) && is_array($options['writerOptions']) ? $options['writerOptions'] : [];
        if (!isset($readerOptions['sourcePath'])) {
            $readerOptions['sourcePath'] = $path;
        }
        if (
            self::canonicalInputFormat($from) === 'epub'
            && self::canonicalOutputFormat($to) === 'wordpress'
            && self::extractMediaOptions($options) === null
            && !(bool) ($writerOptions['includeMetadata'] ?? false)
        ) {
            (new WordPressBlockWriter($writerOptions))->writeNodesTo(
                (new EpubReader($readerOptions))->streamEpubFileNodes($path),
                $sink
            );

            return;
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }
        self::convertToSink($bytes, $from, $to, $sink, $options);
    }

    /**
     * @param array{readerOptions?: array<string, mixed>, writerOptions?: array<string, mixed>, extractMedia?: string|array<string, mixed>, extract-media?: string|array<string, mixed>} $options
     * @return array{output:string, media:list<array<string, mixed>>, diagnostics:list<string>, sourceIntegrity?:array<string,bool|int|null>}
     */
    public static function convertFileWithMedia(string $path, string $from, string $to, array $options = []): array
    {
        if (self::canonicalInputFormat($from) === 'epub') {
            $readerOptions = isset($options['readerOptions']) && is_array($options['readerOptions']) ? $options['readerOptions'] : [];
            $writerOptions = isset($options['writerOptions']) && is_array($options['writerOptions']) ? $options['writerOptions'] : [];
            if (!isset($readerOptions['sourcePath'])) {
                $readerOptions['sourcePath'] = $path;
            }
            $extractOptions = self::extractMediaOptions($options);
            $document = self::readFile($path, $from, $readerOptions, $to);
            $entries = [];
            $diagnostics = [];
            if ($extractOptions !== null) {
                if (!isset($extractOptions['sourcePath'])) {
                    $extractOptions['sourcePath'] = $readerOptions['sourcePath'];
                }
                $extracted = (new PandocMediaExtractor())->extractFile($document, $path, $from, $extractOptions);
                $document = $extracted['document'];
                $entries = $extracted['entries'];
                $diagnostics = $extracted['diagnostics'];
                if (isset($extractOptions['outputDirectory']) && is_string($extractOptions['outputDirectory']) && $extractOptions['outputDirectory'] !== '') {
                    self::writeMediaEntries($entries, $extractOptions['outputDirectory']);
                }
            }

            return [
                'output' => self::write($document, $to, $writerOptions),
                'media' => self::publicMediaEntries($entries),
                'diagnostics' => $diagnostics,
            ];
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }
        if (!isset($options['readerOptions']) || !is_array($options['readerOptions'])) {
            $options['readerOptions'] = [];
        }
        if (!isset($options['readerOptions']['sourcePath'])) {
            $options['readerOptions']['sourcePath'] = $path;
        }

        return self::convertWithMedia($bytes, $from, $to, $options);
    }

    public static function canRead(string $format): bool
    {
        $canonical = self::canonicalInputFormat($format);
        $support = array_replace(PandocFormatRegistry::phpInputSupport(), PandocFormatRegistry::phpLocalInputSupport());

        return isset($support[$canonical]) && $support[$canonical]['status'] !== 'unsupported';
    }

    public static function canWrite(string $format): bool
    {
        $canonical = self::canonicalOutputFormat($format);
        if ($canonical === 'wordpress') {
            return true;
        }

        $support = PandocFormatRegistry::phpOutputSupport();

        return isset($support[$canonical]) && $support[$canonical]['status'] !== 'unsupported';
    }

    public static function canonicalInputFormat(string $format): string
    {
        $format = self::normalizeFormat($format);
        $aliases = array_replace(PandocFormatRegistry::inputAliases(), self::EXTRA_INPUT_ALIASES);
        $markdownFormat = MarkdownFormatProfile::canonicalMarkdownFormat($format);
        if ($markdownFormat !== null) {
            return $aliases[$markdownFormat] ?? $markdownFormat;
        }

        if (self::docxFormatExtensionOptions($format) !== null) {
            return 'docx';
        }

        $format = str_replace('-', '_', $format);

        return $aliases[$format] ?? $format;
    }

    public static function canonicalOutputFormat(string $format): string
    {
        $format = str_replace('-', '_', self::normalizeFormat($format));
        $aliases = array_replace(PandocFormatRegistry::outputAliases(), self::EXTRA_OUTPUT_ALIASES);

        return $aliases[$format] ?? $format;
    }

    private static function normalizeFormat(string $format): string
    {
        return strtolower(trim($format));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function readerOptionsForOutputPreparation(
        string $inputFormat,
        ?string $outputFormat,
        array $options
    ): array {
        if ($outputFormat !== null
            && self::canonicalInputFormat($inputFormat) === 'pdf'
            && self::canonicalOutputFormat($outputFormat) === 'wordpress') {
            // PdfReader calls this preload at its low-water checkpoint between
            // geometry passes. Compiling the only large TraceMonkey writer
            // helper there avoids a new Zend allocator arena after the AST is
            // retained, while standalone reads remain completely unchanged.
            $options['__pdfMemoryCheckpointPreloadClass'] = WordPressNestedBlockRenderer::class;
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function readerOptionsForRequestedFormat(string $implementation, string $requestedFormat, array $options): array
    {
        if ($implementation === MarkdownReader::class) {
            if (!isset($options['format']) && !isset($options['variant'])) {
                // The registry uses the base dialect for dispatch, while the
                // reader must see its extension specification. Canonicalizing
                // the base preserves the historical markdown_github-to-GFM
                // dispatch while retaining explicit extension switches.
                $options['format'] = self::markdownReaderFormat($requestedFormat);
            }

            return $options;
        }

        if ($implementation !== DocxReader::class) {
            return $options;
        }

        $extensionOptions = self::docxFormatExtensionOptions($requestedFormat);
        if ($extensionOptions === null) {
            return $options;
        }

        foreach ($extensionOptions as $name => $value) {
            if (!array_key_exists($name, $options)) {
                $options[$name] = $value;
            }
        }

        return $options;
    }

    private static function markdownReaderFormat(string $requestedFormat): string
    {
        $canonical = MarkdownFormatProfile::canonicalMarkdownFormat($requestedFormat);
        if ($canonical === null) {
            return $requestedFormat;
        }

        $extensionSuffix = MarkdownFormatProfile::markdownExtensionOptionSuffix(
            MarkdownFormatProfile::markdownExtensionOverrides($requestedFormat)
        );

        return $canonical . $extensionSuffix;
    }

    /**
     * Returns the local DocxReader options encoded by a Pandoc DOCX format
     * specification, or null when the specification is not a supported DOCX
     * format. Keeping this separate from registry lookup prevents a suffix
     * such as docx+styles from being treated as an unknown format name.
     *
     * @return array<string, bool>|null
     */
    private static function docxFormatExtensionOptions(string $format): ?array
    {
        if (preg_match('/^docx((?:[+-][a-z0-9_]+)*)$/', $format, $matches) !== 1) {
            return null;
        }

        $suffix = $matches[1] ?? '';
        if ($suffix === '') {
            return [];
        }

        $options = [];
        if (preg_match_all('/([+-])([a-z0-9_]+)/', $suffix, $extensions, PREG_SET_ORDER) === false) {
            return null;
        }

        foreach ($extensions as $extension) {
            if (($extension[2] ?? '') !== 'styles') {
                return null;
            }
            $options['stylesExtension'] = ($extension[1] ?? '') === '+';
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    private static function extractMediaOptions(array $options): ?array
    {
        $raw = $options['extractMedia'] ?? $options['extract-media'] ?? null;
        if ($raw === null || $raw === false) {
            return null;
        }
        if (is_string($raw)) {
            return ['destination' => $raw];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if ($raw === true) {
            return ['destination' => 'media'];
        }

        throw new \InvalidArgumentException('extractMedia must be a destination string or options array.');
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private static function writeMediaEntries(array $entries, string $outputDirectory): void
    {
        foreach ($entries as $entry) {
            $mediaPath = isset($entry['mediaPath']) && is_string($entry['mediaPath']) ? $entry['mediaPath'] : '';
            $contents = isset($entry['contents']) && is_string($entry['contents']) ? $entry['contents'] : null;
            if ($mediaPath === '' || $contents === null || str_contains($mediaPath, "\0") || str_contains($mediaPath, '..')) {
                continue;
            }
            $path = rtrim($outputDirectory, '/\\') . '/' . str_replace('\\', '/', $mediaPath);
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("Unable to create media directory '{$dir}'.");
            }
            file_put_contents($path, $contents);
        }
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private static function publicMediaEntries(array $entries): array
    {
        return array_map(static function (array $entry): array {
            unset($entry['contents']);

            return $entry;
        }, $entries);
    }

    /**
     * @return array{status:string, implementation:string, notes:string}
     */
    private static function inputSupport(string $format): array
    {
        $support = array_replace(PandocFormatRegistry::phpInputSupport(), PandocFormatRegistry::phpLocalInputSupport());
        if (!isset($support[$format])) {
            throw new \InvalidArgumentException("Unknown Pandoc input format '{$format}'.");
        }
        if ($support[$format]['status'] === 'unsupported') {
            throw new \InvalidArgumentException("No native PHP Pandoc reader is registered for input format '{$format}'.");
        }

        return $support[$format];
    }

    /**
     * @return array{status:string, implementation:string, notes:string}
     */
    private static function outputSupport(string $format): array
    {
        $support = PandocFormatRegistry::phpOutputSupport();
        if (!isset($support[$format])) {
            throw new \InvalidArgumentException("Unknown Pandoc output format '{$format}'.");
        }
        if ($support[$format]['status'] === 'unsupported') {
            throw new \InvalidArgumentException("No native PHP Pandoc writer is registered for output format '{$format}'.");
        }

        return $support[$format];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function reader(string $implementation, string $format, array $options): object
    {
        return match ($implementation) {
            BibliographyReader::class => new BibliographyReader($format, $options),
            DocBookReader::class => new DocBookReader(array_replace($options, ['format' => $format])),
            DocxReader::class => new DocxReader($options),
            DokuWikiReader::class => new DokuWikiReader(),
            EpubReader::class => new EpubReader($options),
            Fb2Reader::class => new Fb2Reader(),
            HtmlReader::class => new HtmlReader($options),
            IpynbReader::class => new IpynbReader(),
            JiraReader::class => new JiraReader(),
            LegacyDocReader::class => new LegacyDocReader(),
            ManReader::class => new ManReader(),
            MarkdownReader::class => new MarkdownReader(self::markdownReaderOptions($format, $options)),
            MediaWikiReader::class => new MediaWikiReader(),
            JsonReader::class => new JsonReader(),
            LatexReader::class => new LatexReader($options),
            NativeReader::class => new NativeReader(),
            MdocReader::class => new MdocReader(),
            OdtReader::class => new OdtReader(),
            OpmlReader::class => new OpmlReader($options),
            PdfReader::class => new PdfReader($options),
            PptxReader::class => new PptxReader(),
            RtfReader::class => new RtfReader(),
            RstReader::class => new RstReader(),
            XmlReader::class => new XmlReader($format, $options),
            XlsxReader::class => new XlsxReader(),
            default => throw new \InvalidArgumentException("Unsupported Pandoc reader implementation '{$implementation}'."),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string> $names
     */
    private static function stringReaderOption(array $options, array $names): ?string
    {
        foreach ($names as $name) {
            $value = $options[$name] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function writer(string $implementation, string $format, array $options): object
    {
        return match ($implementation) {
            DocxWriter::class => new DocxWriter($options),
            EpubWriter::class => new EpubWriter($options),
            HtmlWriter::class => new HtmlWriter($options),
            JsonWriter::class => new JsonWriter(),
            NativeWriter::class => new NativeWriter($options),
            OpmlWriter::class => new OpmlWriter($options),
            PlainWriter::class => new PlainWriter($options),
            PptxWriter::class => new PptxWriter($options),
            default => throw new \InvalidArgumentException("Unsupported Pandoc writer implementation '{$implementation}'."),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function markdownReaderOptions(string $format, array $options): array
    {
        if ($format === 'html') {
            return array_replace(['htmlNativeDivs' => true], $options);
        }

        if (!isset($options['format']) && !isset($options['variant'])) {
            $options['format'] = $format;
        }

        return $options;
    }

}
