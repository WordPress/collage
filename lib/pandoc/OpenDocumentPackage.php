<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpenDocumentPackage
{
    public const TEXT_MIMETYPE = 'application/vnd.oasis.opendocument.text';
    public const MANIFEST_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    public const OFFICE_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    public const TEXT_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    public const STYLE_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    public const DRAW_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    public const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    public const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    public const META_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
    public const CONFIG_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:config:1.0';
    public const RDF_NAMESPACE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    public const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';
    private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';
    private const MANIFEST_FILE_ENTRY_STRUCTURAL_ATTRIBUTES = [
        'full-path' => true,
        'media-type' => true,
        'preferred-view-mode' => true,
        'size' => true,
        'version' => true,
    ];
    private const MANIFEST_FILE_ENTRY_STRUCTURAL_CHILD_ELEMENTS = [
        'encryption-data' => true,
    ];
    private const MANIFEST_ROOT_STRUCTURAL_ATTRIBUTES = [
        'version' => true,
    ];
    private const OFFICE_DOCUMENT_ROOT_STRUCTURAL_ATTRIBUTES = [
        'version' => true,
    ];
    private const PREFERRED_VIEW_MODE_VALUES = [
        'edit' => true,
        'presentation-slide-show' => true,
        'read-only' => true,
    ];
    private const MANIFEST_DECLARED_SIZE_LARGEST_ITEM_LIMIT = 5;

    /** @var array<string, array<string, mixed>> */
    private array $manifestEntriesByPath;

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param array<string, array<string, mixed>> $manifestEntriesByPath
     * @param array<string, array{name:string, family:string, parent:string|null, displayName:string|null}> $stylesByName
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $rdfMetadata
     * @param array<string, mixed> $manifestRootExtensionElements
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly ?string $manifestVersion,
        private readonly array $manifestRootAttributes,
        private readonly array $manifestRootExtensionElements,
        private readonly array $manifestEntries,
        array $manifestEntriesByPath,
        private readonly array $stylesByName,
        private readonly array $metadata,
        private readonly array $settings,
        private readonly array $rdfMetadata,
    ) {
        $this->manifestEntriesByPath = $manifestEntriesByPath;
    }

    public static function fromPackage(ZipPackage $package): self
    {
        self::assertTextPackageMimetype($package);

        if (!$package->has('META-INF/manifest.xml')) {
            throw new \RuntimeException('ODT package is missing META-INF/manifest.xml');
        }

        $manifest = self::parseManifest($package->read('META-INF/manifest.xml'));
        $manifestEntries = self::withPackageEntryMetadata($manifest['entries'], $package);
        $manifestEntriesByPath = [];
        $manifestEntriesByPackagePath = [];
        foreach ($manifestEntries as $entry) {
            if (isset($manifestEntriesByPath[$entry['path']])) {
                throw new \InvalidArgumentException('Duplicate ODF manifest full-path: ' . $entry['path']);
            }

            if (is_string($entry['packagePath'] ?? null) && $entry['packagePath'] !== '') {
                if (isset($manifestEntriesByPackagePath[$entry['packagePath']])) {
                    throw new \InvalidArgumentException('Duplicate ODF manifest package part: ' . $entry['packagePath']);
                }

                $manifestEntriesByPackagePath[$entry['packagePath']] = true;
            }

            $manifestEntriesByPath[$entry['path']] = $entry;
            if (is_string($entry['pathReference'] ?? null) && $entry['pathReference'] !== '' && !isset($manifestEntriesByPath[$entry['pathReference']])) {
                $manifestEntriesByPath[$entry['pathReference']] = $entry;
            }
            if (is_string($entry['packagePath'] ?? null) && !isset($manifestEntriesByPath[$entry['packagePath']])) {
                $manifestEntriesByPath[$entry['packagePath']] = $entry;
            }
        }

        $root = $manifestEntriesByPath['/'] ?? null;
        if ($root === null || $root['mediaType'] !== self::TEXT_MIMETYPE) {
            throw new \RuntimeException('ODT manifest root must identify an OpenDocument text package');
        }

        foreach (['content.xml'] as $requiredPart) {
            if (!isset($manifestEntriesByPath[$requiredPart])) {
                throw new \RuntimeException("ODT manifest is missing required part {$requiredPart}");
            }
            if (!$package->has($requiredPart)) {
                throw new \RuntimeException("ODT package is missing manifest-declared part {$requiredPart}");
            }
        }

        foreach (['styles.xml', 'meta.xml', 'settings.xml'] as $optionalPart) {
            if (isset($manifestEntriesByPath[$optionalPart]) && !$package->has($optionalPart)) {
                throw new \RuntimeException("ODT package is missing manifest-declared part {$optionalPart}");
            }
        }

        $styles = isset($manifestEntriesByPath['styles.xml']) ? self::parseStyles($package->read('styles.xml')) : [];
        $metadata = isset($manifestEntriesByPath['meta.xml']) ? self::parseMetadata($package->read('meta.xml')) : [];
        $settings = isset($manifestEntriesByPath['settings.xml']) ? self::parseSettings($package->read('settings.xml')) : self::emptySettings();
        $rdfMetadata = self::readRdfMetadata($package, $manifestEntries);

        return new self(
            $package,
            $manifest['version'],
            $manifest['rootAttributes'],
            $manifest['rootExtensionElements'],
            $manifestEntries,
            $manifestEntriesByPath,
            $styles,
            $metadata,
            $settings,
            $rdfMetadata
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function rawImportPreflight(
        string $bytes,
        ?int $maxTotalUncompressedBytes = null,
        ?float $maxExpansionRatio = null,
        ?int $maxEntryUncompressedBytes = null
    ): array {
        $zipPreflight = ZipPackage::rawStrictImportPreflight(
            $bytes,
            $maxTotalUncompressedBytes,
            $maxExpansionRatio,
            $maxEntryUncompressedBytes
        );
        $mimetypeEntry = self::rawMimetypeEntryPreflight($bytes);
        $zip64 = is_array($zipPreflight['zip64EndOfCentralDirectory'] ?? null)
            ? $zipPreflight['zip64EndOfCentralDirectory']
            : null;
        $rawCentralDirectoryExpansionRatio = self::rawCentralDirectoryExpansionRatioBucketPreflight($zipPreflight);
        $rawCentralDirectoryNameByteLength = self::rawCentralDirectoryNameByteLengthBucketPreflight($zipPreflight);
        $diagnostics = [];
        $addDiagnostic = static function (string $diagnostic) use (&$diagnostics): void {
            if (!in_array($diagnostic, $diagnostics, true)) {
                $diagnostics[] = $diagnostic;
            }
        };
        foreach (($zipPreflight['diagnostics'] ?? []) as $diagnostic) {
            if (is_string($diagnostic) && $diagnostic !== '') {
                $addDiagnostic($diagnostic);
            }
        }
        foreach (($mimetypeEntry['diagnostics'] ?? []) as $diagnostic) {
            if (is_string($diagnostic) && $diagnostic !== '') {
                $addDiagnostic($diagnostic);
            }
        }

        $zipPackage = null;
        $zipPackageInstantiationError = is_string($zipPreflight['instantiationError'] ?? null)
            ? $zipPreflight['instantiationError']
            : null;
        $canInstantiateZipPackage = false;
        if (($zipPreflight['canInstantiate'] ?? false) === true) {
            try {
                $zipPackage = ZipPackage::fromString($bytes);
                $canInstantiateZipPackage = true;
                $zipPackageInstantiationError = null;
            } catch (\RuntimeException $exception) {
                $zipPackageInstantiationError = $exception->getMessage();
                $addDiagnostic('zip-package-instantiation-failed');
            }
        } else {
            $addDiagnostic('zip-package-instantiation-failed');
        }

        $openDocumentPackageInstantiationError = null;
        $canInstantiateOpenDocumentPackage = false;
        $manifestVersion = null;
        $manifestEntryCount = null;
        if ($zipPackage instanceof ZipPackage) {
            try {
                $openDocumentPackage = self::fromPackage($zipPackage);
                $canInstantiateOpenDocumentPackage = true;
                $manifestVersion = $openDocumentPackage->manifestVersion();
                $manifestEntryCount = count($openDocumentPackage->manifestEntries());
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                $openDocumentPackageInstantiationError = $exception->getMessage();
                $addDiagnostic('odf-package-instantiation-failed');
            }
        } else {
            $openDocumentPackageInstantiationError = $zipPackageInstantiationError
                ?? 'ZIP package could not be instantiated';
            $addDiagnostic('odf-package-instantiation-failed');
        }

        return [
            'format' => 'odf',
            'packageByteLength' => strlen($bytes),
            'entryCount' => (int) ($zipPreflight['entryCount'] ?? 0),
            'isValid' => $canInstantiateOpenDocumentPackage
                && ($zipPreflight['isValid'] ?? false) === true
                && ($mimetypeEntry['matchesOpenDocumentText'] ?? false) === true,
            'isOpenDocumentTextPackage' => $canInstantiateOpenDocumentPackage
                || ($mimetypeEntry['matchesOpenDocumentText'] ?? false) === true,
            'canInstantiateZipPackage' => $canInstantiateZipPackage,
            'zipPackageInstantiationError' => $zipPackageInstantiationError,
            'canInstantiateOpenDocumentPackage' => $canInstantiateOpenDocumentPackage,
            'openDocumentPackageInstantiationError' => $openDocumentPackageInstantiationError,
            'manifestVersion' => $manifestVersion,
            'manifestEntryCount' => $manifestEntryCount,
            'mimetypeEntry' => $mimetypeEntry,
            'requiresZip64' => $zip64 !== null && ($zip64['requiresZip64'] ?? false) === true,
            'hasZip64EndOfCentralDirectoryLocator' => $zip64 !== null
                && ($zip64['hasZip64EndOfCentralDirectoryLocator'] ?? false) === true,
            'hasZip64EndOfCentralDirectory' => $zip64 !== null
                && ($zip64['hasZip64EndOfCentralDirectory'] ?? false) === true,
            'zip64EndOfCentralDirectoryIssueCodes' => $zip64['issues'] ?? [],
            'zip64EndOfCentralDirectory' => $zip64,
            'rawCentralDirectoryExpansionRatioBucketSummaryCount' => $rawCentralDirectoryExpansionRatio['summaryCount'],
            'rawCentralDirectoryExpansionRatioBuckets' => $rawCentralDirectoryExpansionRatio['buckets'],
            'rawCentralDirectoryExpansionRatioBucketSummaries' => $rawCentralDirectoryExpansionRatio['summaries'],
            'rawCentralDirectoryExpansionRatioUnknownEntryCount' => $rawCentralDirectoryExpansionRatio['unknownEntryCount'],
            'rawCentralDirectoryExpansionRatioEntryCount' => $rawCentralDirectoryExpansionRatio['entryCount'],
            'rawCentralDirectoryExpansionRatioByteExposurePolicy' => 'odf-raw-central-directory-expansion-ratio-metadata-only',
            'rawCentralDirectoryExpansionRatioCanExposeBytes' => false,
            'rawCentralDirectoryNameByteLengthBucketSummaryCount' => $rawCentralDirectoryNameByteLength['summaryCount'],
            'rawCentralDirectoryNameByteLengthBuckets' => $rawCentralDirectoryNameByteLength['buckets'],
            'rawCentralDirectoryNameByteLengthBucketCounts' => $rawCentralDirectoryNameByteLength['bucketCounts'],
            'rawCentralDirectoryNameByteLengthBucketSummaries' => $rawCentralDirectoryNameByteLength['summaries'],
            'rawCentralDirectoryNameByteLengthEntryCount' => $rawCentralDirectoryNameByteLength['entryCount'],
            'rawCentralDirectoryNameByteLengthRawBytes' => $rawCentralDirectoryNameByteLength['rawNameBytes'],
            'rawCentralDirectoryNameByteLengthDecodedBytes' => $rawCentralDirectoryNameByteLength['decodedNameBytes'],
            'rawCentralDirectoryNameByteLengthDecodedNameDiffersFromRawNameCount' => $rawCentralDirectoryNameByteLength['decodedNameDiffersFromRawNameCount'],
            'rawCentralDirectoryNameByteLengthByteExposurePolicy' => 'odf-raw-central-directory-name-byte-length-metadata-only',
            'rawCentralDirectoryNameByteLengthCanExposeBytes' => false,
            'zipRawStrictImport' => $zipPreflight,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'byteExposurePolicy' => 'odf-raw-package-import-metadata-only',
            'canExposeBytes' => false,
        ];
    }

    /**
     * @param array<string, mixed> $zipPreflight
     * @return array{
     *     summaryCount:int,
     *     buckets:list<string>,
     *     bucketCounts:array<string, int>,
     *     summaries:list<array<string, mixed>>,
     *     entryCount:int,
     *     rawNameBytes:int,
     *     decodedNameBytes:int,
     *     decodedNameDiffersFromRawNameCount:int
     * }
     */
    private static function rawCentralDirectoryNameByteLengthBucketPreflight(array $zipPreflight): array
    {
        $centralDirectory = is_array($zipPreflight['centralDirectorySize'] ?? null)
            ? $zipPreflight['centralDirectorySize']
            : [];
        $entries = is_array($centralDirectory['entries'] ?? null) ? $centralDirectory['entries'] : [];
        $summaries = [];
        $entryCount = 0;
        $rawNameBytes = 0;
        $decodedNameBytes = 0;
        $decodedNameDiffersFromRawNameCount = 0;

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
            if ($name === '') {
                continue;
            }

            $rawName = is_string($entry['rawName'] ?? null) ? $entry['rawName'] : $name;
            $rawLength = strlen($rawName);
            $decodedLength = strlen($name);
            $bucket = self::packagePathByteLengthBucket($rawLength);
            $bucketKey = $bucket['packagePathByteLengthBucket'];
            if (!isset($summaries[$bucketKey])) {
                $summaries[$bucketKey] = [
                    'rawCentralDirectoryNameByteLengthBucket' => $bucketKey,
                    'minRawCentralDirectoryNameByteLength' => $bucket['minPackagePathByteLength'],
                    'maxRawCentralDirectoryNameByteLength' => $bucket['maxPackagePathByteLength'],
                    'entryCount' => 0,
                    'fileEntryCount' => 0,
                    'directoryEntryCount' => 0,
                    'rawNameBytes' => 0,
                    'decodedNameBytes' => 0,
                    'centralDirectoryRecordBytes' => 0,
                    'decodedNameDiffersFromRawNameCount' => 0,
                    'directoryRoots' => [],
                    'compressionMethodNames' => [],
                    'entryNames' => [],
                    'longestRawNameEntryName' => null,
                    'longestRawNameByteLength' => 0,
                ];
            }

            ++$entryCount;
            ++$summaries[$bucketKey]['entryCount'];
            $summaries[$bucketKey]['rawNameBytes'] += $rawLength;
            $summaries[$bucketKey]['decodedNameBytes'] += $decodedLength;
            $rawNameBytes += $rawLength;
            $decodedNameBytes += $decodedLength;
            $recordOffset = is_int($entry['centralDirectoryOffset'] ?? null) ? $entry['centralDirectoryOffset'] : null;
            $recordEnd = is_int($entry['recordEnd'] ?? null) ? $entry['recordEnd'] : null;
            if ($recordOffset !== null && $recordEnd !== null && $recordEnd >= $recordOffset) {
                $summaries[$bucketKey]['centralDirectoryRecordBytes'] += $recordEnd - $recordOffset;
            }
            if (($entry['isDirectory'] ?? false) === true) {
                ++$summaries[$bucketKey]['directoryEntryCount'];
            } else {
                ++$summaries[$bucketKey]['fileEntryCount'];
            }
            if ($rawName !== $name) {
                ++$summaries[$bucketKey]['decodedNameDiffersFromRawNameCount'];
                ++$decodedNameDiffersFromRawNameCount;
            }
            $summaries[$bucketKey]['entryNames'][] = $name;

            $directoryRoot = self::packageDirectoryRoot($name);
            if (!in_array($directoryRoot, $summaries[$bucketKey]['directoryRoots'], true)) {
                $summaries[$bucketKey]['directoryRoots'][] = $directoryRoot;
            }
            $compressionMethodName = is_string($entry['compressionMethodName'] ?? null)
                ? $entry['compressionMethodName']
                : '';
            if ($compressionMethodName !== '' && !in_array($compressionMethodName, $summaries[$bucketKey]['compressionMethodNames'], true)) {
                $summaries[$bucketKey]['compressionMethodNames'][] = $compressionMethodName;
            }
            if (
                $rawLength > $summaries[$bucketKey]['longestRawNameByteLength']
                || (
                    $rawLength === $summaries[$bucketKey]['longestRawNameByteLength']
                    && (
                        $summaries[$bucketKey]['longestRawNameEntryName'] === null
                        || strcmp($name, (string) $summaries[$bucketKey]['longestRawNameEntryName']) < 0
                    )
                )
            ) {
                $summaries[$bucketKey]['longestRawNameEntryName'] = $name;
                $summaries[$bucketKey]['longestRawNameByteLength'] = $rawLength;
            }
        }

        foreach ($summaries as &$summary) {
            sort($summary['directoryRoots'], SORT_STRING);
            sort($summary['compressionMethodNames'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
        }
        unset($summary);

        $ordered = [];
        $bucketCounts = [];
        foreach (['up-to-8-bytes', '9-to-16-bytes', '17-to-32-bytes', '33-to-64-bytes', 'over-64-bytes'] as $bucket) {
            if (isset($summaries[$bucket])) {
                $ordered[] = $summaries[$bucket];
                $bucketCounts[$bucket] = $summaries[$bucket]['entryCount'];
            }
        }

        return [
            'summaryCount' => count($ordered),
            'buckets' => array_keys($bucketCounts),
            'bucketCounts' => $bucketCounts,
            'summaries' => $ordered,
            'entryCount' => $entryCount,
            'rawNameBytes' => $rawNameBytes,
            'decodedNameBytes' => $decodedNameBytes,
            'decodedNameDiffersFromRawNameCount' => $decodedNameDiffersFromRawNameCount,
        ];
    }

    /**
     * @param array<string, mixed> $zipPreflight
     * @return array{
     *     summaryCount:int,
     *     buckets:list<string>,
     *     summaries:list<array<string, mixed>>,
     *     unknownEntryCount:int,
     *     entryCount:int
     * }
     */
    private static function rawCentralDirectoryExpansionRatioBucketPreflight(array $zipPreflight): array
    {
        $centralDirectory = is_array($zipPreflight['centralDirectorySize'] ?? null)
            ? $zipPreflight['centralDirectorySize']
            : [];
        $entries = is_array($centralDirectory['entries'] ?? null) ? $centralDirectory['entries'] : [];
        $summaries = [];
        $entryCount = 0;
        $unknownEntryCount = 0;

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
            if ($name === '') {
                continue;
            }

            ++$entryCount;
            $compressedSize = is_int($entry['compressedSize'] ?? null) ? $entry['compressedSize'] : 0;
            $uncompressedSize = is_int($entry['uncompressedSize'] ?? null) ? $entry['uncompressedSize'] : 0;
            $expansionRatio = is_float($entry['expansionRatio'] ?? null) || is_int($entry['expansionRatio'] ?? null)
                ? (float) $entry['expansionRatio']
                : null;
            $bucket = self::zipPackageManifestExpansionRatioBucket($expansionRatio);
            $bucketKey = $bucket['expansionRatioBucket'];
            if (!isset($summaries[$bucketKey])) {
                $summaries[$bucketKey] = [
                    'expansionRatioBucket' => $bucket['expansionRatioBucket'],
                    'minExpansionRatio' => $bucket['minExpansionRatio'],
                    'maxExpansionRatio' => $bucket['maxExpansionRatio'],
                    'entryCount' => 0,
                    'fileEntryCount' => 0,
                    'directoryEntryCount' => 0,
                    'unknownExpansionRatioEntryCount' => 0,
                    'compressedBytes' => 0,
                    'uncompressedBytes' => 0,
                    'directoryRoots' => [],
                    'compressionMethodNames' => [],
                    'entryNames' => [],
                    'largestExpansionRatioEntryName' => null,
                    'largestExpansionRatio' => null,
                ];
            }

            ++$summaries[$bucketKey]['entryCount'];
            if (($entry['isDirectory'] ?? false) === true) {
                ++$summaries[$bucketKey]['directoryEntryCount'];
            } else {
                ++$summaries[$bucketKey]['fileEntryCount'];
            }
            if ($expansionRatio === null) {
                ++$summaries[$bucketKey]['unknownExpansionRatioEntryCount'];
                ++$unknownEntryCount;
            }

            $summaries[$bucketKey]['compressedBytes'] += $compressedSize;
            $summaries[$bucketKey]['uncompressedBytes'] += $uncompressedSize;
            $summaries[$bucketKey]['entryNames'][] = $name;

            $directoryRoot = self::packageDirectoryRoot($name);
            if (!in_array($directoryRoot, $summaries[$bucketKey]['directoryRoots'], true)) {
                $summaries[$bucketKey]['directoryRoots'][] = $directoryRoot;
            }
            $compressionMethodName = is_string($entry['compressionMethodName'] ?? null)
                ? $entry['compressionMethodName']
                : '';
            if ($compressionMethodName !== '' && !in_array($compressionMethodName, $summaries[$bucketKey]['compressionMethodNames'], true)) {
                $summaries[$bucketKey]['compressionMethodNames'][] = $compressionMethodName;
            }

            if (
                $expansionRatio !== null
                && (
                    !is_float($summaries[$bucketKey]['largestExpansionRatio'])
                    || $expansionRatio > $summaries[$bucketKey]['largestExpansionRatio']
                )
            ) {
                $summaries[$bucketKey]['largestExpansionRatioEntryName'] = $name;
                $summaries[$bucketKey]['largestExpansionRatio'] = $expansionRatio;
            }
        }

        foreach ($summaries as &$summary) {
            sort($summary['directoryRoots'], SORT_STRING);
            sort($summary['compressionMethodNames'], SORT_STRING);
        }
        unset($summary);

        $ordered = [];
        foreach (['zero-byte', 'up-to-1x', '1x-to-10x', '10x-to-100x', 'over-100x', 'unknown'] as $bucket) {
            if (isset($summaries[$bucket])) {
                $ordered[] = $summaries[$bucket];
            }
        }

        return [
            'summaryCount' => count($ordered),
            'buckets' => array_map(
                static fn (array $summary): string => (string) $summary['expansionRatioBucket'],
                $ordered
            ),
            'summaries' => $ordered,
            'unknownEntryCount' => $unknownEntryCount,
            'entryCount' => $entryCount,
        ];
    }

    public function package(): ZipPackage
    {
        return $this->package;
    }

    public function manifestVersion(): ?string
    {
        return $this->manifestVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function manifestRootAttributes(): array
    {
        return $this->manifestRootAttributes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function manifestEntries(): array
    {
        return $this->manifestEntries;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function manifestEntry(string $path): ?array
    {
        return $this->manifestEntriesByPath[self::normalizeManifestPath($path)] ?? null;
    }

    public function mediaTypeForPath(string $path): ?string
    {
        return $this->manifestEntry($path)['mediaType'] ?? null;
    }

    /**
     * @return array<string, array{name:string, family:string, parent:string|null, displayName:string|null}>
     */
    public function stylesByName(): array
    {
        return $this->stylesByName;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function rdfMetadata(): array
    {
        return $this->rdfMetadata;
    }

    public function readContentDocument(): AstNode
    {
        $dom = self::loadXml($this->package->read('content.xml'), 'ODT content.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !in_array($root->localName, ['document-content', 'document'], true) || $root->namespaceURI !== self::OFFICE_NAMESPACE) {
            throw new \InvalidArgumentException('ODT content.xml must use an office:document-content or office:document root');
        }

        $body = self::firstElementByPath($root, [
            [self::OFFICE_NAMESPACE, 'body'],
            [self::OFFICE_NAMESPACE, 'text'],
        ]);
        if (!$body instanceof \DOMElement) {
            throw new \InvalidArgumentException('ODT content.xml is missing office:body/office:text');
        }

        $blocks = [];
        foreach ($body->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'h') {
                $blocks[] = new AstNode('heading', [
                    'level' => self::intAttribute($child, self::TEXT_NAMESPACE, 'outline-level', 1),
                    'text' => self::plainText($child),
                    'styleName' => self::namespacedAttribute($child, self::TEXT_NAMESPACE, 'style-name'),
                ], self::inlineNodes($child));
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'p') {
                $inlines = self::inlineNodes($child);
                if ($inlines !== []) {
                    $blocks[] = new AstNode('paragraph', [
                        'text' => self::plainText($child),
                        'styleName' => self::namespacedAttribute($child, self::TEXT_NAMESPACE, 'style-name'),
                    ], $inlines);
                }
            }
        }

        return new AstNode('document', [
            'format' => 'odt',
            'metadata' => $this->metadata,
            'styles' => $this->stylesByName,
            'settings' => $this->settings,
            'rdfMetadata' => $this->rdfMetadata,
        ], $blocks);
    }

    /**
     * @return array{
     *     mimetype:string,
     *     mimetypeEntry:array<string, mixed>,
     *     manifestVersion:string|null,
     *     contentXml:bool,
     *     stylesXml:bool,
     *     metaXml:bool,
     *     settingsXml:bool,
     *     mediaParts:list<array<string, mixed>>,
     *     missingMediaPartCount:int,
     *     missingMediaParts:list<array{path:string, mediaType:string}>,
     *     exposableMediaPartCount:int,
     *     encryptedCount:int,
     *     encryptedParts:list<string>,
     *     manifestMediaTypeSummary:array<string, mixed>,
     *     manifestReview:array<string, mixed>,
     *     packageInventory:array<string, mixed>,
     *     packageByteHandoff:array<string, mixed>,
     *     packageIdentity:array<string, mixed>,
     *     undeclaredPackageEntryCount:int,
     *     undeclaredPackageEntries:list<array<string, mixed>>,
     *     packageThumbnails:array<string, mixed>,
     *     packageSignatures:array<string, mixed>,
     *     packageObjects:array<string, mixed>,
     *     packageObjectReplacements:array<string, mixed>,
     *     packageScripts:array<string, mixed>,
     *     packageConfigurations:array<string, mixed>,
     *     packageFonts:array<string, mixed>,
     *     packageLayoutCaches:array<string, mixed>,
     *     documentParts:array<string, mixed>,
     *     packageStyles:array<string, mixed>,
     *     comments:array<string, mixed>,
     *     rdfMetadata:array<string, mixed>,
     *     metadata:array<string, mixed>,
     *     settings:array<string, mixed>,
     *     styleNames:list<string>,
     *     contentBlocks:int
     * }
     */
    public function summarize(): array
    {
        $packageInventory = $this->packageInventory();
        $mediaParts = [];
        $missingMediaParts = [];
        $exposableMediaPartCount = 0;
        $encryptedParts = [];
        $undeclaredPackageEntries = $this->undeclaredPackageEntries();
        $packageThumbnails = self::packageThumbnailMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageSignatures = self::packageSignatureMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageObjects = self::embeddedObjectPackageMetadata($this->package, $this->manifestEntries);
        $packageObjectReplacements = self::packageObjectReplacementMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageScripts = self::packageScriptMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageConfigurations = self::packageConfigurationMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageFonts = self::packageFontMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageLayoutCaches = self::packageLayoutCacheMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $documentParts = $this->documentPartPackageProvenance($packageInventory);
        $packageStyles = $this->packageStyleProvenance($packageInventory);
        $manifestMediaTypeSummary = self::manifestMediaTypeSummary($this->manifestEntries);
        foreach ($this->manifestEntries as $entry) {
            if (self::isMediaResourceManifestEntry($entry)) {
                $mediaParts[] = [
                    'path' => $entry['path'],
                    'packagePath' => $entry['packagePath'],
                    'pathReference' => $entry['pathReference'],
                    'pathSuffix' => $entry['pathSuffix'],
                    'pathQuery' => $entry['pathQuery'],
                    'pathFragment' => $entry['pathFragment'],
                    'pathShape' => $entry['pathShape'],
                    'packagePathShape' => $entry['packagePathShape'],
                    'uriEncodedPackageReference' => ($entry['uriEncodedPackageReference'] ?? false) === true,
                    'manifestMediaFamily' => $entry['manifestMediaFamily'],
                    'mediaType' => $entry['mediaType'],
                    'mediaTypeBase' => $entry['mediaTypeBase'],
                    'mediaTypeHasParameters' => $entry['mediaTypeHasParameters'],
                    'mediaTypeParameterCount' => $entry['mediaTypeParameterCount'],
                    'mediaTypeParameters' => $entry['mediaTypeParameters'],
                    'mediaTypeParameterMap' => $entry['mediaTypeParameterMap'],
                    'version' => $entry['version'],
                    'preferredViewMode' => $entry['preferredViewMode'],
                    'exists' => $entry['exists'],
                    'byteLength' => $entry['byteLength'],
                    'storedByteLength' => $entry['storedByteLength'],
                    'compressedByteLength' => $entry['compressedByteLength'],
                    'compressionMethod' => $entry['compressionMethod'],
                    'compressionMethodName' => $entry['compressionMethodName'],
                    'crc32' => $entry['crc32'],
                    'storedCrc32' => $entry['storedCrc32'],
                    'byteSha256' => $entry['byteSha256'],
                    'zipModifiedAt' => $entry['zipModifiedAt'],
                    'zipTimestampSource' => $entry['zipTimestampSource'],
                    'zipModifiedDosTime' => $entry['zipModifiedDosTime'],
                    'zipModifiedDosDate' => $entry['zipModifiedDosDate'],
                    'zipHasDosTimestamp' => $entry['zipHasDosTimestamp'],
                    'zipIsDosTimestampValid' => $entry['zipIsDosTimestampValid'],
                    'zipDosModifiedAt' => $entry['zipDosModifiedAt'],
                    'zipExtendedModifiedAt' => $entry['zipExtendedModifiedAt'],
                    'zipNtfsModifiedAt' => $entry['zipNtfsModifiedAt'],
                    'zipLocalModifiedAt' => $entry['zipLocalModifiedAt'],
                    'zipLocalTimestampSource' => $entry['zipLocalTimestampSource'],
                    'zipTimestampIssues' => $entry['zipTimestampIssues'],
                    'declaredSize' => $entry['size'],
                    'declaredSizeMismatch' => $entry['declaredSizeMismatch'],
                    'missingMediaType' => ($entry['missingMediaType'] ?? false) === true,
                    'encrypted' => $entry['encrypted'],
                    'encryption' => $entry['encryption'],
                    'encryptionRecordCount' => is_array($entry['encryption']) ? ($entry['encryption']['recordCount'] ?? 0) : 0,
                    'encryptionIssueCodes' => is_array($entry['encryption']) ? ($entry['encryption']['issueCodes'] ?? []) : [],
                    'canExposeBytes' => $entry['canExposeBytes'],
                    'byteExposurePolicy' => $entry['byteExposurePolicy'],
                    'diagnostics' => $entry['diagnostics'],
                ];
                if (!$entry['exists']) {
                    $missingMediaParts[] = [
                        'path' => $entry['path'],
                        'mediaType' => $entry['mediaType'],
                    ];
                }
                if ($entry['canExposeBytes']) {
                    ++$exposableMediaPartCount;
                }
            }
            if ($entry['encrypted']) {
                $encryptedParts[] = $entry['path'];
            }
        }

        return [
            'mimetype' => self::TEXT_MIMETYPE,
            'mimetypeEntry' => self::mimetypeEntryReview($this->package),
            'manifestVersion' => $this->manifestVersion,
            'manifestRootAttributes' => $this->manifestRootAttributes,
            'manifestRootExtensionElements' => $this->manifestRootExtensionElements,
            'manifestPackageCoverage' => $packageInventory['manifestPackageCoverage'],
            'contentXml' => isset($this->manifestEntriesByPath['content.xml']),
            'stylesXml' => isset($this->manifestEntriesByPath['styles.xml']),
            'metaXml' => isset($this->manifestEntriesByPath['meta.xml']),
            'settingsXml' => isset($this->manifestEntriesByPath['settings.xml']),
            'mediaParts' => $mediaParts,
            'missingMediaPartCount' => count($missingMediaParts),
            'missingMediaParts' => $missingMediaParts,
            'exposableMediaPartCount' => $exposableMediaPartCount,
            'encryptedCount' => count($encryptedParts),
            'encryptedParts' => $encryptedParts,
            'undeclaredPackageEntryCount' => count($undeclaredPackageEntries),
            'undeclaredPackageEntries' => $undeclaredPackageEntries,
            'packageThumbnails' => $packageThumbnails,
            'packageSignatures' => $packageSignatures,
            'packageObjects' => $packageObjects,
            'packageObjectReplacements' => $packageObjectReplacements,
            'packageScripts' => $packageScripts,
            'packageConfigurations' => $packageConfigurations,
            'packageFonts' => $packageFonts,
            'packageLayoutCaches' => $packageLayoutCaches,
            'documentParts' => $documentParts,
            'packageStyles' => $packageStyles,
            'rdfMetadata' => $this->rdfMetadata,
            'manifestEncryption' => self::manifestEncryptionSummary($this->manifestEntries),
            'manifestMediaTypeSummary' => $manifestMediaTypeSummary,
            'manifestReview' => self::manifestReview(
                $this->manifestEntries,
                $undeclaredPackageEntries,
                $this->manifestRootAttributes,
                $this->manifestRootExtensionElements
            ),
            'packageInventory' => $packageInventory,
            'packageByteHandoff' => $packageInventory['packageByteHandoff'],
            'packageIdentity' => $this->packageIdentity($packageInventory, $manifestMediaTypeSummary),
            'metadata' => $this->metadata,
            'settings' => $this->settings,
            'styleNames' => array_keys($this->stylesByName),
            'contentBlocks' => count($this->readContentDocument()->children),
        ];
    }

    /**
     * @param array<string, mixed> $packageInventory
     * @return array<string, mixed>
     */
    private function documentPartPackageProvenance(array $packageInventory): array
    {
        $expectedRoots = [
            'content.xml' => 'document-content',
            'styles.xml' => 'document-styles',
            'meta.xml' => 'document-meta',
            'settings.xml' => 'document-settings',
        ];
        $parts = is_array($packageInventory['parts'] ?? null) ? $packageInventory['parts'] : [];
        $items = [];
        $versionCounts = [];
        $missingVersionParts = [];
        $versionMismatches = [];
        $rootCustomAttributeCount = 0;
        $rootCustomAttributeNames = [];
        $rootCustomAttributeItems = [];
        $diagnosticCodeCounts = [];
        $diagnosticCount = 0;
        $declaredCount = 0;
        $packagePartCount = 0;
        $missingPackagePartCount = 0;
        $undeclaredPackagePartCount = 0;

        foreach ($expectedRoots as $part => $expectedRoot) {
            $manifestEntry = $this->manifestEntriesByPath[$part] ?? null;
            $inventoryPart = is_array($parts[$part] ?? null) ? $parts[$part] : null;
            if (!is_array($manifestEntry) && !is_array($inventoryPart)) {
                continue;
            }

            $declaredInManifest = is_array($manifestEntry);
            if ($declaredInManifest) {
                ++$declaredCount;
            }
            if (is_array($inventoryPart)) {
                ++$packagePartCount;
            }

            $diagnostics = [];
            $rootName = null;
            $rootNamespace = null;
            $validRoot = false;
            $officeVersion = null;
            $rootAttributeProvenance = self::emptyAttributeProvenance();

            if (!is_array($inventoryPart)) {
                ++$missingPackagePartCount;
                $diagnostics[] = 'odf-document-part-missing-package-part';
            } elseif (!$declaredInManifest) {
                ++$undeclaredPackagePartCount;
                $diagnostics[] = 'odf-document-part-undeclared-package-part';
            } else {
                $dom = self::loadXml($this->package->read($part), 'ODT ' . $part);
                $root = $dom->documentElement;
                if ($root instanceof \DOMElement) {
                    $rootName = $root->localName;
                    $rootNamespace = $root->namespaceURI;
                    $validRoot = $root->namespaceURI === self::OFFICE_NAMESPACE
                        && in_array($root->localName, [$expectedRoot, 'document'], true);
                    $officeVersion = self::optionalString(self::namespacedAttribute($root, self::OFFICE_NAMESPACE, 'version'));
                    $rootAttributeProvenance = self::documentPartRootAttributeProvenance($root);
                }

                if (!$validRoot) {
                    $diagnostics[] = 'odf-document-part-unexpected-root';
                }
                if ($officeVersion === null) {
                    $diagnostics[] = 'odf-document-part-missing-office-version';
                    $missingVersionParts[] = $part;
                } else {
                    $versionCounts[$officeVersion] = ($versionCounts[$officeVersion] ?? 0) + 1;
                    if ($this->manifestVersion !== null && $officeVersion !== $this->manifestVersion) {
                        $diagnostics[] = 'odf-document-part-version-mismatch';
                        $versionMismatches[] = [
                            'part' => $part,
                            'officeVersion' => $officeVersion,
                            'manifestVersion' => $this->manifestVersion,
                        ];
                    }
                }
            }

            $partCustomAttributeCount = (int) ($rootAttributeProvenance['customAttributeCount'] ?? 0);
            if ($partCustomAttributeCount > 0) {
                $rootCustomAttributeCount += $partCustomAttributeCount;
                foreach ($rootAttributeProvenance['customAttributeNames'] ?? [] as $attributeName) {
                    if (is_string($attributeName) && $attributeName !== '' && !in_array($attributeName, $rootCustomAttributeNames, true)) {
                        $rootCustomAttributeNames[] = $attributeName;
                    }
                }
                $rootCustomAttributeItems[] = [
                    'part' => $part,
                    'expectedRoot' => $expectedRoot,
                    'rootName' => $rootName,
                    'rootCustomAttributeCount' => $partCustomAttributeCount,
                    'rootCustomAttributeNames' => $rootAttributeProvenance['customAttributeNames'] ?? [],
                    'rootCustomAttributes' => $rootAttributeProvenance['customAttributes'] ?? [],
                    'rootCustomAttributeMap' => $rootAttributeProvenance['customAttributeMap'] ?? [],
                    'rootNamespaceDeclarationCount' => $rootAttributeProvenance['namespaceDeclarationCount'] ?? 0,
                    'rootNamespaceDeclarationNames' => $rootAttributeProvenance['namespaceDeclarationNames'] ?? [],
                    'rootNamespaceDeclarations' => $rootAttributeProvenance['namespaceDeclarations'] ?? [],
                    'rootNamespaceDeclarationMap' => $rootAttributeProvenance['namespaceDeclarationMap'] ?? [],
                ];
            }

            $diagnostics = array_values(array_unique($diagnostics));
            foreach ($diagnostics as $diagnostic) {
                $diagnosticCodeCounts[$diagnostic] = ($diagnosticCodeCounts[$diagnostic] ?? 0) + 1;
            }
            $diagnosticCount += count($diagnostics);

            $items[] = [
                'part' => $part,
                'expectedRoot' => $expectedRoot,
                'acceptedRootNames' => [$expectedRoot, 'document'],
                'rootName' => $rootName,
                'rootNamespace' => $rootNamespace,
                'validRoot' => $validRoot,
                'officeVersion' => $officeVersion,
                'manifestVersion' => $this->manifestVersion,
                'declaredInManifest' => $declaredInManifest,
                'manifestIndex' => is_array($manifestEntry) ? ($manifestEntry['manifestIndex'] ?? null) : null,
                'manifestPath' => is_array($manifestEntry) ? ($manifestEntry['path'] ?? null) : null,
                'manifestMediaType' => is_array($manifestEntry) ? ($manifestEntry['mediaType'] ?? null) : null,
                'exists' => is_array($inventoryPart),
                'storedByteLength' => is_array($inventoryPart) ? ($inventoryPart['byteLength'] ?? null) : null,
                'compressedByteLength' => is_array($inventoryPart) ? ($inventoryPart['compressedByteLength'] ?? null) : null,
                'compressionMethod' => is_array($inventoryPart) ? ($inventoryPart['compressionMethod'] ?? null) : null,
                'compressionMethodName' => is_array($inventoryPart) ? ($inventoryPart['compressionMethodName'] ?? null) : null,
                'crc32' => is_array($inventoryPart) ? ($inventoryPart['crc32'] ?? null) : null,
                'packageByteExposurePolicy' => is_array($inventoryPart) ? ($inventoryPart['byteExposurePolicy'] ?? null) : null,
                'byteExposurePolicy' => 'odf-document-part-package-provenance-metadata-only',
                'canExposeBytes' => false,
                'rootAttributeCount' => $rootAttributeProvenance['attributeCount'] ?? 0,
                'rootAttributeNames' => $rootAttributeProvenance['attributeNames'] ?? [],
                'rootAttributes' => $rootAttributeProvenance['attributes'] ?? [],
                'rootCustomAttributeCount' => $rootAttributeProvenance['customAttributeCount'] ?? 0,
                'rootCustomAttributeNames' => $rootAttributeProvenance['customAttributeNames'] ?? [],
                'rootCustomAttributes' => $rootAttributeProvenance['customAttributes'] ?? [],
                'rootCustomAttributeMap' => $rootAttributeProvenance['customAttributeMap'] ?? [],
                'rootNamespaceDeclarationCount' => $rootAttributeProvenance['namespaceDeclarationCount'] ?? 0,
                'rootNamespaceDeclarationNames' => $rootAttributeProvenance['namespaceDeclarationNames'] ?? [],
                'rootNamespaceDeclarations' => $rootAttributeProvenance['namespaceDeclarations'] ?? [],
                'rootNamespaceDeclarationMap' => $rootAttributeProvenance['namespaceDeclarationMap'] ?? [],
                'diagnostics' => $diagnostics,
            ];
        }

        ksort($versionCounts, SORT_STRING);
        ksort($diagnosticCodeCounts, SORT_STRING);
        sort($rootCustomAttributeNames, SORT_STRING);

        return [
            'count' => count($items),
            'declaredCount' => $declaredCount,
            'packagePartCount' => $packagePartCount,
            'missingPackagePartCount' => $missingPackagePartCount,
            'undeclaredPackagePartCount' => $undeclaredPackagePartCount,
            'versionedCount' => array_sum($versionCounts),
            'missingVersionCount' => count($missingVersionParts),
            'missingVersionParts' => $missingVersionParts,
            'versionMismatchCount' => count($versionMismatches),
            'versionMismatches' => $versionMismatches,
            'manifestVersion' => $this->manifestVersion,
            'versionCounts' => $versionCounts,
            'rootCustomAttributePartCount' => count($rootCustomAttributeItems),
            'rootCustomAttributeCount' => $rootCustomAttributeCount,
            'rootCustomAttributeNames' => $rootCustomAttributeNames,
            'rootCustomAttributeItems' => $rootCustomAttributeItems,
            'diagnosticCount' => $diagnosticCount,
            'diagnosticCodeCounts' => $diagnosticCodeCounts,
            'byteExposurePolicy' => 'odf-document-part-package-provenance-metadata-only',
            'canExposeBytes' => false,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $packageInventory
     * @return array<string, mixed>
     */
    private function packageStyleProvenance(array $packageInventory): array
    {
        $part = is_array($packageInventory['parts']['styles.xml'] ?? null)
            ? $packageInventory['parts']['styles.xml']
            : null;
        $styleNames = array_keys($this->stylesByName);
        sort($styleNames, SORT_STRING);
        $items = [];

        if (is_array($part) || $styleNames !== []) {
            $items[] = self::withoutEmptyValues([
                'path' => 'styles.xml',
                'declaredInManifest' => is_array($part) && ($part['declaredInManifest'] ?? false) === true,
                'exists' => is_array($part),
                'manifestIndex' => is_array($part) ? ($part['manifestIndex'] ?? null) : null,
                'manifestPath' => is_array($part) ? ($part['manifestPath'] ?? null) : null,
                'manifestMediaType' => is_array($part) ? ($part['manifestMediaType'] ?? null) : null,
                'styleCount' => count($styleNames),
                'styleNames' => $styleNames,
                'storedByteLength' => is_array($part) ? ($part['byteLength'] ?? null) : null,
                'compressedByteLength' => is_array($part) ? ($part['compressedByteLength'] ?? null) : null,
                'compressionMethod' => is_array($part) ? ($part['compressionMethod'] ?? null) : null,
                'compressionMethodName' => is_array($part) ? ($part['compressionMethodName'] ?? null) : null,
                'crc32' => is_array($part) ? ($part['crc32'] ?? null) : null,
                'packageByteExposurePolicy' => is_array($part) ? ($part['byteExposurePolicy'] ?? null) : null,
                'byteExposurePolicy' => 'odf-style-package-provenance-metadata-only',
                'canExposeBytes' => false,
            ]);
        }

        return [
            'count' => count($items),
            'styleCount' => count($styleNames),
            'styleNames' => $styleNames,
            'sourceParts' => $items === [] ? [] : ['styles.xml'],
            'byteExposurePolicy' => 'odf-style-package-provenance-metadata-only',
            'canExposeBytes' => false,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packageInventory(): array
    {
        $declaredPackagePaths = [
            'mimetype' => true,
            'META-INF/manifest.xml' => true,
        ];
        $manifestDeclaredPartCount = 0;
        foreach ($this->manifestEntries as $entry) {
            $path = $entry['path'] ?? null;
            if (!is_string($path) || $path === '' || $path === '/') {
                continue;
            }

            $packagePath = $entry['packagePath'] ?? null;
            $pathReference = $entry['pathReference'] ?? null;
            if (is_string($packagePath) && $packagePath !== '') {
                $declaredPackagePaths[$packagePath] = true;
            } elseif (is_string($pathReference) && $pathReference !== '') {
                $declaredPackagePaths[$pathReference] = true;
            } else {
                $declaredPackagePaths[$path] = true;
            }
            ++$manifestDeclaredPartCount;
        }

        $localHeaderOrder = $this->package->localHeaderOrderPreflight();
        $compressionMethods = $this->package->compressionMethodPreflight();
        $generalPurposeFlags = $this->package->generalPurposeFlagPreflight();
        $comments = $this->package->commentPreflight();
        $modificationTimes = $this->package->modificationTimePreflight();
        $modificationTimeByName = self::zipModificationTimeEntriesByName($modificationTimes);
        $platformMetadata = $this->package->platformMetadataPreflight();
        $platformMetadataByName = self::zipPreflightEntriesByName($platformMetadata);
        $nameHygiene = $this->package->nameHygienePreflight();
        $nameHygieneByName = self::zipPreflightEntriesByName($nameHygiene);
        $permissions = $this->package->permissionPreflight();
        $permissionsByName = self::zipPreflightEntriesByName($permissions);
        $creatorHostSystems = $this->package->creatorHostSystemPreflight();
        $creatorHostSystemsByName = self::zipPreflightEntriesByName($creatorHostSystems);
        $dosAttributes = $this->package->dosAttributePreflight();
        $dosAttributesByName = self::zipPreflightEntriesByName($dosAttributes);
        $internalAttributes = $this->package->internalAttributePreflight();
        $internalAttributesByName = self::zipPreflightEntriesByName($internalAttributes);
        $extraFields = $this->package->extraFieldPreflight();
        $unixOwners = $this->package->unixOwnerPreflight();
        $unixOwnersByName = self::zipPreflightEntriesByName($unixOwners);
        $namePolicy = self::zipNamePolicyProvenance($this->package);
        $packageManifest = $this->package->packageManifestPreflight();
        $zipPackageManifestSummary = self::zipPackageManifestAggregateProvenance($packageManifest);
        $localHeaderMetadata = ZipPackage::localHeaderMetadataPreflight($this->package->bytes());
        $packageManifestByName = self::zipPreflightEntriesByName($packageManifest);
        $localHeaderMetadataByName = self::zipLocalHeaderMetadataEntriesByName($localHeaderMetadata);
        $extraFieldsByName = self::zipPreflightEntriesByName($extraFields);
        $generalPurposeFlagsByName = self::zipPreflightEntriesByName($generalPurposeFlags);
        $objectPackageRootParts = self::embeddedObjectPackageRootParts($this->manifestEntries);
        $localOrderByName = [];
        foreach ($localHeaderOrder['entries'] as $entry) {
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $localOrderByName[$name] = $entry;
            }
        }
        $commentEntriesByName = [];
        foreach ($comments['entries'] ?? [] as $entry) {
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $commentEntriesByName[$name] = $entry;
            }
        }

        $parts = [];
        $undeclaredEntries = [];
        $packageDirectoryCount = 0;
        $roleCounts = [];
        $roleByteLengths = [];
        $roleCompressedByteLengths = [];
        $undeclaredRoleCounts = [];
        $unsupportedCompressionPartNames = [];
        $corePackagePartCount = 0;
        $mediaResourcePartCount = 0;
        $packageThumbnailPartCount = 0;
        $packageSignaturePartCount = 0;
        $embeddedObjectPackageRootCount = 0;
        $embeddedObjectPackagePartCount = 0;
        $objectReplacementPartCount = 0;
        $scriptPackagePartCount = 0;
        $configurationPackagePartCount = 0;
        $fontPackagePartCount = 0;
        $rdfMetadataPartCount = 0;
        $layoutCachePartCount = 0;
        $metaInfSidecarPackagePartCount = 0;
        $databasePackagePartCount = 0;
        $unsupportedCompressionMethodCount = 0;
        $totalByteLength = 0;
        $totalCompressedByteLength = 0;
        $exposableEntryCount = 0;
        $blockedEntryCount = 0;
        $exposableByteLength = 0;
        $exposableCompressedByteLength = 0;
        $blockedByteLength = 0;
        $blockedCompressedByteLength = 0;
        $unsupportedCompressionByteLength = 0;
        $unsupportedCompressionCompressedByteLength = 0;
        $byteExposurePolicyCounts = [];
        $byteExposurePolicyByteLengths = [];
        $byteExposurePolicyCompressedByteLengths = [];
        $byteExposurePolicyItems = [];
        $manifestMediaFamilyCounts = [];
        $manifestMediaFamilyByteLengths = [];
        $manifestMediaFamilyCompressedByteLengths = [];
        $packagePathKindCounts = [];
        $packageTopLevelSegmentCounts = [];
        $packagePathExtensionCounts = [];
        $packageAreaCounts = [];
        $packageAreaByteLengths = [];
        $packageAreaCompressedByteLengths = [];
        $packageAreaSummaries = [];
        $packagePathsByPackageArea = [];
        $packagePathDepthCounts = [];
        $packagePathsByPathDepth = [];
        $maxPackagePathDepth = 0;
        $packagePathDepthRoleCounts = [];
        $entryNamesByPackagePathDepthRole = [];
        $packagePathDepthByteExposurePolicyCounts = [];
        $entryNamesByPackagePathDepthByteExposurePolicy = [];
        $zipPackageManifestPathSegmentPositionRoleCounts = [];
        $entryNamesByZipPackageManifestPathSegmentPositionRole = [];
        $zipPackageManifestPathSegmentPositionByteExposurePolicyCounts = [];
        $entryNamesByZipPackageManifestPathSegmentPositionByteExposurePolicy = [];
        $rawNameProvenanceEntryCount = 0;
        $legacyEncodedNameEntryCount = 0;
        $unicodePathExtraEntryCount = 0;
        $decodedNameDiffersFromRawNameEntryCount = 0;
        $rawNameProvenanceEntries = [];
        $packagePartXmlCdataSections = [
            'partCount' => 0,
            'sectionCount' => 0,
            'byteLength' => 0,
            'partNames' => [],
            'sections' => [],
            'truncated' => false,
        ];
        $packagePartXmlComments = [
            'partCount' => 0,
            'commentCount' => 0,
            'byteLength' => 0,
            'parentDepthCounts' => [],
            'partNames' => [],
            'comments' => [],
            'truncated' => false,
        ];
        $packagePartXmlProcessingInstructions = [
            'partCount' => 0,
            'instructionCount' => 0,
            'dataByteLength' => 0,
            'targets' => [],
            'partNames' => [],
            'instructions' => [],
            'truncated' => false,
        ];
        $packagePartXmlRootElements = [
            'partCount' => 0,
            'rootNames' => [],
            'rootNameCounts' => [],
            'namespaceUris' => [],
            'namespaceUriCounts' => [],
            'partNames' => [],
            'roots' => [],
            'truncated' => false,
        ];
        foreach ($this->package->entries() as $centralDirectoryIndex => $entry) {
            $manifestEntry = $this->manifestEntriesByPath[$entry->name] ?? null;
            $isUndeclared = !$entry->isDirectory() && !isset($declaredPackagePaths[$entry->name]);
            $localOrder = $localOrderByName[$entry->name] ?? null;
            $commentEntry = $commentEntriesByName[$entry->name] ?? null;
            $embeddedObjectPackage = self::embeddedObjectPackageMembership($entry->name, $objectPackageRootParts);
            $rawNameProvenance = self::zipEntryRawNameProvenance($entry);
            $extraFieldProvenance = self::zipExtraFieldProvenance($extraFieldsByName[$entry->name] ?? null);
            $unixOwnerProvenance = self::zipUnixOwnerMetadataProvenance($unixOwnersByName[$entry->name] ?? null);
            $generalPurposeFlagProvenance = self::zipGeneralPurposeFlagProvenance($generalPurposeFlagsByName[$entry->name] ?? null);
            $timestampProvenance = self::zipTimestampProvenance($modificationTimeByName[$entry->name] ?? null);
            $packageManifestEntrySource = self::zipPackageManifestEntrySourceProvenance($packageManifestByName[$entry->name] ?? null);
            $localHeaderMetadataProvenance = self::zipLocalHeaderMetadataProvenance($localHeaderMetadataByName[$entry->name] ?? null);
            $platformAttributeProvenance = self::zipPlatformAttributeProvenance(
                $entry,
                $platformMetadataByName[$entry->name] ?? null,
                $permissionsByName[$entry->name] ?? null,
                $creatorHostSystemsByName[$entry->name] ?? null,
                $dosAttributesByName[$entry->name] ?? null,
                $internalAttributesByName[$entry->name] ?? null
            );
            $nameHygieneProvenance = self::zipNameHygieneProvenance($nameHygieneByName[$entry->name] ?? null);
            if ($entry->isDirectory()) {
                ++$packageDirectoryCount;
            }

            $pathShape = self::pathShape($entry->name);
            $packageArea = self::packageAreaFromPathShape($pathShape);
            $packagePathDepth = self::packagePathDepthFromPathShape($pathShape);
            $packagePathByteLength = strlen($entry->name);
            $packagePathByteLengthBucket = self::packagePathByteLengthBucket($packagePathByteLength);
            $packagePartExtension = self::packagePartExtension($entry->name);
            $rawPackagePartExtension = self::packagePartRawExtension($entry->name);
            $roles = self::packageEntryRoles($entry, $manifestEntry, $isUndeclared, $embeddedObjectPackage);
            $byteExposurePolicy = null;
            if (is_array($manifestEntry)) {
                $byteExposurePolicy = $manifestEntry['byteExposurePolicy'] ?? null;
            } elseif ($isUndeclared || is_array($embeddedObjectPackage)) {
                $byteExposurePolicy = self::undeclaredPackageEntryByteExposurePolicy($entry->name, $embeddedObjectPackage);
            }
            $xmlRootElement = self::packagePartXmlRootElementMetadata(
                $this->package,
                $entry,
                is_array($manifestEntry) ? (string) ($manifestEntry['mediaTypeBase'] ?? '') : ''
            );
            $xmlCdataSections = self::packagePartXmlCdataSectionMetadata(
                $this->package,
                $entry,
                is_array($manifestEntry) ? (string) ($manifestEntry['mediaTypeBase'] ?? '') : ''
            );
            $xmlComments = self::packagePartXmlCommentMetadata(
                $this->package,
                $entry,
                is_array($manifestEntry) ? (string) ($manifestEntry['mediaTypeBase'] ?? '') : ''
            );
            $xmlProcessingInstructions = self::packagePartXmlProcessingInstructionMetadata(
                $this->package,
                $entry,
                is_array($manifestEntry) ? (string) ($manifestEntry['mediaTypeBase'] ?? '') : ''
            );
            $item = [
                'path' => $entry->name,
                'pathShape' => $pathShape,
                'packageDirectoryBaseName' => $pathShape['directoryBaseName'] ?? null,
                'packageDirectoryBaseNameStem' => $pathShape['directoryBaseNameStem'] ?? null,
                'packageCaseFoldDirectoryBaseNameStem' => $pathShape['caseFoldDirectoryBaseNameStem'] ?? null,
                'packageArea' => $packageArea,
                'packagePathDepth' => $packagePathDepth,
                'packagePathByteLength' => $packagePathByteLength,
                'packagePathByteLengthBucket' => $packagePathByteLengthBucket['packagePathByteLengthBucket'],
                'packagePathByteLengthBucketMin' => $packagePathByteLengthBucket['minPackagePathByteLength'],
                'packagePathByteLengthBucketMax' => $packagePathByteLengthBucket['maxPackagePathByteLength'],
                'packagePartExtension' => $packagePartExtension,
                'rawPackagePartExtension' => $rawPackagePartExtension,
                'packagePartExtensionHasUppercase' => $rawPackagePartExtension !== null && preg_match('/[A-Z]/', $rawPackagePartExtension) === 1,
                'packagePartExtensionWasNormalized' => $packagePartExtension !== null && $rawPackagePartExtension !== null && $packagePartExtension !== $rawPackagePartExtension,
                'extensionlessPackagePart' => !$entry->isDirectory() && $packagePartExtension === null,
                'roles' => $roles,
                'centralDirectoryIndex' => $centralDirectoryIndex,
                'localHeaderOrder' => is_array($localOrder) ? $localOrder['localHeaderOrder'] : null,
                'localHeaderOffset' => $entry->localHeaderOffset,
                'matchesCentralDirectoryOrder' => is_array($localOrder)
                    ? $localOrder['matchesCentralDirectoryOrder']
                    : null,
                'compressionMethod' => $entry->compressionMethod,
                'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
                'byteLength' => $entry->uncompressedSize,
                'compressedByteLength' => $entry->compressedSize,
                'crc32' => $entry->crc32Hex(),
                'byteSha256' => is_array($manifestEntry) ? ($manifestEntry['byteSha256'] ?? null) : null,
                'xmlHasRootElement' => ($xmlRootElement['hasRootElement'] ?? false) === true,
                'xmlRootElementName' => $xmlRootElement['name'] ?? null,
                'xmlRootElementLocalName' => $xmlRootElement['localName'] ?? null,
                'xmlRootElementPrefix' => $xmlRootElement['prefix'] ?? null,
                'xmlRootElementNamespaceUri' => $xmlRootElement['namespaceUri'] ?? null,
                'xmlRootElementPath' => $xmlRootElement['path'] ?? null,
                'xmlRootElementAttributeCount' => $xmlRootElement['attributeCount'] ?? 0,
                'xmlRootElementAttributeNames' => $xmlRootElement['attributeNames'] ?? [],
                'xmlRootElementNamespaceDeclarationCount' => $xmlRootElement['namespaceDeclarationCount'] ?? 0,
                'xmlRootElementNamespaceDeclarationNames' => $xmlRootElement['namespaceDeclarationNames'] ?? [],
                'xmlCdataSectionCount' => $xmlCdataSections['count'],
                'xmlCdataSectionByteLength' => $xmlCdataSections['byteLength'],
                'xmlCdataSections' => $xmlCdataSections['sections'],
                'xmlCdataSectionsTruncated' => $xmlCdataSections['truncated'],
                'xmlCommentCount' => $xmlComments['count'],
                'xmlCommentByteLength' => $xmlComments['byteLength'],
                'xmlCommentParentDepthCounts' => $xmlComments['parentDepthCounts'],
                'xmlComments' => $xmlComments['comments'],
                'xmlCommentsTruncated' => $xmlComments['truncated'],
                'xmlProcessingInstructionCount' => $xmlProcessingInstructions['count'],
                'xmlProcessingInstructionDataByteLength' => $xmlProcessingInstructions['dataByteLength'],
                'xmlProcessingInstructionTargets' => $xmlProcessingInstructions['targets'],
                'xmlProcessingInstructions' => $xmlProcessingInstructions['instructions'],
                'xmlProcessingInstructionsTruncated' => $xmlProcessingInstructions['truncated'],
                'zipEntryComment' => $entry->comment,
                'zipEntryCommentLength' => strlen($entry->rawComment),
                'zipEntryCommentEncoding' => $entry->commentEncoding,
                'zipEntryHasComment' => $entry->comment !== '',
                'zipEntryCommentIssues' => is_array($commentEntry) ? ($commentEntry['issues'] ?? []) : [],
                'isDirectory' => $entry->isDirectory(),
                'declaredInManifest' => is_array($manifestEntry),
                'manifestIndex' => is_array($manifestEntry) ? $manifestEntry['manifestIndex'] : null,
                'manifestPath' => is_array($manifestEntry) ? $manifestEntry['path'] : null,
                'manifestPackagePath' => is_array($manifestEntry) ? $manifestEntry['packagePath'] : null,
                'manifestPathReference' => is_array($manifestEntry) ? $manifestEntry['pathReference'] : null,
                'manifestPathSuffix' => is_array($manifestEntry) ? $manifestEntry['pathSuffix'] : null,
                'manifestPathQuery' => is_array($manifestEntry) ? $manifestEntry['pathQuery'] : null,
                'manifestPathFragment' => is_array($manifestEntry) ? $manifestEntry['pathFragment'] : null,
                'manifestPathShape' => is_array($manifestEntry) ? ($manifestEntry['pathShape'] ?? null) : null,
                'manifestPackagePathShape' => is_array($manifestEntry) ? ($manifestEntry['packagePathShape'] ?? null) : null,
                'manifestUriEncodedPackageReference' => is_array($manifestEntry) && ($manifestEntry['uriEncodedPackageReference'] ?? false) === true,
                'manifestMediaFamily' => is_array($manifestEntry) ? ($manifestEntry['manifestMediaFamily'] ?? null) : null,
                'manifestMediaType' => is_array($manifestEntry) ? $manifestEntry['mediaType'] : null,
                'manifestMediaTypeBase' => is_array($manifestEntry) ? $manifestEntry['mediaTypeBase'] : null,
                'manifestMediaTypeHasParameters' => is_array($manifestEntry) ? $manifestEntry['mediaTypeHasParameters'] : false,
                'manifestMediaTypeParameterCount' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameterCount'] : 0,
                'manifestMediaTypeParameters' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameters'] : [],
                'manifestMediaTypeParameterMap' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameterMap'] : [],
                'manifestVersion' => is_array($manifestEntry) ? $manifestEntry['version'] : null,
                'manifestPreferredViewMode' => is_array($manifestEntry) ? $manifestEntry['preferredViewMode'] : null,
                'manifestAttributeCount' => is_array($manifestEntry) ? ($manifestEntry['manifestAttributeCount'] ?? 0) : 0,
                'manifestAttributeNames' => is_array($manifestEntry) ? ($manifestEntry['manifestAttributeNames'] ?? []) : [],
                'manifestAttributes' => is_array($manifestEntry) ? ($manifestEntry['manifestAttributes'] ?? []) : [],
                'customManifestAttributeCount' => is_array($manifestEntry) ? ($manifestEntry['customManifestAttributeCount'] ?? 0) : 0,
                'customManifestAttributeNames' => is_array($manifestEntry) ? ($manifestEntry['customManifestAttributeNames'] ?? []) : [],
                'customManifestAttributes' => is_array($manifestEntry) ? ($manifestEntry['customManifestAttributes'] ?? []) : [],
                'customManifestAttributeMap' => is_array($manifestEntry) ? ($manifestEntry['customManifestAttributeMap'] ?? []) : [],
                'manifestNamespaceDeclarationCount' => is_array($manifestEntry) ? ($manifestEntry['manifestNamespaceDeclarationCount'] ?? 0) : 0,
                'manifestNamespaceDeclarationNames' => is_array($manifestEntry) ? ($manifestEntry['manifestNamespaceDeclarationNames'] ?? []) : [],
                'manifestNamespaceDeclarations' => is_array($manifestEntry) ? ($manifestEntry['manifestNamespaceDeclarations'] ?? []) : [],
                'manifestNamespaceDeclarationMap' => is_array($manifestEntry) ? ($manifestEntry['manifestNamespaceDeclarationMap'] ?? []) : [],
                'manifestChildElementCount' => is_array($manifestEntry) ? ($manifestEntry['manifestChildElementCount'] ?? 0) : 0,
                'manifestChildElementNames' => is_array($manifestEntry) ? ($manifestEntry['manifestChildElementNames'] ?? []) : [],
                'manifestChildElements' => is_array($manifestEntry) ? ($manifestEntry['manifestChildElements'] ?? []) : [],
                'customManifestChildElementCount' => is_array($manifestEntry) ? ($manifestEntry['customManifestChildElementCount'] ?? 0) : 0,
                'customManifestChildElementNames' => is_array($manifestEntry) ? ($manifestEntry['customManifestChildElementNames'] ?? []) : [],
                'customManifestChildElements' => is_array($manifestEntry) ? ($manifestEntry['customManifestChildElements'] ?? []) : [],
                'manifestDeclaredSize' => is_array($manifestEntry) ? $manifestEntry['declaredSize'] : null,
                'manifestDeclaredSizeMismatch' => is_array($manifestEntry) && ($manifestEntry['declaredSizeMismatch'] ?? false) === true,
                'manifestMissingMediaType' => is_array($manifestEntry) && ($manifestEntry['missingMediaType'] ?? false) === true,
                'manifestDiagnostics' => is_array($manifestEntry) ? ($manifestEntry['diagnostics'] ?? []) : [],
                'manifestEncryption' => is_array($manifestEntry) ? $manifestEntry['encryption'] : null,
                'manifestEncryptionRecordCount' => is_array($manifestEntry) && is_array($manifestEntry['encryption'] ?? null)
                    ? ($manifestEntry['encryption']['recordCount'] ?? 0)
                    : 0,
                'manifestEncryptionIssueCodes' => is_array($manifestEntry) && is_array($manifestEntry['encryption'] ?? null)
                    ? ($manifestEntry['encryption']['issueCodes'] ?? [])
                    : [],
                'embeddedObjectPackagePart' => is_array($embeddedObjectPackage),
                'embeddedObjectRootPart' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['rootPart'] : null,
                'embeddedObjectPath' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['objectPath'] : null,
                'embeddedObjectType' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['objectType'] : null,
                'embeddedObjectRoot' => is_array($embeddedObjectPackage) && $embeddedObjectPackage['isRoot'] === true,
                'embeddedObjectContainedPart' => is_array($embeddedObjectPackage) && $embeddedObjectPackage['isRoot'] !== true,
                'embeddedObjectMediaType' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['mediaType'] : null,
                'thumbnailPackagePart' => self::isThumbnailPackagePartName($entry->name),
                'objectReplacementPackagePart' => self::isObjectReplacementPackagePartName($entry->name),
                'scriptPackagePart' => self::isScriptPackagePartName($entry->name),
                'signaturePackagePart' => self::isSignaturePackagePartName($entry->name),
                'configurationPackagePart' => self::isConfigurationPackagePartName($entry->name),
                'fontPackagePart' => self::isFontPackagePart($entry->name, is_array($manifestEntry) ? (string) ($manifestEntry['mediaType'] ?? '') : null),
                'rdfMetadataPart' => self::isRdfMetadataPart($entry->name, is_array($manifestEntry) ? (string) ($manifestEntry['mediaType'] ?? '') : null),
                'layoutCachePackagePart' => self::isLayoutCachePackagePartName($entry->name),
                'metaInfSidecarPackagePart' => self::isMetaInfSidecarPackagePartName($entry->name),
                'databasePackagePart' => self::isDatabasePackagePartName($entry->name),
                'encrypted' => is_array($manifestEntry) && ($manifestEntry['encrypted'] ?? false) === true,
                'canExposeBytes' => is_array($manifestEntry) && ($manifestEntry['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $byteExposurePolicy,
                'undeclared' => $isUndeclared,
            ] + $rawNameProvenance + $extraFieldProvenance + $unixOwnerProvenance + $generalPurposeFlagProvenance + $packageManifestEntrySource + $localHeaderMetadataProvenance + $timestampProvenance + $platformAttributeProvenance + $nameHygieneProvenance;

            self::recordZipPackageManifestPathSegmentPositionInventory(
                $zipPackageManifestPathSegmentPositionRoleCounts,
                $entryNamesByZipPackageManifestPathSegmentPositionRole,
                $zipPackageManifestPathSegmentPositionByteExposurePolicyCounts,
                $entryNamesByZipPackageManifestPathSegmentPositionByteExposurePolicy,
                is_array($item['zipPackageManifestPathSegmentPositionReviews'] ?? null)
                    ? $item['zipPackageManifestPathSegmentPositionReviews']
                    : [],
                $entry->name,
                $roles,
                is_string($byteExposurePolicy) ? $byteExposurePolicy : null
            );
            foreach ($roles as $role) {
                $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
                $roleByteLengths[$role] = ($roleByteLengths[$role] ?? 0) + $entry->uncompressedSize;
                $roleCompressedByteLengths[$role] = ($roleCompressedByteLengths[$role] ?? 0) + $entry->compressedSize;
                if ($isUndeclared) {
                    $undeclaredRoleCounts[$role] = ($undeclaredRoleCounts[$role] ?? 0) + 1;
                }
            }
            $totalByteLength += $entry->uncompressedSize;
            $totalCompressedByteLength += $entry->compressedSize;
            if (($entry->compressionMethod !== 0 && $entry->compressionMethod !== 8)) {
                ++$unsupportedCompressionMethodCount;
                $unsupportedCompressionPartNames[$entry->name] = true;
                $unsupportedCompressionByteLength += $entry->uncompressedSize;
                $unsupportedCompressionCompressedByteLength += $entry->compressedSize;
            }
            if (($item['canExposeBytes'] ?? false) === true) {
                ++$exposableEntryCount;
                $exposableByteLength += $entry->uncompressedSize;
                $exposableCompressedByteLength += $entry->compressedSize;
            } else {
                ++$blockedEntryCount;
                $blockedByteLength += $entry->uncompressedSize;
                $blockedCompressedByteLength += $entry->compressedSize;
            }
            if (is_string($byteExposurePolicy) && $byteExposurePolicy !== '') {
                $byteExposurePolicyCounts[$byteExposurePolicy] = ($byteExposurePolicyCounts[$byteExposurePolicy] ?? 0) + 1;
                $byteExposurePolicyByteLengths[$byteExposurePolicy] = ($byteExposurePolicyByteLengths[$byteExposurePolicy] ?? 0) + $entry->uncompressedSize;
                $byteExposurePolicyCompressedByteLengths[$byteExposurePolicy] = ($byteExposurePolicyCompressedByteLengths[$byteExposurePolicy] ?? 0) + $entry->compressedSize;
                $byteExposurePolicyItems[] = self::withoutEmptyValues([
                    'path' => $entry->name,
                    'centralDirectoryIndex' => $centralDirectoryIndex,
                    'roles' => $roles,
                    'byteExposurePolicy' => $byteExposurePolicy,
                    'declaredInManifest' => is_array($manifestEntry),
                    'undeclared' => $isUndeclared,
                    'canExposeBytes' => is_array($manifestEntry) && ($manifestEntry['canExposeBytes'] ?? false) === true,
                ]);
            }
            if (is_array($manifestEntry) && is_string($manifestEntry['manifestMediaFamily'] ?? null)) {
                $family = $manifestEntry['manifestMediaFamily'];
                $manifestMediaFamilyCounts[$family] = ($manifestMediaFamilyCounts[$family] ?? 0) + 1;
                $manifestMediaFamilyByteLengths[$family] = ($manifestMediaFamilyByteLengths[$family] ?? 0) + $entry->uncompressedSize;
                $manifestMediaFamilyCompressedByteLengths[$family] = ($manifestMediaFamilyCompressedByteLengths[$family] ?? 0) + $entry->compressedSize;
            }
            $pathKind = $pathShape['kind'] ?? null;
            if (is_string($pathKind) && $pathKind !== '') {
                $packagePathKindCounts[$pathKind] = ($packagePathKindCounts[$pathKind] ?? 0) + 1;
            }
            $topLevelSegment = $pathShape['topLevelSegment'] ?? null;
            if (is_string($topLevelSegment) && $topLevelSegment !== '') {
                $packageTopLevelSegmentCounts[$topLevelSegment] = ($packageTopLevelSegmentCounts[$topLevelSegment] ?? 0) + 1;
            }
            $pathExtension = $pathShape['extension'] ?? null;
            if (is_string($pathExtension) && $pathExtension !== '') {
                $packagePathExtensionCounts[$pathExtension] = ($packagePathExtensionCounts[$pathExtension] ?? 0) + 1;
            }
            self::recordPackageTopologySummary(
                $packageAreaCounts,
                $packageAreaByteLengths,
                $packageAreaCompressedByteLengths,
                $packageAreaSummaries,
                $packagePathsByPackageArea,
                $packagePathDepthCounts,
                $packagePathsByPathDepth,
                $maxPackagePathDepth,
                $entry->name,
                $packageArea,
                $packagePathDepth,
                $roles,
                $entry->isDirectory(),
                $entry->uncompressedSize,
                $entry->compressedSize,
                is_array($manifestEntry),
                $isUndeclared,
                ($item['canExposeBytes'] ?? false) === true,
                is_string($byteExposurePolicy) ? $byteExposurePolicy : null,
            );
            self::recordPackagePathDepthInventory(
                $packagePathDepthRoleCounts,
                $entryNamesByPackagePathDepthRole,
                $packagePathDepthByteExposurePolicyCounts,
                $entryNamesByPackagePathDepthByteExposurePolicy,
                $packagePathDepth,
                $entry->name,
                $roles,
                is_string($byteExposurePolicy) ? $byteExposurePolicy : null
            );
            if (array_intersect($roles, ['odf-mimetype', 'odf-manifest', 'odf-content', 'odf-styles', 'odf-meta', 'odf-settings']) !== []) {
                ++$corePackagePartCount;
            }
            if (in_array('media-resource', $roles, true)) {
                ++$mediaResourcePartCount;
            }
            if (in_array('package-thumbnail', $roles, true)) {
                ++$packageThumbnailPartCount;
            }
            if (in_array('package-signature', $roles, true)) {
                ++$packageSignaturePartCount;
            }
            if (in_array('embedded-object-root', $roles, true)) {
                ++$embeddedObjectPackageRootCount;
            }
            if (in_array('embedded-object-part', $roles, true)) {
                ++$embeddedObjectPackagePartCount;
            }
            if (in_array('object-replacement', $roles, true)) {
                ++$objectReplacementPartCount;
            }
            if (in_array('script-package', $roles, true)) {
                ++$scriptPackagePartCount;
            }
            if (in_array('configuration-package', $roles, true)) {
                ++$configurationPackagePartCount;
            }
            if (in_array('font-package', $roles, true)) {
                ++$fontPackagePartCount;
            }
            if (in_array('rdf-metadata', $roles, true)) {
                ++$rdfMetadataPartCount;
            }
            if (in_array('layout-cache', $roles, true)) {
                ++$layoutCachePartCount;
            }
            if (in_array('meta-inf-sidecar', $roles, true)) {
                ++$metaInfSidecarPackagePartCount;
            }
            if (in_array('database-package', $roles, true)) {
                ++$databasePackagePartCount;
            }
            if ($rawNameProvenance['hasRawNameProvenance']) {
                ++$rawNameProvenanceEntryCount;
                $rawNameProvenanceEntries[] = [
                    'path' => $entry->name,
                    'centralDirectoryIndex' => $centralDirectoryIndex,
                ] + $rawNameProvenance;
            }
            if ($rawNameProvenance['usesLegacyNameEncoding']) {
                ++$legacyEncodedNameEntryCount;
            }
            if ($rawNameProvenance['usesUnicodePathExtraField']) {
                ++$unicodePathExtraEntryCount;
            }
            if (!$rawNameProvenance['rawNameMatchesDecodedName']) {
                ++$decodedNameDiffersFromRawNameEntryCount;
            }
            self::recordPackagePartXmlRootElementSummary($packagePartXmlRootElements, $entry->name, $xmlRootElement);
            self::recordPackagePartXmlCdataSectionSummary($packagePartXmlCdataSections, $entry->name, $xmlCdataSections);
            self::recordPackagePartXmlCommentSummary($packagePartXmlComments, $entry->name, $xmlComments);
            self::recordPackagePartXmlProcessingInstructionSummary(
                $packagePartXmlProcessingInstructions,
                $entry->name,
                $xmlProcessingInstructions
            );

            $parts[$entry->name] = $item;
            if ($isUndeclared) {
                $undeclaredEntries[] = $item;
            }
        }
        ksort($roleCounts, SORT_STRING);
        ksort($roleByteLengths, SORT_STRING);
        ksort($roleCompressedByteLengths, SORT_STRING);
        ksort($undeclaredRoleCounts, SORT_STRING);
        ksort($byteExposurePolicyCounts, SORT_STRING);
        ksort($byteExposurePolicyByteLengths, SORT_STRING);
        ksort($byteExposurePolicyCompressedByteLengths, SORT_STRING);
        ksort($manifestMediaFamilyCounts, SORT_STRING);
        ksort($manifestMediaFamilyByteLengths, SORT_STRING);
        ksort($manifestMediaFamilyCompressedByteLengths, SORT_STRING);
        ksort($packagePathKindCounts, SORT_STRING);
        ksort($packageTopLevelSegmentCounts, SORT_STRING);
        ksort($packagePathExtensionCounts, SORT_STRING);
        ksort($packageAreaCounts, SORT_STRING);
        ksort($packageAreaByteLengths, SORT_STRING);
        ksort($packageAreaCompressedByteLengths, SORT_STRING);
        self::sortPackageStringListMap($packagePathsByPackageArea, SORT_STRING);
        ksort($packagePathDepthCounts, SORT_NUMERIC);
        self::sortPackageStringListMap($packagePathsByPathDepth, SORT_NUMERIC);
        self::sortPackageNestedCountMap($packagePathDepthRoleCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByPackagePathDepthRole, SORT_NUMERIC);
        self::sortPackageNestedCountMap($packagePathDepthByteExposurePolicyCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByPackagePathDepthByteExposurePolicy, SORT_NUMERIC);
        self::sortPackageNestedCountMap($zipPackageManifestPathSegmentPositionRoleCounts);
        self::sortPackageNestedStringListMap($entryNamesByZipPackageManifestPathSegmentPositionRole);
        self::sortPackageNestedCountMap($zipPackageManifestPathSegmentPositionByteExposurePolicyCounts);
        self::sortPackageNestedStringListMap($entryNamesByZipPackageManifestPathSegmentPositionByteExposurePolicy);
        $packageAreaSummaries = self::finalizePackageAreaSummaries($packageAreaSummaries);
        $packagePartExtensions = self::packagePartExtensionInventory($parts);
        $packagePartRawExtensions = self::packagePartRawExtensionInventory($parts);
        $packagePartBasenames = self::packagePartBasenameInventory($parts);
        $packageDirectoryBaseNames = self::packageDirectoryBaseNameInventory($parts);
        $packageCaseFoldTopLevelSegments = self::packageCaseFoldTopLevelSegmentInventory($parts);
        $packageZipSourceRecordDirectoryRoots = self::packageZipSourceRecordDirectoryRootInventory($parts);
        $packageZipSourceRecordPackagePartExtensions = self::packageZipSourceRecordPackagePartExtensionInventory($parts);
        $packageZipSourceRecordPackagePartBaseNameStems = self::packageZipSourceRecordPackagePartBaseNameStemInventory($parts);
        $packageZipSourceRecordCompressionMethods = self::packageZipSourceRecordCompressionMethodInventory($parts);
        $packageZipSourceRecordRoles = self::packageZipSourceRecordRoleInventory($parts);
        $packageCrc32s = OdfPackageCrc32Inventory::summarize($parts);
        $packageZipTimestampSources = self::packageZipTimestampSourceInventory($parts);
        $packageExtraFields = self::packageExtraFieldInventory($parts);
        $packagePathByteLengths = self::packagePathByteLengthInventory($parts);
        $manifestPackageCoverage = self::manifestPackageCoverageProvenance($this->manifestEntries, $parts, $undeclaredEntries);
        $packageByteHandoff = OpenDocumentPackageByteHandoff::summarize($this->package, $parts, 'path');
        $centralDirectoryOrderMismatchRoles = self::centralDirectoryOrderMismatchRoleInventory($parts);

        return [
            'entryCount' => count($parts),
            'manifestDeclaredPartCount' => $manifestDeclaredPartCount,
            'manifestPackageCoverage' => $manifestPackageCoverage,
            'manifestPackageCoverageIssueCount' => $manifestPackageCoverage['issueCount'],
            'manifestPackageCoverageIssueCodes' => $manifestPackageCoverage['issueCodes'],
            'manifestPackageCoveredReferenceCount' => $manifestPackageCoverage['manifestPackageCoveredReferenceCount'],
            'manifestPackageMissingReferenceCount' => $manifestPackageCoverage['manifestPackageMissingReferenceCount'],
            'manifestPackageUndeclaredZipEntryCount' => $manifestPackageCoverage['packageUndeclaredZipEntryCount'],
            'undeclaredEntryCount' => count($undeclaredEntries),
            'undeclaredEntries' => $undeclaredEntries,
            'packageDirectoryCount' => $packageDirectoryCount,
            'extensionlessPackagePartCount' => $packagePartExtensions['extensionlessPackagePartCount'],
            'packagePartExtensionCounts' => $packagePartExtensions['packagePartExtensionCounts'],
            'entryNamesByPackagePartExtension' => $packagePartExtensions['entryNamesByPackagePartExtension'],
            'packagePartExtensionSummaryCount' => count($packagePartExtensions['packagePartExtensionSummaries']),
            'packagePartExtensionSummaries' => $packagePartExtensions['packagePartExtensionSummaries'],
            'packagePartRawExtensionCount' => count($packagePartRawExtensions['packagePartRawExtensionSummaries']),
            'packagePartRawExtensionCounts' => $packagePartRawExtensions['packagePartRawExtensionCounts'],
            'entryNamesByPackagePartRawExtension' => $packagePartRawExtensions['entryNamesByPackagePartRawExtension'],
            'packagePartRawExtensionUppercasePartCount' => $packagePartRawExtensions['packagePartRawExtensionUppercasePartCount'],
            'packagePartRawExtensionNormalizedPartCount' => $packagePartRawExtensions['packagePartRawExtensionNormalizedPartCount'],
            'packagePartRawExtensionSummaryCount' => count($packagePartRawExtensions['packagePartRawExtensionSummaries']),
            'packagePartRawExtensionSummaries' => $packagePartRawExtensions['packagePartRawExtensionSummaries'],
            'packageBasenameCounts' => $packagePartBasenames['packageBasenameCounts'],
            'entryNamesByPackageBasename' => $packagePartBasenames['entryNamesByPackageBasename'],
            'packageBasenameStemCounts' => $packagePartBasenames['packageBasenameStemCounts'],
            'packageCaseFoldedBasenameCounts' => $packagePartBasenames['packageCaseFoldedBasenameCounts'],
            'entryNamesByPackageCaseFoldedBasename' => $packagePartBasenames['entryNamesByPackageCaseFoldedBasename'],
            'duplicatePackageBasenameCount' => $packagePartBasenames['duplicatePackageBasenameCount'],
            'duplicatePackageBasenameEntryCount' => $packagePartBasenames['duplicatePackageBasenameEntryCount'],
            'duplicatePackageBasenameSummaries' => $packagePartBasenames['duplicatePackageBasenameSummaries'],
            'caseFoldedPackageBasenameDuplicateCount' => $packagePartBasenames['caseFoldedPackageBasenameDuplicateCount'],
            'caseFoldedPackageBasenameDuplicateEntryCount' => $packagePartBasenames['caseFoldedPackageBasenameDuplicateEntryCount'],
            'caseFoldedPackageBasenameDuplicateSummaries' => $packagePartBasenames['caseFoldedPackageBasenameDuplicateSummaries'],
            'packageDirectoryBaseNameCount' => $packageDirectoryBaseNames['packageDirectoryBaseNameCount'],
            'packageDirectoryBaseNameCounts' => $packageDirectoryBaseNames['packageDirectoryBaseNameCounts'],
            'entryNamesByPackageDirectoryBaseName' => $packageDirectoryBaseNames['entryNamesByPackageDirectoryBaseName'],
            'duplicatePackageDirectoryBaseNameCount' => $packageDirectoryBaseNames['duplicatePackageDirectoryBaseNameCount'],
            'duplicatePackageDirectoryBaseNames' => $packageDirectoryBaseNames['duplicatePackageDirectoryBaseNames'],
            'packageDirectoryBaseNames' => $packageDirectoryBaseNames['packageDirectoryBaseNames'],
            'packageCaseFoldDirectoryBaseNameCount' => $packageDirectoryBaseNames['packageCaseFoldDirectoryBaseNameCount'],
            'packageCaseFoldDirectoryBaseNameCounts' => $packageDirectoryBaseNames['packageCaseFoldDirectoryBaseNameCounts'],
            'entryNamesByPackageCaseFoldDirectoryBaseName' => $packageDirectoryBaseNames['entryNamesByPackageCaseFoldDirectoryBaseName'],
            'duplicatePackageCaseFoldDirectoryBaseNameCount' => $packageDirectoryBaseNames['duplicatePackageCaseFoldDirectoryBaseNameCount'],
            'duplicatePackageCaseFoldDirectoryBaseNames' => $packageDirectoryBaseNames['duplicatePackageCaseFoldDirectoryBaseNames'],
            'packageCaseFoldDirectoryBaseNames' => $packageDirectoryBaseNames['packageCaseFoldDirectoryBaseNames'],
            'packageDirectoryBaseNameStemCount' => $packageDirectoryBaseNames['packageDirectoryBaseNameStemCount'],
            'packageDirectoryBaseNameStemCounts' => $packageDirectoryBaseNames['packageDirectoryBaseNameStemCounts'],
            'entryNamesByPackageDirectoryBaseNameStem' => $packageDirectoryBaseNames['entryNamesByPackageDirectoryBaseNameStem'],
            'duplicatePackageDirectoryBaseNameStemCount' => $packageDirectoryBaseNames['duplicatePackageDirectoryBaseNameStemCount'],
            'duplicatePackageDirectoryBaseNameStems' => $packageDirectoryBaseNames['duplicatePackageDirectoryBaseNameStems'],
            'packageDirectoryBaseNameStems' => $packageDirectoryBaseNames['packageDirectoryBaseNameStems'],
            'packageCaseFoldDirectoryBaseNameStemCount' => $packageDirectoryBaseNames['packageCaseFoldDirectoryBaseNameStemCount'],
            'packageCaseFoldDirectoryBaseNameStemCounts' => $packageDirectoryBaseNames['packageCaseFoldDirectoryBaseNameStemCounts'],
            'entryNamesByPackageCaseFoldDirectoryBaseNameStem' => $packageDirectoryBaseNames['entryNamesByPackageCaseFoldDirectoryBaseNameStem'],
            'duplicatePackageCaseFoldDirectoryBaseNameStemCount' => $packageDirectoryBaseNames['duplicatePackageCaseFoldDirectoryBaseNameStemCount'],
            'duplicatePackageCaseFoldDirectoryBaseNameStems' => $packageDirectoryBaseNames['duplicatePackageCaseFoldDirectoryBaseNameStems'],
            'packageCaseFoldDirectoryBaseNameStems' => $packageDirectoryBaseNames['packageCaseFoldDirectoryBaseNameStems'],
            'packageZipSourceRecordDirectoryRootCount' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordDirectoryRootCount'],
            'packageZipSourceRecordDirectoryRootCounts' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordDirectoryRootCounts'],
            'packageZipSourceRecordDirectoryRootBytes' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordDirectoryRootBytes'],
            'packageZipSourceRecordEntryCount' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordEntryCount'],
            'packageZipSourceRecordByteLength' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordByteLength'],
            'packageZipSourceRecordLocalRecordByteLength' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordLocalRecordByteLength'],
            'packageZipSourceRecordCentralDirectoryRecordByteLength' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordCentralDirectoryRecordByteLength'],
            'packageZipSourceRecordLocalHeaderReviewFieldByteLength' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordLocalHeaderReviewFieldByteLength'],
            'packageZipSourceRecordCentralDirectoryReviewFieldByteLength' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordCentralDirectoryReviewFieldByteLength'],
            'packageZipSourceRecordReviewFieldByteLength' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordReviewFieldByteLength'],
            'packageZipSourceRecordDataDescriptorEntryCount' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordDataDescriptorEntryCount'],
            'packageZipSourceRecordDirectoryRoots' => $packageZipSourceRecordDirectoryRoots['packageZipSourceRecordDirectoryRoots'],
            'packageZipSourceRecordPackagePartExtensionCount' => $packageZipSourceRecordPackagePartExtensions['packageZipSourceRecordPackagePartExtensionCount'],
            'packageZipSourceRecordPackagePartExtensionCounts' => $packageZipSourceRecordPackagePartExtensions['packageZipSourceRecordPackagePartExtensionCounts'],
            'packageZipSourceRecordPackagePartExtensionBytes' => $packageZipSourceRecordPackagePartExtensions['packageZipSourceRecordPackagePartExtensionBytes'],
            'packageZipSourceRecordExtensionlessPackagePartCount' => $packageZipSourceRecordPackagePartExtensions['packageZipSourceRecordExtensionlessPackagePartCount'],
            'packageZipSourceRecordPackagePartExtensionDataDescriptorEntryCount' => $packageZipSourceRecordPackagePartExtensions['packageZipSourceRecordPackagePartExtensionDataDescriptorEntryCount'],
            'packageZipSourceRecordPackagePartExtensionIssueEntryCount' => $packageZipSourceRecordPackagePartExtensions['packageZipSourceRecordPackagePartExtensionIssueEntryCount'],
            'packageZipSourceRecordPackagePartExtensions' => $packageZipSourceRecordPackagePartExtensions['packageZipSourceRecordPackagePartExtensions'],
            'packageZipSourceRecordPackagePartBaseNameStemCount' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordPackagePartBaseNameStemCount'],
            'packageZipSourceRecordPackagePartBaseNameStemCounts' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordPackagePartBaseNameStemCounts'],
            'packageZipSourceRecordPackagePartBaseNameStemBytes' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordPackagePartBaseNameStemBytes'],
            'packageZipSourceRecordPackagePartBaseNameStemDataDescriptorEntryCount' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordPackagePartBaseNameStemDataDescriptorEntryCount'],
            'packageZipSourceRecordPackagePartBaseNameStemIssueEntryCount' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordPackagePartBaseNameStemIssueEntryCount'],
            'packageZipSourceRecordDuplicatePackagePartBaseNameStemCount' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordDuplicatePackagePartBaseNameStemCount'],
            'packageZipSourceRecordDuplicatePackagePartBaseNameStemEntryCount' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordDuplicatePackagePartBaseNameStemEntryCount'],
            'packageZipSourceRecordDuplicatePackagePartBaseNameStems' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordDuplicatePackagePartBaseNameStems'],
            'packageZipSourceRecordPackagePartBaseNameStems' => $packageZipSourceRecordPackagePartBaseNameStems['packageZipSourceRecordPackagePartBaseNameStems'],
            'packageZipSourceRecordCompressionMethodCount' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethodCount'],
            'packageZipSourceRecordCompressionMethodCounts' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethodCounts'],
            'packageZipSourceRecordCompressionMethodBytes' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethodBytes'],
            'packageZipSourceRecordCompressionMethodCompressedByteLengths' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethodCompressedByteLengths'],
            'packageZipSourceRecordCompressionMethodUncompressedByteLengths' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethodUncompressedByteLengths'],
            'packageZipSourceRecordCompressionMethodExpansionRatios' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethodExpansionRatios'],
            'packageZipSourceRecordCompressionMethodDataDescriptorEntryCount' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethodDataDescriptorEntryCount'],
            'packageZipSourceRecordCompressionMethodUnsupportedEntryCount' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethodUnsupportedEntryCount'],
            'packageZipSourceRecordCompressionMethods' => $packageZipSourceRecordCompressionMethods['packageZipSourceRecordCompressionMethods'],
            'packageZipSourceRecordRoleCount' => $packageZipSourceRecordRoles['packageZipSourceRecordRoleCount'],
            'packageZipSourceRecordRoleCounts' => $packageZipSourceRecordRoles['packageZipSourceRecordRoleCounts'],
            'packageZipSourceRecordRoleBytes' => $packageZipSourceRecordRoles['packageZipSourceRecordRoleBytes'],
            'packageZipSourceRecordRoleOccurrenceCount' => $packageZipSourceRecordRoles['packageZipSourceRecordRoleOccurrenceCount'],
            'packageZipSourceRecordRoleDataDescriptorOccurrenceCount' => $packageZipSourceRecordRoles['packageZipSourceRecordRoleDataDescriptorOccurrenceCount'],
            'packageZipSourceRecordRoleIssueOccurrenceCount' => $packageZipSourceRecordRoles['packageZipSourceRecordRoleIssueOccurrenceCount'],
            'packageZipSourceRecordRoles' => $packageZipSourceRecordRoles['packageZipSourceRecordRoles'],
            'packageCrc32EntryCount' => $packageCrc32s['packageCrc32EntryCount'],
            'packageCrc32Count' => $packageCrc32s['packageCrc32Count'],
            'packageDuplicateCrc32Count' => $packageCrc32s['packageDuplicateCrc32Count'],
            'packageDuplicateCrc32EntryCount' => $packageCrc32s['packageDuplicateCrc32EntryCount'],
            'packageCrc32Counts' => $packageCrc32s['packageCrc32Counts'],
            'packageCrc32ByteLengths' => $packageCrc32s['packageCrc32ByteLengths'],
            'packageCrc32CompressedByteLengths' => $packageCrc32s['packageCrc32CompressedByteLengths'],
            'packageCrc32SourceRecordBytes' => $packageCrc32s['packageCrc32SourceRecordBytes'],
            'entryNamesByPackageCrc32' => $packageCrc32s['entryNamesByPackageCrc32'],
            'packageCrc32Summaries' => $packageCrc32s['packageCrc32Summaries'],
            'packageDuplicateCrc32Summaries' => $packageCrc32s['packageDuplicateCrc32Summaries'],
            'packageZipTimestampSourceCount' => $packageZipTimestampSources['packageZipTimestampSourceCount'],
            'packageZipTimestampSourceCounts' => $packageZipTimestampSources['packageZipTimestampSourceCounts'],
            'packageZipTimestampSourceByteLengths' => $packageZipTimestampSources['packageZipTimestampSourceByteLengths'],
            'packageZipTimestampSourceRecordBytes' => $packageZipTimestampSources['packageZipTimestampSourceRecordBytes'],
            'packageZipTimestampSourceModifiedEntryCount' => $packageZipTimestampSources['packageZipTimestampSourceModifiedEntryCount'],
            'packageZipTimestampSourceIssueEntryCount' => $packageZipTimestampSources['packageZipTimestampSourceIssueEntryCount'],
            'packageZipTimestampSources' => $packageZipTimestampSources['packageZipTimestampSources'],
            'roleCounts' => $roleCounts,
            'undeclaredRoleCounts' => $undeclaredRoleCounts,
            'corePackagePartCount' => $corePackagePartCount,
            'mediaResourcePartCount' => $mediaResourcePartCount,
            'packageThumbnailPartCount' => $packageThumbnailPartCount,
            'packageSignaturePartCount' => $packageSignaturePartCount,
            'embeddedObjectPackageRootCount' => $embeddedObjectPackageRootCount,
            'embeddedObjectPackagePartCount' => $embeddedObjectPackagePartCount,
            'objectReplacementPartCount' => $objectReplacementPartCount,
            'scriptPackagePartCount' => $scriptPackagePartCount,
            'configurationPackagePartCount' => $configurationPackagePartCount,
            'fontPackagePartCount' => $fontPackagePartCount,
            'rdfMetadataPartCount' => $rdfMetadataPartCount,
            'layoutCachePartCount' => $layoutCachePartCount,
            'metaInfSidecarPackagePartCount' => $metaInfSidecarPackagePartCount,
            'databasePackagePartCount' => $databasePackagePartCount,
            'unsupportedCompressionMethodCount' => $unsupportedCompressionMethodCount,
            'totalByteLength' => $totalByteLength,
            'totalCompressedByteLength' => $totalCompressedByteLength,
            'exposableEntryCount' => $exposableEntryCount,
            'blockedEntryCount' => $blockedEntryCount,
            'exposableByteLength' => $exposableByteLength,
            'exposableCompressedByteLength' => $exposableCompressedByteLength,
            'blockedByteLength' => $blockedByteLength,
            'blockedCompressedByteLength' => $blockedCompressedByteLength,
            'unsupportedCompressionByteLength' => $unsupportedCompressionByteLength,
            'unsupportedCompressionCompressedByteLength' => $unsupportedCompressionCompressedByteLength,
            'byteExposurePolicyCounts' => $byteExposurePolicyCounts,
            'byteExposurePolicyItemCount' => count($byteExposurePolicyItems),
            'byteExposurePolicyItems' => $byteExposurePolicyItems,
            'byteExposurePolicyByteLengths' => $byteExposurePolicyByteLengths,
            'byteExposurePolicyCompressedByteLengths' => $byteExposurePolicyCompressedByteLengths,
            'packageByteHandoff' => $packageByteHandoff,
            'manifestMediaFamilyCounts' => $manifestMediaFamilyCounts,
            'manifestMediaFamilyByteLengths' => $manifestMediaFamilyByteLengths,
            'manifestMediaFamilyCompressedByteLengths' => $manifestMediaFamilyCompressedByteLengths,
            'packagePathKindCounts' => $packagePathKindCounts,
            'packageTopLevelSegmentCounts' => $packageTopLevelSegmentCounts,
            'packageCaseFoldTopLevelSegmentCount' => $packageCaseFoldTopLevelSegments['packageCaseFoldTopLevelSegmentCount'],
            'packageCaseFoldTopLevelSegmentCounts' => $packageCaseFoldTopLevelSegments['packageCaseFoldTopLevelSegmentCounts'],
            'duplicatePackageCaseFoldTopLevelSegmentCount' => $packageCaseFoldTopLevelSegments['duplicatePackageCaseFoldTopLevelSegmentCount'],
            'duplicatePackageCaseFoldTopLevelSegmentEntryCount' => $packageCaseFoldTopLevelSegments['duplicatePackageCaseFoldTopLevelSegmentEntryCount'],
            'duplicatePackageCaseFoldTopLevelSegments' => $packageCaseFoldTopLevelSegments['duplicatePackageCaseFoldTopLevelSegments'],
            'packageCaseFoldTopLevelSegments' => $packageCaseFoldTopLevelSegments['packageCaseFoldTopLevelSegments'],
            'packagePathExtensionCounts' => $packagePathExtensionCounts,
            'packageAreaCounts' => $packageAreaCounts,
            'packageAreaByteLengths' => $packageAreaByteLengths,
            'packageAreaCompressedByteLengths' => $packageAreaCompressedByteLengths,
            'packageAreaSummaries' => $packageAreaSummaries,
            'packagePathsByPackageArea' => $packagePathsByPackageArea,
            'packagePathDepthCounts' => $packagePathDepthCounts,
            'packagePathsByPathDepth' => $packagePathsByPathDepth,
            'maxPackagePathDepth' => $maxPackagePathDepth,
            'packagePathDepthRoleCounts' => $packagePathDepthRoleCounts,
            'entryNamesByPackagePathDepthRole' => $entryNamesByPackagePathDepthRole,
            'packagePathDepthByteExposurePolicyCounts' => $packagePathDepthByteExposurePolicyCounts,
            'entryNamesByPackagePathDepthByteExposurePolicy' => $entryNamesByPackagePathDepthByteExposurePolicy,
            'packagePathByteLengthBucketCount' => $packagePathByteLengths['packagePathByteLengthBucketCount'],
            'packagePathByteLengthBuckets' => $packagePathByteLengths['packagePathByteLengthBuckets'],
            'packagePathByteLengthBucketCounts' => $packagePathByteLengths['packagePathByteLengthBucketCounts'],
            'entryNamesByPackagePathByteLengthBucket' => $packagePathByteLengths['entryNamesByPackagePathByteLengthBucket'],
            'packagePathByteLengthRoleCounts' => $packagePathByteLengths['packagePathByteLengthRoleCounts'],
            'entryNamesByPackagePathByteLengthRole' => $packagePathByteLengths['entryNamesByPackagePathByteLengthRole'],
            'packagePathByteLengthByteExposurePolicyCounts' => $packagePathByteLengths['packagePathByteLengthByteExposurePolicyCounts'],
            'entryNamesByPackagePathByteLengthByteExposurePolicy' => $packagePathByteLengths['entryNamesByPackagePathByteLengthByteExposurePolicy'],
            'packagePathByteLengthBucketSummaries' => $packagePathByteLengths['packagePathByteLengthBucketSummaries'],
            'zipPackageManifestPathSegmentPositionRoleCounts' => $zipPackageManifestPathSegmentPositionRoleCounts,
            'entryNamesByZipPackageManifestPathSegmentPositionRole' => $entryNamesByZipPackageManifestPathSegmentPositionRole,
            'zipPackageManifestPathSegmentPositionByteExposurePolicyCounts' => $zipPackageManifestPathSegmentPositionByteExposurePolicyCounts,
            'entryNamesByZipPackageManifestPathSegmentPositionByteExposurePolicy' => $entryNamesByZipPackageManifestPathSegmentPositionByteExposurePolicy,
            'rawNameProvenanceEntryCount' => $rawNameProvenanceEntryCount,
            'legacyEncodedNameEntryCount' => $legacyEncodedNameEntryCount,
            'unicodePathExtraEntryCount' => $unicodePathExtraEntryCount,
            'decodedNameDiffersFromRawNameEntryCount' => $decodedNameDiffersFromRawNameEntryCount,
            'rawNameProvenanceEntries' => $rawNameProvenanceEntries,
            'packagePartXmlRootElementPartCount' => $packagePartXmlRootElements['partCount'],
            'packagePartXmlRootElementNames' => $packagePartXmlRootElements['rootNames'],
            'packagePartXmlRootElementNameCounts' => $packagePartXmlRootElements['rootNameCounts'],
            'packagePartXmlRootElementNamespaceUris' => $packagePartXmlRootElements['namespaceUris'],
            'packagePartXmlRootElementNamespaceUriCounts' => $packagePartXmlRootElements['namespaceUriCounts'],
            'packagePartXmlRootElementPartNames' => $packagePartXmlRootElements['partNames'],
            'packagePartXmlRootElements' => $packagePartXmlRootElements['roots'],
            'packagePartXmlRootElementsTruncated' => $packagePartXmlRootElements['truncated'],
            'packagePartXmlCdataSectionPartCount' => $packagePartXmlCdataSections['partCount'],
            'packagePartXmlCdataSectionCount' => $packagePartXmlCdataSections['sectionCount'],
            'packagePartXmlCdataSectionByteLength' => $packagePartXmlCdataSections['byteLength'],
            'packagePartXmlCdataSectionPartNames' => $packagePartXmlCdataSections['partNames'],
            'packagePartXmlCdataSections' => $packagePartXmlCdataSections['sections'],
            'packagePartXmlCdataSectionsTruncated' => $packagePartXmlCdataSections['truncated'],
            'packagePartXmlCommentPartCount' => $packagePartXmlComments['partCount'],
            'packagePartXmlCommentCount' => $packagePartXmlComments['commentCount'],
            'packagePartXmlCommentByteLength' => $packagePartXmlComments['byteLength'],
            'packagePartXmlCommentParentDepthCounts' => $packagePartXmlComments['parentDepthCounts'],
            'packagePartXmlCommentPartNames' => $packagePartXmlComments['partNames'],
            'packagePartXmlComments' => $packagePartXmlComments['comments'],
            'packagePartXmlCommentsTruncated' => $packagePartXmlComments['truncated'],
            'packagePartXmlProcessingInstructionPartCount' => $packagePartXmlProcessingInstructions['partCount'],
            'packagePartXmlProcessingInstructionCount' => $packagePartXmlProcessingInstructions['instructionCount'],
            'packagePartXmlProcessingInstructionDataByteLength' => $packagePartXmlProcessingInstructions['dataByteLength'],
            'packagePartXmlProcessingInstructionTargets' => $packagePartXmlProcessingInstructions['targets'],
            'packagePartXmlProcessingInstructionPartNames' => $packagePartXmlProcessingInstructions['partNames'],
            'packagePartXmlProcessingInstructions' => $packagePartXmlProcessingInstructions['instructions'],
            'packagePartXmlProcessingInstructionsTruncated' => $packagePartXmlProcessingInstructions['truncated'],
            'namePolicy' => $namePolicy,
            'zipNamePolicyValid' => ($namePolicy['valid'] ?? false) === true,
            'zipNamePolicyIssueCount' => $namePolicy['issueCount'] ?? 0,
            'zipNamePolicyIssueCodes' => $namePolicy['issueCodes'] ?? [],
            'zipPathHierarchyCollisionEntryCount' => $namePolicy['pathHierarchyCollisionEntryCount'] ?? 0,
            'zipCaseInsensitiveNameCollisionGroupCount' => $namePolicy['caseInsensitiveNameCollisionGroupCount'] ?? 0,
            'zipCaseInsensitiveNameCollisionEntryCount' => $namePolicy['caseInsensitiveNameCollisionEntryCount'] ?? 0,
            'zipRawNameCollisionGroupCount' => $namePolicy['rawNameCollisionGroupCount'] ?? 0,
            'zipRawNameCollisionEntryCount' => $namePolicy['rawNameCollisionEntryCount'] ?? 0,
            'zipRawNameProvenanceEntryCount' => $namePolicy['rawNameProvenanceEntryCount'] ?? 0,
            'zipNameHygieneReviewEntryCount' => $namePolicy['nameHygieneReviewEntryCount'] ?? 0,
            'zipNameHygieneLeadingOrTrailingWhitespaceEntryCount' => $namePolicy['nameHygieneLeadingOrTrailingWhitespaceEntryCount'] ?? 0,
            'zipNameHygieneTrailingDotSegmentEntryCount' => $namePolicy['nameHygieneTrailingDotSegmentEntryCount'] ?? 0,
            'zipNameHygieneWindowsReservedNameEntryCount' => $namePolicy['nameHygieneWindowsReservedNameEntryCount'] ?? 0,
            'zipNameHygieneWindowsAlternateDataStreamEntryCount' => $namePolicy['nameHygieneWindowsAlternateDataStreamEntryCount'] ?? 0,
            'zipNameHygieneUnicodeFormatControlEntryCount' => $namePolicy['nameHygieneUnicodeFormatControlEntryCount'] ?? 0,
            'zipNameHygieneUnicodeBidiControlEntryCount' => $namePolicy['nameHygieneUnicodeBidiControlEntryCount'] ?? 0,
            'byteExposurePolicy' => 'odf-package-inventory-metadata-only',
            'canExposeBytes' => false,
            'roles' => array_keys($roleCounts),
            'centralDirectoryOrderMatchesLocalHeaderOrder' => !$localHeaderOrder['hasCentralDirectoryOrderMismatch'],
            'centralDirectoryOrderMismatchRoleCount' => $centralDirectoryOrderMismatchRoles['roleCount'],
            'centralDirectoryOrderMismatchRoleCounts' => $centralDirectoryOrderMismatchRoles['roleCounts'],
            'centralDirectoryOrderMismatchRoleByteLengths' => $centralDirectoryOrderMismatchRoles['roleByteLengths'],
            'centralDirectoryOrderMismatchRoleCompressedByteLengths' => $centralDirectoryOrderMismatchRoles['roleCompressedByteLengths'],
            'centralDirectoryOrderMismatchRoleSummaries' => $centralDirectoryOrderMismatchRoles['roleSummaries'],
            'zipPackageManifest' => $packageManifest,
            'zipPackageManifestSha256' => $packageManifest['manifestSha256'],
            ...$zipPackageManifestSummary,
            'packageSource' => $packageManifest['packageSource'],
            'archiveLength' => $packageManifest['archiveLength'],
            'archiveSha256' => $packageManifest['archiveSha256'],
            'centralDirectoryOffset' => $packageManifest['centralDirectoryOffset'],
            'centralDirectoryBytes' => $packageManifest['centralDirectoryBytes'],
            'centralDirectoryEnd' => $packageManifest['centralDirectoryEnd'],
            'centralDirectorySha256' => $packageManifest['centralDirectorySha256'],
            'centralDirectoryToEocdGapOffset' => $packageManifest['centralDirectoryToEocdGapOffset'],
            'centralDirectoryToEocdGapBytes' => $packageManifest['centralDirectoryToEocdGapBytes'],
            'centralDirectoryToEocdGapSha256' => $packageManifest['centralDirectoryToEocdGapSha256'],
            'endOfCentralDirectoryOffset' => $packageManifest['endOfCentralDirectoryOffset'],
            'endOfCentralDirectoryBytes' => $packageManifest['endOfCentralDirectoryBytes'],
            'endOfCentralDirectoryEnd' => $packageManifest['endOfCentralDirectoryEnd'],
            'endOfCentralDirectorySha256' => $packageManifest['endOfCentralDirectorySha256'],
            'packageCommentOffset' => $packageManifest['packageCommentOffset'],
            'packageCommentBytes' => $packageManifest['packageCommentBytes'],
            'packageCommentSha256' => $packageManifest['packageCommentSha256'],
            'hasCentralDirectorySignature' => $packageManifest['hasCentralDirectorySignature'],
            'centralDirectorySignatureOffset' => $packageManifest['centralDirectorySignatureOffset'],
            'centralDirectorySignatureDataOffset' => $packageManifest['centralDirectorySignatureDataOffset'],
            'centralDirectorySignatureEnd' => $packageManifest['centralDirectorySignatureEnd'],
            'centralDirectorySignatureBytes' => $packageManifest['centralDirectorySignatureBytes'],
            'centralDirectorySignatureRecordBytes' => $packageManifest['centralDirectorySignatureRecordBytes'],
            'centralDirectorySignaturePreviewHex' => $packageManifest['centralDirectorySignaturePreviewHex'],
            'centralDirectorySignaturePreviewByteCount' => $packageManifest['centralDirectorySignaturePreviewByteCount'],
            'centralDirectorySignatureSha256' => $packageManifest['centralDirectorySignatureSha256'],
            'centralDirectorySignatureLocation' => $packageManifest['centralDirectorySignatureLocation'],
            'centralDirectorySignatureVerification' => $packageManifest['centralDirectorySignatureVerification'],
            'centralDirectorySignatureByteExposurePolicy' => $packageManifest['centralDirectorySignatureByteExposurePolicy'],
            'centralDirectorySignatureCanExposeBytes' => $packageManifest['centralDirectorySignatureCanExposeBytes'],
            'roleByteLengths' => $roleByteLengths,
            'roleCompressedByteLengths' => $roleCompressedByteLengths,
            'unsupportedCompressionPartNames' => array_keys($unsupportedCompressionPartNames),
            'localHeaderOrder' => $localHeaderOrder,
            'localHeaderMetadataEntryCount' => $localHeaderMetadata['entryCount'],
            'localHeaderMetadataTotalEntryCount' => $localHeaderMetadata['totalEntryCount'],
            'localHeaderMetadataCentralDirectoryOffset' => $localHeaderMetadata['centralDirectoryOffset'],
            'localHeaderMetadataCentralDirectoryBytes' => $localHeaderMetadata['centralDirectorySize'],
            'localHeaderMetadataIsSupportedByBoundedReader' => $localHeaderMetadata['isSupportedByBoundedReader'],
            'localHeaderMetadataIssueCodes' => $localHeaderMetadata['issues'],
            'localHeaderMetadataMismatchEntryCount' => $localHeaderMetadata['mismatchedEntryCount'],
            'localHeaderMetadataMismatchedEntries' => self::zipLocalHeaderMetadataMismatchProvenance($localHeaderMetadata),
            'compressionMethods' => $compressionMethods,
            'generalPurposeFlags' => $generalPurposeFlags,
            'generalPurposeFlagEntryCount' => $generalPurposeFlags['entryCount'],
            'generalPurposeFlagSupportedEntryCount' => $generalPurposeFlags['supportedEntryCount'],
            'unsupportedGeneralPurposeFlagEntryCount' => $generalPurposeFlags['unsupportedFlagEntryCount'],
            'utf8NameGeneralPurposeFlagEntryCount' => $generalPurposeFlags['utf8NameEntryCount'],
            'dataDescriptorGeneralPurposeFlagEntryCount' => $generalPurposeFlags['dataDescriptorEntryCount'],
            'deflateOptionGeneralPurposeFlagEntryCount' => $generalPurposeFlags['deflateOptionEntryCount'],
            'strictGeneralPurposeFlagReviewEntryCount' => $generalPurposeFlags['strictReviewEntryCount'],
            'unsupportedGeneralPurposeFlagEntries' => $generalPurposeFlags['unsupportedEntries'],
            'strictGeneralPurposeFlagReviewEntries' => $generalPurposeFlags['strictReviewEntries'],
            'extraFields' => $extraFields,
            'hasZipExtraFields' => $extraFields['extraFieldEntryCount'] > 0,
            'extraFieldEntryCount' => $extraFields['extraFieldEntryCount'],
            'duplicateExtraFieldEntryCount' => $extraFields['duplicateExtraFieldEntryCount'],
            'duplicateCentralExtraFieldEntryCount' => $extraFields['duplicateCentralExtraFieldEntryCount'],
            'duplicateLocalExtraFieldEntryCount' => $extraFields['duplicateLocalExtraFieldEntryCount'],
            'mismatchedExtraFieldEntryCount' => $extraFields['mismatchedExtraFieldEntryCount'],
            'mismatchedExtraFieldValueEntryCount' => $extraFields['mismatchedExtraFieldValueEntryCount'],
            'centralOnlyExtraFieldEntryCount' => $extraFields['centralOnlyExtraFieldEntryCount'],
            'localOnlyExtraFieldEntryCount' => $extraFields['localOnlyExtraFieldEntryCount'],
            'extraFieldIdCount' => $extraFields['extraFieldIdCount'],
            'centralExtraFieldIdCount' => $extraFields['centralExtraFieldIdCount'],
            'localExtraFieldIdCount' => $extraFields['localExtraFieldIdCount'],
            'sharedExtraFieldIdCount' => $extraFields['sharedExtraFieldIdCount'],
            'centralOnlyExtraFieldIdCount' => $extraFields['centralOnlyExtraFieldIdCount'],
            'localOnlyExtraFieldIdCount' => $extraFields['localOnlyExtraFieldIdCount'],
            'extraFieldIdUsage' => $extraFields['extraFieldIdUsage'],
            'extraFieldIdHexes' => self::zipExtraFieldUsageIdHexes($extraFields),
            'centralExtraFieldIdHexes' => self::zipExtraFieldUsageIdHexes($extraFields, 'appearsInCentral'),
            'localExtraFieldIdHexes' => self::zipExtraFieldUsageIdHexes($extraFields, 'appearsInLocal'),
            'sharedExtraFieldIdHexes' => self::zipExtraFieldUsageIdHexes($extraFields, 'appearsInBoth'),
            'centralOnlyExtraFieldIdHexes' => self::zipExtraFieldUsageIdHexes($extraFields, 'appearsOnlyInCentral'),
            'localOnlyExtraFieldIdHexes' => self::zipExtraFieldUsageIdHexes($extraFields, 'appearsOnlyInLocal'),
            'unixOwners' => $unixOwners,
            'hasUnixOwnerMetadata' => $unixOwners['ownerMetadataEntryCount'] > 0,
            'hasMismatchedUnixOwnerMetadata' => $unixOwners['mismatchedOwnerMetadataEntryCount'] > 0,
            'unixOwnerMetadataEntryCount' => $unixOwners['ownerMetadataEntryCount'],
            'centralUnixOwnerMetadataEntryCount' => $unixOwners['centralOwnerMetadataEntryCount'],
            'localUnixOwnerMetadataEntryCount' => $unixOwners['localOwnerMetadataEntryCount'],
            'mismatchedUnixOwnerMetadataEntryCount' => $unixOwners['mismatchedOwnerMetadataEntryCount'],
            'unixOwnerMetadataIssueCodes' => array_values(array_filter([
                $unixOwners['ownerMetadataEntryCount'] > 0 ? 'unix-owner-extra-fields' : null,
                $unixOwners['mismatchedOwnerMetadataEntryCount'] > 0 ? 'unix-uid-gid-mismatch' : null,
            ])),
            'unixOwnerMetadataEntries' => $unixOwners['ownerMetadataEntries'],
            'mismatchedUnixOwnerMetadataEntries' => $unixOwners['mismatchedOwnerMetadataEntries'],
            'unixOwnerMetadataByteExposurePolicy' => 'zip-unix-owner-metadata-only',
            'unixOwnerMetadataCanExposeBytes' => false,
            'extraFieldIdRoleCount' => $packageExtraFields['extraFieldIdRoleCount'],
            'extraFieldIdRoleCounts' => $packageExtraFields['extraFieldIdRoleCounts'],
            'entryNamesByExtraFieldIdRole' => $packageExtraFields['entryNamesByExtraFieldIdRole'],
            'extraFieldIdManifestMediaFamilyCount' => $packageExtraFields['extraFieldIdManifestMediaFamilyCount'],
            'extraFieldIdManifestMediaFamilyCounts' => $packageExtraFields['extraFieldIdManifestMediaFamilyCounts'],
            'entryNamesByExtraFieldIdManifestMediaFamily' => $packageExtraFields['entryNamesByExtraFieldIdManifestMediaFamily'],
            'centralOnlyExtraFieldIdRoleCount' => $packageExtraFields['centralOnlyExtraFieldIdRoleCount'],
            'centralOnlyExtraFieldIdRoleCounts' => $packageExtraFields['centralOnlyExtraFieldIdRoleCounts'],
            'entryNamesByCentralOnlyExtraFieldIdRole' => $packageExtraFields['entryNamesByCentralOnlyExtraFieldIdRole'],
            'centralOnlyExtraFieldIdManifestMediaFamilyCount' => $packageExtraFields['centralOnlyExtraFieldIdManifestMediaFamilyCount'],
            'centralOnlyExtraFieldIdManifestMediaFamilyCounts' => $packageExtraFields['centralOnlyExtraFieldIdManifestMediaFamilyCounts'],
            'entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily' => $packageExtraFields['entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily'],
            'localOnlyExtraFieldIdRoleCount' => $packageExtraFields['localOnlyExtraFieldIdRoleCount'],
            'localOnlyExtraFieldIdRoleCounts' => $packageExtraFields['localOnlyExtraFieldIdRoleCounts'],
            'entryNamesByLocalOnlyExtraFieldIdRole' => $packageExtraFields['entryNamesByLocalOnlyExtraFieldIdRole'],
            'localOnlyExtraFieldIdManifestMediaFamilyCount' => $packageExtraFields['localOnlyExtraFieldIdManifestMediaFamilyCount'],
            'localOnlyExtraFieldIdManifestMediaFamilyCounts' => $packageExtraFields['localOnlyExtraFieldIdManifestMediaFamilyCounts'],
            'entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily' => $packageExtraFields['entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily'],
            'mismatchedExtraFieldValueIdRoleCount' => $packageExtraFields['mismatchedExtraFieldValueIdRoleCount'],
            'mismatchedExtraFieldValueIdRoleCounts' => $packageExtraFields['mismatchedExtraFieldValueIdRoleCounts'],
            'entryNamesByMismatchedExtraFieldValueIdRole' => $packageExtraFields['entryNamesByMismatchedExtraFieldValueIdRole'],
            'mismatchedExtraFieldValueIdManifestMediaFamilyCount' => $packageExtraFields['mismatchedExtraFieldValueIdManifestMediaFamilyCount'],
            'mismatchedExtraFieldValueIdManifestMediaFamilyCounts' => $packageExtraFields['mismatchedExtraFieldValueIdManifestMediaFamilyCounts'],
            'entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily' => $packageExtraFields['entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily'],
            'extraFieldIdRoleSummaryCount' => $packageExtraFields['extraFieldIdRoleSummaryCount'],
            'extraFieldIdRoleSummaries' => $packageExtraFields['extraFieldIdRoleSummaries'],
            'comments' => $comments,
            'hasPackageComment' => ($comments['hasPackageComment'] ?? false) === true,
            'hasEntryComments' => ($comments['hasEntryComments'] ?? false) === true,
            'entryCommentCount' => is_int($comments['entryCommentCount'] ?? null) ? $comments['entryCommentCount'] : 0,
            'commentedEntryNames' => is_array($comments['commentedEntryNames'] ?? null) ? $comments['commentedEntryNames'] : [],
            'modificationTimes' => $modificationTimes,
            'zipTimestampEntryCount' => $modificationTimes['timestampEntryCount'],
            'zipDosTimestampEntryCount' => $modificationTimes['dosTimestampEntryCount'],
            'zipExtendedTimestampEntryCount' => $modificationTimes['extendedTimestampEntryCount'],
            'zipNtfsTimestampEntryCount' => $modificationTimes['ntfsTimestampEntryCount'],
            'zipInvalidDosTimestampEntryCount' => $modificationTimes['invalidDosTimestampEntryCount'],
            'zipInvalidDosTimestampEntries' => $modificationTimes['invalidDosTimestampEntries'],
            'platformMetadata' => $platformMetadata,
            'platformMetadataEntryCount' => $platformMetadata['platformMetadataEntryCount'],
            'nameHygiene' => $nameHygiene,
            'nameHygieneReviewEntryCount' => $nameHygiene['reviewEntryCount'],
            'nameHygieneLeadingOrTrailingWhitespaceEntryCount' => $nameHygiene['leadingOrTrailingWhitespaceEntryCount'],
            'nameHygieneTrailingDotSegmentEntryCount' => $nameHygiene['trailingDotSegmentEntryCount'],
            'nameHygieneWindowsReservedNameEntryCount' => $nameHygiene['windowsReservedNameEntryCount'],
            'nameHygieneWindowsAlternateDataStreamEntryCount' => $nameHygiene['windowsAlternateDataStreamEntryCount'],
            'nameHygieneUnicodeFormatControlEntryCount' => $nameHygiene['unicodeFormatControlEntryCount'],
            'nameHygieneUnicodeBidiControlEntryCount' => $nameHygiene['unicodeBidiControlEntryCount'],
            'creatorHostSystems' => $creatorHostSystems,
            'knownCreatorHostSystemEntryCount' => $creatorHostSystems['knownHostSystemEntryCount'],
            'unknownCreatorHostSystemEntryCount' => $creatorHostSystems['unknownHostSystemEntryCount'],
            'creatorVersionBelowNeededEntryCount' => $creatorHostSystems['creatorVersionBelowNeededEntryCount'],
            'permissions' => $permissions,
            'unixModeEntryCount' => $permissions['unixModeEntryCount'],
            'executableFileCount' => $permissions['executableFileCount'],
            'writablePermissionEntryCount' => $permissions['writablePermissionEntryCount'],
            'dosAttributes' => $dosAttributes,
            'dosAttributeEntryCount' => $dosAttributes['dosAttributeEntryCount'],
            'hiddenSystemOrVolumeLabelEntryCount' => $dosAttributes['hiddenSystemOrVolumeLabelEntryCount'],
            'internalAttributes' => $internalAttributes,
            'internalAttributeEntryCount' => $internalAttributes['internalAttributeEntryCount'],
            'parts' => $parts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function zipNamePolicyProvenance(ZipPackage $package): array
    {
        $pathHierarchy = $package->pathHierarchyPreflight();
        $caseInsensitiveNames = $package->caseInsensitiveNamePreflight();
        $rawNames = $package->rawNamePreflight();
        $nameHygiene = $package->nameHygienePreflight();
        $issueCodes = [];
        if ((int) ($pathHierarchy['collisionEntryCount'] ?? 0) > 0) {
            $issueCodes[] = 'path-hierarchy-collisions';
        }
        if ((int) ($caseInsensitiveNames['collisionEntryCount'] ?? 0) > 0) {
            $issueCodes[] = 'case-insensitive-name-collisions';
        }
        if ((int) ($rawNames['collisionEntryCount'] ?? 0) > 0) {
            $issueCodes[] = 'raw-name-collisions';
        }
        if ((int) ($rawNames['provenanceEntryCount'] ?? 0) > 0) {
            $issueCodes[] = 'raw-name-provenance-review-entries';
        }
        if ((int) ($nameHygiene['reviewEntryCount'] ?? 0) > 0) {
            $issueCodes[] = 'name-hygiene-review-entries';
        }

        return [
            'present' => true,
            'entryCount' => count($package->entries()),
            'valid' => $issueCodes === [],
            'issueCount' => count($issueCodes),
            'issueCodes' => $issueCodes,
            'pathHierarchyCollisionEntryCount' => (int) ($pathHierarchy['collisionEntryCount'] ?? 0),
            'pathHierarchyCollisionEntries' => $pathHierarchy['collisionEntries'] ?? [],
            'caseInsensitiveNameCollisionGroupCount' => (int) ($caseInsensitiveNames['collisionGroupCount'] ?? 0),
            'caseInsensitiveNameCollisionEntryCount' => (int) ($caseInsensitiveNames['collisionEntryCount'] ?? 0),
            'caseInsensitiveNameCollisionGroups' => $caseInsensitiveNames['collisionGroups'] ?? [],
            'caseInsensitiveNameCollisionEntries' => $caseInsensitiveNames['collisionEntries'] ?? [],
            'rawNameCollisionGroupCount' => (int) ($rawNames['collisionGroupCount'] ?? 0),
            'rawNameCollisionEntryCount' => (int) ($rawNames['collisionEntryCount'] ?? 0),
            'rawNameProvenanceEntryCount' => (int) ($rawNames['provenanceEntryCount'] ?? 0),
            'rawNameLegacyEncodedEntryCount' => (int) ($rawNames['legacyEncodedNameEntryCount'] ?? 0),
            'rawNameUnicodePathExtraEntryCount' => (int) ($rawNames['unicodePathExtraEntryCount'] ?? 0),
            'rawNameDecodedDiffersEntryCount' => (int) ($rawNames['decodedNameDiffersFromRawNameEntryCount'] ?? 0),
            'rawNameCollisionGroups' => $rawNames['collisionGroups'] ?? [],
            'rawNameCollisionEntries' => $rawNames['collisionEntries'] ?? [],
            'rawNameProvenanceEntries' => $rawNames['provenanceEntries'] ?? [],
            'nameHygieneReviewEntryCount' => (int) ($nameHygiene['reviewEntryCount'] ?? 0),
            'nameHygieneLeadingOrTrailingWhitespaceEntryCount' => (int) ($nameHygiene['leadingOrTrailingWhitespaceEntryCount'] ?? 0),
            'nameHygieneTrailingDotSegmentEntryCount' => (int) ($nameHygiene['trailingDotSegmentEntryCount'] ?? 0),
            'nameHygieneWindowsReservedNameEntryCount' => (int) ($nameHygiene['windowsReservedNameEntryCount'] ?? 0),
            'nameHygieneWindowsAlternateDataStreamEntryCount' => (int) ($nameHygiene['windowsAlternateDataStreamEntryCount'] ?? 0),
            'nameHygieneUnicodeFormatControlEntryCount' => (int) ($nameHygiene['unicodeFormatControlEntryCount'] ?? 0),
            'nameHygieneUnicodeBidiControlEntryCount' => (int) ($nameHygiene['unicodeBidiControlEntryCount'] ?? 0),
            'nameHygieneReviewEntries' => $nameHygiene['reviewEntries'] ?? [],
            'byteExposurePolicy' => 'odf-zip-name-policy-metadata-only',
            'canExposeBytes' => false,
        ];
    }

    /**
     * @param array{partCount:int, rootNames:list<string>, rootNameCounts:array<string, int>, namespaceUris:list<string>, namespaceUriCounts:array<string, int>, partNames:list<string>, roots:list<array<string, mixed>>, truncated:bool} $summary
     * @param array{hasRootElement:bool, name:?string, localName:?string, prefix:?string, namespaceUri:?string, path:?string, attributeCount:int, attributeNames:list<string>, namespaceDeclarationCount:int, namespaceDeclarationNames:list<string>} $metadata
     */
    private static function recordPackagePartXmlRootElementSummary(array &$summary, string $partName, array $metadata): void
    {
        if (($metadata['hasRootElement'] ?? false) !== true) {
            return;
        }

        ++$summary['partCount'];
        $summary['partNames'][] = $partName;
        $name = $metadata['name'] ?? null;
        if (is_string($name) && $name !== '') {
            $summary['rootNameCounts'][$name] = ($summary['rootNameCounts'][$name] ?? 0) + 1;
            if (!in_array($name, $summary['rootNames'], true)) {
                $summary['rootNames'][] = $name;
            }
        }
        $namespaceUri = $metadata['namespaceUri'] ?? null;
        if (is_string($namespaceUri) && $namespaceUri !== '') {
            $summary['namespaceUriCounts'][$namespaceUri] = ($summary['namespaceUriCounts'][$namespaceUri] ?? 0) + 1;
            if (!in_array($namespaceUri, $summary['namespaceUris'], true)) {
                $summary['namespaceUris'][] = $namespaceUri;
            }
        }

        if (count($summary['roots']) >= 64) {
            $summary['truncated'] = true;
        } else {
            $summary['roots'][] = ['partName' => $partName] + $metadata;
        }

        sort($summary['partNames'], SORT_STRING);
        sort($summary['rootNames'], SORT_STRING);
        sort($summary['namespaceUris'], SORT_STRING);
        ksort($summary['rootNameCounts'], SORT_STRING);
        ksort($summary['namespaceUriCounts'], SORT_STRING);
    }

    /**
     * @return array{hasRootElement:bool, name:?string, localName:?string, prefix:?string, namespaceUri:?string, path:?string, attributeCount:int, attributeNames:list<string>, namespaceDeclarationCount:int, namespaceDeclarationNames:list<string>}
     */
    private static function packagePartXmlRootElementMetadata(
        ZipPackage $package,
        ZipPackageEntry $entry,
        string $mediaTypeBase
    ): array {
        $empty = [
            'hasRootElement' => false,
            'name' => null,
            'localName' => null,
            'prefix' => null,
            'namespaceUri' => null,
            'path' => null,
            'attributeCount' => 0,
            'attributeNames' => [],
            'namespaceDeclarationCount' => 0,
            'namespaceDeclarationNames' => [],
        ];
        if (
            $entry->isDirectory()
            || !in_array($entry->compressionMethod, [0, 8], true)
            || !self::isXmlPackagePart($entry->name, $mediaTypeBase, self::packagePartExtension($entry->name))
        ) {
            return $empty;
        }

        try {
            $dom = self::loadXmlForPackageProvenance($package->read($entry->name), $entry->name);
        } catch (\Throwable) {
            return $empty;
        }
        if (!$dom instanceof \DOMDocument || !$dom->documentElement instanceof \DOMElement) {
            return $empty;
        }

        $root = $dom->documentElement;
        $attributeNames = self::xmlRootElementAttributeNames($root);
        $namespaceDeclarationNames = self::xmlRootElementNamespaceDeclarationNames($root);

        return [
            'hasRootElement' => true,
            'name' => self::qualifiedDomName($root),
            'localName' => self::optionalString($root->localName),
            'prefix' => self::optionalString($root->prefix),
            'namespaceUri' => self::optionalString($root->namespaceURI),
            'path' => self::domElementPath($root),
            'attributeCount' => count($attributeNames),
            'attributeNames' => $attributeNames,
            'namespaceDeclarationCount' => count($namespaceDeclarationNames),
            'namespaceDeclarationNames' => $namespaceDeclarationNames,
        ];
    }

    /**
     * @return list<string>
     */
    private static function xmlRootElementAttributeNames(\DOMElement $root): array
    {
        $names = [];
        foreach ($root->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr || $attribute->namespaceURI === self::XMLNS_NAMESPACE) {
                continue;
            }

            $names[] = self::qualifiedDomAttributeName($attribute);
        }

        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function xmlRootElementNamespaceDeclarationNames(\DOMElement $root): array
    {
        if (!$root->ownerDocument instanceof \DOMDocument) {
            return [];
        }

        $nodes = (new \DOMXPath($root->ownerDocument))->query('namespace::*', $root);
        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $names = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMNameSpaceNode || $node->nodeName === 'xmlns:xml') {
                continue;
            }

            $names[] = $node->nodeName;
        }

        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        return $names;
    }

    private static function qualifiedDomAttributeName(\DOMAttr $attribute): string
    {
        return $attribute->prefix !== null && $attribute->prefix !== ''
            ? $attribute->prefix . ':' . $attribute->localName
            : $attribute->name;
    }

    /**
     * @param array{partCount:int, sectionCount:int, byteLength:int, partNames:list<string>, sections:list<array<string, mixed>>, truncated:bool} $summary
     * @param array{count:int, byteLength:int, sections:list<array<string, mixed>>, truncated:bool} $metadata
     */
    private static function recordPackagePartXmlCdataSectionSummary(array &$summary, string $partName, array $metadata): void
    {
        $sectionCount = (int) ($metadata['count'] ?? 0);
        if ($sectionCount <= 0) {
            return;
        }

        ++$summary['partCount'];
        $summary['sectionCount'] += $sectionCount;
        $summary['byteLength'] += (int) ($metadata['byteLength'] ?? 0);
        $summary['partNames'][] = $partName;
        if (($metadata['truncated'] ?? false) === true) {
            $summary['truncated'] = true;
        }

        $summaryLimit = 64;
        foreach (($metadata['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            if (count($summary['sections']) >= $summaryLimit) {
                $summary['truncated'] = true;
                continue;
            }

            $summary['sections'][] = ['partName' => $partName] + $section;
        }

        sort($summary['partNames'], SORT_STRING);
    }

    /**
     * @return array{count:int, byteLength:int, sections:list<array<string, mixed>>, truncated:bool}
     */
    private static function packagePartXmlCdataSectionMetadata(
        ZipPackage $package,
        ZipPackageEntry $entry,
        string $mediaTypeBase
    ): array {
        $empty = [
            'count' => 0,
            'byteLength' => 0,
            'sections' => [],
            'truncated' => false,
        ];
        if (
            $entry->isDirectory()
            || !in_array($entry->compressionMethod, [0, 8], true)
            || !self::isXmlPackagePart($entry->name, $mediaTypeBase, self::packagePartExtension($entry->name))
        ) {
            return $empty;
        }

        try {
            $dom = self::loadXmlForPackageProvenance($package->read($entry->name), $entry->name);
        } catch (\Throwable) {
            return $empty;
        }
        if (!$dom instanceof \DOMDocument) {
            return $empty;
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//text()');
        if (!$nodes instanceof \DOMNodeList) {
            return $empty;
        }

        $sections = [];
        $count = 0;
        $byteLength = 0;
        $truncated = false;
        $itemLimit = 32;
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMNode || $node->nodeType !== XML_CDATA_SECTION_NODE) {
                continue;
            }

            ++$count;
            $value = (string) $node->nodeValue;
            $valueByteLength = strlen($value);
            $byteLength += $valueByteLength;
            if (count($sections) >= $itemLimit) {
                $truncated = true;
                continue;
            }

            $parent = $node->parentNode instanceof \DOMElement ? $node->parentNode : null;
            $parentPath = self::domElementPath($parent);
            $sections[] = [
                'index' => $count - 1,
                'parentPath' => $parentPath,
                'parentDepth' => self::domElementPathDepth($parentPath),
                'byteLength' => $valueByteLength,
                'crc32' => sprintf('%08x', crc32($value)),
                'sha256' => hash('sha256', $value),
            ];
        }

        return [
            'count' => $count,
            'byteLength' => $byteLength,
            'sections' => $sections,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param array{partCount:int, commentCount:int, byteLength:int, parentDepthCounts:array<int, int>, partNames:list<string>, comments:list<array<string, mixed>>, truncated:bool} $summary
     * @param array{count:int, byteLength:int, parentDepthCounts:array<int, int>, comments:list<array<string, mixed>>, truncated:bool} $metadata
     */
    private static function recordPackagePartXmlCommentSummary(array &$summary, string $partName, array $metadata): void
    {
        $commentCount = (int) ($metadata['count'] ?? 0);
        if ($commentCount <= 0) {
            return;
        }

        ++$summary['partCount'];
        $summary['commentCount'] += $commentCount;
        $summary['byteLength'] += (int) ($metadata['byteLength'] ?? 0);
        $summary['partNames'][] = $partName;
        foreach (($metadata['parentDepthCounts'] ?? []) as $depth => $count) {
            if (!is_int($depth) && !(is_string($depth) && ctype_digit($depth))) {
                continue;
            }

            $depth = (int) $depth;
            $summary['parentDepthCounts'][$depth] = ($summary['parentDepthCounts'][$depth] ?? 0) + (int) $count;
        }
        if (($metadata['truncated'] ?? false) === true) {
            $summary['truncated'] = true;
        }

        $summaryLimit = 64;
        foreach (($metadata['comments'] ?? []) as $comment) {
            if (!is_array($comment)) {
                continue;
            }
            if (count($summary['comments']) >= $summaryLimit) {
                $summary['truncated'] = true;
                continue;
            }

            $summary['comments'][] = ['partName' => $partName] + $comment;
        }

        sort($summary['partNames'], SORT_STRING);
        ksort($summary['parentDepthCounts'], SORT_NUMERIC);
    }

    /**
     * @return array{count:int, byteLength:int, parentDepthCounts:array<int, int>, comments:list<array<string, mixed>>, truncated:bool}
     */
    private static function packagePartXmlCommentMetadata(
        ZipPackage $package,
        ZipPackageEntry $entry,
        string $mediaTypeBase
    ): array {
        $empty = [
            'count' => 0,
            'byteLength' => 0,
            'parentDepthCounts' => [],
            'comments' => [],
            'truncated' => false,
        ];
        if (
            $entry->isDirectory()
            || !in_array($entry->compressionMethod, [0, 8], true)
            || !self::isXmlPackagePart($entry->name, $mediaTypeBase, self::packagePartExtension($entry->name))
        ) {
            return $empty;
        }

        try {
            $dom = self::loadXmlForPackageProvenance($package->read($entry->name), $entry->name);
        } catch (\Throwable) {
            return $empty;
        }
        if (!$dom instanceof \DOMDocument) {
            return $empty;
        }

        $comments = [];
        $count = 0;
        $byteLength = 0;
        $parentDepthCounts = [];
        $truncated = false;
        $itemLimit = 32;
        $walk = static function (\DOMNode $node) use (&$walk, &$comments, &$count, &$byteLength, &$parentDepthCounts, &$truncated, $itemLimit): void {
            if ($node instanceof \DOMComment) {
                ++$count;
                $value = (string) $node->nodeValue;
                $valueByteLength = strlen($value);
                $byteLength += $valueByteLength;
                $parent = $node->parentNode instanceof \DOMElement ? $node->parentNode : null;
                $parentPath = self::domElementPath($parent);
                $parentDepth = self::domElementPathDepth($parentPath);
                $parentDepthCounts[$parentDepth] = ($parentDepthCounts[$parentDepth] ?? 0) + 1;
                if (count($comments) >= $itemLimit) {
                    $truncated = true;
                } else {
                    $comments[] = [
                        'index' => $count - 1,
                        'parentPath' => $parentPath,
                        'parentDepth' => $parentDepth,
                        'byteLength' => $valueByteLength,
                        'crc32' => sprintf('%08x', crc32($value)),
                        'sha256' => hash('sha256', $value),
                    ];
                }
            }

            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMNode) {
                    $walk($child);
                }
            }
        };
        $walk($dom);
        ksort($parentDepthCounts, SORT_NUMERIC);

        return [
            'count' => $count,
            'byteLength' => $byteLength,
            'parentDepthCounts' => $parentDepthCounts,
            'comments' => $comments,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param array{partCount:int, instructionCount:int, dataByteLength:int, targets:list<string>, partNames:list<string>, instructions:list<array<string, mixed>>, truncated:bool} $summary
     * @param array{count:int, dataByteLength:int, targets:list<string>, instructions:list<array<string, mixed>>, truncated:bool} $metadata
     */
    private static function recordPackagePartXmlProcessingInstructionSummary(array &$summary, string $partName, array $metadata): void
    {
        $instructionCount = (int) ($metadata['count'] ?? 0);
        if ($instructionCount <= 0) {
            return;
        }

        ++$summary['partCount'];
        $summary['instructionCount'] += $instructionCount;
        $summary['dataByteLength'] += (int) ($metadata['dataByteLength'] ?? 0);
        $summary['partNames'][] = $partName;
        foreach (($metadata['targets'] ?? []) as $target) {
            if (is_string($target) && $target !== '' && !in_array($target, $summary['targets'], true)) {
                $summary['targets'][] = $target;
            }
        }
        if (($metadata['truncated'] ?? false) === true) {
            $summary['truncated'] = true;
        }

        $summaryLimit = 64;
        foreach (($metadata['instructions'] ?? []) as $instruction) {
            if (!is_array($instruction)) {
                continue;
            }
            if (count($summary['instructions']) >= $summaryLimit) {
                $summary['truncated'] = true;
                continue;
            }

            $summary['instructions'][] = ['partName' => $partName] + $instruction;
        }

        sort($summary['partNames'], SORT_STRING);
        sort($summary['targets'], SORT_STRING);
    }

    /**
     * @return array{count:int, dataByteLength:int, targets:list<string>, instructions:list<array<string, mixed>>, truncated:bool}
     */
    private static function packagePartXmlProcessingInstructionMetadata(
        ZipPackage $package,
        ZipPackageEntry $entry,
        string $mediaTypeBase
    ): array {
        $empty = [
            'count' => 0,
            'dataByteLength' => 0,
            'targets' => [],
            'instructions' => [],
            'truncated' => false,
        ];
        if (
            $entry->isDirectory()
            || !in_array($entry->compressionMethod, [0, 8], true)
            || !self::isXmlPackagePart($entry->name, $mediaTypeBase, self::packagePartExtension($entry->name))
        ) {
            return $empty;
        }

        try {
            $dom = self::loadXmlForPackageProvenance($package->read($entry->name), $entry->name);
        } catch (\Throwable) {
            return $empty;
        }
        if (!$dom instanceof \DOMDocument) {
            return $empty;
        }

        $instructions = [];
        $targets = [];
        $count = 0;
        $dataByteLength = 0;
        $truncated = false;
        $itemLimit = 32;
        $walk = static function (\DOMNode $node) use (&$walk, &$instructions, &$targets, &$count, &$dataByteLength, &$truncated, $itemLimit): void {
            if ($node instanceof \DOMProcessingInstruction) {
                $target = (string) $node->target;
                if ($target !== '' && strtolower($target) !== 'xml') {
                    ++$count;
                    $data = (string) $node->data;
                    $dataLength = strlen($data);
                    $dataByteLength += $dataLength;
                    if (!in_array($target, $targets, true)) {
                        $targets[] = $target;
                    }
                    if (count($instructions) >= $itemLimit) {
                        $truncated = true;
                    } else {
                        $parent = $node->parentNode instanceof \DOMElement ? $node->parentNode : null;
                        $parentPath = self::domElementPath($parent);
                        $instructions[] = [
                            'index' => $count - 1,
                            'target' => $target,
                            'parentPath' => $parentPath,
                            'parentDepth' => self::domElementPathDepth($parentPath),
                            'dataByteLength' => $dataLength,
                            'dataCrc32' => sprintf('%08x', crc32($data)),
                            'dataSha256' => hash('sha256', $data),
                        ];
                    }
                }
            }

            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMNode) {
                    $walk($child);
                }
            }
        };
        $walk($dom);
        sort($targets, SORT_STRING);

        return [
            'count' => $count,
            'dataByteLength' => $dataByteLength,
            'targets' => $targets,
            'instructions' => $instructions,
            'truncated' => $truncated,
        ];
    }

    private static function isXmlPackagePart(string $partName, string $mediaTypeBase, ?string $partExtension): bool
    {
        return $partName === 'META-INF/manifest.xml'
            || $partExtension === 'xml'
            || self::isXmlMediaTypeBase($mediaTypeBase);
    }

    private static function loadXmlForPackageProvenance(string $xml, string $label): ?\DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }

    private static function domElementPath(?\DOMElement $element): string
    {
        if (!$element instanceof \DOMElement) {
            return '/';
        }

        $segments = [];
        $node = $element;
        while ($node instanceof \DOMElement) {
            array_unshift($segments, self::qualifiedDomName($node));
            $node = $node->parentNode;
        }

        return '/' . implode('/', $segments);
    }

    private static function qualifiedDomName(\DOMElement $element): string
    {
        return $element->prefix !== null && $element->prefix !== ''
            ? $element->prefix . ':' . $element->localName
            : $element->localName;
    }

    private static function domElementPathDepth(string $path): int
    {
        return $path === '/' ? 0 : substr_count($path, '/');
    }

    /**
     * @param array<string, mixed> $packageInventory
     * @return array<string, mixed>
     */
    private function packageIdentity(array $packageInventory, array $manifestMediaTypeSummary): array
    {
        $zipPackageManifestSummary = self::zipPackageManifestAggregateProvenance(
            is_array($packageInventory['zipPackageManifest'] ?? null) ? $packageInventory['zipPackageManifest'] : []
        );
        $manifestEntries = [];
        $manifestPartReferenceSuffixItems = [];
        $manifestPartReferenceQueryCount = 0;
        $manifestPartReferenceFragmentCount = 0;
        foreach ($this->manifestEntries as $entry) {
            $manifestEntries[] = self::withoutEmptyValues([
                'manifestIndex' => $entry['manifestIndex'] ?? null,
                'path' => $entry['path'] ?? null,
                'packagePath' => $entry['packagePath'] ?? null,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'pathShape' => $entry['pathShape'] ?? [],
                'packagePathShape' => $entry['packagePathShape'] ?? null,
                'mediaType' => $entry['mediaType'] ?? null,
                'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
                'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
                'version' => $entry['version'] ?? null,
                'exists' => ($entry['exists'] ?? false) === true,
                'isDirectory' => ($entry['isDirectory'] ?? false) === true,
                'encrypted' => ($entry['encrypted'] ?? false) === true,
                'scriptPackagePart' => ($entry['scriptPackagePart'] ?? false) === true,
                'signaturePackagePart' => ($entry['signaturePackagePart'] ?? false) === true,
                'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
                'fontPackagePart' => ($entry['fontPackagePart'] ?? false) === true,
                'rdfMetadataPart' => ($entry['rdfMetadataPart'] ?? false) === true,
                'objectReplacementPackagePart' => ($entry['objectReplacementPackagePart'] ?? false) === true,
                'layoutCachePackagePart' => ($entry['layoutCachePackagePart'] ?? false) === true,
                'metaInfSidecarPackagePart' => ($entry['metaInfSidecarPackagePart'] ?? false) === true,
                'databasePackagePart' => ($entry['databasePackagePart'] ?? false) === true,
                'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
                'storedByteLength' => $entry['storedByteLength'] ?? null,
                'compressedByteLength' => $entry['compressedByteLength'] ?? null,
                'compressionMethod' => $entry['compressionMethod'] ?? null,
                'compressionMethodName' => $entry['compressionMethodName'] ?? null,
                'storedCrc32' => $entry['storedCrc32'] ?? null,
                'byteSha256' => $entry['byteSha256'] ?? null,
                'declaredSize' => $entry['declaredSize'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'customManifestAttributeCount' => $entry['customManifestAttributeCount'] ?? 0,
                'customManifestAttributeNames' => $entry['customManifestAttributeNames'] ?? [],
                'customManifestAttributeMap' => $entry['customManifestAttributeMap'] ?? [],
                'manifestNamespaceDeclarationCount' => $entry['manifestNamespaceDeclarationCount'] ?? 0,
                'manifestNamespaceDeclarationNames' => $entry['manifestNamespaceDeclarationNames'] ?? [],
                'manifestNamespaceDeclarationMap' => $entry['manifestNamespaceDeclarationMap'] ?? [],
                'customManifestChildElementCount' => $entry['customManifestChildElementCount'] ?? 0,
                'customManifestChildElementNames' => $entry['customManifestChildElementNames'] ?? [],
                'diagnostics' => $entry['diagnostics'] ?? [],
            ]);
            if (is_string($entry['pathSuffix'] ?? null)) {
                $manifestPartReferenceSuffixItems[] = self::manifestPartReferenceSuffixItem($entry);
            }
            if (is_string($entry['pathQuery'] ?? null)) {
                ++$manifestPartReferenceQueryCount;
            }
            if (is_string($entry['pathFragment'] ?? null)) {
                ++$manifestPartReferenceFragmentCount;
            }
        }

        $packageEntries = [];
        foreach ($packageInventory['parts'] ?? [] as $part) {
            if (!is_array($part)) {
                continue;
            }

            $packageEntries[] = self::withoutEmptyValues([
                'path' => $part['path'] ?? null,
                'pathShape' => $part['pathShape'] ?? [],
                'packageDirectoryBaseName' => $part['packageDirectoryBaseName'] ?? null,
                'packageDirectoryBaseNameStem' => $part['packageDirectoryBaseNameStem'] ?? null,
                'packageCaseFoldDirectoryBaseNameStem' => $part['packageCaseFoldDirectoryBaseNameStem'] ?? null,
                'packageArea' => $part['packageArea'] ?? null,
                'packagePathDepth' => $part['packagePathDepth'] ?? null,
                'packagePathByteLength' => $part['packagePathByteLength'] ?? null,
                'packagePathByteLengthBucket' => $part['packagePathByteLengthBucket'] ?? null,
                'packagePathByteLengthBucketMin' => $part['packagePathByteLengthBucketMin'] ?? null,
                'packagePathByteLengthBucketMax' => $part['packagePathByteLengthBucketMax'] ?? null,
                'packagePartExtension' => $part['packagePartExtension'] ?? null,
                'rawPackagePartExtension' => $part['rawPackagePartExtension'] ?? null,
                'packagePartExtensionHasUppercase' => ($part['packagePartExtensionHasUppercase'] ?? false) === true,
                'packagePartExtensionWasNormalized' => ($part['packagePartExtensionWasNormalized'] ?? false) === true,
                'extensionlessPackagePart' => ($part['extensionlessPackagePart'] ?? false) === true,
                'roles' => $part['roles'] ?? [],
                'centralDirectoryIndex' => $part['centralDirectoryIndex'] ?? null,
                'localHeaderOrder' => $part['localHeaderOrder'] ?? null,
                'compressionMethod' => $part['compressionMethod'] ?? null,
                'compressionMethodName' => $part['compressionMethodName'] ?? null,
                'byteLength' => $part['byteLength'] ?? null,
                'compressedByteLength' => $part['compressedByteLength'] ?? null,
                'crc32' => $part['crc32'] ?? null,
                'byteSha256' => $part['byteSha256'] ?? null,
                'zipPackageManifestEntry' => $part['zipPackageManifestEntry'] ?? [],
                'zipPackageManifestCompressionMethodName' => $part['zipPackageManifestCompressionMethodName'] ?? null,
                'zipPackageManifestCrc32Hex' => $part['zipPackageManifestCrc32Hex'] ?? null,
                'zipPackageManifestCompressedSize' => $part['zipPackageManifestCompressedSize'] ?? null,
                'zipPackageManifestUncompressedSize' => $part['zipPackageManifestUncompressedSize'] ?? null,
                'zipPackageManifestExpansionRatio' => $part['zipPackageManifestExpansionRatio'] ?? null,
                'zipPackageManifestExpansionRatioBucket' => $part['zipPackageManifestExpansionRatioBucket'] ?? null,
                'zipPackageManifestVersionMadeBy' => $part['zipPackageManifestVersionMadeBy'] ?? null,
                'zipPackageManifestMadeByHostSystem' => $part['zipPackageManifestMadeByHostSystem'] ?? null,
                'zipPackageManifestMadeByHostSystemName' => $part['zipPackageManifestMadeByHostSystemName'] ?? null,
                'zipPackageManifestMadeByVersion' => $part['zipPackageManifestMadeByVersion'] ?? null,
                'zipPackageManifestVersionNeededToExtract' => $part['zipPackageManifestVersionNeededToExtract'] ?? null,
                'zipPackageManifestCreatorVersionMeetsNeeded' => $part['zipPackageManifestCreatorVersionMeetsNeeded'] ?? null,
                'zipPackageManifestCreatorVersionComparison' => $part['zipPackageManifestCreatorVersionComparison'] ?? null,
                'zipPackageManifestCreatorVersionDelta' => $part['zipPackageManifestCreatorVersionDelta'] ?? null,
                'zipPackageManifestCreatorHostSystemIsKnown' => $part['zipPackageManifestCreatorHostSystemIsKnown'] ?? null,
                'zipPackageManifestCreatorHostSystemIssues' => $part['zipPackageManifestCreatorHostSystemIssues'] ?? [],
                'zipPackageManifestCaseFoldKey' => $part['zipPackageManifestCaseFoldKey'] ?? null,
                'zipPackageManifestCaseInsensitiveEquivalentEntryNames' => $part['zipPackageManifestCaseInsensitiveEquivalentEntryNames'] ?? [],
                'zipPackageManifestHasCaseInsensitiveNameCollision' => ($part['zipPackageManifestHasCaseInsensitiveNameCollision'] ?? false) === true,
                'zipPackageManifestCaseInsensitiveNameCollisionIssues' => $part['zipPackageManifestCaseInsensitiveNameCollisionIssues'] ?? [],
                'zipPackageManifestDirectoryRoot' => $part['zipPackageManifestDirectoryRoot'] ?? null,
                'zipPackageManifestPathSegments' => $part['zipPackageManifestPathSegments'] ?? [],
                'zipPackageManifestPathSegmentCount' => $part['zipPackageManifestPathSegmentCount'] ?? null,
                'zipPackageManifestPathSegmentPositionReviews' => $part['zipPackageManifestPathSegmentPositionReviews'] ?? [],
                'zipPackageManifestDirectoryDepth' => $part['zipPackageManifestDirectoryDepth'] ?? null,
                'zipPackageManifestPackagePartBaseName' => $part['zipPackageManifestPackagePartBaseName'] ?? null,
                'zipPackageManifestPackagePartCaseFoldBaseName' => $part['zipPackageManifestPackagePartCaseFoldBaseName'] ?? null,
                'zipPackageManifestPackagePartBaseNameStem' => $part['zipPackageManifestPackagePartBaseNameStem'] ?? null,
                'zipPackageManifestPackagePartCaseFoldBaseNameStem' => $part['zipPackageManifestPackagePartCaseFoldBaseNameStem'] ?? null,
                'zipPackageManifestPackagePartExtension' => $part['zipPackageManifestPackagePartExtension'] ?? null,
                'zipPackageManifestPackagePartExtensionKey' => $part['zipPackageManifestPackagePartExtensionKey'] ?? null,
                'zipPackageManifestExtensionlessPackagePart' => ($part['zipPackageManifestExtensionlessPackagePart'] ?? false) === true,
                'zipPackageManifestCentralDirectoryIndex' => $part['zipPackageManifestCentralDirectoryIndex'] ?? null,
                'zipPackageManifestLocalHeaderOrder' => $part['zipPackageManifestLocalHeaderOrder'] ?? null,
                'zipLocalHeaderOffset' => $part['zipLocalHeaderOffset'] ?? null,
                'zipLocalHeaderBytes' => $part['zipLocalHeaderBytes'] ?? null,
                'zipLocalHeaderSha256' => $part['zipLocalHeaderSha256'] ?? null,
                'zipLocalHeaderFixedHeaderBytes' => $part['zipLocalHeaderFixedHeaderBytes'] ?? null,
                'zipLocalHeaderVariableFieldOffset' => $part['zipLocalHeaderVariableFieldOffset'] ?? null,
                'zipLocalHeaderVariableFieldBytes' => $part['zipLocalHeaderVariableFieldBytes'] ?? null,
                'zipLocalHeaderVariableFieldSha256' => $part['zipLocalHeaderVariableFieldSha256'] ?? null,
                'zipLocalHeaderRawNameOffset' => $part['zipLocalHeaderRawNameOffset'] ?? null,
                'zipLocalHeaderRawNameBytes' => $part['zipLocalHeaderRawNameBytes'] ?? null,
                'zipLocalHeaderRawNameSha256' => $part['zipLocalHeaderRawNameSha256'] ?? null,
                'zipLocalHeaderExtraFieldOffset' => $part['zipLocalHeaderExtraFieldOffset'] ?? null,
                'zipLocalHeaderExtraFieldBytes' => $part['zipLocalHeaderExtraFieldBytes'] ?? null,
                'zipLocalHeaderExtraFieldSha256' => $part['zipLocalHeaderExtraFieldSha256'] ?? null,
                'zipLocalHeaderReviewFieldBytes' => $part['zipLocalHeaderReviewFieldBytes'] ?? null,
                'zipLocalRecordOffset' => $part['zipLocalRecordOffset'] ?? null,
                'zipLocalRecordBytes' => $part['zipLocalRecordBytes'] ?? null,
                'zipLocalRecordEnd' => $part['zipLocalRecordEnd'] ?? null,
                'zipLocalRecordSha256' => $part['zipLocalRecordSha256'] ?? null,
                'zipCompressedDataOffset' => $part['zipCompressedDataOffset'] ?? null,
                'zipCompressedDataBytes' => $part['zipCompressedDataBytes'] ?? null,
                'zipCompressedDataEnd' => $part['zipCompressedDataEnd'] ?? null,
                'zipCompressedDataSha256' => $part['zipCompressedDataSha256'] ?? null,
                'zipUsesDataDescriptor' => ($part['zipUsesDataDescriptor'] ?? false) === true,
                'zipDataDescriptorOffset' => $part['zipDataDescriptorOffset'] ?? null,
                'zipDataDescriptorBytes' => $part['zipDataDescriptorBytes'] ?? 0,
                'zipDataDescriptorEnd' => $part['zipDataDescriptorEnd'] ?? null,
                'zipDataDescriptorSha256' => $part['zipDataDescriptorSha256'] ?? null,
                'zipCentralDirectoryRecordOffset' => $part['zipCentralDirectoryRecordOffset'] ?? null,
                'zipCentralDirectoryRecordEnd' => $part['zipCentralDirectoryRecordEnd'] ?? null,
                'zipCentralDirectoryRecordBytes' => $part['zipCentralDirectoryRecordBytes'] ?? null,
                'zipCentralDirectoryRecordSha256' => $part['zipCentralDirectoryRecordSha256'] ?? null,
                'zipCentralDirectoryFixedHeaderBytes' => $part['zipCentralDirectoryFixedHeaderBytes'] ?? null,
                'zipCentralDirectoryVariableFieldOffset' => $part['zipCentralDirectoryVariableFieldOffset'] ?? null,
                'zipCentralDirectoryVariableFieldBytes' => $part['zipCentralDirectoryVariableFieldBytes'] ?? null,
                'zipCentralDirectoryVariableFieldSha256' => $part['zipCentralDirectoryVariableFieldSha256'] ?? null,
                'zipCentralDirectoryRawNameOffset' => $part['zipCentralDirectoryRawNameOffset'] ?? null,
                'zipCentralDirectoryRawNameBytes' => $part['zipCentralDirectoryRawNameBytes'] ?? null,
                'zipCentralDirectoryRawNameSha256' => $part['zipCentralDirectoryRawNameSha256'] ?? null,
                'zipCentralDirectoryExtraFieldOffset' => $part['zipCentralDirectoryExtraFieldOffset'] ?? null,
                'zipCentralDirectoryExtraFieldBytes' => $part['zipCentralDirectoryExtraFieldBytes'] ?? null,
                'zipCentralDirectoryExtraFieldSha256' => $part['zipCentralDirectoryExtraFieldSha256'] ?? null,
                'zipCentralDirectoryRawCommentOffset' => $part['zipCentralDirectoryRawCommentOffset'] ?? null,
                'zipCentralDirectoryRawCommentBytes' => $part['zipCentralDirectoryRawCommentBytes'] ?? null,
                'zipCentralDirectoryRawCommentSha256' => $part['zipCentralDirectoryRawCommentSha256'] ?? null,
                'zipCentralDirectoryReviewFieldBytes' => $part['zipCentralDirectoryReviewFieldBytes'] ?? null,
                'zipSourceRecordBytes' => $part['zipSourceRecordBytes'] ?? null,
                'zipHasSourceRecordProvenance' => ($part['zipHasSourceRecordProvenance'] ?? false) === true,
                'zipLocalHeaderMetadataMatchesCentralDirectory' => $part['zipLocalHeaderMetadataMatchesCentralDirectory'] ?? null,
                'zipLocalHeaderMetadataIssues' => $part['zipLocalHeaderMetadataIssues'] ?? [],
                'zipCentralVersionNeededToExtract' => $part['zipCentralVersionNeededToExtract'] ?? null,
                'zipLocalVersionNeededToExtract' => $part['zipLocalVersionNeededToExtract'] ?? null,
                'zipCentralGeneralPurposeFlags' => $part['zipCentralGeneralPurposeFlags'] ?? null,
                'zipLocalGeneralPurposeFlags' => $part['zipLocalGeneralPurposeFlags'] ?? null,
                'zipCentralCompressionMethod' => $part['zipCentralCompressionMethod'] ?? null,
                'zipLocalCompressionMethod' => $part['zipLocalCompressionMethod'] ?? null,
                'zipCentralCrc32' => $part['zipCentralCrc32'] ?? null,
                'zipLocalCrc32' => $part['zipLocalCrc32'] ?? null,
                'zipCentralCompressedSize' => $part['zipCentralCompressedSize'] ?? null,
                'zipLocalCompressedSize' => $part['zipLocalCompressedSize'] ?? null,
                'zipCentralUncompressedSize' => $part['zipCentralUncompressedSize'] ?? null,
                'zipLocalUncompressedSize' => $part['zipLocalUncompressedSize'] ?? null,
                'zipLocalHeaderUsesDataDescriptor' => ($part['zipLocalHeaderUsesDataDescriptor'] ?? false) === true,
                'zipLocalHeaderHasZeroDataDescriptorPlaceholders' => $part['zipLocalHeaderHasZeroDataDescriptorPlaceholders'] ?? null,
                'zipGeneralPurposeFlags' => $part['zipGeneralPurposeFlags'] ?? null,
                'zipGeneralPurposeFlagNames' => $part['zipGeneralPurposeFlagNames'] ?? [],
                'zipUnsupportedGeneralPurposeFlagBits' => $part['zipUnsupportedGeneralPurposeFlagBits'] ?? 0,
                'zipGeneralPurposeFlagsSupported' => ($part['zipGeneralPurposeFlagsSupported'] ?? false) === true,
                'zipUsesUtf8Names' => ($part['zipUsesUtf8Names'] ?? false) === true,
                'zipGeneralPurposeUsesDataDescriptor' => ($part['zipGeneralPurposeUsesDataDescriptor'] ?? false) === true,
                'zipDeflateOptionFlags' => $part['zipDeflateOptionFlags'] ?? 0,
                'zipDeflateOptionName' => $part['zipDeflateOptionName'] ?? null,
                'zipGeneralPurposeRequiresStrictReview' => ($part['zipGeneralPurposeRequiresStrictReview'] ?? false) === true,
                'zipGeneralPurposeFlagIssues' => $part['zipGeneralPurposeFlagIssues'] ?? [],
                'madeByHostSystem' => $part['madeByHostSystem'] ?? null,
                'madeByHostSystemName' => $part['madeByHostSystemName'] ?? null,
                'madeByVersion' => $part['madeByVersion'] ?? null,
                'versionMadeBy' => $part['versionMadeBy'] ?? null,
                'versionNeededToExtract' => $part['versionNeededToExtract'] ?? null,
                'creatorVersionMeetsNeeded' => $part['creatorVersionMeetsNeeded'] ?? null,
                'creatorVersionComparison' => $part['creatorVersionComparison'] ?? null,
                'creatorVersionDelta' => $part['creatorVersionDelta'] ?? null,
                'creatorHostIssues' => $part['creatorHostIssues'] ?? [],
                'externalAttributes' => $part['externalAttributes'] ?? null,
                'externalAttributesHex' => $part['externalAttributesHex'] ?? null,
                'hasExternalAttributes' => ($part['hasExternalAttributes'] ?? false) === true,
                'dosAttributes' => $part['dosAttributes'] ?? null,
                'dosAttributeNames' => $part['dosAttributeNames'] ?? [],
                'hasDosAttributes' => ($part['hasDosAttributes'] ?? false) === true,
                'hasDosReadOnlyAttribute' => ($part['hasDosReadOnlyAttribute'] ?? false) === true,
                'hasDosHiddenAttribute' => ($part['hasDosHiddenAttribute'] ?? false) === true,
                'hasDosSystemAttribute' => ($part['hasDosSystemAttribute'] ?? false) === true,
                'hasDosVolumeLabelAttribute' => ($part['hasDosVolumeLabelAttribute'] ?? false) === true,
                'hasDosDirectoryAttribute' => ($part['hasDosDirectoryAttribute'] ?? false) === true,
                'hasDosArchiveAttribute' => ($part['hasDosArchiveAttribute'] ?? false) === true,
                'internalFileAttributes' => $part['internalFileAttributes'] ?? null,
                'internalFileAttributesHex' => $part['internalFileAttributesHex'] ?? null,
                'internalAttributeNames' => $part['internalAttributeNames'] ?? [],
                'hasInternalFileAttributes' => ($part['hasInternalFileAttributes'] ?? false) === true,
                'hasTextInternalAttribute' => ($part['hasTextInternalAttribute'] ?? false) === true,
                'hasUnknownInternalAttributeBits' => ($part['hasUnknownInternalAttributeBits'] ?? false) === true,
                'unknownInternalAttributeBits' => $part['unknownInternalAttributeBits'] ?? null,
                'unixMode' => $part['unixMode'] ?? null,
                'unixModeOctal' => $part['unixModeOctal'] ?? null,
                'unixPermissions' => $part['unixPermissions'] ?? null,
                'unixPermissionsOctal' => $part['unixPermissionsOctal'] ?? null,
                'hasUnixMode' => ($part['hasUnixMode'] ?? false) === true,
                'unixFileType' => $part['unixFileType'] ?? null,
                'unixFileTypeName' => $part['unixFileTypeName'] ?? null,
                'isUnixExecutableFile' => ($part['isUnixExecutableFile'] ?? false) === true,
                'isGroupWritable' => ($part['isGroupWritable'] ?? false) === true,
                'isWorldWritable' => ($part['isWorldWritable'] ?? false) === true,
                'hasWritablePermissions' => ($part['hasWritablePermissions'] ?? false) === true,
                'platformMetadataPlatform' => $part['platformMetadataPlatform'] ?? null,
                'platformMetadataIssues' => $part['platformMetadataIssues'] ?? [],
                'platformAttributeIssues' => $part['platformAttributeIssues'] ?? [],
                'hasPlatformAttributeProvenance' => ($part['hasPlatformAttributeProvenance'] ?? false) === true,
                'zipNameHygieneSegments' => $part['zipNameHygieneSegments'] ?? [],
                'zipNameHygieneFlaggedSegmentCount' => $part['zipNameHygieneFlaggedSegmentCount'] ?? 0,
                'zipNameHygieneFlaggedSegments' => $part['zipNameHygieneFlaggedSegments'] ?? [],
                'zipNameHygieneIssueCodes' => $part['zipNameHygieneIssueCodes'] ?? [],
                'hasZipNameHygieneIssue' => ($part['hasZipNameHygieneIssue'] ?? false) === true,
                'rawNameHex' => $part['rawNameHex'] ?? null,
                'nameEncoding' => $part['nameEncoding'] ?? null,
                'rawNameMatchesDecodedName' => ($part['rawNameMatchesDecodedName'] ?? false) === true,
                'usesLegacyNameEncoding' => ($part['usesLegacyNameEncoding'] ?? false) === true,
                'usesUnicodePathExtraField' => ($part['usesUnicodePathExtraField'] ?? false) === true,
                'hasRawNameProvenance' => ($part['hasRawNameProvenance'] ?? false) === true,
                'zipEntryComment' => $part['zipEntryComment'] ?? null,
                'zipEntryCommentLength' => $part['zipEntryCommentLength'] ?? null,
                'zipEntryCommentEncoding' => $part['zipEntryCommentEncoding'] ?? null,
                'zipEntryHasComment' => ($part['zipEntryHasComment'] ?? false) === true,
                'zipEntryCommentIssues' => $part['zipEntryCommentIssues'] ?? [],
                'zipExtraFieldIds' => $part['zipExtraFieldIds'] ?? [],
                'zipExtraFieldIdHexes' => $part['zipExtraFieldIdHexes'] ?? [],
                'extraFieldIdCount' => $part['extraFieldIdCount'] ?? 0,
                'centralExtraFieldIds' => $part['centralExtraFieldIds'] ?? [],
                'centralExtraFieldIdHexes' => $part['centralExtraFieldIdHexes'] ?? [],
                'localExtraFieldIds' => $part['localExtraFieldIds'] ?? [],
                'localExtraFieldIdHexes' => $part['localExtraFieldIdHexes'] ?? [],
                'centralExtraFieldRecordCount' => $part['centralExtraFieldRecordCount'] ?? 0,
                'localExtraFieldRecordCount' => $part['localExtraFieldRecordCount'] ?? 0,
                'duplicateCentralExtraFieldIds' => $part['duplicateCentralExtraFieldIds'] ?? [],
                'duplicateCentralExtraFieldIdHexes' => $part['duplicateCentralExtraFieldIdHexes'] ?? [],
                'duplicateLocalExtraFieldIds' => $part['duplicateLocalExtraFieldIds'] ?? [],
                'duplicateLocalExtraFieldIdHexes' => $part['duplicateLocalExtraFieldIdHexes'] ?? [],
                'centralOnlyExtraFieldIds' => $part['centralOnlyExtraFieldIds'] ?? [],
                'centralOnlyExtraFieldIdHexes' => $part['centralOnlyExtraFieldIdHexes'] ?? [],
                'localOnlyExtraFieldIds' => $part['localOnlyExtraFieldIds'] ?? [],
                'localOnlyExtraFieldIdHexes' => $part['localOnlyExtraFieldIdHexes'] ?? [],
                'mismatchedExtraFieldValueIds' => $part['mismatchedExtraFieldValueIds'] ?? [],
                'mismatchedExtraFieldValueIdHexes' => $part['mismatchedExtraFieldValueIdHexes'] ?? [],
                'centralLocalExtraFieldIdsMatch' => ($part['centralLocalExtraFieldIdsMatch'] ?? false) === true,
                'centralLocalExtraFieldValuesMatch' => ($part['centralLocalExtraFieldValuesMatch'] ?? false) === true,
                'hasCentralExtraFields' => ($part['hasCentralExtraFields'] ?? false) === true,
                'hasLocalExtraFields' => ($part['hasLocalExtraFields'] ?? false) === true,
                'hasZipExtraFieldProvenance' => ($part['hasZipExtraFieldProvenance'] ?? false) === true,
                'hasDuplicateExtraFieldIds' => ($part['hasDuplicateExtraFieldIds'] ?? false) === true,
                'hasMismatchedExtraFieldIds' => ($part['hasMismatchedExtraFieldIds'] ?? false) === true,
                'hasMismatchedExtraFieldValues' => ($part['hasMismatchedExtraFieldValues'] ?? false) === true,
                'centralUnixOwner' => $part['centralUnixOwner'] ?? null,
                'localUnixOwner' => $part['localUnixOwner'] ?? null,
                'hasCentralUnixOwnerMetadata' => ($part['hasCentralUnixOwnerMetadata'] ?? false) === true,
                'hasLocalUnixOwnerMetadata' => ($part['hasLocalUnixOwnerMetadata'] ?? false) === true,
                'hasUnixOwnerMetadata' => ($part['hasUnixOwnerMetadata'] ?? false) === true,
                'unixOwnerMetadataMatches' => ($part['unixOwnerMetadataMatches'] ?? true) === true,
                'unixOwnerMetadataIssues' => $part['unixOwnerMetadataIssues'] ?? [],
                'unixOwnerMetadataByteExposurePolicy' => $part['unixOwnerMetadataByteExposurePolicy'] ?? 'zip-unix-owner-metadata-only',
                'unixOwnerMetadataCanExposeBytes' => ($part['unixOwnerMetadataCanExposeBytes'] ?? false) === true,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'manifestIndex' => $part['manifestIndex'] ?? null,
                'manifestPath' => $part['manifestPath'] ?? null,
                'manifestPackagePath' => $part['manifestPackagePath'] ?? null,
                'manifestPathReference' => $part['manifestPathReference'] ?? null,
                'manifestPathSuffix' => $part['manifestPathSuffix'] ?? null,
                'manifestPathQuery' => $part['manifestPathQuery'] ?? null,
                'manifestPathFragment' => $part['manifestPathFragment'] ?? null,
                'manifestPathShape' => $part['manifestPathShape'] ?? null,
                'manifestPackagePathShape' => $part['manifestPackagePathShape'] ?? null,
                'manifestUriEncodedPackageReference' => ($part['manifestUriEncodedPackageReference'] ?? false) === true,
                'manifestMediaTypeBase' => $part['manifestMediaTypeBase'] ?? null,
                'manifestMediaFamily' => $part['manifestMediaFamily'] ?? null,
                'manifestDeclaredSizeMismatch' => ($part['manifestDeclaredSizeMismatch'] ?? false) === true,
                'customManifestAttributeCount' => $part['customManifestAttributeCount'] ?? 0,
                'customManifestAttributeNames' => $part['customManifestAttributeNames'] ?? [],
                'customManifestAttributeMap' => $part['customManifestAttributeMap'] ?? [],
                'manifestNamespaceDeclarationCount' => $part['manifestNamespaceDeclarationCount'] ?? 0,
                'manifestNamespaceDeclarationNames' => $part['manifestNamespaceDeclarationNames'] ?? [],
                'manifestNamespaceDeclarationMap' => $part['manifestNamespaceDeclarationMap'] ?? [],
                'customManifestChildElementCount' => $part['customManifestChildElementCount'] ?? 0,
                'customManifestChildElementNames' => $part['customManifestChildElementNames'] ?? [],
                'manifestDiagnostics' => $part['manifestDiagnostics'] ?? [],
                'scriptPackagePart' => ($part['scriptPackagePart'] ?? false) === true,
                'signaturePackagePart' => ($part['signaturePackagePart'] ?? false) === true,
                'configurationPackagePart' => ($part['configurationPackagePart'] ?? false) === true,
                'fontPackagePart' => ($part['fontPackagePart'] ?? false) === true,
                'rdfMetadataPart' => ($part['rdfMetadataPart'] ?? false) === true,
                'objectReplacementPackagePart' => ($part['objectReplacementPackagePart'] ?? false) === true,
                'layoutCachePackagePart' => ($part['layoutCachePackagePart'] ?? false) === true,
                'metaInfSidecarPackagePart' => ($part['metaInfSidecarPackagePart'] ?? false) === true,
                'databasePackagePart' => ($part['databasePackagePart'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $part['byteExposurePolicy'] ?? null,
                'undeclared' => ($part['undeclared'] ?? false) === true,
            ]);
        }

        $comments = is_array($packageInventory['comments'] ?? null) ? $packageInventory['comments'] : [];
        $preferredViewModes = self::manifestPreferredViewModeSummary($this->manifestEntries);
        $manifestDeclaredSizeRoles = self::manifestDeclaredSizeRoleSummary($this->manifestEntries);
        $payload = [
            'identityVersion' => 1,
            'packageType' => 'opendocument-text',
            'mimetype' => self::TEXT_MIMETYPE,
            'manifestVersion' => $this->manifestVersion,
            'manifestEntryCount' => count($manifestEntries),
            'packageEntryCount' => count($packageEntries),
            'manifestRootCustomAttributeCount' => $this->manifestRootAttributes['customAttributeCount'] ?? 0,
            'manifestRootCustomAttributeNames' => $this->manifestRootAttributes['customAttributeNames'] ?? [],
            'manifestRootCustomAttributeMap' => $this->manifestRootAttributes['customAttributeMap'] ?? [],
            'manifestRootNamespaceDeclarationCount' => $this->manifestRootAttributes['namespaceDeclarationCount'] ?? 0,
            'manifestRootNamespaceDeclarationNames' => $this->manifestRootAttributes['namespaceDeclarationNames'] ?? [],
            'manifestRootNamespaceDeclarationMap' => $this->manifestRootAttributes['namespaceDeclarationMap'] ?? [],
            'manifestRootExtensionElementCount' => $this->manifestRootExtensionElements['extensionElementCount'] ?? 0,
            'manifestRootExtensionElementNames' => $this->manifestRootExtensionElements['extensionElementNames'] ?? [],
            'manifestRootExtensionElements' => $this->manifestRootExtensionElements['extensionElements'] ?? [],
            'manifestPaths' => array_column($manifestEntries, 'path'),
            'packagePaths' => array_column($packageEntries, 'path'),
            'manifestPackageCoverage' => $packageInventory['manifestPackageCoverage'] ?? [],
            'manifestPartReferenceSuffixCount' => count($manifestPartReferenceSuffixItems),
            'manifestPartReferenceQueryCount' => $manifestPartReferenceQueryCount,
            'manifestPartReferenceFragmentCount' => $manifestPartReferenceFragmentCount,
            'manifestPartReferenceSuffixItems' => $manifestPartReferenceSuffixItems,
            'manifestEncryption' => self::manifestEncryptionSummary($this->manifestEntries),
            'preferredViewModes' => $preferredViewModes,
            'manifestDeclaredSizeRoleCount' => $manifestDeclaredSizeRoles['manifestDeclaredSizeRoleCount'],
            'manifestDeclaredSizeRoleCounts' => $manifestDeclaredSizeRoles['manifestDeclaredSizeRoleCounts'],
            'manifestDeclaredSizeRoleByteLengths' => $manifestDeclaredSizeRoles['manifestDeclaredSizeRoleByteLengths'],
            'manifestDeclaredSizeRoleMismatchCounts' => $manifestDeclaredSizeRoles['manifestDeclaredSizeRoleMismatchCounts'],
            'manifestDeclaredSizeRoleExistingCounts' => $manifestDeclaredSizeRoles['manifestDeclaredSizeRoleExistingCounts'],
            'manifestDeclaredSizeRoleMissingCounts' => $manifestDeclaredSizeRoles['manifestDeclaredSizeRoleMissingCounts'],
            'manifestDeclaredSizeRoleSummaries' => $manifestDeclaredSizeRoles['manifestDeclaredSizeRoleSummaries'],
            'hasPackageComment' => ($comments['hasPackageComment'] ?? false) === true,
            'hasEntryComments' => ($comments['hasEntryComments'] ?? false) === true,
            'entryCommentCount' => is_int($comments['entryCommentCount'] ?? null) ? $comments['entryCommentCount'] : 0,
            'commentedEntryNames' => is_array($comments['commentedEntryNames'] ?? null) ? $comments['commentedEntryNames'] : [],
            'manifestEntries' => $manifestEntries,
            'manifestMediaTypeSummary' => $manifestMediaTypeSummary,
            'manifestMediaTypeCount' => $manifestMediaTypeSummary['mediaTypeCount'] ?? 0,
            'manifestMediaTypeParameterizedItemCount' => $manifestMediaTypeSummary['parameterizedItemCount'] ?? 0,
            'manifestMediaTypeParameterNames' => $manifestMediaTypeSummary['mediaTypeParameterNames'] ?? [],
            'manifestMediaTypeParameterValueCount' => $manifestMediaTypeSummary['mediaTypeParameterValueCount'] ?? 0,
            'manifestMediaTypeParameterValuesByName' => $manifestMediaTypeSummary['mediaTypeParameterValuesByName'] ?? [],
            'manifestMediaTypeParameterValueCounts' => $manifestMediaTypeSummary['mediaTypeParameterValueCounts'] ?? [],
            'manifestMediaTypeParameterValueSummaries' => $manifestMediaTypeSummary['mediaTypeParameterValueSummaries'] ?? [],
            'manifestEmptyMediaTypeCount' => $manifestMediaTypeSummary['emptyMediaTypeCount'] ?? 0,
            'manifestEmptyMediaTypeDirectoryCount' => $manifestMediaTypeSummary['emptyMediaTypeDirectoryCount'] ?? 0,
            'manifestEmptyMediaTypeNonDirectoryCount' => $manifestMediaTypeSummary['emptyMediaTypeNonDirectoryCount'] ?? 0,
            'packageEntries' => $packageEntries,
            'manifestMediaFamilyCounts' => $packageInventory['manifestMediaFamilyCounts'] ?? [],
            'extensionlessPackagePartCount' => $packageInventory['extensionlessPackagePartCount'] ?? 0,
            'packagePartExtensionCounts' => $packageInventory['packagePartExtensionCounts'] ?? [],
            'packagePartRawExtensionCount' => $packageInventory['packagePartRawExtensionCount'] ?? 0,
            'packagePartRawExtensionCounts' => $packageInventory['packagePartRawExtensionCounts'] ?? [],
            'entryNamesByPackagePartRawExtension' => $packageInventory['entryNamesByPackagePartRawExtension'] ?? [],
            'packagePartRawExtensionUppercasePartCount' => $packageInventory['packagePartRawExtensionUppercasePartCount'] ?? 0,
            'packagePartRawExtensionNormalizedPartCount' => $packageInventory['packagePartRawExtensionNormalizedPartCount'] ?? 0,
            'packagePartRawExtensionSummaryCount' => $packageInventory['packagePartRawExtensionSummaryCount'] ?? 0,
            'packagePartRawExtensionSummaries' => $packageInventory['packagePartRawExtensionSummaries'] ?? [],
            'packageBasenameCounts' => $packageInventory['packageBasenameCounts'] ?? [],
            'entryNamesByPackageBasename' => $packageInventory['entryNamesByPackageBasename'] ?? [],
            'packageBasenameStemCounts' => $packageInventory['packageBasenameStemCounts'] ?? [],
            'packageCaseFoldedBasenameCounts' => $packageInventory['packageCaseFoldedBasenameCounts'] ?? [],
            'entryNamesByPackageCaseFoldedBasename' => $packageInventory['entryNamesByPackageCaseFoldedBasename'] ?? [],
            'duplicatePackageBasenameCount' => $packageInventory['duplicatePackageBasenameCount'] ?? 0,
            'duplicatePackageBasenameEntryCount' => $packageInventory['duplicatePackageBasenameEntryCount'] ?? 0,
            'duplicatePackageBasenameSummaries' => $packageInventory['duplicatePackageBasenameSummaries'] ?? [],
            'caseFoldedPackageBasenameDuplicateCount' => $packageInventory['caseFoldedPackageBasenameDuplicateCount'] ?? 0,
            'caseFoldedPackageBasenameDuplicateEntryCount' => $packageInventory['caseFoldedPackageBasenameDuplicateEntryCount'] ?? 0,
            'caseFoldedPackageBasenameDuplicateSummaries' => $packageInventory['caseFoldedPackageBasenameDuplicateSummaries'] ?? [],
            'packageDirectoryBaseNameCount' => $packageInventory['packageDirectoryBaseNameCount'] ?? 0,
            'packageDirectoryBaseNameCounts' => $packageInventory['packageDirectoryBaseNameCounts'] ?? [],
            'entryNamesByPackageDirectoryBaseName' => $packageInventory['entryNamesByPackageDirectoryBaseName'] ?? [],
            'duplicatePackageDirectoryBaseNameCount' => $packageInventory['duplicatePackageDirectoryBaseNameCount'] ?? 0,
            'duplicatePackageDirectoryBaseNames' => $packageInventory['duplicatePackageDirectoryBaseNames'] ?? [],
            'packageDirectoryBaseNames' => $packageInventory['packageDirectoryBaseNames'] ?? [],
            'packageCaseFoldDirectoryBaseNameCount' => $packageInventory['packageCaseFoldDirectoryBaseNameCount'] ?? 0,
            'packageCaseFoldDirectoryBaseNameCounts' => $packageInventory['packageCaseFoldDirectoryBaseNameCounts'] ?? [],
            'entryNamesByPackageCaseFoldDirectoryBaseName' => $packageInventory['entryNamesByPackageCaseFoldDirectoryBaseName'] ?? [],
            'duplicatePackageCaseFoldDirectoryBaseNameCount' => $packageInventory['duplicatePackageCaseFoldDirectoryBaseNameCount'] ?? 0,
            'duplicatePackageCaseFoldDirectoryBaseNames' => $packageInventory['duplicatePackageCaseFoldDirectoryBaseNames'] ?? [],
            'packageCaseFoldDirectoryBaseNames' => $packageInventory['packageCaseFoldDirectoryBaseNames'] ?? [],
            'packageDirectoryBaseNameStemCount' => $packageInventory['packageDirectoryBaseNameStemCount'] ?? 0,
            'packageDirectoryBaseNameStemCounts' => $packageInventory['packageDirectoryBaseNameStemCounts'] ?? [],
            'entryNamesByPackageDirectoryBaseNameStem' => $packageInventory['entryNamesByPackageDirectoryBaseNameStem'] ?? [],
            'packageCaseFoldDirectoryBaseNameStemCount' => $packageInventory['packageCaseFoldDirectoryBaseNameStemCount'] ?? 0,
            'packageCaseFoldDirectoryBaseNameStemCounts' => $packageInventory['packageCaseFoldDirectoryBaseNameStemCounts'] ?? [],
            'entryNamesByPackageCaseFoldDirectoryBaseNameStem' => $packageInventory['entryNamesByPackageCaseFoldDirectoryBaseNameStem'] ?? [],
            'duplicatePackageDirectoryBaseNameStemCount' => $packageInventory['duplicatePackageDirectoryBaseNameStemCount'] ?? 0,
            'duplicatePackageDirectoryBaseNameStems' => $packageInventory['duplicatePackageDirectoryBaseNameStems'] ?? [],
            'packageDirectoryBaseNameStems' => $packageInventory['packageDirectoryBaseNameStems'] ?? [],
            'duplicatePackageCaseFoldDirectoryBaseNameStemCount' => $packageInventory['duplicatePackageCaseFoldDirectoryBaseNameStemCount'] ?? 0,
            'duplicatePackageCaseFoldDirectoryBaseNameStems' => $packageInventory['duplicatePackageCaseFoldDirectoryBaseNameStems'] ?? [],
            'packageCaseFoldDirectoryBaseNameStems' => $packageInventory['packageCaseFoldDirectoryBaseNameStems'] ?? [],
            'packageZipSourceRecordDirectoryRootCount' => $packageInventory['packageZipSourceRecordDirectoryRootCount'] ?? 0,
            'packageZipSourceRecordDirectoryRootCounts' => $packageInventory['packageZipSourceRecordDirectoryRootCounts'] ?? [],
            'packageZipSourceRecordDirectoryRootBytes' => $packageInventory['packageZipSourceRecordDirectoryRootBytes'] ?? [],
            'packageZipSourceRecordEntryCount' => $packageInventory['packageZipSourceRecordEntryCount'] ?? 0,
            'packageZipSourceRecordByteLength' => $packageInventory['packageZipSourceRecordByteLength'] ?? 0,
            'packageZipSourceRecordLocalRecordByteLength' => $packageInventory['packageZipSourceRecordLocalRecordByteLength'] ?? 0,
            'packageZipSourceRecordCentralDirectoryRecordByteLength' => $packageInventory['packageZipSourceRecordCentralDirectoryRecordByteLength'] ?? 0,
            'packageZipSourceRecordLocalHeaderReviewFieldByteLength' => $packageInventory['packageZipSourceRecordLocalHeaderReviewFieldByteLength'] ?? 0,
            'packageZipSourceRecordCentralDirectoryReviewFieldByteLength' => $packageInventory['packageZipSourceRecordCentralDirectoryReviewFieldByteLength'] ?? 0,
            'packageZipSourceRecordReviewFieldByteLength' => $packageInventory['packageZipSourceRecordReviewFieldByteLength'] ?? 0,
            'packageZipSourceRecordDataDescriptorEntryCount' => $packageInventory['packageZipSourceRecordDataDescriptorEntryCount'] ?? 0,
            'packageZipSourceRecordDirectoryRoots' => $packageInventory['packageZipSourceRecordDirectoryRoots'] ?? [],
            'packageZipSourceRecordPackagePartExtensionCount' => $packageInventory['packageZipSourceRecordPackagePartExtensionCount'] ?? 0,
            'packageZipSourceRecordPackagePartExtensionCounts' => $packageInventory['packageZipSourceRecordPackagePartExtensionCounts'] ?? [],
            'packageZipSourceRecordPackagePartExtensionBytes' => $packageInventory['packageZipSourceRecordPackagePartExtensionBytes'] ?? [],
            'packageZipSourceRecordExtensionlessPackagePartCount' => $packageInventory['packageZipSourceRecordExtensionlessPackagePartCount'] ?? 0,
            'packageZipSourceRecordPackagePartExtensionDataDescriptorEntryCount' => $packageInventory['packageZipSourceRecordPackagePartExtensionDataDescriptorEntryCount'] ?? 0,
            'packageZipSourceRecordPackagePartExtensionIssueEntryCount' => $packageInventory['packageZipSourceRecordPackagePartExtensionIssueEntryCount'] ?? 0,
            'packageZipSourceRecordPackagePartExtensions' => $packageInventory['packageZipSourceRecordPackagePartExtensions'] ?? [],
            'packageZipSourceRecordPackagePartBaseNameStemCount' => $packageInventory['packageZipSourceRecordPackagePartBaseNameStemCount'] ?? 0,
            'packageZipSourceRecordPackagePartBaseNameStemCounts' => $packageInventory['packageZipSourceRecordPackagePartBaseNameStemCounts'] ?? [],
            'packageZipSourceRecordPackagePartBaseNameStemBytes' => $packageInventory['packageZipSourceRecordPackagePartBaseNameStemBytes'] ?? [],
            'packageZipSourceRecordPackagePartBaseNameStemDataDescriptorEntryCount' => $packageInventory['packageZipSourceRecordPackagePartBaseNameStemDataDescriptorEntryCount'] ?? 0,
            'packageZipSourceRecordPackagePartBaseNameStemIssueEntryCount' => $packageInventory['packageZipSourceRecordPackagePartBaseNameStemIssueEntryCount'] ?? 0,
            'packageZipSourceRecordDuplicatePackagePartBaseNameStemCount' => $packageInventory['packageZipSourceRecordDuplicatePackagePartBaseNameStemCount'] ?? 0,
            'packageZipSourceRecordDuplicatePackagePartBaseNameStemEntryCount' => $packageInventory['packageZipSourceRecordDuplicatePackagePartBaseNameStemEntryCount'] ?? 0,
            'packageZipSourceRecordDuplicatePackagePartBaseNameStems' => $packageInventory['packageZipSourceRecordDuplicatePackagePartBaseNameStems'] ?? [],
            'packageZipSourceRecordPackagePartBaseNameStems' => $packageInventory['packageZipSourceRecordPackagePartBaseNameStems'] ?? [],
            'packageZipSourceRecordCompressionMethodCount' => $packageInventory['packageZipSourceRecordCompressionMethodCount'] ?? 0,
            'packageZipSourceRecordCompressionMethodCounts' => $packageInventory['packageZipSourceRecordCompressionMethodCounts'] ?? [],
            'packageZipSourceRecordCompressionMethodBytes' => $packageInventory['packageZipSourceRecordCompressionMethodBytes'] ?? [],
            'packageZipSourceRecordCompressionMethodCompressedByteLengths' => $packageInventory['packageZipSourceRecordCompressionMethodCompressedByteLengths'] ?? [],
            'packageZipSourceRecordCompressionMethodUncompressedByteLengths' => $packageInventory['packageZipSourceRecordCompressionMethodUncompressedByteLengths'] ?? [],
            'packageZipSourceRecordCompressionMethodExpansionRatios' => $packageInventory['packageZipSourceRecordCompressionMethodExpansionRatios'] ?? [],
            'packageZipSourceRecordCompressionMethodDataDescriptorEntryCount' => $packageInventory['packageZipSourceRecordCompressionMethodDataDescriptorEntryCount'] ?? 0,
            'packageZipSourceRecordCompressionMethodUnsupportedEntryCount' => $packageInventory['packageZipSourceRecordCompressionMethodUnsupportedEntryCount'] ?? 0,
            'packageZipSourceRecordCompressionMethods' => $packageInventory['packageZipSourceRecordCompressionMethods'] ?? [],
            'packageZipSourceRecordRoleCount' => $packageInventory['packageZipSourceRecordRoleCount'] ?? 0,
            'packageZipSourceRecordRoleCounts' => $packageInventory['packageZipSourceRecordRoleCounts'] ?? [],
            'packageZipSourceRecordRoleBytes' => $packageInventory['packageZipSourceRecordRoleBytes'] ?? [],
            'packageZipSourceRecordRoleOccurrenceCount' => $packageInventory['packageZipSourceRecordRoleOccurrenceCount'] ?? 0,
            'packageZipSourceRecordRoleDataDescriptorOccurrenceCount' => $packageInventory['packageZipSourceRecordRoleDataDescriptorOccurrenceCount'] ?? 0,
            'packageZipSourceRecordRoleIssueOccurrenceCount' => $packageInventory['packageZipSourceRecordRoleIssueOccurrenceCount'] ?? 0,
            'packageZipSourceRecordRoles' => $packageInventory['packageZipSourceRecordRoles'] ?? [],
            'packageCrc32EntryCount' => $packageInventory['packageCrc32EntryCount'] ?? 0,
            'packageCrc32Count' => $packageInventory['packageCrc32Count'] ?? 0,
            'packageDuplicateCrc32Count' => $packageInventory['packageDuplicateCrc32Count'] ?? 0,
            'packageDuplicateCrc32EntryCount' => $packageInventory['packageDuplicateCrc32EntryCount'] ?? 0,
            'packageCrc32Counts' => $packageInventory['packageCrc32Counts'] ?? [],
            'packageCrc32ByteLengths' => $packageInventory['packageCrc32ByteLengths'] ?? [],
            'packageCrc32CompressedByteLengths' => $packageInventory['packageCrc32CompressedByteLengths'] ?? [],
            'packageCrc32SourceRecordBytes' => $packageInventory['packageCrc32SourceRecordBytes'] ?? [],
            'entryNamesByPackageCrc32' => $packageInventory['entryNamesByPackageCrc32'] ?? [],
            'packageCrc32Summaries' => $packageInventory['packageCrc32Summaries'] ?? [],
            'packageDuplicateCrc32Summaries' => $packageInventory['packageDuplicateCrc32Summaries'] ?? [],
            'packageZipTimestampSourceCount' => $packageInventory['packageZipTimestampSourceCount'] ?? 0,
            'packageZipTimestampSourceCounts' => $packageInventory['packageZipTimestampSourceCounts'] ?? [],
            'packageZipTimestampSourceByteLengths' => $packageInventory['packageZipTimestampSourceByteLengths'] ?? [],
            'packageZipTimestampSourceRecordBytes' => $packageInventory['packageZipTimestampSourceRecordBytes'] ?? [],
            'packageZipTimestampSourceModifiedEntryCount' => $packageInventory['packageZipTimestampSourceModifiedEntryCount'] ?? 0,
            'packageZipTimestampSourceIssueEntryCount' => $packageInventory['packageZipTimestampSourceIssueEntryCount'] ?? 0,
            'packageZipTimestampSources' => $packageInventory['packageZipTimestampSources'] ?? [],
            'packagePathKindCounts' => $packageInventory['packagePathKindCounts'] ?? [],
            'packageTopLevelSegmentCounts' => $packageInventory['packageTopLevelSegmentCounts'] ?? [],
            'packageCaseFoldTopLevelSegmentCount' => $packageInventory['packageCaseFoldTopLevelSegmentCount'] ?? 0,
            'packageCaseFoldTopLevelSegmentCounts' => $packageInventory['packageCaseFoldTopLevelSegmentCounts'] ?? [],
            'duplicatePackageCaseFoldTopLevelSegmentCount' => $packageInventory['duplicatePackageCaseFoldTopLevelSegmentCount'] ?? 0,
            'duplicatePackageCaseFoldTopLevelSegmentEntryCount' => $packageInventory['duplicatePackageCaseFoldTopLevelSegmentEntryCount'] ?? 0,
            'duplicatePackageCaseFoldTopLevelSegments' => $packageInventory['duplicatePackageCaseFoldTopLevelSegments'] ?? [],
            'packageCaseFoldTopLevelSegments' => $packageInventory['packageCaseFoldTopLevelSegments'] ?? [],
            'packagePathExtensionCounts' => $packageInventory['packagePathExtensionCounts'] ?? [],
            'packageAreaCounts' => $packageInventory['packageAreaCounts'] ?? [],
            'packageAreaByteLengths' => $packageInventory['packageAreaByteLengths'] ?? [],
            'packageAreaCompressedByteLengths' => $packageInventory['packageAreaCompressedByteLengths'] ?? [],
            'packageAreaSummaries' => $packageInventory['packageAreaSummaries'] ?? [],
            'packagePathsByPackageArea' => $packageInventory['packagePathsByPackageArea'] ?? [],
            'packagePathDepthCounts' => $packageInventory['packagePathDepthCounts'] ?? [],
            'packagePathsByPathDepth' => $packageInventory['packagePathsByPathDepth'] ?? [],
            'maxPackagePathDepth' => $packageInventory['maxPackagePathDepth'] ?? 0,
            'packagePathDepthRoleCounts' => $packageInventory['packagePathDepthRoleCounts'] ?? [],
            'entryNamesByPackagePathDepthRole' => $packageInventory['entryNamesByPackagePathDepthRole'] ?? [],
            'packagePathDepthByteExposurePolicyCounts' => $packageInventory['packagePathDepthByteExposurePolicyCounts'] ?? [],
            'entryNamesByPackagePathDepthByteExposurePolicy' => $packageInventory['entryNamesByPackagePathDepthByteExposurePolicy'] ?? [],
            'packagePathByteLengthBucketCount' => $packageInventory['packagePathByteLengthBucketCount'] ?? 0,
            'packagePathByteLengthBuckets' => $packageInventory['packagePathByteLengthBuckets'] ?? [],
            'packagePathByteLengthBucketCounts' => $packageInventory['packagePathByteLengthBucketCounts'] ?? [],
            'entryNamesByPackagePathByteLengthBucket' => $packageInventory['entryNamesByPackagePathByteLengthBucket'] ?? [],
            'packagePathByteLengthRoleCounts' => $packageInventory['packagePathByteLengthRoleCounts'] ?? [],
            'entryNamesByPackagePathByteLengthRole' => $packageInventory['entryNamesByPackagePathByteLengthRole'] ?? [],
            'packagePathByteLengthByteExposurePolicyCounts' => $packageInventory['packagePathByteLengthByteExposurePolicyCounts'] ?? [],
            'entryNamesByPackagePathByteLengthByteExposurePolicy' => $packageInventory['entryNamesByPackagePathByteLengthByteExposurePolicy'] ?? [],
            'packagePathByteLengthBucketSummaries' => $packageInventory['packagePathByteLengthBucketSummaries'] ?? [],
            'zipPackageManifestPathSegmentPositionRoleCounts' => $packageInventory['zipPackageManifestPathSegmentPositionRoleCounts'] ?? [],
            'entryNamesByZipPackageManifestPathSegmentPositionRole' => $packageInventory['entryNamesByZipPackageManifestPathSegmentPositionRole'] ?? [],
            'zipPackageManifestPathSegmentPositionByteExposurePolicyCounts' => $packageInventory['zipPackageManifestPathSegmentPositionByteExposurePolicyCounts'] ?? [],
            'entryNamesByZipPackageManifestPathSegmentPositionByteExposurePolicy' => $packageInventory['entryNamesByZipPackageManifestPathSegmentPositionByteExposurePolicy'] ?? [],
            'byteExposurePolicyCounts' => $packageInventory['byteExposurePolicyCounts'] ?? [],
            'roleCounts' => $packageInventory['roleCounts'] ?? [],
            'centralDirectoryOrderMismatchRoleCount' => $packageInventory['centralDirectoryOrderMismatchRoleCount'] ?? 0,
            'centralDirectoryOrderMismatchRoleCounts' => $packageInventory['centralDirectoryOrderMismatchRoleCounts'] ?? [],
            'centralDirectoryOrderMismatchRoleByteLengths' => $packageInventory['centralDirectoryOrderMismatchRoleByteLengths'] ?? [],
            'centralDirectoryOrderMismatchRoleCompressedByteLengths' => $packageInventory['centralDirectoryOrderMismatchRoleCompressedByteLengths'] ?? [],
            'centralDirectoryOrderMismatchRoleSummaries' => $packageInventory['centralDirectoryOrderMismatchRoleSummaries'] ?? [],
            'undeclaredEntryCount' => $packageInventory['undeclaredEntryCount'] ?? 0,
            'unsupportedCompressionMethodCount' => $packageInventory['unsupportedCompressionMethodCount'] ?? 0,
            'encryptedCount' => count($this->encryptedManifestEntries()),
            'corePackagePartCount' => $packageInventory['corePackagePartCount'] ?? 0,
            'mediaResourcePartCount' => $packageInventory['mediaResourcePartCount'] ?? 0,
            'packageThumbnailPartCount' => $packageInventory['packageThumbnailPartCount'] ?? 0,
            'packageSignaturePartCount' => $packageInventory['packageSignaturePartCount'] ?? 0,
            'embeddedObjectPackageRootCount' => $packageInventory['embeddedObjectPackageRootCount'] ?? 0,
            'embeddedObjectPackagePartCount' => $packageInventory['embeddedObjectPackagePartCount'] ?? 0,
            'objectReplacementPartCount' => $packageInventory['objectReplacementPartCount'] ?? 0,
            'scriptPackagePartCount' => $packageInventory['scriptPackagePartCount'] ?? 0,
            'configurationPackagePartCount' => $packageInventory['configurationPackagePartCount'] ?? 0,
            'fontPackagePartCount' => $packageInventory['fontPackagePartCount'] ?? 0,
            'rdfMetadataPartCount' => $packageInventory['rdfMetadataPartCount'] ?? 0,
            'layoutCachePartCount' => $packageInventory['layoutCachePartCount'] ?? 0,
            'metaInfSidecarPackagePartCount' => $packageInventory['metaInfSidecarPackagePartCount'] ?? 0,
            'databasePackagePartCount' => $packageInventory['databasePackagePartCount'] ?? 0,
            'hasZipExtraFields' => ($packageInventory['hasZipExtraFields'] ?? false) === true,
            'extraFieldEntryCount' => $packageInventory['extraFieldEntryCount'] ?? 0,
            'duplicateExtraFieldEntryCount' => $packageInventory['duplicateExtraFieldEntryCount'] ?? 0,
            'mismatchedExtraFieldEntryCount' => $packageInventory['mismatchedExtraFieldEntryCount'] ?? 0,
            'mismatchedExtraFieldValueEntryCount' => $packageInventory['mismatchedExtraFieldValueEntryCount'] ?? 0,
            'extraFieldIdCount' => $packageInventory['extraFieldIdCount'] ?? 0,
            'centralExtraFieldIdCount' => $packageInventory['centralExtraFieldIdCount'] ?? 0,
            'localExtraFieldIdCount' => $packageInventory['localExtraFieldIdCount'] ?? 0,
            'sharedExtraFieldIdCount' => $packageInventory['sharedExtraFieldIdCount'] ?? 0,
            'centralOnlyExtraFieldIdCount' => $packageInventory['centralOnlyExtraFieldIdCount'] ?? 0,
            'localOnlyExtraFieldIdCount' => $packageInventory['localOnlyExtraFieldIdCount'] ?? 0,
            'extraFieldIdUsage' => $packageInventory['extraFieldIdUsage'] ?? [],
            'extraFieldIdHexes' => $packageInventory['extraFieldIdHexes'] ?? [],
            'centralExtraFieldIdHexes' => $packageInventory['centralExtraFieldIdHexes'] ?? [],
            'localExtraFieldIdHexes' => $packageInventory['localExtraFieldIdHexes'] ?? [],
            'sharedExtraFieldIdHexes' => $packageInventory['sharedExtraFieldIdHexes'] ?? [],
            'centralOnlyExtraFieldIdHexes' => $packageInventory['centralOnlyExtraFieldIdHexes'] ?? [],
            'localOnlyExtraFieldIdHexes' => $packageInventory['localOnlyExtraFieldIdHexes'] ?? [],
            'hasUnixOwnerMetadata' => ($packageInventory['hasUnixOwnerMetadata'] ?? false) === true,
            'hasMismatchedUnixOwnerMetadata' => ($packageInventory['hasMismatchedUnixOwnerMetadata'] ?? false) === true,
            'unixOwnerMetadataEntryCount' => $packageInventory['unixOwnerMetadataEntryCount'] ?? 0,
            'centralUnixOwnerMetadataEntryCount' => $packageInventory['centralUnixOwnerMetadataEntryCount'] ?? 0,
            'localUnixOwnerMetadataEntryCount' => $packageInventory['localUnixOwnerMetadataEntryCount'] ?? 0,
            'mismatchedUnixOwnerMetadataEntryCount' => $packageInventory['mismatchedUnixOwnerMetadataEntryCount'] ?? 0,
            'unixOwnerMetadataIssueCodes' => $packageInventory['unixOwnerMetadataIssueCodes'] ?? [],
            'unixOwnerMetadataEntries' => $packageInventory['unixOwnerMetadataEntries'] ?? [],
            'mismatchedUnixOwnerMetadataEntries' => $packageInventory['mismatchedUnixOwnerMetadataEntries'] ?? [],
            'unixOwnerMetadataByteExposurePolicy' => $packageInventory['unixOwnerMetadataByteExposurePolicy'] ?? 'zip-unix-owner-metadata-only',
            'unixOwnerMetadataCanExposeBytes' => ($packageInventory['unixOwnerMetadataCanExposeBytes'] ?? false) === true,
            'extraFieldIdRoleCount' => $packageInventory['extraFieldIdRoleCount'] ?? 0,
            'extraFieldIdRoleCounts' => $packageInventory['extraFieldIdRoleCounts'] ?? [],
            'entryNamesByExtraFieldIdRole' => $packageInventory['entryNamesByExtraFieldIdRole'] ?? [],
            'extraFieldIdManifestMediaFamilyCount' => $packageInventory['extraFieldIdManifestMediaFamilyCount'] ?? 0,
            'extraFieldIdManifestMediaFamilyCounts' => $packageInventory['extraFieldIdManifestMediaFamilyCounts'] ?? [],
            'entryNamesByExtraFieldIdManifestMediaFamily' => $packageInventory['entryNamesByExtraFieldIdManifestMediaFamily'] ?? [],
            'centralOnlyExtraFieldIdRoleCount' => $packageInventory['centralOnlyExtraFieldIdRoleCount'] ?? 0,
            'centralOnlyExtraFieldIdRoleCounts' => $packageInventory['centralOnlyExtraFieldIdRoleCounts'] ?? [],
            'entryNamesByCentralOnlyExtraFieldIdRole' => $packageInventory['entryNamesByCentralOnlyExtraFieldIdRole'] ?? [],
            'centralOnlyExtraFieldIdManifestMediaFamilyCount' => $packageInventory['centralOnlyExtraFieldIdManifestMediaFamilyCount'] ?? 0,
            'centralOnlyExtraFieldIdManifestMediaFamilyCounts' => $packageInventory['centralOnlyExtraFieldIdManifestMediaFamilyCounts'] ?? [],
            'entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily' => $packageInventory['entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily'] ?? [],
            'localOnlyExtraFieldIdRoleCount' => $packageInventory['localOnlyExtraFieldIdRoleCount'] ?? 0,
            'localOnlyExtraFieldIdRoleCounts' => $packageInventory['localOnlyExtraFieldIdRoleCounts'] ?? [],
            'entryNamesByLocalOnlyExtraFieldIdRole' => $packageInventory['entryNamesByLocalOnlyExtraFieldIdRole'] ?? [],
            'localOnlyExtraFieldIdManifestMediaFamilyCount' => $packageInventory['localOnlyExtraFieldIdManifestMediaFamilyCount'] ?? 0,
            'localOnlyExtraFieldIdManifestMediaFamilyCounts' => $packageInventory['localOnlyExtraFieldIdManifestMediaFamilyCounts'] ?? [],
            'entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily' => $packageInventory['entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily'] ?? [],
            'mismatchedExtraFieldValueIdRoleCount' => $packageInventory['mismatchedExtraFieldValueIdRoleCount'] ?? 0,
            'mismatchedExtraFieldValueIdRoleCounts' => $packageInventory['mismatchedExtraFieldValueIdRoleCounts'] ?? [],
            'entryNamesByMismatchedExtraFieldValueIdRole' => $packageInventory['entryNamesByMismatchedExtraFieldValueIdRole'] ?? [],
            'mismatchedExtraFieldValueIdManifestMediaFamilyCount' => $packageInventory['mismatchedExtraFieldValueIdManifestMediaFamilyCount'] ?? 0,
            'mismatchedExtraFieldValueIdManifestMediaFamilyCounts' => $packageInventory['mismatchedExtraFieldValueIdManifestMediaFamilyCounts'] ?? [],
            'entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily' => $packageInventory['entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily'] ?? [],
            'extraFieldIdRoleSummaryCount' => $packageInventory['extraFieldIdRoleSummaryCount'] ?? 0,
            'extraFieldIdRoleSummaries' => $packageInventory['extraFieldIdRoleSummaries'] ?? [],
            'platformMetadataEntryCount' => $packageInventory['platformMetadataEntryCount'] ?? 0,
            'nameHygieneReviewEntryCount' => $packageInventory['nameHygieneReviewEntryCount'] ?? 0,
            'nameHygieneLeadingOrTrailingWhitespaceEntryCount' => $packageInventory['nameHygieneLeadingOrTrailingWhitespaceEntryCount'] ?? 0,
            'nameHygieneTrailingDotSegmentEntryCount' => $packageInventory['nameHygieneTrailingDotSegmentEntryCount'] ?? 0,
            'nameHygieneWindowsReservedNameEntryCount' => $packageInventory['nameHygieneWindowsReservedNameEntryCount'] ?? 0,
            'nameHygieneWindowsAlternateDataStreamEntryCount' => $packageInventory['nameHygieneWindowsAlternateDataStreamEntryCount'] ?? 0,
            'nameHygieneUnicodeFormatControlEntryCount' => $packageInventory['nameHygieneUnicodeFormatControlEntryCount'] ?? 0,
            'nameHygieneUnicodeBidiControlEntryCount' => $packageInventory['nameHygieneUnicodeBidiControlEntryCount'] ?? 0,
            'knownCreatorHostSystemEntryCount' => $packageInventory['knownCreatorHostSystemEntryCount'] ?? 0,
            'unknownCreatorHostSystemEntryCount' => $packageInventory['unknownCreatorHostSystemEntryCount'] ?? 0,
            'creatorVersionBelowNeededEntryCount' => $packageInventory['creatorVersionBelowNeededEntryCount'] ?? 0,
            'unixModeEntryCount' => $packageInventory['unixModeEntryCount'] ?? 0,
            'executableFileCount' => $packageInventory['executableFileCount'] ?? 0,
            'writablePermissionEntryCount' => $packageInventory['writablePermissionEntryCount'] ?? 0,
            'dosAttributeEntryCount' => $packageInventory['dosAttributeEntryCount'] ?? 0,
            'hiddenSystemOrVolumeLabelEntryCount' => $packageInventory['hiddenSystemOrVolumeLabelEntryCount'] ?? 0,
            'internalAttributeEntryCount' => $packageInventory['internalAttributeEntryCount'] ?? 0,
            'rawNameProvenanceEntryCount' => $packageInventory['rawNameProvenanceEntryCount'] ?? 0,
            'legacyEncodedNameEntryCount' => $packageInventory['legacyEncodedNameEntryCount'] ?? 0,
            'unicodePathExtraEntryCount' => $packageInventory['unicodePathExtraEntryCount'] ?? 0,
            'decodedNameDiffersFromRawNameEntryCount' => $packageInventory['decodedNameDiffersFromRawNameEntryCount'] ?? 0,
            'rawNameProvenanceEntries' => $packageInventory['rawNameProvenanceEntries'] ?? [],
            'zipNamePolicyValid' => ($packageInventory['zipNamePolicyValid'] ?? false) === true,
            'zipNamePolicyIssueCount' => $packageInventory['zipNamePolicyIssueCount'] ?? 0,
            'zipNamePolicyIssueCodes' => $packageInventory['zipNamePolicyIssueCodes'] ?? [],
            'zipPathHierarchyCollisionEntryCount' => $packageInventory['zipPathHierarchyCollisionEntryCount'] ?? 0,
            'zipCaseInsensitiveNameCollisionGroupCount' => $packageInventory['zipCaseInsensitiveNameCollisionGroupCount'] ?? 0,
            'zipCaseInsensitiveNameCollisionEntryCount' => $packageInventory['zipCaseInsensitiveNameCollisionEntryCount'] ?? 0,
            'zipRawNameCollisionGroupCount' => $packageInventory['zipRawNameCollisionGroupCount'] ?? 0,
            'zipRawNameCollisionEntryCount' => $packageInventory['zipRawNameCollisionEntryCount'] ?? 0,
            'zipRawNameProvenanceEntryCount' => $packageInventory['zipRawNameProvenanceEntryCount'] ?? 0,
            'zipNameHygieneReviewEntryCount' => $packageInventory['zipNameHygieneReviewEntryCount'] ?? 0,
            'zipNameHygieneLeadingOrTrailingWhitespaceEntryCount' => $packageInventory['zipNameHygieneLeadingOrTrailingWhitespaceEntryCount'] ?? 0,
            'zipNameHygieneTrailingDotSegmentEntryCount' => $packageInventory['zipNameHygieneTrailingDotSegmentEntryCount'] ?? 0,
            'zipNameHygieneWindowsReservedNameEntryCount' => $packageInventory['zipNameHygieneWindowsReservedNameEntryCount'] ?? 0,
            'zipNameHygieneWindowsAlternateDataStreamEntryCount' => $packageInventory['zipNameHygieneWindowsAlternateDataStreamEntryCount'] ?? 0,
            'zipNameHygieneUnicodeFormatControlEntryCount' => $packageInventory['zipNameHygieneUnicodeFormatControlEntryCount'] ?? 0,
            'zipNameHygieneUnicodeBidiControlEntryCount' => $packageInventory['zipNameHygieneUnicodeBidiControlEntryCount'] ?? 0,
            'localHeaderMetadataEntryCount' => $packageInventory['localHeaderMetadataEntryCount'] ?? 0,
            'localHeaderMetadataIsSupportedByBoundedReader' => ($packageInventory['localHeaderMetadataIsSupportedByBoundedReader'] ?? false) === true,
            'localHeaderMetadataIssueCodes' => $packageInventory['localHeaderMetadataIssueCodes'] ?? [],
            'localHeaderMetadataMismatchEntryCount' => $packageInventory['localHeaderMetadataMismatchEntryCount'] ?? 0,
            'localHeaderMetadataMismatchedEntries' => $packageInventory['localHeaderMetadataMismatchedEntries'] ?? [],
            'generalPurposeFlagEntryCount' => $packageInventory['generalPurposeFlagEntryCount'] ?? 0,
            'generalPurposeFlagSupportedEntryCount' => $packageInventory['generalPurposeFlagSupportedEntryCount'] ?? 0,
            'unsupportedGeneralPurposeFlagEntryCount' => $packageInventory['unsupportedGeneralPurposeFlagEntryCount'] ?? 0,
            'utf8NameGeneralPurposeFlagEntryCount' => $packageInventory['utf8NameGeneralPurposeFlagEntryCount'] ?? 0,
            'dataDescriptorGeneralPurposeFlagEntryCount' => $packageInventory['dataDescriptorGeneralPurposeFlagEntryCount'] ?? 0,
            'deflateOptionGeneralPurposeFlagEntryCount' => $packageInventory['deflateOptionGeneralPurposeFlagEntryCount'] ?? 0,
            'strictGeneralPurposeFlagReviewEntryCount' => $packageInventory['strictGeneralPurposeFlagReviewEntryCount'] ?? 0,
            'unsupportedGeneralPurposeFlagEntries' => $packageInventory['unsupportedGeneralPurposeFlagEntries'] ?? [],
            'strictGeneralPurposeFlagReviewEntries' => $packageInventory['strictGeneralPurposeFlagReviewEntries'] ?? [],
            'zipPackageManifestSha256' => $packageInventory['zipPackageManifestSha256'] ?? null,
            ...$zipPackageManifestSummary,
            'packageSource' => $packageInventory['packageSource'] ?? [],
            'archiveLength' => $packageInventory['archiveLength'] ?? 0,
            'archiveSha256' => $packageInventory['archiveSha256'] ?? null,
            'centralDirectoryOffset' => $packageInventory['centralDirectoryOffset'] ?? null,
            'centralDirectoryBytes' => $packageInventory['centralDirectoryBytes'] ?? null,
            'centralDirectoryEnd' => $packageInventory['centralDirectoryEnd'] ?? null,
            'centralDirectorySha256' => $packageInventory['centralDirectorySha256'] ?? null,
            'centralDirectoryToEocdGapOffset' => $packageInventory['centralDirectoryToEocdGapOffset'] ?? null,
            'centralDirectoryToEocdGapBytes' => $packageInventory['centralDirectoryToEocdGapBytes'] ?? 0,
            'centralDirectoryToEocdGapSha256' => $packageInventory['centralDirectoryToEocdGapSha256'] ?? null,
            'endOfCentralDirectoryOffset' => $packageInventory['endOfCentralDirectoryOffset'] ?? null,
            'endOfCentralDirectoryBytes' => $packageInventory['endOfCentralDirectoryBytes'] ?? null,
            'endOfCentralDirectoryEnd' => $packageInventory['endOfCentralDirectoryEnd'] ?? null,
            'endOfCentralDirectorySha256' => $packageInventory['endOfCentralDirectorySha256'] ?? null,
            'packageCommentOffset' => $packageInventory['packageCommentOffset'] ?? null,
            'packageCommentBytes' => $packageInventory['packageCommentBytes'] ?? 0,
            'packageCommentSha256' => $packageInventory['packageCommentSha256'] ?? null,
            'hasCentralDirectorySignature' => ($packageInventory['hasCentralDirectorySignature'] ?? false) === true,
            'centralDirectorySignatureOffset' => $packageInventory['centralDirectorySignatureOffset'] ?? null,
            'centralDirectorySignatureDataOffset' => $packageInventory['centralDirectorySignatureDataOffset'] ?? null,
            'centralDirectorySignatureEnd' => $packageInventory['centralDirectorySignatureEnd'] ?? null,
            'centralDirectorySignatureBytes' => $packageInventory['centralDirectorySignatureBytes'] ?? 0,
            'centralDirectorySignatureRecordBytes' => $packageInventory['centralDirectorySignatureRecordBytes'] ?? 0,
            'centralDirectorySignaturePreviewHex' => $packageInventory['centralDirectorySignaturePreviewHex'] ?? '',
            'centralDirectorySignaturePreviewByteCount' => $packageInventory['centralDirectorySignaturePreviewByteCount'] ?? 0,
            'centralDirectorySignatureSha256' => $packageInventory['centralDirectorySignatureSha256'] ?? null,
            'centralDirectorySignatureLocation' => $packageInventory['centralDirectorySignatureLocation'] ?? null,
            'centralDirectorySignatureVerification' => $packageInventory['centralDirectorySignatureVerification'] ?? 'not-present',
            'centralDirectorySignatureByteExposurePolicy' => $packageInventory['centralDirectorySignatureByteExposurePolicy'] ?? 'not-present',
            'centralDirectorySignatureCanExposeBytes' => ($packageInventory['centralDirectorySignatureCanExposeBytes'] ?? false) === true,
            'totalByteLength' => $packageInventory['totalByteLength'] ?? 0,
            'totalCompressedByteLength' => $packageInventory['totalCompressedByteLength'] ?? 0,
            'exposableByteLength' => $packageInventory['exposableByteLength'] ?? 0,
            'blockedByteLength' => $packageInventory['blockedByteLength'] ?? 0,
        ];
        $identityPayload = $payload + [
            'comments' => [
                'hasPackageComment' => ($comments['hasPackageComment'] ?? false) === true,
                'packageComment' => $comments['packageComment'] ?? null,
                'entryCommentCount' => is_int($comments['entryCommentCount'] ?? null) ? $comments['entryCommentCount'] : 0,
                'commentedEntryNames' => is_array($comments['commentedEntryNames'] ?? null) ? $comments['commentedEntryNames'] : [],
                'entries' => is_array($comments['entries'] ?? null) ? $comments['entries'] : [],
            ],
        ];
        $identityJson = json_encode(
            self::canonicalIdentityValue($identityPayload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $payload['identitySha256'] = hash('sha256', $identityJson);
        $payload['identityPayloadByteLength'] = strlen($identityJson);
        $payload['byteExposurePolicy'] = 'odf-package-identity-metadata-only';
        $payload['canExposeBytes'] = false;

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @return array<string, mixed>
     */
    private static function manifestMediaTypeSummary(array $manifestEntries): array
    {
        $groups = [];
        $groupOrder = [];
        $emptyMediaTypeParts = [];
        $emptyMediaTypeDirectoryParts = [];
        $emptyMediaTypeNonDirectoryItems = [];
        $invalidDeclaredSizeItems = [];
        $parameterValueRecords = [];
        $groupParameterValueRecords = [];
        $diagnostics = [];
        $summary = [
            'manifestItemCount' => count($manifestEntries),
            'typedItemCount' => 0,
            'mediaTypeCount' => 0,
            'emptyMediaTypeCount' => 0,
            'emptyMediaTypeParts' => [],
            'emptyMediaTypeDirectoryCount' => 0,
            'emptyMediaTypeDirectoryParts' => [],
            'emptyMediaTypeNonDirectoryCount' => 0,
            'emptyMediaTypeNonDirectoryItems' => [],
            'diagnosticCount' => 0,
            'diagnosticCodeCounts' => [],
            'diagnostics' => [],
            'directoryCount' => 0,
            'missingCount' => 0,
            'encryptedCount' => 0,
            'versionedItemCount' => 0,
            'manifestVersions' => [],
            'versionedItems' => [],
            'preferredViewModeCount' => 0,
            'preferredViewModes' => [],
            'preferredViewModeItems' => [],
            'declaredSizeMismatchCount' => 0,
            'invalidDeclaredSizeCount' => 0,
            'invalidDeclaredSizeItems' => [],
            'parameterizedItemCount' => 0,
            'mediaTypeParameterNames' => [],
            'mediaTypeParameterValueCount' => 0,
            'mediaTypeParameterValuesByName' => [],
            'mediaTypeParameterValueCounts' => [],
            'mediaTypeParameterValueSummaries' => [],
            'storedByteLength' => 0,
            'compressedByteLength' => 0,
            'exposableByteLength' => 0,
            'declaredSize' => 0,
            'storedCompressionMethodCount' => 0,
            'deflatedCompressionMethodCount' => 0,
            'unsupportedCompressionMethodCount' => 0,
            'items' => [],
        ];

        foreach ($manifestEntries as $entry) {
            $part = self::manifestMediaTypePartLabel($entry);
            $fullPath = is_string($entry['fullPath'] ?? null) ? $entry['fullPath'] : ($entry['path'] ?? null);
            $packagePath = is_string($entry['part'] ?? null) ? $entry['part'] : ($entry['packagePath'] ?? null);
            $mediaType = trim((string) ($entry['mediaType'] ?? ''));
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeBase = (string) ($entry['mediaTypeBase'] ?? $mediaTypeReport['mediaTypeBase']);
            $mediaTypeParameters = is_array($entry['mediaTypeParameters'] ?? null)
                ? $entry['mediaTypeParameters']
                : $mediaTypeReport['mediaTypeParameters'];
            $exists = ($entry['exists'] ?? false) === true;
            $isDirectory = ($entry['isDirectory'] ?? false) === true;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declaredSizeMismatch = ($entry['declaredSizeMismatch'] ?? false) === true;
            $declaredSizeInvalid = ($entry['declaredSizeInvalid'] ?? false) === true;
            $byteLength = $entry['byteLength'] ?? null;
            $storedByteLength = $entry['storedByteLength'] ?? null;
            $compressedByteLength = $entry['compressedByteLength'] ?? null;
            $declaredSize = $entry['declaredSize'] ?? ($entry['size'] ?? null);
            $compressionMethod = $entry['compressionMethod'] ?? null;
            $itemDiagnostics = is_array($entry['diagnostics'] ?? null) ? $entry['diagnostics'] : [];
            $manifestVersion = trim((string) ($entry['version'] ?? ''));
            $preferredViewMode = trim((string) ($entry['preferredViewMode'] ?? ''));

            if ($isDirectory) {
                ++$summary['directoryCount'];
            }
            if (!$exists) {
                ++$summary['missingCount'];
            }
            if ($encrypted) {
                ++$summary['encryptedCount'];
            }
            if ($declaredSizeMismatch) {
                ++$summary['declaredSizeMismatchCount'];
            }
            if ($declaredSizeInvalid) {
                ++$summary['invalidDeclaredSizeCount'];
                $invalidDeclaredSizeItems[] = self::withoutEmptyValues([
                    'fullPath' => $fullPath,
                    'part' => $packagePath,
                    'mediaType' => $mediaType,
                    'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
                    'exists' => $exists,
                    'isDirectory' => $isDirectory,
                    'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                    'diagnostics' => $itemDiagnostics,
                ]);
            }
            if ($manifestVersion !== '') {
                ++$summary['versionedItemCount'];
                if (!in_array($manifestVersion, $summary['manifestVersions'], true)) {
                    $summary['manifestVersions'][] = $manifestVersion;
                }
                $summary['versionedItems'][] = self::withoutEmptyValues([
                    'fullPath' => $fullPath,
                    'part' => $packagePath,
                    'mediaType' => $mediaType,
                    'version' => $manifestVersion,
                    'exists' => $exists,
                    'isDirectory' => $isDirectory,
                ]);
            }
            if ($preferredViewMode !== '') {
                ++$summary['preferredViewModeCount'];
                if (!in_array($preferredViewMode, $summary['preferredViewModes'], true)) {
                    $summary['preferredViewModes'][] = $preferredViewMode;
                }
                $summary['preferredViewModeItems'][] = self::withoutEmptyValues([
                    'fullPath' => $fullPath,
                    'part' => $packagePath,
                    'mediaType' => $mediaType,
                    'preferredViewMode' => $preferredViewMode,
                    'exists' => $exists,
                    'isDirectory' => $isDirectory,
                ]);
            }
            if (is_int($storedByteLength)) {
                $summary['storedByteLength'] += $storedByteLength;
            }
            if (is_int($byteLength) && ($entry['canExposeBytes'] ?? false) === true) {
                $summary['exposableByteLength'] += $byteLength;
            }
            if (is_int($compressedByteLength)) {
                $summary['compressedByteLength'] += $compressedByteLength;
            }
            if (is_int($declaredSize)) {
                $summary['declaredSize'] += $declaredSize;
            }
            if ($compressionMethod === 0) {
                ++$summary['storedCompressionMethodCount'];
            } elseif ($compressionMethod === 8) {
                ++$summary['deflatedCompressionMethodCount'];
            } elseif (is_int($compressionMethod)) {
                ++$summary['unsupportedCompressionMethodCount'];
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $code = (string) $diagnostic;
                if ($code === '') {
                    continue;
                }

                $diagnostics[] = self::withoutEmptyValues([
                    'code' => $code,
                    'fullPath' => $fullPath,
                    'part' => $packagePath,
                    'mediaType' => $mediaType,
                    'exists' => $exists,
                    'isDirectory' => $isDirectory,
                    'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                ]);
            }

            if ($mediaType === '') {
                $emptyMediaTypeParts[] = $part;
                if ($isDirectory) {
                    $emptyMediaTypeDirectoryParts[] = $part;
                } else {
                    $emptyMediaTypeNonDirectoryItems[] = self::withoutEmptyValues([
                        'fullPath' => $fullPath,
                        'part' => $packagePath,
                        'exists' => $exists,
                        'storedByteLength' => $storedByteLength,
                        'compressedByteLength' => $compressedByteLength,
                        'compressionMethod' => $compressionMethod,
                        'compressionMethodName' => $entry['compressionMethodName'] ?? null,
                        'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                        'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
                        'diagnostics' => $itemDiagnostics,
                    ]);
                }
                continue;
            }

            $groupMediaType = $mediaTypeBase === '' ? $mediaType : $mediaTypeBase;
            if (!isset($groups[$groupMediaType])) {
                $groups[$groupMediaType] = [
                    'mediaType' => $groupMediaType,
                    'count' => 0,
                    'parts' => [],
                    'rawMediaTypes' => [],
                    'rawMediaTypeCount' => 0,
                    'parameterizedItemCount' => 0,
                    'mediaTypeParameterNames' => [],
                    'mediaTypeParameterValueCount' => 0,
                    'mediaTypeParameterValuesByName' => [],
                    'mediaTypeParameterValueCounts' => [],
                    'mediaTypeParameterValueSummaries' => [],
                    'existsCount' => 0,
                    'missingCount' => 0,
                    'directoryCount' => 0,
                    'encryptedCount' => 0,
                    'versionedItemCount' => 0,
                    'manifestVersions' => [],
                    'preferredViewModeCount' => 0,
                    'preferredViewModes' => [],
                    'declaredSizeMismatchCount' => 0,
                    'invalidDeclaredSizeCount' => 0,
                    'storedByteLength' => 0,
                    'compressedByteLength' => 0,
                    'exposableByteLength' => 0,
                    'declaredSize' => 0,
                    'storedCompressionMethodCount' => 0,
                    'deflatedCompressionMethodCount' => 0,
                    'unsupportedCompressionMethodCount' => 0,
                ];
                $groupParameterValueRecords[$groupMediaType] = [];
                $groupOrder[] = $groupMediaType;
            }

            ++$summary['typedItemCount'];
            ++$groups[$groupMediaType]['count'];
            $groups[$groupMediaType]['parts'][] = $part;
            if (!in_array($mediaType, $groups[$groupMediaType]['rawMediaTypes'], true)) {
                $groups[$groupMediaType]['rawMediaTypes'][] = $mediaType;
            }
            if (($entry['mediaTypeHasParameters'] ?? $mediaTypeReport['mediaTypeHasParameters']) === true) {
                ++$summary['parameterizedItemCount'];
                ++$groups[$groupMediaType]['parameterizedItemCount'];
            }
            foreach ($mediaTypeParameters as $parameter) {
                if (!is_array($parameter)) {
                    continue;
                }
                $name = (string) ($parameter['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                if (!in_array($name, $summary['mediaTypeParameterNames'], true)) {
                    $summary['mediaTypeParameterNames'][] = $name;
                }
                if (!in_array($name, $groups[$groupMediaType]['mediaTypeParameterNames'], true)) {
                    $groups[$groupMediaType]['mediaTypeParameterNames'][] = $name;
                }
                $value = (string) ($parameter['value'] ?? '');
                self::recordMediaTypeParameterValue($parameterValueRecords, $name, $value, $part, $mediaType);
                self::recordMediaTypeParameterValue($groupParameterValueRecords[$groupMediaType], $name, $value, $part, $mediaType);
            }
            if ($exists) {
                ++$groups[$groupMediaType]['existsCount'];
            } else {
                ++$groups[$groupMediaType]['missingCount'];
            }
            if ($isDirectory) {
                ++$groups[$groupMediaType]['directoryCount'];
            }
            if ($encrypted) {
                ++$groups[$groupMediaType]['encryptedCount'];
            }
            if ($manifestVersion !== '') {
                ++$groups[$groupMediaType]['versionedItemCount'];
                if (!in_array($manifestVersion, $groups[$groupMediaType]['manifestVersions'], true)) {
                    $groups[$groupMediaType]['manifestVersions'][] = $manifestVersion;
                }
            }
            if ($preferredViewMode !== '') {
                ++$groups[$groupMediaType]['preferredViewModeCount'];
                if (!in_array($preferredViewMode, $groups[$groupMediaType]['preferredViewModes'], true)) {
                    $groups[$groupMediaType]['preferredViewModes'][] = $preferredViewMode;
                }
            }
            if ($declaredSizeMismatch) {
                ++$groups[$groupMediaType]['declaredSizeMismatchCount'];
            }
            if ($declaredSizeInvalid) {
                ++$groups[$groupMediaType]['invalidDeclaredSizeCount'];
            }
            if (is_int($storedByteLength)) {
                $groups[$groupMediaType]['storedByteLength'] += $storedByteLength;
            }
            if (is_int($byteLength) && ($entry['canExposeBytes'] ?? false) === true) {
                $groups[$groupMediaType]['exposableByteLength'] += $byteLength;
            }
            if (is_int($compressedByteLength)) {
                $groups[$groupMediaType]['compressedByteLength'] += $compressedByteLength;
            }
            if (is_int($declaredSize)) {
                $groups[$groupMediaType]['declaredSize'] += $declaredSize;
            }
            if ($compressionMethod === 0) {
                ++$groups[$groupMediaType]['storedCompressionMethodCount'];
            } elseif ($compressionMethod === 8) {
                ++$groups[$groupMediaType]['deflatedCompressionMethodCount'];
            } elseif (is_int($compressionMethod)) {
                ++$groups[$groupMediaType]['unsupportedCompressionMethodCount'];
            }
        }

        $items = [];
        sort($summary['mediaTypeParameterNames'], SORT_STRING);
        $parameterValueRollup = self::mediaTypeParameterValueRollup($parameterValueRecords);
        $summary['mediaTypeParameterValueCount'] = $parameterValueRollup['valueCount'];
        $summary['mediaTypeParameterValuesByName'] = $parameterValueRollup['valuesByName'];
        $summary['mediaTypeParameterValueCounts'] = $parameterValueRollup['valueCounts'];
        $summary['mediaTypeParameterValueSummaries'] = $parameterValueRollup['summaries'];
        foreach ($groupOrder as $mediaType) {
            sort($groups[$mediaType]['mediaTypeParameterNames'], SORT_STRING);
            $groupParameterValueRollup = self::mediaTypeParameterValueRollup(
                $groupParameterValueRecords[$mediaType] ?? []
            );
            $groups[$mediaType]['mediaTypeParameterValueCount'] = $groupParameterValueRollup['valueCount'];
            $groups[$mediaType]['mediaTypeParameterValuesByName'] = $groupParameterValueRollup['valuesByName'];
            $groups[$mediaType]['mediaTypeParameterValueCounts'] = $groupParameterValueRollup['valueCounts'];
            $groups[$mediaType]['mediaTypeParameterValueSummaries'] = $groupParameterValueRollup['summaries'];
            $groups[$mediaType]['rawMediaTypeCount'] = count($groups[$mediaType]['rawMediaTypes']);
            $items[] = $groups[$mediaType];
        }

        $summary['mediaTypeCount'] = count($items);
        $summary['emptyMediaTypeCount'] = count($emptyMediaTypeParts);
        $summary['emptyMediaTypeParts'] = $emptyMediaTypeParts;
        $summary['emptyMediaTypeDirectoryCount'] = count($emptyMediaTypeDirectoryParts);
        $summary['emptyMediaTypeDirectoryParts'] = $emptyMediaTypeDirectoryParts;
        $summary['emptyMediaTypeNonDirectoryCount'] = count($emptyMediaTypeNonDirectoryItems);
        $summary['emptyMediaTypeNonDirectoryItems'] = $emptyMediaTypeNonDirectoryItems;
        $summary['invalidDeclaredSizeItems'] = $invalidDeclaredSizeItems;
        $summary['diagnosticCount'] = count($diagnostics);
        $summary['diagnosticCodeCounts'] = self::diagnosticCodeCounts($diagnostics);
        $summary['diagnostics'] = $diagnostics;
        $summary['items'] = $items;

        return $summary;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $records
     */
    private static function recordMediaTypeParameterValue(
        array &$records,
        string $name,
        string $value,
        string $part,
        string $mediaType
    ): void {
        if ($name === '') {
            return;
        }

        $records[$name] ??= [];
        $records[$name][$value] ??= [
            'name' => $name,
            'value' => $value,
            'occurrenceCount' => 0,
            'parts' => [],
            'mediaTypes' => [],
        ];
        ++$records[$name][$value]['occurrenceCount'];
        if (!in_array($part, $records[$name][$value]['parts'], true)) {
            $records[$name][$value]['parts'][] = $part;
        }
        if (!in_array($mediaType, $records[$name][$value]['mediaTypes'], true)) {
            $records[$name][$value]['mediaTypes'][] = $mediaType;
        }
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $records
     * @return array{valueCount:int, valuesByName:array<string, list<string>>, valueCounts:array<string, array<string, int>>, summaries:list<array{name:string, value:string, occurrenceCount:int, partCount:int, parts:list<string>, mediaTypeCount:int, mediaTypes:list<string>}>}
     */
    private static function mediaTypeParameterValueRollup(array $records): array
    {
        $valuesByName = [];
        $valueCounts = [];
        $summaries = [];

        ksort($records, SORT_STRING);
        foreach ($records as $name => $values) {
            ksort($values, SORT_STRING);
            foreach ($values as $value => $record) {
                $parts = is_array($record['parts'] ?? null) ? $record['parts'] : [];
                $mediaTypes = is_array($record['mediaTypes'] ?? null) ? $record['mediaTypes'] : [];
                sort($parts, SORT_STRING);
                sort($mediaTypes, SORT_STRING);

                $valuesByName[$name] ??= [];
                if (!in_array((string) $value, $valuesByName[$name], true)) {
                    $valuesByName[$name][] = (string) $value;
                }
                $valueCounts[$name] ??= [];
                $valueCounts[$name][(string) $value] = (int) ($record['occurrenceCount'] ?? 0);
                $summaries[] = [
                    'name' => $name,
                    'value' => (string) $value,
                    'occurrenceCount' => (int) ($record['occurrenceCount'] ?? 0),
                    'partCount' => count($parts),
                    'parts' => array_values($parts),
                    'mediaTypeCount' => count($mediaTypes),
                    'mediaTypes' => array_values($mediaTypes),
                ];
            }
        }

        return [
            'valueCount' => count($summaries),
            'valuesByName' => $valuesByName,
            'valueCounts' => $valueCounts,
            'summaries' => $summaries,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function manifestMediaTypePartLabel(array $entry): string
    {
        $part = $entry['part'] ?? ($entry['packagePath'] ?? null);
        if (is_string($part) && $part !== '') {
            return $part;
        }

        $fullPath = $entry['fullPath'] ?? ($entry['path'] ?? null);
        if (is_string($fullPath) && $fullPath !== '') {
            return $fullPath;
        }

        return '/';
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, int>
     */
    private static function diagnosticCodeCounts(array $diagnostics): array
    {
        $counts = [];
        foreach ($diagnostics as $diagnostic) {
            $code = is_array($diagnostic) ? (string) ($diagnostic['code'] ?? '') : (string) $diagnostic;
            if ($code === '') {
                continue;
            }

            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function encryptedManifestEntries(): array
    {
        return array_values(array_filter(
            $this->manifestEntries,
            static fn (array $entry): bool => ($entry['encrypted'] ?? false) === true
        ));
    }

    private static function packagePartExtension(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === '' ? null : $extension;
    }

    private static function packagePartRawExtension(string $path): ?string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension === '' ? null : $extension;
    }

    private static function packagePartBasenameStem(string $basename): string
    {
        $stem = pathinfo($basename, PATHINFO_FILENAME);

        return $stem === '' ? $basename : $stem;
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array{
     *     roleCount:int,
     *     roleCounts:array<string, int>,
     *     roleByteLengths:array<string, int>,
     *     roleCompressedByteLengths:array<string, int>,
     *     roleSummaries:list<array<string, mixed>>
     * }
     */
    private static function centralDirectoryOrderMismatchRoleInventory(array $parts): array
    {
        $summaries = [];

        foreach ($parts as $name => $part) {
            if (($part['matchesCentralDirectoryOrder'] ?? null) !== false) {
                continue;
            }

            $entryName = is_string($part['path'] ?? null) && $part['path'] !== ''
                ? $part['path']
                : (string) $name;
            $roles = array_values(array_unique(array_filter(
                array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []),
                static fn (string $role): bool => $role !== ''
            )));
            $byteLength = is_int($part['byteLength'] ?? null) ? $part['byteLength'] : 0;
            $compressedByteLength = is_int($part['compressedByteLength'] ?? null) ? $part['compressedByteLength'] : 0;
            $centralDirectoryIndex = $part['centralDirectoryIndex'] ?? null;
            $localHeaderOrder = $part['localHeaderOrder'] ?? null;

            foreach ($roles as $role) {
                if (!isset($summaries[$role])) {
                    $summaries[$role] = [
                        'role' => $role,
                        'mismatchedEntryCount' => 0,
                        'byteLength' => 0,
                        'compressedByteLength' => 0,
                        'mismatchedEntryNames' => [],
                        'centralDirectoryIndexes' => [],
                        'localHeaderOrders' => [],
                    ];
                }

                ++$summaries[$role]['mismatchedEntryCount'];
                $summaries[$role]['byteLength'] += $byteLength;
                $summaries[$role]['compressedByteLength'] += $compressedByteLength;
                $summaries[$role]['mismatchedEntryNames'][] = $entryName;
                if (is_int($centralDirectoryIndex)) {
                    $summaries[$role]['centralDirectoryIndexes'][] = $centralDirectoryIndex;
                }
                if (is_int($localHeaderOrder)) {
                    $summaries[$role]['localHeaderOrders'][] = $localHeaderOrder;
                }
            }
        }

        ksort($summaries, SORT_STRING);
        $roleCounts = [];
        $roleByteLengths = [];
        $roleCompressedByteLengths = [];
        foreach ($summaries as $role => $summary) {
            $roleCounts[$role] = $summary['mismatchedEntryCount'];
            $roleByteLengths[$role] = $summary['byteLength'];
            $roleCompressedByteLengths[$role] = $summary['compressedByteLength'];
        }

        return [
            'roleCount' => count($summaries),
            'roleCounts' => $roleCounts,
            'roleByteLengths' => $roleByteLengths,
            'roleCompressedByteLengths' => $roleCompressedByteLengths,
            'roleSummaries' => array_values($summaries),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array{
     *     extensionlessPackagePartCount:int,
     *     packagePartExtensionCounts:array<string, int>,
     *     entryNamesByPackagePartExtension:array<string, list<string>>,
     *     packagePartExtensionSummaries:list<array<string, mixed>>
     * }
     */
    private static function packagePartExtensionInventory(array $parts): array
    {
        $extensionlessPackagePartCount = 0;
        $extensionCounts = [];
        $entryNamesByExtension = [];
        $summaries = [];

        foreach ($parts as $name => $part) {
            $path = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
            $extension = is_string($part['packagePartExtension'] ?? null) ? $part['packagePartExtension'] : null;
            $extensionKey = $extension ?? '(none)';
            $isDirectory = ($part['isDirectory'] ?? false) === true;
            $byteLength = is_int($part['byteLength'] ?? null) ? $part['byteLength'] : 0;
            $compressedByteLength = is_int($part['compressedByteLength'] ?? null) ? $part['compressedByteLength'] : 0;
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));

            if (!$isDirectory && $extension === null) {
                ++$extensionlessPackagePartCount;
            }

            $extensionCounts[$extensionKey] = ($extensionCounts[$extensionKey] ?? 0) + 1;
            $entryNamesByExtension[$extensionKey][] = $path;

            if (!isset($summaries[$extensionKey])) {
                $summaries[$extensionKey] = [
                    'extensionKey' => $extensionKey,
                    'packagePartExtension' => $extension,
                    'partCount' => 0,
                    'directoryEntryCount' => 0,
                    'extensionlessPackagePartCount' => 0,
                    'declaredPartCount' => 0,
                    'undeclaredPartCount' => 0,
                    'encryptedPartCount' => 0,
                    'exposablePartCount' => 0,
                    'blockedPartCount' => 0,
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'roleCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'partNames' => [],
                    'largestPart' => null,
                ];
            }

            ++$summaries[$extensionKey]['partCount'];
            $summaries[$extensionKey]['byteLength'] += $byteLength;
            $summaries[$extensionKey]['compressedByteLength'] += $compressedByteLength;
            $summaries[$extensionKey]['partNames'][] = $path;

            if ($isDirectory) {
                ++$summaries[$extensionKey]['directoryEntryCount'];
            }
            if (!$isDirectory && $extension === null) {
                ++$summaries[$extensionKey]['extensionlessPackagePartCount'];
            }
            if (($part['declaredInManifest'] ?? false) === true) {
                ++$summaries[$extensionKey]['declaredPartCount'];
            }
            if (($part['undeclared'] ?? false) === true) {
                ++$summaries[$extensionKey]['undeclaredPartCount'];
            }
            if (($part['encrypted'] ?? false) === true) {
                ++$summaries[$extensionKey]['encryptedPartCount'];
            }
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$summaries[$extensionKey]['exposablePartCount'];
            } else {
                ++$summaries[$extensionKey]['blockedPartCount'];
            }

            foreach ($roles as $role) {
                $summaries[$extensionKey]['roleCounts'][$role] =
                    ($summaries[$extensionKey]['roleCounts'][$role] ?? 0) + 1;
            }

            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) ? $part['byteExposurePolicy'] : '';
            if ($byteExposurePolicy !== '') {
                $summaries[$extensionKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                    ($summaries[$extensionKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            }

            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) ? $part['manifestMediaFamily'] : '';
            if ($manifestMediaFamily !== '') {
                $summaries[$extensionKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                    ($summaries[$extensionKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            }

            $partSummary = [
                'path' => $path,
                'byteLength' => $byteLength,
                'compressedByteLength' => $compressedByteLength,
                'roles' => $roles,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'encrypted' => ($part['encrypted'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $byteExposurePolicy === '' ? null : $byteExposurePolicy,
                'manifestMediaTypeBase' => is_string($part['manifestMediaTypeBase'] ?? null) ? $part['manifestMediaTypeBase'] : null,
                'manifestMediaFamily' => $manifestMediaFamily === '' ? null : $manifestMediaFamily,
            ];
            $largestPart = $summaries[$extensionKey]['largestPart'];
            if (
                !is_array($largestPart)
                || $byteLength > (int) ($largestPart['byteLength'] ?? 0)
                || ($byteLength === (int) ($largestPart['byteLength'] ?? 0) && strcmp($path, (string) ($largestPart['path'] ?? '')) < 0)
            ) {
                $summaries[$extensionKey]['largestPart'] = $partSummary;
            }
        }

        ksort($extensionCounts, SORT_STRING);
        ksort($entryNamesByExtension, SORT_STRING);
        foreach ($entryNamesByExtension as $extensionKey => $names) {
            sort($names, SORT_STRING);
            $entryNamesByExtension[$extensionKey] = $names;
        }

        ksort($summaries, SORT_STRING);
        foreach ($summaries as $extensionKey => $summary) {
            sort($summary['partNames'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            $summaries[$extensionKey] = $summary;
        }

        return [
            'extensionlessPackagePartCount' => $extensionlessPackagePartCount,
            'packagePartExtensionCounts' => $extensionCounts,
            'entryNamesByPackagePartExtension' => $entryNamesByExtension,
            'packagePartExtensionSummaries' => array_values($summaries),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array{
     *     packagePartRawExtensionCounts:array<string, int>,
     *     entryNamesByPackagePartRawExtension:array<string, list<string>>,
     *     packagePartRawExtensionUppercasePartCount:int,
     *     packagePartRawExtensionNormalizedPartCount:int,
     *     packagePartRawExtensionSummaries:list<array<string, mixed>>
     * }
     */
    private static function packagePartRawExtensionInventory(array $parts): array
    {
        $rawExtensionCounts = [];
        $entryNamesByRawExtension = [];
        $uppercasePartCount = 0;
        $normalizedPartCount = 0;
        $summaries = [];

        foreach ($parts as $name => $part) {
            $path = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
            $rawExtension = is_string($part['rawPackagePartExtension'] ?? null) ? $part['rawPackagePartExtension'] : null;
            $rawExtensionKey = $rawExtension ?? '(none)';
            $extension = is_string($part['packagePartExtension'] ?? null) ? $part['packagePartExtension'] : null;
            $extensionKey = $extension ?? '(none)';
            $isDirectory = ($part['isDirectory'] ?? false) === true;
            $byteLength = is_int($part['byteLength'] ?? null) ? $part['byteLength'] : 0;
            $compressedByteLength = is_int($part['compressedByteLength'] ?? null) ? $part['compressedByteLength'] : 0;
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));
            $hasUppercase = $rawExtension !== null && preg_match('/[A-Z]/', $rawExtension) === 1;
            $wasNormalized = $extension !== null && $rawExtension !== null && $extension !== $rawExtension;

            if ($hasUppercase) {
                ++$uppercasePartCount;
            }
            if ($wasNormalized) {
                ++$normalizedPartCount;
            }

            $rawExtensionCounts[$rawExtensionKey] = ($rawExtensionCounts[$rawExtensionKey] ?? 0) + 1;
            $entryNamesByRawExtension[$rawExtensionKey][] = $path;

            if (!isset($summaries[$rawExtensionKey])) {
                $summaries[$rawExtensionKey] = [
                    'rawExtensionKey' => $rawExtensionKey,
                    'rawPackagePartExtension' => $rawExtension,
                    'extensionlessPackagePart' => $rawExtension === null,
                    'partCount' => 0,
                    'directoryEntryCount' => 0,
                    'extensionlessPackagePartCount' => 0,
                    'uppercasePartCount' => 0,
                    'normalizedPartCount' => 0,
                    'declaredPartCount' => 0,
                    'undeclaredPartCount' => 0,
                    'encryptedPartCount' => 0,
                    'exposablePartCount' => 0,
                    'blockedPartCount' => 0,
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'packagePartExtensionCounts' => [],
                    'roleCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'partNames' => [],
                    'largestPart' => null,
                ];
            }

            ++$summaries[$rawExtensionKey]['partCount'];
            $summaries[$rawExtensionKey]['byteLength'] += $byteLength;
            $summaries[$rawExtensionKey]['compressedByteLength'] += $compressedByteLength;
            $summaries[$rawExtensionKey]['partNames'][] = $path;
            $summaries[$rawExtensionKey]['packagePartExtensionCounts'][$extensionKey] =
                ($summaries[$rawExtensionKey]['packagePartExtensionCounts'][$extensionKey] ?? 0) + 1;

            if ($isDirectory) {
                ++$summaries[$rawExtensionKey]['directoryEntryCount'];
            }
            if (!$isDirectory && $rawExtension === null) {
                ++$summaries[$rawExtensionKey]['extensionlessPackagePartCount'];
            }
            if ($hasUppercase) {
                ++$summaries[$rawExtensionKey]['uppercasePartCount'];
            }
            if ($wasNormalized) {
                ++$summaries[$rawExtensionKey]['normalizedPartCount'];
            }
            if (($part['declaredInManifest'] ?? false) === true) {
                ++$summaries[$rawExtensionKey]['declaredPartCount'];
            }
            if (($part['undeclared'] ?? false) === true) {
                ++$summaries[$rawExtensionKey]['undeclaredPartCount'];
            }
            if (($part['encrypted'] ?? false) === true) {
                ++$summaries[$rawExtensionKey]['encryptedPartCount'];
            }
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$summaries[$rawExtensionKey]['exposablePartCount'];
            } else {
                ++$summaries[$rawExtensionKey]['blockedPartCount'];
            }

            foreach ($roles as $role) {
                $summaries[$rawExtensionKey]['roleCounts'][$role] =
                    ($summaries[$rawExtensionKey]['roleCounts'][$role] ?? 0) + 1;
            }

            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) ? $part['byteExposurePolicy'] : '';
            if ($byteExposurePolicy !== '') {
                $summaries[$rawExtensionKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                    ($summaries[$rawExtensionKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            }

            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) ? $part['manifestMediaFamily'] : '';
            if ($manifestMediaFamily !== '') {
                $summaries[$rawExtensionKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                    ($summaries[$rawExtensionKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            }

            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) ? $part['manifestMediaTypeBase'] : '';
            if ($manifestMediaTypeBase !== '') {
                $summaries[$rawExtensionKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                    ($summaries[$rawExtensionKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            }

            $partSummary = [
                'path' => $path,
                'packagePartExtension' => $extension,
                'rawPackagePartExtension' => $rawExtension,
                'packagePartExtensionHasUppercase' => $hasUppercase,
                'packagePartExtensionWasNormalized' => $wasNormalized,
                'extensionlessPackagePart' => !$isDirectory && $rawExtension === null,
                'byteLength' => $byteLength,
                'compressedByteLength' => $compressedByteLength,
                'roles' => $roles,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'encrypted' => ($part['encrypted'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $byteExposurePolicy === '' ? null : $byteExposurePolicy,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '' ? null : $manifestMediaTypeBase,
                'manifestMediaFamily' => $manifestMediaFamily === '' ? null : $manifestMediaFamily,
            ];
            $largestPart = $summaries[$rawExtensionKey]['largestPart'];
            if (
                !is_array($largestPart)
                || $byteLength > (int) ($largestPart['byteLength'] ?? 0)
                || ($byteLength === (int) ($largestPart['byteLength'] ?? 0) && strcmp($path, (string) ($largestPart['path'] ?? '')) < 0)
            ) {
                $summaries[$rawExtensionKey]['largestPart'] = $partSummary;
            }
        }

        ksort($rawExtensionCounts, SORT_STRING);
        ksort($entryNamesByRawExtension, SORT_STRING);
        foreach ($entryNamesByRawExtension as $rawExtensionKey => $names) {
            sort($names, SORT_STRING);
            $entryNamesByRawExtension[$rawExtensionKey] = $names;
        }

        ksort($summaries, SORT_STRING);
        foreach ($summaries as $rawExtensionKey => $summary) {
            sort($summary['partNames'], SORT_STRING);
            ksort($summary['packagePartExtensionCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            $summaries[$rawExtensionKey] = $summary;
        }

        return [
            'packagePartRawExtensionCounts' => $rawExtensionCounts,
            'entryNamesByPackagePartRawExtension' => $entryNamesByRawExtension,
            'packagePartRawExtensionUppercasePartCount' => $uppercasePartCount,
            'packagePartRawExtensionNormalizedPartCount' => $normalizedPartCount,
            'packagePartRawExtensionSummaries' => array_values($summaries),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array{
     *     packageBasenameCounts:array<string, int>,
     *     entryNamesByPackageBasename:array<string, list<string>>,
     *     packageBasenameStemCounts:array<string, int>,
     *     packageCaseFoldedBasenameCounts:array<string, int>,
     *     entryNamesByPackageCaseFoldedBasename:array<string, list<string>>,
     *     duplicatePackageBasenameCount:int,
     *     duplicatePackageBasenameEntryCount:int,
     *     duplicatePackageBasenameSummaries:list<array<string, mixed>>,
     *     caseFoldedPackageBasenameDuplicateCount:int,
     *     caseFoldedPackageBasenameDuplicateEntryCount:int,
     *     caseFoldedPackageBasenameDuplicateSummaries:list<array<string, mixed>>
     * }
     */
    private static function packagePartBasenameInventory(array $parts): array
    {
        $basenameCounts = [];
        $entryNamesByBasename = [];
        $stemCounts = [];
        $caseFoldedBasenameCounts = [];
        $entryNamesByCaseFoldedBasename = [];
        $basenamesByCaseFoldedBasename = [];

        foreach ($parts as $name => $part) {
            $path = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
            $pathShape = is_array($part['pathShape'] ?? null) ? $part['pathShape'] : [];
            $basename = is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null;
            if ($basename === null || $basename === '') {
                continue;
            }

            $stem = self::packagePartBasenameStem($basename);
            $caseFoldKey = strtolower($basename);
            $basenameCounts[$basename] = ($basenameCounts[$basename] ?? 0) + 1;
            $entryNamesByBasename[$basename][] = $path;
            $stemCounts[$stem] = ($stemCounts[$stem] ?? 0) + 1;
            $caseFoldedBasenameCounts[$caseFoldKey] = ($caseFoldedBasenameCounts[$caseFoldKey] ?? 0) + 1;
            $entryNamesByCaseFoldedBasename[$caseFoldKey][] = $path;
            $basenamesByCaseFoldedBasename[$caseFoldKey][$basename] = true;
        }

        ksort($basenameCounts, SORT_STRING);
        ksort($entryNamesByBasename, SORT_STRING);
        foreach ($entryNamesByBasename as $basename => $names) {
            sort($names, SORT_STRING);
            $entryNamesByBasename[$basename] = $names;
        }
        ksort($stemCounts, SORT_STRING);
        ksort($caseFoldedBasenameCounts, SORT_STRING);
        ksort($entryNamesByCaseFoldedBasename, SORT_STRING);
        foreach ($entryNamesByCaseFoldedBasename as $caseFoldKey => $names) {
            sort($names, SORT_STRING);
            $entryNamesByCaseFoldedBasename[$caseFoldKey] = $names;
        }
        ksort($basenamesByCaseFoldedBasename, SORT_STRING);

        $duplicateSummaries = [];
        $duplicateEntryCount = 0;
        foreach ($entryNamesByBasename as $basename => $names) {
            if (count($names) < 2) {
                continue;
            }

            $duplicateEntryCount += count($names);
            $duplicateSummaries[] = [
                'packageBasename' => $basename,
                'entryCount' => count($names),
                'entryNames' => $names,
            ];
        }

        $caseFoldedDuplicateSummaries = [];
        $caseFoldedDuplicateEntryCount = 0;
        foreach ($entryNamesByCaseFoldedBasename as $caseFoldKey => $names) {
            if (count($names) < 2) {
                continue;
            }

            $basenames = array_keys($basenamesByCaseFoldedBasename[$caseFoldKey] ?? []);
            sort($basenames, SORT_STRING);
            $caseFoldedDuplicateEntryCount += count($names);
            $caseFoldedDuplicateSummaries[] = [
                'caseFoldKey' => $caseFoldKey,
                'entryCount' => count($names),
                'packageBasenames' => $basenames,
                'entryNames' => $names,
            ];
        }

        return [
            'packageBasenameCounts' => $basenameCounts,
            'entryNamesByPackageBasename' => $entryNamesByBasename,
            'packageBasenameStemCounts' => $stemCounts,
            'packageCaseFoldedBasenameCounts' => $caseFoldedBasenameCounts,
            'entryNamesByPackageCaseFoldedBasename' => $entryNamesByCaseFoldedBasename,
            'duplicatePackageBasenameCount' => count($duplicateSummaries),
            'duplicatePackageBasenameEntryCount' => $duplicateEntryCount,
            'duplicatePackageBasenameSummaries' => $duplicateSummaries,
            'caseFoldedPackageBasenameDuplicateCount' => count($caseFoldedDuplicateSummaries),
            'caseFoldedPackageBasenameDuplicateEntryCount' => $caseFoldedDuplicateEntryCount,
            'caseFoldedPackageBasenameDuplicateSummaries' => $caseFoldedDuplicateSummaries,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array{
     *     packageZipSourceRecordDirectoryRootCount:int,
     *     packageZipSourceRecordDirectoryRootCounts:array<string, int>,
     *     packageZipSourceRecordDirectoryRootBytes:array<string, int>,
     *     packageZipSourceRecordEntryCount:int,
     *     packageZipSourceRecordByteLength:int,
     *     packageZipSourceRecordLocalRecordByteLength:int,
     *     packageZipSourceRecordCentralDirectoryRecordByteLength:int,
     *     packageZipSourceRecordLocalHeaderReviewFieldByteLength:int,
     *     packageZipSourceRecordCentralDirectoryReviewFieldByteLength:int,
     *     packageZipSourceRecordReviewFieldByteLength:int,
     *     packageZipSourceRecordDataDescriptorEntryCount:int,
     *     packageZipSourceRecordDirectoryRoots:list<array<string, mixed>>
     * }
     */
    private static function packageZipSourceRecordDirectoryRootInventory(array $parts): array
    {
        $intField = static function (array $part, string $field): int {
            $value = $part[$field] ?? null;

            return is_int($value) ? $value : 0;
        };
        $roots = [];

        foreach ($parts as $name => $part) {
            if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
                continue;
            }

            $entryName = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
            $directoryRoot = is_string($part['zipPackageManifestDirectoryRoot'] ?? null)
                ? $part['zipPackageManifestDirectoryRoot']
                : self::packageDirectoryRoot($entryName);
            if ($directoryRoot === '') {
                $directoryRoot = '/';
            }

            if (!isset($roots[$directoryRoot])) {
                $roots[$directoryRoot] = [
                    'directoryRoot' => $directoryRoot,
                    'entryCount' => 0,
                    'sourceRecordBytes' => 0,
                    'localRecordBytes' => 0,
                    'localHeaderBytes' => 0,
                    'localHeaderFixedHeaderBytes' => 0,
                    'localHeaderVariableFieldBytes' => 0,
                    'localHeaderRawNameBytes' => 0,
                    'localHeaderExtraFieldBytes' => 0,
                    'localHeaderReviewFieldBytes' => 0,
                    'compressedDataBytes' => 0,
                    'dataDescriptorBytes' => 0,
                    'dataDescriptorEntryCount' => 0,
                    'centralDirectoryRecordBytes' => 0,
                    'centralDirectoryFixedHeaderBytes' => 0,
                    'centralDirectoryVariableFieldBytes' => 0,
                    'centralDirectoryRawNameBytes' => 0,
                    'centralDirectoryExtraFieldBytes' => 0,
                    'centralDirectoryRawCommentBytes' => 0,
                    'centralDirectoryReviewFieldBytes' => 0,
                    'exposableEntryCount' => 0,
                    'blockedEntryCount' => 0,
                    'compressionMethodCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'entryNames' => [],
                    'largestSourceRecordEntry' => null,
                ];
            }

            $sourceRecordBytes = $intField($part, 'zipSourceRecordBytes');
            $localRecordBytes = $intField($part, 'zipLocalRecordBytes');
            $localHeaderBytes = $intField($part, 'zipLocalHeaderBytes');
            $localHeaderFixedHeaderBytes = $intField($part, 'zipLocalHeaderFixedHeaderBytes');
            $localHeaderVariableFieldBytes = $intField($part, 'zipLocalHeaderVariableFieldBytes');
            $localHeaderRawNameBytes = $intField($part, 'zipLocalHeaderRawNameBytes');
            $localHeaderExtraFieldBytes = $intField($part, 'zipLocalHeaderExtraFieldBytes');
            $localHeaderReviewFieldBytes = $intField($part, 'zipLocalHeaderReviewFieldBytes');
            $compressedDataBytes = $intField($part, 'zipCompressedDataBytes');
            $dataDescriptorBytes = $intField($part, 'zipDataDescriptorBytes');
            $centralDirectoryRecordBytes = $intField($part, 'zipCentralDirectoryRecordBytes');
            $centralDirectoryFixedHeaderBytes = $intField($part, 'zipCentralDirectoryFixedHeaderBytes');
            $centralDirectoryVariableFieldBytes = $intField($part, 'zipCentralDirectoryVariableFieldBytes');
            $centralDirectoryRawNameBytes = $intField($part, 'zipCentralDirectoryRawNameBytes');
            $centralDirectoryExtraFieldBytes = $intField($part, 'zipCentralDirectoryExtraFieldBytes');
            $centralDirectoryRawCommentBytes = $intField($part, 'zipCentralDirectoryRawCommentBytes');
            $centralDirectoryReviewFieldBytes = $intField($part, 'zipCentralDirectoryReviewFieldBytes');
            $compressionMethod = is_int($part['compressionMethod'] ?? null) ? (string) $part['compressionMethod'] : '(missing)';
            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) && $part['byteExposurePolicy'] !== ''
                ? $part['byteExposurePolicy']
                : '(missing)';
            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) && $part['manifestMediaFamily'] !== ''
                ? $part['manifestMediaFamily']
                : '(missing)';
            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) && $part['manifestMediaTypeBase'] !== ''
                ? $part['manifestMediaTypeBase']
                : '(missing)';
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));

            ++$roots[$directoryRoot]['entryCount'];
            $roots[$directoryRoot]['sourceRecordBytes'] += $sourceRecordBytes;
            $roots[$directoryRoot]['localRecordBytes'] += $localRecordBytes;
            $roots[$directoryRoot]['localHeaderBytes'] += $localHeaderBytes;
            $roots[$directoryRoot]['localHeaderFixedHeaderBytes'] += $localHeaderFixedHeaderBytes;
            $roots[$directoryRoot]['localHeaderVariableFieldBytes'] += $localHeaderVariableFieldBytes;
            $roots[$directoryRoot]['localHeaderRawNameBytes'] += $localHeaderRawNameBytes;
            $roots[$directoryRoot]['localHeaderExtraFieldBytes'] += $localHeaderExtraFieldBytes;
            $roots[$directoryRoot]['localHeaderReviewFieldBytes'] += $localHeaderReviewFieldBytes;
            $roots[$directoryRoot]['compressedDataBytes'] += $compressedDataBytes;
            $roots[$directoryRoot]['dataDescriptorBytes'] += $dataDescriptorBytes;
            $roots[$directoryRoot]['centralDirectoryRecordBytes'] += $centralDirectoryRecordBytes;
            $roots[$directoryRoot]['centralDirectoryFixedHeaderBytes'] += $centralDirectoryFixedHeaderBytes;
            $roots[$directoryRoot]['centralDirectoryVariableFieldBytes'] += $centralDirectoryVariableFieldBytes;
            $roots[$directoryRoot]['centralDirectoryRawNameBytes'] += $centralDirectoryRawNameBytes;
            $roots[$directoryRoot]['centralDirectoryExtraFieldBytes'] += $centralDirectoryExtraFieldBytes;
            $roots[$directoryRoot]['centralDirectoryRawCommentBytes'] += $centralDirectoryRawCommentBytes;
            $roots[$directoryRoot]['centralDirectoryReviewFieldBytes'] += $centralDirectoryReviewFieldBytes;
            $roots[$directoryRoot]['entryNames'][] = $entryName;
            $roots[$directoryRoot]['compressionMethodCounts'][$compressionMethod] =
                ($roots[$directoryRoot]['compressionMethodCounts'][$compressionMethod] ?? 0) + 1;
            $roots[$directoryRoot]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($roots[$directoryRoot]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $roots[$directoryRoot]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($roots[$directoryRoot]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $roots[$directoryRoot]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($roots[$directoryRoot]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            if ($dataDescriptorBytes > 0 || ($part['zipUsesDataDescriptor'] ?? false) === true) {
                ++$roots[$directoryRoot]['dataDescriptorEntryCount'];
            }
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$roots[$directoryRoot]['exposableEntryCount'];
            } else {
                ++$roots[$directoryRoot]['blockedEntryCount'];
            }
            foreach ($roles as $role) {
                if ($role === '') {
                    continue;
                }
                $roots[$directoryRoot]['roleCounts'][$role] =
                    ($roots[$directoryRoot]['roleCounts'][$role] ?? 0) + 1;
            }

            $pathShape = is_array($part['pathShape'] ?? null) ? $part['pathShape'] : [];
            $entrySummary = [
                'entryName' => $entryName,
                'directoryRoot' => $directoryRoot,
                'packageDirectory' => is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null,
                'packageBasename' => is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null,
                'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
                'compressionMethod' => is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : null,
                'compressionMethodName' => is_string($part['compressionMethodName'] ?? null) ? $part['compressionMethodName'] : null,
                'byteLength' => $intField($part, 'byteLength'),
                'compressedByteLength' => $intField($part, 'compressedByteLength'),
                'sourceRecordBytes' => $sourceRecordBytes,
                'localRecordBytes' => $localRecordBytes,
                'localHeaderBytes' => $localHeaderBytes,
                'localHeaderFixedHeaderBytes' => $localHeaderFixedHeaderBytes,
                'localHeaderVariableFieldBytes' => $localHeaderVariableFieldBytes,
                'localHeaderRawNameBytes' => $localHeaderRawNameBytes,
                'localHeaderExtraFieldBytes' => $localHeaderExtraFieldBytes,
                'localHeaderReviewFieldBytes' => $localHeaderReviewFieldBytes,
                'compressedDataBytes' => $compressedDataBytes,
                'dataDescriptorBytes' => $dataDescriptorBytes,
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'centralDirectoryFixedHeaderBytes' => $centralDirectoryFixedHeaderBytes,
                'centralDirectoryVariableFieldBytes' => $centralDirectoryVariableFieldBytes,
                'centralDirectoryRawNameBytes' => $centralDirectoryRawNameBytes,
                'centralDirectoryExtraFieldBytes' => $centralDirectoryExtraFieldBytes,
                'centralDirectoryRawCommentBytes' => $centralDirectoryRawCommentBytes,
                'centralDirectoryReviewFieldBytes' => $centralDirectoryReviewFieldBytes,
                'roles' => $roles,
                'byteExposurePolicy' => $byteExposurePolicy === '(missing)' ? null : $byteExposurePolicy,
                'manifestMediaFamily' => $manifestMediaFamily === '(missing)' ? null : $manifestMediaFamily,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '(missing)' ? null : $manifestMediaTypeBase,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
            ];
            $largestEntry = $roots[$directoryRoot]['largestSourceRecordEntry'];
            if (
                !is_array($largestEntry)
                || $sourceRecordBytes > (int) ($largestEntry['sourceRecordBytes'] ?? 0)
                || ($sourceRecordBytes === (int) ($largestEntry['sourceRecordBytes'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
            ) {
                $roots[$directoryRoot]['largestSourceRecordEntry'] = $entrySummary;
            }
        }

        $directoryRootCounts = [];
        $directoryRootBytes = [];
        $entryCount = 0;
        $sourceRecordByteLength = 0;
        $localRecordByteLength = 0;
        $centralDirectoryRecordByteLength = 0;
        $localHeaderReviewFieldByteLength = 0;
        $centralDirectoryReviewFieldByteLength = 0;
        $dataDescriptorEntryCount = 0;
        ksort($roots, SORT_STRING);
        foreach ($roots as $directoryRoot => $summary) {
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['compressionMethodCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $roots[$directoryRoot] = $summary;
            $directoryRootCounts[$directoryRoot] = $summary['entryCount'];
            $directoryRootBytes[$directoryRoot] = $summary['sourceRecordBytes'];
            $entryCount += $summary['entryCount'];
            $sourceRecordByteLength += $summary['sourceRecordBytes'];
            $localRecordByteLength += $summary['localRecordBytes'];
            $centralDirectoryRecordByteLength += $summary['centralDirectoryRecordBytes'];
            $localHeaderReviewFieldByteLength += $summary['localHeaderReviewFieldBytes'];
            $centralDirectoryReviewFieldByteLength += $summary['centralDirectoryReviewFieldBytes'];
            $dataDescriptorEntryCount += $summary['dataDescriptorEntryCount'];
        }

        return [
            'packageZipSourceRecordDirectoryRootCount' => count($roots),
            'packageZipSourceRecordDirectoryRootCounts' => $directoryRootCounts,
            'packageZipSourceRecordDirectoryRootBytes' => $directoryRootBytes,
            'packageZipSourceRecordEntryCount' => $entryCount,
            'packageZipSourceRecordByteLength' => $sourceRecordByteLength,
            'packageZipSourceRecordLocalRecordByteLength' => $localRecordByteLength,
            'packageZipSourceRecordCentralDirectoryRecordByteLength' => $centralDirectoryRecordByteLength,
            'packageZipSourceRecordLocalHeaderReviewFieldByteLength' => $localHeaderReviewFieldByteLength,
            'packageZipSourceRecordCentralDirectoryReviewFieldByteLength' => $centralDirectoryReviewFieldByteLength,
            'packageZipSourceRecordReviewFieldByteLength' => $localHeaderReviewFieldByteLength + $centralDirectoryReviewFieldByteLength,
            'packageZipSourceRecordDataDescriptorEntryCount' => $dataDescriptorEntryCount,
            'packageZipSourceRecordDirectoryRoots' => array_values($roots),
        ];
    }

    /**
     * @param array<string, mixed> $part
     * @return list<string>
     */
    private static function zipSourceRecordIssueCodesForPart(array $part): array
    {
        $issues = [];
        foreach ([
            'zipLocalHeaderMetadataIssues',
            'zipGeneralPurposeFlagIssues',
            'zipTimestampIssues',
            'zipEntryCommentIssues',
            'zipNameHygieneIssueCodes',
            'zipPackageManifestCreatorHostSystemIssues',
            'zipPackageManifestCaseInsensitiveNameCollisionIssues',
            'creatorHostIssues',
            'platformMetadataIssues',
            'platformAttributeIssues',
        ] as $field) {
            $values = is_array($part[$field] ?? null) ? $part[$field] : [];
            foreach ($values as $value) {
                if (is_string($value) && $value !== '') {
                    $issues[$value] = true;
                }
            }
        }

        $issueCodes = array_keys($issues);
        sort($issueCodes, SORT_STRING);

        return $issueCodes;
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function packageZipSourceRecordPackagePartExtensionInventory(array $parts): array
    {
        $intField = static function (array $part, string $field): int {
            $value = $part[$field] ?? null;

            return is_int($value) ? $value : 0;
        };
        $extensions = [];

        foreach ($parts as $name => $part) {
            if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
                continue;
            }

            $entryName = is_string($part['path'] ?? null)
                ? $part['path']
                : (is_string($part['part'] ?? null) ? $part['part'] : (string) $name);
            $pathShape = is_array($part['pathShape'] ?? null)
                ? $part['pathShape']
                : (is_array($part['packagePathShape'] ?? null) ? $part['packagePathShape'] : []);
            $partExtension = is_string($part['zipPackageManifestPackagePartExtension'] ?? null)
                ? $part['zipPackageManifestPackagePartExtension']
                : (is_string($part['packagePartExtension'] ?? null) ? $part['packagePartExtension'] : null);
            $partExtensionKey = $partExtension ?? '(none)';

            if (!isset($extensions[$partExtensionKey])) {
                $extensions[$partExtensionKey] = [
                    'packagePartExtensionKey' => $partExtensionKey,
                    'packagePartExtension' => $partExtension,
                    'extensionlessPackagePart' => $partExtension === null,
                    'entryCount' => 0,
                    'sourceRecordBytes' => 0,
                    'localRecordBytes' => 0,
                    'localHeaderBytes' => 0,
                    'localHeaderFixedHeaderBytes' => 0,
                    'localHeaderVariableFieldBytes' => 0,
                    'localHeaderRawNameBytes' => 0,
                    'localHeaderExtraFieldBytes' => 0,
                    'localHeaderReviewFieldBytes' => 0,
                    'compressedDataBytes' => 0,
                    'dataDescriptorBytes' => 0,
                    'dataDescriptorEntryCount' => 0,
                    'centralDirectoryRecordBytes' => 0,
                    'centralDirectoryFixedHeaderBytes' => 0,
                    'centralDirectoryVariableFieldBytes' => 0,
                    'centralDirectoryRawNameBytes' => 0,
                    'centralDirectoryExtraFieldBytes' => 0,
                    'centralDirectoryRawCommentBytes' => 0,
                    'centralDirectoryReviewFieldBytes' => 0,
                    'sourceRecordIssueEntryCount' => 0,
                    'sourceRecordIssueCount' => 0,
                    'exposableEntryCount' => 0,
                    'blockedEntryCount' => 0,
                    'directoryRootCounts' => [],
                    'compressionMethodCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'entryNames' => [],
                    'largestSourceRecordEntry' => null,
                ];
            }

            $directoryRoot = is_string($part['zipPackageManifestDirectoryRoot'] ?? null)
                ? $part['zipPackageManifestDirectoryRoot']
                : self::packageDirectoryRoot($entryName);
            if ($directoryRoot === '') {
                $directoryRoot = '/';
            }
            $sourceRecordBytes = $intField($part, 'zipSourceRecordBytes');
            $localRecordBytes = $intField($part, 'zipLocalRecordBytes');
            $localHeaderBytes = $intField($part, 'zipLocalHeaderBytes');
            $localHeaderFixedHeaderBytes = $intField($part, 'zipLocalHeaderFixedHeaderBytes');
            $localHeaderVariableFieldBytes = $intField($part, 'zipLocalHeaderVariableFieldBytes');
            $localHeaderRawNameBytes = $intField($part, 'zipLocalHeaderRawNameBytes');
            $localHeaderExtraFieldBytes = $intField($part, 'zipLocalHeaderExtraFieldBytes');
            $localHeaderReviewFieldBytes = $intField($part, 'zipLocalHeaderReviewFieldBytes');
            $compressedDataBytes = $intField($part, 'zipCompressedDataBytes');
            $dataDescriptorBytes = $intField($part, 'zipDataDescriptorBytes');
            $centralDirectoryRecordBytes = $intField($part, 'zipCentralDirectoryRecordBytes');
            $centralDirectoryFixedHeaderBytes = $intField($part, 'zipCentralDirectoryFixedHeaderBytes');
            $centralDirectoryVariableFieldBytes = $intField($part, 'zipCentralDirectoryVariableFieldBytes');
            $centralDirectoryRawNameBytes = $intField($part, 'zipCentralDirectoryRawNameBytes');
            $centralDirectoryExtraFieldBytes = $intField($part, 'zipCentralDirectoryExtraFieldBytes');
            $centralDirectoryRawCommentBytes = $intField($part, 'zipCentralDirectoryRawCommentBytes');
            $centralDirectoryReviewFieldBytes = $intField($part, 'zipCentralDirectoryReviewFieldBytes');
            $sourceRecordIssues = self::zipSourceRecordIssueCodesForPart($part);
            $compressionMethod = is_int($part['compressionMethod'] ?? null) ? (string) $part['compressionMethod'] : '(missing)';
            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) && $part['byteExposurePolicy'] !== ''
                ? $part['byteExposurePolicy']
                : '(missing)';
            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) && $part['manifestMediaFamily'] !== ''
                ? $part['manifestMediaFamily']
                : '(missing)';
            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) && $part['manifestMediaTypeBase'] !== ''
                ? $part['manifestMediaTypeBase']
                : '(missing)';
            $roles = array_values(array_unique(array_filter(
                array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []),
                static fn (string $role): bool => $role !== ''
            )));

            ++$extensions[$partExtensionKey]['entryCount'];
            $extensions[$partExtensionKey]['sourceRecordBytes'] += $sourceRecordBytes;
            $extensions[$partExtensionKey]['localRecordBytes'] += $localRecordBytes;
            $extensions[$partExtensionKey]['localHeaderBytes'] += $localHeaderBytes;
            $extensions[$partExtensionKey]['localHeaderFixedHeaderBytes'] += $localHeaderFixedHeaderBytes;
            $extensions[$partExtensionKey]['localHeaderVariableFieldBytes'] += $localHeaderVariableFieldBytes;
            $extensions[$partExtensionKey]['localHeaderRawNameBytes'] += $localHeaderRawNameBytes;
            $extensions[$partExtensionKey]['localHeaderExtraFieldBytes'] += $localHeaderExtraFieldBytes;
            $extensions[$partExtensionKey]['localHeaderReviewFieldBytes'] += $localHeaderReviewFieldBytes;
            $extensions[$partExtensionKey]['compressedDataBytes'] += $compressedDataBytes;
            $extensions[$partExtensionKey]['dataDescriptorBytes'] += $dataDescriptorBytes;
            $extensions[$partExtensionKey]['centralDirectoryRecordBytes'] += $centralDirectoryRecordBytes;
            $extensions[$partExtensionKey]['centralDirectoryFixedHeaderBytes'] += $centralDirectoryFixedHeaderBytes;
            $extensions[$partExtensionKey]['centralDirectoryVariableFieldBytes'] += $centralDirectoryVariableFieldBytes;
            $extensions[$partExtensionKey]['centralDirectoryRawNameBytes'] += $centralDirectoryRawNameBytes;
            $extensions[$partExtensionKey]['centralDirectoryExtraFieldBytes'] += $centralDirectoryExtraFieldBytes;
            $extensions[$partExtensionKey]['centralDirectoryRawCommentBytes'] += $centralDirectoryRawCommentBytes;
            $extensions[$partExtensionKey]['centralDirectoryReviewFieldBytes'] += $centralDirectoryReviewFieldBytes;
            $extensions[$partExtensionKey]['sourceRecordIssueCount'] += count($sourceRecordIssues);
            $extensions[$partExtensionKey]['entryNames'][] = $entryName;
            $extensions[$partExtensionKey]['directoryRootCounts'][$directoryRoot] =
                ($extensions[$partExtensionKey]['directoryRootCounts'][$directoryRoot] ?? 0) + 1;
            $extensions[$partExtensionKey]['compressionMethodCounts'][$compressionMethod] =
                ($extensions[$partExtensionKey]['compressionMethodCounts'][$compressionMethod] ?? 0) + 1;
            $extensions[$partExtensionKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($extensions[$partExtensionKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $extensions[$partExtensionKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($extensions[$partExtensionKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $extensions[$partExtensionKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($extensions[$partExtensionKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            if ($dataDescriptorBytes > 0 || ($part['zipUsesDataDescriptor'] ?? false) === true) {
                ++$extensions[$partExtensionKey]['dataDescriptorEntryCount'];
            }
            if ($sourceRecordIssues !== []) {
                ++$extensions[$partExtensionKey]['sourceRecordIssueEntryCount'];
            }
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$extensions[$partExtensionKey]['exposableEntryCount'];
            } else {
                ++$extensions[$partExtensionKey]['blockedEntryCount'];
            }
            foreach ($roles as $role) {
                $extensions[$partExtensionKey]['roleCounts'][$role] =
                    ($extensions[$partExtensionKey]['roleCounts'][$role] ?? 0) + 1;
            }

            $entrySummary = [
                'entryName' => $entryName,
                'packagePartExtensionKey' => $partExtensionKey,
                'packagePartExtension' => $partExtension,
                'extensionlessPackagePart' => $partExtension === null,
                'directoryRoot' => $directoryRoot,
                'packageDirectory' => is_string($part['packageDirectory'] ?? null)
                    ? $part['packageDirectory']
                    : (is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null),
                'packageBasename' => is_string($part['packageBasename'] ?? null)
                    ? $part['packageBasename']
                    : (is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null),
                'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
                'byteLength' => $intField($part, 'byteLength'),
                'compressedByteLength' => $intField($part, 'compressedByteLength'),
                'compressionMethod' => is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : null,
                'compressionMethodName' => is_string($part['compressionMethodName'] ?? null) ? $part['compressionMethodName'] : null,
                'sourceRecordBytes' => $sourceRecordBytes,
                'localRecordBytes' => $localRecordBytes,
                'localHeaderBytes' => $localHeaderBytes,
                'localHeaderFixedHeaderBytes' => $localHeaderFixedHeaderBytes,
                'localHeaderVariableFieldBytes' => $localHeaderVariableFieldBytes,
                'localHeaderRawNameBytes' => $localHeaderRawNameBytes,
                'localHeaderExtraFieldBytes' => $localHeaderExtraFieldBytes,
                'localHeaderReviewFieldBytes' => $localHeaderReviewFieldBytes,
                'compressedDataBytes' => $compressedDataBytes,
                'dataDescriptorBytes' => $dataDescriptorBytes,
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'centralDirectoryFixedHeaderBytes' => $centralDirectoryFixedHeaderBytes,
                'centralDirectoryVariableFieldBytes' => $centralDirectoryVariableFieldBytes,
                'centralDirectoryRawNameBytes' => $centralDirectoryRawNameBytes,
                'centralDirectoryExtraFieldBytes' => $centralDirectoryExtraFieldBytes,
                'centralDirectoryRawCommentBytes' => $centralDirectoryRawCommentBytes,
                'centralDirectoryReviewFieldBytes' => $centralDirectoryReviewFieldBytes,
                'sourceRecordIssueCount' => count($sourceRecordIssues),
                'sourceRecordIssues' => $sourceRecordIssues,
                'roles' => $roles,
                'byteExposurePolicy' => $byteExposurePolicy === '(missing)' ? null : $byteExposurePolicy,
                'manifestMediaFamily' => $manifestMediaFamily === '(missing)' ? null : $manifestMediaFamily,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '(missing)' ? null : $manifestMediaTypeBase,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
            ];
            $largestEntry = $extensions[$partExtensionKey]['largestSourceRecordEntry'];
            if (
                !is_array($largestEntry)
                || $sourceRecordBytes > (int) ($largestEntry['sourceRecordBytes'] ?? 0)
                || ($sourceRecordBytes === (int) ($largestEntry['sourceRecordBytes'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
            ) {
                $extensions[$partExtensionKey]['largestSourceRecordEntry'] = $entrySummary;
            }
        }

        $extensionCounts = [];
        $extensionBytes = [];
        $extensionlessPackagePartCount = 0;
        $dataDescriptorEntryCount = 0;
        $issueEntryCount = 0;
        ksort($extensions, SORT_STRING);
        foreach ($extensions as $extensionKey => $summary) {
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['directoryRootCounts'], SORT_STRING);
            ksort($summary['compressionMethodCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $extensions[$extensionKey] = $summary;
            $extensionCounts[$extensionKey] = $summary['entryCount'];
            $extensionBytes[$extensionKey] = $summary['sourceRecordBytes'];
            if ($summary['packagePartExtension'] === null) {
                $extensionlessPackagePartCount += $summary['entryCount'];
            }
            $dataDescriptorEntryCount += $summary['dataDescriptorEntryCount'];
            $issueEntryCount += $summary['sourceRecordIssueEntryCount'];
        }

        return [
            'packageZipSourceRecordPackagePartExtensionCount' => count($extensions),
            'packageZipSourceRecordPackagePartExtensionCounts' => $extensionCounts,
            'packageZipSourceRecordPackagePartExtensionBytes' => $extensionBytes,
            'packageZipSourceRecordExtensionlessPackagePartCount' => $extensionlessPackagePartCount,
            'packageZipSourceRecordPackagePartExtensionDataDescriptorEntryCount' => $dataDescriptorEntryCount,
            'packageZipSourceRecordPackagePartExtensionIssueEntryCount' => $issueEntryCount,
            'packageZipSourceRecordPackagePartExtensions' => array_values($extensions),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function packageZipSourceRecordPackagePartBaseNameStemInventory(array $parts): array
    {
        $intField = static function (array $part, string $field): int {
            $value = $part[$field] ?? null;

            return is_int($value) ? $value : 0;
        };
        $baseNameStems = [];

        foreach ($parts as $name => $part) {
            if (($part['zipHasSourceRecordProvenance'] ?? false) !== true || ($part['isDirectory'] ?? false) === true) {
                continue;
            }

            $entryName = is_string($part['path'] ?? null)
                ? $part['path']
                : (is_string($part['part'] ?? null) ? $part['part'] : (string) $name);
            $pathShape = is_array($part['pathShape'] ?? null)
                ? $part['pathShape']
                : (is_array($part['packagePathShape'] ?? null) ? $part['packagePathShape'] : []);
            $baseName = is_string($part['zipPackageManifestPackagePartBaseName'] ?? null)
                ? $part['zipPackageManifestPackagePartBaseName']
                : (is_string($part['packageBasename'] ?? null)
                    ? $part['packageBasename']
                    : (is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : ''));
            $baseNameStem = is_string($part['zipPackageManifestPackagePartBaseNameStem'] ?? null)
                ? $part['zipPackageManifestPackagePartBaseNameStem']
                : ($baseName !== '' ? self::packagePartBasenameStem($baseName) : null);
            if (!is_string($baseNameStem) || $baseNameStem === '') {
                continue;
            }

            if (!isset($baseNameStems[$baseNameStem])) {
                $baseNameStems[$baseNameStem] = [
                    'baseNameStem' => $baseNameStem,
                    'entryCount' => 0,
                    'baseNameVariantCount' => 0,
                    'extensionVariantCount' => 0,
                    'extensionlessPackagePartCount' => 0,
                    'sourceRecordBytes' => 0,
                    'localRecordBytes' => 0,
                    'localHeaderBytes' => 0,
                    'localHeaderFixedHeaderBytes' => 0,
                    'localHeaderVariableFieldBytes' => 0,
                    'localHeaderRawNameBytes' => 0,
                    'localHeaderExtraFieldBytes' => 0,
                    'localHeaderReviewFieldBytes' => 0,
                    'compressedDataBytes' => 0,
                    'dataDescriptorBytes' => 0,
                    'dataDescriptorEntryCount' => 0,
                    'centralDirectoryRecordBytes' => 0,
                    'centralDirectoryFixedHeaderBytes' => 0,
                    'centralDirectoryVariableFieldBytes' => 0,
                    'centralDirectoryRawNameBytes' => 0,
                    'centralDirectoryExtraFieldBytes' => 0,
                    'centralDirectoryRawCommentBytes' => 0,
                    'centralDirectoryReviewFieldBytes' => 0,
                    'sourceRecordIssueEntryCount' => 0,
                    'sourceRecordIssueCount' => 0,
                    'exposableEntryCount' => 0,
                    'blockedEntryCount' => 0,
                    'baseNameCounts' => [],
                    'partExtensionCounts' => [],
                    'directoryRootCounts' => [],
                    'compressionMethodCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'entryNames' => [],
                    'largestSourceRecordEntry' => null,
                ];
            }

            $partExtension = is_string($part['zipPackageManifestPackagePartExtension'] ?? null)
                ? $part['zipPackageManifestPackagePartExtension']
                : (is_string($part['packagePartExtension'] ?? null) ? $part['packagePartExtension'] : null);
            $partExtensionKey = $partExtension ?? '(none)';
            $directoryRoot = is_string($part['zipPackageManifestDirectoryRoot'] ?? null)
                ? $part['zipPackageManifestDirectoryRoot']
                : self::packageDirectoryRoot($entryName);
            if ($directoryRoot === '') {
                $directoryRoot = '/';
            }

            $sourceRecordBytes = $intField($part, 'zipSourceRecordBytes');
            $localRecordBytes = $intField($part, 'zipLocalRecordBytes');
            $localHeaderBytes = $intField($part, 'zipLocalHeaderBytes');
            $localHeaderFixedHeaderBytes = $intField($part, 'zipLocalHeaderFixedHeaderBytes');
            $localHeaderVariableFieldBytes = $intField($part, 'zipLocalHeaderVariableFieldBytes');
            $localHeaderRawNameBytes = $intField($part, 'zipLocalHeaderRawNameBytes');
            $localHeaderExtraFieldBytes = $intField($part, 'zipLocalHeaderExtraFieldBytes');
            $localHeaderReviewFieldBytes = $intField($part, 'zipLocalHeaderReviewFieldBytes');
            $compressedDataBytes = $intField($part, 'zipCompressedDataBytes');
            $dataDescriptorBytes = $intField($part, 'zipDataDescriptorBytes');
            $centralDirectoryRecordBytes = $intField($part, 'zipCentralDirectoryRecordBytes');
            $centralDirectoryFixedHeaderBytes = $intField($part, 'zipCentralDirectoryFixedHeaderBytes');
            $centralDirectoryVariableFieldBytes = $intField($part, 'zipCentralDirectoryVariableFieldBytes');
            $centralDirectoryRawNameBytes = $intField($part, 'zipCentralDirectoryRawNameBytes');
            $centralDirectoryExtraFieldBytes = $intField($part, 'zipCentralDirectoryExtraFieldBytes');
            $centralDirectoryRawCommentBytes = $intField($part, 'zipCentralDirectoryRawCommentBytes');
            $centralDirectoryReviewFieldBytes = $intField($part, 'zipCentralDirectoryReviewFieldBytes');
            $sourceRecordIssues = self::zipSourceRecordIssueCodesForPart($part);
            $compressionMethod = is_int($part['compressionMethod'] ?? null) ? (string) $part['compressionMethod'] : '(missing)';
            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) && $part['byteExposurePolicy'] !== ''
                ? $part['byteExposurePolicy']
                : '(missing)';
            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) && $part['manifestMediaFamily'] !== ''
                ? $part['manifestMediaFamily']
                : '(missing)';
            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) && $part['manifestMediaTypeBase'] !== ''
                ? $part['manifestMediaTypeBase']
                : '(missing)';
            $roles = array_values(array_unique(array_filter(
                array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []),
                static fn (string $role): bool => $role !== ''
            )));

            ++$baseNameStems[$baseNameStem]['entryCount'];
            $baseNameStems[$baseNameStem]['sourceRecordBytes'] += $sourceRecordBytes;
            $baseNameStems[$baseNameStem]['localRecordBytes'] += $localRecordBytes;
            $baseNameStems[$baseNameStem]['localHeaderBytes'] += $localHeaderBytes;
            $baseNameStems[$baseNameStem]['localHeaderFixedHeaderBytes'] += $localHeaderFixedHeaderBytes;
            $baseNameStems[$baseNameStem]['localHeaderVariableFieldBytes'] += $localHeaderVariableFieldBytes;
            $baseNameStems[$baseNameStem]['localHeaderRawNameBytes'] += $localHeaderRawNameBytes;
            $baseNameStems[$baseNameStem]['localHeaderExtraFieldBytes'] += $localHeaderExtraFieldBytes;
            $baseNameStems[$baseNameStem]['localHeaderReviewFieldBytes'] += $localHeaderReviewFieldBytes;
            $baseNameStems[$baseNameStem]['compressedDataBytes'] += $compressedDataBytes;
            $baseNameStems[$baseNameStem]['dataDescriptorBytes'] += $dataDescriptorBytes;
            $baseNameStems[$baseNameStem]['centralDirectoryRecordBytes'] += $centralDirectoryRecordBytes;
            $baseNameStems[$baseNameStem]['centralDirectoryFixedHeaderBytes'] += $centralDirectoryFixedHeaderBytes;
            $baseNameStems[$baseNameStem]['centralDirectoryVariableFieldBytes'] += $centralDirectoryVariableFieldBytes;
            $baseNameStems[$baseNameStem]['centralDirectoryRawNameBytes'] += $centralDirectoryRawNameBytes;
            $baseNameStems[$baseNameStem]['centralDirectoryExtraFieldBytes'] += $centralDirectoryExtraFieldBytes;
            $baseNameStems[$baseNameStem]['centralDirectoryRawCommentBytes'] += $centralDirectoryRawCommentBytes;
            $baseNameStems[$baseNameStem]['centralDirectoryReviewFieldBytes'] += $centralDirectoryReviewFieldBytes;
            $baseNameStems[$baseNameStem]['sourceRecordIssueCount'] += count($sourceRecordIssues);
            $baseNameStems[$baseNameStem]['entryNames'][] = $entryName;
            $baseNameStems[$baseNameStem]['baseNameCounts'][$baseName] =
                ($baseNameStems[$baseNameStem]['baseNameCounts'][$baseName] ?? 0) + 1;
            $baseNameStems[$baseNameStem]['partExtensionCounts'][$partExtensionKey] =
                ($baseNameStems[$baseNameStem]['partExtensionCounts'][$partExtensionKey] ?? 0) + 1;
            $baseNameStems[$baseNameStem]['directoryRootCounts'][$directoryRoot] =
                ($baseNameStems[$baseNameStem]['directoryRootCounts'][$directoryRoot] ?? 0) + 1;
            $baseNameStems[$baseNameStem]['compressionMethodCounts'][$compressionMethod] =
                ($baseNameStems[$baseNameStem]['compressionMethodCounts'][$compressionMethod] ?? 0) + 1;
            $baseNameStems[$baseNameStem]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($baseNameStems[$baseNameStem]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $baseNameStems[$baseNameStem]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($baseNameStems[$baseNameStem]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $baseNameStems[$baseNameStem]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($baseNameStems[$baseNameStem]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            if ($partExtension === null) {
                ++$baseNameStems[$baseNameStem]['extensionlessPackagePartCount'];
            }
            if ($dataDescriptorBytes > 0 || ($part['zipUsesDataDescriptor'] ?? false) === true) {
                ++$baseNameStems[$baseNameStem]['dataDescriptorEntryCount'];
            }
            if ($sourceRecordIssues !== []) {
                ++$baseNameStems[$baseNameStem]['sourceRecordIssueEntryCount'];
            }
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$baseNameStems[$baseNameStem]['exposableEntryCount'];
            } else {
                ++$baseNameStems[$baseNameStem]['blockedEntryCount'];
            }
            foreach ($roles as $role) {
                $baseNameStems[$baseNameStem]['roleCounts'][$role] =
                    ($baseNameStems[$baseNameStem]['roleCounts'][$role] ?? 0) + 1;
            }

            $entrySummary = [
                'entryName' => $entryName,
                'baseNameStem' => $baseNameStem,
                'packageBasename' => $baseName,
                'packagePartExtension' => $partExtension,
                'extensionlessPackagePart' => $partExtension === null,
                'directoryRoot' => $directoryRoot,
                'packageDirectory' => is_string($part['packageDirectory'] ?? null)
                    ? $part['packageDirectory']
                    : (is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null),
                'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
                'byteLength' => $intField($part, 'byteLength'),
                'compressedByteLength' => $intField($part, 'compressedByteLength'),
                'compressionMethod' => is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : null,
                'compressionMethodName' => is_string($part['compressionMethodName'] ?? null) ? $part['compressionMethodName'] : null,
                'sourceRecordBytes' => $sourceRecordBytes,
                'localRecordBytes' => $localRecordBytes,
                'localHeaderBytes' => $localHeaderBytes,
                'localHeaderFixedHeaderBytes' => $localHeaderFixedHeaderBytes,
                'localHeaderVariableFieldBytes' => $localHeaderVariableFieldBytes,
                'localHeaderRawNameBytes' => $localHeaderRawNameBytes,
                'localHeaderExtraFieldBytes' => $localHeaderExtraFieldBytes,
                'localHeaderReviewFieldBytes' => $localHeaderReviewFieldBytes,
                'compressedDataBytes' => $compressedDataBytes,
                'dataDescriptorBytes' => $dataDescriptorBytes,
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'centralDirectoryFixedHeaderBytes' => $centralDirectoryFixedHeaderBytes,
                'centralDirectoryVariableFieldBytes' => $centralDirectoryVariableFieldBytes,
                'centralDirectoryRawNameBytes' => $centralDirectoryRawNameBytes,
                'centralDirectoryExtraFieldBytes' => $centralDirectoryExtraFieldBytes,
                'centralDirectoryRawCommentBytes' => $centralDirectoryRawCommentBytes,
                'centralDirectoryReviewFieldBytes' => $centralDirectoryReviewFieldBytes,
                'sourceRecordIssueCount' => count($sourceRecordIssues),
                'sourceRecordIssues' => $sourceRecordIssues,
                'roles' => $roles,
                'byteExposurePolicy' => $byteExposurePolicy === '(missing)' ? null : $byteExposurePolicy,
                'manifestMediaFamily' => $manifestMediaFamily === '(missing)' ? null : $manifestMediaFamily,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '(missing)' ? null : $manifestMediaTypeBase,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
            ];
            $largestEntry = $baseNameStems[$baseNameStem]['largestSourceRecordEntry'];
            if (
                !is_array($largestEntry)
                || $sourceRecordBytes > (int) ($largestEntry['sourceRecordBytes'] ?? 0)
                || ($sourceRecordBytes === (int) ($largestEntry['sourceRecordBytes'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
            ) {
                $baseNameStems[$baseNameStem]['largestSourceRecordEntry'] = $entrySummary;
            }
        }

        $baseNameStemCounts = [];
        $baseNameStemBytes = [];
        $dataDescriptorEntryCount = 0;
        $issueEntryCount = 0;
        $duplicateBaseNameStemCount = 0;
        $duplicateBaseNameStemEntryCount = 0;
        $duplicateBaseNameStems = [];
        ksort($baseNameStems, SORT_STRING);
        foreach ($baseNameStems as $baseNameStem => $summary) {
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['baseNameCounts'], SORT_STRING);
            ksort($summary['partExtensionCounts'], SORT_STRING);
            ksort($summary['directoryRootCounts'], SORT_STRING);
            ksort($summary['compressionMethodCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $summary['baseNameVariantCount'] = count($summary['baseNameCounts']);
            $summary['extensionVariantCount'] = count($summary['partExtensionCounts']);
            $baseNameStems[$baseNameStem] = $summary;
            $baseNameStemCounts[$baseNameStem] = $summary['entryCount'];
            $baseNameStemBytes[$baseNameStem] = $summary['sourceRecordBytes'];
            $dataDescriptorEntryCount += $summary['dataDescriptorEntryCount'];
            $issueEntryCount += $summary['sourceRecordIssueEntryCount'];
            if ($summary['entryCount'] > 1) {
                ++$duplicateBaseNameStemCount;
                $duplicateBaseNameStemEntryCount += $summary['entryCount'];
                $duplicateBaseNameStems[] = $baseNameStem;
            }
        }

        return [
            'packageZipSourceRecordPackagePartBaseNameStemCount' => count($baseNameStems),
            'packageZipSourceRecordPackagePartBaseNameStemCounts' => $baseNameStemCounts,
            'packageZipSourceRecordPackagePartBaseNameStemBytes' => $baseNameStemBytes,
            'packageZipSourceRecordPackagePartBaseNameStemDataDescriptorEntryCount' => $dataDescriptorEntryCount,
            'packageZipSourceRecordPackagePartBaseNameStemIssueEntryCount' => $issueEntryCount,
            'packageZipSourceRecordDuplicatePackagePartBaseNameStemCount' => $duplicateBaseNameStemCount,
            'packageZipSourceRecordDuplicatePackagePartBaseNameStemEntryCount' => $duplicateBaseNameStemEntryCount,
            'packageZipSourceRecordDuplicatePackagePartBaseNameStems' => $duplicateBaseNameStems,
            'packageZipSourceRecordPackagePartBaseNameStems' => array_values($baseNameStems),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function packageZipSourceRecordRoleInventory(array $parts): array
    {
        $intField = static function (array $part, string $field): int {
            $value = $part[$field] ?? null;

            return is_int($value) ? $value : 0;
        };
        $roles = [];

        foreach ($parts as $name => $part) {
            if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
                continue;
            }

            $entryName = is_string($part['path'] ?? null)
                ? $part['path']
                : (is_string($part['part'] ?? null) ? $part['part'] : (string) $name);
            $partRoles = array_values(array_unique(array_filter(
                array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []),
                static fn (string $role): bool => $role !== ''
            )));
            if ($partRoles === []) {
                $partRoles = ['package-part'];
            }

            $pathShape = is_array($part['pathShape'] ?? null) ? $part['pathShape'] : [];
            $directoryRoot = is_string($part['zipPackageManifestDirectoryRoot'] ?? null)
                ? $part['zipPackageManifestDirectoryRoot']
                : self::packageDirectoryRoot($entryName);
            if ($directoryRoot === '') {
                $directoryRoot = '/';
            }

            $sourceRecordBytes = $intField($part, 'zipSourceRecordBytes');
            $localRecordBytes = $intField($part, 'zipLocalRecordBytes');
            $localHeaderBytes = $intField($part, 'zipLocalHeaderBytes');
            $localHeaderFixedHeaderBytes = $intField($part, 'zipLocalHeaderFixedHeaderBytes');
            $localHeaderVariableFieldBytes = $intField($part, 'zipLocalHeaderVariableFieldBytes');
            $localHeaderRawNameBytes = $intField($part, 'zipLocalHeaderRawNameBytes');
            $localHeaderExtraFieldBytes = $intField($part, 'zipLocalHeaderExtraFieldBytes');
            $localHeaderReviewFieldBytes = $intField($part, 'zipLocalHeaderReviewFieldBytes');
            $compressedDataBytes = $intField($part, 'zipCompressedDataBytes');
            $dataDescriptorBytes = $intField($part, 'zipDataDescriptorBytes');
            $centralDirectoryRecordBytes = $intField($part, 'zipCentralDirectoryRecordBytes');
            $centralDirectoryFixedHeaderBytes = $intField($part, 'zipCentralDirectoryFixedHeaderBytes');
            $centralDirectoryVariableFieldBytes = $intField($part, 'zipCentralDirectoryVariableFieldBytes');
            $centralDirectoryRawNameBytes = $intField($part, 'zipCentralDirectoryRawNameBytes');
            $centralDirectoryExtraFieldBytes = $intField($part, 'zipCentralDirectoryExtraFieldBytes');
            $centralDirectoryRawCommentBytes = $intField($part, 'zipCentralDirectoryRawCommentBytes');
            $centralDirectoryReviewFieldBytes = $intField($part, 'zipCentralDirectoryReviewFieldBytes');
            $sourceRecordIssues = self::zipSourceRecordIssueCodesForPart($part);
            $sourceRecordIssueCount = count($sourceRecordIssues);
            $compressionMethod = is_int($part['compressionMethod'] ?? null) ? (string) $part['compressionMethod'] : '(missing)';
            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) && $part['byteExposurePolicy'] !== ''
                ? $part['byteExposurePolicy']
                : '(missing)';
            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) && $part['manifestMediaFamily'] !== ''
                ? $part['manifestMediaFamily']
                : '(missing)';
            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) && $part['manifestMediaTypeBase'] !== ''
                ? $part['manifestMediaTypeBase']
                : '(missing)';

            $entrySummary = [
                'entryName' => $entryName,
                'directoryRoot' => $directoryRoot,
                'packageDirectory' => is_string($part['packageDirectory'] ?? null)
                    ? $part['packageDirectory']
                    : (is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null),
                'packageBasename' => is_string($part['packageBasename'] ?? null)
                    ? $part['packageBasename']
                    : (is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null),
                'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
                'isDirectory' => ($part['isDirectory'] ?? false) === true,
                'byteLength' => $intField($part, 'byteLength'),
                'compressedByteLength' => $intField($part, 'compressedByteLength'),
                'compressionMethod' => is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : null,
                'compressionMethodName' => is_string($part['compressionMethodName'] ?? null) ? $part['compressionMethodName'] : null,
                'sourceRecordBytes' => $sourceRecordBytes,
                'localRecordBytes' => $localRecordBytes,
                'localHeaderBytes' => $localHeaderBytes,
                'localHeaderFixedHeaderBytes' => $localHeaderFixedHeaderBytes,
                'localHeaderVariableFieldBytes' => $localHeaderVariableFieldBytes,
                'localHeaderRawNameBytes' => $localHeaderRawNameBytes,
                'localHeaderExtraFieldBytes' => $localHeaderExtraFieldBytes,
                'localHeaderReviewFieldBytes' => $localHeaderReviewFieldBytes,
                'compressedDataBytes' => $compressedDataBytes,
                'dataDescriptorBytes' => $dataDescriptorBytes,
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'centralDirectoryFixedHeaderBytes' => $centralDirectoryFixedHeaderBytes,
                'centralDirectoryVariableFieldBytes' => $centralDirectoryVariableFieldBytes,
                'centralDirectoryRawNameBytes' => $centralDirectoryRawNameBytes,
                'centralDirectoryExtraFieldBytes' => $centralDirectoryExtraFieldBytes,
                'centralDirectoryRawCommentBytes' => $centralDirectoryRawCommentBytes,
                'centralDirectoryReviewFieldBytes' => $centralDirectoryReviewFieldBytes,
                'sourceRecordIssueCount' => $sourceRecordIssueCount,
                'sourceRecordIssues' => $sourceRecordIssues,
                'roles' => $partRoles,
                'byteExposurePolicy' => $byteExposurePolicy === '(missing)' ? null : $byteExposurePolicy,
                'manifestMediaFamily' => $manifestMediaFamily === '(missing)' ? null : $manifestMediaFamily,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '(missing)' ? null : $manifestMediaTypeBase,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
            ];

            foreach ($partRoles as $role) {
                if (!isset($roles[$role])) {
                    $roles[$role] = [
                        'role' => $role,
                        'entryCount' => 0,
                        'sourceRecordBytes' => 0,
                        'localRecordBytes' => 0,
                        'localHeaderBytes' => 0,
                        'localHeaderFixedHeaderBytes' => 0,
                        'localHeaderVariableFieldBytes' => 0,
                        'localHeaderRawNameBytes' => 0,
                        'localHeaderExtraFieldBytes' => 0,
                        'localHeaderReviewFieldBytes' => 0,
                        'compressedDataBytes' => 0,
                        'dataDescriptorBytes' => 0,
                        'dataDescriptorEntryCount' => 0,
                        'centralDirectoryRecordBytes' => 0,
                        'centralDirectoryFixedHeaderBytes' => 0,
                        'centralDirectoryVariableFieldBytes' => 0,
                        'centralDirectoryRawNameBytes' => 0,
                        'centralDirectoryExtraFieldBytes' => 0,
                        'centralDirectoryRawCommentBytes' => 0,
                        'centralDirectoryReviewFieldBytes' => 0,
                        'sourceRecordIssueEntryCount' => 0,
                        'sourceRecordIssueCount' => 0,
                        'directoryRootCounts' => [],
                        'compressionMethodCounts' => [],
                        'byteExposurePolicyCounts' => [],
                        'manifestMediaFamilyCounts' => [],
                        'manifestMediaTypeBaseCounts' => [],
                        'entryNames' => [],
                        'largestSourceRecordEntry' => null,
                    ];
                }

                ++$roles[$role]['entryCount'];
                $roles[$role]['sourceRecordBytes'] += $sourceRecordBytes;
                $roles[$role]['localRecordBytes'] += $localRecordBytes;
                $roles[$role]['localHeaderBytes'] += $localHeaderBytes;
                $roles[$role]['localHeaderFixedHeaderBytes'] += $localHeaderFixedHeaderBytes;
                $roles[$role]['localHeaderVariableFieldBytes'] += $localHeaderVariableFieldBytes;
                $roles[$role]['localHeaderRawNameBytes'] += $localHeaderRawNameBytes;
                $roles[$role]['localHeaderExtraFieldBytes'] += $localHeaderExtraFieldBytes;
                $roles[$role]['localHeaderReviewFieldBytes'] += $localHeaderReviewFieldBytes;
                $roles[$role]['compressedDataBytes'] += $compressedDataBytes;
                $roles[$role]['dataDescriptorBytes'] += $dataDescriptorBytes;
                $roles[$role]['centralDirectoryRecordBytes'] += $centralDirectoryRecordBytes;
                $roles[$role]['centralDirectoryFixedHeaderBytes'] += $centralDirectoryFixedHeaderBytes;
                $roles[$role]['centralDirectoryVariableFieldBytes'] += $centralDirectoryVariableFieldBytes;
                $roles[$role]['centralDirectoryRawNameBytes'] += $centralDirectoryRawNameBytes;
                $roles[$role]['centralDirectoryExtraFieldBytes'] += $centralDirectoryExtraFieldBytes;
                $roles[$role]['centralDirectoryRawCommentBytes'] += $centralDirectoryRawCommentBytes;
                $roles[$role]['centralDirectoryReviewFieldBytes'] += $centralDirectoryReviewFieldBytes;
                $roles[$role]['sourceRecordIssueCount'] += $sourceRecordIssueCount;
                $roles[$role]['entryNames'][] = $entryName;
                $roles[$role]['directoryRootCounts'][$directoryRoot] =
                    ($roles[$role]['directoryRootCounts'][$directoryRoot] ?? 0) + 1;
                $roles[$role]['compressionMethodCounts'][$compressionMethod] =
                    ($roles[$role]['compressionMethodCounts'][$compressionMethod] ?? 0) + 1;
                $roles[$role]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                    ($roles[$role]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
                $roles[$role]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                    ($roles[$role]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
                $roles[$role]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                    ($roles[$role]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
                if ($dataDescriptorBytes > 0 || ($part['zipUsesDataDescriptor'] ?? false) === true) {
                    ++$roles[$role]['dataDescriptorEntryCount'];
                }
                if ($sourceRecordIssueCount > 0) {
                    ++$roles[$role]['sourceRecordIssueEntryCount'];
                }

                $roleEntrySummary = $entrySummary;
                $roleEntrySummary['role'] = $role;
                $largestEntry = $roles[$role]['largestSourceRecordEntry'];
                if (
                    !is_array($largestEntry)
                    || $sourceRecordBytes > (int) ($largestEntry['sourceRecordBytes'] ?? 0)
                    || ($sourceRecordBytes === (int) ($largestEntry['sourceRecordBytes'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
                ) {
                    $roles[$role]['largestSourceRecordEntry'] = $roleEntrySummary;
                }
            }
        }

        $roleCounts = [];
        $roleBytes = [];
        $occurrenceCount = 0;
        $dataDescriptorOccurrenceCount = 0;
        $issueOccurrenceCount = 0;
        ksort($roles, SORT_STRING);
        foreach ($roles as $role => $summary) {
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['directoryRootCounts'], SORT_STRING);
            ksort($summary['compressionMethodCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            $roles[$role] = $summary;
            $roleCounts[$role] = $summary['entryCount'];
            $roleBytes[$role] = $summary['sourceRecordBytes'];
            $occurrenceCount += $summary['entryCount'];
            $dataDescriptorOccurrenceCount += $summary['dataDescriptorEntryCount'];
            $issueOccurrenceCount += $summary['sourceRecordIssueEntryCount'];
        }

        return [
            'packageZipSourceRecordRoleCount' => count($roles),
            'packageZipSourceRecordRoleCounts' => $roleCounts,
            'packageZipSourceRecordRoleBytes' => $roleBytes,
            'packageZipSourceRecordRoleOccurrenceCount' => $occurrenceCount,
            'packageZipSourceRecordRoleDataDescriptorOccurrenceCount' => $dataDescriptorOccurrenceCount,
            'packageZipSourceRecordRoleIssueOccurrenceCount' => $issueOccurrenceCount,
            'packageZipSourceRecordRoles' => array_values($roles),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function packageZipSourceRecordCompressionMethodInventory(array $parts): array
    {
        $intField = static function (array $part, string $field): int {
            $value = $part[$field] ?? null;

            return is_int($value) ? $value : 0;
        };
        $methods = [];

        foreach ($parts as $name => $part) {
            if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
                continue;
            }

            $compressionMethod = is_int($part['compressionMethod'] ?? null) ? $part['compressionMethod'] : null;
            $compressionMethodKey = $compressionMethod !== null ? (string) $compressionMethod : '(missing)';
            if (!isset($methods[$compressionMethodKey])) {
                $methods[$compressionMethodKey] = [
                    'compressionMethodKey' => $compressionMethodKey,
                    'compressionMethod' => $compressionMethod,
                    'compressionMethodName' => $compressionMethod !== null ? self::compressionMethodName($compressionMethod) : null,
                    'entryCount' => 0,
                    'entryNames' => [],
                    'sourceRecordBytes' => 0,
                    'localRecordBytes' => 0,
                    'localHeaderBytes' => 0,
                    'compressedDataBytes' => 0,
                    'dataDescriptorBytes' => 0,
                    'dataDescriptorEntryCount' => 0,
                    'centralDirectoryRecordBytes' => 0,
                    'compressedByteLength' => 0,
                    'uncompressedByteLength' => 0,
                    'expansionRatio' => 0.0,
                    'unsupportedEntryCount' => 0,
                    'exposableEntryCount' => 0,
                    'blockedEntryCount' => 0,
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'largestSourceRecordEntry' => null,
                ];
            }

            $entryName = is_string($part['path'] ?? null)
                ? $part['path']
                : (is_string($part['part'] ?? null) ? $part['part'] : (string) $name);
            $sourceRecordBytes = $intField($part, 'zipSourceRecordBytes');
            $localRecordBytes = $intField($part, 'zipLocalRecordBytes');
            $localHeaderBytes = $intField($part, 'zipLocalHeaderBytes');
            $compressedDataBytes = $intField($part, 'zipCompressedDataBytes');
            $dataDescriptorBytes = $intField($part, 'zipDataDescriptorBytes');
            $centralDirectoryRecordBytes = $intField($part, 'zipCentralDirectoryRecordBytes');
            $compressedByteLength = $intField($part, 'compressedByteLength');
            $uncompressedByteLength = $intField($part, 'byteLength');
            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) && $part['byteExposurePolicy'] !== ''
                ? $part['byteExposurePolicy']
                : '(missing)';
            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) && $part['manifestMediaFamily'] !== ''
                ? $part['manifestMediaFamily']
                : '(missing)';
            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) && $part['manifestMediaTypeBase'] !== ''
                ? $part['manifestMediaTypeBase']
                : '(missing)';
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));

            ++$methods[$compressionMethodKey]['entryCount'];
            $methods[$compressionMethodKey]['entryNames'][] = $entryName;
            $methods[$compressionMethodKey]['sourceRecordBytes'] += $sourceRecordBytes;
            $methods[$compressionMethodKey]['localRecordBytes'] += $localRecordBytes;
            $methods[$compressionMethodKey]['localHeaderBytes'] += $localHeaderBytes;
            $methods[$compressionMethodKey]['compressedDataBytes'] += $compressedDataBytes;
            $methods[$compressionMethodKey]['dataDescriptorBytes'] += $dataDescriptorBytes;
            $methods[$compressionMethodKey]['centralDirectoryRecordBytes'] += $centralDirectoryRecordBytes;
            $methods[$compressionMethodKey]['compressedByteLength'] += $compressedByteLength;
            $methods[$compressionMethodKey]['uncompressedByteLength'] += $uncompressedByteLength;
            if ($dataDescriptorBytes > 0 || ($part['zipUsesDataDescriptor'] ?? false) === true) {
                ++$methods[$compressionMethodKey]['dataDescriptorEntryCount'];
            }
            if ($compressionMethod !== null && $compressionMethod !== 0 && $compressionMethod !== 8) {
                ++$methods[$compressionMethodKey]['unsupportedEntryCount'];
            }
            if (($part['canExposeBytes'] ?? false) === true) {
                ++$methods[$compressionMethodKey]['exposableEntryCount'];
            } else {
                ++$methods[$compressionMethodKey]['blockedEntryCount'];
            }
            $methods[$compressionMethodKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($methods[$compressionMethodKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $methods[$compressionMethodKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($methods[$compressionMethodKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $methods[$compressionMethodKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($methods[$compressionMethodKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            foreach ($roles as $role) {
                if ($role === '') {
                    continue;
                }
                $methods[$compressionMethodKey]['roleCounts'][$role] =
                    ($methods[$compressionMethodKey]['roleCounts'][$role] ?? 0) + 1;
            }

            $pathShape = is_array($part['pathShape'] ?? null) ? $part['pathShape'] : [];
            $entrySummary = [
                'entryName' => $entryName,
                'compressionMethodKey' => $compressionMethodKey,
                'compressionMethod' => $compressionMethod,
                'compressionMethodName' => $compressionMethod !== null ? self::compressionMethodName($compressionMethod) : null,
                'packageDirectory' => is_string($part['packageDirectory'] ?? null)
                    ? $part['packageDirectory']
                    : (is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null),
                'packageBasename' => is_string($part['packageBasename'] ?? null)
                    ? $part['packageBasename']
                    : (is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null),
                'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
                'byteLength' => $uncompressedByteLength,
                'compressedByteLength' => $compressedByteLength,
                'sourceRecordBytes' => $sourceRecordBytes,
                'localRecordBytes' => $localRecordBytes,
                'localHeaderBytes' => $localHeaderBytes,
                'compressedDataBytes' => $compressedDataBytes,
                'dataDescriptorBytes' => $dataDescriptorBytes,
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'roles' => $roles,
                'byteExposurePolicy' => $byteExposurePolicy === '(missing)' ? null : $byteExposurePolicy,
                'manifestMediaFamily' => $manifestMediaFamily === '(missing)' ? null : $manifestMediaFamily,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '(missing)' ? null : $manifestMediaTypeBase,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
            ];
            $largestEntry = $methods[$compressionMethodKey]['largestSourceRecordEntry'];
            if (
                !is_array($largestEntry)
                || $sourceRecordBytes > (int) ($largestEntry['sourceRecordBytes'] ?? 0)
                || ($sourceRecordBytes === (int) ($largestEntry['sourceRecordBytes'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
            ) {
                $methods[$compressionMethodKey]['largestSourceRecordEntry'] = $entrySummary;
            }
        }

        $methodCounts = [];
        $methodBytes = [];
        $methodCompressedByteLengths = [];
        $methodUncompressedByteLengths = [];
        $methodExpansionRatios = [];
        $dataDescriptorEntryCount = 0;
        $unsupportedEntryCount = 0;
        ksort($methods, SORT_STRING);
        foreach ($methods as $methodKey => $summary) {
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $summary['expansionRatio'] = $summary['uncompressedByteLength'] === 0
                ? 0.0
                : ($summary['compressedByteLength'] === 0 ? null : (float) ($summary['uncompressedByteLength'] / $summary['compressedByteLength']));
            $methods[$methodKey] = $summary;
            $methodCounts[$methodKey] = $summary['entryCount'];
            $methodBytes[$methodKey] = $summary['sourceRecordBytes'];
            $methodCompressedByteLengths[$methodKey] = $summary['compressedByteLength'];
            $methodUncompressedByteLengths[$methodKey] = $summary['uncompressedByteLength'];
            $methodExpansionRatios[$methodKey] = $summary['expansionRatio'];
            $dataDescriptorEntryCount += $summary['dataDescriptorEntryCount'];
            $unsupportedEntryCount += $summary['unsupportedEntryCount'];
        }

        return [
            'packageZipSourceRecordCompressionMethodCount' => count($methods),
            'packageZipSourceRecordCompressionMethodCounts' => $methodCounts,
            'packageZipSourceRecordCompressionMethodBytes' => $methodBytes,
            'packageZipSourceRecordCompressionMethodCompressedByteLengths' => $methodCompressedByteLengths,
            'packageZipSourceRecordCompressionMethodUncompressedByteLengths' => $methodUncompressedByteLengths,
            'packageZipSourceRecordCompressionMethodExpansionRatios' => $methodExpansionRatios,
            'packageZipSourceRecordCompressionMethodDataDescriptorEntryCount' => $dataDescriptorEntryCount,
            'packageZipSourceRecordCompressionMethodUnsupportedEntryCount' => $unsupportedEntryCount,
            'packageZipSourceRecordCompressionMethods' => array_values($methods),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function packageZipTimestampSourceInventory(array $parts): array
    {
        $intField = static function (array $part, string $field): int {
            $value = $part[$field] ?? null;

            return is_int($value) ? $value : 0;
        };
        $sources = [];

        foreach ($parts as $name => $part) {
            if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
                continue;
            }

            $entryName = is_string($part['path'] ?? null)
                ? $part['path']
                : (is_string($part['part'] ?? null) ? $part['part'] : (string) $name);
            $timestampSource = is_string($part['zipTimestampSource'] ?? null) && $part['zipTimestampSource'] !== ''
                ? $part['zipTimestampSource']
                : null;
            $timestampSourceKey = $timestampSource ?? '(missing)';
            if (!isset($sources[$timestampSourceKey])) {
                $sources[$timestampSourceKey] = [
                    'timestampSourceKey' => $timestampSourceKey,
                    'timestampSource' => $timestampSource,
                    'entryCount' => 0,
                    'modifiedEntryCount' => 0,
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'sourceRecordBytes' => 0,
                    'localRecordBytes' => 0,
                    'centralDirectoryRecordBytes' => 0,
                    'dosTimestampEntryCount' => 0,
                    'extendedTimestampEntryCount' => 0,
                    'ntfsTimestampEntryCount' => 0,
                    'invalidDosTimestampEntryCount' => 0,
                    'zipModificationTimeIssueEntryCount' => 0,
                    'zipModificationTimeIssueCount' => 0,
                    'directoryRootCounts' => [],
                    'localTimestampSourceCounts' => [],
                    'centralTimestampSourceCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'entryNames' => [],
                    'earliestModifiedAt' => null,
                    'latestModifiedAt' => null,
                    'earliestModifiedEntry' => null,
                    'latestModifiedEntry' => null,
                    'largestSourceRecordEntry' => null,
                ];
            }

            $pathShape = is_array($part['pathShape'] ?? null) ? $part['pathShape'] : [];
            $directoryRoot = is_string($part['zipPackageManifestDirectoryRoot'] ?? null)
                ? $part['zipPackageManifestDirectoryRoot']
                : self::packageDirectoryRoot($entryName);
            if ($directoryRoot === '') {
                $directoryRoot = '/';
            }
            $localTimestampSource = is_string($part['zipLocalTimestampSource'] ?? null) && $part['zipLocalTimestampSource'] !== ''
                ? $part['zipLocalTimestampSource']
                : '(missing)';
            $centralTimestampSource = is_string($part['zipCentralTimestampSource'] ?? null) && $part['zipCentralTimestampSource'] !== ''
                ? $part['zipCentralTimestampSource']
                : '(missing)';
            $byteLength = $intField($part, 'byteLength');
            $compressedByteLength = $intField($part, 'compressedByteLength');
            $sourceRecordBytes = $intField($part, 'zipSourceRecordBytes');
            $localRecordBytes = $intField($part, 'zipLocalRecordBytes');
            $centralDirectoryRecordBytes = $intField($part, 'zipCentralDirectoryRecordBytes');
            $modifiedAt = is_int($part['zipModifiedAt'] ?? null) ? $part['zipModifiedAt'] : null;
            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) && $part['byteExposurePolicy'] !== ''
                ? $part['byteExposurePolicy']
                : '(missing)';
            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) && $part['manifestMediaFamily'] !== ''
                ? $part['manifestMediaFamily']
                : '(missing)';
            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) && $part['manifestMediaTypeBase'] !== ''
                ? $part['manifestMediaTypeBase']
                : '(missing)';
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));
            $issues = is_array($part['zipTimestampIssues'] ?? null)
                ? array_values(array_filter($part['zipTimestampIssues'], static fn (mixed $issue): bool => is_string($issue)))
                : [];

            $entrySummary = [
                'entryName' => $entryName,
                'directoryRoot' => $directoryRoot,
                'packageDirectory' => is_string($part['packageDirectory'] ?? null)
                    ? $part['packageDirectory']
                    : (is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null),
                'packageBasename' => is_string($part['packageBasename'] ?? null)
                    ? $part['packageBasename']
                    : (is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null),
                'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
                'byteLength' => $byteLength,
                'compressedByteLength' => $compressedByteLength,
                'sourceRecordBytes' => $sourceRecordBytes,
                'localRecordBytes' => $localRecordBytes,
                'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                'zipModifiedAt' => $modifiedAt,
                'zipTimestampSource' => $timestampSource,
                'zipLocalTimestampSource' => $localTimestampSource === '(missing)' ? null : $localTimestampSource,
                'zipCentralTimestampSource' => $centralTimestampSource === '(missing)' ? null : $centralTimestampSource,
                'zipHasDosTimestamp' => ($part['zipHasDosTimestamp'] ?? false) === true,
                'zipIsDosTimestampValid' => ($part['zipIsDosTimestampValid'] ?? true) === true,
                'zipExtendedModifiedAt' => is_int($part['zipExtendedModifiedAt'] ?? null) ? $part['zipExtendedModifiedAt'] : null,
                'zipNtfsModifiedAt' => is_int($part['zipNtfsModifiedAt'] ?? null) ? $part['zipNtfsModifiedAt'] : null,
                'zipModificationTimeIssueCount' => count($issues),
                'zipModificationTimeIssues' => $issues,
                'roles' => $roles,
                'byteExposurePolicy' => $byteExposurePolicy === '(missing)' ? null : $byteExposurePolicy,
                'manifestMediaFamily' => $manifestMediaFamily === '(missing)' ? null : $manifestMediaFamily,
                'manifestMediaType' => is_string($part['manifestMediaType'] ?? null) ? $part['manifestMediaType'] : null,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '(missing)' ? null : $manifestMediaTypeBase,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'undeclared' => ($part['undeclared'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
            ];

            ++$sources[$timestampSourceKey]['entryCount'];
            $sources[$timestampSourceKey]['entryNames'][] = $entryName;
            $sources[$timestampSourceKey]['byteLength'] += $byteLength;
            $sources[$timestampSourceKey]['compressedByteLength'] += $compressedByteLength;
            $sources[$timestampSourceKey]['sourceRecordBytes'] += $sourceRecordBytes;
            $sources[$timestampSourceKey]['localRecordBytes'] += $localRecordBytes;
            $sources[$timestampSourceKey]['centralDirectoryRecordBytes'] += $centralDirectoryRecordBytes;
            $sources[$timestampSourceKey]['directoryRootCounts'][$directoryRoot] =
                ($sources[$timestampSourceKey]['directoryRootCounts'][$directoryRoot] ?? 0) + 1;
            $sources[$timestampSourceKey]['localTimestampSourceCounts'][$localTimestampSource] =
                ($sources[$timestampSourceKey]['localTimestampSourceCounts'][$localTimestampSource] ?? 0) + 1;
            $sources[$timestampSourceKey]['centralTimestampSourceCounts'][$centralTimestampSource] =
                ($sources[$timestampSourceKey]['centralTimestampSourceCounts'][$centralTimestampSource] ?? 0) + 1;
            $sources[$timestampSourceKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($sources[$timestampSourceKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            $sources[$timestampSourceKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                ($sources[$timestampSourceKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            $sources[$timestampSourceKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                ($sources[$timestampSourceKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            foreach ($roles as $role) {
                if ($role === '') {
                    continue;
                }
                $sources[$timestampSourceKey]['roleCounts'][$role] =
                    ($sources[$timestampSourceKey]['roleCounts'][$role] ?? 0) + 1;
            }

            if ($modifiedAt !== null) {
                ++$sources[$timestampSourceKey]['modifiedEntryCount'];
                $earliestModifiedAt = $sources[$timestampSourceKey]['earliestModifiedAt'];
                if (!is_int($earliestModifiedAt) || $modifiedAt < $earliestModifiedAt) {
                    $sources[$timestampSourceKey]['earliestModifiedAt'] = $modifiedAt;
                    $sources[$timestampSourceKey]['earliestModifiedEntry'] = $entrySummary;
                }
                $latestModifiedAt = $sources[$timestampSourceKey]['latestModifiedAt'];
                if (!is_int($latestModifiedAt) || $modifiedAt > $latestModifiedAt) {
                    $sources[$timestampSourceKey]['latestModifiedAt'] = $modifiedAt;
                    $sources[$timestampSourceKey]['latestModifiedEntry'] = $entrySummary;
                }
            }
            if (($part['zipHasDosTimestamp'] ?? false) === true) {
                ++$sources[$timestampSourceKey]['dosTimestampEntryCount'];
            }
            if (($part['zipIsDosTimestampValid'] ?? true) !== true) {
                ++$sources[$timestampSourceKey]['invalidDosTimestampEntryCount'];
            }
            if (is_int($part['zipExtendedModifiedAt'] ?? null)) {
                ++$sources[$timestampSourceKey]['extendedTimestampEntryCount'];
            }
            if (is_int($part['zipNtfsModifiedAt'] ?? null)) {
                ++$sources[$timestampSourceKey]['ntfsTimestampEntryCount'];
            }
            if ($issues !== []) {
                ++$sources[$timestampSourceKey]['zipModificationTimeIssueEntryCount'];
                $sources[$timestampSourceKey]['zipModificationTimeIssueCount'] += count($issues);
            }

            $largestEntry = $sources[$timestampSourceKey]['largestSourceRecordEntry'];
            if (
                !is_array($largestEntry)
                || $sourceRecordBytes > (int) ($largestEntry['sourceRecordBytes'] ?? 0)
                || ($sourceRecordBytes === (int) ($largestEntry['sourceRecordBytes'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
            ) {
                $sources[$timestampSourceKey]['largestSourceRecordEntry'] = $entrySummary;
            }
        }

        $sourceCounts = [];
        $sourceByteLengths = [];
        $sourceRecordBytes = [];
        $modifiedEntryCount = 0;
        $issueEntryCount = 0;
        ksort($sources, SORT_STRING);
        foreach ($sources as $sourceKey => $summary) {
            sort($summary['entryNames'], SORT_STRING);
            ksort($summary['directoryRootCounts'], SORT_STRING);
            ksort($summary['localTimestampSourceCounts'], SORT_STRING);
            ksort($summary['centralTimestampSourceCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            $sources[$sourceKey] = $summary;
            $sourceCounts[$sourceKey] = $summary['entryCount'];
            $sourceByteLengths[$sourceKey] = $summary['byteLength'];
            $sourceRecordBytes[$sourceKey] = $summary['sourceRecordBytes'];
            $modifiedEntryCount += $summary['modifiedEntryCount'];
            $issueEntryCount += $summary['zipModificationTimeIssueEntryCount'];
        }

        return [
            'packageZipTimestampSourceCount' => count($sources),
            'packageZipTimestampSourceCounts' => $sourceCounts,
            'packageZipTimestampSourceByteLengths' => $sourceByteLengths,
            'packageZipTimestampSourceRecordBytes' => $sourceRecordBytes,
            'packageZipTimestampSourceModifiedEntryCount' => $modifiedEntryCount,
            'packageZipTimestampSourceIssueEntryCount' => $issueEntryCount,
            'packageZipTimestampSources' => array_values($sources),
        ];
    }

    private static function packageDirectoryRoot(string $entryName): string
    {
        $trimmed = trim($entryName, '/');
        if ($trimmed === '' || !str_contains($trimmed, '/')) {
            return '/';
        }

        $segments = explode('/', $trimmed);

        return $segments[0] . '/';
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array{
     *     packageDirectoryBaseNameCount:int,
     *     packageDirectoryBaseNameCounts:array<string, int>,
     *     entryNamesByPackageDirectoryBaseName:array<string, list<string>>,
     *     duplicatePackageDirectoryBaseNameCount:int,
     *     duplicatePackageDirectoryBaseNames:list<string>,
     *     packageDirectoryBaseNames:list<array<string, mixed>>,
     *     packageCaseFoldDirectoryBaseNameCount:int,
     *     packageCaseFoldDirectoryBaseNameCounts:array<string, int>,
     *     entryNamesByPackageCaseFoldDirectoryBaseName:array<string, list<string>>,
     *     duplicatePackageCaseFoldDirectoryBaseNameCount:int,
     *     duplicatePackageCaseFoldDirectoryBaseNames:list<string>,
     *     packageCaseFoldDirectoryBaseNames:list<array<string, mixed>>,
     *     packageDirectoryBaseNameStemCount:int,
     *     packageDirectoryBaseNameStemCounts:array<string, int>,
     *     entryNamesByPackageDirectoryBaseNameStem:array<string, list<string>>,
     *     duplicatePackageDirectoryBaseNameStemCount:int,
     *     duplicatePackageDirectoryBaseNameStems:list<string>,
     *     packageDirectoryBaseNameStems:list<array<string, mixed>>,
     *     packageCaseFoldDirectoryBaseNameStemCount:int,
     *     packageCaseFoldDirectoryBaseNameStemCounts:array<string, int>,
     *     entryNamesByPackageCaseFoldDirectoryBaseNameStem:array<string, list<string>>,
     *     duplicatePackageCaseFoldDirectoryBaseNameStemCount:int,
     *     duplicatePackageCaseFoldDirectoryBaseNameStems:list<string>,
     *     packageCaseFoldDirectoryBaseNameStems:list<array<string, mixed>>
     * }
     */
    private static function packageDirectoryBaseNameInventory(array $parts): array
    {
        $directoryBaseNameCounts = [];
        $entryNamesByDirectoryBaseName = [];
        $directoryBaseNames = [];
        $caseFoldDirectoryBaseNameCounts = [];
        $entryNamesByCaseFoldDirectoryBaseName = [];
        $caseFoldDirectoryBaseNames = [];
        $directoryBaseNameStemCounts = [];
        $entryNamesByDirectoryBaseNameStem = [];
        $directoryBaseNameStems = [];
        $caseFoldDirectoryBaseNameStemCounts = [];
        $entryNamesByCaseFoldDirectoryBaseNameStem = [];
        $caseFoldDirectoryBaseNameStems = [];

        foreach ($parts as $name => $part) {
            $entryName = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
            $pathShape = is_array($part['pathShape'] ?? null) ? $part['pathShape'] : [];
            $packageDirectory = is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null;
            $directoryBaseName = is_string($part['packageDirectoryBaseName'] ?? null) ? $part['packageDirectoryBaseName'] : null;
            if ($directoryBaseName === null && is_string($pathShape['directoryBaseName'] ?? null)) {
                $directoryBaseName = $pathShape['directoryBaseName'];
            }
            if ($directoryBaseName === null && is_string($packageDirectory) && $packageDirectory !== '') {
                $directorySegments = explode('/', trim($packageDirectory, '/'));
                $directoryBaseName = $directorySegments === [] ? null : $directorySegments[count($directorySegments) - 1];
            }
            if ($directoryBaseName === null || $directoryBaseName === '') {
                continue;
            }

            $directoryBaseNameStem = is_string($part['packageDirectoryBaseNameStem'] ?? null) ? $part['packageDirectoryBaseNameStem'] : null;
            if ($directoryBaseNameStem === null && is_string($pathShape['directoryBaseNameStem'] ?? null)) {
                $directoryBaseNameStem = $pathShape['directoryBaseNameStem'];
            }
            if ($directoryBaseNameStem === null || $directoryBaseNameStem === '') {
                $directoryBaseNameStem = self::packagePartBasenameStem($directoryBaseName);
            }
            $caseFoldDirectoryBaseNameStem = is_string($part['packageCaseFoldDirectoryBaseNameStem'] ?? null)
                ? $part['packageCaseFoldDirectoryBaseNameStem']
                : null;
            if ($caseFoldDirectoryBaseNameStem === null && is_string($pathShape['caseFoldDirectoryBaseNameStem'] ?? null)) {
                $caseFoldDirectoryBaseNameStem = $pathShape['caseFoldDirectoryBaseNameStem'];
            }
            if ($caseFoldDirectoryBaseNameStem === null || $caseFoldDirectoryBaseNameStem === '') {
                $caseFoldDirectoryBaseNameStem = strtolower($directoryBaseNameStem);
            }

            $caseFoldDirectoryBaseName = strtolower($directoryBaseName);
            $packageDirectoryKey = is_string($packageDirectory) && $packageDirectory !== '' ? $packageDirectory : '(root)';
            $isDirectory = ($part['isDirectory'] ?? false) === true;
            $byteLength = is_int($part['byteLength'] ?? null) ? $part['byteLength'] : 0;
            $compressedByteLength = is_int($part['compressedByteLength'] ?? null) ? $part['compressedByteLength'] : 0;
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));
            $declaredInManifest = ($part['declaredInManifest'] ?? false) === true;
            $undeclared = ($part['undeclared'] ?? false) === true;
            $encrypted = ($part['encrypted'] ?? false) === true;
            $canExposeBytes = ($part['canExposeBytes'] ?? false) === true;
            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) ? $part['byteExposurePolicy'] : '';
            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) ? $part['manifestMediaFamily'] : '';
            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) ? $part['manifestMediaTypeBase'] : '';

            $directoryBaseNameCounts[$directoryBaseName] = ($directoryBaseNameCounts[$directoryBaseName] ?? 0) + 1;
            $entryNamesByDirectoryBaseName[$directoryBaseName][] = $entryName;
            $caseFoldDirectoryBaseNameCounts[$caseFoldDirectoryBaseName] =
                ($caseFoldDirectoryBaseNameCounts[$caseFoldDirectoryBaseName] ?? 0) + 1;
            $entryNamesByCaseFoldDirectoryBaseName[$caseFoldDirectoryBaseName][] = $entryName;
            $directoryBaseNameStemCounts[$directoryBaseNameStem] =
                ($directoryBaseNameStemCounts[$directoryBaseNameStem] ?? 0) + 1;
            $entryNamesByDirectoryBaseNameStem[$directoryBaseNameStem][] = $entryName;
            $caseFoldDirectoryBaseNameStemCounts[$caseFoldDirectoryBaseNameStem] =
                ($caseFoldDirectoryBaseNameStemCounts[$caseFoldDirectoryBaseNameStem] ?? 0) + 1;
            $entryNamesByCaseFoldDirectoryBaseNameStem[$caseFoldDirectoryBaseNameStem][] = $entryName;

            if (!isset($directoryBaseNames[$directoryBaseName])) {
                $directoryBaseNames[$directoryBaseName] = [
                    'directoryBaseName' => $directoryBaseName,
                    'directoryCount' => 0,
                    'entryCount' => 0,
                    'fileEntryCount' => 0,
                    'directoryEntryCount' => 0,
                    'declaredPartCount' => 0,
                    'undeclaredPartCount' => 0,
                    'encryptedPartCount' => 0,
                    'exposablePartCount' => 0,
                    'blockedPartCount' => 0,
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'packageDirectoryCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'packageDirectories' => [],
                    'entryNames' => [],
                    'largestEntry' => null,
                ];
            }

            ++$directoryBaseNames[$directoryBaseName]['entryCount'];
            $directoryBaseNames[$directoryBaseName]['byteLength'] += $byteLength;
            $directoryBaseNames[$directoryBaseName]['compressedByteLength'] += $compressedByteLength;
            $directoryBaseNames[$directoryBaseName]['entryNames'][] = $entryName;
            $directoryBaseNames[$directoryBaseName]['packageDirectoryCounts'][$packageDirectoryKey] =
                ($directoryBaseNames[$directoryBaseName]['packageDirectoryCounts'][$packageDirectoryKey] ?? 0) + 1;
            if ($isDirectory) {
                ++$directoryBaseNames[$directoryBaseName]['directoryEntryCount'];
            } else {
                ++$directoryBaseNames[$directoryBaseName]['fileEntryCount'];
            }
            if ($declaredInManifest) {
                ++$directoryBaseNames[$directoryBaseName]['declaredPartCount'];
            }
            if ($undeclared) {
                ++$directoryBaseNames[$directoryBaseName]['undeclaredPartCount'];
            }
            if ($encrypted) {
                ++$directoryBaseNames[$directoryBaseName]['encryptedPartCount'];
            }
            if ($canExposeBytes) {
                ++$directoryBaseNames[$directoryBaseName]['exposablePartCount'];
            } else {
                ++$directoryBaseNames[$directoryBaseName]['blockedPartCount'];
            }
            foreach ($roles as $role) {
                $directoryBaseNames[$directoryBaseName]['roleCounts'][$role] =
                    ($directoryBaseNames[$directoryBaseName]['roleCounts'][$role] ?? 0) + 1;
            }
            if ($byteExposurePolicy !== '') {
                $directoryBaseNames[$directoryBaseName]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                    ($directoryBaseNames[$directoryBaseName]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            }
            if ($manifestMediaFamily !== '') {
                $directoryBaseNames[$directoryBaseName]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                    ($directoryBaseNames[$directoryBaseName]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            }
            if ($manifestMediaTypeBase !== '') {
                $directoryBaseNames[$directoryBaseName]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                    ($directoryBaseNames[$directoryBaseName]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            }

            if (!isset($caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName])) {
                $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName] = [
                    'caseFoldDirectoryBaseName' => $caseFoldDirectoryBaseName,
                    'directoryBaseNameVariantCount' => 0,
                    'directoryCount' => 0,
                    'entryCount' => 0,
                    'fileEntryCount' => 0,
                    'directoryEntryCount' => 0,
                    'declaredPartCount' => 0,
                    'undeclaredPartCount' => 0,
                    'encryptedPartCount' => 0,
                    'exposablePartCount' => 0,
                    'blockedPartCount' => 0,
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'directoryBaseNameCounts' => [],
                    'packageDirectoryCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'packageDirectories' => [],
                    'entryNames' => [],
                    'largestEntry' => null,
                ];
            }

            ++$caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['entryCount'];
            $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['byteLength'] += $byteLength;
            $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['compressedByteLength'] += $compressedByteLength;
            $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['entryNames'][] = $entryName;
            $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['directoryBaseNameCounts'][$directoryBaseName] =
                ($caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['directoryBaseNameCounts'][$directoryBaseName] ?? 0) + 1;
            $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['packageDirectoryCounts'][$packageDirectoryKey] =
                ($caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['packageDirectoryCounts'][$packageDirectoryKey] ?? 0) + 1;

            if ($isDirectory) {
                ++$caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['directoryEntryCount'];
            } else {
                ++$caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['fileEntryCount'];
            }
            if ($declaredInManifest) {
                ++$caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['declaredPartCount'];
            }
            if ($undeclared) {
                ++$caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['undeclaredPartCount'];
            }
            if ($encrypted) {
                ++$caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['encryptedPartCount'];
            }
            if ($canExposeBytes) {
                ++$caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['exposablePartCount'];
            } else {
                ++$caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['blockedPartCount'];
            }
            foreach ($roles as $role) {
                $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['roleCounts'][$role] =
                    ($caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['roleCounts'][$role] ?? 0) + 1;
            }
            if ($byteExposurePolicy !== '') {
                $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                    ($caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            }
            if ($manifestMediaFamily !== '') {
                $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                    ($caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            }
            if ($manifestMediaTypeBase !== '') {
                $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                    ($caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            }

            $entrySummary = [
                'entryName' => $entryName,
                'packageDirectory' => $packageDirectory,
                'directoryBaseName' => $directoryBaseName,
                'directoryBaseNameStem' => $directoryBaseNameStem,
                'caseFoldDirectoryBaseName' => $caseFoldDirectoryBaseName,
                'caseFoldDirectoryBaseNameStem' => $caseFoldDirectoryBaseNameStem,
                'packageBasename' => is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null,
                'packagePathDepth' => is_int($part['packagePathDepth'] ?? null) ? $part['packagePathDepth'] : null,
                'packagePartExtension' => is_string($part['packagePartExtension'] ?? null) ? $part['packagePartExtension'] : null,
                'byteLength' => $byteLength,
                'compressedByteLength' => $compressedByteLength,
                'crc32' => is_string($part['crc32'] ?? null) ? $part['crc32'] : null,
                'byteSha256' => is_string($part['byteSha256'] ?? null) ? $part['byteSha256'] : null,
                'roles' => $roles,
                'declaredInManifest' => $declaredInManifest,
                'undeclared' => $undeclared,
                'encrypted' => $encrypted,
                'canExposeBytes' => $canExposeBytes,
                'byteExposurePolicy' => $byteExposurePolicy === '' ? null : $byteExposurePolicy,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '' ? null : $manifestMediaTypeBase,
                'manifestMediaFamily' => $manifestMediaFamily === '' ? null : $manifestMediaFamily,
            ];
            $largestDirectoryBaseNameEntry = $directoryBaseNames[$directoryBaseName]['largestEntry'];
            if (
                !is_array($largestDirectoryBaseNameEntry)
                || $byteLength > (int) ($largestDirectoryBaseNameEntry['byteLength'] ?? 0)
                || ($byteLength === (int) ($largestDirectoryBaseNameEntry['byteLength'] ?? 0) && strcmp($entryName, (string) ($largestDirectoryBaseNameEntry['entryName'] ?? '')) < 0)
            ) {
                $directoryBaseNames[$directoryBaseName]['largestEntry'] = $entrySummary;
            }
            $largestEntry = $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['largestEntry'];
            if (
                !is_array($largestEntry)
                || $byteLength > (int) ($largestEntry['byteLength'] ?? 0)
                || ($byteLength === (int) ($largestEntry['byteLength'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
            ) {
                $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName]['largestEntry'] = $entrySummary;
            }

            foreach ([
                [
                    'summaries' => &$directoryBaseNameStems,
                    'key' => $directoryBaseNameStem,
                    'keyField' => 'directoryBaseNameStem',
                    'caseFolded' => false,
                ],
                [
                    'summaries' => &$caseFoldDirectoryBaseNameStems,
                    'key' => $caseFoldDirectoryBaseNameStem,
                    'keyField' => 'caseFoldDirectoryBaseNameStem',
                    'caseFolded' => true,
                ],
            ] as &$stemTarget) {
                $stemKey = $stemTarget['key'];
                $stemKeyField = $stemTarget['keyField'];
                $stemSummaries = &$stemTarget['summaries'];
                if (!isset($stemSummaries[$stemKey])) {
                    $stemSummaries[$stemKey] = [
                        $stemKeyField => $stemKey,
                        'directoryBaseNameVariantCount' => 0,
                        'directoryCount' => 0,
                        'entryCount' => 0,
                        'fileEntryCount' => 0,
                        'directoryEntryCount' => 0,
                        'declaredPartCount' => 0,
                        'undeclaredPartCount' => 0,
                        'encryptedPartCount' => 0,
                        'exposablePartCount' => 0,
                        'blockedPartCount' => 0,
                        'byteLength' => 0,
                        'compressedByteLength' => 0,
                        'directoryBaseNameCounts' => [],
                        'packageDirectoryCounts' => [],
                        'manifestMediaFamilyCounts' => [],
                        'manifestMediaTypeBaseCounts' => [],
                        'roleCounts' => [],
                        'byteExposurePolicyCounts' => [],
                        'packageDirectories' => [],
                        'entryNames' => [],
                        'largestEntry' => null,
                    ];
                    if ($stemTarget['caseFolded'] === true) {
                        $stemSummaries[$stemKey]['directoryBaseNameStemVariantCount'] = 0;
                        $stemSummaries[$stemKey]['directoryBaseNameStemCounts'] = [];
                    }
                }

                ++$stemSummaries[$stemKey]['entryCount'];
                $stemSummaries[$stemKey]['byteLength'] += $byteLength;
                $stemSummaries[$stemKey]['compressedByteLength'] += $compressedByteLength;
                $stemSummaries[$stemKey]['entryNames'][] = $entryName;
                $stemSummaries[$stemKey]['directoryBaseNameCounts'][$directoryBaseName] =
                    ($stemSummaries[$stemKey]['directoryBaseNameCounts'][$directoryBaseName] ?? 0) + 1;
                if ($stemTarget['caseFolded'] === true) {
                    $stemSummaries[$stemKey]['directoryBaseNameStemCounts'][$directoryBaseNameStem] =
                        ($stemSummaries[$stemKey]['directoryBaseNameStemCounts'][$directoryBaseNameStem] ?? 0) + 1;
                }
                $stemSummaries[$stemKey]['packageDirectoryCounts'][$packageDirectoryKey] =
                    ($stemSummaries[$stemKey]['packageDirectoryCounts'][$packageDirectoryKey] ?? 0) + 1;

                if ($isDirectory) {
                    ++$stemSummaries[$stemKey]['directoryEntryCount'];
                } else {
                    ++$stemSummaries[$stemKey]['fileEntryCount'];
                }
                if ($declaredInManifest) {
                    ++$stemSummaries[$stemKey]['declaredPartCount'];
                }
                if ($undeclared) {
                    ++$stemSummaries[$stemKey]['undeclaredPartCount'];
                }
                if ($encrypted) {
                    ++$stemSummaries[$stemKey]['encryptedPartCount'];
                }
                if ($canExposeBytes) {
                    ++$stemSummaries[$stemKey]['exposablePartCount'];
                } else {
                    ++$stemSummaries[$stemKey]['blockedPartCount'];
                }
                foreach ($roles as $role) {
                    $stemSummaries[$stemKey]['roleCounts'][$role] =
                        ($stemSummaries[$stemKey]['roleCounts'][$role] ?? 0) + 1;
                }
                if ($byteExposurePolicy !== '') {
                    $stemSummaries[$stemKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                        ($stemSummaries[$stemKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
                }
                if ($manifestMediaFamily !== '') {
                    $stemSummaries[$stemKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                        ($stemSummaries[$stemKey]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
                }
                if ($manifestMediaTypeBase !== '') {
                    $stemSummaries[$stemKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                        ($stemSummaries[$stemKey]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
                }

                $largestStemEntry = $stemSummaries[$stemKey]['largestEntry'];
                if (
                    !is_array($largestStemEntry)
                    || $byteLength > (int) ($largestStemEntry['byteLength'] ?? 0)
                    || ($byteLength === (int) ($largestStemEntry['byteLength'] ?? 0) && strcmp($entryName, (string) ($largestStemEntry['entryName'] ?? '')) < 0)
                ) {
                    $stemSummaries[$stemKey]['largestEntry'] = $entrySummary;
                }
                unset($stemSummaries);
            }
            unset($stemTarget);
        }

        ksort($directoryBaseNameCounts, SORT_STRING);
        ksort($entryNamesByDirectoryBaseName, SORT_STRING);
        foreach ($entryNamesByDirectoryBaseName as $directoryBaseName => $entryNames) {
            sort($entryNames, SORT_STRING);
            $entryNamesByDirectoryBaseName[$directoryBaseName] = $entryNames;
        }
        ksort($caseFoldDirectoryBaseNameCounts, SORT_STRING);
        ksort($entryNamesByCaseFoldDirectoryBaseName, SORT_STRING);
        foreach ($entryNamesByCaseFoldDirectoryBaseName as $caseFoldDirectoryBaseName => $entryNames) {
            sort($entryNames, SORT_STRING);
            $entryNamesByCaseFoldDirectoryBaseName[$caseFoldDirectoryBaseName] = $entryNames;
        }
        ksort($directoryBaseNameStemCounts, SORT_STRING);
        ksort($entryNamesByDirectoryBaseNameStem, SORT_STRING);
        foreach ($entryNamesByDirectoryBaseNameStem as $directoryBaseNameStem => $entryNames) {
            sort($entryNames, SORT_STRING);
            $entryNamesByDirectoryBaseNameStem[$directoryBaseNameStem] = $entryNames;
        }
        ksort($caseFoldDirectoryBaseNameStemCounts, SORT_STRING);
        ksort($entryNamesByCaseFoldDirectoryBaseNameStem, SORT_STRING);
        foreach ($entryNamesByCaseFoldDirectoryBaseNameStem as $caseFoldDirectoryBaseNameStem => $entryNames) {
            sort($entryNames, SORT_STRING);
            $entryNamesByCaseFoldDirectoryBaseNameStem[$caseFoldDirectoryBaseNameStem] = $entryNames;
        }

        $duplicateDirectoryBaseNames = [];
        ksort($directoryBaseNames, SORT_STRING);
        foreach ($directoryBaseNames as $directoryBaseName => $summary) {
            ksort($summary['packageDirectoryCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
            $summary['packageDirectories'] = array_keys($summary['packageDirectoryCounts']);
            sort($summary['packageDirectories'], SORT_STRING);
            $summary['directoryCount'] = count($summary['packageDirectories']);
            if ($summary['directoryCount'] > 1) {
                $duplicateDirectoryBaseNames[] = $directoryBaseName;
            }
            $directoryBaseNames[$directoryBaseName] = $summary;
        }

        $duplicateCaseFoldDirectoryBaseNames = [];
        ksort($caseFoldDirectoryBaseNames, SORT_STRING);
        foreach ($caseFoldDirectoryBaseNames as $caseFoldDirectoryBaseName => $summary) {
            ksort($summary['directoryBaseNameCounts'], SORT_STRING);
            ksort($summary['packageDirectoryCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
            $summary['packageDirectories'] = array_keys($summary['packageDirectoryCounts']);
            sort($summary['packageDirectories'], SORT_STRING);
            $summary['directoryBaseNameVariantCount'] = count($summary['directoryBaseNameCounts']);
            $summary['directoryCount'] = count($summary['packageDirectories']);
            if ($summary['directoryCount'] > 1) {
                $duplicateCaseFoldDirectoryBaseNames[] = $caseFoldDirectoryBaseName;
            }
            $caseFoldDirectoryBaseNames[$caseFoldDirectoryBaseName] = $summary;
        }

        $duplicateDirectoryBaseNameStems = [];
        ksort($directoryBaseNameStems, SORT_STRING);
        foreach ($directoryBaseNameStems as $directoryBaseNameStem => $summary) {
            ksort($summary['directoryBaseNameCounts'], SORT_STRING);
            ksort($summary['packageDirectoryCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
            $summary['packageDirectories'] = array_keys($summary['packageDirectoryCounts']);
            sort($summary['packageDirectories'], SORT_STRING);
            $summary['directoryBaseNameVariantCount'] = count($summary['directoryBaseNameCounts']);
            $summary['directoryCount'] = count($summary['packageDirectories']);
            if ($summary['directoryCount'] > 1) {
                $duplicateDirectoryBaseNameStems[] = $directoryBaseNameStem;
            }
            $directoryBaseNameStems[$directoryBaseNameStem] = $summary;
        }

        $duplicateCaseFoldDirectoryBaseNameStems = [];
        ksort($caseFoldDirectoryBaseNameStems, SORT_STRING);
        foreach ($caseFoldDirectoryBaseNameStems as $caseFoldDirectoryBaseNameStem => $summary) {
            ksort($summary['directoryBaseNameStemCounts'], SORT_STRING);
            ksort($summary['directoryBaseNameCounts'], SORT_STRING);
            ksort($summary['packageDirectoryCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
            $summary['packageDirectories'] = array_keys($summary['packageDirectoryCounts']);
            sort($summary['packageDirectories'], SORT_STRING);
            $summary['directoryBaseNameStemVariantCount'] = count($summary['directoryBaseNameStemCounts']);
            $summary['directoryBaseNameVariantCount'] = count($summary['directoryBaseNameCounts']);
            $summary['directoryCount'] = count($summary['packageDirectories']);
            if ($summary['directoryCount'] > 1) {
                $duplicateCaseFoldDirectoryBaseNameStems[] = $caseFoldDirectoryBaseNameStem;
            }
            $caseFoldDirectoryBaseNameStems[$caseFoldDirectoryBaseNameStem] = $summary;
        }

        return [
            'packageDirectoryBaseNameCount' => count($directoryBaseNameCounts),
            'packageDirectoryBaseNameCounts' => $directoryBaseNameCounts,
            'entryNamesByPackageDirectoryBaseName' => $entryNamesByDirectoryBaseName,
            'duplicatePackageDirectoryBaseNameCount' => count($duplicateDirectoryBaseNames),
            'duplicatePackageDirectoryBaseNames' => $duplicateDirectoryBaseNames,
            'packageDirectoryBaseNames' => array_values($directoryBaseNames),
            'packageCaseFoldDirectoryBaseNameCount' => count($caseFoldDirectoryBaseNameCounts),
            'packageCaseFoldDirectoryBaseNameCounts' => $caseFoldDirectoryBaseNameCounts,
            'entryNamesByPackageCaseFoldDirectoryBaseName' => $entryNamesByCaseFoldDirectoryBaseName,
            'duplicatePackageCaseFoldDirectoryBaseNameCount' => count($duplicateCaseFoldDirectoryBaseNames),
            'duplicatePackageCaseFoldDirectoryBaseNames' => $duplicateCaseFoldDirectoryBaseNames,
            'packageCaseFoldDirectoryBaseNames' => array_values($caseFoldDirectoryBaseNames),
            'packageDirectoryBaseNameStemCount' => count($directoryBaseNameStemCounts),
            'packageDirectoryBaseNameStemCounts' => $directoryBaseNameStemCounts,
            'entryNamesByPackageDirectoryBaseNameStem' => $entryNamesByDirectoryBaseNameStem,
            'duplicatePackageDirectoryBaseNameStemCount' => count($duplicateDirectoryBaseNameStems),
            'duplicatePackageDirectoryBaseNameStems' => $duplicateDirectoryBaseNameStems,
            'packageDirectoryBaseNameStems' => array_values($directoryBaseNameStems),
            'packageCaseFoldDirectoryBaseNameStemCount' => count($caseFoldDirectoryBaseNameStemCounts),
            'packageCaseFoldDirectoryBaseNameStemCounts' => $caseFoldDirectoryBaseNameStemCounts,
            'entryNamesByPackageCaseFoldDirectoryBaseNameStem' => $entryNamesByCaseFoldDirectoryBaseNameStem,
            'duplicatePackageCaseFoldDirectoryBaseNameStemCount' => count($duplicateCaseFoldDirectoryBaseNameStems),
            'duplicatePackageCaseFoldDirectoryBaseNameStems' => $duplicateCaseFoldDirectoryBaseNameStems,
            'packageCaseFoldDirectoryBaseNameStems' => array_values($caseFoldDirectoryBaseNameStems),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array{
     *     packageCaseFoldTopLevelSegmentCount:int,
     *     packageCaseFoldTopLevelSegmentCounts:array<string, int>,
     *     duplicatePackageCaseFoldTopLevelSegmentCount:int,
     *     duplicatePackageCaseFoldTopLevelSegmentEntryCount:int,
     *     duplicatePackageCaseFoldTopLevelSegments:list<string>,
     *     packageCaseFoldTopLevelSegments:list<array<string, mixed>>
     * }
     */
    private static function packageCaseFoldTopLevelSegmentInventory(array $parts): array
    {
        $caseFoldSegmentCounts = [];
        $segments = [];

        foreach ($parts as $name => $part) {
            if (!is_array($part)) {
                continue;
            }

            $entryName = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
            $pathShape = is_array($part['pathShape'] ?? null) ? $part['pathShape'] : self::pathShape($entryName);
            $topLevelSegment = is_string($pathShape['topLevelSegment'] ?? null)
                ? $pathShape['topLevelSegment']
                : '';
            if ($topLevelSegment === '') {
                continue;
            }

            $caseFoldTopLevelSegment = strtolower($topLevelSegment);
            $directory = is_string($pathShape['directory'] ?? null) ? $pathShape['directory'] : null;
            $directoryKey = $directory !== null && $directory !== '' ? $directory : '(root)';
            $basename = is_string($pathShape['basename'] ?? null) ? $pathShape['basename'] : null;
            $pathSegments = is_array($pathShape['segments'] ?? null) ? array_values($pathShape['segments']) : [];
            $pathDepth = is_int($part['packagePathDepth'] ?? null)
                ? $part['packagePathDepth']
                : self::packagePathDepthFromPathShape($pathShape);
            $isDirectory = ($part['isDirectory'] ?? false) === true;
            $byteLength = is_int($part['byteLength'] ?? null) ? $part['byteLength'] : 0;
            $compressedByteLength = is_int($part['compressedByteLength'] ?? null) ? $part['compressedByteLength'] : 0;
            $roles = array_values(array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []));
            $declaredInManifest = ($part['declaredInManifest'] ?? false) === true;
            $undeclared = ($part['undeclared'] ?? false) === true;
            $canExposeBytes = ($part['canExposeBytes'] ?? false) === true;
            $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) ? $part['byteExposurePolicy'] : '';
            $manifestMediaFamily = is_string($part['manifestMediaFamily'] ?? null) ? $part['manifestMediaFamily'] : '';
            $manifestMediaTypeBase = is_string($part['manifestMediaTypeBase'] ?? null) ? $part['manifestMediaTypeBase'] : '';
            if ($manifestMediaFamily === '' && $manifestMediaTypeBase !== '') {
                $manifestMediaFamily = self::packageCaseFoldTopLevelSegmentMediaFamily(
                    $entryName,
                    $manifestMediaTypeBase,
                    $roles,
                    $isDirectory
                );
            }
            $packagePartExtension = is_string($part['packagePartExtension'] ?? null) ? $part['packagePartExtension'] : null;

            $caseFoldSegmentCounts[$caseFoldTopLevelSegment] =
                ($caseFoldSegmentCounts[$caseFoldTopLevelSegment] ?? 0) + 1;
            if (!isset($segments[$caseFoldTopLevelSegment])) {
                $segments[$caseFoldTopLevelSegment] = [
                    'caseFoldTopLevelSegment' => $caseFoldTopLevelSegment,
                    'topLevelSegmentVariantCount' => 0,
                    'entryCount' => 0,
                    'fileEntryCount' => 0,
                    'directoryEntryCount' => 0,
                    'declaredPartCount' => 0,
                    'undeclaredPartCount' => 0,
                    'exposablePartCount' => 0,
                    'blockedPartCount' => 0,
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'topLevelSegmentCounts' => [],
                    'packagePathDepthCounts' => [],
                    'packageDirectoryCounts' => [],
                    'manifestMediaFamilyCounts' => [],
                    'manifestMediaTypeBaseCounts' => [],
                    'roleCounts' => [],
                    'byteExposurePolicyCounts' => [],
                    'packageDirectories' => [],
                    'entryNames' => [],
                    'largestEntry' => null,
                ];
            }

            ++$segments[$caseFoldTopLevelSegment]['entryCount'];
            $segments[$caseFoldTopLevelSegment]['byteLength'] += $byteLength;
            $segments[$caseFoldTopLevelSegment]['compressedByteLength'] += $compressedByteLength;
            $segments[$caseFoldTopLevelSegment]['entryNames'][] = $entryName;
            $segments[$caseFoldTopLevelSegment]['topLevelSegmentCounts'][$topLevelSegment] =
                ($segments[$caseFoldTopLevelSegment]['topLevelSegmentCounts'][$topLevelSegment] ?? 0) + 1;
            $segments[$caseFoldTopLevelSegment]['packagePathDepthCounts'][$pathDepth] =
                ($segments[$caseFoldTopLevelSegment]['packagePathDepthCounts'][$pathDepth] ?? 0) + 1;
            $segments[$caseFoldTopLevelSegment]['packageDirectoryCounts'][$directoryKey] =
                ($segments[$caseFoldTopLevelSegment]['packageDirectoryCounts'][$directoryKey] ?? 0) + 1;
            if ($isDirectory) {
                ++$segments[$caseFoldTopLevelSegment]['directoryEntryCount'];
            } else {
                ++$segments[$caseFoldTopLevelSegment]['fileEntryCount'];
            }
            if ($declaredInManifest) {
                ++$segments[$caseFoldTopLevelSegment]['declaredPartCount'];
            }
            if ($undeclared) {
                ++$segments[$caseFoldTopLevelSegment]['undeclaredPartCount'];
            }
            if ($canExposeBytes) {
                ++$segments[$caseFoldTopLevelSegment]['exposablePartCount'];
            } else {
                ++$segments[$caseFoldTopLevelSegment]['blockedPartCount'];
            }
            foreach ($roles as $role) {
                if ($role !== '') {
                    $segments[$caseFoldTopLevelSegment]['roleCounts'][$role] =
                        ($segments[$caseFoldTopLevelSegment]['roleCounts'][$role] ?? 0) + 1;
                }
            }
            if ($byteExposurePolicy !== '') {
                $segments[$caseFoldTopLevelSegment]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                    ($segments[$caseFoldTopLevelSegment]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
            }
            if ($manifestMediaFamily !== '') {
                $segments[$caseFoldTopLevelSegment]['manifestMediaFamilyCounts'][$manifestMediaFamily] =
                    ($segments[$caseFoldTopLevelSegment]['manifestMediaFamilyCounts'][$manifestMediaFamily] ?? 0) + 1;
            }
            if ($manifestMediaTypeBase !== '') {
                $segments[$caseFoldTopLevelSegment]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] =
                    ($segments[$caseFoldTopLevelSegment]['manifestMediaTypeBaseCounts'][$manifestMediaTypeBase] ?? 0) + 1;
            }

            $entrySummary = [
                'entryName' => $entryName,
                'topLevelSegment' => $topLevelSegment,
                'caseFoldTopLevelSegment' => $caseFoldTopLevelSegment,
                'packageDirectory' => $directory,
                'packageBasename' => $basename,
                'packagePathDepth' => $pathDepth,
                'packagePathSegments' => $pathSegments,
                'packagePartExtension' => $packagePartExtension,
                'byteLength' => $byteLength,
                'compressedByteLength' => $compressedByteLength,
                'crc32' => is_string($part['crc32'] ?? null) ? $part['crc32'] : null,
                'byteSha256' => is_string($part['byteSha256'] ?? null) ? $part['byteSha256'] : null,
                'roles' => $roles,
                'declaredInManifest' => $declaredInManifest,
                'undeclared' => $undeclared,
                'isDirectory' => $isDirectory,
                'canExposeBytes' => $canExposeBytes,
                'byteExposurePolicy' => $byteExposurePolicy === '' ? null : $byteExposurePolicy,
                'manifestMediaTypeBase' => $manifestMediaTypeBase === '' ? null : $manifestMediaTypeBase,
                'manifestMediaFamily' => $manifestMediaFamily === '' ? null : $manifestMediaFamily,
            ];
            $largestEntry = $segments[$caseFoldTopLevelSegment]['largestEntry'];
            if (
                !is_array($largestEntry)
                || $byteLength > (int) ($largestEntry['byteLength'] ?? 0)
                || ($byteLength === (int) ($largestEntry['byteLength'] ?? 0) && strcmp($entryName, (string) ($largestEntry['entryName'] ?? '')) < 0)
            ) {
                $segments[$caseFoldTopLevelSegment]['largestEntry'] = $entrySummary;
            }
        }

        ksort($caseFoldSegmentCounts, SORT_STRING);
        $duplicates = [];
        $duplicateEntryCount = 0;
        ksort($segments, SORT_STRING);
        foreach ($segments as $caseFoldTopLevelSegment => $summary) {
            ksort($summary['topLevelSegmentCounts'], SORT_STRING);
            ksort($summary['packagePathDepthCounts'], SORT_NUMERIC);
            ksort($summary['packageDirectoryCounts'], SORT_STRING);
            ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
            ksort($summary['manifestMediaTypeBaseCounts'], SORT_STRING);
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            sort($summary['entryNames'], SORT_STRING);
            $summary['packageDirectories'] = array_keys($summary['packageDirectoryCounts']);
            sort($summary['packageDirectories'], SORT_STRING);
            $summary['topLevelSegmentVariantCount'] = count($summary['topLevelSegmentCounts']);
            if ($summary['topLevelSegmentVariantCount'] > 1) {
                $duplicates[] = $caseFoldTopLevelSegment;
                $duplicateEntryCount += (int) $summary['entryCount'];
            }
            $segments[$caseFoldTopLevelSegment] = $summary;
        }

        return [
            'packageCaseFoldTopLevelSegmentCount' => count($caseFoldSegmentCounts),
            'packageCaseFoldTopLevelSegmentCounts' => $caseFoldSegmentCounts,
            'duplicatePackageCaseFoldTopLevelSegmentCount' => count($duplicates),
            'duplicatePackageCaseFoldTopLevelSegmentEntryCount' => $duplicateEntryCount,
            'duplicatePackageCaseFoldTopLevelSegments' => $duplicates,
            'packageCaseFoldTopLevelSegments' => array_values($segments),
        ];
    }

    /**
     * @param list<string> $roles
     */
    private static function packageCaseFoldTopLevelSegmentMediaFamily(
        string $entryName,
        string $mediaTypeBase,
        array $roles,
        bool $isDirectory
    ): string {
        $base = strtolower(trim($mediaTypeBase));
        if ($isDirectory) {
            return 'directory';
        }
        if (in_array('script-package', $roles, true)) {
            return 'script';
        }
        if (in_array('configuration-package', $roles, true)) {
            return 'configuration';
        }
        if (in_array('package-thumbnail', $roles, true)) {
            return 'thumbnail';
        }
        if (in_array('package-signature', $roles, true)) {
            return 'signature';
        }
        if (in_array('font-package', $roles, true) || ($base !== '' && self::isFontMediaType($base))) {
            return 'font';
        }
        if (in_array('rdf-metadata', $roles, true) || $base === 'application/rdf+xml') {
            return 'rdf';
        }

        $mediaResourceFamily = self::mediaResourceFamilyFromMediaTypeBase($base);
        if ($mediaResourceFamily !== null) {
            return $mediaResourceFamily;
        }

        $packageMediaResourceFamily = self::mediaResourceFamilyFromPackagePart($entryName);
        if ($base === 'application/octet-stream' && $packageMediaResourceFamily !== null) {
            return $packageMediaResourceFamily;
        }
        if (self::isXmlMediaTypeBase($base)) {
            return 'xml';
        }
        if ($base === '') {
            return 'missing-media-type';
        }
        if ($base === 'application/octet-stream' || str_starts_with($base, 'application/vnd.')) {
            return 'binary';
        }

        return 'other';
    }

    private static function canonicalIdentityValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalIdentityValue($item), $value);
        }

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[(string) $key] = self::canonicalIdentityValue($item);
        }
        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    private static function packageAreaFromPathShape(array $pathShape): string
    {
        $segments = is_array($pathShape['segments'] ?? null) ? $pathShape['segments'] : [];
        if ($segments === []) {
            return '/';
        }

        if (($pathShape['kind'] ?? null) !== 'directory' && count($segments) === 1) {
            return '/';
        }

        $topLevelSegment = $segments[0] ?? null;
        return is_string($topLevelSegment) && $topLevelSegment !== '' ? $topLevelSegment . '/' : '/';
    }

    private static function packagePathDepthFromPathShape(array $pathShape): int
    {
        $segmentCount = $pathShape['segmentCount'] ?? null;
        if (is_int($segmentCount)) {
            return $segmentCount;
        }

        $segments = is_array($pathShape['segments'] ?? null) ? $pathShape['segments'] : [];
        return count($segments);
    }

    private static function recordPackageTopologySummary(
        array &$areaCounts,
        array &$areaByteLengths,
        array &$areaCompressedByteLengths,
        array &$areaSummaries,
        array &$pathsByArea,
        array &$depthCounts,
        array &$pathsByDepth,
        int &$maxDepth,
        string $path,
        string $area,
        int $depth,
        array $roles,
        bool $isDirectory,
        int $byteLength,
        int $compressedByteLength,
        bool $declaredInManifest,
        bool $undeclared,
        bool $canExposeBytes,
        ?string $byteExposurePolicy
    ): void {
        $areaCounts[$area] = ($areaCounts[$area] ?? 0) + 1;
        $areaByteLengths[$area] = ($areaByteLengths[$area] ?? 0) + $byteLength;
        $areaCompressedByteLengths[$area] = ($areaCompressedByteLengths[$area] ?? 0) + $compressedByteLength;
        $pathsByArea[$area] ??= [];
        $pathsByArea[$area][$path] = true;
        $depthCounts[$depth] = ($depthCounts[$depth] ?? 0) + 1;
        $pathsByDepth[$depth] ??= [];
        $pathsByDepth[$depth][$path] = true;
        $maxDepth = max($maxDepth, $depth);

        $areaSummaries[$area] ??= [
            'packageArea' => $area,
            'entryCount' => 0,
            'fileEntryCount' => 0,
            'directoryEntryCount' => 0,
            'byteLength' => 0,
            'compressedByteLength' => 0,
            'declaredEntryCount' => 0,
            'undeclaredEntryCount' => 0,
            'exposableEntryCount' => 0,
            'blockedEntryCount' => 0,
            'roleCounts' => [],
            'byteExposurePolicyCounts' => [],
            'packagePaths' => [],
        ];

        $summary = &$areaSummaries[$area];
        $summary['entryCount']++;
        if ($isDirectory) {
            $summary['directoryEntryCount']++;
        } else {
            $summary['fileEntryCount']++;
        }
        $summary['byteLength'] += $byteLength;
        $summary['compressedByteLength'] += $compressedByteLength;
        if ($declaredInManifest) {
            $summary['declaredEntryCount']++;
        }
        if ($undeclared) {
            $summary['undeclaredEntryCount']++;
        }
        if ($canExposeBytes) {
            $summary['exposableEntryCount']++;
        } else {
            $summary['blockedEntryCount']++;
        }
        foreach ($roles as $role) {
            if (is_string($role) && $role !== '') {
                $summary['roleCounts'][$role] = ($summary['roleCounts'][$role] ?? 0) + 1;
            }
        }
        if ($byteExposurePolicy !== null && $byteExposurePolicy !== '') {
            $summary['byteExposurePolicyCounts'][$byteExposurePolicy] =
                ($summary['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
        }
        $summary['packagePaths'][$path] = true;
        unset($summary);
    }

    private static function sortPackageStringListMap(array &$map, int $keySortFlags): void
    {
        ksort($map, $keySortFlags);
        foreach ($map as &$paths) {
            $paths = array_keys($paths);
            sort($paths, SORT_STRING);
        }
        unset($paths);
    }

    private static function sortPackageNestedCountMap(array &$map, int $keySortFlags = SORT_STRING): void
    {
        ksort($map, $keySortFlags);
        foreach ($map as &$counts) {
            ksort($counts, SORT_STRING);
        }
        unset($counts);
    }

    private static function sortPackageNestedStringListMap(array &$map, int $keySortFlags = SORT_STRING): void
    {
        ksort($map, $keySortFlags);
        foreach ($map as &$nestedMap) {
            self::sortPackageStringListMap($nestedMap, SORT_STRING);
        }
        unset($nestedMap);
    }

    /**
     * @return array{packagePathByteLengthBucket:string,minPackagePathByteLength:int,maxPackagePathByteLength:int|null}
     */
    private static function packagePathByteLengthBucket(int $byteLength): array
    {
        if ($byteLength <= 8) {
            return [
                'packagePathByteLengthBucket' => 'up-to-8-bytes',
                'minPackagePathByteLength' => 0,
                'maxPackagePathByteLength' => 8,
            ];
        }

        if ($byteLength <= 16) {
            return [
                'packagePathByteLengthBucket' => '9-to-16-bytes',
                'minPackagePathByteLength' => 9,
                'maxPackagePathByteLength' => 16,
            ];
        }

        if ($byteLength <= 32) {
            return [
                'packagePathByteLengthBucket' => '17-to-32-bytes',
                'minPackagePathByteLength' => 17,
                'maxPackagePathByteLength' => 32,
            ];
        }

        if ($byteLength <= 64) {
            return [
                'packagePathByteLengthBucket' => '33-to-64-bytes',
                'minPackagePathByteLength' => 33,
                'maxPackagePathByteLength' => 64,
            ];
        }

        return [
            'packagePathByteLengthBucket' => 'over-64-bytes',
            'minPackagePathByteLength' => 65,
            'maxPackagePathByteLength' => null,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function packagePathByteLengthInventory(array $parts): array
    {
        $summaries = [];
        $bucketCounts = [];
        $entryNamesByBucket = [];
        $roleCounts = [];
        $entryNamesByRole = [];
        $byteExposurePolicyCounts = [];
        $entryNamesByByteExposurePolicy = [];

        foreach ($parts as $fallbackName => $part) {
            $entryName = is_string($part['path'] ?? null) && $part['path'] !== ''
                ? $part['path']
                : (is_string($part['part'] ?? null) && $part['part'] !== '' ? $part['part'] : $fallbackName);
            if (!is_string($entryName) || $entryName === '') {
                continue;
            }

            $byteLength = is_int($part['packagePathByteLength'] ?? null)
                ? $part['packagePathByteLength']
                : strlen($entryName);
            $bucket = self::packagePathByteLengthBucket($byteLength);
            $bucketKey = $bucket['packagePathByteLengthBucket'];
            if (!isset($summaries[$bucketKey])) {
                $summaries[$bucketKey] = [
                    'packagePathByteLengthBucket' => $bucketKey,
                    'minPackagePathByteLength' => $bucket['minPackagePathByteLength'],
                    'maxPackagePathByteLength' => $bucket['maxPackagePathByteLength'],
                    'entryCount' => 0,
                    'fileEntryCount' => 0,
                    'directoryEntryCount' => 0,
                    'manifestDeclaredEntryCount' => 0,
                    'undeclaredEntryCount' => 0,
                    'byteLength' => 0,
                    'compressedByteLength' => 0,
                    'sourceRecordBytes' => 0,
                    'longestPackagePathByteLength' => 0,
                    'longestEntryName' => null,
                    'entryNames' => [],
                    'roleCounts' => [],
                    'byteExposurePolicyCounts' => [],
                ];
            }

            ++$summaries[$bucketKey]['entryCount'];
            $bucketCounts[$bucketKey] = ($bucketCounts[$bucketKey] ?? 0) + 1;
            $entryNamesByBucket[$bucketKey][$entryName] = true;
            $summaries[$bucketKey]['entryNames'][$entryName] = true;
            if (($part['isDirectory'] ?? false) === true) {
                ++$summaries[$bucketKey]['directoryEntryCount'];
            } else {
                ++$summaries[$bucketKey]['fileEntryCount'];
            }
            if (($part['declaredInManifest'] ?? false) === true) {
                ++$summaries[$bucketKey]['manifestDeclaredEntryCount'];
            }
            if (($part['undeclared'] ?? false) === true) {
                ++$summaries[$bucketKey]['undeclaredEntryCount'];
            }
            $summaries[$bucketKey]['byteLength'] += (int) ($part['byteLength'] ?? 0);
            $summaries[$bucketKey]['compressedByteLength'] += (int) ($part['compressedByteLength'] ?? 0);
            $summaries[$bucketKey]['sourceRecordBytes'] += (int) ($part['zipSourceRecordBytes'] ?? 0);
            if ($byteLength > $summaries[$bucketKey]['longestPackagePathByteLength']) {
                $summaries[$bucketKey]['longestPackagePathByteLength'] = $byteLength;
                $summaries[$bucketKey]['longestEntryName'] = $entryName;
            }

            foreach (is_array($part['roles'] ?? null) ? $part['roles'] : [] as $role) {
                if (!is_string($role) || $role === '') {
                    continue;
                }

                $summaries[$bucketKey]['roleCounts'][$role] = ($summaries[$bucketKey]['roleCounts'][$role] ?? 0) + 1;
                $roleCounts[$bucketKey][$role] = ($roleCounts[$bucketKey][$role] ?? 0) + 1;
                $entryNamesByRole[$bucketKey][$role][$entryName] = true;
            }

            $byteExposurePolicy = $part['byteExposurePolicy'] ?? null;
            if (is_string($byteExposurePolicy) && $byteExposurePolicy !== '') {
                $summaries[$bucketKey]['byteExposurePolicyCounts'][$byteExposurePolicy] =
                    ($summaries[$bucketKey]['byteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
                $byteExposurePolicyCounts[$bucketKey][$byteExposurePolicy] =
                    ($byteExposurePolicyCounts[$bucketKey][$byteExposurePolicy] ?? 0) + 1;
                $entryNamesByByteExposurePolicy[$bucketKey][$byteExposurePolicy][$entryName] = true;
            }
        }

        $orderedSummaries = [];
        $orderedBuckets = [];
        $orderedBucketCounts = [];
        foreach (['up-to-8-bytes', '9-to-16-bytes', '17-to-32-bytes', '33-to-64-bytes', 'over-64-bytes'] as $bucketKey) {
            if (!isset($summaries[$bucketKey])) {
                continue;
            }

            $summary = $summaries[$bucketKey];
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            $summary['entryNames'] = array_keys($summary['entryNames']);
            sort($summary['entryNames'], SORT_STRING);
            $summary['roleCount'] = count($summary['roleCounts']);
            $summary['roles'] = array_keys($summary['roleCounts']);
            $summary['byteExposurePolicyCount'] = count($summary['byteExposurePolicyCounts']);
            $summary['byteExposurePolicies'] = array_keys($summary['byteExposurePolicyCounts']);
            $orderedSummaries[] = $summary;
            $orderedBuckets[] = $bucketKey;
            $orderedBucketCounts[$bucketKey] = $bucketCounts[$bucketKey] ?? 0;
        }

        self::sortPackageStringListMap($entryNamesByBucket, SORT_STRING);
        self::sortPackageNestedCountMap($roleCounts);
        self::sortPackageNestedStringListMap($entryNamesByRole);
        self::sortPackageNestedCountMap($byteExposurePolicyCounts);
        self::sortPackageNestedStringListMap($entryNamesByByteExposurePolicy);

        return [
            'packagePathByteLengthBucketCount' => count($orderedSummaries),
            'packagePathByteLengthBuckets' => $orderedBuckets,
            'packagePathByteLengthBucketCounts' => $orderedBucketCounts,
            'entryNamesByPackagePathByteLengthBucket' => $entryNamesByBucket,
            'packagePathByteLengthRoleCounts' => $roleCounts,
            'entryNamesByPackagePathByteLengthRole' => $entryNamesByRole,
            'packagePathByteLengthByteExposurePolicyCounts' => $byteExposurePolicyCounts,
            'entryNamesByPackagePathByteLengthByteExposurePolicy' => $entryNamesByByteExposurePolicy,
            'packagePathByteLengthBucketSummaries' => $orderedSummaries,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function packageExtraFieldInventory(array $parts): array
    {
        $extraFieldIds = [];
        $entryNamesByExtraFieldId = [];
        $extraFieldIdRoleCounts = [];
        $entryNamesByExtraFieldIdRole = [];
        $extraFieldIdManifestMediaFamilyCounts = [];
        $entryNamesByExtraFieldIdManifestMediaFamily = [];
        $centralOnlyExtraFieldIds = [];
        $centralOnlyExtraFieldIdRoleCounts = [];
        $entryNamesByCentralOnlyExtraFieldIdRole = [];
        $centralOnlyExtraFieldIdManifestMediaFamilyCounts = [];
        $entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily = [];
        $localOnlyExtraFieldIds = [];
        $localOnlyExtraFieldIdRoleCounts = [];
        $entryNamesByLocalOnlyExtraFieldIdRole = [];
        $localOnlyExtraFieldIdManifestMediaFamilyCounts = [];
        $entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily = [];
        $mismatchedExtraFieldValueIds = [];
        $mismatchedExtraFieldValueIdRoleCounts = [];
        $entryNamesByMismatchedExtraFieldValueIdRole = [];
        $mismatchedExtraFieldValueIdManifestMediaFamilyCounts = [];
        $entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily = [];
        $recordDimensions = static function (
            array &$counts,
            array &$entryNames,
            int $id,
            array $dimensions,
            string $entryName
        ): void {
            foreach ($dimensions as $dimension) {
                if (!is_string($dimension) || $dimension === '') {
                    continue;
                }

                $counts[$id][$dimension] = ($counts[$id][$dimension] ?? 0) + 1;
                $entryNames[$id][$dimension][$entryName] = true;
            }
        };

        foreach ($parts as $fallbackName => $part) {
            $entryName = is_string($part['path'] ?? null) && $part['path'] !== ''
                ? $part['path']
                : (is_string($part['part'] ?? null) && $part['part'] !== '' ? $part['part'] : $fallbackName);
            if (!is_string($entryName) || $entryName === '') {
                continue;
            }

            $roles = [];
            foreach (is_array($part['roles'] ?? null) ? $part['roles'] : [] as $role) {
                if (is_string($role) && $role !== '') {
                    $roles[$role] = true;
                }
            }
            $roles = array_keys($roles);
            sort($roles, SORT_STRING);

            $manifestMediaFamilies = [];
            if (is_string($part['manifestMediaFamily'] ?? null) && $part['manifestMediaFamily'] !== '') {
                $manifestMediaFamilies[] = $part['manifestMediaFamily'];
            }

            foreach (self::zipPreflightIntegerList($part, 'zipExtraFieldIds') as $id) {
                $extraFieldIds[$id] = true;
                $entryNamesByExtraFieldId[$id][$entryName] = true;
                $recordDimensions($extraFieldIdRoleCounts, $entryNamesByExtraFieldIdRole, $id, $roles, $entryName);
                $recordDimensions(
                    $extraFieldIdManifestMediaFamilyCounts,
                    $entryNamesByExtraFieldIdManifestMediaFamily,
                    $id,
                    $manifestMediaFamilies,
                    $entryName
                );
            }
            foreach (self::zipPreflightIntegerList($part, 'centralOnlyExtraFieldIds') as $id) {
                $centralOnlyExtraFieldIds[$id] = true;
                $recordDimensions($centralOnlyExtraFieldIdRoleCounts, $entryNamesByCentralOnlyExtraFieldIdRole, $id, $roles, $entryName);
                $recordDimensions(
                    $centralOnlyExtraFieldIdManifestMediaFamilyCounts,
                    $entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily,
                    $id,
                    $manifestMediaFamilies,
                    $entryName
                );
            }
            foreach (self::zipPreflightIntegerList($part, 'localOnlyExtraFieldIds') as $id) {
                $localOnlyExtraFieldIds[$id] = true;
                $recordDimensions($localOnlyExtraFieldIdRoleCounts, $entryNamesByLocalOnlyExtraFieldIdRole, $id, $roles, $entryName);
                $recordDimensions(
                    $localOnlyExtraFieldIdManifestMediaFamilyCounts,
                    $entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily,
                    $id,
                    $manifestMediaFamilies,
                    $entryName
                );
            }
            foreach (self::zipPreflightIntegerList($part, 'mismatchedExtraFieldValueIds') as $id) {
                $mismatchedExtraFieldValueIds[$id] = true;
                $recordDimensions($mismatchedExtraFieldValueIdRoleCounts, $entryNamesByMismatchedExtraFieldValueIdRole, $id, $roles, $entryName);
                $recordDimensions(
                    $mismatchedExtraFieldValueIdManifestMediaFamilyCounts,
                    $entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily,
                    $id,
                    $manifestMediaFamilies,
                    $entryName
                );
            }
        }

        ksort($extraFieldIds, SORT_NUMERIC);
        self::sortPackageStringListMap($entryNamesByExtraFieldId, SORT_NUMERIC);
        self::sortPackageNestedCountMap($extraFieldIdRoleCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByExtraFieldIdRole, SORT_NUMERIC);
        self::sortPackageNestedCountMap($extraFieldIdManifestMediaFamilyCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByExtraFieldIdManifestMediaFamily, SORT_NUMERIC);
        self::sortPackageNestedCountMap($centralOnlyExtraFieldIdRoleCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByCentralOnlyExtraFieldIdRole, SORT_NUMERIC);
        self::sortPackageNestedCountMap($centralOnlyExtraFieldIdManifestMediaFamilyCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily, SORT_NUMERIC);
        self::sortPackageNestedCountMap($localOnlyExtraFieldIdRoleCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByLocalOnlyExtraFieldIdRole, SORT_NUMERIC);
        self::sortPackageNestedCountMap($localOnlyExtraFieldIdManifestMediaFamilyCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily, SORT_NUMERIC);
        self::sortPackageNestedCountMap($mismatchedExtraFieldValueIdRoleCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByMismatchedExtraFieldValueIdRole, SORT_NUMERIC);
        self::sortPackageNestedCountMap($mismatchedExtraFieldValueIdManifestMediaFamilyCounts, SORT_NUMERIC);
        self::sortPackageNestedStringListMap($entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily, SORT_NUMERIC);

        $summaries = [];
        foreach (array_keys($extraFieldIds) as $id) {
            $roleCounts = $extraFieldIdRoleCounts[$id] ?? [];
            $manifestMediaFamilyCounts = $extraFieldIdManifestMediaFamilyCounts[$id] ?? [];
            $roles = array_keys($roleCounts);
            $manifestMediaFamilies = array_keys($manifestMediaFamilyCounts);
            sort($roles, SORT_STRING);
            sort($manifestMediaFamilies, SORT_STRING);
            $summaries[] = [
                'extraFieldId' => $id,
                'extraFieldIdHex' => sprintf('0x%04x', $id),
                'entryCount' => count($entryNamesByExtraFieldId[$id] ?? []),
                'entryNames' => $entryNamesByExtraFieldId[$id] ?? [],
                'roleCount' => count($roleCounts),
                'roles' => $roles,
                'roleCounts' => $roleCounts,
                'manifestMediaFamilyCount' => count($manifestMediaFamilyCounts),
                'manifestMediaFamilies' => $manifestMediaFamilies,
                'manifestMediaFamilyCounts' => $manifestMediaFamilyCounts,
                'centralOnlyRoleCounts' => $centralOnlyExtraFieldIdRoleCounts[$id] ?? [],
                'localOnlyRoleCounts' => $localOnlyExtraFieldIdRoleCounts[$id] ?? [],
                'mismatchedValueRoleCounts' => $mismatchedExtraFieldValueIdRoleCounts[$id] ?? [],
            ];
        }

        return [
            'extraFieldIdRoleCount' => count($extraFieldIdRoleCounts),
            'extraFieldIdRoleCounts' => $extraFieldIdRoleCounts,
            'entryNamesByExtraFieldIdRole' => $entryNamesByExtraFieldIdRole,
            'extraFieldIdManifestMediaFamilyCount' => count($extraFieldIdManifestMediaFamilyCounts),
            'extraFieldIdManifestMediaFamilyCounts' => $extraFieldIdManifestMediaFamilyCounts,
            'entryNamesByExtraFieldIdManifestMediaFamily' => $entryNamesByExtraFieldIdManifestMediaFamily,
            'centralOnlyExtraFieldIdRoleCount' => count($centralOnlyExtraFieldIdRoleCounts),
            'centralOnlyExtraFieldIdRoleCounts' => $centralOnlyExtraFieldIdRoleCounts,
            'entryNamesByCentralOnlyExtraFieldIdRole' => $entryNamesByCentralOnlyExtraFieldIdRole,
            'centralOnlyExtraFieldIdManifestMediaFamilyCount' => count($centralOnlyExtraFieldIdManifestMediaFamilyCounts),
            'centralOnlyExtraFieldIdManifestMediaFamilyCounts' => $centralOnlyExtraFieldIdManifestMediaFamilyCounts,
            'entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily' => $entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily,
            'localOnlyExtraFieldIdRoleCount' => count($localOnlyExtraFieldIdRoleCounts),
            'localOnlyExtraFieldIdRoleCounts' => $localOnlyExtraFieldIdRoleCounts,
            'entryNamesByLocalOnlyExtraFieldIdRole' => $entryNamesByLocalOnlyExtraFieldIdRole,
            'localOnlyExtraFieldIdManifestMediaFamilyCount' => count($localOnlyExtraFieldIdManifestMediaFamilyCounts),
            'localOnlyExtraFieldIdManifestMediaFamilyCounts' => $localOnlyExtraFieldIdManifestMediaFamilyCounts,
            'entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily' => $entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily,
            'mismatchedExtraFieldValueIdRoleCount' => count($mismatchedExtraFieldValueIdRoleCounts),
            'mismatchedExtraFieldValueIdRoleCounts' => $mismatchedExtraFieldValueIdRoleCounts,
            'entryNamesByMismatchedExtraFieldValueIdRole' => $entryNamesByMismatchedExtraFieldValueIdRole,
            'mismatchedExtraFieldValueIdManifestMediaFamilyCount' => count($mismatchedExtraFieldValueIdManifestMediaFamilyCounts),
            'mismatchedExtraFieldValueIdManifestMediaFamilyCounts' => $mismatchedExtraFieldValueIdManifestMediaFamilyCounts,
            'entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily' => $entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily,
            'extraFieldIdRoleSummaryCount' => count($summaries),
            'extraFieldIdRoleSummaries' => $summaries,
        ];
    }

    /**
     * @param list<string> $roles
     */
    private static function recordPackagePathDepthInventory(
        array &$roleCounts,
        array &$entryNamesByRole,
        array &$byteExposurePolicyCounts,
        array &$entryNamesByByteExposurePolicy,
        int $depth,
        string $entryName,
        array $roles,
        ?string $byteExposurePolicy
    ): void {
        foreach ($roles as $role) {
            if (!is_string($role) || $role === '') {
                continue;
            }

            $roleCounts[$depth] ??= [];
            $roleCounts[$depth][$role] = ($roleCounts[$depth][$role] ?? 0) + 1;
            $entryNamesByRole[$depth] ??= [];
            $entryNamesByRole[$depth][$role] ??= [];
            $entryNamesByRole[$depth][$role][$entryName] = true;
        }

        if ($byteExposurePolicy !== null && $byteExposurePolicy !== '') {
            $byteExposurePolicyCounts[$depth] ??= [];
            $byteExposurePolicyCounts[$depth][$byteExposurePolicy] =
                ($byteExposurePolicyCounts[$depth][$byteExposurePolicy] ?? 0) + 1;
            $entryNamesByByteExposurePolicy[$depth] ??= [];
            $entryNamesByByteExposurePolicy[$depth][$byteExposurePolicy] ??= [];
            $entryNamesByByteExposurePolicy[$depth][$byteExposurePolicy][$entryName] = true;
        }
    }

    /**
     * @param list<array<string, mixed>> $positionReviews
     * @param list<string> $roles
     */
    private static function recordZipPackageManifestPathSegmentPositionInventory(
        array &$roleCounts,
        array &$entryNamesByRole,
        array &$byteExposurePolicyCounts,
        array &$entryNamesByByteExposurePolicy,
        array $positionReviews,
        string $entryName,
        array $roles,
        ?string $byteExposurePolicy
    ): void {
        $positions = [];
        foreach ($positionReviews as $review) {
            $position = is_array($review) && is_string($review['position'] ?? null)
                ? $review['position']
                : '';
            if ($position !== '') {
                $positions[$position] = true;
            }
        }

        foreach (array_keys($positions) as $position) {
            foreach ($roles as $role) {
                if (!is_string($role) || $role === '') {
                    continue;
                }

                $roleCounts[$position] ??= [];
                $roleCounts[$position][$role] = ($roleCounts[$position][$role] ?? 0) + 1;
                $entryNamesByRole[$position] ??= [];
                $entryNamesByRole[$position][$role] ??= [];
                $entryNamesByRole[$position][$role][$entryName] = true;
            }

            if ($byteExposurePolicy !== null && $byteExposurePolicy !== '') {
                $byteExposurePolicyCounts[$position] ??= [];
                $byteExposurePolicyCounts[$position][$byteExposurePolicy] =
                    ($byteExposurePolicyCounts[$position][$byteExposurePolicy] ?? 0) + 1;
                $entryNamesByByteExposurePolicy[$position] ??= [];
                $entryNamesByByteExposurePolicy[$position][$byteExposurePolicy] ??= [];
                $entryNamesByByteExposurePolicy[$position][$byteExposurePolicy][$entryName] = true;
            }
        }
    }

    private static function finalizePackageAreaSummaries(array $summaries): array
    {
        ksort($summaries, SORT_STRING);
        foreach ($summaries as &$summary) {
            ksort($summary['roleCounts'], SORT_STRING);
            ksort($summary['byteExposurePolicyCounts'], SORT_STRING);
            $summary['packagePaths'] = array_keys($summary['packagePaths']);
            sort($summary['packagePaths'], SORT_STRING);
        }
        unset($summary);

        return array_values($summaries);
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param array<string, array<string, mixed>> $packageParts
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function manifestPackageCoverageProvenance(array $manifestEntries, array $packageParts, array $undeclaredPackageEntries): array
    {
        $manifestReferences = [];
        $manifestPackageReferencePaths = [];
        $existingPackageReferencePaths = [];
        $coveredPackageReferencePaths = [];
        $missingPackageReferencePaths = [];
        $directoryPackageReferencePaths = [];
        $virtualDirectoryPackageReferencePaths = [];
        $packagePaths = [];
        $declaredZipEntryPaths = [];
        $undeclaredZipEntryPaths = [];
        $manifestPackageReferenceMediaFamilyCounts = [];
        $manifestPackageMissingReferenceMediaFamilyCounts = [];
        $manifestPackageReferenceByteExposurePolicyCounts = [];
        $manifestPackageMissingReferenceByteExposurePolicyCounts = [];
        $manifestPackageFileReferenceCount = 0;
        $manifestPackageDirectoryReferenceCount = 0;
        $manifestPackageExistingReferenceCount = 0;
        $manifestPackageCoveredReferenceCount = 0;
        $manifestPackageMissingReferenceCount = 0;
        $manifestPackageVirtualDirectoryReferenceCount = 0;
        $packageFileEntryCount = 0;
        $packageDirectoryEntryCount = 0;
        $packageDeclaredZipEntryCount = 0;

        foreach ($packageParts as $name => $part) {
            $path = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
            $packagePaths[] = $path;
            if (($part['isDirectory'] ?? false) === true) {
                ++$packageDirectoryEntryCount;
            } else {
                ++$packageFileEntryCount;
            }
            if (($part['declaredInManifest'] ?? false) === true) {
                ++$packageDeclaredZipEntryCount;
                $declaredZipEntryPaths[] = $path;
            }
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $path = $entry['path'] ?? null;
            if (is_string($path) && $path !== '') {
                $undeclaredZipEntryPaths[] = $path;
            }
        }

        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '') {
                continue;
            }

            $manifestPath = is_string($entry['path'] ?? null) ? $entry['path'] : $packagePath;
            $isDirectory = ($entry['isDirectory'] ?? false) === true || str_ends_with($packagePath, '/');
            $exists = ($entry['exists'] ?? false) === true;
            $hasZipEntry = isset($packageParts[$packagePath]);
            $manifestPackageReferencePaths[] = $packagePath;
            if ($isDirectory) {
                ++$manifestPackageDirectoryReferenceCount;
                $directoryPackageReferencePaths[] = $packagePath;
            } else {
                ++$manifestPackageFileReferenceCount;
            }
            if ($exists) {
                ++$manifestPackageExistingReferenceCount;
                $existingPackageReferencePaths[] = $packagePath;
            }
            if ($hasZipEntry) {
                ++$manifestPackageCoveredReferenceCount;
                $coveredPackageReferencePaths[] = $packagePath;
            }
            if (!$exists) {
                ++$manifestPackageMissingReferenceCount;
                $missingPackageReferencePaths[] = $packagePath;
            }
            if ($isDirectory && $exists && !$hasZipEntry) {
                ++$manifestPackageVirtualDirectoryReferenceCount;
                $virtualDirectoryPackageReferencePaths[] = $packagePath;
            }

            $part = is_array($packageParts[$packagePath] ?? null) ? $packageParts[$packagePath] : null;
            $mediaFamily = is_string($entry['manifestMediaFamily'] ?? null)
                ? $entry['manifestMediaFamily']
                : (is_array($part) && is_string($part['manifestMediaFamily'] ?? null) ? $part['manifestMediaFamily'] : null);
            if (is_string($mediaFamily) && $mediaFamily !== '') {
                $manifestPackageReferenceMediaFamilyCounts[$mediaFamily] =
                    ($manifestPackageReferenceMediaFamilyCounts[$mediaFamily] ?? 0) + 1;
                if (!$exists) {
                    $manifestPackageMissingReferenceMediaFamilyCounts[$mediaFamily] =
                        ($manifestPackageMissingReferenceMediaFamilyCounts[$mediaFamily] ?? 0) + 1;
                }
            }

            $byteExposurePolicy = is_string($entry['byteExposurePolicy'] ?? null) ? $entry['byteExposurePolicy'] : null;
            if (is_string($byteExposurePolicy) && $byteExposurePolicy !== '') {
                $manifestPackageReferenceByteExposurePolicyCounts[$byteExposurePolicy] =
                    ($manifestPackageReferenceByteExposurePolicyCounts[$byteExposurePolicy] ?? 0) + 1;
                if (!$exists) {
                    $manifestPackageMissingReferenceByteExposurePolicyCounts[$byteExposurePolicy] =
                        ($manifestPackageMissingReferenceByteExposurePolicyCounts[$byteExposurePolicy] ?? 0) + 1;
                }
            }

            $manifestReferences[] = self::withoutEmptyValues([
                'manifestIndex' => $entry['manifestIndex'] ?? null,
                'manifestPath' => $manifestPath,
                'packagePath' => $packagePath,
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'hasZipEntry' => $hasZipEntry,
                'virtualDirectoryReference' => $isDirectory && $exists && !$hasZipEntry,
                'missingPackageReference' => !$exists,
                'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
                'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
                'byteExposurePolicy' => $byteExposurePolicy,
                'roles' => is_array($part) ? ($part['roles'] ?? []) : [],
            ]);
        }

        $sortStringList = static function (array $items): array {
            $items = array_values(array_unique(array_filter(
                $items,
                static fn (mixed $item): bool => is_string($item) && $item !== ''
            )));
            sort($items, SORT_STRING);

            return $items;
        };

        $issueCodes = [];
        if ($manifestPackageMissingReferenceCount > 0) {
            $issueCodes[] = 'missing-manifest-declared-package-references';
        }
        if ($undeclaredZipEntryPaths !== []) {
            $issueCodes[] = 'undeclared-zip-package-entries';
        }

        $manifestPackageReferenceCount = count($manifestPackageReferencePaths);
        ksort($manifestPackageReferenceMediaFamilyCounts, SORT_STRING);
        ksort($manifestPackageMissingReferenceMediaFamilyCounts, SORT_STRING);
        ksort($manifestPackageReferenceByteExposurePolicyCounts, SORT_STRING);
        ksort($manifestPackageMissingReferenceByteExposurePolicyCounts, SORT_STRING);

        return [
            'present' => true,
            'manifestPackageReferenceCount' => $manifestPackageReferenceCount,
            'manifestPackageFileReferenceCount' => $manifestPackageFileReferenceCount,
            'manifestPackageDirectoryReferenceCount' => $manifestPackageDirectoryReferenceCount,
            'manifestPackageExistingReferenceCount' => $manifestPackageExistingReferenceCount,
            'manifestPackageCoveredReferenceCount' => $manifestPackageCoveredReferenceCount,
            'manifestPackageMissingReferenceCount' => $manifestPackageMissingReferenceCount,
            'manifestPackageVirtualDirectoryReferenceCount' => $manifestPackageVirtualDirectoryReferenceCount,
            'manifestPackageCoverageComplete' => $manifestPackageMissingReferenceCount === 0,
            'manifestPackageZipCoverageComplete' => $manifestPackageCoveredReferenceCount === ($manifestPackageReferenceCount - $manifestPackageVirtualDirectoryReferenceCount),
            'manifestPackageReferencePaths' => $sortStringList($manifestPackageReferencePaths),
            'manifestPackageExistingReferencePaths' => $sortStringList($existingPackageReferencePaths),
            'manifestPackageCoveredReferencePaths' => $sortStringList($coveredPackageReferencePaths),
            'manifestPackageMissingReferencePaths' => $sortStringList($missingPackageReferencePaths),
            'manifestPackageDirectoryReferencePaths' => $sortStringList($directoryPackageReferencePaths),
            'manifestPackageVirtualDirectoryReferencePaths' => $sortStringList($virtualDirectoryPackageReferencePaths),
            'manifestPackageReferenceMediaFamilyCounts' => $manifestPackageReferenceMediaFamilyCounts,
            'manifestPackageMissingReferenceMediaFamilyCounts' => $manifestPackageMissingReferenceMediaFamilyCounts,
            'manifestPackageReferenceByteExposurePolicyCounts' => $manifestPackageReferenceByteExposurePolicyCounts,
            'manifestPackageMissingReferenceByteExposurePolicyCounts' => $manifestPackageMissingReferenceByteExposurePolicyCounts,
            'packageEntryCount' => count($packageParts),
            'packageFileEntryCount' => $packageFileEntryCount,
            'packageDirectoryEntryCount' => $packageDirectoryEntryCount,
            'packageDeclaredZipEntryCount' => $packageDeclaredZipEntryCount,
            'packageUndeclaredZipEntryCount' => count($undeclaredZipEntryPaths),
            'packageEntryPaths' => $sortStringList($packagePaths),
            'packageDeclaredZipEntryPaths' => $sortStringList($declaredZipEntryPaths),
            'packageUndeclaredZipEntryPaths' => $sortStringList($undeclaredZipEntryPaths),
            'issueCount' => count($issueCodes),
            'issueCodes' => $issueCodes,
            'byteExposurePolicy' => 'odf-manifest-package-coverage-metadata-only',
            'canExposeBytes' => false,
            'manifestReferences' => $manifestReferences,
        ];
    }

    /**
     * @return array{rawNameHex:string,nameEncoding:string,rawNameMatchesDecodedName:bool,usesLegacyNameEncoding:bool,usesUnicodePathExtraField:bool,hasRawNameProvenance:bool}
     */
    private static function zipEntryRawNameProvenance(ZipPackageEntry $entry): array
    {
        $rawNameMatchesDecodedName = $entry->rawName === $entry->name;
        $usesLegacyNameEncoding = $entry->nameEncoding === 'cp437';
        $usesUnicodePathExtraField = $entry->nameEncoding === 'info-zip-unicode-path';

        return [
            'rawNameHex' => bin2hex($entry->rawName),
            'nameEncoding' => $entry->nameEncoding,
            'rawNameMatchesDecodedName' => $rawNameMatchesDecodedName,
            'usesLegacyNameEncoding' => $usesLegacyNameEncoding,
            'usesUnicodePathExtraField' => $usesUnicodePathExtraField,
            'hasRawNameProvenance' => !$rawNameMatchesDecodedName
                || $usesLegacyNameEncoding
                || $usesUnicodePathExtraField,
        ];
    }

    /**
     * @param array<string, mixed> $modificationTimes
     * @return array<string, array<string, mixed>>
     */
    private static function zipModificationTimeEntriesByName(array $modificationTimes): array
    {
        $entriesByName = [];
        foreach (is_array($modificationTimes['entries'] ?? null) ? $modificationTimes['entries'] : [] as $entry) {
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $entriesByName[$name] = $entry;
            }
        }

        return $entriesByName;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, array<string, mixed>>
     */
    private static function zipPreflightEntriesByName(array $summary): array
    {
        $entriesByName = [];
        foreach (is_array($summary['entries'] ?? null) ? $summary['entries'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $entriesByName[$name] = $entry;
            }
        }

        return $entriesByName;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, array<string, mixed>>
     */
    private static function zipLocalHeaderMetadataEntriesByName(array $summary): array
    {
        $entriesByName = [];
        foreach (is_array($summary['entries'] ?? null) ? $summary['entries'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = $entry['centralName'] ?? null;
            if (is_string($name) && $name !== '') {
                $entriesByName[$name] = $entry;
            }
        }

        return $entriesByName;
    }

    /**
     * @param array<string, mixed> $summary
     * @return list<array<string, mixed>>
     */
    private static function zipLocalHeaderMetadataMismatchProvenance(array $summary): array
    {
        $items = [];
        foreach (is_array($summary['mismatchedEntries'] ?? null) ? $summary['mismatchedEntries'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $part = $entry['centralName'] ?? null;
            if (!is_string($part) || $part === '') {
                continue;
            }

            $items[] = self::withoutEmptyValues([
                'path' => $part,
                'centralDirectoryIndex' => $entry['centralDirectoryIndex'] ?? null,
                'localHeaderOffset' => $entry['localHeaderOffset'] ?? null,
                'issues' => is_array($entry['issues'] ?? null) ? $entry['issues'] : [],
                'centralVersionNeededToExtract' => $entry['centralVersionNeededToExtract'] ?? null,
                'localVersionNeededToExtract' => $entry['localVersionNeededToExtract'] ?? null,
                'centralGeneralPurposeFlags' => $entry['centralGeneralPurposeFlags'] ?? null,
                'localGeneralPurposeFlags' => $entry['localGeneralPurposeFlags'] ?? null,
                'centralCompressionMethod' => $entry['centralCompressionMethod'] ?? null,
                'localCompressionMethod' => $entry['localCompressionMethod'] ?? null,
                'usesDataDescriptor' => ($entry['usesDataDescriptor'] ?? false) === true,
                'hasZeroLocalHeaderPlaceholders' => $entry['hasZeroLocalHeaderPlaceholders'] ?? null,
            ]);
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>
     */
    private static function zipLocalHeaderMetadataProvenance(?array $entry): array
    {
        if ($entry === null) {
            return [
                'zipLocalHeaderMetadataMatchesCentralDirectory' => null,
                'zipLocalHeaderMetadataIssues' => [],
            ];
        }

        return [
            'zipLocalHeaderMetadataMatchesCentralDirectory' => ($entry['hasMetadataMismatch'] ?? false) !== true,
            'zipLocalHeaderMetadataIssues' => is_array($entry['issues'] ?? null) ? $entry['issues'] : [],
            'zipLocalFixedHeaderOffset' => $entry['localFixedHeaderOffset'] ?? null,
            'zipLocalFixedHeaderBytes' => $entry['localFixedHeaderLength'] ?? null,
            'zipLocalFixedHeaderEnd' => $entry['localHeaderEnd'] ?? null,
            'zipLocalVariableFieldsOffset' => $entry['localVariableFieldsOffset'] ?? null,
            'zipLocalVariableFieldsBytes' => $entry['localVariableFieldsLength'] ?? null,
            'zipLocalRawNameOffset' => $entry['localRawNameOffset'] ?? null,
            'zipLocalRawNameBytes' => $entry['localRawNameLength'] ?? null,
            'zipLocalExtraFieldOffset' => $entry['localExtraFieldOffset'] ?? null,
            'zipLocalExtraFieldBytes' => $entry['localExtraFieldLength'] ?? null,
            'zipCentralVersionNeededToExtract' => $entry['centralVersionNeededToExtract'] ?? null,
            'zipLocalVersionNeededToExtract' => $entry['localVersionNeededToExtract'] ?? null,
            'zipCentralGeneralPurposeFlags' => $entry['centralGeneralPurposeFlags'] ?? null,
            'zipLocalGeneralPurposeFlags' => $entry['localGeneralPurposeFlags'] ?? null,
            'zipCentralCompressionMethod' => $entry['centralCompressionMethod'] ?? null,
            'zipLocalCompressionMethod' => $entry['localCompressionMethod'] ?? null,
            'zipCentralModifiedDosTime' => $entry['centralModifiedDosTime'] ?? null,
            'zipLocalModifiedDosTime' => $entry['localModifiedDosTime'] ?? null,
            'zipCentralModifiedDosDate' => $entry['centralModifiedDosDate'] ?? null,
            'zipLocalModifiedDosDate' => $entry['localModifiedDosDate'] ?? null,
            'zipCentralCrc32' => $entry['centralCrc32'] ?? null,
            'zipLocalCrc32' => $entry['localCrc32'] ?? null,
            'zipCentralCompressedSize' => $entry['centralCompressedSize'] ?? null,
            'zipLocalCompressedSize' => $entry['localCompressedSize'] ?? null,
            'zipCentralUncompressedSize' => $entry['centralUncompressedSize'] ?? null,
            'zipLocalUncompressedSize' => $entry['localUncompressedSize'] ?? null,
            'zipLocalHeaderUsesDataDescriptor' => ($entry['usesDataDescriptor'] ?? false) === true,
            'zipLocalHeaderHasZeroDataDescriptorPlaceholders' => $entry['hasZeroLocalHeaderPlaceholders'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>
     */
    private static function zipPackageManifestEntrySourceProvenance(?array $entry): array
    {
        if ($entry === null) {
            return [];
        }

        $expansionRatioBucket = self::zipPackageManifestExpansionRatioBucket($entry['expansionRatio'] ?? null);
        $localRecordBytes = is_int($entry['localRecordBytes'] ?? null) ? $entry['localRecordBytes'] : null;
        $centralDirectoryRecordBytes = is_int($entry['centralDirectoryRecordBytes'] ?? null)
            ? $entry['centralDirectoryRecordBytes']
            : null;
        $sourceRecordBytes = $localRecordBytes !== null || $centralDirectoryRecordBytes !== null
            ? (int) ($localRecordBytes ?? 0) + (int) ($centralDirectoryRecordBytes ?? 0)
            : null;

        return [
            'zipPackageManifestEntry' => $entry,
            'zipPackageManifestCompressionMethodName' => $entry['compressionMethodName'] ?? null,
            'zipPackageManifestCrc32Hex' => $entry['crc32Hex'] ?? null,
            'zipPackageManifestCompressedSize' => $entry['compressedSize'] ?? null,
            'zipPackageManifestUncompressedSize' => $entry['uncompressedSize'] ?? null,
            'zipPackageManifestExpansionRatio' => $entry['expansionRatio'] ?? null,
            'zipPackageManifestExpansionRatioBucket' => $expansionRatioBucket['expansionRatioBucket'],
            'zipPackageManifestExpansionRatioBucketMin' => $expansionRatioBucket['minExpansionRatio'],
            'zipPackageManifestExpansionRatioBucketMax' => $expansionRatioBucket['maxExpansionRatio'],
            'zipPackageManifestVersionMadeBy' => $entry['versionMadeBy'] ?? null,
            'zipPackageManifestMadeByHostSystem' => $entry['madeByHostSystem'] ?? null,
            'zipPackageManifestMadeByHostSystemName' => $entry['madeByHostSystemName'] ?? null,
            'zipPackageManifestMadeByVersion' => $entry['madeByVersion'] ?? null,
            'zipPackageManifestVersionNeededToExtract' => $entry['versionNeededToExtract'] ?? null,
            'zipPackageManifestCreatorVersionMeetsNeeded' => $entry['creatorVersionMeetsNeeded'] ?? null,
            'zipPackageManifestCreatorVersionComparison' => $entry['creatorVersionComparison'] ?? null,
            'zipPackageManifestCreatorVersionDelta' => $entry['creatorVersionDelta'] ?? null,
            'zipPackageManifestCreatorHostSystemIsKnown' => $entry['creatorHostSystemIsKnown'] ?? null,
            'zipPackageManifestCreatorHostSystemIssues' => is_array($entry['creatorHostSystemIssues'] ?? null) ? $entry['creatorHostSystemIssues'] : [],
            'zipPackageManifestCaseFoldKey' => $entry['caseFoldKey'] ?? null,
            'zipPackageManifestCaseInsensitiveEquivalentEntryNames' => is_array($entry['caseInsensitiveEquivalentEntryNames'] ?? null) ? $entry['caseInsensitiveEquivalentEntryNames'] : [],
            'zipPackageManifestHasCaseInsensitiveNameCollision' => ($entry['hasCaseInsensitiveNameCollision'] ?? false) === true,
            'zipPackageManifestCaseInsensitiveNameCollisionIssues' => is_array($entry['caseInsensitiveNameCollisionIssues'] ?? null) ? $entry['caseInsensitiveNameCollisionIssues'] : [],
            'zipPackageManifestDirectoryRoot' => $entry['directoryRoot'] ?? null,
            'zipPackageManifestPathSegments' => is_array($entry['pathSegments'] ?? null) ? $entry['pathSegments'] : [],
            'zipPackageManifestPathSegmentCount' => $entry['pathSegmentCount'] ?? null,
            'zipPackageManifestPathSegmentPositionReviews' => is_array($entry['pathSegmentPositionReviews'] ?? null) ? $entry['pathSegmentPositionReviews'] : [],
            'zipPackageManifestDirectoryDepth' => $entry['directoryDepth'] ?? null,
            'zipPackageManifestPackagePartBaseName' => $entry['packagePartBaseName'] ?? null,
            'zipPackageManifestPackagePartCaseFoldBaseName' => $entry['packagePartCaseFoldBaseName'] ?? null,
            'zipPackageManifestPackagePartBaseNameStem' => $entry['packagePartBaseNameStem'] ?? null,
            'zipPackageManifestPackagePartCaseFoldBaseNameStem' => $entry['packagePartCaseFoldBaseNameStem'] ?? null,
            'zipPackageManifestPackagePartExtension' => $entry['packagePartExtension'] ?? null,
            'zipPackageManifestPackagePartExtensionKey' => $entry['packagePartExtensionKey'] ?? null,
            'zipPackageManifestExtensionlessPackagePart' => ($entry['extensionlessPackagePart'] ?? false) === true,
            'zipPackageManifestCentralDirectoryIndex' => $entry['centralDirectoryIndex'] ?? null,
            'zipPackageManifestLocalHeaderOrder' => $entry['localHeaderOrder'] ?? null,
            'zipLocalHeaderOffset' => $entry['localHeaderOffset'] ?? null,
            'zipLocalHeaderBytes' => $entry['localHeaderLength'] ?? null,
            'zipLocalHeaderSha256' => $entry['localHeaderSha256'] ?? null,
            'zipLocalHeaderFixedHeaderBytes' => $entry['localHeaderFixedHeaderBytes'] ?? null,
            'zipLocalHeaderVariableFieldOffset' => $entry['localHeaderVariableFieldOffset'] ?? null,
            'zipLocalHeaderVariableFieldBytes' => $entry['localHeaderVariableFieldBytes'] ?? null,
            'zipLocalHeaderVariableFieldSha256' => $entry['localHeaderVariableFieldSha256'] ?? null,
            'zipLocalHeaderRawNameOffset' => $entry['localHeaderRawNameOffset'] ?? null,
            'zipLocalHeaderRawNameBytes' => $entry['localHeaderRawNameBytes'] ?? null,
            'zipLocalHeaderRawNameSha256' => $entry['localHeaderRawNameSha256'] ?? null,
            'zipLocalHeaderExtraFieldOffset' => $entry['localHeaderExtraFieldOffset'] ?? null,
            'zipLocalHeaderExtraFieldBytes' => $entry['localHeaderExtraFieldBytes'] ?? null,
            'zipLocalHeaderExtraFieldSha256' => $entry['localHeaderExtraFieldSha256'] ?? null,
            'zipLocalHeaderReviewFieldBytes' => $entry['localHeaderReviewFieldBytes'] ?? null,
            'zipLocalRecordOffset' => $entry['localRecordOffset'] ?? null,
            'zipLocalRecordBytes' => $localRecordBytes,
            'zipLocalRecordEnd' => $entry['localRecordEnd'] ?? null,
            'zipLocalRecordSha256' => $entry['localRecordSha256'] ?? null,
            'zipCompressedDataOffset' => $entry['compressedDataOffset'] ?? null,
            'zipCompressedDataBytes' => $entry['compressedSize'] ?? null,
            'zipCompressedDataEnd' => $entry['compressedDataEnd'] ?? null,
            'zipCompressedDataSha256' => $entry['compressedDataSha256'] ?? null,
            'zipUsesDataDescriptor' => ($entry['usesDataDescriptor'] ?? false) === true,
            'zipDataDescriptorOffset' => $entry['dataDescriptorOffset'] ?? null,
            'zipDataDescriptorBytes' => $entry['dataDescriptorBytes'] ?? 0,
            'zipDataDescriptorEnd' => $entry['dataDescriptorEnd'] ?? null,
            'zipDataDescriptorSha256' => $entry['dataDescriptorSha256'] ?? null,
            'zipCentralDirectoryRecordOffset' => $entry['centralDirectoryRecordOffset'] ?? null,
            'zipCentralDirectoryRecordEnd' => $entry['centralDirectoryRecordEnd'] ?? null,
            'zipCentralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
            'zipCentralDirectoryRecordSha256' => $entry['centralDirectoryRecordSha256'] ?? null,
            'zipCentralDirectoryFixedHeaderBytes' => $entry['centralDirectoryFixedHeaderBytes'] ?? null,
            'zipCentralDirectoryVariableFieldOffset' => $entry['centralDirectoryVariableFieldOffset'] ?? null,
            'zipCentralDirectoryVariableFieldBytes' => $entry['centralDirectoryVariableFieldBytes'] ?? null,
            'zipCentralDirectoryVariableFieldSha256' => $entry['centralDirectoryVariableFieldSha256'] ?? null,
            'zipCentralDirectoryRawNameOffset' => $entry['centralDirectoryRawNameOffset'] ?? null,
            'zipCentralDirectoryRawNameBytes' => $entry['centralDirectoryRawNameBytes'] ?? null,
            'zipCentralDirectoryRawNameSha256' => $entry['centralDirectoryRawNameSha256'] ?? null,
            'zipCentralDirectoryExtraFieldOffset' => $entry['centralDirectoryExtraFieldOffset'] ?? null,
            'zipCentralDirectoryExtraFieldBytes' => $entry['centralDirectoryExtraFieldBytes'] ?? null,
            'zipCentralDirectoryExtraFieldSha256' => $entry['centralDirectoryExtraFieldSha256'] ?? null,
            'zipCentralDirectoryRawCommentOffset' => $entry['centralDirectoryRawCommentOffset'] ?? null,
            'zipCentralDirectoryRawCommentBytes' => $entry['centralDirectoryRawCommentBytes'] ?? null,
            'zipCentralDirectoryRawCommentSha256' => $entry['centralDirectoryRawCommentSha256'] ?? null,
            'zipCentralDirectoryReviewFieldBytes' => $entry['centralDirectoryReviewFieldBytes'] ?? null,
            'zipSourceRecordBytes' => $sourceRecordBytes,
            'zipHasSourceRecordProvenance' => is_string($entry['localRecordSha256'] ?? null)
                && is_string($entry['centralDirectoryRecordSha256'] ?? null),
        ];
    }

    /**
     * @return array{expansionRatioBucket:string,minExpansionRatio:?float,maxExpansionRatio:?float}
     */
    private static function zipPackageManifestExpansionRatioBucket(mixed $expansionRatio): array
    {
        $ratio = is_int($expansionRatio) || is_float($expansionRatio)
            ? (float) $expansionRatio
            : null;
        if ($ratio === null) {
            return [
                'expansionRatioBucket' => 'unknown',
                'minExpansionRatio' => null,
                'maxExpansionRatio' => null,
            ];
        }

        if ($ratio <= 0.0) {
            return [
                'expansionRatioBucket' => 'zero-byte',
                'minExpansionRatio' => 0.0,
                'maxExpansionRatio' => 0.0,
            ];
        }

        if ($ratio <= 1.0) {
            return [
                'expansionRatioBucket' => 'up-to-1x',
                'minExpansionRatio' => 0.0,
                'maxExpansionRatio' => 1.0,
            ];
        }

        if ($ratio <= 10.0) {
            return [
                'expansionRatioBucket' => '1x-to-10x',
                'minExpansionRatio' => 1.0,
                'maxExpansionRatio' => 10.0,
            ];
        }

        if ($ratio <= 100.0) {
            return [
                'expansionRatioBucket' => '10x-to-100x',
                'minExpansionRatio' => 10.0,
                'maxExpansionRatio' => 100.0,
            ];
        }

        return [
            'expansionRatioBucket' => 'over-100x',
            'minExpansionRatio' => 100.0,
            'maxExpansionRatio' => null,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private static function zipPackageManifestAggregateProvenance(array $manifest): array
    {
        return [
            'zipPackageManifestVersion' => $manifest['manifestVersion'] ?? null,
            'zipPackageManifestPackageSource' => is_array($manifest['packageSource'] ?? null) ? $manifest['packageSource'] : [],
            'zipPackageManifestArchiveBytes' => $manifest['archiveBytes'] ?? ($manifest['archiveLength'] ?? 0),
            'zipPackageManifestArchiveLength' => $manifest['archiveLength'] ?? ($manifest['archiveBytes'] ?? 0),
            'zipPackageManifestArchiveSha256' => $manifest['archiveSha256'] ?? null,
            'zipPackageManifestEntryCount' => $manifest['entryCount'] ?? 0,
            'zipPackageManifestFileEntryCount' => $manifest['fileEntryCount'] ?? 0,
            'zipPackageManifestDirectoryEntryCount' => $manifest['directoryEntryCount'] ?? 0,
            'zipPackageManifestCompressedBytes' => $manifest['compressedBytes'] ?? 0,
            'zipPackageManifestUncompressedBytes' => $manifest['uncompressedBytes'] ?? 0,
            'zipPackageManifestExpansionRatio' => $manifest['expansionRatio'] ?? null,
            'zipPackageManifestLargestEntry' => is_array($manifest['largestEntry'] ?? null) ? $manifest['largestEntry'] : null,
            'zipPackageManifestZeroByteEntryCount' => $manifest['zeroByteEntryCount'] ?? 0,
            'zipPackageManifestZeroByteFileCount' => $manifest['zeroByteFileCount'] ?? 0,
            'zipPackageManifestEmptyDirectoryEntryCount' => $manifest['emptyDirectoryEntryCount'] ?? 0,
            'zipPackageManifestHasZeroByteEntries' => ($manifest['hasZeroByteEntries'] ?? false) === true,
            'zipPackageManifestZeroByteEntries' => is_array($manifest['zeroByteEntries'] ?? null) ? $manifest['zeroByteEntries'] : [],
            'zipPackageManifestUnknownExpansionRatioEntryCount' => $manifest['unknownExpansionRatioEntryCount'] ?? 0,
            'zipPackageManifestHasUnknownExpansionRatioEntries' => ($manifest['hasUnknownExpansionRatioEntries'] ?? false) === true,
            'zipPackageManifestUnknownExpansionRatioEntries' => is_array($manifest['unknownExpansionRatioEntries'] ?? null) ? $manifest['unknownExpansionRatioEntries'] : [],
            'zipPackageManifestExpansionRatioBucketSummaryCount' => $manifest['expansionRatioBucketSummaryCount'] ?? 0,
            'zipPackageManifestExpansionRatioBuckets' => is_array($manifest['expansionRatioBuckets'] ?? null) ? $manifest['expansionRatioBuckets'] : [],
            'zipPackageManifestExpansionRatioBucketSummaries' => is_array($manifest['expansionRatioBucketSummaries'] ?? null) ? $manifest['expansionRatioBucketSummaries'] : [],
            'zipPackageManifestLocalHeaderBytes' => $manifest['localHeaderBytes'] ?? 0,
            'zipPackageManifestLocalHeaderFixedHeaderBytes' => $manifest['localHeaderFixedHeaderBytes'] ?? 0,
            'zipPackageManifestLocalHeaderVariableFieldBytes' => $manifest['localHeaderVariableFieldBytes'] ?? 0,
            'zipPackageManifestLocalHeaderRawNameBytes' => $manifest['localHeaderRawNameBytes'] ?? 0,
            'zipPackageManifestLocalHeaderExtraFieldBytes' => $manifest['localHeaderExtraFieldBytes'] ?? 0,
            'zipPackageManifestLocalHeaderReviewFieldBytes' => $manifest['localHeaderReviewFieldBytes'] ?? 0,
            'zipPackageManifestLocalExtraFieldEntryCount' => $manifest['localExtraFieldEntryCount'] ?? 0,
            'zipPackageManifestHasLocalHeaderReviewFields' => ($manifest['hasLocalHeaderReviewFields'] ?? false) === true,
            'zipPackageManifestLocalRecordBytes' => $manifest['localRecordBytes'] ?? 0,
            'zipPackageManifestDataDescriptorEntryCount' => $manifest['dataDescriptorEntryCount'] ?? 0,
            'zipPackageManifestDataDescriptorBytes' => $manifest['dataDescriptorBytes'] ?? 0,
            'zipPackageManifestStoredEntryCount' => $manifest['storedEntryCount'] ?? 0,
            'zipPackageManifestDeflatedEntryCount' => $manifest['deflatedEntryCount'] ?? 0,
            'zipPackageManifestUnsupportedCompressionMethodCount' => $manifest['unsupportedCompressionMethodCount'] ?? 0,
            'zipPackageManifestCentralDirectoryOffset' => $manifest['centralDirectoryOffset'] ?? null,
            'zipPackageManifestCentralDirectoryBytes' => $manifest['centralDirectoryBytes'] ?? 0,
            'zipPackageManifestCentralDirectoryEnd' => $manifest['centralDirectoryEnd'] ?? null,
            'zipPackageManifestCentralDirectorySha256' => $manifest['centralDirectorySha256'] ?? null,
            'zipPackageManifestCentralDirectoryToEocdGapOffset' => $manifest['centralDirectoryToEocdGapOffset'] ?? null,
            'zipPackageManifestCentralDirectoryToEocdGapBytes' => $manifest['centralDirectoryToEocdGapBytes'] ?? 0,
            'zipPackageManifestCentralDirectoryToEocdGapSha256' => $manifest['centralDirectoryToEocdGapSha256'] ?? null,
            'zipPackageManifestEndOfCentralDirectoryOffset' => $manifest['endOfCentralDirectoryOffset'] ?? null,
            'zipPackageManifestEndOfCentralDirectoryBytes' => $manifest['endOfCentralDirectoryBytes'] ?? 0,
            'zipPackageManifestEndOfCentralDirectoryEnd' => $manifest['endOfCentralDirectoryEnd'] ?? null,
            'zipPackageManifestEndOfCentralDirectorySha256' => $manifest['endOfCentralDirectorySha256'] ?? null,
            'zipPackageManifestCentralDirectoryRecordBytes' => $manifest['centralDirectoryRecordBytes'] ?? 0,
            'zipPackageManifestCentralDirectoryFixedHeaderBytes' => $manifest['centralDirectoryFixedHeaderBytes'] ?? 0,
            'zipPackageManifestCentralDirectoryVariableFieldBytes' => $manifest['centralDirectoryVariableFieldBytes'] ?? 0,
            'zipPackageManifestCentralDirectoryRawNameBytes' => $manifest['centralDirectoryRawNameBytes'] ?? 0,
            'zipPackageManifestCentralDirectoryExtraFieldBytes' => $manifest['centralDirectoryExtraFieldBytes'] ?? 0,
            'zipPackageManifestCentralDirectoryRawCommentBytes' => $manifest['centralDirectoryRawCommentBytes'] ?? 0,
            'zipPackageManifestCentralDirectoryReviewFieldBytes' => $manifest['centralDirectoryReviewFieldBytes'] ?? 0,
            'zipPackageManifestSourceRecordBytes' => $manifest['sourceRecordBytes'] ?? 0,
            'zipPackageManifestCentralExtraFieldEntryCount' => $manifest['centralExtraFieldEntryCount'] ?? 0,
            'zipPackageManifestEntryCommentCount' => $manifest['entryCommentCount'] ?? 0,
            'zipPackageManifestHasPackageComment' => ($manifest['hasPackageComment'] ?? false) === true,
            'zipPackageManifestPackageCommentOffset' => $manifest['packageCommentOffset'] ?? null,
            'zipPackageManifestPackageCommentBytes' => $manifest['packageCommentBytes'] ?? 0,
            'zipPackageManifestPackageCommentSha256' => $manifest['packageCommentSha256'] ?? null,
            'zipPackageManifestHasCentralDirectorySignature' => ($manifest['hasCentralDirectorySignature'] ?? false) === true,
            'zipPackageManifestCentralDirectorySignatureOffset' => $manifest['centralDirectorySignatureOffset'] ?? null,
            'zipPackageManifestCentralDirectorySignatureDataOffset' => $manifest['centralDirectorySignatureDataOffset'] ?? null,
            'zipPackageManifestCentralDirectorySignatureEnd' => $manifest['centralDirectorySignatureEnd'] ?? null,
            'zipPackageManifestCentralDirectorySignatureBytes' => $manifest['centralDirectorySignatureBytes'] ?? 0,
            'zipPackageManifestCentralDirectorySignatureRecordBytes' => $manifest['centralDirectorySignatureRecordBytes'] ?? 0,
            'zipPackageManifestCentralDirectorySignaturePreviewHex' => $manifest['centralDirectorySignaturePreviewHex'] ?? '',
            'zipPackageManifestCentralDirectorySignaturePreviewByteCount' => $manifest['centralDirectorySignaturePreviewByteCount'] ?? 0,
            'zipPackageManifestCentralDirectorySignatureSha256' => $manifest['centralDirectorySignatureSha256'] ?? null,
            'zipPackageManifestCentralDirectorySignatureLocation' => $manifest['centralDirectorySignatureLocation'] ?? null,
            'zipPackageManifestCentralDirectorySignatureVerification' => $manifest['centralDirectorySignatureVerification'] ?? 'not-present',
            'zipPackageManifestCentralDirectorySignatureByteExposurePolicy' => $manifest['centralDirectorySignatureByteExposurePolicy'] ?? 'not-present',
            'zipPackageManifestCentralDirectorySignatureCanExposeBytes' => ($manifest['centralDirectorySignatureCanExposeBytes'] ?? false) === true,
            'zipPackageManifestHasCentralDirectoryReviewFields' => ($manifest['hasCentralDirectoryReviewFields'] ?? false) === true,
            'zipPackageManifestMaxPathSegmentCount' => $manifest['maxPathSegmentCount'] ?? 0,
            'zipPackageManifestMaxDirectoryDepth' => $manifest['maxDirectoryDepth'] ?? 0,
            'zipPackageManifestDeepestEntryNames' => is_array($manifest['deepestEntryNames'] ?? null) ? $manifest['deepestEntryNames'] : [],
            'zipPackageManifestDeepestEntryNameCount' => is_array($manifest['deepestEntryNames'] ?? null) ? count($manifest['deepestEntryNames']) : 0,
            'zipPackageManifestPathSegmentPositionSummaryCount' => $manifest['pathSegmentPositionSummaryCount'] ?? 0,
            'zipPackageManifestPathSegmentPositionOccurrenceCount' => $manifest['pathSegmentPositionOccurrenceCount'] ?? 0,
            'zipPackageManifestPathSegmentPositionCounts' => is_array($manifest['pathSegmentPositionCounts'] ?? null) ? $manifest['pathSegmentPositionCounts'] : [],
            'zipPackageManifestPathSegmentPositionEntryCounts' => is_array($manifest['pathSegmentPositionEntryCounts'] ?? null) ? $manifest['pathSegmentPositionEntryCounts'] : [],
            'zipPackageManifestPathSegmentPositionSummaries' => is_array($manifest['pathSegmentPositionSummaries'] ?? null) ? $manifest['pathSegmentPositionSummaries'] : [],
            'zipPackageManifestCaseFoldPathSegmentSummaryCount' => $manifest['caseFoldPathSegmentSummaryCount'] ?? 0,
            'zipPackageManifestCaseFoldPathSegments' => is_array($manifest['caseFoldPathSegments'] ?? null) ? $manifest['caseFoldPathSegments'] : [],
            'zipPackageManifestCaseFoldPathSegmentOccurrenceCount' => $manifest['caseFoldPathSegmentOccurrenceCount'] ?? 0,
            'zipPackageManifestCaseFoldPathSegmentCounts' => is_array($manifest['caseFoldPathSegmentCounts'] ?? null) ? $manifest['caseFoldPathSegmentCounts'] : [],
            'zipPackageManifestCaseFoldPathSegmentEntryCounts' => is_array($manifest['caseFoldPathSegmentEntryCounts'] ?? null) ? $manifest['caseFoldPathSegmentEntryCounts'] : [],
            'zipPackageManifestCaseFoldPathSegmentSummaries' => is_array($manifest['caseFoldPathSegmentSummaries'] ?? null) ? $manifest['caseFoldPathSegmentSummaries'] : [],
            'zipPackageManifestCaseInsensitiveNameCollisionGroupCount' => $manifest['caseInsensitiveNameCollisionGroupCount'] ?? 0,
            'zipPackageManifestCaseInsensitiveNameCollisionEntryCount' => $manifest['caseInsensitiveNameCollisionEntryCount'] ?? 0,
            'zipPackageManifestHasCaseInsensitiveNameCollisions' => ($manifest['hasCaseInsensitiveNameCollisions'] ?? false) === true,
            'zipPackageManifestCaseInsensitiveNameCollisionGroups' => is_array($manifest['caseInsensitiveNameCollisionGroups'] ?? null) ? $manifest['caseInsensitiveNameCollisionGroups'] : [],
            'zipPackageManifestCaseInsensitiveNameCollisionEntries' => is_array($manifest['caseInsensitiveNameCollisionEntries'] ?? null) ? $manifest['caseInsensitiveNameCollisionEntries'] : [],
            'zipPackageManifestCompressionMethodSummaryCount' => $manifest['compressionMethodSummaryCount'] ?? 0,
            'zipPackageManifestCompressionMethodSummaries' => is_array($manifest['compressionMethodSummaries'] ?? null) ? $manifest['compressionMethodSummaries'] : [],
            'zipPackageManifestGeneralPurposeFlagSummaryCount' => $manifest['generalPurposeFlagSummaryCount'] ?? 0,
            'zipPackageManifestGeneralPurposeUtf8NameEntryCount' => $manifest['generalPurposeUtf8NameEntryCount'] ?? 0,
            'zipPackageManifestGeneralPurposeDataDescriptorEntryCount' => $manifest['generalPurposeDataDescriptorEntryCount'] ?? 0,
            'zipPackageManifestGeneralPurposeDeflateOptionEntryCount' => $manifest['generalPurposeDeflateOptionEntryCount'] ?? 0,
            'zipPackageManifestGeneralPurposeFlagSummaries' => is_array($manifest['generalPurposeFlagSummaries'] ?? null) ? $manifest['generalPurposeFlagSummaries'] : [],
            'zipPackageManifestCreatorHostSystemSummaryCount' => $manifest['creatorHostSystemSummaryCount'] ?? 0,
            'zipPackageManifestKnownCreatorHostSystemEntryCount' => $manifest['knownCreatorHostSystemEntryCount'] ?? 0,
            'zipPackageManifestUnknownCreatorHostSystemEntryCount' => $manifest['unknownCreatorHostSystemEntryCount'] ?? 0,
            'zipPackageManifestCreatorVersionMeetsNeededEntryCount' => $manifest['creatorVersionMeetsNeededEntryCount'] ?? 0,
            'zipPackageManifestCreatorVersionBelowNeededEntryCount' => $manifest['creatorVersionBelowNeededEntryCount'] ?? 0,
            'zipPackageManifestCreatorVersionEqualNeededEntryCount' => $manifest['creatorVersionEqualNeededEntryCount'] ?? 0,
            'zipPackageManifestCreatorVersionAboveNeededEntryCount' => $manifest['creatorVersionAboveNeededEntryCount'] ?? 0,
            'zipPackageManifestCreatorVersionBelowNeededKnownHostEntryCount' => $manifest['creatorVersionBelowNeededKnownHostEntryCount'] ?? 0,
            'zipPackageManifestCreatorVersionBelowNeededUnknownHostEntryCount' => $manifest['creatorVersionBelowNeededUnknownHostEntryCount'] ?? 0,
            'zipPackageManifestHasUnknownCreatorHostSystems' => ($manifest['hasUnknownCreatorHostSystems'] ?? false) === true,
            'zipPackageManifestHasCreatorVersionBelowNeededEntries' => ($manifest['hasCreatorVersionBelowNeededEntries'] ?? false) === true,
            'zipPackageManifestCreatorVersionComparisonCounts' => is_array($manifest['creatorVersionComparisonCounts'] ?? null) ? $manifest['creatorVersionComparisonCounts'] : [],
            'zipPackageManifestCreatorHostSystemSummaries' => is_array($manifest['creatorHostSystemSummaries'] ?? null) ? $manifest['creatorHostSystemSummaries'] : [],
            'zipPackageManifestUnknownCreatorHostSystemEntries' => is_array($manifest['unknownCreatorHostSystemEntries'] ?? null) ? $manifest['unknownCreatorHostSystemEntries'] : [],
            'zipPackageManifestCreatorVersionBelowNeededEntries' => is_array($manifest['creatorVersionBelowNeededEntries'] ?? null) ? $manifest['creatorVersionBelowNeededEntries'] : [],
            'zipPackageManifestDirectoryRootCount' => $manifest['directoryRootCount'] ?? 0,
            'zipPackageManifestDirectoryRoots' => is_array($manifest['directoryRoots'] ?? null) ? $manifest['directoryRoots'] : [],
            'zipPackageManifestDirectoryRootSummaries' => is_array($manifest['directoryRootSummaries'] ?? null) ? $manifest['directoryRootSummaries'] : [],
            'zipPackageManifestExtensionlessPackagePartCount' => $manifest['extensionlessPackagePartCount'] ?? 0,
            'zipPackageManifestHasExtensionlessPackageParts' => ($manifest['hasExtensionlessPackageParts'] ?? false) === true,
            'zipPackageManifestPackagePartExtensionSummaryCount' => $manifest['packagePartExtensionSummaryCount'] ?? 0,
            'zipPackageManifestPackagePartExtensions' => is_array($manifest['packagePartExtensions'] ?? null) ? $manifest['packagePartExtensions'] : [],
            'zipPackageManifestPackagePartExtensionSummaries' => is_array($manifest['packagePartExtensionSummaries'] ?? null) ? $manifest['packagePartExtensionSummaries'] : [],
            'zipPackageManifestPackagePartBaseNameSummaryCount' => $manifest['packagePartBaseNameSummaryCount'] ?? 0,
            'zipPackageManifestPackagePartBaseNames' => is_array($manifest['packagePartBaseNames'] ?? null) ? $manifest['packagePartBaseNames'] : [],
            'zipPackageManifestPackagePartBaseNameSummaries' => is_array($manifest['packagePartBaseNameSummaries'] ?? null) ? $manifest['packagePartBaseNameSummaries'] : [],
            'zipPackageManifestDuplicatePackagePartBaseNameCount' => $manifest['duplicatePackagePartBaseNameCount'] ?? 0,
            'zipPackageManifestHasDuplicatePackagePartBaseNames' => ($manifest['hasDuplicatePackagePartBaseNames'] ?? false) === true,
            'zipPackageManifestDuplicatePackagePartBaseNames' => is_array($manifest['duplicatePackagePartBaseNames'] ?? null) ? $manifest['duplicatePackagePartBaseNames'] : [],
            'zipPackageManifestDuplicatePackagePartBaseNameSummaries' => is_array($manifest['duplicatePackagePartBaseNameSummaries'] ?? null) ? $manifest['duplicatePackagePartBaseNameSummaries'] : [],
            'zipPackageManifestPackagePartCaseFoldBaseNameSummaryCount' => $manifest['packagePartCaseFoldBaseNameSummaryCount'] ?? 0,
            'zipPackageManifestPackagePartCaseFoldBaseNames' => is_array($manifest['packagePartCaseFoldBaseNames'] ?? null) ? $manifest['packagePartCaseFoldBaseNames'] : [],
            'zipPackageManifestPackagePartCaseFoldBaseNameSummaries' => is_array($manifest['packagePartCaseFoldBaseNameSummaries'] ?? null) ? $manifest['packagePartCaseFoldBaseNameSummaries'] : [],
            'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameCount' => $manifest['duplicatePackagePartCaseFoldBaseNameCount'] ?? 0,
            'zipPackageManifestHasDuplicatePackagePartCaseFoldBaseNames' => ($manifest['hasDuplicatePackagePartCaseFoldBaseNames'] ?? false) === true,
            'zipPackageManifestDuplicatePackagePartCaseFoldBaseNames' => is_array($manifest['duplicatePackagePartCaseFoldBaseNames'] ?? null) ? $manifest['duplicatePackagePartCaseFoldBaseNames'] : [],
            'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameSummaries' => is_array($manifest['duplicatePackagePartCaseFoldBaseNameSummaries'] ?? null) ? $manifest['duplicatePackagePartCaseFoldBaseNameSummaries'] : [],
            'zipPackageManifestPackagePartBaseNameStemSummaryCount' => $manifest['packagePartBaseNameStemSummaryCount'] ?? 0,
            'zipPackageManifestPackagePartBaseNameStems' => is_array($manifest['packagePartBaseNameStems'] ?? null) ? $manifest['packagePartBaseNameStems'] : [],
            'zipPackageManifestPackagePartBaseNameStemSummaries' => is_array($manifest['packagePartBaseNameStemSummaries'] ?? null) ? $manifest['packagePartBaseNameStemSummaries'] : [],
            'zipPackageManifestDuplicatePackagePartBaseNameStemCount' => $manifest['duplicatePackagePartBaseNameStemCount'] ?? 0,
            'zipPackageManifestHasDuplicatePackagePartBaseNameStems' => ($manifest['hasDuplicatePackagePartBaseNameStems'] ?? false) === true,
            'zipPackageManifestDuplicatePackagePartBaseNameStems' => is_array($manifest['duplicatePackagePartBaseNameStems'] ?? null) ? $manifest['duplicatePackagePartBaseNameStems'] : [],
            'zipPackageManifestDuplicatePackagePartBaseNameStemSummaries' => is_array($manifest['duplicatePackagePartBaseNameStemSummaries'] ?? null) ? $manifest['duplicatePackagePartBaseNameStemSummaries'] : [],
            'zipPackageManifestPackagePartCaseFoldBaseNameStemSummaryCount' => $manifest['packagePartCaseFoldBaseNameStemSummaryCount'] ?? 0,
            'zipPackageManifestPackagePartCaseFoldBaseNameStems' => is_array($manifest['packagePartCaseFoldBaseNameStems'] ?? null) ? $manifest['packagePartCaseFoldBaseNameStems'] : [],
            'zipPackageManifestPackagePartCaseFoldBaseNameStemSummaries' => is_array($manifest['packagePartCaseFoldBaseNameStemSummaries'] ?? null) ? $manifest['packagePartCaseFoldBaseNameStemSummaries'] : [],
            'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameStemCount' => $manifest['duplicatePackagePartCaseFoldBaseNameStemCount'] ?? 0,
            'zipPackageManifestHasDuplicatePackagePartCaseFoldBaseNameStems' => ($manifest['hasDuplicatePackagePartCaseFoldBaseNameStems'] ?? false) === true,
            'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameStems' => is_array($manifest['duplicatePackagePartCaseFoldBaseNameStems'] ?? null) ? $manifest['duplicatePackagePartCaseFoldBaseNameStems'] : [],
            'zipPackageManifestDuplicatePackagePartCaseFoldBaseNameStemSummaries' => is_array($manifest['duplicatePackagePartCaseFoldBaseNameStemSummaries'] ?? null) ? $manifest['duplicatePackagePartCaseFoldBaseNameStemSummaries'] : [],
            'zipPackageManifestCentralDirectoryOrderNames' => is_array($manifest['centralDirectoryOrderNames'] ?? null) ? $manifest['centralDirectoryOrderNames'] : [],
            'zipPackageManifestLocalHeaderOrderNames' => is_array($manifest['localHeaderOrderNames'] ?? null) ? $manifest['localHeaderOrderNames'] : [],
            'zipPackageManifestCentralDirectoryOrderMatchesLocalHeaderOrder' => ($manifest['centralDirectoryOrderMatchesLocalHeaderOrder'] ?? false) === true,
        ];
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return list<int>
     */
    private static function zipPreflightIntegerList(?array $entry, string $key): array
    {
        if (!is_array($entry[$key] ?? null)) {
            return [];
        }

        $values = [];
        foreach ($entry[$key] as $value) {
            if (is_int($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param list<int> $ids
     * @return list<string>
     */
    private static function zipExtraFieldIdHexes(array $ids): array
    {
        $hexes = [];
        foreach ($ids as $id) {
            $hexes[] = sprintf('0x%04x', $id);
        }

        return $hexes;
    }

    /**
     * @param array<string, mixed> $extraFields
     * @return list<string>
     */
    private static function zipExtraFieldUsageIdHexes(array $extraFields, ?string $presenceKey = null): array
    {
        if (!is_array($extraFields['extraFieldIdUsage'] ?? null)) {
            return [];
        }

        $hexes = [];
        foreach ($extraFields['extraFieldIdUsage'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($presenceKey !== null && ($row[$presenceKey] ?? false) !== true) {
                continue;
            }
            if (is_string($row['idHex'] ?? null) && $row['idHex'] !== '') {
                $hexes[] = $row['idHex'];
                continue;
            }
            if (is_int($row['id'] ?? null)) {
                $hexes[] = sprintf('0x%04x', $row['id']);
            }
        }

        return $hexes;
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>
     */
    private static function zipGeneralPurposeFlagProvenance(?array $entry): array
    {
        if ($entry === null) {
            return [
                'zipGeneralPurposeFlagNames' => [],
                'zipUnsupportedGeneralPurposeFlagBits' => 0,
                'zipGeneralPurposeFlagsSupported' => true,
                'zipUsesUtf8Names' => false,
                'zipGeneralPurposeUsesDataDescriptor' => false,
                'zipDeflateOptionFlags' => 0,
                'zipGeneralPurposeRequiresStrictReview' => false,
                'zipGeneralPurposeFlagIssues' => [],
            ];
        }

        return [
            'zipGeneralPurposeFlags' => $entry['generalPurposeFlags'] ?? null,
            'zipGeneralPurposeFlagNames' => is_array($entry['flagNames'] ?? null) ? $entry['flagNames'] : [],
            'zipUnsupportedGeneralPurposeFlagBits' => $entry['unsupportedFlagBits'] ?? 0,
            'zipGeneralPurposeFlagsSupported' => ($entry['isSupportedByReader'] ?? false) === true,
            'zipUsesUtf8Names' => ($entry['usesUtf8Names'] ?? false) === true,
            'zipGeneralPurposeUsesDataDescriptor' => ($entry['usesDataDescriptor'] ?? false) === true,
            'zipDeflateOptionFlags' => $entry['deflateOptionFlags'] ?? 0,
            'zipDeflateOptionName' => $entry['deflateOptionName'] ?? null,
            'zipGeneralPurposeRequiresStrictReview' => ($entry['requiresStrictReview'] ?? false) === true,
            'zipGeneralPurposeFlagIssues' => is_array($entry['issues'] ?? null) ? $entry['issues'] : [],
        ];
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>
     */
    private static function zipExtraFieldProvenance(?array $entry): array
    {
        $centralExtraFieldIds = self::zipPreflightIntegerList($entry, 'centralExtraFieldIds');
        $localExtraFieldIds = self::zipPreflightIntegerList($entry, 'localExtraFieldIds');
        $zipExtraFieldIds = array_values(array_unique(array_merge($centralExtraFieldIds, $localExtraFieldIds)));
        sort($zipExtraFieldIds, SORT_NUMERIC);
        $centralOnlyExtraFieldIds = self::zipPreflightIntegerList($entry, 'centralOnlyExtraFieldIds');
        $localOnlyExtraFieldIds = self::zipPreflightIntegerList($entry, 'localOnlyExtraFieldIds');
        $duplicateCentralExtraFieldIds = self::zipPreflightIntegerList($entry, 'duplicateCentralExtraFieldIds');
        $duplicateLocalExtraFieldIds = self::zipPreflightIntegerList($entry, 'duplicateLocalExtraFieldIds');
        $mismatchedExtraFieldValueIds = self::zipPreflightIntegerList($entry, 'mismatchedExtraFieldValueIds');

        return [
            'zipExtraFieldIds' => $zipExtraFieldIds,
            'zipExtraFieldIdHexes' => self::zipExtraFieldIdHexes($zipExtraFieldIds),
            'extraFieldIdCount' => count($zipExtraFieldIds),
            'centralExtraFieldIds' => $centralExtraFieldIds,
            'centralExtraFieldIdHexes' => self::zipExtraFieldIdHexes($centralExtraFieldIds),
            'localExtraFieldIds' => $localExtraFieldIds,
            'localExtraFieldIdHexes' => self::zipExtraFieldIdHexes($localExtraFieldIds),
            'centralExtraFieldRecordCount' => count($centralExtraFieldIds),
            'localExtraFieldRecordCount' => count($localExtraFieldIds),
            'duplicateCentralExtraFieldIds' => $duplicateCentralExtraFieldIds,
            'duplicateCentralExtraFieldIdHexes' => self::zipExtraFieldIdHexes($duplicateCentralExtraFieldIds),
            'duplicateLocalExtraFieldIds' => $duplicateLocalExtraFieldIds,
            'duplicateLocalExtraFieldIdHexes' => self::zipExtraFieldIdHexes($duplicateLocalExtraFieldIds),
            'centralOnlyExtraFieldIds' => $centralOnlyExtraFieldIds,
            'centralOnlyExtraFieldIdHexes' => self::zipExtraFieldIdHexes($centralOnlyExtraFieldIds),
            'localOnlyExtraFieldIds' => $localOnlyExtraFieldIds,
            'localOnlyExtraFieldIdHexes' => self::zipExtraFieldIdHexes($localOnlyExtraFieldIds),
            'mismatchedExtraFieldValueIds' => $mismatchedExtraFieldValueIds,
            'mismatchedExtraFieldValueIdHexes' => self::zipExtraFieldIdHexes($mismatchedExtraFieldValueIds),
            'centralLocalExtraFieldIdsMatch' => $centralOnlyExtraFieldIds === [] && $localOnlyExtraFieldIds === [],
            'centralLocalExtraFieldValuesMatch' => $mismatchedExtraFieldValueIds === [],
            'hasCentralExtraFields' => $centralExtraFieldIds !== [],
            'hasLocalExtraFields' => $localExtraFieldIds !== [],
            'hasZipExtraFieldProvenance' => $zipExtraFieldIds !== [],
            'hasDuplicateExtraFieldIds' => ($entry['hasDuplicateExtraFieldIds'] ?? false) === true,
            'hasMismatchedExtraFieldIds' => ($entry['hasMismatchedExtraFieldIds'] ?? false) === true,
            'hasMismatchedExtraFieldValues' => ($entry['hasMismatchedExtraFieldValues'] ?? false) === true,
        ];
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>
     */
    private static function zipUnixOwnerMetadataProvenance(?array $entry): array
    {
        $centralOwner = is_array($entry['centralOwner'] ?? null) ? $entry['centralOwner'] : null;
        $localOwner = is_array($entry['localOwner'] ?? null) ? $entry['localOwner'] : null;
        $issues = is_array($entry['issues'] ?? null) ? $entry['issues'] : [];

        return [
            'centralUnixOwner' => $centralOwner,
            'localUnixOwner' => $localOwner,
            'hasCentralUnixOwnerMetadata' => ($entry['hasCentralOwnerMetadata'] ?? false) === true,
            'hasLocalUnixOwnerMetadata' => ($entry['hasLocalOwnerMetadata'] ?? false) === true,
            'hasUnixOwnerMetadata' => $centralOwner !== null || $localOwner !== null,
            'unixOwnerMetadataMatches' => ($entry['ownerMetadataMatches'] ?? true) === true,
            'unixOwnerMetadataIssues' => $issues,
            'unixOwnerMetadataByteExposurePolicy' => 'zip-unix-owner-metadata-only',
            'unixOwnerMetadataCanExposeBytes' => false,
        ];
    }

    /**
     * @param array<string, mixed>|null $platformMetadata
     * @param array<string, mixed>|null $permissions
     * @param array<string, mixed>|null $creatorHost
     * @param array<string, mixed>|null $dosAttributes
     * @param array<string, mixed>|null $internalAttributes
     * @return array<string, mixed>
     */
    private static function zipPlatformAttributeProvenance(
        ZipPackageEntry $entry,
        ?array $platformMetadata,
        ?array $permissions,
        ?array $creatorHost,
        ?array $dosAttributes,
        ?array $internalAttributes
    ): array {
        $unixMode = $entry->unixMode();
        $unixPermissions = $entry->unixPermissionBits();
        $platformAttributeIssues = [];
        foreach ([$platformMetadata, $permissions, $creatorHost, $internalAttributes] as $summary) {
            foreach (is_array($summary['issues'] ?? null) ? $summary['issues'] : [] as $issue) {
                if (is_string($issue) && $issue !== '' && !in_array($issue, $platformAttributeIssues, true)) {
                    $platformAttributeIssues[] = $issue;
                }
            }
        }
        if (is_array($dosAttributes)) {
            foreach ([
                'hasHiddenAttribute' => 'dos-hidden-attribute',
                'hasSystemAttribute' => 'dos-system-attribute',
                'hasVolumeLabelAttribute' => 'dos-volume-label-attribute',
            ] as $flag => $issue) {
                if (($dosAttributes[$flag] ?? false) === true && !in_array($issue, $platformAttributeIssues, true)) {
                    $platformAttributeIssues[] = $issue;
                }
            }
        }

        return [
            'madeByHostSystem' => $entry->madeByHostSystem(),
            'madeByHostSystemName' => $creatorHost['madeByHostSystemName'] ?? null,
            'madeByVersion' => $entry->madeByVersion(),
            'versionMadeBy' => $entry->versionMadeBy,
            'versionNeededToExtract' => $entry->neededToExtractVersion(),
            'creatorVersionMeetsNeeded' => $creatorHost['creatorVersionMeetsNeeded'] ?? null,
            'creatorVersionComparison' => $creatorHost['creatorVersionComparison'] ?? null,
            'creatorVersionDelta' => $creatorHost['creatorVersionDelta'] ?? null,
            'creatorHostIssues' => is_array($creatorHost['issues'] ?? null) ? $creatorHost['issues'] : [],
            'externalAttributes' => $entry->externalFileAttributes,
            'externalAttributesHex' => sprintf('%08x', $entry->externalFileAttributes),
            'hasExternalAttributes' => $entry->externalFileAttributes !== 0,
            'dosAttributes' => $dosAttributes['dosAttributes'] ?? ($entry->externalFileAttributes & 0xff),
            'dosAttributeNames' => is_array($dosAttributes['dosAttributeNames'] ?? null) ? $dosAttributes['dosAttributeNames'] : $entry->dosAttributeNames(),
            'hasDosAttributes' => (($dosAttributes['dosAttributes'] ?? ($entry->externalFileAttributes & 0xff)) !== 0),
            'hasDosReadOnlyAttribute' => $dosAttributes['hasReadOnlyAttribute'] ?? $entry->hasDosReadOnlyAttribute(),
            'hasDosHiddenAttribute' => $dosAttributes['hasHiddenAttribute'] ?? $entry->hasDosHiddenAttribute(),
            'hasDosSystemAttribute' => $dosAttributes['hasSystemAttribute'] ?? $entry->hasDosSystemAttribute(),
            'hasDosVolumeLabelAttribute' => $dosAttributes['hasVolumeLabelAttribute'] ?? $entry->hasDosVolumeLabelAttribute(),
            'hasDosDirectoryAttribute' => $dosAttributes['hasDirectoryAttribute'] ?? $entry->hasDosDirectoryAttribute(),
            'hasDosArchiveAttribute' => $dosAttributes['hasArchiveAttribute'] ?? $entry->hasDosArchiveAttribute(),
            'internalFileAttributes' => $entry->internalFileAttributes,
            'internalFileAttributesHex' => sprintf('%04x', $entry->internalFileAttributes),
            'internalAttributeNames' => is_array($internalAttributes['internalAttributeNames'] ?? null) ? $internalAttributes['internalAttributeNames'] : $entry->internalAttributeNames(),
            'hasInternalFileAttributes' => $internalAttributes['hasInternalFileAttributes'] ?? ($entry->internalFileAttributes !== 0),
            'hasTextInternalAttribute' => $internalAttributes['hasTextInternalAttribute'] ?? $entry->hasTextInternalAttribute(),
            'hasUnknownInternalAttributeBits' => $internalAttributes['hasUnknownInternalAttributeBits'] ?? ($entry->unknownInternalAttributeBits() !== 0),
            'unknownInternalAttributeBits' => $internalAttributes['unknownInternalAttributeBits'] ?? $entry->unknownInternalAttributeBits(),
            'unixMode' => $unixMode,
            'unixModeOctal' => $unixMode === null ? null : sprintf('%06o', $unixMode),
            'unixPermissions' => $unixPermissions,
            'unixPermissionsOctal' => $unixPermissions === null ? null : sprintf('%04o', $unixPermissions),
            'hasUnixMode' => $unixMode !== null,
            'unixFileType' => $entry->unixFileType(),
            'unixFileTypeName' => $entry->unixFileTypeName(),
            'isUnixExecutableFile' => $permissions['isExecutableFile'] ?? $entry->isUnixExecutableFile(),
            'isGroupWritable' => $permissions['isGroupWritable'] ?? false,
            'isWorldWritable' => $permissions['isWorldWritable'] ?? false,
            'hasWritablePermissions' => $permissions['hasWritablePermissions'] ?? false,
            'platformMetadataPlatform' => $platformMetadata['platform'] ?? null,
            'platformMetadataIssues' => is_array($platformMetadata['issues'] ?? null) ? $platformMetadata['issues'] : [],
            'platformAttributeIssues' => $platformAttributeIssues,
            'hasPlatformAttributeProvenance' => $platformAttributeIssues !== []
                || $entry->externalFileAttributes !== 0
                || $entry->internalFileAttributes !== 0
                || $entry->madeByHostSystem() !== 3
                || $entry->madeByVersion() !== $entry->neededToExtractVersion(),
        ];
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>
     */
    private static function zipNameHygieneProvenance(?array $entry): array
    {
        if ($entry === null) {
            return [
                'zipNameHygieneSegments' => [],
                'zipNameHygieneFlaggedSegmentCount' => 0,
                'zipNameHygieneFlaggedSegments' => [],
                'zipNameHygieneIssueCodes' => [],
                'hasZipNameHygieneIssue' => false,
            ];
        }

        $flaggedSegments = is_array($entry['flaggedSegments'] ?? null) ? $entry['flaggedSegments'] : [];

        return [
            'zipNameHygieneSegments' => is_array($entry['segments'] ?? null) ? $entry['segments'] : [],
            'zipNameHygieneFlaggedSegmentCount' => count($flaggedSegments),
            'zipNameHygieneFlaggedSegments' => $flaggedSegments,
            'zipNameHygieneIssueCodes' => is_array($entry['issues'] ?? null) ? $entry['issues'] : [],
            'hasZipNameHygieneIssue' => ($entry['hasNameHygieneIssue'] ?? false) === true,
        ];
    }

    /**
     * @param array<string, mixed>|null $timestamp
     * @return array<string, mixed>
     */
    private static function zipTimestampProvenance(?array $timestamp): array
    {
        return [
            'zipModifiedAt' => $timestamp['modifiedAt'] ?? null,
            'zipTimestampSource' => $timestamp['timestampSource'] ?? null,
            'zipModifiedDosTime' => $timestamp['modifiedDosTime'] ?? null,
            'zipModifiedDosDate' => $timestamp['modifiedDosDate'] ?? null,
            'zipHasDosTimestamp' => ($timestamp['hasDosTimestamp'] ?? false) === true,
            'zipIsDosTimestampValid' => ($timestamp['isDosTimestampValid'] ?? true) === true,
            'zipDosModifiedAt' => $timestamp['dosModifiedAt'] ?? null,
            'zipExtendedModifiedAt' => $timestamp['extendedModifiedAt'] ?? null,
            'zipNtfsModifiedAt' => $timestamp['ntfsModifiedAt'] ?? null,
            'zipCentralModifiedAt' => $timestamp['centralModifiedAt'] ?? null,
            'zipCentralTimestampSource' => $timestamp['centralTimestampSource'] ?? null,
            'zipLocalExtendedModifiedAt' => $timestamp['localExtendedModifiedAt'] ?? null,
            'zipLocalNtfsModifiedAt' => $timestamp['localNtfsModifiedAt'] ?? null,
            'zipLocalModifiedAt' => $timestamp['localModifiedAt'] ?? null,
            'zipLocalTimestampSource' => $timestamp['localTimestampSource'] ?? null,
            'zipTimestampIssues' => is_array($timestamp['issues'] ?? null) ? $timestamp['issues'] : [],
        ];
    }

    /**
     * @param array<string, mixed>|null $manifestEntry
     * @param array<string, mixed>|null $embeddedObjectPackage
     * @return list<string>
     */
    private static function packageEntryRoles(
        ZipPackageEntry $entry,
        ?array $manifestEntry,
        bool $undeclared,
        ?array $embeddedObjectPackage
    ): array
    {
        $roles = [];
        if ($entry->name === 'mimetype') {
            $roles[] = 'odf-mimetype';
        }
        if ($entry->name === 'META-INF/manifest.xml') {
            $roles[] = 'odf-manifest';
        }
        if ($entry->name === 'content.xml') {
            $roles[] = 'odf-content';
        }
        if ($entry->name === 'styles.xml') {
            $roles[] = 'odf-styles';
        }
        if ($entry->name === 'meta.xml') {
            $roles[] = 'odf-meta';
        }
        if ($entry->name === 'settings.xml') {
            $roles[] = 'odf-settings';
        }
        if (self::isThumbnailPackagePartName($entry->name)) {
            $roles[] = 'package-thumbnail';
        }
        if (self::isSignaturePackagePartName($entry->name)) {
            $roles[] = 'package-signature';
        }
        if (self::isScriptPackagePartName($entry->name)) {
            $roles[] = 'script-package';
        }
        if (self::isConfigurationPackagePartName($entry->name)) {
            $roles[] = 'configuration-package';
        }
        if (self::isFontPackagePart($entry->name, is_array($manifestEntry) ? (string) ($manifestEntry['mediaType'] ?? '') : null)) {
            $roles[] = 'font-package';
        }
        if (self::isRdfMetadataPart($entry->name, is_array($manifestEntry) ? (string) ($manifestEntry['mediaType'] ?? '') : null)) {
            $roles[] = 'rdf-metadata';
        }
        if ($entry->isDirectory()) {
            $roles[] = 'zip-directory';
        }
        if (is_array($embeddedObjectPackage)) {
            $roles[] = $embeddedObjectPackage['isRoot'] === true ? 'embedded-object-root' : 'embedded-object-part';
        }
        if (self::isObjectReplacementPackagePartName($entry->name)) {
            $roles[] = 'object-replacement';
        }
        if (self::isLayoutCachePackagePartName($entry->name)) {
            $roles[] = 'layout-cache';
        }
        if (self::isMetaInfSidecarPackagePartName($entry->name)) {
            $roles[] = 'meta-inf-sidecar';
        }
        if (self::isDatabasePackagePartName($entry->name)) {
            $roles[] = 'database-package';
        }
        if (is_array($manifestEntry)) {
            $roles[] = 'manifest-declared';
            if (!$entry->isDirectory() && self::isMediaResourceManifestEntry($manifestEntry)) {
                $roles[] = 'media-resource';
            }
        }
        if ($undeclared) {
            $roles[] = 'undeclared-package-entry';
        }

        return $roles === [] ? ['package-part'] : array_values(array_unique($roles));
    }

    private static function isScriptPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'basic/')
            || str_starts_with($normalized, 'scripts/');
    }

    private static function isConfigurationPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'configurations2/');
    }

    private static function isFontPackagePart(string $path, ?string $mediaType = null): bool
    {
        if (self::isFontPackagePartName($path)) {
            return true;
        }

        return $mediaType !== null && self::isFontMediaType($mediaType);
    }

    private static function isRdfMetadataPart(string $path, ?string $mediaType = null): bool
    {
        if (self::isRdfPartName($path)) {
            return true;
        }

        return $mediaType !== null && self::mediaTypeReport($mediaType)['mediaTypeBase'] === 'application/rdf+xml';
    }

    private static function isRdfPartName(string $path): bool
    {
        return strtolower(basename($path)) === 'manifest.rdf';
    }

    private static function isFontPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));
        if (str_ends_with($normalized, '/')) {
            return false;
        }

        return str_starts_with($normalized, 'fonts/');
    }

    private static function isObjectReplacementPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));
        if (str_ends_with($normalized, '/')) {
            return false;
        }

        return str_starts_with($normalized, 'objectreplacements/');
    }

    private static function isLayoutCachePackagePartName(string $path): bool
    {
        return strtolower(ltrim($path, '/')) === 'layout-cache';
    }

    private static function isMetaInfSidecarPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));
        if (
            $normalized === 'meta-inf/manifest.xml'
            || str_ends_with($normalized, '/')
            || self::isSignaturePackagePartName($path)
        ) {
            return false;
        }

        return str_starts_with($normalized, 'meta-inf/');
    }

    private static function isDatabasePackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));
        if (str_ends_with($normalized, '/')) {
            return false;
        }

        return str_starts_with($normalized, 'database/');
    }

    private static function layoutCacheMediaTypeFromPart(string $path): string
    {
        return self::isLayoutCachePackagePartName($path) ? 'application/binary' : '';
    }

    private static function isLayoutCacheMediaType(string $mediaType): bool
    {
        return in_array(self::mediaTypeReport($mediaType)['mediaTypeBase'], ['application/binary', 'application/octet-stream'], true);
    }

    private static function objectReplacementMediaTypeFromPart(string $path): ?string
    {
        return self::thumbnailMediaTypeFromPart($path);
    }

    private static function isObjectReplacementMediaType(string $mediaType): bool
    {
        return str_starts_with(self::mediaTypeReport($mediaType)['mediaTypeBase'], 'image/');
    }

    private static function isFontMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return str_starts_with($base, 'font/')
            || in_array($base, [
                'application/font-woff',
                'application/font-woff2',
                'application/vnd.ms-fontobject',
                'application/vnd.ms-opentype',
                'application/x-font-opentype',
                'application/x-font-otf',
                'application/x-font-ttf',
                'application/x-font-type1',
                'application/x-font-woff',
                'application/x-font-woff2',
                'application/x-opentype',
                'application/x-truetype-font',
            ], true);
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, array<string, mixed>>
     */
    private static function embeddedObjectPackageRootParts(array $entries): array
    {
        $roots = [];
        foreach ($entries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '') {
                continue;
            }
            if (!self::isEmbeddedObjectPackageMediaType((string) ($entry['mediaTypeBase'] ?? $entry['mediaType'] ?? ''))) {
                continue;
            }

            $root = rtrim($packagePath, '/') . '/';
            if ($root === '/') {
                continue;
            }

            $roots[$root] = $entry;
        }

        return $roots;
    }

    private static function isEmbeddedObjectPackageMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return in_array($base, [
            'application/vnd.oasis.opendocument.chart',
            'application/vnd.oasis.opendocument.database',
            'application/vnd.oasis.opendocument.formula',
            'application/vnd.oasis.opendocument.graphics',
            'application/vnd.oasis.opendocument.presentation',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.text',
        ], true);
    }

    /**
     * @param array<string, array<string, mixed>> $objectPackageRootParts
     * @return array<string, mixed>|null
     */
    private static function embeddedObjectPackageMembership(string $part, array $objectPackageRootParts): ?array
    {
        foreach ($objectPackageRootParts as $rootPart => $rootEntry) {
            if ($part !== $rootPart && !str_starts_with($part, $rootPart)) {
                continue;
            }

            return [
                'rootPart' => $rootPart,
                'objectPath' => rtrim($rootPart, '/'),
                'objectType' => self::objectTypeForMediaType((string) ($rootEntry['mediaTypeBase'] ?? $rootEntry['mediaType'] ?? '')),
                'mediaType' => (string) ($rootEntry['mediaType'] ?? ''),
                'mediaTypeBase' => (string) ($rootEntry['mediaTypeBase'] ?? ''),
                'isRoot' => $part === $rootPart,
            ];
        }

        return null;
    }

    private static function objectTypeForMediaType(string $mediaType): string
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return match ($base) {
            'application/vnd.oasis.opendocument.chart' => 'chart',
            'application/vnd.oasis.opendocument.database' => 'database',
            'application/vnd.oasis.opendocument.formula' => 'formula',
            'application/vnd.oasis.opendocument.graphics' => 'graphics',
            'application/vnd.oasis.opendocument.presentation' => 'presentation',
            'application/vnd.oasis.opendocument.spreadsheet' => 'spreadsheet',
            'application/vnd.oasis.opendocument.text' => 'text',
            default => 'object',
        };
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function isMediaResourceManifestEntry(array $entry): bool
    {
        $packagePath = $entry['packagePath'] ?? $entry['path'] ?? '';
        if (!is_string($packagePath) || $packagePath === '' || str_ends_with($packagePath, '/')) {
            return false;
        }
        if (($entry['embeddedObjectPackagePart'] ?? false) === true) {
            return false;
        }
        if (is_string($packagePath) && self::isThumbnailPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isSignaturePackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isScriptPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isConfigurationPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isFontPackagePart($packagePath, is_string($entry['mediaType'] ?? null) ? $entry['mediaType'] : null)) {
            return false;
        }
        if (is_string($packagePath) && self::isObjectReplacementPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isLayoutCachePackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isMetaInfSidecarPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isDatabasePackagePartName($packagePath)) {
            return false;
        }

        $mediaTypeBase = (string) ($entry['mediaTypeBase'] ?? $entry['mediaType'] ?? '');
        if (
            str_starts_with($mediaTypeBase, 'image/')
            || str_starts_with($mediaTypeBase, 'audio/')
            || str_starts_with($mediaTypeBase, 'video/')
        ) {
            return true;
        }
        if ($mediaTypeBase === 'application/octet-stream' && self::mediaResourceFamilyFromPackagePart($packagePath) !== null) {
            return true;
        }

        return str_starts_with((string) ($entry['path'] ?? ''), 'Pictures/');
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private static function withPackageEntryMetadata(array $entries, ZipPackage $package): array
    {
        $hydrated = [];
        $modificationTimeByName = self::zipModificationTimeEntriesByName($package->modificationTimePreflight());
        $objectPackageRootParts = self::embeddedObjectPackageRootParts($entries);
        foreach ($entries as $entry) {
            $isRoot = $entry['path'] === '/';
            $packagePath = $entry['packagePath'];
            $isDirectory = is_string($packagePath) && str_ends_with($packagePath, '/');
            $embeddedObjectPackage = is_string($packagePath)
                ? self::embeddedObjectPackageMembership($packagePath, $objectPackageRootParts)
                : null;
            $embeddedObjectPackagePart = is_array($embeddedObjectPackage);
            $thumbnailPackagePart = is_string($packagePath) && self::isThumbnailPackagePartName($packagePath);
            $scriptPackagePart = is_string($packagePath) && self::isScriptPackagePartName($packagePath);
            $signaturePackagePart = is_string($packagePath) && self::isSignaturePackagePartName($packagePath);
            $configurationPackagePart = is_string($packagePath) && self::isConfigurationPackagePartName($packagePath);
            $fontPackagePart = is_string($packagePath) && self::isFontPackagePart($packagePath, (string) ($entry['mediaType'] ?? ''));
            $rdfMetadataPart = is_string($packagePath) && self::isRdfMetadataPart($packagePath, (string) ($entry['mediaType'] ?? ''));
            $objectReplacementPackagePart = is_string($packagePath) && self::isObjectReplacementPackagePartName($packagePath);
            $layoutCachePackagePart = is_string($packagePath) && self::isLayoutCachePackagePartName($packagePath);
            $metaInfSidecarPackagePart = is_string($packagePath) && self::isMetaInfSidecarPackagePartName($packagePath);
            $databasePackagePart = is_string($packagePath) && self::isDatabasePackagePartName($packagePath);
            $zipEntry = (!$isRoot && is_string($packagePath) && $package->has($packagePath))
                ? $package->entry($packagePath)
                : null;
            $timestampProvenance = $zipEntry instanceof ZipPackageEntry
                ? self::zipTimestampProvenance($modificationTimeByName[$zipEntry->name] ?? null)
                : self::zipTimestampProvenance(null);
            $exists = $isRoot || $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = is_array($entry['encryption']);
            $missingMediaType = ($entry['missingMediaType'] ?? false) === true;
            $storedByteLength = $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null;
            $compressionMethod = $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null;
            $hasSupportedCompression = $compressionMethod === null || $compressionMethod === 0 || $compressionMethod === 8;
            $canExposeBytes = !$isRoot
                && $exists
                && !$isDirectory
                && !$encrypted
                && !$embeddedObjectPackagePart
                && !$thumbnailPackagePart
                && !$scriptPackagePart
                && !$signaturePackagePart
                && !$configurationPackagePart
                && !$fontPackagePart
                && !$rdfMetadataPart
                && !$objectReplacementPackagePart
                && !$layoutCachePackagePart
                && !$metaInfSidecarPackagePart
                && !$databasePackagePart
                && !$missingMediaType
                && $hasSupportedCompression;
            $declaredSize = is_int($entry['size'] ?? null) ? $entry['size'] : null;
            $declaredSizeMismatch = $declaredSize !== null
                && $storedByteLength !== null
                && !$isDirectory
                && $declaredSize !== $storedByteLength;
            $diagnostics = is_array($entry['diagnostics'] ?? null) ? array_values($entry['diagnostics']) : [];

            if (!$exists) {
                $diagnostics[] = 'odf-manifest-missing-package-part';
            }
            if ($isDirectory) {
                $diagnostics[] = 'odf-manifest-directory-entry';
            }
            if ($encrypted) {
                $diagnostics[] = 'odf-manifest-encrypted-package-part';
            }
            if ($declaredSizeMismatch) {
                $diagnostics[] = 'odf-manifest-declared-size-mismatch';
            }
            if (!$hasSupportedCompression) {
                $diagnostics[] = 'odf-manifest-unsupported-compression-method';
            }
            $manifestMediaFamily = self::manifestMediaFamily(
                $entry,
                $isRoot,
                $isDirectory,
                $embeddedObjectPackage,
                $scriptPackagePart,
                $configurationPackagePart,
                $fontPackagePart,
                $rdfMetadataPart,
                $objectReplacementPackagePart,
                $layoutCachePackagePart,
                $metaInfSidecarPackagePart,
                $databasePackagePart
            );

            $hydrated[] = array_merge($entry, [
                'exists' => $exists,
                'isDirectory' => $isDirectory,
                'manifestMediaFamily' => $manifestMediaFamily,
                'byteLength' => $canExposeBytes && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedByteLength' => $storedByteLength,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $compressionMethod,
                'compressionMethodName' => $compressionMethod !== null ? self::compressionMethodName($compressionMethod) : null,
                'crc32' => $canExposeBytes && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'byteSha256' => self::packageEntryByteSha256($package, $packagePath, $canExposeBytes),
                'declaredSize' => $declaredSize,
                'declaredSizeMismatch' => $declaredSizeMismatch,
                'embeddedObjectPackagePart' => $embeddedObjectPackagePart,
                'embeddedObjectRootPart' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['rootPart'] : null,
                'embeddedObjectPath' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['objectPath'] : null,
                'embeddedObjectType' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['objectType'] : null,
                'embeddedObjectRoot' => is_array($embeddedObjectPackage) && $embeddedObjectPackage['isRoot'] === true,
                'embeddedObjectContainedPart' => is_array($embeddedObjectPackage) && $embeddedObjectPackage['isRoot'] !== true,
                'embeddedObjectMediaType' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['mediaType'] : null,
                'thumbnailPackagePart' => $thumbnailPackagePart,
                'scriptPackagePart' => $scriptPackagePart,
                'signaturePackagePart' => $signaturePackagePart,
                'configurationPackagePart' => $configurationPackagePart,
                'fontPackagePart' => $fontPackagePart,
                'rdfMetadataPart' => $rdfMetadataPart,
                'objectReplacementPackagePart' => $objectReplacementPackagePart,
                'layoutCachePackagePart' => $layoutCachePackagePart,
                'metaInfSidecarPackagePart' => $metaInfSidecarPackagePart,
                'databasePackagePart' => $databasePackagePart,
                'canExposeBytes' => $canExposeBytes,
                'byteExposurePolicy' => self::byteExposurePolicy(
                    $isRoot,
                    $exists,
                    $isDirectory,
                    $encrypted,
                    $embeddedObjectPackagePart,
                    $thumbnailPackagePart,
                    $scriptPackagePart,
                    $signaturePackagePart,
                    $configurationPackagePart,
                    $fontPackagePart,
                    $rdfMetadataPart,
                    $objectReplacementPackagePart,
                    $layoutCachePackagePart,
                    $metaInfSidecarPackagePart,
                    $databasePackagePart,
                    $missingMediaType,
                    $hasSupportedCompression
                ),
                'diagnostics' => $diagnostics,
            ], $timestampProvenance);
        }

        return $hydrated;
    }

    private static function byteExposurePolicy(
        bool $isRoot,
        bool $exists,
        bool $isDirectory,
        bool $encrypted,
        bool $embeddedObjectPackagePart,
        bool $thumbnailPackagePart,
        bool $scriptPackagePart,
        bool $signaturePackagePart,
        bool $configurationPackagePart,
        bool $fontPackagePart,
        bool $rdfMetadataPart,
        bool $objectReplacementPackagePart,
        bool $layoutCachePackagePart,
        bool $metaInfSidecarPackagePart,
        bool $databasePackagePart,
        bool $missingMediaType,
        bool $hasSupportedCompression
    ): string {
        if ($isRoot) {
            return 'package-root-no-bytes';
        }
        if ($isDirectory) {
            return 'directory-entry-no-bytes';
        }
        if ($encrypted) {
            return 'encrypted-resource-bytes-blocked';
        }
        if ($embeddedObjectPackagePart) {
            return 'embedded-object-package-bytes-blocked';
        }
        if ($thumbnailPackagePart) {
            return 'package-thumbnail-bytes-blocked';
        }
        if ($scriptPackagePart) {
            return 'script-package-bytes-blocked';
        }
        if ($signaturePackagePart) {
            return 'signature-package-bytes-blocked';
        }
        if ($configurationPackagePart) {
            return 'configuration-package-bytes-blocked';
        }
        if ($fontPackagePart) {
            return 'font-package-bytes-blocked';
        }
        if ($rdfMetadataPart) {
            return 'rdf-metadata-bytes-blocked';
        }
        if ($objectReplacementPackagePart) {
            return 'object-replacement-package-bytes-blocked';
        }
        if ($layoutCachePackagePart) {
            return 'layout-cache-package-bytes-blocked';
        }
        if ($metaInfSidecarPackagePart) {
            return 'meta-inf-sidecar-package-bytes-blocked';
        }
        if ($databasePackagePart) {
            return 'database-package-bytes-blocked';
        }
        if ($missingMediaType) {
            return 'missing-media-type-bytes-blocked';
        }
        if (!$hasSupportedCompression) {
            return 'unsupported-compression-bytes-blocked';
        }

        return $exists ? 'package-bytes-exposable' : 'missing-package-part';
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed>|null $embeddedObjectPackage
     */
    private static function manifestMediaFamily(
        array $entry,
        bool $isRoot,
        bool $isDirectory,
        ?array $embeddedObjectPackage,
        bool $scriptPackagePart,
        bool $configurationPackagePart,
        bool $fontPackagePart,
        bool $rdfMetadataPart,
        bool $objectReplacementPackagePart,
        bool $layoutCachePackagePart,
        bool $metaInfSidecarPackagePart,
        bool $databasePackagePart
    ): string {
        if ($isRoot) {
            return 'opendocument-text-package';
        }

        $packagePath = is_string($entry['packagePath'] ?? null) ? $entry['packagePath'] : '';
        $mediaTypeBase = strtolower(trim((string) ($entry['mediaTypeBase'] ?? '')));

        if ($isDirectory && is_array($embeddedObjectPackage) && ($embeddedObjectPackage['isRoot'] ?? false) === true) {
            return 'opendocument-object-package';
        }
        if ($isDirectory) {
            return 'directory';
        }
        if ($scriptPackagePart) {
            return 'script';
        }
        if ($configurationPackagePart) {
            return 'configuration';
        }
        if (self::isThumbnailPackagePartName($packagePath)) {
            return 'thumbnail';
        }
        if (self::isSignaturePackagePartName($packagePath)) {
            return 'signature';
        }
        if ($objectReplacementPackagePart) {
            return 'object-replacement';
        }
        if ($layoutCachePackagePart) {
            return 'layout-cache';
        }
        if ($metaInfSidecarPackagePart) {
            return 'meta-inf-sidecar';
        }
        if ($databasePackagePart) {
            return 'database';
        }
        if ($fontPackagePart || ($mediaTypeBase !== '' && self::isFontMediaType($mediaTypeBase))) {
            return 'font';
        }
        if ($rdfMetadataPart || $mediaTypeBase === 'application/rdf+xml') {
            return 'rdf';
        }
        if (is_array($embeddedObjectPackage) && ($embeddedObjectPackage['isRoot'] ?? false) === true) {
            return 'opendocument-object-package';
        }
        if ($mediaTypeBase !== '' && self::isEmbeddedObjectPackageMediaType($mediaTypeBase)) {
            return 'opendocument-object-package';
        }

        $mediaResourceFamily = self::mediaResourceFamilyFromMediaTypeBase($mediaTypeBase);
        if ($mediaResourceFamily !== null) {
            return $mediaResourceFamily;
        }
        $packageMediaResourceFamily = self::mediaResourceFamilyFromPackagePart($packagePath);
        if ($mediaTypeBase === 'application/octet-stream' && $packageMediaResourceFamily !== null) {
            return $packageMediaResourceFamily;
        }
        if (self::isXmlMediaTypeBase($mediaTypeBase)) {
            return 'xml';
        }
        if (($entry['missingMediaType'] ?? false) === true || $mediaTypeBase === '') {
            return 'missing-media-type';
        }
        if ($mediaTypeBase === 'application/octet-stream' || str_starts_with($mediaTypeBase, 'application/vnd.')) {
            return 'binary';
        }

        return 'other';
    }

    private static function packageEntryByteSha256(ZipPackage $package, ?string $packagePath, bool $canExposeBytes): ?string
    {
        if (!$canExposeBytes || $packagePath === null || $packagePath === '' || str_ends_with($packagePath, '/')) {
            return null;
        }

        return hash('sha256', $package->read($packagePath));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function undeclaredPackageEntries(): array
    {
        $specialPackageParts = [
            'mimetype' => true,
            'META-INF/manifest.xml' => true,
        ];
        $entries = [];
        $objectPackageRootParts = self::embeddedObjectPackageRootParts($this->manifestEntries);

        foreach ($this->package->entries() as $entry) {
            $path = $entry->name;
            if (isset($this->manifestEntriesByPath[$path]) || isset($specialPackageParts[$path])) {
                continue;
            }

            $embeddedObjectPackage = self::embeddedObjectPackageMembership($path, $objectPackageRootParts);
            $entries[] = [
                'path' => $path,
                'pathShape' => self::pathShape($path),
                'isDirectory' => str_ends_with($path, '/'),
                'storedByteLength' => $entry->uncompressedSize,
                'compressedByteLength' => $entry->compressedSize,
                'compressionMethod' => $entry->compressionMethod,
                'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
                'crc32' => $entry->crc32Hex(),
                'byteSha256' => null,
                'thumbnailPackagePart' => self::isThumbnailPackagePartName($path),
                'scriptPackagePart' => self::isScriptPackagePartName($path),
                'signaturePackagePart' => self::isSignaturePackagePartName($path),
                'configurationPackagePart' => self::isConfigurationPackagePartName($path),
                'fontPackagePart' => self::isFontPackagePartName($path),
                'rdfMetadataPart' => self::isRdfPartName($path),
                'objectReplacementPackagePart' => self::isObjectReplacementPackagePartName($path),
                'layoutCachePackagePart' => self::isLayoutCachePackagePartName($path),
                'metaInfSidecarPackagePart' => self::isMetaInfSidecarPackagePartName($path),
                'databasePackagePart' => self::isDatabasePackagePartName($path),
                'canExposeBytes' => false,
                'byteExposurePolicy' => self::undeclaredPackageEntryByteExposurePolicy($path, $embeddedObjectPackage),
                'diagnostics' => ['odf-manifest-undeclared-package-entry'],
            ];
        }

        return $entries;
    }

    /**
     * @param array<string, mixed>|null $embeddedObjectPackage
     */
    private static function undeclaredPackageEntryByteExposurePolicy(string $path, ?array $embeddedObjectPackage = null): string
    {
        if (is_array($embeddedObjectPackage)) {
            return 'embedded-object-package-bytes-blocked';
        }
        if (self::isThumbnailPackagePartName($path)) {
            return 'package-thumbnail-bytes-blocked';
        }
        if (self::isScriptPackagePartName($path)) {
            return 'script-package-bytes-blocked';
        }
        if (self::isSignaturePackagePartName($path)) {
            return 'signature-package-bytes-blocked';
        }
        if (self::isConfigurationPackagePartName($path)) {
            return 'configuration-package-bytes-blocked';
        }
        if (self::isFontPackagePartName($path)) {
            return 'font-package-bytes-blocked';
        }
        if (self::isRdfPartName($path)) {
            return 'rdf-metadata-bytes-blocked';
        }
        if (self::isObjectReplacementPackagePartName($path)) {
            return 'object-replacement-package-bytes-blocked';
        }
        if (self::isLayoutCachePackagePartName($path)) {
            return 'layout-cache-package-bytes-blocked';
        }
        if (self::isMetaInfSidecarPackagePartName($path)) {
            return 'meta-inf-sidecar-package-bytes-blocked';
        }
        if (self::isDatabasePackagePartName($path)) {
            return 'database-package-bytes-blocked';
        }

        return 'undeclared-package-entry-no-bytes';
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @return array<string, mixed>
     */
    private static function readRdfMetadata(ZipPackage $package, array $manifestEntries): array
    {
        $candidatesByPart = [];
        foreach ($manifestEntries as $entry) {
            if (!self::isRdfManifestEntry($entry)) {
                continue;
            }

            $packagePath = $entry['packagePath'] ?? null;
            if (is_string($packagePath) && $packagePath !== '' && !str_ends_with($packagePath, '/')) {
                $entry['declared'] = true;
                $candidatesByPart[$packagePath] = $entry;
            }
        }

        $undeclaredCandidates = [];
        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory() || !self::isRdfPartName($entry->name) || isset($candidatesByPart[$entry->name])) {
                continue;
            }

            $undeclaredCandidates[$entry->name] = [
                'path' => $entry->name,
                'packagePath' => $entry->name,
                'pathReference' => $entry->name,
                'mediaType' => null,
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
            ];
        }
        ksort($undeclaredCandidates, SORT_STRING);
        $candidatesByPart += $undeclaredCandidates;

        $parts = [];
        $subjectsBySubject = [];
        $parsedPartCount = 0;
        $parseErrorCount = 0;
        $tripleCount = 0;
        $literalCount = 0;
        $resourceCount = 0;

        foreach ($candidatesByPart as $part => $entry) {
            $zipEntry = $package->has($part) ? $package->entry($part) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $partMetadata = [
                'fullPath' => $entry['path'] ?? $part,
                'path' => $entry['path'] ?? $part,
                'packagePath' => $part,
                'part' => $part,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $entry['mediaType'] ?? null,
                'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
                'mediaTypeHasParameters' => ($entry['mediaTypeHasParameters'] ?? false) === true,
                'mediaTypeParameterCount' => $entry['mediaTypeParameterCount'] ?? 0,
                'mediaTypeParameters' => $entry['mediaTypeParameters'] ?? [],
                'mediaTypeParameterMap' => $entry['mediaTypeParameterMap'] ?? [],
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'canExposeAsDocumentMedia' => false,
                'reviewPolicy' => 'package-rdf-metadata-only',
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'tripleCount' => 0,
                'literalCount' => 0,
                'resourceCount' => 0,
                'subjectCount' => 0,
                'subjects' => [],
                'triples' => [],
            ];

            if (!$zipEntry instanceof ZipPackageEntry) {
                $partMetadata['parseable'] = false;
                $partMetadata['diagnostic'] = 'missing-rdf-part';
                $parts[] = $partMetadata;
                continue;
            }

            if ($encrypted) {
                $partMetadata['parseable'] = false;
                $partMetadata['diagnostic'] = 'encrypted-rdf-part';
                $parts[] = $partMetadata;
                continue;
            }

            try {
                $parsed = self::parseRdfMetadataPart($package->read($part, 1048576), $part);
            } catch (\InvalidArgumentException $exception) {
                $partMetadata['parseable'] = false;
                $partMetadata['diagnostic'] = 'invalid-rdf-xml';
                $partMetadata['error'] = $exception->getMessage();
                ++$parseErrorCount;
                $parts[] = $partMetadata;
                continue;
            }

            $partMetadata = array_merge($partMetadata, $parsed, ['parseable' => true]);
            if (!$declared) {
                $partMetadata['diagnostic'] = 'odf-rdf-package-undeclared-part';
            }
            ++$parsedPartCount;
            $tripleCount += (int) ($parsed['tripleCount'] ?? 0);
            $literalCount += (int) ($parsed['literalCount'] ?? 0);
            $resourceCount += (int) ($parsed['resourceCount'] ?? 0);

            foreach ($parsed['subjectsBySubject'] ?? [] as $subject => $subjectMetadata) {
                if (!is_string($subject) || !is_array($subjectMetadata)) {
                    continue;
                }

                if (!isset($subjectsBySubject[$subject])) {
                    $subjectsBySubject[$subject] = [
                        'subject' => $subject,
                        'partCount' => 0,
                        'tripleCount' => 0,
                        'literalCount' => 0,
                        'resourceCount' => 0,
                        'parts' => [],
                        'predicates' => [],
                    ];
                }

                ++$subjectsBySubject[$subject]['partCount'];
                $subjectsBySubject[$subject]['tripleCount'] += (int) ($subjectMetadata['tripleCount'] ?? 0);
                $subjectsBySubject[$subject]['literalCount'] += (int) ($subjectMetadata['literalCount'] ?? 0);
                $subjectsBySubject[$subject]['resourceCount'] += (int) ($subjectMetadata['resourceCount'] ?? 0);
                $subjectsBySubject[$subject]['parts'][] = $part;
                foreach ($subjectMetadata['predicates'] ?? [] as $predicate) {
                    if (is_string($predicate) && $predicate !== '' && !in_array($predicate, $subjectsBySubject[$subject]['predicates'], true)) {
                        $subjectsBySubject[$subject]['predicates'][] = $predicate;
                    }
                }
            }

            $parts[] = $partMetadata;
        }

        foreach ($subjectsBySubject as &$subjectMetadata) {
            $subjectMetadata['parts'] = array_values(array_unique($subjectMetadata['parts']));
            sort($subjectMetadata['predicates'], SORT_STRING);
        }
        unset($subjectMetadata);

        return [
            'count' => count($parts),
            'partCount' => count($parts),
            'parsedPartCount' => $parsedPartCount,
            'parseErrorCount' => $parseErrorCount,
            'tripleCount' => $tripleCount,
            'literalCount' => $literalCount,
            'resourceCount' => $resourceCount,
            'subjectCount' => count($subjectsBySubject),
            'parts' => $parts,
            'subjectsBySubject' => $subjectsBySubject,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function isRdfManifestEntry(array $entry): bool
    {
        $mediaTypeBase = strtolower((string) ($entry['mediaTypeBase'] ?? self::mediaTypeReport((string) ($entry['mediaType'] ?? ''))['mediaTypeBase']));
        $packagePath = (string) ($entry['packagePath'] ?? '');

        return $mediaTypeBase === 'application/rdf+xml' || self::isRdfPartName($packagePath);
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseRdfMetadataPart(string $xml, string $part): array
    {
        $dom = self::loadXml($xml, 'ODT RDF metadata ' . $part);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !self::isElement($root, self::RDF_NAMESPACE, 'RDF')) {
            throw new \InvalidArgumentException('ODT RDF metadata ' . $part . ' must use rdf:RDF as its root element');
        }

        $triples = [];
        $subjectsBySubject = [];
        foreach (self::childElements($root) as $descriptionIndex => $description) {
            $subject = self::rdfSubject($description, $part, $descriptionIndex);
            $descriptionTriples = self::rdfDescriptionTriples($description, $subject);
            if (!self::isElement($description, self::RDF_NAMESPACE, 'Description')) {
                array_unshift($descriptionTriples, [
                    'subject' => $subject,
                    'predicate' => 'rdf:type',
                    'predicateNamespace' => self::RDF_NAMESPACE,
                    'predicateName' => 'type',
                    'objectType' => 'resource',
                    'object' => self::rdfExpandedName($description),
                ]);
            }

            foreach ($descriptionTriples as $triple) {
                if ($triple === []) {
                    continue;
                }

                $triples[] = $triple;
                if (!isset($subjectsBySubject[$subject])) {
                    $subjectsBySubject[$subject] = [
                        'subject' => $subject,
                        'tripleCount' => 0,
                        'literalCount' => 0,
                        'resourceCount' => 0,
                        'predicates' => [],
                    ];
                }

                ++$subjectsBySubject[$subject]['tripleCount'];
                if (($triple['objectType'] ?? '') === 'resource') {
                    ++$subjectsBySubject[$subject]['resourceCount'];
                } else {
                    ++$subjectsBySubject[$subject]['literalCount'];
                }
                $predicate = (string) ($triple['predicate'] ?? '');
                if ($predicate !== '' && !in_array($predicate, $subjectsBySubject[$subject]['predicates'], true)) {
                    $subjectsBySubject[$subject]['predicates'][] = $predicate;
                }
            }
        }

        foreach ($subjectsBySubject as &$subjectMetadata) {
            sort($subjectMetadata['predicates'], SORT_STRING);
        }
        unset($subjectMetadata);

        $literalCount = 0;
        $resourceCount = 0;
        foreach ($triples as $triple) {
            if (($triple['objectType'] ?? '') === 'resource') {
                ++$resourceCount;
            } else {
                ++$literalCount;
            }
        }

        return [
            'tripleCount' => count($triples),
            'literalCount' => $literalCount,
            'resourceCount' => $resourceCount,
            'subjectCount' => count($subjectsBySubject),
            'subjects' => array_values($subjectsBySubject),
            'subjectsBySubject' => $subjectsBySubject,
            'triples' => $triples,
        ];
    }

    private static function rdfSubject(\DOMElement $description, string $part, int $index): string
    {
        foreach (['about', 'ID', 'nodeID'] as $name) {
            $value = self::attributeValue($description, self::RDF_NAMESPACE, $name);
            if ($value === '') {
                continue;
            }

            if ($name === 'ID') {
                return '#' . $value;
            }
            if ($name === 'nodeID') {
                return '_:' . $value;
            }

            return $value;
        }

        return '_:' . $part . '#' . ($index + 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rdfDescriptionTriples(\DOMElement $description, string $subject): array
    {
        $triples = [];
        foreach ($description->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            if ($attribute->namespaceURI === self::RDF_NAMESPACE || $attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') {
                continue;
            }

            $value = trim($attribute->value);
            if ($value === '') {
                continue;
            }

            $triples[] = [
                'subject' => $subject,
                'predicate' => self::rdfNodeName($attribute),
                'predicateNamespace' => $attribute->namespaceURI,
                'predicateName' => $attribute->localName,
                'objectType' => 'literal',
                'object' => $value,
            ];
        }

        foreach (self::childElements($description) as $property) {
            $triple = self::rdfPropertyTriple($property, $subject);
            if ($triple !== []) {
                $triples[] = $triple;
            }
        }

        return $triples;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rdfPropertyTriple(\DOMElement $property, string $subject): array
    {
        $resource = self::attributeValue($property, self::RDF_NAMESPACE, 'resource');
        $nodeId = self::attributeValue($property, self::RDF_NAMESPACE, 'nodeID');
        $language = self::attributeValue($property, self::XML_NAMESPACE, 'lang');
        $datatype = self::attributeValue($property, self::RDF_NAMESPACE, 'datatype');
        $parseType = self::attributeValue($property, self::RDF_NAMESPACE, 'parseType');

        $metadata = [
            'subject' => $subject,
            'predicate' => self::rdfNodeName($property),
            'predicateNamespace' => $property->namespaceURI,
            'predicateName' => $property->localName,
        ];

        if ($resource !== '') {
            $metadata['objectType'] = 'resource';
            $metadata['object'] = $resource;
        } elseif ($nodeId !== '') {
            $metadata['objectType'] = 'resource';
            $metadata['object'] = '_:' . $nodeId;
        } else {
            $metadata['objectType'] = 'literal';
            $metadata['object'] = self::normalizedText($property);
        }

        if ($language !== '') {
            $metadata['language'] = $language;
        }
        if ($datatype !== '') {
            $metadata['datatype'] = $datatype;
        }
        if ($parseType !== '') {
            $metadata['parseType'] = $parseType;
        }

        return self::withoutEmptyValues($metadata);
    }

    private static function rdfNodeName(\DOMNode $node): string
    {
        $prefix = $node->prefix;
        if (is_string($prefix) && $prefix !== '') {
            return $prefix . ':' . $node->localName;
        }

        return $node->localName ?? $node->nodeName;
    }

    private static function rdfExpandedName(\DOMElement $element): string
    {
        $namespace = $element->namespaceURI;
        if (is_string($namespace) && $namespace !== '') {
            return $namespace . $element->localName;
        }

        return $element->localName;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, readableCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, encryptedCount:int, invalidMediaTypeCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
     */
    private static function packageFontMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isFontPackagePart($packagePath, (string) ($entry['mediaType'] ?? ''))) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isFontPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::fontMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'fontFormatSourceHint' => 'package-extension',
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'font-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $fontFormatCounts = [];
        $fontFormatSourceCounts = [];
        $fontFormatFamilyCounts = [];
        $fontFileExtensionCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $rawMediaType = trim((string) ($entry['mediaType'] ?? ''));
            $mediaType = $rawMediaType;
            if ($mediaType === '') {
                $mediaType = self::fontMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeValid = $mediaType === '' || self::isFontMediaType($mediaType);
            $fontFormat = self::fontFormatProvenance(
                $packagePath,
                $mediaTypeReport['mediaTypeBase'],
                $rawMediaType !== '' && ($entry['fontFormatSourceHint'] ?? '') !== 'package-extension'
            );
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-font-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-font-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-font-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-font-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $fontFormatCounts[$fontFormat['fontFormat']] = ($fontFormatCounts[$fontFormat['fontFormat']] ?? 0) + 1;
            $fontFormatSourceCounts[$fontFormat['fontFormatSource']] = ($fontFormatSourceCounts[$fontFormat['fontFormatSource']] ?? 0) + 1;
            $fontFormatFamilyCounts[$fontFormat['fontFormatFamily']] = ($fontFormatFamilyCounts[$fontFormat['fontFormatFamily']] ?? 0) + 1;
            $extensionKey = $fontFormat['fontFileExtension'] ?? 'none';
            $fontFileExtensionCounts[$extensionKey] = ($fontFileExtensionCounts[$extensionKey] ?? 0) + 1;

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'fontFileExtension' => $fontFormat['fontFileExtension'],
                'fontFormat' => $fontFormat['fontFormat'],
                'fontFormatSource' => $fontFormat['fontFormatSource'],
                'fontFormatFamily' => $fontFormat['fontFormatFamily'],
                'recognizedFontFormat' => $fontFormat['recognizedFontFormat'],
                'expectedMediaTypeRole' => 'font',
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'reviewPolicy' => 'package-font-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($fontFormatCounts, SORT_STRING);
        ksort($fontFormatSourceCounts, SORT_STRING);
        ksort($fontFormatFamilyCounts, SORT_STRING);
        ksort($fontFileExtensionCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !self::isFontMediaType((string) $item['mediaType']),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => ($item['issues'] ?? []) !== [])),
            'issueCodes' => array_keys($issueCodes),
            'fontFormatCounts' => $fontFormatCounts,
            'fontFormatSourceCounts' => $fontFormatSourceCounts,
            'fontFormatFamilyCounts' => $fontFormatFamilyCounts,
            'fontFileExtensionCounts' => $fontFileExtensionCounts,
            'recognizedFontFormatCount' => count(array_filter($items, static fn (array $item): bool => $item['recognizedFontFormat'] === true)),
            'unknownFontFormatCount' => count(array_filter($items, static fn (array $item): bool => $item['recognizedFontFormat'] !== true)),
            'items' => $items,
        ];
    }

    private static function fontMediaTypeFromPart(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            'pfb', 'pfa' => 'application/x-font-type1',
            'ttc' => 'font/collection',
            'ttf' => 'font/ttf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => null,
        };
    }

    /**
     * @return array{fontFileExtension:string|null, fontFormat:string, fontFormatSource:string, fontFormatFamily:string, recognizedFontFormat:bool}
     */
    private static function fontFormatProvenance(string $path, string $mediaTypeBase, bool $hasManifestMediaType): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extension = $extension === '' ? null : $extension;
        $format = $hasManifestMediaType ? self::fontFormatFromMediaTypeBase($mediaTypeBase) : null;
        $source = $format === null ? null : 'media-type';

        if ($format === null) {
            $format = $extension === null ? null : self::fontFormatFromExtension($extension);
            $source = $format === null ? null : 'package-extension';
        }

        $format ??= 'unknown';

        return [
            'fontFileExtension' => $extension,
            'fontFormat' => $format,
            'fontFormatSource' => $source ?? 'unknown',
            'fontFormatFamily' => self::fontFormatFamily($format),
            'recognizedFontFormat' => $format !== 'unknown',
        ];
    }

    private static function fontFormatFromMediaTypeBase(string $mediaTypeBase): ?string
    {
        return match (strtolower($mediaTypeBase)) {
            'application/font-woff',
            'application/x-font-woff',
            'font/woff' => 'woff',
            'application/font-woff2',
            'application/x-font-woff2',
            'font/woff2' => 'woff2',
            'application/vnd.ms-fontobject' => 'embedded-opentype',
            'application/vnd.ms-opentype',
            'application/x-font-opentype',
            'application/x-font-otf',
            'application/x-opentype',
            'font/otf' => 'opentype',
            'application/x-font-ttf',
            'application/x-truetype-font',
            'font/ttf' => 'truetype',
            'font/collection' => 'truetype-collection',
            'application/x-font-type1' => 'type1',
            default => null,
        };
    }

    private static function fontFormatFromExtension(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'eot' => 'embedded-opentype',
            'otf' => 'opentype',
            'pfa', 'pfb' => 'type1',
            'ttc' => 'truetype-collection',
            'ttf' => 'truetype',
            'woff' => 'woff',
            'woff2' => 'woff2',
            default => null,
        };
    }

    private static function fontFormatFamily(string $format): string
    {
        return match ($format) {
            'embedded-opentype' => 'legacy-webfont',
            'opentype', 'truetype', 'truetype-collection' => 'sfnt',
            'type1' => 'type1',
            'woff', 'woff2' => 'webfont',
            default => 'unknown',
        };
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, readableCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, encryptedCount:int, invalidMediaTypeCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
     */
    private static function packageThumbnailMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isThumbnailPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isThumbnailPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::thumbnailMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'package-thumbnail-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::thumbnailMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeValid = str_starts_with($mediaTypeReport['mediaTypeBase'], 'image/');
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-thumbnail-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-thumbnail-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-thumbnail-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-thumbnail-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'expectedMediaTypePrefix' => 'image/',
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'package-thumbnail-bytes-blocked',
                'reviewPolicy' => 'package-thumbnail-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !str_starts_with((string) $item['mediaTypeBase'], 'image/'),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'byteExposurePolicy' => 'package-thumbnail-bytes-blocked',
            'reviewPolicy' => 'package-thumbnail-metadata-only',
            'items' => $items,
        ];
    }

    private static function isThumbnailPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));
        if (!str_starts_with($normalized, 'thumbnails/') || str_ends_with($normalized, '/')) {
            return false;
        }

        return self::thumbnailMediaTypeFromPart($normalized) !== null;
    }

    private static function thumbnailMediaTypeFromPart(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'tif', 'tiff' => 'image/tiff',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, readableCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, encryptedCount:int, invalidMediaTypeCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
     */
    private static function packageSignatureMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isSignaturePackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isSignaturePackagePartName($packagePath)) {
                continue;
            }

            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => null,
                'mediaTypeBase' => '',
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = is_string($entry['mediaType'] ?? null) ? (string) $entry['mediaType'] : null;
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $mediaTypeValid = $mediaType === null
                || $mediaTypeReport['mediaTypeBase'] === 'text/xml'
                || $mediaTypeReport['mediaTypeBase'] === 'application/xml';
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-signature-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-signature-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-signature-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-signature-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'expectedMediaTypes' => ['text/xml', 'application/xml'],
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'reviewPolicy' => 'package-signature-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !in_array($item['mediaTypeBase'], ['text/xml', 'application/xml'], true),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, readableCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, encryptedCount:int, invalidMediaTypeCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
     */
    private static function packageObjectReplacementMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isObjectReplacementPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isObjectReplacementPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::objectReplacementMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'object-replacement-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::objectReplacementMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeValid = $mediaType !== '' && self::isObjectReplacementMediaType($mediaType);
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-object-replacement-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-object-replacement-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-object-replacement-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-object-replacement-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'expectedMediaTypePrefix' => 'image/',
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'object-replacement-package-bytes-blocked',
                'reviewPolicy' => 'object-replacement-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !self::isObjectReplacementMediaType((string) $item['mediaType']),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageLayoutCacheMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isLayoutCachePackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isLayoutCachePackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::layoutCacheMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'layout-cache-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::layoutCacheMediaTypeFromPart($packagePath);
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeValid = self::isLayoutCacheMediaType($mediaType);
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-layout-cache-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-layout-cache-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-layout-cache-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-layout-cache-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'expectedMediaTypes' => ['application/binary', 'application/octet-stream'],
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'layout-cache-package-bytes-blocked',
                'reviewPolicy' => 'layout-cache-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !self::isLayoutCacheMediaType((string) $item['mediaType']),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'byteExposurePolicy' => 'layout-cache-package-bytes-blocked',
            'reviewPolicy' => 'layout-cache-metadata-only',
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageScriptMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || str_ends_with($packagePath, '/') || !self::isScriptPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || str_ends_with($packagePath, '/') || !self::isScriptPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::scriptMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'script-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $containers = [];
        $scriptKinds = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::scriptMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $pathInfo = self::scriptPackagePathInfo($packagePath, $mediaTypeReport['mediaTypeBase']);
            $mediaTypeValid = self::scriptMediaTypeValid($packagePath, $mediaTypeReport['mediaTypeBase']);
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-script-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-script-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-script-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-script-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            if (($pathInfo['scriptContainer'] ?? null) !== null) {
                $containers[(string) $pathInfo['scriptContainer']] = true;
            }
            if (($pathInfo['scriptKind'] ?? null) !== null) {
                $scriptKinds[(string) $pathInfo['scriptKind']] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'mediaTypeValid' => $mediaTypeValid,
                'scriptContainer' => $pathInfo['scriptContainer'] ?? null,
                'scriptKind' => $pathInfo['scriptKind'] ?? null,
                'scriptPath' => $pathInfo['scriptPath'] ?? null,
                'scriptLibrary' => $pathInfo['scriptLibrary'] ?? null,
                'scriptModule' => $pathInfo['scriptModule'] ?? null,
                'extension' => $pathInfo['extension'] ?? null,
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'script-package-bytes-blocked',
                'reviewPolicy' => 'package-script-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($containers, SORT_STRING);
        ksort($scriptKinds, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaTypeValid'] !== true,
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'scriptContainers' => array_keys($containers),
            'scriptKinds' => array_keys($scriptKinds),
            'byteExposurePolicy' => 'script-package-bytes-blocked',
            'reviewPolicy' => 'package-script-metadata-only',
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageConfigurationMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isConfigurationPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isConfigurationPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::configurationMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'configuration-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $areas = [];
        $kinds = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $isDirectory = str_ends_with($packagePath, '/');
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::configurationMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $pathInfo = self::configurationPackagePathInfo($packagePath, $mediaTypeReport['mediaTypeBase']);
            $mediaTypeValid = self::configurationMediaTypeValid($packagePath, $mediaTypeReport['mediaTypeBase'], $isDirectory);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-configuration-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-configuration-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-configuration-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-configuration-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            if (($pathInfo['configurationArea'] ?? null) !== null) {
                $areas[(string) $pathInfo['configurationArea']] = true;
            }
            if (($pathInfo['configurationKind'] ?? null) !== null) {
                $kinds[(string) $pathInfo['configurationKind']] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'configurationArea' => $pathInfo['configurationArea'] ?? null,
                'configurationKind' => $pathInfo['configurationKind'] ?? null,
                'configurationPath' => $pathInfo['configurationPath'] ?? null,
                'extension' => $pathInfo['extension'] ?? null,
                'exists' => $exists,
                'isDirectory' => $isDirectory,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'configuration-package-bytes-blocked',
                'reviewPolicy' => 'configuration-package-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($areas, SORT_STRING);
        ksort($kinds, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'storedPartCount' => count(array_filter($items, static fn (array $item): bool => $item['storedByteLength'] !== null)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('odf-configuration-invalid-media-type', $item['issues'], true),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'configurationAreas' => array_keys($areas),
            'configurationKinds' => array_keys($kinds),
            'byteExposurePolicy' => 'configuration-package-bytes-blocked',
            'reviewPolicy' => 'configuration-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function configurationMediaTypeFromPart(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'tif', 'tiff' => 'image/tiff',
            'webp' => 'image/webp',
            'xml' => 'text/xml',
            default => null,
        };
    }

    private static function configurationMediaTypeValid(string $path, string $mediaTypeBase, bool $isDirectory): bool
    {
        if ($isDirectory) {
            return $mediaTypeBase === '';
        }

        if ($mediaTypeBase === '') {
            return false;
        }

        $expected = self::configurationMediaTypeFromPart($path);
        if ($expected === null) {
            return true;
        }

        $expectedBase = self::mediaTypeReport($expected)['mediaTypeBase'];
        if (str_starts_with($expectedBase, 'image/')) {
            return str_starts_with($mediaTypeBase, 'image/');
        }

        if ($expectedBase === 'text/xml') {
            return in_array($mediaTypeBase, ['text/xml', 'application/xml'], true);
        }

        return $mediaTypeBase === $expectedBase;
    }

    /**
     * @return array<string, string|null>
     */
    private static function configurationPackagePathInfo(string $path, string $mediaTypeBase): array
    {
        $trimmed = trim($path, '/');
        $segments = $trimmed === '' ? [] : explode('/', $trimmed);
        $relativeSegments = array_slice($segments, 1);
        $configurationPath = $relativeSegments === [] ? null : implode('/', $relativeSegments);
        $area = $relativeSegments[0] ?? null;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isDirectory = str_ends_with($path, '/');

        $kind = 'configuration-part';
        if ($isDirectory) {
            $kind = $configurationPath === null ? 'configuration-root' : 'configuration-directory';
        } elseif (str_starts_with($mediaTypeBase, 'image/')) {
            $kind = 'configuration-image';
        } elseif (in_array($mediaTypeBase, ['text/xml', 'application/xml'], true) || $extension === 'xml') {
            $kind = 'configuration-xml';
        }

        return [
            'configurationArea' => $area === '' ? null : $area,
            'configurationKind' => $kind,
            'configurationPath' => $configurationPath,
            'extension' => $extension === '' ? null : $extension,
        ];
    }

    private static function scriptMediaTypeFromPart(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'bsh' => 'application/x-beanshell',
            'class' => 'application/java-vm',
            'jar' => 'application/java-archive',
            'js', 'mjs' => 'application/javascript',
            'py' => 'text/x-python',
            'xba', 'xml' => 'text/xml',
            default => null,
        };
    }

    private static function scriptMediaTypeValid(string $path, string $mediaTypeBase): bool
    {
        if ($mediaTypeBase === '') {
            return false;
        }

        $expected = self::scriptMediaTypeFromPart($path);
        if ($expected === null) {
            return true;
        }

        $expectedBase = self::mediaTypeReport($expected)['mediaTypeBase'];
        if ($expectedBase === 'application/javascript') {
            return in_array($mediaTypeBase, ['application/javascript', 'text/javascript'], true);
        }
        if ($expectedBase === 'text/xml') {
            return in_array($mediaTypeBase, ['text/xml', 'application/xml'], true);
        }
        if ($expectedBase === 'text/x-python') {
            return in_array($mediaTypeBase, ['text/x-python', 'application/x-python'], true);
        }

        return $mediaTypeBase === $expectedBase;
    }

    /**
     * @return array<string, string|null>
     */
    private static function scriptPackagePathInfo(string $path, string $mediaTypeBase): array
    {
        $trimmed = trim($path, '/');
        $segments = $trimmed === '' ? [] : explode('/', $trimmed);
        $container = strtolower($segments[0] ?? '');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $scriptPath = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : null;
        $module = $extension === '' ? basename($path) : basename($path, '.' . $extension);
        $library = null;
        if ($container === 'basic' && isset($segments[1]) && $segments[1] !== '') {
            $library = $segments[1];
        }

        $kind = match ($extension) {
            'bsh' => 'beanshell',
            'class' => 'java-class',
            'jar' => 'java-archive',
            'js', 'mjs' => 'javascript',
            'py' => 'python',
            'xba' => 'basic-module',
            'xml' => $container === 'basic' ? 'basic-module' : 'script-xml',
            default => match ($mediaTypeBase) {
                'application/javascript', 'text/javascript' => 'javascript',
                'application/java-archive' => 'java-archive',
                'application/java-vm' => 'java-class',
                'text/x-python' => 'python',
                default => $container === 'basic' ? 'basic-package-part' : 'script-package-part',
            },
        };

        return [
            'scriptContainer' => $container === '' ? null : $container,
            'scriptKind' => $kind,
            'scriptPath' => $scriptPath,
            'scriptLibrary' => $library,
            'scriptModule' => $module === '' ? null : $module,
            'extension' => $extension === '' ? null : $extension,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @return array<string, mixed>
     */
    private static function embeddedObjectPackageMetadata(ZipPackage $package, array $manifestEntries): array
    {
        $objectPackageRootParts = self::embeddedObjectPackageRootParts($manifestEntries);
        $entriesByPath = [];
        foreach ($package->entries() as $entry) {
            $entriesByPath[$entry->name] = $entry;
        }
        $manifestEntriesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (is_string($packagePath) && $packagePath !== '') {
                $manifestEntriesByPath[$packagePath] = $entry;
            }
        }

        $items = [];
        $byRootPart = [];
        $rootParts = [];
        $objectTypes = [];
        $issueCodes = [];
        foreach ($objectPackageRootParts as $rootPart => $rootEntry) {
            $objectPath = rtrim($rootPart, '/');
            $declaredContainedParts = [];
            $declaredContainedPartItems = [];
            $existingDeclaredContainedParts = [];
            $missingDeclaredContainedParts = [];
            $encryptedDeclaredContainedParts = [];
            foreach ($manifestEntries as $entry) {
                $packagePath = $entry['packagePath'] ?? null;
                if (!is_string($packagePath) || $packagePath === '' || $packagePath === $rootPart || !str_starts_with($packagePath, $rootPart)) {
                    continue;
                }

                $declaredContainedParts[$packagePath] = true;
                $summary = self::embeddedObjectManifestPartSummary($entry);
                $declaredContainedPartItems[] = $summary;
                if (($entry['exists'] ?? false) === true) {
                    $existingDeclaredContainedParts[] = $summary;
                } else {
                    $missingDeclaredContainedParts[] = $summary;
                }
                if (($entry['encrypted'] ?? false) === true) {
                    $encryptedDeclaredContainedParts[] = $summary;
                }
            }

            $containedParts = [];
            $undeclaredContainedParts = [];
            $containedByteLength = 0;
            $containedRoleCounts = [];
            $containedRoleByteLengths = [];
            $containedRoleCompressedByteLengths = [];
            $containedMediaFamilyCounts = [];
            $containedMediaFamilyByteLengths = [];
            $containedMediaFamilyCompressedByteLengths = [];
            foreach ($entriesByPath as $path => $entry) {
                if ($path === $rootPart || !str_starts_with($path, $rootPart) || $entry->isDirectory()) {
                    continue;
                }

                $containedClassification = self::embeddedObjectContainedPartClassification(
                    $entry->name,
                    $manifestEntriesByPath[$entry->name] ?? null
                );
                $containedRole = $containedClassification['containedRole'];
                $containedMediaFamily = $containedClassification['containedMediaFamily'];
                $partSummary = [
                    'path' => $entry->name,
                    'part' => $entry->name,
                    'byteLength' => $entry->uncompressedSize,
                    'compressedByteLength' => $entry->compressedSize,
                    'compressionMethod' => $entry->compressionMethod,
                    'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
                    'crc32' => $entry->crc32Hex(),
                    'declaredInManifest' => isset($declaredContainedParts[$entry->name]),
                    'containedRole' => $containedRole,
                    'containedMediaFamily' => $containedMediaFamily,
                ];
                $containedParts[] = $partSummary;
                $containedByteLength += $entry->uncompressedSize;
                $containedRoleCounts[$containedRole] = ($containedRoleCounts[$containedRole] ?? 0) + 1;
                $containedRoleByteLengths[$containedRole] = ($containedRoleByteLengths[$containedRole] ?? 0) + $entry->uncompressedSize;
                $containedRoleCompressedByteLengths[$containedRole] = ($containedRoleCompressedByteLengths[$containedRole] ?? 0) + $entry->compressedSize;
                $containedMediaFamilyCounts[$containedMediaFamily] = ($containedMediaFamilyCounts[$containedMediaFamily] ?? 0) + 1;
                $containedMediaFamilyByteLengths[$containedMediaFamily] = ($containedMediaFamilyByteLengths[$containedMediaFamily] ?? 0) + $entry->uncompressedSize;
                $containedMediaFamilyCompressedByteLengths[$containedMediaFamily] = ($containedMediaFamilyCompressedByteLengths[$containedMediaFamily] ?? 0) + $entry->compressedSize;
                if (!isset($declaredContainedParts[$entry->name])) {
                    $undeclaredContainedParts[] = $partSummary;
                }
            }

            usort($containedParts, static fn (array $left, array $right): int => strcmp((string) $left['part'], (string) $right['part']));
            usort($undeclaredContainedParts, static fn (array $left, array $right): int => strcmp((string) $left['part'], (string) $right['part']));
            ksort($containedRoleCounts, SORT_STRING);
            ksort($containedRoleByteLengths, SORT_STRING);
            ksort($containedRoleCompressedByteLengths, SORT_STRING);
            ksort($containedMediaFamilyCounts, SORT_STRING);
            ksort($containedMediaFamilyByteLengths, SORT_STRING);
            ksort($containedMediaFamilyCompressedByteLengths, SORT_STRING);

            $exists = $containedParts !== [] || isset($entriesByPath[$rootPart]);
            $encrypted = ($rootEntry['encrypted'] ?? false) === true || $encryptedDeclaredContainedParts !== [];
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-embedded-object-package-missing';
            }
            if ($missingDeclaredContainedParts !== []) {
                $issues[] = 'odf-embedded-object-package-missing-declared-part';
            }
            if ($undeclaredContainedParts !== []) {
                $issues[] = 'odf-embedded-object-package-undeclared-contained-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-embedded-object-package-encrypted';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $objectType = self::objectTypeForMediaType((string) ($rootEntry['mediaTypeBase'] ?? $rootEntry['mediaType'] ?? ''));
            $item = [
                'rootPart' => $rootPart,
                'objectPath' => $objectPath,
                'fullPath' => $rootEntry['path'] ?? null,
                'manifestIndex' => $rootEntry['manifestIndex'] ?? null,
                'objectType' => $objectType,
                'mediaType' => $rootEntry['mediaType'] ?? '',
                'mediaTypeBase' => $rootEntry['mediaTypeBase'] ?? '',
                'version' => $rootEntry['version'] ?? null,
                'preferredViewMode' => $rootEntry['preferredViewMode'] ?? null,
                'exists' => $exists,
                'encrypted' => $encrypted,
                'canExposeBytes' => false,
                'byteExposurePolicy' => 'embedded-object-package-bytes-blocked',
                'reviewPolicy' => 'embedded-object-package-metadata-only',
                'containedPartCount' => count($containedParts),
                'containedByteLength' => $containedParts === [] ? null : $containedByteLength,
                'containedRoleCounts' => $containedRoleCounts,
                'containedRoleByteLengths' => $containedRoleByteLengths,
                'containedRoleCompressedByteLengths' => $containedRoleCompressedByteLengths,
                'containedMediaFamilyCounts' => $containedMediaFamilyCounts,
                'containedMediaFamilyByteLengths' => $containedMediaFamilyByteLengths,
                'containedMediaFamilyCompressedByteLengths' => $containedMediaFamilyCompressedByteLengths,
                'containedParts' => $containedParts,
                'declaredContainedPartCount' => count($declaredContainedPartItems),
                'declaredContainedParts' => $declaredContainedPartItems,
                'existingDeclaredContainedPartCount' => count($existingDeclaredContainedParts),
                'missingDeclaredContainedPartCount' => count($missingDeclaredContainedParts),
                'missingDeclaredContainedParts' => $missingDeclaredContainedParts,
                'encryptedDeclaredContainedPartCount' => count($encryptedDeclaredContainedParts),
                'undeclaredContainedPartCount' => count($undeclaredContainedParts),
                'undeclaredContainedParts' => $undeclaredContainedParts,
                'issues' => $issues,
            ];
            $items[] = $item;
            $byRootPart[$rootPart] = $item;
            $rootParts[] = $rootPart;
            if (!in_array($objectType, $objectTypes, true)) {
                $objectTypes[] = $objectType;
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-embedded-object-package-missing', $item['issues'], true))),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'containedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['containedPartCount'], $items)),
            'containedByteLength' => array_sum(array_map(static fn (array $item): int => (int) ($item['containedByteLength'] ?? 0), $items)),
            'containedRoleCounts' => self::sumEmbeddedObjectPackageBuckets($items, 'containedRoleCounts'),
            'containedRoleByteLengths' => self::sumEmbeddedObjectPackageBuckets($items, 'containedRoleByteLengths'),
            'containedRoleCompressedByteLengths' => self::sumEmbeddedObjectPackageBuckets($items, 'containedRoleCompressedByteLengths'),
            'containedMediaFamilyCounts' => self::sumEmbeddedObjectPackageBuckets($items, 'containedMediaFamilyCounts'),
            'containedMediaFamilyByteLengths' => self::sumEmbeddedObjectPackageBuckets($items, 'containedMediaFamilyByteLengths'),
            'containedMediaFamilyCompressedByteLengths' => self::sumEmbeddedObjectPackageBuckets($items, 'containedMediaFamilyCompressedByteLengths'),
            'declaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['declaredContainedPartCount'], $items)),
            'existingDeclaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['existingDeclaredContainedPartCount'], $items)),
            'missingDeclaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['missingDeclaredContainedPartCount'], $items)),
            'encryptedDeclaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['encryptedDeclaredContainedPartCount'], $items)),
            'undeclaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['undeclaredContainedPartCount'], $items)),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'rootParts' => $rootParts,
            'objectTypes' => $objectTypes,
            'byteExposurePolicy' => 'embedded-object-package-bytes-blocked',
            'reviewPolicy' => 'embedded-object-package-metadata-only',
            'byRootPart' => $byRootPart,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed>|null $manifestEntry
     * @return array{containedRole:string, containedMediaFamily:string}
     */
    private static function embeddedObjectContainedPartClassification(string $part, ?array $manifestEntry): array
    {
        $mediaTypeBase = '';
        if (is_array($manifestEntry)) {
            $mediaTypeBase = (string) ($manifestEntry['mediaTypeBase'] ?? '');
            if ($mediaTypeBase === '') {
                $mediaTypeBase = self::mediaTypeReport((string) ($manifestEntry['mediaType'] ?? ''))['mediaTypeBase'];
            }
        }

        $mediaFamily = self::embeddedObjectContainedMediaFamily($part, $mediaTypeBase);
        $role = match ($mediaFamily) {
            'rdf' => 'rdf-metadata',
            'image', 'audio', 'video' => 'media-resource',
            'xml' => 'document-xml',
            default => 'package-part',
        };

        return [
            'containedRole' => $role,
            'containedMediaFamily' => $mediaFamily,
        ];
    }

    private static function embeddedObjectContainedMediaFamily(string $part, string $mediaTypeBase): string
    {
        $base = strtolower(trim($mediaTypeBase));
        if ($base === 'application/rdf+xml' || self::isRdfPartName($part)) {
            return 'rdf';
        }

        $mediaFamily = self::mediaResourceFamilyFromMediaTypeBase($base);
        if ($mediaFamily !== null) {
            return $mediaFamily;
        }
        if (self::isXmlMediaTypeBase($base) || self::isEmbeddedObjectXmlPartName($part)) {
            return 'xml';
        }

        $packageFamily = self::mediaResourceFamilyFromPackagePart($part);
        if ($packageFamily !== null) {
            return $packageFamily;
        }

        return 'other';
    }

    private static function isXmlMediaTypeBase(string $mediaTypeBase): bool
    {
        $base = strtolower(trim($mediaTypeBase));

        return $base === 'text/xml'
            || $base === 'application/xml'
            || str_ends_with($base, '+xml');
    }

    private static function isEmbeddedObjectXmlPartName(string $part): bool
    {
        $name = strtolower(basename($part));

        return in_array($name, ['content.xml', 'styles.xml', 'meta.xml', 'settings.xml'], true)
            || str_ends_with($name, '.xml');
    }

    private static function mediaResourceFamilyFromMediaTypeBase(string $mediaTypeBase): ?string
    {
        $base = strtolower(trim($mediaTypeBase));
        if (str_starts_with($base, 'image/')) {
            return 'image';
        }
        if (str_starts_with($base, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($base, 'video/')) {
            return 'video';
        }

        return null;
    }

    private static function mediaResourceFamilyFromPackagePart(string $part): ?string
    {
        $normalized = strtolower(ltrim($part, '/'));
        if (str_starts_with($normalized, 'pictures/')) {
            return 'image';
        }

        return match (strtolower(pathinfo($normalized, PATHINFO_EXTENSION))) {
            'apng', 'avif', 'bmp', 'gif', 'jpe', 'jpeg', 'jpg', 'png', 'svg', 'tif', 'tiff', 'webp' => 'image',
            'aac', 'aif', 'aiff', 'flac', 'm4a', 'mp3', 'oga', 'ogg', 'opus', 'wav', 'weba' => 'audio',
            'avi', 'm4v', 'mov', 'mp4', 'mpeg', 'mpg', 'ogv', 'webm' => 'video',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, int>
     */
    private static function sumEmbeddedObjectPackageBuckets(array $items, string $key): array
    {
        $summary = [];
        foreach ($items as $item) {
            $buckets = $item[$key] ?? [];
            if (!is_array($buckets)) {
                continue;
            }
            foreach ($buckets as $bucket => $value) {
                if (!is_string($bucket)) {
                    continue;
                }
                $summary[$bucket] = ($summary[$bucket] ?? 0) + (int) $value;
            }
        }
        ksort($summary, SORT_STRING);

        return $summary;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function embeddedObjectManifestPartSummary(array $entry): array
    {
        return [
            'fullPath' => $entry['path'] ?? null,
            'path' => $entry['path'] ?? null,
            'part' => $entry['packagePath'] ?? null,
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'mediaType' => $entry['mediaType'] ?? '',
            'mediaTypeBase' => $entry['mediaTypeBase'] ?? '',
            'exists' => ($entry['exists'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
            'declaredSize' => $entry['declaredSize'] ?? null,
            'storedByteLength' => $entry['storedByteLength'] ?? null,
            'compressedByteLength' => $entry['compressedByteLength'] ?? null,
            'compressionMethod' => $entry['compressionMethod'] ?? null,
            'compressionMethodName' => $entry['compressionMethodName'] ?? null,
            'storedCrc32' => $entry['storedCrc32'] ?? null,
            'diagnostics' => $entry['diagnostics'] ?? [],
        ];
    }

    private static function isSignaturePackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'meta-inf/')
            && str_ends_with($normalized, 'signatures.xml')
            && !str_ends_with($normalized, '/');
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @param array<string, mixed> $manifestRootAttributes
     * @param array<string, mixed> $manifestRootExtensionElements
     * @return array<string, mixed>
     */
    private static function manifestReview(
        array $entries,
        array $undeclaredPackageEntries = [],
        array $manifestRootAttributes = [],
        array $manifestRootExtensionElements = []
    ): array
    {
        $summary = [
            'count' => count($entries),
            'existsCount' => 0,
            'missingCount' => 0,
            'directoryCount' => 0,
            'encryptedCount' => 0,
            'declaredSizeMismatchCount' => 0,
            'storedByteLength' => 0,
            'compressedByteLength' => 0,
            'exposableByteLength' => 0,
            'declaredSize' => 0,
            'declaredSizeItemCount' => 0,
            'declaredSizeItems' => [],
            'largestDeclaredSizeItemLimit' => self::MANIFEST_DECLARED_SIZE_LARGEST_ITEM_LIMIT,
            'largestDeclaredSizeItemCount' => 0,
            'largestDeclaredSizeItems' => [],
            'storedCompressionMethodCount' => 0,
            'deflatedCompressionMethodCount' => 0,
            'unsupportedCompressionMethodCount' => 0,
            'manifestFileEntryCount' => count($entries),
            'manifestFileEntryOrder' => [],
            'manifestPartReferenceSuffixCount' => 0,
            'manifestPartReferenceQueryCount' => 0,
            'manifestPartReferenceFragmentCount' => 0,
            'manifestPartReferenceSuffixItems' => [],
            'manifestPathKindCounts' => [],
            'manifestTopLevelSegmentCounts' => [],
            'manifestPathExtensionCounts' => [],
            'manifestPathShapeItems' => [],
            'manifestMediaFamilyCounts' => [],
            'manifestMediaFamilyByteLengths' => [],
            'manifestMediaFamilyCompressedByteLengths' => [],
            'manifestMediaFamilyItems' => [],
            'manifestRootAttributeCount' => $manifestRootAttributes['attributeCount'] ?? 0,
            'manifestRootAttributeNames' => $manifestRootAttributes['attributeNames'] ?? [],
            'manifestRootAttributes' => $manifestRootAttributes['attributes'] ?? [],
            'manifestRootCustomAttributeCount' => $manifestRootAttributes['customAttributeCount'] ?? 0,
            'manifestRootCustomAttributeNames' => $manifestRootAttributes['customAttributeNames'] ?? [],
            'manifestRootCustomAttributes' => $manifestRootAttributes['customAttributes'] ?? [],
            'manifestRootCustomAttributeMap' => $manifestRootAttributes['customAttributeMap'] ?? [],
            'manifestRootNamespaceDeclarationCount' => $manifestRootAttributes['namespaceDeclarationCount'] ?? 0,
            'manifestRootNamespaceDeclarationNames' => $manifestRootAttributes['namespaceDeclarationNames'] ?? [],
            'manifestRootNamespaceDeclarations' => $manifestRootAttributes['namespaceDeclarations'] ?? [],
            'manifestRootNamespaceDeclarationMap' => $manifestRootAttributes['namespaceDeclarationMap'] ?? [],
            'manifestRootExtensionElementCount' => $manifestRootExtensionElements['extensionElementCount'] ?? 0,
            'manifestRootExtensionElementNames' => $manifestRootExtensionElements['extensionElementNames'] ?? [],
            'manifestRootExtensionElements' => $manifestRootExtensionElements['extensionElements'] ?? [],
            'manifestCustomAttributeEntryCount' => 0,
            'manifestCustomAttributeCount' => 0,
            'manifestCustomAttributeNames' => [],
            'manifestCustomAttributeItems' => [],
            'manifestCustomChildElementEntryCount' => 0,
            'manifestCustomChildElementCount' => 0,
            'manifestCustomChildElementNames' => [],
            'manifestCustomChildElementItems' => [],
            'uriEncodedPackageReferenceCount' => 0,
            'uriEncodedPackageReferenceItems' => [],
            'zipTimestampEntryCount' => 0,
            'zipTimestampSourceCounts' => [],
            'zipTimestampItems' => [],
            'zipInvalidDosTimestampEntryCount' => 0,
            'zipInvalidDosTimestampItems' => [],
            'embeddedObjectPackagePartCount' => 0,
            'embeddedObjectRootCount' => 0,
            'embeddedObjectContainedPartCount' => 0,
            'embeddedObjectPackageItems' => [],
            'objectReplacementPackagePartCount' => 0,
            'objectReplacementPackageItems' => [],
            'scriptPackagePartCount' => 0,
            'scriptPackageItems' => [],
            'configurationPackagePartCount' => 0,
            'configurationPackageItems' => [],
            'fontPackagePartCount' => 0,
            'fontPackageItems' => [],
            'rdfMetadataPartCount' => 0,
            'rdfMetadataItems' => [],
            'layoutCachePartCount' => 0,
            'layoutCacheItems' => [],
            'metaInfSidecarPackagePartCount' => 0,
            'metaInfSidecarPackageItems' => [],
            'databasePackagePartCount' => 0,
            'databasePackageItems' => [],
            'missingMediaTypeCount' => 0,
            'missingMediaTypeItems' => [],
            'missingManifestDeclaredPartCount' => 0,
            'missingManifestDeclaredItems' => [],
            'missingManifestDeclaredRoleCounts' => [],
            'missingManifestDeclaredByteExposurePolicyCounts' => [],
            'missingManifestDeclaredMediaTypeBaseCounts' => [],
            'diagnosticCount' => 0,
            'diagnosticCodeCounts' => [],
            'diagnostics' => [],
            'undeclaredPackageEntryCount' => count($undeclaredPackageEntries),
            'undeclaredPackageEntries' => $undeclaredPackageEntries,
            'items' => [],
            'missingItems' => [],
            'directoryItems' => [],
            'encryptedItems' => [],
            'declaredSizeMismatches' => [],
        ];

        foreach ($entries as $entry) {
            $item = self::manifestReviewItem($entry);
            $summary['items'][] = $item;
            $summary['manifestFileEntryOrder'][] = self::manifestFileEntryOrderItem($entry);
            $summary['manifestPathShapeItems'][] = self::manifestPathShapeItem($entry);
            $pathShape = is_array($entry['pathShape'] ?? null) ? $entry['pathShape'] : [];
            $pathKind = $pathShape['kind'] ?? null;
            if (is_string($pathKind) && $pathKind !== '') {
                $summary['manifestPathKindCounts'][$pathKind] = ($summary['manifestPathKindCounts'][$pathKind] ?? 0) + 1;
            }
            $topLevelSegment = $pathShape['topLevelSegment'] ?? null;
            if (is_string($topLevelSegment) && $topLevelSegment !== '') {
                $summary['manifestTopLevelSegmentCounts'][$topLevelSegment] = ($summary['manifestTopLevelSegmentCounts'][$topLevelSegment] ?? 0) + 1;
            }
            $pathExtension = $pathShape['extension'] ?? null;
            if (is_string($pathExtension) && $pathExtension !== '') {
                $summary['manifestPathExtensionCounts'][$pathExtension] = ($summary['manifestPathExtensionCounts'][$pathExtension] ?? 0) + 1;
            }
            if (is_string($entry['pathSuffix'] ?? null)) {
                $summary['manifestPartReferenceSuffixItems'][] = self::manifestPartReferenceSuffixItem($entry);
            }
            if (is_string($entry['pathQuery'] ?? null)) {
                ++$summary['manifestPartReferenceQueryCount'];
            }
            if (is_string($entry['pathFragment'] ?? null)) {
                ++$summary['manifestPartReferenceFragmentCount'];
            }
            if (is_string($entry['manifestMediaFamily'] ?? null)) {
                $family = $entry['manifestMediaFamily'];
                $summary['manifestMediaFamilyCounts'][$family] = ($summary['manifestMediaFamilyCounts'][$family] ?? 0) + 1;
                if (is_int($entry['storedByteLength'] ?? null)) {
                    $summary['manifestMediaFamilyByteLengths'][$family] = ($summary['manifestMediaFamilyByteLengths'][$family] ?? 0) + $entry['storedByteLength'];
                }
                if (is_int($entry['compressedByteLength'] ?? null)) {
                    $summary['manifestMediaFamilyCompressedByteLengths'][$family] = ($summary['manifestMediaFamilyCompressedByteLengths'][$family] ?? 0) + $entry['compressedByteLength'];
                }
                $summary['manifestMediaFamilyItems'][] = self::manifestMediaFamilyItem($entry);
            }
            $customManifestAttributes = is_array($entry['customManifestAttributes'] ?? null)
                ? $entry['customManifestAttributes']
                : [];
            if ($customManifestAttributes !== []) {
                ++$summary['manifestCustomAttributeEntryCount'];
                $summary['manifestCustomAttributeCount'] += count($customManifestAttributes);
                foreach ($entry['customManifestAttributeNames'] ?? [] as $attributeName) {
                    if (is_string($attributeName) && $attributeName !== '' && !in_array($attributeName, $summary['manifestCustomAttributeNames'], true)) {
                        $summary['manifestCustomAttributeNames'][] = $attributeName;
                    }
                }
                $summary['manifestCustomAttributeItems'][] = [
                    'manifestIndex' => $entry['manifestIndex'] ?? null,
                    'fullPath' => $entry['path'],
                    'path' => $entry['path'],
                    'part' => $entry['packagePath'] ?? null,
                    'packagePath' => $entry['packagePath'] ?? null,
                    'customManifestAttributeCount' => count($customManifestAttributes),
                    'customManifestAttributeNames' => $entry['customManifestAttributeNames'] ?? [],
                    'customManifestAttributes' => $customManifestAttributes,
                    'customManifestAttributeMap' => $entry['customManifestAttributeMap'] ?? [],
                    'manifestNamespaceDeclarationCount' => $entry['manifestNamespaceDeclarationCount'] ?? 0,
                    'manifestNamespaceDeclarationNames' => $entry['manifestNamespaceDeclarationNames'] ?? [],
                    'manifestNamespaceDeclarations' => $entry['manifestNamespaceDeclarations'] ?? [],
                    'manifestNamespaceDeclarationMap' => $entry['manifestNamespaceDeclarationMap'] ?? [],
                ];
            }
            $customManifestChildElements = is_array($entry['customManifestChildElements'] ?? null)
                ? $entry['customManifestChildElements']
                : [];
            if ($customManifestChildElements !== []) {
                ++$summary['manifestCustomChildElementEntryCount'];
                $summary['manifestCustomChildElementCount'] += count($customManifestChildElements);
                foreach ($entry['customManifestChildElementNames'] ?? [] as $elementName) {
                    if (is_string($elementName) && $elementName !== '' && !in_array($elementName, $summary['manifestCustomChildElementNames'], true)) {
                        $summary['manifestCustomChildElementNames'][] = $elementName;
                    }
                }
                $summary['manifestCustomChildElementItems'][] = [
                    'manifestIndex' => $entry['manifestIndex'] ?? null,
                    'fullPath' => $entry['path'],
                    'path' => $entry['path'],
                    'part' => $entry['packagePath'] ?? null,
                    'packagePath' => $entry['packagePath'] ?? null,
                    'customManifestChildElementCount' => count($customManifestChildElements),
                    'customManifestChildElementNames' => $entry['customManifestChildElementNames'] ?? [],
                    'customManifestChildElements' => $customManifestChildElements,
                    'manifestChildElementCount' => $entry['manifestChildElementCount'] ?? 0,
                    'manifestChildElementNames' => $entry['manifestChildElementNames'] ?? [],
                ];
            }
            if (($entry['uriEncodedPackageReference'] ?? false) === true) {
                ++$summary['uriEncodedPackageReferenceCount'];
                $summary['uriEncodedPackageReferenceItems'][] = $item;
            }
            if (is_int($entry['zipModifiedAt'] ?? null) || is_string($entry['zipTimestampSource'] ?? null)) {
                ++$summary['zipTimestampEntryCount'];
                $summary['zipTimestampItems'][] = $item;
                $source = (string) ($entry['zipTimestampSource'] ?? '');
                if ($source !== '') {
                    $summary['zipTimestampSourceCounts'][$source] = ($summary['zipTimestampSourceCounts'][$source] ?? 0) + 1;
                }
            }
            if (($entry['zipIsDosTimestampValid'] ?? true) !== true) {
                ++$summary['zipInvalidDosTimestampEntryCount'];
                $summary['zipInvalidDosTimestampItems'][] = $item;
            }
            if (($entry['embeddedObjectPackagePart'] ?? false) === true) {
                ++$summary['embeddedObjectPackagePartCount'];
                $summary['embeddedObjectPackageItems'][] = $item;
                if (($entry['embeddedObjectRoot'] ?? false) === true) {
                    ++$summary['embeddedObjectRootCount'];
                } else {
                    ++$summary['embeddedObjectContainedPartCount'];
                }
            }
            if (($entry['objectReplacementPackagePart'] ?? false) === true) {
                ++$summary['objectReplacementPackagePartCount'];
                $summary['objectReplacementPackageItems'][] = $item;
            }
            if (($entry['scriptPackagePart'] ?? false) === true) {
                ++$summary['scriptPackagePartCount'];
                $summary['scriptPackageItems'][] = $item;
            }
            if (($entry['configurationPackagePart'] ?? false) === true) {
                ++$summary['configurationPackagePartCount'];
                $summary['configurationPackageItems'][] = $item;
            }
            if (($entry['fontPackagePart'] ?? false) === true) {
                ++$summary['fontPackagePartCount'];
                $summary['fontPackageItems'][] = $item;
            }
            if (($entry['rdfMetadataPart'] ?? false) === true) {
                ++$summary['rdfMetadataPartCount'];
                $summary['rdfMetadataItems'][] = $item;
            }
            if (($entry['layoutCachePackagePart'] ?? false) === true) {
                ++$summary['layoutCachePartCount'];
                $summary['layoutCacheItems'][] = $item;
            }
            if (($entry['metaInfSidecarPackagePart'] ?? false) === true) {
                ++$summary['metaInfSidecarPackagePartCount'];
                $summary['metaInfSidecarPackageItems'][] = $item;
            }
            if (($entry['databasePackagePart'] ?? false) === true) {
                ++$summary['databasePackagePartCount'];
                $summary['databasePackageItems'][] = $item;
            }
            if (($entry['missingMediaType'] ?? false) === true) {
                ++$summary['missingMediaTypeCount'];
                $summary['missingMediaTypeItems'][] = $item;
            }
            foreach (is_array($entry['diagnostics'] ?? null) ? $entry['diagnostics'] : [] as $diagnostic) {
                if (!is_string($diagnostic) || $diagnostic === '') {
                    continue;
                }

                $summary['diagnostics'][] = [
                    'code' => $diagnostic,
                    'path' => (string) ($entry['path'] ?? ''),
                    'packagePath' => is_string($entry['packagePath'] ?? null) ? $entry['packagePath'] : null,
                    'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                ];
                $summary['diagnosticCodeCounts'][$diagnostic] = ($summary['diagnosticCodeCounts'][$diagnostic] ?? 0) + 1;
            }
            if (($entry['exists'] ?? false) === true) {
                ++$summary['existsCount'];
            } else {
                ++$summary['missingCount'];
                $summary['missingItems'][] = $item;
                $missingRoles = self::missingManifestDeclaredRoles($entry);
                $missingDeclaredItem = $item;
                $missingDeclaredItem['roles'] = $missingRoles;
                $summary['missingManifestDeclaredItems'][] = $missingDeclaredItem;
                foreach ($missingRoles as $role) {
                    $summary['missingManifestDeclaredRoleCounts'][$role] = ($summary['missingManifestDeclaredRoleCounts'][$role] ?? 0) + 1;
                }
                $byteExposurePolicy = $entry['byteExposurePolicy'] ?? null;
                if (is_string($byteExposurePolicy) && $byteExposurePolicy !== '') {
                    $summary['missingManifestDeclaredByteExposurePolicyCounts'][$byteExposurePolicy] = ($summary['missingManifestDeclaredByteExposurePolicyCounts'][$byteExposurePolicy] ?? 0) + 1;
                }
                $mediaTypeBase = is_string($entry['mediaTypeBase'] ?? null) && $entry['mediaTypeBase'] !== ''
                    ? $entry['mediaTypeBase']
                    : '(missing)';
                $summary['missingManifestDeclaredMediaTypeBaseCounts'][$mediaTypeBase] = ($summary['missingManifestDeclaredMediaTypeBaseCounts'][$mediaTypeBase] ?? 0) + 1;
            }
            if (($entry['isDirectory'] ?? false) === true) {
                ++$summary['directoryCount'];
                $summary['directoryItems'][] = $item;
            }
            if (($entry['encrypted'] ?? false) === true) {
                ++$summary['encryptedCount'];
                $summary['encryptedItems'][] = $item;
            }
            if (($entry['declaredSizeMismatch'] ?? false) === true) {
                ++$summary['declaredSizeMismatchCount'];
                $summary['declaredSizeMismatches'][] = $item;
            }
            if (is_int($entry['storedByteLength'] ?? null)) {
                $summary['storedByteLength'] += $entry['storedByteLength'];
            }
            if (is_int($entry['compressedByteLength'] ?? null)) {
                $summary['compressedByteLength'] += $entry['compressedByteLength'];
            }
            if (($entry['canExposeBytes'] ?? false) === true && is_int($entry['byteLength'] ?? null)) {
                $summary['exposableByteLength'] += $entry['byteLength'];
            }
            if (is_int($entry['declaredSize'] ?? null)) {
                $summary['declaredSize'] += $entry['declaredSize'];
                $summary['declaredSizeItems'][] = $item;
            }
            if (($entry['compressionMethod'] ?? null) === 0) {
                ++$summary['storedCompressionMethodCount'];
            } elseif (($entry['compressionMethod'] ?? null) === 8) {
                ++$summary['deflatedCompressionMethodCount'];
            } elseif (is_int($entry['compressionMethod'] ?? null)) {
                ++$summary['unsupportedCompressionMethodCount'];
            }
        }
        $summary['manifestPartReferenceSuffixCount'] = count($summary['manifestPartReferenceSuffixItems']);
        $summary['missingManifestDeclaredPartCount'] = count($summary['missingManifestDeclaredItems']);
        $summary['preferredViewModes'] = self::manifestPreferredViewModeSummary($entries);
        $summary['manifestEncryption'] = self::manifestEncryptionSummary($entries);
        $summary['diagnosticCount'] = count($summary['diagnostics']);
        $summary['declaredSizeItemCount'] = count($summary['declaredSizeItems']);
        $summary['largestDeclaredSizeItems'] = self::largestDeclaredSizeItems(
            $summary['declaredSizeItems'],
            self::MANIFEST_DECLARED_SIZE_LARGEST_ITEM_LIMIT
        );
        $summary['largestDeclaredSizeItemCount'] = count($summary['largestDeclaredSizeItems']);
        $summary += self::manifestDeclaredSizeRoleSummary($entries);
        sort($summary['manifestCustomAttributeNames'], SORT_STRING);
        sort($summary['manifestCustomChildElementNames'], SORT_STRING);
        ksort($summary['manifestPathKindCounts'], SORT_STRING);
        ksort($summary['manifestTopLevelSegmentCounts'], SORT_STRING);
        ksort($summary['manifestPathExtensionCounts'], SORT_STRING);
        ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
        ksort($summary['manifestMediaFamilyByteLengths'], SORT_STRING);
        ksort($summary['manifestMediaFamilyCompressedByteLengths'], SORT_STRING);
        ksort($summary['zipTimestampSourceCounts'], SORT_STRING);
        ksort($summary['missingManifestDeclaredRoleCounts'], SORT_STRING);
        ksort($summary['missingManifestDeclaredByteExposurePolicyCounts'], SORT_STRING);
        ksort($summary['missingManifestDeclaredMediaTypeBaseCounts'], SORT_STRING);
        ksort($summary['diagnosticCodeCounts'], SORT_STRING);

        return $summary;
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private static function missingManifestDeclaredRoles(array $entry): array
    {
        $path = is_string($entry['packagePath'] ?? null) ? $entry['packagePath'] : '';
        $mediaType = (string) ($entry['mediaType'] ?? '');
        $roles = [];

        if ($path === 'content.xml') {
            $roles[] = 'odf-content';
        }
        if ($path === 'styles.xml') {
            $roles[] = 'odf-styles';
        }
        if ($path === 'meta.xml') {
            $roles[] = 'odf-meta';
        }
        if ($path === 'settings.xml') {
            $roles[] = 'odf-settings';
        }
        if (($entry['thumbnailPackagePart'] ?? false) === true || self::isThumbnailPackagePartName($path)) {
            $roles[] = 'package-thumbnail';
        }
        if (($entry['signaturePackagePart'] ?? false) === true || self::isSignaturePackagePartName($path)) {
            $roles[] = 'package-signature';
        }
        if (($entry['configurationPackagePart'] ?? false) === true || self::isConfigurationPackagePartName($path)) {
            $roles[] = 'configuration-package';
        }
        if (($entry['fontPackagePart'] ?? false) === true || self::isFontPackagePart($path, $mediaType)) {
            $roles[] = 'font-package';
        }

        $rdfMetadataPart = ($entry['rdfMetadataPart'] ?? false) === true || self::isRdfMetadataPart($path, $mediaType);
        if ($rdfMetadataPart) {
            $roles[] = 'rdf-metadata';
        }
        if (($entry['objectReplacementPackagePart'] ?? false) === true || self::isObjectReplacementPackagePartName($path)) {
            $roles[] = 'object-replacement';
        }
        if (($entry['layoutCachePackagePart'] ?? false) === true || self::isLayoutCachePackagePartName($path)) {
            $roles[] = 'layout-cache';
        }
        if (($entry['metaInfSidecarPackagePart'] ?? false) === true || self::isMetaInfSidecarPackagePartName($path)) {
            $roles[] = 'meta-inf-sidecar';
        }
        if (($entry['databasePackagePart'] ?? false) === true || self::isDatabasePackagePartName($path)) {
            $roles[] = 'database-package';
        }

        $embeddedObjectPackagePart = ($entry['embeddedObjectPackagePart'] ?? false) === true;
        if ($embeddedObjectPackagePart) {
            $roles[] = ($entry['embeddedObjectRoot'] ?? false) === true ? 'embedded-object-root' : 'embedded-object-part';
        }

        $roles[] = 'manifest-declared';
        if (!str_ends_with($path, '/') && self::isMediaResourceManifestEntry($entry)) {
            $roles[] = 'media-resource';
        }
        if (($entry['scriptPackagePart'] ?? false) === true || self::isScriptPackagePartName($path)) {
            $roles[] = 'script-package';
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private static function manifestEncryptionSummary(array $entries): array
    {
        $items = [];
        $encryptedParts = [];
        $checksumTypeCounts = [];
        $algorithmNameCounts = [];
        $keyDerivationNameCounts = [];
        $startKeyGenerationNameCounts = [];
        $unknownChildNameCounts = [];
        $issueCodeCounts = [];
        $recordCount = 0;
        $unknownChildCount = 0;
        $issueItemCount = 0;

        foreach ($entries as $entry) {
            if (($entry['encrypted'] ?? false) !== true) {
                continue;
            }

            $encryption = is_array($entry['encryption'] ?? null) ? $entry['encryption'] : [];
            $records = is_array($encryption['records'] ?? null) ? $encryption['records'] : [];
            if ($records === [] && $encryption !== []) {
                $records = [$encryption];
            }

            $recordCount += count($records);
            $checksumTypes = [];
            $algorithmNames = [];
            $keyDerivationNames = [];
            $startKeyGenerationNames = [];
            $unknownChildNames = [];
            $issueCodes = [];

            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $checksumType = $record['checksumType'] ?? null;
                if (is_string($checksumType) && $checksumType !== '') {
                    $checksumTypes[] = $checksumType;
                    $checksumTypeCounts[$checksumType] = ($checksumTypeCounts[$checksumType] ?? 0) + 1;
                }

                foreach (self::encryptionNamedRecords($record, 'algorithm', 'algorithms') as $algorithm) {
                    $name = $algorithm['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $algorithmNames[] = $name;
                        $algorithmNameCounts[$name] = ($algorithmNameCounts[$name] ?? 0) + 1;
                    }
                }

                foreach (self::encryptionNamedRecords($record, 'keyDerivation', 'keyDerivations') as $keyDerivation) {
                    $name = $keyDerivation['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $keyDerivationNames[] = $name;
                        $keyDerivationNameCounts[$name] = ($keyDerivationNameCounts[$name] ?? 0) + 1;
                    }
                }

                foreach (self::encryptionNamedRecords($record, 'startKeyGeneration', 'startKeyGenerations') as $startKeyGeneration) {
                    $name = $startKeyGeneration['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $startKeyGenerationNames[] = $name;
                        $startKeyGenerationNameCounts[$name] = ($startKeyGenerationNameCounts[$name] ?? 0) + 1;
                    }
                }

                foreach (is_array($record['unknownChildren'] ?? null) ? $record['unknownChildren'] : [] as $unknownChild) {
                    if (!is_array($unknownChild)) {
                        continue;
                    }

                    $name = $unknownChild['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $unknownChildNames[] = $name;
                        $unknownChildNameCounts[$name] = ($unknownChildNameCounts[$name] ?? 0) + 1;
                        ++$unknownChildCount;
                    }
                }

                foreach (is_array($record['issueCodes'] ?? null) ? $record['issueCodes'] : [] as $issueCode) {
                    if (is_string($issueCode) && $issueCode !== '') {
                        $issueCodes[] = $issueCode;
                    }
                }
            }

            foreach (is_array($encryption['issueCodes'] ?? null) ? $encryption['issueCodes'] : [] as $issueCode) {
                if (is_string($issueCode) && $issueCode !== '') {
                    $issueCodes[] = $issueCode;
                }
            }

            $issueCodes = array_values(array_unique($issueCodes));
            if ($issueCodes !== []) {
                ++$issueItemCount;
                foreach ($issueCodes as $issueCode) {
                    $issueCodeCounts[$issueCode] = ($issueCodeCounts[$issueCode] ?? 0) + 1;
                }
            }

            $path = (string) ($entry['path'] ?? $entry['fullPath'] ?? '');
            $part = $entry['packagePath'] ?? $entry['part'] ?? null;
            if (is_string($part) && $part !== '') {
                $encryptedParts[] = $part;
            }

            $items[] = self::withoutEmptyValues([
                'manifestIndex' => $entry['manifestIndex'] ?? null,
                'fullPath' => $path,
                'path' => $path,
                'part' => $part,
                'packagePath' => $part,
                'mediaType' => $entry['mediaType'] ?? null,
                'encryptionRecordCount' => count($records),
                'checksumTypes' => array_values(array_unique($checksumTypes)),
                'algorithmNames' => array_values(array_unique($algorithmNames)),
                'keyDerivationNames' => array_values(array_unique($keyDerivationNames)),
                'startKeyGenerationNames' => array_values(array_unique($startKeyGenerationNames)),
                'unknownChildNames' => array_values(array_unique($unknownChildNames)),
                'issueCodes' => $issueCodes,
                'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
            ]);
        }

        ksort($checksumTypeCounts, SORT_STRING);
        ksort($algorithmNameCounts, SORT_STRING);
        ksort($keyDerivationNameCounts, SORT_STRING);
        ksort($startKeyGenerationNameCounts, SORT_STRING);
        ksort($unknownChildNameCounts, SORT_STRING);
        ksort($issueCodeCounts, SORT_STRING);

        return [
            'count' => count($items),
            'encryptedItemCount' => count($items),
            'recordCount' => $recordCount,
            'encryptedParts' => $encryptedParts,
            'checksumTypeCounts' => $checksumTypeCounts,
            'algorithmNameCounts' => $algorithmNameCounts,
            'keyDerivationNameCounts' => $keyDerivationNameCounts,
            'startKeyGenerationNameCounts' => $startKeyGenerationNameCounts,
            'unknownChildCount' => $unknownChildCount,
            'unknownChildNameCounts' => $unknownChildNameCounts,
            'issueItemCount' => $issueItemCount,
            'issueCodeCounts' => $issueCodeCounts,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return list<array<string, mixed>>
     */
    private static function encryptionNamedRecords(array $record, string $singleKey, string $pluralKey): array
    {
        $items = [];
        if (is_array($record[$pluralKey] ?? null)) {
            foreach ($record[$pluralKey] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        } elseif (is_array($record[$singleKey] ?? null)) {
            $items[] = $record[$singleKey];
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private static function manifestPreferredViewModeSummary(array $entries): array
    {
        $items = [];
        $nonRootItems = [];
        $invalidTokenItems = [];
        $modeCounts = [];
        $issueCodeCounts = [];
        $rootMode = null;
        $definedModeCount = 0;
        $namespacedTokenCount = 0;

        foreach ($entries as $entry) {
            $mode = trim((string) ($entry['preferredViewMode'] ?? ''));
            if ($mode === '') {
                continue;
            }

            $path = (string) ($entry['path'] ?? '');
            $isRootEntry = $path === '/';
            $classification = self::preferredViewModeClassification($mode);
            $issues = [];
            if (!$isRootEntry) {
                $issues[] = 'odf-preferred-view-mode-non-root-entry';
            }
            if (!$classification['validToken']) {
                $issues[] = 'odf-preferred-view-mode-invalid-token';
            }

            $review = self::withoutEmptyValues([
                'manifestIndex' => $entry['manifestIndex'] ?? null,
                'fullPath' => $path,
                'path' => $path,
                'part' => $entry['packagePath'] ?? null,
                'packagePath' => $entry['packagePath'] ?? null,
                'mediaType' => $entry['mediaType'] ?? null,
                'preferredViewMode' => $mode,
                'applicableToRootEntry' => $isRootEntry,
                'validToken' => $classification['validToken'],
                'definedMode' => $classification['definedMode'],
                'namespacedToken' => $classification['namespacedToken'],
                'modeFamily' => $classification['modeFamily'],
                'issues' => $issues,
            ]);
            $items[] = $review;
            $modeCounts[$mode] = ($modeCounts[$mode] ?? 0) + 1;

            if ($isRootEntry) {
                $rootMode = $mode;
            } else {
                $nonRootItems[] = $review;
            }
            if ($classification['definedMode']) {
                ++$definedModeCount;
            }
            if ($classification['namespacedToken']) {
                ++$namespacedTokenCount;
            }
            if (!$classification['validToken']) {
                $invalidTokenItems[] = $review;
            }
            foreach ($issues as $issue) {
                $issueCodeCounts[$issue] = ($issueCodeCounts[$issue] ?? 0) + 1;
            }
        }

        ksort($modeCounts, SORT_STRING);
        ksort($issueCodeCounts, SORT_STRING);

        return [
            'count' => count($items),
            'itemCount' => count($items),
            'rootMode' => $rootMode,
            'definedModeCount' => $definedModeCount,
            'namespacedTokenCount' => $namespacedTokenCount,
            'invalidTokenCount' => count($invalidTokenItems),
            'nonRootEntryCount' => count($nonRootItems),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => ($item['issues'] ?? []) !== [])),
            'issueCodes' => array_keys($issueCodeCounts),
            'issueCodeCounts' => $issueCodeCounts,
            'modeCounts' => $modeCounts,
            'nonRootItems' => $nonRootItems,
            'invalidTokenItems' => $invalidTokenItems,
            'items' => $items,
        ];
    }

    /**
     * @return array{validToken:bool, definedMode:bool, namespacedToken:bool, modeFamily:string}
     */
    private static function preferredViewModeClassification(string $mode): array
    {
        if (isset(self::PREFERRED_VIEW_MODE_VALUES[$mode])) {
            return [
                'validToken' => true,
                'definedMode' => true,
                'namespacedToken' => false,
                'modeFamily' => 'defined',
            ];
        }

        if (self::isNamespacedToken($mode)) {
            return [
                'validToken' => true,
                'definedMode' => false,
                'namespacedToken' => true,
                'modeFamily' => 'namespaced-token',
            ];
        }

        return [
            'validToken' => false,
            'definedMode' => false,
            'namespacedToken' => false,
            'modeFamily' => 'invalid-token',
        ];
    }

    private static function isNamespacedToken(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9._-]*:[A-Za-z_][A-Za-z0-9._-]*$/', $value) === 1;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function largestDeclaredSizeItems(array $items, int $limit): array
    {
        usort($items, static function (array $left, array $right): int {
            $byDeclaredSize = ((int) ($right['declaredSize'] ?? 0))
                <=> ((int) ($left['declaredSize'] ?? 0));
            if ($byDeclaredSize !== 0) {
                return $byDeclaredSize;
            }

            $byStoredSize = ((int) ($right['storedByteLength'] ?? 0))
                <=> ((int) ($left['storedByteLength'] ?? 0));
            if ($byStoredSize !== 0) {
                return $byStoredSize;
            }

            return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        return array_slice($items, 0, max(0, $limit));
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array{manifestDeclaredSizeRoleCount:int, manifestDeclaredSizeRoleCounts:array<string, int>, manifestDeclaredSizeRoleByteLengths:array<string, int>, manifestDeclaredSizeRoleMismatchCounts:array<string, int>, manifestDeclaredSizeRoleExistingCounts:array<string, int>, manifestDeclaredSizeRoleMissingCounts:array<string, int>, manifestDeclaredSizeRoleSummaries:list<array{role:string, declaredSizeItemCount:int, declaredSize:int, declaredSizeMismatchCount:int, existingCount:int, missingCount:int, parts:list<string>}>}
     */
    private static function manifestDeclaredSizeRoleSummary(array $entries): array
    {
        $roleCounts = [];
        $roleByteLengths = [];
        $roleMismatchCounts = [];
        $roleExistingCounts = [];
        $roleMissingCounts = [];
        $partsByRole = [];

        foreach ($entries as $entry) {
            $declaredSize = $entry['declaredSize'] ?? ($entry['size'] ?? null);
            if (!is_int($declaredSize)) {
                continue;
            }

            $part = self::manifestMediaTypePartLabel($entry);
            foreach (self::missingManifestDeclaredRoles($entry) as $role) {
                $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
                $roleByteLengths[$role] = ($roleByteLengths[$role] ?? 0) + $declaredSize;
                if (($entry['declaredSizeMismatch'] ?? false) === true) {
                    $roleMismatchCounts[$role] = ($roleMismatchCounts[$role] ?? 0) + 1;
                }
                if (($entry['exists'] ?? false) === true) {
                    $roleExistingCounts[$role] = ($roleExistingCounts[$role] ?? 0) + 1;
                } else {
                    $roleMissingCounts[$role] = ($roleMissingCounts[$role] ?? 0) + 1;
                }
                $partsByRole[$role] ??= [];
                if (!in_array($part, $partsByRole[$role], true)) {
                    $partsByRole[$role][] = $part;
                }
            }
        }

        ksort($roleCounts, SORT_STRING);
        ksort($roleByteLengths, SORT_STRING);
        ksort($roleMismatchCounts, SORT_STRING);
        ksort($roleExistingCounts, SORT_STRING);
        ksort($roleMissingCounts, SORT_STRING);
        ksort($partsByRole, SORT_STRING);

        $summaries = [];
        foreach (array_keys($roleCounts) as $role) {
            $summaries[] = [
                'role' => $role,
                'declaredSizeItemCount' => $roleCounts[$role],
                'declaredSize' => $roleByteLengths[$role] ?? 0,
                'declaredSizeMismatchCount' => $roleMismatchCounts[$role] ?? 0,
                'existingCount' => $roleExistingCounts[$role] ?? 0,
                'missingCount' => $roleMissingCounts[$role] ?? 0,
                'parts' => $partsByRole[$role] ?? [],
            ];
        }

        return [
            'manifestDeclaredSizeRoleCount' => count($roleCounts),
            'manifestDeclaredSizeRoleCounts' => $roleCounts,
            'manifestDeclaredSizeRoleByteLengths' => $roleByteLengths,
            'manifestDeclaredSizeRoleMismatchCounts' => $roleMismatchCounts,
            'manifestDeclaredSizeRoleExistingCounts' => $roleExistingCounts,
            'manifestDeclaredSizeRoleMissingCounts' => $roleMissingCounts,
            'manifestDeclaredSizeRoleSummaries' => $summaries,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestPathShapeItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'part' => $entry['packagePath'] ?? null,
            'packagePath' => $entry['packagePath'] ?? null,
            'pathShape' => $entry['pathShape'] ?? [],
            'packagePathShape' => $entry['packagePathShape'] ?? null,
            'mediaType' => $entry['mediaType'],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestMediaFamilyItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'packagePath' => $entry['packagePath'] ?? null,
            'mediaType' => $entry['mediaType'],
            'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
            'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'storedByteLength' => $entry['storedByteLength'] ?? null,
            'compressedByteLength' => $entry['compressedByteLength'] ?? null,
            'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestReviewItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'part' => $entry['packagePath'] ?? null,
            'partReference' => $entry['pathReference'] ?? null,
            'partSuffix' => $entry['pathSuffix'] ?? null,
            'partQuery' => $entry['pathQuery'] ?? null,
            'partFragment' => $entry['pathFragment'] ?? null,
            'packagePath' => $entry['packagePath'] ?? null,
            'pathReference' => $entry['pathReference'] ?? null,
            'pathSuffix' => $entry['pathSuffix'] ?? null,
            'pathQuery' => $entry['pathQuery'] ?? null,
            'pathFragment' => $entry['pathFragment'] ?? null,
            'pathShape' => $entry['pathShape'] ?? [],
            'packagePathShape' => $entry['packagePathShape'] ?? null,
            'uriEncodedPackageReference' => ($entry['uriEncodedPackageReference'] ?? false) === true,
            'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
            'mediaType' => $entry['mediaType'],
            'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
            'mediaTypeHasParameters' => ($entry['mediaTypeHasParameters'] ?? false) === true,
            'mediaTypeParameterCount' => $entry['mediaTypeParameterCount'] ?? 0,
            'mediaTypeParameters' => $entry['mediaTypeParameters'] ?? [],
            'mediaTypeParameterMap' => $entry['mediaTypeParameterMap'] ?? [],
            'version' => $entry['version'] ?? null,
            'preferredViewMode' => $entry['preferredViewMode'] ?? null,
            'manifestAttributeCount' => $entry['manifestAttributeCount'] ?? 0,
            'manifestAttributeNames' => $entry['manifestAttributeNames'] ?? [],
            'manifestAttributes' => $entry['manifestAttributes'] ?? [],
            'customManifestAttributeCount' => $entry['customManifestAttributeCount'] ?? 0,
            'customManifestAttributeNames' => $entry['customManifestAttributeNames'] ?? [],
            'customManifestAttributes' => $entry['customManifestAttributes'] ?? [],
            'customManifestAttributeMap' => $entry['customManifestAttributeMap'] ?? [],
            'manifestNamespaceDeclarationCount' => $entry['manifestNamespaceDeclarationCount'] ?? 0,
            'manifestNamespaceDeclarationNames' => $entry['manifestNamespaceDeclarationNames'] ?? [],
            'manifestNamespaceDeclarations' => $entry['manifestNamespaceDeclarations'] ?? [],
            'manifestNamespaceDeclarationMap' => $entry['manifestNamespaceDeclarationMap'] ?? [],
            'manifestChildElementCount' => $entry['manifestChildElementCount'] ?? 0,
            'manifestChildElementNames' => $entry['manifestChildElementNames'] ?? [],
            'manifestChildElements' => $entry['manifestChildElements'] ?? [],
            'customManifestChildElementCount' => $entry['customManifestChildElementCount'] ?? 0,
            'customManifestChildElementNames' => $entry['customManifestChildElementNames'] ?? [],
            'customManifestChildElements' => $entry['customManifestChildElements'] ?? [],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'encryption' => $entry['encryption'] ?? null,
            'encryptionRecordCount' => is_array($entry['encryption'] ?? null) ? ($entry['encryption']['recordCount'] ?? 0) : 0,
            'encryptionIssueCodes' => is_array($entry['encryption'] ?? null) ? ($entry['encryption']['issueCodes'] ?? []) : [],
            'embeddedObjectPackagePart' => ($entry['embeddedObjectPackagePart'] ?? false) === true,
            'embeddedObjectRootPart' => $entry['embeddedObjectRootPart'] ?? null,
            'embeddedObjectPath' => $entry['embeddedObjectPath'] ?? null,
            'embeddedObjectType' => $entry['embeddedObjectType'] ?? null,
            'embeddedObjectRoot' => ($entry['embeddedObjectRoot'] ?? false) === true,
            'embeddedObjectContainedPart' => ($entry['embeddedObjectContainedPart'] ?? false) === true,
            'embeddedObjectMediaType' => $entry['embeddedObjectMediaType'] ?? null,
            'objectReplacementPackagePart' => ($entry['objectReplacementPackagePart'] ?? false) === true,
            'scriptPackagePart' => ($entry['scriptPackagePart'] ?? false) === true,
            'signaturePackagePart' => ($entry['signaturePackagePart'] ?? false) === true,
            'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
            'fontPackagePart' => ($entry['fontPackagePart'] ?? false) === true,
            'rdfMetadataPart' => ($entry['rdfMetadataPart'] ?? false) === true,
            'layoutCachePackagePart' => ($entry['layoutCachePackagePart'] ?? false) === true,
            'metaInfSidecarPackagePart' => ($entry['metaInfSidecarPackagePart'] ?? false) === true,
            'databasePackagePart' => ($entry['databasePackagePart'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'byteLength' => $entry['byteLength'] ?? null,
            'storedByteLength' => $entry['storedByteLength'] ?? null,
            'compressedByteLength' => $entry['compressedByteLength'] ?? null,
            'compressionMethod' => $entry['compressionMethod'] ?? null,
            'compressionMethodName' => $entry['compressionMethodName'] ?? null,
            'crc32' => $entry['crc32'] ?? null,
            'storedCrc32' => $entry['storedCrc32'] ?? null,
            'byteSha256' => $entry['byteSha256'] ?? null,
            'zipModifiedAt' => $entry['zipModifiedAt'] ?? null,
            'zipTimestampSource' => $entry['zipTimestampSource'] ?? null,
            'zipModifiedDosTime' => $entry['zipModifiedDosTime'] ?? null,
            'zipModifiedDosDate' => $entry['zipModifiedDosDate'] ?? null,
            'zipHasDosTimestamp' => ($entry['zipHasDosTimestamp'] ?? false) === true,
            'zipIsDosTimestampValid' => ($entry['zipIsDosTimestampValid'] ?? true) === true,
            'zipDosModifiedAt' => $entry['zipDosModifiedAt'] ?? null,
            'zipExtendedModifiedAt' => $entry['zipExtendedModifiedAt'] ?? null,
            'zipNtfsModifiedAt' => $entry['zipNtfsModifiedAt'] ?? null,
            'zipCentralModifiedAt' => $entry['zipCentralModifiedAt'] ?? null,
            'zipCentralTimestampSource' => $entry['zipCentralTimestampSource'] ?? null,
            'zipLocalExtendedModifiedAt' => $entry['zipLocalExtendedModifiedAt'] ?? null,
            'zipLocalNtfsModifiedAt' => $entry['zipLocalNtfsModifiedAt'] ?? null,
            'zipLocalModifiedAt' => $entry['zipLocalModifiedAt'] ?? null,
            'zipLocalTimestampSource' => $entry['zipLocalTimestampSource'] ?? null,
            'zipTimestampIssues' => $entry['zipTimestampIssues'] ?? [],
            'declaredSize' => $entry['declaredSize'] ?? null,
            'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
            'missingMediaType' => ($entry['missingMediaType'] ?? false) === true,
            'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
            'diagnostics' => $entry['diagnostics'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestFileEntryOrderItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'part' => $entry['packagePath'] ?? null,
            'partReference' => $entry['pathReference'] ?? null,
            'partSuffix' => $entry['pathSuffix'] ?? null,
            'partQuery' => $entry['pathQuery'] ?? null,
            'partFragment' => $entry['pathFragment'] ?? null,
            'pathShape' => $entry['pathShape'] ?? [],
            'packagePathShape' => $entry['packagePathShape'] ?? null,
            'uriEncodedPackageReference' => ($entry['uriEncodedPackageReference'] ?? false) === true,
            'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
            'mediaType' => $entry['mediaType'],
            'manifestAttributeCount' => $entry['manifestAttributeCount'] ?? 0,
            'manifestAttributeNames' => $entry['manifestAttributeNames'] ?? [],
            'customManifestAttributeCount' => $entry['customManifestAttributeCount'] ?? 0,
            'customManifestAttributeNames' => $entry['customManifestAttributeNames'] ?? [],
            'customManifestAttributes' => $entry['customManifestAttributes'] ?? [],
            'customManifestAttributeMap' => $entry['customManifestAttributeMap'] ?? [],
            'manifestNamespaceDeclarationCount' => $entry['manifestNamespaceDeclarationCount'] ?? 0,
            'manifestNamespaceDeclarationNames' => $entry['manifestNamespaceDeclarationNames'] ?? [],
            'manifestNamespaceDeclarations' => $entry['manifestNamespaceDeclarations'] ?? [],
            'manifestNamespaceDeclarationMap' => $entry['manifestNamespaceDeclarationMap'] ?? [],
            'manifestChildElementCount' => $entry['manifestChildElementCount'] ?? 0,
            'manifestChildElementNames' => $entry['manifestChildElementNames'] ?? [],
            'customManifestChildElementCount' => $entry['customManifestChildElementCount'] ?? 0,
            'customManifestChildElementNames' => $entry['customManifestChildElementNames'] ?? [],
            'customManifestChildElements' => $entry['customManifestChildElements'] ?? [],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'embeddedObjectPackagePart' => ($entry['embeddedObjectPackagePart'] ?? false) === true,
            'embeddedObjectRootPart' => $entry['embeddedObjectRootPart'] ?? null,
            'embeddedObjectType' => $entry['embeddedObjectType'] ?? null,
            'embeddedObjectRoot' => ($entry['embeddedObjectRoot'] ?? false) === true,
            'embeddedObjectContainedPart' => ($entry['embeddedObjectContainedPart'] ?? false) === true,
            'objectReplacementPackagePart' => ($entry['objectReplacementPackagePart'] ?? false) === true,
            'scriptPackagePart' => ($entry['scriptPackagePart'] ?? false) === true,
            'signaturePackagePart' => ($entry['signaturePackagePart'] ?? false) === true,
            'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
            'fontPackagePart' => ($entry['fontPackagePart'] ?? false) === true,
            'rdfMetadataPart' => ($entry['rdfMetadataPart'] ?? false) === true,
            'layoutCachePackagePart' => ($entry['layoutCachePackagePart'] ?? false) === true,
            'metaInfSidecarPackagePart' => ($entry['metaInfSidecarPackagePart'] ?? false) === true,
            'databasePackagePart' => ($entry['databasePackagePart'] ?? false) === true,
            'zipModifiedAt' => $entry['zipModifiedAt'] ?? null,
            'zipTimestampSource' => $entry['zipTimestampSource'] ?? null,
            'zipLocalModifiedAt' => $entry['zipLocalModifiedAt'] ?? null,
            'zipLocalTimestampSource' => $entry['zipLocalTimestampSource'] ?? null,
            'zipTimestampIssues' => $entry['zipTimestampIssues'] ?? [],
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'missingMediaType' => ($entry['missingMediaType'] ?? false) === true,
            'diagnostics' => $entry['diagnostics'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestPartReferenceSuffixItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'part' => $entry['packagePath'] ?? null,
            'partReference' => $entry['pathReference'] ?? null,
            'partSuffix' => $entry['pathSuffix'] ?? null,
            'partQuery' => $entry['pathQuery'] ?? null,
            'partFragment' => $entry['pathFragment'] ?? null,
            'uriEncodedPackageReference' => ($entry['uriEncodedPackageReference'] ?? false) === true,
            'mediaType' => $entry['mediaType'],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'embeddedObjectPackagePart' => ($entry['embeddedObjectPackagePart'] ?? false) === true,
            'embeddedObjectRootPart' => $entry['embeddedObjectRootPart'] ?? null,
            'embeddedObjectType' => $entry['embeddedObjectType'] ?? null,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
        ];
    }

    private static function compressionMethodName(int $method): string
    {
        return match ($method) {
            0 => 'stored',
            8 => 'deflated',
            default => 'unsupported',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function rawMimetypeEntryPreflight(string $bytes): array
    {
        $diagnostics = [];
        $length = strlen($bytes);
        if ($length < 30 || substr($bytes, 0, 4) !== "PK\x03\x04") {
            return [
                'exists' => false,
                'isFirstLocalEntry' => false,
                'entryName' => null,
                'mediaType' => null,
                'matchesOpenDocumentText' => false,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'usesDataDescriptor' => null,
                'localHeaderOffset' => 0,
                'localHeaderLength' => null,
                'localNameLength' => null,
                'localExtraFieldLength' => null,
                'contentBytes' => null,
                'compressedByteLength' => null,
                'uncompressedByteLength' => null,
                'payloadAvailable' => false,
                'byteExposurePolicy' => 'odf-mimetype-validation-only',
                'canExposeBytes' => false,
                'diagnostics' => ['odf-raw-mimetype-local-header-missing'],
            ];
        }

        $generalPurposeFlags = self::rawReadUInt16($bytes, 6);
        $compressionMethod = self::rawReadUInt16($bytes, 8);
        $compressedBytes = self::rawReadUInt32($bytes, 18);
        $uncompressedBytes = self::rawReadUInt32($bytes, 22);
        $nameLength = self::rawReadUInt16($bytes, 26);
        $extraFieldLength = self::rawReadUInt16($bytes, 28);
        if (
            $generalPurposeFlags === null
            || $compressionMethod === null
            || $compressedBytes === null
            || $uncompressedBytes === null
            || $nameLength === null
            || $extraFieldLength === null
        ) {
            return [
                'exists' => false,
                'isFirstLocalEntry' => false,
                'entryName' => null,
                'mediaType' => null,
                'matchesOpenDocumentText' => false,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'usesDataDescriptor' => null,
                'localHeaderOffset' => 0,
                'localHeaderLength' => null,
                'localNameLength' => null,
                'localExtraFieldLength' => null,
                'contentBytes' => null,
                'compressedByteLength' => null,
                'uncompressedByteLength' => null,
                'payloadAvailable' => false,
                'byteExposurePolicy' => 'odf-mimetype-validation-only',
                'canExposeBytes' => false,
                'diagnostics' => ['odf-raw-mimetype-local-header-truncated'],
            ];
        }

        $localHeaderLength = 30 + $nameLength + $extraFieldLength;
        $entryName = self::rawRangeAvailable($bytes, 30, $nameLength)
            ? substr($bytes, 30, $nameLength)
            : null;
        if ($entryName !== 'mimetype') {
            $diagnostics[] = 'odf-raw-mimetype-not-first-local-entry';
        }
        if ($compressionMethod !== 0) {
            $diagnostics[] = 'odf-raw-mimetype-compressed';
        }
        if (($generalPurposeFlags & 0x0008) !== 0) {
            $diagnostics[] = 'odf-raw-mimetype-uses-data-descriptor';
        }
        if ($extraFieldLength > 0) {
            $diagnostics[] = 'odf-raw-mimetype-local-extra-fields';
        }
        if ($compressionMethod === 0 && $compressedBytes !== $uncompressedBytes) {
            $diagnostics[] = 'odf-raw-mimetype-stored-size-mismatch';
        }

        $payloadOffset = $localHeaderLength;
        $payloadAvailable = $entryName === 'mimetype'
            && $compressionMethod === 0
            && self::rawRangeAvailable($bytes, $payloadOffset, $uncompressedBytes);
        $mediaType = $payloadAvailable
            ? substr($bytes, $payloadOffset, $uncompressedBytes)
            : null;
        if ($entryName === 'mimetype' && $compressionMethod === 0 && !$payloadAvailable) {
            $diagnostics[] = 'odf-raw-mimetype-bytes-unavailable';
        }
        if ($mediaType !== null && $mediaType !== self::TEXT_MIMETYPE) {
            $diagnostics[] = 'odf-raw-mimetype-mismatch';
        }

        return [
            'exists' => $entryName === 'mimetype',
            'isFirstLocalEntry' => $entryName === 'mimetype',
            'entryName' => $entryName,
            'mediaType' => $mediaType,
            'matchesOpenDocumentText' => $mediaType === self::TEXT_MIMETYPE,
            'compressionMethod' => $compressionMethod,
            'compressionMethodName' => self::compressionMethodName($compressionMethod),
            'usesDataDescriptor' => ($generalPurposeFlags & 0x0008) !== 0,
            'generalPurposeFlags' => $generalPurposeFlags,
            'localHeaderOffset' => 0,
            'localHeaderLength' => $localHeaderLength,
            'localNameLength' => $nameLength,
            'localExtraFieldLength' => $extraFieldLength,
            'contentBytes' => $mediaType === null ? null : strlen($mediaType),
            'compressedByteLength' => $compressedBytes,
            'uncompressedByteLength' => $uncompressedBytes,
            'payloadAvailable' => $payloadAvailable,
            'byteExposurePolicy' => 'odf-mimetype-validation-only',
            'canExposeBytes' => false,
            'diagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    private static function rawReadUInt16(string $bytes, int $offset): ?int
    {
        if (!self::rawRangeAvailable($bytes, $offset, 2)) {
            return null;
        }

        $value = unpack('vvalue', substr($bytes, $offset, 2));

        return is_array($value) ? (int) $value['value'] : null;
    }

    private static function rawReadUInt32(string $bytes, int $offset): ?int
    {
        if (!self::rawRangeAvailable($bytes, $offset, 4)) {
            return null;
        }

        $value = unpack('Vvalue', substr($bytes, $offset, 4));

        return is_array($value) ? (int) $value['value'] : null;
    }

    private static function rawRangeAvailable(string $bytes, int $offset, int $length): bool
    {
        return $offset >= 0
            && $length >= 0
            && $offset <= strlen($bytes)
            && $length <= strlen($bytes) - $offset;
    }

    private static function assertTextPackageMimetype(ZipPackage $package): void
    {
        if (!$package->has('mimetype')) {
            throw new \RuntimeException('ODT package is missing mimetype entry');
        }

        $entries = $package->localEntries();
        $first = $entries[0] ?? null;
        if (!$first instanceof ZipPackageEntry || $first->name !== 'mimetype' || $first->compressionMethod !== 0) {
            throw new \RuntimeException('ODT mimetype entry must be first in local-header order and stored without compression');
        }

        $localMimetype = self::localHeaderPreflightEntry($package, 'mimetype');
        if (is_array($localMimetype) && ((int) ($localMimetype['localExtraFieldLength'] ?? 0)) > 0) {
            throw new \RuntimeException('ODT mimetype entry must not contain local header extra fields');
        }

        if ($package->read('mimetype') !== self::TEXT_MIMETYPE) {
            throw new \RuntimeException('ODT mimetype entry must be application/vnd.oasis.opendocument.text');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function mimetypeEntryReview(ZipPackage $package): array
    {
        $entry = $package->entry('mimetype');
        $localHeader = self::localHeaderPreflightEntry($package, 'mimetype') ?? [];
        $centralDirectoryIndex = null;
        foreach ($package->entries() as $index => $candidate) {
            if ($candidate->name === 'mimetype') {
                $centralDirectoryIndex = $index;
                break;
            }
        }

        $localHeaderOrder = null;
        foreach ($package->localEntries() as $index => $candidate) {
            if ($candidate->name === 'mimetype') {
                $localHeaderOrder = $index;
                break;
            }
        }

        $centralExtraFields = self::zipExtraFieldReviewRecords($entry->centralExtraFields());
        $localExtraFields = is_array($localHeader['localExtraFieldRecords'] ?? null)
            ? $localHeader['localExtraFieldRecords']
            : [];
        $localExtraFieldLength = is_int($localHeader['localExtraFieldLength'] ?? null)
            ? $localHeader['localExtraFieldLength']
            : 0;

        return [
            'name' => $entry->name,
            'mediaType' => self::TEXT_MIMETYPE,
            'firstLocalEntry' => $localHeaderOrder === 0,
            'firstCentralDirectoryEntry' => $centralDirectoryIndex === 0,
            'localHeaderOrder' => $localHeaderOrder,
            'centralDirectoryIndex' => $centralDirectoryIndex,
            'localHeaderOffset' => $entry->localHeaderOffset,
            'localHeaderLength' => $localHeader['localHeaderLength'] ?? null,
            'localNameLength' => $localHeader['localNameLength'] ?? null,
            'localExtraFieldLength' => $localExtraFieldLength,
            'localExtraFieldRecordCount' => count($localExtraFields),
            'localExtraFieldIds' => $localHeader['localExtraFieldIds'] ?? [],
            'localExtraFieldRecords' => $localExtraFields,
            'centralExtraFieldLength' => strlen($entry->centralExtraFieldData),
            'centralExtraFieldRecordCount' => count($centralExtraFields),
            'centralExtraFieldIds' => array_map(
                static fn (array $field): int => (int) $field['id'],
                $centralExtraFields
            ),
            'centralExtraFieldRecords' => $centralExtraFields,
            'hasLocalExtraFields' => $localExtraFieldLength > 0,
            'hasCentralExtraFields' => $centralExtraFields !== [],
            'compressionMethod' => $entry->compressionMethod,
            'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
            'byteLength' => $entry->uncompressedSize,
            'compressedByteLength' => $entry->compressedSize,
            'crc32' => $entry->crc32Hex(),
            'canExposeBytes' => false,
            'byteExposurePolicy' => 'odf-mimetype-validation-only',
            'diagnostics' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function localHeaderPreflightEntry(ZipPackage $package, string $name): ?array
    {
        foreach ($package->localHeaderPreflight()['entries'] as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param list<array{id:int, data:string}> $fields
     * @return list<array{id:int, idHex:string, dataLength:int}>
     */
    private static function zipExtraFieldReviewRecords(array $fields): array
    {
        return array_map(
            static fn (array $field): array => [
                'id' => $field['id'],
                'idHex' => sprintf('%04x', $field['id']),
                'dataLength' => strlen($field['data']),
            ],
            $fields
        );
    }

    /**
     * @return array{
     *     version:string|null,
     *     rootAttributes:array<string, mixed>,
     *     rootExtensionElements:array<string, mixed>,
     *     entries:list<array{manifestIndex:int, path:string, packagePath:string|null, pathReference:string|null, pathSuffix:string|null, pathQuery:string|null, pathFragment:string|null, mediaType:string, version:string|null, size:int|null, preferredViewMode:string|null, encrypted:bool, encryption:array<string, mixed>|null}>
     * }
     */
    private static function parseManifest(string $xml): array
    {
        $dom = self::loadXml($xml, 'ODT manifest.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'manifest' || $root->namespaceURI !== self::MANIFEST_NAMESPACE) {
            throw new \InvalidArgumentException('ODF manifest XML must use the manifest:manifest root');
        }

        $entries = [];
        $manifestIndex = 0;
        $manifestVersion = self::optionalString(self::namespacedAttribute($root, self::MANIFEST_NAMESPACE, 'version'));
        $rootAttributes = self::manifestRootAttributeProvenance($root);
        $rootExtensionElements = self::manifestRootExtensionElementProvenance($root);
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->namespaceURI !== self::MANIFEST_NAMESPACE || $child->localName !== 'file-entry') {
                continue;
            }

            $path = self::normalizeManifestPath(self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'full-path') ?? '');
            $packageReference = self::manifestPackageReference($path);
            $pathReference = $packageReference['pathReference'];
            $packagePath = $packageReference['packagePath'];
            $pathShape = self::pathShape($pathReference ?? $path);
            $packagePathShape = is_string($packagePath) ? self::pathShape($packagePath) : null;
            $uriEncodedPackageReference = is_string($pathReference) && is_string($packagePath) && $pathReference !== $packagePath;
            $mediaType = self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'media-type') ?? '';
            $missingMediaType = $mediaType === '' && !str_ends_with($pathReference ?? $path, '/');
            $diagnostics = $missingMediaType ? ['odf-manifest-file-entry-missing-media-type'] : [];
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $attributeProvenance = self::manifestFileEntryAttributeProvenance($child);
            $childElementProvenance = self::manifestFileEntryChildElementProvenance($child);

            $size = self::manifestSize(
                self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'size'),
                $path
            );
            $encryption = self::manifestEncryption($child);
            $entries[] = [
                'manifestIndex' => $manifestIndex,
                'path' => $path,
                'packagePath' => $packagePath,
                'pathReference' => $pathReference,
                'pathSuffix' => $packageReference['pathSuffix'],
                'pathQuery' => $packageReference['pathQuery'],
                'pathFragment' => $packageReference['pathFragment'],
                'pathShape' => $pathShape,
                'packagePathShape' => $packagePathShape,
                'uriEncodedPackageReference' => $uriEncodedPackageReference,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'version' => self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'version'),
                'size' => $size,
                'preferredViewMode' => self::optionalString(self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'preferred-view-mode')),
                'manifestAttributeCount' => $attributeProvenance['attributeCount'],
                'manifestAttributeNames' => $attributeProvenance['attributeNames'],
                'manifestAttributes' => $attributeProvenance['attributes'],
                'customManifestAttributeCount' => $attributeProvenance['customAttributeCount'],
                'customManifestAttributeNames' => $attributeProvenance['customAttributeNames'],
                'customManifestAttributes' => $attributeProvenance['customAttributes'],
                'customManifestAttributeMap' => $attributeProvenance['customAttributeMap'],
                'manifestNamespaceDeclarationCount' => $attributeProvenance['namespaceDeclarationCount'],
                'manifestNamespaceDeclarationNames' => $attributeProvenance['namespaceDeclarationNames'],
                'manifestNamespaceDeclarations' => $attributeProvenance['namespaceDeclarations'],
                'manifestNamespaceDeclarationMap' => $attributeProvenance['namespaceDeclarationMap'],
                'manifestChildElementCount' => $childElementProvenance['childElementCount'],
                'manifestChildElementNames' => $childElementProvenance['childElementNames'],
                'manifestChildElements' => $childElementProvenance['childElements'],
                'customManifestChildElementCount' => $childElementProvenance['customChildElementCount'],
                'customManifestChildElementNames' => $childElementProvenance['customChildElementNames'],
                'customManifestChildElements' => $childElementProvenance['customChildElements'],
                'missingMediaType' => $missingMediaType,
                'encrypted' => $encryption !== null,
                'encryption' => $encryption,
                'diagnostics' => $diagnostics,
            ];
            ++$manifestIndex;
        }

        return [
            'version' => $manifestVersion,
            'rootAttributes' => $rootAttributes,
            'rootExtensionElements' => $rootExtensionElements,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function manifestFileEntryAttributeProvenance(\DOMElement $element): array
    {
        return self::manifestElementAttributeProvenance($element, self::MANIFEST_FILE_ENTRY_STRUCTURAL_ATTRIBUTES);
    }

    /**
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function manifestRootAttributeProvenance(\DOMElement $element): array
    {
        return self::manifestElementAttributeProvenance($element, self::MANIFEST_ROOT_STRUCTURAL_ATTRIBUTES);
    }

    /**
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function documentPartRootAttributeProvenance(\DOMElement $element): array
    {
        return self::manifestElementAttributeProvenance(
            $element,
            self::OFFICE_DOCUMENT_ROOT_STRUCTURAL_ATTRIBUTES,
            self::OFFICE_NAMESPACE
        );
    }

    /**
     * @return array{
     *     extensionElementCount:int,
     *     extensionElementNames:list<string>,
     *     extensionElements:list<array<string, mixed>>
     * }
     */
    private static function manifestRootExtensionElementProvenance(\DOMElement $element): array
    {
        $extensionElements = [];

        foreach (self::childElements($element) as $child) {
            if ($child->namespaceURI === self::MANIFEST_NAMESPACE && $child->localName === 'file-entry') {
                continue;
            }
            if ($child->namespaceURI === self::MANIFEST_NAMESPACE) {
                throw new \InvalidArgumentException('ODF manifest may only contain manifest:file-entry children in the manifest namespace');
            }

            $extensionElements[] = self::manifestChildElementRecord($child, false);
        }

        return [
            'extensionElementCount' => count($extensionElements),
            'extensionElementNames' => array_values(array_map(static fn (array $item): string => $item['name'], $extensionElements)),
            'extensionElements' => $extensionElements,
        ];
    }

    /**
     * @return array{
     *     childElementCount:int,
     *     childElementNames:list<string>,
     *     childElements:list<array<string, mixed>>,
     *     customChildElementCount:int,
     *     customChildElementNames:list<string>,
     *     customChildElements:list<array<string, mixed>>
     * }
     */
    private static function manifestFileEntryChildElementProvenance(\DOMElement $element): array
    {
        $childElements = [];
        $customChildElements = [];

        foreach (self::childElements($element) as $child) {
            $namespaceUri = (string) $child->namespaceURI;
            $structural = $namespaceUri === self::MANIFEST_NAMESPACE
                && isset(self::MANIFEST_FILE_ENTRY_STRUCTURAL_CHILD_ELEMENTS[$child->localName]);
            $record = self::manifestChildElementRecord($child, $structural);

            $childElements[] = $record;
            if (!$structural) {
                $customChildElements[] = $record;
            }
        }

        return [
            'childElementCount' => count($childElements),
            'childElementNames' => array_values(array_map(static fn (array $item): string => $item['name'], $childElements)),
            'childElements' => $childElements,
            'customChildElementCount' => count($customChildElements),
            'customChildElementNames' => array_values(array_map(static fn (array $item): string => $item['name'], $customChildElements)),
            'customChildElements' => $customChildElements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestChildElementRecord(\DOMElement $child, bool $structural): array
    {
        $record = [
            'name' => self::qualifiedElementName($child),
            'localName' => $child->localName,
            'structural' => $structural,
            'attributeCount' => $child->hasAttributes() ? $child->attributes->length : 0,
            'childElementCount' => count(self::childElements($child)),
        ];
        $namespaceUri = (string) $child->namespaceURI;
        if ($namespaceUri !== '') {
            $record['namespaceUri'] = $namespaceUri;
        }
        if ($child->prefix !== '') {
            $record['prefix'] = $child->prefix;
        }

        return $record;
    }

    /**
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function emptyAttributeProvenance(): array
    {
        return [
            'attributeCount' => 0,
            'attributeNames' => [],
            'attributes' => [],
            'customAttributeCount' => 0,
            'customAttributeNames' => [],
            'customAttributes' => [],
            'customAttributeMap' => [],
            'namespaceDeclarationCount' => 0,
            'namespaceDeclarationNames' => [],
            'namespaceDeclarations' => [],
            'namespaceDeclarationMap' => [],
        ];
    }

    /**
     * @param array<string, bool> $structuralAttributes
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function manifestElementAttributeProvenance(
        \DOMElement $element,
        array $structuralAttributes,
        string $structuralNamespace = self::MANIFEST_NAMESPACE
    ): array
    {
        $attributes = [];
        $customAttributes = [];
        $customAttributeMap = [];
        $namespaceDeclarations = self::manifestElementNamespaceDeclarations($element);
        $namespaceDeclarationMap = array_map(
            static fn (array $declaration): string => $declaration['namespaceUri'],
            $namespaceDeclarations
        );
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attribute) {
                if (!$attribute instanceof \DOMAttr) {
                    continue;
                }

                if ($attribute->prefix !== '' && is_string($attribute->namespaceURI) && $attribute->namespaceURI !== '' && $attribute->prefix !== 'xmlns') {
                    $namespaceName = 'xmlns:' . $attribute->prefix;
                    if (!isset($namespaceDeclarations[$namespaceName])) {
                        $namespaceDeclarations[$namespaceName] = [
                            'name' => $namespaceName,
                            'declaredPrefix' => $attribute->prefix,
                            'namespaceUri' => $attribute->namespaceURI,
                            'default' => false,
                        ];
                        $namespaceDeclarationMap[$namespaceName] = $attribute->namespaceURI;
                    }
                }

                if ($attribute->namespaceURI === self::XMLNS_NAMESPACE) {
                    $name = $attribute->name === 'xmlns'
                        ? 'xmlns'
                        : 'xmlns:' . $attribute->localName;
                    $declaredPrefix = $attribute->name === 'xmlns' ? '' : $attribute->localName;
                    $namespaceDeclarations[$name] = [
                        'name' => $name,
                        'declaredPrefix' => $declaredPrefix,
                        'namespaceUri' => $attribute->value,
                        'default' => $declaredPrefix === '',
                    ];
                    $namespaceDeclarationMap[$name] = $attribute->value;
                    continue;
                }

                $name = $attribute->prefix !== ''
                    ? $attribute->prefix . ':' . $attribute->localName
                    : $attribute->name;
                $structural = $attribute->namespaceURI === $structuralNamespace
                    && isset($structuralAttributes[$attribute->localName]);
                $record = [
                    'name' => $name,
                    'localName' => $attribute->localName,
                    'value' => $attribute->value,
                    'structural' => $structural,
                ];
                $namespaceUri = (string) $attribute->namespaceURI;
                if ($namespaceUri !== '') {
                    $record['namespaceUri'] = $namespaceUri;
                }
                if ($attribute->prefix !== '') {
                    $record['prefix'] = $attribute->prefix;
                }

                $attributes[$name] = $record;
                if (!$structural) {
                    $customAttributes[$name] = $record;
                    $customAttributeMap[$name] = $attribute->value;
                }
            }
        }

        ksort($attributes, SORT_STRING);
        ksort($customAttributes, SORT_STRING);
        ksort($customAttributeMap, SORT_STRING);
        ksort($namespaceDeclarations, SORT_STRING);
        ksort($namespaceDeclarationMap, SORT_STRING);

        return [
            'attributeCount' => count($attributes),
            'attributeNames' => array_keys($attributes),
            'attributes' => array_values($attributes),
            'customAttributeCount' => count($customAttributes),
            'customAttributeNames' => array_keys($customAttributes),
            'customAttributes' => array_values($customAttributes),
            'customAttributeMap' => $customAttributeMap,
            'namespaceDeclarationCount' => count($namespaceDeclarations),
            'namespaceDeclarationNames' => array_keys($namespaceDeclarations),
            'namespaceDeclarations' => array_values($namespaceDeclarations),
            'namespaceDeclarationMap' => $namespaceDeclarationMap,
        ];
    }

    /**
     * @return array<string, array{name:string, declaredPrefix:string, namespaceUri:string, default:bool}>
     */
    private static function manifestElementNamespaceDeclarations(\DOMElement $element): array
    {
        $xpath = new \DOMXPath($element->ownerDocument);
        $nodes = $xpath->query('namespace::*', $element);
        if ($nodes === false) {
            return [];
        }

        $declarations = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMNameSpaceNode || $node->nodeName === 'xmlns:xml') {
                continue;
            }

            $name = $node->nodeName;
            $declaredPrefix = $name === 'xmlns' ? '' : $node->localName;
            $declarations[$name] = [
                'name' => $name,
                'declaredPrefix' => $declaredPrefix,
                'namespaceUri' => (string) $node->nodeValue,
                'default' => $declaredPrefix === '',
            ];
        }

        ksort($declarations, SORT_STRING);

        return $declarations;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function manifestEncryption(\DOMElement $entry): ?array
    {
        $encryptionElements = self::childElements($entry, self::MANIFEST_NAMESPACE, 'encryption-data');
        if ($encryptionElements === []) {
            return null;
        }

        $records = array_map(
            static fn (\DOMElement $encryption): array => self::manifestEncryptionData($encryption),
            $encryptionElements
        );
        $data = $records[0] ?? [];
        $data['records'] = $records;
        $data['recordCount'] = count($records);

        if (count($records) > 1) {
            $issueCodes = is_array($data['issueCodes'] ?? null) ? $data['issueCodes'] : [];
            $issueCodes[] = 'odf-manifest-encryption-multiple-encryption-data';
            $issueCodes = array_values(array_unique($issueCodes));
            $data['issueCodes'] = $issueCodes;
            $data['issueCount'] = count($issueCodes);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionData(\DOMElement $encryption): array
    {
        $data = self::withoutNulls([
            'checksumType' => self::optionalString(self::namespacedAttribute($encryption, self::MANIFEST_NAMESPACE, 'checksum-type')),
            'checksum' => self::optionalString(self::namespacedAttribute($encryption, self::MANIFEST_NAMESPACE, 'checksum')),
        ]);

        $algorithms = array_map(
            static fn (\DOMElement $algorithm): array => self::manifestEncryptionAlgorithm($algorithm),
            self::childElements($encryption, self::MANIFEST_NAMESPACE, 'algorithm')
        );
        if ($algorithms !== []) {
            $data['algorithm'] = $algorithms[0];
            $data['algorithms'] = $algorithms;
            $data['algorithmCount'] = count($algorithms);
        }

        $keyDerivations = array_map(
            static fn (\DOMElement $keyDerivation): array => self::manifestEncryptionKeyDerivation($keyDerivation),
            self::childElements($encryption, self::MANIFEST_NAMESPACE, 'key-derivation')
        );
        if ($keyDerivations !== []) {
            $data['keyDerivation'] = $keyDerivations[0];
            $data['keyDerivations'] = $keyDerivations;
            $data['keyDerivationCount'] = count($keyDerivations);
        }

        $startKeyGenerations = array_map(
            static fn (\DOMElement $startKeyGeneration): array => self::manifestEncryptionStartKeyGeneration($startKeyGeneration),
            self::childElements($encryption, self::MANIFEST_NAMESPACE, 'start-key-generation')
        );
        if ($startKeyGenerations !== []) {
            $data['startKeyGeneration'] = $startKeyGenerations[0];
            $data['startKeyGenerations'] = $startKeyGenerations;
            $data['startKeyGenerationCount'] = count($startKeyGenerations);
        }

        $unknownChildren = self::manifestEncryptionUnknownChildren($encryption);
        if ($unknownChildren !== []) {
            $data['unknownChildCount'] = count($unknownChildren);
            $data['unknownChildren'] = $unknownChildren;
        }

        $issueCodes = [];
        if (count($algorithms) > 1) {
            $issueCodes[] = 'odf-manifest-encryption-multiple-algorithms';
        }
        if (count($keyDerivations) > 1) {
            $issueCodes[] = 'odf-manifest-encryption-multiple-key-derivations';
        }
        if (count($startKeyGenerations) > 1) {
            $issueCodes[] = 'odf-manifest-encryption-multiple-start-key-generations';
        }
        if ($unknownChildren !== []) {
            $issueCodes[] = 'odf-manifest-encryption-unknown-child';
        }
        if ($issueCodes !== []) {
            $data['issueCount'] = count($issueCodes);
            $data['issueCodes'] = $issueCodes;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionAlgorithm(\DOMElement $algorithm): array
    {
        $initialisationVector = self::namespacedAttribute($algorithm, self::MANIFEST_NAMESPACE, 'initialisation-vector')
            ?? self::namespacedAttribute($algorithm, self::MANIFEST_NAMESPACE, 'initialization-vector');

        return self::withoutNulls([
            'name' => self::optionalString(self::namespacedAttribute($algorithm, self::MANIFEST_NAMESPACE, 'algorithm-name')),
            'initialisationVector' => self::optionalString($initialisationVector),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionKeyDerivation(\DOMElement $keyDerivation): array
    {
        return self::withoutNulls([
            'name' => self::optionalString(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'key-derivation-name')),
            'keySize' => self::optionalInt(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'key-size')),
            'iterationCount' => self::optionalInt(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'iteration-count')),
            'salt' => self::optionalString(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'salt')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionStartKeyGeneration(\DOMElement $startKeyGeneration): array
    {
        return self::withoutNulls([
            'name' => self::optionalString(self::namespacedAttribute($startKeyGeneration, self::MANIFEST_NAMESPACE, 'start-key-generation-name')),
            'keySize' => self::optionalInt(self::namespacedAttribute($startKeyGeneration, self::MANIFEST_NAMESPACE, 'key-size')),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function manifestEncryptionUnknownChildren(\DOMElement $encryption): array
    {
        $unknownChildren = [];
        foreach (self::childElements($encryption) as $child) {
            if (
                $child->namespaceURI === self::MANIFEST_NAMESPACE
                && in_array($child->localName, ['algorithm', 'key-derivation', 'start-key-generation'], true)
            ) {
                continue;
            }

            $unknownChildren[] = self::withoutNulls([
                'name' => self::qualifiedElementName($child),
                'namespaceUri' => self::optionalString($child->namespaceURI),
                'localName' => $child->localName,
            ]);
        }

        return $unknownChildren;
    }

    private static function qualifiedElementName(\DOMElement $element): string
    {
        return $element->prefix === '' ? $element->localName : $element->prefix . ':' . $element->localName;
    }

    /**
     * @return array<string, array{name:string, family:string, parent:string|null, displayName:string|null}>
     */
    private static function parseStyles(string $xml): array
    {
        $dom = self::loadXml($xml, 'ODT styles.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !in_array($root->localName, ['document-styles', 'document'], true) || $root->namespaceURI !== self::OFFICE_NAMESPACE) {
            throw new \InvalidArgumentException('ODT styles.xml must use an office:document-styles or office:document root');
        }

        $styles = [];
        foreach ($dom->getElementsByTagNameNS(self::STYLE_NAMESPACE, 'style') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }

            $name = self::namespacedAttribute($style, self::STYLE_NAMESPACE, 'name');
            $family = self::namespacedAttribute($style, self::STYLE_NAMESPACE, 'family');
            if ($name === null || $name === '' || $family === null || $family === '') {
                continue;
            }

            $styles[$name] = [
                'name' => $name,
                'family' => $family,
                'parent' => self::namespacedAttribute($style, self::STYLE_NAMESPACE, 'parent-style-name'),
                'displayName' => self::namespacedAttribute($style, self::STYLE_NAMESPACE, 'display-name'),
            ];
        }

        return $styles;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseMetadata(string $xml): array
    {
        $dom = self::loadXml($xml, 'ODT meta.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !in_array($root->localName, ['document-meta', 'document'], true) || $root->namespaceURI !== self::OFFICE_NAMESPACE) {
            throw new \InvalidArgumentException('ODT meta.xml must use an office:document-meta or office:document root');
        }

        $meta = self::firstElementByPath($root, [[self::OFFICE_NAMESPACE, 'meta']]);
        if (!$meta instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $fields = [
            'generator' => [self::META_NAMESPACE, 'generator'],
            'title' => [self::DC_NAMESPACE, 'title'],
            'description' => [self::DC_NAMESPACE, 'description'],
            'subject' => [self::DC_NAMESPACE, 'subject'],
            'language' => [self::DC_NAMESPACE, 'language'],
            'initialCreator' => [self::META_NAMESPACE, 'initial-creator'],
            'creator' => [self::DC_NAMESPACE, 'creator'],
            'creationDate' => [self::META_NAMESPACE, 'creation-date'],
            'date' => [self::DC_NAMESPACE, 'date'],
            'editingDuration' => [self::META_NAMESPACE, 'editing-duration'],
            'editingCycles' => [self::META_NAMESPACE, 'editing-cycles'],
        ];

        foreach ($fields as $key => [$namespace, $localName]) {
            $value = self::firstChildElementText($meta, $namespace, $localName);
            if ($value !== null && $value !== '') {
                $metadata[$key] = $value;
            }
        }

        $keywords = [];
        foreach (self::childElements($meta, self::META_NAMESPACE, 'keyword') as $keywordElement) {
            $keywordText = trim($keywordElement->textContent);
            if ($keywordText === '') {
                continue;
            }

            foreach (explode(',', $keywordText) as $keyword) {
                $keyword = trim($keyword);
                if ($keyword !== '') {
                    $keywords[] = $keyword;
                }
            }
        }
        if ($keywords !== []) {
            $metadata['keywords'] = $keywords;
        }

        $template = self::metadataChildAttributes($meta, 'template', [
            'href' => [self::XLINK_NAMESPACE, 'href'],
            'title' => [self::XLINK_NAMESPACE, 'title'],
            'type' => [self::XLINK_NAMESPACE, 'type'],
            'actuate' => [self::XLINK_NAMESPACE, 'actuate'],
            'show' => [self::XLINK_NAMESPACE, 'show'],
            'date' => [self::META_NAMESPACE, 'date'],
            'name' => [self::META_NAMESPACE, 'name'],
        ]);
        if ($template !== []) {
            $metadata['template'] = $template;
        }

        $autoReload = self::metadataChildAttributes($meta, 'auto-reload', [
            'href' => [self::XLINK_NAMESPACE, 'href'],
            'type' => [self::XLINK_NAMESPACE, 'type'],
            'actuate' => [self::XLINK_NAMESPACE, 'actuate'],
            'show' => [self::XLINK_NAMESPACE, 'show'],
            'delay' => [self::META_NAMESPACE, 'delay'],
        ]);
        if ($autoReload !== []) {
            $metadata['autoReload'] = $autoReload;
        }

        $hyperlinkBehaviour = self::metadataChildAttributes($meta, 'hyperlink-behaviour', [
            'targetFrameName' => [self::OFFICE_NAMESPACE, 'target-frame-name'],
            'show' => [self::XLINK_NAMESPACE, 'show'],
        ]);
        if ($hyperlinkBehaviour !== []) {
            $metadata['hyperlinkBehaviour'] = $hyperlinkBehaviour;
        }

        $userDefined = [];
        foreach ($meta->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::META_NAMESPACE || $child->localName !== 'user-defined') {
                continue;
            }

            $name = self::namespacedAttribute($child, self::META_NAMESPACE, 'name');
            if ($name === null || $name === '') {
                continue;
            }

            $userDefined[$name] = [
                'value' => trim($child->textContent),
                'valueType' => self::namespacedAttribute($child, self::META_NAMESPACE, 'value-type') ?? 'string',
            ];
        }
        if ($userDefined !== []) {
            $metadata['userDefined'] = $userDefined;
        }

        $statistics = self::documentStatistics($meta);
        if ($statistics !== []) {
            $metadata['statistics'] = $statistics;
        }

        return $metadata;
    }

    /**
     * @param array<string, array{0:string, 1:string}> $attributes
     *
     * @return array<string, string>
     */
    private static function metadataChildAttributes(\DOMElement $meta, string $localName, array $attributes): array
    {
        $element = self::firstDirectChildElement($meta, self::META_NAMESPACE, $localName);
        if (!$element instanceof \DOMElement) {
            return [];
        }

        $values = [];
        foreach ($attributes as $key => [$namespace, $attributeName]) {
            $value = self::optionalString(self::namespacedAttribute($element, $namespace, $attributeName));
            if ($value !== null) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array{count:int,itemCount:int,mapEntryCount:int,sets:list<array<string, mixed>>,setsByName:array<string, array<string, mixed>>}
     */
    private static function emptySettings(): array
    {
        return [
            'count' => 0,
            'itemCount' => 0,
            'mapEntryCount' => 0,
            'sets' => [],
            'setsByName' => [],
        ];
    }

    /**
     * @return array{count:int,itemCount:int,mapEntryCount:int,sets:list<array<string, mixed>>,setsByName:array<string, array<string, mixed>>}
     */
    private static function parseSettings(string $xml): array
    {
        $dom = self::loadXml($xml, 'ODT settings.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !in_array($root->localName, ['document-settings', 'document'], true) || $root->namespaceURI !== self::OFFICE_NAMESPACE) {
            throw new \InvalidArgumentException('ODT settings.xml must use an office:document-settings or office:document root');
        }

        $settingsElement = self::firstElementByPath($root, [[self::OFFICE_NAMESPACE, 'settings']]);
        if (!$settingsElement instanceof \DOMElement) {
            throw new \RuntimeException('ODT settings.xml is missing office:settings');
        }

        $sets = [];
        $setsByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;
        foreach (self::childElements($settingsElement, self::CONFIG_NAMESPACE, 'config-item-set') as $setElement) {
            $name = self::trimmedAttribute($setElement, self::CONFIG_NAMESPACE, 'name');
            if ($name === '') {
                continue;
            }

            $set = self::settingsContainerDefinition($setElement) + ['name' => $name];
            $sets[] = $set;
            $setsByName[$name] = $set;
            $itemCount += (int) ($set['itemCount'] ?? 0);
            $mapEntryCount += (int) ($set['mapEntryCount'] ?? 0);
        }

        return [
            'count' => count($sets),
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'sets' => $sets,
            'setsByName' => $setsByName,
        ];
    }

    /**
     * @return array{itemCount:int,mapEntryCount:int,items:list<array<string, mixed>>,itemsByName:array<string, array<string, mixed>>,maps:list<array<string, mixed>>,mapsByName:array<string, array<string, mixed>>}
     */
    private static function settingsContainerDefinition(\DOMElement $container): array
    {
        $items = [];
        $itemsByName = [];
        $maps = [];
        $mapsByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;

        foreach (self::childElements($container) as $child) {
            if (self::isElement($child, self::CONFIG_NAMESPACE, 'config-item')) {
                $item = self::settingsConfigItemDefinition($child);
                if ($item === []) {
                    continue;
                }

                $items[] = $item;
                $itemsByName[$item['name']] = $item;
                ++$itemCount;
                continue;
            }

            if (self::isElement($child, self::CONFIG_NAMESPACE, 'config-item-map-indexed')
                || self::isElement($child, self::CONFIG_NAMESPACE, 'config-item-map-named')
            ) {
                $map = self::settingsConfigMapDefinition($child);
                if ($map === []) {
                    continue;
                }

                $maps[] = $map;
                $mapsByName[$map['name']] = $map;
                $itemCount += (int) ($map['itemCount'] ?? 0);
                $mapEntryCount += (int) ($map['mapEntryCount'] ?? 0);
            }
        }

        return [
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'items' => $items,
            'itemsByName' => $itemsByName,
            'maps' => $maps,
            'mapsByName' => $mapsByName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function settingsConfigItemDefinition(\DOMElement $item): array
    {
        $name = self::trimmedAttribute($item, self::CONFIG_NAMESPACE, 'name');
        if ($name === '') {
            return [];
        }

        $type = self::trimmedAttribute($item, self::CONFIG_NAMESPACE, 'type');
        $value = self::normalizedText($item);

        return self::withoutNulls([
            'name' => $name,
            'type' => $type === '' ? null : $type,
            'value' => $value,
            'typedValue' => self::settingsConfigTypedValue($value, $type),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function settingsConfigMapDefinition(\DOMElement $map): array
    {
        $name = self::trimmedAttribute($map, self::CONFIG_NAMESPACE, 'name');
        if ($name === '') {
            return [];
        }

        $entries = [];
        $entriesByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;
        foreach (self::childElements($map, self::CONFIG_NAMESPACE, 'config-item-map-entry') as $entryElement) {
            $entry = self::settingsContainerDefinition($entryElement);
            $entry['index'] = count($entries);
            $entryName = self::trimmedAttribute($entryElement, self::CONFIG_NAMESPACE, 'name');
            if ($entryName !== '') {
                $entry['name'] = $entryName;
                $entriesByName[$entryName] = $entry;
            }

            $entries[] = $entry;
            $itemCount += (int) ($entry['itemCount'] ?? 0);
            $mapEntryCount += 1 + (int) ($entry['mapEntryCount'] ?? 0);
        }

        $definition = [
            'name' => $name,
            'type' => $map->localName === 'config-item-map-named' ? 'named' : 'indexed',
            'entryCount' => count($entries),
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'entries' => $entries,
        ];
        if ($entriesByName !== []) {
            $definition['entriesByName'] = $entriesByName;
        }

        return $definition;
    }

    private static function settingsConfigTypedValue(string $value, string $type): mixed
    {
        $type = strtolower(trim($type));
        if (in_array($type, ['boolean', 'bool'], true)) {
            return self::nullableBool($value);
        }
        if (in_array($type, ['int', 'integer', 'long', 'short'], true)) {
            return preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value;
        }
        if (in_array($type, ['float', 'double'], true)) {
            return is_numeric($value) ? (float) $value : $value;
        }

        return $value;
    }

    /**
     * @return array<string, int>
     */
    private static function documentStatistics(\DOMElement $meta): array
    {
        $element = null;
        foreach ($meta->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::META_NAMESPACE && $child->localName === 'document-statistic') {
                $element = $child;
                break;
            }
        }

        if (!$element instanceof \DOMElement) {
            return [];
        }

        $statistics = [];
        foreach ([
            'page-count',
            'table-count',
            'image-count',
            'object-count',
            'paragraph-count',
            'word-count',
            'sentence-count',
            'character-count',
            'non-whitespace-character-count',
            'syllable-count',
        ] as $name) {
            $value = self::namespacedAttribute($element, self::META_NAMESPACE, $name);
            if ($value !== null && ctype_digit($value)) {
                $statistics[self::camelCase($name)] = (int) $value;
            }
        }

        return $statistics;
    }

    private static function camelCase(string $name): string
    {
        return preg_replace_callback(
            '/-([a-z])/',
            static fn (array $matches): string => strtoupper($matches[1]),
            $name
        ) ?? $name;
    }

    /**
     * @return list<AstNode>
     */
    private static function inlineNodes(\DOMNode $node): array
    {
        $nodes = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if ($child->nodeValue !== '') {
                    $nodes[] = new AstNode('text', ['text' => $child->nodeValue]);
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 's') {
                $nodes[] = new AstNode('text', ['text' => str_repeat(' ', self::intAttribute($child, self::TEXT_NAMESPACE, 'c', 1))]);
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'tab') {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'line-break') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'a') {
                $nodes[] = new AstNode('link', [
                    'url' => self::namespacedAttribute($child, self::XLINK_NAMESPACE, 'href') ?? '',
                    'title' => '',
                ], self::inlineNodes($child));
                continue;
            }

            if ($child->namespaceURI === self::DRAW_NAMESPACE && $child->localName === 'frame') {
                $image = self::firstDescendantElement($child, self::DRAW_NAMESPACE, 'image');
                if ($image instanceof \DOMElement) {
                    $href = self::namespacedAttribute($image, self::XLINK_NAMESPACE, 'href') ?? '';
                    if ($href !== '') {
                        $nodes[] = new AstNode('image', [
                            'url' => $href,
                            'alt' => trim($child->textContent),
                            'title' => '',
                            'mediaType' => null,
                        ]);
                    }
                }
                continue;
            }

            array_push($nodes, ...self::inlineNodes($child));
        }

        return self::mergeAdjacentTextNodes($nodes);
    }

    private static function plainText(\DOMNode $node): string
    {
        $text = '';
        foreach (self::inlineNodes($node) as $inline) {
            if ($inline->type === 'text') {
                $text .= (string) $inline->attr('text', '');
            } elseif ($inline->type === 'linebreak') {
                $text .= "\n";
            } else {
                foreach ($inline->children as $child) {
                    if ($child->type === 'text') {
                        $text .= (string) $child->attr('text', '');
                    }
                }
            }
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     *
     * @return list<AstNode>
     */
    private static function mergeAdjacentTextNodes(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            $lastKey = array_key_last($merged);
            $previous = $lastKey === null ? null : $merged[$lastKey];
            if ($node->type === 'text' && $previous instanceof AstNode && $previous->type === 'text') {
                array_pop($merged);
                $merged[] = new AstNode('text', [
                    'text' => (string) $previous->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }

    /**
     * @param list<array{0:string, 1:string}> $path
     */
    private static function firstElementByPath(\DOMElement $root, array $path): ?\DOMElement
    {
        $current = $root;
        foreach ($path as [$namespace, $localName]) {
            $next = null;
            foreach ($current->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                    $next = $child;
                    break;
                }
            }
            if (!$next instanceof \DOMElement) {
                return null;
            }
            $current = $next;
        }

        return $current;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function childElements(\DOMElement $element, ?string $namespace = null, ?string $localName = null): array
    {
        $elements = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($namespace !== null && $child->namespaceURI !== $namespace) {
                continue;
            }
            if ($localName !== null && $child->localName !== $localName) {
                continue;
            }
            $elements[] = $child;
        }

        return $elements;
    }

    private static function isElement(\DOMElement $element, string $namespace, string $localName): bool
    {
        return $element->namespaceURI === $namespace && $element->localName === $localName;
    }

    private static function firstDescendantElement(\DOMElement $root, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($root->getElementsByTagNameNS($namespace, $localName) as $element) {
            if ($element instanceof \DOMElement) {
                return $element;
            }
        }

        return null;
    }

    private static function firstChildElementText(\DOMElement $root, string $namespace, string $localName): ?string
    {
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return trim($child->textContent);
            }
        }

        return null;
    }

    private static function firstDirectChildElement(\DOMElement $root, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private static function namespacedAttribute(\DOMElement $element, string $namespace, string $localName): ?string
    {
        return $element->hasAttributeNS($namespace, $localName) ? $element->getAttributeNS($namespace, $localName) : null;
    }

    private static function attributeValue(\DOMElement $element, string $namespace, string $localName): string
    {
        return trim($element->getAttributeNS($namespace, $localName));
    }

    private static function trimmedAttribute(\DOMElement $element, string $namespace, string $localName): string
    {
        return trim(self::namespacedAttribute($element, $namespace, $localName) ?? '');
    }

    private static function optionalString(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    private static function optionalInt(?string $value): ?int
    {
        $value = $value === null ? '' : trim($value);

        return ctype_digit($value) ? (int) $value : null;
    }

    private static function nullableBool(string $value): ?bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['true', '1', 'yes', 'checked'], true);
    }

    private static function normalizedText(\DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent;

        return trim($text);
    }

    private static function manifestSize(?string $value, string $path): ?int
    {
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException('ODF manifest:size for ' . $path . ' must be a non-negative integer');
        }

        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $max = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($max) || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            throw new \InvalidArgumentException('ODF manifest:size for ' . $path . ' exceeds platform integer bounds');
        }

        return (int) $normalized;
    }

    /**
     * @return array{mediaTypeBase:string, mediaTypeHasParameters:bool, mediaTypeParameterCount:int, mediaTypeParameters:list<array{name:string, value:string, raw:string}>, mediaTypeParameterMap:array<string, string>}
     */
    private static function mediaTypeReport(string $mediaType): array
    {
        $segments = self::mediaTypeSegments($mediaType);
        $base = strtolower(trim((string) array_shift($segments)));
        $parameters = [];
        $parameterMap = [];

        foreach ($segments as $segment) {
            $raw = trim($segment);
            if ($raw === '') {
                continue;
            }

            $equals = strpos($raw, '=');
            $name = $equals === false ? strtolower(trim($raw)) : strtolower(trim(substr($raw, 0, $equals)));
            if ($name === '') {
                continue;
            }

            $value = $equals === false ? '' : trim(substr($raw, $equals + 1));
            if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
                $value = preg_replace('/\\\\([\x20-\x7E])/', '$1', $value) ?? $value;
            }

            $parameters[] = [
                'name' => $name,
                'value' => $value,
                'raw' => $raw,
            ];
            $parameterMap[$name] = $value;
        }

        return [
            'mediaTypeBase' => $base,
            'mediaTypeHasParameters' => $parameters !== [],
            'mediaTypeParameterCount' => count($parameters),
            'mediaTypeParameters' => $parameters,
            'mediaTypeParameterMap' => $parameterMap,
        ];
    }

    /**
     * @return list<string>
     */
    private static function mediaTypeSegments(string $mediaType): array
    {
        $segments = [];
        $current = '';
        $inQuote = false;
        $escaped = false;
        $length = strlen($mediaType);

        for ($index = 0; $index < $length; $index++) {
            $char = $mediaType[$index];
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $current .= $char;
                continue;
            }

            if ($char === ';' && !$inQuote) {
                $segments[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $segments[] = $current;

        return $segments;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function withoutNulls(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function withoutEmptyValues(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
    }

    private static function intAttribute(\DOMElement $element, string $namespace, string $localName, int $default): int
    {
        $value = self::namespacedAttribute($element, $namespace, $localName);
        if ($value === null || $value === '' || !preg_match('/^\d+$/', $value)) {
            return $default;
        }

        return max(1, (int) $value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function pathShape(?string $path): array
    {
        if ($path === null || $path === '') {
            return [
                'kind' => 'missing',
                'segments' => [],
                'pathSegmentPositionReviews' => [],
                'segmentCount' => 0,
                'directorySegmentCount' => 0,
            ];
        }

        if ($path === '/') {
            return [
                'kind' => 'root',
                'segments' => [],
                'pathSegmentPositionReviews' => [],
                'segmentCount' => 0,
                'directorySegmentCount' => 0,
            ];
        }

        $isDirectory = str_ends_with($path, '/');
        $trimmed = trim($path, '/');
        $segments = $trimmed === '' ? [] : explode('/', $trimmed);
        $basename = $segments === [] ? null : $segments[count($segments) - 1];
        $directorySegments = $isDirectory ? $segments : array_slice($segments, 0, -1);
        $directory = $directorySegments === [] ? null : implode('/', $directorySegments) . '/';
        $directoryBaseName = $directorySegments === [] ? null : $directorySegments[count($directorySegments) - 1];
        $directoryBaseNameStem = is_string($directoryBaseName) && $directoryBaseName !== ''
            ? self::packagePartBasenameStem($directoryBaseName)
            : null;
        $extension = null;

        if (!$isDirectory && is_string($basename) && $basename !== '') {
            $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if ($extension === '') {
                $extension = null;
            }
        }

        return self::withoutNulls([
            'kind' => $isDirectory ? 'directory' : 'file',
            'topLevelSegment' => $segments[0] ?? null,
            'directory' => $directory,
            'directoryBaseName' => $directoryBaseName,
            'directoryBaseNameStem' => $directoryBaseNameStem,
            'caseFoldDirectoryBaseNameStem' => is_string($directoryBaseNameStem) ? strtolower($directoryBaseNameStem) : null,
            'basename' => $basename,
            'extension' => $extension,
            'segments' => $segments,
            'pathSegmentPositionReviews' => self::pathSegmentPositionReviews($segments),
            'segmentCount' => count($segments),
            'directorySegmentCount' => count($directorySegments),
        ]);
    }

    /**
     * @param list<string> $segments
     * @return list<array{pathSegmentIndex:int, segment:string, position:string, isFirst:bool, isLast:bool, isOnly:bool}>
     */
    private static function pathSegmentPositionReviews(array $segments): array
    {
        $reviews = [];
        $segmentCount = count($segments);
        foreach ($segments as $segmentIndex => $segment) {
            if (!is_string($segment) || $segment === '') {
                continue;
            }

            $isFirst = $segmentIndex === 0;
            $isLast = $segmentIndex === $segmentCount - 1;
            $isOnly = $segmentCount === 1;
            $position = match (true) {
                $isOnly => 'only',
                $isFirst => 'first',
                $isLast => 'last',
                default => 'middle',
            };

            $reviews[] = [
                'pathSegmentIndex' => $segmentIndex,
                'segment' => $segment,
                'position' => $position,
                'isFirst' => $isFirst,
                'isLast' => $isLast,
                'isOnly' => $isOnly,
            ];
        }

        return $reviews;
    }

    private static function normalizeManifestPath(string $path): string
    {
        if ($path === '') {
            throw new \InvalidArgumentException('ODF manifest full-path must not be empty');
        }

        if ($path === '/') {
            return '/';
        }

        if (str_starts_with($path, '/') || str_contains($path, '\\') || self::hasAsciiControlByte($path)) {
            throw new \InvalidArgumentException('Unsafe ODF manifest full-path: ' . $path);
        }

        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            $isTrailingDirectorySegment = $index === count($segments) - 1 && $segment === '';
            if ($isTrailingDirectorySegment) {
                continue;
            }

            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Unsafe ODF manifest full-path: ' . $path);
            }
        }

        return $path;
    }

    private static function manifestPackagePath(string $path): ?string
    {
        if ($path === '/') {
            return null;
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $path) === 1) {
            throw new \InvalidArgumentException('Malformed percent escape in ODF manifest full-path: ' . $path);
        }

        $decodedPath = rawurldecode($path);
        if ($decodedPath === '' || str_starts_with($decodedPath, '/') || str_contains($decodedPath, '\\') || self::hasAsciiControlByte($decodedPath)) {
            throw new \InvalidArgumentException('Unsafe ODF manifest full-path: ' . $path);
        }

        $segments = explode('/', $decodedPath);
        foreach ($segments as $index => $segment) {
            $isTrailingDirectorySegment = $index === count($segments) - 1 && $segment === '';
            if ($isTrailingDirectorySegment) {
                continue;
            }

            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Unsafe ODF manifest full-path: ' . $path);
            }
        }

        return $decodedPath;
    }

    private static function hasAsciiControlByte(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    /**
     * @return array{packagePath:string|null,pathReference:string|null,pathSuffix:string|null,pathQuery:string|null,pathFragment:string|null}
     */
    private static function manifestPackageReference(string $path): array
    {
        if ($path === '/') {
            return [
                'packagePath' => null,
                'pathReference' => null,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
            ];
        }

        $pathReference = $path;
        $pathSuffix = null;
        $pathQuery = null;
        $pathFragment = null;
        $suffixOffset = strcspn($path, '?#');
        if ($suffixOffset < strlen($path)) {
            $pathReference = substr($path, 0, $suffixOffset);
            $pathSuffix = substr($path, $suffixOffset);
            if (str_starts_with($pathSuffix, '?')) {
                $queryAndFragment = substr($pathSuffix, 1);
                $fragmentOffset = strpos($queryAndFragment, '#');
                if ($fragmentOffset === false) {
                    $pathQuery = $queryAndFragment;
                } else {
                    $pathQuery = substr($queryAndFragment, 0, $fragmentOffset);
                    $pathFragment = substr($queryAndFragment, $fragmentOffset + 1);
                }
            } elseif (str_starts_with($pathSuffix, '#')) {
                $pathFragment = substr($pathSuffix, 1);
            }
        }

        return [
            'packagePath' => self::manifestPackagePath($pathReference),
            'pathReference' => $pathReference,
            'pathSuffix' => $pathSuffix,
            'pathQuery' => $pathQuery,
            'pathFragment' => $pathFragment,
        ];
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \InvalidArgumentException("Unable to parse {$label}");
        }

        return $dom;
    }
}
