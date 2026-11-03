<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ManCorpusAudit
{
    public const TOOL_NAME = 'pandoc-man-corpus-audit';

    private const DEFAULT_MAX_EXAMPLES = 12;
    private const DEFAULT_MAX_PANDOC_NATIVE_OUTPUT_BYTES = 1048576;
    private const VERDICT = 'man-corpus-confidence-not-full-manpage-parity';
    private const CLAIM = 'Audits real or fixture manpage sources by dialect, local PHP man/mdoc reader acceptance, optional pandoc executable native parsing, and normalized AST drift categories; this is corpus confidence evidence and does not assert full roff, man, or mdoc parity.';

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'attrConstructor' => true,
        'attrNative' => true,
        'constructor' => true,
        'man' => true,
        'mdoc' => true,
        'meta' => true,
        'sourceFormat' => true,
        'id' => true,
    ];

    /**
     * @param list<string> $roots
     * @param array{limit?: int, maxExamples?: int, maxPandocNativeOutputBytes?: int, pandocBin?: ?string, comparePandoc?: bool, targetDialects?: list<string>} $options
     * @return array<string, mixed>
     */
    public function run(array $roots, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));
        $maxPandocNativeOutputBytes = max(
            0,
            (int) ($options['maxPandocNativeOutputBytes'] ?? self::DEFAULT_MAX_PANDOC_NATIVE_OUTPUT_BYTES)
        );
        $comparePandoc = (bool) ($options['comparePandoc'] ?? true);
        $targetDialects = $options['targetDialects'] ?? ['man'];
        $targetDialects = $targetDialects === [] ? ['man'] : array_values(array_unique(array_map('strval', $targetDialects)));

        $rootInventory = $this->rootInventory($roots);
        $files = $this->manpageFiles($roots);
        $totalCandidateFileCount = count($files);
        if ($limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        $pandoc = $comparePandoc ? $this->resolvePandocBin($options['pandocBin'] ?? null) : null;
        $pandocVersion = $pandoc === null ? null : $this->pandocVersion($pandoc);

        $dialectCounts = [];
        $targetFileCount = 0;
        $nonTargetFileCount = 0;
        $localParsedCount = 0;
        $localParseFailures = [];
        $controlLeakExamples = [];
        $controlLeakCount = 0;
        $pandocParsedCount = 0;
        $pandocParseFailures = [];
        $bothParsedCount = 0;
        $normalizedMatchCount = 0;
        $visibleTextMatchCount = 0;
        $mismatches = [];
        $categoryCounts = [];
        $nonTargetExamples = [];

        foreach ($files as $path) {
            $sourceResult = $this->readSource($path);
            if (!$sourceResult['ok']) {
                $this->increment($dialectCounts, 'unreadable');
                $localParseFailures[] = [
                    'file' => $path,
                    'dialect' => 'unreadable',
                    'error' => $sourceResult['error'],
                ];
                $this->addCategory($categoryCounts, 'unreadable-source', $path, $maxExamples);
                continue;
            }

            $source = (string) $sourceResult['source'];
            $dialect = $this->detectDialect($source);
            $this->increment($dialectCounts, $dialect);

            if (!in_array($dialect, $targetDialects, true)) {
                ++$nonTargetFileCount;
                if (count($nonTargetExamples) < $maxExamples) {
                    $nonTargetExamples[] = ['file' => $path, 'dialect' => $dialect];
                }
                continue;
            }

            ++$targetFileCount;
            $localResult = $this->readLocalManual($source, $dialect);
            if ($localResult['ok']) {
                ++$localParsedCount;
                /** @var AstNode $localDocument */
                $localDocument = $localResult['document'];
                $leak = $this->firstControlLeak($localDocument);
                if ($leak !== null) {
                    ++$controlLeakCount;
                    $this->addCategory($categoryCounts, 'visible-control-request-leak', $path, $maxExamples);
                    if (count($controlLeakExamples) < $maxExamples) {
                        $controlLeakExamples[] = ['file' => $path, 'leak' => $leak];
                    }
                }
            } else {
                $localParseFailures[] = [
                    'file' => $path,
                    'dialect' => $dialect,
                    'error' => $localResult['error'],
                ];
                $this->addCategory($categoryCounts, 'local-parse-failure', $path, $maxExamples);
            }

            $pandocResult = ['ok' => false, 'document' => null, 'error' => 'pandoc comparison skipped'];
            if ($pandoc !== null) {
                $pandocResult = $this->readPandocNative($pandoc, $source, $dialect, $maxPandocNativeOutputBytes);
                if ($pandocResult['ok']) {
                    ++$pandocParsedCount;
                } else {
                    $pandocParseFailures[] = [
                        'file' => $path,
                        'dialect' => $dialect,
                        'error' => $pandocResult['error'],
                    ];
                    $this->addCategory($categoryCounts, 'pandoc-parse-failure', $path, $maxExamples);
                }
            }

            if ($localResult['ok'] && $pandocResult['ok']) {
                /** @var AstNode $localDocument */
                $localDocument = $localResult['document'];
                /** @var AstNode $pandocDocument */
                $pandocDocument = $pandocResult['document'];
                ++$bothParsedCount;

                $localText = $this->visibleText($localDocument);
                $pandocText = $this->visibleText($pandocDocument);
                if ($localText === $pandocText) {
                    ++$visibleTextMatchCount;
                }

                $localAst = $this->normalizedDocument($localDocument);
                $pandocAst = $this->normalizedDocument($pandocDocument);
                if ($localAst === $pandocAst) {
                    ++$normalizedMatchCount;
                    continue;
                }

                $difference = $this->firstDifference($localAst, $pandocAst) ?? 'unknown-normalized-ast-difference';
                $categories = $this->mismatchCategories($difference);
                foreach ($categories as $category) {
                    $this->addCategory($categoryCounts, $category, $path, $maxExamples);
                }
                if (count($mismatches) < $maxExamples) {
                    $mismatches[] = [
                        'file' => $path,
                        'firstDifference' => $difference,
                        'categories' => $categories,
                        'localTopTypes' => $this->topTypeSequence($localDocument),
                        'pandocTopTypes' => $this->topTypeSequence($pandocDocument),
                    ];
                }
            }
        }

        ksort($dialectCounts, SORT_STRING);
        ksort($categoryCounts, SORT_STRING);
        $localParseFailureCount = count($localParseFailures);
        $pandocParseFailureCount = count($pandocParseFailures);
        $normalizedMismatchCount = $bothParsedCount - $normalizedMatchCount;

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'man-real-world-corpus-audit',
            'roots' => $roots,
            'rootInventory' => $rootInventory,
            'targetDialects' => $targetDialects,
            'limit' => $limit,
            'maxPandocNativeOutputBytes' => $maxPandocNativeOutputBytes,
            'totalCandidateFileCount' => $totalCandidateFileCount,
            'auditedFileCount' => count($files),
            'dialectCounts' => $dialectCounts,
            'targetFileCount' => $targetFileCount,
            'nonTargetFileCount' => $nonTargetFileCount,
            'localParsedCount' => $localParsedCount,
            'localParseFailureCount' => $localParseFailureCount,
            'localControlLeakCount' => $controlLeakCount,
            'pandocComparisonStatus' => $pandoc === null ? 'skipped-pandoc-executable-missing-or-disabled' : 'completed',
            'pandocExecutable' => $pandoc,
            'pandocVersion' => $pandocVersion,
            'pandocParsedCount' => $pandocParsedCount,
            'pandocParseFailureCount' => $pandocParseFailureCount,
            'bothParsedCount' => $bothParsedCount,
            'normalizedAstMatchCount' => $normalizedMatchCount,
            'normalizedAstMismatchCount' => $normalizedMismatchCount,
            'normalizedAstMatchPercent' => self::percent($normalizedMatchCount, $bothParsedCount),
            'visibleTextMatchCount' => $visibleTextMatchCount,
            'visibleTextMismatchCount' => $bothParsedCount - $visibleTextMatchCount,
            'visibleTextMatchPercent' => self::percent($visibleTextMatchCount, $bothParsedCount),
            'corpusStatus' => self::corpusStatus(
                $targetFileCount,
                $localParseFailureCount,
                $controlLeakCount,
                $pandoc === null,
                $pandocParseFailureCount,
                $normalizedMismatchCount
            ),
            'localParseFailures' => array_slice($localParseFailures, 0, $maxExamples),
            'pandocParseFailures' => array_slice($pandocParseFailures, 0, $maxExamples),
            'controlLeakExamples' => $controlLeakExamples,
            'mismatchComparisons' => $mismatches,
            'mismatchCategories' => array_values($categoryCounts),
            'nonTargetExamples' => $nonTargetExamples,
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                $targetFileCount,
                $localParseFailureCount,
                $controlLeakCount,
                $pandoc === null,
                $pandocParseFailureCount,
                $normalizedMatchCount,
                $normalizedMismatchCount,
                $dialectCounts,
                $targetDialects
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc man corpus audit: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'roots=' . implode(',', array_map('strval', $report['roots'] ?? [])),
            'pandocExecutable=' . (string) ($report['pandocExecutable'] ?? ''),
        ];

        $lines[] = sprintf(
            'files: candidates=%d audited=%d target=%d nonTarget=%d dialects=%s',
            (int) ($report['totalCandidateFileCount'] ?? 0),
            (int) ($report['auditedFileCount'] ?? 0),
            (int) ($report['targetFileCount'] ?? 0),
            (int) ($report['nonTargetFileCount'] ?? 0),
            json_encode($report['dialectCounts'] ?? [], JSON_UNESCAPED_SLASHES) ?: '{}'
        );
        $lines[] = sprintf(
            'local: parsed=%d failures=%d controlLeaks=%d status=%s',
            (int) ($report['localParsedCount'] ?? 0),
            (int) ($report['localParseFailureCount'] ?? 0),
            (int) ($report['localControlLeakCount'] ?? 0),
            (string) ($report['corpusStatus'] ?? 'unknown')
        );
        $lines[] = sprintf(
            'pandoc: status=%s parsed=%d failures=%d bothParsed=%d',
            (string) ($report['pandocComparisonStatus'] ?? 'unknown'),
            (int) ($report['pandocParsedCount'] ?? 0),
            (int) ($report['pandocParseFailureCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0)
        );
        $lines[] = sprintf(
            'comparison: normalizedMatches=%d (%s) normalizedMismatches=%d visibleTextMatches=%d (%s)',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (int) ($report['visibleTextMatchCount'] ?? 0),
            self::formatPercent($report['visibleTextMatchPercent'] ?? null)
        );

        $lines = $this->appendExamples($lines, 'controlLeakExamples', $report['controlLeakExamples'] ?? [], 'leak');
        $lines = $this->appendExamples($lines, 'localParseFailures', $report['localParseFailures'] ?? [], 'error');
        $lines = $this->appendExamples($lines, 'pandocParseFailures', $report['pandocParseFailures'] ?? [], 'error');
        $lines = $this->appendMismatchExamples($lines, $report['mismatchComparisons'] ?? []);

        $categories = $report['mismatchCategories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $lines[] = 'mismatchCategories:';
            foreach ($categories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $examples = $category['examples'] ?? [];
                $exampleText = is_array($examples) && $examples !== []
                    ? ' examples=' . implode(',', array_map('strval', $examples))
                    : '';
                $lines[] = sprintf(
                    '- %s count=%d%s',
                    (string) ($category['category'] ?? 'unknown'),
                    (int) ($category['count'] ?? 0),
                    $exampleText
                );
            }
        }

        $lines = self::appendOrderedRemainingGaps($lines, $report);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredTargetFiles(array $report, int $requiredMinimum): bool
    {
        return $requiredMinimum >= 0
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['targetFileCount'] ?? -1) >= $requiredMinimum;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredLocalParse(array $report, int $requiredMinimum): bool
    {
        return $requiredMinimum >= 0
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['localParsedCount'] ?? -1) >= $requiredMinimum;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoLocalParseFailures(array $report): bool
    {
        return ($report['status'] ?? null) === 'completed'
            && (int) ($report['localParseFailureCount'] ?? -1) === 0;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoControlLeaks(array $report): bool
    {
        return ($report['status'] ?? null) === 'completed'
            && (int) ($report['localControlLeakCount'] ?? -1) === 0;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredPandocParse(array $report, int $requiredMinimum): bool
    {
        return $requiredMinimum >= 0
            && ($report['status'] ?? null) === 'completed'
            && ($report['pandocComparisonStatus'] ?? null) === 'completed'
            && (int) ($report['pandocParsedCount'] ?? -1) >= $requiredMinimum;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredNormalizedMatches(array $report, int $requiredMinimum): bool
    {
        return $requiredMinimum >= 0
            && ($report['status'] ?? null) === 'completed'
            && ($report['pandocComparisonStatus'] ?? null) === 'completed'
            && (int) ($report['normalizedAstMatchCount'] ?? -1) >= $requiredMinimum;
    }

    /**
     * @param list<string> $roots
     * @return list<array{root:string, exists:bool, type:string}>
     */
    private function rootInventory(array $roots): array
    {
        return array_map(static function (string $root): array {
            return [
                'root' => $root,
                'exists' => file_exists($root),
                'type' => is_dir($root) ? 'directory' : (is_file($root) ? 'file' : 'missing'),
            ];
        }, $roots);
    }

    /**
     * @param list<string> $roots
     * @return list<string>
     */
    private function manpageFiles(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            if (is_file($root) && $this->isManpagePath($root)) {
                $files[$root] = true;
                continue;
            }
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }
                $path = $fileInfo->getPathname();
                if ($this->isManpagePath($path)) {
                    $files[$path] = true;
                }
            }
        }

        $paths = array_keys($files);
        sort($paths, SORT_STRING);

        return $paths;
    }

    private function isManpagePath(string $path): bool
    {
        $name = basename($path);

        return preg_match('/\\.[1-9][A-Za-z0-9]*(?:\\.gz)?$/', $name) === 1;
    }

    /**
     * @return array{ok: bool, source: ?string, error: ?string}
     */
    private function readSource(string $path): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read manpage '{$path}'");
            }
            if (str_ends_with($path, '.gz')) {
                $decoded = gzdecode($bytes);
                if (!is_string($decoded)) {
                    throw new \RuntimeException("Unable to decode gzip manpage '{$path}'");
                }
                $bytes = $decoded;
            }

            return ['ok' => true, 'source' => $bytes, 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'source' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    private function detectDialect(string $source): string
    {
        $manPosition = null;
        $mdocPosition = null;
        if (preg_match('/^\\.(?:TH|SH|SS|TP|PP|LP|IP|B|I|BR|BI)\\b/m', $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            $manPosition = (int) $match[0][1];
        }
        if (preg_match('/^\\.(?:Dd|Dt|Os|Sh|Nm|Nd|Bl|It)\\b/m', $source, $match, PREG_OFFSET_CAPTURE) === 1) {
            $mdocPosition = (int) $match[0][1];
        }

        if ($manPosition !== null && ($mdocPosition === null || $manPosition <= $mdocPosition)) {
            return 'man';
        }
        if ($mdocPosition !== null) {
            return 'mdoc';
        }

        return 'unknown';
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readLocalManual(string $source, string $dialect): array
    {
        try {
            $reader = $dialect === 'mdoc' ? new MdocReader() : new ManReader();

            return ['ok' => true, 'document' => $reader->read($source), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readPandocNative(string $pandoc, string $source, string $dialect, int $maxOutputBytes): array
    {
        $format = in_array($dialect, ['man', 'mdoc'], true) ? $dialect : 'man';
        $result = $this->runProcess(escapeshellarg($pandoc) . ' -f ' . escapeshellarg($format) . ' -t native', $source);
        if ($result['exitCode'] !== 0) {
            return [
                'ok' => false,
                'document' => null,
                'error' => 'pandoc exited ' . $result['exitCode'] . ': ' . trim($result['stderr']),
            ];
        }
        $stdoutBytes = strlen($result['stdout']);
        if ($maxOutputBytes > 0 && $stdoutBytes > $maxOutputBytes) {
            return [
                'ok' => false,
                'document' => null,
                'error' => "pandoc native output too large: {$stdoutBytes} bytes exceeds {$maxOutputBytes} byte limit",
            ];
        }

        try {
            return ['ok' => true, 'document' => (new NativeReader())->read($result['stdout']), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    private function resolvePandocBin(?string $requested): ?string
    {
        $candidate = $requested;
        if ($candidate === null || $candidate === '') {
            $env = getenv('PANDOC_BIN');
            $candidate = is_string($env) && $env !== '' ? $env : 'pandoc';
        }

        if (str_contains($candidate, DIRECTORY_SEPARATOR)) {
            return is_file($candidate) && is_executable($candidate) ? $candidate : null;
        }

        $output = [];
        $exitCode = 0;
        exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0 || !is_string($output[0] ?? null) || trim($output[0]) === '') {
            return null;
        }

        return trim($output[0]);
    }

    private function pandocVersion(string $pandoc): ?string
    {
        $result = $this->runProcess(escapeshellarg($pandoc) . ' --version');
        if ($result['exitCode'] !== 0) {
            return null;
        }

        $lines = preg_split('/\R/', trim($result['stdout']));

        return is_array($lines) && is_string($lines[0] ?? null) && $lines[0] !== '' ? $lines[0] : null;
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runProcess(string $command, string $stdin = ''): array
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            return ['exitCode' => 127, 'stdout' => '', 'stderr' => 'Unable to start process'];
        }

        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedDocument(AstNode $document): array
    {
        return $this->normalizedNode($document);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedNode(AstNode $node): array
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if (self::isIgnoredAttr($key)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['plain', 'paragraph', 'heading', 'table_cell', 'term'], true)) {
                continue;
            }
            $normalizedValue = $this->normalizedValue($value);
            if ($normalizedValue === [] || $normalizedValue === null || $normalizedValue === '') {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $this->normalizedChildren($node->children),
        ];
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedChildren(array $children): array
    {
        $normalized = [];
        foreach ($children as $child) {
            $node = $this->normalizedNode($child);
            if ($this->isEmptyPandocTableScaffold($node)) {
                continue;
            }
            if ($this->isEmptyTextNode($node)) {
                continue;
            }
            if ($this->isEmptyParagraphNode($node)) {
                continue;
            }
            $this->appendNormalizedChild($normalized, $node);
        }

        return $this->trimBoundaryWhitespaceText($normalized);
    }

    /**
     * @param list<array<string, mixed>> $normalized
     * @param array<string, mixed> $node
     */
    private function appendNormalizedChild(array &$normalized, array $node): void
    {
        $lastIndex = count($normalized) - 1;
        if ($lastIndex >= 0 && $this->isPlainTextNode($normalized[$lastIndex]) && $this->isPlainTextNode($node)) {
            $normalized[$lastIndex]['attrs']['text'] = $this->normalizeWhitespaceText(
                (string) $normalized[$lastIndex]['attrs']['text'] . (string) $node['attrs']['text']
            );
            return;
        }

        $normalized[] = $node;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isPlainTextNode(array $node): bool
    {
        $attrs = $node['attrs'] ?? null;

        return ($node['type'] ?? null) === 'text'
            && is_array($attrs)
            && array_keys($attrs) === ['text']
            && is_string($attrs['text'])
            && ($node['children'] ?? null) === [];
    }

    private function normalizeWhitespaceText(string $text): string
    {
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function trimBoundaryWhitespaceText(array $nodes): array
    {
        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[0])) {
            array_shift($nodes);
        }

        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[count($nodes) - 1])) {
            array_pop($nodes);
        }

        return array_values($nodes);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isWhitespaceOnlyPlainTextNode(array $node): bool
    {
        if (!$this->isPlainTextNode($node)) {
            return false;
        }

        return trim((string) $node['attrs']['text']) === '';
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isEmptyTextNode(array $node): bool
    {
        if (($node['type'] ?? null) !== 'text' || ($node['children'] ?? null) !== []) {
            return false;
        }
        $attrs = $node['attrs'] ?? null;
        if ($attrs === []) {
            return true;
        }

        return is_array($attrs) && ($attrs['text'] ?? null) === '';
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isEmptyParagraphNode(array $node): bool
    {
        return in_array($node['type'] ?? null, ['paragraph', 'plain'], true)
            && ($node['children'] ?? null) === []
            && ($node['attrs'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isEmptyPandocTableScaffold(array $node): bool
    {
        return in_array($node['type'] ?? null, ['table_head', 'table_foot'], true)
            && ($node['children'] ?? []) === []
            && ($node['attrs'] ?? []) === [];
    }

    private function normalizedValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->normalizeWhitespaceText($value);
        }
        if (is_float($value)) {
            return round($value, 12);
        }
        if ($value instanceof AstNode) {
            return $this->normalizedNode($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            if ($this->isAstNodeList($value)) {
                return $this->normalizedChildren($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalizedValue($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (self::isIgnoredAttr((string) $key)) {
                continue;
            }
            $normalized[(string) $key] = $this->normalizedValue($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param list<mixed> $value
     */
    private function isAstNodeList(array $value): bool
    {
        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return $value !== [];
    }

    private static function isIgnoredAttr(string $key): bool
    {
        return isset(self::IGNORED_ATTRS[$key]) || str_starts_with($key, 'native');
    }

    private function firstControlLeak(AstNode $document): ?string
    {
        $text = $this->visibleTextForLeakDetection($document);
        if (preg_match('/(?:^|\\n)\\s*(\\.(?:TH|SH|SS|TP|PP|LP|IP|RS|RE|B|I|SM|SB|BI|BR|IB|IR|RB|RI|nf|fi|EX|EE|TS|TE|br|sp|nh|ad|if|ie|el|ds|nr|PD|so|Dd|Dt|Os|Sh|Ss|Nm|Nd|Bl|It|El|Fl|Ar|Cm|Pa|Xr))(?:\\s|$)/', $text, $match) === 1) {
            return (string) $match[1];
        }

        return null;
    }

    private function visibleTextForLeakDetection(AstNode $node): string
    {
        if (in_array($node->type, ['code', 'emph', 'quoted', 'small_caps', 'strong'], true)) {
            return '';
        }
        if ($node->type === 'text' || $node->type === 'code') {
            return (string) $node->attr('text', '');
        }
        if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
            return ' ';
        }

        $parts = array_values(array_filter(
            array_map(fn (AstNode $child): string => $this->visibleTextForLeakDetection($child), $node->children),
            static fn (string $part): bool => $part !== ''
        ));

        return implode($this->leakDetectionChildSeparator($node), $parts);
    }

    private function leakDetectionChildSeparator(AstNode $node): string
    {
        return in_array($node->type, [
            'document',
            'blockquote',
            'bullet_list',
            'ordered_list',
            'definition_list',
            'definition_item',
            'definition',
            'list_item',
            'line_block',
            'table',
            'table_head',
            'table_body',
            'table_foot',
            'row',
        ], true) ? "\n" : '';
    }

    private function visibleText(AstNode $node): string
    {
        if ($node->type === 'text' || $node->type === 'code') {
            return (string) $node->attr('text', '');
        }
        if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
            return ' ';
        }

        return implode('', array_map(fn (AstNode $child): string => $this->visibleText($child), $node->children));
    }

    /**
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
    }

    private function firstDifference(mixed $local, mixed $pandoc, string $path = 'root'): ?string
    {
        if (gettype($local) !== gettype($pandoc)) {
            return "{$path} type " . gettype($local) . ' vs ' . gettype($pandoc);
        }
        if (!is_array($local)) {
            return $local === $pandoc ? null : "{$path} value " . self::shortJson($local) . ' vs ' . self::shortJson($pandoc);
        }

        $localKeys = array_keys($local);
        $pandocKeys = array_keys($pandoc);
        if ($localKeys !== $pandocKeys) {
            return "{$path} keys " . self::shortJson($localKeys) . ' vs ' . self::shortJson($pandocKeys);
        }

        foreach ($localKeys as $key) {
            $difference = $this->firstDifference($local[$key], $pandoc[$key], $path . '.' . $key);
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mismatchCategories(string $difference): array
    {
        $lower = strtolower($difference);
        $categories = [];
        if (str_contains($lower, '.children keys')) {
            $categories[] = 'child-count-or-inline-granularity';
        }
        if (str_contains($lower, '.attrs keys') || str_contains($lower, '.attrs.')) {
            $categories[] = 'attribute-shape';
        }
        if (str_contains($lower, 'definition') || str_contains($lower, 'term')) {
            $categories[] = 'tagged-paragraph-shape';
        }
        if (str_contains($lower, 'table') || str_contains($lower, 'row') || str_contains($lower, 'cell')) {
            $categories[] = 'table-shape';
        }
        if (str_contains($lower, 'code_block')) {
            $categories[] = 'literal-block-shape';
        }
        if (str_contains($lower, '.type')) {
            $categories[] = 'node-type';
        }
        if (str_contains($lower, ' value ')) {
            $categories[] = 'scalar-value';
        }
        if ($categories === []) {
            $categories[] = 'uncategorized-normalized-ast-drift';
        }

        return array_values(array_unique($categories));
    }

    /**
     * @param array<string, int> $counts
     */
    private function increment(array &$counts, string $key): void
    {
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    /**
     * @param array<string, array{category: string, count: int, examples: list<string>}> $categoryCounts
     */
    private function addCategory(array &$categoryCounts, string $category, string $fixture, int $maxExamples): void
    {
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = ['category' => $category, 'count' => 0, 'examples' => []];
        }

        ++$categoryCounts[$category]['count'];
        if (count($categoryCounts[$category]['examples']) < $maxExamples) {
            $categoryCounts[$category]['examples'][] = $fixture;
        }
    }

    private static function corpusStatus(
        int $targetFileCount,
        int $localParseFailureCount,
        int $controlLeakCount,
        bool $pandocSkipped,
        int $pandocParseFailureCount,
        int $normalizedMismatchCount
    ): string {
        if ($targetFileCount === 0) {
            return 'not-evaluated-no-target-man-files';
        }
        if ($localParseFailureCount > 0) {
            return 'blocked-by-local-parse-failures';
        }
        if ($controlLeakCount > 0) {
            return 'local-parse-accepted-with-visible-control-leaks';
        }
        if ($pandocSkipped) {
            return 'local-parse-acceptance-observed-pandoc-comparison-skipped';
        }
        if ($pandocParseFailureCount > 0) {
            return 'local-parse-accepted-pandoc-parse-failures-observed';
        }
        if ($normalizedMismatchCount > 0) {
            return 'local-and-pandoc-parse-accepted-with-normalized-ast-drift';
        }

        return 'local-and-pandoc-normalized-ast-equality-observed';
    }

    /**
     * @param array<string, int> $dialectCounts
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        int $targetFileCount,
        int $localParseFailureCount,
        int $controlLeakCount,
        bool $pandocSkipped,
        int $pandocParseFailureCount,
        int $normalizedMatchCount,
        int $normalizedMismatchCount,
        array $dialectCounts,
        array $targetDialects
    ): array {
        $mdocCount = $dialectCounts['mdoc'] ?? 0;
        $mdocTargeted = in_array('mdoc', $targetDialects, true);
        $mdocStatus = 'not-evaluated';
        if ($mdocCount > 0) {
            $mdocStatus = $mdocTargeted
                && $targetFileCount > 0
                && $localParseFailureCount === 0
                && $controlLeakCount === 0
                && ($pandocSkipped || ($pandocParseFailureCount === 0 && $normalizedMismatchCount === 0 && $normalizedMatchCount > 0))
                ? 'covered-by-current-mdoc-audit-lane'
                : 'open';
        }

        return [
            [
                'rank' => 1,
                'id' => 'real-world-man-corpus-local-acceptance',
                'status' => $targetFileCount > 0 && $localParseFailureCount === 0 && $controlLeakCount === 0
                    ? 'covered-by-current-corpus-audit'
                    : 'open',
                'currentEvidence' => "target manual files={$targetFileCount}; local parse failures={$localParseFailureCount}; visible control leaks={$controlLeakCount}",
                'evidenceRequired' => 'Keep local PHP manual reader parse failures and visible roff control-request leaks at zero for the audited corpus.',
            ],
            [
                'rank' => 2,
                'id' => 'pandoc-executable-manual-native-comparison',
                'status' => $pandocSkipped
                    ? 'not-evaluated'
                    : ($pandocParseFailureCount === 0 && $normalizedMismatchCount === 0 && $normalizedMatchCount > 0
                        ? 'covered-by-current-executable-evidence'
                        : 'open'),
                'currentEvidence' => $pandocSkipped
                    ? 'pandoc executable comparison skipped'
                    : "pandoc parse failures={$pandocParseFailureCount}; normalized matches={$normalizedMatchCount}; normalized mismatches={$normalizedMismatchCount}",
                'evidenceRequired' => 'Run local PHP manual-family readers and pandoc native output for the same target dialect against the same corpus, then reduce normalized AST drift to zero for target fixtures before claiming executable parity.',
            ],
            [
                'rank' => 3,
                'id' => 'mdoc-dialect-support',
                'status' => $mdocStatus,
                'currentEvidence' => 'detected mdoc files=' . $mdocCount . '; target dialects=' . implode(',', $targetDialects),
                'evidenceRequired' => 'Keep an mdoc-specific audit lane green before claiming broad manpage support across BSD-style manuals.',
            ],
        ];
    }

    /**
     * @param list<string> $lines
     * @param mixed $examples
     * @return list<string>
     */
    private function appendExamples(array $lines, string $label, mixed $examples, string $field): array
    {
        if (!is_array($examples) || $examples === []) {
            return $lines;
        }

        $lines[] = $label . ':';
        foreach ($examples as $example) {
            if (!is_array($example)) {
                continue;
            }
            $lines[] = '- ' . (string) ($example['file'] ?? 'unknown')
                . ': ' . (string) ($example[$field] ?? 'unknown');
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @param mixed $mismatches
     * @return list<string>
     */
    private function appendMismatchExamples(array $lines, mixed $mismatches): array
    {
        if (!is_array($mismatches) || $mismatches === []) {
            return $lines;
        }

        $lines[] = 'mismatchExamples:';
        foreach ($mismatches as $mismatch) {
            if (!is_array($mismatch)) {
                continue;
            }
            $lines[] = '- ' . (string) ($mismatch['file'] ?? 'unknown')
                . ': ' . (string) ($mismatch['firstDifference'] ?? 'unknown');
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'orderedRemainingGaps:';
        foreach ($gaps as $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '- #%d %s [%s]: %s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $count, int $total): ?float
    {
        return $total === 0 ? null : round(($count / $total) * 100, 2);
    }

    private static function formatPercent(mixed $percent): string
    {
        return is_int($percent) || is_float($percent) ? number_format((float) $percent, 2) . '%' : 'n/a';
    }

    private static function shortJson(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return gettype($value);
        }

        return strlen($json) > 240 ? substr($json, 0, 237) . '...' : $json;
    }
}
