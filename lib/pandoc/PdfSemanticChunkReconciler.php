<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use Generator;
use PortLibs\MarkerPDF\PdfDocumentFacts;
use PortLibs\MarkerPDF\PdfPageFacts;
use RuntimeException;

/**
 * Turn arbitrary bounded extraction ranges into deterministic semantic passes.
 *
 * PDF extraction and PDF interpretation have different useful chunk sizes. A
 * resumable importer may collect one or two pages per request, while the
 * document reader needs enough adjacent pages to distinguish page furniture,
 * continued lists, columns, captions, and prose repairs. Feeding extraction
 * ranges directly to PdfReader therefore makes the AST depend on the request
 * size.
 *
 * This reconciler verifies the durable source/page provenance, buffers a fixed
 * number of pages, and gives every PdfReader pass the same immutable complete
 * document profile. The semantic partition is consequently independent of the
 * extraction partition. `semanticDocuments()` is streaming so a caller can
 * persist each resulting AST part before requesting the next one; `reconcile()`
 * is the convenience form for callers which need one in-memory document.
 */
final class PdfSemanticChunkReconciler
{
    public const DEFAULT_SEMANTIC_WINDOW_PAGES = 8;
    public const SEMANTIC_OVERLAP_PAGES = 1;
    public const MAX_EXTRACTION_RANGE_PAGES = 32;

    /** @var array<string, mixed> */
    private array $stats = [];

    /**
     * @param array<string, mixed> $readerOptions
     */
    public function __construct(
        private readonly array $readerOptions = [],
        private readonly int $semanticWindowPages = self::DEFAULT_SEMANTIC_WINDOW_PAGES
    ) {
        if ($semanticWindowPages < 2 || $semanticWindowPages > 32) {
            throw new RuntimeException('PDF semantic windows must contain between 2 and 32 pages.');
        }
    }

