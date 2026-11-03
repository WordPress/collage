<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class UpstreamRunnerDependencies
{
    private const UPSTREAM_COMMIT = '0640c4c9859aa5a3ede082c190fcd5883c24ac83';

    /** @var list<string> */
    private const REQUIRED_FILES = [
        'cabal.project',
        'pandoc.cabal',
        'pandoc-lua-engine/pandoc-lua-engine.cabal',
        'pandoc-server/pandoc-server.cabal',
        'pandoc-cli/pandoc-cli.cabal',
        'test/test-pandoc.hs',
        'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
        'benchmark/benchmark-pandoc.hs',
    ];

    /** @var list<string> */
    private const SOLVER_TARGETS = [
        'test:test-pandoc',
        'test:test-pandoc-lua-engine',
        'benchmark:benchmark-pandoc',
    ];

    /** @var list<string> */
    private const SUPPORT_COMPONENTS = [
        'pandoc-shared-zip-package-core',
        'pandoc-opc-xml-relationships-core',
        'pandoc-xml-html5-dom-core',
        'pandoc-doctemplates-core',
        'pandoc-yaml-metadata-core',
        'pandoc-citation-csl-core',
        'pandoc-bibtex-csl-core',
        'pandoc-docx-openxml-core',
        'pandoc-epub3-package-core',
        'pandoc-odf-open-document-core',
        'pandoc-legacy-doc-cfb-core',
        'pandoc-math-tex-conversion-core',
        'pandoc-syntax-highlighting-core',
        'pandoc-charset-unicode-width-core',
        'pandoc-table-geometry-core',
        'pandoc-archive-compression-streams',
        'pandoc-pdf-engine-handoff-core',
        'pandoc-media-bag-core',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function testSuites(): array
    {
        return [
            'test-pandoc' => [
                'package' => 'pandoc',
                'packageFile' => 'pandoc.cabal',
                'type' => 'exitcode-stdio-1.0',
                'mainIs' => 'test-pandoc.hs',
                'hsSourceDirs' => ['test'],
                'target' => 'test:test-pandoc',
                'runner' => 'Tasty',
                'entryPoint' => [
                    'setsLocaleEncoding' => 'utf-8',
                    'workingDirectory' => 'test',
                    'emulateMode' => 'convertWithOpts noEngine command runner',
                ],
                'groups' => [
                    'command fixtures',
                    'old tests',
                    'shared helpers',
                    'media bag',
                    'XML',
                    'writer groups',
                    'reader groups',
                ],
                'dependencies' => [
                    ['name' => 'pandoc', 'kind' => 'local-library', 'constraint' => null],
                    ['name' => 'Diff', 'constraint' => '>= 0.2 && < 1.1'],
                    ['name' => 'Glob', 'constraint' => '>= 0.7 && < 0.11'],
                    ['name' => 'bytestring', 'constraint' => '>= 0.9 && < 0.13'],
                    ['name' => 'containers', 'constraint' => '>= 0.4.2.1 && < 0.9'],
                    ['name' => 'directory', 'constraint' => '>= 1.2.3 && < 1.4'],
                    ['name' => 'doctemplates', 'constraint' => '>= 0.11 && < 0.12'],
                    ['name' => 'filepath', 'constraint' => '>= 1.1 && < 1.6'],
                    ['name' => 'mtl', 'constraint' => '>= 2.2 && < 2.4'],
                    ['name' => 'pandoc-types', 'constraint' => '>= 1.23.1 && < 1.24'],
                    ['name' => 'process', 'constraint' => '>= 1.2.3 && < 1.7'],
                    ['name' => 'tasty', 'constraint' => '>= 0.11 && < 1.6'],
                    ['name' => 'tasty-golden', 'constraint' => '>= 2.3 && < 2.4'],
                    ['name' => 'tasty-hunit', 'constraint' => '>= 0.9 && < 0.11'],
                    ['name' => 'tasty-quickcheck', 'constraint' => '>= 0.8 && < 0.12'],
                    ['name' => 'text', 'constraint' => '>= 1.1.1.0 && < 2.2'],
                    ['name' => 'temporary', 'constraint' => '>= 1.1 && < 1.4'],
                    ['name' => 'time', 'constraint' => '>= 1.5 && < 1.16'],
                    ['name' => 'xml', 'constraint' => '>= 1.3.12 && < 1.4'],
                    ['name' => 'zip-archive', 'constraint' => '>= 0.4.3 && < 0.5'],
                ],
            ],
            'test-pandoc-lua-engine' => [
                'package' => 'pandoc-lua-engine',
                'packageFile' => 'pandoc-lua-engine/pandoc-lua-engine.cabal',
                'type' => 'exitcode-stdio-1.0',
                'mainIs' => 'test-pandoc-lua-engine.hs',
                'hsSourceDirs' => ['test'],
                'target' => 'test:test-pandoc-lua-engine',
                'runner' => 'Tasty',
                'entryPoint' => [
                    'workingDirectory' => 'pandoc-lua-engine/test',
                ],
                'groups' => [
                    'Lua filters',
                    'Lua modules',
                    'custom writers',
                    'custom readers',
                ],
                'dependencies' => [
                    ['name' => 'pandoc-lua-engine', 'kind' => 'local-library', 'constraint' => null],
                    ['name' => 'bytestring', 'constraint' => null],
                    ['name' => 'directory', 'constraint' => null],
                    ['name' => 'data-default', 'constraint' => null],
                    ['name' => 'exceptions', 'constraint' => '>= 0.8 && < 0.11'],
                    ['name' => 'filepath', 'constraint' => null],
                    ['name' => 'hslua', 'constraint' => '>= 2.5 && < 2.6'],
                    ['name' => 'pandoc', 'constraint' => null],
                    ['name' => 'pandoc-types', 'constraint' => '>= 1.22 && < 1.24'],
                    ['name' => 'tasty', 'constraint' => null],
                    ['name' => 'tasty-golden', 'constraint' => null],
                    ['name' => 'tasty-hunit', 'constraint' => null],
                    ['name' => 'tasty-lua', 'constraint' => '>= 1.1 && < 1.2'],
                    ['name' => 'text', 'constraint' => '>= 1.1.1 && < 2.2'],
                ],
                'libraryExtraDependencies' => [
                    'hslua-module-doclayout',
                    'hslua-module-path',
                    'hslua-module-system',
                    'hslua-module-text',
                    'hslua-module-version',
                    'hslua-module-zip',
                    'lpeg',
                    'pandoc-lua-marshal',
                    'hslua-repl (optional repl flag)',
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     packages:list<string>,
     *     pandocFlags:list<string>,
     *     constraints:array<string, string>,
     *     sourceRepositoryPins:array<string, array{commit:string, source:string}>
     * }
     */
    public function cabalProject(): array
    {
        return [
            'packages' => ['.', 'pandoc-lua-engine', 'pandoc-server', 'pandoc-cli'],
            'pandocFlags' => ['embed_data_files', 'http'],
            'constraints' => [
                'skylighting-format-blaze-html' => 'project constraint from cabal.project',
                'skylighting-format-context' => 'project constraint from cabal.project',
                'auto-update' => 'project constraint from cabal.project',
                'crypton' => 'project constraint from cabal.project',
            ],
            'sourceRepositoryPins' => $this->sourceRepositoryPins(),
        ];
    }

    /**
     * @return array<string, array{commit:string, source:string}>
     */
    public function sourceRepositoryPins(): array
    {
        return [
            'doclayout' => [
                'commit' => 'ef7f18308a61787244a80885d907fcd2c16604d4',
                'source' => 'cabal.project source-repository-package',
            ],
            'typst-symbols' => [
                'commit' => '6e97668c9f2ffea09f3187c34b7641038370fd21',
                'source' => 'cabal.project source-repository-package',
            ],
            'typst-hs' => [
                'commit' => '19e835d40663a92df5bed4e8a0fca5465cacdd6b',
                'source' => 'cabal.project source-repository-package',
            ],
            'texmath' => [
                'commit' => '0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
                'source' => 'cabal.project source-repository-package',
            ],
            'citeproc' => [
                'commit' => '1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd',
                'source' => 'cabal.project source-repository-package',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function requiredFiles(): array
    {
        return self::REQUIRED_FILES;
    }

    /**
     * @return list<string>
     */
    public function supportComponents(): array
    {
        return self::SUPPORT_COMPONENTS;
    }

    /**
     * @param list<string>|array<string, bool> $presentFiles
     * @param array<string, string|null> $toolVersions
     * @return array{
     *     kind:string,
     *     upstreamCommit:string,
     *     willExecute:bool,
     *     status:string,
     *     activationReady:bool,
     *     requiredFiles:list<string>,
     *     presentFiles:list<string>,
     *     missingFiles:list<string>,
     *     tools:array<string, string|null>,
     *     solverTargets:list<string>,
     *     testSuites:array<string, array<string, mixed>>,
     *     cabalProject:array<string, mixed>,
     *     sourceRepositoryPins:array<string, array{commit:string, source:string}>,
     *     supportComponents:list<string>,
     *     dependencyBacklogDecision:string,
     *     activationGate:list<string>,
     *     diagnostics:list<string>
     * }
     */
    public function evaluateLocalGate(array $presentFiles, array $toolVersions = []): array
    {
        $present = $this->normalizePresentFiles($presentFiles);
        $missing = [];
        $presentRequiredFiles = [];
        foreach (self::REQUIRED_FILES as $path) {
            if (!isset($present[$path])) {
                $missing[] = $path;
                continue;
            }

            $presentRequiredFiles[] = $path;
        }

        $activationReady = $missing === [];
        $diagnostics = [
            'pandoc-runner-not-executed',
            'cabal-build-not-run',
            'haskell-test-binaries-not-run',
        ];

        foreach ($this->normalizeToolVersions($toolVersions) as $tool => $version) {
            $diagnostics[] = $version === null
                ? $tool . '-not-on-path'
                : $tool . '-available:' . $version;
        }

        if ($activationReady) {
            $diagnostics[] = 'non-mutating-plan-ready';
        } else {
            $diagnostics[] = 'missing-required-upstream-files:' . count($missing);
        }

        return [
            'kind' => 'pandoc-upstream-runner-dependencies',
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'willExecute' => false,
            'status' => $activationReady ? 'plan-ready' : 'blocked-missing-upstream-checkout',
            'activationReady' => $activationReady,
            'requiredFiles' => self::REQUIRED_FILES,
            'presentFiles' => $this->orderedPresentFiles($present, $presentRequiredFiles),
            'missingFiles' => $missing,
            'tools' => $this->normalizeToolVersions($toolVersions),
            'solverTargets' => self::SOLVER_TARGETS,
            'testSuites' => $this->testSuites(),
            'cabalProject' => $this->cabalProject(),
            'sourceRepositoryPins' => $this->sourceRepositoryPins(),
            'supportComponents' => self::SUPPORT_COMPONENTS,
            'dependencyBacklogDecision' => 'no-new-native-support-component',
            'activationGate' => $this->activationGate($activationReady),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     upstreamCommit:string,
     *     requiredFileCount:int,
     *     solverTargetCount:int,
     *     testSuiteCount:int,
     *     testPandocDependencyCount:int,
     *     luaEngineTestDependencyCount:int,
     *     projectPinCount:int,
     *     supportComponentCount:int
     * }
     */
    public function summary(): array
    {
        $suites = $this->testSuites();

        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'requiredFileCount' => count(self::REQUIRED_FILES),
            'solverTargetCount' => count(self::SOLVER_TARGETS),
            'testSuiteCount' => count($suites),
            'testPandocDependencyCount' => count($suites['test-pandoc']['dependencies']),
            'luaEngineTestDependencyCount' => count($suites['test-pandoc-lua-engine']['dependencies']),
            'projectPinCount' => count($this->sourceRepositoryPins()),
            'supportComponentCount' => count(self::SUPPORT_COMPONENTS),
        ];
    }

    /**
     * @param list<string>|array<string, bool> $presentFiles
     * @return array<string, bool>
     */
    private function normalizePresentFiles(array $presentFiles): array
    {
        $present = [];
        foreach ($presentFiles as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && $value !== '') {
                    $present[$this->normalizePath($value)] = true;
                }
                continue;
            }

            if ($value === true) {
                $present[$this->normalizePath((string) $key)] = true;
            }
        }

        ksort($present);

        return $present;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = preg_replace('#^\./#', '', $path) ?? $path;

        return ltrim($path, '/');
    }

    /**
     * @param array<string, bool> $present
     * @param list<string> $presentRequiredFiles
     * @return list<string>
     */
    private function orderedPresentFiles(array $present, array $presentRequiredFiles): array
    {
        $ordered = $presentRequiredFiles;
        $required = array_fill_keys(self::REQUIRED_FILES, true);
        foreach (array_keys($present) as $path) {
            if (!isset($required[$path])) {
                $ordered[] = $path;
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, string|null> $toolVersions
     * @return array<string, string|null>
     */
    private function normalizeToolVersions(array $toolVersions): array
    {
        $tools = [];
        foreach ($toolVersions as $tool => $version) {
            $tool = strtolower(trim((string) $tool));
            if ($tool === '') {
                continue;
            }

            $tools[$tool] = is_string($version) && trim($version) !== '' ? trim($version) : null;
        }

        ksort($tools);

        return $tools;
    }

    /**
     * @return list<string>
     */
    private function activationGate(bool $activationReady): array
    {
        if (!$activationReady) {
            return [
                'hydrate Pandoc upstream checkout at ' . self::UPSTREAM_COMMIT,
                'verify cabal.project, pandoc.cabal, pandoc-lua-engine/pandoc-lua-engine.cabal, and both test entry points are present',
                'verify pandoc-server, pandoc-cli, and benchmark package entry files are present before dependency planning',
                'record a non-mutating Cabal solver/build plan for test:test-pandoc, test:test-pandoc-lua-engine, and benchmark:benchmark-pandoc',
                'resolve cabal.project Git source-repository pins for doclayout, typst-symbols, typst-hs, texmath, and citeproc',
                'only then attempt bounded Haskell runner execution in a separate slice',
            ];
        }

        return [
            'record non-mutating Cabal solver/build plan for test:test-pandoc, test:test-pandoc-lua-engine, and benchmark:benchmark-pandoc',
            'resolve project-pinned Git source-repository packages before any runner build',
            'keep runner execution deferred to a separately authorized bounded slice',
        ];
    }
}
