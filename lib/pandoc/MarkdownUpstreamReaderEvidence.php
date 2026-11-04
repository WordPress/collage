<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-markdown-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-markdown-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-markdown-root';
    public const CHECKED_IN_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures';
    public const EXPECTED_SELECTED_FIXTURE_COUNT = 137;
    public const EXPECTED_NATIVE_MAPPED_PAIR_COUNT = 137;
    public const EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256 = '643f8150af43ced9329acc44dc0cdff6d5e22a301e05ce3068d54c2282dea023';

    private const SOURCE_FILES = [
        'test/Tests/Readers/Markdown.hs',
        'src/Text/Pandoc/Readers/Markdown.hs',
    ];
    private const RUNNER_TEST_SUITE = 'test:test-pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/markdown-targeted-run';
    private const RUNNER_TASTY_GROUP_PATH = ['Readers', 'Markdown'];
    private const RUNNER_TASTY_PATTERN = '$2 == "Readers" && $3 == "Markdown"';
    private const RUNNER_RESULT_ARTIFACT_KIND = 'upstream-markdown-reader-runner-result-artifact';
    private const RUNNER_TRANSCRIPT_KIND = 'upstream-markdown-reader-runner-transcript';
    private const RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION = 2;
    private const RUNNER_REQUIRED_TRANSCRIPTS = [
        '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
        '.port-libs/pandoc-runner/logs/markdown-targeted-list-tests.txt',
        '.port-libs/pandoc-runner/logs/markdown-targeted-run.txt',
    ];
    private const RUNNER_REQUIRED_ARTIFACTS = [
        '.port-libs/pandoc-runner/artifacts/markdown-targeted-run/result.json',
    ];

    private const CHECKED_IN_MARKDOWN_FIXTURES = [
        'upstream-command-parse-raw.md' => [
            'role' => 'command-parse-raw-reader-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/parse-raw.md',
            'formatProfile' => 'markdown raw_attribute/raw_html/raw_tex',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-parse-raw.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderParseRawFixtureCompletionTest.php',
            ],
            'sha256' => 'e3b50f56f86883e3e323cf97d52cd07a3c3797fb7d5f89bbb422392e8008f72b',
            'bytes' => 379,
        ],
        'upstream-command-md-abbrevs.md' => [
            'role' => 'command-markdown-abbreviation-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/md-abbrevs.md',
            'formatProfile' => 'markdown',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-md-abbrevs.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAbbreviationCommandFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownReaderTest.php',
            ],
            'sha256' => 'e27c636cb9c5663201d84c2558a8f99320704b408c47bfa458eccebe73998d14',
            'bytes' => 398,
        ],
        'upstream-command-details-summary.md' => [
            'role' => 'command-details-summary-raw-html-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/details-summary.md',
            'formatProfile' => 'markdown raw_html/gfm',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-details-summary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTest.php',
            ],
            'sha256' => 'bd279e57d0cad59c8c7b9651f58fee3e763cb822af97ec34323144ea4fa0955c',
            'bytes' => 188,
        ],
        'upstream-command-gfm-details-list.md' => [
            'role' => 'command-gfm-details-list-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/gfm-details-list.md',
            'formatProfile' => 'gfm',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-gfm-details-list.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGfmDetailsListFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownReaderTest.php',
            ],
            'sha256' => 'ac68a5f4067d14d96e92bb45b950d171a808e25f848c33cc67cd8ef55e73ed9d',
            'bytes' => 107,
        ],
        'upstream-markdown-angle-autolinks.md' => [
            'role' => 'markdown-angle-autolink-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected angle autolink coverage',
            'formatProfile' => 'markdown bare_uris/autolink_bare_uris',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-angle-autolinks.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAngleAutolinkFixtureCompletionTest.php',
            ],
            'sha256' => '203f0f64b99105e28b37ebd229ba6d8a6284aa41a1a9f17bdaf5aa3acfbb5836',
            'bytes' => 164,
        ],
        'upstream-markdown-inline-note-citations.md' => [
            'role' => 'markdown-inline-note-citation-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected inline note citation coverage',
            'formatProfile' => 'markdown citations/footnotes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-note-citations.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php',
            ],
            'sha256' => '041671cee998c4a9e27d3fb6af07f5db13ed8ee73ce8cc3112274dd6cc46fbcd',
            'bytes' => 142,
        ],
        'upstream-markdown-citation-span-boundary.md' => [
            'role' => 'markdown-citation-span-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected citation/span boundary coverage',
            'formatProfile' => 'markdown citations/bracketed_spans/native_spans',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-citation-span-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderCitationSpanBoundaryCompletionTest.php',
            ],
            'sha256' => '4a9c744c4eef5597fcd1c178fd756b18ee78e70e57230689c564d2f695bef6d1',
            'bytes' => 84,
        ],
        'upstream-markdown-line-blocks.md' => [
            'role' => 'markdown-line-block-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected line block extension coverage',
            'formatProfile' => 'markdown line_blocks/commonmark_x/gfm profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-line-blocks.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLineBlockProfileSurgeTest.php',
            ],
            'sha256' => '7a175df8a9934d4e50567ba25b1736df404f704740dc8671ea455e8910d4681c',
            'bytes' => 38,
        ],
        'upstream-markdown-task-list.md' => [
            'role' => 'markdown-task-list-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected task list extension coverage',
            'formatProfile' => 'markdown task_lists/commonmark_x/gfm profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-task-list.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTaskListProfileSurgeTest.php',
            ],
            'sha256' => '2631c0b4e1bbaa22fe4e13f8da163f37feb68c6a0c4fb4d8185402b43407611d',
            'bytes' => 108,
        ],
        'upstream-command-empty-paragraphs.md' => [
            'role' => 'command-empty-paragraphs-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/empty-paragraphs.md',
            'formatProfile' => 'native/html/docx empty_paragraphs',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-empty-paragraphs.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTest.php',
            ],
            'sha256' => '3cec1e6ab0a690ebe90035bd1a71453c755b6ff198283b8651c8161c20983314',
            'bytes' => 1800,
        ],
        'upstream-markdown-definition-lists.md' => [
            'role' => 'markdown-definition-list-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/no blank space',
            'formatProfile' => 'markdown definition_lists',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-lists.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '233fac188307a5ed3eeaa321c45322e221637c4deaf8c52626af41161c2aaec0',
            'bytes' => 38,
        ],
        'upstream-markdown-definition-list-blank-first.md' => [
            'role' => 'markdown-definition-list-blank-first-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/blank space before first def',
            'formatProfile' => 'markdown definition_lists',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-blank-first.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '2df49ee09f7e0538c7f2c3fff6ccea40a7a5d7caeeca954aa67a87f785be2970',
            'bytes' => 40,
        ],
        'upstream-markdown-definition-list-blank-second.md' => [
            'role' => 'markdown-definition-list-blank-second-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/blank space before second def',
            'formatProfile' => 'markdown definition_lists',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-blank-second.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '41f9f906efbc3be2cd34fdf1ff854dc4e94ac257e3a467a0f466cf387a9142d2',
            'bytes' => 39,
        ],
        'upstream-markdown-github-wikilinks.md' => [
            'role' => 'markdown-github-wikilink-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown Github wiki links',
            'formatProfile' => 'markdown_github wikilinks_title_before_pipe',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-github-wikilinks.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGithubWikiLinkFixtureCompletionTest.php',
            ],
            'sha256' => '6f0ec576210ab97db42c4e7facdde2a34edfdb34eabc7198fc19367729892b3f',
            'bytes' => 151,
        ],
        'upstream-markdown-inline-code-list-markers.md' => [
            'role' => 'markdown-inline-code-list-marker-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown inline code in lists (#6284) selected marker literal cases',
            'formatProfile' => 'markdown inline code/list markers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-code-list-markers.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineCodeListMarkerCompletionTest.php',
            ],
            'sha256' => '66bbbbf31d775879a2df0a391f044b95241598d42a48a583148224664c50fcef',
            'bytes' => 132,
        ],
        'upstream-markdown-backslash-escaped-links.md' => [
            'role' => 'markdown-backslash-escaped-link-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown backslash escapes/in URL, in title, in reference link title, and in reference link URL',
            'formatProfile' => 'markdown escaped link destinations/titles',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-backslash-escaped-links.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBackslashEscapedLinkFixtureCompletionTest.php',
            ],
            'sha256' => '6ad9984a92f484095874487a5fd713e18489f8216829e1e6f7c9137beb2e5216',
            'bytes' => 110,
        ],
        'upstream-markdown-definition-list-nested-list.md' => [
            'role' => 'markdown-definition-list-nested-list-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/list in definition',
            'formatProfile' => 'markdown definition_lists nested list body',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-nested-list.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => 'fec7e3095c4cd2c98514e86f9dd6ab35106ee9fc9fffdfdb80116bc60bd7f8e7',
            'bytes' => 14,
        ],
        'upstream-markdown-definition-list-html-div.md' => [
            'role' => 'markdown-definition-list-html-div-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/definition list inside html div',
            'formatProfile' => 'markdown definition_lists raw_html html block body',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-html-div.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '8addb5b1c8253a8c5d4019e5d86c16ce335e8bd7409cd3ea54bfddd42dd2c4af',
            'bytes' => 26,
        ],
        'upstream-markdown-link-label-boundaries.md' => [
            'role' => 'markdown-link-label-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown links/no autolink inside link, no inline link inside link, and no bare URI inside link',
            'formatProfile' => 'markdown link label boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-link-label-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLinkLabelBoundaryFixtureCompletionTest.php',
            ],
            'sha256' => '261c0f00e439b4cdf7cdac0748ec16fa55760d79eb5feeef29baf0e373b36e86',
            'bytes' => 76,
        ],
        'upstream-markdown-unbalanced-brackets.md' => [
            'role' => 'markdown-unbalanced-bracket-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown unbalanced brackets',
            'formatProfile' => 'markdown bracket literal boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-unbalanced-brackets.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderUnbalancedBracketFixtureCompletionTest.php',
            ],
            'sha256' => 'ad87edba0a8dc59fc7fb9f90885cd2203e1e4fae65e6a2feaa20cdb059d3ce5c',
            'bytes' => 15,
        ],
        'upstream-markdown-link-title-entities.md' => [
            'role' => 'markdown-link-title-entity-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown entities/in link title',
            'formatProfile' => 'markdown link title entity decoding',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-link-title-entities.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLinkTitleEntityFixtureTest.php',
            ],
            'sha256' => 'c9dc6a34cf99ca078667ee8f6c860a3fd0fc533cc6bef09982871ff7c52dc186',
            'bytes' => 41,
        ],
        'upstream-markdown-inline-code-attribute.md' => [
            'role' => 'markdown-inline-code-attribute-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown inline code/with attribute',
            'formatProfile' => 'markdown inline_code_attributes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-code-attribute.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineCodeAttributeFixtureTest.php',
            ],
            'sha256' => 'c66ef2aad2940a3c5dd08b69f66f2fb3418dd8e13403904ff625c9a9ed721033',
            'bytes' => 40,
        ],
        'upstream-markdown-inline-code-attribute-space.md' => [
            'role' => 'markdown-inline-code-attribute-space-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown inline code/with attribute space',
            'formatProfile' => 'markdown inline_code_attributes spacing boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-code-attribute-space.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineCodeAttributeSpaceFixtureTest.php',
            ],
            'sha256' => '9ebec08cb14463ce3612095b9e3be58869b95cd111cd4a1ab43fd462894aef58',
            'bytes' => 30,
        ],
        'upstream-markdown-character-references.md' => [
            'role' => 'markdown-character-reference-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown entities/character references',
            'formatProfile' => 'markdown entity decoding',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-character-references.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderCharacterReferenceFixtureTest.php',
            ],
            'sha256' => '21c98a8e50f0dc8b4ee6fe323df335c944aca0b5c452c2db0809b47e2fd6aa6d',
            'bytes' => 14,
        ],
        'upstream-markdown-strikeout.md' => [
            'role' => 'markdown-strikeout-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown strikeout inline markup',
            'formatProfile' => 'markdown strikeout nested emph',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-strikeout.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderStrikeoutFixtureTest.php',
            ],
            'sha256' => '30c0bc85dc189577486880cb6f9a135d77b420b091bf6396f31d4a1f985cddcb',
            'bytes' => 25,
        ],
        'upstream-markdown-emoji-symbols.md' => [
            'role' => 'markdown-emoji-symbol-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown emoji/emoji symbols',
            'formatProfile' => 'markdown_github emoji shortcodes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-emoji-symbols.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderEmojiFixtureTest.php',
            ],
            'sha256' => '90eb7db9d0f39fb87c9caa96f9f02d6facd390d39e3e82f59724883e2cc1c32b',
            'bytes' => 17,
        ],
        'upstream-markdown-superscript-subscript.md' => [
            'role' => 'markdown-superscript-subscript-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected superscript/subscript inline markup coverage',
            'formatProfile' => 'markdown superscript/subscript escaped-space boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-superscript-subscript.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderScriptFixtureTest.php',
            ],
            'sha256' => 'bf1a4d320f780ab971cfa70deff5beaf835d738f3f5aab9f7c33def0c2e24efe',
            'bytes' => 199,
        ],
        'upstream-markdown-smart-punctuation.md' => [
            'role' => 'markdown-smart-punctuation-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected smart punctuation coverage',
            'formatProfile' => 'markdown smart quotes/apostrophes/ellipsis',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-smart-punctuation.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php',
            ],
            'sha256' => '4bb6eabecef549ae4b8e3f29c7a7f956d7d1e2a24f157cb42e7c10a97fbb0fb3',
            'bytes' => 135,
        ],
        'upstream-markdown-smart-inline-note-quotes.md' => [
            'role' => 'markdown-smart-inline-note-quote-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown smart punctuation/single quote in inline note within single quotes',
            'formatProfile' => 'markdown+smart inline note quote nesting',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-smart-inline-note-quotes.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php',
            ],
            'sha256' => '7ac90d3c784c265f70c9cb1593a06afe196acaec58e62660144c8657213164e2',
            'bytes' => 14,
        ],
        'upstream-markdown-pipe-table-escaped-cell.md' => [
            'role' => 'markdown-pipe-table-escaped-cell-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected pipe table alignment and escaped pipe cell coverage',
            'formatProfile' => 'markdown pipe_tables escaped cell delimiter',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-pipe-table-escaped-cell.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderPipeTableFixtureCompletionTest.php',
            ],
            'sha256' => '11abcb5c5ff0e2815bc3abcfabbc5fcd38e19fb2be4ac32104b3e4c554d13516',
            'bytes' => 76,
        ],
        'upstream-markdown-fenced-div.md' => [
            'role' => 'markdown-fenced-div-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected fenced div native_divs coverage',
            'formatProfile' => 'markdown fenced_divs/native_divs nested container',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-fenced-div.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderFencedDivFixtureCompletionTest.php',
            ],
            'sha256' => '55f2e8b78f0447326a3cd1574f8622a91d1f3fa8e595b83204eb70adb4e089c2',
            'bytes' => 106,
        ],
        'upstream-markdown-header-attributes.md' => [
            'role' => 'markdown-header-attribute-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected header_attributes explicit id/class/key coverage',
            'formatProfile' => 'markdown header_attributes implicit header reference',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-header-attributes.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderHeaderAttributeFixtureTest.php',
            ],
            'sha256' => '69a74cf0b29a2821c37d2c8b08791c168691de424cf70bb9b8e5802ea4fa0520',
            'bytes' => 80,
        ],
        'upstream-markdown-numbered-examples.md' => [
            'role' => 'markdown-numbered-example-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected numbered_examples labeled reference coverage',
            'formatProfile' => 'markdown numbered_examples labeled cross-reference',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-numbered-examples.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderNumberedExampleFixtureCompletionTest.php',
            ],
            'sha256' => '8b249e3e2d1a4c4995eb28150c84bb4c77166d9dfb97de0b448e90388e7e8fc9',
            'bytes' => 39,
        ],
        'upstream-markdown-mark.md' => [
            'role' => 'markdown-mark-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected mark extension nested inline coverage',
            'formatProfile' => 'markdown mark nested strong inline',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-mark.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderMarkFixtureCompletionTest.php',
            ],
            'sha256' => 'b8c09f9af30b7896e4721d9eada0dfb9cf06c29989eff78cb6ae3e8221a2b4f7',
            'bytes' => 36,
        ],
        'upstream-markdown-bracketed-spans.md' => [
            'role' => 'markdown-bracketed-span-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected bracketed_spans generic Span and smallcaps coverage',
            'formatProfile' => 'markdown bracketed_spans/smallcaps attributes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-bracketed-spans.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBracketedSpanFixtureCompletionTest.php',
            ],
            'sha256' => '04316f1a9913cf1614ae0fcbf3e493a07456fbe5a7d80f9558dda665bf347456',
            'bytes' => 147,
        ],
        'upstream-markdown-fenced-code-attributes.md' => [
            'role' => 'markdown-fenced-code-attribute-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected fenced code block attributes coverage',
            'formatProfile' => 'markdown fenced_code_attributes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-fenced-code-attributes.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderFencedCodeAttributeFixtureCompletionTest.php',
            ],
            'sha256' => '6f09b188dded819552fc8a2297abafbfd0d158d0d238bcaf325a93ec766d8b30',
            'bytes' => 78,
        ],
        'upstream-markdown-mmd-short-scripts.md' => [
            'role' => 'markdown-mmd-short-script-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown sub- and superscripts/short subscript and short superscript delimiter coverage',
            'formatProfile' => 'markdown_mmd short superscript/subscript delimiter boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-mmd-short-scripts.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderMmdShortScriptFixtureCompletionTest.php',
            ],
            'sha256' => '51ad1c2f928c09fe555f260e30579499bdea5d1b34941f1760b3c07a787d03d4',
            'bytes' => 100,
        ],
        'upstream-markdown-numeric-character-references.md' => [
            'role' => 'markdown-numeric-character-reference-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown entities/numeric',
            'formatProfile' => 'markdown numeric entity decoding',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-numeric-character-references.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderNumericCharacterReferenceFixtureCompletionTest.php',
            ],
            'sha256' => '73238d01c18d759c2af0440d38c928593d2511e5d07090487639a50d056df12c',
            'bytes' => 18,
        ],
        'upstream-markdown-footnote-definitions.md' => [
            'role' => 'markdown-footnote-definition-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown footnotes/recursive note',
            'formatProfile' => 'markdown footnotes recursive reference boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-footnote-definitions.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderFootnoteDefinitionFixtureCompletionTest.php',
            ],
            'sha256' => '4e11531363fafbdd59e3c1cd99f37e0162340827819b667ecea1b859f5ca5bd4',
            'bytes' => 21,
        ],
        'upstream-markdown-escaped-line-break.md' => [
            'role' => 'markdown-escaped-line-break-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown hard line breaks/backslash escaped line break',
            'formatProfile' => 'markdown escaped_line_breaks hard line break',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-escaped-line-break.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderEscapedLineBreakFixtureCompletionTest.php',
            ],
            'sha256' => '227f9cf35e3cdba7f00821c2a4c1e3dc7914cf5e74ae2c59a9e49aae616d2303',
            'bytes' => 12,
        ],
        'upstream-markdown-implicit-header-references.md' => [
            'role' => 'markdown-implicit-header-reference-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown implicit header references/ATX header with trailing #s',
            'formatProfile' => 'markdown implicit_header_references atx trailing hash trim',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-implicit-header-references.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderImplicitHeaderReferenceFixtureCompletionTest.php',
            ],
            'sha256' => '0a7eaf250ae086961351c6c684f26aa3b63caf4abe743fa29f2325c6d653f904',
            'bytes' => 46,
        ],
        'upstream-markdown-emph-strong-boundaries.md' => [
            'role' => 'markdown-emph-strong-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown emph and strong/two strongs in emph, emph and strong emph alternating, emph with spaced strong, and intraword underscore with opening underscore (#1121)',
            'formatProfile' => 'markdown emph/strong delimiter nesting and intraword underscore',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-emph-strong-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderEmphStrongBoundaryFixtureCompletionTest.php',
            ],
            'sha256' => 'dacb0085f517373fa21e84028a7433a2315f90fca6eda8500d393a5783b06bf9',
            'bytes' => 84,
        ],
        'upstream-markdown-raw-latex-bare-begin.md' => [
            'role' => 'markdown-raw-latex-bare-begin-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown raw LaTeX/in URL',
            'formatProfile' => 'markdown raw_tex bare environment command literal boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-raw-latex-bare-begin.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderRawLatexBareBeginFixtureCompletionTest.php',
            ],
            'sha256' => 'f4aa0601ed6885d2a2bd06e9502564322ba0ee4e687501995fa00c1481f98d8a',
            'bytes' => 7,
        ],
        'upstream-markdown-figure-latex-placement.md' => [
            'role' => 'markdown-figure-latex-placement-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown figures/latex placement',
            'formatProfile' => 'markdown implicit_figures/link_attributes latex-placement alt boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-figure-latex-placement.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderFigureLatexPlacementFixtureCompletionTest.php',
            ],
            'sha256' => '3840aacf3395bbee84846e39c378749f32b386d09dc3bfd02348c524577dcb56',
            'bytes' => 59,
        ],
        'upstream-markdown-raw-email-address.md' => [
            'role' => 'markdown-github-raw-email-address-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown raw email addresses/issue 2940',
            'formatProfile' => 'markdown_github raw email address strong boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-raw-email-address.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderRawEmailAddressFixtureCompletionTest.php',
            ],
            'sha256' => 'd75b7a8bd91fde01fc8e7aea25c1f124c0a70af523af13330b641757655788ec',
            'bytes' => 10,
        ],
        'upstream-markdown-footnote-continuation-boundaries.md' => [
            'role' => 'markdown-footnote-continuation-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown footnotes/indent followed by newline and flush-left or indented text',
            'formatProfile' => 'markdown footnotes indented continuation and blank-line termination',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-footnote-continuation-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderFootnoteContinuationBoundaryFixtureCompletionTest.php',
            ],
            'sha256' => 'bcd100bebcaa3c2d7e1e51df1a3e72cebbbc93760b6d903039ee149d0153640f',
            'bytes' => 78,
        ],
        'upstream-markdown-heading-boundaries.md' => [
            'role' => 'markdown-heading-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown Headers/blank line before header, bracketed text (#2062), and setext header',
            'formatProfile' => 'markdown ATX/setext heading boundaries and implicit identifiers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-heading-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderHeadingBoundaryFixtureCompletionTest.php',
            ],
            'sha256' => '6497aa032094a74bfdb6cc714e4f922dfc3a29c14b1196bb84337f201caf1f52',
            'bytes' => 44,
        ],
        'upstream-markdown-raw-html-invalid-comment.md' => [
            'role' => 'markdown-raw-html-invalid-comment-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown raw HTML/technically invalid comment',
            'formatProfile' => 'markdown raw_html html comment boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-raw-html-invalid-comment.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderRawHtmlInvalidCommentFixtureCompletionTest.php',
            ],
            'sha256' => 'd2e5f74952fd26fd316d646bc360d421b533233e05620ba08afe784f4c17cafa',
            'bytes' => 23,
        ],
        'upstream-markdown-raw-html-invalid-tag.md' => [
            'role' => 'markdown-raw-html-invalid-tag-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown raw HTML/invalid tag (issue #1820)',
            'formatProfile' => 'markdown raw_html invalid tag literal boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-raw-html-invalid-tag.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderRawHtmlInvalidTagBoundaryCompletionTest.php',
            ],
            'sha256' => 'c981cea993a23dc23358c1d17fdda03abc7ed9b95f0fdd721beb0629dcba891a',
            'bytes' => 15,
        ],
        'upstream-markdown-raw-html-nesting.md' => [
            'role' => 'markdown-raw-html-nesting-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown raw HTML/nesting (issue #1330)',
            'formatProfile' => 'markdown raw_html inline nesting split into raw blocks and parsed text',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-raw-html-nesting.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderRawHtmlNestingFixtureCompletionTest.php',
            ],
            'sha256' => '0e02bd68029985d4aa7eb46ecd54335afa80af300626cf32fbe23756dc764f7b',
            'bytes' => 16,
        ],
        'upstream-markdown-yaml-metadata.md' => [
            'role' => 'markdown-yaml-metadata-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected yaml_metadata_block coverage',
            'formatProfile' => 'markdown yaml_metadata_block metadata blocks/list/scalar body boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-yaml-metadata.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderYamlMetadataFixtureCompletionTest.php',
            ],
            'sha256' => '5f69d57ef44116f63721edde6e0f164d3388f692e0fb9359bdc8ea35261e3376',
            'bytes' => 142,
        ],
        'upstream-markdown-definition-list-tight-bodies.md' => [
            'role' => 'markdown-definition-list-tight-body-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/laziness, first line not indented, and no blank space before first of two paragraphs',
            'formatProfile' => 'markdown definition_lists tight Plain body blocks and lazy SoftBreak continuation',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-tight-bodies.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => '58eb007d7f3dac48da8c992622e5a29defd68c7a84cdabb29b81ac4e218df924',
            'bytes' => 62,
        ],
        'upstream-markdown-lhs-inverse-bird-html.md' => [
            'role' => 'markdown-lhs-inverse-bird-html-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown lhs/inverse bird tracks and html',
            'formatProfile' => 'markdown+lhs literate_haskell bird/inverse code and implicit div close',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-lhs-inverse-bird-html.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLiterateHaskellFixtureCompletionTest.php',
            ],
            'sha256' => 'f08f6db28a623c0f60dbe069e68567e38f7ecbf71367f01eebfa52c2d6735ce0',
            'bytes' => 16,
        ],
        'upstream-markdown-alerts.md' => [
            'role' => 'markdown-alerts-reader-profile-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 gfm/commonmark_x alerts extension profile probe',
            'formatProfile' => 'gfm alerts blockquote-to-div profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-alerts.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAlertProfileCompletionTest.php',
            ],
            'sha256' => 'd4f826212c99ace92b25f414db142d565fa7b737ff6c0cbabb4010d5cf1f7b29',
            'bytes' => 107,
        ],
        'upstream-markdown-strict-compact-heading.md' => [
            'role' => 'markdown-strict-compact-atx-heading-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown_strict -space_in_atx_header compact ATX profile probe',
            'formatProfile' => 'markdown_strict compact ATX heading without auto identifier',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-strict-compact-heading.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderStrictCompactHeadingFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownReaderAtxHeadingSpaceProfileSurgeTest.php',
            ],
            'sha256' => '7631fb35c6f86b29590e5a339c7c14abc67cb65adc8557efffef6f69485eb0b4',
            'bytes' => 4,
        ],
        'upstream-markdown-z-commonmark-x-grid-table-default.md' => [
            'role' => 'markdown-commonmark-x-grid-table-default-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 commonmark_x default grid_tables-disabled profile probe',
            'formatProfile' => 'commonmark_x grid_tables disabled by default, pipe_tables still enabled',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-commonmark-x-grid-table-default.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTableProfileSurgeTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '412a732f5c23e980d34d1be6b014030f06ff723439ba06740804fe1d52a946a1',
            'bytes' => 50,
        ],
        'upstream-markdown-autolink-attributes.md' => [
            'role' => 'markdown-autolink-attribute-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown autolinks/with some attributes and with some attributes and spaces',
            'formatProfile' => 'markdown angle autolink raw_attribute boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-autolink-attributes.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAutolinkAttributeFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownReaderTest.php',
            ],
            'sha256' => '1e53b4ffdeab43731a3909f53ffd8d4f44d2560d5355b7e490ef2f19376c2052',
            'bytes' => 64,
        ],
        'upstream-markdown-z-fancy-list-markers.md' => [
            'role' => 'markdown-fancy-list-marker-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+fancy_lists ordered marker profile probe',
            'formatProfile' => 'markdown+fancy_lists upper-alpha, upper-roman, and parenthesized decimal ordered markers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-fancy-list-markers.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderFancyListFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownReaderListMarkerProfileSurgeTest.php',
            ],
            'sha256' => 'c3d8db151d6eed0603f2e3774c031b405a5ca8b64d69574f3feb590df1e62d21',
            'bytes' => 71,
        ],
        'upstream-markdown-z-hard-line-break-profile.md' => [
            'role' => 'markdown-hard-line-break-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+hard_line_breaks profile probe',
            'formatProfile' => 'markdown+hard_line_breaks physical paragraph newlines as LineBreak constructors',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-hard-line-break-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderHardLineBreakProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '4fdbc441ea7b546100e086ac1e4fc5ae6749b7314311c99db05be450eca12996',
            'bytes' => 17,
        ],
        'upstream-markdown-inline-math.md' => [
            'role' => 'markdown-inline-tex-math-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown inline TeX math profile probe',
            'formatProfile' => 'markdown tex_math_dollars inline math',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-math.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownReaderRawMathProfileMatrixSurgeTest.php',
            ],
            'sha256' => '364f852f91e3d11943ffa83ae6cd717f3b9ae38a2c61100fe135e95d4bf9180a',
            'bytes' => 36,
        ],
        'upstream-markdown-ascii-identifiers.md' => [
            'role' => 'markdown-ascii-identifiers-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+ascii_identifiers heading ID and implicit-reference profile probe',
            'formatProfile' => 'markdown+ascii_identifiers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-ascii-identifiers.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAsciiIdentifierProfileCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '37abcb0679639cce00173e8737b95b0c76da7a3f2b6bc3790bccbd2790abf232',
            'bytes' => 156,
        ],
        'upstream-markdown-z-phpextra-profile.md' => [
            'role' => 'markdown-phpextra-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown_phpextra profile probe',
            'formatProfile' => 'markdown_phpextra header_attributes/link_attributes/definition_lists/footnotes defaults',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-phpextra-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderPhpExtraProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '83e7b30e00869c6ef685979df5fa075d3e4bb2bc988d0e615ea584b6374f5347',
            'bytes' => 120,
        ],
        'upstream-markdown-z-simple-table-profile.md' => [
            'role' => 'markdown-simple-table-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+simple_tables profile probe',
            'formatProfile' => 'markdown+simple_tables header/body table constructor profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-simple-table-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderSimpleTableProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'dead898102ace731504514d4a7babbc466ed7ed3e402ba0cba752a4c610689d9',
            'bytes' => 39,
        ],
        'upstream-markdown-z-short-subsuperscript-profile.md' => [
            'role' => 'markdown-short-subsuperscript-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+short_subsuperscripts profile probe',
            'formatProfile' => 'markdown+short_subsuperscripts short subscript/superscript profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-short-subsuperscript-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderShortSubsuperscriptProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '749be67c41a587eefa87c24129d84369e3fa3a10d9cc299fb709e51d3818e33a',
            'bytes' => 29,
        ],
        'upstream-markdown-z-spaced-reference-link-profile.md' => [
            'role' => 'markdown-spaced-reference-link-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-shortcut_reference_links+spaced_reference_links profile probe',
            'formatProfile' => 'markdown-shortcut_reference_links+spaced_reference_links spaced reference labels with shortcut links disabled',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-spaced-reference-link-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderSpacedReferenceLinkProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '1a8864629a497067a58f7e12bdb6a56005779f92f8219d705e9ef488e6bd9bab',
            'bytes' => 127,
        ],
        'upstream-markdown-z-tex-math-double-backslash-profile.md' => [
            'role' => 'markdown-double-backslash-tex-math-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+tex_math_double_backslash profile probe',
            'formatProfile' => 'markdown+tex_math_double_backslash inline/display math delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-tex-math-double-backslash-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDoubleBackslashMathProfileFixtureCompletionTest.php',
            ],
            'sha256' => '9b0d34f3b6a66f40771940859a979cc06da95e3fc7deb439907edac34a2a484b',
            'bytes' => 40,
        ],
        'upstream-markdown-zz-east-asian-line-break-profile.md' => [
            'role' => 'markdown-east-asian-line-break-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+east_asian_line_breaks profile probe',
            'formatProfile' => 'markdown+east_asian_line_breaks joins East Asian soft line boundaries only',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zz-east-asian-line-break-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderEastAsianLineBreakProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'bfa1c21a376998bbdf364121bcaa093db875c9e415c0d40908a38182922f2871',
            'bytes' => 20,
        ],
        'upstream-markdown-zz-tex-math-single-backslash-profile.md' => [
            'role' => 'markdown-single-backslash-tex-math-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+tex_math_single_backslash profile probe',
            'formatProfile' => 'markdown+tex_math_single_backslash inline/display math delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zz-tex-math-single-backslash-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderSingleBackslashMathProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'f02128f194b2d78319520340497fe7bc3f195a7f030bd59838091275769f918f',
            'bytes' => 36,
        ],
        'upstream-markdown-zzz-intraword-underscore-profile.md' => [
            'role' => 'markdown-intraword-underscore-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-intraword_underscores profile probe',
            'formatProfile' => 'markdown-intraword_underscores intraword emphasis/strong profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzz-intraword-underscore-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderIntrawordUnderscoreProfileCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '23bc30dbf0fb9813f0cf0addfd364c788ce80f56203bf1ca2f69f7f5752fec8d',
            'bytes' => 30,
        ],
        'upstream-markdown-z-lists-without-preceding-blankline-profile.md' => [
            'role' => 'markdown-lists-without-preceding-blankline-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+lists_without_preceding_blankline profile probe',
            'formatProfile' => 'markdown+lists_without_preceding_blankline paragraph-interrupting bullet and non-one ordered list markers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-lists-without-preceding-blankline-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderListsWithoutPrecedingBlanklineProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '4de2a3ad30fc94cb41a4d00e9f004d07fde543dacd892f5c4559812afe118521',
            'bytes' => 47,
        ],
        'upstream-markdown-zzzz-angle-brackets-escapable-profile.md' => [
            'role' => 'markdown-angle-brackets-escapable-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-all_symbols_escapable+angle_brackets_escapable profile probe',
            'formatProfile' => 'markdown-all_symbols_escapable+angle_brackets_escapable angle-only escape profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzz-angle-brackets-escapable-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAngleBracketEscapableProfileCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '1693f0d6344c29946c29918167e5197d7631207e72cb1000f16b14d355904b6b',
            'bytes' => 12,
        ],
        'upstream-markdown-zzzzz-wikilinks-title-after-pipe-profile.md' => [
            'role' => 'markdown-wikilinks-title-after-pipe-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+wikilinks_title_after_pipe profile probe',
            'formatProfile' => 'markdown+wikilinks_title_after_pipe target-before-pipe/title-after-pipe wikilink profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzz-wikilinks-title-after-pipe-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderWikiLinkDirectionCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '57fd7294b0f6f88a4190b23b68771dc5a111d3b9bcc7aa6d000be987cce9fa52',
            'bytes' => 45,
        ],
        'upstream-markdown-zzzzzz-ignore-line-breaks-profile.md' => [
            'role' => 'markdown-ignore-line-breaks-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+ignore_line_breaks profile probe',
            'formatProfile' => 'markdown+ignore_line_breaks ordinary physical newlines ignored while explicit hard-break markers survive',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzz-ignore-line-breaks-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLineBreakProfileSurgeTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'de6e28f373e2f47e1e603f8e9bf80abb7fa9d13fdfe210f9125bd4f052e4ebdc',
            'bytes' => 30,
        ],
        'upstream-markdown-zzzzzzz-auto-identifiers-disabled-profile.md' => [
            'role' => 'markdown-auto-identifiers-disabled-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-auto_identifiers generated heading ID suppression profile probe',
            'formatProfile' => 'markdown-auto_identifiers generated heading IDs disabled while heading text remains parsed',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzz-auto-identifiers-disabled-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAutoIdentifierProfileCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'd5699a975a6717d34a5ec0d06d4b852a3201be0726d90ac01b10e552754ed926',
            'bytes' => 19,
        ],
        'upstream-markdown-zzzzzzzz-blank-before-header-disabled-profile.md' => [
            'role' => 'markdown-blank-before-header-disabled-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-blank_before_header paragraph-interrupting ATX heading profile probe',
            'formatProfile' => 'markdown-blank_before_header paragraph-interrupting ATX heading with implicit header reference',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzz-blank-before-header-disabled-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '153bfcf8342792293c62211ee56b1af9f717e80dd74df85e634f4db18877a580',
            'bytes' => 40,
        ],
        'upstream-markdown-zzzzzzzzz-citation-digit-key.md' => [
            'role' => 'markdown-citation-digit-key-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown citations/key starts with digit',
            'formatProfile' => 'markdown citations digit-leading key',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzz-citation-digit-key.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderCitationDigitKeyFixtureCompletionTest.php',
            ],
            'sha256' => 'ed4794c48dd0f70f0bb11a32c8b2148259ddc3366c5fafa72db9ed97c02b74a8',
            'bytes' => 15,
        ],
        'upstream-markdown-zzzzzzzzzz-bare-uri-bracket-encoding.md' => [
            'role' => 'markdown-bare-uri-bracket-encoding-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bare URIs/Sprite square- and curly-bracket target encoding',
            'formatProfile' => 'markdown+autolink_bare_uris square and curly bracket target percent-encoding',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzz-bare-uri-bracket-encoding.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriBracketEncodingFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'bf3c3167f8df54bff120fe833bd61bbe0db2ed036a9f4e6befdbe178aaaf9702',
            'bytes' => 113,
        ],
        'upstream-markdown-zzzzzzzzzzz-mmd-title-block-profile.md' => [
            'role' => 'markdown-mmd-title-block-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown_mmd mmd_title_block profile probe',
            'formatProfile' => 'markdown_mmd mmd_title_block metadata removed from body',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzz-mmd-title-block-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderMmdTitleBlockProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'ae9b323e06416495765aa0a6f06a3b51c0892744cf842fe6bcdc4287bff41f5a',
            'bytes' => 70,
        ],
        'upstream-markdown-zzzzzzzzzzzz-gfm-auto-identifiers-profile.md' => [
            'role' => 'markdown-gfm-auto-identifiers-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 commonmark+gfm_auto_identifiers+ascii_identifiers profile probe',
            'formatProfile' => 'commonmark+gfm_auto_identifiers+ascii_identifiers punctuation stripping, ASCII folding, dash fallback, and duplicate suffixing',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzz-gfm-auto-identifiers-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGfmAutoIdentifierProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '24fe9261edac6c433bd1add99f394033bda6ad3a8721798e28032ebf00187be8',
            'bytes' => 91,
        ],
        'upstream-markdown-zzzzzzzzzzzzz-citation-link-boundaries.md' => [
            'role' => 'markdown-citation-link-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown footnote/link following citation (#2083)',
            'formatProfile' => 'markdown citations with following footnote, inline link, reference link, shortcut reference link, implicit header reference, and suffix boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzz-citation-link-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderCitationLinkBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '61c35e0cc522237de7afc0ceb3eedf50716dbab3b654c3bfef996196527a469c',
            'bytes' => 178,
        ],
        'upstream-markdown-zzzzzzzzzzzzzz-footnote-fenced-div-boundary.md' => [
            'role' => 'markdown-footnote-fenced-div-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown footnote definition body boundary for same-line and indented fenced divs',
            'formatProfile' => 'markdown footnotes plus fenced_divs/native_divs same-line literal and indented Div boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzz-footnote-fenced-div-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderFootnoteFencedDivBoundaryCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '1899054fb7e7d232092d3a4b96b83d804f0a4c66ded8c370431f04ce46394775',
            'bytes' => 153,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzz-raw-html-list-boundary.md' => [
            'role' => 'markdown-raw-html-list-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown lists/issue #1154',
            'formatProfile' => 'markdown raw_html/native_divs list continuation boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzz-raw-html-list-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderRawHtmlListBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'cbfbf9fca325dc0888cf2ed788e87f9b498d8cc76ce4c9b07ed325b6007fc5a2',
            'bytes' => 131,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzz-gfm-nested-list-continuation.md' => [
            'role' => 'markdown-gfm-nested-list-continuation-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown lists/issue #1636',
            'formatProfile' => 'gfm nested list continuation under prior bullet item',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzz-gfm-nested-list-continuation.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGfmNestedListContinuationFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'ba07e4cc5bd3d93801b0146b1f6244fab8658024aebdb0024b1cab825d48b8ed',
            'bytes' => 20,
        ],
        'upstream-markdown-ordered-task-list.md' => [
            'role' => 'markdown-ordered-task-list-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+task_lists ordered task list profile probe',
            'formatProfile' => 'markdown+task_lists ordered task markers with loose continuation paragraph',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-ordered-task-list.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTaskListProfileSurgeTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '5b58b38b74cae72cec4b7ef630dca1498ad190e96d4ec3941256b3649e31a424',
            'bytes' => 84,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzz-blank-before-blockquote-disabled-profile.md' => [
            'role' => 'markdown-blank-before-blockquote-disabled-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-blank_before_blockquote paragraph-interrupting block quote profile probe',
            'formatProfile' => 'markdown-blank_before_blockquote top-level and nested paragraph-interrupting block quote profile behavior',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzz-blank-before-blockquote-disabled-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '8a4291b15fe0e64bebd8dbdd3654a358d661f899f1dfcba7be6e60d34c0f4f76',
            'bytes' => 51,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzz-blank-before-header-blockquote-profile.md' => [
            'role' => 'markdown-blank-before-header-blockquote-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-blank_before_header blockquote-contained heading implicit-reference profile probe',
            'formatProfile' => 'markdown-blank_before_header blockquote-contained paragraph-interrupting heading and implicit header reference',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzz-blank-before-header-blockquote-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBlankBeforeBlockBoundaryProfileTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '5424e779dd141e369b8a3618ba776adc9965ca590ae67126ec8454c89184ea04',
            'bytes' => 44,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzz-mark-extension-profile.md' => [
            'role' => 'markdown-mark-extension-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+mark profile probe',
            'formatProfile' => 'markdown+mark highlighted inline Span with nested strong content',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzz-mark-extension-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderMarkExtensionProfileCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'b8c09f9af30b7896e4721d9eada0dfb9cf06c29989eff78cb6ae3e8221a2b4f7',
            'bytes' => 36,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzz-angle-autolink-unicode-dash-boundary.md' => [
            'role' => 'markdown-angle-autolink-unicode-dash-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown autolinks/with unicode dash following',
            'formatProfile' => 'markdown angle autolink followed by Unicode dash boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzz-angle-autolink-unicode-dash-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAngleAutolinkResidualTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '6e30b22ae36c55d1fa851d76124bbe27f9439f2e541919e2e711a71c8d4c3071',
            'bytes' => 20,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzz-partial-autolink-boundary.md' => [
            'role' => 'markdown-partial-autolink-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown autolinks/a partial URL (#2277)',
            'formatProfile' => 'markdown angle autolink partial www URL literal boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzz-partial-autolink-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAngleAutolinkResidualTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '781de064172e9944bb03b648b31f917e91d8bd51be8d128e62cdd313c1fc6409',
            'bytes' => 51,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzz-gfm-raw-html-split-angle-boundary.md' => [
            'role' => 'markdown-gfm-raw-html-split-angle-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown raw HTML/issue 2469',
            'formatProfile' => 'gfm raw HTML split angle literal paragraph boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzz-gfm-raw-html-split-angle-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGfmRawHtmlSplitAngleBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '0aff578e385aeec2e57c71ec2f38ce81370718f6d22351ba0bdee3db7e7d3b7c',
            'bytes' => 6,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzz-bare-uri-scheme-boundaries.md' => [
            'role' => 'markdown-bare-uri-scheme-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bare URIs selected scheme, trailing punctuation, and literal http-token boundaries',
            'formatProfile' => 'markdown+autolink_bare_uris uppercase/doi/mailto scheme and trailing punctuation boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzz-bare-uri-scheme-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriSchemeBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'eab76a84dbaa0c8c93b841c15a8b7cf4e29285b1b206311b9e3fa0ac04d94a0b',
            'bytes' => 101,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzz-tex-math-dollar-display-boundary.md' => [
            'role' => 'markdown-tex-math-dollar-display-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown tex_math_dollars display math boundary',
            'formatProfile' => 'markdown tex_math_dollars standalone display math',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzz-tex-math-dollar-display-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineMathFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '4cd6d7a97debf74bcd4580ee94fb067a8d7eea429d74562d5ab1a3204837f1b5',
            'bytes' => 45,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzz-raw-html-inline-commonmark-profile.md' => [
            'role' => 'markdown-commonmark-raw-attribute-inline-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 commonmark+raw_attribute raw HTML inline profile probe',
            'formatProfile' => 'commonmark+raw_attribute raw HTML inline constructor',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzz-raw-html-inline-commonmark-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderRawCodeProfileOverrideSurgeTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '4f5b81222a650acf3c9cc2367c3b0638e122e53c7647275528ae3d5aed370a2a',
            'bytes' => 40,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzz-atx-heading-space-disabled-profile.md' => [
            'role' => 'markdown-atx-heading-space-disabled-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-space_in_atx_header compact attributed ATX heading profile probe',
            'formatProfile' => 'markdown-space_in_atx_header compact attributed ATX heading',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzz-atx-heading-space-disabled-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAtxHeadingSpaceProfileSurgeTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '413d41252fef624ef193fa4175f527d27f0eac5f7cd23c216d4d1afac2901b11',
            'bytes' => 26,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzz-startnum-disabled-profile.md' => [
            'role' => 'markdown-startnum-disabled-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-startnum+fancy_lists ordered-list start number profile probe',
            'formatProfile' => 'markdown-startnum+fancy_lists ordered-list start-number suppression',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzz-startnum-disabled-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderStartnumProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '0393af1be681455314c76c66916a7d262ff1bbfddc323cba8bdf04001fc2a6a0',
            'bytes' => 36,
        ],
        'upstream-markdown-inline-code-list-marker-generated-boundaries.md' => [
            'role' => 'markdown-inline-code-list-marker-generated-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown inline code in lists (#6284) generated newline and blank-indent list marker cases',
            'formatProfile' => 'markdown inline code/list marker generated boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-inline-code-list-marker-generated-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderInlineCodeListMarkerCompletionTest.php',
            ],
            'sha256' => '8556aa8110eadfdde51dbf8e8e2afb293acf254118b27ef3b099453d0ee2f74f',
            'bytes' => 681,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-in-html-blocks-profile.md' => [
            'role' => 'markdown-in-html-blocks-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected markdown_in_html_blocks coverage',
            'formatProfile' => 'markdown+markdown_in_html_blocks container block with nested Markdown content',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-in-html-blocks-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderMarkdownInHtmlBlocksFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'eea6a5f1a4b9fef9e314a6c18a1edee0bbc2cfc66b01186d61414bc7248d938d',
            'bytes' => 108,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-attribute-profile.md' => [
            'role' => 'markdown-attribute-html-block-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected markdown_attribute coverage',
            'formatProfile' => 'markdown_phpextra markdown_attribute container block with nested Markdown content',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-attribute-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderMarkdownInHtmlBlocksFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'dfe0b021428a827a66dcb79aab83ba4ecdfbdebc6aebdd5f28aab915911057b9',
            'bytes' => 121,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-bare-uri-query-boundaries.md' => [
            'role' => 'markdown-bare-uri-query-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bare URIs/Try this query, Sprite parenthesized path, and query plus fragment selected cases',
            'formatProfile' => 'markdown+autolink_bare_uris query-string, parenthesized path, and fragment boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-bare-uri-query-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriQueryBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '2d251db0ec3147f60db5580984f4c7dc5a18eedc8f03f6e8814f4e7c2d4e3e57',
            'bytes' => 183,
        ],
        'upstream-markdown-bare-uri-query-hyphen-boundaries.md' => [
            'role' => 'markdown-bare-uri-query-hyphen-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bare URIs/trailing and leading hyphen query anchor cases',
            'formatProfile' => 'markdown+autolink_bare_uris query values ending and beginning with hyphen',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-bare-uri-query-hyphen-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriQueryBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '161499a458db1013329ddd59fe20e37cafa71947c55beb3a7adffc99a7eb7309',
            'bytes' => 69,
        ],
        'upstream-markdown-bare-uri-port-path-boundaries.md' => [
            'role' => 'markdown-bare-uri-port-path-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bare URIs/port and plus-in-path cases',
            'formatProfile' => 'markdown+autolink_bare_uris port and plus path boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-bare-uri-port-path-boundaries.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriQueryBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '2785c81b2106589d4e877f6ddd13157c9035fc2a8c6b1e757073e555bc85a852',
            'bytes' => 82,
        ],
        'upstream-markdown-smart-inline-note-double-quotes.md' => [
            'role' => 'markdown-smart-inline-note-double-quote-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown smart punctuation/double quote in inline note within double quotes',
            'formatProfile' => 'markdown+smart inline note double-quote nesting',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-smart-inline-note-double-quotes.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '5bd2d9572cd5b3956d61c659cb060d34616c0194c620762c064f2784cae2eeb0',
            'bytes' => 14,
        ],
        'upstream-command-11589-attributed-superscript.md' => [
            'role' => 'command-markdown-attributed-superscript-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/11589.md',
            'formatProfile' => 'markdown superscript/bracketed_spans/inline attributes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-11589-attributed-superscript.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderAttributedSuperscriptFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '7e6fca282949f7571d555d7c58cc85d7434874a7b7774807cec0f03cb8623df4',
            'bytes' => 44,
        ],
        'upstream-markdown-reader-more-grid-table-spans.md' => [
            'role' => 'markdown-reader-more-grid-table-span-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'test/markdown-reader-more.txt grid table cells spanning multiple rows or columns and complex header',
            'formatProfile' => 'markdown+grid_tables row-span, column-span, and complex-header profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-reader-more-grid-table-spans.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGridTableSpanFixtureTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'd83277252dd4c4da6a66b08dc096b30d47afbf50823e9abf3db0e0f313aae727',
            'bytes' => 880,
        ],
        'upstream-markdown-destination-bare-space.md' => [
            'role' => 'markdown-destination-bare-space-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown selected default bare destination space compatibility coverage',
            'formatProfile' => 'markdown bare link destination with space percent-encoding',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-destination-bare-space.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDestinationValidationCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'f997c375eea7aaf978d159a6999a0abbe35fd9decff7850accd52da1fa0063ec',
            'bytes' => 25,
        ],
        'upstream-markdown-smart-french-apostrophe.md' => [
            'role' => 'markdown-smart-french-apostrophe-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown smart punctuation/apostrophe in French',
            'formatProfile' => 'markdown+smart French apostrophe and guillemet boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-smart-french-apostrophe.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderSmartPunctuationFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'f1a577d502c60ea42b20146a305234d7dbeface9f317244dd448e4561f3af3f5',
            'bytes' => 76,
        ],
        'upstream-command-gfm-adjacent-emoji.md' => [
            'role' => 'command-gfm-adjacent-emoji-reader-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/gfm.md adjacent emoji shortcodes',
            'formatProfile' => 'gfm adjacent emoji shortcodes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-gfm-adjacent-emoji.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderEmojiFixtureTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '15a3812101cfabd5d2f4a2f9cff931e025e4f858dac535590c0d699a929f69ac',
            'bytes' => 25,
        ],
        'upstream-command-7080-mmd-reference-image-attributes.md' => [
            'role' => 'command-7080-mmd-reference-image-attribute-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/7080.md',
            'formatProfile' => 'markdown_mmd reference image attributes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-7080-mmd-reference-image-attributes.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderMmdReferenceImageAttributeFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'c9b0bb4fd75042f55ff3287c0519c9df48a6017a116d42403a7a99840a761ca6',
            'bytes' => 56,
        ],
        'upstream-markdown-yaml-typed-metadata.md' => [
            'role' => 'markdown-yaml-typed-metadata-reader-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/yaml-metadata-blocks.md typed scalar/list/map cases',
            'formatProfile' => 'markdown yaml_metadata_block typed metadata constructors',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-yaml-typed-metadata.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderYamlMetadataFixtureCompletionTest.php',
                'lanes/pandoc/tests/YamlMetadataReviewTest.php',
            ],
            'sha256' => '111f6bfc28a9b9aefb6e0e2a06752b55bcac4ebf550708c8b0ec2b51862595f9',
            'bytes' => 293,
        ],
        'upstream-markdown-citation-link-following.md' => [
            'role' => 'markdown-citation-link-following-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown footnote/link following citation group',
            'formatProfile' => 'markdown citations followed by footnote, inline link, reference link, implicit header link, and suffix fallback',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-citation-link-following.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderCitationLinkFollowingFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '59149a056667d4bc32cd387324e59104f8ef85541130db1d9f86b4514b770e98',
            'bytes' => 154,
        ],
        'upstream-command-multiple-metadata-blocks.md' => [
            'role' => 'command-multiple-metadata-blocks-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/multiple-metadata-blocks.md',
            'formatProfile' => 'markdown yaml_metadata_block consecutive block override',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-multiple-metadata-blocks.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderMultipleMetadataBlockFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '92289834d3f1d0322739ceb063bc39014e5c928b995f95f95da653de9e16670a',
            'bytes' => 35,
        ],
        'upstream-markdown-reader-more-raw-tex-environments.md' => [
            'role' => 'markdown-reader-more-raw-tex-environments-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'test/markdown-reader-more.txt raw ConTeXt and LaTeX environments',
            'formatProfile' => 'markdown raw_tex raw ConTeXt/LaTeX environments',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-reader-more-raw-tex-environments.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
                'lanes/pandoc/tests/MarkdownUpstreamReaderEvidenceTest.php',
            ],
            'sha256' => 'ae8e7928a750fece03f479ade0e1d6d7998fd804c186b9d0dcf93e503adbac86',
            'bytes' => 328,
        ],
        'upstream-markdown-reader-more-code-spans.md' => [
            'role' => 'markdown-reader-more-code-span-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'test/markdown-reader-more.txt code spans',
            'formatProfile' => 'markdown code span multiline whitespace and delimiter boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-reader-more-code-spans.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderCodeSpanFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '8dc499f98165df54885e4909cc5808645174597006f8c1d2d95b6f6e16850809',
            'bytes' => 65,
        ],
        'upstream-markdown-multiline-table-caption.md' => [
            'role' => 'markdown-multiline-table-caption-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'test/tables.markdown lines 31-43',
            'formatProfile' => 'markdown multiline_tables captioned table',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-multiline-table-caption.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTableProfileSurgeTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'df28b87c50d6e18bb03759d2668569ec27e628a021d15197a36fcc3adcb81403',
            'bytes' => 633,
        ],
        'upstream-markdown-multiline-table-no-caption.md' => [
            'role' => 'markdown-multiline-table-no-caption-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'test/tables.markdown lines 47-57',
            'formatProfile' => 'markdown multiline_tables table without caption',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-multiline-table-no-caption.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTableProfileSurgeTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '673bcf64942d9e85c766f6daf19abca138d0073a48558732578b5681541f93bc',
            'bytes' => 580,
        ],
        'upstream-markdown-multiline-table-no-header.md' => [
            'role' => 'markdown-multiline-table-no-header-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'test/tables.markdown lines 69-76',
            'formatProfile' => 'markdown multiline_tables table without column headers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-multiline-table-no-header.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTableProfileSurgeTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '364d8e077c23a77f992daf4832db292ad7cebac235f122181e8800b091c4cfa2',
            'bytes' => 421,
        ],
        'upstream-markdown-atx-heading-trim.md' => [
            'role' => 'markdown-atx-heading-trim-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown Headers/ATX header without trailing #s',
            'formatProfile' => 'markdown ATX heading closing-hash trim and implicit identifiers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-atx-heading-trim.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderHeadingBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'd5701e4da7f3e023137f9c6376210ec6a6a1593faf7c563471111e9c287f72f3',
            'bytes' => 30,
        ],
        'upstream-markdown-bare-uri-raw-html-anchor.md' => [
            'role' => 'markdown-bare-uri-raw-html-anchor-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bare URIs/raw HTML anchor',
            'formatProfile' => 'markdown+autolink_bare_uris+raw_html bare URI inside raw HTML anchor text',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-bare-uri-raw-html-anchor.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriRawHtmlAnchorFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'e868024c9897fc6a1ddce46228d4e96f28682f269d8e3a4b6d07995fdca1f5a7',
            'bytes' => 52,
        ],
        'upstream-markdown-bare-uri-git-file-schemes.md' => [
            'role' => 'markdown-bare-uri-git-file-scheme-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bare URIs/git and file scheme cases',
            'formatProfile' => 'markdown+autolink_bare_uris git:// and file:// bare URI schemes',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-bare-uri-git-file-schemes.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriGitFileSchemesFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '47b47e2c929efc0802ff53535df1f0ae9df8464cd7546841fed95e37f917bc8c',
            'bytes' => 62,
        ],
        'upstream-markdown-implicit-header-reference-atx.md' => [
            'role' => 'markdown-implicit-header-reference-atx-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown implicit header references/ATX header without trailing #s',
            'formatProfile' => 'markdown implicit_header_references atx heading label-trim behavior',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-implicit-header-reference-atx.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderImplicitHeaderReferenceFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '1a2161ad2b56bac8e8772ffe2116c591358493457ec60fde2d91f23591a62aaa',
            'bytes' => 40,
        ],
        'upstream-markdown-implicit-header-reference-setext.md' => [
            'role' => 'markdown-implicit-header-reference-setext-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown implicit header references/setext header',
            'formatProfile' => 'markdown implicit_header_references setext heading label-trim behavior',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-implicit-header-reference-setext.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderImplicitHeaderReferenceFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '857c91bec5b62052733b64dc3319f9223892c49e3fb617b841f629f7995878de',
            'bytes' => 42,
        ],
        'upstream-command-11253-latex-macros.md' => [
            'role' => 'command-11253-latex-macros-default-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/11253.md markdown default latex_macros enabled case',
            'formatProfile' => 'markdown latex_macros ifstrequal expansion',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-11253-latex-macros.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLatexMacrosIfstrequalFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '18c3fdd8cbfc1d201a11c2bd212e02f4fd48f243011c6f3ad0015f0efe654d66',
            'bytes' => 78,
        ],
        'upstream-command-11253-latex-macros-disabled.md' => [
            'role' => 'command-11253-latex-macros-disabled-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/11253.md markdown-latex_macros disabled case',
            'formatProfile' => 'markdown-latex_macros raw_tex preserved without latex macro expansion',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-11253-latex-macros-disabled.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderLatexMacrosIfstrequalFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '18c3fdd8cbfc1d201a11c2bd212e02f4fd48f243011c6f3ad0015f0efe654d66',
            'bytes' => 78,
        ],
        'upstream-command-11542-definition-code-block.md' => [
            'role' => 'command-11542-definition-code-block-fixture',
            'sourceKind' => 'upstream-command-fixture',
            'sourceReference' => 'test/command/11542.md markdown definition-list indented code body case',
            'formatProfile' => 'markdown definition_lists marker-line indented code block with nested marker literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-command-11542-definition-code-block.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListBodyFinalHarvestTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '5a22a04e22c5067ff8f1f28805107f4612ca1d64524be701715154ec37c93927',
            'bytes' => 33,
        ],
        'upstream-markdown-bare-uri-path-punctuation.md' => [
            'role' => 'markdown-bare-uri-path-punctuation-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bareLinkTests selected parenthesized, semicolon path, percent-encoded space, and path-period cases',
            'formatProfile' => 'markdown+autolink_bare_uris+raw_html bare URI path punctuation boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-bare-uri-path-punctuation.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriPathPunctuationFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'd48891d00c0184a1eec42834d4a928035dd3480b8aafe11a54790071c6e8a20d',
            'bytes' => 190,
        ],
        'upstream-markdown-link-nested-inline-boundary.md' => [
            'role' => 'markdown-link-nested-inline-boundary-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown links/no inline link inside link',
            'formatProfile' => 'markdown nested inline link boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-link-nested-inline-boundary.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderNestedInlineLinkBoundaryFixtureCompletionTest.php',
            ],
            'sha256' => 'f42eca9798982cf81787d133e54cc52a08bf79046320b63747528f0715b891fa',
            'bytes' => 17,
        ],
        'upstream-markdown-bare-uri-unicode-path.md' => [
            'role' => 'markdown-bare-uri-unicode-path-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown bare URIs/Unicode path with trailing comma',
            'formatProfile' => 'markdown+autolink_bare_uris Unicode path and trailing comma boundary',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-bare-uri-unicode-path.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderBareUriSchemeBoundaryFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '3d28e9f4f4dbc355b43c1d205778926a1480acad2e7b10290ed7391c8bb15369',
            'bytes' => 51,
        ],
        'upstream-markdown-citation-simple-baseline.md' => [
            'role' => 'markdown-citation-simple-baseline-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown citations/simple',
            'formatProfile' => 'markdown+citations AuthorInText baseline citation',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-citation-simple-baseline.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'bc4f311dfc4cec47a0db605bae407fa3fee60201e3b25eaa36654299959d027b',
            'bytes' => 7,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-gfm-definition-list-profile.md' => [
            'role' => 'markdown-gfm-definition-list-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 gfm+definition_lists definition-list profile probe',
            'formatProfile' => 'gfm+definition_lists definition-list profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-gfm-definition-list-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGfmDefinitionListProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '15f9f02ecd616b4b847d7dacf103790e57bc41a850121c98abface8f3130a756',
            'bytes' => 33,
        ],
        'upstream-markdown-definition-list-laziness.md' => [
            'role' => 'markdown-definition-list-laziness-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Tests.Readers.Markdown definition lists/laziness',
            'formatProfile' => 'markdown definition_lists',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-definition-list-laziness.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderDefinitionListFixtureCompletionTest.php',
            ],
            'sha256' => 'b6f3202ad2d01afe0c33e061b2dfd6b022611a0bd2ad30eac9bfad6b49228664',
            'bytes' => 27,
        ],
        'upstream-markdown-reference-multiline-title.md' => [
            'role' => 'markdown-reference-multiline-title-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 Markdown reference title newline folding boundary',
            'formatProfile' => 'markdown reference link/image multiline title folding',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-reference-multiline-title.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderReferenceMultilineTitleCompletionTest.php',
            ],
            'sha256' => '282158ee0bdffa838c1e2db62edabde2e8a594b423aae721ee9ad9b5383e8ac4',
            'bytes' => 230,
        ],
        'upstream-markdown-table-attributes-disabled-profile.md' => [
            'role' => 'markdown-table-attributes-disabled-profile-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown-table_attributes table caption attribute literal profile',
            'formatProfile' => 'markdown-table_attributes table caption attributes remain literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-table-attributes-disabled-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTableAttributeProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'a7bcfe06e2566f9297ee294e5496fadb2760658d6ed58c53e069cdc2097bc06f',
            'bytes' => 127,
        ],
        'upstream-markdown-gfm-math-fence-profile.md' => [
            'role' => 'markdown-gfm-tex-math-fence-profile-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 gfm tex_math_gfm fenced display math profile probe',
            'formatProfile' => 'gfm tex_math_gfm fenced code block converted to display math',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-gfm-math-fence-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGfmMathFenceProfileCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '4cee04122c802974cf1e28f16cde8cd8cf09c0f0a35be9e8b1f2672d9625394e',
            'bytes' => 35,
        ],
        'upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-github-yaml-profile.md' => [
            'role' => 'markdown-github-yaml-metadata-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown_github YAML-looking front matter profile probe',
            'formatProfile' => 'markdown_github keeps YAML-looking front matter as HorizontalRule and Setext heading body',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz-markdown-github-yaml-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderGithubYamlProfileCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '7afb355ffac7bad52a1f21c8ab041f9870a90d6a71106d6e60b3e08f47ab71bc',
            'bytes' => 33,
        ],
        'upstream-markdown-z-old-dashes-profile.md' => [
            'role' => 'markdown-old-dashes-profile-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+old_dashes+smart profile probe',
            'formatProfile' => 'markdown+old_dashes+smart paired dash-run profile',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-old-dashes-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderOldDashesProfileFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'f1ff8794375eca5989866b585159bbca22cf09c6e904520a727bd351206d9298',
            'bytes' => 46,
        ],
        'upstream-markdown-z-fancy-list-parenthesized-profile.md' => [
            'role' => 'markdown-fancy-list-parenthesized-marker-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown+fancy_lists parenthesized Roman and alpha marker profile probe',
            'formatProfile' => 'markdown+fancy_lists lower-roman and lower-alpha two-parenthesis ordered markers',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-z-fancy-list-parenthesized-profile.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderFancyListFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => 'e1fbc26de79ed2924d7bb178af704d714682b46f98f7c775717a66ce53e72c09',
            'bytes' => 58,
        ],
        'upstream-markdown-two-space-line-break.md' => [
            'role' => 'markdown-two-space-line-break-reader-fixture',
            'sourceKind' => 'selected-upstream-markdown-reader-case',
            'sourceReference' => 'Pandoc 3.10 markdown trailing two-space hard line break probe',
            'formatProfile' => 'markdown default trailing two-space line break as LineBreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-markdown-two-space-line-break.md',
            'coverageTests' => [
                'lanes/pandoc/tests/MarkdownReaderTwoSpaceLineBreakFixtureCompletionTest.php',
                'lanes/pandoc/tests/MarkdownNativeAstComparisonHarnessTest.php',
            ],
            'sha256' => '421f07b92161d9e26a8fd2e5fd8a3dc9edd66b12f6dfbbde13ab1d9e2e41c8cd',
            'bytes' => 13,
        ],
    ];

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;
    private readonly ?string $runnerResultArtifact;

    public function __construct(
        string $repoRoot,
        string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT,
        ?string $runnerResultArtifact = null
    )
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }
        if ($runnerResultArtifact === '') {
            throw new \InvalidArgumentException('Runner result artifact must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
        $this->runnerResultArtifact = $runnerResultArtifact;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $root = $this->absoluteUpstreamRoot();
        $staticEvidence = self::checkedInCurrentEvidence($this->repoRoot);
        $nativeAstEvidence = $this->nativeAstEvidence();
        if (!is_dir($root)) {
            $denominator = $this->emptyDenominator();

            return [
                'schemaVersion' => 1,
                'tool' => self::TOOL_NAME,
                'status' => self::STATUS_SKIPPED_MISSING_SOURCE,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'root' => $this->displayPath($root),
                    'commit' => null,
                    'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                ],
                'denominator' => $denominator,
                'sourceInventory' => $this->emptySourceInventory(),
                'staticCurrentEvidence' => $staticEvidence,
                'nativeAstEvidence' => $nativeAstEvidence,
                'runnerEvidence' => $this->runnerEvidence($denominator),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $sourceInventory = $this->sourceInventory($root);
        $validationIssues = $this->validationIssues($sourceInventory, $staticEvidence);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerSources' => self::SOURCE_FILES,
            ],
            'denominator' => self::selectedFixtureDenominator(),
            'sourceInventory' => $sourceInventory,
            'staticCurrentEvidence' => $staticEvidence,
            'nativeAstEvidence' => $nativeAstEvidence,
            'runnerEvidence' => $this->runnerEvidence(self::selectedFixtureDenominator()),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-markdown-reader-evidence' : 'invalid-upstream-markdown-reader-evidence',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInCurrentEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtures = [];
        $issues = [];
        $nativeExpectationEvidence = self::checkedInNativeExpectationEvidence($root);

        foreach (self::CHECKED_IN_MARKDOWN_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $coverageTests = array_values(array_map('strval', $snapshot['coverageTests']));
            $testReferences = self::localTestReferences($root, (string) $name);
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sourceKind' => (string) $snapshot['sourceKind'],
                'sourceReference' => (string) $snapshot['sourceReference'],
                'formatProfile' => (string) $snapshot['formatProfile'],
                'coverageTests' => $coverageTests,
                'localTestReferenceCount' => count($testReferences),
                'localTestReferences' => $testReferences,
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-markdown-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-markdown-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-markdown-fixture-byte-count-mismatch';
            }

            foreach ($coverageTests as $testPath) {
                if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $testPath))) {
                    $issues[] = 'missing-markdown-fixture-coverage-test';
                    break;
                }
            }
            if ($testReferences === []) {
                $issues[] = 'missing-markdown-fixture-local-test-reference';
            }
        }

        $nativeExpectationValidation = is_array($nativeExpectationEvidence['validation'] ?? null)
            ? $nativeExpectationEvidence['validation']
            : [];
        $nativeExpectationIssues = is_array($nativeExpectationValidation['issues'] ?? null)
            ? $nativeExpectationValidation['issues']
            : [];
        if (($nativeExpectationValidation['status'] ?? null) !== 'valid-checked-in-current-markdown-native-expectation-evidence') {
            $issues[] = 'invalid-checked-in-markdown-native-expectation-evidence';
            foreach ($nativeExpectationIssues as $issue) {
                if (is_string($issue)) {
                    $issues[] = $issue;
                }
            }
        }

        return [
            'kind' => 'static-checked-in-current-upstream-markdown-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerSources' => self::SOURCE_FILES,
            ],
            'readerDenominator' => self::selectedFixtureDenominator(),
            'checkedInFixtureDirectory' => self::CHECKED_IN_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'nativeExpectationEvidence' => $nativeExpectationEvidence,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-markdown-reader-evidence' : 'invalid-checked-in-current-markdown-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding selected current upstream-derived Markdown reader fixtures and their native expectations to checked-in SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the selected checked-in Markdown fixture snapshots match the expected SHA-256 hashes and byte counts',
                    'the selected checked-in Markdown native expectation snapshots match the expected deterministic manifest hash',
                    'each selected fixture has at least one local PHP test reference',
                'the fixture set covers selected command, raw-attribute, abbreviation, details/summary, GFM, autolink, angle-autolink attribute attachment, spaced-literal behavior, Unicode dash boundary behavior, partial www autolink literal boundary behavior, and GFM split angle raw-HTML literal paragraph boundary behavior, footnote/citation, inline TeX math, standalone dollar display math, footnote recursive-reference, continuation/termination, and same-line fenced-div boundary behavior, citation/span boundary, empty-paragraph, definition-list spacing, nested-list body, html-div body, tight Plain body blocks, lazy SoftBreak continuation, column-zero marker behavior, and marker-line indented code-block body behavior, GitHub wiki-link, inline-code list-marker, attribute, and spaced-attribute literal behavior, backslash-escaped link, link-label boundary, unbalanced-bracket literal, link-title entity decoding, reference link/image multiline title folding, plain character-reference decoding, strikeout-with-nested-emphasis, GitHub emoji-shortcode, superscript/subscript escaped-space boundary behavior, smart punctuation quotes/apostrophes/ellipsis behavior, pipe-table alignment with escaped-pipe cell behavior, fenced-div nested container behavior, header-attribute explicit id/class/key behavior, numbered-example labeled cross-reference behavior, mark nested inline behavior, markdown+mark highlighted inline Span behavior, bracketed-span generic Span plus smallcaps behavior, fenced-code attribute tuple behavior, MultiMarkdown short superscript/subscript delimiter boundary behavior, numeric character-reference decoding, escaped-line-break and default trailing two-space hard break behavior, implicit-header-reference ATX trailing-hash behavior, emph/strong delimiter nesting plus intraword underscore behavior, raw-LaTeX bare environment command literal behavior, implicit-figure latex-placement plus alt boundary behavior, GitHub raw email address strong-boundary behavior, raw-HTML technically invalid comment preservation behavior, raw-HTML invalid tag literal behavior, raw-HTML nested tag split behavior, raw-HTML list continuation with native Div and raw button block boundaries, GFM nested-list continuation under the prior bullet item, gfm+definition_lists definition-list profile behavior, gfm tex_math_gfm fenced display-math behavior, markdown_github YAML-looking front matter literal Setext heading behavior, ordered task-list marker behavior with loose continuation paragraphs, YAML metadata scalar/list/block body-boundary behavior, consecutive YAML metadata blocks with later duplicate-field override behavior, LHS bird/inverse code with implicit HTML div close behavior, Pandoc 3.10 alert blockquote profile behavior, markdown_strict compact ATX heading profile behavior, markdown-space_in_atx_header compact attributed heading behavior, commonmark_x grid-table-looking block paragraph behavior when grid_tables is disabled by default, markdown+grid_tables row-span/column-span/complex-header table behavior, markdown+fancy_lists ordered marker and two-parenthesis Roman/alpha marker profile behavior, markdown-startnum+fancy_lists ordered start-number suppression behavior, markdown+hard_line_breaks physical-newline LineBreak profile behavior, markdown+lists_without_preceding_blankline paragraph-interrupting list profile behavior, markdown+old_dashes+smart paired dash-run behavior, markdown_phpextra header/link-attribute plus definition-list/footnote profile behavior, markdown+simple_tables header/body table constructor profile behavior, markdown+short_subsuperscripts short script profile behavior, markdown-shortcut_reference_links+spaced_reference_links reference-link profile behavior, markdown+tex_math_double_backslash inline/display math delimiter profile behavior, markdown+tex_math_single_backslash inline/display math delimiter profile behavior, markdown-intraword_underscores intraword emphasis/strong profile behavior, markdown-all_symbols_escapable+angle_brackets_escapable angle-only escape profile behavior, markdown+wikilinks_title_after_pipe target-before-pipe/title-after-pipe profile behavior, markdown+ignore_line_breaks physical-newline suppression with explicit hard-break preservation behavior, markdown-auto_identifiers generated heading ID suppression behavior, markdown-blank_before_header paragraph-interrupting heading plus top-level and blockquote-contained implicit-reference behavior, markdown-blank_before_blockquote top-level and nested paragraph-interrupting block quote behavior, digit-leading citation key behavior, markdown+autolink_bare_uris square/curly bracket target encoding, query-string and fragment retention, parenthesized path retention, Unicode path preservation, uppercase, doi, mailto, trailing punctuation, and literal http-token boundary behavior, markdown_mmd mmd_title_block metadata stripping behavior, markdown_mmd reference image attribute behavior, and commonmark+gfm_auto_identifiers+ascii_identifiers punctuation stripping, ASCII folding, dash fallback, and duplicate suffixing behavior',
                ],
                'doesNotAssert' => [
                    'that this PHP report command executed upstream Haskell/Cabal/Tasty tests',
                    'that every inline Haskell assertion in Tests.Readers.Markdown has a one-to-one checked-in fixture file',
                    'Markdown writer parity',
                    'byte-for-byte identity with Pandoc Haskell reader internals',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticDenominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        $nativeExpectationEvidence = is_array($staticEvidence['nativeExpectationEvidence'] ?? null) ? $staticEvidence['nativeExpectationEvidence'] : [];
        $nativeExpectationValidation = is_array($nativeExpectationEvidence['validation'] ?? null) ? $nativeExpectationEvidence['validation'] : [];
        $nativeAstEvidence = is_array($report['nativeAstEvidence'] ?? null) ? $report['nativeAstEvidence'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $selectedFixtureCount = (int) (
            ($denominator['selectedFixtureCount'] ?? 0) !== 0
                ? $denominator['selectedFixtureCount']
                : ($staticDenominator['selectedFixtureCount'] ?? 0)
        );
        $runnerResultLine = self::hasRunnerResultArtifactEvidence($report)
            ? 'Supplied upstream Haskell/Cabal runner result artifact is validated; Markdown writer parity is not asserted.'
            : 'No upstream Haskell/Cabal runner result artifact is supplied; runtime runner execution is not asserted.';

        return implode(PHP_EOL, [
            'Pandoc Markdown reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Selected checked-in fixtures: ' . $selectedFixtureCount,
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0)
                . ' nativeExpectations=' . (int) ($nativeExpectationEvidence['presentFixtureCount'] ?? 0)
                . ' nativeManifest=' . (string) ($nativeExpectationValidation['status'] ?? 'unknown'),
            'Native AST mapped parity: ' . (int) ($nativeAstEvidence['normalizedAstMatchCount'] ?? 0)
                . '/' . (int) ($nativeAstEvidence['totalPairCount'] ?? 0)
                . ' status=' . (string) ($nativeAstEvidence['astParityStatus'] ?? 'unknown'),
            'Runner status: ' . (string) ($runner['status'] ?? 'unknown'),
            'Runner plan: ' . (string) ($runner['commandPlanStatus'] ?? 'unknown'),
            'Runner result artifact: ' . (string) (($runner['validation']['status'] ?? null) ?? 'not-evaluated'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            $runnerResultLine,
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredSelectedFixtureCount(array $report, int $requiredCount): bool
    {
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $denominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];

        return (int) ($denominator['selectedFixtureCount'] ?? -1) === $requiredCount
            && (int) ($staticEvidence['checkedInFixtureCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-markdown-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && self::hasRequiredSelectedFixtureCount($report, self::EXPECTED_SELECTED_FIXTURE_COUNT);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredNativeMappedParity(array $report, int $requiredPairCount): bool
    {
        $nativeAstEvidence = is_array($report['nativeAstEvidence'] ?? null) ? $report['nativeAstEvidence'] : [];

        return MarkdownNativeAstComparisonHarness::hasRequiredMappedParity($nativeAstEvidence, $requiredPairCount);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerNotRunEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return ($runner['status'] ?? null) === 'not-run'
            && ($runner['executed'] ?? null) === false
            && array_key_exists('command', $runner)
            && $runner['command'] === null
            && array_key_exists('resultArtifact', $runner)
            && $runner['resultArtifact'] === null;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerPlanEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];

        return self::hasRunnerNotRunEvidence($report)
            && ($runner['commandPlanStatus'] ?? null) === 'planned-not-run'
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['entryPoint'] ?? null) === 'test/test-pandoc.hs'
            && ($binding['readerTestModule'] ?? null) === 'test/Tests/Readers/Markdown.hs'
            && ($target['testSuite'] ?? null) === self::RUNNER_TEST_SUITE
            && ($target['tastyGroupPath'] ?? null) === self::RUNNER_TASTY_GROUP_PATH
            && ($target['tastyPattern'] ?? null) === self::RUNNER_TASTY_PATTERN
            && ($runner['futureCommands'] ?? null) === self::runnerFutureCommands()
            && ($runner['requiredTranscripts'] ?? null) === self::RUNNER_REQUIRED_TRANSCRIPTS
            && ($runner['requiredArtifacts'] ?? null) === self::RUNNER_REQUIRED_ARTIFACTS;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerResultArtifactEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $artifact = is_array($runner['resultArtifact'] ?? null) ? $runner['resultArtifact'] : [];
        $validation = is_array($runner['validation'] ?? null) ? $runner['validation'] : [];
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];
        $transcripts = is_array($runner['transcripts'] ?? null) ? $runner['transcripts'] : [];

        return ($runner['status'] ?? null) === 'completed'
            && ($runner['executed'] ?? null) === true
            && ($runner['commandPlanStatus'] ?? null) === 'runner-result-artifact-validated'
            && ($runner['scope'] ?? null) === 'upstream-haskell-runner'
            && ($runner['runner'] ?? null) === 'Cabal/Tasty Pandoc Markdown reader suite'
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['observedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($target['testSuite'] ?? null) === self::RUNNER_TEST_SUITE
            && ($target['tastyGroupPath'] ?? null) === self::RUNNER_TASTY_GROUP_PATH
            && ($target['tastyPattern'] ?? null) === self::RUNNER_TASTY_PATTERN
            && ($artifact['kind'] ?? null) === self::RUNNER_RESULT_ARTIFACT_KIND
            && ($artifact['present'] ?? null) === true
            && is_string($artifact['sha256'] ?? null)
            && is_int($artifact['bytes'] ?? null)
            && ($validation['status'] ?? null) === 'valid-upstream-markdown-reader-runner-result-artifact'
            && ($validation['issues'] ?? null) === []
            && self::hasValidRunnerTranscriptEvidence($transcripts);
    }

    /**
     * @param list<mixed> $transcripts
     */
    private static function hasValidRunnerTranscriptEvidence(array $transcripts): bool
    {
        if (count($transcripts) !== count(self::RUNNER_REQUIRED_TRANSCRIPTS)) {
            return false;
        }

        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $index => $path) {
            $transcript = $transcripts[$index] ?? null;
            if (!is_array($transcript)) {
                return false;
            }
            if (($transcript['kind'] ?? null) !== self::RUNNER_TRANSCRIPT_KIND) {
                return false;
            }
            if (($transcript['path'] ?? null) !== $path) {
                return false;
            }
            if (($transcript['present'] ?? null) !== true) {
                return false;
            }
            if (!is_string($transcript['sha256'] ?? null) || !is_int($transcript['bytes'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-markdown-reader-evidence'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function selectedFixtureDenominator(): array
    {
        $fixtures = [];
        $sourceKinds = [];
        $formatProfiles = [];
        foreach (self::CHECKED_IN_MARKDOWN_FIXTURES as $name => $snapshot) {
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sourceKind' => (string) $snapshot['sourceKind'],
                'sourceReference' => (string) $snapshot['sourceReference'],
                'formatProfile' => (string) $snapshot['formatProfile'],
            ];
            $sourceKinds[(string) $snapshot['sourceKind']] = true;
            $formatProfiles[(string) $snapshot['formatProfile']] = true;
        }

        $sourceKindNames = array_keys($sourceKinds);
        sort($sourceKindNames, SORT_STRING);
        $formatProfileNames = array_keys($formatProfiles);
        sort($formatProfileNames, SORT_STRING);

        return [
            'selectedFixtureCount' => count($fixtures),
            'fixtureScope' => 'selected checked-in upstream-derived Markdown reader fixtures',
            'selectedFixtures' => $fixtures,
            'sourceKinds' => $sourceKindNames,
            'formatProfiles' => $formatProfileNames,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runnerEvidence(array $denominator): array
    {
        if ($this->runnerResultArtifact === null) {
            return self::runnerNotRunEvidence();
        }

        return $this->runnerResultArtifactEvidence($denominator);
    }

    /**
     * @param array<string, mixed> $denominator
     * @return array<string, mixed>
     */
    private function runnerResultArtifactEvidence(array $denominator): array
    {
        $path = $this->absoluteRunnerResultArtifact();
        $artifact = $this->runnerResultArtifactFileEvidence($path);
        $transcripts = $this->runnerTranscriptFileEvidenceList();
        $issues = [];
        $payload = [];

        if (($artifact['present'] ?? false) !== true) {
            $issues[] = 'missing-runner-result-artifact';
        } else {
            try {
                $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    $issues[] = 'invalid-runner-result-artifact-json';
                } else {
                    $payload = $decoded;
                }
            } catch (\JsonException) {
                $issues[] = 'invalid-runner-result-artifact-json';
            }
        }

        $upstream = is_array($payload['upstream'] ?? null) ? $payload['upstream'] : [];
        $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];
        $command = is_array($payload['command'] ?? null) ? $payload['command'] : null;
        $expectedCommand = self::runnerFutureCommands()[2];
        $expectedTestNames = self::readerCaseNames($denominator);
        $observedTestNames = self::stringList($payload['testNames'] ?? ($payload['listedTests'] ?? []));
        $observedTranscriptPaths = self::stringList($payload['transcriptPaths'] ?? []);
        $observedTranscriptRecords = self::runnerTranscriptRecords($payload['transcripts'] ?? []);
        if ($observedTranscriptPaths === [] && $observedTranscriptRecords !== []) {
            $observedTranscriptPaths = self::runnerTranscriptRecordPaths($observedTranscriptRecords);
        }
        $runnerExecuted = ($payload['runnerExecuted'] ?? $payload['executed'] ?? null) === true;
        $exitCode = is_int($payload['exitCode'] ?? null) ? (int) $payload['exitCode'] : null;
        $testCount = is_int($payload['testCount'] ?? null) ? (int) $payload['testCount'] : null;
        $passedCount = is_int($payload['passedCount'] ?? null) ? (int) $payload['passedCount'] : null;
        $failedCount = is_int($payload['failedCount'] ?? null) ? (int) $payload['failedCount'] : null;
        $skippedCount = is_int($payload['skippedCount'] ?? null) ? (int) $payload['skippedCount'] : null;

        if ($payload !== []) {
            if (($payload['schemaVersion'] ?? null) !== self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION) {
                $issues[] = 'runner-result-schema-version-mismatch';
            }
            if (($payload['runner'] ?? null) !== 'Cabal/Tasty Pandoc Markdown reader suite') {
                $issues[] = 'runner-result-runner-name-mismatch';
            }
            if (!$runnerExecuted) {
                $issues[] = 'runner-result-executed-flag-missing-or-false';
            }
            if (($upstream['name'] ?? null) !== 'jgm/pandoc' || ($upstream['commit'] ?? null) !== self::EXPECTED_UPSTREAM_COMMIT) {
                $issues[] = 'runner-result-upstream-commit-mismatch';
            }
            if (
                ($target['testSuite'] ?? null) !== self::RUNNER_TEST_SUITE
                || ($target['tastyGroupPath'] ?? null) !== self::RUNNER_TASTY_GROUP_PATH
                || ($target['tastyPattern'] ?? null) !== self::RUNNER_TASTY_PATTERN
            ) {
                $issues[] = 'runner-result-target-mismatch';
            }
            if ($command !== $expectedCommand) {
                $issues[] = 'runner-result-command-mismatch';
            }
            if ($exitCode !== 0) {
                $issues[] = 'runner-result-exit-code-nonzero';
            }
            if (
                $testCount !== count($expectedTestNames)
                || $passedCount !== count($expectedTestNames)
                || $failedCount !== 0
                || $skippedCount !== 0
            ) {
                $issues[] = 'runner-result-counts-mismatch';
            }
            if ($observedTestNames !== $expectedTestNames) {
                $issues[] = 'runner-result-test-names-mismatch';
            }
            if ($observedTranscriptPaths !== self::RUNNER_REQUIRED_TRANSCRIPTS) {
                $issues[] = 'runner-result-transcript-paths-mismatch';
            }
            foreach (self::runnerTranscriptValidationIssues($observedTranscriptRecords, $transcripts) as $issue) {
                $issues[] = $issue;
            }
        }

        $issues = array_values(array_unique($issues));

        return [
            'runner' => 'Cabal/Tasty Pandoc Markdown reader suite',
            'scope' => 'upstream-haskell-runner',
            'status' => $issues === [] ? 'completed' : 'invalid',
            'executed' => $runnerExecuted,
            'command' => $command,
            'resultArtifact' => $artifact,
            'commandPlanStatus' => $issues === [] ? 'runner-result-artifact-validated' : 'runner-result-artifact-invalid',
            'upstreamBinding' => [
                'name' => 'jgm/pandoc',
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'observedCommit' => is_string($upstream['commit'] ?? null) ? $upstream['commit'] : null,
                'entryPoint' => 'test/test-pandoc.hs',
                'readerTestModule' => 'test/Tests/Readers/Markdown.hs',
            ],
            'target' => [
                'testSuite' => is_string($target['testSuite'] ?? null) ? $target['testSuite'] : null,
                'tastyGroupPath' => is_array($target['tastyGroupPath'] ?? null) ? $target['tastyGroupPath'] : null,
                'tastyPattern' => is_string($target['tastyPattern'] ?? null) ? $target['tastyPattern'] : null,
            ],
            'expected' => [
                'schemaVersion' => self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION,
                'runner' => 'Cabal/Tasty Pandoc Markdown reader suite',
                'testCount' => count($expectedTestNames),
                'passedCount' => count($expectedTestNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $expectedTestNames,
                'transcriptPaths' => self::RUNNER_REQUIRED_TRANSCRIPTS,
                'transcripts' => self::runnerTranscriptRecordsFromEvidence($transcripts),
                'command' => $expectedCommand,
            ],
            'observed' => [
                'schemaVersion' => $payload['schemaVersion'] ?? null,
                'runner' => $payload['runner'] ?? null,
                'exitCode' => $exitCode,
                'testCount' => $testCount,
                'passedCount' => $passedCount,
                'failedCount' => $failedCount,
                'skippedCount' => $skippedCount,
                'testNames' => $observedTestNames,
                'transcriptPaths' => $observedTranscriptPaths,
                'transcripts' => $observedTranscriptRecords,
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'transcripts' => $transcripts,
            'validation' => [
                'status' => $issues === []
                    ? 'valid-upstream-markdown-reader-runner-result-artifact'
                    : 'invalid-upstream-markdown-reader-runner-result-artifact',
                'issues' => $issues,
            ],
            'claim' => $issues === []
                ? 'A supplied upstream Markdown reader runner result artifact matches the pinned targeted Tasty runner evidence contract.'
                : 'The supplied upstream Markdown reader runner result artifact did not satisfy the pinned targeted Tasty runner evidence contract.',
        ];
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private function runnerResultArtifactFileEvidence(string $path): array
    {
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_RESULT_ARTIFACT_KIND,
            'path' => $this->displayPath($path),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    /**
     * @return list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}>
     */
    private function runnerTranscriptFileEvidenceList(): array
    {
        $files = [];
        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $path) {
            $files[] = $this->runnerTranscriptFileEvidence($path);
        }

        return $files;
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private function runnerTranscriptFileEvidence(string $relativePath): array
    {
        $path = $this->absoluteRunnerTranscriptPath($relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_TRANSCRIPT_KIND,
            'path' => $this->displayPath($path),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    private function absoluteRunnerTranscriptPath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @return list<array{path: string, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptRecords(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $records[] = [
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'sha256' => is_string($item['sha256'] ?? null) ? $item['sha256'] : null,
                'bytes' => is_int($item['bytes'] ?? null) ? $item['bytes'] : null,
            ];
        }

        return $records;
    }

    /**
     * @param list<array{path: string, sha256: ?string, bytes: ?int}> $records
     * @return list<string>
     */
    private static function runnerTranscriptRecordPaths(array $records): array
    {
        return array_map(
            static fn (array $record): string => $record['path'],
            $records
        );
    }

    /**
     * @param list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}> $files
     * @return list<array{path: string, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptRecordsFromEvidence(array $files): array
    {
        $records = [];
        foreach ($files as $file) {
            $records[] = [
                'path' => $file['path'],
                'sha256' => $file['sha256'],
                'bytes' => $file['bytes'],
            ];
        }

        return $records;
    }

    /**
     * @param list<array{path: string, sha256: ?string, bytes: ?int}> $observedRecords
     * @param list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}> $files
     * @return list<string>
     */
    private static function runnerTranscriptValidationIssues(array $observedRecords, array $files): array
    {
        $issues = [];
        if ($observedRecords === []) {
            $issues[] = 'runner-result-transcript-records-missing';
        }
        if (self::runnerTranscriptRecordPaths($observedRecords) !== self::RUNNER_REQUIRED_TRANSCRIPTS) {
            $issues[] = 'runner-result-transcript-record-paths-mismatch';
        }

        $recordsByPath = [];
        foreach ($observedRecords as $record) {
            if (isset($recordsByPath[$record['path']])) {
                $issues[] = 'runner-result-transcript-record-paths-not-unique';
                continue;
            }
            $recordsByPath[$record['path']] = $record;
        }

        $filesByPath = [];
        foreach ($files as $file) {
            $filesByPath[$file['path']] = $file;
        }

        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $path) {
            $file = $filesByPath[$path] ?? null;
            if (!is_array($file) || ($file['present'] ?? null) !== true) {
                $issues[] = 'runner-result-transcript-file-missing';
                continue;
            }

            $record = $recordsByPath[$path] ?? null;
            if (!is_array($record)) {
                $issues[] = 'runner-result-transcript-record-missing';
                continue;
            }
            if (($record['sha256'] ?? null) !== $file['sha256']) {
                $issues[] = 'runner-result-transcript-sha256-mismatch';
            }
            if (($record['bytes'] ?? null) !== $file['bytes']) {
                $issues[] = 'runner-result-transcript-bytes-mismatch';
            }
        }

        return array_values(array_unique($issues));
    }

    private function absoluteRunnerResultArtifact(): string
    {
        $path = (string) $this->runnerResultArtifact;
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * @param array<string, mixed> $denominator
     * @return list<string>
     */
    private static function readerCaseNames(array $denominator): array
    {
        $fixtures = is_array($denominator['selectedFixtures'] ?? null) ? $denominator['selectedFixtures'] : [];
        $names = [];
        foreach ($fixtures as $fixture) {
            if (is_array($fixture) && is_string($fixture['name'] ?? null)) {
                $names[] = $fixture['name'];
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc Markdown reader suite',
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'commandPlanStatus' => 'planned-not-run',
            'upstreamBinding' => [
                'name' => 'jgm/pandoc',
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'entryPoint' => 'test/test-pandoc.hs',
                'readerTestModule' => 'test/Tests/Readers/Markdown.hs',
            ],
            'target' => [
                'testSuite' => self::RUNNER_TEST_SUITE,
                'tastyGroupPath' => self::RUNNER_TASTY_GROUP_PATH,
                'tastyPattern' => self::RUNNER_TASTY_PATTERN,
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This native PHP evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
    }

    /**
     * @return list<array{purpose: string, program: string, arguments: list<string>}>
     */
    private static function runnerFutureCommands(): array
    {
        return [
            [
                'purpose' => 'prepare runner dependencies in an isolated build directory',
                'program' => 'cabal',
                'arguments' => [
                    'v2-build',
                    '--offline',
                    '--dry-run',
                    '--only-dependencies',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                ],
            ],
            [
                'purpose' => 'list targeted Markdown reader tests',
                'program' => 'cabal',
                'arguments' => [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                    '--',
                    '--list-tests',
                    '--pattern',
                    self::RUNNER_TASTY_PATTERN,
                ],
            ],
            [
                'purpose' => 'run targeted Markdown reader tests',
                'program' => 'cabal',
                'arguments' => [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_TEST_SUITE,
                    '--',
                    '--pattern',
                    self::RUNNER_TASTY_PATTERN,
                ],
            ],
        ];
    }

    private static function claim(): string
    {
        return 'Tracks selected checked-in current upstream-derived Markdown reader fixtures, dialect profiles, and same-basename native AST comparison for Markdown reader implementation equivalence.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the identity and count of 137 selected checked-in upstream-derived Markdown fixtures',
                'that focused local tests cover those selected fixture files',
                'that 137/137 checked-in Markdown/native fixture pairs have normalized AST equality through the local PHP reader harness',
                'that the upstream Markdown reader source inventory is present when a hydrated upstream checkout is inspected',
                'that upstream Haskell runner evidence is either explicitly not-run or supplied as a validated result artifact',
                'the future upstream runner command plan targets test:test-pandoc Readers/Markdown at the pinned upstream commit without execution',
                'a supplied upstream runner result artifact is validated against the pinned Markdown Tasty target, commit, test names, pass/fail counts, and transcript file identities when explicitly provided',
            ],
            'doesNotAssert' => [
                'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                'Markdown writer parity',
                'byte-for-byte identity with Pandoc Haskell reader internals',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'selectedFixtureCount' => 0,
            'fixtureScope' => 'selected checked-in upstream-derived Markdown reader fixtures',
            'selectedFixtures' => [],
            'sourceKinds' => [],
            'formatProfiles' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySourceInventory(): array
    {
        return [
            'files' => [],
            'presentFileCount' => 0,
            'missingFileCount' => 0,
            'presentLineCount' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nativeAstEvidence(): array
    {
        return (new MarkdownNativeAstComparisonHarness())->run($this->repoRoot . '/lanes/pandoc/fixtures');
    }

    /**
     * @return array<string, mixed>
     */
    private static function checkedInNativeExpectationEvidence(string $root): array
    {
        $names = [];
        foreach (array_keys(self::CHECKED_IN_MARKDOWN_FIXTURES) as $markdownName) {
            $names[] = substr((string) $markdownName, 0, -3) . '.native';
        }
        sort($names, SORT_STRING);

        $fixtures = [];
        $manifestLines = [];
        $issues = [];
        foreach ($names as $name) {
            $relativePath = self::CHECKED_IN_FIXTURE_DIRECTORY . '/' . $name;
            $absolutePath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $present = is_file($absolutePath);
            $sha256 = $present ? hash_file('sha256', $absolutePath) : false;
            $bytes = $present ? filesize($absolutePath) : false;
            $sha256Value = is_string($sha256) ? $sha256 : null;
            $bytesValue = is_int($bytes) ? $bytes : null;

            $fixtures[] = [
                'name' => $name,
                'path' => $relativePath,
                'present' => $present,
                'sha256' => $sha256Value,
                'bytes' => $bytesValue,
            ];

            if (!$present) {
                $issues[] = 'missing-checked-in-markdown-native-expectation';
                continue;
            }

            $manifestLines[] = $name . "\t" . $sha256Value . "\t" . $bytesValue;
        }

        $presentFixtureCount = count($manifestLines);
        $manifestPayload = $manifestLines === [] ? '' : implode("\n", $manifestLines) . "\n";
        $manifestSha256 = hash('sha256', $manifestPayload);
        if ($presentFixtureCount !== self::EXPECTED_NATIVE_MAPPED_PAIR_COUNT) {
            $issues[] = 'checked-in-markdown-native-expectation-count-mismatch';
        }
        if ($manifestSha256 !== self::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256) {
            $issues[] = 'checked-in-markdown-native-expectation-manifest-sha256-mismatch';
        }

        return [
            'kind' => 'static-checked-in-current-markdown-native-expectation-evidence',
            'manifestFormat' => 'native fixture basename, SHA-256, and byte count joined with tabs, sorted by native fixture basename and terminated by newlines',
            'expectedFixtureCount' => self::EXPECTED_NATIVE_MAPPED_PAIR_COUNT,
            'fixtureCount' => count($fixtures),
            'presentFixtureCount' => $presentFixtureCount,
            'expectedManifestSha256' => self::EXPECTED_NATIVE_EXPECTATION_MANIFEST_SHA256,
            'manifestSha256' => $manifestSha256,
            'checkedInNativeFixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-markdown-native-expectation-evidence' : 'invalid-checked-in-current-markdown-native-expectation-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding selected checked-in Markdown .native expectation snapshots to a deterministic manifest hash.',
        ];
    }

    /**
     * @return array{files: list<array{path: string, present: bool, bytes: ?int, lineCount: ?int}>, presentFileCount: int, missingFileCount: int, presentLineCount: int}
     */
    private function sourceInventory(string $root): array
    {
        $files = [];
        foreach (self::SOURCE_FILES as $path) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $present = is_file($absolute);
            $contents = $present ? file_get_contents($absolute) : false;
            $bytes = $present ? filesize($absolute) : false;
            $files[] = [
                'path' => $path,
                'present' => $present,
                'bytes' => is_int($bytes) ? $bytes : null,
                'lineCount' => is_string($contents) ? substr_count($contents, "\n") + ($contents === '' || str_ends_with($contents, "\n") ? 0 : 1) : null,
            ];
        }

        $present = array_values(array_filter($files, static fn (array $file): bool => ($file['present'] ?? false) === true));

        return [
            'files' => $files,
            'presentFileCount' => count($present),
            'missingFileCount' => count($files) - count($present),
            'presentLineCount' => array_sum(array_map(static fn (array $file): int => (int) ($file['lineCount'] ?? 0), $present)),
        ];
    }

    /**
     * @param array<string, mixed> $sourceInventory
     * @param array<string, mixed> $staticEvidence
     * @return list<string>
     */
    private function validationIssues(array $sourceInventory, array $staticEvidence): array
    {
        $issues = [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        if (($staticValidation['status'] ?? null) !== 'valid-checked-in-current-markdown-reader-evidence') {
            $issues[] = 'invalid-checked-in-current-markdown-reader-evidence';
        }
        if ((int) ($sourceInventory['missingFileCount'] ?? 0) > 0) {
            $issues[] = 'missing-upstream-markdown-reader-source';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private static function snapshotFileEvidence(string $root, string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : false;
        $bytes = $present ? filesize($path) : false;

        return [
            'path' => $relativePath,
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'expectedSha256' => $expectedSha256,
            'bytes' => is_int($bytes) ? $bytes : null,
            'expectedBytes' => $expectedBytes,
        ];
    }

    /**
     * @return list<string>
     */
    private static function localTestReferences(string $root, string $fixtureName): array
    {
        $testRoot = rtrim($root, DIRECTORY_SEPARATOR) . '/lanes/pandoc/tests';
        if (!is_dir($testRoot)) {
            return [];
        }

        $references = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($testRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if ($file->getFilename() === 'MarkdownUpstreamReaderEvidenceTest.php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (!is_string($contents) || !str_contains($contents, $fixtureName)) {
                continue;
            }

            $references[] = substr($file->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
        }
        sort($references, SORT_STRING);

        return $references;
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->upstreamRoot);
    }

    private function displayPath(string $path): string
    {
        if (str_starts_with($path, $this->repoRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($this->repoRoot) + 1);
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        $head = $root . '/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }

        $contents = trim((string) file_get_contents($head));
        if (str_starts_with($contents, 'ref: ')) {
            $refPath = $root . '/.git/' . substr($contents, 5);
            return is_file($refPath) ? trim((string) file_get_contents($refPath)) : null;
        }

        return preg_match('/^[0-9a-f]{40}$/', $contents) === 1 ? $contents : null;
    }
}
