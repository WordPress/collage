<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-pptx-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-pptx-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-pptx-root';
    public const CHECKED_IN_CURRENT_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-pptx-reader';
    public const EXPECTED_STATIC_READER_TEST_COMPARE_COUNT = 1;
    public const EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT = 109;

    private const CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_PATH = 'lanes/pandoc/fixtures/upstream-current-pptx-reader/checked-in.executable-native-ast.json';
    private const CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_SHA256 = 'c69de62705d1c19ec24f9e8366b1881fe0cf32a780d35bf3a443d7ef133b3598';
    private const CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_BYTES = 47011;
    private const RUNNER_TEST_SUITE = 'test:test-pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/pptx-targeted-run';
    private const RUNNER_TASTY_GROUP_PATH = ['Readers', 'Pptx'];
    private const RUNNER_TASTY_PATTERN = '$2 == "Readers" && $3 == "Pptx"';
    private const RUNNER_REQUIRED_TRANSCRIPTS = [
        '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
        '.port-libs/pandoc-runner/logs/pptx-targeted-list-tests.txt',
        '.port-libs/pandoc-runner/logs/pptx-targeted-run.txt',
    ];
    private const RUNNER_REQUIRED_ARTIFACTS = [
        '.port-libs/pandoc-runner/artifacts/pptx-targeted-run/result.json',
    ];
    private const RUNNER_RESULT_ARTIFACT_KIND = 'upstream-pptx-reader-runner-result-artifact';
    private const RUNNER_TRANSCRIPT_KIND = 'upstream-pptx-reader-runner-transcript';
    private const RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION = 2;

    private const STATIC_CURRENT_READER_CASES = [
        [
            'name' => 'text extraction',
            'pptx' => 'pptx-reader/basic.pptx',
            'native' => 'pptx-reader/basic.native',
            'pairKey' => 'pptx-reader/basic.pptx|pptx-reader/basic.native',
        ],
    ];
    private const CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT = [
        'basic' => [
            'name' => 'text extraction',
            'pptx' => 'pptx-reader/basic.pptx',
            'native' => 'pptx-reader/basic.native',
            'pairKey' => 'pptx-reader/basic.pptx|pptx-reader/basic.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/basic.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/basic.native',
            'pptxSha256' => 'e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc',
            'nativeSha256' => '42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4',
            'pptxBytes' => 111674,
            'nativeBytes' => 3966,
        ],
        'alternate-content-skip' => [
            'name' => 'generated alternate content wrapper skip parity',
            'pptx' => 'pptx-reader/alternate-content-skip.pptx',
            'native' => 'pptx-reader/alternate-content-skip.native',
            'pairKey' => 'pptx-reader/alternate-content-skip.pptx|pptx-reader/alternate-content-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/alternate-content-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/alternate-content-skip.native',
            'pptxSha256' => 'f84d950060c44f8f0b85ed15f29c5760c49aaf445b101806d70711507c93a194',
            'nativeSha256' => 'd067f8fa32d162f9bc33280c7bc4b725fb1543b454861b7f61e31ef2a18acea1',
            'pptxBytes' => 1534,
            'nativeBytes' => 185,
        ],
        'background-image-skip' => [
            'name' => 'generated background image relationship skip parity',
            'pptx' => 'pptx-reader/background-image-skip.pptx',
            'native' => 'pptx-reader/background-image-skip.native',
            'pairKey' => 'pptx-reader/background-image-skip.pptx|pptx-reader/background-image-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/background-image-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/background-image-skip.native',
            'pptxSha256' => 'e9483d1883b3ea011c81ddf140e6400334f3aa3d52acd3b5f68cf81f0ead1769',
            'nativeSha256' => '5c14cd84da6db538df5569de41e3f5a29ac75b4030de9e3e0b4e28caf14e7b5d',
            'pptxBytes' => 2328,
            'nativeBytes' => 127,
        ],
        'body-before-title' => [
            'name' => 'generated body-before-title placeholder ordering parity',
            'pptx' => 'pptx-reader/body-before-title.pptx',
            'native' => 'pptx-reader/body-before-title.native',
            'pairKey' => 'pptx-reader/body-before-title.pptx|pptx-reader/body-before-title.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/body-before-title.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/body-before-title.native',
            'pptxSha256' => '0211c524f44cac1d910cb51f9540bf2fa6dd6b497d3c018ff4f06517be6564c1',
            'nativeSha256' => 'e0b1dacb8bd85677d2556009e0a79e4443680cf98e45e26c4a3a0747800d7453',
            'pptxBytes' => 1519,
            'nativeBytes' => 132,
        ],
        'minimal' => [
            'name' => 'generated minimal text extraction parity',
            'pptx' => 'pptx-reader/minimal.pptx',
            'native' => 'pptx-reader/minimal.native',
            'pairKey' => 'pptx-reader/minimal.pptx|pptx-reader/minimal.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/minimal.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/minimal.native',
            'pptxSha256' => 'f4852d7b0455ae99a8ef2b3d419cb2aa9ab2f8b5c4167e3770a38483ab36f202',
            'nativeSha256' => '6ec8b821c9a28c12ca65c771d7dcb6df0ec7f9f91b139e318d4cdbbd4fde4c76',
            'pptxBytes' => 1502,
            'nativeBytes' => 119,
        ],
        'missing-relationship-skip' => [
            'name' => 'generated missing relationship skip parity',
            'pptx' => 'pptx-reader/missing-relationship-skip.pptx',
            'native' => 'pptx-reader/missing-relationship-skip.native',
            'pairKey' => 'pptx-reader/missing-relationship-skip.pptx|pptx-reader/missing-relationship-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/missing-relationship-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/missing-relationship-skip.native',
            'pptxSha256' => '0a9ed423c8987719d2b5ac4ed3367db507ece227737e141b371812b57c18e77a',
            'nativeSha256' => 'e751a414543010757345bac58bc4fb6157c1c99cbbd0e958f39753c18db3e5cd',
            'pptxBytes' => 1795,
            'nativeBytes' => 144,
        ],
        'missing-slide-relationship-type' => [
            'name' => 'generated missing slide relationship Type fallback parity',
            'pptx' => 'pptx-reader/missing-slide-relationship-type.pptx',
            'native' => 'pptx-reader/missing-slide-relationship-type.native',
            'pairKey' => 'pptx-reader/missing-slide-relationship-type.pptx|pptx-reader/missing-slide-relationship-type.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/missing-slide-relationship-type.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/missing-slide-relationship-type.native',
            'pptxSha256' => 'ba37020b6c4a1118758c3dc53292e7137e52cd2188ac6c46ce236f9000b442b4',
            'nativeSha256' => '10d3dad7c71db5cf453d3602d085f9c9cf63a6d63d4ae0558b8e75b7eabde390',
            'pptxBytes' => 1707,
            'nativeBytes' => 157,
        ],
        'multi-paragraph-table-cell' => [
            'name' => 'generated multi-paragraph table cell flattening parity',
            'pptx' => 'pptx-reader/multi-paragraph-table-cell.pptx',
            'native' => 'pptx-reader/multi-paragraph-table-cell.native',
            'pairKey' => 'pptx-reader/multi-paragraph-table-cell.pptx|pptx-reader/multi-paragraph-table-cell.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/multi-paragraph-table-cell.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/multi-paragraph-table-cell.native',
            'pptxSha256' => 'ea8c0da62e75f272bd9f3ae72e9c646086e698ee518e8b7c66ff59ed8eafdd19',
            'nativeSha256' => '779fba71bf9c3b12489fe696e19362fc4c435f9cc00c41958887a5d16fa1cff6',
            'pptxBytes' => 1622,
            'nativeBytes' => 1224,
        ],
        'multi-paragraph-textbox' => [
            'name' => 'generated multi-paragraph text box parity',
            'pptx' => 'pptx-reader/multi-paragraph-textbox.pptx',
            'native' => 'pptx-reader/multi-paragraph-textbox.native',
            'pairKey' => 'pptx-reader/multi-paragraph-textbox.pptx|pptx-reader/multi-paragraph-textbox.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/multi-paragraph-textbox.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/multi-paragraph-textbox.native',
            'pptxSha256' => 'f586b777919bb9266acec04640e2992be888ab987009e9d6866dc440d5f3060e',
            'nativeSha256' => '1201499244544e7be60096ac6d0a434ed10036429d0bf18b6dcf2807eb8ad8fd',
            'pptxBytes' => 1519,
            'nativeBytes' => 177,
        ],
        'multiple-paragraph-properties' => [
            'name' => 'generated first paragraph properties child parity',
            'pptx' => 'pptx-reader/multiple-paragraph-properties.pptx',
            'native' => 'pptx-reader/multiple-paragraph-properties.native',
            'pairKey' => 'pptx-reader/multiple-paragraph-properties.pptx|pptx-reader/multiple-paragraph-properties.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/multiple-paragraph-properties.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/multiple-paragraph-properties.native',
            'pptxSha256' => 'c2cf31c18f58461b4f016edc1e005124c9a7f5c5405f52a8d7c4e3ac3a267818',
            'nativeSha256' => 'dd233a289b57a8fd950c49a5cb4d60835cd9a39905c41701278132a596a413e8',
            'pptxBytes' => 1473,
            'nativeBytes' => 297,
        ],
        'namespace-agnostic-drawing-text' => [
            'name' => 'generated terminal nested drawing text parity',
            'pptx' => 'pptx-reader/namespace-agnostic-drawing-text.pptx',
            'native' => 'pptx-reader/namespace-agnostic-drawing-text.native',
            'pairKey' => 'pptx-reader/namespace-agnostic-drawing-text.pptx|pptx-reader/namespace-agnostic-drawing-text.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/namespace-agnostic-drawing-text.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/namespace-agnostic-drawing-text.native',
            'pptxSha256' => '6203769629208902d006b9a481e8a3a75e31d56b4d470da081dfa78049df4a95',
            'nativeSha256' => '7e162f0d57dcd35ee21a78ef454f31fa4862dae95294437a6e71ce7dd85a02c3',
            'pptxBytes' => 1411,
            'nativeBytes' => 166,
        ],
        'paragraph-property-descendant-text' => [
            'name' => 'generated paragraph-property descendant text parity',
            'pptx' => 'pptx-reader/paragraph-property-descendant-text.pptx',
            'native' => 'pptx-reader/paragraph-property-descendant-text.native',
            'pairKey' => 'pptx-reader/paragraph-property-descendant-text.pptx|pptx-reader/paragraph-property-descendant-text.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/paragraph-property-descendant-text.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/paragraph-property-descendant-text.native',
            'pptxSha256' => '7e1190e41ff541fdd9f09dd3c67110cfda46f4aed4fd2b3c9fa60459e73e4ccf',
            'nativeSha256' => '758e867887f88b35ec6bdc27ed1fe04baa4b6bbc5d535c1576f40f8c81aa3bf3',
            'pptxBytes' => 1413,
            'nativeBytes' => 185,
        ],
        'namespace-scoped-table' => [
            'name' => 'generated namespace-scoped table boundary parity',
            'pptx' => 'pptx-reader/namespace-scoped-table.pptx',
            'native' => 'pptx-reader/namespace-scoped-table.native',
            'pairKey' => 'pptx-reader/namespace-scoped-table.pptx|pptx-reader/namespace-scoped-table.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/namespace-scoped-table.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/namespace-scoped-table.native',
            'pptxSha256' => '81c2a77fe8ebd2965b39506d4ea52400617133ab74b8c29d39cae18df93ae83d',
            'nativeSha256' => '7f85d7011628e5ca386fc75a5bf138674f47a9b3d2004a2f58df144bd920336e',
            'pptxBytes' => 1609,
            'nativeBytes' => 1039,
        ],
        'nested-list' => [
            'name' => 'generated adjacent list-level split parity',
            'pptx' => 'pptx-reader/nested-list.pptx',
            'native' => 'pptx-reader/nested-list.native',
            'pairKey' => 'pptx-reader/nested-list.pptx|pptx-reader/nested-list.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/nested-list.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/nested-list.native',
            'pptxSha256' => 'c85b56c09a3568286e4c0d7b1979d88b700d5f609e121955c691a58f2bb97ff0',
            'nativeSha256' => '395c237357a332023f6bb3c991f2f84d54be6fb277ce964cdaad6d9ffe2336a6',
            'pptxBytes' => 1703,
            'nativeBytes' => 253,
        ],
        'no-slides' => [
            'name' => 'generated no-slide presentation parity',
            'pptx' => 'pptx-reader/no-slides.pptx',
            'native' => 'pptx-reader/no-slides.native',
            'pairKey' => 'pptx-reader/no-slides.pptx|pptx-reader/no-slides.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/no-slides.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/no-slides.native',
            'pptxSha256' => '06ee4f11b616153b569aba25917a0c77fd963ad825e65f3a61baf6d83988aead',
            'nativeSha256' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
            'pptxBytes' => 781,
            'nativeBytes' => 3,
        ],
        'no-title-fallback' => [
            'name' => 'generated no-title slide fallback parity',
            'pptx' => 'pptx-reader/no-title-fallback.pptx',
            'native' => 'pptx-reader/no-title-fallback.native',
            'pairKey' => 'pptx-reader/no-title-fallback.pptx|pptx-reader/no-title-fallback.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/no-title-fallback.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/no-title-fallback.native',
            'pptxSha256' => 'de4ad6bc1bf66072bdcb96e31390955f4a283e3be94b0f691dc96ba36765f557',
            'nativeSha256' => 'fcd4183bbfebc6ecd4118786cf7bbc1fb760f2e385d6bbb9bab6031851557763',
            'pptxBytes' => 1533,
            'nativeBytes' => 103,
        ],
        'non-relationship-child-relationships' => [
            'name' => 'generated non-Relationship child relationship parity',
            'pptx' => 'pptx-reader/non-relationship-child-relationships.pptx',
            'native' => 'pptx-reader/non-relationship-child-relationships.native',
            'pairKey' => 'pptx-reader/non-relationship-child-relationships.pptx|pptx-reader/non-relationship-child-relationships.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/non-relationship-child-relationships.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/non-relationship-child-relationships.native',
            'pptxSha256' => 'f528a1f1d7a380770ac34c583c0a9eb3279e895871d01bf1d707f3255c88addc',
            'nativeSha256' => '2f44281be9a23ee4bcac11b033ee74320071e1ac1733d479051beffd19d49124',
            'pptxBytes' => 1867,
            'nativeBytes' => 247,
        ],
        'paragraphless-textbox' => [
            'name' => 'generated paragraphless text box skip parity',
            'pptx' => 'pptx-reader/paragraphless-textbox.pptx',
            'native' => 'pptx-reader/paragraphless-textbox.native',
            'pairKey' => 'pptx-reader/paragraphless-textbox.pptx|pptx-reader/paragraphless-textbox.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/paragraphless-textbox.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/paragraphless-textbox.native',
            'pptxSha256' => '5ecbb58a28c01bba60dab87081eb69b475fd87817410197f87d003443e38a49b',
            'nativeSha256' => '1b8599dd7c13c0c93a592eff7fae16bc53bb07d3ae53b788ecd7874b7e8106e8',
            'pptxBytes' => 1544,
            'nativeBytes' => 113,
        ],
        'empty-paragraph-textbox' => [
            'name' => 'generated explicit empty paragraph text box parity',
            'pptx' => 'pptx-reader/empty-paragraph-textbox.pptx',
            'native' => 'pptx-reader/empty-paragraph-textbox.native',
            'pairKey' => 'pptx-reader/empty-paragraph-textbox.pptx|pptx-reader/empty-paragraph-textbox.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-paragraph-textbox.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-paragraph-textbox.native',
            'pptxSha256' => '3c2746d48004a382c77a6b0780c31dae0246c9f9063251db2f93bcc16e688655',
            'nativeSha256' => '9a1dd6f8ddf28f555cd1f128f5e24864284f1a721d2ae3c1e4598ebdcbe9b21b',
            'pptxBytes' => 1519,
            'nativeBytes' => 169,
        ],
        'empty-title-placeholder' => [
            'name' => 'generated empty title placeholder fallback parity',
            'pptx' => 'pptx-reader/empty-title-placeholder.pptx',
            'native' => 'pptx-reader/empty-title-placeholder.native',
            'pairKey' => 'pptx-reader/empty-title-placeholder.pptx|pptx-reader/empty-title-placeholder.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-title-placeholder.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-title-placeholder.native',
            'pptxSha256' => '4a15fd6e8508407c05e09b9c1fcd3481df624d810b6a4443ec2a99271bd83d12',
            'nativeSha256' => 'aa2979e514f11a5ef811ef8a1a9c2b7a5e61fbf6edc3323dbb8f80d11de1fb3f',
            'pptxBytes' => 1495,
            'nativeBytes' => 98,
        ],
        'end-paragraph-symbol' => [
            'name' => 'generated end-paragraph Wingdings symbol locality parity',
            'pptx' => 'pptx-reader/end-paragraph-symbol.pptx',
            'native' => 'pptx-reader/end-paragraph-symbol.native',
            'pairKey' => 'pptx-reader/end-paragraph-symbol.pptx|pptx-reader/end-paragraph-symbol.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/end-paragraph-symbol.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/end-paragraph-symbol.native',
            'pptxSha256' => '5bf9f05d16bbd53092f9e7c4d7cc68be91370d4c2027d59aa4b31f7649569f0a',
            'nativeSha256' => 'bbcbc22ada6d869940c3b7512ea52562df6a54e6b872a23fb7d536857dbb0466',
            'pptxBytes' => 1654,
            'nativeBytes' => 100,
        ],
        'external-mode-slide-target' => [
            'name' => 'generated slide TargetMode ignored parity',
            'pptx' => 'pptx-reader/external-mode-slide-target.pptx',
            'native' => 'pptx-reader/external-mode-slide-target.native',
            'pairKey' => 'pptx-reader/external-mode-slide-target.pptx|pptx-reader/external-mode-slide-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/external-mode-slide-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/external-mode-slide-target.native',
            'pptxSha256' => 'b0bb86a568a2020e07bebe50775568a3dc2fbd27a10d4ab8144258ae9f7f3eef',
            'nativeSha256' => '7f8c73a728f91c0142c503e40e06a3fa5bf76c1d931d63ffbcf3ffacf205c918',
            'pptxBytes' => 1707,
            'nativeBytes' => 110,
        ],
        'root-targetmode-external' => [
            'name' => 'generated root officeDocument TargetMode ignored parity',
            'pptx' => 'pptx-reader/root-targetmode-external.pptx',
            'native' => 'pptx-reader/root-targetmode-external.native',
            'pairKey' => 'pptx-reader/root-targetmode-external.pptx|pptx-reader/root-targetmode-external.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/root-targetmode-external.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/root-targetmode-external.native',
            'pptxSha256' => '4e3501e9cfe8c0e23e5c977c3ba0b56ea955a96382402144fff007c8ff323587',
            'nativeSha256' => 'f14cb1439bde326803f0428b5ef451c49d557a66e9d3594a1c3b6e1b3ab6905f',
            'pptxBytes' => 1679,
            'nativeBytes' => 75,
        ],
        'root-relationship-root-alias' => [
            'name' => 'generated root officeDocument relationship root alias parity',
            'pptx' => 'pptx-reader/root-relationship-root-alias.pptx',
            'native' => 'pptx-reader/root-relationship-root-alias.native',
            'pairKey' => 'pptx-reader/root-relationship-root-alias.pptx|pptx-reader/root-relationship-root-alias.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/root-relationship-root-alias.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/root-relationship-root-alias.native',
            'pptxSha256' => '1b9177525b9c4361cb1ffab8bdef35c25f20ffaef38348cc2dbb53059f5e1b3f',
            'nativeSha256' => '7246e1485a5e1d1ce805ca0e3655966f4894be3eafee0ea90936f97fa869c31b',
            'pptxBytes' => 1509,
            'nativeBytes' => 97,
        ],
        'external-rich-media-skip' => [
            'name' => 'generated external rich media placeholder skip parity',
            'pptx' => 'pptx-reader/external-rich-media-skip.pptx',
            'native' => 'pptx-reader/external-rich-media-skip.native',
            'pairKey' => 'pptx-reader/external-rich-media-skip.pptx|pptx-reader/external-rich-media-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/external-rich-media-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/external-rich-media-skip.native',
            'pptxSha256' => '26b4f782fcb6aa221cc33495d5635b52c536ada9f4a6a116f4e06c927f38d86b',
            'nativeSha256' => '600f69ab626db820ccdbdbd28f0d8e3f43dde299bf35d82eb77dab031f229b20',
            'pptxBytes' => 2340,
            'nativeBytes' => 135,
        ],
        'field-title' => [
            'name' => 'generated field-backed title placeholder parity',
            'pptx' => 'pptx-reader/field-title.pptx',
            'native' => 'pptx-reader/field-title.native',
            'pairKey' => 'pptx-reader/field-title.pptx|pptx-reader/field-title.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/field-title.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/field-title.native',
            'pptxSha256' => '3f7ed6cd3d9f6088310c5b92652bab7f382d4ec3f128c2d92caf4408fae374cf',
            'nativeSha256' => '125290aa28897bd5dd8bb81472ac61216d200bb8ef6b2358b7992438ea071568',
            'pptxBytes' => 1545,
            'nativeBytes' => 116,
        ],
        'first-text-body' => [
            'name' => 'generated first text body child parity',
            'pptx' => 'pptx-reader/first-text-body.pptx',
            'native' => 'pptx-reader/first-text-body.native',
            'pairKey' => 'pptx-reader/first-text-body.pptx|pptx-reader/first-text-body.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/first-text-body.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/first-text-body.native',
            'pptxSha256' => '9632d9605fcc1ee78db83843121f12c297eafeab15d6098932e8738d6dd74624',
            'nativeSha256' => '98aabf841a37c3c677ef20c7ac0a3987ec55bcc38bde09efcaac83bfc39619e7',
            'pptxBytes' => 1552,
            'nativeBytes' => 63,
        ],
        'first-title-placeholder' => [
            'name' => 'generated first duplicate title placeholder parity',
            'pptx' => 'pptx-reader/first-title-placeholder.pptx',
            'native' => 'pptx-reader/first-title-placeholder.native',
            'pairKey' => 'pptx-reader/first-title-placeholder.pptx|pptx-reader/first-title-placeholder.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/first-title-placeholder.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/first-title-placeholder.native',
            'pptxSha256' => '82b4a59db7cc37a0f31602bc10c60cfb0d9bcb191f6675cd54215e637618a4c6',
            'nativeSha256' => 'acad0d5021e8a75d5793e332692bd48b0e7ebe51d69f63cf179e094940f4167b',
            'pptxBytes' => 1344,
            'nativeBytes' => 103,
        ],
        'first-run-property-symbol' => [
            'name' => 'generated first run-property Wingdings symbol parity',
            'pptx' => 'pptx-reader/first-run-property-symbol.pptx',
            'native' => 'pptx-reader/first-run-property-symbol.native',
            'pairKey' => 'pptx-reader/first-run-property-symbol.pptx|pptx-reader/first-run-property-symbol.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/first-run-property-symbol.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/first-run-property-symbol.native',
            'pptxSha256' => 'ba8e59a0ffaf54ca3bf1966bc508c4237b556105fd8437b1b74d3a1e9ba4aa0f',
            'nativeSha256' => '4aba0d35d612be97b9dcdc36293d4bd244c9c2fa43d3da270fc873c9930c831d',
            'pptxBytes' => 1417,
            'nativeBytes' => 251,
        ],
        'first-table-cell-text-body' => [
            'name' => 'generated first table cell text body parity',
            'pptx' => 'pptx-reader/first-table-cell-text-body.pptx',
            'native' => 'pptx-reader/first-table-cell-text-body.native',
            'pairKey' => 'pptx-reader/first-table-cell-text-body.pptx|pptx-reader/first-table-cell-text-body.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/first-table-cell-text-body.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/first-table-cell-text-body.native',
            'pptxSha256' => '3c922321649bbabb5a9676f354ca2ef62abcefb0c7006501ae1c2010edc3e629',
            'nativeSha256' => '7813fdec7fde0ca32e37328577f91cfc0b0518a72d9a2c6084e2fb3e41e4e7e3',
            'pptxBytes' => 1510,
            'nativeBytes' => 1229,
        ],
        'break-tab-field' => [
            'name' => 'generated break, tab, and field text boundary parity',
            'pptx' => 'pptx-reader/break-tab-field.pptx',
            'native' => 'pptx-reader/break-tab-field.native',
            'pairKey' => 'pptx-reader/break-tab-field.pptx|pptx-reader/break-tab-field.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/break-tab-field.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/break-tab-field.native',
            'pptxSha256' => 'eab556ea99844fb5f815f977d46d5a1923d59f71682c7cceae5e23b5937f113c',
            'nativeSha256' => 'e619a9e7b375700d5fd8c2c74cd9bb5c424098d39b972212a86f58764affadf4',
            'pptxBytes' => 1435,
            'nativeBytes' => 113,
        ],
        'whitespace-drawing-text' => [
            'name' => 'generated whitespace-only drawing text parity',
            'pptx' => 'pptx-reader/whitespace-drawing-text.pptx',
            'native' => 'pptx-reader/whitespace-drawing-text.native',
            'pairKey' => 'pptx-reader/whitespace-drawing-text.pptx|pptx-reader/whitespace-drawing-text.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/whitespace-drawing-text.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/whitespace-drawing-text.native',
            'pptxSha256' => '5ae9d3ad48991a588151ed1be4b24c049cdcba38c934832dad3b3e2e583aca1c',
            'nativeSha256' => 'fca7800593fc3281941905b032d7256d447402d49b3b6886df979d264d5ce7c5',
            'pptxBytes' => 1759,
            'nativeBytes' => 614,
        ],
        'bullets' => [
            'name' => 'generated minimal bullet list parity',
            'pptx' => 'pptx-reader/bullets.pptx',
            'native' => 'pptx-reader/bullets.native',
            'pairKey' => 'pptx-reader/bullets.pptx|pptx-reader/bullets.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/bullets.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/bullets.native',
            'pptxSha256' => '912915e6c9a56eda1e2cb657b23cd007cd0c49da8d8d96a199e9cb8c1e310760',
            'nativeSha256' => 'f53f49de194917ae945eaaff66720120bf8a0df95c6075b31a08ea41f633507c',
            'pptxBytes' => 1543,
            'nativeBytes' => 157,
        ],
        'bunone-wingdings' => [
            'name' => 'generated buNone Wingdings bullet override parity',
            'pptx' => 'pptx-reader/bunone-wingdings.pptx',
            'native' => 'pptx-reader/bunone-wingdings.native',
            'pairKey' => 'pptx-reader/bunone-wingdings.pptx|pptx-reader/bunone-wingdings.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/bunone-wingdings.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/bunone-wingdings.native',
            'pptxSha256' => '8ccf4cfc9c7aeda294d99f34f0364ebfdb42b75d013d96bb9b9fc7776d9b0467',
            'nativeSha256' => 'f89bc42f76c23972fef13fac39bdbb5fafa0f690f488a45eb97f2469d58d4771',
            'pptxBytes' => 1697,
            'nativeBytes' => 232,
        ],
        'case-sensitive-placeholder-type' => [
            'name' => 'generated case-sensitive placeholder type fallback parity',
            'pptx' => 'pptx-reader/case-sensitive-placeholder-type.pptx',
            'native' => 'pptx-reader/case-sensitive-placeholder-type.native',
            'pairKey' => 'pptx-reader/case-sensitive-placeholder-type.pptx|pptx-reader/case-sensitive-placeholder-type.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/case-sensitive-placeholder-type.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/case-sensitive-placeholder-type.native',
            'pptxSha256' => '266be65ed22ffcd004f9aa15b02a03b694046753195758bd4d90c088173fe235',
            'nativeSha256' => '338fda8cc76cf5ce483903d350023f942a9ac3ce391fe2172e51f224d62de47f',
            'pptxBytes' => 1486,
            'nativeBytes' => 116,
        ],
        'wingdings-typeface-case' => [
            'name' => 'generated Wingdings typeface case matching parity',
            'pptx' => 'pptx-reader/wingdings-typeface-case.pptx',
            'native' => 'pptx-reader/wingdings-typeface-case.native',
            'pairKey' => 'pptx-reader/wingdings-typeface-case.pptx|pptx-reader/wingdings-typeface-case.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/wingdings-typeface-case.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/wingdings-typeface-case.native',
            'pptxSha256' => '2a37ab63e4052cacdfaa24aca6e8dbb11ea16ac41aa42996e1f82c358197582d',
            'nativeSha256' => '6410058cd9a16830e37c5039097a69c2308a43b2e1d3149af760c8cd6356b755',
            'pptxBytes' => 1470,
            'nativeBytes' => 357,
        ],
        'center-title-placeholder' => [
            'name' => 'generated centered title placeholder parity',
            'pptx' => 'pptx-reader/center-title-placeholder.pptx',
            'native' => 'pptx-reader/center-title-placeholder.native',
            'pairKey' => 'pptx-reader/center-title-placeholder.pptx|pptx-reader/center-title-placeholder.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/center-title-placeholder.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/center-title-placeholder.native',
            'pptxSha256' => 'e22c661df3cdb54049aed387ad518fd9ecbaa69f28adb176c08dfcac456c3c7b',
            'nativeSha256' => '9589ff6f42a0238f3446f02e7e97e9e52f8b2e3817597d91f4e2ec3788fb1356',
            'pptxBytes' => 1503,
            'nativeBytes' => 114,
        ],
        'cdata-entity-text' => [
            'name' => 'generated CDATA and entity text parity',
            'pptx' => 'pptx-reader/cdata-entity-text.pptx',
            'native' => 'pptx-reader/cdata-entity-text.native',
            'pairKey' => 'pptx-reader/cdata-entity-text.pptx|pptx-reader/cdata-entity-text.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/cdata-entity-text.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/cdata-entity-text.native',
            'pptxSha256' => '369898eb68f295a8e448a0a170a9cecbd39ffb2faf22d70d0d75748c4f7d2d35',
            'nativeSha256' => 'ebffd044d94f5761cde8e231b149d3fe2eb0c686c5474ff4737c9b060eb7ad46',
            'pptxBytes' => 1663,
            'nativeBytes' => 129,
        ],
        'chart-placeholder' => [
            'name' => 'generated chart graphic placeholder parity',
            'pptx' => 'pptx-reader/chart-placeholder.pptx',
            'native' => 'pptx-reader/chart-placeholder.native',
            'pairKey' => 'pptx-reader/chart-placeholder.pptx|pptx-reader/chart-placeholder.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/chart-placeholder.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/chart-placeholder.native',
            'pptxSha256' => '43da4b9bc501e22c665706a9cf93597e445ee123e06ea1edaed49858e862f2ed',
            'nativeSha256' => 'c583540a28768d66ecd7aca44a211ae5ebff6cdec77eeb38b19ac10e5ad11f27',
            'pptxBytes' => 1659,
            'nativeBytes' => 180,
        ],
        'chart-embedded-workbook' => [
            'name' => 'generated chart embedded workbook provenance parity',
            'pptx' => 'pptx-reader/chart-embedded-workbook.pptx',
            'native' => 'pptx-reader/chart-embedded-workbook.native',
            'pairKey' => 'pptx-reader/chart-embedded-workbook.pptx|pptx-reader/chart-embedded-workbook.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/chart-embedded-workbook.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/chart-embedded-workbook.native',
            'pptxSha256' => '29f93cbe2fe5c2021a391acd9ae60e1a7afd68e281e6fa0304c379f527382142',
            'nativeSha256' => '2693cb5ba98115bdb045788a746ee26339d6253034772feaa9beef190fc7ebf9',
            'pptxBytes' => 3021,
            'nativeBytes' => 250,
        ],
        'comments-ignored' => [
            'name' => 'generated comments ignored parity',
            'pptx' => 'pptx-reader/comments-ignored.pptx',
            'native' => 'pptx-reader/comments-ignored.native',
            'pairKey' => 'pptx-reader/comments-ignored.pptx|pptx-reader/comments-ignored.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/comments-ignored.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/comments-ignored.native',
            'pptxSha256' => 'c4677dabb5ef3ac8765c1b818ca007f85cfa16b36a47e3b409bba90fe5c5485a',
            'nativeSha256' => '0adde5d0b2b9a90a0ce7864730f945f448d9d4f204c54db62de3de2294879d2a',
            'pptxBytes' => 2368,
            'nativeBytes' => 122,
        ],
        'content-part-skip' => [
            'name' => 'generated contentPart skip parity',
            'pptx' => 'pptx-reader/content-part-skip.pptx',
            'native' => 'pptx-reader/content-part-skip.native',
            'pairKey' => 'pptx-reader/content-part-skip.pptx|pptx-reader/content-part-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/content-part-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/content-part-skip.native',
            'pptxSha256' => '61244c0cca6dff5a64caa8318b8e81755b2853c221bf57d8cfafb9475deb2b0b',
            'nativeSha256' => '9e223d1d5dad199772749979c4331208ea6ee428b373d213f02c62ad108989f7',
            'pptxBytes' => 1817,
            'nativeBytes' => 125,
        ],
        'diagram-missing-rels' => [
            'name' => 'generated diagram relIds missing relationship placeholder parity',
            'pptx' => 'pptx-reader/diagram-missing-rels.pptx',
            'native' => 'pptx-reader/diagram-missing-rels.native',
            'pairKey' => 'pptx-reader/diagram-missing-rels.pptx|pptx-reader/diagram-missing-rels.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/diagram-missing-rels.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/diagram-missing-rels.native',
            'pptxSha256' => 'f0c37fc30ddd29f7b35d55002005acdf1ae98be1c32112113d95de3ea54e370b',
            'nativeSha256' => 'ddf13978208bba40d57db2aceaed9ae3b49bfcf92c11c514719fe520a9066b18',
            'pptxBytes' => 1461,
            'nativeBytes' => 141,
        ],
        'diagram-no-relids' => [
            'name' => 'generated diagram graphic without relIds placeholder parity',
            'pptx' => 'pptx-reader/diagram-no-relids.pptx',
            'native' => 'pptx-reader/diagram-no-relids.native',
            'pairKey' => 'pptx-reader/diagram-no-relids.pptx|pptx-reader/diagram-no-relids.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/diagram-no-relids.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/diagram-no-relids.native',
            'pptxSha256' => 'd34b13655f60496d827b983c436780deaad410cb870446086608920292bdbed0',
            'nativeSha256' => '1e30c1c1df173905b38cd1526f9ae1a95b0f7a63253dee072207ac0925419354',
            'pptxBytes' => 1418,
            'nativeBytes' => 122,
        ],
        'direct-drawing-paragraphs' => [
            'name' => 'generated direct drawing paragraph boundary parity',
            'pptx' => 'pptx-reader/direct-drawing-paragraphs.pptx',
            'native' => 'pptx-reader/direct-drawing-paragraphs.native',
            'pairKey' => 'pptx-reader/direct-drawing-paragraphs.pptx|pptx-reader/direct-drawing-paragraphs.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/direct-drawing-paragraphs.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/direct-drawing-paragraphs.native',
            'pptxSha256' => '98867e5898bd31ff7f82498fe13d464b237b784ce149c6591c489c00b9bc0979',
            'nativeSha256' => 'cc7a90f26bdb968391ccaecfd3172a4666636d309f3024f67948022e88460b22',
            'pptxBytes' => 1405,
            'nativeBytes' => 124,
        ],
        'document-properties' => [
            'name' => 'generated document property sidecar metadata parity',
            'pptx' => 'pptx-reader/document-properties.pptx',
            'native' => 'pptx-reader/document-properties.native',
            'pairKey' => 'pptx-reader/document-properties.pptx|pptx-reader/document-properties.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/document-properties.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/document-properties.native',
            'pptxSha256' => 'd059bf3fe2086ca7012e76a47a8cdd44c0e0235a6786444fe6ca628f25fba23c',
            'nativeSha256' => '8be0433cdacbaa8af79c12f3eb61f95d789ee60ccae8e2c26e39a35dddbd3648',
            'pptxBytes' => 3187,
            'nativeBytes' => 126,
        ],
        'dot-presentation-target' => [
            'name' => 'generated dot-segment presentation target parity',
            'pptx' => 'pptx-reader/dot-presentation-target.pptx',
            'native' => 'pptx-reader/dot-presentation-target.native',
            'pairKey' => 'pptx-reader/dot-presentation-target.pptx|pptx-reader/dot-presentation-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/dot-presentation-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/dot-presentation-target.native',
            'pptxSha256' => '9783b8a44828e294087a1de24045e4ad9e268479b57f5850f0b4d3c82ef9a5ae',
            'nativeSha256' => 'd86a49808a54e15a27287e0fb9fbcd4838f55b68eca8d3c0ca68547667f7462f',
            'pptxBytes' => 1310,
            'nativeBytes' => 90,
        ],
        'dot-slide-target' => [
            'name' => 'generated dot-segment slide target parity',
            'pptx' => 'pptx-reader/dot-slide-target.pptx',
            'native' => 'pptx-reader/dot-slide-target.native',
            'pairKey' => 'pptx-reader/dot-slide-target.pptx|pptx-reader/dot-slide-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/dot-slide-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/dot-slide-target.native',
            'pptxSha256' => '89b8ceba7657e20909a0406ac371c75b81929b72555b4a698e77ed0bcf944373',
            'nativeSha256' => '835dfba7de0cdcc016d24d7eba54ff6eee05d0434da2154bd49f51fe25a66bb4',
            'pptxBytes' => 1280,
            'nativeBytes' => 101,
        ],
        'duplicate-relationship-id' => [
            'name' => 'generated duplicate relationship id first-target parity',
            'pptx' => 'pptx-reader/duplicate-relationship-id.pptx',
            'native' => 'pptx-reader/duplicate-relationship-id.native',
            'pairKey' => 'pptx-reader/duplicate-relationship-id.pptx|pptx-reader/duplicate-relationship-id.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/duplicate-relationship-id.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/duplicate-relationship-id.native',
            'pptxSha256' => 'da3cc0a2e97ec681bdea132762fe438ac20beee5c4eda19c89e721d82888ab55',
            'nativeSha256' => 'c782680dd8586098eacc123dcbd3c608f621ae3e83853c8c1e7bb58f9f8781f8',
            'pptxBytes' => 1923,
            'nativeBytes' => 115,
        ],
        'duplicate-slide-reference' => [
            'name' => 'generated duplicate slide reference parity',
            'pptx' => 'pptx-reader/duplicate-slide-reference.pptx',
            'native' => 'pptx-reader/duplicate-slide-reference.native',
            'pairKey' => 'pptx-reader/duplicate-slide-reference.pptx|pptx-reader/duplicate-slide-reference.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/duplicate-slide-reference.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/duplicate-slide-reference.native',
            'pptxSha256' => 'f598167fa0995e6069126c2a00f9ed92c7732df26210b9952c8c0a54022d30c6',
            'nativeSha256' => 'a13be46a0ba2e04ae56ee4da86015c4f30401c654f942f574c2d3516a0eb2a3d',
            'pptxBytes' => 1895,
            'nativeBytes' => 176,
        ],
        'embed-and-link-image' => [
            'name' => 'generated embed-over-link image relationship parity',
            'pptx' => 'pptx-reader/embed-and-link-image.pptx',
            'native' => 'pptx-reader/embed-and-link-image.native',
            'pairKey' => 'pptx-reader/embed-and-link-image.pptx|pptx-reader/embed-and-link-image.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/embed-and-link-image.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/embed-and-link-image.native',
            'pptxSha256' => '0675b7e479fbd55a76fb798357dbe2266e509023be3a7a2d2d34dff8ddf7322b',
            'nativeSha256' => '2088c8c09db8bad7bbc09ddcabc8d54500491b2ac13bb75970c5e6ed0969c507',
            'pptxBytes' => 2607,
            'nativeBytes' => 218,
        ],
        'connector-skip' => [
            'name' => 'generated connector shape skip parity',
            'pptx' => 'pptx-reader/connector-skip.pptx',
            'native' => 'pptx-reader/connector-skip.native',
            'pairKey' => 'pptx-reader/connector-skip.pptx|pptx-reader/connector-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/connector-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/connector-skip.native',
            'pptxSha256' => 'ea84954b53c9ff9b53419df4828b32e191261c8e00375f20bd03ea160326a25b',
            'nativeSha256' => 'df89712378d3c5d4994094744ecd4e20f482e0231acd053619ebf92eff5b1254',
            'pptxBytes' => 1493,
            'nativeBytes' => 139,
        ],
        'connector-text-skip' => [
            'name' => 'generated connector text skip parity',
            'pptx' => 'pptx-reader/connector-text-skip.pptx',
            'native' => 'pptx-reader/connector-text-skip.native',
            'pairKey' => 'pptx-reader/connector-text-skip.pptx|pptx-reader/connector-text-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/connector-text-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/connector-text-skip.native',
            'pptxSha256' => '4112630a090c011c06adb2f1607b6d240416ae5fc4c2be8c7f93bd3356de0015',
            'nativeSha256' => '4c0a2dc57adbbe7429f6fcb6ed27c412c6166a2dbe98fa725b4c11739f69aca7',
            'pptxBytes' => 1470,
            'nativeBytes' => 112,
        ],
        'embedded-image' => [
            'name' => 'generated embedded image native parity',
            'pptx' => 'pptx-reader/embedded-image.pptx',
            'native' => 'pptx-reader/embedded-image.native',
            'pairKey' => 'pptx-reader/embedded-image.pptx|pptx-reader/embedded-image.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/embedded-image.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/embedded-image.native',
            'pptxSha256' => 'de45bd6af2dcf74e29dd7d961e5459c3a5d2b420992b1bbf280b10ee6df7256a',
            'nativeSha256' => '1aea7cedcb9155ee19a55db0d2825b1427dab1f51bbb460d140cd637e2bec266',
            'pptxBytes' => 2363,
            'nativeBytes' => 195,
        ],
        'empty-bullet-paragraph' => [
            'name' => 'generated empty bullet paragraph parity',
            'pptx' => 'pptx-reader/empty-bullet-paragraph.pptx',
            'native' => 'pptx-reader/empty-bullet-paragraph.native',
            'pairKey' => 'pptx-reader/empty-bullet-paragraph.pptx|pptx-reader/empty-bullet-paragraph.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-bullet-paragraph.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-bullet-paragraph.native',
            'pptxSha256' => 'e7660917ee56111797224aa96d1a70783169c574117eaf9dbd36299c4efbfaff',
            'nativeSha256' => 'a7420eaafce9765543a82c54d9b0ecdc185ff5557ad60ee77ec3cd6cfc154e10',
            'pptxBytes' => 1526,
            'nativeBytes' => 162,
        ],
        'empty-header-table' => [
            'name' => 'generated empty header table parity',
            'pptx' => 'pptx-reader/empty-header-table.pptx',
            'native' => 'pptx-reader/empty-header-table.native',
            'pairKey' => 'pptx-reader/empty-header-table.pptx|pptx-reader/empty-header-table.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-header-table.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-header-table.native',
            'pptxSha256' => 'adfa227750b01446fb7423b75ebed5ec49d5e8b47b56aee6d2cee3af95e355ad',
            'nativeSha256' => '313eec07897c789b4bdc2835abc54bae10f67ce37a1795c7c37babb7ac898dae',
            'pptxBytes' => 1441,
            'nativeBytes' => 740,
        ],
        'generated-table' => [
            'name' => 'generated table extraction parity',
            'pptx' => 'pptx-reader/generated-table.pptx',
            'native' => 'pptx-reader/generated-table.native',
            'pairKey' => 'pptx-reader/generated-table.pptx|pptx-reader/generated-table.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/generated-table.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/generated-table.native',
            'pptxSha256' => '85fec7638ef6f82c43cd805e9064146c4602cf5e7384ccdfa60a55048ec67b78',
            'nativeSha256' => '17b1efbb9d7b21ddf994fffd6c9d34110c48668ab144fd5b027d40034ec2e832',
            'pptxBytes' => 1702,
            'nativeBytes' => 1192,
        ],
        'graphic-no-uri' => [
            'name' => 'generated graphicData without URI placeholder parity',
            'pptx' => 'pptx-reader/graphic-no-uri.pptx',
            'native' => 'pptx-reader/graphic-no-uri.native',
            'pairKey' => 'pptx-reader/graphic-no-uri.pptx|pptx-reader/graphic-no-uri.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/graphic-no-uri.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/graphic-no-uri.native',
            'pptxSha256' => '83f08ec41905374579aa7d1f3a4298fd839b99fef5fb1f971d170a804bc18a94',
            'nativeSha256' => '3f88fef1c759398017753ef141d6fb81c0c6e2fe3b93d2042f94027faeac72e4',
            'pptxBytes' => 1606,
            'nativeBytes' => 106,
        ],
        'table-span-review' => [
            'name' => 'generated table span review-only parity',
            'pptx' => 'pptx-reader/table-span-review.pptx',
            'native' => 'pptx-reader/table-span-review.native',
            'pairKey' => 'pptx-reader/table-span-review.pptx|pptx-reader/table-span-review.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/table-span-review.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/table-span-review.native',
            'pptxSha256' => '6d39a50f3215706922877dd2148afb0e55208a7600d2ebb48e60830d7d160b0c',
            'nativeSha256' => '8df034dad767bbd20cc5f1f9fb875eecf84b8636dc74100677433cda03b304ce',
            'pptxBytes' => 1739,
            'nativeBytes' => 1598,
        ],
        'table-styles-relationship' => [
            'name' => 'generated table styles relationship parity',
            'pptx' => 'pptx-reader/table-styles-relationship.pptx',
            'native' => 'pptx-reader/table-styles-relationship.native',
            'pairKey' => 'pptx-reader/table-styles-relationship.pptx|pptx-reader/table-styles-relationship.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/table-styles-relationship.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/table-styles-relationship.native',
            'pptxSha256' => '5031c2ca5d8ea2bcd7ae08cd904655d151312a9bb83646c126e643a4acc4f3bc',
            'nativeSha256' => 'a799a5d732ca0a11d94f239d782b61bffe81325c09ed721705fabc2fe079feba',
            'pptxBytes' => 2155,
            'nativeBytes' => 1228,
        ],
        'text-comment-boundary' => [
            'name' => 'generated XML comment text-node boundary parity',
            'pptx' => 'pptx-reader/text-comment-boundary.pptx',
            'native' => 'pptx-reader/text-comment-boundary.native',
            'pairKey' => 'pptx-reader/text-comment-boundary.pptx|pptx-reader/text-comment-boundary.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/text-comment-boundary.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/text-comment-boundary.native',
            'pptxSha256' => '6f0b2e376015d61f06e8320c27eeba91a96b364d766e9dac0b75af66326d7e7c',
            'nativeSha256' => '088750aa75a347feec838c6d3f7bed51842fe70846b64d322b339a5335d5b8e9',
            'pptxBytes' => 1536,
            'nativeBytes' => 105,
        ],
        'textbox-without-nonvisual-properties' => [
            'name' => 'generated text box without nonvisual properties parity',
            'pptx' => 'pptx-reader/textbox-without-nonvisual-properties.pptx',
            'native' => 'pptx-reader/textbox-without-nonvisual-properties.native',
            'pairKey' => 'pptx-reader/textbox-without-nonvisual-properties.pptx|pptx-reader/textbox-without-nonvisual-properties.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/textbox-without-nonvisual-properties.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/textbox-without-nonvisual-properties.native',
            'pptxSha256' => 'c52b2158d87f686fe7f8772ed2981046e565b1248116f4790b6ac8e137db8dbf',
            'nativeSha256' => '4166c3c576b0b9c56b1660166572f88a2d24130db590bb76b6cc211f8201e743',
            'pptxBytes' => 1315,
            'nativeBytes' => 110,
        ],
        'grouped-shape-media-review' => [
            'name' => 'generated grouped shape media review parity',
            'pptx' => 'pptx-reader/grouped-shape-media-review.pptx',
            'native' => 'pptx-reader/grouped-shape-media-review.native',
            'pairKey' => 'pptx-reader/grouped-shape-media-review.pptx|pptx-reader/grouped-shape-media-review.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/grouped-shape-media-review.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/grouped-shape-media-review.native',
            'pptxSha256' => '8125b15cd633f7e88428893df558f6abf7a927b1ba2a13bf36a97497be789de0',
            'nativeSha256' => 'd782ab60b3a2c37fbcaa60cd2a658d2d9457bf4674d1e4d920be5ee7daa75301',
            'pptxBytes' => 2289,
            'nativeBytes' => 119,
        ],
        'grouped-shapes' => [
            'name' => 'generated grouped shape skip parity',
            'pptx' => 'pptx-reader/grouped-shapes.pptx',
            'native' => 'pptx-reader/grouped-shapes.native',
            'pairKey' => 'pptx-reader/grouped-shapes.pptx|pptx-reader/grouped-shapes.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/grouped-shapes.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/grouped-shapes.native',
            'pptxSha256' => '906420300b4dd404e516ea84b72afa1ae74ea5ed729097e1cbaa6e1226fb2d09',
            'nativeSha256' => '4e1caa42c42964a8ca9dab0dfb092ad4303009f46c3b406d491307e951447176',
            'pptxBytes' => 1975,
            'nativeBytes' => 61,
        ],
        'header-only-table' => [
            'name' => 'generated header-only table parity',
            'pptx' => 'pptx-reader/header-only-table.pptx',
            'native' => 'pptx-reader/header-only-table.native',
            'pairKey' => 'pptx-reader/header-only-table.pptx|pptx-reader/header-only-table.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/header-only-table.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/header-only-table.native',
            'pptxSha256' => '0f6885aa81b40ece6baf0594b96671288e2e34443955e8d05faef3a70c84c27b',
            'nativeSha256' => '24386bb8660fccadb33699ccc98bebee91dec39a667bc0e9f0b56ab5a16c32ab',
            'pptxBytes' => 1747,
            'nativeBytes' => 757,
        ],
        'hex-list-level' => [
            'name' => 'generated hexadecimal list level parity',
            'pptx' => 'pptx-reader/hex-list-level.pptx',
            'native' => 'pptx-reader/hex-list-level.native',
            'pairKey' => 'pptx-reader/hex-list-level.pptx|pptx-reader/hex-list-level.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hex-list-level.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hex-list-level.native',
            'pptxSha256' => '32f695d75454c0fb94694cd02f655c248419b27158eff788fd06f910d91190bf',
            'nativeSha256' => '9a880e7716e4fb9301d13de65664811376cfbb7fdbc7e78772432187be00fd64',
            'pptxBytes' => 1548,
            'nativeBytes' => 161,
        ],
        'signed-bullet-level' => [
            'name' => 'generated signed bullet level parity',
            'pptx' => 'pptx-reader/signed-bullet-level.pptx',
            'native' => 'pptx-reader/signed-bullet-level.native',
            'pairKey' => 'pptx-reader/signed-bullet-level.pptx|pptx-reader/signed-bullet-level.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/signed-bullet-level.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/signed-bullet-level.native',
            'pptxSha256' => '96eabf5aee2a41ac7f18672924541ea658a775931d88c9eb81d4807b3cac8152',
            'nativeSha256' => 'e683a48f7c2966aec3033ea3b0e8e28beb48de02fee0c2fa09651cc55f25cdaf',
            'pptxBytes' => 1420,
            'nativeBytes' => 236,
        ],
        'hidden-slide' => [
            'name' => 'generated hidden slide inclusion parity',
            'pptx' => 'pptx-reader/hidden-slide.pptx',
            'native' => 'pptx-reader/hidden-slide.native',
            'pairKey' => 'pptx-reader/hidden-slide.pptx|pptx-reader/hidden-slide.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hidden-slide.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hidden-slide.native',
            'pptxSha256' => '01627fa5f56ca583f3604306984cc1df4b69a15339396b061e44604265cb802f',
            'nativeSha256' => 'a543e3ed60ca4d5f187fba970ed855d5f064a911e3ee3224b07929481c62b515',
            'pptxBytes' => 1893,
            'nativeBytes' => 178,
        ],
        'hidden-shape-metadata' => [
            'name' => 'generated hidden shape metadata parity',
            'pptx' => 'pptx-reader/hidden-shape-metadata.pptx',
            'native' => 'pptx-reader/hidden-shape-metadata.native',
            'pairKey' => 'pptx-reader/hidden-shape-metadata.pptx|pptx-reader/hidden-shape-metadata.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hidden-shape-metadata.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hidden-shape-metadata.native',
            'pptxSha256' => '8ef23fb882dd6f0acd914e1da20fcf14295f8bbd413ddc5fb41da6e4d7e8caea',
            'nativeSha256' => '430227468460a2c9d03fa45b39efdb0ea659e49ad44d9b7a374128688a0f2f4c',
            'pptxBytes' => 1937,
            'nativeBytes' => 304,
        ],
        'ignored-slide-id-attributes' => [
            'name' => 'generated ignored presentation slide id attributes parity',
            'pptx' => 'pptx-reader/ignored-slide-id-attributes.pptx',
            'native' => 'pptx-reader/ignored-slide-id-attributes.native',
            'pairKey' => 'pptx-reader/ignored-slide-id-attributes.pptx|pptx-reader/ignored-slide-id-attributes.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/ignored-slide-id-attributes.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/ignored-slide-id-attributes.native',
            'pptxSha256' => '089bf291993684e3a30d1dcd4caa047475bf38ca13b0b730fd97bebaf56092b6',
            'nativeSha256' => '2be6bf3f3e934918925565417fb8e3fb200eb6ba904ebe16dbbf4ebb2e018372',
            'pptxBytes' => 1944,
            'nativeBytes' => 150,
        ],
        'hyperlink-text' => [
            'name' => 'generated text hyperlink invisibility parity',
            'pptx' => 'pptx-reader/hyperlink-text.pptx',
            'native' => 'pptx-reader/hyperlink-text.native',
            'pairKey' => 'pptx-reader/hyperlink-text.pptx|pptx-reader/hyperlink-text.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hyperlink-text.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hyperlink-text.native',
            'pptxSha256' => '22180e777f4a145bd3aff34f6fd5c2a846ce5567d758a78565b5dfc6addca6e3',
            'nativeSha256' => 'f4334af63e88a238caf0dcb2a4bf37fa1745d54bb2d703ec287fb3cc0474bcd7',
            'pptxBytes' => 2004,
            'nativeBytes' => 100,
        ],
        'inline-formatting' => [
            'name' => 'generated inline run formatting flattening parity',
            'pptx' => 'pptx-reader/inline-formatting.pptx',
            'native' => 'pptx-reader/inline-formatting.native',
            'pairKey' => 'pptx-reader/inline-formatting.pptx|pptx-reader/inline-formatting.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/inline-formatting.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/inline-formatting.native',
            'pptxSha256' => 'ab06c31070771529002bc03bb08bb53dd7212374d1596ef0af278226237a793a',
            'nativeSha256' => '1a3e45263240e1ac99eff8a222867e11db0de7a3ff53a9972769c41fd30518de',
            'pptxBytes' => 27659,
            'nativeBytes' => 142,
        ],
        'list-continuation' => [
            'name' => 'generated buNone list-continuation boundary parity',
            'pptx' => 'pptx-reader/list-continuation.pptx',
            'native' => 'pptx-reader/list-continuation.native',
            'pairKey' => 'pptx-reader/list-continuation.pptx|pptx-reader/list-continuation.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/list-continuation.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/list-continuation.native',
            'pptxSha256' => '2b7ae7359fde4edb717371d518ef80c8bbda374fa72def88c3dcd744c91fdf5f',
            'nativeSha256' => 'd5dd188d56624d8aa5a8a848a40d2e4568e3f522f034573dc8b539842ae702de',
            'pptxBytes' => 1713,
            'nativeBytes' => 294,
        ],
        'linked-image-skip' => [
            'name' => 'generated linked image skip parity',
            'pptx' => 'pptx-reader/linked-image-skip.pptx',
            'native' => 'pptx-reader/linked-image-skip.native',
            'pairKey' => 'pptx-reader/linked-image-skip.pptx|pptx-reader/linked-image-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/linked-image-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/linked-image-skip.native',
            'pptxSha256' => '59da55af98fd7bd06f69b0effb9283e8404e3496e3ff4b5f01bff02c0b1d7f05',
            'nativeSha256' => 'd170e15f31fa6600cd7fa3eb9560e48ebcc5caaff8ce207d43c48c9fe2b49317',
            'pptxBytes' => 2240,
            'nativeBytes' => 118,
        ],
        'literal-image-targets' => [
            'name' => 'generated literal image target skip parity',
            'pptx' => 'pptx-reader/literal-image-targets.pptx',
            'native' => 'pptx-reader/literal-image-targets.native',
            'pairKey' => 'pptx-reader/literal-image-targets.pptx|pptx-reader/literal-image-targets.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/literal-image-targets.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/literal-image-targets.native',
            'pptxSha256' => 'ff432a5190dfade93799e6ded4985fab28230a1ccccf7f3f272fbd9d326b2b24',
            'nativeSha256' => '709139da441ee326f9627fed2515a80ffffd0e231c3a16e5bce6a8864b5d5d3b',
            'pptxBytes' => 2088,
            'nativeBytes' => 73,
        ],
        'picture-bullet-plain' => [
            'name' => 'generated picture bullet marker stays plain parity',
            'pptx' => 'pptx-reader/picture-bullet-plain.pptx',
            'native' => 'pptx-reader/picture-bullet-plain.native',
            'pairKey' => 'pptx-reader/picture-bullet-plain.pptx|pptx-reader/picture-bullet-plain.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/picture-bullet-plain.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/picture-bullet-plain.native',
            'pptxSha256' => 'bdead04f7e3e5f820e45f3a377dc8e8532e14d6a206690cfe3359472825d645d',
            'nativeSha256' => '6364a606bfa373aae9fd8ac687ea9bb0859a0bd718cd3149af16a0d76a5daf2f',
            'pptxBytes' => 2177,
            'nativeBytes' => 159,
        ],
        'picture-shape-hyperlink' => [
            'name' => 'generated picture shape hyperlink ignore parity',
            'pptx' => 'pptx-reader/picture-shape-hyperlink.pptx',
            'native' => 'pptx-reader/picture-shape-hyperlink.native',
            'pairKey' => 'pptx-reader/picture-shape-hyperlink.pptx|pptx-reader/picture-shape-hyperlink.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/picture-shape-hyperlink.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/picture-shape-hyperlink.native',
            'pptxSha256' => '9d4c19437a20700472715756f14926783f6a1fa9f2379bb5187f3fd19391f07a',
            'nativeSha256' => '4fa780ef19805afc3e13c3fee77b4bff99c03468ef2569099aa92e7eef6d3aa5',
            'pptxBytes' => 2491,
            'nativeBytes' => 261,
        ],
        'media-relative-image-target' => [
            'name' => 'generated media-relative image target parity',
            'pptx' => 'pptx-reader/media-relative-image-target.pptx',
            'native' => 'pptx-reader/media-relative-image-target.native',
            'pairKey' => 'pptx-reader/media-relative-image-target.pptx|pptx-reader/media-relative-image-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/media-relative-image-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/media-relative-image-target.native',
            'pptxSha256' => '6058c05a26c250acd076cad8174a2f98b8ceeae3b09ae24f68fd13d0d0f499ee',
            'nativeSha256' => 'e626f61a8eb6163e5022a15814de316ae461a73389af1d4694ee87d81a4211ce',
            'pptxBytes' => 3621,
            'nativeBytes' => 236,
        ],
        'transition-animation-metadata' => [
            'name' => 'generated transition and animation metadata parity',
            'pptx' => 'pptx-reader/transition-animation-metadata.pptx',
            'native' => 'pptx-reader/transition-animation-metadata.native',
            'pairKey' => 'pptx-reader/transition-animation-metadata.pptx|pptx-reader/transition-animation-metadata.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/transition-animation-metadata.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/transition-animation-metadata.native',
            'pptxSha256' => '1705b03613a6a74ccf504d111378482bbe748f1dcfd4412f2a0a6a5bdee36245',
            'nativeSha256' => 'e3ffd5e45e2cd8d69c94af6bedfeeb6f5e96e5056aa33999de0033fd00ba5e64',
            'pptxBytes' => 1994,
            'nativeBytes' => 128,
        ],
        'transition-sound-media' => [
            'name' => 'generated transition sound media review parity',
            'pptx' => 'pptx-reader/transition-sound-media.pptx',
            'native' => 'pptx-reader/transition-sound-media.native',
            'pairKey' => 'pptx-reader/transition-sound-media.pptx|pptx-reader/transition-sound-media.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/transition-sound-media.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/transition-sound-media.native',
            'pptxSha256' => '2975b41855f4c46d214aee4586851a861012370d934fe2b8edc5366b40f70fb1',
            'nativeSha256' => 'c2b9096c98cb64a9497b4284f156e75dea1547d26a860d1b40570380c3798fcb',
            'pptxBytes' => 1883,
            'nativeBytes' => 114,
        ],
        'two-slides' => [
            'name' => 'generated two-slide ordering parity',
            'pptx' => 'pptx-reader/two-slides.pptx',
            'native' => 'pptx-reader/two-slides.native',
            'pairKey' => 'pptx-reader/two-slides.pptx|pptx-reader/two-slides.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/two-slides.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/two-slides.native',
            'pptxSha256' => '58e37ebe22ba5f7e5b9f7c3fe886ae5ff085876371178e63cc115a8f6d4e052c',
            'nativeSha256' => '269e2c8b638af9834b52a0ff23c795578f9b21404e27c60d846cf81b3520596a',
            'pptxBytes' => 1897,
            'nativeBytes' => 177,
        ],
        'unicode-drawing-text' => [
            'name' => 'pandoc 3.10 Unicode drawing text parity',
            'pptx' => 'pptx-reader/unicode-drawing-text.pptx',
            'native' => 'pptx-reader/unicode-drawing-text.native',
            'pairKey' => 'pptx-reader/unicode-drawing-text.pptx|pptx-reader/unicode-drawing-text.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/unicode-drawing-text.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/unicode-drawing-text.native',
            'pptxSha256' => '6bae0a4e7a6ccf8a08a04bb6bfab89f7912b35ef2f2ee0074b886f2383911136',
            'nativeSha256' => 'd0309729b6886e5c7c8b72813360c1e1a0a88ccc9f6ce2364e8a2a991441c252',
            'pptxBytes' => 1496,
            'nativeBytes' => 127,
        ],
        'unknown-graphic-uri' => [
            'name' => 'generated unknown graphicData URI placeholder parity',
            'pptx' => 'pptx-reader/unknown-graphic-uri.pptx',
            'native' => 'pptx-reader/unknown-graphic-uri.native',
            'pairKey' => 'pptx-reader/unknown-graphic-uri.pptx|pptx-reader/unknown-graphic-uri.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/unknown-graphic-uri.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/unknown-graphic-uri.native',
            'pptxSha256' => '23e41aa0f6462f4c59aa42a3072ac6d76418571e234eeecdf8bce1bb4379e525',
            'nativeSha256' => '3b55757f406ad3bfc31c5928c6e978536cef7bf81e575cf9308ae092172b6c28',
            'pptxBytes' => 1642,
            'nativeBytes' => 143,
        ],
        'unqualified-relationship-attributes' => [
            'name' => 'generated unqualified relationship attribute parity',
            'pptx' => 'pptx-reader/unqualified-relationship-attributes.pptx',
            'native' => 'pptx-reader/unqualified-relationship-attributes.native',
            'pairKey' => 'pptx-reader/unqualified-relationship-attributes.pptx|pptx-reader/unqualified-relationship-attributes.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/unqualified-relationship-attributes.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/unqualified-relationship-attributes.native',
            'pptxSha256' => '4cbabcf0bac36cd9c06d688b82173633062c89121296ecceff0b5ecbd2206449',
            'nativeSha256' => '1d8281f7e359f99a495b4f9ccb2012dd67309f8fe5df83c6e22a52227c9d79c6',
            'pptxBytes' => 2099,
            'nativeBytes' => 302,
        ],
        'speaker-notes' => [
            'name' => 'generated speaker notes visibility parity',
            'pptx' => 'pptx-reader/speaker-notes.pptx',
            'native' => 'pptx-reader/speaker-notes.native',
            'pairKey' => 'pptx-reader/speaker-notes.pptx|pptx-reader/speaker-notes.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/speaker-notes.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/speaker-notes.native',
            'pptxSha256' => '52d0a82f3a84c594a9be816307c90b918cb914802bd3622a4cf9e2c06f40ddc5',
            'nativeSha256' => '24f10e8e2632d64f9afb7a3aac8b0e48570d8ef61d76f6f0a51f841d104142f1',
            'pptxBytes' => 2511,
            'nativeBytes' => 95,
        ],
        'numbered-list' => [
            'name' => 'generated auto-numbered paragraph boundary parity',
            'pptx' => 'pptx-reader/numbered-list.pptx',
            'native' => 'pptx-reader/numbered-list.native',
            'pairKey' => 'pptx-reader/numbered-list.pptx|pptx-reader/numbered-list.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/numbered-list.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/numbered-list.native',
            'pptxSha256' => 'ba1162b8a31aba2b9cc01b1d346a070d66a0f8666afa44e0ace72bfdd76f1d4b',
            'nativeSha256' => 'be9e2f1c3a9f5815ea6cc86debe2ff081a4666931dd2e48c32245cd3de40cd9f',
            'pptxBytes' => 1520,
            'nativeBytes' => 118,
        ],
        'octal-list-level' => [
            'name' => 'generated octal list level parity',
            'pptx' => 'pptx-reader/octal-list-level.pptx',
            'native' => 'pptx-reader/octal-list-level.native',
            'pairKey' => 'pptx-reader/octal-list-level.pptx|pptx-reader/octal-list-level.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/octal-list-level.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/octal-list-level.native',
            'pptxSha256' => 'daa5a906fd87b0b35323e93f108fe1b72a5b3f958006876e8944020f872da88f',
            'nativeSha256' => 'd9e8c07df9c64b726e687ee75f9e1ff3d6eae528d2df3e853f7f454022a26573',
            'pptxBytes' => 1423,
            'nativeBytes' => 249,
        ],
        'overflow-bullet-level' => [
            'name' => 'generated Haskell Int overflow bullet level parity',
            'pptx' => 'pptx-reader/overflow-bullet-level.pptx',
            'native' => 'pptx-reader/overflow-bullet-level.native',
            'pairKey' => 'pptx-reader/overflow-bullet-level.pptx|pptx-reader/overflow-bullet-level.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/overflow-bullet-level.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/overflow-bullet-level.native',
            'pptxSha256' => 'c6ccda57f94b450e0aa26ead7887c11edafe4d4efdd183bda2b7fbcbbe469e65',
            'nativeSha256' => '93a7f80f530d6db03d4f9fcac88e2d3a0758ed6e8afa360d90eee0e722937563',
            'pptxBytes' => 1622,
            'nativeBytes' => 378,
        ],
        'parenthesized-bullet-level' => [
            'name' => 'generated parenthesized bullet level parity',
            'pptx' => 'pptx-reader/parenthesized-bullet-level.pptx',
            'native' => 'pptx-reader/parenthesized-bullet-level.native',
            'pairKey' => 'pptx-reader/parenthesized-bullet-level.pptx|pptx-reader/parenthesized-bullet-level.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/parenthesized-bullet-level.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/parenthesized-bullet-level.native',
            'pptxSha256' => '7378843edb65e1063a902bbb646e5602a4fa2ed3fb8632dfddc585ccab202931',
            'nativeSha256' => '6f7909e6f75fa02bb9941a58789b9b27d83740ff78cb3b6ad0d54ddf082af32e',
            'pptxBytes' => 1430,
            'nativeBytes' => 276,
        ],
        'pandoc-generated-image-alt-title' => [
            'name' => 'pandoc 3.10 generated image title and alt parity',
            'pptx' => 'pptx-reader/pandoc-generated-image-alt-title.pptx',
            'native' => 'pptx-reader/pandoc-generated-image-alt-title.native',
            'pairKey' => 'pptx-reader/pandoc-generated-image-alt-title.pptx|pptx-reader/pandoc-generated-image-alt-title.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/pandoc-generated-image-alt-title.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/pandoc-generated-image-alt-title.native',
            'pptxSha256' => '8603ee4876a9d3e5dcc713e283fd256b507555e37b5e29bc4eb24e51077df3a6',
            'nativeSha256' => 'e268004d2c0de80415609e20914e4c949b418cf01b860b58cdb02649badf1136',
            'pptxBytes' => 28067,
            'nativeBytes' => 233,
        ],
        'shape-order' => [
            'name' => 'generated plain text shape ordering parity',
            'pptx' => 'pptx-reader/shape-order.pptx',
            'native' => 'pptx-reader/shape-order.native',
            'pairKey' => 'pptx-reader/shape-order.pptx|pptx-reader/shape-order.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/shape-order.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/shape-order.native',
            'pptxSha256' => '3f92fd142900b957b23cfe2b1afb01d2785d23b77ae62c23429d6bd11fd3c02f',
            'nativeSha256' => '911f29fe22d020d181e007478bff7c157f6df49d06f7c42798bb3a933d33f427',
            'pptxBytes' => 1521,
            'nativeBytes' => 135,
        ],
        'slide-layout-placeholder-no-inherit' => [
            'name' => 'generated slide layout empty placeholder no-inherit parity',
            'pptx' => 'pptx-reader/slide-layout-placeholder-no-inherit.pptx',
            'native' => 'pptx-reader/slide-layout-placeholder-no-inherit.native',
            'pairKey' => 'pptx-reader/slide-layout-placeholder-no-inherit.pptx|pptx-reader/slide-layout-placeholder-no-inherit.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/slide-layout-placeholder-no-inherit.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/slide-layout-placeholder-no-inherit.native',
            'pptxSha256' => '16a18e8709ed45075cf556bc8f78527edf86e7c15c76058d6557270defeb64c5',
            'nativeSha256' => '5fe1085d4fe7bacf348cc26ee690429dc4f783d3f2fdb9202d63a8e9a27176b3',
            'pptxBytes' => 2279,
            'nativeBytes' => 129,
        ],
        'slide-placeholders' => [
            'name' => 'generated slide footer/date/number placeholder visibility parity',
            'pptx' => 'pptx-reader/slide-placeholders.pptx',
            'native' => 'pptx-reader/slide-placeholders.native',
            'pairKey' => 'pptx-reader/slide-placeholders.pptx|pptx-reader/slide-placeholders.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/slide-placeholders.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/slide-placeholders.native',
            'pptxSha256' => 'c8e3aebc55d7e464bb43409263586042420acaac2ce308601dadd081ab17354b',
            'nativeSha256' => 'f76963e6f7aa7b051bddb6ad4fa62016af8f580963f75fee42ecb840c7a64cc6',
            'pptxBytes' => 1598,
            'nativeBytes' => 203,
        ],
        'smartart-hierarchy' => [
            'name' => 'generated SmartArt hierarchy native parity',
            'pptx' => 'pptx-reader/smartart-hierarchy.pptx',
            'native' => 'pptx-reader/smartart-hierarchy.native',
            'pairKey' => 'pptx-reader/smartart-hierarchy.pptx|pptx-reader/smartart-hierarchy.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/smartart-hierarchy.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/smartart-hierarchy.native',
            'pptxSha256' => '186195196185f1c5b95a0e7e2c327dc551371edbd09de4d2f94e418ff10420eb',
            'nativeSha256' => 'bc41c663b7f2711c8d12039d385926db19bae387c07290cd7629e5ab278e2ce9',
            'pptxBytes' => 2664,
            'nativeBytes' => 332,
        ],
        'smartart-title-fallback' => [
            'name' => 'generated SmartArt layout title fallback parity',
            'pptx' => 'pptx-reader/smartart-title-fallback.pptx',
            'native' => 'pptx-reader/smartart-title-fallback.native',
            'pairKey' => 'pptx-reader/smartart-title-fallback.pptx|pptx-reader/smartart-title-fallback.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/smartart-title-fallback.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/smartart-title-fallback.native',
            'pptxSha256' => 'bbc1325cd9324ccd14898628b55589da7ddc4fb7079a071599069e842d985046',
            'nativeSha256' => '46bf18c4facc20fee3f231d19516c94521883988bdc0f0c07a158d477bf51396',
            'pptxBytes' => 2587,
            'nativeBytes' => 296,
        ],
        'percent-encoded-target' => [
            'name' => 'generated literal percent-encoded relationship target parity',
            'pptx' => 'pptx-reader/percent-encoded-target.pptx',
            'native' => 'pptx-reader/percent-encoded-target.native',
            'pairKey' => 'pptx-reader/percent-encoded-target.pptx|pptx-reader/percent-encoded-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/percent-encoded-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/percent-encoded-target.native',
            'pptxSha256' => 'c43d087016af3aca9afd325e3c630c072e8629722a610bfcba248b18c37eddc3',
            'nativeSha256' => '9ceb6189090309ad8b3ea4ec49622cbf6f64d110928046136578c33c8fc48242',
            'pptxBytes' => 2506,
            'nativeBytes' => 117,
        ],
        'presentation-sections-custom-shows' => [
            'name' => 'generated presentation sections and custom shows metadata-only parity',
            'pptx' => 'pptx-reader/presentation-sections-custom-shows.pptx',
            'native' => 'pptx-reader/presentation-sections-custom-shows.native',
            'pairKey' => 'pptx-reader/presentation-sections-custom-shows.pptx|pptx-reader/presentation-sections-custom-shows.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/presentation-sections-custom-shows.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/presentation-sections-custom-shows.native',
            'pptxSha256' => '7a8b620a39e18d4ddb992dd507ff476739e2dfa413ef117e01c134f1f5173341',
            'nativeSha256' => '3655e3cac035a830ee26f85ded16ef3f8e62bb9dd078b4978402638c7393feb3',
            'pptxBytes' => 2917,
            'nativeBytes' => 284,
        ],
        'qualified-bullet-level' => [
            'name' => 'generated qualified bullet level fallback parity',
            'pptx' => 'pptx-reader/qualified-bullet-level.pptx',
            'native' => 'pptx-reader/qualified-bullet-level.native',
            'pairKey' => 'pptx-reader/qualified-bullet-level.pptx|pptx-reader/qualified-bullet-level.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/qualified-bullet-level.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/qualified-bullet-level.native',
            'pptxSha256' => '975698555e2a3766273d24f0c8b11510f1856b9fae77d3fb53c4d70f01abf55b',
            'nativeSha256' => 'c80440348f5567bb5cdd29313dc97aaf86b339b0c44211c873350f22cc49177b',
            'pptxBytes' => 1431,
            'nativeBytes' => 259,
        ],
        'qualified-picture-metadata' => [
            'name' => 'generated qualified picture metadata attribute parity',
            'pptx' => 'pptx-reader/qualified-picture-metadata.pptx',
            'native' => 'pptx-reader/qualified-picture-metadata.native',
            'pairKey' => 'pptx-reader/qualified-picture-metadata.pptx|pptx-reader/qualified-picture-metadata.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/qualified-picture-metadata.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/qualified-picture-metadata.native',
            'pptxSha256' => '701d31f75d1d665bf1ba38cd2ac14963a97193c41b4296ced7bd96e556136229',
            'nativeSha256' => '19bd88efcd70aee87a078b7070ac1909f9c8ba2cb6b05b81e63bc9fcaccc179c',
            'pptxBytes' => 1936,
            'nativeBytes' => 207,
        ],
        'query-fragment-presentation-target' => [
            'name' => 'generated literal query/fragment presentation target parity',
            'pptx' => 'pptx-reader/query-fragment-presentation-target.pptx',
            'native' => 'pptx-reader/query-fragment-presentation-target.native',
            'pairKey' => 'pptx-reader/query-fragment-presentation-target.pptx|pptx-reader/query-fragment-presentation-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/query-fragment-presentation-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/query-fragment-presentation-target.native',
            'pptxSha256' => 'ab5d9267694ea958a26c5f18262481400b7b4b5ab5ae821cb8ce05e1f1494eae',
            'nativeSha256' => '17bc3c608870f832f4e2f72c1da296d9895562e415ec1ee0ab593ffa222897b3',
            'pptxBytes' => 2540,
            'nativeBytes' => 94,
        ],
        'ragged-body-row-table' => [
            'name' => 'generated ragged body row table parity',
            'pptx' => 'pptx-reader/ragged-body-row-table.pptx',
            'native' => 'pptx-reader/ragged-body-row-table.native',
            'pairKey' => 'pptx-reader/ragged-body-row-table.pptx|pptx-reader/ragged-body-row-table.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/ragged-body-row-table.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/ragged-body-row-table.native',
            'pptxSha256' => '1e88d253ef7c8cc42179427fb2cb5097f74fa0cda88bdfba44caaf3d244c6583',
            'nativeSha256' => '3b3d5b70017c80bf39d4cc6d3f77139c2694216030fa481c8d6710d6b4807991',
            'pptxBytes' => 1475,
            'nativeBytes' => 1636,
        ],
        'rel-prefix-image-skip' => [
            'name' => 'generated noncanonical relationship prefix image skip parity',
            'pptx' => 'pptx-reader/rel-prefix-image-skip.pptx',
            'native' => 'pptx-reader/rel-prefix-image-skip.native',
            'pairKey' => 'pptx-reader/rel-prefix-image-skip.pptx|pptx-reader/rel-prefix-image-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/rel-prefix-image-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/rel-prefix-image-skip.native',
            'pptxSha256' => '45a26c62512c0e943dc3ef8c007cc94af758ad1e9ca13a2d63ec08ac338fd05f',
            'nativeSha256' => '346a3a5c5484f21810c5745c41db13a68de5c20a87fe3332bc7da5688fa6ea6b',
            'pptxBytes' => 2380,
            'nativeBytes' => 90,
        ],
        'relationship-root-alias' => [
            'name' => 'generated relationship root alias parity',
            'pptx' => 'pptx-reader/relationship-root-alias.pptx',
            'native' => 'pptx-reader/relationship-root-alias.native',
            'pairKey' => 'pptx-reader/relationship-root-alias.pptx|pptx-reader/relationship-root-alias.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/relationship-root-alias.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/relationship-root-alias.native',
            'pptxSha256' => 'b5e36f9e43595a72fa9c07a5ddcfaf1761263336313da07d698bbb1656052d8a',
            'nativeSha256' => '2b98f648ad9ddf52271c4d333091a599b5d43a9a28396b64952677f658f26641',
            'pptxBytes' => 2182,
            'nativeBytes' => 249,
        ],
        'repeated-slash-slide-target' => [
            'name' => 'generated repeated-slash slide target parity',
            'pptx' => 'pptx-reader/repeated-slash-slide-target.pptx',
            'native' => 'pptx-reader/repeated-slash-slide-target.native',
            'pairKey' => 'pptx-reader/repeated-slash-slide-target.pptx|pptx-reader/repeated-slash-slide-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/repeated-slash-slide-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/repeated-slash-slide-target.native',
            'pptxSha256' => '8592b2e23603c128d9a70849acbd17a50058c405c8699b60f4b01a83ef471300',
            'nativeSha256' => '8174d5156e67751932faec8de101c6e66bd7e7339183cc0bd0248a6137b06e05',
            'pptxBytes' => 1310,
            'nativeBytes' => 92,
        ],
        'repeated-slash-presentation-target' => [
            'name' => 'generated repeated-slash presentation target parity',
            'pptx' => 'pptx-reader/repeated-slash-presentation-target.pptx',
            'native' => 'pptx-reader/repeated-slash-presentation-target.native',
            'pairKey' => 'pptx-reader/repeated-slash-presentation-target.pptx|pptx-reader/repeated-slash-presentation-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/repeated-slash-presentation-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/repeated-slash-presentation-target.native',
            'pptxSha256' => '41899644c0c987c2ec8e96aa25b9ec8160476d1de1d02042c37721d150786f7c',
            'nativeSha256' => '8f5ecb6310e128bbb0c8ec8203c5639722be03864ddcf5ef3e6f9c4f474ce331',
            'pptxBytes' => 1453,
            'nativeBytes' => 94,
        ],
        'rich-media-skip' => [
            'name' => 'generated rich media placeholder skip parity',
            'pptx' => 'pptx-reader/rich-media-skip.pptx',
            'native' => 'pptx-reader/rich-media-skip.native',
            'pairKey' => 'pptx-reader/rich-media-skip.pptx|pptx-reader/rich-media-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/rich-media-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/rich-media-skip.native',
            'pptxSha256' => '2d6d32f08c2c694292d220184cecbfd116e9260e9534720f8f313c56516b1226',
            'nativeSha256' => 'dde7cc213ac82ae4f03a1c97dfaf72650bcafb5c9d5ce06497bf60ea8ceb688a',
            'pptxBytes' => 2633,
            'nativeBytes' => 122,
        ],
        'rooted-slide-target' => [
            'name' => 'generated rooted slide relationship target parity',
            'pptx' => 'pptx-reader/rooted-slide-target.pptx',
            'native' => 'pptx-reader/rooted-slide-target.native',
            'pairKey' => 'pptx-reader/rooted-slide-target.pptx|pptx-reader/rooted-slide-target.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/rooted-slide-target.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/rooted-slide-target.native',
            'pptxSha256' => 'f7ae4f4e696bee21ecbfc967fa96e70d9c67dbf5035ad1fa05e5f5974f6bd433',
            'nativeSha256' => '6059cb62d9ff8d71c8d9719a067256089eb239683e2486b6661f576748b2061b',
            'pptxBytes' => 1529,
            'nativeBytes' => 129,
        ],
        'subtitle-placeholder' => [
            'name' => 'generated subtitle placeholder body parity',
            'pptx' => 'pptx-reader/subtitle-placeholder.pptx',
            'native' => 'pptx-reader/subtitle-placeholder.native',
            'pairKey' => 'pptx-reader/subtitle-placeholder.pptx|pptx-reader/subtitle-placeholder.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/subtitle-placeholder.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/subtitle-placeholder.native',
            'pptxSha256' => '3f15d4e0767367861baf040c1853b22900e821d32980e555cf5e3c10d41be5ea',
            'nativeSha256' => 'a217ec6f6e2a1cee8844f7c7230ae57ed5fc13bca6269aaeab2086db2799ee5f',
            'pptxBytes' => 1505,
            'nativeBytes' => 129,
        ],
        'wrong-typed-slide-relationship' => [
            'name' => 'generated slide relationship Type ignored parity',
            'pptx' => 'pptx-reader/wrong-typed-slide-relationship.pptx',
            'native' => 'pptx-reader/wrong-typed-slide-relationship.native',
            'pairKey' => 'pptx-reader/wrong-typed-slide-relationship.pptx|pptx-reader/wrong-typed-slide-relationship.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/wrong-typed-slide-relationship.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/wrong-typed-slide-relationship.native',
            'pptxSha256' => 'f781a8b009b67786a1df88e500dfd5111ce8848ee72a9eb9d20ba944172e2d70',
            'nativeSha256' => '85a93bce0aa4d85219e859b3abdbaf78cf1e0c1884a6a59934b0e39e2790baf5',
            'pptxBytes' => 1449,
            'nativeBytes' => 121,
        ],
    ];

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;
    private readonly ?string $runnerResultArtifact;

    public function __construct(
        string $repoRoot,
        string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT,
        ?string $runnerResultArtifact = null
    ) {
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
                'staticCurrentEvidence' => $this->staticCurrentEvidence(),
                'runnerEvidence' => $this->runnerEvidence($denominator, false),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $readerTestPath = $root . '/test/Tests/Readers/Pptx.hs';
        $fixtureDirectory = $root . '/test/pptx-reader';
        $readerCases = is_file($readerTestPath)
            ? $this->parseReaderCases((string) file_get_contents($readerTestPath))
            : [];
        $fixturePairs = $this->fixturePairs($fixtureDirectory);
        $unpairedFixtures = $this->unpairedFixtureFiles($fixtureDirectory);
        $observedCommit = $this->gitHead($root);
        $validationIssues = $this->validationIssues($root, $observedCommit, $readerCases, $fixturePairs, $unpairedFixtures);
        $denominator = [
            'readerTestCompareCount' => count($readerCases),
            'fixturePairCount' => count($fixturePairs),
            'referencedPairCount' => count($readerCases),
            'readerCases' => $readerCases,
            'fixturePairs' => array_values($fixturePairs),
            'unpairedPptxFixtureCount' => count($unpairedFixtures['pptx']),
            'unpairedNativeFixtureCount' => count($unpairedFixtures['native']),
            'unpairedPptxFixtures' => $unpairedFixtures['pptx'],
            'unpairedNativeFixtures' => $unpairedFixtures['native'],
            'missingReferencedFiles' => $this->missingReferencedFiles($root, $readerCases),
            'unreferencedFixturePairs' => $this->unreferencedFixturePairs($readerCases, $fixturePairs),
        ];

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $observedCommit,
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/Pptx.hs',
                'fixtureDirectory' => 'test/pptx-reader',
            ],
            'denominator' => $denominator,
            'sourceInventory' => $this->sourceInventory($root),
            'staticCurrentEvidence' => $this->staticCurrentEvidence(),
            'runnerEvidence' => $this->runnerEvidence($denominator, $validationIssues === []),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-pptx-reader-denominator' : 'invalid-upstream-pptx-reader-denominator',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
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
        $staticDenominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];
        $staticNativeParity = is_array($staticEvidence['nativeAstMappedParity'] ?? null) ? $staticEvidence['nativeAstMappedParity'] : [];
        $staticExecutableParity = is_array($staticEvidence['executableNativeAstMappedParity'] ?? null) ? $staticEvidence['executableNativeAstMappedParity'] : [];
        $staticReviewMetadata = is_array($staticEvidence['checkedInReviewMetadata'] ?? null) ? $staticEvidence['checkedInReviewMetadata'] : [];
        $staticReviewMetadataValidation = is_array($staticReviewMetadata['validation'] ?? null) ? $staticReviewMetadata['validation'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $runnerResultLine = self::hasRunnerResultArtifactEvidence($report)
            ? 'Supplied upstream Haskell/Cabal runner result artifact is validated; PPTX writer parity is not asserted.'
            : 'No upstream Haskell/Cabal runner result artifact is supplied; runtime runner execution is not asserted.';

        return implode(PHP_EOL, [
            'Pandoc PPTX reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'Reader test comparisons: ' . (int) ($denominator['readerTestCompareCount'] ?? 0),
            'Fixture pairs: ' . (int) ($denominator['fixturePairCount'] ?? 0)
                . ' unpairedPptx=' . (int) ($denominator['unpairedPptxFixtureCount'] ?? 0)
                . ' unpairedNative=' . (int) ($denominator['unpairedNativeFixtureCount'] ?? 0),
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' comparisons=' . (int) ($staticDenominator['expectedCompareCount'] ?? 0)
                . ' checkedInPairs=' . (int) ($staticEvidence['checkedInFixturePairCount'] ?? 0)
                . ' checkedInUnpairedPptx=' . (int) ($staticEvidence['checkedInUnpairedPptxFixtureCount'] ?? 0)
                . ' checkedInUnpairedNative=' . (int) ($staticEvidence['checkedInUnpairedNativeFixtureCount'] ?? 0),
            'Static native AST mapped parity: ' . (string) ($staticNativeParity['astParityStatus'] ?? 'unknown')
                . ' matches=' . (int) ($staticNativeParity['normalizedAstMatchCount'] ?? 0)
                . ' mismatches=' . (int) ($staticNativeParity['normalizedAstMismatchCount'] ?? 0)
                . ' required=' . (int) ($staticNativeParity['requiredPairCount'] ?? self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT),
            'Static executable native AST parity: ' . (string) ($staticExecutableParity['astParityStatus'] ?? 'unknown')
                . ' matches=' . (int) ($staticExecutableParity['normalizedAstMatchCount'] ?? 0)
                . ' mismatches=' . (int) ($staticExecutableParity['normalizedAstMismatchCount'] ?? 0)
                . ' required=' . (int) ($staticExecutableParity['requiredPptxCount'] ?? self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT),
            'Static checked-in review metadata: ' . (string) ($staticReviewMetadataValidation['status'] ?? 'unknown')
                . ' chartFixtures=' . (int) ($staticReviewMetadata['chartReviewFixtureCount'] ?? 0)
                . ' charts=' . (int) ($staticReviewMetadata['chartReviewCount'] ?? 0)
                . ' noteFixtures=' . (int) ($staticReviewMetadata['speakerNotesFixtureCount'] ?? 0)
                . ' notes=' . (int) ($staticReviewMetadata['speakerNoteCount'] ?? 0)
                . ' commentFixtures=' . (int) ($staticReviewMetadata['commentFixtureCount'] ?? 0)
                . ' comments=' . (int) ($staticReviewMetadata['commentCount'] ?? 0),
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
    public static function hasRequiredReaderTestCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['readerTestCompareCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixturePairCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['fixturePairCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCheckedInReaderTestCount(array $report, int $requiredCount): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $denominator = is_array($evidence['readerDenominator'] ?? null) ? $evidence['readerDenominator'] : [];

        return (int) ($denominator['expectedCompareCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCheckedInFixturePairCount(array $report, int $requiredCount): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];

        return (int) ($evidence['checkedInFixturePairCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-pptx-reader-denominator'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $denominator = is_array($evidence['readerDenominator'] ?? null) ? $evidence['readerDenominator'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-pptx-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($denominator['expectedCompareCount'] ?? -1) === self::EXPECTED_STATIC_READER_TEST_COMPARE_COUNT
            && (int) ($evidence['checkedInFixturePairCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($evidence['checkedInUnpairedPptxFixtureCount'] ?? -1) === 0
            && (int) ($evidence['checkedInUnpairedNativeFixtureCount'] ?? -1) === 0
            && self::hasRequiredStaticNativeMappedParity($report)
            && self::hasRequiredStaticReviewMetadata($report)
            && self::hasRequiredStaticExecutableNativeAstParity($report);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticNativeMappedParity(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $parity = is_array($evidence['nativeAstMappedParity'] ?? null) ? $evidence['nativeAstMappedParity'] : [];

        return ($parity['hasRequiredMappedParity'] ?? null) === true
            && (int) ($parity['requiredPairCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['totalPairCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['comparedPairCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (!array_key_exists('pptxParsedCount', $parity) || (int) $parity['pptxParsedCount'] === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT)
            && (!array_key_exists('nativeParsedCount', $parity) || (int) $parity['nativeParsedCount'] === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT)
            && (int) ($parity['bothParsedCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (!array_key_exists('unpairedPptxCount', $parity) || (int) $parity['unpairedPptxCount'] === 0)
            && (!array_key_exists('unpairedNativeCount', $parity) || (int) $parity['unpairedNativeCount'] === 0)
            && (!array_key_exists('unpairedPptxFixtures', $parity) || $parity['unpairedPptxFixtures'] === [])
            && (!array_key_exists('unpairedNativeFixtures', $parity) || $parity['unpairedNativeFixtures'] === [])
            && (int) ($parity['parseFailureCount'] ?? -1) === 0
            && (int) ($parity['normalizedAstMatchCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['normalizedAstMismatchCount'] ?? -1) === 0
            && ($parity['astParityStatus'] ?? null) === 'normalized-ast-implementation-equivalence-observed';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticReviewMetadata(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $review = is_array($evidence['checkedInReviewMetadata'] ?? null) ? $evidence['checkedInReviewMetadata'] : [];
        $validation = is_array($review['validation'] ?? null) ? $review['validation'] : [];
        $fixtures = is_array($review['fixtures'] ?? null) ? $review['fixtures'] : [];
        $fixturesByStem = [];
        foreach ($fixtures as $fixture) {
            if (!is_array($fixture) || !is_string($fixture['stem'] ?? null)) {
                continue;
            }
            $fixturesByStem[$fixture['stem']] = $fixture;
        }

        $expectedReviewsByStem = self::expectedReviewMetadataByStem();
        foreach ($expectedReviewsByStem as $stem => $expectedReview) {
            $fixture = is_array($fixturesByStem[$stem] ?? null) ? $fixturesByStem[$stem] : [];
            if ((int) ($fixture['chartCount'] ?? -1) !== count($expectedReview['charts'])) {
                return false;
            }
            if (($fixture['charts'] ?? null) !== $expectedReview['charts']) {
                return false;
            }
            if ((int) ($fixture['speakerNoteCount'] ?? -1) !== count($expectedReview['speakerNotes'])) {
                return false;
            }
            if (($fixture['speakerNotes'] ?? null) !== $expectedReview['speakerNotes']) {
                return false;
            }
            if ((int) ($fixture['commentAuthorCount'] ?? -1) !== count($expectedReview['commentAuthors'])) {
                return false;
            }
            if (($fixture['commentAuthors'] ?? null) !== $expectedReview['commentAuthors']) {
                return false;
            }
            if ((int) ($fixture['commentCount'] ?? -1) !== count($expectedReview['comments'])) {
                return false;
            }
            if (($fixture['comments'] ?? null) !== $expectedReview['comments']) {
                return false;
            }
        }

        return ($validation['status'] ?? null) === 'valid-checked-in-current-pptx-review-metadata'
            && ($validation['issues'] ?? null) === []
            && (int) ($review['fixtureCount'] ?? -1) === count($expectedReviewsByStem)
            && (int) ($review['chartReviewFixtureCount'] ?? -1) === count(array_filter($expectedReviewsByStem, static fn (array $expected): bool => $expected['charts'] !== []))
            && (int) ($review['chartReviewCount'] ?? -1) === array_sum(array_map(static fn (array $expected): int => count($expected['charts']), $expectedReviewsByStem))
            && (int) ($review['speakerNotesFixtureCount'] ?? -1) === count(array_filter($expectedReviewsByStem, static fn (array $expected): bool => $expected['speakerNotes'] !== []))
            && (int) ($review['speakerNoteCount'] ?? -1) === array_sum(array_map(static fn (array $expected): int => count($expected['speakerNotes']), $expectedReviewsByStem))
            && (int) ($review['commentFixtureCount'] ?? -1) === count(array_filter($expectedReviewsByStem, static fn (array $expected): bool => $expected['comments'] !== []))
            && (int) ($review['commentCount'] ?? -1) === array_sum(array_map(static fn (array $expected): int => count($expected['comments']), $expectedReviewsByStem))
            && (int) ($review['commentAuthorCount'] ?? -1) === array_sum(array_map(static fn (array $expected): int => count($expected['commentAuthors']), $expectedReviewsByStem));
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticExecutableNativeAstParity(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $parity = is_array($evidence['executableNativeAstMappedParity'] ?? null) ? $evidence['executableNativeAstMappedParity'] : [];
        $validation = is_array($parity['validation'] ?? null) ? $parity['validation'] : [];
        $snapshotFile = is_array($parity['snapshotFile'] ?? null) ? $parity['snapshotFile'] : [];

        return ($parity['hasRequiredExecutableParity'] ?? null) === true
            && ($parity['hasRequiredPandocVersion'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-pptx-executable-native-ast-parity'
            && ($validation['issues'] ?? null) === []
            && ($snapshotFile['present'] ?? null) === true
            && ($snapshotFile['sha256'] ?? null) === self::CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_SHA256
            && (int) ($snapshotFile['bytes'] ?? -1) === self::CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_BYTES
            && (int) ($parity['requiredPptxCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['totalPptxCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['comparedPptxCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['localParsedCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['pandocParsedCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['nativeFixtureParsedCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['bothParsedCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['parseFailureCount'] ?? -1) === 0
            && (int) ($parity['normalizedAstMatchCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['normalizedAstMismatchCount'] ?? -1) === 0
            && (int) ($parity['pandocNativeFixtureComparedCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['pandocNativeFixtureMatchCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            && (int) ($parity['pandocNativeFixtureMismatchCount'] ?? -1) === 0
            && ($parity['requiredPandocVersion'] ?? null) === 'pandoc 3.10'
            && ($parity['pandocVersion'] ?? null) === 'pandoc 3.10'
            && ($parity['astParityStatus'] ?? null) === 'normalized-ast-equality-observed-against-pandoc-executable';
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
            && ($binding['readerTestModule'] ?? null) === 'test/Tests/Readers/Pptx.hs'
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
            && ($runner['runner'] ?? null) === 'Cabal/Tasty Pandoc PPTX reader suite'
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
            && ($validation['status'] ?? null) === 'valid-upstream-pptx-reader-runner-result-artifact'
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

    private static function claim(): string
    {
        return 'Parses the pinned upstream Tests.Readers.Pptx test module and test/pptx-reader fixture directory to establish the current PPTX reader golden-test denominator.';
    }

    /**
     * @return list<string>
     */
    private static function checkedInStaticFixturePairNames(): array
    {
        return array_map(
            static fn (string $stem): string => $stem . '.pptx/' . $stem . '.native',
            array_keys(self::CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT)
        );
    }

    /**
     * @return list<string>
     */
    private static function generatedStaticFixturePairNames(): array
    {
        return array_values(array_filter(
            self::checkedInStaticFixturePairNames(),
            static fn (string $pair): bool => $pair !== 'basic.pptx/basic.native'
        ));
    }

    /**
     * @param list<string> $pairs
     */
    private static function fixturePairNameList(array $pairs): string
    {
        $count = count($pairs);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $pairs[0];
        }

        return implode(', ', array_slice($pairs, 0, -1)) . ', and ' . $pairs[$count - 1];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and file paths of upstream PPTX reader golden comparisons in Tests.Readers.Pptx',
                'that every referenced PPTX/native fixture file exists in the pinned sparse upstream checkout',
                'that root-level test/pptx-reader PPTX/native fixture pairs and unpaired files are accounted for',
                'static checked-in current upstream basic.pptx/basic.native plus generated ' . self::fixturePairNameList(self::generatedStaticFixturePairNames()) . ' fixture identities when staticCurrentEvidence is valid',
                'that local PHP PPTX reader output matches all checked-in current PPTX/native pairs by normalized AST shape when staticCurrentEvidence is valid',
                'that upstream Haskell runner evidence is explicitly not-run',
                'the future upstream runner command plan targets test:test-pandoc Readers/Pptx at the pinned upstream commit without execution',
                'a supplied upstream runner result artifact is validated against the pinned PPTX Tasty target, commit, test names, pass/fail counts, and transcript file identities when explicitly provided',
            ],
            'doesNotAssert' => [
                'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                'PPTX writer parity',
                'byte-for-byte identity with Pandoc Haskell reader internals',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runnerEvidence(array $denominator, bool $denominatorIsValid): array
    {
        if ($this->runnerResultArtifact === null) {
            return self::runnerNotRunEvidence();
        }

        return $this->runnerResultArtifactEvidence($denominator, $denominatorIsValid);
    }

    /**
     * @param array<string, mixed> $denominator
     * @return array<string, mixed>
     */
    private function runnerResultArtifactEvidence(array $denominator, bool $denominatorIsValid): array
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
            if (!$denominatorIsValid) {
                $issues[] = 'runner-result-denominator-invalid';
            }
            if ($expectedTestNames === []) {
                $issues[] = 'runner-result-denominator-empty';
            }
            if (($payload['schemaVersion'] ?? null) !== self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION) {
                $issues[] = 'runner-result-schema-version-mismatch';
            }
            if (($payload['runner'] ?? null) !== 'Cabal/Tasty Pandoc PPTX reader suite') {
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
            'runner' => 'Cabal/Tasty Pandoc PPTX reader suite',
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
                'readerTestModule' => 'test/Tests/Readers/Pptx.hs',
            ],
            'target' => [
                'testSuite' => is_string($target['testSuite'] ?? null) ? $target['testSuite'] : null,
                'tastyGroupPath' => is_array($target['tastyGroupPath'] ?? null) ? $target['tastyGroupPath'] : null,
                'tastyPattern' => is_string($target['tastyPattern'] ?? null) ? $target['tastyPattern'] : null,
            ],
            'expected' => [
                'schemaVersion' => self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION,
                'runner' => 'Cabal/Tasty Pandoc PPTX reader suite',
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
                    ? 'valid-upstream-pptx-reader-runner-result-artifact'
                    : 'invalid-upstream-pptx-reader-runner-result-artifact',
                'issues' => $issues,
            ],
            'claim' => $issues === []
                ? 'A supplied upstream PPTX reader runner result artifact matches the pinned targeted Tasty runner evidence contract.'
                : 'The supplied upstream PPTX reader runner result artifact did not satisfy the pinned targeted Tasty runner evidence contract.',
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
        $cases = is_array($denominator['readerCases'] ?? null) ? $denominator['readerCases'] : [];
        $names = [];
        foreach ($cases as $case) {
            if (is_array($case) && is_string($case['name'] ?? null)) {
                $names[] = $case['name'];
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
            'runner' => 'Cabal/Tasty Pandoc PPTX reader suite',
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
                'readerTestModule' => 'test/Tests/Readers/Pptx.hs',
            ],
            'target' => [
                'testSuite' => self::RUNNER_TEST_SUITE,
                'tastyGroupPath' => self::RUNNER_TASTY_GROUP_PATH,
                'tastyPattern' => self::RUNNER_TASTY_PATTERN,
            ],
            'blockers' => [
                'no committed upstream test:test-pandoc PPTX runner transcript or result artifact is present',
                'this PHP evidence gate intentionally does not invoke Cabal/Tasty or hydrate Haskell build dependencies',
                'a future runner claim must be bound to the pinned upstream commit and exact targeted PPTX Tasty pattern',
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This PHP evidence packet is generated without executing the upstream Haskell runner.',
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
                'purpose' => 'list targeted PPTX reader tests',
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
                'purpose' => 'run targeted PPTX reader tests',
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
     * @return array<string, mixed>
     */
    private function staticCurrentEvidence(): array
    {
        $fixtureDirectory = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $checkedInFixturePairs = array_values($this->fixturePairs($fixtureDirectory));
        $checkedInUnpairedFixtures = $this->unpairedFixtureFiles($fixtureDirectory);
        $checkedInPairKeys = [];
        foreach ($checkedInFixturePairs as $pair) {
            $checkedInPairKeys[(string) $pair['pairKey']] = true;
        }
        $nativeAstReport = (new PptxNativeAstComparisonHarness())->run($fixtureDirectory);
        $nativeAstMappedParity = self::nativeAstMappedParityEvidence($nativeAstReport);
        $executableNativeAstMappedParity = $this->executableNativeAstSnapshotEvidence();
        $checkedInReviewMetadata = $this->checkedInReviewMetadataEvidence($fixtureDirectory);

        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-fixture-directory';
        }

        if (count(self::STATIC_CURRENT_READER_CASES) !== self::EXPECTED_STATIC_READER_TEST_COMPARE_COUNT) {
            $issues[] = 'static-reader-test-count-does-not-match-expected-snapshot';
        }

        if (count($checkedInFixturePairs) !== self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT) {
            $issues[] = 'checked-in-fixture-pair-count-does-not-match-static-snapshot';
        }
        if ($checkedInUnpairedFixtures['pptx'] !== []) {
            $issues[] = 'checked-in-current-unpaired-pptx-fixtures';
        }
        if ($checkedInUnpairedFixtures['native'] !== []) {
            $issues[] = 'checked-in-current-unpaired-native-fixtures';
        }
        if (($nativeAstMappedParity['hasRequiredMappedParity'] ?? false) !== true) {
            $issues[] = 'checked-in-current-native-ast-mapped-parity-mismatch';
        }
        if (($executableNativeAstMappedParity['hasRequiredExecutableParity'] ?? false) !== true) {
            $issues[] = 'checked-in-current-executable-native-ast-parity-mismatch';
        }
        $executableValidation = is_array($executableNativeAstMappedParity['validation'] ?? null) ? $executableNativeAstMappedParity['validation'] : [];
        if (($executableValidation['issues'] ?? []) !== []) {
            $issues[] = 'checked-in-current-executable-native-ast-snapshot-invalid';
        }
        $reviewMetadataValidation = is_array($checkedInReviewMetadata['validation'] ?? null) ? $checkedInReviewMetadata['validation'] : [];
        if (($reviewMetadataValidation['issues'] ?? []) !== []) {
            $issues[] = 'checked-in-current-review-metadata-invalid';
        }

        $snapshotPairs = [];
        foreach (self::CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT as $stem => $snapshot) {
            $pptx = $this->snapshotFileEvidence(
                (string) $snapshot['pptxPath'],
                (string) $snapshot['pptxSha256'],
                (int) $snapshot['pptxBytes']
            );
            $native = $this->snapshotFileEvidence(
                (string) $snapshot['nativePath'],
                (string) $snapshot['nativeSha256'],
                (int) $snapshot['nativeBytes']
            );
            $pairKey = (string) $snapshot['pairKey'];
            $snapshotPairs[] = [
                'stem' => (string) $stem,
                'name' => (string) ($snapshot['name'] ?? self::STATIC_CURRENT_READER_CASES[0]['name']),
                'pptx' => (string) $snapshot['pptx'],
                'native' => (string) $snapshot['native'],
                'pairKey' => $pairKey,
                'checkedInPptx' => $pptx,
                'checkedInNative' => $native,
            ];

            if (($pptx['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-pptx-fixture';
            } elseif (($pptx['sha256'] ?? null) !== $snapshot['pptxSha256']) {
                $issues[] = 'checked-in-current-pptx-sha256-mismatch';
            } elseif ((int) ($pptx['bytes'] ?? -1) !== (int) $snapshot['pptxBytes']) {
                $issues[] = 'checked-in-current-pptx-byte-count-mismatch';
            }

            if (($native['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-native-fixture';
            } elseif (($native['sha256'] ?? null) !== $snapshot['nativeSha256']) {
                $issues[] = 'checked-in-current-native-sha256-mismatch';
            } elseif ((int) ($native['bytes'] ?? -1) !== (int) $snapshot['nativeBytes']) {
                $issues[] = 'checked-in-current-native-byte-count-mismatch';
            }

            if (!isset($checkedInPairKeys[$pairKey])) {
                $issues[] = 'checked-in-current-fixture-pair-key-mismatch';
            }
        }

        return [
            'kind' => 'static-checked-in-current-pptx-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/Pptx.hs',
                'fixtureDirectory' => 'test/pptx-reader',
            ],
            'readerDenominator' => [
                'expectedCompareCount' => count(self::STATIC_CURRENT_READER_CASES),
                'expectedReaderCases' => self::STATIC_CURRENT_READER_CASES,
            ],
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'checkedInFixturePairCount' => count($checkedInFixturePairs),
            'checkedInFixturePairs' => $snapshotPairs,
            'checkedInUnpairedPptxFixtureCount' => count($checkedInUnpairedFixtures['pptx']),
            'checkedInUnpairedNativeFixtureCount' => count($checkedInUnpairedFixtures['native']),
            'checkedInUnpairedPptxFixtures' => $checkedInUnpairedFixtures['pptx'],
            'checkedInUnpairedNativeFixtures' => $checkedInUnpairedFixtures['native'],
            'nativeAstMappedParity' => $nativeAstMappedParity,
            'executableNativeAstMappedParity' => $executableNativeAstMappedParity,
            'checkedInReviewMetadata' => $checkedInReviewMetadata,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-pptx-reader-evidence' : 'invalid-checked-in-current-pptx-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding the pinned Tests.Readers.Pptx one-case denominator to the checked-in current upstream basic.pptx/basic.native fixture pair, plus ' . count(self::generatedStaticFixturePairNames()) . ' generated PPTX/native pairs with local normalized-AST parity.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'Tests.Readers.Pptx at the pinned upstream commit has one golden comparison for pptx-reader/basic.pptx and pptx-reader/basic.native',
                    'the checked-in current PPTX fixture directory contains ' . count(self::checkedInStaticFixturePairNames()) . ' same-stem PPTX/native pairs and no unpaired PPTX/native files',
                    'the checked-in ' . self::fixturePairNameList(self::checkedInStaticFixturePairNames()) . ' files match the expected SHA-256 hashes and byte counts for this snapshot',
                    'local PHP PPTX reader output matches all ' . self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT . ' checked-in current PPTX/native pairs by normalized AST shape',
                    'checked-in executable native AST evidence shows pandoc 3.10, local PHP output, and paired .native fixtures match all ' . self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT . ' checked-in current PPTX fixtures by normalized AST shape',
                    'checked-in chart review metadata covers chart-placeholder.pptx and chart-embedded-workbook.pptx, including embedded workbook package relationships with hashed byte exposure',
                    'checked-in speaker note and comment review metadata covers speaker-notes.pptx and comments-ignored.pptx without rendering those records into native AST output',
                ],
                'doesNotAssert' => [
                    'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                    'that a fresh upstream checkout was inspected during this PHP gate',
                    ...array_map(
                        static fn (string $pair): string => 'that ' . $pair . ' is an upstream Tests.Readers.Pptx fixture',
                        self::generatedStaticFixturePairNames()
                    ),
                    'additional PPTX fixture discovery outside the checked-in ' . self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT . '-pair corpus',
                    'PPTX writer parity',
                    'byte-for-byte identity with Pandoc Haskell reader internals',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkedInReviewMetadataEvidence(string $fixtureDirectory): array
    {
        $issues = [];
        $fixtures = [];
        $chartReviewFixtureCount = 0;
        $chartReviewCount = 0;
        $speakerNotesFixtureCount = 0;
        $speakerNoteCount = 0;
        $commentFixtureCount = 0;
        $commentCount = 0;
        $commentAuthorCount = 0;

        foreach (self::expectedReviewMetadataByStem() as $stem => $expectedReview) {
            $snapshot = self::CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT[$stem] ?? null;
            if (!is_array($snapshot)) {
                $issues[] = 'missing-' . $stem . '-static-snapshot';
                continue;
            }
            $pptx = $this->snapshotFileEvidence(
                (string) $snapshot['pptxPath'],
                (string) $snapshot['pptxSha256'],
                (int) $snapshot['pptxBytes']
            );
            $charts = [];
            $speakerNotes = [];
            $commentAuthors = [];
            $comments = [];
            $path = $fixtureDirectory . DIRECTORY_SEPARATOR . $stem . '.pptx';
            if (($pptx['present'] ?? false) !== true || !is_file($path)) {
                $issues[] = 'missing-' . $stem . '-pptx-fixture';
            } else {
                try {
                    $document = (new PptxReader())->read((string) file_get_contents($path));
                    $review = $document->attr('pptx');
                    foreach (is_array($review['commentAuthors'] ?? null) ? $review['commentAuthors'] : [] as $author) {
                        if (is_array($author)) {
                            $commentAuthors[] = self::compactCommentAuthorReview($author);
                        }
                    }
                    $slides = is_array($review['slides'] ?? null) ? $review['slides'] : [];
                    foreach ($slides as $slide) {
                        if (!is_array($slide)) {
                            continue;
                        }
                        foreach (is_array($slide['charts'] ?? null) ? $slide['charts'] : [] as $chart) {
                            if (is_array($chart)) {
                                $charts[] = self::compactChartReview($chart);
                            }
                        }
                        foreach (is_array($slide['speakerNotes'] ?? null) ? $slide['speakerNotes'] : [] as $note) {
                            if (is_array($note)) {
                                $speakerNotes[] = self::compactSpeakerNoteReview($note);
                            }
                        }
                        foreach (is_array($slide['comments'] ?? null) ? $slide['comments'] : [] as $comment) {
                            if (is_array($comment)) {
                                $comments[] = self::compactCommentReview($comment);
                            }
                        }
                    }
                } catch (\Throwable) {
                    $issues[] = $stem . '-pptx-review-metadata-read-failed';
                }
            }

            $chartCount = count($charts);
            if ($chartCount > 0) {
                ++$chartReviewFixtureCount;
                $chartReviewCount += $chartCount;
            }
            $speakerNoteCountForFixture = count($speakerNotes);
            if ($speakerNoteCountForFixture > 0) {
                ++$speakerNotesFixtureCount;
                $speakerNoteCount += $speakerNoteCountForFixture;
            }
            $commentCountForFixture = count($comments);
            if ($commentCountForFixture > 0) {
                ++$commentFixtureCount;
                $commentCount += $commentCountForFixture;
            }
            $commentAuthorCount += count($commentAuthors);
            if (
                $charts !== $expectedReview['charts']
                || $speakerNotes !== $expectedReview['speakerNotes']
                || $commentAuthors !== $expectedReview['commentAuthors']
                || $comments !== $expectedReview['comments']
            ) {
                $issues[] = $stem . '-pptx-review-metadata-mismatch';
            }

            $fixtures[] = [
                'stem' => $stem,
                'pptx' => (string) $snapshot['pptx'],
                'checkedInPptx' => $pptx,
                'chartCount' => $chartCount,
                'charts' => $charts,
                'speakerNoteCount' => $speakerNoteCountForFixture,
                'speakerNotes' => $speakerNotes,
                'commentAuthorCount' => count($commentAuthors),
                'commentAuthors' => $commentAuthors,
                'commentCount' => $commentCountForFixture,
                'comments' => $comments,
            ];
        }

        return [
            'kind' => 'checked-in-current-pptx-review-metadata',
            'fixtureScope' => 'checked-in PPTX review metadata records that are intentionally excluded from native AST parity',
            'fixtureCount' => count($fixtures),
            'chartReviewFixtureCount' => $chartReviewFixtureCount,
            'chartReviewCount' => $chartReviewCount,
            'speakerNotesFixtureCount' => $speakerNotesFixtureCount,
            'speakerNoteCount' => $speakerNoteCount,
            'commentFixtureCount' => $commentFixtureCount,
            'commentAuthorCount' => $commentAuthorCount,
            'commentCount' => $commentCount,
            'fixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-pptx-review-metadata' : 'invalid-checked-in-current-pptx-review-metadata',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding checked-in PPTX review metadata that is not exposed in native writer output.',
        ];
    }

    /**
     * @param array<string, mixed> $chart
     * @return array<string, mixed>
     */
    private static function compactChartReview(array $chart): array
    {
        return [
            'graphicUri' => (string) ($chart['graphicUri'] ?? ''),
            'relationshipId' => (string) ($chart['relationshipId'] ?? ''),
            'relationshipType' => (string) ($chart['relationshipType'] ?? ''),
            'target' => (string) ($chart['target'] ?? ''),
            'partName' => (string) ($chart['partName'] ?? ''),
            'external' => (bool) ($chart['external'] ?? false),
            'title' => (string) ($chart['title'] ?? ''),
            'chartType' => (string) ($chart['chartType'] ?? ''),
            'series' => is_array($chart['series'] ?? null) ? $chart['series'] : [],
            'externalDataRelationshipIds' => self::stringList($chart['externalDataRelationshipIds'] ?? []),
            'externalDataRelationships' => self::compactChartExternalDataRelationships($chart['externalDataRelationships'] ?? []),
            'issues' => self::stringList($chart['issues'] ?? []),
            'byteExposurePolicy' => (string) ($chart['byteExposurePolicy'] ?? ''),
            'reviewPolicy' => (string) ($chart['reviewPolicy'] ?? ''),
        ];
    }

    /**
     * @param mixed $relationships
     * @return list<array<string, mixed>>
     */
    private static function compactChartExternalDataRelationships(mixed $relationships): array
    {
        if (!is_array($relationships)) {
            return [];
        }

        $compact = [];
        foreach ($relationships as $relationship) {
            if (!is_array($relationship)) {
                continue;
            }
            $compact[] = [
                'relationshipId' => (string) ($relationship['relationshipId'] ?? ''),
                'relationshipType' => (string) ($relationship['relationshipType'] ?? ''),
                'target' => (string) ($relationship['target'] ?? ''),
                'external' => (bool) ($relationship['external'] ?? false),
                'partName' => (string) ($relationship['partName'] ?? ''),
                'exists' => (bool) ($relationship['exists'] ?? false),
                'zipEntry' => (string) ($relationship['zipEntry'] ?? ''),
                'contentType' => (string) ($relationship['contentType'] ?? ''),
                'packageRelationshipRole' => (string) ($relationship['packageRelationshipRole'] ?? ''),
                'embeddedWorkbook' => (bool) ($relationship['embeddedWorkbook'] ?? false),
                'byteLength' => (int) ($relationship['byteLength'] ?? -1),
                'compressedByteLength' => (int) ($relationship['compressedByteLength'] ?? -1),
                'compressionMethod' => (int) ($relationship['compressionMethod'] ?? -1),
                'sha256' => (string) ($relationship['sha256'] ?? ''),
                'byteExposurePolicy' => (string) ($relationship['byteExposurePolicy'] ?? ''),
            ];
        }

        return $compact;
    }

    /**
     * @param array<string, mixed> $note
     * @return array<string, mixed>
     */
    private static function compactSpeakerNoteReview(array $note): array
    {
        return [
            'relationshipId' => (string) ($note['relationshipId'] ?? ''),
            'relationshipType' => (string) ($note['relationshipType'] ?? ''),
            'target' => (string) ($note['target'] ?? ''),
            'partName' => (string) ($note['partName'] ?? ''),
            'text' => (string) ($note['text'] ?? ''),
            'blockCount' => (int) ($note['blockCount'] ?? -1),
        ];
    }

    /**
     * @param array<string, mixed> $author
     * @return array<string, mixed>
     */
    private static function compactCommentAuthorReview(array $author): array
    {
        return [
            'name' => (string) ($author['name'] ?? ''),
            'initials' => (string) ($author['initials'] ?? ''),
            'lastIdx' => (string) ($author['lastIdx'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $comment
     * @return array<string, mixed>
     */
    private static function compactCommentReview(array $comment): array
    {
        return [
            'id' => (string) ($comment['id'] ?? ''),
            'authorId' => (string) ($comment['authorId'] ?? ''),
            'author' => (string) ($comment['author'] ?? ''),
            'initials' => (string) ($comment['initials'] ?? ''),
            'date' => (string) ($comment['date'] ?? ''),
            'text' => (string) ($comment['text'] ?? ''),
            'partName' => (string) ($comment['partName'] ?? ''),
            'x' => (int) ($comment['x'] ?? 0),
            'y' => (int) ($comment['y'] ?? 0),
        ];
    }

    /**
     * @return array<string, array{charts: list<array<string, mixed>>, speakerNotes: list<array<string, mixed>>, commentAuthors: list<array<string, mixed>>, comments: list<array<string, mixed>>}>
     */
    private static function expectedReviewMetadataByStem(): array
    {
        return [
            'chart-placeholder' => [
                'charts' => [self::expectedChartPlaceholderReview()],
                'speakerNotes' => [],
                'commentAuthors' => [],
                'comments' => [],
            ],
            'chart-embedded-workbook' => [
                'charts' => [self::expectedChartEmbeddedWorkbookReview()],
                'speakerNotes' => [],
                'commentAuthors' => [],
                'comments' => [],
            ],
            'speaker-notes' => [
                'charts' => [],
                'speakerNotes' => [self::expectedSpeakerNoteReview()],
                'commentAuthors' => [],
                'comments' => [],
            ],
            'comments-ignored' => [
                'charts' => [],
                'speakerNotes' => [],
                'commentAuthors' => [self::expectedCommentAuthorReview()],
                'comments' => [self::expectedCommentReview()],
            ],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function expectedChartReviewsByStem(): array
    {
        return [
            'chart-placeholder' => [self::expectedChartPlaceholderReview()],
            'chart-embedded-workbook' => [self::expectedChartEmbeddedWorkbookReview()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function expectedSpeakerNoteReview(): array
    {
        return [
            'relationshipId' => 'rIdNotes',
            'relationshipType' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide',
            'target' => '../notesSlides/notesSlide1.xml',
            'partName' => 'ppt/notesSlides/notesSlide1.xml',
            'text' => "Remember the launch date.\nAsk about migration risks.",
            'blockCount' => 2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function expectedCommentAuthorReview(): array
    {
        return [
            'name' => 'Reviewer',
            'initials' => 'RV',
            'lastIdx' => '1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function expectedCommentReview(): array
    {
        return [
            'id' => '1',
            'authorId' => '0',
            'author' => 'Reviewer',
            'initials' => 'RV',
            'date' => '2026-07-03T00:00:00Z',
            'text' => 'Reviewer-only note',
            'partName' => 'ppt/comments/comment1.xml',
            'x' => 120,
            'y' => 240,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function expectedChartPlaceholderReview(): array
    {
        return [
            'graphicUri' => 'http://schemas.openxmlformats.org/drawingml/2006/chart',
            'relationshipId' => 'rIdChart1',
            'relationshipType' => '',
            'target' => '',
            'partName' => '',
            'external' => false,
            'title' => '',
            'chartType' => 'unknown',
            'series' => [],
            'externalDataRelationshipIds' => [],
            'externalDataRelationships' => [],
            'issues' => ['unknown-chart-relationship'],
            'byteExposurePolicy' => 'chart-part-bytes-blocked',
            'reviewPolicy' => 'chart-metadata-and-cache-values-only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function expectedChartEmbeddedWorkbookReview(): array
    {
        return [
            'graphicUri' => 'http://schemas.openxmlformats.org/drawingml/2006/chart',
            'relationshipId' => 'rIdChart1',
            'relationshipType' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart',
            'target' => '../charts/chart1.xml',
            'partName' => 'ppt/charts/chart1.xml',
            'external' => false,
            'title' => 'Embedded Workbook Chart',
            'chartType' => 'unknown',
            'series' => [],
            'externalDataRelationshipIds' => ['rIdWorkbook'],
            'externalDataRelationships' => [
                [
                    'relationshipId' => 'rIdWorkbook',
                    'relationshipType' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package',
                    'target' => '../embeddings/Microsoft_Excel_Worksheet1.xlsx',
                    'external' => false,
                    'partName' => 'ppt/embeddings/Microsoft_Excel_Worksheet1.xlsx',
                    'exists' => true,
                    'zipEntry' => 'ppt/embeddings/Microsoft_Excel_Worksheet1.xlsx',
                    'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'packageRelationshipRole' => 'embedded-workbook',
                    'embeddedWorkbook' => true,
                    'byteLength' => 35,
                    'compressedByteLength' => 35,
                    'compressionMethod' => 0,
                    'sha256' => '88240b7ef08d8ae0d2d98545f46f46a7fc38d4aa83749fb4b273c45d09393c3d',
                    'byteExposurePolicy' => 'package-part-bytes-hashed-not-exposed',
                ],
            ],
            'issues' => [],
            'byteExposurePolicy' => 'chart-part-bytes-blocked',
            'reviewPolicy' => 'chart-metadata-and-cache-values-only',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function executableNativeAstSnapshotEvidence(): array
    {
        $snapshotFile = $this->snapshotFileEvidence(
            self::CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_PATH,
            self::CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_SHA256,
            self::CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_BYTES
        );
        $issues = [];
        $payload = [];
        $path = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_PATH);

        if (($snapshotFile['present'] ?? false) !== true) {
            $issues[] = 'missing-checked-in-current-pptx-executable-native-ast-snapshot';
        } else {
            if (($snapshotFile['sha256'] ?? null) !== self::CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_SHA256) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-snapshot-sha256-mismatch';
            }
            if ((int) ($snapshotFile['bytes'] ?? -1) !== self::CHECKED_IN_EXECUTABLE_NATIVE_AST_SNAPSHOT_BYTES) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-snapshot-byte-count-mismatch';
            }

            try {
                $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $payload = $decoded;
                } else {
                    $issues[] = 'checked-in-current-pptx-executable-native-ast-snapshot-json-root-not-object';
                }
            } catch (\JsonException) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-snapshot-invalid-json';
            }
        }

        if ($payload !== []) {
            if (($payload['schemaVersion'] ?? null) !== 1) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-schema-version-mismatch';
            }
            if (($payload['tool'] ?? null) !== 'pandoc-pptx-executable-native-ast') {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-tool-mismatch';
            }
            if (($payload['evidenceKind'] ?? null) !== 'checked-in-real-pandoc-executable-pptx-native-ast-snapshot') {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-kind-mismatch';
            }
            if (($payload['sourceTool'] ?? null) !== 'tools/pandoc-pptx-executable-native-ast.php') {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-source-tool-mismatch';
            }
            if (($payload['sourceCommand'] ?? null) !== [
                'php',
                'tools/pandoc-pptx-executable-native-ast.php',
                '--pptx-dir=' . self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
                '--pandoc-bin=/opt/homebrew/bin/pandoc',
                '--json',
                '--require-executable-parity=' . self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT,
                '--require-pandoc-version=pandoc 3.10',
            ]) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-source-command-mismatch';
            }
            if (($payload['capturedDate'] ?? null) !== '2026-07-05') {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-captured-date-mismatch';
            }
            if (($payload['pptxDirectory'] ?? null) !== self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-directory-mismatch';
            }
            if (($payload['pandocVersion'] ?? null) !== 'pandoc 3.10') {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-pandoc-version-mismatch';
            }
            if (self::stringList($payload['fixtureStems'] ?? []) !== self::expectedExecutableFixtureStems()) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-fixture-stems-mismatch';
            }
            if (!PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($payload, self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT)) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-parity-mismatch';
            }
            if (!PptxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($payload, 'pandoc 3.10')) {
                $issues[] = 'checked-in-current-pptx-executable-native-ast-version-gate-mismatch';
            }
        }

        return self::executableNativeAstMappedParityEvidence($payload, $snapshotFile, $issues);
    }

    /**
     * @return list<string>
     */
    private static function expectedExecutableFixtureStems(): array
    {
        $stems = array_keys(self::CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT);
        sort($stems, SORT_STRING);

        return $stems;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private static function nativeAstMappedParityEvidence(array $report): array
    {
        return [
            'kind' => 'checked-in-current-pptx-native-normalized-ast-parity',
            'tool' => (string) ($report['tool'] ?? 'pandoc-pptx-native-ast'),
            'status' => (string) ($report['status'] ?? 'unknown'),
            'skipped' => (bool) ($report['skipped'] ?? false),
            'reason' => $report['reason'] ?? null,
            'evidenceKind' => (string) ($report['evidenceKind'] ?? 'pptx-native-normalized-ast-comparison'),
            'requiredPairCount' => self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT,
            'upstreamPptxDirectory' => (string) ($report['upstreamPptxDirectory'] ?? ''),
            'totalPairCount' => (int) ($report['totalPairCount'] ?? 0),
            'comparedPairCount' => (int) ($report['comparedPairCount'] ?? 0),
            'pptxParsedCount' => (int) ($report['pptxParsedCount'] ?? 0),
            'nativeParsedCount' => (int) ($report['nativeParsedCount'] ?? 0),
            'bothParsedCount' => (int) ($report['bothParsedCount'] ?? 0),
            'unpairedPptxCount' => (int) ($report['unpairedPptxCount'] ?? 0),
            'unpairedNativeCount' => (int) ($report['unpairedNativeCount'] ?? 0),
            'unpairedPptxFixtures' => self::stringList($report['unpairedPptxFixtures'] ?? []),
            'unpairedNativeFixtures' => self::stringList($report['unpairedNativeFixtures'] ?? []),
            'parseFailureCount' => (int) ($report['parseFailureCount'] ?? 0),
            'normalizedAstMatchCount' => (int) ($report['normalizedAstMatchCount'] ?? 0),
            'normalizedAstMismatchCount' => (int) ($report['normalizedAstMismatchCount'] ?? 0),
            'normalizedAstMatchPercent' => $report['normalizedAstMatchPercent'] ?? null,
            'astParityStatus' => (string) ($report['astParityStatus'] ?? 'unknown'),
            'hasRequiredMappedParity' => PptxNativeAstComparisonHarness::hasRequiredMappedParity(
                $report,
                self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            ),
            'normalizationPolicy' => is_array($report['normalizationPolicy'] ?? null) ? $report['normalizationPolicy'] : [],
            'parseFailures' => is_array($report['parseFailures'] ?? null) ? $report['parseFailures'] : [],
            'mismatchCategories' => is_array($report['mismatchCategories'] ?? null) ? $report['mismatchCategories'] : [],
            'mismatchComparisons' => is_array($report['mismatchComparisons'] ?? null) ? $report['mismatchComparisons'] : [],
            'fixtureComparisons' => is_array($report['fixtureComparisons'] ?? null) ? $report['fixtureComparisons'] : [],
            'orderedRemainingGaps' => is_array($report['orderedRemainingGaps'] ?? null) ? $report['orderedRemainingGaps'] : [],
            'claim' => (string) ($report['claim'] ?? 'Checked-in current PPTX/native normalized AST comparison.'),
            'claimBoundaries' => [
                'doesAssert' => [
                    'local PHP PPTX reader output and checked-in current native fixtures are equal after documented normalization',
                ],
                'doesNotAssert' => [
                    'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                    'PPTX writer golden package parity',
                    'byte-level PPTX package equality',
                    'byte-for-byte identity with Pandoc Haskell reader internals',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int} $snapshotFile
     * @param list<string> $issues
     * @return array<string, mixed>
     */
    private static function executableNativeAstMappedParityEvidence(array $report, array $snapshotFile, array $issues): array
    {
        return [
            'kind' => 'checked-in-current-pptx-executable-native-normalized-ast-parity',
            'tool' => (string) ($report['tool'] ?? 'pandoc-pptx-executable-native-ast'),
            'status' => (string) ($report['status'] ?? 'unknown'),
            'skipped' => (bool) ($report['skipped'] ?? true),
            'reason' => $report['reason'] ?? null,
            'evidenceKind' => (string) ($report['evidenceKind'] ?? 'checked-in-real-pandoc-executable-pptx-native-ast-snapshot'),
            'requiredPptxCount' => self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT,
            'snapshotFile' => $snapshotFile,
            'sourceTool' => is_string($report['sourceTool'] ?? null) ? $report['sourceTool'] : null,
            'sourceCommand' => is_array($report['sourceCommand'] ?? null) ? $report['sourceCommand'] : [],
            'capturedDate' => is_string($report['capturedDate'] ?? null) ? $report['capturedDate'] : null,
            'pptxDirectory' => (string) ($report['pptxDirectory'] ?? ''),
            'pandocExecutable' => is_string($report['pandocExecutable'] ?? null) ? $report['pandocExecutable'] : null,
            'pandocVersion' => is_string($report['pandocVersion'] ?? null) ? $report['pandocVersion'] : null,
            'requiredPandocVersion' => 'pandoc 3.10',
            'totalPptxCount' => (int) ($report['totalPptxCount'] ?? 0),
            'comparedPptxCount' => (int) ($report['comparedPptxCount'] ?? 0),
            'localParsedCount' => (int) ($report['localParsedCount'] ?? 0),
            'pandocParsedCount' => (int) ($report['pandocParsedCount'] ?? 0),
            'nativeFixtureParsedCount' => (int) ($report['nativeFixtureParsedCount'] ?? 0),
            'bothParsedCount' => (int) ($report['bothParsedCount'] ?? 0),
            'parseFailureCount' => (int) ($report['parseFailureCount'] ?? 0),
            'normalizedAstMatchCount' => (int) ($report['normalizedAstMatchCount'] ?? 0),
            'normalizedAstMismatchCount' => (int) ($report['normalizedAstMismatchCount'] ?? 0),
            'normalizedAstMatchPercent' => $report['normalizedAstMatchPercent'] ?? null,
            'pandocNativeFixtureComparedCount' => (int) ($report['pandocNativeFixtureComparedCount'] ?? 0),
            'pandocNativeFixtureMatchCount' => (int) ($report['pandocNativeFixtureMatchCount'] ?? 0),
            'pandocNativeFixtureMismatchCount' => (int) ($report['pandocNativeFixtureMismatchCount'] ?? 0),
            'pandocNativeFixtureMatchPercent' => $report['pandocNativeFixtureMatchPercent'] ?? null,
            'astParityStatus' => (string) ($report['astParityStatus'] ?? 'unknown'),
            'fixtureStems' => self::stringList($report['fixtureStems'] ?? []),
            'hasRequiredExecutableParity' => PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity(
                $report,
                self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT
            ),
            'hasRequiredPandocVersion' => PptxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion(
                $report,
                'pandoc 3.10'
            ),
            'parseFailures' => is_array($report['parseFailures'] ?? null) ? $report['parseFailures'] : [],
            'mismatchCategories' => is_array($report['mismatchCategories'] ?? null) ? $report['mismatchCategories'] : [],
            'mismatchComparisons' => is_array($report['mismatchComparisons'] ?? null) ? $report['mismatchComparisons'] : [],
            'pandocNativeFixtureMismatchComparisons' => is_array($report['pandocNativeFixtureMismatchComparisons'] ?? null) ? $report['pandocNativeFixtureMismatchComparisons'] : [],
            'fixtureComparisons' => is_array($report['fixtureComparisons'] ?? null) ? $report['fixtureComparisons'] : [],
            'orderedRemainingGaps' => is_array($report['orderedRemainingGaps'] ?? null) ? $report['orderedRemainingGaps'] : [],
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-pptx-executable-native-ast-parity' : 'invalid-checked-in-current-pptx-executable-native-ast-parity',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => (string) ($report['claim'] ?? 'Checked-in executable Pandoc/native normalized AST comparison.'),
            'claimBoundaries' => [
                'doesAssert' => [
                    'checked-in executable Pandoc native output, local PHP PPTX reader output, and paired checked-in native fixtures are equal after documented normalization',
                ],
                'doesNotAssert' => [
                    'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                    'fresh local Pandoc execution during this reader evidence gate',
                    'PPTX writer golden package parity',
                    'byte-for-byte identity with Pandoc Haskell reader internals',
                ],
            ],
        ];
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private function snapshotFileEvidence(string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'readerTestCompareCount' => 0,
            'fixturePairCount' => 0,
            'referencedPairCount' => 0,
            'readerCases' => [],
            'fixturePairs' => [],
            'unpairedPptxFixtureCount' => 0,
            'unpairedNativeFixtureCount' => 0,
            'unpairedPptxFixtures' => [],
            'unpairedNativeFixtures' => [],
            'missingReferencedFiles' => [],
            'unreferencedFixturePairs' => [],
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
     * @return list<array{name: string, pptx: string, native: string, pairKey: string}>
     */
    private function parseReaderCases(string $source): array
    {
        $cases = [];
        $pattern = '/\btestCompare(?:WithOpts\s+\S+)?\s+"([^"]+)"\s+"([^"]+\.pptx)"\s+"([^"]+\.native)"/s';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $pptx = (string) $match[2];
                $native = (string) $match[3];
                $cases[] = [
                    'name' => (string) $match[1],
                    'pptx' => $pptx,
                    'native' => $native,
                    'pairKey' => self::pairKey($pptx, $native),
                ];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{pptx: string, native: string, pairKey: string}>
     */
    private function fixturePairs(string $fixtureDirectory): array
    {
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $pptxByStem = $this->fixtureFilesByStem($fixtureDirectory, 'pptx');
        $nativeByStem = $this->fixtureFilesByStem($fixtureDirectory, 'native');

        $pairs = [];
        foreach (array_intersect(array_keys($pptxByStem), array_keys($nativeByStem)) as $stem) {
            $pptx = $pptxByStem[$stem];
            $native = $nativeByStem[$stem];
            $pairs[$stem] = [
                'pptx' => $pptx,
                'native' => $native,
                'pairKey' => self::pairKey($pptx, $native),
            ];
        }
        ksort($pairs, SORT_STRING);

        return $pairs;
    }

    /**
     * @return array<string, string>
     */
    private function fixtureFilesByStem(string $fixtureDirectory, string $extension): array
    {
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $files = [];
        foreach (glob($fixtureDirectory . '/*.' . $extension) ?: [] as $path) {
            $stem = basename($path, '.' . $extension);
            $files[$stem] = 'pptx-reader/' . basename($path);
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array{pptx:list<string>, native:list<string>}
     */
    private function unpairedFixtureFiles(string $fixtureDirectory): array
    {
        $pptxByStem = $this->fixtureFilesByStem($fixtureDirectory, 'pptx');
        $nativeByStem = $this->fixtureFilesByStem($fixtureDirectory, 'native');

        $unpairedPptx = array_values(array_diff_key($pptxByStem, $nativeByStem));
        $unpairedNative = array_values(array_diff_key($nativeByStem, $pptxByStem));
        sort($unpairedPptx, SORT_STRING);
        sort($unpairedNative, SORT_STRING);

        return [
            'pptx' => $unpairedPptx,
            'native' => $unpairedNative,
        ];
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @return list<array{case: string, path: string}>
     */
    private function missingReferencedFiles(string $root, array $readerCases): array
    {
        $missing = [];
        foreach ($readerCases as $case) {
            foreach (['pptx', 'native'] as $kind) {
                $relative = 'test/' . $case[$kind];
                if (!is_file($root . '/' . $relative)) {
                    $missing[] = [
                        'case' => $case['name'],
                        'path' => $relative,
                    ];
                }
            }
        }

        return $missing;
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @param array<string, array{pptx: string, native: string, pairKey: string}> $fixturePairs
     * @return list<array{pptx: string, native: string, pairKey: string}>
     */
    private function unreferencedFixturePairs(array $readerCases, array $fixturePairs): array
    {
        $referenced = [];
        foreach ($readerCases as $case) {
            $referenced[$case['pairKey']] = true;
        }

        $unreferenced = [];
        foreach ($fixturePairs as $pair) {
            if (!isset($referenced[$pair['pairKey']])) {
                $unreferenced[] = $pair;
            }
        }

        return $unreferenced;
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @param array<string, array{pptx: string, native: string, pairKey: string}> $fixturePairs
     * @param array{pptx:list<string>, native:list<string>} $unpairedFixtures
     * @return list<string>
     */
    private function validationIssues(
        string $root,
        ?string $observedCommit,
        array $readerCases,
        array $fixturePairs,
        array $unpairedFixtures
    ): array
    {
        $issues = [];
        if ($observedCommit === null) {
            $issues[] = 'upstream-git-head-unavailable';
        } elseif ($observedCommit !== self::EXPECTED_UPSTREAM_COMMIT) {
            $issues[] = 'upstream-commit-mismatch';
        }
        if (!is_file($root . '/test/Tests/Readers/Pptx.hs')) {
            $issues[] = 'missing-reader-test-module';
        }
        if (!is_dir($root . '/test/pptx-reader')) {
            $issues[] = 'missing-pptx-reader-fixture-directory';
        }
        if ($readerCases === []) {
            $issues[] = 'no-reader-test-comparisons-found';
        }
        if ($fixturePairs === []) {
            $issues[] = 'no-pptx-native-fixture-pairs-found';
        }
        if ($this->missingReferencedFiles($root, $readerCases) !== []) {
            $issues[] = 'missing-referenced-fixture-files';
        }
        if ($this->unreferencedFixturePairs($readerCases, $fixturePairs) !== []) {
            $issues[] = 'unreferenced-fixture-pairs';
        }
        if ($unpairedFixtures['pptx'] !== []) {
            $issues[] = 'unpaired-pptx-fixtures';
        }
        if ($unpairedFixtures['native'] !== []) {
            $issues[] = 'unpaired-native-fixtures';
        }
        if (count($readerCases) !== count($fixturePairs)) {
            $issues[] = 'reader-test-count-does-not-match-fixture-pair-count';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceInventory(string $root): array
    {
        $relativeFiles = [
            'test/Tests/Readers/Pptx.hs',
            'src/Text/Pandoc/Readers/Pptx.hs',
            'src/Text/Pandoc/Readers/Pptx/Parse.hs',
            'src/Text/Pandoc/Readers/Pptx/Shapes.hs',
            'src/Text/Pandoc/Readers/Pptx/Slides.hs',
            'src/Text/Pandoc/Readers/Pptx/SmartArt.hs',
        ];
        $files = [];
        $presentCount = 0;
        $lineCount = 0;
        foreach ($relativeFiles as $relative) {
            $path = $root . '/' . $relative;
            $present = is_file($path);
            $lines = $present ? count(file($path) ?: []) : 0;
            $files[] = [
                'path' => $relative,
                'present' => $present,
                'lines' => $lines,
            ];
            if ($present) {
                ++$presentCount;
                $lineCount += $lines;
            }
        }

        return [
            'files' => $files,
            'presentFileCount' => $presentCount,
            'missingFileCount' => count($relativeFiles) - $presentCount,
            'presentLineCount' => $lineCount,
        ];
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
    }

    private function displayPath(string $path): string
    {
        $prefix = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        $gitDir = $root . '/.git';
        if (!is_dir($gitDir)) {
            return null;
        }

        $output = [];
        $exitCode = 0;
        exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0 || !is_string($output[0] ?? null)) {
            return self::gitHeadFromFiles($gitDir);
        }

        $head = strtolower(trim($output[0]));

        return preg_match('/^[0-9a-f]{40}$/', $head) === 1 ? $head : null;
    }

    private static function gitHeadFromFiles(string $gitDir): ?string
    {
        $headPath = $gitDir . '/HEAD';
        if (!is_file($headPath)) {
            return null;
        }

        $head = trim((string) file_get_contents($headPath));
        if (preg_match('/^[0-9a-f]{40}$/i', $head) === 1) {
            return strtolower($head);
        }

        if (!str_starts_with($head, 'ref:')) {
            return null;
        }

        $ref = trim(substr($head, 4));
        if ($ref === '' || str_starts_with($ref, '/') || str_contains($ref, '..')) {
            return null;
        }

        $refPath = $gitDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $ref);
        if (!is_file($refPath)) {
            return null;
        }

        $hash = trim((string) file_get_contents($refPath));

        return preg_match('/^[0-9a-f]{40}$/i', $hash) === 1 ? strtolower($hash) : null;
    }

    private static function pairKey(string $pptx, string $native): string
    {
        return $pptx . '|' . $native;
    }
}
