<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class SyntaxHighlighter
{
    private const TOKEN_CLASSES = [
        'attribute' => 'ot',
        'comment' => 'co',
        'constant' => 'cn',
        'datatype' => 'dt',
        'function' => 'fu',
        'information' => 'in',
        'keyword' => 'kw',
        'number' => 'dv',
        'operator' => 'op',
        'preprocessor' => 'pp',
        'region' => 're',
        'string' => 'st',
        'variable' => 'va',
        'warning' => 'al',
    ];

    private const TOKEN_TITLES = [
        'attribute' => 'OtherTok',
        'comment' => 'CommentTok',
        'constant' => 'ConstantTok',
        'datatype' => 'DataTypeTok',
        'function' => 'FunctionTok',
        'information' => 'InformationTok',
        'keyword' => 'KeywordTok',
        'number' => 'DecValTok',
        'operator' => 'OperatorTok',
        'preprocessor' => 'PreprocessorTok',
        'region' => 'RegionMarkerTok',
        'string' => 'StringTok',
        'variable' => 'VariableTok',
        'warning' => 'AlertTok',
    ];

    private const LANGUAGE_ALIASES = [
        'agda' => 'agda',
        'agda2' => 'agda',
        'agda-lang' => 'agda',
        'agda-source' => 'agda',
        'apache' => 'apache',
        'apache-conf' => 'apache',
        'apache-config' => 'apache',
        'apache2' => 'apache',
        'apacheconf' => 'apache',
        'adoc' => 'asciidoc',
        'asc' => 'asciidoc',
        'asciidoc' => 'asciidoc',
        'asciidoctor' => 'asciidoc',
        'awk' => 'awk',
        'awk-script' => 'awk',
        'bash' => 'bash',
        'bat' => 'batch',
        'batch' => 'batch',
        'batchfile' => 'batch',
        'bib' => 'bibtex',
        'biblatex' => 'bibtex',
        'bibtex' => 'bibtex',
        'c' => 'c',
        'cargo-lock' => 'toml',
        'c#' => 'csharp',
        'cc' => 'cpp',
        'cpp' => 'cpp',
        'c++' => 'cpp',
        'cr' => 'crystal',
        'crystal' => 'crystal',
        'crystal-lang' => 'crystal',
        'crystal-source' => 'crystal',
        'cs' => 'csharp',
        'csharp' => 'csharp',
        'csx' => 'csharp',
        'cxx' => 'cpp',
        'cl' => 'commonlisp',
        'clj' => 'clojure',
        'cljc' => 'clojure',
        'cljs' => 'clojure',
        'clojure' => 'clojure',
        'cmake' => 'cmake',
        'cmake-in' => 'cmake',
        'cmakelists' => 'cmake',
        'cmakelists-txt' => 'cmake',
        'cmd' => 'batch',
        'cmd-exe' => 'batch',
        'comma-separated-values' => 'csv',
        'console' => 'bash',
        'coq' => 'coq',
        'coq-script' => 'coq',
        'coqdoc' => 'coq',
        'containerfile' => 'dockerfile',
        'css' => 'css',
        'csv' => 'csv',
        'd' => 'd',
        'dart' => 'dart',
        'dartlang' => 'dart',
        'dlang' => 'd',
        'd-language' => 'd',
        'd-source' => 'd',
        'diff' => 'diff',
        'docker' => 'dockerfile',
        'dockerfile' => 'dockerfile',
        'dosbatch' => 'batch',
        'dot' => 'dot',
        'edn' => 'clojure',
        'elm' => 'elm',
        'elm-module' => 'elm',
        'elm-source' => 'elm',
        'elixir' => 'elixir',
        'erl' => 'erlang',
        'erlang' => 'erlang',
        'erlang-header' => 'erlang',
        'ex' => 'elixir',
        'exs' => 'elixir',
        'fennel' => 'fennel',
        'fennel-lang' => 'fennel',
        'f' => 'fortran',
        'f03' => 'fortran',
        'f08' => 'fortran',
        'f18' => 'fortran',
        'f77' => 'fortran',
        'f90' => 'fortran',
        'f95' => 'fortran',
        'fnl' => 'fennel',
        'flutter' => 'dart',
        'fish' => 'fish',
        'fish-shell' => 'fish',
        'for' => 'fortran',
        'fortran' => 'fortran',
        'fortran-fixed' => 'fortran',
        'fortran-free' => 'fortran',
        'f#' => 'fsharp',
        'f-sharp' => 'fsharp',
        'fs' => 'fsharp',
        'fsi' => 'fsharp',
        'fsharp' => 'fsharp',
        'fsharp-source' => 'fsharp',
        'fsx' => 'fsharp',
        'fsscript' => 'fsharp',
        'ftn' => 'fortran',
        'git-diff' => 'diff',
        'gawk' => 'awk',
        'gnu-sed' => 'sed',
        'graphviz' => 'dot',
        'gsed' => 'sed',
        'gv' => 'dot',
        'h' => 'c',
        'hcl' => 'hcl',
        'hcl2' => 'hcl',
        'hh' => 'cpp',
        'hpp' => 'cpp',
        'hxx' => 'cpp',
        'html' => 'html',
        'handlebars' => 'mustache',
        'hbs' => 'mustache',
        'hogan' => 'mustache',
        'hrl' => 'erlang',
        'hulk' => 'mustache',
        'html5' => 'html',
        'html-handlebars' => 'mustache',
        'html-mst' => 'mustache',
        'html-mu' => 'mustache',
        'html-rac' => 'mustache',
        'html-vue' => 'vue',
        'htaccess' => 'apache',
        'haskell' => 'haskell',
        'hs' => 'haskell',
        'httpd' => 'apache',
        'httpd-conf' => 'apache',
        'idr' => 'idris',
        'idris' => 'idris',
        'idris-lang' => 'idris',
        'idris-source' => 'idris',
        'idris2' => 'idris',
        'atom' => 'xml',
        'cjs' => 'javascript',
        'cfg' => 'ini',
        'editorconfig' => 'ini',
        'gitconfig' => 'ini',
        'gitmodules' => 'ini',
        'ini' => 'ini',
        'ecmascript' => 'javascript',
        'es6' => 'javascript',
        'javascript' => 'javascript',
        'javascript-react' => 'jsx',
        'java' => 'java',
        'jenkinsfile' => 'groovy',
        'js' => 'javascript',
        'jsx' => 'jsx',
        'json' => 'json',
        'json-comments' => 'jsonc',
        'json-with-comments' => 'jsonc',
        'json5' => 'jsonc',
        'jsonc' => 'jsonc',
        'just' => 'just',
        'just-file' => 'just',
        'justfile' => 'just',
        'jl' => 'julia',
        'julia' => 'julia',
        'julia-repl' => 'julia',
        'julia-source' => 'julia',
        'kcfgc' => 'ini',
        'kotlinscript' => 'kotlin',
        'kotlin' => 'kotlin',
        'kotlin-script' => 'kotlin',
        'kt' => 'kotlin',
        'kts' => 'kotlin',
        'latex' => 'tex',
        'lhs' => 'haskell',
        'html-liquid' => 'liquid',
        'lagda' => 'agda',
        'lagda-md' => 'agda',
        'lagda-tex' => 'agda',
        'less' => 'less',
        'less-css' => 'less',
        'lesscss' => 'less',
        'literate-agda' => 'agda',
        'literate-haskell' => 'haskell',
        'literatehaskell' => 'haskell',
        'liquid' => 'liquid',
        'liquid-html' => 'liquid',
        'lisp' => 'commonlisp',
        'lsp' => 'commonlisp',
        'lua' => 'lua',
        'pandoc-lua' => 'lua',
        'commonmark' => 'markdown',
        'common-lisp' => 'commonlisp',
        'commonlisp' => 'commonlisp',
        'gfm' => 'markdown',
        'go' => 'go',
        'golang' => 'go',
        'gradle' => 'groovy',
        'gradle-groovy' => 'groovy',
        'groovy' => 'groovy',
        'groovy-script' => 'groovy',
        'groovy-source' => 'groovy',
        'gvy' => 'groovy',
        'gql' => 'graphql',
        'graphql' => 'graphql',
        'graphql-query' => 'graphql',
        'graphql-schema' => 'graphql',
        'graphqls' => 'graphql',
        'gallina' => 'coq',
        'markdown' => 'markdown',
        'gnu-octave' => 'matlab',
        'mariadb' => 'sql',
        'm' => 'matlab',
        'matlab' => 'matlab',
        'matlab-octave' => 'matlab',
        'matlab-source' => 'matlab',
        'mawk' => 'awk',
        'md' => 'markdown',
        'm-file' => 'matlab',
        'meson' => 'meson',
        'meson-build' => 'meson',
        'mmd' => 'markdown',
        'multimarkdown' => 'markdown',
        'mustache' => 'mustache',
        'mysql' => 'sql',
        'nawk' => 'awk',
        'nix' => 'nix',
        'nix-expr' => 'nix',
        'nix-shell' => 'nix',
        'pandoc' => 'markdown',
        'pandoc-markdown' => 'markdown',
        'patch' => 'diff',
        'pls' => 'ini',
        'gnumakefile' => 'makefile',
        'make' => 'makefile',
        'makefile' => 'makefile',
        'mk' => 'makefile',
        'mjs' => 'javascript',
        'mermaid' => 'mermaid',
        'mermaid-js' => 'mermaid',
        'mermaidjs' => 'mermaid',
        'node' => 'javascript',
        'nodejs' => 'javascript',
        'nginx' => 'nginx',
        'nginx-conf' => 'nginx',
        'nginx-config' => 'nginx',
        'nginxconf' => 'nginx',
        'nim' => 'nim',
        'nim-lang' => 'nim',
        'nim-source' => 'nim',
        'nimrod' => 'nim',
        'nims' => 'nim',
        'nimscript' => 'nim',
        'ml' => 'ocaml',
        'mli' => 'ocaml',
        'mm' => 'objectivec',
        'obj-c' => 'objectivec',
        'objc' => 'objectivec',
        'ocaml' => 'ocaml',
        'ocaml-interface' => 'ocaml',
        'octave' => 'matlab',
        'objective-c' => 'objectivec',
        'objective-c++' => 'objectivec',
        'objectivec' => 'objectivec',
        'objectivecpp' => 'objectivec',
        'delphi' => 'pascal',
        'fpc' => 'pascal',
        'freepascal' => 'pascal',
        'object-pascal' => 'pascal',
        'objectpascal' => 'pascal',
        'pas' => 'pascal',
        'pascal' => 'pascal',
        'pp' => 'pascal',
        'perl' => 'perl',
        'perl6' => 'raku',
        'pl' => 'perl',
        'pl6' => 'raku',
        'pgsql' => 'sql',
        'plpgsql' => 'sql',
        'pm' => 'perl',
        'pm6' => 'raku',
        'posh' => 'powershell',
        'powershell' => 'powershell',
        'p6' => 'raku',
        'php' => 'php',
        'pure-script' => 'purescript',
        'purescript' => 'purescript',
        'purescript-source' => 'purescript',
        'purs' => 'purescript',
        'proto' => 'protobuf',
        'proto2' => 'protobuf',
        'proto3' => 'protobuf',
        'protobuf' => 'protobuf',
        'protobuf3' => 'protobuf',
        'protocol-buffer' => 'protobuf',
        'protocol-buffers' => 'protobuf',
        'postgres' => 'sql',
        'postgresql' => 'sql',
        'ps1' => 'powershell',
        'psd1' => 'powershell',
        'psm1' => 'powershell',
        'pwsh' => 'powershell',
        'py' => 'python',
        'py3' => 'python',
        'python' => 'python',
        'python3' => 'python',
        'q' => 'r',
        'r' => 'r',
        'ractive' => 'mustache',
        'reason' => 'ocaml',
        'reasonml' => 'ocaml',
        'rocq' => 'coq',
        'rocq-prover' => 'coq',
        'rdf' => 'xml',
        'rss' => 'xml',
        'r-script' => 'r',
        'rscript' => 'r',
        'raku' => 'raku',
        'rakudoc' => 'raku',
        'rakumod' => 'raku',
        'rakutest' => 'raku',
        'rake' => 'ruby',
        'rb' => 'ruby',
        'rest' => 'rst',
        'restructured-text' => 'rst',
        'restructuredtext' => 'rst',
        'racket' => 'scheme',
        'rkt' => 'scheme',
        'rktl' => 'scheme',
        'ruby' => 'ruby',
        'rst' => 'rst',
        'sass' => 'sass',
        'sbt' => 'scala',
        'scala' => 'scala',
        'scala-sbt' => 'scala',
        'scheme' => 'scheme',
        'scm' => 'scheme',
        'scss' => 'scss',
        'sed' => 'sed',
        'rs' => 'rust',
        'rust' => 'rust',
        's' => 'r',
        'sh' => 'bash',
        'shell' => 'bash',
        'bash-session' => 'shellsession',
        'console-session' => 'shellsession',
        'shopify' => 'liquid',
        'stream-editor' => 'sed',
        'shell-session' => 'shellsession',
        'shellsession' => 'shellsession',
        'sh-session' => 'shellsession',
        'shopify-liquid' => 'liquid',
        'sql' => 'sql',
        'sqlite' => 'sql',
        'sqlite3' => 'sql',
        'svg' => 'xml',
        'swift' => 'swift',
        'swift-source' => 'swift',
        'swiftui' => 'swift',
        'expect' => 'tcl',
        'terraform' => 'hcl',
        'tcl' => 'tcl',
        'tcl/tk' => 'tcl',
        'tclsh' => 'tcl',
        'tcl-tk' => 'tcl',
        'tcltk' => 'tcl',
        'tex' => 'tex',
        'tf' => 'hcl',
        'tfvars' => 'hcl',
        'tk' => 'tcl',
        'tab-separated-values' => 'tsv',
        'toml' => 'toml',
        'tsv' => 'tsv',
        'ts' => 'typescript',
        'tsx' => 'tsx',
        'typ' => 'typst',
        'typst' => 'typst',
        'typst-source' => 'typst',
        'html+twig' => 'twig',
        'html-twig' => 'twig',
        'timber' => 'twig',
        'twig' => 'twig',
        'twig-html' => 'twig',
        'typescript' => 'typescript',
        'typescript-react' => 'tsx',
        'typescriptreact' => 'tsx',
        'udiff' => 'diff',
        'unified-diff' => 'diff',
        'v' => 'v',
        'v-language' => 'v',
        'v-source' => 'v',
        'vlang' => 'v',
        'vue' => 'vue',
        'vue-component' => 'vue',
        'vue-sfc' => 'vue',
        'vuejs' => 'vue',
        'vim' => 'vim',
        'vim-script' => 'vim',
        'viml' => 'vim',
        'vimscript' => 'vim',
        'xhtml' => 'html',
        'xml' => 'xml',
        'xsd' => 'xml',
        'xsl' => 'xslt',
        'xslt' => 'xslt',
        'yaml' => 'yaml',
        'yml' => 'yaml',
        'z-shell' => 'bash',
        'zsh' => 'bash',
        'zsh-script' => 'bash',
        'zshrc' => 'bash',
    ];

    private const SUPPORTED_STYLES = [
        'breezedark',
        'espresso',
        'haddock',
        'kate',
        'monochrome',
        'pygments',
        'tango',
        'zenburn',
    ];

    private const TOKEN_STYLE_ALIASES = [
        'alerttok' => 'warning',
        'annotationtok' => 'attribute',
        'attributetok' => 'attribute',
        'basentok' => 'number',
        'builtintok' => 'function',
        'chartok' => 'constant',
        'commenttok' => 'comment',
        'commentvartok' => 'comment',
        'constanttok' => 'constant',
        'controlflowtok' => 'keyword',
        'datatypetok' => 'datatype',
        'decvaltok' => 'number',
        'documentationtok' => 'comment',
        'errortok' => 'warning',
        'extensiontok' => 'preprocessor',
        'floattok' => 'number',
        'functiontok' => 'function',
        'importtok' => 'preprocessor',
        'informationtok' => 'information',
        'keywordtok' => 'keyword',
        'operatortok' => 'operator',
        'othertok' => 'attribute',
        'preprocessortok' => 'preprocessor',
        'regionmarkertok' => 'region',
        'specialchartok' => 'constant',
        'specialstringtok' => 'string',
        'stringtok' => 'string',
        'variablename' => 'variable',
        'variabletok' => 'variable',
        'verbatimstringtok' => 'string',
        'warningtok' => 'warning',
    ];

    /**
     * @return array{
     *   language:string,
     *   requestedLanguage:string,
     *   style:string,
     *   tokens:list<array{type:string, text:string, class:string}>,
     *   html:string,
     *   css:string,
     *   diagnostics:list<array{code:string, message:string}>,
     *   lineNumbering:array{enabled:bool, anchors:bool, start:int, lineIdPrefix:string},
     *   tokenTitles:bool,
     *   highlightLines:list<int>
     * }
     */
    public function highlightCodeBlock(AstNode $codeBlock, string $style = 'pygments', array $options = []): array
    {
        if ($codeBlock->type !== 'code_block') {
            throw new \InvalidArgumentException('Syntax highlighter expects a code_block node');
        }

        $requested = self::languageFromCodeBlock($codeBlock);
        $classes = $codeBlock->attr('classes', []);
        $attributes = $codeBlock->attr('attributes', []);

        return $this->highlight((string) $codeBlock->attr('text', ''), $requested, $style, array_replace([
            'id' => (string) $codeBlock->attr('id', ''),
            'classes' => is_array($classes) ? $classes : [],
            'attributes' => is_array($attributes) ? $attributes : [],
        ], $options));
    }

    /**
     * @param array{
     *   id?: string,
     *   classes?: array<int, mixed>,
     *   attributes?: array<string, mixed>,
     *   themeJson?: string,
     *   tokenTitles?: bool|string|int
     * } $options
     * @return array{
     *   language:string,
     *   requestedLanguage:string,
     *   style:string,
     *   tokens:list<array{type:string, text:string, class:string}>,
     *   html:string,
     *   css:string,
     *   diagnostics:list<array{code:string, message:string}>,
     *   lineNumbering:array{enabled:bool, anchors:bool, start:int, lineIdPrefix:string},
     *   tokenTitles:bool,
     *   highlightLines:list<int>
     * }
     */
    public function highlight(string $code, string $language = '', string $style = 'pygments', array $options = []): array
    {
        $requested = trim($language);
        $canonicalLanguage = self::normalizeLanguage($requested) ?? '';
        $canonicalStyle = self::normalizeStyle($style);
        $lineOptions = self::lineNumberingOptions($options);
        $theme = null;
        $diagnostics = [];

        if ($requested !== '' && $canonicalLanguage === '') {
            $diagnostics[] = [
                'code' => 'unsupported-language',
                'message' => "No bounded native syntax definition is available for '{$requested}'",
            ];
        }

        if (isset($options['themeJson']) && is_string($options['themeJson']) && trim($options['themeJson']) !== '') {
            $theme = self::parseThemeJson($options['themeJson'], $canonicalStyle);
            $canonicalStyle = $theme['name'];
            $diagnostics = array_merge($diagnostics, $theme['diagnostics']);
        }

        $tokens = $canonicalLanguage === ''
            ? [['type' => 'text', 'text' => $code, 'class' => '']]
            : $this->tokenize($code, $canonicalLanguage);

        return [
            'language' => $canonicalLanguage,
            'requestedLanguage' => $requested,
            'style' => $canonicalStyle,
            'tokens' => $tokens,
            'html' => self::renderHighlightedHtml($tokens, $canonicalLanguage, $lineOptions),
            'css' => $theme === null ? self::stylesheet($canonicalStyle) : self::stylesheetFromParsedTheme($theme),
            'diagnostics' => $diagnostics,
            'lineNumbering' => [
                'enabled' => $lineOptions['numberLines'],
                'anchors' => $lineOptions['lineAnchors'],
                'start' => $lineOptions['startNumber'],
                'lineIdPrefix' => $lineOptions['lineIdPrefix'],
            ],
            'tokenTitles' => $lineOptions['tokenTitles'],
            'highlightLines' => $lineOptions['highlightLines'],
        ];
    }

    public static function languageFromCodeBlock(AstNode $codeBlock): string
    {
        $classes = $codeBlock->attr('classes', []);
        if (is_array($classes)) {
            foreach ($classes as $class) {
                $class = trim((string) $class);
                if ($class === '' || self::isStructuralClass($class)) {
                    continue;
                }

                return self::stripLanguagePrefix($class);
            }
        }

        $attributes = $codeBlock->attr('attributes', []);
        if (is_array($attributes)) {
            foreach (['language', 'data-language', 'lang'] as $name) {
                if (isset($attributes[$name]) && trim((string) $attributes[$name]) !== '') {
                    return self::stripLanguagePrefix((string) $attributes[$name]);
                }
            }
        }

        $info = trim((string) $codeBlock->attr('info', ''));
        if ($info !== '' && !str_starts_with($info, '{')) {
            $tokens = preg_split('/\s+/', $info, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens !== []) {
                return self::stripLanguagePrefix((string) $tokens[0]);
            }
        }

        return '';
    }

    public static function normalizeLanguage(string $language): ?string
    {
        $language = strtolower(trim(self::stripLanguagePrefix($language)));
        $language = str_replace(['_', '.'], '-', $language);
        if ($language === '' || self::isStructuralClass($language)) {
            return null;
        }

        return self::LANGUAGE_ALIASES[$language] ?? null;
    }

    public static function normalizeStyle(string $style): string
    {
        $style = strtolower(trim($style));
        $style = str_replace(['_', ' '], '-', $style);

        return in_array($style, self::SUPPORTED_STYLES, true) ? $style : 'pygments';
    }

    public static function stylesheet(string $style = 'pygments', string $selector = '.sourceCode'): string
    {
        $selector = trim($selector) === '' ? '.sourceCode' : trim($selector);
        $style = self::normalizeStyle($style);
        $colors = self::styleColors($style);

        return self::stylesheetFromColors($colors, [], $selector);
    }

    /**
     * @return array{
     *   name:string,
     *   colors:array<string, string>,
     *   tokenStyles:array<string, array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}>,
     *   diagnostics:list<array{code:string, message:string}>
     * }
     */
    public static function parseThemeJson(string $json, string $baseStyle = 'pygments'): array
    {
        if (substr($json, 0, 3) === "\xEF\xBB\xBF") {
            throw new \InvalidArgumentException('Pandoc highlight theme JSON must be UTF-8 without a BOM');
        }

        try {
            $theme = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Unable to parse Pandoc highlight theme JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($theme)) {
            throw new \InvalidArgumentException('Pandoc highlight theme JSON must decode to an object');
        }

        $colors = self::styleColors(self::normalizeStyle($baseStyle));
        $diagnostics = [];
        $tokenStyles = [];
        $editorColors = self::arrayValue($theme, ['editor-colors', 'editorColors']) ?? [];
        if (!is_array($editorColors)) {
            $editorColors = [];
        }

        $normalStyle = self::themeTokenStyle($theme, 'NormalTok') ?? self::themeTokenStyle($theme, 'Normal');
        $textColor = self::themeColor($theme, ['text-color', 'textColor', 'default-color', 'defaultColor'], 'theme text color')
            ?? ($normalStyle === null ? null : ($normalStyle['color'] ?? null));
        $backgroundColor = self::themeColor($theme, ['background-color', 'backgroundColor'], 'theme background color')
            ?? ($normalStyle === null ? null : ($normalStyle['background'] ?? null));
        $lineNumberColor = self::themeColor($theme, ['line-number-color', 'lineNumberColor'], 'line number color')
            ?? self::themeColor($editorColors, ['line-number-color', 'line-numbers', 'lineNumberColor', 'lineNumbers'], 'editor line number color');
        $lineNumberBackground = self::themeColor($theme, ['line-number-background-color', 'lineNumberBackgroundColor'], 'line number background color')
            ?? self::themeColor($editorColors, ['line-number-background-color', 'lineNumberBackgroundColor'], 'editor line number background color');

        if ($textColor !== null) {
            $colors['text'] = $textColor;
        }
        if ($backgroundColor !== null) {
            $colors['background'] = $backgroundColor;
        }
        if ($lineNumberColor !== null) {
            $colors['lineNumber'] = $lineNumberColor;
        }
        if ($lineNumberBackground !== null) {
            $colors['lineNumberBackground'] = $lineNumberBackground;
        }

        $themeTokenStyles = self::arrayValue($theme, ['token-styles', 'tokenStyles', 'text-styles', 'textStyles']) ?? [];
        if (!is_array($themeTokenStyles)) {
            throw new \InvalidArgumentException('Pandoc highlight theme token-styles must be an object');
        }
        if ($themeTokenStyles !== [] && array_is_list($themeTokenStyles)) {
            throw new \InvalidArgumentException('Pandoc highlight theme token-styles must be keyed by token type');
        }

        foreach ($themeTokenStyles as $tokenName => $styleValue) {
            if (!is_array($styleValue)) {
                continue;
            }

            $type = self::tokenTypeFromThemeName((string) $tokenName);
            if ($type === null) {
                if (!in_array(self::normalizedThemeTokenName((string) $tokenName), ['normal', 'normaltok'], true)) {
                    $diagnostics[] = [
                        'code' => 'unsupported-theme-token',
                        'message' => "Theme token style '{$tokenName}' is not used by the bounded native highlighter",
                    ];
                }
                continue;
            }

            $parsedStyle = self::parseTokenStyle($styleValue, "token style {$tokenName}");
            if (isset($parsedStyle['color'])) {
                $colors[$type] = $parsedStyle['color'];
            }
            $tokenStyles[$type] = $parsedStyle;
        }

        $nameValue = self::arrayValue($theme, ['name', 'title']);
        if (!is_string($nameValue) || trim($nameValue) === '') {
            $metadata = $theme['metadata'] ?? null;
            if (is_array($metadata)) {
                $metadataName = self::arrayValue($metadata, ['name', 'title']);
                $nameValue = is_string($metadataName) ? $metadataName : 'custom-theme';
            } else {
                $nameValue = 'custom-theme';
            }
        }

        return [
            'name' => self::sanitizeStyleName($nameValue),
            'colors' => $colors,
            'tokenStyles' => $tokenStyles,
            'diagnostics' => $diagnostics,
        ];
    }

    public static function stylesheetFromThemeJson(string $json, string $selector = '.sourceCode', string $baseStyle = 'pygments'): string
    {
        return self::stylesheetFromParsedTheme(self::parseThemeJson($json, $baseStyle), $selector);
    }

    /**
     * @param array{
     *   name:string,
     *   colors:array<string, string>,
     *   tokenStyles:array<string, array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}>,
     *   diagnostics:list<array{code:string, message:string}>
     * } $theme
     */
    private static function stylesheetFromParsedTheme(array $theme, string $selector = '.sourceCode'): string
    {
        $selector = trim($selector) === '' ? '.sourceCode' : trim($selector);

        return self::stylesheetFromColors($theme['colors'], $theme['tokenStyles'], $selector);
    }

    /**
     * @param array<string, string> $colors
     * @param array<string, array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}> $tokenStyles
     */
    private static function stylesheetFromColors(array $colors, array $tokenStyles = [], string $selector = '.sourceCode'): string
    {
        $rules = [
            "{$selector} { background: {$colors['background']}; color: {$colors['text']}; }",
            self::tokenStylesheetRule($selector, 'kw', 'keyword', $colors, $tokenStyles, ['font-weight: 600']),
            self::tokenStylesheetRule($selector, 'dt', 'datatype', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'st', 'string', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'co', 'comment', $colors, $tokenStyles, ['font-style: italic']),
            self::tokenStylesheetRule($selector, 'dv', 'number', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'fu', 'function', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'va', 'variable', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'ot', 'attribute', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'op', 'operator', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'pp', 'preprocessor', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'cn', 'constant', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'in', 'information', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 're', 'region', $colors, $tokenStyles, ['font-weight: 600']),
            self::tokenStylesheetRule($selector, 'al', 'warning', $colors, $tokenStyles, ['font-weight: 600']),
            "{$selector} .highlighted-line { display: inline-block; width: 100%; background-color: rgba(255, 229, 100, 0.24); }",
            'pre.numberSource code { counter-reset: source-line 0; }',
            'pre.numberSource code > span { position: relative; left: -4em; counter-increment: source-line; }',
            self::lineNumberStylesheetRule($colors),
            'pre.numberSource { margin-left: 3em; padding-left: 4px; }',
        ];

        return implode("\n", $rules);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     * @param array{
     *   numberLines?: bool,
     *   lineAnchors?: bool,
     *   startNumber?: int,
     *   lineIdPrefix?: string,
     *   containerClasses?: list<string>,
     *   tokenTitles?: bool,
     *   highlightLines?: list<int>
     * } $options
     */
    public static function renderHighlightedHtml(array $tokens, string $language = '', array $options = []): string
    {
        $language = self::normalizeLanguage($language) ?? '';
        $lineMode = ($options['numberLines'] ?? false) || ($options['lineAnchors'] ?? false) || (($options['highlightLines'] ?? []) !== []);
        if ($lineMode) {
            return self::renderLineNumberedHtml($tokens, $language, $options);
        }

        $classes = trim('sourceCode' . ($language === '' ? '' : ' ' . self::sanitizeClass($language)));
        $tokenTitles = (bool) ($options['tokenTitles'] ?? false);
        $html = '';

        foreach ($tokens as $token) {
            $html .= self::renderTokenHtml($token, $tokenTitles);
        }

        return '<pre class="' . $classes . '"><code class="' . $classes . '">' . $html . '</code></pre>';
    }

    public function wordpressHtmlBlock(AstNode $codeBlock, string $style = 'pygments', array $options = []): string
    {
        $highlighted = $this->highlightCodeBlock($codeBlock, $style, $options);
        $styleName = self::escapeHtml($highlighted['style']);

        return '<!-- wp:html -->'
            . "\n" . '<style data-pandoc-highlight-style="' . $styleName . '">' . "\n"
            . $highlighted['css'] . "\n"
            . '</style>' . "\n"
            . $highlighted['html']
            . "\n" . '<!-- /wp:html -->';
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenize(string $code, string $language): array
    {
        return match ($language) {
            'agda' => $this->tokenizeAgda($code),
            'apache' => $this->tokenizeApacheConfig($code),
            'asciidoc' => $this->tokenizeAsciiDoc($code),
            'awk' => $this->tokenizeAwk($code),
            'bash' => $this->tokenizeBash($code),
            'batch' => $this->tokenizeBatch($code),
            'bibtex' => $this->tokenizeBibtex($code),
            'c', 'cpp' => $this->tokenizeC($code),
            'clojure' => $this->tokenizeClojure($code),
            'cmake' => $this->tokenizeCMake($code),
            'commonlisp' => $this->tokenizeCommonLisp($code),
            'coq' => $this->tokenizeCoq($code),
            'crystal' => $this->tokenizeCrystal($code),
            'csharp' => $this->tokenizeCSharp($code),
            'css' => $this->tokenizeCss($code),
            'csv' => $this->tokenizeDelimitedText($code, ','),
            'd' => $this->tokenizeD($code),
            'dart' => $this->tokenizeDart($code),
            'diff' => $this->tokenizeDiff($code),
            'dot' => $this->tokenizeDot($code),
            'dockerfile' => $this->tokenizeDockerfile($code),
            'elm' => $this->tokenizeElm($code),
            'elixir' => $this->tokenizeElixir($code),
            'erlang' => $this->tokenizeErlang($code),
            'fennel' => $this->tokenizeFennel($code),
            'fish' => $this->tokenizeFish($code),
            'fortran' => $this->tokenizeFortran($code),
            'fsharp' => $this->tokenizeFSharp($code),
            'go' => $this->tokenizeGo($code),
            'graphql' => $this->tokenizeGraphql($code),
            'groovy' => $this->tokenizeGroovy($code),
            'hcl' => $this->tokenizeHcl($code),
            'haskell' => $this->tokenizeHaskell($code),
            'html' => $this->tokenizeHtml($code),
            'idris' => $this->tokenizeIdris($code),
            'ini' => $this->tokenizeIni($code),
            'java' => $this->tokenizeJava($code),
            'javascript' => $this->tokenizeJavaScript($code),
            'jsx' => $this->tokenizeJsx($code),
            'json' => $this->tokenizeJson($code),
            'jsonc' => $this->tokenizeJsonWithComments($code),
            'just' => $this->tokenizeJustfile($code),
            'julia' => $this->tokenizeJulia($code),
            'kotlin' => $this->tokenizeKotlin($code),
            'less' => $this->tokenizeLess($code),
            'liquid' => $this->tokenizeLiquid($code),
            'lua' => $this->tokenizeLua($code),
            'makefile' => $this->tokenizeMakefile($code),
            'markdown' => $this->tokenizeMarkdown($code),
            'matlab' => $this->tokenizeMatlab($code),
            'meson' => $this->tokenizeMeson($code),
            'mermaid' => $this->tokenizeMermaid($code),
            'mustache' => $this->tokenizeMustache($code),
            'nginx' => $this->tokenizeNginx($code),
            'nim' => $this->tokenizeNim($code),
            'nix' => $this->tokenizeNix($code),
            'objectivec' => $this->tokenizeObjectiveC($code),
            'ocaml' => $this->tokenizeOcaml($code),
            'pascal' => $this->tokenizePascal($code),
            'perl' => $this->tokenizePerl($code),
            'php' => $this->tokenizePhp($code),
            'powershell' => $this->tokenizePowerShell($code),
            'protobuf' => $this->tokenizeProtobuf($code),
            'purescript' => $this->tokenizePureScript($code),
            'python' => $this->tokenizePython($code),
            'r' => $this->tokenizeR($code),
            'raku' => $this->tokenizeRaku($code),
            'ruby' => $this->tokenizeRuby($code),
            'rst' => $this->tokenizeRest($code),
            'rust' => $this->tokenizeRust($code),
            'sass', 'scss' => $this->tokenizeScss($code),
            'scala' => $this->tokenizeScala($code),
            'scheme' => $this->tokenizeScheme($code),
            'sed' => $this->tokenizeSed($code),
            'shellsession' => $this->tokenizeShellSession($code),
            'sql' => $this->tokenizeSql($code),
            'swift' => $this->tokenizeSwift($code),
            'tcl' => $this->tokenizeTcl($code),
            'tex' => $this->tokenizeTex($code),
            'toml' => $this->tokenizeToml($code),
            'tsv' => $this->tokenizeDelimitedText($code, "\t"),
            'twig' => $this->tokenizeTwig($code),
            'tsx' => $this->tokenizeTsx($code),
            'typst' => $this->tokenizeTypst($code),
            'typescript' => $this->tokenizeTypeScript($code),
            'v' => $this->tokenizeV($code),
            'vim' => $this->tokenizeVimscript($code),
            'vue' => $this->tokenizeVue($code),
            'xml' => $this->tokenizeXml($code),
            'xslt' => $this->tokenizeXml($code),
            'yaml' => $this->tokenizeYaml($code),
            default => [['type' => 'text', 'text' => $code, 'class' => '']],
        };
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeApacheConfig(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['keyword', '/^<\\/?(?:Directory|Files|If|IfDefine|IfModule|Location|VirtualHost)\\b/i'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^%\\{[A-Za-z_][A-Za-z0-9_:-]*\\}/'],
            ['attribute', '/^\\[[A-Za-z0-9_,=! -]+\\]/'],
            ['datatype', '/^\\bmod_[A-Za-z0-9_]+\\.c\\b/i'],
            ['keyword', '/^\\b(?:AddType|AllowOverride|AuthName|AuthType|BrowserMatch|CustomLog|Deny|DirectoryIndex|ErrorDocument|Header|IfModule|Include|Listen|LogFormat|Options|Order|Redirect|RedirectMatch|Require|RewriteBase|RewriteCond|RewriteEngine|RewriteRule|ServerAlias|ServerName|SetEnv|SetEnvIf|SetEnvIfNoCase|SetHandler|SetOutputFilter|SetInputFilter|Set|Unset|append|always|early|edit|edit\\*|env|expr|merge|onsuccess|set|unset)\\b/i'],
            ['constant', '/^\\b(?:All|Any|Denied|FollowSymLinks|Indexes|None|Off|On|SAMEORIGIN|Require|all|denied|forbidden|granted|last|redirect|skip)\\b/i'],
            ['string', '/^(?:\\/|\\.\\.?\\/)[^\\s\\[\\]#]*/'],
            ['number', '/^\\b\\d{3}\\b|^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_.:-]*\\b/'],
            ['operator', '/^(?:!-?[A-Za-z]|-[A-Za-z]|!=|==|<=|>=|\\/?>|[{}()[\\];,.+*\\/%=!<>?:|&^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeAwk(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', '/^\\/(?:\\\\.|\\[[^\\]\\n]*(?:\\\\.[^\\]\\n]*)*\\]|[^\\/\\\\\\n])+\\/(?=\\s*(?:[),;{}]|&&|\\|\\||$))/'],
            ['keyword', '/^@(?:include|load)\\b/'],
            ['region', '/^\\b(?:BEGINFILE|ENDFILE|BEGIN|END)\\b/'],
            ['keyword', '/^\\b(?:break|case|continue|default|do|else|exit|for|if|return|switch|while)\\b/'],
            ['keyword', '/^\\b(?:delete|function|getline|in|nextfile|next|print|printf)\\b/'],
            ['variable', '/^\\$[A-Za-z0-9_]+/'],
            ['variable', '/^\\b(?:ARGC|ARGIND|ARGV|BINMODE|CONVFMT|ENVIRON|ERRNO|FIELDWIDTHS|FILENAME|FNR|FPAT|FS|FUNCTAB|IGNORECASE|LINT|NF|NR|OFMT|OFS|ORS|PREC|PROCINFO|ROUNDMODE|RS|RT|RSTART|RLENGTH|SUBSEP|SYMTAB|TEXTDOMAIN)\\b/'],
            ['function', '/^\\b(?:and|asort|asorti|atan2|bindtextdomain|close|compl|cos|dcgettext|dcngettext|exp|fflush|gensub|gsub|index|int|isarray|length|log|lshift|match|mktime|or|patsplit|rand|rshift|sin|split|sprintf|sqrt|srand|strftime|strtonum|sub|substr|system|systime|tolower|toupper|typeof|xor)\\b(?=\\s*\\()/'],
            ['constant', '/^\\[\\[:(?:alnum|alpha|blank|cntrl|digit|graph|lower|print|punct|space|upper|xdigit):\\]\\]/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d*)?|\\.\\d+)(?:[eE][+-]?\\d+)?\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\+\\+|--|\\+=|-=|\\*=|\\/=|%=|\\^=|==|!=|<=|>=|&&|\\|\\||!~|~|[{}()[\\];,.+*\\/%=!<>?:&|^$-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeC(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['preprocessor', '/^#[ \\t]*(?:include|define|undef|if|ifdef|ifndef|elif|else|endif|pragma|error|warning)\\b[^\\n]*/'],
            ['string', '/^(?:u8|u|U|L)?"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^(?:u8|u|U|L)?'(?:\\\\.|[^'\\\\])+'/s"],
            ['keyword', '/^\\b(?:alignas|alignof|asm|auto|break|case|catch|class|concept|const|consteval|constexpr|constinit|continue|decltype|default|delete|do|else|enum|explicit|export|extern|for|friend|goto|if|inline|mutable|namespace|new|noexcept|operator|private|protected|public|register|requires|restrict|return|sizeof|static|static_assert|struct|switch|template|this|thread_local|throw|try|typedef|typename|union|using|virtual|volatile|while)\\b/'],
            ['constant', '/^\\b(?:false|NULL|nullptr|true)\\b/'],
            ['datatype', '/^\\b(?:bool|char|char8_t|char16_t|char32_t|double|float|int|long|short|signed|size_t|ssize_t|std|string|uint8_t|uint16_t|uint32_t|uint64_t|unsigned|void|wchar_t)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)[uUlLfF]*\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({*&:]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|->\\*|->|\\.\\*|\\.\\.\\.|<<=|>>=|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|<<|>>|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeObjectiveC(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['preprocessor', '/^#[ \\t]*(?:include|import|define|undef|if|ifdef|ifndef|elif|else|endif|pragma|error|warning)\\b[^\\n]*/'],
            ['string', '/^@?(?:u8|u|U|L)?"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^(?:u8|u|U|L)?'(?:\\\\.|[^'\\\\])+'/s"],
            ['keyword', '/^@(?:autoreleasepool|available|catch|class|compatibility_alias|defs|dynamic|encode|end|finally|implementation|interface|optional|package|private|property|protected|protocol|public|required|selector|synthesize|throw|try)\\b/'],
            ['keyword', '/^\\b(?:alignas|alignof|asm|auto|break|case|const|continue|default|do|else|enum|extern|for|goto|if|inline|nonatomic|nullable|nonnull|null_resettable|readonly|readwrite|register|restrict|return|sizeof|static|struct|switch|typedef|union|volatile|while|weak|strong|copy|assign|retain|unsafe_unretained)\\b/'],
            ['constant', '/^\\b(?:false|nil|Nil|NO|NULL|nullptr|self|super|true|YES)\\b/'],
            ['datatype', '/^\\b(?:BOOL|CGFloat|Class|double|float|id|IMP|int|NSInteger|NSUInteger|long|SEL|short|signed|size_t|ssize_t|uint8_t|uint16_t|uint32_t|uint64_t|unichar|unsigned|void|wchar_t)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)[uUlLfF]*\\b/'],
            ['function', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({*&:]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|->|\\.\\.\\.|\\?:|<<=|>>=|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|<<|>>|[{}()[\\];,.+*\\/%=!<>?:&|^~@-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePascal(string $code): array
    {
        return $this->scan($code, [
            ['preprocessor', '/^\\{\\$[\\s\\S]*?\\}/'],
            ['comment', '/^\\(\\*[\\s\\S]*?\\*\\)/'],
            ['comment', '/^\\{[\\s\\S]*?\\}/'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['constant', '/^\\b(?:false|nil|true)\\b/i'],
            ['keyword', '/^\\b(?:absolute|and|array|as|asm|begin|case|class|const|constructor|destructor|dispinterface|div|do|downto|else|end|except|exit|exports|file|finalization|finally|for|function|goto|if|implementation|in|inherited|initialization|inline|interface|is|label|library|mod|not|object|of|or|out|overload|override|packed|private|procedure|program|property|protected|public|published|raise|record|repeat|resourcestring|result|set|shl|shr|then|threadvar|to|try|type|unit|until|uses|var|while|with|xor)\\b/i'],
            ['datatype', '/^\\b(?:ansistring|boolean|byte|cardinal|char|double|extended|integer|int64|longint|qword|real|shortint|single|string|tobject|word)\\b/i'],
            ['function', '/^\\b(?:assigned|copy|format|inc|length|lowercase|pos|setlength|trim|uppercase|writeln)\\b(?=\\s*\\()/i'],
            ['number', '/^\\$[0-9A-Fa-f]+\\b/'],
            ['number', '/^-?\\b(?:\\d+(?:\\.\\d*)?|\\.\\d+)(?:[eE][+-]?\\d+)?\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['datatype', '/^\\bT[A-Z][A-Za-z0-9_]*\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?::=|\\.\\.|<>|<=|>=|[{}()[\\];,.+*\\/%=!<>:@^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCMake(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$<(?:(?:\\$<[^>\\n]+>)|[^>\\n])+>/'],
            ['variable', '/^\\$\\{[A-Za-z_][A-Za-z0-9_.:\\/-]*\\}/'],
            ['keyword', '/^\\b(?:AND|BYPRODUCTS|CACHE|COMMAND|COMPONENT|CONFIGURE_DEPENDS|DEPENDS|DESTINATION|EXISTS|EXPORT|FILES|FORCE|LANGUAGES|LIBRARY|MATCHES|NOT|OR|OUTPUT_VARIABLE|POLICY|PRIVATE|PUBLIC|REQUIRED|RESULT_VARIABLE|TARGETS|VERSION|WORKING_DIRECTORY)\\b/i'],
            ['datatype', '/^\\b(?:ALIAS|BOOL|C|CUDA|CXX|FILEPATH|IMPORTED|INTERFACE|INTERNAL|MODULE|OBJC|OBJCXX|OBJECT|PATH|SHARED|STATIC|STRING)\\b/i'],
            ['constant', '/^\\b(?:FALSE|NO|NOTFOUND|OFF|ON|TRUE|YES)\\b/i'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)*\\b/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\s*=)/'],
            ['function', '/^\\b(?:add_compile_definitions|add_custom_command|add_custom_target|add_executable|add_library|cmake_minimum_required|configure_file|execute_process|file|find_library|find_package|find_path|find_program|foreach|if|include|install|list|message|option|project|set|target_compile_definitions|target_include_directories|target_link_libraries|target_sources)\\b(?=\\s*\\()/i'],
            ['function', '/^\\b(?:add|cmake|target)_[A-Za-z0-9_]+\\b(?=\\s*\\()/i'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_:-]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeNginx(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$\\{?[A-Za-z_][A-Za-z0-9_]*\\}?/'],
            ['keyword', '/^\\b(?:add_header|access_log|allow|auth_basic|auth_basic_user_file|break|client_body_buffer_size|client_max_body_size|deny|error_log|expires|fastcgi_index|fastcgi_param|fastcgi_pass|fastcgi_read_timeout|fastcgi_split_path_info|gzip|if|include|index|listen|location|proxy_pass|proxy_read_timeout|proxy_redirect|proxy_set_header|return|rewrite|root|server|server_name|set|try_files|types)\\b/i'],
            ['constant', '/^\\b(?:always|break|default_server|http2|last|off|on|permanent|redirect|ssl)\\b/i'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?(?:[kKmMgGsSmMhHdD])?\\b/'],
            ['string', '/^unix:[^\\s{};#\'"$]+/'],
            ['string', '/^(?:\\/|\\.\\.?\\/|@)[^\\s{};#\'"$]*/'],
            ['string', '/^\\\\\\.[A-Za-z0-9_.-]*\\$?/'],
            ['string', '/^(?:\\\\.|[\\^$.*+?()[\\]|-])+/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_.-]*\\b/'],
            ['operator', '/^(?:\\^~|~\\*?|==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>?:|&^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeNim(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#\\[[\\s\\S]*?\\]#/'],
            ['comment', '/^#[^\\n]*/'],
            ['attribute', '/^\\{\\.[\\s\\S]*?\\.\\}/'],
            ['string', '/^(?i)(?:r|raw)?"""[\\s\\S]*?"""/'],
            ['string', '/^(?i)(?:r|raw)?f?"(?:\\\\.|[^"\\\\])*"/'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/"],
            ['keyword', '/^\\b(?:addr|and|as|asm|bind|block|break|case|cast|concept|const|continue|converter|defer|discard|distinct|do|elif|else|end|enum|except|export|finally|for|from|func|if|import|in|include|interface|is|isnot|iterator|let|macro|method|mixin|not|notin|object|of|or|out|proc|ptr|raise|ref|return|shl|shr|static|template|try|tuple|type|using|var|when|while|xor|yield)\\b/'],
            ['constant', '/^\\b(?:false|nil|none|true)\\b/'],
            ['datatype', '/^\\b(?:auto|bool|byte|char|cstring|float(?:32|64)?|int(?:8|16|32|64)?|Natural|Option|Positive|RootObj|seq|string|uint(?:8|16|32|64)?|void)\\b/'],
            ['function', '/^`[^`\\n]+`(?=\\s*\\()/'],
            ['function', '/^[A-Za-z_][A-Za-z0-9_]*[!?*]?(?=\\s*\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*\\b/'],
            ['number', '/^-?\\b(?:0x[0-9A-Fa-f_]+|0b[01_]+|\\d[\\d_]*(?:\\.\\d[\\d_]*)?(?:e[+-]?\\d[\\d_]*)?)(?:\'?[A-Za-z][A-Za-z0-9_]*)?\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.|=>|->|==|!=|<=|>=|\\+=|-=|\\*=|\\/=|:=|[{}()[\\];,.+*\\/%=!<>?:|&@^$\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private function phpPatterns(): array
    {
        return [
            ['preprocessor', '/^<\\?(?:php|=)?|^\\?>/i'],
            ['attribute', '/^#\\[[^\\]\\n]*(?:\\][ \\t]*#\\[[^\\]\\n]*)*\\]/'],
            ['phpdoc', '/^\\/\\*\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^(?:\\/\\/|#)[^\\n]*/'],
            ['string', '/^<<<[ \\t]*([\\\'"]?)([A-Za-z_][A-Za-z0-9_]*)\\1[ \\t]*(?:\\r?\\n[\\s\\S]*?\\r?\\n[ \\t]*\\2;?)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['keyword', '/^\\b(?:abstract|as|break|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|endforeach|endif|enum|extends|final|finally|fn|for|foreach|function|global|if|implements|interface|match|namespace|new|private|protected|public|readonly|return|static|switch|throw|trait|try|use|while|yield)\\b/i'],
            ['constant', '/^\\b(?:false|null|true)\\b/i'],
            ['datatype', '/^\\b(?:array|bool|callable|float|int|iterable|mixed|never|object|self|string|void)\\b/i'],
            ['number', '/^\\b(?:0x[0-9A-Fa-f]+|\\d+(?:\\.\\d+)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[({:]|::|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:=>|->|::|===|!==|==|!=|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:-])/'],
        ];
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private static function phpDocTypePatterns(): array
    {
        return [
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['datatype', '/^(?:non-empty-string|non-empty-array|class-string|positive-int|array-key)\\b/i'],
            ['datatype', '/^\\b(?:array|bool|boolean|callable|false|float|int|integer|iterable|list|mixed|never|null|object|self|static|string|true|void)\\b/i'],
            ['datatype', '/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*/'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['operator', '/^(?:\\[\\]|->|::|<=|>=|=>|[{}()[\\]<>,|&?:=])/'],
        ];
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeNix(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^#[^\\n]*/'],
            ['string', "/^''[\\s\\S]*?''/"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['constant', '/^<[^>\\n]+>/'],
            ['string', '/^(?:\\.{1,2}|~)?\\/[A-Za-z0-9_+.,%:@=-][A-Za-z0-9_+.,%:@=\\/-]*/'],
            ['keyword', '/^\\b(?:assert|else|if|in|inherit|let|or|rec|then|with)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['function', '/^\\b(?:abort|baseNameOf|derivation|dirOf|fetchTarball|import|map|removeAttrs|throw|toString)\\b(?=\\s|$)/'],
            ['datatype', '/^\\bbuiltins\\b/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*=)/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_-]*/'],
            ['operator', '/^(?:\\$\\{|\\+\\+|==|!=|<=|>=|&&|\\|\\||->|\\/\\/|[{}()[\\];,.?:=+*\\/!<>|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePerl(string $code): array
    {
        return $this->scan($code, [
            ['keyword', '/^#![^\\n]*/'],
            ['comment', '/^=(?:head[1-6]|over|back|item|for|begin|end|pod|cut)\\b[^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^s\\/(?:\\\\.|[^\\/\\\\])*\\/(?:\\\\.|[^\\/\\\\])*\\/[msixpadluncgeor]*/'],
            ['string', '/^q[qwxr]?\\{(?:\\\\.|[^}\\\\])*\\}/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:BEGIN|END|__DATA__|__END__|__FILE__|__LINE__|__PACKAGE__|break|continue|default|defer|do|each|else|elsif|for|foreach|given|if|last|local|my|next|our|package|redo|return|state|sub|unless|until|when|while)\\b/'],
            ['keyword', '/^\\b(?:bytes|constant|diagnostics|english|filetest|integer|less|locale|open|sigtrap|strict|subs|utf8|vars|warnings)\\b/'],
            ['constant', '/^\\b(?:undef)\\b/'],
            ['number', '/^-?\\b(?:0[xX]_?[0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB]_?[01](?:_?[01])*|0[0-7](?:_?[0-7])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?_?\\d(?:_?\\d)*)?)\\b/'],
            ['operator', '/^\\b(?:and|cmp|eq|ge|gt|le|lt|ne|not|or)\\b/'],
            ['function', '/^\\b(?:bless|chomp|close|defined|delete|die|exists|grep|join|keys|lc|map|open|print|printf|push|require|say|shift|sort|split|uc|use|values|warn)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?:::[A-Z][A-Za-z0-9_]*)*/'],
            ['variable', '/^(?:\\$[#_]?[A-Za-z_][A-Za-z0-9_]*|[@%][A-Za-z_][A-Za-z0-9_]*|\\$\\d+|[@%]_|\\$_|[$@%]\\{[^}\\n]+\\})/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\})/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:\\(|\\{))/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|=>|->|=~|!~|==|!=|<=|>=|&&|\\|\\||\\/\\/|\\.\\.\\.?|\\+=|-=|\\*=|\\/=|%=|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePhp(string $code): array
    {
        return $this->expandPhpDocTokens($this->scan($code, $this->phpPatterns()));
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     * @return list<array{type:string, text:string, class:string}>
     */
    private function expandPhpDocTokens(array $tokens): array
    {
        $expanded = [];
        foreach ($tokens as $token) {
            if (($token['type'] ?? '') !== 'phpdoc') {
                $this->appendToken($expanded, (string) ($token['type'] ?? 'text'), (string) ($token['text'] ?? ''));
                continue;
            }

            $this->tokenizePhpDocComment((string) ($token['text'] ?? ''), $expanded);
        }

        return $expanded;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizePhpDocComment(string $comment, array &$tokens): void
    {
        $parts = preg_split('/(\n)/', $comment, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            $this->appendToken($tokens, 'comment', $comment);
            return;
        }

        for ($index = 0; $index < count($parts); $index += 2) {
            $line = (string) $parts[$index];
            if ($line !== '') {
                $this->tokenizePhpDocLine($line, $tokens);
            }

            if (($parts[$index + 1] ?? '') === "\n") {
                $this->appendToken($tokens, 'text', "\n");
            }
        }
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizePhpDocLine(string $line, array &$tokens): void
    {
        if (str_starts_with($line, '/**') || trim($line) === '*/') {
            $this->appendToken($tokens, 'comment', $line);
            return;
        }

        if (preg_match('/^([ \t]*\\*[ \t]?)(.*)$/', $line, $matches) !== 1) {
            $this->appendToken($tokens, 'comment', $line);
            return;
        }

        $this->appendToken($tokens, 'comment', $matches[1]);
        $body = (string) $matches[2];
        if (preg_match('/^(@[A-Za-z_][A-Za-z0-9_-]*)(\\s*)(.*)$/', $body, $annotation) !== 1) {
            $this->appendToken($tokens, 'comment', $body);
            return;
        }

        $tag = strtolower(substr($annotation[1], 1));
        $this->appendToken($tokens, 'attribute', $annotation[1]);
        $this->appendToken($tokens, 'text', $annotation[2]);
        $this->tokenizePhpDocAnnotationTail($tag, (string) $annotation[3], $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizePhpDocAnnotationTail(string $tag, string $tail, array &$tokens): void
    {
        if ($tail === '') {
            return;
        }

        if (in_array($tag, ['param', 'phpstan-param', 'psalm-param', 'var', 'property', 'property-read', 'property-write'], true)) {
            if (preg_match('/^(.*?)(\\$[A-Za-z_][A-Za-z0-9_]*)(.*)$/', $tail, $matches) === 1) {
                $this->tokenizePhpDocTypeSegment($matches[1], $tokens);
                $this->appendToken($tokens, 'variable', $matches[2]);
                $this->appendToken($tokens, 'comment', $matches[3]);
                return;
            }
        }

        if (in_array($tag, ['return', 'phpstan-return', 'psalm-return', 'throws', 'throw', 'exception'], true)) {
            $this->tokenizePhpDocLeadingType($tail, $tokens);
            return;
        }

        if (in_array($tag, ['template', 'phpstan-template', 'psalm-template'], true)) {
            if (preg_match('/^(\\S+)(\\s+)(of)(\\s+)(\\S+)(.*)$/i', $tail, $matches) === 1) {
                $this->scanInto($matches[1], self::phpDocTypePatterns(), $tokens);
                $this->appendToken($tokens, 'text', $matches[2]);
                $this->appendToken($tokens, 'comment', $matches[3]);
                $this->appendToken($tokens, 'text', $matches[4]);
                $this->scanInto($matches[5], self::phpDocTypePatterns(), $tokens);
                $this->appendToken($tokens, 'comment', $matches[6]);
                return;
            }

            $this->tokenizePhpDocLeadingType($tail, $tokens);
            return;
        }

        $this->appendToken($tokens, 'comment', $tail);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizePhpDocTypeSegment(string $segment, array &$tokens): void
    {
        $type = rtrim($segment);
        if ($type !== '') {
            $this->scanInto($type, self::phpDocTypePatterns(), $tokens);
        }

        $this->appendToken($tokens, 'text', substr($segment, strlen($type)));
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizePhpDocLeadingType(string $tail, array &$tokens): void
    {
        if (preg_match('/^(\\S+)(.*)$/', $tail, $matches) !== 1) {
            $this->appendToken($tokens, 'comment', $tail);
            return;
        }

        $this->scanInto($matches[1], self::phpDocTypePatterns(), $tokens);
        $this->appendToken($tokens, 'comment', $matches[2]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJava(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:abstract|assert|break|case|catch|class|continue|default|do|else|enum|exports|extends|final|finally|for|if|implements|import|instanceof|interface|module|native|new|non-sealed|open|opens|package|permits|private|protected|provides|public|record|requires|return|sealed|static|strictfp|super|switch|synchronized|this|throw|throws|to|transient|transitive|try|uses|var|volatile|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:boolean|byte|char|double|float|int|long|short|void)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)[fFdDlL]?\\b/'],
            ['datatype', '/^\\b(?:BigDecimal|BigInteger|Boolean|Byte|Character|Class|Double|Exception|Files|Float|HashMap|HashSet|IOException|Integer|List|Long|Map|Objects|Optional|Path|Pattern|Record|Set|String|StringBuilder|URI|UUID)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|->|>>>?=?|<<=?|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|\\.\\.\\.|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeD(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\+[\\s\\S]*?\\+\\//'],
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`[^`]*`/s'],
            ['string', '/^(?:r|x|q)?"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_]*/'],
            ['preprocessor', '/^#[A-Za-z_][A-Za-z0-9_]*/'],
            ['keyword', '/^\\b(?:abstract|alias|align|asm|assert|auto|body|break|case|cast|catch|class|const|continue|debug|default|delegate|delete|deprecated|do|else|enum|export|extern|final|finally|foreach|foreach_reverse|function|goto|if|immutable|import|in|inout|interface|invariant|is|lazy|macro|mixin|module|new|nothrow|out|override|package|pragma|private|protected|public|pure|ref|return|scope|shared|static|struct|super|switch|synchronized|template|this|throw|try|typeof|union|unittest|version|while|with|__gshared|__traits)\\b/'],
            ['constant', '/^\\b(?:__DATE__|__FILE__|__FUNCTION__|__LINE__|__MODULE__|__PRETTY_FUNCTION__|__TIME__|__TIMESTAMP__|false|null|true)\\b/'],
            ['datatype', '/^\\b(?:Object|Throwable|Exception|string|wstring|dstring|bool|byte|ubyte|short|ushort|int|uint|long|ulong|cent|ucent|float|double|real|ifloat|idouble|ireal|cfloat|cdouble|creal|char|wchar|dchar|void|size_t|ptrdiff_t)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)(?:[uU]?[lL]?|[fFL])?\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:!\\s*(?:"[^"\\n]*"|[A-Za-z_][A-Za-z0-9_]*|\\([^\\)\\n]*\\))\\s*\\(|\\())/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|\\.\\.|=>|==|!=|<=|>=|&&|\\|\\||<<|>>|\\+\\+|--|[{}()[\\];,.+*\\/%=!<>?:&|^~$-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeKotlin(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:actual|as|break|by|catch|class|companion|constructor|continue|data|do|dynamic|else|enum|expect|external|final|finally|for|fun|get|if|import|in|infix|init|inline|inner|interface|internal|is|lateinit|noinline|object|open|operator|out|override|package|private|protected|public|reified|return|sealed|set|super|suspend|tailrec|this|throw|to|try|typealias|val|var|vararg|when|where|while)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:Any|Array|Boolean|Byte|Char|CharSequence|Double|Float|Int|Iterable|Json|List|Long|Map|MutableList|MutableMap|Nothing|Pair|Result|Sequence|Set|Short|String|Unit)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)[fFlLuU]*\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:<[^>\\n]+>\\s*)?\\()/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\{)/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|\\.\\.|\\?\\.|\\?:|!!|->|==|!=|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeDart(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^r?"""[\\s\\S]*?"""/'],
            ['string', "/^r?'''[\\s\\S]*?'''/"],
            ['string', '/^r?"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^r?'(?:\\\\.|[^'\\\\])*'/s"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:abstract|as|assert|async|await|base|break|case|catch|class|const|continue|covariant|default|deferred|do|else|enum|export|extends|extension|external|factory|final|finally|for|get|hide|if|implements|import|in|interface|is|late|library|mixin|new|of|on|operator|part|required|rethrow|return|sealed|set|show|static|super|switch|sync|this|throw|try|typedef|var|when|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:BuildContext|Column|DateTime|Duration|Future|Iterable|Key|List|Map|Never|Object|Set|State|StatefulWidget|StatelessWidget|Stream|String|Text|Uri|Widget|bool|double|dynamic|int|num|void)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:<[^>\\n]+>\\s*)?\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\?\\?=|\\?\\?|\\?\\.|\\.\\.\\.?|=>|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|~\\/|[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeSwift(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^#*"""[\\s\\S]*?"""#*/'],
            ['string', '/^#*"(?:\\\\.|[^"\\\\])*"#*/s'],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:actor|any|as|associatedtype|async|await|break|case|catch|class|continue|convenience|defer|deinit|do|else|enum|extension|fallthrough|fileprivate|final|for|func|get|guard|if|import|in|infix|init|inout|internal|is|isolated|lazy|let|mutating|nonisolated|open|operator|optional|override|postfix|precedencegroup|prefix|private|protocol|public|repeat|required|rethrows|return|self|set|some|static|struct|subscript|super|switch|throw|throws|try|typealias|var|where|while)\\b/'],
            ['constant', '/^\\b(?:false|nil|true)\\b/'],
            ['datatype', '/^\\b(?:Any|Array|Binding|Bool|Button|Color|Data|Date|Dictionary|Double|Environment|Error|ForEach|Image|Int|List|NavigationStack|Never|ObservableObject|Published|Result|Set|Some|State|String|Text|URL|UUID|View|Void)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|0[oO][0-7](?:_?[0-7])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:<[^>\\n]+>\\s*)?\\()/'],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.?|->|=>|\\?\\?|\\?\\.|==|!=|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:&|^~@-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeScala(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^[a-z]?"""[\\s\\S]*?"""/'],
            ['string', '/^[a-z]?"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:abstract|case|catch|class|def|derives|do|else|enum|export|extends|extension|final|finally|for|given|if|import|inline|lazy|match|new|null|object|opaque|override|package|private|protected|return|sealed|super|then|this|throw|trait|try|type|val|var|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|Nil|None|Some|true)\\b/'],
            ['datatype', '/^\\b(?:Any|BigDecimal|BigInt|Boolean|CanEqual|Conversion|Double|Either|Float|Future|Int|Iterable|List|Long|Map|Nothing|Option|Seq|Set|String|Try|Unit)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)[dDfFlL]?\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.:]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:<[^>\\n]+>\\s*)?\\()/'],
            ['variable', '/^_+/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|=>|<-|\\?=>|:=|\\+=|-=|\\*=|\\/=|==|!=|<=|>=|&&|\\|\\||->|\\.\\.\\.?|[{}()[\\];,.+*\\/%=!<>?:&|^~@-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCSharp(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['preprocessor', '/^#[ \\t]*(?:define|elif|else|endif|endregion|error|if|line|nullable|pragma|region|undef|warning)\\b[^\\n]*/'],
            ['string', '/^(?:\\$@|@\\$|\\$|@)?"(?:""|\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^\\[(?:[A-Za-z_][A-Za-z0-9_]*:\\s*)?[A-Za-z_][A-Za-z0-9_.]*(?:\\([^\\]\\n]*\\))?\\]/'],
            ['keyword', '/^\\b(?:abstract|as|async|await|base|break|case|catch|checked|class|const|continue|default|delegate|do|else|enum|event|explicit|extern|finally|fixed|for|foreach|get|global|goto|if|implicit|in|init|interface|internal|is|lock|namespace|new|operator|out|override|params|partial|private|protected|public|readonly|record|ref|required|return|sealed|set|sizeof|stackalloc|static|struct|switch|this|throw|try|typeof|unchecked|unsafe|using|var|virtual|volatile|when|where|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:bool|byte|char|decimal|double|dynamic|float|int|long|nint|nuint|object|sbyte|short|string|uint|ulong|ushort|void)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)[mMdDfFlLuU]*\\b/'],
            ['datatype', '/^\\b(?:Action|CancellationToken|Console|DateTime|Dictionary|Exception|Func|Guid|IEnumerable|IReadOnlyList|JsonDocument|JsonElement|JsonPropertyName|JsonSerializer|List|Math|Memory|Nullable|Regex|ReadOnlySpan|Span|StringBuilder|Task|Uri)\\b/'],
            ['function', '/^\\b(?:Deserialize|IsNullOrWhiteSpace|RenderAsync|Trim|WriteLineAsync)\\b(?=\\s*(?:<[^>\\n]+>\\s*)?\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:=>|\\?\\?|\\?\\.|::|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|<<=?|>>=?|\\.\\.\\.?|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeGo(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`[\\s\\S]*?`/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])+'/s"],
            ['keyword', '/^\\b(?:break|case|chan|const|continue|default|defer|else|fallthrough|for|func|go|goto|if|import|interface|map|package|range|return|select|struct|switch|type|var)\\b/'],
            ['constant', '/^\\b(?:false|iota|nil|true)\\b/'],
            ['datatype', '/^\\b(?:any|bool|byte|comparable|complex64|complex128|error|float32|float64|int|int8|int16|int32|int64|rune|string|uint|uint8|uint16|uint32|uint64|uintptr)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|0[oO][0-7](?:_?[0-7])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)(?:i)?\\b/'],
            ['function', '/^\\b(?:append|cap|close|complex|copy|delete|imag|len|make|new|panic|print|println|real|recover)\\b(?=\\s*\\()/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[({*\\[]|\\b))/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|:=|<-|&\\^=?|<<=?|>>=?|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJulia(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#=[\\s\\S]*?=#/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^raw"""[\\s\\S]*?"""/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', '/^raw"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])+'/s"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.!]*/'],
            ['keyword', '/^\\b(?:abstract|baremodule|begin|break|catch|const|continue|do|else|elseif|end|export|finally|for|function|global|if|import|let|local|macro|module|mutable|primitive|quote|return|struct|try|type|using|where|while)\\b/'],
            ['constant', '/^\\b(?:false|missing|nothing|true)\\b/'],
            ['datatype', '/^\\b(?:Any|Bool|Char|Dict|Float16|Float32|Float64|Int|Int8|Int16|Int32|Int64|Integer|Matrix|Missing|Nothing|Pair|Real|Set|String|Symbol|Tuple|UInt|UInt8|UInt16|UInt32|UInt64|Union|Vector)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eEfF][+-]?\\d(?:_?\\d)*)?)(?:im)?\\b/'],
            ['attribute', '/^:[A-Za-z_][A-Za-z0-9_!?]*/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[({\\[.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_!]*(?=\\s*\\()/'],
            ['attribute', '/^\\b[A-Za-z_][A-Za-z0-9_!]*(?==)/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_!]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|::|=>|->|\\|>|==|!=|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:&|^~$@-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMatlab(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^%\\{[\\s\\S]*?%\\}/'],
            ['comment', '/^%[^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['keyword', '/^\\b(?:arguments|break|case|catch|classdef|continue|do|else|elseif|end|end_try_catch|end_unwind_protect|endclassdef|endenumeration|endevents|endfor|endfunction|endif|endmethods|endparfor|endproperties|endswitch|endwhile|enumeration|events|for|function|global|if|methods|otherwise|parfor|persistent|properties|return|switch|try|until|unwind_protect|unwind_protect_cleanup|while)\\b/'],
            ['constant', '/^\\b(?:NaN|Inf|eps|false|i|j|pi|true)\\b/'],
            ['datatype', '/^\\b(?:cell|char|categorical|double|int8|int16|int32|int64|logical|single|string|struct|table|uint8|uint16|uint32|uint64)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d*)?|\\.\\d+)(?:[eEdD][+-]?\\d+)?(?:[ij])?\\b/'],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['function', '/^\\b(?:any|cellfun|disp|double|error|exist|fieldnames|isempty|isfield|isnan|length|lower|max|min|numel|regexprep|size|sprintf|str2double|strlength|strrep|strtrim|struct|warning)\\b(?=\\s*\\()/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|\\.\\*|\\.\\/|\\.\\\\|\\.\\^|==|~=|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:&|^~@\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePowerShell(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^<#[\\s\\S]*?#>/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^@"[\\s\\S]*?(?:\\r?\\n)"@/'],
            ['string', "/^@'[\\s\\S]*?(?:\\r?\\n)'@/"],
            ['string', '/^"(?:`.|[^"`])*"/s'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['constant', '/^\\$(?:false|null|true)\\b/i'],
            ['datatype', '/^\\[(?:bool|byte|char|datetime|decimal|double|float|hashtable|int|long|object|pscustomobject|regex|sbyte|scriptblock|short|string|switch|uint|ulong|ushort|void|xml|[A-Za-z_][A-Za-z0-9_.]*(?:\\[\\])?)\\]/i'],
            ['attribute', '/^\\[[A-Za-z_][A-Za-z0-9_.]*(?:\\([^\\]\\n]*\\))?\\]/'],
            ['function', '/^\\b[A-Za-z][A-Za-z0-9]*-[A-Za-z][A-Za-z0-9]*\\b/'],
            ['keyword', '/^\\b(?:begin|break|catch|class|continue|data|do|dynamicparam|else|elseif|end|exit|filter|finally|for|foreach|from|function|if|in|inlineScript|parallel|param|process|return|sequence|switch|throw|trap|try|until|using|var|while|workflow)\\b/i'],
            ['operator', '/^(?:@\\{|@\\(|::|=>|\\.\\.|&&|\\|\\||-(?:and|contains|eq|f|ge|gt|in|is|isnot|join|le|like|lt|match|ne|not|notcontains|notin|notlike|notmatch|or|replace|split)\\b)/i'],
            ['attribute', '/^-{1,2}[A-Za-z][A-Za-z0-9_-]*/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d+)?)\\b/'],
            ['variable', '/^\\$(?:Alias|Env|Function|Global|Local|Private|Script|Using|Variable|Workflow):[A-Za-z_][A-Za-z0-9_:-]*/i'],
            ['variable', '/^\\$\\{[^}\\n]+\\}|^\\$[_?^$]|^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*=)/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^[{}()[\\];,.+*\\/%=!<>?:|&@`-]/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeProtobuf(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:default|enum|extend|extensions|import|message|oneof|option|optional|package|packed|repeated|required|reserved|returns|rpc|service|syntax|to)\\b(?!\\.)/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^\\b(?:inf|nan)\\b/i'],
            ['datatype', '/^\\b(?:bool|bytes|double|fixed32|fixed64|float|int32|int64|map|sfixed32|sfixed64|sint32|sint64|string|uint32|uint64)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f]+|0[0-7]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['attribute', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*=)/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:[{}()[\\]<>;,.=:+*\\/|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeFish(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*(?:\\[[^\\]\\n]+\\])?/'],
            ['keyword', '/^\\b(?:and|begin|break|case|continue|else|end|for|function|if|in|not|or|return|switch|while)\\b/'],
            ['keyword', '/^\\b(?:builtin|command|exec|set|source)\\b/'],
            ['function', '/^\\b(?:argparse|contains|count|echo|emit|eval|fish|functions|jq|math|path|printf|read|status|string|test|type|wp)\\b(?=\\s|[();]|$)/'],
            ['operator', '/^--(?=\\s|$)/'],
            ['attribute', '/^--?[A-Za-z][A-Za-z0-9_-]*/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_-]*\\b/'],
            ['operator', '/^(?:\\|\\||&&|2>>?|&>>?|>>?|<<?|==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeFortran(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^![^\\n]*/'],
            ['comment', '/^[cC*](?:\\s|$)[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['operator', '/^&(?=\\s*(?:\\r?\\n|!|$))/'],
            ['attribute', '/^\\b(?:allocatable|asynchronous|contiguous|dimension|external|intent|intrinsic|optional|parameter|pointer|protected|save|target|value|volatile)\\b(?=\\s*(?:\\(|,|::|\\)|$))/i'],
            ['keyword', '/^\\b(?:abstract|allocate|associate|block|call|case|class|common|contains|continue|cycle|data|deallocate|do|else|elseif|end|enddo|endif|entry|equivalence|exit|extends|forall|format|function|if|implicit|import|include|in|inout|interface|module|namelist|none|only|operator|out|private|procedure|program|public|pure|recursive|result|return|select|stop|submodule|subroutine|then|type|use|where|while)\\b/i'],
            ['constant', '/^\\.(?:false|true)\\.(?:_[A-Za-z0-9_]+)?/i'],
            ['datatype', '/^\\b(?:character|complex|double\\s+precision|integer|logical|real)\\b/i'],
            ['function', '/^\\b(?:abs|adjustl|adjustr|allocated|associated|char|count|index|int|kind|len|len_trim|logical|max|maxval|merge|min|minval|mod|nint|pack|present|real|repeat|scan|selected_int_kind|selected_real_kind|size|sum|trim|write)\\b(?=\\s*\\()/i'],
            ['number', '/^-?\\b(?:\\d+(?:\\.\\d*)?|\\.\\d+)(?:[eEdD][+-]?\\d+)?(?:_[A-Za-z0-9_]+)?\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['attribute', '/^\\b(?:access|action|advance|decimal|delim|encoding|errmsg|file|fmt|form|iostat|iomsg|kind|len|mold|pad|position|recl|source|stat|status|unit)\\b(?=\\s*=)/i'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|=>|==|\\/=|<=|>=|\\/\\/|\\*\\*|\\.(?:and|eq|eqv|ge|gt|le|lt|ne|neqv|not|or)\\.|[{}()[\\];,.+*\\/%=!<>?:&|^-])/i'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeFSharp(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\(\\*[\\s\\S]*?\\*\\)/'],
            ['comment', '/^\\/\\/\\/[^\\n]*/'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^(?:\\$@|@\\$|\\$|@)?"""[\\s\\S]*?"""/'],
            ['string', '/^(?:\\$@|@\\$|\\$|@)?"(?:""|\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^\\[<[^>\\n]+>\\]/'],
            ['keyword', '/^\\b(?:abstract|and|as|assert|async|base|begin|class|default|delegate|do|done|downcast|elif|else|end|exception|extern|finally|for|fun|function|global|if|in|inherit|inline|interface|internal|lazy|let|match|member|module|mutable|namespace|new|not|of|open|or|override|private|public|rec|return|select|static|struct|then|to|try|type|upcast|use|val|void|when|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:Choice1Of2|Choice2Of2|Error|None|Ok|Some|false|null|true)\\b/'],
            ['datatype', '/^\\b(?:Async|Choice|DateOnly|DateTime|Dictionary|Guid|JsonSerializer|Map|Option|Result|Seq|Set|String|Task|TimeOnly|Uri|bool|byte|char|decimal|double|float|float32|int|int16|int32|int64|list|obj|option|sbyte|seq|string|uint16|uint32|uint64|unit)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)(?:UL|ul|[fFmMlLyYuUnNsS])?\\b/'],
            ['attribute', '/^\\b[A-Z][A-Za-z0-9_\']*(?=\\s*:)/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_\']*(?=\\s*(?:[<({\\[.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_\']*(?=\\s*(?:<[^>\\n]+>\\s*)?\\()/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_\']*(?=\\s+[A-Za-z_(])/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_\']*\\b/'],
            ['operator', '/^(?:\\[<|>\\]|\\{\\||\\|\\}|::|:=|->|<-|=>|\\|>|>>|<<|\\?\\?|\\?\\.|==|<>|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:&|^~@-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJson(string $code): array
    {
        return $this->scan($code, [
            ['attribute', '/^"(?:\\\\.|[^"\\\\])*"(?=\\s*:)/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['number', '/^-?\\b(?:0|[1-9]\\d*)(?:\\.\\d+)?(?:[eE][+-]?\\d+)?\\b/'],
            ['operator', '/^[{}[\\]:,]/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJsonWithComments(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\/\/[^\n]*/'],
            ['comment', '/^\/\*[\s\S]*?\*\//'],
            ['attribute', '/^"(?:\\\\.|[^"\\\\])*"(?=\\s*:)/s'],
            ['attribute', "/^'(?:\\\\.|[^'\\\\])*'(?=\\s*:)/s"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['constant', '/^\b(?:false|null|true|Infinity|NaN)\b/i'],
            ['number', '/^[+-]?(?:0[xX][0-9A-Fa-f]+|(?:\d+\.\d*|\.\d+|\d+)(?:[eE][+-]?\d+)?)\b/'],
            ['attribute', '/^[A-Za-z_$][A-Za-z0-9_$-]*(?=\s*:)/'],
            ['operator', '/^[{}[\]:,]/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeBibtex(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $this->tokenizeBibtexLine($line, $tokens);
            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeBibtexLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(%.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(@[A-Za-z][A-Za-z0-9_-]*)([ \t]*)([({])([A-Za-z0-9_:.\\/-]+)?(,?)(.*)$/', $line, $matches) === 1) {
            $entryType = strtolower($matches[2]);
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'keyword', $matches[2]);
            $this->appendToken($tokens, 'text', $matches[3]);
            $this->appendToken($tokens, 'operator', $matches[4]);

            if ($entryType === '@string' || $entryType === '@preamble' || $entryType === '@comment') {
                $this->scanInto($matches[5] . $matches[6] . $matches[7], self::bibtexFieldPatterns(), $tokens);
                return;
            }

            if (($matches[5] ?? '') !== '') {
                $this->appendToken($tokens, 'variable', $matches[5]);
            }
            if (($matches[6] ?? '') !== '') {
                $this->appendToken($tokens, 'operator', $matches[6]);
            }
            $this->scanInto($matches[7], self::bibtexFieldPatterns(), $tokens);
            return;
        }

        $this->scanInto($line, self::bibtexFieldPatterns(), $tokens);
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private static function bibtexFieldPatterns(): array
    {
        return [
            ['comment', '/^%[^\\n]*/'],
            ['attribute', '/^[A-Za-z][A-Za-z0-9_-]*(?=\\s*=)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', '/^\\{(?:\\\\.|[^{}\\\\]|\\{(?:\\\\.|[^{}\\\\])*\\})*\\}/s'],
            ['keyword', '/^\\b(?:and)\\b/i'],
            ['constant', '/^\\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\\b/i'],
            ['number', '/^\\b\\d{1,4}\\b/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_:.\\/-]*/'],
            ['operator', '/^(?:#|[{}()[\\],=])/'],
        ];
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeDelimitedText(string $code, string $delimiter): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);
        $headerPending = true;

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $isDataLine = trim($line) !== '' && preg_match('/^[ \t]*#/', $line) !== 1;
            $this->tokenizeDelimitedLine($line, $delimiter, $headerPending && $isDataLine, $tokens);
            if ($isDataLine) {
                $headerPending = false;
            }

            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeDelimitedLine(string $line, string $delimiter, bool $isHeader, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(#.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        $offset = 0;
        $length = strlen($line);
        $delimiterLength = strlen($delimiter);

        while ($offset < $length) {
            if (substr($line, $offset, $delimiterLength) === $delimiter) {
                $this->appendToken($tokens, 'operator', $delimiter);
                $offset += $delimiterLength;
                continue;
            }

            if ($line[$offset] === '"') {
                $end = $offset + 1;
                while ($end < $length) {
                    if ($line[$end] !== '"') {
                        $end++;
                        continue;
                    }

                    if (($line[$end + 1] ?? '') === '"') {
                        $end += 2;
                        continue;
                    }

                    $end++;
                    break;
                }

                $this->appendToken($tokens, 'string', substr($line, $offset, $end - $offset));
                $offset = $end;
                continue;
            }

            $nextDelimiter = strpos($line, $delimiter, $offset);
            $fieldEnd = $nextDelimiter === false ? $length : $nextDelimiter;
            $this->appendDelimitedField(substr($line, $offset, $fieldEnd - $offset), $isHeader, $tokens);
            $offset = $fieldEnd;
        }
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendDelimitedField(string $field, bool $isHeader, array &$tokens): void
    {
        if ($field === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(.*?)([ \t]*)$/', $field, $matches) !== 1) {
            $this->appendToken($tokens, 'text', $field);
            return;
        }

        $this->appendToken($tokens, 'text', $matches[1]);
        $value = (string) $matches[2];
        if ($value !== '') {
            if ($isHeader) {
                $this->appendToken($tokens, 'attribute', $value);
            } elseif (preg_match('/^(?:true|false|null|yes|no)$/i', $value) === 1) {
                $this->appendToken($tokens, 'constant', $value);
            } elseif (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $value) === 1) {
                $this->appendToken($tokens, 'number', $value);
            } else {
                $this->appendToken($tokens, 'variable', $value);
            }
        }
        $this->appendToken($tokens, 'text', $matches[3]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeGraphql(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['keyword', '/^\\b(?:directive|enum|extend|fragment|implements|input|interface|mutation|on|query|scalar|schema|subscription|type|union)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:Boolean|Float|ID|Int|String)\\b/'],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_]*/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\s*:)/'],
            ['number', '/^-?\\b(?:0|[1-9]\\d*)(?:\\.\\d+)?(?:[eE][+-]?\\d+)?\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[!\\[\\]{}()|]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|[{}()[\\]:,|=!.@])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeGroovy(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#![^\\n]*/'],
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^\\$\\/[\\s\\S]*?\\/\\$/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', "/^'''[\\s\\S]*?'''/"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['string', '/^\\/(?:\\\\.|\\[[^\\]\\n]*(?:\\\\.[^\\]\\n]*)*\\]|[^\\/\\\\\\n])+\\/[imsux]*/'],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:abstract|as|assert|break|case|catch|class|continue|def|default|do|else|enum|extends|finally|for|goto|if|implements|import|in|instanceof|interface|native|new|package|return|super|switch|this|throw|throws|trait|try|var|while)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:BigDecimal|BigInteger|Boolean|Closure|Double|File|Float|Integer|JsonBuilder|JsonSlurper|List|Long|Map|Object|Pattern|Set|String|URL|boolean|byte|char|double|float|int|long|short|void)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|0[0-7](?:_?[0-7])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)[gGiIlLfFdD]?\\b/'],
            ['function', '/^\\b(?:archiveArtifacts|echo|error|fileExists|findFiles|getProperty|junit|library|node|parallel|pipeline|readFile|readJSON|sh|stage|stash|steps|timeout|trim|unstash|writeFile|writeJSON)\\b(?=\\s*(?:\\(|\\{|["\']|[A-Za-z_]|$))/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\s*:)/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\?\\.|\\*\\.|\\.&|\\.\\.<?|<=>|==~|=~|=>|::|\\?\\:|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|<<=?|>>=?|[{}()[\\];,.+*\\/%=!<>?:&|^~@-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCrystal(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', "/^'''[\\s\\S]*?'''/"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['attribute', '/^@\\[[^\\]\\n]*(?:\\][ \\t]*@\\[[^\\]\\n]*)*\\]/'],
            ['keyword', '/^\\b(?:abstract|alias|annotation|as|asm|begin|break|case|class|def|do|else|elsif|end|ensure|enum|extend|for|fun|getter|if|in|include|lib|macro|module|next|of|out|private|property|protected|require|rescue|return|select|self|setter|struct|super|then|type|typeof|unless|until|when|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|nil|true|STDERR|STDIN|STDOUT)\\b/'],
            ['datatype', '/^\\b(?:Array|Bool|Bytes|Char|Deque|Exception|Float32|Float64|Hash|Int8|Int16|Int32|Int64|JSON|Nil|Number|Set|Slice|StaticArray|String|Symbol|Time|Tuple|UInt8|UInt16|UInt32|UInt64|Union)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)(?:_[iu](?:8|16|32|64)|_f(?:32|64))?\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.:]|::|\\b))/'],
            ['function', '/^\\b(?:empty\\?|from_json|parse|puts|strip|to_json|try)(?=\\s|\\)|\\(|\\{|$|["\'])/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_!?]*(?=\\s*\\()/'],
            ['variable', '/^@[A-Za-z_][A-Za-z0-9_]*/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_!?]*\\b/'],
            ['operator', '/^(?:::|=>|->|\\?\\.|\\?\\?|\\?=|&\\.|\\.\\.\\.?|==|!=|<=|>=|&&|\\|\\||\\+=|-=|\\*=|\\/=|%=|[{}()[\\];,.+*\\/%=!<>?:&|^~@-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeHcl(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^<<-?([A-Za-z_][A-Za-z0-9_]*)[\\s\\S]*?(?:\\r?\\n)[ \\t]*\\1\\b/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['keyword', '/^\\b(?:backend|check|data|dynamic|ephemeral|import|locals|module|moved|output|provider|provider_meta|provisioner|removed|resource|terraform|validation|variable)\\b/'],
            ['keyword', '/^\\b(?:content|for|for_each|if|in|lifecycle|postcondition|precondition)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:any|bool|list|map|number|object|optional|set|string|tuple)\\b/'],
            ['function', '/^\\b(?:abspath|alltrue|anytrue|basename|can|chomp|chunklist|cidrhost|cidrnetmask|cidrsubnet|cidrsubnets|coalesce|compact|concat|contains|csvdecode|dirname|distinct|element|endswith|ephemeralasnull|file|fileset|flatten|format|issensitive|join|jsondecode|jsonencode|keys|length|lookup|lower|merge|nonsensitive|one|range|regex|replace|sensitive|setproduct|sort|split|startswith|strcontains|substr|templatefile|timeadd|timestamp|toset|trimspace|try|upper|values|yamldecode|yamlencode|zipmap)\\b(?=\\s*\\()/'],
            ['number', '/^-?\\b(?:0|[1-9]\\d*)(?:\\.\\d+)?\\b/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*=)/'],
            ['variable', '/^\\b(?:count|data|each|local|module|path|self|terraform|var)\\.[A-Za-z0-9_.-]+\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_-]*(?:\\.[A-Za-z_][A-Za-z0-9_-]*)+\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_-]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|=>|==|!=|<=|>=|&&|\\|\\||\\$\\{|[{}()[\\],.=+*\\/%!<>?:|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeYaml(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['attribute', '/^(?:[A-Za-z_][A-Za-z0-9_.-]*|"[^"\\n]+"|\'[^\'\\n]+\')(?=\\s*:)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['constant', '/^\\b(?:false|null|true|yes|no)\\b/i'],
            ['number', '/^-?\\b(?:0|[1-9]\\d*)(?:\\.\\d+)?\\b/'],
            ['operator', '/^(?:---|\\.\\.\\.|[\\[\\]{}:,.&*|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeIni(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $this->tokenizeIniLine($line, $tokens);
            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeIniLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)([;#].*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(\[[^\]\r]*\])(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'keyword', $matches[2]);
            $this->appendIniTrailingText($matches[3], $tokens);
            return;
        }

        $assignmentOffset = strpos($line, '=');
        if ($assignmentOffset === false) {
            $this->appendToken($tokens, 'text', $line);
            return;
        }

        $key = substr($line, 0, $assignmentOffset);
        if (preg_match('/^([ \t]*)(.*?)([ \t]*)$/', $key, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            if ($matches[2] !== '') {
                $this->appendToken($tokens, 'datatype', $matches[2]);
            }
            $this->appendToken($tokens, 'text', $matches[3]);
        } else {
            $this->appendToken($tokens, 'datatype', $key);
        }

        $this->appendToken($tokens, 'operator', '=');
        $this->appendIniValue(substr($line, $assignmentOffset + 1), $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendIniTrailingText(string $text, array &$tokens): void
    {
        if ($text === '') {
            return;
        }

        if (preg_match('/^([ \t]+)([;#].*)$/', $text, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        $this->appendToken($tokens, 'text', $text);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendIniValue(string $value, array &$tokens): void
    {
        if ($value === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(.*?)([ \t]*)$/', $value, $matches) !== 1) {
            $this->appendToken($tokens, 'string', $value);
            return;
        }

        $this->appendToken($tokens, 'text', $matches[1]);
        $body = $matches[2];
        if ($body !== '') {
            if (self::isIniNumber($body)) {
                $this->appendToken($tokens, 'number', $body);
            } elseif (self::isIniPhpErrorConstantNegation($body)) {
                $this->appendToken($tokens, 'operator', '~');
                $this->appendToken($tokens, 'keyword', substr($body, 1));
            } elseif (self::isIniKeyword($body)) {
                $this->appendToken($tokens, 'keyword', $body);
            } else {
                $this->appendToken($tokens, 'string', $body);
            }
        }
        $this->appendToken($tokens, 'text', $matches[3]);
    }

    private static function isIniNumber(string $value): bool
    {
        return preg_match('/^-?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $value) === 1;
    }

    private static function isIniPhpErrorConstantNegation(string $value): bool
    {
        $constant = substr($value, 1);

        return str_starts_with($value, '~')
            && str_starts_with(strtolower($constant), 'e_')
            && self::isIniKeyword($constant);
    }

    private static function isIniKeyword(string $value): bool
    {
        static $keywords = [
            'default',
            'defaults',
            'e_all',
            'e_compile_error',
            'e_compile_warning',
            'e_core_error',
            'e_core_warning',
            'e_error',
            'e_notice',
            'e_parse',
            'e_strict',
            'e_user_error',
            'e_user_notice',
            'e_user_warning',
            'e_warning',
            'false',
            'localhost',
            'no',
            'normal',
            'null',
            'off',
            'on',
            'true',
            'yes',
        ];

        return in_array(strtolower($value), $keywords, true);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeToml(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $this->tokenizeTomlLine($line, $tokens);
            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeTomlLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(#.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        $tableHeader = self::tomlTableHeaderLine($line);
        if ($tableHeader !== null) {
            $this->appendToken($tokens, 'text', $tableHeader['prefix']);
            $this->appendTomlTableHeader($tableHeader['header'], $tableHeader['body'], $tokens);
            $this->appendTomlTrailingText($tableHeader['trailing'], $tokens);
            return;
        }

        $assignmentOffset = self::tomlAssignmentOffset($line);
        if ($assignmentOffset === null) {
            $this->appendTomlValue($line, $tokens);
            return;
        }

        $key = substr($line, 0, $assignmentOffset);
        $value = substr($line, $assignmentOffset + 1);
        if (preg_match('/^([ \t]*)(.*?)([ \t]*)$/', $key, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            if ($matches[2] !== '') {
                $this->appendTomlKey($matches[2], $tokens);
            }
            $this->appendToken($tokens, 'text', $matches[3]);
        } else {
            $this->appendTomlKey($key, $tokens);
        }

        $this->appendToken($tokens, 'operator', '=');
        $this->appendTomlValue($value, $tokens);
    }

    /**
     * @return array{prefix:string, header:string, body:string, trailing:string}|null
     */
    private static function tomlTableHeaderLine(string $line): ?array
    {
        if (preg_match('/^([ \t]*)(\[.*)$/', $line, $matches) !== 1) {
            return null;
        }

        $prefix = $matches[1];
        $rest = $matches[2];
        $arrayHeader = str_starts_with($rest, '[[');
        $openLength = $arrayHeader ? 2 : 1;
        $close = $arrayHeader ? ']]' : ']';
        $quote = '';
        $length = strlen($rest);

        for ($offset = $openLength; $offset < $length; $offset++) {
            $char = $rest[$offset];
            if ($quote !== '') {
                if ($char === '\\' && $quote === '"' && $offset + 1 < $length) {
                    $offset++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if (substr($rest, $offset, strlen($close)) !== $close) {
                continue;
            }

            $body = substr($rest, $openLength, $offset - $openLength);
            if (trim($body) === '') {
                return null;
            }

            $trailing = substr($rest, $offset + strlen($close));
            if ($trailing !== '' && preg_match('/^[ \t]*(?:#.*)?$/', $trailing) !== 1) {
                return null;
            }

            return [
                'prefix' => $prefix,
                'header' => substr($rest, 0, $offset + strlen($close)),
                'body' => $body,
                'trailing' => $trailing,
            ];
        }

        return null;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendTomlTableHeader(string $header, string $body, array &$tokens): void
    {
        if (preg_match('/^\[\[?[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\]?\]$/', $header) === 1) {
            $this->appendToken($tokens, 'keyword', $header);
            return;
        }

        $arrayHeader = str_starts_with($header, '[[');
        $this->appendToken($tokens, 'operator', $arrayHeader ? '[[' : '[');
        $this->appendTomlKey($body, $tokens);
        $this->appendToken($tokens, 'operator', $arrayHeader ? ']]' : ']');
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendTomlTrailingText(string $text, array &$tokens): void
    {
        if ($text === '') {
            return;
        }

        if (preg_match('/^([ \t]+)(#.*)$/', $text, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        $this->appendToken($tokens, 'text', $text);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendTomlKey(string $key, array &$tokens): void
    {
        $this->scanInto($key, [
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^\\./'],
            ['datatype', '/^[A-Za-z0-9_-]+/'],
        ], $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendTomlValue(string $value, array &$tokens): void
    {
        if ($value === '') {
            return;
        }

        $this->scanInto($value, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', "/^'''[\\s\\S]*?'''/"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['constant', '/^\\b\\d{4}-\\d{2}-\\d{2}(?:[T ]\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})?)?\\b/'],
            ['constant', '/^\\b\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^[+-]?(?:0[xX][0-9A-Fa-f]+(?:_[0-9A-Fa-f]+)*|0[oO][0-7]+(?:_[0-7]+)*|0[bB][01]+(?:_[01]+)*|(?:\\d+(?:_\\d+)*)(?:\\.\\d+(?:_\\d+)*)?(?:[eE][+-]?\\d+(?:_\\d+)*)?|inf|nan)\\b/i'],
            ['datatype', '/^[A-Za-z0-9_-]+(?=\\s*=)/'],
            ['operator', '/^[\\[\\]{},=]/'],
            ['operator', '/^\\./'],
            ['variable', '/^[A-Za-z0-9_-]+/'],
        ], $tokens);
    }

    private static function tomlAssignmentOffset(string $line): ?int
    {
        $quote = null;
        $length = strlen($line);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $line[$offset];
            if ($quote !== null) {
                if ($char === '\\' && $quote === '"' && $offset + 1 < $length) {
                    $offset++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '#') {
                return null;
            }

            if ($char === '=') {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeHtml(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $match = self::nextHtmlRawTextTag($code, $offset);
            if ($match === null) {
                $this->scanHtmlFragment(substr($code, $offset), $tokens);
                break;
            }

            [$tag, $tagOffset] = $match;
            if ($tagOffset > $offset) {
                $this->scanHtmlFragment(substr($code, $offset, $tagOffset - $offset), $tokens);
            }

            $openingEnd = self::htmlTagEndOffset($code, $tagOffset);
            if ($openingEnd === null) {
                $this->scanHtmlFragment(substr($code, $tagOffset), $tokens);
                break;
            }

            $this->scanHtmlFragment(substr($code, $tagOffset, $openingEnd - $tagOffset + 1), $tokens);
            $contentOffset = $openingEnd + 1;
            $closing = self::htmlClosingRawTextTag($code, $tag, $contentOffset);
            if ($closing === null) {
                $this->appendEmbeddedHtmlTokens($tag, substr($code, $contentOffset), $tokens);
                break;
            }

            [$closingOffset, $closingLength] = $closing;
            $this->appendEmbeddedHtmlTokens($tag, substr($code, $contentOffset, $closingOffset - $contentOffset), $tokens);
            $this->scanHtmlFragment(substr($code, $closingOffset, $closingLength), $tokens);
            $offset = $closingOffset + $closingLength;
        }

        return $tokens;
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeVue(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $match = self::nextVueRawTextTag($code, $offset);
            if ($match === null) {
                $this->scanVueFragment(substr($code, $offset), $tokens);
                break;
            }

            [$tag, $tagOffset] = $match;
            if ($tagOffset > $offset) {
                $this->scanVueFragment(substr($code, $offset, $tagOffset - $offset), $tokens);
            }

            $openingEnd = self::htmlTagEndOffset($code, $tagOffset);
            if ($openingEnd === null) {
                $this->scanVueFragment(substr($code, $tagOffset), $tokens);
                break;
            }

            $openingTag = substr($code, $tagOffset, $openingEnd - $tagOffset + 1);
            $this->scanVueFragment($openingTag, $tokens);
            $contentOffset = $openingEnd + 1;
            $closing = self::htmlClosingRawTextTag($code, $tag, $contentOffset);
            if ($closing === null) {
                $this->appendEmbeddedVueRawTextTokens($tag, $openingTag, substr($code, $contentOffset), $tokens);
                break;
            }

            [$closingOffset, $closingLength] = $closing;
            $this->appendEmbeddedVueRawTextTokens($tag, $openingTag, substr($code, $contentOffset, $closingOffset - $contentOffset), $tokens);
            $this->scanVueFragment(substr($code, $closingOffset, $closingLength), $tokens);
            $offset = $closingOffset + $closingLength;
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendAsciiDocListingLine(string $line, ?string $language, array &$tokens): void
    {
        if ($language === null) {
            $this->appendToken($tokens, 'datatype', $line);
            return;
        }

        foreach ($this->tokenize($line, $language) as $token) {
            $this->appendToken($tokens, $token['type'], $token['text']);
        }
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function scanVueFragment(string $code, array &$tokens): void
    {
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $interpolationOffset = strpos($code, '{{', $offset);
            if ($interpolationOffset === false) {
                $this->scanPlainVueFragment(substr($code, $offset), $tokens);
                break;
            }

            if ($interpolationOffset > $offset) {
                $this->scanPlainVueFragment(substr($code, $offset, $interpolationOffset - $offset), $tokens);
            }

            $end = strpos($code, '}}', $interpolationOffset + 2);
            if ($end === false) {
                $this->appendToken($tokens, 'operator', substr($code, $interpolationOffset, 2));
                $this->appendEmbeddedVueExpressionTokens(substr($code, $interpolationOffset + 2), $tokens);
                break;
            }

            $this->appendToken($tokens, 'operator', '{{');
            $this->appendEmbeddedVueExpressionTokens(substr($code, $interpolationOffset + 2, $end - $interpolationOffset - 2), $tokens);
            $this->appendToken($tokens, 'operator', '}}');
            $offset = $end + 2;
        }
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function scanPlainVueFragment(string $code, array &$tokens): void
    {
        $this->scanInto($code, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['function', '/^<\\/?[A-Z][A-Za-z0-9:.-]*/'],
            ['keyword', '/^<\\/?[A-Za-z][A-Za-z0-9:.-]*/'],
            ['attribute', '/^(?:v-[A-Za-z][A-Za-z0-9:.-]*|[:@#][A-Za-z_][A-Za-z0-9_.:-]*)(?=\\s*=|\\s|\\/?>)/'],
            ['attribute', '/^\\b(?:async|defer|disabled|multiple|readonly|required|scoped|selected|setup)\\b(?=\\s|\\/?>)/'],
            ['attribute', '/^[A-Za-z_:][A-Za-z0-9_.:-]*(?=\\s*=)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^\\/?>|^=/'],
        ], $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendEmbeddedVueExpressionTokens(string $code, array &$tokens): void
    {
        foreach ($this->tokenizeJavaScript($code) as $token) {
            $this->appendToken($tokens, $token['type'], $token['text']);
        }
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendEmbeddedVueRawTextTokens(string $tag, string $openingTag, string $code, array &$tokens): void
    {
        $language = self::vueEmbeddedLanguage($tag, $openingTag);
        $embedded = match ($language) {
            'css' => $this->tokenizeCss($code),
            'json' => $this->tokenizeJson($code),
            'jsonc' => $this->tokenizeJsonWithComments($code),
            'less' => $this->tokenizeLess($code),
            'markdown' => $this->tokenizeMarkdown($code),
            'sass', 'scss' => $this->tokenizeScss($code),
            'typescript' => $this->tokenizeTypeScript($code),
            'yaml' => $this->tokenizeYaml($code),
            default => $this->tokenizeJavaScript($code),
        };

        foreach ($embedded as $token) {
            $this->appendToken($tokens, $token['type'], $token['text']);
        }
    }

    private static function vueEmbeddedLanguage(string $tag, string $openingTag): string
    {
        $tag = strtolower($tag);
        $lang = self::vueLangAttribute($openingTag);

        if ($tag === 'style') {
            if (in_array($lang, ['less', 'sass', 'scss'], true)) {
                return $lang;
            }

            return 'css';
        }

        if ($tag === 'script') {
            return in_array($lang, ['ts', 'typescript', 'tsx'], true) ? 'typescript' : 'javascript';
        }

        if ($tag === 'i18n') {
            if (in_array($lang, ['yaml', 'yml'], true)) {
                return 'yaml';
            }

            if (in_array($lang, ['json5', 'jsonc', 'json-with-comments'], true)) {
                return 'jsonc';
            }

            return 'json';
        }

        if ($tag === 'route') {
            if (in_array($lang, ['yaml', 'yml'], true)) {
                return 'yaml';
            }

            if (in_array($lang, ['json5', 'jsonc', 'json-with-comments'], true)) {
                return 'jsonc';
            }

            return 'json';
        }

        if ($tag === 'docs') {
            if (in_array($lang, ['yaml', 'yml'], true)) {
                return 'yaml';
            }

            if (in_array($lang, ['json', 'json5', 'jsonc', 'json-with-comments'], true)) {
                return in_array($lang, ['json5', 'jsonc', 'json-with-comments'], true) ? 'jsonc' : 'json';
            }

            return 'markdown';
        }

        return 'javascript';
    }

    private static function vueLangAttribute(string $openingTag): string
    {
        if (preg_match('/\\blang\\s*=\\s*([\'"]?)([A-Za-z0-9_.+-]+)\\1/i', $openingTag, $matches) !== 1) {
            return '';
        }

        return strtolower(str_replace('_', '-', $matches[2]));
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function scanHtmlFragment(string $code, array &$tokens): void
    {
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $phpIsland = self::nextHtmlPhpIsland($code, $offset);
            if ($phpIsland === null) {
                $this->scanPlainHtmlFragment(substr($code, $offset), $tokens);
                break;
            }

            [$phpOffset, $phpLength] = $phpIsland;
            if ($phpOffset > $offset) {
                $this->scanPlainHtmlFragment(substr($code, $offset, $phpOffset - $offset), $tokens);
            }

            $this->appendEmbeddedHtmlPhpTokens(substr($code, $phpOffset, $phpLength), $tokens);
            $offset = $phpOffset + $phpLength;
        }
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function scanPlainHtmlFragment(string $code, array &$tokens): void
    {
        $this->scanInto($code, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['keyword', '/^<\\/?[A-Za-z][A-Za-z0-9:-]*/'],
            ['attribute', '/^[A-Za-z_:][A-Za-z0-9_.:-]*(?=\\s*=)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^\\/?>|^=/'],
        ], $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendEmbeddedHtmlTokens(string $tag, string $code, array &$tokens): void
    {
        $embedded = $tag === 'style' ? $this->tokenizeCss($code) : $this->tokenizeJavaScript($code);
        foreach ($embedded as $token) {
            $this->appendToken($tokens, $token['type'], $token['text']);
        }
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendEmbeddedHtmlPhpTokens(string $code, array &$tokens): void
    {
        foreach ($this->tokenizePhp($code) as $token) {
            $this->appendToken($tokens, $token['type'], $token['text']);
        }
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private static function nextHtmlRawTextTag(string $code, int $offset): ?array
    {
        if (preg_match('/<(script|style)\\b/i', $code, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }

        return [strtolower($matches[1][0]), $matches[0][1]];
    }

    /**
     * @return array{0:string, 1:int}|null
     */
    private static function nextVueRawTextTag(string $code, int $offset): ?array
    {
        if (preg_match('/<(script|style|i18n|route|docs)\\b/i', $code, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }

        return [strtolower($matches[1][0]), $matches[0][1]];
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    private static function nextHtmlPhpIsland(string $code, int $offset): ?array
    {
        if (preg_match('/<\\?(?:php\\b|=)/i', $code, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }

        $start = $matches[0][1];
        $end = strpos($code, '?>', $start + strlen($matches[0][0]));
        if ($end === false) {
            return [$start, strlen($code) - $start];
        }

        return [$start, $end - $start + 2];
    }

    private static function htmlTagEndOffset(string $code, int $offset): ?int
    {
        $quote = '';
        $length = strlen($code);
        for ($index = $offset; $index < $length; $index++) {
            $char = $code[$index];
            if ($quote !== '') {
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{0:int, 1:int}|null
     */
    private static function htmlClosingRawTextTag(string $code, string $tag, int $offset): ?array
    {
        if (preg_match('/<\\/\\s*' . preg_quote($tag, '/') . '\\s*>/i', $code, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }

        return [$matches[0][1], strlen($matches[0][0])];
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeXml(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['string', '/^<!\\[CDATA\\[[\\s\\S]*?\\]\\]>/'],
            ['preprocessor', '/^<\\?[A-Za-z_][A-Za-z0-9_.:-]*/'],
            ['preprocessor', '/^<!(?:DOCTYPE|ELEMENT|ENTITY|ATTLIST|NOTATION)\\b/i'],
            ['keyword', '/^<\\/?[A-Za-z_][A-Za-z0-9_.:-]*/'],
            ['attribute', '/^(?:xmlns(?::[A-Za-z_][A-Za-z0-9_.:-]*)?|[A-Za-z_:][A-Za-z0-9_.:-]*)(?=\\s*=)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['constant', '/^&(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z_:][A-Za-z0-9_.:-]*);/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['operator', '/^(?:\\?>|\\/?>|=|\\[|\\])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTwig(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\{#[\\s\\S]*?#\\}/'],
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['operator', '/^\\{\\{[-~]?|^\\{%[-~]?|^[-~]?\\}\\}|^[-~]?%\\}/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['function', '/^\\b(?:abs|attribute|batch|block|capitalize|column|constant|convert_encoding|country_name|currency_name|cycle|date|date_modify|default|dump|escape|filter|first|format|function|include|join|json_encode|keys|last|length|lower|map|max|merge|min|nl2br|number_format|parent|random|range|reduce|replace|reverse|round|slice|sort|source|split|striptags|template_from_string|trim|upper|url_encode)(?=\\s*\\()/'],
            ['function', '/^\\b(?:e|escape|raw)(?=\\s*(?:[|,)}\\]\\s]|$))/'],
            ['keyword', '/^\\b(?:apply|autoescape|block|do|else|elseif|embed|endapply|endautoescape|endblock|endembed|endfilter|endfor|endif|endmacro|endsandbox|endset|endspaceless|endverbatim|extends|filter|flush|for|from|if|import|in|is|macro|only|sandbox|set|use|verbatim|with)\\b/'],
            ['constant', '/^\\b(?:false|null|none|true)\\b/i'],
            ['keyword', '/^<\\/?[A-Za-z][A-Za-z0-9:-]*/'],
            ['attribute', '/^(?:aria-[A-Za-z0-9_.:-]+|data-[A-Za-z0-9_.:-]+|alt|class|for|href|id|name|rel|role|src|style|target|title|type|value)(?=\\s*=(?!=))/i'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*:)/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_]*/'],
            ['operator', '/^(?:\\.\\.|==|!=|<=|>=|=>|\\?\\?|\\?:|\\bis\\s+not\\b|\\bnot\\s+in\\b|\\band\\b|\\bor\\b|\\bnot\\b|[{}()[\\].,:|=+*\\/%!<>?~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMustache(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $next = strpos($code, '{{', $offset);
            if ($next === false) {
                $this->scanMustacheHtml(substr($code, $offset), $tokens);
                break;
            }

            if ($next > $offset) {
                $this->scanMustacheHtml(substr($code, $offset, $next - $offset), $tokens);
            }

            $expression = self::consumeMustacheExpression($code, $next);
            $this->appendMustacheExpression($expression, $tokens);
            $offset = $next + strlen($expression);
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function scanMustacheHtml(string $code, array &$tokens): void
    {
        $this->scanInto($code, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['string', '/^<!\\[CDATA\\[[\\s\\S]*?\\]\\]>/'],
            ['preprocessor', '/^<\\?[A-Za-z_][A-Za-z0-9_.:-]*/'],
            ['preprocessor', '/^<!(?:DOCTYPE|ELEMENT|ENTITY|ATTLIST|NOTATION)\\b/i'],
            ['keyword', '/^<\\/?[A-Za-z][A-Za-z0-9:-]*/'],
            ['attribute', '/^(?:aria-[A-Za-z0-9_.:-]+|data-[A-Za-z0-9_.:-]+|[A-Za-z_:][A-Za-z0-9_.:-]*)(?=\\s*=)/i'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['constant', '/^&(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z_:][A-Za-z0-9_.:-]*);/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['operator', '/^(?:\\/?>|=|\\[|\\])/'],
        ], $tokens);
    }

    private static function consumeMustacheExpression(string $code, int $offset): string
    {
        foreach ([
            ['{{!--', '--}}'],
            ['{{{{', '}}}}'],
            ['{{{', '}}}'],
            ['{{!', '}}'],
            ['{{', '}}'],
        ] as [$open, $close]) {
            if (!str_starts_with(substr($code, $offset), $open)) {
                continue;
            }

            $end = strpos($code, $close, $offset + strlen($open));
            if ($end === false) {
                return substr($code, $offset);
            }

            return substr($code, $offset, $end - $offset + strlen($close));
        }

        return '{{';
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendMustacheExpression(string $expression, array &$tokens): void
    {
        if (str_starts_with($expression, '{{!--') || str_starts_with($expression, '{{!')) {
            $this->appendToken($tokens, 'comment', $expression);
            return;
        }

        [$open, $close] = match (true) {
            str_starts_with($expression, '{{{{') => ['{{{{', '}}}}'],
            str_starts_with($expression, '{{{') => ['{{{', '}}}'],
            default => ['{{', '}}'],
        };

        if (!str_ends_with($expression, $close)) {
            $this->appendToken($tokens, 'warning', $expression);
            return;
        }

        $this->appendToken($tokens, 'operator', $open);
        $inner = substr($expression, strlen($open), -strlen($close));
        $this->scanInto($inner, [
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^(?:else\\s+(?:if|unless|with|each(?:-in)?)|else|if|unless|with|each(?:-in)?|block|partial|yield)\\b/'],
            ['function', '/^(?:default|formatDate|include|link|log|lookup|t|translate|wp_kses_post)(?=\\s|\\()/'],
            ['attribute', '/^[A-Za-z_:\\*#\\(\\[][\\)\\]\\w.:_-]*(?=\\s*=)/'],
            ['constant', '/^(?:false|null|true|undefined)\\b/i'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^@?[A-Za-z_$:?\\x80-\\xff][A-Za-z0-9_$:?\\-\\x80-\\xff]*(?:\\.[A-Za-z_$:?\\x80-\\xff][A-Za-z0-9_$:?\\-\\x80-\\xff]*)*/'],
            ['operator', '/^(?:=>|==|!=|<=|>=|\\|\\||&&|[{}()[\\].,=|~#^\\/><&$*!?:+-])/'],
        ], $tokens);
        $this->appendToken($tokens, 'operator', $close);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeLiquid(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\{%[-~]?\\s*comment\\s*[-~]?%\\}[\\s\\S]*?\\{%[-~]?\\s*endcomment\\s*[-~]?%\\}/i'],
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['operator', '/^\\{\\{[-~]?|^\\{%[-~]?|^[-~]?\\}\\}|^[-~]?%\\}/'],
            ['keyword', '/^<\\/?[A-Za-z][A-Za-z0-9:-]*/'],
            ['attribute', '/^(?:aria-[A-Za-z0-9_.:-]+|data-[A-Za-z0-9_.:-]+|[A-Za-z_:][A-Za-z0-9_.:-]*)(?=\\s*=)/i'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:assign|break|capture|case|comment|continue|cycle|decrement|echo|else|elsif|endcase|endcapture|endcomment|endfor|endif|endpaginate|endraw|endschema|endstyle|endtablerow|for|if|include|increment|layout|liquid|paginate|raw|render|schema|section|style|tablerow|unless|when|with)\\b/i'],
            ['function', '/^\\b(?:append|asset_url|at_least|at_most|capitalize|compact|concat|date|default|divided_by|downcase|escape|escape_once|first|handle|handleize|image_url|join|json|last|map|minus|modulo|newline_to_br|plus|prepend|remove|replace|reverse|size|slice|sort|split|strip|strip_html|strip_newlines|times|truncate|truncatewords|uniq|upcase|where)\\b(?=\\s*(?:[|:,)}\\]\\s]|$))/i'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*:)/'],
            ['constant', '/^\\b(?:blank|empty|false|nil|null|true)\\b/i'],
            ['operator', '/^\\b(?:and|contains|or)\\b/i'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_-]*(?:\\.[A-Za-z_][A-Za-z0-9_-]*)*/'],
            ['operator', '/^(?:==|!=|<=|>=|\\.\\.|[{}()[\\].,:|=+*\\/%!<>?~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMermaid(string $code): array
    {
        return $this->scan($code, [
            ['preprocessor', '/^%%\\{[\\s\\S]*?\\}%%/'],
            ['comment', '/^%%[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:flowchart|graph|sequenceDiagram|classDiagram|stateDiagram(?:-v2)?|erDiagram|journey|gantt|pie|gitGraph|mindmap|timeline|quadrantChart|requirementDiagram|C4Context|sankey-beta)\\b/i'],
            ['constant', '/^\\b(?:BT|LR|RL|TB|TD)\\b/'],
            ['keyword', '/^\\b(?:accTitle|accDescr|activate|actor|alt|and|as|autonumber|class|classDef|click|critical|dateFormat|deactivate|else|end|exclude|gitGraph|loop|note|opt|over|participant|par|rect|section|style|subgraph|title|todayMarker)\\b/i'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*:)/'],
            ['string', '/^\\[[^\\]\\n]*\\]/'],
            ['string', '/^\\([^()\\n]*\\)/'],
            ['string', '/^\\{[^}\\n]*\\}/'],
            ['string', '/^\\|[^|\\n]*\\|/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/i'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_-]*\\b/'],
            ['operator', '/^(?:<-->|<--|-->|---|==>|===|-.->|-\\.->|--[ox]|[ox]--|\\|>|::|[{}()[\\];,.+*\\/%=!<>?:&|#~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeSql(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^--[^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['string', '/^\\$\\$[\\s\\S]*?\\$\\$/'],
            ['string', '/^\\$([A-Za-z_][A-Za-z0-9_]*)\\$[\\s\\S]*?\\$\\1\\$/'],
            ['attribute', '/^"(?:[^"\\\\]|\\\\.)*"/s'],
            ['attribute', '/^`(?:``|[^`])*`/'],
            ['attribute', '/^\\[(?:]]|[^\\]])*\\]/'],
            ['constant', '/^\\b(?:current_date|current_time|current_timestamp|false|null|true)\\b/i'],
            ['datatype', '/^\\b(?:bigint|binary|blob|bool|boolean|char|date|datetime|decimal|double|float|int|integer|json|jsonb|longblob|longtext|mediumblob|mediumint|mediumtext|numeric|plpgsql|real|record|regclass|serial|smallint|text|time|timestamp|tinyblob|tinyint|tinytext|trigger|uuid|varchar|void)\\b/i'],
            ['keyword', '/^\\b(?:add|alter|and|as|asc|auto_increment|before|begin|between|by|cascade|case|check|collate|column|commit|conflict|constraint|create|declare|default|delete|desc|distinct|do|drop|duplicate|each|else|end|escape|execute|exists|for|foreign|from|function|group|having|if|in|index|inner|insert|into|is|join|key|language|left|like|limit|not|notice|offset|on|or|order|outer|perform|primary|raise|references|replace|returning|returns|right|rollback|row|select|set|start|table|then|transaction|trigger|unique|unsigned|update|using|values|vacuum|when|where|with|without)\\b/i'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^(?::[A-Za-z_][A-Za-z0-9_]*|@[A-Za-z_][A-Za-z0-9_]*|\\$\\d+)\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:<>|!=|<=|>=|==|\\|\\||::|:=|[(),;.*=<>+\\/%?-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJavaScript(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['comment', '/^#![^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^\\/(?:\\\\.|\\[[^\\]\\n]*(?:\\\\.[^\\]\\n]*)*\\]|[^\\/\\\\\\n])+\\/[dgimsuvy]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:as|async|await|break|case|catch|class|const|continue|debugger|default|delete|do|else|export|extends|finally|for|from|function|get|if|import|in|instanceof|let|new|of|return|set|static|switch|this|throw|try|typeof|var|void|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true|undefined)\\b/'],
            ['datatype', '/^\\b(?:Array|ArrayBuffer|BigInt|Boolean|Buffer|Console|Date|Document|Element|Error|Event|FormData|Function|HTMLElement|Intl|JSON|Map|Math|Number|Object|Promise|Proxy|Reflect|RegExp|Set|String|Symbol|URL|WeakMap|WeakSet|Window|XMLHttpRequest|console|document|global|globalThis|process|window)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|0[oO][0-7]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?n?)\\b/'],
            ['attribute', '/^[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*:)/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*\\b/'],
            ['operator', '/^(?:=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\?\\?|\\?\\.|\\.\\.\\.|\\+\\+|--|[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJsx(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['function', '/^<\\/?[A-Z][A-Za-z0-9_.$:-]*/'],
            ['keyword', '/^<\\/?[a-z][A-Za-z0-9_.$:-]*/'],
            ['attribute', '/^[A-Za-z_:][A-Za-z0-9_.:-]*(?=\\s*=)/'],
            ['keyword', '/^\\b(?:async|await|break|case|catch|class|const|continue|default|do|else|export|extends|finally|for|from|function|if|import|let|new|return|switch|throw|try|typeof|var|while|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true|undefined)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_$]*(?=\\s*(?:[<({.]|\\b))/'],
            ['variable', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*\\b/'],
            ['operator', '/^(?:<\\/>|\\/?>|=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\.\\.\\.|[{}()[\\];,.+*\\/%=!<>?:|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTsx(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^<(?=[A-Z][A-Za-z0-9_$]*>\\s*[),=>])/'],
            ['function', '/^<\\/?[A-Z][A-Za-z0-9_.$:-]*/'],
            ['keyword', '/^<\\/?[a-z][A-Za-z0-9_.$:-]*/'],
            ['attribute', '/^@[A-Za-z_$][A-Za-z0-9_$]*/'],
            ['keyword', '/^\\b(?:abstract|as|asserts|async|await|break|case|catch|class|const|constructor|continue|declare|default|do|else|enum|export|extends|finally|for|from|function|get|if|implements|import|in|infer|interface|is|keyof|let|module|namespace|new|of|private|protected|public|readonly|return|satisfies|set|static|switch|this|throw|try|type|typeof|var|while|yield)\\b/'],
            ['datatype', '/^\\b(?:any|bigint|boolean|never|null|number|object|string|symbol|undefined|unknown|void)\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_$]*(?=\\s*(?:[<:={}\\/.]|\\b))/'],
            ['attribute', '/^[A-Za-z_:][A-Za-z0-9_.:-]*(?=\\s*=)/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*\\b/'],
            ['operator', '/^(?:<\\/>|\\/?>|=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\?\\?|\\?\\.|\\.\\.\\.|[{}()[\\];,.+*\\/%=!<>?:&|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTypeScript(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['attribute', '/^@[A-Za-z_$][A-Za-z0-9_$]*/'],
            ['keyword', '/^\\b(?:abstract|as|asserts|async|await|break|case|catch|class|const|constructor|continue|declare|default|do|else|enum|export|extends|finally|for|from|function|get|if|implements|import|in|infer|interface|is|keyof|let|module|namespace|new|of|private|protected|public|readonly|return|satisfies|set|static|switch|this|throw|try|type|typeof|var|while|yield)\\b/'],
            ['datatype', '/^\\b(?:any|bigint|boolean|never|null|number|object|string|symbol|undefined|unknown|void)\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_$]*(?=\\s*(?:[<:={]|\\b))/'],
            ['function', '/^\\b[a-z_$][A-Za-z0-9_$]*(?=\\s*<[^>\\n]+>\\s*\\()/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*\\b/'],
            ['operator', '/^(?:=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\?\\?|\\?\\.|\\.\\.\\.|[{}()[\\];,.+*\\/%=!<>?:|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePython(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^(?:fr|rf|br|rb|r|u|f|b)?"""[\\s\\S]*?"""/i'],
            ['string', "/^(?:fr|rf|br|rb|r|u|f|b)?'''[\\s\\S]*?'''/i"],
            ['string', '/^(?:fr|rf|br|rb|r|u|f|b)?"(?:\\\\.|[^"\\\\])*"/i'],
            ['string', "/^(?:fr|rf|br|rb|r|u|f|b)?'(?:\\\\.|[^'\\\\])*'/i"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:and|as|assert|async|await|break|case|class|continue|def|del|elif|else|except|finally|for|from|global|if|import|in|is|lambda|match|nonlocal|not|or|pass|raise|return|try|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:False|None|True)\\b/'],
            ['datatype', '/^\\b(?:bool|bytes|complex|dict|float|frozenset|int|list|object|set|str|tuple)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:->|:=|\\*\\*|\\/\\/|==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>:|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeR(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['attribute', '/^`(?:\\\\.|[^`\\\\])+`/s'],
            ['keyword', '/^\\b(?:break|else|for|function|if|in|next|repeat|switch|while)\\b/'],
            ['constant', '/^\\b(?:FALSE|Inf|NA|NA_character_|NA_complex_|NA_integer_|NA_real_|NULL|NaN|TRUE)\\b/'],
            ['number', '/^-?(?:0[xX][0-9A-Fa-f]+|(?:\\d+\\.\\d*|\\.\\d+|\\d+)(?:[eE][+-]?\\d+)?)(?:[Li])?\\b/'],
            ['attribute', '/^[A-Za-z_.][A-Za-z0-9_.]*(?=\\s*:?\\s*=(?!=))/'],
            ['function', '/^[A-Za-z_.][A-Za-z0-9_.]*(?=\\s*\\()/'],
            ['variable', '/^[A-Za-z_.][A-Za-z0-9_.]*/'],
            ['attribute', '/^(?:<<-|<-|->>|->|=(?!(?:=|>))|:=)/'],
            ['operator', '/^(?:\\*\\*|<=|>=|==|=>|!=|\\|>|\\|\\||&&|:::|::|%[^%\\s]+%|[{}()[\\],;+*\\/<>!|&:^@$~.-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeRaku(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^=begin\\b[^\\n]*(?:\\n(?!^=end\\b)[^\\n]*)*\\n^=end\\b[^\\n]*/m'],
            ['comment', '/^=finish\\b[\\s\\S]*/i'],
            ['comment', '/^=(?:begin|for|head\\d|item|end|para|defn|code|comment)\\b[^\\n]*/i'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^q[qxw]?\\s*:to\\s*\\/([A-Za-z_][A-Za-z0-9_-]*)\\/\\s*;?\\R[\\s\\S]*?^\\1\\s*$/m'],
            ['string', '/^q[qxw]?(?:\\{(?:\\\\.|[^}\\\\])*\\}|\\[(?:\\\\.|[^\\]\\\\])*\\]|\\((?:\\\\.|[^)\\\\])*\\)|<(?:\\\\.|[^>\\\\])*>|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['string', '/^(?:rx|m|s)\\s*([#\\/|])(?:\\\\.|(?!\\1)[^\\n])*\\1(?:[A-Za-z]*)/'],
            ['string', '/^\\/(?:\\\\.|[^\\/\\\\\\n])+\\//'],
            ['attribute', '/^:[!?]?[A-Za-z_][A-Za-z0-9_-]*/'],
            ['attribute', '/^\\b[A-Za-z_][A-Za-z0-9_-]*(?=\\s*=>)/'],
            ['variable', '/^[\\$@%&](?:[.!?*])?(?:[A-Za-z_][A-Za-z0-9_-]*|[\\/_!])/'],
            ['keyword', '/^\\b(?:also|augment|but|class|constant|default|does|else|elsif|enum|export|for|gather|given|grammar|has|if|import|is|last|loop|method|module|multi|my|need|next|of|only|our|proto|redo|repeat|require|return|role|rule|state|sub|subset|take|token|trusts|try|unit|unless|until|use|when|where|while|with|without)\\b/'],
            ['constant', '/^\\b(?:Any|False|Mu|Nil|True|self)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?:::[A-Z][A-Za-z0-9_]*)*(?=\\s*(?:[({.:;,]|-->|\\b))/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f_]+|0[bB][01_]+|\\d+(?:_\\d+)*(?:\\.\\d+(?:_\\d+)*)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['operator', '/^\\b(?:and|cmp|div|eq|ge|gt|le|lt|mod|ne|or|x|xx|xor)\\b/'],
            ['function', '/^\\b(?:dd|die|note|put|say|slurp|spurt)(?=$|[^A-Za-z0-9_-])/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_-]*\\b/'],
            ['operator', '/^(?:-->|=>|~~|!~~|\\.\\.\\^?|\\.\\^|::|\\+\\+|--|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeRuby(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['string', '/^%[qQ]?\\{(?:\\\\.|[^}\\\\])*\\}/s'],
            ['attribute', '/^:[A-Za-z_][A-Za-z0-9_]*[!?=]?/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*[!?=]?:(?!:)/'],
            ['variable', '/^(?:@@|@|\\$)[A-Za-z_][A-Za-z0-9_]*/'],
            ['keyword', '/^\\b(?:BEGIN|END|alias|and|begin|break|case|class|def|defined\\?|do|else|elsif|end|ensure|for|if|in|module|next|not|or|private|protected|public|redo|rescue|retry|return|then|undef|unless|until|when|while|yield)\\b/'],
            ['constant', '/^\\b(?:false|nil|self|super|true)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?:::[A-Z][A-Za-z0-9_]*)*/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['function', '/^\\b(?:abort|autoload|binding|catch|eval|exec|exit|fail|fork|format|gets|lambda|load|loop|open|p|print|printf|proc|putc|puts|raise|rand|require|require_relative|select|sleep|sprintf|system|task|throw|warn)\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*[!?=]?(?=\\s*\\()/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_]*[!?=]?\\b/'],
            ['operator', '/^(?:::|=>|->|===|==|!=|<=|>=|=~|!~|&&|\\|\\||\\.\\.\\.?|\\+=|-=|\\*=|\\/=|%=|[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeRust(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['attribute', '/^#!?\\[[^\\]\\n]*\\]/'],
            ['string', '/^r#+".*?"#+/s'],
            ['string', '/^(?:b|br|r)?"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^b?'(?:\\\\.|[^'\\\\])'/s"],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*!(?=\\s*[\\({\\[])/'],
            ['keyword', '/^\\b(?:as|async|await|break|const|continue|crate|dyn|else|enum|extern|fn|for|if|impl|in|let|loop|match|mod|move|mut|pub|ref|return|self|static|struct|super|trait|try|type|union|unsafe|use|where|while|yield)\\b/'],
            ['constant', '/^\\b(?:Err|None|Ok|Some|false|true)\\b/'],
            ['datatype', '/^\\b(?:Box|Debug|HashMap|HashSet|Option|Path|PathBuf|Result|Self|String|Value|Vec|bool|char|f32|f64|i8|i16|i32|i64|i128|isize|str|u8|u16|u32|u64|u128|usize)\\b/'],
            ['attribute', "/^'[A-Za-z_][A-Za-z0-9_]*/"],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)(?:[iu](?:8|16|32|64|128|size)|f(?:32|64))?\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({]|::|\\b))/'],
            ['function', '/^r#[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:\\(|::<))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:\\(|::<))/'],
            ['variable', '/^r#[A-Za-z_][A-Za-z0-9_]*/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|->|=>|==|!=|<=|>=|&&|\\|\\||\\.\\.|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeClojure(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^;[^\\n]*/'],
            ['string', '/^#"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['attribute', '/^:{1,2}[A-Za-z*!?+<>=_.$%&-][A-Za-z0-9*!?+<>=_.$%&\\/:-]*/'],
            ['attribute', '/^\\^[A-Za-z0-9*!?+<>=_.$%&\\/:-]+/'],
            ['constant', '/^\\\\(?:newline|return|space|tab|u[0-9A-Fa-f]{4}|o[0-7]{1,3}|.)/'],
            ['preprocessor', '/^#_/'],
            ['preprocessor', '/^#\\?(?:@)?/'],
            ['keyword', '/^\\b(?:as|case|catch|cond|def|defmacro|defmethod|defmulti|defn|defonce|defprotocol|defrecord|deftype|do|doseq|dotimes|else|extend-protocol|finally|fn|for|if|if-let|if-not|import|in-ns|let|letfn|loop|monitor-enter|monitor-exit|ns|proxy|quote|recur|require|set!|throw|try|use|when|when-let|when-not|while)\\b/'],
            ['constant', '/^\\b(?:false|nil|true)\\b/'],
            ['function', '/^\\b(?:edn\\/read-string|str\\/blank\\?|str\\/join|str\\/trim|assoc|conj|contains\\?|count|filter|first|get|into|map|or|println|reduce|seq|slurp|str|vec)(?=$|[^A-Za-z0-9_])/'],
            ['number', '/^-?(?:0[xX][0-9A-Fa-f]+|\\d+\\/\\d+|(?:\\d+\\.\\d*|\\.\\d+|\\d+)(?:[eE][+-]?\\d+)?[MN]?)(?=$|[^A-Za-z0-9_])/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_.]*(?:\\/[A-Z][A-Za-z0-9_.]*)?\\b/'],
            ['variable', '/^[A-Za-z*!?+<>=_.$%&-][A-Za-z0-9*!?+<>=_.$%&\\/:-]*/'],
            ['operator', '/^(?:#\\{|#\\(|#\\[|~@|->>|->|::?|[{}()[\\]\'`~@^.,])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCommonLisp(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#\\|[\\s\\S]*?\\|#/'],
            ['comment', '/^;[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['constant', '/^#\\\\(?:Space|Tab|Newline|Return|Backspace|Page|Rubout|[A-Za-z0-9_-]+)/i'],
            ['constant', '/^#:[A-Za-z0-9*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*/'],
            ['attribute', '/^:[A-Za-z0-9*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*/'],
            ['variable', '/^\\*[A-Za-z0-9!?+<>=_.$%&~^\\/:-]+\\*/'],
            ['constant', '/^(?:nil|t)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/i'],
            ['datatype', '/^(?:array|bit-vector|boolean|character|condition|cons|fixnum|float|hash-table|integer|list|package|pathname|sequence|string|symbol|vector)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/i'],
            ['keyword', '/^(?:block|case|catch|collect|cond|declare|defclass|defconstant|defgeneric|defmacro|defmethod|defpackage|defparameter|defstruct|deftype|defun|defvar|do|dolist|dotimes|flet|for|function|handler-bind|handler-case|if|in|in-package|labels|lambda|let\\*?|loop|macrolet|multiple-value-bind|progn|quote|return|return-from|setf|setq|tagbody|throw|unless|unwind-protect|when)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/i'],
            ['function', '/^(?:append|apply|aref|car|cdr|concatenate|copy-list|error|find|format|funcall|getf|gethash|identity|length|list|make-[A-Za-z0-9*!?+<>=_.$%&~^\\/:-]+|mapcar|maphash|member|not|null|reduce|remove-if-not|review-packet-[A-Za-z0-9*!?+<>=_.$%&~^\\/:-]+|sort|string=|string-downcase|string-trim|write-line)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/i'],
            ['number', '/^-?(?:#x[0-9A-Fa-f]+|#b[01]+|#o[0-7]+|\\d+\\/\\d+|(?:\\d+\\.\\d*|\\.\\d+|\\d+)(?:[eEdDfFlLsS][+-]?\\d+)?)(?=$|[^A-Za-z0-9_])/'],
            ['variable', '/^[A-Za-z0-9*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*/'],
            ['operator', '/^(?:#\\x27|#\\(|[,`\\x27()]|[\\[\\]{}])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeScheme(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#\\|[\\s\\S]*?\\|#/'],
            ['comment', '/^;[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['keyword', '/^#lang(?=$|\\s)/'],
            ['attribute', '/^#:[A-Za-z*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*/'],
            ['constant', '/^#\\\\(?:newline|return|space|tab|u[0-9A-Fa-f]{4}|.)/'],
            ['constant', '/^#(?:true|false|t|f)(?=$|[^A-Za-z0-9_])/'],
            ['constant', "/^'[A-Za-z*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*/"],
            ['keyword', '/^(?:define(?:-syntax|-values)?|lambda|let\\*?|letrec\\*?|if|cond|else|begin|case|do|delay|set!|quote|quasiquote|unquote(?:-splicing)?|import|export|require|provide|module|struct|match|for(?:\\*|\\/list)?|when|unless|and|or|guard)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['constant', '/^(?:nil|null)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['function', '/^(?:apply|append|car|cdr|cons|display|filter|format|hash|hash-ref|hash-set|length|list|map|member|not|null\\?|number->string|read|regexp-match|reverse|string-append|string-blank\\?|string-downcase|string-length|string-trim|string-upcase|symbol->string|values|vector)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['datatype', '/^(?:boolean|byte|string|symbol|vector|racket|scheme)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['number', '/^-?(?:#(?:b[01]+|o[0-7]+|x[0-9A-Fa-f]+)|\\d+\\/\\d+|(?:\\d+\\.\\d*|\\.\\d+|\\d+)(?:[eE][+-]?\\d+)?)(?=$|[^A-Za-z0-9_])/'],
            ['datatype', '/^[A-Z][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['variable', '/^[A-Za-z*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*/'],
            ['operator', '/^(?:#\\(|#\\[|#\\{|,@|[{}()[\\]\'`.,])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeFennel(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^;[^\\n]*/'],
            ['string', '/^\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['attribute', '/^:[A-Za-z*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*/'],
            ['keyword', '/^(?:accumulate|and|case|collect|do|each|eval-compiler|fn|for|global|icollect|if|lambda|let|local|macro|macros|match|not|or|partial|quote|set|set-forcibly|tset|try|values|var|when|while|λ)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['constant', '/^(?:false|nil|true)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['function', '/^(?:assert|error|ipairs|length|pairs|print|require|select|string\\.[A-Za-z_][A-Za-z0-9_-]*|table\\.[A-Za-z_][A-Za-z0-9_-]*|tonumber|tostring|type|view)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['datatype', '/^(?:boolean|function|number|string|table|thread|userdata)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['number', '/^-?(?:0[xX][0-9A-Fa-f]+|(?:\\d+\\.\\d*|\\.\\d+|\\d+)(?:[eE][+-]?\\d+)?)(?=$|[^A-Za-z0-9_])/'],
            ['datatype', '/^[A-Z][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['function', '/^[A-Za-z*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*(?=\\s*\\[)/'],
            ['operator', '/^(?:not=|~=|==|<=|>=|=>|->|=|<|>)(?=$|[^A-Za-z0-9*!?+<>=_.$%&~^\\/:-])/'],
            ['variable', '/^[A-Za-z*!?+<>=_.$%&~^\\/:-][A-Za-z0-9*!?+<>=_.$%&~^\\/:-]*/'],
            ['operator', '/^(?:#\\{|#\\(|#\\[|[{}()[\\]\'`~@^.,#])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeErlang(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^%[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['constant', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['constant', '/^\\$(?:\\\\(?:x[0-9A-Fa-f]{1,6}|[0-7]{1,3}|.)|[^\\s])/'],
            ['attribute', '/^-[a-z][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['preprocessor', '/^\\?[A-Za-z_][A-Za-z0-9_]*/'],
            ['datatype', '/^#[a-z][A-Za-z0-9_]*(?=\\s*[.{])/'],
            ['attribute', '/^\\b[a-z][A-Za-z0-9_]*(?=\\s*(?:::|=|=>))/'],
            ['keyword', '/^\\b(?:after|begin|case|catch|cond|end|fun|if|let|maybe|of|receive|try|when)\\b/'],
            ['constant', '/^\\b(?:false|nil|ok|true|undefined)\\b/'],
            ['datatype', '/^\\b(?:binary|boolean|float|integer|list|map|number|pid|string|term|tuple)\\b(?=\\s*(?:\\(|,|\\)|$))/'],
            ['datatype', '/^\\b(?:erlang|gen_server|io|lists|maps|string|unicode)\\b(?=\\s*:)/'],
            ['number', '/^-?\\b(?:(?:[2-9]|[12][0-9]|3[0-6])#[0-9A-Za-z]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['function', '/^\\b[a-z][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b_[A-Za-z0-9_]*\\b/'],
            ['variable', '/^\\b[A-Z][A-Za-z0-9_]*\\b/'],
            ['constant', '/^\\b[a-z][A-Za-z0-9_]*(?=\\s*(?:[,.;\\]\\}\\)\\|\\/]|=>|$))/'],
            ['variable', '/^\\b[a-z][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:->|<-|=>|:=|::|=:=|=\\/=|\\/=|==|=<|>=|\\+\\+|--|\\|\\||<<|>>|[{}()[\\];,.+*\\/%=!<>?:#&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeElixir(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^~[A-Za-z](?:"""[\\s\\S]*?"""|\'\'\'[\\s\\S]*?\'\'\'|\\/(?:\\\\.|[^\\/\\\\])*\\/[A-Za-z]*)/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', "/^'''[\\s\\S]*?'''/"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_!?]*/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_!?]*(?=\\s*:)/'],
            ['keyword', '/^\\b(?:after|alias|and|case|catch|cond|def|defdelegate|defexception|defguard|defguardp|defimpl|defmacro|defmacrop|defmodule|defoverridable|defp|defprotocol|defstruct|destructure|do|else|end|fn|for|if|import|in|not|or|quote|raise|receive|require|rescue|super|throw|try|unless|unquote|use|when|with)\\b/'],
            ['constant', '/^\\b(?:false|nil|true)\\b/'],
            ['constant', '/^:[A-Za-z_][A-Za-z0-9_!?@]*(?:[\\/?][A-Za-z0-9_!?@-]+)*/'],
            ['datatype', '/^\\b(?:Access|Agent|Application|Date|DateTime|Decimal|Enum|File|GenServer|Integer|Jason|Keyword|List|Logger|Map|MapSet|Phoenix|Process|Regex|Repo|String|Supervisor|Task|Time|URI)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|0[oO][0-7](?:_?[0-7])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)\\b/'],
            ['datatype', '/^__MODULE__\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_!?]*(?=\\s*(?:<[^>\\n]+>\\s*)?\\()/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_!?]*(?=\\s+(?:do\\b|%\\{|\\[|\\{|[A-Za-z_:@"]))/'],
            ['variable', '/^\\b_[A-Za-z0-9_!?]*\\b/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_!?]*\\b/'],
            ['operator', '/^(?:%\\{|%|\\|>|::|=>|->|<-|\\\\\\\\|<>|==|!=|<=|>=|&&|\\|\\||\\.\\.|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeLua(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^--\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
            ['comment', '/^--[^\\n]*/'],
            ['string', '/^\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:and|break|do|else|elseif|end|for|function|goto|if|in|local|not|or|repeat|return|then|until|while)\\b/'],
            ['constant', '/^\\b(?:false|nil|true)\\b/'],
            ['datatype', '/^\\bpandoc\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['function', '/^\\b(?:assert|collectgarbage|dofile|error|getmetatable|ipairs|load|next|pairs|pcall|print|rawequal|rawget|rawlen|rawset|require|select|setmetatable|tonumber|tostring|type|warn|xpcall)\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|\\.\\.|==|~=|<=|>=|::|\\/\\/|[{}()[\\];,.+*\\/%=#<>:-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeOcaml(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\(\*[\s\S]*?\*\)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['constant', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^\[@{1,3}[A-Za-z_][A-Za-z0-9_\'.-]*/'],
            ['attribute', '/^%[A-Za-z_][A-Za-z0-9_\'.-]*/'],
            ['attribute', '/^[~?][A-Za-z_][A-Za-z0-9_\']*:?(?=\s*)/'],
            ['attribute', '/^\b[a-z_][A-Za-z0-9_\']*(?=\s*:)/'],
            ['keyword', '/^\b(?:and|as|assert|begin|class|constraint|do|done|downto|else|end|exception|external|for|fun|function|functor|if|in|include|inherit|initializer|lazy|let|match|method|module|mutable|new|nonrec|object|of|open|or|private|rec|sig|struct|then|to|try|type|val|virtual|when|while|with)\b/'],
            ['constant', '/^\b(?:Error|False|None|Ok|Some|True|false|true)\b/'],
            ['datatype', '/^\b(?:array|bool|bytes|char|float|int|list|option|result|string|unit)\b/'],
            ['datatype', '/^\b[A-Z][A-Za-z0-9_\']*(?:\.[A-Z][A-Za-z0-9_\']*)*/'],
            ['number', '/^-?\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|0[oO][0-7](?:_?[0-7])*|\d(?:_?\d)*(?:\.\d(?:_?\d)*)?(?:[eE][+-]?\d(?:_?\d)*)?)\b/'],
            ['function', '/^\b[A-Z][A-Za-z0-9_\']*(?:\.[A-Za-z_][A-Za-z0-9_\']*)+\b/'],
            ['function', '/^\b(?:compare|decode|encode|find|map|mem|printf|sprintf|trim|value)\b/'],
            ['function', '/^\b[a-z_][A-Za-z0-9_\']*(?=\s*(?:[({]|\?|\~|:))/'],
            ['variable', '/^\b_[A-Za-z0-9_\']*\b/'],
            ['variable', '/^\b[a-z_][A-Za-z0-9_\']*\b/'],
            ['operator', '/^(?:->|=>|\|>|@@|::|:=|==|!=|<>|<=|>=|&&|\|\||\?\?|[{}()[\];,.+*\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeAgda(string $code): array
    {
        return $this->scan($code, [
            ['preprocessor', '/^\\{-#\\s*[A-Z][A-Za-z0-9_-]*(?:\\s+[^#\\n][\\s\\S]*?)?#-\\}/'],
            ['comment', '/^\\{-[\\s\\S]*?-\\}/'],
            ['comment', '/^--[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['keyword', '/^\\b(?:abstract|as|constructor|data|field|forall|hiding|import|in|infix|infixl|infixr|instance|let|macro|module|mutual|open|pattern|postulate|primitive|private|public|record|renaming|rewrite|syntax|using|variable|where|with)\\b/'],
            ['constant', '/^\\b(?:false|just|nothing|suc|true|zero)\\b/'],
            ['datatype', '/^\\b(?:Bool|Char|IO|Level|List|Maybe|Nat|Prop|Set|String|Unit)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_\']*(?:\\.[A-Z][A-Za-z0-9_\']*)*\\b/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_\'-]*(?=\\s*:)/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_\'-]*(?=\\s+(?!(?:as|hiding|import|in|renaming|using|where|with)\\b)(?:[A-Za-z_(\\"\\d]))/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f_]+|\\d[\\d_]*(?:\\.\\d[\\d_]*)?)\\b/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_\'-]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|=>|->|<-|::|==|\\/=|>=|<=|\\+\\+|&&|\\|\\||[{}()[\\];,.+*\\/%=$<>:|\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeHaskell(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\{-[\\s\\S]*?-\\}/'],
            ['comment', '/^--[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['keyword', '/^\\b(?:as|case|class|data|default|deriving|do|else|family|forall|foreign|hiding|if|import|in|infix|infixl|infixr|instance|let|module|newtype|of|qualified|then|type|where)\\b/'],
            ['constant', '/^\\b(?:EQ|False|GT|Just|LT|Left|Nothing|Right|True)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_\']*(?:\\.[A-Z][A-Za-z0-9_\']*)*/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[oO][0-7]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_\']*\\b/'],
            ['operator', '/^(?:=>|->|<-|::|==|\\/=|>=|<=|&&|\\|\\||[{}()[\\];,.+*\\/%=$<>:|\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePureScript(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\{-[\\s\\S]*?-\\}/'],
            ['comment', '/^--[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['keyword', '/^\\b(?:ado|as|case|class|data|derive|do|else|forall|foreign|hiding|if|import|in|infix|infixl|infixr|instance|let|module|newtype|of|then|type|where)\\b/'],
            ['constant', '/^\\b(?:EQ|False|GT|Just|LT|Left|Nothing|Right|True|false|true|unit)\\b/'],
            ['datatype', '/^\\b(?:Array|Boolean|Effect|Either|Int|List|Maybe|Number|String|Unit)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_\']*(?:\\.[A-Z][A-Za-z0-9_\']*)*\\b/'],
            ['attribute', '/^\\b[a-z_][A-Za-z0-9_\']*(?=\\s*:)(?!\\s*::)/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_\']*(?=\\s*::)/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_\']*(?=\\s+(?!(?:as|else|hiding|in|of|then|where)\\b)(?:[A-Za-z_(\\"\\d]))/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f_]+|\\d[\\d_]*(?:\\.\\d[\\d_]*)?(?:[eE][+-]?\\d[\\d_]*)?)\\b/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_\']*\\b/'],
            ['operator', '/^(?:=>|->|<-|::|==|\\/=|>=|<=|\\+\\+|&&|\\|\\||[{}()[\\];,.+*\\/%=$<>:|\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeIdris(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\{-[\\s\\S]*?-\\}/'],
            ['comment', '/^--[^\\n]*/'],
            ['preprocessor', "/^%[A-Za-z_][A-Za-z0-9_'-]*/"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['keyword', '/^\\b(?:abstract|auto|case|codata|constructor|covering|data|do|else|export|if|implementation|implicit|import|in|infix|infixl|infixr|interface|let|module|mutual|namespace|of|parameters|partial|private|public|record|rewrite|then|total|using|where|with)\\b/'],
            ['constant', '/^\\b(?:False|Just|Left|Nothing|Right|True)\\b/'],
            ['datatype', '/^\\b(?:Bool|Char|Double|Either|Fin|IO|Integer|Int|List|Maybe|Nat|String|Type|Unit|Vect|Void)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_\']*(?:\\.[A-Z][A-Za-z0-9_\']*)*\\b/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_\']*(?=\\s*:)/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_\']*(?=\\s+(?!(?:else|in|of|then|where)\\b)(?:[A-Za-z_(\\"\\d]))/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f_]+|\\d[\\d_]*(?:\\.\\d[\\d_]*)?(?:[eE][+-]?\\d[\\d_]*)?)\\b/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_\']*\\b/'],
            ['operator', '/^(?:=>|->|<-|::|==|\\/=|>=|<=|\\+\\+|&&|\\|\\||[{}()[\\];,.+*\\/%=$<>:|\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCoq(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\(\\*[\\s\\S]*?\\*\\)/'],
            ['attribute', '/^#\\[[^\\]\\n]*(?:\\][ \\t]*#\\[[^\\]\\n]*)*\\]/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['keyword', '/^\\b(?:Abort|About|Admitted|Arguments|Axiom|Check|Class|Close|CoFixpoint|CoInductive|Compute|Context|Corollary|Create|Defined|Definition|Delimit|Derive|End|Eval|Example|Export|Fact|Fixpoint|From|Global|Hint|Hypotheses|Hypothesis|Import|Implicit|Inductive|Instance|Lemma|Let|Load|Local|Locate|Ltac|Module|Notation|Opaque|Open|Parameter|Parameters|Print|Program|Proof|Proposition|Qed|Record|Remark|Require|Resolve|Rewrite|Scope|Search|Section|Set|Theorem|Transparent|Unset|Variable|Variables)\\b/'],
            ['keyword', '/^\\b(?:as|at|by|cofix|else|end|exists|fix|forall|fun|if|in|let|match|rec|return|struct|then|using|where|with)\\b/'],
            ['function', '/^\\b(?:apply|assumption|auto|cbn|destruct|discriminate|eapply|exact|exists|induction|intro|intros|lia|now|refine|reflexivity|rewrite|simpl|split|subst|unfold)\\b/'],
            ['constant', '/^\\b(?:False|I|None|O|S|Some|True|conj|eq_refl|false|left|nil|or_introl|or_intror|right|tt|true)\\b/'],
            ['datatype', '/^\\b(?:Empty_set|Prop|Set|Type|bool|list|nat|option|prod|sig|string|unit)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_\']*(?:\\.[A-Z][A-Za-z0-9_\']*)*\\b/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_\']*(?=\\s*(?:\\(|:=))/'],
            ['attribute', '/^\\b[a-z_][A-Za-z0-9_\']*(?=\\s*:)/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f_]+|\\d[\\d_]*(?:\\.\\d[\\d_]*)?)\\b/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_\']*\\b/'],
            ['operator', '/^(?:<->|->|=>|:=|::|\\/\\\\|\\\\\\/|<=|>=|<>|==|[{}()[\\];,.+*\\/%=$<>:|&?!_\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeElm(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\{-[\\s\\S]*?-\\}/'],
            ['comment', '/^--[^\\n]*/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['constant', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['keyword', '/^\\b(?:alias|as|case|effect|else|exposing|if|import|in|let|module|of|port|then|type|where)\\b/'],
            ['constant', '/^\\b(?:Err|False|Just|Nothing|Ok|True)\\b/'],
            ['datatype', '/^\\b(?:Bool|Char|Cmd|Decoder|Dict|Float|Html|Int|Json|List|Maybe|Model|Msg|Never|Program|Result|String|Sub|Task|Text|Tuple)\\b(?!\\.)/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['function', '/^\\b[A-Z][A-Za-z0-9_]*(?:\\.[A-Z][A-Za-z0-9_]*)*\\.[a-z_][A-Za-z0-9_]*\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?:\\.[A-Z][A-Za-z0-9_]*)*\\b(?!\\.)/'],
            ['function', '/^\\b[a-z_][A-Za-z0-9_\']*(?=\\s*\\()/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_\']*\\b/'],
            ['operator', '/^(?:->|<-|=>|::|==|\\/=|>=|<=|&&|\\|\\||\\|>|<\\||\\.\\.|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTex(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^%[^\\n]*/'],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_.:-]*\\$/'],
            ['keyword', '/^\\\\(?:begin|def|documentclass|end|include|input|let|newcommand|providecommand|renewcommand|section|subsection|subsubsection|usepackage)\\*?\\b/'],
            ['function', '/^\\\\[A-Za-z@]+\\*?/'],
            ['datatype', '/^\\{(?:\\\\?[A-Za-z0-9_.:-]+|[^{}$\\n]{1,80})\\}/'],
            ['number', '/^-?\\d+(?:\\.\\d+)?/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_-]*/'],
            ['operator', '/^(?:\\\\[#$%&_{}]|[{}[\\](),=^_~&#+*\\/:;<>|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTypst(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^```[\\s\\S]*?```/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['attribute', '/^<[A-Za-z_][A-Za-z0-9_.:-]*>/'],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.:-]*/'],
            ['region', '/^={1,6}(?=\\s+#[A-Za-z_])/'],
            ['keyword', '/^#(?:break|continue|context|else|for|if|import|include|in|let|return|set|show|while)\\b/'],
            ['keyword', '/^\\b(?:as|import|include)\\b/'],
            ['function', '/^#[A-Za-z_][A-Za-z0-9_-]*(?:\\.[A-Za-z_][A-Za-z0-9_-]+)+(?=\\s*\\()/'],
            ['function', '/^#[A-Za-z_][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['variable', '/^#[A-Za-z_][A-Za-z0-9_-]*/'],
            ['datatype', '/^\\b(?:array|block|bool|bytes|content|datetime|dictionary|figure|float|heading|image|int|label|link|metadata|none|page|raw|rect|str|table|text)\\b/'],
            ['constant', '/^\\b(?:auto|false|none|true)\\b/'],
            ['number', '/^-?\\b(?:\\d+\\.\\d+|\\.\\d+|\\d+)(?:deg|em|fr|in|mm|pt|px|rad|%)?\\b/i'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*:)/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_-]*\\b/'],
            ['operator', '/^(?:=>|==|!=|<=|>=|\\.\\.\\.|[{}()[\\],.:;=+*\\/%!<>?&|#~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeV(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['attribute', '/^\\[(?:(?:json|heap|deprecated|manualfree|params|inline|typedef|unsafe|flag|export|extern|if|console)(?::[^\\]\\n]*)?|[A-Za-z_][A-Za-z0-9_]*\\s*:[^\\]\\n]+)\\](?:[ \\t]*\\[(?:(?:json|heap|deprecated|manualfree|params|inline|typedef|unsafe|flag|export|extern|if|console)(?::[^\\]\\n]*)?|[A-Za-z_][A-Za-z0-9_]*\\s*:[^\\]\\n]+)\\])*/i'],
            ['string', '/^r"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^r'(?:\\\\.|[^'\\\\])*'/s"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['keyword', '/^\\$(?:else|for|if)\\b/'],
            ['function', '/^\\$d(?=\\s*\\()/'],
            ['keyword', '/^\\b(?:as|asm|assert|atomic|break|const|continue|defer|else|enum|fn|for|go|goto|if|import|in|interface|is|lock|match|module|mut|or|pub|return|rlock|select|shared|spawn|static|struct|type|unsafe)\\b/'],
            ['constant', '/^\\b(?:false|none|null|true)\\b/'],
            ['datatype', '/^\\b(?:any|bool|byte|char|f32|f64|i16|i32|i64|i8|int|isize|map|rune|string|u16|u32|u64|u8|usize|voidptr)\\b/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f_]+|0[bB][01_]+|\\d[\\d_]*(?:\\.\\d[\\d_]*)?(?:[eE][+-]?\\d[\\d_]*)?)(?:[A-Za-z][A-Za-z0-9_]*)?\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({\\[.!?]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.?|:=|=>|\\?\\?|\\?|!|==|!=|<=|>=|&&|\\|\\||<-|[{}()[\\];,.+*\\/%=<>:&|^~@-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeVimscript(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $this->tokenizeVimscriptLine($line, $tokens);
            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeVimscriptLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)("[^\\n]*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        $this->scanInto($line, [
            ['comment', '/^"[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['string', '/^\\/(?:\\\\.|[^\\/\\\\\\n])+\\/[A-Za-z]*/'],
            ['constant', '/^v:(?:false|null|none|true)\\b/i'],
            ['constant', '/^#[0-9A-Fa-f]{3,8}\\b/'],
            ['variable', '/^[gsbtwavl]:[A-Za-z_][A-Za-z0-9_#]*/i'],
            ['attribute', '/^&(?:[gl]:)?[A-Za-z_][A-Za-z0-9_]*/i'],
            ['keyword', '/^\\b(?:augroup|autocmd|break|call|command|continue|else|elseif|endfunction|endif|endfor|endtry|execute|finally|for|function!?|highlight|if|let|match|nnoremap|return|scriptencoding|setlocal|syntax|try|unlet)\\b/i'],
            ['keyword', '/^\\b(?:abort|contained|contains|ctermfg|guifg|silent|syn|then)\\b/i'],
            ['function', '/^\\b(?:empty|escape|execute|expand|fnamemodify|json_decode|printf|readfile|split|substitute|trim)\\b(?=\\s*\\()/i'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d+)?)\\b/'],
            ['attribute', '/^--?[A-Za-z][A-Za-z0-9_-]*/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_#]*(?=\\s*=)/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_#]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_#]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|==|!=|<=|>=|&&|\\|\\||=>|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ], $tokens);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeSed(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);
        $textLinePending = false;

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            if ($textLinePending) {
                $this->appendToken($tokens, 'string', $line);
                $textLinePending = self::sedTextLineContinues($line);
            } else {
                $this->tokenizeSedLine($line, $tokens);
                $textLinePending = self::sedStartsTextCommand($line);
            }

            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeSedLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if ($this->tokenizeSedPrintCommandLine($line, $tokens)) {
            return;
        }

        $this->scanInto($line, self::sedPatterns(), $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeSedPrintCommandLine(string $line, array &$tokens): bool
    {
        if (preg_match('/^(\s*(?:(?:\d+(?:\s*,\s*\d+)?|\$|\/(?:\\\\.|[^\/\\\\\n])+\/(?:\s*,\s*(?:\d+|\$|\/(?:\\\\.|[^\/\\\\\n])+\/))?)\s*)?)(!?)([pP])(?=$|[;}\s])(.*)$/', $line, $matches) !== 1) {
            return false;
        }

        if ($matches[1] !== '') {
            $this->scanInto($matches[1], self::sedPatterns(), $tokens);
        }
        if ($matches[2] !== '') {
            $this->appendToken($tokens, 'operator', $matches[2]);
        }

        $this->appendToken($tokens, 'keyword', $matches[3]);
        if ($matches[4] !== '') {
            $this->scanInto($matches[4], self::sedPatterns(), $tokens);
        }

        return true;
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private static function sedPatterns(): array
    {
        return [
            ['region', '/^:[A-Za-z_][A-Za-z0-9_-]*/'],
            ['number', '/^(?:\d+(?:,\d+)?|\$)(?=[!{;,\sA-Za-z\/]|$)/'],
            ['string', '/^\/(?:\\\\.|[^\/\\\\\n])+\/(?=[!,{;\sA-Za-z]|$)/'],
            ['keyword', '/^s(?=[^\sA-Za-z0-9_\\\\])/'],
            ['keyword', '/^y(?=[^\sA-Za-z0-9_\\\\])/'],
            ['keyword', '/^[aic](?=\\\\|$|\s)/'],
            ['attribute', '/^[gIpMw0-9]+(?=$|[;}\s])/'],
            ['string', '/^([^\sA-Za-z0-9_\\\\])(?:\\\\.|(?!\1)[^\n])*\1(?:\\\\.|(?!\1)[^\n])*\1/'],
            ['comment', '/^#[^\n]*/'],
            ['keyword', '/^[btdDgGhHlnNpPqQrRtTwWx=](?=\b|$|[;{}\s])/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['variable', '/^\b[A-Za-z_][A-Za-z0-9_-]*\b/'],
            ['operator', '/^(?:\\\\|&&|\|\||[{}()[\];,.+*\/=!<>?:|&$^-])/'],
        ];
    }

    private static function sedStartsTextCommand(string $line): bool
    {
        $line = rtrim($line);
        if ($line === '') {
            return false;
        }

        return preg_match(
            '/(?:^|[;{]\s*)(?:(?:\d+(?:\s*,\s*\d+)?|\$|\/(?:\\\\.|[^\/\\\\\n])+\/(?:\s*,\s*(?:\d+|\$|\/(?:\\\\.|[^\/\\\\\n])+\/))?)\s*)?[aci]\s*\\\\$/',
            $line
        ) === 1;
    }

    private static function sedTextLineContinues(string $line): bool
    {
        $line = rtrim($line);
        $backslashes = 0;
        for ($index = strlen($line) - 1; $index >= 0 && $line[$index] === '\\'; $index--) {
            $backslashes++;
        }

        return $backslashes % 2 === 1;
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTcl(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['variable', '/^\$\{[^}\n]+\}/'],
            ['variable', '/^\$[A-Za-z_][A-Za-z0-9_:]*(?:\([^)\n]*\))?/'],
            ['variable', '/^\$[0-9]+/'],
            ['keyword', '/^\b(?:after|append|array|break|catch|continue|dict|else|elseif|error|eval|expr|for|foreach|global|if|incr|lappend|namespace|package|proc|regexp|regsub|rename|require|return|set|source|switch|then|throw|trace|try|unset|uplevel|upvar|variable|while)\b/'],
            ['constant', '/^\b(?:false|no|off|on|true|yes)\b/i'],
            ['function', '/^\b(?:cd|close|concat|encoding|eof|exec|file|flush|format|gets|glob|join|lindex|linsert|list|llength|lrange|lreplace|open|pid|puts|pwd|read|scan|seek|split|string|subst|tell|time|update|wp)\b(?=\s|$|[;{}\[\]])/'],
            ['number', '/^-?\b(?:0[xX][0-9A-Fa-f]+|\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)\b/'],
            ['attribute', '/^--?[A-Za-z][A-Za-z0-9_-]*/'],
            ['function', '/^(?:::)?[A-Za-z_][A-Za-z0-9_:.-]*(?=\s+\{)/'],
            ['operator', '/^\b(?:eq|ne|in|ni)\b/'],
            ['variable', '/^(?:::)?[A-Za-z_][A-Za-z0-9_:.-]*\b/'],
            ['operator', '/^(?:==|!=|<=|>=|&&|\|\||\beq\b|\bne\b|\bin\b|\bni\b|[{}()[\];,.+*\/%=!<>?:|&$^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeBatch(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^::[^\n]*/'],
            ['comment', '/^@?\bREM\b[^\n]*/i'],
            ['region', '/^:[A-Za-z0-9_.-]+/'],
            ['string', '/^"(?:\^\^|\^.|[^"\^])*"/s'],
            ['variable', '/^%[A-Za-z_][A-Za-z0-9_]*%/'],
            ['variable', '/^%~[fdpnxsatz]*\d/i'],
            ['variable', '/^%\d/'],
            ['variable', '/^%%[A-Za-z_][A-Za-z0-9_]*/'],
            ['variable', '/^![A-Za-z_][A-Za-z0-9_]*!/'],
            ['keyword', '/^@?\b(?:assoc|break|call|cd|chcp|cls|copy|del|dir|do|echo|else|endlocal|erase|exit|for|goto|if|in|md|mkdir|move|not|path|pause|popd|prompt|pushd|rd|ren|rename|rmdir|set|setlocal|shift|start|title|type|verify|xcopy)\b/i'],
            ['operator', '/^\b(?:EQU|NEQ|LSS|LEQ|GTR|GEQ)\b/i'],
            ['constant', '/^\b(?:defined|errorlevel|exist|nul|off|on)\b/i'],
            ['function', '/^\b(?:composer|curl|mysql|php|powershell|robocopy|wp|wsl)(?=\s|$|[&|<>])/i'],
            ['attribute', '/^--[A-Za-z0-9][A-Za-z0-9_-]*/'],
            ['attribute', '/^\/[A-Za-z?][A-Za-z0-9_:-]*/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_]*(?=\s*=)/'],
            ['number', '/^-?\b\d+(?:\.\d+)?\b/'],
            ['variable', '/^\b[A-Za-z_][A-Za-z0-9_.-]*\b/'],
            ['operator', '/^(?:\^\^|\^.|&&|\|\||>>|<<|==|[()&|<>@=+*\/%,;:!-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeBash(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);
        $heredocDelimiter = null;

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            if ($heredocDelimiter !== null) {
                if (ltrim(rtrim($line, "\r"), "\t") === $heredocDelimiter) {
                    $this->appendToken($tokens, 'region', $line);
                    $heredocDelimiter = null;
                } else {
                    $this->appendToken($tokens, 'string', $line);
                }
            } else {
                $this->tokenizeBashLine($line, $tokens);
                $heredocDelimiter = self::bashHeredocDelimiter($line);
            }

            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeBashLine(string $line, array &$tokens): void
    {
        $this->scanInto($line, [
            ['keyword', '/^#![^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^<<-?/'],
            ['operator', '/^\\[\\[|^\\]\\]/'],
            ['attribute', '/^--[A-Za-z0-9][A-Za-z0-9_-]*/'],
            ['operator', '/^-[A-Za-z][A-Za-z0-9-]*/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\s*=)/'],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*|^\\$\\{[^}]+\\}/'],
            ['keyword', '/^\\b(?:case|do|done|elif|else|esac|fi|for|function|if|in|then|while)\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['function', '/^\\b(?:autoload|awk|basename|cat|cd|chmod|chown|composer|cp|curl|dirname|echo|emulate|env|find|getopts|grep|jq|make|mkdir|mv|npm|php|print|printf|read|rm|rsync|sed|set|setopt|sh|sort|tar|tee|test|touch|tr|wp|zstyle)(?=\\s|$|[;&|<>])/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_.-]*\\b/'],
            ['operator', '/^(?:\\$\\(|\\)\\)|;;|&&|\\|\\||>>|[{}()[\\];|&<>=$])/'],
        ], $tokens);
    }

    private static function bashHeredocDelimiter(string $line): ?string
    {
        if (preg_match('/(?:^|\\s)<<-?\\s*(?:\\\'([A-Za-z_][A-Za-z0-9_]*)\\\'|"([A-Za-z_][A-Za-z0-9_]*)"|\\\\?([A-Za-z_][A-Za-z0-9_]*))/', $line, $matches) !== 1) {
            return null;
        }

        foreach ([1, 2, 3] as $index) {
            if (isset($matches[$index]) && $matches[$index] !== '') {
                return $matches[$index];
            }
        }

        return null;
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeShellSession(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $this->tokenizeShellSessionLine($line, $tokens);
            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeShellSessionLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*(?:\\([^\\)\\n]+\\)[ \t]*)?(?:(?:[A-Za-z0-9_.-]+@)?[A-Za-z0-9_.-]+(?::[^$#\\n]*)?[#$]|[$#]|>)[ \t]?)(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'region', $matches[1]);
            if ($matches[2] !== '') {
                $this->tokenizeBashLine($matches[2], $tokens);
            }
            return;
        }

        $this->appendToken($tokens, 'information', $line);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCss(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['keyword', '/^@[A-Za-z][A-Za-z0-9_-]*/'],
            ['keyword', '/^!important\\b/i'],
            ['constant', '/^#[0-9A-Fa-f]{3,8}\\b/'],
            ['datatype', '/^\\.[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['datatype', '/^#[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['function', '/^::?[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['function', '/^[A-Za-z_-][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['attribute', '/^--[A-Za-z0-9_-]+|^[A-Za-z-]+(?=\\s*:\\s)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['number', '/^-?(?:\\d+\\.\\d+|\\.\\d+|\\d+)(?:ch|cm|deg|dppx|em|ex|fr|in|mm|ms|pc|pt|px|rem|s|turn|vh|vmax|vmin|vw|%)?(?=$|[^A-Za-z0-9_])/i'],
            ['keyword', '/^\\b(?:auto|block|border-box|center|contents|flex|flow-root|grid|inherit|initial|inline|inline-flex|inline-grid|none|relative|repeat|revert|revert-layer|safe|solid|subgrid|transparent|unsafe|unset)(?=$|[^A-Za-z0-9_-])/i'],
            ['datatype', '/^[A-Za-z][A-Za-z0-9_-]*(?=(?:[#.:\\s,{>+~]|$))/'],
            ['operator', '/^(?:~=|\\|=|\\^=|\\$=|\\*=|::|[{}()[\\]:;,.#>+~=*!\\/|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeLess(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^@(?:charset|color-profile|container|document|font-face|import|keyframes|media|namespace|page|plugin|supports)\\b/i'],
            ['keyword', '/^!important\\b/i'],
            ['variable', '/^@@[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['variable', '/^@\\{[A-Za-z_-][A-Za-z0-9_-]*\\}/'],
            ['variable', '/^@[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['operator', '/^&/'],
            ['constant', '/^#[0-9A-Fa-f]{3,8}\\b/'],
            ['datatype', '/^\\.[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['datatype', '/^#[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['function', '/^::?[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['function', '/^(?:average|ceil|darken|data-uri|escape|fade|fadein|fadeout|floor|lighten|mix|percentage|spin|unit|url)(?=\\s*\\()/i'],
            ['keyword', '/^\\b(?:and|auto|block|border-box|center|each|else|extend|flex|from|grid|if|in|inherit|initial|inline|none|not|only|or|relative|repeat|solid|through|to|transparent|when|unset)\\b/i'],
            ['function', '/^[A-Za-z_-][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['attribute', '/^--[A-Za-z0-9_-]+|^[A-Za-z-]+(?=\\s*:\\s)/'],
            ['constant', '/^\\b(?:false|null|true)\\b/i'],
            ['number', '/^-?(?:\\d+\\.\\d+|\\.\\d+|\\d+)(?:ch|cm|deg|dppx|em|ex|fr|in|mm|ms|pc|pt|px|rem|s|turn|vh|vmax|vmin|vw|%)?(?=$|[^A-Za-z0-9_])/i'],
            ['datatype', '/^[A-Za-z][A-Za-z0-9_-]*(?=(?:[#.:\\s,{>+~]|$))/'],
            ['operator', '/^(?:\\.\\.\\.|~=|\\|=|\\^=|\\$=|\\*=|==|!=|<=|>=|::|=>|[{}()[\\]:;,.#>+~=*!\\/|%-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeScss(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^@(?>at-root|content|debug|each|else|error|extend|for|forward|function|if|import|include|mixin|return|use|warn|while)\\b/'],
            ['keyword', '/^!default\\b|^!global\\b|^!important\\b/i'],
            ['variable', '/^\\$[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['operator', '/^#\\{/'],
            ['constant', '/^#[0-9A-Fa-f]{3,8}\\b/'],
            ['datatype', '/^%[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['datatype', '/^\\.[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['datatype', '/^#[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['operator', '/^&/'],
            ['function', '/^::?[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['function', '/^(?:adjust-color|append|darken|if|lighten|list\\.[A-Za-z_-]+|map\\.[A-Za-z_-]+|map-get|map-has-key|math\\.[A-Za-z_-]+|meta\\.[A-Za-z_-]+|mix|nth|rgba|selector\\.[A-Za-z_-]+|string\\.[A-Za-z_-]+|transparentize)(?=\\s*\\()/'],
            ['function', '/^[A-Za-z_-][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['attribute', '/^--[A-Za-z0-9_-]+|^[A-Za-z-]+(?=\\s*:\\s)/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['number', '/^-?(?:\\d+\\.\\d+|\\.\\d+|\\d+)(?:ch|cm|deg|dppx|em|ex|fr|in|mm|ms|pc|pt|px|rem|s|turn|vh|vmax|vmin|vw|%)?(?=$|[^A-Za-z0-9_])/i'],
            ['keyword', '/^\\b(?:and|as|auto|block|border-box|center|flex|from|grid|inherit|initial|inline|none|not|only|or|relative|repeat|solid|through|to|transparent|unset)\\b/i'],
            ['datatype', '/^[A-Za-z][A-Za-z0-9_-]*(?=(?:[#.:\\s,{>+~]|$))/'],
            ['operator', '/^(?:\\.\\.\\.|~=|\\|=|\\^=|\\$=|\\*=|==|!=|<=|>=|::|=>|[{}()[\\]:;,.#>+~=*!\\/|%-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeDiff(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\\\ No newline at end of file/'],
            ['region', '/^(?:diff --git|Index:)[^\\n]*/'],
            ['region', '/^(?:---|\\+\\+\\+)\\s[^\\n]*/'],
            ['region', '/^@@[^\\n]*@@/'],
            ['attribute', '/^(?:index|new file mode|deleted file mode|similarity index|dissimilarity index|rename from|rename to|copy from|copy to|old mode|new mode)[^\\n]*/i'],
            ['information', '/^\\+(?!\\+\\+)[^\\n]*/'],
            ['warning', '/^-(?!--)[^\\n]*/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeDot(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', '/^<[^>\\n]+>/'],
            ['keyword', '/^\\b(?:digraph|edge|graph|node|strict|subgraph)\\b/i'],
            ['attribute', '/^\\b(?:URL|arrowhead|arrowsize|arrowtail|bgcolor|center|color|constraint|decorateP|dir|distortion|fillcolor|fontcolor|fontname|fontsize|headclip|headlabel|height|label|labelangle|labeldistance|labelfontcolor|labelfontname|labelfontsize|layer|layers|margin|mclimit|minlen|name|nodesep|nslimit|ordering|orientation|page|pagedir|peripheries|port_label_distance|rank|rankdir|ranksep|ratio|regular|rotate|samehead|sametail|shape|shapefile|sides|size|skew|style|tailclip|taillabel|weight|width)(?=\\s*=|\\b)/'],
            ['constant', '/^\\b(?:BT|LR|RL|TB|back|bold|box|dashed|diamond|dotted|ellipse|filled|forward|invis|none|normal|oval|plaintext|record|rounded|same|solid|true|false)\\b/i'],
            ['number', '/^-?\\b(?:\\d+\\.\\d+|\\.\\d+|\\d+)\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:->|--|[{}()[\\];,=<>:+*\\/|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeDockerfile(string $code): array
    {
        return $this->scan($code, [
            ['attribute', '/^#\\s*(?:syntax|escape)=[^\\n]+/i'],
            ['comment', '/^#[^\\n]*/'],
            ['keyword', '/^\\b(?:ADD|ARG|AS|CMD|COPY|ENTRYPOINT|ENV|EXPOSE|FROM|HEALTHCHECK|LABEL|MAINTAINER|ONBUILD|RUN|SHELL|STOPSIGNAL|USER|VOLUME|WORKDIR)\\b/i'],
            ['operator', '/^--[A-Za-z][A-Za-z0-9-]*(?:=(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\\s\\\\]+))?/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*|^\\$\\{[^}]+\\}/'],
            ['constant', '/^\\b(?:false|null|true)\\b/i'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['attribute', '/^\\b[A-Za-z_][A-Za-z0-9_.-]*(?=\\s*=)/'],
            ['function', '/^\\b(?:cat|cd|chmod|chown|composer|cp|curl|echo|find|grep|make|mkdir|mv|npm|php|rm|sed|set|sh|tar|test|wp)(?=\\s)/'],
            ['operator', '/^(?:\\\\(?=\\r?\\n)|&&|\\|\\||[{}()[\\];,.+*\\%=!<>?:|&\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMakefile(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:define|else|endef|endif|export|if|ifdef|ifeq|ifndef|ifneq|include|override|private|sinclude|unexport|vpath)\\b/'],
            ['variable', '/^\\$\\([A-Za-z_][A-Za-z0-9_.-]*\\)|^\\$\\{[A-Za-z_][A-Za-z0-9_.-]*\\}|^\\$[@<^?*+%]/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_.-]*(?=\\s*(?::=|\\?=|\\+=|!=|=))/'],
            ['region', '/^(?:\\.[A-Za-z0-9_.\\/-]+|[A-Za-z0-9_%.\\/-]+)(?=\\s*:(?![=]))/'],
            ['variable', '/^\\.[A-Za-z0-9_.\\/-]+/'],
            ['operator', '/^(?::=|\\?=|\\+=|!=|::|&&|\\|\\||\\|>|-{1,2}[A-Za-z][A-Za-z0-9-]*|[{}()[\\];,.+*\\/%=!<>?:|&@\\\\-])/'],
            ['number', '/^\\b\\d+(?:\\.\\d+)*\\b/'],
            ['function', '/^\\b(?:cat|cd|chmod|chown|composer|cp|curl|echo|find|grep|make|mkdir|mv|npm|php|rm|sed|set|sh|tar|test|wp)(?=\\s|$)/'],
            ['variable', '/^(?:\\.[A-Za-z0-9_.\\/-]+|\\b[A-Za-z_][A-Za-z0-9_.\\/-]*\\b)/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMeson(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', "/^'''[\\s\\S]*?'''/"],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:and|break|continue|elif|else|endforeach|endif|foreach|if|in|not|or)\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['function', '/^\\b(?:add_global_arguments|add_project_arguments|both_libraries|configuration_data|custom_target|declare_dependency|dependency|disabler|environment|executable|files|find_library|find_program|generator|get_option|import|include_directories|install_data|install_headers|install_subdir|join_paths|library|message|option|project|run_command|shared_library|static_library|subdir|summary|warning)\\b(?=\\s*\\()/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d+)?)\\b/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\s*:)/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|==|!=|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJustfile(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['attribute', '/^\\[[A-Za-z_][A-Za-z0-9_-]*(?:\\([^\\]\\n]*\\))?\\]/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', "/^\\{\\{[A-Za-z_][A-Za-z0-9_-]*(?:\\s*\\|\\|\\s*(?:\"(?:\\\\.|[^\"\\\\])*\"|'(?:\\\\.|[^'\\\\])*'))?\\}\\}/"],
            ['keyword', '/^\\b(?:alias|else|export|if|import|include|set|then)\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['function', '/^\\b(?:arch|env|env_var|env_var_or_default|error|invocation_directory|just_executable|justfile|justfile_directory|num_cpus|os|os_family|quote|shell|uuid)\\b(?=\\s*\\()/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['region', '/^[A-Za-z_][A-Za-z0-9_-]*(?:\\s+[A-Za-z_][A-Za-z0-9_-]*(?:=(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\\s:]+))?)*\\s*(?=:(?!=))/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*(?::=|=|\\+=))/'],
            ['function', '/^\\b(?:bash|cat|cd|chmod|cp|echo|find|grep|just|mkdir|mv|php|rm|sed|sh|test|wp)\\b(?=\\s|[;|&]|$)/'],
            ['variable', '/^\\$\\{?[A-Za-z_][A-Za-z0-9_]*\\}?/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_-]*\\b/'],
            ['operator', '/^(?::=|\\+=|&&|\\|\\||==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>?:|&@\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMarkdown(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);
        $fence = null;
        $fenceBody = '';

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            if ($fence !== null) {
                if (self::markdownFenceCloses($line, $fence)) {
                    $this->appendMarkdownFenceBody($tokens, $fenceBody, $fence['language']);
                    $fenceBody = '';
                    $this->appendToken($tokens, 'preprocessor', $line);
                    $fence = null;
                } else {
                    $fenceBody .= $line;
                    if ($nextNewline !== false) {
                        $fenceBody .= "\n";
                    }
                    continue;
                }
            } else {
                $openingFence = self::markdownFenceOpening($line);
                if ($openingFence !== null) {
                    $this->appendToken($tokens, 'preprocessor', $line);
                    $fence = $openingFence;
                    $fenceBody = '';
                } else {
                    $this->tokenizeMarkdownLine($line, $tokens);
                }
            }

            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        if ($fence !== null) {
            $this->appendMarkdownFenceBody($tokens, $fenceBody, $fence['language']);
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeMarkdownLine(string $line, array &$tokens): void
    {
        $this->scanInto($line, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['region', '/^#{1,6}(?=\\s|$)[^\\n]*/'],
            ['region', '/^ {0,3}(?:(?:\\*\\s*){3,}|(?:_\\s*){3,}|(?:-\\s*){3,})(?=\\n|$)/'],
            ['attribute', '/^\\[[^\\]\\n]+\\]:[^\\n]*/'],
            ['attribute', '/^\\[\\^[^\\]\\n]+\\]/'],
            ['information', '/^!\\[[^\\]\\n]*\\]\\((?:\\\\.|[^)\\n])*\\)/'],
            ['attribute', '/^\\[[^\\]\\n]+\\]\\((?:\\\\.|[^)\\n])*\\)/'],
            ['attribute', '/^<(?:(?:https?|mailto):[^>\\s]+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,})>/'],
            ['operator', '/^ {0,3}(?:[-+*]|\\d+[.)])\\s+/'],
            ['operator', '/^>\\s?/'],
            ['constant', '/^\\[[ xX]\\](?=\\s)/'],
            ['string', '/^`+(?:[^`\\\\]|\\\\.)*`+/'],
            ['warning', '/^~~[^~\\n]+~~/'],
            ['keyword', '/^(?:\\*\\*[^*\\n]+\\*\\*|__[^_\\n]+__)/'],
            ['variable', '/^(?:\\*[^*\\n]+\\*|_[^_\\n]+_)/'],
            ['operator', '/^[\\\\*_`{}\\[\\]()#+.!|>-]/'],
        ], $tokens);
    }

    /**
     * @return array{char:string, length:int, language:?string}|null
     */
    private static function markdownFenceOpening(string $line): ?array
    {
        if (preg_match('/^[ \\t]{0,3}((`{3,})|(~{3,}))(.*)$/', $line, $matches) !== 1) {
            return null;
        }

        $marker = $matches[1];

        return [
            'char' => $marker[0],
            'length' => strlen($marker),
            'language' => self::markdownFenceLanguage($matches[4] ?? ''),
        ];
    }

    /**
     * @param array{char:string, length:int, language:?string} $fence
     */
    private static function markdownFenceCloses(string $line, array $fence): bool
    {
        $char = preg_quote($fence['char'], '/');

        return preg_match('/^[ \\t]{0,3}' . $char . '{' . $fence['length'] . ',}[ \\t]*$/', $line) === 1;
    }

    private static function markdownFenceLanguage(string $info): ?string
    {
        $info = trim($info);
        if ($info === '') {
            return null;
        }

        if (str_starts_with($info, '{') && str_ends_with($info, '}')) {
            $info = trim(substr($info, 1, -1));
        }

        $parts = preg_split('/\\s+/', $info) ?: [];
        foreach ($parts as $part) {
            $part = trim((string) $part, " \t\r\n{}");
            if ($part === '' || str_starts_with($part, '#') || str_contains($part, '=')) {
                continue;
            }

            if ($part[0] === '.') {
                $part = substr($part, 1);
            }

            $part = trim($part, " \t\r\n,;");
            if ($part === '') {
                continue;
            }

            $language = self::normalizeLanguage($part);
            if ($language !== null) {
                return $language;
            }
        }

        return null;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendMarkdownFenceBody(array &$tokens, string $body, ?string $language): void
    {
        if ($body === '') {
            return;
        }

        if ($language === null) {
            $this->appendToken($tokens, 'datatype', $body);
            return;
        }

        foreach ($this->tokenize($body, $language) as $token) {
            $this->appendToken($tokens, $token['type'], $token['text']);
        }
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeRest(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);
        $pendingCodeBlock = false;
        $codeBlockIndent = null;

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            if ($codeBlockIndent !== null) {
                if (trim($line) === '') {
                    $this->appendToken($tokens, 'text', $line);
                } elseif (str_starts_with($line, $codeBlockIndent)) {
                    $this->appendToken($tokens, 'datatype', $line);
                } else {
                    $codeBlockIndent = null;
                    $this->tokenizeRestLine($line, $tokens);
                    $pendingCodeBlock = self::restLineStartsCodeBlock($line);
                }
            } elseif ($pendingCodeBlock) {
                if (trim($line) === '') {
                    $this->appendToken($tokens, 'text', $line);
                } elseif (preg_match('/^([ \t]+)\\S/', $line, $matches) === 1) {
                    $codeBlockIndent = $matches[1];
                    $this->appendToken($tokens, 'datatype', $line);
                } else {
                    $pendingCodeBlock = false;
                    $this->tokenizeRestLine($line, $tokens);
                    $pendingCodeBlock = self::restLineStartsCodeBlock($line);
                }
            } else {
                $this->tokenizeRestLine($line, $tokens);
                $pendingCodeBlock = self::restLineStartsCodeBlock($line);
            }

            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeAsciiDoc(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);
        $listingDelimiter = null;
        $listingLanguage = null;
        $pendingListingLanguage = null;

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $trimmed = trim($line);
            if ($listingDelimiter !== null) {
                if ($trimmed === $listingDelimiter) {
                    $this->appendToken($tokens, 'region', $line);
                    $listingDelimiter = null;
                    $listingLanguage = null;
                } else {
                    $this->appendAsciiDocListingLine($line, $listingLanguage, $tokens);
                }
            } else {
                $delimiter = self::asciidocBlockDelimiter($trimmed);
                if ($delimiter !== null) {
                    $this->appendToken($tokens, 'region', $line);
                    $listingDelimiter = $delimiter;
                    $listingLanguage = $pendingListingLanguage;
                    $pendingListingLanguage = null;
                } else {
                    $this->tokenizeAsciiDocLine($line, $tokens);
                    $sourceLanguage = self::asciidocSourceLanguage($trimmed);
                    $pendingListingLanguage = $sourceLanguage ?? (trim($line) === '' ? $pendingListingLanguage : null);
                }
            }

            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeAsciiDocLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(\/\/.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(:{1}[A-Za-z0-9_.-]+:)(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'attribute', $matches[2]);
            $this->appendAsciiDocInline($matches[3], $tokens);
            return;
        }

        if (preg_match('/^([ \t]*)(\\[(?:source,[A-Za-z0-9_+.#-]+|[A-Za-z0-9_+.#,-]+)\\])([ \t]*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'attribute', $matches[2]);
            $this->appendToken($tokens, 'text', $matches[3]);
            return;
        }

        if (preg_match('/^([ \t]*)(={1,6}[ \t]+.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'region', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(?:([*.-])|([0-9]+[.)]))([ \t]+)(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'operator', ($matches[2] !== '' ? $matches[2] : $matches[3]) . $matches[4]);
            $this->appendAsciiDocInline($matches[5], $tokens);
            return;
        }

        if (preg_match('/^([ \t]*)(NOTE|TIP|IMPORTANT|WARNING|CAUTION):([ \t]+.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'keyword', $matches[2] . ':');
            $this->appendAsciiDocInline($matches[3], $tokens);
            return;
        }

        $this->appendAsciiDocInline($line, $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendAsciiDocInline(string $text, array &$tokens): void
    {
        $this->scanInto($text, [
            ['attribute', '/^\\[\\[[A-Za-z0-9_.:-]+\\]\\]/'],
            ['attribute', '/^(?:https?|ftp):\\/\\/[^\\s<>"\'`)\\[\\]]+/'],
            ['function', '/^[A-Za-z][A-Za-z0-9_-]*::(?=[^\\s\\[])/'],
            ['function', '/^[A-Za-z][A-Za-z0-9_-]*:(?=[^\\s\\[])/'],
            ['variable', '/^\\{[A-Za-z0-9_.:-]+\\}/'],
            ['constant', '/^<\\d+>/'],
            ['datatype', '/^`[^`\\n]+`/'],
            ['keyword', '/^\\*[^*\\n]+\\*/'],
            ['variable', '/^_[^_\\n]+_/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['operator', '/^(?:<<|>>|::|[\\\\[\\]{}(),:;=+*<>|\\/.#-])/'],
        ], $tokens);
    }

    private static function asciidocBlockDelimiter(string $line): ?string
    {
        return in_array($line, ['----', '....', '====', '****', '____'], true) ? $line : null;
    }

    private static function asciidocSourceLanguage(string $line): ?string
    {
        if (!str_starts_with($line, '[') || !str_ends_with($line, ']')) {
            return null;
        }

        $parts = array_map('trim', explode(',', substr($line, 1, -1)));
        if ($parts === []) {
            return null;
        }

        $kind = strtolower((string) $parts[0]);
        if ($kind !== 'source' && !str_starts_with($kind, 'source%')) {
            return null;
        }

        foreach (array_slice($parts, 1) as $part) {
            if ($part === '' || str_contains($part, '=') || str_starts_with($part, '.') || str_starts_with($part, '#')) {
                continue;
            }

            $language = self::normalizeLanguage($part);
            if ($language !== null) {
                return $language;
            }
        }

        return null;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeRestLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(\\.\\.(?:[ \t].*|$))$/', $line, $matches) === 1
            && preg_match('/^\\.\\.[ \t]+(?:[A-Za-z0-9_.-]+(?::[A-Za-z0-9_.-]+)*::|__?:|_[A-Za-z0-9_.:+ -]+:)(?:[ \t]|$)/', $matches[2]) !== 1
        ) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(\\.\\.[ \t]+(?:\\[(?:\\d+|#|\\*|#[A-Za-z0-9_.:+-]+)\\]|\\|[A-Za-z0-9_.:+ -]+\\|[ \t]+[A-Za-z0-9_.:+-]+::|(?:__|_[A-Za-z0-9_.:+ -]+):|[A-Za-z0-9_.-]+(?::[A-Za-z0-9_.-]+)*::)(?:[ \t].*|$))$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'datatype', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(:{1}[^:\\n]+:)(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'function', $matches[2]);
            $this->appendRestInline($matches[3], $tokens);
            return;
        }

        if (preg_match('/^([ \t]*)([=\\-~`^"\'+#*]{3,})([ \t]*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'region', $matches[2]);
            $this->appendToken($tokens, 'text', $matches[3]);
            return;
        }

        if (preg_match('/^([ \t]*)(?:([-+*])|([0-9]+[.)]))([ \t]+)(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'operator', ($matches[2] !== '' ? $matches[2] : $matches[3]) . $matches[4]);
            $this->appendRestInline($matches[5], $tokens);
            return;
        }

        $this->appendRestInline($line, $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendRestInline(string $text, array &$tokens): void
    {
        $this->scanInto($text, [
            ['datatype', '/^``[^`\\n]+``/'],
            ['function', '/^\\|[^|\\n]+\\|/'],
            ['function', '/^_`[^`\\n]+`/'],
            ['attribute', '/^\\[[A-Za-z0-9_.:+#*-]+\\]_/'],
            ['keyword', '/^:[A-Za-z0-9_.+-]+:(?=`)/'],
            ['constant', '/^`[^`\\n]+`(?=:[A-Za-z0-9_.+-]+:)/'],
            ['attribute', '/^`[^`\\n]+`__?/'],
            ['constant', '/^`[^`\\n]+`/'],
            ['attribute', '/^(?:https?|ftp):\\/\\/[^\\s<>"\'`)]+[^\\s!"\'`(),.:;<>?~\\]\\}]/'],
            ['keyword', '/^\\*\\*[^*\\n][^\\n]*?\\*\\*/'],
            ['variable', '/^\\*[^*\\n][^\\n]*?\\*/'],
            ['datatype', '/^::(?=\\s*$)/'],
            ['operator', '/^[\\\\`*_{}\\[\\]():|<>-]/'],
        ], $tokens);
    }

    private static function restLineStartsCodeBlock(string $line): bool
    {
        return preg_match('/^\\s*\\.\\.[ \t]+code(?:-block)?::(?:[ \t]|$)/', $line) === 1
            || preg_match('/::\\s*$/', $line) === 1;
    }

    /**
     * @param list<array{0:string, 1:string}> $patterns
     * @return list<array{type:string, text:string, class:string}>
     */
    private function scan(string $code, array $patterns): array
    {
        $tokens = [];
        $this->scanInto($code, $patterns, $tokens);

        return $tokens;
    }

    /**
     * @param list<array{0:string, 1:string}> $patterns
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function scanInto(string $code, array $patterns, array &$tokens): void
    {
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $slice = substr($code, $offset);
            foreach ($patterns as $pattern) {
                $type = (string) ($pattern['type'] ?? $pattern[0] ?? 'text');
                $regex = (string) ($pattern['pattern'] ?? $pattern[1] ?? '');
                if ($regex !== '' && preg_match($regex, $slice, $matches) === 1 && $matches[0] !== '') {
                    $text = $matches[0];
                    $this->appendToken($tokens, $type, $text);
                    $offset += strlen($text);
                    continue 2;
                }
            }

            $this->appendToken($tokens, 'text', $code[$offset]);
            $offset++;
        }
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendToken(array &$tokens, string $type, string $text): void
    {
        if ($text === '') {
            return;
        }

        $class = self::TOKEN_CLASSES[$type] ?? '';
        $last = count($tokens) - 1;
        if ($last >= 0 && $tokens[$last]['type'] === $type && $tokens[$last]['class'] === $class) {
            $tokens[$last]['text'] .= $text;
            return;
        }

        $tokens[] = [
            'type' => $type,
            'text' => $text,
            'class' => $class,
        ];
    }

    private static function stripLanguagePrefix(string $language): string
    {
        $language = trim($language);
        if (str_starts_with(strtolower($language), 'language-')) {
            return substr($language, strlen('language-'));
        }

        return $language;
    }

    private static function isStructuralClass(string $class): bool
    {
        return in_array(self::normalizedClassName($class), [
            'line-anchors',
            'lineanchors',
            'number',
            'number-lines',
            'numberlines',
            'sourcecode',
            'title-attributes',
            'titleattributes',
            'token-title-attributes',
            'token-titleattributes',
            'tokentitle-attributes',
            'tokentitleattributes',
            'token-titles',
            'tokentitles',
            'line-highlight',
            'linehighlight',
            'highlight-lines',
            'highlightlines',
        ], true);
    }

    /**
     * @param array{
     *   id?: string,
     *   classes?: array<int, mixed>,
     *   attributes?: array<string, mixed>
     * } $options
     * @return array{
     *   numberLines: bool,
     *   lineAnchors: bool,
     *   startNumber: int,
     *   lineIdPrefix: string,
     *   containerClasses: list<string>,
     *   tokenTitles: bool,
     *   highlightLines: list<int>
     * }
     */
    private static function lineNumberingOptions(array $options): array
    {
        $classes = [];
        foreach (($options['classes'] ?? []) as $class) {
            $class = trim((string) $class);
            if ($class !== '') {
                $classes[] = $class;
            }
        }

        $normalized = array_map(self::normalizedClassName(...), $classes);
        $attributes = $options['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }
        $start = 1;
        foreach (['startFrom', 'start-from'] as $name) {
            if (isset($attributes[$name]) && preg_match('/^-?\d+$/', (string) $attributes[$name]) === 1) {
                $start = (int) $attributes[$name];
                break;
            }
        }

        $id = self::sanitizeId((string) ($options['id'] ?? ''));
        $highlightLines = self::highlightLineNumbers($options, $attributes, $start);
        $tokenTitles = self::optionBoolean($options['tokenTitles'] ?? null);
        if ($tokenTitles === null) {
            $tokenTitles = in_array('title-attributes', $normalized, true)
                || in_array('titleattributes', $normalized, true)
                || in_array('token-title-attributes', $normalized, true)
                || in_array('token-titleattributes', $normalized, true)
                || in_array('tokentitle-attributes', $normalized, true)
                || in_array('tokentitleattributes', $normalized, true)
                || in_array('token-titles', $normalized, true)
                || in_array('tokentitles', $normalized, true)
                || self::attributeBoolean($attributes, [
                    'data-title-attributes',
                    'data-token-title-attributes',
                    'data-token-titles',
                    'title-attributes',
                    'titleAttributes',
                    'token-title-attributes',
                    'tokenTitleAttributes',
                    'token-titles',
                    'tokenTitles',
                ]);
        }

        return [
            'numberLines' => in_array('number', $normalized, true)
                || in_array('numberlines', $normalized, true)
                || in_array('number-lines', $normalized, true),
            'lineAnchors' => in_array('lineanchors', $normalized, true)
                || in_array('line-anchors', $normalized, true),
            'startNumber' => $start,
            'lineIdPrefix' => $id === '' ? '' : $id . '-',
            'containerClasses' => $classes,
            'tokenTitles' => $tokenTitles,
            'highlightLines' => $highlightLines,
        ];
    }

    private static function normalizedClassName(string $class): string
    {
        return str_replace('_', '-', strtolower(self::stripLanguagePrefix(trim($class))));
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     * @param array{
     *   numberLines?: bool,
     *   lineAnchors?: bool,
     *   startNumber?: int,
     *   lineIdPrefix?: string,
     *   containerClasses?: list<string>,
     *   tokenTitles?: bool,
     *   highlightLines?: list<int>
     * } $options
     */
    private static function renderLineNumberedHtml(array $tokens, string $language, array $options): string
    {
        $numberLines = (bool) ($options['numberLines'] ?? false);
        $tokenTitles = (bool) ($options['tokenTitles'] ?? false);
        $startNumber = (int) ($options['startNumber'] ?? 1);
        $lineIdPrefix = (string) ($options['lineIdPrefix'] ?? '');
        $highlightLines = array_fill_keys(array_map('intval', $options['highlightLines'] ?? []), true);
        $containerClasses = ['sourceCode'];
        if ($numberLines) {
            $containerClasses[] = 'numberSource';
        }

        foreach (($options['containerClasses'] ?? []) as $class) {
            $class = self::sanitizeClass((string) $class);
            if ($class !== '' && !in_array($class, $containerClasses, true)) {
                $containerClasses[] = $class;
            }
        }

        $codeClasses = ['sourceCode'];
        if ($language !== '') {
            $codeClasses[] = self::sanitizeClass($language);
        }

        $codeAttributes = ' class="' . implode(' ', $codeClasses) . '"';
        if ($startNumber !== 1) {
            $codeAttributes .= ' style="counter-reset: source-line ' . ($startNumber - 1) . ';"';
        }

        $lineHtml = [];
        foreach (self::splitTokensIntoLines($tokens) as $index => $lineTokens) {
            $lineNumber = $startNumber + $index;
            $lineId = self::escapeHtml($lineIdPrefix . (string) $lineNumber);
            $lineClasses = [];
            $lineAttributes = '';
            if (isset($highlightLines[$lineNumber])) {
                $lineClasses[] = 'highlighted-line';
                $lineAttributes = ' data-pandoc-line-highlight="' . self::escapeHtml((string) $lineNumber) . '"';
            }
            $line = '<span id="' . $lineId . '"'
                . ($lineClasses === [] ? '' : ' class="' . implode(' ', $lineClasses) . '"')
                . $lineAttributes
                . '><a href="#' . $lineId . '"';
            if (!$numberLines) {
                $line .= ' aria-hidden="true" tabindex="-1"';
            }
            $line .= '></a>';
            foreach ($lineTokens as $token) {
                $line .= self::renderTokenHtml($token, $tokenTitles);
            }
            $line .= '</span>';
            $lineHtml[] = $line;
        }

        return '<div class="sourceCode"><pre class="' . implode(' ', $containerClasses) . '"><code' . $codeAttributes . '>'
            . implode("\n", $lineHtml)
            . '</code></pre></div>';
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     * @return list<list<array{type:string, text:string, class:string}>>
     */
    private static function splitTokensIntoLines(array $tokens): array
    {
        $lines = [[]];
        foreach ($tokens as $token) {
            $text = (string) ($token['text'] ?? '');
            $segments = explode("\n", $text);
            foreach ($segments as $index => $segment) {
                if ($index > 0) {
                    $lines[] = [];
                }
                if ($segment === '') {
                    continue;
                }

                $copy = $token;
                $copy['text'] = $segment;
                $lines[count($lines) - 1][] = $copy;
            }
        }

        if (count($lines) > 1 && $lines[count($lines) - 1] === []) {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $attributes
     * @return list<int>
     */
    private static function highlightLineNumbers(array $options, array $attributes, int $startNumber): array
    {
        $raw = $options['highlightLines'] ?? null;
        if ($raw === null) {
            foreach ([
                'highlight-lines',
                'highlightLines',
                'data-highlight-lines',
                'data-line-highlight',
                'line-highlight',
                'lineHighlight',
                'hl-lines',
                'hl_lines',
            ] as $name) {
                if (array_key_exists($name, $attributes)) {
                    $raw = $attributes[$name];
                    break;
                }
            }
        }

        if ($raw === null || $raw === false) {
            return [];
        }

        if (is_bool($raw)) {
            return [];
        }

        $lines = [];
        $items = is_array($raw) ? $raw : preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($items ?: [] as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            if (preg_match('/^(-?\d+)(?:\.\.|-)(-?\d+)$/', $item, $match) === 1) {
                $from = (int) $match[1];
                $to = (int) $match[2];
                if ($from <= 0 || $to <= 0) {
                    continue;
                }
                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }
                for ($line = $from; $line <= $to; $line++) {
                    $lines[] = $line;
                }
                continue;
            }

            if (preg_match('/^\d+$/', $item) === 1) {
                $lines[] = (int) $item;
            }
        }

        $absolute = self::optionBoolean($options['highlightLinesAbsolute'] ?? null);
        if ($absolute === null) {
            $absolute = self::attributeBoolean($attributes, [
                'highlight-lines-absolute',
                'highlightLinesAbsolute',
                'data-highlight-lines-absolute',
                'hl-lines-absolute',
            ]);
        }

        $lines = array_values(array_unique(array_filter($lines, static fn (int $line): bool => $line > 0)));
        sort($lines);
        if ($lines === [] || $absolute) {
            return $lines;
        }

        return array_map(static fn (int $line): int => $startNumber + $line - 1, $lines);
    }

    /**
     * @return array<string, string>
     */
    private static function styleColors(string $style): array
    {
        $palettes = [
            'breezedark' => [
                'background' => '#232629',
                'text' => '#eff0f1',
                'keyword' => '#3daee9',
                'datatype' => '#27ae60',
                'string' => '#fdbc4b',
                'comment' => '#7f8c8d',
                'number' => '#f67400',
                'function' => '#8e44ad',
                'variable' => '#da4453',
                'attribute' => '#1cdc9a',
                'operator' => '#c0392b',
                'preprocessor' => '#16a085',
                'constant' => '#9b59b6',
                'information' => '#27ae60',
                'region' => '#3daee9',
                'warning' => '#da4453',
            ],
            'monochrome' => [
                'background' => 'transparent',
                'text' => 'inherit',
                'keyword' => 'inherit',
                'datatype' => 'inherit',
                'string' => 'inherit',
                'comment' => 'inherit',
                'number' => 'inherit',
                'function' => 'inherit',
                'variable' => 'inherit',
                'attribute' => 'inherit',
                'operator' => 'inherit',
                'preprocessor' => 'inherit',
                'constant' => 'inherit',
                'information' => 'inherit',
                'region' => 'inherit',
                'warning' => 'inherit',
            ],
            'zenburn' => [
                'background' => '#3f3f3f',
                'text' => '#dcdccc',
                'keyword' => '#f0dfaf',
                'datatype' => '#dfdfbf',
                'string' => '#cc9393',
                'comment' => '#7f9f7f',
                'number' => '#8cd0d3',
                'function' => '#efef8f',
                'variable' => '#dc8cc3',
                'attribute' => '#dfaf8f',
                'operator' => '#f0efd0',
                'preprocessor' => '#ffcfaf',
                'constant' => '#dca3a3',
                'information' => '#7f9f7f',
                'region' => '#efef8f',
                'warning' => '#e37170',
            ],
        ];

        $fallbacks = [
            'background' => '#f8f8f8',
            'text' => '#1f2328',
            'keyword' => '#005cc5',
            'datatype' => '#6f42c1',
            'string' => '#032f62',
            'comment' => '#6a737d',
            'number' => '#005cc5',
            'function' => '#6f42c1',
            'variable' => '#e36209',
            'attribute' => '#22863a',
            'operator' => '#d73a49',
            'preprocessor' => '#d73a49',
            'constant' => '#005cc5',
            'information' => '#22863a',
            'region' => '#6f42c1',
            'warning' => '#b31d28',
        ];

        if (isset($palettes[$style])) {
            return $palettes[$style];
        }

        if ($style === 'espresso') {
            return [
                ...$fallbacks,
                'background' => '#2a211c',
                'text' => '#f8f8f8',
                'keyword' => '#c5656b',
                'string' => '#a6c673',
                'comment' => '#7f7066',
                'function' => '#ffc66d',
            ];
        }

        if ($style === 'tango') {
            return [
                ...$fallbacks,
                'background' => '#fdf6e3',
                'keyword' => '#204a87',
                'string' => '#4e9a06',
                'comment' => '#8f5902',
                'function' => '#5c3566',
            ];
        }

        if ($style === 'kate' || $style === 'haddock') {
            return [
                ...$fallbacks,
                'background' => '#ffffff',
                'keyword' => '#007020',
                'string' => '#4070a0',
                'comment' => '#60a0b0',
                'function' => '#06287e',
            ];
        }

        return $fallbacks;
    }

    /**
     * @param array<string, mixed> $theme
     * @return array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}|null
     */
    private static function themeTokenStyle(array $theme, string $tokenName): ?array
    {
        $tokenStyles = self::arrayValue($theme, ['token-styles', 'tokenStyles', 'text-styles', 'textStyles']);
        if (!is_array($tokenStyles)) {
            return null;
        }

        foreach ($tokenStyles as $name => $style) {
            if (self::normalizedThemeTokenName((string) $name) === self::normalizedThemeTokenName($tokenName) && is_array($style)) {
                return self::parseTokenStyle($style, "token style {$tokenName}");
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $style
     * @return array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}
     */
    private static function parseTokenStyle(array $style, string $label): array
    {
        $parsed = [];
        $color = self::themeColor($style, ['text-color', 'textColor', 'color', 'tokenColor'], "{$label} text color");
        $background = self::themeColor($style, ['background-color', 'backgroundColor', 'background', 'tokenBackground'], "{$label} background color");
        if ($color !== null) {
            $parsed['color'] = $color;
        }
        if ($background !== null) {
            $parsed['background'] = $background;
        }

        foreach (['bold', 'italic', 'underline'] as $flag) {
            $value = self::arrayValue($style, [$flag, 'token' . ucfirst($flag)]);
            if ($value !== null) {
                $parsed[$flag] = self::themeBool($value, "{$label} {$flag}");
            }
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $names
     */
    private static function themeColor(array $values, array $names, string $label): ?string
    {
        $value = self::arrayValue($values, $names);

        return $value === null ? null : self::normalizeThemeColor($value, $label);
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $names
     */
    private static function arrayValue(array $values, array $names): mixed
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $values)) {
                return $values[$name];
            }
        }

        return null;
    }

    private static function normalizeThemeColor(mixed $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
                return strtolower($value);
            }
            if (preg_match('/^#[0-9A-Fa-f]{3}$/', $value) === 1) {
                return strtolower('#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3]);
            }
            if (in_array(strtolower($value), ['inherit', 'transparent'], true)) {
                return strtolower($value);
            }
        }

        if (is_int($value) && $value >= 0 && $value <= 0xFFFFFF) {
            return sprintf('#%06x', $value);
        }

        if (is_array($value) && count($value) === 3) {
            $channels = array_values($value);
            foreach ($channels as $channel) {
                if (!is_int($channel) || $channel < 0 || $channel > 255) {
                    throw new \InvalidArgumentException("Invalid {$label} channel in Pandoc highlight theme");
                }
            }

            return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
        }

        throw new \InvalidArgumentException("Invalid {$label} in Pandoc highlight theme");
    }

    private static function themeBool(mixed $value, string $label): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes' => true,
                '0', 'false', 'no' => false,
                default => throw new \InvalidArgumentException("Invalid {$label} flag in Pandoc highlight theme"),
            };
        }

        throw new \InvalidArgumentException("Invalid {$label} flag in Pandoc highlight theme");
    }

    private static function tokenTypeFromThemeName(string $name): ?string
    {
        $normalized = self::normalizedThemeTokenName($name);
        if ($normalized === 'normal' || $normalized === 'normaltok') {
            return null;
        }

        return self::TOKEN_STYLE_ALIASES[$normalized] ?? self::TOKEN_STYLE_ALIASES[$normalized . 'tok'] ?? null;
    }

    private static function normalizedThemeTokenName(string $name): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '');
    }

    /**
     * @param array<string, string> $colors
     * @param array<string, array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}> $tokenStyles
     * @param list<string> $defaultDeclarations
     */
    private static function tokenStylesheetRule(
        string $selector,
        string $class,
        string $type,
        array $colors,
        array $tokenStyles,
        array $defaultDeclarations = []
    ): string {
        $style = $tokenStyles[$type] ?? null;
        $declarations = ['color: ' . ($style['color'] ?? $colors[$type])];
        if (isset($style['background'])) {
            $declarations[] = 'background-color: ' . $style['background'];
        }
        if (isset($style['bold'])) {
            if ($style['bold']) {
                $declarations[] = 'font-weight: 700';
            }
        } else {
            array_push($declarations, ...$defaultDeclarations);
        }
        if (isset($style['italic'])) {
            if ($style['italic']) {
                $declarations[] = 'font-style: italic';
            }
        } elseif (!isset($style['bold'])) {
            foreach ($defaultDeclarations as $declaration) {
                if (str_starts_with($declaration, 'font-style:')) {
                    $declarations[] = $declaration;
                }
            }
        }
        if (($style['underline'] ?? false) === true) {
            $declarations[] = 'text-decoration: underline';
        }

        return "{$selector} .{$class} { " . implode('; ', array_values(array_unique($declarations))) . '; }';
    }

    /**
     * @param array<string, string> $colors
     */
    private static function lineNumberStylesheetRule(array $colors): string
    {
        $declarations = [
            'content: counter(source-line)',
            'position: relative',
            'left: -1em',
            'text-align: right',
            'vertical-align: baseline',
            'border: none',
            'display: inline-block',
            'user-select: none',
            'padding: 0 4px',
            'width: 4em',
        ];

        if (isset($colors['lineNumber'])) {
            $declarations[] = 'color: ' . $colors['lineNumber'];
        }
        if (isset($colors['lineNumberBackground'])) {
            $declarations[] = 'background-color: ' . $colors['lineNumberBackground'];
        }

        return 'pre.numberSource code > span > a:first-child::before { ' . implode('; ', $declarations) . '; }';
    }

    private static function sanitizeClass(string $class): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $class) ?? '';
    }

    private static function sanitizeStyleName(string $style): string
    {
        $style = strtolower(trim($style));
        $style = preg_replace('/[^a-z0-9_-]+/', '-', $style) ?? '';
        $style = trim($style, '-_');

        return $style === '' ? 'custom-theme' : $style;
    }

    /**
     * @param array{type?:string, text?:string, class?:string} $token
     */
    private static function renderTokenHtml(array $token, bool $tokenTitles): string
    {
        $text = self::escapeHtml((string) ($token['text'] ?? ''));
        $class = self::sanitizeClass((string) ($token['class'] ?? ''));
        if ($class === '') {
            return $text;
        }

        $attributes = ' class="' . $class . '"';
        $title = $tokenTitles ? self::tokenTitle((string) ($token['type'] ?? '')) : null;
        if ($title !== null) {
            $attributes .= ' title="' . self::escapeHtml($title) . '"';
        }

        return '<span' . $attributes . '>' . $text . '</span>';
    }

    private static function tokenTitle(string $type): ?string
    {
        return self::TOKEN_TITLES[$type] ?? null;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string> $names
     */
    private static function attributeBoolean(array $attributes, array $names): bool
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $attributes)) {
                return self::optionBoolean($attributes[$name]) ?? true;
            }
        }

        return false;
    }

    private static function optionBoolean(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '', '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => null,
            };
        }

        return null;
    }

    private static function sanitizeId(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_.:-]/', '', $id) ?? '';
    }

    private static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
