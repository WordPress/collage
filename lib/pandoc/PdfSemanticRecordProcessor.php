<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * One deterministic document-level transform over PDF text records.
 *
 * Implementations may classify, reorder, merge, or conservatively suppress
 * records, but they must remain independent of WordPress serialization. The
 * pipeline records each processor boundary so fidelity regressions can be
 * attributed to the transform which introduced them.
 */
interface PdfSemanticRecordProcessor
{
    public function name(): string;

    /**
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @return list<array{text:string,layout:array<string,mixed>|null}>
     */
    public function process(array $records): array;
}
