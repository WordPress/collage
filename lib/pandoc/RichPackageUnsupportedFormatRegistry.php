<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class RichPackageUnsupportedFormatRegistry
{
    public const UPSTREAM_COMMIT = '0640c4c9859aa5a3ede082c190fcd5883c24ac83';

    /**
     * @var array<string, array{
     *     extensions:list<string>,
     *     directions:array<string, array{
     *         upstream:bool,
     *         state:string,
     *         code:string,
     *         countsAsDirectSupport:bool,
     *         component:?string,
     *         gates:list<string>,
     *         diagnostics:list<string>
     *     }>
     * }>
     */
    private const FORMAT_ROWS = [
        'docx' => [
            'extensions' => ['docx'],
            'directions' => [
                'input' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-input',
                    'code' => 'pandoc.rich-package.input.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'DocxReader',
                    'gates' => ['shared-zip-package-core', 'opc-xml-relationships-core', 'docx-openxml-core'],
                    'diagnostics' => [],
                ],
                'output' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-output',
                    'code' => 'pandoc.rich-package.output.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'DocxWriter',
                    'gates' => ['shared-zip-package-core', 'opc-xml-relationships-core', 'docx-openxml-writer-core'],
                    'diagnostics' => [],
                ],
            ],
        ],
        'odt' => [
            'extensions' => ['odt'],
            'directions' => [
                'input' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-input',
                    'code' => 'pandoc.rich-package.input.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'OdtReader',
                    'gates' => ['shared-zip-package-core', 'odf-open-document-core'],
                    'diagnostics' => [],
                ],
                'output' => [
                    'upstream' => true,
                    'state' => 'unsupported-rich-package-output',
                    'code' => 'pandoc.rich-package.output.unsupported-format',
                    'countsAsDirectSupport' => false,
                    'component' => null,
                    'gates' => ['shared-zip-package-core', 'odf-open-document-writer-core'],
                    'diagnostics' => ['writer-component-missing', 'package-assembly-not-implemented'],
                ],
            ],
        ],
        'opendocument' => [
            'extensions' => ['fodt'],
            'directions' => [
                'output' => [
                    'upstream' => true,
                    'state' => 'unsupported-rich-package-output',
                    'code' => 'pandoc.rich-package.output.unsupported-format',
                    'countsAsDirectSupport' => false,
                    'component' => null,
                    'gates' => ['odf-open-document-writer-core'],
                    'diagnostics' => ['writer-component-missing'],
                ],
            ],
        ],
        'epub' => [
            'extensions' => ['epub'],
            'directions' => [
                'input' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-input',
                    'code' => 'pandoc.rich-package.input.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'EpubReader',
                    'gates' => ['shared-zip-package-core', 'epub3-package-core', 'xml-html5-dom-core'],
                    'diagnostics' => [],
                ],
                'output' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-output',
                    'code' => 'pandoc.rich-package.output.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'EpubWriter',
                    'gates' => ['shared-zip-package-core', 'epub3-package-writer-core', 'xml-html5-dom-core'],
                    'diagnostics' => [],
                ],
            ],
        ],
        'epub2' => [
            'extensions' => ['epub'],
            'directions' => [
                'output' => [
                    'upstream' => true,
                    'state' => 'unsupported-rich-package-output',
                    'code' => 'pandoc.rich-package.output.unsupported-format',
                    'countsAsDirectSupport' => false,
                    'component' => null,
                    'gates' => ['shared-zip-package-core', 'epub2-package-writer-core', 'xml-html5-dom-core'],
                    'diagnostics' => ['writer-component-missing', 'package-assembly-not-implemented'],
                ],
            ],
        ],
        'epub3' => [
            'extensions' => ['epub'],
            'directions' => [
                'output' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-output',
                    'code' => 'pandoc.rich-package.output.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'EpubWriter',
                    'gates' => ['shared-zip-package-core', 'epub3-package-writer-core', 'xml-html5-dom-core'],
                    'diagnostics' => [],
                ],
            ],
        ],
        'ipynb' => [
            'extensions' => ['ipynb'],
            'directions' => [
                'input' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-input',
                    'code' => 'pandoc.rich-package.input.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'IpynbReader',
                    'gates' => ['ipynb-reader-core'],
                    'diagnostics' => [],
                ],
                'output' => [
                    'upstream' => true,
                    'state' => 'unsupported-rich-package-output',
                    'code' => 'pandoc.rich-package.output.unsupported-format',
                    'countsAsDirectSupport' => false,
                    'component' => null,
                    'gates' => ['ipynb-notebook-writer-core'],
                    'diagnostics' => ['writer-component-missing', 'notebook-writer-not-implemented', 'external-notebook-tooling-disallowed'],
                ],
            ],
        ],
        'pptx' => [
            'extensions' => ['pptx'],
            'directions' => [
                'input' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-input',
                    'code' => 'pandoc.rich-package.input.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'PptxReader',
                    'gates' => ['shared-zip-package-core', 'opc-xml-relationships-core', 'pptx-openxml-core'],
                    'diagnostics' => [],
                ],
                'output' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-output',
                    'code' => 'pandoc.rich-package.output.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'PptxWriter',
                    'gates' => ['shared-zip-package-core', 'opc-xml-relationships-core', 'pptx-openxml-writer-core'],
                    'diagnostics' => [],
                ],
            ],
        ],
        'xlsx' => [
            'extensions' => ['xlsx'],
            'directions' => [
                'input' => [
                    'upstream' => true,
                    'state' => 'bounded-native-rich-package-input',
                    'code' => 'pandoc.rich-package.input.bounded-native',
                    'countsAsDirectSupport' => true,
                    'component' => 'XlsxReader',
                    'gates' => ['shared-zip-package-core', 'opc-xml-relationships-core', 'xlsx-openxml-core'],
                    'diagnostics' => [],
                ],
            ],
        ],
        'chunkedhtml' => [
            'extensions' => [],
            'directions' => [
                'output' => [
                    'upstream' => true,
                    'state' => 'unsupported-rich-package-output',
                    'code' => 'pandoc.rich-package.output.unsupported-format',
                    'countsAsDirectSupport' => false,
                    'component' => null,
                    'gates' => ['shared-zip-package-core', 'xml-html5-dom-core', 'chunked-html-package-writer-core'],
                    'diagnostics' => ['writer-component-missing', 'package-assembly-not-implemented'],
                ],
            ],
        ],
        'icml' => [
            'extensions' => ['icml'],
            'directions' => [
                'output' => [
                    'upstream' => true,
                    'state' => 'unsupported-rich-package-output',
                    'code' => 'pandoc.rich-package.output.unsupported-format',
                    'countsAsDirectSupport' => false,
                    'component' => null,
                    'gates' => ['doctemplate-core', 'icml-writer-core'],
                    'diagnostics' => ['writer-component-missing', 'package-assembly-not-implemented'],
                ],
            ],
        ],
        'pdf' => [
            'extensions' => ['pdf'],
            'directions' => [
                'output' => [
                    'upstream' => true,
                    'state' => 'unsupported-rich-package-output',
                    'code' => 'pandoc.rich-package.output.unsupported-format',
                    'countsAsDirectSupport' => false,
                    'component' => null,
                    'gates' => ['pdf-engine-handoff-core'],
                    'diagnostics' => ['writer-component-missing', 'renderer-engine-disallowed', 'rendered-artifact-not-produced'],
                ],
            ],
        ],
    ];

    /**
     * @var array<string, array{format:string, component:?string, gates:list<string>, diagnostics:list<string>}>
     */
    private const SOURCE_ALIASES = [
        'doc' => [
            'format' => 'doc',
            'component' => 'LegacyDocReader',
            'gates' => ['legacy-doc-cfb-core'],
            'diagnostics' => ['source-alias-only', 'not-upstream-direct-reader-token', 'legacy-cfb-handoff-only'],
        ],
        'docx' => [
            'format' => 'docx',
            'component' => 'DocxReader',
            'gates' => ['shared-zip-package-core', 'opc-xml-relationships-core', 'docx-openxml-core'],
            'diagnostics' => [],
        ],
        'odt' => [
            'format' => 'odt',
            'component' => 'OdtReader',
            'gates' => ['shared-zip-package-core', 'odf-open-document-core'],
            'diagnostics' => [],
        ],
        'ods' => [
            'format' => 'ods',
            'component' => null,
            'gates' => ['shared-zip-package-core', 'odf-spreadsheet-reader-core'],
            'diagnostics' => ['source-alias-only', 'not-upstream-direct-reader-token', 'reader-component-missing'],
        ],
        'odp' => [
            'format' => 'odp',
            'component' => null,
            'gates' => ['shared-zip-package-core', 'odf-presentation-reader-core'],
            'diagnostics' => ['source-alias-only', 'not-upstream-direct-reader-token', 'reader-component-missing'],
        ],
        'epub' => [
            'format' => 'epub',
            'component' => 'EpubReader',
            'gates' => ['shared-zip-package-core', 'epub3-package-core', 'xml-html5-dom-core'],
            'diagnostics' => [],
        ],
        'pptx' => [
            'format' => 'pptx',
            'component' => 'PptxReader',
            'gates' => ['shared-zip-package-core', 'opc-xml-relationships-core', 'pptx-openxml-core'],
            'diagnostics' => [],
        ],
        'xlsx' => [
            'format' => 'xlsx',
            'component' => 'XlsxReader',
            'gates' => ['shared-zip-package-core', 'opc-xml-relationships-core', 'xlsx-openxml-core'],
            'diagnostics' => [],
        ],
        'zip' => [
            'format' => 'zip',
            'component' => 'ZipPackage',
            'gates' => ['shared-zip-package-core'],
            'diagnostics' => ['source-alias-only', 'container-preflight-only', 'not-pandoc-document-format'],
        ],
    ];

    /**
     * @var array<string, array{format:string, formats:list<string>, kind:string}>
     */
    private const EXTENSION_ROWS = [
        '.docx' => [
            'format' => 'docx',
            'formats' => ['docx'],
            'kind' => 'office-open-xml-wordprocessing-package',
        ],
        '.epub' => [
            'format' => 'epub',
            'formats' => ['epub', 'epub2', 'epub3'],
            'kind' => 'epub-publication-package',
        ],
        '.fodt' => [
            'format' => 'opendocument',
            'formats' => ['opendocument'],
            'kind' => 'flat-open-document-text',
        ],
        '.icml' => [
            'format' => 'icml',
            'formats' => ['icml'],
            'kind' => 'indesign-markup-file',
        ],
        '.ipynb' => [
            'format' => 'ipynb',
            'formats' => ['ipynb'],
            'kind' => 'notebook-json-package',
        ],
        '.odt' => [
            'format' => 'odt',
            'formats' => ['odt'],
            'kind' => 'open-document-text-package',
        ],
        '.pdf' => [
            'format' => 'pdf',
            'formats' => ['pdf'],
            'kind' => 'pdf-rendered-artifact',
        ],
        '.pptx' => [
            'format' => 'pptx',
            'formats' => ['pptx'],
            'kind' => 'office-open-xml-presentation-package',
        ],
        '.xlsx' => [
            'format' => 'xlsx',
            'formats' => ['xlsx'],
            'kind' => 'office-open-xml-spreadsheet-package',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function richPackageFormats(): array
    {
        return array_keys(self::FORMAT_ROWS);
    }

    /**
     * @return list<string>
     */
    public static function richPackageExtensions(): array
    {
        return array_keys(self::EXTENSION_ROWS);
    }

    /**
     * @return array<string, mixed>
     */
    public static function statusReport(): array
    {
        $inputStatuses = self::statusesForDirection('input');
        $outputStatuses = self::statusesForDirection('output');

        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'source' => [
                'inputFormats' => 'Text.Pandoc.Readers.readers package-style formats',
                'outputFormats' => 'Text.Pandoc.Writers.writers package-style formats',
                'sourceAliases' => 'Text.Pandoc.Format.formatFromFilePath rich package extensions',
            ],
            'denominators' => [
                'richPackageFormats' => count(self::FORMAT_ROWS),
                'upstreamRichPackageInputs' => count($inputStatuses),
                'upstreamRichPackageOutputs' => count($outputStatuses),
                'sourceAliasExtensions' => count(self::SOURCE_ALIASES),
                'richPackageExtensions' => count(self::EXTENSION_ROWS),
            ],
            'directSupport' => [
                'input' => self::supportCounts($inputStatuses),
                'output' => self::supportCounts($outputStatuses),
            ],
            'unsupportedDiagnostics' => [
                'input' => self::unsupportedDiagnostics('input'),
                'output' => self::unsupportedDiagnostics('output'),
            ],
            'unsupportedSummary' => self::unsupportedFormatSummary(),
            'sourceAliasDiagnostics' => self::sourceAliasDiagnostics(),
            'extensionDiagnostics' => self::extensionDiagnostics(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function unsupportedFormatSummary(): array
    {
        $directionBuckets = [
            'input-output' => [],
            'input-only' => [],
            'output-only' => [],
        ];
        $supportBuckets = [
            'boundedNativeInputOutput' => [],
            'boundedNativeInputOnly' => [],
            'boundedNativeOutputOnly' => [],
            'nativeInputUnsupportedOutput' => [],
            'unsupportedInputNativeOutput' => [],
            'unsupportedInputOnly' => [],
            'unsupportedOutputOnly' => [],
            'unsupportedInputOutput' => [],
        ];
        $unsupportedFormats = [
            'input' => [],
            'output' => [],
            'any' => [],
        ];
        $unsupportedDiagnosticCounts = [];
        $unsupportedGateCounts = [];

        foreach (array_keys(self::FORMAT_ROWS) as $format) {
            $input = self::directionRow($format, 'input');
            $output = self::directionRow($format, 'output');
            $hasInput = $input !== null && $input['upstream'] === true;
            $hasOutput = $output !== null && $output['upstream'] === true;
            $inputSupported = $hasInput && $input['countsAsDirectSupport'] === true;
            $outputSupported = $hasOutput && $output['countsAsDirectSupport'] === true;
            $inputUnsupported = $hasInput && !$inputSupported;
            $outputUnsupported = $hasOutput && !$outputSupported;

            $directionBuckets[self::formatDirection($hasInput, $hasOutput)][] = $format;

            if ($inputUnsupported) {
                $unsupportedFormats['input'][] = $format;
            }
            if ($outputUnsupported) {
                $unsupportedFormats['output'][] = $format;
            }
            if ($inputUnsupported || $outputUnsupported) {
                $unsupportedFormats['any'][] = $format;
            }

            if ($hasInput && $hasOutput) {
                if ($inputSupported && $outputSupported) {
                    $supportBuckets['boundedNativeInputOutput'][] = $format;
                } elseif ($inputSupported && $outputUnsupported) {
                    $supportBuckets['nativeInputUnsupportedOutput'][] = $format;
                } elseif ($inputUnsupported && $outputSupported) {
                    $supportBuckets['unsupportedInputNativeOutput'][] = $format;
                } else {
                    $supportBuckets['unsupportedInputOutput'][] = $format;
                }
            } elseif ($hasInput) {
                $supportBuckets[$inputSupported ? 'boundedNativeInputOnly' : 'unsupportedInputOnly'][] = $format;
            } elseif ($hasOutput) {
                $supportBuckets[$outputSupported ? 'boundedNativeOutputOnly' : 'unsupportedOutputOnly'][] = $format;
            }
        }

        foreach (self::unsupportedDiagnostics() as $diagnostic) {
            foreach ($diagnostic['diagnostics'] as $code) {
                $unsupportedDiagnosticCounts[$code] = ($unsupportedDiagnosticCounts[$code] ?? 0) + 1;
            }
            foreach ($diagnostic['gates'] as $gate) {
                $unsupportedGateCounts[$gate] = ($unsupportedGateCounts[$gate] ?? 0) + 1;
            }
        }
        ksort($unsupportedDiagnosticCounts);
        ksort($unsupportedGateCounts);

        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'externalToolFree' => true,
            'directionBuckets' => $directionBuckets,
            'supportBuckets' => $supportBuckets,
            'unsupportedFormats' => $unsupportedFormats,
            'noNativeReaderFormats' => $unsupportedFormats['input'],
            'noNativeWriterFormats' => $unsupportedFormats['output'],
            'unsupportedDiagnosticCounts' => $unsupportedDiagnosticCounts,
            'unsupportedGateCounts' => $unsupportedGateCounts,
            'unsupportedSourceAliasExtensions' => array_column(self::sourceAliasDiagnostics(), 'extension'),
            'unsupportedExtensionNames' => array_column(self::extensionDiagnostics(), 'extension'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reviewPacket(): array
    {
        return [
            'registry' => 'rich-package-unsupported-format',
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'externalToolFree' => true,
            'summary' => self::unsupportedFormatSummary(),
            'unsupportedDiagnostics' => [
                'input' => self::unsupportedDiagnostics('input'),
                'output' => self::unsupportedDiagnostics('output'),
            ],
            'sourceAliasDiagnostics' => self::sourceAliasDiagnostics(),
            'extensionDiagnostics' => self::extensionDiagnostics(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatStatus(string $format, string $direction): array
    {
        $format = self::normalizeFormat($format);
        $direction = self::normalizeDirection($direction);

        if (!isset(self::FORMAT_ROWS[$format])) {
            throw new \InvalidArgumentException("Unknown rich package format: {$format}");
        }

        $row = self::FORMAT_ROWS[$format];
        $directionStatus = $row['directions'][$direction] ?? null;

        if ($directionStatus === null) {
            return [
                'format' => $format,
                'direction' => $direction,
                'state' => 'not-upstream-rich-package-format',
                'code' => 'pandoc.rich-package.' . $direction . '.not-upstream-format',
                'countsAsDirectSupport' => false,
                'upstream' => false,
                'component' => null,
                'gates' => [],
                'diagnostics' => ['not-advertised-by-upstream-pandoc'],
                'sourceExtensions' => $row['extensions'],
            ];
        }

        return [
            'format' => $format,
            'direction' => $direction,
            'state' => $directionStatus['state'],
            'code' => $directionStatus['code'],
            'countsAsDirectSupport' => $directionStatus['countsAsDirectSupport'],
            'upstream' => $directionStatus['upstream'],
            'component' => $directionStatus['component'],
            'gates' => $directionStatus['gates'],
            'diagnostics' => $directionStatus['diagnostics'],
            'sourceExtensions' => $row['extensions'],
        ];
    }

    public static function sourceFormatForExtension(string $extension): ?string
    {
        $extension = strtolower(ltrim($extension, '.'));
        return self::SOURCE_ALIASES[$extension]['format'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function sourceAliasStatus(string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));
        $alias = self::SOURCE_ALIASES[$extension] ?? null;

        if ($alias === null) {
            throw new \InvalidArgumentException("Unknown rich package source extension: {$extension}");
        }

        $format = $alias['format'];
        $directInput = isset(self::FORMAT_ROWS[$format])
            ? self::formatStatus($format, 'input')
            : null;
        $countsAsDirectSupport = $directInput !== null && $directInput['countsAsDirectSupport'] === true;

        return [
            'extension' => $extension,
            'format' => $format,
            'state' => $countsAsDirectSupport ? 'direct-rich-package-input' : 'unsupported-rich-package-source-alias',
            'code' => $countsAsDirectSupport
                ? 'pandoc.rich-package.source-alias.direct-input'
                : 'pandoc.rich-package.source-alias.unsupported-format',
            'countsAsDirectSupport' => $countsAsDirectSupport,
            'component' => $alias['component'],
            'gates' => $alias['gates'],
            'diagnostics' => $alias['diagnostics'],
            'directInputState' => $directInput['state'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function extensionStatus(string $extension): array
    {
        $extension = self::normalizeExtension($extension);
        $row = self::EXTENSION_ROWS[$extension] ?? null;

        if ($row === null) {
            throw new \InvalidArgumentException("Unknown rich package extension: {$extension}");
        }

        $inputFormats = [];
        $outputFormats = [];
        $directInputFormats = [];
        $directOutputFormats = [];
        $unsupportedInputFormats = [];
        $unsupportedOutputFormats = [];
        $diagnostics = [];
        $gates = [];

        foreach ($row['formats'] as $format) {
            foreach (['input', 'output'] as $direction) {
                $status = self::FORMAT_ROWS[$format]['directions'][$direction] ?? null;
                if ($status === null || $status['upstream'] !== true) {
                    continue;
                }

                if ($direction === 'input') {
                    $inputFormats[] = $format;
                    if ($status['countsAsDirectSupport'] === true) {
                        $directInputFormats[] = $format;
                    } else {
                        $unsupportedInputFormats[] = $format;
                    }
                } else {
                    $outputFormats[] = $format;
                    if ($status['countsAsDirectSupport'] === true) {
                        $directOutputFormats[] = $format;
                    } else {
                        $unsupportedOutputFormats[] = $format;
                    }
                }

                if ($status['countsAsDirectSupport'] === false) {
                    foreach ($status['diagnostics'] as $diagnostic) {
                        $diagnostics[] = $diagnostic;
                    }
                    foreach ($status['gates'] as $gate) {
                        $gates[] = $gate;
                    }
                }
            }
        }

        $unsupportedDirections = [];
        if ($unsupportedInputFormats !== []) {
            $unsupportedDirections[] = 'input';
        }
        if ($unsupportedOutputFormats !== []) {
            $unsupportedDirections[] = 'output';
        }

        return [
            'extension' => $extension,
            'format' => $row['format'],
            'kind' => $row['kind'],
            'formats' => $row['formats'],
            'inputFormats' => $inputFormats,
            'outputFormats' => $outputFormats,
            'directInputFormats' => $directInputFormats,
            'directOutputFormats' => $directOutputFormats,
            'unsupportedInputFormats' => $unsupportedInputFormats,
            'unsupportedOutputFormats' => $unsupportedOutputFormats,
            'unsupportedDirections' => $unsupportedDirections,
            'diagnostics' => array_values(array_unique($diagnostics)),
            'gates' => array_values(array_unique($gates)),
            'externalToolFree' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function unsupportedDiagnostics(?string $direction = null): array
    {
        $directions = $direction === null
            ? ['input', 'output']
            : [self::normalizeDirection($direction)];
        $diagnostics = [];

        foreach ($directions as $currentDirection) {
            foreach (self::statusesForDirection($currentDirection) as $status) {
                if ($status['countsAsDirectSupport'] === true) {
                    continue;
                }

                $diagnostics[] = [
                    'format' => $status['format'],
                    'direction' => $currentDirection,
                    'state' => $status['state'],
                    'code' => $status['code'],
                    'gates' => $status['gates'],
                    'diagnostics' => $status['diagnostics'],
                    'sourceExtensions' => $status['sourceExtensions'],
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function sourceAliasDiagnostics(): array
    {
        $diagnostics = [];

        foreach (array_keys(self::SOURCE_ALIASES) as $extension) {
            $status = self::sourceAliasStatus($extension);
            if ($status['countsAsDirectSupport'] === false) {
                $diagnostics[] = $status;
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function extensionDiagnostics(): array
    {
        $diagnostics = [];

        foreach (array_keys(self::EXTENSION_ROWS) as $extension) {
            $status = self::extensionStatus($extension);
            if ($status['unsupportedDirections'] !== []) {
                $diagnostics[] = $status;
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function statusesForDirection(string $direction): array
    {
        $direction = self::normalizeDirection($direction);
        $statuses = [];

        foreach (array_keys(self::FORMAT_ROWS) as $format) {
            $status = self::formatStatus($format, $direction);
            if ($status['upstream'] === true) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    /**
     * @return array{
     *     upstream:bool,
     *     state:string,
     *     code:string,
     *     countsAsDirectSupport:bool,
     *     component:?string,
     *     gates:list<string>,
     *     diagnostics:list<string>
     * }|null
     */
    private static function directionRow(string $format, string $direction): ?array
    {
        return self::FORMAT_ROWS[$format]['directions'][$direction] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $statuses
     * @return array{supported:int, unsupported:int, total:int}
     */
    private static function supportCounts(array $statuses): array
    {
        $supported = 0;

        foreach ($statuses as $status) {
            if ($status['countsAsDirectSupport'] === true) {
                $supported++;
            }
        }

        return [
            'supported' => $supported,
            'unsupported' => count($statuses) - $supported,
            'total' => count($statuses),
        ];
    }

    private static function formatDirection(bool $hasInput, bool $hasOutput): string
    {
        if ($hasInput && $hasOutput) {
            return 'input-output';
        }

        if ($hasInput) {
            return 'input-only';
        }

        return 'output-only';
    }

    private static function normalizeFormat(string $format): string
    {
        return strtolower(trim($format));
    }

    private static function normalizeDirection(string $direction): string
    {
        $direction = strtolower(trim($direction));
        if ($direction !== 'input' && $direction !== 'output') {
            throw new \InvalidArgumentException("Unsupported rich package direction: {$direction}");
        }

        return $direction;
    }

    private static function normalizeExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));
        if ($extension === '') {
            return '';
        }
        if ($extension[0] !== '.') {
            $extension = '.' . $extension;
        }

        return $extension;
    }
}
