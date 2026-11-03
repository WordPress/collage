<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class UpstreamRunnerDependencyAudit
{
    public const UPSTREAM_COMMIT = '0640c4c9859aa5a3ede082c190fcd5883c24ac83';

    private const REQUIRED_FILES = [
        'cabal.project',
        'pandoc.cabal',
        'pandoc-lua-engine/pandoc-lua-engine.cabal',
        'pandoc-server/pandoc-server.cabal',
        'pandoc-cli/pandoc-cli.cabal',
        'test/test-pandoc.hs',
        'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
    ];

    private const STABLE_PLAN_FILES = [
        'cabal.project',
        'cabal.project.freeze',
    ];

    private const REQUIRED_RUNNER_ARTIFACTS = [
        'test/Tests/Command.hs' => 'file',
        'test/Tests/Readers/Markdown.hs' => 'file',
        'test/Tests/Writers/Markdown.hs' => 'file',
        'test/Tests/Writers/Native.hs' => 'file',
        'test/command' => 'directory',
        'test/tables' => 'directory',
        'test/testsuite.txt' => 'file',
        'test/testsuite.native' => 'file',
        'test/markdown-reader-more.txt' => 'file',
        'test/markdown-reader-more.native' => 'file',
        'test/html-reader.html' => 'file',
        'test/html-reader.native' => 'file',
        'pandoc-lua-engine/test/Tests/Lua.hs' => 'file',
        'pandoc-lua-engine/test/Tests/Lua/Module.hs' => 'file',
        'pandoc-lua-engine/test/Tests/Lua/Reader.hs' => 'file',
        'pandoc-lua-engine/test/Tests/Lua/Writer.hs' => 'file',
        'pandoc-lua-engine/test' => 'directory',
        'data' => 'directory',
    ];

    private const REQUIRED_TOOLS = [
        'ghc',
        'cabal',
    ];

    private const TESTED_GHC_VERSIONS = [
        '9.6.7',
        '9.8.4',
        '9.10.3',
        '9.12.2',
    ];

    private const PACKAGE_IDENTITIES = [
        'pandoc.cabal' => [
            'name' => 'pandoc',
            'version' => '3.9.0.2',
            'cabalVersion' => '2.4',
            'buildType' => 'Simple',
        ],
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => [
            'name' => 'pandoc-lua-engine',
            'version' => '0.5.2',
            'cabalVersion' => '2.4',
            'buildType' => 'Simple',
        ],
        'pandoc-server/pandoc-server.cabal' => [
            'name' => 'pandoc-server',
            'version' => '0.1.2',
            'cabalVersion' => '2.4',
            'buildType' => 'Simple',
        ],
        'pandoc-cli/pandoc-cli.cabal' => [
            'name' => 'pandoc-cli',
            'version' => '3.9.0.2',
            'cabalVersion' => '2.4',
            'buildType' => 'Simple',
        ],
    ];

    private const PACKAGE_EXPECTED_SETUP_DEPENDENCIES = [
        'pandoc.cabal' => [],
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => [],
        'pandoc-server/pandoc-server.cabal' => [],
        'pandoc-cli/pandoc-cli.cabal' => [],
    ];

    private const PACKAGE_EXPECTED_FLAG_DEFINITIONS = [
        'pandoc.cabal' => [
            'embed_data_files',
            'http',
        ],
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => [],
        'pandoc-server/pandoc-server.cabal' => [],
        'pandoc-cli/pandoc-cli.cabal' => [
            'lua',
            'nightly',
            'repl',
            'server',
        ],
    ];

    private const PACKAGE_EXPECTED_FLAG_FIELDS = [
        'pandoc.cabal' => [
            'embed_data_files' => [
                'default' => 'False',
                'manual' => 'True',
            ],
            'http' => [
                'default' => 'True',
                'manual' => 'True',
            ],
        ],
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => [],
        'pandoc-server/pandoc-server.cabal' => [],
        'pandoc-cli/pandoc-cli.cabal' => [
            'lua' => [
                'default' => 'True',
                'manual' => null,
            ],
            'nightly' => [
                'default' => 'False',
                'manual' => null,
            ],
            'repl' => [
                'default' => 'True',
                'manual' => null,
            ],
            'server' => [
                'default' => 'True',
                'manual' => null,
            ],
        ],
    ];

    private const PACKAGE_EXPECTED_DATA_FILES = [
        'pandoc.cabal' => [
            'COPYRIGHT',
            'MANUAL.txt',
            'citeproc/biblatex-localization/*.lbx.strings',
            'data/abbreviations',
            'data/bash_completion.tpl',
            'data/creole.lua',
            'data/default.csl',
            'data/docbook-entities.txt',
            'data/docx/[Content_Types].xml',
            'data/docx/_rels/.rels',
            'data/docx/docProps/app.xml',
            'data/docx/docProps/core.xml',
            'data/docx/docProps/custom.xml',
            'data/docx/word/_rels/document.xml.rels',
            'data/docx/word/_rels/footnotes.xml.rels',
            'data/docx/word/comments.xml',
            'data/docx/word/document.xml',
            'data/docx/word/fontTable.xml',
            'data/docx/word/footnotes.xml',
            'data/docx/word/numbering.xml',
            'data/docx/word/settings.xml',
            'data/docx/word/styles.xml',
            'data/docx/word/theme/theme1.xml',
            'data/docx/word/webSettings.xml',
            'data/dzslides/template.html',
            'data/epub.css',
            'data/init.lua',
            'data/odt/META-INF/manifest.xml',
            'data/odt/content.xml',
            'data/odt/manifest.rdf',
            'data/odt/meta.xml',
            'data/odt/mimetype',
            'data/odt/styles.xml',
            'data/pptx/[Content_Types].xml',
            'data/pptx/_rels/.rels',
            'data/pptx/docProps/app.xml',
            'data/pptx/docProps/core.xml',
            'data/pptx/ppt/_rels/presentation.xml.rels',
            'data/pptx/ppt/notesMasters/_rels/notesMaster1.xml.rels',
            'data/pptx/ppt/notesMasters/notesMaster1.xml',
            'data/pptx/ppt/notesSlides/_rels/notesSlide1.xml.rels',
            'data/pptx/ppt/notesSlides/_rels/notesSlide2.xml.rels',
            'data/pptx/ppt/notesSlides/notesSlide1.xml',
            'data/pptx/ppt/notesSlides/notesSlide2.xml',
            'data/pptx/ppt/presProps.xml',
            'data/pptx/ppt/presentation.xml',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout1.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout10.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout11.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout2.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout3.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout4.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout5.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout6.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout7.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout8.xml.rels',
            'data/pptx/ppt/slideLayouts/_rels/slideLayout9.xml.rels',
            'data/pptx/ppt/slideLayouts/slideLayout1.xml',
            'data/pptx/ppt/slideLayouts/slideLayout10.xml',
            'data/pptx/ppt/slideLayouts/slideLayout11.xml',
            'data/pptx/ppt/slideLayouts/slideLayout2.xml',
            'data/pptx/ppt/slideLayouts/slideLayout3.xml',
            'data/pptx/ppt/slideLayouts/slideLayout4.xml',
            'data/pptx/ppt/slideLayouts/slideLayout5.xml',
            'data/pptx/ppt/slideLayouts/slideLayout6.xml',
            'data/pptx/ppt/slideLayouts/slideLayout7.xml',
            'data/pptx/ppt/slideLayouts/slideLayout8.xml',
            'data/pptx/ppt/slideLayouts/slideLayout9.xml',
            'data/pptx/ppt/slideMasters/_rels/slideMaster1.xml.rels',
            'data/pptx/ppt/slideMasters/slideMaster1.xml',
            'data/pptx/ppt/slides/_rels/slide1.xml.rels',
            'data/pptx/ppt/slides/_rels/slide2.xml.rels',
            'data/pptx/ppt/slides/_rels/slide3.xml.rels',
            'data/pptx/ppt/slides/_rels/slide4.xml.rels',
            'data/pptx/ppt/slides/slide1.xml',
            'data/pptx/ppt/slides/slide2.xml',
            'data/pptx/ppt/slides/slide3.xml',
            'data/pptx/ppt/slides/slide4.xml',
            'data/pptx/ppt/tableStyles.xml',
            'data/pptx/ppt/theme/theme1.xml',
            'data/pptx/ppt/theme/theme2.xml',
            'data/pptx/ppt/viewProps.xml',
            'data/templates/affiliations.jats',
            'data/templates/after-header-includes.latex',
            'data/templates/article.jats_publishing',
            'data/templates/common.latex',
            'data/templates/default.ansi',
            'data/templates/default.asciidoc',
            'data/templates/default.bbcode',
            'data/templates/default.beamer',
            'data/templates/default.biblatex',
            'data/templates/default.bibtex',
            'data/templates/default.chunkedhtml',
            'data/templates/default.commonmark',
            'data/templates/default.context',
            'data/templates/default.djot',
            'data/templates/default.docbook4',
            'data/templates/default.docbook5',
            'data/templates/default.dokuwiki',
            'data/templates/default.dzslides',
            'data/templates/default.epub2',
            'data/templates/default.epub3',
            'data/templates/default.haddock',
            'data/templates/default.html4',
            'data/templates/default.html5',
            'data/templates/default.icml',
            'data/templates/default.jats_archiving',
            'data/templates/default.jats_articleauthoring',
            'data/templates/default.jats_publishing',
            'data/templates/default.jira',
            'data/templates/default.latex',
            'data/templates/default.man',
            'data/templates/default.markdown',
            'data/templates/default.markua',
            'data/templates/default.mediawiki',
            'data/templates/default.ms',
            'data/templates/default.muse',
            'data/templates/default.opendocument',
            'data/templates/default.openxml',
            'data/templates/default.opml',
            'data/templates/default.org',
            'data/templates/default.plain',
            'data/templates/default.revealjs',
            'data/templates/default.rst',
            'data/templates/default.rtf',
            'data/templates/default.s5',
            'data/templates/default.slideous',
            'data/templates/default.slidy',
            'data/templates/default.tei',
            'data/templates/default.texinfo',
            'data/templates/default.textile',
            'data/templates/default.typst',
            'data/templates/default.vimdoc',
            'data/templates/default.xwiki',
            'data/templates/default.zimwiki',
            'data/templates/document-metadata.latex',
            'data/templates/font-settings.latex',
            'data/templates/fonts.latex',
            'data/templates/hypersetup.latex',
            'data/templates/passoptions.latex',
            'data/templates/styles.citations.html',
            'data/templates/styles.html',
            'data/templates/template.typst',
            'data/translations/*.yaml',
        ],
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => [],
        'pandoc-server/pandoc-server.cabal' => [],
        'pandoc-cli/pandoc-cli.cabal' => [],
    ];

    private const PACKAGE_EXPECTED_EXTRA_DOC_FILE_GLOBS = [
        'pandoc.cabal' => <<<'CABAL'
changelog.md,
AUTHORS.md,
INSTALL.md,
README.md,
CONTRIBUTING.md,
BUGS
CABAL,
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => '',
        'pandoc-server/pandoc-server.cabal' => '',
        'pandoc-cli/pandoc-cli.cabal' => '',
    ];

    private const PACKAGE_EXPECTED_EXTRA_SOURCE_FILE_GLOBS = [
        'pandoc.cabal' => <<<'CABAL'
test/bodybg.gif
test/*.native
test/command/*.md
test/command/*.csl
test/command/*.svg
test/command/7691.docx
test/command/9391.docx
test/command/9358.docx
test/command/9002.docx
test/command/9603.docx
test/command/11113.docx
test/command/biblio.bib
test/command/averroes.bib
test/command/A.txt
test/command/B.txt
test/command/C.txt
test/command/D.txt
test/command/file1.txt
test/command/file2.txt
test/command/three.txt
test/command/11090/ch1.typ
test/command/01.csv
test/command/chap1/spider.png
test/command/chap2/spider.png
test/command/chap1/text.md
test/command/chap2/text.md
test/command/defaults1.yaml
test/command/defaults2.yaml
test/command/defaults3.yaml
test/command/defaults4.yaml
test/command/defaults5.yaml
test/command/defaults6.yaml
test/command/defaults7.yaml
test/command/defaults8.yaml
test/command/defaults9.yaml
test/command/8024a.yaml
test/command/8024b.yaml
test/command/3533-rst-csv-tables.csv
test/command/3880.txt
test/command/5182.txt
test/command/5700-metadata-file-1.yml
test/command/5700-metadata-file-2.yml
test/command/abbrevs
test/command/sub-file-chapter-1.tex
test/command/sub-file-chapter-2.tex
test/command/bar.tex
test/command/bar-endinput.tex
test/command/yaml-metadata.yaml
test/command/7813-meta.yaml
test/command/3510-subdoc.org
test/command/3510-export.latex
test/command/3510-src.hs
test/command/3971b.tex
test/command/5876.yaml
test/command/5876/metadata/5876.yaml
test/command/5876/metadata/command/5876.yaml
test/command/6466-beg.hs
test/command/6466-end.hs
test/command/6466-mid.hs
test/command/6466-whole.hs
test/command/7861.yaml
test/command/7861/metadata/placeholder
test/command/11486/scroll.revealjs
test/command/11498.png
test/asciidoc-reader.adoc
test/asciidoc-reader.native
test/asciidoc-reader-include.rb
test/asciidoc-reader-include.adoc
test/docbook-chapter.docbook
test/docbook-reader.docbook
test/docbook-xref.docbook
test/endnotexml-reader.xml
test/html-reader.html
test/opml-reader.opml
test/org-select-tags.org
test/haddock-reader.haddock
test/insert
test/lalune.jpg
test/man-reader.man
test/movie.jpg
test/media/rId25.jpg
test/media/rId26.jpg
test/media/rId27.jpg
test/typst-reader.typ
test/undergradmath.typ
test/djot-reader.djot
test/latex-reader.latex
test/textile-reader.textile
test/markdown-reader-more.txt
test/markdown-citations.txt
test/textile-reader.textile
test/mediawiki-reader.wiki
test/vimwiki-reader.wiki
test/creole-reader.txt
test/rst-reader.rst
test/jats-reader.xml
test/jira-reader.jira
test/s5-basic.html
test/s5-fancy.html
test/s5-fragment.html
test/s5-inserts.html
test/tables.context
test/tables.docbook4
test/tables.docbook5
test/tables.jats_archiving
test/tables.jats_articleauthoring
test/tables.jats_publishing
test/tables.jira
test/tables.djot
test/tables.dokuwiki
test/tables.zimwiki
test/tables.icml
test/tables.html4
test/tables.html5
test/tables.latex
test/tables.man
test/tables.ms
test/tables.plain
test/tables.markdown
test/tables.markua
test/tables.mediawiki
test/tables.tei
test/tables.textile
test/tables.opendocument
test/tables.org
test/tables.asciidoc
test/tables.asciidoc_legacy
test/tables.haddock
test/tables.texinfo
test/tables.typst
test/tables.rst
test/tables.rtf
test/tables.txt
test/tables.fb2
test/tables.muse
test/tables.vimdoc
test/tables.bbcode
test/tables.xwiki
test/tables/*.html4
test/tables/*.html5
test/tables/*.latex
test/tables/*.typst
test/tables/*.native
test/tables/*.mediawiki
test/tables/*.jats_archiving
test/tables/*.markdown
test/testsuite.txt
test/ansi-test.txt
test/writer.latex
test/writer.context
test/writer.djot
test/writer.docbook4
test/writer.docbook5
test/writer.jats_archiving
test/writer.jats_articleauthoring
test/writer.jats_publishing
test/writer.jira
test/writer.html4
test/writer.html5
test/writer.man
test/writer.ms
test/writer.markdown
test/writer.markua
test/writer.plain
test/writer.mediawiki
test/writer.textile
test/writer.typst
test/writer.opendocument
test/writer.org
test/writer.asciidoc
test/writer.asciidoc_legacy
test/writer.haddock
test/writer.rst
test/writer.icml
test/writer.rtf
test/writer.tei
test/writer.texinfo
test/writer.fb2
test/writer.opml
test/writer.dokuwiki
test/writer.zimwiki
test/writer.xwiki
test/writer.muse
test/writer.vimdoc
test/writer.bbcode
test/ansi-test.ansi
test/writers-lang-and-dir.latex
test/writers-lang-and-dir.context
test/dokuwiki_inline_formatting.dokuwiki
test/lhs-test.markdown
test/lhs-test.markdown+lhs
test/lhs-test.rst
test/lhs-test.rst+lhs
test/lhs-test.latex
test/lhs-test.latex+lhs
test/lhs-test.html
test/lhs-test.html+lhs
test/lhs-test.fragment.html+lhs
test/pipe-tables.txt
test/dokuwiki_external_images.dokuwiki
test/dokuwiki_multiblock_table.dokuwiki
test/fb2/*.markdown
test/fb2/*.fb2
test/fb2/images-embedded.html
test/fb2/images-embedded.fb2
test/fb2/test-small.png
test/fb2/reader/*.fb2
test/fb2/reader/*.native
test/fb2/test.jpg
test/docx/*.docx
test/docx/golden/*.docx
test/docx/*.native
test/epub/*.epub
test/epub/*.native
test/rtf/*.native
test/rtf/*.rtf
test/pptx/*.pptx
test/pptx/**/*.pptx
test/pptx/**/*.native
test/pptx-reader/basic.pptx
test/pptx-reader/basic.native
test/ipynb/*.native
test/ipynb/*.in.native
test/ipynb/*.out.native
test/ipynb/*.ipynb
test/ipynb/*.out.ipynb
test/ipynb/*.out.html
test/txt2tags.t2t
test/twiki-reader.twiki
test/tikiwiki-reader.tikiwiki
test/odt/odt/*.odt
test/odt/markdown/*.md
test/odt/native/*.native
test/xlsx-reader/*.xlsx
test/xlsx-reader/*.native
test/pod-reader.pod
test/vimdoc/*.markdown
test/vimdoc/*.vimdoc
CABAL,
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => <<<'CABAL'
README.md
test/bytestring.bin
test/bytestring.lua
test/bytestring-reader.lua
test/extensions.lua
test/lua/*.lua
test/lua/module/*.lua
test/lua/module/include.tex
test/lua/module/partial.test
test/lua/module/sample.svg
test/lua/module/sample.epub
test/lua/module/tiny.epub
test/sample.lua
test/tables.custom
test/tables.native
test/testsuite.native
test/writer.custom
test/writer-template.lua
test/writer-template.out.txt
CABAL,
        'pandoc-server/pandoc-server.cabal' => '',
        'pandoc-cli/pandoc-cli.cabal' => <<<'CABAL'
man/pandoc.1
man/pandoc-lua.1
man/pandoc-server.1
CABAL,
    ];

    private const PACKAGE_EXPECTED_EXTRA_TMP_FILE_GLOBS = [
        'pandoc.cabal' => '',
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => '',
        'pandoc-server/pandoc-server.cabal' => '',
        'pandoc-cli/pandoc-cli.cabal' => '',
    ];

    private const PACKAGE_EXPECTED_NATIVE_SYSTEM_FIELDS = [
        'pandoc.cabal' => [],
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => [],
        'pandoc-server/pandoc-server.cabal' => [],
        'pandoc-cli/pandoc-cli.cabal' => [],
    ];

    private const PACKAGE_EXPECTED_SOURCE_REPOSITORIES = [
        'pandoc.cabal' => [
            'head' => [
                'type' => 'git',
                'location' => 'https://github.com/jgm/pandoc.git',
            ],
        ],
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => [
            'head' => [
                'type' => 'git',
                'location' => 'https://github.com/jgm/pandoc.git',
            ],
        ],
        'pandoc-server/pandoc-server.cabal' => [
            'head' => [
                'type' => 'git',
                'location' => 'https://github.com/jgm/pandoc.git',
            ],
        ],
        'pandoc-cli/pandoc-cli.cabal' => [
            'head' => [
                'type' => 'git',
                'location' => 'https://github.com/jgm/pandoc.git',
            ],
        ],
    ];

    private const PROJECT_SOURCE_REPOSITORY_PINS = [
        'doclayout' => 'ef7f18308a61787244a80885d907fcd2c16604d4',
        'typst-symbols' => '6e97668c9f2ffea09f3187c34b7641038370fd21',
        'typst-hs' => '19e835d40663a92df5bed4e8a0fca5465cacdd6b',
        'texmath' => '0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
        'citeproc' => '1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd',
    ];

    private const PROJECT_SOURCE_REPOSITORIES = [
        'doclayout' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/doclayout.git',
        ],
        'typst-symbols' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/typst-symbols.git',
        ],
        'typst-hs' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/typst-hs.git',
        ],
        'texmath' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/texmath.git',
        ],
        'citeproc' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/citeproc.git',
        ],
    ];

    private const PROJECT_PACKAGES = [
        '.',
        'pandoc-lua-engine',
        'pandoc-server',
        'pandoc-cli',
    ];

    private const PROJECT_FLAGS = [
        'pandoc' => [
            'embed_data_files' => true,
            'http' => true,
        ],
    ];

    private const PROJECT_PACKAGE_EXPECTED_FIELDS = [
        'pandoc' => [
            'flags',
        ],
    ];

    private const PROJECT_CONSTRAINTS = [
        'auto-update' => '>= 0.2.6',
        'crypton' => '>= 1.1.1',
        'skylighting-format-blaze-html' => '>= 0.1.2',
        'skylighting-format-context' => '>= 0.1.0.2',
    ];

    private const PROJECT_EXPECTED_UNCONDITIONAL_FIELDS = [
        'constraints',
        'packages',
    ];

    private const PROJECT_EXPECTED_CONDITIONAL_BRANCHES = [];

    private const RUNNER_ENTRY_POINTS = [
        'test:test-pandoc' => [
            'packageFile' => 'pandoc.cabal',
            'type' => 'exitcode-stdio-1.0',
            'mainIs' => 'test-pandoc.hs',
            'sourceDirectory' => 'test',
        ],
        'test:test-pandoc-lua-engine' => [
            'packageFile' => 'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'type' => 'exitcode-stdio-1.0',
            'mainIs' => 'test-pandoc-lua-engine.hs',
            'sourceDirectory' => 'test',
        ],
    ];

    private const RUNNER_EXECUTABLE_OPTIONS = [
        'test:test-pandoc' => [
            '-rtsopts',
            '-with-rtsopts=-A8m',
            '-threaded',
        ],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_DEFAULT_LANGUAGES = [
        'test:test-pandoc' => 'Haskell2010',
        'test:test-pandoc-lua-engine' => 'Haskell2010',
    ];

    private const RUNNER_EXPECTED_MANUAL_FIELDS = [
        'test:test-pandoc' => null,
        'test:test-pandoc-lua-engine' => null,
    ];

    private const RUNNER_EXPECTED_COMMON_IMPORTS = [
        'test:test-pandoc' => [
            'common-executable',
            'common-options',
        ],
        'test:test-pandoc-lua-engine' => [
            'test-options',
        ],
    ];

    private const RUNNER_EXPECTED_MIXINS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_BUILD_TOOLS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_TEST_OPTIONS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_DEFAULT_EXTENSIONS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_OTHER_EXTENSIONS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_CPP_OPTIONS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_AUTOGEN_MODULES = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_REEXPORTED_MODULES = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_MODULE_INTERFACE_FIELDS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_EXTRA_SOURCE_FILES = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_EXTRA_DOC_FILES = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_EXTRA_TMP_FILES = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_DATA_FILES = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_CONDITIONAL_BRANCHES = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_NATIVE_SYSTEM_FIELDS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const BENCHMARK_ENTRY_POINTS = [
        'benchmark:benchmark-pandoc' => [
            'packageFile' => 'pandoc.cabal',
            'type' => 'exitcode-stdio-1.0',
            'mainIs' => 'benchmark-pandoc.hs',
            'sourceDirectory' => 'benchmark',
        ],
    ];

    private const BENCHMARK_DIRECT_DEPENDENCIES = [
        'benchmark:benchmark-pandoc' => [
            'base',
            'pandoc',
            'bytestring',
            'deepseq',
            'mtl',
            'tasty-bench',
            'text',
        ],
    ];

    private const BENCHMARK_DEPENDENCY_CONSTRAINTS = [
        'benchmark:benchmark-pandoc' => [
            'base' => '>= 4.18 && < 5',
            'mtl' => '>= 2.2 && < 2.4',
            'tasty-bench' => '>= 0.4 && <= 0.5',
            'text' => '>= 1.1.1.0 && < 2.2',
        ],
    ];

    private const BENCHMARK_EXECUTABLE_OPTIONS = [
        'benchmark:benchmark-pandoc' => [
            '-rtsopts',
            '-with-rtsopts=-A8m',
            '-threaded',
        ],
    ];

    private const BENCHMARK_DEFAULT_LANGUAGES = [
        'benchmark:benchmark-pandoc' => 'Haskell2010',
    ];

    private const BENCHMARK_EXPECTED_MANUAL_FIELDS = [
        'benchmark:benchmark-pandoc' => null,
    ];

    private const BENCHMARK_EXPECTED_COMMON_IMPORTS = [
        'benchmark:benchmark-pandoc' => [
            'common-executable',
            'common-options',
        ],
    ];

    private const BENCHMARK_EXPECTED_MIXINS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_BUILD_TOOLS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_BENCHMARK_OPTIONS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_DEFAULT_EXTENSIONS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_OTHER_EXTENSIONS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_CPP_OPTIONS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_AUTOGEN_MODULES = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_REEXPORTED_MODULES = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_MODULE_INTERFACE_FIELDS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_OTHER_MODULES = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_EXTRA_SOURCE_FILES = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_EXTRA_DOC_FILES = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_EXTRA_TMP_FILES = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_DATA_FILES = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_CONDITIONAL_BRANCHES = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_NATIVE_SYSTEM_FIELDS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const CABAL_NATIVE_SYSTEM_FIELDS = [
        'c-sources',
        'cxx-sources',
        'js-sources',
        'asm-sources',
        'cmm-sources',
        'extra-bundled-libraries',
        'extra-libraries',
        'extra-lib-dirs',
        'extra-lib-dirs-static',
        'include-dirs',
        'includes',
        'install-includes',
        'autogen-includes',
        'pkgconfig-depends',
        'frameworks',
        'extra-framework-dirs',
        'ld-options',
        'cc-options',
        'cxx-options',
        'ghc-prof-options',
        'ghc-shared-options',
        'ghcjs-options',
        'asm-options',
        'cmm-options',
        'js-options',
        'hsc2hs-options',
        'c2hs-options',
    ];

    private const CABAL_MODULE_INTERFACE_FIELDS = [
        'signatures',
        'virtual-modules',
    ];

    private const BENCHMARK_ARTIFACTS = [
        'benchmark/benchmark-pandoc.hs' => 'file',
        'test/testsuite.txt' => 'file',
        'test/lalune.jpg' => 'file',
        'test/movie.jpg' => 'file',
    ];

    private const BENCHMARK_ARTIFACT_SEMANTICS = [
        'test/testsuite.txt' => [
            'contains Pandoc suite title' => 'Pandoc Test Suite',
            'contains headers section' => '# Headers',
            'contains code blocks section' => '# Code Blocks',
            'contains block quotes section' => '# Block Quotes',
            'contains lists section' => '# Lists',
            'contains definition lists section' => '# Definition Lists',
            'contains html blocks section' => '# HTML Blocks',
            'contains inline markup section' => '# Inline Markup',
            'contains smart punctuation section' => '# Smart quotes, ellipses, dashes',
            'contains latex section' => '# LaTeX',
            'contains special characters section' => '# Special Characters',
            'contains links section' => '# Links',
            'contains images section' => '# Images',
            'contains footnotes section' => '# Footnotes',
            'contains lalune reference image' => '![lalune][]',
            'contains lalune reference definition' => '[lalune]: lalune.jpg "Voyage dans la Lune"',
            'contains movie inline image' => '![movie](movie.jpg)',
        ],
        'test/lalune.jpg' => [
            'has jpeg soi marker' => "\xff\xd8",
            'has jpeg eoi marker' => "\xff\xd9",
        ],
        'test/movie.jpg' => [
            'has jpeg soi marker' => "\xff\xd8",
            'has jpeg eoi marker' => "\xff\xd9",
        ],
    ];

    private const BENCHMARK_ENTRY_SOURCE_SEMANTICS = [
        'benchmark:benchmark-pandoc' => [
            'entryFile' => 'benchmark/benchmark-pandoc.hs',
            'requiredSnippets' => [
                'imports pandoc conversion registry' => 'import Text.Pandoc',
                'imports pandoc media MIME support' => 'import Text.Pandoc.MIME',
                'imports tasty benchmark harness' => 'import Test.Tasty.Bench',
                'skips bibliography-only formats' => 'name `elem` ["bibtex", "biblatex", "csljson"]',
                'resolves readers by flavored format' => 'getReader $ FlavoredFormat name mempty',
                'resolves writers by flavored format' => 'getWriter $ FlavoredFormat name mempty',
                'compiles default writer templates' => 'compileDefaultTemplate name',
                'benchmarks text readers' => 'TextReader r',
                'benchmarks bytestring readers' => 'ByteStringReader r',
                'loads image media fixture lalune' => 'B.readFile "test/lalune.jpg"',
                'loads image media fixture movie' => 'B.readFile "test/movie.jpg"',
                'inserts media before writer benchmark' => 'insertMedia fp (Just mt) bs',
                'reads benchmark testsuite fixture' => 'B.readFile "test/testsuite.txt"',
                'decodes benchmark markdown fixture as UTF-8' => 'UTF8.toText <$> B.readFile "test/testsuite.txt"',
                'raises reader benchmark failures with shown Pandoc errors' => 'either (error . show) id . runPure . r def',
                'reports text bytestring benchmark mismatches' => 'throwError $ PandocSomeError $ "text/bytestring format mismatch: " <> name',
                'parses markdown fixture into Pandoc AST' => 'readMarkdown opts inp',
                'forces parsed AST before benchmarking' => 'force $ runPure $ readMarkdown opts inp',
                'runs tasty benchmark main' => 'defaultMain',
                'wraps writer benchmarks in media environment' => 'env getImages',
                'groups writer benchmarks' => 'bgroup "writers"',
                'maps writers from registry into benchmark group' => 'mapMaybe (writerBench imgs doc . fst) (sortOn fst writers :: [(T.Text, Writer PandocPure)])',
                'groups reader benchmarks' => 'bgroup "readers"',
                'maps readers from registry into benchmark group' => 'mapMaybe (readerBench doc . fst) (sortOn fst readers :: [(T.Text, Reader PandocPure)])',
            ],
        ],
    ];

    private const FORMAT_REGISTRY_SOURCE_ARTIFACTS = [
        'src/Text/Pandoc/Readers.hs' => 'file',
        'src/Text/Pandoc/Writers.hs' => 'file',
        'src/Text/Pandoc/Format.hs' => 'file',
    ];

    private const FORMAT_REGISTRY_SOURCE_SEMANTICS = [
        'src/Text/Pandoc/Readers.hs' => [
            'exports roff man reader' => ', readMan',
            'imports roff man reader module' => 'import Text.Pandoc.Readers.Man',
            'registers man reader format' => '("man" , TextReader readMan)',
        ],
        'src/Text/Pandoc/Writers.hs' => [
            'exports roff man writer' => ', writeMan',
            'exports roff ms writer' => ', writeMs',
            'imports roff man writer module' => 'import Text.Pandoc.Writers.Man',
            'imports roff ms writer module' => 'import Text.Pandoc.Writers.Ms',
            'registers man writer format' => '("man" , TextWriter writeMan)',
            'registers ms writer format' => '("ms" , TextWriter writeMs)',
        ],
        'src/Text/Pandoc/Format.hs' => [
            'infers ms format from dot-ms files' => '".ms" -> defFlavor "ms"',
            'infers ms format from dot-roff files' => '".roff" -> defFlavor "ms"',
            'infers man format from numeric manual suffixes' => '[\'.\',y] | y `elem` [\'1\'..\'9\'] -> defFlavor "man"',
        ],
    ];

    private const CABAL_PLAN_COMMANDS = [
        'runner-test-dependencies' => [
            'program' => 'cabal',
            'arguments' => [
                'v2-build',
                '--offline',
                '--project-dir=.',
                '--builddir=.port-libs/pandoc-runner/cabal-build/runner-test-dependencies',
                '--dry-run',
                '--only-dependencies',
                '--enable-tests',
                '--disable-benchmarks',
                'test:test-pandoc',
                'test:test-pandoc-lua-engine',
            ],
            'targets' => [
                'test:test-pandoc',
                'test:test-pandoc-lua-engine',
            ],
            'component' => 'Pandoc Tasty runner dependency plan',
            'buildDirectory' => '.port-libs/pandoc-runner/cabal-build/runner-test-dependencies',
            'workingDirectory' => 'hydrated Pandoc upstream checkout root',
            'executionPolicy' => 'descriptor-only; do not execute from this isolated PHP lane',
            'outputCapture' => [
                'stdout/stderr command transcript',
                'solver plan summary',
                'optional dist-newstyle/cache/plan.json if Cabal creates it under the reviewed build directory',
            ],
        ],
        'benchmark-dependencies' => [
            'program' => 'cabal',
            'arguments' => [
                'v2-build',
                '--offline',
                '--project-dir=.',
                '--builddir=.port-libs/pandoc-runner/cabal-build/benchmark-dependencies',
                '--dry-run',
                '--only-dependencies',
                '--disable-tests',
                '--enable-benchmarks',
                'benchmark:benchmark-pandoc',
            ],
            'targets' => [
                'benchmark:benchmark-pandoc',
            ],
            'component' => 'Pandoc benchmark dependency plan',
            'buildDirectory' => '.port-libs/pandoc-runner/cabal-build/benchmark-dependencies',
            'workingDirectory' => 'hydrated Pandoc upstream checkout root',
            'executionPolicy' => 'descriptor-only; do not execute from this isolated PHP lane',
            'outputCapture' => [
                'stdout/stderr command transcript',
                'solver plan summary',
                'optional dist-newstyle/cache/plan.json if Cabal creates it under the reviewed build directory',
            ],
        ],
    ];

    private const CABAL_PLAN_WORKSPACE = [
        'environmentPolicy' => 'descriptor-only; do not read or print process environment values from this PHP lane',
        'workingDirectory' => 'hydrated Pandoc upstream checkout root',
        'environmentVariables' => [
            'CABAL_DIR' => '.port-libs/pandoc-runner/cabal',
            'CABAL_CONFIG' => '.port-libs/pandoc-runner/cabal/config',
            'XDG_CACHE_HOME' => '.port-libs/pandoc-runner/cache',
            'XDG_STATE_HOME' => '.port-libs/pandoc-runner/state',
            'TMPDIR' => '.port-libs/pandoc-runner/tmp',
        ],
        'buildDirectories' => [
            'runner-test-dependencies' => '.port-libs/pandoc-runner/cabal-build/runner-test-dependencies',
            'benchmark-dependencies' => '.port-libs/pandoc-runner/cabal-build/benchmark-dependencies',
        ],
        'transcriptFiles' => [
            'runner-test-dependencies' => '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
            'benchmark-dependencies' => '.port-libs/pandoc-runner/logs/benchmark-dependencies.txt',
        ],
        'optionalPlanJsonFiles' => [
            'runner-test-dependencies' => '.port-libs/pandoc-runner/plan/runner-test-dependencies-plan.json',
            'benchmark-dependencies' => '.port-libs/pandoc-runner/plan/benchmark-dependencies-plan.json',
        ],
        'preflight' => [
            'create the declared repo-local directories in the reviewed checkout or disposable audit root before any Cabal command is authorized',
            'keep Cabal dry-run output under .port-libs/pandoc-runner and do not use a default dist-newstyle directory',
            'do not inherit HOME-scoped Cabal store, global config, or user package DB paths; record no HOME-scoped Cabal store in lane evidence',
            'capture only variable names, relative paths, command arguments, and artifact hashes; do not print process environment values',
        ],
    ];

    private const RUNNER_OTHER_MODULES = [
        'test:test-pandoc' => [
            'Tests.Old',
            'Tests.Command',
            'Tests.Helpers',
            'Tests.Shared',
            'Tests.MediaBag',
            'Tests.XML',
            'Tests.Readers.LaTeX',
            'Tests.Readers.HTML',
            'Tests.Readers.JATS',
            'Tests.Readers.Jira',
            'Tests.Readers.Markdown',
            'Tests.Readers.Org',
            'Tests.Readers.Org.Block',
            'Tests.Readers.Org.Block.CodeBlock',
            'Tests.Readers.Org.Block.Figure',
            'Tests.Readers.Org.Block.Header',
            'Tests.Readers.Org.Block.List',
            'Tests.Readers.Org.Block.Table',
            'Tests.Readers.Org.Directive',
            'Tests.Readers.Org.Inline',
            'Tests.Readers.Org.Inline.Citation',
            'Tests.Readers.Org.Inline.Note',
            'Tests.Readers.Org.Inline.Smart',
            'Tests.Readers.Org.Meta',
            'Tests.Readers.Org.Shared',
            'Tests.Readers.RST',
            'Tests.Readers.RTF',
            'Tests.Readers.Docx',
            'Tests.Readers.Pptx',
            'Tests.Readers.Xlsx',
            'Tests.Readers.ODT',
            'Tests.Readers.Txt2Tags',
            'Tests.Readers.EPUB',
            'Tests.Readers.Muse',
            'Tests.Readers.Creole',
            'Tests.Readers.Man',
            'Tests.Readers.Mdoc',
            'Tests.Readers.FB2',
            'Tests.Readers.Pod',
            'Tests.Readers.DokuWiki',
            'Tests.Writers.Markdown',
            'Tests.Writers.Native',
            'Tests.Writers.ConTeXt',
            'Tests.Writers.DocBook',
            'Tests.Writers.HTML',
            'Tests.Writers.JATS',
            'Tests.Writers.Jira',
            'Tests.Writers.Org',
            'Tests.Writers.Plain',
            'Tests.Writers.AsciiDoc',
            'Tests.Writers.LaTeX',
            'Tests.Writers.Docx',
            'Tests.Writers.RST',
            'Tests.Writers.TEI',
            'Tests.Writers.Markua',
            'Tests.Writers.Muse',
            'Tests.Writers.FB2',
            'Tests.Writers.Powerpoint',
            'Tests.Writers.OOXML',
            'Tests.Writers.Ms',
            'Tests.Writers.AnnotatedTable',
            'Tests.Writers.BBCode',
        ],
        'test:test-pandoc-lua-engine' => [
            'Tests.Lua',
            'Tests.Lua.Module',
            'Tests.Lua.Reader',
            'Tests.Lua.Writer',
        ],
    ];

    private const RUNNER_ENTRY_SOURCE_SEMANTICS = [
        'test:test-pandoc' => [
            'entryFile' => 'test/test-pandoc.hs',
            'requiredSnippets' => [
                'sets locale encoding to utf8' => 'setLocaleEncoding utf8',
                'offers --emulate command runner path' => '"--emulate"',
                'uses noEngine for command emulation' => 'convertWithOpts noEngine',
                'catches command emulation exceptions' => 'E.catch',
                'parses --emulate args with default pandoc options' => "parseOptionsFromArgs options defaultOpts \"pandoc\" args'",
                'handles command option info with noEngine' => 'Left e -> handleOptInfo noEngine e',
                'converts parsed command options with noEngine' => 'Right opts -> convertWithOpts noEngine opts',
                'handles emulation errors through pandoc error handler' => '(handleError . Left)',
                'runs from upstream test directory' => 'inDirectory "test"',
                'passes executable path into old command tests' => 'getExecutablePath',
                'runs tasty defaultMain' => 'defaultMain $ tests fp',
                'loads command golden tests' => 'Tests.Command.tests',
                'loads old command tests' => 'Tests.Old.tests',
                'loads shared helper tests' => 'Tests.Shared.tests',
                'loads media bag tests' => 'Tests.MediaBag.tests',
                'loads xml tests' => 'Tests.XML.tests',
                'loads markdown reader tests' => 'Tests.Readers.Markdown.tests',
                'loads html reader tests' => 'Tests.Readers.HTML.tests',
                'loads jats reader tests' => 'Tests.Readers.JATS.tests',
                'loads jira reader tests' => 'Tests.Readers.Jira.tests',
                'loads org reader tests' => 'Tests.Readers.Org.tests',
                'loads latex reader tests' => 'Tests.Readers.LaTeX.tests',
                'loads rst reader tests' => 'Tests.Readers.RST.tests',
                'loads rtf reader tests' => 'Tests.Readers.RTF.tests',
                'loads docx reader tests' => 'Tests.Readers.Docx.tests',
                'loads pptx reader tests' => 'Tests.Readers.Pptx.tests',
                'loads xlsx reader tests' => 'Tests.Readers.Xlsx.tests',
                'loads odt reader tests' => 'Tests.Readers.ODT.tests',
                'loads txt2tags reader tests' => 'Tests.Readers.Txt2Tags.tests',
                'loads epub reader tests' => 'Tests.Readers.EPUB.tests',
                'loads muse reader tests' => 'Tests.Readers.Muse.tests',
                'loads creole reader tests' => 'Tests.Readers.Creole.tests',
                'loads man reader tests' => 'Tests.Readers.Man.tests',
                'loads mdoc reader tests' => 'Tests.Readers.Mdoc.tests',
                'loads fb2 reader tests' => 'Tests.Readers.FB2.tests',
                'loads dokuwiki reader tests' => 'Tests.Readers.DokuWiki.tests',
                'loads pod reader tests' => 'Tests.Readers.Pod.tests',
                'loads native writer tests' => 'Tests.Writers.Native.tests',
                'loads context writer tests' => 'Tests.Writers.ConTeXt.tests',
                'loads html writer tests' => 'Tests.Writers.HTML.tests',
                'loads jats writer tests' => 'Tests.Writers.JATS.tests',
                'loads jira writer tests' => 'Tests.Writers.Jira.tests',
                'loads latex writer tests' => 'Tests.Writers.LaTeX.tests',
                'loads markdown writer tests' => 'Tests.Writers.Markdown.tests',
                'loads org writer tests' => 'Tests.Writers.Org.tests',
                'loads plain writer tests' => 'Tests.Writers.Plain.tests',
                'loads docx writer tests' => 'Tests.Writers.Docx.tests',
                'loads rst writer tests' => 'Tests.Writers.RST.tests',
                'loads asciidoc writer tests' => 'Tests.Writers.AsciiDoc.tests',
                'loads docbook writer tests' => 'Tests.Writers.DocBook.tests',
                'loads tei writer tests' => 'Tests.Writers.TEI.tests',
                'loads markua writer tests' => 'Tests.Writers.Markua.tests',
                'loads muse writer tests' => 'Tests.Writers.Muse.tests',
                'loads fb2 writer tests' => 'Tests.Writers.FB2.tests',
                'loads powerpoint writer tests' => 'Tests.Writers.Powerpoint.tests',
                'loads ms writer tests' => 'Tests.Writers.Ms.tests',
                'loads annotated table writer tests' => 'Tests.Writers.AnnotatedTable.tests',
                'loads bbcode writer tests' => 'Tests.Writers.BBCode.tests',
            ],
        ],
        'test:test-pandoc-lua-engine' => [
            'entryFile' => 'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
            'requiredSnippets' => [
                'runs from lua engine test directory' => 'withCurrentDirectory "test"',
                'runs tasty defaultMain' => 'defaultMain tests',
                'names lua engine tasty group' => 'testGroup "pandoc Lua engine"',
                'loads lua filter tests' => 'Tests.Lua.tests',
                'loads lua module tests' => 'Tests.Lua.Module.tests',
                'loads custom writer tests' => 'Tests.Lua.Writer.tests',
                'loads custom reader tests' => 'Tests.Lua.Reader.tests',
            ],
        ],
    ];

    private const RUNNER_DIRECT_DEPENDENCIES = [
        'test:test-pandoc' => [
            'base',
            'pandoc',
            'Diff',
            'Glob',
            'bytestring',
            'containers',
            'directory',
            'doctemplates',
            'filepath',
            'mtl',
            'pandoc-types',
            'process',
            'tasty',
            'tasty-golden',
            'tasty-hunit',
            'tasty-quickcheck',
            'text',
            'temporary',
            'time',
            'xml',
            'zip-archive',
        ],
        'test:test-pandoc-lua-engine' => [
            'base',
            'pandoc-lua-engine',
            'bytestring',
            'directory',
            'data-default',
            'exceptions',
            'filepath',
            'hslua',
            'pandoc',
            'pandoc-types',
            'tasty',
            'tasty-golden',
            'tasty-hunit',
            'tasty-lua',
            'text',
        ],
    ];

    private const RUNNER_DEPENDENCY_CONSTRAINTS = [
        'test:test-pandoc' => [
            'base' => '>= 4.18 && < 5',
            'Diff' => '>= 0.2 && < 1.1',
            'Glob' => '>= 0.7 && < 0.11',
            'bytestring' => '>= 0.9 && < 0.13',
            'containers' => '>= 0.4.2.1 && < 0.9',
            'directory' => '>= 1.2.3 && < 1.4',
            'doctemplates' => '>= 0.11 && < 0.12',
            'filepath' => '>= 1.1 && < 1.6',
            'mtl' => '>= 2.2 && < 2.4',
            'pandoc-types' => '>= 1.23.1 && < 1.24',
            'process' => '>= 1.2.3 && < 1.7',
            'tasty' => '>= 0.11 && < 1.6',
            'tasty-golden' => '>= 2.3 && < 2.4',
            'tasty-hunit' => '>= 0.9 && < 0.11',
            'tasty-quickcheck' => '>= 0.8 && < 0.12',
            'text' => '>= 1.1.1.0 && < 2.2',
            'temporary' => '>= 1.1 && < 1.4',
            'time' => '>= 1.5 && < 1.16',
            'xml' => '>= 1.3.12 && < 1.4',
            'zip-archive' => '>= 0.4.3 && < 0.5',
        ],
        'test:test-pandoc-lua-engine' => [
            'base' => '>= 4.12 && < 5',
            'exceptions' => '>= 0.8 && < 0.11',
            'hslua' => '>= 2.5 && < 2.6',
            'pandoc-types' => '>= 1.22 && < 1.24',
            'tasty-lua' => '>= 1.1 && < 1.2',
            'text' => '>= 1.1.1 && < 2.2',
        ],
    ];

    private const LUA_ENGINE_LIBRARY_DEPENDENCIES = [
        'hslua-module-doclayout',
        'hslua-module-path',
        'hslua-module-system',
        'hslua-module-text',
        'hslua-module-version',
        'hslua-module-zip',
        'lpeg',
        'pandoc-lua-marshal',
    ];

    private const LUA_ENGINE_LIBRARY_EXPOSED_MODULES = [
        'Text.Pandoc.Lua',
    ];

    private const LUA_ENGINE_LIBRARY_SOURCE_DIRECTORIES = [
        'src',
    ];

    private const LUA_ENGINE_LIBRARY_OTHER_MODULES = [
        'Text.Pandoc.Lua.Custom',
        'Text.Pandoc.Lua.Documentation',
        'Text.Pandoc.Lua.Engine',
        'Text.Pandoc.Lua.Filter',
        'Text.Pandoc.Lua.Global',
        'Text.Pandoc.Lua.Init',
        'Text.Pandoc.Lua.Module',
        'Text.Pandoc.Lua.Marshal.Chunks',
        'Text.Pandoc.Lua.Marshal.CommonState',
        'Text.Pandoc.Lua.Marshal.Context',
        'Text.Pandoc.Lua.Marshal.Format',
        'Text.Pandoc.Lua.Marshal.ImageSize',
        'Text.Pandoc.Lua.Marshal.LogMessage',
        'Text.Pandoc.Lua.Marshal.PandocError',
        'Text.Pandoc.Lua.Marshal.ReaderOptions',
        'Text.Pandoc.Lua.Marshal.Reference',
        'Text.Pandoc.Lua.Marshal.Sources',
        'Text.Pandoc.Lua.Marshal.Template',
        'Text.Pandoc.Lua.Marshal.WriterOptions',
        'Text.Pandoc.Lua.Module.CLI',
        'Text.Pandoc.Lua.Module.Format',
        'Text.Pandoc.Lua.Module.Image',
        'Text.Pandoc.Lua.Module.JSON',
        'Text.Pandoc.Lua.Module.Log',
        'Text.Pandoc.Lua.Module.MediaBag',
        'Text.Pandoc.Lua.Module.Pandoc',
        'Text.Pandoc.Lua.Module.Path',
        'Text.Pandoc.Lua.Module.Scaffolding',
        'Text.Pandoc.Lua.Module.Structure',
        'Text.Pandoc.Lua.Module.System',
        'Text.Pandoc.Lua.Module.Template',
        'Text.Pandoc.Lua.Module.Text',
        'Text.Pandoc.Lua.Module.Types',
        'Text.Pandoc.Lua.Module.Utils',
        'Text.Pandoc.Lua.Orphans',
        'Text.Pandoc.Lua.PandocLua',
        'Text.Pandoc.Lua.Run',
        'Text.Pandoc.Lua.SourcePos',
        'Text.Pandoc.Lua.Writer.Classic',
        'Text.Pandoc.Lua.Writer.Scaffolding',
    ];

    private const LUA_ENGINE_LIBRARY_DEFAULT_LANGUAGE = 'Haskell2010';

    private const LUA_ENGINE_LIBRARY_ALLOWED_CONDITIONAL_BRANCHES = [
        'library default: if flag(repl)',
    ];

    private const LUA_ENGINE_LIBRARY_EXPECTED_MIXINS = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_BUILD_TOOLS = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_AUTOGEN_MODULES = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_REEXPORTED_MODULES = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_MODULE_INTERFACE_FIELDS = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_DEFAULT_EXTENSIONS = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_OTHER_EXTENSIONS = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_SOURCE_FILES = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_DOC_FILES = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_TMP_FILES = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_DATA_FILES = [];

    private const LUA_ENGINE_LIBRARY_EXPECTED_NATIVE_SYSTEM_FIELDS = [];

    private const SERVER_LIBRARY_PACKAGE_FILE = 'pandoc-server/pandoc-server.cabal';

    private const SERVER_LIBRARY_DEPENDENCIES = [
        'aeson',
        'base',
        'base64-bytestring',
        'bytestring',
        'containers',
        'data-default',
        'doctemplates',
        'pandoc',
        'pandoc-types',
        'servant-server',
        'skylighting',
        'text',
        'unicode-collation',
        'wai',
        'wai-cors',
    ];

    private const SERVER_LIBRARY_DEPENDENCY_CONSTRAINTS = [
        'aeson' => '>= 2.0 && < 2.3',
        'base' => '>= 4.12 && < 5',
        'base64-bytestring' => '>= 0.1 && < 1.3',
        'bytestring' => '>= 0.9 && < 0.13',
        'containers' => '>= 0.6.0.1 && < 0.9',
        'data-default' => '>= 0.4 && < 0.9',
        'doctemplates' => '>= 0.11 && < 0.12',
        'pandoc' => '>= 3.9 && < 3.10',
        'pandoc-types' => '>= 1.22 && < 1.24',
        'servant-server' => '>= 0.19 && < 0.21',
        'skylighting' => '>= 0.13 && < 0.15',
        'text' => '>= 1.1.1.0 && < 2.2',
        'unicode-collation' => '>= 0.1.1 && < 0.2',
        'wai' => '>= 3.2 && < 3.3',
        'wai-cors' => '>= 0.2.7 && < 0.3',
    ];

    private const SERVER_LIBRARY_EXPOSED_MODULES = [
        'Text.Pandoc.Server',
    ];

    private const SERVER_LIBRARY_SOURCE_DIRECTORIES = [
        'src',
    ];

    private const SERVER_LIBRARY_DEFAULT_LANGUAGE = 'Haskell2010';

    private const CLI_EXECUTABLE_PACKAGE_FILE = 'pandoc-cli/pandoc-cli.cabal';

    private const CLI_EXECUTABLE_NAME = 'pandoc';

    private const CLI_EXECUTABLE_MAIN_IS = 'pandoc.hs';

    private const CLI_EXECUTABLE_DEPENDENCIES = [
        'base',
        'pandoc',
        'text',
    ];

    private const CLI_EXECUTABLE_DEPENDENCY_CONSTRAINTS = [
        'base' => '>= 4.18 && < 5',
        'pandoc' => '== 3.9.0.2',
    ];

    private const CLI_EXECUTABLE_SOURCE_DIRECTORIES = [
        'src',
    ];

    private const CLI_EXECUTABLE_GHC_OPTIONS = [
        '-Wall',
        '-fno-warn-unused-do-bind',
        '-Wincomplete-record-updates',
        '-Wnoncanonical-monad-instances',
        '-Wcpp-undef',
        '-Wincomplete-uni-patterns',
        '-Widentities',
        '-Wpartial-fields',
        '-Wmissing-signatures',
        '-fhide-source-paths',
        '-Wunused-packages',
        '-Winvalid-haddock',
        '-rtsopts',
        '-with-rtsopts=-A8m',
    ];

    private const CLI_EXECUTABLE_DEFAULT_LANGUAGE = 'Haskell2010';

    private const CLI_EXECUTABLE_COMMON_IMPORTS = [
        'common-executable',
        'common-options',
    ];

    private const CLI_EXECUTABLE_OTHER_EXTENSIONS = [
        'OverloadedStrings',
    ];

    private const CLI_EXECUTABLE_OTHER_MODULES = [
        'PandocCLI.Lua',
        'PandocCLI.Server',
    ];

    private const CLI_EXECUTABLE_EXPECTED_CONDITIONAL_BRANCHES = [
        'common common-options: if os(windows)',
        'executable pandoc: if arch(wasm32)',
        'executable pandoc: else',
        'executable pandoc: if flag(nightly)',
        'executable pandoc: if flag(server)',
        'executable pandoc: if flag(lua)',
        'executable pandoc: if flag(repl)',
    ];

    private const CLI_EXECUTABLE_EXPECTED_CONDITIONAL_FIELD_CLOSURE = [
        'common common-options: if os(windows)' => [
            'sourceDirectories' => [],
            'ghcOptions' => [],
            'cppOptions' => ['-D_WINDOWS'],
            'buildDepends' => [],
            'otherModules' => [],
            'nativeSystemFields' => [],
        ],
        'executable pandoc: if arch(wasm32)' => [
            'sourceDirectories' => ['wasm'],
            'ghcOptions' => ['-optl-Wl,--export=__wasm_call_ctors,--export=hs_init_with_rtsopts,--export=malloc,--export=convert,--export=query'],
            'cppOptions' => ['-DINCLUDE_WASM'],
            'buildDepends' => ['aeson', 'bytestring', 'containers', 'filepath', 'pandoc-lua-engine', 'skylighting'],
            'otherModules' => ['PandocWasm'],
            'nativeSystemFields' => [],
        ],
        'executable pandoc: else after if arch(wasm32)' => [
            'sourceDirectories' => [],
            'ghcOptions' => ['-threaded'],
            'cppOptions' => [],
            'buildDepends' => [],
            'otherModules' => [],
            'nativeSystemFields' => [],
        ],
        'executable pandoc: if flag(nightly)' => [
            'sourceDirectories' => [],
            'ghcOptions' => [],
            'cppOptions' => ['-DNIGHTLY'],
            'buildDepends' => ['template-haskell', 'time'],
            'otherModules' => [],
            'nativeSystemFields' => [],
        ],
        'executable pandoc: if flag(server)' => [
            'sourceDirectories' => ['server'],
            'ghcOptions' => [],
            'cppOptions' => [],
            'buildDepends' => ['pandoc-server >= 0.1.1 && < 0.2', 'safe', 'wai-extra >= 3.0.24', 'warp'],
            'otherModules' => [],
            'nativeSystemFields' => [],
        ],
        'executable pandoc: else after if flag(server)' => [
            'sourceDirectories' => ['no-server'],
            'ghcOptions' => [],
            'cppOptions' => [],
            'buildDepends' => [],
            'otherModules' => [],
            'nativeSystemFields' => [],
        ],
        'executable pandoc: if flag(lua)' => [
            'sourceDirectories' => ['lua'],
            'ghcOptions' => [],
            'cppOptions' => [],
            'buildDepends' => ['pandoc-lua-engine >= 0.5.1 && < 0.6'],
            'otherModules' => [],
            'nativeSystemFields' => [],
        ],
        'executable pandoc: else after if flag(lua)' => [
            'sourceDirectories' => ['no-lua'],
            'ghcOptions' => [],
            'cppOptions' => [],
            'buildDepends' => [],
            'otherModules' => [],
            'nativeSystemFields' => [],
        ],
        'executable pandoc: if flag(repl)' => [
            'sourceDirectories' => [],
            'ghcOptions' => [],
            'cppOptions' => ['-DREPL'],
            'buildDepends' => ['hslua-cli >= 1.4.1 && < 1.5', 'temporary >= 1.1 && < 1.4'],
            'otherModules' => [],
            'nativeSystemFields' => [],
        ],
    ];

    private const CLI_EXECUTABLE_SOURCE_ARTIFACTS = [
        'pandoc-cli/src/pandoc.hs' => 'file',
        'pandoc-cli/wasm/PandocWasm.hs' => 'file',
        'pandoc-cli/server/PandocCLI/Server.hs' => 'file',
        'pandoc-cli/no-server/PandocCLI/Server.hs' => 'file',
        'pandoc-cli/lua/PandocCLI/Lua.hs' => 'file',
        'pandoc-cli/no-lua/PandocCLI/Lua.hs' => 'file',
    ];

    private const CLI_EXECUTABLE_SOURCE_SEMANTICS = [
        'pandoc-cli/src/pandoc.hs' => [
            'imports lua shim module' => 'import PandocCLI.Lua',
            'imports server shim module' => 'import PandocCLI.Server',
            'detects short and long version options' => 's == "-v" || s == "--version"',
            'stops version detection at option separator' => 'takeWhile (/= "--") rawArgs',
            'guards commands with version handler' => 'let versionOr action = if hasVersion then versionInfoCLI else action',
            'routes server executable name' => '"pandoc-server" -> versionOr $ runServer rawArgs',
            'routes server subcommand through version handler' => '"server": args -> versionOr $ runServer args',
            'routes lua subcommand' => '"lua" : args -> runLuaInterpreter "pandoc lua" args',
            'uses selected scripting engine for conversion' => 'engine <- getEngine',
            'parses options with executable program name' => 'parseOptionsFromArgs options defaultOpts prg args',
            'handles option info with selected engine' => 'Left e -> handleOptInfo engine e',
            'reports server feature flag' => '#ifdef VERSION_pandoc_server',
            'reports lua feature flag' => '#ifdef VERSION_hslua_cli',
            'reports feature list and scripting engine in version output' => 'versionInfo getFeatures (Just $ T.unpack (engineName scriptingEngine)) versionSuffix',
        ],
        'pandoc-cli/wasm/PandocWasm.hs' => [
            'exports convert symbol' => 'foreign export ccall "convert" convert',
            'exports query symbol' => 'foreign export ccall "query" query',
            'loads cli scripting engine' => 'engine <- getEngine',
            'writes virtual stdout' => 'BL.writeFile "/stdout"',
            'supports default-template query' => '"default-template" -> DefaultTemplate <$> o .: "format"',
        ],
        'pandoc-cli/server/PandocCLI/Server.hs' => [
            'exports server functions' => 'module PandocCLI.Server ( runCGI , runServer ) where',
            'runs cgi with timeout middleware' => 'CGI.run (timeout cgiTimeout app)',
            'parses server opts' => 'parseServerOptsFromArgs args',
            'runs warp on configured port' => 'Warp.run (serverPort sopts) (timeout (serverTimeout sopts) app)',
        ],
        'pandoc-cli/no-server/PandocCLI/Server.hs' => [
            'exports server placeholders' => 'module PandocCLI.Server ( runCGI , runServer ) where',
            'routes cgi placeholder to unsupported handler' => 'runCGI = serverUnsupported',
            'routes server placeholder to unsupported handler' => 'runServer _args = serverUnsupported',
            'exits with unsupported status' => 'exitWith $ ExitFailure 4',
        ],
        'pandoc-cli/lua/PandocCLI/Lua.hs' => [
            'exports lua interpreter and engine' => 'module PandocCLI.Lua (runLuaInterpreter, getEngine) where',
            'guards repl-specific code' => '#ifdef REPL',
            'runs standalone lua settings' => 'runStandalone settings progName args',
            'falls back when repl support absent' => 'Pandoc not compiled with Lua interpreter support.',
            'loads pandoc lua engine' => 'import Text.Pandoc.Lua (runLua, runLuaNoEnv, getEngine)',
        ],
        'pandoc-cli/no-lua/PandocCLI/Lua.hs' => [
            'exports lua placeholders' => 'module PandocCLI.Lua (runLuaInterpreter, getEngine) where',
            'raises no scripting engine' => 'handleError (Left PandocNoScriptingEngine)',
            'returns no engine placeholder' => 'getEngine = pure noEngine',
        ],
    ];

    /**
     * @param array<string, string|array{available?: bool, version?: string|null}> $tools
     * @return array{
     *   upstreamCommit:string,
     *   checkoutPath:string,
     *   requiredFiles:list<string>,
     *   presentFiles:list<string>,
     *   missingFiles:list<string>,
     *   requiredFileProvenance:array{expected:list<string>, present:array<string, array{sha256:string, bytes:int}>, missing:list<string>},
     *   tools:array<string, array{available:bool, version:string|null}>,
     *   missingTools:list<string>,
     *   planStabilityClosure:array{expectedStablePlanFiles:list<string>, present:array<string, array{sha256:string, bytes:int}>, missing:list<string>, wrongType:array<string, string>, emptyFiles:list<string>, invalidFiles:array<string, string>, unpinnedPlanRisk:bool},
     *   compilerTestedWithClosure:array{packageFile:string, expectedGhcVersions:list<string>, presentGhcVersions:list<string>, missingGhcVersions:list<string>, toolGhcVersion:string|null, toolGhcVersionSupported:bool},
     *   packageIdentityClosure:array{expected:array<string, array{name:string, version:string, cabalVersion:string, buildType:string}>, present:array<string, array{name:string|null, version:string|null, cabalVersion:string|null, buildType:string|null}>, missingHeaders:array<string, list<string>>, mismatchedHeaders:array<string, array<string, array{expected:string, actual:string|null}>>},
     *   packageSetupClosure:array{expectedSetupDependencies:array<string, list<string>>, present:array<string, array{customSetup:bool, setupDepends:list<string>, dependencyConstraints:array<string, string>}>, unexpectedCustomSetupStanzas:array<string, list<string>>, unexpectedSetupDependencies:array<string, list<string>>},
     *   packageFlagDefinitionClosure:array{expectedFlags:array<string, list<string>>, presentFlags:array<string, list<string>>, missingFlags:array<string, list<string>>, unexpectedFlags:array<string, list<string>>, expectedFlagFields:array<string, array<string, array{default:string|null, manual:string|null}>>, presentFlagFields:array<string, array<string, array{default:string|null, manual:string|null}>>, mismatchedFlagFields:array<string, array<string, array<string, array{expected:string|null, actual:string|null}>>>},
     *   packageDataFileClosure:array{expectedDataFiles:array<string, list<string>>, presentDataFiles:array<string, list<string>>, missingDataFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>},
     *   packageExtraFileClosure:array{expectedExtraDocFiles:array<string, list<string>>, presentExtraDocFiles:array<string, list<string>>, missingExtraDocFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, expectedExtraSourceFiles:array<string, list<string>>, presentExtraSourceFiles:array<string, list<string>>, missingExtraSourceFiles:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, expectedExtraTmpFiles:array<string, list<string>>, presentExtraTmpFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>},
     *   packageNativeSystemFieldClosure:array{expectedNativeSystemFields:array<string, array<string, list<string>>>, presentNativeSystemFields:array<string, array<string, list<string>>>, unexpectedNativeSystemFields:array<string, list<string>>},
     *   packageSourceRepositoryClosure:array{expected:array<string, array<string, array{type:string, location:string}>>, present:array<string, array<string, array{type:string|null, location:string|null, fields:array<string, string>}>>, missing:array<string, list<string>>, mismatched:array<string, array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string|null}}>>, unexpected:array<string, list<string>>, unexpectedFields:array<string, array<string, list<string>>>},
     *   runnerTargets:list<string>,
     *   runnerEntryPoints:array<string, array{packageFile:string, type:string, mainIs:string, sourceDirectory:string}>,
     *   benchmarkTargets:list<string>,
     *   benchmarkEntryPoints:array<string, array{packageFile:string, type:string, mainIs:string, sourceDirectory:string}>,
     *   projectSourceRepositoryPins:array{expected:array<string, string>, present:array<string, string>, missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>},
     *   projectSourceRepositoryClosure:array{expected:array<string, array{type:string, location:string}>, present:array<string, array{type:string|null, location:string, tag:string|null, fields:array<string, string>}>, missing:list<string>, mismatched:array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string}>>, unexpected:list<string>, unexpectedFields:array<string, list<string>>},
     *   projectPackageClosure:array{expectedPackages:list<string>, presentPackages:list<string>, missingPackages:list<string>, unexpectedPackages:list<string>, expectedFlags:array<string, array<string, bool>>, presentFlags:array<string, array<string, bool>>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>, unexpectedFlags:array<string, list<string>>, expectedPackageFields:array<string, list<string>>, presentPackageFields:array<string, array<string, string>>, unexpectedPackageFields:array<string, list<string>>},
     *   projectConstraintClosure:array{expectedConstraints:array<string, string>, presentConstraints:array<string, string>, missingConstraints:list<string>, mismatchedConstraints:array<string, array{expected:string, actual:string}>, unexpectedConstraints:list<string>},
     *   projectUnconditionalFieldClosure:array{expectedFields:list<string>, presentFields:list<string>, present:array<string, string>, unexpectedFields:list<string>},
     *   projectConditionalBranchClosure:array{expectedBranches:list<string>, presentBranches:list<string>, missingBranches:list<string>, unexpectedBranches:list<string>},
     *   runnerDependencyClosure:array{expectedDependencies:array<string, list<string>>, expectedDependencyConstraints:array<string, array<string, string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedManualFields:array<string, string|null>, expectedSourceDirectories:array<string, list<string>>, expectedMixins:array<string, list<string>>, expectedBuildTools:array<string, list<string>>, expectedTestOptions:array<string, list<string>>, expectedDefaultExtensions:array<string, list<string>>, expectedOtherExtensions:array<string, list<string>>, expectedCppOptions:array<string, list<string>>, expectedAutogenModules:array<string, list<string>>, expectedReexportedModules:array<string, list<string>>, expectedModuleInterfaceFields:array<string, array<string, list<string>>>, expectedExtraSourceFiles:array<string, list<string>>, expectedExtraDocFiles:array<string, list<string>>, expectedExtraTmpFiles:array<string, list<string>>, expectedDataFiles:array<string, list<string>>, expectedNativeSystemFields:array<string, array<string, list<string>>>, expectedOtherModules:array<string, list<string>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, manual:string|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, cppOptions:list<string>, autogenModules:list<string>, reexportedModules:list<string>, moduleInterfaceFields:array<string, list<string>>, extraSourceFiles:list<string>, extraDocFiles:list<string>, extraTmpFiles:list<string>, dataFiles:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, testOptions:list<string>, defaultExtensions:list<string>, otherExtensions:list<string>, otherModules:list<string>, nativeSystemFields:array<string, list<string>>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, unexpectedDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, unexpectedExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, mismatchedManualFields:array<string, array{expected:string|null, actual:string|null}>, unexpectedSourceDirectories:array<string, list<string>>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, unexpectedTestOptions:array<string, list<string>>, unexpectedDefaultExtensions:array<string, list<string>>, unexpectedOtherExtensions:array<string, list<string>>, unexpectedCppOptions:array<string, list<string>>, unexpectedAutogenModules:array<string, list<string>>, unexpectedReexportedModules:array<string, list<string>>, unexpectedModuleInterfaceFields:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>, unexpectedNativeSystemFields:array<string, list<string>>, missingOtherModules:array<string, list<string>>, unexpectedOtherModules:array<string, list<string>>},
     *   benchmarkDependencyClosure:array{expectedDependencies:array<string, list<string>>, expectedDependencyConstraints:array<string, array<string, string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedManualFields:array<string, string|null>, expectedSourceDirectories:array<string, list<string>>, expectedMixins:array<string, list<string>>, expectedBuildTools:array<string, list<string>>, expectedBenchmarkOptions:array<string, list<string>>, expectedDefaultExtensions:array<string, list<string>>, expectedOtherExtensions:array<string, list<string>>, expectedCppOptions:array<string, list<string>>, expectedAutogenModules:array<string, list<string>>, expectedReexportedModules:array<string, list<string>>, expectedModuleInterfaceFields:array<string, array<string, list<string>>>, expectedOtherModules:array<string, list<string>>, expectedExtraSourceFiles:array<string, list<string>>, expectedExtraDocFiles:array<string, list<string>>, expectedExtraTmpFiles:array<string, list<string>>, expectedDataFiles:array<string, list<string>>, expectedNativeSystemFields:array<string, array<string, list<string>>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, manual:string|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, cppOptions:list<string>, autogenModules:list<string>, reexportedModules:list<string>, moduleInterfaceFields:array<string, list<string>>, otherModules:list<string>, extraSourceFiles:list<string>, extraDocFiles:list<string>, extraTmpFiles:list<string>, dataFiles:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, benchmarkOptions:list<string>, defaultExtensions:list<string>, otherExtensions:list<string>, nativeSystemFields:array<string, list<string>>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, unexpectedDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, unexpectedExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, mismatchedManualFields:array<string, array{expected:string|null, actual:string|null}>, unexpectedSourceDirectories:array<string, list<string>>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, unexpectedBenchmarkOptions:array<string, list<string>>, unexpectedDefaultExtensions:array<string, list<string>>, unexpectedOtherExtensions:array<string, list<string>>, unexpectedCppOptions:array<string, list<string>>, unexpectedAutogenModules:array<string, list<string>>, unexpectedReexportedModules:array<string, list<string>>, unexpectedModuleInterfaceFields:array<string, list<string>>, unexpectedOtherModules:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>, unexpectedNativeSystemFields:array<string, list<string>>},
     *   luaEngineLibraryClosure:array{packageFile:string, expectedDependencies:list<string>, presentDependencies:list<string>, dependencyConstraints:array<string, string>, missingDependencies:list<string>, unexpectedDependencies:list<string>, expectedExposedModules:list<string>, presentExposedModules:list<string>, missingExposedModules:list<string>, unexpectedExposedModules:list<string>, expectedDefaultLanguage:string, presentDefaultLanguage:string|null, mismatchedDefaultLanguage:array{expected:string, actual:string|null}|null, expectedMixins:list<string>, presentMixins:list<string>, unexpectedMixins:list<string>, expectedBuildTools:list<string>, presentBuildToolDepends:list<string>, presentBuildTools:list<string>, unexpectedBuildTools:list<string>, expectedAutogenModules:list<string>, presentAutogenModules:list<string>, unexpectedAutogenModules:list<string>, expectedReexportedModules:list<string>, presentReexportedModules:list<string>, unexpectedReexportedModules:list<string>, expectedModuleInterfaceFields:array<string, list<string>>, presentModuleInterfaceFields:array<string, list<string>>, unexpectedModuleInterfaceFields:list<string>, expectedDefaultExtensions:list<string>, presentDefaultExtensions:list<string>, unexpectedDefaultExtensions:list<string>, expectedOtherExtensions:list<string>, presentOtherExtensions:list<string>, unexpectedOtherExtensions:list<string>, expectedExtraSourceFiles:list<string>, presentExtraSourceFiles:list<string>, unexpectedExtraSourceFiles:list<string>, expectedExtraDocFiles:list<string>, presentExtraDocFiles:list<string>, unexpectedExtraDocFiles:list<string>, expectedExtraTmpFiles:list<string>, presentExtraTmpFiles:list<string>, unexpectedExtraTmpFiles:list<string>, expectedDataFiles:list<string>, presentDataFiles:list<string>, unexpectedDataFiles:list<string>, allowedConditionalBranches:list<string>, presentConditionalBranches:list<string>, unexpectedConditionalBranches:list<string>, expectedNativeSystemFields:array<string, list<string>>, presentNativeSystemFields:array<string, list<string>>, unexpectedNativeSystemFields:list<string>},
     *   runnerEntrySourceClosure:array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>},
     *   runnerArtifactClosure:array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, fileProvenance:array<string, array{sha256:string, bytes:int}>},
     *   benchmarkArtifactClosure:array{expected:array<string, string>, expectedSemantics:array<string, array<string, string>>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, missingSemantics:array<string, list<string>>, fileProvenance:array<string, array{sha256:string, bytes:int}>},
     *   benchmarkEntrySourceClosure:array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>},
     *   formatRegistrySourceClosure:array{expected:array<string, string>, expectedSemantics:array<string, array<string, string>>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, fileProvenance:array<string, array{sha256:string, bytes:int}>, presentSemantics:array<string, list<string>>, missingSemantics:array<string, list<string>>},
     *   cabalPlanCommands:array<string, array{program:string, arguments:list<string>, targets:list<string>, component:string, buildDirectory:string, workingDirectory:string, executionPolicy:string, outputCapture:list<string>}>,
     *   cabalPlanWorkspace:array{environmentPolicy:string, workingDirectory:string, environmentVariables:array<string, string>, buildDirectories:array<string, string>, transcriptFiles:array<string, string>, optionalPlanJsonFiles:array<string, string>, preflight:list<string>},
     *   cabalPlanDescriptorClosure:array{expectedCommands:list<string>, presentCommands:list<string>, missingCommands:list<string>, unexpectedCommands:list<string>, commandPolicyViolations:list<string>, workspacePolicyViolations:list<string>, commandWorkspaceMismatches:list<string>},
     *   readyForNonMutatingCabalPlan:bool,
     *   blockedReasons:list<string>,
     *   nonMutatingPlan:list<string>,
     *   activationGate:string
     * }
     */
    public static function auditCheckout(string $checkoutPath, array $tools = []): array
    {
        $root = rtrim($checkoutPath, DIRECTORY_SEPARATOR);
        if ($root === '') {
            $root = '.';
        }

        $requiredFileProvenance = self::auditRequiredFileProvenance($root);
        $presentFiles = array_keys($requiredFileProvenance['present']);
        $missingFiles = $requiredFileProvenance['missing'];

        $normalizedTools = self::normalizeTools($tools);
        $missingTools = [];
        foreach (self::REQUIRED_TOOLS as $tool) {
            if (($normalizedTools[$tool]['available'] ?? false) !== true) {
                $missingTools[] = $tool;
            }
        }

        $planStabilityClosure = self::auditPlanStabilityClosure($root);
        $projectFile = $root . DIRECTORY_SEPARATOR . 'cabal.project';
        $projectContents = is_file($projectFile) ? (string) file_get_contents($projectFile) : null;
        $projectPins = self::auditProjectPins($projectContents);
        $projectSourceRepositoryClosure = self::auditProjectSourceRepositoryClosure($projectContents);
        $projectPackageClosure = self::auditProjectPackageClosure($projectContents);
        $projectConstraintClosure = self::auditProjectConstraintClosure($projectContents);
        $projectUnconditionalFieldClosure = self::auditProjectUnconditionalFieldClosure($projectContents);
        $projectConditionalBranchClosure = self::auditProjectConditionalBranchClosure($projectContents);
        $compilerTestedWithClosure = self::auditCompilerTestedWithClosure($root, $normalizedTools);
        $packageIdentityClosure = self::auditPackageIdentityClosure($root);
        $packageSetupClosure = self::auditPackageSetupClosure($root);
        $packageFlagDefinitionClosure = self::auditPackageFlagDefinitionClosure($root);
        $packageDataFileClosure = self::auditPackageDataFileClosure($root);
        $packageExtraFileClosure = self::auditPackageExtraFileClosure($root);
        $packageNativeSystemFieldClosure = self::auditPackageNativeSystemFieldClosure($root);
        $packageSourceRepositoryClosure = self::auditPackageSourceRepositoryClosure($root);
        $runnerDependencyClosure = self::auditRunnerDependencyClosure($root);
        $benchmarkDependencyClosure = self::auditBenchmarkDependencyClosure($root);
        $luaEngineLibraryClosure = self::auditLuaEngineLibraryClosure($root);
        $serverLibraryClosure = self::auditServerLibraryClosure($root);
        $cliExecutableClosure = self::auditCliExecutableClosure($root);
        $runnerEntrySourceClosure = self::auditRunnerEntrySourceClosure($root);
        $runnerArtifactClosure = self::auditRunnerArtifactClosure($root);
        $benchmarkArtifactClosure = self::auditBenchmarkArtifactClosure($root);
        $benchmarkEntrySourceClosure = self::auditBenchmarkEntrySourceClosure($root);
        $formatRegistrySourceClosure = self::auditFormatRegistrySourceClosure($root);
        $cabalPlanDescriptorClosure = self::auditCabalPlanDescriptorClosure(
            self::expectedCabalPlanCommands(),
            self::expectedCabalPlanWorkspace()
        );

        $blockedReasons = [];
        if ($missingFiles !== []) {
            $blockedReasons[] = 'missing required upstream runner files: ' . implode(', ', $missingFiles);
        }
        if ($missingTools !== []) {
            $blockedReasons[] = 'missing required Cabal toolchain commands: ' . implode(', ', $missingTools);
        }
        if ($compilerTestedWithClosure['missingGhcVersions'] !== []) {
            $blockedReasons[] = 'missing pandoc.cabal tested-with GHC versions: ' . implode(', ', $compilerTestedWithClosure['missingGhcVersions']);
        }
        if (($normalizedTools['ghc']['available'] ?? false) === true && $compilerTestedWithClosure['toolGhcVersionSupported'] !== true) {
            $blockedReasons[] = 'unsupported or unrecorded ghc version for Pandoc tested-with matrix: ' . ($compilerTestedWithClosure['toolGhcVersion'] ?? 'none');
        }
        if ($packageIdentityClosure['missingHeaders'] !== []) {
            $blockedReasons[] = 'missing Cabal package identity headers: ' . self::formatPackageIdentityFailures($packageIdentityClosure['missingHeaders']);
        }
        if ($packageIdentityClosure['mismatchedHeaders'] !== []) {
            $blockedReasons[] = 'mismatched Cabal package identity headers: ' . self::formatPackageIdentityMismatches($packageIdentityClosure['mismatchedHeaders']);
        }
        if ($packageSetupClosure['unexpectedCustomSetupStanzas'] !== []) {
            $blockedReasons[] = 'unexpected Cabal custom-setup stanzas: ' . self::formatTargetFailures($packageSetupClosure['unexpectedCustomSetupStanzas']);
        }
        if ($packageSetupClosure['unexpectedSetupDependencies'] !== []) {
            $blockedReasons[] = 'unexpected Cabal setup-depends: ' . self::formatTargetFailures($packageSetupClosure['unexpectedSetupDependencies']);
        }
        if ($packageFlagDefinitionClosure['missingFlags'] !== []) {
            $blockedReasons[] = 'missing Cabal package flag definitions: ' . self::formatTargetFailures($packageFlagDefinitionClosure['missingFlags']);
        }
        if ($packageFlagDefinitionClosure['unexpectedFlags'] !== []) {
            $blockedReasons[] = 'unexpected Cabal package flag definitions: ' . self::formatTargetFailures($packageFlagDefinitionClosure['unexpectedFlags']);
        }
        if ($packageFlagDefinitionClosure['mismatchedFlagFields'] !== []) {
            $blockedReasons[] = 'mismatched Cabal package flag fields: ' . self::formatPackageFlagFieldMismatches($packageFlagDefinitionClosure['mismatchedFlagFields']);
        }
        if ($packageDataFileClosure['missingDataFiles'] !== []) {
            $blockedReasons[] = 'missing Cabal package data-files: ' . self::formatTargetFailures($packageDataFileClosure['missingDataFiles']);
        }
        if ($packageDataFileClosure['unexpectedDataFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal package data-files: ' . self::formatTargetFailures($packageDataFileClosure['unexpectedDataFiles']);
        }
        if ($packageExtraFileClosure['missingExtraDocFiles'] !== []) {
            $blockedReasons[] = 'missing Cabal package extra-doc-files: ' . self::formatTargetFailures($packageExtraFileClosure['missingExtraDocFiles']);
        }
        if ($packageExtraFileClosure['unexpectedExtraDocFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal package extra-doc-files: ' . self::formatTargetFailures($packageExtraFileClosure['unexpectedExtraDocFiles']);
        }
        if ($packageExtraFileClosure['missingExtraSourceFiles'] !== []) {
            $blockedReasons[] = 'missing Cabal package extra-source-files: ' . self::formatTargetFailures($packageExtraFileClosure['missingExtraSourceFiles']);
        }
        if ($packageExtraFileClosure['unexpectedExtraSourceFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal package extra-source-files: ' . self::formatTargetFailures($packageExtraFileClosure['unexpectedExtraSourceFiles']);
        }
        if ($packageExtraFileClosure['unexpectedExtraTmpFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal package extra-tmp-files: ' . self::formatTargetFailures($packageExtraFileClosure['unexpectedExtraTmpFiles']);
        }
        if ($packageNativeSystemFieldClosure['unexpectedNativeSystemFields'] !== []) {
            $blockedReasons[] = 'unexpected Cabal package native/system dependencies: ' . self::formatTargetFailures($packageNativeSystemFieldClosure['unexpectedNativeSystemFields']);
        }
        if ($packageSourceRepositoryClosure['missing'] !== []) {
            $blockedReasons[] = 'missing Cabal package source-repository stanzas: ' . self::formatTargetFailures($packageSourceRepositoryClosure['missing']);
        }
        if ($packageSourceRepositoryClosure['mismatched'] !== []) {
            $blockedReasons[] = 'mismatched Cabal package source-repository stanzas: ' . self::formatPackageSourceRepositoryMismatches($packageSourceRepositoryClosure['mismatched']);
        }
        if ($packageSourceRepositoryClosure['unexpected'] !== []) {
            $blockedReasons[] = 'unexpected Cabal package source-repository stanzas: ' . self::formatTargetFailures($packageSourceRepositoryClosure['unexpected']);
        }
        if ($packageSourceRepositoryClosure['unexpectedFields'] !== []) {
            $blockedReasons[] = 'unexpected Cabal package source-repository fields: ' . self::formatNestedTargetFailures($packageSourceRepositoryClosure['unexpectedFields']);
        }
        if ($projectPins['missing'] !== []) {
            $blockedReasons[] = 'missing cabal.project source-repository pins: ' . implode(', ', $projectPins['missing']);
        }
        if ($projectPins['mismatched'] !== []) {
            $blockedReasons[] = 'mismatched cabal.project source-repository pins: ' . implode(', ', array_keys($projectPins['mismatched']));
        }
        if ($projectSourceRepositoryClosure['missing'] !== []) {
            $blockedReasons[] = 'missing cabal.project source-repository package locations/types: ' . implode(', ', $projectSourceRepositoryClosure['missing']);
        }
        if ($projectSourceRepositoryClosure['mismatched'] !== []) {
            $blockedReasons[] = 'mismatched cabal.project source-repository package locations/types: ' . implode(', ', array_keys($projectSourceRepositoryClosure['mismatched']));
        }
        if ($projectSourceRepositoryClosure['unexpected'] !== []) {
            $blockedReasons[] = 'unexpected cabal.project source-repository packages: ' . implode(', ', $projectSourceRepositoryClosure['unexpected']);
        }
        if ($projectSourceRepositoryClosure['unexpectedFields'] !== []) {
            $blockedReasons[] = 'unexpected cabal.project source-repository package fields: ' . self::formatTargetFailures($projectSourceRepositoryClosure['unexpectedFields']);
        }
        if ($projectPackageClosure['missingPackages'] !== []) {
            $blockedReasons[] = 'missing cabal.project package entries: ' . implode(', ', $projectPackageClosure['missingPackages']);
        }
        if ($projectPackageClosure['unexpectedPackages'] !== []) {
            $blockedReasons[] = 'unexpected cabal.project package entries: ' . implode(', ', $projectPackageClosure['unexpectedPackages']);
        }
        if ($projectPackageClosure['unexpectedPackageFields'] !== []) {
            $blockedReasons[] = 'unexpected cabal.project package stanza fields: ' . self::formatTargetFailures($projectPackageClosure['unexpectedPackageFields']);
        }
        if ($projectPackageClosure['missingFlags'] !== []) {
            $blockedReasons[] = 'missing cabal.project package flags: ' . self::formatProjectFlagFailures($projectPackageClosure['missingFlags']);
        }
        if ($projectPackageClosure['mismatchedFlags'] !== []) {
            $blockedReasons[] = 'mismatched cabal.project package flags: ' . self::formatProjectFlagMismatches($projectPackageClosure['mismatchedFlags']);
        }
        if ($projectPackageClosure['unexpectedFlags'] !== []) {
            $blockedReasons[] = 'unexpected cabal.project package flags: ' . self::formatProjectFlagFailures($projectPackageClosure['unexpectedFlags']);
        }
        if ($projectConstraintClosure['missingConstraints'] !== []) {
            $blockedReasons[] = 'missing cabal.project solver constraints: ' . implode(', ', $projectConstraintClosure['missingConstraints']);
        }
        if ($projectConstraintClosure['mismatchedConstraints'] !== []) {
            $blockedReasons[] = 'mismatched cabal.project solver constraints: ' . self::formatProjectConstraintMismatches($projectConstraintClosure['mismatchedConstraints']);
        }
        if ($projectConstraintClosure['unexpectedConstraints'] !== []) {
            $blockedReasons[] = 'unexpected cabal.project solver constraints: ' . implode(', ', $projectConstraintClosure['unexpectedConstraints']);
        }
        if ($projectUnconditionalFieldClosure['unexpectedFields'] !== []) {
            $blockedReasons[] = 'unexpected cabal.project unconditional plan fields: ' . implode(', ', $projectUnconditionalFieldClosure['unexpectedFields']);
        }
        if ($projectConditionalBranchClosure['missingBranches'] !== []) {
            $blockedReasons[] = 'missing cabal.project conditional branches: ' . implode(', ', $projectConditionalBranchClosure['missingBranches']);
        }
        if ($projectConditionalBranchClosure['unexpectedBranches'] !== []) {
            $blockedReasons[] = 'unexpected cabal.project conditional branches: ' . implode(', ', $projectConditionalBranchClosure['unexpectedBranches']);
        }
        if ($runnerDependencyClosure['missingTargets'] !== []) {
            $blockedReasons[] = 'missing Cabal runner test-suite stanzas: ' . implode(', ', $runnerDependencyClosure['missingTargets']);
        }
        if ($runnerDependencyClosure['mismatchedEntryPoints'] !== []) {
            $blockedReasons[] = 'mismatched Cabal runner entry points: ' . self::formatTargetFailures($runnerDependencyClosure['mismatchedEntryPoints']);
        }
        if ($runnerDependencyClosure['missingDependencies'] !== []) {
            $blockedReasons[] = 'missing Cabal runner direct build-depends: ' . self::formatTargetFailures($runnerDependencyClosure['missingDependencies']);
        }
        if ($runnerDependencyClosure['unexpectedDependencies'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner direct build-depends: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedDependencies']);
        }
        if ($runnerDependencyClosure['mismatchedDependencyConstraints'] !== []) {
            $blockedReasons[] = 'mismatched Cabal runner direct build-depends constraints: ' . self::formatTargetConstraintMismatches($runnerDependencyClosure['mismatchedDependencyConstraints']);
        }
        if ($runnerDependencyClosure['missingExecutableOptions'] !== []) {
            $blockedReasons[] = 'missing Cabal runner executable options: ' . self::formatTargetFailures($runnerDependencyClosure['missingExecutableOptions']);
        }
        if ($runnerDependencyClosure['unexpectedExecutableOptions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner executable options: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedExecutableOptions']);
        }
        if ($runnerDependencyClosure['mismatchedDefaultLanguages'] !== []) {
            $blockedReasons[] = 'mismatched Cabal runner default-language: ' . self::formatDefaultLanguageMismatches($runnerDependencyClosure['mismatchedDefaultLanguages']);
        }
        if ($runnerDependencyClosure['mismatchedManualFields'] !== []) {
            $blockedReasons[] = 'mismatched Cabal runner manual fields: ' . self::formatOptionalFieldMismatches($runnerDependencyClosure['mismatchedManualFields'], 'manual');
        }
        if ($runnerDependencyClosure['missingCommonImports'] !== []) {
            $blockedReasons[] = 'missing Cabal runner common imports: ' . self::formatTargetFailures($runnerDependencyClosure['missingCommonImports']);
        }
        if ($runnerDependencyClosure['unexpectedCommonImports'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner common imports: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedCommonImports']);
        }
        if ($runnerDependencyClosure['unresolvedCommonImports'] !== []) {
            $blockedReasons[] = 'unresolved Cabal runner common imports: ' . self::formatTargetFailures($runnerDependencyClosure['unresolvedCommonImports']);
        }
        if ($runnerDependencyClosure['unexpectedSourceDirectories'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner hs-source-dirs: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedSourceDirectories']);
        }
        if ($runnerDependencyClosure['unexpectedMixins'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner mixins: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedMixins']);
        }
        if ($runnerDependencyClosure['unexpectedBuildTools'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner build-tool dependencies: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedBuildTools']);
        }
        if ($runnerDependencyClosure['unexpectedTestOptions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner test-options: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedTestOptions']);
        }
        if ($runnerDependencyClosure['unexpectedDefaultExtensions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner default-extensions: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedDefaultExtensions']);
        }
        if ($runnerDependencyClosure['unexpectedOtherExtensions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner other-extensions: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedOtherExtensions']);
        }
        if ($runnerDependencyClosure['unexpectedCppOptions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner cpp-options: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedCppOptions']);
        }
        if ($runnerDependencyClosure['unexpectedAutogenModules'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner autogen-modules: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedAutogenModules']);
        }
        if ($runnerDependencyClosure['unexpectedReexportedModules'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner reexported-modules: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedReexportedModules']);
        }
        if ($runnerDependencyClosure['unexpectedModuleInterfaceFields'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner module interface fields: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedModuleInterfaceFields']);
        }
        if ($runnerDependencyClosure['unexpectedExtraSourceFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner extra-source-files: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedExtraSourceFiles']);
        }
        if ($runnerDependencyClosure['unexpectedExtraDocFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner extra-doc-files: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedExtraDocFiles']);
        }
        if ($runnerDependencyClosure['unexpectedExtraTmpFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner extra-tmp-files: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedExtraTmpFiles']);
        }
        if ($runnerDependencyClosure['unexpectedDataFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner data-files: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedDataFiles']);
        }
        if ($runnerDependencyClosure['unexpectedConditionalBranches'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner conditional branches: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedConditionalBranches']);
        }
        if ($runnerDependencyClosure['unexpectedNativeSystemFields'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner native/system dependencies: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedNativeSystemFields']);
        }
        if ($runnerDependencyClosure['missingOtherModules'] !== []) {
            $blockedReasons[] = 'missing Cabal runner other-modules: ' . self::formatTargetFailures($runnerDependencyClosure['missingOtherModules']);
        }
        if ($runnerDependencyClosure['unexpectedOtherModules'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner other-modules: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedOtherModules']);
        }
        if ($benchmarkDependencyClosure['missingTargets'] !== []) {
            $blockedReasons[] = 'missing Cabal benchmark stanzas: ' . implode(', ', $benchmarkDependencyClosure['missingTargets']);
        }
        if ($benchmarkDependencyClosure['mismatchedEntryPoints'] !== []) {
            $blockedReasons[] = 'mismatched Cabal benchmark entry points: ' . self::formatTargetFailures($benchmarkDependencyClosure['mismatchedEntryPoints']);
        }
        if ($benchmarkDependencyClosure['missingDependencies'] !== []) {
            $blockedReasons[] = 'missing Cabal benchmark direct build-depends: ' . self::formatTargetFailures($benchmarkDependencyClosure['missingDependencies']);
        }
        if ($benchmarkDependencyClosure['unexpectedDependencies'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark direct build-depends: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedDependencies']);
        }
        if ($benchmarkDependencyClosure['mismatchedDependencyConstraints'] !== []) {
            $blockedReasons[] = 'mismatched Cabal benchmark direct build-depends constraints: ' . self::formatTargetConstraintMismatches($benchmarkDependencyClosure['mismatchedDependencyConstraints']);
        }
        if ($benchmarkDependencyClosure['missingExecutableOptions'] !== []) {
            $blockedReasons[] = 'missing Cabal benchmark executable options: ' . self::formatTargetFailures($benchmarkDependencyClosure['missingExecutableOptions']);
        }
        if ($benchmarkDependencyClosure['unexpectedExecutableOptions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark executable options: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedExecutableOptions']);
        }
        if ($benchmarkDependencyClosure['mismatchedDefaultLanguages'] !== []) {
            $blockedReasons[] = 'mismatched Cabal benchmark default-language: ' . self::formatDefaultLanguageMismatches($benchmarkDependencyClosure['mismatchedDefaultLanguages']);
        }
        if ($benchmarkDependencyClosure['mismatchedManualFields'] !== []) {
            $blockedReasons[] = 'mismatched Cabal benchmark manual fields: ' . self::formatOptionalFieldMismatches($benchmarkDependencyClosure['mismatchedManualFields'], 'manual');
        }
        if ($benchmarkDependencyClosure['missingCommonImports'] !== []) {
            $blockedReasons[] = 'missing Cabal benchmark common imports: ' . self::formatTargetFailures($benchmarkDependencyClosure['missingCommonImports']);
        }
        if ($benchmarkDependencyClosure['unexpectedCommonImports'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark common imports: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedCommonImports']);
        }
        if ($benchmarkDependencyClosure['unresolvedCommonImports'] !== []) {
            $blockedReasons[] = 'unresolved Cabal benchmark common imports: ' . self::formatTargetFailures($benchmarkDependencyClosure['unresolvedCommonImports']);
        }
        if ($benchmarkDependencyClosure['unexpectedSourceDirectories'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark hs-source-dirs: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedSourceDirectories']);
        }
        if ($benchmarkDependencyClosure['unexpectedMixins'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark mixins: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedMixins']);
        }
        if ($benchmarkDependencyClosure['unexpectedBuildTools'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark build-tool dependencies: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedBuildTools']);
        }
        if ($benchmarkDependencyClosure['unexpectedBenchmarkOptions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark options: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedBenchmarkOptions']);
        }
        if ($benchmarkDependencyClosure['unexpectedDefaultExtensions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark default-extensions: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedDefaultExtensions']);
        }
        if ($benchmarkDependencyClosure['unexpectedOtherExtensions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark other-extensions: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedOtherExtensions']);
        }
        if ($benchmarkDependencyClosure['unexpectedCppOptions'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark cpp-options: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedCppOptions']);
        }
        if ($benchmarkDependencyClosure['unexpectedAutogenModules'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark autogen-modules: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedAutogenModules']);
        }
        if ($benchmarkDependencyClosure['unexpectedReexportedModules'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark reexported-modules: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedReexportedModules']);
        }
        if ($benchmarkDependencyClosure['unexpectedModuleInterfaceFields'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark module interface fields: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedModuleInterfaceFields']);
        }
        if ($benchmarkDependencyClosure['unexpectedOtherModules'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark other-modules: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedOtherModules']);
        }
        if ($benchmarkDependencyClosure['unexpectedExtraSourceFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark extra-source-files: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedExtraSourceFiles']);
        }
        if ($benchmarkDependencyClosure['unexpectedExtraDocFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark extra-doc-files: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedExtraDocFiles']);
        }
        if ($benchmarkDependencyClosure['unexpectedExtraTmpFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark extra-tmp-files: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedExtraTmpFiles']);
        }
        if ($benchmarkDependencyClosure['unexpectedDataFiles'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark data-files: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedDataFiles']);
        }
        if ($benchmarkDependencyClosure['unexpectedConditionalBranches'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark conditional branches: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedConditionalBranches']);
        }
        if ($benchmarkDependencyClosure['unexpectedNativeSystemFields'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark native/system dependencies: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedNativeSystemFields']);
        }
        if ($luaEngineLibraryClosure['missingDependencies'] !== []) {
            $blockedReasons[] = 'missing pandoc-lua-engine library build-depends: ' . implode(', ', $luaEngineLibraryClosure['missingDependencies']);
        }
        if ($luaEngineLibraryClosure['unexpectedDependencies'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library Lua support build-depends: ' . implode(', ', $luaEngineLibraryClosure['unexpectedDependencies']);
        }
        if ($luaEngineLibraryClosure['missingExposedModules'] !== []) {
            $blockedReasons[] = 'missing pandoc-lua-engine library exposed-modules: ' . implode(', ', $luaEngineLibraryClosure['missingExposedModules']);
        }
        if ($luaEngineLibraryClosure['unexpectedExposedModules'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library exposed-modules: ' . implode(', ', $luaEngineLibraryClosure['unexpectedExposedModules']);
        }
        if ($luaEngineLibraryClosure['missingSourceDirectories'] !== []) {
            $blockedReasons[] = 'missing pandoc-lua-engine library hs-source-dirs: ' . implode(', ', $luaEngineLibraryClosure['missingSourceDirectories']);
        }
        if ($luaEngineLibraryClosure['unexpectedSourceDirectories'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library hs-source-dirs: ' . implode(', ', $luaEngineLibraryClosure['unexpectedSourceDirectories']);
        }
        if ($luaEngineLibraryClosure['missingOtherModules'] !== []) {
            $blockedReasons[] = 'missing pandoc-lua-engine library other-modules: ' . implode(', ', $luaEngineLibraryClosure['missingOtherModules']);
        }
        if ($luaEngineLibraryClosure['unexpectedOtherModules'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library other-modules: ' . implode(', ', $luaEngineLibraryClosure['unexpectedOtherModules']);
        }
        if ($luaEngineLibraryClosure['missingSourceArtifacts'] !== []) {
            $blockedReasons[] = 'missing pandoc-lua-engine library source artifacts: ' . implode(', ', $luaEngineLibraryClosure['missingSourceArtifacts']);
        }
        if ($luaEngineLibraryClosure['wrongTypeSourceArtifacts'] !== []) {
            $blockedReasons[] = 'mismatched pandoc-lua-engine library source artifact types: ' . self::formatArtifactTypeMismatches($luaEngineLibraryClosure['wrongTypeSourceArtifacts']);
        }
        if ($luaEngineLibraryClosure['emptySourceArtifacts'] !== []) {
            $blockedReasons[] = 'empty pandoc-lua-engine library source artifacts: ' . implode(', ', $luaEngineLibraryClosure['emptySourceArtifacts']);
        }
        if ($luaEngineLibraryClosure['mismatchedDefaultLanguage'] !== null) {
            $blockedReasons[] = 'mismatched pandoc-lua-engine library default-language: expected '
                . $luaEngineLibraryClosure['mismatchedDefaultLanguage']['expected']
                . ', found '
                . ($luaEngineLibraryClosure['mismatchedDefaultLanguage']['actual'] ?? 'none');
        }
        if ($luaEngineLibraryClosure['unexpectedMixins'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library mixins: ' . implode(', ', $luaEngineLibraryClosure['unexpectedMixins']);
        }
        if ($luaEngineLibraryClosure['unexpectedBuildTools'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library build-tool dependencies: ' . implode(', ', $luaEngineLibraryClosure['unexpectedBuildTools']);
        }
        if ($luaEngineLibraryClosure['unexpectedAutogenModules'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library autogen-modules: ' . implode(', ', $luaEngineLibraryClosure['unexpectedAutogenModules']);
        }
        if ($luaEngineLibraryClosure['unexpectedReexportedModules'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library reexported-modules: ' . implode(', ', $luaEngineLibraryClosure['unexpectedReexportedModules']);
        }
        if ($luaEngineLibraryClosure['unexpectedModuleInterfaceFields'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library module interface fields: ' . implode(', ', $luaEngineLibraryClosure['unexpectedModuleInterfaceFields']);
        }
        if ($luaEngineLibraryClosure['unexpectedDefaultExtensions'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library default-extensions: ' . implode(', ', $luaEngineLibraryClosure['unexpectedDefaultExtensions']);
        }
        if ($luaEngineLibraryClosure['unexpectedOtherExtensions'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library other-extensions: ' . implode(', ', $luaEngineLibraryClosure['unexpectedOtherExtensions']);
        }
        if ($luaEngineLibraryClosure['unexpectedExtraSourceFiles'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library extra-source-files: ' . implode(', ', $luaEngineLibraryClosure['unexpectedExtraSourceFiles']);
        }
        if ($luaEngineLibraryClosure['unexpectedExtraDocFiles'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library extra-doc-files: ' . implode(', ', $luaEngineLibraryClosure['unexpectedExtraDocFiles']);
        }
        if ($luaEngineLibraryClosure['unexpectedExtraTmpFiles'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library extra-tmp-files: ' . implode(', ', $luaEngineLibraryClosure['unexpectedExtraTmpFiles']);
        }
        if ($luaEngineLibraryClosure['unexpectedDataFiles'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library data-files: ' . implode(', ', $luaEngineLibraryClosure['unexpectedDataFiles']);
        }
        if ($luaEngineLibraryClosure['unexpectedConditionalBranches'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library conditional branches: ' . implode(', ', $luaEngineLibraryClosure['unexpectedConditionalBranches']);
        }
        if ($luaEngineLibraryClosure['unexpectedNativeSystemFields'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-lua-engine library native/system dependencies: ' . implode(', ', $luaEngineLibraryClosure['unexpectedNativeSystemFields']);
        }
        if ($serverLibraryClosure['missingDependencies'] !== []) {
            $blockedReasons[] = 'missing pandoc-server library build-depends: ' . implode(', ', $serverLibraryClosure['missingDependencies']);
        }
        if ($serverLibraryClosure['unexpectedDependencies'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-server library build-depends: ' . implode(', ', $serverLibraryClosure['unexpectedDependencies']);
        }
        if ($serverLibraryClosure['mismatchedDependencyConstraints'] !== []) {
            $blockedReasons[] = 'mismatched pandoc-server library build-depends constraints: ' . self::formatDependencyConstraintMismatches($serverLibraryClosure['mismatchedDependencyConstraints']);
        }
        if ($serverLibraryClosure['missingExposedModules'] !== []) {
            $blockedReasons[] = 'missing pandoc-server library exposed-modules: ' . implode(', ', $serverLibraryClosure['missingExposedModules']);
        }
        if ($serverLibraryClosure['unexpectedExposedModules'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-server library exposed-modules: ' . implode(', ', $serverLibraryClosure['unexpectedExposedModules']);
        }
        if ($serverLibraryClosure['missingSourceDirectories'] !== []) {
            $blockedReasons[] = 'missing pandoc-server library hs-source-dirs: ' . implode(', ', $serverLibraryClosure['missingSourceDirectories']);
        }
        if ($serverLibraryClosure['unexpectedSourceDirectories'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-server library hs-source-dirs: ' . implode(', ', $serverLibraryClosure['unexpectedSourceDirectories']);
        }
        if ($serverLibraryClosure['mismatchedDefaultLanguage'] !== null) {
            $blockedReasons[] = 'mismatched pandoc-server library default-language: expected '
                . $serverLibraryClosure['mismatchedDefaultLanguage']['expected']
                . ', found '
                . ($serverLibraryClosure['mismatchedDefaultLanguage']['actual'] ?? 'none');
        }
        if ($cliExecutableClosure['missingExecutable'] === true) {
            $blockedReasons[] = 'missing pandoc-cli executable stanza: ' . self::CLI_EXECUTABLE_NAME;
        }
        if ($cliExecutableClosure['mismatchedEntryPoint'] !== []) {
            $blockedReasons[] = 'mismatched pandoc-cli executable entry point: ' . implode('; ', $cliExecutableClosure['mismatchedEntryPoint']);
        }
        if ($cliExecutableClosure['missingDependencies'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable build-depends: ' . implode(', ', $cliExecutableClosure['missingDependencies']);
        }
        if ($cliExecutableClosure['unexpectedDependencies'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable build-depends: ' . implode(', ', $cliExecutableClosure['unexpectedDependencies']);
        }
        if ($cliExecutableClosure['mismatchedDependencyConstraints'] !== []) {
            $blockedReasons[] = 'mismatched pandoc-cli executable build-depends constraints: ' . self::formatDependencyConstraintMismatches($cliExecutableClosure['mismatchedDependencyConstraints']);
        }
        if ($cliExecutableClosure['missingExecutableOptions'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable options: ' . implode(', ', $cliExecutableClosure['missingExecutableOptions']);
        }
        if ($cliExecutableClosure['unexpectedExecutableOptions'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable options: ' . implode(', ', $cliExecutableClosure['unexpectedExecutableOptions']);
        }
        if ($cliExecutableClosure['mismatchedDefaultLanguage'] !== null) {
            $blockedReasons[] = 'mismatched pandoc-cli executable default-language: expected '
                . $cliExecutableClosure['mismatchedDefaultLanguage']['expected']
                . ', found '
                . ($cliExecutableClosure['mismatchedDefaultLanguage']['actual'] ?? 'none');
        }
        if ($cliExecutableClosure['missingCommonImports'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable common imports: ' . implode(', ', $cliExecutableClosure['missingCommonImports']);
        }
        if ($cliExecutableClosure['unexpectedCommonImports'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable common imports: ' . implode(', ', $cliExecutableClosure['unexpectedCommonImports']);
        }
        if ($cliExecutableClosure['unresolvedCommonImports'] !== []) {
            $blockedReasons[] = 'unresolved pandoc-cli executable common imports: ' . implode(', ', $cliExecutableClosure['unresolvedCommonImports']);
        }
        if ($cliExecutableClosure['missingSourceDirectories'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable hs-source-dirs: ' . implode(', ', $cliExecutableClosure['missingSourceDirectories']);
        }
        if ($cliExecutableClosure['unexpectedSourceDirectories'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable hs-source-dirs: ' . implode(', ', $cliExecutableClosure['unexpectedSourceDirectories']);
        }
        if ($cliExecutableClosure['unexpectedMixins'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable mixins: ' . implode(', ', $cliExecutableClosure['unexpectedMixins']);
        }
        if ($cliExecutableClosure['unexpectedBuildTools'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable build-tool dependencies: ' . implode(', ', $cliExecutableClosure['unexpectedBuildTools']);
        }
        if ($cliExecutableClosure['missingOtherExtensions'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable other-extensions: ' . implode(', ', $cliExecutableClosure['missingOtherExtensions']);
        }
        if ($cliExecutableClosure['unexpectedOtherExtensions'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable other-extensions: ' . implode(', ', $cliExecutableClosure['unexpectedOtherExtensions']);
        }
        if ($cliExecutableClosure['unexpectedDefaultExtensions'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable default-extensions: ' . implode(', ', $cliExecutableClosure['unexpectedDefaultExtensions']);
        }
        if ($cliExecutableClosure['unexpectedCppOptions'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable cpp-options: ' . implode(', ', $cliExecutableClosure['unexpectedCppOptions']);
        }
        if ($cliExecutableClosure['unexpectedAutogenModules'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable autogen-modules: ' . implode(', ', $cliExecutableClosure['unexpectedAutogenModules']);
        }
        if ($cliExecutableClosure['unexpectedReexportedModules'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable reexported-modules: ' . implode(', ', $cliExecutableClosure['unexpectedReexportedModules']);
        }
        if ($cliExecutableClosure['unexpectedModuleInterfaceFields'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable module interface fields: ' . implode(', ', $cliExecutableClosure['unexpectedModuleInterfaceFields']);
        }
        if ($cliExecutableClosure['missingOtherModules'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable other-modules: ' . implode(', ', $cliExecutableClosure['missingOtherModules']);
        }
        if ($cliExecutableClosure['unexpectedOtherModules'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable other-modules: ' . implode(', ', $cliExecutableClosure['unexpectedOtherModules']);
        }
        if ($cliExecutableClosure['unexpectedExtraSourceFiles'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable extra-source-files: ' . implode(', ', $cliExecutableClosure['unexpectedExtraSourceFiles']);
        }
        if ($cliExecutableClosure['unexpectedExtraDocFiles'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable extra-doc-files: ' . implode(', ', $cliExecutableClosure['unexpectedExtraDocFiles']);
        }
        if ($cliExecutableClosure['unexpectedExtraTmpFiles'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable extra-tmp-files: ' . implode(', ', $cliExecutableClosure['unexpectedExtraTmpFiles']);
        }
        if ($cliExecutableClosure['unexpectedDataFiles'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable data-files: ' . implode(', ', $cliExecutableClosure['unexpectedDataFiles']);
        }
        if ($cliExecutableClosure['missingConditionalBranches'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable conditional branches: ' . implode(', ', $cliExecutableClosure['missingConditionalBranches']);
        }
        if ($cliExecutableClosure['unexpectedConditionalBranches'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable conditional branches: ' . implode(', ', $cliExecutableClosure['unexpectedConditionalBranches']);
        }
        if ($cliExecutableClosure['missingConditionalFieldEntries'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable conditional branch fields: ' . self::formatTargetFailures($cliExecutableClosure['missingConditionalFieldEntries']);
        }
        if ($cliExecutableClosure['unexpectedConditionalFieldEntries'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable conditional branch fields: ' . self::formatTargetFailures($cliExecutableClosure['unexpectedConditionalFieldEntries']);
        }
        if ($cliExecutableClosure['missingSourceArtifacts'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable source artifacts: ' . implode(', ', $cliExecutableClosure['missingSourceArtifacts']);
        }
        if ($cliExecutableClosure['wrongTypeSourceArtifacts'] !== []) {
            $blockedReasons[] = 'mismatched pandoc-cli executable source artifact types: ' . self::formatArtifactTypeMismatches($cliExecutableClosure['wrongTypeSourceArtifacts']);
        }
        if ($cliExecutableClosure['emptySourceArtifacts'] !== []) {
            $blockedReasons[] = 'empty pandoc-cli executable source artifacts: ' . implode(', ', $cliExecutableClosure['emptySourceArtifacts']);
        }
        if ($cliExecutableClosure['missingSourceSemantics'] !== []) {
            $blockedReasons[] = 'missing pandoc-cli executable source semantics: ' . self::formatTargetFailures($cliExecutableClosure['missingSourceSemantics']);
        }
        if ($cliExecutableClosure['unexpectedNativeSystemFields'] !== []) {
            $blockedReasons[] = 'unexpected pandoc-cli executable native/system dependencies: ' . implode(', ', $cliExecutableClosure['unexpectedNativeSystemFields']);
        }
        if ($runnerEntrySourceClosure['missingTargets'] !== []) {
            $blockedReasons[] = 'missing runner entry point source files: ' . implode(', ', $runnerEntrySourceClosure['missingTargets']);
        }
        if ($runnerEntrySourceClosure['missingSemantics'] !== []) {
            $blockedReasons[] = 'missing runner entry point source semantics: ' . self::formatTargetFailures($runnerEntrySourceClosure['missingSemantics']);
        }
        if ($runnerArtifactClosure['missing'] !== []) {
            $blockedReasons[] = 'missing upstream runner source/golden fixture artifacts: ' . implode(', ', $runnerArtifactClosure['missing']);
        }
        if ($runnerArtifactClosure['wrongType'] !== []) {
            $blockedReasons[] = 'mismatched upstream runner source/golden fixture artifact types: ' . self::formatArtifactTypeMismatches($runnerArtifactClosure['wrongType']);
        }
        if ($runnerArtifactClosure['emptyFiles'] !== []) {
            $blockedReasons[] = 'empty upstream runner source/golden fixture artifacts: ' . implode(', ', $runnerArtifactClosure['emptyFiles']);
        }
        if ($benchmarkArtifactClosure['missing'] !== []) {
            $blockedReasons[] = 'missing upstream benchmark source/data artifacts: ' . implode(', ', $benchmarkArtifactClosure['missing']);
        }
        if ($benchmarkArtifactClosure['wrongType'] !== []) {
            $blockedReasons[] = 'mismatched upstream benchmark source/data artifact types: ' . self::formatArtifactTypeMismatches($benchmarkArtifactClosure['wrongType']);
        }
        if ($benchmarkArtifactClosure['emptyFiles'] !== []) {
            $blockedReasons[] = 'empty upstream benchmark source/data artifacts: ' . implode(', ', $benchmarkArtifactClosure['emptyFiles']);
        }
        if ($benchmarkArtifactClosure['missingSemantics'] !== []) {
            $blockedReasons[] = 'missing upstream benchmark fixture semantics: ' . self::formatTargetFailures($benchmarkArtifactClosure['missingSemantics']);
        }
        if ($benchmarkEntrySourceClosure['missingTargets'] !== []) {
            $blockedReasons[] = 'missing benchmark entry point source files: ' . implode(', ', $benchmarkEntrySourceClosure['missingTargets']);
        }
        if ($benchmarkEntrySourceClosure['missingSemantics'] !== []) {
            $blockedReasons[] = 'missing benchmark entry point source semantics: ' . self::formatTargetFailures($benchmarkEntrySourceClosure['missingSemantics']);
        }
        if ($formatRegistrySourceClosure['missing'] !== []) {
            $blockedReasons[] = 'missing Pandoc format registry source artifacts: ' . implode(', ', $formatRegistrySourceClosure['missing']);
        }
        if ($formatRegistrySourceClosure['wrongType'] !== []) {
            $blockedReasons[] = 'mismatched Pandoc format registry source artifact types: ' . self::formatArtifactTypeMismatches($formatRegistrySourceClosure['wrongType']);
        }
        if ($formatRegistrySourceClosure['emptyFiles'] !== []) {
            $blockedReasons[] = 'empty Pandoc format registry source artifacts: ' . implode(', ', $formatRegistrySourceClosure['emptyFiles']);
        }
        if ($formatRegistrySourceClosure['missingSemantics'] !== []) {
            $blockedReasons[] = 'missing Pandoc roff/manual format registry source semantics: ' . self::formatTargetFailures($formatRegistrySourceClosure['missingSemantics']);
        }
        if (
            $cabalPlanDescriptorClosure['missingCommands'] !== []
            || $cabalPlanDescriptorClosure['unexpectedCommands'] !== []
            || $cabalPlanDescriptorClosure['commandPolicyViolations'] !== []
            || $cabalPlanDescriptorClosure['workspacePolicyViolations'] !== []
            || $cabalPlanDescriptorClosure['commandWorkspaceMismatches'] !== []
        ) {
            $blockedReasons[] = 'invalid Cabal dry-run descriptor workspace: ' . self::formatCabalPlanDescriptorFailures($cabalPlanDescriptorClosure);
        }

        $ready = $blockedReasons === [];

        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'checkoutPath' => $root,
            'requiredFiles' => self::REQUIRED_FILES,
            'presentFiles' => $presentFiles,
            'missingFiles' => $missingFiles,
            'requiredFileProvenance' => $requiredFileProvenance,
            'tools' => $normalizedTools,
            'missingTools' => $missingTools,
            'planStabilityClosure' => $planStabilityClosure,
            'compilerTestedWithClosure' => $compilerTestedWithClosure,
            'packageIdentityClosure' => $packageIdentityClosure,
            'packageFlagDefinitionClosure' => $packageFlagDefinitionClosure,
            'packageDataFileClosure' => $packageDataFileClosure,
            'packageExtraFileClosure' => $packageExtraFileClosure,
            'packageNativeSystemFieldClosure' => $packageNativeSystemFieldClosure,
            'packageSourceRepositoryClosure' => $packageSourceRepositoryClosure,
            'runnerTargets' => array_keys(self::RUNNER_ENTRY_POINTS),
            'runnerEntryPoints' => self::RUNNER_ENTRY_POINTS,
            'benchmarkTargets' => array_keys(self::BENCHMARK_ENTRY_POINTS),
            'benchmarkEntryPoints' => self::BENCHMARK_ENTRY_POINTS,
            'projectSourceRepositoryPins' => $projectPins,
            'projectSourceRepositoryClosure' => $projectSourceRepositoryClosure,
            'projectPackageClosure' => $projectPackageClosure,
            'projectConstraintClosure' => $projectConstraintClosure,
            'projectUnconditionalFieldClosure' => $projectUnconditionalFieldClosure,
            'projectConditionalBranchClosure' => $projectConditionalBranchClosure,
            'packageSetupClosure' => $packageSetupClosure,
            'runnerDependencyClosure' => $runnerDependencyClosure,
            'benchmarkDependencyClosure' => $benchmarkDependencyClosure,
            'luaEngineLibraryClosure' => $luaEngineLibraryClosure,
            'serverLibraryClosure' => $serverLibraryClosure,
            'cliExecutableClosure' => $cliExecutableClosure,
            'runnerEntrySourceClosure' => $runnerEntrySourceClosure,
            'runnerArtifactClosure' => $runnerArtifactClosure,
            'benchmarkArtifactClosure' => $benchmarkArtifactClosure,
            'benchmarkEntrySourceClosure' => $benchmarkEntrySourceClosure,
            'formatRegistrySourceClosure' => $formatRegistrySourceClosure,
            'cabalPlanCommands' => self::expectedCabalPlanCommands(),
            'cabalPlanWorkspace' => self::expectedCabalPlanWorkspace(),
            'cabalPlanDescriptorClosure' => $cabalPlanDescriptorClosure,
            'readyForNonMutatingCabalPlan' => $ready,
            'blockedReasons' => $blockedReasons,
            'nonMutatingPlan' => $ready ? [
                'record Cabal package identity/version headers, package flag definitions plus default/manual values for cabal.project flags and no unexpected Cabal package flag definitions, exact package-level source-repository head closure, exact package-level data-files closure for Pandoc templates/data payloads, exact package-level extra-doc-files and extra-source-files closure for documentation and source fixture globs, no unexpected package-level extra-tmp-files or native/system dependency fields, pandoc.cabal tested-with GHC matrix, cabal.project package/flag closure plus package stanza field closure and source-repository type/location/tag closure, no unexpected cabal.project package/source-repository entries, package stanza fields, flags, source-repository fields, unconditional plan fields, or conditional branches, non-empty runner source/golden fixture artifacts, runner source/golden artifact hashes, runner entry-point semantics including command-emulation parser/error handling plus full Tasty group dispatch, and package-file hashes before any solver/build command',
                'record cabal.project solver constraints and runner executable options, plus no unexpected cabal.project solver constraints or unconditional plan fields before any solver/build command',
                'record test-suite type, buildable state, default-language, absent manual field, common import closure, entry point, direct build-depends with pinned version constraints, exact executable options, no unexpected Cabal custom-setup/setup-depends, no unexpected common imports, unresolved common imports, direct build-depends, hs-source-dirs, mixins, build-tool dependencies, default-extensions, other-extensions, cpp-options, autogen-modules, reexported-modules, module interface fields, extra-source-files, extra-doc-files, extra-tmp-files, data-files, or conditional branches, and exact other-modules closure for test:test-pandoc and test:test-pandoc-lua-engine, plus no unexpected test-options, native/system dependency fields, exact pandoc-lua-engine library HsLua module dependency closure, exact library exposed-modules closure, exact library source directory and other-modules closure, library source artifact hashes, Haskell2010 library default-language, no unexpected pandoc-lua-engine library Lua support build-depends, exposed modules, source directories, other modules, source artifacts, mixins or build-tool dependencies, generated modules, reexported modules, module interface fields, default/other extensions, file artifact globs, native/system dependency fields, or unexpected library conditional branches, exact pandoc-server library direct dependency, exposed-module, source-directory, and default-language closure, and exact pandoc-cli executable entry point, common import, direct dependency, option, source-directory, extension, other-module, known conditional-branch closure, conditional source artifact hashes, and conditional source semantics',
                'record benchmark:benchmark-pandoc type, buildable state, default-language, absent manual field, common import closure, entry point, direct build-depends with pinned version constraints, exact executable options, no unexpected Cabal benchmark common imports, unresolved common imports, direct build-depends, hs-source-dirs, mixins, build-tool dependencies, default-extensions, other-extensions, cpp-options, autogen-modules, reexported-modules, module interface fields, other-modules, extra-source-files, extra-doc-files, extra-tmp-files, data-files, or conditional branches, plus no unexpected benchmark-options or native/system dependency fields, non-empty source/data artifact closure, benchmark source/data artifact hashes, benchmark fixture semantics, entry-source semantics before any benchmark execution, and Pandoc roff/manual reader/writer/file-inference registry source semantics before any benchmark execution',
                'capture stable Cabal plan file provenance and any cabal.project.freeze unpinned-plan risk',
                'prepare exact Cabal dry-run command descriptors for runner-test-dependencies and benchmark-dependencies before any reviewed solver/build command',
                'prepare a descriptor-only local Cabal dry-run workspace with validated repo-local environment variable paths, build directories, transcript files, optional plan.json paths, matching --builddir arguments, and no live process environment output before any reviewed solver/build command',
                'only after the plan is reviewed, run a separate bounded runner slice with explicit artifact output paths',
            ] : [],
            'activationGate' => self::activationGate($missingFiles, $missingTools, $planStabilityClosure, $compilerTestedWithClosure, $packageIdentityClosure, $packageSetupClosure, $packageFlagDefinitionClosure, $packageDataFileClosure, $packageExtraFileClosure, $packageNativeSystemFieldClosure, $packageSourceRepositoryClosure, $projectPins, $projectSourceRepositoryClosure, $projectPackageClosure, $projectConstraintClosure, $projectUnconditionalFieldClosure, $projectConditionalBranchClosure, $runnerDependencyClosure, $benchmarkDependencyClosure, $luaEngineLibraryClosure, $serverLibraryClosure, $cliExecutableClosure, $runnerEntrySourceClosure, $runnerArtifactClosure, $benchmarkArtifactClosure, $benchmarkEntrySourceClosure, $formatRegistrySourceClosure, $cabalPlanDescriptorClosure),
        ];
    }

    /**
     * @return list<string>
     */
    public static function expectedStablePlanFiles(): array
    {
        return self::STABLE_PLAN_FILES;
    }

    /**
     * @return list<string>
     */
    public static function expectedCompilerGhcVersions(): array
    {
        return self::TESTED_GHC_VERSIONS;
    }

    /**
     * @return array<string, array{name:string, version:string, cabalVersion:string, buildType:string}>
     */
    public static function expectedPackageIdentities(): array
    {
        return self::PACKAGE_IDENTITIES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedPackageSetupDependencies(): array
    {
        return self::PACKAGE_EXPECTED_SETUP_DEPENDENCIES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedPackageFlagDefinitions(): array
    {
        return self::PACKAGE_EXPECTED_FLAG_DEFINITIONS;
    }

    /**
     * @return array<string, array<string, array{default:string|null, manual:string|null}>>
     */
    public static function expectedPackageFlagFields(): array
    {
        return self::PACKAGE_EXPECTED_FLAG_FIELDS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedPackageDataFiles(): array
    {
        return self::PACKAGE_EXPECTED_DATA_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedPackageExtraDocFiles(): array
    {
        return self::normalizeExpectedPackageFileGlobs(self::PACKAGE_EXPECTED_EXTRA_DOC_FILE_GLOBS);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedPackageExtraSourceFiles(): array
    {
        return self::normalizeExpectedPackageFileGlobs(self::PACKAGE_EXPECTED_EXTRA_SOURCE_FILE_GLOBS);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedPackageExtraTmpFiles(): array
    {
        return self::normalizeExpectedPackageFileGlobs(self::PACKAGE_EXPECTED_EXTRA_TMP_FILE_GLOBS);
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function expectedPackageNativeSystemFields(): array
    {
        return self::PACKAGE_EXPECTED_NATIVE_SYSTEM_FIELDS;
    }

    /**
     * @return array<string, array<string, array{type:string, location:string}>>
     */
    public static function expectedPackageSourceRepositories(): array
    {
        return self::PACKAGE_EXPECTED_SOURCE_REPOSITORIES;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedProjectPins(): array
    {
        return self::PROJECT_SOURCE_REPOSITORY_PINS;
    }

    /**
     * @return array<string, array{type:string, location:string}>
     */
    public static function expectedProjectSourceRepositories(): array
    {
        return self::PROJECT_SOURCE_REPOSITORIES;
    }

    /**
     * @return list<string>
     */
    public static function expectedProjectPackages(): array
    {
        return self::PROJECT_PACKAGES;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function expectedProjectFlags(): array
    {
        return self::PROJECT_FLAGS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedProjectPackageFields(): array
    {
        return self::PROJECT_PACKAGE_EXPECTED_FIELDS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedProjectConstraints(): array
    {
        return self::PROJECT_CONSTRAINTS;
    }

    /**
     * @return list<string>
     */
    public static function expectedProjectUnconditionalFields(): array
    {
        return self::PROJECT_EXPECTED_UNCONDITIONAL_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function expectedProjectConditionalBranches(): array
    {
        return self::PROJECT_EXPECTED_CONDITIONAL_BRANCHES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerDependencies(): array
    {
        return self::RUNNER_DIRECT_DEPENDENCIES;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function expectedRunnerDependencyConstraints(): array
    {
        return self::RUNNER_DEPENDENCY_CONSTRAINTS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerExecutableOptions(): array
    {
        return self::RUNNER_EXECUTABLE_OPTIONS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedRunnerDefaultLanguages(): array
    {
        return self::RUNNER_DEFAULT_LANGUAGES;
    }

    /**
     * @return array<string, string|null>
     */
    public static function expectedRunnerManualFields(): array
    {
        return self::RUNNER_EXPECTED_MANUAL_FIELDS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerCommonImports(): array
    {
        return self::RUNNER_EXPECTED_COMMON_IMPORTS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerSourceDirectories(): array
    {
        return self::expectedComponentSourceDirectories(self::RUNNER_ENTRY_POINTS);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerMixins(): array
    {
        return self::RUNNER_EXPECTED_MIXINS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerBuildTools(): array
    {
        return self::RUNNER_EXPECTED_BUILD_TOOLS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerTestOptions(): array
    {
        return self::RUNNER_EXPECTED_TEST_OPTIONS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerDefaultExtensions(): array
    {
        return self::RUNNER_EXPECTED_DEFAULT_EXTENSIONS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerOtherExtensions(): array
    {
        return self::RUNNER_EXPECTED_OTHER_EXTENSIONS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerCppOptions(): array
    {
        return self::RUNNER_EXPECTED_CPP_OPTIONS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerAutogenModules(): array
    {
        return self::RUNNER_EXPECTED_AUTOGEN_MODULES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerReexportedModules(): array
    {
        return self::RUNNER_EXPECTED_REEXPORTED_MODULES;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function expectedRunnerModuleInterfaceFields(): array
    {
        return self::RUNNER_EXPECTED_MODULE_INTERFACE_FIELDS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerExtraSourceFiles(): array
    {
        return self::RUNNER_EXPECTED_EXTRA_SOURCE_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerExtraDocFiles(): array
    {
        return self::RUNNER_EXPECTED_EXTRA_DOC_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerExtraTmpFiles(): array
    {
        return self::RUNNER_EXPECTED_EXTRA_TMP_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerDataFiles(): array
    {
        return self::RUNNER_EXPECTED_DATA_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerConditionalBranches(): array
    {
        return self::RUNNER_EXPECTED_CONDITIONAL_BRANCHES;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function expectedRunnerNativeSystemFields(): array
    {
        return self::RUNNER_EXPECTED_NATIVE_SYSTEM_FIELDS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkDependencies(): array
    {
        return self::BENCHMARK_DIRECT_DEPENDENCIES;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function expectedBenchmarkDependencyConstraints(): array
    {
        return self::BENCHMARK_DEPENDENCY_CONSTRAINTS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkExecutableOptions(): array
    {
        return self::BENCHMARK_EXECUTABLE_OPTIONS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedBenchmarkDefaultLanguages(): array
    {
        return self::BENCHMARK_DEFAULT_LANGUAGES;
    }

    /**
     * @return array<string, string|null>
     */
    public static function expectedBenchmarkManualFields(): array
    {
        return self::BENCHMARK_EXPECTED_MANUAL_FIELDS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkCommonImports(): array
    {
        return self::BENCHMARK_EXPECTED_COMMON_IMPORTS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkSourceDirectories(): array
    {
        return self::expectedComponentSourceDirectories(self::BENCHMARK_ENTRY_POINTS);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkMixins(): array
    {
        return self::BENCHMARK_EXPECTED_MIXINS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkBuildTools(): array
    {
        return self::BENCHMARK_EXPECTED_BUILD_TOOLS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkOptions(): array
    {
        return self::BENCHMARK_EXPECTED_BENCHMARK_OPTIONS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkDefaultExtensions(): array
    {
        return self::BENCHMARK_EXPECTED_DEFAULT_EXTENSIONS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkOtherExtensions(): array
    {
        return self::BENCHMARK_EXPECTED_OTHER_EXTENSIONS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkCppOptions(): array
    {
        return self::BENCHMARK_EXPECTED_CPP_OPTIONS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkAutogenModules(): array
    {
        return self::BENCHMARK_EXPECTED_AUTOGEN_MODULES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkReexportedModules(): array
    {
        return self::BENCHMARK_EXPECTED_REEXPORTED_MODULES;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function expectedBenchmarkModuleInterfaceFields(): array
    {
        return self::BENCHMARK_EXPECTED_MODULE_INTERFACE_FIELDS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkOtherModules(): array
    {
        return self::BENCHMARK_EXPECTED_OTHER_MODULES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkExtraSourceFiles(): array
    {
        return self::BENCHMARK_EXPECTED_EXTRA_SOURCE_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkExtraDocFiles(): array
    {
        return self::BENCHMARK_EXPECTED_EXTRA_DOC_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkExtraTmpFiles(): array
    {
        return self::BENCHMARK_EXPECTED_EXTRA_TMP_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkDataFiles(): array
    {
        return self::BENCHMARK_EXPECTED_DATA_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkConditionalBranches(): array
    {
        return self::BENCHMARK_EXPECTED_CONDITIONAL_BRANCHES;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function expectedBenchmarkNativeSystemFields(): array
    {
        return self::BENCHMARK_EXPECTED_NATIVE_SYSTEM_FIELDS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedBenchmarkArtifacts(): array
    {
        return self::BENCHMARK_ARTIFACTS;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function expectedBenchmarkArtifactSemantics(): array
    {
        return self::BENCHMARK_ARTIFACT_SEMANTICS;
    }

    /**
     * @return array<string, array{entryFile:string, requiredSnippets:array<string, string>}>
     */
    public static function expectedBenchmarkEntrySourceSemantics(): array
    {
        return self::BENCHMARK_ENTRY_SOURCE_SEMANTICS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedFormatRegistrySourceArtifacts(): array
    {
        return self::FORMAT_REGISTRY_SOURCE_ARTIFACTS;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function expectedFormatRegistrySourceSemantics(): array
    {
        return self::FORMAT_REGISTRY_SOURCE_SEMANTICS;
    }

    /**
     * @return array<string, array{program:string, arguments:list<string>, targets:list<string>, component:string, workingDirectory:string, executionPolicy:string, outputCapture:list<string>}>
     */
    public static function expectedCabalPlanCommands(): array
    {
        return self::CABAL_PLAN_COMMANDS;
    }

    /**
     * @return array{environmentPolicy:string, workingDirectory:string, environmentVariables:array<string, string>, buildDirectories:array<string, string>, transcriptFiles:array<string, string>, optionalPlanJsonFiles:array<string, string>, preflight:list<string>}
     */
    public static function expectedCabalPlanWorkspace(): array
    {
        return self::CABAL_PLAN_WORKSPACE;
    }

    /**
     * @param array<string, array<string, mixed>> $commands
     * @param array<string, mixed> $workspace
     * @return array{
     *   expectedCommands:list<string>,
     *   presentCommands:list<string>,
     *   missingCommands:list<string>,
     *   unexpectedCommands:list<string>,
     *   commandPolicyViolations:list<string>,
     *   workspacePolicyViolations:list<string>,
     *   commandWorkspaceMismatches:list<string>
     * }
     */
    public static function auditCabalPlanDescriptorClosure(array $commands, array $workspace): array
    {
        $expectedCommands = array_keys(self::CABAL_PLAN_COMMANDS);
        $presentCommands = array_keys($commands);
        sort($presentCommands);
        $missingCommands = array_values(array_diff($expectedCommands, $presentCommands));
        $unexpectedCommands = array_values(array_diff($presentCommands, $expectedCommands));
        $commandPolicyViolations = [];
        $workspacePolicyViolations = [];
        $commandWorkspaceMismatches = [];

        $workspaceBuildDirectories = isset($workspace['buildDirectories']) && is_array($workspace['buildDirectories'])
            ? $workspace['buildDirectories']
            : [];

        foreach ($expectedCommands as $name) {
            if (!isset($commands[$name]) || !is_array($commands[$name])) {
                continue;
            }

            $command = $commands[$name];
            $arguments = isset($command['arguments']) && is_array($command['arguments'])
                ? array_values(array_filter($command['arguments'], 'is_string'))
                : [];
            $targets = isset($command['targets']) && is_array($command['targets'])
                ? array_values(array_filter($command['targets'], 'is_string'))
                : [];
            $buildDirectory = is_string($command['buildDirectory'] ?? null) ? $command['buildDirectory'] : '';
            $workspaceBuildDirectory = is_string($workspaceBuildDirectories[$name] ?? null)
                ? $workspaceBuildDirectories[$name]
                : '';

            if (($command['program'] ?? null) !== 'cabal') {
                $commandPolicyViolations[] = $name . ' program must be cabal';
            }
            if (($command['executionPolicy'] ?? null) !== 'descriptor-only; do not execute from this isolated PHP lane') {
                $commandPolicyViolations[] = $name . ' executionPolicy must remain descriptor-only';
            }
            foreach (['v2-build', '--offline', '--dry-run', '--only-dependencies'] as $requiredArgument) {
                if (!in_array($requiredArgument, $arguments, true)) {
                    $commandPolicyViolations[] = $name . ' missing required dry-run argument ' . $requiredArgument;
                }
            }
            foreach ($targets as $target) {
                if (!in_array($target, $arguments, true)) {
                    $commandPolicyViolations[] = $name . ' target not present in arguments: ' . $target;
                }
            }

            $buildDirectoryViolation = self::cabalPlanPathPolicyViolation($buildDirectory);
            if ($buildDirectoryViolation !== null) {
                $commandPolicyViolations[] = $name . ' buildDirectory ' . $buildDirectoryViolation . ': ' . $buildDirectory;
            }

            $builddirArguments = [];
            foreach ($arguments as $argument) {
                $argumentViolation = self::cabalPlanArgumentPathPolicyViolation($argument);
                if ($argumentViolation !== null) {
                    $commandPolicyViolations[] = $name . ' argument ' . $argumentViolation . ': ' . $argument;
                }
                if (str_starts_with($argument, '--builddir=')) {
                    $builddirArguments[] = substr($argument, strlen('--builddir='));
                }
            }
            if ($builddirArguments === []) {
                $commandPolicyViolations[] = $name . ' missing --builddir argument';
            }

            if ($workspaceBuildDirectory === '') {
                $commandWorkspaceMismatches[] = $name . ' missing matching workspace build directory';
            } elseif ($buildDirectory !== $workspaceBuildDirectory) {
                $commandWorkspaceMismatches[] = $name . ' command buildDirectory does not match workspace build directory';
            }

            foreach ($builddirArguments as $builddirArgument) {
                if ($workspaceBuildDirectory !== '' && $builddirArgument !== $workspaceBuildDirectory) {
                    $commandWorkspaceMismatches[] = $name . ' --builddir argument does not match workspace build directory';
                }
            }
        }

        $expectedEnvironmentVariables = array_keys(self::CABAL_PLAN_WORKSPACE['environmentVariables']);
        $presentEnvironmentVariables = isset($workspace['environmentVariables']) && is_array($workspace['environmentVariables'])
            ? array_keys($workspace['environmentVariables'])
            : [];
        sort($presentEnvironmentVariables);
        foreach (array_diff($expectedEnvironmentVariables, $presentEnvironmentVariables) as $variable) {
            $workspacePolicyViolations[] = 'missing environment variable path descriptor: ' . $variable;
        }
        foreach (array_diff($presentEnvironmentVariables, $expectedEnvironmentVariables) as $variable) {
            $workspacePolicyViolations[] = 'unexpected environment variable path descriptor: ' . $variable;
        }

        if (($workspace['environmentPolicy'] ?? null) !== self::CABAL_PLAN_WORKSPACE['environmentPolicy']) {
            $workspacePolicyViolations[] = 'environmentPolicy must forbid live process environment output';
        }

        foreach (['environmentVariables', 'buildDirectories', 'transcriptFiles', 'optionalPlanJsonFiles'] as $section) {
            if (!isset($workspace[$section]) || !is_array($workspace[$section])) {
                $workspacePolicyViolations[] = 'missing workspace descriptor section: ' . $section;
                continue;
            }

            foreach ($workspace[$section] as $name => $path) {
                if (!is_string($path)) {
                    $workspacePolicyViolations[] = $section . '.' . (string) $name . ' path must be a string';
                    continue;
                }
                $pathViolation = self::cabalPlanPathPolicyViolation($path);
                if ($pathViolation !== null) {
                    $workspacePolicyViolations[] = $section . '.' . (string) $name . ' ' . $pathViolation . ': ' . $path;
                }
            }
        }

        return [
            'expectedCommands' => $expectedCommands,
            'presentCommands' => $presentCommands,
            'missingCommands' => $missingCommands,
            'unexpectedCommands' => $unexpectedCommands,
            'commandPolicyViolations' => $commandPolicyViolations,
            'workspacePolicyViolations' => $workspacePolicyViolations,
            'commandWorkspaceMismatches' => $commandWorkspaceMismatches,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerOtherModules(): array
    {
        return self::RUNNER_OTHER_MODULES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryDependencies(): array
    {
        return self::LUA_ENGINE_LIBRARY_DEPENDENCIES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryExposedModules(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPOSED_MODULES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibrarySourceDirectories(): array
    {
        return self::LUA_ENGINE_LIBRARY_SOURCE_DIRECTORIES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryOtherModules(): array
    {
        return self::LUA_ENGINE_LIBRARY_OTHER_MODULES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibrarySourceArtifacts(): array
    {
        $artifacts = [];
        foreach (array_merge(self::LUA_ENGINE_LIBRARY_EXPOSED_MODULES, self::LUA_ENGINE_LIBRARY_OTHER_MODULES) as $module) {
            $artifacts[] = 'pandoc-lua-engine/src/' . str_replace('.', '/', $module) . '.hs';
        }

        return $artifacts;
    }

    public static function expectedLuaEngineLibraryDefaultLanguage(): string
    {
        return self::LUA_ENGINE_LIBRARY_DEFAULT_LANGUAGE;
    }

    /**
     * @return list<string>
     */
    public static function allowedLuaEngineLibraryConditionalBranches(): array
    {
        return self::LUA_ENGINE_LIBRARY_ALLOWED_CONDITIONAL_BRANCHES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryMixins(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_MIXINS;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryBuildTools(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_BUILD_TOOLS;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryAutogenModules(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_AUTOGEN_MODULES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryReexportedModules(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_REEXPORTED_MODULES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedLuaEngineLibraryModuleInterfaceFields(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_MODULE_INTERFACE_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryDefaultExtensions(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_DEFAULT_EXTENSIONS;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryOtherExtensions(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_OTHER_EXTENSIONS;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryExtraSourceFiles(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_SOURCE_FILES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryExtraDocFiles(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_DOC_FILES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryExtraTmpFiles(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_TMP_FILES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryDataFiles(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_DATA_FILES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedLuaEngineLibraryNativeSystemFields(): array
    {
        return self::LUA_ENGINE_LIBRARY_EXPECTED_NATIVE_SYSTEM_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function expectedServerLibraryDependencies(): array
    {
        return self::SERVER_LIBRARY_DEPENDENCIES;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedServerLibraryDependencyConstraints(): array
    {
        return self::SERVER_LIBRARY_DEPENDENCY_CONSTRAINTS;
    }

    /**
     * @return list<string>
     */
    public static function expectedServerLibraryExposedModules(): array
    {
        return self::SERVER_LIBRARY_EXPOSED_MODULES;
    }

    /**
     * @return list<string>
     */
    public static function expectedServerLibrarySourceDirectories(): array
    {
        return self::SERVER_LIBRARY_SOURCE_DIRECTORIES;
    }

    public static function expectedServerLibraryDefaultLanguage(): string
    {
        return self::SERVER_LIBRARY_DEFAULT_LANGUAGE;
    }

    /**
     * @return list<string>
     */
    public static function expectedCliExecutableDependencies(): array
    {
        return self::CLI_EXECUTABLE_DEPENDENCIES;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedCliExecutableDependencyConstraints(): array
    {
        return self::CLI_EXECUTABLE_DEPENDENCY_CONSTRAINTS;
    }

    /**
     * @return list<string>
     */
    public static function expectedCliExecutableSourceDirectories(): array
    {
        return self::CLI_EXECUTABLE_SOURCE_DIRECTORIES;
    }

    /**
     * @return list<string>
     */
    public static function expectedCliExecutableOptions(): array
    {
        return self::CLI_EXECUTABLE_GHC_OPTIONS;
    }

    public static function expectedCliExecutableDefaultLanguage(): string
    {
        return self::CLI_EXECUTABLE_DEFAULT_LANGUAGE;
    }

    /**
     * @return list<string>
     */
    public static function expectedCliExecutableCommonImports(): array
    {
        return self::CLI_EXECUTABLE_COMMON_IMPORTS;
    }

    /**
     * @return list<string>
     */
    public static function expectedCliExecutableOtherExtensions(): array
    {
        return self::CLI_EXECUTABLE_OTHER_EXTENSIONS;
    }

    /**
     * @return list<string>
     */
    public static function expectedCliExecutableOtherModules(): array
    {
        return self::CLI_EXECUTABLE_OTHER_MODULES;
    }

    /**
     * @return list<string>
     */
    public static function expectedCliExecutableConditionalBranches(): array
    {
        return self::CLI_EXECUTABLE_EXPECTED_CONDITIONAL_BRANCHES;
    }

    /**
     * @return array<string, array{sourceDirectories:list<string>, ghcOptions:list<string>, cppOptions:list<string>, buildDepends:list<string>, otherModules:list<string>, nativeSystemFields:list<string>}>
     */
    public static function expectedCliExecutableConditionalFieldClosure(): array
    {
        return self::CLI_EXECUTABLE_EXPECTED_CONDITIONAL_FIELD_CLOSURE;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedCliExecutableSourceArtifacts(): array
    {
        return self::CLI_EXECUTABLE_SOURCE_ARTIFACTS;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function expectedCliExecutableSourceSemantics(): array
    {
        return self::CLI_EXECUTABLE_SOURCE_SEMANTICS;
    }

    /**
     * @return array<string, array{entryFile:string, requiredSnippets:array<string, string>}>
     */
    public static function expectedRunnerEntrySourceSemantics(): array
    {
        return self::RUNNER_ENTRY_SOURCE_SEMANTICS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedRunnerArtifacts(): array
    {
        return self::requiredRunnerArtifacts();
    }

    /**
     * @return list<string>
     */
    public static function parseCabalProjectPackages(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
        $rawPackages = '';
        $capturing = false;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*packages\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $rawPackages .= ' ' . $match[1];
                $capturing = true;
                continue;
            }

            if (!$capturing) {
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^\s+[^\s:]+(?:\s+[^\s:]+)*\s*$/', $line) === 1) {
                $rawPackages .= ' ' . trim($line);
                continue;
            }

            $capturing = false;
        }

        $packages = [];
        foreach (preg_split('/\s+/', trim($rawPackages)) ?: [] as $package) {
            if ($package !== '' && !in_array($package, $packages, true)) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function parseCabalProjectFlags(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
        $flags = [];
        $currentPackage = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*package\s+([A-Za-z0-9_.-]+)\s*$/', $line, $match) === 1) {
                $currentPackage = $match[1];
                $flags[$currentPackage] ??= [];
                continue;
            }

            if ($currentPackage === null) {
                continue;
            }

            if (preg_match('/^\s*flags\s*:\s*(.*?)\s*$/', $line, $match) !== 1) {
                continue;
            }

            foreach (preg_split('/\s+/', trim($match[1])) ?: [] as $token) {
                if (preg_match('/^([+-])([A-Za-z0-9_-]+)$/', $token, $flagMatch) === 1) {
                    $flags[$currentPackage][$flagMatch[2]] = $flagMatch[1] === '+';
                }
            }
        }

        ksort($flags);
        foreach ($flags as &$packageFlags) {
            ksort($packageFlags);
        }
        unset($packageFlags);

        return $flags;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function parseCabalProjectPackageFields(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
        $fieldsByPackage = [];
        $currentPackage = null;
        $packageIndent = null;
        $currentField = null;
        $currentFieldIndent = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^(\s*)package\s+([A-Za-z0-9_.-]+)\s*$/', $line, $match) === 1) {
                $currentPackage = $match[2];
                $packageIndent = strlen($match[1]);
                $currentField = null;
                $currentFieldIndent = null;
                $fieldsByPackage[$currentPackage] ??= [];
                continue;
            }

            if ($currentPackage === null || $packageIndent === null) {
                continue;
            }

            if (trim($line) === '') {
                $currentField = null;
                $currentFieldIndent = null;
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));
            if ($indent <= $packageIndent) {
                $currentPackage = null;
                $packageIndent = null;
                $currentField = null;
                $currentFieldIndent = null;
                continue;
            }

            if ($currentField !== null && $currentFieldIndent !== null && $indent > $currentFieldIndent && preg_match('/^\s+(.+?)\s*$/', $line, $match) === 1) {
                $continuation = trim($match[1]);
                if ($continuation !== '') {
                    $fieldsByPackage[$currentPackage][$currentField] .= "\n" . $continuation;
                }
                continue;
            }

            if (preg_match('/^\s*([A-Za-z][A-Za-z0-9_-]*)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $currentField = strtolower($match[1]);
                $currentFieldIndent = $indent;
                $value = trim($match[2]);
                $fieldsByPackage[$currentPackage][$currentField] = isset($fieldsByPackage[$currentPackage][$currentField])
                    ? $fieldsByPackage[$currentPackage][$currentField] . "\n" . $value
                    : $value;
            }
        }

        foreach ($fieldsByPackage as &$fields) {
            foreach ($fields as &$value) {
                $value = self::normalizeCabalListItem($value);
            }
            unset($value);
            ksort($fields);
        }
        unset($fields);
        ksort($fieldsByPackage);

        return $fieldsByPackage;
    }

    /**
     * @return array<string, string>
     */
    public static function parseCabalProjectConstraints(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
        $rawConstraints = '';
        $capturing = false;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*constraints\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $rawConstraints .= ' ' . $match[1];
                $capturing = true;
                continue;
            }

            if (!$capturing) {
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^\s+(.+?)\s*$/', $line, $match) === 1) {
                $rawConstraints .= ' ' . trim($match[1]);
                continue;
            }

            $capturing = false;
        }

        $constraints = [];
        foreach (explode(',', str_replace("\n", ' ', $rawConstraints)) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\s+(.+?)\s*$/', $part, $match) === 1) {
                $constraints[$match[1]] = preg_replace('/\s+/', ' ', trim($match[2])) ?? trim($match[2]);
            }
        }

        ksort($constraints);
        return $constraints;
    }

    /**
     * @return array<string, string>
     */
    public static function parseCabalProjectUnconditionalFields(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
        $fields = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\s*:\s*(.*?)\s*$/', $line, $match) !== 1) {
                continue;
            }

            $fields[strtolower($match[1])] = self::normalizeCabalListItem($match[2]);
        }

        ksort($fields);
        return $fields;
    }

    /**
     * @return list<string>
     */
    public static function parseCabalProjectConditionalBranches(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
        $branches = [];
        $lastConditionByIndent = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(?:if|elif|else)\b/i', $trimmed) !== 1) {
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));
            $condition = self::normalizeCabalListItem($trimmed);
            $label = $condition;
            if (preg_match('/^else\b/i', $condition) === 1 && isset($lastConditionByIndent[$indent])) {
                $label = 'else after ' . $lastConditionByIndent[$indent];
            }

            if (!in_array($label, $branches, true)) {
                $branches[] = $label;
            }

            foreach (array_keys($lastConditionByIndent) as $knownIndent) {
                if ($knownIndent > $indent) {
                    unset($lastConditionByIndent[$knownIndent]);
                }
            }

            if (preg_match('/^else\b/i', $condition) !== 1) {
                $lastConditionByIndent[$indent] = $condition;
            }
        }

        return $branches;
    }

    /**
     * @return list<string>
     */
    public static function parseCabalTestedWithGhcVersions(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
        $raw = '';
        $capturing = false;
        $fieldIndent = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $indent = strlen($line) - strlen(ltrim($line));

            if (preg_match('/^\s*tested-with\s*:\s*(.*?)\s*$/i', $line, $match) === 1) {
                $raw .= ' ' . trim($match[1]);
                $capturing = true;
                $fieldIndent = $indent;
                continue;
            }

            if (!$capturing) {
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if ($fieldIndent !== null && $indent > $fieldIndent && preg_match('/^\s+(.*?)\s*$/', $line, $match) === 1) {
                $raw .= ' ' . trim($match[1]);
                continue;
            }

            $capturing = false;
            $fieldIndent = null;
        }

        $versions = [];
        if (preg_match_all('/\bGHC\s*==\s*([0-9]+(?:\.[0-9]+){1,3})\b/i', $raw, $matches) === false) {
            return [];
        }

        foreach ($matches[1] ?? [] as $version) {
            if (!in_array($version, $versions, true)) {
                $versions[] = $version;
            }
        }

        return $versions;
    }

    /**
     * @return array{name:string|null, version:string|null, cabalVersion:string|null, buildType:string|null}
     */
    public static function parseCabalPackageHeader(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
        $fields = [
            'name' => null,
            'version' => null,
            'cabalVersion' => null,
            'buildType' => null,
        ];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(?:common|library|test-suite|benchmark|executable|flag|source-repository)\b/i', $line) === 1) {
                break;
            }

            if (preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) !== 1) {
                continue;
            }

            $value = self::normalizeCabalListItem($match[2]);
            switch (strtolower($match[1])) {
                case 'name':
                    $fields['name'] = $value === '' ? null : $value;
                    break;
                case 'version':
                    $fields['version'] = $value === '' ? null : $value;
                    break;
                case 'cabal-version':
                    $fields['cabalVersion'] = $value === '' ? null : $value;
                    break;
                case 'build-type':
                    $fields['buildType'] = $value === '' ? null : $value;
                    break;
            }
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    public static function parseCabalPackageDataFiles(string $contents): array
    {
        return self::parseCabalPackageTopLevelFileGlobs($contents, 'data-files');
    }

    /**
     * @return list<string>
     */
    public static function parseCabalPackageExtraDocFiles(string $contents): array
    {
        return self::parseCabalPackageTopLevelFileGlobs($contents, 'extra-doc-files');
    }

    /**
     * @return list<string>
     */
    public static function parseCabalPackageExtraSourceFiles(string $contents): array
    {
        return self::parseCabalPackageTopLevelFileGlobs($contents, 'extra-source-files');
    }

    /**
     * @return list<string>
     */
    public static function parseCabalPackageExtraTmpFiles(string $contents): array
    {
        return self::parseCabalPackageTopLevelFileGlobs($contents, 'extra-tmp-files');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function parseCabalPackageNativeSystemFields(string $contents): array
    {
        return self::extractCabalNativeSystemFields(
            self::parseCabalPackageTopLevelFields($contents, self::CABAL_NATIVE_SYSTEM_FIELDS)
        );
    }

    /**
     * @return array<string, array{type:string|null, location:string|null, fields:array<string, string>}>
     */
    public static function parseCabalPackageSourceRepositories(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
        $repositories = [];
        $currentName = null;
        $currentFields = [];
        $lastField = null;
        $lastFieldIndent = null;

        $finish = static function () use (&$repositories, &$currentName, &$currentFields, &$lastField, &$lastFieldIndent): void {
            if ($currentName === null) {
                return;
            }

            $fields = [];
            foreach ($currentFields as $field => $value) {
                $fields[$field] = self::normalizeCabalListItem($value);
            }
            ksort($fields);

            $type = trim((string) ($fields['type'] ?? ''));
            $location = trim((string) ($fields['location'] ?? ''));
            $repositories[$currentName] = [
                'type' => $type === '' ? null : strtolower($type),
                'location' => $location === '' ? null : $location,
                'fields' => $fields,
            ];

            $currentName = null;
            $currentFields = [];
            $lastField = null;
            $lastFieldIndent = null;
        };

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^source-repository\s+([A-Za-z0-9_.-]+)\s*$/i', $line, $match) === 1) {
                $finish();
                $currentName = strtolower($match[1]);
                continue;
            }

            if ($currentName === null) {
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^\S/', $line) === 1) {
                $finish();
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));
            if ($lastField !== null && $lastFieldIndent !== null && $indent > $lastFieldIndent && preg_match('/^\s+(.*?)\s*$/', $line, $match) === 1) {
                $continuation = trim($match[1]);
                if ($continuation !== '') {
                    $currentFields[$lastField] .= "\n" . $continuation;
                }
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $lastField = strtolower($match[1]);
                $lastFieldIndent = $indent;
                $currentFields[$lastField] = trim($match[2]);
            }
        }

        $finish();
        ksort($repositories);

        return $repositories;
    }

    /**
     * @return list<string>
     */
    private static function parseCabalPackageTopLevelFileGlobs(string $contents, string $fieldName): array
    {
        $contents = self::stripCabalLineComments($contents);
        $raw = '';
        $capturing = false;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(?:common|library|test-suite|benchmark|executable|flag|source-repository)\b/i', $line) === 1) {
                break;
            }

            if (preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $capturing = strtolower($match[1]) === $fieldName;
                if ($capturing) {
                    $raw .= "\n" . $match[2];
                }
                continue;
            }

            if ($capturing && preg_match('/^\s+(.+?)\s*$/', $line, $match) === 1) {
                $raw .= "\n" . $match[1];
            }
        }

        return self::extractCabalFileGlobs($raw);
    }

    /**
     * @param list<string> $fieldNames
     * @return array<string, string>
     */
    private static function parseCabalPackageTopLevelFields(string $contents, array $fieldNames): array
    {
        $fields = [];
        $fieldNames = array_values(array_unique(array_map('strtolower', $fieldNames)));
        $capturing = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(?:common|library|test-suite|benchmark|executable|flag|source-repository)\b/i', $line) === 1) {
                break;
            }

            if (preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $field = strtolower($match[1]);
                $capturing = in_array($field, $fieldNames, true) ? $field : null;
                if ($capturing !== null) {
                    $fields[$capturing] = ($fields[$capturing] ?? '') . "\n" . $match[2];
                }
                continue;
            }

            if ($capturing !== null && preg_match('/^\s+(.+?)\s*$/', $line, $match) === 1) {
                $fields[$capturing] .= "\n" . $match[1];
            }
        }

        ksort($fields);
        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public static function parseCabalProjectPins(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
        $pins = [];
        $current = [];
        $finish = static function (array $block) use (&$pins): void {
            $location = trim((string) ($block['location'] ?? ''));
            $tag = trim((string) ($block['tag'] ?? ''));
            if ($location === '' || $tag === '') {
                return;
            }

            $path = parse_url($location, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = $location;
            }

            $repo = strtolower((string) preg_replace('/\.git$/', '', basename($path)));
            if ($repo !== '') {
                $pins[$repo] = $tag;
            }
        };

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*source-repository-package\s*$/', $line) === 1) {
                if ($current !== []) {
                    $finish($current);
                    $current = [];
                }
                $current['source-repository-package'] = 'true';
                continue;
            }

            if ($current === []) {
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $current[strtolower($match[1])] = $match[2];
            }
        }

        if ($current !== []) {
            $finish($current);
        }

        ksort($pins);
        return $pins;
    }

    /**
     * @return array<string, array{type:string|null, location:string, tag:string|null, fields:array<string, string>}>
     */
    public static function parseCabalProjectSourceRepositories(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
        $repositories = [];
        $current = [];
        $finish = static function (array $block) use (&$repositories): void {
            $location = trim((string) ($block['location'] ?? ''));
            if ($location === '') {
                return;
            }

            $path = parse_url($location, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = $location;
            }

            $repo = strtolower((string) preg_replace('/\.git$/', '', basename($path)));
            if ($repo === '') {
                return;
            }

            $type = trim((string) ($block['type'] ?? ''));
            $tag = trim((string) ($block['tag'] ?? ''));
            $fields = [];
            foreach ($block as $field => $value) {
                if ($field === 'source-repository-package') {
                    continue;
                }
                $fields[(string) $field] = self::normalizeCabalListItem((string) $value);
            }
            ksort($fields);
            $repositories[$repo] = [
                'type' => $type === '' ? null : strtolower($type),
                'location' => $location,
                'tag' => $tag === '' ? null : $tag,
                'fields' => $fields,
            ];
        };

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*source-repository-package\s*$/', $line) === 1) {
                if ($current !== []) {
                    $finish($current);
                    $current = [];
                }
                $current['source-repository-package'] = 'true';
                continue;
            }

            if ($current === []) {
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $current[strtolower($match[1])] = $match[2];
            }
        }

        if ($current !== []) {
            $finish($current);
        }

        ksort($repositories);
        return $repositories;
    }

    /**
     * @return array<string, array{setupDepends:list<string>, dependencyConstraints:array<string, string>}>
     */
    public static function parseCabalCustomSetups(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $setups = [];

        foreach ($stanzas as $key => $stanza) {
            if ($stanza['type'] !== 'custom-setup') {
                continue;
            }

            $fields = self::resolveCabalStanzaFields($key, $stanzas);
            $setups[$stanza['name']] = [
                'setupDepends' => self::extractCabalDependencyNames($fields['setup-depends'] ?? ''),
                'dependencyConstraints' => self::extractCabalDependencyConstraints($fields['setup-depends'] ?? ''),
            ];
        }

        ksort($setups);
        return $setups;
    }

    /**
     * @return list<string>
     */
    public static function parseCabalPackageFlags(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $flags = [];

        foreach ($stanzas as $stanza) {
            if ($stanza['type'] !== 'flag') {
                continue;
            }

            if (!in_array($stanza['name'], $flags, true)) {
                $flags[] = $stanza['name'];
            }
        }

        sort($flags);
        return $flags;
    }

    /**
     * @return array<string, array{default:string|null, manual:string|null}>
     */
    public static function parseCabalPackageFlagFields(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $flags = [];

        foreach ($stanzas as $stanza) {
            if ($stanza['type'] !== 'flag') {
                continue;
            }

            $fields = $stanza['fields'];
            $flags[$stanza['name']] = [
                'default' => self::normalizeCabalFlagBooleanValue($fields['default'] ?? null),
                'manual' => self::normalizeCabalFlagBooleanValue($fields['manual'] ?? null),
            ];
        }

        ksort($flags);
        return $flags;
    }

    /**
     * @return array<string, array{type:string|null, buildable:bool|null, manual:string|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, cppOptions:list<string>, autogenModules:list<string>, reexportedModules:list<string>, moduleInterfaceFields:array<string, list<string>>, extraSourceFiles:list<string>, extraDocFiles:list<string>, extraTmpFiles:list<string>, dataFiles:list<string>, conditionalBranches:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, testOptions:list<string>, defaultExtensions:list<string>, otherExtensions:list<string>, otherModules:list<string>, nativeSystemFields:array<string, list<string>>}>
     */
    public static function parseCabalTestSuites(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $suites = [];

        foreach ($stanzas as $key => $stanza) {
            if ($stanza['type'] !== 'test-suite') {
                continue;
            }

            $fields = self::resolveCabalStanzaFields($key, $stanzas);
            $sourceDirectories = self::splitWords($fields['hs-source-dirs'] ?? '');
            $buildDepends = self::extractCabalDependencyNames($fields['build-depends'] ?? '');
            $dependencyConstraints = self::extractCabalDependencyConstraints($fields['build-depends'] ?? '');
            $ghcOptions = self::splitWords($fields['ghc-options'] ?? '');
            $cppOptions = self::splitWords($fields['cpp-options'] ?? '');
            $autogenModules = self::extractCabalModuleNames($fields['autogen-modules'] ?? '');
            $reexportedModules = self::extractCabalReexportedModuleSpecs($fields['reexported-modules'] ?? '');
            $moduleInterfaceFields = self::extractCabalModuleInterfaceFields($fields);
            $extraSourceFiles = self::extractCabalFileGlobs($fields['extra-source-files'] ?? '');
            $extraDocFiles = self::extractCabalFileGlobs($fields['extra-doc-files'] ?? '');
            $extraTmpFiles = self::extractCabalFileGlobs($fields['extra-tmp-files'] ?? '');
            $dataFiles = self::extractCabalFileGlobs($fields['data-files'] ?? '');
            $conditionalBranches = self::resolveCabalStanzaConditionals($key, $stanzas);
            $defaultLanguage = self::firstFieldValue($fields['default-language'] ?? null);
            $mixins = self::extractCabalMixinSpecs($fields['mixins'] ?? '');
            $buildToolDepends = self::extractCabalBuildToolDepends($fields['build-tool-depends'] ?? '');
            $buildTools = self::extractCabalBuildTools($fields['build-tools'] ?? '');
            $testOptions = self::splitWords($fields['test-options'] ?? '');
            $defaultExtensions = self::extractCabalDefaultExtensions(
                self::joinCabalFieldValues($fields['default-extensions'] ?? '', $fields['extensions'] ?? '')
            );
            $otherExtensions = self::extractCabalDefaultExtensions($fields['other-extensions'] ?? '');
            $otherModules = self::extractCabalModuleNames($fields['other-modules'] ?? '');
            $nativeSystemFields = self::extractCabalNativeSystemFields($fields);
            $commonImportClosure = self::resolveCabalCommonImportClosure($key, $stanzas);

            $suites[$stanza['name']] = [
                'type' => self::firstFieldValue($fields['type'] ?? null),
                'buildable' => self::cabalBuildableState($fields['buildable'] ?? null),
                'manual' => self::cabalOptionalBooleanText($fields['manual'] ?? null),
                'mainIs' => self::firstFieldValue($fields['main-is'] ?? null),
                'commonImports' => $commonImportClosure['imports'],
                'unresolvedCommonImports' => $commonImportClosure['unresolved'],
                'sourceDirectories' => $sourceDirectories,
                'buildDepends' => $buildDepends,
                'dependencyConstraints' => $dependencyConstraints,
                'ghcOptions' => $ghcOptions,
                'cppOptions' => $cppOptions,
                'autogenModules' => $autogenModules,
                'reexportedModules' => $reexportedModules,
                'moduleInterfaceFields' => $moduleInterfaceFields,
                'extraSourceFiles' => $extraSourceFiles,
                'extraDocFiles' => $extraDocFiles,
                'extraTmpFiles' => $extraTmpFiles,
                'dataFiles' => $dataFiles,
                'conditionalBranches' => $conditionalBranches,
                'defaultLanguage' => $defaultLanguage,
                'mixins' => $mixins,
                'buildToolDepends' => $buildToolDepends,
                'buildTools' => $buildTools,
                'testOptions' => $testOptions,
                'defaultExtensions' => $defaultExtensions,
                'otherExtensions' => $otherExtensions,
                'otherModules' => $otherModules,
                'nativeSystemFields' => $nativeSystemFields,
            ];
        }

        ksort($suites);
        return $suites;
    }

    /**
     * @return array<string, array{type:string|null, buildable:bool|null, manual:string|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, cppOptions:list<string>, autogenModules:list<string>, reexportedModules:list<string>, moduleInterfaceFields:array<string, list<string>>, otherModules:list<string>, extraSourceFiles:list<string>, extraDocFiles:list<string>, extraTmpFiles:list<string>, dataFiles:list<string>, conditionalBranches:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, benchmarkOptions:list<string>, defaultExtensions:list<string>, otherExtensions:list<string>, nativeSystemFields:array<string, list<string>>}>
     */
    public static function parseCabalBenchmarks(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $benchmarks = [];

        foreach ($stanzas as $key => $stanza) {
            if ($stanza['type'] !== 'benchmark') {
                continue;
            }

            $fields = self::resolveCabalStanzaFields($key, $stanzas);
            $commonImportClosure = self::resolveCabalCommonImportClosure($key, $stanzas);
            $benchmarks[$stanza['name']] = [
                'type' => self::firstFieldValue($fields['type'] ?? null),
                'buildable' => self::cabalBuildableState($fields['buildable'] ?? null),
                'manual' => self::cabalOptionalBooleanText($fields['manual'] ?? null),
                'mainIs' => self::firstFieldValue($fields['main-is'] ?? null),
                'commonImports' => $commonImportClosure['imports'],
                'unresolvedCommonImports' => $commonImportClosure['unresolved'],
                'sourceDirectories' => self::splitWords($fields['hs-source-dirs'] ?? ''),
                'buildDepends' => self::extractCabalDependencyNames($fields['build-depends'] ?? ''),
                'dependencyConstraints' => self::extractCabalDependencyConstraints($fields['build-depends'] ?? ''),
                'ghcOptions' => self::splitWords($fields['ghc-options'] ?? ''),
                'cppOptions' => self::splitWords($fields['cpp-options'] ?? ''),
                'autogenModules' => self::extractCabalModuleNames($fields['autogen-modules'] ?? ''),
                'reexportedModules' => self::extractCabalReexportedModuleSpecs($fields['reexported-modules'] ?? ''),
                'moduleInterfaceFields' => self::extractCabalModuleInterfaceFields($fields),
                'otherModules' => self::extractCabalModuleNames($fields['other-modules'] ?? ''),
                'extraSourceFiles' => self::extractCabalFileGlobs($fields['extra-source-files'] ?? ''),
                'extraDocFiles' => self::extractCabalFileGlobs($fields['extra-doc-files'] ?? ''),
                'extraTmpFiles' => self::extractCabalFileGlobs($fields['extra-tmp-files'] ?? ''),
                'dataFiles' => self::extractCabalFileGlobs($fields['data-files'] ?? ''),
                'conditionalBranches' => self::resolveCabalStanzaConditionals($key, $stanzas),
                'defaultLanguage' => self::firstFieldValue($fields['default-language'] ?? null),
                'mixins' => self::extractCabalMixinSpecs($fields['mixins'] ?? ''),
                'buildToolDepends' => self::extractCabalBuildToolDepends($fields['build-tool-depends'] ?? ''),
                'buildTools' => self::extractCabalBuildTools($fields['build-tools'] ?? ''),
                'benchmarkOptions' => self::splitWords($fields['benchmark-options'] ?? ''),
                'defaultExtensions' => self::extractCabalDefaultExtensions(
                    self::joinCabalFieldValues($fields['default-extensions'] ?? '', $fields['extensions'] ?? '')
                ),
                'otherExtensions' => self::extractCabalDefaultExtensions($fields['other-extensions'] ?? ''),
                'nativeSystemFields' => self::extractCabalNativeSystemFields($fields),
            ];
        }

        ksort($benchmarks);
        return $benchmarks;
    }

    /**
     * @return array<string, array{buildable:bool|null, mainIs:string|null, commonImports:list<string>, unresolvedCommonImports:list<string>, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, cppOptions:list<string>, autogenModules:list<string>, reexportedModules:list<string>, moduleInterfaceFields:array<string, list<string>>, otherModules:list<string>, extraSourceFiles:list<string>, extraDocFiles:list<string>, extraTmpFiles:list<string>, dataFiles:list<string>, conditionalBranches:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, defaultExtensions:list<string>, otherExtensions:list<string>, nativeSystemFields:array<string, list<string>>}>
     */
    public static function parseCabalExecutables(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $executables = [];

        foreach ($stanzas as $key => $stanza) {
            if ($stanza['type'] !== 'executable') {
                continue;
            }

            $fields = self::resolveCabalStanzaFields($key, $stanzas);
            $commonImportClosure = self::resolveCabalCommonImportClosure($key, $stanzas);
            $executables[$stanza['name']] = [
                'buildable' => self::cabalBuildableState($fields['buildable'] ?? null),
                'mainIs' => self::firstFieldValue($fields['main-is'] ?? null),
                'commonImports' => $commonImportClosure['imports'],
                'unresolvedCommonImports' => $commonImportClosure['unresolved'],
                'sourceDirectories' => self::splitWords($fields['hs-source-dirs'] ?? ''),
                'buildDepends' => self::extractCabalDependencyNames($fields['build-depends'] ?? ''),
                'dependencyConstraints' => self::extractCabalDependencyConstraints($fields['build-depends'] ?? ''),
                'ghcOptions' => self::splitWords($fields['ghc-options'] ?? ''),
                'cppOptions' => self::splitWords($fields['cpp-options'] ?? ''),
                'autogenModules' => self::extractCabalModuleNames($fields['autogen-modules'] ?? ''),
                'reexportedModules' => self::extractCabalReexportedModuleSpecs($fields['reexported-modules'] ?? ''),
                'moduleInterfaceFields' => self::extractCabalModuleInterfaceFields($fields),
                'otherModules' => self::extractCabalModuleNames($fields['other-modules'] ?? ''),
                'extraSourceFiles' => self::extractCabalFileGlobs($fields['extra-source-files'] ?? ''),
                'extraDocFiles' => self::extractCabalFileGlobs($fields['extra-doc-files'] ?? ''),
                'extraTmpFiles' => self::extractCabalFileGlobs($fields['extra-tmp-files'] ?? ''),
                'dataFiles' => self::extractCabalFileGlobs($fields['data-files'] ?? ''),
                'conditionalBranches' => self::resolveCabalStanzaConditionals($key, $stanzas),
                'defaultLanguage' => self::firstFieldValue($fields['default-language'] ?? null),
                'mixins' => self::extractCabalMixinSpecs($fields['mixins'] ?? ''),
                'buildToolDepends' => self::extractCabalBuildToolDepends($fields['build-tool-depends'] ?? ''),
                'buildTools' => self::extractCabalBuildTools($fields['build-tools'] ?? ''),
                'defaultExtensions' => self::extractCabalDefaultExtensions(
                    self::joinCabalFieldValues($fields['default-extensions'] ?? '', $fields['extensions'] ?? '')
                ),
                'otherExtensions' => self::extractCabalDefaultExtensions($fields['other-extensions'] ?? ''),
                'nativeSystemFields' => self::extractCabalNativeSystemFields($fields),
            ];
        }

        ksort($executables);
        return $executables;
    }

    /**
     * @return array<string, array{buildDepends:list<string>, dependencyConstraints:array<string, string>, sourceDirectories:list<string>, exposedModules:list<string>, otherModules:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, autogenModules:list<string>, reexportedModules:list<string>, moduleInterfaceFields:array<string, list<string>>, defaultExtensions:list<string>, otherExtensions:list<string>, extraSourceFiles:list<string>, extraDocFiles:list<string>, extraTmpFiles:list<string>, dataFiles:list<string>, conditionalBranches:list<string>, nativeSystemFields:array<string, list<string>>}>
     */
    public static function parseCabalLibraries(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $libraries = [];

        foreach ($stanzas as $key => $stanza) {
            if ($stanza['type'] !== 'library') {
                continue;
            }

            $fields = self::resolveCabalStanzaFields($key, $stanzas);
            $libraries[$stanza['name']] = [
                'buildDepends' => self::extractCabalDependencyNames($fields['build-depends'] ?? ''),
                'dependencyConstraints' => self::extractCabalDependencyConstraints($fields['build-depends'] ?? ''),
                'sourceDirectories' => self::splitWords($fields['hs-source-dirs'] ?? ''),
                'exposedModules' => self::extractCabalModuleNames($fields['exposed-modules'] ?? ''),
                'otherModules' => self::extractCabalModuleNames($fields['other-modules'] ?? ''),
                'defaultLanguage' => self::firstFieldValue($fields['default-language'] ?? null),
                'mixins' => self::extractCabalMixinSpecs($fields['mixins'] ?? ''),
                'buildToolDepends' => self::extractCabalBuildToolDepends($fields['build-tool-depends'] ?? ''),
                'buildTools' => self::extractCabalBuildTools($fields['build-tools'] ?? ''),
                'autogenModules' => self::extractCabalModuleNames($fields['autogen-modules'] ?? ''),
                'reexportedModules' => self::extractCabalReexportedModuleSpecs($fields['reexported-modules'] ?? ''),
                'moduleInterfaceFields' => self::extractCabalModuleInterfaceFields($fields),
                'defaultExtensions' => self::extractCabalDefaultExtensions(
                    self::joinCabalFieldValues($fields['default-extensions'] ?? '', $fields['extensions'] ?? '')
                ),
                'otherExtensions' => self::extractCabalDefaultExtensions($fields['other-extensions'] ?? ''),
                'extraSourceFiles' => self::extractCabalFileGlobs($fields['extra-source-files'] ?? ''),
                'extraDocFiles' => self::extractCabalFileGlobs($fields['extra-doc-files'] ?? ''),
                'extraTmpFiles' => self::extractCabalFileGlobs($fields['extra-tmp-files'] ?? ''),
                'dataFiles' => self::extractCabalFileGlobs($fields['data-files'] ?? ''),
                'conditionalBranches' => self::resolveCabalStanzaConditionals($key, $stanzas),
                'nativeSystemFields' => self::extractCabalNativeSystemFields($fields),
            ];
        }

        ksort($libraries);
        return $libraries;
    }

    /**
     * @param array<string, string|array{available?: bool, version?: string|null}> $tools
     * @return array<string, array{available:bool, version:string|null}>
     */
    private static function normalizeTools(array $tools): array
    {
        $normalized = [];
        foreach (array_unique(array_merge(self::REQUIRED_TOOLS, array_keys($tools))) as $tool) {
            $value = $tools[$tool] ?? ['available' => false, 'version' => null];
            if (is_array($value)) {
                $normalized[$tool] = [
                    'available' => (bool) ($value['available'] ?? false),
                    'version' => isset($value['version']) && is_string($value['version']) ? $value['version'] : null,
                ];
            } else {
                $normalized[$tool] = [
                    'available' => $value !== '',
                    'version' => $value === '' ? null : $value,
                ];
            }
        }

        ksort($normalized);
        return $normalized;
    }

    /**
     * @return array{expected:array<string, string>, present:array<string, string>, missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>}
     */
    private static function auditProjectPins(?string $contents): array
    {
        $present = $contents === null ? [] : self::parseCabalProjectPins($contents);
        $missing = [];
        $mismatched = [];

        foreach (self::PROJECT_SOURCE_REPOSITORY_PINS as $name => $expectedTag) {
            if (!array_key_exists($name, $present)) {
                $missing[] = $name;
                continue;
            }

            if ($present[$name] !== $expectedTag) {
                $mismatched[$name] = [
                    'expected' => $expectedTag,
                    'actual' => $present[$name],
                ];
            }
        }

        return [
            'expected' => self::PROJECT_SOURCE_REPOSITORY_PINS,
            'present' => $present,
            'missing' => $missing,
            'mismatched' => $mismatched,
        ];
    }

    /**
     * @return array{expected:array<string, array{type:string, location:string}>, present:array<string, array{type:string|null, location:string, tag:string|null, fields:array<string, string>}>, missing:list<string>, mismatched:array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string}>>, unexpected:list<string>, unexpectedFields:array<string, list<string>>}
     */
    private static function auditProjectSourceRepositoryClosure(?string $contents): array
    {
        $present = $contents === null ? [] : self::parseCabalProjectSourceRepositories($contents);
        $missing = [];
        $mismatched = [];
        $unexpected = [];
        $unexpectedFields = [];

        foreach (self::PROJECT_SOURCE_REPOSITORIES as $name => $expected) {
            if (!array_key_exists($name, $present)) {
                $missing[] = $name;
                continue;
            }

            $actual = [
                'type' => $present[$name]['type'],
                'location' => $present[$name]['location'],
            ];
            if ($actual['type'] !== $expected['type'] || $actual['location'] !== $expected['location']) {
                $mismatched[$name] = [
                    'expected' => $expected,
                    'actual' => $actual,
                ];
            }

            $extraFields = [];
            foreach ($present[$name]['fields'] as $field => $value) {
                if (in_array($field, ['type', 'location', 'tag'], true)) {
                    continue;
                }
                $extraFields[] = $value === '' ? $field : $field . ': ' . $value;
            }
            if ($extraFields !== []) {
                sort($extraFields);
                $unexpectedFields[$name] = $extraFields;
            }
        }

        foreach (array_keys($present) as $name) {
            if (!array_key_exists($name, self::PROJECT_SOURCE_REPOSITORIES)) {
                $unexpected[] = $name;
            }
        }

        return [
            'expected' => self::PROJECT_SOURCE_REPOSITORIES,
            'present' => $present,
            'missing' => $missing,
            'mismatched' => $mismatched,
            'unexpected' => $unexpected,
            'unexpectedFields' => $unexpectedFields,
        ];
    }

    /**
     * @return array{expectedPackages:list<string>, presentPackages:list<string>, missingPackages:list<string>, unexpectedPackages:list<string>, expectedFlags:array<string, array<string, bool>>, presentFlags:array<string, array<string, bool>>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>, unexpectedFlags:array<string, list<string>>, expectedPackageFields:array<string, list<string>>, presentPackageFields:array<string, array<string, string>>, unexpectedPackageFields:array<string, list<string>>}
     */
    private static function auditProjectPackageClosure(?string $contents): array
    {
        $presentPackages = $contents === null ? [] : self::parseCabalProjectPackages($contents);
        $presentFlags = $contents === null ? [] : self::parseCabalProjectFlags($contents);
        $presentPackageFields = $contents === null ? [] : self::parseCabalProjectPackageFields($contents);
        $missingPackages = [];
        $unexpectedPackages = [];
        $missingFlags = [];
        $mismatchedFlags = [];
        $unexpectedFlags = [];
        $unexpectedPackageFields = [];

        foreach (self::PROJECT_PACKAGES as $package) {
            if (!in_array($package, $presentPackages, true)) {
                $missingPackages[] = $package;
            }
        }

        foreach ($presentPackages as $package) {
            if (!in_array($package, self::PROJECT_PACKAGES, true)) {
                $unexpectedPackages[] = $package;
            }
        }

        foreach (self::PROJECT_FLAGS as $package => $expectedFlags) {
            foreach ($expectedFlags as $flag => $expectedValue) {
                if (!array_key_exists($package, $presentFlags) || !array_key_exists($flag, $presentFlags[$package])) {
                    $missingFlags[$package][] = $flag;
                    continue;
                }

                if ($presentFlags[$package][$flag] !== $expectedValue) {
                    $mismatchedFlags[$package][$flag] = [
                        'expected' => $expectedValue,
                        'actual' => $presentFlags[$package][$flag],
                    ];
                }
            }
        }

        foreach ($presentFlags as $package => $flags) {
            $expectedFlags = self::PROJECT_FLAGS[$package] ?? [];
            foreach (array_keys($flags) as $flag) {
                if (!array_key_exists($flag, $expectedFlags)) {
                    $unexpectedFlags[$package][] = $flag;
                }
            }
        }

        foreach ($presentPackageFields as $package => $fields) {
            $expectedFields = self::PROJECT_PACKAGE_EXPECTED_FIELDS[$package] ?? [];
            foreach ($fields as $field => $value) {
                if (!in_array($field, $expectedFields, true)) {
                    $unexpectedPackageFields[$package][] = $value === '' ? $field : $field . ': ' . $value;
                }
            }
        }

        return [
            'expectedPackages' => self::PROJECT_PACKAGES,
            'presentPackages' => $presentPackages,
            'missingPackages' => $missingPackages,
            'unexpectedPackages' => $unexpectedPackages,
            'expectedFlags' => self::PROJECT_FLAGS,
            'presentFlags' => $presentFlags,
            'missingFlags' => $missingFlags,
            'mismatchedFlags' => $mismatchedFlags,
            'unexpectedFlags' => $unexpectedFlags,
            'expectedPackageFields' => self::PROJECT_PACKAGE_EXPECTED_FIELDS,
            'presentPackageFields' => $presentPackageFields,
            'unexpectedPackageFields' => $unexpectedPackageFields,
        ];
    }

    /**
     * @return array{expectedConstraints:array<string, string>, presentConstraints:array<string, string>, missingConstraints:list<string>, mismatchedConstraints:array<string, array{expected:string, actual:string}>, unexpectedConstraints:list<string>}
     */
    private static function auditProjectConstraintClosure(?string $contents): array
    {
        $present = $contents === null ? [] : self::parseCabalProjectConstraints($contents);
        $missing = [];
        $mismatched = [];
        $unexpected = [];

        foreach (self::PROJECT_CONSTRAINTS as $name => $expectedConstraint) {
            if (!array_key_exists($name, $present)) {
                $missing[] = $name;
                continue;
            }

            if ($present[$name] !== $expectedConstraint) {
                $mismatched[$name] = [
                    'expected' => $expectedConstraint,
                    'actual' => $present[$name],
                ];
            }
        }

        foreach (array_keys($present) as $name) {
            if (!array_key_exists($name, self::PROJECT_CONSTRAINTS)) {
                $unexpected[] = $name;
            }
        }

        return [
            'expectedConstraints' => self::PROJECT_CONSTRAINTS,
            'presentConstraints' => $present,
            'missingConstraints' => $missing,
            'mismatchedConstraints' => $mismatched,
            'unexpectedConstraints' => $unexpected,
        ];
    }

    /**
     * @return array{expectedFields:list<string>, presentFields:list<string>, present:array<string, string>, unexpectedFields:list<string>}
     */
    private static function auditProjectUnconditionalFieldClosure(?string $contents): array
    {
        $present = $contents === null ? [] : self::parseCabalProjectUnconditionalFields($contents);
        $unexpected = [];

        foreach (array_keys($present) as $field) {
            if (!in_array($field, self::PROJECT_EXPECTED_UNCONDITIONAL_FIELDS, true)) {
                $unexpected[] = $field;
            }
        }

        sort($unexpected);

        return [
            'expectedFields' => self::PROJECT_EXPECTED_UNCONDITIONAL_FIELDS,
            'presentFields' => array_keys($present),
            'present' => $present,
            'unexpectedFields' => $unexpected,
        ];
    }

    /**
     * @return array{expectedBranches:list<string>, presentBranches:list<string>, missingBranches:list<string>, unexpectedBranches:list<string>}
     */
    private static function auditProjectConditionalBranchClosure(?string $contents): array
    {
        $present = $contents === null ? [] : self::parseCabalProjectConditionalBranches($contents);
        $missing = [];
        $unexpected = [];

        foreach (self::PROJECT_EXPECTED_CONDITIONAL_BRANCHES as $branch) {
            if (!in_array($branch, $present, true)) {
                $missing[] = $branch;
            }
        }

        foreach ($present as $branch) {
            if (!in_array($branch, self::PROJECT_EXPECTED_CONDITIONAL_BRANCHES, true)) {
                $unexpected[] = $branch;
            }
        }

        return [
            'expectedBranches' => self::PROJECT_EXPECTED_CONDITIONAL_BRANCHES,
            'presentBranches' => $present,
            'missingBranches' => $missing,
            'unexpectedBranches' => $unexpected,
        ];
    }

    /**
     * @param array<string, array{available:bool, version:string|null}> $tools
     * @return array{packageFile:string, expectedGhcVersions:list<string>, presentGhcVersions:list<string>, missingGhcVersions:list<string>, toolGhcVersion:string|null, toolGhcVersionSupported:bool}
     */
    private static function auditCompilerTestedWithClosure(string $root, array $tools): array
    {
        $packageFile = 'pandoc.cabal';
        $path = $root . DIRECTORY_SEPARATOR . $packageFile;
        $present = is_file($path) ? self::parseCabalTestedWithGhcVersions((string) file_get_contents($path)) : [];
        $missing = [];

        foreach (self::TESTED_GHC_VERSIONS as $version) {
            if (!in_array($version, $present, true)) {
                $missing[] = $version;
            }
        }

        $toolVersion = self::normalizeGhcToolVersion($tools['ghc']['version'] ?? null);

        return [
            'packageFile' => $packageFile,
            'expectedGhcVersions' => self::TESTED_GHC_VERSIONS,
            'presentGhcVersions' => $present,
            'missingGhcVersions' => $missing,
            'toolGhcVersion' => $toolVersion,
            'toolGhcVersionSupported' => $toolVersion !== null && in_array($toolVersion, self::TESTED_GHC_VERSIONS, true),
        ];
    }

    /**
     * @return array{expected:array<string, array{name:string, version:string, cabalVersion:string, buildType:string}>, present:array<string, array{name:string|null, version:string|null, cabalVersion:string|null, buildType:string|null}>, missingHeaders:array<string, list<string>>, mismatchedHeaders:array<string, array<string, array{expected:string, actual:string|null}>>}
     */
    private static function auditPackageIdentityClosure(string $root): array
    {
        $present = [];
        $missingHeaders = [];
        $mismatchedHeaders = [];
        $fieldNames = ['name', 'version', 'cabalVersion', 'buildType'];

        foreach (self::PACKAGE_IDENTITIES as $packageFile => $expected) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            if (!is_file($path)) {
                $missingHeaders[$packageFile] = $fieldNames;
                continue;
            }

            $actual = self::parseCabalPackageHeader((string) file_get_contents($path));
            $present[$packageFile] = $actual;

            foreach ($fieldNames as $field) {
                if (($actual[$field] ?? null) === null || $actual[$field] === '') {
                    $missingHeaders[$packageFile][] = $field;
                    continue;
                }

                if ($actual[$field] !== $expected[$field]) {
                    $mismatchedHeaders[$packageFile][$field] = [
                        'expected' => $expected[$field],
                        'actual' => $actual[$field],
                    ];
                }
            }
        }

        return [
            'expected' => self::PACKAGE_IDENTITIES,
            'present' => $present,
            'missingHeaders' => $missingHeaders,
            'mismatchedHeaders' => $mismatchedHeaders,
        ];
    }

    /**
     * @return array{expectedSetupDependencies:array<string, list<string>>, present:array<string, array{customSetup:bool, setupDepends:list<string>, dependencyConstraints:array<string, string>}>, unexpectedCustomSetupStanzas:array<string, list<string>>, unexpectedSetupDependencies:array<string, list<string>>}
     */
    private static function auditPackageSetupClosure(string $root): array
    {
        $present = [];
        $unexpectedCustomSetupStanzas = [];
        $unexpectedSetupDependencies = [];

        foreach (self::PACKAGE_EXPECTED_SETUP_DEPENDENCIES as $packageFile => $expectedDependencies) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            if (!is_file($path)) {
                continue;
            }

            $setups = self::parseCabalCustomSetups((string) file_get_contents($path));
            $setupDepends = [];
            $dependencyConstraints = [];

            foreach ($setups as $setupName => $setup) {
                $unexpectedCustomSetupStanzas[$packageFile][] = $setupName === 'default' ? 'custom-setup' : 'custom-setup:' . $setupName;
                foreach ($setup['setupDepends'] as $dependency) {
                    if (!in_array($dependency, $setupDepends, true)) {
                        $setupDepends[] = $dependency;
                    }
                }
                $dependencyConstraints = array_merge($dependencyConstraints, $setup['dependencyConstraints']);
            }

            sort($setupDepends);
            ksort($dependencyConstraints);
            $present[$packageFile] = [
                'customSetup' => $setups !== [],
                'setupDepends' => $setupDepends,
                'dependencyConstraints' => $dependencyConstraints,
            ];

            foreach ($setupDepends as $dependency) {
                if (!in_array($dependency, $expectedDependencies, true)) {
                    $unexpectedSetupDependencies[$packageFile][] = self::formatCabalSetupDependency($dependency, $dependencyConstraints[$dependency] ?? '');
                }
            }
        }

        return [
            'expectedSetupDependencies' => self::PACKAGE_EXPECTED_SETUP_DEPENDENCIES,
            'present' => $present,
            'unexpectedCustomSetupStanzas' => $unexpectedCustomSetupStanzas,
            'unexpectedSetupDependencies' => $unexpectedSetupDependencies,
        ];
    }

    /**
     * @return array{expectedFlags:array<string, list<string>>, presentFlags:array<string, list<string>>, missingFlags:array<string, list<string>>, unexpectedFlags:array<string, list<string>>, expectedFlagFields:array<string, array<string, array{default:string|null, manual:string|null}>>, presentFlagFields:array<string, array<string, array{default:string|null, manual:string|null}>>, mismatchedFlagFields:array<string, array<string, array<string, array{expected:string|null, actual:string|null}>>>}
     */
    private static function auditPackageFlagDefinitionClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $unexpected = [];
        $presentFlagFields = [];
        $mismatchedFlagFields = [];

        foreach (self::PACKAGE_EXPECTED_FLAG_DEFINITIONS as $packageFile => $expectedFlags) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            $actualFlags = is_file($path) ? self::parseCabalPackageFlags((string) file_get_contents($path)) : [];
            $actualFlagFields = is_file($path) ? self::parseCabalPackageFlagFields((string) file_get_contents($path)) : [];
            $present[$packageFile] = $actualFlags;
            $presentFlagFields[$packageFile] = $actualFlagFields;

            foreach ($expectedFlags as $flag) {
                if (!in_array($flag, $actualFlags, true)) {
                    $missing[$packageFile][] = $flag;
                }
            }

            foreach ($actualFlags as $flag) {
                if (!in_array($flag, $expectedFlags, true)) {
                    $unexpected[$packageFile][] = $flag;
                }
            }

            foreach (self::PACKAGE_EXPECTED_FLAG_FIELDS[$packageFile] ?? [] as $flag => $expectedFields) {
                if (!array_key_exists($flag, $actualFlagFields)) {
                    continue;
                }

                foreach ($expectedFields as $field => $expectedValue) {
                    $actualValue = $actualFlagFields[$flag][$field] ?? null;
                    if ($actualValue !== $expectedValue) {
                        $mismatchedFlagFields[$packageFile][$flag][$field] = [
                            'expected' => $expectedValue,
                            'actual' => $actualValue,
                        ];
                    }
                }
            }
        }

        return [
            'expectedFlags' => self::PACKAGE_EXPECTED_FLAG_DEFINITIONS,
            'presentFlags' => $present,
            'missingFlags' => $missing,
            'unexpectedFlags' => $unexpected,
            'expectedFlagFields' => self::PACKAGE_EXPECTED_FLAG_FIELDS,
            'presentFlagFields' => $presentFlagFields,
            'mismatchedFlagFields' => $mismatchedFlagFields,
        ];
    }

    /**
     * @return array{expectedDataFiles:array<string, list<string>>, presentDataFiles:array<string, list<string>>, missingDataFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>}
     */
    private static function auditPackageDataFileClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $unexpected = [];

        foreach (self::PACKAGE_EXPECTED_DATA_FILES as $packageFile => $expectedDataFiles) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            $actualDataFiles = is_file($path) ? self::parseCabalPackageDataFiles((string) file_get_contents($path)) : [];
            $present[$packageFile] = $actualDataFiles;

            foreach ($expectedDataFiles as $dataFile) {
                if (!in_array($dataFile, $actualDataFiles, true)) {
                    $missing[$packageFile][] = $dataFile;
                }
            }

            foreach ($actualDataFiles as $dataFile) {
                if (!in_array($dataFile, $expectedDataFiles, true)) {
                    $unexpected[$packageFile][] = $dataFile;
                }
            }
        }

        return [
            'expectedDataFiles' => self::PACKAGE_EXPECTED_DATA_FILES,
            'presentDataFiles' => $present,
            'missingDataFiles' => $missing,
            'unexpectedDataFiles' => $unexpected,
        ];
    }

    /**
     * @return array{expectedExtraDocFiles:array<string, list<string>>, presentExtraDocFiles:array<string, list<string>>, missingExtraDocFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, expectedExtraSourceFiles:array<string, list<string>>, presentExtraSourceFiles:array<string, list<string>>, missingExtraSourceFiles:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, expectedExtraTmpFiles:array<string, list<string>>, presentExtraTmpFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>}
     */
    private static function auditPackageExtraFileClosure(string $root): array
    {
        $expectedDocFiles = self::expectedPackageExtraDocFiles();
        $expectedSourceFiles = self::expectedPackageExtraSourceFiles();
        $expectedTmpFiles = self::expectedPackageExtraTmpFiles();
        $presentDocFiles = [];
        $presentSourceFiles = [];
        $presentTmpFiles = [];
        $missingDocFiles = [];
        $missingSourceFiles = [];
        $unexpectedDocFiles = [];
        $unexpectedSourceFiles = [];
        $unexpectedTmpFiles = [];

        foreach ($expectedDocFiles as $packageFile => $expectedFiles) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            $actualFiles = is_file($path) ? self::parseCabalPackageExtraDocFiles((string) file_get_contents($path)) : [];
            $presentDocFiles[$packageFile] = $actualFiles;

            foreach ($expectedFiles as $expectedFile) {
                if (!in_array($expectedFile, $actualFiles, true)) {
                    $missingDocFiles[$packageFile][] = $expectedFile;
                }
            }

            foreach ($actualFiles as $actualFile) {
                if (!in_array($actualFile, $expectedFiles, true)) {
                    $unexpectedDocFiles[$packageFile][] = $actualFile;
                }
            }
        }

        foreach ($expectedSourceFiles as $packageFile => $expectedFiles) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            $actualFiles = is_file($path) ? self::parseCabalPackageExtraSourceFiles((string) file_get_contents($path)) : [];
            $presentSourceFiles[$packageFile] = $actualFiles;

            foreach ($expectedFiles as $expectedFile) {
                if (!in_array($expectedFile, $actualFiles, true)) {
                    $missingSourceFiles[$packageFile][] = $expectedFile;
                }
            }

            foreach ($actualFiles as $actualFile) {
                if (!in_array($actualFile, $expectedFiles, true)) {
                    $unexpectedSourceFiles[$packageFile][] = $actualFile;
                }
            }
        }

        foreach ($expectedTmpFiles as $packageFile => $expectedFiles) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            $actualFiles = is_file($path) ? self::parseCabalPackageExtraTmpFiles((string) file_get_contents($path)) : [];
            $presentTmpFiles[$packageFile] = $actualFiles;

            foreach ($actualFiles as $actualFile) {
                if (!in_array($actualFile, $expectedFiles, true)) {
                    $unexpectedTmpFiles[$packageFile][] = $actualFile;
                }
            }
        }

        return [
            'expectedExtraDocFiles' => $expectedDocFiles,
            'presentExtraDocFiles' => $presentDocFiles,
            'missingExtraDocFiles' => $missingDocFiles,
            'unexpectedExtraDocFiles' => $unexpectedDocFiles,
            'expectedExtraSourceFiles' => $expectedSourceFiles,
            'presentExtraSourceFiles' => $presentSourceFiles,
            'missingExtraSourceFiles' => $missingSourceFiles,
            'unexpectedExtraSourceFiles' => $unexpectedSourceFiles,
            'expectedExtraTmpFiles' => $expectedTmpFiles,
            'presentExtraTmpFiles' => $presentTmpFiles,
            'unexpectedExtraTmpFiles' => $unexpectedTmpFiles,
        ];
    }

    /**
     * @return array{expectedNativeSystemFields:array<string, array<string, list<string>>>, presentNativeSystemFields:array<string, array<string, list<string>>>, unexpectedNativeSystemFields:array<string, list<string>>}
     */
    private static function auditPackageNativeSystemFieldClosure(string $root): array
    {
        $present = [];
        $unexpected = [];

        foreach (self::PACKAGE_EXPECTED_NATIVE_SYSTEM_FIELDS as $packageFile => $expectedFields) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            $actualFields = is_file($path) ? self::parseCabalPackageNativeSystemFields((string) file_get_contents($path)) : [];
            $present[$packageFile] = $actualFields;

            $unexpectedFields = self::unexpectedCabalNativeSystemFields($actualFields, $expectedFields);
            if ($unexpectedFields !== []) {
                $unexpected[$packageFile] = $unexpectedFields;
            }
        }

        return [
            'expectedNativeSystemFields' => self::PACKAGE_EXPECTED_NATIVE_SYSTEM_FIELDS,
            'presentNativeSystemFields' => $present,
            'unexpectedNativeSystemFields' => $unexpected,
        ];
    }

    /**
     * @return array{expected:array<string, array<string, array{type:string, location:string}>>, present:array<string, array<string, array{type:string|null, location:string|null, fields:array<string, string>}>>, missing:array<string, list<string>>, mismatched:array<string, array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string|null}>>>, unexpected:array<string, list<string>>, unexpectedFields:array<string, array<string, list<string>>>}
     */
    private static function auditPackageSourceRepositoryClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $mismatched = [];
        $unexpected = [];
        $unexpectedFields = [];

        foreach (self::PACKAGE_EXPECTED_SOURCE_REPOSITORIES as $packageFile => $expectedRepositories) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
            $actualRepositories = is_file($path) ? self::parseCabalPackageSourceRepositories((string) file_get_contents($path)) : [];
            $present[$packageFile] = $actualRepositories;

            foreach ($expectedRepositories as $name => $expected) {
                if (!array_key_exists($name, $actualRepositories)) {
                    $missing[$packageFile][] = $name;
                    continue;
                }

                $actual = [
                    'type' => $actualRepositories[$name]['type'],
                    'location' => $actualRepositories[$name]['location'],
                ];
                if ($actual['type'] !== $expected['type'] || $actual['location'] !== $expected['location']) {
                    $mismatched[$packageFile][$name] = [
                        'expected' => $expected,
                        'actual' => $actual,
                    ];
                }

                $extraFields = [];
                foreach ($actualRepositories[$name]['fields'] as $field => $value) {
                    if (in_array($field, ['type', 'location'], true)) {
                        continue;
                    }
                    $extraFields[] = $value === '' ? $field : $field . ': ' . $value;
                }
                if ($extraFields !== []) {
                    sort($extraFields);
                    $unexpectedFields[$packageFile][$name] = $extraFields;
                }
            }

            foreach (array_keys($actualRepositories) as $name) {
                if (!array_key_exists($name, $expectedRepositories)) {
                    $unexpected[$packageFile][] = $name;
                }
            }
        }

        return [
            'expected' => self::PACKAGE_EXPECTED_SOURCE_REPOSITORIES,
            'present' => $present,
            'missing' => $missing,
            'mismatched' => $mismatched,
            'unexpected' => $unexpected,
            'unexpectedFields' => $unexpectedFields,
        ];
    }

    /**
     * @return array{expectedDependencies:array<string, list<string>>, expectedDependencyConstraints:array<string, array<string, string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedSourceDirectories:array<string, list<string>>, expectedMixins:array<string, list<string>>, expectedBuildTools:array<string, list<string>>, expectedTestOptions:array<string, list<string>>, expectedDefaultExtensions:array<string, list<string>>, expectedOtherExtensions:array<string, list<string>>, expectedCppOptions:array<string, list<string>>, expectedAutogenModules:array<string, list<string>>, expectedReexportedModules:array<string, list<string>>, expectedExtraSourceFiles:array<string, list<string>>, expectedExtraDocFiles:array<string, list<string>>, expectedExtraTmpFiles:array<string, list<string>>, expectedDataFiles:array<string, list<string>>, expectedConditionalBranches:array<string, list<string>>, expectedNativeSystemFields:array<string, array<string, list<string>>>, expectedOtherModules:array<string, list<string>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, cppOptions:list<string>, autogenModules:list<string>, reexportedModules:list<string>, extraSourceFiles:list<string>, extraDocFiles:list<string>, extraTmpFiles:list<string>, dataFiles:list<string>, conditionalBranches:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, testOptions:list<string>, defaultExtensions:list<string>, otherExtensions:list<string>, otherModules:list<string>, nativeSystemFields:array<string, list<string>>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, unexpectedDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, unexpectedExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedSourceDirectories:array<string, list<string>>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, unexpectedTestOptions:array<string, list<string>>, unexpectedDefaultExtensions:array<string, list<string>>, unexpectedOtherExtensions:array<string, list<string>>, unexpectedCppOptions:array<string, list<string>>, unexpectedAutogenModules:array<string, list<string>>, unexpectedReexportedModules:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>, unexpectedConditionalBranches:array<string, list<string>>, unexpectedNativeSystemFields:array<string, list<string>>, missingOtherModules:array<string, list<string>>, unexpectedOtherModules:array<string, list<string>>}
     */
    private static function auditRunnerDependencyClosure(string $root): array
    {
        $present = [];
        foreach (self::RUNNER_ENTRY_POINTS as $target => $entryPoint) {
            $packageFile = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryPoint['packageFile']);
            if (!is_file($packageFile)) {
                continue;
            }

            $suiteName = substr($target, strlen('test:'));
            $suites = self::parseCabalTestSuites((string) file_get_contents($packageFile));
            if (!array_key_exists($suiteName, $suites)) {
                continue;
            }

            $present[$target] = [
                'packageFile' => $entryPoint['packageFile'],
                'type' => $suites[$suiteName]['type'],
                'buildable' => $suites[$suiteName]['buildable'],
                'manual' => $suites[$suiteName]['manual'],
                'mainIs' => $suites[$suiteName]['mainIs'],
                'commonImports' => $suites[$suiteName]['commonImports'],
                'unresolvedCommonImports' => $suites[$suiteName]['unresolvedCommonImports'],
                'sourceDirectories' => $suites[$suiteName]['sourceDirectories'],
                'buildDepends' => $suites[$suiteName]['buildDepends'],
                'dependencyConstraints' => $suites[$suiteName]['dependencyConstraints'],
                'ghcOptions' => $suites[$suiteName]['ghcOptions'],
                'cppOptions' => $suites[$suiteName]['cppOptions'],
                'autogenModules' => $suites[$suiteName]['autogenModules'],
                'reexportedModules' => $suites[$suiteName]['reexportedModules'],
                'moduleInterfaceFields' => $suites[$suiteName]['moduleInterfaceFields'],
                'extraSourceFiles' => $suites[$suiteName]['extraSourceFiles'],
                'extraDocFiles' => $suites[$suiteName]['extraDocFiles'],
                'extraTmpFiles' => $suites[$suiteName]['extraTmpFiles'],
                'dataFiles' => $suites[$suiteName]['dataFiles'],
                'conditionalBranches' => $suites[$suiteName]['conditionalBranches'],
                'defaultLanguage' => $suites[$suiteName]['defaultLanguage'],
                'mixins' => $suites[$suiteName]['mixins'],
                'buildToolDepends' => $suites[$suiteName]['buildToolDepends'],
                'buildTools' => $suites[$suiteName]['buildTools'],
                'testOptions' => $suites[$suiteName]['testOptions'],
                'defaultExtensions' => $suites[$suiteName]['defaultExtensions'],
                'otherExtensions' => $suites[$suiteName]['otherExtensions'],
                'otherModules' => $suites[$suiteName]['otherModules'],
                'nativeSystemFields' => $suites[$suiteName]['nativeSystemFields'],
            ];
        }

        $missingTargets = [];
        $mismatchedEntryPoints = [];
        $missingDependencies = [];
        $unexpectedDependencies = [];
        $mismatchedDependencyConstraints = [];
        $missingExecutableOptions = [];
        $unexpectedExecutableOptions = [];
        $mismatchedDefaultLanguages = [];
        $mismatchedManualFields = [];
        $missingCommonImports = [];
        $unexpectedCommonImports = [];
        $unresolvedCommonImports = [];
        $unexpectedSourceDirectories = [];
        $unexpectedMixins = [];
        $unexpectedBuildTools = [];
        $unexpectedTestOptions = [];
        $unexpectedDefaultExtensions = [];
        $unexpectedOtherExtensions = [];
        $unexpectedCppOptions = [];
        $unexpectedAutogenModules = [];
        $unexpectedReexportedModules = [];
        $unexpectedModuleInterfaceFields = [];
        $unexpectedExtraSourceFiles = [];
        $unexpectedExtraDocFiles = [];
        $unexpectedExtraTmpFiles = [];
        $unexpectedDataFiles = [];
        $unexpectedConditionalBranches = [];
        $unexpectedNativeSystemFields = [];
        $missingOtherModules = [];
        $unexpectedOtherModules = [];

        foreach (self::RUNNER_ENTRY_POINTS as $target => $entryPoint) {
            if (!array_key_exists($target, $present)) {
                $missingTargets[] = $target;
                continue;
            }

            if ($present[$target]['type'] !== $entryPoint['type']) {
                $mismatchedEntryPoints[$target][] = 'type expected ' . $entryPoint['type'] . ', found ' . ($present[$target]['type'] ?? 'none');
            }

            if ($present[$target]['buildable'] !== true) {
                $mismatchedEntryPoints[$target][] = 'buildable expected true, found ' . self::formatCabalBuildableState($present[$target]['buildable']);
            }

            if ($present[$target]['mainIs'] !== $entryPoint['mainIs']) {
                $mismatchedEntryPoints[$target][] = 'main-is expected ' . $entryPoint['mainIs'] . ', found ' . ($present[$target]['mainIs'] ?? 'none');
            }

            if (!in_array($entryPoint['sourceDirectory'], $present[$target]['sourceDirectories'], true)) {
                $mismatchedEntryPoints[$target][] = 'hs-source-dirs missing ' . $entryPoint['sourceDirectory'];
            }

            $expectedSourceDirectories = [$entryPoint['sourceDirectory']];
            foreach ($present[$target]['sourceDirectories'] as $sourceDirectory) {
                if (!in_array($sourceDirectory, $expectedSourceDirectories, true)) {
                    $unexpectedSourceDirectories[$target][] = $sourceDirectory;
                }
            }

            foreach (self::RUNNER_DIRECT_DEPENDENCIES[$target] as $dependency) {
                if (!in_array($dependency, $present[$target]['buildDepends'], true)) {
                    $missingDependencies[$target][] = $dependency;
                }
            }

            foreach ($present[$target]['buildDepends'] as $dependency) {
                if (!in_array($dependency, self::RUNNER_DIRECT_DEPENDENCIES[$target], true)) {
                    $unexpectedDependencies[$target][] = self::formatCabalSetupDependency($dependency, $present[$target]['dependencyConstraints'][$dependency] ?? '');
                }
            }

            foreach (self::RUNNER_DEPENDENCY_CONSTRAINTS[$target] ?? [] as $dependency => $expectedConstraint) {
                if (!in_array($dependency, $present[$target]['buildDepends'], true)) {
                    continue;
                }

                $actualConstraint = $present[$target]['dependencyConstraints'][$dependency] ?? '';
                if ($actualConstraint !== $expectedConstraint) {
                    $mismatchedDependencyConstraints[$target][$dependency] = [
                        'expected' => $expectedConstraint,
                        'actual' => $actualConstraint,
                    ];
                }
            }

            foreach (self::RUNNER_EXECUTABLE_OPTIONS[$target] as $option) {
                if (!in_array($option, $present[$target]['ghcOptions'], true)) {
                    $missingExecutableOptions[$target][] = $option;
                }
            }

            foreach ($present[$target]['ghcOptions'] as $option) {
                if (!in_array($option, self::RUNNER_EXECUTABLE_OPTIONS[$target], true)) {
                    $unexpectedExecutableOptions[$target][] = $option;
                }
            }

            $expectedLanguage = self::RUNNER_DEFAULT_LANGUAGES[$target];
            if ($present[$target]['defaultLanguage'] !== $expectedLanguage) {
                $mismatchedDefaultLanguages[$target] = [
                    'expected' => $expectedLanguage,
                    'actual' => $present[$target]['defaultLanguage'],
                ];
            }

            $expectedManual = self::RUNNER_EXPECTED_MANUAL_FIELDS[$target] ?? null;
            if ($present[$target]['manual'] !== $expectedManual) {
                $mismatchedManualFields[$target] = [
                    'expected' => $expectedManual,
                    'actual' => $present[$target]['manual'],
                ];
            }

            $expectedCommonImports = self::RUNNER_EXPECTED_COMMON_IMPORTS[$target] ?? [];
            foreach ($expectedCommonImports as $importName) {
                if (!in_array($importName, $present[$target]['commonImports'], true)) {
                    $missingCommonImports[$target][] = $importName;
                }
            }

            foreach ($present[$target]['commonImports'] as $importName) {
                if (!in_array($importName, $expectedCommonImports, true)) {
                    $unexpectedCommonImports[$target][] = $importName;
                }
            }

            if ($present[$target]['unresolvedCommonImports'] !== []) {
                $unresolvedCommonImports[$target] = $present[$target]['unresolvedCommonImports'];
            }

            $expectedMixins = self::RUNNER_EXPECTED_MIXINS[$target] ?? [];
            foreach ($present[$target]['mixins'] as $mixin) {
                if (!in_array($mixin, $expectedMixins, true)) {
                    $unexpectedMixins[$target][] = $mixin;
                }
            }

            $unexpectedBuildTools[$target] = self::unexpectedCabalBuildTools($present[$target]['buildToolDepends'], $present[$target]['buildTools']);
            if ($unexpectedBuildTools[$target] === []) {
                unset($unexpectedBuildTools[$target]);
            }

            $expectedTestOptions = self::RUNNER_EXPECTED_TEST_OPTIONS[$target] ?? [];
            foreach ($present[$target]['testOptions'] as $option) {
                if (!in_array($option, $expectedTestOptions, true)) {
                    $unexpectedTestOptions[$target][] = $option;
                }
            }

            $expectedDefaultExtensions = self::RUNNER_EXPECTED_DEFAULT_EXTENSIONS[$target] ?? [];
            foreach ($present[$target]['defaultExtensions'] as $extension) {
                if (!in_array($extension, $expectedDefaultExtensions, true)) {
                    $unexpectedDefaultExtensions[$target][] = $extension;
                }
            }

            $expectedOtherExtensions = self::RUNNER_EXPECTED_OTHER_EXTENSIONS[$target] ?? [];
            foreach ($present[$target]['otherExtensions'] as $extension) {
                if (!in_array($extension, $expectedOtherExtensions, true)) {
                    $unexpectedOtherExtensions[$target][] = $extension;
                }
            }

            $expectedCppOptions = self::RUNNER_EXPECTED_CPP_OPTIONS[$target] ?? [];
            foreach ($present[$target]['cppOptions'] as $option) {
                if (!in_array($option, $expectedCppOptions, true)) {
                    $unexpectedCppOptions[$target][] = $option;
                }
            }

            $expectedAutogenModules = self::RUNNER_EXPECTED_AUTOGEN_MODULES[$target] ?? [];
            foreach ($present[$target]['autogenModules'] as $module) {
                if (!in_array($module, $expectedAutogenModules, true)) {
                    $unexpectedAutogenModules[$target][] = $module;
                }
            }

            $expectedReexportedModules = self::RUNNER_EXPECTED_REEXPORTED_MODULES[$target] ?? [];
            foreach ($present[$target]['reexportedModules'] as $module) {
                if (!in_array($module, $expectedReexportedModules, true)) {
                    $unexpectedReexportedModules[$target][] = $module;
                }
            }

            $unexpectedModuleInterfaceFields[$target] = self::unexpectedCabalModuleInterfaceFields(
                $present[$target]['moduleInterfaceFields'],
                self::RUNNER_EXPECTED_MODULE_INTERFACE_FIELDS[$target] ?? []
            );
            if ($unexpectedModuleInterfaceFields[$target] === []) {
                unset($unexpectedModuleInterfaceFields[$target]);
            }

            $expectedExtraSourceFiles = self::RUNNER_EXPECTED_EXTRA_SOURCE_FILES[$target] ?? [];
            foreach ($present[$target]['extraSourceFiles'] as $pattern) {
                if (!in_array($pattern, $expectedExtraSourceFiles, true)) {
                    $unexpectedExtraSourceFiles[$target][] = $pattern;
                }
            }

            $expectedExtraDocFiles = self::RUNNER_EXPECTED_EXTRA_DOC_FILES[$target] ?? [];
            foreach ($present[$target]['extraDocFiles'] as $pattern) {
                if (!in_array($pattern, $expectedExtraDocFiles, true)) {
                    $unexpectedExtraDocFiles[$target][] = $pattern;
                }
            }

            $expectedExtraTmpFiles = self::RUNNER_EXPECTED_EXTRA_TMP_FILES[$target] ?? [];
            foreach ($present[$target]['extraTmpFiles'] as $pattern) {
                if (!in_array($pattern, $expectedExtraTmpFiles, true)) {
                    $unexpectedExtraTmpFiles[$target][] = $pattern;
                }
            }

            $expectedDataFiles = self::RUNNER_EXPECTED_DATA_FILES[$target] ?? [];
            foreach ($present[$target]['dataFiles'] as $pattern) {
                if (!in_array($pattern, $expectedDataFiles, true)) {
                    $unexpectedDataFiles[$target][] = $pattern;
                }
            }

            $expectedConditionalBranches = self::RUNNER_EXPECTED_CONDITIONAL_BRANCHES[$target] ?? [];
            foreach ($present[$target]['conditionalBranches'] as $branch) {
                if (!in_array($branch, $expectedConditionalBranches, true)) {
                    $unexpectedConditionalBranches[$target][] = $branch;
                }
            }

            $unexpectedNativeSystemFields[$target] = self::unexpectedCabalNativeSystemFields(
                $present[$target]['nativeSystemFields'],
                self::RUNNER_EXPECTED_NATIVE_SYSTEM_FIELDS[$target] ?? []
            );
            if ($unexpectedNativeSystemFields[$target] === []) {
                unset($unexpectedNativeSystemFields[$target]);
            }

            foreach (self::RUNNER_OTHER_MODULES[$target] as $module) {
                if (!in_array($module, $present[$target]['otherModules'], true)) {
                    $missingOtherModules[$target][] = $module;
                }
            }

            foreach ($present[$target]['otherModules'] as $module) {
                if (!in_array($module, self::RUNNER_OTHER_MODULES[$target], true)) {
                    $unexpectedOtherModules[$target][] = $module;
                }
            }
        }

        return [
            'expectedDependencies' => self::RUNNER_DIRECT_DEPENDENCIES,
            'expectedDependencyConstraints' => self::RUNNER_DEPENDENCY_CONSTRAINTS,
            'expectedExecutableOptions' => self::RUNNER_EXECUTABLE_OPTIONS,
            'expectedDefaultLanguages' => self::RUNNER_DEFAULT_LANGUAGES,
            'expectedManualFields' => self::RUNNER_EXPECTED_MANUAL_FIELDS,
            'expectedCommonImports' => self::RUNNER_EXPECTED_COMMON_IMPORTS,
            'expectedSourceDirectories' => self::expectedRunnerSourceDirectories(),
            'expectedMixins' => self::RUNNER_EXPECTED_MIXINS,
            'expectedBuildTools' => self::RUNNER_EXPECTED_BUILD_TOOLS,
            'expectedTestOptions' => self::RUNNER_EXPECTED_TEST_OPTIONS,
            'expectedDefaultExtensions' => self::RUNNER_EXPECTED_DEFAULT_EXTENSIONS,
            'expectedOtherExtensions' => self::RUNNER_EXPECTED_OTHER_EXTENSIONS,
            'expectedCppOptions' => self::RUNNER_EXPECTED_CPP_OPTIONS,
            'expectedAutogenModules' => self::RUNNER_EXPECTED_AUTOGEN_MODULES,
            'expectedReexportedModules' => self::RUNNER_EXPECTED_REEXPORTED_MODULES,
            'expectedModuleInterfaceFields' => self::RUNNER_EXPECTED_MODULE_INTERFACE_FIELDS,
            'expectedExtraSourceFiles' => self::RUNNER_EXPECTED_EXTRA_SOURCE_FILES,
            'expectedExtraDocFiles' => self::RUNNER_EXPECTED_EXTRA_DOC_FILES,
            'expectedExtraTmpFiles' => self::RUNNER_EXPECTED_EXTRA_TMP_FILES,
            'expectedDataFiles' => self::RUNNER_EXPECTED_DATA_FILES,
            'expectedConditionalBranches' => self::RUNNER_EXPECTED_CONDITIONAL_BRANCHES,
            'expectedNativeSystemFields' => self::RUNNER_EXPECTED_NATIVE_SYSTEM_FIELDS,
            'expectedOtherModules' => self::RUNNER_OTHER_MODULES,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'mismatchedEntryPoints' => $mismatchedEntryPoints,
            'missingDependencies' => $missingDependencies,
            'unexpectedDependencies' => $unexpectedDependencies,
            'mismatchedDependencyConstraints' => $mismatchedDependencyConstraints,
            'missingExecutableOptions' => $missingExecutableOptions,
            'unexpectedExecutableOptions' => $unexpectedExecutableOptions,
            'mismatchedDefaultLanguages' => $mismatchedDefaultLanguages,
            'mismatchedManualFields' => $mismatchedManualFields,
            'missingCommonImports' => $missingCommonImports,
            'unexpectedCommonImports' => $unexpectedCommonImports,
            'unresolvedCommonImports' => $unresolvedCommonImports,
            'unexpectedSourceDirectories' => $unexpectedSourceDirectories,
            'unexpectedMixins' => $unexpectedMixins,
            'unexpectedBuildTools' => $unexpectedBuildTools,
            'unexpectedTestOptions' => $unexpectedTestOptions,
            'unexpectedDefaultExtensions' => $unexpectedDefaultExtensions,
            'unexpectedOtherExtensions' => $unexpectedOtherExtensions,
            'unexpectedCppOptions' => $unexpectedCppOptions,
            'unexpectedAutogenModules' => $unexpectedAutogenModules,
            'unexpectedReexportedModules' => $unexpectedReexportedModules,
            'unexpectedModuleInterfaceFields' => $unexpectedModuleInterfaceFields,
            'unexpectedExtraSourceFiles' => $unexpectedExtraSourceFiles,
            'unexpectedExtraDocFiles' => $unexpectedExtraDocFiles,
            'unexpectedExtraTmpFiles' => $unexpectedExtraTmpFiles,
            'unexpectedDataFiles' => $unexpectedDataFiles,
            'unexpectedConditionalBranches' => $unexpectedConditionalBranches,
            'unexpectedNativeSystemFields' => $unexpectedNativeSystemFields,
            'missingOtherModules' => $missingOtherModules,
            'unexpectedOtherModules' => $unexpectedOtherModules,
        ];
    }

    /**
     * @return array{expectedDependencies:array<string, list<string>>, expectedDependencyConstraints:array<string, array<string, string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedSourceDirectories:array<string, list<string>>, expectedMixins:array<string, list<string>>, expectedBuildTools:array<string, list<string>>, expectedBenchmarkOptions:array<string, list<string>>, expectedDefaultExtensions:array<string, list<string>>, expectedOtherExtensions:array<string, list<string>>, expectedCppOptions:array<string, list<string>>, expectedAutogenModules:array<string, list<string>>, expectedReexportedModules:array<string, list<string>>, expectedOtherModules:array<string, list<string>>, expectedExtraSourceFiles:array<string, list<string>>, expectedExtraDocFiles:array<string, list<string>>, expectedExtraTmpFiles:array<string, list<string>>, expectedDataFiles:array<string, list<string>>, expectedConditionalBranches:array<string, list<string>>, expectedNativeSystemFields:array<string, array<string, list<string>>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, cppOptions:list<string>, autogenModules:list<string>, reexportedModules:list<string>, otherModules:list<string>, extraSourceFiles:list<string>, extraDocFiles:list<string>, extraTmpFiles:list<string>, dataFiles:list<string>, conditionalBranches:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, benchmarkOptions:list<string>, defaultExtensions:list<string>, otherExtensions:list<string>, nativeSystemFields:array<string, list<string>>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, unexpectedDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, unexpectedExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedSourceDirectories:array<string, list<string>>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, unexpectedBenchmarkOptions:array<string, list<string>>, unexpectedDefaultExtensions:array<string, list<string>>, unexpectedOtherExtensions:array<string, list<string>>, unexpectedCppOptions:array<string, list<string>>, unexpectedAutogenModules:array<string, list<string>>, unexpectedReexportedModules:array<string, list<string>>, unexpectedOtherModules:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>, unexpectedConditionalBranches:array<string, list<string>>, unexpectedNativeSystemFields:array<string, list<string>>}
     */
    private static function auditBenchmarkDependencyClosure(string $root): array
    {
        $present = [];
        foreach (self::BENCHMARK_ENTRY_POINTS as $target => $entryPoint) {
            $packageFile = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryPoint['packageFile']);
            if (!is_file($packageFile)) {
                continue;
            }

            $benchmarkName = substr($target, strlen('benchmark:'));
            $benchmarks = self::parseCabalBenchmarks((string) file_get_contents($packageFile));
            if (!array_key_exists($benchmarkName, $benchmarks)) {
                continue;
            }

            $present[$target] = [
                'packageFile' => $entryPoint['packageFile'],
                'type' => $benchmarks[$benchmarkName]['type'],
                'buildable' => $benchmarks[$benchmarkName]['buildable'],
                'manual' => $benchmarks[$benchmarkName]['manual'],
                'mainIs' => $benchmarks[$benchmarkName]['mainIs'],
                'commonImports' => $benchmarks[$benchmarkName]['commonImports'],
                'unresolvedCommonImports' => $benchmarks[$benchmarkName]['unresolvedCommonImports'],
                'sourceDirectories' => $benchmarks[$benchmarkName]['sourceDirectories'],
                'buildDepends' => $benchmarks[$benchmarkName]['buildDepends'],
                'dependencyConstraints' => $benchmarks[$benchmarkName]['dependencyConstraints'],
                'ghcOptions' => $benchmarks[$benchmarkName]['ghcOptions'],
                'cppOptions' => $benchmarks[$benchmarkName]['cppOptions'],
                'autogenModules' => $benchmarks[$benchmarkName]['autogenModules'],
                'reexportedModules' => $benchmarks[$benchmarkName]['reexportedModules'],
                'moduleInterfaceFields' => $benchmarks[$benchmarkName]['moduleInterfaceFields'],
                'otherModules' => $benchmarks[$benchmarkName]['otherModules'],
                'extraSourceFiles' => $benchmarks[$benchmarkName]['extraSourceFiles'],
                'extraDocFiles' => $benchmarks[$benchmarkName]['extraDocFiles'],
                'extraTmpFiles' => $benchmarks[$benchmarkName]['extraTmpFiles'],
                'dataFiles' => $benchmarks[$benchmarkName]['dataFiles'],
                'conditionalBranches' => $benchmarks[$benchmarkName]['conditionalBranches'],
                'defaultLanguage' => $benchmarks[$benchmarkName]['defaultLanguage'],
                'mixins' => $benchmarks[$benchmarkName]['mixins'],
                'buildToolDepends' => $benchmarks[$benchmarkName]['buildToolDepends'],
                'buildTools' => $benchmarks[$benchmarkName]['buildTools'],
                'benchmarkOptions' => $benchmarks[$benchmarkName]['benchmarkOptions'],
                'defaultExtensions' => $benchmarks[$benchmarkName]['defaultExtensions'],
                'otherExtensions' => $benchmarks[$benchmarkName]['otherExtensions'],
                'nativeSystemFields' => $benchmarks[$benchmarkName]['nativeSystemFields'],
            ];
        }

        $missingTargets = [];
        $mismatchedEntryPoints = [];
        $missingDependencies = [];
        $unexpectedDependencies = [];
        $mismatchedDependencyConstraints = [];
        $missingExecutableOptions = [];
        $unexpectedExecutableOptions = [];
        $mismatchedDefaultLanguages = [];
        $mismatchedManualFields = [];
        $missingCommonImports = [];
        $unexpectedCommonImports = [];
        $unresolvedCommonImports = [];
        $unexpectedSourceDirectories = [];
        $unexpectedMixins = [];
        $unexpectedBuildTools = [];
        $unexpectedBenchmarkOptions = [];
        $unexpectedDefaultExtensions = [];
        $unexpectedOtherExtensions = [];
        $unexpectedCppOptions = [];
        $unexpectedAutogenModules = [];
        $unexpectedReexportedModules = [];
        $unexpectedModuleInterfaceFields = [];
        $unexpectedOtherModules = [];
        $unexpectedExtraSourceFiles = [];
        $unexpectedExtraDocFiles = [];
        $unexpectedExtraTmpFiles = [];
        $unexpectedDataFiles = [];
        $unexpectedConditionalBranches = [];
        $unexpectedNativeSystemFields = [];

        foreach (self::BENCHMARK_ENTRY_POINTS as $target => $entryPoint) {
            if (!array_key_exists($target, $present)) {
                $missingTargets[] = $target;
                continue;
            }

            if ($present[$target]['type'] !== $entryPoint['type']) {
                $mismatchedEntryPoints[$target][] = 'type expected ' . $entryPoint['type'] . ', found ' . ($present[$target]['type'] ?? 'none');
            }

            if ($present[$target]['buildable'] !== true) {
                $mismatchedEntryPoints[$target][] = 'buildable expected true, found ' . self::formatCabalBuildableState($present[$target]['buildable']);
            }

            if ($present[$target]['mainIs'] !== $entryPoint['mainIs']) {
                $mismatchedEntryPoints[$target][] = 'main-is expected ' . $entryPoint['mainIs'] . ', found ' . ($present[$target]['mainIs'] ?? 'none');
            }

            if (!in_array($entryPoint['sourceDirectory'], $present[$target]['sourceDirectories'], true)) {
                $mismatchedEntryPoints[$target][] = 'hs-source-dirs missing ' . $entryPoint['sourceDirectory'];
            }

            $expectedSourceDirectories = [$entryPoint['sourceDirectory']];
            foreach ($present[$target]['sourceDirectories'] as $sourceDirectory) {
                if (!in_array($sourceDirectory, $expectedSourceDirectories, true)) {
                    $unexpectedSourceDirectories[$target][] = $sourceDirectory;
                }
            }

            foreach (self::BENCHMARK_DIRECT_DEPENDENCIES[$target] as $dependency) {
                if (!in_array($dependency, $present[$target]['buildDepends'], true)) {
                    $missingDependencies[$target][] = $dependency;
                }
            }

            foreach ($present[$target]['buildDepends'] as $dependency) {
                if (!in_array($dependency, self::BENCHMARK_DIRECT_DEPENDENCIES[$target], true)) {
                    $unexpectedDependencies[$target][] = self::formatCabalSetupDependency($dependency, $present[$target]['dependencyConstraints'][$dependency] ?? '');
                }
            }

            foreach (self::BENCHMARK_DEPENDENCY_CONSTRAINTS[$target] ?? [] as $dependency => $expectedConstraint) {
                if (!in_array($dependency, $present[$target]['buildDepends'], true)) {
                    continue;
                }

                $actualConstraint = $present[$target]['dependencyConstraints'][$dependency] ?? '';
                if ($actualConstraint !== $expectedConstraint) {
                    $mismatchedDependencyConstraints[$target][$dependency] = [
                        'expected' => $expectedConstraint,
                        'actual' => $actualConstraint,
                    ];
                }
            }

            foreach (self::BENCHMARK_EXECUTABLE_OPTIONS[$target] as $option) {
                if (!in_array($option, $present[$target]['ghcOptions'], true)) {
                    $missingExecutableOptions[$target][] = $option;
                }
            }

            foreach ($present[$target]['ghcOptions'] as $option) {
                if (!in_array($option, self::BENCHMARK_EXECUTABLE_OPTIONS[$target], true)) {
                    $unexpectedExecutableOptions[$target][] = $option;
                }
            }

            $expectedLanguage = self::BENCHMARK_DEFAULT_LANGUAGES[$target];
            if ($present[$target]['defaultLanguage'] !== $expectedLanguage) {
                $mismatchedDefaultLanguages[$target] = [
                    'expected' => $expectedLanguage,
                    'actual' => $present[$target]['defaultLanguage'],
                ];
            }

            $expectedManual = self::BENCHMARK_EXPECTED_MANUAL_FIELDS[$target] ?? null;
            if ($present[$target]['manual'] !== $expectedManual) {
                $mismatchedManualFields[$target] = [
                    'expected' => $expectedManual,
                    'actual' => $present[$target]['manual'],
                ];
            }

            $expectedCommonImports = self::BENCHMARK_EXPECTED_COMMON_IMPORTS[$target] ?? [];
            foreach ($expectedCommonImports as $importName) {
                if (!in_array($importName, $present[$target]['commonImports'], true)) {
                    $missingCommonImports[$target][] = $importName;
                }
            }

            foreach ($present[$target]['commonImports'] as $importName) {
                if (!in_array($importName, $expectedCommonImports, true)) {
                    $unexpectedCommonImports[$target][] = $importName;
                }
            }

            if ($present[$target]['unresolvedCommonImports'] !== []) {
                $unresolvedCommonImports[$target] = $present[$target]['unresolvedCommonImports'];
            }

            $expectedMixins = self::BENCHMARK_EXPECTED_MIXINS[$target] ?? [];
            foreach ($present[$target]['mixins'] as $mixin) {
                if (!in_array($mixin, $expectedMixins, true)) {
                    $unexpectedMixins[$target][] = $mixin;
                }
            }

            $unexpectedBuildTools[$target] = self::unexpectedCabalBuildTools($present[$target]['buildToolDepends'], $present[$target]['buildTools']);
            if ($unexpectedBuildTools[$target] === []) {
                unset($unexpectedBuildTools[$target]);
            }

            $expectedBenchmarkOptions = self::BENCHMARK_EXPECTED_BENCHMARK_OPTIONS[$target] ?? [];
            foreach ($present[$target]['benchmarkOptions'] as $option) {
                if (!in_array($option, $expectedBenchmarkOptions, true)) {
                    $unexpectedBenchmarkOptions[$target][] = $option;
                }
            }

            $expectedDefaultExtensions = self::BENCHMARK_EXPECTED_DEFAULT_EXTENSIONS[$target] ?? [];
            foreach ($present[$target]['defaultExtensions'] as $extension) {
                if (!in_array($extension, $expectedDefaultExtensions, true)) {
                    $unexpectedDefaultExtensions[$target][] = $extension;
                }
            }

            $expectedOtherExtensions = self::BENCHMARK_EXPECTED_OTHER_EXTENSIONS[$target] ?? [];
            foreach ($present[$target]['otherExtensions'] as $extension) {
                if (!in_array($extension, $expectedOtherExtensions, true)) {
                    $unexpectedOtherExtensions[$target][] = $extension;
                }
            }

            $expectedCppOptions = self::BENCHMARK_EXPECTED_CPP_OPTIONS[$target] ?? [];
            foreach ($present[$target]['cppOptions'] as $option) {
                if (!in_array($option, $expectedCppOptions, true)) {
                    $unexpectedCppOptions[$target][] = $option;
                }
            }

            $expectedAutogenModules = self::BENCHMARK_EXPECTED_AUTOGEN_MODULES[$target] ?? [];
            foreach ($present[$target]['autogenModules'] as $module) {
                if (!in_array($module, $expectedAutogenModules, true)) {
                    $unexpectedAutogenModules[$target][] = $module;
                }
            }

            $expectedReexportedModules = self::BENCHMARK_EXPECTED_REEXPORTED_MODULES[$target] ?? [];
            foreach ($present[$target]['reexportedModules'] as $module) {
                if (!in_array($module, $expectedReexportedModules, true)) {
                    $unexpectedReexportedModules[$target][] = $module;
                }
            }

            $unexpectedModuleInterfaceFields[$target] = self::unexpectedCabalModuleInterfaceFields(
                $present[$target]['moduleInterfaceFields'],
                self::BENCHMARK_EXPECTED_MODULE_INTERFACE_FIELDS[$target] ?? []
            );
            if ($unexpectedModuleInterfaceFields[$target] === []) {
                unset($unexpectedModuleInterfaceFields[$target]);
            }

            $expectedOtherModules = self::BENCHMARK_EXPECTED_OTHER_MODULES[$target] ?? [];
            foreach ($present[$target]['otherModules'] as $module) {
                if (!in_array($module, $expectedOtherModules, true)) {
                    $unexpectedOtherModules[$target][] = $module;
                }
            }

            $expectedExtraSourceFiles = self::BENCHMARK_EXPECTED_EXTRA_SOURCE_FILES[$target] ?? [];
            foreach ($present[$target]['extraSourceFiles'] as $pattern) {
                if (!in_array($pattern, $expectedExtraSourceFiles, true)) {
                    $unexpectedExtraSourceFiles[$target][] = $pattern;
                }
            }

            $expectedExtraDocFiles = self::BENCHMARK_EXPECTED_EXTRA_DOC_FILES[$target] ?? [];
            foreach ($present[$target]['extraDocFiles'] as $pattern) {
                if (!in_array($pattern, $expectedExtraDocFiles, true)) {
                    $unexpectedExtraDocFiles[$target][] = $pattern;
                }
            }

            $expectedExtraTmpFiles = self::BENCHMARK_EXPECTED_EXTRA_TMP_FILES[$target] ?? [];
            foreach ($present[$target]['extraTmpFiles'] as $pattern) {
                if (!in_array($pattern, $expectedExtraTmpFiles, true)) {
                    $unexpectedExtraTmpFiles[$target][] = $pattern;
                }
            }

            $expectedDataFiles = self::BENCHMARK_EXPECTED_DATA_FILES[$target] ?? [];
            foreach ($present[$target]['dataFiles'] as $pattern) {
                if (!in_array($pattern, $expectedDataFiles, true)) {
                    $unexpectedDataFiles[$target][] = $pattern;
                }
            }

            $expectedConditionalBranches = self::BENCHMARK_EXPECTED_CONDITIONAL_BRANCHES[$target] ?? [];
            foreach ($present[$target]['conditionalBranches'] as $branch) {
                if (!in_array($branch, $expectedConditionalBranches, true)) {
                    $unexpectedConditionalBranches[$target][] = $branch;
                }
            }

            $unexpectedNativeSystemFields[$target] = self::unexpectedCabalNativeSystemFields(
                $present[$target]['nativeSystemFields'],
                self::BENCHMARK_EXPECTED_NATIVE_SYSTEM_FIELDS[$target] ?? []
            );
            if ($unexpectedNativeSystemFields[$target] === []) {
                unset($unexpectedNativeSystemFields[$target]);
            }
        }

        return [
            'expectedDependencies' => self::BENCHMARK_DIRECT_DEPENDENCIES,
            'expectedDependencyConstraints' => self::BENCHMARK_DEPENDENCY_CONSTRAINTS,
            'expectedExecutableOptions' => self::BENCHMARK_EXECUTABLE_OPTIONS,
            'expectedDefaultLanguages' => self::BENCHMARK_DEFAULT_LANGUAGES,
            'expectedManualFields' => self::BENCHMARK_EXPECTED_MANUAL_FIELDS,
            'expectedCommonImports' => self::BENCHMARK_EXPECTED_COMMON_IMPORTS,
            'expectedSourceDirectories' => self::expectedBenchmarkSourceDirectories(),
            'expectedMixins' => self::BENCHMARK_EXPECTED_MIXINS,
            'expectedBuildTools' => self::BENCHMARK_EXPECTED_BUILD_TOOLS,
            'expectedBenchmarkOptions' => self::BENCHMARK_EXPECTED_BENCHMARK_OPTIONS,
            'expectedDefaultExtensions' => self::BENCHMARK_EXPECTED_DEFAULT_EXTENSIONS,
            'expectedOtherExtensions' => self::BENCHMARK_EXPECTED_OTHER_EXTENSIONS,
            'expectedCppOptions' => self::BENCHMARK_EXPECTED_CPP_OPTIONS,
            'expectedAutogenModules' => self::BENCHMARK_EXPECTED_AUTOGEN_MODULES,
            'expectedReexportedModules' => self::BENCHMARK_EXPECTED_REEXPORTED_MODULES,
            'expectedModuleInterfaceFields' => self::BENCHMARK_EXPECTED_MODULE_INTERFACE_FIELDS,
            'expectedOtherModules' => self::BENCHMARK_EXPECTED_OTHER_MODULES,
            'expectedExtraSourceFiles' => self::BENCHMARK_EXPECTED_EXTRA_SOURCE_FILES,
            'expectedExtraDocFiles' => self::BENCHMARK_EXPECTED_EXTRA_DOC_FILES,
            'expectedExtraTmpFiles' => self::BENCHMARK_EXPECTED_EXTRA_TMP_FILES,
            'expectedDataFiles' => self::BENCHMARK_EXPECTED_DATA_FILES,
            'expectedConditionalBranches' => self::BENCHMARK_EXPECTED_CONDITIONAL_BRANCHES,
            'expectedNativeSystemFields' => self::BENCHMARK_EXPECTED_NATIVE_SYSTEM_FIELDS,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'mismatchedEntryPoints' => $mismatchedEntryPoints,
            'missingDependencies' => $missingDependencies,
            'unexpectedDependencies' => $unexpectedDependencies,
            'mismatchedDependencyConstraints' => $mismatchedDependencyConstraints,
            'missingExecutableOptions' => $missingExecutableOptions,
            'unexpectedExecutableOptions' => $unexpectedExecutableOptions,
            'mismatchedDefaultLanguages' => $mismatchedDefaultLanguages,
            'mismatchedManualFields' => $mismatchedManualFields,
            'missingCommonImports' => $missingCommonImports,
            'unexpectedCommonImports' => $unexpectedCommonImports,
            'unresolvedCommonImports' => $unresolvedCommonImports,
            'unexpectedSourceDirectories' => $unexpectedSourceDirectories,
            'unexpectedMixins' => $unexpectedMixins,
            'unexpectedBuildTools' => $unexpectedBuildTools,
            'unexpectedBenchmarkOptions' => $unexpectedBenchmarkOptions,
            'unexpectedDefaultExtensions' => $unexpectedDefaultExtensions,
            'unexpectedOtherExtensions' => $unexpectedOtherExtensions,
            'unexpectedCppOptions' => $unexpectedCppOptions,
            'unexpectedAutogenModules' => $unexpectedAutogenModules,
            'unexpectedReexportedModules' => $unexpectedReexportedModules,
            'unexpectedModuleInterfaceFields' => $unexpectedModuleInterfaceFields,
            'unexpectedOtherModules' => $unexpectedOtherModules,
            'unexpectedExtraSourceFiles' => $unexpectedExtraSourceFiles,
            'unexpectedExtraDocFiles' => $unexpectedExtraDocFiles,
            'unexpectedExtraTmpFiles' => $unexpectedExtraTmpFiles,
            'unexpectedDataFiles' => $unexpectedDataFiles,
            'unexpectedConditionalBranches' => $unexpectedConditionalBranches,
            'unexpectedNativeSystemFields' => $unexpectedNativeSystemFields,
        ];
    }

    /**
     * @return array{packageFile:string, expectedDependencies:list<string>, expectedDependencyConstraints:array<string, string>, presentDependencies:list<string>, dependencyConstraints:array<string, string>, missingDependencies:list<string>, unexpectedDependencies:list<string>, mismatchedDependencyConstraints:array<string, array{expected:string, actual:string}>, expectedExposedModules:list<string>, presentExposedModules:list<string>, missingExposedModules:list<string>, unexpectedExposedModules:list<string>, expectedSourceDirectories:list<string>, presentSourceDirectories:list<string>, missingSourceDirectories:list<string>, unexpectedSourceDirectories:list<string>, expectedDefaultLanguage:string, presentDefaultLanguage:string|null, mismatchedDefaultLanguage:array{expected:string, actual:string|null}|null}
     */
    private static function auditServerLibraryClosure(string $root): array
    {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::SERVER_LIBRARY_PACKAGE_FILE);
        $presentDependencies = [];
        $dependencyConstraints = [];
        $presentExposedModules = [];
        $presentSourceDirectories = [];
        $presentDefaultLanguage = null;

        if (is_file($path)) {
            $libraries = self::parseCabalLibraries((string) file_get_contents($path));
            $presentDependencies = $libraries['default']['buildDepends'] ?? [];
            $dependencyConstraints = $libraries['default']['dependencyConstraints'] ?? [];
            $presentExposedModules = $libraries['default']['exposedModules'] ?? [];
            $presentSourceDirectories = $libraries['default']['sourceDirectories'] ?? [];
            $presentDefaultLanguage = $libraries['default']['defaultLanguage'] ?? null;
        }

        $missingDependencies = [];
        foreach (self::SERVER_LIBRARY_DEPENDENCIES as $dependency) {
            if (!in_array($dependency, $presentDependencies, true)) {
                $missingDependencies[] = $dependency;
            }
        }

        $unexpectedDependencies = [];
        foreach ($presentDependencies as $dependency) {
            if (!in_array($dependency, self::SERVER_LIBRARY_DEPENDENCIES, true)) {
                $unexpectedDependencies[] = self::formatCabalSetupDependency($dependency, $dependencyConstraints[$dependency] ?? '');
            }
        }

        $mismatchedDependencyConstraints = [];
        foreach (self::SERVER_LIBRARY_DEPENDENCY_CONSTRAINTS as $dependency => $expectedConstraint) {
            if (!in_array($dependency, $presentDependencies, true)) {
                continue;
            }

            $actualConstraint = $dependencyConstraints[$dependency] ?? '';
            if ($actualConstraint !== $expectedConstraint) {
                $mismatchedDependencyConstraints[$dependency] = [
                    'expected' => $expectedConstraint,
                    'actual' => $actualConstraint,
                ];
            }
        }

        $missingExposedModules = [];
        foreach (self::SERVER_LIBRARY_EXPOSED_MODULES as $module) {
            if (!in_array($module, $presentExposedModules, true)) {
                $missingExposedModules[] = $module;
            }
        }

        $unexpectedExposedModules = [];
        foreach ($presentExposedModules as $module) {
            if (!in_array($module, self::SERVER_LIBRARY_EXPOSED_MODULES, true)) {
                $unexpectedExposedModules[] = $module;
            }
        }

        $missingSourceDirectories = [];
        foreach (self::SERVER_LIBRARY_SOURCE_DIRECTORIES as $directory) {
            if (!in_array($directory, $presentSourceDirectories, true)) {
                $missingSourceDirectories[] = $directory;
            }
        }

        $unexpectedSourceDirectories = [];
        foreach ($presentSourceDirectories as $directory) {
            if (!in_array($directory, self::SERVER_LIBRARY_SOURCE_DIRECTORIES, true)) {
                $unexpectedSourceDirectories[] = $directory;
            }
        }

        $mismatchedDefaultLanguage = null;
        if (is_file($path) && $presentDefaultLanguage !== self::SERVER_LIBRARY_DEFAULT_LANGUAGE) {
            $mismatchedDefaultLanguage = [
                'expected' => self::SERVER_LIBRARY_DEFAULT_LANGUAGE,
                'actual' => $presentDefaultLanguage,
            ];
        }

        return [
            'packageFile' => self::SERVER_LIBRARY_PACKAGE_FILE,
            'expectedDependencies' => self::SERVER_LIBRARY_DEPENDENCIES,
            'expectedDependencyConstraints' => self::SERVER_LIBRARY_DEPENDENCY_CONSTRAINTS,
            'presentDependencies' => $presentDependencies,
            'dependencyConstraints' => $dependencyConstraints,
            'missingDependencies' => $missingDependencies,
            'unexpectedDependencies' => $unexpectedDependencies,
            'mismatchedDependencyConstraints' => $mismatchedDependencyConstraints,
            'expectedExposedModules' => self::SERVER_LIBRARY_EXPOSED_MODULES,
            'presentExposedModules' => $presentExposedModules,
            'missingExposedModules' => $missingExposedModules,
            'unexpectedExposedModules' => $unexpectedExposedModules,
            'expectedSourceDirectories' => self::SERVER_LIBRARY_SOURCE_DIRECTORIES,
            'presentSourceDirectories' => $presentSourceDirectories,
            'missingSourceDirectories' => $missingSourceDirectories,
            'unexpectedSourceDirectories' => $unexpectedSourceDirectories,
            'expectedDefaultLanguage' => self::SERVER_LIBRARY_DEFAULT_LANGUAGE,
            'presentDefaultLanguage' => $presentDefaultLanguage,
            'mismatchedDefaultLanguage' => $mismatchedDefaultLanguage,
        ];
    }

    private static function auditCliExecutableClosure(string $root): array
    {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CLI_EXECUTABLE_PACKAGE_FILE);
        $present = [];
        $presentConditionalFieldClosure = [];
        $sourceArtifactClosure = self::auditCliExecutableSourceArtifactClosure($root);
        if (is_file($path)) {
            $contents = (string) file_get_contents($path);
            $executables = self::parseCabalExecutables($contents);
            $present = $executables[self::CLI_EXECUTABLE_NAME] ?? [];
            $stanzas = self::parseCabalStanzas($contents);
            $presentConditionalFieldClosure = self::resolveCabalStanzaConditionalFieldBlocks(
                'executable:' . self::CLI_EXECUTABLE_NAME,
                $stanzas,
                self::parseCabalConditionalFieldBlocks($contents)
            );
        }

        $missingExecutable = $present === [];
        $presentBuildable = $present['buildable'] ?? null;
        $presentMainIs = $present['mainIs'] ?? null;
        $presentDependencies = $present['buildDepends'] ?? [];
        $dependencyConstraints = $present['dependencyConstraints'] ?? [];
        $presentExecutableOptions = $present['ghcOptions'] ?? [];
        $presentDefaultLanguage = $present['defaultLanguage'] ?? null;
        $presentCommonImports = $present['commonImports'] ?? [];
        $presentUnresolvedCommonImports = $present['unresolvedCommonImports'] ?? [];
        $presentSourceDirectories = $present['sourceDirectories'] ?? [];
        $presentOtherExtensions = $present['otherExtensions'] ?? [];
        $presentOtherModules = $present['otherModules'] ?? [];
        $presentConditionalBranches = $present['conditionalBranches'] ?? [];
        $presentDefaultExtensions = $present['defaultExtensions'] ?? [];
        $presentCppOptions = $present['cppOptions'] ?? [];
        $presentMixins = $present['mixins'] ?? [];
        $presentBuildToolDepends = $present['buildToolDepends'] ?? [];
        $presentBuildTools = $present['buildTools'] ?? [];
        $presentAutogenModules = $present['autogenModules'] ?? [];
        $presentReexportedModules = $present['reexportedModules'] ?? [];
        $presentModuleInterfaceFields = $present['moduleInterfaceFields'] ?? [];
        $presentExtraSourceFiles = $present['extraSourceFiles'] ?? [];
        $presentExtraDocFiles = $present['extraDocFiles'] ?? [];
        $presentExtraTmpFiles = $present['extraTmpFiles'] ?? [];
        $presentDataFiles = $present['dataFiles'] ?? [];
        $presentNativeSystemFields = $present['nativeSystemFields'] ?? [];

        $mismatchedEntryPoint = [];
        if (!$missingExecutable) {
            if ($presentBuildable !== true) {
                $mismatchedEntryPoint[] = 'buildable expected True, found ' . self::formatBuildableState($presentBuildable);
            }
            if ($presentMainIs !== self::CLI_EXECUTABLE_MAIN_IS) {
                $mismatchedEntryPoint[] = 'main-is expected ' . self::CLI_EXECUTABLE_MAIN_IS . ', found ' . ($presentMainIs ?? 'none');
            }
        }

        $missingDependencies = [];
        foreach (self::CLI_EXECUTABLE_DEPENDENCIES as $dependency) {
            if (!in_array($dependency, $presentDependencies, true)) {
                $missingDependencies[] = $dependency;
            }
        }

        $unexpectedDependencies = [];
        foreach ($presentDependencies as $dependency) {
            if (!in_array($dependency, self::CLI_EXECUTABLE_DEPENDENCIES, true)) {
                $unexpectedDependencies[] = self::formatCabalSetupDependency($dependency, $dependencyConstraints[$dependency] ?? '');
            }
        }

        $mismatchedDependencyConstraints = [];
        foreach (self::CLI_EXECUTABLE_DEPENDENCY_CONSTRAINTS as $dependency => $expectedConstraint) {
            if (!in_array($dependency, $presentDependencies, true)) {
                continue;
            }

            $actualConstraint = $dependencyConstraints[$dependency] ?? '';
            if ($actualConstraint !== $expectedConstraint) {
                $mismatchedDependencyConstraints[$dependency] = [
                    'expected' => $expectedConstraint,
                    'actual' => $actualConstraint,
                ];
            }
        }

        $missingExecutableOptions = [];
        foreach (self::CLI_EXECUTABLE_GHC_OPTIONS as $option) {
            if (!in_array($option, $presentExecutableOptions, true)) {
                $missingExecutableOptions[] = $option;
            }
        }

        $unexpectedExecutableOptions = [];
        foreach ($presentExecutableOptions as $option) {
            if (!in_array($option, self::CLI_EXECUTABLE_GHC_OPTIONS, true)) {
                $unexpectedExecutableOptions[] = $option;
            }
        }

        $mismatchedDefaultLanguage = null;
        if (is_file($path) && $presentDefaultLanguage !== self::CLI_EXECUTABLE_DEFAULT_LANGUAGE) {
            $mismatchedDefaultLanguage = [
                'expected' => self::CLI_EXECUTABLE_DEFAULT_LANGUAGE,
                'actual' => $presentDefaultLanguage,
            ];
        }

        $missingCommonImports = [];
        foreach (self::CLI_EXECUTABLE_COMMON_IMPORTS as $import) {
            if (!in_array($import, $presentCommonImports, true)) {
                $missingCommonImports[] = $import;
            }
        }

        $unexpectedCommonImports = [];
        foreach ($presentCommonImports as $import) {
            if (!in_array($import, self::CLI_EXECUTABLE_COMMON_IMPORTS, true)) {
                $unexpectedCommonImports[] = $import;
            }
        }

        $missingSourceDirectories = [];
        foreach (self::CLI_EXECUTABLE_SOURCE_DIRECTORIES as $directory) {
            if (!in_array($directory, $presentSourceDirectories, true)) {
                $missingSourceDirectories[] = $directory;
            }
        }

        $unexpectedSourceDirectories = [];
        foreach ($presentSourceDirectories as $directory) {
            if (!in_array($directory, self::CLI_EXECUTABLE_SOURCE_DIRECTORIES, true)) {
                $unexpectedSourceDirectories[] = $directory;
            }
        }

        $missingOtherExtensions = [];
        foreach (self::CLI_EXECUTABLE_OTHER_EXTENSIONS as $extension) {
            if (!in_array($extension, $presentOtherExtensions, true)) {
                $missingOtherExtensions[] = $extension;
            }
        }

        $unexpectedOtherExtensions = [];
        foreach ($presentOtherExtensions as $extension) {
            if (!in_array($extension, self::CLI_EXECUTABLE_OTHER_EXTENSIONS, true)) {
                $unexpectedOtherExtensions[] = $extension;
            }
        }

        $missingOtherModules = [];
        foreach (self::CLI_EXECUTABLE_OTHER_MODULES as $module) {
            if (!in_array($module, $presentOtherModules, true)) {
                $missingOtherModules[] = $module;
            }
        }

        $unexpectedOtherModules = [];
        foreach ($presentOtherModules as $module) {
            if (!in_array($module, self::CLI_EXECUTABLE_OTHER_MODULES, true)) {
                $unexpectedOtherModules[] = $module;
            }
        }

        $missingConditionalBranches = [];
        foreach (self::CLI_EXECUTABLE_EXPECTED_CONDITIONAL_BRANCHES as $branch) {
            if (!in_array($branch, $presentConditionalBranches, true)) {
                $missingConditionalBranches[] = $branch;
            }
        }

        $unexpectedConditionalBranches = [];
        foreach ($presentConditionalBranches as $branch) {
            if (!in_array($branch, self::CLI_EXECUTABLE_EXPECTED_CONDITIONAL_BRANCHES, true)) {
                $unexpectedConditionalBranches[] = $branch;
            }
        }

        $conditionalFieldDiff = self::diffConditionalFieldClosure(
            self::CLI_EXECUTABLE_EXPECTED_CONDITIONAL_FIELD_CLOSURE,
            $presentConditionalFieldClosure
        );

        return [
            'packageFile' => self::CLI_EXECUTABLE_PACKAGE_FILE,
            'executableName' => self::CLI_EXECUTABLE_NAME,
            'missingExecutable' => $missingExecutable,
            'expectedMainIs' => self::CLI_EXECUTABLE_MAIN_IS,
            'presentMainIs' => $presentMainIs,
            'expectedBuildable' => true,
            'presentBuildable' => $presentBuildable,
            'mismatchedEntryPoint' => $mismatchedEntryPoint,
            'expectedDependencies' => self::CLI_EXECUTABLE_DEPENDENCIES,
            'expectedDependencyConstraints' => self::CLI_EXECUTABLE_DEPENDENCY_CONSTRAINTS,
            'presentDependencies' => $presentDependencies,
            'dependencyConstraints' => $dependencyConstraints,
            'missingDependencies' => $missingDependencies,
            'unexpectedDependencies' => $unexpectedDependencies,
            'mismatchedDependencyConstraints' => $mismatchedDependencyConstraints,
            'expectedExecutableOptions' => self::CLI_EXECUTABLE_GHC_OPTIONS,
            'presentExecutableOptions' => $presentExecutableOptions,
            'missingExecutableOptions' => $missingExecutableOptions,
            'unexpectedExecutableOptions' => $unexpectedExecutableOptions,
            'expectedDefaultLanguage' => self::CLI_EXECUTABLE_DEFAULT_LANGUAGE,
            'presentDefaultLanguage' => $presentDefaultLanguage,
            'mismatchedDefaultLanguage' => $mismatchedDefaultLanguage,
            'expectedCommonImports' => self::CLI_EXECUTABLE_COMMON_IMPORTS,
            'presentCommonImports' => $presentCommonImports,
            'missingCommonImports' => $missingCommonImports,
            'unexpectedCommonImports' => $unexpectedCommonImports,
            'unresolvedCommonImports' => $presentUnresolvedCommonImports,
            'expectedSourceDirectories' => self::CLI_EXECUTABLE_SOURCE_DIRECTORIES,
            'presentSourceDirectories' => $presentSourceDirectories,
            'missingSourceDirectories' => $missingSourceDirectories,
            'unexpectedSourceDirectories' => $unexpectedSourceDirectories,
            'expectedOtherExtensions' => self::CLI_EXECUTABLE_OTHER_EXTENSIONS,
            'presentOtherExtensions' => $presentOtherExtensions,
            'missingOtherExtensions' => $missingOtherExtensions,
            'unexpectedOtherExtensions' => $unexpectedOtherExtensions,
            'expectedOtherModules' => self::CLI_EXECUTABLE_OTHER_MODULES,
            'presentOtherModules' => $presentOtherModules,
            'missingOtherModules' => $missingOtherModules,
            'unexpectedOtherModules' => $unexpectedOtherModules,
            'expectedConditionalBranches' => self::CLI_EXECUTABLE_EXPECTED_CONDITIONAL_BRANCHES,
            'presentConditionalBranches' => $presentConditionalBranches,
            'missingConditionalBranches' => $missingConditionalBranches,
            'unexpectedConditionalBranches' => $unexpectedConditionalBranches,
            'expectedConditionalFieldClosure' => self::CLI_EXECUTABLE_EXPECTED_CONDITIONAL_FIELD_CLOSURE,
            'presentConditionalFieldClosure' => $presentConditionalFieldClosure,
            'missingConditionalFieldEntries' => $conditionalFieldDiff['missing'],
            'unexpectedConditionalFieldEntries' => $conditionalFieldDiff['unexpected'],
            'expectedSourceArtifacts' => $sourceArtifactClosure['expected'],
            'presentSourceArtifacts' => $sourceArtifactClosure['present'],
            'missingSourceArtifacts' => $sourceArtifactClosure['missing'],
            'wrongTypeSourceArtifacts' => $sourceArtifactClosure['wrongType'],
            'emptySourceArtifacts' => $sourceArtifactClosure['emptyFiles'],
            'sourceArtifactProvenance' => $sourceArtifactClosure['fileProvenance'],
            'expectedSourceSemantics' => $sourceArtifactClosure['expectedSemantics'],
            'presentSourceSemantics' => $sourceArtifactClosure['presentSemantics'],
            'missingSourceSemantics' => $sourceArtifactClosure['missingSemantics'],
            'presentDefaultExtensions' => $presentDefaultExtensions,
            'unexpectedDefaultExtensions' => $presentDefaultExtensions,
            'presentCppOptions' => $presentCppOptions,
            'unexpectedCppOptions' => $presentCppOptions,
            'presentMixins' => $presentMixins,
            'unexpectedMixins' => $presentMixins,
            'presentBuildToolDepends' => $presentBuildToolDepends,
            'presentBuildTools' => $presentBuildTools,
            'unexpectedBuildTools' => self::unexpectedCabalBuildTools($presentBuildToolDepends, $presentBuildTools),
            'presentAutogenModules' => $presentAutogenModules,
            'unexpectedAutogenModules' => $presentAutogenModules,
            'presentReexportedModules' => $presentReexportedModules,
            'unexpectedReexportedModules' => $presentReexportedModules,
            'presentModuleInterfaceFields' => $presentModuleInterfaceFields,
            'unexpectedModuleInterfaceFields' => self::unexpectedCabalModuleInterfaceFields($presentModuleInterfaceFields, []),
            'presentExtraSourceFiles' => $presentExtraSourceFiles,
            'unexpectedExtraSourceFiles' => $presentExtraSourceFiles,
            'presentExtraDocFiles' => $presentExtraDocFiles,
            'unexpectedExtraDocFiles' => $presentExtraDocFiles,
            'presentExtraTmpFiles' => $presentExtraTmpFiles,
            'unexpectedExtraTmpFiles' => $presentExtraTmpFiles,
            'presentDataFiles' => $presentDataFiles,
            'unexpectedDataFiles' => $presentDataFiles,
            'presentNativeSystemFields' => $presentNativeSystemFields,
            'unexpectedNativeSystemFields' => self::unexpectedCabalNativeSystemFields($presentNativeSystemFields, []),
        ];
    }

    /**
     * @return array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, fileProvenance:array<string, array{sha256:string, bytes:int}>, expectedSemantics:array<string, array<string, string>>, presentSemantics:array<string, list<string>>, missingSemantics:array<string, list<string>>}
     */
    private static function auditCliExecutableSourceArtifactClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];
        $emptyFiles = [];
        $fileProvenance = [];
        $presentSemantics = [];
        $missingSemantics = [];

        foreach (self::CLI_EXECUTABLE_SOURCE_ARTIFACTS as $relativePath => $expectedKind) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $actualKind = self::filesystemArtifactKind($path);
            if ($actualKind === null) {
                $missing[] = $relativePath;
                continue;
            }

            if ($actualKind !== $expectedKind) {
                $wrongType[$relativePath] = [
                    'expected' => $expectedKind,
                    'actual' => $actualKind,
                ];
                continue;
            }

            $present[] = $relativePath;
            $bytes = filesize($path);
            if ($bytes === 0) {
                $emptyFiles[] = $relativePath;
            }

            $contents = file_get_contents($path);
            if ($contents !== false) {
                $fileProvenance[$relativePath] = [
                    'sha256' => hash('sha256', $contents),
                    'bytes' => strlen($contents),
                ];

                foreach (self::CLI_EXECUTABLE_SOURCE_SEMANTICS[$relativePath] ?? [] as $label => $snippet) {
                    if (str_contains($contents, $snippet)) {
                        $presentSemantics[$relativePath][] = $label;
                    } else {
                        $missingSemantics[$relativePath][] = $label;
                    }
                }
            }
        }

        return [
            'expected' => self::CLI_EXECUTABLE_SOURCE_ARTIFACTS,
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
            'emptyFiles' => $emptyFiles,
            'fileProvenance' => $fileProvenance,
            'expectedSemantics' => self::CLI_EXECUTABLE_SOURCE_SEMANTICS,
            'presentSemantics' => $presentSemantics,
            'missingSemantics' => $missingSemantics,
        ];
    }

    /**
     * @return array{packageFile:string, expectedDependencies:list<string>, presentDependencies:list<string>, dependencyConstraints:array<string, string>, missingDependencies:list<string>, unexpectedDependencies:list<string>, expectedExposedModules:list<string>, presentExposedModules:list<string>, missingExposedModules:list<string>, unexpectedExposedModules:list<string>, expectedDefaultLanguage:string, presentDefaultLanguage:string|null, mismatchedDefaultLanguage:array{expected:string, actual:string|null}|null, expectedMixins:list<string>, presentMixins:list<string>, unexpectedMixins:list<string>, expectedBuildTools:list<string>, presentBuildToolDepends:list<string>, presentBuildTools:list<string>, unexpectedBuildTools:list<string>, expectedAutogenModules:list<string>, presentAutogenModules:list<string>, unexpectedAutogenModules:list<string>, expectedReexportedModules:list<string>, presentReexportedModules:list<string>, unexpectedReexportedModules:list<string>, expectedModuleInterfaceFields:array<string, list<string>>, presentModuleInterfaceFields:array<string, list<string>>, unexpectedModuleInterfaceFields:list<string>, expectedDefaultExtensions:list<string>, presentDefaultExtensions:list<string>, unexpectedDefaultExtensions:list<string>, expectedOtherExtensions:list<string>, presentOtherExtensions:list<string>, unexpectedOtherExtensions:list<string>, expectedExtraSourceFiles:list<string>, presentExtraSourceFiles:list<string>, unexpectedExtraSourceFiles:list<string>, expectedExtraDocFiles:list<string>, presentExtraDocFiles:list<string>, unexpectedExtraDocFiles:list<string>, expectedExtraTmpFiles:list<string>, presentExtraTmpFiles:list<string>, unexpectedExtraTmpFiles:list<string>, expectedDataFiles:list<string>, presentDataFiles:list<string>, unexpectedDataFiles:list<string>, allowedConditionalBranches:list<string>, presentConditionalBranches:list<string>, unexpectedConditionalBranches:list<string>, expectedNativeSystemFields:array<string, list<string>>, presentNativeSystemFields:array<string, list<string>>, unexpectedNativeSystemFields:list<string>}
     */
    private static function auditLuaEngineLibraryClosure(string $root): array
    {
        $packageFile = 'pandoc-lua-engine/pandoc-lua-engine.cabal';
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
        $presentDependencies = [];
        $dependencyConstraints = [];
        $presentSourceDirectories = [];
        $presentExposedModules = [];
        $presentOtherModules = [];
        $presentDefaultLanguage = null;
        $presentMixins = [];
        $presentBuildToolDepends = [];
        $presentBuildTools = [];
        $presentAutogenModules = [];
        $presentReexportedModules = [];
        $presentModuleInterfaceFields = [];
        $presentDefaultExtensions = [];
        $presentOtherExtensions = [];
        $presentExtraSourceFiles = [];
        $presentExtraDocFiles = [];
        $presentExtraTmpFiles = [];
        $presentDataFiles = [];
        $presentConditionalBranches = [];
        $presentNativeSystemFields = [];

        if (is_file($path)) {
            $libraries = self::parseCabalLibraries((string) file_get_contents($path));
            $presentDependencies = $libraries['default']['buildDepends'] ?? [];
            $dependencyConstraints = $libraries['default']['dependencyConstraints'] ?? [];
            $presentSourceDirectories = $libraries['default']['sourceDirectories'] ?? [];
            $presentExposedModules = $libraries['default']['exposedModules'] ?? [];
            $presentOtherModules = $libraries['default']['otherModules'] ?? [];
            $presentDefaultLanguage = $libraries['default']['defaultLanguage'] ?? null;
            $presentMixins = $libraries['default']['mixins'] ?? [];
            $presentBuildToolDepends = $libraries['default']['buildToolDepends'] ?? [];
            $presentBuildTools = $libraries['default']['buildTools'] ?? [];
            $presentAutogenModules = $libraries['default']['autogenModules'] ?? [];
            $presentReexportedModules = $libraries['default']['reexportedModules'] ?? [];
            $presentModuleInterfaceFields = $libraries['default']['moduleInterfaceFields'] ?? [];
            $presentDefaultExtensions = $libraries['default']['defaultExtensions'] ?? [];
            $presentOtherExtensions = $libraries['default']['otherExtensions'] ?? [];
            $presentExtraSourceFiles = $libraries['default']['extraSourceFiles'] ?? [];
            $presentExtraDocFiles = $libraries['default']['extraDocFiles'] ?? [];
            $presentExtraTmpFiles = $libraries['default']['extraTmpFiles'] ?? [];
            $presentDataFiles = $libraries['default']['dataFiles'] ?? [];
            $presentConditionalBranches = $libraries['default']['conditionalBranches'] ?? [];
            $presentNativeSystemFields = $libraries['default']['nativeSystemFields'] ?? [];
        }

        $missingDependencies = [];
        foreach (self::LUA_ENGINE_LIBRARY_DEPENDENCIES as $dependency) {
            if (!in_array($dependency, $presentDependencies, true)) {
                $missingDependencies[] = $dependency;
            }
        }

        $unexpectedDependencies = [];
        foreach ($presentDependencies as $dependency) {
            if (!in_array($dependency, self::LUA_ENGINE_LIBRARY_DEPENDENCIES, true) && self::isLuaEngineSupportDependency($dependency)) {
                $unexpectedDependencies[] = self::formatCabalSetupDependency($dependency, $dependencyConstraints[$dependency] ?? '');
            }
        }

        $missingExposedModules = [];
        foreach (self::LUA_ENGINE_LIBRARY_EXPOSED_MODULES as $module) {
            if (!in_array($module, $presentExposedModules, true)) {
                $missingExposedModules[] = $module;
            }
        }

        $unexpectedExposedModules = [];
        foreach ($presentExposedModules as $module) {
            if (!in_array($module, self::LUA_ENGINE_LIBRARY_EXPOSED_MODULES, true)) {
                $unexpectedExposedModules[] = $module;
            }
        }

        $missingSourceDirectories = [];
        foreach (self::LUA_ENGINE_LIBRARY_SOURCE_DIRECTORIES as $directory) {
            if (!in_array($directory, $presentSourceDirectories, true)) {
                $missingSourceDirectories[] = $directory;
            }
        }

        $unexpectedSourceDirectories = [];
        foreach ($presentSourceDirectories as $directory) {
            if (!in_array($directory, self::LUA_ENGINE_LIBRARY_SOURCE_DIRECTORIES, true)) {
                $unexpectedSourceDirectories[] = $directory;
            }
        }

        $missingOtherModules = [];
        foreach (self::LUA_ENGINE_LIBRARY_OTHER_MODULES as $module) {
            if (!in_array($module, $presentOtherModules, true)) {
                $missingOtherModules[] = $module;
            }
        }

        $unexpectedOtherModules = [];
        foreach ($presentOtherModules as $module) {
            if (!in_array($module, self::LUA_ENGINE_LIBRARY_OTHER_MODULES, true)) {
                $unexpectedOtherModules[] = $module;
            }
        }

        $sourceArtifactClosure = self::auditLuaEngineLibrarySourceArtifactClosure($root);

        $mismatchedDefaultLanguage = null;
        if (is_file($path) && $presentDefaultLanguage !== self::LUA_ENGINE_LIBRARY_DEFAULT_LANGUAGE) {
            $mismatchedDefaultLanguage = [
                'expected' => self::LUA_ENGINE_LIBRARY_DEFAULT_LANGUAGE,
                'actual' => $presentDefaultLanguage,
            ];
        }

        $unexpectedMixins = [];
        foreach ($presentMixins as $mixin) {
            if (!in_array($mixin, self::LUA_ENGINE_LIBRARY_EXPECTED_MIXINS, true)) {
                $unexpectedMixins[] = $mixin;
            }
        }

        $unexpectedBuildTools = self::unexpectedCabalBuildTools($presentBuildToolDepends, $presentBuildTools);

        $unexpectedAutogenModules = [];
        foreach ($presentAutogenModules as $module) {
            if (!in_array($module, self::LUA_ENGINE_LIBRARY_EXPECTED_AUTOGEN_MODULES, true)) {
                $unexpectedAutogenModules[] = $module;
            }
        }

        $unexpectedReexportedModules = [];
        foreach ($presentReexportedModules as $module) {
            if (!in_array($module, self::LUA_ENGINE_LIBRARY_EXPECTED_REEXPORTED_MODULES, true)) {
                $unexpectedReexportedModules[] = $module;
            }
        }

        $unexpectedModuleInterfaceFields = self::unexpectedCabalModuleInterfaceFields(
            $presentModuleInterfaceFields,
            self::LUA_ENGINE_LIBRARY_EXPECTED_MODULE_INTERFACE_FIELDS
        );

        $unexpectedDefaultExtensions = [];
        foreach ($presentDefaultExtensions as $extension) {
            if (!in_array($extension, self::LUA_ENGINE_LIBRARY_EXPECTED_DEFAULT_EXTENSIONS, true)) {
                $unexpectedDefaultExtensions[] = $extension;
            }
        }

        $unexpectedOtherExtensions = [];
        foreach ($presentOtherExtensions as $extension) {
            if (!in_array($extension, self::LUA_ENGINE_LIBRARY_EXPECTED_OTHER_EXTENSIONS, true)) {
                $unexpectedOtherExtensions[] = $extension;
            }
        }

        $unexpectedExtraSourceFiles = [];
        foreach ($presentExtraSourceFiles as $pattern) {
            if (!in_array($pattern, self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_SOURCE_FILES, true)) {
                $unexpectedExtraSourceFiles[] = $pattern;
            }
        }

        $unexpectedExtraDocFiles = [];
        foreach ($presentExtraDocFiles as $pattern) {
            if (!in_array($pattern, self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_DOC_FILES, true)) {
                $unexpectedExtraDocFiles[] = $pattern;
            }
        }

        $unexpectedExtraTmpFiles = [];
        foreach ($presentExtraTmpFiles as $pattern) {
            if (!in_array($pattern, self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_TMP_FILES, true)) {
                $unexpectedExtraTmpFiles[] = $pattern;
            }
        }

        $unexpectedDataFiles = [];
        foreach ($presentDataFiles as $pattern) {
            if (!in_array($pattern, self::LUA_ENGINE_LIBRARY_EXPECTED_DATA_FILES, true)) {
                $unexpectedDataFiles[] = $pattern;
            }
        }

        $unexpectedConditionalBranches = [];
        foreach ($presentConditionalBranches as $branch) {
            if (!in_array($branch, self::LUA_ENGINE_LIBRARY_ALLOWED_CONDITIONAL_BRANCHES, true)) {
                $unexpectedConditionalBranches[] = $branch;
            }
        }

        $unexpectedNativeSystemFields = self::unexpectedCabalNativeSystemFields(
            $presentNativeSystemFields,
            self::LUA_ENGINE_LIBRARY_EXPECTED_NATIVE_SYSTEM_FIELDS
        );

        return [
            'packageFile' => $packageFile,
            'expectedDependencies' => self::LUA_ENGINE_LIBRARY_DEPENDENCIES,
            'presentDependencies' => $presentDependencies,
            'dependencyConstraints' => $dependencyConstraints,
            'missingDependencies' => $missingDependencies,
            'unexpectedDependencies' => $unexpectedDependencies,
            'expectedExposedModules' => self::LUA_ENGINE_LIBRARY_EXPOSED_MODULES,
            'presentExposedModules' => $presentExposedModules,
            'missingExposedModules' => $missingExposedModules,
            'unexpectedExposedModules' => $unexpectedExposedModules,
            'expectedSourceDirectories' => self::LUA_ENGINE_LIBRARY_SOURCE_DIRECTORIES,
            'presentSourceDirectories' => $presentSourceDirectories,
            'missingSourceDirectories' => $missingSourceDirectories,
            'unexpectedSourceDirectories' => $unexpectedSourceDirectories,
            'expectedOtherModules' => self::LUA_ENGINE_LIBRARY_OTHER_MODULES,
            'presentOtherModules' => $presentOtherModules,
            'missingOtherModules' => $missingOtherModules,
            'unexpectedOtherModules' => $unexpectedOtherModules,
            'expectedSourceArtifacts' => $sourceArtifactClosure['expected'],
            'presentSourceArtifacts' => $sourceArtifactClosure['present'],
            'missingSourceArtifacts' => $sourceArtifactClosure['missing'],
            'wrongTypeSourceArtifacts' => $sourceArtifactClosure['wrongType'],
            'emptySourceArtifacts' => $sourceArtifactClosure['emptyFiles'],
            'sourceArtifactProvenance' => $sourceArtifactClosure['fileProvenance'],
            'expectedDefaultLanguage' => self::LUA_ENGINE_LIBRARY_DEFAULT_LANGUAGE,
            'presentDefaultLanguage' => $presentDefaultLanguage,
            'mismatchedDefaultLanguage' => $mismatchedDefaultLanguage,
            'expectedMixins' => self::LUA_ENGINE_LIBRARY_EXPECTED_MIXINS,
            'presentMixins' => $presentMixins,
            'unexpectedMixins' => $unexpectedMixins,
            'expectedBuildTools' => self::LUA_ENGINE_LIBRARY_EXPECTED_BUILD_TOOLS,
            'presentBuildToolDepends' => $presentBuildToolDepends,
            'presentBuildTools' => $presentBuildTools,
            'unexpectedBuildTools' => $unexpectedBuildTools,
            'expectedAutogenModules' => self::LUA_ENGINE_LIBRARY_EXPECTED_AUTOGEN_MODULES,
            'presentAutogenModules' => $presentAutogenModules,
            'unexpectedAutogenModules' => $unexpectedAutogenModules,
            'expectedReexportedModules' => self::LUA_ENGINE_LIBRARY_EXPECTED_REEXPORTED_MODULES,
            'presentReexportedModules' => $presentReexportedModules,
            'unexpectedReexportedModules' => $unexpectedReexportedModules,
            'expectedModuleInterfaceFields' => self::LUA_ENGINE_LIBRARY_EXPECTED_MODULE_INTERFACE_FIELDS,
            'presentModuleInterfaceFields' => $presentModuleInterfaceFields,
            'unexpectedModuleInterfaceFields' => $unexpectedModuleInterfaceFields,
            'expectedDefaultExtensions' => self::LUA_ENGINE_LIBRARY_EXPECTED_DEFAULT_EXTENSIONS,
            'presentDefaultExtensions' => $presentDefaultExtensions,
            'unexpectedDefaultExtensions' => $unexpectedDefaultExtensions,
            'expectedOtherExtensions' => self::LUA_ENGINE_LIBRARY_EXPECTED_OTHER_EXTENSIONS,
            'presentOtherExtensions' => $presentOtherExtensions,
            'unexpectedOtherExtensions' => $unexpectedOtherExtensions,
            'expectedExtraSourceFiles' => self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_SOURCE_FILES,
            'presentExtraSourceFiles' => $presentExtraSourceFiles,
            'unexpectedExtraSourceFiles' => $unexpectedExtraSourceFiles,
            'expectedExtraDocFiles' => self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_DOC_FILES,
            'presentExtraDocFiles' => $presentExtraDocFiles,
            'unexpectedExtraDocFiles' => $unexpectedExtraDocFiles,
            'expectedExtraTmpFiles' => self::LUA_ENGINE_LIBRARY_EXPECTED_EXTRA_TMP_FILES,
            'presentExtraTmpFiles' => $presentExtraTmpFiles,
            'unexpectedExtraTmpFiles' => $unexpectedExtraTmpFiles,
            'expectedDataFiles' => self::LUA_ENGINE_LIBRARY_EXPECTED_DATA_FILES,
            'presentDataFiles' => $presentDataFiles,
            'unexpectedDataFiles' => $unexpectedDataFiles,
            'allowedConditionalBranches' => self::LUA_ENGINE_LIBRARY_ALLOWED_CONDITIONAL_BRANCHES,
            'presentConditionalBranches' => $presentConditionalBranches,
            'unexpectedConditionalBranches' => $unexpectedConditionalBranches,
            'expectedNativeSystemFields' => self::LUA_ENGINE_LIBRARY_EXPECTED_NATIVE_SYSTEM_FIELDS,
            'presentNativeSystemFields' => $presentNativeSystemFields,
            'unexpectedNativeSystemFields' => $unexpectedNativeSystemFields,
        ];
    }

    /**
     * @return array{expected:list<string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, fileProvenance:array<string, array{sha256:string, bytes:int}>}
     */
    private static function auditLuaEngineLibrarySourceArtifactClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];
        $emptyFiles = [];
        $fileProvenance = [];

        foreach (self::expectedLuaEngineLibrarySourceArtifacts() as $relativePath) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $actualKind = self::filesystemArtifactKind($path);
            if ($actualKind === null) {
                $missing[] = $relativePath;
                continue;
            }

            if ($actualKind !== 'file') {
                $wrongType[$relativePath] = [
                    'expected' => 'file',
                    'actual' => $actualKind,
                ];
                continue;
            }

            $present[] = $relativePath;
            $bytes = filesize($path);
            if ($bytes === 0) {
                $emptyFiles[] = $relativePath;
            }

            $contents = file_get_contents($path);
            if ($contents !== false) {
                $fileProvenance[$relativePath] = [
                    'sha256' => hash('sha256', $contents),
                    'bytes' => strlen($contents),
                ];
            }
        }

        return [
            'expected' => self::expectedLuaEngineLibrarySourceArtifacts(),
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
            'emptyFiles' => $emptyFiles,
            'fileProvenance' => $fileProvenance,
        ];
    }

    private static function isLuaEngineSupportDependency(string $dependency): bool
    {
        return $dependency === 'lpeg'
            || str_starts_with($dependency, 'hslua-module-')
            || str_starts_with($dependency, 'pandoc-lua-');
    }

    /**
     * @return array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>}
     */
    private static function auditRunnerEntrySourceClosure(string $root): array
    {
        $present = [];
        $missingTargets = [];
        $missingSemantics = [];

        foreach (self::RUNNER_ENTRY_SOURCE_SEMANTICS as $target => $expected) {
            $relativePath = $expected['entryFile'];
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (!is_file($path)) {
                $missingTargets[] = $target;
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                $missingTargets[] = $target;
                continue;
            }

            $matched = [];
            foreach ($expected['requiredSnippets'] as $label => $snippet) {
                if (str_contains($contents, $snippet)) {
                    $matched[] = $label;
                    continue;
                }

                $missingSemantics[$target][] = $label;
            }

            $present[$target] = [
                'entryFile' => $relativePath,
                'matchedSnippets' => $matched,
            ];
        }

        return [
            'expected' => self::RUNNER_ENTRY_SOURCE_SEMANTICS,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'missingSemantics' => $missingSemantics,
        ];
    }

    /**
     * @return array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>}
     */
    private static function auditBenchmarkEntrySourceClosure(string $root): array
    {
        $present = [];
        $missingTargets = [];
        $missingSemantics = [];

        foreach (self::BENCHMARK_ENTRY_SOURCE_SEMANTICS as $target => $expected) {
            $relativePath = $expected['entryFile'];
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (!is_file($path)) {
                $missingTargets[] = $target;
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                $missingTargets[] = $target;
                continue;
            }

            $matched = [];
            foreach ($expected['requiredSnippets'] as $label => $snippet) {
                if (str_contains($contents, $snippet)) {
                    $matched[] = $label;
                    continue;
                }

                $missingSemantics[$target][] = $label;
            }

            $present[$target] = [
                'entryFile' => $relativePath,
                'matchedSnippets' => $matched,
            ];
        }

        return [
            'expected' => self::BENCHMARK_ENTRY_SOURCE_SEMANTICS,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'missingSemantics' => $missingSemantics,
        ];
    }

    /**
     * @return array{expected:array<string, string>, expectedSemantics:array<string, array<string, string>>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, fileProvenance:array<string, array{sha256:string, bytes:int}>, presentSemantics:array<string, list<string>>, missingSemantics:array<string, list<string>>}
     */
    private static function auditFormatRegistrySourceClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];
        $emptyFiles = [];
        $fileProvenance = [];
        $presentSemantics = [];
        $missingSemantics = [];

        foreach (self::FORMAT_REGISTRY_SOURCE_ARTIFACTS as $relativePath => $expectedKind) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $actualKind = self::filesystemArtifactKind($path);
            if ($actualKind === null) {
                $missing[] = $relativePath;
                continue;
            }

            if ($actualKind !== $expectedKind) {
                $wrongType[$relativePath] = [
                    'expected' => $expectedKind,
                    'actual' => $actualKind,
                ];
                continue;
            }

            $present[] = $relativePath;
            $bytes = filesize($path);
            if ($bytes === 0) {
                $emptyFiles[] = $relativePath;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $fileProvenance[$relativePath] = [
                'sha256' => hash('sha256', $contents),
                'bytes' => strlen($contents),
            ];

            foreach (self::FORMAT_REGISTRY_SOURCE_SEMANTICS[$relativePath] ?? [] as $label => $snippet) {
                if (str_contains($contents, $snippet)) {
                    $presentSemantics[$relativePath][] = $label;
                    continue;
                }

                $missingSemantics[$relativePath][] = $label;
            }
        }

        return [
            'expected' => self::FORMAT_REGISTRY_SOURCE_ARTIFACTS,
            'expectedSemantics' => self::FORMAT_REGISTRY_SOURCE_SEMANTICS,
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
            'emptyFiles' => $emptyFiles,
            'fileProvenance' => $fileProvenance,
            'presentSemantics' => $presentSemantics,
            'missingSemantics' => $missingSemantics,
        ];
    }

    /**
     * @return array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, fileProvenance:array<string, array{sha256:string, bytes:int}>}
     */
    private static function auditRunnerArtifactClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];
        $emptyFiles = [];
        $fileProvenance = [];

        foreach (self::requiredRunnerArtifacts() as $relativePath => $expectedKind) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $actualKind = self::filesystemArtifactKind($path);
            if ($actualKind === null) {
                $missing[] = $relativePath;
                continue;
            }

            if ($actualKind !== $expectedKind) {
                $wrongType[$relativePath] = [
                    'expected' => $expectedKind,
                    'actual' => $actualKind,
                ];
                continue;
            }

            $present[] = $relativePath;
            if ($expectedKind === 'file') {
                $bytes = filesize($path);
                if ($bytes === 0) {
                    $emptyFiles[] = $relativePath;
                }

                $contents = file_get_contents($path);
                if ($contents !== false) {
                    $fileProvenance[$relativePath] = [
                        'sha256' => hash('sha256', $contents),
                        'bytes' => strlen($contents),
                    ];
                }
            }
        }

        return [
            'expected' => self::requiredRunnerArtifacts(),
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
            'emptyFiles' => $emptyFiles,
            'fileProvenance' => $fileProvenance,
        ];
    }

    /**
     * @return array{expected:array<string, string>, expectedSemantics:array<string, array<string, string>>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, missingSemantics:array<string, list<string>>, fileProvenance:array<string, array{sha256:string, bytes:int}>}
     */
    private static function auditBenchmarkArtifactClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];
        $emptyFiles = [];
        $missingSemantics = [];
        $fileProvenance = [];

        foreach (self::BENCHMARK_ARTIFACTS as $relativePath => $expectedKind) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $actualKind = self::filesystemArtifactKind($path);
            if ($actualKind === null) {
                $missing[] = $relativePath;
                continue;
            }

            if ($actualKind !== $expectedKind) {
                $wrongType[$relativePath] = [
                    'expected' => $expectedKind,
                    'actual' => $actualKind,
                ];
                continue;
            }

            $present[] = $relativePath;
            if ($expectedKind === 'file') {
                $bytes = filesize($path);
                if ($bytes === 0) {
                    $emptyFiles[] = $relativePath;
                }

                $contents = file_get_contents($path);
                if ($contents !== false) {
                    $fileProvenance[$relativePath] = [
                        'sha256' => hash('sha256', $contents),
                        'bytes' => strlen($contents),
                    ];

                    foreach (self::BENCHMARK_ARTIFACT_SEMANTICS[$relativePath] ?? [] as $label => $snippet) {
                        if (!self::benchmarkArtifactContainsSemanticMarker($contents, $snippet)) {
                            $missingSemantics[$relativePath][] = $label;
                        }
                    }
                }
            }
        }

        return [
            'expected' => self::BENCHMARK_ARTIFACTS,
            'expectedSemantics' => self::BENCHMARK_ARTIFACT_SEMANTICS,
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
            'emptyFiles' => $emptyFiles,
            'missingSemantics' => $missingSemantics,
            'fileProvenance' => $fileProvenance,
        ];
    }

    private static function benchmarkArtifactContainsSemanticMarker(string $contents, string $snippet): bool
    {
        if ($snippet === "\xff\xd8") {
            return str_starts_with($contents, $snippet);
        }

        if ($snippet === "\xff\xd9") {
            return str_ends_with($contents, $snippet);
        }

        return str_contains($contents, $snippet);
    }

    /**
     * @return array<string, string>
     */
    private static function requiredRunnerArtifacts(): array
    {
        $artifacts = self::REQUIRED_RUNNER_ARTIFACTS;

        foreach (self::RUNNER_OTHER_MODULES as $target => $modules) {
            $entryPoint = self::RUNNER_ENTRY_POINTS[$target] ?? null;
            if ($entryPoint === null) {
                continue;
            }

            $packageRoot = dirname($entryPoint['packageFile']);
            $packagePrefix = $packageRoot === '.' ? '' : str_replace('\\', '/', $packageRoot) . '/';
            $sourceDirectory = trim($entryPoint['sourceDirectory'], '/');
            $sourcePrefix = $sourceDirectory === '' ? '' : $sourceDirectory . '/';

            foreach ($modules as $module) {
                $relativePath = $packagePrefix . $sourcePrefix . str_replace('.', '/', $module) . '.hs';
                $artifacts[$relativePath] = 'file';
            }
        }

        ksort($artifacts);
        return $artifacts;
    }

    /**
     * @param array<string, array{sourceDirectory:string}> $entryPoints
     * @return array<string, list<string>>
     */
    private static function expectedComponentSourceDirectories(array $entryPoints): array
    {
        $directories = [];
        foreach ($entryPoints as $target => $entryPoint) {
            $directories[$target] = [$entryPoint['sourceDirectory']];
        }

        return $directories;
    }

    /**
     * @return array{expectedStablePlanFiles:list<string>, present:array<string, array{sha256:string, bytes:int}>, missing:list<string>, wrongType:array<string, string>, emptyFiles:list<string>, invalidFiles:array<string, string>, unpinnedPlanRisk:bool}
     */
    private static function auditPlanStabilityClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];
        $emptyFiles = [];
        $invalidFiles = [];

        foreach (self::STABLE_PLAN_FILES as $relativePath) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $actualKind = self::filesystemArtifactKind($path);
            if ($actualKind === null) {
                $missing[] = $relativePath;
                continue;
            }

            if ($actualKind !== 'file') {
                $wrongType[$relativePath] = $actualKind;
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                $emptyFiles[] = $relativePath;
                continue;
            }

            if ($contents === '') {
                $emptyFiles[] = $relativePath;
            }

            if ($relativePath === 'cabal.project.freeze' && $contents !== '' && !self::isCabalProjectFreezeFile($contents)) {
                $invalidFiles[$relativePath] = 'missing pinned constraints';
            }

            $present[$relativePath] = [
                'sha256' => hash('sha256', $contents),
                'bytes' => strlen($contents),
            ];
        }

        return [
            'expectedStablePlanFiles' => self::STABLE_PLAN_FILES,
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
            'emptyFiles' => $emptyFiles,
            'invalidFiles' => $invalidFiles,
            'unpinnedPlanRisk' => !array_key_exists('cabal.project.freeze', $present)
                || in_array('cabal.project.freeze', $emptyFiles, true)
                || array_key_exists('cabal.project.freeze', $invalidFiles)
                || array_key_exists('cabal.project.freeze', $wrongType),
        ];
    }

    private static function isCabalProjectFreezeFile(string $contents): bool
    {
        $contents = self::stripCabalLineComments($contents);
        $rawConstraints = '';
        $capturing = false;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*constraints\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $rawConstraints .= ' ' . $match[1];
                $capturing = true;
                continue;
            }

            if (!$capturing) {
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^\s+(.+?)\s*$/', $line, $match) === 1) {
                $rawConstraints .= ' ' . trim($match[1]);
                continue;
            }

            $capturing = false;
        }

        return preg_match('/(?:^|[,\s])(?:any\.)?[A-Za-z][A-Za-z0-9_-]*\s*==\s*[0-9][A-Za-z0-9_.+-]*/', $rawConstraints) === 1;
    }

    /**
     * @return array{expected:list<string>, present:array<string, array{sha256:string, bytes:int}>, missing:list<string>}
     */
    private static function auditRequiredFileProvenance(string $root): array
    {
        $present = [];
        $missing = [];

        foreach (self::REQUIRED_FILES as $relativePath) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (!is_file($path)) {
                $missing[] = $relativePath;
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                $missing[] = $relativePath;
                continue;
            }

            $present[$relativePath] = [
                'sha256' => hash('sha256', $contents),
                'bytes' => strlen($contents),
            ];
        }

        return [
            'expected' => self::REQUIRED_FILES,
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * @return array<string, array{type:string, name:string, fields:array<string, string>, conditionals:list<string>}>
     */
    private static function parseCabalStanzas(string $contents): array
    {
        $stanzas = [];
        $currentKey = null;
        $lastField = null;
        $lastFieldIndent = null;
        $conditionalIndent = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^custom-setup\s*$/i', $line) === 1) {
                $currentKey = 'custom-setup:default';
                $stanzas[$currentKey] = [
                    'type' => 'custom-setup',
                    'name' => 'default',
                    'fields' => [],
                    'conditionals' => [],
                ];
                $lastField = null;
                $lastFieldIndent = null;
                $conditionalIndent = null;
                continue;
            }

            if (preg_match('/^library\s*$/i', $line) === 1) {
                $currentKey = 'library:default';
                    $stanzas[$currentKey] = [
                        'type' => 'library',
                        'name' => 'default',
                        'fields' => [],
                        'conditionals' => [],
                    ];
                $lastField = null;
                $lastFieldIndent = null;
                $conditionalIndent = null;
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s+([A-Za-z0-9_.-]+)\s*$/', $line, $match) === 1) {
                $type = strtolower($match[1]);
                if (in_array($type, ['test-suite', 'benchmark', 'executable', 'common', 'library', 'flag'], true)) {
                    $currentKey = $type . ':' . $match[2];
                    $stanzas[$currentKey] = [
                        'type' => $type,
                        'name' => $match[2],
                        'fields' => [],
                        'conditionals' => [],
                    ];
                    $lastField = null;
                    $lastFieldIndent = null;
                    $conditionalIndent = null;
                    continue;
                }
            }

            if ($currentKey === null) {
                continue;
            }

            if (preg_match('/^\S/', $line) === 1) {
                $currentKey = null;
                $lastField = null;
                $lastFieldIndent = null;
                $conditionalIndent = null;
                continue;
            }

            $trimmed = trim($line);
            $indent = strlen($line) - strlen(ltrim($line));

            if ($conditionalIndent !== null) {
                if ($trimmed === '') {
                    continue;
                }

                if ($indent > $conditionalIndent) {
                    continue;
                }

                if ($indent === $conditionalIndent && preg_match('/^(?:elif|else)\b/i', $trimmed) === 1) {
                    $condition = self::normalizeCabalListItem($trimmed);
                    if (!in_array($condition, $stanzas[$currentKey]['conditionals'], true)) {
                        $stanzas[$currentKey]['conditionals'][] = $condition;
                    }
                    $lastField = null;
                    $lastFieldIndent = null;
                    continue;
                }

                $conditionalIndent = null;
            }

            if (preg_match('/^(?:if|elif|else)\b/i', $trimmed) === 1) {
                $condition = self::normalizeCabalListItem($trimmed);
                if (!in_array($condition, $stanzas[$currentKey]['conditionals'], true)) {
                    $stanzas[$currentKey]['conditionals'][] = $condition;
                }
                $conditionalIndent = $indent;
                $lastField = null;
                $lastFieldIndent = null;
                continue;
            }

            if ($lastField !== null && $lastFieldIndent !== null && $indent > $lastFieldIndent && preg_match('/^\s+(.*?)\s*$/', $line, $match) === 1) {
                $continuation = trim($match[1]);
                if ($continuation !== '') {
                    $stanzas[$currentKey]['fields'][$lastField] .= "\n" . $continuation;
                }
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $lastField = strtolower($match[1]);
                $lastFieldIndent = $indent;
                $stanzas[$currentKey]['fields'][$lastField] = trim($match[2]);
                continue;
            }

            if ($lastField !== null && preg_match('/^\s+(.*?)\s*$/', $line, $match) === 1) {
                $continuation = trim($match[1]);
                if ($continuation !== '') {
                    $stanzas[$currentKey]['fields'][$lastField] .= "\n" . $continuation;
                }
            }
        }

        return $stanzas;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private static function parseCabalConditionalFieldBlocks(string $contents): array
    {
        $blocks = [];
        $currentKey = null;
        $currentLabel = null;
        $activeBranch = null;
        $conditionalIndent = null;
        $lastCondition = null;
        $lastField = null;
        $lastFieldIndent = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^custom-setup\s*$/i', $line) === 1) {
                $currentKey = 'custom-setup:default';
                $currentLabel = 'custom-setup default';
                $activeBranch = null;
                $conditionalIndent = null;
                $lastCondition = null;
                $lastField = null;
                $lastFieldIndent = null;
                continue;
            }

            if (preg_match('/^library\s*$/i', $line) === 1) {
                $currentKey = 'library:default';
                $currentLabel = 'library default';
                $activeBranch = null;
                $conditionalIndent = null;
                $lastCondition = null;
                $lastField = null;
                $lastFieldIndent = null;
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s+([A-Za-z0-9_.-]+)\s*$/', $line, $match) === 1) {
                $type = strtolower($match[1]);
                if (in_array($type, ['test-suite', 'benchmark', 'executable', 'common', 'library', 'flag'], true)) {
                    $currentKey = $type . ':' . $match[2];
                    $currentLabel = $type . ' ' . $match[2];
                    $activeBranch = null;
                    $conditionalIndent = null;
                    $lastCondition = null;
                    $lastField = null;
                    $lastFieldIndent = null;
                    continue;
                }
            }

            if ($currentKey === null || $currentLabel === null) {
                continue;
            }

            if (preg_match('/^\S/', $line) === 1) {
                $currentKey = null;
                $currentLabel = null;
                $activeBranch = null;
                $conditionalIndent = null;
                $lastCondition = null;
                $lastField = null;
                $lastFieldIndent = null;
                continue;
            }

            $trimmed = trim($line);
            $indent = strlen($line) - strlen(ltrim($line));

            if ($activeBranch !== null && $conditionalIndent !== null) {
                if ($trimmed === '') {
                    continue;
                }

                if ($indent <= $conditionalIndent) {
                    if ($indent === $conditionalIndent && preg_match('/^(?:elif|else)\b/i', $trimmed) === 1) {
                        $condition = self::normalizeCabalListItem($trimmed);
                        $activeBranch = self::conditionalFieldBranchLabel($currentLabel, $condition, $lastCondition);
                        $blocks[$currentKey][$activeBranch] ??= [];
                        if (preg_match('/^else\b/i', $condition) !== 1) {
                            $lastCondition = $condition;
                        }
                        $lastField = null;
                        $lastFieldIndent = null;
                        continue;
                    }

                    $activeBranch = null;
                    $conditionalIndent = null;
                    $lastField = null;
                    $lastFieldIndent = null;
                } else {
                    if (preg_match('/^(?:if|elif|else)\b/i', $trimmed) === 1) {
                        continue;
                    }

                    if ($lastField !== null && $lastFieldIndent !== null && $indent > $lastFieldIndent) {
                        $continuation = trim($line);
                        if ($continuation !== '') {
                            $blocks[$currentKey][$activeBranch][$lastField] .= "\n" . $continuation;
                        }
                        continue;
                    }

                    if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                        $lastField = strtolower($match[1]);
                        $lastFieldIndent = $indent;
                        $blocks[$currentKey][$activeBranch] = self::mergeCabalFields(
                            $blocks[$currentKey][$activeBranch] ?? [],
                            [$lastField => trim($match[2])]
                        );
                    }

                    continue;
                }
            }

            if (preg_match('/^(?:if|elif|else)\b/i', $trimmed) === 1) {
                $condition = self::normalizeCabalListItem($trimmed);
                $activeBranch = self::conditionalFieldBranchLabel($currentLabel, $condition, $lastCondition);
                $blocks[$currentKey][$activeBranch] ??= [];
                if (preg_match('/^else\b/i', $condition) !== 1) {
                    $lastCondition = $condition;
                }
                $conditionalIndent = $indent;
                $lastField = null;
                $lastFieldIndent = null;
            }
        }

        return $blocks;
    }

    private static function conditionalFieldBranchLabel(string $stanzaLabel, string $condition, ?string $previousCondition): string
    {
        if (preg_match('/^else\b/i', $condition) === 1 && $previousCondition !== null) {
            return $stanzaLabel . ': else after ' . $previousCondition;
        }

        return $stanzaLabel . ': ' . $condition;
    }

    /**
     * @param array<string, array{type:string, name:string, fields:array<string, string>, conditionals:list<string>}> $stanzas
     * @param array<string, array<string, array<string, string>>> $blocks
     * @param array<string, bool> $seen
     * @return array<string, array{sourceDirectories:list<string>, ghcOptions:list<string>, cppOptions:list<string>, buildDepends:list<string>, otherModules:list<string>, nativeSystemFields:list<string>}>
     */
    private static function resolveCabalStanzaConditionalFieldBlocks(string $key, array $stanzas, array $blocks, array $seen = []): array
    {
        if (!array_key_exists($key, $stanzas) || array_key_exists($key, $seen)) {
            return [];
        }

        $seen[$key] = true;
        $resolved = [];
        foreach (self::parseCabalImportNames($stanzas[$key]['fields']['import'] ?? '') as $importName) {
            foreach (self::resolveCabalStanzaConditionalFieldBlocks('common:' . $importName, $stanzas, $blocks, $seen) as $label => $fields) {
                $resolved[$label] = self::mergeNormalizedConditionalFields($resolved[$label] ?? [], $fields);
            }
        }

        foreach ($blocks[$key] ?? [] as $label => $fields) {
            $resolved[$label] = self::mergeNormalizedConditionalFields(
                $resolved[$label] ?? [],
                self::normalizeCabalConditionalFields($fields)
            );
        }

        return $resolved;
    }

    /**
     * @param array<string, string> $fields
     * @return array{sourceDirectories:list<string>, ghcOptions:list<string>, cppOptions:list<string>, buildDepends:list<string>, otherModules:list<string>, nativeSystemFields:list<string>}
     */
    private static function normalizeCabalConditionalFields(array $fields): array
    {
        $dependencyConstraints = self::extractCabalDependencyConstraints($fields['build-depends'] ?? '');
        $buildDepends = [];
        foreach (self::extractCabalDependencyNames($fields['build-depends'] ?? '') as $dependency) {
            $buildDepends[] = self::formatCabalSetupDependency($dependency, $dependencyConstraints[$dependency] ?? '');
        }

        return [
            'sourceDirectories' => self::splitWords($fields['hs-source-dirs'] ?? ''),
            'ghcOptions' => self::splitWords($fields['ghc-options'] ?? ''),
            'cppOptions' => self::splitWords($fields['cpp-options'] ?? ''),
            'buildDepends' => $buildDepends,
            'otherModules' => self::extractCabalModuleNames($fields['other-modules'] ?? ''),
            'nativeSystemFields' => self::unexpectedCabalNativeSystemFields(
                self::extractCabalNativeSystemFields($fields),
                []
            ),
        ];
    }

    /**
     * @param array<string, list<string>> $base
     * @param array<string, list<string>> $next
     * @return array<string, list<string>>
     */
    private static function mergeNormalizedConditionalFields(array $base, array $next): array
    {
        foreach (['sourceDirectories', 'ghcOptions', 'cppOptions', 'buildDepends', 'otherModules', 'nativeSystemFields'] as $field) {
            $base[$field] ??= [];
            $next[$field] ??= [];
        }

        foreach ($next as $field => $values) {
            foreach ($values as $value) {
                if (!in_array($value, $base[$field] ?? [], true)) {
                    $base[$field][] = $value;
                }
            }
        }

        return $base;
    }

    /**
     * @param array<string, array<string, list<string>>> $expected
     * @param array<string, array<string, list<string>>> $present
     * @return array{missing:array<string, list<string>>, unexpected:array<string, list<string>>}
     */
    private static function diffConditionalFieldClosure(array $expected, array $present): array
    {
        $missing = [];
        $unexpected = [];

        foreach ($expected as $label => $expectedFields) {
            $presentFields = $present[$label] ?? [];
            foreach ($expectedFields as $field => $expectedValues) {
                foreach ($expectedValues as $value) {
                    if (!in_array($value, $presentFields[$field] ?? [], true)) {
                        $missing[$label][] = self::formatConditionalFieldEntry($field, $value);
                    }
                }
            }
        }

        foreach ($present as $label => $presentFields) {
            $expectedFields = $expected[$label] ?? [];
            foreach ($presentFields as $field => $presentValues) {
                foreach ($presentValues as $value) {
                    if (!in_array($value, $expectedFields[$field] ?? [], true)) {
                        $unexpected[$label][] = self::formatConditionalFieldEntry($field, $value);
                    }
                }
            }
        }

        return [
            'missing' => $missing,
            'unexpected' => $unexpected,
        ];
    }

    private static function formatConditionalFieldEntry(string $field, string $value): string
    {
        return match ($field) {
            'sourceDirectories' => 'hs-source-dirs: ' . $value,
            'ghcOptions' => 'ghc-options: ' . $value,
            'cppOptions' => 'cpp-options: ' . $value,
            'buildDepends' => 'build-depends: ' . $value,
            'otherModules' => 'other-modules: ' . $value,
            default => $value,
        };
    }

    /**
     * @param array<string, array{type:string, name:string, fields:array<string, string>, conditionals:list<string>}> $stanzas
     * @param array<string, bool> $seen
     * @return array<string, string>
     */
    private static function resolveCabalStanzaFields(string $key, array $stanzas, array $seen = []): array
    {
        if (!array_key_exists($key, $stanzas) || array_key_exists($key, $seen)) {
            return [];
        }

        $seen[$key] = true;
        $fields = [];
        foreach (self::parseCabalImportNames($stanzas[$key]['fields']['import'] ?? '') as $importName) {
            $importFields = self::resolveCabalStanzaFields('common:' . $importName, $stanzas, $seen);
            $fields = self::mergeCabalFields($fields, $importFields);
        }

        return self::mergeCabalFields($fields, $stanzas[$key]['fields']);
    }

    /**
     * @param array<string, array{type:string, name:string, fields:array<string, string>, conditionals:list<string>}> $stanzas
     * @param array<string, bool> $seen
     * @return array{imports:list<string>, unresolved:list<string>}
     */
    private static function resolveCabalCommonImportClosure(string $key, array $stanzas, array $seen = []): array
    {
        if (!array_key_exists($key, $stanzas) || array_key_exists($key, $seen)) {
            return [
                'imports' => [],
                'unresolved' => [],
            ];
        }

        $seen[$key] = true;
        $imports = [];
        $unresolved = [];
        foreach (self::parseCabalImportNames($stanzas[$key]['fields']['import'] ?? '') as $importName) {
            if (!in_array($importName, $imports, true)) {
                $imports[] = $importName;
            }

            $importKey = 'common:' . $importName;
            if (!array_key_exists($importKey, $stanzas)) {
                if (!in_array($importName, $unresolved, true)) {
                    $unresolved[] = $importName;
                }
                continue;
            }

            $nested = self::resolveCabalCommonImportClosure($importKey, $stanzas, $seen);
            foreach ($nested['imports'] as $nestedImport) {
                if (!in_array($nestedImport, $imports, true)) {
                    $imports[] = $nestedImport;
                }
            }
            foreach ($nested['unresolved'] as $nestedImport) {
                if (!in_array($nestedImport, $unresolved, true)) {
                    $unresolved[] = $nestedImport;
                }
            }
        }

        return [
            'imports' => $imports,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * @param array<string, array{type:string, name:string, fields:array<string, string>, conditionals:list<string>}> $stanzas
     * @param array<string, bool> $seen
     * @return list<string>
     */
    private static function resolveCabalStanzaConditionals(string $key, array $stanzas, array $seen = []): array
    {
        if (!array_key_exists($key, $stanzas) || array_key_exists($key, $seen)) {
            return [];
        }

        $seen[$key] = true;
        $branches = [];
        foreach (self::parseCabalImportNames($stanzas[$key]['fields']['import'] ?? '') as $importName) {
            foreach (self::resolveCabalStanzaConditionals('common:' . $importName, $stanzas, $seen) as $branch) {
                if (!in_array($branch, $branches, true)) {
                    $branches[] = $branch;
                }
            }
        }

        $stanza = $stanzas[$key];
        $label = $stanza['type'] . ' ' . $stanza['name'];
        foreach ($stanza['conditionals'] as $condition) {
            $branch = $label . ': ' . $condition;
            if (!in_array($branch, $branches, true)) {
                $branches[] = $branch;
            }
        }

        return $branches;
    }

    /**
     * @return list<string>
     */
    private static function parseCabalImportNames(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $names = [];
        foreach (preg_split('/[\s,]+/', trim($raw)) ?: [] as $name) {
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param array<string, string> $base
     * @param array<string, string> $next
     * @return array<string, string>
     */
    private static function mergeCabalFields(array $base, array $next): array
    {
        foreach ($next as $field => $value) {
            if (in_array($field, array_merge(['build-depends', 'setup-depends', 'build-tool-depends', 'build-tools', 'exposed-modules', 'default-extensions', 'extensions', 'other-extensions', 'other-modules', 'autogen-modules', 'reexported-modules', 'mixins', 'extra-source-files', 'extra-doc-files', 'extra-tmp-files', 'data-files'], self::CABAL_NATIVE_SYSTEM_FIELDS, self::CABAL_MODULE_INTERFACE_FIELDS), true) && array_key_exists($field, $base) && $base[$field] !== '') {
                $base[$field] .= ",\n" . $value;
                continue;
            }

            if (in_array($field, ['ghc-options', 'cpp-options', 'hs-source-dirs', 'test-options', 'benchmark-options'], true) && array_key_exists($field, $base) && $base[$field] !== '') {
                $base[$field] .= "\n" . $value;
                continue;
            }

            $base[$field] = $value;
        }

        return $base;
    }

    private static function stripCabalLineComments(string $raw): string
    {
        $lines = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $lines[] = preg_replace('/(^|\s)--.*$/', '$1', $line) ?? $line;
        }

        return implode("\n", $lines);
    }

    private static function stripCabalOptionLineComments(string $raw): string
    {
        $lines = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $lines[] = preg_replace('/(^|\s)--(?:\s|$).*$/', '$1', $line) ?? $line;
        }

        return implode("\n", $lines);
    }

    private static function normalizeCabalProjectForUnconditionalAudit(string $raw): string
    {
        return self::stripCabalConditionalBlocks(self::stripCabalLineComments($raw));
    }

    private static function stripCabalConditionalBlocks(string $raw): string
    {
        $lines = [];
        $conditionalIndent = null;

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $trimmed = trim($line);
            $indent = strlen($line) - strlen(ltrim($line));

            if ($conditionalIndent !== null) {
                if ($trimmed === '') {
                    continue;
                }

                if ($indent > $conditionalIndent) {
                    continue;
                }

                if ($indent === $conditionalIndent && preg_match('/^(?:elif|else)\b/i', $trimmed) === 1) {
                    continue;
                }

                $conditionalIndent = null;
            }

            if (preg_match('/^(?:if|elif|else)\b/i', $trimmed) === 1) {
                $conditionalIndent = $indent;
                continue;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function extractCabalDependencyNames(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $dependencies = [];
        foreach (explode(',', str_replace("\n", ' ', $raw)) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\b/', $part, $match) === 1 && !in_array($match[1], $dependencies, true)) {
                $dependencies[] = $match[1];
            }
        }

        sort($dependencies);
        return $dependencies;
    }

    /**
     * @return array<string, string>
     */
    private static function extractCabalDependencyConstraints(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $constraints = [];
        foreach (explode(',', str_replace("\n", ' ', $raw)) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\b(.*?)$/', $part, $match) === 1) {
                $constraints[$match[1]] = self::normalizeCabalConstraint($match[2]);
            }
        }

        ksort($constraints);
        return $constraints;
    }

    private static function normalizeCabalConstraint(string $raw): string
    {
        return preg_replace('/\s+/', ' ', trim($raw)) ?? trim($raw);
    }

    private static function normalizeGhcToolVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        if (preg_match('/\b([0-9]+(?:\.[0-9]+){1,3})\b/', $version, $match) === 1) {
            return $match[1];
        }

        $version = trim($version);
        return $version === '' ? null : $version;
    }

    private static function normalizeCabalFlagBooleanValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $withoutComment = preg_replace('/\s+--.*$/', '', $value);
        $normalized = self::normalizeCabalListItem((string) $withoutComment);
        if ($normalized === '') {
            return null;
        }

        return match (strtolower($normalized)) {
            'true' => 'True',
            'false' => 'False',
            default => $normalized,
        };
    }

    /**
     * @return list<string>
     */
    private static function extractCabalMixinSpecs(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $mixins = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            if ($part !== '' && !in_array($part, $mixins, true)) {
                $mixins[] = $part;
            }
        }

        sort($mixins);
        return $mixins;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalBuildToolDepends(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $tools = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            if ($part !== '' && !in_array($part, $tools, true)) {
                $tools[] = $part;
            }
        }

        return $tools;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalBuildTools(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $tools = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            if ($part === '') {
                continue;
            }

            $items = preg_match('/[<>=:]/', $part) === 1 ? [$part] : self::splitWords($part);
            foreach ($items as $item) {
                if ($item !== '' && !in_array($item, $tools, true)) {
                    $tools[] = $item;
                }
            }
        }

        return $tools;
    }

    /**
     * @param list<string> $buildToolDepends
     * @param list<string> $buildTools
     * @return list<string>
     */
    private static function unexpectedCabalBuildTools(array $buildToolDepends, array $buildTools): array
    {
        $unexpected = [];
        foreach ($buildToolDepends as $tool) {
            $unexpected[] = 'build-tool-depends: ' . $tool;
        }
        foreach ($buildTools as $tool) {
            $unexpected[] = 'build-tools: ' . $tool;
        }

        return $unexpected;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalDefaultExtensions(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $extensions = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            foreach (self::splitWords($part) as $extension) {
                if ($extension !== '' && !in_array($extension, $extensions, true)) {
                    $extensions[] = $extension;
                }
            }
        }

        sort($extensions);
        return $extensions;
    }

    /**
     * @return list<string>
     */
    private static function splitCabalCommaList(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r", "\n"], ' ', $raw);
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($raw);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $raw[$offset];
            if ($char === '(') {
                $depth++;
                $current .= $char;
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $current .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = self::normalizeCabalListItem($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $parts[] = self::normalizeCabalListItem($current);

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private static function normalizeCabalListItem(string $raw): string
    {
        return preg_replace('/\s+/', ' ', trim($raw)) ?? trim($raw);
    }

    private static function joinCabalFieldValues(string ...$values): string
    {
        $present = [];
        foreach ($values as $value) {
            if (trim($value) !== '') {
                $present[] = $value;
            }
        }

        return implode(",\n", $present);
    }

    /**
     * @return list<string>
     */
    private static function extractCabalModuleNames(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $modules = [];
        foreach (preg_split('/[\s,]+/', str_replace("\n", ' ', trim($raw))) ?: [] as $module) {
            $module = trim($module);
            if ($module !== '' && preg_match('/^[A-Z][A-Za-z0-9_]*(?:\.[A-Z][A-Za-z0-9_]*)*$/', $module) === 1 && !in_array($module, $modules, true)) {
                $modules[] = $module;
            }
        }

        sort($modules);
        return $modules;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalReexportedModuleSpecs(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $modules = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            $simpleModules = self::splitSimpleCabalModuleList($part);
            $items = $simpleModules === [] ? [$part] : $simpleModules;
            foreach ($items as $module) {
                if ($module !== '' && !in_array($module, $modules, true)) {
                    $modules[] = $module;
                }
            }
        }

        sort($modules);
        return $modules;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<string>>
     */
    private static function extractCabalModuleInterfaceFields(array $fields): array
    {
        $moduleInterfaceFields = [];
        foreach (self::CABAL_MODULE_INTERFACE_FIELDS as $field) {
            $items = self::extractCabalModuleNames($fields[$field] ?? '');
            if ($items !== []) {
                $moduleInterfaceFields[$field] = $items;
            }
        }

        ksort($moduleInterfaceFields);
        return $moduleInterfaceFields;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalFileGlobs(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $patterns = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            foreach (self::splitWords($part) as $pattern) {
                if ($pattern !== '' && !in_array($pattern, $patterns, true)) {
                    $patterns[] = $pattern;
                }
            }
        }

        sort($patterns);
        return $patterns;
    }

    /**
     * @param array<string, string> $expected
     * @return array<string, list<string>>
     */
    private static function normalizeExpectedPackageFileGlobs(array $expected): array
    {
        $normalized = [];
        foreach ($expected as $packageFile => $raw) {
            $normalized[$packageFile] = self::extractCabalFileGlobs($raw);
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<string>>
     */
    private static function extractCabalNativeSystemFields(array $fields): array
    {
        $nativeFields = [];
        foreach (self::CABAL_NATIVE_SYSTEM_FIELDS as $field) {
            $items = self::extractCabalNativeSystemFieldItems($field, $fields[$field] ?? '');
            if ($items !== []) {
                $nativeFields[$field] = $items;
            }
        }

        ksort($nativeFields);
        return $nativeFields;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalNativeSystemFieldItems(string $field, string $raw): array
    {
        $optionsFields = ['ld-options', 'cc-options', 'cxx-options', 'ghc-prof-options', 'ghc-shared-options', 'ghcjs-options', 'asm-options', 'cmm-options', 'js-options', 'hsc2hs-options', 'c2hs-options'];
        $raw = in_array($field, $optionsFields, true)
            ? self::stripCabalOptionLineComments($raw)
            : self::stripCabalLineComments($raw);
        if (trim($raw) === '') {
            return [];
        }

        $items = [];
        if ($field === 'pkgconfig-depends') {
            foreach (self::splitCabalCommaList($raw) as $item) {
                if ($item !== '' && !in_array($item, $items, true)) {
                    $items[] = $item;
                }
            }
        } elseif (in_array($field, $optionsFields, true)) {
            foreach (self::splitWords($raw, false) as $item) {
                if ($item !== '' && !in_array($item, $items, true)) {
                    $items[] = $item;
                }
            }
        } else {
            foreach (self::splitCabalCommaList($raw) as $part) {
                foreach (self::splitWords($part) as $item) {
                    if ($item !== '' && !in_array($item, $items, true)) {
                        $items[] = $item;
                    }
                }
            }
        }

        sort($items);
        return $items;
    }

    /**
     * @param array<string, list<string>> $present
     * @param array<string, list<string>> $expected
     * @return list<string>
     */
    private static function unexpectedCabalNativeSystemFields(array $present, array $expected): array
    {
        $unexpected = [];
        foreach ($present as $field => $items) {
            $expectedItems = $expected[$field] ?? [];
            foreach ($items as $item) {
                if (!in_array($item, $expectedItems, true)) {
                    $unexpected[] = $field . ': ' . $item;
                }
            }
        }

        sort($unexpected);
        return $unexpected;
    }

    /**
     * @param array<string, list<string>> $present
     * @param array<string, list<string>> $expected
     * @return list<string>
     */
    private static function unexpectedCabalModuleInterfaceFields(array $present, array $expected): array
    {
        $unexpected = [];
        foreach ($present as $field => $items) {
            $expectedItems = $expected[$field] ?? [];
            foreach ($items as $item) {
                if (!in_array($item, $expectedItems, true)) {
                    $unexpected[] = $field . ': ' . $item;
                }
            }
        }

        sort($unexpected);
        return $unexpected;
    }

    /**
     * @return list<string>
     */
    private static function splitSimpleCabalModuleList(string $raw): array
    {
        $modules = self::splitWords($raw);
        if ($modules === []) {
            return [];
        }

        foreach ($modules as $module) {
            if (preg_match('/^[A-Z][A-Za-z0-9_]*(?:\.[A-Z][A-Za-z0-9_]*)*$/', $module) !== 1) {
                return [];
            }
        }

        return $modules;
    }

    /**
     * @return list<string>
     */
    private static function splitWords(string $raw, bool $stripComments = true): array
    {
        if ($stripComments) {
            $raw = self::stripCabalLineComments($raw);
        }
        $words = [];
        foreach (preg_split('/\s+/', trim(str_replace("\n", ' ', $raw))) ?: [] as $word) {
            if ($word !== '' && !in_array($word, $words, true)) {
                $words[] = $word;
            }
        }

        return $words;
    }

    private static function firstFieldValue(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $values = self::splitWords($raw);
        return $values[0] ?? null;
    }

    private static function cabalOptionalBooleanText(?string $raw): ?string
    {
        $value = self::firstFieldValue($raw);
        return $value === null ? null : strtolower($value);
    }

    private static function cabalBuildableState(?string $raw): ?bool
    {
        if ($raw === null) {
            return true;
        }

        $value = strtolower((string) self::firstFieldValue($raw));
        if (in_array($value, ['true', 'yes'], true)) {
            return true;
        }

        if (in_array($value, ['false', 'no'], true)) {
            return false;
        }

        return null;
    }

    private static function formatCabalBuildableState(?bool $state): string
    {
        if ($state === true) {
            return 'true';
        }

        if ($state === false) {
            return 'false';
        }

        return 'none';
    }

    private static function formatCabalSetupDependency(string $dependency, string $constraint): string
    {
        return $constraint === '' ? $dependency : $dependency . ' ' . $constraint;
    }

    /**
     * @param array<string, list<string>> $missingHeaders
     */
    private static function formatPackageIdentityFailures(array $missingHeaders): string
    {
        $parts = [];
        foreach ($missingHeaders as $packageFile => $fields) {
            $parts[] = $packageFile . ' (' . implode(', ', $fields) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, array{expected:string, actual:string|null}>> $mismatchedHeaders
     */
    private static function formatPackageIdentityMismatches(array $mismatchedHeaders): string
    {
        $parts = [];
        foreach ($mismatchedHeaders as $packageFile => $fields) {
            $fieldParts = [];
            foreach ($fields as $field => $state) {
                $fieldParts[] = $field . ' expected ' . $state['expected'] . ', found ' . ($state['actual'] ?? 'none');
            }
            $parts[] = $packageFile . ' (' . implode(', ', $fieldParts) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, list<string>> $failures
     */
    private static function formatTargetFailures(array $failures): string
    {
        $parts = [];
        foreach ($failures as $target => $items) {
            $parts[] = $target . ' (' . implode(', ', $items) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, list<string>>> $failures
     */
    private static function formatNestedTargetFailures(array $failures): string
    {
        $parts = [];
        foreach ($failures as $target => $groups) {
            $groupParts = [];
            foreach ($groups as $group => $items) {
                $groupParts[] = $group . ' (' . implode(', ', $items) . ')';
            }
            $parts[] = $target . ' (' . implode('; ', $groupParts) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string|null}}>> $mismatches
     */
    private static function formatPackageSourceRepositoryMismatches(array $mismatches): string
    {
        $parts = [];
        foreach ($mismatches as $packageFile => $repositories) {
            $repositoryParts = [];
            foreach ($repositories as $name => $state) {
                $actualType = $state['actual']['type'];
                $actualLocation = $state['actual']['location'];

                if ($actualType !== $state['expected']['type']) {
                    $repositoryParts[] = $name . '.type expected ' . $state['expected']['type'] . ', found ' . ($actualType ?? 'absent');
                }
                if ($actualLocation !== $state['expected']['location']) {
                    $repositoryParts[] = $name . '.location expected ' . $state['expected']['location'] . ', found ' . ($actualLocation ?? 'absent');
                }
            }
            $parts[] = $packageFile . ' (' . implode(', ', $repositoryParts) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, array{expected:string, actual:string}>> $failures
     */
    private static function formatTargetConstraintMismatches(array $failures): string
    {
        $parts = [];
        foreach ($failures as $target => $items) {
            $itemParts = [];
            foreach ($items as $dependency => $state) {
                $itemParts[] = $dependency . ' expected ' . $state['expected'] . ', found ' . ($state['actual'] === '' ? 'none' : $state['actual']);
            }
            $parts[] = $target . ' (' . implode(', ', $itemParts) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array{expected:string, actual:string}> $failures
     */
    private static function formatDependencyConstraintMismatches(array $failures): string
    {
        $parts = [];
        foreach ($failures as $dependency => $state) {
            $parts[] = $dependency . ' expected ' . $state['expected'] . ', found ' . ($state['actual'] === '' ? 'none' : $state['actual']);
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<string, list<string>> $missingFlags
     */
    private static function formatProjectFlagFailures(array $missingFlags): string
    {
        $parts = [];
        foreach ($missingFlags as $package => $flags) {
            $parts[] = $package . ' (' . implode(', ', $flags) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, array{expected:bool, actual:bool|null}>> $mismatchedFlags
     */
    private static function formatProjectFlagMismatches(array $mismatchedFlags): string
    {
        $parts = [];
        foreach ($mismatchedFlags as $package => $flags) {
            foreach ($flags as $flag => $state) {
                $parts[] = $package . ':' . $flag . ' expected ' . ($state['expected'] ? '+' : '-') . ', found ' . ($state['actual'] === true ? '+' : '-');
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, array<string, array{expected:string|null, actual:string|null}>>> $mismatchedFields
     */
    private static function formatPackageFlagFieldMismatches(array $mismatchedFields): string
    {
        $parts = [];
        foreach ($mismatchedFields as $package => $flags) {
            $fieldParts = [];
            foreach ($flags as $flag => $fields) {
                foreach ($fields as $field => $state) {
                    $fieldParts[] = $flag . '.' . $field
                        . ' expected ' . ($state['expected'] ?? 'absent')
                        . ', found ' . ($state['actual'] ?? 'absent');
                }
            }
            $parts[] = $package . ' (' . implode(', ', $fieldParts) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array{expected:string, actual:string}> $mismatchedConstraints
     */
    private static function formatProjectConstraintMismatches(array $mismatchedConstraints): string
    {
        $parts = [];
        foreach ($mismatchedConstraints as $package => $state) {
            $parts[] = $package . ' expected ' . $state['expected'] . ', found ' . $state['actual'];
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array{expected:string, actual:string|null}> $mismatches
     */
    private static function formatDefaultLanguageMismatches(array $mismatches): string
    {
        $parts = [];
        foreach ($mismatches as $target => $state) {
            $parts[] = $target . ' expected ' . $state['expected'] . ', found ' . ($state['actual'] ?? 'none');
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array{expected:string|null, actual:string|null}> $mismatches
     */
    private static function formatOptionalFieldMismatches(array $mismatches, string $field): string
    {
        $parts = [];
        foreach ($mismatches as $target => $state) {
            $parts[] = $target . ' ' . $field . ' expected ' . ($state['expected'] ?? 'absent') . ', found ' . ($state['actual'] ?? 'absent');
        }

        return implode('; ', $parts);
    }

    private static function filesystemArtifactKind(string $path): ?string
    {
        if (is_file($path)) {
            return 'file';
        }

        if (is_dir($path)) {
            return 'directory';
        }

        if (file_exists($path)) {
            return 'other';
        }

        return null;
    }

    /**
     * @param array<string, array{expected:string, actual:string}> $mismatches
     */
    private static function formatArtifactTypeMismatches(array $mismatches): string
    {
        $parts = [];
        foreach ($mismatches as $path => $state) {
            $parts[] = $path . ' expected ' . $state['expected'] . ', found ' . $state['actual'];
        }

        return implode('; ', $parts);
    }

    private static function cabalPlanArgumentPathPolicyViolation(string $argument): ?string
    {
        if (str_starts_with($argument, '--builddir=')) {
            return self::cabalPlanPathPolicyViolation(substr($argument, strlen('--builddir=')));
        }

        if (
            preg_match('#(?:^|=)(?:/|~|[A-Za-z]:[\\\\/])#', $argument) === 1
            || str_contains($argument, '$HOME')
            || str_contains($argument, '${HOME}')
            || str_contains($argument, '%USERPROFILE%')
        ) {
            return 'must not contain absolute or home-scoped paths';
        }
        if (
            $argument === 'dist-newstyle'
            || str_starts_with($argument, 'dist-newstyle/')
            || str_contains($argument, '/dist-newstyle')
        ) {
            return 'must not use Cabal default dist-newstyle paths';
        }

        return null;
    }

    private static function cabalPlanPathPolicyViolation(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return 'must not be empty';
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return 'must be relative, not absolute';
        }
        if (
            str_starts_with($path, '~')
            || str_contains($path, '$HOME')
            || str_contains($path, '${HOME}')
            || str_contains($path, '%USERPROFILE%')
        ) {
            return 'must not be home-scoped';
        }
        if (
            $path === 'dist-newstyle'
            || str_starts_with($path, 'dist-newstyle/')
            || str_contains($path, '/dist-newstyle')
        ) {
            return 'must not use Cabal default dist-newstyle paths';
        }
        if (preg_match('~(?:^|/)\.\.(?:/|$)~', $path) === 1) {
            return 'must not contain parent-directory traversal';
        }
        if (!str_starts_with($path, '.port-libs/pandoc-runner/')) {
            return 'must stay under .port-libs/pandoc-runner';
        }

        return null;
    }

    /**
     * @param array{missingCommands:list<string>, unexpectedCommands:list<string>, commandPolicyViolations:list<string>, workspacePolicyViolations:list<string>, commandWorkspaceMismatches:list<string>} $closure
     */
    private static function formatCabalPlanDescriptorFailures(array $closure): string
    {
        $parts = [];
        if ($closure['missingCommands'] !== []) {
            $parts[] = 'missing commands (' . implode(', ', $closure['missingCommands']) . ')';
        }
        if ($closure['unexpectedCommands'] !== []) {
            $parts[] = 'unexpected commands (' . implode(', ', $closure['unexpectedCommands']) . ')';
        }
        if ($closure['commandPolicyViolations'] !== []) {
            $parts[] = 'command policy (' . implode(', ', $closure['commandPolicyViolations']) . ')';
        }
        if ($closure['workspacePolicyViolations'] !== []) {
            $parts[] = 'workspace policy (' . implode(', ', $closure['workspacePolicyViolations']) . ')';
        }
        if ($closure['commandWorkspaceMismatches'] !== []) {
            $parts[] = 'command/workspace mismatch (' . implode(', ', $closure['commandWorkspaceMismatches']) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param list<string> $missingFiles
     * @param list<string> $missingTools
     * @param array{expectedStablePlanFiles:list<string>, present:array<string, array{sha256:string, bytes:int}>, missing:list<string>, wrongType:array<string, string>, emptyFiles:list<string>, invalidFiles:array<string, string>, unpinnedPlanRisk:bool} $planStabilityClosure
     * @param array{missingGhcVersions:list<string>, toolGhcVersionSupported:bool} $compilerTestedWithClosure
     * @param array{missingHeaders:array<string, list<string>>, mismatchedHeaders:array<string, array<string, array{expected:string, actual:string|null}>>} $packageIdentityClosure
     * @param array{unexpectedCustomSetupStanzas:array<string, list<string>>, unexpectedSetupDependencies:array<string, list<string>>} $packageSetupClosure
     * @param array{missingFlags:array<string, list<string>>, unexpectedFlags:array<string, list<string>>, mismatchedFlagFields:array<string, array<string, array<string, array{expected:string|null, actual:string|null}>>>} $packageFlagDefinitionClosure
     * @param array{missingDataFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>} $packageDataFileClosure
     * @param array{missingExtraDocFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, missingExtraSourceFiles:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>} $packageExtraFileClosure
     * @param array{unexpectedNativeSystemFields:array<string, list<string>>} $packageNativeSystemFieldClosure
     * @param array{missing:array<string, list<string>>, mismatched:array<string, array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string|null}}>>, unexpected:array<string, list<string>>, unexpectedFields:array<string, array<string, list<string>>>} $packageSourceRepositoryClosure
     * @param array{missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>} $projectPins
     * @param array{missing:list<string>, mismatched:array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string}>>, unexpected:list<string>, unexpectedFields:array<string, list<string>>} $projectSourceRepositoryClosure
     * @param array{missingPackages:list<string>, unexpectedPackages:list<string>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>, unexpectedFlags:array<string, list<string>>, unexpectedPackageFields:array<string, list<string>>} $projectPackageClosure
     * @param array{missingConstraints:list<string>, mismatchedConstraints:array<string, array{expected:string, actual:string}>, unexpectedConstraints:list<string>} $projectConstraintClosure
     * @param array{unexpectedFields:list<string>} $projectUnconditionalFieldClosure
     * @param array{missingBranches:list<string>, unexpectedBranches:list<string>} $projectConditionalBranchClosure
     * @param array{missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, unexpectedDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, unexpectedExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedSourceDirectories:array<string, list<string>>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, unexpectedTestOptions:array<string, list<string>>, unexpectedDefaultExtensions:array<string, list<string>>, unexpectedOtherExtensions:array<string, list<string>>, unexpectedCppOptions:array<string, list<string>>, unexpectedAutogenModules:array<string, list<string>>, unexpectedReexportedModules:array<string, list<string>>, unexpectedModuleInterfaceFields:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>, unexpectedConditionalBranches:array<string, list<string>>, unexpectedNativeSystemFields:array<string, list<string>>, missingOtherModules:array<string, list<string>>, unexpectedOtherModules:array<string, list<string>>} $runnerDependencyClosure
     * @param array{missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, unexpectedDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, unexpectedExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedSourceDirectories:array<string, list<string>>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, unexpectedBenchmarkOptions:array<string, list<string>>, unexpectedDefaultExtensions:array<string, list<string>>, unexpectedOtherExtensions:array<string, list<string>>, unexpectedCppOptions:array<string, list<string>>, unexpectedAutogenModules:array<string, list<string>>, unexpectedReexportedModules:array<string, list<string>>, unexpectedModuleInterfaceFields:array<string, list<string>>, unexpectedOtherModules:array<string, list<string>>, unexpectedExtraSourceFiles:array<string, list<string>>, unexpectedExtraDocFiles:array<string, list<string>>, unexpectedExtraTmpFiles:array<string, list<string>>, unexpectedDataFiles:array<string, list<string>>, unexpectedConditionalBranches:array<string, list<string>>, unexpectedNativeSystemFields:array<string, list<string>>} $benchmarkDependencyClosure
     * @param array{missingDependencies:list<string>, unexpectedDependencies:list<string>, missingExposedModules:list<string>, unexpectedExposedModules:list<string>, missingSourceDirectories:list<string>, unexpectedSourceDirectories:list<string>, missingOtherModules:list<string>, unexpectedOtherModules:list<string>, missingSourceArtifacts:list<string>, wrongTypeSourceArtifacts:array<string, array{expected:string, actual:string}>, emptySourceArtifacts:list<string>, mismatchedDefaultLanguage:array{expected:string, actual:string|null}|null, unexpectedMixins:list<string>, unexpectedBuildTools:list<string>, unexpectedAutogenModules:list<string>, unexpectedReexportedModules:list<string>, unexpectedModuleInterfaceFields:list<string>, unexpectedDefaultExtensions:list<string>, unexpectedOtherExtensions:list<string>, unexpectedExtraSourceFiles:list<string>, unexpectedExtraDocFiles:list<string>, unexpectedExtraTmpFiles:list<string>, unexpectedDataFiles:list<string>, unexpectedConditionalBranches:list<string>, unexpectedNativeSystemFields:list<string>} $luaEngineLibraryClosure
     * @param array{missingExecutable:bool, mismatchedEntryPoint:list<string>, missingDependencies:list<string>, unexpectedDependencies:list<string>, mismatchedDependencyConstraints:array<string, array{expected:string, actual:string}>, missingExecutableOptions:list<string>, unexpectedExecutableOptions:list<string>, mismatchedDefaultLanguage:array{expected:string, actual:string|null}|null, missingCommonImports:list<string>, unexpectedCommonImports:list<string>, unresolvedCommonImports:list<string>, missingSourceDirectories:list<string>, unexpectedSourceDirectories:list<string>, unexpectedMixins:list<string>, unexpectedBuildTools:list<string>, missingOtherExtensions:list<string>, unexpectedOtherExtensions:list<string>, unexpectedDefaultExtensions:list<string>, unexpectedCppOptions:list<string>, unexpectedAutogenModules:list<string>, unexpectedReexportedModules:list<string>, unexpectedModuleInterfaceFields:list<string>, missingOtherModules:list<string>, unexpectedOtherModules:list<string>, unexpectedExtraSourceFiles:list<string>, unexpectedExtraDocFiles:list<string>, unexpectedExtraTmpFiles:list<string>, unexpectedDataFiles:list<string>, missingConditionalBranches:list<string>, unexpectedConditionalBranches:list<string>, missingConditionalFieldEntries:array<string, list<string>>, unexpectedConditionalFieldEntries:array<string, list<string>>, unexpectedNativeSystemFields:list<string>} $cliExecutableClosure
     * @param array{missingTargets:list<string>, missingSemantics:array<string, list<string>>} $runnerEntrySourceClosure
     * @param array{missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>} $runnerArtifactClosure
     * @param array{missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, missingSemantics:array<string, list<string>>} $benchmarkArtifactClosure
     * @param array{missingTargets:list<string>, missingSemantics:array<string, list<string>>} $benchmarkEntrySourceClosure
     * @param array{missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>, missingSemantics:array<string, list<string>>} $formatRegistrySourceClosure
     * @param array{expectedCommands:list<string>, presentCommands:list<string>, missingCommands:list<string>, unexpectedCommands:list<string>, commandPolicyViolations:list<string>, workspacePolicyViolations:list<string>, commandWorkspaceMismatches:list<string>} $cabalPlanDescriptorClosure
     */
    private static function activationGate(array $missingFiles, array $missingTools, array $planStabilityClosure, array $compilerTestedWithClosure, array $packageIdentityClosure, array $packageSetupClosure, array $packageFlagDefinitionClosure, array $packageDataFileClosure, array $packageExtraFileClosure, array $packageNativeSystemFieldClosure, array $packageSourceRepositoryClosure, array $projectPins, array $projectSourceRepositoryClosure, array $projectPackageClosure, array $projectConstraintClosure, array $projectUnconditionalFieldClosure, array $projectConditionalBranchClosure, array $runnerDependencyClosure, array $benchmarkDependencyClosure, array $luaEngineLibraryClosure, array $serverLibraryClosure, array $cliExecutableClosure, array $runnerEntrySourceClosure, array $runnerArtifactClosure, array $benchmarkArtifactClosure, array $benchmarkEntrySourceClosure, array $formatRegistrySourceClosure, array $cabalPlanDescriptorClosure): string
    {
        $planStabilitySummary = $planStabilityClosure['unpinnedPlanRisk']
            ? 'capture cabal.project.freeze absence or invalidity as an unpinned-plan risk'
            : 'record stable cabal.project and cabal.project.freeze provenance';

        if (
            $missingFiles === []
            && $missingTools === []
            && $compilerTestedWithClosure['missingGhcVersions'] === []
            && $compilerTestedWithClosure['toolGhcVersionSupported'] === true
            && $packageIdentityClosure['missingHeaders'] === []
            && $packageIdentityClosure['mismatchedHeaders'] === []
            && $packageSetupClosure['unexpectedCustomSetupStanzas'] === []
            && $packageSetupClosure['unexpectedSetupDependencies'] === []
            && $packageFlagDefinitionClosure['missingFlags'] === []
            && $packageFlagDefinitionClosure['unexpectedFlags'] === []
            && $packageFlagDefinitionClosure['mismatchedFlagFields'] === []
            && $packageDataFileClosure['missingDataFiles'] === []
            && $packageDataFileClosure['unexpectedDataFiles'] === []
            && $packageExtraFileClosure['missingExtraDocFiles'] === []
            && $packageExtraFileClosure['unexpectedExtraDocFiles'] === []
            && $packageExtraFileClosure['missingExtraSourceFiles'] === []
            && $packageExtraFileClosure['unexpectedExtraSourceFiles'] === []
            && $packageExtraFileClosure['unexpectedExtraTmpFiles'] === []
            && $packageNativeSystemFieldClosure['unexpectedNativeSystemFields'] === []
            && $packageSourceRepositoryClosure['missing'] === []
            && $packageSourceRepositoryClosure['mismatched'] === []
            && $packageSourceRepositoryClosure['unexpected'] === []
            && $packageSourceRepositoryClosure['unexpectedFields'] === []
            && $projectPins['missing'] === []
            && $projectPins['mismatched'] === []
            && $projectSourceRepositoryClosure['missing'] === []
            && $projectSourceRepositoryClosure['mismatched'] === []
            && $projectSourceRepositoryClosure['unexpected'] === []
            && $projectSourceRepositoryClosure['unexpectedFields'] === []
            && $projectPackageClosure['missingPackages'] === []
            && $projectPackageClosure['unexpectedPackages'] === []
            && $projectPackageClosure['missingFlags'] === []
            && $projectPackageClosure['mismatchedFlags'] === []
            && $projectPackageClosure['unexpectedFlags'] === []
            && $projectPackageClosure['unexpectedPackageFields'] === []
            && $projectConstraintClosure['missingConstraints'] === []
            && $projectConstraintClosure['mismatchedConstraints'] === []
            && $projectConstraintClosure['unexpectedConstraints'] === []
            && $projectUnconditionalFieldClosure['unexpectedFields'] === []
            && $projectConditionalBranchClosure['missingBranches'] === []
            && $projectConditionalBranchClosure['unexpectedBranches'] === []
            && $runnerDependencyClosure['missingTargets'] === []
            && $runnerDependencyClosure['mismatchedEntryPoints'] === []
            && $runnerDependencyClosure['missingDependencies'] === []
            && $runnerDependencyClosure['unexpectedDependencies'] === []
            && $runnerDependencyClosure['mismatchedDependencyConstraints'] === []
            && $runnerDependencyClosure['missingExecutableOptions'] === []
            && $runnerDependencyClosure['unexpectedExecutableOptions'] === []
            && $runnerDependencyClosure['mismatchedDefaultLanguages'] === []
            && $runnerDependencyClosure['mismatchedManualFields'] === []
            && $runnerDependencyClosure['missingCommonImports'] === []
            && $runnerDependencyClosure['unexpectedCommonImports'] === []
            && $runnerDependencyClosure['unresolvedCommonImports'] === []
            && $runnerDependencyClosure['unexpectedSourceDirectories'] === []
            && $runnerDependencyClosure['unexpectedMixins'] === []
            && $runnerDependencyClosure['unexpectedBuildTools'] === []
            && $runnerDependencyClosure['unexpectedTestOptions'] === []
            && $runnerDependencyClosure['unexpectedDefaultExtensions'] === []
            && $runnerDependencyClosure['unexpectedOtherExtensions'] === []
            && $runnerDependencyClosure['unexpectedCppOptions'] === []
            && $runnerDependencyClosure['unexpectedAutogenModules'] === []
            && $runnerDependencyClosure['unexpectedReexportedModules'] === []
            && $runnerDependencyClosure['unexpectedModuleInterfaceFields'] === []
            && $runnerDependencyClosure['unexpectedExtraSourceFiles'] === []
            && $runnerDependencyClosure['unexpectedExtraDocFiles'] === []
            && $runnerDependencyClosure['unexpectedExtraTmpFiles'] === []
            && $runnerDependencyClosure['unexpectedDataFiles'] === []
            && $runnerDependencyClosure['unexpectedConditionalBranches'] === []
            && $runnerDependencyClosure['unexpectedNativeSystemFields'] === []
            && $runnerDependencyClosure['missingOtherModules'] === []
            && $runnerDependencyClosure['unexpectedOtherModules'] === []
            && $benchmarkDependencyClosure['missingTargets'] === []
            && $benchmarkDependencyClosure['mismatchedEntryPoints'] === []
            && $benchmarkDependencyClosure['missingDependencies'] === []
            && $benchmarkDependencyClosure['unexpectedDependencies'] === []
            && $benchmarkDependencyClosure['mismatchedDependencyConstraints'] === []
            && $benchmarkDependencyClosure['missingExecutableOptions'] === []
            && $benchmarkDependencyClosure['unexpectedExecutableOptions'] === []
            && $benchmarkDependencyClosure['mismatchedDefaultLanguages'] === []
            && $benchmarkDependencyClosure['mismatchedManualFields'] === []
            && $benchmarkDependencyClosure['missingCommonImports'] === []
            && $benchmarkDependencyClosure['unexpectedCommonImports'] === []
            && $benchmarkDependencyClosure['unresolvedCommonImports'] === []
            && $benchmarkDependencyClosure['unexpectedSourceDirectories'] === []
            && $benchmarkDependencyClosure['unexpectedMixins'] === []
            && $benchmarkDependencyClosure['unexpectedBuildTools'] === []
            && $benchmarkDependencyClosure['unexpectedBenchmarkOptions'] === []
            && $benchmarkDependencyClosure['unexpectedDefaultExtensions'] === []
            && $benchmarkDependencyClosure['unexpectedOtherExtensions'] === []
            && $benchmarkDependencyClosure['unexpectedCppOptions'] === []
            && $benchmarkDependencyClosure['unexpectedAutogenModules'] === []
            && $benchmarkDependencyClosure['unexpectedReexportedModules'] === []
            && $benchmarkDependencyClosure['unexpectedModuleInterfaceFields'] === []
            && $benchmarkDependencyClosure['unexpectedOtherModules'] === []
            && $benchmarkDependencyClosure['unexpectedExtraSourceFiles'] === []
            && $benchmarkDependencyClosure['unexpectedExtraDocFiles'] === []
            && $benchmarkDependencyClosure['unexpectedExtraTmpFiles'] === []
            && $benchmarkDependencyClosure['unexpectedDataFiles'] === []
            && $benchmarkDependencyClosure['unexpectedConditionalBranches'] === []
            && $benchmarkDependencyClosure['unexpectedNativeSystemFields'] === []
            && $luaEngineLibraryClosure['missingDependencies'] === []
            && $luaEngineLibraryClosure['unexpectedDependencies'] === []
            && $luaEngineLibraryClosure['missingExposedModules'] === []
            && $luaEngineLibraryClosure['unexpectedExposedModules'] === []
            && $luaEngineLibraryClosure['missingSourceDirectories'] === []
            && $luaEngineLibraryClosure['unexpectedSourceDirectories'] === []
            && $luaEngineLibraryClosure['missingOtherModules'] === []
            && $luaEngineLibraryClosure['unexpectedOtherModules'] === []
            && $luaEngineLibraryClosure['missingSourceArtifacts'] === []
            && $luaEngineLibraryClosure['wrongTypeSourceArtifacts'] === []
            && $luaEngineLibraryClosure['emptySourceArtifacts'] === []
            && $luaEngineLibraryClosure['mismatchedDefaultLanguage'] === null
            && $luaEngineLibraryClosure['unexpectedMixins'] === []
            && $luaEngineLibraryClosure['unexpectedBuildTools'] === []
            && $luaEngineLibraryClosure['unexpectedAutogenModules'] === []
            && $luaEngineLibraryClosure['unexpectedReexportedModules'] === []
            && $luaEngineLibraryClosure['unexpectedModuleInterfaceFields'] === []
            && $luaEngineLibraryClosure['unexpectedDefaultExtensions'] === []
            && $luaEngineLibraryClosure['unexpectedOtherExtensions'] === []
            && $luaEngineLibraryClosure['unexpectedExtraSourceFiles'] === []
            && $luaEngineLibraryClosure['unexpectedExtraDocFiles'] === []
            && $luaEngineLibraryClosure['unexpectedExtraTmpFiles'] === []
            && $luaEngineLibraryClosure['unexpectedDataFiles'] === []
            && $luaEngineLibraryClosure['unexpectedConditionalBranches'] === []
            && $luaEngineLibraryClosure['unexpectedNativeSystemFields'] === []
            && $serverLibraryClosure['missingDependencies'] === []
            && $serverLibraryClosure['unexpectedDependencies'] === []
            && $serverLibraryClosure['mismatchedDependencyConstraints'] === []
            && $serverLibraryClosure['missingExposedModules'] === []
            && $serverLibraryClosure['unexpectedExposedModules'] === []
            && $serverLibraryClosure['missingSourceDirectories'] === []
            && $serverLibraryClosure['unexpectedSourceDirectories'] === []
            && $serverLibraryClosure['mismatchedDefaultLanguage'] === null
            && $cliExecutableClosure['missingExecutable'] === false
            && $cliExecutableClosure['mismatchedEntryPoint'] === []
            && $cliExecutableClosure['missingDependencies'] === []
            && $cliExecutableClosure['unexpectedDependencies'] === []
            && $cliExecutableClosure['mismatchedDependencyConstraints'] === []
            && $cliExecutableClosure['missingExecutableOptions'] === []
            && $cliExecutableClosure['unexpectedExecutableOptions'] === []
            && $cliExecutableClosure['mismatchedDefaultLanguage'] === null
            && $cliExecutableClosure['missingCommonImports'] === []
            && $cliExecutableClosure['unexpectedCommonImports'] === []
            && $cliExecutableClosure['unresolvedCommonImports'] === []
            && $cliExecutableClosure['missingSourceDirectories'] === []
            && $cliExecutableClosure['unexpectedSourceDirectories'] === []
            && $cliExecutableClosure['unexpectedMixins'] === []
            && $cliExecutableClosure['unexpectedBuildTools'] === []
            && $cliExecutableClosure['missingOtherExtensions'] === []
            && $cliExecutableClosure['unexpectedOtherExtensions'] === []
            && $cliExecutableClosure['unexpectedDefaultExtensions'] === []
            && $cliExecutableClosure['unexpectedCppOptions'] === []
            && $cliExecutableClosure['unexpectedAutogenModules'] === []
            && $cliExecutableClosure['unexpectedReexportedModules'] === []
            && $cliExecutableClosure['unexpectedModuleInterfaceFields'] === []
            && $cliExecutableClosure['missingOtherModules'] === []
            && $cliExecutableClosure['unexpectedOtherModules'] === []
            && $cliExecutableClosure['unexpectedExtraSourceFiles'] === []
            && $cliExecutableClosure['unexpectedExtraDocFiles'] === []
            && $cliExecutableClosure['unexpectedExtraTmpFiles'] === []
            && $cliExecutableClosure['unexpectedDataFiles'] === []
            && $cliExecutableClosure['missingConditionalBranches'] === []
            && $cliExecutableClosure['unexpectedConditionalBranches'] === []
            && $cliExecutableClosure['missingConditionalFieldEntries'] === []
            && $cliExecutableClosure['unexpectedConditionalFieldEntries'] === []
            && $cliExecutableClosure['missingSourceArtifacts'] === []
            && $cliExecutableClosure['wrongTypeSourceArtifacts'] === []
            && $cliExecutableClosure['emptySourceArtifacts'] === []
            && $cliExecutableClosure['missingSourceSemantics'] === []
            && $cliExecutableClosure['unexpectedNativeSystemFields'] === []
            && $runnerEntrySourceClosure['missingTargets'] === []
            && $runnerEntrySourceClosure['missingSemantics'] === []
            && $runnerArtifactClosure['missing'] === []
            && $runnerArtifactClosure['wrongType'] === []
            && $runnerArtifactClosure['emptyFiles'] === []
            && $benchmarkArtifactClosure['missing'] === []
            && $benchmarkArtifactClosure['wrongType'] === []
            && $benchmarkArtifactClosure['emptyFiles'] === []
            && $benchmarkArtifactClosure['missingSemantics'] === []
            && $benchmarkEntrySourceClosure['missingTargets'] === []
            && $benchmarkEntrySourceClosure['missingSemantics'] === []
            && $formatRegistrySourceClosure['missing'] === []
            && $formatRegistrySourceClosure['wrongType'] === []
            && $formatRegistrySourceClosure['emptyFiles'] === []
            && $formatRegistrySourceClosure['missingSemantics'] === []
            && $cabalPlanDescriptorClosure['missingCommands'] === []
            && $cabalPlanDescriptorClosure['unexpectedCommands'] === []
            && $cabalPlanDescriptorClosure['commandPolicyViolations'] === []
            && $cabalPlanDescriptorClosure['workspacePolicyViolations'] === []
            && $cabalPlanDescriptorClosure['commandWorkspaceMismatches'] === []
        ) {
            return 'Hydrated Pandoc checkout, required Cabal toolchain, Cabal package identity/version headers, package flag definitions plus default/manual values for cabal.project flags, no unexpected Cabal package flag definitions, exact package-level source-repository head closure, exact package-level data-files closure for Pandoc templates/data payloads, exact package-level extra-doc-files and extra-source-files closure for documentation and source fixture globs, no unexpected package-level extra-tmp-files or native/system dependency fields, no package custom-setup/setup-depends hooks, pandoc.cabal tested-with GHC matrix, cabal.project package/flag/constraint closure, no unexpected cabal.project package entries or flags, no unexpected cabal.project package stanza fields, no unexpected cabal.project solver constraints, no unexpected cabal.project unconditional plan fields, no unexpected cabal.project conditional branches, exact cabal.project source-repository Git types and locations, no unexpected cabal.project source-repository packages, no unexpected cabal.project source-repository package fields, ' . $planStabilitySummary . ', non-empty runner source/golden fixtures with artifact hashes, runner entry-point source semantics including command-emulation parser/error handling and full Tasty group dispatch, buildable runner test-suite stanzas, exitcode-stdio runner types, exact runner and benchmark common import closure, no unresolved runner or benchmark common imports, direct build-depends with pinned version constraints, no unexpected runner or benchmark direct build-depends, exact runner and benchmark executable options, Haskell2010 default-language closure, exact absent runner and benchmark manual fields, no unexpected runner or benchmark hs-source-dirs, no unexpected runner or benchmark mixins, no runner or benchmark build-tool dependencies, no unexpected runner test-options, no unexpected benchmark-options, no unexpected runner or benchmark default-extensions, no unexpected runner or benchmark other-extensions, no unexpected runner or benchmark cpp-options, no unexpected runner or benchmark autogen-modules, no unexpected runner or benchmark reexported-modules, no unexpected runner or benchmark module interface fields, no unexpected runner or benchmark other-modules, no unexpected runner or benchmark extra-source-files, no unexpected runner or benchmark extra-doc-files, no unexpected runner or benchmark extra-tmp-files, no unexpected runner or benchmark data-files, no unexpected runner or benchmark conditional branches, no unexpected runner or benchmark native/system dependency fields, runner other-modules closure, exact pandoc-lua-engine library HsLua module dependency closure with exact exposed-modules, exact pandoc-lua-engine library source directory and other-modules closure, non-empty pandoc-lua-engine library source artifacts with artifact hashes, and Haskell2010 library default-language, no unexpected pandoc-lua-engine library Lua support build-depends, exposed modules, source directories, other modules, source artifacts, mixins or build-tool dependencies, generated modules, reexported modules, module interface fields, default/other extensions, file artifact globs, native/system dependency fields, or unexpected library conditional branches, exact pandoc-server library dependency, exposed-module, source-directory, and default-language closure, exact pandoc-cli executable entry point, common import, direct dependency, option, source-directory, extension, other-module, and known conditional-branch closure, exact pandoc-cli conditional branch field bodies, non-empty pandoc-cli conditional source artifacts with hashes and enabled/disabled shim source semantics, non-empty benchmark component dependency/artifact closure with artifact hashes, benchmark fixture semantics, benchmark entry-point source semantics, non-empty Pandoc format registry source artifacts with roff/manual reader, writer, and file-extension semantics, Git pins, exact Cabal dry-run command descriptors runner-test-dependencies and benchmark-dependencies, and a validated repo-local dry-run workspace are present; record a non-mutating solver/build plan before any Haskell runner or benchmark execution.';
        }

        return 'Hydrate Pandoc upstream commit ' . self::UPSTREAM_COMMIT
            . ' with Cabal package identity/version headers, package flag definitions plus default/manual values for cabal.project flags, no unexpected Cabal package flag definitions, exact package-level source-repository head closure, exact package-level data-files closure, no unexpected package-level data-files, exact package-level extra-doc-files and extra-source-files closure, no unexpected package-level extra-doc-files, extra-source-files, extra-tmp-files, or native/system dependency fields, no package custom-setup/setup-depends hooks, pandoc.cabal tested-with GHC matrix, cabal.project package entries/flags/constraints, no unexpected cabal.project package entries or flags, no unexpected cabal.project package stanza fields, no unexpected cabal.project solver constraints, no unexpected cabal.project unconditional plan fields, no unexpected cabal.project conditional branches, exact cabal.project source-repository Git types and locations, no unexpected cabal.project source-repository packages, no unexpected cabal.project source-repository package fields, stable Cabal plan file provenance or an explicit cabal.project.freeze unpinned-plan risk, pandoc.cabal, pandoc-lua-engine/pandoc-lua-engine.cabal, non-empty runner source/golden fixtures with artifact hashes, non-empty benchmark source/data artifacts with artifact hashes and fixture semantics, runner entry-point source semantics including command-emulation parser/error handling and full Tasty group dispatch, benchmark entry-point source semantics, non-empty Pandoc format registry source artifacts with roff/manual reader, writer, and file-extension semantics, buildable exitcode-stdio test-suite types and buildable benchmark components, Haskell2010 default-language closure, exact absent runner and benchmark manual fields, exact runner and benchmark common import closure, no unresolved runner or benchmark common imports, test entry points and benchmark entry points, direct runner build-depends and benchmark build-depends with pinned version constraints, no unexpected runner or benchmark direct build-depends, exact runner and benchmark executable options, no unexpected runner or benchmark hs-source-dirs, no unexpected runner or benchmark mixins, no runner or benchmark build-tool dependencies, no unexpected runner test-options, no unexpected benchmark-options, no unexpected runner or benchmark default-extensions, no unexpected runner or benchmark other-extensions, no unexpected runner or benchmark cpp-options, no unexpected runner or benchmark autogen-modules, no unexpected runner or benchmark reexported-modules, no unexpected runner or benchmark module interface fields, no unexpected runner or benchmark other-modules, no unexpected runner or benchmark extra-source-files, no unexpected runner or benchmark extra-doc-files, no unexpected runner or benchmark extra-tmp-files, no unexpected runner or benchmark data-files, no unexpected runner or benchmark conditional branches, no unexpected runner or benchmark native/system dependency fields, runner other-modules closure, exact pandoc-lua-engine library HsLua module dependency closure, exact pandoc-lua-engine library exposed-modules closure, exact pandoc-lua-engine library other-modules closure, non-empty pandoc-lua-engine library source artifacts with artifact hashes, Haskell2010 pandoc-lua-engine library default-language, no unexpected pandoc-lua-engine library Lua support build-depends, no unexpected pandoc-lua-engine library exposed modules, no unexpected pandoc-lua-engine library source directories, no unexpected pandoc-lua-engine library other modules, no unexpected pandoc-lua-engine library source artifacts, no unexpected pandoc-lua-engine library mixins or build-tool dependencies, no unexpected pandoc-lua-engine library generated, reexported, or module interface fields, no unexpected pandoc-lua-engine library default/other extensions, no unexpected pandoc-lua-engine library file artifact globs, no unexpected pandoc-lua-engine library native/system dependency fields, no unexpected pandoc-lua-engine library conditional branches, exact pandoc-server library dependency/exposed-module/source-directory/default-language closure, exact pandoc-cli executable entry point, common import, direct dependency, option, source-directory, extension, other-module, and known conditional-branch closure, exact pandoc-cli conditional branch field bodies, non-empty pandoc-cli conditional source artifacts with hashes and enabled/disabled shim source semantics, ghc, cabal, exact cabal.project Git source-repository pins, exact Cabal dry-run command descriptors runner-test-dependencies and benchmark-dependencies, and a validated repo-local dry-run workspace before attempting a runner plan.';
    }
}