    /**
     * Reconcile a complete ordered stream of bounded facts ranges.
     *
     * The input iterable is intentionally consumed lazily. Production callers
     * can deserialize one persisted range at a time instead of constructing a
     * complete PdfDocumentFacts object.
     *
     * @param iterable<PdfDocumentFacts> $factRanges
     */
    public function reconcile(string $pdfBytes, iterable $factRanges): AstNode
    {
        $blocks = [];
        $semanticDocuments = 0;
        $familiesById = [];
        $continuationsById = [];
        $sourceEdgesById = [];
        $sourceEdgeMappingComplete = true;
        foreach ($this->semanticDocuments($pdfBytes, $factRanges) as $document) {
            array_push($blocks, ...$document->children());
            $documentMetadata = $document->attr('meta', []);
            if (is_array($documentMetadata)) {
                $documentEdges = is_array($documentMetadata['pdfSemanticSourceEdges'] ?? null)
                    ? $documentMetadata['pdfSemanticSourceEdges']
                    : [];
                if (($documentMetadata['pdfSemanticSourceEdgeMappingComplete'] ?? null) !== true
                    || count($documentEdges) !== count($document->children())) {
                    $sourceEdgeMappingComplete = false;
                }
                foreach ($documentEdges as $edge) {
                    if (!is_array($edge) || !is_string($edge['id'] ?? null) || $edge['id'] === '') {
                        $sourceEdgeMappingComplete = false;
                        continue;
                    }
                    if (isset($sourceEdgesById[$edge['id']]) && $sourceEdgesById[$edge['id']] !== $edge) {
                        throw new RuntimeException('PDF semantic reconciliation found conflicting source-edge identities.');
                    }
                    $sourceEdgesById[$edge['id']] ??= $edge;
                }
                foreach (is_array($documentMetadata['pdfLogicalTableFamilies'] ?? null)
                    ? $documentMetadata['pdfLogicalTableFamilies']
                    : [] as $family) {
                    if (!is_array($family) || !is_string($family['id'] ?? null) || $family['id'] === '') {
                        continue;
                    }
                    $familiesById[$family['id']] = $this->unionLogicalTableFamily(
                        $familiesById[$family['id']] ?? null,
                        $family
                    );
                }
                foreach (is_array($documentMetadata['pdfTableContinuations'] ?? null)
                    ? $documentMetadata['pdfTableContinuations']
                    : [] as $continuation) {
                    if (!is_array($continuation)
                        || !is_string($continuation['id'] ?? null)
                        || $continuation['id'] === '') {
                        continue;
                    }
                    $continuationsById[$continuation['id']] = $this->unionTableContinuation(
                        $continuationsById[$continuation['id']] ?? null,
                        $continuation
                    );
                }
            }
            $semanticDocuments++;
        }

        $families = array_values($familiesById);
        usort($families, static fn (array $left, array $right): int =>
            ((int) ($left['firstPage'] ?? 0) <=> (int) ($right['firstPage'] ?? 0))
                ?: ((string) ($left['id'] ?? '') <=> (string) ($right['id'] ?? ''))
        );
        $continuations = array_values($continuationsById);
        usort($continuations, static fn (array $left, array $right): int =>
            ((int) ($left['firstPage'] ?? 0) <=> (int) ($right['firstPage'] ?? 0))
                ?: ((string) ($left['id'] ?? '') <=> (string) ($right['id'] ?? ''))
        );
        $tableCounts = $this->logicalTableCounts($blocks);
        $this->stats['logicalTableFamilies'] = count($families);
        $this->stats['logicalTables'] = $tableCounts['logical'];
        $this->stats['physicalTables'] = $tableCounts['physical'];
        $sourceEdges = array_values($sourceEdgesById);
        unset($sourceEdgesById);
        $sourceEdgeDigest = $this->jsonListDigest(
            $sourceEdges,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        $sourceEdgeMappingComplete = $sourceEdgeMappingComplete && count($sourceEdges) === count($blocks);
        $this->stats['sourceEdgeCount'] = count($sourceEdges);
        $this->stats['sourceEdgeDigest'] = $sourceEdgeDigest;
        $this->stats['sourceEdgeMappingComplete'] = $sourceEdgeMappingComplete;

        $metadata = [
            'pdfDetectedTables' => $tableCounts['physical'],
            'pdfLogicalTableCount' => $tableCounts['logical'],
            'pdfLogicalTableFamilies' => $families,
            'pdfLogicalTableFamilyCount' => count($families),
            'pdfLogicalTableInstanceCount' => array_sum(array_map(
                static fn (array $family): int => count(is_array($family['instances'] ?? null) ? $family['instances'] : []),
                $families
            )),
            'pdfLogicalTableFamilyPhysicalParts' => array_sum(array_map(
                static fn (array $family): int => max(0, (int) ($family['physicalParts'] ?? 0)),
                $families
            )),
            'pdfTableContinuations' => $continuations,
            'pdfSemanticSourceEdges' => $sourceEdges,
            'pdfSemanticSourceEdgeCount' => count($sourceEdges),
            'pdfSemanticSourceEdgeDigest' => $sourceEdgeDigest,
            'pdfSemanticSourceEdgeMappingComplete' => $sourceEdgeMappingComplete,
            'pdfSemanticChunkReconciliation' => array_replace($this->stats, [
                'semanticDocuments' => $semanticDocuments,
            ]),
        ];

        return new AstNode('document', ['meta' => $metadata], $blocks);
    }

    /**
     * Yield deterministic, bounded PdfReader results suitable for incremental
     * publication. Each yielded document owns one disjoint source-page range.
     *
     * @param iterable<PdfDocumentFacts> $factRanges
     * @return Generator<int, AstNode>
     */
    public function semanticDocuments(string $pdfBytes, iterable $factRanges): Generator
    {
        $this->stats = [
            'schemaVersion' => 2,
            'semanticWindowPages' => $this->semanticWindowPages,
            'semanticOverlapPages' => self::SEMANTIC_OVERLAP_PAGES,
            'maxBufferedPages' => 0,
            'maxSemanticContextPages' => 0,
            'maxResidentPageFacts' => 0,
            'maxInputRangePages' => 0,
            'semanticPasses' => 0,
            'semanticReaderPasses' => 0,
            'processedPages' => 0,
            'sourceEdgeMappingFallbacks' => 0,
            'pageProvenanceDigest' => null,
            'loadedWholeDocument' => false,
        ];

        /** @var list<array{page:PdfPageFacts,providerParts:list<string>}> $pendingPageRecords */
        $pendingPageRecords = [];
        /** @var list<array{page:PdfPageFacts,providerParts:list<string>}> $previousContextRecords */
        $previousContextRecords = [];
        $windowStartPage = 1;
        $nextPage = 1;
        $totalPages = null;
        $source = null;
        $profile = null;
        $profileDigest = null;
        $structure = [];
        $diagnostics = [];
        $unassignedAnnotations = [];
        $pageProvenance = hash_init('sha256');
        $sawRange = false;
        /** @var array<string,array{edge:array<string,mixed>,blockDigest:string}> $seenSourceEdges */
        $seenSourceEdges = [];
        /** @var array<string,list<array{startByte:int,endByte:int,edgeId:string}>> $seenSourceSpans */
        $seenSourceSpans = [];

        foreach ($factRanges as $range) {
            if (!$range instanceof PdfDocumentFacts) {
                throw new RuntimeException('PDF semantic reconciliation requires PdfDocumentFacts ranges.');
            }
            $sawRange = true;
            $rangePages = $range->pages();
            $rangePageCount = count($rangePages);
            if ($rangePageCount < 1) {
                throw new RuntimeException('PDF semantic reconciliation received an empty facts range.');
            }
            if ($rangePageCount > self::MAX_EXTRACTION_RANGE_PAGES) {
                throw new RuntimeException(sprintf(
                    'PDF extraction ranges must remain bounded to at most %d pages before semantic reconciliation.',
                    self::MAX_EXTRACTION_RANGE_PAGES
                ));
            }
            $this->stats['maxInputRangePages'] = max(
                (int) $this->stats['maxInputRangePages'],
                $rangePageCount
            );
            // `pages()` materializes the caller's already-bounded extraction
            // range. Account for that range together with semantic carry-over
            // instead of reporting only the smaller processing queue.
            $this->stats['maxResidentPageFacts'] = max(
                (int) $this->stats['maxResidentPageFacts'],
                count($previousContextRecords) + count($pendingPageRecords) + $rangePageCount
            );

            $rangeSource = $range->source();
            $rangeInventory = $range->inventory();
            $rangeTotalPages = max(0, (int) ($rangeInventory['totalPages'] ?? 0));
            $rangeProfile = $range->structure()['documentProfile'] ?? null;
            if (!is_array($rangeProfile)
                || ($rangeProfile['complete'] ?? null) !== true
                || !is_string($rangeProfile['profileDigest'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $rangeProfile['profileDigest']) !== 1) {
                throw new RuntimeException('PDF semantic reconciliation requires one complete immutable document profile.');
            }

            if ($source === null) {
                $rangeData = $range->toArray();
                $source = $rangeSource;
                $totalPages = $rangeTotalPages;
                $profile = $rangeProfile;
                $profileDigest = $rangeProfile['profileDigest'];
                $structure = $range->structure();
                $diagnostics = $range->diagnostics();
                $unassignedAnnotations = is_array($rangeData['unassignedAnnotations'] ?? null)
                    ? $rangeData['unassignedAnnotations']
                    : [];

                if ($totalPages < 1
                    || (int) ($profile['totalPages'] ?? 0) !== $totalPages
                    || !is_string($source['sha256'] ?? null)
                    || !hash_equals(hash('sha256', $pdfBytes), $source['sha256'])
                    || (int) ($source['byteLength'] ?? -1) !== strlen($pdfBytes)
                    || !hash_equals((string) ($profile['sourceSha256'] ?? ''), $source['sha256'])) {
                    throw new RuntimeException('PDF semantic reconciliation source, inventory, and profile evidence did not agree.');
                }
                $this->stats['sourceSha256'] = $source['sha256'];
                $this->stats['profileDigest'] = $profileDigest;
                $this->stats['totalPages'] = $totalPages;
                unset($rangeData);
            } elseif ($rangeSource !== $source
                || $rangeTotalPages !== $totalPages
                || $rangeProfile !== $profile
                || !hash_equals((string) $profileDigest, (string) $rangeProfile['profileDigest'])) {
                throw new RuntimeException('PDF semantic reconciliation ranges did not share immutable source/profile evidence.');
            }

            $declaredPageNumbers = array_values(array_map(
                static fn (mixed $page): int => (int) $page,
                is_array($rangeInventory['pageNumbers'] ?? null) ? $rangeInventory['pageNumbers'] : []
            ));
            $actualPageNumbers = array_map(
                static fn (PdfPageFacts $page): int => $page->pageNumber(),
                $rangePages
            );
            if ($declaredPageNumbers !== $actualPageNumbers
                || (int) ($rangeInventory['startPage'] ?? 0) !== $actualPageNumbers[0]
                || (int) ($rangeInventory['endPage'] ?? 0) !== $actualPageNumbers[$rangePageCount - 1]) {
                throw new RuntimeException('PDF semantic reconciliation range inventory did not match its page facts.');
            }

            foreach ($rangePages as $page) {
                if ($page->pageNumber() !== $nextPage) {
                    throw new RuntimeException(sprintf(
                        'PDF semantic reconciliation expected page %d but received page %d.',
                        $nextPage,
                        $page->pageNumber()
                    ));
                }
                $this->appendVerifiedPageProvenance(
                    $pageProvenance,
                    $page,
                    (string) $source['sha256']
                );
                $providerParts = array_values(array_filter(
                    explode('+', $range->provider()),
                    static fn (string $providerPart): bool => $providerPart !== ''
                ));
                $pendingPageRecords[] = [
                    'page' => $page,
                    'providerParts' => $providerParts,
                ];
                $this->stats['maxBufferedPages'] = max(
                    (int) $this->stats['maxBufferedPages'],
                    min(count($pendingPageRecords), $this->semanticWindowPages)
                );
                $this->stats['maxResidentPageFacts'] = max(
                    (int) $this->stats['maxResidentPageFacts'],
                    count($previousContextRecords) + count($pendingPageRecords)
                );
                $nextPage++;

                // Keep one page of look-ahead before finalizing the owned
                // window. This is enough to expose a relation crossing the
                // boundary while resident facts stay bounded to window + 2
                // (one previous and one following context page).
                if (count($pendingPageRecords) <= $this->semanticWindowPages) {
                    continue;
                }

                $ownedRecords = array_slice($pendingPageRecords, 0, $this->semanticWindowPages);
                $followingContextRecords = array_slice(
                    $pendingPageRecords,
                    $this->semanticWindowPages,
                    self::SEMANTIC_OVERLAP_PAGES
                );
                $windowEndPage = $windowStartPage + count($ownedRecords) - 1;
                $document = $this->readOwnedSemanticWindow(
                    $pdfBytes,
                    $source,
                    (int) $totalPages,
                    $windowStartPage,
                    $windowEndPage,
                    $previousContextRecords,
                    $ownedRecords,
                    $followingContextRecords,
                    $structure,
                    $profile,
                    $diagnostics,
                    $unassignedAnnotations,
                    $seenSourceEdges,
                    $seenSourceSpans
                );
                $this->stats['semanticPasses']++;
                $this->stats['processedPages'] += count($ownedRecords);
                $previousContextRecords = array_slice(
                    $ownedRecords,
                    -self::SEMANTIC_OVERLAP_PAGES
                );
                $pendingPageRecords = array_slice(
                    $pendingPageRecords,
                    count($ownedRecords)
                );
                $windowStartPage = $windowEndPage + 1;
                $this->retainSemanticOverlapState(
                    $seenSourceEdges,
                    $seenSourceSpans,
                    $windowEndPage
                );
                yield $document;
            }
            unset($rangePages);
        }

        if (!$sawRange || $source === null || $profile === null || $totalPages === null) {
            throw new RuntimeException('PDF semantic reconciliation received no facts ranges.');
        }
        if ($nextPage !== $totalPages + 1) {
            throw new RuntimeException(sprintf(
                'PDF semantic reconciliation stopped at page %d of %d.',
                $nextPage - 1,
                $totalPages
            ));
        }
        if ($pendingPageRecords !== []) {
            $ownedRecords = $pendingPageRecords;
            $windowEndPage = $windowStartPage + count($ownedRecords) - 1;
            $document = $this->readOwnedSemanticWindow(
                $pdfBytes,
                $source,
                $totalPages,
                $windowStartPage,
                $windowEndPage,
                $previousContextRecords,
                $ownedRecords,
                [],
                $structure,
                $profile,
                $diagnostics,
                $unassignedAnnotations,
                $seenSourceEdges,
                $seenSourceSpans
            );
            $this->stats['semanticPasses']++;
            $this->stats['processedPages'] += count($ownedRecords);
            $pendingPageRecords = [];
            yield $document;
        }

        $this->stats['pageProvenanceDigest'] = hash_final($pageProvenance);
        $this->stats['loadedWholeDocument'] = $totalPages <= $this->semanticWindowPages;
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * @param resource|\HashContext $digest
     */
    private function appendVerifiedPageProvenance(
        mixed $digest,
        PdfPageFacts $page,
        string $sourceSha256
    ): void {
        $pageNumber = $page->pageNumber();
        $pageObject = $page->pageObject();
        $lineIds = [];
        $seenLineIds = [];
        foreach ($page->text()['lines'] ?? [] as $line) {
            if (!is_array($line)
                || !is_string($line['id'] ?? null)
                || $line['id'] === ''
                || (int) ($line['page'] ?? 0) !== $pageNumber) {
                throw new RuntimeException('PDF semantic reconciliation found a text line without stable page provenance.');
            }
            $provenance = is_array($line['provenance'] ?? null) ? $line['provenance'] : [];
            if ((int) ($provenance['page'] ?? 0) !== $pageNumber
                || ($pageObject !== null
                    && isset($provenance['pageObject'])
                    && (int) $provenance['pageObject'] !== $pageObject)) {
                throw new RuntimeException('PDF semantic reconciliation found contradictory line/page provenance.');
            }
            if (isset($seenLineIds[$line['id']])) {
                throw new RuntimeException('PDF semantic reconciliation found duplicate stable line provenance on one page.');
            }
            $seenLineIds[$line['id']] = true;
            $lineIds[] = $line['id'];
        }

        $payload = json_encode([
            'sourceSha256' => $sourceSha256,
            'pageNumber' => $pageNumber,
            'pageObject' => $pageObject,
            'lineIds' => $lineIds,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        hash_update($digest, is_string($payload) ? $payload : serialize($lineIds));
    }

    /**
     * Read one disjoint owned window with one immutable facts page on either
     * side. Blocks are retained only through exact source-line spans; a
     * repeated spelling can never deduplicate an occurrence from another
     * page.
     *
     * @param array<string,mixed> $source
     * @param list<array{page:PdfPageFacts,providerParts:list<string>}> $previousContextRecords
     * @param list<array{page:PdfPageFacts,providerParts:list<string>}> $ownedRecords
     * @param list<array{page:PdfPageFacts,providerParts:list<string>}> $followingContextRecords
     * @param array<string,mixed> $structure
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $diagnostics
     * @param array<string,list<array<string,mixed>>> $unassignedAnnotations
     * @param array<string,array{edge:array<string,mixed>,blockDigest:string}> $seenSourceEdges
     * @param array<string,list<array{startByte:int,endByte:int,edgeId:string}>> $seenSourceSpans
     */
    private function readOwnedSemanticWindow(
        string $pdfBytes,
        array $source,
        int $totalPages,
        int $ownedStartPage,
        int $ownedEndPage,
        array $previousContextRecords,
        array $ownedRecords,
        array $followingContextRecords,
        array $structure,
        array $profile,
        array $diagnostics,
        array $unassignedAnnotations,
        array &$seenSourceEdges,
        array &$seenSourceSpans
    ): AstNode {
        $contextRecords = [
            ...$previousContextRecords,
            ...$ownedRecords,
            ...$followingContextRecords,
        ];
        $contextPages = array_map(
            static fn (array $record): PdfPageFacts => $record['page'],
            $contextRecords
        );
        $contextStartPage = $contextPages[0]->pageNumber();
        $contextEndPage = $contextPages[array_key_last($contextPages)]->pageNumber();
        $this->stats['maxSemanticContextPages'] = max(
            (int) $this->stats['maxSemanticContextPages'],
            count($contextPages)
        );

        $contextDocument = $this->readSemanticWindow(
            $pdfBytes,
            $this->providerForPageRecords($contextRecords),
            $source,
            $totalPages,
            $contextStartPage,
            $contextEndPage,
            $contextPages,
            $structure,
            $profile,
            $diagnostics,
            $unassignedAnnotations
        );
        $mapping = $this->mapDocumentBlocksToStableSourceEdges(
            $contextDocument,
            $contextPages,
            (string) ($source['sha256'] ?? '')
        );

        if ($mapping === null) {
            // Do not guess ownership from similar text. Fall back to the old
            // disjoint semantic read whenever PdfReader's exact node/line
            // mapping is absent, malformed, ambiguous, or contradicts the
            // immutable facts in this context.
            $this->stats['sourceEdgeMappingFallbacks']++;
            $ownedPages = array_map(
                static fn (array $record): PdfPageFacts => $record['page'],
                $ownedRecords
            );
            $ownedDocument = $contextStartPage === $ownedStartPage && $contextEndPage === $ownedEndPage
                ? $contextDocument
                : $this->readSemanticWindow(
                    $pdfBytes,
                    $this->providerForPageRecords($ownedRecords),
                    $source,
                    $totalPages,
                    $ownedStartPage,
                    $ownedEndPage,
                    $ownedPages,
                    $structure,
                    $profile,
                    $diagnostics,
                    $unassignedAnnotations
                );
            $ownedMapping = $this->mapDocumentBlocksToStableSourceEdges(
                $ownedDocument,
                $ownedPages,
                (string) ($source['sha256'] ?? '')
            );
            if ($ownedMapping === null) {
                return $this->semanticDocumentWithSourceEdges(
                    $ownedDocument,
                    $ownedDocument->children(),
                    [],
                    $ownedStartPage,
                    $ownedEndPage,
                    $ownedStartPage,
                    $ownedEndPage,
                    false
                );
            }

            return $this->selectOwnedSourceEdges(
                $ownedDocument,
                $ownedMapping,
                $ownedStartPage,
                $ownedEndPage,
                $ownedStartPage,
                $ownedEndPage,
                $seenSourceEdges,
                $seenSourceSpans
            );
        }

        return $this->selectOwnedSourceEdges(
            $contextDocument,
            $mapping,
            $ownedStartPage,
            $ownedEndPage,
            $contextStartPage,
            $contextEndPage,
            $seenSourceEdges,
            $seenSourceSpans
        );
    }

    /**
     * @param list<array{page:PdfPageFacts,providerParts:list<string>}> $records
     */
    private function providerForPageRecords(array $records): string
    {
        $parts = [];
        foreach ($records as $record) {
            foreach ($record['providerParts'] as $part) {
                $parts[$part] = true;
            }
        }

        return implode('+', array_keys($parts));
    }

    /**
     * Bind every top-level AST block through PdfReader's exact source edges.
     * The reader may have proved a bounded layout reorder, so ownership cannot
     * be reconstructed by requiring the final block stream to equal source
     * order. Instead, verify the public node/line/byte identities against the
     * immutable page facts and the reader's independently digested source-edge
     * graph. Any partial, ambiguous, or contradictory mapping declines overlap
     * reconciliation and leaves the caller on its disjoint fail-closed pass.
     *
     * @param list<PdfPageFacts> $pages
     * @return array{blocks:list<AstNode>,edges:list<array<string,mixed>>}|null
     */
    private function mapDocumentBlocksToStableSourceEdges(
        AstNode $document,
        array $pages,
        string $sourceSha256
    ): ?array {
        $metadata = $document->attr('meta', []);
        $disposition = is_array($metadata) && is_array($metadata['pdfSourceDisposition'] ?? null)
            ? $metadata['pdfSourceDisposition']
            : [];
        if (!is_array($metadata)
            || ($metadata['pdfSemanticTextComplete'] ?? null) !== true
            || ($disposition['sourceEdgeMappingComplete'] ?? null) !== true
            || ($disposition['orderedSignificantCharactersPreserved'] ?? null) !== true
            || preg_match('/^[a-f0-9]{64}$/D', $sourceSha256) !== 1) {
            return null;
        }

        /** @var array<string,array{page:int,significantBytes:int}> $sourceLinesById */
        $sourceLinesById = [];
        foreach ($pages as $page) {
            if (!$page instanceof PdfPageFacts) {
                return null;
            }
            $pageNumber = $page->pageNumber();
            $pageObject = $page->pageObject();
            $pageLines = $page->text()['lines'] ?? null;
            if (!is_array($pageLines) || !array_is_list($pageLines)) {
                return null;
            }
            foreach ($pageLines as $line) {
                if (!is_array($line)) {
                    return null;
                }
                $lineProvenance = is_array($line['provenance'] ?? null)
                    ? $line['provenance']
                    : null;
                if (!is_string($line['id'] ?? null)
                    || $line['id'] === ''
                    || isset($sourceLinesById[$line['id']])
                    || !is_int($line['page'] ?? null)
                    || $line['page'] !== $pageNumber
                    || !is_array($lineProvenance)
                    || !is_int($lineProvenance['page'] ?? null)
                    || $lineProvenance['page'] !== $pageNumber
                    || ($pageObject !== null
                        && isset($lineProvenance['pageObject'])
                        && (!is_int($lineProvenance['pageObject'])
                            || $lineProvenance['pageObject'] !== $pageObject))
                    || !is_string($line['text'] ?? null)) {
                    return null;
                }
                $sourceLinesById[$line['id']] = [
                    'page' => $page->pageNumber(),
                    'significantBytes' => strlen($this->semanticSignificantText($line['text'])),
                ];
            }
        }
        $readerDestinations = $this->validatedReaderSourceDestinations(
            $disposition,
            $sourceLinesById
        );
        if ($readerDestinations === null) {
            return null;
        }

        $blocks = $document->children();
        $edges = [];
        $nodeIds = [];
        $nodeIdsBySourceLine = [];
        /** @var array<string,list<array{startByte:int,endByte:int,nodeId:string}>> $spansBySourceLine */
        $spansBySourceLine = [];
        foreach ($blocks as $block) {
            $sourceNodeId = $block->attr('sourceNodeId');
            $sourceLineIds = $this->validatedStableStringList($block->attr('sourceLineIds'));
            $sourceLineEdges = $block->attr('sourceLineEdges');
            if (!is_string($sourceNodeId)
                || preg_match('/^pdf-source-node-[a-f0-9]{32}$/D', $sourceNodeId) !== 1
                || isset($nodeIds[$sourceNodeId])
                || $sourceLineIds === null
                || $sourceLineIds === []
                || !is_array($sourceLineEdges)
                || !array_is_list($sourceLineEdges)
                || $sourceLineEdges === []) {
                return null;
            }
            $nodeIds[$sourceNodeId] = true;
            $spans = [];
            $edgeLineIds = [];
            $pagesByNumber = [];
            foreach ($sourceLineEdges as $sourceLineEdge) {
                if (!is_array($sourceLineEdge)
                    || array_keys($sourceLineEdge) !== ['sourceLineId', 'startByte', 'endByte']
                    || !is_string($sourceLineEdge['sourceLineId'] ?? null)
                    || !is_int($sourceLineEdge['startByte'] ?? null)
                    || !is_int($sourceLineEdge['endByte'] ?? null)) {
                    return null;
                }
                $sourceLineId = $sourceLineEdge['sourceLineId'];
                $sourceLine = $sourceLinesById[$sourceLineId] ?? null;
                $startByte = $sourceLineEdge['startByte'];
                $endByte = $sourceLineEdge['endByte'];
                if (!is_array($sourceLine)
                    || $startByte < 0
                    || $endByte <= $startByte
                    || $endByte > $sourceLine['significantBytes']) {
                    return null;
                }
                $spans[] = [
                    'sourceLineId' => $sourceLineId,
                    'page' => $sourceLine['page'],
                    'startByte' => $startByte,
                    'endByte' => $endByte,
                ];
                if (!isset($edgeLineIds[$sourceLineId])) {
                    $edgeLineIds[$sourceLineId] = true;
                }
                $pagesByNumber[$sourceLine['page']] = true;
                $spansBySourceLine[$sourceLineId][] = [
                    'startByte' => $startByte,
                    'endByte' => $endByte,
                    'nodeId' => $sourceNodeId,
                ];
            }
            if (array_keys($edgeLineIds) !== $sourceLineIds || $pagesByNumber === []) {
                return null;
            }
            $nodeIdentity = json_encode(
                ['type' => $block->type, 'sourceLineEdges' => $sourceLineEdges],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $expectedNodeId = 'pdf-source-node-' . substr(hash(
                'sha256',
                is_string($nodeIdentity) ? $nodeIdentity : serialize($sourceLineEdges)
            ), 0, 32);
            if (!hash_equals($expectedNodeId, $sourceNodeId)) {
                return null;
            }
            foreach ($sourceLineIds as $sourceLineId) {
                $nodeIdsBySourceLine[$sourceLineId][] = $sourceNodeId;
            }
            $edgeIdentity = json_encode([
                'sourceSha256' => $sourceSha256,
                'spans' => $spans,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $edgePages = array_map('intval', array_keys($pagesByNumber));
            sort($edgePages, SORT_NUMERIC);
            $edges[] = [
                'id' => 'pdf-source-edge-' . substr(hash(
                    'sha256',
                    is_string($edgeIdentity) ? $edgeIdentity : serialize($spans)
                ), 0, 32),
                'sourceNodeId' => $sourceNodeId,
                'sourceLineIds' => $sourceLineIds,
                'sourceSpans' => $spans,
                'pages' => $edgePages,
                'firstPage' => $edgePages[0],
                'lastPage' => $edgePages[array_key_last($edgePages)],
                'blockType' => $block->type,
            ];
        }

        foreach ($spansBySourceLine as $sourceSpans) {
            usort($sourceSpans, static fn (array $left, array $right): int =>
                ($left['startByte'] <=> $right['startByte'])
                    ?: ($left['endByte'] <=> $right['endByte'])
                    ?: ($left['nodeId'] <=> $right['nodeId'])
            );
            $previousEnd = -1;
            foreach ($sourceSpans as $sourceSpan) {
                if ($sourceSpan['startByte'] < $previousEnd) {
                    return null;
                }
                $previousEnd = $sourceSpan['endByte'];
            }
        }
        foreach ($readerDestinations as $sourceLineId => $readerDestination) {
            $mappedNodeIds = $nodeIdsBySourceLine[$sourceLineId] ?? [];
            if ($readerDestination['target'] === 'output') {
                if ($readerDestination['destinationNodeIds'] !== $mappedNodeIds) {
                    return null;
                }
            } elseif ($mappedNodeIds !== []) {
                return null;
            }
        }
        foreach ($nodeIdsBySourceLine as $sourceLineId => $_mappedNodeIds) {
            if (!isset($readerDestinations[$sourceLineId])) {
                return null;
            }
        }

        return ['blocks' => $blocks, 'edges' => $edges];
    }

    /**
     * @param array<string,mixed> $disposition
     * @param array<string,array{page:int,significantBytes:int}> $sourceLinesById
     * @return array<string,array{target:string,destinationNodeIds:list<string>}>|null
     */
    private function validatedReaderSourceDestinations(
        array $disposition,
        array $sourceLinesById
    ): ?array {
        $sourceEdges = $disposition['sourceEdges'] ?? null;
        $sourceEdgeDigest = $disposition['sourceEdgeDigest'] ?? null;
        if (!is_array($sourceEdges)
            || !array_is_list($sourceEdges)
            || !is_int($disposition['sourceEdgeCount'] ?? null)
            || $disposition['sourceEdgeCount'] !== count($sourceEdges)
            || !is_string($sourceEdgeDigest)
            || preg_match('/^[a-f0-9]{64}$/D', $sourceEdgeDigest) !== 1) {
            return null;
        }
        $expectedDigest = $this->jsonListDigest(
            $sourceEdges,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!hash_equals($expectedDigest, $sourceEdgeDigest)) {
            return null;
        }

        $destinations = [];
        $edgeIds = [];
        foreach ($sourceEdges as $sourceEdge) {
            if (!is_array($sourceEdge)
                || array_keys($sourceEdge) !== [
                    'id',
                    'sourceOccurrenceId',
                    'page',
                    'disposition',
                    'target',
                    'mappingMode',
                    'destinationNodeIds',
                    'destinationInlineIds',
                    'orderScopeId',
                ]
                || !is_string($sourceEdge['id'] ?? null)
                || preg_match('/^pdf-source-edge-[a-f0-9]{32}$/D', $sourceEdge['id']) !== 1
                || isset($edgeIds[$sourceEdge['id']])
                || !is_string($sourceEdge['sourceOccurrenceId'] ?? null)
                || $sourceEdge['sourceOccurrenceId'] === ''
                || isset($destinations[$sourceEdge['sourceOccurrenceId']])
                || !is_int($sourceEdge['page'] ?? null)
                || !is_string($sourceEdge['disposition'] ?? null)
                || $sourceEdge['disposition'] === ''
                || !is_string($sourceEdge['mappingMode'] ?? null)
                || $sourceEdge['mappingMode'] === ''
                || (!is_string($sourceEdge['orderScopeId'] ?? null)
                    && ($sourceEdge['orderScopeId'] ?? null) !== null)
                || (is_string($sourceEdge['orderScopeId'] ?? null)
                    && $sourceEdge['orderScopeId'] === '')) {
                return null;
            }
            $sourceLineId = $sourceEdge['sourceOccurrenceId'];
            $sourceLine = $sourceLinesById[$sourceLineId] ?? null;
            $destinationNodeIds = $this->validatedStableStringList(
                $sourceEdge['destinationNodeIds'] ?? null,
                true
            );
            $destinationInlineIds = $this->validatedStableStringList(
                $sourceEdge['destinationInlineIds'] ?? null,
                true
            );
            $target = $sourceEdge['target'] ?? null;
            if (!is_array($sourceLine)
                || $sourceEdge['page'] !== $sourceLine['page']
                || $destinationNodeIds === null
                || $destinationInlineIds === null
                || !in_array($target, ['output', 'disposition'], true)
                || ($target === 'output' && $destinationNodeIds === [])
                || ($target === 'disposition' && (
                    $destinationNodeIds !== [] || $destinationInlineIds !== []
                ))) {
                return null;
            }
            $identity = [
                'sourceOccurrenceId' => $sourceLineId,
                'page' => $sourceEdge['page'],
                'disposition' => $sourceEdge['disposition'],
                'target' => $target,
                'mappingMode' => $sourceEdge['mappingMode'],
                'destinationNodeIds' => $destinationNodeIds,
                'destinationInlineIds' => $destinationInlineIds,
                'orderScopeId' => $sourceEdge['orderScopeId'],
            ];
            $encodedIdentity = json_encode(
                $identity,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $expectedEdgeId = 'pdf-source-edge-' . substr(hash(
                'sha256',
                is_string($encodedIdentity) ? $encodedIdentity : serialize($identity)
            ), 0, 32);
            if (!hash_equals($expectedEdgeId, $sourceEdge['id'])) {
                return null;
            }
            $edgeIds[$sourceEdge['id']] = true;
            $destinations[$sourceLineId] = [
                'target' => $target,
                'destinationNodeIds' => $destinationNodeIds,
            ];
        }

        return $destinations;
    }

    /** @return list<string>|null */
    private function validatedStableStringList(mixed $value, bool $allowEmpty = false): ?array
    {
        if (!is_array($value) || !array_is_list($value) || (!$allowEmpty && $value === [])) {
            return null;
        }
        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '' || isset($strings[$item])) {
                return null;
            }
            $strings[$item] = true;
        }

        // The caller already supplied a canonical list. Reusing it after the
        // full uniqueness/type check keeps large reader destination vectors
        // shared through copy-on-write rather than allocating an identical
        // retained list for semantic reconciliation.
        return $value;
    }

    /** @param list<mixed> $values */
    private function jsonListDigest(array $values, int $flags): string
    {
        $digest = hash_init('sha256');
        hash_update($digest, '[');
        foreach ($values as $index => $value) {
            $encoded = json_encode($value, $flags);
            if (!is_string($encoded)) {
                return hash('sha256', serialize($values));
            }
            if ($index > 0) {
                hash_update($digest, ',');
            }
            hash_update($digest, $encoded);
        }
        hash_update($digest, ']');

        return hash_final($digest);
    }

    private function semanticSignificantText(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $text) ?? $text;
    }

    /**
     * @param array{blocks:list<AstNode>,edges:list<array<string,mixed>>} $mapping
     * @param array<string,array{edge:array<string,mixed>,blockDigest:string}> $seenSourceEdges
     * @param array<string,list<array{startByte:int,endByte:int,edgeId:string}>> $seenSourceSpans
     */
    private function selectOwnedSourceEdges(
        AstNode $document,
        array $mapping,
        int $ownedStartPage,
        int $ownedEndPage,
        int $contextStartPage,
        int $contextEndPage,
        array &$seenSourceEdges,
        array &$seenSourceSpans
    ): AstNode {
        $ownedBlocks = [];
        $ownedEdges = [];
        foreach ($mapping['blocks'] as $index => $block) {
            $edge = $mapping['edges'][$index];
            $blockDigest = $this->semanticAstNodeDigest($block);
            $edgeId = (string) $edge['id'];
            if (isset($seenSourceEdges[$edgeId])) {
                if ($seenSourceEdges[$edgeId]['edge'] !== $edge
                    || !hash_equals($seenSourceEdges[$edgeId]['blockDigest'], $blockDigest)) {
                    throw new RuntimeException('PDF semantic overlap produced conflicting output for one stable source edge.');
                }
                continue;
            }
            foreach ($edge['sourceSpans'] as $span) {
                $sourceLineId = (string) ($span['sourceLineId'] ?? '');
                $sourceSpanKey = (int) ($span['page'] ?? 0) . "\0" . $sourceLineId;
                $startByte = (int) ($span['startByte'] ?? -1);
                $endByte = (int) ($span['endByte'] ?? -1);
                foreach ($seenSourceSpans[$sourceSpanKey] ?? [] as $seenSpan) {
                    if ($startByte < $seenSpan['endByte'] && $endByte > $seenSpan['startByte']) {
                        throw new RuntimeException(
                            'PDF semantic overlap changed the block boundary for one stable source span.'
                        );
                    }
                }
            }
            $ownerPage = (int) ($edge['firstPage'] ?? 0);
            if ($ownerPage < $ownedStartPage || $ownerPage > $ownedEndPage) {
                continue;
            }
            $seenSourceEdges[$edgeId] = [
                'edge' => $edge,
                'blockDigest' => $blockDigest,
            ];
            foreach ($edge['sourceSpans'] as $span) {
                $sourceLineId = (string) $span['sourceLineId'];
                $sourceSpanKey = (int) ($span['page'] ?? 0) . "\0" . $sourceLineId;
                $seenSourceSpans[$sourceSpanKey][] = [
                    'startByte' => (int) $span['startByte'],
                    'endByte' => (int) $span['endByte'],
                    'edgeId' => $edgeId,
                ];
            }
            $ownedBlocks[] = $block;
            $ownedEdges[] = $edge;
        }

        return $this->semanticDocumentWithSourceEdges(
            $document,
            $ownedBlocks,
            $ownedEdges,
            $ownedStartPage,
            $ownedEndPage,
            $contextStartPage,
            $contextEndPage,
            count($ownedBlocks) === count($ownedEdges)
        );
    }

    /**
     * Only the final owned page can appear in the next one-page look-behind.
     * Discard older edge/span state so `semanticDocuments()` stays bounded
     * even when the caller streams thousands of extraction ranges.
     *
     * @param array<string,array{edge:array<string,mixed>,blockDigest:string}> $seenSourceEdges
     * @param array<string,list<array{startByte:int,endByte:int,edgeId:string}>> $seenSourceSpans
     */
    private function retainSemanticOverlapState(
        array &$seenSourceEdges,
        array &$seenSourceSpans,
        int $overlapPage
    ): void {
        foreach ($seenSourceEdges as $edgeId => $seen) {
            if ((int) ($seen['edge']['lastPage'] ?? 0) < $overlapPage) {
                unset($seenSourceEdges[$edgeId]);
            }
        }
        $seenSourceSpans = [];
        foreach ($seenSourceEdges as $edgeId => $seen) {
            foreach (is_array($seen['edge']['sourceSpans'] ?? null) ? $seen['edge']['sourceSpans'] : [] as $span) {
                if (!is_array($span)) {
                    continue;
                }
                $sourceLineId = (string) ($span['sourceLineId'] ?? '');
                $sourceSpanKey = (int) ($span['page'] ?? 0) . "\0" . $sourceLineId;
                $seenSourceSpans[$sourceSpanKey][] = [
                    'startByte' => (int) ($span['startByte'] ?? 0),
                    'endByte' => (int) ($span['endByte'] ?? 0),
                    'edgeId' => $edgeId,
                ];
            }
        }
    }

    private function semanticAstNodeDigest(AstNode $node): string
    {
        $encode = function (AstNode $current, bool $topLevel = false) use (&$encode): array {
            $attrs = $current->baseAttrs();
            if (!$topLevel) {
                // Inline source IDs include the reader-local AST path. The
                // exact descendant line edges remain stable, while a context
                // window can legitimately shift that path. Top-level source
                // IDs are edge-derived and are verified separately above.
                unset($attrs['sourceNodeId']);
            }
            // These table-cell fields are Reader-local proof coordinates.
            // Stable sourceLineIds/sourceLineEdges carry the public immutable
            // identity across semantic windows, whose lookbehind necessarily
            // changes local occurrence and order indexes.
            unset(
                $attrs['sourcePdfExactSourceRanges'],
                $attrs['sourcePdfSourceOrderStart'],
                $attrs['sourcePdfSourceOrderEnd']
            );
            $sort = function (mixed $value) use (&$sort): mixed {
                if (!is_array($value)) {
                    return $value;
                }
                if (!array_is_list($value)) {
                    ksort($value, SORT_STRING);
                }
                foreach ($value as $key => $item) {
                    $value[$key] = $sort($item);
                }

                return $value;
            };

            return [
                'type' => $current->type,
                'attrs' => $sort($attrs),
                'children' => array_map(
                    static fn (AstNode $child): array => $encode($child, false),
                    $current->children()
                ),
            ];
        };
        $payload = serialize($encode($node, true));

        return hash('sha256', $payload);
    }

    /**
     * @param list<AstNode> $blocks
     * @param list<array<string,mixed>> $sourceEdges
     */
    private function semanticDocumentWithSourceEdges(
        AstNode $document,
        array $blocks,
        array $sourceEdges,
        int $ownedStartPage,
        int $ownedEndPage,
        int $contextStartPage,
        int $contextEndPage,
        bool $mappingComplete
    ): AstNode {
        $metadata = $document->attr('meta', []);
        $metadata = is_array($metadata) ? $metadata : [];
        $metadata['pdfSemanticSourceEdges'] = $sourceEdges;
        $metadata['pdfSemanticSourceEdgeCount'] = count($sourceEdges);
        $metadata['pdfSemanticSourceEdgeMappingComplete'] = $mappingComplete;
        $metadata['pdfSemanticWindowOwnership'] = [
            'ownedStartPage' => $ownedStartPage,
            'ownedEndPage' => $ownedEndPage,
            'contextStartPage' => $contextStartPage,
            'contextEndPage' => $contextEndPage,
            'overlapBeforePages' => max(0, $ownedStartPage - $contextStartPage),
            'overlapAfterPages' => max(0, $contextEndPage - $ownedEndPage),
        ];

        return new AstNode('document', ['meta' => $metadata], $blocks);
    }

    /**
     * @param array<string, mixed> $source
     * @param list<PdfPageFacts> $pages
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $diagnostics
     * @param array<string, list<array<string, mixed>>> $unassignedAnnotations
     */
    private function readSemanticWindow(
        string $pdfBytes,
        string $provider,
        array $source,
        int $totalPages,
        int $startPage,
        int $endPage,
        array $pages,
        array $structure,
        array $profile,
        array $diagnostics,
        array $unassignedAnnotations
    ): AstNode {
        $this->stats['semanticReaderPasses'] = (int) ($this->stats['semanticReaderPasses'] ?? 0) + 1;
        $structure['documentProfile'] = $profile;
        $facts = new PdfDocumentFacts(
            $provider,
            $source,
            [
                'totalPages' => $totalPages,
                'startPage' => $startPage,
                'endPage' => $endPage,
                'pageNumbers' => range($startPage, $endPage),
                'hasMorePages' => $endPage < $totalPages,
                'nextPage' => $endPage < $totalPages ? $endPage + 1 : null,
            ],
            $pages,
            $structure,
            $diagnostics,
            $unassignedAnnotations
        );

        return (new PdfReader(array_replace(
            $this->readerOptions,
            ['pdfDocumentFacts' => $facts]
        )))->read($pdfBytes);
    }

    /**
     * Metadata is keyed by deterministic public IDs before it is combined.
     * This keeps retries or future overlapping semantic reads from duplicating
     * one family while preserving a stable ordered instance inventory.
     *
     * @param array<string,mixed>|null $existing
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private function unionLogicalTableFamily(?array $existing, array $incoming): array
    {
        if ($existing === null) {
            return $incoming;
        }
        $pages = array_values(array_unique(array_map(
            static fn (mixed $page): int => (int) $page,
            [
                ...(is_array($existing['pages'] ?? null) ? $existing['pages'] : []),
                ...(is_array($incoming['pages'] ?? null) ? $incoming['pages'] : []),
            ]
        )));
        sort($pages, SORT_NUMERIC);
        $instancesById = [];
        foreach ([
            ...(is_array($existing['instances'] ?? null) ? $existing['instances'] : []),
            ...(is_array($incoming['instances'] ?? null) ? $incoming['instances'] : []),
        ] as $instance) {
            if (!is_array($instance) || !is_string($instance['id'] ?? null) || $instance['id'] === '') {
                continue;
            }
            $instancesById[$instance['id']] ??= $instance;
        }
        $instances = array_values($instancesById);
        usort($instances, static fn (array $left, array $right): int =>
            ((int) ($left['page'] ?? 0) <=> (int) ($right['page'] ?? 0))
                ?: ((string) ($left['id'] ?? '') <=> (string) ($right['id'] ?? ''))
        );
        $minimumOverlap = min(
            (float) ($existing['minimumOverlap'] ?? 1.0),
            (float) ($incoming['minimumOverlap'] ?? 1.0)
        );

        return array_replace($existing, [
            'pages' => $pages,
            'firstPage' => $pages === [] ? null : $pages[0],
            'lastPage' => $pages === [] ? null : $pages[array_key_last($pages)],
            'instances' => $instances,
            'instanceCount' => count($instances),
            'physicalParts' => array_sum(array_map(
                static fn (array $instance): int => max(0, (int) ($instance['physicalParts'] ?? 0)),
                $instances
            )),
            'minimumOverlap' => $minimumOverlap,
        ]);
    }

    /**
     * @param array<string,mixed>|null $existing
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private function unionTableContinuation(?array $existing, array $incoming): array
    {
        if ($existing === null) {
            return $incoming;
        }
        $pages = array_values(array_unique(array_map(
            static fn (mixed $page): int => (int) $page,
            [
                ...(is_array($existing['pages'] ?? null) ? $existing['pages'] : []),
                ...(is_array($incoming['pages'] ?? null) ? $incoming['pages'] : []),
            ]
        )));
        sort($pages, SORT_NUMERIC);

        return array_replace($existing, [
            'pages' => $pages,
            'physicalParts' => count($pages),
            'firstPage' => $pages === [] ? null : $pages[0],
            'lastPage' => $pages === [] ? null : $pages[array_key_last($pages)],
        ]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return array{physical:int,logical:int}
     */
    private function logicalTableCounts(array $nodes): array
    {
        $physical = 0;
        $standalone = 0;
        $logicalIds = [];
        $visit = function (array $children) use (&$visit, &$physical, &$standalone, &$logicalIds): void {
            foreach ($children as $node) {
                if (!$node instanceof AstNode) {
                    continue;
                }
                if ($node->type === 'table') {
                    $physical++;
                    $familyId = $node->attr('pdfLogicalTableFamilyId');
                    $continuationId = $node->attr('pdfLogicalTableId');
                    if (is_string($familyId) && $familyId !== '') {
                        $logicalIds['family:' . $familyId] = true;
                    } elseif (is_string($continuationId) && $continuationId !== '') {
                        $logicalIds['continuation:' . $continuationId] = true;
                    } else {
                        $standalone++;
                    }
                }
                $visit($node->children());
            }
        };
        $visit($nodes);

        return [
            'physical' => $physical,
            'logical' => count($logicalIds) + $standalone,
        ];
    }
}
