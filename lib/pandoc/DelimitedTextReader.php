<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextReader
{
    private const INPUT_PREFIX_BYTE_LIMIT = 64;
    private const INPUT_PREFIX_PREVIEW_BYTE_LIMIT = 32;
    private const CONTROL_CHARACTER_SAMPLE_LIMIT = 8;
    private const CONTROL_CHARACTER_SAMPLE_RADIUS = 4;
    private const DIALECT_DETECTION_BYTE_LIMIT = 65536;
    private const DIALECT_DETECTION_ROW_LIMIT = 200;
    private const DIALECT_DETECTION_MIN_MODAL_RATIO = 0.7;
    private const DIALECT_DETECTION_MAX_RAGGED_RATIO = 0.1;
    private const DIALECT_DETECTION_MIN_SCORE_MARGIN = 0.15;
    private const DEFAULT_MAX_RENDERED_ROWS = 1000;
    private const DEFAULT_MAX_RENDERED_CELLS = 20000;
    private const BLANK_ROW_SAMPLE_LIMIT = 1000;

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool, strictParsing?:bool, allowBlankRecords?:bool, cellLineBreak?:string, maxRenderedRows?:int, maxRenderedCells?:int, compactAst?:bool} $options
     */
    public function readCsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'csv', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool, strictParsing?:bool, allowBlankRecords?:bool, cellLineBreak?:string, maxRenderedRows?:int, maxRenderedCells?:int, compactAst?:bool} $options
     */
    public function readTsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'tsv', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool, strictParsing?:bool, allowBlankRecords?:bool, cellLineBreak?:string, maxRenderedRows?:int, maxRenderedCells?:int, compactAst?:bool} $options
     */
    public function readAuto(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'auto', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool, strictParsing?:bool, allowBlankRecords?:bool, cellLineBreak?:string, maxRenderedRows?:int, maxRenderedCells?:int, compactAst?:bool} $options
     */
    public function read(string $text, string $format = 'csv', array $options = []): AstNode
    {
        $inputPrefix = $this->inputPrefixReview($text);
        $strictParsing = $this->strictParsingOption($options);
        $allowBlankRecords = $this->allowBlankRecordsOption($options);
        $prefixText = $strictParsing
            ? $this->inputTextAfterBomPrefix($text, $inputPrefix)
            : $this->inputTextAfterSupportedPrefix($text, $inputPrefix);
        $sourceText = $this->pandocSourceText($prefixText, $inputPrefix);
        $formatResolution = $this->formatResolution($format, $sourceText, $options);
        $format = $formatResolution['format'];
        $dialect = $formatResolution['dialect'] ?? $this->dialectProfile($format, $options);
        $delimiter = $dialect['delimiter'];
        $formatInference = $formatResolution['formatInference'];
        $inputPrefix['formatContext'] = $this->formatContext($formatResolution, $options);
        $hasHeader = $this->headerOption($options);
        $cellLineBreak = $this->cellLineBreakOption($options);
        $maxRenderedRows = $this->maxRenderedRowsOption($options);
        $maxRenderedCells = $this->maxRenderedCellsOption($options);
        $compactAst = $this->compactAstOption($options);

        $parse = $this->parseRowsWithDiagnostics($sourceText, $dialect, $maxRenderedRows);
        if ($strictParsing) {
            $this->assertStrictParsing($format, $inputPrefix, $parse, $allowBlankRecords);
        }
        $rows = $parse['rows'];
        $fieldMetadataRows = $parse['fieldMetadataRows'];
        $sourceRowIndexes = $parse['sourceRowIndexes'];
        $blankRows = $parse['blankRows'];
        $controlCharacters = $this->controlCharacterSummary($sourceText, $dialect, $options);
        if ($rows === []) {
            $sourceAnalysis = $this->sourceAnalysis($sourceText, $dialect, []);
            $sourceExtent = $this->sourceExtentFromParse($parse, 0, 0, false);
            $packet = $this->reviewPacket($format, $dialect, [], [], [], $blankRows, $hasHeader, 0, $formatInference, $inputPrefix, $sourceAnalysis, $parse['diagnostics'], $parse['metrics'], $controlCharacters, $sourceExtent);

            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $packet,
            ]);
        }

        $widths = array_map('count', $rows);
        $columnCount = $this->repairedColumnCount($widths, $hasHeader);
        $rendered = $this->boundedRenderedRows($rows, $fieldMetadataRows, $sourceRowIndexes, $widths, $hasHeader, $columnCount, $maxRenderedRows, $maxRenderedCells);
        $rows = $rendered['rows'];
        $fieldMetadataRows = $rendered['fieldMetadataRows'];
        $sourceRowIndexes = $rendered['sourceRowIndexes'];
        $widths = $rendered['widths'];
        $sourceExtent = $this->sourceExtentFromParse($parse, count($rows), array_sum($widths), $rendered['truncated']);
        $sourceAnalysis = $this->sourceAnalysis($sourceText, $dialect, $widths);
        $sourceHeader = $hasHeader ? array_shift($rows) : null;
        $sourceHeaderFieldMetadata = $hasHeader ? array_shift($fieldMetadataRows) : null;
        $compactCells = $compactAst ? new CompactDelimitedCellStore($columnCount) : null;
        $tableRows = [];
        foreach ($rows as $index => $row) {
            $tableRows[] = $this->tableRow($row, false, $columnCount, $fieldMetadataRows[$index] ?? [], $cellLineBreak, $compactCells);
        }

        $headRows = [];
        if ($sourceHeader !== null) {
            $headRows[] = $this->tableRow($sourceHeader, true, $columnCount, $sourceHeaderFieldMetadata ?? [], $cellLineBreak, $compactCells);
        }

        $packet = $this->reviewPacket(
            $format,
            $dialect,
            $sourceHeader === null ? $rows : [$sourceHeader, ...$rows],
            $widths,
            $sourceRowIndexes,
            $blankRows,
            $hasHeader,
            $columnCount,
            $formatInference,
            $inputPrefix,
            $sourceAnalysis,
            $parse['diagnostics'],
            $parse['metrics'],
            $controlCharacters,
            $sourceExtent
        );
        $table = new AstNode('table', [
            'sourceFormat' => $format,
            'alignments' => array_fill(0, $columnCount, 'default'),
            'delimitedText' => $packet,
            'columnNames' => $sourceHeader === null ? $this->generatedColumnLabels($columnCount) : $this->normalizedRow($sourceHeader, $columnCount),
        ], [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $tableRows),
        ], $compactAst ? new CompactDelimitedTableAttributes() : null);
        if (!$compactAst) {
            $table = TableGeometry::withReviewPacket($table);
        }

        return new AstNode('document', [
            'sourceFormat' => $format,
            'delimitedText' => $packet,
        ], [$table]);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool} $options
     * @return array{format:string, delimiter:string, dialect?:array<string, mixed>, formatInference:array<string, mixed>}
     */
    private function formatResolution(string $format, string $text, array $options): array
    {
        $requestedFormat = strtolower(trim($format));
        if ($requestedFormat === '') {
            throw new \InvalidArgumentException('Delimited text format must not be empty; supported formats: csv, tsv, auto');
        }

        if ($requestedFormat === 'csv' || $requestedFormat === 'tsv') {
            return [
                'format' => $requestedFormat,
                'delimiter' => $this->delimiterForFormat($requestedFormat),
                'formatInference' => [
                    'requestedFormat' => $requestedFormat,
                    'selectedFormat' => $requestedFormat,
                    'source' => 'explicit',
                    'sourceValue' => $requestedFormat,
                    'confidence' => 'explicit',
                    'candidateScores' => [],
                ],
            ];
        }

        if ($requestedFormat !== 'auto') {
            throw new \InvalidArgumentException("Unsupported delimited text format: {$requestedFormat}; supported formats: csv, tsv, auto");
        }

        $explicitDialectInference = $this->explicitDialectInference($options);
        if ($explicitDialectInference !== null) {
            $inferredFormat = $explicitDialectInference['format'];

            return [
                'format' => $inferredFormat,
                'delimiter' => $explicitDialectInference['dialect']['delimiter'],
                'dialect' => $explicitDialectInference['dialect'],
                'formatInference' => [
                    'requestedFormat' => 'auto',
                    'selectedFormat' => $inferredFormat,
                    'source' => 'option',
                    'sourceValue' => $explicitDialectInference['sourceValue'],
                    'confidence' => 'explicit',
                    'candidateScores' => [],
                    'selectedDelimiter' => $this->formatDelimiterValue($explicitDialectInference['dialect']['delimiter']),
                    'selectedDelimiterName' => $explicitDialectInference['dialect']['delimiterName'],
                    'selectedQuote' => $explicitDialectInference['dialect']['quote'],
                    'selectedEscape' => $explicitDialectInference['dialect']['escape'],
                ],
            ];
        }

        $extensionInference = $this->inferFormatFromOptions($options);
        if ($extensionInference !== null) {
            $inferredFormat = $extensionInference['format'];

            return [
                'format' => $inferredFormat,
                'delimiter' => $this->delimiterForFormat($inferredFormat),
                'formatInference' => [
                    'requestedFormat' => 'auto',
                    'selectedFormat' => $inferredFormat,
                    'source' => $extensionInference['source'],
                    'sourceValue' => $extensionInference['sourceValue'],
                    'confidence' => 'extension',
                    'candidateScores' => [],
                ],
            ];
        }

        $contentInference = $this->inferFormatFromContent($text, $options);
        if ($contentInference['format'] === null || $contentInference['dialect'] === null) {
            $reason = $contentInference['rejectionReason'] ?? 'no-confident-delimited-text-dialect';
            throw new \InvalidArgumentException(
                "Unable to infer delimited text dialect confidently ({$reason}); pass delimiter and quote options explicitly."
            );
        }
        $inferredFormat = $contentInference['format'];
        $dialect = $contentInference['dialect'];

        return [
            'format' => $inferredFormat,
            'delimiter' => $dialect['delimiter'],
            'dialect' => $dialect,
            'formatInference' => [
                'requestedFormat' => 'auto',
                'selectedFormat' => $inferredFormat,
                'source' => 'content',
                'sourceValue' => null,
                'confidence' => $contentInference['confidence'],
                'candidateScores' => $contentInference['candidateScores'],
                'dialectCandidates' => $contentInference['dialectCandidates'],
                'selectedDelimiter' => $this->formatDelimiterValue($dialect['delimiter']),
                'selectedDelimiterName' => $dialect['delimiterName'],
                'selectedQuote' => $dialect['quote'],
                'selectedEscape' => $dialect['escape'],
                'modalColumnCount' => $contentInference['selectedCandidate']['modalColumnCount'] ?? null,
                'modalRowRatio' => $contentInference['selectedCandidate']['modalRowRatio'] ?? null,
                'scoreMarginRatio' => $contentInference['scoreMarginRatio'],
            ],
        ];
    }

    /**
     * @param array{delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool} $options
     * @return array{format:string, dialect:array<string, mixed>, sourceValue:string}|null
     */
    private function explicitDialectInference(array $options): ?array
    {
        if (!array_key_exists('delimiter', $options)) {
            return null;
        }

        $dialect = $this->dialectProfile('csv', $options);
        $format = $dialect['delimiter'] === "\t" ? 'tsv' : 'csv';

        return [
            'format' => $format,
            'dialect' => $dialect,
            'sourceValue' => $this->formatDelimiterValue($dialect['delimiter']),
        ];
    }

    private function delimiterForFormat(string $format): string
    {
        return match ($format) {
            'csv' => ',',
            'tsv' => "\t",
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}; supported formats: csv, tsv, auto"),
        };
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     * @return array{format:string, source:string, sourceValue:string}|null
     */
    private function inferFormatFromOptions(array $options): ?array
    {
        if (array_key_exists('extension', $options)) {
            if (!is_string($options['extension'])) {
                throw new \InvalidArgumentException('Delimited text extension option must be a string');
            }

            $extension = trim($options['extension']);
            if ($extension !== '') {
                $format = PandocFormatRegistry::inferTabularDataFormatFromExtension($extension);
                if ($format !== null) {
                    return [
                        'format' => $format,
                        'source' => 'extension',
                        'sourceValue' => $extension,
                    ];
                }
            }
        }

        if (array_key_exists('sourcePath', $options)) {
            if (!is_string($options['sourcePath'])) {
                throw new \InvalidArgumentException('Delimited text sourcePath option must be a string');
            }

            $sourcePath = trim($options['sourcePath']);
            $extension = $sourcePath === '' ? '' : pathinfo($sourcePath, PATHINFO_EXTENSION);
            if ($extension !== '') {
                $format = PandocFormatRegistry::inferTabularDataFormatFromExtension($extension);
                if ($format !== null) {
                    return [
                        'format' => $format,
                        'source' => 'source-path',
                        'sourceValue' => $sourcePath,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param array{quote?:string|null|false, escape?:string|null|false, keepSpace?:bool} $options
     * @return array{
     *     format:string|null,
     *     dialect:array<string, mixed>|null,
     *     confidence:string,
     *     candidateScores:array<string, array<string, mixed>>,
     *     dialectCandidates:list<array<string, mixed>>,
     *     selectedCandidate:array<string, mixed>|null,
     *     scoreMarginRatio:float|null,
     *     rejectionReason:string|null
     * }
     */
    private function inferFormatFromContent(string $text, array $options = []): array
    {
        $text = $this->detectionSample($this->stripBom($text));
        $candidateScores = [
            'csv' => $this->scoreDelimitedRows($text, ','),
            'tsv' => $this->scoreDelimitedRows($text, "\t"),
        ];
        $candidates = [];
        foreach ($this->detectionDelimiterCandidates() as $delimiter) {
            foreach ($this->detectionQuoteCandidates($options, $delimiter) as $quote) {
                foreach ($this->detectionEscapeCandidates($options) as $escape) {
                    if ($quote === null && $escape !== null) {
                        continue;
                    }
                    $dialect = [
                        'delimiter' => $delimiter,
                        'delimiterName' => $this->delimiterName($delimiter),
                        'quote' => $quote,
                        'escape' => $escape,
                        'keepSpace' => $this->keepSpaceDetectionOption($options),
                    ];
                    $candidates[] = $this->scoreDialectCandidate($text, $dialect);
                }
            }
        }

        usort($candidates, static function (array $left, array $right): int {
            return ($right['score'] <=> $left['score'])
                ?: ($right['quotePreference'] <=> $left['quotePreference'])
                ?: ($right['escapePreference'] <=> $left['escapePreference'])
                ?: ($right['delimiterPreference'] <=> $left['delimiterPreference']);
        });

        $validCandidates = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => ($candidate['accepted'] ?? false) === true
        ));
        $selected = $validCandidates[0] ?? null;
        $secondDelimiter = $selected === null ? null : $this->nextBestDifferentDelimiter($validCandidates, $selected);
        $marginRatio = null;
        if ($selected !== null && $secondDelimiter !== null && (int) $selected['score'] > 0) {
            $marginRatio = ((int) $selected['score'] - (int) $secondDelimiter['score']) / (int) $selected['score'];
            if ($marginRatio < self::DIALECT_DETECTION_MIN_SCORE_MARGIN) {
                $selected = null;
            }
        }

        $rejectionReason = null;
        if ($selected === null) {
            $rejectionReason = $this->dialectDetectionRejectionReason($validCandidates, $candidates, $marginRatio);
        }

        $dialect = $selected['dialect'] ?? null;
        $format = null;
        if (is_array($dialect)) {
            $format = $dialect['delimiter'] === "\t" ? 'tsv' : 'csv';
        }

        return [
            'format' => $format,
            'dialect' => is_array($dialect) ? $dialect : null,
            'confidence' => $selected === null ? 'low' : ((float) ($selected['modalRowRatio'] ?? 0.0) >= 0.9 ? 'high' : 'medium'),
            'candidateScores' => $candidateScores,
            'dialectCandidates' => array_slice($candidates, 0, 12),
            'selectedCandidate' => $selected,
            'scoreMarginRatio' => $marginRatio,
            'rejectionReason' => $rejectionReason,
        ];
    }

    /**
     * @return array{rows:int, multicolumnRows:int, columnCount:int, fieldCount:int, modalColumnCount:int, modalRowCount:int, modalRowRatio:float, raggedRowCount:int, badDiagnosticCount:int}
     */
    private function scoreDelimitedRows(string $text, string $delimiter): array
    {
        try {
            $parse = $this->parseRowsWithDiagnostics($text, $this->dialectProfileForDelimiter($delimiter));
            $rows = $parse['rows'];
        } catch (\InvalidArgumentException) {
            return [
                'rows' => 0,
                'multicolumnRows' => 0,
                'columnCount' => 0,
                'fieldCount' => 0,
                'modalColumnCount' => 0,
                'modalRowCount' => 0,
                'modalRowRatio' => 0.0,
                'raggedRowCount' => 0,
                'badDiagnosticCount' => 0,
            ];
        }
        $widths = array_map('count', $rows);
        $modal = $this->modalWidth($widths);

        return [
            'rows' => count($rows),
            'multicolumnRows' => count(array_filter($widths, static fn (int $width): bool => $width > 1)),
            'columnCount' => $widths === [] ? 0 : max($widths),
            'fieldCount' => array_sum($widths),
            'modalColumnCount' => $modal['width'],
            'modalRowCount' => $modal['count'],
            'modalRowRatio' => count($rows) === 0 ? 0.0 : $modal['count'] / count($rows),
            'raggedRowCount' => count($rows) - $modal['count'],
            'badDiagnosticCount' => $this->badParseDiagnosticCount($parse['metrics'] ?? []),
        ];
    }

    private function detectionSample(string $text): string
    {
        if (strlen($text) > self::DIALECT_DETECTION_BYTE_LIMIT) {
            $text = substr($text, 0, self::DIALECT_DETECTION_BYTE_LIMIT);
        }

        if ($text === '') {
            return $text;
        }

        $rowCount = 0;
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            if (!$this->isLineBreak($text[$offset])) {
                continue;
            }
            $rowCount++;
            if ($text[$offset] === "\r" && ($text[$offset + 1] ?? '') === "\n") {
                $offset++;
            }
            if ($rowCount >= self::DIALECT_DETECTION_ROW_LIMIT) {
                return substr($text, 0, $offset + 1);
            }
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private function detectionDelimiterCandidates(): array
    {
        return ["\t", ',', ';', '|', ' '];
    }

    /**
     * @param array{quote?:string|null|false} $options
     * @return list<string|null>
     */
    private function detectionQuoteCandidates(array $options, string $delimiter): array
    {
        if (array_key_exists('quote', $options)) {
            return [$this->optionalSingleCharacterOption($options['quote'], 'quote')];
        }

        if ($delimiter === "\t") {
            return [null, '"', "'"];
        }

        return ['"', "'", null];
    }

    /**
     * @param array{escape?:string|null|false} $options
     * @return list<string|null>
     */
    private function detectionEscapeCandidates(array $options): array
    {
        if (array_key_exists('escape', $options)) {
            return [$this->optionalSingleCharacterOption($options['escape'], 'escape')];
        }

        return [null, '\\'];
    }

    /**
     * @param array{keepSpace?:bool} $options
     */
    private function keepSpaceDetectionOption(array $options): bool
    {
        if (!array_key_exists('keepSpace', $options)) {
            return false;
        }
        if (!is_bool($options['keepSpace'])) {
            throw new \InvalidArgumentException('Delimited text keepSpace option must be a boolean');
        }

        return $options['keepSpace'];
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool} $dialect
     * @return array<string, mixed>
     */
    private function scoreDialectCandidate(string $text, array $dialect): array
    {
        try {
            $parse = $this->parseRowsWithDiagnostics($text, $dialect);
        } catch (\InvalidArgumentException $exception) {
            return [
                'accepted' => false,
                'rejectionReason' => 'parse-error',
                'error' => $exception->getMessage(),
                'score' => 0,
                'delimiter' => $this->formatDelimiterValue($dialect['delimiter']),
                'delimiterName' => $dialect['delimiterName'],
                'quote' => $dialect['quote'],
                'escape' => $dialect['escape'],
                'dialect' => $dialect,
                'quotePreference' => 0,
                'escapePreference' => 0,
                'delimiterPreference' => $this->delimiterPreference($dialect['delimiter']),
            ];
        }

        $rows = $parse['rows'];
        $metrics = $parse['metrics'];
        $widths = array_map('count', $rows);
        $rowCount = count($rows);
        $modal = $this->modalWidth($widths);
        $modalWidth = $modal['width'];
        $modalCount = $modal['count'];
        $modalRatio = $rowCount === 0 ? 0.0 : $modalCount / $rowCount;
        $multicolumnRows = count(array_filter($widths, static fn (int $width): bool => $width > 1));
        $raggedRowCount = max(0, $rowCount - $modalCount);
        $raggedRatio = $rowCount === 0 ? 0.0 : $raggedRowCount / $rowCount;
        $badDiagnosticCount = $this->badParseDiagnosticCount($metrics);
        $accepted = true;
        $rejectionReason = null;
        if ($rowCount === 0) {
            $accepted = false;
            $rejectionReason = 'no-data-rows';
        } elseif ($modalWidth < 2) {
            $accepted = false;
            $rejectionReason = 'modal-column-count-below-2';
        } elseif ($multicolumnRows === 0) {
            $accepted = false;
            $rejectionReason = 'no-multicolumn-rows';
        } elseif ($rowCount === 1 && $modalWidth < 3) {
            $accepted = false;
            $rejectionReason = 'single-row-needs-repeated-delimiter';
        } elseif ($dialect['delimiter'] === ' ' && !$this->spaceDelimitedCandidateHasSignal($rows)) {
            $accepted = false;
            $rejectionReason = 'space-delimiter-needs-structured-fields';
        } elseif ($modalRatio < self::DIALECT_DETECTION_MIN_MODAL_RATIO) {
            $accepted = false;
            $rejectionReason = 'modal-row-ratio-too-low';
        } elseif ($raggedRatio > self::DIALECT_DETECTION_MAX_RAGGED_RATIO) {
            $accepted = false;
            $rejectionReason = 'ragged-row-ratio-too-high';
        } elseif ($badDiagnosticCount > 0) {
            $accepted = false;
            $rejectionReason = 'malformed-quoted-field';
        }

        $quotedFieldCount = (int) ($metrics['quotedFieldCount'] ?? 0);
        $escapedQuoteSequenceCount = (int) ($metrics['escapedQuoteSequenceCount'] ?? 0);
        $delimiterPreference = $this->delimiterPreference($dialect['delimiter']);
        $quotePreference = $this->quotePreference($dialect, $quotedFieldCount);
        $escapePreference = $this->escapePreference($dialect, $escapedQuoteSequenceCount);
        $score = ($modalCount * 10000)
            + ($modalWidth * 1000)
            + ($multicolumnRows * 300)
            + (array_sum($widths) * 10)
            + ($quotedFieldCount * 80)
            + ($escapedQuoteSequenceCount * 20)
            + $delimiterPreference
            + $quotePreference
            + $escapePreference
            - ($raggedRowCount * 5000)
            - ($badDiagnosticCount * 20000);

        return [
            'accepted' => $accepted,
            'rejectionReason' => $rejectionReason,
            'score' => max(0, $score),
            'delimiter' => $this->formatDelimiterValue($dialect['delimiter']),
            'delimiterName' => $dialect['delimiterName'],
            'quote' => $dialect['quote'],
            'escape' => $dialect['escape'],
            'rows' => $rowCount,
            'multicolumnRows' => $multicolumnRows,
            'modalColumnCount' => $modalWidth,
            'modalRowCount' => $modalCount,
            'modalRowRatio' => $modalRatio,
            'raggedRowCount' => $raggedRowCount,
            'raggedRowRatio' => $raggedRatio,
            'fieldCount' => array_sum($widths),
            'quotedFieldCount' => $quotedFieldCount,
            'escapedQuoteSequenceCount' => $escapedQuoteSequenceCount,
            'badDiagnosticCount' => $badDiagnosticCount,
            'diagnosticCodes' => array_values(array_unique(array_column($parse['diagnostics'] ?? [], 'code'))),
            'dialect' => $dialect,
            'quotePreference' => $quotePreference,
            'escapePreference' => $escapePreference,
            'delimiterPreference' => $delimiterPreference,
        ];
    }

    /**
     * @param list<list<string>> $rows
     */
    private function spaceDelimitedCandidateHasSignal(array $rows): bool
    {
        if (count($rows) < 3) {
            return false;
        }

        foreach (array_slice($rows, 1) as $row) {
            foreach ($row as $field) {
                if (preg_match('/[0-9_.:+-]/', $field) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<int> $widths
     * @return array{width:int, count:int}
     */
    private function modalWidth(array $widths): array
    {
        $counts = [];
        foreach ($widths as $width) {
            $counts[$width] = ($counts[$width] ?? 0) + 1;
        }
        if ($counts === []) {
            return ['width' => 0, 'count' => 0];
        }

        $bestWidth = 0;
        $bestCount = 0;
        foreach ($counts as $width => $count) {
            $width = (int) $width;
            if ($count > $bestCount || ($count === $bestCount && $width > $bestWidth)) {
                $bestWidth = $width;
                $bestCount = $count;
            }
        }

        return ['width' => $bestWidth, 'count' => $bestCount];
    }

    /**
     * @param array<string, int> $metrics
     */
    private function badParseDiagnosticCount(array $metrics): int
    {
        return (int) ($metrics['textAfterClosingQuoteCount'] ?? 0)
            + (int) ($metrics['closingQuoteTrailingWhitespaceCount'] ?? 0)
            + (int) ($metrics['closingQuoteTrailingRecordWhitespaceCount'] ?? 0)
            + (int) ($metrics['escapeBeforeLineBreakCount'] ?? 0)
            + (int) ($metrics['unclosedQuoteCount'] ?? 0)
            + (int) ($metrics['emptyQuotedFirstFieldWithoutDelimiterCount'] ?? 0);
    }

    /**
     * @param list<array<string, mixed>> $validCandidates
     * @return array<string, mixed>|null
     */
    private function nextBestDifferentDelimiter(array $validCandidates, array $selected): ?array
    {
        foreach ($validCandidates as $candidate) {
            if (($candidate['delimiterName'] ?? null) !== ($selected['delimiterName'] ?? null)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $validCandidates
     * @param list<array<string, mixed>> $allCandidates
     */
    private function dialectDetectionRejectionReason(array $validCandidates, array $allCandidates, ?float $marginRatio): string
    {
        if ($validCandidates !== [] && $marginRatio !== null && $marginRatio < self::DIALECT_DETECTION_MIN_SCORE_MARGIN) {
            return 'best-candidate-too-close-to-runner-up';
        }
        if ($validCandidates === []) {
            $reasons = array_values(array_unique(array_filter(array_map(
                static fn (array $candidate): ?string => is_string($candidate['rejectionReason'] ?? null) ? $candidate['rejectionReason'] : null,
                $allCandidates
            ))));

            return $reasons === [] ? 'no-accepted-candidates' : implode(',', array_slice($reasons, 0, 3));
        }

        return 'no-confident-candidate';
    }

    private function delimiterPreference(string $delimiter): int
    {
        return match ($delimiter) {
            "\t" => 50,
            ',' => 45,
            ';' => 40,
            '|' => 35,
            ' ' => 20,
            default => 0,
        };
    }

    /**
     * @param array{delimiter:string, quote:string|null} $dialect
     */
    private function quotePreference(array $dialect, int $quotedFieldCount): int
    {
        if ($quotedFieldCount > 0) {
            return $dialect['quote'] === null ? 0 : 60;
        }

        if ($dialect['delimiter'] === "\t") {
            return $dialect['quote'] === null ? 30 : 0;
        }

        return $dialect['quote'] === '"' ? 30 : ($dialect['quote'] === null ? 10 : 0);
    }

    /**
     * @param array{escape:string|null} $dialect
     */
    private function escapePreference(array $dialect, int $escapedQuoteSequenceCount): int
    {
        if ($escapedQuoteSequenceCount > 0) {
            return $dialect['escape'] === null ? 0 : 25;
        }

        return $dialect['escape'] === null ? 15 : 0;
    }

    private function formatDelimiterValue(string $delimiter): string
    {
        return $delimiter === "\t" ? 'tab' : $delimiter;
    }

    /**
     * @param array{header?:bool} $options
     */
    private function headerOption(array $options): bool
    {
        if (!array_key_exists('header', $options)) {
            return true;
        }

        if (!is_bool($options['header'])) {
            throw new \InvalidArgumentException('Delimited text header option must be a boolean');
        }

        return $options['header'];
    }

    /**
     * @param array{strictParsing?:bool} $options
     */
    private function strictParsingOption(array $options): bool
    {
        if (!array_key_exists('strictParsing', $options)) {
            return true;
        }

        if (!is_bool($options['strictParsing'])) {
            throw new \InvalidArgumentException('Delimited text strictParsing option must be a boolean');
        }

        return $options['strictParsing'];
    }

    /**
     * @param array{allowBlankRecords?:bool} $options
     */
    private function allowBlankRecordsOption(array $options): bool
    {
        if (!array_key_exists('allowBlankRecords', $options)) {
            return false;
        }
        if (!is_bool($options['allowBlankRecords'])) {
            throw new \InvalidArgumentException('Delimited text allowBlankRecords option must be a boolean');
        }

        return $options['allowBlankRecords'];
    }

    /**
     * @param list<int> $widths
     */
    private function repairedColumnCount(array $widths, bool $hasHeader): int
    {
        if ($widths === []) {
            return 0;
        }

        return $widths[0];
    }

    /**
     * @param list<int> $widths
     */
    private function rowRepairPolicy(bool $hasHeader, array $widths): string
    {
        return 'pandoc-first-row-width';
    }

    /**
     * @return list<list<string>>
     */
    private function parseRows(string $text, string $delimiter): array
    {
        return $this->parseRowsWithDiagnostics($text, $this->dialectProfileForDelimiter($delimiter))['rows'];
    }

    /**
     * @param array{delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool} $options
     * @return array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool}
     */
    private function dialectProfile(string $format, array $options = []): array
    {
        $profile = match ($format) {
            'csv' => [
                'delimiter' => ',',
                'delimiterName' => 'comma',
                'quote' => '"',
                'escape' => null,
                'keepSpace' => false,
            ],
            'tsv' => [
                'delimiter' => "\t",
                'delimiterName' => 'tab',
                'quote' => null,
                'escape' => null,
                'keepSpace' => false,
            ],
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}; supported formats: csv, tsv, auto"),
        };

        if (array_key_exists('delimiter', $options)) {
            if (!is_string($options['delimiter'])) {
                throw new \InvalidArgumentException('Delimited text delimiter option must be a string');
            }

            $profile['delimiter'] = $this->delimiterOptionValue($options['delimiter']);
            $profile['delimiterName'] = $this->delimiterName($profile['delimiter']);
        }

        if (array_key_exists('quote', $options)) {
            $profile['quote'] = $this->optionalSingleCharacterOption($options['quote'], 'quote');
        }

        if (array_key_exists('escape', $options)) {
            $profile['escape'] = $this->optionalSingleCharacterOption($options['escape'], 'escape');
        }

        if (array_key_exists('keepSpace', $options)) {
            if (!is_bool($options['keepSpace'])) {
                throw new \InvalidArgumentException('Delimited text keepSpace option must be a boolean');
            }
            $profile['keepSpace'] = $options['keepSpace'];
        }

        return $profile;
    }

    /**
     * @return array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool}
     */
    private function dialectProfileForDelimiter(string $delimiter): array
    {
        return $delimiter === "\t" ? $this->dialectProfile('tsv') : $this->dialectProfile('csv');
    }

    private function delimiterOptionValue(string $delimiter): string
    {
        $normalized = strtolower(trim($delimiter));
        $value = match ($normalized) {
            'comma' => ',',
            'tab', '\t' => "\t",
            'space' => ' ',
            'semicolon', 'semi' => ';',
            'pipe', 'bar' => '|',
            default => $delimiter,
        };

        if ($value === '') {
            throw new \InvalidArgumentException('Delimited text delimiter option must not be empty');
        }
        if ($this->length($value) !== 1) {
            throw new \InvalidArgumentException('Delimited text delimiter option must be a single character or known delimiter name');
        }

        return $value;
    }

    private function delimiterName(string $delimiter): string
    {
        return match ($delimiter) {
            ',' => 'comma',
            "\t" => 'tab',
            ' ' => 'space',
            ';' => 'semicolon',
            '|' => 'pipe',
            default => $delimiter,
        };
    }

    private function optionalSingleCharacterOption(mixed $value, string $name): ?string
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Delimited text {$name} option must be a string, null, or false");
        }
        if ($this->length($value) !== 1) {
            throw new \InvalidArgumentException("Delimited text {$name} option must be a single character");
        }

        return $value;
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        if (preg_match_all('/./us', $text, $matches) === 1) {
            return count($matches[0]);
        }

        return strlen($text);
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool} $dialect
     * @return array{
     *     rows:list<list<string>>,
     *     fieldMetadataRows:list<list<array<string, mixed>>>,
     *     sourceRowIndexes:list<int>,
     *     blankRows:list<int>,
     *     diagnostics:list<array<string, mixed>>,
     *     metrics:array<string, mixed>|array{
     *         quotedFieldCount:int,
     *         doubledQuoteEscapeCount:int,
     *         escapedQuoteSequenceCount:int,
     *         quoteInUnquotedFieldCount:int,
     *         textAfterClosingQuoteCount:int,
     *         closingQuoteTrailingWhitespaceCount:int,
     *         closingQuoteTrailingRecordWhitespaceCount:int,
     *         escapeBeforeLineBreakCount:int,
     *         unclosedQuoteCount:int,
     *         quotedLineBreakCount:int,
     *         multilineFieldCount:int,
     *         partialRecordCount:int,
     *         emptyQuotedFirstFieldWithoutDelimiterCount:int
     *     }
     * }
     */
    private function parseRowsWithDiagnostics(string $text, array $dialect, ?int $maxStoredRows = null): array
    {
        $delimiter = $dialect['delimiter'];
        $quote = $dialect['quote'];
        $escape = $dialect['escape'];
        $keepSpace = $dialect['keepSpace'];
        $length = strlen($text);
        $lineStarts = $this->lineStartOffsets($text);
        $sourceSpan = function (int $sourceStartOffset, int $sourceEndOffset) use ($lineStarts): array {
            return $this->sourceSpan($lineStarts, $sourceStartOffset, $sourceEndOffset);
        };
        $rows = [];
        $fieldMetadataRows = [];
        $sourceRowIndexes = [];
        $blankRows = [];
        $diagnostics = [];
        $metrics = [
            'quotedFieldCount' => 0,
            'doubledQuoteEscapeCount' => 0,
            'escapedQuoteSequenceCount' => 0,
            'quoteInUnquotedFieldCount' => 0,
            'textAfterClosingQuoteCount' => 0,
            'closingQuoteTrailingWhitespaceCount' => 0,
            'closingQuoteTrailingRecordWhitespaceCount' => 0,
            'escapeBeforeLineBreakCount' => 0,
            'unclosedQuoteCount' => 0,
            'quotedLineBreakCount' => 0,
            'multilineFieldCount' => 0,
            'partialRecordCount' => 0,
            'emptyQuotedFirstFieldWithoutDelimiterCount' => 0,
            'sourceRowCount' => 0,
            'sourceFieldCount' => 0,
            'sourceMinFieldCount' => 0,
            'sourceMaxFieldCount' => 0,
            'storedRowCount' => 0,
            'omittedRowCount' => 0,
            'blankRowCount' => 0,
            'blankRowsTruncated' => 0,
        ];
        $whitespaceOnlyBlankRows = $this->pandocWhitespaceOnlyDocumentBlankRows($text);
        if ($whitespaceOnlyBlankRows !== null) {
            $metrics['blankRowCount'] = count($whitespaceOnlyBlankRows);
            return [
                'rows' => [],
                'fieldMetadataRows' => [],
                'sourceRowIndexes' => [],
                'blankRows' => $whitespaceOnlyBlankRows,
                'diagnostics' => [],
                'metrics' => $metrics,
            ];
        }

        $row = [];
        $rowFieldMetadata = [];
        $rowFieldCount = 0;
        $firstField = null;
        $firstFieldQuoted = false;
        $firstFieldEndOffset = null;
        $storeCurrentRow = true;
        $field = '';
        $rowIndex = 0;
        $columnIndex = 0;
        $fieldStartOffset = 0;
        $fieldStarted = false;
        $quotedField = false;
        $inQuotedField = false;
        $afterClosingQuote = false;
        $afterClosingQuoteWhitespace = false;
        $fieldHadQuotedLineBreak = false;
        $quotedFieldStartRow = 0;
        $quotedFieldStartColumn = 0;
        $quotedFieldStartOffset = 0;

        $finishField = static function (int $sourceEndOffset) use (
            &$row,
            &$rowFieldMetadata,
            &$rowFieldCount,
            &$firstField,
            &$firstFieldQuoted,
            &$firstFieldEndOffset,
            &$storeCurrentRow,
            &$field,
            &$columnIndex,
            &$fieldStartOffset,
            &$rowIndex,
            &$fieldStarted,
            &$quotedField,
            &$inQuotedField,
            &$afterClosingQuote,
            &$afterClosingQuoteWhitespace,
            &$fieldHadQuotedLineBreak,
            &$metrics,
            $sourceSpan
        ): void {
            $sourceEndOffset = max($fieldStartOffset, $sourceEndOffset);
            $rowFieldCount++;
            if ($rowFieldCount === 1) {
                $firstField = $field;
                $firstFieldQuoted = $quotedField;
                $firstFieldEndOffset = $sourceEndOffset;
            }
            if ($storeCurrentRow) {
                $span = $sourceSpan($fieldStartOffset, $sourceEndOffset);
                $row[] = $field;
                $rowFieldMetadata[] = [
                    'sourceRow' => $rowIndex,
                    'sourceRowNumber' => $rowIndex + 1,
                    'sourceField' => $columnIndex,
                    'sourceFieldNumber' => $columnIndex + 1,
                    'sourceStartOffset' => $fieldStartOffset,
                    'sourceEndOffset' => $sourceEndOffset,
                    'sourceByteRange' => [$fieldStartOffset, $sourceEndOffset],
                    'sourceByteLength' => $sourceEndOffset - $fieldStartOffset,
                    ...$span,
                    'sourceQuoted' => $quotedField,
                    'sourceQuoteClosed' => !$quotedField || !$inQuotedField,
                    'sourceMultiline' => $fieldHadQuotedLineBreak,
                ];
            }
            if ($quotedField && $fieldHadQuotedLineBreak) {
                $metrics['multilineFieldCount']++;
            }

            $field = '';
            $columnIndex++;
            $fieldStartOffset = $sourceEndOffset + 1;
            $fieldStarted = false;
            $quotedField = false;
            $afterClosingQuote = false;
            $afterClosingQuoteWhitespace = false;
            $fieldHadQuotedLineBreak = false;
        };

        $finishRow = static function (int $sourceEndOffset, int $nextRowStartOffset) use (
            &$rows,
            &$fieldMetadataRows,
            &$row,
            &$rowFieldMetadata,
            &$rowFieldCount,
            &$firstField,
            &$firstFieldQuoted,
            &$firstFieldEndOffset,
            &$storeCurrentRow,
            &$sourceRowIndexes,
            &$blankRows,
            &$field,
            &$rowIndex,
            &$columnIndex,
            &$fieldStartOffset,
            &$fieldStarted,
            &$quotedField,
            &$afterClosingQuote,
            &$afterClosingQuoteWhitespace,
            &$diagnostics,
            &$metrics,
            $finishField,
            $maxStoredRows
        ): void {
            $hasPendingField = $fieldStarted || $field !== '' || $rowFieldCount > 0 || $row !== [] || $quotedField || $afterClosingQuote;
            if ($hasPendingField) {
                $finishField($sourceEndOffset);
                $isSingleEmptyFieldRecord = $rowFieldCount === 1 && $firstField === '';
                if ($isSingleEmptyFieldRecord) {
                    if ($firstFieldQuoted) {
                        $metrics['emptyQuotedFirstFieldWithoutDelimiterCount']++;
                        $diagnostics[] = [
                            'code' => 'delimited-text-empty-first-field-without-delimiter',
                            'severity' => 'warning',
                            'message' => 'A quoted empty first field was not followed by a delimiter; Pandoc rejects empty first fields unless another field is present.',
                            'row' => $rowIndex,
                            'column' => 0,
                            'offset' => (int) ($firstFieldEndOffset ?? $sourceEndOffset),
                        ];
                    }
                }

                if (!$isSingleEmptyFieldRecord) {
                    $metrics['sourceRowCount']++;
                    $metrics['sourceFieldCount'] += $rowFieldCount;
                    $metrics['sourceMinFieldCount'] = $metrics['sourceRowCount'] === 1
                        ? $rowFieldCount
                        : min((int) $metrics['sourceMinFieldCount'], $rowFieldCount);
                    $metrics['sourceMaxFieldCount'] = max((int) $metrics['sourceMaxFieldCount'], $rowFieldCount);
                    if ($storeCurrentRow) {
                        $rows[] = $row;
                        $fieldMetadataRows[] = $rowFieldMetadata;
                        $sourceRowIndexes[] = $rowIndex;
                        $metrics['storedRowCount'] = count($rows);
                    } else {
                        $metrics['omittedRowCount']++;
                    }
                } else {
                    $metrics['blankRowCount']++;
                    if (count($blankRows) < self::BLANK_ROW_SAMPLE_LIMIT) {
                        $blankRows[] = $rowIndex;
                    } else {
                        $metrics['blankRowsTruncated']++;
                    }
                }
            } else {
                $metrics['blankRowCount']++;
                if (count($blankRows) < self::BLANK_ROW_SAMPLE_LIMIT) {
                    $blankRows[] = $rowIndex;
                } else {
                    $metrics['blankRowsTruncated']++;
                }
            }

            $row = [];
            $rowFieldMetadata = [];
            $rowFieldCount = 0;
            $firstField = null;
            $firstFieldQuoted = false;
            $firstFieldEndOffset = null;
            $rowIndex++;
            $columnIndex = 0;
            $fieldStartOffset = $nextRowStartOffset;
            $afterClosingQuoteWhitespace = false;
            $storeCurrentRow = $maxStoredRows === null || count($rows) < $maxStoredRows;
        };

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            $next = $offset + 1 < $length ? $text[$offset + 1] : '';

            if ($inQuotedField) {
                if ($this->isLineBreak($char)) {
                    $field .= "\n";
                    $metrics['quotedLineBreakCount']++;
                    $fieldHadQuotedLineBreak = true;
                    if ($char === "\r" && $next === "\n") {
                        $offset++;
                    }

                    continue;
                }

                if ($escape !== null && $this->matchesTokenAt($text, $offset, $escape)) {
                    $nextOffset = $offset + strlen($escape);
                    if ($nextOffset < $length && !$this->isLineBreak($text[$nextOffset])) {
                        $escaped = $this->textCharacterAt($text, $nextOffset);
                        $field .= $escaped;
                        if ($quote !== null && $this->matchesTokenAt($text, $nextOffset, $quote)) {
                            $metrics['escapedQuoteSequenceCount']++;
                        }
                        $offset = $nextOffset + strlen($escaped) - 1;
                        continue;
                    }

                    $metrics['escapeBeforeLineBreakCount']++;
                    $diagnostics[] = $this->diagnostic(
                        'delimited-text-escape-before-linebreak',
                        'warning',
                        'An explicit escape character was followed by a line break or end of input inside a quoted field; Pandoc only escapes non-line-break characters.',
                        $rowIndex,
                        $columnIndex,
                        $offset
                    );
                }

                if ($quote !== null && $this->matchesTokenAt($text, $offset, $quote)) {
                    $nextQuoteOffset = $offset + strlen($quote);
                    if ($escape === null && $this->matchesTokenAt($text, $nextQuoteOffset, $quote)) {
                        $field .= $quote;
                        $metrics['doubledQuoteEscapeCount']++;
                        $offset = $nextQuoteOffset + strlen($quote) - 1;
                        continue;
                    }

                    $inQuotedField = false;
                    $afterClosingQuote = true;
                    $afterClosingQuoteWhitespace = false;
                    $offset += strlen($quote) - 1;
                    continue;
                }

                $field .= $char;
                continue;
            }

            if ($afterClosingQuote) {
                if ($this->matchesTokenAt($text, $offset, $delimiter)) {
                    $delimiterEndOffset = $offset + strlen($delimiter);
                    if ($afterClosingQuoteWhitespace) {
                        $metrics['closingQuoteTrailingWhitespaceCount']++;
                    }
                    $finishField($offset);
                    $fieldStartOffset = $delimiterEndOffset;
                    $this->skipPostDelimiterWhitespace($text, $offset, $delimiter, $keepSpace);
                    continue;
                }

                if ($this->isLineBreak($char)) {
                    $nextRowStartOffset = $char === "\r" && $next === "\n" ? $offset + 2 : $offset + 1;
                    if ($afterClosingQuoteWhitespace && !$this->isOnlyTrailingWhitespace($text, $nextRowStartOffset)) {
                        $metrics['closingQuoteTrailingRecordWhitespaceCount']++;
                        $diagnostics[] = $this->diagnostic(
                            'delimited-text-closing-quote-trailing-record-whitespace',
                            'warning',
                            'Whitespace after a closing quote before a line break was retained; Pandoc only accepts this at end of input.',
                            $rowIndex,
                            $columnIndex,
                            $offset
                        );
                    }
                    $finishRow($offset, $nextRowStartOffset);
                    if ($char === "\r" && $next === "\n") {
                        $offset++;
                    }

                    continue;
                }

                if ($this->isPandocNonLineBreakFinalWhitespace($char)) {
                    $afterClosingQuoteWhitespace = true;
                    continue;
                }

                $field .= $char;
                $fieldStarted = true;
                $afterClosingQuote = false;
                $afterClosingQuoteWhitespace = false;
                $metrics['textAfterClosingQuoteCount']++;
                $diagnostics[] = $this->diagnostic(
                    'delimited-text-text-after-closing-quote',
                    'warning',
                    'Text after a closing quote was retained in the field for review.',
                    $rowIndex,
                    $columnIndex,
                    $offset
                );
                continue;
            }

            if ($this->matchesTokenAt($text, $offset, $delimiter)) {
                $delimiterEndOffset = $offset + strlen($delimiter);
                $finishField($offset);
                $fieldStartOffset = $delimiterEndOffset;
                $this->skipPostDelimiterWhitespace($text, $offset, $delimiter, $keepSpace);
                continue;
            }

            if ($this->isLineBreak($char)) {
                $finishRow($offset, $char === "\r" && $next === "\n" ? $offset + 2 : $offset + 1);
                if ($char === "\r" && $next === "\n") {
                    $offset++;
                }

                continue;
            }

            if ($quote !== null && $this->matchesTokenAt($text, $offset, $quote)) {
                if (!$fieldStarted && $field === '') {
                    $fieldStartOffset = $offset;
                    $fieldStarted = true;
                    $quotedField = true;
                    $inQuotedField = true;
                    $quotedFieldStartRow = $rowIndex;
                    $quotedFieldStartColumn = $columnIndex;
                    $quotedFieldStartOffset = $offset;
                    $metrics['quotedFieldCount']++;
                    $offset += strlen($quote) - 1;
                    continue;
                }

                $field .= $quote;
                $fieldStarted = true;
                $metrics['quoteInUnquotedFieldCount']++;
                $diagnostics[] = $this->diagnostic(
                    'delimited-text-quote-in-unquoted-field',
                    'warning',
                    'Quote character appeared inside an unquoted field and was retained literally.',
                    $rowIndex,
                    $columnIndex,
                    $offset
                );
                $offset += strlen($quote) - 1;
                continue;
            }

            if (!$fieldStarted && $field === '') {
                $fieldStartOffset = $offset;
            }
            $field .= $char;
            $fieldStarted = true;
        }

        if ($inQuotedField) {
            $metrics['unclosedQuoteCount']++;
            $metrics['partialRecordCount']++;
            $diagnostics[] = $this->diagnostic(
                'delimited-text-unclosed-quoted-field',
                'warning',
                'A quoted field reached end of input before a closing quote; the partial record was preserved.',
                $quotedFieldStartRow,
                $quotedFieldStartColumn,
                $quotedFieldStartOffset
            );
        }

        $hasPendingRow = $fieldStarted || $field !== '' || $row !== [] || $quotedField || $afterClosingQuote;
        if ($hasPendingRow) {
            $finishRow($length, $length);
        }

        return [
            'rows' => $rows,
            'fieldMetadataRows' => $fieldMetadataRows,
            'sourceRowIndexes' => $sourceRowIndexes,
            'blankRows' => $blankRows,
            'diagnostics' => $this->diagnosticsWithSourcePositions($diagnostics, $lineStarts),
            'metrics' => $metrics,
        ];
    }

    /**
     * @param array<string, mixed> $inputPrefix
     * @param array{
     *     rows:list<list<string>>,
     *     sourceRowIndexes:list<int>,
     *     blankRows:list<int>,
     *     diagnostics:list<array{code:string, severity:string, message:string, row:int, column:int, offset:int}>,
     *     metrics:array<string, int>
     * } $parse
     */
    private function assertStrictParsing(string $format, array $inputPrefix, array $parse, bool $allowBlankRecords = false): void
    {
        $diagnostic = $this->firstParseDiagnostic($parse, 'delimited-text-empty-first-field-without-delimiter');
        if ($diagnostic !== null) {
            $row = (int) ($diagnostic['sourceLineNumber'] ?? ((int) $diagnostic['row'] + 1));
            $column = (int) ($diagnostic['sourceByteColumnNumber'] ?? 1);
            throw new \InvalidArgumentException("Malformed {$format} input: empty first field at line {$row}, column {$column}; Pandoc requires a delimiter after an empty first field.");
        }

        $blankRows = $allowBlankRecords ? [] : $this->strictRejectedBlankRows($parse);
        if ($blankRows !== []) {
            $row = $blankRows[0] + 1;
            throw new \InvalidArgumentException("Malformed {$format} input: blank record at line {$row}.");
        }

        $metrics = is_array($parse['metrics'] ?? null) ? $parse['metrics'] : [];
        if ((int) ($metrics['textAfterClosingQuoteCount'] ?? 0) > 0) {
            $diagnostic = $this->firstParseDiagnostic($parse, 'delimited-text-text-after-closing-quote');
            $row = $diagnostic === null ? null : ((int) $diagnostic['row'] + 1);
            $column = $diagnostic === null ? null : ((int) $diagnostic['column'] + 1);
            $location = $row === null ? '' : " at line {$row}, column {$column}";
            throw new \InvalidArgumentException("Malformed {$format} input: text after a closing quote{$location}; expected delimiter, line break, or end of input.");
        }

        if ((int) ($metrics['closingQuoteTrailingWhitespaceCount'] ?? 0) > 0) {
            throw new \InvalidArgumentException("Malformed {$format} input: delimiter must immediately follow the quoted field, not trailing whitespace.");
        }

        if ((int) ($metrics['closingQuoteTrailingRecordWhitespaceCount'] ?? 0) > 0) {
            $diagnostic = $this->firstParseDiagnostic($parse, 'delimited-text-closing-quote-trailing-record-whitespace');
            $row = $diagnostic === null ? null : ((int) $diagnostic['row'] + 1);
            $column = $diagnostic === null ? null : ((int) $diagnostic['column'] + 1);
            $location = $row === null ? '' : " at line {$row}, column {$column}";
            throw new \InvalidArgumentException("Malformed {$format} input: whitespace after a closing quote before a line break{$location}; Pandoc only accepts trailing whitespace after the final record.");
        }

        if ((int) ($metrics['escapeBeforeLineBreakCount'] ?? 0) > 0) {
            $diagnostic = $this->firstParseDiagnostic($parse, 'delimited-text-escape-before-linebreak');
            $row = $diagnostic === null ? null : ((int) $diagnostic['row'] + 1);
            $column = $diagnostic === null ? null : ((int) $diagnostic['column'] + 1);
            $location = $row === null ? '' : " at line {$row}, column {$column}";
            throw new \InvalidArgumentException("Malformed {$format} input: escape character before a line break or end of input{$location}; Pandoc escape characters only escape non-line-break characters.");
        }

        if ((int) ($metrics['unclosedQuoteCount'] ?? 0) > 0) {
            $diagnostic = $this->firstParseDiagnostic($parse, 'delimited-text-unclosed-quoted-field');
            $row = $diagnostic === null ? null : ((int) $diagnostic['row'] + 1);
            $column = $diagnostic === null ? null : ((int) $diagnostic['column'] + 1);
            $location = $row === null ? '' : " starting at line {$row}, column {$column}";
            throw new \InvalidArgumentException("Malformed {$format} input: quoted field reaches end of input before a closing quote{$location}.");
        }
    }

    /**
     * @param array{
     *     diagnostics:list<array{code:string, severity:string, message:string, row:int, column:int, offset:int}>
     * } $parse
     * @return array{code:string, severity:string, message:string, row:int, column:int, offset:int}|null
     */
    private function firstParseDiagnostic(array $parse, string $code): ?array
    {
        foreach ($parse['diagnostics'] ?? [] as $diagnostic) {
            if (($diagnostic['code'] ?? null) === $code) {
                return $diagnostic;
            }
        }

        return null;
    }

    /**
     * @param array{
     *     sourceRowIndexes?:list<int>,
     *     blankRows?:list<int>
     * } $parse
     * @return list<int>
     */
    private function strictRejectedBlankRows(array $parse): array
    {
        $blankRows = is_array($parse['blankRows'] ?? null) ? $parse['blankRows'] : [];
        $sourceRowIndexes = is_array($parse['sourceRowIndexes'] ?? null) ? $parse['sourceRowIndexes'] : [];
        $lastSourceRow = $sourceRowIndexes === [] ? -1 : max($sourceRowIndexes);

        return array_values(array_filter(
            $blankRows,
            static fn (int $blankRow): bool => $blankRow <= $lastSourceRow
        ));
    }

    /**
     * @return array{code:string, severity:string, message:string, row:int, column:int, offset:int}
     */
    private function diagnostic(string $code, string $severity, string $message, int $row, int $column, int $offset): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'row' => $row,
            'column' => $column,
            'offset' => $offset,
        ];
    }

    /**
     * @return list<int>
     */
    private function lineStartOffsets(string $text): array
    {
        $starts = [0];
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            if ($char === "\r") {
                if (($text[$offset + 1] ?? '') === "\n") {
                    $offset++;
                }
                $starts[] = $offset + 1;
                continue;
            }

            if ($char === "\n") {
                $starts[] = $offset + 1;
            }
        }

        return $starts;
    }

    /**
     * @param list<int> $lineStarts
     * @return array{sourceLine:int, sourceLineNumber:int, sourceByteColumn:int, sourceByteColumnNumber:int}
     */
    private function sourcePosition(array $lineStarts, int $offset): array
    {
        $offset = max(0, $offset);
        $line = 0;
        $high = count($lineStarts) - 1;
        while ($line < $high) {
            $mid = intdiv($line + $high + 1, 2);
            if ($lineStarts[$mid] <= $offset) {
                $line = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        $column = $offset - $lineStarts[$line];

        return [
            'sourceLine' => $line,
            'sourceLineNumber' => $line + 1,
            'sourceByteColumn' => $column,
            'sourceByteColumnNumber' => $column + 1,
        ];
    }

    /**
     * @param list<int> $lineStarts
     * @return array<string, mixed>
     */
    private function sourceSpan(array $lineStarts, int $startOffset, int $endOffset): array
    {
        $start = $this->sourcePosition($lineStarts, $startOffset);
        $end = $this->sourcePosition($lineStarts, max($startOffset, $endOffset));

        return [
            'sourceStartLine' => $start['sourceLine'],
            'sourceStartLineNumber' => $start['sourceLineNumber'],
            'sourceStartByteColumn' => $start['sourceByteColumn'],
            'sourceStartByteColumnNumber' => $start['sourceByteColumnNumber'],
            'sourceEndLine' => $end['sourceLine'],
            'sourceEndLineNumber' => $end['sourceLineNumber'],
            'sourceEndByteColumn' => $end['sourceByteColumn'],
            'sourceEndByteColumnNumber' => $end['sourceByteColumnNumber'],
            'sourceLocationUnit' => 'byte-column',
            'sourceEndOffsetPolicy' => 'exclusive',
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @param list<int> $lineStarts
     * @return list<array<string, mixed>>
     */
    private function diagnosticsWithSourcePositions(array $diagnostics, array $lineStarts): array
    {
        foreach ($diagnostics as &$diagnostic) {
            if (!is_int($diagnostic['offset'] ?? null)) {
                continue;
            }

            $position = $this->sourcePosition($lineStarts, $diagnostic['offset']);
            $diagnostic['sourceLine'] = $position['sourceLine'];
            $diagnostic['sourceLineNumber'] = $position['sourceLineNumber'];
            $diagnostic['sourceByteColumn'] = $position['sourceByteColumn'];
            $diagnostic['sourceByteColumnNumber'] = $position['sourceByteColumnNumber'];
            $diagnostic['sourceLocationUnit'] = 'byte-column';
        }
        unset($diagnostic);

        return $diagnostics;
    }

    private function skipPostDelimiterWhitespace(string $text, int &$offset, string $delimiter, bool $keepSpace): void
    {
        $index = $offset + strlen($delimiter);
        if ($keepSpace) {
            $offset = $index - 1;
            return;
        }

        $length = strlen($text);
        while ($index < $length && $this->isPostDelimiterWhitespace($text[$index], $delimiter)) {
            $index++;
        }
        $offset = $index - 1;
    }

    private function matchesTokenAt(string $text, int $offset, string $token): bool
    {
        return $offset >= 0 && substr($text, $offset, strlen($token)) === $token;
    }

    private function textCharacterAt(string $text, int $offset): string
    {
        $slice = substr($text, $offset);
        if ($slice !== '' && preg_match('/\A./us', $slice, $matches) === 1) {
            return $matches[0];
        }

        return $text[$offset] ?? '';
    }

    private function isPostDelimiterWhitespace(string $char, string $delimiter): bool
    {
        return $delimiter === "\t" ? $char === ' ' : ($char === ' ' || $char === "\t");
    }

    private function isOnlyTrailingWhitespace(string $text, int $offset): bool
    {
        $length = strlen($text);
        for ($index = $offset; $index < $length; $index++) {
            $char = $text[$index];
            if (!$this->isPandocFinalWhitespace($char)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $row
     * @param list<array<string, mixed>> $fieldMetadata
     */
    private function tableRow(
        array $row,
        bool $header,
        int $columnCount,
        array $fieldMetadata = [],
        string $cellLineBreak = 'linebreak',
        ?CompactDelimitedCellStore $compactCells = null
    ): AstNode
    {
        $cells = [];
        $originalColumnCount = count($row);
        $rowRepair = $originalColumnCount < $columnCount
            ? 'padded'
            : ($originalColumnCount > $columnCount ? 'truncated' : 'unchanged');
        $rowSource = $this->rowSourceFromFieldMetadata($fieldMetadata);
        $compactCells?->beginRow($originalColumnCount, $rowSource);
        for ($column = 0; $column < $columnCount; $column++) {
            $sourceText = $row[$column] ?? '';
            $metadata = is_array($fieldMetadata[$column] ?? null) ? $fieldMetadata[$column] : null;
            $normalizeTrailingLineBreak = $metadata !== null
                && (bool) ($metadata['sourceQuoted'] ?? false)
                && (bool) ($metadata['sourceQuoteClosed'] ?? true);
            $text = $normalizeTrailingLineBreak ? $this->pandocCellText($sourceText) : $sourceText;
            $inlines = $this->cellInlines($text, $cellLineBreak);
            if ($compactCells !== null) {
                $cellIndex = $compactCells->appendCell($metadata, $sourceText, $text);
                $attrs = ['header' => $header, 'text' => $text];
                $resolver = new CompactDelimitedCellAttributes($compactCells, $cellIndex);
            } else {
                $attrs = [
                    'header' => $header,
                    'text' => $text,
                    'sourceColumn' => $column,
                    'originalColumnCount' => $originalColumnCount,
                    'repairedColumnCount' => $columnCount,
                    'rowRepair' => $rowRepair,
                    ...$this->cellSourceProvenance($metadata, $rowSource, $column),
                ];
                if ($sourceText !== $text) {
                    $attrs['sourceText'] = $sourceText;
                }
                $resolver = null;
            }
            $cells[] = new AstNode('table_cell', $attrs, $inlines === [] ? [] : [new AstNode('plain', [], $inlines)], $resolver);
        }

        return new AstNode('table_row', ['header' => $header], $cells);
    }

    private function pandocCellText(string $text): string
    {
        if (!str_ends_with($text, "\n")) {
            return $text;
        }

        return substr($text, 0, -1);
    }

    /**
     * @param list<array<string, mixed>> $fieldMetadata
     * @return array{sourceRow:int|null, sourceRowNumber:int|null}
     */
    private function rowSourceFromFieldMetadata(array $fieldMetadata): array
    {
        foreach ($fieldMetadata as $metadata) {
            if (is_int($metadata['sourceRow'] ?? null) && is_int($metadata['sourceRowNumber'] ?? null)) {
                return [
                    'sourceRow' => $metadata['sourceRow'],
                    'sourceRowNumber' => $metadata['sourceRowNumber'],
                ];
            }
        }

        return ['sourceRow' => null, 'sourceRowNumber' => null];
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @param array{sourceRow:int|null, sourceRowNumber:int|null} $rowSource
     * @return array<string, mixed>
     */
    private function cellSourceProvenance(?array $metadata, array $rowSource, int $column): array
    {
        if ($metadata === null) {
            $provenance = [
                'sourceFieldStatus' => 'padded',
                'sourceColumn' => $column,
            ];
            if ($rowSource['sourceRow'] !== null) {
                $provenance['sourceRow'] = $rowSource['sourceRow'];
                $provenance['sourceRowNumber'] = $rowSource['sourceRowNumber'];
            }

            return $provenance;
        }

        return [
            'sourceFieldStatus' => 'retained',
            'sourceRow' => (int) ($metadata['sourceRow'] ?? 0),
            'sourceRowNumber' => (int) ($metadata['sourceRowNumber'] ?? 1),
            'sourceField' => (int) ($metadata['sourceField'] ?? $column),
            'sourceFieldNumber' => (int) ($metadata['sourceFieldNumber'] ?? $column + 1),
            'sourceStartOffset' => (int) ($metadata['sourceStartOffset'] ?? 0),
            'sourceEndOffset' => (int) ($metadata['sourceEndOffset'] ?? 0),
            'sourceByteRange' => is_array($metadata['sourceByteRange'] ?? null)
                ? array_values($metadata['sourceByteRange'])
                : [0, 0],
            'sourceByteLength' => (int) ($metadata['sourceByteLength'] ?? 0),
            'sourceStartLine' => (int) ($metadata['sourceStartLine'] ?? 0),
            'sourceStartLineNumber' => (int) ($metadata['sourceStartLineNumber'] ?? 1),
            'sourceStartByteColumn' => (int) ($metadata['sourceStartByteColumn'] ?? 0),
            'sourceStartByteColumnNumber' => (int) ($metadata['sourceStartByteColumnNumber'] ?? 1),
            'sourceEndLine' => (int) ($metadata['sourceEndLine'] ?? 0),
            'sourceEndLineNumber' => (int) ($metadata['sourceEndLineNumber'] ?? 1),
            'sourceEndByteColumn' => (int) ($metadata['sourceEndByteColumn'] ?? 0),
            'sourceEndByteColumnNumber' => (int) ($metadata['sourceEndByteColumnNumber'] ?? 1),
            'sourceLocationUnit' => (string) ($metadata['sourceLocationUnit'] ?? 'byte-column'),
            'sourceEndOffsetPolicy' => (string) ($metadata['sourceEndOffsetPolicy'] ?? 'exclusive'),
            'sourceQuoted' => (bool) ($metadata['sourceQuoted'] ?? false),
            'sourceQuoteClosed' => (bool) ($metadata['sourceQuoteClosed'] ?? true),
            'sourceMultiline' => (bool) ($metadata['sourceMultiline'] ?? false),
        ];
    }

    /**
     * @return list<AstNode>
     */
    private function cellInlines(string $text, string $cellLineBreak): array
    {
        if ($text === '') {
            return [];
        }

        $parts = explode("\n", $text);
        if (count($parts) <= 1) {
            if (($parts[0] ?? '') === '') {
                return [];
            }

            return [new AstNode('text', ['text' => $parts[0]])];
        }

        $inlines = [];
        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $inlines[] = new AstNode($cellLineBreak);
            }
            if ($part !== '') {
                $inlines[] = new AstNode('text', ['text' => $part]);
            }
        }

        return $inlines;
    }

    /**
     * @param array{cellLineBreak?:string} $options
     */
    private function cellLineBreakOption(array $options): string
    {
        $value = $options['cellLineBreak'] ?? 'linebreak';
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Delimited text cellLineBreak option must be a string');
        }

        $value = strtolower(trim($value));
        return match ($value) {
            '', 'linebreak' => 'linebreak',
            'softbreak' => 'softbreak',
            default => throw new \InvalidArgumentException('Delimited text cellLineBreak option must be linebreak or softbreak'),
        };
    }

    /**
     * Compact source provenance is the default because writers consume the
     * same AST values while avoiding a PHP hash table for every cell. Callers
     * that inspect `attrs` still receive the fully expanded legacy shape.
     *
     * @param array{compactAst?:bool} $options
     */
    private function compactAstOption(array $options): bool
    {
        if (!array_key_exists('compactAst', $options)) {
            return true;
        }
        if (!is_bool($options['compactAst'])) {
            throw new \InvalidArgumentException('Delimited text compactAst option must be a boolean');
        }

        return $options['compactAst'];
    }

    /**
     * @param array{maxRenderedRows?:int} $options
     */
    private function maxRenderedRowsOption(array $options): int
    {
        if (!array_key_exists('maxRenderedRows', $options)) {
            return self::DEFAULT_MAX_RENDERED_ROWS;
        }
        if (!is_int($options['maxRenderedRows']) || $options['maxRenderedRows'] < 1) {
            throw new \InvalidArgumentException('Delimited text maxRenderedRows option must be a positive integer');
        }

        return $options['maxRenderedRows'];
    }

    /**
     * @param array{maxRenderedCells?:int} $options
     */
    private function maxRenderedCellsOption(array $options): int
    {
        if (!array_key_exists('maxRenderedCells', $options)) {
            return self::DEFAULT_MAX_RENDERED_CELLS;
        }
        if (!is_int($options['maxRenderedCells']) || $options['maxRenderedCells'] < 1) {
            throw new \InvalidArgumentException('Delimited text maxRenderedCells option must be a positive integer');
        }

        return $options['maxRenderedCells'];
    }

    /**
     * @param list<list<string>> $rows
     * @param list<list<array<string, mixed>>> $fieldMetadataRows
     * @param list<int> $sourceRowIndexes
     * @param list<int> $widths
     * @return array{
     *     rows:list<list<string>>,
     *     fieldMetadataRows:list<list<array<string, mixed>>>,
     *     sourceRowIndexes:list<int>,
     *     widths:list<int>,
     *     truncated:bool
     * }
     */
    private function boundedRenderedRows(
        array $rows,
        array $fieldMetadataRows,
        array $sourceRowIndexes,
        array $widths,
        bool $hasHeader,
        int $columnCount,
        int $maxRenderedRows,
        int $maxRenderedCells
    ): array {
        $rowLimit = min($maxRenderedRows, count($rows));
        if ($columnCount > 0) {
            $cellLimitedRows = max(1, intdiv($maxRenderedCells, max(1, $columnCount)));
            if ($hasHeader && count($rows) > 1) {
                $cellLimitedRows = max(2, $cellLimitedRows);
            }
            $rowLimit = min($rowLimit, $cellLimitedRows);
        }

        if ($rowLimit >= count($rows)) {
            return [
                'rows' => $rows,
                'fieldMetadataRows' => $fieldMetadataRows,
                'sourceRowIndexes' => $sourceRowIndexes,
                'widths' => $widths,
                'truncated' => false,
            ];
        }

        return [
            'rows' => array_slice($rows, 0, $rowLimit),
            'fieldMetadataRows' => array_slice($fieldMetadataRows, 0, $rowLimit),
            'sourceRowIndexes' => array_slice($sourceRowIndexes, 0, $rowLimit),
            'widths' => array_slice($widths, 0, $rowLimit),
            'truncated' => true,
        ];
    }

    /**
     * @param array{metrics:array<string, mixed>} $parse
     * @return array<string, mixed>
     */
    private function sourceExtentFromParse(array $parse, int $renderedRowCount, int $renderedFieldCount, bool $renderingTruncated): array
    {
        $metrics = is_array($parse['metrics'] ?? null) ? $parse['metrics'] : [];
        $sourceRowCount = max($renderedRowCount, (int) ($metrics['sourceRowCount'] ?? $renderedRowCount));
        $sourceFieldCount = max($renderedFieldCount, (int) ($metrics['sourceFieldCount'] ?? $renderedFieldCount));
        $storedRowCount = (int) ($metrics['storedRowCount'] ?? $renderedRowCount);
        $omittedRowCount = max(0, $sourceRowCount - $renderedRowCount);

        return [
            'sourceRowCount' => $sourceRowCount,
            'sourceFieldCount' => $sourceFieldCount,
            'sourceMinFieldCount' => (int) ($metrics['sourceMinFieldCount'] ?? 0),
            'sourceMaxFieldCount' => (int) ($metrics['sourceMaxFieldCount'] ?? 0),
            'storedRowCount' => $storedRowCount,
            'renderedRowCount' => $renderedRowCount,
            'renderedFieldCount' => $renderedFieldCount,
            'omittedRowCount' => $omittedRowCount,
            'renderingTruncated' => $renderingTruncated || $omittedRowCount > 0,
            'blankRowCount' => (int) ($metrics['blankRowCount'] ?? 0),
        ];
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool} $dialect
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @param list<int> $sourceRowIndexes
     * @param list<int> $blankRows
     * @param array<string, mixed> $inputPrefix
     * @param array{
     *     finalRecordTerminated:bool,
     *     multilineQuotedFieldCount:int,
     *     multilineQuotedRows:list<int>,
     *     quotedFieldNewlineCount:int,
     *     trailingDelimiterRows:list<int>,
     *     unterminatedQuoteRow:int|null,
     *     partialFinalRecordRow:int|null,
     *     partialFinalRecordFieldCount:int|null
     * } $sourceAnalysis
     * @param list<array{code:string, severity:string, message:string, row:int, column:int, offset:int}> $parseDiagnostics
     * @param array{
     *     quotedFieldCount:int,
     *     doubledQuoteEscapeCount:int,
     *     escapedQuoteSequenceCount:int,
     *     quoteInUnquotedFieldCount:int,
     *     textAfterClosingQuoteCount:int,
     *     closingQuoteTrailingWhitespaceCount:int,
     *     closingQuoteTrailingRecordWhitespaceCount:int,
     *     escapeBeforeLineBreakCount:int,
     *     unclosedQuoteCount:int,
     *     quotedLineBreakCount:int,
     *     multilineFieldCount:int,
     *     partialRecordCount:int,
     *     emptyQuotedFirstFieldWithoutDelimiterCount:int
     * } $parseMetrics
     * @param array<string, mixed> $controlCharacters
     * @param array<string, mixed> $sourceExtent
     * @return array<string, mixed>
     */
    private function reviewPacket(
        string $format,
        array $dialect,
        array $rows,
        array $widths,
        array $sourceRowIndexes,
        array $blankRows,
        bool $hasHeader,
        int $repairedColumnCount,
        array $formatInference,
        array $inputPrefix,
        array $sourceAnalysis,
        array $parseDiagnostics,
        array $parseMetrics,
        array $controlCharacters,
        array $sourceExtent
    ): array
    {
        $renderedRowCount = count($rows);
        $rowCount = (int) ($sourceExtent['sourceRowCount'] ?? $renderedRowCount);
        $sourceColumnCount = max((int) ($sourceExtent['sourceMaxFieldCount'] ?? 0), $widths === [] ? 0 : max($widths));
        $columnCount = max(0, $repairedColumnCount);
        $raggedRows = [];
        foreach ($widths as $index => $width) {
            if ($width !== $columnCount) {
                $raggedRows[] = $index;
            }
        }
        $rowWidthSummary = $this->rowWidthSummary($rows, $widths, $sourceRowIndexes, $blankRows, $hasHeader, $columnCount);
        $rowRepairSummary = $this->rowRepairSummary($rows, $widths, $sourceRowIndexes, $blankRows, $hasHeader, $columnCount);
        $controlCharacters = $this->annotateControlCharactersWithRepair($controlCharacters, $rowRepairSummary);
        $diagnostics = $this->reviewDiagnostics($hasHeader, $formatInference, $inputPrefix, $sourceAnalysis, $rowWidthSummary, $controlCharacters);
        foreach ($parseDiagnostics as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }
        if (($sourceExtent['renderingTruncated'] ?? false) === true) {
            $diagnostics[] = [
                'code' => 'delimited-text-rendered-table-truncated',
                'severity' => 'info',
                'message' => 'The source table was larger than the bounded rendered-table preview; later rows were omitted from the document tree to avoid exhausting memory.',
                'sourceRowCount' => $rowCount,
                'renderedRowCount' => (int) ($sourceExtent['renderedRowCount'] ?? $renderedRowCount),
                'omittedRowCount' => (int) ($sourceExtent['omittedRowCount'] ?? 0),
            ];
        }

        return [
            'format' => $format,
            'delimiter' => $dialect['delimiter'] === "\t" ? 'tab' : $dialect['delimiter'],
            'delimiterName' => $dialect['delimiterName'],
            'quote' => $dialect['quote'],
            'escape' => $dialect['escape'],
            'dialect' => [
                'delimiter' => $dialect['delimiter'] === "\t" ? 'tab' : $dialect['delimiter'],
                'delimiterName' => $dialect['delimiterName'],
                'quote' => $dialect['quote'],
                'escape' => $dialect['escape'],
                'keepSpace' => $dialect['keepSpace'],
                'quoteMode' => $dialect['quote'] === null ? 'literal' : 'quoted-fields',
                'escapeMode' => $dialect['escape'] === null ? 'none' : 'escape-character',
            ],
            'formatInference' => $formatInference,
            'inputPrefix' => $inputPrefix,
            'headerRow' => $hasHeader && $rowCount > 0,
            'headerOption' => $hasHeader ? 'first-row' : 'none',
            'headerSource' => $hasHeader && $rowCount > 0 ? 'source-row-0' : 'generated',
            'rowCount' => $rowCount,
            'bodyRowCount' => $hasHeader ? max(0, $rowCount - 1) : $rowCount,
            'renderedRowCount' => (int) ($sourceExtent['renderedRowCount'] ?? $renderedRowCount),
            'renderedBodyRowCount' => $hasHeader
                ? max(0, (int) ($sourceExtent['renderedRowCount'] ?? $renderedRowCount) - 1)
                : (int) ($sourceExtent['renderedRowCount'] ?? $renderedRowCount),
            'omittedRowCount' => (int) ($sourceExtent['omittedRowCount'] ?? 0),
            'renderingTruncated' => (bool) ($sourceExtent['renderingTruncated'] ?? false),
            'columnCount' => $columnCount,
            'sourceMaxFieldCount' => $sourceColumnCount,
            'columnNames' => $hasHeader && $rows !== []
                ? $this->normalizedRow($rows[0], $columnCount)
                : $this->generatedColumnLabels($columnCount),
            'fieldCount' => (int) ($sourceExtent['sourceFieldCount'] ?? array_sum($widths)),
            'renderedFieldCount' => (int) ($sourceExtent['renderedFieldCount'] ?? array_sum($widths)),
            'minFieldCount' => (int) ($sourceExtent['sourceMinFieldCount'] ?? ($widths === [] ? 0 : min($widths))),
            'maxFieldCount' => $sourceColumnCount,
            'blankRowCount' => (int) ($sourceExtent['blankRowCount'] ?? count($blankRows)),
            'blankRows' => $blankRows,
            'raggedRowCount' => count($raggedRows),
            'raggedRows' => $raggedRows,
            'finalRecordTerminated' => $sourceAnalysis['finalRecordTerminated'],
            'multilineQuotedFieldCount' => $sourceAnalysis['multilineQuotedFieldCount'],
            'multilineQuotedRows' => $sourceAnalysis['multilineQuotedRows'],
            'quotedFieldNewlineCount' => $sourceAnalysis['quotedFieldNewlineCount'],
            'trailingDelimiterRowCount' => count($sourceAnalysis['trailingDelimiterRows']),
            'trailingDelimiterRows' => $sourceAnalysis['trailingDelimiterRows'],
            'unterminatedQuoteAtEof' => $sourceAnalysis['unterminatedQuoteRow'] !== null,
            'unterminatedQuoteRow' => $sourceAnalysis['unterminatedQuoteRow'],
            'partialFinalRecord' => $sourceAnalysis['partialFinalRecordRow'] !== null,
            'partialFinalRecordRow' => $sourceAnalysis['partialFinalRecordRow'],
            'partialFinalRecordFieldCount' => $sourceAnalysis['partialFinalRecordFieldCount'],
            'quotedFieldCount' => $parseMetrics['quotedFieldCount'],
            'doubledQuoteEscapeCount' => $parseMetrics['doubledQuoteEscapeCount'],
            'escapedQuoteSequenceCount' => $parseMetrics['escapedQuoteSequenceCount'],
            'quoteInUnquotedFieldCount' => $parseMetrics['quoteInUnquotedFieldCount'],
            'textAfterClosingQuoteCount' => $parseMetrics['textAfterClosingQuoteCount'],
            'closingQuoteTrailingWhitespaceCount' => $parseMetrics['closingQuoteTrailingWhitespaceCount'] ?? 0,
            'closingQuoteTrailingRecordWhitespaceCount' => $parseMetrics['closingQuoteTrailingRecordWhitespaceCount'] ?? 0,
            'escapeBeforeLineBreakCount' => $parseMetrics['escapeBeforeLineBreakCount'] ?? 0,
            'unclosedQuoteCount' => $parseMetrics['unclosedQuoteCount'],
            'quotedLineBreakCount' => $parseMetrics['quotedLineBreakCount'],
            'multilineFieldCount' => $parseMetrics['multilineFieldCount'],
            'partialRecordCount' => $parseMetrics['partialRecordCount'],
            'emptyQuotedFirstFieldWithoutDelimiterCount' => $parseMetrics['emptyQuotedFirstFieldWithoutDelimiterCount'] ?? 0,
            'rowWidthSummary' => $rowWidthSummary,
            'rowRepairSummary' => $rowRepairSummary,
            'controlCharacters' => $controlCharacters,
            'controlRepairSummary' => $controlCharacters['repairSummary'],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'upstreamEvidence' => $this->upstreamEvidencePacket($format),
        ];
    }

    /**
     * @return array{
     *     denominator:int,
     *     denominatorScope:string,
     *     fixtures:list<string>,
     *     source:string,
     *     reader:string,
     *     selectedDirectFixtureFormat:string,
     *     directFixtureDenominator:int,
     *     directFixtureCount:int,
     *     directFixtures:list<string>,
     *     csvDirectFixtureDenominator:int,
     *     tsvDirectFixtureDenominator:int,
     *     parserOptionFixtureCount:int,
     *     parserOptionFixtures:list<string>,
     *     integrationFixtureCount:int,
     *     integrationFixtures:list<string>,
     *     adjacentFixtureEvidence:array<string, mixed>,
     *     staticCurrentEvidence:array<string, mixed>,
     *     generatedNativeParitySampleCount:int,
     *     generatedNativeParityEvidence:array<string, mixed>,
     *     runnerEvidence:array<string, mixed>,
     *     notRunEvidence:list<array{scope:string, runner:string, status:string, executed:bool, reason:string}>,
     *     closedGaps:list<string>,
     *     openGaps:list<string>,
     *     claimBoundaries:list<string>
     * }
     */
    private function upstreamEvidencePacket(string $format): array
    {
        $csvCommandFixtures = [
            'test/command/csv.md',
            'test/command/01.csv',
            'test/command/9797.md',
        ];
        $tsvCommandFixtures = DelimitedTextUpstreamReaderEvidence::tsvDirectFixturePaths();
        $rstCsvAdjacentEvidence = DelimitedTextUpstreamReaderEvidence::csvAdjacentRstFixtureEvidence();
        $rstCsvFixtures = array_values(array_map(
            static fn (array $fixture): string => (string) $fixture['path'],
            is_array($rstCsvAdjacentEvidence['fixtures'] ?? null) ? $rstCsvAdjacentEvidence['fixtures'] : []
        ));
        $parserOptionFixtures = DelimitedTextUpstreamReaderEvidence::csvParserOptionFixtureNames();
        $staticCurrentEvidence = DelimitedTextUpstreamReaderEvidence::checkedInCurrentEvidence(dirname(__DIR__, 3));
        $generatedCsvNativeStaticEvidence = is_array($staticCurrentEvidence['generatedCsvNativeStaticEvidence'] ?? null)
            ? $staticCurrentEvidence['generatedCsvNativeStaticEvidence']
            : [];
        $generatedTsvNativeStaticEvidence = is_array($staticCurrentEvidence['generatedTsvNativeStaticEvidence'] ?? null)
            ? $staticCurrentEvidence['generatedTsvNativeStaticEvidence']
            : [];
        $runnerEvidence = DelimitedTextUpstreamReaderEvidence::runnerNotRunEvidence();
        $notRunEvidence = [
            [
                'scope' => 'upstream-haskell-runner',
                'runner' => $runnerEvidence['runner'],
                'status' => $runnerEvidence['status'],
                'executed' => $runnerEvidence['executed'],
                'reason' => $runnerEvidence['reason'],
            ],
        ];

        if ($format === 'tsv') {
            return [
                'denominator' => count($tsvCommandFixtures),
                'denominatorScope' => 'direct-reader-fixtures',
                'fixtures' => $tsvCommandFixtures,
                'source' => 'Pandoc 4f5226df4faa0d66dd2c089465b13886360ab3c2 src/Text/Pandoc/CSV.hs and src/Text/Pandoc/Readers/CSV.hs',
                'reader' => 'tsv',
                'selectedDirectFixtureFormat' => 'tsv',
                'directFixtureDenominator' => count($tsvCommandFixtures),
                'directFixtureCount' => count($tsvCommandFixtures),
                'directFixtures' => $tsvCommandFixtures,
                'csvDirectFixtureDenominator' => count($csvCommandFixtures),
                'tsvDirectFixtureDenominator' => count($tsvCommandFixtures),
                'parserOptionFixtureCount' => 0,
                'parserOptionFixtures' => [],
                'integrationFixtureCount' => 0,
                'integrationFixtures' => [],
                'adjacentFixtureEvidence' => [],
                'staticCurrentEvidence' => $staticCurrentEvidence,
                'generatedNativeParitySampleCount' => DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT,
                'generatedNativeParityEvidence' => $generatedTsvNativeStaticEvidence,
                'runnerEvidence' => $runnerEvidence,
                'notRunEvidence' => $notRunEvidence,
                'closedGaps' => [
                    'tsv-tab-delimiter-reader',
                    'tsv-literal-quote-policy',
                    'tsv-trailing-empty-field-preservation',
                    'tsv-row-repair-and-control-character-provenance',
                    'direct-tsv-command-reader',
                    'generated-tsv-native-parity-sample',
                ],
                'openGaps' => [
                    'tsv-gfm-pipe-table-writer-output-not-claimed',
                    'upstream-runner-not-run',
                ],
                'claimBoundaries' => [
                    'TSV direct command evidence includes test/command/8661.md from the pinned upstream corpus.',
                    'The 8661.md GFM pipe-table output is tracked as upstream command context, but this packet only asserts local TSV reader AST semantics.',
                    'Generated TSV-to-native sample evidence is local executable evidence and is not counted as an upstream TSV fixture.',
                    'This packet does not claim upstream Haskell runner parity.',
                ],
            ];
        }

        return [
            'denominator' => count($csvCommandFixtures),
            'denominatorScope' => 'direct-reader-fixtures',
            'fixtures' => $csvCommandFixtures,
            'source' => 'Pandoc 4f5226df4faa0d66dd2c089465b13886360ab3c2 src/Text/Pandoc/CSV.hs and src/Text/Pandoc/Readers/CSV.hs',
            'reader' => 'csv',
            'selectedDirectFixtureFormat' => 'csv',
            'directFixtureDenominator' => count($csvCommandFixtures),
            'directFixtureCount' => count($csvCommandFixtures),
            'directFixtures' => $csvCommandFixtures,
            'csvDirectFixtureDenominator' => count($csvCommandFixtures),
            'tsvDirectFixtureDenominator' => count($tsvCommandFixtures),
            'parserOptionFixtureCount' => count($parserOptionFixtures),
            'parserOptionFixtures' => $parserOptionFixtures,
            'integrationFixtureCount' => count($rstCsvFixtures),
            'integrationFixtures' => $rstCsvFixtures,
            'adjacentFixtureEvidence' => $rstCsvAdjacentEvidence,
            'staticCurrentEvidence' => $staticCurrentEvidence,
            'generatedNativeParitySampleCount' => DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT,
            'generatedNativeParityEvidence' => $generatedCsvNativeStaticEvidence,
            'runnerEvidence' => $runnerEvidence,
            'notRunEvidence' => $notRunEvidence,
            'closedGaps' => [
                'direct-csv-command-reader',
                'shared-csv-parser-option-fixtures',
                'csv-closing-quote-record-whitespace-strictness',
                'csv-row-repair-and-control-character-provenance',
                'rst-csv-table-integration-via-rst-reader',
                'generated-csv-native-parity-sample',
            ],
            'openGaps' => [
                'upstream-runner-not-run',
            ],
            'claimBoundaries' => [
                'The direct CSV command reader fixture and parser option behavior are covered locally.',
                'Generated CSV-to-native sample evidence is local executable evidence and is not counted as an upstream CSV direct fixture.',
                'The two RST csv-table files are tracked as upstream CSV-adjacent evidence and exercised through the RST reader without changing the direct CSV denominator.',
                'This packet does not claim upstream Haskell runner parity.',
            ],
        ];
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool} $dialect
     * @param list<int> $widths
     * @return array{
     *     finalRecordTerminated:bool,
     *     multilineQuotedFieldCount:int,
     *     multilineQuotedRows:list<int>,
     *     quotedFieldNewlineCount:int,
     *     trailingDelimiterRows:list<int>,
     *     unterminatedQuoteRow:int|null,
     *     partialFinalRecordRow:int|null,
     *     partialFinalRecordFieldCount:int|null
     * }
     */
    private function sourceAnalysis(string $text, array $dialect, array $widths): array
    {
        if ($this->pandocWhitespaceOnlyDocumentBlankRows($text) !== null) {
            return [
                'finalRecordTerminated' => true,
                'multilineQuotedFieldCount' => 0,
                'multilineQuotedRows' => [],
                'quotedFieldNewlineCount' => 0,
                'trailingDelimiterRows' => [],
                'unterminatedQuoteRow' => null,
                'partialFinalRecordRow' => null,
                'partialFinalRecordFieldCount' => null,
            ];
        }

        $delimiter = $dialect['delimiter'];
        $quote = $dialect['quote'];
        $escape = $dialect['escape'];
        $keepSpace = $dialect['keepSpace'];
        $length = strlen($text);
        $recordIndex = 0;
        $recordHasContent = false;
        $atFieldStart = true;
        $inQuotes = false;
        $currentQuotedFieldHasNewline = false;
        $multilineQuotedFieldCount = 0;
        $quotedFieldNewlineCount = 0;
        $multilineQuotedRows = [];
        $trailingDelimiterRows = [];

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            $next = $offset + 1 < $length ? $text[$offset + 1] : null;

            if ($inQuotes) {
                if ($escape !== null && $this->matchesTokenAt($text, $offset, $escape)) {
                    $nextOffset = $offset + strlen($escape);
                    if ($nextOffset < $length && !$this->isLineBreak($text[$nextOffset])) {
                        $offset = $nextOffset + strlen($this->textCharacterAt($text, $nextOffset)) - 1;
                    }
                    continue;
                }

                if ($quote !== null && $this->matchesTokenAt($text, $offset, $quote)) {
                    $nextQuoteOffset = $offset + strlen($quote);
                    if ($escape === null && $this->matchesTokenAt($text, $nextQuoteOffset, $quote)) {
                        $offset = $nextQuoteOffset + strlen($quote) - 1;
                        continue;
                    }

                    if ($currentQuotedFieldHasNewline) {
                        $multilineQuotedFieldCount++;
                        $currentQuotedFieldHasNewline = false;
                    }
                    $inQuotes = false;
                    $atFieldStart = false;
                    $offset += strlen($quote) - 1;
                    continue;
                }

                if ($this->isLineBreak($char)) {
                    $quotedFieldNewlineCount++;
                    $currentQuotedFieldHasNewline = true;
                    $multilineQuotedRows[$recordIndex] = true;
                    if ($char === "\r" && $next === "\n") {
                        $offset++;
                    }
                }

                continue;
            }

            if ($this->isLineBreak($char)) {
                if ($recordHasContent) {
                    $recordIndex++;
                }

                $recordHasContent = false;
                $atFieldStart = true;
                if ($char === "\r" && $next === "\n") {
                    $offset++;
                }
                continue;
            }

            $recordHasContent = true;

            if ($this->matchesTokenAt($text, $offset, $delimiter)) {
                $nextOffset = $offset + strlen($delimiter);
                if ($nextOffset >= $length || $this->isLineBreak($text[$nextOffset])) {
                    $trailingDelimiterRows[$recordIndex] = true;
                }
                $atFieldStart = true;
                $this->skipPostDelimiterWhitespace($text, $offset, $delimiter, $keepSpace);
                continue;
            }

            if ($quote !== null && $atFieldStart && $this->matchesTokenAt($text, $offset, $quote)) {
                $inQuotes = true;
                $currentQuotedFieldHasNewline = false;
                $offset += strlen($quote) - 1;
                continue;
            }

            $atFieldStart = false;
        }

        $finalRecordTerminated = $text === '' || $this->endsWithLineBreak($text);
        $unterminatedQuoteRow = $inQuotes && $recordHasContent ? $recordIndex : null;
        if ($inQuotes && $currentQuotedFieldHasNewline) {
            $multilineQuotedFieldCount++;
        }
        $partialFinalRecordRow = null;
        $partialFinalRecordFieldCount = null;
        if (!$finalRecordTerminated && $widths !== []) {
            $lastRow = count($widths) - 1;
            $columnCount = max($widths);
            if ($widths[$lastRow] < $columnCount) {
                $partialFinalRecordRow = $lastRow;
                $partialFinalRecordFieldCount = $widths[$lastRow];
            }
        }

        return [
            'finalRecordTerminated' => $finalRecordTerminated,
            'multilineQuotedFieldCount' => $multilineQuotedFieldCount,
            'multilineQuotedRows' => array_map('intval', array_keys($multilineQuotedRows)),
            'quotedFieldNewlineCount' => $quotedFieldNewlineCount,
            'trailingDelimiterRows' => array_map('intval', array_keys($trailingDelimiterRows)),
            'unterminatedQuoteRow' => $unterminatedQuoteRow,
            'partialFinalRecordRow' => $partialFinalRecordRow,
            'partialFinalRecordFieldCount' => $partialFinalRecordFieldCount,
        ];
    }

    /**
     * @return list<int>|null
     */
    private function pandocWhitespaceOnlyDocumentBlankRows(string $text): ?array
    {
        $length = strlen($text);
        if ($length === 0 || !$this->isLineBreak($text[0])) {
            return null;
        }

        $blankRows = [];
        $rowIndex = 0;
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            if ($this->isPandocNonLineBreakFinalWhitespace($char)) {
                continue;
            }

            if ($char === "\r") {
                $blankRows[] = $rowIndex++;
                if (($text[$offset + 1] ?? '') === "\n") {
                    $offset++;
                }
                continue;
            }

            if ($char === "\n") {
                $blankRows[] = $rowIndex++;
                continue;
            }

            return null;
        }

        return $blankRows === [] ? null : $blankRows;
    }

    private function isPandocFinalWhitespace(string $char): bool
    {
        return $this->isLineBreak($char) || $this->isPandocNonLineBreakFinalWhitespace($char);
    }

    private function isPandocNonLineBreakFinalWhitespace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\v" || $char === "\f";
    }

    /**
     * @param array<string, mixed> $formatInference
     * @param array<string, mixed> $inputPrefix
     * @param array{
     *     multilineQuotedRows:list<int>,
     *     trailingDelimiterRows:list<int>,
     *     unterminatedQuoteRow:int|null,
     *     partialFinalRecordRow:int|null,
     *     partialFinalRecordFieldCount:int|null
     * } $sourceAnalysis
     * @param array<string, mixed> $rowWidthSummary
     * @param array<string, mixed> $controlCharacters
     * @return list<array<string, mixed>>
     */
    private function reviewDiagnostics(
        bool $hasHeader,
        array $formatInference,
        array $inputPrefix,
        array $sourceAnalysis,
        array $rowWidthSummary,
        array $controlCharacters
    ): array
    {
        $diagnostics = [];
        if (!$hasHeader) {
            $diagnostics[] = [
                'code' => 'delimited-text-header-disabled',
                'severity' => 'info',
                'message' => 'No source header row was consumed; generated column labels are review metadata only.',
            ];
        }

        $source = $formatInference['source'] ?? 'explicit';
        if ($source !== 'explicit') {
            $selectedFormat = (string) ($formatInference['selectedFormat'] ?? 'csv');
            $diagnostics[] = [
                'code' => $source === 'default' ? 'delimited-text-format-defaulted' : 'delimited-text-format-inferred',
                'severity' => 'info',
                'message' => "Delimited text format resolved to {$selectedFormat} from {$source} evidence.",
            ];
        }

        foreach ($this->inputPrefixDiagnostics($inputPrefix) as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }

        if ($sourceAnalysis['multilineQuotedRows'] !== []) {
            $diagnostics[] = [
                'code' => 'delimited-text-multiline-quoted-field',
                'severity' => 'info',
                'message' => 'One or more quoted fields contain source line breaks and were read as logical records.',
                'rows' => $sourceAnalysis['multilineQuotedRows'],
            ];
        }

        if ($sourceAnalysis['trailingDelimiterRows'] !== []) {
            $diagnostics[] = [
                'code' => 'delimited-text-trailing-delimiter-empty-field',
                'severity' => 'info',
                'message' => 'One or more records end with a delimiter, producing an explicit empty final field.',
                'rows' => $sourceAnalysis['trailingDelimiterRows'],
            ];
        }

        if ($sourceAnalysis['unterminatedQuoteRow'] !== null) {
            $diagnostics[] = [
                'code' => 'delimited-text-unterminated-quote-eof',
                'severity' => 'warning',
                'message' => 'A quoted field reaches EOF before a closing quote; remaining source text is kept in that field.',
                'row' => $sourceAnalysis['unterminatedQuoteRow'],
            ];
        }

        if ($sourceAnalysis['partialFinalRecordRow'] !== null) {
            $diagnostics[] = [
                'code' => 'delimited-text-partial-final-record',
                'severity' => 'warning',
                'message' => 'The final unterminated source record has fewer fields than the widest parsed record and was padded in the table AST.',
                'row' => $sourceAnalysis['partialFinalRecordRow'],
                'fieldCount' => $sourceAnalysis['partialFinalRecordFieldCount'] ?? 0,
            ];
        }

        if (($rowWidthSummary['blankRowCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-blank-rows-skipped',
                'severity' => 'info',
                'message' => 'Blank source rows were skipped before table construction and preserved in review metadata.',
                'blankRowCount' => $rowWidthSummary['blankRowCount'],
                'rows' => $rowWidthSummary['blankRows'] ?? [],
            ];
        }

        if (($rowWidthSummary['trailingEmptyFieldRows'] ?? []) !== []) {
            $diagnostics[] = [
                'code' => 'delimited-text-trailing-empty-fields-preserved',
                'severity' => 'info',
                'message' => 'Rows with trailing empty fields were preserved before table normalization.',
                'rows' => $rowWidthSummary['trailingEmptyFieldRows'],
            ];
        }

        if (($rowWidthSummary['strict']['mismatchCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-strict-row-width-mismatch',
                'severity' => 'warning',
                'message' => 'Strict row-width policy would reject rows whose field counts differ from the first/header row.',
                'policy' => $rowWidthSummary['strict']['policy'] ?? 'first-row',
                'expectedColumnCount' => $rowWidthSummary['strict']['expectedColumnCount'] ?? 0,
                'mismatchCount' => $rowWidthSummary['strict']['mismatchCount'] ?? 0,
                'mismatches' => $rowWidthSummary['strict']['mismatches'] ?? [],
            ];
        }

        if (($rowWidthSummary['relaxed']['consistent'] ?? true) === false) {
            $diagnostics[] = [
                'code' => 'delimited-text-row-widths-uneven',
                'severity' => 'warning',
                'message' => 'Delimited text rows have uneven field counts; rows were normalized to the first source row width in the native table.',
                'policy' => $rowWidthSummary['relaxed']['policy'] ?? 'pandoc-first-row-width',
                'columnCount' => $rowWidthSummary['relaxed']['columnCount'] ?? 0,
                'changedRowCount' => $rowWidthSummary['relaxed']['changedRowCount'] ?? 0,
                'paddedRowCount' => $rowWidthSummary['relaxed']['paddedRowCount'] ?? 0,
                'truncatedRowCount' => $rowWidthSummary['relaxed']['truncatedRowCount'] ?? 0,
                'paddedRows' => $rowWidthSummary['relaxed']['paddedRows'] ?? [],
                'truncatedRows' => $rowWidthSummary['relaxed']['truncatedRows'] ?? [],
            ];
        }

        if (($rowWidthSummary['header']['mismatchCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-header-width-mismatch',
                'severity' => 'warning',
                'message' => 'Header and body row field counts differ; column names were normalized to the native table width.',
                'headerColumnCount' => $rowWidthSummary['header']['headerColumnCount'] ?? 0,
                'dataColumnCounts' => $rowWidthSummary['header']['dataColumnCounts'] ?? [],
                'mismatchCount' => $rowWidthSummary['header']['mismatchCount'] ?? 0,
                'mismatches' => $rowWidthSummary['header']['mismatches'] ?? [],
            ];
        }

        $controlCount = $controlCharacters['totalCount'] ?? 0;
        if (is_int($controlCount) && $controlCount > 0) {
            $diagnostic = [
                'code' => 'delimited-text-control-characters',
                'severity' => 'warning',
                'message' => "Delimited text input contains {$controlCount} non-structural control character(s) in fields; byte samples are bounded for review.",
                'controlCount' => $controlCount,
                'nulCount' => $controlCharacters['nulCount'] ?? 0,
                'sampleCount' => $controlCharacters['sampleCount'] ?? 0,
                'sampleLimit' => $controlCharacters['sampleLimit'] ?? self::CONTROL_CHARACTER_SAMPLE_LIMIT,
                'controlsInChangedRows' => $controlCharacters['repairSummary']['controlsInChangedRows'] ?? 0,
            ];
            if (($controlCharacters['sourcePath'] ?? null) !== null) {
                $diagnostic['sourcePath'] = $controlCharacters['sourcePath'];
            }

            $diagnostics[] = $diagnostic;
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function inputPrefixReview(string $text): array
    {
        $inputByteCount = strlen($text);
        $bomByteCount = str_starts_with($text, "\xEF\xBB\xBF") ? 3 : 0;
        $leadingWhitespaceByteCount = $this->leadingWhitespaceLinePrefixByteCount(substr($text, $bomByteCount));
        $firstContentOffset = $bomByteCount + $leadingWhitespaceByteCount;
        $inspectedByteCount = min($inputByteCount, self::INPUT_PREFIX_BYTE_LIMIT);
        $previewByteCount = min($inputByteCount, self::INPUT_PREFIX_PREVIEW_BYTE_LIMIT);
        $controlReview = $this->inputPrefixControlReview(substr($text, 0, $inspectedByteCount));

        return [
            'policy' => 'bounded-input-prefix-review',
            'encoding' => 'utf-8',
            'bom' => $bomByteCount > 0 ? 'utf-8' : 'none',
            'bomByteCount' => $bomByteCount,
            'leadingWhitespaceByteCount' => $leadingWhitespaceByteCount,
            'leadingWhitespaceLineCount' => $this->lineBreakCount(substr($text, $bomByteCount, $leadingWhitespaceByteCount)),
            'firstContentOffset' => $firstContentOffset,
            'firstContentLine' => $this->lineBreakCount(substr($text, 0, $firstContentOffset)) + 1,
            'inputByteCount' => $inputByteCount,
            'inspectionByteLimit' => self::INPUT_PREFIX_BYTE_LIMIT,
            'inspectedByteCount' => $inspectedByteCount,
            'inspectionTruncated' => $inputByteCount > self::INPUT_PREFIX_BYTE_LIMIT,
            'prefixPreviewByteLimit' => self::INPUT_PREFIX_PREVIEW_BYTE_LIMIT,
            'prefixPreviewByteCount' => $previewByteCount,
            'prefixPreviewHex' => bin2hex(substr($text, 0, $previewByteCount)),
            'sampleLimit' => self::CONTROL_CHARACTER_SAMPLE_LIMIT,
            'nullByteCount' => $controlReview['nullByteCount'],
            'nullBytes' => $controlReview['nullBytes'],
            'controlCharacterCount' => $controlReview['controlCharacterCount'],
            'controlCharacters' => $controlReview['controlCharacters'],
        ];
    }

    /**
     * @param array<string, mixed> $inputPrefix
     */
    private function inputTextAfterSupportedPrefix(string $text, array $inputPrefix): string
    {
        $firstContentOffset = $inputPrefix['firstContentOffset'] ?? 0;
        if (!is_int($firstContentOffset) || $firstContentOffset <= 0) {
            return $text;
        }

        if ($firstContentOffset >= strlen($text)) {
            return $this->pandocWhitespaceOnlyDocumentBlankRows($text) === null ? $text : '';
        }

        return substr($text, $firstContentOffset);
    }

    /**
     * @param array<string, mixed> $inputPrefix
     */
    private function inputTextAfterBomPrefix(string $text, array $inputPrefix): string
    {
        $bomByteCount = $inputPrefix['bomByteCount'] ?? 0;
        if (!is_int($bomByteCount) || $bomByteCount <= 0) {
            return $text;
        }

        return substr($text, $bomByteCount);
    }

    /**
     * @param array<string, mixed> $inputPrefix
     */
    private function pandocSourceText(string $text, array &$inputPrefix): string
    {
        $carriageReturnCount = substr_count($text, "\r");
        $inputPrefix['carriageReturnNormalization'] = [
            'policy' => 'pandoc-sources-remove-carriage-returns',
            'removedCount' => $carriageReturnCount,
        ];

        return $carriageReturnCount === 0 ? $text : str_replace("\r", '', $text);
    }

    private function leadingWhitespaceLinePrefixByteCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (preg_match('/\A(?:[ \t]*(?:\r\n|\r|\n))+[ \t]*/', $text, $matches) !== 1) {
            return 0;
        }

        return strlen($matches[0]);
    }

    private function lineBreakCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        preg_match_all('/\r\n|\r|\n/', $text, $matches);

        return count($matches[0]);
    }

    /**
     * @return array{nullByteCount:int, nullBytes:list<array{offset:int, hex:string, name:string}>, controlCharacterCount:int, controlCharacters:list<array{offset:int, hex:string, name:string}>}
     */
    private function inputPrefixControlReview(string $text): array
    {
        $nullByteCount = 0;
        $nullBytes = [];
        $controlCharacterCount = 0;
        $controlCharacters = [];
        $length = strlen($text);

        for ($offset = 0; $offset < $length; $offset++) {
            $ordinal = ord($text[$offset]);
            if ($ordinal === 0) {
                $nullByteCount++;
                if (count($nullBytes) < self::CONTROL_CHARACTER_SAMPLE_LIMIT) {
                    $nullBytes[] = [
                        'offset' => $offset,
                        'hex' => '00',
                        'name' => 'NUL',
                    ];
                }
                continue;
            }

            if (!$this->isReviewableControlByte($ordinal)) {
                continue;
            }

            $controlCharacterCount++;
            if (count($controlCharacters) < self::CONTROL_CHARACTER_SAMPLE_LIMIT) {
                $controlCharacters[] = [
                    'offset' => $offset,
                    'hex' => sprintf('%02X', $ordinal),
                    'name' => $this->controlByteName($ordinal),
                ];
            }
        }

        return [
            'nullByteCount' => $nullByteCount,
            'nullBytes' => $nullBytes,
            'controlCharacterCount' => $controlCharacterCount,
            'controlCharacters' => $controlCharacters,
        ];
    }

    /**
     * @param array<string, mixed> $inputPrefix
     * @return list<array<string, mixed>>
     */
    private function inputPrefixDiagnostics(array $inputPrefix): array
    {
        $diagnostics = [];
        if (($inputPrefix['bom'] ?? 'none') === 'utf-8') {
            $diagnostics[] = [
                'code' => 'delimited-text-input-prefix-utf8-bom',
                'severity' => 'info',
                'message' => 'UTF-8 byte-order mark was detected and skipped before parsing the first row.',
                'byteCount' => $inputPrefix['bomByteCount'] ?? 0,
            ];
        }

        if (($inputPrefix['leadingWhitespaceByteCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-input-prefix-leading-whitespace',
                'severity' => 'info',
                'message' => 'Leading whitespace-only records were detected in the input prefix; recovery-mode parsing starts at the first non-whitespace record when one exists.',
                'byteCount' => $inputPrefix['leadingWhitespaceByteCount'],
                'lineCount' => $inputPrefix['leadingWhitespaceLineCount'] ?? 0,
                'firstContentLine' => $inputPrefix['firstContentLine'] ?? 1,
            ];
        }

        if (($inputPrefix['nullByteCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-input-prefix-null-byte',
                'severity' => 'warning',
                'message' => 'NUL bytes were detected in the bounded input-prefix inspection window.',
                'count' => $inputPrefix['nullByteCount'],
                'sampleCount' => count($inputPrefix['nullBytes'] ?? []),
                'sampleLimit' => $inputPrefix['sampleLimit'] ?? self::CONTROL_CHARACTER_SAMPLE_LIMIT,
            ];
        }

        if (($inputPrefix['controlCharacterCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-input-prefix-control-character',
                'severity' => 'warning',
                'message' => 'Non-whitespace control characters were detected in the bounded input-prefix inspection window.',
                'count' => $inputPrefix['controlCharacterCount'],
                'sampleCount' => count($inputPrefix['controlCharacters'] ?? []),
                'sampleLimit' => $inputPrefix['sampleLimit'] ?? self::CONTROL_CHARACTER_SAMPLE_LIMIT,
            ];
        }

        return $diagnostics;
    }

    /**
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @param list<int> $sourceRowIndexes
     * @param list<int> $blankRows
     * @return array<string, mixed>
     */
    private function rowWidthSummary(array $rows, array $widths, array $sourceRowIndexes, array $blankRows, bool $hasHeader, int $repairedColumnCount): array
    {
        $rowCount = count($rows);
        $sourceColumnCount = $widths === [] ? 0 : max($widths);
        $columnCount = max(0, $repairedColumnCount);
        $expectedWidth = $widths[0] ?? 0;
        $strictMismatches = [];
        $headerMismatches = [];
        $relaxedPaddedRows = [];
        $relaxedTruncatedRows = [];
        $trailingEmptyFieldRows = [];

        foreach ($widths as $index => $width) {
            $sourceRow = $sourceRowIndexes[$index] ?? $index;
            $rowRole = $hasHeader && $index === 0 ? 'header' : 'body';
            if ($index > 0 && $width !== $expectedWidth) {
                $strictMismatches[] = $this->rowWidthMismatch($index, $sourceRow, $rowRole, $width, $expectedWidth);
            }

            if ($hasHeader && $index > 0 && $width !== $expectedWidth) {
                $headerMismatches[] = $this->rowWidthMismatch($index, $sourceRow, 'body', $width, $expectedWidth);
            }

            if ($width < $columnCount) {
                $relaxedPaddedRows[] = $this->rowWidthMismatch($index, $sourceRow, $rowRole, $width, $columnCount);
            }
            if ($width > $columnCount) {
                $relaxedTruncatedRows[] = $this->rowWidthMismatch($index, $sourceRow, $rowRole, $width, $columnCount);
            }

            $row = $rows[$index] ?? [];
            if ($row !== [] && $row[array_key_last($row)] === '') {
                $trailingEmptyFieldRows[] = $sourceRow;
            }
        }

        return [
            'rowWidths' => $widths,
            'sourceRowIndexes' => $sourceRowIndexes,
            'blankRowCount' => count($blankRows),
            'blankRows' => $blankRows,
            'trailingEmptyFieldRows' => $trailingEmptyFieldRows,
            'widthCounts' => $this->rowWidthCounts($widths),
            'sourceMaxFieldCount' => $sourceColumnCount,
            'strict' => [
                'policy' => $hasHeader && $rowCount > 0 ? 'header-row' : 'first-row',
                'expectedColumnCount' => $expectedWidth,
                'consistent' => $strictMismatches === [],
                'mismatchCount' => count($strictMismatches),
                'mismatches' => $strictMismatches,
            ],
            'relaxed' => [
                'policy' => $this->rowRepairPolicy($hasHeader, $widths),
                'columnCount' => $columnCount,
                'sourceMaxFieldCount' => $sourceColumnCount,
                'consistent' => $widths === [] || ($relaxedPaddedRows === [] && $relaxedTruncatedRows === []),
                'changedRowCount' => count($relaxedPaddedRows) + count($relaxedTruncatedRows),
                'paddedRowCount' => count($relaxedPaddedRows),
                'truncatedRowCount' => count($relaxedTruncatedRows),
                'paddedRows' => $relaxedPaddedRows,
                'truncatedRows' => $relaxedTruncatedRows,
            ],
            'header' => [
                'enabled' => $hasHeader,
                'headerColumnCount' => $hasHeader && $widths !== [] ? $widths[0] : 0,
                'dataColumnCounts' => $hasHeader ? array_slice($widths, 1) : $widths,
                'consistentWithBody' => $headerMismatches === [],
                'mismatchCount' => count($headerMismatches),
                'mismatches' => $headerMismatches,
            ],
        ];
    }

    /**
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @param list<int> $sourceRowIndexes
     * @param list<int> $blankRows
     * @return array<string, mixed>
     */
    private function rowRepairSummary(array $rows, array $widths, array $sourceRowIndexes, array $blankRows, bool $hasHeader, int $repairedColumnCount): array
    {
        $sourceColumnCount = $widths === [] ? 0 : max($widths);
        $columnCount = max(0, $repairedColumnCount);
        $rowsSummary = [];
        $paddedRows = [];
        $truncatedRows = [];

        foreach ($widths as $index => $width) {
            $sourceRow = $sourceRowIndexes[$index] ?? $index;
            $rowRole = $hasHeader && $index === 0 ? 'header' : 'body';
            $missingFields = max(0, $columnCount - $width);
            $extraFields = max(0, $width - $columnCount);
            $repair = $missingFields > 0 ? 'padded' : ($extraFields > 0 ? 'truncated' : 'unchanged');
            $rowSummary = [
                'row' => $index,
                'sourceRow' => $sourceRow,
                'rowLabel' => 'source-row-' . $sourceRow,
                'rowRole' => $rowRole,
                'repair' => $repair,
                'originalColumnCount' => $width,
                'repairedColumnCount' => $columnCount,
                'missingFieldsAdded' => $missingFields,
                'extraFieldsDropped' => $extraFields,
            ];
            $rowsSummary[] = $rowSummary;
            if ($repair === 'padded') {
                $paddedRows[] = $rowSummary;
            }
            if ($repair === 'truncated') {
                $truncatedRows[] = $rowSummary;
            }
        }

        return [
            'policy' => $this->rowRepairPolicy($hasHeader, $widths),
            'strictPolicy' => $hasHeader && $widths !== [] ? 'header-row' : 'first-row',
            'originalColumnCounts' => $widths,
            'sourceMaxFieldCount' => $sourceColumnCount,
            'repairedColumnCount' => $columnCount,
            'sourceRowIndexes' => $sourceRowIndexes,
            'blankRowCount' => count($blankRows),
            'blankRows' => $blankRows,
            'changedRowCount' => count($paddedRows) + count($truncatedRows),
            'paddedRowCount' => count($paddedRows),
            'truncatedRowCount' => count($truncatedRows),
            'paddedRows' => $paddedRows,
            'truncatedRows' => $truncatedRows,
            'rows' => $rowsSummary,
        ];
    }

    /**
     * @return array{row:int, sourceRow:int, rowLabel:string, rowRole:string, actualFieldCount:int, expectedFieldCount:int, missingFields:int, extraFields:int}
     */
    private function rowWidthMismatch(int $row, int $sourceRow, string $rowRole, int $actual, int $expected): array
    {
        return [
            'row' => $row,
            'sourceRow' => $sourceRow,
            'rowLabel' => 'source-row-' . $sourceRow,
            'rowRole' => $rowRole,
            'actualFieldCount' => $actual,
            'expectedFieldCount' => $expected,
            'missingFields' => max(0, $expected - $actual),
            'extraFields' => max(0, $actual - $expected),
        ];
    }

    /**
     * @param list<int> $widths
     * @return list<array{width:int, count:int}>
     */
    private function rowWidthCounts(array $widths): array
    {
        $counts = [];
        foreach ($widths as $width) {
            $counts[$width] = ($counts[$width] ?? 0) + 1;
        }

        ksort($counts);
        $summary = [];
        foreach ($counts as $width => $count) {
            $summary[] = [
                'width' => (int) $width,
                'count' => $count,
            ];
        }

        return $summary;
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null} $dialect
     * @param array{sourcePath?:string} $options
     * @return array<string, mixed>
     */
    private function controlCharacterSummary(string $text, array $dialect, array $options): array
    {
        $delimiter = $dialect['delimiter'];
        $quote = $dialect['quote'];
        $sourcePath = $this->sourcePathContext($options);
        $samples = [];
        $byCodepoint = [];
        $sourceRows = [];
        $fieldQuoteBuckets = [
            'quoted' => [
                'controlCount' => 0,
                'sampleCount' => 0,
                'sampleIndexes' => [],
            ],
            'unquoted' => [
                'controlCount' => 0,
                'sampleCount' => 0,
                'sampleIndexes' => [],
            ],
        ];
        $totalCount = 0;
        $nulCount = 0;
        $rowIndex = 0;
        $columnIndex = 0;
        $fieldStarted = false;
        $fieldQuoted = false;
        $inQuotedField = false;
        $length = strlen($text);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = $text[$offset];

            if ($quote !== null && $byte === $quote) {
                if (!$fieldStarted) {
                    $fieldStarted = true;
                    $fieldQuoted = true;
                    $inQuotedField = true;
                    continue;
                }

                if ($inQuotedField) {
                    if (($text[$offset + 1] ?? '') === $quote) {
                        $offset++;
                        continue;
                    }

                    $inQuotedField = false;
                    continue;
                }
            }

            if (!$inQuotedField && $byte === $delimiter) {
                $columnIndex++;
                $fieldStarted = false;
                $fieldQuoted = false;
                continue;
            }

            if (!$inQuotedField && ($byte === "\n" || $byte === "\r")) {
                if ($byte === "\r" && ($text[$offset + 1] ?? '') === "\n") {
                    $offset++;
                }

                $rowIndex++;
                $columnIndex = 0;
                $fieldStarted = false;
                $fieldQuoted = false;
                continue;
            }

            $fieldStarted = true;
            $ordinal = ord($byte);
            if (!$this->isReviewableControlByte($ordinal)) {
                continue;
            }

            $totalCount++;
            if ($ordinal === 0) {
                $nulCount++;
            }

            $quoteBucket = $fieldQuoted ? 'quoted' : 'unquoted';
            $fieldQuoteBuckets[$quoteBucket]['controlCount']++;
            $codepoint = sprintf('U+%04X', $ordinal);
            $byCodepoint[$codepoint] = ($byCodepoint[$codepoint] ?? 0) + 1;

            $rowKey = (string) $rowIndex;
            if (!isset($sourceRows[$rowKey])) {
                $sourceRows[$rowKey] = [
                    'sourceRow' => $rowIndex,
                    'rowLabel' => 'source-row-' . $rowIndex,
                    'controlCount' => 0,
                    'quotedControlCount' => 0,
                    'unquotedControlCount' => 0,
                    'columns' => [],
                    'byCodepoint' => [],
                ];
            }
            $sourceRows[$rowKey]['controlCount']++;
            $sourceRows[$rowKey][$fieldQuoted ? 'quotedControlCount' : 'unquotedControlCount']++;
            $sourceRows[$rowKey]['byCodepoint'][$codepoint] = ($sourceRows[$rowKey]['byCodepoint'][$codepoint] ?? 0) + 1;
            if (!in_array($columnIndex, $sourceRows[$rowKey]['columns'], true)) {
                $sourceRows[$rowKey]['columns'][] = $columnIndex;
            }

            if (count($samples) >= self::CONTROL_CHARACTER_SAMPLE_LIMIT) {
                continue;
            }

            $sampleIndex = count($samples);
            $samples[] = [
                'sampleIndex' => $sampleIndex,
                'rowIndex' => $rowIndex,
                'rowNumber' => $rowIndex + 1,
                'sourceRow' => $rowIndex,
                'sourceRowNumber' => $rowIndex + 1,
                'columnIndex' => $columnIndex,
                'columnNumber' => $columnIndex + 1,
                'sourceColumn' => $columnIndex,
                'sourceColumnNumber' => $columnIndex + 1,
                'fieldQuoted' => $fieldQuoted,
                'quoteBucket' => $quoteBucket,
                'byteOffset' => $offset,
                'byteHex' => sprintf('%02X', $ordinal),
                'codepoint' => $codepoint,
                'name' => $this->controlByteName($ordinal),
                'byteSampleHex' => $this->byteSampleHex($text, $offset),
                'textSample' => $this->escapedTextSample($text, $offset),
                'positionBeforeRepair' => [
                    'sourceRow' => $rowIndex,
                    'sourceRowNumber' => $rowIndex + 1,
                    'sourceColumn' => $columnIndex,
                    'sourceColumnNumber' => $columnIndex + 1,
                ],
            ];
            $fieldQuoteBuckets[$quoteBucket]['sampleCount']++;
            $fieldQuoteBuckets[$quoteBucket]['sampleIndexes'][] = $sampleIndex;
        }

        ksort($byCodepoint);
        ksort($sourceRows, SORT_NUMERIC);
        foreach ($sourceRows as &$sourceRow) {
            sort($sourceRow['columns']);
            ksort($sourceRow['byCodepoint']);
        }
        unset($sourceRow);

        return [
            'policy' => 'report-c0-del-controls-except-ht-lf-cr',
            'sourcePath' => $sourcePath,
            'totalCount' => $totalCount,
            'nulCount' => $nulCount,
            'quotedFieldCount' => $fieldQuoteBuckets['quoted']['controlCount'],
            'unquotedFieldCount' => $fieldQuoteBuckets['unquoted']['controlCount'],
            'fieldQuoteBuckets' => $fieldQuoteBuckets,
            'sampleLimit' => self::CONTROL_CHARACTER_SAMPLE_LIMIT,
            'sampleCount' => count($samples),
            'truncated' => $totalCount > count($samples),
            'byCodepoint' => $byCodepoint,
            'sourceRows' => array_values($sourceRows),
            'samples' => $samples,
        ];
    }

    /**
     * @param array<string, mixed> $controlCharacters
     * @param array<string, mixed> $rowRepairSummary
     * @return array<string, mixed>
     */
    private function annotateControlCharactersWithRepair(array $controlCharacters, array $rowRepairSummary): array
    {
        $repairRowsBySource = [];
        foreach ($rowRepairSummary['rows'] ?? [] as $row) {
            if (is_array($row) && isset($row['sourceRow'])) {
                $repairRowsBySource[(string) $row['sourceRow']] = $row;
            }
        }

        $samples = [];
        foreach ($controlCharacters['samples'] ?? [] as $sample) {
            if (!is_array($sample)) {
                continue;
            }

            $sourceRow = $sample['sourceRow'] ?? $sample['rowIndex'] ?? null;
            $sourceColumn = $sample['sourceColumn'] ?? $sample['columnIndex'] ?? null;
            $repairRow = is_int($sourceRow) ? ($repairRowsBySource[(string) $sourceRow] ?? null) : null;
            if (is_array($repairRow)) {
                $sample['rowRepair'] = $this->controlSampleRepairContext($repairRow);
                $sample['positionAfterRepair'] = [
                    'row' => $repairRow['row'],
                    'rowNumber' => $repairRow['row'] + 1,
                    'sourceRow' => $repairRow['sourceRow'],
                    'sourceRowNumber' => $repairRow['sourceRow'] + 1,
                    'columnIndex' => $sourceColumn,
                    'columnNumber' => is_int($sourceColumn) ? $sourceColumn + 1 : null,
                    'columnWithinRepairedWidth' => is_int($sourceColumn)
                        && $sourceColumn < $repairRow['repairedColumnCount'],
                ];
            } else {
                $sample['rowRepair'] = [
                    'repair' => 'unmapped',
                    'rowRole' => 'unknown',
                    'rowLabel' => is_int($sourceRow) ? 'source-row-' . $sourceRow : 'unknown',
                    'originalColumnCount' => 0,
                    'repairedColumnCount' => 0,
                    'missingFieldsAdded' => 0,
                    'extraFieldsDropped' => 0,
                ];
                $sample['positionAfterRepair'] = null;
            }

            $samples[] = $sample;
        }

        $controlCharacters['samples'] = $samples;
        $controlCharacters['repairSummary'] = $this->controlRepairSummary($controlCharacters, $repairRowsBySource);

        return $controlCharacters;
    }

    /**
     * @param array<string, mixed> $repairRow
     * @return array<string, mixed>
     */
    private function controlSampleRepairContext(array $repairRow): array
    {
        return [
            'row' => $repairRow['row'],
            'sourceRow' => $repairRow['sourceRow'],
            'rowLabel' => $repairRow['rowLabel'],
            'rowRole' => $repairRow['rowRole'],
            'repair' => $repairRow['repair'],
            'originalColumnCount' => $repairRow['originalColumnCount'],
            'repairedColumnCount' => $repairRow['repairedColumnCount'],
            'missingFieldsAdded' => $repairRow['missingFieldsAdded'],
            'extraFieldsDropped' => $repairRow['extraFieldsDropped'],
        ];
    }

    /**
     * @param array<string, mixed> $controlCharacters
     * @param array<string, array<string, mixed>> $repairRowsBySource
     * @return array<string, mixed>
     */
    private function controlRepairSummary(array $controlCharacters, array $repairRowsBySource): array
    {
        $byRepair = [
            'padded' => 0,
            'unchanged' => 0,
            'truncated' => 0,
            'unmapped' => 0,
        ];
        $sourceRows = [];

        foreach ($controlCharacters['sourceRows'] ?? [] as $sourceRowControls) {
            if (!is_array($sourceRowControls)) {
                continue;
            }

            $sourceRow = $sourceRowControls['sourceRow'] ?? null;
            $controlCount = $sourceRowControls['controlCount'] ?? 0;
            $repairRow = is_int($sourceRow) ? ($repairRowsBySource[(string) $sourceRow] ?? null) : null;
            $repair = is_array($repairRow) ? (string) $repairRow['repair'] : 'unmapped';
            if (!array_key_exists($repair, $byRepair)) {
                $byRepair[$repair] = 0;
            }
            if (is_int($controlCount)) {
                $byRepair[$repair] += $controlCount;
            }

            $sourceRows[] = [
                'sourceRow' => $sourceRow,
                'rowLabel' => is_array($repairRow) ? $repairRow['rowLabel'] : $sourceRowControls['rowLabel'],
                'rowRole' => is_array($repairRow) ? $repairRow['rowRole'] : 'unknown',
                'repair' => $repair,
                'controlCount' => $controlCount,
                'quotedControlCount' => $sourceRowControls['quotedControlCount'] ?? 0,
                'unquotedControlCount' => $sourceRowControls['unquotedControlCount'] ?? 0,
                'columns' => $sourceRowControls['columns'] ?? [],
                'originalColumnCount' => is_array($repairRow) ? $repairRow['originalColumnCount'] : 0,
                'repairedColumnCount' => is_array($repairRow) ? $repairRow['repairedColumnCount'] : 0,
                'missingFieldsAdded' => is_array($repairRow) ? $repairRow['missingFieldsAdded'] : 0,
                'extraFieldsDropped' => is_array($repairRow) ? $repairRow['extraFieldsDropped'] : 0,
            ];
        }

        return [
            'policy' => 'annotate-controls-after-pandoc-row-normalization',
            'sourcePath' => $controlCharacters['sourcePath'] ?? null,
            'controlCount' => $controlCharacters['totalCount'] ?? 0,
            'sampleCount' => $controlCharacters['sampleCount'] ?? 0,
            'controlsInChangedRows' => $byRepair['padded'] + $byRepair['truncated'],
            'controlsInPaddedRows' => $byRepair['padded'],
            'controlsInTruncatedRows' => $byRepair['truncated'],
            'controlsInUnchangedRows' => $byRepair['unchanged'],
            'controlsInUnmappedRows' => $byRepair['unmapped'],
            'byRepair' => $byRepair,
            'sourceRows' => $sourceRows,
        ];
    }

    /**
     * @param array{format:string, delimiter:string, formatInference:array<string, mixed>} $formatResolution
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     * @return array<string, mixed>
     */
    private function formatContext(array $formatResolution, array $options): array
    {
        $extension = $this->metadataStringOption($options, 'extension');
        $sourcePath = $this->metadataStringOption($options, 'sourcePath');
        $sourcePathExtension = $sourcePath === null ? null : pathinfo($sourcePath, PATHINFO_EXTENSION);
        $sourcePathExtension = $sourcePathExtension === '' ? null : $sourcePathExtension;
        $extensionFormat = $extension === null ? null : PandocFormatRegistry::inferTabularDataFormatFromExtension($extension);
        $sourcePathFormat = $sourcePathExtension === null
            ? null
            : PandocFormatRegistry::inferTabularDataFormatFromExtension($sourcePathExtension);
        $selectedFormat = $formatResolution['format'];
        $contextFormats = array_values(array_unique(array_filter(
            [$extensionFormat, $sourcePathFormat],
            static fn (?string $format): bool => $format !== null
        )));

        return [
            'requestedFormat' => (string) ($formatResolution['formatInference']['requestedFormat'] ?? $selectedFormat),
            'selectedFormat' => $selectedFormat,
            'sourcePath' => $sourcePath,
            'sourcePathExtension' => $sourcePathExtension,
            'sourcePathFormat' => $sourcePathFormat,
            'extension' => $extension,
            'extensionFormat' => $extensionFormat,
            'contextFormats' => $contextFormats,
            'formatMatchesContext' => $contextFormats === [] ? null : $contextFormats === [$selectedFormat],
            'contextConflict' => count($contextFormats) > 1,
        ];
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     */
    private function metadataStringOption(array $options, string $key): ?string
    {
        if (!array_key_exists($key, $options)) {
            return null;
        }

        if (!is_string($options[$key])) {
            throw new \InvalidArgumentException("Delimited text {$key} option must be a string");
        }

        $value = trim($options[$key]);

        return $value === '' ? null : $value;
    }

    /**
     * @param array{sourcePath?:string} $options
     */
    private function sourcePathContext(array $options): ?string
    {
        if (!array_key_exists('sourcePath', $options)) {
            return null;
        }

        if (!is_string($options['sourcePath'])) {
            throw new \InvalidArgumentException('Delimited text sourcePath option must be a string');
        }

        $sourcePath = trim($options['sourcePath']);

        return $sourcePath === '' ? null : $sourcePath;
    }

    private function isReviewableControlByte(int $ordinal): bool
    {
        if ($ordinal === 9 || $ordinal === 10 || $ordinal === 13) {
            return false;
        }

        return $ordinal < 32 || $ordinal === 127;
    }

    private function byteSampleHex(string $text, int $offset): string
    {
        $start = max(0, $offset - self::CONTROL_CHARACTER_SAMPLE_RADIUS);
        $sample = substr($text, $start, (self::CONTROL_CHARACTER_SAMPLE_RADIUS * 2) + 1);
        $bytes = [];
        $length = strlen($sample);
        for ($index = 0; $index < $length; $index++) {
            $bytes[] = sprintf('%02X', ord($sample[$index]));
        }

        return implode(' ', $bytes);
    }

    private function escapedTextSample(string $text, int $offset): string
    {
        $start = max(0, $offset - self::CONTROL_CHARACTER_SAMPLE_RADIUS);
        $sample = substr($text, $start, (self::CONTROL_CHARACTER_SAMPLE_RADIUS * 2) + 1);
        $escaped = '';
        $length = strlen($sample);
        for ($index = 0; $index < $length; $index++) {
            $ordinal = ord($sample[$index]);
            if ($ordinal === 9) {
                $escaped .= '\\t';
                continue;
            }
            if ($ordinal === 10) {
                $escaped .= '\\n';
                continue;
            }
            if ($ordinal === 13) {
                $escaped .= '\\r';
                continue;
            }
            if ($ordinal < 32 || $ordinal === 127 || $ordinal > 126) {
                $escaped .= sprintf('\\x%02X', $ordinal);
                continue;
            }

            $escaped .= $sample[$index];
        }

        return $escaped;
    }

    private function controlByteName(int $ordinal): string
    {
        return [
            0 => 'NUL',
            1 => 'SOH',
            2 => 'STX',
            3 => 'ETX',
            4 => 'EOT',
            5 => 'ENQ',
            6 => 'ACK',
            7 => 'BEL',
            8 => 'BS',
            11 => 'VT',
            12 => 'FF',
            14 => 'SO',
            15 => 'SI',
            16 => 'DLE',
            17 => 'DC1',
            18 => 'DC2',
            19 => 'DC3',
            20 => 'DC4',
            21 => 'NAK',
            22 => 'SYN',
            23 => 'ETB',
            24 => 'CAN',
            25 => 'EM',
            26 => 'SUB',
            27 => 'ESC',
            28 => 'FS',
            29 => 'GS',
            30 => 'RS',
            31 => 'US',
            127 => 'DEL',
        ][$ordinal] ?? sprintf('C0-%02X', $ordinal);
    }

    private function isLineBreak(string $char): bool
    {
        return $char === "\n" || $char === "\r";
    }

    private function endsWithLineBreak(string $text): bool
    {
        return str_ends_with($text, "\n") || str_ends_with($text, "\r");
    }

    private function stripBom(string $text): string
    {
        return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
    }

    /**
     * @return list<string>
     */
    private function generatedColumnLabels(int $columnCount): array
    {
        $labels = [];
        for ($index = 1; $index <= $columnCount; $index++) {
            $labels[] = 'column' . $index;
        }

        return $labels;
    }

    /**
     * @param list<string> $row
     * @return list<string>
     */
    private function normalizedRow(array $row, int $columnCount): array
    {
        $normalized = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $normalized[] = $row[$column] ?? '';
        }

        return $normalized;
    }
}
