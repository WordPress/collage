<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubNativeAstPackageComparisonHarness
{
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';

    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'epub-native-ast-package-implementation-equivalence-observed';
    private const CLAIM = 'Compares local PHP EPUB package parsing and reader output with a supplied checked-in current EPUB fixture directory and same-basename .native goldens. Package parsing/reader acceptance, fixture identity, package feature coverage, and native AST equality are reported separately; writer parity is outside this reader-scoped report.';
    private const PACKAGE_FEATURE_SIGNATURE_KIND = 'checked-in-current-epub-package-feature-signature';
    private const PACKAGE_FEATURE_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const PACKAGE_FEATURE_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-76-fixture-snapshot';
    private const CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256 = '5a3753f72c22cbcd34bb3024bed0b648ece3ec5d366dde320f83d27e98800c9b';
    private const CURRENT_NATIVE_AST_SIGNATURE_KIND = 'checked-in-current-epub-normalized-native-ast-signature';
    private const CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const CURRENT_NATIVE_AST_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-76-fixture-normalized-ast-snapshot';
    private const CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256 = 'febb26d07147f5ed01e222cb31995c6b444e382d41fa8ab8bdb236ff2786eecf';
    private const RUNNER_CABAL_TARGET = 'exe:pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/epub-native-package-run';
    private const RUNNER_FIXTURE_DIRECTORY = 'test/epub';
    private const RUNNER_OUTPUT_FORMAT = 'native';
    private const RUNNER_REQUIRED_TRANSCRIPTS = [
        '.port-libs/pandoc-runner/logs/epub-native-package-runner-dependencies.txt',
        '.port-libs/pandoc-runner/logs/epub-native-package-fixture-inventory.txt',
        '.port-libs/pandoc-runner/logs/epub-native-package-native-generation.txt',
    ];
    private const RUNNER_REQUIRED_ARTIFACTS = [
        '.port-libs/pandoc-runner/artifacts/epub-native-package/result.json',
        '.port-libs/pandoc-runner/artifacts/epub-native-package/generated-native-manifest.json',
    ];
    private const RUNNER_RESULT_ARTIFACT_KIND = 'upstream-epub-native-package-runner-result-artifact';
    private const RUNNER_TRANSCRIPT_KIND = 'upstream-epub-native-package-runner-transcript';
    private const RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION = 2;

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'epubRootfile' => true,
        'epubManifestItemCount' => true,
        'epubManifestItems' => true,
        'epubSpineItems' => true,
        'epubSpineItemRefs' => true,
        'epubReadableResources' => true,
        'epubReferencedResources' => true,
        'epubImageResources' => true,
        'epubMediaBagResources' => true,
        'epubMediaResourcePolicy' => true,
        'epubMediaResourceDirectory' => true,
        'epubMediaResourceCount' => true,
        'epubMediaResourceDiagnostics' => true,
        'epubTocResources' => true,
        'epubTocEntryCount' => true,
        'epubTocEntries' => true,
        'epubLandmarkEntryCount' => true,
        'epubLandmarkEntries' => true,
        'captionBlocks' => true,
        'captionSource' => true,
        'columnSources' => true,
        'columnSpecs' => true,
        'header' => true,
        'metaConstructorProvenance' => true,
        'metaNativeValues' => true,
        'rowHeadColumns' => true,
        'tableGeometry' => true,
        'sourceFormat' => true,
    ];

    /**
     * @var array<string, array{sha256: string, bytes: int}>
     */
    private const CHECKED_IN_CURRENT_FIXTURE_IDENTITIES = array (
  'accessibility-metadata-package.epub' =>
  array (
    'sha256' => '1f0fc1e25c99a96af5af9a0128f2eeb72affcf8e86afbbf7f1541e2a7695c9cc',
    'bytes' => 1855,
  ),
  'accessibility-metadata-package.native' =>
  array (
    'sha256' => '99e5e68f3899c680fcb162ffb432797a07e8194d5aa7ce73b8eca93f8900cfde',
    'bytes' => 289,
  ),
  'all-nonlinear-spine.epub' =>
  array (
    'sha256' => '83fc005e5ab9feaca5c6a08b61d590d0cc3958bbe75b43b5f5a108c599e59882',
    'bytes' => 1364,
  ),
  'all-nonlinear-spine.native' =>
  array (
    'sha256' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
    'bytes' => 3,
  ),
  'appendix-navigation-guide.epub' =>
  array (
    'sha256' => '2c7b9ca20d38dcda15b63a1bf4aa210a3b11b132dc0a8fe6bedec13d24675c4f',
    'bytes' => 1645,
  ),
  'appendix-navigation-guide.native' =>
  array (
    'sha256' => '8e9029119f437f20c1ba3fa8055beb80087bf9230c083e297ac91f3115453bed',
    'bytes' => 272,
  ),
  'audio-navigation.epub' =>
  array (
    'sha256' => '09e5d95402b3a0b34fc1843b61534fe02a58df21ec524071776698f66b8c43a2',
    'bytes' => 1509,
  ),
  'audio-navigation.native' =>
  array (
    'sha256' => '86911ce05ad45760deb8f82eb4fe1b569626b09cddcf9fabc2b45cae50b37a22',
    'bytes' => 262,
  ),
  'auxiliary-lot-guide-index.epub' =>
  array (
    'sha256' => '8581efb4630635b95af119442cb682181b0004b90d53c6c43dfa255fc1c5bb58',
    'bytes' => 1434,
  ),
  'auxiliary-lot-guide-index.native' =>
  array (
    'sha256' => 'b472ccfd6ed29dd2b8f26da6da27509abd6a2c3b0964fddda31519a75a205ee2',
    'bytes' => 331,
  ),
  'bindings-collections-sidecars.epub' =>
  array (
    'sha256' => '82cd32b901ed412a69c5080707ed566207b06030c074bffa3b83460692f07834',
    'bytes' => 3767,
  ),
  'bindings-collections-sidecars.native' =>
  array (
    'sha256' => '2dc016af0d0e6f660a7a825acebf27d3bd2a74d30cc0914651b099877774932d',
    'bytes' => 679,
  ),
  'blockquote-list-spine.epub' =>
  array (
    'sha256' => '74fbf6f8f030e88a866ba652f72d0ec6864149c38ae8df45b9efd0bb4b8d0746',
    'bytes' => 1494,
  ),
  'blockquote-list-spine.native' =>
  array (
    'sha256' => 'dc459413755a1c07b599fb45cbb3cbd4c26bcdf8cf2caef31bdeaa1029bdfbc0',
    'bytes' => 761,
  ),
  'code-block-spine.epub' =>
  array (
    'sha256' => 'edce77261e123eb3aa3ef2614978a0854900a24ae02834d68f9625d70e5f5f3b',
    'bytes' => 1364,
  ),
  'code-block-spine.native' =>
  array (
    'sha256' => '996f70134a1e706fb7525e5fbac1d2d7d7c2ae4d95bfa52a99f1cfee5e3137f8',
    'bytes' => 334,
  ),
  'content-image-nav-media.epub' =>
  array (
    'sha256' => 'd02bb4c45558841903bb5e83ea3f15af2ca00d4221236d10978b4c0d672e8ce6',
    'bytes' => 2410,
  ),
  'content-image-nav-media.native' =>
  array (
    'sha256' => '258f9b8a1b2a9c8df41cbe9142d573d52e248b45cb3872ef2c071328d0e80b34',
    'bytes' => 589,
  ),
  'cross-spine-internal-links.epub' =>
  array (
    'sha256' => '10356bf8205f5eab35bb851bf155cdd279b23804740017cf59bb28d4f10a07e5',
    'bytes' => 1701,
  ),
  'cross-spine-internal-links.native' =>
  array (
    'sha256' => '55c6607d1baee634d2e06404a7c1b7c5271880b20d86c9ccf06d097045bb7f09',
    'bytes' => 743,
  ),
  'definition-list-spine.epub' =>
  array (
    'sha256' => '0baab26570c728b891093f14904fcebd543708c902091e851dce748a76ab2fa0',
    'bytes' => 1416,
  ),
  'definition-list-spine.native' =>
  array (
    'sha256' => 'c63677d9de4ea45d6fe74f5d68f433d73a2b2ec9076a803ecf4c6d7a5e5d78dd',
    'bytes' => 790,
  ),
  'direct-image-spine.epub' =>
  array (
    'sha256' => '695bb5c110c2011b4567c6f4a62b5d3249e00be37cfaff92b965ce346b376cb7',
    'bytes' => 1355,
  ),
  'direct-image-spine.native' =>
  array (
    'sha256' => '8fe089bfca1066f7d76935f553392c35991256fd50fe0ae24fa302793db766e2',
    'bytes' => 344,
  ),
  'direct-svg-spine.epub' =>
  array (
    'sha256' => '77dc5b929067cdcc9edd301ce78f8d391797e3dfcc029ee662dc4e2af4902884',
    'bytes' => 1048,
  ),
  'direct-svg-spine.native' =>
  array (
    'sha256' => '961617101a0680d85822c5483ca37ee262a3936ed7c2a29e6a10679fcca35795',
    'bytes' => 47,
  ),
  'duplicate-spine-idref.epub' =>
  array (
    'sha256' => 'cdcd53351890ca8b684b2ad5581be3f57a49c80296c1c7c70bf52fa5220ea3cd',
    'bytes' => 1423,
  ),
  'duplicate-spine-idref.native' =>
  array (
    'sha256' => 'a531ce241637505ddcc5a03704f159d5fd5ee213cc59721bb1fb4e93105bb5ff',
    'bytes' => 1312,
  ),
  'encoded-image-media-bag.epub' =>
  array (
    'sha256' => '38642e1a951ac69c1f8ff3874e2cb7d9b752310406efd42bb0109a6fd4c0f329',
    'bytes' => 1302,
  ),
  'encoded-image-media-bag.native' =>
  array (
    'sha256' => '1b0af7b52d751bc2524236104b8fb8b153e2bd9f255f4fa61a279446089518e2',
    'bytes' => 349,
  ),
  'epub2_cover.epub' =>
  array (
    'sha256' => '4af73a135aa632cbf0c00b2889a5fc1d39a59a77fa294fdeff5ede72ff6ffed1',
    'bytes' => 11794,
  ),
  'epub2_cover.native' =>
  array (
    'sha256' => '501e5182f6b213cb9482669ae4d9f506c8ece71f8aea29ab4abb014e57d8344a',
    'bytes' => 390,
  ),
  'epub2_no_cover.epub' =>
  array (
    'sha256' => '8369dbe5cf315f1fe00f9dd1bf7c500cc663d7648edbf0d7b6a9b4d785fedf4e',
    'bytes' => 3584,
  ),
  'epub2_no_cover.native' =>
  array (
    'sha256' => '6063e77cc1d1ce4feeaa110b43f6c8c452403a464951c36c156f41dbef269402',
    'bytes' => 322,
  ),
  'epub2_picture.epub' =>
  array (
    'sha256' => '6049dde9e1d0ebcd175a8c5b937984f349af996e293310eafbce09e4c7384495',
    'bytes' => 11742,
  ),
  'epub2_picture.native' =>
  array (
    'sha256' => '1c13430b583a0b9df6b98d7a285a5400571e8bc512acb4ea46d37acafbbac7da',
    'bytes' => 390,
  ),
  'epub3-ncx-toc-fallback.epub' =>
  array (
    'sha256' => 'ead984a9fdd9e85194a55d0c1a4f28d67182493bad9692f8ee19424b33ddd225',
    'bytes' => 2189,
  ),
  'epub3-ncx-toc-fallback.native' =>
  array (
    'sha256' => 'd2af2d91536fe498affbe70f0de4a917c30c5c8e0cc147dc631bbb5cf49af781',
    'bytes' => 1013,
  ),
  'external-footnote-reference.epub' =>
  array (
    'sha256' => '9df47e23e87d0385737c76fbc518bec86d7ab222e9a007c1db1d0e5f9c0ec5d2',
    'bytes' => 1766,
  ),
  'external-footnote-reference.native' =>
  array (
    'sha256' => 'ee4878561dad1a0f53703d0cb4bd8b2726068cee9482c32df47fa481194675ee',
    'bytes' => 286,
  ),
  'features.epub' =>
  array (
    'sha256' => '6bf9a102249d58b32f14b39dfbc966bdecadff68a3fb707cb3ca62334734358a',
    'bytes' => 8970,
  ),
  'features.native' =>
  array (
    'sha256' => 'a9019153ea883dccd5d67af2008079fa8daa763a8f4a0796d44026f67675043b',
    'bytes' => 43996,
  ),
  'figure-caption-spine.epub' =>
  array (
    'sha256' => 'ff953ce2bdaee8786620ea8deb2473c7886334d862112fc520a76658726e8c07',
    'bytes' => 1581,
  ),
  'figure-caption-spine.native' =>
  array (
    'sha256' => 'b470cb9f3fc84d90dda902de611d93b57b6090c2447a2e8a9637c3c97a56d50e',
    'bytes' => 616,
  ),
  'font-manifest-resource.epub' =>
  array (
    'sha256' => 'ab561d6de4579fbe572ae1e99e56c3dcba464f1d9c2906310f1324d1a1243d0e',
    'bytes' => 1512,
  ),
  'font-manifest-resource.native' =>
  array (
    'sha256' => 'd8dddfa841becc2b7bf6e730f790cb283396aec3444365a4e74177ef8843f7c3',
    'bytes' => 273,
  ),
  'formatting.epub' =>
  array (
    'sha256' => '491fc57ec384449a23c4f2abdcfe91be9ab2a07f50f466fb8d80775b89bf3965',
    'bytes' => 14022,
  ),
  'formatting.native' =>
  array (
    'sha256' => '3353ae64eee933d28caab426803ef61deb2ce26923beef703f3028148ffc419d',
    'bytes' => 160079,
  ),
  'fragment-nav-spine.epub' =>
  array (
    'sha256' => 'cf582d0b887cd5c7a01180a7fe45138144bb650dc257f21c32ef33765a50a6b8',
    'bytes' => 1372,
  ),
  'fragment-nav-spine.native' =>
  array (
    'sha256' => '81ffc5d60c1d7c49cfe3f95c44036d87d922c2aef8d71425dce3cb666da5576e',
    'bytes' => 550,
  ),
  'guide-bibliography-reference.epub' =>
  array (
    'sha256' => 'c41d806bf13306837ecfdbc12504a1f134f85d40545bb4694447763297f891fd',
    'bytes' => 1391,
  ),
  'guide-bibliography-reference.native' =>
  array (
    'sha256' => 'e42b3f67d1340493874064c45bb93147b467d1a91f06121655188b6b7640bfa2',
    'bytes' => 284,
  ),
  'guide-glossary-reference.epub' =>
  array (
    'sha256' => '699550c8c91e9f11cb430c24e2e157a1f6dfb4f11cff2b98f5ad3cce72b6141d',
    'bytes' => 1386,
  ),
  'guide-glossary-reference.native' =>
  array (
    'sha256' => 'c40a968d5df5756cbb0aa48cddad780ac399470754e73f6b0b3e55b7e4c24e80',
    'bytes' => 277,
  ),
  'guide-notes-reference.epub' =>
  array (
    'sha256' => '7fdc04f51cc6f359c5f44cd56661d953f2ccd00983a45ae4fedcb91c275fccee',
    'bytes' => 1378,
  ),
  'guide-notes-reference.native' =>
  array (
    'sha256' => '22f8995379eaa6108f1eb658468b1c3075047a0d4d4448d3d573137fb04bf500',
    'bytes' => 270,
  ),
  'guide-preface-reference.epub' =>
  array (
    'sha256' => 'd4470953a6b05f8a8d33a1aa766a04fd9a58ea897b3017a41aed7d2410990d37',
    'bytes' => 1367,
  ),
  'guide-preface-reference.native' =>
  array (
    'sha256' => '7e5c20fc82802f8f019f7de05fd6e38a96a961f0abc98905cb1581b808ce0077',
    'bytes' => 274,
  ),
  'img.epub' =>
  array (
    'sha256' => 'f2c25e0e0612b7ac33a8d6a1c9719a86e7d2a0290472fc7d8b5068de781a822f',
    'bytes' => 20478,
  ),
  'img.native' =>
  array (
    'sha256' => 'd23803b0e2ce59892cad94c660e093375847a08ed856739ca7dda50d2ac4e3a7',
    'bytes' => 5311,
  ),
  'img_no_cover.epub' =>
  array (
    'sha256' => '3063f5e9b9610df1ddcc682ce49c293bcf681f1958700a5b6c3eda344383cf2a',
    'bytes' => 10602,
  ),
  'img_no_cover.native' =>
  array (
    'sha256' => 'f2c48a5ac5a84d3bab0091ca2dfc7af9877bb06dbfb286e7bb40b4d4e9740b8f',
    'bytes' => 5191,
  ),
  'inline-abbr-subscript-superscript.epub' =>
  array (
    'sha256' => '60d188945fb302e0e658afdc5be5843422f94b2edb344b392703dae00f1d1409',
    'bytes' => 1476,
  ),
  'inline-abbr-subscript-superscript.native' =>
  array (
    'sha256' => '3d9b3e8d736bcb4f233b70228b0bebce47e5716334cb949adb80977716999e53',
    'bytes' => 615,
  ),
  'language-french-metadata.epub' =>
  array (
    'sha256' => 'a64733afbdd101dcf679227227eacaa6dd8ec1649721e406cbc245e4e91a5f87',
    'bytes' => 1317,
  ),
  'language-french-metadata.native' =>
  array (
    'sha256' => '66ed9d9c546eb58f4fb7685ab5c30affa158a84b415a068408f95d52298a4dcf',
    'bytes' => 144,
  ),
  'main-section-spine.epub' =>
  array (
    'sha256' => '99f8c2afa52f3cb97bed7466fdff1bcb9a94f795ba27d306cabc70314cab40dc',
    'bytes' => 1445,
  ),
  'main-section-spine.native' =>
  array (
    'sha256' => '9f9d405ee278ca5a586aa3a9cd1020c90e6b66d2c8019e985d7c779a1347e5a5',
    'bytes' => 353,
  ),
  'manifest-fallback-chain.epub' =>
  array (
    'sha256' => 'af579a53102ff39e74bf2f79df687384ba1897c961aba9be197ba575079e18a4',
    'bytes' => 1735,
  ),
  'manifest-fallback-chain.native' =>
  array (
    'sha256' => '1f5d434d455f5b92592e929f598bf1fc07a229969912675419be70ad034d31b8',
    'bytes' => 276,
  ),
  'manifest-fallback-style.epub' =>
  array (
    'sha256' => 'e9c4c86b4fc4d167600f09b0daf4cafa4cd15763b833119209ed42d01ffd5f8f',
    'bytes' => 2063,
  ),
  'manifest-fallback-style.native' =>
  array (
    'sha256' => 'e5ab69b21a48e6f8b0f907ec5dfb1d07bd8942de45fb1aff6e8ce6159f40abb7',
    'bytes' => 580,
  ),
  'manifest-href-encoding.epub' =>
  array (
    'sha256' => 'a5f5643ef8d10b7ed6339a14153991273db0d78e23b2b8c2fcf949922f0c11e8',
    'bytes' => 2281,
  ),
  'manifest-href-encoding.native' =>
  array (
    'sha256' => '59c8166ffa04fa003cf7a11d2f8b5e9097d3402218f1d7553d760f9cad70f8e5',
    'bytes' => 513,
  ),
  'mathml-spine.epub' =>
  array (
    'sha256' => 'c89ff2507ce6ca380f20bdf0e4d2ca15f27baf0c9a68fac7f482587727a568b3',
    'bytes' => 1562,
  ),
  'mathml-spine.native' =>
  array (
    'sha256' => '394d586dfd52a7717a6989f20d0e034d9ac1dbb0c904d43ed2ae598b91be81d0',
    'bytes' => 484,
  ),
  'measurement-inline-spine.epub' =>
  array (
    'sha256' => 'bb31e5ad3dbacbe7c348e0da2993d099b164511deb210be40472b030ed7ab73f',
    'bytes' => 1480,
  ),
  'measurement-inline-spine.native' =>
  array (
    'sha256' => 'af0f1eb46b1768445f0ebb45e879ac928326d14835396fefaef91dbc52e0b496',
    'bytes' => 1020,
  ),
  'media-manifest-mix.epub' =>
  array (
    'sha256' => 'd74b69c881a8a46913a719fe2aa5311cb7fdf5ac747f98e7c5b342a3a78fe04c',
    'bytes' => 1801,
  ),
  'media-manifest-mix.native' =>
  array (
    'sha256' => '73f358ea83264cdf33658f481e03264f515f8d810b01d0b04f0496be8c2f8895',
    'bytes' => 513,
  ),
  'media-overlay-invalid-clips.epub' =>
  array (
    'sha256' => '0a50bda53abe80c587b701b4246c32282656e8f6692d92a0df23f0d879254144',
    'bytes' => 1942,
  ),
  'media-overlay-invalid-clips.native' =>
  array (
    'sha256' => '7eef159079249b60224888cf7ecdf494532cd7a82f886769522aa4a29cf7afd6',
    'bytes' => 369,
  ),
  'media-overlay-package.epub' =>
  array (
    'sha256' => '6af50dc4bf618cd964af7274a688aebcbd16da6804581325c00195b1721ed972',
    'bytes' => 1894,
  ),
  'media-overlay-package.native' =>
  array (
    'sha256' => '4e229ee5d0053c02d5ee8aaa425e800991bdcd5d3efad788043346f68cad1421',
    'bytes' => 300,
  ),
  'metadata-link-page-list-image.epub' =>
  array (
    'sha256' => 'ed2da17a5ea5cc370bde15d43e9480558654e644cf3c4d637ea50c71c1a3241c',
    'bytes' => 1926,
  ),
  'metadata-link-page-list-image.native' =>
  array (
    'sha256' => '884c97ef31814c40e380663f07792a4dd223d67457fd4b7cfbf0bae9be158cc5',
    'bytes' => 1140,
  ),
  'metadata-link-vocab-diagnostics.epub' =>
  array (
    'sha256' => '676ac3a2b19834ae0b3c7527e6353c0b79f37c9fbd98cbeae9e99ca4df3db35f',
    'bytes' => 2353,
  ),
  'metadata-link-vocab-diagnostics.native' =>
  array (
    'sha256' => '36f45d3c72c6917b50e03edd0c0d007926473fa93569362081cbdbf1f70c1043',
    'bytes' => 165,
  ),
  'metadata-record-remote-nav.epub' =>
  array (
    'sha256' => '74f7d7ecaa89dea3d0085f1208a78abf951de22e057245d321036bcd4b35ffe8',
    'bytes' => 1944,
  ),
  'metadata-record-remote-nav.native' =>
  array (
    'sha256' => '9a0dffbca5d0b8a52ac7d12e570a0e671d6892f54298d396d808a49940f31bad',
    'bytes' => 844,
  ),
  'metadata-search-link-semantics.epub' =>
  array (
    'sha256' => '02d2f49316abf1e2f2abc8f6959090dc891e24857b849297201782918cca3a3f',
    'bytes' => 1892,
  ),
  'metadata-search-link-semantics.native' =>
  array (
    'sha256' => '8e78383af179a9392bdc99d397444133b2423163663cfdc41e4e24583c68cd48',
    'bytes' => 1861,
  ),
  'missing-local-manifest-resource.epub' =>
  array (
    'sha256' => '5ce06b74cde06eb0d06f1b41b73f99840983451abb9bb120e8206979ac16dca5',
    'bytes' => 1386,
  ),
  'missing-local-manifest-resource.native' =>
  array (
    'sha256' => '1d2219a57a0cd610c1835c392e0819c4866f91082e92f4015b079f5539a3f1c8',
    'bytes' => 308,
  ),
  'missing-media-overlay.epub' =>
  array (
    'sha256' => '2f6f3b7da6babcda4101045e106c1bfac5ea56377ae96764793d8ccd98cadf07',
    'bytes' => 1422,
  ),
  'missing-media-overlay.native' =>
  array (
    'sha256' => 'fe3aa9b18f5365ca6b16ecafd7b640aa4d64158b7c5c2bc2892b3d02359564b5',
    'bytes' => 334,
  ),
  'multi-rootfile-nested-nav.epub' =>
  array (
    'sha256' => 'd4d65c5c0c6db9dc89ddbe0545f7870815a770d1441be00211b865155a273961',
    'bytes' => 2715,
  ),
  'multi-rootfile-nested-nav.native' =>
  array (
    'sha256' => 'd6aaf8b80629420e9b3ea1854a751cca180bc408a0eac6c8a1513b83eb2aa96b',
    'bytes' => 479,
  ),
  'nav-ncx-linear-guide.epub' =>
  array (
    'sha256' => '45b914d6e5ef83949c5432b7c523c383d323a3b9aa56499946155b88ace41f26',
    'bytes' => 2336,
  ),
  'nav-ncx-linear-guide.native' =>
  array (
    'sha256' => 'abee3ec4119924923d8d1c96ababc92bc0aa9ad38646e198e5d0b384ee0c0dd4',
    'bytes' => 322,
  ),
  'nested-path-media-metadata.epub' =>
  array (
    'sha256' => '685025a751e882b4700b6b31a0cdb8f51eceecaae86be1d83e0590beb2d876b7',
    'bytes' => 3588,
  ),
  'nested-path-media-metadata.native' =>
  array (
    'sha256' => '237760af79e8ff533a0bdab616e5a100ec81c85f7543b34ab388844bb8ad9766',
    'bytes' => 1899,
  ),
  'nested-rootfile-nonlinear-spine.epub' =>
  array (
    'sha256' => 'e0e41f25280f3b7a092ea2ed105af51c33e445221b2d54c877181c96aed191f4',
    'bytes' => 2043,
  ),
  'nested-rootfile-nonlinear-spine.native' =>
  array (
    'sha256' => '49135d70c19c11588f6a316fa00787463ce195aefaf5372a8840d955943dc53c',
    'bytes' => 219,
  ),
  'package-spine-nav-media-metadata.epub' =>
  array (
    'sha256' => '64981f08e5f4b2ae41baf55233e3cf4419c62c25d2606347bfedf0ee7e181a18',
    'bytes' => 2402,
  ),
  'package-spine-nav-media-metadata.native' =>
  array (
    'sha256' => '6d5be8a2ed05f750c291ce141c0110e2264605960ccaf89175de7cf6179fffbd',
    'bytes' => 993,
  ),
  'page-list-cfi-navigation.epub' =>
  array (
    'sha256' => '88feb1210f770ffa341c907fe0f1b9a68c88677abf28021849e73197695d0a8f',
    'bytes' => 1411,
  ),
  'page-list-cfi-navigation.native' =>
  array (
    'sha256' => '36bc594058d69b633756e9080b826be908e244b852647bf8a888e22c770b26d1',
    'bytes' => 327,
  ),
  'page-list-navigation.epub' =>
  array (
    'sha256' => '449c6114a473e2db1df8cf69cd29fddaef4a14a160b65fd7fe30adf0c80b9365',
    'bytes' => 1394,
  ),
  'page-list-navigation.native' =>
  array (
    'sha256' => 'f565404556ec3487d55c3610b56882cebc0662d85e3c1135cf4c05a971544cfa',
    'bytes' => 271,
  ),
  'parent-relative-nav.epub' =>
  array (
    'sha256' => 'caafa83c3b42b02d6aa25905f04b045df1a3db37913a636a296193cc4f8f27f6',
    'bytes' => 1652,
  ),
  'parent-relative-nav.native' =>
  array (
    'sha256' => 'fa48842bd1b89d8ba991dc5d577bb526f61bf89c7e8966f66c0929ca6d149a9e',
    'bytes' => 705,
  ),
  'raw-media-source-spine.epub' =>
  array (
    'sha256' => 'c778ca2f726f06d72ea86cf575f5ee3e9ff37e1443be4c0c2ce83d2d15959340',
    'bytes' => 1913,
  ),
  'raw-media-source-spine.native' =>
  array (
    'sha256' => '6d0e323c314c6cf0ef2e97f2c560cd46b222912aaedd37071580293e53f3f149',
    'bytes' => 616,
  ),
  'remote-manifest-resource.epub' =>
  array (
    'sha256' => 'aaf4a5557c55af341a6a2ed5950ccc5807ce529f6ae4ed4398336345b0646c7f',
    'bytes' => 1385,
  ),
  'remote-manifest-resource.native' =>
  array (
    'sha256' => 'a2b15395968495a5376a60e63ae21b0c0a079f02ee447c1ef2063ec87a613c13',
    'bytes' => 277,
  ),
  'rendition-layout-property.epub' =>
  array (
    'sha256' => 'abdbb293f94d979445600249a1162c0607a2fbcb73fc260d77d61334edef3671',
    'bytes' => 1390,
  ),
  'rendition-layout-property.native' =>
  array (
    'sha256' => '8b595c803ae40a3dedbbff2a9cb6632daf17916e8a72c3788beff91f12033855',
    'bytes' => 314,
  ),
  'scripted-svg-manifest.epub' =>
  array (
    'sha256' => '8845d9a35825bdf882b5d2239b60c1e7fd0f9589c8d06f5be74f0565fc56bb1b',
    'bytes' => 1577,
  ),
  'scripted-svg-manifest.native' =>
  array (
    'sha256' => 'c46fe3dd878f6709fc7dc4db9ce94b4f813924acd40de064614ac5a2eb90caa4',
    'bytes' => 276,
  ),
  'scripted-xhtml-resource.epub' =>
  array (
    'sha256' => '4600cb6c58330de0c0dc6e27deb73c41dae16a395c98ad0774fb3812323d77e5',
    'bytes' => 1556,
  ),
  'scripted-xhtml-resource.native' =>
  array (
    'sha256' => 'e84a35411c739bc6a1d8a54f122eff6fcb3e2552df597ee4c08c3ad178e654f4',
    'bytes' => 273,
  ),
  'spine-fallback-resource.epub' =>
  array (
    'sha256' => 'c042da479466e7353f063d986eb5481e49d2a6d9b93a8348576994f6ae3dbde6',
    'bytes' => 1661,
  ),
  'spine-fallback-resource.native' =>
  array (
    'sha256' => '56a094f8d97c055aeca928ad6d5162be7ca396ea1f869a2b29740aef3415baaa',
    'bytes' => 48,
  ),
  'spine-page-spread.epub' =>
  array (
    'sha256' => '47c48d493ff2846023ce78c1cb407d8025865ef7eb986c9f60607de4189bd5e1',
    'bytes' => 1562,
  ),
  'spine-page-spread.native' =>
  array (
    'sha256' => 'ecdae2b7e18be738e3530727e3d04f253fed3a6474091964d0b9c0c16c984dd9',
    'bytes' => 483,
  ),
  'standalone-footnote.epub' =>
  array (
    'sha256' => '5058fb925a59dadae5ac5e371f4907c5a192b074410d2c668b4e2b6ff483ab53',
    'bytes' => 1384,
  ),
  'standalone-footnote.native' =>
  array (
    'sha256' => '8ba2f5a23a13f1c6d0e309e3ba77ea8bb65702e5c166c38589d954fcc5026657',
    'bytes' => 431,
  ),
  'text-track-captions.epub' =>
  array (
    'sha256' => '2559039311ac1b9a25be74e4b4a7587cadc5579563a8d0ff1fb3b80503c30da5',
    'bytes' => 1812,
  ),
  'text-track-captions.native' =>
  array (
    'sha256' => 'e1f54a06e556fcd9a130357978b110a6931ed79f27af8424df8a861155b71eed',
    'bytes' => 568,
  ),
  'title-page-guide-media-metadata.epub' =>
  array (
    'sha256' => '9a21d071427572212113af33e11d1d39cd692ea840a81980dfaf471840d28dc7',
    'bytes' => 2801,
  ),
  'title-page-guide-media-metadata.native' =>
  array (
    'sha256' => '8f2c47bb97258bdf88a8cf1a8f8f398e42d2afa8bf2633ceda835785aefdf3d0',
    'bytes' => 747,
  ),
  'video-manifest-resource.epub' =>
  array (
    'sha256' => '7db258c0f96c66dc1de9eeaa1fc75ca5e9fddf821b6f0783cd4b74f4f59013b5',
    'bytes' => 1508,
  ),
  'video-manifest-resource.native' =>
  array (
    'sha256' => 'd71b066f5fc0e0a1bef32649948e217373159694ed179af794914b6732618f68',
    'bytes' => 275,
  ),
  'video-navigation.epub' =>
  array (
    'sha256' => '71bf3f39156a0911cd9b542aee3c45d88aabd608a9a268a4c4fe6a949f1956fe',
    'bytes' => 1505,
  ),
  'video-navigation.native' =>
  array (
    'sha256' => '0a7d0436add9426392a1a10b4d4b725848931a4b7f49fdf6c8acea5e86f14241',
    'bytes' => 262,
  ),
  'wasteland.epub' =>
  array (
    'sha256' => '151ec5dbca33e39a4e3f6894e92fa5a101290bdeaaa792e0700595971456a278',
    'bytes' => 25840,
  ),
  'wasteland.native' =>
  array (
    'sha256' => 'c000ec1960f46c87039eef9cf256fd8dcaeb7a739dfe335d093d50174f2b1efd',
    'bytes' => 139698,
  ),
  'xhtml-address-spine.epub' =>
  array (
    'sha256' => '0c0587cc7ada8eeaf5fe7d544597696deee1a92a489aa9d4574be2ed2f85ddf5',
    'bytes' => 1460,
  ),
  'xhtml-address-spine.native' =>
  array (
    'sha256' => '9685f832a8124ad9cf13b68083218e1a5e6ac2b448edee388b4c6bfcff08dbb8',
    'bytes' => 636,
  ),
  'xhtml-chapter-section-spine.epub' =>
  array (
    'sha256' => 'f099215e3cb9b631b00704eff46c6a44d49383ec6008b12867ad5f8007c55ba5',
    'bytes' => 1453,
  ),
  'xhtml-chapter-section-spine.native' =>
  array (
    'sha256' => '67998b223dd1848852a2545e74c2b41175c0e4e611ee83fc7a50f86f5b944dfc',
    'bytes' => 477,
  ),
  'xhtml-del-edit-mark-spine.epub' =>
  array (
    'sha256' => 'd671033f47969f9de481b2ff9a7a0effa55cc69869868215e9206be947cc7f39',
    'bytes' => 1408,
  ),
  'xhtml-del-edit-mark-spine.native' =>
  array (
    'sha256' => '14299e1d878ef72e19b86795da7d53cef6e42765ba831d13c4c6751891fb3fb6',
    'bytes' => 405,
  ),
  'xhtml-details-summary-spine.epub' =>
  array (
    'sha256' => '8742d0b94103c01e4f2ebe6fdf6b2efb183c138c409268352a225cc9b67c51e5',
    'bytes' => 1457,
  ),
  'xhtml-details-summary-spine.native' =>
  array (
    'sha256' => 'fd0736c6261a6fae6c18f1aff8ea2169fdfe05a558c3b4e6a533dc918f00efce',
    'bytes' => 645,
  ),
  'xhtml-kbd-samp-var-spine.epub' =>
  array (
    'sha256' => '7869f24ed5d068397dc203ea5ffbd8975ca2976d4b722005676a06bd05cc4437',
    'bytes' => 1431,
  ),
  'xhtml-kbd-samp-var-spine.native' =>
  array (
    'sha256' => '61d9b29480004ca9347c1f427ffb2abf758ca44bdf951825f6c51fbc371185e0',
    'bytes' => 571,
  ),
  'xhtml-ruby-table-mark.epub' =>
  array (
    'sha256' => '19e2ed10e4aeafe94970c38606939b9dfbd561f15c7f71e4ee904425f9b13b4d',
    'bytes' => 1876,
  ),
  'xhtml-ruby-table-mark.native' =>
  array (
    'sha256' => 'ec35ac3bda86e5242aa9ceb8b5614be45f689b643c2620508687f55daa68a4b8',
    'bytes' => 2302,
  ),
  'xhtml-semantics-spine.epub' =>
  array (
    'sha256' => 'd2a4df3e7287b534b0ad1685d8f241940dd728fa3541ae1d14924506f7544452',
    'bytes' => 1893,
  ),
  'xhtml-semantics-spine.native' =>
  array (
    'sha256' => 'd2e7da70eb00cd5172cc2382532b972a62d9ef9fc1e4c107aa3c504fa2367fa2',
    'bytes' => 3228,
  ),
);

    /**
     * @var array<string, mixed>
     */
    private const CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE = array (
  'kind' => 'epub-package-feature-coverage',
  'fixtureCount' => 76,
  'opfPartNameCounts' =>
  array (
    '/EPUB/package.opf' => 59,
    '/EPUB/wasteland.opf' => 1,
    '/OEBPS/content.opf' => 3,
    '/OPS/book/package.opf' => 4,
    '/OPS/package.opf' => 9,
  ),
  'metadataLanguageCounts' =>
  array (
    'de-DE' => 3,
    'en' => 68,
    'en-GB' => 1,
    'en-US' => 3,
    'fr' => 1,
  ),
  'fixturesWithCreators' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'bindings-collections-sidecars',
    2 => 'blockquote-list-spine',
    3 => 'code-block-spine',
    4 => 'content-image-nav-media',
    5 => 'cross-spine-internal-links',
    6 => 'definition-list-spine',
    7 => 'duplicate-spine-idref',
    8 => 'epub2_cover',
    9 => 'epub2_no_cover',
    10 => 'epub2_picture',
    11 => 'epub3-ncx-toc-fallback',
    12 => 'external-footnote-reference',
    13 => 'features',
    14 => 'figure-caption-spine',
    15 => 'formatting',
    16 => 'img',
    17 => 'img_no_cover',
    18 => 'inline-abbr-subscript-superscript',
    19 => 'language-french-metadata',
    20 => 'main-section-spine',
    21 => 'manifest-fallback-style',
    22 => 'manifest-href-encoding',
    23 => 'mathml-spine',
    24 => 'measurement-inline-spine',
    25 => 'media-manifest-mix',
    26 => 'media-overlay-invalid-clips',
    27 => 'metadata-link-page-list-image',
    28 => 'metadata-link-vocab-diagnostics',
    29 => 'metadata-record-remote-nav',
    30 => 'metadata-search-link-semantics',
    31 => 'missing-media-overlay',
    32 => 'multi-rootfile-nested-nav',
    33 => 'nested-path-media-metadata',
    34 => 'nested-rootfile-nonlinear-spine',
    35 => 'package-spine-nav-media-metadata',
    36 => 'parent-relative-nav',
    37 => 'raw-media-source-spine',
    38 => 'spine-fallback-resource',
    39 => 'text-track-captions',
    40 => 'title-page-guide-media-metadata',
    41 => 'wasteland',
    42 => 'xhtml-address-spine',
    43 => 'xhtml-chapter-section-spine',
    44 => 'xhtml-details-summary-spine',
    45 => 'xhtml-kbd-samp-var-spine',
    46 => 'xhtml-ruby-table-mark',
    47 => 'xhtml-semantics-spine',
  ),
  'navigationTypeCounts' =>
  array (
    'nav' => 68,
    'ncx' => 4,
  ),
  'spineLinearStateCounts' =>
  array (
    'linear' => 92,
    'non-linear' => 16,
  ),
  'spinePageSpreadPlacementCounts' =>
  array (
    'left' => 2,
    'right' => 4,
  ),
  'manifestMediaTypeCounts' =>
  array (
    'application/javascript' => 1,
    'application/json' => 6,
    'application/ld+json' => 2,
    'application/octet-stream' => 1,
    'application/pdf' => 1,
    'application/smil+xml' => 2,
    'application/x-bound-widget' => 1,
    'application/x-dtbncx+xml' => 6,
    'application/x-fallback-demo' => 3,
    'application/xhtml+xml' => 172,
    'audio/mpeg' => 5,
    'font/woff2' => 1,
    'image/gif' => 5,
    'image/jpeg' => 7,
    'image/png' => 13,
    'image/svg+xml' => 2,
    'text/css' => 25,
    'text/vtt' => 2,
    'video/mp4' => 5,
  ),
  'manifestPropertyCounts' =>
  array (
    'accessibility-metadata' => 1,
    'cover-image' => 4,
    'mathml' => 3,
    'nav' => 68,
    'remote-resources' => 4,
    'rendition:layout-pre-paginated' => 1,
    'scripted' => 2,
    'svg' => 3,
    'switch' => 1,
  ),
  'manifestResourceKindCounts' =>
  array (
    'asset' => 14,
    'audio' => 5,
    'cover-image' => 4,
    'font' => 1,
    'image' => 21,
    'media-overlay' => 2,
    'navigation' => 74,
    'script' => 1,
    'style' => 25,
    'svg' => 2,
    'text-track' => 2,
    'video' => 5,
    'xhtml' => 104,
  ),
  'navigationSectionTypes' =>
  array (
    0 => 'appendix',
    1 => 'landmarks',
    2 => 'loa',
    3 => 'loi',
    4 => 'lot',
    5 => 'lov',
    6 => 'page-list',
    7 => 'toc',
  ),
  'guideReferenceTypeCounts' =>
  array (
    'appendix' => 1,
    'bibliography' => 1,
    'cover' => 3,
    'glossary' => 1,
    'index' => 1,
    'notes' => 1,
    'preface' => 1,
    'text' => 12,
    'title-page' => 1,
    'toc' => 1,
  ),
  'packageLinkRelCounts' =>
  array (
    'accessibility-summary' => 1,
    'alternate' => 2,
    'bad/token' => 1,
    'cc:attributionURL' => 1,
    'cc:license' => 2,
    'https://example.invalid/link-rel#review' => 1,
    'preview' => 3,
    'record' => 11,
    'schema:associatedMedia' => 1,
    'search' => 1,
    'unknown:missing' => 1,
  ),
  'packageLinkVocabularyRelCounts' =>
  array (
    'accessibility-summary' => 1,
    'alternate' => 2,
    'bad/token' => 1,
    'cc:attributionURL' => 1,
    'cc:license' => 2,
    'https://example.invalid/link-rel#review' => 1,
    'preview' => 3,
    'record' => 11,
    'schema:associatedMedia' => 1,
    'search' => 1,
    'unknown:missing' => 1,
  ),
  'packageLinkVocabularyPropertyCounts' =>
  array (
    'accessibility-metadata' => 1,
    'bad/property' => 1,
    'https://example.invalid/props#review' => 1,
    'review:packet' => 1,
    'schema-org' => 2,
    'unknown:flag' => 1,
  ),
  'packageLinkMediaTypeCounts' =>
  array (
    'application/json' => 8,
    'application/ld+json' => 2,
    'application/opensearchdescription+xml' => 1,
    'text/html' => 1,
  ),
  'packageLinkMediaTypeParameterNameCounts' =>
  array (
    'profile' => 1,
  ),
  'linkHrefSuffixSourceCounts' =>
  array (
    'collection-link' => 2,
    'package-link' => 1,
  ),
  'accessibilityPropertyCounts' =>
  array (
    'accessMode' => 2,
    'accessibilityFeature' => 2,
    'accessibilityHazard' => 1,
    'accessibilitySummary' => 1,
    'conformsTo' => 1,
  ),
  'encryptionRoleCounts' =>
  array (
    'font' => 3,
  ),
  'collectionRoleCounts' =>
  array (
    'index' => 1,
    'role:primary' => 1,
    'schema:hasPart' => 1,
  ),
  'collectionLinkRelCounts' =>
  array (
    'contents' => 1,
    'index' => 1,
    'record' => 1,
  ),
  'bindingMediaTypeCounts' =>
  array (
    'application/x-bound-widget' => 1,
  ),
  'ocfSidecarKindCounts' =>
  array (
    'manifest' => 1,
    'metadata' => 1,
    'rights' => 1,
    'signatures' => 1,
  ),
  'fixtureFeatureSignatures' =>
  array (
    'accessibility-metadata-package' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'accessibility-summary' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'accessibility-summary' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyPropertyCounts' =>
      array (
        'accessibility-metadata' => 1,
      ),
      'accessibilityPropertyCounts' =>
      array (
        'accessMode' => 2,
        'accessibilityFeature' => 1,
        'accessibilityHazard' => 1,
        'accessibilitySummary' => 1,
        'conformsTo' => 1,
      ),
      'accessibilityLinkedRecordCount' => 1,
      'coverImagePartPresent' => false,
    ),
    'all-nonlinear-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'appendix-navigation-guide' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'appendix',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'appendix' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'audio-navigation' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'loa',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'audio' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'auxiliary-lot-guide-index' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'lot',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'index' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'bindings-collections-sidecars' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'blockquote-list-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'code-block-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'content-image-nav-media' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'audio' => 1,
        'image' => 2,
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'cross-spine-internal-links' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'definition-list-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'direct-image-spine' =>
    array (
      'navigationType' => '',
      'navigationSectionTypes' =>
      array (
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'direct-svg-spine' =>
    array (
      'navigationType' => '',
      'navigationSectionTypes' =>
      array (
      ),
      'manifestResourceKindCounts' =>
      array (
        'svg' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'duplicate-spine-idref' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'encoded-image-media-bag' =>
    array (
      'navigationType' => '',
      'navigationSectionTypes' =>
      array (
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'epub2_cover' =>
    array (
      'navigationType' => 'ncx',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'cover' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => true,
    ),
    'epub2_no_cover' =>
    array (
      'navigationType' => 'ncx',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'toc' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'epub2_picture' =>
    array (
      'navigationType' => 'ncx',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'cover' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => true,
    ),
    'epub3-ncx-toc-fallback' =>
    array (
      'navigationType' => 'ncx',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'external-footnote-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'features' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 2,
        'xhtml' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'figure-caption-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'font-manifest-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'font' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'formatting' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'style' => 2,
        'xhtml' => 7,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'fragment-nav-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'guide-bibliography-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'bibliography' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'guide-glossary-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'glossary' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'guide-notes-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'notes' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'guide-preface-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'preface' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'img' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'cover-image' => 1,
        'image' => 3,
        'navigation' => 1,
        'style' => 2,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => true,
    ),
    'img_no_cover' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 3,
        'navigation' => 1,
        'style' => 2,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'inline-abbr-subscript-superscript' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'language-french-metadata' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'main-section-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'manifest-fallback-chain' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'manifest-fallback-style' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'manifest-href-encoding' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'mathml-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'measurement-inline-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'media-manifest-mix' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 2,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'media-overlay-invalid-clips' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'audio' => 1,
        'media-overlay' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'media-overlay-package' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'audio' => 1,
        'media-overlay' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'metadata-link-page-list-image' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'page-list',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'alternate' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'alternate' => 1,
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'metadata-link-vocab-diagnostics' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'cover-image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'bad/token' => 1,
        'https://example.invalid/link-rel#review' => 1,
        'record' => 2,
        'schema:associatedMedia' => 1,
        'unknown:missing' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'bad/token' => 1,
        'https://example.invalid/link-rel#review' => 1,
        'record' => 2,
        'schema:associatedMedia' => 1,
        'unknown:missing' => 1,
      ),
      'packageLinkVocabularyPropertyCounts' =>
      array (
        'bad/property' => 1,
        'https://example.invalid/props#review' => 1,
        'review:packet' => 1,
        'schema-org' => 2,
        'unknown:flag' => 1,
      ),
      'packageLinkVocabularyDiagnosticCount' => 6,
      'coverImagePartPresent' => true,
    ),
    'metadata-record-remote-nav' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'loi',
        2 => 'page-list',
        3 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'alternate' => 1,
        'preview' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'alternate' => 1,
        'preview' => 1,
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'metadata-search-link-semantics' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
        'search' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
        'search' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'missing-local-manifest-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'missing-media-overlay' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'multi-rootfile-nested-nav' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'nav-ncx-linear-guide' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 2,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'nested-path-media-metadata' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'lot',
        2 => 'page-list',
        3 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'audio' => 1,
        'cover-image' => 1,
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'cover' => 1,
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
      ),
      'coverImagePartPresent' => true,
    ),
    'nested-rootfile-nonlinear-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'package-spine-nav-media-metadata' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'page-list-cfi-navigation' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'page-list',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'pageListCfiTargetCount' => 2,
      'coverImagePartPresent' => false,
    ),
    'page-list-navigation' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'loi',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'parent-relative-nav' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'raw-media-source-spine' =>
    array (
      'navigationType' => '',
      'navigationSectionTypes' =>
      array (
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'text-track' => 1,
        'video' => 2,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'remote-manifest-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'rendition-layout-property' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'scripted-svg-manifest' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'svg' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'scripted-xhtml-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'script' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'spine-fallback-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'spine-page-spread' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'standalone-footnote' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'text-track-captions' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'text-track' => 1,
        'video' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'title-page-guide-media-metadata' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'loa',
        2 => 'page-list',
        3 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'title-page' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'preview' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'preview' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'video-manifest-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'video' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'video-navigation' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'lov',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'video' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'wasteland' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'cover-image' => 1,
        'navigation' => 2,
        'style' => 2,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'cc:attributionURL' => 1,
        'cc:license' => 2,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'cc:attributionURL' => 1,
        'cc:license' => 2,
      ),
      'coverImagePartPresent' => true,
    ),
    'xhtml-address-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-chapter-section-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-del-edit-mark-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-details-summary-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-kbd-samp-var-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-ruby-table-mark' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'preview' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'preview' => 1,
        'record' => 1,
      ),
      'accessibilityPropertyCounts' =>
      array (
        'accessibilityFeature' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-semantics-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
  ),
  'fixturesWithGuideReferences' =>
  array (
    0 => 'appendix-navigation-guide',
    1 => 'auxiliary-lot-guide-index',
    2 => 'epub2_cover',
    3 => 'epub2_no_cover',
    4 => 'epub2_picture',
    5 => 'epub3-ncx-toc-fallback',
    6 => 'guide-bibliography-reference',
    7 => 'guide-glossary-reference',
    8 => 'guide-notes-reference',
    9 => 'guide-preface-reference',
    10 => 'manifest-fallback-style',
    11 => 'manifest-href-encoding',
    12 => 'mathml-spine',
    13 => 'metadata-record-remote-nav',
    14 => 'multi-rootfile-nested-nav',
    15 => 'nav-ncx-linear-guide',
    16 => 'nested-path-media-metadata',
    17 => 'nested-rootfile-nonlinear-spine',
    18 => 'parent-relative-nav',
    19 => 'title-page-guide-media-metadata',
    20 => 'xhtml-ruby-table-mark',
    21 => 'xhtml-semantics-spine',
  ),
  'fixturesWithPackageLinks' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'bindings-collections-sidecars',
    2 => 'manifest-href-encoding',
    3 => 'metadata-link-page-list-image',
    4 => 'metadata-link-vocab-diagnostics',
    5 => 'metadata-record-remote-nav',
    6 => 'metadata-search-link-semantics',
    7 => 'nav-ncx-linear-guide',
    8 => 'nested-path-media-metadata',
    9 => 'title-page-guide-media-metadata',
    10 => 'wasteland',
    11 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithPackageLinkVocabulary' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'bindings-collections-sidecars',
    2 => 'manifest-href-encoding',
    3 => 'metadata-link-page-list-image',
    4 => 'metadata-link-vocab-diagnostics',
    5 => 'metadata-record-remote-nav',
    6 => 'metadata-search-link-semantics',
    7 => 'nav-ncx-linear-guide',
    8 => 'nested-path-media-metadata',
    9 => 'title-page-guide-media-metadata',
    10 => 'wasteland',
    11 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithPackageLinkVocabularyDiagnostics' =>
  array (
    0 => 'metadata-link-vocab-diagnostics',
  ),
  'fixturesWithPackageLinkMediaTypeParameters' =>
  array (
    0 => 'metadata-search-link-semantics',
  ),
  'fixturesWithLinkHrefSuffixes' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'metadata-search-link-semantics',
  ),
  'fixturesWithAccessibilityMetadata' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithCoverImagePart' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_picture',
    2 => 'img',
    3 => 'metadata-link-vocab-diagnostics',
    4 => 'nested-path-media-metadata',
    5 => 'wasteland',
  ),
  'fixturesWithEncryption' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_no_cover',
    2 => 'epub2_picture',
  ),
  'fixturesWithObfuscatedFonts' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_no_cover',
    2 => 'epub2_picture',
  ),
  'fixturesWithBlockedEncryptedByteExposures' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_no_cover',
    2 => 'epub2_picture',
  ),
  'fixturesWithImages' =>
  array (
    0 => 'content-image-nav-media',
    1 => 'direct-image-spine',
    2 => 'direct-svg-spine',
    3 => 'encoded-image-media-bag',
    4 => 'epub2_cover',
    5 => 'epub2_picture',
    6 => 'figure-caption-spine',
    7 => 'formatting',
    8 => 'img',
    9 => 'img_no_cover',
    10 => 'metadata-link-page-list-image',
    11 => 'metadata-link-vocab-diagnostics',
    12 => 'nested-path-media-metadata',
    13 => 'package-spine-nav-media-metadata',
    14 => 'raw-media-source-spine',
    15 => 'scripted-svg-manifest',
    16 => 'title-page-guide-media-metadata',
    17 => 'wasteland',
  ),
  'fixturesWithStylesheets' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_no_cover',
    2 => 'epub2_picture',
    3 => 'features',
    4 => 'formatting',
    5 => 'img',
    6 => 'img_no_cover',
    7 => 'manifest-fallback-style',
    8 => 'manifest-href-encoding',
    9 => 'metadata-link-vocab-diagnostics',
    10 => 'missing-local-manifest-resource',
    11 => 'nested-path-media-metadata',
    12 => 'nested-rootfile-nonlinear-spine',
    13 => 'package-spine-nav-media-metadata',
    14 => 'title-page-guide-media-metadata',
    15 => 'wasteland',
    16 => 'xhtml-semantics-spine',
  ),
  'fixturesWithLandmarks' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'content-image-nav-media',
    2 => 'external-footnote-reference',
    3 => 'features',
    4 => 'formatting',
    5 => 'img',
    6 => 'img_no_cover',
    7 => 'manifest-href-encoding',
    8 => 'metadata-record-remote-nav',
    9 => 'multi-rootfile-nested-nav',
    10 => 'nav-ncx-linear-guide',
    11 => 'nested-path-media-metadata',
    12 => 'nested-rootfile-nonlinear-spine',
    13 => 'package-spine-nav-media-metadata',
    14 => 'parent-relative-nav',
    15 => 'spine-fallback-resource',
    16 => 'title-page-guide-media-metadata',
    17 => 'wasteland',
    18 => 'xhtml-ruby-table-mark',
    19 => 'xhtml-semantics-spine',
  ),
  'fixturesWithPageLists' =>
  array (
    0 => 'content-image-nav-media',
    1 => 'manifest-href-encoding',
    2 => 'metadata-link-page-list-image',
    3 => 'metadata-record-remote-nav',
    4 => 'multi-rootfile-nested-nav',
    5 => 'nested-path-media-metadata',
    6 => 'package-spine-nav-media-metadata',
    7 => 'page-list-cfi-navigation',
    8 => 'page-list-navigation',
    9 => 'spine-fallback-resource',
    10 => 'title-page-guide-media-metadata',
  ),
  'fixturesWithPageListCfiTargets' =>
  array (
    0 => 'page-list-cfi-navigation',
  ),
  'fixturesWithAuxiliaryNavigation' =>
  array (
    0 => 'appendix-navigation-guide',
    1 => 'audio-navigation',
    2 => 'auxiliary-lot-guide-index',
    3 => 'metadata-record-remote-nav',
    4 => 'nested-path-media-metadata',
    5 => 'page-list-navigation',
    6 => 'title-page-guide-media-metadata',
    7 => 'video-navigation',
  ),
  'fixturesWithRemoteManifestResources' =>
  array (
    0 => 'media-manifest-mix',
    1 => 'metadata-record-remote-nav',
    2 => 'nested-path-media-metadata',
    3 => 'remote-manifest-resource',
  ),
  'fixturesWithExternalManifestItems' =>
  array (
    0 => 'media-manifest-mix',
    1 => 'metadata-record-remote-nav',
    2 => 'nested-path-media-metadata',
    3 => 'remote-manifest-resource',
  ),
  'fixturesWithMissingLocalManifestItems' =>
  array (
    0 => 'missing-local-manifest-resource',
  ),
  'fixturesWithManifestFallbackItems' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'bindings-collections-sidecars',
    2 => 'manifest-fallback-chain',
    3 => 'manifest-fallback-style',
    4 => 'manifest-href-encoding',
    5 => 'media-manifest-mix',
    6 => 'metadata-link-vocab-diagnostics',
    7 => 'metadata-record-remote-nav',
    8 => 'nav-ncx-linear-guide',
    9 => 'nested-path-media-metadata',
    10 => 'raw-media-source-spine',
    11 => 'spine-fallback-resource',
    12 => 'text-track-captions',
    13 => 'title-page-guide-media-metadata',
    14 => 'video-manifest-resource',
    15 => 'video-navigation',
    16 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithManifestFallbacks' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'manifest-fallback-chain',
    2 => 'manifest-fallback-style',
    3 => 'media-manifest-mix',
    4 => 'spine-fallback-resource',
  ),
  'fixturesWithResolvedManifestFallbacks' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'manifest-fallback-chain',
    2 => 'manifest-fallback-style',
    3 => 'media-manifest-mix',
    4 => 'spine-fallback-resource',
  ),
  'fixturesWithUsableManifestFallbacks' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'manifest-fallback-chain',
    2 => 'manifest-fallback-style',
    3 => 'media-manifest-mix',
    4 => 'spine-fallback-resource',
  ),
  'fixturesWithMissingManifestFallbacks' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'manifest-href-encoding',
    2 => 'metadata-link-vocab-diagnostics',
    3 => 'metadata-record-remote-nav',
    4 => 'nav-ncx-linear-guide',
    5 => 'nested-path-media-metadata',
    6 => 'raw-media-source-spine',
    7 => 'text-track-captions',
    8 => 'title-page-guide-media-metadata',
    9 => 'video-manifest-resource',
    10 => 'video-navigation',
    11 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithMediaOverlays' =>
  array (
    0 => 'media-overlay-invalid-clips',
    1 => 'media-overlay-package',
    2 => 'missing-media-overlay',
  ),
  'fixturesWithResolvedMediaOverlays' =>
  array (
    0 => 'media-overlay-invalid-clips',
    1 => 'media-overlay-package',
  ),
  'fixturesWithMediaOverlayTextTargets' =>
  array (
    0 => 'media-overlay-invalid-clips',
    1 => 'media-overlay-package',
  ),
  'fixturesWithMediaOverlayAudioTargets' =>
  array (
    0 => 'media-overlay-invalid-clips',
    1 => 'media-overlay-package',
  ),
  'fixturesWithNonLinearSpineItems' =>
  array (
    0 => 'all-nonlinear-spine',
    1 => 'content-image-nav-media',
    2 => 'epub2_cover',
    3 => 'epub2_picture',
    4 => 'external-footnote-reference',
    5 => 'features',
    6 => 'formatting',
    7 => 'img',
    8 => 'img_no_cover',
    9 => 'manifest-href-encoding',
    10 => 'metadata-link-vocab-diagnostics',
    11 => 'multi-rootfile-nested-nav',
    12 => 'nav-ncx-linear-guide',
    13 => 'nested-path-media-metadata',
    14 => 'nested-rootfile-nonlinear-spine',
    15 => 'title-page-guide-media-metadata',
  ),
  'fixturesWithSpinePageSpreadItems' =>
  array (
    0 => 'metadata-link-vocab-diagnostics',
    1 => 'nested-path-media-metadata',
    2 => 'spine-page-spread',
    3 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithCollections' =>
  array (
    0 => 'bindings-collections-sidecars',
  ),
  'fixturesWithBindings' =>
  array (
    0 => 'bindings-collections-sidecars',
  ),
  'fixturesWithOcfSidecars' =>
  array (
    0 => 'bindings-collections-sidecars',
  ),
  'totals' =>
  array (
    'metadataCreators' => 68,
    'manifestItems' => 260,
    'readingOrderItems' => 108,
    'spinePageSpreadItems' => 6,
    'xhtmlAssets' => 172,
    'imageAssets' => 27,
    'stylesheetAssets' => 22,
    'navigationEntries' => 165,
    'landmarkEntries' => 24,
    'pageListEntries' => 16,
    'pageListCfiTargets' => 2,
    'auxiliaryNavigationEntries' => 8,
    'packageLinks' => 15,
    'packageLinkVocabularyRelTokens' => 25,
    'packageLinkVocabularyPropertyTokens' => 7,
    'packageLinkVocabularyResolvedTokens' => 5,
    'packageLinkVocabularyAbsoluteUrlTokens' => 2,
    'packageLinkVocabularyDuplicateTokens' => 2,
    'packageLinkVocabularyDiagnostics' => 6,
    'packageLinkMediaTypeItems' => 12,
    'packageLinkMediaTypeParameters' => 1,
    'linkHrefSuffixes' => 3,
    'linkHrefSuffixQueries' => 1,
    'linkHrefSuffixFragments' => 3,
    'guideReferences' => 23,
    'accessibilityEntries' => 7,
    'accessibilityLinkedRecords' => 1,
    'accessibilityAccessModes' => 2,
    'accessibilityFeatures' => 2,
    'accessibilityHazards' => 1,
    'accessibilityConformsTo' => 1,
    'remoteResourceManifestItems' => 4,
    'externalManifestItems' => 4,
    'missingLocalManifestItems' => 1,
    'manifestFallbackItems' => 19,
    'manifestFallbacks' => 6,
    'resolvedManifestFallbacks' => 6,
    'usableManifestFallbacks' => 6,
    'missingManifestFallbacks' => 13,
    'mediaOverlays' => 3,
    'resolvedMediaOverlays' => 2,
    'missingMediaOverlays' => 1,
    'mediaOverlayReferencedContentItems' => 3,
    'mediaOverlayTimelineItems' => 3,
    'mediaOverlayClipTimings' => 3,
    'mediaOverlayValidClipTimings' => 2,
    'mediaOverlayInvalidClipTimings' => 1,
    'mediaOverlayTextLocalTargets' => 3,
    'mediaOverlayAudioLocalTargets' => 2,
    'mediaOverlayDurations' => 5,
    'encryptionItems' => 3,
    'obfuscatedFonts' => 3,
    'blockedEncryptedByteExposures' => 3,
    'encryptionDiagnostics' => 6,
    'collections' => 2,
    'collectionLinks' => 3,
    'bindingItems' => 1,
    'bindingResolvedHandlers' => 1,
    'bindingMediaTypeParameters' => 1,
    'ocfSidecars' => 4,
  ),
);

    public function run(string $epubDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));
        $repoRoot = is_string($options['repoRoot'] ?? null) && $options['repoRoot'] !== ''
            ? rtrim((string) $options['repoRoot'], DIRECTORY_SEPARATOR)
            : (getcwd() ?: '');
        $runnerResultArtifact = is_string($options['runnerResultArtifact'] ?? null)
            ? (string) $options['runnerResultArtifact']
            : null;
        if ($runnerResultArtifact === '') {
            throw new \InvalidArgumentException('Runner result artifact must not be empty');
        }

        if (!is_dir($epubDirectory)) {
            return $this->skippedReport($epubDirectory, 'upstream-cache-missing');
        }

        $fixtureIdentity = $this->fixtureIdentity($epubDirectory);
        $epubFiles = $this->filesByBasename($epubDirectory, 'epub');
        $nativeFiles = $this->filesByBasename($epubDirectory, 'native');
        $epubNames = array_keys($epubFiles);
        sort($epubNames, SORT_STRING);
        $pairNames = array_values(array_intersect($epubNames, array_keys($nativeFiles)));
        sort($pairNames, SORT_STRING);

        $totalEpubCount = count($epubNames);
        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $epubNames = array_slice($epubNames, 0, $limit);
            $pairNames = array_values(array_intersect($pairNames, $epubNames));
        }

        $packageParsedCount = 0;
        $readerParsedCount = 0;
        $packageParseFailures = [];
        $readerParseFailures = [];
        $packageSummaries = [];
        $packageFeatureSummaries = [];
        $readerDocuments = [];
        $categoryCounts = [];

        foreach ($epubNames as $epubName) {
            $path = $epubFiles[$epubName];
            $packageResult = $this->readPackage($path);
            if ($packageResult['ok']) {
                ++$packageParsedCount;
                /** @var EpubPackage $package */
                $package = $packageResult['package'];
                $summary = $this->packageSummary($epubName, $package);
                $packageFeatureSummaries[] = $summary;
                if (count($packageSummaries) < $maxExamples) {
                    $packageSummaries[] = $summary;
                }
            } else {
                $packageParseFailures[] = [
                    'fixture' => $epubName,
                    'error' => $packageResult['error'],
                ];
                $this->addCategory($categoryCounts, 'package-parse-failure', $epubName, $maxExamples);
            }

            $readerResult = $this->readEpub($path);
            if ($readerResult['ok']) {
                ++$readerParsedCount;
                $readerDocuments[$epubName] = $readerResult['document'];
            } else {
                $readerParseFailures[] = [
                    'fixture' => $epubName,
                    'error' => $readerResult['error'],
                ];
                $this->addCategory($categoryCounts, 'reader-parse-failure', $epubName, $maxExamples);
            }
        }

        $epubPairParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $normalizedAstMatchCount = 0;
        $nativeParseFailures = [];
        $astParseFailures = [];
        $mismatches = [];
        $nativeAstSignatureFixtures = [];
        $nativeAstSignaturePayloadFixtures = [];

        foreach ($pairNames as $pairName) {
            $epubDocument = $readerDocuments[$pairName] ?? null;
            if ($epubDocument instanceof AstNode) {
                ++$epubPairParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            } else {
                $nativeParseFailures[] = [
                    'fixture' => $pairName,
                    'error' => $nativeResult['error'],
                ];
                $this->addCategory($categoryCounts, 'native-parse-failure', $pairName, $maxExamples);
            }

            if (!$epubDocument instanceof AstNode || !$nativeResult['ok']) {
                $astParseFailures[] = [
                    'fixture' => $pairName,
                    'epubError' => $epubDocument instanceof AstNode ? null : 'EPUB reader did not parse fixture',
                    'nativeError' => $nativeResult['error'],
                ];
                continue;
            }

            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $epubAst = $this->normalizedNode($epubDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            $epubAstSha256 = hash('sha256', self::canonicalJson($epubAst));
            $nativeAstSha256 = hash('sha256', self::canonicalJson($nativeAst));
            $normalizedAstMatches = $epubAst === $nativeAst;
            $nativeAstSignatureFixtures[$pairName] = [
                'fixture' => $pairName,
                'epubNormalizedAstSha256' => $epubAstSha256,
                'nativeNormalizedAstSha256' => $nativeAstSha256,
                'normalizedAstMatches' => $normalizedAstMatches,
                'epubTopTypes' => $this->topTypeSequence($epubDocument),
                'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
            ];
            $nativeAstSignaturePayloadFixtures[$pairName] = [
                'fixture' => $pairName,
                'epubNormalizedAst' => $epubAst,
                'nativeNormalizedAst' => $nativeAst,
            ];
            if ($normalizedAstMatches) {
                ++$normalizedAstMatchCount;
                continue;
            }

            $difference = $this->firstDifference($epubAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }
            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'epubTopTypes' => $this->topTypeSequence($epubDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
        }

        ksort($categoryCounts);
        $comparedEpubCount = count($epubNames);
        $comparedPairCount = count($pairNames);
        $packageParseFailureCount = count($packageParseFailures);
        $readerParseFailureCount = count($readerParseFailures);
        $astParseFailureCount = count($astParseFailures);
        $normalizedAstMismatchCount = $bothParsedCount - $normalizedAstMatchCount;

        $packageFeatureCoverage = self::packageFeatureCoverage($packageFeatureSummaries);
        $currentNativeAstSignature = self::currentNativeAstSignature(
            $fixtureIdentity,
            $nativeAstSignatureFixtures,
            $nativeAstSignaturePayloadFixtures,
            $comparedPairCount,
            $astParseFailureCount,
            $normalizedAstMismatchCount
        );
        $runnerEvidence = $runnerResultArtifact === null
            ? self::runnerNotRunEvidence()
            : $this->runnerResultArtifactEvidence($runnerResultArtifact, $repoRoot);
        $runnerResultCovered = self::runnerResultArtifactEvidenceIsValid($runnerEvidence);

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-native-ast-package',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-native-ast-package-comparison',
            'upstreamEpubDirectory' => $epubDirectory,
            'fixtureIdentity' => $fixtureIdentity,
            'packageFeatureCoverage' => $packageFeatureCoverage,
            'packageFeatureSignature' => self::packageFeatureSignature($fixtureIdentity, $packageFeatureCoverage),
            'currentNativeAstSignature' => $currentNativeAstSignature,
            'runnerEvidence' => $runnerEvidence,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalEpubCount' => $totalEpubCount,
            'comparedEpubCount' => $comparedEpubCount,
            'packageParsedCount' => $packageParsedCount,
            'readerParsedCount' => $readerParsedCount,
            'packageParseFailureCount' => $packageParseFailureCount,
            'readerParseFailureCount' => $readerParseFailureCount,
            'packageAcceptanceStatus' => self::packageAcceptanceStatus($comparedEpubCount, $packageParseFailureCount, $readerParseFailureCount),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'epubPairParsedCount' => $epubPairParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'astParseFailureCount' => $astParseFailureCount,
            'nativeParseFailureCount' => count($nativeParseFailures),
            'normalizedAstMatchCount' => $normalizedAstMatchCount,
            'normalizedAstMismatchCount' => $normalizedAstMismatchCount,
            'normalizedAstMatchPercent' => self::percent($normalizedAstMatchCount, $comparedPairCount),
            'astParityStatus' => self::astParityStatus($comparedPairCount, $astParseFailureCount, $normalizedAstMismatchCount),
            'packageSummaries' => $packageSummaries,
            'packageParseFailures' => array_slice($packageParseFailures, 0, $maxExamples),
            'readerParseFailures' => array_slice($readerParseFailures, 0, $maxExamples),
            'nativeParseFailures' => array_slice($nativeParseFailures, 0, $maxExamples),
            'astParseFailures' => array_slice($astParseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'mismatchCategories' => array_values($categoryCounts),
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                $comparedEpubCount,
                $packageParseFailureCount,
                $readerParseFailureCount,
                $comparedPairCount,
                $astParseFailureCount,
                $normalizedAstMatchCount,
                $normalizedAstMismatchCount,
                $runnerResultCovered
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc EPUB native/package comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamEpubDirectory=' . (string) ($report['upstreamEpubDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
            if ($runner !== []) {
                $lines[] = sprintf(
                    'runnerEvidence: status=%s plan=%s executed=%s',
                    (string) ($runner['status'] ?? 'unknown'),
                    (string) ($runner['commandPlanStatus'] ?? 'unknown'),
                    self::formatBooleanFlag($runner['executed'] ?? null)
                );
            }
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'packages: total=%d compared=%d packageParsed=%d readerParsed=%d packageFailures=%d readerFailures=%d status=%s',
            (int) ($report['totalEpubCount'] ?? 0),
            (int) ($report['comparedEpubCount'] ?? 0),
            (int) ($report['packageParsedCount'] ?? 0),
            (int) ($report['readerParsedCount'] ?? 0),
            (int) ($report['packageParseFailureCount'] ?? 0),
            (int) ($report['readerParseFailureCount'] ?? 0),
            (string) ($report['packageAcceptanceStatus'] ?? 'unknown')
        );
        $lines[] = sprintf(
            'nativePairs: total=%d compared=%d parsedBoth=%d parseFailures=%d nativeFailures=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['astParseFailureCount'] ?? 0),
            (int) ($report['nativeParseFailureCount'] ?? 0)
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown')
        );
        $fixtureIdentity = is_array($report['fixtureIdentity'] ?? null) ? $report['fixtureIdentity'] : [];
        $fixtureValidation = is_array($fixtureIdentity['validation'] ?? null) ? $fixtureIdentity['validation'] : [];
        if ($fixtureIdentity !== []) {
            $lines[] = sprintf(
                'fixtureIdentity: status=%s expected=%d observed=%d',
                (string) ($fixtureValidation['status'] ?? 'unknown'),
                (int) ($fixtureIdentity['expectedFileCount'] ?? 0),
                (int) ($fixtureIdentity['observedFileCount'] ?? 0)
            );
        }
        $featureCoverage = is_array($report['packageFeatureCoverage'] ?? null) ? $report['packageFeatureCoverage'] : [];
        if ($featureCoverage !== []) {
            $navigationTypeCounts = is_array($featureCoverage['navigationTypeCounts'] ?? null)
                ? $featureCoverage['navigationTypeCounts']
                : [];
            $totals = is_array($featureCoverage['totals'] ?? null) ? $featureCoverage['totals'] : [];
            $coverFixtures = is_array($featureCoverage['fixturesWithCoverImagePart'] ?? null)
                ? $featureCoverage['fixturesWithCoverImagePart']
                : [];
            $landmarkFixtures = is_array($featureCoverage['fixturesWithLandmarks'] ?? null)
                ? $featureCoverage['fixturesWithLandmarks']
                : [];
            $pageListFixtures = is_array($featureCoverage['fixturesWithPageLists'] ?? null)
                ? $featureCoverage['fixturesWithPageLists']
                : [];
            $pageListCfiFixtures = is_array($featureCoverage['fixturesWithPageListCfiTargets'] ?? null)
                ? $featureCoverage['fixturesWithPageListCfiTargets']
                : [];
            $auxiliaryNavigationFixtures = is_array($featureCoverage['fixturesWithAuxiliaryNavigation'] ?? null)
                ? $featureCoverage['fixturesWithAuxiliaryNavigation']
                : [];
            $nonLinearSpineFixtures = is_array($featureCoverage['fixturesWithNonLinearSpineItems'] ?? null)
                ? $featureCoverage['fixturesWithNonLinearSpineItems']
                : [];
            $spinePageSpreadFixtures = is_array($featureCoverage['fixturesWithSpinePageSpreadItems'] ?? null)
                ? $featureCoverage['fixturesWithSpinePageSpreadItems']
                : [];
            $guideReferenceTypeCounts = is_array($featureCoverage['guideReferenceTypeCounts'] ?? null)
                ? $featureCoverage['guideReferenceTypeCounts']
                : [];
            $packageLinkRelCounts = is_array($featureCoverage['packageLinkRelCounts'] ?? null)
                ? $featureCoverage['packageLinkRelCounts']
                : [];
            $packageLinkVocabularyRelCounts = is_array($featureCoverage['packageLinkVocabularyRelCounts'] ?? null)
                ? $featureCoverage['packageLinkVocabularyRelCounts']
                : [];
            $packageLinkVocabularyPropertyCounts = is_array($featureCoverage['packageLinkVocabularyPropertyCounts'] ?? null)
                ? $featureCoverage['packageLinkVocabularyPropertyCounts']
                : [];
            $packageLinkVocabularyFixtures = is_array($featureCoverage['fixturesWithPackageLinkVocabulary'] ?? null)
                ? $featureCoverage['fixturesWithPackageLinkVocabulary']
                : [];
            $packageLinkMediaTypeCounts = is_array($featureCoverage['packageLinkMediaTypeCounts'] ?? null)
                ? $featureCoverage['packageLinkMediaTypeCounts']
                : [];
            $packageLinkMediaTypeParameterNameCounts = is_array($featureCoverage['packageLinkMediaTypeParameterNameCounts'] ?? null)
                ? $featureCoverage['packageLinkMediaTypeParameterNameCounts']
                : [];
            $packageLinkMediaTypeParameterFixtures = is_array($featureCoverage['fixturesWithPackageLinkMediaTypeParameters'] ?? null)
                ? $featureCoverage['fixturesWithPackageLinkMediaTypeParameters']
                : [];
            $linkHrefSuffixSourceCounts = is_array($featureCoverage['linkHrefSuffixSourceCounts'] ?? null)
                ? $featureCoverage['linkHrefSuffixSourceCounts']
                : [];
            $linkHrefSuffixFixtures = is_array($featureCoverage['fixturesWithLinkHrefSuffixes'] ?? null)
                ? $featureCoverage['fixturesWithLinkHrefSuffixes']
                : [];
            $accessibilityPropertyCounts = is_array($featureCoverage['accessibilityPropertyCounts'] ?? null)
                ? $featureCoverage['accessibilityPropertyCounts']
                : [];
            $accessibilityFixtures = is_array($featureCoverage['fixturesWithAccessibilityMetadata'] ?? null)
                ? $featureCoverage['fixturesWithAccessibilityMetadata']
                : [];
            $encryptionRoleCounts = is_array($featureCoverage['encryptionRoleCounts'] ?? null)
                ? $featureCoverage['encryptionRoleCounts']
                : [];
            $opfPartNameCounts = is_array($featureCoverage['opfPartNameCounts'] ?? null)
                ? $featureCoverage['opfPartNameCounts']
                : [];
            $encryptionFixtures = is_array($featureCoverage['fixturesWithEncryption'] ?? null)
                ? $featureCoverage['fixturesWithEncryption']
                : [];
            $obfuscatedFontFixtures = is_array($featureCoverage['fixturesWithObfuscatedFonts'] ?? null)
                ? $featureCoverage['fixturesWithObfuscatedFonts']
                : [];
            $blockedEncryptedByteFixtures = is_array($featureCoverage['fixturesWithBlockedEncryptedByteExposures'] ?? null)
                ? $featureCoverage['fixturesWithBlockedEncryptedByteExposures']
                : [];
            $manifestFallbackItemFixtures = is_array($featureCoverage['fixturesWithManifestFallbackItems'] ?? null)
                ? $featureCoverage['fixturesWithManifestFallbackItems']
                : [];
            $resolvedManifestFallbackFixtures = is_array($featureCoverage['fixturesWithResolvedManifestFallbacks'] ?? null)
                ? $featureCoverage['fixturesWithResolvedManifestFallbacks']
                : [];
            $usableManifestFallbackFixtures = is_array($featureCoverage['fixturesWithUsableManifestFallbacks'] ?? null)
                ? $featureCoverage['fixturesWithUsableManifestFallbacks']
                : [];
            $missingManifestFallbackFixtures = is_array($featureCoverage['fixturesWithMissingManifestFallbacks'] ?? null)
                ? $featureCoverage['fixturesWithMissingManifestFallbacks']
                : [];
            $mediaOverlayFixtures = is_array($featureCoverage['fixturesWithMediaOverlays'] ?? null)
                ? $featureCoverage['fixturesWithMediaOverlays']
                : [];
            $resolvedMediaOverlayFixtures = is_array($featureCoverage['fixturesWithResolvedMediaOverlays'] ?? null)
                ? $featureCoverage['fixturesWithResolvedMediaOverlays']
                : [];
            $collectionFixtures = is_array($featureCoverage['fixturesWithCollections'] ?? null)
                ? $featureCoverage['fixturesWithCollections']
                : [];
            $bindingFixtures = is_array($featureCoverage['fixturesWithBindings'] ?? null)
                ? $featureCoverage['fixturesWithBindings']
                : [];
            $ocfSidecarFixtures = is_array($featureCoverage['fixturesWithOcfSidecars'] ?? null)
                ? $featureCoverage['fixturesWithOcfSidecars']
                : [];
            $collectionRoleCounts = is_array($featureCoverage['collectionRoleCounts'] ?? null)
                ? $featureCoverage['collectionRoleCounts']
                : [];
            $collectionLinkRelCounts = is_array($featureCoverage['collectionLinkRelCounts'] ?? null)
                ? $featureCoverage['collectionLinkRelCounts']
                : [];
            $bindingMediaTypeCounts = is_array($featureCoverage['bindingMediaTypeCounts'] ?? null)
                ? $featureCoverage['bindingMediaTypeCounts']
                : [];
            $ocfSidecarKindCounts = is_array($featureCoverage['ocfSidecarKindCounts'] ?? null)
                ? $featureCoverage['ocfSidecarKindCounts']
                : [];
            $lines[] = sprintf(
                'packageFeatureCoverage: fixtures=%d nav=%d ncx=%d covers=%d landmarks=%d pageLists=%d pageListCfiFixtures=%d pageListCfiTargets=%d auxiliaryNav=%d metadataCreators=%d accessibilityFixtures=%d accessibilityEntries=%d accessibilityLinkedRecords=%d accessibilityProperties=%s manifestItems=%d readingOrderItems=%d spineLinear=%s nonLinearSpineFixtures=%d spinePageSpread=%s pageSpreadFixtures=%d imageAssets=%d stylesheetAssets=%d resourceKinds=%s guideRefTypes=%s packageLinkRels=%s packageLinkVocabFixtures=%d packageLinkVocabRels=%s packageLinkVocabProps=%s packageLinkVocabRelTokens=%d packageLinkVocabPropertyTokens=%d packageLinkVocabResolvedTokens=%d packageLinkVocabAbsoluteUrlTokens=%d packageLinkVocabDuplicateTokens=%d packageLinkVocabDiagnostics=%d packageLinkMediaTypes=%s packageLinkParamFixtures=%d packageLinkParams=%d packageLinkParamNames=%s linkHrefSuffixFixtures=%d linkHrefSuffixes=%d linkHrefSuffixSources=%s linkHrefQueries=%d linkHrefFragments=%d remoteManifest=%d externalManifest=%d missingLocalManifest=%d manifestFallbackItems=%d manifestFallbacks=%d resolvedFallbacks=%d usableFallbacks=%d missingFallbacks=%d mediaOverlayFixtures=%d resolvedMediaOverlayFixtures=%d mediaOverlays=%d resolvedMediaOverlays=%d mediaOverlayTimelineItems=%d mediaOverlayClipTimings=%d mediaOverlayValidClipTimings=%d mediaOverlayInvalidClipTimings=%d mediaOverlayTextTargets=%d mediaOverlayAudioTargets=%d mediaOverlayDurations=%d encryptionFixtures=%d obfuscatedFontFixtures=%d blockedEncryptedByteExposureFixtures=%d encryptionItems=%d obfuscatedFonts=%d blockedEncryptedByteExposures=%d encryptionDiagnostics=%d encryptionRoles=%s collectionFixtures=%d collections=%d collectionLinks=%d collectionRoles=%s collectionLinkRels=%s bindingFixtures=%d bindings=%d bindingResolvedHandlers=%d bindingParams=%d bindingMediaTypes=%s ocfSidecarFixtures=%d ocfSidecars=%d ocfSidecarKinds=%s opfParts=%s',
                (int) ($featureCoverage['fixtureCount'] ?? 0),
                (int) ($navigationTypeCounts['nav'] ?? 0),
                (int) ($navigationTypeCounts['ncx'] ?? 0),
                count($coverFixtures),
                count($landmarkFixtures),
                count($pageListFixtures),
                count($pageListCfiFixtures),
                (int) ($totals['pageListCfiTargets'] ?? 0),
                count($auxiliaryNavigationFixtures),
                (int) ($totals['metadataCreators'] ?? 0),
                count($accessibilityFixtures),
                (int) ($totals['accessibilityEntries'] ?? 0),
                (int) ($totals['accessibilityLinkedRecords'] ?? 0),
                self::formatCounts($accessibilityPropertyCounts),
                (int) ($totals['manifestItems'] ?? 0),
                (int) ($totals['readingOrderItems'] ?? 0),
                self::formatCounts(is_array($featureCoverage['spineLinearStateCounts'] ?? null)
                    ? $featureCoverage['spineLinearStateCounts']
                    : []),
                count($nonLinearSpineFixtures),
                self::formatCounts(is_array($featureCoverage['spinePageSpreadPlacementCounts'] ?? null)
                    ? $featureCoverage['spinePageSpreadPlacementCounts']
                    : []),
                count($spinePageSpreadFixtures),
                (int) ($totals['imageAssets'] ?? 0),
                (int) ($totals['stylesheetAssets'] ?? 0),
                self::formatCounts(is_array($featureCoverage['manifestResourceKindCounts'] ?? null)
                    ? $featureCoverage['manifestResourceKindCounts']
                    : []),
                self::formatCounts($guideReferenceTypeCounts),
                self::formatCounts($packageLinkRelCounts),
                count($packageLinkVocabularyFixtures),
                self::formatCounts($packageLinkVocabularyRelCounts),
                self::formatCounts($packageLinkVocabularyPropertyCounts),
                (int) ($totals['packageLinkVocabularyRelTokens'] ?? 0),
                (int) ($totals['packageLinkVocabularyPropertyTokens'] ?? 0),
                (int) ($totals['packageLinkVocabularyResolvedTokens'] ?? 0),
                (int) ($totals['packageLinkVocabularyAbsoluteUrlTokens'] ?? 0),
                (int) ($totals['packageLinkVocabularyDuplicateTokens'] ?? 0),
                (int) ($totals['packageLinkVocabularyDiagnostics'] ?? 0),
                self::formatCounts($packageLinkMediaTypeCounts),
                count($packageLinkMediaTypeParameterFixtures),
                (int) ($totals['packageLinkMediaTypeParameters'] ?? 0),
                self::formatCounts($packageLinkMediaTypeParameterNameCounts),
                count($linkHrefSuffixFixtures),
                (int) ($totals['linkHrefSuffixes'] ?? 0),
                self::formatCounts($linkHrefSuffixSourceCounts),
                (int) ($totals['linkHrefSuffixQueries'] ?? 0),
                (int) ($totals['linkHrefSuffixFragments'] ?? 0),
                (int) ($totals['remoteResourceManifestItems'] ?? 0),
                (int) ($totals['externalManifestItems'] ?? 0),
                (int) ($totals['missingLocalManifestItems'] ?? 0),
                count($manifestFallbackItemFixtures),
                (int) ($totals['manifestFallbacks'] ?? 0),
                count($resolvedManifestFallbackFixtures),
                count($usableManifestFallbackFixtures),
                count($missingManifestFallbackFixtures),
                count($mediaOverlayFixtures),
                count($resolvedMediaOverlayFixtures),
                (int) ($totals['mediaOverlays'] ?? 0),
                (int) ($totals['resolvedMediaOverlays'] ?? 0),
                (int) ($totals['mediaOverlayTimelineItems'] ?? 0),
                (int) ($totals['mediaOverlayClipTimings'] ?? 0),
                (int) ($totals['mediaOverlayValidClipTimings'] ?? 0),
                (int) ($totals['mediaOverlayInvalidClipTimings'] ?? 0),
                (int) ($totals['mediaOverlayTextLocalTargets'] ?? 0),
                (int) ($totals['mediaOverlayAudioLocalTargets'] ?? 0),
                (int) ($totals['mediaOverlayDurations'] ?? 0),
                count($encryptionFixtures),
                count($obfuscatedFontFixtures),
                count($blockedEncryptedByteFixtures),
                (int) ($totals['encryptionItems'] ?? 0),
                (int) ($totals['obfuscatedFonts'] ?? 0),
                (int) ($totals['blockedEncryptedByteExposures'] ?? 0),
                (int) ($totals['encryptionDiagnostics'] ?? 0),
                self::formatCounts($encryptionRoleCounts),
                count($collectionFixtures),
                (int) ($totals['collections'] ?? 0),
                (int) ($totals['collectionLinks'] ?? 0),
                self::formatCounts($collectionRoleCounts),
                self::formatCounts($collectionLinkRelCounts),
                count($bindingFixtures),
                (int) ($totals['bindingItems'] ?? 0),
                (int) ($totals['bindingResolvedHandlers'] ?? 0),
                (int) ($totals['bindingMediaTypeParameters'] ?? 0),
                self::formatCounts($bindingMediaTypeCounts),
                count($ocfSidecarFixtures),
                (int) ($totals['ocfSidecars'] ?? 0),
                self::formatCounts($ocfSidecarKindCounts),
                self::formatCounts($opfPartNameCounts)
            );
        }
        $featureSignature = is_array($report['packageFeatureSignature'] ?? null) ? $report['packageFeatureSignature'] : [];
        if ($featureSignature !== []) {
            $signatureValidation = is_array($featureSignature['validation'] ?? null) ? $featureSignature['validation'] : [];
            $lines[] = sprintf(
                'packageFeatureSignature: status=%s matchesExpected=%s sha256=%s expected=%s',
                (string) ($signatureValidation['status'] ?? 'unknown'),
                (($featureSignature['matchesExpected'] ?? false) === true) ? 'true' : 'false',
                (string) ($featureSignature['sha256'] ?? ''),
                (string) ($featureSignature['expectedSha256'] ?? '')
            );
        }
        $nativeAstSignature = is_array($report['currentNativeAstSignature'] ?? null) ? $report['currentNativeAstSignature'] : [];
        if ($nativeAstSignature !== []) {
            $signatureValidation = is_array($nativeAstSignature['validation'] ?? null) ? $nativeAstSignature['validation'] : [];
            $lines[] = sprintf(
                'currentNativeAstSignature: status=%s matchesExpected=%s fixtures=%d sha256=%s expected=%s',
                (string) ($signatureValidation['status'] ?? 'unknown'),
                (($nativeAstSignature['matchesExpected'] ?? false) === true) ? 'true' : 'false',
                (int) ($nativeAstSignature['fixtureCount'] ?? 0),
                (string) ($nativeAstSignature['sha256'] ?? ''),
                (string) ($nativeAstSignature['expectedSha256'] ?? '')
            );
        }
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        if ($runner !== []) {
            $lines[] = sprintf(
                'runnerEvidence: status=%s plan=%s executed=%s',
                (string) ($runner['status'] ?? 'unknown'),
                (string) ($runner['commandPlanStatus'] ?? 'unknown'),
                self::formatBooleanFlag($runner['executed'] ?? null)
            );
        }

        $mismatches = $report['mismatchComparisons'] ?? [];
        if (is_array($mismatches) && $mismatches !== []) {
            $lines[] = 'mismatchExamples:';
            foreach ($mismatches as $mismatch) {
                if (!is_array($mismatch)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($mismatch['fixture'] ?? 'unknown')
                    . ': ' . (string) ($mismatch['firstDifference'] ?? 'unknown');
            }
        }

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
    public static function hasRequiredPackageParity(array $report, int $requiredEpubCount): bool
    {
        if ($requiredEpubCount < 0) {
            throw new \InvalidArgumentException('Required EPUB package count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalEpubCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['comparedEpubCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['packageParsedCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['readerParsedCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['packageParseFailureCount'] ?? -1) === 0
            && (int) ($report['readerParseFailureCount'] ?? -1) === 0
            && ($report['packageAcceptanceStatus'] ?? null) === 'package-and-reader-implementation-equivalence-observed';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredNativeReadiness(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required EPUB native pair count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['epubPairParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['astParseFailureCount'] ?? -1) === 0
            && (int) ($report['nativeParseFailureCount'] ?? -1) === 0;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMappedParity(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required EPUB mapped parity count must not be negative');
        }

        return self::hasRequiredNativeReadiness($report, $requiredPairCount)
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-implementation-equivalence-observed';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixtureIdentity(array $report): bool
    {
        $identity = is_array($report['fixtureIdentity'] ?? null) ? $report['fixtureIdentity'] : [];
        $validation = is_array($identity['validation'] ?? null) ? $identity['validation'] : [];

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($identity['expectedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && (int) ($identity['observedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-fixture-identity'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentPackageFeatureCoverage(array $report): bool
    {
        $coverage = is_array($report['packageFeatureCoverage'] ?? null) ? $report['packageFeatureCoverage'] : [];
        if (($report['skipped'] ?? false) !== false || ($report['status'] ?? null) !== 'completed') {
            return false;
        }

        return self::packageFeatureCoverageMatchesExpected($coverage);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentPackageFeatureSignature(array $report): bool
    {
        $signature = is_array($report['packageFeatureSignature'] ?? null) ? $report['packageFeatureSignature'] : [];
        $validation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && self::hasRequiredFixtureIdentity($report)
            && self::hasRequiredCurrentPackageFeatureCoverage($report)
            && ($signature['kind'] ?? null) === self::PACKAGE_FEATURE_SIGNATURE_KIND
            && ($signature['algorithm'] ?? null) === self::PACKAGE_FEATURE_SIGNATURE_ALGORITHM
            && ($signature['scope'] ?? null) === self::PACKAGE_FEATURE_SIGNATURE_SCOPE
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256
            && ($signature['expectedSha256'] ?? null) === self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256
            && ($signature['hashMatchesExpected'] ?? null) === true
            && ($signature['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-package-feature-signature'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentNativeAstSignature(array $report): bool
    {
        $signature = is_array($report['currentNativeAstSignature'] ?? null) ? $report['currentNativeAstSignature'] : [];
        $validation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];
        $expectedPairCount = count(self::expectedCheckedInCurrentPairNames());

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && self::hasRequiredFixtureIdentity($report)
            && (int) ($report['totalPairCount'] ?? -1) === $expectedPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $expectedPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $expectedPairCount
            && (int) ($report['astParseFailureCount'] ?? -1) === 0
            && (int) ($report['nativeParseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $expectedPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($signature['kind'] ?? null) === self::CURRENT_NATIVE_AST_SIGNATURE_KIND
            && ($signature['algorithm'] ?? null) === self::CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM
            && ($signature['scope'] ?? null) === self::CURRENT_NATIVE_AST_SIGNATURE_SCOPE
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256
            && ($signature['expectedSha256'] ?? null) === self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256
            && ($signature['hashMatchesExpected'] ?? null) === true
            && ($signature['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-normalized-native-ast-signature'
            && ($validation['issues'] ?? null) === [];
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
        $snapshot = is_array($runner['checkedInSnapshot'] ?? null) ? $runner['checkedInSnapshot'] : [];

        return self::hasRunnerNotRunEvidence($report)
            && ($runner['commandPlanStatus'] ?? null) === 'planned-not-run'
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['executableTarget'] ?? null) === self::RUNNER_CABAL_TARGET
            && ($binding['fixtureDirectory'] ?? null) === self::RUNNER_FIXTURE_DIRECTORY
            && ($target['cabalTarget'] ?? null) === self::RUNNER_CABAL_TARGET
            && ($target['inputFormat'] ?? null) === 'epub'
            && ($target['outputFormat'] ?? null) === self::RUNNER_OUTPUT_FORMAT
            && ($target['fixtureDirectory'] ?? null) === self::RUNNER_FIXTURE_DIRECTORY
            && ($target['fixtureBasenames'] ?? null) === self::expectedCheckedInCurrentPairNames()
            && ($snapshot['fixtureIdentityKind'] ?? null) === 'static-checked-in-current-epub-fixture-identity'
            && ($snapshot['expectedFileCount'] ?? null) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && ($snapshot['expectedPairCount'] ?? null) === count(self::expectedCheckedInCurrentPairNames())
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
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];
        $snapshot = is_array($runner['checkedInSnapshot'] ?? null) ? $runner['checkedInSnapshot'] : [];
        $transcripts = is_array($runner['transcripts'] ?? null) ? $runner['transcripts'] : [];

        return self::runnerResultArtifactEvidenceIsValid($runner)
            && ($runner['scope'] ?? null) === 'upstream-haskell-runner'
            && ($runner['runner'] ?? null) === 'Cabal-built Pandoc EPUB to native executable'
            && is_array($runner['command'] ?? null)
            && self::canonicalValue($runner['command'] ?? null) === self::canonicalValue(self::runnerFutureCommands()[2])
            && ($artifact['kind'] ?? null) === self::RUNNER_RESULT_ARTIFACT_KIND
            && ($artifact['present'] ?? null) === true
            && is_string($artifact['sha256'] ?? null)
            && is_int($artifact['bytes'] ?? null)
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['observedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['executableTarget'] ?? null) === self::RUNNER_CABAL_TARGET
            && ($binding['fixtureDirectory'] ?? null) === self::RUNNER_FIXTURE_DIRECTORY
            && ($target['cabalTarget'] ?? null) === self::RUNNER_CABAL_TARGET
            && ($target['inputFormat'] ?? null) === 'epub'
            && ($target['outputFormat'] ?? null) === self::RUNNER_OUTPUT_FORMAT
            && ($target['fixtureDirectory'] ?? null) === self::RUNNER_FIXTURE_DIRECTORY
            && ($target['fixtureBasenames'] ?? null) === self::expectedCheckedInCurrentPairNames()
            && ($snapshot['fixtureIdentityKind'] ?? null) === 'static-checked-in-current-epub-fixture-identity'
            && ($snapshot['expectedFileCount'] ?? null) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && ($snapshot['expectedPairCount'] ?? null) === count(self::expectedCheckedInCurrentPairNames())
            && ($snapshot['packageFeatureSignature'] ?? null) === self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256
            && ($snapshot['nativeAstSignature'] ?? null) === self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256
            && ($runner['futureCommands'] ?? null) === self::runnerFutureCommands()
            && ($runner['requiredTranscripts'] ?? null) === self::RUNNER_REQUIRED_TRANSCRIPTS
            && ($runner['requiredArtifacts'] ?? null) === self::RUNNER_REQUIRED_ARTIFACTS
            && count($transcripts) === count(self::RUNNER_REQUIRED_TRANSCRIPTS);
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedReport(string $epubDirectory, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-native-ast-package',
            'status' => 'skipped',
            'skipped' => true,
            'reason' => $reason,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-native-ast-package-comparison',
            'upstreamEpubDirectory' => $epubDirectory,
            'fixtureIdentity' => self::notEvaluatedFixtureIdentity(),
            'packageFeatureCoverage' => self::emptyPackageFeatureCoverage(),
            'packageFeatureSignature' => self::notEvaluatedPackageFeatureSignature($reason),
            'currentNativeAstSignature' => self::notEvaluatedCurrentNativeAstSignature($reason),
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalEpubCount' => 0,
            'comparedEpubCount' => 0,
            'packageParsedCount' => 0,
            'readerParsedCount' => 0,
            'packageParseFailureCount' => 0,
            'readerParseFailureCount' => 0,
            'packageAcceptanceStatus' => 'not-evaluated-source-directory-unavailable',
            'totalPairCount' => 0,
            'comparedPairCount' => 0,
            'epubPairParsedCount' => 0,
            'nativeParsedCount' => 0,
            'bothParsedCount' => 0,
            'astParseFailureCount' => 0,
            'nativeParseFailureCount' => 0,
            'normalizedAstMatchCount' => 0,
            'normalizedAstMismatchCount' => 0,
            'normalizedAstMatchPercent' => null,
            'astParityStatus' => 'not-evaluated-source-directory-unavailable',
            'packageSummaries' => [],
            'packageParseFailures' => [],
            'readerParseFailures' => [],
            'nativeParseFailures' => [],
            'astParseFailures' => [],
            'mismatchComparisons' => [],
            'mismatchCategories' => [],
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, 0, 0, 0, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'compares' => [
                'node type',
                'non-provenance node attributes',
                'child order and child count',
                'visible inline and block AST shape after adjacent text nodes are coalesced',
            ],
            'excludes' => [
                'document metadata and local EPUB package provenance attrs',
                'derived text attrs on plain, paragraph, heading, table_cell, and term nodes',
                'reader-specific adjacent Str/Space text-node segmentation',
            ],
            'doesNotAssert' => [
                'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                'EPUB writer parity',
                'byte-level EPUB package equality',
                'byte-for-byte identity with Pandoc Haskell reader internals',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runnerResultArtifactEvidence(string $runnerResultArtifact, string $repoRoot): array
    {
        $path = self::absoluteRunnerResultArtifact($runnerResultArtifact, $repoRoot);
        $artifact = self::runnerResultArtifactFileEvidence($path, $repoRoot);
        $transcripts = self::runnerTranscriptFileEvidenceList($repoRoot);
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
        $expectedTarget = self::runnerTarget();
        $expectedFixtureBasenames = self::expectedCheckedInCurrentPairNames();
        $expectedGeneratedNativeManifest = self::expectedGeneratedNativeManifest();
        $observedFixtureBasenames = self::stringList($payload['fixtureBasenames'] ?? ($payload['fixtures'] ?? []));
        $observedGeneratedNativeManifest = self::runnerGeneratedNativeManifestRecords($payload['generatedNativeManifest'] ?? []);
        $observedTranscriptPaths = self::orderedStringList($payload['transcriptPaths'] ?? []);
        $observedTranscriptRecords = self::runnerTranscriptRecords($payload['transcripts'] ?? []);
        if ($observedTranscriptPaths === [] && $observedTranscriptRecords !== []) {
            $observedTranscriptPaths = self::runnerTranscriptRecordPaths($observedTranscriptRecords);
        }
        $runnerExecuted = ($payload['runnerExecuted'] ?? $payload['executed'] ?? null) === true;
        $exitCode = is_int($payload['exitCode'] ?? null) ? (int) $payload['exitCode'] : null;
        $fixtureCount = is_int($payload['fixtureCount'] ?? null) ? (int) $payload['fixtureCount'] : null;
        $generatedNativeCount = is_int($payload['generatedNativeCount'] ?? null) ? (int) $payload['generatedNativeCount'] : null;
        $failedCount = is_int($payload['failedCount'] ?? null) ? (int) $payload['failedCount'] : null;

        if ($payload !== []) {
            if (($payload['schemaVersion'] ?? null) !== self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION) {
                $issues[] = 'runner-result-schema-version-mismatch';
            }
            if (($payload['runner'] ?? null) !== 'Cabal-built Pandoc EPUB to native executable') {
                $issues[] = 'runner-result-runner-name-mismatch';
            }
            if (!$runnerExecuted) {
                $issues[] = 'runner-result-executed-flag-missing-or-false';
            }
            if (($upstream['name'] ?? null) !== 'jgm/pandoc' || ($upstream['commit'] ?? null) !== self::EXPECTED_UPSTREAM_COMMIT) {
                $issues[] = 'runner-result-upstream-commit-mismatch';
            }
            if (self::canonicalValue($target) !== self::canonicalValue($expectedTarget)) {
                $issues[] = 'runner-result-target-mismatch';
            }
            if (self::canonicalValue($command) !== self::canonicalValue($expectedCommand)) {
                $issues[] = 'runner-result-command-mismatch';
            }
            if ($exitCode !== 0) {
                $issues[] = 'runner-result-exit-code-nonzero';
            }
            if (
                $fixtureCount !== count($expectedFixtureBasenames)
                || $generatedNativeCount !== count($expectedGeneratedNativeManifest)
                || $failedCount !== 0
            ) {
                $issues[] = 'runner-result-counts-mismatch';
            }
            if ($observedFixtureBasenames !== $expectedFixtureBasenames) {
                $issues[] = 'runner-result-fixture-basenames-mismatch';
            }
            if ($observedGeneratedNativeManifest !== $expectedGeneratedNativeManifest) {
                $issues[] = 'runner-result-generated-native-manifest-mismatch';
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
            'runner' => 'Cabal-built Pandoc EPUB to native executable',
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
                'executableTarget' => self::RUNNER_CABAL_TARGET,
                'fixtureDirectory' => self::RUNNER_FIXTURE_DIRECTORY,
            ],
            'target' => [
                'cabalTarget' => is_string($target['cabalTarget'] ?? null) ? $target['cabalTarget'] : null,
                'inputFormat' => is_string($target['inputFormat'] ?? null) ? $target['inputFormat'] : null,
                'outputFormat' => is_string($target['outputFormat'] ?? null) ? $target['outputFormat'] : null,
                'fixtureDirectory' => is_string($target['fixtureDirectory'] ?? null) ? $target['fixtureDirectory'] : null,
                'fixtureBasenames' => self::stringList($target['fixtureBasenames'] ?? []),
            ],
            'checkedInSnapshot' => self::runnerCheckedInSnapshot(),
            'expected' => [
                'schemaVersion' => self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION,
                'runner' => 'Cabal-built Pandoc EPUB to native executable',
                'fixtureCount' => count($expectedFixtureBasenames),
                'generatedNativeCount' => count($expectedGeneratedNativeManifest),
                'failedCount' => 0,
                'fixtureBasenames' => $expectedFixtureBasenames,
                'generatedNativeManifest' => $expectedGeneratedNativeManifest,
                'transcriptPaths' => self::RUNNER_REQUIRED_TRANSCRIPTS,
                'transcripts' => self::runnerTranscriptRecordsFromEvidence($transcripts),
                'command' => $expectedCommand,
            ],
            'observed' => [
                'schemaVersion' => $payload['schemaVersion'] ?? null,
                'runner' => $payload['runner'] ?? null,
                'exitCode' => $exitCode,
                'fixtureCount' => $fixtureCount,
                'generatedNativeCount' => $generatedNativeCount,
                'failedCount' => $failedCount,
                'fixtureBasenames' => $observedFixtureBasenames,
                'generatedNativeManifest' => $observedGeneratedNativeManifest,
                'transcriptPaths' => $observedTranscriptPaths,
                'transcripts' => $observedTranscriptRecords,
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'transcripts' => $transcripts,
            'validation' => [
                'status' => $issues === []
                    ? 'valid-upstream-epub-native-package-runner-result-artifact'
                    : 'invalid-upstream-epub-native-package-runner-result-artifact',
                'issues' => $issues,
            ],
            'claim' => $issues === []
                ? 'A supplied upstream EPUB-to-native runner result artifact matches the pinned executable runner evidence contract.'
                : 'The supplied upstream EPUB-to-native runner result artifact did not satisfy the pinned executable runner evidence contract.',
        ];
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private static function runnerResultArtifactFileEvidence(string $path, string $repoRoot): array
    {
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_RESULT_ARTIFACT_KIND,
            'path' => self::displayRunnerPath($path, $repoRoot),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    /**
     * @return list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptFileEvidenceList(string $repoRoot): array
    {
        $files = [];
        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $path) {
            $files[] = self::runnerTranscriptFileEvidence($repoRoot, $path);
        }

        return $files;
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private static function runnerTranscriptFileEvidence(string $repoRoot, string $relativePath): array
    {
        $path = self::absoluteRunnerTranscriptPath($repoRoot, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_TRANSCRIPT_KIND,
            'path' => self::displayRunnerPath($path, $repoRoot),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    private static function absoluteRunnerResultArtifact(string $path, string $repoRoot): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }
        if ($repoRoot === '') {
            return $path;
        }

        return $repoRoot . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
    }

    private static function absoluteRunnerTranscriptPath(string $repoRoot, string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }
        if ($repoRoot === '') {
            return str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private static function displayRunnerPath(string $path, string $repoRoot): string
    {
        if ($repoRoot !== '' && str_starts_with($path, $repoRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($repoRoot) + 1);
        }

        return $path;
    }

    /**
     * @return list<array{fixture: string, path: string, sha256: ?string, bytes: ?int}>
     */
    private static function runnerGeneratedNativeManifestRecords(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $fixture = is_string($item['fixture'] ?? null)
                ? $item['fixture']
                : (is_string($key) ? $key : '');
            $records[] = [
                'fixture' => $fixture,
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'sha256' => is_string($item['sha256'] ?? null) ? $item['sha256'] : null,
                'bytes' => is_int($item['bytes'] ?? null) ? $item['bytes'] : null,
            ];
        }
        usort(
            $records,
            static fn (array $left, array $right): int => ($left['fixture'] <=> $right['fixture'])
                ?: ($left['path'] <=> $right['path'])
        );

        return $records;
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

    /**
     * @return list<array{fixture: string, path: string, sha256: string, bytes: int}>
     */
    private static function expectedGeneratedNativeManifest(): array
    {
        $records = [];
        foreach (self::expectedCheckedInCurrentPairNames() as $fixture) {
            $path = $fixture . '.native';
            $identity = self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES[$path] ?? null;
            if (!is_array($identity)) {
                continue;
            }
            $records[] = [
                'fixture' => $fixture,
                'path' => self::RUNNER_FIXTURE_DIRECTORY . '/' . $path,
                'sha256' => $identity['sha256'],
                'bytes' => $identity['bytes'],
            ];
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerTarget(): array
    {
        return [
            'cabalTarget' => self::RUNNER_CABAL_TARGET,
            'inputFormat' => 'epub',
            'outputFormat' => self::RUNNER_OUTPUT_FORMAT,
            'fixtureDirectory' => self::RUNNER_FIXTURE_DIRECTORY,
            'fixtureBasenames' => self::expectedCheckedInCurrentPairNames(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerCheckedInSnapshot(): array
    {
        return [
            'fixtureIdentityKind' => 'static-checked-in-current-epub-fixture-identity',
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'expectedPairCount' => count(self::expectedCheckedInCurrentPairNames()),
            'packageFeatureSignature' => self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256,
            'nativeAstSignature' => self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256,
        ];
    }

    /**
     * @param array<string, mixed> $runner
     */
    private static function runnerResultArtifactEvidenceIsValid(array $runner): bool
    {
        $validation = is_array($runner['validation'] ?? null) ? $runner['validation'] : [];

        return ($runner['status'] ?? null) === 'completed'
            && ($runner['executed'] ?? null) === true
            && ($runner['commandPlanStatus'] ?? null) === 'runner-result-artifact-validated'
            && ($validation['status'] ?? null) === 'valid-upstream-epub-native-package-runner-result-artifact'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal-built Pandoc EPUB to native executable plan',
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'commandPlanStatus' => 'planned-not-run',
            'upstreamBinding' => [
                'name' => 'jgm/pandoc',
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'executableTarget' => self::RUNNER_CABAL_TARGET,
                'fixtureDirectory' => self::RUNNER_FIXTURE_DIRECTORY,
            ],
            'target' => self::runnerTarget(),
            'checkedInSnapshot' => self::runnerCheckedInSnapshot(),
            'blockers' => [
                'no committed upstream pandoc executable transcript or generated native manifest is present',
                'this PHP evidence gate intentionally does not invoke Cabal or run the upstream Pandoc executable',
                'a future runner claim must be bound to the pinned upstream commit and checked-in EPUB/native fixture snapshot',
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This PHP package/native evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner or executable native generation parity is claimed.',
        ];
    }

    /**
     * @return list<array{purpose: string, program: string, arguments: list<string>}>
     */
    private static function runnerFutureCommands(): array
    {
        return [
            [
                'purpose' => 'prepare the upstream pandoc executable dependencies in an isolated build directory',
                'program' => 'cabal',
                'arguments' => [
                    'v2-build',
                    '--offline',
                    '--dry-run',
                    '--only-dependencies',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_CABAL_TARGET,
                ],
            ],
            [
                'purpose' => 'build the upstream pandoc executable for EPUB to native fixture generation',
                'program' => 'cabal',
                'arguments' => [
                    'v2-build',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_CABAL_TARGET,
                ],
            ],
            [
                'purpose' => 'generate native AST goldens for the checked-in current EPUB fixture set',
                'program' => 'cabal',
                'arguments' => [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_CABAL_TARGET,
                    '--',
                    '--from',
                    'epub',
                    '--to',
                    self::RUNNER_OUTPUT_FORMAT,
                    self::RUNNER_FIXTURE_DIRECTORY . '/{fixture}.epub',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPackageFeatureCoverage(): array
    {
        return [
            'kind' => 'epub-package-feature-coverage',
            'fixtureCount' => 0,
            'opfPartNameCounts' => [],
            'metadataLanguageCounts' => [],
            'fixturesWithCreators' => [],
            'navigationTypeCounts' => [],
            'spineLinearStateCounts' => [],
            'spinePageSpreadPlacementCounts' => [],
            'manifestMediaTypeCounts' => [],
            'manifestPropertyCounts' => [],
            'manifestResourceKindCounts' => [],
            'navigationSectionTypes' => [],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'packageLinkVocabularyRelCounts' => [],
            'packageLinkVocabularyPropertyCounts' => [],
            'packageLinkMediaTypeCounts' => [],
            'packageLinkMediaTypeParameterNameCounts' => [],
            'linkHrefSuffixSourceCounts' => [],
            'accessibilityPropertyCounts' => [],
            'encryptionRoleCounts' => [],
            'collectionRoleCounts' => [],
            'collectionLinkRelCounts' => [],
            'bindingMediaTypeCounts' => [],
            'ocfSidecarKindCounts' => [],
            'fixtureFeatureSignatures' => [],
            'fixturesWithGuideReferences' => [],
            'fixturesWithPackageLinks' => [],
            'fixturesWithPackageLinkVocabulary' => [],
            'fixturesWithPackageLinkVocabularyDiagnostics' => [],
            'fixturesWithPackageLinkMediaTypeParameters' => [],
            'fixturesWithLinkHrefSuffixes' => [],
            'fixturesWithAccessibilityMetadata' => [],
            'fixturesWithCoverImagePart' => [],
            'fixturesWithEncryption' => [],
            'fixturesWithObfuscatedFonts' => [],
            'fixturesWithBlockedEncryptedByteExposures' => [],
            'fixturesWithImages' => [],
            'fixturesWithStylesheets' => [],
            'fixturesWithLandmarks' => [],
            'fixturesWithPageLists' => [],
            'fixturesWithPageListCfiTargets' => [],
            'fixturesWithAuxiliaryNavigation' => [],
            'fixturesWithRemoteManifestResources' => [],
            'fixturesWithExternalManifestItems' => [],
            'fixturesWithMissingLocalManifestItems' => [],
            'fixturesWithManifestFallbackItems' => [],
            'fixturesWithManifestFallbacks' => [],
            'fixturesWithResolvedManifestFallbacks' => [],
            'fixturesWithUsableManifestFallbacks' => [],
            'fixturesWithMissingManifestFallbacks' => [],
            'fixturesWithMediaOverlays' => [],
            'fixturesWithResolvedMediaOverlays' => [],
            'fixturesWithMediaOverlayTextTargets' => [],
            'fixturesWithMediaOverlayAudioTargets' => [],
            'fixturesWithNonLinearSpineItems' => [],
            'fixturesWithSpinePageSpreadItems' => [],
            'fixturesWithCollections' => [],
            'fixturesWithBindings' => [],
            'fixturesWithOcfSidecars' => [],
            'totals' => [
                'metadataCreators' => 0,
                'manifestItems' => 0,
                'readingOrderItems' => 0,
                'spinePageSpreadItems' => 0,
                'xhtmlAssets' => 0,
                'imageAssets' => 0,
                'stylesheetAssets' => 0,
                'navigationEntries' => 0,
                'landmarkEntries' => 0,
                'pageListEntries' => 0,
                'pageListCfiTargets' => 0,
                'auxiliaryNavigationEntries' => 0,
                'packageLinks' => 0,
                'packageLinkVocabularyRelTokens' => 0,
                'packageLinkVocabularyPropertyTokens' => 0,
                'packageLinkVocabularyResolvedTokens' => 0,
                'packageLinkVocabularyAbsoluteUrlTokens' => 0,
                'packageLinkVocabularyDuplicateTokens' => 0,
                'packageLinkVocabularyDiagnostics' => 0,
                'packageLinkMediaTypeItems' => 0,
                'packageLinkMediaTypeParameters' => 0,
                'linkHrefSuffixes' => 0,
                'linkHrefSuffixQueries' => 0,
                'linkHrefSuffixFragments' => 0,
                'guideReferences' => 0,
                'accessibilityEntries' => 0,
                'accessibilityLinkedRecords' => 0,
                'accessibilityAccessModes' => 0,
                'accessibilityFeatures' => 0,
                'accessibilityHazards' => 0,
                'accessibilityConformsTo' => 0,
                'remoteResourceManifestItems' => 0,
                'externalManifestItems' => 0,
                'missingLocalManifestItems' => 0,
                'manifestFallbackItems' => 0,
                'manifestFallbacks' => 0,
                'resolvedManifestFallbacks' => 0,
                'usableManifestFallbacks' => 0,
                'missingManifestFallbacks' => 0,
                'mediaOverlays' => 0,
                'resolvedMediaOverlays' => 0,
                'missingMediaOverlays' => 0,
                'mediaOverlayReferencedContentItems' => 0,
                'mediaOverlayTimelineItems' => 0,
                'mediaOverlayClipTimings' => 0,
                'mediaOverlayValidClipTimings' => 0,
                'mediaOverlayInvalidClipTimings' => 0,
                'mediaOverlayTextLocalTargets' => 0,
                'mediaOverlayAudioLocalTargets' => 0,
                'mediaOverlayDurations' => 0,
                'encryptionItems' => 0,
                'obfuscatedFonts' => 0,
                'blockedEncryptedByteExposures' => 0,
                'encryptionDiagnostics' => 0,
                'collections' => 0,
                'collectionLinks' => 0,
                'bindingItems' => 0,
                'bindingResolvedHandlers' => 0,
                'bindingMediaTypeParameters' => 0,
                'ocfSidecars' => 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $coverage
     */
    private static function packageFeatureCoverageMatchesExpected(array $coverage): bool
    {
        foreach (self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE as $key => $expected) {
            if (($coverage[$key] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $fixtureIdentity
     * @param array<string, mixed> $coverage
     * @return array<string, mixed>
     */
    private static function packageFeatureSignature(array $fixtureIdentity, array $coverage): array
    {
        $payload = self::packageFeatureSignaturePayload($fixtureIdentity, $coverage);
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $fixtureValidation = is_array($fixtureIdentity['validation'] ?? null) ? $fixtureIdentity['validation'] : [];
        $fixtureIdentityMatchesExpected = ($fixtureValidation['status'] ?? null) === 'valid-checked-in-current-epub-fixture-identity'
            && ($fixtureValidation['issues'] ?? null) === [];
        $coverageMatchesExpected = self::packageFeatureCoverageMatchesExpected($coverage);
        $hashMatchesExpected = $sha256 === self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256;
        $issues = [];
        if (!$fixtureIdentityMatchesExpected) {
            $issues[] = 'fixture-identity-does-not-match-expected-snapshot';
        }
        if (!$coverageMatchesExpected) {
            $issues[] = 'package-feature-coverage-does-not-match-expected-snapshot';
        }
        if (!$hashMatchesExpected) {
            $issues[] = 'package-feature-signature-sha256-mismatch';
        }

        return [
            'kind' => self::PACKAGE_FEATURE_SIGNATURE_KIND,
            'algorithm' => self::PACKAGE_FEATURE_SIGNATURE_ALGORITHM,
            'scope' => self::PACKAGE_FEATURE_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'coverageKeys' => array_keys(self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE),
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256,
            'hashMatchesExpected' => $hashMatchesExpected,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-package-feature-signature'
                    : 'invalid-checked-in-current-epub-package-feature-signature',
                'issues' => $issues,
                'fixtureIdentityStatus' => (string) ($fixtureValidation['status'] ?? 'unknown'),
                'packageFeatureCoverageMatchesExpected' => $coverageMatchesExpected,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedPackageFeatureSignature(string $reason): array
    {
        return [
            'kind' => self::PACKAGE_FEATURE_SIGNATURE_KIND,
            'algorithm' => self::PACKAGE_FEATURE_SIGNATURE_ALGORITHM,
            'scope' => self::PACKAGE_FEATURE_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'coverageKeys' => array_keys(self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE),
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256,
            'hashMatchesExpected' => false,
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'fixtureIdentityStatus' => 'not-evaluated-source-directory-unavailable',
                'packageFeatureCoverageMatchesExpected' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $fixtureIdentity
     * @param array<string, array<string, mixed>> $fixtureSignatures
     * @param array<string, array<string, mixed>> $payloadFixtures
     * @return array<string, mixed>
     */
    private static function currentNativeAstSignature(
        array $fixtureIdentity,
        array $fixtureSignatures,
        array $payloadFixtures,
        int $comparedPairCount,
        int $astParseFailureCount,
        int $normalizedAstMismatchCount
    ): array {
        ksort($fixtureSignatures, SORT_STRING);
        ksort($payloadFixtures, SORT_STRING);
        $expectedFixtures = self::expectedCheckedInCurrentPairNames();
        $observedFixtures = array_keys($fixtureSignatures);
        sort($observedFixtures, SORT_STRING);

        $payload = self::currentNativeAstSignaturePayload($fixtureIdentity, $payloadFixtures, $expectedFixtures);
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $fixtureValidation = is_array($fixtureIdentity['validation'] ?? null) ? $fixtureIdentity['validation'] : [];
        $fixtureIdentityMatchesExpected = ($fixtureValidation['status'] ?? null) === 'valid-checked-in-current-epub-fixture-identity'
            && ($fixtureValidation['issues'] ?? null) === [];
        $fixturesMatchExpected = $observedFixtures === $expectedFixtures;
        $astComparisonMatchesExpected = $fixturesMatchExpected
            && $comparedPairCount === count($expectedFixtures)
            && $astParseFailureCount === 0
            && $normalizedAstMismatchCount === 0
            && self::nativeAstFixtureHashesMatch($fixtureSignatures);
        $hashMatchesExpected = $sha256 === self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256;
        $issues = [];
        if (!$fixtureIdentityMatchesExpected) {
            $issues[] = 'fixture-identity-does-not-match-expected-snapshot';
        }
        if (!$fixturesMatchExpected) {
            $issues[] = 'normalized-native-ast-fixtures-do-not-match-expected-snapshot';
        }
        if (!$astComparisonMatchesExpected) {
            $issues[] = 'normalized-native-ast-comparison-does-not-match-expected-snapshot';
        }
        if (!$hashMatchesExpected) {
            $issues[] = 'normalized-native-ast-signature-sha256-mismatch';
        }

        return [
            'kind' => self::CURRENT_NATIVE_AST_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_NATIVE_AST_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'fixtureCount' => count($fixtureSignatures),
            'expectedFixtureCount' => count($expectedFixtures),
            'expectedFixtures' => $expectedFixtures,
            'observedFixtures' => $observedFixtures,
            'fixtureSignatures' => $fixtureSignatures,
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256,
            'hashMatchesExpected' => $hashMatchesExpected,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-normalized-native-ast-signature'
                    : 'invalid-checked-in-current-epub-normalized-native-ast-signature',
                'issues' => $issues,
                'fixtureIdentityStatus' => (string) ($fixtureValidation['status'] ?? 'unknown'),
                'fixturesMatchExpected' => $fixturesMatchExpected,
                'normalizedAstComparisonMatchesExpected' => $astComparisonMatchesExpected,
                'comparedPairCount' => $comparedPairCount,
                'astParseFailureCount' => $astParseFailureCount,
                'normalizedAstMismatchCount' => $normalizedAstMismatchCount,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedCurrentNativeAstSignature(string $reason): array
    {
        return [
            'kind' => self::CURRENT_NATIVE_AST_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_NATIVE_AST_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'fixtureCount' => 0,
            'expectedFixtureCount' => count(self::expectedCheckedInCurrentPairNames()),
            'expectedFixtures' => self::expectedCheckedInCurrentPairNames(),
            'observedFixtures' => [],
            'fixtureSignatures' => [],
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256,
            'hashMatchesExpected' => false,
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'fixtureIdentityStatus' => 'not-evaluated-source-directory-unavailable',
                'fixturesMatchExpected' => false,
                'normalizedAstComparisonMatchesExpected' => false,
                'comparedPairCount' => 0,
                'astParseFailureCount' => 0,
                'normalizedAstMismatchCount' => 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $fixtureIdentity
     * @param array<string, array<string, mixed>> $payloadFixtures
     * @param list<string> $expectedFixtures
     * @return array<string, mixed>
     */
    private static function currentNativeAstSignaturePayload(array $fixtureIdentity, array $payloadFixtures, array $expectedFixtures): array
    {
        $files = [];
        foreach (is_array($fixtureIdentity['files'] ?? null) ? $fixtureIdentity['files'] : [] as $file) {
            if (!is_array($file)) {
                continue;
            }
            $bytes = $file['bytes'] ?? null;
            $files[] = [
                'path' => (string) ($file['path'] ?? ''),
                'sha256' => is_string($file['sha256'] ?? null) ? $file['sha256'] : null,
                'bytes' => is_int($bytes) ? $bytes : (is_numeric($bytes) ? (int) $bytes : null),
            ];
        }
        usort(
            $files,
            static fn (array $left, array $right): int => (string) ($left['path'] ?? '') <=> (string) ($right['path'] ?? '')
        );

        $fixtures = [];
        foreach ($payloadFixtures as $fixture => $payloadFixture) {
            $fixtures[] = [
                'fixture' => is_string($payloadFixture['fixture'] ?? null) ? $payloadFixture['fixture'] : (string) $fixture,
                'epubNormalizedAst' => $payloadFixture['epubNormalizedAst'] ?? null,
                'nativeNormalizedAst' => $payloadFixture['nativeNormalizedAst'] ?? null,
            ];
        }
        usort(
            $fixtures,
            static fn (array $left, array $right): int => (string) ($left['fixture'] ?? '') <=> (string) ($right['fixture'] ?? '')
        );

        return [
            'schemaVersion' => 1,
            'fixtureIdentity' => [
                'kind' => (string) ($fixtureIdentity['kind'] ?? ''),
                'expectedFileCount' => (int) ($fixtureIdentity['expectedFileCount'] ?? 0),
                'observedFileCount' => (int) ($fixtureIdentity['observedFileCount'] ?? 0),
                'expectedFiles' => self::stringList($fixtureIdentity['expectedFiles'] ?? []),
                'observedFiles' => self::stringList($fixtureIdentity['observedFiles'] ?? []),
                'files' => $files,
            ],
            'expectedFixtures' => $expectedFixtures,
            'normalizedNativeAstFixtures' => $fixtures,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $fixtureSignatures
     */
    private static function nativeAstFixtureHashesMatch(array $fixtureSignatures): bool
    {
        if ($fixtureSignatures === []) {
            return false;
        }

        foreach ($fixtureSignatures as $signature) {
            if (($signature['normalizedAstMatches'] ?? null) !== true) {
                return false;
            }
            if (
                !is_string($signature['epubNormalizedAstSha256'] ?? null)
                || !is_string($signature['nativeNormalizedAstSha256'] ?? null)
                || $signature['epubNormalizedAstSha256'] !== $signature['nativeNormalizedAstSha256']
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function expectedCheckedInCurrentPairNames(): array
    {
        $epubFixtures = [];
        $nativeFixtures = [];
        foreach (array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES) as $path) {
            if (str_ends_with($path, '.epub')) {
                $epubFixtures[basename($path, '.epub')] = true;
            } elseif (str_ends_with($path, '.native')) {
                $nativeFixtures[basename($path, '.native')] = true;
            }
        }

        $pairs = array_values(array_intersect(array_keys($epubFixtures), array_keys($nativeFixtures)));
        sort($pairs, SORT_STRING);

        return $pairs;
    }

    /**
     * @param array<string, mixed> $fixtureIdentity
     * @param array<string, mixed> $coverage
     * @return array<string, mixed>
     */
    private static function packageFeatureSignaturePayload(array $fixtureIdentity, array $coverage): array
    {
        $files = [];
        foreach (is_array($fixtureIdentity['files'] ?? null) ? $fixtureIdentity['files'] : [] as $file) {
            if (!is_array($file)) {
                continue;
            }
            $bytes = $file['bytes'] ?? null;
            $files[] = [
                'path' => (string) ($file['path'] ?? ''),
                'sha256' => is_string($file['sha256'] ?? null) ? $file['sha256'] : null,
                'bytes' => is_int($bytes) ? $bytes : (is_numeric($bytes) ? (int) $bytes : null),
            ];
        }
        usort(
            $files,
            static fn (array $left, array $right): int => (string) ($left['path'] ?? '') <=> (string) ($right['path'] ?? '')
        );

        return [
            'schemaVersion' => 1,
            'fixtureIdentity' => [
                'kind' => (string) ($fixtureIdentity['kind'] ?? ''),
                'expectedFileCount' => (int) ($fixtureIdentity['expectedFileCount'] ?? 0),
                'observedFileCount' => (int) ($fixtureIdentity['observedFileCount'] ?? 0),
                'expectedFiles' => self::stringList($fixtureIdentity['expectedFiles'] ?? []),
                'observedFiles' => self::stringList($fixtureIdentity['observedFiles'] ?? []),
                'files' => $files,
            ],
            'packageFeatureCoverage' => self::packageFeatureCoverageSignatureSnapshot($coverage),
        ];
    }

    /**
     * @param array<string, mixed> $coverage
     * @return array<string, mixed>
     */
    private static function packageFeatureCoverageSignatureSnapshot(array $coverage): array
    {
        $snapshot = [
            'kind' => (string) ($coverage['kind'] ?? ''),
        ];
        foreach (self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE as $key => $_expected) {
            $snapshot[$key] = $coverage[$key] ?? null;
        }

        return $snapshot;
    }

    private static function canonicalJson(mixed $value): string
    {
        $json = json_encode(
            self::canonicalValue($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode package feature signature payload.');
        }

        return $json;
    }

    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalValue($item), $value);
        }

        $normalized = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $normalized[(string) $key] = self::canonicalValue($value[$key]);
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function filesByBasename(string $directory, string $extension): array
    {
        $files = [];
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
            $files[basename($path, '.' . $extension)] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureIdentity(string $directory): array
    {
        $observedFiles = [];
        foreach (['epub', 'native'] as $extension) {
            foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
                $observedFiles[basename($path)] = $path;
            }
        }
        ksort($observedFiles, SORT_STRING);

        $files = [];
        $missingFiles = [];
        $changedFiles = [];
        foreach (self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES as $relativePath => $expected) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
            $present = is_file($path);
            $actualSha256 = $present ? hash_file('sha256', $path) : false;
            $size = $present ? filesize($path) : false;
            $actualBytes = is_int($size) ? $size : null;
            $sha256 = is_string($actualSha256) ? $actualSha256 : null;
            $matches = $present
                && $sha256 === $expected['sha256']
                && $actualBytes === $expected['bytes'];

            if (!$present) {
                $missingFiles[] = $relativePath;
            } elseif (!$matches) {
                $changedFiles[] = $relativePath;
            }

            $files[] = [
                'path' => $relativePath,
                'present' => $present,
                'sha256' => $sha256,
                'expectedSha256' => $expected['sha256'],
                'bytes' => $actualBytes,
                'expectedBytes' => $expected['bytes'],
                'matchesExpected' => $matches,
            ];
        }

        $unexpectedFiles = array_values(array_diff(array_keys($observedFiles), array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)));
        sort($unexpectedFiles, SORT_STRING);

        $issues = [];
        if (count($observedFiles) !== count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)) {
            $issues[] = 'fixture-file-count-does-not-match-expected-snapshot';
        }
        if ($missingFiles !== []) {
            $issues[] = 'missing-expected-fixture-files';
        }
        if ($unexpectedFiles !== []) {
            $issues[] = 'unexpected-fixture-files';
        }
        if ($changedFiles !== []) {
            $issues[] = 'fixture-hash-or-byte-count-mismatch';
        }

        return [
            'kind' => 'static-checked-in-current-epub-fixture-identity',
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFileCount' => count($observedFiles),
            'expectedFiles' => array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFiles' => array_keys($observedFiles),
            'files' => $files,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-epub-fixture-identity' : 'invalid-checked-in-current-epub-fixture-identity',
                'issues' => $issues,
                'missingFiles' => $missingFiles,
                'unexpectedFiles' => $unexpectedFiles,
                'changedFiles' => $changedFiles,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedFixtureIdentity(): array
    {
        return [
            'kind' => 'static-checked-in-current-epub-fixture-identity',
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFileCount' => 0,
            'expectedFiles' => array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFiles' => [],
            'files' => [],
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => ['source-directory-unavailable'],
                'missingFiles' => [],
                'unexpectedFiles' => [],
                'changedFiles' => [],
            ],
        ];
    }

    /**
     * @return array{ok: bool, package: ?EpubPackage, error: ?string}
     */
    private function readPackage(string $path): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read EPUB fixture '{$path}'.");
            }

            return ['ok' => true, 'package' => EpubPackage::fromString($bytes), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'package' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function packageSummary(string $fixture, EpubPackage $package): array
    {
        $assets = $package->assetSummary();
        $metadata = $package->metadata();
        $manifestItems = $package->manifestItems();
        $manifestCoverage = self::manifestItemCoverageSummary($manifestItems);
        $resourceKinds = $package->manifestResourceKinds();
        $spineItems = $package->readingOrder();
        $spineLinearStateCounts = [];
        $spinePageSpreadPlacementCounts = [];
        $spinePageSpreadItemCount = 0;
        foreach ($spineItems as $spineItem) {
            $state = (($spineItem['linear'] ?? true) === false) ? 'non-linear' : 'linear';
            $spineLinearStateCounts[$state] = (int) ($spineLinearStateCounts[$state] ?? 0) + 1;
            $pageSpread = is_string($spineItem['pageSpread'] ?? null) ? $spineItem['pageSpread'] : '';
            $pageSpreadProperties = is_array($spineItem['pageSpreadProperties'] ?? null)
                ? array_values($spineItem['pageSpreadProperties'])
                : [];
            if ($pageSpread !== '' || $pageSpreadProperties !== []) {
                ++$spinePageSpreadItemCount;
            }
            if ($pageSpread !== '') {
                $spinePageSpreadPlacementCounts[$pageSpread] = (int) ($spinePageSpreadPlacementCounts[$pageSpread] ?? 0) + 1;
            }
        }
        ksort($spineLinearStateCounts, SORT_STRING);
        ksort($spinePageSpreadPlacementCounts, SORT_STRING);
        $navigation = $package->navigation();
        $navigationSections = $package->navigationSections();
        $guideReferences = $package->guideReferences();
        $manifestFallbacks = $package->manifestFallbacks();
        $mediaOverlays = $package->mediaOverlays();
        $encryption = $package->encryption();
        $encryptionExposure = is_array($encryption['exposure'] ?? null) ? $encryption['exposure'] : [];
        $packageLinkMediaTypes = $package->packageLinkMediaTypes();
        $packageLinkVocabulary = is_array($metadata['linkVocabulary'] ?? null) ? $metadata['linkVocabulary'] : [];
        $linkHrefSuffixes = $package->linkHrefSuffixes();
        $accessibility = is_array($metadata['accessibility'] ?? null) ? $metadata['accessibility'] : [];
        $accessibilityPropertyCounts = self::accessibilityPropertyCounts($accessibility);
        $accessibilityCertification = is_array($accessibility['certification'] ?? null)
            ? $accessibility['certification']
            : [];
        $collections = self::collectionCoverageSummary($package->collections());
        $bindings = $package->bindings();
        $ocfSidecars = $package->ocfSidecars();
        $navigationSectionTypes = [];
        $landmarkEntryCount = 0;
        $pageListEntryCount = 0;
        $pageListCfiTargetCount = 0;
        $auxiliaryNavigationEntryCount = 0;

        foreach ($navigationSections as $section) {
            $types = is_array($section['types'] ?? null) ? $section['types'] : [];
            $entries = is_array($section['entries'] ?? null) ? $section['entries'] : [];
            foreach ($types as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }
                $navigationSectionTypes[$type] = true;
                if ($type === 'landmarks') {
                    $landmarkEntryCount += count($entries);
                } elseif ($type === 'page-list') {
                    $pageListEntryCount += count($entries);
                    $pageListCfiTargetCount += self::pageListCfiTargetCount($entries);
                } elseif ($type !== 'toc') {
                    $auxiliaryNavigationEntryCount += count($entries);
                }
            }
        }
        $navigationSectionTypes = array_keys($navigationSectionTypes);
        sort($navigationSectionTypes, SORT_STRING);

        return [
            'fixture' => $fixture,
            'opfPart' => $package->opfPartName(),
            'metadataTitle' => is_string($metadata['title'] ?? null) ? $metadata['title'] : '',
            'metadataLanguage' => is_string($metadata['language'] ?? null) ? $metadata['language'] : '',
            'metadataCreatorCount' => is_array($metadata['creators'] ?? null) ? count($metadata['creators']) : 0,
            'accessibilityPresent' => ($accessibility['present'] ?? false) === true,
            'accessibilityEntryCount' => is_array($accessibility['entries'] ?? null) ? count($accessibility['entries']) : 0,
            'accessibilityPropertyCounts' => $accessibilityPropertyCounts,
            'accessibilityLinkedRecordCount' => is_array($accessibility['linkedRecords'] ?? null) ? count($accessibility['linkedRecords']) : 0,
            'accessibilityAccessModeCount' => is_array($accessibility['accessModes'] ?? null) ? count($accessibility['accessModes']) : 0,
            'accessibilityFeatureCount' => is_array($accessibility['accessibilityFeatures'] ?? null) ? count($accessibility['accessibilityFeatures']) : 0,
            'accessibilityHazardCount' => is_array($accessibility['accessibilityHazards'] ?? null) ? count($accessibility['accessibilityHazards']) : 0,
            'accessibilityConformsToCount' => is_array($accessibilityCertification['conformsTo'] ?? null)
                ? count($accessibilityCertification['conformsTo'])
                : 0,
            'packageLinkCount' => count($package->packageLinks()),
            'packageLinkRelCounts' => self::packageLinkRelCounts($package->packageLinks()),
            'packageLinkVocabularyRelTokenCount' => (int) ($packageLinkVocabulary['relTokenCount'] ?? 0),
            'packageLinkVocabularyPropertyTokenCount' => (int) ($packageLinkVocabulary['propertyTokenCount'] ?? 0),
            'packageLinkVocabularyResolvedTokenCount' => (int) ($packageLinkVocabulary['resolvedTokenCount'] ?? 0),
            'packageLinkVocabularyAbsoluteUrlTokenCount' => (int) ($packageLinkVocabulary['absoluteUrlTokenCount'] ?? 0),
            'packageLinkVocabularyDuplicateTokenCount' => (int) ($packageLinkVocabulary['duplicateTokenCount'] ?? 0),
            'packageLinkVocabularyDiagnosticCount' => (int) ($packageLinkVocabulary['diagnosticCount'] ?? 0),
            'packageLinkVocabularyRelCounts' => self::intCountMap($packageLinkVocabulary['rels'] ?? []),
            'packageLinkVocabularyPropertyCounts' => self::intCountMap($packageLinkVocabulary['properties'] ?? []),
            'packageLinkMediaTypeCount' => (int) ($packageLinkMediaTypes['itemCount'] ?? 0),
            'packageLinkMediaTypeCounts' => self::packageLinkMediaTypeCounts($packageLinkMediaTypes),
            'packageLinkMediaTypeParameterCount' => (int) ($packageLinkMediaTypes['parameterCount'] ?? 0),
            'packageLinkMediaTypeParameterNameCounts' => self::packageLinkMediaTypeParameterNameCounts($packageLinkMediaTypes),
            'linkHrefSuffixCount' => (int) ($linkHrefSuffixes['itemCount'] ?? 0),
            'linkHrefSuffixQueryCount' => (int) ($linkHrefSuffixes['queryCount'] ?? 0),
            'linkHrefSuffixFragmentCount' => (int) ($linkHrefSuffixes['fragmentCount'] ?? 0),
            'linkHrefSuffixSourceCounts' => is_array($linkHrefSuffixes['sourceCounts'] ?? null) ? $linkHrefSuffixes['sourceCounts'] : [],
            'guideReferenceCount' => count($guideReferences),
            'guideReferenceTypeCounts' => self::guideReferenceTypeCounts($guideReferences),
            'manifestItemCount' => count($manifestItems),
            'manifestMediaTypeCounts' => $manifestCoverage['mediaTypeCounts'],
            'manifestPropertyCounts' => $manifestCoverage['propertyCounts'],
            'manifestResourceKindCounts' => is_array($resourceKinds['kindCounts'] ?? null) ? $resourceKinds['kindCounts'] : [],
            'remoteResourceManifestItemCount' => $manifestCoverage['remoteResourceItemCount'],
            'externalManifestItemCount' => $manifestCoverage['externalItemCount'],
            'missingLocalManifestItemCount' => $manifestCoverage['missingLocalItemCount'],
            'manifestFallbackItemCount' => (int) ($manifestFallbacks['itemCount'] ?? 0),
            'manifestFallbackCount' => (int) ($manifestFallbacks['fallbackCount'] ?? 0),
            'resolvedManifestFallbackCount' => (int) ($manifestFallbacks['resolvedFallbackCount'] ?? 0),
            'usableManifestFallbackCount' => (int) ($manifestFallbacks['usableFallbackCount'] ?? 0),
            'missingManifestFallbackCount' => (int) ($manifestFallbacks['missingFallbackCount'] ?? 0),
            'mediaOverlayCount' => (int) ($mediaOverlays['overlayCount'] ?? 0),
            'resolvedMediaOverlayCount' => (int) ($mediaOverlays['resolvedOverlayCount'] ?? 0),
            'missingMediaOverlayCount' => (int) ($mediaOverlays['missingOverlayCount'] ?? 0),
            'mediaOverlayReferencedContentItemCount' => (int) ($mediaOverlays['referencedContentItemCount'] ?? 0),
            'mediaOverlayTimelineItemCount' => self::mediaOverlayTimelineItemCount($mediaOverlays),
            'mediaOverlayClipTimingCount' => self::mediaOverlayClipTimingCount($mediaOverlays),
            'mediaOverlayValidClipTimingCount' => self::mediaOverlayClipTimingCount($mediaOverlays, true),
            'mediaOverlayInvalidClipTimingCount' => self::mediaOverlayClipTimingCount($mediaOverlays, false),
            'mediaOverlayTextLocalTargetCount' => (int) ($mediaOverlays['textLocalTargetCount'] ?? 0),
            'mediaOverlayAudioLocalTargetCount' => (int) ($mediaOverlays['audioLocalTargetCount'] ?? 0),
            'mediaOverlayDurationCount' => (int) ($mediaOverlays['durationCount'] ?? 0),
            'encryptionPresent' => ($encryption['present'] ?? false) === true,
            'encryptionItemCount' => is_array($encryption['items'] ?? null) ? count($encryption['items']) : 0,
            'obfuscatedFontCount' => is_array($encryption['obfuscatedFonts'] ?? null) ? count($encryption['obfuscatedFonts']) : 0,
            'blockedEncryptedByteExposureCount' => (int) ($encryptionExposure['blockedByteExposureCount'] ?? 0),
            'encryptionDiagnosticCount' => is_array($encryption['diagnostics'] ?? null) ? count($encryption['diagnostics']) : 0,
            'encryptionRoleCounts' => is_array($encryptionExposure['roleCounts'] ?? null) ? $encryptionExposure['roleCounts'] : [],
            'collectionCount' => $collections['collectionCount'],
            'collectionLinkCount' => $collections['collectionLinkCount'],
            'collectionRoleCounts' => $collections['collectionRoleCounts'],
            'collectionLinkRelCounts' => $collections['collectionLinkRelCounts'],
            'bindingCount' => (int) ($bindings['itemCount'] ?? 0),
            'bindingMediaTypeCounts' => self::bindingMediaTypeCounts($bindings),
            'bindingResolvedHandlerCount' => self::bindingResolvedHandlerCount($bindings),
            'bindingMediaTypeParameterCount' => (int) ($bindings['mediaTypeParameterCount'] ?? 0),
            'ocfSidecarCount' => (int) ($ocfSidecars['sidecarCount'] ?? 0),
            'ocfSidecarKindCounts' => self::ocfSidecarKindCounts($ocfSidecars),
            'readingOrderCount' => count($spineItems),
            'spineLinearStateCounts' => $spineLinearStateCounts,
            'spinePageSpreadItemCount' => $spinePageSpreadItemCount,
            'spinePageSpreadPlacementCounts' => $spinePageSpreadPlacementCounts,
            'xhtmlAssetCount' => count($assets['xhtmlParts']),
            'imageAssetCount' => count($assets['imageParts']),
            'stylesheetAssetCount' => count($assets['stylesheetParts']),
            'navigationType' => is_array($navigation) ? (string) ($navigation['type'] ?? '') : null,
            'navigationEntryCount' => is_array($navigation) && is_array($navigation['entries'] ?? null) ? count($navigation['entries']) : 0,
            'navigationSectionCount' => count($navigationSections),
            'navigationSectionTypes' => $navigationSectionTypes,
            'landmarkEntryCount' => $landmarkEntryCount,
            'pageListEntryCount' => $pageListEntryCount,
            'pageListCfiTargetCount' => $pageListCfiTargetCount,
            'auxiliaryNavigationEntryCount' => $auxiliaryNavigationEntryCount,
            'coverImagePart' => $assets['coverImagePart'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private static function pageListCfiTargetCount(array $entries): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['epubCfi'] ?? false) === true) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $accessibility
     * @return array<string, int>
     */
    private static function accessibilityPropertyCounts(array $accessibility): array
    {
        $counts = [];
        foreach (is_array($accessibility['entries'] ?? null) ? $accessibility['entries'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $property = is_string($entry['property'] ?? null) ? $entry['property'] : '';
            if ($property !== '') {
                $counts[$property] = (int) ($counts[$property] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, int>
     */
    private static function packageLinkMediaTypeCounts(array $report): array
    {
        $counts = [];
        foreach (is_array($report['items'] ?? null) ? $report['items'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $mediaType = is_string($item['baseMediaType'] ?? null) ? strtolower(trim($item['baseMediaType'])) : '';
            if ($mediaType !== '') {
                $counts[$mediaType] = (int) ($counts[$mediaType] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, int>
     */
    private static function packageLinkMediaTypeParameterNameCounts(array $report): array
    {
        $counts = [];
        foreach (is_array($report['parameterItems'] ?? null) ? $report['parameterItems'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach (is_array($item['parameterNames'] ?? null) ? $item['parameterNames'] : [] as $name) {
                if (is_string($name) && $name !== '') {
                    $counts[$name] = (int) ($counts[$name] ?? 0) + 1;
                }
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @return array{collectionCount: int, collectionLinkCount: int, collectionRoleCounts: array<string, int>, collectionLinkRelCounts: array<string, int>}
     */
    private static function collectionCoverageSummary(array $collections): array
    {
        $summary = [
            'collectionCount' => 0,
            'collectionLinkCount' => 0,
            'collectionRoleCounts' => [],
            'collectionLinkRelCounts' => [],
        ];
        self::appendCollectionCoverageSummary($collections, $summary);
        ksort($summary['collectionRoleCounts'], SORT_STRING);
        ksort($summary['collectionLinkRelCounts'], SORT_STRING);

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param array{collectionCount: int, collectionLinkCount: int, collectionRoleCounts: array<string, int>, collectionLinkRelCounts: array<string, int>} $summary
     */
    private static function appendCollectionCoverageSummary(array $collections, array &$summary): void
    {
        foreach ($collections as $collection) {
            if (!is_array($collection)) {
                continue;
            }

            ++$summary['collectionCount'];
            foreach (is_array($collection['roleTokens'] ?? null) ? $collection['roleTokens'] : [] as $role) {
                if (is_string($role) && $role !== '') {
                    $summary['collectionRoleCounts'][$role] = (int) ($summary['collectionRoleCounts'][$role] ?? 0) + 1;
                }
            }

            foreach (is_array($collection['links'] ?? null) ? $collection['links'] : [] as $link) {
                if (!is_array($link)) {
                    continue;
                }

                ++$summary['collectionLinkCount'];
                foreach (is_array($link['rel'] ?? null) ? $link['rel'] : [] as $rel) {
                    if (is_string($rel) && $rel !== '') {
                        $summary['collectionLinkRelCounts'][$rel] = (int) ($summary['collectionLinkRelCounts'][$rel] ?? 0) + 1;
                    }
                }
            }

            self::appendCollectionCoverageSummary(
                is_array($collection['children'] ?? null) ? $collection['children'] : [],
                $summary
            );
        }
    }

    /**
     * @param array<string, mixed> $bindings
     * @return array<string, int>
     */
    private static function bindingMediaTypeCounts(array $bindings): array
    {
        $counts = [];
        foreach (is_array($bindings['items'] ?? null) ? $bindings['items'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $mediaType = is_string($item['baseMediaType'] ?? null)
                ? $item['baseMediaType']
                : (is_string($item['mediaType'] ?? null) ? explode(';', $item['mediaType'], 2)[0] : '');
            $mediaType = strtolower(trim($mediaType));
            if ($mediaType !== '') {
                $counts[$mediaType] = (int) ($counts[$mediaType] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private static function bindingResolvedHandlerCount(array $bindings): int
    {
        $count = 0;
        foreach (is_array($bindings['items'] ?? null) ? $bindings['items'] : [] as $item) {
            if (is_array($item) && ($item['handlerExists'] ?? false) === true) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $ocfSidecars
     * @return array<string, int>
     */
    private static function ocfSidecarKindCounts(array $ocfSidecars): array
    {
        $counts = [];
        foreach (is_array($ocfSidecars['kinds'] ?? null) ? $ocfSidecars['kinds'] : [] as $kind) {
            if (is_string($kind) && $kind !== '') {
                $counts[$kind] = (int) ($counts[$kind] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<string, mixed> $mediaOverlays
     */
    private static function mediaOverlayTimelineItemCount(array $mediaOverlays): int
    {
        $count = 0;
        foreach (is_array($mediaOverlays['items'] ?? null) ? $mediaOverlays['items'] : [] as $overlay) {
            if (is_array($overlay)) {
                $count += count(is_array($overlay['items'] ?? null) ? $overlay['items'] : []);
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $mediaOverlays
     */
    private static function mediaOverlayClipTimingCount(array $mediaOverlays, ?bool $valid = null): int
    {
        $count = 0;
        foreach (is_array($mediaOverlays['items'] ?? null) ? $mediaOverlays['items'] : [] as $overlay) {
            if (!is_array($overlay)) {
                continue;
            }

            foreach (is_array($overlay['items'] ?? null) ? $overlay['items'] : [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $hasClipTiming = is_string($item['clipBegin'] ?? null) || is_string($item['clipEnd'] ?? null);
                if (!$hasClipTiming) {
                    continue;
                }

                if ($valid === null || (($item['clipValid'] ?? false) === $valid)) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $summaries
     * @return array<string, mixed>
     */
    private static function packageFeatureCoverage(array $summaries): array
    {
        $coverage = self::emptyPackageFeatureCoverage();
        $coverage['fixtureCount'] = count($summaries);
        $opfPartNameCounts = [];
        $metadataLanguageCounts = [];
        $navigationTypeCounts = [];
        $spineLinearStateCounts = [];
        $spinePageSpreadPlacementCounts = [];
        $manifestMediaTypeCounts = [];
        $manifestPropertyCounts = [];
        $manifestResourceKindCounts = [];
        $guideReferenceTypeCounts = [];
        $packageLinkRelCounts = [];
        $packageLinkVocabularyRelCounts = [];
        $packageLinkVocabularyPropertyCounts = [];
        $packageLinkMediaTypeCounts = [];
        $packageLinkMediaTypeParameterNameCounts = [];
        $linkHrefSuffixSourceCounts = [];
        $accessibilityPropertyCounts = [];
        $encryptionRoleCounts = [];
        $collectionRoleCounts = [];
        $collectionLinkRelCounts = [];
        $bindingMediaTypeCounts = [];
        $ocfSidecarKindCounts = [];
        $navigationSectionTypes = [];
        $fixtureFeatureSignatures = [];

        foreach ($summaries as $summary) {
            $fixture = is_string($summary['fixture'] ?? null) ? $summary['fixture'] : '';
            $opfPart = is_string($summary['opfPart'] ?? null) ? $summary['opfPart'] : '';
            if ($opfPart !== '') {
                $opfPartNameCounts[$opfPart] = (int) ($opfPartNameCounts[$opfPart] ?? 0) + 1;
            }

            $language = is_string($summary['metadataLanguage'] ?? null) ? $summary['metadataLanguage'] : '';
            if ($language !== '') {
                $metadataLanguageCounts[$language] = (int) ($metadataLanguageCounts[$language] ?? 0) + 1;
            }

            $navigationType = is_string($summary['navigationType'] ?? null) ? $summary['navigationType'] : '';
            if ($navigationType !== '') {
                $navigationTypeCounts[$navigationType] = (int) ($navigationTypeCounts[$navigationType] ?? 0) + 1;
            }

            foreach (is_array($summary['spineLinearStateCounts'] ?? null) ? $summary['spineLinearStateCounts'] : [] as $state => $count) {
                if (is_string($state) && $state !== '') {
                    $spineLinearStateCounts[$state] = (int) ($spineLinearStateCounts[$state] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['spinePageSpreadPlacementCounts'] ?? null) ? $summary['spinePageSpreadPlacementCounts'] : [] as $placement => $count) {
                if (is_string($placement) && $placement !== '') {
                    $spinePageSpreadPlacementCounts[$placement] = (int) ($spinePageSpreadPlacementCounts[$placement] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['manifestMediaTypeCounts'] ?? null) ? $summary['manifestMediaTypeCounts'] : [] as $mediaType => $count) {
                if (is_string($mediaType) && $mediaType !== '') {
                    $manifestMediaTypeCounts[$mediaType] = (int) ($manifestMediaTypeCounts[$mediaType] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['manifestPropertyCounts'] ?? null) ? $summary['manifestPropertyCounts'] : [] as $property => $count) {
                if (is_string($property) && $property !== '') {
                    $manifestPropertyCounts[$property] = (int) ($manifestPropertyCounts[$property] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['manifestResourceKindCounts'] ?? null) ? $summary['manifestResourceKindCounts'] : [] as $kind => $count) {
                if (is_string($kind) && $kind !== '') {
                    $manifestResourceKindCounts[$kind] = (int) ($manifestResourceKindCounts[$kind] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['navigationSectionTypes'] ?? null) ? $summary['navigationSectionTypes'] : [] as $type) {
                if (is_string($type) && $type !== '') {
                    $navigationSectionTypes[$type] = true;
                }
            }

            foreach (is_array($summary['guideReferenceTypeCounts'] ?? null) ? $summary['guideReferenceTypeCounts'] : [] as $type => $count) {
                if (is_string($type) && $type !== '') {
                    $guideReferenceTypeCounts[$type] = (int) ($guideReferenceTypeCounts[$type] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['packageLinkRelCounts'] ?? null) ? $summary['packageLinkRelCounts'] : [] as $rel => $count) {
                if (is_string($rel) && $rel !== '') {
                    $packageLinkRelCounts[$rel] = (int) ($packageLinkRelCounts[$rel] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['packageLinkVocabularyRelCounts'] ?? null) ? $summary['packageLinkVocabularyRelCounts'] : [] as $rel => $count) {
                if (is_string($rel) && $rel !== '') {
                    $packageLinkVocabularyRelCounts[$rel] = (int) ($packageLinkVocabularyRelCounts[$rel] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['packageLinkVocabularyPropertyCounts'] ?? null) ? $summary['packageLinkVocabularyPropertyCounts'] : [] as $property => $count) {
                if (is_string($property) && $property !== '') {
                    $packageLinkVocabularyPropertyCounts[$property] = (int) ($packageLinkVocabularyPropertyCounts[$property] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['packageLinkMediaTypeCounts'] ?? null) ? $summary['packageLinkMediaTypeCounts'] : [] as $mediaType => $count) {
                if (is_string($mediaType) && $mediaType !== '') {
                    $packageLinkMediaTypeCounts[$mediaType] = (int) ($packageLinkMediaTypeCounts[$mediaType] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['packageLinkMediaTypeParameterNameCounts'] ?? null) ? $summary['packageLinkMediaTypeParameterNameCounts'] : [] as $name => $count) {
                if (is_string($name) && $name !== '') {
                    $packageLinkMediaTypeParameterNameCounts[$name] = (int) ($packageLinkMediaTypeParameterNameCounts[$name] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['linkHrefSuffixSourceCounts'] ?? null) ? $summary['linkHrefSuffixSourceCounts'] : [] as $source => $count) {
                if (is_string($source) && $source !== '') {
                    $linkHrefSuffixSourceCounts[$source] = (int) ($linkHrefSuffixSourceCounts[$source] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['accessibilityPropertyCounts'] ?? null) ? $summary['accessibilityPropertyCounts'] : [] as $property => $count) {
                if (is_string($property) && $property !== '') {
                    $accessibilityPropertyCounts[$property] = (int) ($accessibilityPropertyCounts[$property] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['encryptionRoleCounts'] ?? null) ? $summary['encryptionRoleCounts'] : [] as $role => $count) {
                if (is_string($role) && $role !== '') {
                    $encryptionRoleCounts[$role] = (int) ($encryptionRoleCounts[$role] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['collectionRoleCounts'] ?? null) ? $summary['collectionRoleCounts'] : [] as $role => $count) {
                if (is_string($role) && $role !== '') {
                    $collectionRoleCounts[$role] = (int) ($collectionRoleCounts[$role] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['collectionLinkRelCounts'] ?? null) ? $summary['collectionLinkRelCounts'] : [] as $rel => $count) {
                if (is_string($rel) && $rel !== '') {
                    $collectionLinkRelCounts[$rel] = (int) ($collectionLinkRelCounts[$rel] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['bindingMediaTypeCounts'] ?? null) ? $summary['bindingMediaTypeCounts'] : [] as $mediaType => $count) {
                if (is_string($mediaType) && $mediaType !== '') {
                    $bindingMediaTypeCounts[$mediaType] = (int) ($bindingMediaTypeCounts[$mediaType] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['ocfSidecarKindCounts'] ?? null) ? $summary['ocfSidecarKindCounts'] : [] as $kind => $count) {
                if (is_string($kind) && $kind !== '') {
                    $ocfSidecarKindCounts[$kind] = (int) ($ocfSidecarKindCounts[$kind] ?? 0) + (int) $count;
                }
            }

            if ($fixture !== '') {
                $fixtureFeatureSignatures[$fixture] = self::fixtureFeatureSignature($summary);
            }
            if ($fixture !== '' && (int) ($summary['guideReferenceCount'] ?? 0) > 0) {
                $coverage['fixturesWithGuideReferences'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['packageLinkCount'] ?? 0) > 0) {
                $coverage['fixturesWithPackageLinks'][] = $fixture;
            }
            if (
                $fixture !== ''
                && (
                    (int) ($summary['packageLinkVocabularyRelTokenCount'] ?? 0)
                    + (int) ($summary['packageLinkVocabularyPropertyTokenCount'] ?? 0)
                ) > 0
            ) {
                $coverage['fixturesWithPackageLinkVocabulary'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['packageLinkVocabularyDiagnosticCount'] ?? 0) > 0) {
                $coverage['fixturesWithPackageLinkVocabularyDiagnostics'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['packageLinkMediaTypeParameterCount'] ?? 0) > 0) {
                $coverage['fixturesWithPackageLinkMediaTypeParameters'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['linkHrefSuffixCount'] ?? 0) > 0) {
                $coverage['fixturesWithLinkHrefSuffixes'][] = $fixture;
            }
            if ($fixture !== '' && ($summary['accessibilityPresent'] ?? false) === true) {
                $coverage['fixturesWithAccessibilityMetadata'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['metadataCreatorCount'] ?? 0) > 0) {
                $coverage['fixturesWithCreators'][] = $fixture;
            }
            if ($fixture !== '' && (($summary['encryptionPresent'] ?? false) === true || (int) ($summary['encryptionItemCount'] ?? 0) > 0)) {
                $coverage['fixturesWithEncryption'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['obfuscatedFontCount'] ?? 0) > 0) {
                $coverage['fixturesWithObfuscatedFonts'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['blockedEncryptedByteExposureCount'] ?? 0) > 0) {
                $coverage['fixturesWithBlockedEncryptedByteExposures'][] = $fixture;
            }
            if ($fixture !== '' && is_string($summary['coverImagePart'] ?? null) && (string) $summary['coverImagePart'] !== '') {
                $coverage['fixturesWithCoverImagePart'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['imageAssetCount'] ?? 0) > 0) {
                $coverage['fixturesWithImages'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['stylesheetAssetCount'] ?? 0) > 0) {
                $coverage['fixturesWithStylesheets'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['landmarkEntryCount'] ?? 0) > 0) {
                $coverage['fixturesWithLandmarks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['pageListEntryCount'] ?? 0) > 0) {
                $coverage['fixturesWithPageLists'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['pageListCfiTargetCount'] ?? 0) > 0) {
                $coverage['fixturesWithPageListCfiTargets'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['auxiliaryNavigationEntryCount'] ?? 0) > 0) {
                $coverage['fixturesWithAuxiliaryNavigation'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['remoteResourceManifestItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithRemoteManifestResources'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['externalManifestItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithExternalManifestItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['missingLocalManifestItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithMissingLocalManifestItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['manifestFallbackItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithManifestFallbackItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['manifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithManifestFallbacks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['resolvedManifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithResolvedManifestFallbacks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['usableManifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithUsableManifestFallbacks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['missingManifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithMissingManifestFallbacks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['mediaOverlayCount'] ?? 0) > 0) {
                $coverage['fixturesWithMediaOverlays'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['resolvedMediaOverlayCount'] ?? 0) > 0) {
                $coverage['fixturesWithResolvedMediaOverlays'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['mediaOverlayTextLocalTargetCount'] ?? 0) > 0) {
                $coverage['fixturesWithMediaOverlayTextTargets'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['mediaOverlayAudioLocalTargetCount'] ?? 0) > 0) {
                $coverage['fixturesWithMediaOverlayAudioTargets'][] = $fixture;
            }
            $spineLinearCounts = is_array($summary['spineLinearStateCounts'] ?? null) ? $summary['spineLinearStateCounts'] : [];
            if ($fixture !== '' && (int) ($spineLinearCounts['non-linear'] ?? 0) > 0) {
                $coverage['fixturesWithNonLinearSpineItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['spinePageSpreadItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithSpinePageSpreadItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['collectionCount'] ?? 0) > 0) {
                $coverage['fixturesWithCollections'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['bindingCount'] ?? 0) > 0) {
                $coverage['fixturesWithBindings'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['ocfSidecarCount'] ?? 0) > 0) {
                $coverage['fixturesWithOcfSidecars'][] = $fixture;
            }

            $coverage['totals']['metadataCreators'] += (int) ($summary['metadataCreatorCount'] ?? 0);
            $coverage['totals']['manifestItems'] += (int) ($summary['manifestItemCount'] ?? 0);
            $coverage['totals']['readingOrderItems'] += (int) ($summary['readingOrderCount'] ?? 0);
            $coverage['totals']['spinePageSpreadItems'] += (int) ($summary['spinePageSpreadItemCount'] ?? 0);
            $coverage['totals']['xhtmlAssets'] += (int) ($summary['xhtmlAssetCount'] ?? 0);
            $coverage['totals']['imageAssets'] += (int) ($summary['imageAssetCount'] ?? 0);
            $coverage['totals']['stylesheetAssets'] += (int) ($summary['stylesheetAssetCount'] ?? 0);
            $coverage['totals']['navigationEntries'] += (int) ($summary['navigationEntryCount'] ?? 0);
            $coverage['totals']['landmarkEntries'] += (int) ($summary['landmarkEntryCount'] ?? 0);
            $coverage['totals']['pageListEntries'] += (int) ($summary['pageListEntryCount'] ?? 0);
            $coverage['totals']['pageListCfiTargets'] += (int) ($summary['pageListCfiTargetCount'] ?? 0);
            $coverage['totals']['auxiliaryNavigationEntries'] += (int) ($summary['auxiliaryNavigationEntryCount'] ?? 0);
            $coverage['totals']['packageLinks'] += (int) ($summary['packageLinkCount'] ?? 0);
            $coverage['totals']['packageLinkVocabularyRelTokens'] += (int) ($summary['packageLinkVocabularyRelTokenCount'] ?? 0);
            $coverage['totals']['packageLinkVocabularyPropertyTokens'] += (int) ($summary['packageLinkVocabularyPropertyTokenCount'] ?? 0);
            $coverage['totals']['packageLinkVocabularyResolvedTokens'] += (int) ($summary['packageLinkVocabularyResolvedTokenCount'] ?? 0);
            $coverage['totals']['packageLinkVocabularyAbsoluteUrlTokens'] += (int) ($summary['packageLinkVocabularyAbsoluteUrlTokenCount'] ?? 0);
            $coverage['totals']['packageLinkVocabularyDuplicateTokens'] += (int) ($summary['packageLinkVocabularyDuplicateTokenCount'] ?? 0);
            $coverage['totals']['packageLinkVocabularyDiagnostics'] += (int) ($summary['packageLinkVocabularyDiagnosticCount'] ?? 0);
            $coverage['totals']['packageLinkMediaTypeItems'] += (int) ($summary['packageLinkMediaTypeCount'] ?? 0);
            $coverage['totals']['packageLinkMediaTypeParameters'] += (int) ($summary['packageLinkMediaTypeParameterCount'] ?? 0);
            $coverage['totals']['linkHrefSuffixes'] += (int) ($summary['linkHrefSuffixCount'] ?? 0);
            $coverage['totals']['linkHrefSuffixQueries'] += (int) ($summary['linkHrefSuffixQueryCount'] ?? 0);
            $coverage['totals']['linkHrefSuffixFragments'] += (int) ($summary['linkHrefSuffixFragmentCount'] ?? 0);
            $coverage['totals']['guideReferences'] += (int) ($summary['guideReferenceCount'] ?? 0);
            $coverage['totals']['accessibilityEntries'] += (int) ($summary['accessibilityEntryCount'] ?? 0);
            $coverage['totals']['accessibilityLinkedRecords'] += (int) ($summary['accessibilityLinkedRecordCount'] ?? 0);
            $coverage['totals']['accessibilityAccessModes'] += (int) ($summary['accessibilityAccessModeCount'] ?? 0);
            $coverage['totals']['accessibilityFeatures'] += (int) ($summary['accessibilityFeatureCount'] ?? 0);
            $coverage['totals']['accessibilityHazards'] += (int) ($summary['accessibilityHazardCount'] ?? 0);
            $coverage['totals']['accessibilityConformsTo'] += (int) ($summary['accessibilityConformsToCount'] ?? 0);
            $coverage['totals']['remoteResourceManifestItems'] += (int) ($summary['remoteResourceManifestItemCount'] ?? 0);
            $coverage['totals']['externalManifestItems'] += (int) ($summary['externalManifestItemCount'] ?? 0);
            $coverage['totals']['missingLocalManifestItems'] += (int) ($summary['missingLocalManifestItemCount'] ?? 0);
            $coverage['totals']['manifestFallbackItems'] += (int) ($summary['manifestFallbackItemCount'] ?? 0);
            $coverage['totals']['manifestFallbacks'] += (int) ($summary['manifestFallbackCount'] ?? 0);
            $coverage['totals']['resolvedManifestFallbacks'] += (int) ($summary['resolvedManifestFallbackCount'] ?? 0);
            $coverage['totals']['usableManifestFallbacks'] += (int) ($summary['usableManifestFallbackCount'] ?? 0);
            $coverage['totals']['missingManifestFallbacks'] += (int) ($summary['missingManifestFallbackCount'] ?? 0);
            $coverage['totals']['mediaOverlays'] += (int) ($summary['mediaOverlayCount'] ?? 0);
            $coverage['totals']['resolvedMediaOverlays'] += (int) ($summary['resolvedMediaOverlayCount'] ?? 0);
            $coverage['totals']['missingMediaOverlays'] += (int) ($summary['missingMediaOverlayCount'] ?? 0);
            $coverage['totals']['mediaOverlayReferencedContentItems'] += (int) ($summary['mediaOverlayReferencedContentItemCount'] ?? 0);
            $coverage['totals']['mediaOverlayTimelineItems'] += (int) ($summary['mediaOverlayTimelineItemCount'] ?? 0);
            $coverage['totals']['mediaOverlayClipTimings'] += (int) ($summary['mediaOverlayClipTimingCount'] ?? 0);
            $coverage['totals']['mediaOverlayValidClipTimings'] += (int) ($summary['mediaOverlayValidClipTimingCount'] ?? 0);
            $coverage['totals']['mediaOverlayInvalidClipTimings'] += (int) ($summary['mediaOverlayInvalidClipTimingCount'] ?? 0);
            $coverage['totals']['mediaOverlayTextLocalTargets'] += (int) ($summary['mediaOverlayTextLocalTargetCount'] ?? 0);
            $coverage['totals']['mediaOverlayAudioLocalTargets'] += (int) ($summary['mediaOverlayAudioLocalTargetCount'] ?? 0);
            $coverage['totals']['mediaOverlayDurations'] += (int) ($summary['mediaOverlayDurationCount'] ?? 0);
            $coverage['totals']['encryptionItems'] += (int) ($summary['encryptionItemCount'] ?? 0);
            $coverage['totals']['obfuscatedFonts'] += (int) ($summary['obfuscatedFontCount'] ?? 0);
            $coverage['totals']['blockedEncryptedByteExposures'] += (int) ($summary['blockedEncryptedByteExposureCount'] ?? 0);
            $coverage['totals']['encryptionDiagnostics'] += (int) ($summary['encryptionDiagnosticCount'] ?? 0);
            $coverage['totals']['collections'] += (int) ($summary['collectionCount'] ?? 0);
            $coverage['totals']['collectionLinks'] += (int) ($summary['collectionLinkCount'] ?? 0);
            $coverage['totals']['bindingItems'] += (int) ($summary['bindingCount'] ?? 0);
            $coverage['totals']['bindingResolvedHandlers'] += (int) ($summary['bindingResolvedHandlerCount'] ?? 0);
            $coverage['totals']['bindingMediaTypeParameters'] += (int) ($summary['bindingMediaTypeParameterCount'] ?? 0);
            $coverage['totals']['ocfSidecars'] += (int) ($summary['ocfSidecarCount'] ?? 0);
        }

        ksort($metadataLanguageCounts, SORT_STRING);
        ksort($opfPartNameCounts, SORT_STRING);
        ksort($navigationTypeCounts, SORT_STRING);
        ksort($manifestMediaTypeCounts, SORT_STRING);
        ksort($manifestPropertyCounts, SORT_STRING);
        ksort($manifestResourceKindCounts, SORT_STRING);
        $coverage['opfPartNameCounts'] = $opfPartNameCounts;
        $coverage['metadataLanguageCounts'] = $metadataLanguageCounts;
        $coverage['navigationTypeCounts'] = $navigationTypeCounts;
        ksort($spineLinearStateCounts, SORT_STRING);
        $coverage['spineLinearStateCounts'] = $spineLinearStateCounts;
        ksort($spinePageSpreadPlacementCounts, SORT_STRING);
        $coverage['spinePageSpreadPlacementCounts'] = $spinePageSpreadPlacementCounts;
        $coverage['manifestMediaTypeCounts'] = $manifestMediaTypeCounts;
        $coverage['manifestPropertyCounts'] = $manifestPropertyCounts;
        $coverage['manifestResourceKindCounts'] = $manifestResourceKindCounts;
        ksort($guideReferenceTypeCounts, SORT_STRING);
        $coverage['guideReferenceTypeCounts'] = $guideReferenceTypeCounts;
        ksort($packageLinkRelCounts, SORT_STRING);
        $coverage['packageLinkRelCounts'] = $packageLinkRelCounts;
        ksort($packageLinkVocabularyRelCounts, SORT_STRING);
        $coverage['packageLinkVocabularyRelCounts'] = $packageLinkVocabularyRelCounts;
        ksort($packageLinkVocabularyPropertyCounts, SORT_STRING);
        $coverage['packageLinkVocabularyPropertyCounts'] = $packageLinkVocabularyPropertyCounts;
        ksort($packageLinkMediaTypeCounts, SORT_STRING);
        $coverage['packageLinkMediaTypeCounts'] = $packageLinkMediaTypeCounts;
        ksort($packageLinkMediaTypeParameterNameCounts, SORT_STRING);
        $coverage['packageLinkMediaTypeParameterNameCounts'] = $packageLinkMediaTypeParameterNameCounts;
        ksort($linkHrefSuffixSourceCounts, SORT_STRING);
        $coverage['linkHrefSuffixSourceCounts'] = $linkHrefSuffixSourceCounts;
        ksort($accessibilityPropertyCounts, SORT_STRING);
        $coverage['accessibilityPropertyCounts'] = $accessibilityPropertyCounts;
        ksort($encryptionRoleCounts, SORT_STRING);
        $coverage['encryptionRoleCounts'] = $encryptionRoleCounts;
        ksort($collectionRoleCounts, SORT_STRING);
        $coverage['collectionRoleCounts'] = $collectionRoleCounts;
        ksort($collectionLinkRelCounts, SORT_STRING);
        $coverage['collectionLinkRelCounts'] = $collectionLinkRelCounts;
        ksort($bindingMediaTypeCounts, SORT_STRING);
        $coverage['bindingMediaTypeCounts'] = $bindingMediaTypeCounts;
        ksort($ocfSidecarKindCounts, SORT_STRING);
        $coverage['ocfSidecarKindCounts'] = $ocfSidecarKindCounts;
        $coverage['navigationSectionTypes'] = array_keys($navigationSectionTypes);
        sort($coverage['navigationSectionTypes'], SORT_STRING);
        ksort($fixtureFeatureSignatures, SORT_STRING);
        $coverage['fixtureFeatureSignatures'] = $fixtureFeatureSignatures;

        return $coverage;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private static function fixtureFeatureSignature(array $summary): array
    {
        $signature = [
            'navigationType' => is_string($summary['navigationType'] ?? null) ? $summary['navigationType'] : '',
            'navigationSectionTypes' => self::stringList($summary['navigationSectionTypes'] ?? []),
            'manifestResourceKindCounts' => self::intCountMap($summary['manifestResourceKindCounts'] ?? []),
            'guideReferenceTypeCounts' => self::intCountMap($summary['guideReferenceTypeCounts'] ?? []),
            'packageLinkRelCounts' => self::intCountMap($summary['packageLinkRelCounts'] ?? []),
        ];
        $packageLinkVocabularyRelCounts = self::intCountMap($summary['packageLinkVocabularyRelCounts'] ?? []);
        if ($packageLinkVocabularyRelCounts !== []) {
            $signature['packageLinkVocabularyRelCounts'] = $packageLinkVocabularyRelCounts;
        }
        $packageLinkVocabularyPropertyCounts = self::intCountMap($summary['packageLinkVocabularyPropertyCounts'] ?? []);
        if ($packageLinkVocabularyPropertyCounts !== []) {
            $signature['packageLinkVocabularyPropertyCounts'] = $packageLinkVocabularyPropertyCounts;
        }
        $packageLinkVocabularyDiagnosticCount = (int) ($summary['packageLinkVocabularyDiagnosticCount'] ?? 0);
        if ($packageLinkVocabularyDiagnosticCount > 0) {
            $signature['packageLinkVocabularyDiagnosticCount'] = $packageLinkVocabularyDiagnosticCount;
        }
        $accessibilityPropertyCounts = self::intCountMap($summary['accessibilityPropertyCounts'] ?? []);
        if ($accessibilityPropertyCounts !== []) {
            $signature['accessibilityPropertyCounts'] = $accessibilityPropertyCounts;
        }
        $accessibilityLinkedRecordCount = (int) ($summary['accessibilityLinkedRecordCount'] ?? 0);
        if ($accessibilityLinkedRecordCount > 0) {
            $signature['accessibilityLinkedRecordCount'] = $accessibilityLinkedRecordCount;
        }
        $pageListCfiTargetCount = (int) ($summary['pageListCfiTargetCount'] ?? 0);
        if ($pageListCfiTargetCount > 0) {
            $signature['pageListCfiTargetCount'] = $pageListCfiTargetCount;
        }
        $signature['coverImagePartPresent'] = is_string($summary['coverImagePart'] ?? null) && $summary['coverImagePart'] !== '';

        return $signature;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $items[] = $item;
            }
        }
        sort($items, SORT_STRING);

        return array_values(array_unique($items));
    }

    /**
     * @return list<string>
     */
    private static function orderedStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array<string, int>
     */
    private static function intCountMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $counts = [];
        foreach ($value as $key => $count) {
            if (is_string($key) && $key !== '') {
                $counts[$key] = (int) $count;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return array<string, int>
     */
    private static function packageLinkRelCounts(array $links): array
    {
        $counts = [];
        foreach ($links as $link) {
            foreach (is_array($link['rel'] ?? null) ? $link['rel'] : [] as $rel) {
                if (!is_string($rel) || $rel === '') {
                    continue;
                }
                $counts[$rel] = (int) ($counts[$rel] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $references
     * @return array<string, int>
     */
    private static function guideReferenceTypeCounts(array $references): array
    {
        $counts = [];
        foreach ($references as $reference) {
            $type = is_string($reference['type'] ?? null) ? strtolower(trim($reference['type'])) : '';
            if ($type === '') {
                continue;
            }
            $counts[$type] = (int) ($counts[$type] ?? 0) + 1;
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<string, mixed> $counts
     */
    private static function formatCounts(array $counts): string
    {
        if ($counts === []) {
            return 'none';
        }

        ksort($counts, SORT_STRING);
        $parts = [];
        foreach ($counts as $key => $count) {
            if (is_string($key) && $key !== '') {
                $parts[] = $key . ':' . (int) $count;
            }
        }

        return $parts === [] ? 'none' : implode(',', $parts);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{mediaTypeCounts: array<string, int>, propertyCounts: array<string, int>, remoteResourceItemCount: int, externalItemCount: int, missingLocalItemCount: int}
     */
    private static function manifestItemCoverageSummary(array $items): array
    {
        $mediaTypeCounts = [];
        $propertyCounts = [];
        $remoteResourceItemCount = 0;
        $externalItemCount = 0;
        $missingLocalItemCount = 0;

        foreach ($items as $item) {
            $mediaType = self::manifestItemMediaType($item);
            if ($mediaType !== '') {
                $mediaTypeCounts[$mediaType] = (int) ($mediaTypeCounts[$mediaType] ?? 0) + 1;
            }

            foreach (is_array($item['properties'] ?? null) ? $item['properties'] : [] as $property) {
                if (!is_string($property) || $property === '') {
                    continue;
                }
                $property = strtolower($property);
                $propertyCounts[$property] = (int) ($propertyCounts[$property] ?? 0) + 1;
                if ($property === 'remote-resources') {
                    ++$remoteResourceItemCount;
                }
            }

            $external = ($item['external'] ?? false) === true;
            if ($external) {
                ++$externalItemCount;
            }
            if (!$external && ($item['exists'] ?? true) !== true) {
                ++$missingLocalItemCount;
            }
        }

        ksort($mediaTypeCounts, SORT_STRING);
        ksort($propertyCounts, SORT_STRING);

        return [
            'mediaTypeCounts' => $mediaTypeCounts,
            'propertyCounts' => $propertyCounts,
            'remoteResourceItemCount' => $remoteResourceItemCount,
            'externalItemCount' => $externalItemCount,
            'missingLocalItemCount' => $missingLocalItemCount,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function manifestItemMediaType(array $item): string
    {
        $mediaType = is_string($item['mediaTypeBase'] ?? null)
            ? $item['mediaTypeBase']
            : (is_string($item['mediaType'] ?? null) ? $item['mediaType'] : '');

        return strtolower(trim(explode(';', $mediaType, 2)[0]));
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readEpub(string $path): array
    {
        try {
            return ['ok' => true, 'document' => (new EpubReader())->readEpubFile($path), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readNative(string $path): array
    {
        try {
            $native = file_get_contents($path);
            if (!is_string($native)) {
                throw new \RuntimeException("Unable to read native fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new NativeReader())->read($native), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    public function normalizedDocument(AstNode $document): array
    {
        return $this->normalizedNode($document);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedNode(AstNode $node): array
    {
        if ($node->type === 'space') {
            // Pandoc JSON exposes Space as its own inline constructor while
            // textual Native parsing folds it into adjacent text nodes.
            return [
                'type' => 'text',
                'attrs' => ['text' => ' '],
                'children' => [],
            ];
        }

        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if ($node->type === 'document' && $key === 'meta') {
                continue;
            }
            if (self::isIgnoredAttrKey($key)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['plain', 'paragraph', 'heading', 'table_cell', 'term'], true)) {
                continue;
            }
            if ($key === 'attributes' && $value === []) {
                continue;
            }

            $normalizedValue = $this->normalizedValue($value);
            if (in_array($key, ['attributes', 'htmlAttributes'], true) && $normalizedValue === []) {
                continue;
            }
            if ($normalizedValue === [] || $normalizedValue === null || $normalizedValue === '') {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $node->type === 'table_cell'
                ? $this->normalizedTableCellChildren($node->children)
                : $this->normalizedChildren($node->children),
        ];
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedTableCellChildren(array $children): array
    {
        $normalized = $this->normalizedChildren($children);
        if (
            count($normalized) === 1
            && in_array($normalized[0]['type'] ?? null, ['plain', 'paragraph'], true)
            && ($normalized[0]['attrs'] ?? null) === []
            && is_array($normalized[0]['children'] ?? null)
        ) {
            return $normalized[0]['children'];
        }

        return $normalized;
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
            if ($this->isEmptyTableFootNode($node)) {
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
            $normalized[$lastIndex]['attrs']['text'] .= $node['attrs']['text'];
            return;
        }

        $normalized[] = $node;
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
    private function isPlainTextNode(array $node): bool
    {
        $attrs = $node['attrs'] ?? null;

        return ($node['type'] ?? null) === 'text'
            && is_array($attrs)
            && array_keys($attrs) === ['text']
            && is_string($attrs['text'])
            && ($node['children'] ?? null) === [];
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
    private function isEmptyTableFootNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'table_foot'
            && ($node['attrs'] ?? null) === []
            && ($node['children'] ?? null) === [];
    }

    private function normalizedValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace("\t", ' ', $value);
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
            if (self::isIgnoredAttrKey((string) $key)) {
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

    private static function isIgnoredAttrKey(string $key): bool
    {
        return isset(self::IGNORED_ATTRS[$key])
            || str_starts_with($key, 'epub')
            || self::isNativeProvenanceAttrKey($key);
    }

    private static function isNativeProvenanceAttrKey(string $key): bool
    {
        return str_starts_with($key, 'native')
            || str_ends_with($key, 'Native')
            || str_ends_with($key, 'Natives')
            || str_ends_with($key, 'Constructor')
            || str_ends_with($key, 'Constructors')
            || in_array($key, ['constructor', 'pandocApiVersion'], true);
    }

    private function firstDifference(mixed $epub, mixed $native, string $path = 'root'): ?string
    {
        if (gettype($epub) !== gettype($native)) {
            return "{$path} type " . gettype($epub) . ' vs ' . gettype($native);
        }
        if (!is_array($epub)) {
            return $epub === $native ? null : "{$path} value " . self::shortJson($epub) . ' vs ' . self::shortJson($native);
        }

        $epubKeys = array_keys($epub);
        $nativeKeys = array_keys($native);
        if ($epubKeys !== $nativeKeys) {
            return "{$path} keys " . self::shortJson($epubKeys) . ' vs ' . self::shortJson($nativeKeys);
        }

        foreach ($epubKeys as $key) {
            $difference = $this->firstDifference($epub[$key], $native[$key], $path . '.' . $key);
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
        if (str_contains($lower, 'raw_html') || str_contains($lower, 'div') || str_contains($lower, 'span')) {
            $categories[] = 'xhtml-structure-shape';
        }
        if (str_contains($lower, 'image') || str_contains($lower, 'url') || str_contains($lower, 'alt')) {
            $categories[] = 'image-shape';
        }
        if (str_contains($lower, 'table') || str_contains($lower, 'row') || str_contains($lower, 'cell')) {
            $categories[] = 'table-shape';
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
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
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

    private static function packageAcceptanceStatus(int $comparedEpubCount, int $packageParseFailureCount, int $readerParseFailureCount): string
    {
        if ($comparedEpubCount === 0) {
            return 'not-evaluated-no-epub-files';
        }
        if ($packageParseFailureCount > 0 || $readerParseFailureCount > 0) {
            return 'blocked-by-package-or-reader-parse-failures';
        }

        return 'package-and-reader-implementation-equivalence-observed';
    }

    private static function astParityStatus(int $comparedPairCount, int $parseFailureCount, int $mismatchCount): string
    {
        if ($comparedPairCount === 0) {
            return 'not-evaluated-no-native-pairs';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'normalized-ast-mismatches-observed';
        }

        return 'normalized-ast-implementation-equivalence-observed';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $directoryPresent,
        int $comparedEpubCount,
        int $packageParseFailureCount,
        int $readerParseFailureCount,
        int $comparedPairCount,
        int $astParseFailureCount,
        int $astMatchCount,
        int $astMismatchCount,
        bool $runnerResultCovered = false
    ): array {
        if (!$directoryPresent) {
            $packageEvidence = 'EPUB directory absent; package comparison did not run';
            $astEvidence = 'EPUB directory absent; native AST comparison did not run';
        } else {
            $packageEvidence = "compared epubs={$comparedEpubCount}; package failures={$packageParseFailureCount}; reader failures={$readerParseFailureCount}";
            $astEvidence = "native pairs={$comparedPairCount}; parse failures={$astParseFailureCount}; normalized matches={$astMatchCount}; normalized mismatches={$astMismatchCount}";
        }

        $packageCovered = $directoryPresent
            && $comparedEpubCount > 0
            && $packageParseFailureCount === 0
            && $readerParseFailureCount === 0;
        $astCovered = $directoryPresent
            && $comparedPairCount > 0
            && $astParseFailureCount === 0
            && $astMismatchCount === 0
            && $astMatchCount === $comparedPairCount;

        return [
            [
                'rank' => 1,
                'id' => 'upstream-epub-package-and-reader-acceptance',
                'status' => !$directoryPresent ? 'not-evaluated' : ($packageCovered ? 'covered-by-current-package-evidence' : 'open'),
                'currentEvidence' => $packageEvidence,
                'evidenceRequired' => 'Parse every upstream EPUB package with both the package preflight reader and local EPUB reader, keeping package and reader failures at zero.',
            ],
            [
                'rank' => 2,
                'id' => 'upstream-epub-native-ast-equality',
                'status' => !$directoryPresent ? 'not-evaluated' : ($astCovered ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $astEvidence,
                'evidenceRequired' => 'Compare local PHP EPUB reader output against same-basename upstream .native goldens, keeping parse failures and normalized AST mismatches at zero.',
            ],
            [
                'rank' => 3,
                'id' => 'upstream-haskell-epub-reader-runner-results',
                'status' => $runnerResultCovered ? 'covered-by-supplied-runner-result-artifact' : 'open',
                'currentEvidence' => $runnerResultCovered
                    ? 'Supplied Cabal exe:pandoc EPUB-to-native runner result artifact validated against the pinned fixture and transcript contract.'
                    : 'Structured planned-not-run Cabal exe:pandoc command evidence is present; this harness does not run the upstream Haskell process itself.',
                'evidenceRequired' => 'Record reproducible upstream Tests.Readers.EPUB runner results when a Haskell runner is available.',
            ],
        ];
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
                '%d. %s [%s]: %s',
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

    private static function formatBooleanFlag(mixed $value): string
    {
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }

        return 'unknown';
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
