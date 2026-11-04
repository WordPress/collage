<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class LegacyDocReader
{
    private const SUMMARY_INFORMATION = "\x05SummaryInformation";
    private const DOCUMENT_SUMMARY_INFORMATION = "\x05DocumentSummaryInformation";
    private const FMTID_USER_DEFINED_PROPERTIES = '05d5cdd59c2e1b10939708002b2cf9ae';
    private const FIB_FC_STSHF = 0x00a2;
    private const FIB_LCB_STSHF = 0x00a6;
    private const FIB_FC_PLCFFND_REF = 0x00aa;
    private const FIB_LCB_PLCFFND_REF = 0x00ae;
    private const FIB_FC_PLCFFND_TXT = 0x00b2;
    private const FIB_LCB_PLCFFND_TXT = 0x00b6;
    private const FIB_FC_PLCFAND_REF = 0x00ba;
    private const FIB_LCB_PLCFAND_REF = 0x00be;
    private const FIB_FC_PLCFAND_TXT = 0x00c2;
    private const FIB_LCB_PLCFAND_TXT = 0x00c6;
    private const FIB_FC_PLCF_SED = 0x00ca;
    private const FIB_LCB_PLCF_SED = 0x00ce;
    private const FIB_FC_PLCF_HDD = 0x00f2;
    private const FIB_LCB_PLCF_HDD = 0x00f6;
    private const FIB_FC_PLCF_BTE_CHPX = 0x00fa;
    private const FIB_LCB_PLCF_BTE_CHPX = 0x00fe;
    private const FIB_FC_PLCF_BTE_PAPX = 0x0102;
    private const FIB_LCB_PLCF_BTE_PAPX = 0x0106;
    private const FIB_FC_PLCF_FLD_MOM = 0x011a;
    private const FIB_LCB_PLCF_FLD_MOM = 0x011e;
    private const FIB_FC_PLCF_FLD_HDR = 0x0122;
    private const FIB_LCB_PLCF_FLD_HDR = 0x0126;
    private const FIB_FC_PLCF_FLD_FTN = 0x012a;
    private const FIB_LCB_PLCF_FLD_FTN = 0x012e;
    private const FIB_FC_PLCF_FLD_ATN = 0x0132;
    private const FIB_LCB_PLCF_FLD_ATN = 0x0136;
    private const FIB_FC_STTBF_BKMK = 0x0142;
    private const FIB_LCB_STTBF_BKMK = 0x0146;
    private const FIB_FC_PLCF_BKF = 0x014a;
    private const FIB_LCB_PLCF_BKF = 0x014e;
    private const FIB_FC_PLCF_BKL = 0x0152;
    private const FIB_LCB_PLCF_BKL = 0x0156;
    private const FIB_FC_DOP = 0x0192;
    private const FIB_LCB_DOP = 0x0196;
    private const FIB_FC_STTBF_ASSOC = 0x019a;
    private const FIB_LCB_STTBF_ASSOC = 0x019e;
    private const FIB_FC_PMS = 0x01fa;
    private const FIB_LCB_PMS = 0x01fe;
    private const FIB_FC_STW_USER = 0x027a;
    private const FIB_LCB_STW_USER = 0x027e;
    private const FIB_FC_ROUTE_SLIP = 0x02ca;
    private const FIB_LCB_ROUTE_SLIP = 0x02ce;
    private const FIB_FC_STTB_SAVED_BY = 0x02d2;
    private const FIB_LCB_STTB_SAVED_BY = 0x02d6;
    private const FIB_FC_STTB_FNM = 0x02da;
    private const FIB_LCB_STTB_FNM = 0x02de;
    private const FIB_FC_GRPXST_ATN_OWNERS = 0x01ba;
    private const FIB_LCB_GRPXST_ATN_OWNERS = 0x01be;
    private const FIB_FC_PLCFEND_REF = 0x020a;
    private const FIB_LCB_PLCFEND_REF = 0x020e;
    private const FIB_FC_PLCFEND_TXT = 0x0212;
    private const FIB_LCB_PLCFEND_TXT = 0x0216;
    private const FIB_FC_PLCF_FLD_EDN = 0x021a;
    private const FIB_LCB_PLCF_FLD_EDN = 0x021e;
    private const FIB_FC_STTBF_RMARK = 0x0232;
    private const FIB_LCB_STTBF_RMARK = 0x0236;
    private const FIB_FC_STTBF_CAPTION = 0x023a;
    private const FIB_LCB_STTBF_CAPTION = 0x023e;
    private const FIB_FC_STTBF_AUTO_CAPTION = 0x0242;
    private const FIB_LCB_STTBF_AUTO_CAPTION = 0x0246;
    private const FIB_FC_PLCF_FLD_TXBX = 0x0262;
    private const FIB_LCB_PLCF_FLD_TXBX = 0x0266;
    private const FIB_FC_PLCF_FLD_HDR_TXBX = 0x0272;
    private const FIB_LCB_PLCF_FLD_HDR_TXBX = 0x0276;
    private const FIB_FC_PLF_LST = 0x02e2;
    private const FIB_LCB_PLF_LST = 0x02e6;
    private const FIB_FC_PLF_LFO = 0x02ea;
    private const FIB_LCB_PLF_LFO = 0x02ee;
    private const SPRM_PF_KEEP = 0x2405;
    private const SPRM_PF_KEEP_FOLLOW = 0x2406;
    private const SPRM_PF_PAGE_BREAK_BEFORE = 0x2407;
    private const SPRM_PDYA_LINE = 0x6412;
    private const SPRM_PDYA_BEFORE = 0xa413;
    private const SPRM_PDYA_AFTER = 0xa414;
    private const SPRM_PDXA_RIGHT = 0x845d;
    private const SPRM_PDXA_LEFT = 0x845e;
    private const SPRM_PDXA_LEFT1 = 0x8460;
    private const SPRM_PJC = 0x2461;
    private const SPRM_CFR_MARK_DEL = 0x0800;
    private const SPRM_CFR_MARK_INS = 0x0801;
    private const SPRM_CF_BOLD = 0x0835;
    private const SPRM_CF_ITALIC = 0x0836;
    private const SPRM_CF_STRIKE = 0x0837;
    private const SPRM_CF_SMALL_CAPS = 0x083a;
    private const SPRM_CF_CAPS = 0x083b;
    private const SPRM_CF_VANISH = 0x083c;
    private const SPRM_C_PIC_LOCATION = 0x6a03;
    private const SPRM_CF_DATA = 0x0806;
    private const SPRM_CF_SPEC = 0x0855;
    private const SPRM_C_KUL = 0x2a3e;
    private const SPRM_CIBST_RMARK = 0x4804;
    private const SPRM_CDTTM_RMARK = 0x6805;
    private const SPRM_CIBST_RMARK_DEL = 0x4863;
    private const SPRM_CDTTM_RMARK_DEL = 0x6864;
    private const SPRM_P_PROP_RMARK = 0xc66f;
    private const FIB_RGLW97_CB_MAC = 0x0040;
    private const FIB_RGLW97_RESERVED3 = 0x0058;
    private const FIB_RGLW97_CCP_FIELDS = [
        'ccpText' => 0x004c,
        'ccpFtn' => 0x0050,
        'ccpHdd' => 0x0054,
        'ccpAtn' => 0x005c,
        'ccpEdn' => 0x0060,
        'ccpTxbx' => 0x0064,
        'ccpHdrTxbx' => 0x0068,
    ];
    private const FIB_RGLW97_SUBDOCUMENT_TYPES = [
        'ccpFtn' => 'footnote',
        'ccpHdd' => 'header',
        'ccpAtn' => 'comment',
        'ccpEdn' => 'endnote',
        'ccpTxbx' => 'textbox',
        'ccpHdrTxbx' => 'header-textbox',
    ];
    private const HEADER_FOOTER_SEPARATOR_ROLES = [
        'footnote-separator',
        'footnote-continuation-separator',
        'footnote-continuation-notice',
        'endnote-separator',
        'endnote-continuation-separator',
        'endnote-continuation-notice',
    ];
    private const HEADER_FOOTER_SECTION_ROLES = [
        'even-page-header',
        'odd-page-header',
        'even-page-footer',
        'odd-page-footer',
        'first-page-header',
        'first-page-footer',
    ];
    private const FIELD_CHARACTER_BEGIN = 0x13;
    private const FIELD_CHARACTER_SEPARATOR = 0x14;
    private const FIELD_CHARACTER_END = 0x15;
    private const MAX_CLIPBOARD_DATA_BYTES = 8388608;
    private const MAX_PROPERTY_BLOB_BYTES = 8388608;
    private const MAX_RESERVED_HYPERLINK_BLOB_BYTES = 1048576;
    private const MAX_RESERVED_HYPERLINK_COUNT = 4096;
    private const FIELD_TYPE_NAMES = [
        0x03 => 'ref',
        0x05 => 'noteref',
        0x06 => 'set',
        0x0c => 'seq',
        0x0d => 'toc',
        0x15 => 'createdate',
        0x16 => 'savedate',
        0x17 => 'printdate',
        0x1a => 'numpages',
        0x1d => 'filename',
        0x1e => 'template',
        0x1f => 'date',
        0x20 => 'time',
        0x21 => 'page',
        0x23 => 'quote',
        0x24 => 'include',
        0x25 => 'pageref',
        0x26 => 'ask',
        0x27 => 'fillin',
        0x28 => 'data',
        0x31 => 'eq',
        0x32 => 'gotobutton',
        0x33 => 'macrobutton',
        0x34 => 'autonumout',
        0x35 => 'autonumlgl',
        0x36 => 'autonum',
        0x37 => 'import',
        0x39 => 'symbol',
        0x3b => 'mergefield',
        0x40 => 'docvariable',
        0x41 => 'section',
        0x42 => 'sectionpages',
        0x43 => 'includepicture',
        0x44 => 'includetext',
        0x45 => 'filesize',
        0x46 => 'formtext',
        0x47 => 'formcheckbox',
        0x48 => 'noteref',
        0x53 => 'formdropdown',
        0x58 => 'hyperlink',
        0x5a => 'listnum',
        0x5f => 'shape',
    ];

    /** @var list<array<string,mixed>> */
    private array $activeExternalFileReferences = [];

    /** @var list<array<string,mixed>> */
    private array $activeAssociatedStrings = [];

    /** @var list<array<string,mixed>> */
    private array $activeListFormats = [];

    /** @var list<array<string,mixed>> */
    private array $activeListOverrides = [];

    /** @var array<int,array<string,mixed>> */
    private array $activeFormFieldDataReferences = [];

    /** @var list<array<string,mixed>> */
    private array $activeInlineTextFormattingApplications = [];

    /** @var list<array<string,mixed>> */
    private array $activeHiddenTextSuppressions = [];

    /**
     * Read legacy binary Word document bytes into the shared AST.
     */
    public function read(string $bytes): AstNode
    {
        return $this->readBytes($bytes)['document'];
    }

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, streamDirectory:list<array<string,mixed>>, directoryEntries:list<array<string,mixed>>, fib:array<string,mixed>, subdocuments:list<array<string,mixed>>, headerFooterStories:list<array<string,mixed>>, styles:list<array<string,mixed>>, formattingRuns:list<array<string,mixed>>, listFormats:list<array<string,mixed>>, listOverrides:list<array<string,mixed>>, sections:list<array<string,mixed>>, bookmarks:list<array<string,mixed>>, footnotes:list<array<string,mixed>>, endnotes:list<array<string,mixed>>, comments:list<array<string,mixed>>, commentAuthors:list<array<string,mixed>>, revisionAuthors:list<array<string,mixed>>, captionDefinitions:list<array<string,mixed>>, autoCaptionRules:list<array<string,mixed>>, fieldCharacters:list<array<string,mixed>>, fields:list<array<string,mixed>>, fieldStories:list<array<string,mixed>>, formFieldDataReferences:list<array<string,mixed>>, embeddedObjects:list<array<string,mixed>>, embeddedObjectReferences:list<array<string,mixed>>, pictureReferences:list<array<string,mixed>>, macroProjects:list<array<string,mixed>>, associatedStrings:list<array<string,mixed>>, documentProperties:array<string,mixed>, documentVariables:list<array<string,mixed>>, saveHistory:list<array<string,mixed>>, externalFileReferences:list<array<string,mixed>>, subdocumentReferences:list<array<string,mixed>>, mailMergeSettings:array<string,mixed>, routeSlip:array<string,mixed>}
     */
    public function readBytes(string $bytes): array
    {
        return $this->readCompoundFile(CompoundFileBinary::fromBytes($bytes));
    }

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, streamDirectory:list<array<string,mixed>>, directoryEntries:list<array<string,mixed>>, fib:array<string,mixed>, subdocuments:list<array<string,mixed>>, headerFooterStories:list<array<string,mixed>>, styles:list<array<string,mixed>>, formattingRuns:list<array<string,mixed>>, listFormats:list<array<string,mixed>>, listOverrides:list<array<string,mixed>>, sections:list<array<string,mixed>>, bookmarks:list<array<string,mixed>>, footnotes:list<array<string,mixed>>, endnotes:list<array<string,mixed>>, comments:list<array<string,mixed>>, commentAuthors:list<array<string,mixed>>, revisionAuthors:list<array<string,mixed>>, captionDefinitions:list<array<string,mixed>>, autoCaptionRules:list<array<string,mixed>>, fieldCharacters:list<array<string,mixed>>, fields:list<array<string,mixed>>, fieldStories:list<array<string,mixed>>, formFieldDataReferences:list<array<string,mixed>>, embeddedObjects:list<array<string,mixed>>, embeddedObjectReferences:list<array<string,mixed>>, pictureReferences:list<array<string,mixed>>, macroProjects:list<array<string,mixed>>, associatedStrings:list<array<string,mixed>>, documentProperties:array<string,mixed>, documentVariables:list<array<string,mixed>>, saveHistory:list<array<string,mixed>>, externalFileReferences:list<array<string,mixed>>, subdocumentReferences:list<array<string,mixed>>, mailMergeSettings:array<string,mixed>, routeSlip:array<string,mixed>}
     */
    public function readCompoundFile(CompoundFileBinary $compoundFile): array
    {
        if (!$compoundFile->hasStream('WordDocument')) {
            throw new \RuntimeException('Legacy DOC CFB package is missing the WordDocument stream');
        }

        $wordDocument = $compoundFile->readStream('WordDocument');
        $fib = $this->readFib($wordDocument);
        if (($fib['oldBinaryFormat'] ?? false) === true) {
            return $this->readWord6To95CompoundFile($compoundFile, $wordDocument, $fib);
        }
        if ($fib['encrypted'] === true) {
            throw new \RuntimeException('Legacy DOC encrypted streams are not supported by the native reader');
        }
        if ((int) $fib['lKey'] !== 0) {
            throw new \RuntimeException('Legacy DOC unencrypted FIB contains a nonzero lKey encryption verifier');
        }

        $tableStreamName = (string) $fib['tableStream'];
        $tableStream = null;
        if ($compoundFile->hasStream($tableStreamName)) {
            $tableStream = $compoundFile->readStream($tableStreamName);
        } elseif ($compoundFile->hasStream('0Table')) {
            $tableStreamName = '0Table';
            $tableStream = $compoundFile->readStream('0Table');
        } elseif ($compoundFile->hasStream('1Table')) {
            $tableStreamName = '1Table';
            $tableStream = $compoundFile->readStream('1Table');
        }
        $dataStream = $compoundFile->hasStream('Data')
            ? $compoundFile->readStream('Data')
            : null;

        $textResult = $this->extractText($wordDocument, $tableStream, $fib);
        $subdocuments = is_array($textResult['subdocuments'] ?? null) ? $textResult['subdocuments'] : [];
        $subdocumentTexts = $this->subdocumentTextsByType($subdocuments);
        $streamDirectory = $this->streamDirectoryReport($compoundFile);
        $directoryEntries = $this->directoryEntryReport($compoundFile);
        $metadata = $this->readMetadata($compoundFile);
        $associatedStrings = $this->associatedStringReport($wordDocument, $tableStream);
        if ($associatedStrings !== []) {
            $metadata = $this->applyAssociatedStringMetadata($metadata, $associatedStrings);
            $metadata['associatedStringCount'] = count($associatedStrings);
            $metadata['associatedStrings'] = $associatedStrings;
        }
        $documentProperties = $this->documentPropertyReport($wordDocument, $tableStream);
        if ($documentProperties !== []) {
            $metadata['documentPropertyByteCount'] = $documentProperties['byteCount'];
            $metadata['documentPolicyFlags'] = $documentProperties['policyFlags'];
            if (($documentProperties['compatibilityOptionFlags'] ?? []) !== []) {
                $metadata['documentCompatibilityOptionFlags'] = $documentProperties['compatibilityOptionFlags'];
            }
            $metadata['documentProperties'] = $documentProperties;
        }
        $documentVariables = $this->documentVariableReport($wordDocument, $tableStream);
        if ($documentVariables !== []) {
            $metadata['documentVariableCount'] = count($documentVariables);
            $metadata['documentVariables'] = $documentVariables;
            $documentVariableValues = [];
            $signatureVariableCount = 0;
            foreach ($documentVariables as $documentVariable) {
                if (($documentVariable['signatureVariable'] ?? false) === true) {
                    $signatureVariableCount++;
                    continue;
                }
                if (isset($documentVariable['name'], $documentVariable['value'])) {
                    $documentVariableValues[(string) $documentVariable['name']] = (string) $documentVariable['value'];
                }
            }
            if ($documentVariableValues !== []) {
                $metadata['documentVariableValues'] = $documentVariableValues;
            }
            if ($signatureVariableCount > 0) {
                $metadata['documentSignatureVariableCount'] = $signatureVariableCount;
                $metadata['documentSignaturePolicy'] = 'signature-blob-metadata-only';
            }
        }
        $saveHistory = $this->saveHistoryReport($wordDocument, $tableStream);
        if ($saveHistory !== []) {
            $metadata['saveHistoryCount'] = count($saveHistory);
            $metadata['saveHistory'] = $saveHistory;
            $latestSaveHistory = $saveHistory[count($saveHistory) - 1];
            $metadata['latestSavedBy'] = $latestSaveHistory['author'];
            $metadata['latestSavedPath'] = $latestSaveHistory['path'];
            $metadata['latestSavedName'] = $latestSaveHistory['basename'];
        }
        $externalFileReferences = $this->externalFileReferenceReport($wordDocument, $tableStream);
        if ($externalFileReferences !== []) {
            $metadata['externalFileReferenceCount'] = count($externalFileReferences);
            $metadata['externalFileReferencePolicy'] = 'metadata-only-native-review';
            $metadata['externalFileReferences'] = $externalFileReferences;
        }
        $subdocumentReferences = $this->subdocumentReferenceReport($externalFileReferences);
        if ($subdocumentReferences !== []) {
            $metadata['subdocumentReferenceCount'] = count($subdocumentReferences);
            $metadata['subdocumentReferencePolicy'] = 'metadata-only-native-review';
            $metadata['subdocumentReferences'] = $subdocumentReferences;
        }
        $mailMergeSettings = $this->mailMergeSettingsReport($wordDocument, $tableStream, $externalFileReferences);
        if ($mailMergeSettings !== []) {
            $metadata['mailMergeSettingsPolicy'] = $mailMergeSettings['extractionPolicy'];
            $metadata['mailMergeSourceRecordCount'] = (int) $mailMergeSettings['sourceRecordCount'];
            $metadata['mailMergeSettings'] = $mailMergeSettings;
            if (isset($mailMergeSettings['sqlQuery'])) {
                $metadata['mailMergeSqlQuery'] = $mailMergeSettings['sqlQuery'];
                $metadata['mailMergeSqlQueryPolicy'] = $mailMergeSettings['sqlQueryPolicy'];
            }
            if (isset($mailMergeSettings['recordFilterStrings']) && is_array($mailMergeSettings['recordFilterStrings'])) {
                $metadata['mailMergeRecordFilterStringCount'] = count($mailMergeSettings['recordFilterStrings']);
            }
        }
        $routeSlip = $this->routeSlipReport($wordDocument, $tableStream);
        if ($routeSlip !== []) {
            $metadata['routeSlipRecipientCount'] = (int) $routeSlip['recipientCount'];
            $metadata['routeSlip'] = $routeSlip;
            $metadata['routeSlipPolicy'] = $routeSlip['extractionPolicy'];
        }
        $metadata['fibBase'] = $this->fibBaseReviewMetadata($fib);
        if (isset($fib['fibRgLw97']) && is_array($fib['fibRgLw97'])) {
            $metadata['fibRgLw97'] = $fib['fibRgLw97'];
        }
        if ($subdocuments !== []) {
            $metadata['subdocumentCount'] = count($subdocuments);
            $metadata['subdocuments'] = $subdocuments;
        }
        $headerFooterStoryReport = $this->headerFooterStoryReport($wordDocument, $tableStream, $subdocumentTexts);
        $headerFooterStories = $headerFooterStoryReport['stories'];
        if ($headerFooterStories !== []) {
            $metadata['headerFooterStoryCount'] = count($headerFooterStories);
            $metadata['headerFooterDeclaredStoryCount'] = $headerFooterStoryReport['declaredStoryCount'];
            $metadata['headerFooterIgnoredFinalCp'] = $headerFooterStoryReport['ignoredFinalCp'];
            $metadata['headerFooterStories'] = $headerFooterStories;
        }
        if ($streamDirectory !== []) {
            $metadata['cfbStreamCount'] = count($streamDirectory);
        }
        $timestampedDirectoryEntryCount = 0;
        $classIdDirectoryEntryCount = 0;
        $stateBitsDirectoryEntryCount = 0;
        $ignoredStreamSizeHighDwordEntryCount = 0;
        foreach ($directoryEntries as $directoryEntry) {
            if (isset($directoryEntry['createdAt']) || isset($directoryEntry['modifiedAt'])) {
                $timestampedDirectoryEntryCount++;
            }
            if (isset($directoryEntry['clsid'])) {
                $classIdDirectoryEntryCount++;
            }
            if (isset($directoryEntry['stateBits'])) {
                $stateBitsDirectoryEntryCount++;
            }
            if (isset($directoryEntry['ignoredStreamSizeHighDword'])) {
                $ignoredStreamSizeHighDwordEntryCount++;
            }
        }
        if ($timestampedDirectoryEntryCount > 0) {
            $metadata['cfbTimestampedDirectoryEntryCount'] = $timestampedDirectoryEntryCount;
        }
        if ($classIdDirectoryEntryCount > 0) {
            $metadata['cfbClassIdDirectoryEntryCount'] = $classIdDirectoryEntryCount;
        }
        if ($stateBitsDirectoryEntryCount > 0) {
            $metadata['cfbStateBitsDirectoryEntryCount'] = $stateBitsDirectoryEntryCount;
        }
        if ($ignoredStreamSizeHighDwordEntryCount > 0) {
            $metadata['cfbIgnoredStreamSizeHighDwordEntryCount'] = $ignoredStreamSizeHighDwordEntryCount;
        }
        $styles = $this->styleSheetReport($wordDocument, $tableStream);
        if ($styles !== []) {
            $metadata['styleCount'] = count($styles);
            $metadata['styles'] = $styles;
            $styleFormattingCounts = $this->styleFormattingCounts($styles);
            if ($styleFormattingCounts['paragraphProperties'] > 0 || $styleFormattingCounts['textProperties'] > 0) {
                $metadata['styleFormattingPolicy'] = 'metadata-only-native-review';
                if ($styleFormattingCounts['paragraphProperties'] > 0) {
                    $metadata['styleParagraphPropertyCount'] = $styleFormattingCounts['paragraphProperties'];
                }
                if ($styleFormattingCounts['textProperties'] > 0) {
                    $metadata['styleTextPropertyCount'] = $styleFormattingCounts['textProperties'];
                }
            }
        }
        $revisionAuthors = $this->revisionAuthorReport($wordDocument, $tableStream);
        if ($revisionAuthors !== []) {
            $metadata['revisionAuthorCount'] = count($revisionAuthors);
            $metadata['revisionAuthors'] = $revisionAuthors;
            $metadata['revisionAuthorPolicy'] = 'metadata-only-native-review';
        }
        $formattingRuns = $this->formattingRunReport($wordDocument, $tableStream, $revisionAuthors);
        if ($formattingRuns !== []) {
            $metadata['formattingRunCount'] = count($formattingRuns);
            $metadata['paragraphFormattingRunCount'] = count(array_filter(
                $formattingRuns,
                static fn (array $run): bool => ($run['kind'] ?? null) === 'paragraph'
            ));
            $metadata['characterFormattingRunCount'] = count(array_filter(
                $formattingRuns,
                static fn (array $run): bool => ($run['kind'] ?? null) === 'character'
            ));
            $revisionMarkedFormattingRunCount = count(array_filter(
                $formattingRuns,
                static fn (array $run): bool => isset($run['revisionMarks']) && is_array($run['revisionMarks']) && $run['revisionMarks'] !== []
            ));
            if ($revisionMarkedFormattingRunCount > 0) {
                $metadata['revisionMarkedFormattingRunCount'] = $revisionMarkedFormattingRunCount;
                $metadata['formattingRevisionPolicy'] = 'metadata-only-native-review';
            }
            $pictureDataFormattingRunCount = count(array_filter(
                $formattingRuns,
                static fn (array $run): bool => isset($run['pictureData']) && is_array($run['pictureData']) && $run['pictureData'] !== []
            ));
            if ($pictureDataFormattingRunCount > 0) {
                $metadata['pictureDataFormattingRunCount'] = $pictureDataFormattingRunCount;
                $metadata['pictureDataExtractionPolicy'] = 'metadata-only-native-review';
            }
            $textPropertyFormattingRunCount = count(array_filter(
                $formattingRuns,
                static fn (array $run): bool => isset($run['textProperties']) && is_array($run['textProperties']) && $run['textProperties'] !== []
            ));
            if ($textPropertyFormattingRunCount > 0) {
                $metadata['textPropertyFormattingRunCount'] = $textPropertyFormattingRunCount;
                $metadata['textPropertyFormattingPolicy'] = 'metadata-only-native-review';
            }
            $paragraphPropertyFormattingRunCount = count(array_filter(
                $formattingRuns,
                static fn (array $run): bool => isset($run['paragraphProperties']) && is_array($run['paragraphProperties']) && $run['paragraphProperties'] !== []
            ));
            if ($paragraphPropertyFormattingRunCount > 0) {
                $metadata['paragraphPropertyFormattingRunCount'] = $paragraphPropertyFormattingRunCount;
                $metadata['paragraphPropertyFormattingPolicy'] = 'metadata-only-native-review';
            }
            $metadata['formattingRuns'] = $formattingRuns;
        }
        $listTable = $this->listTableReport($wordDocument, $tableStream);
        $listFormats = $listTable['formats'];
        $listOverrides = $listTable['overrides'];
        if ($listFormats !== []) {
            $metadata['listFormatCount'] = count($listFormats);
            $metadata['listLevelCount'] = array_sum(array_map(
                static fn (array $format): int => count(is_array($format['levels'] ?? null) ? $format['levels'] : []),
                $listFormats
            ));
            $metadata['listFormats'] = $listFormats;
        }
        if ($listOverrides !== []) {
            $metadata['listOverrideCount'] = count($listOverrides);
            $metadata['listOverrides'] = $listOverrides;
        }
        $listLevelFormattingCounts = $this->listLevelFormattingCounts($listFormats, $listOverrides);
        if ($listLevelFormattingCounts['paragraphProperties'] > 0 || $listLevelFormattingCounts['textProperties'] > 0) {
            $metadata['listLevelFormattingPolicy'] = 'metadata-only-native-review';
            if ($listLevelFormattingCounts['paragraphProperties'] > 0) {
                $metadata['listLevelParagraphPropertyCount'] = $listLevelFormattingCounts['paragraphProperties'];
            }
            if ($listLevelFormattingCounts['textProperties'] > 0) {
                $metadata['listLevelTextPropertyCount'] = $listLevelFormattingCounts['textProperties'];
            }
        }
        $sections = $this->sectionReport($wordDocument, $tableStream, $textResult['text']);
        if ($sections !== []) {
            $metadata['sectionCount'] = count($sections);
            $metadata['sections'] = $sections;
        }
        $bookmarks = $this->standardBookmarkReport($wordDocument, $tableStream, $textResult['text']);
        if ($bookmarks !== []) {
            $metadata['bookmarkCount'] = count($bookmarks);
            $metadata['bookmarks'] = $bookmarks;
        }
        $footnotes = $this->noteReferenceReport('footnote', $wordDocument, $tableStream, $textResult['text'], $subdocumentTexts);
        if ($footnotes !== []) {
            $metadata['footnoteReferenceCount'] = count($footnotes);
            $metadata['footnotes'] = $footnotes;
        }
        $endnotes = $this->noteReferenceReport('endnote', $wordDocument, $tableStream, $textResult['text'], $subdocumentTexts);
        if ($endnotes !== []) {
            $metadata['endnoteReferenceCount'] = count($endnotes);
            $metadata['endnotes'] = $endnotes;
        }
        $commentAuthors = $this->commentAuthorReport($wordDocument, $tableStream);
        if ($commentAuthors !== []) {
            $metadata['commentAuthorCount'] = count($commentAuthors);
            $metadata['commentAuthors'] = $commentAuthors;
        }
        $captionDefinitions = $this->captionDefinitionReport($wordDocument, $tableStream, $fib);
        if ($captionDefinitions !== []) {
            $metadata['captionDefinitionCount'] = count($captionDefinitions);
            $metadata['captionDefinitions'] = $captionDefinitions;
            $metadata['captionDefinitionPolicy'] = 'metadata-only-native-review';
        }
        $autoCaptionRules = $this->autoCaptionRuleReport($wordDocument, $tableStream, $fib, $captionDefinitions);
        if ($autoCaptionRules !== []) {
            $metadata['autoCaptionRuleCount'] = count($autoCaptionRules);
            $metadata['autoCaptionRules'] = $autoCaptionRules;
            $metadata['autoCaptionPolicy'] = 'metadata-only-native-review';
        }
        $comments = $this->commentReferenceReport($wordDocument, $tableStream, $textResult['text'], $subdocumentTexts, $commentAuthors);
        if ($comments !== []) {
            $metadata['commentReferenceCount'] = count($comments);
            $metadata['comments'] = $comments;
        }
        $fieldReport = $this->fieldCharacterReport($wordDocument, $tableStream, $textResult['text'], $subdocumentTexts);
        $fieldCharacters = $fieldReport['characters'];
        $fields = $fieldReport['fields'];
        $fieldStories = $fieldReport['stories'];
        if ($fieldCharacters !== []) {
            $metadata['fieldCharacterCount'] = count($fieldCharacters);
            $metadata['fieldCount'] = count($fields);
            $metadata['fieldCharacters'] = $fieldCharacters;
            $metadata['fields'] = $fields;
            if ($fieldStories !== []) {
                $metadata['fieldStoryCount'] = count($fieldStories);
                $metadata['fieldStories'] = $fieldStories;
            }
        }
        $fileCharacterRanges = $this->textFileCharacterRanges($textResult, $fib);
        $inlineTextFormattingApplications = $this->inlineTextFormattingApplications($formattingRuns, $fileCharacterRanges);
        if ($inlineTextFormattingApplications !== []) {
            $metadata['inlineTextFormattingApplicationCount'] = count($inlineTextFormattingApplications);
            $metadata['inlineTextFormattingPolicy'] = 'semantic-inline-native-review';
        }
        $hiddenTextSuppressions = $this->hiddenTextSuppressionApplications($formattingRuns, $fileCharacterRanges);
        if ($hiddenTextSuppressions !== []) {
            $metadata['hiddenTextSuppressionCount'] = count($hiddenTextSuppressions);
            $metadata['hiddenTextSuppressedCharacterCount'] = array_sum(array_map(
                static fn (array $suppression): int => (int) ($suppression['characterCount'] ?? 0),
                $hiddenTextSuppressions
            ));
            $metadata['hiddenTextSuppressionPolicy'] = 'suppressed-hidden-text-native-review';
        }
        $formFieldDataReferences = $this->formFieldDataReferenceReport($fields, $formattingRuns, $dataStream, $fileCharacterRanges);
        if ($formFieldDataReferences !== []) {
            $metadata['formFieldDataReferenceCount'] = count($formFieldDataReferences);
            $metadata['formFieldDataReferences'] = $formFieldDataReferences;
            $metadata['formFieldDataExtractionPolicy'] = 'metadata-only-native-review';
        }
        $embeddedObjects = $this->embeddedObjectReport($compoundFile);
        if ($embeddedObjects !== []) {
            $metadata['embeddedObjectCount'] = count($embeddedObjects);
        }
        $embeddedObjectReferences = $this->embeddedObjectReferenceReport(
            $textResult['text'],
            $embeddedObjects,
            ($fib['hasPictures'] ?? false) === true
        );
        if ($embeddedObjectReferences !== []) {
            $metadata['embeddedObjectReferenceCount'] = count($embeddedObjectReferences);
            $metadata['embeddedObjectReferences'] = $embeddedObjectReferences;
        }
        $pictureReferences = $this->pictureReferenceReport(
            $textResult['text'],
            ($fib['hasPictures'] ?? false) === true,
            $embeddedObjectReferences,
            $formattingRuns,
            $dataStream,
            $fileCharacterRanges
        );
        if ($pictureReferences !== []) {
            $metadata['pictureReferenceCount'] = count($pictureReferences);
            $metadata['pictureReferences'] = $pictureReferences;
            $metadata['pictureExtractionPolicy'] = 'metadata-only-native-review';
        }
        $macroProjects = $this->macroProjectReport($compoundFile);
        if ($macroProjects !== []) {
            $metadata['containsMacros'] = true;
            $metadata['macroProjectCount'] = count($macroProjects);
            $metadata['macroPolicy'] = 'disabled-native-review';
        }

        $attrs = [
            'sourceFormat' => 'doc',
            'cfbStreams' => $compoundFile->streamNames(),
            'cfbStreamDirectory' => $streamDirectory,
            'cfbDirectoryEntries' => $directoryEntries,
            'textSource' => $textResult['source'],
            'tableStream' => $tableStreamName,
            'meta' => $metadata,
            'subdocuments' => $subdocuments,
            'headerFooterStories' => $headerFooterStories,
            'styles' => $styles,
            'formattingRuns' => $formattingRuns,
            'listFormats' => $listFormats,
            'listOverrides' => $listOverrides,
            'sections' => $sections,
            'bookmarks' => $bookmarks,
            'footnotes' => $footnotes,
            'endnotes' => $endnotes,
            'comments' => $comments,
            'commentAuthors' => $commentAuthors,
            'revisionAuthors' => $revisionAuthors,
            'captionDefinitions' => $captionDefinitions,
            'autoCaptionRules' => $autoCaptionRules,
            'fieldCharacters' => $fieldCharacters,
            'fields' => $fields,
            'fieldStories' => $fieldStories,
            'formFieldDataReferences' => $formFieldDataReferences,
            'inlineTextFormattingApplications' => $inlineTextFormattingApplications,
            'hiddenTextSuppressions' => $hiddenTextSuppressions,
            'embeddedObjects' => $embeddedObjects,
            'embeddedObjectReferences' => $embeddedObjectReferences,
            'pictureReferences' => $pictureReferences,
            'macroProjects' => $macroProjects,
            'associatedStrings' => $associatedStrings,
            'documentProperties' => $documentProperties,
            'documentVariables' => $documentVariables,
            'saveHistory' => $saveHistory,
            'externalFileReferences' => $externalFileReferences,
            'subdocumentReferences' => $subdocumentReferences,
            'mailMergeSettings' => $mailMergeSettings,
            'routeSlip' => $routeSlip,
        ];

        $previousAssociatedStrings = $this->activeAssociatedStrings;
        $previousExternalFileReferences = $this->activeExternalFileReferences;
        $previousListFormats = $this->activeListFormats;
        $previousListOverrides = $this->activeListOverrides;
        $previousFormFieldDataReferences = $this->activeFormFieldDataReferences;
        $previousInlineTextFormattingApplications = $this->activeInlineTextFormattingApplications;
        $previousHiddenTextSuppressions = $this->activeHiddenTextSuppressions;
        $this->activeAssociatedStrings = $associatedStrings;
        $this->activeExternalFileReferences = $externalFileReferences;
        $this->activeListFormats = $listFormats;
        $this->activeListOverrides = $listOverrides;
        $this->activeFormFieldDataReferences = $this->formFieldDataReferencesByBeginCp($formFieldDataReferences);
        $this->activeInlineTextFormattingApplications = $inlineTextFormattingApplications;
        $this->activeHiddenTextSuppressions = $hiddenTextSuppressions;
        try {
            $documentChildren = $this->paragraphNodes(
                $textResult['text'],
                $bookmarks,
                array_merge($footnotes, $endnotes, $comments),
                $embeddedObjectReferences,
                $pictureReferences
            );
        } finally {
            $this->activeAssociatedStrings = $previousAssociatedStrings;
            $this->activeExternalFileReferences = $previousExternalFileReferences;
            $this->activeListFormats = $previousListFormats;
            $this->activeListOverrides = $previousListOverrides;
            $this->activeFormFieldDataReferences = $previousFormFieldDataReferences;
            $this->activeInlineTextFormattingApplications = $previousInlineTextFormattingApplications;
            $this->activeHiddenTextSuppressions = $previousHiddenTextSuppressions;
        }

        return [
            'document' => new AstNode('document', $attrs, $documentChildren),
            'metadata' => $metadata,
            'streams' => $compoundFile->streamNames(),
            'streamDirectory' => $streamDirectory,
            'directoryEntries' => $directoryEntries,
            'fib' => $fib + ['textSource' => $textResult['source']],
            'subdocuments' => $subdocuments,
            'headerFooterStories' => $headerFooterStories,
            'styles' => $styles,
            'formattingRuns' => $formattingRuns,
            'listFormats' => $listFormats,
            'listOverrides' => $listOverrides,
            'sections' => $sections,
            'bookmarks' => $bookmarks,
            'footnotes' => $footnotes,
            'endnotes' => $endnotes,
            'comments' => $comments,
            'commentAuthors' => $commentAuthors,
            'revisionAuthors' => $revisionAuthors,
            'captionDefinitions' => $captionDefinitions,
            'autoCaptionRules' => $autoCaptionRules,
            'fieldCharacters' => $fieldCharacters,
            'fields' => $fields,
            'fieldStories' => $fieldStories,
            'formFieldDataReferences' => $formFieldDataReferences,
            'hiddenTextSuppressions' => $hiddenTextSuppressions,
            'embeddedObjects' => $embeddedObjects,
            'embeddedObjectReferences' => $embeddedObjectReferences,
            'pictureReferences' => $pictureReferences,
            'macroProjects' => $macroProjects,
            'associatedStrings' => $associatedStrings,
            'documentProperties' => $documentProperties,
            'documentVariables' => $documentVariables,
            'saveHistory' => $saveHistory,
            'externalFileReferences' => $externalFileReferences,
            'subdocumentReferences' => $subdocumentReferences,
            'mailMergeSettings' => $mailMergeSettings,
            'routeSlip' => $routeSlip,
        ];
    }

    /**
     * Word 6/95 uses the same FIB base text range but not the Word 97 table
     * structures parsed above. Keep this path to text and paragraph recovery.
     *
     * @param array<string,mixed> $fib
     * @return array<string,mixed>
     */
    private function readWord6To95CompoundFile(CompoundFileBinary $compoundFile, string $wordDocument, array $fib): array
    {
        $textResult = $this->extractText($wordDocument, null, $fib);
        $streamDirectory = $this->streamDirectoryReport($compoundFile);
        $directoryEntries = $this->directoryEntryReport($compoundFile);
        $metadata = $this->readMetadata($compoundFile);
        $metadata['fibBase'] = $this->fibBaseReviewMetadata($fib);
        $metadata['legacyDocReaderMode'] = 'word6-95-basic-text';
        $metadata['legacyDocStructurePolicy'] = 'paragraph-text-only';
        $metadata['legacyDocUnsupportedStructure'] = ['styles', 'lists', 'tables', 'drawings', 'headers-footers'];
        if ($streamDirectory !== []) {
            $metadata['cfbStreamCount'] = count($streamDirectory);
        }

        $empty = [];
        $attrs = [
            'sourceFormat' => 'doc',
            'cfbStreams' => $compoundFile->streamNames(),
            'cfbStreamDirectory' => $streamDirectory,
            'cfbDirectoryEntries' => $directoryEntries,
            'textSource' => $textResult['source'],
            'tableStream' => null,
            'meta' => $metadata,
            'subdocuments' => $empty,
            'headerFooterStories' => $empty,
            'styles' => $empty,
            'formattingRuns' => $empty,
            'listFormats' => $empty,
            'listOverrides' => $empty,
            'sections' => $empty,
            'bookmarks' => $empty,
            'footnotes' => $empty,
            'endnotes' => $empty,
            'comments' => $empty,
            'commentAuthors' => $empty,
            'revisionAuthors' => $empty,
            'captionDefinitions' => $empty,
            'autoCaptionRules' => $empty,
            'fieldCharacters' => $empty,
            'fields' => $empty,
            'fieldStories' => $empty,
            'formFieldDataReferences' => $empty,
            'inlineTextFormattingApplications' => $empty,
            'hiddenTextSuppressions' => $empty,
            'embeddedObjects' => $empty,
            'embeddedObjectReferences' => $empty,
            'pictureReferences' => $empty,
            'macroProjects' => $empty,
            'associatedStrings' => $empty,
            'documentProperties' => $empty,
            'documentVariables' => $empty,
            'saveHistory' => $empty,
            'externalFileReferences' => $empty,
            'subdocumentReferences' => $empty,
            'mailMergeSettings' => $empty,
            'routeSlip' => $empty,
        ];

        return [
            'document' => new AstNode('document', $attrs, $this->paragraphNodes((string) $textResult['text'])),
            'metadata' => $metadata,
            'streams' => $compoundFile->streamNames(),
            'streamDirectory' => $streamDirectory,
            'directoryEntries' => $directoryEntries,
            'fib' => $fib + ['textSource' => $textResult['source']],
            'subdocuments' => $empty,
            'headerFooterStories' => $empty,
            'styles' => $empty,
            'formattingRuns' => $empty,
            'listFormats' => $empty,
            'listOverrides' => $empty,
            'sections' => $empty,
            'bookmarks' => $empty,
            'footnotes' => $empty,
            'endnotes' => $empty,
            'comments' => $empty,
            'commentAuthors' => $empty,
            'revisionAuthors' => $empty,
            'captionDefinitions' => $empty,
            'autoCaptionRules' => $empty,
            'fieldCharacters' => $empty,
            'fields' => $empty,
            'fieldStories' => $empty,
            'formFieldDataReferences' => $empty,
            'hiddenTextSuppressions' => $empty,
            'embeddedObjects' => $empty,
            'embeddedObjectReferences' => $empty,
            'pictureReferences' => $empty,
            'macroProjects' => $empty,
            'associatedStrings' => $empty,
            'documentProperties' => $empty,
            'documentVariables' => $empty,
            'saveHistory' => $empty,
            'externalFileReferences' => $empty,
            'subdocumentReferences' => $empty,
            'mailMergeSettings' => $empty,
            'routeSlip' => $empty,
        ];
    }

    public function readDocument(string $bytes): AstNode
    {
        return $this->readBytes($bytes)['document'];
    }

    /**
     * @return array<string,mixed>
     */
    public function decodeFormFieldData(string $bytes): array
    {
        if (strlen($bytes) < 10) {
            throw new \RuntimeException('Legacy DOC FFData structure is truncated');
        }

        $cursor = 0;
        $version = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($version !== 0xffffffff) {
            throw new \RuntimeException('Legacy DOC FFData version must be 0xffffffff');
        }

        $bits = self::u16($bytes, $cursor);
        $cursor += 2;
        $fieldTypeCode = $bits & 0x0003;
        $currentStateCode = ($bits >> 2) & 0x001f;
        $hasOwnHelpText = (($bits >> 7) & 0x0001) === 1;
        $hasOwnStatusText = (($bits >> 8) & 0x0001) === 1;
        $protected = (($bits >> 9) & 0x0001) === 1;
        $checkboxAutoSize = (($bits >> 10) & 0x0001) === 1;
        $textTypeCode = ($bits >> 11) & 0x0007;
        $recalculateOnExit = (($bits >> 14) & 0x0001) === 1;
        $hasListBox = (($bits >> 15) & 0x0001) === 1;
        $fieldType = $this->legacyDocFormFieldTypeName($fieldTypeCode);

        if ($fieldTypeCode === 0 && $currentStateCode !== 0) {
            throw new \RuntimeException('Legacy DOC FFData textbox state bits must be zero');
        }
        if ($fieldTypeCode === 1 && !in_array($currentStateCode, [0, 1, 25], true)) {
            throw new \RuntimeException('Legacy DOC FFData checkbox state must be unchecked, checked, or undefined');
        }
        if ($fieldTypeCode !== 1 && $checkboxAutoSize) {
            throw new \RuntimeException('Legacy DOC FFData checkbox auto-size bit must be zero for non-checkbox fields');
        }
        if ($fieldTypeCode !== 0 && $textTypeCode !== 0) {
            throw new \RuntimeException('Legacy DOC FFData text-type bits must be zero for non-textbox fields');
        }
        if ($fieldTypeCode === 0 && $textTypeCode > 5) {
            throw new \RuntimeException('Legacy DOC FFData textbox type is unsupported');
        }
        if ($fieldTypeCode === 2 && !$hasListBox) {
            throw new \RuntimeException('Legacy DOC FFData dropdown fields must carry a list box');
        }
        if ($fieldTypeCode !== 2 && $hasListBox) {
            throw new \RuntimeException('Legacy DOC FFData list-box bit must be zero for non-dropdown fields');
        }

        $maxLength = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($fieldTypeCode === 0 && $maxLength > 32767) {
            throw new \RuntimeException('Legacy DOC FFData textbox maximum length is outside the supported range');
        }
        if ($fieldTypeCode !== 0 && $maxLength !== 0) {
            throw new \RuntimeException('Legacy DOC FFData non-textbox fields must not declare a text maximum length');
        }

        $checkboxSizeHalfPoints = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($fieldTypeCode === 1 && ($checkboxSizeHalfPoints < 2 || $checkboxSizeHalfPoints > 3168)) {
            throw new \RuntimeException('Legacy DOC FFData checkbox size must be between 2 and 3168 half-points');
        }

        $name = $this->readLegacyDocXstz($bytes, $cursor, 'FFData field name', 20);
        $record = [
            'source' => 'FFData',
            'versionHex' => '0xffffffff',
            'byteCount' => strlen($bytes),
            'bits' => $bits,
            'fieldType' => $fieldType,
            'fieldTypeCode' => $fieldTypeCode,
            'currentStateCode' => $currentStateCode,
            'protected' => $protected,
            'hasOwnHelpText' => $hasOwnHelpText,
            'hasOwnStatusText' => $hasOwnStatusText,
            'recalculateOnExit' => $recalculateOnExit,
            'hasListBox' => $hasListBox,
            'name' => $name,
        ];

        if ($fieldTypeCode === 0) {
            $defaultText = $this->readLegacyDocXstz($bytes, $cursor, 'FFData default textbox text', 255);
            if (in_array($textTypeCode, [3, 4], true) && $defaultText !== '') {
                throw new \RuntimeException('Legacy DOC FFData current date/time textboxes must not carry default text');
            }
            $record['textType'] = $this->legacyDocTextFormFieldTypeName($textTypeCode);
            $record['textTypeCode'] = $textTypeCode;
            $record['maxLength'] = $maxLength;
            $record['defaultText'] = $defaultText;
        } else {
            if ($cursor + 2 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC FFData default state is truncated');
            }
            $defaultStateCode = self::u16($bytes, $cursor);
            $cursor += 2;
            $record['defaultStateCode'] = $defaultStateCode;

            if ($fieldTypeCode === 1) {
                if (!in_array($defaultStateCode, [0, 1], true)) {
                    throw new \RuntimeException('Legacy DOC FFData checkbox default state must be unchecked or checked');
                }
                $record['checkboxAutoSize'] = $checkboxAutoSize;
                $record['checkboxSizeHalfPoints'] = $checkboxSizeHalfPoints;
                $record['defaultChecked'] = $defaultStateCode === 1;
                $record['checked'] = $currentStateCode === 1;
                $record['checkboxState'] = $currentStateCode === 25
                    ? 'undefined'
                    : ($currentStateCode === 1 ? 'checked' : 'unchecked');
            } else {
                $record['defaultSelectedIndex'] = $defaultStateCode;
                $record['selectedIndex'] = $currentStateCode === 25 ? null : $currentStateCode;
                $record['selectionUndefined'] = $currentStateCode === 25;
            }
        }

        $textFormat = $this->readLegacyDocXstz($bytes, $cursor, 'FFData textbox format', 64);
        if ($fieldTypeCode !== 0 && $textFormat !== '') {
            throw new \RuntimeException('Legacy DOC FFData non-textbox fields must not carry a textbox format');
        }
        if ($textFormat !== '') {
            $record['textFormat'] = $textFormat;
        }

        $helpText = $this->readLegacyDocXstz($bytes, $cursor, 'FFData help text', 255);
        if ($helpText !== '') {
            $record['helpText'] = $helpText;
        }

        $statusText = $this->readLegacyDocXstz($bytes, $cursor, 'FFData status text', 138);
        if ($statusText !== '') {
            $record['statusText'] = $statusText;
        }

        $entryMacro = $this->readLegacyDocXstz($bytes, $cursor, 'FFData entry macro', 32);
        if ($entryMacro !== '') {
            $record['entryMacro'] = $entryMacro;
        }

        $exitMacro = $this->readLegacyDocXstz($bytes, $cursor, 'FFData exit macro', 32);
        if ($exitMacro !== '') {
            $record['exitMacro'] = $exitMacro;
        }

        if ($fieldTypeCode === 2) {
            $items = $this->readLegacyDocUnicodeSttbStrings($bytes, $cursor, 'FFData dropdown list', 25, 255);
            if ($items === []) {
                throw new \RuntimeException('Legacy DOC FFData dropdown list must contain at least one item');
            }
            $defaultIndex = (int) $record['defaultSelectedIndex'];
            if (!array_key_exists($defaultIndex, $items)) {
                throw new \RuntimeException('Legacy DOC FFData dropdown default index is outside the list');
            }
            if ($currentStateCode !== 25 && !array_key_exists($currentStateCode, $items)) {
                throw new \RuntimeException('Legacy DOC FFData dropdown selected index is outside the list');
            }

            $record['dropDownItems'] = $items;
            $record['dropDownItemCount'] = count($items);
            $record['defaultDropDownItem'] = $items[$defaultIndex];
            if ($currentStateCode !== 25) {
                $record['selectedDropDownItem'] = $items[$currentStateCode];
            }
        }

        if ($cursor !== strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC FFData structure contains trailing bytes');
        }

        return $record;
    }

    /**
     * @return array<string,mixed>
     */
    private function readFib(string $wordDocument): array
    {
        if (strlen($wordDocument) < 32) {
            throw new \InvalidArgumentException('WordDocument stream is too short to contain a FIB');
        }
        $wIdent = self::u16($wordDocument, 0);
        $nFib = self::u16($wordDocument, 2);
        $oldBinaryFormat = $wIdent === 0xa5dc;
        if ($wIdent !== 0xa5ec && !$oldBinaryFormat) {
            throw new \InvalidArgumentException('WordDocument stream has an invalid FIB signature');
        }
        if ($oldBinaryFormat && !in_array($nFib, [0x0065, 0x0068], true)) {
            throw new \InvalidArgumentException('WordDocument stream has an unsupported Word 6/95 FIB version');
        }

        $flags = self::u16($wordDocument, 10);
        $languageId = self::u16($wordDocument, 6);
        $pnNext = self::u16($wordDocument, 8);
        $nFibBack = self::u16($wordDocument, 12);
        $lKey = self::u32($wordDocument, 14);
        $fcMin = self::u32($wordDocument, 24);
        $fcMac = self::u32($wordDocument, 28);
        $fibRgLw97 = $oldBinaryFormat ? [] : $this->fibRgLw97ReviewMetadata($wordDocument);

        $fib = [
            'wIdent' => $wIdent,
            'nFib' => $nFib,
            'nFibBack' => $nFibBack,
            'languageId' => $languageId,
            'languageTag' => $this->legacyLanguageTag($languageId),
            'pnNext' => $pnNext,
            'lKey' => $lKey,
            'flags' => $flags,
            'flagNames' => $this->fibFlagNames($flags),
            'quickSaveCount' => ($flags >> 4) & 0x0f,
            'fcMin' => $fcMin,
            'fcMac' => $fcMac,
            'tableStream' => ($flags & 0x0200) !== 0 ? '1Table' : '0Table',
            'template' => ($flags & 0x0001) !== 0,
            'glossary' => ($flags & 0x0002) !== 0,
            'complex' => ($flags & 0x0004) !== 0,
            'hasPictures' => ($flags & 0x0008) !== 0,
            'encrypted' => ($flags & 0x0100) !== 0,
            'readOnlyRecommended' => ($flags & 0x0400) !== 0,
            'writeReservation' => ($flags & 0x0800) !== 0,
            'extendedCharacters' => ($flags & 0x1000) !== 0,
            'loadOverride' => ($flags & 0x2000) !== 0,
            'farEast' => ($flags & 0x4000) !== 0,
            'obfuscated' => ($flags & 0x8000) !== 0,
        ];
        if ($oldBinaryFormat) {
            $fib['oldBinaryFormat'] = true;
            $fib['oldWordVersion'] = $nFib === 0x0065 ? '6.0' : '95';
        }
        if ($fibRgLw97 !== []) {
            $fib['fibRgLw97'] = $fibRgLw97;
        }

        return $fib;
    }

    /**
     * @param array<string,mixed> $fib
     * @return array<string,mixed>
     */
    private function fibBaseReviewMetadata(array $fib): array
    {
        $metadata = [
            'nFib' => (int) ($fib['nFib'] ?? 0),
            'nFibBack' => (int) ($fib['nFibBack'] ?? 0),
            'languageId' => (int) ($fib['languageId'] ?? 0),
            'pnNext' => (int) ($fib['pnNext'] ?? 0),
            'tableStream' => (string) ($fib['tableStream'] ?? ''),
            'quickSaveCount' => (int) ($fib['quickSaveCount'] ?? 0),
            'flags' => is_array($fib['flagNames'] ?? null) ? $fib['flagNames'] : [],
            'template' => ($fib['template'] ?? false) === true,
            'glossary' => ($fib['glossary'] ?? false) === true,
            'complex' => ($fib['complex'] ?? false) === true,
            'hasPictures' => ($fib['hasPictures'] ?? false) === true,
            'readOnlyRecommended' => ($fib['readOnlyRecommended'] ?? false) === true,
            'writeReservation' => ($fib['writeReservation'] ?? false) === true,
            'extendedCharacters' => ($fib['extendedCharacters'] ?? false) === true,
            'loadOverride' => ($fib['loadOverride'] ?? false) === true,
            'farEast' => ($fib['farEast'] ?? false) === true,
        ];

        if (($fib['languageTag'] ?? null) !== null) {
            $metadata['languageTag'] = (string) $fib['languageTag'];
        }
        if (isset($fib['fibRgLw97']) && is_array($fib['fibRgLw97'])) {
            $metadata['fibRgLw97'] = $fib['fibRgLw97'];
        }

        return $metadata;
    }

    /**
     * @return array<string,mixed>
     */
    private function fibRgLw97ReviewMetadata(string $wordDocument): array
    {
        if (strlen($wordDocument) < 0x006c) {
            return [];
        }

        $reserved3 = self::signed32(self::u32($wordDocument, self::FIB_RGLW97_RESERVED3));
        if ($reserved3 !== 0) {
            throw new \RuntimeException('Legacy DOC FibRgLw97 reserved3 must be zero');
        }

        $record = [
            'cbMac' => self::u32($wordDocument, self::FIB_RGLW97_CB_MAC),
        ];
        if ($record['cbMac'] > strlen($wordDocument)) {
            throw new \RuntimeException('Legacy DOC FibRgLw97 cbMac points outside WordDocument');
        }

        $supplementalCharacters = 0;
        foreach (self::FIB_RGLW97_CCP_FIELDS as $field => $offset) {
            $value = self::signed32(self::u32($wordDocument, $offset));
            if ($value < 0) {
                throw new \RuntimeException('Legacy DOC FibRgLw97 ' . $field . ' must not be negative');
            }
            $record[$field] = $value;
            if ($field !== 'ccpText') {
                $supplementalCharacters += $value;
            }
        }

        $declaresCpCounts = (int) $record['ccpText'] > 0 || $supplementalCharacters > 0;
        if (!$declaresCpCounts && (int) $record['cbMac'] === 0) {
            return [];
        }

        $record['supplementalSubdocumentCharacters'] = $supplementalCharacters;
        $record['hasSupplementalSubdocuments'] = $supplementalCharacters > 0;
        if ($declaresCpCounts) {
            $record['pieceTableExpectedLastCp'] = (int) $record['ccpText']
                + ($supplementalCharacters > 0 ? 1 : 0)
                + $supplementalCharacters;
            $record['subdocuments'] = $this->fibRgLw97SubdocumentRanges($record);
        }

        return $record;
    }

    /**
     * @param array<string,mixed> $fibRgLw97
     * @return list<array{type:string,startCp:int,endCp:int,characterCount:int}>
     */
    private function fibRgLw97SubdocumentRanges(array $fibRgLw97): array
    {
        $mainCount = (int) ($fibRgLw97['ccpText'] ?? 0);
        $ranges = [[
            'type' => 'main',
            'startCp' => 0,
            'endCp' => $mainCount,
            'characterCount' => $mainCount,
        ]];

        $cursor = $mainCount;
        $hasSupplemental = ($fibRgLw97['hasSupplementalSubdocuments'] ?? false) === true;
        if ($hasSupplemental) {
            $cursor++;
        }
        foreach (self::FIB_RGLW97_SUBDOCUMENT_TYPES as $field => $type) {
            $count = (int) ($fibRgLw97[$field] ?? 0);
            if ($count === 0) {
                continue;
            }

            $ranges[] = [
                'type' => $type,
                'startCp' => $cursor,
                'endCp' => $cursor + $count,
                'characterCount' => $count,
            ];
            $cursor += $count;
        }

        return $ranges;
    }

    /**
     * @return list<string>
     */
    private function fibFlagNames(int $flags): array
    {
        $map = [
            0x0001 => 'template',
            0x0002 => 'glossary',
            0x0004 => 'complex',
            0x0008 => 'hasPictures',
            0x0100 => 'encrypted',
            0x0200 => 'tableStream1',
            0x0400 => 'readOnlyRecommended',
            0x0800 => 'writeReservation',
            0x1000 => 'extendedCharacters',
            0x2000 => 'loadOverride',
            0x4000 => 'farEast',
            0x8000 => 'obfuscated',
        ];

        $names = [];
        foreach ($map as $bit => $name) {
            if (($flags & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function legacyLanguageTag(int $languageId): ?string
    {
        return match ($languageId) {
            0x0404 => 'zh-TW',
            0x0407 => 'de-DE',
            0x0409 => 'en-US',
            0x040c => 'fr-FR',
            0x0410 => 'it-IT',
            0x0411 => 'ja-JP',
            0x0412 => 'ko-KR',
            0x0413 => 'nl-NL',
            0x0416 => 'pt-BR',
            0x0419 => 'ru-RU',
            0x0804 => 'zh-CN',
            0x0809 => 'en-GB',
            0x0816 => 'pt-PT',
            0x0c0a => 'es-ES',
            default => null,
        };
    }

    /**
     * @return array{text:string,source:string,fullText?:string,subdocuments?:list<array<string,mixed>>,fileCharacterRanges?:list<array<string,int>>}
     */
    private function extractText(string $wordDocument, ?string $tableStream, ?array $fib = null): array
    {
        $fib ??= $this->readFib($wordDocument);
        if (($fib['oldBinaryFormat'] ?? false) === true && ($fib['complex'] ?? false) === true) {
            if (strlen($wordDocument) < 0x164) {
                throw new \RuntimeException('Legacy DOC Word 6/95 complex FIB is truncated before the CLX offset');
            }
            $fcClx = self::u32($wordDocument, 0x160);
            if ($fcClx === 0 || $fcClx >= strlen($wordDocument)) {
                throw new \RuntimeException('Legacy DOC Word 6/95 complex FIB has an invalid CLX offset');
            }

            return ['source' => 'word6-95-piece-table'] + $this->parseClx(
                substr($wordDocument, $fcClx),
                $wordDocument,
                []
            );
        }
        if ($tableStream !== null) {
            $pieceTextResult = $this->extractPieceTableText(
                $wordDocument,
                $tableStream,
                is_array($fib['fibRgLw97'] ?? null) ? $fib['fibRgLw97'] : []
            );
            if ($pieceTextResult !== null) {
                return ['source' => 'piece-table'] + $pieceTextResult;
            }
            if (($fib['complex'] ?? false) === true) {
                throw new \RuntimeException('Legacy DOC complex FIB does not contain a CLX piece table');
            }
        } elseif (($fib['complex'] ?? false) === true) {
            throw new \RuntimeException('Legacy DOC complex FIB requires a table stream with a CLX piece table');
        }

        $fcMin = (int) $fib['fcMin'];
        $fcMac = (int) $fib['fcMac'];
        if ($fcMin > $fcMac || $fcMac > strlen($wordDocument)) {
            throw new \RuntimeException('WordDocument FIB text range points outside the stream');
        }

        $bytes = substr($wordDocument, $fcMin, $fcMac - $fcMin);
        if ($fib['extendedCharacters'] === true) {
            if (strlen($bytes) % 2 !== 0) {
                throw new \RuntimeException('Legacy DOC Unicode text range has an odd byte length');
            }

            return [
                'text' => $this->decodeUtf16Le($bytes),
                'source' => 'fib-text-range',
                'fileCharacterRanges' => [[
                    'cpStart' => 0,
                    'cpEnd' => intdiv(strlen($bytes), 2),
                    'fcStart' => $fcMin,
                    'fcEnd' => $fcMac,
                    'bytesPerCharacter' => 2,
                ]],
            ];
        }

        $isUtf16Le = $this->looksLikeUtf16Le($bytes);

        return [
            'text' => $isUtf16Le ? $this->decodeUtf16Le($bytes) : $this->decodeWindows1252($bytes),
            'source' => 'fib-text-range',
            'fileCharacterRanges' => [[
                'cpStart' => 0,
                'cpEnd' => $isUtf16Le ? intdiv(strlen($bytes), 2) : strlen($bytes),
                'fcStart' => $fcMin,
                'fcEnd' => $fcMac,
                'bytesPerCharacter' => $isUtf16Le ? 2 : 1,
            ]],
        ];
    }

    /**
     * @param array<string,mixed> $fibRgLw97
     * @return array{text:string,fullText:string,subdocuments:list<array<string,mixed>>,fileCharacterRanges:list<array<string,int>>}|null
     */
    private function extractPieceTableText(string $wordDocument, string $tableStream, array $fibRgLw97): ?array
    {
        foreach ([0x01a2, 0x010c] as $offset) {
            if (strlen($wordDocument) < $offset + 8) {
                continue;
            }

            $fcClx = self::u32($wordDocument, $offset);
            $lcbClx = self::u32($wordDocument, $offset + 4);
            if ($lcbClx === 0) {
                continue;
            }
            if ($fcClx > strlen($tableStream) || $fcClx + $lcbClx > strlen($tableStream)) {
                continue;
            }

            return $this->parseClx(substr($tableStream, $fcClx, $lcbClx), $wordDocument, $fibRgLw97);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $fibRgLw97
     * @return array{text:string,fullText:string,subdocuments:list<array<string,mixed>>,fileCharacterRanges:list<array<string,int>>}
     */
    private function parseClx(string $clx, string $wordDocument, array $fibRgLw97): array
    {
        $cursor = 0;
        $length = strlen($clx);
        while ($cursor < $length) {
            $marker = ord($clx[$cursor]);
            $cursor++;
            if ($marker === 0x01) {
                if ($cursor + 2 > $length) {
                    throw new \RuntimeException('Legacy DOC Clx contains a truncated PChgTabs block');
                }
                $skip = self::u16($clx, $cursor);
                $cursor += 2 + $skip;
                continue;
            }

            if ($marker !== 0x02) {
                throw new \RuntimeException('Legacy DOC Clx does not contain a piece table');
            }
            if ($cursor + 4 > $length) {
                throw new \RuntimeException('Legacy DOC piece table length is truncated');
            }

            $pieceTableLength = self::u32($clx, $cursor);
            $cursor += 4;
            if ($cursor + $pieceTableLength > $length) {
                throw new \RuntimeException('Legacy DOC piece table points outside the Clx');
            }

            return $this->parsePlcPcd(substr($clx, $cursor, $pieceTableLength), $wordDocument, $fibRgLw97);
        }

        throw new \RuntimeException('Legacy DOC Clx does not contain a piece table');
    }

    /**
     * @param array<string,mixed> $fibRgLw97
     * @return array{text:string,fullText:string,subdocuments:list<array<string,mixed>>}
     */
    private function parsePlcPcd(string $plcPcd, string $wordDocument, array $fibRgLw97): array
    {
        $length = strlen($plcPcd);
        if ($length < 4 || ($length - 4) % 12 !== 0) {
            throw new \RuntimeException('Legacy DOC piece table has an invalid PlcPcd length');
        }

        $pieceCount = intdiv($length - 4, 12);
        $cpOffsets = [];
        $previousCp = null;
        for ($index = 0; $index <= $pieceCount; $index++) {
            $cp = self::u32($plcPcd, $index * 4);
            if ($previousCp !== null && $cp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC piece table contains duplicate or unsorted CPs');
            }
            $previousCp = $cp;
            $cpOffsets[] = $cp;
        }

        $expectedLastCp = isset($fibRgLw97['pieceTableExpectedLastCp'])
            ? (int) $fibRgLw97['pieceTableExpectedLastCp']
            : null;
        if ($expectedLastCp !== null && $cpOffsets[$pieceCount] !== $expectedLastCp) {
            throw new \RuntimeException('Legacy DOC piece table final CP does not match FibRgLw97 subdocument counts');
        }

        $mainTextCpLimit = isset($fibRgLw97['ccpText']) && (
            (int) $fibRgLw97['ccpText'] > 0
            || ($fibRgLw97['hasSupplementalSubdocuments'] ?? false) === true
        )
            ? (int) $fibRgLw97['ccpText']
            : null;
        if ($mainTextCpLimit !== null && $mainTextCpLimit > $cpOffsets[$pieceCount]) {
            throw new \RuntimeException('Legacy DOC FibRgLw97 main-text CP count exceeds the piece table');
        }

        $pcdOffset = ($pieceCount + 1) * 4;
        $text = '';
        $fullText = '';
        $fileCharacterRanges = [];
        for ($index = 0; $index < $pieceCount; $index++) {
            $characters = $cpOffsets[$index + 1] - $cpOffsets[$index];
            if ($characters <= 0) {
                continue;
            }
            $mainCharacters = $characters;
            if ($mainTextCpLimit !== null) {
                if ($cpOffsets[$index] >= $mainTextCpLimit) {
                    $mainCharacters = 0;
                } else {
                    $mainCharacters = min($characters, $mainTextCpLimit - $cpOffsets[$index]);
                }
            }

            $pcdFlags = self::u16($plcPcd, $pcdOffset + ($index * 8));
            if (($pcdFlags & 0x0004) !== 0) {
                throw new \RuntimeException('Legacy DOC piece table contains a dirty Pcd flag');
            }

            $fcCompressed = self::u32($plcPcd, $pcdOffset + ($index * 8) + 2);
            $compressed = ($fcCompressed & 0x40000000) !== 0;
            $fc = $fcCompressed & 0x3fffffff;
            if ($compressed) {
                $start = intdiv($fc, 2);
                if ($start + $characters > strlen($wordDocument)) {
                    throw new \RuntimeException('Legacy DOC compressed text piece points outside WordDocument');
                }
                $fileCharacterRanges[] = [
                    'cpStart' => $cpOffsets[$index],
                    'cpEnd' => $cpOffsets[$index + 1],
                    'fcStart' => $start,
                    'fcEnd' => $start + $characters,
                    'bytesPerCharacter' => 1,
                ];
                $pieceText = $this->decodeCompressedPiece(substr($wordDocument, $start, $characters));
                $this->assertNoParagraphLastPieceIsValid($pcdFlags, $pieceText);
                $fullText .= $pieceText;
                if ($mainCharacters > 0) {
                    $text .= $mainCharacters === $characters
                        ? $pieceText
                        : $this->charactersToString(array_slice($this->unicodeCharacters($pieceText), 0, $mainCharacters));
                }
                continue;
            }

            $byteLength = $characters * 2;
            if ($fc + $byteLength > strlen($wordDocument)) {
                throw new \RuntimeException('Legacy DOC Unicode text piece points outside WordDocument');
            }
            $fileCharacterRanges[] = [
                'cpStart' => $cpOffsets[$index],
                'cpEnd' => $cpOffsets[$index + 1],
                'fcStart' => $fc,
                'fcEnd' => $fc + $byteLength,
                'bytesPerCharacter' => 2,
            ];
            $pieceText = $this->decodeUtf16Le(substr($wordDocument, $fc, $byteLength));
            $this->assertNoParagraphLastPieceIsValid($pcdFlags, $pieceText);
            $fullText .= $pieceText;
            if ($mainCharacters > 0) {
                $text .= $mainCharacters === $characters
                    ? $pieceText
                    : $this->charactersToString(array_slice($this->unicodeCharacters($pieceText), 0, $mainCharacters));
            }
        }

        if ($mainTextCpLimit === null) {
            $text = $fullText;
        }

        return [
            'text' => $text,
            'fullText' => $fullText,
            'subdocuments' => $this->pieceTableSubdocumentTextReport($fullText, $fibRgLw97),
            'fileCharacterRanges' => $fileCharacterRanges,
        ];
    }

    private function assertNoParagraphLastPieceIsValid(int $pcdFlags, string $pieceText): void
    {
        if (($pcdFlags & 0x0001) !== 0 && str_contains($pieceText, "\r")) {
            throw new \RuntimeException('Legacy DOC piece table marks a piece as paragraph-free but contains a paragraph mark');
        }
    }

    /**
     * @param array<string,mixed> $fibRgLw97
     * @return list<array{type:string,startCp:int,endCp:int,characterCount:int,text:string}>
     */
    private function pieceTableSubdocumentTextReport(string $fullText, array $fibRgLw97): array
    {
        if (!is_array($fibRgLw97['subdocuments'] ?? null)) {
            return [];
        }

        $characters = $this->unicodeCharacters($fullText);
        $textLength = count($characters);
        $subdocuments = [];
        foreach ($fibRgLw97['subdocuments'] as $subdocument) {
            if (!is_array($subdocument) || (string) ($subdocument['type'] ?? '') === 'main') {
                continue;
            }

            $type = (string) ($subdocument['type'] ?? '');
            $startCp = (int) ($subdocument['startCp'] ?? -1);
            $endCp = (int) ($subdocument['endCp'] ?? -1);
            if ($type === '' || $startCp < 0 || $endCp < $startCp || $endCp > $textLength) {
                throw new \RuntimeException('Legacy DOC FibRgLw97 subdocument range points outside piece-table text');
            }

            $subdocuments[] = [
                'type' => $type,
                'startCp' => $startCp,
                'endCp' => $endCp,
                'characterCount' => $endCp - $startCp,
                'text' => $this->charactersToString(array_slice($characters, $startCp, $endCp - $startCp)),
            ];
        }

        return $subdocuments;
    }

    /**
     * @param list<array<string,mixed>> $subdocuments
     * @return array<string,string>
     */
    private function subdocumentTextsByType(array $subdocuments): array
    {
        $texts = [];
        foreach ($subdocuments as $subdocument) {
            $type = (string) ($subdocument['type'] ?? '');
            if ($type === '' || !isset($subdocument['text']) || !is_string($subdocument['text'])) {
                continue;
            }

            $texts[$type] = $subdocument['text'];
        }

        return $texts;
    }

    /**
     * @param array<string,string> $subdocumentTexts
     * @return array{stories:list<array<string,mixed>>,declaredStoryCount:int,ignoredFinalCp:int|null}
     */
    private function headerFooterStoryReport(string $wordDocument, ?string $tableStream, array $subdocumentTexts): array
    {
        $empty = [
            'stories' => [],
            'declaredStoryCount' => 0,
            'ignoredFinalCp' => null,
        ];
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCF_HDD + 4) {
            return $empty;
        }

        $length = self::u32($wordDocument, self::FIB_LCB_PLCF_HDD);
        if ($length === 0) {
            return $empty;
        }
        if (!array_key_exists('header', $subdocumentTexts)) {
            throw new \RuntimeException('Legacy DOC PlcfHdd header/footer table is present without extracted header text');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_PLCF_HDD);

        return $this->parsePlcfhdd(
            $this->tableStreamSlice($tableStream, $offset, $length, 'PlcfHdd header/footer story table'),
            $subdocumentTexts['header']
        );
    }

    /**
     * @return array{stories:list<array<string,mixed>>,declaredStoryCount:int,ignoredFinalCp:int}
     */
    private function parsePlcfhdd(string $bytes, string $headerText): array
    {
        $length = strlen($bytes);
        if ($length < 12 || ($length % 4) !== 0) {
            throw new \RuntimeException('Legacy DOC PlcfHdd header/footer story table has an invalid length');
        }

        $characters = $this->unicodeCharacters($headerText);
        $headerCharacterCount = count($characters);
        if ($headerCharacterCount === 0) {
            throw new \RuntimeException('Legacy DOC PlcfHdd header/footer table is present for an empty header document');
        }

        $cpCount = intdiv($length, 4);
        $storyCount = $cpCount - 2;
        if ($storyCount <= 0 || $storyCount > 4096) {
            throw new \RuntimeException('Legacy DOC PlcfHdd header/footer story table declares an invalid story count');
        }
        $minimumStoryCount = count(self::HEADER_FOOTER_SEPARATOR_ROLES) + count(self::HEADER_FOOTER_SECTION_ROLES);
        if ($storyCount < $minimumStoryCount) {
            throw new \RuntimeException('Legacy DOC PlcfHdd header/footer story table is missing first-section story slots');
        }

        $cps = [];
        for ($index = 0; $index < $cpCount; $index++) {
            $cps[] = self::u32($bytes, $index * 4);
        }

        $previousCp = null;
        for ($index = 0; $index < $cpCount - 1; $index++) {
            $cp = $cps[$index];
            if ($cp >= $headerCharacterCount) {
                return [
                    'stories' => [],
                    'declaredStoryCount' => 0,
                    'ignoredFinalCp' => null,
                ];
            }
            if ($previousCp !== null && $cp < $previousCp) {
                throw new \RuntimeException('Legacy DOC PlcfHdd header/footer story table contains descending CPs');
            }
            $previousCp = $cp;
        }

        $terminatingCp = $cps[$cpCount - 2];
        if ($terminatingCp !== $headerCharacterCount - 1) {
            return [
                'stories' => [],
                'declaredStoryCount' => 0,
                'ignoredFinalCp' => null,
            ];
        }

        $stories = [];
        for ($storyNumber = 0; $storyNumber < $storyCount; $storyNumber++) {
            $startCp = $cps[$storyNumber];
            $endCp = $cps[$storyNumber + 1];
            if ($endCp === $startCp) {
                continue;
            }

            $descriptor = $this->headerFooterStoryDescriptor($storyNumber);
            $story = [
                'index' => $storyNumber + 1,
                'storyNumber' => $storyNumber,
                'sourceTable' => 'PlcfHdd',
                'role' => $descriptor['role'],
                'kind' => $descriptor['kind'],
                'startCp' => $startCp,
                'endCp' => $endCp,
                'characterCount' => $endCp - $startCp,
                'text' => $this->charactersToString(array_slice($characters, $startCp, $endCp - $startCp)),
                'guardCp' => $endCp,
                'hasGuardParagraph' => isset($characters[$endCp]) && $characters[$endCp] === "\r",
            ];
            if (isset($descriptor['sectionIndex'])) {
                $story['sectionIndex'] = $descriptor['sectionIndex'];
                $story['sectionStoryNumber'] = $descriptor['sectionStoryNumber'];
            }
            $stories[] = $story;
        }

        return [
            'stories' => $stories,
            'declaredStoryCount' => $storyCount,
            'ignoredFinalCp' => $cps[$cpCount - 1],
        ];
    }

    /**
     * @return array{role:string,kind:string,sectionIndex?:int,sectionStoryNumber?:int}
     */
    private function headerFooterStoryDescriptor(int $storyNumber): array
    {
        $separatorRoleCount = count(self::HEADER_FOOTER_SEPARATOR_ROLES);
        if ($storyNumber < $separatorRoleCount) {
            return [
                'role' => self::HEADER_FOOTER_SEPARATOR_ROLES[$storyNumber],
                'kind' => 'separator',
            ];
        }

        $sectionStoryOrdinal = $storyNumber - $separatorRoleCount;
        $sectionStoryNumber = $sectionStoryOrdinal % count(self::HEADER_FOOTER_SECTION_ROLES);
        $role = self::HEADER_FOOTER_SECTION_ROLES[$sectionStoryNumber];

        return [
            'role' => $role,
            'kind' => str_contains($role, 'header') ? 'header' : 'footer',
            'sectionIndex' => intdiv($sectionStoryOrdinal, count(self::HEADER_FOOTER_SECTION_ROLES)) + 1,
            'sectionStoryNumber' => $sectionStoryNumber,
        ];
    }

    /**
     * @param array<string,string> $subdocumentTexts
     */
    private function subdocumentRangeText(array $subdocumentTexts, string $type, int $startCp, int $endCp): ?string
    {
        if (!isset($subdocumentTexts[$type])) {
            return null;
        }
        if ($startCp < 0 || $endCp < $startCp) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' body range is invalid');
        }

        $characters = $this->unicodeCharacters($subdocumentTexts[$type]);
        if ($endCp > count($characters)) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' body range points outside supplemental subdocument text');
        }

        return $this->charactersToString(array_slice($characters, $startCp, $endCp - $startCp));
    }

    /**
     * @param list<array<string,mixed>> $bookmarks
     * @param list<array<string,mixed>> $noteReferences
     * @param list<array<string,mixed>> $objectReferences
     * @param list<array<string,mixed>> $pictureReferences
     * @return list<AstNode>
     */
    private function paragraphNodes(
        string $text,
        array $bookmarks = [],
        array $noteReferences = [],
        array $objectReferences = [],
        array $pictureReferences = []
    ): array
    {
        $normalized = str_replace(["\r\n", "\n"], "\r", $text);
        $paragraphs = explode("\r", $normalized);
        $nodes = [];
        $paragraphStartCp = 0;
        foreach ($paragraphs as $paragraph) {
            $paragraphLength = $this->textCharacterLength($paragraph);
            $paragraph = str_replace("\0", '', $paragraph);
            if (trim($paragraph) === '') {
                $paragraphStartCp += $paragraphLength + 1;
                continue;
            }
            $paragraphChildren = $this->inlineNodesWithBookmarks(
                $paragraph,
                $paragraphStartCp,
                $bookmarks,
                $noteReferences,
                $objectReferences,
                $pictureReferences
            );
            if ($paragraphChildren !== []) {
                $nodes[] = new AstNode('paragraph', [], $paragraphChildren);
            }
            $paragraphStartCp += $paragraphLength + 1;
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodes(string $text, int $segmentStartCp = 0): array
    {
        if (strpbrk($text, "\x13\x14\x15") !== false) {
            return $this->fieldAwareInlineNodes($text, $segmentStartCp);
        }

        return $this->plainInlineNodes($text, $segmentStartCp);
    }

    /**
     * @return list<AstNode>
     */
    private function fieldAwareInlineNodes(string $text, int $segmentStartCp = 0): array
    {
        $parts = preg_split('/([\x13\x14\x15])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $this->plainInlineNodes($text, $segmentStartCp);
        }

        $nodes = [];
        $fieldStack = [];
        $localCp = 0;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if ($part === "\x13") {
                if ($fieldStack !== [] && $fieldStack[count($fieldStack) - 1]['collectingResult'] !== true) {
                    throw new \RuntimeException('Legacy DOC nested field codes inside field instructions are not supported by the native reader');
                }
                $fieldStack[] = [
                    'instruction' => '',
                    'resultText' => '',
                    'resultNodes' => [],
                    'collectingResult' => false,
                    'beginCp' => $segmentStartCp + $localCp,
                ];
                $localCp++;
                continue;
            }

            if ($part === "\x14") {
                if ($fieldStack === []) {
                    $localCp++;
                    continue;
                }
                $fieldIndex = count($fieldStack) - 1;
                if ($fieldStack[$fieldIndex]['collectingResult'] === true) {
                    $localCp++;
                    continue;
                }
                $fieldStack[$fieldIndex]['collectingResult'] = true;
                $fieldStack[$fieldIndex]['separatorCp'] = $segmentStartCp + $localCp;
                $localCp++;
                continue;
            }

            if ($part === "\x15") {
                if ($fieldStack === []) {
                    $localCp++;
                    continue;
                }
                $field = array_pop($fieldStack);
                $field['endCp'] = $segmentStartCp + $localCp;
                $fieldNodes = $this->fieldResultNodes($field);
                $localCp++;
                if ($fieldStack === []) {
                    array_push($nodes, ...$fieldNodes);
                    continue;
                }

                $fieldIndex = count($fieldStack) - 1;
                if ($fieldStack[$fieldIndex]['collectingResult'] !== true) {
                    $fieldStack[$fieldIndex]['instruction'] .= $field['instruction'];
                    continue;
                }

                array_push($fieldStack[$fieldIndex]['resultNodes'], ...$fieldNodes);
                $fieldStack[$fieldIndex]['resultText'] .= $field['resultText'];
                continue;
            }

            if ($fieldStack === []) {
                array_push($nodes, ...$this->plainInlineNodes($part, $segmentStartCp + $localCp));
                $localCp += $this->textCharacterLength($part);
                continue;
            }

            $fieldIndex = count($fieldStack) - 1;
            if ($fieldStack[$fieldIndex]['collectingResult'] === true) {
                array_push($fieldStack[$fieldIndex]['resultNodes'], ...$this->plainInlineNodes($part, $segmentStartCp + $localCp));
                $fieldStack[$fieldIndex]['resultText'] .= $part;
            } else {
                $fieldStack[$fieldIndex]['instruction'] .= $part;
            }
            $localCp += $this->textCharacterLength($part);
        }

        if ($fieldStack !== []) {
            $nodes = array_merge($nodes, $this->visibleNodesFromUnterminatedFieldStack($fieldStack));
        }

        return $nodes;
    }

    /**
     * @param array{instruction:string,resultText:string,resultNodes:list<AstNode>,collectingResult:bool,beginCp?:int,separatorCp?:int,endCp?:int} $field
     * @return list<AstNode>
     */
    private function visibleNodesFromUnterminatedFieldStack(array $fieldStack): array
    {
        $nodes = [];
        while ($fieldStack !== []) {
            $field = array_pop($fieldStack);
            $fieldNodes = ($field['collectingResult'] ?? false) === true ? ($field['resultNodes'] ?? []) : [];
            if ($fieldStack === []) {
                array_push($nodes, ...$fieldNodes);
                continue;
            }

            $parentIndex = count($fieldStack) - 1;
            if (($fieldStack[$parentIndex]['collectingResult'] ?? false) === true) {
                array_push($fieldStack[$parentIndex]['resultNodes'], ...$fieldNodes);
                $fieldStack[$parentIndex]['resultText'] .= (string) ($field['resultText'] ?? '');
            }
        }

        return $nodes;
    }

    /**
     * @param array{instruction:string,resultText:string,resultNodes:list<AstNode>,collectingResult:bool,beginCp?:int,separatorCp?:int,endCp?:int} $field
     * @return list<AstNode>
     */
    private function fieldResultNodes(array $field): array
    {
        $resultNodes = $field['resultNodes'];
        $resultText = $field['resultText'];
        $beginCp = isset($field['beginCp']) ? (int) $field['beginCp'] : null;
        if ($resultNodes === []) {
            $attrs = $this->fieldSpanAttrs($field['instruction'], $resultText, $beginCp);
            if ($attrs !== null && ($attrs['attributes']['data-legacy-doc-field'] ?? '') === 'set') {
                return [new AstNode('span', $attrs, [])];
            }

            return [];
        }

        $attrs = $this->hyperlinkFieldAttrs($field['instruction']);
        if ($attrs !== null) {
            return [new AstNode('link', $attrs, $resultNodes)];
        }

        $attrs = $this->fieldSpanAttrs($field['instruction'], $resultText, $beginCp);
        if ($attrs !== null) {
            return [new AstNode('span', $attrs, $resultNodes)];
        }

        return $resultNodes;
    }

    /**
     * @return array{url:string,title?:string}|null
     */
    private function hyperlinkFieldAttrs(string $instruction): ?array
    {
        $tokens = $this->fieldInstructionTokens($instruction);
        if ($tokens === [] || strtoupper(array_shift($tokens)) !== 'HYPERLINK') {
            return null;
        }

        $url = null;
        $anchor = null;
        $title = null;
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if (($switch === 'l' || $switch === 'o') && isset($tokens[$index + 1])) {
                    $index++;
                    if ($switch === 'l') {
                        $anchor = $tokens[$index];
                    } else {
                        $title = $tokens[$index];
                    }
                }
                continue;
            }

            $url ??= $token;
        }

        if ($url === null || $url === '') {
            $url = $anchor === null || $anchor === '' ? '' : '#' . $anchor;
        } elseif ($anchor !== null && $anchor !== '') {
            $url .= '#' . $anchor;
        }
        if ($url === '') {
            return null;
        }

        $attrs = ['url' => $url];
        if ($title !== null && $title !== '') {
            $attrs['title'] = $title;
        }

        return $attrs;
    }

    /**
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function fieldSpanAttrs(string $instruction, string $result = '', ?int $beginCp = null): ?array
    {
        $tokens = $this->fieldInstructionTokens($instruction);
        if ($tokens === []) {
            return null;
        }

        $fieldName = strtoupper(array_shift($tokens));
        $formFields = [
            'FORMTEXT' => 'text',
            'FORMCHECKBOX' => 'checkbox',
            'FORMDROPDOWN' => 'dropdown',
        ];
        if (isset($formFields[$fieldName])) {
            $fieldKey = strtolower($fieldName);
            $attributes = [
                'data-legacy-doc-field' => $fieldKey,
                'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
                'data-legacy-doc-form-field-type' => $formFields[$fieldName],
            ];

            $format = $this->fieldFormatSwitchValue($tokens);
            if ($format !== null && $format !== '') {
                $attributes['data-legacy-doc-field-format'] = $format;
            }
            if ($fieldName === 'FORMCHECKBOX') {
                $attributes['data-legacy-doc-form-field-checked'] = $this->formCheckboxResultIsChecked($result) ? 'true' : 'false';
            }
            if ($beginCp !== null && isset($this->activeFormFieldDataReferences[$beginCp])) {
                $attributes += $this->formFieldDataReferenceAttributes($this->activeFormFieldDataReferences[$beginCp]);
            }

            return [
                'classes' => ['legacy-doc-field', 'legacy-doc-form-field', 'legacy-doc-field-' . $fieldKey],
                'attributes' => $attributes,
            ];
        }

        if ($fieldName === 'SYMBOL') {
            return $this->symbolFieldAttrs($tokens, $instruction);
        }

        $crossReferenceAttrs = $this->crossReferenceFieldAttrs($fieldName, $tokens, $instruction);
        if ($crossReferenceAttrs !== null) {
            return $crossReferenceAttrs;
        }

        $setFieldAttrs = $this->setFieldAttrs($fieldName, $tokens, $instruction);
        if ($setFieldAttrs !== null) {
            return $setFieldAttrs;
        }

        $mailMergeDataRedirectAttrs = $this->mailMergeDataRedirectFieldAttrs($fieldName, $tokens, $instruction);
        if ($mailMergeDataRedirectAttrs !== null) {
            return $mailMergeDataRedirectAttrs;
        }

        $dataFieldAttrs = $this->dataFieldAttrs($fieldName, $tokens, $instruction);
        if ($dataFieldAttrs !== null) {
            return $dataFieldAttrs;
        }

        $promptFieldAttrs = $this->promptFieldAttrs($fieldName, $tokens, $instruction);
        if ($promptFieldAttrs !== null) {
            return $promptFieldAttrs;
        }

        $includeFieldAttrs = $this->includeFieldAttrs($fieldName, $tokens, $instruction);
        if ($includeFieldAttrs !== null) {
            return $includeFieldAttrs;
        }

        $actionFieldAttrs = $this->actionFieldAttrs($fieldName, $tokens, $instruction);
        if ($actionFieldAttrs !== null) {
            return $actionFieldAttrs;
        }

        $literalResultFieldAttrs = $this->literalResultFieldAttrs($fieldName, $tokens, $instruction, $result);
        if ($literalResultFieldAttrs !== null) {
            return $literalResultFieldAttrs;
        }

        $equationFieldAttrs = $this->equationFieldAttrs($fieldName, $tokens, $instruction, $result);
        if ($equationFieldAttrs !== null) {
            return $equationFieldAttrs;
        }

        $generatedFieldAttrs = $this->generatedFieldAttrs($fieldName, $tokens, $instruction);
        if ($generatedFieldAttrs !== null) {
            return $generatedFieldAttrs;
        }

        $numberingFieldAttrs = $this->numberingFieldAttrs($fieldName, $tokens, $instruction);
        if ($numberingFieldAttrs !== null) {
            return $numberingFieldAttrs;
        }

        $sourceLocationFieldAttrs = $this->sourceLocationFieldAttrs($fieldName, $tokens, $instruction, $result);
        if ($sourceLocationFieldAttrs !== null) {
            return $sourceLocationFieldAttrs;
        }

        $fieldNames = [
            'PAGE' => 'page',
            'NUMPAGES' => 'numpages',
            'SECTION' => 'section',
            'SECTIONPAGES' => 'sectionpages',
            'DATE' => 'date',
            'TIME' => 'time',
            'CREATEDATE' => 'createdate',
            'SAVEDATE' => 'savedate',
            'PRINTDATE' => 'printdate',
        ];

        if (!isset($fieldNames[$fieldName])) {
            return null;
        }

        $fieldKey = $fieldNames[$fieldName];
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function symbolFieldAttrs(array $tokens, string $instruction): ?array
    {
        $symbolCode = null;
        $font = null;
        $size = null;
        $switches = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if ($switch === '') {
                    continue;
                }
                if (($switch === 'f' || $switch === 's') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                    $index++;
                    if ($switch === 'f') {
                        $font = $tokens[$index];
                    } else {
                        $size = $tokens[$index];
                    }
                    continue;
                }

                $switches[] = $switch;
                continue;
            }

            $symbolCode ??= $token;
        }
        if ($symbolCode === null || $symbolCode === '') {
            return null;
        }

        $attributes = [
            'data-legacy-doc-field' => 'symbol',
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-symbol-code' => $symbolCode,
        ];
        if ($font !== null && $font !== '') {
            $attributes['data-legacy-doc-symbol-font'] = $font;
        }
        if ($size !== null && $size !== '') {
            $attributes['data-legacy-doc-symbol-size'] = $size;
        }
        if ($switches !== []) {
            $attributes['data-legacy-doc-symbol-switches'] = implode(' ', array_values(array_unique($switches)));
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-symbol-field', 'legacy-doc-field-symbol'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function crossReferenceFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        $fieldTypes = [
            'REF' => 'bookmark',
            'PAGEREF' => 'bookmark-page',
            'NOTEREF' => 'note',
        ];
        if (!isset($fieldTypes[$fieldName])) {
            return null;
        }

        $target = null;
        $switches = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if ($switch !== '' && $switch !== '*' && $switch !== '@') {
                    $switches[] = $switch;
                }
                continue;
            }

            $target ??= $token;
        }
        if ($target === null || $target === '') {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-cross-reference-type' => $fieldTypes[$fieldName],
            'data-legacy-doc-cross-reference-target' => $target,
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }
        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-cross-reference-switches'] = implode(' ', $switches);
            if (in_array('h', $switches, true)) {
                $attributes['data-legacy-doc-cross-reference-hyperlink'] = 'true';
            }
            if (in_array('p', $switches, true)) {
                $attributes['data-legacy-doc-cross-reference-relative'] = 'true';
            }
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-cross-reference', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function setFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        if ($fieldName !== 'SET') {
            return null;
        }

        $arguments = [];
        $switches = [];
        $switchValues = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (!str_starts_with($token, '\\')) {
                $arguments[] = $token;
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === '') {
                continue;
            }
            if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                continue;
            }

            $switches[] = $switch;
            if (isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                $switchValues[$switch][] = $tokens[$index];
            } else {
                $switchValues[$switch] ??= [];
            }
        }

        $name = array_shift($arguments);
        if ($name === null || $name === '') {
            return null;
        }

        $value = implode(' ', $arguments);
        $isSignatureAssignment = $value !== '' && $this->isSignatureDocumentVariableName($name);
        $attributes = [
            'data-legacy-doc-field' => 'set',
            'data-legacy-doc-field-instruction' => $isSignatureAssignment
                ? 'SET ' . $name . ' [redacted]'
                : $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-set-field-type' => 'document-variable-assignment',
            'data-legacy-doc-set-field-name' => $name,
        ];
        if ($isSignatureAssignment) {
            $attributes['data-legacy-doc-field-instruction-redacted'] = 'true';
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        if ($value !== '') {
            $attributes['data-legacy-doc-set-field-value-character-count'] = (string) count($this->unicodeCharacters($value));
            if ($isSignatureAssignment) {
                $attributes['data-legacy-doc-set-field-redacted'] = 'true';
                $attributes['data-legacy-doc-set-field-policy'] = 'signature-blob-metadata-only';
            } else {
                $attributes['data-legacy-doc-set-field-value'] = $value;
            }
        }

        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-set-field-switches'] = implode(' ', $switches);
            foreach ($switches as $switch) {
                $attributeSwitch = preg_replace('/[^a-z0-9-]/', '', $switch);
                if (!is_string($attributeSwitch) || $attributeSwitch === '') {
                    continue;
                }

                $values = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $switchValues[$switch] ?? []
                )));
                $attributes['data-legacy-doc-set-field-switch-' . $attributeSwitch] = $values === []
                    ? 'true'
                    : implode('; ', $values);
            }
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-set-field', 'legacy-doc-field-set'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function mailMergeDataRedirectFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        if ($fieldName !== 'DATA') {
            return null;
        }

        $arguments = [];
        $switches = [];
        $switchValues = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (!str_starts_with($token, '\\')) {
                $arguments[] = $token;
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === '') {
                continue;
            }
            if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                continue;
            }

            $switches[] = $switch;
            if (count($arguments) >= 2 && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                $switchValues[$switch][] = $tokens[$index];
            } else {
                $switchValues[$switch] ??= [];
            }
        }

        $dataSource = $arguments[0] ?? null;
        if ($dataSource === null || $dataSource === '') {
            return null;
        }
        $headerDocument = $arguments[1] ?? null;

        $attributes = [
            'data-legacy-doc-field' => 'data',
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-mail-merge-field-type' => 'data-source-redirect',
            'data-legacy-doc-mail-merge-policy' => 'metadata-only-native-review',
            'data-legacy-doc-mail-merge-data-source' => $dataSource,
            'data-legacy-doc-mail-merge-data-source-kind' => preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $dataSource) === 1 ? 'external-url' : 'file-path',
            'data-legacy-doc-mail-merge-data-source-basename' => $this->legacyPathBasename($dataSource),
            'data-legacy-doc-mail-merge-can-expose-bytes' => 'false',
        ];

        if ($headerDocument !== null && $headerDocument !== '') {
            $attributes['data-legacy-doc-mail-merge-header-document'] = $headerDocument;
            $attributes['data-legacy-doc-mail-merge-header-document-kind'] = preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $headerDocument) === 1 ? 'external-url' : 'file-path';
            $attributes['data-legacy-doc-mail-merge-header-document-basename'] = $this->legacyPathBasename($headerDocument);
        }
        if (count($arguments) > 2) {
            $attributes['data-legacy-doc-mail-merge-extra-arguments'] = implode(' ', array_slice($arguments, 2));
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        $associatedDataSource = $this->matchingAssociatedString($dataSource, 'mailMergeDataSource');
        if ($associatedDataSource !== null) {
            $record = $associatedDataSource['record'];
            $attributes['data-legacy-doc-mail-merge-associated-data-source-table'] = 'SttbfAssoc';
            $attributes['data-legacy-doc-mail-merge-associated-data-source-index'] = (string) ((int) ($record['index'] ?? 8));
            $attributes['data-legacy-doc-mail-merge-associated-data-source-match'] = $associatedDataSource['matchedOn'];
        }

        if ($headerDocument !== null && $headerDocument !== '') {
            $associatedHeaderDocument = $this->matchingAssociatedString($headerDocument, 'mailMergeHeaderDocument');
            if ($associatedHeaderDocument !== null) {
                $record = $associatedHeaderDocument['record'];
                $attributes['data-legacy-doc-mail-merge-header-document-table'] = 'SttbfAssoc';
                $attributes['data-legacy-doc-mail-merge-header-document-index'] = (string) ((int) ($record['index'] ?? 9));
                $attributes['data-legacy-doc-mail-merge-header-document-match'] = $associatedHeaderDocument['matchedOn'];
            }
        }

        $externalReference = $this->matchingExternalFileReference($dataSource, 'mail-merge-data-source');
        if ($externalReference !== null) {
            $reference = $externalReference['reference'];
            $attributes['data-legacy-doc-mail-merge-external-reference-table'] = (string) ($reference['sourceTable'] ?? 'SttbFnm');
            $attributes['data-legacy-doc-mail-merge-external-reference-index'] = (string) ((int) ($reference['index'] ?? 0));
            $attributes['data-legacy-doc-mail-merge-external-reference-match'] = $externalReference['matchedOn'];
            $attributes['data-legacy-doc-mail-merge-external-reference-type'] = 'mail-merge-data-source';
            $attributes['data-legacy-doc-mail-merge-external-reference-document-index'] = (string) ((int) ($reference['documentIndex'] ?? 0));
            $attributes['data-legacy-doc-mail-merge-external-reference-file-system'] = (string) ($reference['fileSystem'] ?? 'unknown');
            $attributes['data-legacy-doc-mail-merge-external-reference-can-expose-bytes'] = ($reference['canExposeBytes'] ?? false) === true ? 'true' : 'false';
        }

        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-mail-merge-switches'] = implode(' ', $switches);
            foreach ($switches as $switch) {
                $attributeSwitch = preg_replace('/[^a-z0-9-]/', '', $switch);
                if (!is_string($attributeSwitch) || $attributeSwitch === '') {
                    continue;
                }

                $values = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $switchValues[$switch] ?? []
                )));
                $attributes['data-legacy-doc-mail-merge-switch-' . $attributeSwitch] = $values === []
                    ? 'true'
                    : implode('; ', $values);
            }
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-mail-merge-data-field', 'legacy-doc-field-data'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{record:array<string,mixed>,matchedOn:string}|null
     */
    private function matchingAssociatedString(string $source, string $role): ?array
    {
        if ($this->activeAssociatedStrings === []) {
            return null;
        }

        $sourceKey = $this->externalFileReferenceMatchKey($source);
        if ($sourceKey === '') {
            return null;
        }

        foreach ($this->activeAssociatedStrings as $record) {
            if (($record['role'] ?? null) !== $role) {
                continue;
            }

            $candidate = $record['value'] ?? null;
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }
            if ($this->externalFileReferenceMatchKey($candidate) === $sourceKey) {
                return [
                    'record' => $record,
                    'matchedOn' => 'value',
                ];
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function dataFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        $fieldTypes = [
            'MERGEFIELD' => 'mail-merge',
            'DOCVARIABLE' => 'document-variable',
            'DOCPROPERTY' => 'document-property',
            'INFO' => 'document-info',
        ];
        $builtInFieldNames = [
            'AUTHOR' => 'Author',
            'TITLE' => 'Title',
            'SUBJECT' => 'Subject',
            'KEYWORDS' => 'Keywords',
            'COMMENTS' => 'Comments',
            'LASTSAVEDBY' => 'Last Saved By',
            'REVNUM' => 'Revision Number',
            'NUMWORDS' => 'Word Count',
            'NUMCHARS' => 'Character Count',
            'EDITTIME' => 'Edit Time',
        ];
        $builtInFieldTypes = [
            'AUTHOR' => 'document-info',
            'TITLE' => 'document-info',
            'SUBJECT' => 'document-info',
            'KEYWORDS' => 'document-info',
            'COMMENTS' => 'document-info',
            'LASTSAVEDBY' => 'document-info',
            'REVNUM' => 'document-statistic',
            'NUMWORDS' => 'document-statistic',
            'NUMCHARS' => 'document-statistic',
            'EDITTIME' => 'document-statistic',
        ];
        $builtInResultKinds = [
            'AUTHOR' => 'text',
            'TITLE' => 'text',
            'SUBJECT' => 'text',
            'KEYWORDS' => 'text',
            'COMMENTS' => 'text',
            'LASTSAVEDBY' => 'text',
            'REVNUM' => 'revision-number',
            'NUMWORDS' => 'word-count',
            'NUMCHARS' => 'character-count',
            'EDITTIME' => 'editing-minutes',
        ];
        $isBuiltInField = isset($builtInFieldNames[$fieldName]);
        if (!isset($fieldTypes[$fieldName]) && !$isBuiltInField) {
            return null;
        }

        $dataFieldName = null;
        $prefix = null;
        $suffix = null;
        $switches = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if ($switch === '') {
                    continue;
                }
                if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                    $index++;
                    continue;
                }
                if (($switch === 'b' || $switch === 'f') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                    $index++;
                    if ($switch === 'b') {
                        $prefix = $tokens[$index];
                    } else {
                        $suffix = $tokens[$index];
                    }
                    $switches[] = $switch;
                    continue;
                }

                $switches[] = $switch;
                continue;
            }

            $dataFieldName ??= $token;
        }
        if (($dataFieldName === null || $dataFieldName === '') && $isBuiltInField) {
            $dataFieldName = $builtInFieldNames[$fieldName];
        }
        if ($dataFieldName === null || $dataFieldName === '') {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-data-field-type' => $isBuiltInField ? $builtInFieldTypes[$fieldName] : $fieldTypes[$fieldName],
            'data-legacy-doc-data-field-name' => $dataFieldName,
        ];
        if ($isBuiltInField) {
            $attributes['data-legacy-doc-data-field-built-in'] = 'true';
            $attributes['data-legacy-doc-data-field-policy'] = 'cached-result-native-review';
            $attributes['data-legacy-doc-data-field-result-kind'] = $builtInResultKinds[$fieldName];
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }
        if ($prefix !== null && $prefix !== '') {
            $attributes['data-legacy-doc-data-field-prefix'] = $prefix;
        }
        if ($suffix !== null && $suffix !== '') {
            $attributes['data-legacy-doc-data-field-suffix'] = $suffix;
        }
        if ($switches !== []) {
            $attributes['data-legacy-doc-data-field-switches'] = implode(' ', array_values(array_unique($switches)));
        }
        $attributes += $this->mailMergeFieldReferenceAttrs($fieldName);

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-data-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function mailMergeFieldReferenceAttrs(string $fieldName): array
    {
        if ($fieldName !== 'MERGEFIELD') {
            return [];
        }

        $attributes = [];
        foreach ($this->activeAssociatedStrings as $record) {
            $role = (string) ($record['role'] ?? '');
            if ($role === 'mailMergeDataSource') {
                $attributes['data-legacy-doc-mail-merge-policy'] = 'metadata-only-native-review';
                $attributes['data-legacy-doc-mail-merge-has-associated-data-source'] = 'true';
                $attributes['data-legacy-doc-mail-merge-associated-data-source-table'] = 'SttbfAssoc';
                $attributes['data-legacy-doc-mail-merge-associated-data-source-index'] = (string) ((int) ($record['index'] ?? 8));
                continue;
            }
            if ($role === 'mailMergeHeaderDocument') {
                $attributes['data-legacy-doc-mail-merge-policy'] = 'metadata-only-native-review';
                $attributes['data-legacy-doc-mail-merge-has-header-document'] = 'true';
                $attributes['data-legacy-doc-mail-merge-header-document-table'] = 'SttbfAssoc';
                $attributes['data-legacy-doc-mail-merge-header-document-index'] = (string) ((int) ($record['index'] ?? 9));
            }
        }

        foreach ($this->activeExternalFileReferences as $reference) {
            if (($reference['referenceType'] ?? null) !== 'mail-merge-data-source') {
                continue;
            }

            $attributes['data-legacy-doc-mail-merge-policy'] = 'metadata-only-native-review';
            $attributes['data-legacy-doc-mail-merge-external-reference-table'] = (string) ($reference['sourceTable'] ?? 'SttbFnm');
            $attributes['data-legacy-doc-mail-merge-external-reference-index'] = (string) ((int) ($reference['index'] ?? 0));
            $attributes['data-legacy-doc-mail-merge-external-reference-type'] = 'mail-merge-data-source';
            $attributes['data-legacy-doc-mail-merge-external-reference-document-index'] = (string) ((int) ($reference['documentIndex'] ?? 0));
            $attributes['data-legacy-doc-mail-merge-external-reference-file-system'] = (string) ($reference['fileSystem'] ?? '');
            $attributes['data-legacy-doc-mail-merge-external-reference-can-expose-bytes'] = ($reference['canExposeBytes'] ?? false) === true ? 'true' : 'false';
            break;
        }

        return $attributes;
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function promptFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        $fieldTypes = [
            'ASK' => 'bookmark-prompt',
            'FILLIN' => 'prompt',
        ];
        if (!isset($fieldTypes[$fieldName])) {
            return null;
        }

        $name = null;
        $prompt = null;
        $default = null;
        $switches = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if ($switch === '') {
                    continue;
                }
                if ($switch === 'd' && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                    $index++;
                    $default = $tokens[$index];
                    $switches[] = $switch;
                    continue;
                }
                if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                    $index++;
                    continue;
                }

                $switches[] = $switch;
                continue;
            }

            if ($fieldName === 'ASK') {
                if ($name === null) {
                    $name = $token;
                    continue;
                }
                $prompt ??= $token;
                continue;
            }

            $prompt ??= $token;
        }

        if ($prompt === null || $prompt === '' || ($fieldName === 'ASK' && ($name === null || $name === ''))) {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-prompt-field-type' => $fieldTypes[$fieldName],
        ];
        if ($name !== null && $name !== '') {
            $attributes['data-legacy-doc-prompt-field-name'] = $name;
        }
        $attributes['data-legacy-doc-prompt-text'] = $prompt;

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }
        if ($default !== null && $default !== '') {
            $attributes['data-legacy-doc-prompt-default'] = $default;
        }
        if ($switches !== []) {
            $attributes['data-legacy-doc-prompt-switches'] = implode(' ', array_values(array_unique($switches)));
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-prompt-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function includeFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        $fieldTypes = [
            'IMPORT' => 'picture',
            'INCLUDE' => 'text',
            'INCLUDEPICTURE' => 'picture',
            'INCLUDETEXT' => 'text',
        ];
        if (!isset($fieldTypes[$fieldName])) {
            return null;
        }

        $source = null;
        $switches = [];
        $switchValues = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (!str_starts_with($token, '\\')) {
                $source ??= $token;
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === '') {
                continue;
            }
            if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                continue;
            }

            $switches[] = $switch;
            if (isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                $switchValues[$switch][] = $tokens[$index];
            } else {
                $switchValues[$switch] ??= [];
            }
        }
        if ($source === null || $source === '') {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-include-field-type' => $fieldTypes[$fieldName],
            'data-legacy-doc-include-source' => $source,
            'data-legacy-doc-include-source-kind' => preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $source) === 1 ? 'external-url' : 'file-path',
            'data-legacy-doc-include-source-basename' => $this->legacyPathBasename($source),
        ];
        $externalReference = $this->matchingExternalFileReference($source);
        if ($externalReference !== null) {
            $reference = $externalReference['reference'];
            $attributes['data-legacy-doc-include-external-reference-index'] = (string) ((int) ($reference['index'] ?? 0));
            $attributes['data-legacy-doc-include-external-reference-match'] = $externalReference['matchedOn'];
            $attributes['data-legacy-doc-include-external-reference-type'] = (string) ($reference['referenceType'] ?? 'unknown');
            $attributes['data-legacy-doc-include-external-reference-document-index'] = (string) ((int) ($reference['documentIndex'] ?? 0));
            $attributes['data-legacy-doc-include-external-reference-file-system'] = (string) ($reference['fileSystem'] ?? 'unknown');
            $attributes['data-legacy-doc-include-external-reference-policy'] = (string) ($reference['extractionPolicy'] ?? 'metadata-only-native-review');
            $attributes['data-legacy-doc-include-external-reference-can-expose-bytes'] = ($reference['canExposeBytes'] ?? false) === true ? 'true' : 'false';
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }
        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-include-field-switches'] = implode(' ', $switches);
            foreach ($switches as $switch) {
                if ($switch === '!') {
                    $attributes['data-legacy-doc-include-field-lock-result'] = 'true';
                    continue;
                }

                $attributeSwitch = preg_replace('/[^a-z0-9-]/', '', $switch);
                if (!is_string($attributeSwitch) || $attributeSwitch === '') {
                    continue;
                }

                $values = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $switchValues[$switch] ?? []
                )));
                $attributes['data-legacy-doc-include-field-switch-' . $attributeSwitch] = $values === []
                    ? 'true'
                    : implode('; ', $values);
            }
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-include-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function sourceLocationFieldAttrs(string $fieldName, array $tokens, string $instruction, string $result): ?array
    {
        $fieldTypes = [
            'FILENAME' => 'document-filename',
            'TEMPLATE' => 'template-filename',
            'FILESIZE' => 'file-size',
        ];
        if (!isset($fieldTypes[$fieldName])) {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-source-field-type' => $fieldTypes[$fieldName],
            'data-legacy-doc-source-field-policy' => 'metadata-only-native-review',
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        $switches = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '' || !str_starts_with($token, '\\')) {
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === '') {
                continue;
            }
            if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                continue;
            }

            $switches[] = $switch;
            if (isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
            }
        }

        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-source-field-switches'] = implode(' ', $switches);
            foreach ($switches as $switch) {
                $attributeSwitch = preg_replace('/[^a-z0-9-]/', '', $switch);
                if (!is_string($attributeSwitch) || $attributeSwitch === '') {
                    continue;
                }

                $attributes['data-legacy-doc-source-field-switch-' . $attributeSwitch] = 'true';
            }
        }

        $resultText = trim($result);
        if ($fieldName === 'FILESIZE') {
            $attributes['data-legacy-doc-source-field-result-kind'] = 'byte-size';
        } elseif ($resultText !== '') {
            $resultKind = $this->legacyPathKind($resultText);
            if ($resultKind === 'file-path' && !str_contains($resultText, '/') && !str_contains($resultText, '\\')) {
                $resultKind = 'filename';
            }
            $attributes['data-legacy-doc-source-field-result-kind'] = $resultKind;
            $basename = $this->legacyPathBasename($resultText);
            if ($basename !== '') {
                $attributes['data-legacy-doc-source-field-basename'] = $basename;
            }
            if ($resultKind === 'file-path' || in_array('p', $switches, true)) {
                $attributes['data-legacy-doc-source-field-full-path'] = 'true';
            }
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-source-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{reference:array<string,mixed>,matchedOn:string}|null
     */
    private function matchingExternalFileReference(string $source, ?string $referenceType = null): ?array
    {
        if ($this->activeExternalFileReferences === []) {
            return null;
        }

        $sourceKey = $this->externalFileReferenceMatchKey($source);
        if ($sourceKey === '') {
            return null;
        }

        foreach ($this->activeExternalFileReferences as $reference) {
            if ($referenceType !== null && ($reference['referenceType'] ?? null) !== $referenceType) {
                continue;
            }

            foreach ([
                'path' => 'path',
                'relativePath' => 'relative-path',
            ] as $field => $matchedOn) {
                $candidate = $reference[$field] ?? null;
                if (!is_string($candidate) || $candidate === '') {
                    continue;
                }
                if ($this->externalFileReferenceMatchKey($candidate) === $sourceKey) {
                    return [
                        'reference' => $reference,
                        'matchedOn' => $matchedOn,
                    ];
                }
            }
        }

        return null;
    }

    private function externalFileReferenceMatchKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value) === 1) {
            return $value;
        }

        $value = str_replace('\\', '/', $value);

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function actionFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        $fieldTypes = [
            'GOTOBUTTON' => 'navigation',
            'MACROBUTTON' => 'macro',
        ];
        if (!isset($fieldTypes[$fieldName])) {
            return null;
        }

        $target = null;
        $displayParts = [];
        $switches = [];
        $switchValues = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (!str_starts_with($token, '\\')) {
                if ($target === null) {
                    $target = $token;
                    continue;
                }

                $displayParts[] = $token;
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === '') {
                continue;
            }
            if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                continue;
            }

            $switches[] = $switch;
            if (isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                $switchValues[$switch][] = $tokens[$index];
            } else {
                $switchValues[$switch] ??= [];
            }
        }
        if ($target === null || $target === '') {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-action-field-type' => $fieldTypes[$fieldName],
        ];
        if ($fieldName === 'MACROBUTTON') {
            $attributes['data-legacy-doc-action-field-command'] = $target;
            $attributes['data-legacy-doc-action-field-command-kind'] = 'macro';
        } else {
            $attributes['data-legacy-doc-action-field-destination'] = $target;
            $attributes['data-legacy-doc-action-field-destination-kind'] = 'bookmark-or-goto-target';
        }
        $attributes['data-legacy-doc-action-field-policy'] = 'metadata-only-native-review';
        $attributes['data-legacy-doc-action-field-execution'] = 'disabled';

        $displayText = trim(implode(' ', $displayParts));
        if ($displayText !== '') {
            $attributes['data-legacy-doc-action-field-display-text'] = $displayText;
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }
        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-action-field-switches'] = implode(' ', $switches);
            foreach ($switches as $switch) {
                $attributeSwitch = preg_replace('/[^a-z0-9-]/', '', $switch);
                if (!is_string($attributeSwitch) || $attributeSwitch === '') {
                    continue;
                }

                $values = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $switchValues[$switch] ?? []
                )));
                $attributes['data-legacy-doc-action-field-switch-' . $attributeSwitch] = $values === []
                    ? 'true'
                    : implode('; ', $values);
            }
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-action-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function literalResultFieldAttrs(string $fieldName, array $tokens, string $instruction, string $result): ?array
    {
        $fieldTypes = [
            'QUOTE' => 'literal-text',
            'SHAPE' => 'shape-quote-alias',
        ];
        if (!isset($fieldTypes[$fieldName])) {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-literal-field-type' => $fieldTypes[$fieldName],
            'data-legacy-doc-literal-field-policy' => 'metadata-only-native-review',
        ];
        if ($fieldName === 'SHAPE') {
            $attributes['data-legacy-doc-literal-field-alias'] = 'quote';
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        $arguments = [];
        $switches = [];
        $switchValues = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (!str_starts_with($token, '\\')) {
                $arguments[] = $token;
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === '') {
                continue;
            }
            if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                continue;
            }

            $switches[] = $switch;
            if (isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                $switchValues[$switch][] = $tokens[$index];
            } else {
                $switchValues[$switch] ??= [];
            }
        }

        if ($arguments !== []) {
            $attributes['data-legacy-doc-literal-field-arguments'] = implode(' ', $arguments);
        }
        $resultText = trim($result);
        if ($resultText !== '') {
            $attributes['data-legacy-doc-literal-field-result-kind'] = 'displayed-result';
            $attributes['data-legacy-doc-literal-field-result-character-count'] = (string) count($this->unicodeCharacters($resultText));
        }
        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-literal-field-switches'] = implode(' ', $switches);
            foreach ($switches as $switch) {
                $attributeSwitch = preg_replace('/[^a-z0-9-]/', '', $switch);
                if (!is_string($attributeSwitch) || $attributeSwitch === '') {
                    continue;
                }

                $values = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $switchValues[$switch] ?? []
                )));
                $attributes['data-legacy-doc-literal-field-switch-' . $attributeSwitch] = $values === []
                    ? 'true'
                    : implode('; ', $values);
            }
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-literal-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function equationFieldAttrs(string $fieldName, array $tokens, string $instruction, string $result): ?array
    {
        if ($fieldName !== 'EQ') {
            return null;
        }

        $attributes = [
            'data-legacy-doc-field' => 'eq',
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-equation-field-type' => 'legacy-word-eq',
            'data-legacy-doc-equation-field-policy' => 'metadata-only-native-review',
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        $codeTokens = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                    $index++;
                    continue;
                }
            }

            $codeTokens[] = $token;
        }

        if ($codeTokens !== []) {
            $attributes['data-legacy-doc-equation-field-code'] = implode(' ', $codeTokens);
        }

        $resultText = trim($result);
        if ($resultText !== '') {
            $attributes['data-legacy-doc-equation-field-result-kind'] = 'displayed-result';
            $attributes['data-legacy-doc-equation-field-result-character-count'] = (string) count($this->unicodeCharacters($resultText));
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-equation-field', 'legacy-doc-field-eq'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function generatedFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        $fieldTypes = [
            'TOC' => 'table-of-contents',
            'INDEX' => 'index',
            'TOA' => 'table-of-authorities',
        ];
        if (!isset($fieldTypes[$fieldName])) {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-generated-field-type' => $fieldTypes[$fieldName],
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        $arguments = [];
        $switches = [];
        $switchValues = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (!str_starts_with($token, '\\')) {
                $arguments[] = $token;
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === '') {
                continue;
            }
            if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                continue;
            }

            $switches[] = $switch;
            if (isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                $switchValues[$switch][] = $tokens[$index];
            } else {
                $switchValues[$switch] ??= [];
            }
        }

        if ($arguments !== []) {
            $attributes['data-legacy-doc-generated-field-arguments'] = implode(' ', $arguments);
        }
        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-generated-field-switches'] = implode(' ', $switches);
            foreach ($switches as $switch) {
                $attributeSwitch = preg_replace('/[^a-z0-9-]/', '', $switch);
                if (!is_string($attributeSwitch) || $attributeSwitch === '') {
                    continue;
                }

                $values = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $switchValues[$switch] ?? []
                )));
                $attributes['data-legacy-doc-generated-field-switch-' . $attributeSwitch] = $values === []
                    ? 'true'
                    : implode('; ', $values);
            }
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-generated-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function numberingFieldAttrs(string $fieldName, array $tokens, string $instruction): ?array
    {
        $fieldTypes = [
            'SEQ' => 'sequence',
            'AUTONUM' => 'auto-number',
            'AUTONUMOUT' => 'auto-number-outline',
            'AUTONUMLGL' => 'auto-number-legal',
            'LISTNUM' => 'list-number',
        ];
        if (!isset($fieldTypes[$fieldName])) {
            return null;
        }

        $fieldKey = strtolower($fieldName);
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-legacy-doc-numbering-field-type' => $fieldTypes[$fieldName],
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        $arguments = [];
        $switches = [];
        $switchValues = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (!str_starts_with($token, '\\')) {
                $arguments[] = $token;
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === '') {
                continue;
            }
            if (($switch === '*' || $switch === '@' || $switch === '#') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                continue;
            }

            $switches[] = $switch;
            if (isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $index++;
                $switchValues[$switch][] = $tokens[$index];
            } else {
                $switchValues[$switch] ??= [];
            }
        }

        if ($arguments !== []) {
            $attributes['data-legacy-doc-numbering-field-name'] = $arguments[0];
            $attributes['data-legacy-doc-numbering-field-arguments'] = implode(' ', $arguments);
        }
        if ($switches !== []) {
            $switches = array_values(array_unique($switches));
            $attributes['data-legacy-doc-numbering-field-switches'] = implode(' ', $switches);
            foreach ($switches as $switch) {
                $attributeSwitch = preg_replace('/[^a-z0-9-]/', '', $switch);
                if (!is_string($attributeSwitch) || $attributeSwitch === '') {
                    continue;
                }

                $values = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $switchValues[$switch] ?? []
                )));
                $attributes['data-legacy-doc-numbering-field-switch-' . $attributeSwitch] = $values === []
                    ? 'true'
                    : implode('; ', $values);
            }
        }

        $attributes += $this->automaticNumberingListReferenceAttrs($fieldName);

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-numbering-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function automaticNumberingListReferenceAttrs(string $fieldName): array
    {
        if (!in_array($fieldName, ['AUTONUM', 'AUTONUMOUT', 'AUTONUMLGL'], true)) {
            return [];
        }

        $matches = [];
        foreach ($this->activeListOverrides as $override) {
            if (($override['autoNumberField'] ?? null) === $fieldName) {
                $matches[] = $override;
            }
        }
        if ($matches === []) {
            return [];
        }

        $override = $matches[0];
        $lsid = (int) ($override['lsid'] ?? 0);
        $format = $this->listFormatByLsid($lsid);
        $level = $this->listReferenceLevel($format, $override);

        $attributes = [
            'data-legacy-doc-numbering-field-list-policy' => 'metadata-only-native-review',
            'data-legacy-doc-numbering-field-list-match-count' => (string) count($matches),
            'data-legacy-doc-numbering-field-list-ilfo' => (string) ((int) ($override['ilfo'] ?? 0)),
            'data-legacy-doc-numbering-field-list-lsid' => (string) $lsid,
        ];
        if (isset($override['firstParagraphCp'])) {
            $attributes['data-legacy-doc-numbering-field-list-first-paragraph-cp'] = (string) ((int) $override['firstParagraphCp']);
        }
        if ($format !== null) {
            $attributes['data-legacy-doc-numbering-field-list-index'] = (string) ((int) ($format['index'] ?? 0));
            $attributes['data-legacy-doc-numbering-field-list-template-code'] = (string) ((int) ($format['templateCode'] ?? 0));
            $attributes['data-legacy-doc-numbering-field-list-simple'] = ($format['simple'] ?? false) === true ? 'true' : 'false';
        }
        if ($level !== null) {
            $attributes['data-legacy-doc-numbering-field-list-level'] = (string) ((int) ($level['level'] ?? 0));
            $attributes['data-legacy-doc-numbering-field-list-start-at'] = (string) ((int) ($level['startAt'] ?? 0));
            $attributes['data-legacy-doc-numbering-field-list-number-format'] = (string) ($level['numberFormat'] ?? '');
            $attributes['data-legacy-doc-numbering-field-list-text-template'] = (string) ($level['numberText'] ?? '');
            $attributes['data-legacy-doc-numbering-field-list-follow'] = (string) ($level['follow'] ?? '');
        }
        foreach (($override['levels'] ?? []) as $levelOverride) {
            if (!is_array($levelOverride) || ($levelOverride['startAtOverride'] ?? false) !== true || isset($levelOverride['startAt']) === false) {
                continue;
            }
            $attributes['data-legacy-doc-numbering-field-list-override-level'] = (string) ((int) ($levelOverride['level'] ?? 0));
            $attributes['data-legacy-doc-numbering-field-list-override-start-at'] = (string) ((int) $levelOverride['startAt']);
            break;
        }

        return $attributes;
    }

    private function listFormatByLsid(int $lsid): ?array
    {
        foreach ($this->activeListFormats as $format) {
            if ((int) ($format['lsid'] ?? 0) === $lsid) {
                return $format;
            }
        }

        return null;
    }

    private function listReferenceLevel(?array $format, array $override): ?array
    {
        if ($format === null) {
            return null;
        }

        $levelIndex = 0;
        foreach (($override['levels'] ?? []) as $levelOverride) {
            if (is_array($levelOverride) && isset($levelOverride['level'])) {
                $levelIndex = (int) $levelOverride['level'];
                break;
            }
        }

        foreach (($format['levels'] ?? []) as $level) {
            if (is_array($level) && (int) ($level['level'] ?? -1) === $levelIndex) {
                return $level;
            }
        }

        return null;
    }

    private function formCheckboxResultIsChecked(string $result): bool
    {
        $normalized = strtolower(trim($result));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on', 'checked', 'x'], true)
            || str_contains($result, "\u{2611}")
            || str_contains($result, "\u{2612}")
            || str_contains($result, "\u{2713}")
            || str_contains($result, "\u{2714}");
    }

    /**
     * @param list<string> $tokens
     */
    private function fieldFormatSwitchValue(array $tokens): ?string
    {
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '\\')) {
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if (($switch === '*' || $switch === '@') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                return $tokens[$index + 1];
            }
        }

        return null;
    }

    private function normalizeFieldInstruction(string $instruction): string
    {
        return preg_replace('/\s+/u', ' ', trim($instruction)) ?? trim($instruction);
    }

    /**
     * @return list<string>
     */
    private function fieldInstructionTokens(string $instruction): array
    {
        $tokens = [];
        $length = strlen($instruction);
        for ($index = 0; $index < $length;) {
            while ($index < $length && ctype_space($instruction[$index])) {
                $index++;
            }
            if ($index >= $length) {
                break;
            }

            if ($instruction[$index] === '"') {
                $index++;
                $token = '';
                while ($index < $length) {
                    $char = $instruction[$index];
                    if ($char === '"' && ($index === 0 || $instruction[$index - 1] !== '\\')) {
                        $index++;
                        break;
                    }
                    if ($char === '\\' && isset($instruction[$index + 1]) && $instruction[$index + 1] === '"') {
                        $token .= '"';
                        $index += 2;
                        continue;
                    }

                    $token .= $char;
                    $index++;
                }
                $tokens[] = $token;
                continue;
            }

            $start = $index;
            while ($index < $length && !ctype_space($instruction[$index])) {
                $index++;
            }
            $tokens[] = substr($instruction, $start, $index - $start);
        }

        return $tokens;
    }

    /**
     * @return list<AstNode>
     */
    private function plainInlineNodes(string $text, int $segmentStartCp = 0): array
    {
        $parts = preg_split('/(\x0b|\x0c)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            $parts = [$text];
        }

        $nodes = [];
        $localCp = 0;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $partLength = $this->textCharacterLength($part);
            if ($part === "\v" || $part === "\f") {
                $nodes[] = new AstNode('linebreak');
                $localCp += $partLength;
                continue;
            }

            $clean = preg_replace('/[\x00-\x08\x0e-\x1f]/u', '', $part);
            if ($clean !== null && $clean !== '') {
                array_push($nodes, ...$this->formattedPlainTextNodes($clean, $segmentStartCp + $localCp));
            }
            $localCp += $partLength;
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function formattedPlainTextNodes(string $text, int $segmentStartCp): array
    {
        if ($this->activeInlineTextFormattingApplications === [] && $this->activeHiddenTextSuppressions === []) {
            return [new AstNode('text', ['text' => $text])];
        }

        $nodes = [];
        foreach ($this->visiblePlainTextSlices($text, $segmentStartCp) as $slice) {
            array_push(
                $nodes,
                ...$this->formattedVisiblePlainTextNodes(
                    (string) $slice['text'],
                    (int) $slice['startCp']
                )
            );
        }

        return $nodes;
    }

    /**
     * @return list<array{text:string,startCp:int}>
     */
    private function visiblePlainTextSlices(string $text, int $segmentStartCp): array
    {
        if ($this->activeHiddenTextSuppressions === []) {
            return [['text' => $text, 'startCp' => $segmentStartCp]];
        }

        $characters = $this->unicodeCharacters($text);
        $segmentLength = count($characters);
        if ($segmentLength === 0) {
            return [];
        }

        $segmentEndCp = $segmentStartCp + $segmentLength;
        $hiddenRanges = [];
        foreach ($this->activeHiddenTextSuppressions as $suppression) {
            $startCp = max((int) ($suppression['cpStart'] ?? 0), $segmentStartCp);
            $endCp = min((int) ($suppression['cpEnd'] ?? 0), $segmentEndCp);
            if ($endCp <= $startCp) {
                continue;
            }

            $hiddenRanges[] = [
                'start' => $startCp - $segmentStartCp,
                'end' => $endCp - $segmentStartCp,
            ];
        }
        if ($hiddenRanges === []) {
            return [['text' => $text, 'startCp' => $segmentStartCp]];
        }

        usort(
            $hiddenRanges,
            static function (array $left, array $right): int {
                $start = ((int) $left['start']) <=> ((int) $right['start']);
                if ($start !== 0) {
                    return $start;
                }

                return ((int) $left['end']) <=> ((int) $right['end']);
            }
        );

        $slices = [];
        $cursor = 0;
        foreach ($hiddenRanges as $range) {
            $start = max($cursor, (int) $range['start']);
            $end = min($segmentLength, (int) $range['end']);
            if ($start > $cursor) {
                $slices[] = [
                    'text' => $this->charactersToString(array_slice($characters, $cursor, $start - $cursor)),
                    'startCp' => $segmentStartCp + $cursor,
                ];
            }
            if ($end > $cursor) {
                $cursor = $end;
            }
        }

        if ($cursor < $segmentLength) {
            $slices[] = [
                'text' => $this->charactersToString(array_slice($characters, $cursor)),
                'startCp' => $segmentStartCp + $cursor,
            ];
        }

        return $slices;
    }

    /**
     * @return list<AstNode>
     */
    private function formattedVisiblePlainTextNodes(string $text, int $segmentStartCp): array
    {
        if ($this->activeInlineTextFormattingApplications === []) {
            return [new AstNode('text', ['text' => $text])];
        }

        $characters = $this->unicodeCharacters($text);
        $segmentLength = count($characters);
        if ($segmentLength === 0) {
            return [];
        }

        $segmentEndCp = $segmentStartCp + $segmentLength;
        $candidates = [];
        foreach ($this->activeInlineTextFormattingApplications as $application) {
            $nodeTypes = $application['nodeTypes'] ?? [];
            if (!is_array($nodeTypes) || $nodeTypes === []) {
                continue;
            }

            $startCp = max((int) ($application['cpStart'] ?? 0), $segmentStartCp);
            $endCp = min((int) ($application['cpEnd'] ?? 0), $segmentEndCp);
            if ($endCp <= $startCp) {
                continue;
            }

            $candidates[] = [
                'start' => $startCp - $segmentStartCp,
                'end' => $endCp - $segmentStartCp,
                'nodeTypes' => array_values(array_filter(
                    array_map(static fn (mixed $nodeType): string => (string) $nodeType, $nodeTypes),
                    static fn (string $nodeType): bool => in_array($nodeType, ['strong', 'emph', 'underline', 'strikeout', 'small_caps'], true)
                )),
            ];
        }
        if ($candidates === []) {
            return [new AstNode('text', ['text' => $text])];
        }

        usort(
            $candidates,
            static function (array $left, array $right): int {
                $start = ((int) $left['start']) <=> ((int) $right['start']);
                if ($start !== 0) {
                    return $start;
                }

                return ((int) $left['end']) <=> ((int) $right['end']);
            }
        );

        $nodes = [];
        $cursor = 0;
        foreach ($candidates as $candidate) {
            $start = (int) $candidate['start'];
            $end = (int) $candidate['end'];
            $nodeTypes = $candidate['nodeTypes'];
            if (!is_array($nodeTypes) || $nodeTypes === [] || $start < $cursor || $end > $segmentLength) {
                continue;
            }
            if ($start > $cursor) {
                $nodes[] = new AstNode('text', [
                    'text' => $this->charactersToString(array_slice($characters, $cursor, $start - $cursor)),
                ]);
            }

            $slice = $this->charactersToString(array_slice($characters, $start, $end - $start));
            array_push($nodes, ...$this->wrapInlineFormattingNodes([new AstNode('text', ['text' => $slice])], $nodeTypes));
            $cursor = $end;
        }

        if ($cursor < $segmentLength) {
            $nodes[] = new AstNode('text', [
                'text' => $this->charactersToString(array_slice($characters, $cursor)),
            ]);
        }

        return $nodes === [] ? [new AstNode('text', ['text' => $text])] : $nodes;
    }

    /**
     * @param list<AstNode> $nodes
     * @param list<string> $nodeTypes
     * @return list<AstNode>
     */
    private function wrapInlineFormattingNodes(array $nodes, array $nodeTypes): array
    {
        foreach (array_reverse($nodeTypes) as $nodeType) {
            $nodes = [new AstNode($nodeType, [], $nodes)];
        }

        return $nodes;
    }

    /**
     * @param list<array<string,mixed>> $bookmarks
     * @param list<array<string,mixed>> $noteReferences
     * @param list<array<string,mixed>> $objectReferences
     * @param list<array<string,mixed>> $pictureReferences
     * @return list<AstNode>
     */
    private function inlineNodesWithBookmarks(
        string $text,
        int $paragraphStartCp,
        array $bookmarks,
        array $noteReferences = [],
        array $objectReferences = [],
        array $pictureReferences = []
    ): array {
        if ($bookmarks === []) {
            return $this->inlineNodesWithNoteReferences($text, $paragraphStartCp, $noteReferences, $objectReferences, $pictureReferences);
        }

        $chars = $this->unicodeCharacters($text);
        $paragraphLength = count($chars);
        $paragraphEndCp = $paragraphStartCp + $paragraphLength;
        $candidates = [];
        foreach ($bookmarks as $bookmark) {
            if (($bookmark['canAnchor'] ?? false) !== true) {
                continue;
            }

            $startCp = (int) ($bookmark['startCp'] ?? -1);
            $endCp = (int) ($bookmark['endCp'] ?? -1);
            if ($startCp < $paragraphStartCp || $endCp > $paragraphEndCp || $startCp > $endCp) {
                continue;
            }

            $candidates[] = $bookmark;
        }
        if ($candidates === []) {
            return $this->inlineNodesWithNoteReferences($text, $paragraphStartCp, $noteReferences, $objectReferences, $pictureReferences);
        }

        usort(
            $candidates,
            static function (array $left, array $right): int {
                $start = ((int) $left['startCp']) <=> ((int) $right['startCp']);
                if ($start !== 0) {
                    return $start;
                }

                return ((int) $right['endCp']) <=> ((int) $left['endCp']);
            }
        );

        $nodes = [];
        $cursor = 0;
        foreach ($candidates as $bookmark) {
            $start = (int) $bookmark['startCp'] - $paragraphStartCp;
            $end = (int) $bookmark['endCp'] - $paragraphStartCp;
            if ($start < $cursor || $start > $paragraphLength || $end > $paragraphLength) {
                continue;
            }
            if ($start > $cursor) {
                array_push(
                    $nodes,
                    ...$this->inlineNodesWithNoteReferences(
                        $this->charactersToString(array_slice($chars, $cursor, $start - $cursor)),
                        $paragraphStartCp + $cursor,
                        $noteReferences,
                        $objectReferences,
                        $pictureReferences
                    )
                );
            }

            $bookmarkText = $this->charactersToString(array_slice($chars, $start, $end - $start));
            $bookmarkNodes = $bookmarkText === ''
                ? []
                : $this->inlineNodesWithNoteReferences($bookmarkText, $paragraphStartCp + $start, $noteReferences, $objectReferences, $pictureReferences);
            $nodes[] = new AstNode('span', $this->bookmarkSpanAttrs($bookmark), $bookmarkNodes);
            $cursor = $end;
        }

        if ($cursor < $paragraphLength) {
            array_push(
                $nodes,
                ...$this->inlineNodesWithNoteReferences(
                    $this->charactersToString(array_slice($chars, $cursor)),
                    $paragraphStartCp + $cursor,
                    $noteReferences,
                    $objectReferences,
                    $pictureReferences
                )
            );
        }

        return $nodes === [] ? $this->inlineNodesWithNoteReferences($text, $paragraphStartCp, $noteReferences, $objectReferences, $pictureReferences) : $nodes;
    }

    /**
     * @param list<array<string,mixed>> $noteReferences
     * @param list<array<string,mixed>> $objectReferences
     * @param list<array<string,mixed>> $pictureReferences
     * @return list<AstNode>
     */
    private function inlineNodesWithNoteReferences(
        string $text,
        int $segmentStartCp,
        array $noteReferences,
        array $objectReferences = [],
        array $pictureReferences = []
    ): array
    {
        if ($noteReferences === [] && $objectReferences === [] && $pictureReferences === []) {
            return $this->inlineNodes($text, $segmentStartCp);
        }

        $chars = $this->unicodeCharacters($text);
        $segmentLength = count($chars);
        $segmentEndCp = $segmentStartCp + $segmentLength;
        $candidates = [];
        foreach ($noteReferences as $noteReference) {
            if (($noteReference['canAnchor'] ?? false) !== true) {
                continue;
            }

            $referenceCp = (int) ($noteReference['referenceCp'] ?? -1);
            if ($referenceCp < $segmentStartCp || $referenceCp >= $segmentEndCp) {
                continue;
            }

            $candidates[] = ['kind' => 'note', 'reference' => $noteReference, 'referenceCp' => $referenceCp];
        }
        foreach ($objectReferences as $objectReference) {
            if (($objectReference['canAnchor'] ?? false) !== true) {
                continue;
            }

            $referenceCp = (int) ($objectReference['referenceCp'] ?? -1);
            if ($referenceCp < $segmentStartCp || $referenceCp >= $segmentEndCp) {
                continue;
            }

            $candidates[] = ['kind' => 'object', 'reference' => $objectReference, 'referenceCp' => $referenceCp];
        }
        foreach ($pictureReferences as $pictureReference) {
            if (($pictureReference['canAnchor'] ?? false) !== true) {
                continue;
            }

            $referenceCp = (int) ($pictureReference['referenceCp'] ?? -1);
            if ($referenceCp < $segmentStartCp || $referenceCp >= $segmentEndCp) {
                continue;
            }

            $candidates[] = ['kind' => 'picture', 'reference' => $pictureReference, 'referenceCp' => $referenceCp];
        }
        if ($candidates === []) {
            return $this->inlineNodes($text, $segmentStartCp);
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => ((int) $left['referenceCp']) <=> ((int) $right['referenceCp'])
        );

        $nodes = [];
        $cursor = 0;
        foreach ($candidates as $candidate) {
            $localCp = (int) $candidate['referenceCp'] - $segmentStartCp;
            if ($localCp < $cursor || $localCp >= $segmentLength) {
                continue;
            }

            if ($localCp > $cursor) {
                array_push(
                    $nodes,
                    ...$this->inlineNodes(
                        $this->charactersToString(array_slice($chars, $cursor, $localCp - $cursor)),
                        $segmentStartCp + $cursor
                    )
                );
            }

            $reference = is_array($candidate['reference'] ?? null) ? $candidate['reference'] : [];
            if (($candidate['kind'] ?? '') === 'object') {
                $nodes[] = new AstNode('span', $this->embeddedObjectReferenceSpanAttrs($reference), [
                    new AstNode('text', ['text' => $this->embeddedObjectReferenceLabel($reference)]),
                ]);
            } elseif (($candidate['kind'] ?? '') === 'picture') {
                $nodes[] = new AstNode('span', $this->pictureReferenceSpanAttrs($reference), [
                    new AstNode('text', ['text' => $this->pictureReferenceLabel($reference)]),
                ]);
            } else {
                $nodes[] = new AstNode('span', $this->noteReferenceSpanAttrs($reference), [
                    new AstNode('superscript', [], [
                        new AstNode('text', ['text' => (string) ($reference['marker'] ?? '')]),
                    ]),
                ]);
            }
            $cursor = $localCp + 1;
        }

        if ($cursor < $segmentLength) {
            array_push(
                $nodes,
                ...$this->inlineNodes(
                    $this->charactersToString(array_slice($chars, $cursor)),
                    $segmentStartCp + $cursor
                )
            );
        }

        return $nodes === [] ? $this->inlineNodes($text, $segmentStartCp) : $nodes;
    }

    /**
     * @param array<string,mixed> $bookmark
     * @return array{id:string,classes:list<string>,attributes:array<string,string>}
     */
    private function bookmarkSpanAttrs(array $bookmark): array
    {
        $classes = ['legacy-doc-bookmark'];
        if (($bookmark['hidden'] ?? false) === true) {
            $classes[] = 'legacy-doc-bookmark-hidden';
        }

        return [
            'id' => (string) ($bookmark['anchorId'] ?? $bookmark['name'] ?? ''),
            'classes' => $classes,
            'attributes' => [
                'data-legacy-doc-bookmark' => (string) ($bookmark['name'] ?? ''),
                'data-legacy-doc-bookmark-start-cp' => (string) ((int) ($bookmark['startCp'] ?? 0)),
                'data-legacy-doc-bookmark-end-cp' => (string) ((int) ($bookmark['endCp'] ?? 0)),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $noteReference
     * @return array{classes:list<string>,attributes:array<string,string>}
     */
    private function noteReferenceSpanAttrs(array $noteReference): array
    {
        $type = (string) ($noteReference['type'] ?? 'footnote');
        if ($type === 'comment') {
            $attributes = [
                'data-legacy-doc-comment-index' => (string) ((int) ($noteReference['index'] ?? 0)),
                'data-legacy-doc-comment-reference-cp' => (string) ((int) ($noteReference['referenceCp'] ?? 0)),
                'data-legacy-doc-comment-text-start-cp' => (string) ((int) ($noteReference['textStartCp'] ?? 0)),
                'data-legacy-doc-comment-text-end-cp' => (string) ((int) ($noteReference['textEndCp'] ?? 0)),
                'data-legacy-doc-comment-author-index' => (string) ((int) ($noteReference['authorIndex'] ?? 0)),
            ];
            if (($noteReference['authorInitials'] ?? '') !== '') {
                $attributes['data-legacy-doc-comment-author-initials'] = (string) $noteReference['authorInitials'];
            }
            if (($noteReference['authorName'] ?? '') !== '') {
                $attributes['data-legacy-doc-comment-author-name'] = (string) $noteReference['authorName'];
            }
            if (isset($noteReference['bookmarkTag'])) {
                $attributes['data-legacy-doc-comment-bookmark-tag'] = (string) ((int) $noteReference['bookmarkTag']);
            }
            if (isset($noteReference['bodyText'])) {
                $attributes['data-legacy-doc-comment-has-body'] = 'true';
                $attributes['data-legacy-doc-comment-body-character-count'] = (string) ((int) ($noteReference['bodyCharacterCount'] ?? 0));
            }

            return [
                'classes' => ['legacy-doc-comment-ref'],
                'attributes' => $attributes,
            ];
        }

        return [
            'classes' => ['legacy-doc-note-ref', 'legacy-doc-' . $type . '-ref'],
            'attributes' => [
                'data-legacy-doc-note-type' => $type,
                'data-legacy-doc-note-index' => (string) ((int) ($noteReference['referenceIndex'] ?? 0)),
                'data-legacy-doc-note-reference-cp' => (string) ((int) ($noteReference['referenceCp'] ?? 0)),
                'data-legacy-doc-note-text-start-cp' => (string) ((int) ($noteReference['textStartCp'] ?? 0)),
                'data-legacy-doc-note-text-end-cp' => (string) ((int) ($noteReference['textEndCp'] ?? 0)),
                'data-legacy-doc-note-auto-numbered' => ($noteReference['autoNumbered'] ?? false) === true ? 'true' : 'false',
            ] + (isset($noteReference['bodyText']) ? [
                'data-legacy-doc-note-has-body' => 'true',
                'data-legacy-doc-note-body-character-count' => (string) ((int) ($noteReference['bodyCharacterCount'] ?? 0)),
            ] : []),
        ];
    }

    /**
     * @param list<array<string,mixed>> $embeddedObjects
     * @return list<array<string,mixed>>
     */
    private function embeddedObjectReferenceReport(string $text, array $embeddedObjects, bool $hasPictures = false): array
    {
        if ($embeddedObjects === [] || !str_contains($text, "\x01")) {
            return [];
        }

        $characters = $this->unicodeCharacters($text);
        $references = [];
        $objectIndex = 0;
        foreach ($characters as $cp => $character) {
            if ($character !== "\x01") {
                continue;
            }

            $object = $embeddedObjects[$objectIndex] ?? null;
            $record = [
                'type' => 'embedded-object',
                'referenceCp' => $cp,
                'characterCode' => 0x01,
                'objectIndex' => $objectIndex + 1,
                'canAnchor' => true,
            ];
            if (is_array($object)) {
                $record += $this->embeddedObjectReferenceObjectMetadata($object);
            } else {
                if ($hasPictures) {
                    continue;
                }
                $record['unmatchedObjectPoolEntry'] = true;
            }

            $references[] = $record;
            $objectIndex++;
        }

        return $references;
    }

    /**
     * @param list<array<string,mixed>> $embeddedObjectReferences
     * @return list<array<string,mixed>>
     */
    private function pictureReferenceReport(
        string $text,
        bool $hasPictures,
        array $embeddedObjectReferences,
        array $formattingRuns = [],
        ?string $dataStream = null,
        array $fileCharacterRanges = []
    ): array
    {
        if (!$hasPictures || !str_contains($text, "\x01")) {
            return [];
        }

        $objectReferenceCps = [];
        foreach ($embeddedObjectReferences as $reference) {
            if (isset($reference['referenceCp'])) {
                $objectReferenceCps[(int) $reference['referenceCp']] = true;
            }
        }

        $references = [];
        foreach ($this->unicodeCharacters($text) as $cp => $character) {
            if ($character !== "\x01" || isset($objectReferenceCps[$cp])) {
                continue;
            }

            $record = [
                'type' => 'inline-picture',
                'referenceCp' => $cp,
                'characterCode' => 0x01,
                'pictureIndex' => count($references) + 1,
                'source' => 'fib-has-pictures',
                'extractionPolicy' => 'metadata-only-native-review',
                'canAnchor' => true,
                'canExposeBytes' => false,
            ];
            $pictureData = $this->pictureDataForReferenceCp($formattingRuns, $fileCharacterRanges, $cp);
            if ($pictureData !== null) {
                if ($dataStream === null) {
                    throw new \RuntimeException('Legacy DOC inline picture CHPX metadata references a missing Data stream');
                }
                $dataStreamOffset = (int) ($pictureData['dataStreamOffset'] ?? -1);
                if ($dataStreamOffset < 0 || $dataStreamOffset > strlen($dataStream)) {
                    throw new \RuntimeException('Legacy DOC inline picture CHPX metadata points outside the Data stream');
                }

                $record['source'] = 'chpx-data-stream';
                $record['sourceSprms'] = is_array($pictureData['sourceSprms'] ?? null)
                    ? array_values(array_map(static fn (mixed $value): string => (string) $value, $pictureData['sourceSprms']))
                    : [];
                $record['dataStreamOffset'] = $dataStreamOffset;
                $record['availableDataBytes'] = strlen($dataStream) - $dataStreamOffset;
                $record['hasSpecialCharacterFormatting'] = ($pictureData['hasSpecialCharacterFormatting'] ?? false) === true;
                $record['isBinaryData'] = ($pictureData['isBinaryData'] ?? false) === true;
                $record['dataStreamKind'] = (string) ($pictureData['dataStreamKind'] ?? 'picture');
            }

            $references[] = $record;
        }

        return $references;
    }

    /**
     * @param list<array<string,mixed>> $fields
     * @param list<array<string,mixed>> $formattingRuns
     * @param list<array<string,int>> $fileCharacterRanges
     * @return list<array<string,mixed>>
     */
    private function formFieldDataReferenceReport(array $fields, array $formattingRuns, ?string $dataStream, array $fileCharacterRanges): array
    {
        $fieldTypeMap = [
            'formtext' => 'text',
            'formcheckbox' => 'checkbox',
            'formdropdown' => 'dropdown',
        ];

        $references = [];
        foreach ($fields as $field) {
            $fieldType = (string) ($field['type'] ?? '');
            if (!isset($fieldTypeMap[$fieldType]) || (string) ($field['story'] ?? 'main') !== 'main') {
                continue;
            }

            $beginCp = (int) ($field['beginCp'] ?? -1);
            if ($beginCp < 0) {
                continue;
            }

            $pictureData = $this->pictureDataForReferenceCp($formattingRuns, $fileCharacterRanges, $beginCp);
            if ($pictureData === null) {
                continue;
            }
            if ($dataStream === null) {
                throw new \RuntimeException('Legacy DOC form-field CHPX metadata references a missing Data stream');
            }

            $dataStreamOffset = (int) ($pictureData['dataStreamOffset'] ?? -1);
            if ($dataStreamOffset < 0 || $dataStreamOffset > strlen($dataStream)) {
                throw new \RuntimeException('Legacy DOC form-field CHPX metadata points outside the Data stream');
            }

            $formFieldData = $this->decodeFormFieldDataFromDataStream($dataStream, $dataStreamOffset);
            $expectedType = $fieldTypeMap[$fieldType];
            if (($formFieldData['fieldType'] ?? null) !== $expectedType) {
                throw new \RuntimeException('Legacy DOC FFData field type does not match the Plcfld form field type');
            }

            $reference = [
                'type' => 'form-field-data',
                'fieldIndex' => (int) ($field['index'] ?? (count($references) + 1)),
                'fieldType' => $fieldType,
                'fieldTypeCode' => (int) ($field['typeCode'] ?? -1),
                'formFieldType' => $expectedType,
                'beginCp' => $beginCp,
                'endCp' => (int) ($field['endCp'] ?? $beginCp),
                'source' => 'chpx-data-stream',
                'sourceSprms' => is_array($pictureData['sourceSprms'] ?? null)
                    ? array_values(array_map(static fn (mixed $value): string => (string) $value, $pictureData['sourceSprms']))
                    : [],
                'dataStreamOffset' => $dataStreamOffset,
                'dataStreamByteCount' => (int) ($formFieldData['byteCount'] ?? 0),
                'availableDataBytes' => strlen($dataStream) - $dataStreamOffset,
                'hasSpecialCharacterFormatting' => ($pictureData['hasSpecialCharacterFormatting'] ?? false) === true,
                'isBinaryData' => ($pictureData['isBinaryData'] ?? false) === true,
                'dataStreamKind' => (string) ($pictureData['dataStreamKind'] ?? 'binary-data'),
                'extractionPolicy' => 'metadata-only-native-review',
                'canExposeBytes' => false,
                'formFieldData' => $formFieldData,
            ];
            if (($formFieldData['name'] ?? '') !== '') {
                $reference['name'] = (string) $formFieldData['name'];
            }
            if (($formFieldData['entryMacro'] ?? '') !== '' || ($formFieldData['exitMacro'] ?? '') !== '') {
                $reference['macroPolicy'] = 'disabled-native-review';
            }

            $references[] = $reference;
        }

        return $references;
    }

    /**
     * @param list<array<string,mixed>> $references
     * @return array<int,array<string,mixed>>
     */
    private function formFieldDataReferencesByBeginCp(array $references): array
    {
        $byBeginCp = [];
        foreach ($references as $reference) {
            if (!isset($reference['beginCp'])) {
                continue;
            }

            $byBeginCp[(int) $reference['beginCp']] = $reference;
        }

        return $byBeginCp;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeFormFieldDataFromDataStream(string $dataStream, int $offset): array
    {
        if ($offset < 0 || $offset >= strlen($dataStream)) {
            throw new \RuntimeException('Legacy DOC FFData Data stream offset points outside the Data stream');
        }

        $available = substr($dataStream, $offset);
        $byteCount = $this->formFieldDataByteCount($available);

        return $this->decodeFormFieldData(substr($available, 0, $byteCount));
    }

    private function formFieldDataByteCount(string $bytes): int
    {
        if (strlen($bytes) < 10) {
            throw new \RuntimeException('Legacy DOC FFData Data stream payload is truncated');
        }

        $cursor = 4;
        $bits = self::u16($bytes, $cursor);
        $cursor += 2;
        $fieldTypeCode = $bits & 0x0003;
        if ($fieldTypeCode > 2) {
            throw new \RuntimeException('Legacy DOC FFData Data stream payload field type is unsupported');
        }

        $cursor += 4;
        if ($cursor > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC FFData Data stream payload is truncated');
        }

        $this->readLegacyDocXstz($bytes, $cursor, 'FFData field name', 20);
        if ($fieldTypeCode === 0) {
            $this->readLegacyDocXstz($bytes, $cursor, 'FFData default textbox text', 255);
        } else {
            if ($cursor + 2 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC FFData default state is truncated');
            }
            $cursor += 2;
        }

        $this->readLegacyDocXstz($bytes, $cursor, 'FFData textbox format', 64);
        $this->readLegacyDocXstz($bytes, $cursor, 'FFData help text', 255);
        $this->readLegacyDocXstz($bytes, $cursor, 'FFData status text', 138);
        $this->readLegacyDocXstz($bytes, $cursor, 'FFData entry macro', 32);
        $this->readLegacyDocXstz($bytes, $cursor, 'FFData exit macro', 32);
        if ($fieldTypeCode === 2) {
            $this->readLegacyDocUnicodeSttbStrings($bytes, $cursor, 'FFData dropdown list', 25, 255);
        }

        return $cursor;
    }

    /**
     * @param array<string,mixed> $reference
     * @return array<string,string>
     */
    private function formFieldDataReferenceAttributes(array $reference): array
    {
        $formFieldData = is_array($reference['formFieldData'] ?? null) ? $reference['formFieldData'] : [];
        $attributes = [
            'data-legacy-doc-form-field-data-source' => (string) ($reference['source'] ?? 'chpx-data-stream'),
            'data-legacy-doc-form-field-data-stream-offset' => (string) ((int) ($reference['dataStreamOffset'] ?? 0)),
            'data-legacy-doc-form-field-data-byte-count' => (string) ((int) ($reference['dataStreamByteCount'] ?? ($formFieldData['byteCount'] ?? 0))),
            'data-legacy-doc-form-field-data-available-bytes' => (string) ((int) ($reference['availableDataBytes'] ?? 0)),
            'data-legacy-doc-form-field-data-policy' => (string) ($reference['extractionPolicy'] ?? 'metadata-only-native-review'),
            'data-legacy-doc-form-field-can-expose-bytes' => ($reference['canExposeBytes'] ?? false) === true ? 'true' : 'false',
        ];

        if (is_array($reference['sourceSprms'] ?? null) && $reference['sourceSprms'] !== []) {
            $attributes['data-legacy-doc-form-field-source-sprms'] = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $reference['sourceSprms']));
        }

        foreach ([
            'name' => 'data-legacy-doc-form-field-name',
            'fieldType' => 'data-legacy-doc-form-field-ffdata-type',
            'textType' => 'data-legacy-doc-form-field-text-type',
            'defaultText' => 'data-legacy-doc-form-field-default-text',
            'textFormat' => 'data-legacy-doc-form-field-text-format',
            'helpText' => 'data-legacy-doc-form-field-help-text',
            'statusText' => 'data-legacy-doc-form-field-status-text',
            'entryMacro' => 'data-legacy-doc-form-field-entry-macro',
            'exitMacro' => 'data-legacy-doc-form-field-exit-macro',
            'checkboxState' => 'data-legacy-doc-form-field-checkbox-state',
            'defaultDropDownItem' => 'data-legacy-doc-form-field-default-item',
            'selectedDropDownItem' => 'data-legacy-doc-form-field-selected-item',
        ] as $sourceKey => $attributeName) {
            if (($formFieldData[$sourceKey] ?? '') !== '') {
                $attributes[$attributeName] = (string) $formFieldData[$sourceKey];
            }
        }

        foreach ([
            'maxLength' => 'data-legacy-doc-form-field-max-length',
            'checkboxSizeHalfPoints' => 'data-legacy-doc-form-field-checkbox-size-half-points',
            'defaultSelectedIndex' => 'data-legacy-doc-form-field-default-selected-index',
            'selectedIndex' => 'data-legacy-doc-form-field-selected-index',
            'dropDownItemCount' => 'data-legacy-doc-form-field-item-count',
        ] as $sourceKey => $attributeName) {
            if (array_key_exists($sourceKey, $formFieldData) && $formFieldData[$sourceKey] !== null) {
                $attributes[$attributeName] = (string) ((int) $formFieldData[$sourceKey]);
            }
        }

        foreach ([
            'protected' => 'data-legacy-doc-form-field-protected',
            'recalculateOnExit' => 'data-legacy-doc-form-field-recalculate-on-exit',
            'defaultChecked' => 'data-legacy-doc-form-field-default-checked',
            'checked' => 'data-legacy-doc-form-field-checked',
            'checkboxAutoSize' => 'data-legacy-doc-form-field-checkbox-auto-size',
            'selectionUndefined' => 'data-legacy-doc-form-field-selection-undefined',
        ] as $sourceKey => $attributeName) {
            if (array_key_exists($sourceKey, $formFieldData)) {
                $attributes[$attributeName] = $formFieldData[$sourceKey] === true ? 'true' : 'false';
            }
        }

        if (is_array($formFieldData['dropDownItems'] ?? null) && $formFieldData['dropDownItems'] !== []) {
            $attributes['data-legacy-doc-form-field-items'] = implode('; ', array_map(static fn (mixed $value): string => (string) $value, $formFieldData['dropDownItems']));
        }
        if (($formFieldData['entryMacro'] ?? '') !== '' || ($formFieldData['exitMacro'] ?? '') !== '') {
            $attributes['data-legacy-doc-form-field-macro-policy'] = 'disabled-native-review';
        }

        return $attributes;
    }

    /**
     * @param array<string,mixed> $textResult
     * @param array<string,mixed> $fib
     * @return list<array<string,int>>
     */
    private function textFileCharacterRanges(array $textResult, array $fib): array
    {
        if (is_array($textResult['fileCharacterRanges'] ?? null)) {
            $ranges = [];
            foreach ($textResult['fileCharacterRanges'] as $range) {
                if (!is_array($range)) {
                    continue;
                }
                $ranges[] = [
                    'cpStart' => (int) ($range['cpStart'] ?? 0),
                    'cpEnd' => (int) ($range['cpEnd'] ?? 0),
                    'fcStart' => (int) ($range['fcStart'] ?? 0),
                    'fcEnd' => (int) ($range['fcEnd'] ?? 0),
                    'bytesPerCharacter' => max(1, (int) ($range['bytesPerCharacter'] ?? 1)),
                ];
            }

            return $ranges;
        }

        $text = (string) ($textResult['text'] ?? '');
        $characterCount = count($this->unicodeCharacters($text));
        if ($characterCount === 0) {
            return [];
        }

        $fcMin = (int) ($fib['fcMin'] ?? 0);
        $fcMac = (int) ($fib['fcMac'] ?? $fcMin);
        $byteLength = max(0, $fcMac - $fcMin);
        $bytesPerCharacter = $characterCount > 0 && $byteLength === $characterCount * 2 ? 2 : 1;

        return [[
            'cpStart' => 0,
            'cpEnd' => $characterCount,
            'fcStart' => $fcMin,
            'fcEnd' => $fcMac,
            'bytesPerCharacter' => $bytesPerCharacter,
        ]];
    }

    /**
     * @param list<array<string,mixed>> $formattingRuns
     * @param list<array<string,int>> $fileCharacterRanges
     * @return array<string,mixed>|null
     */
    private function pictureDataForReferenceCp(array $formattingRuns, array $fileCharacterRanges, int $referenceCp): ?array
    {
        foreach ($formattingRuns as $run) {
            if (($run['kind'] ?? null) !== 'character' || !is_array($run['pictureData'] ?? null) || $run['pictureData'] === []) {
                continue;
            }

            $cpRange = $this->characterRangeForFileOffsets(
                (int) ($run['startFc'] ?? -1),
                (int) ($run['endFc'] ?? -1),
                $fileCharacterRanges
            );
            if ($cpRange === null) {
                continue;
            }
            if ($referenceCp < $cpRange['cpStart'] || $referenceCp >= $cpRange['cpEnd']) {
                continue;
            }

            $pictureData = $run['pictureData'][0] ?? null;
            return is_array($pictureData) ? $pictureData : null;
        }

        return null;
    }

    /**
     * @param list<array<string,int>> $fileCharacterRanges
     * @return array{cpStart:int,cpEnd:int}|null
     */
    private function characterRangeForFileOffsets(int $startFc, int $endFc, array $fileCharacterRanges): ?array
    {
        if ($startFc < 0 || $endFc <= $startFc) {
            return null;
        }

        foreach ($fileCharacterRanges as $range) {
            $fcStart = (int) ($range['fcStart'] ?? 0);
            $fcEnd = (int) ($range['fcEnd'] ?? 0);
            $bytesPerCharacter = max(1, (int) ($range['bytesPerCharacter'] ?? 1));
            if ($startFc < $fcStart || $endFc > $fcEnd) {
                continue;
            }

            return [
                'cpStart' => (int) ($range['cpStart'] ?? 0) + intdiv($startFc - $fcStart, $bytesPerCharacter),
                'cpEnd' => (int) ($range['cpStart'] ?? 0) + intdiv($endFc - $fcStart + $bytesPerCharacter - 1, $bytesPerCharacter),
            ];
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $formattingRuns
     * @param list<array<string,int>> $fileCharacterRanges
     * @return list<array<string,mixed>>
     */
    private function inlineTextFormattingApplications(array $formattingRuns, array $fileCharacterRanges): array
    {
        $applications = [];
        foreach ($formattingRuns as $run) {
            if (($run['kind'] ?? null) !== 'character' || !is_array($run['textProperties'] ?? null)) {
                continue;
            }

            $nodeTypes = is_array($run['inlineFormattingNodeTypes'] ?? null)
                ? array_values(array_map(static fn (mixed $nodeType): string => (string) $nodeType, $run['inlineFormattingNodeTypes']))
                : $this->inlineFormattingNodeTypes($run['textProperties']);
            if ($nodeTypes === []) {
                continue;
            }

            $cpRange = $this->characterRangeForFileOffsets(
                (int) ($run['startFc'] ?? -1),
                (int) ($run['endFc'] ?? -1),
                $fileCharacterRanges
            );
            if ($cpRange === null || $cpRange['cpEnd'] <= $cpRange['cpStart']) {
                continue;
            }

            $sourceSprms = [];
            foreach ($run['textProperties'] as $property) {
                if (!is_array($property) || $this->inlineFormattingPropertyNodeType($property) === null) {
                    continue;
                }
                if (isset($property['sourceSprm'])) {
                    $sourceSprms[] = (string) $property['sourceSprm'];
                }
            }

            $applications[] = [
                'source' => 'ChpxFkp',
                'formattingRunIndex' => (int) ($run['index'] ?? 0),
                'cpStart' => $cpRange['cpStart'],
                'cpEnd' => $cpRange['cpEnd'],
                'nodeTypes' => $nodeTypes,
                'sourceSprms' => array_values(array_unique($sourceSprms)),
                'policy' => 'semantic-inline-native-review',
            ];
        }

        usort(
            $applications,
            static function (array $left, array $right): int {
                $start = ((int) $left['cpStart']) <=> ((int) $right['cpStart']);
                if ($start !== 0) {
                    return $start;
                }

                return ((int) $left['cpEnd']) <=> ((int) $right['cpEnd']);
            }
        );

        return $applications;
    }

    /**
     * @param list<array<string,mixed>> $formattingRuns
     * @param list<array<string,int>> $fileCharacterRanges
     * @return list<array<string,mixed>>
     */
    private function hiddenTextSuppressionApplications(array $formattingRuns, array $fileCharacterRanges): array
    {
        $applications = [];
        foreach ($formattingRuns as $run) {
            if (($run['kind'] ?? null) !== 'character' || !is_array($run['textProperties'] ?? null)) {
                continue;
            }

            $hiddenProperties = array_values(array_filter(
                $run['textProperties'],
                static fn (mixed $property): bool => is_array($property)
                    && ($property['name'] ?? null) === 'hidden'
                    && ($property['enabled'] ?? false) === true
            ));
            if ($hiddenProperties === []) {
                continue;
            }

            $cpRange = $this->characterRangeForFileOffsets(
                (int) ($run['startFc'] ?? -1),
                (int) ($run['endFc'] ?? -1),
                $fileCharacterRanges
            );
            if ($cpRange === null || $cpRange['cpEnd'] <= $cpRange['cpStart']) {
                continue;
            }

            $sourceSprms = [];
            foreach ($hiddenProperties as $property) {
                if (isset($property['sourceSprm'])) {
                    $sourceSprms[] = (string) $property['sourceSprm'];
                }
            }

            $applications[] = [
                'source' => 'ChpxFkp',
                'formattingRunIndex' => (int) ($run['index'] ?? 0),
                'cpStart' => $cpRange['cpStart'],
                'cpEnd' => $cpRange['cpEnd'],
                'characterCount' => $cpRange['cpEnd'] - $cpRange['cpStart'],
                'sourceSprms' => array_values(array_unique($sourceSprms)),
                'policy' => 'suppressed-hidden-text-native-review',
            ];
        }

        usort(
            $applications,
            static function (array $left, array $right): int {
                $start = ((int) $left['cpStart']) <=> ((int) $right['cpStart']);
                if ($start !== 0) {
                    return $start;
                }

                return ((int) $left['cpEnd']) <=> ((int) $right['cpEnd']);
            }
        );

        return $applications;
    }

    /**
     * @param list<array<string,mixed>> $textProperties
     * @return list<string>
     */
    private function inlineFormattingNodeTypes(array $textProperties): array
    {
        $nodeTypes = [];
        foreach ($textProperties as $property) {
            if (!is_array($property)) {
                continue;
            }

            $nodeType = $this->inlineFormattingPropertyNodeType($property);
            if ($nodeType !== null) {
                $nodeTypes[] = $nodeType;
            }
        }

        $ordered = [];
        foreach (['strong', 'emph', 'underline', 'strikeout', 'small_caps'] as $nodeType) {
            if (in_array($nodeType, $nodeTypes, true)) {
                $ordered[] = $nodeType;
            }
        }

        return $ordered;
    }

    /**
     * @param array<string,mixed> $property
     */
    private function inlineFormattingPropertyNodeType(array $property): ?string
    {
        if (($property['enabled'] ?? false) !== true) {
            return null;
        }

        return match ((string) ($property['name'] ?? '')) {
            'bold' => 'strong',
            'italic' => 'emph',
            'underline' => 'underline',
            'strikethrough' => 'strikeout',
            'small-caps' => 'small_caps',
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $object
     * @return array<string,mixed>
     */
    private function embeddedObjectReferenceObjectMetadata(array $object): array
    {
        $record = [
            'storagePath' => (string) ($object['storagePath'] ?? ''),
            'objectId' => (string) ($object['objectId'] ?? ''),
            'streamCount' => (int) ($object['streamCount'] ?? 0),
            'totalBytes' => (int) ($object['totalBytes'] ?? 0),
            'hasNativeData' => ($object['hasNativeData'] ?? false) === true,
            'hasPresentationData' => ($object['hasPresentationData'] ?? false) === true,
            'canExposeBytes' => false,
        ];

        $label = $this->embeddedObjectDisplayLabel($object);
        if ($label !== '') {
            $record['label'] = $label;
        }
        foreach ([
            'nativeLabels',
            'nativeSourcePaths',
            'compoundObjectDisplayNames',
            'compoundObjectClipboardFormats',
        ] as $field) {
            if (is_array($object[$field] ?? null) && $object[$field] !== []) {
                $record[$field] = array_values(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $object[$field]
                ));
            }
        }
        if (is_array($object['transmissionFormat'] ?? null)) {
            $format = $object['transmissionFormat'];
            $record['transmissionFormat'] = [
                'code' => (int) ($format['code'] ?? 0),
                'name' => (string) ($format['name'] ?? ''),
            ];
        }
        if (isset($object['nativeDataBytes']) && is_int($object['nativeDataBytes'])) {
            $record['nativeDataBytes'] = $object['nativeDataBytes'];
        }

        return $record;
    }

    /**
     * @param array<string,mixed> $object
     */
    private function embeddedObjectDisplayLabel(array $object): string
    {
        foreach (['nativeLabels', 'compoundObjectDisplayNames', 'compoundObjectClipboardFormats'] as $field) {
            if (is_array($object[$field] ?? null) && isset($object[$field][0]) && (string) $object[$field][0] !== '') {
                return (string) $object[$field][0];
            }
        }

        return (string) ($object['objectId'] ?? '');
    }

    /**
     * @param array<string,mixed> $reference
     * @return array{classes:list<string>,attributes:array<string,string>}
     */
    private function embeddedObjectReferenceSpanAttrs(array $reference): array
    {
        $attributes = [
            'data-legacy-doc-object-ref' => (string) ((int) ($reference['objectIndex'] ?? 0)),
            'data-legacy-doc-object-reference-cp' => (string) ((int) ($reference['referenceCp'] ?? 0)),
            'data-legacy-doc-object-character-code' => (string) ((int) ($reference['characterCode'] ?? 0)),
            'data-legacy-doc-object-can-expose-bytes' => 'false',
        ];

        foreach ([
            'storagePath' => 'data-legacy-doc-object-storage',
            'objectId' => 'data-legacy-doc-object-id',
            'label' => 'data-legacy-doc-object-label',
        ] as $source => $attribute) {
            if (($reference[$source] ?? '') !== '') {
                $attributes[$attribute] = (string) $reference[$source];
            }
        }
        if (isset($reference['nativeDataBytes'])) {
            $attributes['data-legacy-doc-object-native-data-bytes'] = (string) ((int) $reference['nativeDataBytes']);
        }
        if (is_array($reference['transmissionFormat'] ?? null) && ($reference['transmissionFormat']['name'] ?? '') !== '') {
            $attributes['data-legacy-doc-object-transmission-format'] = (string) $reference['transmissionFormat']['name'];
        }
        if (($reference['hasNativeData'] ?? false) === true) {
            $attributes['data-legacy-doc-object-has-native-data'] = 'true';
        }
        if (($reference['hasPresentationData'] ?? false) === true) {
            $attributes['data-legacy-doc-object-has-presentation-data'] = 'true';
        }
        if (($reference['unmatchedObjectPoolEntry'] ?? false) === true) {
            $attributes['data-legacy-doc-object-unmatched'] = 'true';
        }

        return [
            'classes' => ['legacy-doc-object-ref'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string,mixed> $reference
     */
    private function embeddedObjectReferenceLabel(array $reference): string
    {
        $label = trim((string) ($reference['label'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($reference['objectId'] ?? ''));
        }

        return $label === '' ? 'embedded object' : 'embedded object: ' . $label;
    }

    /**
     * @param array<string,mixed> $reference
     * @return array{classes:list<string>,attributes:array<string,string>}
     */
    private function pictureReferenceSpanAttrs(array $reference): array
    {
        $attributes = [
            'data-legacy-doc-picture-ref' => (string) ((int) ($reference['pictureIndex'] ?? 0)),
            'data-legacy-doc-picture-reference-cp' => (string) ((int) ($reference['referenceCp'] ?? 0)),
            'data-legacy-doc-picture-character-code' => (string) ((int) ($reference['characterCode'] ?? 0)),
            'data-legacy-doc-picture-can-expose-bytes' => 'false',
            'data-legacy-doc-picture-source' => (string) ($reference['source'] ?? 'fib-has-pictures'),
            'data-legacy-doc-picture-policy' => (string) ($reference['extractionPolicy'] ?? 'metadata-only-native-review'),
        ];
        if (isset($reference['dataStreamOffset'])) {
            $attributes['data-legacy-doc-picture-data-stream-offset'] = (string) ((int) $reference['dataStreamOffset']);
        }
        if (isset($reference['availableDataBytes'])) {
            $attributes['data-legacy-doc-picture-data-stream-available-bytes'] = (string) ((int) $reference['availableDataBytes']);
        }
        if (is_array($reference['sourceSprms'] ?? null) && $reference['sourceSprms'] !== []) {
            $attributes['data-legacy-doc-picture-source-sprms'] = implode(' ', array_map(
                static fn (mixed $value): string => (string) $value,
                $reference['sourceSprms']
            ));
        }
        if (isset($reference['dataStreamKind'])) {
            $attributes['data-legacy-doc-picture-data-stream-kind'] = (string) $reference['dataStreamKind'];
        }

        return [
            'classes' => ['legacy-doc-picture-ref'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string,mixed> $reference
     */
    private function pictureReferenceLabel(array $reference): string
    {
        return 'inline picture';
    }

    /**
     * @return array<string,mixed>
     */
    private function readMetadata(CompoundFileBinary $compoundFile): array
    {
        $metadata = [];
        if ($compoundFile->hasStream(self::SUMMARY_INFORMATION)) {
            $metadata = array_replace(
                $metadata,
                $this->propertySetMetadata($compoundFile->readStream(self::SUMMARY_INFORMATION), 'summary')
            );
        }
        if ($compoundFile->hasStream(self::DOCUMENT_SUMMARY_INFORMATION)) {
            $metadata = array_replace(
                $metadata,
                $this->propertySetMetadata($compoundFile->readStream(self::DOCUMENT_SUMMARY_INFORMATION), 'document-summary')
            );
        }

        return $metadata;
    }

    /**
     * @return array<string,mixed>
     */
    private function documentPropertyReport(string $wordDocument, ?string $tableStream): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_DOP + 4) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_DOP);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC DOP document properties require the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_DOP);
        return $this->parseDop($this->tableStreamSlice($tableStream, $offset, $length, 'DOP document properties'));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function documentVariableReport(string $wordDocument, ?string $tableStream): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_STW_USER + 4) {
            return [];
        }
        $fcMin = self::u32($wordDocument, 24);
        if ($fcMin > 0 && self::FIB_LCB_STW_USER + 4 > $fcMin) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_STW_USER);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC document variables require the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_STW_USER);
        return $this->parseStwUser($this->tableStreamSlice($tableStream, $offset, $length, 'StwUser document variables'));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseStwUser(string $bytes): array
    {
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('Legacy DOC StwUser document variables are truncated');
        }

        $cursor = 0;
        $names = $this->readStwUserNameTable($bytes, $cursor);
        if ($names === []) {
            if ($cursor !== strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC StwUser document variables contain trailing bytes');
            }

            return [];
        }

        $variables = [];
        foreach ($names as $index => $name) {
            $variables[] = $this->readStwUserValue($bytes, $cursor, $name, $index);
        }
        if ($cursor !== strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC StwUser document variables contain trailing bytes');
        }

        return $variables;
    }

    /**
     * @return list<string>
     */
    private function readStwUserNameTable(string $bytes, int &$cursor): array
    {
        if ($cursor + 6 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC StwUser name table is truncated');
        }
        if (self::u16($bytes, $cursor) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC StwUser name table must use extended strings');
        }
        $cursor += 2;
        $count = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($count > 4096) {
            throw new \RuntimeException('Legacy DOC StwUser name table contains too many variables');
        }
        if (self::u16($bytes, $cursor) !== 4) {
            throw new \RuntimeException('Legacy DOC StwUser name table must carry four-byte ignored extra data');
        }
        $cursor += 2;

        $names = [];
        $seen = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC StwUser variable name length is truncated');
            }
            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters === 0 || $characters > 255) {
                throw new \RuntimeException('Legacy DOC StwUser variable name length is invalid');
            }
            $byteLength = $characters * 2;
            if ($cursor + $byteLength + 4 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC StwUser variable name is truncated');
            }
            $name = $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
            $cursor += 4;
            if ($name === '') {
                throw new \RuntimeException('Legacy DOC StwUser variable name is empty');
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if (isset($seen[$key])) {
                throw new \RuntimeException('Legacy DOC StwUser variable names must be unique');
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }

    /**
     * @return array<string,mixed>
     */
    private function readStwUserValue(string $bytes, int &$cursor, string $name, int $index): array
    {
        if ($cursor + 2 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC StwUser variable value length is truncated');
        }

        $characters = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($characters > 4096) {
            throw new \RuntimeException('Legacy DOC StwUser variable value length is invalid');
        }

        $byteLength = $characters * 2;
        if ($cursor + $byteLength > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC StwUser variable value is truncated');
        }

        $valueBytes = substr($bytes, $cursor, $byteLength);
        $cursor += $byteLength;
        $record = [
            'index' => $index,
            'name' => $name,
            'valueCharacterCount' => $characters,
        ];
        if ($this->isSignatureDocumentVariableName($name)) {
            $record['signatureVariable'] = true;
            $record['redacted'] = true;
            $record['valueByteCount'] = 2 + $byteLength;
            $record['extractionPolicy'] = 'signature-blob-metadata-only';
            $record['canExposeBytes'] = false;

            return $record;
        }

        $record['value'] = $this->decodeUtf16Le($valueBytes);
        $record['valueByteCount'] = 2 + $byteLength;

        return $record;
    }

    private function isSignatureDocumentVariableName(string $name): bool
    {
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);

        return in_array($normalized, ['sign', 'sigagile', 'sigv3'], true);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function saveHistoryReport(string $wordDocument, ?string $tableStream): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_STTB_SAVED_BY + 4) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_STTB_SAVED_BY);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC save-history metadata requires the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_STTB_SAVED_BY);

        return $this->parseSttbSavedBy(
            $this->tableStreamSlice($tableStream, $offset, $length, 'SttbSavedBy save-history table')
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseSttbSavedBy(string $bytes): array
    {
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('Legacy DOC save-history table is truncated');
        }
        if (self::u16($bytes, 0) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC save-history table must use extended strings');
        }

        $count = self::u16($bytes, 2);
        if (($count % 2) !== 0 || $count > 0x0014) {
            throw new \RuntimeException('Legacy DOC save-history table must contain an even count of at most 20 strings');
        }
        if (self::u16($bytes, 4) !== 0) {
            throw new \RuntimeException('Legacy DOC save-history table must not contain extra data');
        }

        $cursor = 6;
        $strings = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC save-history table is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters > 4096) {
                throw new \RuntimeException('Legacy DOC save-history string length exceeds the bounded native reader limit');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC save-history table points outside its string data');
            }

            $strings[] = $characters === 0 ? '' : $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
        }
        if ($cursor !== strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC save-history table contains trailing bytes');
        }

        $history = [];
        for ($index = 0, $pairIndex = 0; $index < count($strings); $index += 2, $pairIndex++) {
            $path = $strings[$index + 1];
            $history[] = [
                'index' => $pairIndex,
                'sourceTable' => 'SttbSavedBy',
                'order' => 'earliest-to-latest',
                'author' => $strings[$index],
                'path' => $path,
                'basename' => $this->legacyPathBasename($path),
            ];
        }

        return $history;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function externalFileReferenceReport(string $wordDocument, ?string $tableStream): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_STTB_FNM + 4) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_STTB_FNM);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC external filename metadata requires the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_STTB_FNM);

        return $this->parseSttbFnm(
            $this->tableStreamSlice($tableStream, $offset, $length, 'SttbFnm external filename table')
        );
    }

    /**
     * @param list<array<string,mixed>> $externalFileReferences
     * @return list<array<string,mixed>>
     */
    private function subdocumentReferenceReport(array $externalFileReferences): array
    {
        $subdocuments = [];
        foreach ($externalFileReferences as $reference) {
            if (($reference['referenceType'] ?? null) !== 'subdocument') {
                continue;
            }

            $path = (string) ($reference['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $record = [
                'index' => count($subdocuments),
                'sourceTable' => (string) ($reference['sourceTable'] ?? 'SttbFnm'),
                'externalFileReferenceIndex' => (int) ($reference['index'] ?? 0),
                'relationshipRole' => 'master-subdocument-link',
                'path' => $path,
                'pathKind' => $this->legacyPathKind($path),
                'pathCharacterCount' => (int) ($reference['pathCharacterCount'] ?? $this->textCharacterLength($path)),
                'basename' => (string) ($reference['basename'] ?? $this->legacyPathBasename($path)),
                'fnpi' => (int) ($reference['fnpi'] ?? 0),
                'documentIndex' => (int) ($reference['documentIndex'] ?? 0),
                'ichRelative' => (int) ($reference['ichRelative'] ?? 0xff),
                'fileSystem' => (string) ($reference['fileSystem'] ?? 'unknown'),
                'canExposeBytes' => false,
                'extractionPolicy' => (string) ($reference['extractionPolicy'] ?? 'metadata-only-native-review'),
            ];
            if (isset($reference['relativePath']) && is_string($reference['relativePath']) && $reference['relativePath'] !== '') {
                $record['relativePath'] = $reference['relativePath'];
            }
            if (isset($reference['fileSystemFlags']) && is_array($reference['fileSystemFlags'])) {
                $record['fileSystemFlags'] = array_values(array_map(
                    static fn (mixed $flag): string => (string) $flag,
                    $reference['fileSystemFlags']
                ));
            }

            $subdocuments[] = $record;
        }

        return $subdocuments;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseSttbFnm(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 6) {
            throw new \RuntimeException('Legacy DOC external filename table is truncated');
        }
        if (self::u16($bytes, 0) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC external filename table must use extended strings');
        }

        $count = self::u16($bytes, 2);
        if ($count > 1024) {
            throw new \RuntimeException('Legacy DOC external filename table contains too many references');
        }
        if (self::u16($bytes, 4) !== 8) {
            throw new \RuntimeException('Legacy DOC external filename table must contain 8-byte FNIF records');
        }

        $references = [];
        $cursor = 6;
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC external filename table string length is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters === 0) {
                throw new \RuntimeException('Legacy DOC external filename table contains an empty filename');
            }
            if ($characters > 2048) {
                throw new \RuntimeException('Legacy DOC external filename length exceeds the bounded native reader limit');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength > $length) {
                throw new \RuntimeException('Legacy DOC external filename table points outside its string data');
            }

            $path = $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
            if ($cursor + 8 > $length) {
                throw new \RuntimeException('Legacy DOC external filename table is truncated before its FNIF record');
            }

            $fnpi = self::u16($bytes, $cursor);
            $referenceTypeCode = $fnpi & 0x000f;
            $documentIndex = ($fnpi >> 4) & 0x0fff;
            $ichRelative = ord($bytes[$cursor + 2]);
            $fnfb = ord($bytes[$cursor + 3]);
            $cursor += 8;

            if (!in_array($referenceTypeCode, [3, 5], true)) {
                throw new \RuntimeException('Legacy DOC external filename table contains an invalid FNPI reference type');
            }
            if ($documentIndex === 0x0fff) {
                throw new \RuntimeException('Legacy DOC external filename table contains an invalid FNPI document identifier');
            }

            $fileSystemFlags = $this->legacyDocFnfbFileSystemFlags($fnfb);
            $hasFat = in_array('fat', $fileSystemFlags, true);
            $hasNtfs = in_array('ntfs', $fileSystemFlags, true);
            $hasNonFileSystem = in_array('non-file-system', $fileSystemFlags, true);
            if ($hasNonFileSystem && ($hasFat || $hasNtfs)) {
                throw new \RuntimeException('Legacy DOC external filename table combines non-file-system and file-system flags');
            }

            $pathCharacters = $this->unicodeCharacters($path);
            $record = [
                'index' => $index,
                'sourceTable' => 'SttbFnm',
                'path' => $path,
                'pathCharacterCount' => count($pathCharacters),
                'basename' => $this->legacyPathBasename($path),
                'fnpi' => $fnpi,
                'referenceTypeCode' => $referenceTypeCode,
                'referenceType' => $this->legacyDocExternalFileReferenceType($referenceTypeCode),
                'documentIndex' => $documentIndex,
                'ichRelative' => $ichRelative,
                'fnfb' => $fnfb,
                'fileSystemFlags' => $fileSystemFlags,
                'fileSystem' => $this->legacyDocFnfbFileSystem($fileSystemFlags),
                'canExposeBytes' => false,
                'extractionPolicy' => 'metadata-only-native-review',
            ];
            if ($ichRelative !== 0xff) {
                if ($ichRelative >= count($pathCharacters)) {
                    throw new \RuntimeException('Legacy DOC external filename relative path offset points outside the filename');
                }
                $record['relativePath'] = $this->charactersToString(array_slice($pathCharacters, $ichRelative));
            }

            $references[] = $record;
        }

        if ($cursor !== $length) {
            throw new \RuntimeException('Legacy DOC external filename table contains trailing bytes');
        }

        return $references;
    }

    private function legacyDocExternalFileReferenceType(int $referenceTypeCode): string
    {
        return match ($referenceTypeCode) {
            3 => 'mail-merge-data-source',
            5 => 'subdocument',
            default => 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    private function legacyDocFnfbFileSystemFlags(int $fnfb): array
    {
        $flags = [];
        if (($fnfb & 0x01) !== 0) {
            $flags[] = 'fat';
        }
        if (($fnfb & 0x08) !== 0) {
            $flags[] = 'ntfs';
        }
        if (($fnfb & 0x10) !== 0) {
            $flags[] = 'non-file-system';
        }

        return $flags;
    }

    /**
     * @param list<string> $flags
     */
    private function legacyDocFnfbFileSystem(array $flags): string
    {
        if (in_array('non-file-system', $flags, true)) {
            return 'non-file-system';
        }
        if (in_array('ntfs', $flags, true)) {
            return in_array('fat', $flags, true) ? 'fat+ntfs' : 'ntfs';
        }
        if (in_array('fat', $flags, true)) {
            return 'fat';
        }

        return $flags === [] ? 'unspecified' : 'unknown';
    }

    /**
     * @param list<array<string,mixed>> $externalFileReferences
     * @return array<string,mixed>
     */
    private function mailMergeSettingsReport(string $wordDocument, ?string $tableStream, array $externalFileReferences): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_PMS + 4) {
            return [];
        }
        $fcMin = self::u32($wordDocument, 24);
        if ($fcMin > 0 && self::FIB_LCB_PMS + 4 > $fcMin) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_PMS);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC Pms mail-merge settings require the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_PMS);

        return $this->parseMailMergeSettings(
            $this->tableStreamSlice($tableStream, $offset, $length, 'Pms mail-merge settings'),
            $externalFileReferences
        );
    }

    /**
     * @param list<array<string,mixed>> $externalFileReferences
     * @return array<string,mixed>
     */
    private function parseMailMergeSettings(string $bytes, array $externalFileReferences): array
    {
        $length = strlen($bytes);
        if ($length < 30) {
            throw new \RuntimeException('Legacy DOC Pms mail-merge settings are truncated');
        }

        $cursor = 0;
        $stateBits = self::u16($bytes, $cursor);
        $cursor += 2;
        $state = $this->legacyDocMailMergeState($stateBits);
        $headerFieldSourceIndex = ord($bytes[$cursor]);
        $cursor++;
        $dataFetchSourceIndex = ord($bytes[$cursor]);
        $cursor++;
        if ($headerFieldSourceIndex > 1 || $dataFetchSourceIndex > 1) {
            throw new \RuntimeException('Legacy DOC Pms mail-merge source index is outside the two PMFS records');
        }

        $currentRecordIndex = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($currentRecordIndex > 0xfffffff0 && $currentRecordIndex !== 0xffffffff) {
            throw new \RuntimeException('Legacy DOC Pms current record index uses a reserved sentinel value');
        }

        $sourceRecords = [];
        for ($index = 0; $index < 2; $index++) {
            $record = $this->readLegacyDocMailMergeSourceRecord($bytes, $cursor, $index, $externalFileReferences);
            if ($record['source'] !== 'none') {
                $sourceRecords[] = $record;
            }
        }

        $recordFilterBits = self::u32($bytes, $cursor);
        $cursor += 4;
        $recordFilter = $this->legacyDocMailMergeRecordFilter($recordFilterBits);

        $sqlByteCount = self::u16($bytes, $cursor);
        $cursor += 2;

        $report = [
            'sourceTable' => 'Pms',
            'extractionPolicy' => 'metadata-only-native-review',
            'stateBits' => $stateBits,
            'state' => $state,
            'stateFlags' => $state['flags'],
            'documentType' => $state['documentType'],
            'destination' => $state['destination'],
            'headerFieldSourceIndex' => $headerFieldSourceIndex,
            'dataFetchSourceIndex' => $dataFetchSourceIndex,
            'sourceRecordCount' => count($sourceRecords),
            'sourceRecords' => $sourceRecords,
            'recordFilter' => $recordFilter,
        ];
        if ($currentRecordIndex === 0xffffffff) {
            $report['currentRecordNil'] = true;
        } else {
            $report['currentRecordIndex'] = $currentRecordIndex;
        }

        if ($sqlByteCount > 0) {
            if (($sqlByteCount % 2) !== 0) {
                throw new \RuntimeException('Legacy DOC Pms SQL query byte count must be UTF-16LE aligned');
            }
            if ($cursor + $sqlByteCount > $length) {
                throw new \RuntimeException('Legacy DOC Pms SQL query points outside the settings payload');
            }

            $sqlBytes = substr($bytes, $cursor, $sqlByteCount);
            $cursor += $sqlByteCount;
            if ($sqlByteCount < 2 || substr($sqlBytes, -2) !== "\0\0") {
                throw new \RuntimeException('Legacy DOC Pms SQL query must be null-terminated');
            }

            $query = $this->decodeUtf16Le(substr($sqlBytes, 0, -2));
            if ($this->textCharacterLength($query) > 4096) {
                throw new \RuntimeException('Legacy DOC Pms SQL query exceeds the bounded native reader limit');
            }
            $report['sqlQuery'] = $query;
            $report['sqlQueryPolicy'] = 'metadata-only-native-review';
        }

        if ((int) $recordFilter['sttbfRfsHandle'] !== 0) {
            $report['recordFilterStrings'] = $this->readLegacyDocUnicodeSttbStrings(
                $bytes,
                $cursor,
                'Pms SttbfRfs record-filter string table',
                64,
                1024
            );
            $report['recordFilterStringCount'] = count($report['recordFilterStrings']);
        }

        if ($cursor !== $length) {
            throw new \RuntimeException('Legacy DOC Pms mail-merge settings contain trailing bytes');
        }

        return $report;
    }

    /**
     * @param list<array<string,mixed>> $externalFileReferences
     * @return array<string,mixed>
     */
    private function readLegacyDocMailMergeSourceRecord(string $bytes, int &$cursor, int $index, array $externalFileReferences): array
    {
        if ($cursor + 8 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC Pms source record is truncated');
        }

        $sourceCode = ord($bytes[$cursor]);
        $cursor++;
        $flags = ord($bytes[$cursor]);
        $cursor++;
        if (($flags & ~0x0f) !== 0) {
            throw new \RuntimeException('Legacy DOC Pms source record contains unknown flag bits');
        }

        $fieldToken = self::u16($bytes, $cursor);
        $cursor += 2;
        $recordToken = self::u16($bytes, $cursor);
        $cursor += 2;
        $fnpi = self::u16($bytes, $cursor);
        $cursor += 2;
        $referenceTypeCode = $fnpi & 0x000f;
        $documentIndex = ($fnpi >> 4) & 0x0fff;
        $source = $this->legacyDocMailMergeSourceName($sourceCode);

        $record = [
            'index' => $index,
            'sourceCode' => $sourceCode,
            'source' => $source,
            'flagBits' => $flags,
            'flags' => $this->legacyDocMailMergeSourceFlags($flags),
            'fieldSeparatorTokenCode' => $fieldToken,
            'fieldSeparatorToken' => $this->legacyDocMailMergeSeparatorTokenName($fieldToken),
            'recordSeparatorTokenCode' => $recordToken,
            'recordSeparatorToken' => $this->legacyDocMailMergeSeparatorTokenName($recordToken),
            'fnpi' => $fnpi,
            'referenceTypeCode' => $referenceTypeCode,
            'referenceType' => $this->legacyDocExternalFileReferenceType($referenceTypeCode),
            'documentIndex' => $documentIndex,
            'canExposeBytes' => false,
            'extractionPolicy' => 'metadata-only-native-review',
        ];
        if ($source === 'none') {
            if ($flags !== 0 || $fieldToken !== 0 || $recordToken !== 0 || $fnpi !== 0) {
                throw new \RuntimeException('Legacy DOC Pms empty source record contains active metadata');
            }

            return $record;
        }

        $reference = $this->legacyDocMailMergeExternalReference($externalFileReferences, $fnpi);
        if ($reference === null && (($flags & 0x01) !== 0 || $source === 'data-file')) {
            throw new \RuntimeException('Legacy DOC Pms source record references a missing external filename entry');
        }
        if ($reference !== null) {
            $path = (string) $reference['path'];
            $record['externalFileReferenceIndex'] = (int) $reference['index'];
            $record['path'] = $path;
            $record['pathKind'] = $this->legacyPathKind($path);
            $record['pathCharacterCount'] = (int) ($reference['pathCharacterCount'] ?? $this->textCharacterLength($path));
            $record['basename'] = (string) ($reference['basename'] ?? $this->legacyPathBasename($path));
            $record['referenceTypeCode'] = (int) $reference['referenceTypeCode'];
            $record['referenceType'] = (string) $reference['referenceType'];
            $record['documentIndex'] = (int) $reference['documentIndex'];
            $record['ichRelative'] = (int) $reference['ichRelative'];
            $record['fileSystem'] = (string) $reference['fileSystem'];
            $record['fileSystemFlags'] = is_array($reference['fileSystemFlags'] ?? null)
                ? array_values(array_map(static fn (mixed $flag): string => (string) $flag, $reference['fileSystemFlags']))
                : [];
            if (isset($reference['relativePath'])) {
                $record['relativePath'] = (string) $reference['relativePath'];
            }
        }

        return $record;
    }

    /**
     * @return array<string,mixed>
     */
    private function legacyDocMailMergeState(int $bits): array
    {
        $flags = [];
        if (($bits & 0x0001) !== 0) {
            $flags[] = 'main-document-selected';
        }
        if (($bits & 0x0002) !== 0) {
            $flags[] = 'data-source-selected';
        }
        if (($bits & 0x0004) !== 0) {
            $flags[] = 'header-file-selected';
        }
        if (($bits & 0x0100) !== 0) {
            $flags[] = 'automatic-label-or-envelope';
        }
        if (($bits & 0x0400) !== 0) {
            $flags[] = 'suppress-blank-lines';
        }
        if (($bits & 0x0800) !== 0) {
            $flags[] = 'record-selection-enabled';
        }

        $documentTypeCode = ($bits >> 3) & 0x001f;
        $destinationCode = ($bits >> 13) & 0x0007;

        return [
            'flags' => $flags,
            'mainDocumentSelected' => ($bits & 0x0001) !== 0,
            'dataSourceSelected' => ($bits & 0x0002) !== 0,
            'headerFileSelected' => ($bits & 0x0004) !== 0,
            'documentTypeCode' => $documentTypeCode,
            'documentType' => $this->legacyDocMailMergeDocumentType($documentTypeCode),
            'destinationCode' => $destinationCode,
            'destination' => $this->legacyDocMailMergeDestination($destinationCode),
            'automaticLabelOrEnvelope' => ($bits & 0x0100) !== 0,
            'suppressBlankLines' => ($bits & 0x0400) !== 0,
            'recordSelectionEnabled' => ($bits & 0x0800) !== 0,
            'reservedBits' => $bits & 0x1200,
        ];
    }

    private function legacyDocMailMergeDocumentType(int $code): string
    {
        return match ($code) {
            0 => 'normal',
            1 => 'letters',
            2 => 'envelopes',
            4 => 'labels',
            8 => 'catalog',
            default => 'unknown:' . $code,
        };
    }

    private function legacyDocMailMergeDestination(int $code): string
    {
        return match ($code) {
            0 => 'new-document',
            1 => 'printer',
            2 => 'email',
            4 => 'fax',
            default => 'unknown:' . $code,
        };
    }

    private function legacyDocMailMergeSourceName(int $code): string
    {
        return match ($code) {
            0xff => 'none',
            0 => 'data-file',
            1 => 'dde',
            2 => 'odbc',
            3 => 'query-file',
            4 => 'address-book',
            default => 'unknown:' . $code,
        };
    }

    /**
     * @return list<string>
     */
    private function legacyDocMailMergeSourceFlags(int $flags): array
    {
        $names = [];
        if (($flags & 0x01) !== 0) {
            $names[] = 'link-to-filename';
        }
        if (($flags & 0x02) !== 0) {
            $names[] = 'link-to-connection-string';
        }
        if (($flags & 0x04) !== 0) {
            $names[] = 'no-prompt-query';
        }
        if (($flags & 0x08) !== 0) {
            $names[] = 'query';
        }

        return $names;
    }

    private function legacyDocMailMergeSeparatorTokenName(int $code): string
    {
        return match ($code) {
            0 => 'none',
            0x0009 => 'tab',
            0x000a => 'line-feed',
            0x000d => 'carriage-return',
            0x002c => 'comma',
            0x003b => 'semicolon',
            default => 'token:' . $code,
        };
    }

    /**
     * @param list<array<string,mixed>> $externalFileReferences
     * @return array<string,mixed>|null
     */
    private function legacyDocMailMergeExternalReference(array $externalFileReferences, int $fnpi): ?array
    {
        foreach ($externalFileReferences as $reference) {
            if ((int) ($reference['fnpi'] ?? -1) === $fnpi) {
                return $reference;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function legacyDocMailMergeRecordFilter(int $bits): array
    {
        $errorCheckCode = ($bits >> 1) & 0x0003;
        if ($errorCheckCode === 3) {
            throw new \RuntimeException('Legacy DOC Pms record-filter error-check mode uses a reserved value');
        }

        return [
            'raw' => $bits,
            'showDataForm' => ($bits & 0x00000001) !== 0,
            'errorCheckCode' => $errorCheckCode,
            'errorCheckMode' => match ($errorCheckCode) {
                0 => 'none',
                1 => 'simulate',
                2 => 'complete',
            },
            'manualDocumentSetup' => ($bits & 0x00000008) !== 0,
            'mailAsPlainText' => ($bits & 0x00000010) !== 0,
            'defaultSqlQuery' => ($bits & 0x00000040) !== 0,
            'recordFilteringEnabled' => ($bits & 0x00000080) !== 0,
            'sttbfRfsHandle' => ($bits >> 16) & 0xffff,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function routeSlipReport(string $wordDocument, ?string $tableStream): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_ROUTE_SLIP + 4) {
            return [];
        }
        $fcMin = self::u32($wordDocument, 24);
        if ($fcMin > 0 && self::FIB_LCB_ROUTE_SLIP + 4 > $fcMin) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_ROUTE_SLIP);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC route-slip metadata requires the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_ROUTE_SLIP);

        return $this->parseRouteSlip(
            $this->tableStreamSlice($tableStream, $offset, $length, 'RouteSlip routing metadata')
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function parseRouteSlip(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 24) {
            throw new \RuntimeException('Legacy DOC RouteSlip metadata is truncated');
        }

        $cursor = 0;
        $routed = self::u16($bytes, $cursor) !== 0;
        $cursor += 2;
        $returnOriginal = self::u16($bytes, $cursor) !== 0;
        $cursor += 2;
        $trackStatus = self::u16($bytes, $cursor) !== 0;
        $cursor += 2;
        $dirty = self::u16($bytes, $cursor) !== 0;
        $cursor += 2;
        $protect = self::u16($bytes, $cursor);
        $cursor += 2;
        $stage = self::signed16(self::u16($bytes, $cursor));
        $cursor += 2;
        $deliveryOption = self::signed16(self::u16($bytes, $cursor));
        $cursor += 2;
        $recipientCount = self::signed16(self::u16($bytes, $cursor));
        $cursor += 2;
        if ($recipientCount < 0 || $recipientCount > 1024) {
            throw new \RuntimeException('Legacy DOC RouteSlip metadata contains too many recipients');
        }
        if ($stage < 0 || ($recipientCount > 0 && $stage >= $recipientCount)) {
            throw new \RuntimeException('Legacy DOC RouteSlip metadata contains an invalid routing stage');
        }
        if (!in_array($deliveryOption, [0, 1], true)) {
            throw new \RuntimeException('Legacy DOC RouteSlip metadata contains an invalid delivery option');
        }

        $subject = $this->readRouteSlipAnsiString($bytes, $cursor, $length, 'subject');
        $message = $this->readRouteSlipAnsiString($bytes, $cursor, $length, 'message');
        $status = $this->readRouteSlipAnsiString($bytes, $cursor, $length, 'status');
        $title = $this->readRouteSlipAnsiString($bytes, $cursor, $length, 'title');

        $recipients = [];
        for ($index = 0; $index < $recipientCount; $index++) {
            if ($cursor + 4 > $length) {
                throw new \RuntimeException('Legacy DOC RouteSlip recipient record is truncated');
            }

            $entryIdByteCount = self::signed16(self::u16($bytes, $cursor));
            $cursor += 2;
            $nameByteCount = self::signed16(self::u16($bytes, $cursor));
            $cursor += 2;
            if ($entryIdByteCount < 0 || $entryIdByteCount > 4096) {
                throw new \RuntimeException('Legacy DOC RouteSlip recipient entry-id length is invalid');
            }
            if ($nameByteCount <= 0 || $nameByteCount > 255) {
                throw new \RuntimeException('Legacy DOC RouteSlip recipient name length is invalid');
            }
            if ($cursor + $entryIdByteCount + $nameByteCount > $length) {
                throw new \RuntimeException('Legacy DOC RouteSlip recipient record points outside metadata');
            }

            $entryIdBytes = substr($bytes, $cursor, $entryIdByteCount);
            $cursor += $entryIdByteCount;
            $nameBytes = substr($bytes, $cursor, $nameByteCount);
            $cursor += $nameByteCount;
            $recipients[] = [
                'index' => $index,
                'sourceTable' => 'RouteSlipInfo',
                'sourceEncoding' => 'Windows-1252',
                'name' => $this->decodeWindows1252($nameBytes),
                'entryIdByteCount' => $entryIdByteCount,
                'entryIdHex' => bin2hex($entryIdBytes),
            ];
        }

        if ($cursor !== $length) {
            throw new \RuntimeException('Legacy DOC RouteSlip metadata contains trailing bytes');
        }

        return [
            'sourceTable' => 'RouteSlip',
            'sourceEncoding' => 'Windows-1252',
            'extractionPolicy' => 'metadata-only-native-review',
            'recipientCount' => $recipientCount,
            'recipients' => $recipients,
            'routed' => $routed,
            'returnOriginal' => $returnOriginal,
            'trackStatus' => $trackStatus,
            'dirty' => $dirty,
            'protect' => $protect,
            'stage' => $stage,
            'deliveryOption' => $deliveryOption,
            'deliveryMode' => $deliveryOption === 0 ? 'serial' : 'parallel',
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'title' => $title,
        ];
    }

    private function readRouteSlipAnsiString(string $bytes, int &$cursor, int $length, string $field): string
    {
        if ($cursor + 2 > $length) {
            throw new \RuntimeException('Legacy DOC RouteSlip ' . $field . ' length is truncated');
        }
        $byteLength = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($byteLength > 255) {
            throw new \RuntimeException('Legacy DOC RouteSlip ' . $field . ' exceeds the bounded native reader limit');
        }
        if ($cursor + $byteLength > $length) {
            throw new \RuntimeException('Legacy DOC RouteSlip ' . $field . ' points outside metadata');
        }

        $valueBytes = substr($bytes, $cursor, $byteLength);
        $cursor += $byteLength;

        return $byteLength === 0 ? '' : $this->decodeWindows1252($valueBytes);
    }

    private function legacyPathBasename(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $path);
        $slash = strrpos($normalized, '/');

        return $slash === false ? $path : substr($normalized, $slash + 1);
    }

    private function legacyPathKind(string $path): string
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $path) === 1 ? 'external-url' : 'file-path';
    }

    /**
     * @return array<string,mixed>
     */
    private function parseDop(string $bytes): array
    {
        $byteCount = strlen($bytes);
        if ($byteCount < 84) {
            throw new \RuntimeException('Legacy DOC DOP document properties are truncated');
        }
        if (self::u16($bytes, 18) !== 0) {
            throw new \RuntimeException('Legacy DOC DOP document properties contain nonzero reserved wSpare2');
        }

        $flags1 = self::u32($bytes, 0);
        $flags2 = self::u32($bytes, 4);
        $flags3 = self::u32($bytes, 52);
        if (($flags3 & (1 << 30)) !== 0) {
            throw new \RuntimeException('Legacy DOC DOP document properties contain nonzero reserved form-data flag');
        }

        $revisionNumber = self::signed16(self::u16($bytes, 32));
        if ($revisionNumber < 0) {
            throw new \RuntimeException('Legacy DOC DOP document properties contain an invalid revision count');
        }

        $viewFlags = self::u16($bytes, 82);
        $statistics = [
            'wordCount' => self::signed32(self::u32($bytes, 38)),
            'characterCount' => self::signed32(self::u32($bytes, 42)),
            'pageCount' => self::signed16(self::u16($bytes, 46)),
            'paragraphCount' => self::signed32(self::u32($bytes, 48)),
            'lineCount' => self::signed32(self::u32($bytes, 56)),
            'wordCountWithSubdocuments' => self::signed32(self::u32($bytes, 60)),
            'characterCountWithSubdocuments' => self::signed32(self::u32($bytes, 64)),
            'pageCountWithSubdocuments' => self::signed16(self::u16($bytes, 68)),
            'paragraphCountWithSubdocuments' => self::signed32(self::u32($bytes, 70)),
            'lineCountWithSubdocuments' => self::signed32(self::u32($bytes, 74)),
        ];

        $properties = [
            'byteCount' => $byteCount,
            'baseByteCount' => 84,
            'policyFlags' => $this->documentPropertyFlagNames($flags1, $flags2, $flags3, $viewFlags),
            'footnotePlacement' => $this->dopFootnotePlacement(($flags1 >> 5) & 0x03),
            'footnoteNumberingRestart' => $this->dopNumberingRestart(($flags1 >> 16) & 0x03),
            'footnoteStartingNumber' => ($flags1 >> 18) & 0x3fff,
            'compatibilityOptions' => self::u16($bytes, 8),
            'compatibilityOptionFlags' => $this->dopCompatibilityOptionFlagNames(self::u16($bytes, 8)),
            'defaultTabStopTwips' => self::u16($bytes, 10),
            'htmlCodePage' => self::u16($bytes, 12),
            'hyphenationZoneTwips' => self::u16($bytes, 14),
            'consecutiveHyphenLimit' => self::u16($bytes, 16),
            'createdAt' => $this->readDttm($bytes, 20),
            'revisedAt' => $this->readDttm($bytes, 24),
            'lastPrintedAt' => $this->readDttm($bytes, 28),
            'revisionNumber' => $revisionNumber,
            'editMinutes' => self::signed32(self::u32($bytes, 34)),
            'statistics' => $statistics,
            'endnoteNumberingRestart' => $this->dopNumberingRestart($flags3 & 0x03),
            'endnoteStartingNumber' => ($flags3 >> 2) & 0x3fff,
            'endnotePlacement' => $this->dopEndnotePlacement(($flags3 >> 16) & 0x03),
            'protectionHash' => sprintf('%08x', self::u32($bytes, 78)),
            'view' => [
                'kind' => $this->dopViewKind($viewFlags & 0x07),
                'zoomPercent' => ($viewFlags >> 3) & 0x01ff,
                'zoomKind' => $this->dopZoomKind(($viewFlags >> 12) & 0x03),
                'gutterAtTop' => (($viewFlags >> 15) & 0x01) === 1,
            ],
        ];
        if ($byteCount > 84) {
            $properties['extendedByteCount'] = $byteCount - 84;
        }

        return $properties;
    }

    /**
     * @return list<string>
     */
    private function dopCompatibilityOptionFlagNames(int $flags): array
    {
        $names = [];
        foreach ([
            0 => 'no-tab-hanging-indent',
            1 => 'no-space-raise-lower',
            2 => 'suppress-space-before-after-page-break',
            3 => 'wrap-trailing-spaces',
            4 => 'print-color-as-black',
            5 => 'no-column-balance',
            6 => 'convert-mail-merge-escapes',
            7 => 'suppress-top-spacing',
            8 => 'single-border-for-contiguous-cells',
            10 => 'show-breaks-in-frames',
            11 => 'swap-borders-facing-pages',
            12 => 'leave-backslash-alone',
            13 => 'expand-shift-return',
            14 => 'underline-trailing-spaces',
            15 => 'balance-single-byte-double-byte-width',
        ] as $bit => $name) {
            if (($flags & (1 << $bit)) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function documentPropertyFlagNames(int $flags1, int $flags2, int $flags3, int $viewFlags): array
    {
        $names = [];
        foreach ([
            [0, 'facing-pages'],
            [2, 'mail-merge-main-document'],
        ] as [$bit, $name]) {
            if (($flags1 & (1 << $bit)) !== 0) {
                $names[] = $name;
            }
        }
        foreach ([
            [6, 'spelling-checked'],
            [7, 'spelling-clean'],
            [8, 'spelling-errors-hidden'],
            [9, 'grammar-errors-hidden'],
            [10, 'label-document'],
            [11, 'hyphenate-caps'],
            [12, 'auto-hyphenation'],
            [13, 'form-no-fields'],
            [14, 'link-styles'],
            [15, 'track-revisions'],
            [17, 'exact-word-counts'],
            [20, 'comments-locked'],
            [21, 'mirror-margins'],
            [22, 'word97-compatibility'],
            [25, 'forms-protection-enabled'],
            [26, 'display-form-field-selection'],
            [27, 'revision-markup-view'],
            [28, 'print-revision-markup'],
            [29, 'vba-project-locked'],
            [30, 'revisions-locked'],
            [31, 'embed-fonts'],
        ] as [$bit, $name]) {
            if (($flags2 & (1 << $bit)) !== 0) {
                $names[] = $name;
            }
        }
        foreach ([
            [26, 'print-form-data-only'],
            [27, 'save-form-data-only'],
            [28, 'shade-form-fields'],
            [29, 'shade-merge-fields'],
            [31, 'include-subdocuments-in-statistics'],
        ] as [$bit, $name]) {
            if (($flags3 & (1 << $bit)) !== 0) {
                $names[] = $name;
            }
        }
        if (($viewFlags & (1 << 15)) !== 0) {
            $names[] = 'gutter-at-top';
        }

        return $names;
    }

    private function dopFootnotePlacement(int $value): string
    {
        return [
            0 => 'section-end',
            1 => 'page-bottom',
            2 => 'beneath-text',
            3 => 'document-end',
        ][$value];
    }

    private function dopNumberingRestart(int $value): string
    {
        return [
            0 => 'continuous',
            1 => 'section',
            2 => 'page',
            3 => 'manual',
        ][$value];
    }

    private function dopEndnotePlacement(int $value): string
    {
        return [
            0 => 'section-end',
            1 => 'page-bottom',
            2 => 'beneath-text',
            3 => 'document-end',
        ][$value];
    }

    private function dopViewKind(int $value): string
    {
        return [
            0 => 'none',
            1 => 'print',
            2 => 'outline',
            3 => 'master-pages',
            4 => 'normal',
            5 => 'web',
            6 => 'reserved',
            7 => 'reserved',
        ][$value];
    }

    private function dopZoomKind(int $value): string
    {
        return [
            0 => 'none',
            1 => 'full-page',
            2 => 'best-fit',
            3 => 'text-fit',
        ][$value];
    }

    private function readDttm(string $bytes, int $offset): ?string
    {
        return $this->readDttmFromValue(
            self::u32($bytes, $offset),
            'Legacy DOC DOP document properties contain an invalid DTTM timestamp'
        );
    }

    private function readDttmFromValue(int $value, string $invalidMessage): ?string
    {
        if ($value === 0) {
            return null;
        }

        $minute = $value & 0x3f;
        $hour = ($value >> 6) & 0x1f;
        $day = ($value >> 11) & 0x1f;
        $month = ($value >> 16) & 0x0f;
        $year = (($value >> 20) & 0x01ff) + 1900;
        if ($minute > 59 || $hour > 23 || $day < 1 || $day > 31 || $month < 1 || $month > 12) {
            throw new \RuntimeException($invalidMessage);
        }

        return sprintf('%04d-%02d-%02dT%02d:%02d:00', $year, $month, $day, $hour, $minute);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function associatedStringReport(string $wordDocument, ?string $tableStream): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_STTBF_ASSOC + 4) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_STTBF_ASSOC);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC associated strings require the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_STTBF_ASSOC);
        return $this->parseSttbfAssoc($this->tableStreamSlice($tableStream, $offset, $length, 'SttbfAssoc associated string table'));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseSttbfAssoc(string $bytes): array
    {
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('Legacy DOC associated string table is truncated');
        }
        if (self::u16($bytes, 0) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC associated string table must use extended strings');
        }
        if (self::u16($bytes, 2) !== 18) {
            throw new \RuntimeException('Legacy DOC associated string table must contain 18 strings');
        }
        if (self::u16($bytes, 4) !== 0) {
            throw new \RuntimeException('Legacy DOC associated string table must not contain extra data');
        }

        $cursor = 6;
        $records = [];
        for ($index = 0; $index < 18; $index++) {
            if ($cursor + 2 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC associated string table is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters > 255) {
                throw new \RuntimeException('Legacy DOC associated string table entry exceeds 255 characters');
            }
            if ($index === 0x11 && $characters > 15) {
                throw new \RuntimeException('Legacy DOC write-reservation password exceeds 15 characters');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC associated string table points outside its string data');
            }

            $value = $characters === 0 ? '' : $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
            $role = $this->associationStringRole($index);
            if ($role === null || $value === '') {
                continue;
            }

            if ($role === 'writeReservationPassword') {
                $records[] = [
                    'index' => $index,
                    'role' => $role,
                    'redacted' => true,
                    'characterCount' => $this->textCharacterLength($value),
                ];
                continue;
            }

            $records[] = [
                'index' => $index,
                'role' => $role,
                'value' => $value,
            ];
        }

        if ($cursor !== strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC associated string table contains trailing bytes');
        }

        return $records;
    }

    private function associationStringRole(int $index): ?string
    {
        return match ($index) {
            0x01 => 'associatedTemplatePath',
            0x02 => 'title',
            0x03 => 'subject',
            0x04 => 'keywords',
            0x06 => 'creator',
            0x07 => 'lastModifiedBy',
            0x08 => 'mailMergeDataSource',
            0x09 => 'mailMergeHeaderDocument',
            0x11 => 'writeReservationPassword',
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $metadata
     * @param list<array<string,mixed>> $associatedStrings
     * @return array<string,mixed>
     */
    private function applyAssociatedStringMetadata(array $metadata, array $associatedStrings): array
    {
        foreach ($associatedStrings as $record) {
            $role = (string) ($record['role'] ?? '');
            if ($role === 'writeReservationPassword') {
                $metadata['hasWriteReservationPassword'] = true;
                $metadata['writeReservationPasswordCharacterCount'] = (int) ($record['characterCount'] ?? 0);
                continue;
            }

            $name = $this->associatedStringMetadataName($role);
            if ($name === null || !array_key_exists('value', $record)) {
                continue;
            }
            if (isset($metadata[$name]) && $metadata[$name] !== '') {
                continue;
            }

            $metadata[$name] = (string) $record['value'];
        }

        return $metadata;
    }

    private function associatedStringMetadataName(string $role): ?string
    {
        return match ($role) {
            'associatedTemplatePath' => 'associatedTemplatePath',
            'title' => 'title',
            'subject' => 'subject',
            'keywords' => 'keywords',
            'creator' => 'creator',
            'lastModifiedBy' => 'lastModifiedBy',
            'mailMergeDataSource' => 'mailMergeDataSource',
            'mailMergeHeaderDocument' => 'mailMergeHeaderDocument',
            default => null,
        };
    }

    /**
     * @return list<array{index:int,name:string,characterCount:int}>
     */
    private function commentAuthorReport(string $wordDocument, ?string $tableStream): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_GRPXST_ATN_OWNERS + 4) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_GRPXST_ATN_OWNERS);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC comment author names require the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_GRPXST_ATN_OWNERS);

        return $this->parseGrpXstAtnOwners(
            $this->tableStreamSlice($tableStream, $offset, $length, 'GrpXstAtnOwners comment author names')
        );
    }

    /**
     * @return list<array{index:int,name:string,characterCount:int}>
     */
    private function parseGrpXstAtnOwners(string $bytes): array
    {
        $length = strlen($bytes);
        $cursor = 0;
        $records = [];
        $seenNames = [];
        while ($cursor < $length) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC comment author name table is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters >= 56) {
                throw new \RuntimeException('Legacy DOC comment author name exceeds the 55-character XST limit');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength + 2 > $length) {
                throw new \RuntimeException('Legacy DOC comment author name table points outside its string data');
            }
            if (substr($bytes, $cursor + $byteLength, 2) !== "\0\0") {
                throw new \RuntimeException('Legacy DOC comment author name table entry is missing its Xstz terminator');
            }

            $name = $characters === 0 ? '' : $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength + 2;
            $normalizedName = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if (isset($seenNames[$normalizedName])) {
                throw new \RuntimeException('Legacy DOC comment author name table contains duplicate names');
            }

            $seenNames[$normalizedName] = true;
            $records[] = [
                'index' => count($records),
                'name' => $name,
                'characterCount' => $characters,
                'sourceEncoding' => 'UTF-16LE-Xstz',
                'recordBytes' => 2 + $byteLength + 2,
            ];
            if (count($records) > 0x7fff) {
                throw new \RuntimeException('Legacy DOC comment author name table exceeds the supported entry limit');
            }
        }

        return $records;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function revisionAuthorReport(string $wordDocument, ?string $tableStream): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_STTBF_RMARK + 4) {
            return [];
        }

        $fcMin = self::u32($wordDocument, 24);
        if ($fcMin > 0 && self::FIB_LCB_STTBF_RMARK + 4 > $fcMin) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_STTBF_RMARK);
        if ($length === 0) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC revision author table requires the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_STTBF_RMARK);

        return $this->parseSttbfRMark(
            $this->tableStreamSlice($tableStream, $offset, $length, 'SttbfRMark revision author table')
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseSttbfRMark(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 6) {
            throw new \RuntimeException('Legacy DOC revision author table is truncated');
        }
        if (self::u16($bytes, 0) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC revision author table must use extended strings');
        }

        $count = self::u16($bytes, 2);
        if ($count === 0 || $count > 0x7fff) {
            throw new \RuntimeException('Legacy DOC revision author table must contain between 1 and 32767 names');
        }
        if (self::u16($bytes, 4) !== 0) {
            throw new \RuntimeException('Legacy DOC revision author table must not contain extra data');
        }

        $cursor = 6;
        $records = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC revision author table string length is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters === 0) {
                throw new \RuntimeException('Legacy DOC revision author table contains an empty author name');
            }
            if ($characters > 255) {
                throw new \RuntimeException('Legacy DOC revision author name exceeds the bounded native reader limit');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength > $length) {
                throw new \RuntimeException('Legacy DOC revision author table points outside its string data');
            }

            $name = $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;

            if ($index === 0 && $name !== 'Unknown') {
                throw new \RuntimeException('Legacy DOC revision author table first entry must be Unknown');
            }

            $record = [
                'index' => $index,
                'sourceTable' => 'SttbfRMark',
                'name' => $name,
                'characterCount' => $characters,
                'sourceEncoding' => 'UTF-16LE-STTB',
                'canExposeBytes' => false,
                'extractionPolicy' => 'metadata-only-native-review',
            ];
            if ($index === 0) {
                $record['reservedUnknownAuthor'] = true;
            } else {
                $record['reviewerRole'] = 'revision-author';
            }

            $records[] = $record;
        }
        if ($cursor !== $length) {
            throw new \RuntimeException('Legacy DOC revision author table contains trailing bytes');
        }

        return $records;
    }

    /**
     * @param array<string,mixed> $fib
     * @return list<array<string,mixed>>
     */
    private function captionDefinitionReport(string $wordDocument, ?string $tableStream, array $fib): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_STTBF_CAPTION + 4) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_STTBF_CAPTION);
        if ($length === 0 || ($fib['template'] ?? false) !== true) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC caption definitions require the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_STTBF_CAPTION);

        return $this->parseSttbfCaption(
            $this->tableStreamSlice($tableStream, $offset, $length, 'SttbfCaption caption definition table')
        );
    }

    /**
     * @param array<string,mixed> $fib
     * @param list<array<string,mixed>> $captionDefinitions
     * @return list<array<string,mixed>>
     */
    private function autoCaptionRuleReport(string $wordDocument, ?string $tableStream, array $fib, array $captionDefinitions): array
    {
        if (strlen($wordDocument) < self::FIB_LCB_STTBF_AUTO_CAPTION + 4) {
            return [];
        }

        $length = self::u32($wordDocument, self::FIB_LCB_STTBF_AUTO_CAPTION);
        if ($length === 0 || ($fib['template'] ?? false) !== true) {
            return [];
        }
        if ($tableStream === null) {
            throw new \RuntimeException('Legacy DOC AutoCaption rules require the selected table stream');
        }

        $offset = self::u32($wordDocument, self::FIB_FC_STTBF_AUTO_CAPTION);

        return $this->parseSttbfAutoCaption(
            $this->tableStreamSlice($tableStream, $offset, $length, 'SttbfAutoCaption rule table'),
            $captionDefinitions
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseSttbfCaption(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 6) {
            throw new \RuntimeException('Legacy DOC caption definition table is truncated');
        }
        if (self::u16($bytes, 0) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC caption definition table must use extended strings');
        }

        $count = self::u16($bytes, 2);
        if ($count > 1024) {
            throw new \RuntimeException('Legacy DOC caption definition table contains too many captions');
        }
        if (self::u16($bytes, 4) !== 6) {
            throw new \RuntimeException('Legacy DOC caption definition table must contain 6-byte CAPI records');
        }

        $cursor = 6;
        $records = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC caption label length is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters === 0 || $characters > 40) {
                throw new \RuntimeException('Legacy DOC caption labels must contain between 1 and 40 characters');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength + 6 > $length) {
                throw new \RuntimeException('Legacy DOC caption definition table points outside its string or CAPI data');
            }

            $label = $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
            $record = $this->parseCaptionCapi(substr($bytes, $cursor, 6), $index, $label, $characters);
            $cursor += 6;

            $records[] = $record;
        }
        if ($cursor !== $length) {
            throw new \RuntimeException('Legacy DOC caption definition table contains trailing bytes');
        }

        return $records;
    }

    /**
     * @return array<string,mixed>
     */
    private function parseCaptionCapi(string $bytes, int $index, string $label, int $characters): array
    {
        if (strlen($bytes) !== 6) {
            throw new \RuntimeException('Legacy DOC caption CAPI record is truncated');
        }

        $flags = self::u16($bytes, 0);
        $insertLocationCode = $flags & 0x0003;
        if (!in_array($insertLocationCode, [0, 1], true)) {
            throw new \RuntimeException('Legacy DOC caption CAPI insert location is invalid');
        }

        $includeChapterNumber = (($flags >> 2) & 0x0001) === 1;
        $headingLevel = ($flags >> 3) & 0x000f;
        $noLabel = (($flags >> 15) & 0x0001) === 1;
        $numberFormatCode = self::u16($bytes, 2);
        if (!$this->isKnownMsonfc($numberFormatCode)) {
            throw new \RuntimeException('Legacy DOC caption CAPI number format is not a known MSONFC value');
        }

        $separatorCode = self::u16($bytes, 4);
        $record = [
            'index' => $index,
            'sourceTable' => 'SttbfCaption',
            'label' => $label,
            'labelCharacterCount' => $characters,
            'insertLocationCode' => $insertLocationCode,
            'insertLocation' => $insertLocationCode === 0 ? 'below-selected-item' : 'above-selected-item',
            'includeLabel' => !$noLabel,
            'includeChapterNumber' => $includeChapterNumber,
            'numberFormatCode' => $numberFormatCode,
            'numberFormat' => $this->msonfcNumberFormat($numberFormatCode),
            'canExposeBytes' => false,
            'extractionPolicy' => 'metadata-only-native-review',
        ];
        if ($noLabel) {
            $record['noLabel'] = true;
        }
        if ($includeChapterNumber) {
            if ($headingLevel < 1 || $headingLevel > 9) {
                throw new \RuntimeException('Legacy DOC caption CAPI chapter heading level is invalid');
            }
            if (!$this->isValidCaptionSeparatorCode($separatorCode)) {
                throw new \RuntimeException('Legacy DOC caption CAPI chapter separator is invalid');
            }

            $record['headingLevel'] = $headingLevel;
            $record['chapterSeparatorCode'] = $separatorCode;
            $record['chapterSeparator'] = $this->captionSeparatorName($separatorCode);
            $record['chapterSeparatorCharacter'] = $this->captionSeparatorCharacter($separatorCode);
        }

        return $record;
    }

    /**
     * @param list<array<string,mixed>> $captionDefinitions
     * @return list<array<string,mixed>>
     */
    private function parseSttbfAutoCaption(string $bytes, array $captionDefinitions): array
    {
        $length = strlen($bytes);
        if ($length < 6) {
            throw new \RuntimeException('Legacy DOC AutoCaption table is truncated');
        }
        if (self::u16($bytes, 0) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC AutoCaption table must use extended strings');
        }

        $count = self::u16($bytes, 2);
        if ($count > 1024) {
            throw new \RuntimeException('Legacy DOC AutoCaption table contains too many rules');
        }
        if (self::u16($bytes, 4) !== 2) {
            throw new \RuntimeException('Legacy DOC AutoCaption table must contain 2-byte caption indexes');
        }

        $cursor = 6;
        $records = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC AutoCaption ProgID length is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters === 0 || $characters > 255) {
                throw new \RuntimeException('Legacy DOC AutoCaption ProgID must contain between 1 and 255 characters');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength + 2 > $length) {
                throw new \RuntimeException('Legacy DOC AutoCaption table points outside its ProgID or index data');
            }

            $progId = $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
            $captionIndex = self::u16($bytes, $cursor);
            $cursor += 2;
            if (!array_key_exists($captionIndex, $captionDefinitions)) {
                throw new \RuntimeException('Legacy DOC AutoCaption table references an unknown caption definition');
            }

            $caption = $captionDefinitions[$captionIndex];
            $record = [
                'index' => $index,
                'sourceTable' => 'SttbfAutoCaption',
                'progId' => $progId,
                'progIdCharacterCount' => $characters,
                'captionIndex' => $captionIndex,
                'captionLabel' => (string) ($caption['label'] ?? ''),
                'captionInsertLocation' => (string) ($caption['insertLocation'] ?? ''),
                'captionNumberFormat' => (string) ($caption['numberFormat'] ?? ''),
                'canExposeBytes' => false,
                'extractionPolicy' => 'metadata-only-native-review',
            ];
            if (isset($caption['headingLevel'])) {
                $record['captionHeadingLevel'] = (int) $caption['headingLevel'];
            }
            if (isset($caption['chapterSeparator'])) {
                $record['captionChapterSeparator'] = (string) $caption['chapterSeparator'];
            }

            $records[] = $record;
        }
        if ($cursor !== $length) {
            throw new \RuntimeException('Legacy DOC AutoCaption table contains trailing bytes');
        }

        return $records;
    }

    private function isKnownMsonfc(int $numberFormatCode): bool
    {
        return ($numberFormatCode >= 0x00 && $numberFormatCode <= 0x3b)
            || $numberFormatCode === 0xff;
    }

    private function msonfcNumberFormat(int $numberFormatCode): string
    {
        return [
            0x00 => 'decimal',
            0x01 => 'upperRoman',
            0x02 => 'lowerRoman',
            0x03 => 'upperLetter',
            0x04 => 'lowerLetter',
            0x05 => 'ordinal',
            0x06 => 'cardinalText',
            0x07 => 'ordinalText',
            0x08 => 'hex',
            0x16 => 'decimalZero',
            0x17 => 'bullet',
            0xff => 'none',
        ][$numberFormatCode] ?? sprintf('msonfc-0x%02x', $numberFormatCode);
    }

    private function isValidCaptionSeparatorCode(int $separatorCode): bool
    {
        return in_array($separatorCode, [0x001e, 0x002e, 0x003a, 0x2013, 0x2014], true);
    }

    private function captionSeparatorName(int $separatorCode): string
    {
        return [
            0x001e => 'hyphen',
            0x002e => 'period',
            0x003a => 'colon',
            0x2013 => 'en-dash',
            0x2014 => 'em-dash',
        ][$separatorCode];
    }

    private function captionSeparatorCharacter(int $separatorCode): string
    {
        return match ($separatorCode) {
            0x001e => '-',
            0x002e => '.',
            0x003a => ':',
            0x2013, 0x2014 => self::codepointToUtf8($separatorCode),
        };
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function streamDirectoryReport(CompoundFileBinary $compoundFile): array
    {
        $streams = [];
        foreach ($compoundFile->entries() as $entry) {
            if (($entry['type'] ?? null) !== 2) {
                continue;
            }

            $path = (string) ($entry['path'] ?? '');
            $slash = strrpos($path, '/');
            $stream = [
                'path' => $path,
                'name' => (string) ($entry['name'] ?? ''),
                'storagePath' => $slash === false ? '' : substr($path, 0, $slash),
                'bytes' => (int) ($entry['size'] ?? 0),
                'directoryId' => (int) ($entry['directoryId'] ?? 0),
            ];

            if (($entry['createdAt'] ?? null) !== null) {
                $stream['createdAt'] = (string) $entry['createdAt'];
            }
            if (($entry['modifiedAt'] ?? null) !== null) {
                $stream['modifiedAt'] = (string) $entry['modifiedAt'];
            }
            if (isset($entry['ignoredStreamSizeHighDword'])) {
                $stream['ignoredStreamSizeHighDword'] = (int) $entry['ignoredStreamSizeHighDword'];
            }

            $streams[] = $stream;
        }

        return $streams;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function directoryEntryReport(CompoundFileBinary $compoundFile): array
    {
        $entries = [];
        foreach ($compoundFile->entries() as $entry) {
            $path = (string) ($entry['path'] ?? '');
            $typeId = (int) ($entry['type'] ?? 0);
            $record = [
                'path' => $path,
                'name' => (string) ($entry['name'] ?? ''),
                'type' => match ($typeId) {
                    1 => 'storage',
                    2 => 'stream',
                    5 => 'root',
                    default => 'unknown',
                },
                'bytes' => (int) ($entry['size'] ?? 0),
                'directoryId' => (int) ($entry['directoryId'] ?? 0),
            ];

            if (($entry['createdAt'] ?? null) !== null) {
                $record['createdAt'] = (string) $entry['createdAt'];
            }
            if (($entry['modifiedAt'] ?? null) !== null) {
                $record['modifiedAt'] = (string) $entry['modifiedAt'];
            }
            if (($entry['clsid'] ?? null) !== null) {
                $record['clsid'] = (string) $entry['clsid'];
            }
            if (($entry['stateBits'] ?? null) !== null) {
                $record['stateBits'] = (int) $entry['stateBits'];
            }
            if (isset($entry['ignoredStreamSizeHighDword'])) {
                $record['ignoredStreamSizeHighDword'] = (int) $entry['ignoredStreamSizeHighDword'];
            }

            $entries[] = $record;
        }

        return $entries;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function embeddedObjectReport(CompoundFileBinary $compoundFile): array
    {
        $objects = [];
        foreach ($compoundFile->entries() as $entry) {
            if ($entry['type'] !== 2) {
                continue;
            }

            $path = (string) $entry['path'];
            $segments = explode('/', $path);
            if (count($segments) < 3 || strtolower($segments[0]) !== 'objectpool') {
                continue;
            }

            $objectId = $segments[1];
            if ($objectId === '') {
                continue;
            }

            $storagePath = $segments[0] . '/' . $objectId;
            $streamName = implode('/', array_slice($segments, 2));
            $role = $this->embeddedObjectStreamRole($streamName);
            $stream = [
                'path' => $path,
                'name' => $streamName,
                'role' => $role,
                'bytes' => (int) $entry['size'],
                'canExposeBytes' => false,
            ];

            $objects[$storagePath] ??= [
                'storagePath' => $storagePath,
                'objectId' => $objectId,
                'streamCount' => 0,
                'totalBytes' => 0,
                'hasNativeData' => false,
                'hasPresentationData' => false,
                'canExposeBytes' => false,
                'streams' => [],
            ];
            $objects[$storagePath]['streamCount']++;
            $objects[$storagePath]['totalBytes'] += (int) $entry['size'];
            $objects[$storagePath]['hasNativeData'] = $objects[$storagePath]['hasNativeData'] || $role === 'native-data';
            $objects[$storagePath]['hasPresentationData'] = $objects[$storagePath]['hasPresentationData'] || $role === 'presentation-data';

            if ($role === 'object-info') {
                $format = $this->embeddedObjectInfoFormat($compoundFile->readStream($path));
                if ($format !== null) {
                    $objects[$storagePath]['transmissionFormat'] = $format;
                }
            }
            if ($role === 'compound-object') {
                $compoundObject = $this->embeddedCompoundObjectMetadata($compoundFile->readStream($path));
                $stream['compoundObject'] = $compoundObject;
                if (($compoundObject['displayName'] ?? '') !== '') {
                    $objects[$storagePath]['compoundObjectDisplayNames'][] = (string) $compoundObject['displayName'];
                }
                if (is_array($compoundObject['clipboardFormat'] ?? null)) {
                    $clipboardFormat = $compoundObject['clipboardFormat'];
                    if (($clipboardFormat['name'] ?? '') !== '') {
                        $objects[$storagePath]['compoundObjectClipboardFormats'][] = (string) $clipboardFormat['name'];
                    } elseif (isset($clipboardFormat['code'])) {
                        $objects[$storagePath]['compoundObjectClipboardFormats'][] = 'standard:' . (string) $clipboardFormat['code'];
                    }
                }
                foreach (($compoundObject['diagnostics'] ?? []) as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }
                    $objects[$storagePath]['diagnostics'][] = ['stream' => $path] + $diagnostic;
                }
            }
            if ($role === 'native-data') {
                $oleNative = $this->embeddedOleNativeMetadata($compoundFile->readStream($path));
                $stream['oleNative'] = $oleNative;
                if (($oleNative['label'] ?? '') !== '') {
                    $objects[$storagePath]['nativeLabels'][] = (string) $oleNative['label'];
                }
                if (($oleNative['sourcePath'] ?? '') !== '') {
                    $objects[$storagePath]['nativeSourcePaths'][] = (string) $oleNative['sourcePath'];
                }
                if (($oleNative['temporaryPath'] ?? '') !== '') {
                    $objects[$storagePath]['nativeTemporaryPaths'][] = (string) $oleNative['temporaryPath'];
                }
                if (isset($oleNative['nativeDataBytes']) && is_int($oleNative['nativeDataBytes'])) {
                    $objects[$storagePath]['nativeDataBytes'] = (int) ($objects[$storagePath]['nativeDataBytes'] ?? 0)
                        + $oleNative['nativeDataBytes'];
                }
                foreach (($oleNative['diagnostics'] ?? []) as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }
                    $objects[$storagePath]['diagnostics'][] = ['stream' => $path] + $diagnostic;
                }
            }

            $objects[$storagePath]['streams'][] = $stream;
        }

        $result = array_values($objects);
        foreach ($result as &$object) {
            foreach (['compoundObjectDisplayNames', 'compoundObjectClipboardFormats', 'nativeLabels', 'nativeSourcePaths', 'nativeTemporaryPaths'] as $field) {
                if (!isset($object[$field]) || !is_array($object[$field])) {
                    continue;
                }
                $object[$field] = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $object[$field]
                )));
            }
            usort(
                $object['streams'],
                static fn (array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path'])
            );
        }
        unset($object);
        usort(
            $result,
            static fn (array $left, array $right): int => strcmp((string) $left['storagePath'], (string) $right['storagePath'])
        );

        return $result;
    }

    private function embeddedObjectStreamRole(string $streamName): string
    {
        return match (true) {
            $streamName === "\x01Ole10Native" => 'native-data',
            $streamName === "\x01CompObj" => 'compound-object',
            $streamName === "\x03ObjInfo" => 'object-info',
            $streamName === "\x03EPRINT" => 'print-presentation',
            str_starts_with($streamName, "\x02OlePres") => 'presentation-data',
            default => 'private-data',
        };
    }

    /**
     * @return array{code:int,name:string}|null
     */
    private function embeddedObjectInfoFormat(string $bytes): ?array
    {
        if (strlen($bytes) < 4) {
            return null;
        }

        $code = self::u16($bytes, 2);

        return [
            'code' => $code,
            'name' => match ($code) {
                0x0001 => 'rtf',
                0x0002 => 'text',
                0x0003 => 'metafile',
                0x0004 => 'bitmap',
                0x0005 => 'dib',
                0x000a => 'html',
                0x0014 => 'unicode-text',
                default => 'unknown',
            },
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function embeddedCompoundObjectMetadata(string $bytes): array
    {
        $metadata = ['canExposeBytes' => false];
        $diagnostics = [];
        $length = strlen($bytes);
        if ($length < 28) {
            $metadata['diagnostics'] = [[
                'code' => 'truncated-compobj-header',
                'message' => 'CompObj stream is too short to contain the 28-byte header',
            ]];

            return $metadata;
        }

        $cursor = 28;
        $ansiUserType = $this->readLengthPrefixedAnsiString($bytes, $cursor);
        if ($ansiUserType === null) {
            $metadata['diagnostics'] = [[
                'code' => 'truncated-compobj-ansi-user-type',
                'message' => 'CompObj stream is missing the ANSI display name',
            ]];

            return $metadata;
        }
        if ($ansiUserType !== '') {
            $metadata['ansiUserType'] = $ansiUserType;
            $metadata['displayName'] = $ansiUserType;
        }

        $ansiClipboard = $this->readClipboardFormatOrAnsiString($bytes, $cursor, $diagnostics);
        if ($ansiClipboard !== null) {
            $metadata['ansiClipboardFormat'] = $ansiClipboard;
            $metadata['clipboardFormat'] = $ansiClipboard;
        } elseif ($diagnostics !== []) {
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }

        if ($cursor >= $length) {
            if ($diagnostics !== []) {
                $metadata['diagnostics'] = $diagnostics;
            }

            return $metadata;
        }

        $reserved = $this->readLengthPrefixedAnsiStringWithSize($bytes, $cursor);
        if ($reserved === null) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-reserved-ansi',
                'message' => 'CompObj stream has a truncated reserved ANSI string',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        $cursor += $reserved['bytes'];
        if ($reserved['declaredCharacters'] === 0 || $reserved['declaredCharacters'] > 0x28 || $cursor + 4 > $length) {
            if ($diagnostics !== []) {
                $metadata['diagnostics'] = $diagnostics;
            }

            return $metadata;
        }

        $unicodeMarker = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($unicodeMarker !== 0x71b239f4) {
            $metadata['unicodeMarker'] = $unicodeMarker;
            if ($diagnostics !== []) {
                $metadata['diagnostics'] = $diagnostics;
            }

            return $metadata;
        }
        $metadata['unicodeMarker'] = $unicodeMarker;

        $unicodeUserType = $this->readLengthPrefixedUnicodeString($bytes, $cursor);
        if ($unicodeUserType === null) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-unicode-user-type',
                'message' => 'CompObj stream has a truncated Unicode display name',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        if ($unicodeUserType !== '') {
            $metadata['unicodeUserType'] = $unicodeUserType;
            $metadata['displayName'] = $unicodeUserType;
        }

        $unicodeClipboard = $this->readClipboardFormatOrUnicodeString($bytes, $cursor, $diagnostics);
        if ($unicodeClipboard !== null) {
            $metadata['unicodeClipboardFormat'] = $unicodeClipboard;
            $metadata['clipboardFormat'] = $unicodeClipboard;
        }

        if ($diagnostics !== []) {
            $metadata['diagnostics'] = $diagnostics;
        }

        return $metadata;
    }

    private function readLengthPrefixedAnsiString(string $bytes, int &$cursor): ?string
    {
        $value = $this->readLengthPrefixedAnsiStringWithSize($bytes, $cursor);
        if ($value === null) {
            return null;
        }

        $cursor += $value['bytes'];

        return $value['value'];
    }

    /**
     * @return array{value:string,bytes:int,declaredCharacters:int}|null
     */
    private function readLengthPrefixedAnsiStringWithSize(string $bytes, int $offset): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $characters = self::u32($bytes, $offset);
        if ($characters === 0) {
            return [
                'value' => '',
                'bytes' => 4,
                'declaredCharacters' => 0,
            ];
        }
        if ($characters > 0x100000 || $offset + 4 + $characters > strlen($bytes)) {
            return null;
        }

        $raw = substr($bytes, $offset + 4, $characters);
        $value = $this->decodeCodePageString($raw, 1252);
        $clean = preg_replace('/[\x00-\x08\x0e-\x1f]/u', '', $value);

        return [
            'value' => is_string($clean) ? $clean : $value,
            'bytes' => 4 + $characters,
            'declaredCharacters' => $characters,
        ];
    }

    private function readLengthPrefixedUnicodeString(string $bytes, int &$cursor): ?string
    {
        if ($cursor + 4 > strlen($bytes)) {
            return null;
        }

        $byteLength = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($byteLength === 0) {
            return '';
        }
        if (($byteLength % 2) !== 0 || $byteLength > 0x200000 || $cursor + $byteLength > strlen($bytes)) {
            return null;
        }

        $value = rtrim($this->decodeUtf16Le(substr($bytes, $cursor, $byteLength)), "\0");
        $cursor += $byteLength;

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $diagnostics
     * @return array<string,mixed>|null
     */
    private function readClipboardFormatOrAnsiString(string $bytes, int &$cursor, array &$diagnostics): ?array
    {
        if ($cursor + 4 > strlen($bytes)) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-ansi-clipboard-format',
                'message' => 'CompObj stream is missing the ANSI clipboard format marker',
            ];

            return null;
        }

        $marker = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($marker === 0) {
            return [
                'kind' => 'none',
            ];
        }
        if ($marker === 0xffffffff || $marker === 0xfffffffe) {
            if ($cursor + 4 > strlen($bytes)) {
                $diagnostics[] = [
                    'code' => 'truncated-compobj-ansi-standard-clipboard-format',
                    'message' => 'CompObj stream has a truncated ANSI standard clipboard format identifier',
                ];

                return null;
            }

            $code = self::u32($bytes, $cursor);
            $cursor += 4;

            return $this->standardClipboardFormat($code);
        }
        if ($marker > 0x190 || $cursor + $marker > strlen($bytes)) {
            $diagnostics[] = [
                'code' => 'invalid-compobj-ansi-clipboard-format',
                'message' => 'CompObj ANSI registered clipboard format length is invalid',
                'declaredCharacters' => $marker,
            ];

            return null;
        }

        $value = $this->decodeCodePageString(substr($bytes, $cursor, $marker), 1252);
        $cursor += $marker;

        return [
            'kind' => 'registered',
            'name' => $value,
        ];
    }

    /**
     * @param list<array<string,mixed>> $diagnostics
     * @return array<string,mixed>|null
     */
    private function readClipboardFormatOrUnicodeString(string $bytes, int &$cursor, array &$diagnostics): ?array
    {
        if ($cursor + 4 > strlen($bytes)) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-unicode-clipboard-format',
                'message' => 'CompObj stream is missing the Unicode clipboard format marker',
            ];

            return null;
        }

        $marker = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($marker === 0) {
            return [
                'kind' => 'none',
            ];
        }
        if ($marker === 0xffffffff || $marker === 0xfffffffe) {
            if ($cursor + 4 > strlen($bytes)) {
                $diagnostics[] = [
                    'code' => 'truncated-compobj-unicode-standard-clipboard-format',
                    'message' => 'CompObj stream has a truncated Unicode standard clipboard format identifier',
                ];

                return null;
            }

            $code = self::u32($bytes, $cursor);
            $cursor += 4;

            return $this->standardClipboardFormat($code);
        }
        if ($marker > 0x190) {
            $diagnostics[] = [
                'code' => 'invalid-compobj-unicode-clipboard-format',
                'message' => 'CompObj Unicode registered clipboard format length is invalid',
                'declaredCharacters' => $marker,
            ];

            return null;
        }

        $byteLength = $marker * 2;
        if ($cursor + $byteLength > strlen($bytes)) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-unicode-clipboard-format-name',
                'message' => 'CompObj Unicode registered clipboard format name is truncated',
                'declaredCharacters' => $marker,
            ];

            return null;
        }

        $value = rtrim($this->decodeUtf16Le(substr($bytes, $cursor, $byteLength)), "\0");
        $cursor += $byteLength;

        return [
            'kind' => 'registered',
            'name' => $value,
        ];
    }

    /**
     * @return array{kind:string,code:int,name:string}
     */
    private function standardClipboardFormat(int $code): array
    {
        return [
            'kind' => 'standard',
            'code' => $code,
            'name' => match ($code) {
                1 => 'CF_TEXT',
                2 => 'CF_BITMAP',
                3 => 'CF_METAFILEPICT',
                4 => 'CF_SYLK',
                5 => 'CF_DIF',
                6 => 'CF_TIFF',
                7 => 'CF_OEMTEXT',
                8 => 'CF_DIB',
                9 => 'CF_PALETTE',
                10 => 'CF_PENDATA',
                11 => 'CF_RIFF',
                12 => 'CF_WAVE',
                13 => 'CF_UNICODETEXT',
                14 => 'CF_ENHMETAFILE',
                15 => 'CF_HDROP',
                16 => 'CF_LOCALE',
                17 => 'CF_DIBV5',
                default => 'standard:' . (string) $code,
            },
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function embeddedOleNativeMetadata(string $bytes): array
    {
        $metadata = ['canExposeBytes' => false];
        $diagnostics = [];
        $length = strlen($bytes);
        if ($length < 4) {
            $metadata['diagnostics'] = [[
                'code' => 'truncated-ole-native-size',
                'message' => 'Ole10Native stream is too short to contain the declared payload size',
            ]];

            return $metadata;
        }

        $declaredPayloadBytes = self::u32($bytes, 0);
        $metadata['declaredPayloadBytes'] = $declaredPayloadBytes;
        if ($declaredPayloadBytes > $length - 4) {
            $diagnostics[] = [
                'code' => 'ole-native-declared-size-exceeds-stream',
                'message' => 'Ole10Native declared payload size exceeds the CFB stream length',
                'declaredPayloadBytes' => $declaredPayloadBytes,
                'availablePayloadBytes' => $length - 4,
            ];
        }

        $cursor = 4;
        if ($cursor + 2 > $length) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-flags',
                'message' => 'Ole10Native stream is missing the native flags field',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }

        $metadata['flags'] = self::u16($bytes, $cursor);
        $cursor += 2;

        $label = $this->readOleNativeAnsiString($bytes, $cursor);
        if ($label === null) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-label',
                'message' => 'Ole10Native stream is missing a null-terminated display label',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        if ($label !== '') {
            $metadata['label'] = $label;
        }

        $sourcePath = $this->readOleNativeAnsiString($bytes, $cursor);
        if ($sourcePath === null) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-source-path',
                'message' => 'Ole10Native stream is missing a null-terminated source path',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        if ($sourcePath !== '') {
            $metadata['sourcePath'] = $sourcePath;
        }

        if ($cursor + 4 > $length) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-secondary-flags',
                'message' => 'Ole10Native stream is missing secondary flags before the command path',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }

        $secondaryFlags = self::u16($bytes, $cursor);
        $unknownFlags = self::u16($bytes, $cursor + 2);
        if ($secondaryFlags !== 0) {
            $metadata['secondaryFlags'] = $secondaryFlags;
        }
        if ($unknownFlags !== 0) {
            $metadata['unknownFlags'] = $unknownFlags;
        }
        $cursor += 4;

        $temporaryPath = $this->readOleNativeAnsiString($bytes, $cursor);
        if ($temporaryPath === null) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-temporary-path',
                'message' => 'Ole10Native stream is missing a null-terminated temporary path',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        if ($temporaryPath !== '') {
            $metadata['temporaryPath'] = $temporaryPath;
        }

        if ($cursor + 4 > $length) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-data-size',
                'message' => 'Ole10Native stream is missing the embedded native-data size',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }

        $nativeDataBytes = self::u32($bytes, $cursor);
        $cursor += 4;
        $metadata['nativeDataBytes'] = $nativeDataBytes;
        $availableNativeDataBytes = max(0, $length - $cursor);
        if ($nativeDataBytes > $availableNativeDataBytes) {
            $metadata['availableNativeDataBytes'] = $availableNativeDataBytes;
            $diagnostics[] = [
                'code' => 'ole-native-data-size-exceeds-stream',
                'message' => 'Ole10Native embedded native-data size exceeds the remaining stream bytes',
                'nativeDataBytes' => $nativeDataBytes,
                'availableNativeDataBytes' => $availableNativeDataBytes,
            ];
        }

        if ($diagnostics !== []) {
            $metadata['diagnostics'] = $diagnostics;
        }

        return $metadata;
    }

    private function readOleNativeAnsiString(string $bytes, int &$cursor): ?string
    {
        $end = strpos($bytes, "\0", $cursor);
        if ($end === false) {
            return null;
        }

        $value = $this->decodeCodePageString(substr($bytes, $cursor, $end - $cursor), 1252);
        $cursor = $end + 1;
        $clean = preg_replace('/[\x00-\x08\x0e-\x1f]/u', '', $value);

        return is_string($clean) ? $clean : $value;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function macroProjectReport(CompoundFileBinary $compoundFile): array
    {
        $projects = [];
        foreach ($compoundFile->entries() as $entry) {
            if ($entry['type'] !== 2) {
                continue;
            }

            $path = (string) $entry['path'];
            $segments = explode('/', $path);
            $root = $this->macroProjectRoot($segments);
            if ($root === null) {
                continue;
            }

            $streamName = implode('/', array_slice($segments, 1));
            if ($streamName === '') {
                continue;
            }

            $role = $this->macroProjectStreamRole($streamName);
            $stream = [
                'path' => $path,
                'name' => $streamName,
                'role' => $role,
                'bytes' => (int) $entry['size'],
                'canExposeBytes' => false,
            ];

            $projects[$root] ??= [
                'storagePath' => $root,
                'policy' => 'macro-execution-disabled',
                'canExecute' => false,
                'canExposeBytes' => false,
                'streamCount' => 0,
                'totalBytes' => 0,
                'hasVbaStorage' => false,
                'hasDirStream' => false,
                'hasProjectStream' => false,
                'hasProjectWmStream' => false,
                'hasPerformanceCache' => false,
                'moduleStreams' => [],
                'streams' => [],
            ];

            $projects[$root]['streamCount']++;
            $projects[$root]['totalBytes'] += (int) $entry['size'];
            $projects[$root]['hasVbaStorage'] = $projects[$root]['hasVbaStorage']
                || str_starts_with(strtolower($streamName), 'vba/');
            $projects[$root]['hasDirStream'] = $projects[$root]['hasDirStream']
                || $role === 'vba-dir-compressed';
            $projects[$root]['hasProjectStream'] = $projects[$root]['hasProjectStream']
                || $role === 'project-properties';
            $projects[$root]['hasProjectWmStream'] = $projects[$root]['hasProjectWmStream']
                || $role === 'project-codepage';
            $projects[$root]['hasPerformanceCache'] = $projects[$root]['hasPerformanceCache']
                || $role === 'vba-performance-cache';
            if ($role === 'module-stream') {
                $projects[$root]['moduleStreams'][] = basename($streamName);
            }
            $projects[$root]['streams'][] = $stream;
        }

        $result = array_values($projects);
        foreach ($result as &$project) {
            $moduleStreams = array_values(array_unique(array_map(
                static fn (mixed $name): string => (string) $name,
                $project['moduleStreams']
            )));
            sort($moduleStreams, SORT_STRING);
            $project['moduleStreams'] = $moduleStreams;

            usort(
                $project['streams'],
                static fn (array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path'])
            );

            $diagnostics = [];
            if ($project['hasVbaStorage'] === true && $project['hasDirStream'] !== true) {
                $diagnostics[] = [
                    'code' => 'missing-vba-dir-stream',
                    'message' => 'VBA storage is present but the compressed dir stream was not found',
                ];
            }
            if ($project['hasDirStream'] === true) {
                $diagnostics[] = [
                    'code' => 'compressed-vba-dir-not-expanded',
                    'message' => 'VBA dir stream bytes are retained for reviewer policy only and are not decompressed or executed',
                ];
            }
            $project['diagnostics'] = $diagnostics;
        }
        unset($project);

        usort(
            $result,
            static fn (array $left, array $right): int => strcmp((string) $left['storagePath'], (string) $right['storagePath'])
        );

        return $result;
    }

    /**
     * @param list<string> $segments
     */
    private function macroProjectRoot(array $segments): ?string
    {
        if ($segments === []) {
            return null;
        }

        $root = (string) $segments[0];

        return in_array(strtolower($root), ['macros', '_vba_project_cur'], true) ? $root : null;
    }

    private function macroProjectStreamRole(string $streamName): string
    {
        $normalized = strtolower(str_replace('\\', '/', $streamName));
        $baseName = basename($streamName);
        $lowerBaseName = strtolower($baseName);

        if ($normalized === 'vba/dir') {
            return 'vba-dir-compressed';
        }
        if ($normalized === 'vba/_vba_project') {
            return 'vba-performance-cache';
        }
        if ($streamName === 'PROJECT') {
            return 'project-properties';
        }
        if ($streamName === 'PROJECTwm') {
            return 'project-codepage';
        }
        if (str_starts_with($normalized, 'vba/') && !in_array($lowerBaseName, ['dir', '_vba_project'], true)) {
            return 'module-stream';
        }

        return 'private-macro-data';
    }

    /**
     * @return array<string,mixed>
     */
    private function propertySetMetadata(string $bytes, string $kind): array
    {
        if (strlen($bytes) < 48 || self::u16($bytes, 0) !== 0xfffe) {
            return [];
        }

        $setCount = self::u32($bytes, 24);
        $metadata = [];
        for ($index = 0; $index < $setCount; $index++) {
            $descriptorOffset = 28 + ($index * 20);
            if ($descriptorOffset + 20 > strlen($bytes)) {
                break;
            }
            $fmtid = bin2hex(substr($bytes, $descriptorOffset, 16));
            $propertySetOffset = self::u32($bytes, $descriptorOffset + 16);
            if ($propertySetOffset >= strlen($bytes)) {
                continue;
            }

            $propertySet = $this->readPropertySet($bytes, $propertySetOffset);
            if ($kind === 'document-summary' && $fmtid === self::FMTID_USER_DEFINED_PROPERTIES) {
                $userDefinedMetadata = $this->userDefinedDocumentPropertyMetadata($propertySet['properties'], $propertySet['dictionary']);
                if (
                    isset($userDefinedMetadata['customProperties'], $metadata['customProperties'])
                    && is_array($userDefinedMetadata['customProperties'])
                    && is_array($metadata['customProperties'])
                ) {
                    $userDefinedMetadata['customProperties'] = array_replace(
                        $metadata['customProperties'],
                        $userDefinedMetadata['customProperties']
                    );
                }
                $metadata = array_replace($metadata, $userDefinedMetadata);
                continue;
            }

            $metadata = array_replace($metadata, $this->mapPropertySetValues($propertySet['properties'], $kind));
        }

        return $metadata;
    }

    /**
     * @return array{properties:array<int,mixed>,dictionary:array<int,string>}
     */
    private function readPropertySet(string $bytes, int $offset): array
    {
        if ($offset + 8 > strlen($bytes)) {
            return [
                'properties' => [],
                'dictionary' => [],
            ];
        }

        $propertySetSize = self::u32($bytes, $offset);
        $propertyCount = self::u32($bytes, $offset + 4);
        if ($propertySetSize < 8 || $offset + $propertySetSize > strlen($bytes)) {
            return [
                'properties' => [],
                'dictionary' => [],
            ];
        }

        $entriesOffset = $offset + 8;
        $values = [];
        $codepage = 1252;
        $locations = [];
        $directoryBytes = $propertyCount * 8;
        if ($entriesOffset + $directoryBytes > $offset + $propertySetSize) {
            throw new \RuntimeException('Legacy DOC OLE property-set directory points outside its section');
        }
        $minimumValueOffset = 8 + $directoryBytes;
        for ($index = 0; $index < $propertyCount; $index++) {
            $entryOffset = $entriesOffset + ($index * 8);
            $propertyId = self::u32($bytes, $entryOffset);
            $valueOffset = self::u32($bytes, $entryOffset + 4);
            if (isset($locations[$propertyId])) {
                throw new \RuntimeException('Legacy DOC OLE property-set directory contains duplicate property identifiers');
            }
            if ($valueOffset < $minimumValueOffset || $valueOffset >= $propertySetSize || ($valueOffset % 4) !== 0) {
                return [
                    'properties' => [],
                    'dictionary' => [],
                ];
            }

            $locations[$propertyId] = $offset + $valueOffset;
        }

        if (isset($locations[1])) {
            $codepageValue = $this->readTypedPropertyValue($bytes, $locations[1], $codepage);
            if (is_int($codepageValue)) {
                $codepage = $codepageValue < 0 ? $codepageValue + 0x10000 : $codepageValue;
                $values[1] = $codepage;
            }
        }

        $dictionary = [];
        if (isset($locations[0])) {
            $dictionary = $this->readDictionary($bytes, $locations[0], $codepage, $offset + $propertySetSize);
        }

        foreach ($locations as $propertyId => $valueOffset) {
            if ($propertyId === 0 || $propertyId === 1) {
                continue;
            }
            $dictionaryName = $dictionary[$propertyId] ?? null;
            if (is_string($dictionaryName) && $this->isReservedHyperlinkPropertyName($dictionaryName)) {
                try {
                    $values[$propertyId] = $this->readReservedHyperlinkPropertyValue($dictionaryName, $bytes, $valueOffset);
                } catch (\RuntimeException $exception) {
                    $values[$propertyId] = [
                        'type' => 'malformed-reserved-hyperlink-property',
                        'sourceProperty' => $dictionaryName,
                        'error' => $exception->getMessage(),
                        'extractionPolicy' => 'metadata-only-native-review',
                        'canExposeBytes' => false,
                    ];
                }
                continue;
            }
            $values[$propertyId] = $this->readTypedPropertyValue($bytes, $valueOffset, $codepage);
        }

        return [
            'properties' => $values,
            'dictionary' => $dictionary,
        ];
    }

    private function readTypedPropertyValue(string $bytes, int $offset, int $codepage): mixed
    {
        $typedValue = $this->readTypedPropertyValueWithSize($bytes, $offset, $codepage);

        return $typedValue['value'] ?? null;
    }

    /**
     * @return array{value:mixed,bytes:int}|null
     */
    private function readTypedPropertyValueWithSize(string $bytes, int $offset, int $codepage): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $type = self::u16($bytes, $offset);
        if (self::u16($bytes, $offset + 2) !== 0) {
            throw new \RuntimeException('Legacy DOC OLE property value contains nonzero typed-value padding');
        }
        $valueOffset = $offset + 4;

        return match ($type) {
            0x0002 => $this->readPadded16TypedValue($bytes, $valueOffset, 'i2'),
            0x0003 => $valueOffset + 4 <= strlen($bytes)
                ? ['value' => self::signed32(self::u32($bytes, $valueOffset)), 'bytes' => 8]
                : null,
            0x0004 => $valueOffset + 4 <= strlen($bytes)
                ? ['value' => self::readFloat32($bytes, $valueOffset), 'bytes' => 8]
                : null,
            0x0005 => $valueOffset + 8 <= strlen($bytes)
                ? ['value' => self::readFloat64($bytes, $valueOffset), 'bytes' => 12]
                : null,
            0x0006 => $valueOffset + 8 <= strlen($bytes)
                ? ['value' => self::formatCurrencyScaledInteger(self::signed64($bytes, $valueOffset)), 'bytes' => 12]
                : null,
            0x0007 => $valueOffset + 8 <= strlen($bytes)
                ? ['value' => self::oleAutomationDateIso8601($bytes, $valueOffset), 'bytes' => 12]
                : null,
            0x000b => $this->readPadded16TypedValue($bytes, $valueOffset, 'bool'),
            0x0012 => $this->readPadded16TypedValue($bytes, $valueOffset, 'ui2'),
            0x0013 => $valueOffset + 4 <= strlen($bytes)
                ? ['value' => self::u32($bytes, $valueOffset), 'bytes' => 8]
                : null,
            0x0014 => $valueOffset + 8 <= strlen($bytes)
                ? ['value' => self::signed64($bytes, $valueOffset), 'bytes' => 12]
                : null,
            0x0015 => $valueOffset + 8 <= strlen($bytes)
                ? ['value' => self::unsigned64($bytes, $valueOffset), 'bytes' => 12]
                : null,
            0x001e => $this->typedSizedValue($this->readLpstrWithSize($bytes, $valueOffset, $codepage)),
            0x001f => $this->typedSizedValue($this->readLpwstrWithSize($bytes, $valueOffset)),
            0x0040 => $valueOffset + 8 <= strlen($bytes)
                ? ['value' => $this->readFiletime($bytes, $valueOffset), 'bytes' => 12]
                : null,
            0x0041 => $this->typedSizedValue($this->readBlobWithSize($bytes, $valueOffset)),
            0x0047 => $this->typedSizedValue($this->readClipboardDataWithSize($bytes, $valueOffset)),
            0x0048 => $valueOffset + 16 <= strlen($bytes)
                ? ['value' => self::formatClsid(substr($bytes, $valueOffset, 16)), 'bytes' => 20]
                : null,
            0x100c => $this->typedSizedValue($this->readVariantVectorWithSize($bytes, $valueOffset, $codepage)),
            0x101e => $this->typedSizedValue($this->readLpstrVectorWithSize($bytes, $valueOffset, $codepage)),
            0x101f => $this->typedSizedValue($this->readLpwstrVectorWithSize($bytes, $valueOffset)),
            default => null,
        };
    }

    /**
     * @return array{value:int|bool,bytes:int}|null
     */
    private function readPadded16TypedValue(string $bytes, int $valueOffset, string $kind): ?array
    {
        if ($valueOffset + 4 > strlen($bytes)) {
            return null;
        }
        if (substr($bytes, $valueOffset + 2, 2) !== "\0\0") {
            throw new \RuntimeException('Legacy DOC OLE property value contains nonzero 16-bit value padding');
        }

        $raw = self::u16($bytes, $valueOffset);
        $value = match ($kind) {
            'i2' => self::signed16($raw),
            'bool' => $raw !== 0,
            'ui2' => $raw,
            default => throw new \RuntimeException('Legacy DOC OLE property value uses an unsupported 16-bit scalar type'),
        };

        return [
            'value' => $value,
            'bytes' => 8,
        ];
    }

    /**
     * @param array{value:mixed,bytes:int}|null $sizedValue
     * @return array{value:mixed,bytes:int}|null
     */
    private function typedSizedValue(?array $sizedValue): ?array
    {
        if ($sizedValue === null) {
            return null;
        }

        return [
            'value' => $sizedValue['value'],
            'bytes' => 4 + $sizedValue['bytes'],
        ];
    }

    /**
     * @return array{value:array<string,mixed>,bytes:int}|null
     */
    private function readBlobWithSize(string $bytes, int $offset): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $byteCount = self::u32($bytes, $offset);
        if ($byteCount > self::MAX_PROPERTY_BLOB_BYTES || $offset + 4 + $byteCount > strlen($bytes)) {
            return null;
        }

        $payload = substr($bytes, $offset + 4, $byteCount);

        return [
            'value' => [
                'type' => 'blob',
                'conformsTo' => 'VT_BLOB',
                'byteCount' => $byteCount,
                'sha256' => hash('sha256', $payload),
                'extractionPolicy' => 'metadata-only-native-review',
                'canExposeBytes' => false,
            ],
            'bytes' => $this->consumeDwordPadding($bytes, $offset, 4 + $byteCount),
        ];
    }

    /**
     * @return array{value:array<string,mixed>,bytes:int}|null
     */
    private function readClipboardDataWithSize(string $bytes, int $offset): ?array
    {
        if ($offset + 8 > strlen($bytes)) {
            return null;
        }

        $declaredBytes = self::u32($bytes, $offset);
        if (
            $declaredBytes < 4
            || $declaredBytes > self::MAX_CLIPBOARD_DATA_BYTES
            || $offset + 4 + $declaredBytes > strlen($bytes)
        ) {
            return null;
        }

        $clipboardTag = self::u32($bytes, $offset + 4);
        $record = [
            'type' => 'clipboardData',
            'clipboardTag' => $this->clipboardTagName($clipboardTag),
            'clipboardTagValue' => $clipboardTag,
            'hasData' => $clipboardTag !== 0,
            'byteCount' => 0,
            'extractionPolicy' => 'metadata-only-native-review',
            'canExposeBytes' => false,
        ];

        if ($clipboardTag === 0) {
            if ($declaredBytes !== 4) {
                return null;
            }

            return [
                'value' => $record,
                'bytes' => $this->consumeDwordPadding($bytes, $offset, 4 + $declaredBytes),
            ];
        }

        if ($declaredBytes < 8) {
            return null;
        }

        $formatId = self::u32($bytes, $offset + 8);
        $dataOffset = $offset + 12;
        $dataLength = $declaredBytes - 8;
        $data = substr($bytes, $dataOffset, $dataLength);
        $record['formatId'] = $formatId;
        $record['format'] = $this->clipboardFormatName($formatId);
        $record['byteCount'] = $dataLength;
        $record['sha256'] = hash('sha256', $data);

        return [
            'value' => $record,
            'bytes' => $this->consumeDwordPadding($bytes, $offset, 4 + $declaredBytes),
        ];
    }

    private function clipboardTagName(int $clipboardTag): string
    {
        return match ($clipboardTag) {
            0x00000000 => 'none',
            0xffffffff => 'windows',
            0xfffffffe => 'macintosh',
            default => 'application-specific',
        };
    }

    private function clipboardFormatName(int $formatId): string
    {
        return match ($formatId) {
            0x00000003 => 'metafilepict',
            0x00000008 => 'dib',
            0x0000000e => 'enhanced-metafile',
            default => 'unknown',
        };
    }

    /**
     * @param array<int,mixed> $properties
     * @return array<string,mixed>
     */
    private function mapPropertySetValues(array $properties, string $kind): array
    {
        $map = $kind === 'summary'
            ? [
                2 => 'title',
                3 => 'subject',
                4 => 'creator',
                5 => 'keywords',
                6 => 'description',
                7 => 'template',
                8 => 'lastModifiedBy',
                9 => 'revision',
                11 => 'lastPrinted',
                12 => 'created',
                13 => 'modified',
                14 => 'pageCount',
                15 => 'wordCount',
                16 => 'characterCount',
                18 => 'application',
                19 => 'documentSecurity',
            ]
            : [
                2 => 'category',
                3 => 'presentationFormat',
                4 => 'byteCount',
                5 => 'lineCount',
                6 => 'paragraphCount',
                7 => 'slideCount',
                8 => 'noteCount',
                9 => 'hiddenSlideCount',
                10 => 'multimediaClipCount',
                11 => 'scale',
                14 => 'manager',
                15 => 'company',
                16 => 'linksDirty',
                17 => 'charactersWithSpaces',
                19 => 'sharedDocument',
                22 => 'hyperlinksChanged',
                23 => 'applicationVersion',
                26 => 'contentType',
                27 => 'contentStatus',
                28 => 'language',
                29 => 'documentVersion',
            ];

        $metadata = [];
        foreach ($map as $propertyId => $name) {
            $value = $properties[$propertyId] ?? null;
            if ($value !== null && $value !== '') {
                $metadata[$name] = $value;
            }
        }
        if ($kind === 'summary' && isset($metadata['documentSecurity']) && is_int($metadata['documentSecurity'])) {
            $metadata['documentSecurityFlags'] = $this->documentSecurityFlags($metadata['documentSecurity']);
        }
        if ($kind === 'summary') {
            $thumbnail = $this->summaryThumbnailMetadata($properties[17] ?? null);
            if ($thumbnail !== null) {
                $metadata['thumbnail'] = $thumbnail;
                $metadata['thumbnailClipboardTag'] = (string) ($thumbnail['clipboardTag'] ?? '');
                $metadata['thumbnailByteCount'] = (int) ($thumbnail['byteCount'] ?? 0);
                $metadata['thumbnailPolicy'] = (string) ($thumbnail['extractionPolicy'] ?? '');
                if (isset($thumbnail['format'])) {
                    $metadata['thumbnailFormat'] = (string) $thumbnail['format'];
                }
                if (isset($thumbnail['sha256'])) {
                    $metadata['thumbnailSha256'] = (string) $thumbnail['sha256'];
                }
            }
        }
        if ($kind === 'document-summary') {
            $documentParts = $this->stringList($properties[13] ?? null);
            if ($documentParts !== []) {
                $metadata['documentParts'] = $documentParts;
            }

            $headingPairs = $this->documentHeadingPairs($properties[12] ?? null, $documentParts);
            if ($headingPairs !== []) {
                $metadata['headingPairs'] = $headingPairs;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function summaryThumbnailMetadata(mixed $value): ?array
    {
        if (!is_array($value) || ($value['type'] ?? null) !== 'clipboardData') {
            return null;
        }

        $thumbnail = $value;
        $thumbnail['type'] = 'thumbnail';
        $thumbnail['source'] = 'SummaryInformation';
        $thumbnail['conformsTo'] = 'VT_CF';

        return $thumbnail;
    }

    /**
     * @param array<int,mixed> $properties
     * @param array<int,string> $dictionary
     * @return array<string,mixed>
     */
    private function userDefinedDocumentPropertyMetadata(array $properties, array $dictionary): array
    {
        $metadata = [];
        $customProperties = [];
        foreach ($dictionary as $propertyId => $name) {
            $value = $properties[$propertyId] ?? null;
            if ($name === '' || $value === null || $value === '') {
                continue;
            }

            if ($name === '_PID_LINKBASE') {
                if (is_array($value) && ($value['type'] ?? null) === 'reserved-link-base') {
                    $metadata['hyperlinkBase'] = $value['value'];
                    $metadata['hyperlinkBaseSourceProperty'] = $name;
                    $metadata['hyperlinkBaseByteCount'] = $value['byteCount'];
                    $metadata['hyperlinkBasePolicy'] = $value['extractionPolicy'];
                    $metadata['hyperlinkBaseCanExposeBytes'] = $value['canExposeBytes'];
                    $metadata['hyperlinkBaseMetadata'] = $value;
                }
                continue;
            }

            if ($name === '_PID_HLINKS') {
                if (is_array($value) && ($value['type'] ?? null) === 'reserved-hyperlinks') {
                    $metadata['hyperlinks'] = $value['links'];
                    $metadata['hyperlinkCount'] = $value['count'];
                    $metadata['hyperlinkByteCount'] = $value['byteCount'];
                    $metadata['hyperlinkPolicy'] = $value['extractionPolicy'];
                    $metadata['hyperlinkCanExposeBytes'] = $value['canExposeBytes'];
                    $metadata['hyperlinkMetadata'] = $value;
                }
                continue;
            }

            $customProperties[$name] = $value;
        }

        if ($customProperties !== []) {
            $metadata['customProperties'] = $customProperties;
        }

        return $metadata;
    }

    private function isReservedHyperlinkPropertyName(string $name): bool
    {
        return $name === '_PID_LINKBASE' || $name === '_PID_HLINKS';
    }

    /**
     * @return array<string,mixed>
     */
    private function readReservedHyperlinkPropertyValue(string $name, string $bytes, int $offset): array
    {
        return match ($name) {
            '_PID_LINKBASE' => $this->readPidLinkBaseProperty($bytes, $offset),
            '_PID_HLINKS' => $this->readPidHlinksProperty($bytes, $offset),
            default => throw new \RuntimeException('Legacy DOC reserved hyperlink property name is not supported'),
        };
    }

    /**
     * @return array{type:string,sourceProperty:string,value:string,byteCount:int,sourceEncoding:string,extractionPolicy:string,canExposeBytes:bool}
     */
    private function readPidLinkBaseProperty(string $bytes, int $offset): array
    {
        $blob = $this->readReservedHyperlinkBlobProperty($bytes, $offset, '_PID_LINKBASE');
        $payload = $blob['payload'];
        if ($payload === '' || (strlen($payload) % 2) !== 0 || !str_ends_with($payload, "\0\0")) {
            throw new \RuntimeException('Legacy DOC _PID_LINKBASE hyperlink base is not a null-terminated UTF-16LE string');
        }

        $value = rtrim($this->decodeUtf16Le($payload), "\0");
        if ($value === '') {
            throw new \RuntimeException('Legacy DOC _PID_LINKBASE hyperlink base is empty');
        }

        return [
            'type' => 'reserved-link-base',
            'sourceProperty' => '_PID_LINKBASE',
            'value' => $value,
            'byteCount' => $blob['byteCount'],
            'sourceEncoding' => 'UTF-16LE',
            'extractionPolicy' => 'metadata-only-native-review',
            'canExposeBytes' => false,
        ];
    }

    /**
     * @return array{type:string,sourceProperty:string,count:int,byteCount:int,links:list<array<string,mixed>>,extractionPolicy:string,canExposeBytes:bool}
     */
    private function readPidHlinksProperty(string $bytes, int $offset): array
    {
        $blob = $this->readReservedHyperlinkBlobProperty($bytes, $offset, '_PID_HLINKS');
        $links = $this->readVtHyperlinks($blob['payload']);

        return [
            'type' => 'reserved-hyperlinks',
            'sourceProperty' => '_PID_HLINKS',
            'count' => count($links),
            'byteCount' => $blob['byteCount'],
            'links' => $links,
            'extractionPolicy' => 'metadata-only-native-review',
            'canExposeBytes' => false,
        ];
    }

    /**
     * @return array{payload:string,byteCount:int,bytes:int}
     */
    private function readReservedHyperlinkBlobProperty(string $bytes, int $offset, string $label): array
    {
        if ($offset + 8 > strlen($bytes) || self::u16($bytes, $offset) !== 0x0041) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' reserved hyperlink property must be encoded as VT_BLOB');
        }
        if (self::u16($bytes, $offset + 2) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' reserved hyperlink property has nonzero typed-value padding');
        }

        $byteCount = self::u32($bytes, $offset + 4);
        if (
            $byteCount > self::MAX_RESERVED_HYPERLINK_BLOB_BYTES
            || $offset + 8 + $byteCount > strlen($bytes)
        ) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' reserved hyperlink blob is truncated or too large');
        }

        return [
            'payload' => substr($bytes, $offset + 8, $byteCount),
            'byteCount' => $byteCount,
            'bytes' => $this->consumeDwordPadding($bytes, $offset, 8 + $byteCount),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function readVtHyperlinks(string $payload): array
    {
        if (strlen($payload) < 4) {
            throw new \RuntimeException('Legacy DOC _PID_HLINKS VtHyperlinks payload is truncated');
        }

        $elementCount = self::u32($payload, 0);
        if (($elementCount % 6) !== 0) {
            throw new \RuntimeException('Legacy DOC _PID_HLINKS VtHyperlinks element count is not divisible by six');
        }

        $hyperlinkCount = intdiv($elementCount, 6);
        if ($hyperlinkCount > self::MAX_RESERVED_HYPERLINK_COUNT) {
            throw new \RuntimeException('Legacy DOC _PID_HLINKS VtHyperlinks payload has too many links');
        }

        $cursor = 4;
        $links = [];
        for ($index = 0; $index < $hyperlinkCount; $index++) {
            $hash = $this->readRequiredVtI4($payload, $cursor, '_PID_HLINKS hash');
            $cursor += $hash['bytes'];
            $appData = $this->readRequiredVtI4($payload, $cursor, '_PID_HLINKS app data');
            $cursor += $appData['bytes'];
            $shapeId = $this->readRequiredVtI4($payload, $cursor, '_PID_HLINKS shape id');
            $cursor += $shapeId['bytes'];
            $info = $this->readRequiredVtI4($payload, $cursor, '_PID_HLINKS info');
            $cursor += $info['bytes'];
            $target = $this->readRequiredVtString($payload, $cursor, '_PID_HLINKS target');
            $cursor += $target['bytes'];
            $location = $this->readRequiredVtString($payload, $cursor, '_PID_HLINKS location');
            $cursor += $location['bytes'];
            $fixupStatusCode = ($info['unsigned'] >> 16) & 0xffff;

            $links[] = [
                'sourceProperty' => '_PID_HLINKS',
                'index' => $index + 1,
                'target' => $target['value'],
                'location' => $location['value'],
                'targetKind' => $this->reservedHyperlinkTargetKind($target['value'], $location['value']),
                'hash' => $hash['unsigned'],
                'hashHex' => sprintf('%08x', $hash['unsigned']),
                'appData' => $appData['unsigned'],
                'shapeId' => $shapeId['unsigned'],
                'info' => $info['unsigned'],
                'fixupStatusCode' => $fixupStatusCode,
                'fixupStatus' => $this->reservedHyperlinkFixupStatus($fixupStatusCode),
                'extractionPolicy' => 'metadata-only-native-review',
                'canExposeBytes' => false,
            ];
        }

        if ($cursor !== strlen($payload)) {
            throw new \RuntimeException('Legacy DOC _PID_HLINKS VtHyperlinks payload has trailing bytes');
        }

        return $links;
    }

    /**
     * @return array{unsigned:int,signed:int,bytes:int}
     */
    private function readRequiredVtI4(string $bytes, int $offset, string $label): array
    {
        if ($offset + 8 > strlen($bytes) || self::u16($bytes, $offset) !== 0x0003) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' value must be VT_I4');
        }
        if (self::u16($bytes, $offset + 2) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' value has nonzero typed-value padding');
        }

        $raw = self::u32($bytes, $offset + 4);

        return [
            'unsigned' => $raw,
            'signed' => self::signed32($raw),
            'bytes' => 8,
        ];
    }

    /**
     * @return array{value:string,bytes:int}
     */
    private function readRequiredVtString(string $bytes, int $offset, string $label): array
    {
        if ($offset + 4 > strlen($bytes) || self::u16($bytes, $offset) !== 0x001f) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' value must be a VT_LPWSTR VtString');
        }
        if (self::u16($bytes, $offset + 2) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' value has nonzero typed-value padding');
        }

        $value = $this->readLpwstrWithSize($bytes, $offset + 4);
        if ($value === null) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' VtString payload is truncated');
        }

        return [
            'value' => $value['value'],
            'bytes' => 4 + $value['bytes'],
        ];
    }

    private function reservedHyperlinkTargetKind(string $target, string $location): string
    {
        if ($target === '') {
            return $location === '' ? 'empty' : 'document-location';
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target) === 1) {
            return 'external-url';
        }
        if (str_starts_with($target, '#')) {
            return 'fragment';
        }

        return 'relative-or-file';
    }

    private function reservedHyperlinkFixupStatus(int $status): string
    {
        return match ($status) {
            0 => 'synchronized',
            1 => 'requires-fixup',
            2 => 'delete-on-load',
            default => 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    private function documentSecurityFlags(int $flags): array
    {
        $map = [
            0x00000001 => 'passwordProtected',
            0x00000002 => 'readOnlyRecommended',
            0x00000004 => 'readOnlyEnforced',
            0x00000008 => 'lockedForAnnotations',
        ];

        $names = [];
        foreach ($map as $bit => $name) {
            if (($flags & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function looksLikeUtf16Le(string $bytes): bool
    {
        $length = strlen($bytes);
        if ($length < 4 || $length % 2 !== 0) {
            return false;
        }

        $zeroOddBytes = 0;
        for ($index = 1; $index < $length; $index += 2) {
            if ($bytes[$index] === "\0") {
                $zeroOddBytes++;
            }
        }

        return $zeroOddBytes >= intdiv($length, 4);
    }

    private function decodeCompressedPiece(string $bytes): string
    {
        $map = [
            0x82 => "\u{201a}",
            0x83 => "\u{0192}",
            0x84 => "\u{201e}",
            0x85 => "\u{2026}",
            0x86 => "\u{2020}",
            0x87 => "\u{2021}",
            0x88 => "\u{02c6}",
            0x89 => "\u{2030}",
            0x8a => "\u{0160}",
            0x8b => "\u{2039}",
            0x8c => "\u{0152}",
            0x91 => "\u{2018}",
            0x92 => "\u{2019}",
            0x93 => "\u{201c}",
            0x94 => "\u{201d}",
            0x95 => "\u{2022}",
            0x96 => "\u{2013}",
            0x97 => "\u{2014}",
            0x98 => "\u{02dc}",
            0x99 => "\u{2122}",
            0x9a => "\u{0161}",
            0x9b => "\u{203a}",
            0x9c => "\u{0153}",
            0x9f => "\u{0178}",
        ];

        $text = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $byte = ord($bytes[$index]);
            $text .= $map[$byte] ?? self::codepointToUtf8($byte);
        }

        return $text;
    }

    private function decodeWindows1252(string $bytes): string
    {
        $decoded = iconv('Windows-1252', 'UTF-8//IGNORE', $bytes);

        return is_string($decoded) ? $decoded : $bytes;
    }

    private function decodeCodePageString(string $bytes, int $codepage): string
    {
        if ($codepage === 1200) {
            while (strlen($bytes) >= 2 && substr($bytes, -2) === "\0\0") {
                $bytes = substr($bytes, 0, -2);
            }

            return $this->decodeUtf16Le($bytes);
        }

        $bytes = rtrim($bytes, "\0");
        if ($codepage === 65001) {
            if (preg_match('//u', $bytes) === 1) {
                return $bytes;
            }
            $decoded = @iconv('UTF-8', 'UTF-8//IGNORE', $bytes);

            return is_string($decoded) ? $decoded : '';
        }
        if ($codepage === 1251) {
            return $this->decodeWindows1251($bytes);
        }

        $encoding = $this->oleCodepageEncoding($codepage);
        if ($encoding !== null) {
            $decoded = @iconv($encoding, 'UTF-8//IGNORE', $bytes);
            if (is_string($decoded)) {
                return $decoded;
            }
        }

        return $this->decodeWindows1252($bytes);
    }

    private function oleCodepageEncoding(int $codepage): ?string
    {
        return match ($codepage) {
            874 => 'CP874',
            932 => 'CP932',
            936 => 'CP936',
            949 => 'CP949',
            950 => 'CP950',
            1250 => 'Windows-1250',
            1252 => 'Windows-1252',
            1253 => 'Windows-1253',
            1254 => 'Windows-1254',
            1255 => 'Windows-1255',
            1256 => 'Windows-1256',
            1257 => 'Windows-1257',
            1258 => 'Windows-1258',
            default => null,
        };
    }

    private function decodeWindows1251(string $bytes): string
    {
        $map = [
            0x80 => 0x0402,
            0x81 => 0x0403,
            0x82 => 0x201a,
            0x83 => 0x0453,
            0x84 => 0x201e,
            0x85 => 0x2026,
            0x86 => 0x2020,
            0x87 => 0x2021,
            0x88 => 0x20ac,
            0x89 => 0x2030,
            0x8a => 0x0409,
            0x8b => 0x2039,
            0x8c => 0x040a,
            0x8d => 0x040c,
            0x8e => 0x040b,
            0x8f => 0x040f,
            0x90 => 0x0452,
            0x91 => 0x2018,
            0x92 => 0x2019,
            0x93 => 0x201c,
            0x94 => 0x201d,
            0x95 => 0x2022,
            0x96 => 0x2013,
            0x97 => 0x2014,
            0x99 => 0x2122,
            0x9a => 0x0459,
            0x9b => 0x203a,
            0x9c => 0x045a,
            0x9d => 0x045c,
            0x9e => 0x045b,
            0x9f => 0x045f,
            0xa0 => 0x00a0,
            0xa1 => 0x040e,
            0xa2 => 0x045e,
            0xa3 => 0x0408,
            0xa4 => 0x00a4,
            0xa5 => 0x0490,
            0xa6 => 0x00a6,
            0xa7 => 0x00a7,
            0xa8 => 0x0401,
            0xa9 => 0x00a9,
            0xaa => 0x0404,
            0xab => 0x00ab,
            0xac => 0x00ac,
            0xad => 0x00ad,
            0xae => 0x00ae,
            0xaf => 0x0407,
            0xb0 => 0x00b0,
            0xb1 => 0x00b1,
            0xb2 => 0x0406,
            0xb3 => 0x0456,
            0xb4 => 0x0491,
            0xb5 => 0x00b5,
            0xb6 => 0x00b6,
            0xb7 => 0x00b7,
            0xb8 => 0x0451,
            0xb9 => 0x2116,
            0xba => 0x0454,
            0xbb => 0x00bb,
            0xbc => 0x0458,
            0xbd => 0x0405,
            0xbe => 0x0455,
            0xbf => 0x0457,
        ];

        $text = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $byte = ord($bytes[$index]);
            if ($byte < 0x80) {
                $text .= chr($byte);
            } elseif ($byte >= 0xc0) {
                $text .= self::codepointToUtf8(0x0410 + ($byte - 0xc0));
            } elseif (isset($map[$byte])) {
                $text .= self::codepointToUtf8($map[$byte]);
            } else {
                $text .= self::codepointToUtf8($byte);
            }
        }

        return $text;
    }

    private function decodeUtf16Le(string $bytes): string
    {
        $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', $bytes);

        return is_string($decoded) ? $decoded : '';
    }

    private function readLpstr(string $bytes, int $offset, int $codepage): ?string
    {
        $value = $this->readLpstrWithSize($bytes, $offset, $codepage);

        return $value['value'] ?? null;
    }

    /**
     * @return array{value:string,bytes:int}|null
     */
    private function readLpstrWithSize(string $bytes, int $offset, int $codepage): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }
        $length = self::u32($bytes, $offset);
        if ($length === 0 || $offset + 4 + $length > strlen($bytes)) {
            return null;
        }

        $payloadBytes = 4 + $length;
        return [
            'value' => $this->decodeCodePageString(substr($bytes, $offset + 4, $length), $codepage),
            'bytes' => $this->consumeDwordPadding($bytes, $offset, $payloadBytes),
        ];
    }

    private function readLpwstr(string $bytes, int $offset): ?string
    {
        $value = $this->readLpwstrWithSize($bytes, $offset);

        return $value['value'] ?? null;
    }

    /**
     * @return array{value:string,bytes:int}|null
     */
    private function readLpwstrWithSize(string $bytes, int $offset): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }
        $characters = self::u32($bytes, $offset);
        $byteLength = $characters * 2;
        if ($characters === 0 || $offset + 4 + $byteLength > strlen($bytes)) {
            return null;
        }

        $payloadBytes = 4 + $byteLength;
        return [
            'value' => rtrim($this->decodeUtf16Le(substr($bytes, $offset + 4, $byteLength)), "\0"),
            'bytes' => $this->consumeDwordPadding($bytes, $offset, $payloadBytes),
        ];
    }

    private function consumeDwordPadding(string $bytes, int $offset, int $payloadBytes): int
    {
        $padding = (4 - ($payloadBytes % 4)) % 4;
        if ($padding === 0) {
            return $payloadBytes;
        }

        $paddingOffset = $offset + $payloadBytes;
        if ($paddingOffset + $padding > strlen($bytes)) {
            return $payloadBytes;
        }

        return substr($bytes, $paddingOffset, $padding) === str_repeat("\0", $padding)
            ? $payloadBytes + $padding
            : $payloadBytes;
    }

    /**
     * @return array{value:list<string>,bytes:int}|null
     */
    private function readLpstrVectorWithSize(string $bytes, int $offset, int $codepage): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $count = self::u32($bytes, $offset);
        $cursor = $offset + 4;
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $value = $this->readLpstrWithSize($bytes, $cursor, $codepage);
            if ($value === null) {
                return null;
            }
            $values[] = $value['value'];
            $cursor += $value['bytes'];
        }

        return [
            'value' => $values,
            'bytes' => $cursor - $offset,
        ];
    }

    /**
     * @return array{value:list<string>,bytes:int}|null
     */
    private function readLpwstrVectorWithSize(string $bytes, int $offset): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $count = self::u32($bytes, $offset);
        $cursor = $offset + 4;
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $value = $this->readLpwstrWithSize($bytes, $cursor);
            if ($value === null) {
                return null;
            }
            $values[] = $value['value'];
            $cursor += $value['bytes'];
        }

        return [
            'value' => $values,
            'bytes' => $cursor - $offset,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function readDictionary(string $bytes, int $offset, int $codepage, int $limit): array
    {
        if ($offset + 4 > $limit || $limit > strlen($bytes)) {
            return [];
        }

        $count = self::u32($bytes, $offset);
        $cursor = $offset + 4;
        $dictionary = [];
        $seenNames = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 8 > $limit) {
                return [];
            }

            $propertyId = self::u32($bytes, $cursor);
            $nameLength = self::u32($bytes, $cursor + 4);
            $cursor += 8;
            if ($propertyId < 2 || $propertyId > 0x7fffffff || $nameLength === 0) {
                return [];
            }

            if ($codepage === 1200) {
                $byteLength = $nameLength * 2;
                if ($cursor + $byteLength > $limit) {
                    return [];
                }

                $rawName = substr($bytes, $cursor, $byteLength);
                if (!str_ends_with($rawName, "\0\0")) {
                    throw new \RuntimeException('Legacy DOC custom property dictionary Unicode name is not null-terminated');
                }

                $name = rtrim($this->decodeUtf16Le($rawName), "\0");
                $cursor += $byteLength;
                $padding = (4 - ($byteLength % 4)) % 4;
                if ($cursor + $padding > $limit) {
                    return [];
                }
                if ($padding > 0 && substr($bytes, $cursor, $padding) !== str_repeat("\0", $padding)) {
                    throw new \RuntimeException('Legacy DOC custom property dictionary Unicode name padding is not zeroed');
                }
                $cursor += $padding;
            } else {
                if ($cursor + $nameLength > $limit) {
                    return [];
                }

                $rawName = substr($bytes, $cursor, $nameLength);
                if (!str_ends_with($rawName, "\0")) {
                    throw new \RuntimeException('Legacy DOC custom property dictionary name is not null-terminated');
                }

                $name = $this->decodeCodePageString($rawName, $codepage);
                $cursor += $nameLength;
            }

            $normalizedName = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if (isset($dictionary[$propertyId]) || isset($seenNames[$normalizedName])) {
                throw new \RuntimeException('Legacy DOC custom property dictionary contains duplicate entries');
            }

            $dictionary[$propertyId] = $name;
            $seenNames[$normalizedName] = true;
        }

        return $dictionary;
    }

    /**
     * @return array{value:list<mixed>,bytes:int}|null
     */
    private function readVariantVectorWithSize(string $bytes, int $offset, int $codepage): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $count = self::u32($bytes, $offset);
        $cursor = $offset + 4;
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $value = $this->readTypedPropertyValueWithSize($bytes, $cursor, $codepage);
            if ($value === null) {
                return null;
            }
            $values[] = $value['value'];
            $cursor += $value['bytes'];
        }

        return [
            'value' => $values,
            'bytes' => $cursor - $offset,
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @param list<string> $documentParts
     * @return list<array{heading:string,count:int,parts:list<string>}>
     */
    private function documentHeadingPairs(mixed $value, array $documentParts): array
    {
        if (!is_array($value)) {
            return [];
        }

        $pairs = [];
        $partOffset = 0;
        for ($index = 0, $count = count($value); $index + 1 < $count; $index += 2) {
            $heading = $value[$index];
            $partCount = $value[$index + 1];
            if (!is_string($heading) || $heading === '' || !is_int($partCount) || $partCount < 0) {
                continue;
            }

            $parts = array_slice($documentParts, $partOffset, $partCount);
            $partOffset += $partCount;
            $pairs[] = [
                'heading' => $heading,
                'count' => $partCount,
                'parts' => $parts,
            ];
        }

        return $pairs;
    }

    /**
     * @param array<string,string> $subdocumentTexts
     * @return list<array<string,mixed>>
     */
    private function noteReferenceReport(
        string $type,
        string $wordDocument,
        ?string $tableStream,
        string $text,
        array $subdocumentTexts = []
    ): array
    {
        if ($tableStream === null) {
            return [];
        }

        if ($type === 'footnote') {
            $fcRefOffset = self::FIB_FC_PLCFFND_REF;
            $lcbRefOffset = self::FIB_LCB_PLCFFND_REF;
            $fcTxtOffset = self::FIB_FC_PLCFFND_TXT;
            $lcbTxtOffset = self::FIB_LCB_PLCFFND_TXT;
        } else {
            $fcRefOffset = self::FIB_FC_PLCFEND_REF;
            $lcbRefOffset = self::FIB_LCB_PLCFEND_REF;
            $fcTxtOffset = self::FIB_FC_PLCFEND_TXT;
            $lcbTxtOffset = self::FIB_LCB_PLCFEND_TXT;
        }
        if (strlen($wordDocument) < $lcbTxtOffset + 4) {
            return [];
        }

        $fib = $this->readFib($wordDocument);
        if ((int) $fib['fcMin'] > 0 && $lcbTxtOffset + 4 > (int) $fib['fcMin']) {
            return [];
        }

        $fcRef = self::u32($wordDocument, $fcRefOffset);
        $lcbRef = self::u32($wordDocument, $lcbRefOffset);
        $fcTxt = self::u32($wordDocument, $fcTxtOffset);
        $lcbTxt = self::u32($wordDocument, $lcbTxtOffset);
        if ($lcbRef === 0 && $lcbTxt === 0) {
            return [];
        }
        if ($lcbRef === 0 || $lcbTxt === 0) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC is present without matching text-range PLC');
        }

        $references = $this->parseNoteReferencePlc(
            $this->tableStreamSlice($tableStream, $fcRef, $lcbRef, $type . ' reference PLC'),
            $type
        );
        $ranges = $this->parseNoteTextPlc(
            $this->tableStreamSlice($tableStream, $fcTxt, $lcbTxt, $type . ' text PLC'),
            $type
        );
        if (count($references) !== count($ranges)) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' reference and text PLCs do not contain parallel counts');
        }

        $characters = $this->unicodeCharacters($text);
        $textLength = count($characters);
        $result = [];
        foreach ($references as $index => $reference) {
            $referenceCp = (int) $reference['referenceCp'];
            if ($referenceCp < 0 || $referenceCp >= $textLength) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' reference CP points outside the extracted main text');
            }

            $referenceIndex = (int) $reference['referenceIndex'];
            $autoNumbered = $referenceIndex !== 0;
            $referenceCharacter = $characters[$referenceCp] ?? '';

            $range = $ranges[$index];
            $bodyText = $this->subdocumentRangeText(
                $subdocumentTexts,
                $type,
                (int) $range['startCp'],
                (int) $range['endCp']
            );
            $record = [
                'type' => $type,
                'index' => $index + 1,
                'referenceCp' => $referenceCp,
                'referenceIndex' => $referenceIndex,
                'autoNumbered' => $autoNumbered,
                'marker' => $this->noteReferenceMarker($autoNumbered, $index, $referenceCharacter),
                'textStartCp' => (int) $range['startCp'],
                'textEndCp' => (int) $range['endCp'],
                'canAnchor' => true,
            ];
            if ($autoNumbered && $referenceCharacter !== "\x02") {
                $record['malformedAutoNumberedReference'] = true;
            }
            if ($bodyText !== null) {
                $record['bodyText'] = $bodyText;
                $record['bodyCharacterCount'] = $this->textCharacterLength($bodyText);
            }

            $result[] = $record;
        }

        return $result;
    }

    /**
     * @return list<array{referenceCp:int,referenceIndex:int}>
     */
    private function parseNoteReferencePlc(string $bytes, string $type): array
    {
        $length = strlen($bytes);
        if ($length < 10 || (($length - 4) % 6) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC has an invalid length');
        }

        $count = intdiv($length - 4, 6);
        $dataOffset = ($count + 1) * 4;
        $entries = [];
        $previousCp = null;
        $seenCps = [];
        for ($index = 0; $index < $count; $index++) {
            $referenceCp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $referenceCp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC contains duplicate or unsorted CPs');
            }
            if (isset($seenCps[$referenceCp])) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC contains duplicate CPs');
            }
            $previousCp = $referenceCp;
            $seenCps[$referenceCp] = true;
            $entries[] = [
                'referenceCp' => $referenceCp,
                'referenceIndex' => self::u16($bytes, $dataOffset + ($index * 2)),
            ];
        }

        $ignoredCp = self::u32($bytes, $count * 4);
        if ($previousCp !== null && $ignoredCp <= $previousCp) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC final CP is not after the last reference CP');
        }

        return $entries;
    }

    /**
     * @return list<array{startCp:int,endCp:int}>
     */
    private function parseNoteTextPlc(string $bytes, string $type): array
    {
        $length = strlen($bytes);
        if ($length < 12 || ($length % 4) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' text PLC has an invalid length');
        }

        $cpCount = intdiv($length, 4);
        $noteCount = $cpCount - 2;
        $cps = [];
        $previousCp = null;
        for ($index = 0; $index < $cpCount; $index++) {
            $cp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $cp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' text PLC contains duplicate or unsorted CPs');
            }
            $previousCp = $cp;
            $cps[] = $cp;
        }

        $ranges = [];
        for ($index = 0; $index < $noteCount; $index++) {
            $startCp = $cps[$index];
            $endCp = $cps[$index + 1];
            if ($startCp >= $endCp) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' text PLC contains an empty note range');
            }
            $ranges[] = [
                'startCp' => $startCp,
                'endCp' => $endCp,
            ];
        }

        return $ranges;
    }

    private function noteReferenceMarker(bool $autoNumbered, int $index, string $referenceCharacter): string
    {
        if ($autoNumbered) {
            return (string) ($index + 1);
        }
        if ($referenceCharacter !== '' && preg_match('/[\x00-\x1f]/u', $referenceCharacter) !== 1) {
            return $referenceCharacter;
        }

        return '*';
    }

    /**
     * @param array<string,string> $subdocumentTexts
     * @param list<array<string,mixed>> $commentAuthors
     * @return list<array<string,mixed>>
     */
    private function commentReferenceReport(
        string $wordDocument,
        ?string $tableStream,
        string $text,
        array $subdocumentTexts = [],
        array $commentAuthors = []
    ): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCFAND_TXT + 4) {
            return [];
        }

        $fib = $this->readFib($wordDocument);
        if ((int) $fib['fcMin'] > 0 && self::FIB_LCB_PLCFAND_TXT + 4 > (int) $fib['fcMin']) {
            return [];
        }

        $fcRef = self::u32($wordDocument, self::FIB_FC_PLCFAND_REF);
        $lcbRef = self::u32($wordDocument, self::FIB_LCB_PLCFAND_REF);
        $fcTxt = self::u32($wordDocument, self::FIB_FC_PLCFAND_TXT);
        $lcbTxt = self::u32($wordDocument, self::FIB_LCB_PLCFAND_TXT);
        if ($lcbRef === 0 && $lcbTxt === 0) {
            return [];
        }
        if ($lcbRef === 0 || $lcbTxt === 0) {
            throw new \RuntimeException('Legacy DOC comment reference PLC is present without matching text-range PLC');
        }

        $references = $this->parseCommentReferencePlc(
            $this->tableStreamSlice($tableStream, $fcRef, $lcbRef, 'comment reference PLC')
        );
        $ranges = $this->parseNoteTextPlc(
            $this->tableStreamSlice($tableStream, $fcTxt, $lcbTxt, 'comment text PLC'),
            'comment'
        );
        if (count($references) !== count($ranges)) {
            throw new \RuntimeException('Legacy DOC comment reference and text PLCs do not contain parallel counts');
        }

        $characters = $this->unicodeCharacters($text);
        $textLength = count($characters);
        $comments = [];
        foreach ($references as $index => $reference) {
            $referenceCp = (int) $reference['referenceCp'];
            if ($referenceCp < 0 || $referenceCp >= $textLength) {
                throw new \RuntimeException('Legacy DOC comment reference CP points outside the extracted main text');
            }
            if (($characters[$referenceCp] ?? '') !== "\x05") {
                throw new \RuntimeException('Legacy DOC comment reference is missing the special annotation reference character');
            }

            $range = $ranges[$index];
            $bodyText = $this->subdocumentRangeText(
                $subdocumentTexts,
                'comment',
                (int) $range['startCp'],
                (int) $range['endCp']
            );
            $authorInitials = (string) ($reference['authorInitials'] ?? '');
            $authorIndex = (int) $reference['authorIndex'];
            $record = [
                'type' => 'comment',
                'index' => $index + 1,
                'referenceCp' => $referenceCp,
                'authorInitials' => $authorInitials,
                'authorIndex' => $authorIndex,
                'bookmarkTag' => (int) $reference['bookmarkTag'],
                'lengthZeroRange' => (int) $reference['bookmarkTag'] === -1,
                'marker' => $authorInitials !== '' ? $authorInitials : (string) ($index + 1),
                'textStartCp' => (int) $range['startCp'],
                'textEndCp' => (int) $range['endCp'],
                'canAnchor' => true,
            ];
            if ($commentAuthors !== []) {
                if (!isset($commentAuthors[$authorIndex])) {
                    throw new \RuntimeException('Legacy DOC comment author index points outside the owner name table');
                }

                $record['authorName'] = (string) ($commentAuthors[$authorIndex]['name'] ?? '');
            }
            if ($bodyText !== null) {
                $record['bodyText'] = $bodyText;
                $record['bodyCharacterCount'] = $this->textCharacterLength($bodyText);
            }

            $comments[] = $record;
        }

        return $comments;
    }

    /**
     * @param array<string,string> $subdocumentTexts
     * @return array{characters:list<array<string,mixed>>,fields:list<array<string,mixed>>,stories:list<array<string,mixed>>}
     */
    private function fieldCharacterReport(
        string $wordDocument,
        ?string $tableStream,
        string $text,
        array $subdocumentTexts = []
    ): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCF_FLD_MOM + 4) {
            return [
                'characters' => [],
                'fields' => [],
                'stories' => [],
            ];
        }

        $characters = [];
        $fields = [];
        $stories = [];
        $fib = $this->readFib($wordDocument);
        $appendParsed = function (array $parsed, string $story, string $table, int $storyCharacterCount) use (&$characters, &$fields, &$stories): void {
            if ($parsed['characters'] === [] && $parsed['fields'] === []) {
                return;
            }

            $stories[] = [
                'story' => $story,
                'table' => $table,
                'characterCount' => $storyCharacterCount,
                'fieldCharacterCount' => count($parsed['characters']),
                'fieldCount' => count($parsed['fields']),
            ];

            foreach ($parsed['characters'] as $record) {
                $record['storyIndex'] = (int) ($record['index'] ?? 0);
                $record['index'] = count($characters) + 1;
                $characters[] = $record;
            }

            foreach ($parsed['fields'] as $record) {
                $record['storyIndex'] = (int) ($record['index'] ?? 0);
                $record['index'] = count($fields) + 1;
                $fields[] = $record;
            }
        };

        $lcbPlcfFldMom = self::u32($wordDocument, self::FIB_LCB_PLCF_FLD_MOM);
        $fcPlcfFldMom = self::u32($wordDocument, self::FIB_FC_PLCF_FLD_MOM);
        if ($lcbPlcfFldMom > 0) {
            $appendParsed(
                $this->parsePlcfld(
                    $this->tableStreamSlice($tableStream, $fcPlcfFldMom, $lcbPlcfFldMom, 'PlcfldMom field table'),
                    $text,
                    'main',
                    'PlcfldMom'
                ),
                'main',
                'PlcfldMom',
                $this->textCharacterLength($text)
            );
        }

        foreach ($this->supplementalFieldStoryDescriptors() as $descriptor) {
            $fcOffset = (int) $descriptor['fcOffset'];
            $lcbOffset = (int) $descriptor['lcbOffset'];
            if (strlen($wordDocument) < $lcbOffset + 4) {
                continue;
            }
            if ((int) ($fib['fcMin'] ?? 0) > 0 && $lcbOffset + 4 > (int) $fib['fcMin']) {
                continue;
            }

            $length = self::u32($wordDocument, $lcbOffset);
            if ($length === 0) {
                continue;
            }

            $story = (string) $descriptor['story'];
            if (!array_key_exists($story, $subdocumentTexts)) {
                throw new \RuntimeException('Legacy DOC ' . $descriptor['table'] . ' field table is present without extracted ' . $story . ' text');
            }

            $storyText = $subdocumentTexts[$story];
            $offset = self::u32($wordDocument, $fcOffset);
            $appendParsed(
                $this->parsePlcfld(
                    $this->tableStreamSlice($tableStream, $offset, $length, $descriptor['table'] . ' field table'),
                    $storyText,
                    $story,
                    (string) $descriptor['table']
                ),
                $story,
                (string) $descriptor['table'],
                $this->textCharacterLength($storyText)
            );
        }

        return [
            'characters' => $characters,
            'fields' => $fields,
            'stories' => $stories,
        ];
    }

    /**
     * @return list<array{story:string,table:string,fcOffset:int,lcbOffset:int}>
     */
    private function supplementalFieldStoryDescriptors(): array
    {
        return [
            [
                'story' => 'header',
                'table' => 'PlcfldHdr',
                'fcOffset' => self::FIB_FC_PLCF_FLD_HDR,
                'lcbOffset' => self::FIB_LCB_PLCF_FLD_HDR,
            ],
            [
                'story' => 'footnote',
                'table' => 'PlcfldFtn',
                'fcOffset' => self::FIB_FC_PLCF_FLD_FTN,
                'lcbOffset' => self::FIB_LCB_PLCF_FLD_FTN,
            ],
            [
                'story' => 'comment',
                'table' => 'PlcfldAtn',
                'fcOffset' => self::FIB_FC_PLCF_FLD_ATN,
                'lcbOffset' => self::FIB_LCB_PLCF_FLD_ATN,
            ],
            [
                'story' => 'endnote',
                'table' => 'PlcfldEdn',
                'fcOffset' => self::FIB_FC_PLCF_FLD_EDN,
                'lcbOffset' => self::FIB_LCB_PLCF_FLD_EDN,
            ],
            [
                'story' => 'textbox',
                'table' => 'PlcfldTxbx',
                'fcOffset' => self::FIB_FC_PLCF_FLD_TXBX,
                'lcbOffset' => self::FIB_LCB_PLCF_FLD_TXBX,
            ],
            [
                'story' => 'header-textbox',
                'table' => 'PlcfldHdrTxbx',
                'fcOffset' => self::FIB_FC_PLCF_FLD_HDR_TXBX,
                'lcbOffset' => self::FIB_LCB_PLCF_FLD_HDR_TXBX,
            ],
        ];
    }

    /**
     * @return array{characters:list<array<string,mixed>>,fields:list<array<string,mixed>>}
     */
    private function parsePlcfld(string $bytes, string $text, string $story = 'main', string $label = 'Plcfld'): array
    {
        $length = strlen($bytes);
        if ($length < 4 || (($length - 4) % 6) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' field table has an invalid length');
        }

        $fieldCharacterCount = intdiv($length - 4, 6);
        if ($fieldCharacterCount === 0) {
            return [
                'characters' => [],
                'fields' => [],
            ];
        }

        $textCharacters = $this->unicodeCharacters($text);
        $textLength = count($textCharacters);
        $cps = [];
        $previousCp = null;
        for ($index = 0; $index <= $fieldCharacterCount; $index++) {
            $cp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $cp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC ' . $label . ' field table contains duplicate or unsorted CPs');
            }
            if ($cp > $textLength) {
                return [
                    'characters' => [],
                    'fields' => [],
                ];
            }
            $previousCp = $cp;
            $cps[] = $cp;
        }

        $fieldCharacters = [];
        $fields = [];
        $stack = [];
        $dataOffset = ($fieldCharacterCount + 1) * 4;
        for ($index = 0; $index < $fieldCharacterCount; $index++) {
            $cp = $cps[$index];
            if ($cp >= $textLength) {
                return [
                    'characters' => [],
                    'fields' => [],
                ];
            }

            $fldOffset = $dataOffset + ($index * 2);
            $fieldCharacterCode = ord($bytes[$fldOffset]);
            $flags = ord($bytes[$fldOffset + 1]);
            $expectedCharacter = match ($fieldCharacterCode) {
                self::FIELD_CHARACTER_BEGIN => "\x13",
                self::FIELD_CHARACTER_SEPARATOR => "\x14",
                self::FIELD_CHARACTER_END => "\x15",
                default => null,
            };
            if ($expectedCharacter === null) {
                return [
                    'characters' => [],
                    'fields' => [],
                ];
            }
            if (($textCharacters[$cp] ?? '') !== $expectedCharacter) {
                return [
                    'characters' => [],
                    'fields' => [],
                ];
            }

            if ($fieldCharacterCode === self::FIELD_CHARACTER_BEGIN) {
                $type = $this->fieldTypeName($flags);
                $nestingLevel = count($stack);
                $fieldCharacters[] = [
                    'index' => $index + 1,
                    'cp' => $cp,
                    'kind' => 'begin',
                    'story' => $story,
                    'fieldCharacterCode' => $fieldCharacterCode,
                    'typeCode' => $flags,
                    'type' => $type,
                    'nestingLevel' => $nestingLevel,
                ];
                $stack[] = [
                    'beginCp' => $cp,
                    'typeCode' => $flags,
                    'type' => $type,
                    'nestingLevel' => $nestingLevel,
                ];
                continue;
            }

            if ($fieldCharacterCode === self::FIELD_CHARACTER_SEPARATOR) {
                if ($stack === []) {
                    return [
                        'characters' => [],
                        'fields' => [],
                    ];
                }
                $openIndex = count($stack) - 1;
                if (isset($stack[$openIndex]['separatorCp'])) {
                    return [
                        'characters' => [],
                        'fields' => [],
                    ];
                }
                $stack[$openIndex]['separatorCp'] = $cp;
                $fieldCharacters[] = [
                    'index' => $index + 1,
                    'cp' => $cp,
                    'kind' => 'separator',
                    'story' => $story,
                    'fieldCharacterCode' => $fieldCharacterCode,
                    'nestingLevel' => (int) $stack[$openIndex]['nestingLevel'],
                ];
                continue;
            }

            if ($stack === []) {
                return [
                    'characters' => [],
                    'fields' => [],
                ];
            }

            $open = array_pop($stack);
            $hasResult = isset($open['separatorCp']);
            $endFlagMetadata = $this->fieldEndFlagMetadata($flags, $hasResult);
            $fieldCharacters[] = [
                'index' => $index + 1,
                'cp' => $cp,
                'kind' => 'end',
                'story' => $story,
                'fieldCharacterCode' => $fieldCharacterCode,
                'nestingLevel' => (int) $open['nestingLevel'],
            ] + $endFlagMetadata;

            $field = [
                'index' => count($fields) + 1,
                'story' => $story,
                'typeCode' => (int) $open['typeCode'],
                'type' => (string) $open['type'],
                'beginCp' => (int) $open['beginCp'],
                'endCp' => $cp,
                'instructionStartCp' => (int) $open['beginCp'] + 1,
                'instructionEndCp' => $hasResult ? (int) $open['separatorCp'] : $cp,
                'hasResult' => $hasResult,
                'nestingLevel' => (int) $open['nestingLevel'],
            ] + $endFlagMetadata;
            if ($hasResult) {
                $field['separatorCp'] = (int) $open['separatorCp'];
                $field['resultStartCp'] = (int) $open['separatorCp'] + 1;
                $field['resultEndCp'] = $cp;
            }

            $fields[] = $field;
        }

        if ($stack !== []) {
            return [
                'characters' => [],
                'fields' => [],
            ];
        }

        return [
            'characters' => $fieldCharacters,
            'fields' => $fields,
        ];
    }

    private function fieldTypeName(int $typeCode): string
    {
        return self::FIELD_TYPE_NAMES[$typeCode] ?? 'unknown';
    }

    /**
     * @return array<string,mixed>
     */
    private function fieldEndFlagMetadata(int $flags, bool $hasResult): array
    {
        $hasSeparatorFlag = ($flags & 0x80) !== 0;

        return [
            'endFlags' => $flags,
            'endFlagNames' => $this->fieldEndFlagNames($flags),
            'differ' => ($flags & 0x01) !== 0,
            'zombieEmbed' => ($flags & 0x02) !== 0,
            'resultDirty' => ($flags & 0x04) !== 0,
            'resultEdited' => ($flags & 0x08) !== 0,
            'locked' => ($flags & 0x10) !== 0,
            'privateResult' => ($flags & 0x20) !== 0,
            'nested' => ($flags & 0x40) !== 0,
            'hasSeparatorFlag' => $hasSeparatorFlag,
            'separatorFlagMatchesRange' => $hasSeparatorFlag === $hasResult,
        ];
    }

    /**
     * @return list<string>
     */
    private function fieldEndFlagNames(int $flags): array
    {
        $names = [];
        foreach ([
            0x01 => 'differ',
            0x02 => 'zombie-embed',
            0x04 => 'result-dirty',
            0x08 => 'result-edited',
            0x10 => 'locked',
            0x20 => 'private-result',
            0x40 => 'nested',
            0x80 => 'has-separator',
        ] as $mask => $name) {
            if (($flags & $mask) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<array{referenceCp:int,authorInitials:string,authorIndex:int,bookmarkTag:int}>
     */
    private function parseCommentReferencePlc(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 38 || (($length - 4) % 34) !== 0) {
            throw new \RuntimeException('Legacy DOC comment reference PLC has an invalid length');
        }

        $count = intdiv($length - 4, 34);
        $dataOffset = ($count + 1) * 4;
        $entries = [];
        $previousCp = null;
        $seenCps = [];
        for ($index = 0; $index < $count; $index++) {
            $referenceCp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $referenceCp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC comment reference PLC contains duplicate or unsorted CPs');
            }
            if (isset($seenCps[$referenceCp])) {
                throw new \RuntimeException('Legacy DOC comment reference PLC contains duplicate CPs');
            }
            $previousCp = $referenceCp;
            $seenCps[$referenceCp] = true;

            $recordOffset = $dataOffset + ($index * 30);
            $unusedBits = self::u16($bytes, $recordOffset + 22);
            $unusedFlags = self::u16($bytes, $recordOffset + 24);
            if ($unusedBits !== 0 || $unusedFlags !== 0) {
                throw new \RuntimeException('Legacy DOC comment reference descriptor contains nonzero reserved fields');
            }

            $bookmarkTag = self::signed32(self::u32($bytes, $recordOffset + 26));
            $entries[] = [
                'referenceCp' => $referenceCp,
                'authorInitials' => $this->readLpxCharBuffer9(substr($bytes, $recordOffset, 20)),
                'authorIndex' => self::u16($bytes, $recordOffset + 20),
                'bookmarkTag' => $bookmarkTag,
            ];
        }

        $ignoredCp = self::u32($bytes, $count * 4);
        if ($previousCp !== null && $ignoredCp <= $previousCp) {
            throw new \RuntimeException('Legacy DOC comment reference PLC final CP is not after the last reference CP');
        }

        return $entries;
    }

    private function readLpxCharBuffer9(string $bytes): string
    {
        if (strlen($bytes) !== 20) {
            throw new \RuntimeException('Legacy DOC LPXCharBuffer9 is truncated');
        }

        $characters = self::u16($bytes, 0);
        if ($characters > 9) {
            throw new \RuntimeException('Legacy DOC LPXCharBuffer9 declares too many characters');
        }

        return $characters === 0 ? '' : $this->decodeUtf16Le(substr($bytes, 2, $characters * 2));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function styleSheetReport(string $wordDocument, ?string $tableStream): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_STSHF + 4) {
            return [];
        }

        $fcStshf = self::u32($wordDocument, self::FIB_FC_STSHF);
        $lcbStshf = self::u32($wordDocument, self::FIB_LCB_STSHF);
        if ($lcbStshf === 0) {
            return [];
        }

        return $this->parseStyleSheet($this->tableStreamSlice($tableStream, $fcStshf, $lcbStshf, 'STSH stylesheet'));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseStyleSheet(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 20) {
            throw new \RuntimeException('Legacy DOC stylesheet is too short to contain an LPStshi header');
        }

        $cbStshi = self::u16($bytes, 0);
        $stylesOffset = 2 + $cbStshi;
        if ($cbStshi < 18 || $stylesOffset > $length) {
            throw new \RuntimeException('Legacy DOC stylesheet LPStshi header length is invalid');
        }
        if (($stylesOffset % 2) !== 0) {
            $stylesOffset++;
        }

        $styleCount = self::u16($bytes, 2);
        $stdfBaseBytes = self::u16($bytes, 4);
        $styleFlags = self::u16($bytes, 6);
        $fixedStyleCount = self::u16($bytes, 10);
        if ($styleCount < 0x000f || $styleCount >= 0x0ffe) {
            throw new \RuntimeException('Legacy DOC stylesheet contains an invalid style count');
        }
        if (!in_array($stdfBaseBytes, [0x000a, 0x0012], true)) {
            throw new \RuntimeException('Legacy DOC stylesheet uses an unsupported StdfBase length');
        }
        if (($styleFlags & 0x0001) === 0 || ($styleFlags & 0xfffe) !== 0) {
            throw new \RuntimeException('Legacy DOC stylesheet contains reserved Stshif flags');
        }
        if ($fixedStyleCount !== 0x000f) {
            throw new \RuntimeException('Legacy DOC stylesheet fixed-style count is invalid');
        }

        $styles = [];
        $nonEmptyByIstd = [];
        $seenNames = [];
        $cursor = $stylesOffset;
        for ($istd = 0; $istd < $styleCount; $istd++) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC stylesheet is truncated before all LPStd records');
            }

            $cbStd = self::u16($bytes, $cursor);
            $cursor += 2;
            if (($cbStd & 0x8000) !== 0) {
                throw new \RuntimeException('Legacy DOC stylesheet contains a negative LPStd length');
            }
            if ($cbStd === 0) {
                if (($cursor % 2) !== 0) {
                    $cursor++;
                }
                continue;
            }
            if ($cursor + $cbStd > $length) {
                throw new \RuntimeException('Legacy DOC stylesheet LPStd points outside the STSH bytes');
            }

            $style = $this->parseStyleDefinition(substr($bytes, $cursor, $cbStd), $istd, $cbStd, $stdfBaseBytes, $seenNames);
            $styles[] = $style;
            $nonEmptyByIstd[$istd] = true;
            $cursor += $cbStd;
            if (($cursor % 2) !== 0) {
                $cursor++;
            }
        }

        foreach ($styles as $style) {
            if (isset($style['basedOnIstd'])) {
                $base = (int) $style['basedOnIstd'];
                if ($base < 0 || $base >= $styleCount || !isset($nonEmptyByIstd[$base]) || $base === (int) $style['istd']) {
                    throw new \RuntimeException('Legacy DOC stylesheet contains an invalid based-on style reference');
                }
            }
        }
        $this->assertStyleInheritanceIsAcyclic($styles);

        return $styles;
    }

    /**
     * @param array<string, true> $seenNames
     * @return array<string,mixed>
     */
    private function parseStyleDefinition(string $bytes, int $istd, int $cbStd, int $stdfBaseBytes, array &$seenNames): array
    {
        if (strlen($bytes) < $stdfBaseBytes + 4 || $stdfBaseBytes < 10) {
            throw new \RuntimeException('Legacy DOC stylesheet style definition is missing its Xstz name');
        }

        $word0 = self::u16($bytes, 0);
        $word1 = self::u16($bytes, 2);
        $word2 = self::u16($bytes, 4);
        $bchUpe = self::u16($bytes, 6);
        $grfstd = self::u16($bytes, 8);
        if ($bchUpe !== $cbStd) {
            throw new \RuntimeException('Legacy DOC stylesheet style definition length mirror is invalid');
        }

        $stk = $word1 & 0x000f;
        $style = [
            'istd' => $istd,
            'type' => $this->styleTypeName($stk),
            'name' => '',
            'sti' => $word0 & 0x0fff,
            'builtIn' => ($word0 & 0x0fff) !== 0x0ffe,
            'cupx' => $word2 & 0x000f,
            'nextIstd' => $word2 >> 4,
            'cbStd' => $cbStd,
            'bchUpe' => $bchUpe,
            'grfstd' => $grfstd,
        ];

        $basedOnIstd = $word1 >> 4;
        if ($basedOnIstd !== 0x0fff) {
            $style['basedOnIstd'] = $basedOnIstd;
        }

        [$name, $bytesRead] = $this->readXstzName($bytes, $stdfBaseBytes);
        if ($name === '') {
            throw new \RuntimeException('Legacy DOC stylesheet style definition contains an empty name');
        }
        if ($stdfBaseBytes + $bytesRead > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC stylesheet style name points outside its LPStd record');
        }

        $nameParts = array_map('trim', explode(',', $name));
        if ($nameParts === [] || in_array('', $nameParts, true)) {
            throw new \RuntimeException('Legacy DOC stylesheet style definition contains an empty alias');
        }
        foreach ($nameParts as $styleName) {
            $normalized = $this->normalizedStyleName($styleName);
            if (isset($seenNames[$normalized])) {
                continue;
            }
            $seenNames[$normalized] = true;
        }

        $style['name'] = $nameParts[0];
        if (count($nameParts) > 1) {
            $style['aliases'] = array_slice($nameParts, 1);
        }

        $upxOffset = $stdfBaseBytes + $bytesRead;
        if ($upxOffset > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC stylesheet style UPX groups point outside the LPStd record');
        }
        if (($upxOffset % 2) !== 0) {
            $upxOffset++;
        }
        if ($upxOffset < strlen($bytes)) {
            $style += $this->parseStyleDefinitionUpxs($bytes, $upxOffset, (string) $style['type'], (int) $style['cupx']);
        }

        return $style;
    }

    /**
     * @return array<string,mixed>
     */
    private function parseStyleDefinitionUpxs(string $bytes, int $offset, string $styleType, int $cupx): array
    {
        if ($cupx <= 0) {
            throw new \RuntimeException('Legacy DOC stylesheet style contains UPX bytes but declares no UPX groups');
        }

        $length = strlen($bytes);
        $cursor = $offset;
        $upxRecords = [];
        $paragraphProperties = [];
        $textProperties = [];
        $upxByteCount = 0;

        for ($index = 0; $index < $cupx; $index++) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC stylesheet style UPX group is truncated');
            }

            $byteCount = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($cursor + $byteCount > $length) {
                throw new \RuntimeException('Legacy DOC stylesheet style UPX group points outside the LPStd record');
            }

            $payload = substr($bytes, $cursor, $byteCount);
            $cursor += $byteCount;
            if (($byteCount % 2) !== 0) {
                if ($cursor >= $length || $bytes[$cursor] !== "\0") {
                    throw new \RuntimeException('Legacy DOC stylesheet style UPX group is missing its even-byte padding');
                }
                $cursor++;
            }

            $kind = $this->styleDefinitionUpxKind($styleType, $index);
            $upxRecords[] = [
                'index' => $index + 1,
                'kind' => $kind,
                'byteCount' => $byteCount,
            ];
            $upxByteCount += $byteCount;

            if ($payload === '') {
                continue;
            }

            if ($kind === 'paragraph') {
                foreach ($this->styleFormattingProperties($this->parsePapxParagraphProperties($payload), 'PAPX') as $property) {
                    $paragraphProperties[] = $property;
                }
                continue;
            }

            if ($kind === 'character') {
                foreach ($this->styleFormattingProperties($this->parseChpxTextProperties($payload), 'CHPX') as $property) {
                    $textProperties[] = $property;
                }
            }
        }

        if ($cursor !== $length) {
            throw new \RuntimeException('Legacy DOC stylesheet style contains trailing bytes after its UPX groups');
        }

        $report = [
            'upxRecordCount' => count($upxRecords),
            'upxByteCount' => $upxByteCount,
            'upxRecords' => $upxRecords,
            'canApplyStyleFormatting' => false,
        ];
        if ($paragraphProperties !== []) {
            $report['paragraphProperties'] = $paragraphProperties;
            $report['paragraphPropertyCount'] = count($paragraphProperties);
            $report['paragraphPropertyExtractionPolicy'] = 'metadata-only-native-review';
        }
        if ($textProperties !== []) {
            $report['textProperties'] = $textProperties;
            $report['textPropertyCount'] = count($textProperties);
            $report['textPropertyExtractionPolicy'] = 'metadata-only-native-review';
        }

        return $report;
    }

    private function styleDefinitionUpxKind(string $styleType, int $index): string
    {
        return match ($styleType) {
            'paragraph' => match ($index) {
                0 => 'paragraph',
                1 => 'character',
                default => 'extra',
            },
            'character' => $index === 0 ? 'character' : 'extra',
            'table' => match ($index) {
                0 => 'table',
                1 => 'paragraph',
                2 => 'character',
                default => 'extra',
            },
            'numbering' => $index === 0 ? 'paragraph' : 'extra',
            default => 'extra',
        };
    }

    /**
     * @param list<array<string,mixed>> $properties
     * @return list<array<string,mixed>>
     */
    private function styleFormattingProperties(array $properties, string $sourceUpx): array
    {
        return array_map(
            static function (array $property) use ($sourceUpx): array {
                $property['source'] = 'Stshf';
                $property['sourceRecord'] = 'STD';
                $property['sourceUpx'] = $sourceUpx;

                return $property;
            },
            $properties
        );
    }

    /**
     * @param list<array<string,mixed>> $styles
     * @return array{paragraphProperties:int,textProperties:int}
     */
    private function styleFormattingCounts(array $styles): array
    {
        $paragraphProperties = 0;
        $textProperties = 0;
        foreach ($styles as $style) {
            $paragraphProperties += count(is_array($style['paragraphProperties'] ?? null) ? $style['paragraphProperties'] : []);
            $textProperties += count(is_array($style['textProperties'] ?? null) ? $style['textProperties'] : []);
        }

        return [
            'paragraphProperties' => $paragraphProperties,
            'textProperties' => $textProperties,
        ];
    }

    private function styleTypeName(int $stk): string
    {
        return match ($stk) {
            1 => 'paragraph',
            2 => 'character',
            3 => 'table',
            4 => 'numbering',
            default => throw new \RuntimeException('Legacy DOC stylesheet contains an unsupported style type'),
        };
    }

    /**
     * @return array{0:string,1:int}
     */
    private function readXstzName(string $bytes, int $offset): array
    {
        if ($offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC stylesheet style name is truncated');
        }

        $characters = self::u16($bytes, $offset);
        if ($characters === 0 || $characters > 255) {
            throw new \RuntimeException('Legacy DOC stylesheet style name length is outside the supported range');
        }

        $textOffset = $offset + 2;
        $byteLength = $characters * 2;
        $terminatorOffset = $textOffset + $byteLength;
        if ($terminatorOffset + 2 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC stylesheet style name points outside its string data');
        }
        if (self::u16($bytes, $terminatorOffset) !== 0) {
            throw new \RuntimeException('Legacy DOC stylesheet style name is missing its null terminator');
        }

        return [
            $this->decodeUtf16Le(substr($bytes, $textOffset, $byteLength)),
            2 + $byteLength + 2,
        ];
    }

    private function legacyDocFormFieldTypeName(int $fieldTypeCode): string
    {
        return match ($fieldTypeCode) {
            0 => 'text',
            1 => 'checkbox',
            2 => 'dropdown',
            default => throw new \RuntimeException('Legacy DOC FFData field type is unsupported'),
        };
    }

    private function legacyDocTextFormFieldTypeName(int $textTypeCode): string
    {
        return match ($textTypeCode) {
            0 => 'regular',
            1 => 'number',
            2 => 'date-time',
            3 => 'current-date',
            4 => 'current-time',
            5 => 'calculation',
            default => throw new \RuntimeException('Legacy DOC FFData textbox type is unsupported'),
        };
    }

    private function readLegacyDocXstz(string $bytes, int &$cursor, string $context, int $maxCharacters): string
    {
        if ($cursor + 4 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC ' . $context . ' is truncated');
        }

        $characters = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($characters > $maxCharacters) {
            throw new \RuntimeException('Legacy DOC ' . $context . ' exceeds the supported character limit');
        }

        $byteLength = $characters * 2;
        if ($cursor + $byteLength + 2 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC ' . $context . ' points outside its string data');
        }

        $value = $characters === 0 ? '' : $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
        $cursor += $byteLength;
        if (self::u16($bytes, $cursor) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $context . ' is missing its null terminator');
        }
        $cursor += 2;

        return $value;
    }

    /**
     * @return list<string>
     */
    private function readLegacyDocUnicodeSttbStrings(string $bytes, int &$cursor, string $context, int $maxCount, int $maxCharacters): array
    {
        if ($cursor + 6 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC ' . $context . ' is truncated');
        }
        if (self::u16($bytes, $cursor) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC ' . $context . ' must use extended strings');
        }
        $cursor += 2;

        $count = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($count > $maxCount) {
            throw new \RuntimeException('Legacy DOC ' . $context . ' contains too many strings');
        }

        $extraBytes = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($extraBytes !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $context . ' must not contain extra data');
        }

        $strings = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC ' . $context . ' string length is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters > $maxCharacters) {
                throw new \RuntimeException('Legacy DOC ' . $context . ' string exceeds the supported character limit');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC ' . $context . ' points outside its string data');
            }

            $strings[] = $characters === 0 ? '' : $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
        }

        return $strings;
    }

    /**
     * @param list<array<string,mixed>> $styles
     */
    private function assertStyleInheritanceIsAcyclic(array $styles): void
    {
        $baseByIstd = [];
        foreach ($styles as $style) {
            if (isset($style['basedOnIstd'])) {
                $baseByIstd[(int) $style['istd']] = (int) $style['basedOnIstd'];
            }
        }

        foreach (array_keys($baseByIstd) as $istd) {
            $seen = [];
            $cursor = $istd;
            while (isset($baseByIstd[$cursor])) {
                if (isset($seen[$cursor])) {
                    throw new \RuntimeException('Legacy DOC stylesheet based-on styles form an inheritance loop');
                }
                $seen[$cursor] = true;
                $cursor = $baseByIstd[$cursor];
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $revisionAuthors
     * @return list<array<string,mixed>>
     */
    private function formattingRunReport(string $wordDocument, ?string $tableStream, array $revisionAuthors): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCF_BTE_PAPX + 4) {
            return [];
        }

        return array_merge(
            $this->formattingTableReport(
                'paragraph',
                'PlcBtePapx',
                $wordDocument,
                $tableStream,
                self::FIB_FC_PLCF_BTE_PAPX,
                self::FIB_LCB_PLCF_BTE_PAPX,
                $revisionAuthors
            ),
            $this->formattingTableReport(
                'character',
                'PlcBteChpx',
                $wordDocument,
                $tableStream,
                self::FIB_FC_PLCF_BTE_CHPX,
                self::FIB_LCB_PLCF_BTE_CHPX,
                $revisionAuthors
            )
        );
    }

    /**
     * @param list<array<string,mixed>> $revisionAuthors
     * @return list<array<string,mixed>>
     */
    private function formattingTableReport(
        string $kind,
        string $label,
        string $wordDocument,
        string $tableStream,
        int $fcOffset,
        int $lcbOffset,
        array $revisionAuthors
    ): array {
        $fc = self::u32($wordDocument, $fcOffset);
        $lcb = self::u32($wordDocument, $lcbOffset);
        if ($lcb === 0) {
            return [];
        }
        return $this->parsePlcBte(
            $this->tableStreamSlice($tableStream, $fc, $lcb, $label),
            $kind,
            $label,
            $wordDocument,
            $revisionAuthors
        );
    }

    /**
     * @param list<array<string,mixed>> $revisionAuthors
     * @return list<array<string,mixed>>
     */
    private function parsePlcBte(string $bytes, string $kind, string $label, string $wordDocument, array $revisionAuthors): array
    {
        $length = strlen($bytes);
        if ($length < 12 || (($length - 4) % 8) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' formatting table has an invalid length');
        }

        $runCount = intdiv($length - 4, 8);
        $fcCount = $runCount + 1;
        $fcs = [];
        $previousFc = null;
        for ($index = 0; $index < $fcCount; $index++) {
            $fc = self::u32($bytes, $index * 4);
            if ($previousFc !== null && $fc <= $previousFc) {
                throw new \RuntimeException('Legacy DOC ' . $label . ' formatting table contains duplicate or unsorted file offsets');
            }
            if ($fc > strlen($wordDocument)) {
                throw new \RuntimeException('Legacy DOC ' . $label . ' formatting table points outside WordDocument');
            }

            $previousFc = $fc;
            $fcs[] = $fc;
        }

        $pnOffset = $fcCount * 4;
        $runs = [];
        for ($index = 0; $index < $runCount; $index++) {
            $pnFkp = self::u32($bytes, $pnOffset + ($index * 4));
            $fkpPage = $pnFkp & 0x003fffff;
            $unusedBits = $pnFkp >> 22;
            $fkpByteOffset = $fkpPage * 512;
            if ($fkpByteOffset < 0 || $fkpByteOffset + 512 > strlen($wordDocument)) {
                throw new \RuntimeException('Legacy DOC ' . $label . ' formatting table points to an FKP page outside WordDocument');
            }

            $run = [
                'kind' => $kind,
                'table' => $label,
                'index' => $index + 1,
                'startFc' => $fcs[$index],
                'endFc' => $fcs[$index + 1],
                'byteLength' => $fcs[$index + 1] - $fcs[$index],
                'fkpPage' => $fkpPage,
                'fkpByteOffset' => $fkpByteOffset,
                'fkpByteCount' => 512,
                'fkpRunCount' => ord($wordDocument[$fkpByteOffset + 511]),
                'canApplyFormatting' => false,
            ];
            if ($unusedBits !== 0) {
                $run['unusedPnFkpBits'] = $unusedBits;
            }
            if ($kind === 'character') {
                $grpprl = $this->chpxGrpprlForRun(
                    $wordDocument,
                    $fkpByteOffset,
                    $fcs[$index],
                    $fcs[$index + 1]
                );
                if ($grpprl !== null) {
                    $textProperties = $this->parseChpxTextProperties($grpprl);
                    if ($textProperties !== []) {
                        $run['textProperties'] = $textProperties;
                        $run['textPropertyCount'] = count($textProperties);
                        $run['textPropertyExtractionPolicy'] = 'metadata-only-native-review';
                        $inlineFormattingNodeTypes = $this->inlineFormattingNodeTypes($textProperties);
                        if ($inlineFormattingNodeTypes !== []) {
                            $run['canApplyFormatting'] = true;
                            $run['inlineFormattingNodeTypes'] = $inlineFormattingNodeTypes;
                            $run['inlineFormattingPolicy'] = 'semantic-inline-native-review';
                        }
                    }
                    $revisionMarks = $this->parseChpxRevisionMarks($grpprl, $revisionAuthors);
                    if ($revisionMarks !== []) {
                        $run['revisionMarks'] = $revisionMarks;
                        $run['revisionMarkCount'] = count($revisionMarks);
                        $run['revisionExtractionPolicy'] = 'metadata-only-native-review';
                    }
                    $pictureData = $this->parseChpxPictureData($grpprl);
                    if ($pictureData !== []) {
                        $run['pictureData'] = $pictureData;
                        $run['pictureDataCount'] = count($pictureData);
                        $run['pictureDataExtractionPolicy'] = 'metadata-only-native-review';
                    }
                }
            } elseif ($kind === 'paragraph') {
                $papx = $this->papxGrpprlForRun(
                    $wordDocument,
                    $fkpByteOffset,
                    $fcs[$index],
                    $fcs[$index + 1]
                );
                if ($papx !== null) {
                    $run['paragraphStyleIndex'] = $papx['styleIndex'];
                    $paragraphProperties = $this->parsePapxParagraphProperties($papx['grpprl']);
                    if ($paragraphProperties !== []) {
                        $run['paragraphProperties'] = $paragraphProperties;
                        $run['paragraphPropertyCount'] = count($paragraphProperties);
                        $run['paragraphPropertyExtractionPolicy'] = 'metadata-only-native-review';
                    }
                    $revisionMarks = $this->parsePapxPropertyRevisionMarks($papx['grpprl'], $revisionAuthors);
                    if ($revisionMarks !== []) {
                        $run['revisionMarks'] = $revisionMarks;
                        $run['revisionMarkCount'] = count($revisionMarks);
                        $run['revisionExtractionPolicy'] = 'metadata-only-native-review';
                    }
                }
            }

            $runs[] = $run;
        }

        return $runs;
    }

    /**
     * @return string|null
     */
    private function chpxGrpprlForRun(
        string $wordDocument,
        int $fkpByteOffset,
        int $startFc,
        int $endFc
    ): ?string {
        $page = substr($wordDocument, $fkpByteOffset, 512);
        $runCount = ord($page[511]);
        if ($runCount === 0) {
            return null;
        }
        if ($runCount > 0x65) {
            throw new \RuntimeException('Legacy DOC ChpxFkp formatting metadata has too many runs');
        }

        $rgbOffset = ($runCount + 1) * 4;
        if ($rgbOffset + $runCount > 511) {
            throw new \RuntimeException('Legacy DOC ChpxFkp formatting metadata has an invalid rgb offset');
        }

        for ($index = 0; $index < $runCount; $index++) {
            $chpxByteOffset = ord($page[$rgbOffset + $index]) * 2;
            if ($chpxByteOffset === 0) {
                continue;
            }

            $fkpStartFc = self::u32($page, $index * 4);
            $fkpEndFc = self::u32($page, ($index + 1) * 4);
            if ($fkpStartFc !== $startFc || $fkpEndFc !== $endFc) {
                continue;
            }
            if ($chpxByteOffset < $rgbOffset + $runCount || $chpxByteOffset >= 511) {
                throw new \RuntimeException('Legacy DOC ChpxFkp formatting metadata points outside its CHPX area');
            }

            $grpprlLength = ord($page[$chpxByteOffset]);
            if ($chpxByteOffset + 1 + $grpprlLength > 511) {
                throw new \RuntimeException('Legacy DOC ChpxFkp formatting metadata CHPX points outside the FKP page');
            }

            return substr($page, $chpxByteOffset + 1, $grpprlLength);
        }

        return null;
    }

    /**
     * @return array{styleIndex:int,grpprl:string}|null
     */
    private function papxGrpprlForRun(
        string $wordDocument,
        int $fkpByteOffset,
        int $startFc,
        int $endFc
    ): ?array {
        $page = substr($wordDocument, $fkpByteOffset, 512);
        $runCount = ord($page[511]);
        if ($runCount === 0) {
            return null;
        }
        if ($runCount > 0x1d) {
            throw new \RuntimeException('Legacy DOC PapxFkp formatting metadata has too many runs');
        }

        $bxOffset = ($runCount + 1) * 4;
        $bxByteCount = $runCount * 13;
        if ($bxOffset + $bxByteCount > 511) {
            throw new \RuntimeException('Legacy DOC PapxFkp formatting metadata has an invalid BX offset');
        }

        for ($index = 0; $index < $runCount; $index++) {
            $fkpStartFc = self::u32($page, $index * 4);
            $fkpEndFc = self::u32($page, ($index + 1) * 4);
            if ($fkpStartFc !== $startFc || $fkpEndFc !== $endFc) {
                continue;
            }

            $papxByteOffset = ord($page[$bxOffset + ($index * 13)]) * 2;
            if ($papxByteOffset === 0) {
                return null;
            }
            if ($papxByteOffset < $bxOffset + $bxByteCount || $papxByteOffset >= 511) {
                throw new \RuntimeException('Legacy DOC PapxFkp formatting metadata points outside its PAPX area');
            }

            $cb = ord($page[$papxByteOffset]);
            if ($cb === 0) {
                if ($papxByteOffset + 2 > 511) {
                    throw new \RuntimeException('Legacy DOC PapxFkp formatting metadata PAPX has a truncated extended size');
                }
                $payloadStart = $papxByteOffset + 2;
                $payloadByteCount = ord($page[$papxByteOffset + 1]) * 2;
            } else {
                $payloadStart = $papxByteOffset + 1;
                $payloadByteCount = ($cb * 2) - 1;
            }
            if ($payloadByteCount < 2 || $payloadStart + $payloadByteCount > 511) {
                throw new \RuntimeException('Legacy DOC PapxFkp formatting metadata PAPX points outside the FKP page');
            }

            return [
                'styleIndex' => self::u16($page, $payloadStart),
                'grpprl' => substr($page, $payloadStart + 2, $payloadByteCount - 2),
            ];
        }

        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseChpxTextProperties(string $grpprl): array
    {
        $length = strlen($grpprl);
        $cursor = 0;
        $properties = [];

        while ($cursor < $length) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC CHPX text formatting metadata contains a truncated SPRM');
            }

            $sprm = self::u16($grpprl, $cursor);
            $cursor += 2;
            $operandByteCount = $this->sprmOperandByteCount($sprm, $grpprl, $cursor);
            if ($cursor + $operandByteCount > $length) {
                throw new \RuntimeException('Legacy DOC CHPX text formatting metadata contains a truncated SPRM operand');
            }

            $operandOffset = $cursor;
            $cursor += $operandByteCount;

            $property = match ($sprm) {
                self::SPRM_CF_BOLD => $this->chpxToggleTextProperty('bold', 'sprmCFBold', $grpprl[$operandOffset]),
                self::SPRM_CF_ITALIC => $this->chpxToggleTextProperty('italic', 'sprmCFItalic', $grpprl[$operandOffset]),
                self::SPRM_CF_STRIKE => $this->chpxToggleTextProperty('strikethrough', 'sprmCFStrike', $grpprl[$operandOffset]),
                self::SPRM_CF_SMALL_CAPS => $this->chpxToggleTextProperty('small-caps', 'sprmCFSmallCaps', $grpprl[$operandOffset]),
                self::SPRM_CF_CAPS => $this->chpxToggleTextProperty('all-caps', 'sprmCFCaps', $grpprl[$operandOffset]),
                self::SPRM_CF_VANISH => $this->chpxToggleTextProperty('hidden', 'sprmCFVanish', $grpprl[$operandOffset]),
                self::SPRM_C_KUL => $this->chpxUnderlineTextProperty(ord($grpprl[$operandOffset])),
                default => null,
            };

            if ($property !== null) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * @return array<string,mixed>
     */
    private function chpxToggleTextProperty(string $name, string $sourceSprm, string $operand): array
    {
        $rawOperand = ord($operand);
        $state = match ($rawOperand) {
            0 => 'off',
            1 => 'on',
            128 => 'toggle',
            129 => 'preserve',
            default => 'unknown',
        };

        return [
            'name' => $name,
            'source' => 'ChpxFkp',
            'sourceSprm' => $sourceSprm,
            'rawOperand' => $rawOperand,
            'state' => $state,
            'enabled' => $rawOperand === 1,
            'extractionPolicy' => 'metadata-only-native-review',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function chpxUnderlineTextProperty(int $underlineCode): array
    {
        return [
            'name' => 'underline',
            'source' => 'ChpxFkp',
            'sourceSprm' => 'sprmCKul',
            'rawOperand' => $underlineCode,
            'style' => $this->legacyDocUnderlineStyleName($underlineCode),
            'enabled' => $underlineCode !== 0,
            'extractionPolicy' => 'metadata-only-native-review',
        ];
    }

    private function legacyDocUnderlineStyleName(int $underlineCode): string
    {
        return match ($underlineCode) {
            0 => 'none',
            1 => 'single',
            2 => 'by-word',
            3 => 'double',
            4 => 'dotted',
            6 => 'thick',
            7 => 'dash',
            9 => 'dot-dash',
            10 => 'dot-dot-dash',
            11 => 'wave',
            default => 'unknown',
        };
    }

    /**
     * @param list<array<string,mixed>> $revisionAuthors
     * @return list<array<string,mixed>>
     */
    private function parseChpxRevisionMarks(string $grpprl, array $revisionAuthors): array
    {
        $length = strlen($grpprl);
        $cursor = 0;
        $states = [
            'inserted' => [
                'type' => 'inserted',
                'active' => false,
                'sourceSprms' => [],
            ],
            'deleted' => [
                'type' => 'deleted',
                'active' => false,
                'sourceSprms' => [],
            ],
        ];

        while ($cursor < $length) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC CHPX revision metadata contains a truncated SPRM');
            }

            $sprm = self::u16($grpprl, $cursor);
            $cursor += 2;
            $operandByteCount = $this->sprmOperandByteCount($sprm, $grpprl, $cursor);
            if ($cursor + $operandByteCount > $length) {
                throw new \RuntimeException('Legacy DOC CHPX revision metadata contains a truncated SPRM operand');
            }

            $operandOffset = $cursor;
            $cursor += $operandByteCount;

            switch ($sprm) {
                case self::SPRM_CFR_MARK_INS:
                    $states['inserted']['active'] = ord($grpprl[$operandOffset]) === 1;
                    $states['inserted']['sourceSprms'][] = 'sprmCFRMarkIns';
                    break;
                case self::SPRM_CIBST_RMARK:
                    $states['inserted']['authorIndex'] = self::signed16(self::u16($grpprl, $operandOffset));
                    $states['inserted']['sourceSprms'][] = 'sprmCIbstRMark';
                    break;
                case self::SPRM_CDTTM_RMARK:
                    $states['inserted']['timestamp'] = $this->readDttmFromValue(
                        self::u32($grpprl, $operandOffset),
                        'Legacy DOC CHPX inserted revision mark contains an invalid DTTM timestamp'
                    );
                    $states['inserted']['sourceSprms'][] = 'sprmCDttmRMark';
                    break;
                case self::SPRM_CFR_MARK_DEL:
                    $states['deleted']['active'] = ord($grpprl[$operandOffset]) === 1;
                    $states['deleted']['sourceSprms'][] = 'sprmCFRMarkDel';
                    break;
                case self::SPRM_CIBST_RMARK_DEL:
                    $states['deleted']['authorIndex'] = self::signed16(self::u16($grpprl, $operandOffset));
                    $states['deleted']['sourceSprms'][] = 'sprmCIbstRMarkDel';
                    break;
                case self::SPRM_CDTTM_RMARK_DEL:
                    $states['deleted']['timestamp'] = $this->readDttmFromValue(
                        self::u32($grpprl, $operandOffset),
                        'Legacy DOC CHPX deleted revision mark contains an invalid DTTM timestamp'
                    );
                    $states['deleted']['sourceSprms'][] = 'sprmCDttmRMarkDel';
                    break;
            }
        }

        $marks = [];
        foreach ($states as $state) {
            $sourceSprms = is_array($state['sourceSprms'] ?? null) ? $state['sourceSprms'] : [];
            if ($sourceSprms === [] || (($state['active'] ?? false) !== true && !isset($state['authorIndex']) && !isset($state['timestamp']))) {
                continue;
            }

            $record = [
                'type' => $state['type'],
                'source' => 'ChpxFkp',
                'sourceSprms' => $sourceSprms,
                'canApplyRevision' => false,
                'extractionPolicy' => 'metadata-only-native-review',
            ];
            if (isset($state['authorIndex'])) {
                $authorIndex = (int) $state['authorIndex'];
                if ($authorIndex < 0) {
                    throw new \RuntimeException('Legacy DOC CHPX revision metadata contains a negative author index');
                }
                $record['authorIndex'] = $authorIndex;
                if ($revisionAuthors !== []) {
                    if (!isset($revisionAuthors[$authorIndex]) || !isset($revisionAuthors[$authorIndex]['name'])) {
                        throw new \RuntimeException('Legacy DOC CHPX revision metadata author index is outside SttbfRMark');
                    }
                    $record['authorName'] = (string) $revisionAuthors[$authorIndex]['name'];
                    $record['authorSourceTable'] = 'SttbfRMark';
                }
            }
            if (array_key_exists('timestamp', $state)) {
                $record['timestamp'] = $state['timestamp'];
            }

            $marks[] = $record;
        }

        return $marks;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parsePapxParagraphProperties(string $grpprl): array
    {
        $length = strlen($grpprl);
        $cursor = 0;
        $properties = [];

        while ($cursor < $length) {
            if ($cursor + 2 > $length) {
                return $properties;
            }

            $sprm = self::u16($grpprl, $cursor);
            $cursor += 2;
            $operandByteCount = $this->sprmOperandByteCount($sprm, $grpprl, $cursor);
            if ($cursor + $operandByteCount > $length) {
                return $properties;
            }

            $operandOffset = $cursor;
            $cursor += $operandByteCount;

            $property = match ($sprm) {
                self::SPRM_PJC => $this->papxJustificationProperty(ord($grpprl[$operandOffset])),
                self::SPRM_PF_KEEP => $this->papxBoolProperty('keep-lines', 'sprmPFKeep', ord($grpprl[$operandOffset])),
                self::SPRM_PF_KEEP_FOLLOW => $this->papxBoolProperty('keep-with-next', 'sprmPFKeepFollow', ord($grpprl[$operandOffset])),
                self::SPRM_PF_PAGE_BREAK_BEFORE => $this->papxBoolProperty('page-break-before', 'sprmPFPageBreakBefore', ord($grpprl[$operandOffset])),
                self::SPRM_PDXA_LEFT => $this->papxTwipsProperty('left-indent', 'sprmPDxaLeft', self::signed16(self::u16($grpprl, $operandOffset))),
                self::SPRM_PDXA_LEFT1 => $this->papxTwipsProperty('first-line-indent', 'sprmPDxaLeft1', self::signed16(self::u16($grpprl, $operandOffset))),
                self::SPRM_PDXA_RIGHT => $this->papxTwipsProperty('right-indent', 'sprmPDxaRight', self::signed16(self::u16($grpprl, $operandOffset))),
                self::SPRM_PDYA_BEFORE => $this->papxSpacingProperty('space-before', 'sprmPDyaBefore', self::u16($grpprl, $operandOffset)),
                self::SPRM_PDYA_AFTER => $this->papxSpacingProperty('space-after', 'sprmPDyaAfter', self::u16($grpprl, $operandOffset)),
                self::SPRM_PDYA_LINE => $this->papxLineSpacingProperty(
                    self::u16($grpprl, $operandOffset),
                    self::u16($grpprl, $operandOffset + 2)
                ),
                default => null,
            };

            if ($property !== null) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * @return array<string,mixed>
     */
    private function papxJustificationProperty(int $code): array
    {
        if ($code > 9) {
            throw new \RuntimeException('Legacy DOC PAPX paragraph justification value is invalid');
        }

        return [
            'name' => 'justification',
            'source' => 'PapxFkp',
            'sourceSprm' => 'sprmPJc',
            'rawOperand' => $code,
            'value' => $this->legacyDocParagraphJustificationName($code),
            'extractionPolicy' => 'metadata-only-native-review',
        ];
    }

    private function legacyDocParagraphJustificationName(int $code): string
    {
        return match ($code) {
            0 => 'left',
            1 => 'center',
            2 => 'right',
            3 => 'both',
            4 => 'distribute',
            5 => 'medium-kashida',
            6 => 'indent',
            7 => 'high-kashida',
            8 => 'low-kashida',
            9 => 'thai-distribute',
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function papxBoolProperty(string $name, string $sourceSprm, int $rawOperand): array
    {
        if ($rawOperand !== 0 && $rawOperand !== 1) {
            throw new \RuntimeException('Legacy DOC PAPX ' . $sourceSprm . ' Bool8 operand is invalid');
        }

        return [
            'name' => $name,
            'source' => 'PapxFkp',
            'sourceSprm' => $sourceSprm,
            'rawOperand' => $rawOperand,
            'enabled' => $rawOperand === 1,
            'extractionPolicy' => 'metadata-only-native-review',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function papxTwipsProperty(string $name, string $sourceSprm, int $twips): array
    {
        return [
            'name' => $name,
            'source' => 'PapxFkp',
            'sourceSprm' => $sourceSprm,
            'twips' => $twips,
            'extractionPolicy' => 'metadata-only-native-review',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function papxSpacingProperty(string $name, string $sourceSprm, int $twips): array
    {
        if ($twips > 0x7bc0) {
            throw new \RuntimeException('Legacy DOC PAPX ' . $sourceSprm . ' spacing value is outside the supported twips range');
        }

        return [
            'name' => $name,
            'source' => 'PapxFkp',
            'sourceSprm' => $sourceSprm,
            'twips' => $twips,
            'extractionPolicy' => 'metadata-only-native-review',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function papxLineSpacingProperty(int $rawDyaLine, int $fMultLinespace): array
    {
        if ($fMultLinespace !== 0 && $fMultLinespace !== 1) {
            throw new \RuntimeException('Legacy DOC PAPX line-spacing multiplier flag is invalid');
        }
        if ($rawDyaLine > 0x7bc0 && $rawDyaLine < 0x8440) {
            throw new \RuntimeException('Legacy DOC PAPX line-spacing value is outside the supported range');
        }

        $record = [
            'name' => 'line-spacing',
            'source' => 'PapxFkp',
            'sourceSprm' => 'sprmPDyaLine',
            'rawDyaLine' => $rawDyaLine,
            'fMultLinespace' => $fMultLinespace === 1,
        ];

        if ($rawDyaLine >= 0x8440) {
            $record['mode'] = 'exact';
            $record['twips'] = 0x10000 - $rawDyaLine;
            $record['extractionPolicy'] = 'metadata-only-native-review';

            return $record;
        }

        if ($fMultLinespace === 1) {
            $record['mode'] = 'multiple';
            $record['multiple'] = (float) ($rawDyaLine / 240);
            $record['extractionPolicy'] = 'metadata-only-native-review';

            return $record;
        }

        $record['mode'] = 'at-least';
        $record['twips'] = $rawDyaLine;
        $record['extractionPolicy'] = 'metadata-only-native-review';

        return $record;
    }

    /**
     * @param list<array<string,mixed>> $revisionAuthors
     * @return list<array<string,mixed>>
     */
    private function parsePapxPropertyRevisionMarks(string $grpprl, array $revisionAuthors): array
    {
        $length = strlen($grpprl);
        $cursor = 0;
        $marks = [];

        while ($cursor < $length) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC PAPX revision metadata contains a truncated SPRM');
            }

            $sprm = self::u16($grpprl, $cursor);
            $cursor += 2;
            $operandByteCount = $this->sprmOperandByteCount($sprm, $grpprl, $cursor);
            if ($cursor + $operandByteCount > $length) {
                throw new \RuntimeException('Legacy DOC PAPX revision metadata contains a truncated SPRM operand');
            }

            $operandOffset = $cursor;
            $cursor += $operandByteCount;
            if ($sprm !== self::SPRM_P_PROP_RMARK) {
                continue;
            }
            if ($operandByteCount !== 8 || ord($grpprl[$operandOffset]) !== 7) {
                throw new \RuntimeException('Legacy DOC PAPX property revision mark operand must be 7 bytes');
            }

            $active = ord($grpprl[$operandOffset + 1]);
            if ($active !== 0 && $active !== 1) {
                throw new \RuntimeException('Legacy DOC PAPX property revision mark active flag is invalid');
            }
            if ($active === 0) {
                continue;
            }

            $authorIndex = self::signed16(self::u16($grpprl, $operandOffset + 2));
            if ($authorIndex < 0) {
                throw new \RuntimeException('Legacy DOC PAPX property revision mark contains a negative author index');
            }

            $record = [
                'type' => 'paragraph-property',
                'source' => 'PapxFkp',
                'sourceSprms' => ['sprmPPropRMark'],
                'authorIndex' => $authorIndex,
                'timestamp' => $this->readDttmFromValue(
                    self::u32($grpprl, $operandOffset + 4),
                    'Legacy DOC PAPX property revision mark contains an invalid DTTM timestamp'
                ),
                'canApplyRevision' => false,
                'extractionPolicy' => 'metadata-only-native-review',
            ];
            if ($revisionAuthors !== []) {
                if (!isset($revisionAuthors[$authorIndex]) || !isset($revisionAuthors[$authorIndex]['name'])) {
                    throw new \RuntimeException('Legacy DOC PAPX property revision mark author index is outside SttbfRMark');
                }
                $record['authorName'] = (string) $revisionAuthors[$authorIndex]['name'];
                $record['authorSourceTable'] = 'SttbfRMark';
            }

            $marks[] = $record;
        }

        return $marks;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseChpxPictureData(string $grpprl): array
    {
        $length = strlen($grpprl);
        $cursor = 0;
        $sourceSprms = [];
        $hasSpecialCharacterFormatting = null;
        $dataStreamOffset = null;
        $isBinaryData = null;

        while ($cursor < $length) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC CHPX picture metadata contains a truncated SPRM');
            }

            $sprm = self::u16($grpprl, $cursor);
            $cursor += 2;
            $operandByteCount = $this->sprmOperandByteCount($sprm, $grpprl, $cursor);
            if ($cursor + $operandByteCount > $length) {
                throw new \RuntimeException('Legacy DOC CHPX picture metadata contains a truncated SPRM operand');
            }

            $operandOffset = $cursor;
            $cursor += $operandByteCount;

            switch ($sprm) {
                case self::SPRM_CF_SPEC:
                    $hasSpecialCharacterFormatting = ord($grpprl[$operandOffset]) === 1;
                    $sourceSprms[] = 'sprmCFSpec';
                    break;
                case self::SPRM_C_PIC_LOCATION:
                    $dataStreamOffset = self::signed32(self::u32($grpprl, $operandOffset));
                    $sourceSprms[] = 'sprmCPicLocation';
                    break;
                case self::SPRM_CF_DATA:
                    $isBinaryData = ord($grpprl[$operandOffset]) === 1;
                    $sourceSprms[] = 'sprmCFData';
                    break;
            }
        }

        if ($dataStreamOffset === null) {
            if ($isBinaryData === true) {
                throw new \RuntimeException('Legacy DOC CHPX picture metadata marks binary data without sprmCPicLocation');
            }

            return [];
        }
        if ($dataStreamOffset < 0) {
            throw new \RuntimeException('Legacy DOC CHPX picture metadata contains a negative Data stream offset');
        }

        $sourceSprms = array_values(array_unique($sourceSprms));
        $record = [
            'source' => 'ChpxFkp',
            'sourceSprms' => $sourceSprms,
            'dataStreamOffset' => $dataStreamOffset,
            'hasSpecialCharacterFormatting' => $hasSpecialCharacterFormatting === true,
            'isBinaryData' => $isBinaryData === true,
            'dataStreamKind' => $isBinaryData === true ? 'binary-data' : 'picture',
            'canExposeBytes' => false,
            'extractionPolicy' => 'metadata-only-native-review',
        ];
        if ($hasSpecialCharacterFormatting !== true) {
            $record['missingSpecialCharacterFormatting'] = true;
        }

        return [$record];
    }

    private function sprmOperandByteCount(int $sprm, string $grpprl, int $operandOffset): int
    {
        $spra = ($sprm >> 13) & 0x07;

        return match ($spra) {
            0, 1 => 1,
            2, 4, 5 => 2,
            3 => 4,
            6 => $this->variableSprmOperandByteCount($grpprl, $operandOffset),
            7 => 3,
        };
    }

    private function variableSprmOperandByteCount(string $grpprl, int $operandOffset): int
    {
        if ($operandOffset >= strlen($grpprl)) {
            throw new \RuntimeException('Legacy DOC SPRM metadata contains a truncated variable SPRM operand');
        }

        return 1 + ord($grpprl[$operandOffset]);
    }

    /**
     * @return array{formats:list<array<string,mixed>>,overrides:list<array<string,mixed>>}
     */
    private function listTableReport(string $wordDocument, ?string $tableStream): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLF_LFO + 4) {
            return [
                'formats' => [],
                'overrides' => [],
            ];
        }

        $fcPlfLst = self::u32($wordDocument, self::FIB_FC_PLF_LST);
        $lcbPlfLst = self::u32($wordDocument, self::FIB_LCB_PLF_LST);
        $fcPlfLfo = self::u32($wordDocument, self::FIB_FC_PLF_LFO);
        $lcbPlfLfo = self::u32($wordDocument, self::FIB_LCB_PLF_LFO);
        if ($lcbPlfLst === 0 && $lcbPlfLfo === 0) {
            return [
                'formats' => [],
                'overrides' => [],
            ];
        }
        if ($lcbPlfLst === 0 && $lcbPlfLfo !== 0) {
            throw new \RuntimeException('Legacy DOC list-format overrides are present without list formats');
        }

        $formats = $this->parsePlfLst($tableStream, $fcPlfLst, $lcbPlfLst);
        $overrides = $lcbPlfLfo === 0
            ? []
            : $this->parsePlfLfo(
                $this->tableStreamSlice($tableStream, $fcPlfLfo, $lcbPlfLfo, 'PlfLfo list override table'),
                $formats
            );

        return [
            'formats' => $formats,
            'overrides' => $overrides,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parsePlfLst(string $tableStream, int $offset, int $length): array
    {
        $bytes = $this->tableStreamSlice($tableStream, $offset, $length, 'PlfLst list format table');
        if (strlen($bytes) < 2) {
            throw new \RuntimeException('Legacy DOC list format table is too short to contain cLst');
        }

        $listCount = self::signed16(self::u16($bytes, 0));
        if ($listCount < 0 || $listCount > 2048) {
            throw new \RuntimeException('Legacy DOC list format table contains an invalid list count');
        }
        $lstfByteCount = $listCount * 28;
        if (2 + $lstfByteCount !== $length) {
            throw new \RuntimeException('Legacy DOC list format table length does not match its LSTF count');
        }

        $formats = [];
        $seenLsids = [];
        $levelCursor = $offset + $length;
        for ($index = 0; $index < $listCount; $index++) {
            $lstfOffset = 2 + ($index * 28);
            $lsid = self::signed32(self::u32($bytes, $lstfOffset));
            if ($lsid === -1) {
                throw new \RuntimeException('Legacy DOC list format contains the reserved LSTF lsid value');
            }
            if (isset($seenLsids[$lsid])) {
                throw new \RuntimeException('Legacy DOC list format table contains duplicate LSTF identifiers');
            }
            $seenLsids[$lsid] = true;

            $styleLinks = [];
            for ($level = 0; $level < 9; $level++) {
                $istd = self::signed16(self::u16($bytes, $lstfOffset + 8 + ($level * 2)));
                if ($istd !== 0x0fff) {
                    $styleLinks[] = [
                        'level' => $level,
                        'istd' => $istd,
                    ];
                }
            }

            $flags = ord($bytes[$lstfOffset + 26]);
            if (($flags & 0xe0) !== 0) {
                throw new \RuntimeException('Legacy DOC list format table contains nonzero reserved LSTF flags');
            }

            $simple = ($flags & 0x01) !== 0;
            $levelCount = $simple ? 1 : 9;
            $levels = [];
            for ($level = 0; $level < $levelCount; $level++) {
                [$levelRecord, $levelCursor] = $this->parseLvl($tableStream, $levelCursor, $level, 'PlfLst');
                $levels[] = $levelRecord;
            }

            $format = [
                'index' => $index + 1,
                'lsid' => $lsid,
                'templateCode' => self::signed32(self::u32($bytes, $lstfOffset + 4)),
                'simple' => $simple,
                'autoNumber' => ($flags & 0x04) !== 0,
                'hybrid' => ($flags & 0x10) !== 0,
                'htmlCompatibilityFlags' => ord($bytes[$lstfOffset + 27]),
                'levelCount' => $levelCount,
                'canApplyNumbering' => false,
                'levels' => $levels,
            ];
            if ($styleLinks !== []) {
                $format['linkedStyleIstds'] = $styleLinks;
            }

            $formats[] = $format;
        }

        return $formats;
    }

    /**
     * @return array{0:array<string,mixed>,1:int}
     */
    private function parseLvl(string $bytes, int $offset, int $levelIndex, string $context): array
    {
        if ($offset + 30 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC list level record is truncated in ' . $context);
        }

        $startAt = self::signed32(self::u32($bytes, $offset));
        $nfc = ord($bytes[$offset + 4]);
        if (in_array($nfc, [0x08, 0x09, 0x0f, 0x13], true)) {
            throw new \RuntimeException('Legacy DOC list level uses a reserved number-format code');
        }
        $hasNumberSequence = !in_array($nfc, [0xff, 0x17], true);
        if ($hasNumberSequence && ($startAt < 0 || $startAt > 0x7fff)) {
            throw new \RuntimeException('Legacy DOC list level start-at value is outside the supported range');
        }

        $bits = ord($bytes[$offset + 5]);
        $jc = $bits & 0x03;
        if ($jc > 2) {
            throw new \RuntimeException('Legacy DOC list level uses an invalid justification value');
        }

        $placeholderOffsets = [];
        $previousPlaceholderOffset = 0;
        for ($index = 0; $index < 9; $index++) {
            $placeholderOffset = ord($bytes[$offset + 6 + $index]);
            if ($placeholderOffset === 0) {
                break;
            }
            if ($placeholderOffset <= $previousPlaceholderOffset) {
                throw new \RuntimeException('Legacy DOC list level placeholder offsets are duplicate or unsorted');
            }
            $previousPlaceholderOffset = $placeholderOffset;
            $placeholderOffsets[] = $placeholderOffset;
        }
        if (count($placeholderOffsets) > $levelIndex + 1) {
            throw new \RuntimeException('Legacy DOC list level declares too many placeholder offsets');
        }

        $follow = ord($bytes[$offset + 15]);
        if ($follow > 2) {
            throw new \RuntimeException('Legacy DOC list level uses an invalid follow-character value');
        }
        $fNoRestart = ($bits & 0x08) !== 0;
        $ilvlRestartLim = ord($bytes[$offset + 26]);
        if ($fNoRestart && $hasNumberSequence && $ilvlRestartLim > $levelIndex) {
            throw new \RuntimeException('Legacy DOC list level restart limit exceeds the current level');
        }

        $chpxBytes = ord($bytes[$offset + 24]);
        $papxBytes = ord($bytes[$offset + 25]);
        $cursor = $offset + 28;
        if ($cursor + $papxBytes + $chpxBytes + 2 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC list level property groups point outside the table stream');
        }
        $papxGrpprl = substr($bytes, $cursor, $papxBytes);
        $cursor += $papxBytes;
        $chpxGrpprl = substr($bytes, $cursor, $chpxBytes);
        $cursor += $chpxBytes;
        $numberTextCharacters = self::u16($bytes, $cursor);
        $cursor += 2;
        if ($numberTextCharacters > 255 || $cursor + ($numberTextCharacters * 2) > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC list level number text is invalid or truncated');
        }

        $numberTextCodes = [];
        for ($index = 0; $index < $numberTextCharacters; $index++) {
            $numberTextCodes[] = self::u16($bytes, $cursor + ($index * 2));
        }
        $cursor += $numberTextCharacters * 2;
        $malformedBulletNumberText = $nfc === 0x17 && ($numberTextCharacters !== 1 || $placeholderOffsets !== []);

        $placeholderLevels = [];
        foreach ($placeholderOffsets as $placeholderOffset) {
            if ($placeholderOffset < 1 || $placeholderOffset > $numberTextCharacters) {
                throw new \RuntimeException('Legacy DOC list level placeholder offset points outside the number text');
            }

            $placeholderLevel = $numberTextCodes[$placeholderOffset - 1] ?? 0;
            if ($placeholderLevel > $levelIndex) {
                throw new \RuntimeException('Legacy DOC list level placeholder references a deeper level');
            }
            $placeholderLevels[] = $placeholderLevel;
        }

        $record = [
            'level' => $levelIndex,
            'startAt' => $startAt,
            'numberFormatCode' => $nfc,
            'numberFormat' => $this->listNumberFormatName($nfc),
            'justification' => match ($jc) {
                0 => 'left',
                1 => 'center',
                2 => 'right',
            },
            'legalNumbering' => ($bits & 0x04) !== 0,
            'noRestart' => $fNoRestart,
            'indentSaved' => ($bits & 0x10) !== 0,
            'converted' => ($bits & 0x20) !== 0,
            'tentative' => ($bits & 0x80) !== 0,
            'follow' => match ($follow) {
                0 => 'tab',
                1 => 'space',
                2 => 'nothing',
            },
            'placeholderOffsets' => $placeholderOffsets,
            'placeholderLevels' => $placeholderLevels,
            'numberText' => $this->listNumberTextTemplate($numberTextCodes, $placeholderOffsets),
            'numberTextCharacterCount' => $numberTextCharacters,
            'malformedBulletNumberText' => $malformedBulletNumberText,
            'paragraphPropertyBytes' => $papxBytes,
            'characterPropertyBytes' => $chpxBytes,
            'restartLimitLevel' => $ilvlRestartLim,
            'htmlCompatibilityFlags' => ord($bytes[$offset + 27]),
            'canApplyNumbering' => false,
        ];
        if (($bits & 0x10) !== 0) {
            $record['savedIndentTwips'] = self::signed32(self::u32($bytes, $offset + 16));
        }
        if ($papxGrpprl !== '') {
            $paragraphProperties = $this->listLevelFormattingProperties(
                $this->parsePapxParagraphProperties($papxGrpprl),
                $context
            );
            if ($paragraphProperties !== []) {
                $record['paragraphProperties'] = $paragraphProperties;
                $record['paragraphPropertyCount'] = count($paragraphProperties);
                $record['paragraphPropertyExtractionPolicy'] = 'metadata-only-native-review';
            }
        }
        if ($chpxGrpprl !== '') {
            $textProperties = $this->listLevelFormattingProperties(
                $this->parseChpxTextProperties($chpxGrpprl),
                $context
            );
            if ($textProperties !== []) {
                $record['textProperties'] = $textProperties;
                $record['textPropertyCount'] = count($textProperties);
                $record['textPropertyExtractionPolicy'] = 'metadata-only-native-review';
            }
        }

        return [$record, $cursor];
    }

    /**
     * @param list<array<string,mixed>> $properties
     * @return list<array<string,mixed>>
     */
    private function listLevelFormattingProperties(array $properties, string $sourceTable): array
    {
        return array_map(
            static function (array $property) use ($sourceTable): array {
                $property['source'] = $sourceTable;
                $property['sourceRecord'] = 'LVL';

                return $property;
            },
            $properties
        );
    }

    /**
     * @param list<array<string,mixed>> $formats
     * @param list<array<string,mixed>> $overrides
     * @return array{paragraphProperties:int,textProperties:int}
     */
    private function listLevelFormattingCounts(array $formats, array $overrides): array
    {
        $paragraphProperties = 0;
        $textProperties = 0;
        foreach ($formats as $format) {
            foreach (($format['levels'] ?? []) as $level) {
                if (!is_array($level)) {
                    continue;
                }
                $paragraphProperties += count(is_array($level['paragraphProperties'] ?? null) ? $level['paragraphProperties'] : []);
                $textProperties += count(is_array($level['textProperties'] ?? null) ? $level['textProperties'] : []);
            }
        }
        foreach ($overrides as $override) {
            foreach (($override['levels'] ?? []) as $levelOverride) {
                if (!is_array($levelOverride) || !is_array($levelOverride['levelFormat'] ?? null)) {
                    continue;
                }
                $level = $levelOverride['levelFormat'];
                $paragraphProperties += count(is_array($level['paragraphProperties'] ?? null) ? $level['paragraphProperties'] : []);
                $textProperties += count(is_array($level['textProperties'] ?? null) ? $level['textProperties'] : []);
            }
        }

        return [
            'paragraphProperties' => $paragraphProperties,
            'textProperties' => $textProperties,
        ];
    }

    /**
     * @param list<int> $numberTextCodes
     * @param list<int> $placeholderOffsets
     */
    private function listNumberTextTemplate(array $numberTextCodes, array $placeholderOffsets): string
    {
        $placeholders = array_fill_keys($placeholderOffsets, true);
        $text = '';
        foreach ($numberTextCodes as $index => $code) {
            $position = $index + 1;
            if (isset($placeholders[$position])) {
                $text .= '%' . (string) ($code + 1);
                continue;
            }

            $text .= self::codepointToUtf8($code);
        }

        return $text;
    }

    private function listNumberFormatName(int $nfc): string
    {
        return match ($nfc) {
            0x00 => 'decimal',
            0x01 => 'upper-roman',
            0x02 => 'lower-roman',
            0x03 => 'upper-letter',
            0x04 => 'lower-letter',
            0x16 => 'leading-zero-decimal',
            0x17 => 'bullet',
            0xff => 'none',
            default => 'msonfc:' . (string) $nfc,
        };
    }

    /**
     * @param list<array<string,mixed>> $formats
     * @return list<array<string,mixed>>
     */
    private function parsePlfLfo(string $bytes, array $formats): array
    {
        if (strlen($bytes) < 4) {
            throw new \RuntimeException('Legacy DOC list override table is too short to contain lfoMac');
        }

        $formatByLsid = [];
        foreach ($formats as $format) {
            $formatByLsid[(int) ($format['lsid'] ?? 0)] = true;
        }

        $lfoMac = self::u32($bytes, 0);
        if ($lfoMac > 4096 || 4 + ($lfoMac * 16) > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC list override table contains an invalid LFO count');
        }

        $overrides = [];
        for ($index = 0; $index < $lfoMac; $index++) {
            $lfoOffset = 4 + ($index * 16);
            $lsid = self::signed32(self::u32($bytes, $lfoOffset));
            if (!isset($formatByLsid[$lsid])) {
                throw new \RuntimeException('Legacy DOC list override references an unknown list identifier');
            }
            $field = ord($bytes[$lfoOffset + 13]);
            if (!in_array($field, [0x00, 0xfc, 0xfd, 0xfe, 0xff], true)) {
                throw new \RuntimeException('Legacy DOC list override contains an invalid auto-number field code');
            }

            $overrides[] = [
                'ilfo' => $index + 1,
                'lsid' => $lsid,
                'overrideLevelCount' => ord($bytes[$lfoOffset + 12]),
                'autoNumberField' => match ($field) {
                    0xfc => 'AUTONUMLGL',
                    0xfd => 'AUTONUMOUT',
                    0xfe => 'AUTONUM',
                    default => null,
                },
                'htmlCompatibilityFlags' => ord($bytes[$lfoOffset + 14]),
                'levels' => [],
            ];
        }

        $cursor = 4 + ($lfoMac * 16);
        for ($index = 0; $index < $lfoMac; $index++) {
            if ($cursor + 4 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC list override table is truncated before LFOData');
            }

            $cp = self::u32($bytes, $cursor);
            $cursor += 4;
            if ($cp !== 0xffffffff) {
                $overrides[$index]['firstParagraphCp'] = $cp;
            }

            $seenLevels = [];
            $levelCount = (int) $overrides[$index]['overrideLevelCount'];
            if ($levelCount > 9) {
                throw new \RuntimeException('Legacy DOC list override declares too many level overrides');
            }
            for ($levelIndex = 0; $levelIndex < $levelCount; $levelIndex++) {
                [$levelOverride, $cursor] = $this->parseLfoLevelOverride($bytes, $cursor);
                $level = (int) $levelOverride['level'];
                if (isset($seenLevels[$level])) {
                    throw new \RuntimeException('Legacy DOC list override contains duplicate level overrides');
                }
                $seenLevels[$level] = true;
                $overrides[$index]['levels'][] = $levelOverride;
            }
        }

        if ($cursor !== strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC list override table contains trailing bytes');
        }

        return $overrides;
    }

    /**
     * @return array{0:array<string,mixed>,1:int}
     */
    private function parseLfoLevelOverride(string $bytes, int $offset): array
    {
        if ($offset + 8 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC list level override is truncated');
        }

        $startAt = self::signed32(self::u32($bytes, $offset));
        $bits = self::u32($bytes, $offset + 4);
        $level = $bits & 0x0f;
        $startAtOverride = ($bits & 0x10) !== 0;
        $formattingOverride = ($bits & 0x20) !== 0;
        if ($level > 8) {
            throw new \RuntimeException('Legacy DOC list level override references an invalid level');
        }
        if ($startAtOverride && !$formattingOverride && ($startAt < 0 || $startAt > 0x7fff)) {
            throw new \RuntimeException('Legacy DOC list level override start-at value is outside the supported range');
        }

        $record = [
            'level' => $level,
            'startAtOverride' => $startAtOverride,
            'formattingOverride' => $formattingOverride,
            'htmlCompatibilityFlags' => ($bits >> 6) & 0xff,
        ];
        if ($startAtOverride && !$formattingOverride) {
            $record['startAt'] = $startAt;
        }

        $cursor = $offset + 8;
        if ($formattingOverride) {
            [$record['levelFormat'], $cursor] = $this->parseLvl($bytes, $cursor, $level, 'LFOLVL');
        }

        return [$record, $cursor];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function sectionReport(string $wordDocument, ?string $tableStream, string $text): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCF_SED + 4) {
            return [];
        }

        $fcPlcfSed = self::u32($wordDocument, self::FIB_FC_PLCF_SED);
        $lcbPlcfSed = self::u32($wordDocument, self::FIB_LCB_PLCF_SED);
        if ($lcbPlcfSed === 0) {
            return [];
        }

        return $this->parsePlcfSed(
            $this->tableStreamSlice($tableStream, $fcPlcfSed, $lcbPlcfSed, 'PlcfSed'),
            $wordDocument,
            $text
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parsePlcfSed(string $bytes, string $wordDocument, string $text): array
    {
        $length = strlen($bytes);
        if ($length < 20 || (($length - 4) % 16) !== 0) {
            throw new \RuntimeException('Legacy DOC section descriptor PLC has an invalid length');
        }

        $sectionCount = intdiv($length - 4, 16);
        $cpCount = $sectionCount + 1;
        $cps = [];
        $previousCp = null;
        for ($index = 0; $index < $cpCount; $index++) {
            $cp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $cp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC section descriptor PLC contains duplicate or unsorted CPs');
            }
            if ($index === 0 && $cp !== 0) {
                throw new \RuntimeException('Legacy DOC section descriptor PLC must start at main-text CP 0');
            }

            $previousCp = $cp;
            $cps[] = $cp;
        }

        $characters = $this->unicodeCharacters($text);
        $textLength = count($characters);
        if ($cps[$sectionCount] < $textLength) {
            return [];
        }

        $sections = [];
        $sedOffset = $cpCount * 4;
        for ($index = 0; $index < $sectionCount; $index++) {
            $startCp = $cps[$index];
            $endCp = $cps[$index + 1];
            $hasSectionBreak = $index < $sectionCount - 1;
            if ($startCp > $textLength || ($hasSectionBreak && $endCp > $textLength)) {
                return [];
            }
            if ($hasSectionBreak) {
                $breakCharacter = $endCp > 0 ? ($characters[$endCp - 1] ?? '') : '';
                if ($breakCharacter !== "\f") {
                    return [];
                }
            }

            $offset = $sedOffset + ($index * 12);
            $fcSepx = self::signed32(self::u32($bytes, $offset + 2));
            $section = [
                'index' => $index + 1,
                'startCp' => $startCp,
                'endCp' => min($endCp, $textLength),
                'contentEndCp' => $hasSectionBreak ? $endCp - 1 : min($endCp, $textLength),
                'hasSectionBreak' => $hasSectionBreak,
                'hasSepx' => $fcSepx !== -1,
            ];

            if ($fcSepx !== -1) {
                $section += $this->readSepxProvenance($wordDocument, $fcSepx);
            }

            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @return array{sepxFc:int,sepxByteCount:int,sprmByteCount:int}
     */
    private function readSepxProvenance(string $wordDocument, int $fcSepx): array
    {
        if ($fcSepx < 0 || $fcSepx + 2 > strlen($wordDocument)) {
            throw new \RuntimeException('Legacy DOC section descriptor SEPX pointer points outside WordDocument');
        }

        $byteCount = self::signed16(self::u16($wordDocument, $fcSepx));
        if ($byteCount < 0 || $fcSepx + 2 + $byteCount > strlen($wordDocument)) {
            throw new \RuntimeException('Legacy DOC section descriptor SEPX byte count points outside WordDocument');
        }

        return [
            'sepxFc' => $fcSepx,
            'sepxByteCount' => $byteCount + 2,
            'sprmByteCount' => $byteCount,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function standardBookmarkReport(string $wordDocument, ?string $tableStream, string $text): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCF_BKL + 4) {
            return [];
        }

        $fcSttbfBkmk = self::u32($wordDocument, self::FIB_FC_STTBF_BKMK);
        $lcbSttbfBkmk = self::u32($wordDocument, self::FIB_LCB_STTBF_BKMK);
        $fcPlcfBkf = self::u32($wordDocument, self::FIB_FC_PLCF_BKF);
        $lcbPlcfBkf = self::u32($wordDocument, self::FIB_LCB_PLCF_BKF);
        $fcPlcfBkl = self::u32($wordDocument, self::FIB_FC_PLCF_BKL);
        $lcbPlcfBkl = self::u32($wordDocument, self::FIB_LCB_PLCF_BKL);
        if ($lcbSttbfBkmk === 0) {
            return [];
        }
        if ($lcbPlcfBkf === 0 || $lcbPlcfBkl === 0) {
            throw new \RuntimeException('Legacy DOC bookmark names are present without matching bookmark range PLCs');
        }

        $names = $this->parseSttbfBkmk($this->tableStreamSlice($tableStream, $fcSttbfBkmk, $lcbSttbfBkmk, 'SttbfBkmk'));
        $starts = $this->parsePlcfBkf($this->tableStreamSlice($tableStream, $fcPlcfBkf, $lcbPlcfBkf, 'PlcfBkf'));
        $endCps = $this->parsePlcfBkl($this->tableStreamSlice($tableStream, $fcPlcfBkl, $lcbPlcfBkl, 'PlcfBkl'));
        if (count($names) !== count($starts) || count($starts) !== count($endCps)) {
            throw new \RuntimeException('Legacy DOC bookmark tables do not contain parallel element counts');
        }

        $textLength = $this->textCharacterLength($text);
        $bookmarks = [];
        $seenIbkl = [];
        foreach ($starts as $index => $start) {
            $ibkl = (int) $start['ibkl'];
            if ($ibkl < 0 || $ibkl >= count($endCps)) {
                throw new \RuntimeException('Legacy DOC bookmark start points outside the bookmark end PLC');
            }
            if (isset($seenIbkl[$ibkl])) {
                throw new \RuntimeException('Legacy DOC bookmark start records reuse a bookmark end index');
            }
            $seenIbkl[$ibkl] = true;

            $startCp = (int) $start['startCp'];
            $endCp = (int) $endCps[$ibkl];
            if ($startCp > $endCp) {
                throw new \RuntimeException('Legacy DOC bookmark start CP is after its end CP');
            }

            $name = $names[$index];
            $bookmarks[] = [
                'name' => $name,
                'anchorId' => $name,
                'startCp' => $startCp,
                'endCp' => $endCp,
                'hidden' => str_starts_with($name, '_'),
                'bkc' => (int) $start['bkc'],
                'canAnchor' => $startCp >= 0 && $endCp <= $textLength,
            ];
        }

        return $bookmarks;
    }

    private function tableStreamSlice(string $tableStream, int $offset, int $length, string $label): string
    {
        if ($length <= 0 || $offset < 0 || $offset + $length > strlen($tableStream)) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' points outside the table stream');
        }

        return substr($tableStream, $offset, $length);
    }

    /**
     * @return list<string>
     */
    private function parseSttbfBkmk(string $bytes): array
    {
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('Legacy DOC bookmark name table is truncated');
        }
        if (self::u16($bytes, 0) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC bookmark name table must use extended strings');
        }
        $count = self::u16($bytes, 2);
        if ($count > 0x3ffb) {
            throw new \RuntimeException('Legacy DOC bookmark name table exceeds the standard bookmark limit');
        }
        if (self::u16($bytes, 4) !== 0) {
            throw new \RuntimeException('Legacy DOC bookmark name table must not contain extra data');
        }

        $cursor = 6;
        $names = [];
        $seen = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC bookmark name table is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters <= 0 || $characters >= 40) {
                throw new \RuntimeException('Legacy DOC bookmark name length is outside the supported range');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC bookmark name table points outside its string data');
            }

            $name = $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
            if ($name === '') {
                throw new \RuntimeException('Legacy DOC bookmark name table contains an empty decoded name');
            }

            $key = $this->normalizedBookmarkName($name);
            if (isset($seen[$key])) {
                throw new \RuntimeException('Legacy DOC bookmark name table contains duplicate names');
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }

    /**
     * @return list<array{startCp:int,ibkl:int,bkc:int}>
     */
    private function parsePlcfBkf(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 4 || (($length - 4) % 8) !== 0) {
            throw new \RuntimeException('Legacy DOC bookmark start PLC has an invalid length');
        }

        $count = intdiv($length - 4, 8);
        $dataOffset = ($count + 1) * 4;
        $entries = [];
        for ($index = 0; $index < $count; $index++) {
            $fbkfOffset = $dataOffset + ($index * 4);
            $entries[] = [
                'startCp' => self::u32($bytes, $index * 4),
                'ibkl' => self::u16($bytes, $fbkfOffset),
                'bkc' => self::u16($bytes, $fbkfOffset + 2),
            ];
        }

        return $entries;
    }

    /**
     * @return list<int>
     */
    private function parsePlcfBkl(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 4 || ($length % 4) !== 0) {
            throw new \RuntimeException('Legacy DOC bookmark end PLC has an invalid length');
        }

        $count = intdiv($length, 4) - 1;
        $cps = [];
        for ($index = 0; $index < $count; $index++) {
            $cps[] = self::u32($bytes, $index * 4);
        }

        return $cps;
    }

    private function normalizedBookmarkName(string $name): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    private function normalizedStyleName(string $name): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    private function textCharacterLength(string $text): int
    {
        return count($this->unicodeCharacters($text));
    }

    /**
     * @return list<string>
     */
    private function unicodeCharacters(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($characters)) {
            return array_values($characters);
        }

        return str_split($text);
    }

    /**
     * @param list<string> $characters
     */
    private function charactersToString(array $characters): string
    {
        return implode('', $characters);
    }

    private function readVariantBool(string $bytes, int $offset): ?bool
    {
        if ($offset + 2 > strlen($bytes)) {
            return null;
        }

        return self::u16($bytes, $offset) !== 0;
    }

    private function readFiletime(string $bytes, int $offset): ?string
    {
        if ($offset + 8 > strlen($bytes)) {
            return null;
        }

        $ticks = self::u64($bytes, $offset);
        if ($ticks === 0) {
            return null;
        }

        $seconds = intdiv($ticks, 10000000) - 11644473600;
        if ($seconds < 0) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s\Z', $seconds);
    }

    private static function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
            return "\xef\xbf\xbd";
        }
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3f))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }

    private static function signed16(int $value): int
    {
        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private static function signed32(int $value): int
    {
        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private static function readFloat32(string $bytes, int $offset): float
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of legacy DOC data');
        }
        $values = unpack('gvalue', substr($bytes, $offset, 4));

        return (float) $values['value'];
    }

    private static function readFloat64(string $bytes, int $offset): float
    {
        if ($offset < 0 || $offset + 8 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of legacy DOC data');
        }
        $values = unpack('evalue', substr($bytes, $offset, 8));

        return (float) $values['value'];
    }

    private static function formatCurrencyScaledInteger(int $scaledValue): string
    {
        if ($scaledValue === -PHP_INT_MAX - 1) {
            return '-922337203685477.5808';
        }

        $negative = $scaledValue < 0;
        if ($negative) {
            $scaledValue = -$scaledValue;
        }

        $whole = intdiv($scaledValue, 10000);
        $fraction = $scaledValue % 10000;

        return ($negative ? '-' : '') . (string) $whole . '.' . str_pad((string) $fraction, 4, '0', STR_PAD_LEFT);
    }

    private static function oleAutomationDateIso8601(string $bytes, int $offset): ?string
    {
        $days = self::readFloat64($bytes, $offset);
        if (!is_finite($days)) {
            return null;
        }

        $seconds = (int) round(($days - 25569.0) * 86400);

        return gmdate('Y-m-d\TH:i:s\Z', $seconds);
    }

    private static function signed64(string $bytes, int $offset): int
    {
        $low = self::u32($bytes, $offset);
        $high = self::u32($bytes, $offset + 4);
        if ($high < 0x80000000) {
            return self::u64($bytes, $offset);
        }
        if ($high === 0x80000000 && $low === 0) {
            return -PHP_INT_MAX - 1;
        }

        $inverseLow = (~$low) & 0xffffffff;
        $inverseHigh = (~$high) & 0xffffffff;
        $magnitude = ($inverseHigh * 4294967296) + $inverseLow + 1;

        return -$magnitude;
    }

    private static function unsigned64(string $bytes, int $offset): int|string
    {
        $low = self::u32($bytes, $offset);
        $high = self::u32($bytes, $offset + 4);
        if ($high <= intdiv(PHP_INT_MAX - $low, 4294967296)) {
            return ($high * 4294967296) + $low;
        }

        return self::unsigned64DecimalString($high, $low);
    }

    private static function unsigned64DecimalString(int $high, int $low): string
    {
        $digits = '';
        while ($high !== 0 || $low !== 0) {
            $quotientHigh = intdiv($high, 10);
            $currentLow = ($high % 10) * 4294967296 + $low;
            $quotientLow = intdiv($currentLow, 10);
            $digits = (string) ($currentLow % 10) . $digits;
            $high = $quotientHigh;
            $low = $quotientLow;
        }

        return $digits === '' ? '0' : $digits;
    }

    private static function formatClsid(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new \RuntimeException('Legacy DOC CLSID property value is truncated');
        }

        return sprintf(
            '%08x-%04x-%04x-%s-%s',
            self::u32($bytes, 0),
            self::u16($bytes, 4),
            self::u16($bytes, 6),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6))
        );
    }

    private static function u16(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 2 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of legacy DOC data');
        }
        $values = unpack('vvalue', substr($bytes, $offset, 2));

        return (int) $values['value'];
    }

    private static function u32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of legacy DOC data');
        }
        $values = unpack('Vvalue', substr($bytes, $offset, 4));

        return (int) $values['value'];
    }

    private static function u64(string $bytes, int $offset): int
    {
        $low = self::u32($bytes, $offset);
        $high = self::u32($bytes, $offset + 4);
        if ($high > intdiv(PHP_INT_MAX - $low, 4294967296)) {
            throw new \RuntimeException('Legacy DOC FILETIME exceeds PHP integer range');
        }

        return ($high * 4294967296) + $low;
    }
}
