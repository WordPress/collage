<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocUpstreamRunnerDependencyAudit
{
    public const STATUS_READY_FOR_DEPENDENCY_PLAN = 'ready_for_dependency_plan';
    public const STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE = 'blocked_missing_upstream_source';
    public const STATUS_BLOCKED_MISSING_HASKELL_TOOLS = 'blocked_missing_haskell_tools';

    /** @var list<string> */
    private const REQUIRED_SOURCE_FILES = [
        'cabal.project',
        'pandoc.cabal',
        'pandoc-lua-engine/pandoc-lua-engine.cabal',
        'pandoc-server/pandoc-server.cabal',
        'pandoc-cli/pandoc-cli.cabal',
        'test/test-pandoc.hs',
        'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
    ];

    /** @var list<string> */
    private const STABLE_PLAN_FILES = [
        'cabal.project',
        'cabal.project.freeze',
    ];

    /** @var list<string> */
    private const REQUIRED_TOOLS = [
        'ghc',
        'cabal',
    ];

    /** @var list<array{root:string, complete:bool, present:list<string>, missing:list<string>, stablePlanFiles:list<string>}> */
    private readonly array $candidates;

    /** @var array<string, string|null> */
    private readonly array $toolVersions;

    /**
     * @param list<string> $candidateRoots
     * @param array<string, string|null> $toolVersions
     */
    private function __construct(array $candidateRoots, array $toolVersions)
    {
        $this->toolVersions = $toolVersions;
        $this->candidates = array_values(array_map(
            static fn (string $root): array => self::auditCandidateRoot($root),
            self::uniqueExistingRoots($candidateRoots)
        ));
    }

    /**
     * @param array<string, string|null> $toolVersions
     */
    public static function fromCandidateRoots(array $candidateRoots, array $toolVersions = []): self
    {
        return new self($candidateRoots, $toolVersions);
    }

    /**
     * @param array<string, string|null> $toolVersions
     */
    public static function fromLaneAndCacheRoots(string $laneRoot, string $upstreamCacheRoot, array $toolVersions = []): self
    {
        $roots = [$laneRoot, $upstreamCacheRoot, rtrim($upstreamCacheRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pandoc'];

        if (is_dir($upstreamCacheRoot)) {
            foreach (new \DirectoryIterator($upstreamCacheRoot) as $entry) {
                if ($entry->isDot() || !$entry->isDir()) {
                    continue;
                }

                if (str_contains(strtolower($entry->getFilename()), 'pandoc')) {
                    $roots[] = $entry->getPathname();
                }
            }
        }

        return new self($roots, $toolVersions);
    }

    /**
     * @return list<array{root:string, complete:bool, present:list<string>, missing:list<string>, stablePlanFiles:list<string>}>
     */
    public function candidates(): array
    {
        return $this->candidates;
    }

    public function status(): string
    {
        if ($this->completeSourceRoot() === null) {
            return self::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE;
        }

        if ($this->missingRequiredTools() !== []) {
            return self::STATUS_BLOCKED_MISSING_HASKELL_TOOLS;
        }

        return self::STATUS_READY_FOR_DEPENDENCY_PLAN;
    }

    public function hasHydratedCheckout(): bool
    {
        return $this->completeSourceRoot() !== null;
    }

    public function completeSourceRoot(): ?string
    {
        foreach ($this->candidates as $candidate) {
            if ($candidate['complete']) {
                return $candidate['root'];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function missingSourceFiles(): array
    {
        $completeRoot = $this->completeSourceRoot();
        if ($completeRoot !== null) {
            return [];
        }

        $present = [];
        foreach ($this->candidates as $candidate) {
            foreach ($candidate['present'] as $path) {
                $present[$path] = true;
            }
        }

        return array_values(array_filter(
            self::REQUIRED_SOURCE_FILES,
            static fn (string $path): bool => !isset($present[$path])
        ));
    }

    /**
     * @return list<string>
     */
    public function stablePlanFiles(): array
    {
        foreach ($this->candidates as $candidate) {
            if ($candidate['complete']) {
                return $candidate['stablePlanFiles'];
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function missingRequiredTools(): array
    {
        $missing = [];
        foreach (self::REQUIRED_TOOLS as $tool) {
            if (($this->toolVersions[$tool] ?? null) === null || $this->toolVersions[$tool] === '') {
                $missing[] = $tool;
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    public function activationGate(): array
    {
        if ($this->completeSourceRoot() === null) {
            return [
                'hydrate Pandoc upstream checkout at the manifest commit',
                'restore cabal.project, Pandoc package manifests, and both Tasty test entrypoints',
                'record ghc and cabal versions without running the Haskell runner',
            ];
        }

        if ($this->missingRequiredTools() !== []) {
            return [
                'install or expose required Haskell tools: ' . implode(', ', $this->missingRequiredTools()),
                'record a non-mutating Cabal dependency plan before any Haskell test executable build',
            ];
        }

        $gate = [
            'record a non-mutating Cabal dependency plan for test-pandoc and test-pandoc-lua-engine',
        ];

        $missingStablePlanFiles = array_values(array_diff(self::STABLE_PLAN_FILES, $this->stablePlanFiles()));
        if ($missingStablePlanFiles !== []) {
            $gate[] = 'capture absent stable Cabal plan files as an unpinned-plan risk: ' . implode(', ', $missingStablePlanFiles);
        }

        $gate[] = 'run any bounded Haskell executable build only in a separate explicitly authorized runner slice';

        return $gate;
    }

    public function summary(): string
    {
        return match ($this->status()) {
            self::STATUS_READY_FOR_DEPENDENCY_PLAN => 'Pandoc upstream source files and required Haskell tools are present; next step is a non-mutating Cabal dependency plan, not runner execution.',
            self::STATUS_BLOCKED_MISSING_HASKELL_TOOLS => 'Pandoc upstream source files are present, but required Haskell tools are missing: ' . implode(', ', $this->missingRequiredTools()) . '.',
            default => 'Pandoc upstream runner dependency closure is blocked because no single local checkout contains the required Cabal files and Tasty test entrypoints.',
        };
    }

    /**
     * @return list<string>
     */
    public static function requiredSourceFiles(): array
    {
        return self::REQUIRED_SOURCE_FILES;
    }

    /**
     * @return list<string>
     */
    private static function uniqueExistingRoots(array $roots): array
    {
        $unique = [];
        foreach ($roots as $root) {
            if (!is_string($root) || $root === '') {
                continue;
            }

            $normalized = rtrim($root, DIRECTORY_SEPARATOR);
            if ($normalized === '' || !is_dir($normalized)) {
                continue;
            }

            $real = realpath($normalized);
            if ($real === false) {
                continue;
            }

            $unique[$real] = true;
        }

        return array_keys($unique);
    }

    /**
     * @return array{root:string, complete:bool, present:list<string>, missing:list<string>, stablePlanFiles:list<string>}
     */
    private static function auditCandidateRoot(string $root): array
    {
        $present = [];
        $missing = [];

        foreach (self::REQUIRED_SOURCE_FILES as $relativePath) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (self::hasNonEmptyFile($path)) {
                $present[] = $relativePath;
            } else {
                $missing[] = $relativePath;
            }
        }

        $stablePlanFiles = [];
        foreach (self::STABLE_PLAN_FILES as $relativePath) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (self::hasNonEmptyFile($path)) {
                $stablePlanFiles[] = $relativePath;
            }
        }

        return [
            'root' => $root,
            'complete' => $missing === [],
            'present' => $present,
            'missing' => $missing,
            'stablePlanFiles' => $stablePlanFiles,
        ];
    }

    private static function hasNonEmptyFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $size = filesize($path);

        return $size !== false && $size > 0;
    }
}
