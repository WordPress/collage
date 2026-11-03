<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class IpynbReader
{
    private const MAX_CELLS = 200;
    private const MAX_CELL_SOURCE_BYTES = 1048576;
    private const MAX_EXECUTION_COUNT = 2147483647;
    private const SUPPORTED_NBFORMAT_MAJOR = 4;
    private const SUPPORTED_NBFORMAT_MINOR = 5;
    private const UNSAFE_CELL_METADATA_KEYS = [
        'collapsed',
        'deletable',
        'editable',
        'hide_input',
        'jupyter',
        'scrolled',
        'slideshow',
        'tags',
        'trusted',
    ];

    private readonly MarkdownReader $markdownReader;

    public function __construct(?MarkdownReader $markdownReader = null)
    {
        $this->markdownReader = $markdownReader ?? new MarkdownReader(['yamlMetadata' => false]);
    }

    public function read(string $json): AstNode
    {
        $notebook = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($notebook)) {
            throw new \InvalidArgumentException('IPYNB source must decode to a JSON object');
        }

        $cellsValue = $notebook['cells'] ?? null;
        $cells = is_array($cellsValue) ? $cellsValue : [];
        if (count($cells) > self::MAX_CELLS) {
            throw new \InvalidArgumentException('IPYNB notebook exceeds the bounded native reader cell limit');
        }

        $nbformat = $notebook['nbformat'] ?? null;
        $nbformatMinor = $notebook['nbformat_minor'] ?? null;
        $cellIdsRequired = $this->cellIdsRequired($nbformat, $nbformatMinor);

        $notebookSchemaDiagnostics = $this->notebookSchemaDiagnostics($notebook, $cellsValue);
        $schemaDiagnostics = $notebookSchemaDiagnostics;
        $cellSchemaDiagnosticCount = 0;
        $rawMarkdownCellDiagnostics = [];

        $metadata = isset($notebook['metadata']) && is_array($notebook['metadata']) ? $notebook['metadata'] : [];
        $language = $this->metadataString($metadata['language_info'] ?? null, 'name')
            ?? $this->metadataString($metadata['kernelspec'] ?? null, 'language')
            ?? '';

        $blocks = [];
        $cellSummaries = [];
        $markdownCellCount = 0;
        $codeCellCount = 0;
        $rawCellCount = 0;
        $attachmentCount = 0;
        $outputCount = 0;
        $unsupportedResourceCount = 0;
        $outputBytePresenceCount = 0;
        $outputMimeBundleCount = 0;
        $notebookRichOutputUnsupportedCount = 0;
        $notebookOutputMimeTypes = [];
        $notebookOutputDiagnostics = [];
        $notebookOutputAggregateDiagnostics = [];
        $notebookDiagnostics = [];
        $attachmentMedia = [];
        $attachmentMediaDiagnostics = [];
        $attachmentManifestEntries = [];
        $attachmentDiagnostics = [];
        $cellExecutionCountPresentCount = 0;
        $cellExecutionCountValidCount = 0;
        $outputExecutionCountRecordCount = 0;
        $outputExecutionCountMismatchCount = 0;
        $outputRepeatedMimeBundleKeyCount = 0;
        $sourceShapeCounts = [];
        $sourceLineEndingStyles = [];
        $sourceLineEndingCounts = ['lf' => 0, 'crlf' => 0, 'cr' => 0];
        $sourceTrailingNewlineCount = 0;
        $emptySourceCount = 0;
        $sourceContentStateCounts = [
            'empty' => 0,
            'whitespace-only' => 0,
            'content' => 0,
        ];
        $sourceFingerprintCounts = [];
        $sourceFingerprintIndexes = [];
        $totalSourceBytes = 0;
        $totalSourceLineCount = 0;
        $mixedLineEndingSourceCount = 0;
        $metadataKeys = $this->metadataKeys($metadata);

        foreach ($cells as $index => $cell) {
            $cellIndex = is_int($index) ? $index : count($cellSummaries);
            if (!is_array($cell)) {
                throw new \InvalidArgumentException("IPYNB cell {$cellIndex} is not an object");
            }

            $cellType = $this->cellType($cell['cell_type'] ?? null);
            $cellSchemaDiagnostics = $this->cellSchemaDiagnostics($cell, $cellType, $cellIndex);
            foreach ($cellSchemaDiagnostics as $diagnostic) {
                $schemaDiagnostics[] = $diagnostic;
            }
            $cellSchemaDiagnosticCount += count($cellSchemaDiagnostics);

            $sourcePresent = array_key_exists('source', $cell);
            $sourceValue = $sourcePresent ? $cell['source'] : '';
            $sourceReview = $this->sourceReview($sourceValue, $sourcePresent, "IPYNB cell {$cellIndex} source");
            $source = $sourceReview['text'];
            if ($sourceReview['bytes'] > self::MAX_CELL_SOURCE_BYTES) {
                throw new \InvalidArgumentException("IPYNB cell {$cellIndex} exceeds the bounded native reader source limit");
            }
            $cellSourceDiagnostics = $this->rawMarkdownCellDiagnostics($cell, $cellType, $cellIndex, $sourcePresent, $sourceValue, $source);
            foreach ($cellSourceDiagnostics as $diagnostic) {
                $rawMarkdownCellDiagnostics[] = $diagnostic;
            }
            $sourceShapeCounts[$sourceReview['shape']] = ($sourceShapeCounts[$sourceReview['shape']] ?? 0) + 1;
            $sourceLineEndingStyles[$sourceReview['lineEnding']] = ($sourceLineEndingStyles[$sourceReview['lineEnding']] ?? 0) + 1;
            foreach ($sourceReview['lineEndingCounts'] as $lineEnding => $count) {
                $sourceLineEndingCounts[$lineEnding] += $count;
            }
            if ($sourceReview['trailingNewline']) {
                $sourceTrailingNewlineCount++;
            }
            if ($sourceReview['bytes'] === 0) {
                $emptySourceCount++;
            }
            $sourceContentStateCounts[$sourceReview['contentState']] = ($sourceContentStateCounts[$sourceReview['contentState']] ?? 0) + 1;
            $sourceFingerprint = $sourceReview['fingerprint'];
            $sourceFingerprintCounts[$sourceFingerprint] = ($sourceFingerprintCounts[$sourceFingerprint] ?? 0) + 1;
            $sourceFingerprintIndexes[$sourceFingerprint][] = $cellIndex;
            $totalSourceBytes += $sourceReview['bytes'];
            $totalSourceLineCount += $sourceReview['lineCount'];
            if ($sourceReview['hasMixedLineEndings']) {
                $mixedLineEndingSourceCount++;
            }

            $attachments = isset($cell['attachments']) && is_array($cell['attachments']) ? $cell['attachments'] : [];
            $outputs = isset($cell['outputs']) && is_array($cell['outputs']) ? $cell['outputs'] : [];
            $attachmentSummary = $this->attachmentSummary($attachments, $cellIndex);
            $cellId = $this->cellIdValue($cell);
            $cellIdDiagnostics = $this->cellIdDiagnostics($cell, $cellType, $cellIndex, $cellIdsRequired);
            $executionSummary = $this->executionCountSummary($cell, $cellType, $cellIndex, $cellId);
            $outputSummary = $this->outputSummary($outputs, $executionSummary['validInteger'], $cellType, $cellIndex, $cellId);
            $cellMetadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
            $cellMetadataKeys = $this->metadataKeys($cellMetadata);
            $cellTags = $this->metadataStringList($cellMetadata['tags'] ?? null);
            $cellDiagnostics = $this->cellDiagnostics($attachmentSummary, $outputSummary);
            $cellExecutionDiagnostics = array_merge($cellIdDiagnostics, $executionSummary['diagnostics'], $outputSummary['executionDiagnostics']);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            $unsupportedResourceCount += $attachmentSummary['count'] + $outputSummary['bytePresenceCount'];
            $outputBytePresenceCount += $outputSummary['bytePresenceCount'];
            $outputMimeBundleCount += $outputSummary['mimeBundleCount'];
            $outputExecutionCountRecordCount += $outputSummary['executionCountRecordCount'];
            $outputExecutionCountMismatchCount += $outputSummary['executionCountMismatchCount'];
            $outputRepeatedMimeBundleKeyCount += count($outputSummary['repeatedMimeBundleKeys']);
            $notebookRichOutputUnsupportedCount += $outputSummary['richUnsupportedCount'];
            $notebookOutputMimeTypes = array_merge($notebookOutputMimeTypes, $outputSummary['mimeTypes']);
            $notebookOutputDiagnostics = array_merge($notebookOutputDiagnostics, $outputSummary['unsupportedVerdicts']);
            $notebookOutputAggregateDiagnostics = array_merge($notebookOutputAggregateDiagnostics, $outputSummary['aggregateDiagnostics']);
            $notebookDiagnostics = array_merge($notebookDiagnostics, $cellExecutionDiagnostics);
            $attachmentMedia = array_merge($attachmentMedia, $attachmentSummary['media']);
            $attachmentMediaDiagnostics = array_merge($attachmentMediaDiagnostics, $attachmentSummary['diagnostics']);
            $attachmentManifestEntries = array_merge($attachmentManifestEntries, $attachmentSummary['manifestEntries']);
            $attachmentDiagnostics = array_merge($attachmentDiagnostics, $attachmentSummary['manifestDiagnostics']);
            if ($executionSummary['present']) {
                $cellExecutionCountPresentCount++;
            }
            if ($executionSummary['valid']) {
                $cellExecutionCountValidCount++;
            }

            $attributes = [
                'data-ipynb-cell-index' => (string) $cellIndex,
                'data-ipynb-cell-type' => $cellType,
            ];
            if ($cellId !== null) {
                $attributes['data-ipynb-cell-id'] = $cellId;
            }
            if ($attachmentSummary['count'] > 0) {
                $attributes['data-ipynb-attachment-count'] = (string) $attachmentSummary['count'];
            }
            if ($outputSummary['count'] > 0) {
                $attributes['data-ipynb-output-count'] = (string) $outputSummary['count'];
                $attributes['data-ipynb-output-indexes'] = implode(' ', array_map(static fn (int $index): string => (string) $index, $outputSummary['indexes']));
                $attributes['data-ipynb-output-display-order'] = implode(' ', $outputSummary['orderTypes']);
            }
            if ($outputSummary['mimeTypes'] !== []) {
                $attributes['data-ipynb-output-mime-types'] = implode(' ', $outputSummary['mimeTypes']);
            }
            if ($outputSummary['repeatedMimeBundleKeys'] !== []) {
                $attributes['data-ipynb-output-repeated-mime-keys'] = implode(' ', $outputSummary['repeatedMimeBundleKeys']);
            }
            if ($outputSummary['richUnsupportedCount'] > 0) {
                $attributes['data-ipynb-rich-output-unsupported-count'] = (string) $outputSummary['richUnsupportedCount'];
            }
            if ($outputSummary['bytePresenceCount'] > 0) {
                $attributes['data-ipynb-output-byte-policy'] = 'metadata-only';
                $attributes['data-ipynb-output-byte-presence-count'] = (string) $outputSummary['bytePresenceCount'];
            }
            if ($outputSummary['executionCounts'] !== []) {
                $attributes['data-ipynb-output-execution-counts'] = implode(' ', array_map(static fn (int $count): string => (string) $count, $outputSummary['executionCounts']));
            }
            if ($outputSummary['executionCountMismatchCount'] > 0) {
                $attributes['data-ipynb-output-execution-count-mismatch-count'] = (string) $outputSummary['executionCountMismatchCount'];
            }
            if ($outputSummary['errorNames'] !== []) {
                $attributes['data-ipynb-output-error-names'] = implode(' ', $outputSummary['errorNames']);
            }
            if ($outputSummary['streamNames'] !== []) {
                $attributes['data-ipynb-output-stream-names'] = implode(' ', $outputSummary['streamNames']);
            }
            if ($outputSummary['aggregateDiagnostics'] !== []) {
                $attributes['data-ipynb-output-aggregate-diagnostic-count'] = (string) count($outputSummary['aggregateDiagnostics']);
            }
            if ($executionSummary['validInteger'] !== null) {
                $attributes['data-ipynb-execution-count'] = (string) $executionSummary['validInteger'];
            }
            if ($cellTags !== []) {
                $attributes['data-ipynb-cell-tags'] = implode(' ', $cellTags);
            }
            if ($cellExecutionDiagnostics !== []) {
                $attributes['data-ipynb-diagnostic-count'] = (string) count($cellExecutionDiagnostics);
            }
            if ($cellDiagnostics !== []) {
                $attributes['data-ipynb-diagnostics'] = implode(' ', $cellDiagnostics);
            }
            if ($attachmentSummary['media'] !== []) {
                $attributes['data-ipynb-attachment-media-count'] = (string) count($attachmentSummary['media']);
            }
            if ($attachmentSummary['diagnostics'] !== []) {
                $attributes['data-ipynb-attachment-diagnostics'] = implode(' ', $attachmentSummary['diagnostics']);
            }

            $children = match ($cellType) {
                'markdown' => $this->markdownCellBlocks($source),
                'code' => [$this->codeCellBlock($source, $language, $cellIndex, $executionSummary['validInteger'])],
                'raw' => [$this->rawCellBlock($source, $cellIndex)],
                default => [$this->unsupportedCellBlock($source, $cellType, $cellIndex)],
            };

            if ($cellType === 'markdown') {
                $markdownCellCount++;
            } elseif ($cellType === 'code') {
                $codeCellCount++;
            } elseif ($cellType === 'raw') {
                $rawCellCount++;
            }

            $cellAttrs = [
                'classes' => ['ipynb-cell', 'ipynb-' . $cellType . '-cell'],
                'attributes' => $attributes,
                'ipynbCellType' => $cellType,
                'ipynbCellIndex' => $cellIndex,
                'ipynbSourceShape' => $sourceReview['shape'],
                'ipynbSourcePartCount' => $sourceReview['partCount'],
                'ipynbSourceBytes' => $sourceReview['bytes'],
                'ipynbSourceLineCount' => $sourceReview['lineCount'],
                'ipynbSourceLineEnding' => $sourceReview['lineEnding'],
                'ipynbSourceLineEndingCount' => $sourceReview['lineEndingCount'],
                'ipynbSourceLineEndingCounts' => $sourceReview['lineEndingCounts'],
                'ipynbSourceTrailingNewline' => $sourceReview['trailingNewline'],
                'ipynbSourceHasTrailingLineEnding' => $sourceReview['trailingNewline'],
                'ipynbSourceHasMixedLineEndings' => $sourceReview['hasMixedLineEndings'],
                'ipynbSourceContentState' => $sourceReview['contentState'],
                'ipynbSourceDigest' => $sourceReview['digest'],
                'ipynbSourceFingerprint' => $sourceFingerprint,
                'ipynbSourceDiagnostics' => $sourceReview['diagnostics'],
                'ipynbAttachmentCount' => $attachmentSummary['count'],
                'ipynbAttachmentNames' => $attachmentSummary['names'],
                'ipynbAttachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'ipynbAttachmentMedia' => $attachmentSummary['media'],
                'ipynbAttachmentMediaDiagnostics' => $attachmentSummary['diagnostics'],
                'ipynbAttachmentDiagnostics' => $attachmentSummary['manifestDiagnostics'],
                'ipynbOutputCount' => $outputSummary['count'],
                'ipynbOutputTypes' => $outputSummary['types'],
                'ipynbOutputOrderTypes' => $outputSummary['orderTypes'],
                'ipynbOutputIndexes' => $outputSummary['indexes'],
                'ipynbOutputMimeTypes' => $outputSummary['mimeTypes'],
                'ipynbOutputSummaries' => $outputSummary['outputs'],
                'ipynbOutputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'ipynbOutputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'ipynbOutputRepeatedMimeBundleKeys' => $outputSummary['repeatedMimeBundleKeys'],
                'ipynbOutputRepeatedMimeBundleRecords' => $outputSummary['repeatedMimeBundleRecords'],
                'ipynbOutputAggregateDiagnostics' => $outputSummary['aggregateDiagnostics'],
                'ipynbOutputExecutionCounts' => $outputSummary['executionCounts'],
                'ipynbOutputExecutionCountRecords' => $outputSummary['executionCountRecords'],
                'ipynbOutputExecutionCountRecordCount' => $outputSummary['executionCountRecordCount'],
                'ipynbOutputExecutionCountMismatchCount' => $outputSummary['executionCountMismatchCount'],
                'ipynbOutputErrorNames' => $outputSummary['errorNames'],
                'ipynbOutputStreamNames' => $outputSummary['streamNames'],
                'ipynbOutputUnsupportedVerdicts' => $outputSummary['unsupportedVerdicts'],
                'ipynbRichOutputUnsupportedCount' => $outputSummary['richUnsupportedCount'],
                'ipynbUnsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['bytePresenceCount'],
                'ipynbUnsupportedResourceDiagnostics' => $cellDiagnostics,
                'ipynbCellMetadataKeys' => $cellMetadataKeys,
                'ipynbCellTags' => $cellTags,
                'ipynbExecutionCountPresent' => $executionSummary['present'],
                'ipynbExecutionCountValid' => $executionSummary['valid'],
                'ipynbExecutionCountType' => $executionSummary['type'],
                'ipynbDiagnosticCount' => count($cellExecutionDiagnostics),
                'ipynbDiagnostics' => $cellExecutionDiagnostics,
            ];
            if ($cellId !== null) {
                $cellAttrs['ipynbCellId'] = $cellId;
            }
            if (array_key_exists('execution_count', $cell) && (is_int($cell['execution_count']) || $cell['execution_count'] === null)) {
                $cellAttrs['ipynbExecutionCount'] = $cell['execution_count'];
            }
            if ($cellSchemaDiagnostics !== []) {
                $cellAttrs['ipynbCellSchemaDiagnosticCount'] = count($cellSchemaDiagnostics);
                $cellAttrs['ipynbCellSchemaDiagnostics'] = $cellSchemaDiagnostics;
            }
            if ($cellSourceDiagnostics !== []) {
                $cellAttrs['ipynbCellSourceDiagnosticCount'] = count($cellSourceDiagnostics);
                $cellAttrs['ipynbCellSourceDiagnostics'] = $cellSourceDiagnostics;
            }

            $blocks[] = new AstNode('div', $cellAttrs, $children);
            $cellSummary = [
                'index' => $cellIndex,
                'type' => $cellType,
                'sourceBytes' => $sourceReview['bytes'],
                'sourceShape' => $sourceReview['shape'],
                'sourcePartCount' => $sourceReview['partCount'],
                'sourceLineCount' => $sourceReview['lineCount'],
                'sourceLineEnding' => $sourceReview['lineEnding'],
                'sourceLineEndingCount' => $sourceReview['lineEndingCount'],
                'sourceLineEndings' => $sourceReview['lineEndingCounts'],
                'sourceLineEndingCounts' => $sourceReview['lineEndingCounts'],
                'sourceTrailingNewline' => $sourceReview['trailingNewline'],
                'sourceHasTrailingLineEnding' => $sourceReview['trailingNewline'],
                'sourceHasMixedLineEndings' => $sourceReview['hasMixedLineEndings'],
                'sourceContentState' => $sourceReview['contentState'],
                'sourceDigest' => $sourceReview['digest'],
                'sourceFingerprint' => $sourceFingerprint,
                'sourceDiagnostics' => $sourceReview['diagnostics'],
                'attachmentCount' => $attachmentSummary['count'],
                'attachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'attachmentMedia' => $attachmentSummary['media'],
                'attachmentMediaDiagnostics' => $attachmentSummary['diagnostics'],
                'attachmentDiagnostics' => $attachmentSummary['manifestDiagnostics'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputOrderTypes' => $outputSummary['orderTypes'],
                'outputIndexes' => $outputSummary['indexes'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'outputSummaries' => $outputSummary['outputs'],
                'outputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'outputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'outputRepeatedMimeBundleKeys' => $outputSummary['repeatedMimeBundleKeys'],
                'outputAggregateDiagnosticCount' => count($outputSummary['aggregateDiagnostics']),
                'outputAggregateDiagnostics' => $outputSummary['aggregateDiagnostics'],
                'outputExecutionCounts' => $outputSummary['executionCounts'],
                'outputExecutionCountRecords' => $outputSummary['executionCountRecords'],
                'outputExecutionCountRecordCount' => $outputSummary['executionCountRecordCount'],
                'outputExecutionCountMismatchCount' => $outputSummary['executionCountMismatchCount'],
                'outputErrorNames' => $outputSummary['errorNames'],
                'outputStreamNames' => $outputSummary['streamNames'],
                'outputUnsupportedVerdicts' => $outputSummary['unsupportedVerdicts'],
                'richOutputUnsupportedCount' => $outputSummary['richUnsupportedCount'],
                'unsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['bytePresenceCount'],
                'diagnostics' => $cellDiagnostics,
                'executionCountPresent' => $executionSummary['present'],
                'executionCountValid' => $executionSummary['valid'],
                'diagnosticCount' => count($cellExecutionDiagnostics),
                'executionDiagnostics' => $cellExecutionDiagnostics,
                'metadataKeys' => $cellMetadataKeys,
                'tags' => $cellTags,
            ];
            if ($cellId !== null) {
                $cellSummary['id'] = $cellId;
            }
            if (array_key_exists('execution_count', $cell) && (is_int($cell['execution_count']) || $cell['execution_count'] === null)) {
                $cellSummary['executionCount'] = $cell['execution_count'];
            }
            if ($cellSchemaDiagnostics !== []) {
                $cellSummary['schemaDiagnosticCount'] = count($cellSchemaDiagnostics);
                $cellSummary['schemaDiagnostics'] = $cellSchemaDiagnostics;
            }
            if ($cellSourceDiagnostics !== []) {
                $cellSummary['sourceDiagnosticCount'] = count($cellSourceDiagnostics);
                $cellSummary['rawMarkdownSourceDiagnostics'] = $cellSourceDiagnostics;
            }
            $cellSummaries[] = $cellSummary;
        }

        ksort($sourceShapeCounts);
        ksort($sourceLineEndingStyles);
        ksort($sourceFingerprintCounts);
        $duplicateSourceFingerprints = [];
        $duplicateSourceCellCount = 0;
        foreach ($sourceFingerprintCounts as $sourceFingerprint => $count) {
            if ($count <= 1) {
                continue;
            }
            $duplicateSourceCellCount += $count;
            $duplicateSourceFingerprints[] = [
                'sourceFingerprint' => $sourceFingerprint,
                'count' => $count,
                'cellIndexes' => $sourceFingerprintIndexes[$sourceFingerprint] ?? [],
            ];
        }
        foreach ($cellSummaries as &$cellSummary) {
            $fingerprint = (string) ($cellSummary['sourceFingerprint'] ?? '');
            $cellSummary['sourceFingerprintCount'] = $sourceFingerprintCounts[$fingerprint] ?? 1;
        }
        unset($cellSummary);
        $attachmentCollisionGroups = $this->attachmentCollisionGroups($attachmentManifestEntries);
        if ($attachmentCollisionGroups !== []) {
            $attachmentDiagnostics[] = 'ipynb-attachment-safe-name-collision';
        }
        $attachmentDiagnostics = $this->uniqueSortedStrings($attachmentDiagnostics);
        $attachmentManifest = [
            'reviewPolicy' => 'metadata-only-no-payload',
            'payloadExposurePolicy' => 'ipynb-attachment-payload-bytes-omitted',
            'attachmentCount' => $attachmentCount,
            'entryCount' => count($attachmentManifestEntries),
            'entries' => $attachmentManifestEntries,
            'diagnosticCount' => count($attachmentDiagnostics),
            'diagnostics' => $attachmentDiagnostics,
            'collisionGroupCount' => count($attachmentCollisionGroups),
            'collisionGroups' => $attachmentCollisionGroups,
        ];

        return new AstNode('document', [
            'sourceFormat' => 'ipynb',
            'notebookCellCount' => count($cells),
            'notebookMarkdownCellCount' => $markdownCellCount,
            'notebookCodeCellCount' => $codeCellCount,
            'notebookRawCellCount' => $rawCellCount,
            'notebookSourceShapeCounts' => $sourceShapeCounts,
            'notebookSourceLineEndingStyles' => $sourceLineEndingStyles,
            'notebookSourceLineEndingCounts' => $sourceLineEndingCounts,
            'notebookSourceTrailingNewlineCount' => $sourceTrailingNewlineCount,
            'notebookEmptySourceCount' => $emptySourceCount,
            'notebookSourceContentStateCounts' => $sourceContentStateCounts,
            'notebookSourceSummary' => [
                'cellCount' => count($cells),
                'totalSourceBytes' => $totalSourceBytes,
                'totalSourceLineCount' => $totalSourceLineCount,
                'sourceShapeCounts' => $sourceShapeCounts,
                'sourceLineEndingCounts' => $sourceLineEndingCounts,
                'emptySourceCount' => $sourceContentStateCounts['empty'],
                'whitespaceOnlySourceCount' => $sourceContentStateCounts['whitespace-only'],
                'contentSourceCount' => $sourceContentStateCounts['content'],
                'mixedLineEndingSourceCount' => $mixedLineEndingSourceCount,
                'trailingLineEndingSourceCount' => $sourceTrailingNewlineCount,
                'uniqueSourceFingerprintCount' => count($sourceFingerprintCounts),
                'duplicateSourceFingerprintCount' => count($duplicateSourceFingerprints),
                'duplicateSourceCellCount' => $duplicateSourceCellCount,
            ],
            'notebookSourceFingerprintCounts' => $sourceFingerprintCounts,
            'notebookDuplicateSourceFingerprints' => $duplicateSourceFingerprints,
            'notebookAttachmentCount' => $attachmentCount,
            'notebookAttachmentMediaCount' => count($attachmentMedia),
            'notebookAttachmentMedia' => $attachmentMedia,
            'notebookAttachmentMediaDiagnostics' => $this->uniqueSortedStrings($attachmentMediaDiagnostics),
            'notebookAttachmentManifest' => $attachmentManifest,
            'notebookAttachmentDiagnostics' => $attachmentDiagnostics,
            'notebookAttachmentCollisionCount' => count($attachmentCollisionGroups),
            'notebookOutputCount' => $outputCount,
            'notebookOutputMimeTypes' => $this->uniqueSortedStrings($notebookOutputMimeTypes),
            'notebookOutputBytePresenceCount' => $outputBytePresenceCount,
            'notebookOutputMimeBundleCount' => $outputMimeBundleCount,
            'notebookOutputRepeatedMimeBundleKeyCount' => $outputRepeatedMimeBundleKeyCount,
            'notebookOutputAggregateDiagnosticCount' => count($notebookOutputAggregateDiagnostics),
            'notebookOutputAggregateDiagnostics' => $notebookOutputAggregateDiagnostics,
            'notebookCellIdsRequired' => $cellIdsRequired,
            'notebookCellExecutionCountPresentCount' => $cellExecutionCountPresentCount,
            'notebookCellExecutionCountValidCount' => $cellExecutionCountValidCount,
            'notebookOutputExecutionCountRecordCount' => $outputExecutionCountRecordCount,
            'notebookOutputExecutionCountMismatchCount' => $outputExecutionCountMismatchCount,
            'notebookDiagnosticCount' => count($notebookDiagnostics),
            'notebookDiagnostics' => $notebookDiagnostics,
            'notebookRichOutputUnsupportedCount' => $notebookRichOutputUnsupportedCount,
            'notebookOutputDiagnostics' => $notebookOutputDiagnostics,
            'notebookUnsupportedResourceCount' => $unsupportedResourceCount,
            'notebookNbformat' => $nbformat,
            'notebookNbformatMinor' => $nbformatMinor,
            'notebookMetadataKeys' => $metadataKeys,
            'notebookKernelName' => $this->metadataString($metadata['kernelspec'] ?? null, 'name'),
            'notebookLanguage' => $language,
            'notebookSchemaByteExposurePolicy' => 'metadata-only',
            'notebookSchemaDiagnosticCount' => count($schemaDiagnostics),
            'notebookSchemaDiagnostics' => $schemaDiagnostics,
            'notebookSchemaReview' => $this->notebookSchemaReview(
                $notebook,
                count($cells),
                count($notebookSchemaDiagnostics),
                $cellSchemaDiagnosticCount,
                $schemaDiagnostics
            ),
            'notebookRawMarkdownCellByteExposurePolicy' => 'metadata-only',
            'notebookRawMarkdownCellDiagnosticCount' => count($rawMarkdownCellDiagnostics),
            'notebookRawMarkdownCellDiagnostics' => $rawMarkdownCellDiagnostics,
            'notebookRawMarkdownCellReview' => $this->rawMarkdownCellReview($markdownCellCount, $rawCellCount, $rawMarkdownCellDiagnostics),
            'notebookCells' => $cellSummaries,
            'notebookResourcePolicy' => [
                'state' => $unsupportedResourceCount > 0 ? 'metadata-only' : 'none',
                'byteExposure' => 'blocked',
                'diagnostics' => $unsupportedResourceCount > 0 ? ['external-notebook-resource-bytes-blocked'] : [],
            ],
            'notebookOutputBytePolicy' => [
                'state' => $outputBytePresenceCount > 0 ? 'metadata-only' : 'none',
                'byteExposure' => 'blocked',
                'diagnostics' => $outputBytePresenceCount > 0 ? ['ipynb-rich-output-bytes-blocked'] : [],
            ],
        ], $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function markdownCellBlocks(string $source): array
    {
        if (trim($source) === '') {
            return [];
        }

        return $this->markdownReader->read($source)->children;
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function codeCellBlock(string $source, string $language, int $index, ?int $executionCount): AstNode
    {
        $classes = ['ipynb-code-cell-source'];
        if ($language !== '') {
            array_unshift($classes, $this->sanitizeClassToken($language));
        }

        $attrs = [
            'classes' => array_values(array_filter($classes, static fn (string $class): bool => $class !== '')),
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => 'code',
            ],
            'text' => $source,
        ];
        if ($executionCount !== null) {
            $attrs['attributes']['data-ipynb-execution-count'] = (string) $executionCount;
        }

        return new AstNode('code_block', $attrs);
    }

    private function rawCellBlock(string $source, int $index): AstNode
    {
        return new AstNode('code_block', [
            'classes' => ['ipynb-raw-cell-source'],
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => 'raw',
            ],
            'text' => $source,
        ]);
    }

    private function unsupportedCellBlock(string $source, string $cellType, int $index): AstNode
    {
        return new AstNode('code_block', [
            'classes' => ['ipynb-unsupported-cell-source'],
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => $cellType,
            ],
            'text' => $source,
        ]);
    }

    private function cellType(mixed $cellType): string
    {
        if (!is_string($cellType) || $cellType === '') {
            return 'unknown';
        }

        $normalized = $this->sanitizeClassToken(strtolower($cellType));

        return $normalized === '' ? 'unknown' : $normalized;
    }

    private function cellIdsRequired(mixed $nbformat, mixed $nbformatMinor): bool
    {
        if (!is_int($nbformat)) {
            return false;
        }
        if ($nbformat > 4) {
            return true;
        }

        return $nbformat === 4 && is_int($nbformatMinor) && $nbformatMinor >= 5;
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function cellIdValue(array $cell): ?string
    {
        $id = $cell['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param array<string, mixed> $cell
     * @return list<array<string, mixed>>
     */
    private function cellIdDiagnostics(array $cell, string $cellType, int $index, bool $required): array
    {
        if (!$required) {
            return [];
        }

        if (!array_key_exists('id', $cell)) {
            return [$this->diagnostic('missing-cell-id', $index, $cellType, null)];
        }

        $id = $cell['id'];
        if (!is_string($id) || $id === '') {
            return [$this->diagnostic('invalid-cell-id', $index, $cellType, null, [
                'valueType' => $this->valueKind($id),
            ])];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $cell
     * @return array{present:bool, valid:bool, validInteger:int|null, type:string, diagnostics:list<array<string, mixed>>}
     */
    private function executionCountSummary(array $cell, string $cellType, int $index, ?string $cellId): array
    {
        $present = array_key_exists('execution_count', $cell);
        $diagnostics = [];
        $type = 'missing';
        $valid = false;
        $validInteger = null;

        if (!$present) {
            if ($cellType === 'code') {
                $diagnostics[] = $this->diagnostic('missing-cell-execution-count', $index, $cellType, $cellId);
            }

            return [
                'present' => false,
                'valid' => false,
                'validInteger' => null,
                'type' => $type,
                'diagnostics' => $diagnostics,
            ];
        }

        $value = $cell['execution_count'];
        if ($value === null) {
            $type = 'null';
            $valid = true;
        } elseif (is_int($value)) {
            $type = 'integer';
            if ($value < 0 || $value > self::MAX_EXECUTION_COUNT) {
                $diagnostics[] = $this->diagnostic('cell-execution-count-out-of-range', $index, $cellType, $cellId, [
                    'value' => $value,
                    'min' => 0,
                    'max' => self::MAX_EXECUTION_COUNT,
                ]);
            } else {
                $valid = true;
                $validInteger = $value;
            }
        } else {
            $type = $this->valueKind($value);
            $diagnostics[] = $this->diagnostic('cell-execution-count-invalid-type', $index, $cellType, $cellId, [
                'valueType' => $type,
            ]);
        }

        if ($cellType !== 'code') {
            $diagnostics[] = $this->diagnostic('unexpected-cell-execution-count', $index, $cellType, $cellId, [
                'valueType' => $type,
            ]);
        }

        return [
            'present' => true,
            'valid' => $valid,
            'validInteger' => $validInteger,
            'type' => $type,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     text:string,
     *     shape:string,
     *     partCount:int,
     *     bytes:int,
     *     lineCount:int,
     *     trailingNewline:bool,
     *     lineEnding:string,
     *     lineEndingCount:int,
     *     lineEndingCounts:array{lf:int, crlf:int, cr:int},
     *     hasMixedLineEndings:bool,
     *     contentState:string,
     *     digest:array{algorithm:string, value:string},
     *     fingerprint:string,
     *     diagnostics:list<string>
     * }
     */
    private function sourceReview(mixed $source, bool $sourcePresent, string $label): array
    {
        if (!$sourcePresent) {
            $text = '';
            $shape = 'missing';
            $partCount = 0;
        } elseif ($source === null) {
            $text = '';
            $shape = 'null';
            $partCount = 0;
        } elseif (is_string($source)) {
            $text = $source;
            $shape = 'string';
            $partCount = 1;
        } elseif (is_array($source)) {
            $parts = [];
            foreach ($source as $index => $line) {
                if (!is_string($line)) {
                    throw new \InvalidArgumentException("{$label} entry {$index} is not a string");
                }
                $parts[] = $line;
            }

            $text = implode('', $parts);
            $shape = 'list';
            $partCount = count($parts);
        } else {
            throw new \InvalidArgumentException("{$label} must be a string, string array, null, or missing");
        }

        $lineEndingCounts = $this->lineEndingCounts($text);
        $lineEndingCount = array_sum($lineEndingCounts);
        $lineEnding = $this->lineEndingStyle($lineEndingCounts);
        $bytes = strlen($text);
        $trailingNewline = $text !== '' && (str_ends_with($text, "\n") || str_ends_with($text, "\r"));
        $lineCount = $bytes === 0
            ? 0
            : $lineEndingCount + ($trailingNewline ? 0 : 1);
        $sourceDigest = hash('sha256', $text);
        $contentState = $bytes === 0
            ? 'empty'
            : (trim($text) === '' ? 'whitespace-only' : 'content');
        $hasMixedLineEndings = count(array_filter($lineEndingCounts, static fn (int $count): bool => $count > 0)) > 1;

        $diagnostics = [
            'source-shape:' . $shape,
            'source-parts:' . $partCount,
            'source-bytes:' . $bytes,
            'source-lines:' . $lineCount,
            'source-line-ending:' . $lineEnding,
        ];
        if ($trailingNewline) {
            $diagnostics[] = 'source-trailing-newline';
        }
        if ($bytes === 0) {
            $diagnostics[] = 'source-empty';
        }

        return [
            'text' => $text,
            'shape' => $shape,
            'partCount' => $partCount,
            'bytes' => $bytes,
            'lineCount' => $lineCount,
            'trailingNewline' => $trailingNewline,
            'lineEnding' => $lineEnding,
            'lineEndingCount' => $lineEndingCount,
            'lineEndingCounts' => $lineEndingCounts,
            'hasMixedLineEndings' => $hasMixedLineEndings,
            'contentState' => $contentState,
            'digest' => [
                'algorithm' => 'sha256',
                'value' => $sourceDigest,
            ],
            'fingerprint' => 'sha256:' . $sourceDigest,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{lf:int, crlf:int, cr:int}
     */
    private function lineEndingCounts(string $source): array
    {
        $counts = ['lf' => 0, 'crlf' => 0, 'cr' => 0];
        $length = strlen($source);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = $source[$offset];
            if ($byte === "\r") {
                if ($offset + 1 < $length && $source[$offset + 1] === "\n") {
                    $counts['crlf']++;
                    $offset++;
                } else {
                    $counts['cr']++;
                }
                continue;
            }

            if ($byte === "\n") {
                $counts['lf']++;
            }
        }

        return $counts;
    }

    /**
     * @param array{lf:int, crlf:int, cr:int} $counts
     */
    private function lineEndingStyle(array $counts): string
    {
        $present = array_keys(array_filter($counts, static fn (int $count): bool => $count > 0));

        return match (count($present)) {
            0 => 'none',
            1 => $present[0],
            default => 'mixed',
        };
    }

    /**
     * @param array<string, mixed> $attachments
     * @return array{
     *     count:int,
     *     names:list<string>,
     *     mimeTypes:list<string>,
     *     media:list<array{
     *         cellIndex:int,
     *         name:string,
     *         mimeTypes:list<string>,
     *         primaryMimeType:string,
     *         mediaPath:string,
     *         byteExposure:string,
     *         extractionState:string,
     *         diagnostics:list<string>
     *     }>,
     *     diagnostics:list<string>,
     *     manifestEntries:list<array<string, mixed>>,
     *     manifestDiagnostics:list<string>
     * }
     */
    private function attachmentSummary(array $attachments, int $cellIndex): array
    {
        $names = [];
        $mimeTypes = [];
        $media = [];
        $diagnostics = [];
        $manifestEntries = [];
        $manifestDiagnostics = [];
        $usedMediaPaths = [];
        $attachmentNames = [];
        foreach ($attachments as $name => $_payload) {
            $attachmentNames[] = (string) $name;
        }
        sort($attachmentNames);

        foreach ($attachmentNames as $name) {
            $payload = $attachments[$name] ?? null;
            if (!is_array($payload)) {
                continue;
            }
            $names[] = (string) $name;
            $payloadMimeTypes = $this->attachmentMimeTypes($payload);
            $mimeTypes = array_merge($mimeTypes, $payloadMimeTypes);
            $entryDiagnostics = $this->attachmentNameDiagnostics($name);
            $manifestDiagnostics = array_merge($manifestDiagnostics, $entryDiagnostics);
            $manifestEntries[] = [
                'cellIndex' => $cellIndex,
                'name' => $name,
                'safeName' => $this->attachmentSafeName($name, count($manifestEntries)),
                'mimeTypeCount' => count($payloadMimeTypes),
                'mimeTypes' => $payloadMimeTypes,
                'payloadExposurePolicy' => 'metadata-only-no-payload',
                'diagnostics' => $entryDiagnostics,
            ];
            $mediaPathPlan = $this->attachmentMediaPath($name, $payloadMimeTypes);
            $mediaPath = 'ipynb-media/' . $mediaPathPlan['safeName'];
            $itemDiagnostics = array_merge(['attachment-bytes-blocked'], $mediaPathPlan['diagnostics']);
            if (isset($usedMediaPaths[$mediaPath])) {
                $mediaPath = $this->disambiguateAttachmentMediaPath($mediaPath, $cellIndex, $name, $payloadMimeTypes);
                $itemDiagnostics[] = 'attachment-media-path-collision';
            }
            $usedMediaPaths[$mediaPath] = true;
            $itemDiagnostics = $this->uniqueSortedStrings($itemDiagnostics);
            $diagnostics = array_merge($diagnostics, $itemDiagnostics);

            $media[] = [
                'cellIndex' => $cellIndex,
                'name' => $name,
                'mimeTypes' => $payloadMimeTypes,
                'primaryMimeType' => $payloadMimeTypes[0] ?? $this->mimeTypeFromPath($name),
                'mediaPath' => $mediaPath,
                'byteExposure' => 'blocked',
                'extractionState' => 'planned-metadata-only',
                'diagnostics' => $itemDiagnostics,
            ];
        }
        sort($names);
        sort($mimeTypes);

        return [
            'count' => count($names),
            'names' => $names,
            'mimeTypes' => array_values(array_unique($mimeTypes)),
            'media' => $media,
            'diagnostics' => $this->uniqueSortedStrings($diagnostics),
            'manifestEntries' => $manifestEntries,
            'manifestDiagnostics' => $this->uniqueSortedStrings($manifestDiagnostics),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function attachmentMimeTypes(array $payload): array
    {
        $mimeTypes = [];
        foreach ($payload as $mimeType => $data) {
            if (!is_string($mimeType) || $mimeType === '') {
                continue;
            }
            if (!is_scalar($data) && !$this->isStringList($data)) {
                continue;
            }
            $mimeTypes[] = $mimeType;
        }
        sort($mimeTypes);

        return array_values(array_unique($mimeTypes));
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array{safeName:string, caseFoldKey:string, attachmentCount:int, entries:list<array{cellIndex:int, name:string, safeName:string}>}>
     */
    private function attachmentCollisionGroups(array $entries): array
    {
        $buckets = [];
        foreach ($entries as $entry) {
            $safeName = $entry['safeName'] ?? null;
            if (!is_string($safeName) || $safeName === '') {
                continue;
            }

            $key = strtolower($safeName);
            $buckets[$key][] = [
                'cellIndex' => (int) ($entry['cellIndex'] ?? 0),
                'name' => (string) ($entry['name'] ?? ''),
                'safeName' => $safeName,
            ];
        }

        $groups = [];
        foreach ($buckets as $key => $items) {
            if (count($items) < 2) {
                continue;
            }

            usort($items, static fn (array $left, array $right): int => [$left['cellIndex'], $left['name']] <=> [$right['cellIndex'], $right['name']]);
            $groups[] = [
                'safeName' => $items[0]['safeName'],
                'caseFoldKey' => $key,
                'attachmentCount' => count($items),
                'entries' => $items,
            ];
        }
        usort($groups, static fn (array $left, array $right): int => $left['caseFoldKey'] <=> $right['caseFoldKey']);

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function attachmentNameDiagnostics(string $name): array
    {
        $diagnostics = [];
        if ($name === '') {
            $diagnostics[] = 'ipynb-attachment-empty-name';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            $diagnostics[] = 'ipynb-attachment-control-bytes';
        }
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $name) === 1) {
            $diagnostics[] = 'ipynb-attachment-absolute-path';
        }
        if (str_contains($name, '\\')) {
            $diagnostics[] = 'ipynb-attachment-backslash-path';
        }
        if (str_contains($name, '?') || str_contains($name, '#')) {
            $diagnostics[] = 'ipynb-attachment-query-fragment';
        }

        $segments = preg_split('/[\/\\\\]+/', $name) ?: [];
        if (in_array('..', $segments, true)) {
            $diagnostics[] = 'ipynb-attachment-parent-segment';
        }

        return $this->uniqueSortedStrings($diagnostics);
    }

    private function attachmentSafeName(string $name, int $ordinal): string
    {
        $path = preg_replace('/[?#].*$/', '', str_replace('\\', '/', $name)) ?? $name;
        $segments = explode('/', $path);
        $base = end($segments);
        if (!is_string($base) || $base === '' || $base === '.' || $base === '..') {
            $base = 'attachment-' . ($ordinal + 1);
        }

        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? '';
        $safe = trim($safe, '.-');

        return $safe === '' ? 'attachment-' . ($ordinal + 1) : $safe;
    }

    /**
     * @param array<int, mixed> $outputs
     * @return array{count:int, types:list<string>, orderTypes:list<string>, indexes:list<int>, mimeTypes:list<string>, outputs:list<array<string, mixed>>, unsupportedVerdicts:list<array<string, mixed>>, richUnsupportedCount:int, mimeBundleCount:int, bytePresenceCount:int, repeatedMimeBundleKeys:list<string>, repeatedMimeBundleRecords:list<array<string, mixed>>, aggregateDiagnostics:list<array<string, mixed>>, executionCounts:list<int>, executionCountRecords:list<array<string, mixed>>, executionCountRecordCount:int, executionCountMismatchCount:int, executionDiagnostics:list<array<string, mixed>>, errorNames:list<string>, streamNames:list<string>, diagnostics:list<string>}
     */
    private function outputSummary(array $outputs, ?int $cellExecutionCount, string $cellType, int $cellIndex, ?string $cellId): array
    {
        $types = [];
        $orderTypes = [];
        $indexes = [];
        $mimeTypes = [];
        $mimeOccurrences = [];
        $summaries = [];
        $unsupportedVerdicts = [];
        $mimeBundleCount = 0;
        $bytePresenceCount = 0;
        $executionCounts = [];
        $executionCountRecords = [];
        $executionDiagnostics = [];
        $executionCountMismatchCount = 0;
        $errorNames = [];
        $streamNames = [];
        $diagnostics = [];
        $aggregateDiagnostics = [];
        foreach ($outputs as $index => $output) {
            $outputIndex = is_int($index) ? $index : count($summaries);
            $indexes[] = $outputIndex;
            if (!is_array($output)) {
                $summaries[] = [
                    'index' => $outputIndex,
                    'type' => 'unknown',
                    'outputType' => 'unknown',
                    'diagnostics' => ['ipynb-output-not-object'],
                ];
                continue;
            }
            $type = $output['output_type'] ?? null;
            $outputType = is_string($type) && $type !== '' ? $type : 'unknown';
            $orderTypes[] = $outputType;
            if ($outputType !== 'unknown') {
                $types[] = $outputType;
            }

            $summary = [
                'index' => $outputIndex,
                'type' => $outputType,
                'outputType' => $outputType,
            ];

            $outputMimeTypes = $this->outputMimeTypes($output);
            $outputDiagnostics = [];
            $hasMimeBundle = $outputMimeTypes !== [];
            $hasStreamPayload = $outputType === 'stream' && $this->stringList($output['text'] ?? null) !== [];
            $errorName = is_string($output['ename'] ?? null) && $output['ename'] !== '' ? $output['ename'] : null;
            $hasErrorValue = is_string($output['evalue'] ?? null) && $output['evalue'] !== '';
            $tracebackLines = $this->stringList($output['traceback'] ?? null);
            $hasErrorPayload = $outputType === 'error' && ($errorName !== null || $hasErrorValue || $tracebackLines !== []);
            $hasBytePresence = $hasMimeBundle || $hasStreamPayload || $hasErrorPayload;

            if ($hasBytePresence) {
                $bytePresenceCount++;
                $outputDiagnostics[] = 'output-bytes-blocked';
            }
            if ($hasMimeBundle) {
                $mimeBundleCount++;
                $outputDiagnostics[] = 'output-mime-bundle-metadata-only';
            }
            if ($hasStreamPayload) {
                $outputDiagnostics[] = 'output-stream-bytes-blocked';
            }
            if ($outputType === 'error') {
                $outputDiagnostics[] = 'output-error-metadata-only';
                if ($tracebackLines !== []) {
                    $outputDiagnostics[] = 'output-error-traceback-bytes-blocked';
                }
            }
            foreach ($outputDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }

            if ($outputMimeTypes !== []) {
                $summary['mimeTypes'] = $outputMimeTypes;
                $summary['mimeCount'] = count($outputMimeTypes);
                array_push($mimeTypes, ...$outputMimeTypes);
                foreach ($outputMimeTypes as $mimeType) {
                    $mimeOccurrences[$mimeType] ??= [];
                    $mimeOccurrences[$mimeType][] = $outputIndex;
                }
            }

            if (isset($output['metadata']) && is_array($output['metadata'])) {
                $summary['metadataKeys'] = $this->metadataKeys($output['metadata']);
                $summary['metadataKeyCount'] = count($output['metadata']);
            }
            $summary['bytePresence'] = $hasBytePresence ? 'present' : 'none';
            $summary['byteExposure'] = $hasBytePresence ? 'blocked' : 'none';
            $summary['diagnostics'] = array_values(array_unique($outputDiagnostics));

            if ($outputType === 'stream') {
                $streamName = $output['name'] ?? null;
                if (is_string($streamName) && $streamName !== '') {
                    $summary['streamName'] = $streamName;
                    $streamNames[] = $streamName;
                }
                $summary['textLineCount'] = $this->outputTextLineCount($output['text'] ?? null);
            } elseif ($outputType === 'error') {
                if ($errorName !== null) {
                    $summary['errorName'] = $errorName;
                    $errorNames[] = $errorName;
                }
                $summary['errorValuePresent'] = $hasErrorValue;
                $summary['tracebackLineCount'] = $this->outputTextLineCount($output['traceback'] ?? null);
            }
            if (array_key_exists('execution_count', $output)) {
                $value = $output['execution_count'];
                $record = [
                    'outputIndex' => $outputIndex,
                    'outputType' => $outputType === 'unknown' ? null : $outputType,
                    'valueType' => $this->valueKind($value),
                    'valid' => false,
                    'matchesCell' => null,
                ];

                if (is_int($value) || $value === null) {
                    $summary['executionCount'] = $value;
                }
                if (is_int($value)) {
                    $record['executionCount'] = $value;
                    if ($value < 0 || $value > self::MAX_EXECUTION_COUNT) {
                        $executionDiagnostics[] = $this->diagnostic('output-execution-count-out-of-range', $cellIndex, $cellType, $cellId, [
                            'outputIndex' => $outputIndex,
                            'outputType' => $record['outputType'],
                            'value' => $value,
                            'min' => 0,
                            'max' => self::MAX_EXECUTION_COUNT,
                        ]);
                    } else {
                        $record['valid'] = true;
                        $executionCounts[] = $value;
                        if ($cellExecutionCount !== null) {
                            $record['matchesCell'] = $value === $cellExecutionCount;
                            if ($value !== $cellExecutionCount) {
                                $executionCountMismatchCount++;
                                $executionDiagnostics[] = $this->diagnostic('output-execution-count-mismatch', $cellIndex, $cellType, $cellId, [
                                    'outputIndex' => $outputIndex,
                                    'outputType' => $record['outputType'],
                                    'cellExecutionCount' => $cellExecutionCount,
                                    'outputExecutionCount' => $value,
                                ]);
                            }
                        }
                    }
                } else {
                    $executionDiagnostics[] = $this->diagnostic('output-execution-count-invalid-type', $cellIndex, $cellType, $cellId, [
                        'outputIndex' => $outputIndex,
                        'outputType' => $record['outputType'],
                        'valueType' => $record['valueType'],
                    ]);
                }

                $summary['executionCountRecord'] = $record;
                $executionCountRecords[] = $record;
            } elseif ($outputType === 'execute_result') {
                $executionDiagnostics[] = $this->diagnostic('output-execution-count-missing', $cellIndex, $cellType, $cellId, [
                    'outputIndex' => $outputIndex,
                    'outputType' => $outputType,
                ]);
            }

            if ($this->isRichOutputType($outputType) && $outputMimeTypes !== []) {
                $verdict = [
                    'code' => 'ipynb-rich-output-unsupported',
                    'cellIndex' => $cellIndex,
                    'outputIndex' => $outputIndex,
                    'outputType' => $outputType,
                    'mimeTypes' => $outputMimeTypes,
                    'mimeCount' => count($outputMimeTypes),
                    'reason' => 'rich-output-rendering-not-implemented',
                    'payloadPolicy' => 'metadata-only-no-payload-bytes',
                ];
                $summary['unsupportedVerdict'] = $verdict['code'];
                $unsupportedVerdicts[] = $verdict;
            }

            $summaries[] = $summary;
        }
        $types = array_values(array_unique($types));
        $mimeTypes = array_values(array_unique($mimeTypes));
        sort($mimeTypes);
        $uniqueOrderTypes = array_values(array_unique($orderTypes));
        if (count($orderTypes) > 1 && count($uniqueOrderTypes) > 1) {
            $aggregateDiagnostics[] = $this->diagnostic('mixed-output-display-order', $cellIndex, $cellType, $cellId, [
                'outputIndexes' => $indexes,
                'outputTypes' => $orderTypes,
                'uniqueOutputTypes' => $uniqueOrderTypes,
            ]);
        }
        $repeatedMimeBundleRecords = $this->repeatedMimeBundleRecords($mimeOccurrences);
        foreach ($repeatedMimeBundleRecords as $record) {
            $aggregateDiagnostics[] = $this->diagnostic('repeated-output-mime-bundle-key', $cellIndex, $cellType, $cellId, [
                'mimeType' => $record['mimeType'],
                'outputIndexes' => $record['outputIndexes'],
                'occurrenceCount' => $record['count'],
            ]);
        }

        return [
            'count' => count($outputs),
            'types' => $types,
            'orderTypes' => $orderTypes,
            'indexes' => $indexes,
            'mimeTypes' => $mimeTypes,
            'outputs' => $summaries,
            'unsupportedVerdicts' => $unsupportedVerdicts,
            'richUnsupportedCount' => count($unsupportedVerdicts),
            'mimeBundleCount' => $mimeBundleCount,
            'bytePresenceCount' => $bytePresenceCount,
            'repeatedMimeBundleKeys' => array_column($repeatedMimeBundleRecords, 'mimeType'),
            'repeatedMimeBundleRecords' => $repeatedMimeBundleRecords,
            'aggregateDiagnostics' => $aggregateDiagnostics,
            'executionCounts' => array_values(array_unique($executionCounts)),
            'executionCountRecords' => $executionCountRecords,
            'executionCountRecordCount' => count($executionCountRecords),
            'executionCountMismatchCount' => $executionCountMismatchCount,
            'executionDiagnostics' => $executionDiagnostics,
            'errorNames' => $this->uniqueSortedStrings($errorNames),
            'streamNames' => $this->uniqueSortedStrings($streamNames),
            'diagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @param array<string, list<int>> $mimeOccurrences
     * @return list<array{mimeType:string, outputIndexes:list<int>, count:int}>
     */
    private function repeatedMimeBundleRecords(array $mimeOccurrences): array
    {
        ksort($mimeOccurrences);
        $records = [];
        foreach ($mimeOccurrences as $mimeType => $outputIndexes) {
            if (count($outputIndexes) < 2) {
                continue;
            }
            $records[] = [
                'mimeType' => $mimeType,
                'outputIndexes' => $outputIndexes,
                'count' => count($outputIndexes),
            ];
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $output
     * @return list<string>
     */
    private function outputMimeTypes(array $output): array
    {
        $data = $output['data'] ?? null;
        if (!is_array($data)) {
            return [];
        }

        $mimeTypes = [];
        foreach ($data as $mimeType => $_payload) {
            $mimeType = $this->normalizeMimeType((string) $mimeType);
            if ($mimeType !== '') {
                $mimeTypes[] = $mimeType;
            }
        }
        $mimeTypes = array_values(array_unique($mimeTypes));
        sort($mimeTypes);

        return $mimeTypes;
    }

    private function normalizeMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        if (preg_match('/^[a-z0-9][a-z0-9.+_-]*\/[a-z0-9][a-z0-9.+_-]*(?:\s*;\s*[a-z0-9_.-]+=(?:"[^"]*"|[^;\s]+))*$/', $mimeType) !== 1) {
            return '';
        }

        return $mimeType;
    }

    private function outputTextLineCount(mixed $text): int
    {
        if (is_string($text)) {
            return $text === '' ? 0 : substr_count($text, "\n") + 1;
        }

        if (!is_array($text)) {
            return 0;
        }

        $count = 0;
        foreach ($text as $line) {
            if (is_string($line)) {
                $count++;
            }
        }

        return $count;
    }

    private function isRichOutputType(string $outputType): bool
    {
        return in_array($outputType, ['display_data', 'execute_result'], true);
    }

    /**
     * @param array{count:int, names:list<string>, mimeTypes:list<string>, media:list<array<string, mixed>>, diagnostics:list<string>} $attachmentSummary
     * @param array{count:int, types:list<string>, mimeTypes:list<string>, outputs:list<array<string, mixed>>, unsupportedVerdicts:list<array<string, mixed>>, richUnsupportedCount:int, mimeBundleCount:int, bytePresenceCount:int, diagnostics:list<string>} $outputSummary
     * @return list<string>
     */
    private function cellDiagnostics(array $attachmentSummary, array $outputSummary): array
    {
        $diagnostics = [];
        if ($attachmentSummary['count'] > 0) {
            $diagnostics[] = 'attachment-bytes-blocked';
        }
        foreach ($outputSummary['diagnostics'] as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }

        return array_values(array_unique($diagnostics));
    }

    /**
     * @param array<string, mixed> $cell
     * @return list<array<string, mixed>>
     */
    private function rawMarkdownCellDiagnostics(
        array $cell,
        string $cellType,
        int $index,
        bool $sourcePresent,
        mixed $sourceValue,
        string $source
    ): array {
        if (!in_array($cellType, ['markdown', 'raw'], true)) {
            return [];
        }

        $metadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
        $conversionSupported = $cellType === 'markdown';

        return [[
            'type' => $cellType . '-cell-source-review',
            'scope' => 'cell-source',
            'severity' => 'info',
            'cellIndex' => $index,
            'cellType' => $cellType,
            'sourceShape' => $this->sourceShape($sourcePresent, $sourceValue),
            'sourceBytes' => strlen($source),
            'sourceLineCount' => $this->sourceLineCount($source),
            'byteExposurePolicy' => 'metadata-only',
            'sourcePayloadIncluded' => false,
            'metadataPolicy' => 'keys-only',
            'metadataKeyCount' => count($metadata),
            'unsafeMetadataKeys' => $this->unsafeCellMetadataKeys($metadata),
            'conversionSupported' => $conversionSupported,
            'conversionVerdict' => $conversionSupported
                ? 'parsed-as-native-markdown-blocks'
                : 'unsupported-native-conversion-preserved-as-code-block',
            'externalTooling' => false,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>
     */
    private function rawMarkdownCellReview(int $markdownCellCount, int $rawCellCount, array $diagnostics): array
    {
        return [
            'scope' => 'raw-markdown-cell-source',
            'byteExposurePolicy' => 'metadata-only',
            'checkedCellCount' => $markdownCellCount + $rawCellCount,
            'diagnosticCount' => count($diagnostics),
            'externalTooling' => false,
            'diagnostics' => $diagnostics,
        ];
    }

    private function sourceShape(bool $sourcePresent, mixed $source): string
    {
        if (!$sourcePresent) {
            return 'missing';
        }
        if (is_string($source)) {
            return 'string';
        }
        if (is_array($source)) {
            return array_is_list($source) ? 'string-array' : 'object';
        }

        return $this->jsonValueType($source);
    }

    private function sourceLineCount(string $source): int
    {
        if ($source === '') {
            return 0;
        }

        return substr_count($source, "\n") + 1;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function unsafeCellMetadataKeys(array $metadata): array
    {
        $unsafe = [];
        foreach (array_keys($metadata) as $key) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::UNSAFE_CELL_METADATA_KEYS, true)) {
                $unsafe[] = $normalized;
            }
        }
        $unsafe = array_values(array_unique($unsafe));
        sort($unsafe);

        return $unsafe;
    }

    /**
     * @param array<string, mixed> $notebook
     * @return list<array<string, mixed>>
     */
    private function notebookSchemaDiagnostics(array $notebook, mixed $cells): array
    {
        $diagnostics = [];

        if (!array_key_exists('nbformat', $notebook)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-nbformat', 'notebook', 'nbformat', 'integer 4', 'missing');
        } elseif (!is_int($notebook['nbformat'])) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-nbformat', 'notebook', 'nbformat', 'integer 4', $this->jsonValueType($notebook['nbformat']));
        } elseif ($notebook['nbformat'] !== self::SUPPORTED_NBFORMAT_MAJOR) {
            $diagnostics[] = $this->schemaDiagnostic('unsupported-nbformat', 'notebook', 'nbformat', 'integer 4', 'integer');
        }

        if (!array_key_exists('nbformat_minor', $notebook)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-nbformat-minor', 'notebook', 'nbformat_minor', 'non-negative integer', 'missing');
        } elseif (!is_int($notebook['nbformat_minor']) || $notebook['nbformat_minor'] < 0) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-nbformat-minor', 'notebook', 'nbformat_minor', 'non-negative integer', $this->jsonValueType($notebook['nbformat_minor']));
        } elseif (($notebook['nbformat'] ?? null) === self::SUPPORTED_NBFORMAT_MAJOR && $notebook['nbformat_minor'] > self::SUPPORTED_NBFORMAT_MINOR) {
            $diagnostics[] = $this->schemaDiagnostic('future-nbformat-minor', 'notebook', 'nbformat_minor', 'minor version <= 5', 'integer');
        }

        if (array_key_exists('metadata', $notebook) && !is_array($notebook['metadata'])) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-notebook-metadata', 'notebook', 'metadata', 'object', $this->jsonValueType($notebook['metadata']));
        }

        if (!array_key_exists('cells', $notebook)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-cells-array', 'notebook', 'cells', 'array', 'missing');
        } elseif (!is_array($cells)) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cells-array', 'notebook', 'cells', 'array', $this->jsonValueType($cells));
        } elseif (!array_is_list($cells)) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cells-shape', 'notebook', 'cells', 'array', 'object');
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $cell
     * @return list<array<string, mixed>>
     */
    private function cellSchemaDiagnostics(array $cell, string $cellType, int $index): array
    {
        $diagnostics = [];

        if (!array_key_exists('cell_type', $cell)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-cell-type', 'cell', 'cell_type', 'markdown, code, or raw', 'missing', $index);
        } elseif (!is_string($cell['cell_type']) || $cell['cell_type'] === '') {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cell-type', 'cell', 'cell_type', 'non-empty string', $this->jsonValueType($cell['cell_type']), $index);
        } elseif (!in_array($cellType, ['markdown', 'code', 'raw'], true)) {
            $diagnostics[] = $this->schemaDiagnostic('unsupported-cell-type', 'cell', 'cell_type', 'markdown, code, or raw', 'string', $index);
        }

        if (!array_key_exists('metadata', $cell)) {
            $diagnostics[] = $this->schemaDiagnostic('missing-cell-metadata', 'cell', 'metadata', 'object', 'missing', $index);
        } elseif (!is_array($cell['metadata'])) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cell-metadata', 'cell', 'metadata', 'object', $this->jsonValueType($cell['metadata']), $index);
        }

        if (array_key_exists('id', $cell) && (!is_string($cell['id']) || $cell['id'] === '')) {
            $diagnostics[] = $this->schemaDiagnostic('invalid-cell-id', 'cell', 'id', 'non-empty string', $this->jsonValueType($cell['id']), $index);
        }

        if (array_key_exists('attachments', $cell)) {
            if (!is_array($cell['attachments'])) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-cell-attachments', 'cell', 'attachments', 'object', $this->jsonValueType($cell['attachments']), $index);
            } elseif ($cell['attachments'] !== [] && array_is_list($cell['attachments'])) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-cell-attachments', 'cell', 'attachments', 'object', 'array', $index);
            }
        }

        if ($cellType === 'code') {
            if (!array_key_exists('execution_count', $cell)) {
                $diagnostics[] = $this->schemaDiagnostic('missing-code-execution-count', 'cell', 'execution_count', 'integer or null', 'missing', $index);
            } elseif (!is_int($cell['execution_count']) && $cell['execution_count'] !== null) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-code-execution-count', 'cell', 'execution_count', 'integer or null', $this->jsonValueType($cell['execution_count']), $index);
            }

            if (!array_key_exists('outputs', $cell)) {
                $diagnostics[] = $this->schemaDiagnostic('missing-code-outputs', 'cell', 'outputs', 'array', 'missing', $index);
            } elseif (!is_array($cell['outputs'])) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-code-outputs', 'cell', 'outputs', 'array', $this->jsonValueType($cell['outputs']), $index);
            } elseif (!array_is_list($cell['outputs'])) {
                $diagnostics[] = $this->schemaDiagnostic('invalid-code-outputs', 'cell', 'outputs', 'array', 'object', $index);
            }
        } elseif (array_key_exists('outputs', $cell)) {
            $diagnostics[] = $this->schemaDiagnostic('unexpected-cell-outputs', 'cell', 'outputs', 'absent on non-code cells', $this->jsonValueType($cell['outputs']), $index);
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $notebook
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>
     */
    private function notebookSchemaReview(
        array $notebook,
        int $cellCount,
        int $notebookDiagnosticCount,
        int $cellDiagnosticCount,
        array $diagnostics
    ): array {
        $review = [
            'schema' => 'nbformat-v4-bounded',
            'byteExposurePolicy' => 'metadata-only',
            'checkedCellCount' => $cellCount,
            'diagnosticCount' => count($diagnostics),
            'notebookDiagnosticCount' => $notebookDiagnosticCount,
            'cellDiagnosticCount' => $cellDiagnosticCount,
            'diagnostics' => $diagnostics,
        ];

        if (array_key_exists('nbformat', $notebook) && is_int($notebook['nbformat'])) {
            $review['nbformat'] = $notebook['nbformat'];
        }
        if (array_key_exists('nbformat_minor', $notebook) && is_int($notebook['nbformat_minor'])) {
            $review['nbformatMinor'] = $notebook['nbformat_minor'];
        }

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaDiagnostic(
        string $type,
        string $scope,
        string $field,
        string $expected,
        string $actual,
        ?int $cellIndex = null
    ): array {
        $diagnostic = [
            'type' => $type,
            'scope' => $scope,
            'field' => $field,
            'severity' => 'warning',
            'expected' => $expected,
            'actual' => $actual,
        ];

        if ($cellIndex !== null) {
            $diagnostic['cellIndex'] = $cellIndex;
        }

        return $diagnostic;
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnostic(string $issue, int $cellIndex, string $cellType, ?string $cellId, array $extra = []): array
    {
        $diagnostic = [
            'issue' => $issue,
            'cellIndex' => $cellIndex,
            'cellType' => $cellType,
        ];
        if ($cellId !== null) {
            $diagnostic['cellId'] = $cellId;
        }

        return $diagnostic + $extra;
    }

    private function valueKind(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_array($value)) {
            return 'array';
        }

        return get_debug_type($value);
    }

    private function jsonValueType(mixed $value): string
    {
        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_string($value)) {
            return 'string';
        }

        return get_debug_type($value);
    }

    /**
     * @param mixed $metadata
     */
    private function metadataString(mixed $metadata, string $key): ?string
    {
        if (!is_array($metadata)) {
            return null;
        }

        $value = $metadata[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param list<string> $mimeTypes
     * @return array{safeName:string, diagnostics:list<string>}
     */
    private function attachmentMediaPath(string $name, array $mimeTypes): array
    {
        $diagnostics = [];
        $path = $name;
        if ($path === '') {
            $diagnostics[] = 'attachment-empty-name';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            $diagnostics[] = 'attachment-control-byte-name';
        }
        if (str_contains($path, '\\')) {
            $diagnostics[] = 'attachment-backslash-path';
        }

        $normalized = str_replace('\\', '/', $path);
        if ($this->isUri($normalized)) {
            $diagnostics[] = 'attachment-uri-name';
            $uriPath = parse_url($normalized, PHP_URL_PATH);
            $normalized = is_string($uriPath) && $uriPath !== '' ? $uriPath : $normalized;
        }
        if (str_contains($normalized, '?') || str_contains($normalized, '#')) {
            $diagnostics[] = 'attachment-query-or-fragment';
            $normalized = strtok($normalized, '?#') ?: $normalized;
        }
        if (str_starts_with($normalized, '/') || str_starts_with($normalized, '//') || preg_match('/\A[A-Za-z]:\//', $normalized) === 1) {
            $diagnostics[] = 'attachment-absolute-path';
        }
        if (str_contains($normalized, '%')) {
            $diagnostics[] = 'attachment-percent-encoded-path';
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                $diagnostics[] = 'attachment-path-traversal';
                continue;
            }
            $segments[] = $segment;
        }

        $requiresOpaqueName = array_diff($diagnostics, ['attachment-safe-path-remapped']) !== [];
        if ($requiresOpaqueName) {
            return [
                'safeName' => 'attachment-' . substr(sha1($name), 0, 12) . $this->attachmentExtension($name, $mimeTypes[0] ?? ''),
                'diagnostics' => $this->uniqueSortedStrings(array_merge($diagnostics, ['attachment-safe-path-remapped'])),
            ];
        }

        $safeSegments = [];
        foreach ($segments as $segment) {
            $safeSegment = preg_replace('/[^A-Za-z0-9._-]+/', '-', $segment) ?? '';
            $safeSegment = trim($safeSegment, '-');
            if ($safeSegment === '' || $safeSegment === '.' || $safeSegment === '..') {
                $safeSegment = 'attachment';
            }
            if ($safeSegment !== $segment) {
                $diagnostics[] = 'attachment-safe-path-remapped';
            }
            $safeSegments[] = $safeSegment;
        }

        $safeName = implode('/', $safeSegments);
        if ($safeName === '') {
            $safeName = 'attachment-' . substr(sha1($name), 0, 12) . $this->attachmentExtension($name, $mimeTypes[0] ?? '');
            $diagnostics[] = 'attachment-safe-path-remapped';
        }

        return [
            'safeName' => $safeName,
            'diagnostics' => $this->uniqueSortedStrings($diagnostics),
        ];
    }

    /**
     * @param list<string> $mimeTypes
     */
    private function disambiguateAttachmentMediaPath(string $mediaPath, int $cellIndex, string $name, array $mimeTypes): string
    {
        $extension = $this->pathExtension($mediaPath);
        $stem = $extension === '' ? $mediaPath : substr($mediaPath, 0, -strlen($extension));
        $suffix = substr(sha1($cellIndex . "\0" . $name . "\0" . implode("\0", $mimeTypes)), 0, 12);

        return $stem . '-' . $suffix . $extension;
    }

    private function attachmentExtension(string $name, string $mimeType): string
    {
        $extension = $this->pathExtension(strtok(str_replace('\\', '/', $name), '?#') ?: $name);
        if ($extension !== '' && !str_contains($extension, '%')) {
            return $extension;
        }

        return match (strtolower($mimeType)) {
            'image/apng' => '.apng',
            'image/avif' => '.avif',
            'image/gif' => '.gif',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/svg+xml' => '.svg',
            'image/webp' => '.webp',
            'text/html' => '.html',
            'text/plain' => '.txt',
            'application/json' => '.json',
            'application/pdf' => '.pdf',
            default => '',
        };
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower($this->pathExtension($path))) {
            '.apng' => 'image/apng',
            '.avif' => 'image/avif',
            '.gif' => 'image/gif',
            '.jpeg', '.jpg', '.jpe' => 'image/jpeg',
            '.png' => 'image/png',
            '.svg', '.svgz' => 'image/svg+xml',
            '.webp' => 'image/webp',
            '.html', '.htm' => 'text/html',
            '.txt', '.text' => 'text/plain',
            '.json' => 'application/json',
            '.pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function pathExtension(string $path): string
    {
        $basename = basename($path);
        $position = strrpos($basename, '.');
        if ($position === false || $position === 0) {
            return '';
        }

        return substr($basename, $position);
    }

    private function isUri(string $source): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1
            && preg_match('/\A[A-Za-z]:\//', $source) !== 1;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function metadataKeys(array $metadata): array
    {
        $keys = [];
        foreach ($metadata as $key => $_value) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @return list<string>
     */
    private function metadataStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }
        sort($strings);

        return array_values(array_unique($strings));
    }

    private function isStringList(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $strings
     * @return list<string>
     */
    private function uniqueSortedStrings(array $strings): array
    {
        $strings = array_values(array_unique(array_filter($strings, static fn (string $string): bool => $string !== '')));
        sort($strings);

        return $strings;
    }

    private function sanitizeClassToken(string $token): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?? '';
    }
}
