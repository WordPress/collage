<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;
use PortLibs\Pandoc\ZipPackageEntry;
use PortLibs\Pandoc\GzipStream;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
$pathSegmentPositionReviews = static function (array $segments): array {
    $reviews = [];
    $segmentCount = count($segments);
    foreach ($segments as $segmentIndex => $segment) {
        $isFirst = $segmentIndex === 0;
        $isLast = $segmentIndex === $segmentCount - 1;
        $isOnly = $segmentCount === 1;
        $position = match (true) {
            $isOnly => 'only',
            $isFirst => 'first',
            $isLast => 'last',
            default => 'middle',
        };

        $reviews[] = [
            'pathSegmentIndex' => $segmentIndex,
            'segment' => $segment,
            'position' => $position,
            'isFirst' => $isFirst,
            'isLast' => $isLast,
            'isOnly' => $isOnly,
        ];
    }

    return $reviews;
};
$packNtfsFileTime = static function (int $timestamp): string {
    $filetime = ($timestamp + 11644473600) * 10000000;
    $low = $filetime & 0xffffffff;
    $high = intdiv($filetime, 0x100000000);

    return pack('VV', $low, $high);
};
$buildUnicodeExtra = static function (int $id, string $rawBytes, string $utf8Text) use ($crc32): string {
    $payload = pack('CV', 1, $crc32($rawBytes)) . $utf8Text;

    return pack('vv', $id, strlen($payload)) . $payload;
};
$buildNtfsExtra = static function (int $modifiedAt, int $accessedAt, int $createdAt) use ($packNtfsFileTime): string {
    $payload = pack('Vvv', 0, 0x0001, 24)
        . $packNtfsFileTime($modifiedAt)
        . $packNtfsFileTime($accessedAt)
        . $packNtfsFileTime($createdAt);

    return pack('vv', 0x000a, strlen($payload)) . $payload;
};
$packZipVariableUnsignedInteger = static function (int $value): string {
    if ($value < 0) {
        throw new \RuntimeException('ZIP variable unsigned integer value must be non-negative');
    }

    $bytes = '';
    do {
        $bytes .= chr($value & 0xff);
        $value = intdiv($value, 256);
    } while ($value > 0);

    return $bytes;
};
$buildUnixOwnerExtra = static function (int $uid, int $gid) use ($packZipVariableUnsignedInteger): string {
    $uidBytes = $packZipVariableUnsignedInteger($uid);
    $gidBytes = $packZipVariableUnsignedInteger($gid);
    $payload = chr(1)
        . chr(strlen($uidBytes))
        . $uidBytes
        . chr(strlen($gidBytes))
        . $gidBytes;

    return pack('vv', 0x7875, strlen($payload)) . $payload;
};
$buildWinZipAesExtra = static function (
    int $vendorVersion = 2,
    string $vendorId = 'AE',
    int $strength = 3,
    int $actualCompressionMethod = 8,
    string $trailingBytes = ''
): string {
    if (strlen($vendorId) !== 2) {
        throw new \RuntimeException('WinZip AES vendor id must be exactly two bytes');
    }

    $payload = pack('v', $vendorVersion)
        . $vendorId
        . chr($strength)
        . pack('v', $actualCompressionMethod)
        . $trailingBytes;

    return pack('vv', 0x9901, strlen($payload)) . $payload;
};

/**
 * @param list<array{
 *     name:string,
 *     data?:string,
 *     method?:int,
 *     localMethod?:int,
 *     centralMethod?:int,
 *     flags?:int,
 *     localFlags?:int,
 *     centralFlags?:int,
 *     descriptor?:bool,
 *     descriptorSignature?:bool,
 *     descriptorZip64?:bool,
 *     localSlack?:string,
 *     centralCrc?:int,
 *     centralCompressedSize?:int,
 *     centralUncompressedSize?:int,
 *     descriptorCrc?:int,
 *     descriptorCompressedSize?:int,
 *     descriptorUncompressedSize?:int,
 *     localCrc?:int,
 *     localCompressedSize?:int,
 *     localUncompressedSize?:int,
 *     modifiedTime?:int,
 *     modifiedDate?:int,
 *     localModifiedTime?:int,
 *     localModifiedDate?:int,
 *     localExtra?:string,
 *     centralExtra?:string,
 *     localName?:string,
 *     versionMadeBy?:int,
 *     diskStart?:int,
 *     internalAttributes?:int,
 *     externalAttributes?:int,
 *     comment?:string,
 *     versionNeededToExtract?:int,
 *     localVersionNeeded?:int,
 *     centralVersionNeeded?:int,
 *     centralLocalHeaderOffset?:int,
 *     centralIndex?:int
 * }> $entries
 */
$buildZipPackage = static function (array $entries, string $comment = '') use ($crc32): string {
    $body = '';
    $centralRecords = [];

    foreach ($entries as $entryIndex => $entry) {
        $name = $entry['name'];
        $localName = $entry['localName'] ?? $name;
        $data = $entry['data'] ?? '';
        $method = $entry['method'] ?? 0;
        $flags = $entry['flags'] ?? 0x0800;
        $descriptor = (bool) ($entry['descriptor'] ?? false);
        if ($descriptor) {
            $flags |= 0x0008;
        }

        $localMethod = $entry['localMethod'] ?? $method;
        $centralMethod = $entry['centralMethod'] ?? $method;
        $localFlags = $entry['localFlags'] ?? $flags;
        $centralFlags = $entry['centralFlags'] ?? $flags;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $actualCrc = $crc32($data);
        $centralCrc = $entry['centralCrc'] ?? $actualCrc;
        $centralCompressedSize = $entry['centralCompressedSize'] ?? $compressedSize;
        $centralUncompressedSize = $entry['centralUncompressedSize'] ?? $uncompressedSize;
        $offset = strlen($body);
        $modifiedTime = $entry['modifiedTime'] ?? 0;
        $modifiedDate = $entry['modifiedDate'] ?? 0;
        $localModifiedTime = $entry['localModifiedTime'] ?? $modifiedTime;
        $localModifiedDate = $entry['localModifiedDate'] ?? $modifiedDate;
        $localVersionNeeded = $entry['localVersionNeeded'] ?? ($entry['versionNeededToExtract'] ?? 20);
        $centralVersionNeeded = $entry['centralVersionNeeded'] ?? ($entry['versionNeededToExtract'] ?? 20);
        $localExtra = $entry['localExtra'] ?? '';
        $centralExtra = $entry['centralExtra'] ?? $localExtra;
        $localCrc = $entry['localCrc'] ?? ($descriptor ? 0 : $actualCrc);
        $localCompressedSize = $entry['localCompressedSize'] ?? ($descriptor ? 0 : $compressedSize);
        $localUncompressedSize = $entry['localUncompressedSize'] ?? ($descriptor ? 0 : $uncompressedSize);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            $localVersionNeeded,
            $localFlags,
            $localMethod,
            $localModifiedTime,
            $localModifiedDate,
            $localCrc,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($localName),
            strlen($localExtra)
        );
        $body .= $localName . $localExtra . $compressed;
        if ($descriptor) {
            if ($entry['descriptorSignature'] ?? true) {
                $body .= "PK\x07\x08";
            }
            if ($entry['descriptorZip64'] ?? false) {
                $body .= pack(
                    'VVVVV',
                    $entry['descriptorCrc'] ?? $actualCrc,
                    $entry['descriptorCompressedSize'] ?? $compressedSize,
                    0,
                    $entry['descriptorUncompressedSize'] ?? $uncompressedSize,
                    0
                );
            } else {
                $body .= pack(
                    'VVV',
                    $entry['descriptorCrc'] ?? $actualCrc,
                    $entry['descriptorCompressedSize'] ?? $compressedSize,
                    $entry['descriptorUncompressedSize'] ?? $uncompressedSize
                );
            }
        }
        $body .= $entry['localSlack'] ?? '';

        $entryComment = $entry['comment'] ?? '';
        $centralRecord = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            $entry['versionMadeBy'] ?? 0x0314,
            $centralVersionNeeded,
            $centralFlags,
            $centralMethod,
            $modifiedTime,
            $modifiedDate,
            $centralCrc,
            $centralCompressedSize,
            $centralUncompressedSize,
            strlen($name),
            strlen($centralExtra),
            strlen($entryComment),
            $entry['diskStart'] ?? 0,
            $entry['internalAttributes'] ?? 0,
            $entry['externalAttributes'] ?? 0,
            $entry['centralLocalHeaderOffset'] ?? $offset
        );
        $centralRecord .= $name . $centralExtra . $entryComment;
        $centralRecords[] = [
            'order' => $entry['centralIndex'] ?? $entryIndex,
            'index' => $entryIndex,
            'record' => $centralRecord,
        ];
    }

    usort(
        $centralRecords,
        static fn (array $left, array $right): int => [$left['order'], $left['index']] <=> [$right['order'], $right['index']]
    );
    $central = implode('', array_map(static fn (array $record): string => $record['record'], $centralRecords));
    $centralOffset = strlen($body);
    $centralSize = strlen($central);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralSize, $centralOffset, strlen($comment))
        . $comment;
};

$buildPrefixedPackage = static function () use ($crc32): string {
    $prefix = "MZhidden-review-stub\n";
    $name = 'word/document.xml';
    $data = '<w:document><w:p>prefixed package</w:p></w:document>';
    $crc = $crc32($data);
    $localHeaderOffset = strlen($prefix);
    $body = $prefix . pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0
    );
    $body .= $name . $data;
    $centralOffset = strlen($body);
    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0,
        0,
        0,
        0,
        0x81a40000,
        $localHeaderOffset
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), $centralOffset, 0);
};

$buildDuplicateLocalOffsetPackage = static function () use ($crc32, $buildUnicodeExtra): string {
    $rawName = 'word/media/review-image.bin';
    $data = "shared local entry should not be exposed twice\n";
    $crc = $crc32($data);
    $firstUnicodePath = $buildUnicodeExtra(0x7075, $rawName, 'word/media/review-one.png');
    $secondUnicodePath = $buildUnicodeExtra(0x7075, $rawName, 'word/media/review-two.png');

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($rawName),
        strlen($firstUnicodePath)
    );
    $body .= $rawName . $firstUnicodePath . $data;

    $central = '';
    foreach ([
        ['name' => $rawName, 'extra' => $firstUnicodePath],
        ['name' => $rawName, 'extra' => $secondUnicodePath],
    ] as $entry) {
        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0,
            0,
            0,
            0,
            $crc,
            strlen($data),
            strlen($data),
            strlen($entry['name']),
            strlen($entry['extra']),
            0,
            0,
            0,
            0x81a40000,
            0
        );
        $central .= $entry['name'] . $entry['extra'];
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 2, 2, strlen($central), strlen($body), 0);
};
$buildCentralDirectorySignaturePackage = static function (
    bool $includeInCentralDirectorySize = false,
    ?int $declaredSignatureLength = null
) use ($crc32): string {
    $name = 'word/document.xml';
    $data = '<w:document><w:body><w:p>digitally signed central directory</w:p></w:body></w:document>';
    $crc = $crc32($data);

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0
    );
    $body .= $name . $data;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        0x0800,
        0,
        0,
        0,
        $crc,
        strlen($data),
        strlen($data),
        strlen($name),
        0,
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name;
    $centralDirectorySignatureData = 'central-signature';
    $centralDirectorySignature = pack(
        'Vv',
        0x05054b50,
        $declaredSignatureLength ?? strlen($centralDirectorySignatureData)
    ) . $centralDirectorySignatureData;
    $centralDirectorySize = strlen($central) + ($includeInCentralDirectorySize ? strlen($centralDirectorySignature) : 0);

    return $body
        . $central
        . $centralDirectorySignature
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, $centralDirectorySize, strlen($body), 0);
};
$buildTraditionalEncryptedPackage = static function (
    string $encryptedHeader = "PKWAREHEAD12",
    string $ciphertext = "encrypted stored payload bytes\n"
) use ($crc32): string {
    $name = 'word/media/encrypted-review.bin';
    $plaintext = "review media bytes before traditional ZIP encryption\n";
    $encryptedPayload = $encryptedHeader . $ciphertext;
    $crc = $crc32($plaintext);
    $flags = 0x0801;

    $body = pack(
        'VvvvvvVVVvv',
        0x04034b50,
        20,
        $flags,
        0,
        0,
        0,
        $crc,
        strlen($encryptedPayload),
        strlen($plaintext),
        strlen($name),
        0
    );
    $body .= $name . $encryptedPayload;

    $central = pack(
        'VvvvvvvVVVvvvvvVV',
        0x02014b50,
        0x0314,
        20,
        $flags,
        0,
        0,
        0,
        $crc,
        strlen($encryptedPayload),
        strlen($plaintext),
        strlen($name),
        0,
        0,
        0,
        0,
        0x81a40000,
        0
    );
    $central .= $name;

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($body), 0);
};
$packUInt64 = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('ZIP64 fixture values must be non-negative');
    }

    return pack('VV', $value & 0xffffffff, intdiv($value, 0x100000000));
};
$buildZip64EndOfCentralDirectoryPackage = static function () use ($buildZipPackage, $packUInt64): string {
    $zip = $buildZipPackage([
        [
            'name' => 'word/document.xml',
            'data' => '<w:document><w:p>zip64 package metadata</w:p></w:document>',
            'method' => 8,
        ],
    ]);
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('EOCD fixture not found');
    }

    $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    $zip64EocdOffset = $eocdOffset;
    $zip64Eocd = "PK\x06\x06"
        . $packUInt64(44)
        . pack('vvVV', 45, 45, 0, 0)
        . $packUInt64(1)
        . $packUInt64(1)
        . $packUInt64((int) $centralDirectorySize)
        . $packUInt64((int) $centralDirectoryOffset);
    $zip64Locator = "PK\x06\x07"
        . pack('V', 0)
        . $packUInt64($zip64EocdOffset)
        . pack('V', 1);
    $eocd = substr($zip, $eocdOffset);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 8, 2);
    $eocd = substr_replace($eocd, pack('v', 0xffff), 10, 2);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 12, 4);
    $eocd = substr_replace($eocd, pack('V', 0xffffffff), 16, 4);

    return substr($zip, 0, $eocdOffset) . $zip64Eocd . $zip64Locator . $eocd;
};
$rewriteZip64EndOfCentralDirectoryPayloadSize = static function (string $zip, int $payloadSize) use ($packUInt64): string {
    $summary = ZipPackage::endOfCentralDirectoryPreflight($zip);
    $recordOffset = $summary['zip64EndOfCentralDirectoryOffset'];
    if ($recordOffset === null) {
        throw new RuntimeException('ZIP64 EOCD record fixture not found');
    }

    return substr_replace($zip, $packUInt64($payloadSize), $recordOffset + 4, 8);
};
$insertZip64EndOfCentralDirectoryExtensibleData = static function (string $zip, string $extraData) use ($packUInt64): string {
    $summary = ZipPackage::endOfCentralDirectoryPreflight($zip);
    $recordOffset = $summary['zip64EndOfCentralDirectoryOffset'];
    $locatorOffset = $summary['zip64EndOfCentralDirectoryLocatorOffset'];
    $payloadSize = $summary['zip64EndOfCentralDirectoryPayloadSize'];
    if ($recordOffset === null || $locatorOffset === null || $payloadSize === null) {
        throw new RuntimeException('ZIP64 EOCD record fixture not found');
    }

    $zip = substr_replace($zip, $packUInt64($payloadSize + strlen($extraData)), $recordOffset + 4, 8);

    return substr($zip, 0, $locatorOffset) . $extraData . substr($zip, $locatorOffset);
};
$rewriteEndOfCentralDirectory = static function (string $zip, array $fields): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('EOCD fixture not found');
    }

    $offsets = [
        'diskNumber' => 4,
        'centralDirectoryDisk' => 6,
        'diskEntryCount' => 8,
        'totalEntryCount' => 10,
        'centralDirectorySize' => 12,
        'centralDirectoryOffset' => 16,
    ];
    foreach ($fields as $field => $value) {
        if (!isset($offsets[$field]) || !is_int($value)) {
            throw new RuntimeException('Unsupported EOCD fixture field: ' . (string) $field);
        }

        $format = $field === 'centralDirectorySize' || $field === 'centralDirectoryOffset' ? 'V' : 'v';
        $zip = substr_replace($zip, pack($format, $value), $eocdOffset + $offsets[$field], $format === 'V' ? 4 : 2);
    }

    return $zip;
};
$rewriteFirstCentralLocalHeaderOffset = static function (string $zip, int $localHeaderOffset): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('EOCD fixture not found');
    }

    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    if (substr($zip, $centralDirectoryOffset, 4) !== "PK\x01\x02") {
        throw new RuntimeException('Central directory fixture not found');
    }

    return substr_replace($zip, pack('V', $localHeaderOffset), $centralDirectoryOffset + 42, 4);
};
$corruptZipEntryPayload = static function (string $zip, string $entryName, int $byteOffset = 0): string {
    $cursor = 0;
    $length = strlen($zip);

    while ($cursor + 30 <= $length && substr($zip, $cursor, 4) === "PK\x03\x04") {
        $compressedSize = unpack('Vvalue', substr($zip, $cursor + 18, 4))['value'];
        $nameLength = unpack('vvalue', substr($zip, $cursor + 26, 2))['value'];
        $extraLength = unpack('vvalue', substr($zip, $cursor + 28, 2))['value'];
        $nameStart = $cursor + 30;
        $dataStart = $nameStart + $nameLength + $extraLength;
        $name = substr($zip, $nameStart, $nameLength);

        if ($name === $entryName) {
            if ($compressedSize <= 0 || $byteOffset < 0 || $byteOffset >= $compressedSize) {
                throw new RuntimeException('Cannot corrupt ZIP fixture payload for ' . $entryName);
            }

            $payloadOffset = $dataStart + $byteOffset;
            $zip[$payloadOffset] = chr(ord($zip[$payloadOffset]) ^ 0xff);

            return $zip;
        }

        $cursor = $dataStart + $compressedSize;
    }

    throw new RuntimeException('ZIP fixture entry not found: ' . $entryName);
};

return [
    'reads current zip package central directory and stored deflated parts' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $zip = $buildZipPackage([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'method' => 0,
            ],
            [
                'name' => '_rels/.rels',
                'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
                'method' => 8,
                'comment' => 'root rels',
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Imported post</w:p></w:body></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ], 'package comment');

        $package = ZipPackage::fromString($zip);
        $entries = $package->entries();

        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/media/',
        ], $package->names());
        $t->same(4, count($entries));
        $t->true($entries[0] instanceof ZipPackageEntry);
        $t->same('[Content_Types].xml', $entries[0]->name);
        $t->same(0, $entries[0]->compressionMethod);
        $t->same($crc32('<Types><Default Extension="xml" ContentType="application/xml"/></Types>'), $entries[0]->crc32);
        $t->same(sprintf('%08x', $entries[0]->crc32), $entries[0]->crc32Hex());
        $t->same('_rels/.rels', $entries[1]->name);
        $t->same(8, $entries[1]->compressionMethod);
        $t->same('root rels', $entries[1]->comment);
        $t->true($entries[3]->isDirectory());
        $t->true($package->has('/word/document.xml'));
        $t->same('<Types><Default Extension="xml" ContentType="application/xml"/></Types>', $package->read('[Content_Types].xml'));
        $t->same('<Relationships><Relationship Target="word/document.xml"/></Relationships>', $package->read('_rels/.rels'));
        $t->same('<w:document><w:body><w:p>Imported post</w:p></w:body></w:document>', $package->read('/word/document.xml'));
        $t->same('', $package->read('word/media/'));
        $t->true($package->centralDirectoryOffset() > 0);
        $t->same('package comment', $package->packageComment());
        $t->same($zip, $package->bytes());
    },

    'reads zip packages whose package comment contains eocd signature bytes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>EOCD-looking comment bytes</w:p></w:body></w:document>';
        $comment = "PK\x05\x06" . str_repeat("\0", 18);
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
        ], $comment);
        $actualEocdOffset = strlen($zip) - 22 - strlen($comment);

        $archive = ZipPackage::endOfCentralDirectoryPreflight($zip);
        $package = ZipPackage::fromString($zip);
        $rawPreflight = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $t->same($actualEocdOffset, $archive['eocdOffset']);
        $t->same(1, $archive['totalEntryCount']);
        $t->same(strlen($comment), $archive['packageCommentLength']);
        $t->same($comment, $archive['packageComment']);
        $t->same(['word/document.xml'], $package->names());
        $t->same($comment, $package->packageComment());
        $t->same($documentXml, $package->read('/word/document.xml'));
        $t->same(true, $rawPreflight['canInstantiate']);
        $t->same($actualEocdOffset, $rawPreflight['archive']['eocdOffset']);
        $t->contains('package-or-entry-comments', implode(',', $rawPreflight['diagnostics']));
        $t->same(false, $rawPreflight['strictImport']['isValid']);
        $t->same(true, $rawPreflight['strictImport']['comments']['hasPackageComment']);
    },

    'exposes zip package entries in local header order for container preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => 'application/epub+zip',
                'method' => 0,
                'centralIndex' => 2,
            ],
            [
                'name' => 'META-INF/container.xml',
                'data' => '<container/>',
                'method' => 8,
                'centralIndex' => 0,
            ],
            [
                'name' => 'OEBPS/package.opf',
                'data' => '<package/>',
                'method' => 8,
                'centralIndex' => 1,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $localEntries = $package->localEntries();

        $t->same(['META-INF/container.xml', 'OEBPS/package.opf', 'mimetype'], $package->names());
        $t->same(['mimetype', 'META-INF/container.xml', 'OEBPS/package.opf'], $package->localNames());
        $t->same('mimetype', $localEntries[0]->name);
        $t->true($localEntries[0]->localHeaderOffset < $localEntries[1]->localHeaderOffset);
        $t->true($localEntries[1]->localHeaderOffset < $localEntries[2]->localHeaderOffset);
        $t->same('application/epub+zip', $package->read('/mimetype'));
        $t->same('<container/>', $package->read('/META-INF/container.xml'));
    },

    'preflights deterministic zip package manifest for shared package ingestion' => static function (TestRunner $t) use ($buildZipPackage, $crc32, $pathSegmentPositionReviews): void {
        $mimetype = 'application/epub+zip';
        $contentXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Manifest identity</p></body></html>';
        $zip = $buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => $mimetype,
                'method' => 0,
                'centralIndex' => 2,
            ],
            [
                'name' => 'OEBPS/content.xhtml',
                'data' => $contentXhtml,
                'method' => 8,
                'centralIndex' => 0,
            ],
            [
                'name' => 'OEBPS/images/',
                'data' => '',
                'method' => 0,
                'centralIndex' => 1,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $expectedCentralOrder = ['OEBPS/content.xhtml', 'OEBPS/images/', 'mimetype'];
        $expectedLocalOrder = ['mimetype', 'OEBPS/content.xhtml', 'OEBPS/images/'];
        $expectedEntries = [
            [
                'name' => 'OEBPS/content.xhtml',
                'isDirectory' => false,
                'caseFoldKey' => 'oebps/content.xhtml',
                'caseInsensitiveEquivalentEntryNames' => ['OEBPS/content.xhtml'],
                'hasCaseInsensitiveNameCollision' => false,
                'caseInsensitiveNameCollisionIssues' => [],
                'directoryRoot' => 'OEBPS/',
                'pathSegments' => ['OEBPS', 'content.xhtml'],
                'pathSegmentPositionReviews' => $pathSegmentPositionReviews(['OEBPS', 'content.xhtml']),
                'pathSegmentCount' => 2,
                'directoryDepth' => 1,
                'packagePartBaseName' => 'content.xhtml',
                'packagePartCaseFoldBaseName' => 'content.xhtml',
                'packagePartBaseNameStem' => 'content',
                'packagePartCaseFoldBaseNameStem' => 'content',
                'packagePartExtension' => 'xhtml',
                'packagePartExtensionKey' => 'xhtml',
                'extensionlessPackagePart' => false,
                'centralDirectoryIndex' => 0,
                'localHeaderOrder' => 1,
                'compressionMethod' => 8,
                'crc32Hex' => sprintf('%08x', $crc32($contentXhtml)),
                'compressedSize' => strlen(gzdeflate($contentXhtml)),
                'uncompressedSize' => strlen($contentXhtml),
                'expansionRatio' => strlen($contentXhtml) / strlen(gzdeflate($contentXhtml)),
                'compressedDataSha256' => hash('sha256', gzdeflate($contentXhtml)),
            ],
            [
                'name' => 'OEBPS/images/',
                'isDirectory' => true,
                'caseFoldKey' => 'oebps/images/',
                'caseInsensitiveEquivalentEntryNames' => ['OEBPS/images/'],
                'hasCaseInsensitiveNameCollision' => false,
                'caseInsensitiveNameCollisionIssues' => [],
                'directoryRoot' => 'OEBPS/',
                'pathSegments' => ['OEBPS', 'images'],
                'pathSegmentPositionReviews' => $pathSegmentPositionReviews(['OEBPS', 'images']),
                'pathSegmentCount' => 2,
                'directoryDepth' => 1,
                'packagePartBaseName' => 'images',
                'packagePartCaseFoldBaseName' => 'images',
                'packagePartBaseNameStem' => null,
                'packagePartCaseFoldBaseNameStem' => null,
                'packagePartExtension' => null,
                'packagePartExtensionKey' => '(directory)',
                'extensionlessPackagePart' => false,
                'centralDirectoryIndex' => 1,
                'localHeaderOrder' => 2,
                'compressionMethod' => 0,
                'crc32Hex' => sprintf('%08x', $crc32('')),
                'compressedSize' => 0,
                'uncompressedSize' => 0,
                'expansionRatio' => 0.0,
                'compressedDataSha256' => hash('sha256', ''),
            ],
            [
                'name' => 'mimetype',
                'isDirectory' => false,
                'caseFoldKey' => 'mimetype',
                'caseInsensitiveEquivalentEntryNames' => ['mimetype'],
                'hasCaseInsensitiveNameCollision' => false,
                'caseInsensitiveNameCollisionIssues' => [],
                'directoryRoot' => '/',
                'pathSegments' => ['mimetype'],
                'pathSegmentPositionReviews' => $pathSegmentPositionReviews(['mimetype']),
                'pathSegmentCount' => 1,
                'directoryDepth' => 0,
                'packagePartBaseName' => 'mimetype',
                'packagePartCaseFoldBaseName' => 'mimetype',
                'packagePartBaseNameStem' => 'mimetype',
                'packagePartCaseFoldBaseNameStem' => 'mimetype',
                'packagePartExtension' => null,
                'packagePartExtensionKey' => '(none)',
                'extensionlessPackagePart' => true,
                'centralDirectoryIndex' => 2,
                'localHeaderOrder' => 0,
                'compressionMethod' => 0,
                'crc32Hex' => sprintf('%08x', $crc32($mimetype)),
                'compressedSize' => strlen($mimetype),
                'uncompressedSize' => strlen($mimetype),
                'expansionRatio' => 1.0,
                'compressedDataSha256' => hash('sha256', $mimetype),
            ],
        ];
        $expectedEntries = array_map(static function (array $entry) use ($manifest, $zip, $expectedCentralOrder, $expectedLocalOrder): array {
            foreach ($manifest['entries'] as $manifestEntry) {
                if ($manifestEntry['name'] !== $entry['name']) {
                    continue;
                }

                $centralDirectoryRecordBytes = $manifestEntry['centralDirectoryRecordEnd']
                    - $manifestEntry['centralDirectoryRecordOffset'];
                $localHeaderOrderDelta = $entry['localHeaderOrder'] - $entry['centralDirectoryIndex'];
                $localHeaderOrderRelation = $localHeaderOrderDelta < 0
                    ? 'local-before-central-order'
                    : ($localHeaderOrderDelta === 0 ? 'same-order' : 'local-after-central-order');

                return [
                    'name' => $entry['name'],
                    'isDirectory' => $entry['isDirectory'],
                    'caseFoldKey' => $entry['caseFoldKey'],
                    'caseInsensitiveEquivalentEntryNames' => $entry['caseInsensitiveEquivalentEntryNames'],
                    'hasCaseInsensitiveNameCollision' => $entry['hasCaseInsensitiveNameCollision'],
                    'caseInsensitiveNameCollisionIssues' => $entry['caseInsensitiveNameCollisionIssues'],
                    'versionMadeBy' => 0x0314,
                    'madeByHostSystem' => 3,
                    'madeByHostSystemName' => 'unix',
                    'madeByVersion' => 20,
                    'versionNeededToExtract' => 20,
                    'localVersionNeededToExtract' => 20,
                    'minimumVersionNeededToExtract' => $entry['compressionMethod'] === 8 ? 20 : 10,
                    'localMinimumVersionNeededToExtract' => $entry['compressionMethod'] === 8 ? 20 : 10,
                    'versionNeededToExtractMatchesLocalHeader' => true,
                    'versionNeededToExtractMeetsFeatureMinimum' => true,
                    'localVersionNeededToExtractMeetsFeatureMinimum' => true,
                    'versionNeededToExtractExceedsBoundedReader' => false,
                    'localVersionNeededToExtractExceedsBoundedReader' => false,
                    'creatorVersionMeetsNeeded' => true,
                    'creatorVersionComparison' => 'equals-needed',
                    'creatorVersionDelta' => 0,
                    'creatorHostSystemIsKnown' => true,
                    'creatorHostSystemIssues' => [],
                    'directoryRoot' => $entry['directoryRoot'],
                    'entryNameBytes' => strlen($entry['name']),
                    'entryNameLengthBucket' => strlen($entry['name']) <= 15
                        ? 'up-to-15-bytes'
                        : '16-to-63-bytes',
                    'pathSegments' => $entry['pathSegments'],
                    'pathSegmentPositionReviews' => $entry['pathSegmentPositionReviews'],
                    'pathSegmentCount' => $entry['pathSegmentCount'],
                    'directoryDepth' => $entry['directoryDepth'],
                    'packagePartBaseName' => $entry['packagePartBaseName'],
                    'packagePartCaseFoldBaseName' => $entry['packagePartCaseFoldBaseName'],
                    'packagePartBaseNameStem' => $entry['packagePartBaseNameStem'],
                    'packagePartCaseFoldBaseNameStem' => $entry['packagePartCaseFoldBaseNameStem'],
                    'packagePartExtension' => $entry['packagePartExtension'],
                    'packagePartExtensionKey' => $entry['packagePartExtensionKey'],
                    'extensionlessPackagePart' => $entry['extensionlessPackagePart'],
                    'centralDirectoryIndex' => $entry['centralDirectoryIndex'],
                    'localHeaderOrder' => $entry['localHeaderOrder'],
                    'localHeaderOrderDelta' => $localHeaderOrderDelta,
                    'localHeaderOrderDisplacement' => abs($localHeaderOrderDelta),
                    'localHeaderOrderRelation' => $localHeaderOrderRelation,
                    'localHeaderOrderMatchesCentralDirectoryOrder' => $localHeaderOrderDelta === 0,
                    'localHeaderNameAtCentralDirectoryIndex' => $expectedLocalOrder[$entry['centralDirectoryIndex']] ?? null,
                    'centralDirectoryNameAtLocalHeaderOrder' => $expectedCentralOrder[$entry['localHeaderOrder']] ?? null,
                    'compressionMethod' => $entry['compressionMethod'],
                    'crc32Hex' => $entry['crc32Hex'],
                    'compressedSize' => $entry['compressedSize'],
                    'uncompressedSize' => $entry['uncompressedSize'],
                    'expansionRatio' => $entry['expansionRatio'],
                    'localHeaderSha256' => hash(
                        'sha256',
                        substr($zip, $manifestEntry['localHeaderOffset'], $manifestEntry['localHeaderLength'])
                    ),
                    'localHeaderFixedHeaderBytes' => 30,
                    'localHeaderVariableFieldBytes' => $manifestEntry['localHeaderVariableFieldBytes'],
                    'localHeaderVariableFieldSha256' => hash(
                        'sha256',
                        substr(
                            $zip,
                            $manifestEntry['localHeaderVariableFieldOffset'],
                            $manifestEntry['localHeaderVariableFieldBytes']
                        )
                    ),
                    'localHeaderRawNameBytes' => strlen($entry['name']),
                    'localHeaderRawNameSha256' => hash('sha256', $entry['name']),
                    'localHeaderExtraFieldBytes' => 0,
                    'localHeaderExtraFieldSha256' => hash('sha256', ''),
                    'localHeaderReviewFieldBytes' => 0,
                    'localRecordBytes' => $manifestEntry['localRecordBytes'],
                    'localRecordSha256' => hash(
                        'sha256',
                        substr($zip, $manifestEntry['localRecordOffset'], $manifestEntry['localRecordBytes'])
                    ),
                    'compressedDataSha256' => $entry['compressedDataSha256'],
                    'usesDataDescriptor' => $manifestEntry['usesDataDescriptor'],
                    'dataDescriptorBytes' => $manifestEntry['dataDescriptorBytes'],
                    'dataDescriptorSha256' => $manifestEntry['dataDescriptorSha256'],
                    'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
                    'centralDirectoryRecordSha256' => hash(
                        'sha256',
                        substr($zip, $manifestEntry['centralDirectoryRecordOffset'], $centralDirectoryRecordBytes)
                    ),
                    'centralDirectoryFixedHeaderBytes' => 46,
                    'centralDirectoryVariableFieldBytes' => $manifestEntry['centralDirectoryVariableFieldBytes'],
                    'centralDirectoryVariableFieldSha256' => hash(
                        'sha256',
                        substr(
                            $zip,
                            $manifestEntry['centralDirectoryVariableFieldOffset'],
                            $manifestEntry['centralDirectoryVariableFieldBytes']
                        )
                    ),
                    'centralDirectoryRawNameBytes' => $manifestEntry['centralDirectoryRawNameBytes'],
                    'centralDirectoryRawNameSha256' => hash('sha256', $entry['name']),
                    'centralDirectoryExtraFieldBytes' => $manifestEntry['centralDirectoryExtraFieldBytes'],
                    'centralDirectoryExtraFieldSha256' => hash('sha256', ''),
                    'centralDirectoryRawCommentBytes' => $manifestEntry['centralDirectoryRawCommentBytes'],
                    'centralDirectoryRawCommentSha256' => hash('sha256', ''),
                    'centralDirectoryReviewFieldBytes' => $manifestEntry['centralDirectoryReviewFieldBytes'],
                    'sourceRecordBytes' => $manifestEntry['localRecordBytes'] + $centralDirectoryRecordBytes,
                ];
            }

            throw new RuntimeException('Expected manifest entry is missing from source provenance fixture');
        }, $expectedEntries);
        $expectedEntriesByName = [];
        foreach ($expectedEntries as $entry) {
            $expectedEntriesByName[$entry['name']] = $entry;
        }
        $expectedSizeEntry = static fn (array $entry): array => [
            'name' => $entry['name'],
            'compressionMethod' => $entry['compressionMethod'],
            'isDirectory' => $entry['isDirectory'],
            'compressedSize' => $entry['compressedSize'],
            'uncompressedSize' => $entry['uncompressedSize'],
            'expansionRatio' => $entry['expansionRatio'],
        ];
        $expectedExpansionRatio = (strlen($contentXhtml) + strlen($mimetype))
            / (strlen(gzdeflate($contentXhtml)) + strlen($mimetype));
        $expectedLargestEntry = $expectedSizeEntry($expectedEntriesByName['OEBPS/content.xhtml']);
        $expectedZeroByteEntries = [$expectedSizeEntry($expectedEntriesByName['OEBPS/images/'])];
        $expectedUnknownExpansionRatioEntries = [];
        $expectedExpansionRatioBucketSummaries = [
            [
                'expansionRatioBucket' => 'zero-byte',
                'minExpansionRatio' => 0.0,
                'maxExpansionRatio' => 0.0,
                'entryCount' => 1,
                'fileEntryCount' => 0,
                'directoryEntryCount' => 1,
                'unknownExpansionRatioEntryCount' => 0,
                'compressedBytes' => 0,
                'uncompressedBytes' => 0,
                'localRecordBytes' => $expectedEntriesByName['OEBPS/images/']['localRecordBytes'],
                'sourceRecordBytes' => $expectedEntriesByName['OEBPS/images/']['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'directoryRoots' => ['OEBPS/'],
                'compressionMethodNames' => ['stored'],
                'entryNames' => ['OEBPS/images/'],
                'largestExpansionRatioEntryName' => 'OEBPS/images/',
                'largestExpansionRatio' => 0.0,
            ],
            [
                'expansionRatioBucket' => 'up-to-1x',
                'minExpansionRatio' => 0.0,
                'maxExpansionRatio' => 1.0,
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'unknownExpansionRatioEntryCount' => 0,
                'compressedBytes' => strlen($mimetype),
                'uncompressedBytes' => strlen($mimetype),
                'localRecordBytes' => $expectedEntriesByName['mimetype']['localRecordBytes'],
                'sourceRecordBytes' => $expectedEntriesByName['mimetype']['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'directoryRoots' => ['/'],
                'compressionMethodNames' => ['stored'],
                'entryNames' => ['mimetype'],
                'largestExpansionRatioEntryName' => 'mimetype',
                'largestExpansionRatio' => 1.0,
            ],
            [
                'expansionRatioBucket' => '1x-to-10x',
                'minExpansionRatio' => 1.0,
                'maxExpansionRatio' => 10.0,
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'unknownExpansionRatioEntryCount' => 0,
                'compressedBytes' => strlen(gzdeflate($contentXhtml)),
                'uncompressedBytes' => strlen($contentXhtml),
                'localRecordBytes' => $expectedEntriesByName['OEBPS/content.xhtml']['localRecordBytes'],
                'sourceRecordBytes' => $expectedEntriesByName['OEBPS/content.xhtml']['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'directoryRoots' => ['OEBPS/'],
                'compressionMethodNames' => ['deflated'],
                'entryNames' => ['OEBPS/content.xhtml'],
                'largestExpansionRatioEntryName' => 'OEBPS/content.xhtml',
                'largestExpansionRatio' => strlen($contentXhtml) / strlen(gzdeflate($contentXhtml)),
            ],
        ];
        $expectedExpansionRatioBuckets = array_map(
            static fn (array $summary): string => $summary['expansionRatioBucket'],
            $expectedExpansionRatioBucketSummaries
        );
        $expectedCompressionMethodSummaries = [
            [
                'compressionMethod' => 0,
                'compressionMethodName' => 'stored',
                'entryCount' => 2,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 1,
                'compressedBytes' => strlen($mimetype),
                'uncompressedBytes' => strlen($mimetype),
                'localRecordBytes' => $expectedEntriesByName['OEBPS/images/']['localRecordBytes']
                    + $expectedEntriesByName['mimetype']['localRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
            ],
            [
                'compressionMethod' => 8,
                'compressionMethodName' => 'deflated',
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen(gzdeflate($contentXhtml)),
                'uncompressedBytes' => strlen($contentXhtml),
                'localRecordBytes' => $expectedEntriesByName['OEBPS/content.xhtml']['localRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
            ],
        ];
        $expectedGeneralPurposeFlagSummaries = [
            [
                'generalPurposeFlags' => 0x0800,
                'generalPurposeFlagsHex' => '0800',
                'flagNames' => ['utf-8-names'],
                'unsupportedFlagBits' => 0,
                'unsupportedFlagBitsHex' => '0000',
                'isSupportedByReader' => true,
                'usesUtf8Names' => true,
                'usesDataDescriptor' => false,
                'deflateOptionFlags' => 0,
                'deflateOptionName' => null,
                'entryCount' => 3,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 1,
                'compressedBytes' => strlen(gzdeflate($contentXhtml)) + strlen($mimetype),
                'uncompressedBytes' => strlen($contentXhtml) + strlen($mimetype),
                'localRecordBytes' => array_sum(array_column($expectedEntries, 'localRecordBytes')),
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'entryNames' => $expectedCentralOrder,
            ],
        ];
        $expectedVersionNeededToExtractSummaries = [
            [
                'versionNeededToExtract' => 20,
                'entryCount' => 3,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 1,
                'compressedBytes' => strlen(gzdeflate($contentXhtml)) + strlen($mimetype),
                'uncompressedBytes' => strlen($contentXhtml) + strlen($mimetype),
                'localRecordBytes' => array_sum(array_column($expectedEntries, 'localRecordBytes')),
                'sourceRecordBytes' => array_sum(array_column($expectedEntries, 'sourceRecordBytes')),
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'minimumVersionNeededToExtracts' => [10, 20],
                'compressionMethodNames' => ['deflated', 'stored'],
                'entryNames' => $expectedCentralOrder,
            ],
        ];
        $expectedCreatorVersionComparisonCounts = [
            'below-needed' => 0,
            'equals-needed' => 3,
            'above-needed' => 0,
        ];
        $expectedCreatorHostSystemSummaries = [
            [
                'madeByHostSystem' => 3,
                'madeByHostSystemName' => 'unix',
                'isKnown' => true,
                'entryCount' => 3,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 1,
                'compressedBytes' => strlen(gzdeflate($contentXhtml)) + strlen($mimetype),
                'uncompressedBytes' => strlen($contentXhtml) + strlen($mimetype),
                'localRecordBytes' => array_sum(array_column($expectedEntries, 'localRecordBytes')),
                'creatorVersionBelowNeededEntryCount' => 0,
                'entryNames' => $expectedCentralOrder,
            ],
        ];
        $expectedDirectoryRootSummaries = [
            [
                'directoryRoot' => '/',
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen($mimetype),
                'uncompressedBytes' => strlen($mimetype),
                'localRecordBytes' => $expectedEntriesByName['mimetype']['localRecordBytes'],
                'sourceRecordBytes' => $expectedEntriesByName['mimetype']['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'entryNames' => ['mimetype'],
            ],
            [
                'directoryRoot' => 'OEBPS/',
                'entryCount' => 2,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 1,
                'compressedBytes' => strlen(gzdeflate($contentXhtml)),
                'uncompressedBytes' => strlen($contentXhtml),
                'localRecordBytes' => $expectedEntriesByName['OEBPS/content.xhtml']['localRecordBytes']
                    + $expectedEntriesByName['OEBPS/images/']['localRecordBytes'],
                'sourceRecordBytes' => $expectedEntriesByName['OEBPS/content.xhtml']['sourceRecordBytes']
                    + $expectedEntriesByName['OEBPS/images/']['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'entryNames' => ['OEBPS/content.xhtml', 'OEBPS/images/'],
            ],
        ];
        $expectedPackagePartExtensionSummaries = [
            [
                'extensionKey' => '(none)',
                'packagePartExtension' => null,
                'fileEntryCount' => 1,
                'compressedBytes' => strlen($mimetype),
                'uncompressedBytes' => strlen($mimetype),
                'localRecordBytes' => $expectedEntriesByName['mimetype']['localRecordBytes'],
                'sourceRecordBytes' => $expectedEntriesByName['mimetype']['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'entryNames' => ['mimetype'],
            ],
            [
                'extensionKey' => 'xhtml',
                'packagePartExtension' => 'xhtml',
                'fileEntryCount' => 1,
                'compressedBytes' => strlen(gzdeflate($contentXhtml)),
                'uncompressedBytes' => strlen($contentXhtml),
                'localRecordBytes' => $expectedEntriesByName['OEBPS/content.xhtml']['localRecordBytes'],
                'sourceRecordBytes' => $expectedEntriesByName['OEBPS/content.xhtml']['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'entryNames' => ['OEBPS/content.xhtml'],
            ],
        ];
        $expectedPackagePartExtensions = ['xhtml'];
        $expectedDirectoryRoots = array_map(
            static fn (array $summary): string => $summary['directoryRoot'],
            $expectedDirectoryRootSummaries
        );
        $expectedLocalHeaderOrderRelationCounts = [
            'same-order' => 0,
            'local-before-central-order' => 1,
            'local-after-central-order' => 2,
            'missing-local-header-order' => 0,
        ];
        $expectedLocalHeaderOrderDisplacementEntries = [
            [
                'name' => 'OEBPS/content.xhtml',
                'centralDirectoryIndex' => 0,
                'localHeaderOrder' => 1,
                'localHeaderOrderDelta' => 1,
                'localHeaderOrderDisplacement' => 1,
                'localHeaderOrderRelation' => 'local-after-central-order',
                'localHeaderNameAtCentralDirectoryIndex' => 'mimetype',
                'centralDirectoryNameAtLocalHeaderOrder' => 'OEBPS/images/',
            ],
            [
                'name' => 'OEBPS/images/',
                'centralDirectoryIndex' => 1,
                'localHeaderOrder' => 2,
                'localHeaderOrderDelta' => 1,
                'localHeaderOrderDisplacement' => 1,
                'localHeaderOrderRelation' => 'local-after-central-order',
                'localHeaderNameAtCentralDirectoryIndex' => 'OEBPS/content.xhtml',
                'centralDirectoryNameAtLocalHeaderOrder' => 'mimetype',
            ],
            [
                'name' => 'mimetype',
                'centralDirectoryIndex' => 2,
                'localHeaderOrder' => 0,
                'localHeaderOrderDelta' => -2,
                'localHeaderOrderDisplacement' => 2,
                'localHeaderOrderRelation' => 'local-before-central-order',
                'localHeaderNameAtCentralDirectoryIndex' => 'OEBPS/images/',
                'centralDirectoryNameAtLocalHeaderOrder' => 'OEBPS/content.xhtml',
            ],
        ];
        $expectedHash = hash('sha256', json_encode([
            'manifestVersion' => 'zip-package-manifest-v1',
            'packageSource' => $manifest['packageSource'],
            'packageByteLayout' => $manifest['packageByteLayout'],
            'packageByteLayoutVersion' => $manifest['packageByteLayoutVersion'],
            'packageByteLayoutIssueCount' => $manifest['packageByteLayoutIssueCount'],
            'packageByteLayoutIssues' => $manifest['packageByteLayoutIssues'],
            'packageByteLayoutIsLocalRegionContiguous' => $manifest['packageByteLayoutIsLocalRegionContiguous'],
            'packageByteLayoutIsArchiveLayoutContiguous' => $manifest['packageByteLayoutIsArchiveLayoutContiguous'],
            'packageByteLayoutUnclaimedLocalBytes' => $manifest['packageByteLayoutUnclaimedLocalBytes'],
            'packageByteLayoutInterEntryGapCount' => $manifest['packageByteLayoutInterEntryGapCount'],
            'packageByteLayoutUnaccountedArchiveBytes' => $manifest['packageByteLayoutUnaccountedArchiveBytes'],
            'packageByteLayoutTrailingByteCount' => $manifest['packageByteLayoutTrailingByteCount'],
            'archiveBytes' => $manifest['archiveBytes'],
            'archiveSha256' => $manifest['archiveSha256'],
            'centralDirectoryOffset' => $manifest['centralDirectoryOffset'],
            'centralDirectoryBytes' => $manifest['centralDirectoryBytes'],
            'centralDirectorySha256' => $manifest['centralDirectorySha256'],
            'endOfCentralDirectoryOffset' => $manifest['endOfCentralDirectoryOffset'],
            'endOfCentralDirectoryBytes' => $manifest['endOfCentralDirectoryBytes'],
            'endOfCentralDirectorySha256' => $manifest['endOfCentralDirectorySha256'],
            'endOfCentralDirectoryFixedFields' => $manifest['endOfCentralDirectoryFixedFields'],
            'endOfCentralDirectoryFixedFieldIssueCount' => $manifest['endOfCentralDirectoryFixedFieldIssueCount'],
            'hasEndOfCentralDirectoryFixedFieldIssues' => $manifest['hasEndOfCentralDirectoryFixedFieldIssues'],
            'endOfCentralDirectoryFixedFieldIssues' => $manifest['endOfCentralDirectoryFixedFieldIssues'],
            'packageCommentOffset' => $manifest['packageCommentOffset'],
            'packageCommentBytes' => $manifest['packageCommentBytes'],
            'packageCommentEnd' => $manifest['packageCommentEnd'],
            'packageCommentSha256' => $manifest['packageCommentSha256'],
            'packageCommentPreviewHex' => $manifest['packageCommentPreviewHex'],
            'packageCommentPreviewByteCount' => $manifest['packageCommentPreviewByteCount'],
            'packageCommentByteExposurePolicy' => $manifest['packageCommentByteExposurePolicy'],
            'canExposePackageCommentBytes' => $manifest['canExposePackageCommentBytes'],
            'hasPackageComment' => $manifest['hasPackageComment'],
            'hasCentralDirectorySignature' => $manifest['hasCentralDirectorySignature'],
            'centralDirectorySignatureOffset' => $manifest['centralDirectorySignatureOffset'],
            'centralDirectorySignatureDataOffset' => $manifest['centralDirectorySignatureDataOffset'],
            'centralDirectorySignatureEnd' => $manifest['centralDirectorySignatureEnd'],
            'centralDirectorySignatureBytes' => $manifest['centralDirectorySignatureBytes'],
            'centralDirectorySignatureRecordBytes' => $manifest['centralDirectorySignatureRecordBytes'],
            'centralDirectorySignaturePreviewHex' => $manifest['centralDirectorySignaturePreviewHex'],
            'centralDirectorySignaturePreviewByteCount' => $manifest['centralDirectorySignaturePreviewByteCount'],
            'centralDirectorySignatureSha256' => $manifest['centralDirectorySignatureSha256'],
            'centralDirectorySignatureLocation' => $manifest['centralDirectorySignatureLocation'],
            'centralDirectorySignatureVerification' => $manifest['centralDirectorySignatureVerification'],
            'centralDirectorySignatureByteExposurePolicy' => $manifest['centralDirectorySignatureByteExposurePolicy'],
            'centralDirectorySignatureCanExposeBytes' => $manifest['centralDirectorySignatureCanExposeBytes'],
            'centralDirectoryOrderNames' => $expectedCentralOrder,
            'localHeaderOrderNames' => $expectedLocalOrder,
            'localHeaderOrderRelationCounts' => $expectedLocalHeaderOrderRelationCounts,
            'localHeaderOrderMatchCount' => 0,
            'localHeaderOrderDisplacementEntryCount' => count($expectedLocalHeaderOrderDisplacementEntries),
            'maxLocalHeaderOrderDisplacement' => 2,
            'localHeaderOrderDisplacementEntries' => $expectedLocalHeaderOrderDisplacementEntries,
            'localHeaderBytes' => (30 * count($expectedEntries))
                + strlen('OEBPS/content.xhtml')
                + strlen('OEBPS/images/')
                + strlen('mimetype'),
            'localHeaderFixedHeaderBytes' => 30 * count($expectedEntries),
            'localHeaderFixedFieldEntryCount' => $manifest['localHeaderFixedFieldEntryCount'],
            'localHeaderFixedFieldIssueEntryCount' => $manifest['localHeaderFixedFieldIssueEntryCount'],
            'hasLocalHeaderFixedFieldIssues' => $manifest['hasLocalHeaderFixedFieldIssues'],
            'localHeaderFixedFieldIssues' => $manifest['localHeaderFixedFieldIssues'],
            'localHeaderFixedFieldEntries' => $manifest['localHeaderFixedFieldEntries'],
            'localHeaderFixedFieldIssueEntries' => $manifest['localHeaderFixedFieldIssueEntries'],
            'localHeaderVariableFieldBytes' => strlen('OEBPS/content.xhtml')
                + strlen('OEBPS/images/')
                + strlen('mimetype'),
            'localHeaderRawNameBytes' => strlen('OEBPS/content.xhtml')
                + strlen('OEBPS/images/')
                + strlen('mimetype'),
            'localHeaderExtraFieldBytes' => 0,
            'localHeaderReviewFieldBytes' => 0,
            'localExtraFieldEntryCount' => 0,
            'expansionRatio' => $expectedExpansionRatio,
            'largestEntry' => $expectedLargestEntry,
            'zeroByteEntryCount' => 1,
            'zeroByteFileCount' => 0,
            'emptyDirectoryEntryCount' => 1,
            'zeroByteEntries' => $expectedZeroByteEntries,
            'unknownExpansionRatioEntryCount' => 0,
            'unknownExpansionRatioEntries' => $expectedUnknownExpansionRatioEntries,
            'expansionRatioBucketSummaryCount' => count($expectedExpansionRatioBucketSummaries),
            'expansionRatioBuckets' => $expectedExpansionRatioBuckets,
            'expansionRatioBucketSummaries' => $expectedExpansionRatioBucketSummaries,
            'nameLengthBucketSummaryCount' => $manifest['nameLengthBucketSummaryCount'],
            'nameLengthBuckets' => $manifest['nameLengthBuckets'],
            'nameLengthBucketSummaries' => $manifest['nameLengthBucketSummaries'],
            'centralDirectoryRecordBytes' => array_sum(array_column($expectedEntries, 'centralDirectoryRecordBytes')),
            'centralDirectoryFixedHeaderBytes' => 46 * count($expectedEntries),
            'centralDirectoryVariableFieldBytes' => strlen('OEBPS/content.xhtml')
                + strlen('OEBPS/images/')
                + strlen('mimetype'),
            'centralDirectoryRawNameBytes' => strlen('OEBPS/content.xhtml')
                + strlen('OEBPS/images/')
                + strlen('mimetype'),
            'centralDirectoryExtraFieldBytes' => 0,
            'centralDirectoryRawCommentBytes' => 0,
            'centralDirectoryReviewFieldBytes' => 0,
            'sourceRecordBytes' => array_sum(array_column($expectedEntries, 'sourceRecordBytes')),
            'centralExtraFieldEntryCount' => 0,
            'entryCommentCount' => 0,
            'hasEntryComments' => false,
            'commentedEntryNames' => [],
            'entryCommentSummaryCount' => 0,
            'entryCommentSourceRecordBytes' => 0,
            'entryCommentSummaries' => [],
            'maxPathSegmentCount' => 2,
            'maxDirectoryDepth' => 1,
            'deepestEntryNames' => ['OEBPS/content.xhtml', 'OEBPS/images/'],
            'caseInsensitiveNameCollisionGroupCount' => 0,
            'caseInsensitiveNameCollisionEntryCount' => 0,
            'caseInsensitiveNameCollisionGroups' => [],
            'caseInsensitiveNameCollisionEntries' => [],
            'compressionMethodSummaries' => $expectedCompressionMethodSummaries,
            'generalPurposeFlagSummaries' => $expectedGeneralPurposeFlagSummaries,
            'versionNeededToExtractSummaryCount' => 1,
            'versionNeededToExtractVersions' => [20],
            'minimumVersionNeededToExtractVersions' => [10, 20],
            'maxVersionNeededToExtract' => 20,
            'maxMinimumVersionNeededToExtract' => 20,
            'versionNeededToExtractSummaries' => $expectedVersionNeededToExtractSummaries,
            'creatorHostSystemSummaryCount' => 1,
            'knownCreatorHostSystemEntryCount' => 3,
            'unknownCreatorHostSystemEntryCount' => 0,
            'creatorVersionMeetsNeededEntryCount' => 3,
            'creatorVersionBelowNeededEntryCount' => 0,
            'creatorVersionEqualNeededEntryCount' => 3,
            'creatorVersionAboveNeededEntryCount' => 0,
            'creatorVersionBelowNeededKnownHostEntryCount' => 0,
            'creatorVersionBelowNeededUnknownHostEntryCount' => 0,
            'creatorHostSystemSummaries' => $expectedCreatorHostSystemSummaries,
            'creatorVersionComparisonCounts' => $expectedCreatorVersionComparisonCounts,
            'unknownCreatorHostSystemEntries' => [],
            'creatorVersionBelowNeededEntries' => [],
            'directoryRootSummaries' => $expectedDirectoryRootSummaries,
            'extensionlessPackagePartCount' => 1,
            'packagePartExtensions' => $expectedPackagePartExtensions,
            'packagePartExtensionSummaries' => $expectedPackagePartExtensionSummaries,
            'packagePartBaseNameSummaryCount' => $manifest['packagePartBaseNameSummaryCount'],
            'packagePartBaseNames' => $manifest['packagePartBaseNames'],
            'packagePartBaseNameSummaries' => $manifest['packagePartBaseNameSummaries'],
            'duplicatePackagePartBaseNameCount' => $manifest['duplicatePackagePartBaseNameCount'],
            'duplicatePackagePartBaseNames' => $manifest['duplicatePackagePartBaseNames'],
            'duplicatePackagePartBaseNameSummaries' => $manifest['duplicatePackagePartBaseNameSummaries'],
            'packagePartCaseFoldBaseNameSummaryCount' => $manifest['packagePartCaseFoldBaseNameSummaryCount'],
            'packagePartCaseFoldBaseNames' => $manifest['packagePartCaseFoldBaseNames'],
            'packagePartCaseFoldBaseNameSummaries' => $manifest['packagePartCaseFoldBaseNameSummaries'],
            'duplicatePackagePartCaseFoldBaseNameCount' => $manifest['duplicatePackagePartCaseFoldBaseNameCount'],
            'duplicatePackagePartCaseFoldBaseNames' => $manifest['duplicatePackagePartCaseFoldBaseNames'],
            'duplicatePackagePartCaseFoldBaseNameSummaries' => $manifest['duplicatePackagePartCaseFoldBaseNameSummaries'],
            'packagePartBaseNameStemSummaryCount' => $manifest['packagePartBaseNameStemSummaryCount'],
            'packagePartBaseNameStems' => $manifest['packagePartBaseNameStems'],
            'packagePartBaseNameStemSummaries' => $manifest['packagePartBaseNameStemSummaries'],
            'duplicatePackagePartBaseNameStemCount' => $manifest['duplicatePackagePartBaseNameStemCount'],
            'duplicatePackagePartBaseNameStems' => $manifest['duplicatePackagePartBaseNameStems'],
            'duplicatePackagePartBaseNameStemSummaries' => $manifest['duplicatePackagePartBaseNameStemSummaries'],
            'packagePartCaseFoldBaseNameStemSummaryCount' => $manifest['packagePartCaseFoldBaseNameStemSummaryCount'],
            'packagePartCaseFoldBaseNameStems' => $manifest['packagePartCaseFoldBaseNameStems'],
            'packagePartCaseFoldBaseNameStemSummaries' => $manifest['packagePartCaseFoldBaseNameStemSummaries'],
            'duplicatePackagePartCaseFoldBaseNameStemCount' => $manifest['duplicatePackagePartCaseFoldBaseNameStemCount'],
            'duplicatePackagePartCaseFoldBaseNameStems' => $manifest['duplicatePackagePartCaseFoldBaseNameStems'],
            'duplicatePackagePartCaseFoldBaseNameStemSummaries' => $manifest['duplicatePackagePartCaseFoldBaseNameStemSummaries'],
            'pathSegmentSummaryCount' => $manifest['pathSegmentSummaryCount'],
            'pathSegmentOccurrenceCount' => $manifest['pathSegmentOccurrenceCount'],
            'pathSegmentCounts' => $manifest['pathSegmentCounts'],
            'pathSegmentEntryCounts' => $manifest['pathSegmentEntryCounts'],
            'pathSegmentSummaries' => $manifest['pathSegmentSummaries'],
            'caseFoldPathSegmentSummaryCount' => $manifest['caseFoldPathSegmentSummaryCount'],
            'caseFoldPathSegments' => $manifest['caseFoldPathSegments'],
            'caseFoldPathSegmentOccurrenceCount' => $manifest['caseFoldPathSegmentOccurrenceCount'],
            'caseFoldPathSegmentCounts' => $manifest['caseFoldPathSegmentCounts'],
            'caseFoldPathSegmentEntryCounts' => $manifest['caseFoldPathSegmentEntryCounts'],
            'caseFoldPathSegmentSummaries' => $manifest['caseFoldPathSegmentSummaries'],
            'pathSegmentPositionSummaryCount' => $manifest['pathSegmentPositionSummaryCount'],
            'pathSegmentPositionOccurrenceCount' => $manifest['pathSegmentPositionOccurrenceCount'],
            'pathSegmentPositionCounts' => $manifest['pathSegmentPositionCounts'],
            'pathSegmentPositionEntryCounts' => $manifest['pathSegmentPositionEntryCounts'],
            'pathSegmentPositionSummaries' => $manifest['pathSegmentPositionSummaries'],
            'entries' => $expectedEntries,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $t->same('zip-package-manifest-v1', $manifest['manifestVersion']);
        $t->same($expectedHash, $manifest['manifestSha256']);
        $t->same(strlen($zip), $manifest['archiveBytes']);
        $t->same(hash('sha256', $zip), $manifest['archiveSha256']);
        $t->same(3, $manifest['entryCount']);
        $t->same(2, $manifest['fileEntryCount']);
        $t->same(1, $manifest['directoryEntryCount']);
        $t->same(strlen(gzdeflate($contentXhtml)) + strlen($mimetype), $manifest['compressedBytes']);
        $t->same(strlen($contentXhtml) + strlen($mimetype), $manifest['uncompressedBytes']);
        $t->same($expectedExpansionRatio, $manifest['expansionRatio']);
        $t->same($expectedLargestEntry, $manifest['largestEntry']);
        $t->same(1, $manifest['zeroByteEntryCount']);
        $t->same(0, $manifest['zeroByteFileCount']);
        $t->same(1, $manifest['emptyDirectoryEntryCount']);
        $t->same(true, $manifest['hasZeroByteEntries']);
        $t->same($expectedZeroByteEntries, $manifest['zeroByteEntries']);
        $t->same(0, $manifest['unknownExpansionRatioEntryCount']);
        $t->same(false, $manifest['hasUnknownExpansionRatioEntries']);
        $t->same($expectedUnknownExpansionRatioEntries, $manifest['unknownExpansionRatioEntries']);
        $t->same(count($expectedExpansionRatioBucketSummaries), $manifest['expansionRatioBucketSummaryCount']);
        $t->same($expectedExpansionRatioBuckets, $manifest['expansionRatioBuckets']);
        $t->same($expectedExpansionRatioBucketSummaries, $manifest['expansionRatioBucketSummaries']);
        $t->same(2, $manifest['storedEntryCount']);
        $t->same(1, $manifest['deflatedEntryCount']);
        $t->same(0, $manifest['unsupportedCompressionMethodCount']);
        $t->same(30 * 3 + strlen('OEBPS/content.xhtml') + strlen('OEBPS/images/') + strlen('mimetype'), $manifest['localHeaderBytes']);
        $t->same(30 * 3, $manifest['localHeaderFixedHeaderBytes']);
        $t->same(strlen('OEBPS/content.xhtml') + strlen('OEBPS/images/') + strlen('mimetype'), $manifest['localHeaderVariableFieldBytes']);
        $t->same($manifest['localHeaderVariableFieldBytes'], $manifest['localHeaderRawNameBytes']);
        $t->same(0, $manifest['localHeaderExtraFieldBytes']);
        $t->same(0, $manifest['localHeaderReviewFieldBytes']);
        $t->same(0, $manifest['localExtraFieldEntryCount']);
        $t->same(false, $manifest['hasLocalHeaderReviewFields']);
        $t->same($package->centralDirectoryOffset(), $manifest['centralDirectoryOffset']);
        $t->same($manifest['endOfCentralDirectoryOffset'] - $manifest['centralDirectoryOffset'], $manifest['centralDirectoryBytes']);
        $t->same($manifest['endOfCentralDirectoryOffset'], $manifest['centralDirectoryEnd']);
        $t->same(
            hash('sha256', substr($zip, $manifest['centralDirectoryOffset'], $manifest['centralDirectoryBytes'])),
            $manifest['centralDirectorySha256']
        );
        $t->same(22, $manifest['endOfCentralDirectoryBytes']);
        $t->same(strlen($zip), $manifest['endOfCentralDirectoryEnd']);
        $t->same(
            hash('sha256', substr($zip, $manifest['endOfCentralDirectoryOffset'], $manifest['endOfCentralDirectoryBytes'])),
            $manifest['endOfCentralDirectorySha256']
        );
        $t->same($manifest['endOfCentralDirectoryOffset'] + 22, $manifest['packageCommentOffset']);
        $t->same(0, $manifest['packageCommentBytes']);
        $t->same($manifest['packageCommentOffset'], $manifest['packageCommentEnd']);
        $t->same(null, $manifest['packageCommentSha256']);
        $t->same('', $manifest['packageCommentPreviewHex']);
        $t->same(0, $manifest['packageCommentPreviewByteCount']);
        $t->same('zip-package-comment-source-metadata-only', $manifest['packageCommentByteExposurePolicy']);
        $t->same(false, $manifest['canExposePackageCommentBytes']);
        $t->same(false, $manifest['hasPackageComment']);
        $t->same(false, $manifest['hasCentralDirectorySignature']);
        $t->same(null, $manifest['centralDirectorySignatureOffset']);
        $t->same(0, $manifest['centralDirectorySignatureBytes']);
        $t->same(null, $manifest['centralDirectorySignatureSha256']);
        $t->same(array_sum(array_column($expectedEntries, 'centralDirectoryRecordBytes')), $manifest['centralDirectoryRecordBytes']);
        $t->same(46 * count($expectedEntries), $manifest['centralDirectoryFixedHeaderBytes']);
        $t->same(
            strlen('OEBPS/content.xhtml') + strlen('OEBPS/images/') + strlen('mimetype'),
            $manifest['centralDirectoryVariableFieldBytes']
        );
        $t->same($manifest['centralDirectoryVariableFieldBytes'], $manifest['centralDirectoryRawNameBytes']);
        $t->same(0, $manifest['centralDirectoryExtraFieldBytes']);
        $t->same(0, $manifest['centralDirectoryRawCommentBytes']);
        $t->same(0, $manifest['centralDirectoryReviewFieldBytes']);
        $t->same(array_sum(array_column($expectedEntries, 'sourceRecordBytes')), $manifest['sourceRecordBytes']);
        $t->same(0, $manifest['centralExtraFieldEntryCount']);
        $t->same(0, $manifest['entryCommentCount']);
        $t->same(false, $manifest['hasEntryComments']);
        $t->same([], $manifest['commentedEntryNames']);
        $t->same(0, $manifest['entryCommentSummaryCount']);
        $t->same(0, $manifest['entryCommentSourceRecordBytes']);
        $t->same([], $manifest['entryCommentSummaries']);
        $t->same(false, $manifest['hasCentralDirectoryReviewFields']);
        $t->same(2, $manifest['maxPathSegmentCount']);
        $t->same(1, $manifest['maxDirectoryDepth']);
        $t->same(['OEBPS/content.xhtml', 'OEBPS/images/'], $manifest['deepestEntryNames']);
        $t->same(0, $manifest['caseInsensitiveNameCollisionGroupCount']);
        $t->same(0, $manifest['caseInsensitiveNameCollisionEntryCount']);
        $t->same(false, $manifest['hasCaseInsensitiveNameCollisions']);
        $t->same([], $manifest['caseInsensitiveNameCollisionGroups']);
        $t->same([], $manifest['caseInsensitiveNameCollisionEntries']);
        $t->same(2, $manifest['compressionMethodSummaryCount']);
        $t->same($expectedCompressionMethodSummaries, $manifest['compressionMethodSummaries']);
        $t->same(1, $manifest['generalPurposeFlagSummaryCount']);
        $t->same(3, $manifest['generalPurposeUtf8NameEntryCount']);
        $t->same(0, $manifest['generalPurposeDataDescriptorEntryCount']);
        $t->same(0, $manifest['generalPurposeDeflateOptionEntryCount']);
        $t->same($expectedGeneralPurposeFlagSummaries, $manifest['generalPurposeFlagSummaries']);
        $t->same(1, $manifest['versionNeededToExtractSummaryCount']);
        $t->same([20], $manifest['versionNeededToExtractVersions']);
        $t->same([10, 20], $manifest['minimumVersionNeededToExtractVersions']);
        $t->same(20, $manifest['maxVersionNeededToExtract']);
        $t->same(20, $manifest['maxMinimumVersionNeededToExtract']);
        $t->same(false, $manifest['hasMultipleVersionNeededToExtractVersions']);
        $t->same($expectedVersionNeededToExtractSummaries, $manifest['versionNeededToExtractSummaries']);
        $t->same(1, $manifest['creatorHostSystemSummaryCount']);
        $t->same(3, $manifest['knownCreatorHostSystemEntryCount']);
        $t->same(0, $manifest['unknownCreatorHostSystemEntryCount']);
        $t->same(3, $manifest['creatorVersionMeetsNeededEntryCount']);
        $t->same(0, $manifest['creatorVersionBelowNeededEntryCount']);
        $t->same(3, $manifest['creatorVersionEqualNeededEntryCount']);
        $t->same(0, $manifest['creatorVersionAboveNeededEntryCount']);
        $t->same(0, $manifest['creatorVersionBelowNeededKnownHostEntryCount']);
        $t->same(0, $manifest['creatorVersionBelowNeededUnknownHostEntryCount']);
        $t->same(false, $manifest['hasUnknownCreatorHostSystems']);
        $t->same(false, $manifest['hasCreatorVersionBelowNeededEntries']);
        $t->same($expectedCreatorVersionComparisonCounts, $manifest['creatorVersionComparisonCounts']);
        $t->same($expectedCreatorHostSystemSummaries, $manifest['creatorHostSystemSummaries']);
        $t->same([], $manifest['unknownCreatorHostSystemEntries']);
        $t->same([], $manifest['creatorVersionBelowNeededEntries']);
        $t->same(2, $manifest['directoryRootCount']);
        $t->same($expectedDirectoryRoots, $manifest['directoryRoots']);
        $t->same($expectedDirectoryRootSummaries, $manifest['directoryRootSummaries']);
        $t->same(1, $manifest['extensionlessPackagePartCount']);
        $t->same(true, $manifest['hasExtensionlessPackageParts']);
        $t->same(2, $manifest['packagePartExtensionSummaryCount']);
        $t->same($expectedPackagePartExtensions, $manifest['packagePartExtensions']);
        $t->same($expectedPackagePartExtensionSummaries, $manifest['packagePartExtensionSummaries']);
        $t->same(3, $manifest['pathSegmentPositionSummaryCount']);
        $t->same(5, $manifest['pathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 2, 'last' => 2, 'only' => 1], $manifest['pathSegmentPositionCounts']);
        $t->same(['first' => 2, 'last' => 2, 'only' => 1], $manifest['pathSegmentPositionEntryCounts']);
        $t->same($expectedCentralOrder, $manifest['centralDirectoryOrderNames']);
        $t->same($expectedLocalOrder, $manifest['localHeaderOrderNames']);
        $t->same(false, $manifest['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same($expectedLocalHeaderOrderRelationCounts, $manifest['localHeaderOrderRelationCounts']);
        $t->same(0, $manifest['localHeaderOrderMatchCount']);
        $t->same(count($expectedLocalHeaderOrderDisplacementEntries), $manifest['localHeaderOrderDisplacementEntryCount']);
        $t->same(true, $manifest['hasLocalHeaderOrderDisplacements']);
        $t->same(2, $manifest['maxLocalHeaderOrderDisplacement']);
        $t->same($expectedLocalHeaderOrderDisplacementEntries, $manifest['localHeaderOrderDisplacementEntries']);
        $t->same($expectedEntries, array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'isDirectory' => $entry['isDirectory'],
                'caseFoldKey' => $entry['caseFoldKey'],
                'caseInsensitiveEquivalentEntryNames' => $entry['caseInsensitiveEquivalentEntryNames'],
                'hasCaseInsensitiveNameCollision' => $entry['hasCaseInsensitiveNameCollision'],
                'caseInsensitiveNameCollisionIssues' => $entry['caseInsensitiveNameCollisionIssues'],
                'versionMadeBy' => $entry['versionMadeBy'],
                'madeByHostSystem' => $entry['madeByHostSystem'],
                'madeByHostSystemName' => $entry['madeByHostSystemName'],
                'madeByVersion' => $entry['madeByVersion'],
                'versionNeededToExtract' => $entry['versionNeededToExtract'],
                'localVersionNeededToExtract' => $entry['localVersionNeededToExtract'],
                'minimumVersionNeededToExtract' => $entry['minimumVersionNeededToExtract'],
                'localMinimumVersionNeededToExtract' => $entry['localMinimumVersionNeededToExtract'],
                'versionNeededToExtractMatchesLocalHeader' => $entry['versionNeededToExtractMatchesLocalHeader'],
                'versionNeededToExtractMeetsFeatureMinimum' => $entry['versionNeededToExtractMeetsFeatureMinimum'],
                'localVersionNeededToExtractMeetsFeatureMinimum' => $entry['localVersionNeededToExtractMeetsFeatureMinimum'],
                'versionNeededToExtractExceedsBoundedReader' => $entry['versionNeededToExtractExceedsBoundedReader'],
                'localVersionNeededToExtractExceedsBoundedReader' => $entry['localVersionNeededToExtractExceedsBoundedReader'],
                'creatorVersionMeetsNeeded' => $entry['creatorVersionMeetsNeeded'],
                'creatorVersionComparison' => $entry['creatorVersionComparison'],
                'creatorVersionDelta' => $entry['creatorVersionDelta'],
                'creatorHostSystemIsKnown' => $entry['creatorHostSystemIsKnown'],
                'creatorHostSystemIssues' => $entry['creatorHostSystemIssues'],
                'directoryRoot' => $entry['directoryRoot'],
                'entryNameBytes' => $entry['entryNameBytes'],
                'entryNameLengthBucket' => $entry['entryNameLengthBucket'],
                'pathSegments' => $entry['pathSegments'],
                'pathSegmentPositionReviews' => $entry['pathSegmentPositionReviews'],
                'pathSegmentCount' => $entry['pathSegmentCount'],
                'directoryDepth' => $entry['directoryDepth'],
                'packagePartBaseName' => $entry['packagePartBaseName'],
                'packagePartCaseFoldBaseName' => $entry['packagePartCaseFoldBaseName'],
                'packagePartBaseNameStem' => $entry['packagePartBaseNameStem'],
                'packagePartCaseFoldBaseNameStem' => $entry['packagePartCaseFoldBaseNameStem'],
                'packagePartExtension' => $entry['packagePartExtension'],
                'packagePartExtensionKey' => $entry['packagePartExtensionKey'],
                'extensionlessPackagePart' => $entry['extensionlessPackagePart'],
                'centralDirectoryIndex' => $entry['centralDirectoryIndex'],
                'localHeaderOrder' => $entry['localHeaderOrder'],
                'localHeaderOrderDelta' => $entry['localHeaderOrderDelta'],
                'localHeaderOrderDisplacement' => $entry['localHeaderOrderDisplacement'],
                'localHeaderOrderRelation' => $entry['localHeaderOrderRelation'],
                'localHeaderOrderMatchesCentralDirectoryOrder' => $entry['localHeaderOrderMatchesCentralDirectoryOrder'],
                'localHeaderNameAtCentralDirectoryIndex' => $entry['localHeaderNameAtCentralDirectoryIndex'],
                'centralDirectoryNameAtLocalHeaderOrder' => $entry['centralDirectoryNameAtLocalHeaderOrder'],
                'compressionMethod' => $entry['compressionMethod'],
                'crc32Hex' => $entry['crc32Hex'],
                'compressedSize' => $entry['compressedSize'],
                'uncompressedSize' => $entry['uncompressedSize'],
                'expansionRatio' => $entry['expansionRatio'],
                'localHeaderSha256' => $entry['localHeaderSha256'],
                'localHeaderFixedHeaderBytes' => $entry['localHeaderFixedHeaderBytes'],
                'localHeaderVariableFieldBytes' => $entry['localHeaderVariableFieldBytes'],
                'localHeaderVariableFieldSha256' => $entry['localHeaderVariableFieldSha256'],
                'localHeaderRawNameBytes' => $entry['localHeaderRawNameBytes'],
                'localHeaderRawNameSha256' => $entry['localHeaderRawNameSha256'],
                'localHeaderExtraFieldBytes' => $entry['localHeaderExtraFieldBytes'],
                'localHeaderExtraFieldSha256' => $entry['localHeaderExtraFieldSha256'],
                'localHeaderReviewFieldBytes' => $entry['localHeaderReviewFieldBytes'],
                'localRecordBytes' => $entry['localRecordBytes'],
                'localRecordSha256' => $entry['localRecordSha256'],
                'compressedDataSha256' => $entry['compressedDataSha256'],
                'usesDataDescriptor' => $entry['usesDataDescriptor'],
                'dataDescriptorBytes' => $entry['dataDescriptorBytes'],
                'dataDescriptorSha256' => $entry['dataDescriptorSha256'],
                'centralDirectoryRecordBytes' => $entry['centralDirectoryRecordBytes'],
                'centralDirectoryRecordSha256' => $entry['centralDirectoryRecordSha256'],
                'centralDirectoryFixedHeaderBytes' => $entry['centralDirectoryFixedHeaderBytes'],
                'centralDirectoryVariableFieldBytes' => $entry['centralDirectoryVariableFieldBytes'],
                'centralDirectoryVariableFieldSha256' => $entry['centralDirectoryVariableFieldSha256'],
                'centralDirectoryRawNameBytes' => $entry['centralDirectoryRawNameBytes'],
                'centralDirectoryRawNameSha256' => $entry['centralDirectoryRawNameSha256'],
                'centralDirectoryExtraFieldBytes' => $entry['centralDirectoryExtraFieldBytes'],
                'centralDirectoryExtraFieldSha256' => $entry['centralDirectoryExtraFieldSha256'],
                'centralDirectoryRawCommentBytes' => $entry['centralDirectoryRawCommentBytes'],
                'centralDirectoryRawCommentSha256' => $entry['centralDirectoryRawCommentSha256'],
                'centralDirectoryReviewFieldBytes' => $entry['centralDirectoryReviewFieldBytes'],
                'sourceRecordBytes' => $entry['sourceRecordBytes'],
            ],
            $manifest['entries']
        ));
        $t->same($manifest, $strict['packageManifest']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },

    'preflights zip package manifest local fixed fields for package handoff' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $documentXml = '<w:document><w:body><w:p>manifest local fixed fields</w:p></w:body></w:document>';
        $commentsXml = '<w:comments><w:comment>manifest descriptor placeholders</w:comment></w:comments>';
        $commentsExtra = pack('vva*', 0xcafe, strlen('comments-local'), 'comments-local');
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
                'modifiedTime' => 0x1234,
                'modifiedDate' => 0x5678,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
                'localExtra' => $commentsExtra,
                'centralExtra' => $commentsExtra,
                'modifiedTime' => 0x2222,
                'modifiedDate' => 0x4444,
            ],
        ]));

        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $raw = ZipPackage::rawStrictImportPreflight($package->bytes(), 2048, 100.0, 2048);

        $t->same(2, $manifest['localHeaderFixedFieldEntryCount']);
        $t->same(0, $manifest['localHeaderFixedFieldIssueEntryCount']);
        $t->same(false, $manifest['hasLocalHeaderFixedFieldIssues']);
        $t->same([], $manifest['localHeaderFixedFieldIssues']);
        $t->same([], $manifest['localHeaderFixedFieldIssueEntries']);

        $documentFixed = $manifest['localHeaderFixedFieldEntries'][0];
        $commentsFixed = $manifest['localHeaderFixedFieldEntries'][1];

        $t->same('word/document.xml', $documentFixed['name']);
        $t->same(0, $documentFixed['centralDirectoryIndex']);
        $t->same(0, $documentFixed['localFixedHeaderOffset']);
        $t->same(30, $documentFixed['localFixedHeaderLength']);
        $t->same(30, $documentFixed['localFixedHeaderEnd']);
        $t->same(4, $documentFixed['localVersionNeededToExtractOffset']);
        $t->same(6, $documentFixed['localGeneralPurposeFlagsOffset']);
        $t->same(8, $documentFixed['localCompressionMethodOffset']);
        $t->same(10, $documentFixed['localModifiedDosTimeOffset']);
        $t->same(12, $documentFixed['localModifiedDosDateOffset']);
        $t->same(14, $documentFixed['localCrc32Offset']);
        $t->same(18, $documentFixed['localCompressedSizeOffset']);
        $t->same(22, $documentFixed['localUncompressedSizeOffset']);
        $t->same(26, $documentFixed['localNameLengthOffset']);
        $t->same(28, $documentFixed['localExtraFieldLengthOffset']);
        $t->same(20, $documentFixed['centralVersionNeededToExtract']);
        $t->same(20, $documentFixed['localVersionNeededToExtract']);
        $t->same(0x0800, $documentFixed['centralGeneralPurposeFlags']);
        $t->same(0x0800, $documentFixed['localGeneralPurposeFlags']);
        $t->same(0, $documentFixed['centralCompressionMethod']);
        $t->same(0, $documentFixed['localCompressionMethod']);
        $t->same(0x1234, $documentFixed['centralModifiedDosTime']);
        $t->same(0x1234, $documentFixed['localModifiedDosTime']);
        $t->same(0x5678, $documentFixed['centralModifiedDosDate']);
        $t->same(0x5678, $documentFixed['localModifiedDosDate']);
        $t->same($crc32($documentXml), $documentFixed['centralCrc32']);
        $t->same(sprintf('%08x', $crc32($documentXml)), $documentFixed['centralCrc32Hex']);
        $t->same($crc32($documentXml), $documentFixed['localFixedHeaderCrc32']);
        $t->same(sprintf('%08x', $crc32($documentXml)), $documentFixed['localFixedHeaderCrc32Hex']);
        $t->same(strlen($documentXml), $documentFixed['centralCompressedSize']);
        $t->same(strlen($documentXml), $documentFixed['localFixedHeaderCompressedSize']);
        $t->same(strlen($documentXml), $documentFixed['centralUncompressedSize']);
        $t->same(strlen($documentXml), $documentFixed['localFixedHeaderUncompressedSize']);
        $t->same(strlen('word/document.xml'), $documentFixed['localFixedHeaderNameLength']);
        $t->same(0, $documentFixed['localFixedHeaderExtraFieldLength']);
        $t->same(null, $documentFixed['localFixedHeaderHasZeroDataDescriptorPlaceholders']);
        $t->same(true, $documentFixed['localHeaderFixedFieldsMatchCentralDirectory']);
        $t->same([], $documentFixed['localHeaderFixedFieldIssues']);

        $commentsCompressedSize = strlen(gzdeflate($commentsXml));
        $t->same('word/comments.xml', $commentsFixed['name']);
        $t->same(1, $commentsFixed['centralDirectoryIndex']);
        $t->same($manifest['entries'][1]['localHeaderOffset'], $commentsFixed['localFixedHeaderOffset']);
        $t->same($manifest['entries'][1]['localHeaderOffset'] + 30, $commentsFixed['localFixedHeaderEnd']);
        $t->same(8, $commentsFixed['centralCompressionMethod']);
        $t->same(8, $commentsFixed['localCompressionMethod']);
        $t->same(0x2222, $commentsFixed['centralModifiedDosTime']);
        $t->same(0x2222, $commentsFixed['localModifiedDosTime']);
        $t->same(0x4444, $commentsFixed['centralModifiedDosDate']);
        $t->same(0x4444, $commentsFixed['localModifiedDosDate']);
        $t->same($crc32($commentsXml), $commentsFixed['centralCrc32']);
        $t->same(0, $commentsFixed['localFixedHeaderCrc32']);
        $t->same('00000000', $commentsFixed['localFixedHeaderCrc32Hex']);
        $t->same($commentsCompressedSize, $commentsFixed['centralCompressedSize']);
        $t->same(0, $commentsFixed['localFixedHeaderCompressedSize']);
        $t->same(strlen($commentsXml), $commentsFixed['centralUncompressedSize']);
        $t->same(0, $commentsFixed['localFixedHeaderUncompressedSize']);
        $t->same(strlen('word/comments.xml'), $commentsFixed['localFixedHeaderNameLength']);
        $t->same(strlen($commentsExtra), $commentsFixed['localFixedHeaderExtraFieldLength']);
        $t->same(true, $commentsFixed['localFixedHeaderHasZeroDataDescriptorPlaceholders']);
        $t->same(true, $commentsFixed['localHeaderFixedFieldsMatchCentralDirectory']);
        $t->same([], $commentsFixed['localHeaderFixedFieldIssues']);
        $t->same($manifest, $strict['packageManifest']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },

    'summarizes zip package manifest expansion ratio buckets for package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = str_repeat('D', 2048);
        $styleXml = '<style/>';
        $emptyBytes = '';
        $mediaBytes = str_repeat('M', 70000);
        $unknownName = 'word/media/zero-compressed.bin';
        $unknownUncompressedSize = 37;
        $zip = $buildZipPackage([
            ['name' => 'word/document.xml', 'data' => $documentXml, 'method' => 8],
            ['name' => 'word/styles.xml', 'data' => $styleXml, 'method' => 0],
            ['name' => 'word/media/empty.bin', 'data' => $emptyBytes, 'method' => 0],
            ['name' => 'word/media/', 'data' => '', 'method' => 0],
            ['name' => 'word/media/large.bin', 'data' => $mediaBytes, 'method' => 8],
            [
                'name' => $unknownName,
                'data' => '',
                'method' => 12,
                'centralCompressedSize' => 0,
                'centralUncompressedSize' => $unknownUncompressedSize,
                'localCompressedSize' => 0,
                'localUncompressedSize' => $unknownUncompressedSize,
            ],
        ]);
        $documentCompressed = strlen(gzdeflate($documentXml));
        $mediaCompressed = strlen(gzdeflate($mediaBytes));
        $documentRatio = strlen($documentXml) / $documentCompressed;
        $mediaRatio = strlen($mediaBytes) / $mediaCompressed;
        $largestHighRatioName = $mediaRatio > $documentRatio ? 'word/media/large.bin' : 'word/document.xml';
        $largestHighRatio = max($documentRatio, $mediaRatio);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(131072, 100000.0, 131072);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 131072, 100000.0, 131072);
        $entriesByName = array_column($manifest['entries'], null, 'name');
        $sumEntryField = static function (array $names, string $field) use ($entriesByName): int {
            $total = 0;
            foreach ($names as $name) {
                $total += (int) $entriesByName[$name][$field];
            }

            return $total;
        };
        $buckets = array_column($manifest['expansionRatioBucketSummaries'], null, 'expansionRatioBucket');

        $t->same(4, $manifest['expansionRatioBucketSummaryCount']);
        $t->same(['zero-byte', 'up-to-1x', 'over-100x', 'unknown'], $manifest['expansionRatioBuckets']);

        $t->same([
            'expansionRatioBucket' => 'zero-byte',
            'minExpansionRatio' => 0.0,
            'maxExpansionRatio' => 0.0,
            'entryCount' => 2,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 1,
            'unknownExpansionRatioEntryCount' => 0,
            'compressedBytes' => 0,
            'uncompressedBytes' => 0,
            'localRecordBytes' => $sumEntryField(['word/media/empty.bin', 'word/media/'], 'localRecordBytes'),
            'sourceRecordBytes' => $sumEntryField(['word/media/empty.bin', 'word/media/'], 'sourceRecordBytes'),
            'dataDescriptorEntryCount' => 0,
            'dataDescriptorBytes' => 0,
            'directoryRoots' => ['word/'],
            'compressionMethodNames' => ['stored'],
            'entryNames' => ['word/media/empty.bin', 'word/media/'],
            'largestExpansionRatioEntryName' => 'word/media/empty.bin',
            'largestExpansionRatio' => 0.0,
        ], $buckets['zero-byte']);
        $t->same([
            'expansionRatioBucket' => 'up-to-1x',
            'minExpansionRatio' => 0.0,
            'maxExpansionRatio' => 1.0,
            'entryCount' => 1,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 0,
            'unknownExpansionRatioEntryCount' => 0,
            'compressedBytes' => strlen($styleXml),
            'uncompressedBytes' => strlen($styleXml),
            'localRecordBytes' => $entriesByName['word/styles.xml']['localRecordBytes'],
            'sourceRecordBytes' => $entriesByName['word/styles.xml']['sourceRecordBytes'],
            'dataDescriptorEntryCount' => 0,
            'dataDescriptorBytes' => 0,
            'directoryRoots' => ['word/'],
            'compressionMethodNames' => ['stored'],
            'entryNames' => ['word/styles.xml'],
            'largestExpansionRatioEntryName' => 'word/styles.xml',
            'largestExpansionRatio' => 1.0,
        ], $buckets['up-to-1x']);
        $t->same([
            'expansionRatioBucket' => 'over-100x',
            'minExpansionRatio' => 100.0,
            'maxExpansionRatio' => null,
            'entryCount' => 2,
            'fileEntryCount' => 2,
            'directoryEntryCount' => 0,
            'unknownExpansionRatioEntryCount' => 0,
            'compressedBytes' => $documentCompressed + $mediaCompressed,
            'uncompressedBytes' => strlen($documentXml) + strlen($mediaBytes),
            'localRecordBytes' => $sumEntryField(['word/document.xml', 'word/media/large.bin'], 'localRecordBytes'),
            'sourceRecordBytes' => $sumEntryField(['word/document.xml', 'word/media/large.bin'], 'sourceRecordBytes'),
            'dataDescriptorEntryCount' => 0,
            'dataDescriptorBytes' => 0,
            'directoryRoots' => ['word/'],
            'compressionMethodNames' => ['deflated'],
            'entryNames' => ['word/document.xml', 'word/media/large.bin'],
            'largestExpansionRatioEntryName' => $largestHighRatioName,
            'largestExpansionRatio' => $largestHighRatio,
        ], $buckets['over-100x']);
        $t->same([
            'expansionRatioBucket' => 'unknown',
            'minExpansionRatio' => null,
            'maxExpansionRatio' => null,
            'entryCount' => 1,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 0,
            'unknownExpansionRatioEntryCount' => 1,
            'compressedBytes' => 0,
            'uncompressedBytes' => $unknownUncompressedSize,
            'localRecordBytes' => $entriesByName[$unknownName]['localRecordBytes'],
            'sourceRecordBytes' => $entriesByName[$unknownName]['sourceRecordBytes'],
            'dataDescriptorEntryCount' => 0,
            'dataDescriptorBytes' => 0,
            'directoryRoots' => ['word/'],
            'compressionMethodNames' => ['unsupported'],
            'entryNames' => [$unknownName],
            'largestExpansionRatioEntryName' => null,
            'largestExpansionRatio' => null,
        ], $buckets['unknown']);

        $t->same($manifest, $strict['packageManifest']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },

    'rolls up zip package manifest entry name length buckets before shared package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $shortName = 'mimetype';
        $shortDirectoryName = 'word/media/';
        $mediumName = 'word/document.xml';
        $longName = 'word/media/' . str_repeat('l', 54) . '.bin';
        $veryLongName = 'word/media/' . str_repeat('v', 118) . '.bin';
        $mimetype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $documentXml = '<w:document><w:body><w:p>name buckets</w:p></w:body></w:document>';
        $longBytes = 'long package name payload';
        $veryLongBytes = 'very long package name payload';

        $zip = $buildZipPackage([
            [
                'name' => $shortName,
                'data' => $mimetype,
                'method' => 0,
            ],
            [
                'name' => $shortDirectoryName,
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => $mediumName,
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => $longName,
                'data' => $longBytes,
                'method' => 0,
            ],
            [
                'name' => $veryLongName,
                'data' => $veryLongBytes,
                'method' => 0,
            ],
        ]);
        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(4096, 100.0, 4096);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 4096, 100.0, 4096);

        $entriesByName = array_column($manifest['entries'], null, 'name');
        $sumEntryField = static function (array $names, string $field) use ($entriesByName): int {
            $total = 0;
            foreach ($names as $name) {
                $total += (int) $entriesByName[$name][$field];
            }

            return $total;
        };
        $buckets = array_column($manifest['nameLengthBucketSummaries'], null, 'nameLengthBucket');

        $t->same(strlen($shortName), $entriesByName[$shortName]['entryNameBytes']);
        $t->same('up-to-15-bytes', $entriesByName[$shortName]['entryNameLengthBucket']);
        $t->same(strlen($shortDirectoryName), $entriesByName[$shortDirectoryName]['entryNameBytes']);
        $t->same('up-to-15-bytes', $entriesByName[$shortDirectoryName]['entryNameLengthBucket']);
        $t->same(strlen($mediumName), $entriesByName[$mediumName]['entryNameBytes']);
        $t->same('16-to-63-bytes', $entriesByName[$mediumName]['entryNameLengthBucket']);
        $t->same(strlen($longName), $entriesByName[$longName]['entryNameBytes']);
        $t->same('64-to-127-bytes', $entriesByName[$longName]['entryNameLengthBucket']);
        $t->same(strlen($veryLongName), $entriesByName[$veryLongName]['entryNameBytes']);
        $t->same('128-plus-bytes', $entriesByName[$veryLongName]['entryNameLengthBucket']);

        $t->same(4, $manifest['nameLengthBucketSummaryCount']);
        $t->same(
            ['up-to-15-bytes', '16-to-63-bytes', '64-to-127-bytes', '128-plus-bytes'],
            $manifest['nameLengthBuckets']
        );

        $shortBucketNames = [$shortName, $shortDirectoryName];
        $t->same([
            'nameLengthBucket' => 'up-to-15-bytes',
            'minNameBytes' => 0,
            'maxNameBytes' => 15,
            'entryCount' => 2,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 1,
            'entryNameBytes' => strlen($shortName) + strlen($shortDirectoryName),
            'localHeaderRawNameBytes' => $sumEntryField($shortBucketNames, 'localHeaderRawNameBytes'),
            'centralDirectoryRawNameBytes' => $sumEntryField($shortBucketNames, 'centralDirectoryRawNameBytes'),
            'compressedBytes' => strlen($mimetype),
            'uncompressedBytes' => strlen($mimetype),
            'localRecordBytes' => $sumEntryField($shortBucketNames, 'localRecordBytes'),
            'sourceRecordBytes' => $sumEntryField($shortBucketNames, 'sourceRecordBytes'),
            'directoryRoots' => ['/', 'word/'],
            'packagePartExtensionKeys' => ['(directory)', '(none)'],
            'entryNames' => $shortBucketNames,
            'minEntryNameBytes' => strlen($shortName),
            'maxEntryNameBytes' => strlen($shortDirectoryName),
            'longestEntryNames' => [$shortDirectoryName],
        ], $buckets['up-to-15-bytes']);

        $t->same(1, $buckets['16-to-63-bytes']['entryCount']);
        $t->same(strlen($mediumName), $buckets['16-to-63-bytes']['entryNameBytes']);
        $t->same(['xml'], $buckets['16-to-63-bytes']['packagePartExtensionKeys']);
        $t->same([$mediumName], $buckets['16-to-63-bytes']['longestEntryNames']);
        $t->same(1, $buckets['64-to-127-bytes']['entryCount']);
        $t->same(strlen($longName), $buckets['64-to-127-bytes']['entryNameBytes']);
        $t->same([$longName], $buckets['64-to-127-bytes']['entryNames']);
        $t->same(1, $buckets['128-plus-bytes']['entryCount']);
        $t->same(strlen($veryLongName), $buckets['128-plus-bytes']['entryNameBytes']);
        $t->same([$veryLongName], $buckets['128-plus-bytes']['entryNames']);

        $t->same($manifest['nameLengthBucketSummaries'], $strict['packageManifest']['nameLengthBucketSummaries']);
        $t->same($manifest['nameLengthBucketSummaries'], $raw['packageManifest']['nameLengthBucketSummaries']);
        $t->same($manifest['nameLengthBucketSummaries'], $raw['strictImport']['packageManifest']['nameLengthBucketSummaries']);
    },

    'preflights zip package manifest path segment positions for shared package handoff' => static function (TestRunner $t) use ($buildZipPackage, $pathSegmentPositionReviews): void {
        $documentXml = '<w:document><w:body><w:p>path segment positions</w:p></w:body></w:document>';
        $scanBytes = "deep scan payload bytes\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'method' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'word/media/deep/scan.png',
                'data' => $scanBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/deep/',
                'data' => '',
                'method' => 0,
            ],
        ]));

        $manifest = $package->packageManifestPreflight();
        $entriesByName = [];
        foreach ($manifest['entries'] as $entry) {
            $entriesByName[$entry['name']] = $entry;
        }
        $positions = [];
        foreach ($manifest['pathSegmentPositionSummaries'] as $position) {
            $positions[$position['position']] = $position;
        }
        $segments = [];
        foreach ($manifest['pathSegmentSummaries'] as $segment) {
            $segments[$segment['segment']] = $segment;
        }

        $t->same($pathSegmentPositionReviews(['mimetype']), $entriesByName['mimetype']['pathSegmentPositionReviews']);
        $t->same(
            $pathSegmentPositionReviews(['word', 'media', 'deep', 'scan.png']),
            $entriesByName['word/media/deep/scan.png']['pathSegmentPositionReviews']
        );
        $t->same(4, $manifest['pathSegmentPositionSummaryCount']);
        $t->same(10, $manifest['pathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 3, 'last' => 3, 'middle' => 3, 'only' => 1], $manifest['pathSegmentPositionCounts']);
        $t->same(['first' => 3, 'last' => 3, 'middle' => 2, 'only' => 1], $manifest['pathSegmentPositionEntryCounts']);
        $t->same(['first', 'last', 'middle', 'only'], array_column($manifest['pathSegmentPositionSummaries'], 'position'));
        $t->same(6, $manifest['pathSegmentSummaryCount']);
        $t->same(10, $manifest['pathSegmentOccurrenceCount']);
        $t->same([
            'deep' => 2,
            'document.xml' => 1,
            'media' => 2,
            'mimetype' => 1,
            'scan.png' => 1,
            'word' => 3,
        ], $manifest['pathSegmentCounts']);
        $t->same($manifest['pathSegmentCounts'], $manifest['pathSegmentEntryCounts']);
        $t->same(['deep', 'document.xml', 'media', 'mimetype', 'scan.png', 'word'], array_column($manifest['pathSegmentSummaries'], 'segment'));

        $word = $segments['word'];
        $wordEntrySourceBytes = $entriesByName['word/document.xml']['sourceRecordBytes']
            + $entriesByName['word/media/deep/scan.png']['sourceRecordBytes']
            + $entriesByName['word/media/deep/']['sourceRecordBytes'];
        $wordEntryLocalRecordBytes = $entriesByName['word/document.xml']['localRecordBytes']
            + $entriesByName['word/media/deep/scan.png']['localRecordBytes']
            + $entriesByName['word/media/deep/']['localRecordBytes'];
        $t->same('word', $word['segment']);
        $t->same('word', $word['caseFoldSegment']);
        $t->same(3, $word['occurrenceCount']);
        $t->same(3, $word['entryCount']);
        $t->same(2, $word['fileEntryCount']);
        $t->same(1, $word['directoryEntryCount']);
        $t->same(strlen($documentXml) + strlen($scanBytes), $word['compressedBytes']);
        $t->same(strlen($documentXml) + strlen($scanBytes), $word['uncompressedBytes']);
        $t->same($wordEntryLocalRecordBytes, $word['localRecordBytes']);
        $t->same($wordEntrySourceBytes, $word['sourceRecordBytes']);
        $t->same([0 => 3], $word['pathSegmentIndexCounts']);
        $t->same(['word/' => 3], $word['directoryRootCounts']);
        $t->same(['(directory)' => 1, 'png' => 1, 'xml' => 1], $word['packagePartExtensionCounts']);
        $t->same(['0' => 3], $word['compressionMethodCounts']);
        $t->same([
            'word/document.xml',
            'word/media/deep/',
            'word/media/deep/scan.png',
        ], $word['entryNames']);

        $deep = $segments['deep'];
        $t->same(2, $deep['occurrenceCount']);
        $t->same(2, $deep['entryCount']);
        $t->same(1, $deep['fileEntryCount']);
        $t->same(1, $deep['directoryEntryCount']);
        $t->same(strlen($scanBytes), $deep['compressedBytes']);
        $t->same([2 => 2], $deep['pathSegmentIndexCounts']);
        $t->same(['(directory)' => 1, 'png' => 1], $deep['packagePartExtensionCounts']);
        $t->same(['word/media/deep/', 'word/media/deep/scan.png'], $deep['entryNames']);

        $middle = $positions['middle'];
        $t->same(3, $middle['occurrenceCount']);
        $t->same(2, $middle['entryCount']);
        $t->same(1, $middle['fileEntryCount']);
        $t->same(1, $middle['directoryEntryCount']);
        $t->same(strlen($scanBytes), $middle['compressedBytes']);
        $t->same(strlen($scanBytes), $middle['uncompressedBytes']);
        $t->same(2, $middle['uniqueSegmentCount']);
        $t->same(['deep' => 1, 'media' => 2], $middle['segmentCounts']);
        $t->same(['deep', 'media'], $middle['segments']);
        $t->same([1 => 2, 2 => 1], $middle['pathSegmentIndexCounts']);
        $t->same(['word/media/deep/scan.png', 'word/media/deep/'], $middle['entryNames']);

        $last = $positions['last'];
        $t->same(['deep' => 1, 'document.xml' => 1, 'scan.png' => 1], $last['segmentCounts']);
        $t->same([1 => 1, 2 => 1, 3 => 1], $last['pathSegmentIndexCounts']);

        $only = $positions['only'];
        $t->same(['mimetype' => 1], $only['segmentCounts']);
        $t->same(['mimetype'], $only['entryNames']);
    },

    'rolls up zip package manifest case-fold path segments before shared package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document/>';
        $upperMediaBytes = 'upper media bytes';
        $lowerMediaBytes = 'lower media bytes';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'Word/Media/review.PNG',
                'data' => $upperMediaBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => $lowerMediaBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/MEDIA/',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'mimetype',
                'data' => 'application/epub+zip',
                'method' => 0,
            ],
        ]);
        $manifest = ZipPackage::fromString($zip)->packageManifestPreflight();
        $entriesByName = [];
        foreach ($manifest['entries'] as $entry) {
            $entriesByName[$entry['name']] = $entry;
        }
        $caseFoldSegments = [];
        foreach ($manifest['caseFoldPathSegmentSummaries'] as $summary) {
            $caseFoldSegments[$summary['caseFoldSegment']] = $summary;
        }
        $sumEntryBytes = static function (array $names, string $field) use ($entriesByName): int {
            $bytes = 0;
            foreach ($names as $name) {
                $bytes += $entriesByName[$name][$field];
            }

            return $bytes;
        };
        $wordNames = [
            'Word/Media/review.PNG',
            'word/MEDIA/',
            'word/document.xml',
            'word/media/review.png',
        ];
        $mediaNames = [
            'Word/Media/review.PNG',
            'word/MEDIA/',
            'word/media/review.png',
        ];
        $reviewNames = [
            'Word/Media/review.PNG',
            'word/media/review.png',
        ];

        $t->same(5, $manifest['caseFoldPathSegmentSummaryCount']);
        $t->same(['document.xml', 'media', 'mimetype', 'review.png', 'word'], $manifest['caseFoldPathSegments']);
        $t->same(11, $manifest['caseFoldPathSegmentOccurrenceCount']);
        $t->same([
            'document.xml' => 1,
            'media' => 3,
            'mimetype' => 1,
            'review.png' => 2,
            'word' => 4,
        ], $manifest['caseFoldPathSegmentCounts']);
        $t->same($manifest['caseFoldPathSegmentCounts'], $manifest['caseFoldPathSegmentEntryCounts']);

        $word = $caseFoldSegments['word'];
        $t->same('word', $word['caseFoldSegment']);
        $t->same(4, $word['occurrenceCount']);
        $t->same(4, $word['entryCount']);
        $t->same(3, $word['fileEntryCount']);
        $t->same(1, $word['directoryEntryCount']);
        $t->same(strlen($documentXml) + strlen($upperMediaBytes) + strlen($lowerMediaBytes), $word['compressedBytes']);
        $t->same(strlen($documentXml) + strlen($upperMediaBytes) + strlen($lowerMediaBytes), $word['uncompressedBytes']);
        $t->same($sumEntryBytes($wordNames, 'localRecordBytes'), $word['localRecordBytes']);
        $t->same($sumEntryBytes($wordNames, 'sourceRecordBytes'), $word['sourceRecordBytes']);
        $t->same(2, $word['segmentVariantCount']);
        $t->same(['Word' => 1, 'word' => 3], $word['segmentCounts']);
        $t->same(['Word', 'word'], $word['segments']);
        $t->same([0 => 4], $word['pathSegmentIndexCounts']);
        $t->same(['Word/' => 1, 'word/' => 3], $word['directoryRootCounts']);
        $t->same(['(directory)' => 1, 'png' => 2, 'xml' => 1], $word['packagePartExtensionCounts']);
        $t->same(['0' => 4], $word['compressionMethodCounts']);
        $t->same($wordNames, $word['entryNames']);

        $media = $caseFoldSegments['media'];
        $t->same(3, $media['occurrenceCount']);
        $t->same(3, $media['entryCount']);
        $t->same(2, $media['fileEntryCount']);
        $t->same(1, $media['directoryEntryCount']);
        $t->same(strlen($upperMediaBytes) + strlen($lowerMediaBytes), $media['compressedBytes']);
        $t->same($sumEntryBytes($mediaNames, 'sourceRecordBytes'), $media['sourceRecordBytes']);
        $t->same(3, $media['segmentVariantCount']);
        $t->same(['MEDIA' => 1, 'Media' => 1, 'media' => 1], $media['segmentCounts']);
        $t->same(['MEDIA', 'Media', 'media'], $media['segments']);
        $t->same([1 => 3], $media['pathSegmentIndexCounts']);
        $t->same(['Word/' => 1, 'word/' => 2], $media['directoryRootCounts']);
        $t->same(['(directory)' => 1, 'png' => 2], $media['packagePartExtensionCounts']);
        $t->same($mediaNames, $media['entryNames']);

        $review = $caseFoldSegments['review.png'];
        $t->same(2, $review['occurrenceCount']);
        $t->same(2, $review['entryCount']);
        $t->same(2, $review['fileEntryCount']);
        $t->same(0, $review['directoryEntryCount']);
        $t->same(strlen($upperMediaBytes) + strlen($lowerMediaBytes), $review['compressedBytes']);
        $t->same($sumEntryBytes($reviewNames, 'sourceRecordBytes'), $review['sourceRecordBytes']);
        $t->same(2, $review['segmentVariantCount']);
        $t->same(['review.PNG' => 1, 'review.png' => 1], $review['segmentCounts']);
        $t->same([2 => 2], $review['pathSegmentIndexCounts']);
        $t->same(['Word/' => 1, 'word/' => 1], $review['directoryRootCounts']);
        $t->same(['png' => 2], $review['packagePartExtensionCounts']);
        $t->same($reviewNames, $review['entryNames']);
    },

    'rolls up zip package manifest basenames before shared package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document/>',
                'method' => 0,
            ],
            [
                'name' => 'customXml/document.xml',
                'data' => '<item/>',
                'method' => 0,
            ],
            [
                'name' => 'CustomXml/DOCUMENT.XML',
                'data' => '<upper/>',
                'method' => 0,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => 'png-review',
                'method' => 0,
            ],
            [
                'name' => 'word/Media/Review.PNG',
                'data' => 'png-review-upper',
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'mimetype',
                'data' => 'application/epub+zip',
                'method' => 0,
            ],
        ]);
        $manifest = ZipPackage::fromString($zip)->packageManifestPreflight();
        $entriesByName = [];
        foreach ($manifest['entries'] as $entry) {
            $entriesByName[$entry['name']] = $entry;
        }
        $baseNamesByKey = [];
        foreach ($manifest['packagePartBaseNameSummaries'] as $summary) {
            $baseNamesByKey[$summary['packagePartBaseName']] = $summary;
        }
        $baseNameStemsByKey = [];
        foreach ($manifest['packagePartBaseNameStemSummaries'] as $summary) {
            $baseNameStemsByKey[$summary['packagePartBaseNameStem']] = $summary;
        }
        $caseFoldBaseNamesByKey = [];
        foreach ($manifest['packagePartCaseFoldBaseNameSummaries'] as $summary) {
            $caseFoldBaseNamesByKey[$summary['packagePartCaseFoldBaseName']] = $summary;
        }
        $caseFoldStemsByKey = [];
        foreach ($manifest['packagePartCaseFoldBaseNameStemSummaries'] as $summary) {
            $caseFoldStemsByKey[$summary['packagePartCaseFoldBaseNameStem']] = $summary;
        }
        $sumEntryBytes = static function (array $names, string $field) use ($entriesByName): int {
            $bytes = 0;
            foreach ($names as $name) {
                $bytes += $entriesByName[$name][$field];
            }

            return $bytes;
        };
        $documentStemNames = ['word/document.xml', 'customXml/document.xml'];

        $t->same('Review.PNG', $entriesByName['word/Media/Review.PNG']['packagePartBaseName']);
        $t->same('review.png', $entriesByName['word/Media/Review.PNG']['packagePartCaseFoldBaseName']);
        $t->same('Review', $entriesByName['word/Media/Review.PNG']['packagePartBaseNameStem']);
        $t->same('review', $entriesByName['word/Media/Review.PNG']['packagePartCaseFoldBaseNameStem']);
        $t->same('media', $entriesByName['word/media/']['packagePartBaseName']);
        $t->same(null, $entriesByName['word/media/']['packagePartBaseNameStem']);
        $t->same('mimetype', $entriesByName['mimetype']['packagePartBaseName']);
        $t->same('mimetype', $entriesByName['mimetype']['packagePartBaseNameStem']);

        $t->same(6, $manifest['packagePartBaseNameSummaryCount']);
        $t->same(1, $manifest['duplicatePackagePartBaseNameCount']);
        $t->same(true, $manifest['hasDuplicatePackagePartBaseNames']);
        $t->same(['document.xml'], $manifest['duplicatePackagePartBaseNames']);
        $t->same(2, $baseNamesByKey['document.xml']['entryCount']);
        $t->same(['customXml/' => 1, 'word/' => 1], $baseNamesByKey['document.xml']['directoryRootCounts']);
        $t->same(['xml' => 2], $baseNamesByKey['document.xml']['packagePartExtensionKeyCounts']);

        $t->same(5, $manifest['packagePartBaseNameStemSummaryCount']);
        $t->same(['DOCUMENT', 'Review', 'document', 'mimetype', 'review'], $manifest['packagePartBaseNameStems']);
        $t->same(1, $manifest['duplicatePackagePartBaseNameStemCount']);
        $t->same(true, $manifest['hasDuplicatePackagePartBaseNameStems']);
        $t->same(['document'], $manifest['duplicatePackagePartBaseNameStems']);
        $t->same('document', $baseNameStemsByKey['document']['packagePartBaseNameStem']);
        $t->same('document', $baseNameStemsByKey['document']['packagePartCaseFoldBaseNameStem']);
        $t->same(2, $baseNameStemsByKey['document']['fileEntryCount']);
        $t->same($sumEntryBytes($documentStemNames, 'sourceRecordBytes'), $baseNameStemsByKey['document']['sourceRecordBytes']);
        $t->same(1, $baseNameStemsByKey['document']['packagePartBaseNameVariantCount']);
        $t->same(['document.xml' => 2], $baseNameStemsByKey['document']['packagePartBaseNameCounts']);
        $t->same(['document.xml'], $baseNameStemsByKey['document']['packagePartBaseNames']);
        $t->same(['xml' => 2], $baseNameStemsByKey['document']['packagePartExtensionKeyCounts']);
        $t->same(['customXml/' => 1, 'word/' => 1], $baseNameStemsByKey['document']['directoryRootCounts']);
        $t->same($documentStemNames, $baseNameStemsByKey['document']['entryNames']);
        $t->same(1, $baseNameStemsByKey['Review']['fileEntryCount']);
        $t->same('review', $baseNameStemsByKey['Review']['packagePartCaseFoldBaseNameStem']);
        $t->same(1, $baseNameStemsByKey['review']['fileEntryCount']);

        $t->same(4, $manifest['packagePartCaseFoldBaseNameSummaryCount']);
        $t->same(2, $manifest['duplicatePackagePartCaseFoldBaseNameCount']);
        $t->same(true, $manifest['hasDuplicatePackagePartCaseFoldBaseNames']);
        $t->same(['document.xml', 'review.png'], $manifest['duplicatePackagePartCaseFoldBaseNames']);
        $t->same(3, $caseFoldBaseNamesByKey['document.xml']['entryCount']);
        $t->same(2, $caseFoldBaseNamesByKey['document.xml']['packagePartBaseNameVariantCount']);
        $t->same([
            'DOCUMENT.XML' => 1,
            'document.xml' => 2,
        ], $caseFoldBaseNamesByKey['document.xml']['packagePartBaseNameCounts']);
        $t->same([
            'CustomXml/' => 1,
            'customXml/' => 1,
            'word/' => 1,
        ], $caseFoldBaseNamesByKey['document.xml']['directoryRootCounts']);
        $t->same(['xml' => 3], $caseFoldBaseNamesByKey['document.xml']['packagePartExtensionKeyCounts']);
        $t->same(2, $caseFoldBaseNamesByKey['review.png']['entryCount']);
        $t->same([
            'Review.PNG' => 1,
            'review.png' => 1,
        ], $caseFoldBaseNamesByKey['review.png']['packagePartBaseNameCounts']);
        $t->same(1, $caseFoldBaseNamesByKey['media']['directoryEntryCount']);
        $t->same(['(directory)' => 1], $caseFoldBaseNamesByKey['media']['packagePartExtensionKeyCounts']);

        $t->same(3, $manifest['packagePartCaseFoldBaseNameStemSummaryCount']);
        $t->same(2, $manifest['duplicatePackagePartCaseFoldBaseNameStemCount']);
        $t->same(true, $manifest['hasDuplicatePackagePartCaseFoldBaseNameStems']);
        $t->same(['document', 'review'], $manifest['duplicatePackagePartCaseFoldBaseNameStems']);
        $t->same(3, $caseFoldStemsByKey['document']['fileEntryCount']);
        $t->same([
            'DOCUMENT' => 1,
            'document' => 2,
        ], $caseFoldStemsByKey['document']['packagePartBaseNameStemCounts']);
        $t->same(['xml' => 3], $caseFoldStemsByKey['document']['packagePartExtensionKeyCounts']);
        $t->same(2, $caseFoldStemsByKey['review']['fileEntryCount']);
        $t->same(['mimetype'], $caseFoldStemsByKey['mimetype']['entryNames']);
    },

    'preflights zip package manifest case-insensitive name collisions for package handoff' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>case collision manifest</w:p></w:document>',
            ],
            [
                'name' => 'word/media/Review.PNG',
                'data' => "first reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "second reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $manifest = $package->packageManifestPreflight();
        $casePreflight = $package->caseInsensitiveNamePreflight();
        $raw = ZipPackage::rawStrictImportPreflight($package->bytes(), 4096, 100.0, 4096);
        $expectedEquivalentNames = ['word/media/Review.PNG', 'word/media/review.png'];
        $expectedCollisionEntries = [
            [
                'name' => 'word/media/Review.PNG',
                'caseFoldKey' => 'word/media/review.png',
                'caseInsensitiveEquivalentEntryNames' => $expectedEquivalentNames,
                'hasCaseInsensitiveNameCollision' => true,
                'caseInsensitiveNameCollisionIssues' => ['case-insensitive-name-collision'],
            ],
            [
                'name' => 'word/media/review.png',
                'caseFoldKey' => 'word/media/review.png',
                'caseInsensitiveEquivalentEntryNames' => $expectedEquivalentNames,
                'hasCaseInsensitiveNameCollision' => true,
                'caseInsensitiveNameCollisionIssues' => ['case-insensitive-name-collision'],
            ],
        ];

        $t->same(3, $manifest['entryCount']);
        $t->same(1, $manifest['caseInsensitiveNameCollisionGroupCount']);
        $t->same(2, $manifest['caseInsensitiveNameCollisionEntryCount']);
        $t->same(true, $manifest['hasCaseInsensitiveNameCollisions']);
        $t->same($casePreflight['collisionGroups'], $manifest['caseInsensitiveNameCollisionGroups']);
        $t->same('word/media/review.png', $manifest['caseInsensitiveNameCollisionGroups'][0]['caseFoldKey']);
        $t->same($expectedEquivalentNames, $manifest['caseInsensitiveNameCollisionGroups'][0]['entryNames']);
        $t->same($expectedCollisionEntries, $manifest['caseInsensitiveNameCollisionEntries']);

        $t->same('word/document.xml', $manifest['entries'][0]['name']);
        $t->same('word/document.xml', $manifest['entries'][0]['caseFoldKey']);
        $t->same(['word/document.xml'], $manifest['entries'][0]['caseInsensitiveEquivalentEntryNames']);
        $t->same(false, $manifest['entries'][0]['hasCaseInsensitiveNameCollision']);
        $t->same([], $manifest['entries'][0]['caseInsensitiveNameCollisionIssues']);
        $t->same($expectedCollisionEntries, array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'caseFoldKey' => $entry['caseFoldKey'],
                'caseInsensitiveEquivalentEntryNames' => $entry['caseInsensitiveEquivalentEntryNames'],
                'hasCaseInsensitiveNameCollision' => $entry['hasCaseInsensitiveNameCollision'],
                'caseInsensitiveNameCollisionIssues' => $entry['caseInsensitiveNameCollisionIssues'],
            ],
            array_slice($manifest['entries'], 1)
        ));
        $t->same($manifest, $raw['packageManifest']);
        $t->same(false, $raw['isValid']);
        $t->contains('case-insensitive-name-collisions', implode(',', $raw['diagnostics']));
    },

    'preflights zip package manifest creator host systems for package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>manifest creator host metadata</w:p></w:document>',
                'method' => 8,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x0314,
            ],
            [
                'name' => 'word/media/windows-newer.txt',
                'data' => "windows creator version newer than needed\n",
                'method' => 0,
                'versionNeededToExtract' => 10,
                'versionMadeBy' => 0x0a14,
            ],
            [
                'name' => 'word/media/unix-legacy.bin',
                'data' => "legacy creator version below deflate need\n",
                'method' => 8,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x030a,
            ],
            [
                'name' => 'word/media/unknown.bin',
                'data' => "unknown host with equal creator version\n",
                'method' => 0,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x3f14,
            ],
        ]);
        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $creatorHosts = $package->creatorHostSystemPreflight();
        $raw = ZipPackage::rawStrictImportPreflight($zip, 4096, 100.0, 4096);

        $entryNames = static fn (array $entries): array => array_map(
            static fn (array $entry): string => $entry['name'],
            $entries
        );
        $manifestEntriesByName = [];
        foreach ($manifest['entries'] as $entry) {
            $manifestEntriesByName[$entry['name']] = $entry;
        }
        $expectedUnknownEntry = [
            'name' => 'word/media/unknown.bin',
            'versionMadeBy' => 0x3f14,
            'madeByHostSystem' => 63,
            'madeByHostSystemName' => 'unknown',
            'madeByVersion' => 20,
            'versionNeededToExtract' => 20,
            'localVersionNeededToExtract' => 20,
            'minimumVersionNeededToExtract' => 10,
            'localMinimumVersionNeededToExtract' => 10,
            'versionNeededToExtractMatchesLocalHeader' => true,
            'versionNeededToExtractMeetsFeatureMinimum' => true,
            'localVersionNeededToExtractMeetsFeatureMinimum' => true,
            'versionNeededToExtractExceedsBoundedReader' => false,
            'localVersionNeededToExtractExceedsBoundedReader' => false,
            'creatorVersionMeetsNeeded' => true,
            'creatorVersionComparison' => 'equals-needed',
            'creatorVersionDelta' => 0,
            'creatorHostSystemIsKnown' => false,
            'creatorHostSystemIssues' => ['unknown-creator-host-system'],
        ];
        $expectedBelowNeededEntry = [
            'name' => 'word/media/unix-legacy.bin',
            'versionMadeBy' => 0x030a,
            'madeByHostSystem' => 3,
            'madeByHostSystemName' => 'unix',
            'madeByVersion' => 10,
            'versionNeededToExtract' => 20,
            'localVersionNeededToExtract' => 20,
            'minimumVersionNeededToExtract' => 20,
            'localMinimumVersionNeededToExtract' => 20,
            'versionNeededToExtractMatchesLocalHeader' => true,
            'versionNeededToExtractMeetsFeatureMinimum' => true,
            'localVersionNeededToExtractMeetsFeatureMinimum' => true,
            'versionNeededToExtractExceedsBoundedReader' => false,
            'localVersionNeededToExtractExceedsBoundedReader' => false,
            'creatorVersionMeetsNeeded' => false,
            'creatorVersionComparison' => 'below-needed',
            'creatorVersionDelta' => -10,
            'creatorHostSystemIsKnown' => true,
            'creatorHostSystemIssues' => ['creator-version-below-version-needed'],
        ];
        $expectedCreatorVersionComparisonCounts = [
            'below-needed' => 1,
            'equals-needed' => 2,
            'above-needed' => 1,
        ];
        $expectedCreatorHostSystemSummaries = [
            [
                'madeByHostSystem' => 3,
                'madeByHostSystemName' => 'unix',
                'isKnown' => true,
                'entryCount' => 2,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 0,
                'compressedBytes' => $manifestEntriesByName['word/document.xml']['compressedSize']
                    + $manifestEntriesByName['word/media/unix-legacy.bin']['compressedSize'],
                'uncompressedBytes' => $manifestEntriesByName['word/document.xml']['uncompressedSize']
                    + $manifestEntriesByName['word/media/unix-legacy.bin']['uncompressedSize'],
                'localRecordBytes' => $manifestEntriesByName['word/document.xml']['localRecordBytes']
                    + $manifestEntriesByName['word/media/unix-legacy.bin']['localRecordBytes'],
                'creatorVersionBelowNeededEntryCount' => 1,
                'entryNames' => ['word/document.xml', 'word/media/unix-legacy.bin'],
            ],
            [
                'madeByHostSystem' => 10,
                'madeByHostSystemName' => 'windows-ntfs',
                'isKnown' => true,
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'compressedBytes' => $manifestEntriesByName['word/media/windows-newer.txt']['compressedSize'],
                'uncompressedBytes' => $manifestEntriesByName['word/media/windows-newer.txt']['uncompressedSize'],
                'localRecordBytes' => $manifestEntriesByName['word/media/windows-newer.txt']['localRecordBytes'],
                'creatorVersionBelowNeededEntryCount' => 0,
                'entryNames' => ['word/media/windows-newer.txt'],
            ],
            [
                'madeByHostSystem' => 63,
                'madeByHostSystemName' => 'unknown',
                'isKnown' => false,
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'compressedBytes' => $manifestEntriesByName['word/media/unknown.bin']['compressedSize'],
                'uncompressedBytes' => $manifestEntriesByName['word/media/unknown.bin']['uncompressedSize'],
                'localRecordBytes' => $manifestEntriesByName['word/media/unknown.bin']['localRecordBytes'],
                'creatorVersionBelowNeededEntryCount' => 0,
                'entryNames' => ['word/media/unknown.bin'],
            ],
        ];

        $t->same(4, $manifest['entryCount']);
        $t->same(3, $manifest['creatorHostSystemSummaryCount']);
        $t->same(3, $manifest['knownCreatorHostSystemEntryCount']);
        $t->same(1, $manifest['unknownCreatorHostSystemEntryCount']);
        $t->same(3, $manifest['creatorVersionMeetsNeededEntryCount']);
        $t->same(1, $manifest['creatorVersionBelowNeededEntryCount']);
        $t->same(2, $manifest['creatorVersionEqualNeededEntryCount']);
        $t->same(1, $manifest['creatorVersionAboveNeededEntryCount']);
        $t->same(1, $manifest['creatorVersionBelowNeededKnownHostEntryCount']);
        $t->same(0, $manifest['creatorVersionBelowNeededUnknownHostEntryCount']);
        $t->same(true, $manifest['hasUnknownCreatorHostSystems']);
        $t->same(true, $manifest['hasCreatorVersionBelowNeededEntries']);
        $t->same($expectedCreatorVersionComparisonCounts, $manifest['creatorVersionComparisonCounts']);
        $t->same($expectedCreatorHostSystemSummaries, $manifest['creatorHostSystemSummaries']);
        $t->same([$expectedUnknownEntry], $manifest['unknownCreatorHostSystemEntries']);
        $t->same([$expectedBelowNeededEntry], $manifest['creatorVersionBelowNeededEntries']);

        $t->same($creatorHosts['creatorVersionComparisonCounts'], $manifest['creatorVersionComparisonCounts']);
        $t->same($creatorHosts['knownHostSystemEntryCount'], $manifest['knownCreatorHostSystemEntryCount']);
        $t->same($creatorHosts['unknownHostSystemEntryCount'], $manifest['unknownCreatorHostSystemEntryCount']);
        $t->same($entryNames($creatorHosts['unknownEntries']), $entryNames($manifest['unknownCreatorHostSystemEntries']));
        $t->same(
            $entryNames($creatorHosts['creatorVersionBelowNeededEntries']),
            $entryNames($manifest['creatorVersionBelowNeededEntries'])
        );
        $t->same('equals-needed', $manifestEntriesByName['word/document.xml']['creatorVersionComparison']);
        $t->same('above-needed', $manifestEntriesByName['word/media/windows-newer.txt']['creatorVersionComparison']);
        $t->same('below-needed', $manifestEntriesByName['word/media/unix-legacy.bin']['creatorVersionComparison']);
        $t->same(['creator-version-below-version-needed'], $manifestEntriesByName['word/media/unix-legacy.bin']['creatorHostSystemIssues']);
        $t->same(['unknown-creator-host-system'], $manifestEntriesByName['word/media/unknown.bin']['creatorHostSystemIssues']);

        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
        $t->same(false, $raw['isValid']);
        $t->same(true, $raw['canInstantiate']);
        $t->contains('unknown-creator-host-systems', implode(',', $raw['diagnostics']));
        $t->contains('creator-version-below-version-needed', implode(',', $raw['diagnostics']));
    },

    'preflights zip package manifest archive source records for package handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildCentralDirectorySignaturePackage): void {
        $comment = 'archive package review comment';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>archive source manifest</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "archive source media\n",
                'method' => 0,
            ],
        ], $comment);
        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $layout = ZipPackage::packageByteLayoutPreflight($zip);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $source = $manifest['packageSource'];
        $layoutSummary = $manifest['packageByteLayout'];

        $t->same(strlen($zip), $source['archiveLength']);
        $t->same(strlen($zip), $manifest['archiveBytes']);
        $t->same($source['archiveLength'], $manifest['archiveLength']);
        $t->same(hash('sha256', $zip), $manifest['archiveSha256']);
        $t->same($manifest['archiveSha256'], $source['archiveSha256']);
        $t->same($package->centralDirectoryOffset(), $manifest['centralDirectoryOffset']);
        $t->same($manifest['centralDirectoryOffset'], $source['centralDirectoryOffset']);
        $t->true($manifest['centralDirectoryOffset'] < $manifest['centralDirectoryEnd']);
        $t->same($manifest['centralDirectoryEnd'] - $manifest['centralDirectoryOffset'], $manifest['centralDirectoryBytes']);
        $t->same($manifest['endOfCentralDirectoryOffset'], $manifest['centralDirectoryEnd']);
        $t->same($manifest['centralDirectoryEnd'], $source['centralDirectoryEnd']);
        $t->same(hash('sha256', substr($zip, $manifest['centralDirectoryOffset'], $manifest['centralDirectoryBytes'])), $manifest['centralDirectorySha256']);
        $t->same($manifest['centralDirectorySha256'], $source['centralDirectorySha256']);
        $t->same($layout['centralDirectoryToEocdGapOffset'], $source['centralDirectoryToEocdGapOffset']);
        $t->same($layout['centralDirectoryToEocdGapBytes'], $source['centralDirectoryToEocdGapBytes']);
        $t->same($layout['centralDirectoryToEocdGapSha256'], $source['centralDirectoryToEocdGapSha256']);
        $t->same(22 + strlen($comment), $manifest['endOfCentralDirectoryBytes']);
        $t->same($manifest['endOfCentralDirectoryBytes'], $source['endOfCentralDirectoryBytes']);
        $t->same(strlen($zip), $manifest['endOfCentralDirectoryEnd']);
        $t->same($manifest['endOfCentralDirectoryEnd'], $source['endOfCentralDirectoryEnd']);
        $t->same(strlen($comment), $manifest['packageCommentBytes']);
        $t->same($layout['packageCommentOffset'], $manifest['packageCommentOffset']);
        $t->same($manifest['packageCommentOffset'], $source['packageCommentOffset']);
        $t->same($manifest['packageCommentBytes'], $source['packageCommentBytes']);
        $t->same($manifest['packageCommentOffset'] + strlen($comment), $manifest['packageCommentEnd']);
        $t->same($manifest['packageCommentEnd'], $source['packageCommentEnd']);
        $t->same(hash('sha256', $comment), $manifest['packageCommentSha256']);
        $t->same($manifest['packageCommentSha256'], $source['packageCommentSha256']);
        $t->same(bin2hex(substr($comment, 0, 16)), $manifest['packageCommentPreviewHex']);
        $t->same($manifest['packageCommentPreviewHex'], $source['packageCommentPreviewHex']);
        $t->same(16, $manifest['packageCommentPreviewByteCount']);
        $t->same($manifest['packageCommentPreviewByteCount'], $source['packageCommentPreviewByteCount']);
        $t->same('zip-package-comment-source-metadata-only', $manifest['packageCommentByteExposurePolicy']);
        $t->same($manifest['packageCommentByteExposurePolicy'], $source['packageCommentByteExposurePolicy']);
        $t->same(false, $manifest['canExposePackageCommentBytes']);
        $t->same($manifest['canExposePackageCommentBytes'], $source['canExposePackageCommentBytes']);
        $t->same(true, $manifest['hasPackageComment']);
        $t->same($manifest['hasPackageComment'], $source['hasPackageComment']);
        $t->same(hash('sha256', substr($zip, $manifest['endOfCentralDirectoryOffset'], $manifest['endOfCentralDirectoryBytes'])), $manifest['endOfCentralDirectorySha256']);
        $t->same($manifest['endOfCentralDirectorySha256'], $source['endOfCentralDirectorySha256']);
        $t->same(false, $manifest['hasCentralDirectorySignature']);
        $t->same(null, $manifest['centralDirectorySignatureOffset']);
        $t->same(0, $manifest['centralDirectorySignatureBytes']);
        $t->same(null, $manifest['centralDirectorySignatureSha256']);
        $t->same('zip-package-byte-layout-summary-v1', $manifest['packageByteLayoutVersion']);
        $t->same($manifest['packageByteLayoutVersion'], $layoutSummary['layoutVersion']);
        $t->same($layout['archiveLength'], $layoutSummary['archiveLength']);
        $t->same($layout['archiveSha256'], $layoutSummary['archiveSha256']);
        $t->same($layout['prefixByteCount'], $layoutSummary['prefixByteCount']);
        $t->same($layout['localRegionBytes'], $layoutSummary['localRegionBytes']);
        $t->same($layout['localRegionSha256'], $layoutSummary['localRegionSha256']);
        $t->same($layout['localEntryRecordBytes'], $layoutSummary['localEntryRecordBytes']);
        $t->same($layout['unclaimedLocalBytes'], $layoutSummary['unclaimedLocalBytes']);
        $t->same($layout['interEntryGapCount'], $layoutSummary['interEntryGapCount']);
        $t->same($layout['unaccountedArchiveBytes'], $layoutSummary['unaccountedArchiveBytes']);
        $t->same($layout['trailingByteCount'], $layoutSummary['trailingByteCount']);
        $t->same($layout['isLocalRegionContiguous'], $layoutSummary['isLocalRegionContiguous']);
        $t->same($layout['isArchiveLayoutContiguous'], $layoutSummary['isArchiveLayoutContiguous']);
        $t->same(0, $manifest['packageByteLayoutIssueCount']);
        $t->same([], $manifest['packageByteLayoutIssues']);
        $t->same($layoutSummary['issueCount'], $manifest['packageByteLayoutIssueCount']);
        $t->same($layoutSummary['issues'], $manifest['packageByteLayoutIssues']);
        $t->same($layoutSummary['isLocalRegionContiguous'], $manifest['packageByteLayoutIsLocalRegionContiguous']);
        $t->same($layoutSummary['isArchiveLayoutContiguous'], $manifest['packageByteLayoutIsArchiveLayoutContiguous']);
        $t->same($layoutSummary['unclaimedLocalBytes'], $manifest['packageByteLayoutUnclaimedLocalBytes']);
        $t->same($layoutSummary['interEntryGapCount'], $manifest['packageByteLayoutInterEntryGapCount']);
        $t->same($layoutSummary['unaccountedArchiveBytes'], $manifest['packageByteLayoutUnaccountedArchiveBytes']);
        $t->same($layoutSummary['trailingByteCount'], $manifest['packageByteLayoutTrailingByteCount']);
        $t->same(count($layout['entries']), count($layoutSummary['entries']));
        $t->same($layout['entries'][0]['name'], $layoutSummary['entries'][0]['name']);
        $t->same($layout['entries'][0]['localRecordBytes'], $layoutSummary['entries'][0]['localRecordBytes']);
        $t->same($layout['entries'][0]['recordEnd'], $layoutSummary['entries'][0]['recordEnd']);
        $t->same($layout['entries'][0]['nextOffset'], $layoutSummary['entries'][0]['nextOffset']);
        $t->same([], $layoutSummary['entries'][0]['issues']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);

        $signedZip = $buildCentralDirectorySignaturePackage();
        $signedPackage = ZipPackage::fromString($signedZip);
        $signedManifest = $signedPackage->packageManifestPreflight();
        $signedRaw = ZipPackage::rawStrictImportPreflight($signedZip, 2048, 100.0, 2048);
        $signedSignature = ZipPackage::centralDirectorySignaturePolicyPreflight($signedZip);
        $signedLayout = ZipPackage::packageByteLayoutPreflight($signedZip);
        $signedSource = $signedManifest['packageSource'];
        $signedLayoutSummary = $signedManifest['packageByteLayout'];

        $t->same(true, $signedManifest['hasCentralDirectorySignature']);
        $t->same(strlen('central-signature'), $signedManifest['centralDirectorySignatureBytes']);
        $t->same(hash('sha256', 'central-signature'), $signedManifest['centralDirectorySignatureSha256']);
        $t->same(
            hash('sha256', substr($signedZip, $signedManifest['centralDirectorySignatureOffset'] + 6, $signedManifest['centralDirectorySignatureBytes'])),
            $signedManifest['centralDirectorySignatureSha256']
        );
        $t->same(
            $signedManifest['centralDirectorySignatureSha256'],
            $signedPackage->centralDirectorySignaturePreflight()['signatureSha256']
        );
        $t->same($signedSignature['offset'], $signedSource['centralDirectoryToEocdGapOffset']);
        $t->same($signedSignature['signatureLength'] + 6, $signedSource['centralDirectoryToEocdGapBytes']);
        $t->same(
            hash(
                'sha256',
                substr($signedZip, $signedSource['centralDirectoryToEocdGapOffset'], $signedSource['centralDirectoryToEocdGapBytes'])
            ),
            $signedSource['centralDirectoryToEocdGapSha256']
        );
        $t->same($signedSource['centralDirectoryToEocdGapOffset'], $signedManifest['centralDirectoryToEocdGapOffset']);
        $t->same($signedSource['centralDirectoryToEocdGapBytes'], $signedManifest['centralDirectoryToEocdGapBytes']);
        $t->same($signedSource['centralDirectoryToEocdGapSha256'], $signedManifest['centralDirectoryToEocdGapSha256']);
        $t->same($signedLayout['centralDirectoryToEocdGapBytes'], $signedLayoutSummary['centralDirectoryToEocdGapBytes']);
        $t->same($signedLayout['centralDirectoryToEocdGapSignature'], $signedLayoutSummary['centralDirectoryToEocdGapSignature']);
        $t->same($signedLayout['centralDirectoryToEocdGapSha256'], $signedLayoutSummary['centralDirectoryToEocdGapSha256']);
        $t->same(true, $signedLayoutSummary['isCentralDirectoryToEocdGapExplainedBySignature']);
        $t->same(true, $signedManifest['packageByteLayoutIsArchiveLayoutContiguous']);
        $t->same(0, $signedManifest['packageByteLayoutIssueCount']);
        $t->same($signedManifest, $signedRaw['packageManifest']);
        $t->same($signedManifest, $signedRaw['strictImport']['packageManifest']);
    },

    'preflights zip package manifest eocd fixed fields for package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $comment = 'package manifest eocd fixed field comment';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>manifest eocd fixed fields</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 0,
            ],
        ], $comment);
        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $fixedFields = ZipPackage::endOfCentralDirectoryFixedFieldsPreflight($zip);
        $expectedManifestFixedFields = $fixedFields;
        unset($expectedManifestFixedFields['packageComment'], $expectedManifestFixedFields['packageCommentHex']);
        $expectedManifestFixedFields['packageCommentByteExposurePolicy'] = 'zip-package-comment-source-metadata-only';
        $expectedManifestFixedFields['canExposePackageCommentBytes'] = false;

        $t->same($expectedManifestFixedFields, $manifest['endOfCentralDirectoryFixedFields']);
        $t->same($expectedManifestFixedFields, $manifest['packageSource']['endOfCentralDirectoryFixedFields']);
        $t->same(0, $manifest['endOfCentralDirectoryFixedFieldIssueCount']);
        $t->same(false, $manifest['hasEndOfCentralDirectoryFixedFieldIssues']);
        $t->same([], $manifest['endOfCentralDirectoryFixedFieldIssues']);
        $t->same($manifest['endOfCentralDirectoryFixedFieldIssueCount'], $manifest['packageSource']['endOfCentralDirectoryFixedFieldIssueCount']);
        $t->same($manifest['hasEndOfCentralDirectoryFixedFieldIssues'], $manifest['packageSource']['hasEndOfCentralDirectoryFixedFieldIssues']);
        $t->same($manifest['endOfCentralDirectoryFixedFieldIssues'], $manifest['packageSource']['endOfCentralDirectoryFixedFieldIssues']);
        $t->same(false, array_key_exists('packageComment', $manifest['endOfCentralDirectoryFixedFields']));
        $t->same(false, array_key_exists('packageCommentHex', $manifest['endOfCentralDirectoryFixedFields']));
        $t->same($comment, $fixedFields['packageComment']);
        $t->same(bin2hex($comment), $fixedFields['packageCommentHex']);
        $t->same(bin2hex(substr($comment, 0, 16)), $manifest['endOfCentralDirectoryFixedFields']['packageCommentPreviewHex']);
        $t->same(strlen($comment), $manifest['endOfCentralDirectoryFixedFields']['packageCommentLength']);
        $t->same($manifest['endOfCentralDirectoryOffset'], $manifest['endOfCentralDirectoryFixedFields']['eocdOffset']);
        $t->same($manifest['endOfCentralDirectoryOffset'], $manifest['endOfCentralDirectoryFixedFields']['fixedHeaderOffset']);
        $t->same($manifest['endOfCentralDirectoryOffset'] + 22, $manifest['endOfCentralDirectoryFixedFields']['fixedHeaderEnd']);
        $t->same($manifest['entryCount'], $manifest['endOfCentralDirectoryFixedFields']['totalEntryCount']);
        $t->same($manifest['entryCount'], $manifest['endOfCentralDirectoryFixedFields']['diskEntryCount']);
        $t->same($manifest['centralDirectoryOffset'], $manifest['endOfCentralDirectoryFixedFields']['centralDirectoryOffset']);
        $t->same($manifest['centralDirectoryBytes'], $manifest['endOfCentralDirectoryFixedFields']['centralDirectorySize']);
        $t->same($manifest['centralDirectoryEnd'], $manifest['endOfCentralDirectoryFixedFields']['centralDirectoryEnd']);
        $t->same($manifest, $strict['packageManifest']);
        $t->same($fixedFields, $raw['endOfCentralDirectoryFixedFields']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },

    'preflights zip package manifest central directory review field byte totals for package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>manifest central review fields</w:p></w:body></w:document>';
        $mediaBytes = "manifest central review media\n";
        $documentExtra = pack('vva*', 0xcafe, strlen('manifest-review'), 'manifest-review');
        $documentComment = 'document manifest review';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
                'localExtra' => '',
                'centralExtra' => $documentExtra,
                'comment' => $documentComment,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ], 'manifest central wrapper');
        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $variableFields = ZipPackage::centralDirectoryVariableFieldsPreflight($zip);
        $entries = [];
        foreach ($manifest['entries'] as $entry) {
            $entries[$entry['name']] = $entry;
        }
        $documentEntry = $entries['word/document.xml'];
        $mediaEntry = $entries['word/media/review.bin'];

        $expectedRawNameBytes = strlen('word/document.xml') + strlen('word/media/review.bin');
        $expectedReviewFieldBytes = strlen($documentExtra) + strlen($documentComment);
        $expectedVariableFieldBytes = $expectedRawNameBytes + $expectedReviewFieldBytes;
        $expectedEntryCommentSummaries = [
            [
                'name' => 'word/document.xml',
                'centralDirectoryIndex' => 0,
                'directoryRoot' => 'word/',
                'packagePartExtensionKey' => 'xml',
                'compressionMethod' => 0,
                'compressionMethodName' => 'stored',
                'centralDirectoryRawCommentOffset' => $documentEntry['centralDirectoryRawCommentOffset'],
                'centralDirectoryRawCommentBytes' => strlen($documentComment),
                'centralDirectoryRawCommentSha256' => hash('sha256', $documentComment),
                'centralDirectoryRecordBytes' => $documentEntry['centralDirectoryRecordBytes'],
                'centralDirectoryReviewFieldBytes' => $expectedReviewFieldBytes,
                'sourceRecordBytes' => $documentEntry['sourceRecordBytes'],
                'entryCommentByteExposurePolicy' => 'zip-entry-comment-source-metadata-only',
                'entryCommentCanExposeBytes' => false,
            ],
        ];

        $t->same(2, $manifest['entryCount']);
        $t->same(46 * 2, $manifest['centralDirectoryFixedHeaderBytes']);
        $t->same(
            $documentEntry['centralDirectoryRecordBytes'] + $mediaEntry['centralDirectoryRecordBytes'],
            $manifest['centralDirectoryRecordBytes']
        );
        $t->same($expectedVariableFieldBytes, $manifest['centralDirectoryVariableFieldBytes']);
        $t->same($expectedRawNameBytes, $manifest['centralDirectoryRawNameBytes']);
        $t->same(strlen($documentExtra), $manifest['centralDirectoryExtraFieldBytes']);
        $t->same(strlen($documentComment), $manifest['centralDirectoryRawCommentBytes']);
        $t->same($expectedReviewFieldBytes, $manifest['centralDirectoryReviewFieldBytes']);
        $t->same(1, $manifest['centralExtraFieldEntryCount']);
        $t->same(1, $manifest['entryCommentCount']);
        $t->same(true, $manifest['hasEntryComments']);
        $t->same(['word/document.xml'], $manifest['commentedEntryNames']);
        $t->same(1, $manifest['entryCommentSummaryCount']);
        $t->same($documentEntry['sourceRecordBytes'], $manifest['entryCommentSourceRecordBytes']);
        $t->same($expectedEntryCommentSummaries, $manifest['entryCommentSummaries']);
        $t->same(true, $manifest['hasCentralDirectoryReviewFields']);
        $t->same($variableFields['centralDirectoryVariableFieldBytes'], $manifest['centralDirectoryVariableFieldBytes']);
        $t->same($variableFields['centralDirectoryReviewFieldBytes'], $manifest['centralDirectoryReviewFieldBytes']);
        $t->same($variableFields['centralExtraFieldEntryCount'], $manifest['centralExtraFieldEntryCount']);
        $t->same($variableFields['entryCommentCount'], $manifest['entryCommentCount']);

        $t->same(46 + strlen('word/document.xml') + strlen($documentExtra) + strlen($documentComment), $documentEntry['centralDirectoryRecordBytes']);
        $t->same(46, $documentEntry['centralDirectoryFixedHeaderBytes']);
        $t->same(strlen('word/document.xml') + strlen($documentExtra) + strlen($documentComment), $documentEntry['centralDirectoryVariableFieldBytes']);
        $t->same(strlen('word/document.xml'), $documentEntry['centralDirectoryRawNameBytes']);
        $t->same(strlen($documentExtra), $documentEntry['centralDirectoryExtraFieldBytes']);
        $t->same(strlen($documentComment), $documentEntry['centralDirectoryRawCommentBytes']);
        $t->same($expectedReviewFieldBytes, $documentEntry['centralDirectoryReviewFieldBytes']);
        $t->same(
            hash('sha256', substr($zip, $documentEntry['centralDirectoryRecordOffset'], $documentEntry['centralDirectoryRecordBytes'])),
            $documentEntry['centralDirectoryRecordSha256']
        );

        $t->same(46 + strlen('word/media/review.bin'), $mediaEntry['centralDirectoryRecordBytes']);
        $t->same(46, $mediaEntry['centralDirectoryFixedHeaderBytes']);
        $t->same(strlen('word/media/review.bin'), $mediaEntry['centralDirectoryVariableFieldBytes']);
        $t->same(strlen('word/media/review.bin'), $mediaEntry['centralDirectoryRawNameBytes']);
        $t->same(0, $mediaEntry['centralDirectoryExtraFieldBytes']);
        $t->same(0, $mediaEntry['centralDirectoryRawCommentBytes']);
        $t->same(0, $mediaEntry['centralDirectoryReviewFieldBytes']);
        $t->same(
            hash('sha256', substr($zip, $mediaEntry['centralDirectoryRecordOffset'], $mediaEntry['centralDirectoryRecordBytes'])),
            $mediaEntry['centralDirectoryRecordSha256']
        );
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },

    'preflights zip package manifest compressed payload hashes for package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>manifest payload hash</w:p></w:body></w:document>';
        $mediaBytes = "stored image bytes\n";
        $documentCompressed = gzdeflate($documentXml);
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/image.png',
                'data' => $mediaBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $documentEntry = $manifest['entries'][0];
        $mediaEntry = $manifest['entries'][1];
        $directoryEntry = $manifest['entries'][2];

        $t->same(hash('sha256', $documentCompressed), $documentEntry['compressedDataSha256']);
        $t->same(
            hash('sha256', substr($zip, $documentEntry['compressedDataOffset'], $documentEntry['compressedSize'])),
            $documentEntry['compressedDataSha256']
        );
        $t->same(hash('sha256', $mediaBytes), $mediaEntry['compressedDataSha256']);
        $t->same(hash('sha256', ''), $directoryEntry['compressedDataSha256']);
        $t->same($documentEntry['compressedDataEnd'], $mediaEntry['localHeaderOffset']);
        $t->same($mediaEntry['compressedDataEnd'], $directoryEntry['localHeaderOffset']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },

    'preflights zip package manifest data descriptor source records for package handoff' => static function (TestRunner $t) use ($buildZipPackage, $crc32, $pathSegmentPositionReviews): void {
        $documentXml = '<w:document><w:body><w:p>descriptor manifest source</w:p></w:body></w:document>';
        $commentsXml = '<w:comments><w:comment>descriptor manifest sidecar</w:comment></w:comments>';
        $commentsCompressed = gzdeflate($commentsXml);
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $documentEntry = $manifest['entries'][0];
        $commentsEntry = $manifest['entries'][1];
        $descriptorBytes = substr(
            $zip,
            $commentsEntry['dataDescriptorOffset'],
            $commentsEntry['dataDescriptorBytes']
        );
        $expectedManifestEntries = [
            [
                'name' => 'word/document.xml',
                'isDirectory' => false,
                'caseFoldKey' => 'word/document.xml',
                'caseInsensitiveEquivalentEntryNames' => ['word/document.xml'],
                'hasCaseInsensitiveNameCollision' => false,
                'caseInsensitiveNameCollisionIssues' => [],
                'versionMadeBy' => 0x0314,
                'madeByHostSystem' => 3,
                'madeByHostSystemName' => 'unix',
                'madeByVersion' => 20,
                'versionNeededToExtract' => 20,
                'localVersionNeededToExtract' => 20,
                'minimumVersionNeededToExtract' => 10,
                'localMinimumVersionNeededToExtract' => 10,
                'versionNeededToExtractMatchesLocalHeader' => true,
                'versionNeededToExtractMeetsFeatureMinimum' => true,
                'localVersionNeededToExtractMeetsFeatureMinimum' => true,
                'versionNeededToExtractExceedsBoundedReader' => false,
                'localVersionNeededToExtractExceedsBoundedReader' => false,
                'creatorVersionMeetsNeeded' => true,
                'creatorVersionComparison' => 'equals-needed',
                'creatorVersionDelta' => 0,
                'creatorHostSystemIsKnown' => true,
                'creatorHostSystemIssues' => [],
                'directoryRoot' => 'word/',
                'entryNameBytes' => strlen('word/document.xml'),
                'entryNameLengthBucket' => '16-to-63-bytes',
                'pathSegments' => ['word', 'document.xml'],
                'pathSegmentPositionReviews' => $pathSegmentPositionReviews(['word', 'document.xml']),
                'pathSegmentCount' => 2,
                'directoryDepth' => 1,
                'packagePartBaseName' => 'document.xml',
                'packagePartCaseFoldBaseName' => 'document.xml',
                'packagePartBaseNameStem' => 'document',
                'packagePartCaseFoldBaseNameStem' => 'document',
                'packagePartExtension' => 'xml',
                'packagePartExtensionKey' => 'xml',
                'extensionlessPackagePart' => false,
                'centralDirectoryIndex' => 0,
                'localHeaderOrder' => 0,
                'localHeaderOrderDelta' => 0,
                'localHeaderOrderDisplacement' => 0,
                'localHeaderOrderRelation' => 'same-order',
                'localHeaderOrderMatchesCentralDirectoryOrder' => true,
                'localHeaderNameAtCentralDirectoryIndex' => 'word/document.xml',
                'centralDirectoryNameAtLocalHeaderOrder' => 'word/document.xml',
                'compressionMethod' => 0,
                'crc32Hex' => sprintf('%08x', $crc32($documentXml)),
                'compressedSize' => strlen($documentXml),
                'uncompressedSize' => strlen($documentXml),
                'expansionRatio' => 1.0,
                'localHeaderSha256' => $documentEntry['localHeaderSha256'],
                'localHeaderFixedHeaderBytes' => $documentEntry['localHeaderFixedHeaderBytes'],
                'localHeaderVariableFieldBytes' => $documentEntry['localHeaderVariableFieldBytes'],
                'localHeaderVariableFieldSha256' => $documentEntry['localHeaderVariableFieldSha256'],
                'localHeaderRawNameBytes' => $documentEntry['localHeaderRawNameBytes'],
                'localHeaderRawNameSha256' => $documentEntry['localHeaderRawNameSha256'],
                'localHeaderExtraFieldBytes' => $documentEntry['localHeaderExtraFieldBytes'],
                'localHeaderExtraFieldSha256' => $documentEntry['localHeaderExtraFieldSha256'],
                'localHeaderReviewFieldBytes' => $documentEntry['localHeaderReviewFieldBytes'],
                'localRecordBytes' => $documentEntry['localRecordBytes'],
                'localRecordSha256' => $documentEntry['localRecordSha256'],
                'compressedDataSha256' => hash('sha256', $documentXml),
                'usesDataDescriptor' => false,
                'dataDescriptorBytes' => 0,
                'dataDescriptorSha256' => null,
                'centralDirectoryRecordBytes' => $documentEntry['centralDirectoryRecordBytes'],
                'centralDirectoryRecordSha256' => $documentEntry['centralDirectoryRecordSha256'],
                'centralDirectoryFixedHeaderBytes' => $documentEntry['centralDirectoryFixedHeaderBytes'],
                'centralDirectoryVariableFieldBytes' => $documentEntry['centralDirectoryVariableFieldBytes'],
                'centralDirectoryVariableFieldSha256' => $documentEntry['centralDirectoryVariableFieldSha256'],
                'centralDirectoryRawNameBytes' => $documentEntry['centralDirectoryRawNameBytes'],
                'centralDirectoryRawNameSha256' => $documentEntry['centralDirectoryRawNameSha256'],
                'centralDirectoryExtraFieldBytes' => $documentEntry['centralDirectoryExtraFieldBytes'],
                'centralDirectoryExtraFieldSha256' => $documentEntry['centralDirectoryExtraFieldSha256'],
                'centralDirectoryRawCommentBytes' => $documentEntry['centralDirectoryRawCommentBytes'],
                'centralDirectoryRawCommentSha256' => $documentEntry['centralDirectoryRawCommentSha256'],
                'centralDirectoryReviewFieldBytes' => $documentEntry['centralDirectoryReviewFieldBytes'],
                'sourceRecordBytes' => $documentEntry['sourceRecordBytes'],
            ],
            [
                'name' => 'word/comments.xml',
                'isDirectory' => false,
                'caseFoldKey' => 'word/comments.xml',
                'caseInsensitiveEquivalentEntryNames' => ['word/comments.xml'],
                'hasCaseInsensitiveNameCollision' => false,
                'caseInsensitiveNameCollisionIssues' => [],
                'versionMadeBy' => 0x0314,
                'madeByHostSystem' => 3,
                'madeByHostSystemName' => 'unix',
                'madeByVersion' => 20,
                'versionNeededToExtract' => 20,
                'localVersionNeededToExtract' => 20,
                'minimumVersionNeededToExtract' => 20,
                'localMinimumVersionNeededToExtract' => 20,
                'versionNeededToExtractMatchesLocalHeader' => true,
                'versionNeededToExtractMeetsFeatureMinimum' => true,
                'localVersionNeededToExtractMeetsFeatureMinimum' => true,
                'versionNeededToExtractExceedsBoundedReader' => false,
                'localVersionNeededToExtractExceedsBoundedReader' => false,
                'creatorVersionMeetsNeeded' => true,
                'creatorVersionComparison' => 'equals-needed',
                'creatorVersionDelta' => 0,
                'creatorHostSystemIsKnown' => true,
                'creatorHostSystemIssues' => [],
                'directoryRoot' => 'word/',
                'entryNameBytes' => strlen('word/comments.xml'),
                'entryNameLengthBucket' => '16-to-63-bytes',
                'pathSegments' => ['word', 'comments.xml'],
                'pathSegmentPositionReviews' => $pathSegmentPositionReviews(['word', 'comments.xml']),
                'pathSegmentCount' => 2,
                'directoryDepth' => 1,
                'packagePartBaseName' => 'comments.xml',
                'packagePartCaseFoldBaseName' => 'comments.xml',
                'packagePartBaseNameStem' => 'comments',
                'packagePartCaseFoldBaseNameStem' => 'comments',
                'packagePartExtension' => 'xml',
                'packagePartExtensionKey' => 'xml',
                'extensionlessPackagePart' => false,
                'centralDirectoryIndex' => 1,
                'localHeaderOrder' => 1,
                'localHeaderOrderDelta' => 0,
                'localHeaderOrderDisplacement' => 0,
                'localHeaderOrderRelation' => 'same-order',
                'localHeaderOrderMatchesCentralDirectoryOrder' => true,
                'localHeaderNameAtCentralDirectoryIndex' => 'word/comments.xml',
                'centralDirectoryNameAtLocalHeaderOrder' => 'word/comments.xml',
                'compressionMethod' => 8,
                'crc32Hex' => sprintf('%08x', $crc32($commentsXml)),
                'compressedSize' => strlen($commentsCompressed),
                'uncompressedSize' => strlen($commentsXml),
                'expansionRatio' => strlen($commentsXml) / strlen($commentsCompressed),
                'localHeaderSha256' => $commentsEntry['localHeaderSha256'],
                'localHeaderFixedHeaderBytes' => $commentsEntry['localHeaderFixedHeaderBytes'],
                'localHeaderVariableFieldBytes' => $commentsEntry['localHeaderVariableFieldBytes'],
                'localHeaderVariableFieldSha256' => $commentsEntry['localHeaderVariableFieldSha256'],
                'localHeaderRawNameBytes' => $commentsEntry['localHeaderRawNameBytes'],
                'localHeaderRawNameSha256' => $commentsEntry['localHeaderRawNameSha256'],
                'localHeaderExtraFieldBytes' => $commentsEntry['localHeaderExtraFieldBytes'],
                'localHeaderExtraFieldSha256' => $commentsEntry['localHeaderExtraFieldSha256'],
                'localHeaderReviewFieldBytes' => $commentsEntry['localHeaderReviewFieldBytes'],
                'localRecordBytes' => $commentsEntry['localRecordBytes'],
                'localRecordSha256' => $commentsEntry['localRecordSha256'],
                'compressedDataSha256' => hash('sha256', $commentsCompressed),
                'usesDataDescriptor' => true,
                'dataDescriptorBytes' => strlen($descriptorBytes),
                'dataDescriptorSha256' => hash('sha256', $descriptorBytes),
                'centralDirectoryRecordBytes' => $commentsEntry['centralDirectoryRecordBytes'],
                'centralDirectoryRecordSha256' => $commentsEntry['centralDirectoryRecordSha256'],
                'centralDirectoryFixedHeaderBytes' => $commentsEntry['centralDirectoryFixedHeaderBytes'],
                'centralDirectoryVariableFieldBytes' => $commentsEntry['centralDirectoryVariableFieldBytes'],
                'centralDirectoryVariableFieldSha256' => $commentsEntry['centralDirectoryVariableFieldSha256'],
                'centralDirectoryRawNameBytes' => $commentsEntry['centralDirectoryRawNameBytes'],
                'centralDirectoryRawNameSha256' => $commentsEntry['centralDirectoryRawNameSha256'],
                'centralDirectoryExtraFieldBytes' => $commentsEntry['centralDirectoryExtraFieldBytes'],
                'centralDirectoryExtraFieldSha256' => $commentsEntry['centralDirectoryExtraFieldSha256'],
                'centralDirectoryRawCommentBytes' => $commentsEntry['centralDirectoryRawCommentBytes'],
                'centralDirectoryRawCommentSha256' => $commentsEntry['centralDirectoryRawCommentSha256'],
                'centralDirectoryReviewFieldBytes' => $commentsEntry['centralDirectoryReviewFieldBytes'],
                'sourceRecordBytes' => $commentsEntry['sourceRecordBytes'],
            ],
        ];
        $expectedCompressionMethodSummaries = [
            [
                'compressionMethod' => 0,
                'compressionMethodName' => 'stored',
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen($documentXml),
                'uncompressedBytes' => strlen($documentXml),
                'localRecordBytes' => $documentEntry['localRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
            ],
            [
                'compressionMethod' => 8,
                'compressionMethodName' => 'deflated',
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen($commentsCompressed),
                'uncompressedBytes' => strlen($commentsXml),
                'localRecordBytes' => $commentsEntry['localRecordBytes'],
                'dataDescriptorEntryCount' => 1,
                'dataDescriptorBytes' => strlen($descriptorBytes),
            ],
        ];
        $expectedGeneralPurposeFlagSummaries = [
            [
                'generalPurposeFlags' => 0x0800,
                'generalPurposeFlagsHex' => '0800',
                'flagNames' => ['utf-8-names'],
                'unsupportedFlagBits' => 0,
                'unsupportedFlagBitsHex' => '0000',
                'isSupportedByReader' => true,
                'usesUtf8Names' => true,
                'usesDataDescriptor' => false,
                'deflateOptionFlags' => 0,
                'deflateOptionName' => null,
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen($documentXml),
                'uncompressedBytes' => strlen($documentXml),
                'localRecordBytes' => $documentEntry['localRecordBytes'],
                'dataDescriptorEntryCount' => 0,
                'dataDescriptorBytes' => 0,
                'entryNames' => ['word/document.xml'],
            ],
            [
                'generalPurposeFlags' => 0x0808,
                'generalPurposeFlagsHex' => '0808',
                'flagNames' => ['data-descriptor', 'utf-8-names'],
                'unsupportedFlagBits' => 0,
                'unsupportedFlagBitsHex' => '0000',
                'isSupportedByReader' => true,
                'usesUtf8Names' => true,
                'usesDataDescriptor' => true,
                'deflateOptionFlags' => 0,
                'deflateOptionName' => null,
                'entryCount' => 1,
                'fileEntryCount' => 1,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen($commentsCompressed),
                'uncompressedBytes' => strlen($commentsXml),
                'localRecordBytes' => $commentsEntry['localRecordBytes'],
                'dataDescriptorEntryCount' => 1,
                'dataDescriptorBytes' => strlen($descriptorBytes),
                'entryNames' => ['word/comments.xml'],
            ],
        ];
        $expectedVersionNeededToExtractSummaries = [
            [
                'versionNeededToExtract' => 20,
                'entryCount' => 2,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen($documentXml) + strlen($commentsCompressed),
                'uncompressedBytes' => strlen($documentXml) + strlen($commentsXml),
                'localRecordBytes' => $documentEntry['localRecordBytes'] + $commentsEntry['localRecordBytes'],
                'sourceRecordBytes' => $documentEntry['sourceRecordBytes'] + $commentsEntry['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 1,
                'dataDescriptorBytes' => strlen($descriptorBytes),
                'minimumVersionNeededToExtracts' => [10, 20],
                'compressionMethodNames' => ['deflated', 'stored'],
                'entryNames' => ['word/document.xml', 'word/comments.xml'],
            ],
        ];
        $expectedCreatorVersionComparisonCounts = [
            'below-needed' => 0,
            'equals-needed' => 2,
            'above-needed' => 0,
        ];
        $expectedCreatorHostSystemSummaries = [
            [
                'madeByHostSystem' => 3,
                'madeByHostSystemName' => 'unix',
                'isKnown' => true,
                'entryCount' => 2,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen($documentXml) + strlen($commentsCompressed),
                'uncompressedBytes' => strlen($documentXml) + strlen($commentsXml),
                'localRecordBytes' => $documentEntry['localRecordBytes'] + $commentsEntry['localRecordBytes'],
                'creatorVersionBelowNeededEntryCount' => 0,
                'entryNames' => ['word/document.xml', 'word/comments.xml'],
            ],
        ];
        $expectedDirectoryRootSummaries = [
            [
                'directoryRoot' => 'word/',
                'entryCount' => 2,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 0,
                'compressedBytes' => strlen($documentXml) + strlen($commentsCompressed),
                'uncompressedBytes' => strlen($documentXml) + strlen($commentsXml),
                'localRecordBytes' => $documentEntry['localRecordBytes'] + $commentsEntry['localRecordBytes'],
                'sourceRecordBytes' => $documentEntry['sourceRecordBytes'] + $commentsEntry['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 1,
                'dataDescriptorBytes' => strlen($descriptorBytes),
                'entryNames' => ['word/document.xml', 'word/comments.xml'],
            ],
        ];
        $expectedPackagePartExtensionSummaries = [
            [
                'extensionKey' => 'xml',
                'packagePartExtension' => 'xml',
                'fileEntryCount' => 2,
                'compressedBytes' => strlen($documentXml) + strlen($commentsCompressed),
                'uncompressedBytes' => strlen($documentXml) + strlen($commentsXml),
                'localRecordBytes' => $documentEntry['localRecordBytes'] + $commentsEntry['localRecordBytes'],
                'sourceRecordBytes' => $documentEntry['sourceRecordBytes'] + $commentsEntry['sourceRecordBytes'],
                'dataDescriptorEntryCount' => 1,
                'dataDescriptorBytes' => strlen($descriptorBytes),
                'entryNames' => ['word/document.xml', 'word/comments.xml'],
            ],
        ];
        $expectedPackagePartExtensions = ['xml'];
        $expectedLocalHeaderOrderRelationCounts = [
            'same-order' => 2,
            'local-before-central-order' => 0,
            'local-after-central-order' => 0,
            'missing-local-header-order' => 0,
        ];
        $expectedHash = hash('sha256', json_encode([
            'manifestVersion' => 'zip-package-manifest-v1',
            'packageSource' => $manifest['packageSource'],
            'packageByteLayout' => $manifest['packageByteLayout'],
            'packageByteLayoutVersion' => $manifest['packageByteLayoutVersion'],
            'packageByteLayoutIssueCount' => $manifest['packageByteLayoutIssueCount'],
            'packageByteLayoutIssues' => $manifest['packageByteLayoutIssues'],
            'packageByteLayoutIsLocalRegionContiguous' => $manifest['packageByteLayoutIsLocalRegionContiguous'],
            'packageByteLayoutIsArchiveLayoutContiguous' => $manifest['packageByteLayoutIsArchiveLayoutContiguous'],
            'packageByteLayoutUnclaimedLocalBytes' => $manifest['packageByteLayoutUnclaimedLocalBytes'],
            'packageByteLayoutInterEntryGapCount' => $manifest['packageByteLayoutInterEntryGapCount'],
            'packageByteLayoutUnaccountedArchiveBytes' => $manifest['packageByteLayoutUnaccountedArchiveBytes'],
            'packageByteLayoutTrailingByteCount' => $manifest['packageByteLayoutTrailingByteCount'],
            'archiveBytes' => $manifest['archiveBytes'],
            'archiveSha256' => $manifest['archiveSha256'],
            'centralDirectoryOffset' => $manifest['centralDirectoryOffset'],
            'centralDirectoryBytes' => $manifest['centralDirectoryBytes'],
            'centralDirectorySha256' => $manifest['centralDirectorySha256'],
            'endOfCentralDirectoryOffset' => $manifest['endOfCentralDirectoryOffset'],
            'endOfCentralDirectoryBytes' => $manifest['endOfCentralDirectoryBytes'],
            'endOfCentralDirectorySha256' => $manifest['endOfCentralDirectorySha256'],
            'endOfCentralDirectoryFixedFields' => $manifest['endOfCentralDirectoryFixedFields'],
            'endOfCentralDirectoryFixedFieldIssueCount' => $manifest['endOfCentralDirectoryFixedFieldIssueCount'],
            'hasEndOfCentralDirectoryFixedFieldIssues' => $manifest['hasEndOfCentralDirectoryFixedFieldIssues'],
            'endOfCentralDirectoryFixedFieldIssues' => $manifest['endOfCentralDirectoryFixedFieldIssues'],
            'packageCommentOffset' => $manifest['packageCommentOffset'],
            'packageCommentBytes' => $manifest['packageCommentBytes'],
            'packageCommentEnd' => $manifest['packageCommentEnd'],
            'packageCommentSha256' => $manifest['packageCommentSha256'],
            'packageCommentPreviewHex' => $manifest['packageCommentPreviewHex'],
            'packageCommentPreviewByteCount' => $manifest['packageCommentPreviewByteCount'],
            'packageCommentByteExposurePolicy' => $manifest['packageCommentByteExposurePolicy'],
            'canExposePackageCommentBytes' => $manifest['canExposePackageCommentBytes'],
            'hasPackageComment' => $manifest['hasPackageComment'],
            'hasCentralDirectorySignature' => $manifest['hasCentralDirectorySignature'],
            'centralDirectorySignatureOffset' => $manifest['centralDirectorySignatureOffset'],
            'centralDirectorySignatureDataOffset' => $manifest['centralDirectorySignatureDataOffset'],
            'centralDirectorySignatureEnd' => $manifest['centralDirectorySignatureEnd'],
            'centralDirectorySignatureBytes' => $manifest['centralDirectorySignatureBytes'],
            'centralDirectorySignatureRecordBytes' => $manifest['centralDirectorySignatureRecordBytes'],
            'centralDirectorySignaturePreviewHex' => $manifest['centralDirectorySignaturePreviewHex'],
            'centralDirectorySignaturePreviewByteCount' => $manifest['centralDirectorySignaturePreviewByteCount'],
            'centralDirectorySignatureSha256' => $manifest['centralDirectorySignatureSha256'],
            'centralDirectorySignatureLocation' => $manifest['centralDirectorySignatureLocation'],
            'centralDirectorySignatureVerification' => $manifest['centralDirectorySignatureVerification'],
            'centralDirectorySignatureByteExposurePolicy' => $manifest['centralDirectorySignatureByteExposurePolicy'],
            'centralDirectorySignatureCanExposeBytes' => $manifest['centralDirectorySignatureCanExposeBytes'],
            'centralDirectoryOrderNames' => ['word/document.xml', 'word/comments.xml'],
            'localHeaderOrderNames' => ['word/document.xml', 'word/comments.xml'],
            'localHeaderOrderRelationCounts' => $expectedLocalHeaderOrderRelationCounts,
            'localHeaderOrderMatchCount' => 2,
            'localHeaderOrderDisplacementEntryCount' => 0,
            'maxLocalHeaderOrderDisplacement' => 0,
            'localHeaderOrderDisplacementEntries' => [],
            'localHeaderBytes' => $manifest['localHeaderBytes'],
            'localHeaderFixedHeaderBytes' => $manifest['localHeaderFixedHeaderBytes'],
            'localHeaderFixedFieldEntryCount' => $manifest['localHeaderFixedFieldEntryCount'],
            'localHeaderFixedFieldIssueEntryCount' => $manifest['localHeaderFixedFieldIssueEntryCount'],
            'hasLocalHeaderFixedFieldIssues' => $manifest['hasLocalHeaderFixedFieldIssues'],
            'localHeaderFixedFieldIssues' => $manifest['localHeaderFixedFieldIssues'],
            'localHeaderFixedFieldEntries' => $manifest['localHeaderFixedFieldEntries'],
            'localHeaderFixedFieldIssueEntries' => $manifest['localHeaderFixedFieldIssueEntries'],
            'localHeaderVariableFieldBytes' => $manifest['localHeaderVariableFieldBytes'],
            'localHeaderRawNameBytes' => $manifest['localHeaderRawNameBytes'],
            'localHeaderExtraFieldBytes' => $manifest['localHeaderExtraFieldBytes'],
            'localHeaderReviewFieldBytes' => $manifest['localHeaderReviewFieldBytes'],
            'localExtraFieldEntryCount' => $manifest['localExtraFieldEntryCount'],
            'expansionRatio' => $manifest['expansionRatio'],
            'largestEntry' => $manifest['largestEntry'],
            'zeroByteEntryCount' => $manifest['zeroByteEntryCount'],
            'zeroByteFileCount' => $manifest['zeroByteFileCount'],
            'emptyDirectoryEntryCount' => $manifest['emptyDirectoryEntryCount'],
            'zeroByteEntries' => $manifest['zeroByteEntries'],
            'unknownExpansionRatioEntryCount' => $manifest['unknownExpansionRatioEntryCount'],
            'unknownExpansionRatioEntries' => $manifest['unknownExpansionRatioEntries'],
            'expansionRatioBucketSummaryCount' => $manifest['expansionRatioBucketSummaryCount'],
            'expansionRatioBuckets' => $manifest['expansionRatioBuckets'],
            'expansionRatioBucketSummaries' => $manifest['expansionRatioBucketSummaries'],
            'nameLengthBucketSummaryCount' => $manifest['nameLengthBucketSummaryCount'],
            'nameLengthBuckets' => $manifest['nameLengthBuckets'],
            'nameLengthBucketSummaries' => $manifest['nameLengthBucketSummaries'],
            'centralDirectoryRecordBytes' => $manifest['centralDirectoryRecordBytes'],
            'centralDirectoryFixedHeaderBytes' => $manifest['centralDirectoryFixedHeaderBytes'],
            'centralDirectoryVariableFieldBytes' => $manifest['centralDirectoryVariableFieldBytes'],
            'centralDirectoryRawNameBytes' => $manifest['centralDirectoryRawNameBytes'],
            'centralDirectoryExtraFieldBytes' => $manifest['centralDirectoryExtraFieldBytes'],
            'centralDirectoryRawCommentBytes' => $manifest['centralDirectoryRawCommentBytes'],
            'centralDirectoryReviewFieldBytes' => $manifest['centralDirectoryReviewFieldBytes'],
            'sourceRecordBytes' => $manifest['sourceRecordBytes'],
            'centralExtraFieldEntryCount' => $manifest['centralExtraFieldEntryCount'],
            'entryCommentCount' => $manifest['entryCommentCount'],
            'hasEntryComments' => $manifest['hasEntryComments'],
            'commentedEntryNames' => $manifest['commentedEntryNames'],
            'entryCommentSummaryCount' => $manifest['entryCommentSummaryCount'],
            'entryCommentSourceRecordBytes' => $manifest['entryCommentSourceRecordBytes'],
            'entryCommentSummaries' => $manifest['entryCommentSummaries'],
            'maxPathSegmentCount' => 2,
            'maxDirectoryDepth' => 1,
            'deepestEntryNames' => ['word/document.xml', 'word/comments.xml'],
            'caseInsensitiveNameCollisionGroupCount' => 0,
            'caseInsensitiveNameCollisionEntryCount' => 0,
            'caseInsensitiveNameCollisionGroups' => [],
            'caseInsensitiveNameCollisionEntries' => [],
            'compressionMethodSummaries' => $expectedCompressionMethodSummaries,
            'generalPurposeFlagSummaries' => $expectedGeneralPurposeFlagSummaries,
            'versionNeededToExtractSummaryCount' => 1,
            'versionNeededToExtractVersions' => [20],
            'minimumVersionNeededToExtractVersions' => [10, 20],
            'maxVersionNeededToExtract' => 20,
            'maxMinimumVersionNeededToExtract' => 20,
            'versionNeededToExtractSummaries' => $expectedVersionNeededToExtractSummaries,
            'creatorHostSystemSummaryCount' => 1,
            'knownCreatorHostSystemEntryCount' => 2,
            'unknownCreatorHostSystemEntryCount' => 0,
            'creatorVersionMeetsNeededEntryCount' => 2,
            'creatorVersionBelowNeededEntryCount' => 0,
            'creatorVersionEqualNeededEntryCount' => 2,
            'creatorVersionAboveNeededEntryCount' => 0,
            'creatorVersionBelowNeededKnownHostEntryCount' => 0,
            'creatorVersionBelowNeededUnknownHostEntryCount' => 0,
            'creatorHostSystemSummaries' => $expectedCreatorHostSystemSummaries,
            'creatorVersionComparisonCounts' => $expectedCreatorVersionComparisonCounts,
            'unknownCreatorHostSystemEntries' => [],
            'creatorVersionBelowNeededEntries' => [],
            'directoryRootSummaries' => $expectedDirectoryRootSummaries,
            'extensionlessPackagePartCount' => 0,
            'packagePartExtensions' => $expectedPackagePartExtensions,
            'packagePartExtensionSummaries' => $expectedPackagePartExtensionSummaries,
            'packagePartBaseNameSummaryCount' => $manifest['packagePartBaseNameSummaryCount'],
            'packagePartBaseNames' => $manifest['packagePartBaseNames'],
            'packagePartBaseNameSummaries' => $manifest['packagePartBaseNameSummaries'],
            'duplicatePackagePartBaseNameCount' => $manifest['duplicatePackagePartBaseNameCount'],
            'duplicatePackagePartBaseNames' => $manifest['duplicatePackagePartBaseNames'],
            'duplicatePackagePartBaseNameSummaries' => $manifest['duplicatePackagePartBaseNameSummaries'],
            'packagePartCaseFoldBaseNameSummaryCount' => $manifest['packagePartCaseFoldBaseNameSummaryCount'],
            'packagePartCaseFoldBaseNames' => $manifest['packagePartCaseFoldBaseNames'],
            'packagePartCaseFoldBaseNameSummaries' => $manifest['packagePartCaseFoldBaseNameSummaries'],
            'duplicatePackagePartCaseFoldBaseNameCount' => $manifest['duplicatePackagePartCaseFoldBaseNameCount'],
            'duplicatePackagePartCaseFoldBaseNames' => $manifest['duplicatePackagePartCaseFoldBaseNames'],
            'duplicatePackagePartCaseFoldBaseNameSummaries' => $manifest['duplicatePackagePartCaseFoldBaseNameSummaries'],
            'packagePartBaseNameStemSummaryCount' => $manifest['packagePartBaseNameStemSummaryCount'],
            'packagePartBaseNameStems' => $manifest['packagePartBaseNameStems'],
            'packagePartBaseNameStemSummaries' => $manifest['packagePartBaseNameStemSummaries'],
            'duplicatePackagePartBaseNameStemCount' => $manifest['duplicatePackagePartBaseNameStemCount'],
            'duplicatePackagePartBaseNameStems' => $manifest['duplicatePackagePartBaseNameStems'],
            'duplicatePackagePartBaseNameStemSummaries' => $manifest['duplicatePackagePartBaseNameStemSummaries'],
            'packagePartCaseFoldBaseNameStemSummaryCount' => $manifest['packagePartCaseFoldBaseNameStemSummaryCount'],
            'packagePartCaseFoldBaseNameStems' => $manifest['packagePartCaseFoldBaseNameStems'],
            'packagePartCaseFoldBaseNameStemSummaries' => $manifest['packagePartCaseFoldBaseNameStemSummaries'],
            'duplicatePackagePartCaseFoldBaseNameStemCount' => $manifest['duplicatePackagePartCaseFoldBaseNameStemCount'],
            'duplicatePackagePartCaseFoldBaseNameStems' => $manifest['duplicatePackagePartCaseFoldBaseNameStems'],
            'duplicatePackagePartCaseFoldBaseNameStemSummaries' => $manifest['duplicatePackagePartCaseFoldBaseNameStemSummaries'],
            'pathSegmentSummaryCount' => $manifest['pathSegmentSummaryCount'],
            'pathSegmentOccurrenceCount' => $manifest['pathSegmentOccurrenceCount'],
            'pathSegmentCounts' => $manifest['pathSegmentCounts'],
            'pathSegmentEntryCounts' => $manifest['pathSegmentEntryCounts'],
            'pathSegmentSummaries' => $manifest['pathSegmentSummaries'],
            'caseFoldPathSegmentSummaryCount' => $manifest['caseFoldPathSegmentSummaryCount'],
            'caseFoldPathSegments' => $manifest['caseFoldPathSegments'],
            'caseFoldPathSegmentOccurrenceCount' => $manifest['caseFoldPathSegmentOccurrenceCount'],
            'caseFoldPathSegmentCounts' => $manifest['caseFoldPathSegmentCounts'],
            'caseFoldPathSegmentEntryCounts' => $manifest['caseFoldPathSegmentEntryCounts'],
            'caseFoldPathSegmentSummaries' => $manifest['caseFoldPathSegmentSummaries'],
            'pathSegmentPositionSummaryCount' => $manifest['pathSegmentPositionSummaryCount'],
            'pathSegmentPositionOccurrenceCount' => $manifest['pathSegmentPositionOccurrenceCount'],
            'pathSegmentPositionCounts' => $manifest['pathSegmentPositionCounts'],
            'pathSegmentPositionEntryCounts' => $manifest['pathSegmentPositionEntryCounts'],
            'pathSegmentPositionSummaries' => $manifest['pathSegmentPositionSummaries'],
            'entries' => $expectedManifestEntries,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $t->same(1, $manifest['dataDescriptorEntryCount']);
        $t->same(16, $manifest['dataDescriptorBytes']);
        $t->same($documentEntry['localRecordBytes'] + $commentsEntry['localRecordBytes'], $manifest['localRecordBytes']);
        $t->same(
            $documentEntry['sourceRecordBytes'] + $commentsEntry['sourceRecordBytes'],
            $manifest['sourceRecordBytes']
        );
        $t->same(2, $manifest['maxPathSegmentCount']);
        $t->same(1, $manifest['maxDirectoryDepth']);
        $t->same(['word/document.xml', 'word/comments.xml'], $manifest['deepestEntryNames']);
        $t->same(0, $manifest['caseInsensitiveNameCollisionGroupCount']);
        $t->same(0, $manifest['caseInsensitiveNameCollisionEntryCount']);
        $t->same(false, $manifest['hasCaseInsensitiveNameCollisions']);
        $t->same([], $manifest['caseInsensitiveNameCollisionGroups']);
        $t->same([], $manifest['caseInsensitiveNameCollisionEntries']);
        $t->same(0, $manifest['extensionlessPackagePartCount']);
        $t->same(false, $manifest['hasExtensionlessPackageParts']);
        $t->same(1, $manifest['packagePartExtensionSummaryCount']);
        $t->same($expectedPackagePartExtensions, $manifest['packagePartExtensions']);
        $t->same($expectedPackagePartExtensionSummaries, $manifest['packagePartExtensionSummaries']);
        $t->same(2, $manifest['pathSegmentPositionSummaryCount']);
        $t->same(4, $manifest['pathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 2, 'last' => 2], $manifest['pathSegmentPositionCounts']);
        $t->same(['first' => 2, 'last' => 2], $manifest['pathSegmentPositionEntryCounts']);
        $t->same(2, $manifest['generalPurposeFlagSummaryCount']);
        $t->same(2, $manifest['generalPurposeUtf8NameEntryCount']);
        $t->same(1, $manifest['generalPurposeDataDescriptorEntryCount']);
        $t->same(0, $manifest['generalPurposeDeflateOptionEntryCount']);
        $t->same($expectedGeneralPurposeFlagSummaries, $manifest['generalPurposeFlagSummaries']);
        $t->same(1, $manifest['versionNeededToExtractSummaryCount']);
        $t->same([20], $manifest['versionNeededToExtractVersions']);
        $t->same([10, 20], $manifest['minimumVersionNeededToExtractVersions']);
        $t->same(20, $manifest['maxVersionNeededToExtract']);
        $t->same(20, $manifest['maxMinimumVersionNeededToExtract']);
        $t->same(false, $manifest['hasMultipleVersionNeededToExtractVersions']);
        $t->same($expectedVersionNeededToExtractSummaries, $manifest['versionNeededToExtractSummaries']);
        $t->same(1, $manifest['creatorHostSystemSummaryCount']);
        $t->same(2, $manifest['knownCreatorHostSystemEntryCount']);
        $t->same(0, $manifest['unknownCreatorHostSystemEntryCount']);
        $t->same(2, $manifest['creatorVersionMeetsNeededEntryCount']);
        $t->same(0, $manifest['creatorVersionBelowNeededEntryCount']);
        $t->same(2, $manifest['creatorVersionEqualNeededEntryCount']);
        $t->same(0, $manifest['creatorVersionAboveNeededEntryCount']);
        $t->same(0, $manifest['creatorVersionBelowNeededKnownHostEntryCount']);
        $t->same(0, $manifest['creatorVersionBelowNeededUnknownHostEntryCount']);
        $t->same(false, $manifest['hasUnknownCreatorHostSystems']);
        $t->same(false, $manifest['hasCreatorVersionBelowNeededEntries']);
        $t->same($expectedCreatorVersionComparisonCounts, $manifest['creatorVersionComparisonCounts']);
        $t->same($expectedCreatorHostSystemSummaries, $manifest['creatorHostSystemSummaries']);
        $t->same([], $manifest['unknownCreatorHostSystemEntries']);
        $t->same([], $manifest['creatorVersionBelowNeededEntries']);
        $t->same(['word/document.xml', 'word/comments.xml'], $manifest['centralDirectoryOrderNames']);
        $t->same(['word/document.xml', 'word/comments.xml'], $manifest['localHeaderOrderNames']);
        $t->same(true, $manifest['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same($expectedLocalHeaderOrderRelationCounts, $manifest['localHeaderOrderRelationCounts']);
        $t->same(2, $manifest['localHeaderOrderMatchCount']);
        $t->same(0, $manifest['localHeaderOrderDisplacementEntryCount']);
        $t->same(false, $manifest['hasLocalHeaderOrderDisplacements']);
        $t->same(0, $manifest['maxLocalHeaderOrderDisplacement']);
        $t->same([], $manifest['localHeaderOrderDisplacementEntries']);
        $t->same($expectedHash, $manifest['manifestSha256']);
        $t->same($expectedManifestEntries, array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'isDirectory' => $entry['isDirectory'],
                'caseFoldKey' => $entry['caseFoldKey'],
                'caseInsensitiveEquivalentEntryNames' => $entry['caseInsensitiveEquivalentEntryNames'],
                'hasCaseInsensitiveNameCollision' => $entry['hasCaseInsensitiveNameCollision'],
                'caseInsensitiveNameCollisionIssues' => $entry['caseInsensitiveNameCollisionIssues'],
                'versionMadeBy' => $entry['versionMadeBy'],
                'madeByHostSystem' => $entry['madeByHostSystem'],
                'madeByHostSystemName' => $entry['madeByHostSystemName'],
                'madeByVersion' => $entry['madeByVersion'],
                'versionNeededToExtract' => $entry['versionNeededToExtract'],
                'localVersionNeededToExtract' => $entry['localVersionNeededToExtract'],
                'minimumVersionNeededToExtract' => $entry['minimumVersionNeededToExtract'],
                'localMinimumVersionNeededToExtract' => $entry['localMinimumVersionNeededToExtract'],
                'versionNeededToExtractMatchesLocalHeader' => $entry['versionNeededToExtractMatchesLocalHeader'],
                'versionNeededToExtractMeetsFeatureMinimum' => $entry['versionNeededToExtractMeetsFeatureMinimum'],
                'localVersionNeededToExtractMeetsFeatureMinimum' => $entry['localVersionNeededToExtractMeetsFeatureMinimum'],
                'versionNeededToExtractExceedsBoundedReader' => $entry['versionNeededToExtractExceedsBoundedReader'],
                'localVersionNeededToExtractExceedsBoundedReader' => $entry['localVersionNeededToExtractExceedsBoundedReader'],
                'creatorVersionMeetsNeeded' => $entry['creatorVersionMeetsNeeded'],
                'creatorVersionComparison' => $entry['creatorVersionComparison'],
                'creatorVersionDelta' => $entry['creatorVersionDelta'],
                'creatorHostSystemIsKnown' => $entry['creatorHostSystemIsKnown'],
                'creatorHostSystemIssues' => $entry['creatorHostSystemIssues'],
                'directoryRoot' => $entry['directoryRoot'],
                'entryNameBytes' => $entry['entryNameBytes'],
                'entryNameLengthBucket' => $entry['entryNameLengthBucket'],
                'pathSegments' => $entry['pathSegments'],
                'pathSegmentPositionReviews' => $entry['pathSegmentPositionReviews'],
                'pathSegmentCount' => $entry['pathSegmentCount'],
                'directoryDepth' => $entry['directoryDepth'],
                'packagePartBaseName' => $entry['packagePartBaseName'],
                'packagePartCaseFoldBaseName' => $entry['packagePartCaseFoldBaseName'],
                'packagePartBaseNameStem' => $entry['packagePartBaseNameStem'],
                'packagePartCaseFoldBaseNameStem' => $entry['packagePartCaseFoldBaseNameStem'],
                'packagePartExtension' => $entry['packagePartExtension'],
                'packagePartExtensionKey' => $entry['packagePartExtensionKey'],
                'extensionlessPackagePart' => $entry['extensionlessPackagePart'],
                'centralDirectoryIndex' => $entry['centralDirectoryIndex'],
                'localHeaderOrder' => $entry['localHeaderOrder'],
                'localHeaderOrderDelta' => $entry['localHeaderOrderDelta'],
                'localHeaderOrderDisplacement' => $entry['localHeaderOrderDisplacement'],
                'localHeaderOrderRelation' => $entry['localHeaderOrderRelation'],
                'localHeaderOrderMatchesCentralDirectoryOrder' => $entry['localHeaderOrderMatchesCentralDirectoryOrder'],
                'localHeaderNameAtCentralDirectoryIndex' => $entry['localHeaderNameAtCentralDirectoryIndex'],
                'centralDirectoryNameAtLocalHeaderOrder' => $entry['centralDirectoryNameAtLocalHeaderOrder'],
                'compressionMethod' => $entry['compressionMethod'],
                'crc32Hex' => $entry['crc32Hex'],
                'compressedSize' => $entry['compressedSize'],
                'uncompressedSize' => $entry['uncompressedSize'],
                'expansionRatio' => $entry['expansionRatio'],
                'localHeaderSha256' => $entry['localHeaderSha256'],
                'localHeaderFixedHeaderBytes' => $entry['localHeaderFixedHeaderBytes'],
                'localHeaderVariableFieldBytes' => $entry['localHeaderVariableFieldBytes'],
                'localHeaderVariableFieldSha256' => $entry['localHeaderVariableFieldSha256'],
                'localHeaderRawNameBytes' => $entry['localHeaderRawNameBytes'],
                'localHeaderRawNameSha256' => $entry['localHeaderRawNameSha256'],
                'localHeaderExtraFieldBytes' => $entry['localHeaderExtraFieldBytes'],
                'localHeaderExtraFieldSha256' => $entry['localHeaderExtraFieldSha256'],
                'localHeaderReviewFieldBytes' => $entry['localHeaderReviewFieldBytes'],
                'localRecordBytes' => $entry['localRecordBytes'],
                'localRecordSha256' => $entry['localRecordSha256'],
                'compressedDataSha256' => $entry['compressedDataSha256'],
                'usesDataDescriptor' => $entry['usesDataDescriptor'],
                'dataDescriptorBytes' => $entry['dataDescriptorBytes'],
                'dataDescriptorSha256' => $entry['dataDescriptorSha256'],
                'centralDirectoryRecordBytes' => $entry['centralDirectoryRecordBytes'],
                'centralDirectoryRecordSha256' => $entry['centralDirectoryRecordSha256'],
                'centralDirectoryFixedHeaderBytes' => $entry['centralDirectoryFixedHeaderBytes'],
                'centralDirectoryVariableFieldBytes' => $entry['centralDirectoryVariableFieldBytes'],
                'centralDirectoryVariableFieldSha256' => $entry['centralDirectoryVariableFieldSha256'],
                'centralDirectoryRawNameBytes' => $entry['centralDirectoryRawNameBytes'],
                'centralDirectoryRawNameSha256' => $entry['centralDirectoryRawNameSha256'],
                'centralDirectoryExtraFieldBytes' => $entry['centralDirectoryExtraFieldBytes'],
                'centralDirectoryExtraFieldSha256' => $entry['centralDirectoryExtraFieldSha256'],
                'centralDirectoryRawCommentBytes' => $entry['centralDirectoryRawCommentBytes'],
                'centralDirectoryRawCommentSha256' => $entry['centralDirectoryRawCommentSha256'],
                'centralDirectoryReviewFieldBytes' => $entry['centralDirectoryReviewFieldBytes'],
                'sourceRecordBytes' => $entry['sourceRecordBytes'],
            ],
            $manifest['entries']
        ));

        $t->same(false, $documentEntry['usesDataDescriptor']);
        $t->same(0, $documentEntry['dataDescriptorBytes']);
        $t->same(null, $documentEntry['dataDescriptorOffset']);
        $t->same(null, $documentEntry['dataDescriptorEnd']);
        $t->same(null, $documentEntry['dataDescriptorSha256']);
        $t->same($documentEntry['compressedDataEnd'], $documentEntry['localRecordEnd']);
        $t->same(
            hash('sha256', substr($zip, $documentEntry['localRecordOffset'], $documentEntry['localRecordBytes'])),
            $documentEntry['localRecordSha256']
        );

        $t->same(true, $commentsEntry['usesDataDescriptor']);
        $t->same($commentsEntry['compressedDataEnd'], $commentsEntry['dataDescriptorOffset']);
        $t->same(16, $commentsEntry['dataDescriptorBytes']);
        $t->same($commentsEntry['dataDescriptorOffset'] + 16, $commentsEntry['dataDescriptorEnd']);
        $t->same($commentsEntry['dataDescriptorEnd'], $commentsEntry['localRecordEnd']);
        $t->same(hash('sha256', $commentsCompressed), $commentsEntry['compressedDataSha256']);
        $t->same(
            hash('sha256', substr($zip, $commentsEntry['dataDescriptorOffset'], $commentsEntry['dataDescriptorBytes'])),
            $commentsEntry['dataDescriptorSha256']
        );
        $t->same(
            hash('sha256', substr($zip, $commentsEntry['localRecordOffset'], $commentsEntry['localRecordBytes'])),
            $commentsEntry['localRecordSha256']
        );
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },

    'rolls up zip package manifest extraction versions for package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>version needed manifest</w:p></w:body></w:document>';
        $storedBytes = "stored media\n";
        $streamedBytes = "stored descriptor media\n";
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'word/media/stored.bin',
                'data' => $storedBytes,
                'method' => 0,
                'versionNeededToExtract' => 10,
            ],
            [
                'name' => 'word/media/streamed.bin',
                'data' => $streamedBytes,
                'method' => 0,
                'descriptor' => true,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'versionNeededToExtract' => 10,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $raw = ZipPackage::rawStrictImportPreflight($zip, 4096, 100.0, 4096);
        $entriesByName = [];
        foreach ($manifest['entries'] as $entry) {
            $entriesByName[$entry['name']] = $entry;
        }
        $versionSummaries = [];
        foreach ($manifest['versionNeededToExtractSummaries'] as $summary) {
            $versionSummaries[$summary['versionNeededToExtract']] = $summary;
        }

        $t->same(2, $manifest['versionNeededToExtractSummaryCount']);
        $t->same([10, 20], $manifest['versionNeededToExtractVersions']);
        $t->same([10, 20], $manifest['minimumVersionNeededToExtractVersions']);
        $t->same(20, $manifest['maxVersionNeededToExtract']);
        $t->same(20, $manifest['maxMinimumVersionNeededToExtract']);
        $t->same(true, $manifest['hasMultipleVersionNeededToExtractVersions']);

        $t->same([
            'versionNeededToExtract' => 10,
            'entryCount' => 2,
            'fileEntryCount' => 1,
            'directoryEntryCount' => 1,
            'compressedBytes' => strlen($storedBytes),
            'uncompressedBytes' => strlen($storedBytes),
            'localRecordBytes' => $entriesByName['word/media/stored.bin']['localRecordBytes']
                + $entriesByName['word/media/']['localRecordBytes'],
            'sourceRecordBytes' => $entriesByName['word/media/stored.bin']['sourceRecordBytes']
                + $entriesByName['word/media/']['sourceRecordBytes'],
            'dataDescriptorEntryCount' => 0,
            'dataDescriptorBytes' => 0,
            'minimumVersionNeededToExtracts' => [10],
            'compressionMethodNames' => ['stored'],
            'entryNames' => ['word/media/stored.bin', 'word/media/'],
        ], $versionSummaries[10]);
        $t->same([
            'versionNeededToExtract' => 20,
            'entryCount' => 2,
            'fileEntryCount' => 2,
            'directoryEntryCount' => 0,
            'compressedBytes' => strlen(gzdeflate($documentXml)) + strlen($streamedBytes),
            'uncompressedBytes' => strlen($documentXml) + strlen($streamedBytes),
            'localRecordBytes' => $entriesByName['word/document.xml']['localRecordBytes']
                + $entriesByName['word/media/streamed.bin']['localRecordBytes'],
            'sourceRecordBytes' => $entriesByName['word/document.xml']['sourceRecordBytes']
                + $entriesByName['word/media/streamed.bin']['sourceRecordBytes'],
            'dataDescriptorEntryCount' => 1,
            'dataDescriptorBytes' => $entriesByName['word/media/streamed.bin']['dataDescriptorBytes'],
            'minimumVersionNeededToExtracts' => [20],
            'compressionMethodNames' => ['deflated', 'stored'],
            'entryNames' => ['word/document.xml', 'word/media/streamed.bin'],
        ], $versionSummaries[20]);

        $t->same(10, $entriesByName['word/media/stored.bin']['versionNeededToExtract']);
        $t->same(10, $entriesByName['word/media/stored.bin']['localVersionNeededToExtract']);
        $t->same(10, $entriesByName['word/media/stored.bin']['minimumVersionNeededToExtract']);
        $t->same(true, $entriesByName['word/media/stored.bin']['versionNeededToExtractMatchesLocalHeader']);
        $t->same(true, $entriesByName['word/media/stored.bin']['versionNeededToExtractMeetsFeatureMinimum']);
        $t->same(false, $entriesByName['word/media/stored.bin']['versionNeededToExtractExceedsBoundedReader']);
        $t->same(20, $entriesByName['word/media/streamed.bin']['minimumVersionNeededToExtract']);
        $t->same(20, $entriesByName['word/media/streamed.bin']['localMinimumVersionNeededToExtract']);
        $t->same(true, $entriesByName['word/media/streamed.bin']['usesDataDescriptor']);
        $t->same(true, $entriesByName['word/media/streamed.bin']['localVersionNeededToExtractMeetsFeatureMinimum']);
        $t->same(20, $entriesByName['word/document.xml']['minimumVersionNeededToExtract']);
        $t->same(10, $entriesByName['word/media/']['minimumVersionNeededToExtract']);
        $t->same($manifest, $raw['packageManifest']);
    },

    'preflights zip package manifest source record hashes for package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>manifest source records</w:p></w:body></w:document>';
        $commentsXml = '<w:comments><w:comment>manifest source sidecar</w:comment></w:comments>';
        $commentsExtra = pack('vv', 0x5455, 0);
        $commentsComment = 'central manifest review';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'localExtra' => $commentsExtra,
                'centralExtra' => $commentsExtra,
                'comment' => $commentsComment,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $manifest = $package->packageManifestPreflight();
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $documentEntry = $manifest['entries'][0];
        $commentsEntry = $manifest['entries'][1];
        $documentCentralDirectoryRecordBytes = $documentEntry['centralDirectoryRecordEnd']
            - $documentEntry['centralDirectoryRecordOffset'];
        $commentsCentralDirectoryRecordBytes = $commentsEntry['centralDirectoryRecordEnd']
            - $commentsEntry['centralDirectoryRecordOffset'];

        $t->same(hash('sha256', substr($zip, $documentEntry['localHeaderOffset'], $documentEntry['localHeaderLength'])), $documentEntry['localHeaderSha256']);
        $t->same(hash('sha256', substr($zip, $documentEntry['centralDirectoryRecordOffset'], $documentCentralDirectoryRecordBytes)), $documentEntry['centralDirectoryRecordSha256']);
        $t->same(hash('sha256', substr($zip, $commentsEntry['localHeaderOffset'], $commentsEntry['localHeaderLength'])), $commentsEntry['localHeaderSha256']);
        $t->same(hash('sha256', substr($zip, $commentsEntry['centralDirectoryRecordOffset'], $commentsCentralDirectoryRecordBytes)), $commentsEntry['centralDirectoryRecordSha256']);
        $t->same(30 + strlen('word/document.xml') + 30 + strlen('word/comments.xml') + strlen($commentsExtra), $manifest['localHeaderBytes']);
        $t->same(60, $manifest['localHeaderFixedHeaderBytes']);
        $t->same(strlen('word/document.xml') + strlen('word/comments.xml') + strlen($commentsExtra), $manifest['localHeaderVariableFieldBytes']);
        $t->same(strlen('word/document.xml') + strlen('word/comments.xml'), $manifest['localHeaderRawNameBytes']);
        $t->same(strlen($commentsExtra), $manifest['localHeaderExtraFieldBytes']);
        $t->same(strlen($commentsExtra), $manifest['localHeaderReviewFieldBytes']);
        $t->same(1, $manifest['localExtraFieldEntryCount']);
        $t->same(true, $manifest['hasLocalHeaderReviewFields']);
        $t->same(30, $documentEntry['localHeaderFixedHeaderBytes']);
        $t->same(strlen('word/document.xml'), $documentEntry['localHeaderVariableFieldBytes']);
        $t->same(
            hash(
                'sha256',
                substr(
                    $zip,
                    $documentEntry['localHeaderVariableFieldOffset'],
                    $documentEntry['localHeaderVariableFieldBytes']
                )
            ),
            $documentEntry['localHeaderVariableFieldSha256']
        );
        $t->same($documentEntry['localHeaderVariableFieldOffset'], $documentEntry['localHeaderRawNameOffset']);
        $t->same(strlen('word/document.xml'), $documentEntry['localHeaderRawNameBytes']);
        $t->same(hash('sha256', 'word/document.xml'), $documentEntry['localHeaderRawNameSha256']);
        $t->same($documentEntry['localHeaderRawNameOffset'] + strlen('word/document.xml'), $documentEntry['localHeaderExtraFieldOffset']);
        $t->same(0, $documentEntry['localHeaderExtraFieldBytes']);
        $t->same(hash('sha256', ''), $documentEntry['localHeaderExtraFieldSha256']);
        $t->same(0, $documentEntry['localHeaderReviewFieldBytes']);
        $t->same(30 + strlen('word/comments.xml') + strlen($commentsExtra), $commentsEntry['localHeaderLength']);
        $t->same(30, $commentsEntry['localHeaderFixedHeaderBytes']);
        $t->same(strlen('word/comments.xml') + strlen($commentsExtra), $commentsEntry['localHeaderVariableFieldBytes']);
        $t->same(
            hash(
                'sha256',
                substr(
                    $zip,
                    $commentsEntry['localHeaderVariableFieldOffset'],
                    $commentsEntry['localHeaderVariableFieldBytes']
                )
            ),
            $commentsEntry['localHeaderVariableFieldSha256']
        );
        $t->same($commentsEntry['localHeaderVariableFieldOffset'], $commentsEntry['localHeaderRawNameOffset']);
        $t->same(strlen('word/comments.xml'), $commentsEntry['localHeaderRawNameBytes']);
        $t->same(hash('sha256', 'word/comments.xml'), $commentsEntry['localHeaderRawNameSha256']);
        $t->same($commentsEntry['localHeaderRawNameOffset'] + strlen('word/comments.xml'), $commentsEntry['localHeaderExtraFieldOffset']);
        $t->same(strlen($commentsExtra), $commentsEntry['localHeaderExtraFieldBytes']);
        $t->same(hash('sha256', $commentsExtra), $commentsEntry['localHeaderExtraFieldSha256']);
        $t->same(strlen($commentsExtra), $commentsEntry['localHeaderReviewFieldBytes']);
        $t->same(46 + strlen('word/comments.xml') + strlen($commentsExtra) + strlen($commentsComment), $commentsCentralDirectoryRecordBytes);
        $t->same($documentEntry['centralDirectoryRecordOffset'] + 46, $documentEntry['centralDirectoryVariableFieldOffset']);
        $t->same(strlen('word/document.xml'), $documentEntry['centralDirectoryVariableFieldBytes']);
        $t->same(hash('sha256', 'word/document.xml'), $documentEntry['centralDirectoryVariableFieldSha256']);
        $t->same($documentEntry['centralDirectoryVariableFieldOffset'], $documentEntry['centralDirectoryRawNameOffset']);
        $t->same(hash('sha256', 'word/document.xml'), $documentEntry['centralDirectoryRawNameSha256']);
        $t->same($documentEntry['centralDirectoryRawNameOffset'] + strlen('word/document.xml'), $documentEntry['centralDirectoryExtraFieldOffset']);
        $t->same(hash('sha256', ''), $documentEntry['centralDirectoryExtraFieldSha256']);
        $t->same($documentEntry['centralDirectoryExtraFieldOffset'], $documentEntry['centralDirectoryRawCommentOffset']);
        $t->same(hash('sha256', ''), $documentEntry['centralDirectoryRawCommentSha256']);
        $t->same($commentsEntry['centralDirectoryRecordOffset'] + 46, $commentsEntry['centralDirectoryVariableFieldOffset']);
        $t->same(
            hash(
                'sha256',
                substr(
                    $zip,
                    $commentsEntry['centralDirectoryVariableFieldOffset'],
                    $commentsEntry['centralDirectoryVariableFieldBytes']
                )
            ),
            $commentsEntry['centralDirectoryVariableFieldSha256']
        );
        $t->same($commentsEntry['centralDirectoryVariableFieldOffset'], $commentsEntry['centralDirectoryRawNameOffset']);
        $t->same(hash('sha256', 'word/comments.xml'), $commentsEntry['centralDirectoryRawNameSha256']);
        $t->same($commentsEntry['centralDirectoryRawNameOffset'] + strlen('word/comments.xml'), $commentsEntry['centralDirectoryExtraFieldOffset']);
        $t->same(hash('sha256', $commentsExtra), $commentsEntry['centralDirectoryExtraFieldSha256']);
        $t->same($commentsEntry['centralDirectoryExtraFieldOffset'] + strlen($commentsExtra), $commentsEntry['centralDirectoryRawCommentOffset']);
        $t->same(hash('sha256', $commentsComment), $commentsEntry['centralDirectoryRawCommentSha256']);
        $t->same(hash('sha256', gzdeflate($commentsXml)), $commentsEntry['compressedDataSha256']);
        $t->same($manifest, $raw['packageManifest']);
        $t->same($manifest, $raw['strictImport']['packageManifest']);
    },

    'preflights zip local header spans for stored and streamed package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $mimetype = 'application/epub+zip';
        $documentXml = '<w:document><w:p>local header span inventory</w:p></w:document>';
        $commentsXml = '<w:comments><w:comment>descriptor span</w:comment></w:comments>';
        $localExtra = pack('vva*', 0xcafe, strlen('local-review'), 'local-review');
        $zip = $buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => $mimetype,
                'method' => 0,
                'centralIndex' => 2,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'localExtra' => $localExtra,
                'centralExtra' => $localExtra,
                'centralIndex' => 0,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
                'centralIndex' => 1,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $summary = $package->localHeaderPreflight();
        $entries = $summary['entries'];

        $t->same(3, $summary['entryCount']);
        $t->same('mimetype', $summary['firstLocalEntryName']);
        $t->same('mimetype', $entries[0]['name']);
        $t->same('word/document.xml', $entries[1]['name']);
        $t->same('word/comments.xml', $entries[2]['name']);
        $t->same(0, $entries[0]['localHeaderOffset']);
        $t->same(30 + strlen('mimetype'), $entries[0]['localHeaderLength']);
        $t->same(0, $entries[0]['localFixedHeaderOffset']);
        $t->same(30, $entries[0]['localFixedHeaderLength']);
        $t->same(30, $entries[0]['localVariableFieldsOffset']);
        $t->same(strlen('mimetype'), $entries[0]['localVariableFieldsLength']);
        $t->same(30, $entries[0]['localNameOffset']);
        $t->same(strlen('mimetype'), $entries[0]['localNameLength']);
        $t->same(30 + strlen('mimetype'), $entries[0]['localExtraFieldOffset']);
        $t->same(0, $entries[0]['localExtraFieldLength']);
        $t->same($entries[0]['localHeaderLength'], $entries[0]['dataStart']);
        $t->same(strlen($mimetype), $entries[0]['compressedSize']);
        $t->same($entries[0]['dataStart'] + strlen($mimetype), $entries[0]['compressedDataEnd']);
        $t->same(false, $entries[0]['usesDataDescriptor']);
        $t->same(null, $entries[0]['descriptorOffset']);
        $t->same(null, $entries[0]['descriptorLength']);
        $t->same($entries[0]['compressedDataEnd'], $entries[0]['recordEnd']);
        $t->same($entries[1]['localHeaderOffset'], $entries[0]['nextOffset']);
        $t->same(true, $entries[0]['isContiguousWithNext']);
        $t->same(0, $entries[0]['compressionMethod']);
        $t->same(0x0800, $entries[0]['generalPurposeFlags']);
        $t->same(null, $entries[0]['hasZeroLocalHeaderPlaceholders']);
        $t->same(false, $entries[0]['hasLocalExtraFields']);

        $t->same(8, $entries[1]['compressionMethod']);
        $t->same(0x0800, $entries[1]['generalPurposeFlags']);
        $t->same($entries[1]['localHeaderOffset'], $entries[1]['localFixedHeaderOffset']);
        $t->same(30, $entries[1]['localFixedHeaderLength']);
        $t->same($entries[1]['localHeaderOffset'] + 30, $entries[1]['localVariableFieldsOffset']);
        $t->same(strlen('word/document.xml') + strlen($localExtra), $entries[1]['localVariableFieldsLength']);
        $t->same($entries[1]['localVariableFieldsOffset'], $entries[1]['localNameOffset']);
        $t->same(strlen('word/document.xml'), $entries[1]['localNameLength']);
        $t->same($entries[1]['localNameOffset'] + strlen('word/document.xml'), $entries[1]['localExtraFieldOffset']);
        $t->same(strlen($localExtra), $entries[1]['localExtraFieldLength']);
        $t->same($entries[1]['localExtraFieldOffset'] + strlen($localExtra), $entries[1]['dataStart']);
        $t->same(true, $entries[1]['hasLocalExtraFields']);
        $t->same(strlen(gzdeflate($documentXml)), $entries[1]['compressedSize']);
        $t->same(false, $entries[1]['usesDataDescriptor']);
        $t->same($entries[2]['localHeaderOffset'], $entries[1]['nextOffset']);
        $t->same(true, $entries[1]['isContiguousWithNext']);
        $t->same($entries[1]['dataStart'] + $entries[1]['compressedSize'], $entries[1]['recordEnd']);

        $t->same(8, $entries[2]['compressionMethod']);
        $t->same(0x0808, $entries[2]['generalPurposeFlags']);
        $t->same(0, $entries[2]['localHeaderCrc32']);
        $t->same(0, $entries[2]['localHeaderCompressedSize']);
        $t->same(0, $entries[2]['localHeaderUncompressedSize']);
        $t->same($entries[2]['localHeaderOffset'] + 30, $entries[2]['localVariableFieldsOffset']);
        $t->same(strlen('word/comments.xml'), $entries[2]['localVariableFieldsLength']);
        $t->same($entries[2]['localVariableFieldsOffset'] + strlen('word/comments.xml'), $entries[2]['localExtraFieldOffset']);
        $t->same(0, $entries[2]['localExtraFieldLength']);
        $t->same(false, $entries[2]['hasLocalExtraFields']);
        $t->same(true, $entries[2]['usesDataDescriptor']);
        $t->same(true, $entries[2]['hasZeroLocalHeaderPlaceholders']);
        $t->same($entries[2]['compressedDataEnd'], $entries[2]['descriptorOffset']);
        $t->same(16, $entries[2]['descriptorLength']);
        $t->same($entries[2]['compressedDataEnd'] + 16, $entries[2]['recordEnd']);
        $t->same($summary['centralDirectoryOffset'], $entries[2]['nextOffset']);
        $t->same(true, $entries[2]['isContiguousWithNext']);
        $t->same(3 * 30, $summary['localFixedHeaderBytes']);
        $t->same(strlen('mimetype') + strlen('word/document.xml') + strlen('word/comments.xml'), $summary['localNameBytes']);
        $t->same(strlen($localExtra), $summary['localExtraFieldBytes']);
        $t->same($summary['localNameBytes'] + strlen($localExtra), $summary['localVariableFieldBytes']);
        $t->same($summary['localFixedHeaderBytes'] + $summary['localVariableFieldBytes'], $summary['localHeaderBytes']);
        $t->same(1, $summary['localExtraFieldEntryCount']);
        $t->same($summary, $package->strictImportPreflight(2048, 100.0, 2048)['localHeaders']);
        $t->same($summary, ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048)['strictImport']['localHeaders']);
        $t->same($mimetype, $package->read('/mimetype'));
        $t->same($documentXml, $package->read('/word/document.xml'));
        $t->same($commentsXml, $package->read('/word/comments.xml'));
    },

    'summarizes zip local header extra field records for package review' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentReviewExtra = pack('vva*', 0xcafe, strlen('document-review'), 'document-review');
        $documentAuditExtra = pack('vva*', 0xbeef, strlen('document-audit'), 'document-audit');
        $mediaExtra = pack('vva*', 0x1234, strlen('media-review'), 'media-review');
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>local extra field review</w:p></w:document>',
                'method' => 8,
                'localExtra' => $documentReviewExtra . $documentAuditExtra,
                'centralExtra' => $documentReviewExtra . $documentAuditExtra,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => 'review media bytes',
                'method' => 0,
                'localExtra' => $mediaExtra,
                'centralExtra' => $mediaExtra,
            ],
            [
                'name' => 'word/notes.xml',
                'data' => '<w:notes/>',
                'method' => 0,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $summary = $package->localHeaderPreflight();
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $document = $summary['entries'][0];
        $documentRecords = $document['localExtraFieldRecords'];
        $media = $summary['entries'][1];
        $notes = $summary['entries'][2];

        $t->same(3, $summary['entryCount']);
        $t->same(2, $summary['localExtraFieldEntryCount']);
        $t->same(3, $summary['localExtraFieldRecordCount']);
        $t->same([0xcafe, 0xbeef, 0x1234], $summary['localExtraFieldRecordIds']);
        $t->same($summary, $rawStrict['strictImport']['localHeaders']);

        $t->same('word/document.xml', $document['name']);
        $t->same(strlen($documentReviewExtra . $documentAuditExtra), $document['localExtraFieldLength']);
        $t->same(2, $document['localExtraFieldRecordCount']);
        $t->same([0xcafe, 0xbeef], $document['localExtraFieldIds']);
        $t->same([], $document['localExtraFieldStructureIssues']);
        $t->same(2, count($documentRecords));

        $t->same(0xcafe, $documentRecords[0]['id']);
        $t->same('cafe', $documentRecords[0]['idHex']);
        $t->same($document['localExtraFieldOffset'], $documentRecords[0]['localExtraFieldRecordOffset']);
        $t->same($document['localExtraFieldOffset'] + 4, $documentRecords[0]['localExtraFieldDataOffset']);
        $t->same(strlen('document-review'), $documentRecords[0]['declaredDataLength']);
        $t->same($document['localExtraFieldOffset'] + strlen($documentReviewExtra), $documentRecords[0]['localExtraFieldRecordEnd']);

        $t->same(0xbeef, $documentRecords[1]['id']);
        $t->same('beef', $documentRecords[1]['idHex']);
        $t->same($document['localExtraFieldOffset'] + strlen($documentReviewExtra), $documentRecords[1]['localExtraFieldRecordOffset']);
        $t->same($document['localExtraFieldOffset'] + strlen($documentReviewExtra) + 4, $documentRecords[1]['localExtraFieldDataOffset']);
        $t->same(strlen('document-audit'), $documentRecords[1]['declaredDataLength']);
        $t->same($document['dataStart'], $documentRecords[1]['localExtraFieldRecordEnd']);

        $t->same('word/media/review.bin', $media['name']);
        $t->same(1, $media['localExtraFieldRecordCount']);
        $t->same([0x1234], $media['localExtraFieldIds']);
        $t->same('1234', $media['localExtraFieldRecords'][0]['idHex']);
        $t->same($media['dataStart'], $media['localExtraFieldRecords'][0]['localExtraFieldRecordEnd']);

        $t->same('word/notes.xml', $notes['name']);
        $t->same(0, $notes['localExtraFieldRecordCount']);
        $t->same([], $notes['localExtraFieldIds']);
        $t->same([], $notes['localExtraFieldRecords']);
    },

    'preflights raw zip local header span gaps before package instantiation' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $orphanName = 'word/media/orphan.bin';
        $orphanData = "unlisted local media bytes should stay blocked\n";
        $orphanBytes = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            0,
            0,
            0,
            $crc32($orphanData),
            strlen($orphanData),
            strlen($orphanData),
            strlen($orphanName),
            0
        );
        $orphanBytes .= $orphanName . $orphanData;
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw local span review</w:p></w:document>',
                'method' => 0,
                'localSlack' => $orphanBytes,
            ],
        ]);

        $spanPreflight = ZipPackage::localHeaderSpanPreflight($zip);
        $entry = $spanPreflight['entries'][0];
        $rawPreflight = ZipPackage::rawStrictImportPreflight($zip, 512, 20.0, 512);

        $t->same(1, $spanPreflight['entryCount']);
        $t->same(1, $spanPreflight['totalEntryCount']);
        $t->same(1, $spanPreflight['issueEntryCount']);
        $t->same(false, $spanPreflight['isSupportedByBoundedReader']);
        $t->same(['local-entry-unclaimed-bytes'], $spanPreflight['issues']);
        $t->same('word/document.xml', $entry['name']);
        $t->same(strlen($orphanBytes), $entry['unclaimedBytes']);
        $t->same(16, $entry['unclaimedBytesPreviewByteCount']);
        $t->same(bin2hex(substr($orphanBytes, 0, 16)), $entry['unclaimedBytesPreviewHex']);
        $t->same('local-file-header', $entry['unclaimedBytesSignature']);
        $t->same(true, $entry['unclaimedBytesStartWithLocalHeader']);
        $t->same(false, $entry['isContiguousWithNext']);
        $t->same(true, $entry['hasSpanIssue']);
        $t->same(['local-entry-unclaimed-bytes'], $entry['issues']);

        $t->same(false, $rawPreflight['isValid']);
        $t->same(false, $rawPreflight['canInstantiate']);
        $t->same(1, $rawPreflight['entryCount']);
        $t->same(1, $rawPreflight['localHeaderSpans']['issueEntryCount']);
        $t->same(['local-entry-unclaimed-bytes'], $rawPreflight['localHeaderSpans']['issues']);
        $t->same('local-file-header', $rawPreflight['localHeaderSpans']['issueEntries'][0]['unclaimedBytesSignature']);
        $t->same(bin2hex(substr($orphanBytes, 0, 16)), $rawPreflight['localHeaderSpans']['issueEntries'][0]['unclaimedBytesPreviewHex']);
        $t->same($spanPreflight, $rawPreflight['localHeaderSpans']);
        $t->same(null, $rawPreflight['strictImport']);
        $t->contains('local-header-span-issues', implode(',', $rawPreflight['diagnostics']));
        $t->contains('local-entry-unclaimed-bytes', implode(',', $rawPreflight['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawPreflight['diagnostics']));
    },

    'summarizes zip local header span byte buckets before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:p>span byte bucket document</w:p></w:document>';
        $commentsXml = '<w:comments><w:comment>span descriptor bucket</w:comment></w:comments>';
        $storedNote = "stored span bucket note\n";
        $hiddenSlack = 'hidden-local-span-slack';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
                'localSlack' => $hiddenSlack,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $storedNote,
                'method' => 0,
            ],
        ]);
        $documentCompressedBytes = strlen(gzdeflate($documentXml));
        $commentsCompressedBytes = strlen(gzdeflate($commentsXml));
        $localHeaderBytes = (30 + strlen('word/document.xml'))
            + (30 + strlen('word/comments.xml'))
            + (30 + strlen('word/media/review.txt'));
        $compressedDataBytes = $documentCompressedBytes + $commentsCompressedBytes + strlen($storedNote);
        $descriptorBytes = 16;

        $summary = ZipPackage::localHeaderSpanPreflight($zip);
        $rawPreflight = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $descriptorEntry = $summary['entries'][1];

        $t->same(3, $summary['entryCount']);
        $t->same(3, $summary['totalEntryCount']);
        $t->same(3, $summary['availableLocalHeaderEntryCount']);
        $t->same($localHeaderBytes, $summary['localHeaderBytes']);
        $t->same($compressedDataBytes, $summary['compressedDataBytes']);
        $t->same($descriptorBytes, $summary['dataDescriptorBytes']);
        $t->same($localHeaderBytes + $compressedDataBytes + $descriptorBytes, $summary['claimedRecordBytes']);
        $t->same(strlen($hiddenSlack), $summary['unclaimedBytes']);
        $t->same(1, $summary['unclaimedByteEntryCount']);
        $t->same(2, $summary['contiguousEntryCount']);
        $t->same(1, $summary['issueEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['local-entry-unclaimed-bytes', 'data-descriptor-length-mismatch'], $summary['issues']);

        $t->same('word/comments.xml', $descriptorEntry['name']);
        $t->same(true, $descriptorEntry['usesDataDescriptor']);
        $t->same($descriptorBytes, $descriptorEntry['descriptorLength']);
        $t->same(strlen($hiddenSlack), $descriptorEntry['unclaimedBytes']);
        $t->same(false, $descriptorEntry['isContiguousWithNext']);
        $t->same(['local-entry-unclaimed-bytes', 'data-descriptor-length-mismatch'], $descriptorEntry['issues']);

        $t->same(false, $rawPreflight['isValid']);
        $t->same(false, $rawPreflight['canInstantiate']);
        $t->same($summary, $rawPreflight['localHeaderSpans']);
        $t->contains('local-header-span-issues', implode(',', $rawPreflight['diagnostics']));
        $t->contains('local-entry-unclaimed-bytes', implode(',', $rawPreflight['diagnostics']));
        $t->contains('data-descriptor-length-mismatch', implode(',', $rawPreflight['diagnostics']));
        $t->contains('data-descriptor-entries', implode(',', $rawPreflight['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawPreflight['diagnostics']));
    },

    'preflights zip package byte layout before raw package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentName = 'word/document.xml';
        $mediaName = 'word/media/review.txt';
        $documentXml = '<w:document><w:body><w:p>' . str_repeat('layout accounting ', 10) . '</w:p></w:body></w:document>';
        $mediaBytes = "stored layout media bytes\n";
        $gapBytes = 'layout-gap-review';
        $packageComment = "layout-comment PK\x05\x06 marker";
        $zip = $buildZipPackage([
            [
                'name' => $documentName,
                'data' => $documentXml,
                'method' => 8,
                'localSlack' => $gapBytes,
            ],
            [
                'name' => $mediaName,
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ], $packageComment);
        $documentCompressedBytes = strlen(gzdeflate($documentXml));
        $documentHeaderBytes = 30 + strlen($documentName);
        $mediaHeaderBytes = 30 + strlen($mediaName);
        $localHeaderBytes = $documentHeaderBytes + $mediaHeaderBytes;
        $localPayloadBytes = $documentCompressedBytes + strlen($mediaBytes);
        $localRecordBytes = $localHeaderBytes + $localPayloadBytes;
        $localRegionBytes = $localRecordBytes + strlen($gapBytes);
        $centralDirectoryBytes = 46 + strlen($documentName) + 46 + strlen($mediaName);

        $summary = ZipPackage::packageByteLayoutPreflight($zip);
        $rawPreflight = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same(strlen($zip), $summary['archiveLength']);
        $t->same(hash('sha256', $zip), $summary['archiveSha256']);
        $t->same(0, $summary['prefixByteCount']);
        $t->same(false, $summary['hasPackagePrefix']);
        $t->same(null, $summary['prefixSha256']);
        $t->same(0, $summary['localRegionOffset']);
        $t->same($localRegionBytes, $summary['localRegionBytes']);
        $t->same(hash('sha256', substr($zip, 0, $localRegionBytes)), $summary['localRegionSha256']);
        $t->same(60, $summary['localHeaderFixedBytes']);
        $t->same(strlen($documentName) + strlen($mediaName), $summary['localHeaderVariableFieldBytes']);
        $t->same($localHeaderBytes, $summary['localHeaderBytes']);
        $t->same($localPayloadBytes, $summary['localPayloadBytes']);
        $t->same(0, $summary['dataDescriptorBytes']);
        $t->same($localRecordBytes, $summary['localEntryRecordBytes']);
        $t->same(strlen($gapBytes), $summary['unclaimedLocalBytes']);
        $t->same($localRegionBytes, $summary['localRegionAccountedBytes']);
        $t->same(0, $summary['localRegionUnaccountedBytes']);
        $t->same(1, $summary['interEntryGapCount']);
        $t->same($localRegionBytes, $summary['centralDirectoryOffset']);
        $t->same($centralDirectoryBytes, $summary['centralDirectoryBytes']);
        $t->same(
            hash('sha256', substr($zip, $localRegionBytes, $centralDirectoryBytes)),
            $summary['centralDirectorySha256']
        );
        $t->same($localRegionBytes + $centralDirectoryBytes, $summary['centralDirectoryEnd']);
        $t->same($summary['centralDirectoryEnd'], $summary['eocdOffset']);
        $t->same(null, $summary['centralDirectoryToEocdGapOffset']);
        $t->same(0, $summary['centralDirectoryToEocdGapBytes']);
        $t->same(null, $summary['centralDirectoryToEocdGapSignature']);
        $t->same('', $summary['centralDirectoryToEocdGapPreviewHex']);
        $t->same(0, $summary['centralDirectoryToEocdGapPreviewByteCount']);
        $t->same(null, $summary['centralDirectoryToEocdGapSha256']);
        $t->same(false, $summary['isCentralDirectoryToEocdGapExplainedBySignature']);
        $t->same(22, $summary['eocdFixedHeaderBytes']);
        $t->same(hash('sha256', substr($zip, $summary['eocdOffset'], 22)), $summary['eocdFixedHeaderSha256']);
        $t->same($summary['eocdOffset'] + 22, $summary['packageCommentOffset']);
        $t->same(strlen($packageComment), $summary['packageCommentBytes']);
        $t->same(strlen($zip), $summary['packageCommentEnd']);
        $t->same(bin2hex(substr($packageComment, 0, 16)), $summary['packageCommentPreviewHex']);
        $t->same(16, $summary['packageCommentPreviewByteCount']);
        $t->same(hash('sha256', $packageComment), $summary['packageCommentSha256']);
        $t->same(true, $summary['hasPackageComment']);
        $t->same(22 + strlen($packageComment), $summary['endOfCentralDirectoryBytes']);
        $t->same(
            hash('sha256', substr($zip, $summary['eocdOffset'], 22 + strlen($packageComment))),
            $summary['endOfCentralDirectorySha256']
        );
        $t->same(strlen($zip), $summary['declaredArchiveEndOffset']);
        $t->same(0, $summary['trailingByteCount']);
        $t->same(null, $summary['trailingBytesSha256']);
        $t->same(strlen($zip), $summary['accountedArchiveBytes']);
        $t->same(0, $summary['unaccountedArchiveBytes']);
        $t->same(false, $summary['isLocalRegionContiguous']);
        $t->same(false, $summary['isArchiveLayoutContiguous']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['local-entry-unclaimed-bytes'], $summary['issues']);

        $t->same($documentName, $summary['entries'][0]['name']);
        $t->same($documentHeaderBytes + $documentCompressedBytes, $summary['entries'][0]['localRecordBytes']);
        $t->same(strlen($gapBytes), $summary['entries'][0]['unclaimedBytes']);
        $t->same(bin2hex(substr($gapBytes, 0, 16)), $summary['entries'][0]['unclaimedBytesPreviewHex']);
        $t->same(false, $summary['entries'][0]['isContiguousWithNext']);
        $t->same(['local-entry-unclaimed-bytes'], $summary['entries'][0]['issues']);
        $t->same($mediaName, $summary['entries'][1]['name']);
        $t->same($mediaHeaderBytes + strlen($mediaBytes), $summary['entries'][1]['localRecordBytes']);
        $t->same(0, $summary['entries'][1]['unclaimedBytes']);
        $t->same(true, $summary['entries'][1]['isContiguousWithNext']);

        $t->same(false, $rawPreflight['isValid']);
        $t->same(false, $rawPreflight['canInstantiate']);
        $t->same($summary, $rawPreflight['packageByteLayout']);
        $t->contains('package-byte-layout-issues', implode(',', $rawPreflight['diagnostics']));
        $t->contains('local-entry-unclaimed-bytes', implode(',', $rawPreflight['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawPreflight['diagnostics']));

        $safeZip = $buildZipPackage([
            [
                'name' => $documentName,
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => $mediaName,
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ]);
        $safeSummary = ZipPackage::packageByteLayoutPreflight($safeZip);
        $safeRaw = ZipPackage::rawStrictImportPreflight($safeZip, 2048, 100.0, 2048);

        $t->same(true, $safeSummary['isSupportedByBoundedReader']);
        $t->same(true, $safeSummary['isArchiveLayoutContiguous']);
        $t->same(0, $safeSummary['unclaimedLocalBytes']);
        $t->same($safeSummary['eocdOffset'] + 22, $safeSummary['packageCommentOffset']);
        $t->same($safeSummary['packageCommentOffset'], $safeSummary['packageCommentEnd']);
        $t->same('', $safeSummary['packageCommentPreviewHex']);
        $t->same(0, $safeSummary['packageCommentPreviewByteCount']);
        $t->same(null, $safeSummary['packageCommentSha256']);
        $t->same(hash('sha256', substr($safeZip, $safeSummary['eocdOffset'], 22)), $safeSummary['endOfCentralDirectorySha256']);
        $t->same(false, $safeSummary['hasPackageComment']);
        $t->same([], $safeSummary['issues']);
        $t->same($safeSummary, $safeRaw['packageByteLayout']);
        $t->same($safeSummary, $safeRaw['strictImport']['packageByteLayout']);
        $t->same(true, $safeRaw['isValid']);
        $t->same(true, $safeRaw['canInstantiate']);
    },

    'preflights zip package byte layout package comment provenance before raw package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $packageComment = "package byte layout comment PK\x05\x06 reviewer";
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>package comment byte layout provenance</w:p></w:document>',
                'method' => 0,
            ],
        ], $packageComment);
        $expectedEocdOffset = strlen($zip) - strlen($packageComment) - 22;
        $commentSignatureOffset = strpos($zip, "PK\x05\x06", $expectedEocdOffset + 22);
        if ($commentSignatureOffset === false) {
            throw new RuntimeException('Fixture package comment EOCD-like signature not found');
        }

        $package = ZipPackage::fromString($zip);
        $summary = ZipPackage::packageByteLayoutPreflight($zip);
        $rawPreflight = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);

        $t->same($expectedEocdOffset, $summary['eocdOffset']);
        $t->same($expectedEocdOffset + 22, $summary['packageCommentOffset']);
        $t->same(strlen($packageComment), $summary['packageCommentBytes']);
        $t->same(strlen($zip), $summary['packageCommentEnd']);
        $t->same(bin2hex(substr($packageComment, 0, 16)), $summary['packageCommentPreviewHex']);
        $t->same(16, $summary['packageCommentPreviewByteCount']);
        $t->same(hash('sha256', $packageComment), $summary['packageCommentSha256']);
        $t->same(true, $summary['hasPackageComment']);
        $t->same(22 + strlen($packageComment), $summary['endOfCentralDirectoryBytes']);
        $t->same(
            hash('sha256', substr($zip, $summary['eocdOffset'], 22 + strlen($packageComment))),
            $summary['endOfCentralDirectorySha256']
        );
        $t->same(strlen($zip), $summary['declaredArchiveEndOffset']);
        $t->same(0, $summary['trailingByteCount']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);
        $t->same($summary['packageCommentOffset'] + strlen('package byte layout comment '), $commentSignatureOffset);
        $t->same(true, $summary['eocdOffset'] < $commentSignatureOffset);
        $t->same($packageComment, $package->packageComment());
        $t->same($summary, $rawPreflight['packageByteLayout']);
        $t->same($summary, $rawPreflight['strictImport']['packageByteLayout']);
        $t->same($summary, $strict['packageByteLayout']);
        $t->same(true, $rawPreflight['canInstantiate']);
    },

    'preflights hidden zip local span record signatures before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $reviewPayload = 'hidden archive-extra reviewer metadata';
        $hiddenRecord = "PK\x06\x08" . pack('V', strlen($reviewPayload)) . $reviewPayload;
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>hidden span signature review</w:p></w:document>',
                'method' => 0,
                'localSlack' => $hiddenRecord,
            ],
        ]);

        $summary = ZipPackage::localHeaderSpanPreflight($zip);
        $entry = $summary['issueEntries'][0];
        $rawPreflight = ZipPackage::rawStrictImportPreflight($zip, 512, 20.0, 512);

        $t->same(1, $summary['issueEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['local-entry-unclaimed-bytes'], $summary['issues']);
        $t->same(strlen($hiddenRecord), $entry['unclaimedBytes']);
        $t->same(16, $entry['unclaimedBytesPreviewByteCount']);
        $t->same(bin2hex(substr($hiddenRecord, 0, 16)), $entry['unclaimedBytesPreviewHex']);
        $t->same('archive-extra-data-record', $entry['unclaimedBytesSignature']);
        $t->same(false, $entry['unclaimedBytesStartWithLocalHeader']);
        $t->same($summary, $rawPreflight['localHeaderSpans']);
        $t->contains('local-header-span-issues', implode(',', $rawPreflight['diagnostics']));
        $t->contains('local-entry-unclaimed-bytes', implode(',', $rawPreflight['diagnostics']));
    },

    'preflights central directory local header offsets before package instantiation' => static function (TestRunner $t) use ($buildZipPackage, $rewriteFirstCentralLocalHeaderOffset): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>central offset review</w:p></w:document>',
                'method' => 0,
            ],
        ]);
        $archive = ZipPackage::endOfCentralDirectoryPreflight($zip);
        $insideCentralZip = $rewriteFirstCentralLocalHeaderOffset($zip, $archive['centralDirectoryOffset']);
        $beyondArchiveZip = $rewriteFirstCentralLocalHeaderOffset($zip, strlen($zip) + 32);

        $insideCentralSpanPreflight = ZipPackage::localHeaderSpanPreflight($insideCentralZip);
        $insideCentralEntry = $insideCentralSpanPreflight['issueEntries'][0];
        $insideCentralRawPreflight = ZipPackage::rawStrictImportPreflight($insideCentralZip, 512, 20.0, 512);
        $insideCentralRejected = false;
        try {
            ZipPackage::fromString($insideCentralZip);
        } catch (RuntimeException $exception) {
            $insideCentralRejected = true;
        }

        $t->same(true, $insideCentralRejected);
        $t->same(1, $insideCentralSpanPreflight['entryCount']);
        $t->same(1, $insideCentralSpanPreflight['issueEntryCount']);
        $t->same(false, $insideCentralSpanPreflight['isSupportedByBoundedReader']);
        $t->same([
            'local-header-prefix-bytes',
            'local-header-offset-inside-central-directory',
        ], $insideCentralSpanPreflight['issues']);
        $t->same('word/document.xml', $insideCentralEntry['name']);
        $t->same($archive['centralDirectoryOffset'], $insideCentralEntry['localHeaderOffset']);
        $t->same('inside-central-directory', $insideCentralEntry['localHeaderOffsetLocation']);
        $t->contains('inside the central directory', $insideCentralEntry['localHeaderOffsetError']);
        $t->same(false, $insideCentralEntry['localHeaderAvailable']);
        $t->same(null, $insideCentralEntry['localHeaderLength']);
        $t->same(null, $insideCentralEntry['dataStart']);
        $t->same(null, $insideCentralEntry['recordEnd']);
        $t->same(true, $insideCentralEntry['hasSpanIssue']);
        $t->same(['local-header-offset-inside-central-directory'], $insideCentralEntry['issues']);

        $t->same(false, $insideCentralRawPreflight['isValid']);
        $t->same(false, $insideCentralRawPreflight['canInstantiate']);
        $t->same($insideCentralSpanPreflight, $insideCentralRawPreflight['localHeaderSpans']);
        $t->contains('local-header-span-issues', implode(',', $insideCentralRawPreflight['diagnostics']));
        $t->contains('local-header-offset-inside-central-directory', implode(',', $insideCentralRawPreflight['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $insideCentralRawPreflight['diagnostics']));

        $beyondArchiveSpanPreflight = ZipPackage::localHeaderSpanPreflight($beyondArchiveZip);
        $beyondArchiveEntry = $beyondArchiveSpanPreflight['issueEntries'][0];
        $beyondArchiveRawPreflight = ZipPackage::rawStrictImportPreflight($beyondArchiveZip, 512, 20.0, 512);

        $t->same(1, $beyondArchiveSpanPreflight['issueEntryCount']);
        $t->same(false, $beyondArchiveSpanPreflight['isSupportedByBoundedReader']);
        $t->contains('local-header-offset-beyond-archive', implode(',', $beyondArchiveSpanPreflight['issues']));
        $t->same(strlen($zip) + 32, $beyondArchiveEntry['localHeaderOffset']);
        $t->same('beyond-archive', $beyondArchiveEntry['localHeaderOffsetLocation']);
        $t->contains('beyond archive length', $beyondArchiveEntry['localHeaderOffsetError']);
        $t->same(false, $beyondArchiveEntry['localHeaderAvailable']);
        $t->same(['local-header-offset-beyond-archive'], $beyondArchiveEntry['issues']);
        $t->same(false, $beyondArchiveRawPreflight['isValid']);
        $t->same(false, $beyondArchiveRawPreflight['canInstantiate']);
        $t->same($beyondArchiveSpanPreflight, $beyondArchiveRawPreflight['localHeaderSpans']);
        $t->contains('local-header-offset-beyond-archive', implode(',', $beyondArchiveRawPreflight['diagnostics']));
    },

    'preflights zip central directory order against local header order before package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $mimetype = 'application/vnd.oasis.opendocument.text';
        $contentXml = '<office:document-content><text:p>body</text:p></office:document-content>';
        $stylesXml = '<office:document-styles><style:style/></office:document-styles>';
        $zip = $buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => $mimetype,
                'method' => 0,
                'centralIndex' => 2,
            ],
            [
                'name' => 'content.xml',
                'data' => $contentXml,
                'method' => 8,
                'centralIndex' => 0,
            ],
            [
                'name' => 'styles.xml',
                'data' => $stylesXml,
                'method' => 8,
                'centralIndex' => 1,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $summary = $package->localHeaderOrderPreflight();
        $rawSummary = ZipPackage::centralDirectoryLocalHeaderOrderPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $strictSummary = $package->strictImportPreflight(2048, 100.0, 2048);
        $entries = $summary['entries'];

        $t->same(3, $summary['entryCount']);
        $t->same(['content.xml', 'styles.xml', 'mimetype'], $summary['centralDirectoryOrderNames']);
        $t->same(['mimetype', 'content.xml', 'styles.xml'], $summary['localHeaderOrderNames']);
        $t->same(true, $summary['hasCentralDirectoryOrderMismatch']);
        $t->same(3, $summary['mismatchedEntryCount']);
        $t->same('content.xml', $entries[0]['name']);
        $t->same(0, $entries[0]['centralDirectoryIndex']);
        $t->same($summary['centralDirectoryOffset'], $entries[0]['centralDirectoryRecordOffset']);
        $t->true($entries[0]['centralDirectoryRecordOffset'] < $entries[0]['centralDirectoryRecordEnd']);
        $t->same(1, $entries[0]['localHeaderOrder']);
        $t->same('mimetype', $entries[0]['localHeaderNameAtCentralDirectoryIndex']);
        $t->same('styles.xml', $entries[0]['centralDirectoryNameAtLocalHeaderOrder']);
        $t->same(false, $entries[0]['matchesCentralDirectoryOrder']);
        $t->same('styles.xml', $entries[1]['name']);
        $t->same($entries[0]['centralDirectoryRecordEnd'], $entries[1]['centralDirectoryRecordOffset']);
        $t->same(2, $entries[1]['localHeaderOrder']);
        $t->same('mimetype', $entries[2]['name']);
        $t->same($entries[1]['centralDirectoryRecordEnd'], $entries[2]['centralDirectoryRecordOffset']);
        $t->same(0, $entries[2]['localHeaderOrder']);
        $t->same($summary, $strictSummary['localHeaderOrder']);
        $t->same($summary, $rawSummary);
        $t->same($summary, $rawStrict['localHeaderOrder']);
        $t->same($summary, $rawStrict['strictImport']['localHeaderOrder']);
        $t->same(false, $strictSummary['isValid']);
        $t->same(['central-directory-local-header-order-mismatch'], $strictSummary['diagnostics']);
        $t->same(true, $rawStrict['canInstantiate']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(null, $rawStrict['instantiationError']);
        $t->contains('central-directory-local-header-order-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->same(['central-directory-local-header-order-mismatch'], $rawStrict['strictImport']['diagnostics']);
        $t->same($mimetype, $package->read('/mimetype'));

        $matchingPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => $mimetype,
                'method' => 0,
            ],
            [
                'name' => 'content.xml',
                'data' => $contentXml,
                'method' => 8,
            ],
        ]));
        $matchingSummary = $matchingPackage->localHeaderOrderPreflight();
        $matchingRaw = ZipPackage::rawStrictImportPreflight($matchingPackage->bytes(), 2048, 100.0, 2048);

        $t->same(2, $matchingSummary['entryCount']);
        $t->same(['mimetype', 'content.xml'], $matchingSummary['centralDirectoryOrderNames']);
        $t->same(['mimetype', 'content.xml'], $matchingSummary['localHeaderOrderNames']);
        $t->same(false, $matchingSummary['hasCentralDirectoryOrderMismatch']);
        $t->same(0, $matchingSummary['mismatchedEntryCount']);
        $t->same(true, $matchingSummary['entries'][0]['matchesCentralDirectoryOrder']);
        $t->same($matchingSummary['centralDirectoryOffset'], $matchingSummary['entries'][0]['centralDirectoryRecordOffset']);
        $t->same(0, $matchingSummary['entries'][0]['localHeaderOrder']);
        $t->same($matchingSummary, $matchingPackage->strictImportPreflight(2048, 100.0, 2048)['localHeaderOrder']);
        $t->same($matchingSummary, ZipPackage::centralDirectoryLocalHeaderOrderPreflight($matchingPackage->bytes()));
        $t->same($matchingSummary, $matchingRaw['localHeaderOrder']);
        $t->same(true, $matchingPackage->strictImportPreflight(2048, 100.0, 2048)['isValid']);
        $t->same(true, $matchingRaw['isValid']);
    },

    'preflights zip central and local header name provenance before entry exposure' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $legacyRawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $legacyZip = $buildZipPackage([
            [
                'name' => $legacyRawName,
                'data' => "legacy named image bytes\n",
                'method' => 0,
                'flags' => 0,
                'localExtra' => $buildUnicodeExtra(0x7075, $legacyRawName, $unicodeName),
                'centralExtra' => $buildUnicodeExtra(0x7075, $legacyRawName, $unicodeName),
            ],
        ]);

        $legacySummary = ZipPackage::localHeaderNamePreflight($legacyZip);
        $legacyEntry = $legacySummary['entries'][0];
        $t->same(1, $legacySummary['entryCount']);
        $t->same(0, $legacySummary['mismatchedEntryCount']);
        $t->same(true, $legacySummary['isSupportedByBoundedReader']);
        $t->same([], $legacySummary['issues']);
        $t->same($legacyRawName, $legacyEntry['centralRawName']);
        $t->same($legacyRawName, $legacyEntry['localRawName']);
        $t->same($unicodeName, $legacyEntry['centralName']);
        $t->same($unicodeName, $legacyEntry['localName']);
        $t->same('info-zip-unicode-path', $legacyEntry['centralNameEncoding']);
        $t->same('info-zip-unicode-path', $legacyEntry['localNameEncoding']);
        $t->same(true, $legacyEntry['rawNameMatchesCentral']);
        $t->same(true, $legacyEntry['decodedNameMatchesCentral']);
        $t->same(true, $legacyEntry['generalPurposeFlagsMatchCentral']);
        $t->same([], $legacyEntry['issues']);
        $t->same([$unicodeName], ZipPackage::fromString($legacyZip)->names());

        $mismatchedZip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/other.xml',
                'data' => '<w:document/>',
                'method' => 0,
            ],
        ]);

        $mismatchSummary = ZipPackage::localHeaderNamePreflight($mismatchedZip);
        $mismatchEntry = $mismatchSummary['mismatchedEntries'][0];
        $t->same(1, $mismatchSummary['entryCount']);
        $t->same(1, $mismatchSummary['mismatchedEntryCount']);
        $t->same(false, $mismatchSummary['isSupportedByBoundedReader']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $mismatchSummary['issues']);
        $t->same('word/document.xml', $mismatchEntry['centralName']);
        $t->same('word/other.xml', $mismatchEntry['localName']);
        $t->same('word/document.xml', $mismatchEntry['centralRawName']);
        $t->same('word/other.xml', $mismatchEntry['localRawName']);
        $t->same(false, $mismatchEntry['rawNameMatchesCentral']);
        $t->same(false, $mismatchEntry['decodedNameMatchesCentral']);
        $t->same(true, $mismatchEntry['generalPurposeFlagsMatchCentral']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $mismatchEntry['issues']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($mismatchedZip));
    },

    'preflights unsafe zip local header names before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/media/review.png',
                'localName' => 'word/../media/review.png',
                'data' => "safe central name hides an unsafe local header path\n",
                'method' => 0,
            ],
        ]);

        $summary = ZipPackage::localHeaderNamePreflight($zip);
        $entry = $summary['mismatchedEntries'][0];
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 512, 20.0, 512);

        $t->same(1, $summary['entryCount']);
        $t->same(1, $summary['mismatchedEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'local-header-unsafe-raw-name',
            'local-header-raw-name-parent-directory-segment',
            'local-header-unsafe-decoded-name',
            'local-header-decoded-name-parent-directory-segment',
            'local-header-name-mismatch',
            'local-header-decoded-name-mismatch',
        ], $summary['issues']);
        $t->same('word/media/review.png', $entry['centralName']);
        $t->same('word/../media/review.png', $entry['localName']);
        $t->same('word/../media/review.png', $entry['localRawName']);
        $t->same(false, $entry['localRawNameIsSafe']);
        $t->same(['parent-directory-segment'], $entry['localRawNameSafetyIssues']);
        $t->same(false, $entry['localDecodedNameIsSafe']);
        $t->same(['parent-directory-segment'], $entry['localDecodedNameSafetyIssues']);
        $t->same(false, $entry['rawNameMatchesCentral']);
        $t->same(false, $entry['decodedNameMatchesCentral']);
        $t->same(true, $entry['generalPurposeFlagsMatchCentral']);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(1, $rawStrict['entryCount']);
        $t->same($summary, $rawStrict['localHeaderNames']);
        $t->same(1, $rawStrict['localHeaderMetadata']['mismatchedEntryCount']);
        $t->same([], $rawStrict['preflightErrors']);
        $t->contains('local-header-name-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-header-unsafe-raw-name', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-header-raw-name-parent-directory-segment', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights zip local header metadata mismatches before entry exposure' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $safeDocumentXml = '<w:document><w:p>safe local metadata</w:p></w:document>';
        $safeDocumentCompressed = gzdeflate($safeDocumentXml);
        if ($safeDocumentCompressed === false) {
            throw new RuntimeException('Unable to deflate safe local metadata fixture');
        }
        $safeCommentsXml = '<w:comments><w:comment>safe descriptor metadata</w:comment></w:comments>';
        $safeZip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $safeDocumentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $safeCommentsXml,
                'method' => 8,
                'descriptor' => true,
            ],
        ]);
        $safeSummary = ZipPackage::localHeaderMetadataPreflight($safeZip);

        $t->same(2, $safeSummary['entryCount']);
        $t->same(2, $safeSummary['totalEntryCount']);
        $t->same(0, $safeSummary['mismatchedEntryCount']);
        $t->same(true, $safeSummary['isSupportedByBoundedReader']);
        $t->same([], $safeSummary['issues']);
        $t->same([], $safeSummary['mismatchedEntries']);
        $t->same('word/document.xml', $safeSummary['entries'][0]['centralName']);
        $t->same('word/document.xml', $safeSummary['entries'][0]['localName']);
        $t->same(20, $safeSummary['entries'][0]['centralVersionNeededToExtract']);
        $t->same(20, $safeSummary['entries'][0]['localVersionNeededToExtract']);
        $t->same(8, $safeSummary['entries'][0]['centralCompressionMethod']);
        $t->same(8, $safeSummary['entries'][0]['localCompressionMethod']);
        $t->same($crc32($safeDocumentXml), $safeSummary['entries'][0]['localCrc32']);
        $t->same(strlen($safeDocumentCompressed), $safeSummary['entries'][0]['localCompressedSize']);
        $t->same(strlen($safeDocumentXml), $safeSummary['entries'][0]['localUncompressedSize']);
        $t->same(false, $safeSummary['entries'][0]['usesDataDescriptor']);
        $t->same(null, $safeSummary['entries'][0]['hasZeroLocalHeaderPlaceholders']);
        $t->same(false, $safeSummary['entries'][0]['hasMetadataMismatch']);
        $t->same('word/comments.xml', $safeSummary['entries'][1]['centralName']);
        $t->same(true, $safeSummary['entries'][1]['usesDataDescriptor']);
        $t->same(0, $safeSummary['entries'][1]['localCrc32']);
        $t->same(0, $safeSummary['entries'][1]['localCompressedSize']);
        $t->same(0, $safeSummary['entries'][1]['localUncompressedSize']);
        $t->same(true, $safeSummary['entries'][1]['hasZeroLocalHeaderPlaceholders']);
        $t->same(false, $safeSummary['entries'][1]['hasMetadataMismatch']);
        $t->same($safeDocumentXml, ZipPackage::fromString($safeZip)->read('/word/document.xml'));
        $t->same($safeCommentsXml, ZipPackage::fromString($safeZip)->read('/word/comments.xml'));

        $mediaBytes = "mismatched media metadata\n";
        $descriptorXml = '<w:comments><w:comment>descriptor placeholder mismatch</w:comment></w:comments>';
        $mismatchedZip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>version mismatch</w:p></w:document>',
                'method' => 8,
                'centralVersionNeeded' => 20,
                'localVersionNeeded' => 10,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $mediaBytes,
                'method' => 0,
                'localMethod' => 8,
                'modifiedTime' => 0x4a21,
                'modifiedDate' => 0x5b63,
                'localModifiedTime' => 0x4a22,
                'localModifiedDate' => 0x5b64,
                'localCrc' => 0x12345678,
                'localCompressedSize' => strlen($mediaBytes) + 2,
                'localUncompressedSize' => strlen($mediaBytes) + 3,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $descriptorXml,
                'method' => 8,
                'descriptor' => true,
                'localCrc' => 1,
            ],
        ]);
        $summary = ZipPackage::localHeaderMetadataPreflight($mismatchedZip);

        $t->same(3, $summary['entryCount']);
        $t->same(3, $summary['totalEntryCount']);
        $t->same(3, $summary['mismatchedEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'local-header-version-needed-mismatch',
            'local-header-compression-method-mismatch',
            'local-header-modification-time-mismatch',
            'local-header-crc32-mismatch',
            'local-header-compressed-size-mismatch',
            'local-header-uncompressed-size-mismatch',
            'local-header-data-descriptor-placeholders-not-zero',
        ], $summary['issues']);
        $t->same('word/document.xml', $summary['mismatchedEntries'][0]['centralName']);
        $t->same(['local-header-version-needed-mismatch'], $summary['mismatchedEntries'][0]['issues']);
        $t->same(20, $summary['mismatchedEntries'][0]['centralVersionNeededToExtract']);
        $t->same(10, $summary['mismatchedEntries'][0]['localVersionNeededToExtract']);
        $t->same('word/media/review.txt', $summary['mismatchedEntries'][1]['centralName']);
        $t->same([
            'local-header-compression-method-mismatch',
            'local-header-modification-time-mismatch',
            'local-header-crc32-mismatch',
            'local-header-compressed-size-mismatch',
            'local-header-uncompressed-size-mismatch',
        ], $summary['mismatchedEntries'][1]['issues']);
        $t->same(0, $summary['mismatchedEntries'][1]['centralCompressionMethod']);
        $t->same(8, $summary['mismatchedEntries'][1]['localCompressionMethod']);
        $t->same(0x4a21, $summary['mismatchedEntries'][1]['centralModifiedDosTime']);
        $t->same(0x4a22, $summary['mismatchedEntries'][1]['localModifiedDosTime']);
        $t->same(0x5b63, $summary['mismatchedEntries'][1]['centralModifiedDosDate']);
        $t->same(0x5b64, $summary['mismatchedEntries'][1]['localModifiedDosDate']);
        $t->same($crc32($mediaBytes), $summary['mismatchedEntries'][1]['centralCrc32']);
        $t->same(0x12345678, $summary['mismatchedEntries'][1]['localCrc32']);
        $t->same(strlen($mediaBytes), $summary['mismatchedEntries'][1]['centralCompressedSize']);
        $t->same(strlen($mediaBytes) + 2, $summary['mismatchedEntries'][1]['localCompressedSize']);
        $t->same(strlen($mediaBytes), $summary['mismatchedEntries'][1]['centralUncompressedSize']);
        $t->same(strlen($mediaBytes) + 3, $summary['mismatchedEntries'][1]['localUncompressedSize']);
        $t->same('word/comments.xml', $summary['mismatchedEntries'][2]['centralName']);
        $t->same(['local-header-data-descriptor-placeholders-not-zero'], $summary['mismatchedEntries'][2]['issues']);
        $t->same(true, $summary['mismatchedEntries'][2]['usesDataDescriptor']);
        $t->same(false, $summary['mismatchedEntries'][2]['hasZeroLocalHeaderPlaceholders']);
        $t->same(1, $summary['mismatchedEntries'][2]['localCrc32']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($mismatchedZip));

        $timeMismatchZip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>timestamp provenance only</w:p></w:document>',
                'method' => 8,
                'modifiedTime' => 0x4a21,
                'modifiedDate' => 0x5b63,
                'localModifiedTime' => 0x4a22,
                'localModifiedDate' => 0x5b63,
            ],
        ]);
        $timeMismatchSummary = ZipPackage::localHeaderMetadataPreflight($timeMismatchZip);
        $timeMismatchPackage = ZipPackage::fromString($timeMismatchZip);

        $t->same(1, $timeMismatchSummary['mismatchedEntryCount']);
        $t->same(['local-header-modification-time-mismatch'], $timeMismatchSummary['issues']);
        $t->same(['local-header-modification-time-mismatch'], $timeMismatchSummary['mismatchedEntries'][0]['issues']);
        $t->same(
            '<w:document><w:p>timestamp provenance only</w:p></w:document>',
            $timeMismatchPackage->read('word/document.xml')
        );
    },

    'preflights zip local header fixed field byte offsets before raw package handoff' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $documentXml = '<w:document><w:p>local fixed field provenance</w:p></w:document>';
        $documentCompressed = gzdeflate($documentXml);
        if ($documentCompressed === false) {
            throw new RuntimeException('Unable to deflate local fixed header fixture');
        }
        $localExtra = pack('vva*', 0xcafe, strlen('local-fixed'), 'local-fixed');
        $commentsXml = '<w:comments><w:comment>descriptor byte offsets</w:comment></w:comments>';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'modifiedTime' => 0x4a21,
                'modifiedDate' => 0x5b63,
                'localExtra' => $localExtra,
                'centralExtra' => $localExtra,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
            ],
        ]);
        $summary = ZipPackage::localHeaderMetadataPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $t->same(2, $summary['entryCount']);
        $t->same(0, $summary['mismatchedEntryCount']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same($summary, $rawStrict['localHeaderMetadata']);

        $first = $summary['entries'][0];
        $t->same('word/document.xml', $first['centralName']);
        $t->same(0, $first['localHeaderOffset']);
        $t->same(0, $first['localFixedHeaderOffset']);
        $t->same(30, $first['localFixedHeaderLength']);
        $t->same(0, $first['localSignatureOffset']);
        $t->same(4, $first['localSignatureLength']);
        $t->same(4, $first['localVersionNeededToExtractOffset']);
        $t->same(6, $first['localGeneralPurposeFlagsOffset']);
        $t->same(8, $first['localCompressionMethodOffset']);
        $t->same(10, $first['localModifiedDosTimeOffset']);
        $t->same(12, $first['localModifiedDosDateOffset']);
        $t->same(14, $first['localCrc32Offset']);
        $t->same(18, $first['localCompressedSizeOffset']);
        $t->same(22, $first['localUncompressedSizeOffset']);
        $t->same(26, $first['localNameLengthOffset']);
        $t->same(28, $first['localExtraFieldLengthOffset']);
        $t->same(30 + strlen('word/document.xml') + strlen($localExtra), $first['localHeaderLength']);
        $t->same(30, $first['localVariableFieldsOffset']);
        $t->same(strlen('word/document.xml') + strlen($localExtra), $first['localVariableFieldsLength']);
        $t->same(30, $first['localRawNameOffset']);
        $t->same(strlen('word/document.xml'), $first['localRawNameLength']);
        $t->same(30 + strlen('word/document.xml'), $first['localExtraFieldOffset']);
        $t->same(strlen($localExtra), $first['localExtraFieldLength']);
        $t->same(30 + strlen('word/document.xml') + strlen($localExtra), $first['localHeaderEnd']);
        $t->same(0x4a21, $first['localModifiedDosTime']);
        $t->same(0x5b63, $first['localModifiedDosDate']);
        $t->same($crc32($documentXml), $first['localCrc32']);
        $t->same(strlen($documentCompressed), $first['localCompressedSize']);
        $t->same(strlen($documentXml), $first['localUncompressedSize']);

        $second = $summary['entries'][1];
        $expectedSecondOffset = $first['localHeaderEnd'] + strlen($documentCompressed);
        $t->same('word/comments.xml', $second['centralName']);
        $t->same($expectedSecondOffset, $second['localHeaderOffset']);
        $t->same($expectedSecondOffset, $second['localFixedHeaderOffset']);
        $t->same($expectedSecondOffset + 14, $second['localCrc32Offset']);
        $t->same($expectedSecondOffset + 18, $second['localCompressedSizeOffset']);
        $t->same($expectedSecondOffset + 22, $second['localUncompressedSizeOffset']);
        $t->same($expectedSecondOffset + 30, $second['localVariableFieldsOffset']);
        $t->same(strlen('word/comments.xml'), $second['localVariableFieldsLength']);
        $t->same($expectedSecondOffset + 30, $second['localRawNameOffset']);
        $t->same($expectedSecondOffset + 30 + strlen('word/comments.xml'), $second['localHeaderEnd']);
        $t->same(true, $second['usesDataDescriptor']);
        $t->same(0, $second['localCrc32']);
        $t->same(0, $second['localCompressedSize']);
        $t->same(0, $second['localUncompressedSize']);
        $t->same(true, $second['hasZeroLocalHeaderPlaceholders']);
    },

    'preflights stored first mimetype entries for ODT and EPUB containers' => static function (TestRunner $t) use ($buildZipPackage): void {
        $odtMimetype = 'application/vnd.oasis.opendocument.text';
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => $odtMimetype,
                'method' => 0,
                'centralIndex' => 2,
            ],
            [
                'name' => 'META-INF/manifest.xml',
                'data' => '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"/>',
                'method' => 8,
                'centralIndex' => 0,
            ],
            [
                'name' => 'content.xml',
                'data' => '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"/>',
                'method' => 8,
                'centralIndex' => 1,
            ],
        ]));
        $summary = $package->storedFirstEntryPreflight('/mimetype', $odtMimetype);

        $t->same('mimetype', $summary['entryName']);
        $t->same(true, $summary['exists']);
        $t->same('mimetype', $summary['firstLocalEntryName']);
        $t->same(true, $summary['isFirstLocalEntry']);
        $t->same(0, $summary['compressionMethod']);
        $t->same('stored', $summary['compressionMethodName']);
        $t->same(true, $summary['isStored']);
        $t->same([], $summary['centralExtraFieldIds']);
        $t->same([], $summary['localExtraFieldIds']);
        $t->same(false, $summary['hasCentralExtraFields']);
        $t->same(false, $summary['hasLocalExtraFields']);
        $t->same(false, $summary['usesDataDescriptor']);
        $t->same(strlen($odtMimetype), $summary['expectedBytes']);
        $t->same(strlen($odtMimetype), $summary['contentBytes']);
        $t->same(true, $summary['contentsMatch']);
        $t->same(true, $summary['isValid']);
        $t->same([], $summary['diagnostics']);
        $t->same($summary, $package->assertStoredFirstEntry('mimetype', $odtMimetype, 'ODT mimetype entry'));

        $extra = pack('vva*', 0xcafe, strlen('review'), 'review');
        $extraFieldPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => $odtMimetype,
                'method' => 0,
                'localExtra' => $extra,
                'centralExtra' => $extra,
            ],
            [
                'name' => 'content.xml',
                'data' => '<office:document-content/>',
                'method' => 8,
            ],
        ]));
        $extraSummary = $extraFieldPackage->storedFirstEntryPreflight('mimetype', $odtMimetype);
        $t->same([0xcafe], $extraSummary['centralExtraFieldIds']);
        $t->same([0xcafe], $extraSummary['localExtraFieldIds']);
        $t->same(true, $extraSummary['hasCentralExtraFields']);
        $t->same(true, $extraSummary['hasLocalExtraFields']);
        $t->same(false, $extraSummary['usesDataDescriptor']);
        $t->same(false, $extraSummary['isValid']);
        $t->contains('must not carry ZIP extra fields', implode('; ', $extraSummary['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): array => $extraFieldPackage->assertStoredFirstEntry('mimetype', $odtMimetype, 'ODT mimetype entry'));

        $descriptorPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => $odtMimetype,
                'method' => 0,
                'descriptor' => true,
            ],
            [
                'name' => 'content.xml',
                'data' => '<office:document-content/>',
                'method' => 8,
            ],
        ]));
        $descriptorSummary = $descriptorPackage->storedFirstEntryPreflight('mimetype', $odtMimetype);
        $t->same(true, $descriptorSummary['isFirstLocalEntry']);
        $t->same(true, $descriptorSummary['isStored']);
        $t->same(true, $descriptorSummary['usesDataDescriptor']);
        $t->same([], $descriptorSummary['centralExtraFieldIds']);
        $t->same([], $descriptorSummary['localExtraFieldIds']);
        $t->same(true, $descriptorSummary['contentsMatch']);
        $t->same(false, $descriptorSummary['isValid']);
        $t->contains('must not use a ZIP data descriptor', implode('; ', $descriptorSummary['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): array => $descriptorPackage->assertStoredFirstEntry('mimetype', $odtMimetype, 'ODT mimetype entry'));

        $notFirstPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'META-INF/container.xml',
                'data' => '<container/>',
                'method' => 0,
            ],
            [
                'name' => 'mimetype',
                'data' => 'application/epub+zip',
                'method' => 0,
            ],
        ]));
        $notFirstSummary = $notFirstPackage->storedFirstEntryPreflight('mimetype', 'application/epub+zip');
        $t->same('META-INF/container.xml', $notFirstSummary['firstLocalEntryName']);
        $t->same(false, $notFirstSummary['isFirstLocalEntry']);
        $t->contains('first local ZIP entry', implode('; ', $notFirstSummary['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): array => $notFirstPackage->assertStoredFirstEntry('mimetype', 'application/epub+zip', 'EPUB mimetype entry'));

        $deflatedPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => $odtMimetype,
                'method' => 8,
            ],
        ]));
        $deflatedSummary = $deflatedPackage->storedFirstEntryPreflight('mimetype', $odtMimetype);
        $t->same(8, $deflatedSummary['compressionMethod']);
        $t->same('deflated', $deflatedSummary['compressionMethodName']);
        $t->same(false, $deflatedSummary['isStored']);
        $t->same(true, $deflatedSummary['contentsMatch']);
        $t->contains('stored compression', implode('; ', $deflatedSummary['diagnostics']));

        $wrongContentsPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => 'application/zip',
                'method' => 0,
            ],
        ]));
        $wrongContentsSummary = $wrongContentsPackage->storedFirstEntryPreflight('mimetype', $odtMimetype);
        $t->same(false, $wrongContentsSummary['contentsMatch']);
        $t->same(strlen('application/zip'), $wrongContentsSummary['contentBytes']);
        $t->contains('expected bytes', implode('; ', $wrongContentsSummary['diagnostics']));

        $missingSummary = $package->storedFirstEntryPreflight('missing-mimetype', $odtMimetype);
        $t->same(false, $missingSummary['exists']);
        $t->same(null, $missingSummary['compressionMethod']);
        $t->same(null, $missingSummary['compressionMethodName']);
        $t->same(false, $missingSummary['contentsMatch']);
        $t->contains('missing entry missing-mimetype', implode('; ', $missingSummary['diagnostics']));
    },

    'reads package entries whose local header uses a data descriptor' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => str_repeat('<w:comment>review</w:comment>', 8),
                'method' => 8,
                'descriptor' => true,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $entry = $package->entry('word/comments.xml');

        $t->same(8, $entry->compressionMethod);
        $t->same(0x0808, $entry->generalPurposeFlags);
        $t->same(str_repeat('<w:comment>review</w:comment>', 8), $package->read('word/comments.xml'));
    },

    'reads data descriptors with and without optional signatures' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:p>descriptor signed</w:p></w:document>';
        $footnotesXml = '<w:footnotes><w:footnote>descriptor unsigned</w:footnote></w:footnotes>';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'descriptor' => true,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => $footnotesXml,
                'method' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $descriptorPreflight = $package->dataDescriptorPreflight();

        $t->same($documentXml, $package->read('word/document.xml'));
        $t->same($footnotesXml, $package->read('word/footnotes.xml'));
        $t->same(8, $package->entry('word/document.xml')->compressionMethod);
        $t->same(0, $package->entry('word/footnotes.xml')->compressionMethod);
        $t->same(2, $descriptorPreflight['entryCount']);
        $t->same(2, $descriptorPreflight['descriptorEntryCount']);
        $t->same(1, $descriptorPreflight['signedDescriptorEntryCount']);
        $t->same(1, $descriptorPreflight['unsignedDescriptorEntryCount']);
        $t->same(0, $descriptorPreflight['zip64SizedDescriptorEntryCount']);
        $t->same('word/document.xml', $descriptorPreflight['descriptorEntries'][0]['name']);
        $t->same(true, $descriptorPreflight['descriptorEntries'][0]['usesDataDescriptor']);
        $t->same(true, $descriptorPreflight['descriptorEntries'][0]['hasSignature']);
        $t->same(16, $descriptorPreflight['descriptorEntries'][0]['descriptorLength']);
        $t->same($package->entry('word/document.xml')->crc32Hex(), $descriptorPreflight['descriptorEntries'][0]['crc32Hex']);
        $t->same(strlen(gzdeflate($documentXml)), $descriptorPreflight['descriptorEntries'][0]['compressedSize']);
        $t->same(strlen($documentXml), $descriptorPreflight['descriptorEntries'][0]['uncompressedSize']);
        $t->same(false, $descriptorPreflight['descriptorEntries'][0]['usesZip64SizedDescriptor']);
        $t->same(0, $descriptorPreflight['descriptorEntries'][0]['localHeaderCrc32']);
        $t->same(0, $descriptorPreflight['descriptorEntries'][0]['localHeaderCompressedSize']);
        $t->same(0, $descriptorPreflight['descriptorEntries'][0]['localHeaderUncompressedSize']);
        $t->same(true, $descriptorPreflight['descriptorEntries'][0]['hasZeroLocalHeaderPlaceholders']);
        $t->same('word/footnotes.xml', $descriptorPreflight['descriptorEntries'][1]['name']);
        $t->same(false, $descriptorPreflight['descriptorEntries'][1]['hasSignature']);
        $t->same(12, $descriptorPreflight['descriptorEntries'][1]['descriptorLength']);
        $t->same(strlen($footnotesXml), $descriptorPreflight['descriptorEntries'][1]['compressedSize']);
        $t->same(strlen($footnotesXml), $descriptorPreflight['descriptorEntries'][1]['uncompressedSize']);
        $t->same(0, $descriptorPreflight['descriptorEntries'][1]['localHeaderCrc32']);
        $t->same(0, $descriptorPreflight['descriptorEntries'][1]['localHeaderCompressedSize']);
        $t->same(0, $descriptorPreflight['descriptorEntries'][1]['localHeaderUncompressedSize']);
        $t->same(true, $descriptorPreflight['descriptorEntries'][1]['hasZeroLocalHeaderPlaceholders']);
        $t->same(true, $descriptorPreflight['descriptorEntries'][0]['descriptorOffset'] < $descriptorPreflight['descriptorEntries'][1]['descriptorOffset']);
        $t->same($descriptorPreflight['descriptorEntries'][0]['descriptorOffset'] + 4, $descriptorPreflight['descriptorEntries'][0]['valueOffset']);
        $t->same($descriptorPreflight['descriptorEntries'][1]['descriptorOffset'], $descriptorPreflight['descriptorEntries'][1]['valueOffset']);
    },

    'reads unsigned data descriptors whose crc bytes equal the optional signature marker' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $reviewNote = "word comments descriptor crc-signature collision\n" . "\x71\xe1\xd2\x2b";
        $zip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => $reviewNote,
                'method' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>descriptor collision follower</w:p></w:document>',
                'method' => 8,
            ],
        ]);

        $t->same(0x08074b50, $crc32($reviewNote));

        $package = ZipPackage::fromString($zip);
        $descriptorPreflight = $package->dataDescriptorPreflight();
        $localHeaderPreflight = $package->localHeaderPreflight();

        $t->same($reviewNote, $package->read('/word/comments.xml'));
        $t->same('<w:document><w:p>descriptor collision follower</w:p></w:document>', $package->read('/word/document.xml'));
        $t->same(2, $descriptorPreflight['entryCount']);
        $t->same(1, $descriptorPreflight['descriptorEntryCount']);
        $t->same(0, $descriptorPreflight['signedDescriptorEntryCount']);
        $t->same(1, $descriptorPreflight['unsignedDescriptorEntryCount']);
        $t->same('word/comments.xml', $descriptorPreflight['descriptorEntries'][0]['name']);
        $t->same(false, $descriptorPreflight['descriptorEntries'][0]['hasSignature']);
        $t->same(12, $descriptorPreflight['descriptorEntries'][0]['descriptorLength']);
        $t->same($descriptorPreflight['descriptorEntries'][0]['descriptorOffset'], $descriptorPreflight['descriptorEntries'][0]['valueOffset']);
        $t->same('08074b50', $descriptorPreflight['descriptorEntries'][0]['crc32Hex']);
        $t->same(0x08074b50, $descriptorPreflight['descriptorEntries'][0]['crc32']);
        $t->same(strlen($reviewNote), $descriptorPreflight['descriptorEntries'][0]['compressedSize']);
        $t->same(strlen($reviewNote), $descriptorPreflight['descriptorEntries'][0]['uncompressedSize']);
        $t->same(true, $descriptorPreflight['descriptorEntries'][0]['hasZeroLocalHeaderPlaceholders']);
        $t->same(12, $localHeaderPreflight['entries'][0]['descriptorLength']);
        $t->same(true, $localHeaderPreflight['entries'][0]['isContiguousWithNext']);
    },

    'rejects data descriptor entries with nonzero local header placeholders' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>descriptor local crc placeholder</w:p></w:document>',
                'method' => 8,
                'descriptor' => true,
                'localCrc' => 1,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments><w:comment>descriptor local compressed size</w:comment></w:comments>',
                'method' => 8,
                'descriptor' => true,
                'localCompressedSize' => 1,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes><w:footnote>descriptor local uncompressed size</w:footnote></w:footnotes>',
                'method' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
                'localUncompressedSize' => 1,
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/endnotes.xml',
                'data' => '<w:endnotes><w:endnote>zero placeholders stay valid</w:endnote></w:endnotes>',
                'method' => 8,
                'descriptor' => true,
            ],
        ]));
        $summary = $package->dataDescriptorPreflight();

        $t->same(1, $summary['descriptorEntryCount']);
        $t->same('word/endnotes.xml', $summary['descriptorEntries'][0]['name']);
        $t->same(0, $summary['descriptorEntries'][0]['localHeaderCrc32']);
        $t->same(0, $summary['descriptorEntries'][0]['localHeaderCompressedSize']);
        $t->same(0, $summary['descriptorEntries'][0]['localHeaderUncompressedSize']);
        $t->same(true, $summary['descriptorEntries'][0]['hasZeroLocalHeaderPlaceholders']);
        $t->same('<w:endnotes><w:endnote>zero placeholders stay valid</w:endnote></w:endnotes>', $package->read('/word/endnotes.xml'));
    },

    'rejects zip64 sized data descriptors before package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>zip64 descriptor signed</w:p></w:document>',
                'method' => 8,
                'descriptor' => true,
                'descriptorZip64' => true,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'zip64 descriptor unsigned',
                'method' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
                'descriptorZip64' => true,
            ],
        ])));
    },

    'builds current zip package bytes for generated pandoc containers' => static function (TestRunner $t): void {
        $parts = [
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
                'comment' => 'content types',
            ],
            [
                'name' => '_rels/.rels',
                'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Generated WordPress packet</w:p></w:body></w:document>',
                'comment' => 'office document',
            ],
            [
                'name' => 'word/media/',
            ],
        ];

        $package = ZipPackage::fromParts($parts, 'wordpress package writer');
        $roundTrip = ZipPackage::fromString($package->bytes());

        $t->same($package->bytes(), ZipPackage::build($parts, 'wordpress package writer'));
        $t->contains("PK\x03\x04", $package->bytes());
        $t->contains("PK\x01\x02", $package->bytes());
        $t->same('wordpress package writer', $roundTrip->packageComment());
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/media/',
        ], $roundTrip->names());
        $t->same(0, $roundTrip->entry('[Content_Types].xml')->compressionMethod);
        $t->same(8, $roundTrip->entry('_rels/.rels')->compressionMethod);
        $t->same(8, $roundTrip->entry('word/document.xml')->compressionMethod);
        $t->same(0, $roundTrip->entry('word/media/')->compressionMethod);
        $t->same(0x10, $roundTrip->entry('word/media/')->externalFileAttributes);
        $t->same('content types', $roundTrip->entry('[Content_Types].xml')->comment);
        $t->same('office document', $roundTrip->entry('word/document.xml')->comment);
        $t->same('<Types><Default Extension="xml" ContentType="application/xml"/></Types>', $roundTrip->read('/[Content_Types].xml'));
        $t->same('<Relationships><Relationship Target="word/document.xml"/></Relationships>', $roundTrip->read('/_rels/.rels'));
        $t->same('<w:document><w:body><w:p>Generated WordPress packet</w:p></w:body></w:document>', $roundTrip->read('word/document.xml'));
        $t->same('', $roundTrip->read('word/media/'));
        $t->true($roundTrip->centralDirectoryOffset() > 0);
    },

    'builds generated zip packages with explicit known creator host systems' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>Windows generated host metadata</w:p></w:document>',
                'creatorHostSystem' => 10,
                'externalAttributes' => 0x20,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "DOS media review bytes\n",
                'compressionMethod' => 0,
                'creatorHostSystem' => 0,
                'externalAttributes' => 0x20,
            ],
        ]);
        $document = $package->entry('/word/document.xml');
        $media = $package->entry('/word/media/review.txt');
        $creatorHosts = $package->creatorHostSystemPreflight();
        $rawCreatorHosts = ZipPackage::creatorHostSystemPolicyPreflight($package->bytes());
        $strict = $package->strictImportPreflight(512, 20.0, 512);

        $t->same(10, $document->madeByHostSystem());
        $t->same('windows-ntfs', $creatorHosts['entries'][0]['madeByHostSystemName']);
        $t->same(20, $document->madeByVersion());
        $t->same(0x0a14, $document->versionMadeBy);
        $t->same(0, $media->madeByHostSystem());
        $t->same('ms-dos-fat', $creatorHosts['entries'][1]['madeByHostSystemName']);
        $t->same(2, $creatorHosts['knownHostSystemEntryCount']);
        $t->same(0, $creatorHosts['unknownHostSystemEntryCount']);
        $t->same(2, count($creatorHosts['hostSystems']));
        $t->same(10, $creatorHosts['hostSystems'][0]['id']);
        $t->same(0, $creatorHosts['hostSystems'][1]['id']);
        $t->same(0, $rawCreatorHosts['unknownHostSystemEntryCount']);
        $t->same(true, $rawCreatorHosts['isSupportedByBoundedReader']);
        $t->same([], $rawCreatorHosts['issues']);
        $t->same(true, $strict['isValid']);
        $t->same([], $strict['diagnostics']);
        $t->same('windows-ntfs', $strict['creatorHostSystems']['entries'][0]['madeByHostSystemName']);
        $t->same('ms-dos-fat', $strict['creatorHostSystems']['entries'][1]['madeByHostSystemName']);
        $t->same('<w:document><w:p>Windows generated host metadata</w:p></w:document>', $package->read('word/document.xml'));
        $t->same("DOS media review bytes\n", $package->read('word/media/review.txt'));

        $t->throws(\RuntimeException::class, static fn (): string => ZipPackage::build([
            [
                'name' => 'word/media/unknown-host.bin',
                'data' => 'unknown generated creator host systems stay blocked',
                'creatorHostSystem' => 63,
            ],
        ]));
        $t->throws(\RuntimeException::class, static fn (): string => ZipPackage::build([
            [
                'name' => 'word/media/string-host.bin',
                'data' => 'creator host system must be a byte-sized integer',
                'creatorHostSystem' => 'unix',
            ],
        ]));
    },

    'exposes zip version needed metadata and rejects local version mismatches before package preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>version metadata</w:p></w:document>',
                'method' => 8,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => 'stored reviewer media bytes',
                'method' => 0,
                'versionNeededToExtract' => 10,
            ],
        ]));

        $document = $package->entry('/word/document.xml');
        $media = $package->entry('word/media/review.bin');

        $t->same(20, $document->versionNeededToExtract);
        $t->same(20, $document->neededToExtractVersion());
        $t->same(10, $media->versionNeededToExtract);
        $t->same(10, $media->neededToExtractVersion());
        $t->same('<w:document><w:p>version metadata</w:p></w:document>', $package->read('/word/document.xml'));
        $t->same('stored reviewer media bytes', $package->read('/word/media/review.bin'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
                'centralVersionNeeded' => 20,
                'localVersionNeeded' => 10,
            ],
        ])));
    },

    'preflights zip modification times and rejects invalid dos timestamps before media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentModifiedAt = 1780479016;
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>timestamp metadata</w:p></w:document>',
                'modifiedAt' => $documentModifiedAt,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => 'review media timestamp provenance',
                'compressionMethod' => 0,
            ],
        ]);
        $summary = $package->modificationTimePreflight();

        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['timestampEntryCount']);
        $t->same(1, $summary['dosTimestampEntryCount']);
        $t->same(1, $summary['extendedTimestampEntryCount']);
        $t->same(0, $summary['ntfsTimestampEntryCount']);
        $t->same(0, $summary['invalidDosTimestampEntryCount']);
        $t->same([], $summary['invalidDosTimestampEntries']);
        $t->same('word/document.xml', $summary['entries'][0]['name']);
        $t->same(true, $summary['entries'][0]['hasDosTimestamp']);
        $t->same(true, $summary['entries'][0]['isDosTimestampValid']);
        $t->same($documentModifiedAt, $summary['entries'][0]['modifiedAt']);
        $t->same('extended-timestamp', $summary['entries'][0]['timestampSource']);
        $t->same($documentModifiedAt, $package->entry('/word/document.xml')->dosLastModifiedTimestamp());
        $t->same(false, $summary['entries'][1]['hasDosTimestamp']);
        $t->same(true, $summary['entries'][1]['isDosTimestampValid']);
        $t->same(null, $summary['entries'][1]['modifiedAt']);
        $t->same($summary, $package->assertValidModificationTimes());
        $t->same($summary, $package->strictImportPreflight(2048, 100.0, 2048)['modificationTimes']);

        $invalidPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/bad-date.txt',
                'data' => 'invalid DOS date metadata should be reviewed',
                'method' => 0,
                'modifiedTime' => 0,
                'modifiedDate' => 0x0020,
            ],
        ]));
        $invalidSummary = $invalidPackage->modificationTimePreflight();
        $strictSummary = $invalidPackage->strictImportPreflight(2048, 100.0, 2048);

        $t->same(1, $invalidSummary['entryCount']);
        $t->same(0, $invalidSummary['timestampEntryCount']);
        $t->same(1, $invalidSummary['dosTimestampEntryCount']);
        $t->same(1, $invalidSummary['invalidDosTimestampEntryCount']);
        $t->same('word/media/bad-date.txt', $invalidSummary['invalidDosTimestampEntries'][0]['name']);
        $t->same(false, $invalidSummary['invalidDosTimestampEntries'][0]['isDosTimestampValid']);
        $t->same(['invalid-dos-modified-timestamp'], $invalidSummary['invalidDosTimestampEntries'][0]['issues']);
        $t->same(null, $invalidSummary['invalidDosTimestampEntries'][0]['modifiedAt']);
        $t->same(['invalid-modification-times'], $strictSummary['diagnostics']);
        $t->same(false, $strictSummary['isValid']);
        $t->throws(\RuntimeException::class, static fn (): array => $invalidPackage->assertValidModificationTimes());
        $t->throws(\RuntimeException::class, static fn (): array => $invalidPackage->assertStrictImportable(2048, 100.0, 2048));
        $t->same('invalid DOS date metadata should be reviewed', $invalidPackage->read('/word/media/bad-date.txt'));
    },

    'preflights central and local zip timestamp provenance before media handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildNtfsExtra): void {
        $centralModifiedAt = 1780479017;
        $localModifiedAt = 1780479020;
        $localAccessedAt = 1780479021;
        $localCreatedAt = 1780479022;
        $centralTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, $centralModifiedAt);
        $localNtfsExtra = $buildNtfsExtra($localModifiedAt, $localAccessedAt, $localCreatedAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'timestamp provenance should remain inspectable',
                'method' => 0,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localExtra' => $localNtfsExtra,
                'centralExtra' => $centralTimestampExtra,
            ],
        ]));
        $summary = $package->modificationTimePreflight();
        $entry = $summary['entries'][0];

        $t->same(1, $summary['entryCount']);
        $t->same(1, $summary['timestampEntryCount']);
        $t->same(1, $summary['extendedTimestampEntryCount']);
        $t->same(0, $summary['ntfsTimestampEntryCount']);
        $t->same('extended-timestamp', $entry['timestampSource']);
        $t->same($centralModifiedAt, $entry['modifiedAt']);
        $t->same(null, $entry['localExtendedModifiedAt']);
        $t->same($localModifiedAt, $entry['localNtfsModifiedAt']);
        $t->same($localModifiedAt, $entry['localModifiedAt']);
        $t->same('ntfs', $entry['localTimestampSource']);
        $t->same(['modifiedAt' => $localModifiedAt, 'accessedAt' => $localAccessedAt, 'createdAt' => $localCreatedAt], $package->localNtfsTimestamps('word/media/reviewer-note.txt'));
        $t->same('timestamp provenance should remain inspectable', $package->read('/word/media/reviewer-note.txt'));
    },

    'preflights raw zip modification times before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $centralModifiedAt = 1780479017;
        $centralTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, $centralModifiedAt);
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/spoofed-document.xml',
                'data' => '<w:document><w:p>raw timestamp policy</w:p></w:document>',
                'method' => 0,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localExtra' => '',
                'centralExtra' => $centralTimestampExtra,
            ],
            [
                'name' => 'word/media/bad-date.txt',
                'data' => 'invalid DOS timestamp remains visible before construction',
                'method' => 0,
                'modifiedTime' => 0,
                'modifiedDate' => 0x0020,
            ],
        ]);
        $summary = ZipPackage::modificationTimePolicyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same(1, $summary['timestampEntryCount']);
        $t->same(2, $summary['dosTimestampEntryCount']);
        $t->same(1, $summary['extendedTimestampEntryCount']);
        $t->same(0, $summary['ntfsTimestampEntryCount']);
        $t->same(1, $summary['invalidDosTimestampEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['invalid-modification-times'], $summary['issues']);

        $first = $summary['entries'][0];
        $t->same('word/document.xml', $first['name']);
        $t->same('word/document.xml', $first['rawName']);
        $t->same(0, $first['centralDirectoryIndex']);
        $t->same(19400, $first['modifiedDosTime']);
        $t->same(23747, $first['modifiedDosDate']);
        $t->same(true, $first['hasDosTimestamp']);
        $t->same(true, $first['isDosTimestampValid']);
        $t->same(1780479016, $first['dosModifiedAt']);
        $t->same($centralModifiedAt, $first['extendedModifiedAt']);
        $t->same($centralModifiedAt, $first['modifiedAt']);
        $t->same('extended-timestamp', $first['timestampSource']);
        $t->same([], $first['issues']);

        $invalid = $summary['invalidDosTimestampEntries'][0];
        $t->same('word/media/bad-date.txt', $invalid['name']);
        $t->same(false, $invalid['isDosTimestampValid']);
        $t->same(null, $invalid['dosModifiedAt']);
        $t->same(null, $invalid['modifiedAt']);
        $t->same(['invalid-dos-modified-timestamp'], $invalid['issues']);

        $t->same($summary, $rawStrict['modificationTimes']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->contains('invalid-modification-times', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-header-name-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'rejects unsupported zip extraction versions before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/zip64-era-review.bin',
                'data' => 'unsupported extraction version metadata should stay blocked',
                'method' => 0,
                'versionNeededToExtract' => 45,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/bzip2-review.bin',
                'data' => 'unsupported bzip2-era package metadata should stay blocked',
                'method' => 12,
                'versionNeededToExtract' => 46,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/lzma-review.bin',
                'data' => 'unsupported lzma-era package metadata should stay blocked',
                'method' => 14,
                'versionNeededToExtract' => 63,
            ],
        ])));
    },

    'preflights understated zip extraction versions before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>deflated package parts need version 20 metadata</w:p></w:document>',
                'method' => 8,
                'versionNeededToExtract' => 10,
            ],
            [
                'name' => 'word/media/streamed.bin',
                'data' => 'stored media using a data descriptor also needs version 20 metadata',
                'method' => 0,
                'descriptor' => true,
                'versionNeededToExtract' => 10,
            ],
            [
                'name' => 'word/media/stored.bin',
                'data' => 'ordinary stored media remains version 10 compatible',
                'method' => 0,
                'versionNeededToExtract' => 10,
            ],
        ]);
        $summary = ZipPackage::compressionMethodPolicyPreflight($zip);

        $t->same(3, $summary['entryCount']);
        $t->same(1, $summary['supportedEntryCount']);
        $t->same(0, $summary['unsupportedCompressionMethodCount']);
        $t->same(2, $summary['storedEntryCount']);
        $t->same(1, $summary['deflatedEntryCount']);
        $t->same(2, $summary['unsupportedVersionEntryCount']);
        $t->same(0, $summary['versionNeededExceedsBoundedReaderEntryCount']);
        $t->same(2, $summary['understatedVersionEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['unsupported-version-needed', 'version-needed-below-feature-minimum'], $summary['issues']);

        $deflatedEntry = $summary['understatedVersionEntries'][0];
        $t->same('word/document.xml', $deflatedEntry['name']);
        $t->same(8, $deflatedEntry['compressionMethod']);
        $t->same('deflated', $deflatedEntry['compressionMethodName']);
        $t->same(10, $deflatedEntry['versionNeededToExtract']);
        $t->same(20, $deflatedEntry['minimumVersionNeededToExtract']);
        $t->same(true, $deflatedEntry['versionNeededTooLow']);
        $t->same(false, $deflatedEntry['versionNeededExceedsBoundedReader']);
        $t->same(false, $deflatedEntry['usesDataDescriptor']);
        $t->same(['zip-version-needed-below-feature-minimum'], $deflatedEntry['diagnostics']);

        $descriptorEntry = $summary['understatedVersionEntries'][1];
        $t->same('word/media/streamed.bin', $descriptorEntry['name']);
        $t->same(0, $descriptorEntry['compressionMethod']);
        $t->same('stored', $descriptorEntry['compressionMethodName']);
        $t->same(10, $descriptorEntry['versionNeededToExtract']);
        $t->same(20, $descriptorEntry['minimumVersionNeededToExtract']);
        $t->same(true, $descriptorEntry['usesDataDescriptor']);
        $t->same(true, $descriptorEntry['versionNeededTooLow']);
        $t->same(['zip-version-needed-below-feature-minimum'], $descriptorEntry['diagnostics']);

        $storedEntry = $summary['entries'][2];
        $t->same('word/media/stored.bin', $storedEntry['name']);
        $t->same(10, $storedEntry['versionNeededToExtract']);
        $t->same(10, $storedEntry['minimumVersionNeededToExtract']);
        $t->same(false, $storedEntry['versionNeededTooLow']);
        $t->same(true, $storedEntry['isSupported']);
        $t->same([], $storedEntry['diagnostics']);
        $package = ZipPackage::fromString($zip);
        $t->same('<w:document><w:p>deflated package parts need version 20 metadata</w:p></w:document>', $package->read('word/document.xml'));
        $t->same('stored media using a data descriptor also needs version 20 metadata', $package->read('word/media/streamed.bin'));

        $safeZip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ordinary deflate version metadata</w:p></w:document>',
                'method' => 8,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'word/media/streamed.bin',
                'data' => 'streamed stored media with version 20 metadata',
                'method' => 0,
                'descriptor' => true,
                'versionNeededToExtract' => 20,
            ],
            [
                'name' => 'word/media/stored.bin',
                'data' => 'stored media with version 10 metadata',
                'method' => 0,
                'versionNeededToExtract' => 10,
            ],
        ]);
        $safeSummary = ZipPackage::compressionMethodPolicyPreflight($safeZip);

        $t->same(3, $safeSummary['supportedEntryCount']);
        $t->same(0, $safeSummary['unsupportedVersionEntryCount']);
        $t->same(0, $safeSummary['understatedVersionEntryCount']);
        $t->same([], $safeSummary['issues']);
        $t->same(true, $safeSummary['isSupportedByBoundedReader']);
        $t->same(
            '<w:document><w:p>ordinary deflate version metadata</w:p></w:document>',
            ZipPackage::fromString($safeZip)->read('/word/document.xml')
        );
    },

    'preserves zip entry modification metadata and external attributes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>metadata</w:p></w:document>',
                'method' => 8,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'externalAttributes' => 0x81a40000,
            ],
        ], 'metadata package');

        $package = ZipPackage::fromString($zip);
        $entry = $package->entry('/word/document.xml');

        $t->same(19400, $entry->modifiedDosTime());
        $t->same(23747, $entry->modifiedDosDate());
        $t->same(1780479016, $entry->lastModifiedTimestamp());
        $t->same(3, $entry->madeByHostSystem());
        $t->same(20, $entry->madeByVersion());
        $t->same(0x81a4, $entry->unixMode());
        $t->same(false, $entry->isUnixSymlink());
        $t->same(0x81a40000, $entry->externalFileAttributes);
        $t->same('metadata package', $package->packageComment());
        $t->same('<w:document><w:p>metadata</w:p></w:document>', $package->read('word/document.xml'));
    },

    'preflights zip internal file attributes before strict media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:p>internal text flag</w:p></w:document>';
        $binaryMedia = "binary media provenance\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'internalAttributes' => 0x0001,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $binaryMedia,
                'method' => 0,
                'internalAttributes' => 0x8002,
            ],
            [
                'name' => 'word/media/plain.txt',
                'data' => 'ordinary media note',
                'method' => 0,
            ],
        ]));

        $document = $package->entry('/word/document.xml');
        $media = $package->entry('/word/media/review.bin');
        $summary = $package->internalAttributePreflight();

        $t->same(0x0001, $document->internalFileAttributes);
        $t->same(true, $document->hasTextInternalAttribute());
        $t->same(0, $document->unknownInternalAttributeBits());
        $t->same(['apparently-text'], $document->internalAttributeNames());
        $t->same(0x8002, $media->internalFileAttributes);
        $t->same(false, $media->hasTextInternalAttribute());
        $t->same(0x8002, $media->unknownInternalAttributeBits());
        $t->same(['unknown-0x8002'], $media->internalAttributeNames());
        $t->same(3, $summary['entryCount']);
        $t->same(2, $summary['internalAttributeEntryCount']);
        $t->same(1, $summary['textInternalAttributeEntryCount']);
        $t->same(1, $summary['unknownInternalAttributeEntryCount']);
        $t->same('word/document.xml', $summary['internalAttributeEntries'][0]['name']);
        $t->same(['internal-text-attribute'], $summary['internalAttributeEntries'][0]['issues']);
        $t->same('word/media/review.bin', $summary['unknownInternalAttributeEntries'][0]['name']);
        $t->same(['unknown-internal-file-attribute-bits'], $summary['unknownInternalAttributeEntries'][0]['issues']);
        $t->same(false, $summary['entries'][2]['hasInternalFileAttributes']);
        $t->same([], $summary['entries'][2]['issues']);

        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $t->same(false, $strict['isValid']);
        $t->contains('internal-file-attributes', implode(',', $strict['diagnostics']));
        $t->same(2, $strict['internalAttributes']['internalAttributeEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoInternalFileAttributes());
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(2048, 100.0, 2048));
        $t->same($documentXml, $package->read('/word/document.xml'));
        $t->same($binaryMedia, $package->read('/word/media/review.bin'));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ordinary internal attributes</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => 'ordinary media note',
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoInternalFileAttributes();

        $t->same(2, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['internalAttributeEntryCount']);
        $t->same([], $safeSummary['internalAttributeEntries']);
        $t->same(true, $safePackage->strictImportPreflight(2048, 100.0, 2048)['isValid']);
    },

    'preflights raw zip internal file attributes before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/document-local-spoof.xml',
                'data' => '<w:document><w:p>raw internal text flag</w:p></w:document>',
                'method' => 0,
                'internalAttributes' => 0x0001,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => "binary media provenance\n",
                'method' => 0,
                'internalAttributes' => 0x8002,
            ],
            [
                'name' => 'word/media/plain.txt',
                'data' => 'ordinary media note',
                'method' => 0,
            ],
        ]);
        $summary = ZipPackage::internalAttributePolicyPreflight($zip);
        $raw = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $t->same(3, $summary['entryCount']);
        $t->same(2, $summary['internalAttributeEntryCount']);
        $t->same(1, $summary['textInternalAttributeEntryCount']);
        $t->same(1, $summary['unknownInternalAttributeEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['internal-file-attributes'], $summary['issues']);
        $t->same('word/document.xml', $summary['textInternalAttributeEntries'][0]['name']);
        $t->same(0x0001, $summary['textInternalAttributeEntries'][0]['internalFileAttributes']);
        $t->same(['apparently-text'], $summary['textInternalAttributeEntries'][0]['internalAttributeNames']);
        $t->same(['internal-text-attribute'], $summary['textInternalAttributeEntries'][0]['issues']);
        $t->same('word/media/review.bin', $summary['unknownInternalAttributeEntries'][0]['name']);
        $t->same(0x8002, $summary['unknownInternalAttributeEntries'][0]['unknownInternalAttributeBits']);
        $t->same(['unknown-0x8002'], $summary['unknownInternalAttributeEntries'][0]['internalAttributeNames']);
        $t->same(['unknown-internal-file-attribute-bits'], $summary['unknownInternalAttributeEntries'][0]['issues']);
        $t->same(false, $summary['entries'][2]['hasInternalFileAttributes']);
        $t->same('metadata', $summary['entries'][2]['policy']);

        $t->same(false, $raw['isValid']);
        $t->same(false, $raw['canInstantiate']);
        $t->same(3, $raw['entryCount']);
        $t->same($summary, $raw['internalAttributes']);
        $t->same(1, $raw['localHeaderNames']['mismatchedEntryCount']);
        $t->contains('internal-file-attributes', implode(',', $raw['diagnostics']));
        $t->contains('local-header-name-issues', implode(',', $raw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $raw['diagnostics']));
        $t->same(null, $raw['strictImport']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights unix executable permissions before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>permission preflight</w:p></w:document>',
                'method' => 8,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/reviewer-script.bin',
                'data' => "#!/bin/sh\necho reviewer-script\n",
                'method' => 0,
                'externalAttributes' => 0x81ed0000,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x41ed0000,
            ],
            [
                'name' => 'word/media/fat-flags.bin',
                'data' => 'non-unix host bits should stay metadata only',
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0x81ff0000,
            ],
        ]));

        $document = $package->entry('/word/document.xml');
        $script = $package->entry('/word/media/reviewer-script.bin');
        $directory = $package->entry('/word/media/');
        $fatEntry = $package->entry('/word/media/fat-flags.bin');
        $summary = $package->permissionPreflight();

        $t->same(0644, $document->unixPermissionBits());
        $t->same(false, $document->isUnixExecutableFile());
        $t->same(0755, $script->unixPermissionBits());
        $t->same(true, $script->isUnixExecutableFile());
        $t->same(0755, $directory->unixPermissionBits());
        $t->same(false, $directory->isUnixExecutableFile());
        $t->same(null, $fatEntry->unixPermissionBits());
        $t->same(false, $fatEntry->isUnixExecutableFile());
        $t->same(4, $summary['entryCount']);
        $t->same(3, $summary['unixModeEntryCount']);
        $t->same(1, $summary['executableFileCount']);
        $t->same(0, $summary['writablePermissionEntryCount']);
        $t->same('word/media/reviewer-script.bin', $summary['executableEntries'][0]['name']);
        $t->same(0x81ed, $summary['executableEntries'][0]['unixMode']);
        $t->same(0755, $summary['executableEntries'][0]['permissions']);
        $t->same(false, $summary['executableEntries'][0]['isGroupWritable']);
        $t->same(false, $summary['executableEntries'][0]['isWorldWritable']);
        $t->same(['unix-executable-file'], $summary['executableEntries'][0]['issues']);
        $t->same(true, $summary['entries'][1]['isExecutableFile']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoExecutableFiles());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe generated permissions</w:p></w:document>',
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'externalAttributes' => 0x41ed0000,
            ],
        ]);
        $safeSummary = $safePackage->assertNoExecutableFiles();

        $t->same(2, $safeSummary['unixModeEntryCount']);
        $t->same(0, $safeSummary['executableFileCount']);
        $t->same(0, $safeSummary['writablePermissionEntryCount']);
        $t->same([], $safeSummary['executableEntries']);
        $t->same([], $safeSummary['writablePermissionEntries']);
    },

    'preflights group and world writable unix permissions before media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>writable permission preflight</w:p></w:document>',
                'method' => 8,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/group-writable.txt',
                'data' => "group writable media provenance\n",
                'method' => 0,
                'externalAttributes' => 0x81b40000,
            ],
            [
                'name' => 'word/media/world-writable.txt',
                'data' => "world writable media provenance\n",
                'method' => 0,
                'externalAttributes' => 0x81b60000,
            ],
            [
                'name' => 'word/media/open-directory/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x41ff0000,
            ],
            [
                'name' => 'word/media/fat-permissions.bin',
                'data' => "non-unix host mode bits stay metadata\n",
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0x81b60000,
            ],
        ]));
        $summary = $package->permissionPreflight();

        $t->same(5, $summary['entryCount']);
        $t->same(4, $summary['unixModeEntryCount']);
        $t->same(0, $summary['executableFileCount']);
        $t->same(3, $summary['groupWritableEntryCount']);
        $t->same(2, $summary['worldWritableEntryCount']);
        $t->same(3, $summary['writablePermissionEntryCount']);
        $t->same([], $summary['executableEntries']);
        $t->same('word/media/group-writable.txt', $summary['writablePermissionEntries'][0]['name']);
        $t->same(0x81b4, $summary['writablePermissionEntries'][0]['unixMode']);
        $t->same(0664, $summary['writablePermissionEntries'][0]['permissions']);
        $t->same(true, $summary['writablePermissionEntries'][0]['isGroupWritable']);
        $t->same(false, $summary['writablePermissionEntries'][0]['isWorldWritable']);
        $t->same(['unix-group-writable-permission'], $summary['writablePermissionEntries'][0]['issues']);
        $t->same('word/media/world-writable.txt', $summary['writablePermissionEntries'][1]['name']);
        $t->same(0666, $summary['writablePermissionEntries'][1]['permissions']);
        $t->same(true, $summary['writablePermissionEntries'][1]['isGroupWritable']);
        $t->same(true, $summary['writablePermissionEntries'][1]['isWorldWritable']);
        $t->same([
            'unix-group-writable-permission',
            'unix-world-writable-permission',
        ], $summary['writablePermissionEntries'][1]['issues']);
        $t->same('word/media/open-directory/', $summary['writablePermissionEntries'][2]['name']);
        $t->same(0777, $summary['writablePermissionEntries'][2]['permissions']);
        $t->same(true, $summary['writablePermissionEntries'][2]['isDirectory']);
        $t->same(true, $summary['writablePermissionEntries'][2]['hasWritablePermissions']);
        $t->same(null, $summary['entries'][4]['unixMode']);
        $t->same(false, $summary['entries'][4]['hasWritablePermissions']);
        $t->same(false, $summary['entries'][4]['isGroupWritable']);
        $t->same(false, $summary['entries'][4]['isWorldWritable']);

        $strict = $package->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strict['isValid']);
        $t->same(['unix-writable-permission-entries'], $strict['diagnostics']);
        $t->same(3, $strict['permissions']['writablePermissionEntryCount']);
        $t->same(3, $strict['permissions']['groupWritableEntryCount']);
        $t->same(2, $strict['permissions']['worldWritableEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoWritablePermissionEntries());
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(4096, 100.0, 4096));
        $t->same("group writable media provenance\n", $package->read('/word/media/group-writable.txt'));
        $t->same("world writable media provenance\n", $package->read('/word/media/world-writable.txt'));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe writable permission policy</w:p></w:document>',
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'externalAttributes' => 0x41ed0000,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "safe permission media provenance\n",
                'compressionMethod' => 0,
                'externalAttributes' => 0x81a40000,
            ],
        ]);
        $safeSummary = $safePackage->assertNoWritablePermissionEntries();

        $t->same(3, $safeSummary['entryCount']);
        $t->same(3, $safeSummary['unixModeEntryCount']);
        $t->same(0, $safeSummary['groupWritableEntryCount']);
        $t->same(0, $safeSummary['worldWritableEntryCount']);
        $t->same(0, $safeSummary['writablePermissionEntryCount']);
        $t->same([], $safeSummary['writablePermissionEntries']);
        $t->same(true, $safePackage->strictImportPreflight(4096, 100.0, 4096)['isValid']);
    },

    'preflights zip creator host systems before package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>creator host metadata</w:p></w:document>',
                'method' => 8,
                'versionMadeBy' => 0x0314,
            ],
            [
                'name' => 'word/media/windows-review.bin',
                'data' => 'windows-origin media bytes',
                'method' => 0,
                'versionMadeBy' => 0x0a14,
            ],
            [
                'name' => 'word/media/unknown-review.bin',
                'data' => 'unknown-origin media bytes',
                'method' => 0,
                'versionMadeBy' => 0x3f14,
            ],
        ]));
        $summary = $package->creatorHostSystemPreflight();

        $t->same(3, $summary['entryCount']);
        $t->same(2, $summary['knownHostSystemEntryCount']);
        $t->same(1, $summary['unknownHostSystemEntryCount']);
        $t->same(3, count($summary['hostSystems']));
        $t->same(3, $summary['hostSystems'][0]['id']);
        $t->same('unix', $summary['hostSystems'][0]['name']);
        $t->same(1, $summary['hostSystems'][0]['entryCount']);
        $t->same(10, $summary['hostSystems'][1]['id']);
        $t->same('windows-ntfs', $summary['hostSystems'][1]['name']);
        $t->same(63, $summary['hostSystems'][2]['id']);
        $t->same('unknown', $summary['hostSystems'][2]['name']);
        $t->same(false, $summary['hostSystems'][2]['isKnown']);
        $t->same('word/media/unknown-review.bin', $summary['unknownEntries'][0]['name']);
        $t->same(63, $summary['unknownEntries'][0]['madeByHostSystem']);
        $t->same('unknown', $summary['unknownEntries'][0]['madeByHostSystemName']);
        $t->same(20, $summary['unknownEntries'][0]['madeByVersion']);
        $t->same(0x3f14, $summary['unknownEntries'][0]['versionMadeBy']);
        $t->same('windows-ntfs', $summary['entries'][1]['madeByHostSystemName']);
        $t->same(true, $summary['entries'][1]['isKnown']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertKnownCreatorHostSystems());

        $safePackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>known creator metadata</w:p></w:document>',
                'method' => 8,
                'versionMadeBy' => 0x0314,
            ],
            [
                'name' => 'word/media/windows-review.bin',
                'data' => 'known windows media bytes',
                'method' => 0,
                'versionMadeBy' => 0x0a14,
            ],
        ]));
        $safeSummary = $safePackage->assertKnownCreatorHostSystems();

        $t->same(2, $safeSummary['knownHostSystemEntryCount']);
        $t->same(0, $safeSummary['unknownHostSystemEntryCount']);
        $t->same([], $safeSummary['unknownEntries']);

        $rawZip = $buildZipPackage([
            [
                'name' => 'word/media/strong-unknown-host.bin',
                'data' => 'raw creator host metadata should survive unsupported flags',
                'method' => 0,
                'flags' => 0x0840,
                'versionMadeBy' => 0x3f14,
            ],
        ]);
        $rawCreatorHosts = ZipPackage::creatorHostSystemPolicyPreflight($rawZip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($rawZip, 512, 20.0, 512);

        $t->same(1, $rawCreatorHosts['entryCount']);
        $t->same(0, $rawCreatorHosts['knownHostSystemEntryCount']);
        $t->same(1, $rawCreatorHosts['unknownHostSystemEntryCount']);
        $t->same(1, $rawCreatorHosts['blockedEntryCount']);
        $t->same(false, $rawCreatorHosts['isSupportedByBoundedReader']);
        $t->same(['unknown-creator-host-systems'], $rawCreatorHosts['issues']);
        $t->same('word/media/strong-unknown-host.bin', $rawCreatorHosts['unknownEntries'][0]['name']);
        $t->same(63, $rawCreatorHosts['unknownEntries'][0]['madeByHostSystem']);
        $t->same('unknown', $rawCreatorHosts['unknownEntries'][0]['madeByHostSystemName']);
        $t->same('blocked', $rawCreatorHosts['unknownEntries'][0]['policy']);
        $t->same(['zip-unknown-creator-host-system'], $rawCreatorHosts['unknownEntries'][0]['diagnostics']);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->same($rawCreatorHosts, $rawStrict['creatorHostSystems']);
        $t->same(1, $rawStrict['encryption']['strongEncryptionEntryCount']);
        $t->contains('unknown-creator-host-systems', implode(',', $rawStrict['diagnostics']));
        $t->contains('encrypted-zip-entries', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
    },

    'preflights zip creator version provenance before package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>creator version provenance</w:p></w:document>',
                'method' => 8,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x030a,
            ],
            [
                'name' => 'word/media/stored.txt',
                'data' => "stored media remains zip 1.0 compatible\n",
                'method' => 0,
                'versionNeededToExtract' => 10,
                'versionMadeBy' => 0x000a,
            ],
        ]);
        $package = ZipPackage::fromString($zip);
        $summary = $package->creatorHostSystemPreflight();
        $rawSummary = ZipPackage::creatorHostSystemPolicyPreflight($zip);
        $strict = $package->strictImportPreflight(4096, 100.0, 4096);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 4096, 100.0, 4096);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['knownHostSystemEntryCount']);
        $t->same(0, $summary['unknownHostSystemEntryCount']);
        $t->same(1, $summary['creatorVersionBelowNeededEntryCount']);
        $t->same('word/document.xml', $summary['creatorVersionBelowNeededEntries'][0]['name']);
        $t->same(10, $summary['creatorVersionBelowNeededEntries'][0]['madeByVersion']);
        $t->same(20, $summary['creatorVersionBelowNeededEntries'][0]['versionNeededToExtract']);
        $t->same(false, $summary['creatorVersionBelowNeededEntries'][0]['creatorVersionMeetsNeeded']);
        $t->same(['creator-version-below-version-needed'], $summary['creatorVersionBelowNeededEntries'][0]['issues']);
        $t->same(true, $summary['entries'][1]['creatorVersionMeetsNeeded']);
        $t->same([], $summary['entries'][1]['issues']);

        $t->same(1, $rawSummary['creatorVersionBelowNeededEntryCount']);
        $t->same(1, $rawSummary['blockedEntryCount']);
        $t->same(false, $rawSummary['isSupportedByBoundedReader']);
        $t->same(['creator-version-below-version-needed'], $rawSummary['issues']);
        $t->same('blocked', $rawSummary['creatorVersionBelowNeededEntries'][0]['policy']);
        $t->same(['zip-creator-version-below-version-needed'], $rawSummary['creatorVersionBelowNeededEntries'][0]['diagnostics']);

        $t->same(false, $strict['isValid']);
        $t->same(['creator-version-below-version-needed'], $strict['diagnostics']);
        $t->same($summary, $strict['creatorHostSystems']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(true, $rawStrict['canInstantiate']);
        $t->same($rawSummary, $rawStrict['creatorHostSystems']);
        $t->same(['creator-version-below-version-needed'], $rawStrict['strictImport']['diagnostics']);
        $t->contains('creator-version-below-version-needed', implode(',', $rawStrict['diagnostics']));
        $t->same('<w:document><w:p>creator version provenance</w:p></w:document>', $package->read('word/document.xml'));

        $safeZip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>creator version accepted</w:p></w:document>',
                'method' => 8,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x0314,
            ],
            [
                'name' => 'word/media/stored.txt',
                'data' => "stored media compatible creator version\n",
                'method' => 0,
                'versionNeededToExtract' => 10,
                'versionMadeBy' => 0x000a,
            ],
        ]);
        $safeRaw = ZipPackage::rawStrictImportPreflight($safeZip, 4096, 100.0, 4096);

        $t->same(true, $safeRaw['isValid']);
        $t->same(0, $safeRaw['creatorHostSystems']['creatorVersionBelowNeededEntryCount']);
        $t->same([], $safeRaw['diagnostics']);
    },

    'preflights zip creator host and version matrix before package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>creator matrix equal version</w:p></w:document>',
                'method' => 8,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x0314,
            ],
            [
                'name' => 'word/media/windows-newer.txt',
                'data' => "windows creator version newer than needed\n",
                'method' => 0,
                'versionNeededToExtract' => 10,
                'versionMadeBy' => 0x0a14,
            ],
            [
                'name' => 'word/media/unix-legacy-deflate.bin',
                'data' => "legacy creator version below deflate need\n",
                'method' => 8,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x030a,
            ],
            [
                'name' => 'word/media/unknown-equal.bin',
                'data' => "unknown host with equal creator version\n",
                'method' => 0,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x3f14,
            ],
            [
                'name' => 'word/media/unknown-legacy.bin',
                'data' => "unknown host with legacy creator version\n",
                'method' => 0,
                'versionNeededToExtract' => 20,
                'versionMadeBy' => 0x3f0a,
            ],
        ]);
        $package = ZipPackage::fromString($zip);
        $summary = $package->creatorHostSystemPreflight();
        $rawSummary = ZipPackage::creatorHostSystemPolicyPreflight($zip);
        $strict = $package->strictImportPreflight(4096, 100.0, 4096);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 4096, 100.0, 4096);

        $entryNames = static fn (array $entries): array => array_map(
            static fn (array $entry): string => $entry['name'],
            $entries
        );
        $entriesByName = [];
        foreach ($summary['entries'] as $entry) {
            $entriesByName[$entry['name']] = $entry;
        }
        $hostSystemCounts = [];
        foreach ($summary['hostSystems'] as $hostSystem) {
            $hostSystemCounts[$hostSystem['id']] = $hostSystem['entryCount'];
        }

        $t->same(5, $summary['entryCount']);
        $t->same(3, $summary['knownHostSystemEntryCount']);
        $t->same(2, $summary['unknownHostSystemEntryCount']);
        $t->same(3, $summary['creatorVersionMeetsNeededEntryCount']);
        $t->same(2, $summary['creatorVersionBelowNeededEntryCount']);
        $t->same(2, $summary['creatorVersionEqualNeededEntryCount']);
        $t->same(1, $summary['creatorVersionAboveNeededEntryCount']);
        $t->same(1, $summary['creatorVersionBelowNeededKnownHostEntryCount']);
        $t->same(1, $summary['creatorVersionBelowNeededUnknownHostEntryCount']);
        $t->same(['below-needed' => 2, 'equals-needed' => 2, 'above-needed' => 1], $summary['creatorVersionComparisonCounts']);
        $t->same([3 => 2, 10 => 1, 63 => 2], $hostSystemCounts);
        $t->same(['word/media/unknown-equal.bin', 'word/media/unknown-legacy.bin'], $entryNames($summary['unknownEntries']));
        $t->same(['word/media/unix-legacy-deflate.bin', 'word/media/unknown-legacy.bin'], $entryNames($summary['creatorVersionBelowNeededEntries']));

        $t->same('equals-needed', $entriesByName['word/document.xml']['creatorVersionComparison']);
        $t->same(0, $entriesByName['word/document.xml']['creatorVersionDelta']);
        $t->same([], $entriesByName['word/document.xml']['issues']);
        $t->same('above-needed', $entriesByName['word/media/windows-newer.txt']['creatorVersionComparison']);
        $t->same(10, $entriesByName['word/media/windows-newer.txt']['creatorVersionDelta']);
        $t->same('below-needed', $entriesByName['word/media/unix-legacy-deflate.bin']['creatorVersionComparison']);
        $t->same(-10, $entriesByName['word/media/unix-legacy-deflate.bin']['creatorVersionDelta']);
        $t->same(['creator-version-below-version-needed'], $entriesByName['word/media/unix-legacy-deflate.bin']['issues']);
        $t->same(['unknown-creator-host-system'], $entriesByName['word/media/unknown-equal.bin']['issues']);
        $t->same(['unknown-creator-host-system', 'creator-version-below-version-needed'], $entriesByName['word/media/unknown-legacy.bin']['issues']);

        $t->same($summary['creatorVersionComparisonCounts'], $rawSummary['creatorVersionComparisonCounts']);
        $t->same(3, $rawSummary['blockedEntryCount']);
        $t->same(false, $rawSummary['isSupportedByBoundedReader']);
        $t->same(['unknown-creator-host-systems', 'creator-version-below-version-needed'], $rawSummary['issues']);
        $t->same(['word/media/unix-legacy-deflate.bin', 'word/media/unknown-equal.bin', 'word/media/unknown-legacy.bin'], $entryNames($rawSummary['blockedEntries']));
        $t->same(['zip-unknown-creator-host-system', 'zip-creator-version-below-version-needed'], $rawSummary['blockedEntries'][2]['diagnostics']);

        $t->same(false, $strict['isValid']);
        $t->same($summary, $strict['creatorHostSystems']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(true, $rawStrict['canInstantiate']);
        $t->same($rawSummary, $rawStrict['creatorHostSystems']);
        $t->contains('unknown-creator-host-systems', implode(',', $rawStrict['diagnostics']));
        $t->contains('creator-version-below-version-needed', implode(',', $rawStrict['diagnostics']));
    },

    'preflights zip DOS hidden system and volume label attributes before media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>DOS attribute metadata</w:p></w:document>',
                'method' => 8,
                'externalAttributes' => 0x81a40020,
            ],
            [
                'name' => 'word/media/hidden-review.txt',
                'data' => 'hidden media bytes require review',
                'method' => 0,
                'externalAttributes' => 0x81a40022,
            ],
            [
                'name' => 'word/media/system-review.txt',
                'data' => 'system media bytes require review',
                'method' => 0,
                'externalAttributes' => 0x81a40024,
            ],
            [
                'name' => 'word/media/VOLUME',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x00000008,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x41ed0010,
            ],
        ]));
        $summary = $package->dosAttributePreflight();

        $t->same(5, $summary['entryCount']);
        $t->same(5, $summary['dosAttributeEntryCount']);
        $t->same(0, $summary['readOnlyEntryCount']);
        $t->same(1, $summary['hiddenEntryCount']);
        $t->same(1, $summary['systemEntryCount']);
        $t->same(1, $summary['volumeLabelEntryCount']);
        $t->same(1, $summary['directoryAttributeEntryCount']);
        $t->same(3, $summary['archiveEntryCount']);
        $t->same(3, $summary['hiddenSystemOrVolumeLabelEntryCount']);
        $t->same('word/media/hidden-review.txt', $summary['hiddenSystemOrVolumeLabelEntries'][0]['name']);
        $t->same(['hidden', 'archive'], $summary['entries'][1]['dosAttributeNames']);
        $t->same(true, $summary['entries'][1]['hasHiddenAttribute']);
        $t->same(false, $summary['entries'][1]['hasSystemAttribute']);
        $t->same(true, $summary['entries'][2]['hasSystemAttribute']);
        $t->same(['volume-label'], $summary['entries'][3]['dosAttributeNames']);
        $t->same(true, $summary['entries'][3]['hasVolumeLabelAttribute']);
        $t->same(['directory'], $summary['entries'][4]['dosAttributeNames']);
        $t->same(true, $package->entry('/word/media/hidden-review.txt')->hasDosHiddenAttribute());
        $t->same(true, $package->entry('/word/media/system-review.txt')->hasDosSystemAttribute());
        $t->same(true, $package->entry('/word/media/VOLUME')->hasDosVolumeLabelAttribute());
        $t->same(true, $package->entry('/word/document.xml')->hasDosArchiveAttribute());
        $t->same(false, $package->entry('/word/document.xml')->hasDosReadOnlyAttribute());
        $t->same(false, $package->strictImportPreflight(2048, 100.0, 2048)['isValid']);
        $t->contains('hidden-system-or-volume-label-entries', implode(',', $package->strictImportPreflight(2048, 100.0, 2048)['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoHiddenSystemOrVolumeLabelEntries());
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(2048, 100.0, 2048));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe DOS attributes</w:p></w:document>',
                'externalAttributes' => 0x81a40020,
            ],
            [
                'name' => 'word/media/',
                'externalAttributes' => 0x41ed0010,
            ],
            [
                'name' => 'word/media/read-only-review.txt',
                'data' => 'read-only archive bit stays review metadata',
                'compressionMethod' => 0,
                'externalAttributes' => 0x81a40021,
            ],
        ]);
        $safeSummary = $safePackage->assertNoHiddenSystemOrVolumeLabelEntries();

        $t->same(3, $safeSummary['dosAttributeEntryCount']);
        $t->same(1, $safeSummary['readOnlyEntryCount']);
        $t->same(0, $safeSummary['hiddenSystemOrVolumeLabelEntryCount']);
        $t->same([], $safeSummary['hiddenSystemOrVolumeLabelEntries']);
        $t->same(true, $safePackage->strictImportPreflight(2048, 100.0, 2048)['isValid']);
        $t->same('read-only archive bit stays review metadata', $safePackage->read('/word/media/read-only-review.txt'));
    },

    'preflights raw zip DOS hidden system and volume label attributes before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw DOS attribute metadata</w:p></w:document>',
                'method' => 8,
                'externalAttributes' => 0x81a40020,
            ],
            [
                'name' => 'word/media/hidden-review.txt',
                'localName' => 'word/media/hidden-local.txt',
                'data' => 'hidden media bytes require raw review',
                'method' => 0,
                'externalAttributes' => 0x81a40022,
            ],
            [
                'name' => 'word/media/system-review.txt',
                'data' => 'system media bytes require raw review',
                'method' => 0,
                'externalAttributes' => 0x81a40024,
            ],
            [
                'name' => 'word/media/VOLUME',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x00000008,
            ],
        ]);

        $summary = ZipPackage::dosAttributePolicyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $t->same(4, $summary['entryCount']);
        $t->same(4, $summary['dosAttributeEntryCount']);
        $t->same(0, $summary['readOnlyEntryCount']);
        $t->same(1, $summary['hiddenEntryCount']);
        $t->same(1, $summary['systemEntryCount']);
        $t->same(1, $summary['volumeLabelEntryCount']);
        $t->same(0, $summary['directoryAttributeEntryCount']);
        $t->same(3, $summary['archiveEntryCount']);
        $t->same(3, $summary['hiddenSystemOrVolumeLabelEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['hidden-system-or-volume-label-entries'], $summary['issues']);

        $hidden = $summary['hiddenSystemOrVolumeLabelEntries'][0];
        $t->same('word/media/hidden-review.txt', $hidden['name']);
        $t->same('word/media/hidden-review.txt', $hidden['rawName']);
        $t->same(['hidden', 'archive'], $hidden['dosAttributeNames']);
        $t->same(true, $hidden['hasHiddenAttribute']);
        $t->same(false, $hidden['hasSystemAttribute']);
        $t->same(false, $hidden['hasVolumeLabelAttribute']);
        $t->same('blocked', $hidden['policy']);
        $t->same(['zip-dos-hidden-attribute'], $hidden['diagnostics']);
        $t->same(['dos-hidden-attribute'], $hidden['issues']);

        $system = $summary['hiddenSystemOrVolumeLabelEntries'][1];
        $t->same('word/media/system-review.txt', $system['name']);
        $t->same(['system', 'archive'], $system['dosAttributeNames']);
        $t->same(['zip-dos-system-attribute'], $system['diagnostics']);
        $t->same(['dos-system-attribute'], $system['issues']);

        $volume = $summary['hiddenSystemOrVolumeLabelEntries'][2];
        $t->same('word/media/VOLUME', $volume['name']);
        $t->same(['volume-label'], $volume['dosAttributeNames']);
        $t->same(['zip-dos-volume-label-attribute'], $volume['diagnostics']);
        $t->same(['dos-volume-label-attribute'], $volume['issues']);

        $t->same($summary, $rawStrict['dosAttributes']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->contains('hidden-system-or-volume-label-entries', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-header-name-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'rejects zip symlink entries before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review.png',
                'data' => '../embeddings/oleObject1.bin',
                'method' => 0,
                'externalAttributes' => 0xa1ff0000,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/review.png',
                'data' => '../embeddings/oleObject1.bin',
                'compressionMethod' => 0,
                'externalAttributes' => 0xa1ff0000,
            ],
        ]));

        $fatPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review.png',
                'data' => 'ordinary external attribute bytes',
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0xa1ff0000,
            ],
        ]));

        $t->same(null, $fatPackage->entry('word/media/review.png')->unixMode());
        $t->same(false, $fatPackage->entry('word/media/review.png')->isUnixSymlink());
    },

    'rejects unix special file zip entries before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-device.bin',
                'data' => 'character device metadata must not become media bytes',
                'method' => 0,
                'externalAttributes' => 0x21b60000,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-fifo.bin',
                'data' => 'fifo metadata must not become media bytes',
                'method' => 0,
                'externalAttributes' => 0x11b60000,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-socket.bin',
                'data' => 'socket metadata must not become media bytes',
                'method' => 0,
                'externalAttributes' => 0xc1ff0000,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-block-device.bin',
                'data' => 'block device metadata must stay blocked',
                'compressionMethod' => 0,
                'externalAttributes' => 0x61b60000,
            ],
        ]));

        $safePackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>regular file type metadata</w:p></w:document>',
                'method' => 8,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x41ed0000,
            ],
            [
                'name' => 'word/media/fat-device-bits.bin',
                'data' => 'non-unix external attribute bits stay metadata only',
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0x21b60000,
            ],
        ]));

        $document = $safePackage->entry('/word/document.xml');
        $directory = $safePackage->entry('/word/media/');
        $fatEntry = $safePackage->entry('/word/media/fat-device-bits.bin');

        $t->same(0x8000, $document->unixFileType());
        $t->same('regular-file', $document->unixFileTypeName());
        $t->same(false, $document->isUnixSpecialFile());
        $t->same(0x4000, $directory->unixFileType());
        $t->same('directory', $directory->unixFileTypeName());
        $t->same(false, $directory->isUnixSpecialFile());
        $t->same(null, $fatEntry->unixFileType());
        $t->same(null, $fatEntry->unixFileTypeName());
        $t->same(false, $fatEntry->isUnixSpecialFile());
        $t->same('non-unix external attribute bits stay metadata only', $safePackage->read('/word/media/fat-device-bits.bin'));
    },

    'rejects zip directory entries with payload before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => 'hidden directory payload',
                'method' => 0,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 8,
            ],
        ])));

        $directoryPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));

        $t->true($directoryPackage->entry('word/media/')->isDirectory());
        $t->same('00000000', $directoryPackage->entry('word/media/')->crc32Hex());
        $t->same('', $directoryPackage->read('word/media/'));
    },

    'rejects zip directory entries with nonzero crc before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'centralCrc' => 0x7b,
                'localCrc' => 0x7b,
            ],
        ])));

        $directoryPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));

        $t->true($directoryPackage->entry('word/media/')->isDirectory());
        $t->same('00000000', $directoryPackage->entry('word/media/')->crc32Hex());
        $t->same('', $directoryPackage->read('word/media/'));
    },

    'rejects zip DOS directory attributes without directory names before package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-folder',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x10,
            ],
        ])));

        $directoryPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x10,
            ],
        ]));

        $entry = $directoryPackage->entry('/word/media/');
        $t->true($entry->isDirectory());
        $t->true($entry->hasDosDirectoryAttribute());
        $t->same('', $directoryPackage->read('/word/media/'));
    },

    'rejects zip unix file type metadata that disagrees with entry names' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x81a40000,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-folder',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x41ed0000,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/',
                'externalAttributes' => 0x81a40000,
            ],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-folder',
                'data' => '',
                'compressionMethod' => 0,
                'externalAttributes' => 0x41ed0000,
            ],
        ]));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>unix type metadata</w:p></w:document>',
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'externalAttributes' => 0x41ed0000,
            ],
        ]);
        $t->same(0x8000, $safePackage->entry('/word/document.xml')->unixFileType());
        $t->same('regular-file', $safePackage->entry('/word/document.xml')->unixFileTypeName());
        $t->same(0x4000, $safePackage->entry('/word/media/')->unixFileType());
        $t->same('directory', $safePackage->entry('/word/media/')->unixFileTypeName());
        $t->same('<w:document><w:p>unix type metadata</w:p></w:document>', $safePackage->read('/word/document.xml'));

        $fatPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-folder',
                'data' => '',
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0x41ed0000,
            ],
        ]));
        $t->same(null, $fatPackage->entry('/word/media/reviewer-folder')->unixFileType());
        $t->same('', $fatPackage->read('/word/media/reviewer-folder'));
    },

    'preflights raw zip external attribute policy before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/media/link-review.png',
                'data' => '../embeddings/oleObject1.bin',
                'method' => 0,
                'externalAttributes' => 0xa1ff0000,
            ],
            [
                'name' => 'word/media/device-review.bin',
                'data' => 'character device metadata must not become media bytes',
                'method' => 0,
                'externalAttributes' => 0x21b60000,
            ],
            [
                'name' => 'word/media/reviewer-folder',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x10,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/fat-highbits.bin',
                'data' => 'non-unix high bits stay metadata only',
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0xa1ff0000,
            ],
        ]);

        $summary = ZipPackage::externalAttributePolicyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 20.0, 1024);

        $t->same(5, $summary['entryCount']);
        $t->same(4, $summary['issueEntryCount']);
        $t->same(1, $summary['symlinkEntryCount']);
        $t->same(1, $summary['unixSpecialFileEntryCount']);
        $t->same(1, $summary['directoryAttributeMismatchEntryCount']);
        $t->same(1, $summary['unixFileTypeMismatchEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'symlink-zip-entries',
            'unix-special-file-entries',
            'directory-attribute-mismatch',
            'unix-file-type-name-mismatch',
        ], $summary['issues']);

        $symlink = $summary['symlinkEntries'][0];
        $t->same('word/media/link-review.png', $symlink['name']);
        $t->same(3, $symlink['madeByHostSystem']);
        $t->same('unix', $symlink['madeByHostSystemName']);
        $t->same(0xa000, $symlink['unixFileType']);
        $t->same('symlink', $symlink['unixFileTypeName']);
        $t->same(true, $symlink['isUnixSymlink']);
        $t->same(false, $symlink['isUnixSpecialFile']);
        $t->same(false, $symlink['hasDirectoryAttributeMismatch']);
        $t->same(false, $symlink['hasUnixFileTypeMismatch']);
        $t->same('blocked', $symlink['policy']);
        $t->same(['zip-unix-symlink-entry'], $symlink['diagnostics']);
        $t->same(['symlink-zip-entry'], $symlink['issues']);

        $special = $summary['unixSpecialFileEntries'][0];
        $t->same('word/media/device-review.bin', $special['name']);
        $t->same(0x2000, $special['unixFileType']);
        $t->same('character-device', $special['unixFileTypeName']);
        $t->same(true, $special['isUnixSpecialFile']);
        $t->same(['zip-unix-special-file-entry'], $special['diagnostics']);
        $t->same(['unix-special-file-entry'], $special['issues']);

        $dosMismatch = $summary['directoryAttributeMismatchEntries'][0];
        $t->same('word/media/reviewer-folder', $dosMismatch['name']);
        $t->same(0x10, $dosMismatch['dosAttributes']);
        $t->same(['directory'], $dosMismatch['dosAttributeNames']);
        $t->same(true, $dosMismatch['hasDosDirectoryAttribute']);
        $t->same(false, $dosMismatch['isDirectory']);
        $t->same(true, $dosMismatch['hasDirectoryAttributeMismatch']);
        $t->same(['zip-dos-directory-attribute-name-mismatch'], $dosMismatch['diagnostics']);

        $unixMismatch = $summary['unixFileTypeMismatchEntries'][0];
        $t->same('word/media/', $unixMismatch['name']);
        $t->same(true, $unixMismatch['isDirectory']);
        $t->same(0x8000, $unixMismatch['unixFileType']);
        $t->same('regular-file', $unixMismatch['unixFileTypeName']);
        $t->same(true, $unixMismatch['hasUnixFileTypeMismatch']);
        $t->same(['zip-unix-file-type-name-mismatch'], $unixMismatch['diagnostics']);

        $fatEntry = $summary['entries'][4];
        $t->same('word/media/fat-highbits.bin', $fatEntry['name']);
        $t->same(0, $fatEntry['madeByHostSystem']);
        $t->same('ms-dos-fat', $fatEntry['madeByHostSystemName']);
        $t->same(null, $fatEntry['unixMode']);
        $t->same(null, $fatEntry['unixFileType']);
        $t->same(null, $fatEntry['unixFileTypeName']);
        $t->same(false, $fatEntry['isUnixSymlink']);
        $t->same(false, $fatEntry['isUnixSpecialFile']);
        $t->same('metadata', $fatEntry['policy']);
        $t->same([], $fatEntry['diagnostics']);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->same($summary, $rawStrict['externalAttributes']);
        $t->contains('symlink-zip-entries', implode(',', $rawStrict['diagnostics']));
        $t->contains('unix-special-file-entries', implode(',', $rawStrict['diagnostics']));
        $t->contains('directory-attribute-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->contains('unix-file-type-name-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
    },

    'preflights zip file-directory path collisions before media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $collisionPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>path hierarchy preflight</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media',
                'data' => 'file bytes shadowing a media directory path',
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
            ],
            [
                'name' => 'word/media/thumbnails/thumb.png',
                'data' => "thumbnail reviewer attachment placeholder\n",
                'method' => 0,
            ],
        ]));
        $summary = $collisionPackage->pathHierarchyPreflight();

        $t->same(5, $summary['entryCount']);
        $t->same(4, $summary['collisionEntryCount']);
        $t->same('word/document.xml', $summary['entries'][0]['name']);
        $t->same(false, $summary['entries'][0]['hasPathHierarchyCollision']);
        $t->same([], $summary['entries'][0]['issues']);
        $t->same('word/media', $summary['entries'][1]['name']);
        $t->same('word/media', $summary['entries'][1]['path']);
        $t->same('word/media/', $summary['entries'][1]['samePathDirectoryName']);
        $t->same([
            'word/media/review.png',
            'word/media/thumbnails/thumb.png',
        ], $summary['entries'][1]['descendantEntryNames']);
        $t->same(['file-directory-same-path', 'file-used-as-directory'], $summary['entries'][1]['issues']);
        $t->same('word/media/', $summary['entries'][2]['name']);
        $t->same('word/media', $summary['entries'][2]['samePathFileName']);
        $t->same(['file-directory-same-path'], $summary['entries'][2]['issues']);
        $t->same(['word/media'], $summary['entries'][3]['ancestorFileNames']);
        $t->same(['ancestor-file-entry'], $summary['entries'][3]['issues']);
        $t->same(['word/media'], $summary['entries'][4]['ancestorFileNames']);
        $t->same(['ancestor-file-entry'], $summary['entries'][4]['issues']);
        $t->same('word/media', $summary['collisionEntries'][0]['name']);
        $t->same('word/media/', $summary['collisionEntries'][1]['name']);
        $t->throws(\RuntimeException::class, static fn (): array => $collisionPackage->assertNoPathHierarchyCollisions());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe hierarchy</w:p></w:document>',
            ],
            [
                'name' => 'word/media/',
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoPathHierarchyCollisions();

        $t->same(3, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['collisionEntryCount']);
        $t->same([], $safeSummary['collisionEntries']);
        $t->same('word/media/review.png', $safeSummary['entries'][2]['name']);
        $t->same([], $safeSummary['entries'][2]['ancestorFileNames']);
        $t->same(false, $safeSummary['entries'][2]['hasPathHierarchyCollision']);
        $t->same("PNG reviewer attachment placeholder\n", $safePackage->read('/word/media/review.png'));
    },

    'preflights raw central directory path hierarchy collisions before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/other.xml',
                'data' => '<w:document><w:p>raw hierarchy local spoof</w:p></w:document>',
                'method' => 0,
            ],
            [
                'name' => 'word/media',
                'data' => 'file shadows package media directory',
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
            ],
        ]);

        $summary = ZipPackage::centralDirectoryPathHierarchyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 20.0, 2048);

        $t->same(4, $summary['entryCount']);
        $t->same(3, $summary['collisionEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['path-hierarchy-collisions'], $summary['issues']);
        $t->same('word/document.xml', $summary['entries'][0]['name']);
        $t->same(false, $summary['entries'][0]['hasPathHierarchyCollision']);
        $t->same('word/media', $summary['entries'][1]['name']);
        $t->same('word/media/', $summary['entries'][1]['samePathDirectoryName']);
        $t->same(['word/media/review.png'], $summary['entries'][1]['descendantEntryNames']);
        $t->same(['file-directory-same-path', 'file-used-as-directory'], $summary['entries'][1]['issues']);
        $t->same('word/media/', $summary['entries'][2]['name']);
        $t->same('word/media', $summary['entries'][2]['samePathFileName']);
        $t->same(['file-directory-same-path'], $summary['entries'][2]['issues']);
        $t->same(['word/media'], $summary['entries'][3]['ancestorFileNames']);
        $t->same(['ancestor-file-entry'], $summary['entries'][3]['issues']);
        $t->same($summary, $rawStrict['centralDirectoryPathHierarchy']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->contains('central-directory-path-hierarchy-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('path-hierarchy-collisions', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-header-name-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights case-insensitive zip entry name collisions before media handoff' => static function (TestRunner $t): void {
        $collisionPackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>case collision preflight</w:p></w:document>',
            ],
            [
                'name' => 'word/media/Review.PNG',
                'data' => "first reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "second reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $summary = $collisionPackage->caseInsensitiveNamePreflight();

        $t->same(3, $summary['entryCount']);
        $t->same(1, $summary['collisionGroupCount']);
        $t->same(2, $summary['collisionEntryCount']);
        $t->same('word/media/review.png', $summary['collisionGroups'][0]['caseFoldKey']);
        $t->same(['word/media/Review.PNG', 'word/media/review.png'], $summary['collisionGroups'][0]['entryNames']);
        $t->same('word/document.xml', $summary['entries'][0]['name']);
        $t->same(false, $summary['entries'][0]['hasCaseInsensitiveNameCollision']);
        $t->same([], $summary['entries'][0]['issues']);
        $t->same('word/media/Review.PNG', $summary['entries'][1]['name']);
        $t->same('word/media/review.png', $summary['entries'][1]['caseFoldKey']);
        $t->same(['word/media/Review.PNG', 'word/media/review.png'], $summary['entries'][1]['equivalentEntryNames']);
        $t->same(true, $summary['entries'][1]['hasCaseInsensitiveNameCollision']);
        $t->same(['case-insensitive-name-collision'], $summary['entries'][1]['issues']);
        $t->same('word/media/review.png', $summary['entries'][2]['name']);
        $t->same(['case-insensitive-name-collision'], $summary['entries'][2]['issues']);
        $t->same('word/media/Review.PNG', $summary['collisionEntries'][0]['name']);
        $t->same('word/media/review.png', $summary['collisionEntries'][1]['name']);
        $t->same("first reviewer attachment placeholder\n", $collisionPackage->read('/word/media/Review.PNG'));
        $t->same("second reviewer attachment placeholder\n", $collisionPackage->read('/word/media/review.png'));
        $strictSummary = $collisionPackage->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strictSummary['isValid']);
        $t->contains('case-insensitive-name-collisions', implode(',', $strictSummary['diagnostics']));
        $t->same(2, $strictSummary['caseInsensitiveNames']['collisionEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $collisionPackage->assertNoCaseInsensitiveNameCollisions());
        $t->throws(\RuntimeException::class, static fn (): array => $collisionPackage->assertStrictImportable(4096, 100.0, 4096));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe case names</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoCaseInsensitiveNameCollisions();
        $t->same(2, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['collisionGroupCount']);
        $t->same(0, $safeSummary['collisionEntryCount']);
        $t->same([], $safeSummary['collisionGroups']);
        $t->same([], $safeSummary['collisionEntries']);
        $t->same(true, $safePackage->strictImportPreflight(4096, 100.0, 4096)['isValid']);
    },

    'preflights unicode-normalized zip entry name collisions before media handoff' => static function (TestRunner $t): void {
        $precomposedName = "word/media/Caf\u{00e9}.PNG";
        $decomposedName = "word/media/cafe\u{0301}.png";
        $collisionPackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>unicode name collision preflight</w:p></w:document>',
            ],
            [
                'name' => $precomposedName,
                'data' => "precomposed reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => $decomposedName,
                'data' => "decomposed reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $summary = $collisionPackage->caseInsensitiveNamePreflight();

        $t->same(3, $summary['entryCount']);
        $t->same(1, $summary['collisionGroupCount']);
        $t->same(2, $summary['collisionEntryCount']);
        $t->same("word/media/caf\u{00e9}.png", $summary['collisionGroups'][0]['caseFoldKey']);
        $t->same([$precomposedName, $decomposedName], $summary['collisionGroups'][0]['entryNames']);
        $t->same($precomposedName, $summary['entries'][1]['name']);
        $t->same("word/media/caf\u{00e9}.png", $summary['entries'][1]['caseFoldKey']);
        $t->same([$precomposedName, $decomposedName], $summary['entries'][1]['equivalentEntryNames']);
        $t->same(true, $summary['entries'][1]['hasCaseInsensitiveNameCollision']);
        $t->same(['case-insensitive-name-collision'], $summary['entries'][1]['issues']);
        $t->same($decomposedName, $summary['entries'][2]['name']);
        $t->same(['case-insensitive-name-collision'], $summary['entries'][2]['issues']);
        $t->same("precomposed reviewer attachment placeholder\n", $collisionPackage->read('/' . $precomposedName));
        $t->same("decomposed reviewer attachment placeholder\n", $collisionPackage->read('/' . $decomposedName));

        $strictSummary = $collisionPackage->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strictSummary['isValid']);
        $t->contains('case-insensitive-name-collisions', implode(',', $strictSummary['diagnostics']));
        $t->same(2, $strictSummary['caseInsensitiveNames']['collisionEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $collisionPackage->assertNoCaseInsensitiveNameCollisions());
        $t->throws(\RuntimeException::class, static fn (): array => $collisionPackage->assertStrictImportable(4096, 100.0, 4096));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe unicode names</w:p></w:document>',
            ],
            [
                'name' => 'word/media/cafe.png',
                'data' => "ascii reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => "word/media/caf\u{00e9}.png",
                'data' => "accented reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoCaseInsensitiveNameCollisions();
        $t->same(3, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['collisionGroupCount']);
        $t->same(0, $safeSummary['collisionEntryCount']);
        $t->same(true, $safePackage->strictImportPreflight(4096, 100.0, 4096)['isValid']);
    },

    'preflights legacy raw zip name collisions before strict media handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $firstName = 'word/media/review-one.png';
        $secondName = 'word/media/review-two.png';
        $collisionPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw name collision review</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => $rawName,
                'localName' => $rawName,
                'data' => "first reviewer attachment placeholder\n",
                'method' => 0,
                'flags' => 0,
                'localExtra' => $buildUnicodeExtra(0x7075, $rawName, $firstName),
                'centralExtra' => $buildUnicodeExtra(0x7075, $rawName, $firstName),
            ],
            [
                'name' => $rawName,
                'localName' => $rawName,
                'data' => "second reviewer attachment placeholder\n",
                'method' => 0,
                'flags' => 0,
                'localExtra' => $buildUnicodeExtra(0x7075, $rawName, $secondName),
                'centralExtra' => $buildUnicodeExtra(0x7075, $rawName, $secondName),
            ],
        ]));
        $summary = $collisionPackage->rawNamePreflight();

        $t->same([
            'word/document.xml',
            $firstName,
            $secondName,
        ], $collisionPackage->names());
        $t->same(3, $summary['entryCount']);
        $t->same(1, $summary['collisionGroupCount']);
        $t->same(2, $summary['collisionEntryCount']);
        $t->same($rawName, $summary['collisionGroups'][0]['rawName']);
        $t->same(bin2hex($rawName), $summary['collisionGroups'][0]['rawNameHex']);
        $t->same([$firstName, $secondName], $summary['collisionGroups'][0]['entryNames']);
        $t->same('word/document.xml', $summary['entries'][0]['name']);
        $t->same(false, $summary['entries'][0]['hasRawNameCollision']);
        $t->same([], $summary['entries'][0]['issues']);
        $t->same($firstName, $summary['entries'][1]['name']);
        $t->same($rawName, $summary['entries'][1]['rawName']);
        $t->same(bin2hex($rawName), $summary['entries'][1]['rawNameHex']);
        $t->same([$firstName, $secondName], $summary['entries'][1]['equivalentEntryNames']);
        $t->same(true, $summary['entries'][1]['hasRawNameCollision']);
        $t->same(['raw-name-collision', 'raw-name-decoded-value-differs', 'raw-name-info-zip-unicode-path'], $summary['entries'][1]['issues']);
        $t->same($secondName, $summary['entries'][2]['name']);
        $t->same(['raw-name-collision', 'raw-name-decoded-value-differs', 'raw-name-info-zip-unicode-path'], $summary['entries'][2]['issues']);
        $t->same("first reviewer attachment placeholder\n", $collisionPackage->read('/' . $firstName));
        $t->same("second reviewer attachment placeholder\n", $collisionPackage->read('/' . $secondName));

        $strictSummary = $collisionPackage->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strictSummary['isValid']);
        $t->same(['raw-name-collisions', 'raw-name-provenance-review-entries'], $strictSummary['diagnostics']);
        $t->same(2, $strictSummary['rawNames']['collisionEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $collisionPackage->assertNoRawNameCollisions());
        $t->throws(\RuntimeException::class, static fn (): array => $collisionPackage->assertStrictImportable(4096, 100.0, 4096));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe raw names</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review-one.png',
                'data' => "first reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/review-two.png',
                'data' => "second reviewer attachment placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoRawNameCollisions();
        $t->same(3, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['collisionGroupCount']);
        $t->same(0, $safeSummary['collisionEntryCount']);
        $t->same(true, $safePackage->strictImportPreflight(4096, 100.0, 4096)['isValid']);
    },

    'preflights raw zip name provenance before media review handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $cp437RawName = "word/media/caf\x82.png";
        $cp437Name = "word/media/caf\u{00e9}.png";
        $unicodeRawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $unicodePathExtra = $buildUnicodeExtra(0x7075, $unicodeRawName, $unicodeName);
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw name provenance review</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => $cp437RawName,
                'data' => "cp437 media placeholder\n",
                'method' => 0,
                'flags' => 0,
            ],
            [
                'name' => $unicodeRawName,
                'localName' => $unicodeRawName,
                'data' => "unicode path media placeholder\n",
                'method' => 0,
                'flags' => 0,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra,
            ],
            [
                'name' => 'word/media/plain.txt',
                'data' => "plain utf8 media placeholder\n",
                'method' => 0,
            ],
        ]));

        $summary = $package->rawNamePreflight();

        $t->same([
            'word/document.xml',
            $cp437Name,
            $unicodeName,
            'word/media/plain.txt',
        ], $package->names());
        $t->same(4, $summary['entryCount']);
        $t->same(0, $summary['collisionGroupCount']);
        $t->same(0, $summary['collisionEntryCount']);
        $t->same(2, $summary['provenanceEntryCount']);
        $t->same(1, $summary['legacyEncodedNameEntryCount']);
        $t->same(1, $summary['unicodePathExtraEntryCount']);
        $t->same(2, $summary['decodedNameDiffersFromRawNameEntryCount']);
        $t->same($cp437Name, $summary['provenanceEntries'][0]['name']);
        $t->same($cp437RawName, $summary['provenanceEntries'][0]['rawName']);
        $t->same('cp437', $summary['provenanceEntries'][0]['nameEncoding']);
        $t->same(false, $summary['provenanceEntries'][0]['rawNameMatchesDecodedName']);
        $t->same(true, $summary['provenanceEntries'][0]['usesLegacyNameEncoding']);
        $t->same(false, $summary['provenanceEntries'][0]['usesUnicodePathExtraField']);
        $t->same(true, $summary['provenanceEntries'][0]['hasRawNameProvenance']);
        $t->same(['raw-name-decoded-value-differs', 'raw-name-legacy-encoding'], $summary['provenanceEntries'][0]['issues']);
        $t->same($unicodeName, $summary['provenanceEntries'][1]['name']);
        $t->same($unicodeRawName, $summary['provenanceEntries'][1]['rawName']);
        $t->same('info-zip-unicode-path', $summary['provenanceEntries'][1]['nameEncoding']);
        $t->same(false, $summary['provenanceEntries'][1]['rawNameMatchesDecodedName']);
        $t->same(false, $summary['provenanceEntries'][1]['usesLegacyNameEncoding']);
        $t->same(true, $summary['provenanceEntries'][1]['usesUnicodePathExtraField']);
        $t->same(['raw-name-decoded-value-differs', 'raw-name-info-zip-unicode-path'], $summary['provenanceEntries'][1]['issues']);
        $t->same(false, $summary['entries'][0]['hasRawNameProvenance']);
        $t->same('utf-8', $summary['entries'][0]['nameEncoding']);
        $t->same(true, $summary['entries'][3]['rawNameMatchesDecodedName']);
        $t->same(false, $summary['entries'][3]['hasRawNameProvenance']);
        $t->same($summary, $package->strictImportPreflight(4096, 100.0, 4096)['rawNames']);
        $t->same("cp437 media placeholder\n", $package->read('/' . $cp437Name));
        $t->same("unicode path media placeholder\n", $package->read('/' . $unicodeName));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoRawNameProvenanceReviewEntries());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>plain raw name provenance</w:p></w:document>',
            ],
            [
                'name' => 'word/media/plain.txt',
                'data' => "plain media placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoRawNameProvenanceReviewEntries();
        $t->same(2, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['provenanceEntryCount']);
        $t->same(0, $safeSummary['legacyEncodedNameEntryCount']);
        $t->same(0, $safeSummary['unicodePathExtraEntryCount']);
        $t->same([], $safeSummary['provenanceEntries']);
        $t->same(true, $safePackage->strictImportPreflight(4096, 100.0, 4096)['isValid']);
    },

    'blocks raw zip name provenance in strict import preflight' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $cp437RawName = "word/media/caf\x82.txt";
        $cp437Name = "word/media/caf\u{00e9}.txt";
        $unicodeRawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.txt";
        $unicodePathExtra = $buildUnicodeExtra(0x7075, $unicodeRawName, $unicodeName);
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $cp437RawName,
                'data' => "legacy encoded reviewer note\n",
                'method' => 0,
                'flags' => 0,
            ],
            [
                'name' => $unicodeRawName,
                'localName' => $unicodeRawName,
                'data' => "unicode path reviewer note\n",
                'method' => 0,
                'flags' => 0,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra,
            ],
        ]));

        $summary = $package->strictImportPreflight(2048, 100.0, 2048);

        $t->same(false, $summary['isValid']);
        $t->same(['raw-name-provenance-review-entries'], $summary['diagnostics']);
        $t->same(2, $summary['rawNames']['provenanceEntryCount']);
        $t->same(1, $summary['rawNames']['legacyEncodedNameEntryCount']);
        $t->same(1, $summary['rawNames']['unicodePathExtraEntryCount']);
        $t->same(2, $summary['rawNames']['decodedNameDiffersFromRawNameEntryCount']);
        $t->same($cp437Name, $summary['rawNames']['provenanceEntries'][0]['name']);
        $t->same(['raw-name-decoded-value-differs', 'raw-name-legacy-encoding'], $summary['rawNames']['provenanceEntries'][0]['issues']);
        $t->same($unicodeName, $summary['rawNames']['provenanceEntries'][1]['name']);
        $t->same(['raw-name-decoded-value-differs', 'raw-name-info-zip-unicode-path'], $summary['rawNames']['provenanceEntries'][1]['issues']);
        $t->same("legacy encoded reviewer note\n", $package->read('/' . $cp437Name));
        $t->same("unicode path reviewer note\n", $package->read('/' . $unicodeName));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(2048, 100.0, 2048));
    },

    'preflights zip entry name hygiene before office package media handoff' => static function (TestRunner $t): void {
        $reviewPackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>name hygiene preflight</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review image.png',
                'data' => "safe internal-space attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/ leading.png',
                'data' => "leading-space attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/review.png ',
                'data' => "trailing-space attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/trailing./review.png',
                'data' => "trailing-dot segment attachment placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $summary = $reviewPackage->nameHygienePreflight();

        $t->same(5, $summary['entryCount']);
        $t->same(3, $summary['reviewEntryCount']);
        $t->same(2, $summary['leadingOrTrailingWhitespaceEntryCount']);
        $t->same(1, $summary['trailingDotSegmentEntryCount']);
        $t->same(0, $summary['windowsReservedNameEntryCount']);
        $t->same(0, $summary['windowsAlternateDataStreamEntryCount']);
        $t->same('word/media/review image.png', $summary['entries'][1]['name']);
        $t->same(['word', 'media', 'review image.png'], $summary['entries'][1]['segments']);
        $t->same(false, $summary['entries'][1]['hasNameHygieneIssue']);
        $t->same([], $summary['entries'][1]['issues']);
        $t->same([], $summary['entries'][1]['flaggedSegments']);
        $t->same('word/media/ leading.png', $summary['reviewEntries'][0]['name']);
        $t->same(['segment-leading-or-trailing-whitespace'], $summary['reviewEntries'][0]['issues']);
        $t->same(2, $summary['reviewEntries'][0]['flaggedSegments'][0]['index']);
        $t->same(' leading.png', $summary['reviewEntries'][0]['flaggedSegments'][0]['segment']);
        $t->same(['segment-leading-or-trailing-whitespace'], $summary['reviewEntries'][0]['flaggedSegments'][0]['issues']);
        $t->same('word/media/review.png ', $summary['reviewEntries'][1]['name']);
        $t->same('review.png ', $summary['reviewEntries'][1]['flaggedSegments'][0]['segment']);
        $t->same(['segment-leading-or-trailing-whitespace'], $summary['reviewEntries'][1]['issues']);
        $t->same('word/media/trailing./review.png', $summary['reviewEntries'][2]['name']);
        $t->same('trailing.', $summary['reviewEntries'][2]['flaggedSegments'][0]['segment']);
        $t->same(['segment-trailing-dot'], $summary['reviewEntries'][2]['issues']);
        $t->same("safe internal-space attachment placeholder\n", $reviewPackage->read('/word/media/review image.png'));
        $t->same("trailing-space attachment placeholder\n", $reviewPackage->read('/word/media/review.png '));

        $strictSummary = $reviewPackage->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strictSummary['isValid']);
        $t->same(['name-hygiene-review-entries'], $strictSummary['diagnostics']);
        $t->same(3, $strictSummary['nameHygiene']['reviewEntryCount']);
        $t->same(2, $strictSummary['nameHygiene']['leadingOrTrailingWhitespaceEntryCount']);
        $t->same(1, $strictSummary['nameHygiene']['trailingDotSegmentEntryCount']);
        $t->same(0, $strictSummary['nameHygiene']['windowsReservedNameEntryCount']);
        $t->same(0, $strictSummary['nameHygiene']['windowsAlternateDataStreamEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertNoNameHygieneReviewEntries());
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertStrictImportable(4096, 100.0, 4096));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe name hygiene</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review image.png',
                'data' => "safe internal-space attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/source-diagram.svg',
                'data' => "<svg />\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoNameHygieneReviewEntries();
        $t->same(3, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['reviewEntryCount']);
        $t->same(0, $safeSummary['leadingOrTrailingWhitespaceEntryCount']);
        $t->same(0, $safeSummary['trailingDotSegmentEntryCount']);
        $t->same(0, $safeSummary['windowsReservedNameEntryCount']);
        $t->same(0, $safeSummary['windowsAlternateDataStreamEntryCount']);
        $t->same([], $safeSummary['reviewEntries']);
        $t->same(true, $safePackage->strictImportPreflight(4096, 100.0, 4096)['isValid']);
    },

    'preflights windows reserved zip entry names before office package media handoff' => static function (TestRunner $t): void {
        $reviewPackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>windows name hygiene preflight</w:p></w:document>',
            ],
            [
                'name' => 'word/media/CON',
                'data' => "windows device attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/aux.txt',
                'data' => "windows device extension placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/review.png:Zone.Identifier',
                'data' => "alternate data stream attachment placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/source-image.png',
                'data' => "safe source image placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $summary = $reviewPackage->nameHygienePreflight();

        $t->same(5, $summary['entryCount']);
        $t->same(3, $summary['reviewEntryCount']);
        $t->same(0, $summary['leadingOrTrailingWhitespaceEntryCount']);
        $t->same(0, $summary['trailingDotSegmentEntryCount']);
        $t->same(2, $summary['windowsReservedNameEntryCount']);
        $t->same(1, $summary['windowsAlternateDataStreamEntryCount']);
        $t->same('word/media/CON', $summary['entries'][1]['name']);
        $t->same(['word', 'media', 'CON'], $summary['entries'][1]['segments']);
        $t->same(true, $summary['entries'][1]['hasNameHygieneIssue']);
        $t->same(['segment-windows-reserved-name'], $summary['entries'][1]['issues']);
        $t->same('word/media/source-image.png', $summary['entries'][4]['name']);
        $t->same(false, $summary['entries'][4]['hasNameHygieneIssue']);
        $t->same([], $summary['entries'][4]['issues']);
        $t->same([], $summary['entries'][4]['flaggedSegments']);
        $t->same('word/media/CON', $summary['reviewEntries'][0]['name']);
        $t->same(2, $summary['reviewEntries'][0]['flaggedSegments'][0]['index']);
        $t->same('CON', $summary['reviewEntries'][0]['flaggedSegments'][0]['segment']);
        $t->same(['segment-windows-reserved-name'], $summary['reviewEntries'][0]['flaggedSegments'][0]['issues']);
        $t->same(['segment-windows-reserved-name'], $summary['reviewEntries'][0]['issues']);
        $t->same('word/media/aux.txt', $summary['reviewEntries'][1]['name']);
        $t->same('aux.txt', $summary['reviewEntries'][1]['flaggedSegments'][0]['segment']);
        $t->same(['segment-windows-reserved-name'], $summary['reviewEntries'][1]['issues']);
        $t->same('word/media/review.png:Zone.Identifier', $summary['reviewEntries'][2]['name']);
        $t->same('review.png:Zone.Identifier', $summary['reviewEntries'][2]['flaggedSegments'][0]['segment']);
        $t->same(['segment-windows-alternate-data-stream'], $summary['reviewEntries'][2]['issues']);
        $t->same("windows device attachment placeholder\n", $reviewPackage->read('/word/media/CON'));
        $t->same("windows device extension placeholder\n", $reviewPackage->read('/word/media/aux.txt'));
        $t->same("alternate data stream attachment placeholder\n", $reviewPackage->read('/word/media/review.png:Zone.Identifier'));
        $t->same("safe source image placeholder\n", $reviewPackage->read('/word/media/source-image.png'));

        $strictSummary = $reviewPackage->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strictSummary['isValid']);
        $t->same(['name-hygiene-review-entries'], $strictSummary['diagnostics']);
        $t->same(3, $strictSummary['nameHygiene']['reviewEntryCount']);
        $t->same(2, $strictSummary['nameHygiene']['windowsReservedNameEntryCount']);
        $t->same(1, $strictSummary['nameHygiene']['windowsAlternateDataStreamEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertNoNameHygieneReviewEntries());
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertStrictImportable(4096, 100.0, 4096));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>windows safe name hygiene</w:p></w:document>',
            ],
            [
                'name' => 'word/media/source-image.png',
                'data' => "safe source image placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/composition.png',
                'data' => "safe composition placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoNameHygieneReviewEntries();
        $t->same(3, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['reviewEntryCount']);
        $t->same(0, $safeSummary['windowsReservedNameEntryCount']);
        $t->same(0, $safeSummary['windowsAlternateDataStreamEntryCount']);
        $t->same([], $safeSummary['reviewEntries']);
        $t->same(true, $safePackage->strictImportPreflight(4096, 100.0, 4096)['isValid']);
    },

    'preflights unicode format controls in zip entry names before media handoff' => static function (TestRunner $t): void {
        $rightToLeftOverrideName = "word/media/review\u{202e}gnp.txt";
        $zeroWidthJoinerName = "word/media/vector\u{200d}icon.svg";
        $leftToRightMarkName = "word/media/source\u{200e}.png";
        $reviewPackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>unicode format control name preflight</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review-image.png',
                'data' => "safe source image placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => $rightToLeftOverrideName,
                'data' => "right-to-left override media placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => $zeroWidthJoinerName,
                'data' => "zero-width joiner media placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => $leftToRightMarkName,
                'data' => "left-to-right mark media placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $summary = $reviewPackage->nameHygienePreflight();

        $t->same(5, $summary['entryCount']);
        $t->same(3, $summary['reviewEntryCount']);
        $t->same(0, $summary['leadingOrTrailingWhitespaceEntryCount']);
        $t->same(0, $summary['trailingDotSegmentEntryCount']);
        $t->same(0, $summary['windowsReservedNameEntryCount']);
        $t->same(0, $summary['windowsAlternateDataStreamEntryCount']);
        $t->same(3, $summary['unicodeFormatControlEntryCount']);
        $t->same(2, $summary['unicodeBidiControlEntryCount']);
        $t->same(false, $summary['entries'][1]['hasNameHygieneIssue']);
        $t->same([], $summary['entries'][1]['flaggedSegments']);

        $t->same($rightToLeftOverrideName, $summary['reviewEntries'][0]['name']);
        $t->same(['segment-unicode-format-control', 'segment-bidi-format-control'], $summary['reviewEntries'][0]['issues']);
        $t->same('review' . "\u{202e}" . 'gnp.txt', $summary['reviewEntries'][0]['flaggedSegments'][0]['segment']);
        $t->same(['right-to-left-override'], $summary['reviewEntries'][0]['flaggedSegments'][0]['unicodeFormatControlNames']);
        $t->same(['right-to-left-override'], $summary['reviewEntries'][0]['flaggedSegments'][0]['bidiControlNames']);

        $t->same($zeroWidthJoinerName, $summary['reviewEntries'][1]['name']);
        $t->same(['segment-unicode-format-control'], $summary['reviewEntries'][1]['issues']);
        $t->same(['zero-width-joiner'], $summary['reviewEntries'][1]['flaggedSegments'][0]['unicodeFormatControlNames']);
        $t->same([], $summary['reviewEntries'][1]['flaggedSegments'][0]['bidiControlNames']);

        $t->same($leftToRightMarkName, $summary['reviewEntries'][2]['name']);
        $t->same(['segment-unicode-format-control', 'segment-bidi-format-control'], $summary['reviewEntries'][2]['issues']);
        $t->same(['left-to-right-mark'], $summary['reviewEntries'][2]['flaggedSegments'][0]['unicodeFormatControlNames']);
        $t->same(['left-to-right-mark'], $summary['reviewEntries'][2]['flaggedSegments'][0]['bidiControlNames']);
        $t->same("right-to-left override media placeholder\n", $reviewPackage->read('/' . $rightToLeftOverrideName));

        $strictSummary = $reviewPackage->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strictSummary['isValid']);
        $t->same(['name-hygiene-review-entries'], $strictSummary['diagnostics']);
        $t->same(3, $strictSummary['nameHygiene']['unicodeFormatControlEntryCount']);
        $t->same(2, $strictSummary['nameHygiene']['unicodeBidiControlEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertNoNameHygieneReviewEntries());
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertStrictImportable(4096, 100.0, 4096));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>unicode safe name hygiene</w:p></w:document>',
            ],
            [
                'name' => 'word/media/source-image.png',
                'data' => "safe source image placeholder\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoNameHygieneReviewEntries();
        $t->same(2, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['unicodeFormatControlEntryCount']);
        $t->same(0, $safeSummary['unicodeBidiControlEntryCount']);
        $t->same([], $safeSummary['reviewEntries']);
        $t->same(true, $safePackage->strictImportPreflight(4096, 100.0, 4096)['isValid']);
    },

    'preflights zip platform metadata sidecars before office package media handoff' => static function (TestRunner $t): void {
        $reviewPackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>platform metadata preflight</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "visible reviewer image placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => '__MACOSX/',
                'data' => '',
                'compressionMethod' => 0,
            ],
            [
                'name' => '__MACOSX/word/media/._review.png',
                'data' => "appledouble resource fork placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/._review.png',
                'data' => "resource fork beside visible image\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/.DS_Store',
                'data' => "finder metadata should not import as document media\n",
                'compressionMethod' => 0,
            ],
        ]);
        $summary = $reviewPackage->platformMetadataPreflight();

        $t->same(6, $summary['entryCount']);
        $t->same(4, $summary['platformMetadataEntryCount']);
        $t->same(2, $summary['macosSidecarEntryCount']);
        $t->same(2, $summary['appleDoubleEntryCount']);
        $t->same(1, $summary['finderMetadataEntryCount']);
        $t->same(0, $summary['windowsSidecarEntryCount']);
        $t->same(0, $summary['windowsThumbnailCacheEntryCount']);
        $t->same(0, $summary['windowsDesktopIniEntryCount']);
        $t->same('word/media/review.png', $summary['entries'][1]['name']);
        $t->same(null, $summary['entries'][1]['platform']);
        $t->same(false, $summary['entries'][1]['isMacosSidecar']);
        $t->same(false, $summary['entries'][1]['isAppleDouble']);
        $t->same(false, $summary['entries'][1]['isFinderMetadata']);
        $t->same(false, $summary['entries'][1]['isWindowsSidecar']);
        $t->same([], $summary['entries'][1]['issues']);
        $t->same('__MACOSX/', $summary['platformMetadataEntries'][0]['name']);
        $t->same('__MACOSX', $summary['platformMetadataEntries'][0]['path']);
        $t->same(true, $summary['platformMetadataEntries'][0]['isDirectory']);
        $t->same('macos', $summary['platformMetadataEntries'][0]['platform']);
        $t->same(['macos-sidecar-entry'], $summary['platformMetadataEntries'][0]['issues']);
        $t->same('__MACOSX/word/media/._review.png', $summary['platformMetadataEntries'][1]['name']);
        $t->same(true, $summary['platformMetadataEntries'][1]['isMacosSidecar']);
        $t->same(true, $summary['platformMetadataEntries'][1]['isAppleDouble']);
        $t->same(false, $summary['platformMetadataEntries'][1]['isFinderMetadata']);
        $t->same(['macos-sidecar-entry', 'appledouble-resource-entry'], $summary['platformMetadataEntries'][1]['issues']);
        $t->same('word/media/._review.png', $summary['platformMetadataEntries'][2]['name']);
        $t->same(false, $summary['platformMetadataEntries'][2]['isMacosSidecar']);
        $t->same(true, $summary['platformMetadataEntries'][2]['isAppleDouble']);
        $t->same(['appledouble-resource-entry'], $summary['platformMetadataEntries'][2]['issues']);
        $t->same('word/media/.DS_Store', $summary['platformMetadataEntries'][3]['name']);
        $t->same(false, $summary['platformMetadataEntries'][3]['isAppleDouble']);
        $t->same(true, $summary['platformMetadataEntries'][3]['isFinderMetadata']);
        $t->same(['finder-metadata-entry'], $summary['platformMetadataEntries'][3]['issues']);
        $t->same("visible reviewer image placeholder\n", $reviewPackage->read('/word/media/review.png'));
        $t->same("resource fork beside visible image\n", $reviewPackage->read('/word/media/._review.png'));

        $strictSummary = $reviewPackage->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strictSummary['isValid']);
        $t->same(['platform-metadata-entries'], $strictSummary['diagnostics']);
        $t->same(4, $strictSummary['platformMetadata']['platformMetadataEntryCount']);
        $t->same(2, $strictSummary['platformMetadata']['macosSidecarEntryCount']);
        $t->same(2, $strictSummary['platformMetadata']['appleDoubleEntryCount']);
        $t->same(1, $strictSummary['platformMetadata']['finderMetadataEntryCount']);
        $t->same(0, $strictSummary['platformMetadata']['windowsSidecarEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertNoPlatformMetadataEntries());
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertStrictImportable(4096, 100.0, 4096));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe platform metadata</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "visible reviewer image placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/source-diagram.svg',
                'data' => "<svg />\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoPlatformMetadataEntries();
        $t->same(3, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['platformMetadataEntryCount']);
        $t->same(0, $safeSummary['macosSidecarEntryCount']);
        $t->same(0, $safeSummary['appleDoubleEntryCount']);
        $t->same(0, $safeSummary['finderMetadataEntryCount']);
        $t->same(0, $safeSummary['windowsSidecarEntryCount']);
        $t->same(0, $safeSummary['windowsThumbnailCacheEntryCount']);
        $t->same(0, $safeSummary['windowsDesktopIniEntryCount']);
        $t->same([], $safeSummary['platformMetadataEntries']);
        $strictClean = $safePackage->assertStrictImportable(4096, 100.0, 4096);
        $t->same(true, $strictClean['isValid']);
        $t->same(0, $strictClean['platformMetadata']['platformMetadataEntryCount']);
    },

    'preflights windows platform metadata sidecars before office package media handoff' => static function (TestRunner $t): void {
        $reviewPackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>windows platform metadata preflight</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "visible reviewer image placeholder\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/Thumbs.db',
                'data' => "windows thumbnail cache should not import as document media\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'customXml/desktop.ini',
                'data' => "[.ShellClassInfo]\nLocalizedResourceName=Custom XML\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/media/source-diagram.svg',
                'data' => "<svg />\n",
                'compressionMethod' => 0,
            ],
        ]);
        $summary = $reviewPackage->platformMetadataPreflight();

        $t->same(5, $summary['entryCount']);
        $t->same(2, $summary['platformMetadataEntryCount']);
        $t->same(0, $summary['macosSidecarEntryCount']);
        $t->same(0, $summary['appleDoubleEntryCount']);
        $t->same(0, $summary['finderMetadataEntryCount']);
        $t->same(2, $summary['windowsSidecarEntryCount']);
        $t->same(1, $summary['windowsThumbnailCacheEntryCount']);
        $t->same(1, $summary['windowsDesktopIniEntryCount']);
        $t->same('word/media/review.png', $summary['entries'][1]['name']);
        $t->same(null, $summary['entries'][1]['platform']);
        $t->same(false, $summary['entries'][1]['isWindowsSidecar']);
        $t->same(false, $summary['entries'][1]['isWindowsThumbnailCache']);
        $t->same(false, $summary['entries'][1]['isWindowsDesktopIni']);
        $t->same([], $summary['entries'][1]['issues']);
        $t->same('word/media/Thumbs.db', $summary['platformMetadataEntries'][0]['name']);
        $t->same('windows', $summary['platformMetadataEntries'][0]['platform']);
        $t->same(false, $summary['platformMetadataEntries'][0]['isMacosSidecar']);
        $t->same(true, $summary['platformMetadataEntries'][0]['isWindowsSidecar']);
        $t->same(true, $summary['platformMetadataEntries'][0]['isWindowsThumbnailCache']);
        $t->same(false, $summary['platformMetadataEntries'][0]['isWindowsDesktopIni']);
        $t->same(['windows-thumbnail-cache-entry'], $summary['platformMetadataEntries'][0]['issues']);
        $t->same('customXml/desktop.ini', $summary['platformMetadataEntries'][1]['name']);
        $t->same('windows', $summary['platformMetadataEntries'][1]['platform']);
        $t->same(false, $summary['platformMetadataEntries'][1]['isWindowsThumbnailCache']);
        $t->same(true, $summary['platformMetadataEntries'][1]['isWindowsDesktopIni']);
        $t->same(['windows-desktop-ini-entry'], $summary['platformMetadataEntries'][1]['issues']);
        $t->same("windows thumbnail cache should not import as document media\n", $reviewPackage->read('/word/media/Thumbs.db'));

        $strictSummary = $reviewPackage->strictImportPreflight(4096, 100.0, 4096);
        $t->same(false, $strictSummary['isValid']);
        $t->same(['platform-metadata-entries'], $strictSummary['diagnostics']);
        $t->same(2, $strictSummary['platformMetadata']['platformMetadataEntryCount']);
        $t->same(2, $strictSummary['platformMetadata']['windowsSidecarEntryCount']);
        $t->same(1, $strictSummary['platformMetadata']['windowsThumbnailCacheEntryCount']);
        $t->same(1, $strictSummary['platformMetadata']['windowsDesktopIniEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertNoPlatformMetadataEntries());
        $t->throws(\RuntimeException::class, static fn (): array => $reviewPackage->assertStrictImportable(4096, 100.0, 4096));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>windows safe platform metadata</w:p></w:document>',
            ],
            [
                'name' => 'word/media/thumbs-up.png',
                'data' => "ordinary image whose name is not Thumbs.db\n",
                'compressionMethod' => 0,
            ],
            [
                'name' => 'docProps/core.xml',
                'data' => "<cp:coreProperties />\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoPlatformMetadataEntries();
        $t->same(3, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['platformMetadataEntryCount']);
        $t->same(0, $safeSummary['windowsSidecarEntryCount']);
        $t->same(0, $safeSummary['windowsThumbnailCacheEntryCount']);
        $t->same(0, $safeSummary['windowsDesktopIniEntryCount']);
        $t->same([], $safeSummary['platformMetadataEntries']);
        $strictClean = $safePackage->assertStrictImportable(4096, 100.0, 4096);
        $t->same(true, $strictClean['isValid']);
        $t->same(0, $strictClean['platformMetadata']['windowsSidecarEntryCount']);
    },

    'preflights raw zip platform metadata before package instantiation failure' => static function (TestRunner $t) use ($buildZipPackage): void {
        $bytes = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/other.xml',
                'data' => '<w:document><w:p>raw platform metadata review</w:p></w:document>',
                'method' => 0,
            ],
            [
                'name' => '__MACOSX/word/media/._review.png',
                'data' => "AppleDouble sidecar should not be imported as document media\n",
                'method' => 0,
            ],
            [
                'name' => 'word/media/Thumbs.db',
                'data' => "Windows thumbnail cache should not be imported as document media\n",
                'method' => 0,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "Visible media that would otherwise import normally\n",
                'method' => 0,
            ],
        ]);

        $summary = ZipPackage::platformMetadataPolicyPreflight($bytes);
        $t->same(4, $summary['entryCount']);
        $t->same(2, $summary['platformMetadataEntryCount']);
        $t->same(1, $summary['macosSidecarEntryCount']);
        $t->same(1, $summary['appleDoubleEntryCount']);
        $t->same(0, $summary['finderMetadataEntryCount']);
        $t->same(1, $summary['windowsSidecarEntryCount']);
        $t->same(1, $summary['windowsThumbnailCacheEntryCount']);
        $t->same(0, $summary['windowsDesktopIniEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(
            [
                'platform-metadata-entries',
                'macos-sidecar-entries',
                'appledouble-resource-entries',
                'windows-sidecar-entries',
                'windows-thumbnail-cache-entries',
            ],
            $summary['issues']
        );
        $t->same('__MACOSX/word/media/._review.png', $summary['platformMetadataEntries'][0]['name']);
        $t->same('macos', $summary['platformMetadataEntries'][0]['platform']);
        $t->same(['macos-sidecar-entry', 'appledouble-resource-entry'], $summary['platformMetadataEntries'][0]['issues']);
        $t->same(['zip-macos-sidecar-entry', 'zip-appledouble-resource-entry'], $summary['platformMetadataEntries'][0]['diagnostics']);
        $t->same('blocked', $summary['platformMetadataEntries'][0]['policy']);
        $t->same('word/media/Thumbs.db', $summary['platformMetadataEntries'][1]['name']);
        $t->same('windows', $summary['platformMetadataEntries'][1]['platform']);
        $t->same(['windows-thumbnail-cache-entry'], $summary['platformMetadataEntries'][1]['issues']);
        $t->same(['zip-windows-thumbnail-cache-entry'], $summary['platformMetadataEntries'][1]['diagnostics']);
        $t->same('metadata', $summary['entries'][3]['policy']);
        $t->same([], $summary['entries'][3]['issues']);

        $rawStrict = ZipPackage::rawStrictImportPreflight($bytes, 4096, 100.0, 4096);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(true, str_contains((string) $rawStrict['instantiationError'], 'local header name'));
        $t->same($summary, $rawStrict['platformMetadata']);
        $t->same(null, $rawStrict['strictImport']);
        $t->same(true, in_array('platform-metadata-entries', $rawStrict['diagnostics'], true));
        $t->same(true, in_array('macos-sidecar-entries', $rawStrict['diagnostics'], true));
        $t->same(true, in_array('appledouble-resource-entries', $rawStrict['diagnostics'], true));
        $t->same(true, in_array('windows-sidecar-entries', $rawStrict['diagnostics'], true));
        $t->same(true, in_array('local-header-name-issues', $rawStrict['diagnostics'], true));
        $t->same(true, in_array('local-header-name-mismatch', $rawStrict['diagnostics'], true));
        $t->same(true, in_array('zip-package-instantiation-failed', $rawStrict['diagnostics'], true));
        $t->same('word/document.xml', $rawStrict['localHeaderNames']['mismatchedEntries'][0]['centralName']);
        $t->same('word/other.xml', $rawStrict['localHeaderNames']['mismatchedEntries'][0]['localName']);

        $clean = ZipPackage::platformMetadataPolicyPreflight(ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>clean raw platform policy</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "Visible media without platform sidecars\n",
                'compressionMethod' => 0,
            ],
        ])->bytes());
        $t->same(2, $clean['entryCount']);
        $t->same(0, $clean['platformMetadataEntryCount']);
        $t->same(true, $clean['isSupportedByBoundedReader']);
        $t->same([], $clean['issues']);
    },

    'rejects stored zip entry size mismatches before package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $storedMedia = "stored reviewer media bytes\n";
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => $storedMedia,
                'method' => 0,
                'centralUncompressedSize' => strlen($storedMedia) + 1,
                'localUncompressedSize' => strlen($storedMedia) + 1,
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => $storedMedia,
                'method' => 0,
            ],
        ]));

        $t->same($storedMedia, $package->read('/word/media/reviewer-note.txt'));
    },

    'rejects zip local entry layout overlap before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>overlap</w:p></w:document>',
                'method' => 0,
                'centralCompressedSize' => strlen('<w:document><w:p>overlap</w:p></w:document>') + 12,
                'centralUncompressedSize' => strlen('<w:document><w:p>overlap</w:p></w:document>') + 12,
            ],
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 0,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 0,
                'flags' => 0x0808,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes/>',
                'method' => 0,
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ordinary layout</w:p></w:document>',
                'method' => 0,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 8,
                'descriptor' => true,
            ],
        ]));

        $t->same([
            'word/document.xml',
            'word/comments.xml',
        ], $package->names());
        $t->same('<w:document><w:p>ordinary layout</w:p></w:document>', $package->read('/word/document.xml'));
        $t->same('<w:comments/>', $package->read('/word/comments.xml'));
    },

    'rejects zip local entry slack and package prefixes before import preflight' => static function (TestRunner $t) use ($buildZipPackage, $buildPrefixedPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildPrefixedPackage()));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>hidden local slack</w:p></w:document>',
                'method' => 0,
                'localSlack' => 'hidden-bytes-before-next-header',
            ],
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>hidden central slack</w:p></w:document>',
                'method' => 0,
                'localSlack' => 'hidden-bytes-before-central-directory',
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments><w:comment>descriptor remains valid</w:comment></w:comments>',
                'method' => 8,
                'descriptor' => true,
            ],
        ]));

        $t->same(['word/comments.xml'], $package->localNames());
        $t->same('<w:comments><w:comment>descriptor remains valid</w:comment></w:comments>', $package->read('/word/comments.xml'));
    },

    'preflights zip package prefixes before raw strict import' => static function (TestRunner $t) use ($buildPrefixedPackage): void {
        $zip = $buildPrefixedPackage();
        $prefix = "MZhidden-review-stub\n";
        $archive = ZipPackage::endOfCentralDirectoryPreflight($zip);
        $summary = ZipPackage::packagePrefixPreflight($zip);
        $layout = ZipPackage::packageByteLayoutPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 512, 20.0, 512);

        $t->same(1, $summary['entryCount']);
        $t->same(true, $summary['hasPackagePrefix']);
        $t->same(strlen($prefix), $summary['prefixByteCount']);
        $t->same(16, $summary['prefixPreviewByteCount']);
        $t->same(bin2hex(substr($prefix, 0, 16)), $summary['prefixPreviewHex']);
        $t->same('mz-executable-stub', $summary['prefixSignature']);
        $t->same(true, $summary['hasExecutableStubPrefix']);
        $t->same(strlen($prefix), $summary['firstLocalHeaderOffset']);
        $t->same($archive['centralDirectoryOffset'], $summary['centralDirectoryOffset']);
        $t->same($archive['centralDirectoryOffset'] - strlen($prefix), $summary['centralDirectoryOffsetAfterPrefix']);
        $t->same($archive['eocdOffset'] - strlen($prefix), $summary['eocdOffsetAfterPrefix']);
        $t->same(['local-header-prefix-bytes'], $summary['localHeaderSpanIssues']);
        $t->same([], $summary['localHeaderSpanIssuesWithoutPrefix']);
        $t->same(true, $summary['isPackageLayoutOtherwiseContiguous']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['package-prefix-bytes', 'package-prefix-mz-executable-stub'], $summary['issues']);
        $t->same(true, $layout['hasPackagePrefix']);
        $t->same(strlen($prefix), $layout['prefixByteCount']);
        $t->same(hash('sha256', $prefix), $layout['prefixSha256']);
        $t->same(
            hash('sha256', substr($zip, strlen($prefix), $layout['localRegionBytes'])),
            $layout['localRegionSha256']
        );

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(1, $rawStrict['entryCount']);
        $t->same($summary, $rawStrict['packagePrefix']);
        $t->same($layout, $rawStrict['packageByteLayout']);
        $t->same(1, $rawStrict['localHeaderSpans']['entryCount']);
        $t->same(0, $rawStrict['localHeaderSpans']['issueEntryCount']);
        $t->same(['local-header-prefix-bytes'], $rawStrict['localHeaderSpans']['issues']);
        $t->contains('local-header-span-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-header-prefix-bytes', implode(',', $rawStrict['diagnostics']));
        $t->contains('package-prefix-bytes', implode(',', $rawStrict['diagnostics']));
        $t->contains('package-prefix-mz-executable-stub', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));

        $safeSummary = ZipPackage::packagePrefixPreflight(ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ordinary prefix-free package</w:p></w:document>',
            ],
        ])->bytes());
        $t->same(false, $safeSummary['hasPackagePrefix']);
        $t->same(0, $safeSummary['prefixByteCount']);
        $t->same('', $safeSummary['prefixPreviewHex']);
        $t->same(null, $safeSummary['prefixSignature']);
        $t->same(true, $safeSummary['isSupportedByBoundedReader']);
        $t->same([], $safeSummary['issues']);
    },

    'rejects duplicate zip local header offsets before package import preflight' => static function (TestRunner $t) use ($buildDuplicateLocalOffsetPackage): void {
        $zip = $buildDuplicateLocalOffsetPackage();
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights duplicate zip local header offsets before raw strict import' => static function (TestRunner $t) use ($buildDuplicateLocalOffsetPackage): void {
        $zip = $buildDuplicateLocalOffsetPackage();
        $summary = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 100.0, 1024);
        $group = $summary['duplicateLocalHeaderOffsetGroups'][0];

        $t->same(2, $summary['declaredEntryCount']);
        $t->same(2, $summary['scannedEntryCount']);
        $t->same(false, $summary['hasEntryCountMismatch']);
        $t->same(true, $summary['hasDuplicateLocalHeaderOffsets']);
        $t->same(1, $summary['duplicateLocalHeaderOffsetGroupCount']);
        $t->same(2, $summary['duplicateLocalHeaderOffsetEntryCount']);
        $t->same(0, $group['localHeaderOffset']);
        $t->same(2, $group['count']);
        $t->same(['word/media/review-one.png', 'word/media/review-two.png'], $group['names']);
        $t->same([0, 1], $group['centralDirectoryIndexes']);
        $t->same(true, $group['centralDirectoryOffsets'][0] < $group['centralDirectoryOffsets'][1]);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['central-directory-duplicate-local-header-offsets'], $summary['issues']);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same($summary, $rawStrict['centralDirectoryInventory']);
        $t->contains('central-directory-inventory-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('central-directory-duplicate-local-header-offsets', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
    },

    'preflights zip central directory digital signatures before package import handoff' => static function (TestRunner $t) use ($buildCentralDirectorySignaturePackage): void {
        $signedZip = $buildCentralDirectorySignaturePackage();
        $package = ZipPackage::fromString($signedZip);
        $signature = $package->centralDirectorySignaturePreflight();
        $layout = ZipPackage::packageByteLayoutPreflight($signedZip);

        $t->true($package->hasCentralDirectorySignature());
        $t->same('central-signature', $package->centralDirectorySignature());
        $t->same(true, $signature['present']);
        $t->same($package->centralDirectoryOffset() + 46 + strlen('word/document.xml'), $signature['offset']);
        $t->same('central-signature', $signature['signatureData']);
        $t->same(strlen('central-signature'), $signature['signatureLength']);
        $t->same(hash('sha256', 'central-signature'), $signature['signatureSha256']);
        $t->same('not-performed-native-bounded-reader', $signature['cryptographicVerification']);
        $t->same(strlen('central-signature') + 6, $layout['centralDirectoryToEocdGapBytes']);
        $t->same(
            hash(
                'sha256',
                substr($signedZip, (int) $layout['centralDirectoryToEocdGapOffset'], $layout['centralDirectoryToEocdGapBytes'])
            ),
            $layout['centralDirectoryToEocdGapSha256']
        );
        $t->same('<w:document><w:body><w:p>digitally signed central directory</w:p></w:body></w:document>', $package->read('/word/document.xml'));

        $includedSizePackage = ZipPackage::fromString($buildCentralDirectorySignaturePackage(true));
        $t->true($includedSizePackage->hasCentralDirectorySignature());
        $t->same('central-signature', $includedSizePackage->centralDirectorySignaturePreflight()['signatureData']);
        $t->same(hash('sha256', 'central-signature'), $includedSizePackage->centralDirectorySignaturePreflight()['signatureSha256']);
        $t->same(
            '<w:document><w:body><w:p>digitally signed central directory</w:p></w:body></w:document>',
            $includedSizePackage->read('/word/document.xml')
        );
    },

    'rejects unverified zip central directory digital signatures in strict package imports' => static function (TestRunner $t) use ($buildCentralDirectorySignaturePackage): void {
        $package = ZipPackage::fromString($buildCentralDirectorySignaturePackage());
        $summary = $package->strictImportPreflight(2048, 100.0, 2048);

        $t->same(false, $summary['isValid']);
        $t->same(['central-directory-signature-unverified'], $summary['diagnostics']);
        $t->same(true, $summary['archive']['hasCentralDirectorySignature']);
        $t->same(strlen('central-signature'), $summary['archive']['centralDirectorySignatureLength']);
        $t->same('not-performed-native-bounded-reader', $package->centralDirectorySignaturePreflight()['cryptographicVerification']);
        $t->same(
            '<w:document><w:body><w:p>digitally signed central directory</w:p></w:body></w:document>',
            $package->read('/word/document.xml')
        );
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(2048, 100.0, 2048));
    },

    'preflights raw central directory digital signature provenance before package instantiation' => static function (TestRunner $t) use ($buildCentralDirectorySignaturePackage): void {
        $signedZip = $buildCentralDirectorySignaturePackage();
        $centralName = 'word/document.xml';
        $spoofedLocalName = 'word/reviewer.xml';
        $spoofedSignedZip = substr($signedZip, 0, 30)
            . $spoofedLocalName
            . substr($signedZip, 30 + strlen($centralName));

        $signature = ZipPackage::centralDirectorySignaturePolicyPreflight($spoofedSignedZip);
        $raw = ZipPackage::rawStrictImportPreflight($spoofedSignedZip, 2048, 100.0, 2048);

        $t->same(false, $raw['isValid']);
        $t->same(false, $raw['canInstantiate']);
        $t->contains('central-directory-signature-unverified', implode(',', $raw['diagnostics']));
        $t->contains('local-header-name-issues', implode(',', $raw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $raw['diagnostics']));
        $t->same($signature, $raw['centralDirectorySignature']);
        $t->same(true, $signature['present']);
        $t->same(1, $signature['entryCount']);
        $t->same($raw['centralDirectoryInventory']['centralDirectoryEnd'], $signature['offset']);
        $t->same($signature['offset'] + 6, $signature['dataOffset']);
        $t->same($raw['archive']['eocdOffset'], $signature['endOffset']);
        $t->same('between-central-directory-and-eocd', $signature['location']);
        $t->same('central-signature', $signature['signatureData']);
        $t->same(strlen('central-signature'), $signature['signatureLength']);
        $t->same(bin2hex(substr('central-signature', 0, 16)), $signature['signaturePreviewHex']);
        $t->same(hash('sha256', 'central-signature'), $signature['signatureSha256']);
        $t->same('not-performed-native-bounded-reader', $signature['cryptographicVerification']);
        $t->same(false, $signature['isSupportedByBoundedReader']);
        $t->same(['central-directory-signature-unverified'], $signature['issues']);
        $t->same('word/document.xml', $raw['localHeaderNames']['mismatchedEntries'][0]['centralName']);
        $t->same($spoofedLocalName, $raw['localHeaderNames']['mismatchedEntries'][0]['localName']);
    },

    'preflights central directory digital signature sha256 provenance before package handoff' => static function (TestRunner $t) use ($buildCentralDirectorySignaturePackage): void {
        $signedZip = $buildCentralDirectorySignaturePackage();
        $expectedHash = hash('sha256', 'central-signature');
        $package = ZipPackage::fromString($signedZip);
        $instanceSignature = $package->centralDirectorySignaturePreflight();
        $rawSignature = ZipPackage::centralDirectorySignaturePolicyPreflight($signedZip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($signedZip, 2048, 100.0, 2048);
        $unsignedPackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>unsigned central directory</w:p></w:document>',
            ],
        ]);

        $t->same($expectedHash, $instanceSignature['signatureSha256']);
        $t->same($expectedHash, $rawSignature['signatureSha256']);
        $t->same($expectedHash, $rawStrict['centralDirectorySignature']['signatureSha256']);
        $t->same(strlen('central-signature'), $rawSignature['signatureLength']);
        $t->same('not-performed-native-bounded-reader', $rawSignature['cryptographicVerification']);
        $t->same(null, $unsignedPackage->centralDirectorySignaturePreflight()['signatureSha256']);
        $t->same(null, ZipPackage::centralDirectorySignaturePolicyPreflight($unsignedPackage->bytes())['signatureSha256']);
    },

    'rejects malformed zip central directory digital signature records' => static function (TestRunner $t) use ($buildCentralDirectorySignaturePackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString(
            $buildCentralDirectorySignaturePackage(false, 5)
        ));
    },

    'preflights zip archive extra data records before package import handoff' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>archive extra metadata</w:p></w:document>',
                'method' => 8,
            ],
        ]);
        $eocdOffset = strrpos($zip, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }
        $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
        $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
        $archiveExtraData = 'review-archive-extra-data';
        $archiveExtraRecord = "PK\x06\x08" . pack('V', strlen($archiveExtraData)) . $archiveExtraData;
        $cleanSummary = ZipPackage::archiveExtraDataRecordPreflight($zip);

        $t->same(1, $cleanSummary['entryCount']);
        $t->same(false, $cleanSummary['hasArchiveExtraDataRecord']);
        $t->same(true, $cleanSummary['isSupportedByBoundedReader']);
        $t->same([], $cleanSummary['archiveExtraDataRecords']);

        $tailRecordZip = substr($zip, 0, $eocdOffset) . $archiveExtraRecord . substr($zip, $eocdOffset);
        $tailSummary = ZipPackage::archiveExtraDataRecordPreflight($tailRecordZip);
        $tailRecord = $tailSummary['archiveExtraDataRecords'][0];

        $t->same(1, $tailSummary['entryCount']);
        $t->same(1, $tailSummary['archiveExtraDataRecordCount']);
        $t->same(true, $tailSummary['hasArchiveExtraDataRecord']);
        $t->same(false, $tailSummary['isSupportedByBoundedReader']);
        $t->same($eocdOffset, $tailSummary['centralDirectoryEnd']);
        $t->same($eocdOffset, $tailRecord['offset']);
        $t->same($eocdOffset + 8, $tailRecord['dataOffset']);
        $t->same(strlen($archiveExtraData), $tailRecord['dataLength']);
        $t->same($eocdOffset + strlen($archiveExtraRecord), $tailRecord['endOffset']);
        $t->same(8, $tailRecord['fixedHeaderLength']);
        $t->same(strlen($archiveExtraRecord), $tailRecord['recordLength']);
        $t->same(hash('sha256', $archiveExtraRecord), $tailRecord['recordSha256']);
        $t->same(hash('sha256', $archiveExtraData), $tailRecord['dataSha256']);
        $t->same(bin2hex(substr($archiveExtraData, 0, 16)), $tailRecord['dataPreviewHex']);
        $t->same(16, $tailRecord['dataPreviewByteCount']);
        $t->same('zip-archive-extra-data-record-metadata-only', $tailRecord['byteExposurePolicy']);
        $t->same(false, $tailRecord['canExposeBytes']);
        $t->same('between-central-directory-and-eocd', $tailRecord['location']);
        $t->same(['archive-extra-data-record'], $tailRecord['issues']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($tailRecordZip));

        $prefixRecordZip = substr($zip, 0, $centralDirectoryOffset)
            . $archiveExtraRecord
            . substr($zip, $centralDirectoryOffset);
        $prefixRecordZip = $rewriteEndOfCentralDirectory($prefixRecordZip, [
            'centralDirectorySize' => $centralDirectorySize + strlen($archiveExtraRecord),
        ]);
        $prefixSummary = ZipPackage::archiveExtraDataRecordPreflight($prefixRecordZip);
        $prefixRecord = $prefixSummary['archiveExtraDataRecords'][0];

        $t->same(1, $prefixSummary['entryCount']);
        $t->same(1, $prefixSummary['archiveExtraDataRecordCount']);
        $t->same(false, $prefixSummary['isSupportedByBoundedReader']);
        $t->same($centralDirectoryOffset, $prefixRecord['offset']);
        $t->same('central-directory-prefix', $prefixRecord['location']);
        $t->same(['archive-extra-data-record'], $prefixRecord['issues']);
        $t->same('word/document.xml', $prefixSummary['entries'][0]['name']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($prefixRecordZip));
    },

    'preflights inter-entry zip archive extra data records before raw strict import' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $archiveExtraData = 'review-archive-extra-data';
        $archiveExtraRecord = "PK\x06\x08" . pack('V', strlen($archiveExtraData)) . $archiveExtraData;
        $twoEntryZip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>archive extra inter-entry metadata</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "review media bytes\n",
                'method' => 0,
            ],
        ]);
        $twoEntryEocdOffset = strrpos($twoEntryZip, "PK\x05\x06");
        if ($twoEntryEocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }
        $twoEntryCentralDirectorySize = unpack('Vvalue', substr($twoEntryZip, $twoEntryEocdOffset + 12, 4))['value'];
        $twoEntryCentralDirectoryOffset = unpack('Vvalue', substr($twoEntryZip, $twoEntryEocdOffset + 16, 4))['value'];
        $firstCentralNameLength = unpack('vvalue', substr($twoEntryZip, $twoEntryCentralDirectoryOffset + 28, 2))['value'];
        $firstCentralExtraLength = unpack('vvalue', substr($twoEntryZip, $twoEntryCentralDirectoryOffset + 30, 2))['value'];
        $firstCentralCommentLength = unpack('vvalue', substr($twoEntryZip, $twoEntryCentralDirectoryOffset + 32, 2))['value'];
        $interEntryOffset = $twoEntryCentralDirectoryOffset
            + 46
            + $firstCentralNameLength
            + $firstCentralExtraLength
            + $firstCentralCommentLength;
        $interEntryRecordZip = substr($twoEntryZip, 0, $interEntryOffset)
            . $archiveExtraRecord
            . substr($twoEntryZip, $interEntryOffset);
        $interEntryRecordZip = $rewriteEndOfCentralDirectory($interEntryRecordZip, [
            'centralDirectorySize' => $twoEntryCentralDirectorySize + strlen($archiveExtraRecord),
        ]);
        $interEntrySummary = ZipPackage::archiveExtraDataRecordPreflight($interEntryRecordZip);
        $interEntryRecord = $interEntrySummary['archiveExtraDataRecords'][0];
        $interEntryRawStrict = ZipPackage::rawStrictImportPreflight($interEntryRecordZip, 4096, 100.0, 4096);

        $t->same(2, $interEntrySummary['entryCount']);
        $t->same(1, $interEntrySummary['archiveExtraDataRecordCount']);
        $t->same(true, $interEntrySummary['hasArchiveExtraDataRecord']);
        $t->same(false, $interEntrySummary['isSupportedByBoundedReader']);
        $t->same($interEntryOffset, $interEntryRecord['offset']);
        $t->same(strlen($archiveExtraRecord), $interEntryRecord['recordLength']);
        $t->same(hash('sha256', $archiveExtraRecord), $interEntryRecord['recordSha256']);
        $t->same(hash('sha256', $archiveExtraData), $interEntryRecord['dataSha256']);
        $t->same('before-central-directory-entry', $interEntryRecord['location']);
        $t->same(['archive-extra-data-record'], $interEntryRecord['issues']);
        $t->same('word/document.xml', $interEntrySummary['entries'][0]['name']);
        $t->same('word/media/review.png', $interEntrySummary['entries'][1]['name']);
        $t->same(false, $interEntryRawStrict['isValid']);
        $t->same(false, $interEntryRawStrict['canInstantiate']);
        $t->same(1, $interEntryRawStrict['archiveExtraDataRecords']['archiveExtraDataRecordCount']);
        $t->same(
            'before-central-directory-entry',
            $interEntryRawStrict['archiveExtraDataRecords']['archiveExtraDataRecords'][0]['location']
        );
        $t->same(true, in_array('archive-extra-data-records', $interEntryRawStrict['diagnostics'], true));
        $t->same(true, in_array('zip-package-instantiation-failed', $interEntryRawStrict['diagnostics'], true));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($interEntryRecordZip));
    },

    'preflights central directory inventory across inter-entry archive extra records' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $archiveExtraData = 'inventory-archive-extra-data';
        $archiveExtraRecord = "PK\x06\x08" . pack('V', strlen($archiveExtraData)) . $archiveExtraData;
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>inventory archive extra metadata</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "review media bytes\n",
                'method' => 0,
            ],
        ]);
        $eocdOffset = strrpos($zip, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }
        $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
        $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
        $firstCentralNameLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 28, 2))['value'];
        $firstCentralExtraLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 30, 2))['value'];
        $firstCentralCommentLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 32, 2))['value'];
        $interEntryOffset = $centralDirectoryOffset
            + 46
            + $firstCentralNameLength
            + $firstCentralExtraLength
            + $firstCentralCommentLength;
        $interEntryRecordZip = substr($zip, 0, $interEntryOffset)
            . $archiveExtraRecord
            . substr($zip, $interEntryOffset);
        $interEntryRecordZip = $rewriteEndOfCentralDirectory($interEntryRecordZip, [
            'centralDirectorySize' => $centralDirectorySize + strlen($archiveExtraRecord),
        ]);

        $summary = ZipPackage::centralDirectoryInventoryPreflight($interEntryRecordZip);
        $archiveExtraSummary = ZipPackage::archiveExtraDataRecordPreflight($interEntryRecordZip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($interEntryRecordZip, 4096, 100.0, 4096);
        $skippedRecord = $summary['skippedArchiveExtraDataRecords'][0];
        $diagnostics = implode(',', $rawStrict['diagnostics']);

        $t->same(2, $summary['declaredEntryCount']);
        $t->same(2, $summary['scannedEntryCount']);
        $t->same(2, $summary['entryCount']);
        $t->same(true, $summary['scanCompletedCentralDirectory']);
        $t->same(false, $summary['hasUnexpectedCentralDirectoryTail']);
        $t->same(false, $summary['hasEntryCountMismatch']);
        $t->same(0, $summary['entryCountDelta']);
        $t->same(0, $summary['centralDirectoryTailBytes']);
        $t->same(1, $summary['skippedArchiveExtraDataRecordCount']);
        $t->same(strlen($archiveExtraRecord), $summary['skippedArchiveExtraDataRecordBytes']);
        $t->same($interEntryOffset, $skippedRecord['offset']);
        $t->same($interEntryOffset + 8, $skippedRecord['dataOffset']);
        $t->same(strlen($archiveExtraData), $skippedRecord['dataLength']);
        $t->same($interEntryOffset + strlen($archiveExtraRecord), $skippedRecord['endOffset']);
        $t->same(8, $skippedRecord['fixedHeaderLength']);
        $t->same(strlen($archiveExtraRecord), $skippedRecord['recordLength']);
        $t->same(hash('sha256', $archiveExtraRecord), $skippedRecord['recordSha256']);
        $t->same(hash('sha256', $archiveExtraData), $skippedRecord['dataSha256']);
        $t->same(bin2hex(substr($archiveExtraData, 0, 16)), $skippedRecord['dataPreviewHex']);
        $t->same(16, $skippedRecord['dataPreviewByteCount']);
        $t->same('zip-archive-extra-data-record-metadata-only', $skippedRecord['byteExposurePolicy']);
        $t->same(false, $skippedRecord['canExposeBytes']);
        $t->same('before-central-directory-entry', $skippedRecord['location']);
        $t->same(['archive-extra-data-record'], $skippedRecord['issues']);
        $t->same(['word/document.xml', 'word/media/review.png'], array_column($summary['entries'], 'name'));
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);

        $t->same($archiveExtraSummary['archiveExtraDataRecords'], $summary['skippedArchiveExtraDataRecords']);
        $t->same($summary, $rawStrict['centralDirectoryInventory']);
        $t->contains('archive-extra-data-records', $diagnostics);
        $t->contains('zip-package-instantiation-failed', $diagnostics);
        $t->same(false, str_contains($diagnostics, 'central-directory-inventory-issues'));
    },

    'preflights zip end of central directory archive layout before package import' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>EOCD preflight</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
            ],
        ], 'archive layout comment');
        $rawSummary = ZipPackage::endOfCentralDirectoryPreflight($zip);
        $package = ZipPackage::fromString($zip);
        $summary = $package->archivePreflight();

        $t->same($rawSummary['eocdOffset'], strlen($zip) - 22 - strlen('archive layout comment'));
        $t->same(0, $summary['diskNumber']);
        $t->same(0, $summary['centralDirectoryDisk']);
        $t->same(2, $summary['diskEntryCount']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same($package->centralDirectoryOffset(), $summary['centralDirectoryOffset']);
        $t->same($rawSummary['centralDirectorySize'], $summary['centralDirectorySize']);
        $t->same($summary['eocdOffset'], $summary['centralDirectoryEnd']);
        $t->same('archive layout comment', $summary['packageComment']);
        $t->same(strlen('archive layout comment'), $summary['packageCommentLength']);
        $t->same(true, $summary['isSingleDisk']);
        $t->same(false, $summary['requiresZip64']);
        $t->same(true, $summary['isArchiveLayoutSupported']);
        $t->same(false, $summary['hasCentralDirectorySignature']);
        $t->same(null, $summary['centralDirectorySignatureOffset']);
        $t->same(0, $summary['centralDirectorySignatureLength']);

        $splitZip = $rewriteEndOfCentralDirectory($zip, [
            'diskNumber' => 1,
            'centralDirectoryDisk' => 1,
            'diskEntryCount' => 1,
        ]);
        $splitSummary = ZipPackage::endOfCentralDirectoryPreflight($splitZip);
        $t->same(1, $splitSummary['diskNumber']);
        $t->same(1, $splitSummary['centralDirectoryDisk']);
        $t->same(1, $splitSummary['diskEntryCount']);
        $t->same(2, $splitSummary['totalEntryCount']);
        $t->same(false, $splitSummary['isSingleDisk']);
        $t->same(false, $splitSummary['isArchiveLayoutSupported']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($splitZip));

        $zip64MarkerZip = $rewriteEndOfCentralDirectory($zip, [
            'diskEntryCount' => 0xffff,
            'totalEntryCount' => 0xffff,
            'centralDirectorySize' => 0xffffffff,
        ]);
        $zip64Summary = ZipPackage::endOfCentralDirectoryPreflight($zip64MarkerZip);
        $t->same(true, $zip64Summary['requiresZip64']);
        $t->same(false, $zip64Summary['isArchiveLayoutSupported']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip64MarkerZip));
    },

    'preflights zip end of central directory fixed fields before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $comment = 'eocd fixed field comment';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>EOCD fixed fields</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "review media bytes\n",
                'method' => 0,
            ],
        ], $comment);
        $package = ZipPackage::fromString($zip);
        $eocdOffset = strrpos($zip, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }
        $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
        $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];

        $summary = ZipPackage::endOfCentralDirectoryFixedFieldsPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);

        $t->same(strlen($zip), $summary['archiveLength']);
        $t->same(true, $summary['hasEndOfCentralDirectoryRecord']);
        $t->same($eocdOffset, $summary['eocdOffset']);
        $t->same($eocdOffset, $summary['fixedHeaderOffset']);
        $t->same(22, $summary['fixedHeaderLength']);
        $t->same($eocdOffset, $summary['signatureOffset']);
        $t->same(4, $summary['signatureLength']);
        $t->same('504b0506', $summary['signatureHex']);
        $t->same($eocdOffset + 4, $summary['diskNumberOffset']);
        $t->same(0, $summary['diskNumber']);
        $t->same($eocdOffset + 6, $summary['centralDirectoryDiskOffset']);
        $t->same(0, $summary['centralDirectoryDisk']);
        $t->same($eocdOffset + 8, $summary['diskEntryCountOffset']);
        $t->same(2, $summary['diskEntryCount']);
        $t->same($eocdOffset + 10, $summary['totalEntryCountOffset']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same($eocdOffset + 12, $summary['centralDirectorySizeOffset']);
        $t->same($centralDirectorySize, $summary['centralDirectorySize']);
        $t->same($eocdOffset + 16, $summary['centralDirectoryOffsetFieldOffset']);
        $t->same($centralDirectoryOffset, $summary['centralDirectoryOffset']);
        $t->same($eocdOffset + 20, $summary['packageCommentLengthOffset']);
        $t->same(strlen($comment), $summary['packageCommentLength']);
        $t->same($eocdOffset + 22, $summary['fixedHeaderEnd']);
        $t->same($eocdOffset + 22, $summary['packageCommentOffset']);
        $t->same(strlen($zip), $summary['packageCommentEnd']);
        $t->same($comment, $summary['packageComment']);
        $t->same(bin2hex($comment), $summary['packageCommentHex']);
        $t->same(bin2hex(substr($comment, 0, 16)), $summary['packageCommentPreviewHex']);
        $t->same(strlen($zip), $summary['declaredArchiveEndOffset']);
        $t->same(strlen($comment), $summary['availablePackageCommentBytes']);
        $t->same(0, $summary['missingPackageCommentBytes']);
        $t->same(true, $summary['hasPackageComment']);
        $t->same(false, $summary['hasTrailingBytes']);
        $t->same(0, $summary['trailingByteCount']);
        $t->same(null, $summary['trailingBytesOffset']);
        $t->same(null, $summary['trailingBytesPreviewHex']);
        $t->same(false, $summary['hasTruncatedPackageComment']);
        $t->same($eocdOffset, $summary['centralDirectoryEnd']);
        $t->same(true, $summary['isSingleDisk']);
        $t->same(false, $summary['requiresZip64']);
        $t->same(true, $summary['isArchiveLayoutSupported']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);
        $t->same($summary, $rawStrict['endOfCentralDirectoryFixedFields']);
        $t->same($summary, $strict['endOfCentralDirectoryFixedFields']);

        $tailedZip = $zip . 'detached-tail';
        $tailedSummary = ZipPackage::endOfCentralDirectoryFixedFieldsPreflight($tailedZip);
        $tailedRaw = ZipPackage::rawStrictImportPreflight($tailedZip, 2048, 100.0, 2048);

        $t->same(strlen($tailedZip), $tailedSummary['archiveLength']);
        $t->same($eocdOffset, $tailedSummary['eocdOffset']);
        $t->same(strlen($zip), $tailedSummary['declaredArchiveEndOffset']);
        $t->same(true, $tailedSummary['hasTrailingBytes']);
        $t->same(strlen('detached-tail'), $tailedSummary['trailingByteCount']);
        $t->same(strlen($zip), $tailedSummary['trailingBytesOffset']);
        $t->same(bin2hex('detached-tail'), $tailedSummary['trailingBytesPreviewHex']);
        $t->same(false, $tailedSummary['isSupportedByBoundedReader']);
        $t->same(['eocd-trailing-bytes'], $tailedSummary['issues']);
        $t->same($tailedSummary, $tailedRaw['endOfCentralDirectoryFixedFields']);
        $t->contains('eocd-trailing-bytes', implode(',', $tailedRaw['diagnostics']));
        $t->same(false, $tailedRaw['canInstantiate']);

        $truncatedZip = substr($zip, 0, -8);
        $truncatedSummary = ZipPackage::endOfCentralDirectoryFixedFieldsPreflight($truncatedZip);
        $t->same(true, $truncatedSummary['hasTruncatedPackageComment']);
        $t->same(strlen($comment) - 8, $truncatedSummary['availablePackageCommentBytes']);
        $t->same(8, $truncatedSummary['missingPackageCommentBytes']);
        $t->same(substr($comment, 0, -8), $truncatedSummary['packageComment']);
        $t->same(bin2hex(substr($comment, 0, -8)), $truncatedSummary['packageCommentHex']);
        $t->same(['eocd-comment-truncated'], $truncatedSummary['issues']);
    },

    'preflights trailing bytes after the zip end of central directory before raw import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>EOCD trailing bytes</w:p></w:document>',
                'method' => 8,
            ],
        ], 'review comment');
        $eocdOffset = strrpos($zip, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }
        $trailingBytes = "detached reviewer bytes\n";
        $tailedZip = $zip . $trailingBytes;

        $safeSummary = ZipPackage::endOfCentralDirectoryTrailingBytesPreflight($zip);
        $summary = ZipPackage::endOfCentralDirectoryTrailingBytesPreflight($tailedZip);
        $raw = ZipPackage::rawStrictImportPreflight($tailedZip, 512, 20.0, 512);

        $t->same(true, $safeSummary['hasEndOfCentralDirectoryCandidate']);
        $t->same(false, $safeSummary['hasTrailingBytes']);
        $t->same(0, $safeSummary['trailingByteCount']);
        $t->same(true, $safeSummary['isSupportedByBoundedReader']);
        $t->same([], $safeSummary['issues']);

        $t->same(true, $summary['hasEndOfCentralDirectoryCandidate']);
        $t->same($eocdOffset, $summary['eocdOffset']);
        $t->same(strlen($tailedZip), $summary['archiveLength']);
        $t->same(strlen($zip), $summary['declaredArchiveEndOffset']);
        $t->same(strlen('review comment'), $summary['declaredPackageCommentLength']);
        $t->same(strlen('review comment'), $summary['availablePackageCommentBytes']);
        $t->same(1, $summary['totalEntryCount']);
        $t->same(true, $summary['hasTrailingBytes']);
        $t->same(false, $summary['hasTruncatedComment']);
        $t->same(strlen($trailingBytes), $summary['trailingByteCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['eocd-trailing-bytes'], $summary['issues']);

        $t->same(false, $raw['isValid']);
        $t->same(false, $raw['canInstantiate']);
        $t->same(1, $raw['entryCount']);
        $t->same($summary, $raw['endOfCentralDirectoryTrailingBytes']);
        $t->same(null, $raw['archive']);
        $t->contains('eocd-trailing-bytes', implode(',', $raw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $raw['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($tailedZip));
    },

    'preflights raw eocd central directory offsets before package instantiation' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>EOCD central directory pointer</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "review media bytes\n",
                'method' => 0,
            ],
        ]);
        $basePointer = ZipPackage::endOfCentralDirectoryOffsetPreflight($zip);

        $t->same(true, $basePointer['hasEndOfCentralDirectoryRecord']);
        $t->same(2, $basePointer['totalEntryCount']);
        $t->same('central-directory-header', $basePointer['centralDirectoryStartSignature']);
        $t->same(true, $basePointer['centralDirectoryRangeStartsWithCentralHeader']);
        $t->same(true, $basePointer['centralDirectoryRangeAvailable']);
        $t->same(true, $basePointer['centralDirectoryRangeBeforeEocd']);
        $t->same(true, $basePointer['centralDirectoryEndMatchesEocdOffset']);
        $t->same(true, $basePointer['isSupportedByBoundedReader']);
        $t->same([], $basePointer['issues']);

        $badOffsetZip = $rewriteEndOfCentralDirectory($zip, [
            'centralDirectoryOffset' => 0,
        ]);
        $badPointer = ZipPackage::endOfCentralDirectoryOffsetPreflight($badOffsetZip);
        $badRaw = ZipPackage::rawStrictImportPreflight($badOffsetZip, 2048, 100.0, 2048);

        $t->same(true, $badPointer['hasEndOfCentralDirectoryRecord']);
        $t->same(2, $badPointer['totalEntryCount']);
        $t->same(0, $badPointer['centralDirectoryOffset']);
        $t->same($basePointer['centralDirectorySize'], $badPointer['centralDirectorySize']);
        $t->same('local-file-header', $badPointer['centralDirectoryStartSignature']);
        $t->same('local-file-header', $badPointer['centralDirectoryOffsetLocation']);
        $t->same(false, $badPointer['centralDirectoryRangeStartsWithCentralHeader']);
        $t->same(true, $badPointer['centralDirectoryRangeAvailable']);
        $t->same(true, $badPointer['centralDirectoryRangeBeforeEocd']);
        $t->same(false, $badPointer['centralDirectoryEndMatchesEocdOffset']);
        $t->same(false, $badPointer['isSupportedByBoundedReader']);
        $t->same(['central-directory-offset-not-central-header'], $badPointer['issues']);

        $t->same(false, $badRaw['isValid']);
        $t->same(false, $badRaw['canInstantiate']);
        $t->same(2, $badRaw['entryCount']);
        $t->same(null, $badRaw['archive']);
        $t->same($badPointer, $badRaw['endOfCentralDirectoryOffset']);
        $t->contains('central-directory-offset-not-central-header', implode(',', $badRaw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $badRaw['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($badOffsetZip));
    },

    'preflights zip central directory inventory counts before package import' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $centralExtra = pack('vva*', 0xcafe, strlen('inventory'), 'inventory');
        $entryComment = 'inventory review';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>central inventory</w:p></w:document>',
                'method' => 8,
                'centralExtra' => $centralExtra,
                'localExtra' => '',
                'comment' => $entryComment,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
            ],
        ]);
        $summary = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $package = ZipPackage::fromString($zip);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $first = $summary['entries'][0];
        $second = $summary['entries'][1];
        $firstRecordBytes = 46 + strlen('word/document.xml') + strlen($centralExtra) + strlen($entryComment);
        $secondRecordBytes = 46 + strlen('word/media/review.png');

        $t->same(2, $summary['declaredEntryCount']);
        $t->same(2, $summary['diskEntryCount']);
        $t->same(2, $summary['scannedEntryCount']);
        $t->same(2, $summary['entryCount']);
        $t->same($summary['centralDirectorySize'], $summary['scannedCentralDirectoryBytes']);
        $t->same($firstRecordBytes + $secondRecordBytes, $summary['centralDirectoryEntryRecordBytes']);
        $t->same(92, $summary['centralDirectoryFixedHeaderBytes']);
        $t->same(
            strlen('word/document.xml') + strlen($centralExtra) + strlen($entryComment) + strlen('word/media/review.png'),
            $summary['centralDirectoryVariableFieldBytes']
        );
        $t->same(strlen('word/document.xml') + strlen('word/media/review.png'), $summary['centralDirectoryNameBytes']);
        $t->same(strlen($centralExtra), $summary['centralDirectoryExtraFieldBytes']);
        $t->same(strlen($entryComment), $summary['centralDirectoryCommentBytes']);
        $t->same(1, $summary['centralExtraFieldEntryCount']);
        $t->same(1, $summary['entryCommentCount']);
        $t->same(true, $summary['hasCentralDirectoryVariableFields']);
        $t->same(true, $summary['hasCentralExtraFields']);
        $t->same(true, $summary['hasEntryComments']);
        $t->same(0, $summary['centralDirectoryTailBytes']);
        $t->same(false, $summary['hasEntryCountMismatch']);
        $t->same(0, $summary['entryCountDelta']);
        $t->same(0, $summary['extraScannedEntryCount']);
        $t->same(0, $summary['missingDeclaredEntryCount']);
        $t->same(null, $summary['entryCountMismatchKind']);
        $t->same(false, $summary['hasCentralDirectorySignature']);
        $t->same(null, $summary['centralDirectorySignature']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);
        $t->same(['word/document.xml', 'word/media/review.png'], array_column($summary['entries'], 'name'));
        $t->same([0, 1], array_column($summary['entries'], 'centralDirectoryIndex'));
        $t->same($summary['centralDirectoryOffset'], $first['offset']);
        $t->same($first['offset'], $first['recordOffset']);
        $t->same($firstRecordBytes, $first['recordLength']);
        $t->same($first['offset'], $first['fixedHeaderOffset']);
        $t->same(46, $first['fixedHeaderLength']);
        $t->same($first['offset'] + 46, $first['variableFieldsOffset']);
        $t->same($firstRecordBytes - 46, $first['variableFieldsLength']);
        $t->same($first['variableFieldsOffset'], $first['rawNameOffset']);
        $t->same(strlen('word/document.xml'), $first['rawNameLength']);
        $t->same($first['rawNameOffset'] + strlen('word/document.xml'), $first['centralExtraFieldOffset']);
        $t->same(strlen($centralExtra), $first['centralExtraFieldLength']);
        $t->same($first['centralExtraFieldOffset'] + strlen($centralExtra), $first['rawCommentOffset']);
        $t->same(strlen($entryComment), $first['rawCommentLength']);
        $t->same($first['rawCommentOffset'] + strlen($entryComment), $first['recordEnd']);
        $t->same($first['recordEnd'], $second['offset']);
        $t->same($secondRecordBytes, $second['recordLength']);
        $t->same(0, $second['centralExtraFieldLength']);
        $t->same(0, $second['rawCommentLength']);
        $t->same($summary, $rawStrict['centralDirectoryInventory']);
        $t->same($summary, $strict['centralDirectoryInventory']);
        $t->contains('package-or-entry-comments', implode(',', $rawStrict['diagnostics']));
        $t->same(true, $package->has('/word/document.xml'));

        $declaredTooLow = $rewriteEndOfCentralDirectory($zip, [
            'diskEntryCount' => 1,
            'totalEntryCount' => 1,
        ]);
        $lowSummary = ZipPackage::centralDirectoryInventoryPreflight($declaredTooLow);
        $t->same(1, $lowSummary['declaredEntryCount']);
        $t->same(2, $lowSummary['scannedEntryCount']);
        $t->same(true, $lowSummary['hasEntryCountMismatch']);
        $t->same(1, $lowSummary['entryCountDelta']);
        $t->same(1, $lowSummary['extraScannedEntryCount']);
        $t->same(0, $lowSummary['missingDeclaredEntryCount']);
        $t->same('declared-too-low', $lowSummary['entryCountMismatchKind']);
        $t->same(false, $lowSummary['isSupportedByBoundedReader']);
        $t->same(['central-directory-entry-count-mismatch'], $lowSummary['issues']);
        $t->same(['word/document.xml', 'word/media/review.png'], array_column($lowSummary['entries'], 'name'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($declaredTooLow));

        $declaredTooHigh = $rewriteEndOfCentralDirectory($zip, [
            'diskEntryCount' => 3,
            'totalEntryCount' => 3,
        ]);
        $highSummary = ZipPackage::centralDirectoryInventoryPreflight($declaredTooHigh);
        $t->same(3, $highSummary['declaredEntryCount']);
        $t->same(2, $highSummary['scannedEntryCount']);
        $t->same(true, $highSummary['hasEntryCountMismatch']);
        $t->same(-1, $highSummary['entryCountDelta']);
        $t->same(0, $highSummary['extraScannedEntryCount']);
        $t->same(1, $highSummary['missingDeclaredEntryCount']);
        $t->same('declared-too-high', $highSummary['entryCountMismatchKind']);
        $t->same(false, $highSummary['isSupportedByBoundedReader']);
        $t->same(['central-directory-entry-count-mismatch'], $highSummary['issues']);
        $t->same(0, $highSummary['centralDirectoryTailBytes']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($declaredTooHigh));
    },

    'preflights zip central directory inventory byte spans before package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $extra = pack('vva*', 0xcafe, strlen('inventory-span'), 'inventory-span');
        $contentTypes = '<Types><Default Extension="xml" ContentType="application/xml"/></Types>';
        $documentXml = '<w:document><w:p>inventory byte spans</w:p></w:document>';
        $zip = $buildZipPackage([
            [
                'name' => '[Content_Types].xml',
                'data' => $contentTypes,
                'method' => 0,
                'localExtra' => $extra,
                'centralExtra' => $extra,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $summary = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $first = $summary['entries'][0];
        $second = $summary['entries'][1];
        $firstRecordBytes = 46 + strlen('[Content_Types].xml') + strlen($extra);
        $secondRecordBytes = 46 + strlen('word/document.xml');

        $t->same($summary, $rawStrict['centralDirectoryInventory']);
        $t->same($summary, $strict['centralDirectoryInventory']);
        $t->same(true, $rawStrict['isValid']);
        $t->same(true, $strict['isValid']);
        $t->same($firstRecordBytes + $secondRecordBytes, $summary['centralDirectoryEntryRecordBytes']);
        $t->same(92, $summary['centralDirectoryFixedHeaderBytes']);
        $t->same(strlen('[Content_Types].xml') + strlen($extra) + strlen('word/document.xml'), $summary['centralDirectoryVariableFieldBytes']);
        $t->same(strlen('[Content_Types].xml') + strlen('word/document.xml'), $summary['centralDirectoryNameBytes']);
        $t->same(strlen($extra), $summary['centralDirectoryExtraFieldBytes']);
        $t->same(0, $summary['centralDirectoryCommentBytes']);
        $t->same(1, $summary['centralExtraFieldEntryCount']);
        $t->same(0, $summary['entryCommentCount']);
        $t->same(true, $summary['hasCentralDirectoryVariableFields']);
        $t->same(true, $summary['hasCentralExtraFields']);
        $t->same(false, $summary['hasEntryComments']);
        $t->same($summary['centralDirectoryOffset'], $first['recordOffset']);
        $t->same($firstRecordBytes, $first['recordLength']);
        $t->same($first['recordOffset'] + 46, $first['variableFieldsOffset']);
        $t->same(strlen('[Content_Types].xml'), $first['rawNameLength']);
        $t->same($first['rawNameOffset'] + strlen('[Content_Types].xml'), $first['centralExtraFieldOffset']);
        $t->same(strlen($extra), $first['centralExtraFieldLength']);
        $t->same($first['centralExtraFieldOffset'] + strlen($extra), $first['rawCommentOffset']);
        $t->same(0, $first['rawCommentLength']);
        $t->same($first['rawCommentOffset'], $first['recordEnd']);
        $t->same($first['recordEnd'], $second['recordOffset']);
        $t->same($secondRecordBytes, $second['recordLength']);
        $t->same($second['recordOffset'] + 46, $second['variableFieldsOffset']);
        $t->same(0, $second['centralExtraFieldLength']);
        $t->same(0, $second['rawCommentLength']);
        $t->same($second['recordOffset'] + $secondRecordBytes, $second['recordEnd']);
    },

    'preflights duplicate zip central directory names before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/media/review.txt',
                'data' => "first review media bytes\n",
                'method' => 0,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "second spoofed review media bytes\n",
                'method' => 0,
            ],
        ]);
        $summary = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 100.0, 1024);
        $group = $summary['duplicateEntryNameGroups'][0];
        $rawGroup = $summary['duplicateEntryRawNameGroups'][0];

        $t->same(2, $summary['declaredEntryCount']);
        $t->same(2, $summary['scannedEntryCount']);
        $t->same(false, $summary['hasEntryCountMismatch']);
        $t->same(true, $summary['hasDuplicateEntryNames']);
        $t->same(1, $summary['duplicateEntryNameGroupCount']);
        $t->same(2, $summary['duplicateEntryNameEntryCount']);
        $t->same(1, $summary['duplicateEntryRawNameGroupCount']);
        $t->same(2, $summary['duplicateEntryRawNameEntryCount']);
        $t->same('word/media/review.txt', $group['name']);
        $t->same(2, $group['count']);
        $t->same([0, 1], $group['centralDirectoryIndexes']);
        $t->same(true, $group['localHeaderOffsets'][0] < $group['localHeaderOffsets'][1]);
        $t->same('word/media/review.txt', $rawGroup['rawName']);
        $t->same($group['centralDirectoryIndexes'], $rawGroup['centralDirectoryIndexes']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['duplicate-central-directory-entry-names'], $summary['issues']);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(2, $rawStrict['entryCount']);
        $t->same($summary, $rawStrict['centralDirectoryInventory']);
        $t->same(0, $rawStrict['localHeaderSpans']['issueEntryCount']);
        $t->contains('central-directory-inventory-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('duplicate-central-directory-entry-names', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights zip central directory variable field byte provenance before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $centralExtra = pack('vva*', 0xcafe, strlen('review-extra'), 'review-extra');
        $entryComment = 'central entry review';
        $packageComment = 'central package review';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>central variable fields</w:p></w:document>',
                'method' => 8,
                'localExtra' => $centralExtra,
                'centralExtra' => $centralExtra,
                'comment' => $entryComment,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => "central variable field media\n",
                'method' => 0,
            ],
        ], $packageComment);
        $eocdOffset = strrpos($zip, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }

        $summary = ZipPackage::centralDirectoryVariableFieldsPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $package = ZipPackage::fromString($zip);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $first = $summary['entries'][0];
        $second = $summary['entries'][1];

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['declaredEntryCount']);
        $t->same($package->centralDirectoryOffset(), $summary['centralDirectoryOffset']);
        $t->same($eocdOffset, $summary['eocdOffset']);
        $t->same($eocdOffset + 22, $summary['packageCommentOffset']);
        $t->same(strlen($packageComment), $summary['packageCommentLength']);
        $t->same(strlen($zip), $summary['packageCommentEnd']);
        $t->same(strlen('word/document.xml') + strlen('word/media/review.bin'), $summary['centralDirectoryNameBytes']);
        $t->same(strlen($centralExtra), $summary['centralDirectoryExtraFieldBytes']);
        $t->same(strlen($entryComment), $summary['centralDirectoryCommentBytes']);
        $t->same(strlen($centralExtra) + strlen($entryComment), $summary['centralDirectoryReviewFieldBytes']);
        $t->same(1, $summary['centralExtraFieldEntryCount']);
        $t->same(1, $summary['entryCommentCount']);
        $t->same(1, $summary['reviewFieldEntryCount']);
        $t->same(true, $summary['hasCentralDirectoryVariableFields']);
        $t->same(true, $summary['hasCentralExtraFields']);
        $t->same(true, $summary['hasEntryComments']);
        $t->same(true, $summary['hasCentralDirectoryReviewFields']);
        $t->same(true, $summary['hasPackageComment']);
        $t->same($summary['centralDirectoryEnd'], $summary['scanStoppedOffset']);
        $t->same(false, $summary['hasUnexpectedCentralDirectoryTail']);
        $t->same(null, $summary['unexpectedRecordOffset']);
        $t->same(null, $summary['unexpectedRecordSignatureHex']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);

        $t->same('word/document.xml', $first['name']);
        $t->same(0, $first['centralDirectoryIndex']);
        $t->same($summary['centralDirectoryOffset'], $first['recordOffset']);
        $t->same($first['recordOffset'], $first['fixedHeaderOffset']);
        $t->same(46, $first['fixedHeaderLength']);
        $t->same($first['recordOffset'] + 46, $first['variableFieldsOffset']);
        $t->same(strlen('word/document.xml') + strlen($centralExtra) + strlen($entryComment), $first['variableFieldsLength']);
        $t->same($first['variableFieldsOffset'], $first['rawNameOffset']);
        $t->same(strlen('word/document.xml'), $first['rawNameLength']);
        $t->same($first['rawNameOffset'] + $first['rawNameLength'], $first['centralExtraFieldOffset']);
        $t->same(strlen($centralExtra), $first['centralExtraFieldLength']);
        $t->same($first['centralExtraFieldOffset'] + $first['centralExtraFieldLength'], $first['rawCommentOffset']);
        $t->same(strlen($entryComment), $first['rawCommentLength']);
        $t->same($first['rawCommentOffset'] + $first['rawCommentLength'], $first['recordEnd']);
        $t->same(strlen($centralExtra) + strlen($entryComment), $first['reviewFieldBytes']);
        $t->same(true, $first['hasCentralExtraFields']);
        $t->same(true, $first['hasEntryComment']);
        $t->same($first, $summary['largestReviewFieldEntry']);

        $t->same('word/media/review.bin', $second['name']);
        $t->same($first['recordEnd'], $second['recordOffset']);
        $t->same(0, $second['centralExtraFieldLength']);
        $t->same(0, $second['rawCommentLength']);
        $t->same(0, $second['reviewFieldBytes']);
        $t->same(false, $second['hasCentralExtraFields']);
        $t->same(false, $second['hasEntryComment']);
        $t->same($summary, $rawStrict['centralDirectoryVariableFields']);
        $t->same($summary, $strict['centralDirectoryVariableFields']);
    },

    'preflights zip local header variable field byte provenance before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $localExtra = pack('vva*', 0xcafe, strlen('local-review'), 'local-review');
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>local variable fields</w:p></w:document>',
                'method' => 8,
                'localExtra' => $localExtra,
                'centralExtra' => $localExtra,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => "local variable field media\n",
                'method' => 0,
            ],
        ]);

        $summary = ZipPackage::localHeaderVariableFieldsPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $package = ZipPackage::fromString($zip);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $localHeaders = $package->localHeaderPreflight();
        $first = $summary['entries'][0];
        $second = $summary['entries'][1];

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same($package->centralDirectoryOffset(), $summary['centralDirectoryOffset']);
        $t->same(strlen('word/document.xml') + strlen('word/media/review.bin'), $summary['localHeaderNameBytes']);
        $t->same(strlen($localExtra), $summary['localHeaderExtraFieldBytes']);
        $t->same(strlen('word/document.xml') + strlen('word/media/review.bin') + strlen($localExtra), $summary['localHeaderVariableFieldBytes']);
        $t->same(1, $summary['localExtraFieldEntryCount']);
        $t->same(0, $summary['skippedArchiveExtraDataRecordCount']);
        $t->same(0, $summary['skippedArchiveExtraDataRecordBytes']);
        $t->same([], $summary['skippedArchiveExtraDataRecords']);
        $t->same(true, $summary['hasLocalHeaderVariableFields']);
        $t->same(true, $summary['hasLocalExtraFields']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);

        $t->same('word/document.xml', $first['name']);
        $t->same('word/document.xml', $first['centralName']);
        $t->same(0, $first['localHeaderOffset']);
        $t->same($first['localHeaderOffset'], $first['fixedHeaderOffset']);
        $t->same(30, $first['fixedHeaderLength']);
        $t->same($first['localHeaderOffset'] + 30, $first['variableFieldsOffset']);
        $t->same(strlen('word/document.xml') + strlen($localExtra), $first['variableFieldsLength']);
        $t->same($first['variableFieldsOffset'], $first['rawNameOffset']);
        $t->same(strlen('word/document.xml'), $first['rawNameLength']);
        $t->same($first['rawNameOffset'] + $first['rawNameLength'], $first['localExtraFieldOffset']);
        $t->same(strlen($localExtra), $first['localExtraFieldLength']);
        $t->same($first['localExtraFieldOffset'] + $first['localExtraFieldLength'], $first['dataStart']);
        $t->same(true, $first['hasLocalExtraFields']);

        $t->same('word/media/review.bin', $second['name']);
        $t->same('word/media/review.bin', $second['centralName']);
        $t->same(0, $second['localExtraFieldLength']);
        $t->same(false, $second['hasLocalExtraFields']);
        $t->same($second['localHeaderOffset'] + 30, $second['variableFieldsOffset']);
        $t->same(strlen('word/media/review.bin'), $second['variableFieldsLength']);
        $t->same($second['variableFieldsOffset'] + $second['variableFieldsLength'], $second['dataStart']);

        $t->same($summary, $rawStrict['localHeaderVariableFields']);
        $t->same($summary, $strict['localHeaderVariableFields']);
        $t->same($summary['localHeaderVariableFieldBytes'], $localHeaders['localHeaderVariableFieldBytes']);
        $t->same($first['dataStart'], $localHeaders['entries'][0]['dataStart']);
    },

    'preflights zip local header variable fields across inter-entry archive extra records' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $localExtra = pack('vva*', 0xcafe, strlen('local-review'), 'local-review');
        $archiveExtraData = 'local-header-archive-extra';
        $archiveExtraRecord = "PK\x06\x08" . pack('V', strlen($archiveExtraData)) . $archiveExtraData;
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>local fields across archive extra data</w:p></w:document>',
                'method' => 8,
                'localExtra' => $localExtra,
                'centralExtra' => $localExtra,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "review media bytes\n",
                'method' => 0,
            ],
        ]);
        $eocdOffset = strrpos($zip, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }
        $centralDirectorySize = unpack('Vvalue', substr($zip, $eocdOffset + 12, 4))['value'];
        $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
        $firstCentralNameLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 28, 2))['value'];
        $firstCentralExtraLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 30, 2))['value'];
        $firstCentralCommentLength = unpack('vvalue', substr($zip, $centralDirectoryOffset + 32, 2))['value'];
        $interEntryOffset = $centralDirectoryOffset
            + 46
            + $firstCentralNameLength
            + $firstCentralExtraLength
            + $firstCentralCommentLength;
        $interEntryRecordZip = substr($zip, 0, $interEntryOffset)
            . $archiveExtraRecord
            . substr($zip, $interEntryOffset);
        $interEntryRecordZip = $rewriteEndOfCentralDirectory($interEntryRecordZip, [
            'centralDirectorySize' => $centralDirectorySize + strlen($archiveExtraRecord),
        ]);

        $summary = ZipPackage::localHeaderVariableFieldsPreflight($interEntryRecordZip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($interEntryRecordZip, 4096, 100.0, 4096);
        $archiveExtraSummary = ZipPackage::archiveExtraDataRecordPreflight($interEntryRecordZip);
        $skippedRecord = $summary['skippedArchiveExtraDataRecords'][0];
        $diagnostics = implode(',', $rawStrict['diagnostics']);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same(strlen('word/document.xml') + strlen('word/media/review.png'), $summary['localHeaderNameBytes']);
        $t->same(strlen($localExtra), $summary['localHeaderExtraFieldBytes']);
        $t->same(1, $summary['localExtraFieldEntryCount']);
        $t->same(1, $summary['skippedArchiveExtraDataRecordCount']);
        $t->same(strlen($archiveExtraRecord), $summary['skippedArchiveExtraDataRecordBytes']);
        $t->same($interEntryOffset, $skippedRecord['offset']);
        $t->same($interEntryOffset + 8, $skippedRecord['dataOffset']);
        $t->same(strlen($archiveExtraData), $skippedRecord['dataLength']);
        $t->same($interEntryOffset + strlen($archiveExtraRecord), $skippedRecord['endOffset']);
        $t->same('before-central-directory-entry', $skippedRecord['location']);
        $t->same(['archive-extra-data-record'], $skippedRecord['issues']);
        $t->same('word/document.xml', $summary['entries'][0]['name']);
        $t->same('word/media/review.png', $summary['entries'][1]['name']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);

        $t->same($archiveExtraSummary['archiveExtraDataRecords'], $summary['skippedArchiveExtraDataRecords']);
        $t->same($summary, $rawStrict['localHeaderVariableFields']);
        $t->contains('archive-extra-data-records', $diagnostics);
        $t->same(false, str_contains($diagnostics, 'raw-local-header-variable-fields-preflight-failed'));
    },

    'preflights raw zip local header extra field record provenance before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentExtra = pack('vva*', 0xcafe, strlen('local-record'), 'local-record')
            . pack('vva*', 0xbeef, strlen('audit'), 'audit');
        $mediaExtra = pack('vva*', 0x1234, strlen('media'), 'media');
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>local extra record provenance</w:p></w:document>',
                'method' => 8,
                'localExtra' => $documentExtra,
                'centralExtra' => $documentExtra,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => "local extra record media bytes\n",
                'method' => 0,
                'localExtra' => $mediaExtra,
                'centralExtra' => $mediaExtra,
            ],
        ]);

        $summary = ZipPackage::localHeaderVariableFieldsPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $package = ZipPackage::fromString($zip);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $localHeaders = $package->localHeaderPreflight();
        $first = $summary['entries'][0];
        $second = $summary['entries'][1];
        $firstRecord = $first['localExtraFieldRecords'][0];
        $secondRecord = $first['localExtraFieldRecords'][1];
        $mediaRecord = $second['localExtraFieldRecords'][0];

        $t->same(2, $summary['localExtraFieldEntryCount']);
        $t->same(3, $summary['localExtraFieldRecordCount']);
        $t->same([0xcafe, 0xbeef, 0x1234], $summary['localExtraFieldRecordIds']);
        $t->same($first, $summary['largestLocalExtraFieldEntry']);
        $t->same($summary, $rawStrict['localHeaderVariableFields']);
        $t->same($summary, $strict['localHeaderVariableFields']);

        $t->same('word/document.xml', $first['name']);
        $t->same(strlen($documentExtra), $first['localExtraFieldLength']);
        $t->same(2, $first['localExtraFieldRecordCount']);
        $t->same([0xcafe, 0xbeef], $first['localExtraFieldIds']);
        $t->same([], $first['localExtraFieldStructureIssues']);
        $t->same(0xcafe, $firstRecord['id']);
        $t->same('cafe', $firstRecord['idHex']);
        $t->same(strlen('local-record'), $firstRecord['declaredDataLength']);
        $t->same($first['localExtraFieldOffset'], $firstRecord['localExtraFieldRecordOffset']);
        $t->same($firstRecord['localExtraFieldRecordOffset'] + 4, $firstRecord['localExtraFieldDataOffset']);
        $t->same($firstRecord['localExtraFieldDataOffset'] + strlen('local-record'), $firstRecord['localExtraFieldRecordEnd']);
        $t->same(false, $firstRecord['isTruncated']);
        $t->same(null, $firstRecord['issue']);
        $t->same(0xbeef, $secondRecord['id']);
        $t->same($firstRecord['localExtraFieldRecordEnd'], $secondRecord['localExtraFieldRecordOffset']);
        $t->same($first['dataStart'], $secondRecord['localExtraFieldRecordEnd']);

        $t->same('word/media/review.bin', $second['name']);
        $t->same(1, $second['localExtraFieldRecordCount']);
        $t->same([0x1234], $second['localExtraFieldIds']);
        $t->same('1234', $mediaRecord['idHex']);
        $t->same($second['localExtraFieldOffset'], $mediaRecord['localExtraFieldRecordOffset']);
        $t->same($second['dataStart'], $mediaRecord['localExtraFieldRecordEnd']);

        $t->same($summary['localExtraFieldRecordIds'], $localHeaders['localExtraFieldRecordIds']);
        $t->same($first['localExtraFieldRecords'], $localHeaders['entries'][0]['localExtraFieldRecords']);
        $t->same($second['localExtraFieldRecords'], $localHeaders['entries'][1]['localExtraFieldRecords']);
    },

    'preflights zip central directory recovery metadata before package import' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>central recovery</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "central recovery attachment\n",
                'method' => 0,
            ],
        ]);
        $base = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $eocdOffset = strrpos($zip, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }

        $gapPayload = "hidden central directory gap\n";
        $gapBytes = "PK\x06\x08" . pack('V', strlen($gapPayload)) . $gapPayload;
        $gapZip = substr($zip, 0, $eocdOffset) . $gapBytes . substr($zip, $eocdOffset);
        $gapSummary = ZipPackage::centralDirectoryInventoryPreflight($gapZip);
        $gapRaw = ZipPackage::rawStrictImportPreflight($gapZip, 2048, 100.0, 2048);

        $t->same(2, $gapSummary['scannedEntryCount']);
        $t->same(true, $gapSummary['scanCompletedCentralDirectory']);
        $t->same($base['centralDirectoryEnd'], $gapSummary['scanStoppedOffset']);
        $t->same(false, $gapSummary['hasUnexpectedCentralDirectoryTail']);
        $t->same(0, $gapSummary['centralDirectoryTailBytes']);
        $t->same(null, $gapSummary['unexpectedRecordOffset']);
        $t->same(null, $gapSummary['unexpectedRecordSignatureHex']);
        $t->same(true, $gapSummary['hasCentralDirectoryEocdGap']);
        $t->same($base['centralDirectoryEnd'], $gapSummary['centralDirectoryEocdGapOffset']);
        $t->same(strlen($gapBytes), $gapSummary['centralDirectoryEocdGapBytes']);
        $t->same(false, $gapSummary['isCentralDirectoryEocdGapExplainedBySignature']);
        $t->same(['central-directory-eocd-gap'], $gapSummary['issues']);
        $t->same(false, $gapRaw['isValid']);
        $t->same(false, $gapRaw['canInstantiate']);
        $t->same($gapSummary, $gapRaw['centralDirectoryInventory']);
        $t->contains('central-directory-eocd-gap', implode(',', $gapRaw['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($gapZip));

        $tailPayload = "hidden central tail\n";
        $tailBytes = "PK\x06\x08" . pack('V', strlen($tailPayload)) . $tailPayload;
        $tailZip = substr($zip, 0, $eocdOffset) . $tailBytes . substr($zip, $eocdOffset);
        $tailZip = $rewriteEndOfCentralDirectory($tailZip, [
            'centralDirectorySize' => $base['centralDirectorySize'] + strlen($tailBytes),
        ]);
        $tailSummary = ZipPackage::centralDirectoryInventoryPreflight($tailZip);
        $tailRaw = ZipPackage::rawStrictImportPreflight($tailZip, 2048, 100.0, 2048);

        $t->same(2, $tailSummary['scannedEntryCount']);
        $t->same(true, $tailSummary['scanCompletedCentralDirectory']);
        $t->same($tailSummary['centralDirectoryEnd'], $tailSummary['scanStoppedOffset']);
        $t->same(false, $tailSummary['hasUnexpectedCentralDirectoryTail']);
        $t->same(0, $tailSummary['centralDirectoryTailBytes']);
        $t->same(null, $tailSummary['unexpectedRecordOffset']);
        $t->same(null, $tailSummary['unexpectedRecordSignatureHex']);
        $t->same(false, $tailSummary['hasCentralDirectoryEocdGap']);
        $t->same(null, $tailSummary['centralDirectoryEocdGapOffset']);
        $t->same(0, $tailSummary['centralDirectoryEocdGapBytes']);
        $t->same(false, $tailSummary['isCentralDirectoryEocdGapExplainedBySignature']);
        $t->same(1, $tailSummary['skippedArchiveExtraDataRecordCount']);
        $t->same(strlen($tailBytes), $tailSummary['skippedArchiveExtraDataRecordBytes']);
        $t->same($base['centralDirectoryEnd'], $tailSummary['skippedArchiveExtraDataRecords'][0]['offset']);
        $t->same('central-directory-tail', $tailSummary['skippedArchiveExtraDataRecords'][0]['location']);
        $t->same(['archive-extra-data-record'], $tailSummary['skippedArchiveExtraDataRecords'][0]['issues']);
        $t->same([], $tailSummary['issues']);
        $t->same(false, $tailRaw['isValid']);
        $t->same(false, $tailRaw['canInstantiate']);
        $t->same($tailSummary, $tailRaw['centralDirectoryInventory']);
        $t->contains('archive-extra-data-records', implode(',', $tailRaw['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($tailZip));
    },

    'preflights recoverable central headers beyond understated directory size' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>understated central directory</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/recoverable-one.txt',
                'data' => "recoverable central entry one\n",
                'method' => 0,
            ],
            [
                'name' => 'word/media/recoverable-two.txt',
                'data' => "recoverable central entry two\n",
                'method' => 0,
            ],
        ]);
        $base = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $understatedCentralDirectorySize = $base['entries'][0]['recordEnd'] - $base['centralDirectoryOffset'];
        $understatedZip = $rewriteEndOfCentralDirectory($zip, [
            'centralDirectorySize' => $understatedCentralDirectorySize,
        ]);
        $summary = ZipPackage::centralDirectoryInventoryPreflight($understatedZip);
        $repairPlan = ZipPackage::centralDirectoryRepairPlanPreflight($understatedZip);
        $raw = ZipPackage::rawStrictImportPreflight($understatedZip, 2048, 100.0, 2048);

        $t->same(3, $summary['declaredEntryCount']);
        $t->same(1, $summary['scannedEntryCount']);
        $t->same(true, $summary['scanCompletedCentralDirectory']);
        $t->same(false, $summary['hasUnexpectedCentralDirectoryTail']);
        $t->same(0, $summary['centralDirectoryTailBytes']);
        $t->same($base['entries'][1]['offset'], $summary['scanStoppedOffset']);
        $t->same(true, $summary['hasCentralDirectoryEocdGap']);
        $t->same($base['entries'][1]['offset'], $summary['centralDirectoryEocdGapOffset']);
        $t->same($base['eocdOffset'] - $base['entries'][1]['offset'], $summary['centralDirectoryEocdGapBytes']);
        $t->same('central-directory-header', $summary['centralDirectoryEocdGapSignature']);
        $t->same(16, $summary['centralDirectoryEocdGapPreviewByteCount']);
        $t->same('504b0102', substr($summary['centralDirectoryEocdGapPreviewHex'], 0, 8));
        $t->same(true, $summary['hasEntryCountMismatch']);
        $t->same(-2, $summary['entryCountDelta']);
        $t->same(0, $summary['extraScannedEntryCount']);
        $t->same(2, $summary['missingDeclaredEntryCount']);
        $t->same('declared-too-high', $summary['entryCountMismatchKind']);
        $t->same(true, $summary['hasRecoverableCentralDirectoryGapEntries']);
        $t->same(2, $summary['recoverableGapEntryCount']);
        $t->same(
            ['word/media/recoverable-one.txt', 'word/media/recoverable-two.txt'],
            array_column($summary['recoverableGapEntries'], 'name')
        );
        $t->same([1, 2], array_column($summary['recoverableGapEntries'], 'centralDirectoryIndex'));
        $t->same(
            [$base['entries'][1]['offset'], $base['entries'][2]['offset']],
            array_column($summary['recoverableGapEntries'], 'offset')
        );
        $t->same(
            [$base['entries'][1]['localHeaderOffset'], $base['entries'][2]['localHeaderOffset']],
            array_column($summary['recoverableGapEntries'], 'localHeaderOffset')
        );
        $t->same(3, $repairPlan['declaredEntryCount']);
        $t->same(1, $repairPlan['scannedEntryCount']);
        $t->same(2, $repairPlan['recoverableGapEntryCount']);
        $t->same(3, $repairPlan['plannedEntryCount']);
        $t->same(true, $repairPlan['plannedMatchesDeclaredEntryCount']);
        $t->same($base['centralDirectoryOffset'], $repairPlan['centralDirectoryOffset']);
        $t->same($understatedCentralDirectorySize, $repairPlan['declaredCentralDirectorySize']);
        $t->same($base['centralDirectorySize'], $repairPlan['correctedCentralDirectorySize']);
        $t->same($base['eocdOffset'] - $base['entries'][1]['offset'], $repairPlan['recoveredGapBytes']);
        $t->same(0, $repairPlan['unrecoveredGapBytes']);
        $t->same(true, $repairPlan['gapFullyRecovered']);
        $t->same(true, $repairPlan['repairAvailable']);
        $t->same('review-only-central-directory-size-repair', $repairPlan['policy']);
        $t->same(false, $repairPlan['isSupportedByBoundedReader']);
        $t->same(
            [
                'central-directory-repair-plan-review',
                'central-directory-size-understatement-repair-available',
            ],
            $repairPlan['issues']
        );
        $t->same(0, $repairPlan['duplicatePlannedEntryNameGroupCount']);
        $t->same(0, $repairPlan['duplicatePlannedRawNameGroupCount']);
        $t->same(0, $repairPlan['duplicatePlannedLocalHeaderOffsetGroupCount']);
        $t->same(['word/document.xml'], array_column($repairPlan['retainedEntries'], 'name'));
        $t->same(
            ['word/media/recoverable-one.txt', 'word/media/recoverable-two.txt'],
            array_column($repairPlan['recoverableEntries'], 'name')
        );
        $t->same(
            [
                'word/document.xml',
                'word/media/recoverable-one.txt',
                'word/media/recoverable-two.txt',
            ],
            array_column($repairPlan['plannedEntries'], 'name')
        );
        $t->same('retain-declared-central-directory-entry', $repairPlan['plannedEntries'][0]['action']);
        $t->same('append-recoverable-gap-central-directory-entry', $repairPlan['plannedEntries'][1]['action']);
        $t->same('declared-central-directory', $repairPlan['plannedEntries'][0]['source']);
        $t->same('central-directory-eocd-gap', $repairPlan['plannedEntries'][1]['source']);
        $t->same($summary, $repairPlan['inventory']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(
            [
                'central-directory-entry-count-mismatch',
                'central-directory-eocd-gap',
                'central-directory-eocd-gap-central-headers',
            ],
            $summary['issues']
        );
        $t->same(false, $raw['isValid']);
        $t->same(false, $raw['canInstantiate']);
        $t->same($summary, $raw['centralDirectoryInventory']);
        $t->same($repairPlan, $raw['centralDirectoryRepairPlan']);
        $t->contains('central-directory-inventory-issues', implode(',', $raw['diagnostics']));
        $t->contains('central-directory-eocd-gap-central-headers', implode(',', $raw['diagnostics']));
        $t->contains('central-directory-repair-plan-review', implode(',', $raw['diagnostics']));
        $t->contains('central-directory-size-understatement-repair-available', implode(',', $raw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $raw['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($understatedZip));

        $cleanRepairPlan = ZipPackage::centralDirectoryRepairPlanPreflight($zip);
        $t->same(false, $cleanRepairPlan['repairAvailable']);
        $t->same('no-central-directory-repair-needed', $cleanRepairPlan['policy']);
        $t->same(true, $cleanRepairPlan['isSupportedByBoundedReader']);
        $t->same([], $cleanRepairPlan['issues']);
    },

    'keeps central directory repair plans incomplete when gap bytes remain' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>partial central repair</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/recoverable.txt',
                'data' => "recoverable central entry\n",
                'method' => 0,
            ],
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 8,
            ],
        ]);
        $base = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $gapTailPayload = 'review-only central repair tail';
        $gapTail = "PK\x06\x08" . pack('V', strlen($gapTailPayload)) . $gapTailPayload;
        $zipWithGapTail = substr($zip, 0, $base['eocdOffset']) . $gapTail . substr($zip, $base['eocdOffset']);
        $understatedZip = $rewriteEndOfCentralDirectory($zipWithGapTail, [
            'centralDirectorySize' => $base['entries'][0]['recordEnd'] - $base['centralDirectoryOffset'],
        ]);
        $summary = ZipPackage::centralDirectoryInventoryPreflight($understatedZip);
        $repairPlan = ZipPackage::centralDirectoryRepairPlanPreflight($understatedZip);
        $raw = ZipPackage::rawStrictImportPreflight($understatedZip, 2048, 100.0, 2048);

        $t->same(3, $summary['declaredEntryCount']);
        $t->same(1, $summary['scannedEntryCount']);
        $t->same(2, $summary['recoverableGapEntryCount']);
        $t->same(true, $summary['hasRecoverableCentralDirectoryGapEntries']);
        $t->same(strlen($gapTail), $repairPlan['unrecoveredGapBytes']);
        $t->same(false, $repairPlan['gapFullyRecovered']);
        $t->same(false, $repairPlan['repairAvailable']);
        $t->same('central-directory-repair-not-complete', $repairPlan['policy']);
        $t->same(false, $repairPlan['isSupportedByBoundedReader']);
        $t->same(
            [
                'central-directory-repair-plan-review',
                'central-directory-repair-not-complete',
                'central-directory-repair-gap-unrecovered',
            ],
            $repairPlan['issues']
        );
        $t->same(3, $repairPlan['plannedEntryCount']);
        $t->same(true, $repairPlan['plannedMatchesDeclaredEntryCount']);
        $t->same(
            ['word/document.xml', 'word/media/recoverable.txt', 'word/styles.xml'],
            array_column($repairPlan['plannedEntries'], 'name')
        );
        $t->same($base['centralDirectorySize'], $repairPlan['correctedCentralDirectorySize']);
        $t->same(0, $repairPlan['duplicatePlannedLocalHeaderOffsetGroupCount']);
        $t->same($repairPlan, $raw['centralDirectoryRepairPlan']);
        $t->contains('central-directory-repair-not-complete', implode(',', $raw['diagnostics']));
        $t->contains('central-directory-repair-gap-unrecovered', implode(',', $raw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $raw['diagnostics']));
    },

    'summarizes central directory repair plan buckets before package handoff' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZipPackage([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'method' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>repair bucket review</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "repair bucket media\n",
                'method' => 0,
            ],
        ]);
        $base = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $understatedZip = $rewriteEndOfCentralDirectory($zip, [
            'centralDirectorySize' => $base['entries'][1]['recordEnd'] - $base['centralDirectoryOffset'],
        ]);

        $repairPlan = ZipPackage::centralDirectoryRepairPlanPreflight($understatedZip);
        $raw = ZipPackage::rawStrictImportPreflight($understatedZip, 2048, 100.0, 2048);

        $t->same(4, $repairPlan['declaredEntryCount']);
        $t->same(2, $repairPlan['scannedEntryCount']);
        $t->same(2, $repairPlan['retainedEntryCount']);
        $t->same(2, $repairPlan['recoverableGapEntryCount']);
        $t->same(4, $repairPlan['plannedEntryCount']);
        $t->same(['[Content_Types].xml', 'word/document.xml'], $repairPlan['retainedEntryNames']);
        $t->same(['word/styles.xml', 'word/media/review.txt'], $repairPlan['recoverableEntryNames']);
        $t->same(
            ['[Content_Types].xml', 'word/document.xml', 'word/styles.xml', 'word/media/review.txt'],
            $repairPlan['plannedEntryNames']
        );
        $t->same([
            'retain-declared-central-directory-entry' => 2,
            'append-recoverable-gap-central-directory-entry' => 2,
        ], $repairPlan['plannedActionCounts']);
        $t->same([
            'declared-central-directory' => 2,
            'central-directory-eocd-gap' => 2,
        ], $repairPlan['plannedSourceCounts']);
        $t->same(true, $repairPlan['plannedMatchesDeclaredEntryCount']);
        $t->same(true, $repairPlan['gapFullyRecovered']);
        $t->same(true, $repairPlan['repairAvailable']);
        $t->same('review-only-central-directory-size-repair', $repairPlan['policy']);
        $t->same([
            'central-directory-repair-plan-review',
            'central-directory-size-understatement-repair-available',
        ], $repairPlan['issues']);
        $t->same(0, $repairPlan['duplicatePlannedEntryNameGroupCount']);
        $t->same(0, $repairPlan['duplicatePlannedRawNameGroupCount']);
        $t->same(0, $repairPlan['duplicatePlannedLocalHeaderOffsetGroupCount']);
        $t->same($repairPlan, $raw['centralDirectoryRepairPlan']);
        $t->contains('central-directory-repair-plan-review', implode(',', $raw['diagnostics']));
        $t->contains('central-directory-size-understatement-repair-available', implode(',', $raw['diagnostics']));
    },

    'embeds zip central directory inventory in strict package import preflight' => static function (TestRunner $t) use ($buildZipPackage, $buildCentralDirectorySignaturePackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>strict central inventory</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
            ],
        ]);
        $package = ZipPackage::fromString($zip);
        $inventory = ZipPackage::centralDirectoryInventoryPreflight($zip);
        $strictSummary = $package->strictImportPreflight(2048, 100.0, 2048);

        $t->same($inventory, $strictSummary['centralDirectoryInventory']);
        $t->same(true, $strictSummary['centralDirectoryInventory']['isSupportedByBoundedReader']);
        $t->same([], $strictSummary['centralDirectoryInventory']['issues']);
        $t->same(['word/document.xml', 'word/media/review.png'], array_column($strictSummary['centralDirectoryInventory']['entries'], 'name'));
        $t->same(true, $strictSummary['isValid']);
        $t->same([], $strictSummary['diagnostics']);

        $signedZip = $buildCentralDirectorySignaturePackage();
        $signedPackage = ZipPackage::fromString($signedZip);
        $signedSummary = $signedPackage->strictImportPreflight(2048, 100.0, 2048);

        $t->same(true, $signedSummary['centralDirectoryInventory']['hasCentralDirectorySignature']);
        $t->same('between-central-directory-and-eocd', $signedSummary['centralDirectoryInventory']['centralDirectorySignature']['location']);
        $t->same(strlen('central-signature'), $signedSummary['centralDirectoryInventory']['centralDirectorySignature']['dataLength']);
        $t->same(false, $signedSummary['isValid']);
        $t->same(['central-directory-signature-unverified'], $signedSummary['diagnostics']);
    },

    'preflights split zip archive disk markers before bounded package import' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory, $buildCentralDirectorySignaturePackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>split disk preflight</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG review media\n",
                'method' => 0,
            ],
        ]);
        $summary = ZipPackage::splitArchivePreflight($zip);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['diskEntryCount']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same(true, $summary['isSingleDisk']);
        $t->same(false, $summary['hasSplitArchiveMarkers']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);
        $t->same(0, $summary['splitArchiveEntryCount']);
        $t->same(['word/document.xml', 'word/media/review.png'], array_column($summary['entries'], 'name'));
        $t->same([0, 0], array_column($summary['entries'], 'diskStart'));

        $signedZip = $buildCentralDirectorySignaturePackage(true);
        $signedSummary = ZipPackage::splitArchivePreflight($signedZip);

        $t->same(1, $signedSummary['entryCount']);
        $t->same(true, $signedSummary['isSingleDisk']);
        $t->same(false, $signedSummary['hasSplitArchiveMarkers']);
        $t->same(true, $signedSummary['isSupportedByBoundedReader']);
        $t->same([], $signedSummary['issues']);
        $t->same(1, $signedSummary['centralDirectoryNonEntryRecordCount']);
        $t->same('central-directory-digital-signature', $signedSummary['centralDirectoryNonEntryRecords'][0]['type']);
        $t->same(strlen('central-signature') + 6, $signedSummary['centralDirectoryNonEntryRecords'][0]['length']);
        $t->same(['word/document.xml'], array_column($signedSummary['entries'], 'name'));

        $splitZip = $rewriteEndOfCentralDirectory($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>split EOCD preflight</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/split.png',
                'data' => "split media payload\n",
                'method' => 0,
                'diskStart' => 2,
            ],
        ]), [
            'diskNumber' => 1,
            'centralDirectoryDisk' => 1,
            'diskEntryCount' => 1,
        ]);
        $splitSummary = ZipPackage::splitArchivePreflight($splitZip);

        $t->same(2, $splitSummary['entryCount']);
        $t->same(1, $splitSummary['diskNumber']);
        $t->same(1, $splitSummary['centralDirectoryDisk']);
        $t->same(1, $splitSummary['diskEntryCount']);
        $t->same(2, $splitSummary['totalEntryCount']);
        $t->same(false, $splitSummary['isSingleDisk']);
        $t->same(true, $splitSummary['hasSplitArchiveMarkers']);
        $t->same(false, $splitSummary['isSupportedByBoundedReader']);
        $t->same(['split-archive-eocd', 'split-entry-disk-start'], $splitSummary['issues']);
        $t->same(1, $splitSummary['splitArchiveEntryCount']);
        $t->same('word/media/split.png', $splitSummary['splitArchiveEntries'][0]['name']);
        $t->same(2, $splitSummary['splitArchiveEntries'][0]['diskStart']);
        $t->same(['split-entry-disk-start'], $splitSummary['splitArchiveEntries'][0]['issues']);
        $t->same([0, 2], array_column($splitSummary['entries'], 'diskStart'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($splitZip));

        $entrySplitZip = $buildZipPackage([
            [
                'name' => 'word/split-entry.xml',
                'data' => '<w:p>entry disk start marker</w:p>',
                'diskStart' => 3,
            ],
        ]);
        $entrySummary = ZipPackage::splitArchivePreflight($entrySplitZip);

        $t->same(true, $entrySummary['isSingleDisk']);
        $t->same(true, $entrySummary['hasSplitArchiveMarkers']);
        $t->same(false, $entrySummary['isSupportedByBoundedReader']);
        $t->same(['split-entry-disk-start'], $entrySummary['issues']);
        $t->same(1, $entrySummary['splitArchiveEntryCount']);
        $t->same('word/split-entry.xml', $entrySummary['splitArchiveEntries'][0]['name']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($entrySplitZip));
    },

    'preflights zip64 end of central directory locator before package import' => static function (TestRunner $t) use ($buildZip64EndOfCentralDirectoryPackage): void {
        $zip = $buildZip64EndOfCentralDirectoryPackage();
        $summary = ZipPackage::endOfCentralDirectoryPreflight($zip);

        $t->same(true, $summary['requiresZip64']);
        $t->same(false, $summary['isArchiveLayoutSupported']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectory']);
        $t->same($summary['eocdOffset'] - 20, $summary['zip64EndOfCentralDirectoryLocatorOffset']);
        $t->same(56, $summary['zip64EndOfCentralDirectorySize']);
        $t->same(1, $summary['zip64TotalDisks']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights zip64 end of central directory locator target signatures before package import' => static function (TestRunner $t) use ($buildZip64EndOfCentralDirectoryPackage, $packUInt64): void {
        $zip = $buildZip64EndOfCentralDirectoryPackage();
        $validAccounting = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($zip);
        $validArchive = ZipPackage::endOfCentralDirectoryPreflight($zip);

        $t->same(true, $validAccounting['recordOffsetAvailable']);
        $t->same('zip64-end-of-central-directory', $validAccounting['recordSignature']);
        $t->same('504b0606', $validAccounting['recordSignatureHex']);
        $t->same(true, $validArchive['zip64EndOfCentralDirectoryRecordOffsetAvailable']);
        $t->same('zip64-end-of-central-directory', $validArchive['zip64EndOfCentralDirectoryRecordSignature']);
        $t->same('504b0606', $validArchive['zip64EndOfCentralDirectoryRecordSignatureHex']);

        $locatorOffset = $validAccounting['locatorOffset'];
        if ($locatorOffset === null) {
            throw new \RuntimeException('Expected ZIP64 locator offset in fixture');
        }

        $localHeaderTargetZip = substr_replace($zip, $packUInt64(0), $locatorOffset + 8, 8);
        $localHeaderAccounting = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($localHeaderTargetZip);
        $localHeaderArchive = ZipPackage::endOfCentralDirectoryPreflight($localHeaderTargetZip);
        $localHeaderRaw = ZipPackage::rawStrictImportPreflight($localHeaderTargetZip, 512, 20.0, 512);

        $t->same(true, $localHeaderAccounting['recordOffsetAvailable']);
        $t->same('local-file-header', $localHeaderAccounting['recordSignature']);
        $t->same('504b0304', $localHeaderAccounting['recordSignatureHex']);
        $t->contains('zip64-end-of-central-directory-locator-target-not-record', implode(',', $localHeaderAccounting['issues']));
        $t->same(true, $localHeaderArchive['zip64EndOfCentralDirectoryRecordOffsetAvailable']);
        $t->same('local-file-header', $localHeaderArchive['zip64EndOfCentralDirectoryRecordSignature']);
        $t->same('504b0304', $localHeaderArchive['zip64EndOfCentralDirectoryRecordSignatureHex']);
        $t->same($localHeaderAccounting, $localHeaderRaw['zip64EndOfCentralDirectory']);
        $t->contains('zip64-end-of-central-directory-locator-target-not-record', implode(',', $localHeaderRaw['diagnostics']));

        $outOfRangeZip = substr_replace($zip, $packUInt64(strlen($zip) + 128), $locatorOffset + 8, 8);
        $outOfRangeAccounting = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($outOfRangeZip);
        $outOfRangeArchive = ZipPackage::endOfCentralDirectoryPreflight($outOfRangeZip);
        $outOfRangeRaw = ZipPackage::rawStrictImportPreflight($outOfRangeZip, 512, 20.0, 512);

        $t->same(false, $outOfRangeAccounting['recordOffsetAvailable']);
        $t->same(null, $outOfRangeAccounting['recordSignature']);
        $t->same(null, $outOfRangeAccounting['recordSignatureHex']);
        $t->contains('zip64-end-of-central-directory-locator-target-unavailable', implode(',', $outOfRangeAccounting['issues']));
        $t->same(false, $outOfRangeArchive['zip64EndOfCentralDirectoryRecordOffsetAvailable']);
        $t->same(null, $outOfRangeArchive['zip64EndOfCentralDirectoryRecordSignature']);
        $t->same(null, $outOfRangeArchive['zip64EndOfCentralDirectoryRecordSignatureHex']);
        $t->same($outOfRangeAccounting, $outOfRangeRaw['zip64EndOfCentralDirectory']);
        $t->contains('zip64-end-of-central-directory-locator-target-unavailable', implode(',', $outOfRangeRaw['diagnostics']));
    },

    'preflights zip64 end of central directory accounting before package import' => static function (TestRunner $t) use ($buildZip64EndOfCentralDirectoryPackage, $buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $zip = $buildZip64EndOfCentralDirectoryPackage();
        $summary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($zip);
        $eocdSummary = ZipPackage::endOfCentralDirectoryPreflight($zip);

        $t->same(true, $summary['requiresZip64']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectory']);
        $t->same(['zip64-end-of-central-directory'], $summary['issues']);
        $t->same($eocdSummary['eocdOffset'], $summary['eocdOffset']);
        $t->same($eocdSummary['zip64EndOfCentralDirectoryLocatorOffset'], $summary['locatorOffset']);
        $t->same($eocdSummary['zip64EndOfCentralDirectoryOffset'], $summary['recordOffset']);
        $t->same(44, $summary['recordPayloadSize']);
        $t->same(56, $summary['recordSize']);
        $t->same($summary['locatorOffset'], $summary['recordEnd']);
        $t->same(true, $summary['recordEndsAtLocator']);
        $t->same(0, $summary['recordExtensibleDataSize']);
        $t->same(45, $summary['versionMadeBy']);
        $t->same(45, $summary['versionNeededToExtract']);
        $t->same(0, $summary['locatorDiskWithEndOfCentralDirectory']);
        $t->same(1, $summary['locatorTotalDisks']);
        $t->same(0, $summary['diskNumber']);
        $t->same(0, $summary['centralDirectoryDisk']);
        $t->same(1, $summary['diskEntryCount']);
        $t->same(1, $summary['totalEntryCount']);
        $t->same($eocdSummary['zip64TotalEntryCount'], $summary['totalEntryCount']);
        $t->same($eocdSummary['zip64CentralDirectoryOffset'], $summary['centralDirectoryOffset']);
        $t->same($eocdSummary['zip64CentralDirectorySize'], $summary['centralDirectorySize']);
        $t->same($summary['centralDirectoryOffset'] + $summary['centralDirectorySize'], $summary['centralDirectoryEnd']);
        $t->same($summary['recordOffset'], $summary['centralDirectoryEnd']);
        $t->same(true, $summary['centralDirectoryEndMatchesRecordOffset']);
        $t->same(true, $summary['eocdFieldsMatchZip64Record']);
        $t->same(6, $summary['eocdZip64ResolutionFieldCount']);
        $t->same(4, $summary['eocdZip64SentinelFieldCount']);
        $t->same(4, $summary['eocdZip64ResolvedFieldCount']);
        $t->same(0, $summary['eocdZip64MissingFieldCount']);
        $t->same(2, $summary['eocdZip64MirroredFieldCount']);
        $t->same(0, $summary['eocdZip64MismatchedFieldCount']);
        $t->same([
            'diskEntryCount',
            'totalEntryCount',
            'centralDirectorySize',
            'centralDirectoryOffset',
        ], $summary['eocdZip64SentinelFields']);
        $t->same($summary['eocdZip64SentinelFields'], $summary['eocdZip64ResolvedFields']);
        $t->same([], $summary['eocdZip64MissingFields']);
        $t->same([
            'diskNumber',
            'centralDirectoryDisk',
        ], $summary['eocdZip64MirroredFields']);
        $t->same([], $summary['eocdZip64MismatchedFields']);
        $t->same([
            'diskNumber' => 'classic-eocd-mirror',
            'centralDirectoryDisk' => 'classic-eocd-mirror',
            'diskEntryCount' => 'zip64-record',
            'totalEntryCount' => 'zip64-record',
            'centralDirectorySize' => 'zip64-record',
            'centralDirectoryOffset' => 'zip64-record',
        ], array_column($summary['eocdZip64FieldResolutions'], 'resolution', 'field'));
        $t->same(0xffffffff, $summary['eocdZip64FieldResolutions'][4]['eocdSentinelValue']);
        $t->same($summary['centralDirectorySize'], $summary['eocdZip64FieldResolutions'][4]['zip64Value']);
        $t->same(true, $summary['eocdZip64FieldResolutions'][4]['usesZip64Record']);
        $t->same(true, $summary['eocdZip64FieldResolutions'][4]['matchesZip64Record']);
        $t->same(true, $summary['isSingleDisk']);

        $locatorOffset = $summary['locatorOffset'];
        if ($locatorOffset === null) {
            throw new \RuntimeException('Expected ZIP64 locator offset in fixture');
        }
        $mismatchedLocatorZip = substr_replace($zip, pack('V', 2), $locatorOffset + 16, 4);
        $mismatchedLocatorSummary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($mismatchedLocatorZip);
        $t->same(2, $mismatchedLocatorSummary['locatorTotalDisks']);
        $t->same(false, $mismatchedLocatorSummary['isSingleDisk']);
        $t->same([
            'zip64-end-of-central-directory',
            'zip64-split-archive',
            'zip64-locator-total-disks-mismatch',
        ], $mismatchedLocatorSummary['issues']);

        $eocdMismatchZip = substr_replace($zip, pack('v', 2), $summary['eocdOffset'] + 8, 2);
        $eocdMismatchZip = substr_replace($eocdMismatchZip, pack('v', 2), $summary['eocdOffset'] + 10, 2);
        $eocdMismatchSummary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($eocdMismatchZip);
        $eocdMismatchRaw = ZipPackage::rawStrictImportPreflight($eocdMismatchZip, 512, 20.0, 512);
        $t->same(false, $eocdMismatchSummary['eocdFieldsMatchZip64Record']);
        $t->same(2, $eocdMismatchSummary['eocdZip64SentinelFieldCount']);
        $t->same(2, $eocdMismatchSummary['eocdZip64ResolvedFieldCount']);
        $t->same(2, $eocdMismatchSummary['eocdZip64MirroredFieldCount']);
        $t->same(2, $eocdMismatchSummary['eocdZip64MismatchedFieldCount']);
        $t->same([
            'centralDirectorySize',
            'centralDirectoryOffset',
        ], $eocdMismatchSummary['eocdZip64SentinelFields']);
        $t->same([
            'diskNumber',
            'centralDirectoryDisk',
        ], $eocdMismatchSummary['eocdZip64MirroredFields']);
        $t->same([
            'diskEntryCount',
            'totalEntryCount',
        ], $eocdMismatchSummary['eocdZip64MismatchedFields']);
        $t->same([
            'diskNumber' => 'classic-eocd-mirror',
            'centralDirectoryDisk' => 'classic-eocd-mirror',
            'diskEntryCount' => 'classic-eocd-mismatch',
            'totalEntryCount' => 'classic-eocd-mismatch',
            'centralDirectorySize' => 'zip64-record',
            'centralDirectoryOffset' => 'zip64-record',
        ], array_column($eocdMismatchSummary['eocdZip64FieldResolutions'], 'resolution', 'field'));
        $t->same([
            'zip64-end-of-central-directory',
            'zip64-eocd-field-mismatch',
        ], $eocdMismatchSummary['issues']);
        $t->same(false, $eocdMismatchRaw['isValid']);
        $t->same($eocdMismatchSummary, $eocdMismatchRaw['zip64EndOfCentralDirectory']);
        $t->contains('zip64-eocd-field-mismatch', implode(',', $eocdMismatchRaw['diagnostics']));

        $sentinelOnlyZip = $rewriteEndOfCentralDirectory($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>zip64 sentinel accounting</w:p></w:document>',
                'method' => 8,
            ],
        ]), [
            'totalEntryCount' => 0xffff,
            'centralDirectorySize' => 0xffffffff,
        ]);
        $sentinelOnlySummary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($sentinelOnlyZip);
        $t->same(true, $sentinelOnlySummary['requiresZip64']);
        $t->same(false, $sentinelOnlySummary['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(false, $sentinelOnlySummary['hasZip64EndOfCentralDirectory']);
        $t->same(false, $sentinelOnlySummary['isSupportedByBoundedReader']);
        $t->same(['zip64-end-of-central-directory-required'], $sentinelOnlySummary['issues']);
        $t->same(null, $sentinelOnlySummary['eocdFieldsMatchZip64Record']);
        $t->same(6, $sentinelOnlySummary['eocdZip64ResolutionFieldCount']);
        $t->same(2, $sentinelOnlySummary['eocdZip64SentinelFieldCount']);
        $t->same(0, $sentinelOnlySummary['eocdZip64ResolvedFieldCount']);
        $t->same(2, $sentinelOnlySummary['eocdZip64MissingFieldCount']);
        $t->same(0, $sentinelOnlySummary['eocdZip64MirroredFieldCount']);
        $t->same(0, $sentinelOnlySummary['eocdZip64MismatchedFieldCount']);
        $t->same([
            'totalEntryCount',
            'centralDirectorySize',
        ], $sentinelOnlySummary['eocdZip64SentinelFields']);
        $t->same([], $sentinelOnlySummary['eocdZip64ResolvedFields']);
        $t->same($sentinelOnlySummary['eocdZip64SentinelFields'], $sentinelOnlySummary['eocdZip64MissingFields']);
        $t->same([], $sentinelOnlySummary['eocdZip64MismatchedFields']);
        $t->same([
            'diskNumber' => 'classic-eocd',
            'centralDirectoryDisk' => 'classic-eocd',
            'diskEntryCount' => 'classic-eocd',
            'totalEntryCount' => 'zip64-record-missing',
            'centralDirectorySize' => 'zip64-record-missing',
            'centralDirectoryOffset' => 'classic-eocd',
        ], array_column($sentinelOnlySummary['eocdZip64FieldResolutions'], 'resolution', 'field'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($sentinelOnlyZip));
    },

    'preflights zip64 end of central directory record size policy before package import' => static function (TestRunner $t) use (
        $buildZip64EndOfCentralDirectoryPackage,
        $rewriteZip64EndOfCentralDirectoryPayloadSize,
        $insertZip64EndOfCentralDirectoryExtensibleData
    ): void {
        $zip = $buildZip64EndOfCentralDirectoryPackage();
        $validSummary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($zip);

        $t->same(44, $validSummary['recordPayloadSize']);
        $t->same(56, $validSummary['recordSize']);
        $t->same($validSummary['locatorOffset'], $validSummary['recordEnd']);
        $t->same(true, $validSummary['recordEndsAtLocator']);
        $t->same(0, $validSummary['recordExtensibleDataSize']);
        $t->same(null, $validSummary['recordExtensibleDataOffset']);
        $t->same(0, $validSummary['recordExtensibleDataAvailableBytes']);
        $t->same(0, $validSummary['recordExtensibleDataMissingBytes']);
        $t->same(null, $validSummary['recordExtensibleDataSha256']);
        $t->same(null, $validSummary['recordExtensibleDataPreviewHex']);
        $t->same(0, $validSummary['recordExtensibleDataPreviewByteCount']);
        $t->same('zip64-end-of-central-directory-extensible-data-metadata-only', $validSummary['recordExtensibleDataByteExposurePolicy']);
        $t->same(false, $validSummary['recordExtensibleDataCanExposeBytes']);

        $tooSmallZip = $rewriteZip64EndOfCentralDirectoryPayloadSize($zip, 40);
        $tooSmallSummary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($tooSmallZip);
        $tooSmallRaw = ZipPackage::rawStrictImportPreflight($tooSmallZip, 512, 20.0, 512);

        $t->same(40, $tooSmallSummary['recordPayloadSize']);
        $t->same(52, $tooSmallSummary['recordSize']);
        $t->same($tooSmallSummary['recordOffset'] + 52, $tooSmallSummary['recordEnd']);
        $t->same(false, $tooSmallSummary['recordEndsAtLocator']);
        $t->same(0, $tooSmallSummary['recordExtensibleDataSize']);
        $t->same([
            'zip64-end-of-central-directory',
            'zip64-end-of-central-directory-record-too-small',
            'zip64-end-of-central-directory-record-gap-before-locator',
        ], $tooSmallSummary['issues']);
        $t->same($tooSmallSummary, $tooSmallRaw['zip64EndOfCentralDirectory']);
        $t->contains('zip64-end-of-central-directory-record-too-small', implode(',', $tooSmallRaw['diagnostics']));
        $t->contains('zip64-end-of-central-directory-record-gap-before-locator', implode(',', $tooSmallRaw['diagnostics']));

        $overlapZip = $rewriteZip64EndOfCentralDirectoryPayloadSize($zip, 60);
        $overlapSummary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($overlapZip);
        $overlapRaw = ZipPackage::rawStrictImportPreflight($overlapZip, 512, 20.0, 512);

        $t->same(60, $overlapSummary['recordPayloadSize']);
        $t->same(72, $overlapSummary['recordSize']);
        $t->same($overlapSummary['locatorOffset'] + 16, $overlapSummary['recordEnd']);
        $t->same(false, $overlapSummary['recordEndsAtLocator']);
        $t->same(16, $overlapSummary['recordExtensibleDataSize']);
        $t->same($overlapSummary['recordOffset'] + 56, $overlapSummary['recordExtensibleDataOffset']);
        $t->same(16, $overlapSummary['recordExtensibleDataAvailableBytes']);
        $t->same(0, $overlapSummary['recordExtensibleDataMissingBytes']);
        $t->same(
            hash('sha256', substr($overlapZip, $overlapSummary['recordExtensibleDataOffset'], 16)),
            $overlapSummary['recordExtensibleDataSha256']
        );
        $t->same(
            bin2hex(substr($overlapZip, $overlapSummary['recordExtensibleDataOffset'], 16)),
            $overlapSummary['recordExtensibleDataPreviewHex']
        );
        $t->same(16, $overlapSummary['recordExtensibleDataPreviewByteCount']);
        $t->same('zip64-end-of-central-directory-extensible-data-metadata-only', $overlapSummary['recordExtensibleDataByteExposurePolicy']);
        $t->same(false, $overlapSummary['recordExtensibleDataCanExposeBytes']);
        $t->same([
            'zip64-end-of-central-directory',
            'zip64-end-of-central-directory-extensible-data-sector',
            'zip64-end-of-central-directory-record-overlaps-locator',
        ], $overlapSummary['issues']);
        $t->same($overlapSummary, $overlapRaw['zip64EndOfCentralDirectory']);
        $t->contains('zip64-end-of-central-directory-extensible-data-sector', implode(',', $overlapRaw['diagnostics']));
        $t->contains('zip64-end-of-central-directory-record-overlaps-locator', implode(',', $overlapRaw['diagnostics']));

        $extensibleData = 'pandoc-zip64-review';
        $extensibleZip = $insertZip64EndOfCentralDirectoryExtensibleData($zip, $extensibleData);
        $extensibleSummary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($extensibleZip);
        $extensibleRaw = ZipPackage::rawStrictImportPreflight($extensibleZip, 512, 20.0, 512);

        $t->same(44 + strlen($extensibleData), $extensibleSummary['recordPayloadSize']);
        $t->same(56 + strlen($extensibleData), $extensibleSummary['recordSize']);
        $t->same($extensibleSummary['locatorOffset'], $extensibleSummary['recordEnd']);
        $t->same(true, $extensibleSummary['recordEndsAtLocator']);
        $t->same(strlen($extensibleData), $extensibleSummary['recordExtensibleDataSize']);
        $t->same($extensibleSummary['recordOffset'] + 56, $extensibleSummary['recordExtensibleDataOffset']);
        $t->same(strlen($extensibleData), $extensibleSummary['recordExtensibleDataAvailableBytes']);
        $t->same(0, $extensibleSummary['recordExtensibleDataMissingBytes']);
        $t->same(hash('sha256', $extensibleData), $extensibleSummary['recordExtensibleDataSha256']);
        $t->same(bin2hex(substr($extensibleData, 0, 16)), $extensibleSummary['recordExtensibleDataPreviewHex']);
        $t->same(16, $extensibleSummary['recordExtensibleDataPreviewByteCount']);
        $t->same('zip64-end-of-central-directory-extensible-data-metadata-only', $extensibleSummary['recordExtensibleDataByteExposurePolicy']);
        $t->same(false, $extensibleSummary['recordExtensibleDataCanExposeBytes']);
        $t->same([
            'zip64-end-of-central-directory',
            'zip64-end-of-central-directory-extensible-data-sector',
        ], $extensibleSummary['issues']);
        $t->same($extensibleSummary, $extensibleRaw['zip64EndOfCentralDirectory']);
        $t->contains('zip64-end-of-central-directory-extensible-data-sector', implode(',', $extensibleRaw['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($extensibleZip));
    },

    'preflights malformed zip64 end of central directory locators before package import' => static function (TestRunner $t) use ($buildZip64EndOfCentralDirectoryPackage, $packUInt64): void {
        $zip = $buildZip64EndOfCentralDirectoryPackage();
        $validSummary = ZipPackage::endOfCentralDirectoryPreflight($zip);
        $locatorOffset = $validSummary['zip64EndOfCentralDirectoryLocatorOffset'];
        if ($locatorOffset === null) {
            throw new \RuntimeException('Expected ZIP64 locator offset in fixture');
        }

        $malformedZip = substr_replace($zip, $packUInt64(0), $locatorOffset + 8, 8);
        $summary = ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight($malformedZip);
        $archive = ZipPackage::endOfCentralDirectoryPreflight($malformedZip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($malformedZip, 512, 20.0, 512);

        $t->same(true, $summary['requiresZip64']);
        $t->same(true, $summary['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(false, $summary['hasZip64EndOfCentralDirectory']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'zip64-end-of-central-directory',
            'zip64-end-of-central-directory-record-missing',
            'zip64-end-of-central-directory-locator-target-not-record',
        ], $summary['issues']);
        $t->same($validSummary['eocdOffset'], $summary['eocdOffset']);
        $t->same($locatorOffset, $summary['locatorOffset']);
        $t->same(0, $summary['locatorRecordOffset']);
        $t->same(0, $summary['recordOffset']);
        $t->same(true, $summary['recordOffsetAvailable']);
        $t->same('local-file-header', $summary['recordSignature']);
        $t->same('504b0304', $summary['recordSignatureHex']);
        $t->same(null, $summary['recordSize']);
        $t->same(null, $summary['recordPayloadSize']);
        $t->same(0, $summary['locatorDiskWithEndOfCentralDirectory']);
        $t->same(1, $summary['locatorTotalDisks']);
        $t->same(true, $archive['requiresZip64']);
        $t->same(true, $archive['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(false, $archive['hasZip64EndOfCentralDirectory']);
        $t->same('local-file-header', $archive['zip64EndOfCentralDirectoryRecordSignature']);
        $t->same('504b0304', $archive['zip64EndOfCentralDirectoryRecordSignatureHex']);
        $t->same([
            'zip64-end-of-central-directory',
            'zip64-end-of-central-directory-record-missing',
            'zip64-end-of-central-directory-locator-target-not-record',
        ], $archive['zip64Issues']);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(0xffff, $rawStrict['entryCount']);
        $t->same($summary, $rawStrict['zip64EndOfCentralDirectory']);
        $t->same(true, $rawStrict['archive']['hasZip64EndOfCentralDirectoryLocator']);
        $t->same(false, $rawStrict['archive']['hasZip64EndOfCentralDirectory']);
        $t->same(null, $rawStrict['centralDirectoryInventory']);
        $t->contains('unsupported-archive-layout', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip64-end-of-central-directory-record-missing', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip64-end-of-central-directory-locator-target-not-record', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($malformedZip));
    },

    'decodes cp437 zip entry names and comments when utf8 flag is absent' => static function (TestRunner $t) use ($buildZipPackage): void {
        $rawName = "word/media/caf\x82.png";
        $decodedName = "word/media/caf\u{00e9}.png";
        $rawComment = "r\x82sum\x82 media";
        $decodedComment = "r\u{00e9}sum\u{00e9} media";

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $rawComment,
            ],
        ]));
        $entry = $package->entry($decodedName);

        $t->same([$decodedName], $package->names());
        $t->same($decodedName, $entry->name);
        $t->same($rawName, $entry->rawName);
        $t->same('cp437', $entry->nameEncoding);
        $t->same($decodedComment, $entry->comment);
        $t->same($rawComment, $entry->rawComment);
        $t->same('cp437', $entry->commentEncoding);
        $t->same("PNG reviewer attachment placeholder\n", $package->read('/' . $decodedName));
    },

    'uses info zip unicode path and comment extras for legacy encoded package names' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra, $crc32): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $rawComment = 'legacy reviewer comment';
        $unicodeComment = "Unicode reviewer \u{2603} comment";
        $unicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, $unicodeName);
        $unicodeCommentExtra = $buildUnicodeExtra(0x6375, $rawComment, $unicodeComment);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => "Unicode media attachment placeholder\n",
                'method' => 8,
                'flags' => 0,
                'comment' => $rawComment,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra . $unicodeCommentExtra,
            ],
        ]));
        $entry = $package->entry($unicodeName);

        $t->same([$unicodeName], $package->names());
        $t->same($rawName, $entry->rawName);
        $t->same($unicodeName, $entry->name);
        $t->same('info-zip-unicode-path', $entry->nameEncoding);
        $t->same($rawComment, $entry->rawComment);
        $t->same($unicodeComment, $entry->comment);
        $t->same('info-zip-unicode-comment', $entry->commentEncoding);
        $t->same("\x01" . pack('V', $crc32($rawName)) . $unicodeName, $entry->centralExtraField(0x7075));
        $t->same("Unicode media attachment placeholder\n", $package->read('/' . $unicodeName));
    },

    'rejects central unicode zip path names when local source metadata is missing' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $unicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, $unicodeName);

        $safePackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => "paired Unicode path metadata stays readable\n",
                'method' => 0,
                'flags' => 0,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra,
            ],
        ]));
        $safeEntry = $safePackage->entry('/' . $unicodeName);

        $t->same([$unicodeName], $safePackage->names());
        $t->same($rawName, $safeEntry->rawName);
        $t->same($unicodeName, $safeEntry->name);
        $t->same('info-zip-unicode-path', $safeEntry->nameEncoding);
        $t->same("paired Unicode path metadata stays readable\n", $safePackage->read('/' . $unicodeName));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'central Unicode path without matching local metadata',
                'method' => 0,
                'flags' => 0,
                'localExtra' => '',
                'centralExtra' => $unicodePathExtra,
            ],
        ])));
    },

    'rejects contradictory utf8 flagged unicode zip metadata before media handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => "word/media/review-\xff.bin",
                'data' => 'invalid utf8 raw name should not be masked by Unicode path metadata',
                'method' => 0,
                'flags' => 0x0800,
                'centralExtra' => $buildUnicodeExtra(0x7075, "word/media/review-\xff.bin", 'word/media/review.png'),
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review.png',
                'data' => 'conflicting Unicode path metadata should not rename UTF-8 header text',
                'method' => 0,
                'flags' => 0x0800,
                'centralExtra' => $buildUnicodeExtra(0x7075, 'word/media/review.png', "word/media/review-\u{2603}.png"),
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review-comment.png',
                'data' => 'invalid utf8 raw comment should not be masked by Unicode comment metadata',
                'method' => 0,
                'flags' => 0x0800,
                'comment' => "review-\xff-comment",
                'centralExtra' => $buildUnicodeExtra(0x6375, "review-\xff-comment", 'review-comment'),
            ],
        ])));

        $safeName = "word/media/review-\u{2603}.png";
        $safeComment = "review \u{2603} comment";
        $safePackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $safeName,
                'data' => "matching UTF-8 metadata stays readable\n",
                'method' => 0,
                'flags' => 0x0800,
                'comment' => $safeComment,
                'centralExtra' => $buildUnicodeExtra(0x7075, $safeName, $safeName)
                    . $buildUnicodeExtra(0x6375, $safeComment, $safeComment),
            ],
        ]));
        $entry = $safePackage->entry('/' . $safeName);

        $t->same([$safeName], $safePackage->names());
        $t->same($safeName, $entry->rawName);
        $t->same($safeName, $entry->name);
        $t->same($safeComment, $entry->rawComment);
        $t->same($safeComment, $entry->comment);
        $t->same('info-zip-unicode-path', $entry->nameEncoding);
        $t->same('info-zip-unicode-comment', $entry->commentEncoding);
        $t->same("matching UTF-8 metadata stays readable\n", $safePackage->read('/' . $safeName));
    },

    'preflights package and entry comments for reviewer provenance' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $rawComment = 'legacy reviewer comment';
        $unicodeComment = "Unicode reviewer \u{2603} comment";
        $cp437Comment = "r\x82sum\x82 media";

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => "Unicode media attachment placeholder\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $rawComment,
                'localExtra' => $buildUnicodeExtra(0x7075, $rawName, $unicodeName),
                'centralExtra' => $buildUnicodeExtra(0x7075, $rawName, $unicodeName)
                    . $buildUnicodeExtra(0x6375, $rawComment, $unicodeComment),
            ],
            [
                'name' => "word/media/caf\x82.png",
                'data' => "legacy media attachment placeholder\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $cp437Comment,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>comment metadata</w:p></w:document>',
                'method' => 8,
            ],
        ], "package r\x82sum\x82"));

        $packageBytes = $package->bytes();
        $byteLayout = ZipPackage::packageByteLayoutPreflight($packageBytes);
        $source = $package->packageCommentSourcePreflight();
        $summary = $package->commentPreflight();

        $t->same("package r\u{00e9}sum\u{00e9}", $summary['packageComment']);
        $t->same('cp437', $summary['packageCommentEncoding']);
        $t->same(strlen("package r\x82sum\x82"), $summary['packageCommentLength']);
        $t->same($source, array_intersect_key($summary, $source));
        $t->same(true, $summary['packageCommentSourceAvailable']);
        $t->same($byteLayout['packageCommentOffset'], $summary['packageCommentOffset']);
        $t->same(strlen("package r\x82sum\x82"), $summary['packageCommentBytes']);
        $t->same($byteLayout['packageCommentEnd'], $summary['packageCommentEnd']);
        $t->same(hash('sha256', "package r\x82sum\x82"), $summary['packageCommentSha256']);
        $t->same(bin2hex("package r\x82sum\x82"), $summary['packageCommentPreviewHex']);
        $t->same(strlen("package r\x82sum\x82"), $summary['packageCommentPreviewByteCount']);
        $t->same('zip-package-comment-source-metadata-only', $summary['packageCommentByteExposurePolicy']);
        $t->same(false, $summary['canExposePackageCommentBytes']);
        $t->same(2, $summary['entryCommentCount']);
        $t->same(3, count($summary['entries']));
        $t->same($unicodeName, $summary['commentedEntries'][0]['name']);
        $t->same($unicodeComment, $summary['commentedEntries'][0]['comment']);
        $t->same('info-zip-unicode-comment', $summary['commentedEntries'][0]['commentEncoding']);
        $t->same($rawComment, $summary['commentedEntries'][0]['rawComment']);
        $t->same("word/media/caf\u{00e9}.png", $summary['commentedEntries'][1]['name']);
        $t->same("r\u{00e9}sum\u{00e9} media", $summary['commentedEntries'][1]['comment']);
        $t->same('cp437', $summary['commentedEntries'][1]['commentEncoding']);
        $t->same('', $summary['entries'][2]['comment']);
        $t->same('utf-8', $summary['entries'][2]['commentEncoding']);
    },

    'rejects empty info zip unicode comments that hide raw entry comments' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawComment = 'review comment must remain visible';
        $emptyUnicodeCommentExtra = $buildUnicodeExtra(0x6375, $rawComment, '');

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review-note.txt',
                'data' => "empty Unicode comment metadata should not hide raw comments\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $rawComment,
                'centralExtra' => $emptyUnicodeCommentExtra,
            ],
        ])));

        $commentedPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review-note.txt',
                'data' => "raw comment metadata remains visible\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $rawComment,
            ],
        ]));
        $summary = $commentedPackage->commentPreflight();

        $t->same(true, $summary['hasEntryComments']);
        $t->same(1, $summary['entryCommentCount']);
        $t->same(['word/media/review-note.txt'], $summary['commentedEntryNames']);
        $t->same($rawComment, $summary['commentedEntries'][0]['comment']);
        $t->same($rawComment, $summary['commentedEntries'][0]['rawComment']);
        $t->same('cp437', $summary['commentedEntries'][0]['commentEncoding']);
        $t->throws(\RuntimeException::class, static fn (): array => $commentedPackage->assertNoPackageOrEntryComments());
    },

    'preflights strict zip comment policy before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $commentedPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>commented package policy</w:p></w:document>',
                'method' => 8,
                'comment' => 'document reviewer comment',
            ],
            [
                'name' => 'word/media/review-note.txt',
                'data' => 'review packet comment metadata',
                'method' => 0,
            ],
        ], 'source package review comment'));
        $summary = $commentedPackage->commentPreflight();

        $t->same('source package review comment', $summary['packageComment']);
        $t->same(true, $summary['hasPackageComment']);
        $t->same(true, $summary['hasEntryComments']);
        $t->same(true, $summary['hasComments']);
        $t->same(1, $summary['entryCommentCount']);
        $t->same(['word/document.xml'], $summary['commentedEntryNames']);
        $t->same('document reviewer comment', $summary['commentedEntries'][0]['comment']);
        $t->same('review packet comment metadata', $commentedPackage->read('/word/media/review-note.txt'));
        $t->throws(\RuntimeException::class, static fn (): array => $commentedPackage->assertNoPackageOrEntryComments());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>no comment policy</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review-note.txt',
                'data' => 'comment-free media handoff',
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoPackageOrEntryComments();

        $t->same(false, $safeSummary['hasPackageComment']);
        $t->same(false, $safeSummary['hasEntryComments']);
        $t->same(false, $safeSummary['hasComments']);
        $t->same(0, $safeSummary['entryCommentCount']);
        $t->same([], $safeSummary['commentedEntryNames']);
        $t->same('comment-free media handoff', $safePackage->read('/word/media/review-note.txt'));
    },

    'preflights raw zip comment control bytes before strict package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>control byte comment policy</w:p></w:document>',
                'method' => 8,
                'comment' => "entry\x7fcomment",
            ],
            [
                'name' => 'word/media/review-note.txt',
                'data' => 'control-byte comment metadata remains bounded',
                'method' => 0,
            ],
        ], "source\0package"));
        $summary = $package->commentPreflight();

        $t->same(true, $summary['hasComments']);
        $t->same(true, $summary['hasCommentControlBytes']);
        $t->same(true, $summary['packageCommentHasControlBytes']);
        $t->same("source\0package", $summary['packageComment']);
        $t->same("source\0package", $summary['rawPackageComment']);
        $t->same([6], $summary['packageCommentControlByteOffsets']);
        $t->same(['package-comment-control-bytes'], $summary['packageCommentIssues']);
        $t->same(1, $summary['commentControlByteEntryCount']);
        $t->same('word/document.xml', $summary['commentControlByteEntries'][0]['name']);
        $t->same("entry\x7fcomment", $summary['commentControlByteEntries'][0]['comment']);
        $t->same("entry\x7fcomment", $summary['commentControlByteEntries'][0]['rawComment']);
        $t->same(true, $summary['commentControlByteEntries'][0]['hasControlBytes']);
        $t->same([5], $summary['commentControlByteEntries'][0]['commentControlByteOffsets']);
        $t->same(['entry-comment-control-bytes'], $summary['commentControlByteEntries'][0]['issues']);
        $t->same([5], $summary['commentedEntries'][0]['commentControlByteOffsets']);
        $t->same([], $summary['entries'][1]['commentControlByteOffsets']);
        $t->same([], $summary['entries'][1]['issues']);

        $strictSummary = $package->strictImportPreflight(2048, 100.0, 2048);

        $t->same(false, $strictSummary['isValid']);
        $t->contains('package-or-entry-comments', implode(',', $strictSummary['diagnostics']));
        $t->contains('comment-control-bytes', implode(',', $strictSummary['diagnostics']));
        $t->same($summary, $strictSummary['comments']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(2048, 100.0, 2048));
        $t->same('control-byte comment metadata remains bounded', $package->read('/word/media/review-note.txt'));

        $safeCommentPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>readable comment policy</w:p></w:document>',
                'method' => 8,
                'comment' => 'reviewer comment',
            ],
        ], 'package reviewer comment'));
        $safeCommentSummary = $safeCommentPackage->commentPreflight();

        $t->same(true, $safeCommentSummary['hasComments']);
        $t->same(false, $safeCommentSummary['hasCommentControlBytes']);
        $t->same(false, $safeCommentSummary['packageCommentHasControlBytes']);
        $t->same([], $safeCommentSummary['packageCommentControlByteOffsets']);
        $t->same([], $safeCommentSummary['packageCommentIssues']);
        $t->same(0, $safeCommentSummary['commentControlByteEntryCount']);
        $t->same([], $safeCommentSummary['commentControlByteEntries']);
        $t->same([], $safeCommentPackage->strictImportPreflight(2048, 100.0, 2048)['comments']['commentControlByteEntries']);
        $t->same(['package-or-entry-comments'], $safeCommentPackage->strictImportPreflight(2048, 100.0, 2048)['diagnostics']);
    },

    'preflights zip comment unicode format controls before strict package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>Unicode comment control policy</w:p></w:document>',
                'method' => 8,
                'comment' => "entry\u{200d}comment",
            ],
            [
                'name' => 'word/media/review-note.txt',
                'data' => 'Unicode comment metadata remains bounded',
                'method' => 0,
            ],
        ], "source\u{202e}package"));
        $summary = $package->commentPreflight();

        $t->same(true, $summary['hasComments']);
        $t->same(false, $summary['hasCommentControlBytes']);
        $t->same(true, $summary['hasCommentUnicodeFormatControls']);
        $t->same(true, $summary['hasCommentBidiControls']);
        $t->same(true, $summary['packageCommentHasUnicodeFormatControls']);
        $t->same(true, $summary['packageCommentHasBidiControls']);
        $t->same("source\u{202e}package", $summary['packageComment']);
        $t->same("source\u{202e}package", $summary['rawPackageComment']);
        $t->same([], $summary['packageCommentControlByteOffsets']);
        $t->same(['right-to-left-override'], $summary['packageCommentUnicodeFormatControlNames']);
        $t->same(['right-to-left-override'], $summary['packageCommentBidiControlNames']);
        $t->same([
            'package-comment-unicode-format-control',
            'package-comment-bidi-format-control',
        ], $summary['packageCommentIssues']);
        $t->same(1, $summary['commentUnicodeFormatControlEntryCount']);
        $t->same(0, $summary['commentBidiControlEntryCount']);
        $t->same('word/document.xml', $summary['commentUnicodeFormatControlEntries'][0]['name']);
        $t->same("entry\u{200d}comment", $summary['commentUnicodeFormatControlEntries'][0]['comment']);
        $t->same(false, $summary['commentUnicodeFormatControlEntries'][0]['hasControlBytes']);
        $t->same(true, $summary['commentUnicodeFormatControlEntries'][0]['hasUnicodeFormatControls']);
        $t->same(false, $summary['commentUnicodeFormatControlEntries'][0]['hasBidiControls']);
        $t->same(['zero-width-joiner'], $summary['commentUnicodeFormatControlEntries'][0]['unicodeFormatControlNames']);
        $t->same([], $summary['commentUnicodeFormatControlEntries'][0]['bidiControlNames']);
        $t->same(['entry-comment-unicode-format-control'], $summary['commentUnicodeFormatControlEntries'][0]['issues']);
        $t->same(['entry-comment-unicode-format-control'], $summary['commentedEntries'][0]['issues']);
        $t->same([], $summary['entries'][1]['unicodeFormatControlNames']);
        $t->same([], $summary['entries'][1]['issues']);

        $strictSummary = $package->strictImportPreflight(2048, 100.0, 2048);

        $t->same(false, $strictSummary['isValid']);
        $t->same([
            'package-or-entry-comments',
            'comment-unicode-format-controls',
            'comment-bidi-format-controls',
        ], $strictSummary['diagnostics']);
        $t->same($summary, $strictSummary['comments']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(2048, 100.0, 2048));
        $t->same('Unicode comment metadata remains bounded', $package->read('/word/media/review-note.txt'));
    },

    'preflights raw zip comments before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/spoofed-document.xml',
                'data' => '<w:document><w:p>raw comment policy</w:p></w:document>',
                'method' => 8,
                'comment' => "entry\x7fcomment",
            ],
            [
                'name' => 'word/media/review-note.txt',
                'data' => 'raw comment metadata remains visible before package construction',
                'method' => 0,
            ],
        ], "source\u{202e}package");
        $packageCommentBytes = "source\u{202e}package";
        $commentSource = ZipPackage::rawPackageCommentSourcePreflight($zip);

        $summary = ZipPackage::commentPolicyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['totalEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'package-or-entry-comments',
            'comment-control-bytes',
            'comment-unicode-format-controls',
            'comment-bidi-format-controls',
        ], $summary['issues']);
        $t->same("source\u{202e}package", $summary['packageComment']);
        $t->same("source\u{202e}package", $summary['rawPackageComment']);
        $t->same($commentSource, array_intersect_key($summary, $commentSource));
        $t->same(true, $summary['packageCommentSourceAvailable']);
        $t->same(strlen($packageCommentBytes), $summary['packageCommentBytes']);
        $t->same(hash('sha256', $packageCommentBytes), $summary['packageCommentSha256']);
        $t->same(bin2hex($packageCommentBytes), $summary['packageCommentPreviewHex']);
        $t->same(strlen($packageCommentBytes), $summary['packageCommentPreviewByteCount']);
        $t->same('zip-package-comment-source-metadata-only', $summary['packageCommentByteExposurePolicy']);
        $t->same(false, $summary['canExposePackageCommentBytes']);
        $t->same(true, $summary['hasPackageComment']);
        $t->same(true, $summary['hasEntryComments']);
        $t->same(true, $summary['hasComments']);
        $t->same(true, $summary['hasCommentControlBytes']);
        $t->same(true, $summary['hasCommentUnicodeFormatControls']);
        $t->same(true, $summary['hasCommentBidiControls']);
        $t->same(['right-to-left-override'], $summary['packageCommentUnicodeFormatControlNames']);
        $t->same(['right-to-left-override'], $summary['packageCommentBidiControlNames']);
        $t->same(1, $summary['entryCommentCount']);
        $t->same(['word/document.xml'], $summary['commentedEntryNames']);
        $t->same('word/document.xml', $summary['commentControlByteEntries'][0]['name']);
        $t->same("entry\x7fcomment", $summary['commentControlByteEntries'][0]['comment']);
        $t->same([5], $summary['commentControlByteEntries'][0]['commentControlByteOffsets']);
        $t->same(['entry-comment-control-bytes'], $summary['commentControlByteEntries'][0]['issues']);
        $t->same([], $summary['entries'][1]['issues']);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->same($summary, $rawStrict['comments']);
        $t->same($commentSource, array_intersect_key($rawStrict['comments'], $commentSource));
        $t->contains('local-header-name-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('package-or-entry-comments', implode(',', $rawStrict['diagnostics']));
        $t->contains('comment-control-bytes', implode(',', $rawStrict['diagnostics']));
        $t->contains('comment-unicode-format-controls', implode(',', $rawStrict['diagnostics']));
        $t->contains('comment-bidi-format-controls', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'rejects generated zip comment control bytes before package writing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>generated package comment control bytes</w:p></w:document>',
            ],
        ], "source\0package"));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>generated entry comment control bytes</w:p></w:document>',
                'comment' => "entry\x7freview",
            ],
        ]));

        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe generated comments still round trip</w:p></w:document>',
                'comment' => 'reviewer comment',
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "comment-free media remains readable\n",
                'compressionMethod' => 0,
            ],
        ], 'source package');
        $summary = $package->commentPreflight();

        $t->same(true, $summary['hasComments']);
        $t->same(false, $summary['hasCommentControlBytes']);
        $t->same(false, $summary['packageCommentHasControlBytes']);
        $t->same([], $summary['packageCommentControlByteOffsets']);
        $t->same(0, $summary['commentControlByteEntryCount']);
        $t->same([], $summary['commentControlByteEntries']);
        $t->same('source package', $summary['packageComment']);
        $t->same('reviewer comment', $summary['commentedEntries'][0]['comment']);
        $t->same(['package-or-entry-comments'], $package->strictImportPreflight(2048, 100.0, 2048)['diagnostics']);
        $t->same("comment-free media remains readable\n", $package->read('word/media/review.txt'));
    },

    'rejects unsafe raw zip names even when unicode path metadata is safe' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $safeUnicodePath = 'word/media/review.png';
        $absoluteRawName = '/word/media/review.png';
        $traversalRawName = 'word/../media/review.png';

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $absoluteRawName,
                'data' => 'absolute raw path with safe Unicode path',
                'flags' => 0,
                'centralExtra' => $buildUnicodeExtra(0x7075, $absoluteRawName, $safeUnicodePath),
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $traversalRawName,
                'data' => 'traversal raw path with safe Unicode path',
                'flags' => 0,
                'centralExtra' => $buildUnicodeExtra(0x7075, $traversalRawName, $safeUnicodePath),
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/review-image.bin',
                'data' => "safe raw path with Unicode media name\n",
                'flags' => 0,
                'localExtra' => $buildUnicodeExtra(0x7075, 'word/media/review-image.bin', $safeUnicodePath),
                'centralExtra' => $buildUnicodeExtra(0x7075, 'word/media/review-image.bin', $safeUnicodePath),
            ],
        ]));

        $t->same([$safeUnicodePath], $package->names());
        $t->same('word/media/review-image.bin', $package->entry('/' . $safeUnicodePath)->rawName);
        $t->same("safe raw path with Unicode media name\n", $package->read('/' . $safeUnicodePath));
    },

    'rejects mismatched unicode zip path metadata before exposing package names' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $centralPathExtra = $buildUnicodeExtra(0x7075, $rawName, $unicodeName);
        $badCentralPathExtra = $buildUnicodeExtra(0x7075, 'word/media/other.bin', $unicodeName);
        $badLocalPathExtra = $buildUnicodeExtra(0x7075, $rawName, 'word/media/other.png');

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'bad central unicode path crc',
                'flags' => 0,
                'centralExtra' => $badCentralPathExtra,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'bad local unicode path value',
                'flags' => 0,
                'localExtra' => $badLocalPathExtra,
                'centralExtra' => $centralPathExtra,
            ],
        ])));
    },

    'preflights info zip unicode extras before raw strict package import' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $rawComment = 'legacy reviewer comment';
        $unicodeComment = "Unicode reviewer \u{2603} comment";
        $unicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, $unicodeName);
        $unicodeCommentExtra = $buildUnicodeExtra(0x6375, $rawComment, $unicodeComment);

        $safePolicy = ZipPackage::unicodeExtraFieldPolicyPreflight($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'valid unicode metadata',
                'flags' => 0,
                'comment' => $rawComment,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra . $unicodeCommentExtra,
            ],
        ]));

        $t->same(true, $safePolicy['isSupportedByBoundedReader']);
        $t->same(1, $safePolicy['entryCount']);
        $t->same(1, $safePolicy['unicodeExtraFieldEntryCount']);
        $t->same(1, $safePolicy['centralUnicodePathEntryCount']);
        $t->same(1, $safePolicy['localUnicodePathEntryCount']);
        $t->same(1, $safePolicy['unicodeCommentEntryCount']);
        $t->same(0, $safePolicy['issueEntryCount']);
        $t->same([], $safePolicy['issues']);
        $t->same($unicodeName, $safePolicy['entries'][0]['centralUnicodePath']['text']);
        $t->same($unicodeName, $safePolicy['entries'][0]['localUnicodePath']['text']);
        $t->same(true, $safePolicy['entries'][0]['unicodePathMatchesLocalHeader']);
        $t->same($unicodeComment, $safePolicy['entries'][0]['unicodeComment']['text']);

        $badCentralPathExtra = $buildUnicodeExtra(0x7075, 'word/media/other.bin', $unicodeName);
        $badCrcBytes = $buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'bad central unicode path crc',
                'flags' => 0,
                'centralExtra' => $badCentralPathExtra,
            ],
        ]);
        $badCrcPolicy = ZipPackage::unicodeExtraFieldPolicyPreflight($badCrcBytes);
        $badCrcRaw = ZipPackage::rawStrictImportPreflight($badCrcBytes, 512, 20.0, 512);

        $t->same(false, $badCrcPolicy['isSupportedByBoundedReader']);
        $t->same(1, $badCrcPolicy['issueEntryCount']);
        $t->same(['unicode-path-extra-field-crc32-mismatch'], $badCrcPolicy['issues']);
        $t->same(null, $badCrcPolicy['issueEntries'][0]['centralUnicodePath']['text']);
        $t->same(false, $badCrcRaw['isValid']);
        $t->same(false, $badCrcRaw['canInstantiate']);
        $t->same($badCrcPolicy, $badCrcRaw['unicodeExtraFields']);
        $t->contains('unicode-extra-field-issues', implode(',', $badCrcRaw['diagnostics']));
        $t->contains('unicode-path-extra-field-crc32-mismatch', implode(',', $badCrcRaw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $badCrcRaw['diagnostics']));

        $missingLocalBytes = $buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'central unicode path without matching local source metadata',
                'flags' => 0,
                'centralExtra' => $unicodePathExtra,
            ],
        ]);
        $missingLocalPolicy = ZipPackage::unicodeExtraFieldPolicyPreflight($missingLocalBytes);
        $missingLocalRaw = ZipPackage::rawStrictImportPreflight($missingLocalBytes, 512, 20.0, 512);

        $t->same(false, $missingLocalPolicy['isSupportedByBoundedReader']);
        $t->same(['unicode-path-local-extra-field-missing'], $missingLocalPolicy['issues']);
        $t->same($unicodeName, $missingLocalPolicy['issueEntries'][0]['centralUnicodePath']['text']);
        $t->same(false, $missingLocalRaw['canInstantiate']);
        $t->same($missingLocalPolicy, $missingLocalRaw['unicodeExtraFields']);
        $t->contains('unicode-path-local-extra-field-missing', implode(',', $missingLocalRaw['diagnostics']));

        $emptyCommentExtra = $buildUnicodeExtra(0x6375, $rawComment, '');
        $emptyCommentBytes = $buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'empty unicode comment replacement',
                'flags' => 0,
                'comment' => $rawComment,
                'centralExtra' => $emptyCommentExtra,
            ],
        ]);
        $emptyCommentPolicy = ZipPackage::unicodeExtraFieldPolicyPreflight($emptyCommentBytes);
        $emptyCommentRaw = ZipPackage::rawStrictImportPreflight($emptyCommentBytes, 512, 20.0, 512);

        $t->same(false, $emptyCommentPolicy['isSupportedByBoundedReader']);
        $t->same(1, $emptyCommentPolicy['unicodeCommentEntryCount']);
        $t->same(['unicode-comment-extra-field-empty-replacement'], $emptyCommentPolicy['issues']);
        $t->same(null, $emptyCommentPolicy['issueEntries'][0]['unicodeComment']['text']);
        $t->same(false, $emptyCommentRaw['canInstantiate']);
        $t->same($emptyCommentPolicy, $emptyCommentRaw['unicodeExtraFields']);
        $t->contains('unicode-comment-extra-field-empty-replacement', implode(',', $emptyCommentRaw['diagnostics']));
    },

    'rejects duplicate info zip unicode path and comment extra fields before media handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $alternateUnicodeName = "word/media/review-alt-\u{2603}.png";
        $rawComment = 'legacy reviewer comment';
        $unicodeComment = "Unicode reviewer \u{2603} comment";
        $alternateUnicodeComment = "alternate Unicode reviewer \u{2603} comment";
        $unicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, $unicodeName);
        $alternateUnicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, $alternateUnicodeName);
        $unicodeCommentExtra = $buildUnicodeExtra(0x6375, $rawComment, $unicodeComment);
        $alternateUnicodeCommentExtra = $buildUnicodeExtra(0x6375, $rawComment, $alternateUnicodeComment);

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'duplicate central Unicode path metadata',
                'flags' => 0,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra . $alternateUnicodePathExtra,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'duplicate local Unicode path metadata',
                'flags' => 0,
                'localExtra' => $unicodePathExtra . $alternateUnicodePathExtra,
                'centralExtra' => $unicodePathExtra,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'duplicate central Unicode comment metadata',
                'flags' => 0,
                'comment' => $rawComment,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra . $unicodeCommentExtra . $alternateUnicodeCommentExtra,
            ],
        ])));

        $safePackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => "single Unicode metadata fields stay readable\n",
                'flags' => 0,
                'comment' => $rawComment,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra . $unicodeCommentExtra,
            ],
        ]));
        $safeEntry = $safePackage->entry('/' . $unicodeName);

        $t->same([$unicodeName], $safePackage->names());
        $t->same($rawName, $safeEntry->rawName);
        $t->same($unicodeName, $safeEntry->name);
        $t->same('info-zip-unicode-path', $safeEntry->nameEncoding);
        $t->same($rawComment, $safeEntry->rawComment);
        $t->same($unicodeComment, $safeEntry->comment);
        $t->same('info-zip-unicode-comment', $safeEntry->commentEncoding);
        $t->same("single Unicode metadata fields stay readable\n", $safePackage->read('/' . $unicodeName));
    },

    'writes zip entry modification metadata for generated package parts' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>Generated metadata packet</w:p></w:document>',
                'modifiedAt' => 1780479016,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'modifiedDosTime' => 19400,
                'modifiedDosDate' => 23747,
            ],
        ]);

        $roundTrip = ZipPackage::fromString($package->bytes());
        $document = $roundTrip->entry('word/document.xml');
        $mediaDirectory = $roundTrip->entry('word/media/');

        $t->same(19400, $document->modifiedDosTime());
        $t->same(23747, $document->modifiedDosDate());
        $t->same(1780479016, $document->lastModifiedTimestamp());
        $t->same(0x81a40000, $document->externalFileAttributes);
        $t->same(19400, $mediaDirectory->modifiedDosTime());
        $t->same(23747, $mediaDirectory->modifiedDosDate());
        $t->same(1780479016, $mediaDirectory->lastModifiedTimestamp());
        $t->same(0x10, $mediaDirectory->externalFileAttributes);
    },

    'reads zip extra fields and uses extended timestamp metadata' => static function (TestRunner $t) use ($buildZipPackage): void {
        $extendedTimestamp = 1780479017;
        $extendedTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, $extendedTimestamp);
        $vendorExtra = pack('vva*', 0xcafe, 6, 'review');

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>extra fields</w:p></w:document>',
                'method' => 8,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localExtra' => $extendedTimestampExtra . $vendorExtra,
                'centralExtra' => $extendedTimestampExtra . $vendorExtra,
            ],
        ]));

        $entry = $package->entry('word/document.xml');

        $t->same($extendedTimestamp, $entry->extendedLastModifiedTimestamp());
        $t->same($extendedTimestamp, $entry->lastModifiedTimestamp());
        $t->same($extendedTimestampExtra . $vendorExtra, $entry->centralExtraFieldData);
        $t->same("\x01" . pack('V', $extendedTimestamp), $entry->centralExtraField(0x5455));
        $t->same('review', $entry->centralExtraField(0xcafe));
        $t->same(null, $entry->centralExtraField(0x000a));
        $t->same([
            ['id' => 0x5455, 'data' => "\x01" . pack('V', $extendedTimestamp)],
            ['id' => 0xcafe, 'data' => 'review'],
        ], $entry->centralExtraFields());
        $t->same('<w:document><w:p>extra fields</w:p></w:document>', $package->read('/word/document.xml'));
    },

    'reads central zip extended timestamps with local-only access metadata' => static function (TestRunner $t) use ($buildZipPackage): void {
        $modifiedAt = 1780479017;
        $accessedAt = 1780479021;
        $centralTimestampExtra = pack('vvCV', 0x5455, 5, 0x03, $modifiedAt);
        $localTimestampExtra = pack('vvCVV', 0x5455, 9, 0x03, $modifiedAt, $accessedAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>central timestamp</w:p></w:document>',
                'method' => 8,
                'localExtra' => $localTimestampExtra,
                'centralExtra' => $centralTimestampExtra,
            ],
        ]));
        $entry = $package->entry('word/document.xml');

        $t->same(['modifiedAt' => $modifiedAt], $entry->extendedTimestamps());
        $t->same($modifiedAt, $entry->extendedLastModifiedTimestamp());
        $t->same(null, $entry->extendedAccessedTimestamp());
        $t->same($accessedAt, $package->localExtendedAccessedTimestamp('/word/document.xml'));
        $t->same('<w:document><w:p>central timestamp</w:p></w:document>', $package->read('/word/document.xml'));
    },

    'reads local zip extended timestamps with absent optional access metadata' => static function (TestRunner $t) use ($buildZipPackage): void {
        $modifiedAt = 1780479017;
        $timestampExtra = pack('vvCV', 0x5455, 5, 0x03, $modifiedAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings><w:zoom w:percent="100"/></w:settings>',
                'method' => 8,
                'localExtra' => $timestampExtra,
                'centralExtra' => $timestampExtra,
            ],
        ]));
        $entry = $package->entry('word/settings.xml');

        $t->same(['modifiedAt' => $modifiedAt], $entry->extendedTimestamps());
        $t->same(['modifiedAt' => $modifiedAt], $package->localExtendedTimestamps('word/settings.xml'));
        $t->same($modifiedAt, $entry->extendedLastModifiedTimestamp());
        $t->same($modifiedAt, $package->localExtendedLastModifiedTimestamp('word/settings.xml'));
        $t->same(null, $entry->extendedAccessedTimestamp());
        $t->same(null, $package->localExtendedAccessedTimestamp('word/settings.xml'));
        $t->same('<w:settings><w:zoom w:percent="100"/></w:settings>', $package->read('word/settings.xml'));
    },

    'exposes local zip extra fields for package preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $extendedTimestamp = 1780479017;
        $timestampExtra = pack('vvCV', 0x5455, 5, 0x01, $extendedTimestamp);
        $localVendorExtra = pack('vva*', 0xcafe, 5, 'local');
        $centralVendorExtra = pack('vva*', 0xcafe, 7, 'central');

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>local metadata</w:p></w:document>',
                'method' => 8,
                'localExtra' => $timestampExtra . $localVendorExtra,
                'centralExtra' => $timestampExtra . $centralVendorExtra,
            ],
        ]));

        $entry = $package->entry('word/document.xml');

        $t->same('central', $entry->centralExtraField(0xcafe));
        $t->same('local', $package->localExtraField('/word/document.xml', 0xcafe));
        $t->same($extendedTimestamp, $package->localExtendedLastModifiedTimestamp('word/document.xml'));
        $t->same([
            ['id' => 0x5455, 'data' => "\x01" . pack('V', $extendedTimestamp)],
            ['id' => 0xcafe, 'data' => 'local'],
        ], $package->localExtraFields('word/document.xml'));
        $t->same('<w:document><w:p>local metadata</w:p></w:document>', $package->read('/word/document.xml'));
    },

    'reads local extended timestamp access and creation metadata for package preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $modifiedAt = 1780479017;
        $accessedAt = 1780479022;
        $createdAt = 1780479023;
        $centralTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, $modifiedAt);
        $localTimestampExtra = pack('vvCVVV', 0x5455, 13, 0x07, $modifiedAt, $accessedAt, $createdAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'reviewer media provenance',
                'method' => 8,
                'localExtra' => $localTimestampExtra,
                'centralExtra' => $centralTimestampExtra,
            ],
        ]));
        $entry = $package->entry('word/media/reviewer-note.txt');

        $t->same(['modifiedAt' => $modifiedAt], $entry->extendedTimestamps());
        $t->same($modifiedAt, $entry->extendedLastModifiedTimestamp());
        $t->same(null, $entry->extendedAccessedTimestamp());
        $t->same(null, $entry->extendedCreatedTimestamp());
        $t->same([
            'modifiedAt' => $modifiedAt,
            'accessedAt' => $accessedAt,
            'createdAt' => $createdAt,
        ], $package->localExtendedTimestamps('/word/media/reviewer-note.txt'));
        $t->same($modifiedAt, $package->localExtendedLastModifiedTimestamp('word/media/reviewer-note.txt'));
        $t->same($accessedAt, $package->localExtendedAccessedTimestamp('word/media/reviewer-note.txt'));
        $t->same($createdAt, $package->localExtendedCreatedTimestamp('word/media/reviewer-note.txt'));
        $t->same($modifiedAt, $entry->lastModifiedTimestamp());
        $t->same('reviewer media provenance', $package->read('/word/media/reviewer-note.txt'));
    },

    'reads local extended timestamp fields without modified time' => static function (TestRunner $t) use ($buildZipPackage): void {
        $accessedAt = 1780479022;
        $createdAt = 1780479023;
        $localTimestampExtra = pack('vvCVV', 0x5455, 9, 0x06, $accessedAt, $createdAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/audit-source.txt',
                'data' => 'source packet audit timestamp',
                'method' => 0,
                'localExtra' => $localTimestampExtra,
                'centralExtra' => '',
            ],
        ]));

        $t->same(null, $package->entry('/word/media/audit-source.txt')->extendedTimestamps());
        $t->same([
            'accessedAt' => $accessedAt,
            'createdAt' => $createdAt,
        ], $package->localExtendedTimestamps('word/media/audit-source.txt'));
        $t->same(null, $package->localExtendedLastModifiedTimestamp('word/media/audit-source.txt'));
        $t->same($accessedAt, $package->localExtendedAccessedTimestamp('word/media/audit-source.txt'));
        $t->same($createdAt, $package->localExtendedCreatedTimestamp('word/media/audit-source.txt'));
        $t->same('source packet audit timestamp', $package->read('word/media/audit-source.txt'));
    },

    'rejects local extended timestamp mismatches before exposing package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $centralTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479017);
        $localTimestampExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479018);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>mismatch</w:p></w:document>',
                'method' => 8,
                'localExtra' => $localTimestampExtra,
                'centralExtra' => $centralTimestampExtra,
            ],
        ])));
    },

    'writes extended timestamp extra fields for generated package parts' => static function (TestRunner $t): void {
        $modifiedAt = 1780479017;
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>exact modified timestamp</w:p></w:document>',
                'modifiedAt' => $modifiedAt,
            ],
        ]);

        $roundTrip = ZipPackage::fromString($package->bytes());
        $entry = $roundTrip->entry('word/document.xml');

        $t->same(19400, $entry->modifiedDosTime());
        $t->same(23747, $entry->modifiedDosDate());
        $t->same($modifiedAt, $entry->extendedLastModifiedTimestamp());
        $t->same($modifiedAt, $entry->lastModifiedTimestamp());
        $t->same("\x01" . pack('V', $modifiedAt), $entry->centralExtraField(0x5455));
        $t->contains(pack('vvCV', 0x5455, 5, 0x01, $modifiedAt), $package->bytes());
        $t->same('<w:document><w:p>exact modified timestamp</w:p></w:document>', $roundTrip->read('word/document.xml'));
    },

    'writes bounded custom zip extra fields for generated review package parts' => static function (TestRunner $t): void {
        $modifiedAt = 1780479017;
        $reviewExtra = pack('vva*', 0xcafe, strlen('wp-review:v1'), 'wp-review:v1');
        $package = ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'review packet provenance',
                'modifiedAt' => $modifiedAt,
                'extraFieldData' => $reviewExtra,
            ],
        ]);

        $roundTrip = ZipPackage::fromString($package->bytes());
        $entry = $roundTrip->entry('word/media/reviewer-note.txt');

        $t->same($modifiedAt, $entry->extendedLastModifiedTimestamp());
        $t->same('wp-review:v1', $entry->centralExtraField(0xcafe));
        $t->same('wp-review:v1', $roundTrip->localExtraField('/word/media/reviewer-note.txt', 0xcafe));
        $t->same([
            ['id' => 0x5455, 'data' => "\x01" . pack('V', $modifiedAt)],
            ['id' => 0xcafe, 'data' => 'wp-review:v1'],
        ], $entry->centralExtraFields());
        $t->same([
            ['id' => 0x5455, 'data' => "\x01" . pack('V', $modifiedAt)],
            ['id' => 0xcafe, 'data' => 'wp-review:v1'],
        ], $roundTrip->localExtraFields('word/media/reviewer-note.txt'));
        $t->same('review packet provenance', $roundTrip->read('word/media/reviewer-note.txt'));
    },

    'rejects duplicate generated zip extra fields before package writing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'duplicate generated extra-field ids',
                'extraFieldData' => pack('vva*', 0xcafe, strlen('first-review'), 'first-review')
                    . pack('vva*', 0xcafe, strlen('second-review'), 'second-review'),
            ],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'duplicate generated extended timestamp id',
                'modifiedAt' => 1780479017,
                'extraFieldData' => pack('vvCV', 0x5455, 5, 0x01, 1780479018),
            ],
        ]));

        $package = ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'safe generated extra-field ids',
                'modifiedAt' => 1780479017,
                'extraFieldData' => pack('vva*', 0xcafe, strlen('wp-review:v1'), 'wp-review:v1'),
            ],
        ]);
        $summary = $package->assertNoDuplicateExtraFieldIds();

        $t->same(0, $summary['duplicateExtraFieldEntryCount']);
        $t->same([0x5455, 0xcafe], $summary['entries'][0]['centralExtraFieldIds']);
        $t->same([0x5455, 0xcafe], $summary['entries'][0]['localExtraFieldIds']);
        $t->same('safe generated extra-field ids', ZipPackage::fromString($package->bytes())->read('word/media/reviewer-note.txt'));
    },

    'preflights duplicate zip extra field ids before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $centralExtra = pack('vva*', 0xcafe, strlen('central-one'), 'central-one')
            . pack('vva*', 0xcafe, strlen('central-two'), 'central-two')
            . pack('vva*', 0x5455, 5, "\x01" . pack('V', 1780479017));
        $localExtra = pack('vva*', 0xbeef, strlen('local-one'), 'local-one')
            . pack('vva*', 0xbeef, strlen('local-two'), 'local-two')
            . pack('vva*', 0x5455, 5, "\x01" . pack('V', 1780479017));
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'reviewer media provenance with duplicate extra fields',
                'method' => 0,
                'centralExtra' => $centralExtra,
                'localExtra' => $localExtra,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>duplicate extra field audit</w:p></w:document>',
                'method' => 8,
            ],
        ]));
        $summary = $package->extraFieldPreflight();

        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['extraFieldEntryCount']);
        $t->same(1, $summary['duplicateExtraFieldEntryCount']);
        $t->same(1, $summary['duplicateCentralExtraFieldEntryCount']);
        $t->same(1, $summary['duplicateLocalExtraFieldEntryCount']);
        $t->same('word/media/reviewer-note.txt', $summary['duplicateEntries'][0]['name']);
        $t->same([0xcafe, 0xcafe, 0x5455], $summary['entries'][0]['centralExtraFieldIds']);
        $t->same(['0xcafe', '0xcafe', '0x5455'], $summary['entries'][0]['centralExtraFieldIdHexes']);
        $t->same([0xbeef, 0xbeef, 0x5455], $summary['entries'][0]['localExtraFieldIds']);
        $t->same(['0xbeef', '0xbeef', '0x5455'], $summary['entries'][0]['localExtraFieldIdHexes']);
        $t->same([0xcafe], $summary['entries'][0]['duplicateCentralExtraFieldIds']);
        $t->same(['0xcafe'], $summary['entries'][0]['duplicateCentralExtraFieldIdHexes']);
        $t->same([0xbeef], $summary['entries'][0]['duplicateLocalExtraFieldIds']);
        $t->same(['0xbeef'], $summary['entries'][0]['duplicateLocalExtraFieldIdHexes']);
        $t->same(true, $summary['entries'][0]['hasDuplicateExtraFieldIds']);
        $t->same([], $summary['entries'][1]['duplicateCentralExtraFieldIds']);
        $t->same([], $summary['entries'][1]['duplicateCentralExtraFieldIdHexes']);
        $t->same([], $summary['entries'][1]['duplicateLocalExtraFieldIds']);
        $t->same([], $summary['entries'][1]['duplicateLocalExtraFieldIdHexes']);
        $t->same('central-one', $package->entry('word/media/reviewer-note.txt')->centralExtraField(0xcafe));
        $t->same('local-one', $package->localExtraField('/word/media/reviewer-note.txt', 0xbeef));
        $t->same('reviewer media provenance with duplicate extra fields', $package->read('/word/media/reviewer-note.txt'));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoDuplicateExtraFieldIds());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>safe extra fields</w:p></w:document>',
                'modifiedAt' => 1780479017,
                'extraFieldData' => pack('vva*', 0xcafe, strlen('wp-review:v1'), 'wp-review:v1'),
            ],
        ]);
        $safeSummary = $safePackage->assertNoDuplicateExtraFieldIds();

        $t->same(1, $safeSummary['entryCount']);
        $t->same(1, $safeSummary['extraFieldEntryCount']);
        $t->same(0, $safeSummary['duplicateExtraFieldEntryCount']);
        $t->same([], $safeSummary['duplicateEntries']);
        $t->same([0x5455, 0xcafe], $safeSummary['entries'][0]['centralExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $safeSummary['entries'][0]['centralExtraFieldIdHexes']);
        $t->same([0x5455, 0xcafe], $safeSummary['entries'][0]['localExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $safeSummary['entries'][0]['localExtraFieldIdHexes']);
    },

    'preflights central and local zip extra field id mismatches before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $timestampExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479017);
        $centralReviewExtra = pack('vva*', 0xcafe, strlen('central-review'), 'central-review');
        $localReviewExtra = pack('vva*', 0xbeef, strlen('local-review'), 'local-review');
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'review media with split central/local metadata',
                'method' => 0,
                'centralExtra' => $timestampExtra . $centralReviewExtra,
                'localExtra' => $timestampExtra . $localReviewExtra,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>extra field mismatch audit</w:p></w:document>',
                'method' => 8,
            ],
        ]));
        $summary = $package->extraFieldPreflight();

        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['extraFieldEntryCount']);
        $t->same(0, $summary['duplicateExtraFieldEntryCount']);
        $t->same(1, $summary['mismatchedExtraFieldEntryCount']);
        $t->same(1, $summary['centralOnlyExtraFieldEntryCount']);
        $t->same(1, $summary['localOnlyExtraFieldEntryCount']);
        $t->same('word/media/reviewer-note.txt', $summary['mismatchedEntries'][0]['name']);
        $t->same([0x5455, 0xcafe], $summary['mismatchedEntries'][0]['centralExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $summary['mismatchedEntries'][0]['centralExtraFieldIdHexes']);
        $t->same([0x5455, 0xbeef], $summary['mismatchedEntries'][0]['localExtraFieldIds']);
        $t->same(['0x5455', '0xbeef'], $summary['mismatchedEntries'][0]['localExtraFieldIdHexes']);
        $t->same([0xcafe], $summary['mismatchedEntries'][0]['centralOnlyExtraFieldIds']);
        $t->same(['0xcafe'], $summary['mismatchedEntries'][0]['centralOnlyExtraFieldIdHexes']);
        $t->same([0xbeef], $summary['mismatchedEntries'][0]['localOnlyExtraFieldIds']);
        $t->same(['0xbeef'], $summary['mismatchedEntries'][0]['localOnlyExtraFieldIdHexes']);
        $t->same(true, $summary['mismatchedEntries'][0]['hasMismatchedExtraFieldIds']);
        $t->same(false, $summary['mismatchedEntries'][0]['hasDuplicateExtraFieldIds']);
        $t->same(false, $summary['entries'][1]['hasMismatchedExtraFieldIds']);
        $t->same('central-review', $package->entry('word/media/reviewer-note.txt')->centralExtraField(0xcafe));
        $t->same('local-review', $package->localExtraField('/word/media/reviewer-note.txt', 0xbeef));
        $t->same('review media with split central/local metadata', $package->read('/word/media/reviewer-note.txt'));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertMatchingExtraFieldIds());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'matching review metadata',
                'modifiedAt' => 1780479017,
                'extraFieldData' => pack('vva*', 0xcafe, strlen('wp-review:v1'), 'wp-review:v1'),
            ],
        ]);
        $safeSummary = $safePackage->assertMatchingExtraFieldIds();

        $t->same(1, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['mismatchedExtraFieldEntryCount']);
        $t->same([], $safeSummary['mismatchedEntries']);
        $t->same([0x5455, 0xcafe], $safeSummary['entries'][0]['centralExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $safeSummary['entries'][0]['centralExtraFieldIdHexes']);
        $t->same([0x5455, 0xcafe], $safeSummary['entries'][0]['localExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $safeSummary['entries'][0]['localExtraFieldIdHexes']);
        $t->same([], $safeSummary['entries'][0]['centralOnlyExtraFieldIds']);
        $t->same([], $safeSummary['entries'][0]['centralOnlyExtraFieldIdHexes']);
        $t->same([], $safeSummary['entries'][0]['localOnlyExtraFieldIds']);
        $t->same([], $safeSummary['entries'][0]['localOnlyExtraFieldIdHexes']);
    },

    'preflights central and local zip extra field value mismatches before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $timestampExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479017);
        $centralReviewExtra = pack('vva*', 0xcafe, strlen('central-review'), 'central-review');
        $localReviewExtra = pack('vva*', 0xcafe, strlen('local-review'), 'local-review');
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'review media with conflicting central/local metadata values',
                'method' => 0,
                'centralExtra' => $timestampExtra . $centralReviewExtra,
                'localExtra' => $timestampExtra . $localReviewExtra,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>extra field value mismatch audit</w:p></w:document>',
                'method' => 8,
            ],
        ]));
        $summary = $package->extraFieldPreflight();

        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['extraFieldEntryCount']);
        $t->same(0, $summary['duplicateExtraFieldEntryCount']);
        $t->same(0, $summary['mismatchedExtraFieldEntryCount']);
        $t->same(1, $summary['mismatchedExtraFieldValueEntryCount']);
        $t->same(0, $summary['centralOnlyExtraFieldEntryCount']);
        $t->same(0, $summary['localOnlyExtraFieldEntryCount']);
        $t->same([], $summary['mismatchedEntries']);
        $t->same('word/media/reviewer-note.txt', $summary['valueMismatchedEntries'][0]['name']);
        $t->same([0x5455, 0xcafe], $summary['valueMismatchedEntries'][0]['centralExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $summary['valueMismatchedEntries'][0]['centralExtraFieldIdHexes']);
        $t->same([0x5455, 0xcafe], $summary['valueMismatchedEntries'][0]['localExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $summary['valueMismatchedEntries'][0]['localExtraFieldIdHexes']);
        $t->same([0xcafe], $summary['valueMismatchedEntries'][0]['mismatchedExtraFieldValueIds']);
        $t->same(['0xcafe'], $summary['valueMismatchedEntries'][0]['mismatchedExtraFieldValueIdHexes']);
        $t->same(false, $summary['valueMismatchedEntries'][0]['hasMismatchedExtraFieldIds']);
        $t->same(true, $summary['valueMismatchedEntries'][0]['hasMismatchedExtraFieldValues']);
        $t->same(false, $summary['entries'][1]['hasMismatchedExtraFieldValues']);
        $t->same([], $summary['entries'][1]['mismatchedExtraFieldValueIds']);
        $t->same([], $summary['entries'][1]['mismatchedExtraFieldValueIdHexes']);
        $t->same('central-review', $package->entry('word/media/reviewer-note.txt')->centralExtraField(0xcafe));
        $t->same('local-review', $package->localExtraField('/word/media/reviewer-note.txt', 0xcafe));
        $t->same('review media with conflicting central/local metadata values', $package->read('/word/media/reviewer-note.txt'));
        $t->same($summary, $package->assertMatchingExtraFieldIds());
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertMatchingExtraFieldValues());

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'matching review metadata values',
                'modifiedAt' => 1780479017,
                'extraFieldData' => pack('vva*', 0xcafe, strlen('wp-review:v1'), 'wp-review:v1'),
            ],
        ]);
        $safeSummary = $safePackage->assertMatchingExtraFieldValues();

        $t->same(1, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['mismatchedExtraFieldValueEntryCount']);
        $t->same([], $safeSummary['valueMismatchedEntries']);
        $t->same([0x5455, 0xcafe], $safeSummary['entries'][0]['centralExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $safeSummary['entries'][0]['centralExtraFieldIdHexes']);
        $t->same([0x5455, 0xcafe], $safeSummary['entries'][0]['localExtraFieldIds']);
        $t->same(['0x5455', '0xcafe'], $safeSummary['entries'][0]['localExtraFieldIdHexes']);
        $t->same([], $safeSummary['entries'][0]['mismatchedExtraFieldValueIds']);
        $t->same([], $safeSummary['entries'][0]['mismatchedExtraFieldValueIdHexes']);
        $t->same(false, $safeSummary['entries'][0]['hasMismatchedExtraFieldValues']);
        $t->same('matching review metadata values', $safePackage->read('word/media/reviewer-note.txt'));
    },

    'summarizes zip extra field id usage across central and local headers' => static function (TestRunner $t) use ($buildZipPackage): void {
        $timestampExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479017);
        $centralReviewExtra = pack('vva*', 0xcafe, strlen('central-review'), 'central-review');
        $localReviewExtra = pack('vva*', 0xcafe, strlen('local-review'), 'local-review');
        $centralOnlyExtra = pack('vva*', 0x1111, strlen('central-only'), 'central-only');
        $localOnlyExtra = pack('vva*', 0x2222, strlen('local-only'), 'local-only');
        $localAuditExtra = pack('vva*', 0x3333, strlen('audit-local'), 'audit-local');
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>extra field id usage</w:p></w:document>',
                'method' => 8,
                'centralExtra' => $timestampExtra . $centralReviewExtra,
                'localExtra' => $timestampExtra . $localReviewExtra,
            ],
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'central and local-only extra-field ids',
                'method' => 0,
                'centralExtra' => $centralOnlyExtra,
                'localExtra' => $localOnlyExtra,
            ],
            [
                'name' => 'word/media/local-audit.txt',
                'data' => 'local-only audit extra-field id',
                'method' => 0,
                'centralExtra' => '',
                'localExtra' => $localAuditExtra,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $summary = $package->extraFieldPreflight();
        $rawSummary = ZipPackage::extraFieldPolicyPreflight($zip);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $usageById = [];
        foreach ($summary['extraFieldIdUsage'] as $row) {
            $usageById[$row['id']] = $row;
        }

        $t->same(3, $summary['extraFieldEntryCount']);
        $t->same(5, $summary['extraFieldIdCount']);
        $t->same(3, $summary['centralExtraFieldIdCount']);
        $t->same(4, $summary['localExtraFieldIdCount']);
        $t->same(2, $summary['sharedExtraFieldIdCount']);
        $t->same(1, $summary['centralOnlyExtraFieldIdCount']);
        $t->same(2, $summary['localOnlyExtraFieldIdCount']);
        $t->same([0x1111, 0x2222, 0x3333, 0x5455, 0xcafe], array_column($summary['extraFieldIdUsage'], 'id'));
        $t->same(['0x1111', '0x2222', '0x3333', '0x5455', '0xcafe'], $summary['extraFieldIdHexes']);
        $t->same(['0x1111', '0x5455', '0xcafe'], $summary['centralExtraFieldIdHexes']);
        $t->same(['0x2222', '0x3333', '0x5455', '0xcafe'], $summary['localExtraFieldIdHexes']);
        $t->same(['0x5455', '0xcafe'], $summary['sharedExtraFieldIdHexes']);
        $t->same(['0x1111'], $summary['centralOnlyExtraFieldIdHexes']);
        $t->same(['0x2222', '0x3333'], $summary['localOnlyExtraFieldIdHexes']);
        $t->same($summary['extraFieldIdHexes'], $rawSummary['extraFieldIdHexes']);
        $t->same($summary['centralExtraFieldIdHexes'], $rawSummary['centralExtraFieldIdHexes']);
        $t->same($summary['localExtraFieldIdHexes'], $rawSummary['localExtraFieldIdHexes']);
        $t->same($summary['sharedExtraFieldIdHexes'], $rawSummary['sharedExtraFieldIdHexes']);
        $t->same($summary['centralOnlyExtraFieldIdHexes'], $rawSummary['centralOnlyExtraFieldIdHexes']);
        $t->same($summary['localOnlyExtraFieldIdHexes'], $rawSummary['localOnlyExtraFieldIdHexes']);
        $t->same($summary['extraFieldIdHexes'], $strict['extraFields']['extraFieldIdHexes']);
        $t->same($summary['extraFieldIdHexes'], $rawStrict['extraFields']['extraFieldIdHexes']);
        $t->same($summary['extraFieldIdUsage'], $rawSummary['extraFieldIdUsage']);
        $t->same($summary['extraFieldIdUsage'], $strict['extraFields']['extraFieldIdUsage']);
        $t->same($summary['extraFieldIdUsage'], $rawStrict['extraFields']['extraFieldIdUsage']);

        $timestampUsage = $usageById[0x5455];
        $t->same('0x5455', $timestampUsage['idHex']);
        $t->same(1, $timestampUsage['centralRecordCount']);
        $t->same(1, $timestampUsage['localRecordCount']);
        $t->same(1, $timestampUsage['centralEntryCount']);
        $t->same(1, $timestampUsage['localEntryCount']);
        $t->same(true, $timestampUsage['appearsInBoth']);
        $t->same(false, $timestampUsage['appearsOnlyInCentral']);
        $t->same(false, $timestampUsage['appearsOnlyInLocal']);
        $t->same(['word/document.xml'], $timestampUsage['centralEntryNames']);
        $t->same(['word/document.xml'], $timestampUsage['localEntryNames']);

        $centralOnlyUsage = $usageById[0x1111];
        $t->same(true, $centralOnlyUsage['appearsOnlyInCentral']);
        $t->same(false, $centralOnlyUsage['appearsInLocal']);
        $t->same(['word/media/reviewer-note.txt'], $centralOnlyUsage['centralEntryNames']);
        $t->same([], $centralOnlyUsage['localEntryNames']);

        $localAuditUsage = $usageById[0x3333];
        $t->same(false, $localAuditUsage['appearsInCentral']);
        $t->same(true, $localAuditUsage['appearsOnlyInLocal']);
        $t->same([], $localAuditUsage['centralEntryNames']);
        $t->same(['word/media/local-audit.txt'], $localAuditUsage['localEntryNames']);

        $reviewUsage = $usageById[0xcafe];
        $t->same(true, $reviewUsage['appearsInBoth']);
        $t->same(['word/document.xml'], $reviewUsage['centralEntryNames']);
        $t->same(['word/document.xml'], $reviewUsage['localEntryNames']);
        $t->same([0x1111], $summary['mismatchedEntries'][0]['centralOnlyExtraFieldIds']);
        $t->same(['0x1111'], $summary['mismatchedEntries'][0]['centralOnlyExtraFieldIdHexes']);
        $t->same([0xcafe], $summary['valueMismatchedEntries'][0]['mismatchedExtraFieldValueIds']);
        $t->same(['0xcafe'], $summary['valueMismatchedEntries'][0]['mismatchedExtraFieldValueIdHexes']);
        $t->contains('central-local-extra-field-id-mismatch', implode(',', $strict['diagnostics']));
        $t->contains('central-local-extra-field-value-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->same('central and local-only extra-field ids', $package->read('word/media/reviewer-note.txt'));
    },

    'reads ntfs zip extra field timestamps for office package preflight' => static function (TestRunner $t) use ($buildZipPackage, $buildNtfsExtra): void {
        $modifiedAt = 1780479017;
        $accessedAt = 1780479018;
        $createdAt = 1780479019;
        $ntfsExtra = $buildNtfsExtra($modifiedAt, $accessedAt, $createdAt);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ntfs timestamps</w:p></w:document>',
                'method' => 8,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localExtra' => $ntfsExtra,
                'centralExtra' => $ntfsExtra,
            ],
        ]));

        $entry = $package->entry('word/document.xml');
        $timestamps = [
            'modifiedAt' => $modifiedAt,
            'accessedAt' => $accessedAt,
            'createdAt' => $createdAt,
        ];

        $t->same($timestamps, $entry->ntfsTimestamps());
        $t->same($modifiedAt, $entry->ntfsLastModifiedTimestamp());
        $t->same($modifiedAt, $entry->lastModifiedTimestamp());
        $t->same($timestamps, $package->localNtfsTimestamps('/word/document.xml'));
        $t->same($modifiedAt, $package->localNtfsLastModifiedTimestamp('word/document.xml'));
        $t->same('<w:document><w:p>ntfs timestamps</w:p></w:document>', $package->read('/word/document.xml'));
    },

    'preflights unix uid gid zip owner extra fields before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildUnixOwnerExtra): void {
        $ownerExtra = $buildUnixOwnerExtra(1001, 1002);
        $owner = [
            'version' => 1,
            'uid' => 1001,
            'gid' => 1002,
            'uidByteLength' => 2,
            'gidByteLength' => 2,
        ];
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>unix owner preflight</w:p></w:document>',
                'method' => 8,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => "reviewer media owner metadata\n",
                'method' => 0,
                'localExtra' => $ownerExtra,
                'centralExtra' => $ownerExtra,
                'externalAttributes' => 0x81a40000,
            ],
        ]));

        $entry = $package->entry('/word/media/reviewer-note.txt');
        $t->same($owner, $entry->unixUidGid());
        $t->same($owner, $package->localUnixUidGid('/word/media/reviewer-note.txt'));

        $summary = $package->unixOwnerPreflight();
        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['ownerMetadataEntryCount']);
        $t->same(1, $summary['centralOwnerMetadataEntryCount']);
        $t->same(1, $summary['localOwnerMetadataEntryCount']);
        $t->same(0, $summary['mismatchedOwnerMetadataEntryCount']);
        $t->same('word/media/reviewer-note.txt', $summary['ownerMetadataEntries'][0]['name']);
        $t->same($owner, $summary['ownerMetadataEntries'][0]['centralOwner']);
        $t->same($owner, $summary['ownerMetadataEntries'][0]['localOwner']);
        $t->same(true, $summary['ownerMetadataEntries'][0]['ownerMetadataMatches']);
        $t->same([
            'central-unix-uid-gid-extra-field',
            'local-unix-uid-gid-extra-field',
        ], $summary['ownerMetadataEntries'][0]['issues']);

        $strictSummary = $package->strictImportPreflight(2048, 100.0, 2048);
        $t->same(false, $strictSummary['isValid']);
        $t->same(['unix-owner-extra-fields'], $strictSummary['diagnostics']);
        $t->same(1, $strictSummary['unixOwners']['ownerMetadataEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoUnixOwnerMetadata());
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(2048, 100.0, 2048));
        $t->same("reviewer media owner metadata\n", $package->read('/word/media/reviewer-note.txt'));

        $localOnlyPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/local-owner.txt',
                'data' => "local owner metadata\n",
                'method' => 0,
                'localExtra' => $buildUnixOwnerExtra(33, 44),
                'centralExtra' => '',
                'externalAttributes' => 0x81a40000,
            ],
        ]));
        $localOnlySummary = $localOnlyPackage->unixOwnerPreflight();
        $t->same(1, $localOnlySummary['ownerMetadataEntryCount']);
        $t->same(0, $localOnlySummary['centralOwnerMetadataEntryCount']);
        $t->same(1, $localOnlySummary['localOwnerMetadataEntryCount']);
        $t->same(null, $localOnlySummary['ownerMetadataEntries'][0]['centralOwner']);
        $t->same(33, $localOnlySummary['ownerMetadataEntries'][0]['localOwner']['uid']);
        $t->same([
            'local-unix-uid-gid-extra-field',
        ], $localOnlySummary['ownerMetadataEntries'][0]['issues']);

        $mismatchedPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/mismatched-owner.txt',
                'data' => "mismatched owner metadata\n",
                'method' => 0,
                'localExtra' => $buildUnixOwnerExtra(600, 601),
                'centralExtra' => $buildUnixOwnerExtra(500, 501),
                'externalAttributes' => 0x81a40000,
            ],
        ]));
        $mismatchedSummary = $mismatchedPackage->unixOwnerPreflight();
        $t->same(1, $mismatchedSummary['ownerMetadataEntryCount']);
        $t->same(1, $mismatchedSummary['mismatchedOwnerMetadataEntryCount']);
        $t->same(false, $mismatchedSummary['mismatchedOwnerMetadataEntries'][0]['ownerMetadataMatches']);
        $t->same([
            'central-unix-uid-gid-extra-field',
            'local-unix-uid-gid-extra-field',
            'unix-uid-gid-mismatch',
        ], $mismatchedSummary['mismatchedOwnerMetadataEntries'][0]['issues']);
        $t->contains('unix-owner-extra-fields', implode(',', $mismatchedPackage->strictImportPreflight(2048, 100.0, 2048)['diagnostics']));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/truncated-owner.txt',
                'data' => 'truncated owner extra field',
                'method' => 0,
                'centralExtra' => pack('vvCC', 0x7875, 2, 1, 1),
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/unsupported-owner-version.txt',
                'data' => 'unsupported owner extra version',
                'method' => 0,
                'centralExtra' => pack('vvCCCCC', 0x7875, 5, 2, 1, 1, 1, 2),
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/empty-owner-id.txt',
                'data' => 'empty owner id',
                'method' => 0,
                'centralExtra' => pack('vvCC', 0x7875, 2, 1, 0),
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/generated-owner.txt',
                'data' => "generated owner metadata\n",
                'extraFieldData' => $ownerExtra,
            ],
        ]));
    },

    'preflights raw unix uid gid owner extra fields before package instantiation' => static function (TestRunner $t) use ($buildZipPackage, $buildUnixOwnerExtra): void {
        $centralOnlyOwner = [
            'version' => 1,
            'uid' => 10,
            'gid' => 11,
            'uidByteLength' => 1,
            'gidByteLength' => 1,
        ];
        $localOnlyOwner = [
            'version' => 1,
            'uid' => 20,
            'gid' => 21,
            'uidByteLength' => 1,
            'gidByteLength' => 1,
        ];
        $centralMismatchOwner = [
            'version' => 1,
            'uid' => 30,
            'gid' => 31,
            'uidByteLength' => 1,
            'gidByteLength' => 1,
        ];
        $localMismatchOwner = [
            'version' => 1,
            'uid' => 32,
            'gid' => 33,
            'uidByteLength' => 1,
            'gidByteLength' => 1,
        ];
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/document-local-spoof.xml',
                'data' => '<w:document><w:p>raw owner preflight</w:p></w:document>',
                'method' => 0,
                'centralExtra' => $buildUnixOwnerExtra(10, 11),
            ],
            [
                'name' => 'word/media/local-owner.txt',
                'data' => "local owner metadata\n",
                'method' => 0,
                'localExtra' => $buildUnixOwnerExtra(20, 21),
                'centralExtra' => '',
            ],
            [
                'name' => 'word/media/mismatched-owner.txt',
                'data' => "mismatched owner metadata\n",
                'method' => 0,
                'centralExtra' => $buildUnixOwnerExtra(30, 31),
                'localExtra' => $buildUnixOwnerExtra(32, 33),
            ],
        ]);

        $summary = ZipPackage::unixOwnerPolicyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);

        $t->same(3, $summary['entryCount']);
        $t->same(3, $summary['ownerMetadataEntryCount']);
        $t->same(2, $summary['centralOwnerMetadataEntryCount']);
        $t->same(2, $summary['localOwnerMetadataEntryCount']);
        $t->same(1, $summary['mismatchedOwnerMetadataEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['unix-owner-extra-fields', 'unix-uid-gid-mismatch'], $summary['issues']);

        $centralOnly = $summary['ownerMetadataEntries'][0];
        $t->same('word/document.xml', $centralOnly['name']);
        $t->same($centralOnlyOwner, $centralOnly['centralOwner']);
        $t->same(null, $centralOnly['localOwner']);
        $t->same(true, $centralOnly['hasCentralOwnerMetadata']);
        $t->same(false, $centralOnly['hasLocalOwnerMetadata']);
        $t->same(true, $centralOnly['ownerMetadataMatches']);
        $t->same('blocked', $centralOnly['policy']);
        $t->same(['central-unix-uid-gid-extra-field'], $centralOnly['issues']);
        $t->same(['zip-central-unix-uid-gid-extra-field'], $centralOnly['diagnostics']);

        $localOnly = $summary['ownerMetadataEntries'][1];
        $t->same('word/media/local-owner.txt', $localOnly['name']);
        $t->same(null, $localOnly['centralOwner']);
        $t->same($localOnlyOwner, $localOnly['localOwner']);
        $t->same([
            'local-unix-uid-gid-extra-field',
        ], $localOnly['issues']);

        $mismatch = $summary['mismatchedOwnerMetadataEntries'][0];
        $t->same('word/media/mismatched-owner.txt', $mismatch['name']);
        $t->same($centralMismatchOwner, $mismatch['centralOwner']);
        $t->same($localMismatchOwner, $mismatch['localOwner']);
        $t->same(false, $mismatch['ownerMetadataMatches']);
        $t->same([
            'central-unix-uid-gid-extra-field',
            'local-unix-uid-gid-extra-field',
            'unix-uid-gid-mismatch',
        ], $mismatch['issues']);
        $t->same([
            'zip-central-unix-uid-gid-extra-field',
            'zip-local-unix-uid-gid-extra-field',
            'zip-unix-uid-gid-mismatch',
        ], $mismatch['diagnostics']);

        $t->same($summary, $rawStrict['unixOwners']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->contains('unix-owner-extra-fields', implode(',', $rawStrict['diagnostics']));
        $t->contains('unix-uid-gid-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-header-name-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'rejects malformed and mismatched ntfs zip extra field metadata' => static function (TestRunner $t) use ($buildZipPackage, $buildNtfsExtra): void {
        $centralNtfsExtra = $buildNtfsExtra(1780479017, 1780479018, 1780479019);
        $localNtfsExtra = $buildNtfsExtra(1780479020, 1780479018, 1780479019);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ntfs mismatch</w:p></w:document>',
                'method' => 0,
                'centralExtra' => $centralNtfsExtra,
                'localExtra' => $localNtfsExtra,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
                'centralExtra' => pack('vvVvv', 0x000a, 12, 0, 0x0001, 20) . str_repeat("\0", 20),
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/nonzero-ntfs-reserved.bin',
                'data' => 'NTFS reserved bytes must stay zero',
                'method' => 0,
                'centralExtra' => pack('vvV', 0x000a, 4, 1),
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes/>',
                'method' => 0,
                'centralExtra' => $centralNtfsExtra,
                'localExtra' => pack('vvC', 0x000a, 4, 0),
            ],
        ])));
    },

    'tolerates malformed optional extended timestamp extra fields' => static function (TestRunner $t) use ($buildZipPackage): void {
        $truncatedCentralExtra = pack('vvC', 0x5455, 1, 0x01);
        $truncatedLocalExtra = pack('vvC', 0x5455, 1, 0x01);
        $validCentralExtra = pack('vvCV', 0x5455, 5, 0x01, 1780479017);

        $centralTruncated = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document/>',
                'method' => 0,
                'centralExtra' => $truncatedCentralExtra,
            ],
        ]));
        $t->same('<w:document/>', $centralTruncated->read('word/document.xml'));
        $t->same(null, $centralTruncated->entry('word/document.xml')->lastModifiedTimestamp());

        $centralAccessTruncated = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'truncated central access timestamp',
                'method' => 0,
                'centralExtra' => pack('vvC', 0x5455, 1, 0x02),
            ],
        ]));
        $t->same('truncated central access timestamp', $centralAccessTruncated->read('word/media/reviewer-note.txt'));

        $localTruncated = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document/>',
                'method' => 0,
                'centralExtra' => $validCentralExtra,
                'localExtra' => $truncatedLocalExtra,
            ],
        ]));
        $t->same('<w:document/>', $localTruncated->read('word/document.xml'));

        $localCreationTruncated = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'truncated local creation timestamp',
                'method' => 0,
                'centralExtra' => '',
                'localExtra' => pack('vvCVV', 0x5455, 9, 0x07, 1780479017, 1780479022),
            ],
        ]));
        $t->same('truncated local creation timestamp', $localCreationTruncated->read('word/media/reviewer-note.txt'));

        $unknownFlag = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/unknown-extended-timestamp-flag.txt',
                'data' => 'unknown extended timestamp flags should stay blocked',
                'method' => 0,
                'centralExtra' => pack('vvCV', 0x5455, 5, 0x09, 1780479017),
            ],
        ]));
        $t->same('unknown extended timestamp flags should stay blocked', $unknownFlag->read('word/media/unknown-extended-timestamp-flag.txt'));

        $trailing = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/trailing-extended-timestamp.txt',
                'data' => 'trailing extended timestamp bytes should stay blocked',
                'method' => 0,
                'centralExtra' => pack('vvCVV', 0x5455, 9, 0x01, 1780479017, 0),
            ],
        ]));
        $t->same('trailing extended timestamp bytes should stay blocked', $trailing->read('word/media/trailing-extended-timestamp.txt'));

        $localTrailing = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/local-trailing-extended-timestamp.txt',
                'data' => 'local trailing extended timestamp bytes should stay blocked',
                'method' => 0,
                'centralExtra' => '',
                'localExtra' => pack('vvCVV', 0x5455, 9, 0x02, 1780479022, 0),
            ],
        ]));
        $t->same('local trailing extended timestamp bytes should stay blocked', $localTrailing->read('word/media/local-trailing-extended-timestamp.txt'));
    },

    'rejects invalid generated zip package parts before writing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'first'],
            ['name' => 'word/document.xml', 'data' => 'second'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/../document.xml', 'data' => 'traversal'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'compressionMethod' => 12],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/media/', 'data' => 'not a directory entry'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/media/', 'compressionMethod' => 8],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            'not a part array',
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => []],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'comment' => str_repeat('x', 0x10000)],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok'],
        ], str_repeat('x', 0x10000)));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok'],
        ], "invalid package comment \xff"));
    },

    'rejects invalid generated zip entry metadata before writing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedAt' => '2026-06-03'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedAt' => 1780479016, 'modifiedDosTime' => 19400, 'modifiedDosDate' => 23747],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedDosTime' => 19400],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedDosTime' => 0x10000, 'modifiedDosDate' => 23747],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'modifiedAt' => 315532799],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'externalAttributes' => -1],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'extraFieldData' => ['not bytes']],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'extraFieldData' => pack('vvC', 0xcafe, 4, 1)],
        ]));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => 'ok', 'extraFieldData' => pack('vv', 0x0001, 8) . str_repeat("\0", 8)],
        ]));
    },

    'rejects semantically invalid generated zip dos timestamps before writing' => static function (TestRunner $t): void {
        $invalidFields = [
            ['modifiedDosTime' => 0x0000, 'modifiedDosDate' => 0x0001],
            ['modifiedDosTime' => 0xc000, 'modifiedDosDate' => 23747],
            ['modifiedDosTime' => 0x0780, 'modifiedDosDate' => 23747],
            ['modifiedDosTime' => 0x001e, 'modifiedDosDate' => 23747],
        ];

        foreach ($invalidFields as $fields) {
            $rejected = false;
            try {
                ZipPackage::fromParts([[
                    'name' => 'word/document.xml',
                    'data' => 'ok',
                    ...$fields,
                ]]);
            } catch (\RuntimeException $exception) {
                $rejected = str_contains($exception->getMessage(), 'valid timestamp');
            }

            $t->same(true, $rejected);
        }
    },

    'rejects unsafe package part names before exposing entries' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => '/word/document.xml', 'data' => 'absolute'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'C:word/document.xml', 'data' => 'drive letter'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/../document.xml', 'data' => 'traversal'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word\\document.xml', 'data' => 'backslash'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): bool => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => 'ok'],
        ]))->has('../word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'C:word/document.xml', 'data' => 'drive letter'],
        ]));

        $rawName = 'word/media/review-image.bin';
        $unsafeUnicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, 'C:word/media/review.png');
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'unicode path drive letter',
                'flags' => 0,
                'centralExtra' => $unsafeUnicodePathExtra,
            ],
        ])));
    },

    'rejects zip package part names with control bytes before exposing entries' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => "word/media/review\nimage.png", 'data' => 'newline raw central name'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => "word/media/review\timage.png", 'data' => 'tab raw central name'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => "word/media/review\x7fimage.png", 'data' => 'delete raw central name'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => "word/media/generated\nreview.png", 'data' => 'generated newline name'],
        ]));

        $rawName = 'word/media/review-image.bin';
        $unsafeUnicodePathExtra = $buildUnicodeExtra(0x7075, $rawName, "word/media/review\nimage.png");
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => $rawName,
                'data' => 'unicode path control byte',
                'flags' => 0,
                'centralExtra' => $unsafeUnicodePathExtra,
            ],
        ])));

        $safePackage = ZipPackage::fromParts([
            ['name' => 'word/media/review image.png', 'data' => 'spaces remain safe package names', 'compressionMethod' => 0],
            ['name' => 'word/media/review-image.png', 'data' => 'dash remains safe package name', 'compressionMethod' => 0],
        ]);
        $t->same(['word/media/review image.png', 'word/media/review-image.png'], $safePackage->names());
        $t->same('spaces remain safe package names', $safePackage->read('/word/media/review image.png'));
    },

    'rejects duplicate encrypted and split zip package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => 'first'],
            ['name' => 'word/document.xml', 'data' => 'second'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/encrypted.xml', 'data' => 'secret', 'flags' => 0x0801],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/split.xml', 'data' => 'split', 'diskStart' => 1],
        ])));
    },

    'rejects strong and central-directory encrypted zip metadata before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/strong-encryption.xml', 'data' => 'masked strong encryption metadata', 'flags' => 0x0840],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/central-directory-encrypted.xml', 'data' => 'masked local header metadata', 'flags' => 0x2800],
        ])));
    },

    'preflights traditional zip encryption header layout without extracting bytes' => static function (TestRunner $t) use ($buildTraditionalEncryptedPackage): void {
        $zip = $buildTraditionalEncryptedPackage();
        $summary = ZipPackage::encryptionPolicyPreflight($zip);
        $entry = $summary['encryptedEntries'][0];

        $t->same(1, $summary['entryCount']);
        $t->same(1, $summary['encryptedEntryCount']);
        $t->same(1, $summary['traditionalEncryptionEntryCount']);
        $t->same(0, $summary['strongEncryptionEntryCount']);
        $t->same(0, $summary['centralDirectoryEncryptionEntryCount']);
        $t->same(0, $summary['winZipAesEntryCount']);
        $t->same(0, $summary['truncatedTraditionalEncryptionHeaderEntryCount']);
        $t->same(true, $summary['hasEncryptedEntries']);
        $t->same('encrypted-zip-entries-blocked', $summary['extractionPolicy']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['encrypted-zip-entries'], $summary['issues']);
        $t->same('word/media/encrypted-review.bin', $entry['name']);
        $t->same('stored', $entry['compressionMethodName']);
        $t->same(0x0801, $entry['generalPurposeFlags']);
        $t->same(0x0801, $entry['localGeneralPurposeFlags']);
        $t->same(true, $entry['hasTraditionalEncryption']);
        $t->same(false, $entry['hasTruncatedTraditionalEncryptionHeader']);
        $t->same(['traditional'], $entry['encryptionTypes']);
        $t->same('blocked', $entry['policy']);
        $t->same(['zip-encrypted-entry-not-extracted', 'zip-traditional-encryption'], $entry['diagnostics']);
        $t->same(12, $entry['traditionalEncryptionHeaderLength']);
        $t->same(12, $entry['traditionalEncryptionHeaderAvailableBytes']);
        $t->same($entry['localHeaderDataOffset'], $entry['traditionalEncryptionHeaderOffset']);
        $t->same($entry['localHeaderDataOffset'] + 12, $entry['traditionalEncryptionPayloadOffset']);
        $t->same($entry['compressedSize'] - 12, $entry['traditionalEncryptionPayloadSize']);
        $t->same($entry['localHeaderDataOffset'] + $entry['compressedSize'], $entry['compressedDataEnd']);
        $t->same(true, $entry['compressedSizeIncludesTraditionalEncryptionHeader']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));

        $truncatedZip = $buildTraditionalEncryptedPackage('short', '');
        $truncated = ZipPackage::encryptionPolicyPreflight($truncatedZip);
        $truncatedEntry = $truncated['encryptedEntries'][0];

        $t->same(1, $truncated['truncatedTraditionalEncryptionHeaderEntryCount']);
        $t->same(['encrypted-zip-entries', 'truncated-traditional-encryption-header'], $truncated['issues']);
        $t->same(12, $truncatedEntry['traditionalEncryptionHeaderLength']);
        $t->same(5, $truncatedEntry['traditionalEncryptionHeaderAvailableBytes']);
        $t->same(null, $truncatedEntry['traditionalEncryptionPayloadOffset']);
        $t->same(null, $truncatedEntry['traditionalEncryptionPayloadSize']);
        $t->same(true, $truncatedEntry['hasTruncatedTraditionalEncryptionHeader']);
        $t->contains('zip-traditional-encryption-header-truncated', implode(',', $truncatedEntry['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($truncatedZip));
    },

    'rejects winzip aes extra fields before package import handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $aesExtra = pack('vva*', 0x9901, strlen('AE' . "\x02\x00" . 'vendor' . "\x08\x00"), 'AE' . "\x02\x00" . 'vendor' . "\x08\x00");

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/aes-central.bin',
                'data' => 'AES extra metadata should stay blocked from central entries',
                'method' => 0,
                'centralExtra' => $aesExtra,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/aes-local.bin',
                'data' => 'AES extra metadata should stay blocked from local headers',
                'method' => 0,
                'localExtra' => $aesExtra,
                'centralExtra' => '',
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromParts([
            [
                'name' => 'word/media/aes-generated.bin',
                'data' => 'AES extra metadata should stay blocked in generated packages',
                'compressionMethod' => 0,
                'extraFieldData' => $aesExtra,
            ],
        ]));
    },

    'preflights winzip aes extra field provenance before raw package instantiation' => static function (TestRunner $t) use ($buildZipPackage, $buildWinZipAesExtra): void {
        $centralAes = $buildWinZipAesExtra(2, 'AE', 3, 8);
        $localAes = $buildWinZipAesExtra(1, 'AE', 1, 0);
        $truncatedAes = pack('vv', 0x9901, 3) . 'abc';
        $zip = $buildZipPackage([
            [
                'name' => 'word/media/aes-deflated.bin',
                'data' => 'well formed AES metadata stays blocked',
                'method' => 0,
                'centralExtra' => $centralAes,
                'localExtra' => $centralAes,
            ],
            [
                'name' => 'word/media/aes-mismatch.bin',
                'data' => 'mismatched AES metadata stays blocked',
                'method' => 0,
                'centralExtra' => $centralAes,
                'localExtra' => $localAes,
            ],
            [
                'name' => 'word/media/aes-truncated.bin',
                'data' => 'truncated AES metadata stays blocked',
                'method' => 0,
                'centralExtra' => $truncatedAes,
                'localExtra' => '',
            ],
        ]);

        $summary = ZipPackage::encryptionPolicyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 512, 20.0, 512);

        $t->same(3, $summary['entryCount']);
        $t->same(3, $summary['encryptedEntryCount']);
        $t->same(3, $summary['winZipAesEntryCount']);
        $t->same(3, $summary['centralWinZipAesEntryCount']);
        $t->same(2, $summary['localWinZipAesEntryCount']);
        $t->same(1, $summary['mismatchedWinZipAesEntryCount']);
        $t->same(1, $summary['malformedWinZipAesEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'encrypted-zip-entries',
            'winzip-aes-extra-field-mismatch',
            'malformed-winzip-aes-extra-fields',
        ], $summary['issues']);

        $wellFormed = $summary['encryptedEntries'][0];
        $t->same('word/media/aes-deflated.bin', $wellFormed['name']);
        $t->same(true, $wellFormed['hasWinZipAesExtraField']);
        $t->same(true, $wellFormed['hasCentralWinZipAesExtraField']);
        $t->same(true, $wellFormed['hasLocalWinZipAesExtraField']);
        $t->same(true, $wellFormed['winZipAesExtraFieldMatches']);
        $t->same(false, $wellFormed['hasMalformedWinZipAesExtraField']);
        $t->same(['winzip-aes'], $wellFormed['encryptionTypes']);
        $t->same([
            'zip-encrypted-entry-not-extracted',
            'zip-winzip-aes-extra-field',
            'zip-central-winzip-aes-extra-field',
            'zip-local-winzip-aes-extra-field',
        ], $wellFormed['diagnostics']);
        $t->same(7, $wellFormed['centralWinZipAes']['dataLength']);
        $t->same('02004145030800', $wellFormed['centralWinZipAes']['dataHex']);
        $t->same(2, $wellFormed['centralWinZipAes']['vendorVersion']);
        $t->same('AE-2', $wellFormed['centralWinZipAes']['vendorVersionName']);
        $t->same('AE', $wellFormed['centralWinZipAes']['vendorId']);
        $t->same('4145', $wellFormed['centralWinZipAes']['vendorIdHex']);
        $t->same(3, $wellFormed['centralWinZipAes']['strength']);
        $t->same('aes-256', $wellFormed['centralWinZipAes']['strengthName']);
        $t->same(8, $wellFormed['centralWinZipAes']['actualCompressionMethod']);
        $t->same('deflated', $wellFormed['centralWinZipAes']['actualCompressionMethodName']);
        $t->same(0, $wellFormed['centralWinZipAes']['trailingByteCount']);
        $t->same(true, $wellFormed['centralWinZipAes']['isWellFormed']);
        $t->same([], $wellFormed['centralWinZipAes']['issues']);

        $mismatched = $summary['encryptedEntries'][1];
        $t->same('word/media/aes-mismatch.bin', $mismatched['name']);
        $t->same(false, $mismatched['winZipAesExtraFieldMatches']);
        $t->same(1, $mismatched['localWinZipAes']['vendorVersion']);
        $t->same('AE-1', $mismatched['localWinZipAes']['vendorVersionName']);
        $t->same(1, $mismatched['localWinZipAes']['strength']);
        $t->same('aes-128', $mismatched['localWinZipAes']['strengthName']);
        $t->same(0, $mismatched['localWinZipAes']['actualCompressionMethod']);
        $t->same('stored', $mismatched['localWinZipAes']['actualCompressionMethodName']);
        $t->contains('zip-winzip-aes-extra-field-mismatch', implode(',', $mismatched['diagnostics']));

        $truncated = $summary['encryptedEntries'][2];
        $t->same('word/media/aes-truncated.bin', $truncated['name']);
        $t->same(true, $truncated['hasMalformedWinZipAesExtraField']);
        $t->same(null, $truncated['localWinZipAes']);
        $t->same(3, $truncated['centralWinZipAes']['dataLength']);
        $t->same(false, $truncated['centralWinZipAes']['isWellFormed']);
        $t->same(['winzip-aes-extra-field-truncated'], $truncated['centralWinZipAes']['issues']);
        $t->contains('zip-winzip-aes-extra-field-malformed', implode(',', $truncated['diagnostics']));

        $t->same($summary, $rawStrict['encryption']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->contains('encrypted-zip-entries', implode(',', $rawStrict['diagnostics']));
        $t->contains('winzip-aes-extra-field-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->contains('malformed-winzip-aes-extra-fields', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'rejects unsupported zip general purpose flag bits before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $enhancedDeflateZip = $buildZipPackage([
            [
                'name' => 'word/media/enhanced-deflate.bin',
                'data' => 'enhanced deflate metadata should stay blocked',
                'method' => 8,
                'flags' => 0x0810,
            ],
        ]);
        $enhancedDeflatePackage = ZipPackage::fromString($enhancedDeflateZip);
        $t->same('enhanced deflate metadata should stay blocked', $enhancedDeflatePackage->read('word/media/enhanced-deflate.bin'));

        $rawFlagPreflight = ZipPackage::rawStrictImportPreflight($enhancedDeflateZip, 512, 20.0, 512);
        $t->same(0, $rawFlagPreflight['generalPurposeFlags']['unsupportedFlagEntryCount']);
        $t->same([], $rawFlagPreflight['generalPurposeFlags']['issues']);

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/patched-data.bin',
                'data' => 'compressed patched data should stay blocked',
                'method' => 8,
                'flags' => 0x0820,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reserved-flags.bin',
                'data' => 'reserved general-purpose flags should stay blocked',
                'method' => 0,
                'flags' => 0xc800,
            ],
        ])));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>supported descriptor and deflate option flags</w:p></w:document>',
                'method' => 8,
                'flags' => 0x080e,
                'descriptor' => true,
            ],
        ]));

        $t->same(0x080e, $package->entry('word/document.xml')->generalPurposeFlags);
        $t->same(
            '<w:document><w:p>supported descriptor and deflate option flags</w:p></w:document>',
            $package->read('/word/document.xml')
        );
    },

    'rejects deflate option flags on non-deflated zip entries before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $storedMaximumZip = $buildZipPackage([
            [
                'name' => 'word/media/stored-maximum.bin',
                'data' => 'stored package media must not claim deflate maximum compression flags',
                'method' => 0,
                'flags' => 0x0802,
            ],
        ]);
        $storedMaximumPackage = ZipPackage::fromString($storedMaximumZip);
        $t->same('stored package media must not claim deflate maximum compression flags', $storedMaximumPackage->read('word/media/stored-maximum.bin'));

        $rawDeflateOptionPreflight = ZipPackage::rawStrictImportPreflight($storedMaximumZip, 512, 20.0, 512);
        $t->same(false, $rawDeflateOptionPreflight['isValid']);
        $t->same(true, $rawDeflateOptionPreflight['canInstantiate']);
        $t->same(1, $rawDeflateOptionPreflight['generalPurposeFlags']['deflateOptionMethodMismatchEntryCount']);
        $t->same(0x0002, $rawDeflateOptionPreflight['generalPurposeFlags']['deflateOptionMethodMismatchEntries'][0]['deflateOptionFlags']);
        $t->same('deflate-maximum-compression', $rawDeflateOptionPreflight['generalPurposeFlags']['deflateOptionMethodMismatchEntries'][0]['deflateOptionName']);
        $t->same([
            'deflate-option-flags',
            'deflate-option-flags-without-deflate',
        ], $rawDeflateOptionPreflight['generalPurposeFlags']['deflateOptionMethodMismatchEntries'][0]['issues']);
        $t->contains('deflate-option-flag-entries', implode(',', $rawDeflateOptionPreflight['diagnostics']));
        $t->contains('deflate-option-flags-without-deflate', implode(',', $rawDeflateOptionPreflight['diagnostics']));

        $directoryFlagZip = $buildZipPackage([
            [
                'name' => '_rels/',
                'data' => '',
                'method' => 0,
                'flags' => 0x0802,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>directory flag tolerance</w:p></w:document>',
                'method' => 8,
            ],
        ]);
        $directoryFlagPackage = ZipPackage::fromString($directoryFlagZip);
        $directoryFlagPreflight = ZipPackage::rawStrictImportPreflight($directoryFlagZip, 512, 20.0, 512);

        $t->same(true, $directoryFlagPackage->entry('_rels/')->isDirectory());
        $t->same(0x0802, $directoryFlagPackage->entry('_rels/')->generalPurposeFlags);
        $t->same(0, $directoryFlagPreflight['generalPurposeFlags']['deflateOptionMethodMismatchEntryCount']);
        $t->same(
            '<w:document><w:p>directory flag tolerance</w:p></w:document>',
            $directoryFlagPackage->read('word/document.xml')
        );

        $storedFastPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/stored-fast.bin',
                'data' => 'stored package media must not claim deflate fast compression flags',
                'method' => 0,
                'flags' => 0x0804,
            ],
        ]));
        $t->same('stored package media must not claim deflate fast compression flags', $storedFastPackage->read('word/media/stored-fast.bin'));

        $storedSuperfastPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/stored-superfast.bin',
                'data' => 'stored package media must not claim deflate superfast compression flags',
                'method' => 0,
                'flags' => 0x0806,
            ],
        ]));
        $t->same('stored package media must not claim deflate superfast compression flags', $storedSuperfastPackage->read('word/media/stored-superfast.bin'));

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>deflate option flags stay valid on deflated entries</w:p></w:document>',
                'method' => 8,
                'flags' => 0x0806,
            ],
            [
                'name' => 'word/media/unsupported-method.bin',
                'data' => 'unsupported methods without deflate option flags remain preflightable',
                'method' => 12,
            ],
        ]));

        $t->same(0x0806, $package->entry('word/document.xml')->generalPurposeFlags);
        $t->same(
            '<w:document><w:p>deflate option flags stay valid on deflated entries</w:p></w:document>',
            $package->read('/word/document.xml')
        );
        $t->same(12, $package->entry('word/media/unsupported-method.bin')->compressionMethod);
    },

    'preflights supported zip general purpose flags before strict package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:p>descriptor flag review</w:p></w:document>';
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'flags' => 0x080e,
                'descriptor' => true,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "legacy CP437 metadata remains readable\n",
                'method' => 0,
                'flags' => 0,
            ],
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 8,
                'flags' => 0x0800,
            ],
        ]));
        $summary = $package->generalPurposeFlagPreflight();

        $t->same(3, $summary['entryCount']);
        $t->same(3, $summary['supportedEntryCount']);
        $t->same(0, $summary['unsupportedFlagEntryCount']);
        $t->same(2, $summary['utf8NameEntryCount']);
        $t->same(1, $summary['dataDescriptorEntryCount']);
        $t->same(1, $summary['deflateOptionEntryCount']);
        $t->same(1, $summary['strictReviewEntryCount']);
        $t->same('word/document.xml', $summary['strictReviewEntries'][0]['name']);
        $t->same(0x080e, $summary['entries'][0]['generalPurposeFlags']);
        $t->same(['deflate-super-fast', 'data-descriptor', 'utf-8-names'], $summary['entries'][0]['flagNames']);
        $t->same(true, $summary['entries'][0]['usesDataDescriptor']);
        $t->same(0x0006, $summary['entries'][0]['deflateOptionFlags']);
        $t->same('deflate-super-fast', $summary['entries'][0]['deflateOptionName']);
        $t->same(true, $summary['entries'][0]['requiresStrictReview']);
        $t->same(['data-descriptor-entry', 'deflate-option-flags'], $summary['entries'][0]['issues']);
        $t->same(false, $summary['entries'][1]['usesUtf8Names']);
        $t->same('cp437', $package->entry('/word/media/review.txt')->nameEncoding);
        $t->same(null, $summary['entries'][1]['deflateOptionName']);
        $t->same([], $summary['entries'][1]['issues']);
        $t->same(['utf-8-names'], $summary['entries'][2]['flagNames']);

        $strictSummary = $package->strictImportPreflight(2048, 100.0, 2048);
        $t->same(false, $strictSummary['isValid']);
        $t->contains('data-descriptor-entries', implode(',', $strictSummary['diagnostics']));
        $t->contains('deflate-option-flag-entries', implode(',', $strictSummary['diagnostics']));
        $t->same(1, $strictSummary['generalPurposeFlags']['strictReviewEntryCount']);
        $t->same($documentXml, $package->read('/word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertNoStrictGeneralPurposeFlagReviewEntries());
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertStrictImportable(2048, 100.0, 2048));

        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>ordinary flags</w:p></w:document>',
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "ordinary media bytes\n",
                'compressionMethod' => 0,
            ],
        ]);
        $safeSummary = $safePackage->assertNoStrictGeneralPurposeFlagReviewEntries();
        $t->same(2, $safeSummary['entryCount']);
        $t->same(0, $safeSummary['dataDescriptorEntryCount']);
        $t->same(0, $safeSummary['deflateOptionEntryCount']);
        $t->same(0, $safeSummary['strictReviewEntryCount']);
        $t->same([], $safeSummary['strictReviewEntries']);
        $t->same(true, $safePackage->strictImportPreflight(2048, 100.0, 2048)['isValid']);
    },

    'rejects crc and local file header names before exposing package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document>changed</w:document>',
                'method' => 8,
                'centralCrc' => 0,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/other.xml',
                'data' => '<w:document/>',
                'method' => 0,
            ],
        ])));
    },

    'rejects local header and data descriptor integrity mismatches' => static function (TestRunner $t) use ($buildZipPackage): void {
        $validDescriptorZip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments><w:comment>descriptor metadata preflight</w:comment></w:comments>',
                'method' => 8,
                'descriptor' => true,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>descriptor follower</w:p></w:document>',
                'method' => 0,
            ],
        ]);
        $validDescriptorSummary = ZipPackage::dataDescriptorIntegrityPreflight($validDescriptorZip);

        $t->same(2, $validDescriptorSummary['entryCount']);
        $t->same(1, $validDescriptorSummary['descriptorEntryCount']);
        $t->same(1, $validDescriptorSummary['matchedDescriptorEntryCount']);
        $t->same(0, $validDescriptorSummary['mismatchedDescriptorEntryCount']);
        $t->same(0, $validDescriptorSummary['zip64SizedDescriptorEntryCount']);
        $t->same(true, $validDescriptorSummary['isSupportedByBoundedReader']);
        $t->same([], $validDescriptorSummary['issues']);
        $t->same([], $validDescriptorSummary['mismatchedDescriptorEntries']);
        $t->same('word/comments.xml', $validDescriptorSummary['descriptorEntries'][0]['name']);
        $t->same(true, $validDescriptorSummary['descriptorEntries'][0]['usesDataDescriptor']);
        $t->same(true, $validDescriptorSummary['descriptorEntries'][0]['hasSignature']);
        $t->same(true, $validDescriptorSummary['descriptorEntries'][0]['hasZeroLocalHeaderPlaceholders']);
        $t->same(true, $validDescriptorSummary['descriptorEntries'][0]['descriptorValuesMatchCentral']);
        $t->same(false, $validDescriptorSummary['descriptorEntries'][0]['usesZip64SizedDescriptor']);
        $t->same([], $validDescriptorSummary['descriptorEntries'][0]['issues']);
        $t->same('word/document.xml', $validDescriptorSummary['entries'][1]['name']);
        $t->same(false, $validDescriptorSummary['entries'][1]['usesDataDescriptor']);
        $t->same(null, $validDescriptorSummary['entries'][1]['descriptorOffset']);

        $descriptorCrcMismatchZip = $buildZipPackage([
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes/>',
                'method' => 8,
                'descriptor' => true,
                'descriptorCrc' => 0,
            ],
        ]);
        $descriptorCrcMismatchSummary = ZipPackage::dataDescriptorIntegrityPreflight($descriptorCrcMismatchZip);

        $t->same(1, $descriptorCrcMismatchSummary['entryCount']);
        $t->same(1, $descriptorCrcMismatchSummary['descriptorEntryCount']);
        $t->same(0, $descriptorCrcMismatchSummary['matchedDescriptorEntryCount']);
        $t->same(1, $descriptorCrcMismatchSummary['mismatchedDescriptorEntryCount']);
        $t->same(false, $descriptorCrcMismatchSummary['isSupportedByBoundedReader']);
        $t->same(['data-descriptor-crc32-mismatch'], $descriptorCrcMismatchSummary['issues']);
        $t->same('word/footnotes.xml', $descriptorCrcMismatchSummary['mismatchedDescriptorEntries'][0]['name']);
        $t->same(['data-descriptor-crc32-mismatch'], $descriptorCrcMismatchSummary['mismatchedDescriptorEntries'][0]['issues']);
        $t->same(0, $descriptorCrcMismatchSummary['mismatchedDescriptorEntries'][0]['crc32']);
        $t->same('00000000', $descriptorCrcMismatchSummary['mismatchedDescriptorEntries'][0]['crc32Hex']);
        $t->same($descriptorCrcMismatchSummary['entries'][0], $descriptorCrcMismatchSummary['mismatchedDescriptorEntries'][0]);

        $descriptorSizeMismatchZip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 8,
                'descriptor' => true,
                'descriptorUncompressedSize' => 999,
            ],
        ]);
        $descriptorSizeMismatchSummary = ZipPackage::dataDescriptorIntegrityPreflight($descriptorSizeMismatchZip);

        $t->same(1, $descriptorSizeMismatchSummary['mismatchedDescriptorEntryCount']);
        $t->same(['data-descriptor-size-mismatch'], $descriptorSizeMismatchSummary['issues']);
        $t->same(['data-descriptor-size-mismatch'], $descriptorSizeMismatchSummary['mismatchedDescriptorEntries'][0]['issues']);
        $t->same(strlen(gzdeflate('<w:comments/>')), $descriptorSizeMismatchSummary['mismatchedDescriptorEntries'][0]['compressedSize']);
        $t->same(999, $descriptorSizeMismatchSummary['mismatchedDescriptorEntries'][0]['uncompressedSize']);
        $t->same(strlen(gzdeflate('<w:comments/>')), $descriptorSizeMismatchSummary['mismatchedDescriptorEntries'][0]['centralCompressedSize']);
        $t->same(strlen('<w:comments/>'), $descriptorSizeMismatchSummary['mismatchedDescriptorEntries'][0]['centralUncompressedSize']);

        $descriptorPlaceholderSummary = ZipPackage::dataDescriptorIntegrityPreflight($buildZipPackage([
            [
                'name' => 'word/endnotes.xml',
                'data' => '<w:endnotes/>',
                'method' => 8,
                'descriptor' => true,
                'localCrc' => 1,
            ],
        ]));
        $t->same(['local-header-data-descriptor-placeholders-not-zero'], $descriptorPlaceholderSummary['issues']);
        $t->same(false, $descriptorPlaceholderSummary['descriptorEntries'][0]['hasZeroLocalHeaderPlaceholders']);
        $t->same(['local-header-data-descriptor-placeholders-not-zero'], $descriptorPlaceholderSummary['descriptorEntries'][0]['issues']);

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document>local crc mismatch</w:document>',
                'method' => 8,
                'localCrc' => 0,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/styles.xml',
                'data' => '<w:styles/>',
                'method' => 0,
                'localCompressedSize' => 1,
            ],
        ])));
        $timestampMismatchZip = $buildZipPackage([
            [
                'name' => 'word/settings.xml',
                'data' => '<w:settings/>',
                'method' => 0,
                'modifiedTime' => 19400,
                'modifiedDate' => 23747,
                'localModifiedTime' => 19401,
            ],
        ]);
        $timestampMismatchSummary = ZipPackage::localHeaderMetadataPreflight($timestampMismatchZip);
        $t->same(['local-header-modification-time-mismatch'], $timestampMismatchSummary['issues']);
        $t->same('<w:settings/>', ZipPackage::fromString($timestampMismatchZip)->read('word/settings.xml'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 8,
                'descriptor' => true,
                'descriptorUncompressedSize' => 999,
            ],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/footnotes.xml',
                'data' => '<w:footnotes/>',
                'method' => 8,
                'descriptor' => true,
                'descriptorCrc' => 0,
            ],
        ])));
    },

    'preflights data descriptor boundary slack before package import' => static function (TestRunner $t) use ($buildZipPackage): void {
        $commentsXml = '<w:comments><w:comment>descriptor slack review</w:comment></w:comments>';
        $slack = 'hidden-descriptor-tail';
        $zip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
                'localSlack' => $slack,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>descriptor follower</w:p></w:document>',
                'method' => 0,
            ],
        ]);

        $summary = ZipPackage::dataDescriptorIntegrityPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 20.0, 1024);
        $descriptorEntry = $summary['descriptorEntries'][0];

        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['descriptorEntryCount']);
        $t->same(0, $summary['matchedDescriptorEntryCount']);
        $t->same(1, $summary['mismatchedDescriptorEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['data-descriptor-length-mismatch'], $summary['issues']);
        $t->same('word/comments.xml', $descriptorEntry['name']);
        $t->same(true, $descriptorEntry['hasSignature']);
        $t->same(true, $descriptorEntry['descriptorValuesMatchCentral']);
        $t->same(16, $descriptorEntry['descriptorLength']);
        $t->same(16 + strlen($slack), $descriptorEntry['descriptorSpan']);
        $t->same($descriptorEntry['descriptorOffset'] + 16, $descriptorEntry['descriptorEnd']);
        $t->same($descriptorEntry['descriptorEnd'] + strlen($slack), $descriptorEntry['nextOffset']);
        $t->same(strlen($slack), $descriptorEntry['surplusDescriptorBytes']);
        $t->same(0, $descriptorEntry['truncatedDescriptorBytes']);
        $t->same(['data-descriptor-length-mismatch'], $descriptorEntry['issues']);
        $t->same($descriptorEntry, $summary['mismatchedDescriptorEntries'][0]);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same($summary, $rawStrict['dataDescriptors']);
        $t->contains('data-descriptor-length-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights missing data descriptors before the next local header record' => static function (TestRunner $t) use ($buildZipPackage): void {
        $commentsXml = '<w:comments><w:comment>descriptor swallowed by central size</w:comment></w:comments>';
        $compressedSize = strlen(gzdeflate($commentsXml));
        $zip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
                'centralCompressedSize' => $compressedSize + 16,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>descriptor follower</w:p></w:document>',
                'method' => 0,
            ],
        ]);

        $summary = ZipPackage::dataDescriptorIntegrityPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 20.0, 1024);
        $descriptorEntry = $summary['descriptorEntries'][0];

        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['descriptorEntryCount']);
        $t->same(0, $summary['matchedDescriptorEntryCount']);
        $t->same(1, $summary['mismatchedDescriptorEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'data-descriptor-truncated',
            'data-descriptor-missing-before-next-local-header',
        ], $summary['issues']);
        $t->same('word/comments.xml', $descriptorEntry['name']);
        $t->same(false, $descriptorEntry['hasSignature']);
        $t->same(null, $descriptorEntry['descriptorLength']);
        $t->same(0, $descriptorEntry['descriptorSpan']);
        $t->same(0, $descriptorEntry['descriptorBytesBeforeNextRecord']);
        $t->same('local-header', $descriptorEntry['descriptorNextRecordKind']);
        $t->same(true, $descriptorEntry['descriptorStartsWithRecordSignature']);
        $t->same('504b0304', $descriptorEntry['descriptorStartSignatureHex']);
        $t->same('local-file-header', $descriptorEntry['descriptorStartSignatureName']);
        $t->same(null, $descriptorEntry['descriptorValuesMatchCentral']);
        $t->same([
            'data-descriptor-truncated',
            'data-descriptor-missing-before-next-local-header',
        ], $descriptorEntry['issues']);
        $t->same($descriptorEntry, $summary['mismatchedDescriptorEntries'][0]);

        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same($summary, $rawStrict['dataDescriptors']);
        $t->contains('data-descriptor-missing-before-next-local-header', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'rejects local header flags and methods before exposing package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $localFlagMismatchZip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => '<w:comments/>',
                'method' => 8,
                'descriptor' => true,
                'localFlags' => 0x0800,
            ],
        ]);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($localFlagMismatchZip));

        $rawLocalFlagMismatch = ZipPackage::rawStrictImportPreflight($localFlagMismatchZip, 512, 20.0, 512);
        $t->same(false, $rawLocalFlagMismatch['isValid']);
        $t->same(false, $rawLocalFlagMismatch['canInstantiate']);
        $t->same(1, $rawLocalFlagMismatch['generalPurposeFlags']['localHeaderFlagMismatchEntryCount']);
        $t->same(false, $rawLocalFlagMismatch['generalPurposeFlags']['mismatchedEntries'][0]['generalPurposeFlagsMatchLocalHeader']);
        $t->same(0x0808, $rawLocalFlagMismatch['generalPurposeFlags']['mismatchedEntries'][0]['generalPurposeFlags']);
        $t->same(0x0800, $rawLocalFlagMismatch['generalPurposeFlags']['mismatchedEntries'][0]['localGeneralPurposeFlags']);
        $t->contains('general-purpose-flag-issues', implode(',', $rawLocalFlagMismatch['diagnostics']));
        $t->contains('local-header-flags-mismatch', implode(',', $rawLocalFlagMismatch['diagnostics']));
        $t->contains('data-descriptor-entries', implode(',', $rawLocalFlagMismatch['diagnostics']));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>method mismatch</w:p></w:document>',
                'method' => 8,
                'localMethod' => 0,
            ],
        ])));
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>matching local header</w:p></w:document>',
                'method' => 8,
            ],
        ]));
        $t->same(['word/document.xml'], $package->names());
        $t->same(['word/document.xml'], $package->localNames());
        $t->same(8, $package->entry('word/document.xml')->compressionMethod);
        $t->same('<w:document><w:p>matching local header</w:p></w:document>', $package->read('word/document.xml'));
    },

    'rejects unsupported compression methods and malformed package endings' => static function (TestRunner $t) use ($buildZipPackage): void {
        $unsupported = ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'method' => 12],
        ]));

        $t->same(['word/document.xml'], $unsupported->names());
        $t->throws(\RuntimeException::class, static fn (): string => $unsupported->read('word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString('not a zip package'));
    },

    'preflights unsupported compression methods before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>compression preflight</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review-bzip2.bin',
                'data' => 'Unsupported method bytes stay blocked before media handoff',
                'method' => 12,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $summary = $package->compressionMethodPreflight();

        $t->same(3, $summary['entryCount']);
        $t->same(2, $summary['supportedEntryCount']);
        $t->same(1, $summary['unsupportedCompressionMethodCount']);
        $t->same(1, $summary['storedEntryCount']);
        $t->same(1, $summary['deflatedEntryCount']);
        $t->same('word/media/review-bzip2.bin', $summary['unsupportedEntries'][0]['name']);
        $t->same(12, $summary['unsupportedEntries'][0]['compressionMethod']);
        $t->same(false, $summary['unsupportedEntries'][0]['isDirectory']);
        $t->same('word/media/review-bzip2.bin', $summary['entries'][1]['name']);
        $t->same('unsupported', $summary['entries'][1]['compressionMethodName']);
        $t->same(false, $summary['entries'][1]['isSupported']);
        $t->same('stored', $summary['entries'][2]['compressionMethodName']);
        $t->same(true, $summary['entries'][2]['isSupported']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertSupportedCompressionMethods());

        $safePackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>supported methods</w:p></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => 'stored media note',
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $safeSummary = $safePackage->assertSupportedCompressionMethods();

        $t->same(3, $safeSummary['entryCount']);
        $t->same(3, $safeSummary['supportedEntryCount']);
        $t->same(0, $safeSummary['unsupportedCompressionMethodCount']);
        $t->same([], $safeSummary['unsupportedEntries']);
    },

    'preflights compression method byte buckets before shared package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:p>bucketed compression accounting</w:p></w:document>';
        $storedNote = "stored package reviewer note\n";
        $unsupportedBytes = 'bzip2-like package bytes remain blocked';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $storedNote,
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'word/media/review-bzip2.bin',
                'data' => $unsupportedBytes,
                'method' => 12,
            ],
        ]);
        $package = ZipPackage::fromString($zip);
        $summary = $package->compressionMethodPreflight();
        $deflatedCompressed = strlen(gzdeflate($documentXml));
        $storedCompressed = strlen($storedNote);
        $unsupportedCompressed = strlen($unsupportedBytes);
        $expectedBuckets = [
            [
                'compressionMethod' => 0,
                'compressionMethodName' => 'stored',
                'entryCount' => 2,
                'compressedBytes' => $storedCompressed,
                'uncompressedBytes' => strlen($storedNote),
                'isSupported' => true,
            ],
            [
                'compressionMethod' => 8,
                'compressionMethodName' => 'deflated',
                'entryCount' => 1,
                'compressedBytes' => $deflatedCompressed,
                'uncompressedBytes' => strlen($documentXml),
                'isSupported' => true,
            ],
            [
                'compressionMethod' => 12,
                'compressionMethodName' => 'unsupported',
                'entryCount' => 1,
                'compressedBytes' => $unsupportedCompressed,
                'uncompressedBytes' => strlen($unsupportedBytes),
                'isSupported' => false,
            ],
        ];

        $t->same(4, $summary['entryCount']);
        $t->same(3, $summary['supportedEntryCount']);
        $t->same(2, $summary['storedEntryCount']);
        $t->same(1, $summary['deflatedEntryCount']);
        $t->same(1, $summary['unsupportedCompressionMethodCount']);
        $t->same($storedCompressed, $summary['storedCompressedBytes']);
        $t->same(strlen($storedNote), $summary['storedUncompressedBytes']);
        $t->same($deflatedCompressed, $summary['deflatedCompressedBytes']);
        $t->same(strlen($documentXml), $summary['deflatedUncompressedBytes']);
        $t->same($unsupportedCompressed, $summary['unsupportedCompressedBytes']);
        $t->same(strlen($unsupportedBytes), $summary['unsupportedUncompressedBytes']);
        $t->same($expectedBuckets, $summary['methodBuckets']);

        $rawPolicy = ZipPackage::compressionMethodPolicyPreflight($zip);
        $t->same($summary['storedCompressedBytes'], $rawPolicy['storedCompressedBytes']);
        $t->same($summary['storedUncompressedBytes'], $rawPolicy['storedUncompressedBytes']);
        $t->same($summary['deflatedCompressedBytes'], $rawPolicy['deflatedCompressedBytes']);
        $t->same($summary['deflatedUncompressedBytes'], $rawPolicy['deflatedUncompressedBytes']);
        $t->same($summary['unsupportedCompressedBytes'], $rawPolicy['unsupportedCompressedBytes']);
        $t->same($summary['unsupportedUncompressedBytes'], $rawPolicy['unsupportedUncompressedBytes']);
        $t->same($expectedBuckets, $rawPolicy['methodBuckets']);
        $t->same(false, $rawPolicy['isSupportedByBoundedReader']);

        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 256, 20.0, 256);
        $t->same($expectedBuckets, $rawStrict['compressionMethods']['methodBuckets']);
        $t->same($expectedBuckets, $rawStrict['strictImport']['compressionMethods']['methodBuckets']);
        $t->contains('unsupported-compression-methods', implode(',', $rawStrict['diagnostics']));
    },

    'aggregates strict zip import preflight policy before package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>strict package import</w:p></w:document>',
                'modifiedAt' => 1780479017,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'externalAttributes' => 0x41ed0000,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "review media bytes\n",
                'compressionMethod' => 0,
                'externalAttributes' => 0x81a40000,
            ],
        ]);
        $safeSummary = $safePackage->strictImportPreflight(512, 20.0, 512);

        $t->same(true, $safeSummary['isValid']);
        $t->same([], $safeSummary['diagnostics']);
        $t->same(3, $safeSummary['entryCount']);
        $t->same(512, $safeSummary['maxTotalUncompressedBytes']);
        $t->same(20.0, $safeSummary['maxExpansionRatio']);
        $t->same(512, $safeSummary['maxEntryUncompressedBytes']);
        $t->same(3, $safeSummary['compressionMethods']['supportedEntryCount']);
        $t->same(0, $safeSummary['comments']['entryCommentCount']);
        $t->same(0, $safeSummary['extraFields']['duplicateExtraFieldEntryCount']);
        $t->same(0, $safeSummary['pathHierarchy']['collisionEntryCount']);
        $t->same(0, $safeSummary['permissions']['executableFileCount']);
        $t->same(0, $safeSummary['creatorHostSystems']['unknownHostSystemEntryCount']);
        $t->same(3, $safeSummary['readIntegrity']['readableEntryCount']);
        $t->same(0, $safeSummary['readIntegrity']['failedEntryCount']);
        $t->same($safeSummary, $safePackage->assertStrictImportable(512, 20.0, 512));
        $t->same("review media bytes\n", $safePackage->read('/word/media/review.txt'));

        $duplicateExtra = pack('vva*', 0xbeef, strlen('first'), 'first')
            . pack('vva*', 0xbeef, strlen('second'), 'second');
        $badPackage = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => str_repeat('A', 512),
                'method' => 8,
                'comment' => 'document comment must be reviewed',
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/unsupported.bin',
                'data' => 'unsupported package media',
                'method' => 12,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media',
                'data' => 'file shadows media directory',
                'method' => 0,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
                'externalAttributes' => 0x41ed0000,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "PNG reviewer attachment placeholder\n",
                'method' => 0,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/reviewer-script.sh',
                'data' => "#!/bin/sh\necho review\n",
                'method' => 0,
                'externalAttributes' => 0x81ed0000,
            ],
            [
                'name' => 'word/media/unknown-host.bin',
                'data' => 'unknown creator host metadata',
                'method' => 0,
                'versionMadeBy' => 0x3f14,
                'externalAttributes' => 0,
            ],
            [
                'name' => 'word/media/duplicate-extra.bin',
                'data' => 'duplicate extra field metadata',
                'method' => 0,
                'localExtra' => $duplicateExtra,
                'centralExtra' => $duplicateExtra,
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/split-extra.bin',
                'data' => 'split extra field metadata',
                'method' => 0,
                'localExtra' => pack('vva*', 0xcafe, strlen('local'), 'local'),
                'centralExtra' => pack('vva*', 0xcafe, strlen('central'), 'central'),
                'externalAttributes' => 0x81a40000,
            ],
        ], 'package comment must be reviewed'));
        $badSummary = $badPackage->strictImportPreflight(256, 2.0, 256);

        $t->same(false, $badSummary['isValid']);
        $t->same([
            'package-or-entry-comments',
            'unsupported-compression-methods',
            'duplicate-extra-field-ids',
            'central-local-extra-field-value-mismatch',
            'path-hierarchy-collisions',
            'executable-file-entries',
            'unknown-creator-host-systems',
            'total-uncompressed-size-exceeds-limit',
            'expansion-ratio-exceeds-limit',
            'unreadable-entries',
        ], $badSummary['diagnostics']);
        $t->same(9, $badSummary['entryCount']);
        $t->same(1, $badSummary['compressionMethods']['unsupportedCompressionMethodCount']);
        $t->same(1, $badSummary['extraFields']['duplicateExtraFieldEntryCount']);
        $t->same(1, $badSummary['extraFields']['mismatchedExtraFieldValueEntryCount']);
        $t->same(8, $badSummary['pathHierarchy']['collisionEntryCount']);
        $t->same(1, $badSummary['permissions']['executableFileCount']);
        $t->same(1, $badSummary['creatorHostSystems']['unknownHostSystemEntryCount']);
        $t->same(true, $badSummary['size']['uncompressedBytes'] > 256);
        $t->same(true, ($badSummary['size']['expansionRatio'] ?? 0.0) > 2.0);
        $t->same(2, $badSummary['readIntegrity']['failedEntryCount']);
        $t->same('word/document.xml', $badSummary['readIntegrity']['failedEntries'][0]['name']);
        $t->same('word/media/unsupported.bin', $badSummary['readIntegrity']['failedEntries'][1]['name']);
        $t->throws(\RuntimeException::class, static fn (): array => $badPackage->assertStrictImportable(256, 2.0, 256));
    },

    'rejects empty zip package bytes before strict document import' => static function (TestRunner $t): void {
        $emptyPackage = ZipPackage::fromParts([]);
        $contentPresence = $emptyPackage->contentPresencePreflight();
        $strict = $emptyPackage->strictImportPreflight(512, 20.0, 512);
        $raw = ZipPackage::rawStrictImportPreflight($emptyPackage->bytes(), 512, 20.0, 512);

        $t->same([], $emptyPackage->names());
        $t->same(0, $contentPresence['entryCount']);
        $t->same(false, $contentPresence['hasEntries']);
        $t->same(false, $contentPresence['isSupportedByBoundedReader']);
        $t->same(['empty-package'], $contentPresence['issues']);

        $t->same(false, $strict['isValid']);
        $t->same(0, $strict['entryCount']);
        $t->same(['empty-package'], $strict['diagnostics']);
        $t->same($contentPresence, $strict['contentPresence']);
        $t->same(0, $strict['centralDirectoryInventory']['entryCount']);
        $t->same(0, $strict['readIntegrity']['readableEntryCount']);
        $t->throws(\RuntimeException::class, static fn (): array => $emptyPackage->assertStrictImportable(512, 20.0, 512));

        $t->same(false, $raw['isValid']);
        $t->same(true, $raw['canInstantiate']);
        $t->same(null, $raw['instantiationError']);
        $t->same(0, $raw['entryCount']);
        $t->same(['empty-package'], $raw['diagnostics']);
        $t->same([], $raw['preflightErrors']);
        $t->same(false, $raw['strictImport']['isValid']);
        $t->same($contentPresence, $raw['strictImport']['contentPresence']);
    },

    'preflights raw zip package bytes before strict import instantiation' => static function (TestRunner $t) use (
        $buildZipPackage,
        $rewriteEndOfCentralDirectory,
        $buildZip64EndOfCentralDirectoryPackage,
        $buildTraditionalEncryptedPackage
    ): void {
        $safePackage = ZipPackage::fromParts([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw strict package import</w:p></w:document>',
                'externalAttributes' => 0x81a40000,
            ],
            [
                'name' => 'word/media/',
                'externalAttributes' => 0x41ed0000,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "review media bytes\n",
                'compressionMethod' => 0,
                'externalAttributes' => 0x81a40000,
            ],
        ]);
        $safeRaw = ZipPackage::rawStrictImportPreflight($safePackage->bytes(), 512, 20.0, 512);

        $t->same(true, $safeRaw['isValid']);
        $t->same(true, $safeRaw['canInstantiate']);
        $t->same(null, $safeRaw['instantiationError']);
        $t->same([], $safeRaw['diagnostics']);
        $t->same([], $safeRaw['preflightErrors']);
        $t->same(3, $safeRaw['entryCount']);
        $t->same(512, $safeRaw['maxTotalUncompressedBytes']);
        $t->same(20.0, $safeRaw['maxExpansionRatio']);
        $t->same(512, $safeRaw['maxEntryUncompressedBytes']);
        $t->same(true, $safeRaw['archive']['isArchiveLayoutSupported']);
        $t->same(3, $safeRaw['centralDirectoryInventory']['entryCount']);
        $t->same(0, $safeRaw['localHeaderNames']['mismatchedEntryCount']);
        $t->same(true, $safeRaw['localHeaderNames']['isSupportedByBoundedReader']);
        $t->same(0, $safeRaw['localHeaderMetadata']['mismatchedEntryCount']);
        $t->same(true, $safeRaw['localHeaderMetadata']['isSupportedByBoundedReader']);
        $t->same(3, $safeRaw['localHeaderSpans']['entryCount']);
        $t->same(0, $safeRaw['localHeaderSpans']['issueEntryCount']);
        $t->same(true, $safeRaw['localHeaderSpans']['isSupportedByBoundedReader']);
        $t->same(true, $safeRaw['compressionMethods']['isSupportedByBoundedReader']);
        $t->same(false, $safeRaw['encryption']['hasEncryptedEntries']);
        $t->same(0, $safeRaw['archiveExtraDataRecords']['archiveExtraDataRecordCount']);
        $t->same(true, $safeRaw['extraFieldStructure']['isSupportedByBoundedReader']);
        $t->same(0, $safeRaw['extraFieldStructure']['issueEntryCount']);
        $t->same(true, $safeRaw['extraFields']['isSupportedByBoundedReader']);
        $t->same(0, $safeRaw['extraFields']['duplicateExtraFieldEntryCount']);
        $t->same(true, $safeRaw['centralDirectoryPathHierarchy']['isSupportedByBoundedReader']);
        $t->same(0, $safeRaw['centralDirectoryPathHierarchy']['collisionEntryCount']);
        $t->same(0, $safeRaw['zip64ExtraFields']['zip64ExtraFieldEntryCount']);
        $t->same(0, $safeRaw['dataDescriptors']['mismatchedDescriptorEntryCount']);
        $t->same(true, $safeRaw['strictImport']['isValid']);

        $unsupportedZip = $buildZipPackage([
            [
                'name' => 'word/media/bzip2-review.bin',
                'data' => 'unsupported method bytes stay blocked',
                'method' => 12,
            ],
        ]);
        $unsupportedRaw = ZipPackage::rawStrictImportPreflight($unsupportedZip, 256, 20.0, 256);

        $t->same(false, $unsupportedRaw['isValid']);
        $t->same(true, $unsupportedRaw['canInstantiate']);
        $t->same(null, $unsupportedRaw['instantiationError']);
        $t->same(1, $unsupportedRaw['entryCount']);
        $t->same(1, $unsupportedRaw['compressionMethods']['unsupportedCompressionMethodCount']);
        $t->same(1, $unsupportedRaw['strictImport']['readIntegrity']['failedEntryCount']);
        $t->contains('unsupported-compression-methods', implode(',', $unsupportedRaw['diagnostics']));
        $t->contains('unreadable-entries', implode(',', $unsupportedRaw['diagnostics']));

        $localNameMismatchRaw = ZipPackage::rawStrictImportPreflight($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/other.xml',
                'data' => '<w:document><w:p>raw local name spoof</w:p></w:document>',
                'method' => 8,
            ],
        ]), 512, 20.0, 512);

        $t->same(false, $localNameMismatchRaw['isValid']);
        $t->same(false, $localNameMismatchRaw['canInstantiate']);
        $t->same(1, $localNameMismatchRaw['entryCount']);
        $t->same(1, $localNameMismatchRaw['localHeaderNames']['mismatchedEntryCount']);
        $t->same(false, $localNameMismatchRaw['localHeaderNames']['isSupportedByBoundedReader']);
        $t->same('word/document.xml', $localNameMismatchRaw['localHeaderNames']['mismatchedEntries'][0]['centralName']);
        $t->same('word/other.xml', $localNameMismatchRaw['localHeaderNames']['mismatchedEntries'][0]['localName']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $localNameMismatchRaw['localHeaderNames']['issues']);
        $t->same(0, $localNameMismatchRaw['localHeaderMetadata']['mismatchedEntryCount']);
        $t->contains('local-header-name-issues', implode(',', $localNameMismatchRaw['diagnostics']));
        $t->contains('local-header-name-mismatch', implode(',', $localNameMismatchRaw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $localNameMismatchRaw['diagnostics']));

        $localMetadataMismatchRaw = ZipPackage::rawStrictImportPreflight($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw local metadata spoof</w:p></w:document>',
                'method' => 8,
                'localMethod' => 0,
                'localCrc' => 0,
                'localCompressedSize' => 1,
                'localUncompressedSize' => 2,
            ],
        ]), 512, 20.0, 512);

        $t->same(false, $localMetadataMismatchRaw['isValid']);
        $t->same(false, $localMetadataMismatchRaw['canInstantiate']);
        $t->same(1, $localMetadataMismatchRaw['entryCount']);
        $t->same(0, $localMetadataMismatchRaw['localHeaderNames']['mismatchedEntryCount']);
        $t->same(1, $localMetadataMismatchRaw['localHeaderMetadata']['mismatchedEntryCount']);
        $t->same(false, $localMetadataMismatchRaw['localHeaderMetadata']['isSupportedByBoundedReader']);
        $t->same('word/document.xml', $localMetadataMismatchRaw['localHeaderMetadata']['mismatchedEntries'][0]['centralName']);
        $t->same([
            'local-header-compression-method-mismatch',
            'local-header-crc32-mismatch',
            'local-header-compressed-size-mismatch',
            'local-header-uncompressed-size-mismatch',
        ], $localMetadataMismatchRaw['localHeaderMetadata']['issues']);
        $t->contains('local-header-metadata-issues', implode(',', $localMetadataMismatchRaw['diagnostics']));
        $t->contains('local-header-compression-method-mismatch', implode(',', $localMetadataMismatchRaw['diagnostics']));
        $t->contains('local-header-crc32-mismatch', implode(',', $localMetadataMismatchRaw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $localMetadataMismatchRaw['diagnostics']));

        $splitZip = $rewriteEndOfCentralDirectory($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw split package</w:p></w:document>',
                'method' => 8,
            ],
        ]), [
            'diskNumber' => 1,
            'centralDirectoryDisk' => 1,
            'diskEntryCount' => 1,
        ]);
        $splitRaw = ZipPackage::rawStrictImportPreflight($splitZip, 512, 20.0, 512);

        $t->same(false, $splitRaw['isValid']);
        $t->same(false, $splitRaw['canInstantiate']);
        $t->same(1, $splitRaw['entryCount']);
        $t->same(false, $splitRaw['archive']['isArchiveLayoutSupported']);
        $t->same(true, $splitRaw['splitArchive']['hasSplitArchiveMarkers']);
        $t->same(['split-archive-eocd'], $splitRaw['splitArchive']['issues']);
        $t->contains('unsupported-archive-layout', implode(',', $splitRaw['diagnostics']));
        $t->contains('split-archive-eocd', implode(',', $splitRaw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $splitRaw['diagnostics']));

        $archiveExtraZip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw archive extra package</w:p></w:document>',
                'method' => 8,
            ],
        ]);
        $eocdOffset = strrpos($archiveExtraZip, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('EOCD fixture not found');
        }
        $archiveExtraData = 'raw-archive-extra-data';
        $archiveExtraRecord = "PK\x06\x08" . pack('V', strlen($archiveExtraData)) . $archiveExtraData;
        $archiveExtraZip = substr($archiveExtraZip, 0, $eocdOffset)
            . $archiveExtraRecord
            . substr($archiveExtraZip, $eocdOffset);
        $archiveExtraRaw = ZipPackage::rawStrictImportPreflight($archiveExtraZip, 512, 20.0, 512);

        $t->same(false, $archiveExtraRaw['isValid']);
        $t->same(false, $archiveExtraRaw['canInstantiate']);
        $t->same(1, $archiveExtraRaw['entryCount']);
        $t->same(true, $archiveExtraRaw['archiveExtraDataRecords']['hasArchiveExtraDataRecord']);
        $t->same('between-central-directory-and-eocd', $archiveExtraRaw['archiveExtraDataRecords']['archiveExtraDataRecords'][0]['location']);
        $t->contains('archive-extra-data-records', implode(',', $archiveExtraRaw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $archiveExtraRaw['diagnostics']));

        $zip64Raw = ZipPackage::rawStrictImportPreflight($buildZip64EndOfCentralDirectoryPackage(), 512, 20.0, 512);

        $t->same(false, $zip64Raw['isValid']);
        $t->same(false, $zip64Raw['canInstantiate']);
        $t->same(1, $zip64Raw['entryCount']);
        $t->same(true, $zip64Raw['archive']['requiresZip64']);
        $t->same(true, $zip64Raw['zip64EndOfCentralDirectory']['requiresZip64']);
        $t->same(null, $zip64Raw['centralDirectoryInventory']);
        $t->same(null, $zip64Raw['localHeaderNames']);
        $t->same(null, $zip64Raw['localHeaderMetadata']);
        $t->same(null, $zip64Raw['localHeaderSpans']);
        $t->contains('unsupported-archive-layout', implode(',', $zip64Raw['diagnostics']));
        $t->contains('zip64-end-of-central-directory', implode(',', $zip64Raw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $zip64Raw['diagnostics']));

        $encryptedRaw = ZipPackage::rawStrictImportPreflight($buildTraditionalEncryptedPackage(), 512, 20.0, 512);

        $t->same(false, $encryptedRaw['isValid']);
        $t->same(false, $encryptedRaw['canInstantiate']);
        $t->same(1, $encryptedRaw['entryCount']);
        $t->same(true, $encryptedRaw['encryption']['hasEncryptedEntries']);
        $t->same(1, $encryptedRaw['encryption']['traditionalEncryptionEntryCount']);
        $t->same(null, $encryptedRaw['strictImport']);
        $t->contains('encrypted-zip-entries', implode(',', $encryptedRaw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $encryptedRaw['diagnostics']));
    },

    'preflights central directory fixed headers before raw package handoff' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $contentTypes = '<Types><Default Extension="xml" ContentType="application/xml"/></Types>';
        $documentXml = '<w:document><w:p>central fixed header accounting</w:p></w:document>';
        $deflatedDocumentXml = gzdeflate($documentXml);
        if ($deflatedDocumentXml === false) {
            throw new RuntimeException('Unable to deflate central fixed header fixture');
        }

        $zip = $buildZipPackage([
            [
                'name' => '[Content_Types].xml',
                'data' => $contentTypes,
                'method' => 0,
                'modifiedTime' => 0x4a21,
                'modifiedDate' => 0x579b,
                'externalAttributes' => 0x20,
            ],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'modifiedTime' => 0x4a22,
                'modifiedDate' => 0x579c,
                'externalAttributes' => 0x20,
            ],
        ]);
        $package = ZipPackage::fromString($zip);

        $summary = ZipPackage::centralDirectoryFixedHeaderPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 100.0, 2048);
        $strict = $package->strictImportPreflight(2048, 100.0, 2048);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['declaredEntryCount']);
        $t->same(92, $summary['centralDirectoryFixedHeaderBytes']);
        $t->same(46, $summary['fixedHeaderLength']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);
        $t->same(false, $summary['hasUnexpectedCentralDirectoryTail']);
        $t->same(null, $summary['unexpectedRecordOffset']);
        $t->same(null, $summary['unexpectedRecordSignatureHex']);

        $first = $summary['entries'][0];
        $t->same('[Content_Types].xml', $first['name']);
        $t->same($summary['centralDirectoryOffset'], $first['recordOffset']);
        $t->same($first['recordOffset'], $first['fixedHeaderOffset']);
        $t->same($first['recordOffset'], $first['signatureOffset']);
        $t->same(4, $first['signatureLength']);
        $t->same($first['recordOffset'] + 4, $first['versionMadeByOffset']);
        $t->same(0x0314, $first['versionMadeBy']);
        $t->same(3, $first['creatorHostSystem']);
        $t->same(20, $first['creatorVersion']);
        $t->same($first['recordOffset'] + 6, $first['versionNeededToExtractOffset']);
        $t->same(20, $first['versionNeededToExtract']);
        $t->same($first['recordOffset'] + 8, $first['generalPurposeFlagsOffset']);
        $t->same(0x0800, $first['generalPurposeFlags']);
        $t->same($first['recordOffset'] + 10, $first['compressionMethodOffset']);
        $t->same(0, $first['compressionMethod']);
        $t->same('stored', $first['compressionMethodName']);
        $t->same($first['recordOffset'] + 12, $first['modifiedDosTimeOffset']);
        $t->same(0x4a21, $first['modifiedDosTime']);
        $t->same($first['recordOffset'] + 14, $first['modifiedDosDateOffset']);
        $t->same(0x579b, $first['modifiedDosDate']);
        $t->same($first['recordOffset'] + 16, $first['crc32Offset']);
        $t->same($crc32($contentTypes), $first['crc32']);
        $t->same(sprintf('%08x', $crc32($contentTypes)), $first['crc32Hex']);
        $t->same($first['recordOffset'] + 20, $first['compressedSizeOffset']);
        $t->same(strlen($contentTypes), $first['compressedSize']);
        $t->same($first['recordOffset'] + 24, $first['uncompressedSizeOffset']);
        $t->same(strlen($contentTypes), $first['uncompressedSize']);
        $t->same($first['recordOffset'] + 28, $first['nameLengthOffset']);
        $t->same(strlen('[Content_Types].xml'), $first['nameLength']);
        $t->same($first['recordOffset'] + 30, $first['extraFieldLengthOffset']);
        $t->same(0, $first['extraFieldLength']);
        $t->same($first['recordOffset'] + 32, $first['commentLengthOffset']);
        $t->same(0, $first['commentLength']);
        $t->same($first['recordOffset'] + 34, $first['diskStartOffset']);
        $t->same(0, $first['diskStart']);
        $t->same($first['recordOffset'] + 36, $first['internalAttributesOffset']);
        $t->same(0, $first['internalAttributes']);
        $t->same($first['recordOffset'] + 38, $first['externalAttributesOffset']);
        $t->same(0x20, $first['externalAttributes']);
        $t->same($first['recordOffset'] + 42, $first['localHeaderOffsetFieldOffset']);
        $t->same(0, $first['localHeaderOffset']);
        $t->same($first['recordOffset'] + 46, $first['fixedHeaderEnd']);
        $t->same($first['recordOffset'] + 46, $first['variableFieldsOffset']);
        $t->same(strlen('[Content_Types].xml'), $first['variableFieldsLength']);
        $t->same($first['variableFieldsOffset'] + strlen('[Content_Types].xml'), $first['recordEnd']);

        $second = $summary['entries'][1];
        $t->same('word/document.xml', $second['name']);
        $t->same(8, $second['compressionMethod']);
        $t->same('deflated', $second['compressionMethodName']);
        $t->same($crc32($documentXml), $second['crc32']);
        $t->same(strlen($deflatedDocumentXml), $second['compressedSize']);
        $t->same(strlen($documentXml), $second['uncompressedSize']);
        $t->same($second['recordOffset'] + 46, $second['variableFieldsOffset']);
        $t->same(strlen('word/document.xml'), $second['variableFieldsLength']);

        $t->same($summary, $rawStrict['centralDirectoryFixedHeaders']);
        $t->same($summary, $strict['centralDirectoryFixedHeaders']);
        $t->same(true, $rawStrict['isValid']);
        $t->same(true, $strict['isValid']);
    },

    'preflights central directory variable fields before raw package handoff' => static function (TestRunner $t) use ($buildZipPackage, $rewriteEndOfCentralDirectory): void {
        $extra = pack('vva*', 0xcafe, strlen('field'), 'field');
        $entryComment = 'content types review';
        $zip = $buildZipPackage([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'method' => 0,
                'centralExtra' => $extra,
                'localExtra' => '',
                'comment' => $entryComment,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>central variable field accounting</w:p></w:document>',
                'method' => 8,
            ],
        ], 'package comment');
        $nameBytes = strlen('[Content_Types].xml') + strlen('word/document.xml');

        $summary = ZipPackage::centralDirectoryVariableFieldsPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 20.0, 1024);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['declaredEntryCount']);
        $t->same($nameBytes + strlen($extra) + strlen($entryComment), $summary['centralDirectoryVariableFieldBytes']);
        $t->same($nameBytes, $summary['centralDirectoryNameBytes']);
        $t->same(strlen($extra), $summary['centralDirectoryExtraFieldBytes']);
        $t->same(strlen($entryComment), $summary['centralDirectoryCommentBytes']);
        $t->same(1, $summary['centralExtraFieldEntryCount']);
        $t->same(1, $summary['entryCommentCount']);
        $t->same(true, $summary['hasCentralDirectoryVariableFields']);
        $t->same(true, $summary['hasCentralExtraFields']);
        $t->same(true, $summary['hasEntryComments']);
        $t->same(true, $summary['hasPackageComment']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);
        $t->same('[Content_Types].xml', $summary['entries'][0]['name']);
        $t->same(46, $summary['entries'][0]['fixedHeaderLength']);
        $t->same(strlen('[Content_Types].xml'), $summary['entries'][0]['rawNameLength']);
        $t->same(strlen($extra), $summary['entries'][0]['centralExtraFieldLength']);
        $t->same(strlen($entryComment), $summary['entries'][0]['rawCommentLength']);
        $t->same($summary['entries'][0]['variableFieldsOffset'], $summary['entries'][0]['rawNameOffset']);
        $t->same($summary['entries'][0]['rawNameOffset'] + strlen('[Content_Types].xml'), $summary['entries'][0]['centralExtraFieldOffset']);
        $t->same($summary['entries'][0]['centralExtraFieldOffset'] + strlen($extra), $summary['entries'][0]['rawCommentOffset']);
        $t->same($summary['entries'][0]['rawCommentOffset'] + strlen($entryComment), $summary['entries'][0]['recordEnd']);

        $t->same($summary, $rawStrict['centralDirectoryVariableFields']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(true, $rawStrict['canInstantiate']);
        $t->contains('package-or-entry-comments', implode(',', $rawStrict['diagnostics']));

        $declaredTooHigh = $rewriteEndOfCentralDirectory($zip, [
            'diskEntryCount' => 3,
            'totalEntryCount' => 3,
        ]);
        $mismatch = ZipPackage::centralDirectoryVariableFieldsPreflight($declaredTooHigh);
        $mismatchRaw = ZipPackage::rawStrictImportPreflight($declaredTooHigh, 1024, 20.0, 1024);

        $t->same(2, $mismatch['entryCount']);
        $t->same(3, $mismatch['declaredEntryCount']);
        $t->same(false, $mismatch['isSupportedByBoundedReader']);
        $t->same(['central-directory-variable-field-missing-entry'], $mismatch['issues']);
        $t->same($mismatch, $mismatchRaw['centralDirectoryVariableFields']);
        $t->same(false, $mismatchRaw['isValid']);
        $t->same(false, $mismatchRaw['canInstantiate']);
        $t->contains('central-directory-variable-field-issues', implode(',', $mismatchRaw['diagnostics']));
        $t->contains('central-directory-variable-field-missing-entry', implode(',', $mismatchRaw['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $mismatchRaw['diagnostics']));
    },

    'preflights raw central directory name collisions before package instantiation' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $rawName = 'word/media/review-image.bin';
        $firstName = 'word/media/review-one.png';
        $secondName = 'word/media/review-two.png';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>raw name collision review</w:p></w:document>',
                'method' => 8,
                'flags' => 0x0801,
            ],
            [
                'name' => 'word/media/Review.PNG',
                'data' => "case collision first attachment bytes\n",
                'method' => 0,
                'flags' => 0x0801,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => "case collision second attachment bytes\n",
                'method' => 0,
                'flags' => 0x0801,
            ],
            [
                'name' => $rawName,
                'localName' => $rawName,
                'data' => "raw collision first attachment bytes\n",
                'method' => 0,
                'flags' => 0x0001,
                'localExtra' => $buildUnicodeExtra(0x7075, $rawName, $firstName),
                'centralExtra' => $buildUnicodeExtra(0x7075, $rawName, $firstName),
            ],
            [
                'name' => $rawName,
                'localName' => $rawName,
                'data' => "raw collision second attachment bytes\n",
                'method' => 0,
                'flags' => 0x0001,
                'localExtra' => $buildUnicodeExtra(0x7075, $rawName, $secondName),
                'centralExtra' => $buildUnicodeExtra(0x7075, $rawName, $secondName),
            ],
        ]);

        $summary = ZipPackage::centralDirectoryNameCollisionPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 2048, 20.0, 2048);

        $t->same(5, $summary['entryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['case-insensitive-name-collisions', 'raw-name-collisions'], $summary['issues']);
        $t->same(1, $summary['caseInsensitiveNameCollisionGroupCount']);
        $t->same(2, $summary['caseInsensitiveNameCollisionEntryCount']);
        $t->same('word/media/review.png', $summary['caseInsensitiveNameCollisionGroups'][0]['caseFoldKey']);
        $t->same(['word/media/Review.PNG', 'word/media/review.png'], $summary['caseInsensitiveNameCollisionGroups'][0]['entryNames']);
        $t->same('word/media/Review.PNG', $summary['caseInsensitiveNameCollisionEntries'][0]['name']);
        $t->same(['case-insensitive-name-collision'], $summary['caseInsensitiveNameCollisionEntries'][0]['issues']);
        $t->same('word/media/review.png', $summary['caseInsensitiveNameCollisionEntries'][1]['name']);

        $t->same(1, $summary['rawNameCollisionGroupCount']);
        $t->same(2, $summary['rawNameCollisionEntryCount']);
        $t->same($rawName, $summary['rawNameCollisionGroups'][0]['rawName']);
        $t->same(bin2hex($rawName), $summary['rawNameCollisionGroups'][0]['rawNameHex']);
        $t->same([$firstName, $secondName], $summary['rawNameCollisionGroups'][0]['entryNames']);
        $t->same($firstName, $summary['rawNameCollisionEntries'][0]['name']);
        $t->same($rawName, $summary['rawNameCollisionEntries'][0]['rawName']);
        $t->same('info-zip-unicode-path', $summary['rawNameCollisionEntries'][0]['nameEncoding']);
        $t->same(['raw-name-collision'], $summary['rawNameCollisionEntries'][0]['issues']);
        $t->same($secondName, $summary['rawNameCollisionEntries'][1]['name']);

        $t->same($summary, $rawStrict['centralDirectoryNameCollisions']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->same(5, $rawStrict['entryCount']);
        $t->same(5, $rawStrict['encryption']['encryptedEntryCount']);
        $t->contains('central-directory-name-collision-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('case-insensitive-name-collisions', implode(',', $rawStrict['diagnostics']));
        $t->contains('raw-name-collisions', implode(',', $rawStrict['diagnostics']));
        $t->contains('encrypted-zip-entries', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights malformed zip extra field structure before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $centralTruncatedPayload = pack('vv', 0xcafe, 4) . 'A';
        $localTruncatedHeader = "\xbe\xef";
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>central malformed extra field</w:p></w:document>',
                'centralExtra' => $centralTruncatedPayload,
                'localExtra' => '',
            ],
            [
                'name' => 'word/media/local-extra.bin',
                'data' => 'local malformed extra field',
                'centralExtra' => '',
                'localExtra' => $localTruncatedHeader,
            ],
        ]);

        $summary = ZipPackage::extraFieldStructurePolicyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 20.0, 1024);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['extraFieldEntryCount']);
        $t->same(2, $summary['issueEntryCount']);
        $t->same(1, $summary['centralExtraFieldIssueEntryCount']);
        $t->same(1, $summary['localExtraFieldIssueEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['central-extra-field-truncated-payload', 'local-extra-field-truncated-header'], $summary['issues']);

        $centralIssueEntry = $summary['issueEntries'][0];
        $t->same('word/document.xml', $centralIssueEntry['name']);
        $t->same(false, $centralIssueEntry['centralExtraFields']['isWellFormed']);
        $t->same(true, $centralIssueEntry['localExtraFields']['isWellFormed']);
        $t->same(1, $centralIssueEntry['centralExtraFields']['fieldCount']);
        $t->same(0xcafe, $centralIssueEntry['centralExtraFields']['fields'][0]['id']);
        $t->same('cafe', $centralIssueEntry['centralExtraFields']['fields'][0]['idHex']);
        $t->same(4, $centralIssueEntry['centralExtraFields']['fields'][0]['declaredDataLength']);
        $t->same(1, $centralIssueEntry['centralExtraFields']['fields'][0]['availableDataBytes']);
        $t->same('central-extra-field-truncated-payload', $centralIssueEntry['centralExtraFields']['fields'][0]['issue']);

        $localIssueEntry = $summary['issueEntries'][1];
        $t->same('word/media/local-extra.bin', $localIssueEntry['name']);
        $t->same(true, $localIssueEntry['centralExtraFields']['isWellFormed']);
        $t->same(false, $localIssueEntry['localExtraFields']['isWellFormed']);
        $t->same(1, $localIssueEntry['localExtraFields']['fieldCount']);
        $t->same(null, $localIssueEntry['localExtraFields']['fields'][0]['id']);
        $t->same(2, $localIssueEntry['localExtraFields']['fields'][0]['availableDataBytes']);
        $t->same('local-extra-field-truncated-header', $localIssueEntry['localExtraFields']['fields'][0]['issue']);

        $t->same($summary, $rawStrict['extraFieldStructure']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->contains('extra-field-structure-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('central-extra-field-truncated-payload', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-extra-field-truncated-header', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights raw zip extra field id policy before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $duplicateExtra = pack('vva*', 0xcafe, strlen('first'), 'first')
            . pack('vva*', 0xcafe, strlen('second'), 'second');
        $zip = $buildZipPackage([
            [
                'name' => 'word/media/duplicate-extra.bin',
                'data' => "duplicate extra field ids should stay visible before instantiation\n",
                'centralExtra' => $duplicateExtra,
                'localExtra' => $duplicateExtra,
                'centralFlags' => 0x0840,
                'localFlags' => 0x0840,
            ],
            [
                'name' => 'word/media/split-extra.bin',
                'data' => "central/local extra field id splits should stay visible\n",
                'centralExtra' => pack('vva*', 0xbeef, strlen('central-only'), 'central-only'),
                'localExtra' => pack('vva*', 0xfeed, strlen('local-only'), 'local-only'),
                'centralFlags' => 0x0840,
                'localFlags' => 0x0840,
            ],
            [
                'name' => 'word/media/value-extra.bin',
                'data' => "central/local extra field value splits should stay visible\n",
                'centralExtra' => pack('vva*', 0xf00d, strlen('central'), 'central'),
                'localExtra' => pack('vva*', 0xf00d, strlen('local'), 'local'),
                'centralFlags' => 0x0840,
                'localFlags' => 0x0840,
            ],
        ]);

        $summary = ZipPackage::extraFieldPolicyPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 20.0, 1024);

        $t->same(3, $summary['entryCount']);
        $t->same(3, $summary['extraFieldEntryCount']);
        $t->same(3, count($summary['issueEntries']));
        $t->same(1, $summary['duplicateExtraFieldEntryCount']);
        $t->same(1, $summary['duplicateCentralExtraFieldEntryCount']);
        $t->same(1, $summary['duplicateLocalExtraFieldEntryCount']);
        $t->same(1, $summary['mismatchedExtraFieldEntryCount']);
        $t->same(1, $summary['mismatchedExtraFieldValueEntryCount']);
        $t->same(1, $summary['centralOnlyExtraFieldEntryCount']);
        $t->same(1, $summary['localOnlyExtraFieldEntryCount']);
        $t->same(0, $summary['localHeaderUnavailableEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'duplicate-central-extra-field-ids',
            'duplicate-local-extra-field-ids',
            'central-only-extra-field-ids',
            'local-only-extra-field-ids',
            'central-local-extra-field-value-mismatch',
        ], $summary['issues']);

        $duplicateEntry = $summary['duplicateEntries'][0];
        $t->same('word/media/duplicate-extra.bin', $duplicateEntry['name']);
        $t->same([0xcafe, 0xcafe], $duplicateEntry['centralExtraFieldIds']);
        $t->same(['0xcafe', '0xcafe'], $duplicateEntry['centralExtraFieldIdHexes']);
        $t->same([0xcafe, 0xcafe], $duplicateEntry['localExtraFieldIds']);
        $t->same(['0xcafe', '0xcafe'], $duplicateEntry['localExtraFieldIdHexes']);
        $t->same([0xcafe], $duplicateEntry['duplicateCentralExtraFieldIds']);
        $t->same(['0xcafe'], $duplicateEntry['duplicateCentralExtraFieldIdHexes']);
        $t->same([0xcafe], $duplicateEntry['duplicateLocalExtraFieldIds']);
        $t->same(['0xcafe'], $duplicateEntry['duplicateLocalExtraFieldIdHexes']);
        $t->same(true, $duplicateEntry['hasDuplicateExtraFieldIds']);
        $t->same('blocked', $duplicateEntry['policy']);

        $mismatchEntry = $summary['mismatchedEntries'][0];
        $t->same('word/media/split-extra.bin', $mismatchEntry['name']);
        $t->same([0xbeef], $mismatchEntry['centralOnlyExtraFieldIds']);
        $t->same(['0xbeef'], $mismatchEntry['centralOnlyExtraFieldIdHexes']);
        $t->same([0xfeed], $mismatchEntry['localOnlyExtraFieldIds']);
        $t->same(['0xfeed'], $mismatchEntry['localOnlyExtraFieldIdHexes']);
        $t->same(true, $mismatchEntry['hasMismatchedExtraFieldIds']);

        $valueEntry = $summary['valueMismatchedEntries'][0];
        $t->same('word/media/value-extra.bin', $valueEntry['name']);
        $t->same([0xf00d], $valueEntry['mismatchedExtraFieldValueIds']);
        $t->same(['0xf00d'], $valueEntry['mismatchedExtraFieldValueIdHexes']);
        $t->same(true, $valueEntry['hasMismatchedExtraFieldValues']);

        $t->same($summary, $rawStrict['extraFields']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->contains('extra-field-policy-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('duplicate-extra-field-ids', implode(',', $rawStrict['diagnostics']));
        $t->contains('central-local-extra-field-id-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->contains('central-local-extra-field-value-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights zip64 extended information extra field plans before package import' => static function (TestRunner $t) use ($buildZipPackage, $packUInt64): void {
        $centralData = '<w:document><w:p>zip64 central size upgrade plan</w:p></w:document>';
        $centralCompressed = gzdeflate($centralData);
        $centralZip64Values = $packUInt64(strlen($centralData))
            . $packUInt64(strlen($centralCompressed))
            . $packUInt64(0)
            . pack('V', 0);
        $centralZip64Extra = pack('vv', 0x0001, strlen($centralZip64Values)) . $centralZip64Values;
        $localData = "local ZIP64 size placeholders should stay blocked\n";
        $localZip64Values = $packUInt64(strlen($localData)) . $packUInt64(strlen($localData));
        $localZip64Extra = pack('vv', 0x0001, strlen($localZip64Values)) . $localZip64Values;
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $centralData,
                'method' => 8,
                'centralCompressedSize' => 0xffffffff,
                'centralUncompressedSize' => 0xffffffff,
                'centralLocalHeaderOffset' => 0xffffffff,
                'diskStart' => 0xffff,
                'centralExtra' => $centralZip64Extra,
            ],
            [
                'name' => 'word/media/local-size.bin',
                'data' => $localData,
                'method' => 0,
                'localCompressedSize' => 0xffffffff,
                'localUncompressedSize' => 0xffffffff,
                'localExtra' => $localZip64Extra,
                'centralExtra' => '',
            ],
        ]);
        $summary = ZipPackage::zip64ExtraFieldPreflight($zip);

        $t->same(2, $summary['entryCount']);
        $t->same(2, $summary['zip64ExtraFieldEntryCount']);
        $t->same(1, $summary['centralZip64ExtraFieldEntryCount']);
        $t->same(1, $summary['localZip64ExtraFieldEntryCount']);
        $t->same(2, $summary['requiresZip64EntryCount']);
        $t->same(2, count($summary['zip64Entries']));

        $centralEntry = $summary['entries'][0];
        $t->same('word/document.xml', $centralEntry['name']);
        $t->same(true, $centralEntry['centralZip64ExtraFieldPresent']);
        $t->same(false, $centralEntry['localZip64ExtraFieldPresent']);
        $t->same([
            'uncompressedSize',
            'compressedSize',
            'localHeaderOffset',
            'diskStart',
        ], $centralEntry['centralZip64RequiredFields']);
        $t->same([
            'uncompressedSize' => strlen($centralData),
            'compressedSize' => strlen($centralCompressed),
            'localHeaderOffset' => 0,
            'diskStart' => 0,
        ], $centralEntry['centralZip64Values']);
        $t->same(0, $centralEntry['centralZip64ExtraBytes']);
        $t->same(true, $centralEntry['requiresZip64']);
        $t->same(false, $centralEntry['isSupportedByBoundedReader']);
        $t->same(['zip64-extra-field', 'zip64-size-or-offset-sentinel'], $centralEntry['issues']);

        $localEntry = $summary['entries'][1];
        $t->same('word/media/local-size.bin', $localEntry['name']);
        $t->same(false, $localEntry['centralZip64ExtraFieldPresent']);
        $t->same(true, $localEntry['localZip64ExtraFieldPresent']);
        $t->same([], $localEntry['centralZip64RequiredFields']);
        $t->same([
            'uncompressedSize',
            'compressedSize',
        ], $localEntry['localZip64RequiredFields']);
        $t->same([
            'uncompressedSize' => strlen($localData),
            'compressedSize' => strlen($localData),
        ], $localEntry['localZip64Values']);
        $t->same(0, $localEntry['localZip64ExtraBytes']);
        $t->same(true, $localEntry['requiresZip64']);
        $t->same(false, $localEntry['isSupportedByBoundedReader']);
        $t->same(['zip64-extra-field', 'zip64-size-or-offset-sentinel'], $localEntry['issues']);
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'preflights zip64 local header compatibility before package import' => static function (TestRunner $t) use ($buildZipPackage, $packUInt64): void {
        $centralName = 'word/document.xml';
        $localName = 'word/media/spoofed-local-header.bin';
        $data = "ZIP64 local header offset should not hide a spoofed local name\n";
        $zip64OffsetValue = $packUInt64(0);
        $zip64OffsetExtra = pack('vv', 0x0001, strlen($zip64OffsetValue)) . $zip64OffsetValue;
        $zip = $buildZipPackage([
            [
                'name' => $centralName,
                'localName' => $localName,
                'data' => $data,
                'method' => 0,
                'centralLocalHeaderOffset' => 0xffffffff,
                'centralExtra' => $zip64OffsetExtra,
            ],
        ]);

        $summary = ZipPackage::zip64ExtraFieldPreflight($zip);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 1024, 20.0, 1024);
        $entry = $summary['entries'][0];

        $t->same(1, $summary['entryCount']);
        $t->same(1, $summary['zip64ExtraFieldEntryCount']);
        $t->same(1, $summary['requiresZip64EntryCount']);
        $t->same(1, $summary['mismatchedLocalHeaderEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'zip64-extra-field',
            'zip64-size-or-offset-sentinel',
            'zip64-local-header-name-mismatch',
            'zip64-local-header-decoded-name-mismatch',
        ], $summary['issues']);
        $t->same($centralName, $entry['name']);
        $t->same('utf-8', $entry['nameEncoding']);
        $t->same(0xffffffff, $entry['centralLocalHeaderOffset']);
        $t->same(0, $entry['localHeaderOffset']);
        $t->same('zip64-extra-field', $entry['localHeaderOffsetSource']);
        $t->same($localName, $entry['localRawName']);
        $t->same($localName, $entry['localName']);
        $t->same('utf-8', $entry['localNameEncoding']);
        $t->same(0x0800, $entry['localGeneralPurposeFlags']);
        $t->same(0, $entry['localCompressionMethod']);
        $t->same(false, $entry['rawNameMatchesLocalHeader']);
        $t->same(false, $entry['decodedNameMatchesLocalHeader']);
        $t->same(true, $entry['generalPurposeFlagsMatchLocalHeader']);
        $t->same(true, $entry['compressionMethodMatchesLocalHeader']);
        $t->same($entry, $summary['mismatchedLocalHeaderEntries'][0]);
        $t->same($entry, $summary['zip64Entries'][0]);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(1, $rawStrict['zip64ExtraFields']['mismatchedLocalHeaderEntryCount']);
        $t->contains('zip64-local-header-name-mismatch', implode(',', $rawStrict['diagnostics']));
        $t->contains('raw-local-header-names-preflight-failed', implode(',', $rawStrict['diagnostics']));
    },

    'rejects zip64 extra field metadata before office package import preflight' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip64Extra = pack('vv', 0x0001, 8) . str_repeat("\0", 8);
        $summary = ZipPackage::zip64ExtraFieldPreflight($buildZipPackage([
            [
                'name' => 'word/media/oversized-review.bin',
                'data' => 'unneeded zip64 metadata remains review-only',
                'centralExtra' => $zip64Extra,
            ],
        ]));

        $t->same(1, $summary['entryCount']);
        $t->same(1, $summary['zip64ExtraFieldEntryCount']);
        $t->same(1, $summary['centralZip64ExtraFieldEntryCount']);
        $t->same(0, $summary['requiresZip64EntryCount']);
        $t->same('word/media/oversized-review.bin', $summary['zip64Entries'][0]['name']);
        $t->same([], $summary['zip64Entries'][0]['centralZip64RequiredFields']);
        $t->same(['zip64-extra-field', 'zip64-extra-field-without-sentinel', 'zip64-extra-field-trailing-bytes'], $summary['zip64Entries'][0]['issues']);

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>zip64 central metadata</w:p></w:document>',
                'centralExtra' => $zip64Extra,
            ],
        ])));

        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/reviewer-note.txt',
                'data' => 'local zip64 metadata must not be exposed',
                'localExtra' => $zip64Extra,
                'centralExtra' => '',
            ],
        ])));
    },

    'bounds zip part reads before exposing oversized package media bytes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>bounded document</w:p></w:body></w:document>';
        $mediaBytes = str_repeat("review media bytes\n", 12);

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'method' => 8,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));

        $t->same($documentXml, $package->readBounded('/word/document.xml', strlen($documentXml)));
        $t->same($mediaBytes, $package->read('/word/media/review.bin', strlen($mediaBytes)));
        $t->same('', $package->readBounded('/word/media/', 0));
        $t->throws(\RuntimeException::class, static fn (): string => $package->readBounded('/word/document.xml', strlen($documentXml) - 1));
        $t->throws(\RuntimeException::class, static fn (): string => $package->read('/word/media/review.bin', 32));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $package->readBounded('/word/document.xml', -1));
    },

    'preflights selected zip package entries before reader handoff' => static function (TestRunner $t) use ($buildZipPackage, $pathSegmentPositionReviews): void {
        $documentXml = '<w:document><w:body><w:p>selected handoff</w:p></w:body></w:document>';
        $imageBytes = "review image bytes\n";
        $largeBytes = "large selected media bytes\n";
        $unsupportedBytes = "unsupported selected media bytes\n";

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/image.png',
                'data' => $imageBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'word/media/large.bin',
                'data' => $largeBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/unsupported.bin',
                'data' => $unsupportedBytes,
                'method' => 12,
                'centralCompressedSize' => strlen($unsupportedBytes),
                'centralUncompressedSize' => strlen($unsupportedBytes),
                'localCompressedSize' => strlen($unsupportedBytes),
                'localUncompressedSize' => strlen($unsupportedBytes),
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/media/image.png', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/media/', 'required' => false, 'kind' => 'directory', 'role' => 'media-directory'],
            ['name' => 'word/missing.xml', 'required' => true, 'kind' => 'file', 'role' => 'required-sidecar'],
            ['name' => 'word/media/', 'required' => true, 'kind' => 'file', 'role' => 'spoofed-file'],
            ['name' => 'word/media/large.bin', 'required' => false, 'kind' => 'file', 'role' => 'oversized-optional', 'maxUncompressedBytes' => 8],
            ['name' => 'word/media/unsupported.bin', 'required' => true, 'kind' => 'file', 'role' => 'unsupported-required'],
            ['name' => 'word/optional.xml', 'required' => false, 'kind' => 'file', 'role' => 'optional-sidecar'],
        ], 128);
        $documentCompressed = strlen(gzdeflate($documentXml));
        $selectedStoredBytes = strlen($imageBytes) + strlen($largeBytes);
        $selectedUnsupportedEntry = [
            'name' => 'word/media/unsupported.bin',
            'compressionMethod' => 12,
            'isDirectory' => false,
            'compressedSize' => strlen($unsupportedBytes),
            'uncompressedSize' => strlen($unsupportedBytes),
        ];
        $selectedCompressionMethodBuckets = [
            [
                'compressionMethod' => 0,
                'compressionMethodName' => 'stored',
                'entryCount' => 3,
                'compressedBytes' => $selectedStoredBytes,
                'uncompressedBytes' => $selectedStoredBytes,
                'isSupported' => true,
            ],
            [
                'compressionMethod' => 8,
                'compressionMethodName' => 'deflated',
                'entryCount' => 1,
                'compressedBytes' => $documentCompressed,
                'uncompressedBytes' => strlen($documentXml),
                'isSupported' => true,
            ],
            [
                'compressionMethod' => 12,
                'compressionMethodName' => 'unsupported',
                'entryCount' => 1,
                'compressedBytes' => strlen($unsupportedBytes),
                'uncompressedBytes' => strlen($unsupportedBytes),
                'isSupported' => false,
            ],
        ];

        $t->same(8, $summary['requestedEntryCount']);
        $t->same(4, $summary['requiredEntryCount']);
        $t->same(4, $summary['optionalEntryCount']);
        $t->same(5, $summary['presentEntryCount']);
        $t->same(5, $summary['selectedUniqueEntryCount']);
        $t->same(4, $summary['selectedFileEntryCount']);
        $t->same(1, $summary['selectedDirectoryEntryCount']);
        $t->same(3, $summary['selectedStoredEntryCount']);
        $t->same(1, $summary['selectedDeflatedEntryCount']);
        $t->same(1, $summary['selectedUnsupportedCompressionMethodCount']);
        $t->same(4, $summary['selectedSupportedCompressionMethodEntryCount']);
        $t->same($selectedCompressionMethodBuckets, $summary['selectedCompressionMethodBuckets']);
        $t->same([$selectedUnsupportedEntry], $summary['selectedUnsupportedCompressionMethodEntries']);
        $t->same(2, $summary['missingEntryCount']);
        $t->same(1, $summary['missingRequiredEntryCount']);
        $t->same(1, $summary['missingOptionalEntryCount']);
        $t->same(2, $summary['handoffEntryCount']);
        $t->same(2, $summary['readableEntryCount']);
        $t->same(5, $summary['failedEntryCount']);
        $t->same(1, $summary['directoryMismatchEntryCount']);
        $t->same(1, $summary['oversizedEntryCount']);
        $t->same(1, $summary['unreadableEntryCount']);
        $t->same(2, $summary['duplicateRequestedEntryCount']);
        $t->same(1, $summary['duplicateRequestedEntryGroupCount']);
        $t->same(128, $summary['maxEntryUncompressedBytes']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same([
            'duplicate-selected-entry-request',
            'missing-required-entry',
            'directory-entry-not-file',
            'entry-uncompressed-size-exceeds-limit',
            'unreadable-entry',
        ], $summary['issues']);

        $documentEntry = $summary['entries'][0];
        $t->same(0, $documentEntry['requestIndex']);
        $t->same('/word/document.xml', $documentEntry['requestedName']);
        $t->same('word/document.xml', $documentEntry['name']);
        $t->same('main-document', $documentEntry['role']);
        $t->same(true, $documentEntry['required']);
        $t->same('file', $documentEntry['expectedKind']);
        $t->same(true, $documentEntry['exists']);
        $t->same(false, $documentEntry['isDirectory']);
        $t->same('word/', $documentEntry['directoryRoot']);
        $t->same(['word', 'document.xml'], $documentEntry['pathSegments']);
        $t->same($pathSegmentPositionReviews(['word', 'document.xml']), $documentEntry['pathSegmentPositionReviews']);
        $t->same(2, $documentEntry['pathSegmentCount']);
        $t->same(1, $documentEntry['directoryDepth']);
        $t->same('document.xml', $documentEntry['packagePartBaseName']);
        $t->same('document.xml', $documentEntry['packagePartCaseFoldBaseName']);
        $t->same('document', $documentEntry['packagePartBaseNameStem']);
        $t->same('document', $documentEntry['packagePartCaseFoldBaseNameStem']);
        $t->same('xml', $documentEntry['packagePartExtension']);
        $t->same('xml', $documentEntry['packagePartExtensionKey']);
        $t->same(false, $documentEntry['extensionlessPackagePart']);
        $t->same(8, $documentEntry['compressionMethod']);
        $t->same('deflated', $documentEntry['compressionMethodName']);
        $t->same(0, $documentEntry['localHeaderOffset']);
        $t->same(30 + strlen('word/document.xml'), $documentEntry['localHeaderLength']);
        $t->same($documentEntry['localHeaderOffset'] + $documentEntry['localHeaderLength'], $documentEntry['compressedDataOffset']);
        $t->same($documentEntry['compressedDataOffset'] + $documentEntry['compressedSize'], $documentEntry['compressedDataEnd']);
        $t->true($documentEntry['compressedDataEnd'] <= $documentEntry['centralDirectoryRecordOffset']);
        $t->true($documentEntry['centralDirectoryRecordOffset'] < $documentEntry['centralDirectoryRecordEnd']);
        $t->same(strlen($documentXml), $documentEntry['uncompressedSize']);
        $t->same(strlen($documentXml) / $documentEntry['compressedSize'], $documentEntry['expansionRatio']);
        $t->same(strlen($documentXml), $documentEntry['bytesRead']);
        $t->same(hash('sha256', $documentXml), $documentEntry['contentSha256']);
        $t->same('ready', $documentEntry['status']);
        $t->same([], $documentEntry['issues']);

        $imageEntry = $summary['entries'][1];
        $t->same('word/media/image.png', $imageEntry['name']);
        $t->same('stored', $imageEntry['compressionMethodName']);
        $t->same($documentEntry['compressedDataEnd'], $imageEntry['localHeaderOffset']);
        $t->same($imageEntry['compressedDataOffset'] + strlen($imageBytes), $imageEntry['compressedDataEnd']);
        $t->same(1.0, $imageEntry['expansionRatio']);

        $directoryEntry = $summary['entries'][2];
        $t->same('word/media/', $directoryEntry['name']);
        $t->same('directory', $directoryEntry['expectedKind']);
        $t->same(true, $directoryEntry['isDirectory']);
        $t->same(['word', 'media'], $directoryEntry['pathSegments']);
        $t->same($pathSegmentPositionReviews(['word', 'media']), $directoryEntry['pathSegmentPositionReviews']);
        $t->same(2, $directoryEntry['pathSegmentCount']);
        $t->same(1, $directoryEntry['directoryDepth']);
        $t->same('media', $directoryEntry['packagePartBaseName']);
        $t->same('media', $directoryEntry['packagePartCaseFoldBaseName']);
        $t->same(null, $directoryEntry['packagePartBaseNameStem']);
        $t->same(null, $directoryEntry['packagePartCaseFoldBaseNameStem']);
        $t->same(null, $directoryEntry['packagePartExtension']);
        $t->same('(directory)', $directoryEntry['packagePartExtensionKey']);
        $t->same(false, $directoryEntry['extensionlessPackagePart']);
        $t->same('stored', $directoryEntry['compressionMethodName']);
        $t->same($directoryEntry['compressedDataOffset'], $directoryEntry['compressedDataEnd']);
        $t->same(0.0, $directoryEntry['expansionRatio']);
        $t->same(null, $directoryEntry['bytesRead']);
        $t->same(null, $directoryEntry['contentSha256']);
        $t->same('blocked', $directoryEntry['status']);
        $t->same(['duplicate-selected-entry-request'], $directoryEntry['issues']);

        $missingRequired = $summary['entries'][3];
        $t->same('word/missing.xml', $missingRequired['name']);
        $t->same(false, $missingRequired['exists']);
        $t->same(null, $missingRequired['compressionMethodName']);
        $t->same(null, $missingRequired['compressedDataOffset']);
        $t->same(null, $missingRequired['centralDirectoryRecordOffset']);
        $t->same('missing-required', $missingRequired['status']);
        $t->same(['missing-required-entry'], $missingRequired['issues']);

        $directoryMismatch = $summary['entries'][4];
        $t->same('word/media/', $directoryMismatch['name']);
        $t->same('file', $directoryMismatch['expectedKind']);
        $t->same(['duplicate-selected-entry-request', 'directory-entry-not-file'], $directoryMismatch['issues']);
        $t->same('blocked', $directoryMismatch['status']);

        $oversizedEntry = $summary['entries'][5];
        $t->same('word/media/large.bin', $oversizedEntry['name']);
        $t->same(8, $oversizedEntry['maxUncompressedBytes']);
        $t->same(strlen($largeBytes), $oversizedEntry['uncompressedSize']);
        $t->same($oversizedEntry['compressedDataOffset'] + strlen($largeBytes), $oversizedEntry['compressedDataEnd']);
        $t->same(null, $oversizedEntry['bytesRead']);
        $t->same(['entry-uncompressed-size-exceeds-limit'], $oversizedEntry['issues']);

        $unsupportedEntry = $summary['entries'][6];
        $t->same('word/media/unsupported.bin', $unsupportedEntry['name']);
        $t->same(12, $unsupportedEntry['compressionMethod']);
        $t->same('unsupported', $unsupportedEntry['compressionMethodName']);
        $t->same($unsupportedEntry['compressedDataOffset'] + strlen($unsupportedBytes), $unsupportedEntry['compressedDataEnd']);
        $t->same(1.0, $unsupportedEntry['expansionRatio']);
        $t->same(false, $unsupportedEntry['isReadable']);
        $t->same(['unreadable-entry'], $unsupportedEntry['issues']);
        $t->contains('Unsupported ZIP compression method 12', $unsupportedEntry['error']);

        $optionalMissing = $summary['entries'][7];
        $t->same('word/optional.xml', $optionalMissing['name']);
        $t->same(false, $optionalMissing['required']);
        $t->same(false, $optionalMissing['exists']);
        $t->same('request-path', $optionalMissing['packagePathIdentitySource']);
        $t->same('word/', $optionalMissing['directoryRoot']);
        $t->same(['word', 'optional.xml'], $optionalMissing['pathSegments']);
        $t->same($pathSegmentPositionReviews(['word', 'optional.xml']), $optionalMissing['pathSegmentPositionReviews']);
        $t->same('optional.xml', $optionalMissing['packagePartBaseName']);
        $t->same('optional', $optionalMissing['packagePartBaseNameStem']);
        $t->same('xml', $optionalMissing['packagePartExtensionKey']);
        $t->same('missing-optional', $optionalMissing['status']);
        $t->same([], $optionalMissing['issues']);

        $t->same([$directoryEntry, $missingRequired, $directoryMismatch, $oversizedEntry, $unsupportedEntry], $summary['failedEntries']);
        $t->same([$documentEntry, $summary['entries'][1]], $summary['handoffEntries']);

        $safeSummary = $package->entryHandoffPreflight([
            'word/document.xml',
            ['name' => 'word/media/image.png', 'required' => false, 'kind' => 'file'],
        ], 128);

        $t->same(true, $safeSummary['isSupportedByBoundedReader']);
        $t->same([], $safeSummary['issues']);
        $t->same(2, $safeSummary['handoffEntryCount']);
        $t->same(2, $safeSummary['readableEntryCount']);
    },

    'preflights selected zip entry raw name provenance before reader handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $documentXml = '<w:document><w:body><w:p>selected raw name provenance</w:p></w:body></w:document>';
        $unicodeRawName = 'word/media/review-image.bin';
        $unicodeName = "word/media/review-\u{2603}.png";
        $unicodeBytes = "unicode path media placeholder\n";
        $unicodePathExtra = $buildUnicodeExtra(0x7075, $unicodeRawName, $unicodeName);
        $cp437RawName = "word/media/caf\x82.png";
        $cp437Name = "word/media/caf\u{00e9}.png";
        $cp437Bytes = "legacy encoded media placeholder\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => $unicodeRawName,
                'localName' => $unicodeRawName,
                'data' => $unicodeBytes,
                'method' => 0,
                'flags' => 0,
                'localExtra' => $unicodePathExtra,
                'centralExtra' => $unicodePathExtra,
            ],
            [
                'name' => $cp437RawName,
                'data' => $cp437Bytes,
                'method' => 0,
                'flags' => 0,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => $unicodeName, 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => $cp437Name, 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/media/missing.png', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
        ], 1024);

        $t->same(4, $summary['requestedEntryCount']);
        $t->same(3, $summary['presentEntryCount']);
        $t->same(3, $summary['selectedUniqueEntryCount']);
        $t->same(2, $summary['selectedRawNameProvenanceEntryCount']);
        $t->same(1, $summary['selectedLegacyEncodedNameEntryCount']);
        $t->same(1, $summary['selectedUnicodePathExtraEntryCount']);
        $t->same(2, $summary['selectedDecodedNameDiffersFromRawNameEntryCount']);
        $t->same(3, $summary['handoffEntryCount']);
        $t->same(0, $summary['failedEntryCount']);

        $documentEntry = $summary['entries'][0];
        $t->same('word/document.xml', $documentEntry['rawName']);
        $t->same(bin2hex('word/document.xml'), $documentEntry['rawNameHex']);
        $t->same('utf-8', $documentEntry['nameEncoding']);
        $t->same(true, $documentEntry['rawNameMatchesDecodedName']);
        $t->same(false, $documentEntry['hasRawNameProvenance']);

        $unicodeEntry = $summary['entries'][1];
        $t->same($unicodeName, $unicodeEntry['name']);
        $t->same($unicodeRawName, $unicodeEntry['rawName']);
        $t->same(bin2hex($unicodeRawName), $unicodeEntry['rawNameHex']);
        $t->same('info-zip-unicode-path', $unicodeEntry['nameEncoding']);
        $t->same(false, $unicodeEntry['rawNameMatchesDecodedName']);
        $t->same(false, $unicodeEntry['usesLegacyNameEncoding']);
        $t->same(true, $unicodeEntry['usesUnicodePathExtraField']);
        $t->same(true, $unicodeEntry['hasRawNameProvenance']);
        $t->same(strlen($unicodeBytes), $unicodeEntry['bytesRead']);
        $t->same(hash('sha256', $unicodeBytes), $unicodeEntry['contentSha256']);

        $cp437Entry = $summary['entries'][2];
        $t->same($cp437Name, $cp437Entry['name']);
        $t->same($cp437RawName, $cp437Entry['rawName']);
        $t->same(bin2hex($cp437RawName), $cp437Entry['rawNameHex']);
        $t->same('cp437', $cp437Entry['nameEncoding']);
        $t->same(false, $cp437Entry['rawNameMatchesDecodedName']);
        $t->same(true, $cp437Entry['usesLegacyNameEncoding']);
        $t->same(false, $cp437Entry['usesUnicodePathExtraField']);
        $t->same(true, $cp437Entry['hasRawNameProvenance']);
        $t->same(strlen($cp437Bytes), $cp437Entry['bytesRead']);
        $t->same(hash('sha256', $cp437Bytes), $cp437Entry['contentSha256']);

        $t->same([
            [
                'name' => $unicodeName,
                'rawName' => $unicodeRawName,
                'rawNameHex' => bin2hex($unicodeRawName),
                'nameEncoding' => 'info-zip-unicode-path',
                'rawNameMatchesDecodedName' => false,
                'usesLegacyNameEncoding' => false,
                'usesUnicodePathExtraField' => true,
                'hasRawNameProvenance' => true,
            ],
            [
                'name' => $cp437Name,
                'rawName' => $cp437RawName,
                'rawNameHex' => bin2hex($cp437RawName),
                'nameEncoding' => 'cp437',
                'rawNameMatchesDecodedName' => false,
                'usesLegacyNameEncoding' => true,
                'usesUnicodePathExtraField' => false,
                'hasRawNameProvenance' => true,
            ],
        ], $summary['selectedRawNameProvenanceEntries']);

        $missingEntry = $summary['entries'][3];
        $t->same(false, $missingEntry['exists']);
        $t->same(null, $missingEntry['rawName']);
        $t->same(null, $missingEntry['rawNameHex']);
        $t->same(null, $missingEntry['nameEncoding']);
        $t->same(false, $missingEntry['hasRawNameProvenance']);
        $t->same([$documentEntry, $unicodeEntry, $cp437Entry], $summary['handoffEntries']);

        $manifest = $summary['selectedHandoffManifest'];
        $t->same(2, $manifest['rawNameProvenanceRequestCount']);
        $t->same(1, $manifest['legacyEncodedNameRequestCount']);
        $t->same(1, $manifest['unicodePathExtraRequestCount']);
        $t->same(2, $manifest['decodedNameDiffersFromRawNameRequestCount']);
        $t->same([
            [
                'name' => 'word/document.xml',
                'rawNameHex' => bin2hex('word/document.xml'),
                'nameEncoding' => 'utf-8',
                'rawNameMatchesDecodedName' => true,
                'usesLegacyNameEncoding' => false,
                'usesUnicodePathExtraField' => false,
                'hasRawNameProvenance' => false,
            ],
            [
                'name' => $unicodeName,
                'rawNameHex' => bin2hex($unicodeRawName),
                'nameEncoding' => 'info-zip-unicode-path',
                'rawNameMatchesDecodedName' => false,
                'usesLegacyNameEncoding' => false,
                'usesUnicodePathExtraField' => true,
                'hasRawNameProvenance' => true,
            ],
            [
                'name' => $cp437Name,
                'rawNameHex' => bin2hex($cp437RawName),
                'nameEncoding' => 'cp437',
                'rawNameMatchesDecodedName' => false,
                'usesLegacyNameEncoding' => true,
                'usesUnicodePathExtraField' => false,
                'hasRawNameProvenance' => true,
            ],
            [
                'name' => 'word/media/missing.png',
                'rawNameHex' => null,
                'nameEncoding' => null,
                'rawNameMatchesDecodedName' => null,
                'usesLegacyNameEncoding' => false,
                'usesUnicodePathExtraField' => false,
                'hasRawNameProvenance' => false,
            ],
        ], array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'rawNameHex' => $entry['rawNameHex'],
                'nameEncoding' => $entry['nameEncoding'],
                'rawNameMatchesDecodedName' => $entry['rawNameMatchesDecodedName'],
                'usesLegacyNameEncoding' => $entry['usesLegacyNameEncoding'],
                'usesUnicodePathExtraField' => $entry['usesUnicodePathExtraField'],
                'hasRawNameProvenance' => $entry['hasRawNameProvenance'],
            ],
            $manifest['entries']
        ));
        $t->same("unicode path media placeholder\n", $package->read('/' . $unicodeName));
        $t->same("legacy encoded media placeholder\n", $package->read('/' . $cp437Name));
    },

    'preflights selected zip entry raw comment provenance before reader handoff' => static function (TestRunner $t) use ($buildZipPackage, $buildUnicodeExtra): void {
        $documentXml = '<w:document><w:body><w:p>selected raw comment provenance</w:p></w:body></w:document>';
        $unicodeName = 'word/media/commented-unicode.bin';
        $unicodeRawComment = 'legacy media reviewer note';
        $unicodeComment = "Unicode media \u{2603} reviewer note";
        $cp437Name = 'word/media/commented-cp437.bin';
        $cp437RawComment = "r\x82sum\x82 attachment";
        $cp437Comment = "r\u{00e9}sum\u{00e9} attachment";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => $unicodeName,
                'data' => "unicode comment media placeholder\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $unicodeRawComment,
                'centralExtra' => $buildUnicodeExtra(0x6375, $unicodeRawComment, $unicodeComment),
            ],
            [
                'name' => $cp437Name,
                'data' => "legacy comment media placeholder\n",
                'method' => 0,
                'flags' => 0,
                'comment' => $cp437RawComment,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => $unicodeName, 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => $cp437Name, 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/media/missing.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
        ], 1024);

        $t->same(3, $summary['selectedUniqueEntryCount']);
        $t->same(2, $summary['selectedCommentedEntryCount']);
        $t->same(2, $summary['selectedRawCommentProvenanceEntryCount']);
        $t->same(1, $summary['selectedLegacyEncodedCommentEntryCount']);
        $t->same(1, $summary['selectedUnicodeCommentExtraEntryCount']);
        $t->same(2, $summary['selectedDecodedCommentDiffersFromRawCommentEntryCount']);
        $t->same(3, $summary['handoffEntryCount']);
        $t->same(0, $summary['failedEntryCount']);

        $documentEntry = $summary['entries'][0];
        $t->same('', $documentEntry['comment']);
        $t->same('', $documentEntry['rawComment']);
        $t->same('', $documentEntry['rawCommentHex']);
        $t->same('utf-8', $documentEntry['commentEncoding']);
        $t->same(true, $documentEntry['rawCommentMatchesDecodedComment']);
        $t->same(false, $documentEntry['hasRawCommentProvenance']);

        $unicodeEntry = $summary['entries'][1];
        $t->same($unicodeComment, $unicodeEntry['comment']);
        $t->same($unicodeRawComment, $unicodeEntry['rawComment']);
        $t->same(bin2hex($unicodeRawComment), $unicodeEntry['rawCommentHex']);
        $t->same('info-zip-unicode-comment', $unicodeEntry['commentEncoding']);
        $t->same(false, $unicodeEntry['rawCommentMatchesDecodedComment']);
        $t->same(false, $unicodeEntry['usesLegacyCommentEncoding']);
        $t->same(true, $unicodeEntry['usesUnicodeCommentExtraField']);
        $t->same(true, $unicodeEntry['hasRawCommentProvenance']);

        $cp437Entry = $summary['entries'][2];
        $t->same($cp437Comment, $cp437Entry['comment']);
        $t->same($cp437RawComment, $cp437Entry['rawComment']);
        $t->same(bin2hex($cp437RawComment), $cp437Entry['rawCommentHex']);
        $t->same('cp437', $cp437Entry['commentEncoding']);
        $t->same(false, $cp437Entry['rawCommentMatchesDecodedComment']);
        $t->same(true, $cp437Entry['usesLegacyCommentEncoding']);
        $t->same(false, $cp437Entry['usesUnicodeCommentExtraField']);
        $t->same(true, $cp437Entry['hasRawCommentProvenance']);

        $expectedCommentedEntries = [
            [
                'name' => $unicodeName,
                'comment' => $unicodeComment,
                'rawComment' => $unicodeRawComment,
                'rawCommentHex' => bin2hex($unicodeRawComment),
                'commentEncoding' => 'info-zip-unicode-comment',
                'rawCommentMatchesDecodedComment' => false,
                'usesLegacyCommentEncoding' => false,
                'usesUnicodeCommentExtraField' => true,
                'hasRawCommentProvenance' => true,
            ],
            [
                'name' => $cp437Name,
                'comment' => $cp437Comment,
                'rawComment' => $cp437RawComment,
                'rawCommentHex' => bin2hex($cp437RawComment),
                'commentEncoding' => 'cp437',
                'rawCommentMatchesDecodedComment' => false,
                'usesLegacyCommentEncoding' => true,
                'usesUnicodeCommentExtraField' => false,
                'hasRawCommentProvenance' => true,
            ],
        ];
        $t->same($expectedCommentedEntries, $summary['selectedCommentedEntries']);
        $t->same($expectedCommentedEntries, $summary['selectedRawCommentProvenanceEntries']);

        $missingEntry = $summary['entries'][3];
        $t->same(null, $missingEntry['comment']);
        $t->same(null, $missingEntry['rawComment']);
        $t->same(null, $missingEntry['rawCommentHex']);
        $t->same(null, $missingEntry['commentEncoding']);
        $t->same(false, $missingEntry['hasRawCommentProvenance']);
        $t->same([$documentEntry, $unicodeEntry, $cp437Entry], $summary['handoffEntries']);

        $manifest = $summary['selectedHandoffManifest'];
        $t->same(2, $manifest['commentedRequestCount']);
        $t->same(2, $manifest['rawCommentProvenanceRequestCount']);
        $t->same(1, $manifest['legacyEncodedCommentRequestCount']);
        $t->same(1, $manifest['unicodeCommentExtraRequestCount']);
        $t->same(2, $manifest['decodedCommentDiffersFromRawCommentRequestCount']);
        $t->same([
            [
                'name' => 'word/document.xml',
                'rawCommentHex' => '',
                'commentEncoding' => 'utf-8',
                'rawCommentMatchesDecodedComment' => true,
                'usesLegacyCommentEncoding' => false,
                'usesUnicodeCommentExtraField' => false,
                'hasRawCommentProvenance' => false,
            ],
            [
                'name' => $unicodeName,
                'rawCommentHex' => bin2hex($unicodeRawComment),
                'commentEncoding' => 'info-zip-unicode-comment',
                'rawCommentMatchesDecodedComment' => false,
                'usesLegacyCommentEncoding' => false,
                'usesUnicodeCommentExtraField' => true,
                'hasRawCommentProvenance' => true,
            ],
            [
                'name' => $cp437Name,
                'rawCommentHex' => bin2hex($cp437RawComment),
                'commentEncoding' => 'cp437',
                'rawCommentMatchesDecodedComment' => false,
                'usesLegacyCommentEncoding' => true,
                'usesUnicodeCommentExtraField' => false,
                'hasRawCommentProvenance' => true,
            ],
            [
                'name' => 'word/media/missing.bin',
                'rawCommentHex' => null,
                'commentEncoding' => null,
                'rawCommentMatchesDecodedComment' => null,
                'usesLegacyCommentEncoding' => false,
                'usesUnicodeCommentExtraField' => false,
                'hasRawCommentProvenance' => false,
            ],
        ], array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'rawCommentHex' => $entry['rawCommentHex'],
                'commentEncoding' => $entry['commentEncoding'],
                'rawCommentMatchesDecodedComment' => $entry['rawCommentMatchesDecodedComment'],
                'usesLegacyCommentEncoding' => $entry['usesLegacyCommentEncoding'],
                'usesUnicodeCommentExtraField' => $entry['usesUnicodeCommentExtraField'],
                'hasRawCommentProvenance' => $entry['hasRawCommentProvenance'],
            ],
            $manifest['entries']
        ));
    },

    'preflights selected zip entry extra field provenance before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>selected extra field provenance</w:p></w:body></w:document>';
        $documentExtra = pack('vva*', 0xcafe, strlen('document-extra'), 'document-extra');
        $localOnlyExtra = pack('vva*', 0xbeef, strlen('local-only'), 'local-only');
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'localExtra' => $documentExtra,
                'centralExtra' => $documentExtra,
            ],
            [
                'name' => 'word/media/local-extra.bin',
                'data' => "local extra field media placeholder\n",
                'method' => 0,
                'localExtra' => $localOnlyExtra,
                'centralExtra' => '',
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/media/local-extra.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/media/missing.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
        ], 1024);

        $t->same(2, $summary['selectedUniqueEntryCount']);
        $t->same(2, $summary['selectedExtraFieldEntryCount']);
        $t->same(1, $summary['selectedCentralExtraFieldEntryCount']);
        $t->same(2, $summary['selectedLocalExtraFieldEntryCount']);
        $t->same(3, $summary['selectedExtraFieldRecordCount']);
        $t->same(1, $summary['selectedCentralExtraFieldRecordCount']);
        $t->same(2, $summary['selectedLocalExtraFieldRecordCount']);
        $t->same(2, $summary['handoffEntryCount']);
        $t->same(0, $summary['failedEntryCount']);

        $documentEntry = $summary['entries'][0];
        $t->same('word/document.xml', $documentEntry['name']);
        $t->same(strlen($documentExtra), $documentEntry['centralExtraFieldLength']);
        $t->same(1, $documentEntry['centralExtraFieldRecordCount']);
        $t->same([0xcafe], $documentEntry['centralExtraFieldIds']);
        $t->same(['0xcafe'], $documentEntry['centralExtraFieldIdHexes']);
        $t->same(true, $documentEntry['hasCentralExtraFields']);
        $t->same(strlen($documentExtra), $documentEntry['localExtraFieldLength']);
        $t->same(1, $documentEntry['localExtraFieldRecordCount']);
        $t->same([0xcafe], $documentEntry['localExtraFieldIds']);
        $t->same(['0xcafe'], $documentEntry['localExtraFieldIdHexes']);
        $t->same(true, $documentEntry['hasLocalExtraFields']);
        $t->same(true, $documentEntry['centralLocalExtraFieldIdsMatch']);
        $t->same(true, $documentEntry['hasExtraFieldProvenance']);

        $localOnlyEntry = $summary['entries'][1];
        $t->same('word/media/local-extra.bin', $localOnlyEntry['name']);
        $t->same(0, $localOnlyEntry['centralExtraFieldLength']);
        $t->same(0, $localOnlyEntry['centralExtraFieldRecordCount']);
        $t->same([], $localOnlyEntry['centralExtraFieldIds']);
        $t->same([], $localOnlyEntry['centralExtraFieldIdHexes']);
        $t->same(false, $localOnlyEntry['hasCentralExtraFields']);
        $t->same(strlen($localOnlyExtra), $localOnlyEntry['localExtraFieldLength']);
        $t->same(1, $localOnlyEntry['localExtraFieldRecordCount']);
        $t->same([0xbeef], $localOnlyEntry['localExtraFieldIds']);
        $t->same(['0xbeef'], $localOnlyEntry['localExtraFieldIdHexes']);
        $t->same(true, $localOnlyEntry['hasLocalExtraFields']);
        $t->same(false, $localOnlyEntry['centralLocalExtraFieldIdsMatch']);
        $t->same(true, $localOnlyEntry['hasExtraFieldProvenance']);

        $expectedExtraFieldEntries = [
            [
                'name' => 'word/document.xml',
                'centralExtraFieldLength' => strlen($documentExtra),
                'centralExtraFieldRecordCount' => 1,
                'centralExtraFieldIds' => [0xcafe],
                'centralExtraFieldIdHexes' => ['0xcafe'],
                'hasCentralExtraFields' => true,
                'localExtraFieldLength' => strlen($documentExtra),
                'localExtraFieldRecordCount' => 1,
                'localExtraFieldIds' => [0xcafe],
                'localExtraFieldIdHexes' => ['0xcafe'],
                'hasLocalExtraFields' => true,
                'centralLocalExtraFieldIdsMatch' => true,
                'hasExtraFieldProvenance' => true,
            ],
            [
                'name' => 'word/media/local-extra.bin',
                'centralExtraFieldLength' => 0,
                'centralExtraFieldRecordCount' => 0,
                'centralExtraFieldIds' => [],
                'centralExtraFieldIdHexes' => [],
                'hasCentralExtraFields' => false,
                'localExtraFieldLength' => strlen($localOnlyExtra),
                'localExtraFieldRecordCount' => 1,
                'localExtraFieldIds' => [0xbeef],
                'localExtraFieldIdHexes' => ['0xbeef'],
                'hasLocalExtraFields' => true,
                'centralLocalExtraFieldIdsMatch' => false,
                'hasExtraFieldProvenance' => true,
            ],
        ];
        $t->same($expectedExtraFieldEntries, $summary['selectedExtraFieldProvenanceEntries']);

        $missingEntry = $summary['entries'][2];
        $t->same(false, $missingEntry['exists']);
        $t->same(null, $missingEntry['centralExtraFieldLength']);
        $t->same(0, $missingEntry['centralExtraFieldRecordCount']);
        $t->same([], $missingEntry['centralExtraFieldIds']);
        $t->same([], $missingEntry['centralExtraFieldIdHexes']);
        $t->same(false, $missingEntry['hasCentralExtraFields']);
        $t->same(null, $missingEntry['localExtraFieldLength']);
        $t->same(0, $missingEntry['localExtraFieldRecordCount']);
        $t->same([], $missingEntry['localExtraFieldIds']);
        $t->same([], $missingEntry['localExtraFieldIdHexes']);
        $t->same(false, $missingEntry['hasLocalExtraFields']);
        $t->same(null, $missingEntry['centralLocalExtraFieldIdsMatch']);
        $t->same(false, $missingEntry['hasExtraFieldProvenance']);
        $t->same([$documentEntry, $localOnlyEntry], $summary['handoffEntries']);
    },

    'preflights selected zip entry platform attribute provenance before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>selected platform attributes</w:p></w:body></w:document>';
        $macroBytes = "selected executable sidecar bytes\n";
        $commentsXml = '<w:comments><w:comment>internal attribute sidecar</w:comment></w:comments>';
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
                'externalAttributes' => 0x81a40020,
            ],
            [
                'name' => 'word/vbaProject.bin',
                'data' => $macroBytes,
                'method' => 0,
                'externalAttributes' => 0x81ed0002,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'internalAttributes' => 0x0001,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => 'word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/vbaProject.bin', 'required' => false, 'kind' => 'file', 'role' => 'package-sidecar'],
            ['name' => 'word/comments.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
            ['name' => 'word/missing.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
        ], 1024);

        $t->same(4, $summary['requestedEntryCount']);
        $t->same(3, $summary['selectedUniqueEntryCount']);
        $t->same(3, $summary['selectedPlatformAttributeProvenanceEntryCount']);
        $t->same(2, $summary['selectedExternalAttributeEntryCount']);
        $t->same(1, $summary['selectedInternalAttributeEntryCount']);
        $t->same(2, $summary['selectedDosAttributeEntryCount']);
        $t->same(2, $summary['selectedUnixModeEntryCount']);
        $t->same(1, $summary['selectedExecutableFileEntryCount']);
        $t->same(0, $summary['selectedWritablePermissionEntryCount']);
        $t->same(2, $summary['selectedPlatformAttributeIssueEntryCount']);
        $t->same([
            'dos-hidden-attribute',
            'unix-executable-file',
            'internal-text-attribute',
        ], $summary['selectedPlatformAttributeIssues']);
        $t->same(3, $summary['handoffEntryCount']);
        $t->same(0, $summary['failedEntryCount']);

        $documentEntry = $summary['entries'][0];
        $macroEntry = $summary['entries'][1];
        $commentsEntry = $summary['entries'][2];
        $missingEntry = $summary['entries'][3];

        $t->same(3, $documentEntry['madeByHostSystem']);
        $t->same('unix', $documentEntry['madeByHostSystemName']);
        $t->same(20, $documentEntry['madeByVersion']);
        $t->same(0x0314, $documentEntry['versionMadeBy']);
        $t->same(true, $documentEntry['creatorVersionMeetsNeeded']);
        $t->same(0x81a40020, $documentEntry['externalAttributes']);
        $t->same('81a40020', $documentEntry['externalAttributesHex']);
        $t->same(true, $documentEntry['hasExternalAttributes']);
        $t->same(0x20, $documentEntry['dosAttributes']);
        $t->same(['archive'], $documentEntry['dosAttributeNames']);
        $t->same(true, $documentEntry['hasDosAttributes']);
        $t->same(0x81a4, $documentEntry['unixMode']);
        $t->same('100644', $documentEntry['unixModeOctal']);
        $t->same(0644, $documentEntry['unixPermissions']);
        $t->same('0644', $documentEntry['unixPermissionsOctal']);
        $t->same('regular-file', $documentEntry['unixFileTypeName']);
        $t->same(false, $documentEntry['isUnixExecutableFile']);
        $t->same(false, $documentEntry['hasWritablePermissions']);
        $t->same(true, $documentEntry['hasPlatformAttributeProvenance']);
        $t->same([], $documentEntry['platformAttributeIssues']);

        $t->same(0x81ed0002, $macroEntry['externalAttributes']);
        $t->same('81ed0002', $macroEntry['externalAttributesHex']);
        $t->same(0x02, $macroEntry['dosAttributes']);
        $t->same(['hidden'], $macroEntry['dosAttributeNames']);
        $t->same(true, $macroEntry['hasDosHiddenAttribute']);
        $t->same(0x81ed, $macroEntry['unixMode']);
        $t->same('100755', $macroEntry['unixModeOctal']);
        $t->same(0755, $macroEntry['unixPermissions']);
        $t->same('0755', $macroEntry['unixPermissionsOctal']);
        $t->same(true, $macroEntry['isUnixExecutableFile']);
        $t->same(['dos-hidden-attribute', 'unix-executable-file'], $macroEntry['platformAttributeIssues']);
        $t->same($macroBytes, $package->read('word/vbaProject.bin'));

        $t->same(false, $commentsEntry['hasExternalAttributes']);
        $t->same(0, $commentsEntry['externalAttributes']);
        $t->same(null, $commentsEntry['unixMode']);
        $t->same(0x0001, $commentsEntry['internalFileAttributes']);
        $t->same('0001', $commentsEntry['internalFileAttributesHex']);
        $t->same(['apparently-text'], $commentsEntry['internalAttributeNames']);
        $t->same(true, $commentsEntry['hasInternalFileAttributes']);
        $t->same(true, $commentsEntry['hasTextInternalAttribute']);
        $t->same(['internal-text-attribute'], $commentsEntry['platformAttributeIssues']);
        $t->same(hash('sha256', $commentsXml), $commentsEntry['contentSha256']);

        $t->same(false, $missingEntry['exists']);
        $t->same(null, $missingEntry['externalAttributes']);
        $t->same(null, $missingEntry['internalFileAttributes']);
        $t->same(null, $missingEntry['madeByHostSystem']);
        $t->same(false, $missingEntry['hasPlatformAttributeProvenance']);
        $t->same([], $missingEntry['platformAttributeIssues']);

        $t->same([
            [
                'name' => 'word/document.xml',
                'externalAttributes' => 0x81a40020,
                'dosAttributeNames' => ['archive'],
                'unixMode' => 0x81a4,
                'internalFileAttributes' => 0,
                'platformAttributeIssues' => [],
            ],
            [
                'name' => 'word/vbaProject.bin',
                'externalAttributes' => 0x81ed0002,
                'dosAttributeNames' => ['hidden'],
                'unixMode' => 0x81ed,
                'internalFileAttributes' => 0,
                'platformAttributeIssues' => ['dos-hidden-attribute', 'unix-executable-file'],
            ],
            [
                'name' => 'word/comments.xml',
                'externalAttributes' => 0,
                'dosAttributeNames' => [],
                'unixMode' => null,
                'internalFileAttributes' => 0x0001,
                'platformAttributeIssues' => ['internal-text-attribute'],
            ],
        ], array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'externalAttributes' => $entry['externalAttributes'],
                'dosAttributeNames' => $entry['dosAttributeNames'],
                'unixMode' => $entry['unixMode'],
                'internalFileAttributes' => $entry['internalFileAttributes'],
                'platformAttributeIssues' => $entry['platformAttributeIssues'],
            ],
            $summary['selectedPlatformAttributeProvenanceEntries']
        ));

        $t->same([
            'word/vbaProject.bin',
            'word/comments.xml',
        ], array_column($summary['selectedPlatformAttributeIssueEntries'], 'name'));
        $t->same([$documentEntry, $macroEntry, $commentsEntry], $summary['handoffEntries']);
    },

    'preflights selected zip entry local fixed header provenance before reader handoff' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $documentXml = '<w:document><w:body><w:p>selected fixed header provenance</w:p></w:body></w:document>';
        $commentsXml = '<w:comments><w:comment>fixed header descriptor placeholders</w:comment></w:comments>';
        $commentsExtra = pack('vva*', 0xcafe, strlen('comments-local'), 'comments-local');
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
                'modifiedTime' => 0x1234,
                'modifiedDate' => 0x5678,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
                'localExtra' => $commentsExtra,
                'centralExtra' => $commentsExtra,
                'modifiedTime' => 0x2222,
                'modifiedDate' => 0x4444,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => 'word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/comments.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
            ['name' => 'word/missing.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
        ], 1024);

        $t->same(2, $summary['selectedUniqueEntryCount']);
        $t->same(2, $summary['selectedLocalHeaderFixedFieldEntryCount']);
        $t->same(0, $summary['selectedLocalHeaderFixedFieldIssueEntryCount']);
        $t->same([], $summary['selectedLocalHeaderFixedFieldIssueEntries']);

        $documentEntry = $summary['entries'][0];
        $commentsEntry = $summary['entries'][1];
        $missingEntry = $summary['entries'][2];
        $documentFixed = $summary['selectedLocalHeaderFixedFieldEntries'][0];
        $commentsFixed = $summary['selectedLocalHeaderFixedFieldEntries'][1];

        $t->same('word/document.xml', $documentFixed['name']);
        $t->same(0, $documentFixed['localFixedHeaderOffset']);
        $t->same(30, $documentFixed['localFixedHeaderLength']);
        $t->same(30, $documentFixed['localFixedHeaderEnd']);
        $t->same(0, $documentFixed['localSignatureOffset']);
        $t->same(4, $documentFixed['localSignatureLength']);
        $t->same(4, $documentFixed['localVersionNeededToExtractOffset']);
        $t->same(6, $documentFixed['localGeneralPurposeFlagsOffset']);
        $t->same(8, $documentFixed['localCompressionMethodOffset']);
        $t->same(10, $documentFixed['localModifiedDosTimeOffset']);
        $t->same(12, $documentFixed['localModifiedDosDateOffset']);
        $t->same(14, $documentFixed['localCrc32Offset']);
        $t->same(18, $documentFixed['localCompressedSizeOffset']);
        $t->same(22, $documentFixed['localUncompressedSizeOffset']);
        $t->same(26, $documentFixed['localNameLengthOffset']);
        $t->same(28, $documentFixed['localExtraFieldLengthOffset']);
        $t->same(20, $documentFixed['centralVersionNeededToExtract']);
        $t->same(20, $documentFixed['localVersionNeededToExtract']);
        $t->same(0x0800, $documentFixed['centralGeneralPurposeFlags']);
        $t->same(0x0800, $documentFixed['localGeneralPurposeFlags']);
        $t->same(0, $documentFixed['centralCompressionMethod']);
        $t->same(0, $documentFixed['localCompressionMethod']);
        $t->same(0x1234, $documentFixed['centralModifiedDosTime']);
        $t->same(0x1234, $documentFixed['localModifiedDosTime']);
        $t->same(0x5678, $documentFixed['centralModifiedDosDate']);
        $t->same(0x5678, $documentFixed['localModifiedDosDate']);
        $t->same($crc32($documentXml), $documentFixed['centralCrc32']);
        $t->same(sprintf('%08x', $crc32($documentXml)), $documentFixed['centralCrc32Hex']);
        $t->same($crc32($documentXml), $documentFixed['localFixedHeaderCrc32']);
        $t->same(sprintf('%08x', $crc32($documentXml)), $documentFixed['localFixedHeaderCrc32Hex']);
        $t->same(strlen($documentXml), $documentFixed['centralCompressedSize']);
        $t->same(strlen($documentXml), $documentFixed['localFixedHeaderCompressedSize']);
        $t->same(strlen($documentXml), $documentFixed['centralUncompressedSize']);
        $t->same(strlen($documentXml), $documentFixed['localFixedHeaderUncompressedSize']);
        $t->same(strlen('word/document.xml'), $documentFixed['localFixedHeaderNameLength']);
        $t->same(0, $documentFixed['localFixedHeaderExtraFieldLength']);
        $t->same(null, $documentFixed['localFixedHeaderHasZeroDataDescriptorPlaceholders']);
        $t->same(true, $documentFixed['localHeaderFixedFieldsMatchCentralDirectory']);
        $t->same([], $documentFixed['localHeaderFixedFieldIssues']);

        $commentsCompressedSize = strlen(gzdeflate($commentsXml));
        $t->same('word/comments.xml', $commentsFixed['name']);
        $t->same($commentsEntry['localHeaderOffset'], $commentsFixed['localFixedHeaderOffset']);
        $t->same($commentsEntry['localHeaderOffset'] + 30, $commentsFixed['localFixedHeaderEnd']);
        $t->same($commentsEntry['localHeaderOffset'] + 14, $commentsFixed['localCrc32Offset']);
        $t->same(8, $commentsFixed['centralCompressionMethod']);
        $t->same(8, $commentsFixed['localCompressionMethod']);
        $t->same(0x2222, $commentsFixed['centralModifiedDosTime']);
        $t->same(0x2222, $commentsFixed['localModifiedDosTime']);
        $t->same(0x4444, $commentsFixed['centralModifiedDosDate']);
        $t->same(0x4444, $commentsFixed['localModifiedDosDate']);
        $t->same($crc32($commentsXml), $commentsFixed['centralCrc32']);
        $t->same(0, $commentsFixed['localFixedHeaderCrc32']);
        $t->same('00000000', $commentsFixed['localFixedHeaderCrc32Hex']);
        $t->same($commentsCompressedSize, $commentsFixed['centralCompressedSize']);
        $t->same(0, $commentsFixed['localFixedHeaderCompressedSize']);
        $t->same(strlen($commentsXml), $commentsFixed['centralUncompressedSize']);
        $t->same(0, $commentsFixed['localFixedHeaderUncompressedSize']);
        $t->same(strlen('word/comments.xml'), $commentsFixed['localFixedHeaderNameLength']);
        $t->same(strlen($commentsExtra), $commentsFixed['localFixedHeaderExtraFieldLength']);
        $t->same(true, $commentsFixed['localFixedHeaderHasZeroDataDescriptorPlaceholders']);
        $t->same(true, $commentsFixed['localHeaderFixedFieldsMatchCentralDirectory']);
        $t->same([], $commentsFixed['localHeaderFixedFieldIssues']);

        $t->same($documentFixed['localVersionNeededToExtract'], $documentEntry['localVersionNeededToExtract']);
        $t->same($documentFixed['localCrc32Offset'], $documentEntry['localCrc32Offset']);
        $t->same($commentsFixed['localFixedHeaderCompressedSize'], $commentsEntry['localFixedHeaderCompressedSize']);
        $t->same($commentsFixed['localFixedHeaderHasZeroDataDescriptorPlaceholders'], $commentsEntry['localFixedHeaderHasZeroDataDescriptorPlaceholders']);
        $t->same(null, $missingEntry['localVersionNeededToExtract']);
        $t->same(null, $missingEntry['localFixedHeaderOffset']);
        $t->same([], $missingEntry['localHeaderFixedFieldIssues']);
        $t->same([$documentEntry, $commentsEntry], $summary['handoffEntries']);
    },

    'preflights selected zip entry data descriptor provenance before reader handoff' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $documentXml = '<w:document><w:body><w:p>selected descriptor provenance</w:p></w:body></w:document>';
        $commentsXml = '<w:comments><w:comment>signed descriptor</w:comment></w:comments>';
        $footnotesXml = '<w:footnotes><w:footnote>unsigned descriptor</w:footnote></w:footnotes>';
        $commentsCompressedSize = strlen(gzdeflate($commentsXml));
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => $footnotesXml,
                'method' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => 'word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/comments.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
            ['name' => 'word/footnotes.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
            ['name' => 'word/missing.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
        ], 1024);

        $t->same(4, $summary['requestedEntryCount']);
        $t->same(3, $summary['selectedUniqueEntryCount']);
        $t->same(2, $summary['selectedDataDescriptorEntryCount']);
        $t->same(1, $summary['selectedSignedDataDescriptorEntryCount']);
        $t->same(1, $summary['selectedUnsignedDataDescriptorEntryCount']);
        $t->same(0, $summary['selectedZip64SizedDataDescriptorEntryCount']);
        $t->same(2, $summary['selectedZeroLocalHeaderPlaceholderEntryCount']);
        $t->same(3, $summary['handoffEntryCount']);
        $t->same(0, $summary['failedEntryCount']);
        $t->same(true, $summary['isSupportedByBoundedReader']);

        $documentEntry = $summary['entries'][0];
        $commentsEntry = $summary['entries'][1];
        $footnotesEntry = $summary['entries'][2];
        $missingEntry = $summary['entries'][3];

        $t->same(false, $documentEntry['usesDataDescriptor']);
        $t->same(null, $documentEntry['dataDescriptorOffset']);
        $t->same(null, $documentEntry['dataDescriptorLength']);
        $t->same(null, $documentEntry['hasZeroLocalHeaderPlaceholders']);
        $t->same($crc32($documentXml), $documentEntry['localHeaderCrc32']);
        $t->same(strlen($documentXml), $documentEntry['localHeaderCompressedSize']);
        $t->same(strlen($documentXml), $documentEntry['localHeaderUncompressedSize']);

        $t->same(true, $commentsEntry['usesDataDescriptor']);
        $t->same(true, $commentsEntry['dataDescriptorHasSignature']);
        $t->same($commentsEntry['compressedDataEnd'], $commentsEntry['dataDescriptorOffset']);
        $t->same($commentsEntry['compressedDataEnd'] + 4, $commentsEntry['dataDescriptorValueOffset']);
        $t->same(16, $commentsEntry['dataDescriptorLength']);
        $t->same($footnotesEntry['localHeaderOffset'], $commentsEntry['dataDescriptorNextOffset']);
        $t->same(16, $commentsEntry['dataDescriptorSpan']);
        $t->same($footnotesEntry['localHeaderOffset'], $commentsEntry['dataDescriptorEnd']);
        $t->same(0, $commentsEntry['dataDescriptorSurplusBytes']);
        $t->same(0, $commentsEntry['dataDescriptorTruncatedBytes']);
        $t->same($crc32($commentsXml), $commentsEntry['dataDescriptorCrc32']);
        $t->same(sprintf('%08x', $crc32($commentsXml)), $commentsEntry['dataDescriptorCrc32Hex']);
        $t->same($commentsCompressedSize, $commentsEntry['dataDescriptorCompressedSize']);
        $t->same(strlen($commentsXml), $commentsEntry['dataDescriptorUncompressedSize']);
        $t->same(false, $commentsEntry['dataDescriptorUsesZip64SizedFields']);
        $t->same(0, $commentsEntry['localHeaderCrc32']);
        $t->same(0, $commentsEntry['localHeaderCompressedSize']);
        $t->same(0, $commentsEntry['localHeaderUncompressedSize']);
        $t->same(true, $commentsEntry['hasZeroLocalHeaderPlaceholders']);
        $t->same(hash('sha256', $commentsXml), $commentsEntry['contentSha256']);

        $t->same(true, $footnotesEntry['usesDataDescriptor']);
        $t->same(false, $footnotesEntry['dataDescriptorHasSignature']);
        $t->same($footnotesEntry['compressedDataEnd'], $footnotesEntry['dataDescriptorOffset']);
        $t->same($footnotesEntry['compressedDataEnd'], $footnotesEntry['dataDescriptorValueOffset']);
        $t->same(12, $footnotesEntry['dataDescriptorLength']);
        $t->same($documentEntry['centralDirectoryRecordOffset'], $footnotesEntry['dataDescriptorNextOffset']);
        $t->same(12, $footnotesEntry['dataDescriptorSpan']);
        $t->same($documentEntry['centralDirectoryRecordOffset'], $footnotesEntry['dataDescriptorEnd']);
        $t->same($crc32($footnotesXml), $footnotesEntry['dataDescriptorCrc32']);
        $t->same(strlen($footnotesXml), $footnotesEntry['dataDescriptorCompressedSize']);
        $t->same(strlen($footnotesXml), $footnotesEntry['dataDescriptorUncompressedSize']);
        $t->same(true, $footnotesEntry['hasZeroLocalHeaderPlaceholders']);
        $t->same(hash('sha256', $footnotesXml), $footnotesEntry['contentSha256']);

        $expectedDescriptorEntries = [
            [
                'name' => 'word/comments.xml',
                'usesDataDescriptor' => true,
                'dataDescriptorHasSignature' => true,
                'dataDescriptorOffset' => $commentsEntry['compressedDataEnd'],
                'dataDescriptorValueOffset' => $commentsEntry['compressedDataEnd'] + 4,
                'dataDescriptorLength' => 16,
                'dataDescriptorNextOffset' => $footnotesEntry['localHeaderOffset'],
                'dataDescriptorSpan' => 16,
                'dataDescriptorEnd' => $footnotesEntry['localHeaderOffset'],
                'dataDescriptorSurplusBytes' => 0,
                'dataDescriptorTruncatedBytes' => 0,
                'dataDescriptorCrc32' => $crc32($commentsXml),
                'dataDescriptorCrc32Hex' => sprintf('%08x', $crc32($commentsXml)),
                'dataDescriptorCompressedSize' => $commentsCompressedSize,
                'dataDescriptorUncompressedSize' => strlen($commentsXml),
                'dataDescriptorUsesZip64SizedFields' => false,
                'dataDescriptorValuesMatchCentral' => true,
                'dataDescriptorIssues' => [],
                'localHeaderCrc32' => 0,
                'localHeaderCompressedSize' => 0,
                'localHeaderUncompressedSize' => 0,
                'hasZeroLocalHeaderPlaceholders' => true,
            ],
            [
                'name' => 'word/footnotes.xml',
                'usesDataDescriptor' => true,
                'dataDescriptorHasSignature' => false,
                'dataDescriptorOffset' => $footnotesEntry['compressedDataEnd'],
                'dataDescriptorValueOffset' => $footnotesEntry['compressedDataEnd'],
                'dataDescriptorLength' => 12,
                'dataDescriptorNextOffset' => $documentEntry['centralDirectoryRecordOffset'],
                'dataDescriptorSpan' => 12,
                'dataDescriptorEnd' => $documentEntry['centralDirectoryRecordOffset'],
                'dataDescriptorSurplusBytes' => 0,
                'dataDescriptorTruncatedBytes' => 0,
                'dataDescriptorCrc32' => $crc32($footnotesXml),
                'dataDescriptorCrc32Hex' => sprintf('%08x', $crc32($footnotesXml)),
                'dataDescriptorCompressedSize' => strlen($footnotesXml),
                'dataDescriptorUncompressedSize' => strlen($footnotesXml),
                'dataDescriptorUsesZip64SizedFields' => false,
                'dataDescriptorValuesMatchCentral' => true,
                'dataDescriptorIssues' => [],
                'localHeaderCrc32' => 0,
                'localHeaderCompressedSize' => 0,
                'localHeaderUncompressedSize' => 0,
                'hasZeroLocalHeaderPlaceholders' => true,
            ],
        ];
        $t->same($expectedDescriptorEntries, $summary['selectedDataDescriptorProvenanceEntries']);

        $t->same(false, $missingEntry['exists']);
        $t->same(false, $missingEntry['usesDataDescriptor']);
        $t->same(null, $missingEntry['dataDescriptorOffset']);
        $t->same(null, $missingEntry['localHeaderCrc32']);
        $t->same(null, $missingEntry['hasZeroLocalHeaderPlaceholders']);
        $t->same([$documentEntry, $commentsEntry, $footnotesEntry], $summary['handoffEntries']);
    },

    'summarizes selected zip data descriptor review issues before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $commentsXml = '<w:comments><w:comment>descriptor issue rollup</w:comment></w:comments>';
        $footnotesXml = '<w:footnotes><w:footnote>descriptor issue rollup</w:footnote></w:footnotes>';
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>descriptor issue-free handoff</w:p></w:body></w:document>',
                'method' => 0,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
            ],
            [
                'name' => 'word/footnotes.xml',
                'data' => $footnotesXml,
                'method' => 0,
                'descriptor' => true,
                'descriptorSignature' => false,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => 'word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/comments.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
            ['name' => 'word/footnotes.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
            ['name' => 'word/missing.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
        ], 1024);

        $t->same(2, $summary['selectedDataDescriptorEntryCount']);
        $t->same(2, $summary['selectedDataDescriptorValuesMatchCentralEntryCount']);
        $t->same(0, $summary['selectedDataDescriptorIssueEntryCount']);
        $t->same([], $summary['selectedDataDescriptorIssues']);
        $t->same([], $summary['selectedDataDescriptorIssueEntries']);

        $commentsEntry = $summary['entries'][1];
        $footnotesEntry = $summary['entries'][2];
        $documentEntry = $summary['entries'][0];
        $missingEntry = $summary['entries'][3];

        $t->same(null, $documentEntry['dataDescriptorValuesMatchCentral']);
        $t->same([], $documentEntry['dataDescriptorIssues']);
        $t->same(true, $commentsEntry['dataDescriptorValuesMatchCentral']);
        $t->same([], $commentsEntry['dataDescriptorIssues']);
        $t->same(true, $footnotesEntry['dataDescriptorValuesMatchCentral']);
        $t->same([], $footnotesEntry['dataDescriptorIssues']);
        $t->same(null, $missingEntry['dataDescriptorValuesMatchCentral']);
        $t->same([], $missingEntry['dataDescriptorIssues']);

        $t->same([
            [
                'name' => 'word/comments.xml',
                'dataDescriptorValuesMatchCentral' => true,
                'dataDescriptorIssues' => [],
            ],
            [
                'name' => 'word/footnotes.xml',
                'dataDescriptorValuesMatchCentral' => true,
                'dataDescriptorIssues' => [],
            ],
        ], array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'dataDescriptorValuesMatchCentral' => $entry['dataDescriptorValuesMatchCentral'],
                'dataDescriptorIssues' => $entry['dataDescriptorIssues'],
            ],
            $summary['selectedDataDescriptorProvenanceEntries']
        ));
    },
    'summarizes selected zip source byte spans before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>selected source spans</w:p></w:body></w:document>';
        $commentsXml = '<w:comments><w:comment>descriptor source span</w:comment></w:comments>';
        $commentsExtra = pack('vva*', 0xb0b0, strlen('comments-source-span'), 'comments-source-span');
        $documentComment = 'document source record';
        $commentsComment = 'comments descriptor source record';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
                'comment' => $documentComment,
            ],
            [
                'name' => 'word/comments.xml',
                'data' => $commentsXml,
                'method' => 8,
                'descriptor' => true,
                'descriptorSignature' => true,
                'localExtra' => $commentsExtra,
                'centralExtra' => $commentsExtra,
                'comment' => $commentsComment,
            ],
        ], 'source-span-review');
        $package = ZipPackage::fromString($zip);
        $commentsCompressed = gzdeflate($commentsXml);
        if ($commentsCompressed === false) {
            throw new RuntimeException('Unable to deflate comments fixture');
        }

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/comments.xml', 'required' => false, 'kind' => 'file', 'role' => 'review-sidecar'],
        ], 2048);
        $sourceSpansByName = [];
        foreach ($summary['selectedSourceByteSpanEntries'] as $sourceSpanEntry) {
            $sourceSpansByName[$sourceSpanEntry['name']] = $sourceSpanEntry;
        }
        $sourceBucketsByName = [];
        foreach ($summary['selectedSourceByteSpanBuckets'] as $sourceBucket) {
            $sourceBucketsByName[$sourceBucket['bucket']] = $sourceBucket;
        }

        $documentSpan = $sourceSpansByName['word/document.xml'];
        $commentsSpan = $sourceSpansByName['word/comments.xml'];
        $documentEntry = $summary['entries'][0];
        $commentsEntry = $summary['entries'][1];
        $layout = ZipPackage::packageByteLayoutPreflight($zip);
        $archiveTrailer = $summary['selectedSourceArchiveTrailer'];

        $t->same(2, $summary['selectedSourceByteSpanEntryCount']);
        $t->same(0, $summary['selectedSourceByteSpanIssueCount']);
        $t->same([], $summary['selectedSourceByteSpanIssues']);
        $t->same(12, $summary['selectedSourceByteSpanBucketCount']);
        $t->same([
            'source-record',
            'local-record',
            'local-header',
            'local-fixed-header',
            'local-header-variable-fields',
            'local-review-fields',
            'compressed-data',
            'data-descriptor',
            'central-directory-record',
            'central-directory-fixed-header',
            'central-directory-variable-fields',
            'central-directory-review-fields',
        ], array_column($summary['selectedSourceByteSpanBuckets'], 'bucket'));
        $t->same(
            $documentSpan['localRecordBytes'] + $commentsSpan['localRecordBytes'],
            $summary['selectedSourceLocalRecordBytes']
        );
        $t->same(
            $documentSpan['localHeaderBytes'] + $commentsSpan['localHeaderBytes'],
            $summary['selectedSourceLocalHeaderBytes']
        );
        $t->same(strlen($documentXml) + strlen($commentsCompressed), $summary['selectedSourceCompressedDataBytes']);
        $t->same(16, $summary['selectedSourceDataDescriptorBytes']);
        $t->same(
            $documentSpan['centralDirectoryRecordBytes'] + $commentsSpan['centralDirectoryRecordBytes'],
            $summary['selectedSourceCentralDirectoryRecordBytes']
        );
        $t->same(92, $summary['selectedSourceCentralDirectoryFixedHeaderBytes']);
        $t->same(
            $documentSpan['centralDirectoryVariableFieldBytes'] + $commentsSpan['centralDirectoryVariableFieldBytes'],
            $summary['selectedSourceCentralDirectoryVariableFieldBytes']
        );
        $t->same(
            strlen('word/document.xml') + strlen('word/comments.xml'),
            $summary['selectedSourceCentralDirectoryRawNameBytes']
        );
        $t->same(strlen($commentsExtra), $summary['selectedSourceCentralDirectoryExtraFieldBytes']);
        $t->same(strlen($documentComment) + strlen($commentsComment), $summary['selectedSourceCentralDirectoryRawCommentBytes']);
        $t->same(
            strlen($commentsExtra) + strlen($documentComment) + strlen($commentsComment),
            $summary['selectedSourceCentralDirectoryReviewFieldBytes']
        );
        $t->same(
            $documentSpan['sourceRecordBytes'] + $commentsSpan['sourceRecordBytes'],
            $summary['selectedSourceTotalRecordBytes']
        );
        $t->same($summary['selectedSourceTotalRecordBytes'], $sourceBucketsByName['source-record']['bytes']);
        $t->same($summary['selectedSourceLocalRecordBytes'], $sourceBucketsByName['local-record']['bytes']);
        $t->same($summary['selectedSourceLocalHeaderBytes'], $sourceBucketsByName['local-header']['bytes']);
        $t->same($summary['selectedSourceLocalFixedHeaderBytes'], $sourceBucketsByName['local-fixed-header']['bytes']);
        $t->same($summary['selectedSourceLocalHeaderVariableFieldBytes'], $sourceBucketsByName['local-header-variable-fields']['bytes']);
        $t->same($summary['selectedSourceLocalReviewFieldBytes'], $sourceBucketsByName['local-review-fields']['bytes']);
        $t->same($summary['selectedSourceCompressedDataBytes'], $sourceBucketsByName['compressed-data']['bytes']);
        $t->same($summary['selectedSourceDataDescriptorBytes'], $sourceBucketsByName['data-descriptor']['bytes']);
        $t->same($summary['selectedSourceCentralDirectoryRecordBytes'], $sourceBucketsByName['central-directory-record']['bytes']);
        $t->same($summary['selectedSourceCentralDirectoryFixedHeaderBytes'], $sourceBucketsByName['central-directory-fixed-header']['bytes']);
        $t->same($summary['selectedSourceCentralDirectoryVariableFieldBytes'], $sourceBucketsByName['central-directory-variable-fields']['bytes']);
        $t->same($summary['selectedSourceCentralDirectoryReviewFieldBytes'], $sourceBucketsByName['central-directory-review-fields']['bytes']);
        $t->same(2, $sourceBucketsByName['source-record']['entryCount']);
        $t->same(2, $sourceBucketsByName['source-record']['nonZeroEntryCount']);
        $t->same(0, $sourceBucketsByName['source-record']['zeroByteEntryCount']);
        $t->same(['word/document.xml', 'word/comments.xml'], $sourceBucketsByName['source-record']['entryNames']);
        $t->same(2, $sourceBucketsByName['data-descriptor']['entryCount']);
        $t->same(1, $sourceBucketsByName['data-descriptor']['nonZeroEntryCount']);
        $t->same(1, $sourceBucketsByName['data-descriptor']['zeroByteEntryCount']);
        $t->same(['word/comments.xml'], $sourceBucketsByName['data-descriptor']['nonZeroEntryNames']);
        $t->same(1, $sourceBucketsByName['local-review-fields']['nonZeroEntryCount']);
        $t->same(1, $sourceBucketsByName['local-review-fields']['zeroByteEntryCount']);
        $t->same(['word/comments.xml'], $sourceBucketsByName['local-review-fields']['nonZeroEntryNames']);
        $t->same(2, $sourceBucketsByName['central-directory-review-fields']['nonZeroEntryCount']);
        $t->same(['word/document.xml', 'word/comments.xml'], $sourceBucketsByName['central-directory-review-fields']['nonZeroEntryNames']);

        $t->same(true, $summary['selectedSourceHasArchiveTrailer']);
        $t->same($layout['eocdOffset'], $summary['selectedSourceArchiveTrailerOffset']);
        $t->same($layout['endOfCentralDirectoryBytes'], $summary['selectedSourceArchiveTrailerBytes']);
        $t->same($layout['eocdOffset'] + $layout['endOfCentralDirectoryBytes'], $summary['selectedSourceArchiveTrailerEnd']);
        $t->same($layout['endOfCentralDirectorySha256'], $summary['selectedSourceArchiveTrailerSha256']);
        $t->same(strlen('source-span-review'), $summary['selectedSourceArchiveTrailerReviewFieldBytes']);
        $t->same('zip-selected-source-archive-trailer-metadata-only', $summary['selectedSourceArchiveTrailerByteExposurePolicy']);
        $t->same(false, $summary['selectedSourceArchiveTrailerCanExposeBytes']);
        $t->same($layout['eocdOffset'], $summary['selectedSourceEndOfCentralDirectoryOffset']);
        $t->same($layout['endOfCentralDirectoryBytes'], $summary['selectedSourceEndOfCentralDirectoryBytes']);
        $t->same($layout['endOfCentralDirectorySha256'], $summary['selectedSourceEndOfCentralDirectorySha256']);
        $t->same(22, $summary['selectedSourceEndOfCentralDirectoryFixedHeaderBytes']);
        $t->same(hash('sha256', substr($zip, $layout['eocdOffset'], 22)), $summary['selectedSourceEndOfCentralDirectoryFixedHeaderSha256']);
        $t->same($layout['packageCommentOffset'], $summary['selectedSourcePackageCommentOffset']);
        $t->same(strlen('source-span-review'), $summary['selectedSourcePackageCommentBytes']);
        $t->same($layout['packageCommentEnd'], $summary['selectedSourcePackageCommentEnd']);
        $t->same(hash('sha256', 'source-span-review'), $summary['selectedSourcePackageCommentSha256']);
        $t->same(bin2hex(substr('source-span-review', 0, 16)), $summary['selectedSourcePackageCommentPreviewHex']);
        $t->same(16, $summary['selectedSourcePackageCommentPreviewByteCount']);
        $t->same('zip-package-comment-source-metadata-only', $summary['selectedSourcePackageCommentByteExposurePolicy']);
        $t->same(false, $summary['selectedSourceCanExposePackageCommentBytes']);
        $t->same(true, $summary['selectedSourceHasPackageComment']);
        $t->same(true, $archiveTrailer['hasArchiveTrailerSourceProvenance']);
        $t->same($summary['selectedSourceArchiveTrailerOffset'], $archiveTrailer['archiveTrailerOffset']);
        $t->same($summary['selectedSourceArchiveTrailerBytes'], $archiveTrailer['archiveTrailerBytes']);
        $t->same($summary['selectedSourceArchiveTrailerSha256'], $archiveTrailer['archiveTrailerSha256']);
        $t->same($summary['selectedSourcePackageCommentSha256'], $archiveTrailer['packageCommentSha256']);
        $t->same($summary['selectedSourceArchiveTrailerReviewFieldBytes'], $archiveTrailer['archiveTrailerReviewFieldBytes']);

        $t->same(true, $documentSpan['hasSourceByteSpanProvenance']);
        $t->same(0, $documentSpan['localRecordOffset']);
        $t->same(30 + strlen('word/document.xml'), $documentSpan['localHeaderBytes']);
        $t->same($documentSpan['localRecordOffset'] + $documentSpan['localHeaderBytes'], $documentSpan['localHeaderEnd']);
        $t->same($documentSpan['localHeaderEnd'], $documentSpan['compressedDataOffset']);
        $t->same(strlen($documentXml), $documentSpan['compressedDataBytes']);
        $t->same($documentSpan['compressedDataOffset'] + strlen($documentXml), $documentSpan['compressedDataEnd']);
        $t->same($documentSpan['compressedDataEnd'], $documentSpan['localRecordEnd']);
        $t->same(false, $documentSpan['sourceByteSpanIncludesDataDescriptor']);
        $t->same(null, $documentSpan['dataDescriptorOffset']);
        $t->same(0, $documentSpan['dataDescriptorBytes']);
        $t->same(null, $documentSpan['dataDescriptorEnd']);
        $t->same(null, $documentSpan['dataDescriptorSha256']);
        $t->same(hash('sha256', $documentXml), $documentSpan['compressedDataSha256']);
        $t->same(hash('sha256', substr($zip, $documentSpan['localRecordOffset'], $documentSpan['localRecordBytes'])), $documentSpan['localRecordSha256']);
        $t->same(hash('sha256', substr($zip, $documentSpan['localRecordOffset'], $documentSpan['localHeaderBytes'])), $documentSpan['localHeaderSha256']);
        $t->same(hash('sha256', substr($zip, $documentSpan['centralDirectoryRecordOffset'], $documentSpan['centralDirectoryRecordBytes'])), $documentSpan['centralDirectoryRecordSha256']);
        $t->same(46, $documentSpan['centralDirectoryFixedHeaderBytes']);
        $t->same($documentSpan['centralDirectoryRecordOffset'] + 46, $documentSpan['centralDirectoryVariableFieldOffset']);
        $t->same(strlen('word/document.xml') + strlen($documentComment), $documentSpan['centralDirectoryVariableFieldBytes']);
        $t->same(hash('sha256', substr($zip, $documentSpan['centralDirectoryVariableFieldOffset'], $documentSpan['centralDirectoryVariableFieldBytes'])), $documentSpan['centralDirectoryVariableFieldSha256']);
        $t->same($documentSpan['centralDirectoryVariableFieldOffset'], $documentSpan['centralDirectoryRawNameOffset']);
        $t->same(strlen('word/document.xml'), $documentSpan['centralDirectoryRawNameBytes']);
        $t->same(hash('sha256', 'word/document.xml'), $documentSpan['centralDirectoryRawNameSha256']);
        $t->same($documentSpan['centralDirectoryRawNameOffset'] + strlen('word/document.xml'), $documentSpan['centralDirectoryExtraFieldOffset']);
        $t->same(0, $documentSpan['centralDirectoryExtraFieldBytes']);
        $t->same(hash('sha256', ''), $documentSpan['centralDirectoryExtraFieldSha256']);
        $t->same($documentSpan['centralDirectoryExtraFieldOffset'], $documentSpan['centralDirectoryRawCommentOffset']);
        $t->same(strlen($documentComment), $documentSpan['centralDirectoryRawCommentBytes']);
        $t->same(hash('sha256', $documentComment), $documentSpan['centralDirectoryRawCommentSha256']);
        $t->same(strlen($documentComment), $documentSpan['centralDirectoryReviewFieldBytes']);
        $t->same([], $documentSpan['sourceByteSpanIssues']);

        $t->same(true, $commentsSpan['hasSourceByteSpanProvenance']);
        $t->same($documentSpan['localRecordEnd'], $commentsSpan['localRecordOffset']);
        $t->same(30 + strlen('word/comments.xml') + strlen($commentsExtra), $commentsSpan['localHeaderBytes']);
        $t->same($commentsSpan['localHeaderEnd'], $commentsSpan['compressedDataOffset']);
        $t->same(strlen($commentsCompressed), $commentsSpan['compressedDataBytes']);
        $t->same(hash('sha256', $commentsCompressed), $commentsSpan['compressedDataSha256']);
        $t->same(true, $commentsSpan['sourceByteSpanIncludesDataDescriptor']);
        $t->same($commentsSpan['compressedDataEnd'], $commentsSpan['dataDescriptorOffset']);
        $t->same(16, $commentsSpan['dataDescriptorBytes']);
        $t->same($commentsSpan['dataDescriptorOffset'] + 16, $commentsSpan['dataDescriptorEnd']);
        $t->same($commentsSpan['dataDescriptorEnd'], $commentsSpan['localRecordEnd']);
        $t->same(hash('sha256', substr($zip, $commentsSpan['dataDescriptorOffset'], 16)), $commentsSpan['dataDescriptorSha256']);
        $t->same(hash('sha256', substr($zip, $commentsSpan['localRecordOffset'], $commentsSpan['localRecordBytes'])), $commentsSpan['localRecordSha256']);
        $t->same(hash('sha256', substr($zip, $commentsSpan['centralDirectoryRecordOffset'], $commentsSpan['centralDirectoryRecordBytes'])), $commentsSpan['centralDirectoryRecordSha256']);
        $t->same(46, $commentsSpan['centralDirectoryFixedHeaderBytes']);
        $t->same($commentsSpan['centralDirectoryRecordOffset'] + 46, $commentsSpan['centralDirectoryVariableFieldOffset']);
        $t->same(
            strlen('word/comments.xml') + strlen($commentsExtra) + strlen($commentsComment),
            $commentsSpan['centralDirectoryVariableFieldBytes']
        );
        $t->same(hash('sha256', substr($zip, $commentsSpan['centralDirectoryVariableFieldOffset'], $commentsSpan['centralDirectoryVariableFieldBytes'])), $commentsSpan['centralDirectoryVariableFieldSha256']);
        $t->same($commentsSpan['centralDirectoryVariableFieldOffset'], $commentsSpan['centralDirectoryRawNameOffset']);
        $t->same(strlen('word/comments.xml'), $commentsSpan['centralDirectoryRawNameBytes']);
        $t->same(hash('sha256', 'word/comments.xml'), $commentsSpan['centralDirectoryRawNameSha256']);
        $t->same($commentsSpan['centralDirectoryRawNameOffset'] + strlen('word/comments.xml'), $commentsSpan['centralDirectoryExtraFieldOffset']);
        $t->same(strlen($commentsExtra), $commentsSpan['centralDirectoryExtraFieldBytes']);
        $t->same(hash('sha256', $commentsExtra), $commentsSpan['centralDirectoryExtraFieldSha256']);
        $t->same($commentsSpan['centralDirectoryExtraFieldOffset'] + strlen($commentsExtra), $commentsSpan['centralDirectoryRawCommentOffset']);
        $t->same(strlen($commentsComment), $commentsSpan['centralDirectoryRawCommentBytes']);
        $t->same(hash('sha256', $commentsComment), $commentsSpan['centralDirectoryRawCommentSha256']);
        $t->same(strlen($commentsExtra) + strlen($commentsComment), $commentsSpan['centralDirectoryReviewFieldBytes']);
        $t->same([], $commentsSpan['sourceByteSpanIssues']);

        $t->same($documentSpan['localRecordSha256'], $documentEntry['localRecordSha256']);
        $t->same($documentSpan['sourceRecordBytes'], $documentEntry['sourceRecordBytes']);
        $t->same($commentsSpan['dataDescriptorSha256'], $commentsEntry['dataDescriptorSha256']);
        $t->same($commentsSpan['sourceRecordBytes'], $commentsEntry['sourceRecordBytes']);
        $t->same($commentsSpan['centralDirectoryRecordSha256'], $commentsEntry['centralDirectoryRecordSha256']);

        $expectedManifestEntries = [
            [
                'name' => 'word/document.xml',
                'localRecordOffset' => $documentSpan['localRecordOffset'],
                'localRecordBytes' => $documentSpan['localRecordBytes'],
                'localRecordSha256' => hash('sha256', substr($zip, $documentSpan['localRecordOffset'], $documentSpan['localRecordBytes'])),
                'localHeaderBytes' => $documentSpan['localHeaderBytes'],
                'localHeaderSha256' => $documentSpan['localHeaderSha256'],
                'localHeaderVariableFieldOffset' => $documentSpan['localHeaderVariableFieldOffset'],
                'localHeaderVariableFieldBytes' => $documentSpan['localHeaderVariableFieldBytes'],
                'localHeaderVariableFieldSha256' => $documentSpan['localHeaderVariableFieldSha256'],
                'localRawNameOffset' => $documentSpan['localRawNameOffset'],
                'localRawNameBytes' => $documentSpan['localRawNameBytes'],
                'localRawNameSha256' => $documentSpan['localRawNameSha256'],
                'localExtraFieldOffset' => $documentSpan['localExtraFieldOffset'],
                'localExtraFieldBytes' => $documentSpan['localExtraFieldBytes'],
                'localExtraFieldSha256' => $documentSpan['localExtraFieldSha256'],
                'localHeaderReviewFieldBytes' => $documentSpan['localHeaderReviewFieldBytes'],
                'compressedDataOffset' => $documentSpan['compressedDataOffset'],
                'compressedDataBytes' => strlen($documentXml),
                'compressedDataSha256' => hash('sha256', $documentXml),
                'dataDescriptorOffset' => null,
                'dataDescriptorBytes' => 0,
                'dataDescriptorSha256' => null,
                'centralDirectoryRecordOffset' => $documentSpan['centralDirectoryRecordOffset'],
                'centralDirectoryRecordBytes' => $documentSpan['centralDirectoryRecordBytes'],
                'centralDirectoryRecordSha256' => hash('sha256', substr($zip, $documentSpan['centralDirectoryRecordOffset'], $documentSpan['centralDirectoryRecordBytes'])),
                'centralDirectoryFixedHeaderBytes' => $documentSpan['centralDirectoryFixedHeaderBytes'],
                'centralDirectoryVariableFieldOffset' => $documentSpan['centralDirectoryVariableFieldOffset'],
                'centralDirectoryVariableFieldBytes' => $documentSpan['centralDirectoryVariableFieldBytes'],
                'centralDirectoryVariableFieldSha256' => $documentSpan['centralDirectoryVariableFieldSha256'],
                'centralDirectoryRawNameOffset' => $documentSpan['centralDirectoryRawNameOffset'],
                'centralDirectoryRawNameBytes' => $documentSpan['centralDirectoryRawNameBytes'],
                'centralDirectoryRawNameSha256' => $documentSpan['centralDirectoryRawNameSha256'],
                'centralDirectoryExtraFieldOffset' => $documentSpan['centralDirectoryExtraFieldOffset'],
                'centralDirectoryExtraFieldBytes' => $documentSpan['centralDirectoryExtraFieldBytes'],
                'centralDirectoryExtraFieldSha256' => $documentSpan['centralDirectoryExtraFieldSha256'],
                'centralDirectoryRawCommentOffset' => $documentSpan['centralDirectoryRawCommentOffset'],
                'centralDirectoryRawCommentBytes' => $documentSpan['centralDirectoryRawCommentBytes'],
                'centralDirectoryRawCommentSha256' => $documentSpan['centralDirectoryRawCommentSha256'],
                'centralDirectoryReviewFieldBytes' => $documentSpan['centralDirectoryReviewFieldBytes'],
                'sourceRecordBytes' => $documentSpan['sourceRecordBytes'],
                'sourceByteSpanIssues' => [],
            ],
            [
                'name' => 'word/comments.xml',
                'localRecordOffset' => $commentsSpan['localRecordOffset'],
                'localRecordBytes' => $commentsSpan['localRecordBytes'],
                'localRecordSha256' => hash('sha256', substr($zip, $commentsSpan['localRecordOffset'], $commentsSpan['localRecordBytes'])),
                'localHeaderBytes' => $commentsSpan['localHeaderBytes'],
                'localHeaderSha256' => $commentsSpan['localHeaderSha256'],
                'localHeaderVariableFieldOffset' => $commentsSpan['localHeaderVariableFieldOffset'],
                'localHeaderVariableFieldBytes' => $commentsSpan['localHeaderVariableFieldBytes'],
                'localHeaderVariableFieldSha256' => $commentsSpan['localHeaderVariableFieldSha256'],
                'localRawNameOffset' => $commentsSpan['localRawNameOffset'],
                'localRawNameBytes' => $commentsSpan['localRawNameBytes'],
                'localRawNameSha256' => $commentsSpan['localRawNameSha256'],
                'localExtraFieldOffset' => $commentsSpan['localExtraFieldOffset'],
                'localExtraFieldBytes' => $commentsSpan['localExtraFieldBytes'],
                'localExtraFieldSha256' => $commentsSpan['localExtraFieldSha256'],
                'localHeaderReviewFieldBytes' => $commentsSpan['localHeaderReviewFieldBytes'],
                'compressedDataOffset' => $commentsSpan['compressedDataOffset'],
                'compressedDataBytes' => strlen($commentsCompressed),
                'compressedDataSha256' => hash('sha256', $commentsCompressed),
                'dataDescriptorOffset' => $commentsSpan['dataDescriptorOffset'],
                'dataDescriptorBytes' => 16,
                'dataDescriptorSha256' => hash('sha256', substr($zip, $commentsSpan['dataDescriptorOffset'], 16)),
                'centralDirectoryRecordOffset' => $commentsSpan['centralDirectoryRecordOffset'],
                'centralDirectoryRecordBytes' => $commentsSpan['centralDirectoryRecordBytes'],
                'centralDirectoryRecordSha256' => hash('sha256', substr($zip, $commentsSpan['centralDirectoryRecordOffset'], $commentsSpan['centralDirectoryRecordBytes'])),
                'centralDirectoryFixedHeaderBytes' => $commentsSpan['centralDirectoryFixedHeaderBytes'],
                'centralDirectoryVariableFieldOffset' => $commentsSpan['centralDirectoryVariableFieldOffset'],
                'centralDirectoryVariableFieldBytes' => $commentsSpan['centralDirectoryVariableFieldBytes'],
                'centralDirectoryVariableFieldSha256' => $commentsSpan['centralDirectoryVariableFieldSha256'],
                'centralDirectoryRawNameOffset' => $commentsSpan['centralDirectoryRawNameOffset'],
                'centralDirectoryRawNameBytes' => $commentsSpan['centralDirectoryRawNameBytes'],
                'centralDirectoryRawNameSha256' => $commentsSpan['centralDirectoryRawNameSha256'],
                'centralDirectoryExtraFieldOffset' => $commentsSpan['centralDirectoryExtraFieldOffset'],
                'centralDirectoryExtraFieldBytes' => $commentsSpan['centralDirectoryExtraFieldBytes'],
                'centralDirectoryExtraFieldSha256' => $commentsSpan['centralDirectoryExtraFieldSha256'],
                'centralDirectoryRawCommentOffset' => $commentsSpan['centralDirectoryRawCommentOffset'],
                'centralDirectoryRawCommentBytes' => $commentsSpan['centralDirectoryRawCommentBytes'],
                'centralDirectoryRawCommentSha256' => $commentsSpan['centralDirectoryRawCommentSha256'],
                'centralDirectoryReviewFieldBytes' => $commentsSpan['centralDirectoryReviewFieldBytes'],
                'sourceRecordBytes' => $commentsSpan['sourceRecordBytes'],
                'sourceByteSpanIssues' => [],
            ],
        ];
        $expectedManifestHash = hash('sha256', json_encode([
            'manifestVersion' => 'zip-selected-source-manifest-v2',
            'entryCount' => 2,
            'localRecordBytes' => $summary['selectedSourceLocalRecordBytes'],
            'localHeaderBytes' => $summary['selectedSourceLocalHeaderBytes'],
            'localHeaderVariableFieldBytes' => $summary['selectedSourceLocalHeaderVariableFieldBytes'],
            'localRawNameBytes' => $summary['selectedSourceLocalRawNameBytes'],
            'localExtraFieldBytes' => $summary['selectedSourceLocalExtraFieldBytes'],
            'localHeaderReviewFieldBytes' => $summary['selectedSourceLocalReviewFieldBytes'],
            'compressedDataBytes' => $summary['selectedSourceCompressedDataBytes'],
            'dataDescriptorBytes' => $summary['selectedSourceDataDescriptorBytes'],
            'centralDirectoryRecordBytes' => $summary['selectedSourceCentralDirectoryRecordBytes'],
            'centralDirectoryFixedHeaderBytes' => $summary['selectedSourceCentralDirectoryFixedHeaderBytes'],
            'centralDirectoryVariableFieldBytes' => $summary['selectedSourceCentralDirectoryVariableFieldBytes'],
            'centralDirectoryRawNameBytes' => $summary['selectedSourceCentralDirectoryRawNameBytes'],
            'centralDirectoryExtraFieldBytes' => $summary['selectedSourceCentralDirectoryExtraFieldBytes'],
            'centralDirectoryRawCommentBytes' => $summary['selectedSourceCentralDirectoryRawCommentBytes'],
            'centralDirectoryReviewFieldBytes' => $summary['selectedSourceCentralDirectoryReviewFieldBytes'],
            'reviewFieldBytes' => $summary['selectedSourceLocalReviewFieldBytes'] + $summary['selectedSourceCentralDirectoryReviewFieldBytes'],
            'sourceRecordBytes' => $summary['selectedSourceTotalRecordBytes'],
            'entries' => $expectedManifestEntries,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $t->same('zip-selected-source-manifest-v2', $summary['selectedSourceManifestVersion']);
        $t->same($expectedManifestHash, $summary['selectedSourceManifestSha256']);
        $t->same('zip-selected-source-manifest-v2', $summary['selectedSourceManifest']['manifestVersion']);
        $t->same($expectedManifestHash, $summary['selectedSourceManifest']['manifestSha256']);
        $t->same(2, $summary['selectedSourceManifest']['entryCount']);
        $t->same($summary['selectedSourceLocalRecordBytes'], $summary['selectedSourceManifest']['localRecordBytes']);
        $t->same($summary['selectedSourceLocalHeaderBytes'], $summary['selectedSourceManifest']['localHeaderBytes']);
        $t->same($summary['selectedSourceLocalHeaderVariableFieldBytes'], $summary['selectedSourceManifest']['localHeaderVariableFieldBytes']);
        $t->same($summary['selectedSourceLocalRawNameBytes'], $summary['selectedSourceManifest']['localRawNameBytes']);
        $t->same($summary['selectedSourceLocalExtraFieldBytes'], $summary['selectedSourceManifest']['localExtraFieldBytes']);
        $t->same($summary['selectedSourceLocalReviewFieldBytes'], $summary['selectedSourceManifest']['localHeaderReviewFieldBytes']);
        $t->same($summary['selectedSourceCompressedDataBytes'], $summary['selectedSourceManifest']['compressedDataBytes']);
        $t->same($summary['selectedSourceDataDescriptorBytes'], $summary['selectedSourceManifest']['dataDescriptorBytes']);
        $t->same($summary['selectedSourceCentralDirectoryRecordBytes'], $summary['selectedSourceManifest']['centralDirectoryRecordBytes']);
        $t->same($summary['selectedSourceCentralDirectoryFixedHeaderBytes'], $summary['selectedSourceManifest']['centralDirectoryFixedHeaderBytes']);
        $t->same($summary['selectedSourceCentralDirectoryVariableFieldBytes'], $summary['selectedSourceManifest']['centralDirectoryVariableFieldBytes']);
        $t->same($summary['selectedSourceCentralDirectoryRawNameBytes'], $summary['selectedSourceManifest']['centralDirectoryRawNameBytes']);
        $t->same($summary['selectedSourceCentralDirectoryExtraFieldBytes'], $summary['selectedSourceManifest']['centralDirectoryExtraFieldBytes']);
        $t->same($summary['selectedSourceCentralDirectoryRawCommentBytes'], $summary['selectedSourceManifest']['centralDirectoryRawCommentBytes']);
        $t->same($summary['selectedSourceCentralDirectoryReviewFieldBytes'], $summary['selectedSourceManifest']['centralDirectoryReviewFieldBytes']);
        $t->same(
            $summary['selectedSourceLocalReviewFieldBytes'] + $summary['selectedSourceCentralDirectoryReviewFieldBytes'],
            $summary['selectedSourceManifest']['reviewFieldBytes']
        );
        $t->same($summary['selectedSourceTotalRecordBytes'], $summary['selectedSourceManifest']['sourceRecordBytes']);
        $t->same($expectedManifestEntries, $summary['selectedSourceManifest']['entries']);
        $t->same([$documentEntry, $commentsEntry], $summary['handoffEntries']);
    },

    'preflights selected zip central directory fixed fields before reader handoff' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $documentName = 'word/document.xml';
        $mediaName = 'word/media/review.bin';
        $documentXml = '<w:document><w:body><w:p>selected central fixed header</w:p></w:body></w:document>';
        $mediaBytes = "central fixed media bytes\n";
        $documentExtra = pack('vva*', 0xd00d, strlen('central-fixed'), 'central-fixed');
        $documentComment = 'central fixed comment';
        $zip = $buildZipPackage([
            [
                'name' => $documentName,
                'data' => $documentXml,
                'method' => 8,
                'centralExtra' => $documentExtra,
                'localExtra' => '',
                'comment' => $documentComment,
                'versionMadeBy' => 0x0314,
                'internalAttributes' => 0x0001,
                'externalAttributes' => 0x81a40020,
            ],
            [
                'name' => $mediaName,
                'data' => $mediaBytes,
                'method' => 0,
                'versionMadeBy' => 0x0014,
                'externalAttributes' => 0x00000020,
            ],
        ]);
        $package = ZipPackage::fromString($zip);
        $summary = $package->entryHandoffPreflight([
            ['name' => $documentName, 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => $mediaName, 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
        ], 2048);

        $documentEntry = $summary['entries'][0];
        $mediaEntry = $summary['entries'][1];
        $documentFixed = $summary['selectedCentralDirectoryFixedFieldEntries'][0];
        $mediaFixed = $summary['selectedCentralDirectoryFixedFieldEntries'][1];
        $documentOffset = $documentEntry['centralDirectoryRecordOffset'];
        $mediaOffset = $mediaEntry['centralDirectoryRecordOffset'];
        $documentCompressed = gzdeflate($documentXml);
        if ($documentCompressed === false) {
            throw new RuntimeException('Unable to deflate document fixture');
        }

        $t->same(2, $summary['selectedCentralDirectoryFixedFieldEntryCount']);
        $t->same(0, $summary['selectedCentralDirectoryFixedFieldIssueEntryCount']);
        $t->same([], $summary['selectedCentralDirectoryFixedFieldIssueEntries']);
        $t->same('word/document.xml', $documentFixed['name']);
        $t->same($documentOffset, $documentFixed['centralDirectoryFixedHeaderOffset']);
        $t->same(46, $documentFixed['centralDirectoryFixedHeaderLength']);
        $t->same($documentOffset + 46, $documentFixed['centralDirectoryFixedHeaderEnd']);
        $t->same($documentOffset, $documentFixed['centralDirectorySignatureOffset']);
        $t->same(4, $documentFixed['centralDirectorySignatureLength']);
        $t->same($documentOffset + 4, $documentFixed['centralDirectoryVersionMadeByOffset']);
        $t->same($documentOffset + 42, $documentFixed['centralDirectoryLocalHeaderOffsetFieldOffset']);
        $t->same(0x0314, $documentFixed['centralDirectoryVersionMadeBy']);
        $t->same(3, $documentFixed['centralDirectoryCreatorHostSystem']);
        $t->same(20, $documentFixed['centralDirectoryCreatorVersion']);
        $t->same(20, $documentFixed['centralDirectoryVersionNeededToExtract']);
        $t->same(0x0800, $documentFixed['centralDirectoryGeneralPurposeFlags']);
        $t->same(8, $documentFixed['centralDirectoryCompressionMethod']);
        $t->same($crc32($documentXml), $documentFixed['centralDirectoryCrc32']);
        $t->same(sprintf('%08x', $crc32($documentXml)), $documentFixed['centralDirectoryCrc32Hex']);
        $t->same(strlen($documentCompressed), $documentFixed['centralDirectoryCompressedSize']);
        $t->same(strlen($documentXml), $documentFixed['centralDirectoryUncompressedSize']);
        $t->same(strlen($documentName), $documentFixed['centralDirectoryRawNameLength']);
        $t->same(strlen($documentExtra), $documentFixed['centralDirectoryExtraFieldLength']);
        $t->same(strlen($documentComment), $documentFixed['centralDirectoryRawCommentLength']);
        $t->same(0, $documentFixed['centralDirectoryDiskStart']);
        $t->same(0x0001, $documentFixed['centralDirectoryInternalAttributes']);
        $t->same(0x81a40020, $documentFixed['centralDirectoryExternalAttributes']);
        $t->same($documentEntry['localHeaderOffset'], $documentFixed['centralDirectoryLocalHeaderOffset']);
        $t->same(true, $documentFixed['centralDirectoryFixedFieldsMatchEntryMetadata']);
        $t->same([], $documentFixed['centralDirectoryFixedFieldIssues']);

        $t->same($documentFixed['centralDirectoryFixedHeaderOffset'], $documentEntry['centralDirectoryFixedHeaderOffset']);
        $t->same($documentFixed['centralDirectoryVersionMadeBy'], $documentEntry['centralDirectoryVersionMadeBy']);
        $t->same($documentFixed['centralDirectoryExternalAttributes'], $documentEntry['centralDirectoryExternalAttributes']);
        $t->same($documentFixed['centralDirectoryFixedFieldsMatchEntryMetadata'], $documentEntry['centralDirectoryFixedFieldsMatchEntryMetadata']);

        $t->same('word/media/review.bin', $mediaFixed['name']);
        $t->same($mediaOffset, $mediaFixed['centralDirectoryFixedHeaderOffset']);
        $t->same(0x0014, $mediaFixed['centralDirectoryVersionMadeBy']);
        $t->same(0, $mediaFixed['centralDirectoryCreatorHostSystem']);
        $t->same(0, $mediaFixed['centralDirectoryCompressionMethod']);
        $t->same(strlen($mediaBytes), $mediaFixed['centralDirectoryCompressedSize']);
        $t->same(strlen($mediaBytes), $mediaFixed['centralDirectoryUncompressedSize']);
        $t->same(strlen($mediaName), $mediaFixed['centralDirectoryRawNameLength']);
        $t->same(0, $mediaFixed['centralDirectoryExtraFieldLength']);
        $t->same(0, $mediaFixed['centralDirectoryRawCommentLength']);
        $t->same($mediaEntry['localHeaderOffset'], $mediaFixed['centralDirectoryLocalHeaderOffset']);
        $t->same(true, $mediaFixed['centralDirectoryFixedFieldsMatchEntryMetadata']);
        $t->same([], $mediaFixed['centralDirectoryFixedFieldIssues']);
        $t->same([$documentEntry, $mediaEntry], $summary['handoffEntries']);
    },

    'preflights selected zip central directory variable fields before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>selected central directory fields</w:p></w:body></w:document>';
        $mediaBytes = "selected central directory media bytes\n";
        $documentExtra = pack('vva*', 0xcafe, strlen('central-review'), 'central-review');
        $documentComment = 'central document comment';
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
                'centralExtra' => $documentExtra,
                'localExtra' => '',
                'comment' => $documentComment,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ]);
        $package = ZipPackage::fromString($zip);

        $summary = $package->entryHandoffPreflight([
            ['name' => 'word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/media/review.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/missing.xml', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
        ], 2048);

        $documentEntry = $summary['entries'][0];
        $mediaEntry = $summary['entries'][1];
        $missingEntry = $summary['entries'][2];
        $documentSpan = $summary['selectedSourceByteSpanEntries'][0];
        $mediaSpan = $summary['selectedSourceByteSpanEntries'][1];

        $t->same(3, $summary['requestedEntryCount']);
        $t->same(2, $summary['selectedUniqueEntryCount']);
        $t->same(2, $summary['selectedSourceByteSpanEntryCount']);
        $t->same(92, $summary['selectedSourceCentralDirectoryFixedHeaderBytes']);
        $t->same(
            strlen('word/document.xml') + strlen($documentExtra) + strlen($documentComment) + strlen('word/media/review.bin'),
            $summary['selectedSourceCentralDirectoryVariableFieldBytes']
        );
        $t->same(strlen('word/document.xml') + strlen('word/media/review.bin'), $summary['selectedSourceCentralDirectoryRawNameBytes']);
        $t->same(strlen($documentExtra), $summary['selectedSourceCentralDirectoryExtraFieldBytes']);
        $t->same(strlen($documentComment), $summary['selectedSourceCentralDirectoryRawCommentBytes']);
        $t->same(strlen($documentExtra) + strlen($documentComment), $summary['selectedSourceCentralDirectoryReviewFieldBytes']);
        $t->same(2, $summary['handoffEntryCount']);
        $t->same(0, $summary['failedEntryCount']);

        $t->same('word/document.xml', $documentSpan['name']);
        $t->same(46, $documentSpan['centralDirectoryFixedHeaderBytes']);
        $t->same($documentSpan['centralDirectoryRecordOffset'] + 46, $documentSpan['centralDirectoryVariableFieldOffset']);
        $t->same(strlen('word/document.xml') + strlen($documentExtra) + strlen($documentComment), $documentSpan['centralDirectoryVariableFieldBytes']);
        $t->same(hash('sha256', substr($zip, $documentSpan['centralDirectoryVariableFieldOffset'], $documentSpan['centralDirectoryVariableFieldBytes'])), $documentSpan['centralDirectoryVariableFieldSha256']);
        $t->same($documentSpan['centralDirectoryVariableFieldOffset'], $documentSpan['centralDirectoryRawNameOffset']);
        $t->same(strlen('word/document.xml'), $documentSpan['centralDirectoryRawNameBytes']);
        $t->same(hash('sha256', 'word/document.xml'), $documentSpan['centralDirectoryRawNameSha256']);
        $t->same($documentSpan['centralDirectoryRawNameOffset'] + strlen('word/document.xml'), $documentSpan['centralDirectoryExtraFieldOffset']);
        $t->same(strlen($documentExtra), $documentSpan['centralDirectoryExtraFieldBytes']);
        $t->same(hash('sha256', $documentExtra), $documentSpan['centralDirectoryExtraFieldSha256']);
        $t->same($documentSpan['centralDirectoryExtraFieldOffset'] + strlen($documentExtra), $documentSpan['centralDirectoryRawCommentOffset']);
        $t->same(strlen($documentComment), $documentSpan['centralDirectoryRawCommentBytes']);
        $t->same(hash('sha256', $documentComment), $documentSpan['centralDirectoryRawCommentSha256']);
        $t->same(strlen($documentExtra) + strlen($documentComment), $documentSpan['centralDirectoryReviewFieldBytes']);
        $t->same($documentSpan['centralDirectoryRecordBytes'], $documentSpan['centralDirectoryFixedHeaderBytes'] + $documentSpan['centralDirectoryVariableFieldBytes']);
        $t->same([], $documentSpan['sourceByteSpanIssues']);

        $t->same('word/media/review.bin', $mediaSpan['name']);
        $t->same(46, $mediaSpan['centralDirectoryFixedHeaderBytes']);
        $t->same(strlen('word/media/review.bin'), $mediaSpan['centralDirectoryVariableFieldBytes']);
        $t->same(0, $mediaSpan['centralDirectoryExtraFieldBytes']);
        $t->same(hash('sha256', ''), $mediaSpan['centralDirectoryExtraFieldSha256']);
        $t->same(0, $mediaSpan['centralDirectoryRawCommentBytes']);
        $t->same(hash('sha256', ''), $mediaSpan['centralDirectoryRawCommentSha256']);
        $t->same(0, $mediaSpan['centralDirectoryReviewFieldBytes']);
        $t->same([], $mediaSpan['sourceByteSpanIssues']);

        $t->same($documentSpan['centralDirectoryVariableFieldSha256'], $documentEntry['centralDirectoryVariableFieldSha256']);
        $t->same($mediaSpan['centralDirectoryRawNameBytes'], $mediaEntry['centralDirectoryRawNameBytes']);
        $t->same(false, $missingEntry['exists']);
        $t->same(null, $missingEntry['centralDirectoryVariableFieldBytes']);
        $t->same([$documentEntry, $mediaEntry], $summary['handoffEntries']);
    },

    'preflights selected zip local header variable fields before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>selected local header fields</w:p></w:body></w:document>';
        $mediaBytes = "selected local header media bytes\n";
        $documentLocalExtra = pack('vva*', 0xcafe, strlen('local-review'), 'local-review');
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
                'localExtra' => $documentLocalExtra,
                'centralExtra' => '',
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ]);
        $package = ZipPackage::fromString($zip);

        $summary = $package->entryHandoffPreflight([
            ['name' => 'word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/media/review.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/missing.xml', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
        ], 2048);

        $documentEntry = $summary['entries'][0];
        $mediaEntry = $summary['entries'][1];
        $missingEntry = $summary['entries'][2];
        $documentSpan = $summary['selectedSourceByteSpanEntries'][0];
        $mediaSpan = $summary['selectedSourceByteSpanEntries'][1];

        $t->same(3, $summary['requestedEntryCount']);
        $t->same(2, $summary['selectedUniqueEntryCount']);
        $t->same(2, $summary['selectedSourceByteSpanEntryCount']);
        $t->same(60, $summary['selectedSourceLocalFixedHeaderBytes']);
        $t->same(
            strlen('word/document.xml') + strlen($documentLocalExtra) + strlen('word/media/review.bin'),
            $summary['selectedSourceLocalHeaderVariableFieldBytes']
        );
        $t->same(strlen('word/document.xml') + strlen('word/media/review.bin'), $summary['selectedSourceLocalRawNameBytes']);
        $t->same(strlen($documentLocalExtra), $summary['selectedSourceLocalExtraFieldBytes']);
        $t->same(strlen($documentLocalExtra), $summary['selectedSourceLocalReviewFieldBytes']);
        $t->same(2, $summary['handoffEntryCount']);
        $t->same(0, $summary['failedEntryCount']);

        $t->same('word/document.xml', $documentSpan['name']);
        $t->same(30, $documentSpan['localFixedHeaderBytes']);
        $t->same($documentSpan['localRecordOffset'] + 30, $documentSpan['localHeaderVariableFieldOffset']);
        $t->same(strlen('word/document.xml') + strlen($documentLocalExtra), $documentSpan['localHeaderVariableFieldBytes']);
        $t->same(hash('sha256', substr($zip, $documentSpan['localHeaderVariableFieldOffset'], $documentSpan['localHeaderVariableFieldBytes'])), $documentSpan['localHeaderVariableFieldSha256']);
        $t->same($documentSpan['localHeaderVariableFieldOffset'], $documentSpan['localRawNameOffset']);
        $t->same(strlen('word/document.xml'), $documentSpan['localRawNameBytes']);
        $t->same(hash('sha256', 'word/document.xml'), $documentSpan['localRawNameSha256']);
        $t->same($documentSpan['localRawNameOffset'] + strlen('word/document.xml'), $documentSpan['localExtraFieldOffset']);
        $t->same(strlen($documentLocalExtra), $documentSpan['localExtraFieldBytes']);
        $t->same(hash('sha256', $documentLocalExtra), $documentSpan['localExtraFieldSha256']);
        $t->same(strlen($documentLocalExtra), $documentSpan['localHeaderReviewFieldBytes']);
        $t->same($documentSpan['localHeaderBytes'], $documentSpan['localFixedHeaderBytes'] + $documentSpan['localHeaderVariableFieldBytes']);

        $t->same('word/media/review.bin', $mediaSpan['name']);
        $t->same(30, $mediaSpan['localFixedHeaderBytes']);
        $t->same(strlen('word/media/review.bin'), $mediaSpan['localHeaderVariableFieldBytes']);
        $t->same(0, $mediaSpan['localExtraFieldBytes']);
        $t->same(hash('sha256', ''), $mediaSpan['localExtraFieldSha256']);
        $t->same(0, $mediaSpan['localHeaderReviewFieldBytes']);

        $t->same($documentSpan['localHeaderVariableFieldSha256'], $documentEntry['localHeaderVariableFieldSha256']);
        $t->same($mediaSpan['localRawNameBytes'], $mediaEntry['localRawNameBytes']);
        $t->same(false, $missingEntry['exists']);
        $t->same(null, $missingEntry['localHeaderVariableFieldBytes']);
        $t->same([$documentEntry, $mediaEntry], $summary['handoffEntries']);
    },

    'preflights selected zip package aggregate bytes before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>selected aggregate budget</w:p></w:body></w:document>';
        $mediaBytes = "selected media handoff bytes\n";
        $totalSelectedBytes = strlen($documentXml) + strlen($mediaBytes);
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ]));

        $requests = [
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/document.xml', 'required' => false, 'kind' => 'file', 'role' => 'duplicate-main-document'],
            ['name' => 'word/media/review.txt', 'required' => false, 'kind' => 'file', 'role' => 'media-review'],
        ];

        $summary = $package->entryHandoffPreflight($requests, 1024, $totalSelectedBytes - 1);

        $t->same(3, $summary['requestedEntryCount']);
        $t->same(2, $summary['presentEntryCount']);
        $t->same(2, $summary['selectedUniqueEntryCount']);
        $t->same($totalSelectedBytes, $summary['selectedCompressedBytes']);
        $t->same($totalSelectedBytes, $summary['selectedUncompressedBytes']);
        $t->same(1.0, $summary['selectedExpansionRatio']);
        $t->same(0, $summary['selectedUnknownExpansionRatioEntryCount']);
        $t->same(false, $summary['selectedHasUnknownExpansionRatioEntries']);
        $t->same([], $summary['selectedUnknownExpansionRatioEntries']);
        $t->same(1024, $summary['maxEntryUncompressedBytes']);
        $t->same($totalSelectedBytes - 1, $summary['maxTotalUncompressedBytes']);
        $t->same(0, $summary['handoffEntryCount']);
        $t->same(0, $summary['readableEntryCount']);
        $t->same(3, $summary['failedEntryCount']);
        $t->same(3, $summary['totalUncompressedSizeExceedsLimitEntryCount']);
        $t->same(2, $summary['duplicateRequestedEntryCount']);
        $t->same(1, $summary['duplicateRequestedEntryGroupCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['duplicate-selected-entry-request', 'total-uncompressed-size-exceeds-limit'], $summary['issues']);
        $t->same([], $summary['handoffEntries']);

        $t->same(['duplicate-selected-entry-request', 'total-uncompressed-size-exceeds-limit'], $summary['entries'][0]['issues']);
        $t->same(['duplicate-selected-entry-request', 'total-uncompressed-size-exceeds-limit'], $summary['entries'][1]['issues']);
        $t->same(['total-uncompressed-size-exceeds-limit'], $summary['entries'][2]['issues']);
        foreach ($summary['entries'] as $entry) {
            $t->same('blocked', $entry['status']);
            $t->same(false, $entry['isReadable']);
            $t->same(null, $entry['bytesRead']);
            $t->same(null, $entry['contentSha256']);
        }

        $safeSummary = $package->entryHandoffPreflight($requests, 1024, $totalSelectedBytes);

        $t->same(false, $safeSummary['isSupportedByBoundedReader']);
        $t->same(['duplicate-selected-entry-request'], $safeSummary['issues']);
        $t->same(2, $safeSummary['selectedUniqueEntryCount']);
        $t->same($totalSelectedBytes, $safeSummary['selectedUncompressedBytes']);
        $t->same(1.0, $safeSummary['selectedExpansionRatio']);
        $t->same(0, $safeSummary['selectedUnknownExpansionRatioEntryCount']);
        $t->same(false, $safeSummary['selectedHasUnknownExpansionRatioEntries']);
        $t->same([], $safeSummary['selectedUnknownExpansionRatioEntries']);
        $t->same(1, $safeSummary['handoffEntryCount']);
        $t->same(1, $safeSummary['readableEntryCount']);
        $t->same(2, $safeSummary['failedEntryCount']);
        $t->same(0, $safeSummary['totalUncompressedSizeExceedsLimitEntryCount']);
        $t->same(null, $safeSummary['entries'][0]['bytesRead']);
        $t->same(null, $safeSummary['entries'][0]['contentSha256']);
        $t->same(null, $safeSummary['entries'][1]['bytesRead']);
        $t->same(null, $safeSummary['entries'][1]['contentSha256']);
        $t->same(strlen($mediaBytes), $safeSummary['entries'][2]['bytesRead']);
    },

    'summarizes selected zero byte zip handoff buckets before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>selected zero byte handoff</w:p></w:body></w:document>';
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/empty.bin',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'word/media/empty-deflated.bin',
                'data' => '',
                'method' => 8,
            ],
            [
                'name' => 'word/media/empty-dir/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $emptyDeflatedCompressed = strlen(gzdeflate(''));
        $emptyStoredFile = [
            'name' => 'word/media/empty.bin',
            'compressionMethod' => 0,
            'isDirectory' => false,
            'compressedSize' => 0,
            'uncompressedSize' => 0,
            'expansionRatio' => 0.0,
        ];
        $emptyDeflatedFile = [
            'name' => 'word/media/empty-deflated.bin',
            'compressionMethod' => 8,
            'isDirectory' => false,
            'compressedSize' => $emptyDeflatedCompressed,
            'uncompressedSize' => 0,
            'expansionRatio' => 0.0,
        ];
        $emptyDirectory = [
            'name' => 'word/media/empty-dir/',
            'compressionMethod' => 0,
            'isDirectory' => true,
            'compressedSize' => 0,
            'uncompressedSize' => 0,
            'expansionRatio' => 0.0,
        ];

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => '/word/media/empty.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/media/empty-deflated.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/media/empty-dir/', 'required' => false, 'kind' => 'directory', 'role' => 'media-directory'],
            ['name' => 'word/media/missing.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
        ], 2048);

        $t->same(5, $summary['requestedEntryCount']);
        $t->same(4, $summary['presentEntryCount']);
        $t->same(4, $summary['selectedUniqueEntryCount']);
        $t->same(3, $summary['selectedFileEntryCount']);
        $t->same(1, $summary['selectedDirectoryEntryCount']);
        $t->same(3, $summary['selectedZeroByteEntryCount']);
        $t->same(2, $summary['selectedZeroByteFileCount']);
        $t->same(1, $summary['selectedEmptyDirectoryEntryCount']);
        $t->same(true, $summary['selectedHasZeroByteEntries']);
        $t->same([$emptyStoredFile, $emptyDeflatedFile, $emptyDirectory], $summary['selectedZeroByteEntries']);
        $t->same(4, $summary['handoffEntryCount']);
        $t->same(4, $summary['readableEntryCount']);
        $t->same(0, $summary['failedEntryCount']);
        $t->same(3, $summary['handoffZeroByteEntryCount']);
        $t->same(2, $summary['handoffZeroByteFileCount']);
        $t->same(1, $summary['handoffEmptyDirectoryEntryCount']);
        $t->same(true, $summary['handoffHasZeroByteEntries']);
        $t->same(true, $summary['isSupportedByBoundedReader']);
        $t->same([], $summary['issues']);

        $emptyStoredHandoff = [
            'requestIndex' => 1,
            'requestedName' => '/word/media/empty.bin',
            'name' => 'word/media/empty.bin',
            'role' => 'attachment',
            'required' => false,
            'expectedKind' => 'file',
        ] + $emptyStoredFile;
        $emptyDeflatedHandoff = [
            'requestIndex' => 2,
            'requestedName' => 'word/media/empty-deflated.bin',
            'name' => 'word/media/empty-deflated.bin',
            'role' => 'attachment',
            'required' => false,
            'expectedKind' => 'file',
        ] + $emptyDeflatedFile;
        $emptyDirectoryHandoff = [
            'requestIndex' => 3,
            'requestedName' => 'word/media/empty-dir/',
            'name' => 'word/media/empty-dir/',
            'role' => 'media-directory',
            'required' => false,
            'expectedKind' => 'directory',
        ] + $emptyDirectory;
        $t->same([$emptyStoredHandoff, $emptyDeflatedHandoff, $emptyDirectoryHandoff], $summary['handoffZeroByteEntries']);

        $emptyStoredEntry = $summary['entries'][1];
        $emptyDeflatedEntry = $summary['entries'][2];
        $emptyDirectoryEntry = $summary['entries'][3];
        $missingEntry = $summary['entries'][4];
        $t->same(0, $emptyStoredEntry['bytesRead']);
        $t->same(0, $emptyDeflatedEntry['bytesRead']);
        $t->same(0, $emptyDirectoryEntry['bytesRead']);
        $t->same(hash('sha256', ''), $emptyStoredEntry['contentSha256']);
        $t->same(hash('sha256', ''), $emptyDeflatedEntry['contentSha256']);
        $t->same(hash('sha256', ''), $emptyDirectoryEntry['contentSha256']);
        $t->same('missing-optional', $missingEntry['status']);
        $t->same([], $summary['failedEntries']);
    },

    'preflights selected zip package expansion before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>selected expansion review</w:p></w:body></w:document>';
        $zeroCompressedName = 'word/media/zero-compressed.bin';
        $zeroUncompressedSize = 37;
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => $zeroCompressedName,
                'data' => '',
                'method' => 12,
                'centralCompressedSize' => 0,
                'centralUncompressedSize' => $zeroUncompressedSize,
                'localCompressedSize' => 0,
                'localUncompressedSize' => $zeroUncompressedSize,
            ],
        ]));
        $documentCompressed = strlen(gzdeflate($documentXml));

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => $zeroCompressedName, 'required' => false, 'kind' => 'file', 'role' => 'media-review'],
        ], 1024);

        $expectedUnknownEntry = [
            'name' => $zeroCompressedName,
            'compressionMethod' => 12,
            'isDirectory' => false,
            'compressedSize' => 0,
            'uncompressedSize' => $zeroUncompressedSize,
            'expansionRatio' => null,
        ];

        $t->same(2, $summary['selectedUniqueEntryCount']);
        $t->same($documentCompressed, $summary['selectedCompressedBytes']);
        $t->same(strlen($documentXml) + $zeroUncompressedSize, $summary['selectedUncompressedBytes']);
        $t->same((strlen($documentXml) + $zeroUncompressedSize) / $documentCompressed, $summary['selectedExpansionRatio']);
        $t->same(1, $summary['selectedUnknownExpansionRatioEntryCount']);
        $t->same(true, $summary['selectedHasUnknownExpansionRatioEntries']);
        $t->same([$expectedUnknownEntry], $summary['selectedUnknownExpansionRatioEntries']);
        $t->same(1, $summary['handoffEntryCount']);
        $t->same(1, $summary['readableEntryCount']);
        $t->same(1, $summary['failedEntryCount']);
        $t->same(['unreadable-entry'], $summary['issues']);

        $documentEntry = $summary['entries'][0];
        $t->same(strlen($documentXml) / $documentCompressed, $documentEntry['expansionRatio']);
        $t->same('ready', $documentEntry['status']);
        $t->same(hash('sha256', $documentXml), $documentEntry['contentSha256']);

        $zeroCompressedEntry = $summary['entries'][1];
        $t->same($zeroCompressedName, $zeroCompressedEntry['name']);
        $t->same('unsupported', $zeroCompressedEntry['compressionMethodName']);
        $t->same(null, $zeroCompressedEntry['expansionRatio']);
        $t->same(false, $zeroCompressedEntry['isReadable']);
        $t->same(['unreadable-entry'], $zeroCompressedEntry['issues']);
        $t->contains('Unsupported ZIP compression method 12', $zeroCompressedEntry['error']);
    },

    'preflights duplicate selected zip package requests before reader handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>duplicate selected handoff</w:p></w:body></w:document>';
        $imageBytes = "review image bytes\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/image.png',
                'data' => $imageBytes,
                'method' => 0,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/document.xml', 'required' => false, 'kind' => 'file', 'role' => 'secondary-main-document'],
            ['name' => 'word/media/image.png', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
        ], 256);

        $t->same(3, $summary['requestedEntryCount']);
        $t->same(1, $summary['requiredEntryCount']);
        $t->same(2, $summary['optionalEntryCount']);
        $t->same(2, $summary['presentEntryCount']);
        $t->same(2, $summary['duplicateRequestedEntryCount']);
        $t->same(1, $summary['duplicateRequestedEntryGroupCount']);
        $t->same(1, $summary['handoffEntryCount']);
        $t->same(1, $summary['readableEntryCount']);
        $t->same(2, $summary['failedEntryCount']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['duplicate-selected-entry-request'], $summary['issues']);
        $t->same([
            [
                'name' => 'word/document.xml',
                'count' => 2,
                'requestIndexes' => [0, 1],
                'requestedNames' => ['/word/document.xml', 'word/document.xml'],
                'requiredCount' => 1,
                'optionalCount' => 1,
            ],
        ], $summary['duplicateRequestedEntryGroups']);

        $firstDuplicate = $summary['entries'][0];
        $secondDuplicate = $summary['entries'][1];
        $safeAttachment = $summary['entries'][2];
        $t->same(true, $firstDuplicate['isDuplicateRequest']);
        $t->same(true, $secondDuplicate['isDuplicateRequest']);
        $t->same('blocked', $firstDuplicate['status']);
        $t->same('blocked', $secondDuplicate['status']);
        $t->same(['duplicate-selected-entry-request'], $firstDuplicate['issues']);
        $t->same(['duplicate-selected-entry-request'], $secondDuplicate['issues']);
        $t->same(false, $firstDuplicate['isReadable']);
        $t->same(false, $secondDuplicate['isReadable']);
        $t->same(null, $firstDuplicate['bytesRead']);
        $t->same(null, $secondDuplicate['bytesRead']);
        $t->same(null, $firstDuplicate['contentSha256']);
        $t->same(null, $secondDuplicate['contentSha256']);
        $t->same(false, $safeAttachment['isDuplicateRequest']);
        $t->same('ready', $safeAttachment['status']);
        $t->same(hash('sha256', $imageBytes), $safeAttachment['contentSha256']);
        $t->same([$firstDuplicate, $secondDuplicate], $summary['failedEntries']);
        $t->same([$safeAttachment], $summary['handoffEntries']);
    },

    'summarizes selected zip handoff roles for package review' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>role handoff</w:p></w:body></w:document>';
        $imageBytes = "review image bytes\n";
        $largeBytes = "large attachment bytes\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'word/media/image.png',
                'data' => $imageBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/large.bin',
                'data' => $largeBytes,
                'method' => 0,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => '/word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/media/image.png', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => '/word/media/image.png', 'required' => false, 'kind' => 'file', 'role' => 'attachment'],
            ['name' => 'word/media/large.bin', 'required' => false, 'kind' => 'file', 'role' => 'attachment', 'maxUncompressedBytes' => 8],
            ['name' => 'word/missing.xml', 'required' => true, 'kind' => 'file', 'role' => 'required-sidecar'],
            ['name' => 'word/optional.xml', 'required' => false, 'kind' => 'file'],
        ], 1024);
        $byRole = [];
        foreach ($summary['roleSummaries'] as $roleSummary) {
            $byRole[$roleSummary['role'] ?? '(none)'] = $roleSummary;
        }

        $t->same(4, $summary['requestedRoleCount']);
        $t->same(['(none)', 'attachment', 'main-document', 'required-sidecar'], array_keys($byRole));

        $t->same(null, $byRole['(none)']['role']);
        $t->same(1, $byRole['(none)']['requestCount']);
        $t->same(0, $byRole['(none)']['presentEntryCount']);
        $t->same(1, $byRole['(none)']['missingEntryCount']);
        $t->same(0, $byRole['(none)']['handoffEntryCount']);
        $t->same(0, $byRole['(none)']['failedEntryCount']);
        $t->same(['word/optional.xml'], $byRole['(none)']['missingEntryNames']);
        $t->same([], $byRole['(none)']['issues']);

        $t->same(3, $byRole['attachment']['requestCount']);
        $t->same(0, $byRole['attachment']['requiredCount']);
        $t->same(3, $byRole['attachment']['optionalCount']);
        $t->same(3, $byRole['attachment']['presentEntryCount']);
        $t->same(0, $byRole['attachment']['missingEntryCount']);
        $t->same(0, $byRole['attachment']['handoffEntryCount']);
        $t->same(0, $byRole['attachment']['handoffUniqueEntryCount']);
        $t->same(3, $byRole['attachment']['failedEntryCount']);
        $t->same(2, $byRole['attachment']['duplicateRequestCount']);
        $t->same(2, $byRole['attachment']['selectedUniqueEntryCount']);
        $t->same(strlen($imageBytes) + strlen($largeBytes), $byRole['attachment']['selectedCompressedBytes']);
        $t->same(strlen($imageBytes) + strlen($largeBytes), $byRole['attachment']['selectedUncompressedBytes']);
        $t->same(0, $byRole['attachment']['handoffCompressedBytes']);
        $t->same(0, $byRole['attachment']['handoffUncompressedBytes']);
        $t->same(['word/media/image.png', 'word/media/large.bin'], $byRole['attachment']['selectedEntryNames']);
        $t->same([], $byRole['attachment']['handoffEntryNames']);
        $t->same(['word/media/image.png', 'word/media/image.png', 'word/media/large.bin'], $byRole['attachment']['failedEntryNames']);
        $t->same(['duplicate-selected-entry-request', 'entry-uncompressed-size-exceeds-limit'], $byRole['attachment']['issues']);
        $t->same([
            'duplicate-selected-entry-request' => 2,
            'entry-uncompressed-size-exceeds-limit' => 1,
        ], $byRole['attachment']['issueCounts']);

        $t->same(1, $byRole['main-document']['requestCount']);
        $t->same(1, $byRole['main-document']['requiredCount']);
        $t->same(1, $byRole['main-document']['presentEntryCount']);
        $t->same(1, $byRole['main-document']['handoffEntryCount']);
        $t->same(1, $byRole['main-document']['handoffUniqueEntryCount']);
        $t->same(strlen($documentXml), $byRole['main-document']['selectedUncompressedBytes']);
        $t->same(strlen($documentXml), $byRole['main-document']['handoffUncompressedBytes']);
        $t->same(['word/document.xml'], $byRole['main-document']['selectedEntryNames']);
        $t->same(['word/document.xml'], $byRole['main-document']['handoffEntryNames']);
        $t->same([], $byRole['main-document']['issueCounts']);

        $t->same(1, $byRole['required-sidecar']['requestCount']);
        $t->same(1, $byRole['required-sidecar']['requiredCount']);
        $t->same(0, $byRole['required-sidecar']['presentEntryCount']);
        $t->same(1, $byRole['required-sidecar']['missingEntryCount']);
        $t->same(0, $byRole['required-sidecar']['handoffEntryCount']);
        $t->same(0, $byRole['required-sidecar']['handoffUniqueEntryCount']);
        $t->same(1, $byRole['required-sidecar']['failedEntryCount']);
        $t->same(0, $byRole['required-sidecar']['handoffUncompressedBytes']);
        $t->same([], $byRole['required-sidecar']['handoffEntryNames']);
        $t->same(['word/missing.xml'], $byRole['required-sidecar']['missingEntryNames']);
        $t->same(['word/missing.xml'], $byRole['required-sidecar']['failedEntryNames']);
        $t->same(['missing-required-entry'], $byRole['required-sidecar']['issues']);
        $t->same(['missing-required-entry' => 1], $byRole['required-sidecar']['issueCounts']);

        $manifest = $summary['selectedHandoffManifest'];
        $manifestPayload = $manifest;
        unset($manifestPayload['manifestSha256']);
        $manifestJson = json_encode(
            $manifestPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $t->same('zip-selected-handoff-manifest-v1', $summary['selectedHandoffManifestVersion']);
        $t->same('zip-selected-handoff-manifest-v1', $manifest['manifestVersion']);
        $t->same(hash('sha256', $manifestJson), $summary['selectedHandoffManifestSha256']);
        $t->same($summary['selectedHandoffManifestSha256'], $manifest['manifestSha256']);
        $t->same(6, $manifest['requestedEntryCount']);
        $t->same(4, $manifest['presentRequestCount']);
        $t->same(2, $manifest['missingRequestCount']);
        $t->same(1, $manifest['handoffRequestCount']);
        $t->same(4, $manifest['failedRequestCount']);
        $t->same(1, $manifest['contentHashEntryCount']);
        $t->same(3, $manifest['issueCount']);
        $t->same([
            'duplicate-selected-entry-request',
            'entry-uncompressed-size-exceeds-limit',
            'missing-required-entry',
        ], $manifest['issues']);
        $t->same([
            'duplicate-selected-entry-request' => 2,
            'entry-uncompressed-size-exceeds-limit' => 1,
            'missing-required-entry' => 1,
        ], $manifest['issueCounts']);
        $t->same(['attachment', 'main-document', 'required-sidecar'], $manifest['roles']);
        $t->same(true, $manifest['hasUnassignedRole']);
        $t->same([
            'request-path' => 2,
            'zip-entry' => 4,
        ], $manifest['packagePathIdentitySourceCounts']);
        $t->same([
            'request-path' => ['word/missing.xml', 'word/optional.xml'],
            'zip-entry' => ['word/document.xml', 'word/media/image.png', 'word/media/large.bin'],
        ], $manifest['entryNamesByPackagePathIdentitySource']);
        $t->same([
            [
                'requestIndex' => 0,
                'name' => 'word/document.xml',
                'role' => 'main-document',
                'status' => 'ready',
                'exists' => true,
                'isReadable' => true,
                'bytesRead' => strlen($documentXml),
                'contentSha256' => hash('sha256', $documentXml),
                'issues' => [],
            ],
            [
                'requestIndex' => 1,
                'name' => 'word/media/image.png',
                'role' => 'attachment',
                'status' => 'blocked',
                'exists' => true,
                'isReadable' => false,
                'bytesRead' => null,
                'contentSha256' => null,
                'issues' => ['duplicate-selected-entry-request'],
            ],
            [
                'requestIndex' => 2,
                'name' => 'word/media/image.png',
                'role' => 'attachment',
                'status' => 'blocked',
                'exists' => true,
                'isReadable' => false,
                'bytesRead' => null,
                'contentSha256' => null,
                'issues' => ['duplicate-selected-entry-request'],
            ],
            [
                'requestIndex' => 3,
                'name' => 'word/media/large.bin',
                'role' => 'attachment',
                'status' => 'blocked',
                'exists' => true,
                'isReadable' => false,
                'bytesRead' => null,
                'contentSha256' => null,
                'issues' => ['entry-uncompressed-size-exceeds-limit'],
            ],
            [
                'requestIndex' => 4,
                'name' => 'word/missing.xml',
                'role' => 'required-sidecar',
                'status' => 'missing-required',
                'exists' => false,
                'isReadable' => false,
                'bytesRead' => null,
                'contentSha256' => null,
                'issues' => ['missing-required-entry'],
            ],
            [
                'requestIndex' => 5,
                'name' => 'word/optional.xml',
                'role' => null,
                'status' => 'missing-optional',
                'exists' => false,
                'isReadable' => false,
                'bytesRead' => null,
                'contentSha256' => null,
                'issues' => [],
            ],
        ], array_map(
            static fn (array $entry): array => [
                'requestIndex' => $entry['requestIndex'],
                'name' => $entry['name'],
                'role' => $entry['role'],
                'status' => $entry['status'],
                'exists' => $entry['exists'],
                'isReadable' => $entry['isReadable'],
                'bytesRead' => $entry['bytesRead'],
                'contentSha256' => $entry['contentSha256'],
                'issues' => $entry['issues'],
            ],
            $manifest['entries']
        ));
    },

    'summarizes selected zip handoff manifest for package review' => static function (TestRunner $t) use ($buildZipPackage, $pathSegmentPositionReviews): void {
        $documentXml = '<w:document><w:body><w:p>manifest handoff</w:p></w:body></w:document>';
        $mediaBytes = "manifest image bytes\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 0,
            ],
            [
                'name' => 'word/media/image.png',
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => 'word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/media/image.png', 'required' => false, 'kind' => 'file', 'role' => 'media', 'maxUncompressedBytes' => 4],
            ['name' => 'docProps/missing.xml', 'required' => false, 'kind' => 'file', 'role' => 'metadata'],
        ], 1024);
        $manifest = $summary['selectedHandoffManifest'];
        $manifestPayload = $manifest;
        unset($manifestPayload['manifestSha256']);
        $manifestJson = json_encode(
            $manifestPayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $t->same('zip-selected-handoff-manifest-v1', $manifest['manifestVersion']);
        $t->same(hash('sha256', $manifestJson), $manifest['manifestSha256']);
        $t->same($manifest['manifestSha256'], $summary['selectedHandoffManifestSha256']);
        $t->same(3, $manifest['requestedEntryCount']);
        $t->same(2, $manifest['presentRequestCount']);
        $t->same(1, $manifest['missingRequestCount']);
        $t->same(1, $manifest['handoffRequestCount']);
        $t->same(1, $manifest['failedRequestCount']);
        $t->same(1, $manifest['contentHashEntryCount']);
        $t->same(1, $manifest['issueCount']);
        $t->same(['entry-uncompressed-size-exceeds-limit'], $manifest['issues']);
        $t->same(['entry-uncompressed-size-exceeds-limit' => 1], $manifest['issueCounts']);
        $t->same(['main-document', 'media', 'metadata'], $manifest['roles']);
        $t->same(false, $manifest['hasUnassignedRole']);
        $t->same([
            'request-path' => 1,
            'zip-entry' => 2,
        ], $manifest['packagePathIdentitySourceCounts']);
        $t->same([
            'request-path' => ['docProps/missing.xml'],
            'zip-entry' => ['word/document.xml', 'word/media/image.png'],
        ], $manifest['entryNamesByPackagePathIdentitySource']);
        $t->same('zip-entry', $manifest['entries'][0]['packagePathIdentitySource']);
        $t->same(['word', 'document.xml'], $manifest['entries'][0]['pathSegments']);
        $t->same($pathSegmentPositionReviews(['word', 'document.xml']), $manifest['entries'][0]['pathSegmentPositionReviews']);
        $t->same(2, $manifest['entries'][0]['pathSegmentCount']);
        $t->same(1, $manifest['entries'][0]['directoryDepth']);
        $t->same('document.xml', $manifest['entries'][0]['packagePartBaseName']);
        $t->same('document', $manifest['entries'][0]['packagePartBaseNameStem']);
        $t->same('xml', $manifest['entries'][0]['packagePartExtensionKey']);
        $t->same('zip-entry', $manifest['entries'][1]['packagePathIdentitySource']);
        $t->same(['word', 'media', 'image.png'], $manifest['entries'][1]['pathSegments']);
        $t->same($pathSegmentPositionReviews(['word', 'media', 'image.png']), $manifest['entries'][1]['pathSegmentPositionReviews']);
        $t->same(3, $manifest['entries'][1]['pathSegmentCount']);
        $t->same(2, $manifest['entries'][1]['directoryDepth']);
        $t->same('image.png', $manifest['entries'][1]['packagePartBaseName']);
        $t->same('image', $manifest['entries'][1]['packagePartBaseNameStem']);
        $t->same('png', $manifest['entries'][1]['packagePartExtensionKey']);
        $t->same('request-path', $manifest['entries'][2]['packagePathIdentitySource']);
        $t->same('docProps/', $manifest['entries'][2]['directoryRoot']);
        $t->same(['docProps', 'missing.xml'], $manifest['entries'][2]['pathSegments']);
        $t->same($pathSegmentPositionReviews(['docProps', 'missing.xml']), $manifest['entries'][2]['pathSegmentPositionReviews']);
        $t->same(2, $manifest['entries'][2]['pathSegmentCount']);
        $t->same(1, $manifest['entries'][2]['directoryDepth']);
        $t->same('missing.xml', $manifest['entries'][2]['packagePartBaseName']);
        $t->same('missing', $manifest['entries'][2]['packagePartBaseNameStem']);
        $t->same('xml', $manifest['entries'][2]['packagePartExtension']);
        $t->same('xml', $manifest['entries'][2]['packagePartExtensionKey']);
        $t->same(false, $manifest['entries'][2]['extensionlessPackagePart']);
        $t->same([
            [
                'name' => 'word/document.xml',
                'role' => 'main-document',
                'status' => 'ready',
                'exists' => true,
                'isReadable' => true,
                'contentSha256' => hash('sha256', $documentXml),
                'issues' => [],
            ],
            [
                'name' => 'word/media/image.png',
                'role' => 'media',
                'status' => 'blocked',
                'exists' => true,
                'isReadable' => false,
                'contentSha256' => null,
                'issues' => ['entry-uncompressed-size-exceeds-limit'],
            ],
            [
                'name' => 'docProps/missing.xml',
                'role' => 'metadata',
                'status' => 'missing-optional',
                'exists' => false,
                'isReadable' => false,
                'contentSha256' => null,
                'issues' => [],
            ],
        ], array_map(
            static fn (array $entry): array => [
                'name' => $entry['name'],
                'role' => $entry['role'],
                'status' => $entry['status'],
                'exists' => $entry['exists'],
                'isReadable' => $entry['isReadable'],
                'contentSha256' => $entry['contentSha256'],
                'issues' => $entry['issues'],
            ],
            $manifest['entries']
        ));
    },

    'summarizes selected zip handoff directory roots for package review' => static function (TestRunner $t) use ($buildZipPackage): void {
        $contentTypesXml = '<Types/>';
        $packageRelsXml = '<Relationships/>';
        $coreXml = '<cp:coreProperties/>';
        $documentXml = '<w:document><w:body><w:p>directory root handoff</w:p></w:body></w:document>';
        $documentRelsXml = '<Relationships><Relationship Id="rIdImage" Target="media/image.png"/></Relationships>';
        $imageBytes = "image bytes\n";
        $largeBytes = "large selected media bytes\n";

        $package = ZipPackage::fromString($buildZipPackage([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'method' => 0],
            ['name' => '_rels/.rels', 'data' => $packageRelsXml, 'method' => 0],
            ['name' => 'docProps/core.xml', 'data' => $coreXml, 'method' => 0],
            ['name' => 'word/document.xml', 'data' => $documentXml, 'method' => 0],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelsXml, 'method' => 0],
            ['name' => 'word/media/', 'data' => '', 'method' => 0],
            ['name' => 'word/media/image.png', 'data' => $imageBytes, 'method' => 0],
            ['name' => 'word/media/large.bin', 'data' => $largeBytes, 'method' => 0],
        ]));

        $summary = $package->entryHandoffPreflight([
            ['name' => '[Content_Types].xml', 'required' => true, 'kind' => 'file', 'role' => 'content-types'],
            ['name' => '_rels/.rels', 'required' => true, 'kind' => 'file', 'role' => 'root-relationships'],
            ['name' => 'docProps/core.xml', 'required' => false, 'kind' => 'file', 'role' => 'metadata'],
            ['name' => 'word/document.xml', 'required' => true, 'kind' => 'file', 'role' => 'main-document'],
            ['name' => 'word/_rels/document.xml.rels', 'required' => false, 'kind' => 'file', 'role' => 'document-relationships'],
            ['name' => 'word/media/', 'required' => false, 'kind' => 'directory', 'role' => 'media-directory'],
            ['name' => 'word/media/image.png', 'required' => false, 'kind' => 'file', 'role' => 'media'],
            ['name' => 'word/media/large.bin', 'required' => false, 'kind' => 'file', 'role' => 'media', 'maxUncompressedBytes' => 8],
            ['name' => 'customXml/item1.xml', 'required' => false, 'kind' => 'file', 'role' => 'custom-xml'],
        ], 1024);

        $selectedByRoot = [];
        foreach ($summary['selectedDirectoryRootSummaries'] as $rootSummary) {
            $selectedByRoot[$rootSummary['directoryRoot']] = $rootSummary;
        }
        $handoffByRoot = [];
        foreach ($summary['handoffDirectoryRootSummaries'] as $rootSummary) {
            $handoffByRoot[$rootSummary['directoryRoot']] = $rootSummary;
        }
        $selectedByExtension = [];
        foreach ($summary['selectedPackagePartExtensionSummaries'] as $extensionSummary) {
            $selectedByExtension[$extensionSummary['extensionKey']] = $extensionSummary;
        }
        $handoffByExtension = [];
        foreach ($summary['handoffPackagePartExtensionSummaries'] as $extensionSummary) {
            $handoffByExtension[$extensionSummary['extensionKey']] = $extensionSummary;
        }

        $t->same(4, $summary['selectedDirectoryRootCount']);
        $t->same(4, $summary['handoffDirectoryRootCount']);
        $t->same(['/', '_rels/', 'docProps/', 'word/'], array_keys($selectedByRoot));
        $t->same(['/', '_rels/', 'docProps/', 'word/'], array_keys($handoffByRoot));
        $t->same(4, $summary['selectedPackagePartExtensionSummaryCount']);
        $t->same(['bin', 'png', 'rels', 'xml'], $summary['selectedPackagePartExtensions']);
        $t->same(0, $summary['selectedExtensionlessPackagePartCount']);
        $t->same(false, $summary['selectedHasExtensionlessPackageParts']);
        $t->same(3, $summary['handoffPackagePartExtensionSummaryCount']);
        $t->same(['png', 'rels', 'xml'], $summary['handoffPackagePartExtensions']);
        $t->same(0, $summary['handoffExtensionlessPackagePartCount']);
        $t->same(false, $summary['handoffHasExtensionlessPackageParts']);
        $t->same(['bin', 'png', 'rels', 'xml'], array_keys($selectedByExtension));
        $t->same(['png', 'rels', 'xml'], array_keys($handoffByExtension));

        $t->same(1, $selectedByRoot['/']['entryCount']);
        $t->same(1, $selectedByRoot['/']['fileEntryCount']);
        $t->same(0, $selectedByRoot['/']['directoryEntryCount']);
        $t->same(strlen($contentTypesXml), $selectedByRoot['/']['uncompressedBytes']);
        $t->same(['[Content_Types].xml'], $selectedByRoot['/']['entryNames']);
        $t->same(['content-types'], $selectedByRoot['/']['roles']);

        $wordSelectedBytes = strlen($documentXml) + strlen($documentRelsXml) + strlen($imageBytes) + strlen($largeBytes);
        $t->same(5, $selectedByRoot['word/']['entryCount']);
        $t->same(4, $selectedByRoot['word/']['fileEntryCount']);
        $t->same(1, $selectedByRoot['word/']['directoryEntryCount']);
        $t->same($wordSelectedBytes, $selectedByRoot['word/']['uncompressedBytes']);
        $t->same([
            'word/document.xml',
            'word/_rels/document.xml.rels',
            'word/media/',
            'word/media/image.png',
            'word/media/large.bin',
        ], $selectedByRoot['word/']['entryNames']);
        $t->same(['document-relationships', 'main-document', 'media', 'media-directory'], $selectedByRoot['word/']['roles']);

        $wordHandoffBytes = strlen($documentXml) + strlen($documentRelsXml) + strlen($imageBytes);
        $t->same(4, $handoffByRoot['word/']['entryCount']);
        $t->same(3, $handoffByRoot['word/']['fileEntryCount']);
        $t->same(1, $handoffByRoot['word/']['directoryEntryCount']);
        $t->same($wordHandoffBytes, $handoffByRoot['word/']['uncompressedBytes']);
        $t->same([
            'word/document.xml',
            'word/_rels/document.xml.rels',
            'word/media/',
            'word/media/image.png',
        ], $handoffByRoot['word/']['entryNames']);
        $t->same(['document-relationships', 'main-document', 'media', 'media-directory'], $handoffByRoot['word/']['roles']);
        $t->same(1, $selectedByExtension['bin']['fileEntryCount']);
        $t->same(strlen($largeBytes), $selectedByExtension['bin']['uncompressedBytes']);
        $t->same(['word/media/large.bin'], $selectedByExtension['bin']['entryNames']);
        $t->same(['media'], $selectedByExtension['bin']['roles']);
        $t->same(2, $selectedByExtension['rels']['fileEntryCount']);
        $t->same(strlen($packageRelsXml) + strlen($documentRelsXml), $selectedByExtension['rels']['uncompressedBytes']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $selectedByExtension['rels']['entryNames']);
        $t->same(['document-relationships', 'root-relationships'], $selectedByExtension['rels']['roles']);
        $t->same(3, $selectedByExtension['xml']['fileEntryCount']);
        $t->same(strlen($contentTypesXml) + strlen($coreXml) + strlen($documentXml), $selectedByExtension['xml']['uncompressedBytes']);
        $t->same(['[Content_Types].xml', 'docProps/core.xml', 'word/document.xml'], $selectedByExtension['xml']['entryNames']);
        $t->same(['content-types', 'main-document', 'metadata'], $selectedByExtension['xml']['roles']);
        $t->same(1, $handoffByExtension['png']['fileEntryCount']);
        $t->same(strlen($imageBytes), $handoffByExtension['png']['uncompressedBytes']);
        $t->same(['word/media/image.png'], $handoffByExtension['png']['entryNames']);
        $t->same(false, isset($handoffByExtension['bin']));
        $t->same(['entry-uncompressed-size-exceeds-limit'], $summary['issues']);
        $t->same('word/', $summary['entries'][6]['directoryRoot']);
        $t->same('customXml/', $summary['entries'][8]['directoryRoot']);
        $t->same('request-path', $summary['entries'][8]['packagePathIdentitySource']);
    },

    'preflights aggregate zip package expansion before exposing media bytes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>aggregate package preflight</w:p></w:body></w:document>';
        $mediaBytes = str_repeat("review media bytes\n", 24);
        $storedBytes = "stored reviewer note\n";

        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $storedBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $documentCompressed = strlen(gzdeflate($documentXml));
        $mediaCompressed = strlen(gzdeflate($mediaBytes));
        $storedCompressed = strlen($storedBytes);
        $totalCompressed = $documentCompressed + $mediaCompressed + $storedCompressed;
        $totalUncompressed = strlen($documentXml) + strlen($mediaBytes) + strlen($storedBytes);
        $summary = $package->sizePreflight();

        $t->same(4, $summary['entryCount']);
        $t->same(3, $summary['fileCount']);
        $t->same(1, $summary['directoryCount']);
        $t->same(2, $summary['deflatedEntryCount']);
        $t->same(2, $summary['storedEntryCount']);
        $t->same(0, $summary['unsupportedCompressionMethodCount']);
        $t->same($totalCompressed, $summary['compressedBytes']);
        $t->same($totalUncompressed, $summary['uncompressedBytes']);
        $t->same($totalUncompressed / $totalCompressed, $summary['expansionRatio']);
        $t->same('word/media/review.bin', $summary['largestEntry']['name']);
        $t->same(strlen($mediaBytes), $summary['largestEntry']['uncompressedSize']);
        $t->same($mediaCompressed, $summary['largestEntry']['compressedSize']);
        $t->same($mediaCompressed === 0 ? null : strlen($mediaBytes) / $mediaCompressed, $summary['largestEntry']['expansionRatio']);
        $t->same('word/media/review.bin', $summary['entries'][1]['name']);
        $t->same(strlen($mediaBytes), $summary['entries'][1]['uncompressedSize']);
        $t->same($summary, $package->assertSizePreflight($totalUncompressed, $summary['expansionRatio']));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertSizePreflight($totalUncompressed - 1, null));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertSizePreflight(null, $summary['expansionRatio'] - 0.01));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $package->assertSizePreflight(-1, null));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $package->assertSizePreflight(null, -0.01));

        $zeroCompressed = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/media/zero-compressed.bin',
                'data' => '',
                'method' => 12,
                'centralCompressedSize' => 0,
                'centralUncompressedSize' => 37,
                'localCompressedSize' => 0,
                'localUncompressedSize' => 37,
            ],
        ]));
        $zeroCompressedSummary = $zeroCompressed->sizePreflight();
        $t->same(null, $zeroCompressedSummary['expansionRatio']);
        $t->same(1, $zeroCompressedSummary['unknownExpansionRatioEntryCount']);
        $t->same(true, $zeroCompressedSummary['hasUnknownExpansionRatioEntries']);
        $t->same('word/media/zero-compressed.bin', $zeroCompressedSummary['unknownExpansionRatioEntries'][0]['name']);
        $t->same($zeroCompressedSummary['entries'][0], $zeroCompressedSummary['unknownExpansionRatioEntries'][0]);
        $t->throws(\RuntimeException::class, static fn (): array => $zeroCompressed->assertSizePreflight(null, 10.0));
    },

    'preflights zero byte zip entry buckets before package byte handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>zero byte package preflight</w:p></w:body></w:document>';
        $mediaBytes = "active media bytes\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/empty.bin',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'word/media/empty-deflated.bin',
                'data' => '',
                'method' => 8,
            ],
            [
                'name' => 'word/media/empty-dir/',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'word/media/review.bin',
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ]));
        $documentCompressed = strlen(gzdeflate($documentXml));
        $emptyDeflatedCompressed = strlen(gzdeflate(''));
        $totalCompressed = $documentCompressed + $emptyDeflatedCompressed + strlen($mediaBytes);
        $totalUncompressed = strlen($documentXml) + strlen($mediaBytes);
        $emptyStoredFile = [
            'name' => 'word/media/empty.bin',
            'compressionMethod' => 0,
            'isDirectory' => false,
            'compressedSize' => 0,
            'uncompressedSize' => 0,
            'expansionRatio' => 0.0,
        ];
        $emptyDeflatedFile = [
            'name' => 'word/media/empty-deflated.bin',
            'compressionMethod' => 8,
            'isDirectory' => false,
            'compressedSize' => $emptyDeflatedCompressed,
            'uncompressedSize' => 0,
            'expansionRatio' => 0.0,
        ];
        $emptyDirectory = [
            'name' => 'word/media/empty-dir/',
            'compressionMethod' => 0,
            'isDirectory' => true,
            'compressedSize' => 0,
            'uncompressedSize' => 0,
            'expansionRatio' => 0.0,
        ];
        $summary = $package->sizePreflight();
        $manifest = $package->packageManifestPreflight();

        $t->same(5, $summary['entryCount']);
        $t->same(4, $summary['fileCount']);
        $t->same(1, $summary['directoryCount']);
        $t->same(3, $summary['zeroByteEntryCount']);
        $t->same(2, $summary['zeroByteFileCount']);
        $t->same(1, $summary['emptyDirectoryEntryCount']);
        $t->same(true, $summary['hasZeroByteEntries']);
        $t->same($totalCompressed, $summary['compressedBytes']);
        $t->same($totalUncompressed, $summary['uncompressedBytes']);
        $t->same([$emptyStoredFile, $emptyDeflatedFile, $emptyDirectory], $summary['zeroByteEntries']);
        $t->same($emptyStoredFile, $summary['entries'][1]);
        $t->same($emptyDeflatedFile, $summary['entries'][2]);
        $t->same($emptyDirectory, $summary['entries'][3]);
        $t->same('word/document.xml', $summary['largestEntry']['name']);
        $t->same($summary, $package->assertSizePreflight($totalUncompressed, 100.0));
        $t->same($summary['expansionRatio'], $manifest['expansionRatio']);
        $t->same($summary['largestEntry'], $manifest['largestEntry']);
        $t->same($summary['zeroByteEntryCount'], $manifest['zeroByteEntryCount']);
        $t->same($summary['zeroByteFileCount'], $manifest['zeroByteFileCount']);
        $t->same($summary['emptyDirectoryEntryCount'], $manifest['emptyDirectoryEntryCount']);
        $t->same($summary['hasZeroByteEntries'], $manifest['hasZeroByteEntries']);
        $t->same($summary['zeroByteEntries'], $manifest['zeroByteEntries']);
        $t->same($summary['unknownExpansionRatioEntryCount'], $manifest['unknownExpansionRatioEntryCount']);
        $t->same($summary['hasUnknownExpansionRatioEntries'], $manifest['hasUnknownExpansionRatioEntries']);
        $t->same($summary['unknownExpansionRatioEntries'], $manifest['unknownExpansionRatioEntries']);

        $strictSummary = $package->strictImportPreflight(2048, 100.0, 2048);
        $rawStrict = ZipPackage::rawStrictImportPreflight($package->bytes(), 2048, 100.0, 2048);
        $t->same(true, $strictSummary['isValid']);
        $t->same($summary, $strictSummary['size']);
        $t->same(true, $rawStrict['canInstantiate']);
        $t->same($summary, $rawStrict['strictImport']['size']);
    },

    'preflights central directory byte accounting before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>' . str_repeat('central size accounting ', 32) . '</w:p></w:body></w:document>';
        $mediaBytes = "stored media bytes for review\n";
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/spoofed-document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $mediaBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]);
        $documentCompressed = strlen(gzdeflate($documentXml));
        $totalCompressed = $documentCompressed + strlen($mediaBytes);
        $totalUncompressed = strlen($documentXml) + strlen($mediaBytes);

        $summary = ZipPackage::centralDirectorySizePreflight($zip, 128, 2.0);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, 128, 2.0, 4096);

        $t->same(3, $summary['declaredEntryCount']);
        $t->same(3, $summary['scannedEntryCount']);
        $t->same(false, $summary['hasEntryCountMismatch']);
        $t->same(2, $summary['fileCount']);
        $t->same(1, $summary['directoryCount']);
        $t->same(2, $summary['storedEntryCount']);
        $t->same(1, $summary['deflatedEntryCount']);
        $t->same(0, $summary['unsupportedCompressionMethodCount']);
        $t->same(0, $summary['unknownExpansionRatioEntryCount']);
        $t->same(false, $summary['hasUnknownExpansionRatioEntries']);
        $t->same([], $summary['unknownExpansionRatioEntries']);
        $t->same($totalCompressed, $summary['compressedBytes']);
        $t->same($totalUncompressed, $summary['uncompressedBytes']);
        $t->same(true, $summary['totalsAreExact']);
        $t->same(false, $summary['hasUnknownByteCounts']);
        $t->same(0, $summary['zip64SizeSentinelEntryCount']);
        $t->same($totalUncompressed / $totalCompressed, $summary['expansionRatio']);
        $t->same('word/document.xml', $summary['largestEntry']['name']);
        $t->same($documentCompressed, $summary['largestEntry']['compressedSize']);
        $t->same(strlen($documentXml), $summary['largestEntry']['uncompressedSize']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['total-uncompressed-size-exceeds-limit', 'expansion-ratio-exceeds-limit'], $summary['issues']);
        $t->same('word/media/review.txt', $summary['entries'][1]['name']);
        $t->same('stored', $summary['entries'][1]['compressionMethodName']);
        $t->same(strlen($mediaBytes), $summary['entries'][1]['compressedSize']);
        $t->same(strlen($mediaBytes), $summary['entries'][1]['uncompressedSize']);

        $t->same($summary, $rawStrict['centralDirectorySize']);
        $t->same(false, $rawStrict['isValid']);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->same(null, $rawStrict['strictImport']);
        $t->contains('total-uncompressed-size-exceeds-limit', implode(',', $rawStrict['diagnostics']));
        $t->contains('expansion-ratio-exceeds-limit', implode(',', $rawStrict['diagnostics']));
        $t->contains('local-header-name-issues', implode(',', $rawStrict['diagnostics']));
        $t->contains('zip-package-instantiation-failed', implode(',', $rawStrict['diagnostics']));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));
    },

    'rejects excessive entry counts before package materialization' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            ['name' => 'one.txt', 'data' => 'one', 'method' => 0],
            ['name' => 'two.txt', 'data' => 'two', 'method' => 0],
            ['name' => 'three.txt', 'data' => 'three', 'method' => 0],
        ]);

        $limited = ZipPackage::rawStrictImportPreflight($zip, null, null, null, 2);
        $t->same(3, $limited['entryCount']);
        $t->same(2, $limited['maxEntryCount']);
        $t->same(false, $limited['canInstantiate']);
        $t->same(false, $limited['isValid']);
        $t->same(null, $limited['strictImport']);
        $t->same(null, $limited['centralDirectoryInventory']);
        $t->contains('entry-count-exceeds-limit', implode(',', $limited['diagnostics']));
        $t->throws(
            \RuntimeException::class,
            static fn (): ZipPackage => ZipPackage::fromString($zip, 2)
        );

        $boundary = ZipPackage::rawStrictImportPreflight($zip, null, null, null, 3);
        $t->same(3, $boundary['maxEntryCount']);
        $t->same(true, $boundary['canInstantiate']);
        $t->same(true, $boundary['isValid']);
        $t->same(3, $boundary['strictImport']['entryCount'] ?? null);
        $t->same(3, count(ZipPackage::fromString($zip, 3)->entries()));

        $t->throws(
            \InvalidArgumentException::class,
            static fn (): array => ZipPackage::rawStrictImportPreflight($zip, null, null, null, -1)
        );
        $t->throws(
            \InvalidArgumentException::class,
            static fn (): ZipPackage => ZipPackage::fromString($zip, -1)
        );
    },

    'summarizes central directory expansion ratio buckets before package instantiation' => static function (TestRunner $t) use ($buildZipPackage): void {
        $storedBytes = "stored central directory review\n";
        $highExpansionBytes = str_repeat('A', 5000);
        $unknownUncompressedSize = 37;
        $zip = $buildZipPackage([
            [
                'name' => 'empty.bin',
                'data' => '',
                'method' => 0,
            ],
            [
                'name' => 'docProps/core.xml',
                'data' => $storedBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/high.bin',
                'data' => $highExpansionBytes,
                'method' => 8,
            ],
            [
                'name' => 'word/media/unknown.bin',
                'data' => '',
                'method' => 12,
                'centralCompressedSize' => 0,
                'centralUncompressedSize' => $unknownUncompressedSize,
                'localCompressedSize' => 0,
                'localUncompressedSize' => $unknownUncompressedSize,
            ],
        ]);
        $highExpansionCompressedSize = strlen(gzdeflate($highExpansionBytes));

        $summary = ZipPackage::centralDirectorySizePreflight($zip, null, 100.0);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, null, 100.0, 8192);
        $buckets = array_column($summary['expansionRatioBucketSummaries'], null, 'expansionRatioBucket');

        $t->same(4, $summary['expansionRatioBucketSummaryCount']);
        $t->same(['zero-byte', 'up-to-1x', 'over-100x', 'unknown'], $summary['expansionRatioBuckets']);
        $t->same('zero-byte', $summary['entries'][0]['expansionRatioBucket']);
        $t->same('up-to-1x', $summary['entries'][1]['expansionRatioBucket']);
        $t->same('over-100x', $summary['entries'][2]['expansionRatioBucket']);
        $t->same('unknown', $summary['entries'][3]['expansionRatioBucket']);
        $t->same('/', $summary['entries'][0]['directoryRoot']);
        $t->same('docProps/', $summary['entries'][1]['directoryRoot']);
        $t->same('word/', $summary['entries'][2]['directoryRoot']);
        $t->same('word/', $summary['entries'][3]['directoryRoot']);

        $t->same(1, $buckets['zero-byte']['entryCount']);
        $t->same(0, $buckets['zero-byte']['compressedBytes']);
        $t->same(0, $buckets['zero-byte']['uncompressedBytes']);
        $t->same(['empty.bin'], $buckets['zero-byte']['entryNames']);
        $t->same(['/'], $buckets['zero-byte']['directoryRoots']);
        $t->same(['stored'], $buckets['zero-byte']['compressionMethodNames']);

        $t->same(1, $buckets['up-to-1x']['entryCount']);
        $t->same(strlen($storedBytes), $buckets['up-to-1x']['compressedBytes']);
        $t->same(strlen($storedBytes), $buckets['up-to-1x']['uncompressedBytes']);
        $t->same(['docProps/core.xml'], $buckets['up-to-1x']['entryNames']);
        $t->same(['docProps/'], $buckets['up-to-1x']['directoryRoots']);
        $t->same(['stored'], $buckets['up-to-1x']['compressionMethodNames']);

        $t->same(1, $buckets['over-100x']['entryCount']);
        $t->same($highExpansionCompressedSize, $buckets['over-100x']['compressedBytes']);
        $t->same(strlen($highExpansionBytes), $buckets['over-100x']['uncompressedBytes']);
        $t->same('word/media/high.bin', $buckets['over-100x']['largestExpansionRatioEntryName']);
        $t->same(strlen($highExpansionBytes) / $highExpansionCompressedSize, $buckets['over-100x']['largestExpansionRatio']);
        $t->same(['word/media/high.bin'], $buckets['over-100x']['entryNames']);
        $t->same(['word/'], $buckets['over-100x']['directoryRoots']);
        $t->same(['deflated'], $buckets['over-100x']['compressionMethodNames']);

        $t->same(1, $buckets['unknown']['entryCount']);
        $t->same(1, $buckets['unknown']['unknownExpansionRatioEntryCount']);
        $t->same(0, $buckets['unknown']['compressedBytes']);
        $t->same($unknownUncompressedSize, $buckets['unknown']['uncompressedBytes']);
        $t->same(['word/media/unknown.bin'], $buckets['unknown']['entryNames']);
        $t->same(['word/'], $buckets['unknown']['directoryRoots']);
        $t->same(['unsupported'], $buckets['unknown']['compressionMethodNames']);
        $t->same(1, $summary['unknownExpansionRatioEntryCount']);
        $t->same(['expansion-ratio-unknown'], $summary['issues']);
        $t->same($summary, $rawStrict['centralDirectorySize']);
        $t->contains('expansion-ratio-unknown', implode(',', $rawStrict['diagnostics']));
    },

    'preflights zero compressed zip entry expansion provenance before package handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>zero compressed review</w:p></w:body></w:document>';
        $zeroName = 'word/media/zero-compressed.bin';
        $zeroUncompressedSize = 37;
        $zip = $buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => $zeroName,
                'data' => '',
                'method' => 12,
                'centralCompressedSize' => 0,
                'centralUncompressedSize' => $zeroUncompressedSize,
                'localCompressedSize' => 0,
                'localUncompressedSize' => $zeroUncompressedSize,
            ],
        ]);
        $package = ZipPackage::fromString($zip);
        $documentCompressed = strlen(gzdeflate($documentXml));
        $totalUncompressed = strlen($documentXml) + $zeroUncompressedSize;

        $expectedSizeEntry = [
            'name' => $zeroName,
            'compressionMethod' => 12,
            'isDirectory' => false,
            'compressedSize' => 0,
            'uncompressedSize' => $zeroUncompressedSize,
            'expansionRatio' => null,
        ];

        $summary = $package->sizePreflight();
        $manifest = $package->packageManifestPreflight();
        $strict = $package->strictImportPreflight(4096, 100.0, 4096);
        $centralSummary = ZipPackage::centralDirectorySizePreflight($zip, null, 100.0);
        $rawStrict = ZipPackage::rawStrictImportPreflight($zip, null, 100.0, 4096);

        $t->same(2, $summary['entryCount']);
        $t->same($documentCompressed, $summary['compressedBytes']);
        $t->same($totalUncompressed, $summary['uncompressedBytes']);
        $t->same($totalUncompressed / $documentCompressed, $summary['expansionRatio']);
        $t->same(1, $summary['unknownExpansionRatioEntryCount']);
        $t->same(true, $summary['hasUnknownExpansionRatioEntries']);
        $t->same($expectedSizeEntry, $summary['unknownExpansionRatioEntries'][0]);
        $t->same($expectedSizeEntry, $summary['entries'][1]);
        $t->same($summary['expansionRatio'], $manifest['expansionRatio']);
        $t->same($summary['largestEntry'], $manifest['largestEntry']);
        $t->same($summary['zeroByteEntryCount'], $manifest['zeroByteEntryCount']);
        $t->same($summary['hasZeroByteEntries'], $manifest['hasZeroByteEntries']);
        $t->same($summary['unknownExpansionRatioEntryCount'], $manifest['unknownExpansionRatioEntryCount']);
        $t->same($summary['hasUnknownExpansionRatioEntries'], $manifest['hasUnknownExpansionRatioEntries']);
        $t->same($summary['unknownExpansionRatioEntries'], $manifest['unknownExpansionRatioEntries']);

        $t->same(1, $centralSummary['unknownExpansionRatioEntryCount']);
        $t->same(true, $centralSummary['hasUnknownExpansionRatioEntries']);
        $t->same($zeroName, $centralSummary['unknownExpansionRatioEntries'][0]['name']);
        $t->same(0, $centralSummary['unknownExpansionRatioEntries'][0]['compressedSize']);
        $t->same($zeroUncompressedSize, $centralSummary['unknownExpansionRatioEntries'][0]['uncompressedSize']);
        $t->same(null, $centralSummary['unknownExpansionRatioEntries'][0]['expansionRatio']);
        $t->same(false, $centralSummary['isSupportedByBoundedReader']);
        $t->same(['expansion-ratio-unknown'], $centralSummary['issues']);

        $t->contains('expansion-ratio-unknown', implode(',', $strict['diagnostics']));
        $t->contains('unsupported-compression-methods', implode(',', $strict['diagnostics']));
        $t->contains('unreadable-entries', implode(',', $strict['diagnostics']));
        $t->same($summary, $strict['size']);

        $t->contains('expansion-ratio-unknown', implode(',', $rawStrict['diagnostics']));
        $t->same($centralSummary, $rawStrict['centralDirectorySize']);
        $t->same($summary, $rawStrict['strictImport']['size']);
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertSizePreflight(null, 100.0));
    },

    'preflights readable zip entry payloads before office package media handoff' => static function (TestRunner $t) use ($buildZipPackage, $corruptZipEntryPayload, $crc32): void {
        $documentXml = '<w:document><w:body><w:p>integrity preflight</w:p></w:body></w:document>';
        $mediaBytes = "review media payload bytes\n";
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $mediaBytes,
                'method' => 0,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ]));
        $summary = $package->readIntegrityPreflight(1024);

        $t->same(3, $summary['entryCount']);
        $t->same(3, $summary['readableEntryCount']);
        $t->same(0, $summary['failedEntryCount']);
        $t->same(1024, $summary['maxEntryUncompressedBytes']);
        $t->same([], $summary['failedEntries']);
        $t->same('word/document.xml', $summary['entries'][0]['name']);
        $t->same(true, $summary['entries'][0]['isReadable']);
        $t->same(strlen($documentXml), $summary['entries'][0]['bytesRead']);
        $t->same($crc32($documentXml), $summary['entries'][0]['crc32']);
        $t->same(sprintf('%08x', $crc32($documentXml)), $summary['entries'][0]['crc32Hex']);
        $t->same(hash('sha256', $documentXml), $summary['entries'][0]['contentSha256']);
        $t->same(hash('sha256', $mediaBytes), $summary['entries'][1]['contentSha256']);
        $t->same('word/media/', $summary['entries'][2]['name']);
        $t->same(true, $summary['entries'][2]['isDirectory']);
        $t->same(0, $summary['entries'][2]['bytesRead']);
        $t->same(hash('sha256', ''), $summary['entries'][2]['contentSha256']);
        $t->same($summary, $package->assertReadableEntries(1024));

        $corruptPackage = ZipPackage::fromString($corruptZipEntryPayload($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => $mediaBytes,
                'method' => 0,
            ],
        ]), 'word/document.xml'));
        $corruptSummary = $corruptPackage->readIntegrityPreflight();

        $t->same(2, $corruptSummary['entryCount']);
        $t->same(1, $corruptSummary['readableEntryCount']);
        $t->same(1, $corruptSummary['failedEntryCount']);
        $t->same(null, $corruptSummary['maxEntryUncompressedBytes']);
        $t->same('word/document.xml', $corruptSummary['failedEntries'][0]['name']);
        $t->same(false, $corruptSummary['entries'][0]['isReadable']);
        $t->same(null, $corruptSummary['entries'][0]['bytesRead']);
        $t->same(null, $corruptSummary['entries'][0]['contentSha256']);
        $t->same(null, $corruptSummary['failedEntries'][0]['contentSha256']);
        $t->contains('ZIP entry word/document.xml', $corruptSummary['entries'][0]['error']);
        $t->same(true, $corruptSummary['entries'][1]['isReadable']);
        $t->same(hash('sha256', $mediaBytes), $corruptSummary['entries'][1]['contentSha256']);
        $t->throws(\RuntimeException::class, static fn (): array => $corruptPackage->assertReadableEntries());
    },

    'rejects deflated zip payloads with trailing bytes before media handoff' => static function (TestRunner $t) use ($buildZipPackage): void {
        $documentXml = '<w:document><w:body><w:p>deflate trailing bytes</w:p></w:body></w:document>';
        $trailingBytes = 'hidden-review-tail';
        $declaredCompressedSize = strlen(gzdeflate($documentXml)) + strlen($trailingBytes);
        $package = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'localSlack' => $trailingBytes,
                'localCompressedSize' => $declaredCompressedSize,
                'centralCompressedSize' => $declaredCompressedSize,
            ],
            [
                'name' => 'word/media/review.txt',
                'data' => "review media payload remains readable\n",
                'method' => 0,
            ],
        ]));
        $summary = $package->readIntegrityPreflight();

        $t->same(2, $summary['entryCount']);
        $t->same(1, $summary['readableEntryCount']);
        $t->same(1, $summary['failedEntryCount']);
        $t->same('word/document.xml', $summary['failedEntries'][0]['name']);
        $t->contains('trailing bytes after the raw deflate stream', $summary['failedEntries'][0]['error']);
        $t->same(false, $summary['entries'][0]['isReadable']);
        $t->same(true, $summary['entries'][1]['isReadable']);
        $t->same("review media payload remains readable\n", $package->read('/word/media/review.txt'));
        $t->throws(\RuntimeException::class, static fn (): string => $package->read('/word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): array => $package->assertReadableEntries());
    },

    'builds and reads bounded gzip streams around package fixture bytes' => static function (TestRunner $t) use ($crc32): void {
        $package = ZipPackage::fromParts([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'compressionMethod' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:p>GZip wrapped import source</w:p></w:document>',
            ],
        ]);
        $extraFieldData = pack('CCv', ord('W'), ord('P'), strlen('review:v1')) . 'review:v1';
        $gzip = GzipStream::build($package->bytes(), [
            'modifiedAt' => 1780479017,
            'extraFlags' => 2,
            'operatingSystem' => 3,
            'extraFieldData' => $extraFieldData,
            'filename' => 'wordpress-package.zip',
            'comment' => 'migration source package',
            'headerCrc' => true,
            'compressionLevel' => 9,
        ]);
        $members = GzipStream::members($gzip);

        $t->same(1, count($members));
        $t->same($package->bytes(), GzipStream::decode($gzip));
        $t->same($package->bytes(), $members[0]['data']);
        $t->same(1780479017, $members[0]['modifiedAt']);
        $t->same(2, $members[0]['extraFlags']);
        $t->same(3, $members[0]['operatingSystem']);
        $t->same($extraFieldData, $members[0]['extraFieldData']);
        $t->same('WP', $members[0]['extraFields'][0]['identifier']);
        $t->same('review:v1', $members[0]['extraFields'][0]['data']);
        $t->same('wordpress-package.zip', $members[0]['filename']);
        $t->same('migration source package', $members[0]['comment']);
        $t->true(is_int($members[0]['headerCrc16']));
        $t->same($crc32($package->bytes()), $members[0]['crc32']);
        $t->same(strlen($package->bytes()), $members[0]['uncompressedSize']);
        $t->true($members[0]['compressedSize'] > 0);
        $t->same(strlen($gzip), $members[0]['memberSize']);
        $roundTrip = ZipPackage::fromString(GzipStream::decode($gzip));
        $t->same('<w:document><w:p>GZip wrapped import source</w:p></w:document>', $roundTrip->read('/word/document.xml'));
    },

    'reads concatenated gzip members as one import stream' => static function (TestRunner $t): void {
        $first = GzipStream::build("title: Archive packet\n", [
            'filename' => 'packet.yml',
            'comment' => 'front matter',
        ]);
        $second = GzipStream::build("body: Ready for WordPress review\n", [
            'filename' => 'body.yml',
        ]);
        $members = GzipStream::members($first . $second);

        $t->same(2, count($members));
        $t->same("title: Archive packet\nbody: Ready for WordPress review\n", GzipStream::decode($first . $second));
        $t->same("title: Archive packet\n", $members[0]['data']);
        $t->same("body: Ready for WordPress review\n", $members[1]['data']);
        $t->same('packet.yml', $members[0]['filename']);
        $t->same('front matter', $members[0]['comment']);
        $t->same('body.yml', $members[1]['filename']);
        $t->same(null, $members[1]['comment']);
        $t->true($members[0]['memberSize'] < strlen($first . $second));
        $t->same(strlen($second), $members[1]['memberSize']);
    },

    'rejects malformed gzip stream headers and trailers' => static function (TestRunner $t): void {
        $valid = GzipStream::build('review source', [
            'filename' => 'source.txt',
            'headerCrc' => true,
        ]);
        $badCrc = substr_replace($valid, "\0\0\0\0", -8, 4);
        $badSize = substr_replace($valid, pack('V', 999), -4, 4);
        $badHeaderCrc = substr_replace($valid, "\0\0", 21, 2);
        $badReservedFlags = substr_replace($valid, chr(0xe0 | 0x0a), 3, 1);
        $badMethod = substr_replace($valid, chr(12), 2, 1);
        $missingNameTerminator = "\x1f\x8b\x08\x08" . pack('VCC', 0, 0, 255) . 'unterminated';

        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::decode('not gzip'));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::decode($badCrc));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::decode($badSize));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($badHeaderCrc));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($badReservedFlags));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($badMethod));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($missingNameTerminator));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::build('x', ['filename' => "bad\0name"]));
        $t->throws(\RuntimeException::class, static fn (): string => GzipStream::build('x', ['extraFieldData' => str_repeat('x', 0x10000)]));
        $t->throws(\RuntimeException::class, static fn (): array => GzipStream::members($valid, 1));
    },
    'tolerates producer zip metadata quirks while preserving readable entry bytes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $truncatedExtendedTimestamp = pack('vv', 0x5455, 5) . chr(0x03) . pack('V', 123456);
        $zip = $buildZipPackage([
            [
                'name' => 'mimetype',
                'data' => 'application/epub+zip',
                'method' => 0,
                'flags' => 0x0806,
                'versionNeededToExtract' => 10,
                'localExtra' => $truncatedExtendedTimestamp,
                'centralExtra' => $truncatedExtendedTimestamp,
            ],
            [
                'name' => 'EPUB/content.xhtml',
                'data' => '<html><body>metadata quirks</body></html>',
                'method' => 8,
                'flags' => 0x0810,
                'versionNeededToExtract' => 10,
            ],
        ]);

        $package = ZipPackage::fromString($zip);

        $t->same('application/epub+zip', $package->read('mimetype'));
        $t->same('<html><body>metadata quirks</body></html>', $package->read('EPUB/content.xhtml'));
        $t->same(123456, $package->entry('mimetype')->lastModifiedTimestamp());
        $t->same(10, $package->entry('EPUB/content.xhtml')->versionNeededToExtract);
        $t->same(10, $package->entry('EPUB/content.xhtml')->neededToExtractVersion());
    },
];
