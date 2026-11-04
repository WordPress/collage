<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocMediaExtractor
{
    private const MAX_PACKAGE_MEDIA_BYTES = 33554432;
    private const MAX_PDF_SCAN_BYTES = 67108864;
    private const MAX_PDF_IMAGE_BYTES = 33554432;
    private const MAX_PDF_RASTER_IMAGE_BYTES = 16777216;
    private const MAX_PDF_RASTER_IMAGE_PIXELS = 48000000;
    private const MAX_PDF_UNIFORM_RASTER_DECODE_BYTES = 8388608;
    private const MAX_PDF_PAGE_RASTER_IMAGES = 96;
    private const MAX_PDF_PAGE_RASTER_TOTAL_BYTES = 67108864;
    private const MAX_PDF_PAGE_RASTER_DIMENSION = 8192;
    private const MAX_PDF_PAGE_RASTER_PIXELS = 16000000;
    private const MAX_PDF_PAGE_RASTER_FALLBACK_SOURCE_BYTES = 25165824;
    private const MAX_PDF_PAGE_RASTER_FALLBACK_ERROR_BYTES = 2048;
    private const MAX_PDF_PAGE_IMAGE_PIXELS = 8000000;
    private const MAX_PDF_IMAGES = 96;
    private const MAX_PDF_IMAGE_PLACEMENT_CANDIDATES = 384;
    private const MAX_PDF_IMAGE_PLACEMENT_CANDIDATES_PER_PAGE = 64;
    private const MAX_PDF_IMAGE_PLACEMENTS = 96;
    private const MAX_PDF_IMAGE_PLACEMENTS_PER_PAGE = 16;
    private const MAX_PDF_CLIPPED_ARTIFACT_MEDIA_ANCHOR_PROOFS = 64;

    /** @var array<string,string> */
    private array $sanitizedPageJpegs = [];

    /**
     * @param array<string, mixed> $options
     * @return array{
     *     document:AstNode,
     *     entries:list<array{path:string, mediaPath:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, pathRepairSummary:string, extractionPathRepairSummary:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, linkedMimeGroup?:string, linkedMimeGroupSize?:int}>,
     *     diagnostics:list<string>
     * }
     */
    public function extract(AstNode $document, string $bytes, string $format, array $options, ?EpubArchive $epubArchive = null): array
    {
        $this->sanitizedPageJpegs = [];
        $destination = (string) ($options['destination'] ?? $options['extractMedia'] ?? $options['extract-media'] ?? 'media');
        $sourcePath = isset($options['sourcePath']) && is_string($options['sourcePath']) ? $options['sourcePath'] : null;
        $imageMode = $this->normalizeImageMode($options['imageMode'] ?? $options['image-mode'] ?? $options['pdfImageMode'] ?? $options['pdf-image-mode'] ?? 'all');
        $pdfRasterImages = $this->normalizePdfRasterImages($options['pdfRasterImages'] ?? $options['pdf-raster-images'] ?? []);
        $format = PandocConverter::canonicalInputFormat($format);
        $bag = new MediaBag();
        $diagnostics = ['extract-media-image-mode:' . $imageMode];
        $pdfPageRasters = [];
        $pdfPageRasterFallbacks = [];
        $pdfPageRasterFallbackSource = null;

        if ($format === 'pdf' && $imageMode !== 'none') {
            $pdfPageRasters = $this->validatedPdfPageRasters(
                $document,
                $bytes,
                $options['pdfPageRasters'] ?? $options['pdf-page-rasters'] ?? [],
                $diagnostics
            );
            if ($pdfPageRasters !== []) {
                $pageRasterDocument = $this->documentWithValidatedPdfPageRasters(
                    $document,
                    $pdfPageRasters,
                    $diagnostics
                );
                $document = $pageRasterDocument['document'];
                $pdfPageRasters = $pageRasterDocument['rasters'];
            }
            $pdfPageRasterFallbacks = $this->validatedPdfPageRasterFallbacks(
                $document,
                $bytes,
                $options['pdfPageRasterFallbacks'] ?? $options['pdf-page-raster-fallbacks'] ?? [],
                $diagnostics
            );
            foreach (array_keys($pdfPageRasterFallbacks) as $page) {
                if (!isset($pdfPageRasters[$page])) {
                    continue;
                }
                unset($pdfPageRasterFallbacks[$page]);
                $diagnostics[] = 'extract-media-pdf-page-raster-fallback-rejected:successful-response:page-'
                    . $page;
            }
        }

        if ($imageMode === 'none') {
            $document = $this->documentWithoutImages($document);
        }

        $imageSources = $this->imageSources($document);
        if ($imageSources !== []) {
            $this->loadDataUriImages($bag, $imageSources, $diagnostics);
            $this->loadPackageImages($bag, $imageSources, $bytes, $format, $diagnostics, $epubArchive);
            if ($sourcePath !== null) {
                $this->loadLocalImages($bag, $imageSources, $sourcePath, $diagnostics);
            }
        }

        $pdfImagePlacements = [];
        $pdfImageOccurrenceDispositions = [];
        if ($format === 'pdf' && $imageMode !== 'none') {
            $sourcePdfImagePlacements = $this->pdfImageOccurrencesWithStableIdentity(
                $this->pdfImagePlacements($document, $bytes, $diagnostics)
            );
            $pageRasterSet = array_fill_keys(array_map('intval', array_keys($pdfPageRasters)), true);
            $ordinaryPdfImagePlacements = [];
            foreach ($sourcePdfImagePlacements as $placement) {
                $this->setPdfImageOccurrenceDisposition(
                    $pdfImageOccurrenceDispositions,
                    $placement,
                    'pending',
                    'awaiting-media-resolution'
                );
                $page = max(1, (int) ($placement['page'] ?? 1));
                if (isset($pageRasterSet[$page])) {
                    $this->setPdfImageOccurrenceDisposition(
                        $pdfImageOccurrenceDispositions,
                        $placement,
                        'intentional_omission',
                        'whole-page-raster-replacement'
                    );
                    continue;
                }
                $ordinaryPdfImagePlacements[] = $placement;
            }
            $placedPdfImages = $this->anchoredPdfImagePlacements(
                $document,
                $ordinaryPdfImagePlacements,
                $diagnostics,
                $pdfImageOccurrenceDispositions,
                $imageMode,
                $bytes
            );
            if ($placedPdfImages !== []) {
                $pdfImagePlacements = $this->loadPdfImages(
                    $bag,
                    $bytes,
                    $diagnostics,
                    $imageMode,
                    $placedPdfImages,
                    $pdfRasterImages,
                    $pdfImageOccurrenceDispositions
                );
                if ($pdfImagePlacements !== []) {
                    $document = $this->documentWithPlacedPdfImageBlocks(
                        $document,
                        $pdfImagePlacements,
                        $pdfImageOccurrenceDispositions
                    );
                }
            }
            $this->finalizePdfImageOccurrenceDispositions($pdfImageOccurrenceDispositions);
            $document = $this->documentWithPdfImageOccurrenceDispositions(
                $document,
                array_values($pdfImageOccurrenceDispositions)
            );
            foreach ($pdfImageOccurrenceDispositions as $disposition) {
                $diagnostics[] = $this->pdfImageOccurrenceDiagnostic($disposition);
            }
        }

        if ($pdfPageRasterFallbacks !== []) {
            $pageFallbackDocument = $this->documentWithValidatedPdfPageRasterFallbacks(
                $document,
                $pdfPageRasterFallbacks,
                $diagnostics
            );
            $document = $pageFallbackDocument['document'];
            $pdfPageRasterFallbacks = $pageFallbackDocument['fallbacks'];
            $pdfPageRasterFallbackSource = $pageFallbackDocument['source'];
        }

        foreach ($pdfPageRasters as $raster) {
            $bag->insertMedia($raster['source'], 'image/png', $raster['contents']);
        }
        if ($pdfPageRasterFallbacks !== [] && is_string($pdfPageRasterFallbackSource)) {
            $bag->insertMedia($pdfPageRasterFallbackSource, 'application/pdf', $bytes);
            $diagnostics[] = 'extract-media-pdf-page-raster-fallback-original-attached';
        }

        $extracted = $bag->extractMedia($document, $destination);

        return [
            'document' => $extracted['document'],
            'entries' => $extracted['entries'],
            'diagnostics' => array_values(array_unique(array_merge($diagnostics, $extracted['diagnostics']))),
        ];
    }

    /**
     * Use a native file-backed EPUB archive when available so package media
     * never requires retaining the whole source ZIP as a PHP string.
     *
     * @param array<string, mixed> $options
     * @return array{
     *     document:AstNode,
     *     entries:list<array{path:string, mediaPath:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, pathRepairSummary:string, extractionPathRepairSummary:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, linkedMimeGroup?:string, linkedMimeGroupSize?:int}>,
     *     diagnostics:list<string>
     * }
     */
    public function extractFile(AstNode $document, string $path, string $format, array $options): array
    {
        if (PandocConverter::canonicalInputFormat($format) === 'epub') {
            try {
                $epubArchive = EpubArchiveFactory::fromFile($path);
            } catch (\Throwable) {
                // Preserve the byte-backed fallback for ZIP variants the
                // native extension cannot open.
                $epubArchive = null;
            }
            if ($epubArchive !== null) {
                return $this->extract($document, '', $format, $options, $epubArchive);
            }
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return $this->extract($document, $bytes, $format, $options);
    }

    private function normalizeImageMode(mixed $mode): string
    {
        if (is_bool($mode)) {
            return $mode ? 'all' : 'none';
        }

        $mode = strtolower(str_replace(['_', ' '], '-', trim((string) $mode)));

        return match ($mode) {
            'none', 'no', 'off', 'false', '0', 'no-images', 'without-images' => 'none',
            'important', 'auto', 'selected', 'significant' => 'important',
            default => 'all',
        };
    }

    /**
     * Accept browser or host supplied web-safe rasters for PDF image XObjects.
     * A decoder remains outside this class, but the PDF object and intrinsic
     * dimensions are checked again before a raster can become document media.
     *
     * @return array<string,array{contents:string,mimeType:string,width:int,height:int}>
     */
    private function normalizePdfRasterImages(mixed $images): array
    {
        if (!is_array($images)) {
            return [];
        }

        $normalized = [];
        foreach ($images as $key => $image) {
            if (!is_array($image)) {
                continue;
            }
            $object = (string) ($image['object'] ?? $key);
            if (preg_match('/^\d+$/', $object) !== 1) {
                continue;
            }
            // Browser decoders preserve the PDF's zero-padded object label
            // (for example `00003`), while placements and xref-selected
            // assets use the canonical integer form (`3`). Bind both forms
            // to the same object without weakening the numeric-only check.
            $object = (string) ((int) $object);
            $contents = $image['contents'] ?? $image['bytes'] ?? '';
            $mimeType = strtolower(trim((string) ($image['mimeType'] ?? $image['mime'] ?? '')));
            $width = $image['width'] ?? null;
            $height = $image['height'] ?? null;
            if (!is_string($contents) || $contents === '' || strlen($contents) > self::MAX_PDF_RASTER_IMAGE_BYTES
                || !in_array($mimeType, ['image/png', 'image/avif'], true) || !is_numeric($width) || !is_numeric($height)) {
                continue;
            }
            $width = (int) $width;
            $height = (int) $height;
            if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PDF_RASTER_IMAGE_PIXELS
                || !$this->pdfRasterHasDimensions($contents, $mimeType, $width, $height)) {
                continue;
            }

            $normalized[$object] = [
                'contents' => $contents,
                'mimeType' => $mimeType,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $normalized;
    }

    /**
     * Validate a renderer response against the immutable request carried by
     * PdfReader. Every accepted record is bound to the current PDF bytes, one
     * normative page rectangle, the page rotation, exact output dimensions,
     * and the complete PNG payload. Malformed siblings never authorize a
     * fallback by page number.
     *
     * @param list<string> $diagnostics
     * @return array<int,array<string,mixed>>
     */
    private function validatedPdfPageRasters(
        AstNode $document,
        string $pdfBytes,
        mixed $responses,
        array &$diagnostics
    ): array {
        if (!is_array($responses) || $responses === []) {
            return [];
        }
        if (!array_is_list($responses) || count($responses) > self::MAX_PDF_PAGE_RASTER_IMAGES) {
            $diagnostics[] = 'extract-media-pdf-page-raster-rejected:response-container';

            return [];
        }
        $requestMetadata = $this->validatedPdfPageRasterRequestMetadata(
            $document,
            $pdfBytes,
            $diagnostics,
            'extract-media-pdf-page-raster-rejected:request-metadata'
        );
        if ($requestMetadata === null) {
            return [];
        }
        $sourceSha256 = $requestMetadata['sourceSha256'];
        $requestsById = $requestMetadata['requestsById'];

        $responseIdCounts = [];
        $responsePageCounts = [];
        $responseDigestCounts = [];
        foreach ($responses as $response) {
            if (!is_array($response)) {
                continue;
            }
            if (is_string($response['requestId'] ?? null)) {
                $responseIdCounts[$response['requestId']] = ($responseIdCounts[$response['requestId']] ?? 0) + 1;
            }
            if (is_int($response['page'] ?? null)) {
                $responsePageCounts[$response['page']] = ($responsePageCounts[$response['page']] ?? 0) + 1;
            }
            if (is_string($response['requestDigest'] ?? null)) {
                $responseDigestCounts[$response['requestDigest']] =
                    ($responseDigestCounts[$response['requestDigest']] ?? 0) + 1;
            }
        }

        $responseKeys = [
            'byteLength',
            'contents',
            'height',
            'method',
            'mimeType',
            'page',
            'pageBox',
            'pageBoxSource',
            'pageObject',
            'pageRotation',
            'proofDigest',
            'requestDigest',
            'requestId',
            'sha256',
            'sourceSha256',
            'version',
            'width',
        ];
        $accepted = [];
        $acceptedBytes = 0;
        foreach ($responses as $response) {
            $pageLabel = is_array($response) && is_int($response['page'] ?? null)
                ? ':page-' . $response['page']
                : '';
            if (!is_array($response) || !$this->pdfArrayHasExactKeys($response, $responseKeys)) {
                $diagnostics[] = 'extract-media-pdf-page-raster-rejected:response-shape' . $pageLabel;
                continue;
            }
            $requestId = is_string($response['requestId'] ?? null) ? $response['requestId'] : '';
            $requestDigest = is_string($response['requestDigest'] ?? null) ? $response['requestDigest'] : '';
            $page = is_int($response['page'] ?? null) ? $response['page'] : 0;
            if (($responseIdCounts[$requestId] ?? 0) !== 1
                || ($responsePageCounts[$page] ?? 0) !== 1
                || ($responseDigestCounts[$requestDigest] ?? 0) !== 1) {
                $diagnostics[] = 'extract-media-pdf-page-raster-rejected:duplicate' . $pageLabel;
                continue;
            }
            $request = $requestsById[$requestId] ?? null;
            if (!is_array($request)) {
                $diagnostics[] = 'extract-media-pdf-page-raster-rejected:unknown-request' . $pageLabel;
                continue;
            }
            if (($response['version'] ?? null) !== 1
                || ($response['method'] ?? null) !== $request['method']
                || !is_string($response['sourceSha256'] ?? null)
                || !hash_equals($sourceSha256, $response['sourceSha256'])
                || !hash_equals($request['sourceSha256'], $response['sourceSha256'])
                || $page !== $request['page']
                || ($response['pageObject'] ?? null) !== $request['pageObject']
                || ($response['pageBoxSource'] ?? null) !== $request['pageBoxSource']
                || ($response['pageRotation'] ?? null) !== $request['pageRotation']
                || !$this->pdfPageRasterBoxesMatch($response['pageBox'] ?? null, $request['pageBox'])
                || ($response['width'] ?? null) !== $request['width']
                || ($response['height'] ?? null) !== $request['height']
                || ($response['mimeType'] ?? null) !== 'image/png'
                || !hash_equals($request['requestDigest'], $requestDigest)) {
                $diagnostics[] = 'extract-media-pdf-page-raster-rejected:request-mismatch' . $pageLabel;
                continue;
            }
            $contents = $response['contents'] ?? null;
            $byteLength = $response['byteLength'] ?? null;
            $sha256 = $response['sha256'] ?? null;
            $proofDigest = $response['proofDigest'] ?? null;
            if (!is_string($contents)
                || $contents === ''
                || strlen($contents) > self::MAX_PDF_RASTER_IMAGE_BYTES
                || !is_int($byteLength)
                || $byteLength !== strlen($contents)
                || !is_string($sha256)
                || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1
                || !hash_equals($sha256, hash('sha256', $contents))
                || !is_string($proofDigest)
                || preg_match('/^[a-f0-9]{64}$/D', $proofDigest) !== 1
                || !hash_equals(
                    $proofDigest,
                    $this->pdfPageRasterProofDigest($requestDigest, $byteLength, $sha256)
                )
                || !$this->pdfPageRasterPngIsValid(
                    $contents,
                    $request['width'],
                    $request['height']
                )) {
                $diagnostics[] = 'extract-media-pdf-page-raster-rejected:payload' . $pageLabel;
                continue;
            }
            if ($acceptedBytes + $byteLength > self::MAX_PDF_PAGE_RASTER_TOTAL_BYTES) {
                $diagnostics[] = 'extract-media-pdf-page-raster-rejected:total-byte-limit' . $pageLabel;
                continue;
            }
            $acceptedBytes += $byteLength;
            $accepted[$page] = $response + [
                'source' => 'pdf/page-' . $page . '-' . substr($sha256, 0, 16) . '.png',
            ];
            $diagnostics[] = 'extract-media-pdf-page-raster-validated:page-' . $page;
        }
        ksort($accepted, SORT_NUMERIC);

        return $accepted;
    }

    /**
     * Recompute the immutable renderer capability list against the current
     * source bytes. Both successful PNGs and unavailable acknowledgements use
     * this gate; neither may authorize work by a page number alone.
     *
     * @param list<string> $diagnostics
     * @return array{sourceSha256:string,requestsById:array<string,array<string,mixed>>}|null
     */
    private function validatedPdfPageRasterRequestMetadata(
        AstNode $document,
        string $pdfBytes,
        array &$diagnostics,
        string $diagnostic
    ): ?array {
        $meta = $document->attr('meta', []);
        $requests = is_array($meta) ? ($meta['pdfPageRasterRequests'] ?? null) : null;
        $neededPages = is_array($meta) ? ($meta['pdfPagesNeedingImageRepresentation'] ?? null) : null;
        $processedPages = is_array($meta) ? ($meta['pdfProcessedPageNumbers'] ?? null) : null;
        if (!is_array($requests)
            || !array_is_list($requests)
            || !is_array($neededPages)
            || !array_is_list($neededPages)
            || !is_array($processedPages)
            || !array_is_list($processedPages)
            || ($meta['pdfPageRasterRequestCount'] ?? null) !== count($requests)) {
            $diagnostics[] = $diagnostic;

            return null;
        }
        $sourceSha256 = hash('sha256', $pdfBytes);
        $neededSet = [];
        foreach ($neededPages as $page) {
            if (!is_int($page) || $page < 1 || isset($neededSet[$page])) {
                $diagnostics[] = $diagnostic;

                return null;
            }
            $neededSet[$page] = true;
        }
        $processedSet = [];
        foreach ($processedPages as $page) {
            if (!is_int($page) || $page < 1 || isset($processedSet[$page])) {
                $diagnostics[] = $diagnostic;

                return null;
            }
            $processedSet[$page] = true;
        }

        $requestsById = [];
        $requestPages = [];
        $requestDigests = [];
        $requestKeys = [
            'height',
            'id',
            'method',
            'mimeType',
            'page',
            'pageBox',
            'pageBoxSource',
            'pageObject',
            'pageRotation',
            'requestDigest',
            'sourceSha256',
            'version',
            'width',
        ];
        foreach ($requests as $request) {
            if (!is_array($request)
                || !$this->pdfArrayHasExactKeys($request, $requestKeys)
                || ($request['version'] ?? null) !== 1
                || ($request['method'] ?? null) !== 'pdfjs-whole-page-raster'
                || !is_string($request['id'] ?? null)
                || !is_string($request['sourceSha256'] ?? null)
                || !hash_equals($sourceSha256, $request['sourceSha256'])
                || !is_int($request['page'] ?? null)
                || !isset($neededSet[$request['page']])
                || !isset($processedSet[$request['page']])
                || !is_int($request['pageObject'] ?? null)
                || $request['pageObject'] < 1
                || !in_array($request['pageBoxSource'] ?? null, ['CropBox', 'MediaBox'], true)
                || !is_int($request['pageRotation'] ?? null)
                || !in_array($request['pageRotation'], [0, 90, 180, 270], true)
                || !is_int($request['width'] ?? null)
                || !is_int($request['height'] ?? null)
                || $request['width'] < 1
                || $request['height'] < 1
                || $request['width'] > self::MAX_PDF_PAGE_RASTER_DIMENSION
                || $request['height'] > self::MAX_PDF_PAGE_RASTER_DIMENSION
                || $request['width'] * $request['height'] > self::MAX_PDF_PAGE_RASTER_PIXELS
                || ($request['mimeType'] ?? null) !== 'image/png'
                || !$this->pdfPageRasterBoxIsValid($request['pageBox'] ?? null)
                || !is_string($request['requestDigest'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $request['requestDigest']) !== 1
                || !hash_equals(
                    $request['requestDigest'],
                    $this->pdfPageRasterRequestDigest($request)
                )
                || !hash_equals(
                    $request['id'],
                    'pdf-page-raster-' . substr($request['requestDigest'], 0, 32)
                )
                || isset($requestsById[$request['id']])
                || isset($requestPages[$request['page']])
                || isset($requestDigests[$request['requestDigest']])) {
                $diagnostics[] = $diagnostic;

                return null;
            }
            $requestsById[$request['id']] = $request;
            $requestPages[$request['page']] = true;
            $requestDigests[$request['requestDigest']] = true;
        }

        return ['sourceSha256' => $sourceSha256, 'requestsById' => $requestsById];
    }

    /**
     * Accept only the renderer's minimal unavailable acknowledgement. It has
     * no page, geometry, digest, or payload fields of its own; those facts are
     * recovered exclusively from the revalidated immutable request.
     *
     * @param list<string> $diagnostics
     * @return array<int,array<string,mixed>>
     */
    private function validatedPdfPageRasterFallbacks(
        AstNode $document,
        string $pdfBytes,
        mixed $fallbacks,
        array &$diagnostics
    ): array {
        if (!is_array($fallbacks) || $fallbacks === []) {
            return [];
        }
        if (!array_is_list($fallbacks) || count($fallbacks) > self::MAX_PDF_PAGE_RASTER_IMAGES) {
            $diagnostics[] = 'extract-media-pdf-page-raster-fallback-rejected:response-container';

            return [];
        }
        if (strlen($pdfBytes) > self::MAX_PDF_PAGE_RASTER_FALLBACK_SOURCE_BYTES) {
            $diagnostics[] = 'extract-media-pdf-page-raster-fallback-rejected:source-byte-limit';

            return [];
        }
        $requestMetadata = $this->validatedPdfPageRasterRequestMetadata(
            $document,
            $pdfBytes,
            $diagnostics,
            'extract-media-pdf-page-raster-fallback-rejected:request-metadata'
        );
        if ($requestMetadata === null) {
            return [];
        }
        $requestsById = $requestMetadata['requestsById'];
        $idCounts = [];
        foreach ($fallbacks as $fallback) {
            if (is_array($fallback) && is_string($fallback['requestId'] ?? null)) {
                $idCounts[$fallback['requestId']] = ($idCounts[$fallback['requestId']] ?? 0) + 1;
            }
        }

        $accepted = [];
        foreach ($fallbacks as $fallback) {
            if (!is_array($fallback)
                || !$this->pdfArrayHasExactKeys($fallback, ['error', 'requestId'])) {
                $diagnostics[] = 'extract-media-pdf-page-raster-fallback-rejected:response-shape';
                continue;
            }
            $requestId = is_string($fallback['requestId'] ?? null) ? $fallback['requestId'] : '';
            if (($idCounts[$requestId] ?? 0) !== 1) {
                $diagnostics[] = 'extract-media-pdf-page-raster-fallback-rejected:duplicate';
                continue;
            }
            $request = $requestsById[$requestId] ?? null;
            if (!is_array($request)) {
                $diagnostics[] = 'extract-media-pdf-page-raster-fallback-rejected:unknown-request';
                continue;
            }
            $error = $fallback['error'] ?? null;
            if (!is_string($error)
                || trim($error) === ''
                || strlen($error) > self::MAX_PDF_PAGE_RASTER_FALLBACK_ERROR_BYTES
                || preg_match('//u', $error) !== 1
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $error) === 1) {
                $diagnostics[] = 'extract-media-pdf-page-raster-fallback-rejected:error';
                continue;
            }
            $page = $request['page'];
            $accepted[$page] = [
                'requestId' => $requestId,
                'error' => $error,
                'page' => $page,
                'requestDigest' => $request['requestDigest'],
                'sourceSha256' => $requestMetadata['sourceSha256'],
            ];
            $diagnostics[] = 'extract-media-pdf-page-raster-fallback-validated:page-' . $page;
        }
        ksort($accepted, SORT_NUMERIC);

        return $accepted;
    }

    /** @param list<string> $expectedKeys */
    private function pdfArrayHasExactKeys(array $value, array $expectedKeys): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expectedKeys, SORT_STRING);

        return $keys === $expectedKeys;
    }

    private function pdfPageRasterBoxIsValid(mixed $box): bool
    {
        if (!is_array($box) || !array_is_list($box) || count($box) !== 4) {
            return false;
        }
        foreach ($box as $coordinate) {
            if (!is_numeric($coordinate) || !is_finite((float) $coordinate)) {
                return false;
            }
        }

        return (float) $box[2] - (float) $box[0] > 0.000001
            && (float) $box[3] - (float) $box[1] > 0.000001;
    }

    private function pdfPageRasterBoxesMatch(mixed $left, mixed $right): bool
    {
        if (!$this->pdfPageRasterBoxIsValid($left) || !$this->pdfPageRasterBoxIsValid($right)) {
            return false;
        }
        foreach (array_values($left) as $index => $coordinate) {
            if (sprintf('%.6F', (float) $coordinate) !== sprintf('%.6F', (float) $right[$index])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $request */
    private function pdfPageRasterRequestDigest(array $request): string
    {
        $box = array_map(
            static fn (mixed $value): string => sprintf('%.6F', (float) $value),
            array_values(is_array($request['pageBox'] ?? null) ? $request['pageBox'] : [])
        );

        return hash('sha256', implode("\n", [
            'pdf-page-raster-request-v1',
            'method=' . (string) ($request['method'] ?? ''),
            'sourceSha256=' . (string) ($request['sourceSha256'] ?? ''),
            'page=' . (string) ($request['page'] ?? ''),
            'pageObject=' . (string) ($request['pageObject'] ?? ''),
            'pageBox=' . implode(',', $box),
            'pageBoxSource=' . (string) ($request['pageBoxSource'] ?? ''),
            'pageRotation=' . (string) ($request['pageRotation'] ?? ''),
            'width=' . (string) ($request['width'] ?? ''),
            'height=' . (string) ($request['height'] ?? ''),
            'mimeType=' . (string) ($request['mimeType'] ?? ''),
        ]));
    }

    private function pdfPageRasterProofDigest(
        string $requestDigest,
        int $byteLength,
        string $sha256
    ): string {
        return hash('sha256', implode("\n", [
            'pdf-page-raster-proof-v1',
            'requestDigest=' . $requestDigest,
            'byteLength=' . $byteLength,
            'sha256=' . $sha256,
        ]));
    }

    private function pdfPageRasterPngIsValid(string $bytes, int $width, int $height): bool
    {
        $length = strlen($bytes);
        if ($length < 57
            || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n"
            || $width < 1
            || $height < 1) {
            return false;
        }
        $offset = 8;
        $chunkCount = 0;
        $seenHeader = false;
        $seenData = false;
        $seenEnd = false;
        while ($offset + 12 <= $length && $chunkCount < 4096) {
            $size = unpack('Nlength', substr($bytes, $offset, 4));
            $chunkLength = is_array($size) ? (int) ($size['length'] ?? -1) : -1;
            $type = substr($bytes, $offset + 4, 4);
            if ($chunkLength < 0
                || $chunkLength > self::MAX_PDF_RASTER_IMAGE_BYTES
                || preg_match('/^[A-Za-z]{4}$/D', $type) !== 1
                || $offset + 12 + $chunkLength > $length) {
                return false;
            }
            $data = substr($bytes, $offset + 8, $chunkLength);
            $expectedCrc = substr($bytes, $offset + 8 + $chunkLength, 4);
            if (!hash_equals($expectedCrc, pack('N', crc32($type . $data)))) {
                return false;
            }
            if (!$seenHeader) {
                if ($type !== 'IHDR' || $chunkLength !== 13) {
                    return false;
                }
                $header = unpack(
                    'Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace',
                    $data
                );
                $bitDepth = is_array($header) ? (int) ($header['bitDepth'] ?? 0) : 0;
                $colorType = is_array($header) ? (int) ($header['colorType'] ?? -1) : -1;
                $validBitDepths = [
                    0 => [1, 2, 4, 8, 16],
                    2 => [8, 16],
                    3 => [1, 2, 4, 8],
                    4 => [8, 16],
                    6 => [8, 16],
                ];
                if (!is_array($header)
                    || (int) ($header['width'] ?? 0) !== $width
                    || (int) ($header['height'] ?? 0) !== $height
                    || !isset($validBitDepths[$colorType])
                    || !in_array($bitDepth, $validBitDepths[$colorType], true)
                    || (int) ($header['compression'] ?? -1) !== 0
                    || (int) ($header['filter'] ?? -1) !== 0
                    || !in_array((int) ($header['interlace'] ?? -1), [0, 1], true)) {
                    return false;
                }
                $seenHeader = true;
            } elseif ($type === 'IHDR') {
                return false;
            }
            if ($type === 'IDAT') {
                if ($chunkLength === 0) {
                    return false;
                }
                $seenData = true;
            }
            $offset += 12 + $chunkLength;
            $chunkCount++;
            if ($type === 'IEND') {
                if ($chunkLength !== 0 || !$seenData || $offset !== $length) {
                    return false;
                }
                $seenEnd = true;
                break;
            }
        }

        return $seenHeader && $seenData && $seenEnd;
    }

    /**
     * Suppress page-scoped visual fragments only after a complete raster
     * response and a live source-edge graph establish an exact page boundary.
     * Text remains untouched: unsupported_no_text/needsOcr are source facts,
     * not claims that a browser raster became editable.
     *
     * @param array<int,array<string,mixed>> $rasters
     * @param list<string> $diagnostics
     * @return array{document:AstNode,rasters:array<int,array<string,mixed>>}
     */
    private function documentWithValidatedPdfPageRasters(
        AstNode $document,
        array $rasters,
        array &$diagnostics
    ): array {
        $original = $document;
        $candidateSet = array_fill_keys(array_map('intval', array_keys($rasters)), true);
        $filtered = $this->documentWithoutPdfConstituentPageVisuals($original, $candidateSet);
        $indexes = $this->pdfPageRasterBoundaryIndexes($filtered, array_keys($candidateSet));
        $accepted = array_intersect_key($rasters, $indexes);
        foreach (array_diff_key($rasters, $accepted) as $page => $_raster) {
            $diagnostics[] = 'extract-media-pdf-page-raster-rejected:page-boundary:page-' . $page;
        }
        if ($accepted === []) {
            return ['document' => $original, 'rasters' => []];
        }

        // A rejected sibling must not suppress its page visuals. Rebuild from
        // the original AST with only the accepted page set, then re-prove the
        // final insertion points against that exact live graph.
        $acceptedSet = array_fill_keys(array_map('intval', array_keys($accepted)), true);
        $filtered = $this->documentWithoutPdfConstituentPageVisuals($original, $acceptedSet);
        $indexes = $this->pdfPageRasterBoundaryIndexes($filtered, array_keys($acceptedSet));
        $accepted = array_intersect_key($accepted, $indexes);
        if ($accepted === []) {
            return ['document' => $original, 'rasters' => []];
        }
        $acceptedSet = array_fill_keys(array_map('intval', array_keys($accepted)), true);
        $filtered = $this->documentWithoutPdfConstituentPageVisuals($original, $acceptedSet);
        $indexes = $this->pdfPageRasterBoundaryIndexes($filtered, array_keys($acceptedSet));
        $accepted = array_intersect_key($accepted, $indexes);
        if ($accepted === []) {
            return ['document' => $original, 'rasters' => []];
        }

        $before = [];
        foreach ($accepted as $page => $raster) {
            $before[$indexes[$page]][$page] = $this->placedPdfPageRasterBlock($raster);
        }
        $children = [];
        $childCount = count($filtered->children);
        for ($index = 0; $index <= $childCount; $index++) {
            if (isset($before[$index])) {
                ksort($before[$index], SORT_NUMERIC);
                array_push($children, ...array_values($before[$index]));
            }
            if ($index < $childCount) {
                $children[] = $filtered->children[$index];
            }
        }
        $withBlocks = new AstNode($filtered->type, $filtered->attrs, $children);
        $withMetadata = $this->documentWithPdfPageRasterMetadata($withBlocks, $accepted);
        foreach (array_keys($accepted) as $page) {
            $diagnostics[] = 'extract-media-pdf-page-raster-loaded:page-' . $page;
        }

        return ['document' => $withMetadata, 'rasters' => $accepted];
    }

    /**
     * Place a visible renderer-unavailable notice at the same live physical
     * page boundary used for a successful raster. The original AST is never
     * filtered here: constituent source visuals remain eligible, and these
     * notices do not change represented-page or completeness metadata.
     *
     * @param array<int,array<string,mixed>> $fallbacks
     * @param list<string> $diagnostics
     * @return array{document:AstNode,fallbacks:array<int,array<string,mixed>>,source:?string}
     */
    private function documentWithValidatedPdfPageRasterFallbacks(
        AstNode $document,
        array $fallbacks,
        array &$diagnostics
    ): array {
        $indexes = $this->pdfPageRasterBoundaryIndexes($document, array_keys($fallbacks));
        $accepted = array_intersect_key($fallbacks, $indexes);
        foreach (array_diff_key($fallbacks, $accepted) as $page => $_fallback) {
            $diagnostics[] = 'extract-media-pdf-page-raster-fallback-rejected:page-boundary:page-'
                . $page;
        }
        if ($accepted === []) {
            return ['document' => $document, 'fallbacks' => [], 'source' => null];
        }
        ksort($accepted, SORT_NUMERIC);
        $sourceSha256 = (string) (reset($accepted)['sourceSha256'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $sourceSha256) !== 1) {
            return ['document' => $document, 'fallbacks' => [], 'source' => null];
        }
        $source = 'pdf/original-' . $sourceSha256 . '.pdf';
        $downloadPage = (int) array_key_first($accepted);
        $before = [];
        foreach ($accepted as $page => $fallback) {
            $before[$indexes[$page]][$page] = $this->placedPdfPageRasterFallbackBlock(
                $fallback,
                $source,
                $page === $downloadPage
            );
        }

        $children = [];
        $childCount = count($document->children);
        for ($index = 0; $index <= $childCount; $index++) {
            if (isset($before[$index])) {
                ksort($before[$index], SORT_NUMERIC);
                array_push($children, ...array_values($before[$index]));
            }
            if ($index < $childCount) {
                $children[] = $document->children[$index];
            }
        }
        $withBlocks = new AstNode($document->type, $document->attrs, $children);
        $withMetadata = $this->documentWithPdfPageRasterFallbackMetadata(
            $withBlocks,
            $accepted,
            $source
        );
        foreach (array_keys($accepted) as $page) {
            $diagnostics[] = 'extract-media-pdf-page-raster-fallback-inserted:page-' . $page;
        }

        return ['document' => $withMetadata, 'fallbacks' => $accepted, 'source' => $source];
    }

    /** @param array<string,mixed> $fallback */
    private function placedPdfPageRasterFallbackBlock(
        array $fallback,
        string $source,
        bool $includeDownload
    ): AstNode {
        $page = max(1, (int) ($fallback['page'] ?? 1));
        $requestId = (string) ($fallback['requestId'] ?? '');
        $attributes = [
            'data-pandoc-pdf-page' => (string) $page,
            'data-pandoc-pdf-page-raster-fallback' => 'renderer-unavailable',
            'data-pandoc-pdf-page-raster-fallback-request' => $requestId,
            'data-pandoc-pdf-page-represented' => 'false',
        ];
        $children = [
            new AstNode('text', [
                'text' => 'PDF page ' . $page . ' image is unavailable.',
            ]),
        ];
        if ($includeDownload) {
            $children[] = new AstNode('text', ['text' => ' ']);
            $children[] = new AstNode('link', [
                'url' => $source,
                'title' => 'Download original PDF',
                'classes' => ['pandoc-pdf-page-raster-fallback-download'],
                'attributes' => [
                    'data-pandoc-pdf-page-raster-fallback-download' => 'true',
                    'data-pandoc-pdf-original-download' => 'true',
                    'download' => 'original.pdf',
                ],
            ], [
                new AstNode('text', ['text' => 'Download the original PDF.']),
            ]);
        }

        return new AstNode('paragraph', [
            'classes' => [
                'pandoc-pdf-page-raster-fallback',
                'pandoc-pdf-page-image-unavailable',
            ],
            'attributes' => $attributes,
        ], $children);
    }

    /** @param array<int,array<string,mixed>> $fallbacks */
    private function documentWithPdfPageRasterFallbackMetadata(
        AstNode $document,
        array $fallbacks,
        string $source
    ): AstNode {
        $attrs = $document->attrs;
        $meta = is_array($attrs['meta'] ?? null) ? $attrs['meta'] : [];
        $pages = array_map('intval', array_keys($fallbacks));
        sort($pages, SORT_NUMERIC);
        $records = [];
        foreach ($fallbacks as $fallback) {
            $records[] = [
                'requestId' => (string) $fallback['requestId'],
                'page' => (int) $fallback['page'],
                'error' => (string) $fallback['error'],
                'requestDigest' => (string) $fallback['requestDigest'],
                'sourceSha256' => (string) $fallback['sourceSha256'],
            ];
        }
        $meta['pdfPageRasterFallbackPageNumbers'] = $pages;
        $meta['pdfPageRasterFallbackCount'] = count($records);
        $meta['pdfPageRasterFallbacks'] = $records;
        $meta['pdfPageRasterFallbackOriginalSource'] = $source;
        $meta['pdfPageRasterFallbackOriginalAttached'] = true;
        $meta['pdfPageRasterFallbackRepresentationGranted'] = false;
        $attrs['meta'] = $meta;

        return new AstNode($document->type, $attrs, $document->children);
    }

    /** @param array<int,true> $pageSet */
    private function documentWithoutPdfConstituentPageVisuals(
        AstNode $document,
        array $pageSet
    ): AstNode {
        $children = [];
        foreach ($document->children as $child) {
            $filtered = $this->pdfNodeWithoutConstituentPageVisuals($child, $pageSet);
            if ($filtered !== null) {
                $children[] = $filtered;
            }
        }

        return new AstNode($document->type, $document->attrs, $children);
    }

    /** @param array<int,true> $pageSet */
    private function pdfNodeWithoutConstituentPageVisuals(
        AstNode $node,
        array $pageSet
    ): ?AstNode {
        $visualPage = $this->pdfConstituentVisualPage($node);
        if ($visualPage !== null && isset($pageSet[$visualPage])) {
            return null;
        }
        $children = [];
        foreach ($node->children as $child) {
            $filtered = $this->pdfNodeWithoutConstituentPageVisuals($child, $pageSet);
            if ($filtered !== null) {
                $children[] = $filtered;
            }
        }

        return new AstNode($node->type, $node->attrs, $children);
    }

    private function pdfConstituentVisualPage(AstNode $node): ?int
    {
        $attributes = $node->attr('attributes', []);
        $classes = $node->attr('classes', []);
        if (!is_array($attributes)
            || !is_array($classes)
            || !is_numeric($attributes['data-pandoc-pdf-page'] ?? null)) {
            return null;
        }
        $isVisual = array_intersect($classes, [
            'pandoc-pdf-image-block',
            'pandoc-pdf-form-figure',
            'pandoc-pdf-form-rendered',
            'pandoc-pdf-page-raster',
            'pandoc-pdf-page-raster-fallback',
        ]) !== [];
        foreach ([
            'data-pandoc-pdf-image-object',
            'data-pandoc-pdf-form-id',
            'data-pandoc-pdf-visual-id',
            'data-pandoc-pdf-visual-kind',
        ] as $key) {
            $isVisual = $isVisual || array_key_exists($key, $attributes);
        }

        return $isVisual ? max(1, (int) $attributes['data-pandoc-pdf-page']) : null;
    }

    /**
     * Map live top-level source destinations back to their physical pages.
     * The edge digest, edge identities, and top-level binding identities are
     * all recomputed before any requested page is assigned an insertion slot.
     *
     * @param list<int> $requestedPages
     * @return array<int,int>
     */
    private function pdfPageRasterBoundaryIndexes(AstNode $document, array $requestedPages): array
    {
        $meta = $document->attr('meta', []);
        $sourceDisposition = is_array($meta) ? ($meta['pdfSourceDisposition'] ?? null) : null;
        $sourceEdges = is_array($sourceDisposition)
            && ($sourceDisposition['version'] ?? null) === 2
            && ($sourceDisposition['sourceEdgeMappingComplete'] ?? null) === true
            && is_array($sourceDisposition['sourceEdges'] ?? null)
                ? $sourceDisposition['sourceEdges']
                : null;
        if (!is_array($sourceEdges)
            || !array_is_list($sourceEdges)
            || ($sourceDisposition['sourceEdgeCount'] ?? null) !== count($sourceEdges)
            || ($sourceDisposition['sourceOccurrenceCount'] ?? null) !== count($sourceEdges)
            || !is_string($sourceDisposition['sourceEdgeDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $sourceDisposition['sourceEdgeDigest']) !== 1
            || !hash_equals(
                $sourceDisposition['sourceEdgeDigest'],
                $this->pdfSourceDispositionEdgeDigest($sourceEdges)
            )) {
            return [];
        }
        $edgesBySourceId = [];
        $edgeIds = [];
        foreach ($sourceEdges as $edge) {
            if (!is_array($edge)
                || !$this->pdfSourceDispositionEdgeIdentityIsValid($edge)
                || isset($edgesBySourceId[$edge['sourceOccurrenceId']])
                || isset($edgeIds[$edge['id']])) {
                return [];
            }
            $edgesBySourceId[$edge['sourceOccurrenceId']] = $edge;
            $edgeIds[$edge['id']] = true;
        }

        $facts = [];
        $topNodeIds = [];
        foreach ($document->children as $index => $block) {
            $sourceIds = $this->validatedPdfTopLevelSourceLineIds($block);
            if ($sourceIds === false) {
                return [];
            }
            $pages = [];
            if ($sourceIds !== []) {
                $nodeId = $block->attr('sourceNodeId');
                if (!is_string($nodeId) || $nodeId === '' || isset($topNodeIds[$nodeId])) {
                    return [];
                }
                $topNodeIds[$nodeId] = true;
                foreach ($sourceIds as $sourceId) {
                    $edge = $edgesBySourceId[$sourceId] ?? null;
                    if (!is_array($edge)
                        || ($edge['target'] ?? null) !== 'output'
                        || !in_array($nodeId, $edge['destinationNodeIds'] ?? [], true)) {
                        return [];
                    }
                    $pages[(int) $edge['page']] = true;
                }
            } else {
                $visualPage = $this->pdfConstituentVisualPage($block);
                if ($visualPage !== null) {
                    $pages[$visualPage] = true;
                } elseif (preg_match('/\S/u', $this->pdfImageAnchorBlockText($block)) === 1) {
                    return [];
                }
            }
            if ($pages !== []) {
                $pageNumbers = array_keys($pages);
                sort($pageNumbers, SORT_NUMERIC);
                $facts[$index] = [
                    'min' => $pageNumbers[0],
                    'max' => $pageNumbers[count($pageNumbers) - 1],
                ];
            }
        }
        foreach ($sourceEdges as $edge) {
            if (($edge['target'] ?? null) !== 'output') {
                continue;
            }
            foreach ($edge['destinationNodeIds'] as $nodeId) {
                if (!isset($topNodeIds[$nodeId])) {
                    return [];
                }
            }
        }
        $previousPage = 0;
        foreach ($facts as $fact) {
            if ($fact['min'] < $previousPage) {
                return [];
            }
            $previousPage = max($previousPage, $fact['max']);
        }

        $indexes = [];
        foreach ($requestedPages as $page) {
            if (!is_int($page) || $page < 1) {
                continue;
            }
            $insertionIndex = count($document->children);
            $valid = true;
            foreach ($facts as $index => $fact) {
                if ($fact['max'] < $page) {
                    $insertionIndex = $index + 1;
                    continue;
                }
                if ($fact['min'] >= $page) {
                    $insertionIndex = $index;
                    break;
                }
                $valid = false;
                break;
            }
            if ($valid) {
                $indexes[$page] = $insertionIndex;
            }
        }

        return $indexes;
    }

    /** @param array<string,mixed> $raster */
    private function placedPdfPageRasterBlock(array $raster): AstNode
    {
        $page = max(1, (int) $raster['page']);
        $box = array_values($raster['pageBox']);
        $pageWidth = (float) $box[2] - (float) $box[0];
        $pageHeight = (float) $box[3] - (float) $box[1];
        if (in_array((int) $raster['pageRotation'], [90, 270], true)) {
            [$pageWidth, $pageHeight] = [$pageHeight, $pageWidth];
        }
        $attributes = [
            'data-pandoc-pdf-page' => (string) $page,
            'data-pandoc-pdf-page-raster' => 'browser-pdfjs',
            'data-pandoc-pdf-page-raster-request' => (string) $raster['requestDigest'],
            'data-pandoc-pdf-page-raster-proof' => (string) $raster['proofDigest'],
            'data-pandoc-pdf-page-raster-sha256' => (string) $raster['sha256'],
            'data-pandoc-pdf-page-raster-width' => (string) $raster['width'],
            'data-pandoc-pdf-page-raster-height' => (string) $raster['height'],
            'data-pandoc-pdf-page-rotation' => (string) $raster['pageRotation'],
        ];
        $label = 'PDF page ' . $page . ' image; editable text unavailable';

        return new AstNode('paragraph', [
            'classes' => ['pandoc-pdf-page-raster', 'pandoc-pdf-page-image'],
            'attributes' => $attributes,
        ], [
            new AstNode('image', [
                'url' => (string) $raster['source'],
                'title' => 'PDF page ' . $page . ' raster',
                'alt' => $label,
                'width' => $this->pdfPointDimension($pageWidth),
                'height' => $this->pdfPointDimension($pageHeight),
                'attributes' => $attributes,
            ], [
                new AstNode('text', ['text' => $label]),
            ]),
        ]);
    }

    /** @param array<int,array<string,mixed>> $rasters */
    private function documentWithPdfPageRasterMetadata(AstNode $document, array $rasters): AstNode
    {
        $attrs = $document->attrs;
        $meta = is_array($attrs['meta'] ?? null) ? $attrs['meta'] : [];
        $pages = array_map('intval', array_keys($rasters));
        sort($pages, SORT_NUMERIC);
        $represented = [];
        foreach ((is_array($meta['pdfRepresentedPageNumbers'] ?? null)
            ? $meta['pdfRepresentedPageNumbers']
            : []) as $page) {
            if (is_int($page) && $page > 0) {
                $represented[$page] = true;
            }
        }
        foreach ($pages as $page) {
            $represented[$page] = true;
        }
        $representedPages = array_keys($represented);
        sort($representedPages, SORT_NUMERIC);
        $processedPages = is_array($meta['pdfProcessedPageNumbers'] ?? null)
            ? array_values(array_unique(array_map('intval', $meta['pdfProcessedPageNumbers'])))
            : [];
        sort($processedPages, SORT_NUMERIC);
        $proofs = [];
        foreach ($rasters as $raster) {
            $proof = $raster;
            unset($proof['contents'], $proof['source']);
            $proofs[] = $proof;
        }
        $meta['pdfPageRasterRepresentedPageNumbers'] = $pages;
        $meta['pdfPageRasterSuppressedVisualPageNumbers'] = $pages;
        $meta['pdfPageRasterProofs'] = $proofs;
        $meta['pdfRepresentedPageNumbers'] = $representedPages;
        $meta['pdfPageRepresentationComplete'] = $processedPages !== []
            && $representedPages === $processedPages;
        if (is_array($meta['pdfFormXObjectPlacements'] ?? null)) {
            $pageSet = array_fill_keys($pages, true);
            $meta['pdfFormXObjectPlacements'] = array_values(array_filter(
                $meta['pdfFormXObjectPlacements'],
                static fn (mixed $placement): bool => !is_array($placement)
                    || !isset($pageSet[max(1, (int) ($placement['page'] ?? 1))])
            ));
        }
        $attrs['meta'] = $meta;

        return new AstNode($document->type, $attrs, $document->children);
    }

    private function documentWithoutImages(AstNode $document): AstNode
    {
        return $this->filterImages($document) ?? new AstNode('document', $document->attrs, []);
    }

    private function filterImages(AstNode $node): ?AstNode
    {
        if ($node->type === 'image') {
            return null;
        }

        $children = [];
        $removedChild = false;
        foreach ($node->children as $child) {
            $filtered = $this->filterImages($child);
            if ($filtered !== null) {
                $children[] = $filtered;
            } else {
                $removedChild = true;
            }
        }

        if ($removedChild && $children === [] && in_array($node->type, ['paragraph', 'plain', 'list_item'], true) && trim((string) $node->attr('text', '')) === '') {
            return null;
        }

        return new AstNode($node->type, $node->attrs, $children);
    }

    /**
     * @return list<string>
     */
    private function imageSources(AstNode $document): array
    {
        $sources = [];
        $this->walk($document, static function (AstNode $node) use (&$sources): void {
            if ($node->type !== 'image') {
                return;
            }
            $source = (string) $node->attr('url', $node->attr('src', ''));
            if ($source !== '') {
                $sources[$source] = true;
            }
        });

        return array_keys($sources);
    }

    private function walk(AstNode $node, callable $callback): void
    {
        $callback($node);
        foreach ($node->children as $child) {
            $this->walk($child, $callback);
        }
    }

    /**
     * @param list<string> $sources
     * @param list<string> $diagnostics
     */
    private function loadDataUriImages(MediaBag $bag, array $sources, array &$diagnostics): void
    {
        foreach ($sources as $source) {
            if (!str_starts_with($source, 'data:') || $bag->has($source)) {
                continue;
            }
            try {
                $bag->insertDataUri($source);
                $diagnostics[] = 'extract-media-data-uri-loaded';
            } catch (\InvalidArgumentException) {
                $diagnostics[] = 'extract-media-data-uri-invalid';
            }
        }
    }

    /**
     * @param list<string> $sources
     * @param list<string> $diagnostics
     */
    private function loadPackageImages(
        MediaBag $bag,
        array $sources,
        string $bytes,
        string $format,
        array &$diagnostics,
        ?EpubArchive $epubArchive = null
    ): void
    {
        if (!in_array($format, ['docx', 'epub', 'odt', 'odp', 'ods', 'pptx', 'xlsx'], true)) {
            return;
        }

        try {
            $zip = $format === 'epub' && $epubArchive !== null
                ? $epubArchive
                : ($format === 'epub'
                    ? EpubArchiveFactory::fromString($bytes)
                    : ZipPackage::fromString($bytes));
        } catch (\Throwable) {
            $diagnostics[] = 'extract-media-package-unreadable';

            return;
        }

        $names = $zip->names();
        foreach ($sources as $source) {
            if ($source === '' || str_starts_with($source, 'data:') || $bag->has($source)) {
                continue;
            }
            $entryName = $this->resolvePackageEntry($source, $format, $names);
            if ($entryName === null) {
                continue;
            }

            try {
                $contents = $zip->readBounded($entryName, self::MAX_PACKAGE_MEDIA_BYTES);
            } catch (\Throwable) {
                $diagnostics[] = 'extract-media-package-read-failed:' . $this->diagnosticToken($source);
                continue;
            }

            $bag->insertMedia($source, $this->mimeTypeFromPath($entryName), $contents);
            $diagnostics[] = 'extract-media-package-loaded:' . $this->diagnosticToken($source);
        }
    }

    /**
     * @param list<string> $names
     */
    private function resolvePackageEntry(string $source, string $format, array $names): ?string
    {
        $source = ltrim(str_replace('\\', '/', rawurldecode($source)), '/');
        if ($source === '' || str_contains($source, "\0")) {
            return null;
        }

        $nameSet = array_fill_keys($names, true);
        $candidates = [$source];
        if ($format === 'docx' && str_starts_with($source, 'media/')) {
            $candidates[] = 'word/' . $source;
        }
        if (in_array($format, ['odt', 'odp', 'ods'], true) && !str_starts_with($source, 'Pictures/')) {
            $candidates[] = 'Pictures/' . basename($source);
        }
        foreach ($candidates as $candidate) {
            if (isset($nameSet[$candidate])) {
                return $candidate;
            }
        }

        $suffixMatches = [];
        foreach ($names as $name) {
            if ($name === $source || str_ends_with($name, '/' . $source)) {
                $suffixMatches[] = $name;
            }
        }
        if (count($suffixMatches) === 1) {
            return $suffixMatches[0];
        }

        $basename = basename($source);
        if ($basename === $source || $basename === '') {
            return null;
        }
        $basenameMatches = [];
        foreach ($names as $name) {
            if (basename($name) === $basename && $this->looksLikeImagePath($name)) {
                $basenameMatches[] = $name;
            }
        }

        return count($basenameMatches) === 1 ? $basenameMatches[0] : null;
    }

    /**
     * @param list<string> $sources
     * @param list<string> $diagnostics
     */
    private function loadLocalImages(MediaBag $bag, array $sources, string $sourcePath, array &$diagnostics): void
    {
        $baseDir = is_dir($sourcePath) ? $sourcePath : dirname($sourcePath);
        foreach ($sources as $source) {
            if ($source === '' || $bag->has($source) || str_starts_with($source, 'data:') || $this->isUri($source)) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', rawurldecode($source)), '/');
            if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..')) {
                continue;
            }
            $path = $baseDir . '/' . $relative;
            if (!is_file($path) || !$this->looksLikeImagePath($path)) {
                continue;
            }
            $contents = file_get_contents($path);
            if (!is_string($contents)) {
                continue;
            }
            $bag->insertMedia($source, $this->mimeTypeFromPath($path), $contents);
            $diagnostics[] = 'extract-media-local-loaded:' . $this->diagnosticToken($source);
        }
    }

    /**
     * Prefer the compact placement map emitted by PdfReader. A direct media
     * extraction call can still inspect paint locations, but without final
     * text anchors it must not guess where an image belongs.
     *
     * @param list<string> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function pdfImagePlacements(AstNode $document, string $bytes, array &$diagnostics): array
    {
        $metadata = $document->attr('meta', []);
        $placements = is_array($metadata) ? ($metadata['pdfImagePlacements'] ?? null) : null;
        if (is_array($placements)) {
            $normalized = array_values(array_filter($placements, static fn (mixed $placement): bool => is_array($placement)));
            $diagnostics[] = 'extract-media-pdf-placement-metadata:' . count($normalized);

            return $normalized;
        }

        if (!class_exists(\PortLibs\MarkerPDF\PdfTextExtractor::class)) {
            $diagnostics[] = 'extract-media-pdf-placement-unavailable';

            return [];
        }

        try {
            $placements = (new \PortLibs\MarkerPDF\PdfTextExtractor())->extractImagePlacements($bytes);
            $diagnostics[] = 'extract-media-pdf-placement-unanchored-scan:' . count($placements);

            return $placements;
        } catch (\Throwable) {
            $diagnostics[] = 'extract-media-pdf-placement-scan-failed';

            return [];
        }
    }

    /**
     * Older placement metadata did not always carry the native occurrence
     * id. Derive a deterministic fallback without collapsing two legitimate
     * paintings of the same asset. Native ids remain unchanged unless an
     * invalid producer supplied the same id more than once.
     *
     * @param list<array<string,mixed>> $placements
     * @return list<array<string,mixed>>
     */
    private function pdfImageOccurrencesWithStableIdentity(array $placements): array
    {
        $normalized = [];
        $idCounts = [];
        foreach ($placements as $ordinal => $placement) {
            $page = max(1, (int) ($placement['page'] ?? 1));
            $paintOrder = is_numeric($placement['paintOrder'] ?? null)
                ? max(0, (int) $placement['paintOrder'])
                : $ordinal + 1;
            $object = is_numeric($placement['object'] ?? null) ? max(0, (int) $placement['object']) : 0;
            $id = is_string($placement['id'] ?? null) ? trim($placement['id']) : '';
            if ($id === '') {
                $id = 'pdf-image-p' . $page . '-n' . $paintOrder . '-o' . $object;
            }
            $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
            if ($idCounts[$id] > 1) {
                $id .= '-duplicate-' . $idCounts[$id];
            }
            $placement['id'] = $id;
            $placement['page'] = $page;
            $placement['paintOrder'] = $paintOrder;
            $placement['object'] = $object;
            $placement['sourceOccurrenceOrder'] = $ordinal;
            $normalized[] = $placement;
        }

        usort($normalized, static function (array $left, array $right): int {
            return ((int) $left['page']) <=> ((int) $right['page'])
                ?: ((int) $left['paintOrder']) <=> ((int) $right['paintOrder'])
                ?: ((int) $left['sourceOccurrenceOrder']) <=> ((int) $right['sourceOccurrenceOrder'])
                ?: strcmp((string) $left['id'], (string) $right['id']);
        });

        return $normalized;
    }

    /**
     * @param array<string,array<string,mixed>> $dispositions
     * @param array<string,mixed> $placement
     */
    private function setPdfImageOccurrenceDisposition(
        array &$dispositions,
        array $placement,
        string $disposition,
        string $reason
    ): void {
        $id = (string) ($placement['id'] ?? '');
        if ($id === '') {
            return;
        }
        $record = $dispositions[$id] ?? [
            'id' => $id,
            'kind' => (string) ($placement['kind'] ?? 'image-xobject'),
            'page' => max(1, (int) ($placement['page'] ?? 1)),
            'object' => max(0, (int) ($placement['object'] ?? 0)),
            'paintOrder' => max(0, (int) ($placement['paintOrder'] ?? 0)),
            'bbox' => is_array($placement['bbox'] ?? null) ? $placement['bbox'] : null,
        ];
        foreach (['source', 'mimeType', 'anchorIndex', 'anchorPosition'] as $key) {
            if (array_key_exists($key, $placement)) {
                $record[$key] = $placement[$key];
            }
        }
        $record['disposition'] = $disposition;
        $record['reason'] = $reason;
        $dispositions[$id] = $record;
    }

    /** @param array<string,array<string,mixed>> $dispositions */
    private function finalizePdfImageOccurrenceDispositions(array &$dispositions): void
    {
        foreach ($dispositions as $id => $record) {
            if (($record['disposition'] ?? 'pending') !== 'pending') {
                continue;
            }
            $record['disposition'] = 'unresolved';
            $record['reason'] = 'occurrence-not-finalized';
            $dispositions[$id] = $record;
        }
    }

    /** @param array<string,mixed> $record */
    private function pdfImageOccurrenceDiagnostic(array $record): string
    {
        $bbox = $this->pdfImagePlacementBoundingBoxKey($record['bbox'] ?? null);

        return 'extract-media-pdf-occurrence:'
            . $this->diagnosticToken((string) ($record['id'] ?? 'unknown'))
            . ':page-' . max(1, (int) ($record['page'] ?? 1))
            . ':paint-' . max(0, (int) ($record['paintOrder'] ?? 0))
            . ':bbox-' . ($bbox === '' ? 'none' : $this->diagnosticToken($bbox))
            . ':' . $this->diagnosticToken((string) ($record['disposition'] ?? 'unresolved'))
            . ':' . $this->diagnosticToken((string) ($record['reason'] ?? 'unknown'));
    }

    /**
     * @param list<array<string,mixed>> $dispositions
     */
    private function documentWithPdfImageOccurrenceDispositions(AstNode $document, array $dispositions): AstNode
    {
        $attrs = $document->attrs;
        $meta = is_array($attrs['meta'] ?? null) ? $attrs['meta'] : [];
        $meta['pdfMediaOccurrenceDispositions'] = $dispositions;
        $meta['pdfMediaOccurrenceComplete'] = !in_array(
            'unresolved',
            array_column($dispositions, 'disposition'),
            true
        );
        $imageBackedVisibleOverlay = false;
        foreach ($dispositions as $disposition) {
            if (($disposition['disposition'] ?? null) === 'resolved'
                && ($disposition['anchorPosition'] ?? null) === 'page-with-visible-overlay') {
                $imageBackedVisibleOverlay = true;
                break;
            }
        }
        if ($imageBackedVisibleOverlay) {
            $meta['pdfTextLayerStatus'] = 'unsupported_no_text';
            $meta['pdfNeedsOcr'] = true;
        }
        $processedPages = is_array($meta['pdfProcessedPageNumbers'] ?? null)
            ? array_values(array_unique(array_map('intval', $meta['pdfProcessedPageNumbers'])))
            : [];
        $representedPageSet = [];
        foreach ((is_array($meta['pdfRepresentedPageNumbers'] ?? null)
            ? $meta['pdfRepresentedPageNumbers']
            : []) as $page) {
            $page = (int) $page;
            if ($page > 0) {
                $representedPageSet[$page] = true;
            }
        }
        foreach ($dispositions as $disposition) {
            if (($disposition['disposition'] ?? null) !== 'resolved'
                || !in_array(
                    ($disposition['anchorPosition'] ?? null),
                    ['page-only', 'page-with-visible-overlay'],
                    true
                )) {
                continue;
            }
            $page = max(1, (int) ($disposition['page'] ?? 1));
            $representedPageSet[$page] = true;
        }
        $representedPages = array_keys($representedPageSet);
        sort($representedPages, SORT_NUMERIC);
        sort($processedPages, SORT_NUMERIC);
        $meta['pdfRepresentedPageNumbers'] = $representedPages;
        $meta['pdfPageRepresentationComplete'] = $processedPages !== []
            && $representedPages === $processedPages;
        $attrs['meta'] = $meta;

        return new AstNode($document->type, $attrs, $document->children);
    }

    /**
     * @param list<array<string, mixed>> $placements
     * @param list<string> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function anchoredPdfImagePlacements(
        AstNode $document,
        array $placements,
        array &$diagnostics,
        array &$occurrenceDispositions,
        string $imageMode,
        string $pdfBytes
    ): array
    {
        $anchored = [];
        $seen = [];
        $candidateCount = 0;
        $candidateCountByPage = [];
        $placementCountByPage = [];
        $candidateLimitReported = false;
        $placementLimitReported = false;
        $candidatePageLimitReported = [];
        $placementPageLimitReported = [];
        $clippedArtifactMediaAnchorContext = $this->validatedPdfClippedArtifactMediaAnchorContext(
            $document,
            $pdfBytes
        );
        $anchorDemand = $this->pdfImageAnchorDemand(
            $placements,
            $clippedArtifactMediaAnchorContext
        );
        $anchorUseCounts = [];
        $anchorEvidenceByPage = [];
        $pendingFallback = [];
        $pageOnlyPlacementModes = $this->pdfPageOnlyImagePlacementModes($document, $placements, $pdfBytes);
        foreach ($placements as $placement) {
            if (!$this->pdfImagePlacementIsEligible($placement)) {
                $sourceDisposition = (string) ($placement['disposition'] ?? '');
                $sourceReason = (string) ($placement['dispositionReason'] ?? 'placement-ineligible');
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    $sourceDisposition === 'intentional_omission' || $imageMode === 'important'
                        ? 'intentional_omission'
                        : 'unresolved',
                    $imageMode === 'important'
                        ? 'image-mode-placement-ineligible'
                        : ($sourceReason === '' ? 'placement-ineligible' : $sourceReason)
                );
                continue;
            }
            if (!is_numeric($placement['object'] ?? null) || (int) $placement['object'] < 0) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'image-object-invalid'
                );
                continue;
            }
            $page = max(1, (int) ($placement['page'] ?? 1));
            if ($candidateCount >= self::MAX_PDF_IMAGE_PLACEMENT_CANDIDATES) {
                if (!$candidateLimitReported) {
                    $diagnostics[] = 'extract-media-pdf-image-placement-candidate-limit';
                    $candidateLimitReported = true;
                }
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'image-placement-candidate-limit'
                );
                continue;
            }
            if (($candidateCountByPage[$page] ?? 0) >= self::MAX_PDF_IMAGE_PLACEMENT_CANDIDATES_PER_PAGE) {
                if (!isset($candidatePageLimitReported[$page])) {
                    $diagnostics[] = 'extract-media-pdf-image-placement-candidate-page-limit:' . $page;
                    $candidatePageLimitReported[$page] = true;
                }
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'image-placement-candidate-page-limit'
                );
                continue;
            }
            $candidateCount++;
            $candidateCountByPage[$page] = ($candidateCountByPage[$page] ?? 0) + 1;

            $placementId = (string) ($placement['id'] ?? '');
            if ($placementId !== '' && isset($pageOnlyPlacementModes[$placementId])) {
                $placement['anchorIndex'] = -1;
                $placement['anchorPosition'] = $pageOnlyPlacementModes[$placementId];
                $placement['anchorEvidence'] = 'normative-page-box-coverage';
                $diagnostics[] = 'extract-media-pdf-page-image-anchor:'
                    . (string) $placement['object'] . ':page-' . $page;
                $this->appendAnchoredPdfImagePlacement(
                    $placement,
                    $anchored,
                    $seen,
                    $placementCountByPage,
                    $placementLimitReported,
                    $placementPageLimitReported,
                    $diagnostics,
                    $occurrenceDispositions
                );
                continue;
            }

            $clippedArtifactAnchorSide = $this->validatedPdfClippedArtifactMediaAnchorSide(
                $placement,
                $page,
                $clippedArtifactMediaAnchorContext
            );
            $following = $clippedArtifactAnchorSide === 'following'
                ? null
                : $this->pdfImageTextAnchorMatch(
                    $document,
                    $placement['followingText'] ?? null,
                    $page,
                    $anchorDemand,
                    $anchorUseCounts
                );
            $followingText = is_string($placement['followingText'] ?? null)
                ? $this->normalizedPdfImageAnchorText($placement['followingText'])
                : '';
            $precedingText = is_string($placement['precedingText'] ?? null)
                ? $this->normalizedPdfImageAnchorText($placement['precedingText'])
                : '';
            $preceding = $clippedArtifactAnchorSide === 'preceding'
                ? null
                : ($precedingText !== ''
                    && $precedingText === $followingText
                    && $clippedArtifactAnchorSide === null
                        ? $following
                        : $this->pdfImageTextAnchorMatch(
                            $document,
                            $placement['precedingText'] ?? null,
                            $page,
                            $anchorDemand,
                            $anchorUseCounts
                        ));
            if ($following === null && $preceding === null) {
                $pendingFallback[] = $placement;
                continue;
            }
            if ($following !== null && $preceding !== null && $preceding['index'] >= $following['index']) {
                // PDF paint order and the final prose order can disagree.
                // When the two geometry anchors would bracket the image in
                // reverse AST order, choosing one could move it to a wholly
                // unrelated point in the document.
                $diagnostics[] = 'extract-media-pdf-image-anchor-order-conflict:' . (string) $placement['object'];
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'image-anchor-order-conflict'
                );
                continue;
            }

            $selectedAnchor = $following ?? $preceding;
            $placement['anchorIndex'] = $selectedAnchor['index'];
            $placement['anchorPosition'] = $following !== null ? 'before' : 'after';
            $placement['anchorEvidence'] = $selectedAnchor['quality'];
            if ($imageMode === 'important' && $selectedAnchor['quality'] === 'contained') {
                if ($clippedArtifactAnchorSide !== null) {
                    $pendingFallback[] = $placement;
                    continue;
                }
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'intentional_omission',
                    'image-mode-weak-semantic-anchor'
                );
                continue;
            }
            $anchorEvidenceByPage[$page][] = $this->pdfImageAnchorGeometryEvidence($placement);
            $this->appendAnchoredPdfImagePlacement(
                $placement,
                $anchored,
                $seen,
                $placementCountByPage,
                $placementLimitReported,
                $placementPageLimitReported,
                $diagnostics,
                $occurrenceDispositions
            );
        }

        foreach ($pendingFallback as $placement) {
            $page = max(1, (int) ($placement['page'] ?? 1));
            if ($this->pdfImageOccurrencePageIsMissing($document, $page)) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'missing-page-occurrence'
                );
                continue;
            }
            $fallback = $this->pdfImagePageRegionFallbackAnchor(
                $placement,
                $anchorEvidenceByPage[$page] ?? []
            );
            if ($fallback === null) {
                $diagnostics[] = 'extract-media-pdf-image-unanchored:' . (string) $placement['object'];
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    $imageMode === 'important' ? 'intentional_omission' : 'unresolved',
                    $imageMode === 'important'
                        ? 'image-mode-no-semantic-region-anchor'
                        : 'image-placement-unanchored'
                );
                continue;
            }
            $placement['anchorIndex'] = $fallback['index'];
            $placement['anchorPosition'] = $fallback['position'];
            $clippedArtifactAnchorAuthorized = $this->validatedPdfClippedArtifactMediaAnchorSide(
                $placement,
                $page,
                $clippedArtifactMediaAnchorContext
            ) !== null;
            $placement['anchorEvidence'] = $clippedArtifactAnchorAuthorized
                ? 'clipped-artifact-counterpart-page-region-y-paint'
                : 'page-region-y-paint';
            $diagnostics[] = 'extract-media-pdf-image-region-fallback:'
                . (string) $placement['object'] . ':page-' . $page;
            if ($clippedArtifactAnchorAuthorized) {
                $diagnostics[] = 'extract-media-pdf-image-clipped-artifact-anchor:'
                    . (string) $placement['object'] . ':page-' . $page;
            }
            if ($imageMode === 'important' && !$clippedArtifactAnchorAuthorized) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'intentional_omission',
                    'image-mode-region-only-anchor'
                );
                continue;
            }
            $this->appendAnchoredPdfImagePlacement(
                $placement,
                $anchored,
                $seen,
                $placementCountByPage,
                $placementLimitReported,
                $placementPageLimitReported,
                $diagnostics,
                $occurrenceDispositions
            );
        }

        return $anchored;
    }

    /**
     * A single-page document with no usable body text has no ordinary semantic
     * block to anchor its page scan against. Retain one image only when
     * normative CropBox or MediaBox evidence proves that it paints essentially
     * the complete page. A sparse visible stamp may follow that image when a
     * much larger non-painting OCR layer proves the page still needs OCR and
     * visibility/occlusion accounting proves the stamp was not hidden by later
     * paint. Keeping exactly one eligible visual occurrence prevents this
     * narrow path from turning arbitrary captionless images into a gallery.
     *
     * @param list<array<string,mixed>> $placements
     * @return array<string,'page-only'|'page-with-visible-overlay'>
     */
    private function pdfPageOnlyImagePlacementModes(
        AstNode $document,
        array $placements,
        string $pdfBytes
    ): array
    {
        $meta = $document->attr('meta', []);
        if (!is_array($meta)
            || ($meta['pdfTextVisibilityComplete'] ?? null) !== true
            || ($meta['pdfTextComplete'] ?? null) !== true
            || ($meta['pdfRangeComplete'] ?? null) !== true
            || ($meta['pdfDocumentComplete'] ?? null) !== true
            || ($meta['pdfSemanticTextComplete'] ?? null) !== true
            || (int) ($meta['pdfPageCount'] ?? 0) !== 1
            || (int) ($meta['pdfPagesProcessed'] ?? 0) !== 1
            || array_values($meta['pdfProcessedPageNumbers'] ?? []) !== [1]
            || ($meta['pdfHasMorePages'] ?? null) !== false
            || ($meta['pdfPageLimitApplied'] ?? null) !== false
            || !is_array($meta['pdfPageExtractionIssues'] ?? null)
            || $meta['pdfPageExtractionIssues'] !== []
            || !$this->pdfPageImageAnnotationsAreAbsent($meta)
            || count($placements) !== 1) {
            return [];
        }
        $visibility = is_array($meta['pdfTextVisibility'] ?? null) ? $meta['pdfTextVisibility'] : [];
        $visibleRuns = (int) ($visibility['visibleOutputRuns'] ?? -1);
        $textLines = (int) ($meta['pdfTextLines'] ?? -1);
        $textRuns = (int) ($meta['pdfTextRuns'] ?? -1);
        $strictNoText = $document->children === []
            && ($meta['pdfTextLayerStatus'] ?? null) === 'unsupported_no_text'
            && $textLines === 0
            && $textRuns === 0
            && $visibleRuns === 0;
        $suppressedNonPaintingRuns = (int) ($visibility['suppressedNonPaintingRuns'] ?? 0);
        $sparseVisibleOverlay = $document->children !== []
            && count($document->children) <= 2
            && in_array(($meta['pdfTextLayerStatus'] ?? null), ['visible_text', 'incomplete'], true)
            && $textLines >= 1
            && $textLines <= 2
            && $textRuns >= 1
            && $textRuns <= 2
            && $visibleRuns === $textRuns
            && $suppressedNonPaintingRuns >= max(4, $visibleRuns * 4)
            && (int) ($visibility['suppressedRenderingModeRuns'] ?? -1) === $suppressedNonPaintingRuns
            && (int) ($visibility['unresolvedRuns'] ?? -1) === 0
            && (int) ($visibility['unresolvedOcclusionRiskRuns'] ?? -1) === 0;
        if (!$strictNoText && !$sparseVisibleOverlay) {
            return [];
        }

        $eligible = [];
        foreach ($placements as $placement) {
            if (!$this->pdfImagePlacementIsEligible($placement)
                || ($placement['kind'] ?? 'image-xobject') !== 'image-xobject'
                || (int) ($placement['object'] ?? 0) < 1
                || ($placement['imageMask'] ?? false) === true
                || ($placement['visible'] ?? false) !== true
                || ($placement['confidence'] ?? '') !== 'high'
                || ($placement['boundsClipped'] ?? false) === true
                || ($placement['disposition'] ?? null) !== 'pending'
                || ($placement['dispositionReason'] ?? null) !== null
                || (int) ($placement['pageRotation'] ?? -1) !== 0
                || (int) ($placement['page'] ?? 0) !== 1
                || !$this->pdfPageImageHasOpaqueSelfContainedCompositing($placement)
                || !$this->pdfImagePlacementHasPageAlignedMatrix($placement)
                || !$this->pdfImagePlacementCoversNormativePage($placement)) {
                continue;
            }
            $eligible[] = $placement;
        }
        if (count($eligible) !== 1) {
            return [];
        }
        $id = trim((string) ($eligible[0]['id'] ?? ''));
        if ($id === '' || !class_exists(\PortLibs\MarkerPDF\PdfTextExtractor::class)) {
            return [];
        }
        try {
            $extractor = new \PortLibs\MarkerPDF\PdfTextExtractor();
            $visualOccurrences = $extractor->extractVisualOccurrences($pdfBytes);
            $selectedAssets = $extractor->extractPaintedImageAssets(
                $pdfBytes,
                [(int) ($eligible[0]['object'] ?? 0)]
            );
            $pageAnnotationsPresent = $extractor->pageAnnotationsPresent($pdfBytes);
        } catch (\Throwable) {
            return [];
        }
        if ($pageAnnotationsPresent !== false
            || count($visualOccurrences) !== 1
            || count($selectedAssets) !== 1) {
            return [];
        }
        $onlyVisual = $visualOccurrences[0];
        if (!is_array($onlyVisual)
            || ($onlyVisual['kind'] ?? null) !== 'image-xobject'
            || ($onlyVisual['disposition'] ?? null) !== 'pending'
            || ($onlyVisual['dispositionReason'] ?? null) !== null
            || !$this->pdfPageImageOccurrenceMatchesPlacement($eligible[0], $onlyVisual)
            || !$this->pdfPageImageAssetIsBrowserSafeAndSelfContained($selectedAssets[0], $id)) {
            return [];
        }

        return [$id => $sparseVisibleOverlay ? 'page-with-visible-overlay' : 'page-only'];
    }

    /** @param array<string,mixed> $meta */
    private function pdfPageImageAnnotationsAreAbsent(array $meta): bool
    {
        foreach ([
            'pdfLinkAnnotations',
            'pdfTextAnnotations',
            'pdfFileAttachmentAnnotations',
            'pdfPopupAnnotations',
            'pdfAppearanceAnnotations',
        ] as $key) {
            if (!is_array($meta[$key] ?? null) || $meta[$key] !== []) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $placement */
    private function pdfPageImageHasOpaqueSelfContainedCompositing(array $placement): bool
    {
        $alpha = $placement['nonStrokingAlpha'] ?? null;

        return ($placement['compositingKnown'] ?? null) === true
            && ($placement['requiresCompositing'] ?? null) === false
            && ($placement['sourceImageHasSoftMask'] ?? null) === false
            && ($placement['sourceImageHasExplicitMask'] ?? null) === false
            && ($placement['sourceImageHasOptionalContent'] ?? null) === false
            && ($placement['sourceImageHasIntent'] ?? null) === false
            && ($placement['sourceImageHasInterpolate'] ?? null) === false
            && ($placement['graphicsStateHasSoftMask'] ?? null) === false
            && ($placement['graphicsStateBlendModeNormal'] ?? null) === true
            && ($placement['pageImageGraphicsStateSafe'] ?? null) === true
            && ($placement['pageHasGroup'] ?? null) === false
            && ($placement['pageHasDefaultDeviceColorSpace'] ?? null) === false
            && is_numeric($alpha)
            && is_finite((float) $alpha)
            && abs((float) $alpha - 1.0) <= 0.000001;
    }

    /**
     * The document metadata and the fresh xref-selected inventory are two
     * independent inputs. Bind the complete occurrence identity and geometry,
     * not just an object number or stable-looking id, before a raw asset can
     * stand in for the page.
     *
     * @param array<string,mixed> $placement
     * @param array<string,mixed> $occurrence
     */
    private function pdfPageImageOccurrenceMatchesPlacement(array $placement, array $occurrence): bool
    {
        $id = (string) ($placement['id'] ?? '');
        if ($id === '' || !hash_equals($id, (string) ($occurrence['id'] ?? ''))) {
            return false;
        }
        foreach (['page', 'pageObject', 'contentStream', 'paintOrder', 'object', 'pageBoxObject', 'pageRotation'] as $key) {
            if (!is_numeric($placement[$key] ?? null)
                || !is_numeric($occurrence[$key] ?? null)
                || (int) $placement[$key] !== (int) $occurrence[$key]) {
                return false;
            }
        }
        foreach (['resource', 'pageBoxSource'] as $key) {
            if (!is_string($placement[$key] ?? null)
                || !hash_equals((string) $placement[$key], (string) ($occurrence[$key] ?? ''))) {
                return false;
            }
        }
        foreach ([
            'pageHasGroup',
            'pageHasDefaultDeviceColorSpace',
            'pageImageGraphicsStateSafe',
            'sourceImageHasOptionalContent',
            'sourceImageHasIntent',
            'sourceImageHasInterpolate',
        ] as $key) {
            if (!is_bool($placement[$key] ?? null)
                || !is_bool($occurrence[$key] ?? null)
                || $placement[$key] !== $occurrence[$key]) {
                return false;
            }
        }
        if (!is_array($placement['resourcePath'] ?? null)
            || !is_array($occurrence['resourcePath'] ?? null)
            || array_values($placement['resourcePath']) !== array_values($occurrence['resourcePath'])) {
            return false;
        }

        return $this->pdfPageImageNumericArrayMatches($placement['matrix'] ?? null, $occurrence['matrix'] ?? null)
            && $this->pdfPageImageRectangleMatches($placement['bbox'] ?? null, $occurrence['bbox'] ?? null)
            && $this->pdfPageImageRectangleMatches($placement['pageBox'] ?? null, $occurrence['pageBox'] ?? null);
    }

    private function pdfPageImageNumericArrayMatches(mixed $left, mixed $right): bool
    {
        if (!is_array($left) || !is_array($right) || count($left) !== count($right) || $left === []) {
            return false;
        }
        foreach (array_values($left) as $index => $value) {
            $other = array_values($right)[$index] ?? null;
            if (!is_numeric($value) || !is_numeric($other)
                || !is_finite((float) $value) || !is_finite((float) $other)
                || abs((float) $value - (float) $other) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    private function pdfPageImageRectangleMatches(mixed $left, mixed $right): bool
    {
        if (!is_array($left) || !is_array($right)) {
            return false;
        }
        foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
            if (!is_numeric($left[$coordinate] ?? null)
                || !is_numeric($right[$coordinate] ?? null)
                || !is_finite((float) $left[$coordinate])
                || !is_finite((float) $right[$coordinate])
                || abs((float) $left[$coordinate] - (float) $right[$coordinate]) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $asset */
    private function pdfPageImageAssetIsBrowserSafeAndSelfContained(array $asset, string $occurrenceId): bool
    {
        $dictionary = is_string($asset['dictionary'] ?? null) ? $asset['dictionary'] : '';
        $stream = is_string($asset['stream'] ?? null) ? $asset['stream'] : null;
        $width = is_numeric($asset['width'] ?? null) ? (int) $asset['width'] : 0;
        $height = is_numeric($asset['height'] ?? null) ? (int) $asset['height'] : 0;
        $filters = is_array($asset['filters'] ?? null)
            ? array_values(array_filter($asset['filters'], 'is_string'))
            : [];
        $occurrenceIds = is_array($asset['occurrenceIds'] ?? null)
            ? array_values(array_filter($asset['occurrenceIds'], 'is_string'))
            : [];
        if (($asset['availability'] ?? null) !== 'available'
            || (int) ($asset['object'] ?? 0) < 1
            || ($asset['imageMask'] ?? null) !== false
            || $dictionary === ''
            || $stream === null
            || $stream === ''
            || $width < 1
            || $height < 1
            || $width * $height > self::MAX_PDF_PAGE_IMAGE_PIXELS
            || $occurrenceIds !== [$occurrenceId]
            || preg_match(
                '/\/(?:SMask|Mask|Decode|DecodeParms|DP|OC|Intent|Interpolate)(?![A-Za-z0-9_.#-])/',
                $dictionary
            ) === 1) {
            return false;
        }
        if (preg_match(
            '/\/(?:ColorSpace|CS)(?![A-Za-z0-9_.#-])\s*\/(DeviceGray|DeviceRGB)(?![A-Za-z0-9_.#-])/',
            $dictionary,
            $colorSpaceMatch
        ) !== 1) {
            return false;
        }
        $colorSpace = (string) $colorSpaceMatch[1];
        $components = $colorSpace === 'DeviceGray' ? 1 : 3;
        if (($this->pdfIntegerEntry($dictionary, 'BitsPerComponent') ?? 0) !== 8) {
            return false;
        }

        if ($filters === ['DCTDecode'] || $filters === ['DCT']) {
            $dimensions = $this->pdfJpegDimensions($stream);
            if (!is_array($dimensions)
                || $dimensions['width'] !== $width
                || $dimensions['height'] !== $height) {
                return false;
            }
            $sanitized = $this->pdfSanitizedPageJpeg($stream, $width, $height);
            if ($sanitized === null) {
                return false;
            }
            $this->sanitizedPageJpegs[$this->pdfPageJpegCacheKey($stream, $width, $height)] = $sanitized;

            return true;
        }
        if ($filters === ['FlateDecode'] || $filters === ['Fl']) {
            $expectedBytes = $width * $height * $components;
            $decoded = $this->pdfFlateDecode($stream, $expectedBytes);

            return is_string($decoded) && strlen($decoded) === $expectedBytes;
        }

        return false;
    }

    /** @return array{width:int,height:int}|null */
    private function pdfJpegDimensions(string $bytes): ?array
    {
        $length = strlen($bytes);
        if ($length < 12 || substr($bytes, 0, 2) !== "\xff\xd8" || substr($bytes, -2) !== "\xff\xd9") {
            return null;
        }
        $offset = 2;
        $dimensions = null;
        while ($offset + 4 <= $length) {
            if (ord($bytes[$offset]) !== 0xff) {
                return null;
            }
            while ($offset < $length && ord($bytes[$offset]) === 0xff) {
                $offset++;
            }
            if ($offset >= $length) {
                return null;
            }
            $marker = ord($bytes[$offset++]);
            if ($marker === 0xd9) {
                return $dimensions;
            }
            if ($marker === 0xda) {
                return $dimensions;
            }
            if ($marker === 0x01 || ($marker >= 0xd0 && $marker <= 0xd8)) {
                continue;
            }
            if ($offset + 2 > $length) {
                return null;
            }
            $segmentLength = unpack('nlength', substr($bytes, $offset, 2));
            $segmentLength = is_array($segmentLength) ? (int) ($segmentLength['length'] ?? 0) : 0;
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                return null;
            }
            if (in_array($marker, [0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf], true)) {
                if ($segmentLength < 7) {
                    return null;
                }
                $size = unpack('nheight/nwidth', substr($bytes, $offset + 3, 4));
                $width = is_array($size) ? (int) ($size['width'] ?? 0) : 0;
                $height = is_array($size) ? (int) ($size['height'] ?? 0) : 0;
                if ($width < 1 || $height < 1) {
                    return null;
                }
                $dimensions = ['width' => $width, 'height' => $height];
            }
            $offset += $segmentLength;
        }

        return null;
    }

    private function pdfPageJpegCacheKey(string $bytes, int $width, int $height): string
    {
        return hash('sha256', $bytes) . ':' . $width . 'x' . $height;
    }

    /**
     * Decode the complete JPEG and re-encode only its displayed RGB pixels.
     * This both proves the bounded source is decodable and removes source APP,
     * COM, ICC, EXIF, and orientation metadata before the page image is hosted
     * as a standalone browser asset.
     */
    private function pdfSanitizedPageJpeg(string $bytes, int $width, int $height): ?string
    {
        if (!function_exists('imagecreatefromstring')
            || !function_exists('imagesx')
            || !function_exists('imagesy')
            || !function_exists('imagejpeg')) {
            return null;
        }
        try {
            $image = @imagecreatefromstring($bytes);
        } catch (\Throwable) {
            return null;
        }
        if ($image === false) {
            return null;
        }

        $bufferLevel = ob_get_level();
        try {
            if (@imagesx($image) !== $width || @imagesy($image) !== $height) {
                return null;
            }
            if (!ob_start()) {
                return null;
            }
            $encoded = @imagejpeg($image, null, 90);
            $sanitized = ob_get_contents();
            if ($encoded !== true
                || !is_string($sanitized)
                || $sanitized === '') {
                return null;
            }
            $sanitized = $this->pdfJpegWithoutMetadataSegments($sanitized);
            if ($sanitized === null || strlen($sanitized) > self::MAX_PDF_RASTER_IMAGE_BYTES) {
                return null;
            }
            $dimensions = $this->pdfJpegDimensions($sanitized);
            if (!is_array($dimensions)
                || $dimensions['width'] !== $width
                || $dimensions['height'] !== $height) {
                return null;
            }

            return $sanitized;
        } catch (\Throwable) {
            return null;
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            if (function_exists('imagedestroy')) {
                @imagedestroy($image);
            }
        }
    }

    /**
     * The GD re-encode already severs source metadata from the pixels. Remove
     * the encoder's replacement APP/COM records too, so the hosted page image
     * contains only JPEG coding tables, dimensions, and scan data.
     */
    private function pdfJpegWithoutMetadataSegments(string $bytes): ?string
    {
        $length = strlen($bytes);
        if ($length < 4 || substr($bytes, 0, 2) !== "\xff\xd8") {
            return null;
        }
        $output = "\xff\xd8";
        $offset = 2;
        while ($offset + 2 <= $length) {
            $markerStart = $offset;
            if (ord($bytes[$offset]) !== 0xff) {
                return null;
            }
            while ($offset < $length && ord($bytes[$offset]) === 0xff) {
                $offset++;
            }
            if ($offset >= $length) {
                return null;
            }
            $marker = ord($bytes[$offset++]);
            if ($marker === 0xda) {
                $output .= substr($bytes, $markerStart);

                return $output;
            }
            if ($marker === 0xd9) {
                return null;
            }
            if ($marker === 0x01 || ($marker >= 0xd0 && $marker <= 0xd8)) {
                $output .= substr($bytes, $markerStart, $offset - $markerStart);
                continue;
            }
            if ($offset + 2 > $length) {
                return null;
            }
            $segmentLength = unpack('nlength', substr($bytes, $offset, 2));
            $segmentLength = is_array($segmentLength) ? (int) ($segmentLength['length'] ?? 0) : 0;
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                return null;
            }
            $segmentEnd = $offset + $segmentLength;
            $isMetadata = ($marker >= 0xe0 && $marker <= 0xef) || $marker === 0xfe;
            if (!$isMetadata) {
                $output .= substr($bytes, $markerStart, $segmentEnd - $markerStart);
            }
            $offset = $segmentEnd;
        }

        return null;
    }

    /** @param array<string,mixed> $placement */
    private function pdfImagePlacementHasPageAlignedMatrix(array $placement): bool
    {
        $matrix = $placement['matrix'] ?? null;
        if (!is_array($matrix) || count($matrix) !== 6) {
            return false;
        }
        foreach ($matrix as $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) {
                return false;
            }
        }

        return (float) $matrix[0] > 0.000001
            && abs((float) $matrix[1]) <= 0.000001
            && abs((float) $matrix[2]) <= 0.000001
            && (float) $matrix[3] > 0.000001;
    }

    /** @param array<string,mixed> $placement */
    private function pdfImagePlacementCoversNormativePage(array $placement): bool
    {
        if (!in_array((string) ($placement['pageBoxSource'] ?? ''), ['CropBox', 'MediaBox'], true)
            || !is_numeric($placement['pageBoxObject'] ?? null)
            || (int) $placement['pageBoxObject'] < 1) {
            return false;
        }
        $image = is_array($placement['bbox'] ?? null) ? $placement['bbox'] : [];
        $page = is_array($placement['pageBox'] ?? null) ? $placement['pageBox'] : [];
        foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
            if (!is_numeric($image[$coordinate] ?? null)
                || !is_finite((float) $image[$coordinate])
                || !is_numeric($page[$coordinate] ?? null)
                || !is_finite((float) $page[$coordinate])) {
                return false;
            }
        }
        $pageX1 = min((float) $page['x1'], (float) $page['x2']);
        $pageY1 = min((float) $page['y1'], (float) $page['y2']);
        $pageX2 = max((float) $page['x1'], (float) $page['x2']);
        $pageY2 = max((float) $page['y1'], (float) $page['y2']);
        $imageX1 = min((float) $image['x1'], (float) $image['x2']);
        $imageY1 = min((float) $image['y1'], (float) $image['y2']);
        $imageX2 = max((float) $image['x1'], (float) $image['x2']);
        $imageY2 = max((float) $image['y1'], (float) $image['y2']);
        $pageArea = ($pageX2 - $pageX1) * ($pageY2 - $pageY1);
        if ($pageArea <= 0.000001 || $imageX2 <= $imageX1 || $imageY2 <= $imageY1) {
            return false;
        }
        $intersectionWidth = max(0.0, min($pageX2, $imageX2) - max($pageX1, $imageX1));
        $intersectionHeight = max(0.0, min($pageY2, $imageY2) - max($pageY1, $imageY1));
        $pageWidth = $pageX2 - $pageX1;
        $pageHeight = $pageY2 - $pageY1;
        $edgeEpsilon = max(0.01, max($pageWidth, $pageHeight) * 0.000001);
        $materialOverhang = $imageX1 < $pageX1 - $edgeEpsilon
            || $imageX2 > $pageX2 + $edgeEpsilon
            || $imageY1 < $pageY1 - $edgeEpsilon
            || $imageY2 > $pageY2 + $edgeEpsilon;

        return !$materialOverhang
            && ($intersectionWidth * $intersectionHeight) / $pageArea >= 0.98;
    }

    /**
     * Apply occurrence-level duplicate and placement limits after an anchor
     * has been selected. This keeps every source painting dispositioned even
     * when a deterministic page-region fallback reaches a document cap.
     *
     * @param array<string,mixed> $placement
     * @param list<array<string,mixed>> $anchored
     * @param array<string,bool> $seen
     * @param array<int,int> $placementCountByPage
     * @param array<int,bool> $placementPageLimitReported
     * @param list<string> $diagnostics
     * @param array<string,array<string,mixed>> $occurrenceDispositions
     */
    private function appendAnchoredPdfImagePlacement(
        array $placement,
        array &$anchored,
        array &$seen,
        array &$placementCountByPage,
        bool &$placementLimitReported,
        array &$placementPageLimitReported,
        array &$diagnostics,
        array &$occurrenceDispositions
    ): void {
        $page = max(1, (int) ($placement['page'] ?? 1));
        $key = (string) ((int) $placement['object'])
            . ':' . $page
            . ':' . (string) $placement['anchorIndex']
            . ':' . $placement['anchorPosition']
            . ':' . (string) ($placement['paintOrder'] ?? '')
            . ':' . $this->pdfImagePlacementBoundingBoxKey($placement['bbox'] ?? null);
        if (isset($seen[$key])) {
            $diagnostics[] = 'extract-media-pdf-image-placement-duplicate:' . (string) $placement['object'];
            $this->setPdfImageOccurrenceDisposition(
                $occurrenceDispositions,
                $placement,
                'unresolved',
                'duplicate-placement-record'
            );
            return;
        }
        if (count($anchored) >= self::MAX_PDF_IMAGE_PLACEMENTS) {
            if (!$placementLimitReported) {
                $diagnostics[] = 'extract-media-pdf-image-placement-limit';
                $placementLimitReported = true;
            }
            $this->setPdfImageOccurrenceDisposition(
                $occurrenceDispositions,
                $placement,
                'unresolved',
                'image-placement-limit'
            );
            return;
        }
        if (($placementCountByPage[$page] ?? 0) >= self::MAX_PDF_IMAGE_PLACEMENTS_PER_PAGE) {
            if (!isset($placementPageLimitReported[$page])) {
                $diagnostics[] = 'extract-media-pdf-image-placement-page-limit:' . $page;
                $placementPageLimitReported[$page] = true;
            }
            $this->setPdfImageOccurrenceDisposition(
                $occurrenceDispositions,
                $placement,
                'unresolved',
                'image-placement-page-limit'
            );
            return;
        }
        $seen[$key] = true;
        $anchored[] = $placement;
        $placementCountByPage[$page] = ($placementCountByPage[$page] ?? 0) + 1;
    }

    /**
     * Count repeated anchor demand by both occurrence and source page. A
     * caption repeated once per page is a page-scoped bijection even when
     * several image occurrences on each page share that same caption.
     *
     * @param list<array<string,mixed>> $placements
     * @param array<string,array{page:int,artifactSourceOccurrenceId:string,artifactProjectionDigest:string}> $clippedArtifactContext
     * @return array<string,array{count:int,pages:list<int>}>
     */
    private function pdfImageAnchorDemand(
        array $placements,
        array $clippedArtifactContext
    ): array
    {
        $demand = [];
        foreach ($placements as $placement) {
            if (!$this->pdfImagePlacementIsEligible($placement)) {
                continue;
            }
            $page = max(1, (int) ($placement['page'] ?? 1));
            $clippedArtifactSide = $this->validatedPdfClippedArtifactMediaAnchorSide(
                $placement,
                $page,
                $clippedArtifactContext
            );
            $seenForOccurrence = [];
            foreach (['preceding' => 'precedingText', 'following' => 'followingText'] as $side => $key) {
                if ($clippedArtifactSide === $side) {
                    continue;
                }
                $anchor = $placement[$key] ?? null;
                if (!is_string($anchor)) {
                    continue;
                }
                $anchor = $this->normalizedPdfImageAnchorText($anchor);
                if ($anchor === '' || strlen($anchor) < 3 || isset($seenForOccurrence[$anchor])) {
                    continue;
                }
                $seenForOccurrence[$anchor] = true;
                $record = $demand[$anchor] ?? ['count' => 0, 'pages' => []];
                $record['count']++;
                if (!in_array($page, $record['pages'], true)) {
                    $record['pages'][] = $page;
                    sort($record['pages'], SORT_NUMERIC);
                }
                $demand[$anchor] = $record;
            }
        }

        return $demand;
    }

    private function pdfImageOccurrencePageIsMissing(AstNode $document, int $page): bool
    {
        $meta = $document->attr('meta', []);
        if (!is_array($meta)) {
            return false;
        }
        $processedPages = $meta['pdfProcessedPageNumbers'] ?? null;
        if (!is_array($processedPages) || $processedPages === []) {
            return false;
        }

        return !in_array($page, array_map('intval', $processedPages), true);
    }

    /**
     * The PDF reader distinguishes a safely anchored clipped image from an
     * image whose geometry is unsafe. Keep backward compatibility with
     * high-confidence metadata emitted before that distinction existed.
     *
     * @param array<string, mixed> $placement
     */
    private function pdfImagePlacementIsEligible(array $placement): bool
    {
        if (array_key_exists('placementEligible', $placement)) {
            return $placement['placementEligible'] === true;
        }

        return ($placement['visible'] ?? false) === true
            && ($placement['confidence'] ?? '') === 'high';
    }

    private function pdfImagePlacementBoundingBoxKey(mixed $bbox): string
    {
        if (!is_array($bbox)) {
            return '';
        }

        $coordinates = [];
        foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
            $value = $bbox[$coordinate] ?? null;
            if (!is_numeric($value)) {
                return '';
            }
            $coordinates[] = rtrim(rtrim(sprintf('%.4F', (float) $value), '0'), '.');
        }

        return implode(',', $coordinates);
    }

    /**
     * Resolve repeated text page-locally before using the legacy exact
     * occurrence bijection. Prefix matches are strong enough for a semantic
     * block that merged adjacent source lines; a text fragment merely found
     * inside a larger block remains deliberately weak.
     *
     * @param array<string,array{count:int,pages:list<int>}> $anchorDemand
     * @param array<string,int> $anchorUseCounts
     * @return array{index:int,quality:string}|null
     */
    private function pdfImageTextAnchorMatch(
        AstNode $document,
        mixed $anchor,
        int $page,
        array $anchorDemand,
        array &$anchorUseCounts
    ): ?array
    {
        if (!is_string($anchor)) {
            return null;
        }
        $anchor = $this->normalizedPdfImageAnchorText($anchor);
        if ($anchor === '' || strlen($anchor) < 3) {
            return null;
        }

        $candidates = [];
        foreach ($document->children as $candidateIndex => $block) {
            $text = $this->normalizedPdfImageAnchorText($this->pdfImageAnchorBlockText($block));
            if ($text === '' || !str_contains($text, $anchor)) {
                continue;
            }
            $candidates[] = [
                'index' => $candidateIndex,
                'quality' => $text === $anchor
                    ? 'exact'
                    : (str_starts_with($text, $anchor) ? 'prefix' : 'contained'),
            ];
        }
        $strongCandidates = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['quality'] !== 'contained'
        ));
        if ($strongCandidates !== []) {
            $candidates = $strongCandidates;
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        $demand = $anchorDemand[$anchor] ?? ['count' => 0, 'pages' => []];
        if (count($candidates) === count($demand['pages'])) {
            $pageIndex = array_search($page, $demand['pages'], true);
            if (is_int($pageIndex) && isset($candidates[$pageIndex])) {
                return $candidates[$pageIndex];
            }
        }
        if ($demand['count'] < 1 || count($candidates) !== $demand['count']) {
            return null;
        }
        $use = $anchorUseCounts[$anchor] ?? 0;
        if (!isset($candidates[$use])) {
            return null;
        }
        $anchorUseCounts[$anchor] = $use + 1;

        return $candidates[$use];
    }

    /**
     * @param array<string,mixed> $placement
     * @return array{index:int,position:string,x1:float|null,y1:float|null,x2:float|null,y2:float|null,paintOrder:int}
     */
    private function pdfImageAnchorGeometryEvidence(array $placement): array
    {
        $bbox = is_array($placement['bbox'] ?? null) ? $placement['bbox'] : [];
        $number = static fn (mixed $value): ?float => is_numeric($value) && is_finite((float) $value)
            ? (float) $value
            : null;

        return [
            'index' => (int) $placement['anchorIndex'],
            'position' => ($placement['anchorPosition'] ?? '') === 'after' ? 'after' : 'before',
            'x1' => $number($bbox['x1'] ?? null),
            'y1' => $number($bbox['y1'] ?? null),
            'x2' => $number($bbox['x2'] ?? null),
            'y2' => $number($bbox['y2'] ?? null),
            'paintOrder' => max(0, (int) ($placement['paintOrder'] ?? 0)),
        ];
    }

    /**
     * Use an already evidenced text/image region on the same page as a
     * deterministic fallback. Horizontal region affinity wins, then visual
     * y distance and paint order. No cross-page inference is permitted.
     *
     * @param array<string,mixed> $placement
     * @param list<array{index:int,position:string,x1:float|null,y1:float|null,x2:float|null,y2:float|null,paintOrder:int}> $evidence
     * @return array{index:int,position:string}|null
     */
    private function pdfImagePageRegionFallbackAnchor(array $placement, array $evidence): ?array
    {
        if ($evidence === []) {
            return null;
        }
        $bbox = is_array($placement['bbox'] ?? null) ? $placement['bbox'] : [];
        foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
            if (!is_numeric($bbox[$coordinate] ?? null) || !is_finite((float) $bbox[$coordinate])) {
                return null;
            }
        }
        $x1 = min((float) $bbox['x1'], (float) $bbox['x2']);
        $x2 = max((float) $bbox['x1'], (float) $bbox['x2']);
        $y1 = min((float) $bbox['y1'], (float) $bbox['y2']);
        $y2 = max((float) $bbox['y1'], (float) $bbox['y2']);
        $centerX = ($x1 + $x2) / 2.0;
        $centerY = ($y1 + $y2) / 2.0;
        $paintOrder = max(0, (int) ($placement['paintOrder'] ?? 0));
        $ranked = [];
        foreach ($evidence as $candidate) {
            if ($candidate['x1'] === null || $candidate['y1'] === null
                || $candidate['x2'] === null || $candidate['y2'] === null) {
                continue;
            }
            $candidateX1 = min($candidate['x1'], $candidate['x2']);
            $candidateX2 = max($candidate['x1'], $candidate['x2']);
            $candidateY1 = min($candidate['y1'], $candidate['y2']);
            $candidateY2 = max($candidate['y1'], $candidate['y2']);
            $horizontalGap = max(0.0, max($x1, $candidateX1) - min($x2, $candidateX2));
            $candidateCenterX = ($candidateX1 + $candidateX2) / 2.0;
            $candidateCenterY = ($candidateY1 + $candidateY2) / 2.0;
            $ranked[] = [
                'candidate' => $candidate,
                'score' => [
                    $horizontalGap,
                    abs($centerX - $candidateCenterX),
                    abs($centerY - $candidateCenterY),
                    abs($paintOrder - $candidate['paintOrder']),
                    $candidate['index'],
                ],
                'centerY' => $candidateCenterY,
            ];
        }
        if ($ranked === []) {
            return null;
        }
        usort($ranked, static function (array $left, array $right): int {
            foreach (array_keys($left['score']) as $index) {
                $comparison = $left['score'][$index] <=> $right['score'][$index];
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        });
        $nearest = $ranked[0];
        $candidate = $nearest['candidate'];
        if (abs($centerY - $nearest['centerY']) > 0.000001) {
            $position = $centerY > $nearest['centerY'] ? 'before' : 'after';
        } else {
            $position = $paintOrder < $candidate['paintOrder'] ? 'before' : 'after';
        }

        return ['index' => $candidate['index'], 'position' => $position];
    }

    /**
     * Validate the complete bridge graph once per extraction. The returned
     * map is deliberately compact so a page with many placements does not
     * repeatedly hash the PDF, serialize every source edge, or walk the AST.
     *
     * @return array<string,array{page:int,artifactSourceOccurrenceId:string,artifactProjectionDigest:string}>
     */
    private function validatedPdfClippedArtifactMediaAnchorContext(
        AstNode $document,
        string $pdfBytes
    ): array {
        $meta = $document->attr('meta', []);
        if (!is_array($meta)
            || ($meta['pdfClippedDisplayArtifactMediaAnchorProofTruncatedCount'] ?? null) !== 0) {
            return [];
        }
        $proofs = $meta['pdfClippedDisplayArtifactMediaAnchorProofs'] ?? null;
        if (!is_array($proofs)
            || !array_is_list($proofs)
            || count($proofs) > self::MAX_PDF_CLIPPED_ARTIFACT_MEDIA_ANCHOR_PROOFS) {
            return [];
        }
        if ($proofs === []) {
            return [];
        }

        $visibility = $meta['pdfTextVisibility'] ?? null;
        $laterPaintRisks = is_array($visibility)
            && is_array($visibility['laterPaintRisks'] ?? null)
                ? $visibility['laterPaintRisks']
                : null;
        if (!is_array($laterPaintRisks)
            || !array_is_list($laterPaintRisks)
            || count($laterPaintRisks) !== count($proofs)
            || ($visibility['complete'] ?? true) !== false
            || ($visibility['laterPaintRiskCount'] ?? null) !== count($laterPaintRisks)
            || ($visibility['laterPaintRiskRecordedCount'] ?? null) !== count($laterPaintRisks)
            || ($visibility['laterPaintRiskUnboundCount'] ?? null) !== 0
            || ($visibility['laterPaintRiskTruncatedCount'] ?? null) !== 0
            || !is_string($visibility['laterPaintRisksDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $visibility['laterPaintRisksDigest']) !== 1
            || !hash_equals(
                $visibility['laterPaintRisksDigest'],
                $this->pdfClippedArtifactDigest($laterPaintRisks, true)
            )) {
            return [];
        }
        $laterPaintRisksById = [];
        foreach ($laterPaintRisks as $risk) {
            if (!is_array($risk)
                || !$this->pdfClippedArtifactLaterPaintRiskIdentityIsValid($risk)
                || isset($laterPaintRisksById[$risk['id']])) {
                return [];
            }
            $laterPaintRisksById[$risk['id']] = $risk;
        }
        $imagePlacements = $meta['pdfImagePlacements'] ?? null;
        if (!is_array($imagePlacements) || !array_is_list($imagePlacements)) {
            return [];
        }

        $reconciliation = $meta['pdfTextVisibilityReconciliation'] ?? null;
        $reconciliationDigest = is_array($reconciliation)
            ? ($reconciliation['proofDigest'] ?? null)
            : null;
        $reconciliationPayload = is_array($reconciliation) ? $reconciliation : [];
        unset($reconciliationPayload['proofDigest']);
        if (!is_array($reconciliation)
            || array_keys($reconciliation) !== [
                'version',
                'policy',
                'rawComplete',
                'complete',
                'reconciled',
                'riskCount',
                'reconciledRiskCount',
                'unresolvedRiskCount',
                'riskIds',
                'mediaProofDigests',
                'failureReason',
                'proofDigest',
            ]
            || ($reconciliation['version'] ?? null) !== 1
            || ($reconciliation['policy'] ?? null)
                !== 'exact-whole-source-later-image-artifact-v1'
            || ($reconciliation['rawComplete'] ?? null) !== false
            || ($reconciliation['complete'] ?? null) !== true
            || ($reconciliation['reconciled'] ?? null) !== true
            || ($reconciliation['riskCount'] ?? null) !== count($proofs)
            || ($reconciliation['reconciledRiskCount'] ?? null) !== count($proofs)
            || ($reconciliation['unresolvedRiskCount'] ?? null) !== 0
            || !is_array($reconciliation['riskIds'] ?? null)
            || !array_is_list($reconciliation['riskIds'])
            || !is_array($reconciliation['mediaProofDigests'] ?? null)
            || !array_is_list($reconciliation['mediaProofDigests'])
            || !array_key_exists('failureReason', $reconciliation)
            || $reconciliation['failureReason'] !== null
            || !is_string($reconciliationDigest)
            || preg_match('/^[a-f0-9]{64}$/D', $reconciliationDigest) !== 1
            || !hash_equals(
                $reconciliationDigest,
                $this->pdfClippedArtifactDigest($reconciliationPayload, true)
            )) {
            return [];
        }

        $sourceDisposition = $meta['pdfSourceDisposition'] ?? null;
        $sourceEdges = is_array($sourceDisposition)
            && ($sourceDisposition['version'] ?? null) === 2
            && ($sourceDisposition['sourceEdgeMappingComplete'] ?? false) === true
            && is_array($sourceDisposition['sourceEdges'] ?? null)
                ? $sourceDisposition['sourceEdges']
                : null;
        if (!is_array($sourceEdges)
            || !array_is_list($sourceEdges)
            || ($sourceDisposition['sourceEdgeCount'] ?? null) !== count($sourceEdges)
            || ($sourceDisposition['sourceOccurrenceCount'] ?? null) !== count($sourceEdges)
            || !is_string($sourceDisposition['sourceEdgeDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $sourceDisposition['sourceEdgeDigest']) !== 1
            || !hash_equals(
                $sourceDisposition['sourceEdgeDigest'],
                $this->pdfSourceDispositionEdgeDigest($sourceEdges)
            )) {
            return [];
        }

        $sourceEdgesByOccurrence = [];
        $sourceEdgeIds = [];
        foreach ($sourceEdges as $edge) {
            if (!is_array($edge) || !$this->pdfSourceDispositionEdgeIdentityIsValid($edge)) {
                return [];
            }
            $sourceId = $edge['sourceOccurrenceId'];
            $edgeId = $edge['id'];
            if (isset($sourceEdgesByOccurrence[$sourceId]) || isset($sourceEdgeIds[$edgeId])) {
                return [];
            }
            $sourceEdgesByOccurrence[$sourceId] = $edge;
            $sourceEdgeIds[$edgeId] = true;
        }

        $sourceSha256 = hash('sha256', $pdfBytes);
        $validatedProofs = [];
        $provedArtifactSourceIds = [];
        $provedLaterPaintRiskIds = [];
        $provedLaterPaintPlacementIds = [];
        $validatedProofDigestList = [];
        $requestedDestinationNodeIds = [];
        $requestedCounterpartSourceIds = [];
        foreach ($proofs as $proof) {
            if (!is_array($proof)
                || array_keys($proof) !== [
                    'version',
                    'method',
                    'sourceSha256',
                    'page',
                    'artifactSourceOccurrenceId',
                    'artifactProjectionDigest',
                    'counterpartSourceOccurrenceId',
                    'counterpartProjectionDigest',
                    'counterpartDestinationNodeId',
                    'laterPaintRiskId',
                    'laterPaintRiskDigest',
                    'laterPaintOperation',
                    'laterPaintOperator',
                    'laterPaintResource',
                    'laterPaintObject',
                    'laterPaintPlacementId',
                    'laterPaintPlacementDigest',
                    'artifactProofDigest',
                    'proofDigest',
                ]) {
                return [];
            }
            $proofDigest = is_string($proof['proofDigest'] ?? null) ? $proof['proofDigest'] : '';
            $proofPayload = $proof;
            unset($proofPayload['proofDigest']);
            $encodedProof = json_encode(
                $proofPayload,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
            $recomputedProofDigest = hash(
                'sha256',
                is_string($encodedProof) ? $encodedProof : serialize($proofPayload)
            );
            $page = $proof['page'] ?? null;
            $artifactId = is_string($proof['artifactSourceOccurrenceId'] ?? null)
                ? $proof['artifactSourceOccurrenceId']
                : '';
            $artifactProjectionDigest = is_string($proof['artifactProjectionDigest'] ?? null)
                ? $proof['artifactProjectionDigest']
                : '';
            $counterpartId = is_string($proof['counterpartSourceOccurrenceId'] ?? null)
                ? $proof['counterpartSourceOccurrenceId']
                : '';
            $destinationNodeId = is_string($proof['counterpartDestinationNodeId'] ?? null)
                ? $proof['counterpartDestinationNodeId']
                : '';
            $laterPaintRiskId = is_string($proof['laterPaintRiskId'] ?? null)
                ? $proof['laterPaintRiskId']
                : '';
            $laterPaintPlacementId = is_string($proof['laterPaintPlacementId'] ?? null)
                ? $proof['laterPaintPlacementId']
                : '';
            foreach ([
                $proof['sourceSha256'] ?? null,
                $artifactProjectionDigest,
                $proof['counterpartProjectionDigest'] ?? null,
                $proof['laterPaintRiskDigest'] ?? null,
                $proof['laterPaintPlacementDigest'] ?? null,
                $proof['artifactProofDigest'] ?? null,
                $proofDigest,
            ] as $digest) {
                if (!is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
                    return [];
                }
            }
            if (($proof['version'] ?? null) !== 2
                || ($proof['method'] ?? null)
                    !== 'whole-source-clipped-display-artifact-media-anchor'
                || !is_int($page)
                || $page < 1
                || !hash_equals($sourceSha256, (string) $proof['sourceSha256'])
                || !hash_equals($proofDigest, $recomputedProofDigest)
                || $artifactId === ''
                || $counterpartId === ''
                || $counterpartId === $artifactId
                || $destinationNodeId === ''
                || $laterPaintRiskId === ''
                || !is_int($proof['laterPaintOperation'] ?? null)
                || $proof['laterPaintOperation'] < 0
                || ($proof['laterPaintOperator'] ?? null) !== 'Do'
                || !is_string($proof['laterPaintResource'] ?? null)
                || $proof['laterPaintResource'] === ''
                || !is_int($proof['laterPaintObject'] ?? null)
                || $proof['laterPaintObject'] < 1
                || $laterPaintPlacementId === '') {
                return [];
            }
            if (isset($provedArtifactSourceIds[$artifactId])) {
                return [];
            }
            $provedArtifactSourceIds[$artifactId] = true;
            if (isset($provedLaterPaintRiskIds[$laterPaintRiskId])
                || isset($provedLaterPaintPlacementIds[$laterPaintPlacementId])) {
                return [];
            }
            $provedLaterPaintRiskIds[$laterPaintRiskId] = true;
            $provedLaterPaintPlacementIds[$laterPaintPlacementId] = true;
            $validatedProofDigestList[] = $proofDigest;

            $risk = $laterPaintRisksById[$laterPaintRiskId] ?? null;
            if (!is_array($risk)
                || $risk['sourceSha256'] !== $sourceSha256
                || $risk['page'] !== $page
                || $risk['sourceProjectionDigest'] !== $artifactProjectionDigest
                || $risk['riskDigest'] !== $proof['laterPaintRiskDigest']
                || $risk['paintOperation'] !== $proof['laterPaintOperation']
                || $risk['paintOperator'] !== $proof['laterPaintOperator']
                || $risk['paintResource'] !== $proof['laterPaintResource']
                || $risk['paintObject'] !== $proof['laterPaintObject']) {
                return [];
            }
            $matchingPlacements = [];
            foreach ($imagePlacements as $placement) {
                if (!is_array($placement)
                    || ($placement['id'] ?? null) !== $laterPaintPlacementId
                    || ($placement['kind'] ?? null) !== 'image-xobject'
                    || ($placement['page'] ?? null) !== $risk['page']
                    || ($placement['contentStream'] ?? null) !== $risk['paintStream']
                    || ($placement['object'] ?? null) !== $risk['paintObject']
                    || ($placement['resource'] ?? null) !== $risk['paintResource']
                    || ($placement['visible'] ?? null) !== true
                    || ($placement['placementEligible'] ?? null) !== true
                    || !$this->pdfClippedArtifactBoundsMatch(
                        $placement['bbox'] ?? null,
                        $risk['paintBounds']
                    )
                    || !hash_equals(
                        $proof['laterPaintPlacementDigest'],
                        $this->pdfClippedArtifactPlacementDigest($placement)
                    )) {
                    continue;
                }
                $matchingPlacements[] = $placement;
            }
            if (count($matchingPlacements) !== 1) {
                return [];
            }

            $artifactEdge = $sourceEdgesByOccurrence[$artifactId] ?? null;
            $counterpartEdge = $sourceEdgesByOccurrence[$counterpartId] ?? null;
            if (!is_array($artifactEdge)
                || !is_array($counterpartEdge)
                || ($artifactEdge['page'] ?? null) !== $page
                || ($artifactEdge['disposition'] ?? null) !== 'artifact'
                || ($artifactEdge['target'] ?? null) !== 'disposition'
                || ($artifactEdge['mappingMode'] ?? null) !== 'explicit-disposition'
                || ($artifactEdge['destinationNodeIds'] ?? null) !== []
                || ($artifactEdge['destinationInlineIds'] ?? null) !== []
                || ($counterpartEdge['page'] ?? null) !== $page
                || ($counterpartEdge['target'] ?? null) !== 'output'
                || !in_array(
                    $counterpartEdge['disposition'] ?? null,
                    ['emitted', 'boundary-repair', 'semantic-structure'],
                    true
                )
                || !in_array(
                    $counterpartEdge['mappingMode'] ?? null,
                    ['exact-sequence', 'exact-authorized-scope', 'exact-semantic-list-marker'],
                    true
                )
                || ($counterpartEdge['destinationNodeIds'] ?? null) !== [$destinationNodeId]) {
                return [];
            }

            $key = $this->pdfClippedArtifactMediaAnchorKey(
                $page,
                $artifactId,
                $artifactProjectionDigest
            );
            if (isset($validatedProofs[$key])) {
                return [];
            }
            $validatedProofs[$key] = [
                'page' => $page,
                'artifactSourceOccurrenceId' => $artifactId,
                'artifactProjectionDigest' => $artifactProjectionDigest,
                'counterpartSourceOccurrenceId' => $counterpartId,
                'counterpartDestinationNodeId' => $destinationNodeId,
            ];
            $requestedDestinationNodeIds[$destinationNodeId] = true;
            $requestedCounterpartSourceIds[$counterpartId] = true;
        }

        if ($reconciliation['riskIds'] !== array_keys($laterPaintRisksById)
            || $reconciliation['mediaProofDigests'] !== $validatedProofDigestList) {
            return [];
        }

        $counterpartTopLevelClaimCounts = [];
        foreach ($document->children as $block) {
            $sourceLineIds = $this->validatedPdfTopLevelSourceLineIds($block);
            if ($sourceLineIds === false) {
                return [];
            }
            foreach ($sourceLineIds as $sourceLineId) {
                if (isset($requestedCounterpartSourceIds[$sourceLineId])) {
                    $counterpartTopLevelClaimCounts[$sourceLineId] =
                        ($counterpartTopLevelClaimCounts[$sourceLineId] ?? 0) + 1;
                }
            }
        }
        $liveDestinationMatches = [];
        foreach ($document->children as $block) {
            $this->collectPdfClippedArtifactDestinationNodeMatches(
                $block,
                0,
                $requestedDestinationNodeIds,
                $liveDestinationMatches
            );
        }
        foreach ($validatedProofs as $proof) {
            $destinationNodeId = $proof['counterpartDestinationNodeId'];
            $matches = $liveDestinationMatches[$destinationNodeId] ?? [];
            if (count($matches) !== 1
                || $matches[0]['depth'] !== 0
                || ($counterpartTopLevelClaimCounts[$proof['counterpartSourceOccurrenceId']] ?? 0)
                    !== 1) {
                return [];
            }
            $sourceLineIds = $matches[0]['node']->attr('sourceLineIds', []);
            if (!is_array($sourceLineIds)
                || !array_is_list($sourceLineIds)) {
                return [];
            }
            $uniqueSourceLineIds = [];
            foreach ($sourceLineIds as $sourceLineId) {
                if (!is_string($sourceLineId)
                    || $sourceLineId === ''
                    || isset($uniqueSourceLineIds[$sourceLineId])) {
                    return [];
                }
                $uniqueSourceLineIds[$sourceLineId] = true;
            }
            if (!isset($uniqueSourceLineIds[$proof['counterpartSourceOccurrenceId']])) {
                return [];
            }
        }

        return array_map(
            static fn (array $proof): array => [
                'page' => $proof['page'],
                'artifactSourceOccurrenceId' => $proof['artifactSourceOccurrenceId'],
                'artifactProjectionDigest' => $proof['artifactProjectionDigest'],
            ],
            $validatedProofs
        );
    }

    /** @param array<string,mixed> $risk */
    private function pdfClippedArtifactLaterPaintRiskIdentityIsValid(array $risk): bool
    {
        if (array_keys($risk) !== [
            'id',
            'version',
            'sourceSha256',
            'page',
            'sourceOccurrenceIndex',
            'sourceStream',
            'sourceRange',
            'sourceProjectionDigest',
            'textOperation',
            'textBounds',
            'paintOperation',
            'paintStream',
            'paintOperator',
            'paintResource',
            'paintObject',
            'paintSubtype',
            'paintBounds',
            'riskDigest',
        ]
            || ($risk['version'] ?? null) !== 1
            || !is_string($risk['sourceSha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $risk['sourceSha256']) !== 1
            || !is_int($risk['page'] ?? null)
            || $risk['page'] < 1
            || !is_int($risk['sourceOccurrenceIndex'] ?? null)
            || $risk['sourceOccurrenceIndex'] < 0
            || !is_int($risk['sourceStream'] ?? null)
            || $risk['sourceStream'] < 1
            || !is_array($risk['sourceRange'] ?? null)
            || array_keys($risk['sourceRange']) !== ['start', 'end']
            || !is_int($risk['sourceRange']['start'] ?? null)
            || !is_int($risk['sourceRange']['end'] ?? null)
            || $risk['sourceRange']['start'] < 0
            || $risk['sourceRange']['end'] <= $risk['sourceRange']['start']
            || !is_string($risk['sourceProjectionDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $risk['sourceProjectionDigest']) !== 1
            || !is_int($risk['textOperation'] ?? null)
            || $risk['textOperation'] < 0
            || !$this->pdfClippedArtifactBoundsAreFinite($risk['textBounds'] ?? null)
            || !is_int($risk['paintOperation'] ?? null)
            || $risk['paintOperation'] <= $risk['textOperation']
            || !is_int($risk['paintStream'] ?? null)
            || $risk['paintStream'] < 1
            || ($risk['paintOperator'] ?? null) !== 'Do'
            || !is_string($risk['paintResource'] ?? null)
            || $risk['paintResource'] === ''
            || !is_int($risk['paintObject'] ?? null)
            || $risk['paintObject'] < 1
            || ($risk['paintSubtype'] ?? null) !== 'Image'
            || !$this->pdfClippedArtifactBoundsAreFinite($risk['paintBounds'] ?? null)
            || !is_string($risk['riskDigest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $risk['riskDigest']) !== 1) {
            return false;
        }
        $payload = $risk;
        $id = array_shift($payload);
        $digest = array_pop($payload);
        $expectedDigest = $this->pdfClippedArtifactDigest($payload, true);

        return is_string($id)
            && hash_equals('pdf-later-paint-risk-' . substr($expectedDigest, 0, 32), $id)
            && is_string($digest)
            && hash_equals($expectedDigest, $digest);
    }

    private function pdfClippedArtifactBoundsAreFinite(mixed $bounds): bool
    {
        if (!is_array($bounds) || array_keys($bounds) !== ['x1', 'y1', 'x2', 'y2']) {
            return false;
        }
        foreach ($bounds as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
                return false;
            }
        }

        return (float) $bounds['x2'] > (float) $bounds['x1']
            && (float) $bounds['y2'] > (float) $bounds['y1'];
    }

    private function pdfClippedArtifactBoundsMatch(mixed $left, mixed $right): bool
    {
        if (!$this->pdfClippedArtifactBoundsAreFinite($left)
            || !$this->pdfClippedArtifactBoundsAreFinite($right)) {
            return false;
        }
        foreach (['x1', 'y1', 'x2', 'y2'] as $key) {
            if (abs((float) $left[$key] - (float) $right[$key]) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $placement */
    private function pdfClippedArtifactPlacementDigest(array $placement): string
    {
        return $this->pdfClippedArtifactDigest([
            'id' => $placement['id'] ?? null,
            'kind' => $placement['kind'] ?? null,
            'page' => $placement['page'] ?? null,
            'contentStream' => $placement['contentStream'] ?? null,
            'paintOrder' => $placement['paintOrder'] ?? null,
            'object' => $placement['object'] ?? null,
            'resource' => $placement['resource'] ?? null,
            'bbox' => $placement['bbox'] ?? null,
        ], true);
    }

    private function pdfClippedArtifactDigest(mixed $value, bool $preserveZeroFraction = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($preserveZeroFraction) {
            $flags |= JSON_PRESERVE_ZERO_FRACTION;
        }
        $encoded = json_encode($value, $flags);

        return hash('sha256', is_string($encoded) ? $encoded : serialize($value));
    }

    /**
     * A dispositioned clipped artifact cannot remain an ordinary text anchor.
     * Authorize the existing same-page region fallback only when exactly one
     * selected side still contains the reader-carried occurrence ID and its
     * digest matches that side's actual text after the reader's canonical
     * projection. This rejects replayed identity fields and same-page text
     * collisions without treating the remote counterpart as a placement.
     *
     * @param array<string,mixed> $placement
     * @param array<string,array{page:int,artifactSourceOccurrenceId:string,artifactProjectionDigest:string}> $context
     */
    private function validatedPdfClippedArtifactMediaAnchorSide(
        array $placement,
        int $page,
        array $context
    ): ?string {
        if ($context === []) {
            return null;
        }

        $matchingSides = [];
        foreach (['preceding', 'following'] as $side) {
            $text = $placement[$side . 'Text'] ?? null;
            $sourceId = $placement[$side . 'SourceOccurrenceId'] ?? null;
            $projectionDigest = $placement[$side . 'SourceProjectionDigest'] ?? null;
            if (!is_string($text)
                || !is_string($sourceId)
                || $sourceId === ''
                || !is_string($projectionDigest)
                || preg_match('/^[a-f0-9]{64}$/D', $projectionDigest) !== 1) {
                continue;
            }
            $projection = $this->pdfImageSourceOccurrenceComparableText($text);
            if ($projection === ''
                || !hash_equals($projectionDigest, hash('sha256', $projection))) {
                continue;
            }
            $key = $this->pdfClippedArtifactMediaAnchorKey($page, $sourceId, $projectionDigest);
            if (isset($context[$key])) {
                $matchingSides[] = $side;
            }
        }

        return count($matchingSides) === 1 ? $matchingSides[0] : null;
    }

    private function pdfClippedArtifactMediaAnchorKey(
        int $page,
        string $sourceId,
        string $projectionDigest
    ): string {
        return hash('sha256', serialize([$page, $sourceId, $projectionDigest]));
    }

    /**
     * @param array<string,true> $requestedNodeIds
     * @param array<string,list<array{depth:int,node:AstNode}>> $matches
     */
    private function collectPdfClippedArtifactDestinationNodeMatches(
        AstNode $node,
        int $depth,
        array $requestedNodeIds,
        array &$matches
    ): void {
        $nodeId = $node->attr('sourceNodeId');
        if (is_string($nodeId) && isset($requestedNodeIds[$nodeId])) {
            $matches[$nodeId][] = ['depth' => $depth, 'node' => $node];
        }
        foreach ($node->children as $child) {
            $this->collectPdfClippedArtifactDestinationNodeMatches(
                $child,
                $depth + 1,
                $requestedNodeIds,
                $matches
            );
        }
    }

    /**
     * Recompute the immutable top-level binding identity and its derived
     * occurrence list. An undecorated textless block is valid and returns an
     * empty list; partial or forged decoration returns false.
     *
     * @return list<string>|false
     */
    private function validatedPdfTopLevelSourceLineIds(AstNode $node): array|false
    {
        $hasNodeId = array_key_exists('sourceNodeId', $node->attrs);
        $hasSourceLineIds = array_key_exists('sourceLineIds', $node->attrs);
        $hasSourceLineEdges = array_key_exists('sourceLineEdges', $node->attrs);
        if (!$hasNodeId && !$hasSourceLineIds && !$hasSourceLineEdges) {
            return [];
        }
        $nodeId = $node->attr('sourceNodeId');
        $sourceLineIds = $node->attr('sourceLineIds');
        $sourceLineEdges = $node->attr('sourceLineEdges');
        if (!$hasNodeId
            || !$hasSourceLineIds
            || !$hasSourceLineEdges
            || !is_string($nodeId)
            || $nodeId === ''
            || !is_array($sourceLineIds)
            || !array_is_list($sourceLineIds)
            || !is_array($sourceLineEdges)
            || !array_is_list($sourceLineEdges)
            || $sourceLineEdges === []) {
            return false;
        }

        $derivedSourceLineIds = [];
        $seenSourceLineIds = [];
        $seenEdges = [];
        foreach ($sourceLineEdges as $edge) {
            if (!is_array($edge)
                || array_keys($edge) !== ['sourceLineId', 'startByte', 'endByte']
                || !is_string($edge['sourceLineId'])
                || $edge['sourceLineId'] === ''
                || preg_match('//u', $edge['sourceLineId']) !== 1
                || !is_int($edge['startByte'])
                || !is_int($edge['endByte'])
                || $edge['startByte'] < 0
                || $edge['endByte'] <= $edge['startByte']) {
                return false;
            }
            $edgeKey = $edge['sourceLineId'] . "\0"
                . $edge['startByte'] . "\0" . $edge['endByte'];
            if (isset($seenEdges[$edgeKey])) {
                return false;
            }
            $seenEdges[$edgeKey] = true;
            if (!isset($seenSourceLineIds[$edge['sourceLineId']])) {
                $seenSourceLineIds[$edge['sourceLineId']] = true;
                $derivedSourceLineIds[] = $edge['sourceLineId'];
            }
        }
        if ($sourceLineIds !== $derivedSourceLineIds) {
            return false;
        }

        $identity = json_encode(
            ['type' => $node->type, 'sourceLineEdges' => $sourceLineEdges],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($identity)) {
            return false;
        }
        $expectedNodeId = 'pdf-source-node-' . substr(hash('sha256', $identity), 0, 32);

        return hash_equals($expectedNodeId, $nodeId) ? $derivedSourceLineIds : false;
    }

    /** @param list<array<string,mixed>> $edges */
    private function pdfSourceDispositionEdgeDigest(array $edges): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $digest = hash_init('sha256');
        hash_update($digest, '[');
        foreach ($edges as $index => $edge) {
            $encoded = json_encode($edge, $flags);
            if (!is_string($encoded)) {
                return hash('sha256', serialize($edges));
            }
            if ($index > 0) {
                hash_update($digest, ',');
            }
            hash_update($digest, $encoded);
        }
        hash_update($digest, ']');

        return hash_final($digest);
    }

    /**
     * Mirror PdfReader's source-occurrence projection before checking a
     * carried digest. Placement text normally arrives pre-normalized, but
     * repeating the projection here makes tampered or replayed metadata fail
     * closed at the consumer boundary.
     */
    private function pdfImageSourceOccurrenceComparableText(string $text): string
    {
        $text = $this->repairPdfImageSourceControlLigatures($text);
        if ($text !== '' && preg_match('//u', $text) !== 1) {
            $decoded = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
            if (!is_string($decoded) || $decoded === '') {
                $decoded = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            }
            if (is_string($decoded)) {
                $text = $this->repairPdfImageSourceControlLigatures($decoded);
            }
        }

        $controlClass = '[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]';
        if (preg_match('/' . $controlClass . '/u', $text) === 1) {
            $text = preg_replace_callback(
                '/(^|[ \t])(' . $controlClass . '{2,})[ \t]*(?=[\p{L}\p{N}])/u',
                static function (array $match): string {
                    $characters = preg_split('//u', $match[2], -1, PREG_SPLIT_NO_EMPTY) ?: [];

                    return count($characters) >= 2 && count(array_unique($characters)) === 1
                        ? $match[1] . "\u{2022} "
                        : $match[1] . ' ';
                },
                $text
            ) ?? $text;
            $text = preg_replace('/' . $controlClass . '+/u', ' ', $text) ?? $text;
        }
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }

        return preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $text) ?? '';
    }

    private function repairPdfImageSourceControlLigatures(string $text): string
    {
        if (!str_contains($text, "\x02")) {
            return $text;
        }

        return preg_replace('/((?<=\p{L})\x02(?=\p{Ll})|\x02(?=\p{Ll}))/u', 'fi', $text) ?? $text;
    }

    /** @param array<string,mixed> $edge */
    private function pdfSourceDispositionEdgeIdentityIsValid(array $edge): bool
    {
        $sourceId = $edge['sourceOccurrenceId'] ?? null;
        $page = $edge['page'] ?? null;
        $disposition = $edge['disposition'] ?? null;
        $target = $edge['target'] ?? null;
        $mappingMode = $edge['mappingMode'] ?? null;
        $nodeIds = $edge['destinationNodeIds'] ?? null;
        $inlineIds = $edge['destinationInlineIds'] ?? null;
        $scopeId = $edge['orderScopeId'] ?? null;
        if (array_keys($edge) !== [
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
            || !is_string($edge['id'] ?? null)
            || !is_string($sourceId)
            || $sourceId === ''
            || !is_int($page)
            || $page < 1
            || !is_string($disposition)
            || !is_string($target)
            || !is_string($mappingMode)
            || !is_array($nodeIds)
            || !array_is_list($nodeIds)
            || !is_array($inlineIds)
            || !array_is_list($inlineIds)
            || ($scopeId !== null && !is_string($scopeId))) {
            return false;
        }
        foreach ([$nodeIds, $inlineIds] as $ids) {
            $seen = [];
            foreach ($ids as $id) {
                if (!is_string($id) || $id === '' || isset($seen[$id])) {
                    return false;
                }
                $seen[$id] = true;
            }
        }
        $identity = [
            'sourceOccurrenceId' => $sourceId,
            'page' => $page,
            'disposition' => $disposition,
            'target' => $target,
            'mappingMode' => $mappingMode,
            'destinationNodeIds' => $nodeIds,
            'destinationInlineIds' => $inlineIds,
            'orderScopeId' => $scopeId,
        ];
        $encoded = json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expectedId = 'pdf-source-edge-' . substr(hash(
            'sha256',
            is_string($encoded) ? $encoded : serialize($identity)
        ), 0, 32);

        return hash_equals($expectedId, $edge['id']);
    }

    private function pdfImageAnchorBlockText(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        if ($text !== '') {
            return $text;
        }
        $parts = [];
        foreach ($node->children as $child) {
            $childText = $this->pdfImageAnchorBlockText($child);
            if ($childText !== '') {
                $parts[] = $childText;
            }
        }

        return implode(' ', $parts);
    }

    private function normalizedPdfImageAnchorText(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    /**
     * @param list<string> $diagnostics
     * @param list<array<string, mixed>> $requestedPlacements
     * @param array<string,array{contents:string,mimeType:string,width:int,height:int}> $rasterImages
     * @return list<array<string, mixed>>
     */
    private function loadPdfImages(
        MediaBag $bag,
        string $bytes,
        array &$diagnostics,
        string $imageMode,
        array $requestedPlacements,
        array $rasterImages,
        array &$occurrenceDispositions
    ): array {
        if (strlen($bytes) > self::MAX_PDF_SCAN_BYTES) {
            $diagnostics[] = 'extract-media-pdf-scan-skipped:too-large';
            foreach ($requestedPlacements as $placement) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'pdf-source-byte-limit'
                );
            }

            return [];
        }

        $placementsByObject = [];
        foreach ($requestedPlacements as $placement) {
            if (!is_numeric($placement['object'] ?? null)) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'image-object-invalid'
                );
                continue;
            }
            $placementsByObject[(string) ((int) $placement['object'])][] = $placement;
        }
        if ($placementsByObject === []) {
            return [];
        }

        if (!class_exists(\PortLibs\MarkerPDF\PdfTextExtractor::class)) {
            foreach ($requestedPlacements as $placement) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'xref-selected-image-extractor-unavailable'
                );
            }

            return [];
        }

        try {
            $selectedAssets = (new \PortLibs\MarkerPDF\PdfTextExtractor())->extractPaintedImageAssets(
                $bytes,
                array_keys($placementsByObject)
            );
        } catch (\Throwable) {
            $diagnostics[] = 'extract-media-pdf-selected-image-scan-failed';
            foreach ($requestedPlacements as $placement) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'xref-selected-image-scan-failed'
                );
            }

            return [];
        }
        $selectedAssetsByObject = [];
        foreach ($selectedAssets as $selectedAsset) {
            if (is_array($selectedAsset) && is_numeric($selectedAsset['object'] ?? null)) {
                $selectedAssetsByObject[(string) ((int) $selectedAsset['object'])] = $selectedAsset;
            }
        }

        $assets = [];
        $loaded = 0;
        foreach ($placementsByObject as $objectKey => $objectPlacements) {
            $objectNumber = $objectKey;
            $selectedAsset = $selectedAssetsByObject[$objectKey] ?? null;
            if (!is_array($selectedAsset)) {
                $reason = ((int) $objectKey) === 0
                    ? 'inline-image-decoder-unavailable'
                    : 'xref-selected-painted-asset-unavailable';
                foreach ($objectPlacements as $placement) {
                    $this->setPdfImageOccurrenceDisposition(
                        $occurrenceDispositions,
                        $placement,
                        'unresolved',
                        $reason
                    );
                }
                $diagnostics[] = 'extract-media-pdf-image-placement-unavailable:' . $objectKey;
                continue;
            }
            if ($loaded >= self::MAX_PDF_IMAGES) {
                $diagnostics[] = 'extract-media-pdf-image-limit';
                foreach ($objectPlacements as $placement) {
                    $this->setPdfImageOccurrenceDisposition(
                        $occurrenceDispositions,
                        $placement,
                        'unresolved',
                        'pdf-image-asset-limit'
                    );
                }
                continue;
            }

            $dictionary = (string) ($selectedAsset['dictionary'] ?? '');
            $width = is_numeric($selectedAsset['width'] ?? null) ? (int) $selectedAsset['width'] : null;
            $height = is_numeric($selectedAsset['height'] ?? null) ? (int) $selectedAsset['height'] : null;
            $imageMask = ($selectedAsset['imageMask'] ?? false) === true;
            if ($imageMask) {
                $diagnostics[] = 'extract-media-pdf-image-mask-skipped:' . $objectNumber;
                foreach ($objectPlacements as $placement) {
                    $this->setPdfImageOccurrenceDisposition(
                        $occurrenceDispositions,
                        $placement,
                        'unresolved',
                        'image-mask-requires-compositing'
                    );
                }
                continue;
            }

            $stream = is_string($selectedAsset['stream'] ?? null) ? $selectedAsset['stream'] : null;
            $streamLength = is_numeric($selectedAsset['streamByteLength'] ?? null)
                ? (int) $selectedAsset['streamByteLength']
                : (is_string($stream) ? strlen($stream) : 0);
            $raster = $rasterImages[$objectNumber] ?? $rasterImages[$objectKey] ?? null;
            if ($raster !== null && ($raster['width'] !== $width || $raster['height'] !== $height)) {
                $diagnostics[] = 'extract-media-pdf-image-raster-skipped:dimensions:' . $objectNumber;
                $raster = null;
            }
            if (($selectedAsset['availability'] ?? 'unavailable') !== 'available' && $raster === null) {
                $reason = (string) ($selectedAsset['unavailableReason'] ?? 'image-stream-unavailable');
                foreach ($objectPlacements as $placement) {
                    $this->setPdfImageOccurrenceDisposition($occurrenceDispositions, $placement, 'unresolved', $reason);
                }
                $diagnostics[] = 'extract-media-pdf-image-skipped:stream';
                continue;
            }
            if ($stream !== null && strlen($stream) > self::MAX_PDF_IMAGE_BYTES && $raster === null) {
                foreach ($objectPlacements as $placement) {
                    $this->setPdfImageOccurrenceDisposition(
                        $occurrenceDispositions,
                        $placement,
                        'unresolved',
                        'image-stream-byte-limit'
                    );
                }
                $diagnostics[] = 'extract-media-pdf-image-skipped:stream';
                continue;
            }
            $filters = is_array($selectedAsset['filters'] ?? null)
                ? array_values(array_filter($selectedAsset['filters'], 'is_string'))
                : [];
            // Selection describes the image painted by the PDF, not the
            // compression ratio of a browser-supplied replacement raster.
            // A lossless PNG can be smaller than its JPX stream (or larger
            // than a JBIG2 stream) without changing whether the source image
            // is an important document asset.
            $importance = $this->pdfImageImportance($width, $height, $streamLength, false);
            if ($imageMode === 'important' && $importance !== 'important') {
                $diagnostics[] = 'extract-media-pdf-image-unimportant:' . $objectNumber . ':' . $importance;
                foreach ($objectPlacements as $placement) {
                    $this->setPdfImageOccurrenceDisposition(
                        $occurrenceDispositions,
                        $placement,
                        'intentional_omission',
                        'image-mode-unimportant-' . $importance
                    );
                }
                continue;
            }

            if ($raster !== null) {
                $mimeType = $raster['mimeType'];
                $extension = $this->pdfRasterExtension($mimeType);
                $imageBytes = $raster['contents'];
                $diagnostics[] = 'extract-media-pdf-image-raster-loaded:' . $objectNumber . ':' . $importance;
            } else {
                if ($stream === null || $stream === '') {
                    foreach ($objectPlacements as $placement) {
                        $this->setPdfImageOccurrenceDisposition(
                            $occurrenceDispositions,
                            $placement,
                            'unresolved',
                            'image-stream-unavailable'
                        );
                    }
                    continue;
                }
                $mimeType = $this->pdfEmbeddableMimeType($filters);
                $extension = $mimeType === 'image/jpeg' ? '.jpg' : '.jp2';
                $imageBytes = $stream;
                if ($mimeType === null) {
                    $flateImage = $this->pdfFlateImagePng($dictionary, $filters, $stream, $width, $height, false);
                    if ($flateImage === null) {
                        $diagnostics[] = 'extract-media-pdf-image-skipped:' . ($filters === [] ? 'unfiltered' : implode('+', $filters));
                        foreach ($objectPlacements as $placement) {
                            $this->setPdfImageOccurrenceDisposition(
                                $occurrenceDispositions,
                                $placement,
                                'unresolved',
                                'image-decoder-unavailable-' . ($filters === [] ? 'unfiltered' : implode('-', $filters))
                            );
                        }
                        continue;
                    }
                    [$mimeType, $extension, $imageBytes] = $flateImage;
                }
            }

            if ($imageMode === 'important'
                && $raster !== null
                && $this->pdfUniformSupplementalPngRaster(
                    $imageBytes,
                    $mimeType,
                    $width,
                    $height
                )) {
                $diagnostics[] = 'extract-media-pdf-image-uniform-raster-filler:' . $objectNumber;
                foreach ($objectPlacements as $placement) {
                    $this->setPdfImageOccurrenceDisposition(
                        $occurrenceDispositions,
                        $placement,
                        'intentional_omission',
                        'image-mode-uniform-raster-filler'
                    );
                }
                continue;
            }

            $hasPageImagePlacement = array_filter(
                $objectPlacements,
                static fn (array $placement): bool => in_array(
                    $placement['anchorPosition'] ?? null,
                    ['page-only', 'page-with-visible-overlay'],
                    true
                )
            ) !== [];
            if ($hasPageImagePlacement && $raster === null && $mimeType === 'image/jpeg') {
                if ($width === null || $height === null || $stream === null) {
                    foreach ($objectPlacements as $placement) {
                        $this->setPdfImageOccurrenceDisposition(
                            $occurrenceDispositions,
                            $placement,
                            'unresolved',
                            'page-image-jpeg-sanitize-failed'
                        );
                    }
                    continue;
                }
                $cacheKey = $this->pdfPageJpegCacheKey($stream, $width, $height);
                $sanitized = $this->sanitizedPageJpegs[$cacheKey]
                    ?? $this->pdfSanitizedPageJpeg($stream, $width, $height);
                if ($sanitized === null) {
                    foreach ($objectPlacements as $placement) {
                        $this->setPdfImageOccurrenceDisposition(
                            $occurrenceDispositions,
                            $placement,
                            'unresolved',
                            'page-image-jpeg-sanitize-failed'
                        );
                    }
                    $diagnostics[] = 'extract-media-pdf-page-image-jpeg-sanitize-failed:' . $objectNumber;
                    continue;
                }
                $imageBytes = $sanitized;
                $diagnostics[] = 'extract-media-pdf-page-image-jpeg-sanitized:' . $objectNumber;
            }

            $source = 'pdf/image-' . $objectNumber . $extension;
            $bag->insertMedia($source, $mimeType, $imageBytes);
            // JPEG 2000 is not a browser-safe image format. Keep the source
            // bytes even when an optional rasterizer is unavailable, but do
            // not leave a broken <img> in the converted document. The
            // placement below becomes a download-oriented placeholder until
            // a caller supplies a browser-compatible supplemental raster.
            $webPreviewUnavailable = $raster === null && $mimeType === 'image/jp2';
            $assets[$objectKey] = [
                'source' => $source,
                'object' => $objectNumber,
                'mimeType' => $mimeType,
                'byteLength' => strlen($imageBytes),
                'width' => $width,
                'height' => $height,
                'imageMask' => false,
                'importance' => $importance,
                'webPreviewUnavailable' => $webPreviewUnavailable,
            ];
            if ($webPreviewUnavailable) {
                $diagnostics[] = 'extract-media-pdf-image-placeholder:' . $objectNumber . ':jpeg2000-raster-unavailable';
            }
            $diagnostics[] = 'extract-media-pdf-image-loaded:' . $objectNumber . ':' . $importance;
            $loaded++;
        }

        $resolved = [];
        foreach ($requestedPlacements as $placement) {
            $objectKey = is_numeric($placement['object'] ?? null) ? (string) ((int) $placement['object']) : '';
            if ($objectKey === '' || !isset($assets[$objectKey])) {
                continue;
            }
            $resolved[] = array_replace($placement, $assets[$objectKey]);
        }

        return $resolved;
    }

    /**
     * @param list<array<string, mixed>> $placements
     */
    private function documentWithPlacedPdfImageBlocks(
        AstNode $document,
        array $placements,
        array &$occurrenceDispositions
    ): AstNode
    {
        $before = [];
        $after = [];
        $pageOnly = [];
        foreach ($placements as $placement) {
            if (in_array(
                ($placement['anchorPosition'] ?? null),
                ['page-only', 'page-with-visible-overlay'],
                true
            )) {
                $pageOnly[] = $placement;
                continue;
            }
            $index = $placement['anchorIndex'] ?? null;
            if (!is_int($index) && !is_numeric($index)) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'image-anchor-index-missing'
                );
                continue;
            }
            $index = (int) $index;
            if (!isset($document->children[$index])) {
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'unresolved',
                    'missing-page-occurrence'
                );
                continue;
            }
            $target = ($placement['anchorPosition'] ?? '') === 'after' ? $after : $before;
            $target[$index][] = $placement;
            if (($placement['anchorPosition'] ?? '') === 'after') {
                $after = $target;
            } else {
                $before = $target;
            }
        }

        if ($before === [] && $after === [] && $pageOnly === []) {
            return $document;
        }

        $sort = static function (array &$group): void {
            usort($group, static function (array $left, array $right): int {
                return ((int) ($left['page'] ?? 0)) <=> ((int) ($right['page'] ?? 0))
                    ?: ((int) ($left['paintOrder'] ?? 0)) <=> ((int) ($right['paintOrder'] ?? 0))
                    ?: strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''))
                    ?: ((int) ($left['object'] ?? 0)) <=> ((int) ($right['object'] ?? 0));
            });
        };
        foreach ($before as &$group) {
            $sort($group);
        }
        unset($group);
        foreach ($after as &$group) {
            $sort($group);
        }
        unset($group);
        $sort($pageOnly);

        $captionAssociations = $this->placedPdfImageCaptionAssociations(
            $document,
            $before,
            $after
        );
        $captionsByPlacementId = $captionAssociations['byPlacementId'];
        $claimedCaptionIndexes = $captionAssociations['claimedIndexes'];

        $children = [];
        if ($pageOnly !== []) {
            foreach ($pageOnly as $placement) {
                $visibleOverlay = ($placement['anchorPosition'] ?? null) === 'page-with-visible-overlay';
                if ($document->children !== [] && !$visibleOverlay) {
                    $this->setPdfImageOccurrenceDisposition(
                        $occurrenceDispositions,
                        $placement,
                        'unresolved',
                        'page-image-document-not-empty'
                    );
                    continue;
                }
                $children[] = $this->placedPdfImageBlock($placement);
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'resolved',
                    ($placement['webPreviewUnavailable'] ?? false) === true
                        ? 'page-original-with-visible-placeholder'
                        : ($visibleOverlay
                            ? 'page-image-with-sparse-visible-overlay'
                            : 'page-image-without-visible-text')
                );
            }
        }
        foreach ($document->children as $index => $block) {
            foreach ($before[$index] ?? [] as $placement) {
                $placementId = (string) ($placement['id'] ?? '');
                $children[] = $this->placedPdfImageBlock(
                    $placement,
                    $captionsByPlacementId[$placementId] ?? null
                );
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'resolved',
                    ($placement['webPreviewUnavailable'] ?? false) === true
                        ? 'original-with-visible-placeholder'
                        : 'placed-media-attachment'
                );
            }
            if (!isset($claimedCaptionIndexes[$index])) {
                $children[] = $block;
            }
            foreach ($after[$index] ?? [] as $placement) {
                $placementId = (string) ($placement['id'] ?? '');
                $children[] = $this->placedPdfImageBlock(
                    $placement,
                    $captionsByPlacementId[$placementId] ?? null
                );
                $this->setPdfImageOccurrenceDisposition(
                    $occurrenceDispositions,
                    $placement,
                    'resolved',
                    ($placement['webPreviewUnavailable'] ?? false) === true
                        ? 'original-with-visible-placeholder'
                        : 'placed-media-attachment'
                );
            }
        }

        return new AstNode($document->type, $document->attrs, $children);
    }

    /**
     * Promote only an exact, source-bound `Figure N:` paragraph immediately
     * following one placed image. The occurrence id and projection digest
     * prove that the candidate belongs to the image's source page; requiring
     * a one-to-one claim prevents a repeated caption from being consumed by
     * more than one painting.
     *
     * @param array<int,list<array<string,mixed>>> $before
     * @param array<int,list<array<string,mixed>>> $after
     * @return array{
     *     byPlacementId:array<string,AstNode>,
     *     claimedIndexes:array<int,true>
     * }
     */
    private function placedPdfImageCaptionAssociations(
        AstNode $document,
        array $before,
        array $after
    ): array
    {
        $candidatesByIndex = [];
        foreach ($before as $index => $placements) {
            foreach ($placements as $placement) {
                $candidate = $this->validatedPlacedPdfImageCaption(
                    $document,
                    (int) $index,
                    $placement
                );
                if ($candidate === null) {
                    continue;
                }
                $candidatesByIndex[(int) $index][] = $candidate;
            }
        }
        foreach ($after as $index => $placements) {
            $captionIndex = (int) $index + 1;
            foreach ($placements as $placement) {
                $candidate = $this->validatedPlacedPdfImageCaption(
                    $document,
                    $captionIndex,
                    $placement
                );
                if ($candidate === null) {
                    continue;
                }
                $candidatesByIndex[$captionIndex][] = $candidate;
            }
        }

        $byPlacementId = [];
        $claimedIndexes = [];
        foreach ($candidatesByIndex as $index => $candidates) {
            if (count($candidates) !== 1) {
                continue;
            }
            $candidate = $candidates[0];
            $placementId = $candidate['placementId'];
            if (isset($byPlacementId[$placementId])) {
                continue;
            }
            $byPlacementId[$placementId] = $candidate['caption'];
            $claimedIndexes[(int) $index] = true;
        }

        return [
            'byPlacementId' => $byPlacementId,
            'claimedIndexes' => $claimedIndexes,
        ];
    }

    /**
     * @param array<string,mixed> $placement
     * @return array{placementId:string,caption:AstNode}|null
     */
    private function validatedPlacedPdfImageCaption(
        AstNode $document,
        int $captionIndex,
        array $placement
    ): ?array
    {
        if (($placement['webPreviewUnavailable'] ?? false) === true
            || !isset($document->children[$captionIndex])) {
            return null;
        }
        $caption = $document->children[$captionIndex];
        if ($caption->type !== 'paragraph') {
            return null;
        }

        $captionText = $this->pdfImageAnchorBlockText($caption);
        if ($captionText === ''
            || preg_match('/\AFigure[ \t]+[0-9]+:[ \t]*\S/uD', $captionText) !== 1) {
            return null;
        }
        $placementId = $placement['id'] ?? null;
        $followingText = $placement['followingText'] ?? null;
        $sourceId = $placement['followingSourceOccurrenceId'] ?? null;
        $projectionDigest = $placement['followingSourceProjectionDigest'] ?? null;
        if (!is_string($placementId)
            || $placementId === ''
            || !is_string($followingText)
            || !hash_equals($captionText, $followingText)
            || !is_string($sourceId)
            || $sourceId === ''
            || !is_string($projectionDigest)
            || preg_match('/^[a-f0-9]{64}$/D', $projectionDigest) !== 1) {
            return null;
        }

        $projection = $this->pdfImageSourceOccurrenceComparableText($followingText);
        if ($projection === ''
            || !hash_equals($projectionDigest, hash('sha256', $projection))) {
            return null;
        }
        $sourceLineIds = $this->validatedPdfTopLevelSourceLineIds($caption);
        if ($sourceLineIds === false
            || count($sourceLineIds) !== 1
            || $sourceLineIds[0] !== $sourceId) {
            return null;
        }

        return ['placementId' => $placementId, 'caption' => $caption];
    }

    /**
     * @param array<string, mixed> $placement
     */
    private function placedPdfImageBlock(array $placement, ?AstNode $caption = null): AstNode
    {
        $pageOnly = in_array(
            ($placement['anchorPosition'] ?? null),
            ['page-only', 'page-with-visible-overlay'],
            true
        );
        $attributes = [
            'data-pandoc-pdf-image-object' => (string) $placement['object'],
            'data-pandoc-pdf-image-type' => (string) $placement['mimeType'],
            'data-pandoc-pdf-image-bytes' => (string) $placement['byteLength'],
            'data-pandoc-pdf-image-importance' => (string) $placement['importance'],
            'data-pandoc-pdf-image-placement' => $pageOnly ? 'page' : 'inline',
        ];
        if (is_string($placement['id'] ?? null) && $placement['id'] !== '') {
            $attributes['data-pandoc-pdf-visual-id'] = $placement['id'];
            $attributes['data-pandoc-pdf-occurrence-id'] = $placement['id'];
        }
        if (isset($placement['width']) && $placement['width'] !== null) {
            $attributes['data-pandoc-pdf-image-width'] = (string) $placement['width'];
        }
        if (isset($placement['height']) && $placement['height'] !== null) {
            $attributes['data-pandoc-pdf-image-height'] = (string) $placement['height'];
        }
        if (isset($placement['page'])) {
            $attributes['data-pandoc-pdf-page'] = (string) $placement['page'];
        }
        if (isset($placement['paintOrder'])) {
            $attributes['data-pandoc-pdf-paint-order'] = (string) $placement['paintOrder'];
        }
        if (is_array($placement['bbox'] ?? null)) {
            foreach (['x1', 'y1', 'x2', 'y2'] as $coordinate) {
                if (is_numeric($placement['bbox'][$coordinate] ?? null)) {
                    $attributes['data-pandoc-pdf-image-' . $coordinate] = (string) $placement['bbox'][$coordinate];
                }
            }
        }

        if (($placement['webPreviewUnavailable'] ?? false) === true) {
            $attributes['data-pandoc-pdf-image-rendering'] = 'unavailable';

            return new AstNode('paragraph', [
                'classes' => ['pandoc-pdf-image-block', 'pandoc-pdf-image-placed', 'pandoc-pdf-image-placeholder'],
                'attributes' => $attributes,
            ], [
                new AstNode('span', [
                    'classes' => ['pandoc-pdf-image-placeholder'],
                    'attributes' => array_replace($attributes, ['role' => 'note']),
                ], [
                    new AstNode('text', [
                        'text' => 'PDF image ' . (string) $placement['object']
                            . ' was extracted as JPEG 2000 media, but no JPEG 2000 decoder was available for a preview. ',
                    ]),
                    new AstNode('link', [
                        'url' => (string) $placement['source'],
                        'title' => 'Download original JPEG 2000 image',
                        'attributes' => ['data-pandoc-pdf-image-original' => 'true'],
                    ], [
                        new AstNode('text', ['text' => 'Download original image.']),
                    ]),
                ]),
            ]);
        }

        $pageNumber = max(1, (int) ($placement['page'] ?? 1));
        $imageLabel = $pageOnly
            ? 'PDF page ' . $pageNumber . ' image; editable text unavailable'
            : 'PDF image ' . (string) $placement['object'];
        $imageAttrs = [
            'url' => (string) $placement['source'],
            'title' => 'PDF image ' . (string) $placement['object'],
            'attributes' => $attributes,
        ];
        $captionText = $caption instanceof AstNode
            ? $this->pdfImageAnchorBlockText($caption)
            : '';
        if ($pageOnly) {
            $imageAttrs['alt'] = $imageLabel;
        } elseif ($captionText !== '') {
            $imageAttrs['alt'] = $captionText;
            $imageAttrs['attributes'] = array_replace(
                $attributes,
                ['data-pandoc-alt-source' => 'figure-caption']
            );
        }
        $displaySize = $this->pdfImageDisplaySize($placement['bbox'] ?? null);
        if ($displaySize !== null) {
            // PDF user-space units are points by default. Retaining the
            // painted bounding-box size keeps an icon from expanding to its
            // source bitmap dimensions when it is inserted into a flowing
            // HTML/WordPress document.
            $imageAttrs['width'] = $displaySize['width'];
            $imageAttrs['height'] = $displaySize['height'];
        }

        $blockAttrs = [
            'classes' => array_values(array_filter([
                'pandoc-pdf-image-block',
                'pandoc-pdf-image-placed',
                $pageOnly ? 'pandoc-pdf-page-image' : null,
            ])),
            'attributes' => $attributes,
        ];
        $image = new AstNode('image', $imageAttrs, [
            new AstNode('text', [
                'text' => $captionText !== '' ? $captionText : $imageLabel,
            ]),
        ]);
        if (!$caption instanceof AstNode || $captionText === '') {
            return new AstNode('paragraph', $blockAttrs, [$image]);
        }

        return new AstNode('figure', array_replace($blockAttrs, [
            'caption' => $captionText,
            'captionInlines' => $caption->children,
            // Keep the exact source-bound paragraph in the standard caption
            // payload even though it is no longer a standalone document
            // block. Its source node, line ids, edges, and inline bindings
            // therefore remain available to downstream provenance tooling.
            'captionBlocks' => [$caption],
        ]), [$image]);
    }

    /**
     * @return array{width:string,height:string}|null
     */
    private function pdfImageDisplaySize(mixed $bbox): ?array
    {
        if (!is_array($bbox)
            || !is_numeric($bbox['x1'] ?? null) || !is_numeric($bbox['y1'] ?? null)
            || !is_numeric($bbox['x2'] ?? null) || !is_numeric($bbox['y2'] ?? null)) {
            return null;
        }

        $width = abs((float) $bbox['x2'] - (float) $bbox['x1']);
        $height = abs((float) $bbox['y2'] - (float) $bbox['y1']);
        if ($width <= 0.000001 || $height <= 0.000001 || $width > 10000.0 || $height > 10000.0) {
            return null;
        }

        return [
            'width' => $this->pdfPointDimension($width),
            'height' => $this->pdfPointDimension($height),
        ];
    }

    private function pdfPointDimension(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.') . 'pt';
    }

    /**
     * A decoder-supplied PNG which contains exactly one pixel value is a
     * flat paint tile, not a useful standalone document image. Validate the
     * complete bounded PNG and reverse every scanline filter before making
     * that determination; byte size or dimensions alone never authorize an
     * omission, and unsupported PNG variants fail closed.
     */
    private function pdfUniformSupplementalPngRaster(
        string $bytes,
        string $mimeType,
        ?int $expectedWidth,
        ?int $expectedHeight
    ): bool {
        if ($mimeType !== 'image/png'
            || $expectedWidth === null
            || $expectedHeight === null
            || $expectedWidth < 1
            || $expectedHeight < 1
            || !str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return false;
        }

        $offset = 8;
        $length = strlen($bytes);
        $width = 0;
        $height = 0;
        $bytesPerPixel = 0;
        $indexed = false;
        $paletteEntries = null;
        $compressed = '';
        $seenHeader = false;
        $seenImageData = false;
        $seenEnd = false;
        while ($offset + 12 <= $length) {
            $chunkLengthValue = unpack('Nlength', substr($bytes, $offset, 4));
            $chunkLength = is_array($chunkLengthValue)
                ? (int) ($chunkLengthValue['length'] ?? -1)
                : -1;
            $offset += 4;
            if ($chunkLength < 0 || $offset + 8 + $chunkLength > $length) {
                return false;
            }
            $type = substr($bytes, $offset, 4);
            $offset += 4;
            $data = substr($bytes, $offset, $chunkLength);
            $offset += $chunkLength;
            $crc = substr($bytes, $offset, 4);
            $offset += 4;
            if (!hash_equals($crc, hash('crc32b', $type . $data, true))) {
                return false;
            }

            if ($type === 'IHDR') {
                if ($seenHeader || $chunkLength !== 13 || $seenImageData) {
                    return false;
                }
                $header = unpack(
                    'Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace',
                    $data
                );
                $width = is_array($header) ? (int) ($header['width'] ?? 0) : 0;
                $height = is_array($header) ? (int) ($header['height'] ?? 0) : 0;
                $bitDepth = is_array($header) ? (int) ($header['bitDepth'] ?? 0) : 0;
                $colorType = is_array($header) ? (int) ($header['colorType'] ?? -1) : -1;
                if ($width !== $expectedWidth
                    || $height !== $expectedHeight
                    || $bitDepth !== 8
                    || ($header['compression'] ?? null) !== 0
                    || ($header['filter'] ?? null) !== 0
                    || ($header['interlace'] ?? null) !== 0
                    || !isset([0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4][$colorType])) {
                    return false;
                }
                $bytesPerPixel = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4][$colorType];
                $indexed = $colorType === 3;
                $seenHeader = true;
                continue;
            }
            if (!$seenHeader) {
                return false;
            }
            if ($type === 'PLTE') {
                if ($seenImageData || $chunkLength < 3 || $chunkLength % 3 !== 0) {
                    return false;
                }
                $paletteEntries = intdiv($chunkLength, 3);
                continue;
            }
            if ($type === 'IDAT') {
                if ($seenEnd
                    || strlen($compressed) + $chunkLength > self::MAX_PDF_RASTER_IMAGE_BYTES) {
                    return false;
                }
                $compressed .= $data;
                $seenImageData = true;
                continue;
            }
            if ($type === 'IEND') {
                if ($chunkLength !== 0 || !$seenImageData || $offset !== $length) {
                    return false;
                }
                $seenEnd = true;
                break;
            }
        }
        if (!$seenHeader
            || !$seenImageData
            || !$seenEnd
            || $bytesPerPixel < 1
            || ($indexed && $paletteEntries === null)) {
            return false;
        }

        $rowLength = $width * $bytesPerPixel;
        $decodedLength = ($rowLength + 1) * $height;
        if ($rowLength < 1
            || $decodedLength < 1
            || $decodedLength > self::MAX_PDF_UNIFORM_RASTER_DECODE_BYTES) {
            return false;
        }
        $decoded = @zlib_decode($compressed, $decodedLength);
        if (!is_string($decoded) || strlen($decoded) !== $decodedLength) {
            return false;
        }

        $decodedOffset = 0;
        $previous = str_repeat("\0", $rowLength);
        $uniformPixel = null;
        for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
            $filter = ord($decoded[$decodedOffset++]);
            if ($filter < 0 || $filter > 4) {
                return false;
            }
            $row = '';
            for ($column = 0; $column < $rowLength; $column++) {
                $raw = ord($decoded[$decodedOffset++]);
                $left = $column >= $bytesPerPixel
                    ? ord($row[$column - $bytesPerPixel])
                    : 0;
                $up = ord($previous[$column]);
                $upperLeft = $column >= $bytesPerPixel
                    ? ord($previous[$column - $bytesPerPixel])
                    : 0;
                $predictor = match ($filter) {
                    0 => 0,
                    1 => $left,
                    2 => $up,
                    3 => intdiv($left + $up, 2),
                    4 => $this->pdfPngPaethPredictor($left, $up, $upperLeft),
                };
                $row .= chr(($raw + $predictor) & 0xff);
            }
            for ($pixelOffset = 0; $pixelOffset < $rowLength; $pixelOffset += $bytesPerPixel) {
                $pixel = substr($row, $pixelOffset, $bytesPerPixel);
                if ($indexed
                    && $paletteEntries !== null
                    && ord($pixel) >= $paletteEntries) {
                    return false;
                }
                if ($uniformPixel === null) {
                    $uniformPixel = $pixel;
                    continue;
                }
                if (!hash_equals($uniformPixel, $pixel)) {
                    return false;
                }
            }
            $previous = $row;
        }

        return is_string($uniformPixel) && $uniformPixel !== '';
    }

    private function pdfPngPaethPredictor(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upperLeftDistance = abs($estimate - $upperLeft);
        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }

        return $upDistance <= $upperLeftDistance ? $up : $upperLeft;
    }

    private function pdfIntegerEntry(string $objectBody, string $name): ?int
    {
        if (preg_match('#/' . preg_quote($name, '#') . '\s+(\d+)\b#', $objectBody, $match) !== 1) {
            return null;
        }

        return max(0, (int) $match[1]);
    }

    private function pdfImageImportance(?int $width, ?int $height, int $byteLength, bool $imageMask): string
    {
        if ($imageMask) {
            return 'mask';
        }
        if ($width !== null && $height !== null && ($width < 16 || $height < 16)) {
            return 'tiny';
        }
        if ($byteLength >= 8192) {
            return 'important';
        }
        if ($width !== null && $height !== null && ($width * $height >= 10000 || ($width >= 96 && $height >= 96))) {
            return 'important';
        }

        return 'small';
    }

    /**
     * @param list<string> $filters
     */
    private function pdfEmbeddableMimeType(array $filters): ?string
    {
        if ($filters === ['DCTDecode'] || $filters === ['DCT']) {
            return 'image/jpeg';
        }
        if ($filters === ['JPXDecode']) {
            return 'image/jp2';
        }

        return null;
    }

    /**
     * @param list<string> $filters
     * @return array{0:string, 1:string, 2:string}|null
     */
    private function pdfFlateImagePng(string $objectBody, array $filters, string $stream, ?int $width, ?int $height, bool $imageMask): ?array
    {
        if ($filters !== ['FlateDecode'] && $filters !== ['Fl']) {
            return null;
        }
        if ($width === null || $height === null || $width <= 0 || $height <= 0 || $width * $height > 8000000) {
            return null;
        }

        $bitsPerComponent = $this->pdfIntegerEntry($objectBody, 'BitsPerComponent') ?? ($imageMask ? 1 : 8);
        $pixelCount = $width * $height;
        $decoded = $this->pdfFlateDecode($stream, min(self::MAX_PDF_IMAGE_BYTES, $pixelCount * 4));
        if ($decoded === null || $decoded === '') {
            return null;
        }

        if ($bitsPerComponent === 8) {
            $decodedLength = strlen($decoded);
            if ($decodedLength === $pixelCount) {
                return ['image/png', '.png', $this->pngEncodeGrayscale8($width, $height, $decoded)];
            }
            if ($decodedLength === $pixelCount * 3) {
                return ['image/png', '.png', $this->pngEncodeRgb8($width, $height, $decoded)];
            }
            if ($decodedLength === $pixelCount * 4) {
                return ['image/png', '.png', $this->pngEncodeRgba8($width, $height, $decoded)];
            }
        }

        if ($bitsPerComponent === 1) {
            $rowBytes = (int) ceil($width / 8);
            if (strlen($decoded) !== $rowBytes * $height) {
                return null;
            }

            return ['image/png', '.png', $this->pngEncodeGrayscale8(
                $width,
                $height,
                $this->unpackOneBitImageRows($decoded, $width, $height, $rowBytes)
            )];
        }

        return null;
    }

    private function pdfFlateDecode(string $stream, int $maxOutputBytes): ?string
    {
        $maxOutputBytes = max(1, min(self::MAX_PDF_IMAGE_BYTES, $maxOutputBytes));
        $decoded = @gzuncompress($stream, $maxOutputBytes + 1);
        if (is_string($decoded) && strlen($decoded) <= $maxOutputBytes) {
            return $decoded;
        }

        $decoded = @gzdecode($stream, $maxOutputBytes + 1);
        if (is_string($decoded) && strlen($decoded) <= $maxOutputBytes) {
            return $decoded;
        }

        if (strlen($stream) > 2) {
            $decoded = @gzinflate(substr($stream, 2), $maxOutputBytes + 1);
            if (is_string($decoded) && strlen($decoded) <= $maxOutputBytes) {
                return $decoded;
            }
        }

        return null;
    }

    private function unpackOneBitImageRows(string $bytes, int $width, int $height, int $rowBytes): string
    {
        $pixels = '';
        for ($y = 0; $y < $height; $y++) {
            $rowOffset = $y * $rowBytes;
            for ($x = 0; $x < $width; $x++) {
                $byte = ord($bytes[$rowOffset + intdiv($x, 8)]);
                $bit = ($byte >> (7 - ($x % 8))) & 1;
                $pixels .= $bit === 1 ? "\xff" : "\x00";
            }
        }

        return $pixels;
    }

    private function pngHasDimensions(string $bytes, int $width, int $height): bool
    {
        if (strlen($bytes) < 24 || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n" || substr($bytes, 12, 4) !== 'IHDR') {
            return false;
        }
        $dimensions = unpack('Nwidth/Nheight', substr($bytes, 16, 8));

        return is_array($dimensions)
            && (int) ($dimensions['width'] ?? 0) === $width
            && (int) ($dimensions['height'] ?? 0) === $height;
    }

    private function pdfRasterHasDimensions(string $bytes, string $mimeType, int $width, int $height): bool
    {
        return match ($mimeType) {
            'image/png' => $this->pngHasDimensions($bytes, $width, $height),
            'image/avif' => $this->avifHasDimensions($bytes, $width, $height),
            default => false,
        };
    }

    private function pdfRasterExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/avif' => '.avif',
            default => '.png',
        };
    }

    /**
     * Verify the minimal AVIF container structure needed to bind a supplied
     * browser/build raster to the PDF Image XObject dimensions. The decoder
     * remains external, but requiring an AVIF brand and an ispe image-spatial
     * extent avoids accepting a mislabeled opaque byte string.
     */
    private function avifHasDimensions(string $bytes, int $width, int $height): bool
    {
        $length = strlen($bytes);
        if ($length < 24) {
            return false;
        }

        $hasAvifBrand = false;
        $offset = 0;
        while ($offset + 8 <= $length) {
            $header = unpack('Nsize', substr($bytes, $offset, 4));
            $boxLength = is_array($header) ? (int) ($header['size'] ?? 0) : 0;
            if ($boxLength === 0) {
                $boxLength = $length - $offset;
            }
            // Extended-length ISO BMFF boxes are not needed for bounded image
            // rasters. Reject them rather than risk an integer-width error.
            if ($boxLength === 1 || $boxLength < 8 || $boxLength > $length - $offset) {
                return false;
            }

            $type = substr($bytes, $offset + 4, 4);
            if ($type === 'ftyp' && $boxLength >= 16) {
                for ($brandOffset = $offset + 8; $brandOffset + 4 <= $offset + $boxLength; $brandOffset += 4) {
                    $brand = substr($bytes, $brandOffset, 4);
                    if ($brand === 'avif' || $brand === 'avis') {
                        $hasAvifBrand = true;
                        break;
                    }
                }
            }
            $offset += $boxLength;
        }
        if (!$hasAvifBrand) {
            return false;
        }

        // `ispe` lives below the meta/iprp/ipco hierarchy. Looking for a
        // bounded complete box is deliberately enough here: dimensions bind
        // this media to an already validated PDF object and the browser still
        // performs the definitive AVIF decode when it displays it.
        $search = 0;
        while (($typeOffset = strpos($bytes, 'ispe', $search)) !== false) {
            $boxOffset = $typeOffset - 4;
            if ($boxOffset >= 0 && $boxOffset + 20 <= $length) {
                $header = unpack('Nsize', substr($bytes, $boxOffset, 4));
                $boxLength = is_array($header) ? (int) ($header['size'] ?? 0) : 0;
                if ($boxLength >= 20 && $boxLength <= $length - $boxOffset) {
                    $dimensions = unpack('Nwidth/Nheight', substr($bytes, $boxOffset + 12, 8));
                    if (is_array($dimensions)
                        && (int) ($dimensions['width'] ?? 0) === $width
                        && (int) ($dimensions['height'] ?? 0) === $height) {
                        return true;
                    }
                }
            }
            $search = $typeOffset + 4;
        }

        return false;
    }

    private function pngEncodeGrayscale8(int $width, int $height, string $pixels): string
    {
        return $this->pngEncode($width, $height, 0, $this->pngScanlines($pixels, $height, $width));
    }

    private function pngEncodeRgb8(int $width, int $height, string $pixels): string
    {
        return $this->pngEncode($width, $height, 2, $this->pngScanlines($pixels, $height, $width * 3));
    }

    private function pngEncodeRgba8(int $width, int $height, string $pixels): string
    {
        return $this->pngEncode($width, $height, 6, $this->pngScanlines($pixels, $height, $width * 4));
    }

    private function pngScanlines(string $pixels, int $height, int $rowLength): string
    {
        $scanlines = '';
        for ($y = 0; $y < $height; $y++) {
            $scanlines .= "\x00" . substr($pixels, $y * $rowLength, $rowLength);
        }

        return $scanlines;
    }

    private function pngEncode(int $width, int $height, int $colorType, string $scanlines): string
    {
        return "\x89PNG\r\n\x1a\n"
            . $this->pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, $colorType, 0, 0, 0))
            . $this->pngChunk('IDAT', gzcompress($scanlines))
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function isUri(string $source): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1;
    }

    private function looksLikeImagePath(string $path): bool
    {
        return str_starts_with($this->mimeTypeFromPath($path), 'image/');
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'apng' => 'image/apng',
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            'jpeg', 'jpg', 'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'svg', 'svgz' => 'image/svg+xml',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'tif', 'tiff' => 'image/tiff',
            'jp2', 'jpx' => 'image/jp2',
            default => 'application/octet-stream',
        };
    }

    private function diagnosticToken(string $source): string
    {
        $source = preg_replace('/[^A-Za-z0-9._-]+/', '-', $source) ?? $source;

        return substr($source, 0, 96);
    }
}
