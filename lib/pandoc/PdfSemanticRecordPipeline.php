<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use RuntimeException;

/** Run and audit an ordered set of document-level PDF semantic processors. */
final class PdfSemanticRecordPipeline
{
    /** @var list<PdfSemanticRecordProcessor> */
    private readonly array $processors;

    /** @param list<PdfSemanticRecordProcessor> $processors */
    public function __construct(array $processors)
    {
        $names = [];
        foreach ($processors as $processor) {
            if (!$processor instanceof PdfSemanticRecordProcessor) {
                throw new RuntimeException('PDF semantic pipelines require PdfSemanticRecordProcessor values.');
            }
            if (isset($names[$processor->name()])) {
                throw new RuntimeException('PDF semantic processor names must be unique within one pipeline.');
            }
            $names[$processor->name()] = true;
        }
        $this->processors = array_values($processors);
    }

    /**
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @return array{
     *   records:list<array{text:string,layout:array<string,mixed>|null}>,
     *   trace:list<array<string,mixed>>
     * }
     */
    public function run(array $records): array
    {
        return $this->runOwned($records);
    }

    /**
     * Run the pipeline while transferring the caller's record ownership into
     * the result. The input variable is emptied before this method returns.
     *
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @return array{
     *   records:list<array{text:string,layout:array<string,mixed>|null}>,
     *   trace:list<array<string,mixed>>
     * }
     */
    public function runOwned(array &$records): array
    {
        $owned = $records;
        $records = [];
        $trace = [];
        try {
            foreach ($this->processors as $processor) {
                $before = $this->projection($owned);
                $processed = $processor->process($owned);
                // Returning from a processor transfers ownership to its output.
                // Release the complete prior graph before validation: retaining
                // both proof-heavy documents at this boundary is itself an
                // unbounded memory failure. A processor which throws before
                // returning still leaves $owned available to the catch below.
                $owned = [];
                $this->assertValidRecords($processed, $processor->name());
                $this->normalizeRecordsInPlace($processed);
                $owned = $processed;
                unset($processed);
                $after = $this->projection($owned);
                $trace[] = [
                    'processor' => $processor->name(),
                    'inputRecords' => $before['records'],
                    'outputRecords' => $after['records'],
                    'recordDelta' => $after['records'] - $before['records'],
                    'inputTextBytes' => $before['bytes'],
                    'outputTextBytes' => $after['bytes'],
                    'textByteDelta' => $after['bytes'] - $before['bytes'],
                    'changed' => $before['digest'] !== $after['digest'],
                    'inputDigest' => $before['digest'],
                    'outputDigest' => $after['digest'],
                ];
            }
        } catch (\Throwable $error) {
            $records = $owned;
            throw $error;
        }

        $result = ['records' => $owned, 'trace' => $trace];
        $owned = [];

        return $result;
    }

    /**
     * @param array<mixed> $records
     */
    private function assertValidRecords(array $records, string $processor): void
    {
        foreach ($records as $record) {
            if (!is_array($record)
                || !is_string($record['text'] ?? null)
                || (($record['layout'] ?? null) !== null && !is_array($record['layout']))) {
                throw new RuntimeException("PDF semantic processor {$processor} returned an invalid record.");
            }
            $hasOutputPage = array_key_exists('sourcePdfOutputPage', $record);
            if ($hasOutputPage) {
                $outputPage = $record['sourcePdfOutputPage'];
                if ($outputPage !== null && (!is_int($outputPage) || $outputPage < 1)) {
                    throw new RuntimeException(
                        "PDF semantic processor {$processor} returned an invalid output page."
                    );
                }
            }
        }
    }

    /** @param array<mixed> $records */
    private function normalizeRecordsInPlace(array &$records): void
    {
        foreach ($records as $index => $record) {
            $hasOutputPage = array_key_exists('sourcePdfOutputPage', $record);

            // A conforming processor record is already the normalized value.
            // Do not rebuild it: even one wrapper write can trigger a copy of
            // a proof-heavy layout while the processor result owns the only
            // remaining document graph. Non-canonical/private output remains
            // rare and is normalized one record at a time.
            $canonical = true;
            $keyIndex = 0;
            foreach ($record as $key => $_value) {
                $expectedKey = match ($keyIndex) {
                    0 => 'text',
                    1 => 'layout',
                    2 => $hasOutputPage ? 'sourcePdfOutputPage' : null,
                    default => null,
                };
                if ($key !== $expectedKey) {
                    $canonical = false;
                    break;
                }
                $keyIndex++;
            }
            $expectedKeyCount = $hasOutputPage ? 3 : 2;
            if ($canonical && $keyIndex === $expectedKeyCount) {
                continue;
            }

            $normalizedRecord = [
                'text' => $record['text'],
                'layout' => $record['layout'] ?? null,
            ];
            if ($hasOutputPage) {
                $normalizedRecord['sourcePdfOutputPage'] = $record['sourcePdfOutputPage'];
            }
            $records[$index] = $normalizedRecord;
        }
        if (!array_is_list($records)) {
            $records = array_values($records);
        }
    }

    /** @param list<array{text:string,layout:array<string,mixed>|null}> $records @return array{records:int,bytes:int,digest:string} */
    private function projection(array $records): array
    {
        $hash = hash_init('sha256');
        $bytes = 0;
        foreach ($records as $record) {
            $text = is_string($record['text'] ?? null) ? $record['text'] : '';
            $bytes += strlen($text);
            hash_update($hash, pack('N', strlen($text)) . $text);
            $layout = json_encode(
                is_array($record['layout'] ?? null) ? $record['layout'] : null,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
            $layout = is_string($layout) ? $layout : 'null';
            hash_update($hash, pack('N', strlen($layout)) . $layout);
        }

        return ['records' => count($records), 'bytes' => $bytes, 'digest' => hash_final($hash)];
    }
}
