<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class HtmlUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-html-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-html-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-html-root';
    public const CHECKED_IN_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures';
    public const EXPECTED_SELECTED_FIXTURE_COUNT = 133;
    public const EXPECTED_NATIVE_MAPPED_PAIR_COUNT = 133;

    private const SOURCE_FILES = [
        'test/Tests/Readers/HTML.hs',
        'src/Text/Pandoc/Readers/HTML.hs',
    ];
    private const RUNNER_TEST_SUITE = 'test:test-pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/html-targeted-run';
    private const RUNNER_TASTY_GROUP_PATH = ['Readers', 'HTML'];
    private const RUNNER_TASTY_PATTERN = '$2 == "Readers" && $3 == "HTML"';
    private const RUNNER_REQUIRED_TRANSCRIPTS = [
        '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
        '.port-libs/pandoc-runner/logs/html-targeted-list-tests.txt',
        '.port-libs/pandoc-runner/logs/html-targeted-run.txt',
    ];
    private const RUNNER_REQUIRED_ARTIFACTS = [
        '.port-libs/pandoc-runner/artifacts/html-targeted-run/result.json',
    ];
    private const RUNNER_RESULT_ARTIFACT_KIND = 'upstream-html-reader-runner-result-artifact';
    private const RUNNER_TRANSCRIPT_KIND = 'upstream-html-reader-runner-transcript';
    private const RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION = 2;
    private const HTMLDOCUMENT_MAPPED_PAIR_EXCLUSIONS = [];

    private const CHECKED_IN_HTML_FIXTURES = [
        'upstream-html-anchor-image-attrs.html' => [
            'sha256' => '27073f93fc90c5a85361723faad6fa6e1e44a891b344680476c41f9a4df3be74',
            'bytes' => 363,
        ],
        'upstream-html-base-absolute-image.html' => [
            'sha256' => 'f1ddb1f06c2b15d5667621c3c16b173d9afef19a7d5146bc017db44eba454e95',
            'bytes' => 239,
        ],
        'upstream-html-base-media.html' => [
            'sha256' => '2769a543b2e88aba05d2d95bd22f4dd53e9c7b81c270d7dadd4038e2428e1c8a',
            'bytes' => 461,
        ],
        'upstream-html-base-relative-image.html' => [
            'sha256' => '1ed7cb61b59720c413b955c2046759ee9f5c4113329e9c9e43cbf21e4b9abd0a',
            'bytes' => 116,
        ],
        'upstream-html-bdo-direction.html' => [
            'sha256' => '8a49cc0d2a5be4a343c0bea3717f7003e713b68898d44a03ea65ba4267acb02e',
            'bytes' => 239,
        ],
        'upstream-html-blockquote.html' => [
            'sha256' => '7c1e8ba1dcde81e031bed35a0d75fad7dba0bf13ddbeef6188d38ae5cae82678',
            'bytes' => 193,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-checkbox-list.html' => [
            'sha256' => 'f10574b3a847f995ed86e7c0876948cac0a452a06b7b0aece497a12412de5e45',
            'bytes' => 599,
        ],
        'upstream-html-cite-wbr-raw-inline.html' => [
            'sha256' => 'ce0511c08b23c0e77b8487471c6d276804e3a525932732f543a7447a4ccd7ec9',
            'bytes' => 292,
        ],
        'upstream-html-definition-list.html' => [
            'sha256' => 'b90033d358361a2fbb664565e8a16ba7b7b474b54ce6c694ce7866bbcf805fcf',
            'bytes' => 248,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-details-summary-raw-block.html' => [
            'sha256' => 'f711f5a1e931605d4c6a6f17ec3ee863c12bdcacfbbf6291fa352305ebf049ca',
            'bytes' => 268,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-doc-noteref-footnotes.html' => [
            'sha256' => 'c6842790e7750ca06998c0f7e0e91c17f44c59e7698cc1f05c1fa2172f266f2f',
            'bytes' => 521,
        ],
        'upstream-html-doc-noteref-table-placement.html' => [
            'sha256' => '9fe0e5278a6698d05f306aa3b45af53828d9eedc5c5f554b1dfaf67801f5ebeb',
            'bytes' => 1588,
        ],
        'upstream-html-figure-caption.html' => [
            'sha256' => '4ce8a7d28f575b096ca2d26c0510526a81d269b09921fe8d7637fc2dcd727a03',
            'bytes' => 542,
        ],
        'upstream-html-generic-raw-inline.html' => [
            'sha256' => '1f1367a1693e92216965e77c30d190be4662b8c756696f59eb7b44d32265d52e',
            'bytes' => 271,
        ],
        'upstream-html-header-native-divs.html' => [
            'sha256' => '3c3d08b67b3506cd2d43a7490237c240f7eb33fa26b0ac02aab612f72feb10dd',
            'bytes' => 358,
        ],
        'upstream-html-iframe-local-resource.html' => [
            'sha256' => '86f1137cd0a68b10b18c96eb5144ba938bf7ed23d7fcd582a2275049fe214a7c',
            'bytes' => 312,
        ],
        'upstream-html-inline-code-aliases.html' => [
            'sha256' => '885749949b1f212a85f081b8a094836517a87a1ce82978e1cc4b33cd900f7719',
            'bytes' => 242,
        ],
        'upstream-html-inline-quote-cite-base.html' => [
            'sha256' => '58ab514d8472421c2abb19720e4debd695d5eb6f4f8dfea431081129eadad0b3',
            'bytes' => 307,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-lang-metadata.html' => [
            'sha256' => 'bf1f7665c81c1c438333e8c7b68a0ddc754a8fdb43515082c541d9589c95b218',
            'bytes' => 184,
        ],
        'upstream-html-line-block.html' => [
            'sha256' => 'a4d154266a877da11a47d3102be71faf469d468f0ae2c3cabbc44c8e6af38324',
            'bytes' => 286,
        ],
        'upstream-html-list-item-id.html' => [
            'sha256' => '7f94cd5a7639e1193a309cb0fb04453be05d9e545050bf2b1e18727392bed540',
            'bytes' => 367,
        ],
        'upstream-html-main-native-divs.html' => [
            'sha256' => '092476d2be22cecef9c1a2a59bc1643ac6b25a6e783238f249438801565f75b2',
            'bytes' => 435,
        ],
        'upstream-html-math-renderer-spans.html' => [
            'sha256' => 'e49465a7bbda8503f0d979c015a79eb14e9da6591252f6ce85eeff79a737151d',
            'bytes' => 564,
        ],
        'upstream-html-mathml-annotation.html' => [
            'sha256' => '61554b0518b7631ec28580295e06bf25e52048581ef704755d1f757e27fa2536',
            'bytes' => 673,
        ],
        'upstream-html-orphan-list-blocks.html' => [
            'sha256' => '14d455dbd0284dcab58715fe4c5e0ed2042a28e2e75ed2815773cf4b1d5b0762',
            'bytes' => 393,
        ],
        'upstream-html-pre-code-attributes.html' => [
            'sha256' => '78967902404465f84e40de1811d31d02b59df8b61d51804e59e5af90076390f7',
            'bytes' => 446,
        ],
        'upstream-html-pre-code-br.html' => [
            'sha256' => '904f669d02c0ceb4a9fd17f49141892dcab2b686ecc2f95a4ad1c450ee9bde30',
            'bytes' => 410,
        ],
        'upstream-html-raw-disabled-skip.html' => [
            'sha256' => 'd9a2445ca3adbbfa7e71d4c6b274f3a1415dc0297b5dd83eba28b692e1a93a3a',
            'bytes' => 686,
        ],
        'upstream-html-script-raw-block.html' => [
            'sha256' => 'fb4460c0338874e1df4835c1babbc6aec72ede2ad1230ba047354b10b93f47f3',
            'bytes' => 395,
        ],
        'upstream-html-section-aside-native-divs.html' => [
            'sha256' => '4b603995bd1fd40d53728011117159ca7dfc2a6fa5181cf04eb102d4c7a81b9a',
            'bytes' => 489,
        ],
        'upstream-html-small-inline.html' => [
            'sha256' => 'e2396299f217b7b9586fe658a469d9c1a758e7253843396d3b45809fc1e02ecb',
            'bytes' => 252,
        ],
        'upstream-html-smallcaps-class.html' => [
            'sha256' => '7caf7c91f9bbdd53e398c5a24ead2c8dea7bc64b54ec2071f86b3b9f5d0e6698',
            'bytes' => 373,
        ],
        'upstream-html-span-strikeout.html' => [
            'sha256' => '747abe3e4ae1dd7eceb84374cf2d011eeea200ec462a707f15c9a7d452c24023',
            'bytes' => 292,
        ],
        'upstream-html-spanlike-inline.html' => [
            'sha256' => 'e9db8292f853608429f5bcd454539aaee94bd5f3cc591e81dde11d15853050df',
            'bytes' => 383,
        ],
        'upstream-html-standalone-applet-inline.html' => [
            'sha256' => 'e6e7304349b25f9e2ac9abfa4fd6ba0b8d213eab5a2d63c2e71363ccb9d572fe',
            'bytes' => 136,
        ],
        'upstream-html-standalone-audio-inline.html' => [
            'sha256' => 'd0446e5747e434d546a0c2a15fae1f90abae0770abecdd3f3f75c54f13c0885c',
            'bytes' => 294,
        ],
        'upstream-html-standalone-button-inline.html' => [
            'sha256' => '458261a563155df934db322517be91c53288c7a6be6f33ea7bc0abeb263ea3f9',
            'bytes' => 130,
        ],
        'upstream-html-standalone-del-inline.html' => [
            'sha256' => 'bacde20bd18fd52bb4cb30a46d1eb1c13c0edfab93e14682a78344b044064792',
            'bytes' => 102,
        ],
        'upstream-html-standalone-inline-flow.html' => [
            'sha256' => 'e61703febac1355b893a2fd82043eaeacccbe10346f6b0b31d1eeda7224d09f1',
            'bytes' => 315,
        ],
        'upstream-html-standalone-ins-inline.html' => [
            'sha256' => '51dbb0ca79fffb7ff896d8efc4c5eb43dcfb40801cfca2de0ed73d3775f2d54e',
            'bytes' => 133,
        ],
        'upstream-html-standalone-linebreak.html' => [
            'sha256' => 'd3d98dce3cf6ef4831c5f9e7ade3ef30a9e7cfdba22d95d377cccb56f853b9a8',
            'bytes' => 60,
        ],
        'upstream-html-standalone-map-inline.html' => [
            'sha256' => 'e47c57f7acb1c64cef2f3ab277b1fec0e85ceee066f7730c2d07bba6ad5fd7ce',
            'bytes' => 155,
        ],
        'upstream-html-standalone-noscript-inline.html' => [
            'sha256' => '3e5b4514606df09a247defe704af4f77e2acadaa3a0386967901a087317f3a85',
            'bytes' => 136,
        ],
        'upstream-html-standalone-object-embed-inline.html' => [
            'sha256' => '812c92b498e7277341af326de065e42dc2418127d8cab4319e37efde1a695155',
            'bytes' => 236,
        ],
        'upstream-html-standalone-progress-inline.html' => [
            'sha256' => '58d700a3804ad3173490f753cc6de701132297494df5965111a413d1e9e7d170',
            'bytes' => 61,
        ],
        'upstream-html-standalone-svg-inline.html' => [
            'sha256' => '494458bac5f13caf80af60ae9d5b819f526a2abe6ea86a85eaa9d19b9f5ff850',
            'bytes' => 186,
        ],
        'upstream-html-standalone-video-inline.html' => [
            'sha256' => 'a6137290335aff99a2ce6ba523e84a56c423f82a404ff0551667bd8f31deae6f',
            'bytes' => 292,
        ],
        'upstream-html-standalone-void-inline.html' => [
            'sha256' => '2262d0478848397f25460afd7c8434f9eb1f8a853960dd981e773feb4bf0f370',
            'bytes' => 497,
        ],
        'upstream-html-sup-sub-inline.html' => [
            'sha256' => 'dbe1779879889257c5c569412522975181231391267bade40b84280943bdf2a0',
            'bytes' => 173,
        ],
        'upstream-html-style-raw-block.html' => [
            'sha256' => 'd1bab4d1f54578b76172cfad1840328c0bd2b21d19b12033e6cef7b01b265f07',
            'bytes' => 334,
        ],
        'upstream-html-style-script-inline.html' => [
            'sha256' => 'd94c7acd5813fe1d91d2b4d1cdfab9e10f6a324931c34ad51e24e2e4e1c06655',
            'bytes' => 338,
        ],
        'upstream-html-svg-disabled-raw-html.html' => [
            'sha256' => '549e6825f21db28ea13f593c7a82ace600ca1b5278463fc1fcc8c8009ba717c1',
            'bytes' => 313,
        ],
        'upstream-html-svg-raw-html.html' => [
            'sha256' => '0877b690a62009527229615f57306aee142ab77a93dec345be4691a0a9ab36b6',
            'bytes' => 305,
        ],
        'upstream-html-table-foot.html' => [
            'sha256' => '250bdb2dfa7b027925bf2b2b858555085c27116f6c3326339a7fc6275326b159',
            'bytes' => 382,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-textarea-raw-block.html' => [
            'sha256' => '9bce92712152c77d67a9739b2e6157ee2d046f31ad30e473e007337bc6f37674',
            'bytes' => 324,
        ],
        'upstream-html-thematic-break.html' => [
            'sha256' => 'beb96f5bb964a06db042cb6e9b8b52f187c0701216e5b27a3a381d9d4cf536ea',
            'bytes' => 205,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-ruby-annotation.html' => [
            'sha256' => 'a0a8c53ca2264dcded30176307d7094145ccb337bfcabfae87a5d880074a0d60',
            'bytes' => 203,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-address-block.html' => [
            'sha256' => '8108f7532e80e46c24c47d11ea212837f7cc3123d5dfbafa03af9187a0b50fcf',
            'bytes' => 284,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-xml-lang-metadata.html' => [
            'sha256' => 'ee6034835ca62d6e63472a2aa6a27c506f4c015369f8114409246357c2333596',
            'bytes' => 95,
        ],
        'upstream-native-html-row-header-table.html' => [
            'sha256' => '5f59ee99b16a90f6da337f94dd75c239cefb4ff7073c21e516077773892a332d',
            'bytes' => 288,
        ],
        'upstream-html-multi-tbody-row-header-table.html' => [
            'sha256' => '2b89dfbad53b5f34a1d4f9ffc48d3450d0641ac762c6324a265f7061ab889b22',
            'bytes' => 391,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-kbd-samp-var-inline.html' => [
            'sha256' => '1b768d1b3867b74ff56121a1b6c5bfcd52c9725a3c0febfb872abfb0f95717b5',
            'bytes' => 205,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-form-controls.html' => [
            'sha256' => '283067b6426ef087c9f9fa1cc7267969d589b0f88c4bca24e1d697b93f768a6e',
            'bytes' => 319,
            'sourceKind' => 'generated-current-html-reader-fixture',
        ],
        'upstream-html-standalone-image-data-external.html' => [
            'sha256' => 'f7d25720e4bb8a3f4166a26c9cd0ffe12f24feb578a2000d5bdba3acaf7cad64',
            'bytes' => 62,
        ],
        'upstream-html-standalone-emph-strong-inline.html' => [
            'sha256' => '30ce545fa7dad51eef264c260ccc456fbfe636ae42d4bc7f860698d1aa32c43b',
            'bytes' => 58,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-main-inline-plain.html' => [
            'sha256' => '7c05eccdc284eb2cdbcf6dfb5a7275eb3036926cc91ede2412eacb0cb0d2d414',
            'bytes' => 92,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-transparent-inline-fragment.html' => [
            'sha256' => 'dc2051302c2f3d87ec33d57f516b827e6f81a7509d1950c0cd774dca84da6380',
            'bytes' => 123,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-sup-sub-inline.html' => [
            'sha256' => 'ad549a16c064f08763a22fdd6bb5184799fc3470779d3c627b423f891cdbe3dd',
            'bytes' => 35,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-time-inline.html' => [
            'sha256' => '68ca00e5e22b94dd429ba6dc13106c0caf8a75f1598a9ec609795997f197d4a8',
            'bytes' => 64,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-kbd-inline.html' => [
            'sha256' => '2b542c385308a675fd1a084f018d30297c553bcd1749b6e632fdcf3d3c4bc860',
            'bytes' => 15,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-bdo-mark-q-inline.html' => [
            'sha256' => '0c295703f1457595e8a9cf1e5fda4e1a4a807e48ad81f5d3694542b9ef519e88',
            'bytes' => 67,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-progress-in-paragraph.html' => [
            'sha256' => '570ccc646ee217b1726dc356ef6c7795e752a31a31fafde43201e2df11af030d',
            'bytes' => 69,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-underline-inline.html' => [
            'sha256' => 'fd33f08488ef2a161dd581ab75355eae7fc48feaf15d561939763d3b6bd7832e',
            'bytes' => 37,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-abbr-dfn-inline.html' => [
            'sha256' => 'd4032c96239a8a49f9ab6185a22a6052438f7dd9cd6fc6f18e4e92dfa80537a4',
            'bytes' => 56,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-data-value-inline.html' => [
            'sha256' => '2bff4d9f60fe92319105056c1cdebd7a0802dee1088bad3bbfe104144768af63',
            'bytes' => 66,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-meter-inline.html' => [
            'sha256' => '1f5d55e79a85dfc7d95a6e9dd28e448ba41f3af6a4f797a12c21e420e59c5ac6',
            'bytes' => 97,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-bdi-inline.html' => [
            'sha256' => 'ea4774da33a60ee93b6e0ca24cc86b39785e46cb2a202750cd350141e05c8b2f',
            'bytes' => 45,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-optional-list-item-tree-construction.html' => [
            'sha256' => 'ad087956d31cf2b3ba9b2212a55fcd6e8c6835f891dec7bfdff92b021fd18628',
            'bytes' => 53,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-implicit-tbody-table.html' => [
            'sha256' => '8fee8c5db5323415652fcd9f6b1a136c447db09f10f1eb3a2b9fb7730c9da79b',
            'bytes' => 52,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-table-row-col-span.html' => [
            'sha256' => 'eedcf367f273f27df44c2ff75ba1762dc7735353512a8d38963fca10c0fb74fe',
            'bytes' => 214,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-meta-refresh-boundary.html' => [
            'sha256' => 'ba5be3a810cc2e1092befdc2df2c99ea3fc50b2ae688f9a95ef4ea02e0643af1',
            'bytes' => 218,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-invalid-table-children.html' => [
            'sha256' => '71537e89ccb404aa52cb698063ced36b2e796a5dc81b8d5dc1468881a43d0de8',
            'bytes' => 82,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-table-tree-construction.html' => [
            'sha256' => '98a40e8da15d9893bc3a03d4c7ff692260ff941bf3162ad23c8e2bb15775bc1c',
            'bytes' => 56,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-hr-tree-construction.html' => [
            'sha256' => '0fc42b6a1ffbbb922f3c2bd736f2ee60d4bbf888f4f2a6a2762ae624cd1983bd',
            'bytes' => 23,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-colgroup-width-table.html' => [
            'sha256' => '1d8f37ed10a3f790b6937485c58a13e2289f50fafc24abef907714aac7f71768',
            'bytes' => 249,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-template-raw-boundary.html' => [
            'sha256' => '91b8f7fbae20491a4d3967987170fe30867ed7c8cc4531512628aab3d3e3726f',
            'bytes' => 85,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-s-inline.html' => [
            'sha256' => '2629f102923aa9d840693ff041a207fde3e62d0d7fbd4cdf5d87d6849dba2a91',
            'bytes' => 37,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-anchor-without-href.html' => [
            'sha256' => '914c6cde89ab5f70447b7062514f5df224094f7ba8b98a957ed3733c36c36b04',
            'bytes' => 19,
        ],
        'upstream-html-main-role-native-divs.html' => [
            'sha256' => '61e961ece998487900942f5b55713075aaeda0d93f1de312121cd994e45b8666',
            'bytes' => 31,
        ],
        'upstream-html-ordered-list-type-start.html' => [
            'sha256' => '0e2c27f56de97f2ee0f1ebc9064440f91fac47426c068d94de5cc87c69bd9969',
            'bytes' => 73,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-base-root-relative-image.html' => [
            'sha256' => 'c4b5fe671b13a730ebcaf3d49e6a883a80001cc99208d48ef350d43ae0d3cd74',
            'bytes' => 114,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-image-title.html' => [
            'sha256' => '1fa56d948da7f1cf3ce88bb75685dcb9c74a17fad609f09cd4dfca059b5e0752',
            'bytes' => 62,
        ],
        'upstream-html-omitted-heading-end-tags.html' => [
            'sha256' => '0dd6169d31f8a90d90840c94d72d4c70af0e03b99291a95b6747bc1ced9b2eb5',
            'bytes' => 54,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-base-trailing-slash-image.html' => [
            'sha256' => '02aa50f4dd62dcf7bfa9b57cdaf2756717d1b5209b08f0a4bed6ad739284c2f4',
            'bytes' => 113,
        ],
        'upstream-html-main-closes-paragraph.html' => [
            'sha256' => '03f9df87c568970e2ce343aeb9cc8b752cd75c55d1a99b6c33e18eca6d0e24bd',
            'bytes' => 34,
        ],
        'upstream-html-orphan-table-fragment-tree-construction.html' => [
            'sha256' => 'b2cfadf098850c5125949f44c3cc01e41a7fbaaf098cf382b9b806399e8d3b2e',
            'bytes' => 66,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-optional-definition-list-tree-construction.html' => [
            'sha256' => '387b58f4b56a792dafc194c0029ac4b0fc1c99419dde5c201501901dd8ac96ab',
            'bytes' => 243,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-block-tree-construction.html' => [
            'sha256' => '332ef58c13dc629c9af634d6096041416ba1857379b305aa38e4ec1f8673ac8d',
            'bytes' => 59,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-multi-term-definition-list.html' => [
            'sha256' => '3a9072350c433235c5c142f1d82d247dbbc356b2e8fff0bd186ffa748f7d6fdc',
            'bytes' => 284,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-transparent-block-tree-construction.html' => [
            'sha256' => '0193bc1f8f94e1c1303576891c9949276967d7c418a6c204a6305f2ca01c39bd',
            'bytes' => 108,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-picture-fallback-image.html' => [
            'sha256' => '1e1396000b0b78527486b6f9bd6d4cb8027510273edeb1b6c536d030ac89b8de',
            'bytes' => 339,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-select-optgroup-inline.html' => [
            'sha256' => 'c58cb6e52ceeba2c5e7e5e51760dc3e0dfd2ba58a13e7ddaf55b95ca3d695187',
            'bytes' => 113,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-section-tree-construction.html' => [
            'sha256' => 'daff8006c6084395cdc7b0dd7b5efd469e49e1e54b03db3a946390cee3446849',
            'bytes' => 78,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-button-scope-tree-construction.html' => [
            'sha256' => 'a2ee7dd6a95ed8f4e3282aa9f8e620b3de8b390aad3e5ef7257bb503b4d57186',
            'bytes' => 35,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-pre-tree-construction.html' => [
            'sha256' => '8eceeeecbd143851572f8ed7c7ad5dd26ae61c60ebea20b08242d3770d344698',
            'bytes' => 35,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-output-inline.html' => [
            'sha256' => 'b7cdf8a4d74cdf334c8ed55dec1e96adb2af9b9f680d72b2ab33a9f2d603dba1',
            'bytes' => 47,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-cite-inline.html' => [
            'sha256' => '4ec967a951f951b47c64d1d8a500750b2a37d2e341ecaf7f89f43af1826996c5',
            'bytes' => 18,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-nav-tree-construction.html' => [
            'sha256' => '5fcd29ba0fc60c738e55a2dcf28733fd75777816ccfc68d3541eb7c0a8669813',
            'bytes' => 38,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-blockquote-tree-construction.html' => [
            'sha256' => 'bfbe9978a048677c0c48347d3940525725a20ef3516be4fe568cbad495c6edd4',
            'bytes' => 53,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-optional-option-tree-construction.html' => [
            'sha256' => 'df80d126a1a670f800dd1f6b25e8bf30441234999f3646aabce566bd6e5ae8f5',
            'bytes' => 66,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-form-in-form-tree-construction.html' => [
            'sha256' => 'de14a5e938bbf2a0637dad444c7f219b5e630f848de93bf194364781ce7d9397',
            'bytes' => 47,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-aside-tree-construction.html' => [
            'sha256' => '41a787852aa67f877da07fbfbfd8d6d7b8d93e0a7f66af041085aa06349025f1',
            'bytes' => 109,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-main-followed-by-text.html' => [
            'sha256' => '347f48d34d40488fa5a4167149c855fa2b5d1c6be5380216588237fa6077a2ca',
            'bytes' => 42,
        ],
        'upstream-html-paragraph-article-tree-construction.html' => [
            'sha256' => 'ddb92874e0d138288a79fdcaef96c22bcf7f22d11af9009e4abd30e97207465f',
            'bytes' => 113,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-empty-tables.html' => [
            'sha256' => 'cbf99b248f5c21a807bf9f98481a0291ef4e946f66311a4e2769a23dde5d92c7',
            'bytes' => 118,
            'sourceKind' => 'selected-upstream-html-reader-fixture',
        ],
        'upstream-html-body-lang-overrides-html.html' => [
            'sha256' => '7eebe387ceb242b10b110dce6397d05eb24d624a587e0e593e072908815c2f38',
            'bytes' => 76,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-code-aliases-inline.html' => [
            'sha256' => '2f77158fc9d147540d829e3414b049c9f754db38291ff523fc73dbfc3a852d9f',
            'bytes' => 93,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-foster-parent-table-text.html' => [
            'sha256' => 'd0f6f541e2aeac27af48e96b9929aaff30ca389112e43691bef6cb1b92e913ba',
            'bytes' => 51,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-standalone-b-i-inline.html' => [
            'sha256' => '504912dd740ea221ec0741dfb400072f587ff206f87cea26d4226600ab63a24f',
            'bytes' => 26,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-pre-code-class-precedence.html' => [
            'sha256' => '5a80c115dc5d4ed5883e0c1d6e67f9f6d77bdbbdfc0ed3683633086777dac57c',
            'bytes' => 212,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-definition-list-tree-construction.html' => [
            'sha256' => '3307984364ad9d3d7cbfd5d3f09f6f0578f7320a00cfff1cf5117c7089b14684',
            'bytes' => 108,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-base-fragment-link.html' => [
            'sha256' => '1841247f84acfab6aef873b7f7354d0fe12c55dff00100d0e6f8a08597cc86e7',
            'bytes' => 231,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-nested-paragraph-tree-construction.html' => [
            'sha256' => '75f4f8fca0a2c26bf6b202836d621182306f36fe5e837bb3b4c29c7ab604a71b',
            'bytes' => 63,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-fieldset-tree-construction.html' => [
            'sha256' => '0a8c5c9ce5293e52d0590a34538154e7b027306b9be42add4264f6c1835df764',
            'bytes' => 111,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-paragraph-heading-tree-construction.html' => [
            'sha256' => 'c8dce6817bef81e8ccd40b1579afd71d3415ffbf53382250b157f85021c388e9',
            'bytes' => 33,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-xmp-rawtext-fallback.html' => [
            'sha256' => '6b128bbb68c2168a47c87b18e8973d144af051be321c01867cc97b47fa244b19',
            'bytes' => 282,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-titlepage-skip.html' => [
            'sha256' => '228fb56876f317f173d333088e460dc7866b90fb8a4d2b63ca0d5451216cac9a',
            'bytes' => 294,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-menu-item-tree-construction.html' => [
            'sha256' => '16ceabed68b6ba331b60f655916df7c583e5f57c00fb5e41022ed486418c1a6f',
            'bytes' => 127,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-rawtext-fallback-containers.html' => [
            'sha256' => '7c8c30fc78501bd3e2656b4e346bf9bd48f82a64576df289a2039548932320d8',
            'bytes' => 270,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-button-inline-fallback.html' => [
            'sha256' => '3b5ea1b7ef017fef2371761b760cc270f5a9532c6ccd9a13fcfc9c0b7a8607ff',
            'bytes' => 95,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-fallback-content-containers.html' => [
            'sha256' => 'e01d947db29c0be9fac65ddab7502e63f429d4d29d6de6afc3d62d1846bec219',
            'bytes' => 275,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-inline-fallback-content-containers.html' => [
            'sha256' => '02d354aaff14ee364d44f6dc5df9c102c0d2f10dc50cecebc47bc9161ac10e6b',
            'bytes' => 297,
            'sourceKind' => 'direct-pandoc-3.10-native-probe',
        ],
        'upstream-html-reader-full-golden.html' => [
            'sha256' => 'a0ede6d299a88152efa6e776e4d3d6ecbdb2303e2efc195a7cb1d56820d71d25',
            'bytes' => 17314,
            'sourceKind' => 'selected-upstream-html-reader-fixture',
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
        $upstreamCommit = $this->gitHead($root);
        $validationIssues = $this->validationIssues($sourceInventory, $staticEvidence, $nativeAstEvidence, $upstreamCommit);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $upstreamCommit,
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerSources' => self::SOURCE_FILES,
            ],
            'denominator' => self::selectedFixtureDenominator(),
            'sourceInventory' => $sourceInventory,
            'staticCurrentEvidence' => $staticEvidence,
            'nativeAstEvidence' => $nativeAstEvidence,
            'runnerEvidence' => $this->runnerEvidence(self::selectedFixtureDenominator()),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-html-reader-evidence' : 'invalid-upstream-html-reader-evidence',
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
        $categoryCounts = [];
        $nativePairCount = 0;

        foreach (self::CHECKED_IN_HTML_FIXTURES as $name => $snapshot) {
            $category = self::categoryForFixture($name);
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $file = self::snapshotFileEvidence(
                $root,
                self::CHECKED_IN_FIXTURE_DIRECTORY . '/' . $name,
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $nativePairFile = self::currentFileEvidence(
                $root,
                self::CHECKED_IN_FIXTURE_DIRECTORY . '/' . self::nativePairFixtureName($name)
            );
            $testReferences = self::localTestReferences($root, $name);
            $fixtures[] = [
                'name' => $name,
                'category' => $category,
                'checkedInFile' => $file,
                'checkedInNativePairFile' => $nativePairFile,
                'localTestReferenceCount' => count($testReferences),
                'localTestReferences' => $testReferences,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-html-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-html-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-html-fixture-byte-count-mismatch';
            }
            if ($testReferences === []) {
                $issues[] = 'missing-html-fixture-local-test-reference';
            }
            if (($nativePairFile['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-native-pair-fixture';
            } else {
                ++$nativePairCount;
            }
        }
        ksort($categoryCounts, SORT_STRING);

        return [
            'kind' => 'static-checked-in-current-upstream-html-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerSources' => self::SOURCE_FILES,
            ],
            'readerDenominator' => self::selectedFixtureDenominator(),
            'checkedInFixtureDirectory' => self::CHECKED_IN_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInNativePairCount' => $nativePairCount,
            'checkedInCategoryCounts' => $categoryCounts,
            'checkedInFixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-html-reader-evidence' : 'invalid-checked-in-current-html-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding the checked-in upstream-derived and generated-current HTML reader fixture corpus, native expectations, and TagSoup-backed native AST comparison to SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the checked-in HTML reader fixture corpus has 133 pinned fixture snapshots',
                    'each pinned HTML fixture has a same-basename checked-in native expectation file',
                    'each pinned fixture has at least one local test reference',
                    'the current checked-in HTML/native mapped gate observes 133/133 normalized AST matches through the TagSoup-backed Pandoc HTML reader port when included in the report',
                ],
                'doesNotAssert' => [
                    'that this PHP report command executed upstream Haskell/Cabal/Tasty tests',
                    'that every inline Haskell assertion in Tests.Readers.HTML has a one-to-one checked-in fixture file',
                    'HTML writer parity',
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
        $native = is_array($report['nativeAstEvidence'] ?? null) ? $report['nativeAstEvidence'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $selectedFixtureCount = (int) (
            ($denominator['selectedFixtureCount'] ?? 0) !== 0
                ? $denominator['selectedFixtureCount']
                : ($staticDenominator['selectedFixtureCount'] ?? 0)
        );
        $runnerResultLine = self::hasRunnerResultArtifactEvidence($report)
            ? 'Supplied upstream Haskell/Cabal runner result artifact is validated; full HTML reader parity is not asserted.'
            : 'No upstream Haskell/Cabal runner result or full HTML reader parity is asserted.';

        return implode(PHP_EOL, [
            'Pandoc HTML reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Selected checked-in fixtures: ' . $selectedFixtureCount,
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0)
                . ' nativePairs=' . (int) ($staticEvidence['checkedInNativePairCount'] ?? 0),
            'Native AST mapped parity: ' . (int) ($native['normalizedAstMatchCount'] ?? 0)
                . '/' . (int) ($native['comparedPairCount'] ?? 0)
                . ' status=' . (string) ($native['astParityStatus'] ?? 'unknown'),
            'Native AST fixture inventory: html=' . (int) ($native['htmlFixtureCount'] ?? 0)
                . ' native=' . (int) ($native['nativeFixtureCount'] ?? 0)
                . ' paired=' . (int) ($native['pairedFixtureCount'] ?? $native['totalPairCount'] ?? 0)
                . ' unpairedHtml=' . (int) ($native['unpairedHtmlFixtureCount'] ?? 0)
                . ' unpairedNative=' . (int) ($native['unpairedNativeFixtureCount'] ?? 0),
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

        return ($validation['status'] ?? null) === 'valid-checked-in-current-html-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($staticEvidence['checkedInNativePairCount'] ?? -1) === self::EXPECTED_SELECTED_FIXTURE_COUNT
            && self::hasRequiredSelectedFixtureCount($report, self::EXPECTED_SELECTED_FIXTURE_COUNT);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredNativeMappedParity(array $report, int $requiredPairCount): bool
    {
        $native = is_array($report['nativeAstEvidence'] ?? null) ? $report['nativeAstEvidence'] : [];

        return HtmlNativeAstComparisonHarness::hasRequiredMappedParity($native, $requiredPairCount);
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
            && ($binding['readerTestModule'] ?? null) === 'test/Tests/Readers/HTML.hs'
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
            && ($runner['runner'] ?? null) === 'Cabal/Tasty Pandoc HTML reader suite'
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
            && ($validation['status'] ?? null) === 'valid-upstream-html-reader-runner-result-artifact'
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

        return ($validation['status'] ?? null) === 'valid-upstream-html-reader-evidence'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function selectedFixtureDenominator(): array
    {
        $fixtures = [];
        $categories = [];
        foreach (array_keys(self::CHECKED_IN_HTML_FIXTURES) as $name) {
            $category = self::categoryForFixture($name);
            $fixtures[] = [
                'name' => $name,
                'category' => $category,
                'sourceKind' => (string) (self::CHECKED_IN_HTML_FIXTURES[$name]['sourceKind'] ?? 'selected-upstream-html-reader-fixture'),
            ];
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }
        ksort($categories, SORT_STRING);

        return [
            'selectedFixtureCount' => count($fixtures),
            'fixtureScope' => 'selected checked-in upstream-derived and generated-current HTML reader fixtures',
            'selectedFixtures' => $fixtures,
            'categoryCounts' => $categories,
            'nativeMappedPairCount' => self::EXPECTED_NATIVE_MAPPED_PAIR_COUNT,
            'nativeMappedPairExclusionCount' => count(self::HTMLDOCUMENT_MAPPED_PAIR_EXCLUSIONS),
            'nativeMappedPairExclusions' => array_map(
                static fn (string $name, string $reason): array => ['name' => $name, 'reason' => $reason],
                array_keys(self::HTMLDOCUMENT_MAPPED_PAIR_EXCLUSIONS),
                array_values(self::HTMLDOCUMENT_MAPPED_PAIR_EXCLUSIONS)
            ),
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
            if (($payload['runner'] ?? null) !== 'Cabal/Tasty Pandoc HTML reader suite') {
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
            'runner' => 'Cabal/Tasty Pandoc HTML reader suite',
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
                'readerTestModule' => 'test/Tests/Readers/HTML.hs',
            ],
            'target' => [
                'testSuite' => is_string($target['testSuite'] ?? null) ? $target['testSuite'] : null,
                'tastyGroupPath' => is_array($target['tastyGroupPath'] ?? null) ? $target['tastyGroupPath'] : null,
                'tastyPattern' => is_string($target['tastyPattern'] ?? null) ? $target['tastyPattern'] : null,
            ],
            'expected' => [
                'schemaVersion' => self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION,
                'runner' => 'Cabal/Tasty Pandoc HTML reader suite',
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
                    ? 'valid-upstream-html-reader-runner-result-artifact'
                    : 'invalid-upstream-html-reader-runner-result-artifact',
                'issues' => $issues,
            ],
            'claim' => $issues === []
                ? 'A supplied upstream HTML reader runner result artifact matches the pinned targeted Tasty runner evidence contract.'
                : 'The supplied upstream HTML reader runner result artifact did not satisfy the pinned targeted Tasty runner evidence contract.',
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
            'runner' => 'Cabal/Tasty Pandoc HTML reader suite',
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
                'readerTestModule' => 'test/Tests/Readers/HTML.hs',
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
                'purpose' => 'list targeted HTML reader tests',
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
                'purpose' => 'run targeted HTML reader tests',
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
        return 'Tracks selected checked-in upstream-derived and generated-current HTML reader fixtures, local test references, and the same-basename HTML/native AST comparison for HTML reader implementation equivalence.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the identity and count of 133 selected checked-in upstream-derived and generated-current HTML fixtures',
                'that each selected fixture has a same-basename checked-in native expectation file',
                'that each selected fixture is referenced by at least one local focused test',
                'that the native AST gate observes 133/133 TagSoup-backed checked-in same-basename HTML/native matches',
                'that upstream Haskell runner evidence is either explicitly not-run or supplied as a validated result artifact',
                'the future upstream runner command plan targets test:test-pandoc Readers/HTML at the pinned upstream commit without execution',
                'a supplied upstream runner result artifact is validated against the pinned HTML Tasty target, commit, test names, pass/fail counts, and transcript file identities when explicitly provided',
            ],
            'doesNotAssert' => [
                'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                'HTML writer parity',
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
            'fixtureScope' => 'selected checked-in upstream-derived and generated-current HTML reader fixtures',
            'selectedFixtures' => [],
            'categoryCounts' => [],
            'nativeMappedPairCount' => 0,
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
     * @param array<string, mixed> $nativeAstEvidence
     * @return list<string>
     */
    private function validationIssues(array $sourceInventory, array $staticEvidence, array $nativeAstEvidence, ?string $upstreamCommit): array
    {
        $issues = [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        if ($upstreamCommit !== self::EXPECTED_UPSTREAM_COMMIT) {
            $issues[] = 'upstream-html-reader-commit-mismatch';
        }
        if (($staticValidation['status'] ?? null) !== 'valid-checked-in-current-html-reader-evidence') {
            $issues[] = 'invalid-checked-in-current-html-reader-evidence';
        }
        if ((int) ($sourceInventory['missingFileCount'] ?? 0) > 0) {
            $issues[] = 'missing-upstream-html-reader-source';
        }
        if (!HtmlNativeAstComparisonHarness::hasRequiredMappedParity($nativeAstEvidence, self::EXPECTED_NATIVE_MAPPED_PAIR_COUNT)) {
            $issues[] = 'html-native-ast-mapped-parity-not-observed';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function nativeAstEvidence(): array
    {
        return (new HtmlNativeAstComparisonHarness())->run($this->repoRoot . '/lanes/pandoc/fixtures');
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

    private static function nativePairFixtureName(string $htmlFixtureName): string
    {
        $nativeFixtureName = preg_replace('/\.html$/', '.native', $htmlFixtureName);

        return is_string($nativeFixtureName) && $nativeFixtureName !== $htmlFixtureName
            ? $nativeFixtureName
            : $htmlFixtureName . '.native';
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private static function currentFileEvidence(string $root, string $relativePath): array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : false;
        $bytes = $present ? filesize($path) : false;

        return [
            'path' => $relativePath,
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
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
            if ($file->getFilename() === 'HtmlUpstreamReaderEvidenceTest.php') {
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

    private static function categoryForFixture(string $name): string
    {
        if ($name === 'upstream-native-html-row-header-table.html' || $name === 'upstream-html-multi-tbody-row-header-table.html') {
            return 'native-html-table-row-header';
        }
        if (str_contains($name, 'doc-noteref')) {
            return 'doc-noteref-footnote-table';
        }
        if (str_contains($name, 'raw') || str_contains($name, 'svg') || str_contains($name, 'script') || str_contains($name, 'style') || str_contains($name, 'textarea')) {
            return 'raw-html-boundary';
        }
        if (str_contains($name, 'math')) {
            return 'math-html';
        }
        if (str_contains($name, 'standalone')) {
            return 'standalone-inline-html';
        }
        if ($name === 'upstream-html-paragraph-block-tree-construction.html'
            || $name === 'upstream-html-paragraph-aside-tree-construction.html'
            || $name === 'upstream-html-paragraph-nav-tree-construction.html'
            || $name === 'upstream-html-paragraph-transparent-block-tree-construction.html'
            || $name === 'upstream-html-nested-paragraph-tree-construction.html'
            || $name === 'upstream-html-paragraph-pre-tree-construction.html'
            || $name === 'upstream-html-paragraph-fieldset-tree-construction.html'
            || $name === 'upstream-html-button-scope-tree-construction.html'
            || $name === 'upstream-html-form-in-form-tree-construction.html'
            || $name === 'upstream-html-fallback-content-containers.html'
            || str_contains($name, 'address')
            || str_contains($name, 'article')
            || str_contains($name, 'blockquote')
            || str_contains($name, 'heading')
            || str_contains($name, 'menu')
            || str_contains($name, 'table')
            || str_contains($name, 'titlepage')
            || str_contains($name, 'list')
            || str_contains($name, 'figure')
        ) {
            return 'block-structure';
        }

        return 'inline-and-metadata';
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
