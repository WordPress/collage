<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MediaBag
{
    /** @var array<string, array{source:string, canonicalSource:string, sourcePath:string, path:string, pathRepairSummary:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, sha1:string, byteLength:int}> */
    private array $itemsByCanonicalSource = [];

    /**
     * @return array{contents:string, mimeType:string}
     */
    public static function decodeDataUri(string $uri): array
    {
        if (!preg_match('/\Adata:([^,]*),(.*)\z/s', $uri, $matches)) {
            throw new \InvalidArgumentException('Invalid media bag data URI');
        }

        $metadata = $matches[1];
        $payload = $matches[2];
        $parts = $metadata === '' ? [] : explode(';', $metadata);
        $mimeType = $parts[0] ?? '';
        if ($mimeType === '') {
            $mimeType = 'text/plain';
        }

        $base64 = in_array('base64', array_map('strtolower', $parts), true);
        if ($base64) {
            $payload = explode(';', $payload, 2)[0];
            $decoded = base64_decode(preg_replace('/\s+/', '', $payload) ?? $payload, true);
            if ($decoded === false) {
                throw new \InvalidArgumentException('Invalid media bag base64 data URI');
            }

            return ['contents' => $decoded, 'mimeType' => strtolower($mimeType)];
        }

        return ['contents' => rawurldecode($payload), 'mimeType' => strtolower($mimeType)];
    }

    public function insertDataUri(string $uri): void
    {
        $data = self::decodeDataUri($uri);
        $this->insertMedia($uri, $data['mimeType'], $data['contents']);
    }

    public function insertMedia(string $source, ?string $mimeType, string $contents): void
    {
        $entry = self::directoryEntry($source, $mimeType, strlen($contents), sha1($contents));
        $this->itemsByCanonicalSource[$entry['canonicalSource']] = $entry + ['contents' => $contents];
    }

    /**
     * Build the stable media metadata without retaining its body. EPUB uses
     * this after hashing a ZIP entry as a stream.
     *
     * @return array{source:string, canonicalSource:string, sourcePath:string, path:string, pathRepairSummary:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, sha1:string, byteLength:int}
     */
    public static function directoryEntry(string $source, ?string $mimeType, int $byteLength, string $sha1): array
    {
        if ($source === '') {
            throw new \InvalidArgumentException('Media bag source must not be empty');
        }
        if ($byteLength < 0) {
            throw new \InvalidArgumentException('Media bag byte length must not be negative');
        }
        if (preg_match('/^[a-f0-9]{40}$/', $sha1) !== 1) {
            throw new \InvalidArgumentException('Media bag SHA-1 must be a lowercase hexadecimal digest');
        }

        $canonicalSource = self::canonicalizeSource($source);
        $decodedSource = rawurldecode($canonicalSource);
        $sourcePath = str_starts_with($source, 'data:')
            ? 'data-uri'
            : self::uriPathOrSource($source, $decodedSource);
        $inferredMimeType = self::mimeTypeFromPath($sourcePath);
        $declaredMimeType = $mimeType === null ? '' : self::normalizeMimeType($mimeType);
        $mimeTypeSource = $declaredMimeType === '' ? 'path' : 'declared';
        $normalizedMimeType = $mimeTypeSource === 'path'
            ? $inferredMimeType
            : $declaredMimeType;
        $hashPath = $sha1 . self::extensionFor($normalizedMimeType, $sourcePath);
        $path = str_starts_with($source, 'data:')
            ? $hashPath
            : (self::isSafeRelativeMediaPath($decodedSource) ? $decodedSource : $hashPath);

        return [
            'source' => $source,
            'canonicalSource' => $canonicalSource,
            'sourcePath' => $sourcePath,
            'path' => $path,
            'pathRepairSummary' => self::pathRepairSummary($source, $canonicalSource, $decodedSource, $path),
            'mimeType' => $normalizedMimeType,
            'mimeTypeSource' => $mimeTypeSource,
            'inferredMimeType' => $inferredMimeType,
            'mimeRepairSummary' => self::mimeRepairSummary($source, $sourcePath, $path, $mimeTypeSource, $normalizedMimeType, $inferredMimeType),
            'sha1' => $sha1,
            'byteLength' => $byteLength,
        ];
    }

    public function deleteMedia(string $source): void
    {
        unset($this->itemsByCanonicalSource[self::canonicalizeSource($source)]);
    }

    public function has(string $source): bool
    {
        return $this->lookup($source) !== null;
    }

    /**
     * @return array{source:string, canonicalSource:string, sourcePath:string, path:string, pathRepairSummary:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, sha1:string, byteLength:int}|null
     */
    public function lookup(string $source): ?array
    {
        return $this->itemsByCanonicalSource[self::canonicalizeSource($source)] ?? null;
    }

    /**
     * @return list<array{path:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, pathRepairSummary:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string}>
     */
    public function directory(): array
    {
        $items = [];
        foreach ($this->itemsByCanonicalSource as $item) {
            $items[] = [
                'path' => $item['path'],
                'mimeType' => $item['mimeType'],
                'byteLength' => $item['byteLength'],
                'sha1' => $item['sha1'],
                'source' => $item['source'],
                'canonicalSource' => $item['canonicalSource'],
                'sourcePath' => $item['sourcePath'],
                'pathRepairSummary' => $item['pathRepairSummary'],
                'mimeTypeSource' => $item['mimeTypeSource'],
                'inferredMimeType' => $item['inferredMimeType'],
                'mimeRepairSummary' => $item['mimeRepairSummary'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $items;
    }

    /**
     * @return list<array{path:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, pathRepairSummary:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string}>
     */
    public function mediaItems(): array
    {
        $items = [];
        foreach ($this->itemsByCanonicalSource as $item) {
            $items[] = [
                'path' => $item['path'],
                'mimeType' => $item['mimeType'],
                'byteLength' => $item['byteLength'],
                'sha1' => $item['sha1'],
                'source' => $item['source'],
                'canonicalSource' => $item['canonicalSource'],
                'sourcePath' => $item['sourcePath'],
                'pathRepairSummary' => $item['pathRepairSummary'],
                'mimeTypeSource' => $item['mimeTypeSource'],
                'inferredMimeType' => $item['inferredMimeType'],
                'mimeRepairSummary' => $item['mimeRepairSummary'],
                'contents' => $item['contents'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        return $items;
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resources
     * @return array{document:AstNode, diagnostics:list<string>}
     */
    public function fillDocument(AstNode $document, array $resources): array
    {
        $diagnostics = [];
        $resourcesByCanonicalSource = self::canonicalResourceMap($resources);
        $document = $this->mapResourceNodes($document, function (AstNode $image) use ($resources, $resourcesByCanonicalSource, &$diagnostics): AstNode {
            $source = (string) $image->attr('url', '');
            if ($source === '' || $this->lookupMediaSource($source) !== null) {
                return $image;
            }

            if (str_starts_with($source, 'data:')) {
                try {
                    $this->insertDataUri($source);
                } catch (\InvalidArgumentException) {
                    $diagnostics[] = 'media-resource-invalid:data-uri';

                    return $this->placeholderFor($image);
                }

                $diagnostics[] = 'media-resource-loaded:data-uri';

                return $image;
            }

            $lookup = self::lookupResource($source, $resources, $resourcesByCanonicalSource, false);
            foreach ($lookup['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            if ($lookup['resource'] !== null) {
                $resource = $lookup['resource'];
                $contents = is_array($resource)
                    ? (string) ($resource['contents'] ?? $resource['data'] ?? '')
                    : (string) $resource;
                $mimeType = is_array($resource) ? ($resource['mimeType'] ?? null) : null;
                $this->insertMedia($source, is_string($mimeType) ? $mimeType : null, $contents);
                $item = $this->lookup($source);
                if ($item !== null && self::hasContentTypePathConflict($item['source'], $item['mimeType'])) {
                    $diagnostics[] = 'media-resource-content-type-conflict:' . self::diagnosticSource($source);
                }
                $diagnostics[] = 'media-resource-loaded:' . self::diagnosticSource($source);

                return $image;
            }

            $diagnostics[] = 'media-resource-missing:' . self::diagnosticSource($source);

            return $this->placeholderFor($image);
        }, function (AstNode $link) use ($resources, $resourcesByCanonicalSource, &$diagnostics): AstNode {
            $source = (string) $link->attr('url', '');
            if ($source === '' || $this->lookupMediaSource($source) !== null) {
                return $link;
            }

            if (str_starts_with($source, 'data:')) {
                try {
                    $this->insertDataUri($source);
                } catch (\InvalidArgumentException) {
                    $diagnostics[] = 'media-resource-link-invalid:data-uri';

                    return $link;
                }

                $diagnostics[] = 'media-resource-link-loaded:data-uri';

                return $link;
            }

            $lookup = self::lookupResource($source, $resources, $resourcesByCanonicalSource, true);
            foreach ($lookup['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            if ($lookup['resource'] !== null) {
                $resource = $lookup['resource'];
                $contents = is_array($resource)
                    ? (string) ($resource['contents'] ?? $resource['data'] ?? '')
                    : (string) $resource;
                $mimeType = is_array($resource) ? ($resource['mimeType'] ?? null) : null;
                $this->insertMedia($source, is_string($mimeType) ? $mimeType : null, $contents);
                $item = $this->lookup($source);
                if ($item !== null && self::hasContentTypePathConflict($item['source'], $item['mimeType'])) {
                    $diagnostics[] = 'media-resource-content-type-conflict:' . self::diagnosticSource($source);
                }
                $diagnostics[] = 'media-resource-link-loaded:' . self::diagnosticSource($source);

                return $link;
            }

            return $link;
        });

        return ['document' => $document, 'diagnostics' => $diagnostics];
    }

    /**
     * @return array{
     *     document:AstNode,
     *     entries:list<array{path:string, mediaPath:string, mimeType:string, byteLength:int, sha1:string, source:string, canonicalSource:string, sourcePath:string, pathRepairSummary:string, extractionPathRepairSummary:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, linkedMimeGroup?:string, linkedMimeGroupSize?:int}>,
     *     diagnostics:list<string>
     * }
     */
    public function extractMedia(AstNode $document, string $destination): array
    {
        $destination = self::normalizeExtractionDestination($destination);
        $entries = [];
        $diagnostics = [];
        $extractionPlan = $this->plannedExtractionPlan();
        $plannedPaths = $extractionPlan['paths'];
        $linkedMimeGroups = $this->linkedMimeGroups($document);
        foreach ($this->itemsForExtraction() as $item) {
            $plan = $plannedPaths[$item['canonicalSource']] ?? ['path' => $item['path'], 'collision' => 'none'];
            $mediaPath = $plan['path'];
            $portableSource = self::portableProvenanceSource($item, 'source');
            $portableCanonicalSource = self::portableProvenanceSource($item, 'canonicalSource');
            $entry = [
                'path' => $destination . '/' . $mediaPath,
                'mediaPath' => $mediaPath,
                'mimeType' => $item['mimeType'],
                'byteLength' => $item['byteLength'],
                'sha1' => $item['sha1'],
                'source' => $portableSource,
                'canonicalSource' => $portableCanonicalSource,
                'sourcePath' => $item['sourcePath'],
                'pathRepairSummary' => $item['pathRepairSummary'],
                'extractionPathRepairSummary' => self::extractionPathRepairSummary($item, $plan),
                'mimeTypeSource' => $item['mimeTypeSource'],
                'inferredMimeType' => $item['inferredMimeType'],
                'mimeRepairSummary' => $item['mimeRepairSummary'],
                'contents' => $item['contents'],
            ];
            foreach ($extractionPlan['diagnostics'][$item['canonicalSource']] ?? [] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $linkedMimeGroup = $linkedMimeGroups[$item['canonicalSource']] ?? null;
            if ($linkedMimeGroup !== null) {
                $entry['linkedMimeGroup'] = $linkedMimeGroup['group'];
                $entry['linkedMimeGroupSize'] = $linkedMimeGroup['size'];
            }
            $entries[] = $entry;
            if ($mediaPath !== $item['path']) {
                $diagnostics[] = 'media-resource-path-collision:' . self::diagnosticSource($item['source']);
                if ($plan['collision'] === 'casefold') {
                    $diagnostics[] = 'media-resource-path-casefold-collision:' . self::diagnosticSource($item['source']);
                }
            }
            if (self::hasContentTypePathConflict($item['source'], $item['mimeType'])) {
                $diagnostics[] = 'media-resource-content-type-conflict:' . self::diagnosticSource($item['source']);
            }
        }

        usort($entries, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

        $document = $this->mapResourceNodes($document, function (AstNode $image) use ($destination, $plannedPaths, &$diagnostics): AstNode {
            $source = (string) $image->attr('url', '');
            $item = $this->lookupMediaSource($source);
            if ($item === null) {
                return $image;
            }

            $attrs = $image->attrs;
            $plan = $plannedPaths[$item['canonicalSource']] ?? ['path' => $item['path'], 'collision' => 'none'];
            $mediaPath = $plan['path'];
            $mappedUrl = $destination . '/' . $mediaPath;
            $attrs['url'] = $mappedUrl;
            $attrs['attributes'] = $this->mediaProvenanceAttributes($attrs, $item, $mediaPath, $mappedUrl, $plan, null);
            $diagnostics[] = 'media-resource-mapped:' . self::diagnosticSource($source);

            return new AstNode($image->type, $attrs, $image->children);
        }, function (AstNode $link) use ($destination, $plannedPaths, $linkedMimeGroups, &$diagnostics): AstNode {
            $source = (string) $link->attr('url', '');
            $item = $this->lookupMediaSource($source);
            if ($item === null) {
                return $link;
            }

            $attrs = $link->attrs;
            $plan = $plannedPaths[$item['canonicalSource']] ?? ['path' => $item['path'], 'collision' => 'none'];
            $mediaPath = $plan['path'];
            $mappedUrl = $destination . '/' . $mediaPath;
            $attrs['url'] = $mappedUrl;
            $attrs['attributes'] = $this->mediaProvenanceAttributes(
                $attrs,
                $item,
                $mediaPath,
                $mappedUrl,
                $plan,
                $linkedMimeGroups[$item['canonicalSource']] ?? null
            );
            $diagnostics[] = 'media-resource-link-mapped:' . self::diagnosticSource($source);

            return new AstNode($link->type, $attrs, $link->children);
        });

        return [
            'document' => $document,
            'entries' => $entries,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array{source:string, canonicalSource:string, sourcePath:string, path:string, pathRepairSummary:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, sha1:string, byteLength:int} $item
     * @param array{path:string, collision:string} $plan
     * @param array{group:string, size:int}|null $linkedMimeGroup
     * @return array<string, string>
     */
    private function mediaProvenanceAttributes(
        array $attrs,
        array $item,
        string $mediaPath,
        string $mappedUrl,
        array $plan,
        ?array $linkedMimeGroup
    ): array
    {
        $attributes = [];
        $existingAttributes = $attrs['attributes'] ?? [];
        if (is_array($existingAttributes)) {
            foreach ($existingAttributes as $name => $value) {
                if (!is_string($name)) {
                    continue;
                }

                $value = (string) $value;
                if ($value === '') {
                    continue;
                }

                $attributes[$name] = $value;
            }
        }

        $portableSource = self::portableProvenanceSource($item, 'source');
        $portableCanonicalSource = self::portableProvenanceSource($item, 'canonicalSource');
        $attributes = array_replace($attributes, [
            'data-pandoc-media-source' => $portableSource,
            'data-pandoc-media-canonical-source' => $portableCanonicalSource,
            'data-pandoc-media-original-path' => $item['path'],
            'data-pandoc-media-path' => $mediaPath,
            'data-pandoc-media-target' => $mappedUrl,
            'data-pandoc-media-type' => $item['mimeType'],
            'data-pandoc-media-bytes' => (string) $item['byteLength'],
            'data-pandoc-media-sha1' => $item['sha1'],
            'data-pandoc-media-source-path' => $item['sourcePath'],
            'data-pandoc-media-source-sha1' => str_starts_with($item['source'], 'data:')
                ? $item['sha1']
                : sha1($item['source']),
            'data-pandoc-media-path-repaired' => $mediaPath === $item['path'] ? 'false' : 'true',
            'data-pandoc-media-path-repair' => self::extractionPathRepairSummary($item, $plan),
            'data-pandoc-media-mime-source' => $item['mimeTypeSource'],
            'data-pandoc-media-inferred-type' => $item['inferredMimeType'],
            'data-pandoc-media-mime-repair' => $item['mimeRepairSummary'],
        ]);

        if ($linkedMimeGroup !== null) {
            $attributes['data-pandoc-media-linked-mime-group'] = $linkedMimeGroup['group'];
            $attributes['data-pandoc-media-linked-mime-group-size'] = (string) $linkedMimeGroup['size'];
        }

        return $attributes;
    }

    /**
     * A resolved data URI is content, not durable provenance. Retaining the
     * encoded body in block attributes and extraction manifests can multiply
     * one browser-rendered image across every PDF segment and can make the
     * resulting WordPress save larger than its visible document. The media
     * body is already stored separately and integrity checked by SHA-1, so a
     * stable digest is the complete portable identity needed after mapping.
     *
     * @param array{source:string,sha1:string} $item
     */
    private static function portableProvenanceSource(array $item, string $field): string
    {
        return str_starts_with($item['source'], 'data:')
            ? 'data-uri:sha1:' . $item['sha1']
            : (string) ($item[$field] ?? $item['source']);
    }

    /**
     * @return list<array{source:string, canonicalSource:string, sourcePath:string, path:string, pathRepairSummary:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, sha1:string, byteLength:int}>
     */
    private function itemsForExtraction(): array
    {
        $items = array_values($this->itemsByCanonicalSource);
        usort($items, static function (array $a, array $b): int {
            $path = $a['path'] <=> $b['path'];
            if ($path !== 0) {
                return $path;
            }

            $aLiteral = $a['canonicalSource'] === $a['path'];
            $bLiteral = $b['canonicalSource'] === $b['path'];
            if ($aLiteral !== $bLiteral) {
                return $aLiteral ? -1 : 1;
            }

            return $a['canonicalSource'] <=> $b['canonicalSource'];
        });

        return $items;
    }

    /**
     * @return array{paths:array<string, array{path:string, collision:string}>, diagnostics:array<string, list<string>>}
     */
    private function plannedExtractionPlan(): array
    {
        $paths = [];
        $used = [];
        $usedCaseFolded = [];
        $diagnostics = [];
        foreach ($this->itemsForExtraction() as $item) {
            $path = $item['path'];
            $collision = 'none';
            $caseFoldedPath = self::caseFoldMediaPath($path);
            $exactCollision = isset($used[$path]) && $used[$path] !== $item['sha1'];
            $caseFoldedCollision = !$exactCollision
                && isset($usedCaseFolded[$caseFoldedPath])
                && (
                    $usedCaseFolded[$caseFoldedPath]['path'] !== $path
                    || $usedCaseFolded[$caseFoldedPath]['sha1'] !== $item['sha1']
                );
            if ($exactCollision || $caseFoldedCollision) {
                $collision = $exactCollision ? 'path' : 'casefold';
                if ($caseFoldedCollision) {
                    $diagnostics[$item['canonicalSource']][] = 'media-resource-path-casefold-conflict:' . self::diagnosticSource($item['source']);
                }
                $path = self::disambiguateMediaPath($path, $item['sha1'], $item['canonicalSource'], $used, $usedCaseFolded);
            }

            $paths[$item['canonicalSource']] = [
                'path' => $path,
                'collision' => $collision,
            ];
            $used[$path] = $item['sha1'];
            $usedCaseFolded[self::caseFoldMediaPath($path)] = [
                'path' => $path,
                'sha1' => $item['sha1'],
            ];
        }

        return ['paths' => $paths, 'diagnostics' => $diagnostics];
    }

    private static function canonicalizeSource(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return $source;
        }

        $pathSource = str_replace('\\', '/', $source);
        if (self::isWindowsDrivePath($pathSource)) {
            return self::normalizePath($pathSource);
        }

        if (self::isUri($source)) {
            return $source;
        }

        return self::normalizePath($pathSource);
    }

    private static function normalizePath(string $path): string
    {
        $prefix = '';
        if (preg_match('/\A([A-Za-z]:)(\/?)(.*)\z/', $path, $matches)) {
            $prefix = $matches[1] . '/';
            $path = $matches[3];
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $path = ltrim($path, '/');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);
        if ($prefix !== '') {
            return $prefix . $normalized;
        }

        return $normalized === '' ? '.' : $normalized;
    }

    private static function isUri(string $source): bool
    {
        return !self::isWindowsDrivePath(str_replace('\\', '/', $source))
            && preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1;
    }

    private static function isWindowsDrivePath(string $source): bool
    {
        return preg_match('/\A[A-Za-z]:\//', $source) === 1;
    }

    private static function uriPathOrSource(string $source, string $decodedSource): string
    {
        if (!self::isUri($source)) {
            return $decodedSource;
        }

        $path = parse_url($source, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? rawurldecode($path) : $decodedSource;
    }

    private static function isSafeRelativeMediaPath(string $path): bool
    {
        return $path !== ''
            && $path !== '.'
            && !str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && preg_match('/\A[A-Za-z]:[\/\\\\]/', $path) !== 1
            && !str_contains($path, '..')
            && !str_contains($path, '%')
            && !str_contains($path, '?')
            && !str_contains($path, '#')
            && !self::isUri($path);
    }

    private static function mimeTypeFromPath(string $path): string
    {
        if (str_ends_with(strtolower($path), '.gz')) {
            $path = substr($path, 0, -3);
        }

        return match (strtolower(self::pathExtension($path))) {
            '.apng' => 'image/apng',
            '.avif' => 'image/avif',
            '.gif' => 'image/gif',
            '.jpeg', '.jpg', '.jpe' => 'image/jpeg',
            '.png' => 'image/png',
            '.svg', '.svgz' => 'image/svg+xml',
            '.webp' => 'image/webp',
            '.bmp' => 'image/bmp',
            '.ico' => 'image/x-icon',
            '.tif', '.tiff' => 'image/tiff',
            '.css' => 'text/css',
            '.js', '.mjs' => 'text/javascript',
            '.json', '.map', '.webmanifest' => 'application/json',
            '.html', '.htm' => 'text/html',
            '.xhtml' => 'application/xhtml+xml',
            '.xml' => 'application/xml',
            '.mp3' => 'audio/mpeg',
            '.m4a' => 'audio/mp4',
            '.ogg', '.oga' => 'audio/ogg',
            '.wav' => 'audio/wav',
            '.flac' => 'audio/flac',
            '.mp4', '.m4v' => 'video/mp4',
            '.webm' => 'video/webm',
            '.ogv' => 'video/ogg',
            '.woff' => 'font/woff',
            '.woff2' => 'font/woff2',
            '.ttf' => 'font/ttf',
            '.otf' => 'font/otf',
            '.pdf' => 'application/pdf',
            '.epub' => 'application/epub+zip',
            '.txt', '.text' => 'text/plain',
            '.md', '.markdown' => 'text/markdown',
            '.csv' => 'text/csv',
            '.tsv' => 'text/tab-separated-values',
            default => 'application/octet-stream',
        };
    }

    private static function extensionFor(string $mimeType, string $path): string
    {
        $mimeExtension = match (strtolower($mimeType)) {
            'image/apng' => '.apng',
            'image/avif' => '.avif',
            'image/gif' => '.gif',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/svg+xml' => '.svg',
            'image/webp' => '.webp',
            'image/bmp' => '.bmp',
            'image/x-icon', 'image/vnd.microsoft.icon' => '.ico',
            'image/tiff' => '.tiff',
            'text/css' => '.css',
            'text/javascript', 'application/javascript', 'application/ecmascript' => '.js',
            'application/json' => '.json',
            'text/html' => '.html',
            'application/xhtml+xml' => '.xhtml',
            'application/xml', 'text/xml' => '.xml',
            'audio/mpeg' => '.mp3',
            'audio/mp4' => '.m4a',
            'audio/ogg' => '.ogg',
            'audio/wav', 'audio/wave', 'audio/x-wav' => '.wav',
            'audio/flac' => '.flac',
            'video/mp4' => '.mp4',
            'video/webm' => '.webm',
            'video/ogg' => '.ogv',
            'font/woff', 'application/font-woff' => '.woff',
            'font/woff2' => '.woff2',
            'font/ttf', 'application/font-sfnt' => '.ttf',
            'font/otf', 'application/vnd.ms-opentype' => '.otf',
            'application/pdf' => '.pdf',
            'application/epub+zip' => '.epub',
            'text/plain' => '.txt',
            'text/markdown' => '.md',
            'text/csv' => '.csv',
            'text/tab-separated-values' => '.tsv',
            default => '',
        };
        if ($mimeExtension !== '') {
            return $mimeExtension;
        }

        $extension = self::pathExtension($path);

        return str_contains($extension, '%') ? '' : $extension;
    }

    /**
     * @param array<string, string> $usedPaths
     * @param array<string, array{path:string, sha1:string}> $usedCaseFoldedPaths
     */
    private static function disambiguateMediaPath(
        string $path,
        string $sha1,
        string $canonicalSource,
        array $usedPaths,
        array $usedCaseFoldedPaths
    ): string
    {
        $extension = self::pathExtension($path);
        $stem = $extension === '' ? $path : substr($path, 0, -strlen($extension));
        $seed = $canonicalSource . "\0" . $sha1;

        do {
            $suffix = substr(sha1($seed), 0, 12);
            $candidate = $stem . '-' . $suffix . $extension;
            $seed = $candidate . "\0" . $seed;
            $candidateCaseFolded = self::caseFoldMediaPath($candidate);
            $caseFoldedCollision = isset($usedCaseFoldedPaths[$candidateCaseFolded])
                && (
                    $usedCaseFoldedPaths[$candidateCaseFolded]['path'] !== $candidate
                    || $usedCaseFoldedPaths[$candidateCaseFolded]['sha1'] !== $sha1
                );
        } while ((isset($usedPaths[$candidate]) && $usedPaths[$candidate] !== $sha1) || $caseFoldedCollision);

        return $candidate;
    }

    private static function caseFoldMediaPath(string $path): string
    {
        return strtolower($path);
    }

    private static function pathExtension(string $path): string
    {
        $path = strtok($path, "?#");
        $basename = basename($path === false ? '' : $path);
        $position = strrpos($basename, '.');
        if ($position === false || $position === 0) {
            return '';
        }

        return substr($basename, $position);
    }

    private static function normalizeExtractionDestination(string $destination): string
    {
        $destination = rtrim(preg_replace('#/+#', '/', trim(str_replace('\\', '/', $destination))) ?? '', '/');
        if ($destination === '' || !self::isSafeRelativeMediaPath($destination)) {
            throw new \InvalidArgumentException('Media extraction destination must be a safe relative path');
        }

        return self::normalizePath($destination);
    }

    private static function pathRepairSummary(string $source, string $canonicalSource, string $decodedSource, string $path): string
    {
        if (str_starts_with($source, 'data:')) {
            return 'data-uri-hash-path';
        }

        $reasons = [];
        if (str_contains($source, '\\')) {
            $reasons[] = 'separator-normalized-path';
        }

        if ($canonicalSource !== str_replace('\\', '/', $source)) {
            $reasons[] = 'normalized-path';
        }

        if ($decodedSource !== $canonicalSource) {
            $reasons[] = $path === $decodedSource ? 'percent-decoded-path' : 'percent-decoded-path-rejected';
        }

        if ($path !== $decodedSource) {
            if (self::isUri($source)) {
                $reasons[] = 'uri-hash-path';
            } elseif (str_starts_with($decodedSource, '/') || self::isWindowsDrivePath($decodedSource)) {
                $reasons[] = 'absolute-source-hash-path';
            } elseif (str_contains($decodedSource, '?') || str_contains($decodedSource, '#')) {
                $reasons[] = 'url-suffix-hash-path';
            } else {
                $reasons[] = 'unsafe-source-hash-path';
            }
        }

        $reasons = array_values(array_unique($reasons));

        return $reasons === [] ? 'safe-relative-path' : implode(',', $reasons);
    }

    private static function mimeRepairSummary(
        string $source,
        string $sourcePath,
        string $path,
        string $mimeTypeSource,
        string $normalizedMimeType,
        string $inferredMimeType
    ): string {
        if (str_starts_with($source, 'data:')) {
            return 'data-uri-mime';
        }

        if ($mimeTypeSource === 'path') {
            return 'inferred-from-path';
        }

        if ($inferredMimeType !== 'application/octet-stream' && $inferredMimeType !== $normalizedMimeType) {
            $extension = strtolower(self::pathExtension($sourcePath));
            $extension = $extension === '' ? 'no-extension' : $extension;
            $repair = self::mimeTypeFromPath($path) === $normalizedMimeType
                ? 'path-extension-from-content-type'
                : 'metadata-only';

            return 'extension-content-type-disagreement:' . $extension . ':' . $inferredMimeType . '=>' . $normalizedMimeType . ':' . $repair;
        }

        return 'declared-mime-matches-path';
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resources
     * @return array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>
     */
    private static function canonicalResourceMap(array $resources): array
    {
        $canonical = [];
        foreach ($resources as $source => $resource) {
            if (!is_string($source)) {
                continue;
            }

            $canonical[self::canonicalizeSource($source)] = $resource;
        }

        return $canonical;
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resources
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resourcesByCanonicalSource
     * @return array{
     *     resource:string|array{contents?:string, data?:string, mimeType?:string|null}|null,
     *     diagnostics:list<string>
     * }
     */
    private static function lookupResource(string $source, array $resources, array $resourcesByCanonicalSource, bool $linkedResource): array
    {
        $matches = self::resourceLookupMatches($source, $resources, $resourcesByCanonicalSource);
        if ($matches === []) {
            return ['resource' => null, 'diagnostics' => []];
        }

        return [
            'resource' => $matches[0]['resource'],
            'diagnostics' => self::resourceConflictDiagnostics($source, $matches, $linkedResource),
        ];
    }

    /**
     * @return list<string>
     */
    private static function resourceLookupKeys(string $source): array
    {
        return array_map(
            static fn (array $record): string => $record['key'],
            self::resourceLookupKeyRecords($source)
        );
    }

    /**
     * @return list<array{key:string, repair:string}>
     */
    private static function resourceLookupKeyRecords(string $source): array
    {
        $records = [
            ['key' => $source, 'repair' => 'exact'],
            ['key' => self::canonicalizeSource($source), 'repair' => 'canonical'],
        ];
        $pathOnlySource = self::pathOnlyRelativeSource($source);
        if ($pathOnlySource !== $source) {
            $records[] = ['key' => $pathOnlySource, 'repair' => 'path-only'];
            $records[] = ['key' => self::canonicalizeSource($pathOnlySource), 'repair' => 'path-only'];
        }
        $decodedPathOnlySource = self::decodedRelativeSourceKey($pathOnlySource);
        if ($decodedPathOnlySource !== null) {
            $records[] = ['key' => $decodedPathOnlySource, 'repair' => 'percent-decoded'];
            $records[] = ['key' => self::canonicalizeSource($decodedPathOnlySource), 'repair' => 'percent-decoded'];
        }

        $unique = [];
        $result = [];
        foreach ($records as $record) {
            if (isset($unique[$record['key']])) {
                continue;
            }

            $unique[$record['key']] = true;
            $result[] = $record;
        }

        return $result;
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resources
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resourcesByCanonicalSource
     * @return list<array{
     *     sourceKey:string,
     *     repair:string,
     *     resource:string|array{contents?:string, data?:string, mimeType?:string|null}
     * }>
     */
    private static function resourceLookupMatches(string $source, array $resources, array $resourcesByCanonicalSource): array
    {
        $matches = [];
        $seen = [];
        foreach (self::resourceLookupKeyRecords($source) as $record) {
            $key = $record['key'];
            $repair = $record['repair'];
            if (array_key_exists($key, $resources)) {
                self::appendResourceLookupMatch($matches, $seen, $key, $repair, $resources[$key]);
            }

            $canonicalKey = self::canonicalizeSource($key);
            if (array_key_exists($canonicalKey, $resources)) {
                self::appendResourceLookupMatch($matches, $seen, $canonicalKey, $repair, $resources[$canonicalKey]);
            }
            if (array_key_exists($canonicalKey, $resourcesByCanonicalSource)) {
                self::appendResourceLookupMatch($matches, $seen, $canonicalKey, $repair, $resourcesByCanonicalSource[$canonicalKey]);
            }

            foreach ($resources as $resourceSource => $resource) {
                if (!is_string($resourceSource) || self::canonicalizeSource($resourceSource) !== $canonicalKey) {
                    continue;
                }

                self::appendResourceLookupMatch($matches, $seen, $resourceSource, $repair, $resource);
            }
        }

        return $matches;
    }

    /**
     * @param list<array{sourceKey:string, repair:string, resource:string|array{contents?:string, data?:string, mimeType?:string|null}}> $matches
     * @param array<string, bool> $seen
     * @param string|array{contents?:string, data?:string, mimeType?:string|null} $resource
     */
    private static function appendResourceLookupMatch(array &$matches, array &$seen, string $sourceKey, string $repair, string|array $resource): void
    {
        $parts = self::resourceParts($sourceKey, $resource);
        $fingerprint = $sourceKey . "\0" . $parts['sha1'] . "\0" . $parts['mimeType'];
        if (isset($seen[$fingerprint])) {
            return;
        }

        $seen[$fingerprint] = true;
        $matches[] = [
            'sourceKey' => $sourceKey,
            'repair' => $repair,
            'resource' => $resource,
        ];
    }

    /**
     * @param list<array{sourceKey:string, repair:string, resource:string|array{contents?:string, data?:string, mimeType?:string|null}}> $matches
     * @return list<string>
     */
    private static function resourceConflictDiagnostics(string $source, array $matches, bool $linkedResource): array
    {
        $fingerprints = [];
        $mimeGroupFingerprints = [];
        $hasPercentDecodedCandidate = false;
        foreach ($matches as $match) {
            $parts = self::resourceParts($match['sourceKey'], $match['resource']);
            $fingerprint = $parts['sha1'] . "\0" . $parts['mimeType'];
            $fingerprints[$fingerprint] = true;
            $mimeGroupFingerprints[$parts['mimeGroup']][$fingerprint] = true;
            if ($match['repair'] === 'percent-decoded') {
                $hasPercentDecodedCandidate = true;
            }
        }

        if (count($fingerprints) < 2) {
            return [];
        }

        $diagnostics = ['media-resource-repair-conflict:' . self::diagnosticSource($source)];
        if ($hasPercentDecodedCandidate) {
            $diagnostics[] = 'media-resource-percent-decode-conflict:' . self::diagnosticSource($source);
        }
        if ($linkedResource) {
            $diagnostics[] = 'media-resource-link-duplicate-mime-summary:'
                . self::diagnosticSource($source)
                . ':'
                . self::linkedResourceMimeSummary($matches);
        }
        if ($linkedResource && self::hasMimeGroupConflict($mimeGroupFingerprints)) {
            $diagnostics[] = 'media-resource-link-mime-group-conflict:' . self::diagnosticSource($source);
        }

        return $diagnostics;
    }

    /**
     * @param list<array{sourceKey:string, repair:string, resource:string|array{contents?:string, data?:string, mimeType?:string|null}}> $matches
     */
    private static function linkedResourceMimeSummary(array $matches): string
    {
        $fingerprintsByMimeType = [];
        foreach ($matches as $match) {
            $parts = self::resourceParts($match['sourceKey'], $match['resource']);
            $fingerprint = $parts['sha1'] . "\0" . $parts['mimeType'];
            $fingerprintsByMimeType[$parts['mimeType']][$fingerprint] = true;
        }

        ksort($fingerprintsByMimeType, SORT_STRING);

        $summary = [];
        foreach ($fingerprintsByMimeType as $mimeType => $fingerprints) {
            $summary[] = $mimeType . '=' . count($fingerprints);
        }

        return implode(',', $summary);
    }

    /**
     * @param array<string, array<string, bool>> $mimeGroupFingerprints
     */
    private static function hasMimeGroupConflict(array $mimeGroupFingerprints): bool
    {
        if (count($mimeGroupFingerprints) > 1) {
            return true;
        }

        foreach ($mimeGroupFingerprints as $fingerprints) {
            if (count($fingerprints) > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|array{contents?:string, data?:string, mimeType?:string|null} $resource
     * @return array{contents:string, mimeType:string, sha1:string, mimeGroup:string}
     */
    private static function resourceParts(string $sourceKey, string|array $resource): array
    {
        $contents = is_array($resource)
            ? (string) ($resource['contents'] ?? $resource['data'] ?? '')
            : (string) $resource;
        $mimeType = is_array($resource) && isset($resource['mimeType']) && is_string($resource['mimeType'])
            ? self::normalizeMimeType($resource['mimeType'])
            : '';
        if ($mimeType === '') {
            $mimeType = self::mimeTypeFromSourcePath($sourceKey);
        }

        return [
            'contents' => $contents,
            'mimeType' => $mimeType,
            'sha1' => sha1($contents),
            'mimeGroup' => self::mimeGroup($mimeType),
        ];
    }

    private static function hasContentTypePathConflict(string $source, string $mimeType): bool
    {
        if (str_starts_with($source, 'data:')) {
            return false;
        }

        $sourceMimeType = self::mimeTypeFromSourcePath($source);
        $normalizedMimeType = self::normalizeMimeType($mimeType);

        return $sourceMimeType !== 'application/octet-stream'
            && $normalizedMimeType !== ''
            && $sourceMimeType !== $normalizedMimeType;
    }

    private static function mimeTypeFromSourcePath(string $source): string
    {
        $path = self::pathOnlyRelativeSource($source);
        if (self::isUri($source)) {
            $uriPath = parse_url($source, PHP_URL_PATH);
            if (is_string($uriPath) && $uriPath !== '') {
                $path = $uriPath;
            }
        }

        return self::mimeTypeFromPath(rawurldecode($path));
    }

    private static function normalizeMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));
        $parameterPosition = strpos($mimeType, ';');
        if ($parameterPosition !== false) {
            $mimeType = trim(substr($mimeType, 0, $parameterPosition));
        }

        return $mimeType;
    }

    private static function mimeGroup(string $mimeType): string
    {
        $slashPosition = strpos($mimeType, '/');

        return $slashPosition === false ? $mimeType : substr($mimeType, 0, $slashPosition);
    }

    private static function pathOnlyRelativeSource(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return $source;
        }

        $queryPosition = strpos($source, '?');
        $fragmentPosition = strpos($source, '#');
        $positions = array_filter(
            [$queryPosition, $fragmentPosition],
            static fn (int|false $position): bool => $position !== false
        );
        if ($positions === []) {
            return $source;
        }

        return substr($source, 0, min($positions));
    }

    /**
     * @return array{source:string, canonicalSource:string, sourcePath:string, path:string, pathRepairSummary:string, mimeType:string, mimeTypeSource:string, inferredMimeType:string, mimeRepairSummary:string, contents:string, sha1:string, byteLength:int}|null
     */
    private function lookupMediaSource(string $source): ?array
    {
        foreach (self::resourceLookupKeys($source) as $key) {
            $item = $this->lookup($key);
            if ($item !== null) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{group:string, size:int}>
     */
    private function linkedMimeGroups(AstNode $node): array
    {
        $groups = [];
        $this->collectLinkedMimeGroups($node, $groups);

        $linkedMimeGroups = [];
        foreach ($groups as $mimeType => $canonicalSources) {
            $sources = array_keys($canonicalSources);
            sort($sources);
            $size = count($sources);
            if ($size < 2) {
                continue;
            }

            $group = self::mimeGroupKey($mimeType);
            foreach ($sources as $canonicalSource) {
                $linkedMimeGroups[$canonicalSource] = [
                    'group' => $group,
                    'size' => $size,
                ];
            }
        }

        return $linkedMimeGroups;
    }

    /**
     * @param array<string, array<string, true>> $groups
     */
    private function collectLinkedMimeGroups(AstNode $node, array &$groups): void
    {
        if ($node->type === 'link') {
            $source = (string) $node->attr('url', '');
            $item = $source === '' ? null : $this->lookupMediaSource($source);
            if ($item !== null) {
                $groups[$item['mimeType']][$item['canonicalSource']] = true;
            }
        }

        foreach ($node->children as $child) {
            $this->collectLinkedMimeGroups($child, $groups);
        }
    }

    private static function mimeGroupKey(string $mimeType): string
    {
        $key = preg_replace('/[^a-z0-9]+/', '-', strtolower($mimeType)) ?? '';
        $key = trim($key, '-');

        return $key === '' ? 'unknown' : $key;
    }

    /**
     * @param array{pathRepairSummary:string} $item
     * @param array{collision:string} $plan
     */
    private static function extractionPathRepairSummary(array $item, array $plan): string
    {
        $summary = $item['pathRepairSummary'];
        $reasons = $summary === '' ? [] : explode(',', $summary);
        if ($plan['collision'] === 'path') {
            $reasons[] = 'path-collision-disambiguated';
        } elseif ($plan['collision'] === 'casefold') {
            $reasons[] = 'casefold-path-collision-disambiguated';
        }

        return implode(',', array_values(array_unique($reasons)));
    }

    private static function decodedRelativeSourceKey(string $source): ?string
    {
        if (str_starts_with($source, 'data:') || self::isUri($source) || !str_contains($source, '%')) {
            return null;
        }

        $decoded = rawurldecode($source);
        if (
            $decoded === $source
            || str_contains($decoded, "\0")
            || !self::isSafeRelativeMediaPath($decoded)
        ) {
            return null;
        }

        return $decoded;
    }

    private function placeholderFor(AstNode $image): AstNode
    {
        $attrs = $image->attrs;
        $attributes = $attrs['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }

        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }

        $attrs['classes'] = array_values(array_unique(array_merge(['image', 'placeholder'], $classes)));
        $attrs['attributes'] = array_merge([
            'original-image-src' => (string) $image->attr('url', ''),
            'original-image-title' => (string) $image->attr('title', ''),
        ], $attributes);

        return new AstNode('span', $attrs, $image->children);
    }

    /**
     * @param callable(AstNode): AstNode $mapImage
     * @param callable(AstNode): AstNode $mapLink
     */
    private function mapResourceNodes(AstNode $node, callable $mapImage, callable $mapLink): AstNode
    {
        $children = array_map(fn (AstNode $child): AstNode => $this->mapResourceNodes($child, $mapImage, $mapLink), $node->children);
        $mapped = new AstNode($node->type, $node->attrs, $children);

        return match ($mapped->type) {
            'image' => $mapImage($mapped),
            'link' => $mapLink($mapped),
            default => $mapped,
        };
    }

    private static function diagnosticSource(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return 'data-uri';
        }

        return $source;
    }
}
