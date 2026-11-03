<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-delimited-text-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-delimited-text-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-delimited-text-root';
    public const CHECKED_IN_CURRENT_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-csv-reader';
    public const CHECKED_IN_CURRENT_TSV_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-tsv-reader';
    public const CHECKED_IN_CURRENT_TSV_NATIVE_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/current-tsv-reader';
    public const CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/generated-current-csv-reader';
    public const CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/generated-current-tsv-reader';
    public const EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT = 3;
    public const EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT = 2;
    public const EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT = 1;
    public const EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT = 2;
    public const EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT = 2;
    public const EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT = 71;
    public const EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT = 44;
    public const EXPECTED_GENERATED_CSV_PANDOC_EXECUTABLE_NATIVE_SAMPLE_COUNT = 50;
    public const EXPECTED_GENERATED_TSV_PANDOC_EXECUTABLE_NATIVE_SAMPLE_COUNT = 31;
    public const EXPECTED_CSV_PARSER_OPTION_FIXTURE_COUNT = 10;
    public const REQUIRED_PANDOC_EXECUTABLE_VERSION = 'pandoc 3.10';

    private const RUNNER_TEST_SUITE = 'test:test-pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/delimited-text-targeted-run';
    private const RUNNER_WORKING_DIRECTORY = 'hydrated Pandoc upstream checkout root';
    private const RUNNER_RESULT_ARTIFACT_KIND = 'upstream-delimited-text-reader-runner-result-artifact';
    private const RUNNER_TRANSCRIPT_KIND = 'upstream-delimited-text-reader-runner-transcript';
    private const RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION = 2;
    private const RUNNER_TASTY_GROUP_PATH = ['Command:', 'csv.md', '#1'];
    private const RUNNER_TASTY_PATTERN = '$2 == "Command:" && $3 == "csv.md" && $4 == "#1"';
    private const RUNNER_EXPECTED_TEST_NAMES = ['Command: csv.md #1'];
    private const RUNNER_DIRECT_COMMAND_FIXTURE = 'test/command/csv.md';
    private const RUNNER_DIRECT_INPUT_FIXTURE = 'test/command/01.csv';
    private const RUNNER_REQUIRED_TRANSCRIPTS = [
        '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
        '.port-libs/pandoc-runner/logs/delimited-text-targeted-list-tests.txt',
        '.port-libs/pandoc-runner/logs/delimited-text-targeted-run.txt',
    ];
    private const RUNNER_REQUIRED_ARTIFACTS = [
        '.port-libs/pandoc-runner/artifacts/delimited-text-targeted-run/result.json',
    ];

    private const CSV_PARSER_OPTION_FIXTURES = [
        'no-header-ragged',
        'space-delimiter-single-quote',
        'backslash-escaped-quote',
        'backslash-escaped-nonquote',
        'backslash-escaped-escape',
        'bang-escaped-csv-options',
        'keep-space-after-comma',
        'semicolon-delimiter-multiline-cell',
        'pipe-delimiter-quoted-field',
        'quote-disabled-literal',
    ];

    private const CHECKED_IN_CURRENT_CSV_FIXTURES = [
        'csv.md' => [
            'role' => 'direct-csv-command-reader-native-output',
            'upstreamPath' => 'test/command/csv.md',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/csv.md',
            'sha256' => '42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6',
            'bytes' => 2719,
        ],
        '01.csv' => [
            'role' => 'direct-csv-command-reader-input-fixture',
            'upstreamPath' => 'test/command/01.csv',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/01.csv',
            'sha256' => '257c619e19786fddf7685a31a45f6495446a5213083540d09ecba6ce7f1e62cd',
            'bytes' => 47,
        ],
        '9797.md' => [
            'role' => 'direct-csv-command-reader-multiline-cell-native-output',
            'upstreamPath' => 'test/command/9797.md',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/9797.md',
            'sha256' => '5ef0f665c3f0f8eb0982c269d86cdaf9e8f0be4130bf767e2cb871a9102c6c40',
            'bytes' => 857,
        ],
    ];

    private const CURRENT_CSV_DIRECT_NATIVE_SAMPLES = [
        'csv-command-table' => [
            'fixtureName' => 'csv.md',
            'transcriptPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/csv.md',
            'upstreamPath' => 'test/command/csv.md',
        ],
        'csv-command-multiline-cell' => [
            'fixtureName' => '9797.md',
            'transcriptPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/9797.md',
            'upstreamPath' => 'test/command/9797.md',
        ],
    ];

    private const CHECKED_IN_CURRENT_TSV_FIXTURES = [
        '8661.md' => [
            'role' => 'direct-tsv-command-reader-gfm-output',
            'upstreamPath' => 'test/command/8661.md',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-tsv-reader/8661.md',
            'sha256' => '86ceedf9c34dff9b22b36220a7712a0fcd7d831f2f5d740eb44dce1d10ab8f72',
            'bytes' => 104,
        ],
    ];

    private const CHECKED_IN_CURRENT_TSV_NATIVE_FIXTURES = [
        'direct-tsv-basic.tsv' => [
            'role' => 'current-tsv-direct-reader-input-fixture',
            'sample' => 'direct-tsv-basic',
            'checkedInPath' => 'lanes/pandoc/fixtures/current-tsv-reader/direct-tsv-basic.tsv',
            'sha256' => '49211121ac37d8822bbf039af6f25053bf115bbecc3896b75eee3ec001609297',
            'bytes' => 76,
        ],
        'direct-tsv-basic.native' => [
            'role' => 'current-tsv-direct-reader-expected-native-output',
            'sample' => 'direct-tsv-basic',
            'checkedInPath' => 'lanes/pandoc/fixtures/current-tsv-reader/direct-tsv-basic.native',
            'sha256' => 'cdfa36aef2f3046e63448694aafac953231cd1ae56748abbc16dd7b31a2d463e',
            'bytes' => 1309,
        ],
        'upstream-8661.tsv' => [
            'role' => 'current-tsv-direct-reader-input-fixture',
            'sample' => 'upstream-8661',
            'checkedInPath' => 'lanes/pandoc/fixtures/current-tsv-reader/upstream-8661.tsv',
            'sha256' => '010ff53d678796e41afd61be409858425b85c6a965a112c48ca1931528226a93',
            'bytes' => 10,
        ],
        'upstream-8661.native' => [
            'role' => 'current-tsv-direct-reader-expected-native-output',
            'sample' => 'upstream-8661',
            'checkedInPath' => 'lanes/pandoc/fixtures/current-tsv-reader/upstream-8661.native',
            'sha256' => '45acf069b2683568beef4dc2ad677e52b46cf28824e479f25351b2bfc1995f5f',
            'bytes' => 1342,
        ],
    ];

    private const CURRENT_TSV_DIRECT_NATIVE_SAMPLES = [
        'direct-tsv-basic' => [
            'inputPath' => 'lanes/pandoc/fixtures/current-tsv-reader/direct-tsv-basic.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/current-tsv-reader/direct-tsv-basic.native',
        ],
        'upstream-8661' => [
            'inputPath' => 'lanes/pandoc/fixtures/current-tsv-reader/upstream-8661.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/current-tsv-reader/upstream-8661.native',
        ],
    ];

    private const CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES = [
        'quoted-multiline.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-multiline',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv',
            'sha256' => 'a038fe6edd54cf98e2b3afaf14dd4e5cbdbbdb86ab2b62d9bd60cd783ce3324e',
            'bytes' => 80,
        ],
        'quoted-multiline.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-multiline',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.native',
            'sha256' => 'b0b4ae0c2f04421f042eef43c3a79ab699e771a3873e28b23e85d15091f03d57',
            'bytes' => 1894,
        ],
        'post-delimiter-space.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'post-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.csv',
            'sha256' => '109867931d7a1d37a49d565c175d085415b378800e2acd2d4ec8f1c24935601f',
            'bytes' => 131,
        ],
        'post-delimiter-space.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'post-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.native',
            'sha256' => '766278b6bf6c85a71a50a50df5c8ee776c7e774020897f8f39e34d9841a9c8d1',
            'bytes' => 1684,
        ],
        'backslash-escaped-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'backslash-escaped-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.csv',
            'sha256' => 'ae11512ae25941072ef5c297914c544a0815f2a2aba9527a9c80ca1ac5aa406e',
            'bytes' => 33,
        ],
        'backslash-escaped-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'backslash-escaped-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.native',
            'sha256' => '0a512d33990f2629025b2eaae15e34d070fe5e985926e6d2d06d2937ac8ef1b5',
            'bytes' => 932,
        ],
        'quoted-linebreak.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-linebreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.csv',
            'sha256' => 'b017e1cc1434c3422538e1b16fb240ae2c35b0bda12041f568cf5da7921b0476',
            'bytes' => 48,
        ],
        'quoted-linebreak.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-linebreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.native',
            'sha256' => '84472dfb9a0d40daf8c8c38cd50892cd2e13e8118e133ebfcac3720a16ae54f8',
            'bytes' => 2136,
        ],
        'no-header-ragged.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'no-header-ragged',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.csv',
            'sha256' => '178c37d0389b55262ee5a906f2d6a83f914da8bfd819fd37718206065baf876d',
            'bytes' => 57,
        ],
        'no-header-ragged.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'no-header-ragged',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.native',
            'sha256' => '93abe5e561c358a1aa13563f0e20c3eff64cbe334425714a66da87d6b4057798',
            'bytes' => 1214,
        ],
        'bom-leading-whitespace.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'bom-leading-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.csv',
            'sha256' => '6812293a42d8d68da5c184020b3a3a4a579b6f77125080bf40486b8e433f3aec',
            'bytes' => 50,
        ],
        'bom-leading-whitespace.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'bom-leading-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.native',
            'sha256' => '9657368b59d4181c81246a5a11bd5dba277a29088dfdc392c31e2a44fd615e36',
            'bytes' => 1229,
        ],
        'text-after-closing-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'text-after-closing-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.csv',
            'sha256' => 'baa94e35273deb1680660c255569262f9258132d2f97c7550b082f9676e991a6',
            'bytes' => 65,
        ],
        'text-after-closing-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'text-after-closing-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.native',
            'sha256' => '8e33c870e16bb77dc144c177673e3313dce9415c80bda3c9b13123466d42442e',
            'bytes' => 1246,
        ],
        'trailing-empty-fields.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'trailing-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.csv',
            'sha256' => '2f8e15547906de3b9b95a5d354e039809382171b9d64366d751d8e493b5553d5',
            'bytes' => 62,
        ],
        'trailing-empty-fields.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'trailing-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.native',
            'sha256' => '86ca6197ec2c3178474e08e68f8deac8996f0fc7f994a803ec1a399e56f9f849',
            'bytes' => 1477,
        ],
        'crlf-rows.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'crlf-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.csv',
            'sha256' => '9936f7d7046f8e486617541749ff65707d43e463b88577ee8c187615f7c7bc9d',
            'bytes' => 45,
        ],
        'crlf-rows.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'crlf-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.native',
            'sha256' => '95a70343048b4accc704b7ba0613fce1dfea60c0f719eadadb9c2c73761f2c76',
            'bytes' => 1210,
        ],
        'unquoted-space-empty-quoted.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'unquoted-space-empty-quoted',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv',
            'sha256' => 'f59f8d34be7b452806cfd54e49584047e6156c6791b7df067d7452ba697ddba7',
            'bytes' => 74,
        ],
        'unquoted-space-empty-quoted.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'unquoted-space-empty-quoted',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.native',
            'sha256' => '2460dd7891857c3927c5f229fbd819afe432604a92606a61f3cb5b87d6bcd3d7',
            'bytes' => 1523,
        ],
        'comment-looking-data.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'comment-looking-data',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.csv',
            'sha256' => 'cbfda6df02a13b5ba96fcd6ab171b5083c20ef97af65e858ae110032eb9f51c8',
            'bytes' => 96,
        ],
        'comment-looking-data.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'comment-looking-data',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.native',
            'sha256' => 'dcb0f03da9d7ec90de5ce244b3e3002b4f41cc18a9f10314189bcb457823bab6',
            'bytes' => 1617,
        ],
        'no-header-edge-delimiters.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'no-header-edge-delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.csv',
            'sha256' => 'fecf7f0f3ba6bd37411f4c8ebcd36ffedf3a9c8f1e52213fdd044ae4decc0fb1',
            'bytes' => 49,
        ],
        'no-header-edge-delimiters.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'no-header-edge-delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.native',
            'sha256' => '43066e049b19a9f9f6a210b3e25981d07a01915ba784dd86d8427fbf109408c9',
            'bytes' => 1395,
        ],
        'single-quote-dialect.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'single-quote-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.csv',
            'sha256' => 'd59a5e83a298313470b808ba0381a51e3eacb0d50f317719717999e3009c1c2d',
            'bytes' => 104,
        ],
        'single-quote-dialect.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'single-quote-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.native',
            'sha256' => '9c05ec1d28eeda63e95a2f99d84cd0ce4bd6413c6b786efb5c973f86dcdb79b6',
            'bytes' => 1646,
        ],
        'semicolon-delimiter-multiline-cell.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'semicolon-delimiter-multiline-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.csv',
            'sha256' => 'c383ab2b385dcae671a50b2b226051d74d738aaa627dd9c4393af0d39b863336',
            'bytes' => 112,
        ],
        'semicolon-delimiter-multiline-cell.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'semicolon-delimiter-multiline-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.native',
            'sha256' => '32ddacd1d7a77be7516423cc0d67ade520cf024bac92b03607dda08267dfad2f',
            'bytes' => 2016,
        ],
        'cr-only-rows.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'cr-only-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.csv',
            'sha256' => 'fca94752c9fdfbe612a0a998c33a2ba3d5fd816db58ab9648bd41d9318bf3624',
            'bytes' => 45,
        ],
        'cr-only-rows.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'cr-only-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.native',
            'sha256' => 'e3bad4c4dc164b635eec375b48010d2b7cecd6e94274b5cc90484e24276f6a91',
            'bytes' => 1145,
        ],
        'unterminated-quote-eof.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'unterminated-quote-eof',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.csv',
            'sha256' => '272c4e0c03e402d21e2b808459fc913dd3eacc2e7c9dafdfb6f506c8127eb747',
            'bytes' => 34,
        ],
        'unterminated-quote-eof.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'unterminated-quote-eof',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.native',
            'sha256' => '754ba8a6135cf7f7064b714cb6a33990958865e0a5ee04532710a74cc395e74b',
            'bytes' => 925,
        ],
        'duplicate-header-labels.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'duplicate-header-labels',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.csv',
            'sha256' => 'd0627dffb43d149d884fba447424eed9544c36f9885516afd3e2a04e807c101f',
            'bytes' => 42,
        ],
        'duplicate-header-labels.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'duplicate-header-labels',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.native',
            'sha256' => '7e2b213a1c5fa209f5c3f41187012455d9bd701b2da6ff379b15519707ff938e',
            'bytes' => 1211,
        ],
        'keep-space-after-comma.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'keep-space-after-comma',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.csv',
            'sha256' => '68e6bdf13bdb5129562eca08ba28a7516377821d8d2cf951f2927ae923dfb656',
            'bytes' => 118,
        ],
        'keep-space-after-comma.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'keep-space-after-comma',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.native',
            'sha256' => '5a110b2e35a46a8a3e98961b0a68baf210d015a374c99fdd04c60dfee641c721',
            'bytes' => 1731,
        ],
        'space-delimiter-single-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'space-delimiter-single-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.csv',
            'sha256' => '577165de4a8e2beaee7ef748dc7686c9a283f71e730f8d2e21be94e16cde65f4',
            'bytes' => 79,
        ],
        'space-delimiter-single-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'space-delimiter-single-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.native',
            'sha256' => '594390fc80d43bada7903e66a771be44bbef23b24a7f11a2e9ac87e96bc542dd',
            'bytes' => 1579,
        ],
        'blank-row-skipped.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'blank-row-skipped',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.csv',
            'sha256' => '4d721ac02e32060a616d3fef61083cc6f88adae5ace5ced3d77fe5f6fb966321',
            'bytes' => 71,
        ],
        'blank-row-skipped.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'blank-row-skipped',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.native',
            'sha256' => 'cf931bb22f5eeb8934579b99d4109e60801dd40e9f48e4e78a4e24038bc07a5f',
            'bytes' => 1555,
        ],
        'backslash-escaped-nonquote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'backslash-escaped-nonquote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.csv',
            'sha256' => 'e93eadf2bb257f0e678680ac6e9e2c5b6895410c70e91b414e727da53b8cbd43',
            'bytes' => 85,
        ],
        'backslash-escaped-nonquote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'backslash-escaped-nonquote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.native',
            'sha256' => '155fe9867cd9cca831158d85716c5ef1368c60fddd8edad116b8e067ab465eb9',
            'bytes' => 1601,
        ],
        'pipe-delimiter-quoted-field.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'pipe-delimiter-quoted-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.csv',
            'sha256' => '260877bbb70ff332d8bcff85e829231f71de1dc6d3584fca014e1b3861aab6f8',
            'bytes' => 118,
        ],
        'pipe-delimiter-quoted-field.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'pipe-delimiter-quoted-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.native',
            'sha256' => '2df2bf05bc29b8b1484e85435e332eff22e71e81aab2c46c2ce3c8caf75d939b',
            'bytes' => 1697,
        ],
        'quote-disabled-literal.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quote-disabled-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.csv',
            'sha256' => 'd660c2016f15d2181c677dd6545d768f579d6cffcaed5909292260420cf8efde',
            'bytes' => 96,
        ],
        'quote-disabled-literal.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quote-disabled-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.native',
            'sha256' => '0f5b9311d4ace127a447f0ab12474ca032d67db6ea57300ed95cd995d4ff8d5e',
            'bytes' => 1606,
        ],
        'blank-input.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'blank-input',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.csv',
            'sha256' => '01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b',
            'bytes' => 1,
        ],
        'blank-input.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'blank-input',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.native',
            'sha256' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
            'bytes' => 3,
        ],
        'unicode-safe.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'unicode-safe',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.csv',
            'sha256' => 'fc76c7b95aec02b9c85b4f435682cab9b5003be0a0f698117ec062e80ea59929',
            'bytes' => 91,
        ],
        'unicode-safe.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'unicode-safe',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.native',
            'sha256' => 'd4e72fa00d0fcb0f7b1ea4bd44561f5aaadb710f0420b5bc7f78cf0c72a277fe',
            'bytes' => 1364,
        ],
        'quote-in-unquoted-field.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quote-in-unquoted-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.csv',
            'sha256' => '83cdb32eeb44e162f294a30313f3652df81a16df4a298969cb80ecef0277f8d4',
            'bytes' => 47,
        ],
        'quote-in-unquoted-field.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quote-in-unquoted-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.native',
            'sha256' => 'bf2d71e0867ca7b1487c59cff7bf7912d03783dc646003bc3eb0f7a44a3eb9f1',
            'bytes' => 1217,
        ],
        'header-only.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'header-only',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-only.csv',
            'sha256' => '8d10b9e38497ef13bc091e1574b71423a614593e489bd5af9943f946a0296dad',
            'bytes' => 18,
        ],
        'header-only.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'header-only',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-only.native',
            'sha256' => '6c1d2eed4478d45205fe2f2fb63b3ba282aad8c27f37b5a01168ba689bee0f00',
            'bytes' => 610,
        ],
        'leading-whitespace-record.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'leading-whitespace-record',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.csv',
            'sha256' => 'f3365cd5dd45cc2aee1135d4c538390734856d50e5fccf2417b7b8a0568dde89',
            'bytes' => 10,
        ],
        'leading-whitespace-record.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'leading-whitespace-record',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.native',
            'sha256' => 'a18a1b109ba5943baea04ee1f42bbae8e5d4121d3250745ea61e6178f0324846',
            'bytes' => 577,
        ],
        'leading-blank-whitespace.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'leading-blank-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.csv',
            'sha256' => '009966d20c582967816f9721a10b558b07333c88849bff11176b5140e746191e',
            'bytes' => 3,
        ],
        'leading-blank-whitespace.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'leading-blank-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.native',
            'sha256' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
            'bytes' => 3,
        ],
        'quoted-final-vtab-whitespace.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-final-vtab-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.csv',
            'sha256' => '295f211324039598c36a9f427e0c9075833fe2b835459f0a65b62936dfcdaaa4',
            'bytes' => 16,
        ],
        'quoted-final-vtab-whitespace.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-final-vtab-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.native',
            'sha256' => '8f33457b985b91dafdf573244515410fb7d5d43a1004a86ad42845a323c55aff',
            'bytes' => 680,
        ],
        'unquoted-final-formfeed.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'unquoted-final-formfeed',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.csv',
            'sha256' => '80650824fca0b4705c51a54aa7328f4ed13db4a51c55ac3603e7fa55ce295beb',
            'bytes' => 12,
        ],
        'unquoted-final-formfeed.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'unquoted-final-formfeed',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.native',
            'sha256' => 'a3fbb8cf65627ffdb520bb05437dd79096ccf633cffc8d6537b920738e1db792',
            'bytes' => 683,
        ],
        'space-only-record.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'space-only-record',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.csv',
            'sha256' => 'e16f1596201850fd4a63680b27f603cb64e67176159be3d8ed78a4403fdb1700',
            'bytes' => 2,
        ],
        'space-only-record.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'space-only-record',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.native',
            'sha256' => '2643d68a231e44eded9e7ea0647254e3435024cf2dba73ffe924bcdffcab2ae3',
            'bytes' => 344,
        ],
        'quoted-trailing-linebreak.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-trailing-linebreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-linebreak.csv',
            'sha256' => 'c806b117273e5a54d3c91a0ada3051854672d049e6a9b62362c0665b5969a56b',
            'bytes' => 55,
        ],
        'quoted-trailing-linebreak.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-trailing-linebreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-linebreak.native',
            'sha256' => '124132d6e2d241254ed916f8a2d21439fc3e16acc306cee80c98b2fa43f7c2bb',
            'bytes' => 2363,
        ],
        'leading-empty-fields.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'leading-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.csv',
            'sha256' => 'ae9dd96c1d786a3a17e28f8b12a8209d8ccce05339b099ab2ea9ffbc8024e82a',
            'bytes' => 36,
        ],
        'leading-empty-fields.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'leading-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.native',
            'sha256' => '7b88f2fd371de6bda7ed4d4c0de1bfbb9eb7726231cdf6551918735c60c33bf1',
            'bytes' => 1866,
        ],
        'quoted-header-fields.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-header-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.csv',
            'sha256' => '20a690bd9ec550c7bef7124c2be17ec2adcdfde55e227f174527154fa2fb005e',
            'bytes' => 63,
        ],
        'quoted-header-fields.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-header-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.native',
            'sha256' => '7dc4b37ae154cc6c61fe2044fd857198c7cddeedb11dd703760bfc056ab74525',
            'bytes' => 1260,
        ],
        'unquoted-tab-cell.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'unquoted-tab-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.csv',
            'sha256' => '05913c8dfbf085e9a4bce6e7cb78a0cf21bcc730c8c9e99a46ef568095febaea',
            'bytes' => 22,
        ],
        'unquoted-tab-cell.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'unquoted-tab-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.native',
            'sha256' => 'af548f35ca48115c87452df2017c558d88a0b9ffae7923419d3572d8894c9099',
            'bytes' => 1153,
        ],
        'quoted-empty-fields.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.csv',
            'sha256' => 'e1f1ebedf2b64fc3de8e758427aed94132e6ac506d235fe6962543c8b6e12a30',
            'bytes' => 51,
        ],
        'quoted-empty-fields.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.native',
            'sha256' => 'b54f94d0f28e8d0591abad246ebcf26ea9f486560bd98c5ccb5bb16385c2b21f',
            'bytes' => 2439,
        ],
        'leading-whitespace-before-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'leading-whitespace-before-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.csv',
            'sha256' => 'de435cced6dfe6d32ffb53fb28ed4f9c2202b6c0900c82ddc5edd351576c03a3',
            'bytes' => 86,
        ],
        'leading-whitespace-before-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'leading-whitespace-before-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.native',
            'sha256' => '0ed2ba01e1eaca6d5b82d7b75f1b13087f27c4fe5ff2ab9d1e137c3d284e01e5',
            'bytes' => 2371,
        ],
        'post-delimiter-tab.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'post-delimiter-tab',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.csv',
            'sha256' => '330ad95f24c4f732fb00c829df8ba209be3972e1c424a4734c60d34e535fbc43',
            'bytes' => 80,
        ],
        'post-delimiter-tab.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'post-delimiter-tab',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.native',
            'sha256' => 'c9c3e78d71ded4a8b14f2fe66631b11f58a914683a18ef59dc51f99f3c4e7215',
            'bytes' => 1319,
        ],
        'quoted-blank-line-cell.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-blank-line-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.csv',
            'sha256' => '125c8c5b1b014ada9f163e5c1e0d90abb4392728cb01e6950c312b362e9d90eb',
            'bytes' => 34,
        ],
        'quoted-blank-line-cell.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-blank-line-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.native',
            'sha256' => 'c246fe7f10c8983792119be048df8360dd3382315dd136775370944206725ef9',
            'bytes' => 1595,
        ],
        'blank-leading-header.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'blank-leading-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.csv',
            'sha256' => 'e3578702642814615d38649dc6885e5862cb727ea9e418b076d8a9d2cc592525',
            'bytes' => 38,
        ],
        'blank-leading-header.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'blank-leading-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.native',
            'sha256' => '32cb6fb96be43fdaabf0372ffa28ca17fad44ee8789d764fed8fd86df97ad847',
            'bytes' => 2032,
        ],
        'pre-delimiter-space.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'pre-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.csv',
            'sha256' => 'cf31b6c9a903bdc7743edd7be575dc1da5987b6dbd18525f8bf5c88d7cb61d0b',
            'bytes' => 52,
        ],
        'pre-delimiter-space.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'pre-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.native',
            'sha256' => 'aecb3a09cd5c535db2000bca63f7c477e3dd8d3757631268783730ab2ebad7a8',
            'bytes' => 2179,
        ],
        'markdown-syntax-literal.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'markdown-syntax-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.csv',
            'sha256' => '54efc59a5af4fdbb175321dedea7de7df6e5c1257dc9d58201a4a9eebe8a06ed',
            'bytes' => 77,
        ],
        'markdown-syntax-literal.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'markdown-syntax-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.native',
            'sha256' => 'b53dd045777e3aeb09537e85c060f30529302052616c31565652ecf15c66f773',
            'bytes' => 2317,
        ],
        'formula-looking-literals.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'formula-looking-literals',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.csv',
            'sha256' => '25b3fd258d1d8eac491f5c337c1e9dd68020586339d0a7254df05c74f0b21f12',
            'bytes' => 89,
        ],
        'formula-looking-literals.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'formula-looking-literals',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.native',
            'sha256' => 'cd5252c5c890122ababd2e5566f4ae9a02fcf1d23d6c718c6bf77cb72e42ffa5',
            'bytes' => 2185,
        ],
        'literal-backslashes.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'literal-backslashes',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/literal-backslashes.csv',
            'sha256' => '426d024bd9c67b127cedc72003f90a55b54e41cbe20961a05b23845a1e3f1f22',
            'bytes' => 92,
        ],
        'literal-backslashes.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'literal-backslashes',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/literal-backslashes.native',
            'sha256' => '408fedf50b4502884be9b0f8b1fa9e44c183e769ffc7f9f906a5ef466bdb4dc6',
            'bytes' => 1311,
        ],
        'quoted-quotes-only.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-quotes-only',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-quotes-only.csv',
            'sha256' => '63ef7ddd79d41205fa8bc5a8eaa5dcd0be980be3529ea9fc4abbddfbd4b1179c',
            'bytes' => 99,
        ],
        'quoted-quotes-only.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-quotes-only',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-quotes-only.native',
            'sha256' => '6595eba846246fa0a76e818374a36a2356f7ecd97dd4001704e12829e5773ec9',
            'bytes' => 1664,
        ],
        'interior-empty-header.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'interior-empty-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/interior-empty-header.csv',
            'sha256' => '95252bfc868a3c78b6ba0690c43b6627228f3139242197d9bc9fa05eca0465cc',
            'bytes' => 41,
        ],
        'interior-empty-header.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'interior-empty-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/interior-empty-header.native',
            'sha256' => 'b9a001598d1b933ad675d41970f1c8c25f7212a48ccadb846045fea433a4bb93',
            'bytes' => 2447,
        ],
        'header-width-truncates-extra-fields.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'header-width-truncates-extra-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-width-truncates-extra-fields.csv',
            'sha256' => 'd816429cc1d19aefb09df7dabad0470d59ffb0164db8c56122f3306571735a49',
            'bytes' => 12,
        ],
        'header-width-truncates-extra-fields.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'header-width-truncates-extra-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-width-truncates-extra-fields.native',
            'sha256' => '365fea12f7cbf4904d4e15db99b416c4555a997646a8cd90ec693c33c076bb4e',
            'bytes' => 1447,
        ],
        'quoted-edge-spaces.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-edge-spaces',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-edge-spaces.csv',
            'sha256' => 'abc9c092cd5343f3609e738a60ced32cc878e6aa6c20518e4218bce5de67e6d2',
            'bytes' => 79,
        ],
        'quoted-edge-spaces.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-edge-spaces',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-edge-spaces.native',
            'sha256' => '186662f425cf75dee71c2aaa88f204249922cf5a0fadb3020d4501dff983c052',
            'bytes' => 1588,
        ],
        'quoted-multiline-header.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-multiline-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline-header.csv',
            'sha256' => '6d34714c83cfeb46420fa58ad0cd2b53115a3ec267c19fa16abfd70c5f510941',
            'bytes' => 35,
        ],
        'quoted-multiline-header.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-multiline-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline-header.native',
            'sha256' => '7261b85400dd1114d0de4629767fffe214637e59a8cf6df17f8c4ec5a76ce9bd',
            'bytes' => 1571,
        ],
        'quoted-empty-headers.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-empty-headers',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-headers.csv',
            'sha256' => 'd22094a0cfb728163fb7f9e26b2e9985890823d2e470dca087a9f52c21ca4a9b',
            'bytes' => 34,
        ],
        'quoted-empty-headers.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-empty-headers',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-headers.native',
            'sha256' => '08f6093a0ede304ba9357e01f087d9883666f8e15d91b46e4e5d337c27ed023a',
            'bytes' => 1946,
        ],
        'post-delimiter-empty-quoted.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'post-delimiter-empty-quoted',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-empty-quoted.csv',
            'sha256' => '5b70ee2360cfdc191ae8af628897cf94d1fb3228a5c557f0952dab5f3cd97c29',
            'bytes' => 76,
        ],
        'post-delimiter-empty-quoted.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'post-delimiter-empty-quoted',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-empty-quoted.native',
            'sha256' => 'a06f9b9a2bb80ec4faf40ce9fea4522eaf76d0a3ceb54ec039c0140e98f61b61',
            'bytes' => 1511,
        ],
        'quoted-final-row-padding.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-final-row-padding',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-row-padding.csv',
            'sha256' => 'c962bf14b2ca02b23d7dd77614492770c4d88673eee7530e52ebbe16b3e85c79',
            'bytes' => 22,
        ],
        'quoted-final-row-padding.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-final-row-padding',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-row-padding.native',
            'sha256' => 'f2d395bc233528a771c73fff137350a3934393bcf070347bce41b83550823667',
            'bytes' => 685,
        ],
        'quoted-crlf-linebreak.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-crlf-linebreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-crlf-linebreak.csv',
            'sha256' => '3f2469c5b1f5e4b354f235434cd2bed54061b6b2a285447ad29f6fb41b60a1ac',
            'bytes' => 51,
        ],
        'quoted-crlf-linebreak.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-crlf-linebreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-crlf-linebreak.native',
            'sha256' => '3956114302f624d1849536a2d452f85b0ce684fb25319e13db3708f41903e71d',
            'bytes' => 2135,
        ],
        'quoted-bare-cr-normalized.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-bare-cr-normalized',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-bare-cr-normalized.csv',
            'sha256' => 'a0ffe7593af38cbd60c634f672daafb274bc05c2995859c6eea4fa5b1e096a5f',
            'bytes' => 43,
        ],
        'quoted-bare-cr-normalized.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-bare-cr-normalized',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-bare-cr-normalized.native',
            'sha256' => '9b868b8f3d50b07608e490a0eee15529c6e476e58d0397f5cc236eaf927bf853',
            'bytes' => 2112,
        ],
        'quoted-delimiter-blank-short-row.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-delimiter-blank-short-row',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-delimiter-blank-short-row.csv',
            'sha256' => '522b8a1e6bfa91cafc8d3b96b90724fe05b1947b6aa24775302eeac1c2b29e28',
            'bytes' => 56,
        ],
        'quoted-delimiter-blank-short-row.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-delimiter-blank-short-row',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-delimiter-blank-short-row.native',
            'sha256' => '1be02b724aa36c655dae9ff39d52086244d5f586ff8c1c8b3fa63513d9d150cb',
            'bytes' => 2304,
        ],
        'delimiter-only-row.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'delimiter-only-row',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/delimiter-only-row.csv',
            'sha256' => '9ea069dba8355db9b07e013bb2e27ae2293301cff67e96e40d4c6d9bc96fac1d',
            'bytes' => 43,
        ],
        'delimiter-only-row.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'delimiter-only-row',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/delimiter-only-row.native',
            'sha256' => '20e6be4a619b1e8f9961f222470fef988fe9664f56a9ce8ed691518e9f8c422a',
            'bytes' => 2445,
        ],
        'quoted-delimiter-boundaries.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-delimiter-boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-delimiter-boundaries.csv',
            'sha256' => 'daedb210e8254563974d614e0ff69337115b63e61334dea670751bedc7b94c8d',
            'bytes' => 82,
        ],
        'quoted-delimiter-boundaries.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-delimiter-boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-delimiter-boundaries.native',
            'sha256' => '39aea7e34af33e26b7a533b072ace9fca853fba38497e084f06e5c40034bf5fb',
            'bytes' => 2756,
        ],
        'quoted-softbreak.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-softbreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-softbreak.csv',
            'sha256' => '933f3c6150a9ebc91c5f7e20ed759cd4db485b60c09ad15acef99556db359fea',
            'bytes' => 56,
        ],
        'quoted-softbreak.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-softbreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-softbreak.native',
            'sha256' => '499dc8c7d9b0988ed79afc0ec6d533f0a749cdec8b3537cc8c4390d4f5b467a6',
            'bytes' => 1257,
        ],
        'bang-escaped-csv-options.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'bang-escaped-csv-options',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bang-escaped-csv-options.csv',
            'sha256' => '519f8bd0e8185f2e01ee037c1c0ad7f4ce6d1ee6ca2167788bbdd9eafe84a0bc',
            'bytes' => 93,
        ],
        'bang-escaped-csv-options.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'bang-escaped-csv-options',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bang-escaped-csv-options.native',
            'sha256' => 'b83612bbf806265ad8219105375347e30721ffd1d93ae18b789f357ac99dc429',
            'bytes' => 1640,
        ],
        'single-quote-double-quote-literal.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'single-quote-double-quote-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-double-quote-literal.csv',
            'sha256' => 'b16ed04a1bc02dcefc0e0e8201af1524c3a974ae634ac58a637f2cc328967f65',
            'bytes' => 158,
        ],
        'single-quote-double-quote-literal.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'single-quote-double-quote-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-double-quote-literal.native',
            'sha256' => 'd5dcb94684914780bbb4145fd721770595a39d55019968f5d3a26ceb2e200be7',
            'bytes' => 1831,
        ],
        'keep-space-before-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'keep-space-before-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-before-quote.csv',
            'sha256' => '752a2af5360dfe6d457b209d72923b22d339ac44942f8c74850aff22beabb389',
            'bytes' => 59,
        ],
        'keep-space-before-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'keep-space-before-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-before-quote.native',
            'sha256' => 'f528d527b80b33f7e71db1355373d00ac3de4c509312fecd0cf688468d46c033',
            'bytes' => 1277,
        ],
        'quoted-trailing-empty-field.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-trailing-empty-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-empty-field.csv',
            'sha256' => '23521624c9a238993d14490b3f282bdc0f2c1590a1376371924df9a254cdc5ba',
            'bytes' => 81,
        ],
        'quoted-trailing-empty-field.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-trailing-empty-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-empty-field.native',
            'sha256' => '48df01be83b5a077c286db9614763182fbaba277dbc184474a0d1e1d21dbb6e9',
            'bytes' => 1867,
        ],
        'quoted-unicode-doubled-quotes.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-unicode-doubled-quotes',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-unicode-doubled-quotes.csv',
            'sha256' => 'b1848c5b9322e05db0b79fc3079f973f485c1aef8d4a8797623ba67a71c63623',
            'bytes' => 127,
        ],
        'quoted-unicode-doubled-quotes.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-unicode-doubled-quotes',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-unicode-doubled-quotes.native',
            'sha256' => '497470323a734ce0b6344c5d0d5f8bb01a197c5fd6fdfd466d08957613649b42',
            'bytes' => 2773,
        ],
        'partial-final-record.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'partial-final-record',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/partial-final-record.csv',
            'sha256' => 'a988403d02b32de83283cd2cbde2840b2397dab1c1556973ed64a41fdc13e2df',
            'bytes' => 35,
        ],
        'partial-final-record.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'partial-final-record',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/partial-final-record.native',
            'sha256' => 'd3f75a9ea6c00e79fa3a015b6464280ae354392c705335651e71fd8e34eb4ea5',
            'bytes' => 1185,
        ],
        'quoted-final-space-whitespace.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-final-space-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-space-whitespace.csv',
            'sha256' => 'd30d086405286d4569dcaac5f727e57861b98319788c5b950f1ee2f42d6c6507',
            'bytes' => 17,
        ],
        'quoted-final-space-whitespace.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-final-space-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-space-whitespace.native',
            'sha256' => 'e1aeed0b50260073654fdc92ec94b201f5d9678112837250f7f36d47692504e6',
            'bytes' => 1128,
        ],
        'quoted-doubled-linebreak-ragged.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-doubled-linebreak-ragged',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-doubled-linebreak-ragged.csv',
            'sha256' => '34a72fa6c201f47bb4c5b41ee9d48a3052ae54d817a58cf28b5a0509486931e4',
            'bytes' => 88,
        ],
        'quoted-doubled-linebreak-ragged.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-doubled-linebreak-ragged',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-doubled-linebreak-ragged.native',
            'sha256' => '4515da292e71e03feb07e21c19d7d7a9966086ef0ce1bd0f8888e2041a87d04e',
            'bytes' => 1585,
        ],
        'quoted-tab-cell.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-tab-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-tab-cell.csv',
            'sha256' => 'a38d027bb7d8a6ccd8007f09bb6e4ea2fe891a654c99d2ae94340b425815be3e',
            'bytes' => 52,
        ],
        'quoted-tab-cell.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-tab-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-tab-cell.native',
            'sha256' => '8b44069d6379ccbb9ca71d673127f56b9f40f5853a6773efa82dffde5a6ffa34',
            'bytes' => 2152,
        ],
        'backslash-escaped-escape.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'backslash-escaped-escape',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-escape.csv',
            'sha256' => 'c329e8a7695787738350288ab268d4f56824923a6c47fc0746d59235dee37e13',
            'bytes' => 46,
        ],
        'backslash-escaped-escape.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'backslash-escaped-escape',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-escape.native',
            'sha256' => '70c73e8c5d2241f187f718ee94420980edfd7c955bc06076c0166ead19ffd6e0',
            'bytes' => 977,
        ],
        'backslash-before-closing-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'backslash-before-closing-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-before-closing-quote.csv',
            'sha256' => '2f9f98b142122454bcc94d2e6e24840c9b5e42984998392f0c79689b42851ee1',
            'bytes' => 63,
        ],
        'backslash-before-closing-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'backslash-before-closing-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-before-closing-quote.native',
            'sha256' => '99018bb1d759f850fb5957c080a5f2ee36c8d73fbee50c9e6a7f94db80b4f4f9',
            'bytes' => 1310,
        ],
        'closing-quote-record-whitespace-recovery.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'closing-quote-record-whitespace-recovery',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/closing-quote-record-whitespace-recovery.csv',
            'sha256' => 'a35426e2b6bee17b9acbdefcc48444c4dfa892ef50b13212d3568c48ae17450a',
            'bytes' => 30,
        ],
        'closing-quote-record-whitespace-recovery.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'closing-quote-record-whitespace-recovery',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/closing-quote-record-whitespace-recovery.native',
            'sha256' => '9e35a55dad3bae7fcddfa18a3e2c26493569ef1b5fe184967aa1b2120b1e4489',
            'bytes' => 897,
        ],
    ];

    private const CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES = [
        'simple.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'simple',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv',
            'sha256' => 'fcee0aed5a2fde11bbd19f2fc4445357a0d7bbd9c9962df6630fed4b6178ff8e',
            'bytes' => 71,
        ],
        'simple.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'simple',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.native',
            'sha256' => 'f4c930c9d309c4dd6ec1c50eda9e45ff3614566e6c26e4b5254ce3e9c62abb2a',
            'bytes' => 1540,
        ],
        'quote-trailing.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'quote-trailing',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.tsv',
            'sha256' => 'c5694bc5e74a5920c4752369bd967be614f3d7f8fde6395bcd05c9b5f22d85dd',
            'bytes' => 102,
        ],
        'quote-trailing.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'quote-trailing',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.native',
            'sha256' => '51b8ce6dc3164f654f50f7fc1597e2788b04a2b634a32a3f52d51951b68260b6',
            'bytes' => 1975,
        ],
        'unicode-safe.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'unicode-safe',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.tsv',
            'sha256' => 'cd7a0f7e2c4737a1884c0ff3ec73bf6a5990fbdfb6ba1b588b6a6d9202ab3e02',
            'bytes' => 91,
        ],
        'unicode-safe.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'unicode-safe',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.native',
            'sha256' => 'e7d3ea0f37e8d3b0613155eaaf480edf042cd5e22aa4291866ae8a0e627fe990',
            'bytes' => 1370,
        ],
        'ragged-blank-fields.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'ragged-blank-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.tsv',
            'sha256' => '3eb62cad900b02542011bfcb6ffa891856dbf398aa7e7174785264494258c9d4',
            'bytes' => 76,
        ],
        'ragged-blank-fields.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'ragged-blank-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.native',
            'sha256' => '3dff8bc1804021464a9c00917917904cef8c259d3933410507bb0a6961899bce',
            'bytes' => 1756,
        ],
        'no-header.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'no-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.tsv',
            'sha256' => '0553e41c6e8a6257ad01d8dfad5c1ffecfb495a58273b38b1115ddb5635449bd',
            'bytes' => 37,
        ],
        'no-header.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'no-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.native',
            'sha256' => '9d9356cfcfb719fb3093faf108a3f70cbf15dfb3921b37420d8d6a3eef3caf46',
            'bytes' => 1186,
        ],
        'bom-leading-whitespace.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'bom-leading-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.tsv',
            'sha256' => 'd10a56e1e3d9cdf0abb8c3f800d45a8bace164a4ff015c72dad5b5206b55f451',
            'bytes' => 48,
        ],
        'bom-leading-whitespace.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'bom-leading-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.native',
            'sha256' => '9657368b59d4181c81246a5a11bd5dba277a29088dfdc392c31e2a44fd615e36',
            'bytes' => 1229,
        ],
        'blank-row-literal-punctuation.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'blank-row-literal-punctuation',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.tsv',
            'sha256' => '3971c352574fb88bf49073fab5e73d309c3e50d23c169250aec22e8ed3e0c4d8',
            'bytes' => 51,
        ],
        'blank-row-literal-punctuation.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'blank-row-literal-punctuation',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.native',
            'sha256' => '29623a127b4bc0bf3f17b351bfa9f712a1ecbd2d24741d3c2f6aa0475e250023',
            'bytes' => 1253,
        ],
        'comment-looking-data.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'comment-looking-data',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.tsv',
            'sha256' => 'a52c8e6587c36a1deb6d86bce90910eb138f9ed983ba66c6336eca055f0e9d04',
            'bytes' => 84,
        ],
        'comment-looking-data.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'comment-looking-data',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.native',
            'sha256' => '52a97c04e576bedd6bec2609850c3a65c3a90fc165326d9ab11beae1f447cc2e',
            'bytes' => 1399,
        ],
        'no-header-edge-delimiters.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'no-header-edge-delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.tsv',
            'sha256' => '0e90d36fbdce51c4ee0557fa0d1526d849493f30d408675cc445094b7ae79e45',
            'bytes' => 58,
        ],
        'no-header-edge-delimiters.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'no-header-edge-delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.native',
            'sha256' => 'ac289ede527edd0ce9fda8d797a316a12191a81a9a681a1de56af52448bb12f2',
            'bytes' => 1436,
        ],
        'csv-quoted-literal.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'csv-quoted-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.tsv',
            'sha256' => '1c28f3c034a65a005034ae5806e4d035eecd9704c6cf1055b2f0c041e96719be',
            'bytes' => 129,
        ],
        'csv-quoted-literal.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'csv-quoted-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.native',
            'sha256' => '419fb3357404e8b572bf42e5fe3cc32c410f4b69566b282295a7039490ab6fdc',
            'bytes' => 1734,
        ],
        'keep-space-after-tab.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'keep-space-after-tab',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.tsv',
            'sha256' => '4a015006efd98569714058528747683dd5e3a384a0a9615d7d7ebce3bcd8e603',
            'bytes' => 119,
        ],
        'keep-space-after-tab.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'keep-space-after-tab',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.native',
            'sha256' => '88ffc2cd12c0dd74592bceeb20821ec9a38c10f87e9b60a808ca03569c9c1026',
            'bytes' => 1725,
        ],
        'crlf-rows.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'crlf-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.tsv',
            'sha256' => '1ee34fc2887a5be7359dd06425faa9e15c47cc7fd65ea5b475119cf159951eb4',
            'bytes' => 44,
        ],
        'crlf-rows.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'crlf-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.native',
            'sha256' => 'ae90f3b65232ccb820321bacbc03f1f45224cfcfdb7eb2614315e124d91905e0',
            'bytes' => 1210,
        ],
        'quoted-tabs-and-newlines.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'quoted-tabs-and-newlines',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.tsv',
            'sha256' => '063ef586c65fd208bfb670a711edbd004501bb484fe5facbed94c6f898bb6f79',
            'bytes' => 94,
        ],
        'quoted-tabs-and-newlines.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'quoted-tabs-and-newlines',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.native',
            'sha256' => 'dbfdd6519302270f48a6831a9e0594d7779e14922b9f8fd120eee2a7204d2b5b',
            'bytes' => 1615,
        ],
        'blank-leading-header.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'blank-leading-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.tsv',
            'sha256' => 'c2fd8d6c08e7858885d36a4d57a4f79f473418772f1c9f5c6f128b6fbba9858c',
            'bytes' => 21,
        ],
        'blank-leading-header.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'blank-leading-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.native',
            'sha256' => '36321b161eb2743b361b6e5f2d8062b2de6d006969f64290fcbb84bb3d180ed2',
            'bytes' => 872,
        ],
        'basic-status.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'basic-status',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.tsv',
            'sha256' => 'd05b3c50b6780930533f48d3e8192cb4a50ee2f15dec69d75984d10f43dba22d',
            'bytes' => 61,
        ],
        'basic-status.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'basic-status',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.native',
            'sha256' => '71b49eeb3ed15b82ae55464884fd30a7bf4191dbd04fb2625bea3a862896c4a9',
            'bytes' => 1262,
        ],
        'header-only.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'header-only',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.tsv',
            'sha256' => '46486ef39ea30bfa8f03905b713e20d76b78ee760e4e586931fd5008db45abe6',
            'bytes' => 18,
        ],
        'header-only.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'header-only',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.native',
            'sha256' => '6c1d2eed4478d45205fe2f2fb63b3ba282aad8c27f37b5a01168ba689bee0f00',
            'bytes' => 610,
        ],
        'no-header-internal-trailing-empty.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'no-header-internal-trailing-empty',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.tsv',
            'sha256' => '4147bfbde51a4e832fe461334bc8657c055dca86d4b274dee8c3adab32cab9cd',
            'bytes' => 33,
        ],
        'no-header-internal-trailing-empty.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'no-header-internal-trailing-empty',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.native',
            'sha256' => 'c3fade20df04245e26fd3e54990284f7e1a8750c882c2557ec520c75faab46f5',
            'bytes' => 1363,
        ],
        'blank-input.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'blank-input',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.tsv',
            'sha256' => '01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b',
            'bytes' => 1,
        ],
        'blank-input.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'blank-input',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.native',
            'sha256' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
            'bytes' => 3,
        ],
        'duplicate-header-labels.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'duplicate-header-labels',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.tsv',
            'sha256' => 'd973ebe3ce9f9aab73fecd99f1c85e901f0f572089d69deb6f7eb9dee79d0e23',
            'bytes' => 42,
        ],
        'duplicate-header-labels.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'duplicate-header-labels',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.native',
            'sha256' => '7e2b213a1c5fa209f5c3f41187012455d9bd701b2da6ff379b15519707ff938e',
            'bytes' => 1211,
        ],
        'escaped-quote-dialect.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'escaped-quote-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.tsv',
            'sha256' => '1fb627d196a256264e209d4f63d92bf9a40cac52241775abc794679b549fdc4f',
            'bytes' => 81,
        ],
        'escaped-quote-dialect.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'escaped-quote-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.native',
            'sha256' => '858da6b66210ba88c7f74932964abd6a7c35a89464ce20fb855da8d5be4fffe6',
            'bytes' => 1326,
        ],
        'literal-quote-tab-split.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'literal-quote-tab-split',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.tsv',
            'sha256' => '00fa66e3f5a260829bf083772aeea977b1bafda332a62dee7a6b54027cd28bdc',
            'bytes' => 49,
        ],
        'literal-quote-tab-split.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'literal-quote-tab-split',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.native',
            'sha256' => '2dcb1348c01e9fd601db48b537d48593b033a8d45ed9641619e569e925f1582e',
            'bytes' => 1214,
        ],
        'leading-blank-whitespace.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'leading-blank-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.tsv',
            'sha256' => '009966d20c582967816f9721a10b558b07333c88849bff11176b5140e746191e',
            'bytes' => 3,
        ],
        'leading-blank-whitespace.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'leading-blank-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.native',
            'sha256' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
            'bytes' => 3,
        ],
        'unquoted-final-formfeed.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'unquoted-final-formfeed',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.tsv',
            'sha256' => 'a329477fc79b06ee10cd8743544b6e627804200a3c411eba3d14db095444bbf4',
            'bytes' => 12,
        ],
        'unquoted-final-formfeed.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'unquoted-final-formfeed',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.native',
            'sha256' => 'a3fbb8cf65627ffdb520bb05437dd79096ccf633cffc8d6537b920738e1db792',
            'bytes' => 683,
        ],
        'literal-quote-newline-split.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'literal-quote-newline-split',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.tsv',
            'sha256' => 'c98a20cd63e456e0276d69e70c746980935c1495982f25f3dbec73c03b38bd36',
            'bytes' => 16,
        ],
        'literal-quote-newline-split.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'literal-quote-newline-split',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.native',
            'sha256' => '6f2619681b13663971b27c589612e272dd26627a167e9a7a53bee2972899f617',
            'bytes' => 870,
        ],
        'leading-empty-fields.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'leading-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.tsv',
            'sha256' => 'ab4dfed4760d46c5f0dd14b82aa08366dc8b906e748a5e0f7188fa6df4b4d818',
            'bytes' => 36,
        ],
        'leading-empty-fields.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'leading-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.native',
            'sha256' => 'ed543e7867c79895214721849592da8962289f4a8e9d853be8d6bc04f13fc562',
            'bytes' => 1145,
        ],
        'trailing-empty-fields.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'trailing-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.tsv',
            'sha256' => '3f45d3086a898528498ee69696f28d6ee6876ec891d66806d1609ebc5fc2dcc7',
            'bytes' => 43,
        ],
        'trailing-empty-fields.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'trailing-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.native',
            'sha256' => '5765a3463ad42f0e48295e67e3276bf0d6a0d3d0013e131a492379637a40ebbb',
            'bytes' => 2363,
        ],
        'literal-quote-header.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'literal-quote-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.tsv',
            'sha256' => 'bb618e2a1e983dea0842ad93f813bdd7d15e5d00590f868d8d6d2218f92fee3d',
            'bytes' => 49,
        ],
        'literal-quote-header.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'literal-quote-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.native',
            'sha256' => 'ebcd237669082f27a9e47a4602cc46a711a83b158c0e2ffd2006e5ef61c98e64',
            'bytes' => 2131,
        ],
        'markdown-syntax-literal.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'markdown-syntax-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.tsv',
            'sha256' => '307f323f10c85aa81a984dcfb7fc8adc4f9f4d17e551064b3276134bae710d9d',
            'bytes' => 77,
        ],
        'markdown-syntax-literal.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'markdown-syntax-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.native',
            'sha256' => 'b53dd045777e3aeb09537e85c060f30529302052616c31565652ecf15c66f773',
            'bytes' => 2317,
        ],
        'interior-empty-header.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'interior-empty-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.tsv',
            'sha256' => '1c97e1d22017dd23c5b20edb6fd2049cbfdfd33ec2fbaf52ed1e6135462ffd5c',
            'bytes' => 41,
        ],
        'interior-empty-header.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'interior-empty-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.native',
            'sha256' => '6facd7076f0094d0ffe96c17e9b9c774dc60b73b38ac3691928939b1a55fd285',
            'bytes' => 2447,
        ],
        'trailing-empty-header.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'trailing-empty-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.tsv',
            'sha256' => '32f7df1adadfd010a05d9ddd9dbf1705050471da4682cc0d37aa8b81ead666cd',
            'bytes' => 47,
        ],
        'trailing-empty-header.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'trailing-empty-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.native',
            'sha256' => 'b22eac43664c1917ebbcd2a9b6053cab9cf1afb4785ccefe8cd1602eda3682b3',
            'bytes' => 2535,
        ],
        'quoted-softbreak.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'quoted-softbreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-softbreak.tsv',
            'sha256' => '01d5ec9046ee9de78dbc8fdd589b7250fa5925a3d9077d3ff7c941cb1c2c97a1',
            'bytes' => 54,
        ],
        'quoted-softbreak.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'quoted-softbreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-softbreak.native',
            'sha256' => 'db9489756eeaacaeb268e403036dddbf10fb9dc35872123331ff01ca1594a810',
            'bytes' => 1255,
        ],
        'post-delimiter-space.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'post-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/post-delimiter-space.tsv',
            'sha256' => 'e5367f8cd34b279f8f8fc0c5ec78ee042509bc00647c69ee698092d463374496',
            'bytes' => 22,
        ],
        'post-delimiter-space.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'post-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/post-delimiter-space.native',
            'sha256' => '45407755e5d9da0a6d4656f255a9cf0f5b20f924116e4143b964577b56464b10',
            'bytes' => 1152,
        ],
        'delimiter-only-row.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'delimiter-only-row',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/delimiter-only-row.tsv',
            'sha256' => '3a6565c55c9edf509918a14410b2303017c4ba50cd6756d81c82f5b3c18e9a49',
            'bytes' => 33,
        ],
        'delimiter-only-row.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'delimiter-only-row',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/delimiter-only-row.native',
            'sha256' => '7c35f383cedcdfe02af9e39232a63e94fce26b62c3750624110a5475cb9caddd',
            'bytes' => 1067,
        ],
        'single-quote-dialect.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'single-quote-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/single-quote-dialect.tsv',
            'sha256' => '61d1d78f1d9c57ab28505af9e139e085b518a07d27fa85db704354fb7338b2cd',
            'bytes' => 94,
        ],
        'single-quote-dialect.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'single-quote-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/single-quote-dialect.native',
            'sha256' => 'ac434ae179a9b2474a8a7fc6e0ea6c65270e138d25608e73de522f1f21bf67c8',
            'bytes' => 1613,
        ],
        'escaped-tab-dialect.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'escaped-tab-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-tab-dialect.tsv',
            'sha256' => '045876667fbdafbdc984fb54e11ab05837d7fe3bc7c39469fae774b909452eb3',
            'bytes' => 84,
        ],
        'escaped-tab-dialect.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'escaped-tab-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-tab-dialect.native',
            'sha256' => '68b50ae72ee5b99702e8dd6aafa1a2e909775e17e3954b5967c94b2e7b0c0404',
            'bytes' => 1296,
        ],
        'pipe-delimiter-quoted-field.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'pipe-delimiter-quoted-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/pipe-delimiter-quoted-field.tsv',
            'sha256' => '68ff56d2b21faa5fff4bb21e434af9e9436fb4fb8cc049b0555a1de1441b75b1',
            'bytes' => 95,
        ],
        'pipe-delimiter-quoted-field.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'pipe-delimiter-quoted-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/pipe-delimiter-quoted-field.native',
            'sha256' => 'fe8cce799250bddd6e4c6b8459b393afc5fed657be5e9875705afd53d21c58c6',
            'bytes' => 1600,
        ],
        'cr-only-rows.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'cr-only-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/cr-only-rows.tsv',
            'sha256' => '8deecde76e437a9a5d3f14ad235ba87a61f220e4098a109d8bf6f5907a90e8d9',
            'bytes' => 41,
        ],
        'cr-only-rows.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'cr-only-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/cr-only-rows.native',
            'sha256' => '38ce86f6c3c6861529f67360319de7ea26d17dfe9a6d5129e2625b0b9768c32d',
            'bytes' => 1126,
        ],
        'formula-looking-literals.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'formula-looking-literals',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/formula-looking-literals.tsv',
            'sha256' => '4d7c559919b0d5ae6fb444f02400addda086cd7d2e0a2db7d1f135049962a5e3',
            'bytes' => 81,
        ],
        'formula-looking-literals.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'formula-looking-literals',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/formula-looking-literals.native',
            'sha256' => 'cd5252c5c890122ababd2e5566f4ae9a02fcf1d23d6c718c6bf77cb72e42ffa5',
            'bytes' => 2185,
        ],
        'header-width-truncates-extra-fields.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'header-width-truncates-extra-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-width-truncates-extra-fields.tsv',
            'sha256' => 'ccb349be3f4f2f8e75e4b34ca3c93fc267bb7722f5f9500ad9bc8ed4675750fe',
            'bytes' => 12,
        ],
        'header-width-truncates-extra-fields.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'header-width-truncates-extra-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-width-truncates-extra-fields.native',
            'sha256' => '365fea12f7cbf4904d4e15db99b416c4555a997646a8cd90ec693c33c076bb4e',
            'bytes' => 1447,
        ],
        'numeric-looking-literals.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'numeric-looking-literals',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/numeric-looking-literals.tsv',
            'sha256' => 'e325f72f9fcf4ef9d835392ec483f86e7b6938fb85fe5425aecfe4163e7e4f16',
            'bytes' => 85,
        ],
        'numeric-looking-literals.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'numeric-looking-literals',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/numeric-looking-literals.native',
            'sha256' => 'd0b13fbe3429d44deb210a7c1f5f3f6723efae3110b837a2542692ffc48c94ae',
            'bytes' => 3265,
        ],
        'pre-tab-space.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'pre-tab-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/pre-tab-space.tsv',
            'sha256' => '7f2dfb3c381f25391f874dd0595c868c8d9cb62877063f96e10067349e062cea',
            'bytes' => 92,
        ],
        'pre-tab-space.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'pre-tab-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/pre-tab-space.native',
            'sha256' => '81161ab475209b25b9ce6848c52f17752e3972e62acfd60e160d85d5ee8aff19',
            'bytes' => 2671,
        ],
        'literal-quote-space-boundaries.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'literal-quote-space-boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-space-boundaries.tsv',
            'sha256' => '435768f05720cc81f3bcca83538fa8d21cb3ac6d8f5b1db46301a5233e00e4c7',
            'bytes' => 121,
        ],
        'literal-quote-space-boundaries.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'literal-quote-space-boundaries',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-space-boundaries.native',
            'sha256' => '9ca78daf730e41c6ee96496bbf856d1f3dd95e486da8afef939551e756d1ded7',
            'bytes' => 3345,
        ],
        'space-only-fields.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'space-only-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/space-only-fields.tsv',
            'sha256' => '498ff7e89da4ecd3f0589a5006d9bcfae4cb859040cb6f609ee2fd158eef1822',
            'bytes' => 68,
        ],
        'space-only-fields.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'space-only-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/space-only-fields.native',
            'sha256' => 'c8cd96df6c5e457474bcd7a199a54e3388fde74e27dde3e0a3ddca7da6777b83',
            'bytes' => 2514,
        ],
        'consecutive-tab-empty-field.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'consecutive-tab-empty-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/consecutive-tab-empty-field.tsv',
            'sha256' => '9d017e09daf50ea87f98311daa05e5e27b6175262d731732ae384a96dbe3b5ac',
            'bytes' => 49,
        ],
        'consecutive-tab-empty-field.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'consecutive-tab-empty-field',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/consecutive-tab-empty-field.native',
            'sha256' => 'd038f31032b5c3870f5912fc99686f7372595f30412ff45f7a4827ef6de70902',
            'bytes' => 2592,
        ],
    ];

    private const GENERATED_CSV_NATIVE_SAMPLES = [
        'quoted-multiline' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.native',
        ],
        'post-delimiter-space' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.native',
        ],
        'backslash-escaped-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.native',
            'options' => [
                'escape' => '\\',
            ],
        ],
        'quoted-linebreak' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.native',
        ],
        'no-header-ragged' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.native',
            'options' => [
                'header' => false,
            ],
        ],
        'bom-leading-whitespace' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.native',
        ],
        'text-after-closing-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.native',
            'options' => [
                'strictParsing' => false,
            ],
        ],
        'trailing-empty-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.native',
        ],
        'crlf-rows' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.native',
        ],
        'unquoted-space-empty-quoted' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.native',
            'options' => [
                'strictParsing' => false,
            ],
        ],
        'comment-looking-data' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.native',
        ],
        'no-header-edge-delimiters' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.native',
            'options' => [
                'header' => false,
            ],
        ],
        'single-quote-dialect' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.native',
            'options' => [
                'quote' => '\'',
            ],
        ],
        'semicolon-delimiter-multiline-cell' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.native',
            'options' => [
                'delimiter' => 'semicolon',
            ],
        ],
        'cr-only-rows' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.native',
        ],
        'unterminated-quote-eof' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.native',
            'options' => [
                'strictParsing' => false,
            ],
        ],
        'duplicate-header-labels' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.native',
        ],
        'keep-space-after-comma' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.native',
            'options' => [
                'keepSpace' => true,
            ],
        ],
        'space-delimiter-single-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.native',
            'options' => [
                'delimiter' => 'space',
                'quote' => '\'',
            ],
        ],
        'blank-row-skipped' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.native',
        ],
        'backslash-escaped-nonquote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.native',
            'options' => [
                'escape' => '\\',
            ],
        ],
        'pipe-delimiter-quoted-field' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.native',
            'options' => [
                'delimiter' => 'pipe',
            ],
        ],
        'quote-disabled-literal' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.native',
            'options' => [
                'quote' => false,
            ],
        ],
        'blank-input' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.native',
        ],
        'unicode-safe' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.native',
        ],
        'quote-in-unquoted-field' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.native',
        ],
        'header-only' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-only.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-only.native',
        ],
        'leading-whitespace-record' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.native',
            'options' => [
                'strictParsing' => true,
            ],
        ],
        'leading-blank-whitespace' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.native',
        ],
        'quoted-final-vtab-whitespace' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.native',
        ],
        'unquoted-final-formfeed' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.native',
        ],
        'space-only-record' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.native',
        ],
        'quoted-trailing-linebreak' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-linebreak.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-linebreak.native',
        ],
        'leading-empty-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.native',
        ],
        'quoted-header-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.native',
        ],
        'unquoted-tab-cell' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.native',
        ],
        'quoted-empty-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.native',
        ],
        'leading-whitespace-before-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.native',
        ],
        'post-delimiter-tab' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.native',
        ],
        'quoted-blank-line-cell' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.native',
        ],
        'blank-leading-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.native',
        ],
        'pre-delimiter-space' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.native',
        ],
        'markdown-syntax-literal' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.native',
        ],
        'formula-looking-literals' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.native',
        ],
        'literal-backslashes' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/literal-backslashes.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/literal-backslashes.native',
        ],
        'quoted-quotes-only' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-quotes-only.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-quotes-only.native',
        ],
        'interior-empty-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/interior-empty-header.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/interior-empty-header.native',
        ],
        'header-width-truncates-extra-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-width-truncates-extra-fields.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-width-truncates-extra-fields.native',
        ],
        'quoted-edge-spaces' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-edge-spaces.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-edge-spaces.native',
        ],
        'quoted-multiline-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline-header.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline-header.native',
        ],
        'quoted-empty-headers' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-headers.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-headers.native',
        ],
        'post-delimiter-empty-quoted' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-empty-quoted.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-empty-quoted.native',
        ],
        'quoted-final-row-padding' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-row-padding.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-row-padding.native',
        ],
        'quoted-crlf-linebreak' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-crlf-linebreak.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-crlf-linebreak.native',
        ],
        'quoted-bare-cr-normalized' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-bare-cr-normalized.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-bare-cr-normalized.native',
        ],
        'quoted-delimiter-blank-short-row' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-delimiter-blank-short-row.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-delimiter-blank-short-row.native',
        ],
        'delimiter-only-row' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/delimiter-only-row.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/delimiter-only-row.native',
        ],
        'quoted-delimiter-boundaries' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-delimiter-boundaries.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-delimiter-boundaries.native',
        ],
        'quoted-softbreak' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-softbreak.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-softbreak.native',
            'options' => [
                'cellLineBreak' => 'softbreak',
            ],
        ],
        'bang-escaped-csv-options' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bang-escaped-csv-options.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bang-escaped-csv-options.native',
            'options' => [
                'escape' => '!',
            ],
        ],
        'single-quote-double-quote-literal' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-double-quote-literal.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-double-quote-literal.native',
            'options' => [
                'quote' => '\'',
            ],
        ],
        'keep-space-before-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-before-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-before-quote.native',
            'options' => [
                'keepSpace' => true,
            ],
        ],
        'quoted-trailing-empty-field' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-empty-field.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-trailing-empty-field.native',
        ],
        'quoted-unicode-doubled-quotes' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-unicode-doubled-quotes.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-unicode-doubled-quotes.native',
        ],
        'partial-final-record' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/partial-final-record.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/partial-final-record.native',
            'options' => [
                'strictParsing' => false,
            ],
        ],
        'quoted-final-space-whitespace' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-space-whitespace.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-space-whitespace.native',
        ],
        'quoted-doubled-linebreak-ragged' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-doubled-linebreak-ragged.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-doubled-linebreak-ragged.native',
        ],
        'quoted-tab-cell' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-tab-cell.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-tab-cell.native',
        ],
        'backslash-escaped-escape' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-escape.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-escape.native',
            'options' => [
                'escape' => '\\',
            ],
        ],
        'backslash-before-closing-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-before-closing-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-before-closing-quote.native',
        ],
        'closing-quote-record-whitespace-recovery' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/closing-quote-record-whitespace-recovery.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/closing-quote-record-whitespace-recovery.native',
            'options' => [
                'strictParsing' => false,
            ],
        ],
    ];

    private const GENERATED_TSV_NATIVE_SAMPLES = [
        'simple' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.native',
        ],
        'quote-trailing' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.native',
        ],
        'unicode-safe' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.native',
        ],
        'ragged-blank-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.native',
        ],
        'no-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.native',
            'options' => [
                'header' => false,
            ],
        ],
        'bom-leading-whitespace' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.native',
        ],
        'blank-row-literal-punctuation' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.native',
        ],
        'comment-looking-data' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.native',
        ],
        'no-header-edge-delimiters' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.native',
            'options' => [
                'header' => false,
            ],
        ],
        'csv-quoted-literal' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.native',
        ],
        'keep-space-after-tab' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.native',
            'options' => [
                'keepSpace' => true,
            ],
        ],
        'crlf-rows' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.native',
        ],
        'quoted-tabs-and-newlines' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.native',
            'options' => [
                'quote' => '"',
            ],
        ],
        'blank-leading-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.native',
        ],
        'basic-status' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.native',
        ],
        'header-only' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.native',
        ],
        'no-header-internal-trailing-empty' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.native',
            'options' => [
                'header' => false,
            ],
        ],
        'blank-input' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.native',
        ],
        'duplicate-header-labels' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.native',
        ],
        'escaped-quote-dialect' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.native',
            'options' => [
                'quote' => '"',
                'escape' => '\\',
            ],
        ],
        'literal-quote-tab-split' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.native',
        ],
        'leading-blank-whitespace' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.native',
        ],
        'unquoted-final-formfeed' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.native',
        ],
        'literal-quote-newline-split' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.native',
        ],
        'leading-empty-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.native',
        ],
        'trailing-empty-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.native',
        ],
        'literal-quote-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.native',
        ],
        'markdown-syntax-literal' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.native',
        ],
        'interior-empty-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.native',
        ],
        'trailing-empty-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.native',
        ],
        'quoted-softbreak' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-softbreak.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-softbreak.native',
            'options' => [
                'quote' => '"',
                'cellLineBreak' => 'softbreak',
            ],
        ],
        'post-delimiter-space' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/post-delimiter-space.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/post-delimiter-space.native',
        ],
        'delimiter-only-row' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/delimiter-only-row.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/delimiter-only-row.native',
        ],
        'single-quote-dialect' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/single-quote-dialect.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/single-quote-dialect.native',
            'options' => [
                'quote' => '\'',
            ],
        ],
        'escaped-tab-dialect' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-tab-dialect.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-tab-dialect.native',
            'options' => [
                'quote' => '"',
                'escape' => '\\',
            ],
        ],
        'pipe-delimiter-quoted-field' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/pipe-delimiter-quoted-field.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/pipe-delimiter-quoted-field.native',
            'options' => [
                'delimiter' => 'pipe',
                'quote' => '"',
            ],
        ],
        'cr-only-rows' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/cr-only-rows.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/cr-only-rows.native',
        ],
        'formula-looking-literals' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/formula-looking-literals.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/formula-looking-literals.native',
        ],
        'header-width-truncates-extra-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-width-truncates-extra-fields.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-width-truncates-extra-fields.native',
        ],
        'numeric-looking-literals' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/numeric-looking-literals.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/numeric-looking-literals.native',
        ],
        'pre-tab-space' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/pre-tab-space.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/pre-tab-space.native',
        ],
        'literal-quote-space-boundaries' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-space-boundaries.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-space-boundaries.native',
        ],
        'space-only-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/space-only-fields.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/space-only-fields.native',
        ],
        'consecutive-tab-empty-field' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/consecutive-tab-empty-field.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/consecutive-tab-empty-field.native',
        ],
    ];

    private const PANDOC_EXECUTABLE_CSV_NATIVE_SAMPLE_NAMES = [
        'quoted-multiline',
        'post-delimiter-space',
        'quoted-linebreak',
        'trailing-empty-fields',
        'crlf-rows',
        'comment-looking-data',
        'cr-only-rows',
        'duplicate-header-labels',
        'blank-input',
        'unicode-safe',
        'quote-in-unquoted-field',
        'header-only',
        'leading-whitespace-record',
        'leading-blank-whitespace',
        'quoted-final-vtab-whitespace',
        'unquoted-final-formfeed',
        'space-only-record',
        'quoted-trailing-linebreak',
        'leading-empty-fields',
        'quoted-header-fields',
        'unquoted-tab-cell',
        'quoted-empty-fields',
        'leading-whitespace-before-quote',
        'post-delimiter-tab',
        'quoted-blank-line-cell',
        'blank-leading-header',
        'pre-delimiter-space',
        'markdown-syntax-literal',
        'formula-looking-literals',
        'literal-backslashes',
        'quoted-quotes-only',
        'interior-empty-header',
        'header-width-truncates-extra-fields',
        'quoted-edge-spaces',
        'quoted-multiline-header',
        'quoted-empty-headers',
        'post-delimiter-empty-quoted',
        'quoted-final-row-padding',
        'quoted-crlf-linebreak',
        'quoted-bare-cr-normalized',
        'quoted-delimiter-blank-short-row',
        'delimiter-only-row',
        'quoted-delimiter-boundaries',
        'quoted-unicode-doubled-quotes',
        'quoted-trailing-empty-field',
        'partial-final-record',
        'quoted-final-space-whitespace',
        'quoted-doubled-linebreak-ragged',
        'quoted-tab-cell',
        'backslash-before-closing-quote',
    ];

    private const PANDOC_EXECUTABLE_TSV_NATIVE_SAMPLE_NAMES = [
        'simple',
        'quote-trailing',
        'unicode-safe',
        'ragged-blank-fields',
        'comment-looking-data',
        'csv-quoted-literal',
        'crlf-rows',
        'blank-leading-header',
        'basic-status',
        'header-only',
        'blank-input',
        'duplicate-header-labels',
        'literal-quote-tab-split',
        'leading-blank-whitespace',
        'unquoted-final-formfeed',
        'literal-quote-newline-split',
        'leading-empty-fields',
        'trailing-empty-fields',
        'literal-quote-header',
        'markdown-syntax-literal',
        'interior-empty-header',
        'trailing-empty-header',
        'post-delimiter-space',
        'delimiter-only-row',
        'cr-only-rows',
        'formula-looking-literals',
        'header-width-truncates-extra-fields',
        'numeric-looking-literals',
        'literal-quote-space-boundaries',
        'space-only-fields',
        'consecutive-tab-empty-field',
    ];

    private const SOURCE_FILES = [
        'src/Text/Pandoc/CSV.hs',
        'src/Text/Pandoc/Readers/CSV.hs',
    ];

    private const CSV_ADJACENT_RST_FIXTURES = [
        [
            'path' => 'test/command/3533-rst-csv-tables.csv',
            'role' => 'rst-csv-table-included-csv-data',
            'reader' => 'rst',
            'directDelimitedTextReaderFixture' => false,
            'reason' => 'CSV data referenced by an RST csv-table directive; it is exercised through the RST reader integration path, not as direct CSV reader input.',
        ],
        [
            'path' => 'test/command/3533-rst-csv-tables.md',
            'role' => 'rst-csv-table-command-output-fixture',
            'reader' => 'rst',
            'directDelimitedTextReaderFixture' => false,
            'reason' => 'Command fixture for RST csv-table output; the reader under test is RST, not the direct CSV or TSV reader.',
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
        $generatedCsvNativeParityEvidence = self::generatedCsvNativeParityEvidence($this->repoRoot);
        $generatedTsvNativeParityEvidence = self::generatedTsvNativeParityEvidence($this->repoRoot);
        $currentCsvDirectNativeParityEvidence = self::currentCsvDirectNativeParityEvidence($this->repoRoot);
        $currentTsvDirectNativeParityEvidence = self::currentTsvDirectNativeParityEvidence($this->repoRoot);
        $runnerEvidence = $this->runnerEvidence();
        if (!is_dir($root)) {
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
                'denominator' => $this->emptyDenominator(),
                'sourceInventory' => $this->emptySourceInventory(),
                'staticCurrentEvidence' => self::checkedInCurrentEvidence($this->repoRoot),
                'generatedCsvNativeParityEvidence' => $generatedCsvNativeParityEvidence,
                'generatedTsvNativeParityEvidence' => $generatedTsvNativeParityEvidence,
                'currentCsvDirectNativeParityEvidence' => $currentCsvDirectNativeParityEvidence,
                'currentTsvDirectNativeParityEvidence' => $currentTsvDirectNativeParityEvidence,
                'runnerEvidence' => $runnerEvidence,
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $upstreamFixtures = $this->upstreamFixtureEvidence($root);
        $sourceInventory = $this->sourceInventory($root);
        $validationIssues = $this->validationIssues($upstreamFixtures, $sourceInventory, $generatedCsvNativeParityEvidence, $generatedTsvNativeParityEvidence, $currentCsvDirectNativeParityEvidence, $currentTsvDirectNativeParityEvidence, $runnerEvidence);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/command',
                'readerSources' => self::SOURCE_FILES,
            ],
            'denominator' => [
                'csvDirectFixtureCount' => count(self::CHECKED_IN_CURRENT_CSV_FIXTURES),
                'tsvDirectFixtureCount' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
                'fixtureScope' => 'direct CSV and TSV command reader fixtures in test/command',
                'csvDirectFixtures' => array_values(array_map(
                    static fn (array $fixture): string => (string) $fixture['upstreamPath'],
                    self::CHECKED_IN_CURRENT_CSV_FIXTURES
                )),
                'tsvDirectFixtures' => self::tsvDirectFixturePaths(),
                'csvAdjacentRstFixtureCount' => self::EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT,
                'csvAdjacentRstFixtures' => self::csvAdjacentRstFixturePaths(),
                'adjacentFixtureDenominatorImpact' => 0,
                'adjacentFixtureEvidence' => self::csvAdjacentRstFixtureEvidence(),
                'upstreamFixtures' => $upstreamFixtures,
                'parserOptionFixtureCount' => self::EXPECTED_CSV_PARSER_OPTION_FIXTURE_COUNT,
                'parserOptionFixtures' => self::csvParserOptionFixtureNames(),
                'currentCsvDirectNativePairCount' => self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT,
                'currentCsvDirectNativeFixtures' => array_values(array_map(
                    static fn (array $sample): string => (string) $sample['transcriptPath'],
                    self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES
                )),
                'currentTsvDirectNativePairCount' => self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT,
                'currentTsvDirectNativeFixtures' => array_values(array_map(
                    static fn (array $sample): string => (string) $sample['inputPath'],
                    self::CURRENT_TSV_DIRECT_NATIVE_SAMPLES
                )),
            ],
            'sourceInventory' => $sourceInventory,
            'staticCurrentEvidence' => self::checkedInCurrentEvidence($this->repoRoot),
            'generatedCsvNativeParityEvidence' => $generatedCsvNativeParityEvidence,
            'generatedTsvNativeParityEvidence' => $generatedTsvNativeParityEvidence,
            'currentCsvDirectNativeParityEvidence' => $currentCsvDirectNativeParityEvidence,
            'currentTsvDirectNativeParityEvidence' => $currentTsvDirectNativeParityEvidence,
            'runnerEvidence' => $runnerEvidence,
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-delimited-text-reader-evidence' : 'invalid-upstream-delimited-text-reader-evidence',
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
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $tsvFixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_TSV_FIXTURE_DIRECTORY);
        $generatedCsvNativeStaticEvidence = self::checkedInGeneratedCsvNativeEvidence($root);
        $generatedTsvNativeStaticEvidence = self::checkedInGeneratedTsvNativeEvidence($root);
        $currentCsvDirectNativeStaticEvidence = self::checkedInCurrentCsvDirectNativeEvidence($root);
        $currentTsvDirectNativeStaticEvidence = self::checkedInCurrentTsvDirectNativeEvidence($root);
        $adjacentFixtureEvidence = self::csvAdjacentRstFixtureEvidence();
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-fixture-directory';
        }
        if (!is_dir($tsvFixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-tsv-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_CSV_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'upstreamPath' => (string) $snapshot['upstreamPath'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-csv-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-current-csv-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-current-csv-fixture-byte-count-mismatch';
            }
        }

        $tsvFixtures = [];
        foreach (self::CHECKED_IN_CURRENT_TSV_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $tsvFixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'upstreamPath' => (string) $snapshot['upstreamPath'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-tsv-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-current-tsv-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-current-tsv-fixture-byte-count-mismatch';
            }
        }

        if (!self::hasRequiredGeneratedCsvNativeStaticEvidence($generatedCsvNativeStaticEvidence)) {
            $issues[] = 'invalid-checked-in-generated-csv-native-fixture-evidence';
        }

        if (!self::hasRequiredGeneratedTsvNativeStaticEvidence($generatedTsvNativeStaticEvidence)) {
            $issues[] = 'invalid-checked-in-generated-tsv-native-fixture-evidence';
        }

        if (!self::hasRequiredCurrentCsvDirectNativeStaticEvidence($currentCsvDirectNativeStaticEvidence)) {
            $issues[] = 'invalid-checked-in-current-csv-direct-native-transcript-evidence';
        }

        if (!self::hasRequiredCurrentTsvDirectNativeStaticEvidence($currentTsvDirectNativeStaticEvidence)) {
            $issues[] = 'invalid-checked-in-current-tsv-direct-native-fixture-evidence';
        }

        if (!self::hasRequiredCsvAdjacentRstFixtureEvidence($adjacentFixtureEvidence)) {
            $issues[] = 'invalid-csv-adjacent-rst-fixture-evidence';
        }

        return [
            'kind' => 'static-checked-in-current-upstream-delimited-text-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/command',
                'readerSources' => self::SOURCE_FILES,
            ],
            'readerDenominator' => [
                'csvDirectFixtureCount' => count(self::CHECKED_IN_CURRENT_CSV_FIXTURES),
                'tsvDirectFixtureCount' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
                'fixtureScope' => 'direct CSV and TSV command reader fixtures in test/command',
                'csvDirectFixtures' => array_values(array_map(
                    static fn (array $fixture): string => (string) $fixture['upstreamPath'],
                    self::CHECKED_IN_CURRENT_CSV_FIXTURES
                )),
                'tsvDirectFixtures' => self::tsvDirectFixturePaths(),
                'csvAdjacentRstFixtureCount' => self::EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT,
                'csvAdjacentRstFixtures' => self::csvAdjacentRstFixturePaths(),
                'adjacentFixtureDenominatorImpact' => 0,
                'parserOptionFixtureCount' => self::EXPECTED_CSV_PARSER_OPTION_FIXTURE_COUNT,
                'parserOptionFixtures' => self::csvParserOptionFixtureNames(),
                'currentCsvDirectNativePairCount' => self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT,
                'currentCsvDirectNativeFixtures' => array_values(array_map(
                    static fn (array $sample): string => (string) $sample['transcriptPath'],
                    self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES
                )),
                'currentTsvDirectNativePairCount' => self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT,
                'currentTsvDirectNativeFixtures' => array_values(array_map(
                    static fn (array $sample): string => (string) $sample['inputPath'],
                    self::CURRENT_TSV_DIRECT_NATIVE_SAMPLES
                )),
            ],
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'checkedInTsvFixtureDirectory' => self::CHECKED_IN_CURRENT_TSV_FIXTURE_DIRECTORY,
            'checkedInTsvFixtureCount' => count($tsvFixtures),
            'checkedInTsvFixtures' => $tsvFixtures,
            'adjacentFixtureEvidence' => $adjacentFixtureEvidence,
            'generatedCsvNativeStaticEvidence' => $generatedCsvNativeStaticEvidence,
            'generatedTsvNativeStaticEvidence' => $generatedTsvNativeStaticEvidence,
            'currentCsvDirectNativeStaticEvidence' => $currentCsvDirectNativeStaticEvidence,
            'currentTsvDirectNativeStaticEvidence' => $currentTsvDirectNativeStaticEvidence,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-delimited-text-reader-evidence' : 'invalid-checked-in-current-delimited-text-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding Pandoc current CSV command-reader fixtures, the first direct TSV command fixture, two embedded CSV command transcript native probes, two current TSV direct native pairs, and generated CSV/TSV native sample fixtures to checked-in SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the checked-in csv.md, 01.csv, and 9797.md snapshots match the pinned upstream command fixture hashes',
                    'the upstream command corpus has three CSV direct-reader fixtures tracked by this PHP reader',
                    'the checked-in 8661.md snapshot matches the pinned upstream TSV command fixture hash and counts as one TSV direct fixture',
                    'the checked-in csv.md and 9797.md command transcripts expose two embedded CSV input/native pairs for local reader comparison',
                    'the RST csv-table fixture pair is tracked as CSV-adjacent evidence with zero direct-reader denominator impact',
                    'the CSV parser-option fixture names are pinned as local generated native parity samples',
                    'the generated CSV-to-native parity fixture pairs are present as local evidence and are not counted as extra upstream CSV direct fixtures',
                    'the current TSV direct native fixture pairs are present and separate from the generated TSV sample corpus',
                    'the generated TSV-to-native parity fixture pairs are present as local evidence and are not counted as upstream TSV direct fixtures',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that the TSV 8661.md GFM pipe-table output is implemented by the local writer',
                    'full CSV/TSV feature parity beyond the direct reader fixtures and local parser-option cases',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInGeneratedCsvNativeEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-generated-csv-native-fixture-directory';
        }
        $fixtureInventory = self::generatedNativeFixtureInventory(
            $root,
            self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY,
            self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES,
            'csv'
        );

        $fixtures = [];
        foreach (self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sample' => (string) $snapshot['sample'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-generated-csv-native-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-generated-csv-native-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-generated-csv-native-fixture-byte-count-mismatch';
            }
        }
        if (($fixtureInventory['status'] ?? null) !== 'valid-generated-csv-native-fixture-inventory') {
            $issues[] = 'checked-in-generated-csv-native-fixture-inventory-drift';
        }

        $samples = [];
        foreach (self::GENERATED_CSV_NATIVE_SAMPLES as $name => $sample) {
            $samples[] = [
                'name' => (string) $name,
                'inputPath' => (string) $sample['inputPath'],
                'expectedNativePath' => (string) $sample['expectedNativePath'],
                'readerOptions' => is_array($sample['options'] ?? null) ? $sample['options'] : [],
            ];
        }

        return [
            'kind' => 'static-checked-in-generated-csv-native-parity-fixture-evidence',
            'evidenceKind' => 'generated-csv-native-parity-fixtures',
            'checkedInFixtureDirectory' => self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY,
            'sampleCount' => count($samples),
            'samples' => $samples,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'fixtureInventory' => $fixtureInventory,
            'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-generated-csv-native-parity-evidence' : 'invalid-checked-in-generated-csv-native-parity-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static local CSV-to-native fixture evidence; these fixture pairs are generated evidence and are not additional upstream CSV direct-reader fixtures.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the generated CSV input and expected native output fixture files match checked-in SHA-256 and byte-count snapshots',
                    'the generated CSV native sample count is separate from the upstream CSV direct fixture denominator',
                ],
                'doesNotAssert' => [
                    'that upstream adds dedicated CSV command fixtures for these generated samples',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated CSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function csvAdjacentRstFixtureEvidence(): array
    {
        return [
            'kind' => 'csv-adjacent-rst-csv-table-fixture-evidence',
            'relationship' => 'adjacent-rst-reader-fixtures-not-direct-delimited-text',
            'fixtureScope' => 'RST reader csv-table integration fixture pair in test/command',
            'reader' => 'rst',
            'directive' => 'csv-table',
            'fixtureCount' => count(self::CSV_ADJACENT_RST_FIXTURES),
            'fixtures' => self::CSV_ADJACENT_RST_FIXTURES,
            'csvDirectFixtureDenominatorImpact' => 0,
            'tsvDirectFixtureDenominatorImpact' => 0,
            'claim' => 'Tracks the RST csv-table fixture pair as adjacent context only; these files do not increase the direct CSV or TSV reader denominator.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the RST csv-table fixture pair is not counted as direct CSV or TSV reader fixtures',
                    'the adjacent fixture pair has zero direct-reader denominator impact',
                    'that native PHP exercises RST csv-table directives through the RST reader integration path',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that these adjacent fixtures close direct CSV or TSV reader parity',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function csvAdjacentRstFixturePaths(): array
    {
        return array_values(array_map(
            static fn (array $fixture): string => (string) $fixture['path'],
            self::CSV_ADJACENT_RST_FIXTURES
        ));
    }

    /**
     * @return list<string>
     */
    public static function tsvDirectFixturePaths(): array
    {
        return array_values(array_map(
            static fn (array $fixture): string => (string) $fixture['upstreamPath'],
            self::CHECKED_IN_CURRENT_TSV_FIXTURES
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInGeneratedTsvNativeEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-generated-tsv-native-fixture-directory';
        }
        $fixtureInventory = self::generatedNativeFixtureInventory(
            $root,
            self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY,
            self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES,
            'tsv'
        );

        $fixtures = [];
        foreach (self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sample' => (string) $snapshot['sample'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-generated-tsv-native-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-generated-tsv-native-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-generated-tsv-native-fixture-byte-count-mismatch';
            }
        }
        if (($fixtureInventory['status'] ?? null) !== 'valid-generated-tsv-native-fixture-inventory') {
            $issues[] = 'checked-in-generated-tsv-native-fixture-inventory-drift';
        }

        $samples = [];
        foreach (self::GENERATED_TSV_NATIVE_SAMPLES as $name => $sample) {
            $samples[] = [
                'name' => (string) $name,
                'inputPath' => (string) $sample['inputPath'],
                'expectedNativePath' => (string) $sample['expectedNativePath'],
                'readerOptions' => is_array($sample['options'] ?? null) ? $sample['options'] : [],
            ];
        }

        return [
            'kind' => 'static-checked-in-generated-tsv-native-parity-fixture-evidence',
            'evidenceKind' => 'generated-tsv-native-parity-fixtures',
            'checkedInFixtureDirectory' => self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY,
            'sampleCount' => count($samples),
            'samples' => $samples,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'fixtureInventory' => $fixtureInventory,
            'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-generated-tsv-native-parity-evidence' : 'invalid-checked-in-generated-tsv-native-parity-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static local TSV-to-native fixture evidence; these fixture pairs are generated evidence and are not upstream TSV direct-reader fixtures.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the generated TSV input and expected native output fixture files match checked-in SHA-256 and byte-count snapshots',
                    'the generated TSV native sample count is separate from the upstream TSV direct fixture denominator',
                ],
                'doesNotAssert' => [
                    'that generated TSV native samples are upstream TSV command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated TSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInCurrentCsvDirectNativeEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-csv-direct-native-transcript-directory';
        }

        $fixtures = [];
        foreach (self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES as $sampleName => $sample) {
            $fixtureName = (string) $sample['fixtureName'];
            $snapshot = self::CHECKED_IN_CURRENT_CSV_FIXTURES[$fixtureName] ?? null;
            if (!is_array($snapshot)) {
                $issues[] = 'missing-current-csv-direct-native-snapshot-binding';
                continue;
            }

            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $transcript = ($file['present'] ?? false) === true ? file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $snapshot['checkedInPath'])) : false;
            $transcriptPair = is_string($transcript) ? self::csvCommandNativeTranscript($transcript) : null;
            $fixtures[] = [
                'name' => $fixtureName,
                'role' => (string) $snapshot['role'],
                'sample' => (string) $sampleName,
                'upstreamPath' => (string) $sample['upstreamPath'],
                'checkedInFile' => $file,
                'transcriptStatus' => $transcriptPair === null ? 'invalid-current-csv-direct-native-transcript' : 'valid-current-csv-direct-native-transcript',
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-csv-direct-native-transcript';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-current-csv-direct-native-transcript-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-current-csv-direct-native-transcript-byte-count-mismatch';
            } elseif ($transcriptPair === null) {
                $issues[] = 'checked-in-current-csv-direct-native-transcript-invalid';
            }
        }

        $samples = [];
        foreach (self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES as $name => $sample) {
            $samples[] = [
                'name' => (string) $name,
                'fixtureName' => (string) $sample['fixtureName'],
                'transcriptPath' => (string) $sample['transcriptPath'],
                'upstreamPath' => (string) $sample['upstreamPath'],
                'readerOptions' => [],
            ];
        }

        return [
            'kind' => 'static-checked-in-current-csv-direct-native-transcript-evidence',
            'evidenceKind' => 'current-csv-direct-native-transcripts',
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'reader' => 'csv',
            'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            'currentCsvDirectNativePairCount' => self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT,
            'sampleCount' => count($samples),
            'samples' => $samples,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-csv-direct-native-transcript-evidence' : 'invalid-checked-in-current-csv-direct-native-transcript-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static current CSV direct native transcript evidence; this binds embedded upstream command transcript pairs without adding new direct CSV fixture files.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'two current CSV command transcripts are checked in with pinned SHA-256 and byte-count snapshots',
                    'the checked-in csv.md and 9797.md transcripts contain extractable command input and expected native output blocks',
                    'the pair count is separate from generated CSV native parity samples',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that 01.csv contains embedded native output',
                    'full CSV/TSV feature parity beyond these command transcript probes and the generated CSV sample corpus',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInCurrentTsvDirectNativeEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_TSV_NATIVE_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-tsv-direct-native-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_TSV_NATIVE_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sample' => (string) $snapshot['sample'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-tsv-direct-native-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-current-tsv-direct-native-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-current-tsv-direct-native-fixture-byte-count-mismatch';
            }
        }

        $samples = [];
        foreach (self::CURRENT_TSV_DIRECT_NATIVE_SAMPLES as $name => $sample) {
            $samples[] = [
                'name' => (string) $name,
                'inputPath' => (string) $sample['inputPath'],
                'expectedNativePath' => (string) $sample['expectedNativePath'],
                'readerOptions' => [],
            ];
        }

        return [
            'kind' => 'static-checked-in-current-tsv-direct-native-fixture-evidence',
            'evidenceKind' => 'current-tsv-direct-native-fixtures',
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_TSV_NATIVE_FIXTURE_DIRECTORY,
            'reader' => 'tsv',
            'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            'currentTsvDirectNativePairCount' => self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT,
            'sampleCount' => count($samples),
            'samples' => $samples,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-tsv-direct-native-fixture-evidence' : 'invalid-checked-in-current-tsv-direct-native-fixture-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static current TSV direct native fixture evidence; these input/native pairs include one local baseline and one exact TSV input extracted from upstream test/command/8661.md.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'two current TSV direct input/native fixture pairs are checked in with pinned SHA-256 and byte-count snapshots',
                    'the upstream-8661 pair captures the TSV input embedded in test/command/8661.md',
                ],
                'doesNotAssert' => [
                    'that the TSV 8661.md GFM writer pipe-table output is implemented by the local writer',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full TSV feature parity beyond these current direct probes and the generated TSV sample corpus',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function generatedCsvNativeParityEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = self::checkedInGeneratedCsvNativeEvidence($root);
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach (self::GENERATED_CSV_NATIVE_SAMPLES as $name => $sample) {
            $inputPath = (string) $sample['inputPath'];
            $expectedNativePath = (string) $sample['expectedNativePath'];
            $readerOptions = is_array($sample['options'] ?? null) ? $sample['options'] : [];
            $readerOptions['sourcePath'] = $inputPath;
            $staticFixtureBinding = self::generatedNativeSampleStaticBinding($staticEvidence, 'csv', (string) $name);
            $absoluteInputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $absoluteExpectedNativePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNativePath);
            $input = is_file($absoluteInputPath) ? file_get_contents($absoluteInputPath) : false;
            $expectedNative = is_file($absoluteExpectedNativePath) ? file_get_contents($absoluteExpectedNativePath) : false;

            if (!is_string($input) || !is_string($expectedNative)) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => is_string($input) ? null : 'missing-or-unreadable-csv-input-fixture',
                    'expectedNativeError' => is_string($expectedNative) ? null : 'missing-or-unreadable-native-fixture',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                    'staticFixtureBinding' => $staticFixtureBinding,
                    ...$failure,
                ];
                continue;
            }

            try {
                $executionReaderOptions = $readerOptions;
                if (!array_key_exists('strictParsing', $executionReaderOptions)) {
                    $executionReaderOptions['strictParsing'] = false;
                }
                $document = (new DelimitedTextReader())->readCsv($input, $executionReaderOptions);
                $generatedNative = PandocConverter::write($document, 'native');
            } catch (\Throwable $throwable) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => $throwable::class . ': ' . $throwable->getMessage(),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                    'staticFixtureBinding' => $staticFixtureBinding,
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($expectedNative);
            $generatedTokens = self::nativeTokenStream($generatedNative);
            $matched = $expectedTokens === $generatedTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'firstDifference' => self::firstStringDifference($expectedTokens, $generatedTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $table = $document->children[0] ?? null;
            $packet = $table instanceof AstNode ? $table->attr('delimitedText', []) : [];
            $sampleResults[] = [
                'name' => (string) $name,
                'status' => $matched ? 'matched' : 'mismatched',
                'inputPath' => $inputPath,
                'expectedNativePath' => $expectedNativePath,
                'readerOptions' => $readerOptions,
                'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                'staticFixtureBinding' => $staticFixtureBinding,
                'reader' => 'csv',
                'expectedNativeSha256' => hash('sha256', $expectedNative),
                'generatedNativeSha256' => hash('sha256', $generatedNative),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'generatedNativeTokenSha256' => hash('sha256', $generatedTokens),
                'rowCount' => is_array($packet) ? ($packet['rowCount'] ?? null) : null,
                'columnCount' => is_array($packet) ? ($packet['columnCount'] ?? null) : null,
                'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            ];
        }

        $sampleCount = count(self::GENERATED_CSV_NATIVE_SAMPLES);
        $mismatchCount = $comparedCount - $matchCount;
        $staticEvidenceValid = self::hasRequiredGeneratedCsvNativeStaticEvidence($staticEvidence);
        $validStaticFixtureBindingCount = self::validGeneratedNativeSampleStaticBindingCount($sampleResults, 'csv');
        $invalidStaticFixtureBindingCount = $sampleCount - $validStaticFixtureBindingCount;

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => 'executable-generated-csv-native-parity-evidence',
            'evidenceKind' => 'generated-csv-native-parity',
            'status' => 'completed-generated-csv-native-parity-evidence',
            'claim' => 'Executes the local PHP CSV reader and native writer for checked-in generated CSV samples; these are not extra upstream CSV direct fixtures or upstream runner results.',
            'fixtureDirectory' => self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY,
            'reader' => 'csv',
            'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'generatedNativeMatchCount' => $matchCount,
            'generatedNativeMismatchCount' => $mismatchCount,
            'generatedNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'staticFixtureBindingValidCount' => $validStaticFixtureBindingCount,
            'staticFixtureBindingInvalidCount' => $invalidStaticFixtureBindingCount,
            'parityStatus' => self::generatedCsvNativeParityStatus($staticEvidenceValid && $invalidStaticFixtureBindingCount === 0, count($parseFailures), $mismatchCount, $comparedCount, $sampleCount),
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'the local CSV reader can read the checked-in generated CSV samples',
                    'the generated native output matches the checked-in expected native fixtures by normalized native token stream',
                    'each executable generated CSV sample is bound to valid checked-in input and native snapshot evidence',
                    'the upstream CSV direct fixture denominator remains three',
                ],
                'doesNotAssert' => [
                    'that the generated CSV samples are upstream command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated CSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function generatedTsvNativeParityEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = self::checkedInGeneratedTsvNativeEvidence($root);
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach (self::GENERATED_TSV_NATIVE_SAMPLES as $name => $sample) {
            $inputPath = (string) $sample['inputPath'];
            $expectedNativePath = (string) $sample['expectedNativePath'];
            $readerOptions = is_array($sample['options'] ?? null) ? $sample['options'] : [];
            $readerOptions['sourcePath'] = $inputPath;
            $staticFixtureBinding = self::generatedNativeSampleStaticBinding($staticEvidence, 'tsv', (string) $name);
            $absoluteInputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $absoluteExpectedNativePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNativePath);
            $input = is_file($absoluteInputPath) ? file_get_contents($absoluteInputPath) : false;
            $expectedNative = is_file($absoluteExpectedNativePath) ? file_get_contents($absoluteExpectedNativePath) : false;

            if (!is_string($input) || !is_string($expectedNative)) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => is_string($input) ? null : 'missing-or-unreadable-tsv-input-fixture',
                    'expectedNativeError' => is_string($expectedNative) ? null : 'missing-or-unreadable-native-fixture',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                    'staticFixtureBinding' => $staticFixtureBinding,
                    ...$failure,
                ];
                continue;
            }

            try {
                $executionReaderOptions = $readerOptions;
                if (!array_key_exists('strictParsing', $executionReaderOptions)) {
                    $executionReaderOptions['strictParsing'] = false;
                }
                $document = (new DelimitedTextReader())->readTsv($input, $executionReaderOptions);
                $generatedNative = PandocConverter::write($document, 'native');
            } catch (\Throwable $throwable) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => $throwable::class . ': ' . $throwable->getMessage(),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                    'staticFixtureBinding' => $staticFixtureBinding,
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($expectedNative);
            $generatedTokens = self::nativeTokenStream($generatedNative);
            $matched = $expectedTokens === $generatedTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'firstDifference' => self::firstStringDifference($expectedTokens, $generatedTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $table = $document->children[0] ?? null;
            $packet = $table instanceof AstNode ? $table->attr('delimitedText', []) : [];
            $sampleResults[] = [
                'name' => (string) $name,
                'status' => $matched ? 'matched' : 'mismatched',
                'inputPath' => $inputPath,
                'expectedNativePath' => $expectedNativePath,
                'readerOptions' => $readerOptions,
                'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                'staticFixtureBinding' => $staticFixtureBinding,
                'reader' => 'tsv',
                'expectedNativeSha256' => hash('sha256', $expectedNative),
                'generatedNativeSha256' => hash('sha256', $generatedNative),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'generatedNativeTokenSha256' => hash('sha256', $generatedTokens),
                'rowCount' => is_array($packet) ? ($packet['rowCount'] ?? null) : null,
                'columnCount' => is_array($packet) ? ($packet['columnCount'] ?? null) : null,
                'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            ];
        }

        $sampleCount = count(self::GENERATED_TSV_NATIVE_SAMPLES);
        $mismatchCount = $comparedCount - $matchCount;
        $staticEvidenceValid = self::hasRequiredGeneratedTsvNativeStaticEvidence($staticEvidence);
        $validStaticFixtureBindingCount = self::validGeneratedNativeSampleStaticBindingCount($sampleResults, 'tsv');
        $invalidStaticFixtureBindingCount = $sampleCount - $validStaticFixtureBindingCount;

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => 'executable-generated-tsv-native-parity-evidence',
            'evidenceKind' => 'generated-tsv-native-parity',
            'status' => 'completed-generated-tsv-native-parity-evidence',
            'claim' => 'Executes the local PHP TSV reader and native writer for checked-in generated TSV samples; these are not upstream TSV direct fixtures or upstream runner results.',
            'fixtureDirectory' => self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY,
            'reader' => 'tsv',
            'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'generatedNativeMatchCount' => $matchCount,
            'generatedNativeMismatchCount' => $mismatchCount,
            'generatedNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'staticFixtureBindingValidCount' => $validStaticFixtureBindingCount,
            'staticFixtureBindingInvalidCount' => $invalidStaticFixtureBindingCount,
            'parityStatus' => self::generatedTsvNativeParityStatus($staticEvidenceValid && $invalidStaticFixtureBindingCount === 0, count($parseFailures), $mismatchCount, $comparedCount, $sampleCount),
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'the local TSV reader can read the checked-in generated TSV samples',
                    'the generated native output matches the checked-in expected native fixtures by normalized native token stream',
                    'each executable generated TSV sample is bound to valid checked-in input and native snapshot evidence',
                    'the generated TSV native sample corpus is separate from the upstream TSV direct fixture denominator',
                ],
                'doesNotAssert' => [
                    'that the generated TSV samples are upstream command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated TSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function currentCsvDirectNativeParityEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = self::checkedInCurrentCsvDirectNativeEvidence($root);
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach (self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES as $name => $sample) {
            $transcriptPath = (string) $sample['transcriptPath'];
            $absoluteTranscriptPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $transcriptPath);
            $transcript = is_file($absoluteTranscriptPath) ? file_get_contents($absoluteTranscriptPath) : false;
            $pair = is_string($transcript) ? self::csvCommandNativeTranscript($transcript) : null;
            if (!is_string($transcript) || $pair === null) {
                $failure = [
                    'sample' => (string) $name,
                    'fixtureName' => (string) $sample['fixtureName'],
                    'transcriptPath' => $transcriptPath,
                    'upstreamPath' => (string) $sample['upstreamPath'],
                    'readerOptions' => [],
                    'transcriptError' => is_string($transcript) ? 'missing-command-input-native-block' : 'missing-or-unreadable-current-csv-direct-transcript',
                    'inputError' => null,
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            try {
                $document = (new DelimitedTextReader())->readCsv($pair['input']);
                $generatedNative = PandocConverter::write($document, 'native');
            } catch (\Throwable $throwable) {
                $failure = [
                    'sample' => (string) $name,
                    'fixtureName' => (string) $sample['fixtureName'],
                    'transcriptPath' => $transcriptPath,
                    'upstreamPath' => (string) $sample['upstreamPath'],
                    'readerOptions' => [],
                    'transcriptError' => null,
                    'inputError' => $throwable::class . ': ' . $throwable->getMessage(),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($pair['expectedNative']);
            $generatedTokens = self::nativeTokenStream($generatedNative);
            $matched = $expectedTokens === $generatedTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => (string) $name,
                    'fixtureName' => (string) $sample['fixtureName'],
                    'transcriptPath' => $transcriptPath,
                    'upstreamPath' => (string) $sample['upstreamPath'],
                    'firstDifference' => self::firstStringDifference($expectedTokens, $generatedTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $table = $document->children[0] ?? null;
            $packet = $table instanceof AstNode ? $table->attr('delimitedText', []) : [];
            $sampleResults[] = [
                'name' => (string) $name,
                'status' => $matched ? 'matched' : 'mismatched',
                'fixtureName' => (string) $sample['fixtureName'],
                'transcriptPath' => $transcriptPath,
                'upstreamPath' => (string) $sample['upstreamPath'],
                'command' => $pair['command'],
                'readerOptions' => [],
                'reader' => 'csv',
                'inputBytes' => strlen($pair['input']),
                'expectedNativeSha256' => hash('sha256', $pair['expectedNative']),
                'generatedNativeSha256' => hash('sha256', $generatedNative),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'generatedNativeTokenSha256' => hash('sha256', $generatedTokens),
                'rowCount' => is_array($packet) ? ($packet['rowCount'] ?? null) : null,
                'columnCount' => is_array($packet) ? ($packet['columnCount'] ?? null) : null,
            ];
        }

        $sampleCount = count(self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES);
        $mismatchCount = $comparedCount - $matchCount;
        $staticEvidenceValid = self::hasRequiredCurrentCsvDirectNativeStaticEvidence($staticEvidence);
        $observed = $staticEvidenceValid
            && count($parseFailures) === 0
            && $mismatchCount === 0
            && $comparedCount === $sampleCount
            && $matchCount === self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT;

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => 'current-csv-direct-native-parity-evidence',
            'evidenceKind' => 'current-csv-direct-native-parity',
            'status' => $observed ? 'completed-current-csv-direct-native-parity-evidence' : 'incomplete-current-csv-direct-native-parity-evidence',
            'claim' => 'Executes the local PHP CSV reader against embedded input/native pairs from checked-in upstream command transcripts.',
            'fixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'reader' => 'csv',
            'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            'currentCsvDirectNativePairCount' => self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'currentCsvDirectNativeMatchCount' => $matchCount,
            'currentCsvDirectNativeMismatchCount' => $mismatchCount,
            'currentCsvDirectNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'parityStatus' => $observed ? 'current-csv-direct-native-parity-observed' : 'current-csv-direct-native-parity-incomplete',
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'the local CSV reader can read the embedded CSV inputs from csv.md and 9797.md',
                    'the generated native output matches the embedded expected native transcript blocks by normalized native token stream',
                    'the upstream CSV direct fixture denominator remains three',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that 01.csv contains an embedded native expectation',
                    'full CSV feature parity beyond these direct transcript probes and the generated CSV sample corpus',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function currentTsvDirectNativeParityEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = self::checkedInCurrentTsvDirectNativeEvidence($root);
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach (self::CURRENT_TSV_DIRECT_NATIVE_SAMPLES as $name => $sample) {
            $inputPath = (string) $sample['inputPath'];
            $expectedNativePath = (string) $sample['expectedNativePath'];
            $absoluteInputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $absoluteExpectedNativePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNativePath);
            $input = is_file($absoluteInputPath) ? file_get_contents($absoluteInputPath) : false;
            $expectedNative = is_file($absoluteExpectedNativePath) ? file_get_contents($absoluteExpectedNativePath) : false;
            if (!is_string($input) || !is_string($expectedNative)) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => [],
                    'inputError' => is_string($input) ? null : 'missing-or-unreadable-current-tsv-direct-input-fixture',
                    'expectedNativeError' => is_string($expectedNative) ? null : 'missing-or-unreadable-current-tsv-direct-native-fixture',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            try {
                $document = (new DelimitedTextReader())->readTsv($input);
                $generatedNative = PandocConverter::write($document, 'native');
            } catch (\Throwable $throwable) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => [],
                    'inputError' => $throwable::class . ': ' . $throwable->getMessage(),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($expectedNative);
            $generatedTokens = self::nativeTokenStream($generatedNative);
            $matched = $expectedTokens === $generatedTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'firstDifference' => self::firstStringDifference($expectedTokens, $generatedTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $table = $document->children[0] ?? null;
            $packet = $table instanceof AstNode ? $table->attr('delimitedText', []) : [];
            $sampleResults[] = [
                'name' => (string) $name,
                'status' => $matched ? 'matched' : 'mismatched',
                'inputPath' => $inputPath,
                'expectedNativePath' => $expectedNativePath,
                'readerOptions' => [],
                'reader' => 'tsv',
                'expectedNativeSha256' => hash('sha256', $expectedNative),
                'generatedNativeSha256' => hash('sha256', $generatedNative),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'generatedNativeTokenSha256' => hash('sha256', $generatedTokens),
                'rowCount' => is_array($packet) ? ($packet['rowCount'] ?? null) : null,
                'columnCount' => is_array($packet) ? ($packet['columnCount'] ?? null) : null,
            ];
        }

        $sampleCount = count(self::CURRENT_TSV_DIRECT_NATIVE_SAMPLES);
        $mismatchCount = $comparedCount - $matchCount;
        $staticEvidenceValid = self::hasRequiredCurrentTsvDirectNativeStaticEvidence($staticEvidence);
        $observed = $staticEvidenceValid
            && count($parseFailures) === 0
            && $mismatchCount === 0
            && $comparedCount === $sampleCount
            && $matchCount === self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT;

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => 'current-tsv-direct-native-parity-evidence',
            'evidenceKind' => 'current-tsv-direct-native-parity',
            'status' => $observed ? 'completed-current-tsv-direct-native-parity-evidence' : 'incomplete-current-tsv-direct-native-parity-evidence',
            'claim' => 'Executes the local PHP TSV reader against checked-in current direct TSV fixtures and compares native output to checked-in native expectations.',
            'fixtureDirectory' => self::CHECKED_IN_CURRENT_TSV_NATIVE_FIXTURE_DIRECTORY,
            'reader' => 'tsv',
            'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            'currentTsvDirectNativePairCount' => self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'currentTsvDirectNativeMatchCount' => $matchCount,
            'currentTsvDirectNativeMismatchCount' => $mismatchCount,
            'currentTsvDirectNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'parityStatus' => $observed ? 'current-tsv-direct-native-parity-observed' : 'current-tsv-direct-native-parity-incomplete',
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'the local TSV reader can read the checked-in current TSV direct probes',
                    'the generated native output matches the checked-in expected native fixtures by normalized native token stream',
                    'the upstream-8661 direct TSV/native pair covers the reader input embedded in test/command/8661.md',
                ],
                'doesNotAssert' => [
                    'that the TSV 8661.md GFM writer pipe-table output is implemented by the local writer',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full TSV feature parity beyond these direct probes and the generated TSV sample corpus',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function generatedCsvPandocExecutableNativeParityEvidence(string $repoRoot, ?string $pandoc = null): array
    {
        return self::pandocExecutableNativeParityEvidence(
            $repoRoot,
            'csv',
            self::PANDOC_EXECUTABLE_CSV_NATIVE_SAMPLE_NAMES,
            self::GENERATED_CSV_NATIVE_SAMPLES,
            self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY,
            self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            self::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT,
            $pandoc
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function generatedTsvPandocExecutableNativeParityEvidence(string $repoRoot, ?string $pandoc = null): array
    {
        return self::pandocExecutableNativeParityEvidence(
            $repoRoot,
            'tsv',
            self::PANDOC_EXECUTABLE_TSV_NATIVE_SAMPLE_NAMES,
            self::GENERATED_TSV_NATIVE_SAMPLES,
            self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY,
            self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            self::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT,
            $pandoc
        );
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        $generatedCsvNative = is_array($report['generatedCsvNativeParityEvidence'] ?? null) ? $report['generatedCsvNativeParityEvidence'] : [];
        $generatedTsvNative = is_array($report['generatedTsvNativeParityEvidence'] ?? null) ? $report['generatedTsvNativeParityEvidence'] : [];
        $currentCsvDirectNative = is_array($report['currentCsvDirectNativeParityEvidence'] ?? null) ? $report['currentCsvDirectNativeParityEvidence'] : [];
        $currentTsvDirectNative = is_array($report['currentTsvDirectNativeParityEvidence'] ?? null) ? $report['currentTsvDirectNativeParityEvidence'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];
        $executionBoundary = is_array($runner['executionBoundary'] ?? null) ? $runner['executionBoundary'] : [];
        $runnerValidation = is_array($runner['validation'] ?? null) ? $runner['validation'] : [];
        $runnerResultLine = self::hasRunnerResultArtifactEvidence($report)
            ? 'Supplied upstream Haskell/Cabal runner result artifact is validated; full CSV/TSV feature parity is not asserted.'
            : 'No upstream Haskell/Cabal runner result or full CSV/TSV feature parity is asserted.';

        return implode(PHP_EOL, [
            'Pandoc delimited text reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'CSV direct fixtures: ' . (int) ($denominator['csvDirectFixtureCount'] ?? 0),
            'TSV direct fixtures: ' . (int) ($denominator['tsvDirectFixtureCount'] ?? 0),
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0)
                . ' checkedInTsvFixtures=' . (int) ($staticEvidence['checkedInTsvFixtureCount'] ?? 0),
            'Generated CSV native parity: ' . (int) ($generatedCsvNative['generatedNativeMatchCount'] ?? 0)
                . '/' . (int) ($generatedCsvNative['sampleCount'] ?? 0)
                . ' status=' . (string) ($generatedCsvNative['parityStatus'] ?? 'unknown'),
            'Generated TSV native parity: ' . (int) ($generatedTsvNative['generatedNativeMatchCount'] ?? 0)
                . '/' . (int) ($generatedTsvNative['sampleCount'] ?? 0)
                . ' status=' . (string) ($generatedTsvNative['parityStatus'] ?? 'unknown'),
            'Current CSV direct native parity: ' . (int) ($currentCsvDirectNative['currentCsvDirectNativeMatchCount'] ?? 0)
                . '/' . (int) ($currentCsvDirectNative['sampleCount'] ?? 0)
                . ' status=' . (string) ($currentCsvDirectNative['parityStatus'] ?? 'unknown'),
            'Current TSV direct native parity: ' . (int) ($currentTsvDirectNative['currentTsvDirectNativeMatchCount'] ?? 0)
                . '/' . (int) ($currentTsvDirectNative['sampleCount'] ?? 0)
                . ' status=' . (string) ($currentTsvDirectNative['parityStatus'] ?? 'unknown'),
            'Runner status: ' . (string) ($runner['status'] ?? 'unknown'),
            'Runner plan: ' . (string) ($runner['commandPlanStatus'] ?? 'unknown'),
            'Runner target: ' . implode('/', array_map('strval', is_array($target['tastyGroupPath'] ?? null) ? $target['tastyGroupPath'] : [])),
            'Runner execution boundary: ' . (string) ($executionBoundary['status'] ?? 'unknown'),
            'Runner result artifact: ' . (string) (($runnerValidation['status'] ?? null) ?? 'not-evaluated'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            $runnerResultLine,
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $denominator = is_array($evidence['readerDenominator'] ?? null) ? $evidence['readerDenominator'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-delimited-text-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($denominator['csvDirectFixtureCount'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($denominator['tsvDirectFixtureCount'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && (int) ($denominator['currentCsvDirectNativePairCount'] ?? -1) === self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT
            && (int) ($denominator['currentTsvDirectNativePairCount'] ?? -1) === self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT
            && self::hasRequiredGeneratedCsvNativeStaticEvidence(
                is_array($evidence['generatedCsvNativeStaticEvidence'] ?? null) ? $evidence['generatedCsvNativeStaticEvidence'] : []
            )
            && self::hasRequiredGeneratedTsvNativeStaticEvidence(
                is_array($evidence['generatedTsvNativeStaticEvidence'] ?? null) ? $evidence['generatedTsvNativeStaticEvidence'] : []
            )
            && self::hasRequiredCurrentCsvDirectNativeStaticEvidence(
                is_array($evidence['currentCsvDirectNativeStaticEvidence'] ?? null) ? $evidence['currentCsvDirectNativeStaticEvidence'] : []
            )
            && self::hasRequiredCurrentTsvDirectNativeStaticEvidence(
                is_array($evidence['currentTsvDirectNativeStaticEvidence'] ?? null) ? $evidence['currentTsvDirectNativeStaticEvidence'] : []
            )
            && self::hasRequiredCsvAdjacentRstFixtureEvidence(
                is_array($evidence['adjacentFixtureEvidence'] ?? null) ? $evidence['adjacentFixtureEvidence'] : []
            );
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-delimited-text-reader-evidence'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $reportOrEvidence
     */
    public static function hasRunnerPlanEvidence(array $reportOrEvidence): bool
    {
        $runner = is_array($reportOrEvidence['runnerEvidence'] ?? null)
            ? $reportOrEvidence['runnerEvidence']
            : $reportOrEvidence;
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];
        $commandPlan = is_array($runner['commandPlan'] ?? null) ? $runner['commandPlan'] : [];
        $executionBoundary = is_array($runner['executionBoundary'] ?? null) ? $runner['executionBoundary'] : [];

        return ($runner['scope'] ?? null) === 'upstream-haskell-runner'
            && ($runner['status'] ?? null) === 'not-run'
            && ($runner['executed'] ?? null) === false
            && array_key_exists('command', $runner)
            && $runner['command'] === null
            && array_key_exists('resultArtifact', $runner)
            && $runner['resultArtifact'] === null
            && ($runner['commandPlanStatus'] ?? null) === 'planned-not-run'
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['entryPoint'] ?? null) === 'test/test-pandoc.hs'
            && ($binding['commandTestModule'] ?? null) === 'test/Tests/Command.hs'
            && ($binding['commandFixture'] ?? null) === self::RUNNER_DIRECT_COMMAND_FIXTURE
            && ($binding['directInputFixture'] ?? null) === self::RUNNER_DIRECT_INPUT_FIXTURE
            && ($target['testSuite'] ?? null) === self::RUNNER_TEST_SUITE
            && ($target['tastyGroupPath'] ?? null) === self::RUNNER_TASTY_GROUP_PATH
            && ($target['tastyPattern'] ?? null) === self::RUNNER_TASTY_PATTERN
            && self::runnerTargetProvesDirectCsvCommandFixture($target)
            && ($commandPlan['kind'] ?? null) === 'upstream-runner-command-plan'
            && ($commandPlan['status'] ?? null) === 'planned-not-run'
            && ($commandPlan['workingDirectory'] ?? null) === self::RUNNER_WORKING_DIRECTORY
            && ($commandPlan['buildDirectory'] ?? null) === self::RUNNER_BUILD_DIR
            && ($commandPlan['networkMode'] ?? null) === 'offline'
            && ($commandPlan['commandCount'] ?? null) === count(self::runnerFutureCommands())
            && ($commandPlan['commands'] ?? null) === self::runnerFutureCommands()
            && ($executionBoundary['kind'] ?? null) === 'upstream-runner-non-execution-boundary'
            && ($executionBoundary['status'] ?? null) === 'plan-only-not-run'
            && ($executionBoundary['planOnly'] ?? null) === true
            && ($executionBoundary['executed'] ?? null) === false
            && ($executionBoundary['executedCommandCount'] ?? null) === 0
            && ($executionBoundary['executedCommands'] ?? null) === []
            && ($executionBoundary['upstreamRunnerParityClaimed'] ?? null) === false
            && ($runner['futureCommands'] ?? null) === self::runnerFutureCommands()
            && ($runner['requiredTranscripts'] ?? null) === self::RUNNER_REQUIRED_TRANSCRIPTS
            && ($runner['requiredArtifacts'] ?? null) === self::RUNNER_REQUIRED_ARTIFACTS;
    }

    /**
     * @param array<string, mixed> $reportOrEvidence
     */
    public static function hasRunnerResultArtifactEvidence(array $reportOrEvidence): bool
    {
        $runner = is_array($reportOrEvidence['runnerEvidence'] ?? null)
            ? $reportOrEvidence['runnerEvidence']
            : $reportOrEvidence;
        $artifact = is_array($runner['resultArtifact'] ?? null) ? $runner['resultArtifact'] : [];
        $validation = is_array($runner['validation'] ?? null) ? $runner['validation'] : [];
        $transcripts = is_array($runner['transcripts'] ?? null) ? $runner['transcripts'] : [];

        return ($runner['scope'] ?? null) === 'upstream-haskell-runner'
            && ($runner['runner'] ?? null) === 'Cabal/Tasty Pandoc command reader suite'
            && ($runner['status'] ?? null) === 'completed'
            && ($runner['executed'] ?? null) === true
            && ($runner['commandPlanStatus'] ?? null) === 'runner-result-artifact-validated'
            && ($artifact['kind'] ?? null) === self::RUNNER_RESULT_ARTIFACT_KIND
            && ($artifact['present'] ?? null) === true
            && is_string($artifact['sha256'] ?? null)
            && is_int($artifact['bytes'] ?? null)
            && ($validation['status'] ?? null) === 'valid-upstream-delimited-text-reader-runner-result-artifact'
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
     * @return array<string, mixed>
     */
    private function runnerEvidence(): array
    {
        if ($this->runnerResultArtifact === null) {
            return self::runnerNotRunEvidence();
        }

        return $this->runnerResultArtifactEvidence();
    }

    /**
     * @return array<string, mixed>
     */
    private function runnerResultArtifactEvidence(): array
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
        $expectedTestNames = self::RUNNER_EXPECTED_TEST_NAMES;
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
            if (($payload['runner'] ?? null) !== 'Cabal/Tasty Pandoc command reader suite') {
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
            if (!self::runnerTargetProvesDirectCsvCommandFixture($target)) {
                $issues[] = 'runner-result-target-direct-csv-command-fixture-mismatch';
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
            'runner' => 'Cabal/Tasty Pandoc command reader suite',
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
                'commandTestModule' => 'test/Tests/Command.hs',
                'commandFixture' => self::RUNNER_DIRECT_COMMAND_FIXTURE,
                'directInputFixture' => self::RUNNER_DIRECT_INPUT_FIXTURE,
            ],
            'target' => [
                'testSuite' => is_string($target['testSuite'] ?? null) ? $target['testSuite'] : null,
                'tastyGroupPath' => is_array($target['tastyGroupPath'] ?? null) ? $target['tastyGroupPath'] : null,
                'tastyPattern' => is_string($target['tastyPattern'] ?? null) ? $target['tastyPattern'] : null,
                'selectedDirectFixtureFormat' => is_string($target['selectedDirectFixtureFormat'] ?? null) ? $target['selectedDirectFixtureFormat'] : null,
                'directCommandFixture' => is_string($target['directCommandFixture'] ?? null) ? $target['directCommandFixture'] : null,
                'directInputFixture' => is_string($target['directInputFixture'] ?? null) ? $target['directInputFixture'] : null,
                'tsvDirectFixtureAvailable' => is_bool($target['tsvDirectFixtureAvailable'] ?? null) ? $target['tsvDirectFixtureAvailable'] : null,
                'tsvDirectCommandFixture' => is_string($target['tsvDirectCommandFixture'] ?? null) ? $target['tsvDirectCommandFixture'] : null,
                'tsvDirectOutputFormat' => is_string($target['tsvDirectOutputFormat'] ?? null) ? $target['tsvDirectOutputFormat'] : null,
            ],
            'expected' => [
                'schemaVersion' => self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION,
                'runner' => 'Cabal/Tasty Pandoc command reader suite',
                'target' => self::runnerTarget(),
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
                'target' => $target,
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
                    ? 'valid-upstream-delimited-text-reader-runner-result-artifact'
                    : 'invalid-upstream-delimited-text-reader-runner-result-artifact',
                'issues' => $issues,
            ],
            'claim' => $issues === []
                ? 'A supplied upstream delimited text reader runner result artifact matches the pinned targeted Tasty runner evidence contract.'
                : 'The supplied upstream delimited text reader runner result artifact did not satisfy the pinned targeted Tasty runner evidence contract.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc command reader suite',
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
                'commandTestModule' => 'test/Tests/Command.hs',
                'commandFixture' => self::RUNNER_DIRECT_COMMAND_FIXTURE,
                'directInputFixture' => self::RUNNER_DIRECT_INPUT_FIXTURE,
            ],
            'target' => self::runnerTarget(),
            'futureCommands' => self::runnerFutureCommands(),
            'commandPlan' => [
                'kind' => 'upstream-runner-command-plan',
                'status' => 'planned-not-run',
                'workingDirectory' => self::RUNNER_WORKING_DIRECTORY,
                'projectDirectoryArgument' => '.',
                'buildDirectory' => self::RUNNER_BUILD_DIR,
                'networkMode' => 'offline',
                'commandCount' => count(self::runnerFutureCommands()),
                'commands' => self::runnerFutureCommands(),
            ],
            'executionBoundary' => [
                'kind' => 'upstream-runner-non-execution-boundary',
                'status' => 'plan-only-not-run',
                'planOnly' => true,
                'executed' => false,
                'executedCommandCount' => 0,
                'executedCommands' => [],
                'upstreamRunnerParityClaimed' => false,
                'requiredBeforeClaimingParity' => [
                    'execute the targeted Cabal/Tasty run command in the pinned upstream checkout',
                    'capture the required runner transcripts',
                    'write and validate the required runner result artifact',
                ],
            ],
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This native PHP evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
    }

    /**
     * @return array{testSuite: string, tastyGroupPath: list<string>, tastyPattern: string, selectedDirectFixtureFormat: string, directCommandFixture: string, directInputFixture: string, tsvDirectFixtureAvailable: bool, tsvDirectCommandFixture: string, tsvDirectOutputFormat: string}
     */
    private static function runnerTarget(): array
    {
        return [
            'testSuite' => self::RUNNER_TEST_SUITE,
            'tastyGroupPath' => self::RUNNER_TASTY_GROUP_PATH,
            'tastyPattern' => self::RUNNER_TASTY_PATTERN,
            'selectedDirectFixtureFormat' => 'csv',
            'directCommandFixture' => self::RUNNER_DIRECT_COMMAND_FIXTURE,
            'directInputFixture' => self::RUNNER_DIRECT_INPUT_FIXTURE,
            'tsvDirectFixtureAvailable' => true,
            'tsvDirectCommandFixture' => 'test/command/8661.md',
            'tsvDirectOutputFormat' => 'gfm',
        ];
    }

    /**
     * @param array<string, mixed> $target
     */
    private static function runnerTargetProvesDirectCsvCommandFixture(array $target): bool
    {
        return ($target['selectedDirectFixtureFormat'] ?? null) === 'csv'
            && ($target['directCommandFixture'] ?? null) === self::RUNNER_DIRECT_COMMAND_FIXTURE
            && ($target['directInputFixture'] ?? null) === self::RUNNER_DIRECT_INPUT_FIXTURE
            && ($target['tsvDirectFixtureAvailable'] ?? null) === true
            && ($target['tsvDirectCommandFixture'] ?? null) === 'test/command/8661.md'
            && ($target['tsvDirectOutputFormat'] ?? null) === 'gfm';
    }

    /**
     * @return list<array{purpose: string, workingDirectory: string, program: string, arguments: list<string>}>
     */
    private static function runnerFutureCommands(): array
    {
        return [
            [
                'purpose' => 'prepare runner dependencies in an isolated build directory',
                'workingDirectory' => self::RUNNER_WORKING_DIRECTORY,
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
                'purpose' => 'list targeted CSV command reader tests',
                'workingDirectory' => self::RUNNER_WORKING_DIRECTORY,
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
                'purpose' => 'run targeted CSV command reader tests',
                'workingDirectory' => self::RUNNER_WORKING_DIRECTORY,
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
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedCsvNativeStaticEvidence(array $evidence): bool
    {
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-generated-csv-native-parity-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($evidence['sampleCount'] ?? -1) === self::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT
            && (int) ($evidence['checkedInFixtureCount'] ?? -1) === count(self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES)
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (($evidence['fixtureInventory']['status'] ?? null) === 'valid-generated-csv-native-fixture-inventory')
            && self::hasRequiredGeneratedNativeStaticFixtureBindings($evidence, 'csv', array_keys(self::GENERATED_CSV_NATIVE_SAMPLES));
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedTsvNativeStaticEvidence(array $evidence): bool
    {
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-generated-tsv-native-parity-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($evidence['sampleCount'] ?? -1) === self::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT
            && (int) ($evidence['checkedInFixtureCount'] ?? -1) === count(self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES)
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && (($evidence['fixtureInventory']['status'] ?? null) === 'valid-generated-tsv-native-fixture-inventory')
            && self::hasRequiredGeneratedNativeStaticFixtureBindings($evidence, 'tsv', array_keys(self::GENERATED_TSV_NATIVE_SAMPLES));
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredCurrentCsvDirectNativeStaticEvidence(array $evidence): bool
    {
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];
        $fixtures = is_array($evidence['checkedInFixtures'] ?? null) ? $evidence['checkedInFixtures'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-csv-direct-native-transcript-evidence'
            && ($validation['issues'] ?? null) === []
            && ($evidence['reader'] ?? null) === 'csv'
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['currentCsvDirectNativePairCount'] ?? -1) === self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT
            && (int) ($evidence['checkedInFixtureCount'] ?? -1) === count(self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES)
            && array_column($samples, 'name') === array_keys(self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES)
            && array_column($fixtures, 'name') === array_column(self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES, 'fixtureName')
            && array_column($fixtures, 'transcriptStatus') === array_fill(0, self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT, 'valid-current-csv-direct-native-transcript');
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredCurrentTsvDirectNativeStaticEvidence(array $evidence): bool
    {
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];
        $fixtures = is_array($evidence['checkedInFixtures'] ?? null) ? $evidence['checkedInFixtures'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-tsv-direct-native-fixture-evidence'
            && ($validation['issues'] ?? null) === []
            && ($evidence['reader'] ?? null) === 'tsv'
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['currentTsvDirectNativePairCount'] ?? -1) === self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT
            && (int) ($evidence['checkedInFixtureCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_TSV_NATIVE_FIXTURES)
            && array_column($samples, 'name') === array_keys(self::CURRENT_TSV_DIRECT_NATIVE_SAMPLES)
            && array_column($fixtures, 'name') === array_keys(self::CHECKED_IN_CURRENT_TSV_NATIVE_FIXTURES);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredCsvAdjacentRstFixtureEvidence(array $evidence): bool
    {
        $fixtures = is_array($evidence['fixtures'] ?? null) ? $evidence['fixtures'] : [];

        return ($evidence['kind'] ?? null) === 'csv-adjacent-rst-csv-table-fixture-evidence'
            && ($evidence['relationship'] ?? null) === 'adjacent-rst-reader-fixtures-not-direct-delimited-text'
            && ($evidence['reader'] ?? null) === 'rst'
            && ($evidence['directive'] ?? null) === 'csv-table'
            && (int) ($evidence['fixtureCount'] ?? -1) === self::EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT
            && array_column($fixtures, 'path') === self::csvAdjacentRstFixturePaths()
            && array_column($fixtures, 'directDelimitedTextReaderFixture') === [false, false]
            && (int) ($evidence['csvDirectFixtureDenominatorImpact'] ?? -1) === 0
            && (int) ($evidence['tsvDirectFixtureDenominatorImpact'] ?? -1) === 0;
    }

    /**
     * @return list<string>
     */
    public static function csvParserOptionFixtureNames(): array
    {
        return self::CSV_PARSER_OPTION_FIXTURES;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredCsvParserOptionFixtureEvidence(array $evidence, ?int $expectedCount = null): bool
    {
        $expectedCount ??= self::EXPECTED_CSV_PARSER_OPTION_FIXTURE_COUNT;

        return ($evidence['reader'] ?? null) === 'csv'
            && (int) ($evidence['parserOptionFixtureCount'] ?? -1) === $expectedCount
            && ($evidence['parserOptionFixtures'] ?? null) === self::CSV_PARSER_OPTION_FIXTURES
            && count(self::CSV_PARSER_OPTION_FIXTURES) === $expectedCount;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedCsvParserOptionNativeParity(array $evidence, ?int $expectedCount = null): bool
    {
        $expectedCount ??= self::EXPECTED_CSV_PARSER_OPTION_FIXTURE_COUNT;
        $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];
        if (count(self::CSV_PARSER_OPTION_FIXTURES) !== $expectedCount) {
            return false;
        }

        $samplesByName = [];
        foreach ($samples as $sample) {
            if (is_array($sample) && is_string($sample['name'] ?? null)) {
                $samplesByName[$sample['name']] = $sample;
            }
        }

        foreach (self::CSV_PARSER_OPTION_FIXTURES as $name) {
            $sample = is_array($samplesByName[$name] ?? null) ? $samplesByName[$name] : [];
            if (
                ($sample['status'] ?? null) !== 'matched'
                || ($sample['reader'] ?? null) !== 'csv'
                || ($sample['staticFixtureBindingStatus'] ?? null) !== self::validGeneratedNativeSampleStaticBindingStatus('csv')
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedCsvNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required generated CSV native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];

        return ($evidence['status'] ?? null) === 'completed-generated-csv-native-parity-evidence'
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['generatedNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['generatedNativeMismatchCount'] ?? -1) === 0
            && (int) ($evidence['staticFixtureBindingValidCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['staticFixtureBindingInvalidCount'] ?? -1) === 0
            && ($evidence['parityStatus'] ?? null) === 'generated-csv-native-parity-observed-not-upstream-fixture'
            && self::hasRequiredGeneratedCsvNativeStaticEvidence($staticEvidence)
            && self::hasRequiredGeneratedNativeSampleStaticBindings($evidence, 'csv', $requiredSampleCount);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedTsvNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required generated TSV native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];

        return ($evidence['status'] ?? null) === 'completed-generated-tsv-native-parity-evidence'
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['generatedNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['generatedNativeMismatchCount'] ?? -1) === 0
            && (int) ($evidence['staticFixtureBindingValidCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['staticFixtureBindingInvalidCount'] ?? -1) === 0
            && ($evidence['parityStatus'] ?? null) === 'generated-tsv-native-parity-observed-not-upstream-fixture'
            && self::hasRequiredGeneratedTsvNativeStaticEvidence($staticEvidence)
            && self::hasRequiredGeneratedNativeSampleStaticBindings($evidence, 'tsv', $requiredSampleCount);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredCurrentCsvDirectNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required current CSV direct native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];
        $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];

        return ($evidence['status'] ?? null) === 'completed-current-csv-direct-native-parity-evidence'
            && ($evidence['parityStatus'] ?? null) === 'current-csv-direct-native-parity-observed'
            && ($evidence['reader'] ?? null) === 'csv'
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['currentCsvDirectNativePairCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['currentCsvDirectNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['currentCsvDirectNativeMismatchCount'] ?? -1) === 0
            && array_column($samples, 'name') === array_slice(array_keys(self::CURRENT_CSV_DIRECT_NATIVE_SAMPLES), 0, $requiredSampleCount)
            && array_column($samples, 'status') === array_fill(0, $requiredSampleCount, 'matched')
            && self::hasRequiredCurrentCsvDirectNativeStaticEvidence($staticEvidence);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredCurrentTsvDirectNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required current TSV direct native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];
        $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];

        return ($evidence['status'] ?? null) === 'completed-current-tsv-direct-native-parity-evidence'
            && ($evidence['parityStatus'] ?? null) === 'current-tsv-direct-native-parity-observed'
            && ($evidence['reader'] ?? null) === 'tsv'
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['currentTsvDirectNativePairCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['currentTsvDirectNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['currentTsvDirectNativeMismatchCount'] ?? -1) === 0
            && array_column($samples, 'name') === array_slice(array_keys(self::CURRENT_TSV_DIRECT_NATIVE_SAMPLES), 0, $requiredSampleCount)
            && array_column($samples, 'status') === array_fill(0, $requiredSampleCount, 'matched')
            && self::hasRequiredCurrentTsvDirectNativeStaticEvidence($staticEvidence);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedCsvPandocExecutableNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_GENERATED_CSV_PANDOC_EXECUTABLE_NATIVE_SAMPLE_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required generated CSV pandoc executable native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];

        return ($evidence['status'] ?? null) === 'completed-pandoc-executable-generated-csv-native-parity-evidence'
            && ($evidence['reader'] ?? null) === 'csv'
            && ($evidence['requiredPandocVersion'] ?? null) === self::REQUIRED_PANDOC_EXECUTABLE_VERSION
            && ($evidence['pandocVersion'] ?? null) === self::REQUIRED_PANDOC_EXECUTABLE_VERSION
            && ($evidence['pandocExecutableStatus'] ?? null) === 'available'
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['pandocExecutableNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['pandocExecutableNativeMismatchCount'] ?? -1) === 0
            && (int) ($evidence['staticFixtureBindingValidCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['staticFixtureBindingInvalidCount'] ?? -1) === 0
            && ($evidence['parityStatus'] ?? null) === 'pandoc-executable-generated-csv-native-parity-observed'
            && array_column(is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [], 'name') === self::PANDOC_EXECUTABLE_CSV_NATIVE_SAMPLE_NAMES
            && self::hasRequiredGeneratedCsvNativeStaticEvidence($staticEvidence)
            && self::hasRequiredGeneratedNativeSampleStaticBindings($evidence, 'csv', $requiredSampleCount);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedTsvPandocExecutableNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_GENERATED_TSV_PANDOC_EXECUTABLE_NATIVE_SAMPLE_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required generated TSV pandoc executable native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];

        return ($evidence['status'] ?? null) === 'completed-pandoc-executable-generated-tsv-native-parity-evidence'
            && ($evidence['reader'] ?? null) === 'tsv'
            && ($evidence['requiredPandocVersion'] ?? null) === self::REQUIRED_PANDOC_EXECUTABLE_VERSION
            && ($evidence['pandocVersion'] ?? null) === self::REQUIRED_PANDOC_EXECUTABLE_VERSION
            && ($evidence['pandocExecutableStatus'] ?? null) === 'available'
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['pandocExecutableNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['pandocExecutableNativeMismatchCount'] ?? -1) === 0
            && (int) ($evidence['staticFixtureBindingValidCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['staticFixtureBindingInvalidCount'] ?? -1) === 0
            && ($evidence['parityStatus'] ?? null) === 'pandoc-executable-generated-tsv-native-parity-observed'
            && array_column(is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [], 'name') === self::PANDOC_EXECUTABLE_TSV_NATIVE_SAMPLE_NAMES
            && self::hasRequiredGeneratedTsvNativeStaticEvidence($staticEvidence)
            && self::hasRequiredGeneratedNativeSampleStaticBindings($evidence, 'tsv', $requiredSampleCount);
    }

    /**
     * @param array<string, mixed> $staticEvidence
     * @param list<string> $sampleNames
     */
    private static function hasRequiredGeneratedNativeStaticFixtureBindings(array $staticEvidence, string $reader, array $sampleNames): bool
    {
        foreach ($sampleNames as $sampleName) {
            $binding = self::generatedNativeSampleStaticBinding($staticEvidence, $reader, $sampleName);
            if (($binding['status'] ?? null) !== self::validGeneratedNativeSampleStaticBindingStatus($reader)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private static function hasRequiredGeneratedNativeSampleStaticBindings(array $evidence, string $reader, int $requiredSampleCount): bool
    {
        $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];
        if (count($samples) !== $requiredSampleCount) {
            return false;
        }

        $validStatus = self::validGeneratedNativeSampleStaticBindingStatus($reader);
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                return false;
            }
            $binding = is_array($sample['staticFixtureBinding'] ?? null) ? $sample['staticFixtureBinding'] : [];
            if (($sample['staticFixtureBindingStatus'] ?? null) !== $validStatus) {
                return false;
            }
            if (($binding['status'] ?? null) !== $validStatus) {
                return false;
            }
            if (($binding['reader'] ?? null) !== $reader) {
                return false;
            }
            if (($binding['sample'] ?? null) !== ($sample['name'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed> $samples
     */
    private static function validGeneratedNativeSampleStaticBindingCount(array $samples, string $reader): int
    {
        $validStatus = self::validGeneratedNativeSampleStaticBindingStatus($reader);
        $count = 0;
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                continue;
            }
            $binding = is_array($sample['staticFixtureBinding'] ?? null) ? $sample['staticFixtureBinding'] : [];
            if (($sample['staticFixtureBindingStatus'] ?? null) !== $validStatus) {
                continue;
            }
            if (($binding['status'] ?? null) !== $validStatus) {
                continue;
            }
            if (($binding['reader'] ?? null) !== $reader) {
                continue;
            }
            if (($binding['sample'] ?? null) !== ($sample['name'] ?? null)) {
                continue;
            }

            ++$count;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $staticEvidence
     * @return array<string, mixed>
     */
    private static function generatedNativeSampleStaticBinding(array $staticEvidence, string $reader, string $sampleName): array
    {
        if ($reader !== 'csv' && $reader !== 'tsv') {
            throw new \InvalidArgumentException("Unsupported generated native sample reader: {$reader}");
        }

        $fixtures = is_array($staticEvidence['checkedInFixtures'] ?? null) ? $staticEvidence['checkedInFixtures'] : [];
        $inputRole = "generated-{$reader}-native-parity-input-fixture";
        $expectedNativeRole = "generated-{$reader}-native-parity-expected-native-output";
        $inputFixture = null;
        $expectedNativeFixture = null;
        foreach ($fixtures as $fixture) {
            if (!is_array($fixture) || (string) ($fixture['sample'] ?? '') !== $sampleName) {
                continue;
            }
            if (($fixture['role'] ?? null) === $inputRole) {
                $inputFixture = $fixture;
                continue;
            }
            if (($fixture['role'] ?? null) === $expectedNativeRole) {
                $expectedNativeFixture = $fixture;
            }
        }

        $inputSnapshot = self::generatedNativeStaticFixtureSnapshotSummary($inputFixture, $inputRole);
        $expectedNativeSnapshot = self::generatedNativeStaticFixtureSnapshotSummary($expectedNativeFixture, $expectedNativeRole);
        $bindingIsValid = ($inputSnapshot['snapshotMatches'] ?? false) === true
            && ($expectedNativeSnapshot['snapshotMatches'] ?? false) === true;

        return [
            'kind' => "generated-{$reader}-native-sample-static-fixture-binding",
            'reader' => $reader,
            'sample' => $sampleName,
            'status' => $bindingIsValid
                ? self::validGeneratedNativeSampleStaticBindingStatus($reader)
                : self::invalidGeneratedNativeSampleStaticBindingStatus($reader),
            'inputFixture' => $inputSnapshot,
            'expectedNativeFixture' => $expectedNativeSnapshot,
        ];
    }

    /**
     * @param array<string, mixed>|null $fixture
     * @return array<string, mixed>
     */
    private static function generatedNativeStaticFixtureSnapshotSummary(?array $fixture, string $expectedRole): array
    {
        if ($fixture === null) {
            return [
                'name' => null,
                'role' => $expectedRole,
                'checkedInPath' => null,
                'present' => false,
                'sha256MatchesSnapshot' => false,
                'byteCountMatchesSnapshot' => false,
                'snapshotMatches' => false,
                'status' => 'missing-static-fixture-snapshot',
            ];
        }

        $file = is_array($fixture['checkedInFile'] ?? null) ? $fixture['checkedInFile'] : [];
        $present = ($file['present'] ?? false) === true;
        $sha256 = is_string($file['sha256'] ?? null) ? $file['sha256'] : null;
        $expectedSha256 = is_string($file['expectedSha256'] ?? null) ? $file['expectedSha256'] : null;
        $bytes = is_int($file['bytes'] ?? null) ? $file['bytes'] : null;
        $expectedBytes = is_int($file['expectedBytes'] ?? null) ? $file['expectedBytes'] : null;
        $sha256MatchesSnapshot = $sha256 !== null && $expectedSha256 !== null && $sha256 === $expectedSha256;
        $byteCountMatchesSnapshot = $bytes !== null && $expectedBytes !== null && $bytes === $expectedBytes;
        $snapshotMatches = $present && $sha256MatchesSnapshot && $byteCountMatchesSnapshot;

        return [
            'name' => (string) ($fixture['name'] ?? ''),
            'role' => (string) ($fixture['role'] ?? $expectedRole),
            'checkedInPath' => (string) ($file['path'] ?? ''),
            'present' => $present,
            'sha256' => $sha256,
            'expectedSha256' => $expectedSha256,
            'sha256MatchesSnapshot' => $sha256MatchesSnapshot,
            'bytes' => $bytes,
            'expectedBytes' => $expectedBytes,
            'byteCountMatchesSnapshot' => $byteCountMatchesSnapshot,
            'snapshotMatches' => $snapshotMatches,
            'status' => $snapshotMatches ? 'valid-static-fixture-snapshot' : 'invalid-static-fixture-snapshot',
        ];
    }

    private static function validGeneratedNativeSampleStaticBindingStatus(string $reader): string
    {
        return "valid-generated-{$reader}-native-sample-static-binding";
    }

    private static function invalidGeneratedNativeSampleStaticBindingStatus(string $reader): string
    {
        return "invalid-generated-{$reader}-native-sample-static-binding";
    }

    private static function claim(): string
    {
        return 'Tracks the current upstream direct CSV command-reader fixtures, the first upstream direct TSV command fixture, two embedded CSV command transcript native probes, the adjacent RST csv-table fixture pair with zero direct-reader denominator impact, seventy-one generated CSV-to-native evidence samples, two current TSV direct native probes, and forty-four generated TSV-to-native evidence samples for the delimited text reader.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and file identities of upstream direct CSV command-reader fixtures tracked locally',
                'that the current pinned upstream CSV reader source files are present when an upstream checkout is inspected',
                'that the RST csv-table fixture pair is CSV-adjacent evidence and not part of the direct CSV/TSV reader denominator',
                'that RST csv-table directives are exercised through the native RST reader integration path',
                'that test/command/8661.md is available in the pinned direct-reader evidence set as a TSV-to-GFM command fixture',
                'static checked-in current csv.md, 01.csv, and 9797.md fixture identity when staticCurrentEvidence is valid',
                'static checked-in current 8661.md fixture identity when staticCurrentEvidence is valid',
                'two current CSV embedded command transcript native probes when currentCsvDirectNativeParityEvidence is valid',
                'ten CSV parser-option generated native fixture names covering delimiter, quote, escape, keep-space, multiline, pipe, and no-header variants',
                'seventy-one generated CSV-to-native local samples when generatedCsvNativeParityEvidence is valid',
                'two current TSV direct native probes when currentTsvDirectNativeParityEvidence is valid',
                'forty-four generated TSV-to-native local samples when generatedTsvNativeParityEvidence is valid',
                'the non-executed upstream command-test runner plan for the pinned csv.md command fixture',
                'that upstream Haskell runner evidence is either explicitly not-run or supplied as a validated result artifact',
                'a supplied upstream runner result artifact is validated against the pinned CSV command Tasty target, commit, test names, pass/fail counts, and transcript file identities when explicitly provided',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'that the planned Cabal/Tasty runner command has produced a result artifact unless one is explicitly supplied and validated',
                'that local PHP output matches every upstream CSV-adjacent command fixture',
                'that the generated CSV samples are upstream command fixtures',
                'that 01.csv has an embedded native-output transcript',
                'that the TSV 8661.md GFM writer pipe-table output is implemented by this slice',
                'that the generated TSV samples are upstream command fixtures',
                'full CSV/TSV feature parity beyond the direct reader fixture evidence',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'csvDirectFixtureCount' => 0,
            'tsvDirectFixtureCount' => 0,
            'fixtureScope' => 'direct CSV and TSV command reader fixtures in test/command',
            'csvDirectFixtures' => [],
            'tsvDirectFixtures' => [],
            'upstreamFixtures' => [],
            'parserOptionFixtureCount' => 0,
            'parserOptionFixtures' => [],
            'currentCsvDirectNativePairCount' => 0,
            'currentCsvDirectNativeFixtures' => [],
            'currentTsvDirectNativePairCount' => 0,
            'currentTsvDirectNativeFixtures' => [],
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
     * @return list<array{name: string, role: string, path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}>
     */
    private function upstreamFixtureEvidence(string $root): array
    {
        $fixtures = [];
        foreach ([...self::CHECKED_IN_CURRENT_CSV_FIXTURES, ...self::CHECKED_IN_CURRENT_TSV_FIXTURES] as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['upstreamPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                ...$file,
            ];
        }

        return $fixtures;
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
     * @param list<array{name: string, role: string, path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}> $upstreamFixtures
     * @param array<string, mixed> $sourceInventory
     * @param array<string, mixed> $generatedCsvNativeParityEvidence
     * @param array<string, mixed> $generatedTsvNativeParityEvidence
     * @param array<string, mixed> $currentCsvDirectNativeParityEvidence
     * @param array<string, mixed> $currentTsvDirectNativeParityEvidence
     * @param array<string, mixed> $runnerEvidence
     * @return list<string>
     */
    private function validationIssues(array $upstreamFixtures, array $sourceInventory, array $generatedCsvNativeParityEvidence, array $generatedTsvNativeParityEvidence, array $currentCsvDirectNativeParityEvidence, array $currentTsvDirectNativeParityEvidence, array $runnerEvidence): array
    {
        $issues = [];
        foreach ($upstreamFixtures as $fixture) {
            if (($fixture['present'] ?? false) !== true) {
                $issues[] = 'missing-upstream-delimited-text-command-fixture';
            } elseif (($fixture['sha256'] ?? null) !== ($fixture['expectedSha256'] ?? null)) {
                $issues[] = 'upstream-delimited-text-command-fixture-sha256-mismatch';
            } elseif ((int) ($fixture['bytes'] ?? -1) !== (int) ($fixture['expectedBytes'] ?? -2)) {
                $issues[] = 'upstream-delimited-text-command-fixture-byte-count-mismatch';
            }
        }

        if ((int) ($sourceInventory['missingFileCount'] ?? 0) > 0) {
            $issues[] = 'missing-upstream-delimited-text-reader-source';
        }

        if (!self::hasRequiredGeneratedCsvNativeParity($generatedCsvNativeParityEvidence)) {
            $issues[] = 'generated-csv-native-parity-not-observed';
        }

        if (!self::hasRequiredGeneratedTsvNativeParity($generatedTsvNativeParityEvidence)) {
            $issues[] = 'generated-tsv-native-parity-not-observed';
        }

        if (!self::hasRequiredCurrentCsvDirectNativeParity($currentCsvDirectNativeParityEvidence)) {
            $issues[] = 'current-csv-direct-native-parity-not-observed';
        }

        if (!self::hasRequiredCurrentTsvDirectNativeParity($currentTsvDirectNativeParityEvidence)) {
            $issues[] = 'current-tsv-direct-native-parity-not-observed';
        }

        if (!self::hasRunnerPlanEvidence($runnerEvidence) && !self::hasRunnerResultArtifactEvidence($runnerEvidence)) {
            $issues[] = 'invalid-runner-command-plan-evidence';
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
     * @param array<string, array<string, mixed>> $snapshots
     * @return array<string, mixed>
     */
    private static function generatedNativeFixtureInventory(string $root, string $relativeDirectory, array $snapshots, string $reader): array
    {
        if ($reader !== 'csv' && $reader !== 'tsv') {
            throw new \InvalidArgumentException("Unsupported generated native fixture inventory reader: {$reader}");
        }

        $expectedFiles = [];
        $directoryPrefix = rtrim($relativeDirectory, '/') . '/';
        foreach ($snapshots as $snapshot) {
            $path = (string) ($snapshot['checkedInPath'] ?? '');
            $expectedFiles[] = str_starts_with($path, $directoryPrefix)
                ? substr($path, strlen($directoryPrefix))
                : $path;
        }
        sort($expectedFiles, SORT_STRING);

        $directory = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        $actualFiles = [];
        if (is_dir($directory)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                $relativePath = substr($fileInfo->getPathname(), strlen($directory) + 1);
                $actualFiles[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            }
        }
        sort($actualFiles, SORT_STRING);

        $missingFiles = array_values(array_diff($expectedFiles, $actualFiles));
        $unexpectedFiles = array_values(array_diff($actualFiles, $expectedFiles));

        return [
            'kind' => "generated-{$reader}-native-fixture-directory-inventory",
            'reader' => $reader,
            'checkedInFixtureDirectory' => $relativeDirectory,
            'status' => $missingFiles === [] && $unexpectedFiles === []
                ? "valid-generated-{$reader}-native-fixture-inventory"
                : "invalid-generated-{$reader}-native-fixture-inventory",
            'expectedFileCount' => count($expectedFiles),
            'actualFileCount' => count($actualFiles),
            'missingFileCount' => count($missingFiles),
            'unexpectedFileCount' => count($unexpectedFiles),
            'expectedFiles' => $expectedFiles,
            'actualFiles' => $actualFiles,
            'missingFiles' => $missingFiles,
            'unexpectedFiles' => $unexpectedFiles,
        ];
    }

    /**
     * @param list<string> $sampleNames
     * @param array<string, array<string, mixed>> $allSamples
     * @return array<string, mixed>
     */
    private static function pandocExecutableNativeParityEvidence(
        string $repoRoot,
        string $reader,
        array $sampleNames,
        array $allSamples,
        string $fixtureDirectory,
        int $directFixtureDenominator,
        int $generatedNativeCorpusSampleCount,
        ?string $pandoc
    ): array {
        if ($reader !== 'csv' && $reader !== 'tsv') {
            throw new \InvalidArgumentException("Unsupported pandoc executable native parity reader: {$reader}");
        }

        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = $reader === 'csv'
            ? self::checkedInGeneratedCsvNativeEvidence($root)
            : self::checkedInGeneratedTsvNativeEvidence($root);
        $staticEvidenceValid = $reader === 'csv'
            ? self::hasRequiredGeneratedCsvNativeStaticEvidence($staticEvidence)
            : self::hasRequiredGeneratedTsvNativeStaticEvidence($staticEvidence);
        $resolvedPandoc = self::resolvePandocExecutable($pandoc);
        $pandocVersion = $resolvedPandoc === null ? null : self::pandocExecutableVersion($resolvedPandoc);
        $pandocAvailable = $resolvedPandoc !== null;
        $pandocVersionMatches = $pandocVersion === self::REQUIRED_PANDOC_EXECUTABLE_VERSION;
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach ($sampleNames as $name) {
            $sample = is_array($allSamples[$name] ?? null) ? $allSamples[$name] : null;
            $inputPath = is_array($sample) ? (string) ($sample['inputPath'] ?? '') : '';
            $expectedNativePath = is_array($sample) ? (string) ($sample['expectedNativePath'] ?? '') : '';
            $readerOptions = is_array($sample['options'] ?? null) ? $sample['options'] : [];
            $staticFixtureBinding = self::generatedNativeSampleStaticBinding($staticEvidence, $reader, $name);
            $baseResult = [
                'name' => $name,
                'inputPath' => $inputPath,
                'expectedNativePath' => $expectedNativePath,
                'readerOptions' => $readerOptions,
                'reader' => $reader,
                'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                'staticFixtureBinding' => $staticFixtureBinding,
                'pandocExecutable' => $resolvedPandoc,
                'pandocVersion' => $pandocVersion,
            ];

            if ($sample === null) {
                $failure = [
                    'sample' => $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => 'missing-generated-sample-definition',
                    'expectedNativeError' => 'missing-generated-sample-definition',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    ...$baseResult,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            $absoluteInputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $absoluteExpectedNativePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNativePath);
            $input = is_file($absoluteInputPath) ? file_get_contents($absoluteInputPath) : false;
            $expectedNative = is_file($absoluteExpectedNativePath) ? file_get_contents($absoluteExpectedNativePath) : false;
            if (!is_string($input) || !is_string($expectedNative)) {
                $failure = [
                    'sample' => $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => is_string($input) ? null : "missing-or-unreadable-{$reader}-input-fixture",
                    'expectedNativeError' => is_string($expectedNative) ? null : 'missing-or-unreadable-native-fixture',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    ...$baseResult,
                    'status' => 'parse-failed',
                    ...$failure,
                ];
                continue;
            }

            if (!$pandocAvailable || !$pandocVersionMatches) {
                $sampleResults[] = [
                    ...$baseResult,
                    'status' => 'not-run',
                    'expectedNativeSha256' => hash('sha256', $expectedNative),
                    'expectedNativeTokenSha256' => hash('sha256', self::nativeTokenStream($expectedNative)),
                ];
                continue;
            }

            $result = self::runProcess(
                escapeshellarg($resolvedPandoc)
                . ' -f '
                . escapeshellarg($reader)
                . ' -t native '
                . escapeshellarg($absoluteInputPath)
            );
            if ($result['exitCode'] !== 0) {
                $failure = [
                    'sample' => $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => 'pandoc exited ' . $result['exitCode'] . ': ' . trim($result['stderr']),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    ...$baseResult,
                    'status' => 'parse-failed',
                    'pandocExitCode' => $result['exitCode'],
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($expectedNative);
            $pandocTokens = self::nativeTokenStream($result['stdout']);
            $matched = $expectedTokens === $pandocTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'firstDifference' => self::firstStringDifference($expectedTokens, $pandocTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $sampleResults[] = [
                ...$baseResult,
                'status' => $matched ? 'matched' : 'mismatched',
                'pandocExitCode' => $result['exitCode'],
                'expectedNativeSha256' => hash('sha256', $expectedNative),
                'pandocNativeSha256' => hash('sha256', $result['stdout']),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'pandocNativeTokenSha256' => hash('sha256', $pandocTokens),
            ];
        }

        $sampleCount = count($sampleNames);
        $mismatchCount = $comparedCount - $matchCount;
        $validStaticFixtureBindingCount = self::validGeneratedNativeSampleStaticBindingCount($sampleResults, $reader);
        $invalidStaticFixtureBindingCount = $sampleCount - $validStaticFixtureBindingCount;
        $parityStatus = self::pandocExecutableNativeParityStatus(
            $reader,
            $pandocAvailable,
            $pandocVersionMatches,
            $staticEvidenceValid && $invalidStaticFixtureBindingCount === 0,
            count($parseFailures),
            $mismatchCount,
            $comparedCount,
            $sampleCount
        );
        $directDenominatorKey = "{$reader}DirectFixtureDenominator";

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => "pandoc-executable-generated-{$reader}-native-parity-evidence",
            'evidenceKind' => "generated-{$reader}-pandoc-executable-native-parity",
            'status' => $parityStatus === "pandoc-executable-generated-{$reader}-native-parity-observed"
                ? "completed-pandoc-executable-generated-{$reader}-native-parity-evidence"
                : "incomplete-pandoc-executable-generated-{$reader}-native-parity-evidence",
            'claim' => "Executes installed pandoc against the generated {$reader}-to-native subset that is representable by pandoc 3.10 defaults; custom local dialect and recovery samples stay in generated {$reader} native parity evidence.",
            'fixtureDirectory' => $fixtureDirectory,
            'reader' => $reader,
            $directDenominatorKey => $directFixtureDenominator,
            'generatedNativeCorpusSampleCount' => $generatedNativeCorpusSampleCount,
            'sampleNames' => $sampleNames,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'pandocExecutableNativeMatchCount' => $matchCount,
            'pandocExecutableNativeMismatchCount' => $mismatchCount,
            'pandocExecutableNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'staticFixtureBindingValidCount' => $validStaticFixtureBindingCount,
            'staticFixtureBindingInvalidCount' => $invalidStaticFixtureBindingCount,
            'parityStatus' => $parityStatus,
            'pandocExecutable' => $resolvedPandoc,
            'pandocExecutableStatus' => $pandocAvailable ? 'available' : 'missing',
            'requiredPandocVersion' => self::REQUIRED_PANDOC_EXECUTABLE_VERSION,
            'pandocVersion' => $pandocVersion,
            'requiredPandocVersionObserved' => $pandocVersionMatches,
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'installed pandoc is available and reports pandoc 3.10 when parityStatus is observed',
                    "the selected generated {$reader} samples match installed pandoc native output by normalized native token stream",
                    "each executable-pandoc {$reader} sample is bound to valid checked-in input and native snapshot evidence",
                    "the selected subset is narrower than the generated {$reader} local reader corpus",
                ],
                'doesNotAssert' => [
                    'that custom local delimiter, quote, escape, no-header, or recovery-mode samples are accepted by pandoc default CSV/TSV readers',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond this executable-pandoc subset',
                ],
            ],
        ];
    }

    /**
     * @return array{command:string, input:string, expectedNative:string}|null
     */
    private static function csvCommandNativeTranscript(string $transcript): ?array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $transcript));
        $lineCount = count($lines);
        for ($index = 0; $index < $lineCount; ++$index) {
            $line = $lines[$index];
            if (!str_starts_with($line, '% pandoc') || !self::csvCommandTargetsNative($line)) {
                continue;
            }

            $inputLines = [];
            ++$index;
            while ($index < $lineCount && $lines[$index] !== '^D') {
                $inputLines[] = $lines[$index];
                ++$index;
            }
            if ($index >= $lineCount || $lines[$index] !== '^D') {
                return null;
            }

            $nativeLines = [];
            ++$index;
            while ($index < $lineCount && !str_starts_with($lines[$index], '```')) {
                $nativeLines[] = $lines[$index];
                ++$index;
            }
            while ($nativeLines !== [] && end($nativeLines) === '') {
                array_pop($nativeLines);
            }

            return [
                'command' => $line,
                'input' => implode("\n", $inputLines),
                'expectedNative' => implode("\n", $nativeLines),
            ];
        }

        return null;
    }

    private static function csvCommandTargetsNative(string $line): bool
    {
        $tokens = array_values(array_filter(explode(' ', trim($line)), static fn (string $token): bool => $token !== ''));
        $fromCsv = false;
        $toNative = false;
        for ($index = 0, $count = count($tokens); $index < $count; ++$index) {
            if ($tokens[$index] === '-f' && ($tokens[$index + 1] ?? null) === 'csv') {
                $fromCsv = true;
            }
            if ($tokens[$index] === '-t' && ($tokens[$index + 1] ?? null) === 'native') {
                $toNative = true;
            }
        }

        return $fromCsv && $toNative;
    }

    private static function nativeTokenStream(string $native): string
    {
        $native = str_replace('\\12', '\\f', $native);
        $native = (string) preg_replace('/\[\s*\]/', '[]', $native);
        $native = (string) preg_replace('/\(\s*""\s*,\s*\[\]\s*,\s*\[\]\s*\)/', '("",[],[])', $native);

        return (string) preg_replace('/\s+/', ' ', trim($native));
    }

    private static function firstStringDifference(string $expected, string $actual): ?string
    {
        if ($expected === $actual) {
            return null;
        }

        $limit = min(strlen($expected), strlen($actual));
        for ($offset = 0; $offset < $limit; $offset++) {
            if ($expected[$offset] === $actual[$offset]) {
                continue;
            }

            return 'native token byte ' . $offset
                . ' expected=' . bin2hex($expected[$offset])
                . ' actual=' . bin2hex($actual[$offset]);
        }

        return 'native token length expected=' . strlen($expected) . ' actual=' . strlen($actual);
    }

    private static function generatedCsvNativeParityStatus(bool $staticEvidenceValid, int $parseFailureCount, int $mismatchCount, int $comparedCount, int $sampleCount): string
    {
        if (!$staticEvidenceValid) {
            return 'blocked-by-generated-csv-native-fixture-validation';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-generated-csv-native-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'generated-csv-native-mismatches-observed';
        }
        if ($sampleCount > 0 && $comparedCount === $sampleCount) {
            return 'generated-csv-native-parity-observed-not-upstream-fixture';
        }

        return 'not-evaluated-no-generated-csv-native-samples';
    }

    private static function generatedTsvNativeParityStatus(bool $staticEvidenceValid, int $parseFailureCount, int $mismatchCount, int $comparedCount, int $sampleCount): string
    {
        if (!$staticEvidenceValid) {
            return 'blocked-by-generated-tsv-native-fixture-validation';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-generated-tsv-native-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'generated-tsv-native-mismatches-observed';
        }
        if ($sampleCount > 0 && $comparedCount === $sampleCount) {
            return 'generated-tsv-native-parity-observed-not-upstream-fixture';
        }

        return 'not-evaluated-no-generated-tsv-native-samples';
    }

    private static function pandocExecutableNativeParityStatus(
        string $reader,
        bool $pandocAvailable,
        bool $pandocVersionMatches,
        bool $staticEvidenceValid,
        int $parseFailureCount,
        int $mismatchCount,
        int $comparedCount,
        int $sampleCount
    ): string {
        if (!$pandocAvailable) {
            return "missing-pandoc-executable-for-generated-{$reader}-native-parity";
        }
        if (!$pandocVersionMatches) {
            return "pandoc-executable-version-mismatch-for-generated-{$reader}-native-parity";
        }
        if (!$staticEvidenceValid) {
            return "blocked-by-pandoc-executable-generated-{$reader}-native-fixture-validation";
        }
        if ($parseFailureCount > 0) {
            return "blocked-by-pandoc-executable-generated-{$reader}-native-parse-failures";
        }
        if ($mismatchCount > 0) {
            return "pandoc-executable-generated-{$reader}-native-mismatches-observed";
        }
        if ($sampleCount > 0 && $comparedCount === $sampleCount) {
            return "pandoc-executable-generated-{$reader}-native-parity-observed";
        }

        return "not-evaluated-no-pandoc-executable-generated-{$reader}-native-samples";
    }

    private static function resolvePandocExecutable(?string $requested): ?string
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

    private static function pandocExecutableVersion(string $pandoc): ?string
    {
        $result = self::runProcess(escapeshellarg($pandoc) . ' --version');
        if ($result['exitCode'] !== 0) {
            return null;
        }

        $lines = preg_split('/\R/', trim($result['stdout']));

        return is_array($lines) && is_string($lines[0] ?? null) && $lines[0] !== '' ? $lines[0] : null;
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private static function runProcess(string $command): array
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            return ['exitCode' => 127, 'stdout' => '', 'stderr' => 'Unable to start process'];
        }

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

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
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
