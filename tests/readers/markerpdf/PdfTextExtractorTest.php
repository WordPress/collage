<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfWithStreams = static function (array $streams): string {
    $pdf = "%PDF-1.4\n";
    foreach (array_values($streams) as $index => $content) {
        $objectNumber = $index + 1;
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf . "%%EOF";
};

$pdfWithPageContentArray = static function (array $streams): string {
    $references = [];
    foreach (array_keys(array_values($streams)) as $index) {
        $references[] = ($index + 5) . ' 0 R';
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents ["
        . implode(' ', $references)
        . "] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
    foreach (array_values($streams) as $index => $content) {
        $objectNumber = $index + 5;
        $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    return $pdf . "%%EOF";
};

$ascii85Encode = static function (string $bytes): string {
    $encoded = '';
    foreach (str_split($bytes, 4) as $chunk) {
        $length = strlen($chunk);
        if ($length < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $length === 4) {
            $encoded .= 'z';
            continue;
        }

        $digits = array_fill(0, 5, 0);
        for ($index = 4; $index >= 0; $index--) {
            $digits[$index] = $value % 85;
            $value = intdiv($value, 85);
        }

        $group = '';
        foreach ($digits as $digit) {
            $group .= chr($digit + 33);
        }
        $encoded .= substr($group, 0, $length + 1);
    }

    return $encoded . '~>';
};

$lzwEncode = static function (string $bytes, int $earlyChange = 1): string {
    if ($earlyChange !== 0 && $earlyChange !== 1) {
        throw new RuntimeException('Invalid LZW EarlyChange fixture value.');
    }

    $dictionary = [];
    for ($code = 0; $code < 256; $code++) {
        $dictionary[chr($code)] = $code;
    }

    $encoded = '';
    $buffer = 0;
    $bufferBits = 0;
    $nextCode = 258;
    $codeSize = 9;
    $writeCode = static function (int $code, int $width) use (&$encoded, &$buffer, &$bufferBits): void {
        for ($bit = $width - 1; $bit >= 0; $bit--) {
            $buffer = ($buffer << 1) | (($code >> $bit) & 1);
            $bufferBits++;
            if ($bufferBits === 8) {
                $encoded .= chr($buffer);
                $buffer = 0;
                $bufferBits = 0;
            }
        }
    };
    $maybeGrowCodeSize = static function () use (&$codeSize, &$nextCode, $earlyChange): void {
        if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
            $codeSize++;
        }
    };

    $writeCode(256, $codeSize);
    $word = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
        $char = $bytes[$offset];
        if ($word === '') {
            $word = $char;
            continue;
        }

        $candidate = $word . $char;
        if (isset($dictionary[$candidate])) {
            $word = $candidate;
            continue;
        }

        $writeCode($dictionary[$word], $codeSize);
        if ($nextCode < 4096) {
            $dictionary[$candidate] = $nextCode;
            $nextCode++;
            $maybeGrowCodeSize();
        }
        $word = $char;
    }

    if ($word !== '') {
        $writeCode($dictionary[$word], $codeSize);
    }
    $writeCode(257, $codeSize);

    if ($bufferBits > 0) {
        $encoded .= chr($buffer << (8 - $bufferBits));
    }

    return $encoded;
};

$standardR2EncryptedPdfWithContent = static function (string $content, ?string $structActualText = null, int $permissions = -4): string {
    $passwordPadding = hex2bin('28BF4E5E4E758A4164004E56FFFA01082E2E00B6D0683E802F0CA9FE6453697A');
    if ($passwordPadding === false) {
        throw new RuntimeException('Invalid PDF password padding fixture.');
    }
    $rc4 = static function (string $data, string $key): string {
        $keyLength = strlen($key);
        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $output .= chr(ord($data[$offset]) ^ $state[($state[$i] + $state[$j]) & 0xFF]);
        }

        return $output;
    };

    $ownerKey = substr(md5($passwordPadding, true), 0, 5);
    $ownerValue = $rc4($passwordPadding, $ownerKey);
    $fileId = hex2bin('00112233445566778899AABBCCDDEEFF');
    if ($fileId === false) {
        throw new RuntimeException('Invalid PDF file identifier fixture.');
    }
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $fileKey = substr(md5($passwordPadding . $ownerValue . pack('V', $permissionsValue) . $fileId, true), 0, 5);
    $userValue = $rc4($passwordPadding, $fileKey);
    $contentObjectKey = substr(md5($fileKey . "\x05\x00\x00\x00\x00", true), 0, 10);
    $encryptedContent = $rc4($content, $contentObjectKey);

    if ($structActualText !== null) {
        $actualTextObjectKey = substr(md5($fileKey . "\x09\x00\x00\x00\x00", true), 0, 10);
        $encryptedActualText = $rc4($structActualText, $actualTextObjectKey);

        return "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Filter /Standard /V 1 /R 2 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} >>\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText <" . strtoupper(bin2hex($encryptedActualText)) . "> >>\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Filter /Standard /V 1 /R 2 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} >>\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR3EncryptedPdfWithContent = static function (string $content, int $permissions = -4): string {
    $passwordPadding = hex2bin('28BF4E5E4E758A4164004E56FFFA01082E2E00B6D0683E802F0CA9FE6453697A');
    if ($passwordPadding === false) {
        throw new RuntimeException('Invalid PDF password padding fixture.');
    }
    $rc4 = static function (string $data, string $key): string {
        $keyLength = strlen($key);
        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $output .= chr(ord($data[$offset]) ^ $state[($state[$i] + $state[$j]) & 0xFF]);
        }

        return $output;
    };
    $xorBytesWithByte = static function (string $bytes, int $byte): string {
        $result = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $result .= chr(ord($bytes[$index]) ^ ($byte & 0xFF));
        }

        return $result;
    };

    $keyLength = 16;
    $ownerHash = md5($passwordPadding, true);
    for ($round = 0; $round < 50; $round++) {
        $ownerHash = md5(substr($ownerHash, 0, $keyLength), true);
    }
    $ownerKey = substr($ownerHash, 0, $keyLength);
    $ownerValue = $rc4($passwordPadding, $ownerKey);
    for ($round = 1; $round <= 19; $round++) {
        $ownerValue = $rc4($ownerValue, $xorBytesWithByte($ownerKey, $round));
    }

    $fileId = hex2bin('102132435465768798A9BACBDCEDFE0F');
    if ($fileId === false) {
        throw new RuntimeException('Invalid PDF file identifier fixture.');
    }
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $fileKeyHash = md5($passwordPadding . $ownerValue . pack('V', $permissionsValue) . $fileId, true);
    for ($round = 0; $round < 50; $round++) {
        $fileKeyHash = md5(substr($fileKeyHash, 0, $keyLength), true);
    }
    $fileKey = substr($fileKeyHash, 0, $keyLength);

    $userValue = $rc4(md5($passwordPadding . $fileId, true), $fileKey);
    for ($round = 1; $round <= 19; $round++) {
        $userValue = $rc4($userValue, $xorBytesWithByte($fileKey, $round));
    }
    $userValue .= str_repeat("\0", 16);

    $contentObjectKey = substr(md5($fileKey . "\x05\x00\x00\x00\x00", true), 0, 16);
    $encryptedContent = $rc4($content, $contentObjectKey);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Filter /Standard /V 2 /R 3 /Length 128 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} >>\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR4V2EncryptedPdfWithContent = static function (string $content, ?string $structActualText = null, string $streamFilter = 'StdCF', string $stringFilter = 'StdCF', int $permissions = -4): string {
    $passwordPadding = hex2bin('28BF4E5E4E758A4164004E56FFFA01082E2E00B6D0683E802F0CA9FE6453697A');
    if ($passwordPadding === false) {
        throw new RuntimeException('Invalid PDF password padding fixture.');
    }
    $rc4 = static function (string $data, string $key): string {
        $keyLength = strlen($key);
        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $output .= chr(ord($data[$offset]) ^ $state[($state[$i] + $state[$j]) & 0xFF]);
        }

        return $output;
    };
    $xorBytesWithByte = static function (string $bytes, int $byte): string {
        $result = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $result .= chr(ord($bytes[$index]) ^ ($byte & 0xFF));
        }

        return $result;
    };

    $keyLength = 16;
    $ownerHash = md5($passwordPadding, true);
    for ($round = 0; $round < 50; $round++) {
        $ownerHash = md5(substr($ownerHash, 0, $keyLength), true);
    }
    $ownerKey = substr($ownerHash, 0, $keyLength);
    $ownerValue = $rc4($passwordPadding, $ownerKey);
    for ($round = 1; $round <= 19; $round++) {
        $ownerValue = $rc4($ownerValue, $xorBytesWithByte($ownerKey, $round));
    }

    $fileId = hex2bin('2233445566778899AABBCCDDEEFF0011');
    if ($fileId === false) {
        throw new RuntimeException('Invalid PDF file identifier fixture.');
    }
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $fileKeyInput = $passwordPadding . $ownerValue . pack('V', $permissionsValue) . $fileId . "\xFF\xFF\xFF\xFF";
    $fileKeyHash = md5($fileKeyInput, true);
    for ($round = 0; $round < 50; $round++) {
        $fileKeyHash = md5(substr($fileKeyHash, 0, $keyLength), true);
    }
    $fileKey = substr($fileKeyHash, 0, $keyLength);

    $userValue = $rc4(md5($passwordPadding . $fileId, true), $fileKey);
    for ($round = 1; $round <= 19; $round++) {
        $userValue = $rc4($userValue, $xorBytesWithByte($fileKey, $round));
    }
    $userValue .= str_repeat("\0", 16);

    $contentObjectKey = substr(md5($fileKey . "\x05\x00\x00\x00\x00", true), 0, 16);
    $encryptedContent = $streamFilter === 'Identity' ? $content : $rc4($content, $contentObjectKey);
    $encryptDictionary = "<< /Filter /Standard /V 4 /R 4 /Length 128 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} /EncryptMetadata false /CF << /StdCF << /AuthEvent /DocOpen /CFM /V2 /Length 16 >> >> /StmF /{$streamFilter} /StrF /{$stringFilter} >>";

    if ($structActualText !== null) {
        $actualTextObjectKey = substr(md5($fileKey . "\x09\x00\x00\x00\x00", true), 0, 16);
        $actualTextBytes = $stringFilter === 'Identity' ? $structActualText : $rc4($structActualText, $actualTextObjectKey);

        return "%PDF-1.5\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n{$encryptDictionary}\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText <" . strtoupper(bin2hex($actualTextBytes)) . "> >>\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR4AesV2EncryptedPdfWithContent = static function (string $content, ?string $structActualText = null, string $streamFilter = 'StdCF', string $stringFilter = 'StdCF', string $userPassword = '', string $ownerPassword = '', int $permissions = -4, bool $packPageObjectsInObjectStream = false, bool $useEncryptedXrefStream = false): string {
    $passwordPadding = hex2bin('28BF4E5E4E758A4164004E56FFFA01082E2E00B6D0683E802F0CA9FE6453697A');
    if ($passwordPadding === false) {
        throw new RuntimeException('Invalid PDF password padding fixture.');
    }
    $rc4 = static function (string $data, string $key): string {
        $keyLength = strlen($key);
        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        for ($offset = 0, $length = strlen($data); $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $output .= chr(ord($data[$offset]) ^ $state[($state[$i] + $state[$j]) & 0xFF]);
        }

        return $output;
    };
    $xorBytesWithByte = static function (string $bytes, int $byte): string {
        $result = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $result .= chr(ord($bytes[$index]) ^ ($byte & 0xFF));
        }

        return $result;
    };
    $aesV2Encrypt = static function (string $data, string $objectKey, string $iv): string {
        $encrypted = openssl_encrypt($data, 'aes-128-cbc', $objectKey, OPENSSL_RAW_DATA, $iv);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AESV2 fixture bytes.');
        }

        return $iv . $encrypted;
    };

    $keyLength = 16;
    $userPasswordPadding = substr($userPassword . $passwordPadding, 0, 32);
    $ownerPasswordPadding = substr($ownerPassword . $passwordPadding, 0, 32);
    $ownerHash = md5($ownerPasswordPadding, true);
    for ($round = 0; $round < 50; $round++) {
        $ownerHash = md5(substr($ownerHash, 0, $keyLength), true);
    }
    $ownerKey = substr($ownerHash, 0, $keyLength);
    $ownerValue = $rc4($userPasswordPadding, $ownerKey);
    for ($round = 1; $round <= 19; $round++) {
        $ownerValue = $rc4($ownerValue, $xorBytesWithByte($ownerKey, $round));
    }

    $fileId = hex2bin('33445566778899AABBCCDDEEFF001122');
    if ($fileId === false) {
        throw new RuntimeException('Invalid PDF file identifier fixture.');
    }
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $fileKeyInput = $userPasswordPadding . $ownerValue . pack('V', $permissionsValue) . $fileId . "\xFF\xFF\xFF\xFF";
    $fileKeyHash = md5($fileKeyInput, true);
    for ($round = 0; $round < 50; $round++) {
        $fileKeyHash = md5(substr($fileKeyHash, 0, $keyLength), true);
    }
    $fileKey = substr($fileKeyHash, 0, $keyLength);

    $userValue = $rc4(md5($passwordPadding . $fileId, true), $fileKey);
    for ($round = 1; $round <= 19; $round++) {
        $userValue = $rc4($userValue, $xorBytesWithByte($fileKey, $round));
    }
    $userValue .= str_repeat("\0", 16);

    $objectKey = static function (int $objectNumber, int $generation = 0) use ($fileKey): string {
        return substr(md5($fileKey . substr(pack('V', $objectNumber), 0, 3) . substr(pack('V', $generation), 0, 2) . 'sAlT', true), 0, 16);
    };

    $contentObjectKey = $objectKey(5);
    $contentIv = hex2bin('000102030405060708090A0B0C0D0E0F');
    if ($contentIv === false) {
        throw new RuntimeException('Invalid AESV2 content IV fixture.');
    }
    $encryptedContent = $streamFilter === 'Identity' ? $content : $aesV2Encrypt($content, $contentObjectKey, $contentIv);
    $encryptDictionary = "<< /Filter /Standard /V 4 /R 4 /Length 128 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /P {$permissions} /EncryptMetadata false /CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV2 /Length 16 >> >> /StmF /{$streamFilter} /StrF /{$stringFilter} >>";

    if ($structActualText !== null) {
        $actualTextBytes = $structActualText;
        if ($stringFilter !== 'Identity') {
            $actualTextObjectKey = $objectKey(9);
            $actualTextIv = hex2bin('101112131415161718191A1B1C1D1E1F');
            if ($actualTextIv === false) {
                throw new RuntimeException('Invalid AESV2 ActualText IV fixture.');
            }
            $actualTextBytes = $aesV2Encrypt($structActualText, $actualTextObjectKey, $actualTextIv);
        }

        return "%PDF-1.6\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n{$encryptDictionary}\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText <" . strtoupper(bin2hex($actualTextBytes)) . "> >>\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    if ($useEncryptedXrefStream) {
        $encryptStream = static function (string $plain, int $objectNumber, string $iv) use ($streamFilter, $aesV2Encrypt, $objectKey): string {
            return $streamFilter === 'Identity' ? $plain : $aesV2Encrypt($plain, $objectKey($objectNumber), $iv);
        };
        $hexIv = static function (string $hex, string $label): string {
            $iv = hex2bin($hex);
            if ($iv === false) {
                throw new RuntimeException('Invalid AESV2 ' . $label . ' IV fixture.');
            }

            return $iv;
        };

        $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored R4 encrypted xref duplicate) Tj ET';
        $encryptedDuplicateContent = $encryptStream($duplicateContent, 5, $hexIv('202122232425262728292A2B2C2D2E2F', 'duplicate content'));
        $stalePackedPageContent = 'BT /F1 12 Tf 72 720 Td (Ignored R4 encrypted xref object stream stale) Tj ET';
        $encryptedStalePackedPageContent = $encryptStream($stalePackedPageContent, 7, $hexIv('303132333435363738393A3B3C3D3E3F', 'stale packed-page'));

        $pdf = "%PDF-1.6\n";
        $offsets = [];
        $appendObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $appendObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $appendObject(5, "<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream");
        $pdf .= "5 0 obj\n<< /Length " . strlen($encryptedDuplicateContent) . " >>\nstream\n{$encryptedDuplicateContent}\nendstream\nendobj\n";
        $appendObject(6, $encryptDictionary);

        $packedObjects = [];
        if ($packPageObjectsInObjectStream) {
            $packedObjects = [
                2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
                3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
                4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            ];
            $objectStreamHeader = '';
            $objectStreamBody = '';
            foreach ($packedObjects as $objectNumber => $objectBody) {
                $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
                $objectStreamBody .= $objectBody . "\n";
            }
            $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
            if (!is_string($compressedObjectStream)) {
                throw new RuntimeException('Unable to compress AESV2 R4 xref/object-stream fixture.');
            }
            $encryptedObjectStream = $encryptStream($compressedObjectStream, 8, $hexIv('404142434445464748494A4B4C4D4E4F', 'object stream'));
            $appendObject(8, "<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream");
            $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
                . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
                . "7 0 obj\n<< /Length " . strlen($encryptedStalePackedPageContent) . " >>\nstream\n{$encryptedStalePackedPageContent}\nendstream\nendobj\n";
        } else {
            $appendObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
            $appendObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
            $appendObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        }

        $xrefOffset = strlen($pdf);
        $xrefStreamEntry = static function (int $type, int $field2, int $field3): string {
            return chr($type)
                . pack('N', $field2)
                . pack('n', $field3);
        };
        $entries = [];
        for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
            $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
        }
        $packedObjectIndexes = array_flip(array_keys($packedObjects));
        foreach ([1, 2, 3, 4, 5, 6] as $objectNumber) {
            if ($packPageObjectsInObjectStream && isset($packedObjects[$objectNumber])) {
                $entries[$objectNumber] = $xrefStreamEntry(2, 8, $packedObjectIndexes[$objectNumber]);
                continue;
            }
            $entries[$objectNumber] = $xrefStreamEntry(1, $offsets[$objectNumber], 0);
        }
        if ($packPageObjectsInObjectStream) {
            $entries[8] = $xrefStreamEntry(1, $offsets[8], 0);
        }
        $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
        $compressedXrefStream = gzcompress(implode('', $entries));
        if (!is_string($compressedXrefStream)) {
            throw new RuntimeException('Unable to compress AESV2 R4 xref stream fixture.');
        }
        $encryptedXrefStream = $encryptStream($compressedXrefStream, 9, $hexIv('505152535455565758595A5B5C5D5E5F', 'xref stream'));
        $fileIdHex = strtoupper(bin2hex($fileId));

        return $pdf
            . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /Encrypt 6 0 R /ID [<{$fileIdHex}> <{$fileIdHex}>] /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($encryptedXrefStream) . " >>\nstream\n{$encryptedXrefStream}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF";
    }

    return "%PDF-1.6\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR5AesV3EncryptedPdfWithContent = static function (string $content, ?string $structActualText = null, string $streamFilter = 'StdCF', string $stringFilter = 'StdCF', int $permissions = -4, bool $packPageObjectsInObjectStream = false, bool $useEncryptedXrefStream = false): string {
    $fileKey = hex2bin('00112233445566778899AABBCCDDEEFF102132435465768798A9BACBDCEDFE0F');
    $userValidationSalt = hex2bin('0102030405060708');
    $userKeySalt = hex2bin('1112131415161718');
    $ownerValidationSalt = hex2bin('2122232425262728');
    $ownerKeySalt = hex2bin('3132333435363738');
    $fileId = hex2bin('445566778899AABBCCDDEEFF00112233');
    if ($fileKey === false || $userValidationSalt === false || $userKeySalt === false || $ownerValidationSalt === false || $ownerKeySalt === false || $fileId === false) {
        throw new RuntimeException('Invalid AESV3 encryption fixture bytes.');
    }

    $aes256CbcNoPadding = static function (string $data, string $key): string {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AES-256-CBC fixture bytes.');
        }

        return $encrypted;
    };
    $aes256EcbNoPadding = static function (string $data, string $key): string {
        $encrypted = openssl_encrypt($data, 'aes-256-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AES-256-ECB fixture bytes.');
        }

        return $encrypted;
    };
    $aesV3Encrypt = static function (string $data, string $key, string $iv): string {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AESV3 fixture bytes.');
        }

        return $iv . $encrypted;
    };

    $passwordBytes = '';
    $userValue = hash('sha256', $passwordBytes . $userValidationSalt, true) . $userValidationSalt . $userKeySalt;
    $ownerValue = hash('sha256', $passwordBytes . $ownerValidationSalt . $userValue, true) . $ownerValidationSalt . $ownerKeySalt;
    $ueValue = $aes256CbcNoPadding($fileKey, hash('sha256', $passwordBytes . $userKeySalt, true));
    $oeValue = $aes256CbcNoPadding($fileKey, hash('sha256', $passwordBytes . $ownerKeySalt . $userValue, true));
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $permsPlain = pack('V', $permissionsValue) . "\xFF\xFF\xFF\xFF" . 'F' . 'adb' . "\x00\x01\x02\x03";
    $permsValue = $aes256EcbNoPadding($permsPlain, $fileKey);

    $contentIv = hex2bin('404142434445464748494A4B4C4D4E4F');
    if ($contentIv === false) {
        throw new RuntimeException('Invalid AESV3 content IV fixture.');
    }
    $encryptedContent = $streamFilter === 'Identity' ? $content : $aesV3Encrypt($content, $fileKey, $contentIv);
    $encryptDictionary = "<< /Filter /Standard /V 5 /R 5 /Length 256 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /OE <" . strtoupper(bin2hex($oeValue)) . "> /UE <" . strtoupper(bin2hex($ueValue)) . "> /P {$permissions} /Perms <" . strtoupper(bin2hex($permsValue)) . "> /EncryptMetadata false /CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV3 /Length 32 >> >> /StmF /{$streamFilter} /StrF /{$stringFilter} >>";

    if ($structActualText !== null) {
        $actualTextBytes = $structActualText;
        if ($stringFilter !== 'Identity') {
            $actualTextIv = hex2bin('505152535455565758595A5B5C5D5E5F');
            if ($actualTextIv === false) {
                throw new RuntimeException('Invalid AESV3 ActualText IV fixture.');
            }
            $actualTextBytes = $aesV3Encrypt($structActualText, $fileKey, $actualTextIv);
        }

        return "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n{$encryptDictionary}\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText <" . strtoupper(bin2hex($actualTextBytes)) . "> >>\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    if ($useEncryptedXrefStream) {
        $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored R5 encrypted xref duplicate) Tj ET';
        $duplicateContentIv = hex2bin('A0A1A2A3A4A5A6A7A8A9AAABACADAEAF');
        if ($duplicateContentIv === false) {
            throw new RuntimeException('Invalid AESV3 R5 duplicate content IV fixture.');
        }
        $encryptedDuplicateContent = $streamFilter === 'Identity' ? $duplicateContent : $aesV3Encrypt($duplicateContent, $fileKey, $duplicateContentIv);
        $stalePackedPageContent = 'BT /F1 12 Tf 72 720 Td (Ignored R5 encrypted xref object stream stale) Tj ET';
        $stalePackedPageIv = hex2bin('B0B1B2B3B4B5B6B7B8B9BABBBCBDBEBF');
        if ($stalePackedPageIv === false) {
            throw new RuntimeException('Invalid AESV3 R5 stale packed-page IV fixture.');
        }
        $encryptedStalePackedPageContent = $streamFilter === 'Identity' ? $stalePackedPageContent : $aesV3Encrypt($stalePackedPageContent, $fileKey, $stalePackedPageIv);

        $pdf = "%PDF-1.7\n";
        $offsets = [];
        $appendObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $appendObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $appendObject(5, "<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream");
        $pdf .= "5 0 obj\n<< /Length " . strlen($encryptedDuplicateContent) . " >>\nstream\n{$encryptedDuplicateContent}\nendstream\nendobj\n";
        $appendObject(6, $encryptDictionary);
        $packedObjects = [];
        if ($packPageObjectsInObjectStream) {
            $packedObjects = [
                2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
                3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
                4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            ];
            $objectStreamHeader = '';
            $objectStreamBody = '';
            foreach ($packedObjects as $objectNumber => $objectBody) {
                $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
                $objectStreamBody .= $objectBody . "\n";
            }
            $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
            if (!is_string($compressedObjectStream)) {
                throw new RuntimeException('Unable to compress AESV3 R5 xref/object-stream fixture.');
            }
            $objectStreamIv = hex2bin('808182838485868788898A8B8C8D8E8F');
            if ($objectStreamIv === false) {
                throw new RuntimeException('Invalid AESV3 R5 object stream IV fixture.');
            }
            $encryptedObjectStream = $streamFilter === 'Identity' ? $compressedObjectStream : $aesV3Encrypt($compressedObjectStream, $fileKey, $objectStreamIv);
            $appendObject(8, "<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream");
            $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
                . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
                . "7 0 obj\n<< /Length " . strlen($encryptedStalePackedPageContent) . " >>\nstream\n{$encryptedStalePackedPageContent}\nendstream\nendobj\n";
        } else {
            $appendObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
            $appendObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
            $appendObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        }

        $xrefOffset = strlen($pdf);
        $xrefStreamEntry = static function (int $type, int $field2, int $field3): string {
            return chr($type)
                . pack('N', $field2)
                . pack('n', $field3);
        };
        $entries = [];
        for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
            $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
        }
        $packedObjectIndexes = array_flip(array_keys($packedObjects));
        foreach ([1, 2, 3, 4, 5, 6] as $objectNumber) {
            if ($packPageObjectsInObjectStream && isset($packedObjects[$objectNumber])) {
                $entries[$objectNumber] = $xrefStreamEntry(2, 8, $packedObjectIndexes[$objectNumber]);
                continue;
            }
            $entries[$objectNumber] = $xrefStreamEntry(1, $offsets[$objectNumber], 0);
        }
        if ($packPageObjectsInObjectStream) {
            $entries[8] = $xrefStreamEntry(1, $offsets[8], 0);
        }
        $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
        $compressedXrefStream = gzcompress(implode('', $entries));
        if (!is_string($compressedXrefStream)) {
            throw new RuntimeException('Unable to compress AESV3 R5 xref stream fixture.');
        }
        $xrefStreamIv = hex2bin('909192939495969798999A9B9C9D9E9F');
        if ($xrefStreamIv === false) {
            throw new RuntimeException('Invalid AESV3 R5 xref stream IV fixture.');
        }
        $encryptedXrefStream = $streamFilter === 'Identity' ? $compressedXrefStream : $aesV3Encrypt($compressedXrefStream, $fileKey, $xrefStreamIv);
        $fileIdHex = strtoupper(bin2hex($fileId));

        return $pdf
            . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /Encrypt 6 0 R /ID [<{$fileIdHex}> <{$fileIdHex}>] /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($encryptedXrefStream) . " >>\nstream\n{$encryptedXrefStream}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF";
    }

    if ($packPageObjectsInObjectStream) {
        $packedObjects = [
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        ];
        $objectStreamHeader = '';
        $objectStreamBody = '';
        foreach ($packedObjects as $objectNumber => $objectBody) {
            $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
            $objectStreamBody .= $objectBody . "\n";
        }
        $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
        if (!is_string($compressedObjectStream)) {
            throw new RuntimeException('Unable to compress AESV3 R5 object stream fixture.');
        }
        $objectStreamIv = hex2bin('808182838485868788898A8B8C8D8E8F');
        if ($objectStreamIv === false) {
            throw new RuntimeException('Invalid AESV3 R5 object stream IV fixture.');
        }
        $encryptedObjectStream = $aesV3Encrypt($compressedObjectStream, $fileKey, $objectStreamIv);

        return "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n{$encryptDictionary}\nendobj\n"
            . "8 0 obj\n<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$standardR6AesV3EncryptedPdfWithContent = static function (string $content, ?string $structActualText = null, string $streamFilter = 'StdCF', string $stringFilter = 'StdCF', string $userPassword = '', string $ownerPassword = '', bool $packPageObjectsInObjectStream = false, bool $useEncryptedXrefStream = false, int $permissions = -4): string {
    $fileKey = hex2bin('31425364758697A8B9CADBECFD0E1F2031425364758697A8B9CADBECFD0E1F20');
    $userValidationSalt = hex2bin('0807060504030201');
    $userKeySalt = hex2bin('1817161514131211');
    $ownerValidationSalt = hex2bin('2827262524232221');
    $ownerKeySalt = hex2bin('3837363534333231');
    $fileId = hex2bin('66554433221100FFEEDDCCBBAA998877');
    if ($fileKey === false || $userValidationSalt === false || $userKeySalt === false || $ownerValidationSalt === false || $ownerKeySalt === false || $fileId === false) {
        throw new RuntimeException('Invalid AESV3 R6 encryption fixture bytes.');
    }

    $aes256CbcNoPadding = static function (string $data, string $key): string {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AES-256-CBC fixture bytes.');
        }

        return $encrypted;
    };
    $aes256EcbNoPadding = static function (string $data, string $key): string {
        $encrypted = openssl_encrypt($data, 'aes-256-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AES-256-ECB fixture bytes.');
        }

        return $encrypted;
    };
    $aesV3Encrypt = static function (string $data, string $key, string $iv): string {
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($encrypted)) {
            throw new RuntimeException('Unable to encrypt AESV3 R6 fixture bytes.');
        }

        return $iv . $encrypted;
    };
    $r6Hash = static function (string $passwordBytes, string $salt, string $userValue = ''): string {
        $key = hash('sha256', $passwordBytes . $salt . $userValue, true);
        for ($round = 0; ; $round++) {
            $input = str_repeat($passwordBytes . $key . $userValue, 64);
            $encrypted = openssl_encrypt($input, 'aes-128-cbc', substr($key, 0, 16), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($key, 16, 16));
            if (!is_string($encrypted) || $encrypted === '') {
                throw new RuntimeException('Unable to hash AESV3 R6 fixture bytes.');
            }

            $hashSelector = 0;
            for ($index = 0; $index < 16; $index++) {
                $hashSelector = (($hashSelector * 256) + ord($encrypted[$index])) % 3;
            }
            $key = hash(match ($hashSelector) {
                0 => 'sha256',
                1 => 'sha384',
                default => 'sha512',
            }, $encrypted, true);

            if ($round >= 63 && ord($encrypted[strlen($encrypted) - 1]) <= $round - 32) {
                break;
            }
        }

        return substr($key, 0, 32);
    };

    $passwordBytes = substr($userPassword, 0, 127);
    $ownerPasswordBytes = substr($ownerPassword, 0, 127);
    $userValue = $r6Hash($passwordBytes, $userValidationSalt) . $userValidationSalt . $userKeySalt;
    $ownerValue = $r6Hash($ownerPasswordBytes, $ownerValidationSalt, $userValue) . $ownerValidationSalt . $ownerKeySalt;
    $ueValue = $aes256CbcNoPadding($fileKey, $r6Hash($passwordBytes, $userKeySalt));
    $oeValue = $aes256CbcNoPadding($fileKey, $r6Hash($ownerPasswordBytes, $ownerKeySalt, $userValue));
    $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
    $permsPlain = pack('V', $permissionsValue) . "\xFF\xFF\xFF\xFF" . 'F' . 'adb' . "\x04\x05\x06\x07";
    $permsValue = $aes256EcbNoPadding($permsPlain, $fileKey);

    $contentIv = hex2bin('606162636465666768696A6B6C6D6E6F');
    if ($contentIv === false) {
        throw new RuntimeException('Invalid AESV3 R6 content IV fixture.');
    }
    $encryptedContent = $streamFilter === 'Identity' ? $content : $aesV3Encrypt($content, $fileKey, $contentIv);
    $encryptDictionary = "<< /Filter /Standard /V 5 /R 6 /Length 256 /O <" . strtoupper(bin2hex($ownerValue)) . "> /U <" . strtoupper(bin2hex($userValue)) . "> /OE <" . strtoupper(bin2hex($oeValue)) . "> /UE <" . strtoupper(bin2hex($ueValue)) . "> /P {$permissions} /Perms <" . strtoupper(bin2hex($permsValue)) . "> /EncryptMetadata false /CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV3 /Length 32 >> >> /StmF /{$streamFilter} /StrF /{$stringFilter} >>";

    if ($structActualText !== null) {
        $actualTextBytes = $structActualText;
        if ($stringFilter !== 'Identity') {
            $actualTextIv = hex2bin('707172737475767778797A7B7C7D7E7F');
            if ($actualTextIv === false) {
                throw new RuntimeException('Invalid AESV3 R6 ActualText IV fixture.');
            }
            $actualTextBytes = $aesV3Encrypt($structActualText, $fileKey, $actualTextIv);
        }

        return "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n{$encryptDictionary}\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText <" . strtoupper(bin2hex($actualTextBytes)) . "> >>\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    if ($useEncryptedXrefStream) {
        $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored encrypted xref duplicate) Tj ET';
        $duplicateContentIv = hex2bin('A0A1A2A3A4A5A6A7A8A9AAABACADAEAF');
        if ($duplicateContentIv === false) {
            throw new RuntimeException('Invalid AESV3 R6 duplicate content IV fixture.');
        }
        $encryptedDuplicateContent = $streamFilter === 'Identity' ? $duplicateContent : $aesV3Encrypt($duplicateContent, $fileKey, $duplicateContentIv);
        $stalePackedPageContent = 'BT /F1 12 Tf 72 720 Td (Ignored encrypted xref object stream stale) Tj ET';
        $stalePackedPageIv = hex2bin('B0B1B2B3B4B5B6B7B8B9BABBBCBDBEBF');
        if ($stalePackedPageIv === false) {
            throw new RuntimeException('Invalid AESV3 R6 stale packed-page IV fixture.');
        }
        $encryptedStalePackedPageContent = $streamFilter === 'Identity' ? $stalePackedPageContent : $aesV3Encrypt($stalePackedPageContent, $fileKey, $stalePackedPageIv);

        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $appendObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $appendObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
        $appendObject(5, "<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream");
        $pdf .= "5 0 obj\n<< /Length " . strlen($encryptedDuplicateContent) . " >>\nstream\n{$encryptedDuplicateContent}\nendstream\nendobj\n";
        $appendObject(6, $encryptDictionary);
        $packedObjects = [];
        if ($packPageObjectsInObjectStream) {
            $packedObjects = [
                2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
                3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
                4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            ];
            $objectStreamHeader = '';
            $objectStreamBody = '';
            foreach ($packedObjects as $objectNumber => $objectBody) {
                $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
                $objectStreamBody .= $objectBody . "\n";
            }
            $compressedObjectStream = gzcompress($objectStreamHeader . $objectStreamBody);
            if (!is_string($compressedObjectStream)) {
                throw new RuntimeException('Unable to compress AESV3 R6 xref/object-stream fixture.');
            }
            $objectStreamIv = hex2bin('808182838485868788898A8B8C8D8E8F');
            if ($objectStreamIv === false) {
                throw new RuntimeException('Invalid AESV3 R6 object stream IV fixture.');
            }
            $encryptedObjectStream = $streamFilter === 'Identity' ? $compressedObjectStream : $aesV3Encrypt($compressedObjectStream, $fileKey, $objectStreamIv);
            $appendObject(8, "<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream");
            $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
                . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
                . "7 0 obj\n<< /Length " . strlen($encryptedStalePackedPageContent) . " >>\nstream\n{$encryptedStalePackedPageContent}\nendstream\nendobj\n";
        } else {
            $appendObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
            $appendObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
            $appendObject(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        }

        $xrefOffset = strlen($pdf);
        $xrefStreamEntry = static function (int $type, int $field2, int $field3): string {
            return chr($type)
                . pack('N', $field2)
                . pack('n', $field3);
        };
        $entries = [];
        for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
            $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
        }
        $packedObjectIndexes = array_flip(array_keys($packedObjects));
        foreach ([1, 2, 3, 4, 5, 6] as $objectNumber) {
            if ($packPageObjectsInObjectStream && isset($packedObjects[$objectNumber])) {
                $entries[$objectNumber] = $xrefStreamEntry(2, 8, $packedObjectIndexes[$objectNumber]);
                continue;
            }
            $entries[$objectNumber] = $xrefStreamEntry(1, $offsets[$objectNumber], 0);
        }
        if ($packPageObjectsInObjectStream) {
            $entries[8] = $xrefStreamEntry(1, $offsets[8], 0);
        }
        $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
        $compressedXrefStream = gzcompress(implode('', $entries));
        if (!is_string($compressedXrefStream)) {
            throw new RuntimeException('Unable to compress AESV3 R6 xref stream fixture.');
        }
        $xrefStreamIv = hex2bin('909192939495969798999A9B9C9D9E9F');
        if ($xrefStreamIv === false) {
            throw new RuntimeException('Invalid AESV3 R6 xref stream IV fixture.');
        }
        $encryptedXrefStream = $streamFilter === 'Identity' ? $compressedXrefStream : $aesV3Encrypt($compressedXrefStream, $fileKey, $xrefStreamIv);
        $fileIdHex = strtoupper(bin2hex($fileId));

        return $pdf
            . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /Encrypt 6 0 R /ID [<{$fileIdHex}> <{$fileIdHex}>] /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($encryptedXrefStream) . " >>\nstream\n{$encryptedXrefStream}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF";
    }

    if ($packPageObjectsInObjectStream) {
        $packedObjects = [
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
            4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        ];
        $objectStreamHeader = '';
        $objectStreamBody = '';
        foreach ($packedObjects as $objectNumber => $objectBody) {
            $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamBody) . ' ';
            $objectStreamBody .= $objectBody . "\n";
        }
        $objectStreamPlain = $objectStreamHeader . $objectStreamBody;
        $compressedObjectStream = gzcompress($objectStreamPlain);
        if (!is_string($compressedObjectStream)) {
            throw new RuntimeException('Unable to compress AESV3 R6 object stream fixture.');
        }
        $objectStreamIv = hex2bin('808182838485868788898A8B8C8D8E8F');
        if ($objectStreamIv === false) {
            throw new RuntimeException('Invalid AESV3 R6 object stream IV fixture.');
        }
        $encryptedObjectStream = $aesV3Encrypt($compressedObjectStream, $fileKey, $objectStreamIv);

        return "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
            . "6 0 obj\n{$encryptDictionary}\nendobj\n"
            . "8 0 obj\n<< /Type /ObjStm /N " . count($packedObjects) . " /First " . strlen($objectStreamHeader) . " /Filter /FlateDecode /Length " . strlen($encryptedObjectStream) . " >>\nstream\n{$encryptedObjectStream}\nendstream\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
    }

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($encryptedContent) . " >>\nstream\n{$encryptedContent}\nendstream\nendobj\n"
        . "6 0 obj\n{$encryptDictionary}\nendobj\n"
        . "trailer << /Root 1 0 R /Encrypt 6 0 R /ID [<" . strtoupper(bin2hex($fileId)) . "> <" . strtoupper(bin2hex($fileId)) . ">] >>\n%%EOF";
};

$trueTypeWithFormat4CMap = static function (array $codes): string {
    $codes = array_values(array_unique(array_filter($codes, static fn (int $code): bool => $code >= 0 && $code <= 255)));
    sort($codes, SORT_NUMERIC);

    $segCount = count($codes) + 1;
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $segCount) {
        $entrySelector++;
    }
    $searchRange = 2 * (1 << $entrySelector);
    $rangeShift = (2 * $segCount) - $searchRange;

    $endCodes = '';
    $startCodes = '';
    $idDeltas = '';
    foreach ($codes as $code) {
        $endCodes .= pack('n', $code);
        $startCodes .= pack('n', $code);
        $idDeltas .= pack('n', 0);
    }
    $endCodes .= pack('n', 0xFFFF);
    $startCodes .= pack('n', 0xFFFF);
    $idDeltas .= pack('n', 1);
    $idRangeOffsets = str_repeat(pack('n', 0), $segCount);

    $format4Length = 16 + (8 * $segCount);
    $format4 = pack('nnnnnnn', 4, $format4Length, 0, $segCount * 2, $searchRange, $entrySelector, $rangeShift)
        . $endCodes
        . pack('n', 0)
        . $startCodes
        . $idDeltas
        . $idRangeOffsets;
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $format4;

    return pack('Nnnnn', 0x00010000, 1, 16, 0, 0)
        . 'cmap'
        . pack('NNN', 0, 28, strlen($cmap))
        . $cmap;
};

$trueTypeWithFormat4CMapAndPostNames = static function (array $codeToGlyphName): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    $codes = array_keys($codeToGlyphName);
    $glyphNames = array_values($codeToGlyphName);

    $segCount = count($codes) + 1;
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $segCount) {
        $entrySelector++;
    }
    $searchRange = 2 * (1 << $entrySelector);
    $rangeShift = (2 * $segCount) - $searchRange;

    $endCodes = '';
    $startCodes = '';
    $idDeltas = '';
    foreach ($codes as $index => $code) {
        $glyphId = $index + 1;
        $endCodes .= pack('n', $code);
        $startCodes .= pack('n', $code);
        $idDeltas .= pack('n', ($glyphId - $code) & 0xFFFF);
    }
    $endCodes .= pack('n', 0xFFFF);
    $startCodes .= pack('n', 0xFFFF);
    $idDeltas .= pack('n', 1);
    $idRangeOffsets = str_repeat(pack('n', 0), $segCount);

    $format4Length = 16 + (8 * $segCount);
    $format4 = pack('nnnnnnn', 4, $format4Length, 0, $segCount * 2, $searchRange, $entrySelector, $rangeShift)
        . $endCodes
        . pack('n', 0)
        . $startCodes
        . $idDeltas
        . $idRangeOffsets;
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $format4;

    $post = pack('NNnnNNNNNn', 0x00020000, 0, 0, 0, 0, 0, 0, 0, 0, count($glyphNames) + 1)
        . pack('n', 0);
    foreach ($glyphNames as $index => $glyphName) {
        $post .= pack('n', 258 + $index);
    }
    foreach ($glyphNames as $glyphName) {
        $post .= chr(strlen($glyphName)) . $glyphName;
    }

    $tables = ['cmap' => $cmap, 'post' => $post];
    $numTables = count($tables);
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $numTables) {
        $entrySelector++;
    }
    $searchRange = 16 * (1 << $entrySelector);
    $rangeShift = (16 * $numTables) - $searchRange;
    $offset = 12 + (16 * $numTables);
    $records = '';
    $data = '';
    foreach ($tables as $tag => $table) {
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $padding = (4 - (strlen($table) % 4)) % 4;
        $data .= $table . str_repeat("\0", $padding);
        $offset += strlen($table) + $padding;
    }

    return pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift)
        . $records
        . $data;
};

$trueTypeWithFormat4CMapAndPost25Names = static function (array $codeToGlyphName): string {
    $standardPostIndexByName = [
        'space' => 3,
        'D' => 39,
        'F' => 41,
        'K' => 46,
        'O' => 50,
        'P' => 51,
    ];
    $codeToGlyphName = array_filter(
        $codeToGlyphName,
        static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && isset($standardPostIndexByName[$glyphName]),
        ARRAY_FILTER_USE_BOTH
    );
    ksort($codeToGlyphName, SORT_NUMERIC);
    $codes = array_keys($codeToGlyphName);
    $glyphNames = array_values($codeToGlyphName);

    $segCount = count($codes) + 1;
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $segCount) {
        $entrySelector++;
    }
    $searchRange = 2 * (1 << $entrySelector);
    $rangeShift = (2 * $segCount) - $searchRange;

    $endCodes = '';
    $startCodes = '';
    $idDeltas = '';
    foreach ($codes as $index => $code) {
        $glyphId = $index + 1;
        $endCodes .= pack('n', $code);
        $startCodes .= pack('n', $code);
        $idDeltas .= pack('n', ($glyphId - $code) & 0xFFFF);
    }
    $endCodes .= pack('n', 0xFFFF);
    $startCodes .= pack('n', 0xFFFF);
    $idDeltas .= pack('n', 1);
    $idRangeOffsets = str_repeat(pack('n', 0), $segCount);

    $format4Length = 16 + (8 * $segCount);
    $format4 = pack('nnnnnnn', 4, $format4Length, 0, $segCount * 2, $searchRange, $entrySelector, $rangeShift)
        . $endCodes
        . pack('n', 0)
        . $startCodes
        . $idDeltas
        . $idRangeOffsets;
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $format4;

    $post = pack('NNnnNNNNNn', 0x00025000, 0, 0, 0, 0, 0, 0, 0, 0, count($glyphNames) + 1)
        . chr(0);
    foreach ($glyphNames as $index => $glyphName) {
        $glyphId = $index + 1;
        $post .= chr(($standardPostIndexByName[$glyphName] - $glyphId) & 0xFF);
    }

    $tables = ['cmap' => $cmap, 'post' => $post];
    $numTables = count($tables);
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $numTables) {
        $entrySelector++;
    }
    $searchRange = 16 * (1 << $entrySelector);
    $rangeShift = (16 * $numTables) - $searchRange;
    $offset = 12 + (16 * $numTables);
    $records = '';
    $data = '';
    foreach ($tables as $tag => $table) {
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $padding = (4 - (strlen($table) % 4)) % 4;
        $data .= $table . str_repeat("\0", $padding);
        $offset += strlen($table) + $padding;
    }

    return pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift)
        . $records
        . $data;
};

$trueTypeWithFormat4CMapAndPost10Names = static function (array $codeToGlyphName): string {
    $standardPostIndexByName = [
        'space' => 3,
        'D' => 39,
        'F' => 41,
        'K' => 46,
        'O' => 50,
        'P' => 51,
    ];
    $codeToGlyphName = array_filter(
        $codeToGlyphName,
        static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && isset($standardPostIndexByName[$glyphName]),
        ARRAY_FILTER_USE_BOTH
    );
    ksort($codeToGlyphName, SORT_NUMERIC);
    $codes = array_keys($codeToGlyphName);

    $segCount = count($codes) + 1;
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $segCount) {
        $entrySelector++;
    }
    $searchRange = 2 * (1 << $entrySelector);
    $rangeShift = (2 * $segCount) - $searchRange;

    $endCodes = '';
    $startCodes = '';
    $idDeltas = '';
    foreach ($codes as $code) {
        $glyphId = $standardPostIndexByName[$codeToGlyphName[$code]];
        $endCodes .= pack('n', $code);
        $startCodes .= pack('n', $code);
        $idDeltas .= pack('n', ($glyphId - $code) & 0xFFFF);
    }
    $endCodes .= pack('n', 0xFFFF);
    $startCodes .= pack('n', 0xFFFF);
    $idDeltas .= pack('n', 1);
    $idRangeOffsets = str_repeat(pack('n', 0), $segCount);

    $format4Length = 16 + (8 * $segCount);
    $format4 = pack('nnnnnnn', 4, $format4Length, 0, $segCount * 2, $searchRange, $entrySelector, $rangeShift)
        . $endCodes
        . pack('n', 0)
        . $startCodes
        . $idDeltas
        . $idRangeOffsets;
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $format4;
    $post = pack('NNnnNNNNN', 0x00010000, 0, 0, 0, 0, 0, 0, 0, 0);

    $tables = ['cmap' => $cmap, 'post' => $post];
    $numTables = count($tables);
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $numTables) {
        $entrySelector++;
    }
    $searchRange = 16 * (1 << $entrySelector);
    $rangeShift = (16 * $numTables) - $searchRange;
    $offset = 12 + (16 * $numTables);
    $records = '';
    $data = '';
    foreach ($tables as $tag => $table) {
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $padding = (4 - (strlen($table) % 4)) % 4;
        $data .= $table . str_repeat("\0", $padding);
        $offset += strlen($table) + $padding;
    }

    return pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift)
        . $records
        . $data;
};

$trueTypeWithCMapSubtableAndPostNames = static function (string $subtable, int $platformId, int $encodingId, array $glyphNames): string {
    $cmap = pack('nnnnN', 0, 1, $platformId, $encodingId, 12) . $subtable;
    $post = pack('NNnnNNNNNn', 0x00020000, 0, 0, 0, 0, 0, 0, 0, 0, count($glyphNames) + 1)
        . pack('n', 0);
    foreach ($glyphNames as $index => $glyphName) {
        $post .= pack('n', 258 + $index);
    }
    foreach ($glyphNames as $glyphName) {
        $post .= chr(strlen($glyphName)) . $glyphName;
    }

    $tables = ['cmap' => $cmap, 'post' => $post];
    $numTables = count($tables);
    $entrySelector = 0;
    while ((1 << ($entrySelector + 1)) <= $numTables) {
        $entrySelector++;
    }
    $searchRange = 16 * (1 << $entrySelector);
    $rangeShift = (16 * $numTables) - $searchRange;
    $offset = 12 + (16 * $numTables);
    $records = '';
    $data = '';
    foreach ($tables as $tag => $table) {
        $records .= $tag . pack('NNN', 0, $offset, strlen($table));
        $padding = (4 - (strlen($table) % 4)) % 4;
        $data .= $table . str_repeat("\0", $padding);
        $offset += strlen($table) + $padding;
    }

    return pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift)
        . $records
        . $data;
};

$trueTypeWithFormat0CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);

    $glyphIds = array_fill(0, 256, 0);
    $glyphNames = [];
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $glyphIds[$code] = count($glyphNames);
    }

    $glyphIdArray = '';
    foreach ($glyphIds as $glyphId) {
        $glyphIdArray .= chr($glyphId & 0xFF);
    }

    $format0 = pack('nnn', 0, 262, 0) . $glyphIdArray;
    return $trueTypeWithCMapSubtableAndPostNames($format0, 0, 3, $glyphNames);
};

$trueTypeWithFormat6CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    if ($codeToGlyphName === []) {
        return $trueTypeWithCMapSubtableAndPostNames(pack('nnnnn', 6, 10, 0, 0, 0), 3, 1, []);
    }

    $firstCode = min(array_keys($codeToGlyphName));
    $lastCode = max(array_keys($codeToGlyphName));
    $glyphIdByCode = [];
    $glyphNames = [];
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $glyphIdByCode[$code] = count($glyphNames);
    }

    $glyphIds = '';
    for ($code = $firstCode; $code <= $lastCode; $code++) {
        $glyphIds .= pack('n', $glyphIdByCode[$code] ?? 0);
    }

    $entryCount = $lastCode - $firstCode + 1;
    $format6 = pack('nnnnn', 6, 10 + (2 * $entryCount), 0, $firstCode, $entryCount) . $glyphIds;
    return $trueTypeWithCMapSubtableAndPostNames($format6, 3, 1, $glyphNames);
};

$trueTypeWithFormat8CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    if ($codeToGlyphName === []) {
        return $trueTypeWithCMapSubtableAndPostNames(pack('nnNN', 8, 0, 8208, 0) . str_repeat("\0", 8192) . pack('N', 0), 3, 10, []);
    }

    $glyphNames = [];
    $groups = '';
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $groups .= pack('NNN', $code, $code, count($glyphNames));
    }

    $groupCount = count($codeToGlyphName);
    $format8 = pack('nnNN', 8, 0, 8208 + (12 * $groupCount), 0)
        . str_repeat("\0", 8192)
        . pack('N', $groupCount)
        . $groups;
    return $trueTypeWithCMapSubtableAndPostNames($format8, 3, 10, $glyphNames);
};

$trueTypeWithFormat10CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    if ($codeToGlyphName === []) {
        return $trueTypeWithCMapSubtableAndPostNames(pack('nnNNNN', 10, 0, 20, 0, 0, 0), 3, 10, []);
    }

    $firstCode = min(array_keys($codeToGlyphName));
    $lastCode = max(array_keys($codeToGlyphName));
    $glyphIdByCode = [];
    $glyphNames = [];
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $glyphIdByCode[$code] = count($glyphNames);
    }

    $glyphIds = '';
    for ($code = $firstCode; $code <= $lastCode; $code++) {
        $glyphIds .= pack('n', $glyphIdByCode[$code] ?? 0);
    }

    $entryCount = $lastCode - $firstCode + 1;
    $format10 = pack('nnNNNN', 10, 0, 20 + (2 * $entryCount), 0, $firstCode, $entryCount) . $glyphIds;
    return $trueTypeWithCMapSubtableAndPostNames($format10, 3, 10, $glyphNames);
};

$trueTypeWithFormat13CMapAndPostNames = static function (array $codeToGlyphName) use ($trueTypeWithCMapSubtableAndPostNames): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 1 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    ksort($codeToGlyphName, SORT_NUMERIC);
    if ($codeToGlyphName === []) {
        return $trueTypeWithCMapSubtableAndPostNames(pack('nnNNN', 13, 0, 16, 0, 0), 3, 10, []);
    }

    $glyphNames = [];
    $groups = '';
    foreach ($codeToGlyphName as $code => $glyphName) {
        $glyphNames[] = $glyphName;
        $groups .= pack('NNN', $code, $code, count($glyphNames));
    }

    $groupCount = count($codeToGlyphName);
    $format13 = pack('nnNNN', 13, 0, 16 + (12 * $groupCount), 0, $groupCount) . $groups;
    return $trueTypeWithCMapSubtableAndPostNames($format13, 3, 10, $glyphNames);
};

$cffType1CWithFormat0Encoding = static function (array $codeToGlyphName): string {
    $codeToGlyphName = array_filter($codeToGlyphName, static fn (string $glyphName, int $code): bool => $code >= 0 && $code <= 255 && $glyphName !== '', ARRAY_FILTER_USE_BOTH);
    $glyphNames = array_values($codeToGlyphName);
    $codes = array_keys($codeToGlyphName);
    $strings = array_values(array_unique($glyphNames));
    $stringSidByName = [];
    foreach ($strings as $index => $glyphName) {
        $stringSidByName[$glyphName] = 391 + $index;
    }

    $buildIndex = static function (array $objects): string {
        $count = count($objects);
        if ($count === 0) {
            return pack('n', 0);
        }

        $offsets = [1];
        $data = '';
        foreach ($objects as $object) {
            $data .= $object;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = $maxOffset <= 0xFF ? 1 : ($maxOffset <= 0xFFFF ? 2 : ($maxOffset <= 0xFFFFFF ? 3 : 4));
        $bytes = pack('nC', $count, $offSize);
        foreach ($offsets as $offset) {
            for ($shift = ($offSize - 1) * 8; $shift >= 0; $shift -= 8) {
                $bytes .= chr(($offset >> $shift) & 0xFF);
            }
        }

        return $bytes . $data;
    };
    $encodeCffInteger = static function (int $value): string {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;
            return chr(intdiv($adjusted, 256) + 247) . chr($adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;
            return chr(intdiv($adjusted, 256) + 251) . chr($adjusted % 256);
        }

        return chr(28) . pack('n', $value & 0xFFFF);
    };

    $nameIndex = $buildIndex(['TinyCFF']);
    $stringIndex = $buildIndex($strings);
    $globalSubrIndex = $buildIndex([]);
    $charset = "\0";
    foreach ($glyphNames as $glyphName) {
        $charset .= pack('n', $stringSidByName[$glyphName]);
    }
    $encoding = "\0" . chr(count($codes));
    foreach ($codes as $code) {
        $encoding .= chr($code);
    }
    $charStrings = $buildIndex(array_fill(0, count($glyphNames) + 1, chr(14)));
    $topDict = '';
    do {
        $topDictIndex = $buildIndex([$topDict]);
        $afterTopOffset = 4 + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
        $charsetOffset = $afterTopOffset;
        $encodingOffset = $charsetOffset + strlen($charset);
        $charStringsOffset = $encodingOffset + strlen($encoding);
        $nextTopDict = $encodeCffInteger($charsetOffset) . chr(15)
            . $encodeCffInteger($encodingOffset) . chr(16)
            . $encodeCffInteger($charStringsOffset) . chr(17);
    } while ($nextTopDict !== $topDict && ($topDict = $nextTopDict) !== '');

    return "\x01\x00\x04\x04"
        . $nameIndex
        . $buildIndex([$topDict])
        . $stringIndex
        . $globalSubrIndex
        . $charset
        . $encoding
        . $charStrings;
};

$cffType1CWithDefaultStandardEncoding = static function (int $glyphCount): string {
    $glyphCount = max(1, $glyphCount);
    $buildIndex = static function (array $objects): string {
        $count = count($objects);
        if ($count === 0) {
            return pack('n', 0);
        }

        $offsets = [1];
        $data = '';
        foreach ($objects as $object) {
            $data .= $object;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = $maxOffset <= 0xFF ? 1 : ($maxOffset <= 0xFFFF ? 2 : ($maxOffset <= 0xFFFFFF ? 3 : 4));
        $bytes = pack('nC', $count, $offSize);
        foreach ($offsets as $offset) {
            for ($shift = ($offSize - 1) * 8; $shift >= 0; $shift -= 8) {
                $bytes .= chr(($offset >> $shift) & 0xFF);
            }
        }

        return $bytes . $data;
    };
    $encodeCffInteger = static function (int $value): string {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;
            return chr(intdiv($adjusted, 256) + 247) . chr($adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;
            return chr(intdiv($adjusted, 256) + 251) . chr($adjusted % 256);
        }

        return chr(28) . pack('n', $value & 0xFFFF);
    };

    $nameIndex = $buildIndex(['DefaultCFF']);
    $stringIndex = $buildIndex([]);
    $globalSubrIndex = $buildIndex([]);
    $charStrings = $buildIndex(array_fill(0, $glyphCount, chr(14)));
    $topDict = '';
    do {
        $topDictIndex = $buildIndex([$topDict]);
        $charStringsOffset = 4 + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
        $nextTopDict = $encodeCffInteger($charStringsOffset) . chr(17);
    } while ($nextTopDict !== $topDict && ($topDict = $nextTopDict) !== '');

    return "\x01\x00\x04\x04"
        . $nameIndex
        . $buildIndex([$topDict])
        . $stringIndex
        . $globalSubrIndex
        . $charStrings;
};

$cffType1CWithPredefinedCharsetAndEncoding = static function (int $charsetOffset, int $encodingOffset, int $glyphCount): string {
    $glyphCount = max(1, $glyphCount);
    $buildIndex = static function (array $objects): string {
        $count = count($objects);
        if ($count === 0) {
            return pack('n', 0);
        }

        $offsets = [1];
        $data = '';
        foreach ($objects as $object) {
            $data .= $object;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = $maxOffset <= 0xFF ? 1 : ($maxOffset <= 0xFFFF ? 2 : ($maxOffset <= 0xFFFFFF ? 3 : 4));
        $bytes = pack('nC', $count, $offSize);
        foreach ($offsets as $offset) {
            for ($shift = ($offSize - 1) * 8; $shift >= 0; $shift -= 8) {
                $bytes .= chr(($offset >> $shift) & 0xFF);
            }
        }

        return $bytes . $data;
    };
    $encodeCffInteger = static function (int $value): string {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;
            return chr(intdiv($adjusted, 256) + 247) . chr($adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;
            return chr(intdiv($adjusted, 256) + 251) . chr($adjusted % 256);
        }

        return chr(28) . pack('n', $value & 0xFFFF);
    };

    $nameIndex = $buildIndex(['PredefinedCFF']);
    $stringIndex = $buildIndex([]);
    $globalSubrIndex = $buildIndex([]);
    $charStrings = $buildIndex(array_fill(0, $glyphCount, chr(14)));
    $topDict = '';
    do {
        $topDictIndex = $buildIndex([$topDict]);
        $charStringsOffset = 4 + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
        $nextTopDict = $encodeCffInteger($charsetOffset) . chr(15)
            . $encodeCffInteger($encodingOffset) . chr(16)
            . $encodeCffInteger($charStringsOffset) . chr(17);
    } while ($nextTopDict !== $topDict && ($topDict = $nextTopDict) !== '');

    return "\x01\x00\x04\x04"
        . $nameIndex
        . $buildIndex([$topDict])
        . $stringIndex
        . $globalSubrIndex
        . $charStrings;
};

$cffCidKeyedType0CWithFormat0Charset = static function (array $cids, string $ordering = 'UCS'): string {
    $cids = array_values(array_filter($cids, static fn (int $cid): bool => $cid >= 0 && $cid <= 0xFFFF));
    $glyphCount = count($cids) + 1;
    $buildIndex = static function (array $objects): string {
        $count = count($objects);
        if ($count === 0) {
            return pack('n', 0);
        }

        $offsets = [1];
        $data = '';
        foreach ($objects as $object) {
            $data .= $object;
            $offsets[] = strlen($data) + 1;
        }

        $maxOffset = max($offsets);
        $offSize = $maxOffset <= 0xFF ? 1 : ($maxOffset <= 0xFFFF ? 2 : ($maxOffset <= 0xFFFFFF ? 3 : 4));
        $bytes = pack('nC', $count, $offSize);
        foreach ($offsets as $offset) {
            for ($shift = ($offSize - 1) * 8; $shift >= 0; $shift -= 8) {
                $bytes .= chr(($offset >> $shift) & 0xFF);
            }
        }

        return $bytes . $data;
    };
    $encodeCffInteger = static function (int $value): string {
        if ($value >= -107 && $value <= 107) {
            return chr($value + 139);
        }

        if ($value >= 108 && $value <= 1131) {
            $adjusted = $value - 108;
            return chr(intdiv($adjusted, 256) + 247) . chr($adjusted % 256);
        }

        if ($value >= -1131 && $value <= -108) {
            $adjusted = -$value - 108;
            return chr(intdiv($adjusted, 256) + 251) . chr($adjusted % 256);
        }

        return chr(28) . pack('n', $value & 0xFFFF);
    };

    $nameIndex = $buildIndex(['CidCFF']);
    $stringIndex = $buildIndex(['Adobe', $ordering]);
    $registrySid = 391;
    $orderingSid = 392;
    $globalSubrIndex = $buildIndex([]);
    $charset = "\0";
    foreach ($cids as $cid) {
        $charset .= pack('n', $cid);
    }
    $fdSelect = "\0" . str_repeat("\0", $glyphCount);
    $fdArray = $buildIndex(['']);
    $charStrings = $buildIndex(array_fill(0, $glyphCount, chr(14)));
    $topDict = '';
    do {
        $topDictIndex = $buildIndex([$topDict]);
        $afterTopOffset = 4 + strlen($nameIndex) + strlen($topDictIndex) + strlen($stringIndex) + strlen($globalSubrIndex);
        $charsetOffset = $afterTopOffset;
        $fdSelectOffset = $charsetOffset + strlen($charset);
        $fdArrayOffset = $fdSelectOffset + strlen($fdSelect);
        $charStringsOffset = $fdArrayOffset + strlen($fdArray);
        $nextTopDict = $encodeCffInteger($registrySid) . $encodeCffInteger($orderingSid) . $encodeCffInteger(0) . chr(12) . chr(30)
            . $encodeCffInteger($charsetOffset) . chr(15)
            . $encodeCffInteger($charStringsOffset) . chr(17)
            . $encodeCffInteger($fdArrayOffset) . chr(12) . chr(36)
            . $encodeCffInteger($fdSelectOffset) . chr(12) . chr(37);
    } while ($nextTopDict !== $topDict && ($topDict = $nextTopDict) !== '');

    return "\x01\x00\x04\x04"
        . $nameIndex
        . $buildIndex([$topDict])
        . $stringIndex
        . $globalSubrIndex
        . $charset
        . $fdSelect
        . $fdArray
        . $charStrings;
};

$pdfWithCompressedObjectStream = static function (): string {
    $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj ET';
    $cmap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . "<0001> <004F0062006A006500630074002000530074007200650061006D>\n"
        . "<0002> <00200054006500780074>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /ObjectStreamCMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $packedObjects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type0 /BaseFont /CompressedSubset /Encoding /Identity-H /ToUnicode 6 0 R >>',
    ];
    $headerParts = [];
    $body = '';
    foreach ($packedObjects as $objectNumber => $objectBody) {
        $headerParts[] = $objectNumber . ' ' . strlen($body);
        $body .= $objectBody . "\n";
    }
    $header = implode(' ', $headerParts) . "\n";
    $objectStream = gzcompress($header . $body);

    return "%PDF-1.5\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /ObjStm /N " . count($packedObjects) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream\nendobj\n%%EOF";
};

$pdfWithIncrementalObjectStreamUpdate = static function (): string {
    $oldContent = 'BT /F1 12 Tf 72 720 Td (Old Revision Text) Tj ET';
    $newContent = 'BT /F1 12 Tf 72 720 Td (Incremental Object Stream Text) Tj ET';
    $updatedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 6 0 R >>';
    $header = '3 0' . "\n";
    $objectStream = gzcompress($header . $updatedPage . "\n");

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($oldContent) . " >>\nstream\n{$oldContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($newContent) . " >>\nstream\n{$newContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /ObjStm /N 1 /First " . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream\nendobj\n%%EOF";
};

$pdfWithCatalogReachableAndOrphanPages = static function (): string {
    $activeContent = 'BT /F1 12 Tf 72 720 Td (Catalog Reachable Page) Tj ET';
    $orphanContent = 'BT /F1 12 Tf 72 720 Td (Stale Orphan Page) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [7 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 7 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($activeContent) . " >>\nstream\n{$activeContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($orphanContent) . " >>\nstream\n{$orphanContent}\nendstream\nendobj\n%%EOF";
};

$pdfWithGeneratedContentReference = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Generation One Content) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Generation Zero Content) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 1 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 1 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n%%EOF";
};

$pdfWithPartialPageExtractionIssues = static function (): string {
    $readableContent = 'BT /F1 12 Tf 72 720 Td (Readable Page Text) Tj ET';
    $unsupportedContent = 'BT /F1 12 Tf 72 720 Td (Unsupported Page Text) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 6 0 R 8 0 R] /Count 3 /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($readableContent) . " >>\nstream\n{$readableContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Filter /DCTDecode /Length " . strlen($unsupportedContent) . " >>\nstream\n{$unsupportedContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 99 0 R >>\nendobj\n%%EOF";
};

$pdfWithFormXObjectPageExtractionIssues = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Visible Page Text) Tj ET q /FmBad Do Q q /FmBroken Do Q';
    $unsupportedFormContent = 'BT /F1 12 Tf 72 720 Td (Unsupported Form Text) Tj ET';
    $brokenFlateFormContent = 'not-a-flate-stream';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> /XObject << /FmBad 6 0 R /FmBroken 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] /Filter /DCTDecode /Length " . strlen($unsupportedFormContent) . " >>\nstream\n{$unsupportedFormContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] /Filter /FlateDecode /Length " . strlen($brokenFlateFormContent) . " >>\nstream\n{$brokenFlateFormContent}\nendstream\nendobj\n%%EOF";
};

$pdfWithUnresolvedXObjectPageExtractionIssues = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Visible XObject Resource Text) Tj ET q /FmMissing Do Q q /FmAbsent Do Q q /FmMalformed Do Q';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> /XObject << /FmMissing 99 0 R /FmMalformed 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] >>\nendobj\n%%EOF";
};

$pdfWithXrefFreedContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Xref Current Content) Tj ET';
    $freedContent = 'BT /F1 12 Tf 72 720 Td (Freed Stale Content) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents [5 0 R 8 0 R] >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        8 => "<< /Length " . strlen($freedContent) . " >>\nstream\n{$freedContent}\nendstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n";
    for ($objectNumber = 0; $objectNumber <= 8; $objectNumber++) {
        if ($objectNumber === 0 || $objectNumber === 8 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }

    return $pdf . "trailer << /Size 9 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithXrefSelectedDuplicateContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Xref Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Later Duplicate Content) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    for ($objectNumber = 0; $objectNumber <= 5; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }

    return $pdf . "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithEarlierValidStartxrefAfterMalformedAppend = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Earlier Valid Xref Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Bad Append Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    for ($objectNumber = 0; $objectNumber <= 5; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }

    $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";
    return $pdf . "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n999999\n%%EOF";
};

$xrefStreamEntry = static function (int $type, int $field2, int $field3): string {
    return chr($type) . pack('N', $field2) . pack('n', $field3);
};

$xrefStreamPngPredictorRows = static function (string $stream, int $columns): string {
    $encoded = '';
    foreach (str_split($stream, $columns) as $row) {
        $encoded .= "\0" . $row;
    }

    return $encoded;
};

$pdfWithXrefStreamSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Xref Stream Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Xref Stream Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithPredictorXrefStreamSelectedDuplicateContentObject = static function () use ($xrefStreamEntry, $xrefStreamPngPredictorRows): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Predictor Xref Stream Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Predictor Xref Stream Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $encodedRows = $xrefStreamPngPredictorRows(implode('', $entries), 7);
    $xrefStream = gzcompress($encodedRows);

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [0 10] /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 7 >> /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithZeroWidthXrefStreamSelectedDuplicateContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Zero Width Xref Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Zero Width Xref Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = '';
    foreach ([1, 2, 3, 4, 5] as $objectNumber) {
        $entries .= pack('N', $offsets[$objectNumber]);
    }
    $entries .= pack('N', $xrefOffset);
    $xrefStream = gzcompress($entries);

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [0 4 0] /Index [1 5 9 1] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithIndirectXrefStreamArraysSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect Xref Arrays Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Indirect Xref Arrays Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        10 => '[1 4 2]',
        11 => '[0 12]',
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 11; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 12 /Root 1 0 R /W 10 0 R /Index 11 0 R /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithLatestIndirectXrefStreamArraysSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Latest Indirect Xref Arrays Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Latest Indirect Xref Arrays Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        10 => '[1 4 2]',
        11 => '[0 12]',
    ];

    $pdf = "%PDF-1.5\n";
    $pdf .= "10 0 obj\n[1 1 1]\nendobj\n";
    $pdf .= "11 0 obj\n[0 6]\nendobj\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 11; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Size 12 /Root 1 0 R /W 10 0 R /Index 11 0 R /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithLatestIndirectXrefStreamSizeSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Latest Indirect Xref Size Selected Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Latest Indirect Xref Size Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 8 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        8 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        12 => '13',
    ];

    $pdf = "%PDF-1.5\n";
    $pdf .= "12 0 obj\n6\nendobj\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $pdf .= "8 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 12; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "9 0 obj\n<< /Type /XRef /Root 1 0 R /W [1 4 2] /Size 12 0 R /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithIndirectPrevXrefChainSelectedContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect Prev Chain Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Indirect Prev Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $baseXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    for ($objectNumber = 0; $objectNumber <= 5; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$baseXrefOffset}\n%%EOF\n";

    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";
    $prevOffsetObjectOffset = strlen($pdf);
    $pdf .= "20 0 obj\n{$baseXrefOffset}\nendobj\n";

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n20 1\n";
    $pdf .= sprintf("%010d 00000 n \n", $prevOffsetObjectOffset);

    return $pdf . "trailer << /Size 21 /Root 1 0 R /Prev 20 0 R >>\nstartxref\n{$latestXrefOffset}\n%%EOF";
};

$pdfWithNestedTrailerPrevXrefChainSelectedContentObject = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Nested Trailer Prev Content) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Nested Trailer Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $baseXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    for ($objectNumber = 0; $objectNumber <= 5; $objectNumber++) {
        if ($objectNumber === 0 || !isset($offsets[$objectNumber])) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$baseXrefOffset}\n%%EOF\n";

    $pdf .= "5 0 obj\n<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream\nendobj\n";
    $latestObjectOffset = strlen($pdf);
    $pdf .= "20 0 obj\n<< /Note (latest xref only) >>\nendobj\n";

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n20 1\n";
    $pdf .= sprintf("%010d 00000 n \n", $latestObjectOffset);

    return $pdf . "trailer << /Size 21 /Root 1 0 R /Info << /Producer (Nested Trailer) >> /Prev {$baseXrefOffset} >>\nstartxref\n{$latestXrefOffset}\n%%EOF";
};

$pdfWithIndirectXRefStmHybridSelectedContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect XRefStm Selected Content) Tj ET';
    $tableDuplicateContent = 'BT /F1 12 Tf 72 720 Td (Ignored Indirect XRefStm Duplicate) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $tableDuplicateOffset = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Length " . strlen($tableDuplicateContent) . " >>\nstream\n{$tableDuplicateContent}\nendstream\nendobj\n";

    $xrefStreamOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefStreamOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));
    $pdf .= "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n";

    $xrefStreamOffsetObjectOffset = strlen($pdf);
    $pdf .= "10 0 obj\n{$xrefStreamOffset}\nendobj\n";

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n0 11\n";
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 9 && $objectNumber !== 10)) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $objectOffset = $objectNumber === 5
            ? $tableDuplicateOffset
            : ($objectNumber === 9 ? $xrefStreamOffset : ($objectNumber === 10 ? $xrefStreamOffsetObjectOffset : $offsets[$objectNumber]));
        $pdf .= sprintf("%010d 00000 n \n", $objectOffset);
    }

    return $pdf . "trailer << /Size 11 /Root 1 0 R /XRefStm 10 0 R >>\nstartxref\n{$xrefTableOffset}\n%%EOF";
};

$pdfWithXrefStreamCompressedPageSelection = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Xref Stream Packed Page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Xref Stream Stale Packed Page) Tj ET';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>';
    $objectStreamHeader = '3 0 9 ' . strlen($currentPage . "\n") . "\n";
    $objectStream = $objectStreamHeader . $currentPage . "\n" . $stalePage . "\n";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 9 0 R] /Count 2 /Resources << /Font << /F1 4 0 R >> >> >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        6 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        7 => '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}endstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[3] = $xrefStreamEntry(2, 7, 0);
    $entries[10] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "10 0 obj\n<< /Type /XRef /Size 11 /Root 1 0 R /W [1 4 2] /Index [0 11] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithPredictorObjectStreamSelection = static function () use ($xrefStreamEntry, $xrefStreamPngPredictorRows): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Predictor ObjStm Packed Page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Predictor ObjStm Stale Page) Tj ET';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>';
    $objectStreamHeader = '3 0 9 ' . strlen($currentPage . "\n") . "\n";
    $objectStream = $objectStreamHeader . $currentPage . "\n" . $stalePage . "\n";
    $encodedObjectStreamRows = $xrefStreamPngPredictorRows($objectStream, strlen($objectStream));
    $encodedObjectStream = gzcompress($encodedObjectStreamRows);
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 9 0 R] /Count 2 /Resources << /Font << /F1 4 0 R >> >> >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        6 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        7 => '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns ' . strlen($objectStream) . ' >> /Length ' . strlen($encodedObjectStream) . " >>\nstream\n{$encodedObjectStream}endstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[3] = $xrefStreamEntry(2, 7, 0);
    $entries[10] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "10 0 obj\n<< /Type /XRef /Size 11 /Root 1 0 R /W [1 4 2] /Index [0 11] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithIndirectObjectStreamHeaderSelection = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect ObjStm Header Packed Page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Indirect ObjStm Header Stale Page) Tj ET';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>';
    $objectStreamHeader = '3 0 9 ' . strlen($currentPage . "\n") . "\n";
    $objectStream = $objectStreamHeader . $currentPage . "\n" . $stalePage . "\n";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 9 0 R] /Count 2 /Resources << /Font << /F1 4 0 R >> >> >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        6 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        7 => '<< /Type /ObjStm /N 11 0 R /First 12 0 R /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}endstream",
        11 => '2',
        12 => (string) strlen($objectStreamHeader),
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 12; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[3] = $xrefStreamEntry(2, 7, 0);
    $entries[10] = $xrefStreamEntry(1, $xrefOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));

    return $pdf
        . "10 0 obj\n<< /Type /XRef /Size 13 /Root 1 0 R /W [1 4 2] /Index [0 13] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$pdfWithHybridXrefStreamSelectedDuplicateContentObject = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Hybrid Xref Stream Selected Content) Tj ET';
    $tableDuplicateContent = 'BT /F1 12 Tf 72 720 Td (Hybrid Table Duplicate Content) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> >> >>',
        3 => '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }
    $tableDuplicateOffset = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Length " . strlen($tableDuplicateContent) . " >>\nstream\n{$tableDuplicateContent}\nendstream\nendobj\n";

    $xrefStreamOffset = strlen($pdf);
    $entries = [];
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        $entries[$objectNumber] = $xrefStreamEntry(0, 0, 65535);
    }
    foreach ($offsets as $objectNumber => $objectOffset) {
        $entries[$objectNumber] = $xrefStreamEntry(1, $objectOffset, 0);
    }
    $entries[9] = $xrefStreamEntry(1, $xrefStreamOffset, 0);
    $xrefStream = gzcompress(implode('', $entries));
    $pdf .= "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [0 10] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n";

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n0 10\n";
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 9)) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $objectOffset = $objectNumber === 5 ? $tableDuplicateOffset : ($objectNumber === 9 ? $xrefStreamOffset : $offsets[$objectNumber]);
        $pdf .= sprintf("%010d 00000 n \n", $objectOffset);
    }

    return $pdf . "trailer << /Size 10 /Root 1 0 R /XRefStm {$xrefStreamOffset} >>\nstartxref\n{$xrefTableOffset}\n%%EOF";
};

$pdfWithHybridXrefStreamCompressedPageSelection = static function () use ($xrefStreamEntry): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Hybrid Xref Stream Packed Page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Hybrid Xref Stream Stale Packed Page) Tj ET';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Contents 5 0 R >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>';
    $objectStreamHeader = '3 0 9 ' . strlen($currentPage . "\n") . "\n";
    $objectStream = $objectStreamHeader . $currentPage . "\n" . $stalePage . "\n";
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R 9 0 R] /Count 2 /Resources << /Font << /F1 4 0 R >> >> >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream",
        6 => "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream",
        7 => '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}endstream",
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $xrefStreamOffset = strlen($pdf);
    $xrefStream = gzcompress(
        $xrefStreamEntry(2, 7, 0)
        . $xrefStreamEntry(1, $xrefStreamOffset, 0)
    );
    $pdf .= "10 0 obj\n<< /Type /XRef /Size 11 /Root 1 0 R /W [1 4 2] /Index [3 1 10 1] /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n";

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n0 11\n";
    for ($objectNumber = 0; $objectNumber <= 10; $objectNumber++) {
        if ($objectNumber === 0 || $objectNumber === 3 || $objectNumber === 9 || (!isset($offsets[$objectNumber]) && $objectNumber !== 10)) {
            $pdf .= "0000000000 65535 f \n";
            continue;
        }

        $objectOffset = $objectNumber === 10 ? $xrefStreamOffset : $offsets[$objectNumber];
        $pdf .= sprintf("%010d 00000 n \n", $objectOffset);
    }

    return $pdf . "trailer << /Size 11 /Root 1 0 R /XRefStm {$xrefStreamOffset} >>\nstartxref\n{$xrefTableOffset}\n%%EOF";
};

$pdfWithHybridXrefStreamAndTablePreviousBranches = static function () use ($xrefStreamEntry): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Hybrid Prev Branch Text) Tj ET';
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    ];

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $pageBranchXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 4\n";
    $pdf .= "0000000000 65535 f \n";
    for ($objectNumber = 1; $objectNumber <= 3; $objectNumber++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer << /Size 10 >>\n";

    $objects = [
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream",
    ];
    foreach ($objects as $objectNumber => $objectBody) {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$objectBody}\nendobj\n";
    }

    $contentBranchXrefOffset = strlen($pdf);
    $pdf .= "xref\n4 2\n";
    for ($objectNumber = 4; $objectNumber <= 5; $objectNumber++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber]);
    }
    $pdf .= "trailer << /Size 10 >>\n";

    $xrefStreamOffset = strlen($pdf);
    $xrefStream = gzcompress($xrefStreamEntry(1, $xrefStreamOffset, 0));
    $pdf .= "9 0 obj\n<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Index [9 1] /Prev {$pageBranchXrefOffset} /Filter /FlateDecode /Length " . strlen($xrefStream) . " >>\nstream\n{$xrefStream}endstream\nendobj\n";

    $latestXrefOffset = strlen($pdf);
    $pdf .= "xref\n9 1\n";
    $pdf .= sprintf("%010d 00000 n \n", $xrefStreamOffset);

    return $pdf . "trailer << /Size 10 /Root 1 0 R /XRefStm {$xrefStreamOffset} /Prev {$contentBranchXrefOffset} >>\nstartxref\n{$latestXrefOffset}\n%%EOF";
};

$sameShapeTaggedCachePdf = static function (string $variant): string {
    if (!in_array($variant, ['Alpha', 'Bravo'], true)) {
        throw new RuntimeException('Unsupported tagged cache fixture variant.');
    }
    $content = "BT /F1 12 Tf 72 720 Td (Anchor {$variant}) Tj ET";
    $uriVariant = strtolower($variant);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 6 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /StructTreeRoot /K 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /StructElem /S /P /P 6 0 R /Pg 3 0 R /K 0 /Act#75alText (Tagged {$variant}) >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 710 200 740] /A << /S /URI /URI (https://{$uriVariant}.example.test/) >> >>\nendobj\n"
        . "9 0 obj\n<< /Fixture (same-shape-middle-padding) >>\nendobj\n"
        . "10 0 obj\n<< /Fixture (same-shape-cache-tail) >>\nendobj\n%%EOF";
};

return [
    'continues page text and graphics state across ordered Contents array members' => static function (TestRunner $t) use ($pdfWithPageContentArray): void {
        $pdf = $pdfWithPageContentArray([
            'q 1 0 0 1 10 20 cm BT /F1 12 Tf 1 0 0 1 72 720 Tm',
            '(Recovered continuation) Tj 0 -18 Td (Second source line) Tj ET Q',
        ]);
        $extractor = new PdfTextExtractor();

        $t->same(
            ['Recovered continuation', 'Second source line'],
            $extractor->extractTextRuns($pdf)
        );
        $t->same([
            ['page' => 1, 'stream' => 2, 'text' => 'Recovered continuation'],
            ['page' => 1, 'stream' => 2, 'text' => 'Second source line'],
        ], $extractor->extractTextLineItems($pdf));

        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $t->same(['Recovered continuation', 'Second source line'], array_column($positioned, 'text'));
        $t->same([2, 2], array_column($positioned, 'stream'));
        $t->same(82.0, $positioned[0]['textX1'] ?? null);
        $t->same(740.0, $positioned[0]['textY1'] ?? null);
        $t->same(722.0, $positioned[1]['textY1'] ?? null);
    },
    'preserves split Contents dictionary syntax while expanding Form XObjects' => static function (TestRunner $t): void {
        $firstContent = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm /Span << /MCID ';
        $secondContent = '7 >> BDC (Split dictionary line) Tj EMC ET q /Fm1 Do Q';
        $formContent = 'BT /F1 12 Tf 1 0 0 1 20 20 Tm (Form line) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /Font << /F1 4 0 R >> /XObject << /Fm1 7 0 R >> >> "
            . "/Contents [5 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($firstContent) . " >>\nstream\n{$firstContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($secondContent) . " >>\nstream\n{$secondContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] "
            . "/Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($formContent) . " >>\n"
            . "stream\n{$formContent}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(
            ['Split dictionary line', 'Form line'],
            $extractor->extractTextLines($pdf)
        );
        $t->same([
            ['page' => 1, 'stream' => 2, 'text' => 'Split dictionary line'],
            ['page' => 1, 'stream' => 2, 'text' => 'Form line'],
        ], $extractor->extractTextLineItems($pdf));
        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $t->same(['Split dictionary line', 'Form line'], array_column($positioned, 'text'));
        $t->same([2, 2], array_column($positioned, 'stream'));
    },
    'executes a repeated Contents reference at each ordered occurrence' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Repeated stream text) Tj ET ';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /Font << /F1 4 0 R >> >> /Contents [5 0 R 5 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(
            ['Repeated stream text', 'Repeated stream text'],
            $extractor->extractTextRuns($pdf)
        );
        $t->same([1, 2], array_column($extractor->extractTextLineItems($pdf), 'stream'));
        $t->same([1, 2], array_column($extractor->extractPositionedTextRuns($pdf), 'stream'));
        $t->same(2, $extractor->diagnostics($pdf)['textVisibility']['visibleRuns'] ?? null);
    },
    'retains operand source provenance when a text operator begins the next Contents member' => static function (TestRunner $t) use ($pdfWithPageContentArray): void {
        $pdf = $pdfWithPageContentArray([
            'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Split operand text)',
            'Tj ET',
        ]);
        $extractor = new PdfTextExtractor();

        $t->same(['Split operand text'], $extractor->extractTextRuns($pdf));
        $t->same([
            ['page' => 1, 'stream' => 1, 'text' => 'Split operand text'],
        ], $extractor->extractTextLineItems($pdf));
        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $t->same(['Split operand text'], array_column($positioned, 'text'));
        $t->same([1], array_column($positioned, 'stream'));
    },
    'closes cross-member ActualText once with its semantic operand provenance' => static function (TestRunner $t) use ($pdfWithPageContentArray): void {
        $pdf = $pdfWithPageContentArray([
            '/Span << /ActualText (Replacement text) >> BDC BT /F1 12 Tf 1 0 0 1 72 720 Tm',
            '(hidden glyphs) Tj ET EMC',
        ]);
        $extractor = new PdfTextExtractor();

        $t->same(['Replacement text'], $extractor->extractTextRuns($pdf));
        $t->same([
            ['page' => 1, 'stream' => 1, 'text' => 'Replacement text'],
        ], $extractor->extractTextLineItems($pdf));
        $positioned = $extractor->extractPositionedTextRuns($pdf);
        $t->same(['Replacement text'], array_column($positioned, 'text'));
        $t->same([1], array_column($positioned, 'stream'));
    },
    'indexes generation independent free xref conflicts without changing compressed member selection' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $indexMethod = new ReflectionMethod($extractor, 'xrefFreeReferencePrefixes');
        $allowsMethod = new ReflectionMethod($extractor, 'xrefAllowsEmbeddedObject');
        $compressed = [
            'status' => 'compressed',
            'objectStreamNumber' => 7,
            'objectStreamIndex' => 0,
        ];
        $conflictingEntries = [
            '3:0' => $compressed,
            '3:1' => ['status' => 'f'],
            '30:2' => ['status' => 'f'],
        ];
        $freePrefixes = $indexMethod->invoke($extractor, $conflictingEntries);

        $t->same(['3:' => true, '30:' => true], $freePrefixes);
        $t->same(false, $allowsMethod->invoke(
            $extractor,
            3,
            7,
            0,
            $conflictingEntries,
            $freePrefixes
        ));

        $selectedEntries = ['3:0' => $compressed, '30:2' => ['status' => 'f']];
        $selectedFreePrefixes = $indexMethod->invoke($extractor, $selectedEntries);
        $t->same(true, $allowsMethod->invoke(
            $extractor,
            3,
            7,
            0,
            $selectedEntries,
            $selectedFreePrefixes
        ));
        $t->same(false, $allowsMethod->invoke(
            $extractor,
            3,
            7,
            1,
            $selectedEntries,
            $selectedFreePrefixes
        ));
    },
    'recognizes only tokenized escaped structure replacement names across slash and percent delimiters' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $method = new ReflectionMethod($extractor, 'pdfValueContainsStructReplacementName');

        $t->same(true, $method->invoke(
            $extractor,
            '<< /Type /StructElem /Act#75alText (replacement/with%delimiters) /Noise /Value >>'
        ));
        $t->same(true, $method->invoke(
            $extractor,
            "<< /K [ << /Alt (nested/value%text) >> ] % /E (comment decoy)\n >>"
        ));
        $t->same(false, $method->invoke(
            $extractor,
            "<< /ActualTextual (not a key) /Label (/ActualText /Alt /E) % /ActualText (comment decoy)\n /E /NameOnly >>"
        ));
    },
    'isolates same shaped object graph caches when one extractor processes different PDFs' => static function (
        TestRunner $t
    ) use ($sameShapeTaggedCachePdf): void {
        $extractor = new PdfTextExtractor();
        $alphaPdf = $sameShapeTaggedCachePdf('Alpha');
        $bravoPdf = $sameShapeTaggedCachePdf('Bravo');

        $alpha = $extractor->diagnostics($alphaPdf);
        $t->same(['Anchor Alpha'], $extractor->extractTextLines($alphaPdf));
        $t->same('Tagged Alpha', $alpha['taggedStructureItems'][0]['text'] ?? null);
        $t->same('https://alpha.example.test/', $alpha['linkAnnotations'][0]['uri'] ?? null);
        $t->same('Anchor Alpha', $alpha['linkAnnotations'][0]['text'] ?? null);

        $bravo = $extractor->diagnostics($bravoPdf);
        $t->same(['Anchor Bravo'], $extractor->extractTextLines($bravoPdf));
        $t->same('Tagged Bravo', $bravo['taggedStructureItems'][0]['text'] ?? null);
        $t->same('https://bravo.example.test/', $bravo['linkAnnotations'][0]['uri'] ?? null);
        $t->same('Anchor Bravo', $bravo['linkAnnotations'][0]['text'] ?? null);

        $alphaWarm = $extractor->diagnostics($alphaPdf);
        $t->same($alpha['taggedStructureItems'], $alphaWarm['taggedStructureItems']);
        $t->same($alpha['taggedStructureBlocks'], $alphaWarm['taggedStructureBlocks']);
        $t->same($alpha['taggedTables'], $alphaWarm['taggedTables']);
        $t->same($alpha['linkAnnotations'], $alphaWarm['linkAnnotations']);
        $t->same(['Anchor Alpha'], $extractor->extractTextLines($alphaPdf));

        $password = 'cache-secret-marker';
        $passwordExtractor = new PdfTextExtractor(['pdfPassword' => $password]);
        $passwordExtractor->extractPageInventory($alphaPdf);
        $objectGraphKey = (new ReflectionProperty($passwordExtractor, 'pdfObjectsCacheKey'))->getValue($passwordExtractor);
        $t->same(64, is_string($objectGraphKey) ? strlen($objectGraphKey) : 0);
        $t->same(false, is_string($objectGraphKey) && str_contains($objectGraphKey, $password));
    },
    'keeps inherited page provenance exact when a shared structure child is memoized per page' => static function (TestRunner $t): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 10 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
            . "6 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructTreeRoot /K [11 0 R 12 0 R] >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Sect /P 10 0 R /Pg 3 0 R /K [0 13 0 R] >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /Sect /P 10 0 R /Pg 6 0 R /K [0 13 0 R] >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /Span /P 11 0 R /K 0 >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $first = $extractor->diagnostics($pdf)['taggedStructureItems'];
        $second = $extractor->diagnostics($pdf)['taggedStructureItems'];
        $byObject = [];
        foreach ($first as $item) {
            $byObject[$item['objectNumber']] = $item;
        }

        $t->same([1], $byObject[11]['pageNumbers'] ?? null);
        $t->same([3], $byObject[11]['pageObjects'] ?? null);
        $t->same([2], $byObject[12]['pageNumbers'] ?? null);
        $t->same([6], $byObject[12]['pageObjects'] ?? null);
        $t->same(false, array_key_exists('pageNumbers', $byObject[13] ?? []));
        $t->same($first, $second);
    },
    'extracts literal and array text operators from content streams' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td (Hello \\(WP\\)) Tj [(Data) 120 ( Liberation)] TJ ET";
        $runs = (new PdfTextExtractor())->extractTextRuns($pdfWithContent($content));
        $t->same(['Hello (WP)', 'Data Liberation'], $runs);
    },
    'separates a PDF name token immediately following a text operator' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (First) TJ/F2 12 Tf 30 0 Td (second) TJ/F1 12 Tf 42 0 Td (third) TJ ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithContent($content);

        $t->same(['First second third'], $extractor->extractTextLines($pdf));
        $t->same(['First', 'second', 'third'], $extractor->extractTextRuns($pdf));
        $t->same(['First', 'second', 'third'], array_map(
            static fn (array $run): string => $run['text'],
            $extractor->extractPositionedTextRuns($pdf)
        ));
    },
    'uses TJ array displacement as a visual word gap in text and positioned runs' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td '
            . '[(Health) -260 (care) -260 (providers) -260 (should) -260 (practice) -260 (hand) -260 (hygiene)] TJ '
            . 'T* [(Before) -260 (putting) -260 (on) -260 (gloves.)] TJ ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithContent($content);
        $positionedRuns = $extractor->extractPositionedTextRuns($pdf);

        $t->same([
            'Health care providers should practice hand hygiene',
            'Before putting on gloves.',
        ], $extractor->extractTextLines($pdf));
        $t->same([
            'Health care providers should practice hand hygiene',
            'Before putting on gloves.',
        ], $extractor->extractTextRuns($pdf));
        $t->same(['Health', 'care', 'providers', 'should'], array_slice(array_map(static fn (array $run): string => $run['text'], $positionedRuns), 0, 4));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'Beforeputtingongloves'));
    },
    'uses small-font TJ word gaps without splitting kerning fragments' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 8.9664 Tf 72 720 Td '
            . '[(a)-193(counter)-193(for)-193(each)-193(side)-193(exit)] TJ '
            . 'T* [(co)15(ver)-193(the)-193(loop,)-193(the)-193(VM)-193(must)-193(record)] TJ ET';
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithContent($content);

        $t->same([
            'a counter for each side exit',
            'cover the loop, the VM must record',
        ], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'co ver'));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'acounter'));
    },
    'keeps ambiguous isolated TJ gaps as visible word boundaries' => static function (TestRunner $t) use ($pdfWithContent): void {
        // A 0.22em TJ displacement can be either a word gap or tracking at a
        // style boundary. The extractor keeps the visible space; PdfReader
        // may remove it later only with independent document evidence.
        $pdf = $pdfWithContent('BT /F1 12 Tf 72 720 Td [(Hello) -220 (world)] TJ ET');
        $extractor = new PdfTextExtractor();

        $t->same(['Hello world'], $extractor->extractTextLines($pdf));
        $t->same(['Hello world'], $extractor->extractTextRuns($pdf));
        $t->same(['Hello', 'world'], array_map(
            static fn (array $run): string => $run['text'],
            $extractor->extractPositionedTextRuns($pdf)
        ));
    },
    'keeps untagged TJ gaps visible despite matching joined spellings elsewhere' => static function (TestRunner $t) use ($pdfWithContent): void {
        // Untagged TJ adjustments alone do not distinguish `a way` from
        // `away`. Repeated words elsewhere must not turn that ambiguity into
        // a spelling-based special case.
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf 72 720 Td [(This) -220 (a) -220 (way)] TJ ET '
            . 'BT /F1 12 Tf 72 704 Td (away) Tj ET '
            . 'BT /F1 12 Tf 72 688 Td (away) Tj ET'
        );
        $extractor = new PdfTextExtractor();

        $t->same(['This a way', 'away', 'away'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'This away'));
    },
    'extracts positioned text runs with page and stream metadata' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Item) Tj '
            . '1 0 0 1 180 720 Tm (Count) Tj '
            . '1 0 0 1 72 704 Tm (Apples) Tj '
            . '1 0 0 1 180 704 Tm (3) Tj '
            . 'ET';
        $runs = (new PdfTextExtractor())->extractPositionedTextRuns($pdfWithContent($content));

        $t->same(['Item', 'Count', 'Apples', '3'], array_map(static fn (array $run): string => $run['text'], $runs));
        $t->same(1, $runs[0]['page']);
        $t->same(1, $runs[0]['stream']);
        $t->same(12.0, $runs[0]['fontSize']);
        $t->same(72.0, $runs[0]['textX1']);
        $t->true($runs[0]['x1'] < $runs[1]['x1']);
        $t->true($runs[0]['x1'] < $runs[0]['textX1']);
        $t->true($runs[0]['y1'] > $runs[2]['y1']);
    },
    'retains subset BaseFont provenance on positioned text runs' => static function (TestRunner $t): void {
        $content = 'BT /FR 12 Tf 72 720 Td (Plain ) Tj '
            . '/FB 12 Tf (Bold ) Tj '
            . '/FI 12 Tf (Italic ) Tj '
            . '/FBI 12 Tf (Both) Tj ET';
        $toUnicode = "begincmap\n"
            . "1 begincodespacerange\n<00> <FF>\nendcodespacerange\n"
            . "1 beginbfrange\n<00> <FF> <0000>\nendbfrange\n"
            . "endcmap";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
            . "/Resources << /Font << /FR 4 0 R /FB 5 0 R /FI 6 0 R /FBI 7 0 R >> >> "
            . "/Contents 8 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /AAAAAA+HelveticaNeue /Encoding /WinAnsiEncoding /ToUnicode 9 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /BBBBBB+HelveticaNeue-Bold /Encoding /WinAnsiEncoding /ToUnicode 9 0 R >>\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /CCCCCC+HelveticaNeue-Italic /Encoding /WinAnsiEncoding /ToUnicode 9 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /DDDDDD+HelveticaNeue-BoldItalic /Encoding /WinAnsiEncoding /ToUnicode 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

        $runs = (new PdfTextExtractor())->extractPositionedTextRuns($pdf);

        $t->same(['FR', 'FB', 'FI', 'FBI'], array_column($runs, 'fontResource'));
        $t->same(
            ['HelveticaNeue', 'HelveticaNeue-Bold', 'HelveticaNeue-Italic', 'HelveticaNeue-BoldItalic'],
            array_column($runs, 'baseFont')
        );
        $t->same(['Plain ', 'Bold ', 'Italic ', 'Both'], array_column($runs, 'text'));
    },
    'extracts filled rectangles with non-white fill colors' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'q /DeviceRGB cs 0.9647058823529412 0.9686274509803922 0.9803921568627451 scn 70 714 230 18 re f Q '
            . 'q 0.85 g 320 714 80 18 re f Q q 1 1 1 rg 10 10 20 20 re f Q';
        $rectangles = (new PdfTextExtractor())->extractFilledRectangles($pdfWithContent($content));

        $t->same(2, count($rectangles));
        $t->same(1, $rectangles[0]['page']);
        $t->same(1, $rectangles[0]['stream']);
        $t->same('#f6f7fa', $rectangles[0]['fillColor']);
        $t->same(70.0, $rectangles[0]['x1']);
        $t->same(714.0, $rectangles[0]['y1']);
        $t->same(300.0, $rectangles[0]['x2']);
        $t->same(732.0, $rectangles[0]['y2']);
        $t->same('#d9d9d9', $rectangles[1]['fillColor']);
    },
    'extracts flate encoded content streams' => static function (TestRunner $t): void {
        $content = 'BT <48656c6c6f> Tj ET';
        $compressed = gzcompress($content);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /FlateDecode /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n%%EOF";
        $t->same('Hello', (new PdfTextExtractor())->extractPlainText($pdf));
    },
    'extracts ASCIIHex stream filters before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (ASCII Hex Import) Tj ET';
        $encoded = chunk_split(strtoupper(bin2hex($content)), 16, "\n") . '>';
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (Stacked Filter Import) Tj ET');
        $stacked = strtoupper(bin2hex($compressed)) . '>';
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /ASCIIHexDecode /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same('ASCII Hex Import', $extractor->extractPlainText($pdf));
        $t->same('Stacked Filter Import', $extractor->extractPlainText($stackedPdf));
    },
    'extracts RunLength stream filters before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Run Length Import) Tj T* (Clean Blocks) Tj ET';
        $encoded = chr(strlen($content) - 1) . $content . chr(128);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /RunLengthDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (Stacked RunLength Import) Tj ET');
        $stacked = chr(strlen($compressed) - 1) . $compressed . chr(128);
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /RunLengthDecode /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same(['Run Length Import', 'Clean Blocks'], $extractor->extractTextLines($pdf));
        $t->same('Stacked RunLength Import', $extractor->extractPlainText($stackedPdf));
    },
    'extracts ASCII85 stream filters before WordPress paragraph rendering' => static function (TestRunner $t) use ($ascii85Encode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (ASCII85 PDF Import) Tj T* (Clean WordPress Blocks) Tj ET';
        $encoded = $ascii85Encode($content);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /ASCII85Decode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $compressed = gzcompress('BT /F1 12 Tf 72 720 Td (Stacked ASCII85 Import) Tj ET');
        $stacked = $ascii85Encode($compressed);
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $wrappedContent = 'BT /F1 12 Tf 72 720 Td (Wrapped ASCII85 Import) Tj ET';
        $wrapped = '<~' . $ascii85Encode($wrappedContent);
        $wrappedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter /ASCII85Decode /Length " . strlen($wrapped) . " >>\nstream\n{$wrapped}\nendstream\nendobj\n%%EOF";

        $wrappedStackedContent = 'BT /F1 12 Tf 72 720 Td (Wrapped Stacked ASCII85 Import) Tj ET';
        $wrappedStacked = '<~' . $ascii85Encode(gzcompress($wrappedStackedContent));
        $wrappedStackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /Length " . strlen($wrappedStacked) . " >>\nstream\n{$wrappedStacked}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same(['ASCII85 PDF Import', 'Clean WordPress Blocks'], $extractor->extractTextLines($pdf));
        $t->same('Stacked ASCII85 Import', $extractor->extractPlainText($stackedPdf));
        $t->same('Wrapped ASCII85 Import', $extractor->extractPlainText($wrappedPdf));
        $t->same('Wrapped Stacked ASCII85 Import', $extractor->extractPlainText($wrappedStackedPdf));
    },
    'extracts LZW stream filters before WordPress paragraph rendering' => static function (TestRunner $t) use ($lzwEncode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (LZW PDF Import) Tj T* (Clean WordPress Blocks) Tj ET';
        $encoded = $lzwEncode($content);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Filter /LZWDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

        $stackedContent = 'BT /F1 12 Tf 72 720 Td (Stacked LZW Import) Tj ET';
        $stacked = strtoupper(bin2hex($lzwEncode($stackedContent))) . '>';
        $stackedPdf = "%PDF-1.4\n1 0 obj\n<< /Filter [ /ASCIIHexDecode /LZWDecode ] /Length " . strlen($stacked) . " >>\nstream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $predictorContent = 'BT /F1 12 Tf 72 720 Td (LZW Predictor Import) Tj ET';
        $predicted = "\0" . $predictorContent;
        $predictorEncoded = $lzwEncode($predicted);
        $predictorPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /LZWDecode /DecodeParms << /Predictor 12 /Columns " . strlen($predictorContent) . " >> /Length " . strlen($predictorEncoded) . " >>\n"
            . "stream\n{$predictorEncoded}\nendstream\nendobj\n%%EOF";

        $earlyChangeContent = 'BT /F1 12 Tf 72 720 Td (LZW EarlyChange Zero) Tj T* (' . str_repeat('EarlyChangeZero ', 80) . ') Tj ET';
        $earlyChangeEncoded = $lzwEncode($earlyChangeContent, 0);
        $earlyChangePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /LZWDecode /DecodeParms << /EarlyChange 0 >> /Length " . strlen($earlyChangeEncoded) . " >>\n"
            . "stream\n{$earlyChangeEncoded}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same(['LZW PDF Import', 'Clean WordPress Blocks'], $extractor->extractTextLines($pdf));
        $t->same('Stacked LZW Import', $extractor->extractPlainText($stackedPdf));
        $t->same('LZW Predictor Import', $extractor->extractPlainText($predictorPdf));
        $t->contains('LZW EarlyChange Zero', $extractor->extractPlainText($earlyChangePdf));
    },
    'extracts Crypt Identity stream filters and preserves DecodeParms null alignment before WordPress paragraph rendering' => static function (TestRunner $t) use ($lzwEncode): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Crypt Identity Import) Tj T* (Clean WordPress Blocks) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /Crypt /DecodeParms << /Name /Identity >> /Length " . strlen($content) . " >>\n"
            . "stream\n{$content}\nendstream\nendobj\n%%EOF";

        $stackedContent = 'BT /F1 12 Tf 72 720 Td (Stacked Crypt Identity Import) Tj ET';
        $stackedCompressed = gzcompress($stackedContent);
        $stackedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /Identity >> null ] /Length " . strlen($stackedCompressed) . " >>\n"
            . "stream\n{$stackedCompressed}\nendstream\nendobj\n%%EOF";

        $alignmentContent = 'BT /F1 12 Tf 72 720 Td (DecodeParms Null Alignment) Tj ET';
        $alignmentLzw = $lzwEncode($alignmentContent);
        $alignmentPredicted = "\0" . $alignmentLzw;
        $alignmentCompressed = gzcompress($alignmentPredicted);
        $alignmentPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ /FlateDecode /LZWDecode ] /DecodeParms [ << /Predictor 12 /Columns " . strlen($alignmentLzw) . " >> null ] /Length " . strlen($alignmentCompressed) . " >>\n"
            . "stream\n{$alignmentCompressed}\nendstream\nendobj\n%%EOF";

        $unsupportedCryptPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /Crypt /DecodeParms << /Name /StdCF >> /Length " . strlen($content) . " >>\n"
            . "stream\n{$content}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($unsupportedCryptPdf);
        $t->same(['Crypt Identity Import', 'Clean WordPress Blocks'], $extractor->extractTextLines($pdf));
        $t->same('Stacked Crypt Identity Import', $extractor->extractPlainText($stackedPdf));
        $t->same('DecodeParms Null Alignment', $extractor->extractPlainText($alignmentPdf));
        $t->same('', $extractor->extractPlainText($unsupportedCryptPdf));
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(1, $diagnostics['failedStreams']);
    },
    'decodes escaped stream filter names before WordPress paragraph rendering' => static function (TestRunner $t) use ($lzwEncode): void {
        $flateContent = 'BT /F1 12 Tf 72 720 Td (Escaped Flate Filter Import) Tj ET';
        $compressed = gzcompress($flateContent);
        $flatePdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /Flate#44ecode /Length " . strlen($compressed) . " >>\n"
            . "stream\n{$compressed}\nendstream\nendobj\n%%EOF";

        $stackedContent = 'BT /F1 12 Tf 72 720 Td (Escaped Stacked Filter Import) Tj ET';
        $stacked = strtoupper(bin2hex(gzcompress($stackedContent))) . '>';
        $stackedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter [ /ASCIIHex#44ecode /Flate#44ecode ] /Length " . strlen($stacked) . " >>\n"
            . "stream\n{$stacked}\nendstream\nendobj\n%%EOF";

        $lzwContent = 'BT /F1 12 Tf 72 720 Td (Escaped Indirect LZW Import) Tj ET';
        $lzwEncoded = $lzwEncode($lzwContent);
        $lzwPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter 2 0 R /Length " . strlen($lzwEncoded) . " >>\nstream\n{$lzwEncoded}\nendstream\nendobj\n"
            . "2 0 obj\n/LZW#44ecode\nendobj\n%%EOF";

        $cryptContent = 'BT /F1 12 Tf 72 720 Td (Escaped Crypt Identity Import) Tj ET';
        $cryptPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /Cr#79pt /DecodeParms << /Name /Identit#79 >> /Length " . strlen($cryptContent) . " >>\n"
            . "stream\n{$cryptContent}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same('Escaped Flate Filter Import', $extractor->extractPlainText($flatePdf));
        $t->same('Escaped Stacked Filter Import', $extractor->extractPlainText($stackedPdf));
        $t->same('Escaped Indirect LZW Import', $extractor->extractPlainText($lzwPdf));
        $t->same('Escaped Crypt Identity Import', $extractor->extractPlainText($cryptPdf));
    },
    'applies Flate PNG predictors before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Predictor PDF Import) Tj T* (Clean Blocks) Tj ET';
        $predicted = "\0" . $content;
        $compressed = gzcompress($predicted);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns " . strlen($content) . " >> /Length " . strlen($compressed) . " >>\n"
            . "stream\n{$compressed}\nendstream\nendobj\n%%EOF";

        $paethContent = 'BT /F1 12 Tf 72 720 Td (Dynamic Predictor Import) Tj ET';
        $paethPredicted = "\2" . $paethContent;
        $paethCompressed = gzcompress($paethPredicted);
        $paethPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 15 /Columns " . strlen($paethContent) . " >> /Length " . strlen($paethCompressed) . " >>\n"
            . "stream\n{$paethCompressed}\nendstream\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same(['Predictor PDF Import', 'Clean Blocks'], $extractor->extractTextLines($pdf));
        $t->same('Dynamic Predictor Import', $extractor->extractPlainText($paethPdf));
    },
    'resolves indirect stream filters before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Indirect Filter Import) Tj T* (Clean Blocks) Tj ET';
        $encoded = strtoupper(bin2hex($content)) . '>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter 2 0 R /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n"
            . "2 0 obj\n/ASCIIHexDecode\nendobj\n%%EOF";

        $predictorContent = 'BT /F1 12 Tf 72 720 Td (Indirect Predictor Import) Tj ET';
        $predicted = "\0" . $predictorContent;
        $compressed = gzcompress($predicted);
        $predictorPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter 2 0 R /DecodeParms 3 0 R /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
            . "2 0 obj\n/FlateDecode\nendobj\n"
            . "3 0 obj\n<< /Predictor 12 /Columns " . strlen($predictorContent) . " >>\nendobj\n%%EOF";

        $stackedContent = 'BT /F1 12 Tf 72 720 Td (Stacked Indirect Filter Import) Tj ET';
        $stackedEncoded = strtoupper(bin2hex(gzcompress($stackedContent))) . '>';
        $stackedPdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Filter 2 0 R /Length " . strlen($stackedEncoded) . " >>\nstream\n{$stackedEncoded}\nendstream\nendobj\n"
            . "2 0 obj\n[ /ASCIIHexDecode /FlateDecode ]\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $t->same(['Indirect Filter Import', 'Clean Blocks'], $extractor->extractTextLines($pdf));
        $t->same('Indirect Predictor Import', $extractor->extractPlainText($predictorPdf));
        $t->same('Stacked Indirect Filter Import', $extractor->extractPlainText($stackedPdf));
    },
    'reports extraction diagnostics for encrypted unsupported and malformed pdf structures' => static function (TestRunner $t): void {
        $unsupportedContent = 'BT /F1 12 Tf 72 720 Td (Diagnostic Text) Tj ET';
        $badObjectStream = 'not flate object stream';
        $badXrefStream = 'not flate xref stream';
        $pdf = "%PDF-1.5\n"
            . "1 0 obj\n<< /Length " . strlen($unsupportedContent) . " /Filter /DCTDecode >>\nstream\n{$unsupportedContent}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode /Length " . strlen($badObjectStream) . " >>\nstream\n{$badObjectStream}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XRef /Size 6 /W [1 4 2] /Filter /FlateDecode /Length " . strlen($badXrefStream) . " >>\nstream\n{$badXrefStream}\nendstream\nendobj\n"
            . "trailer << /Encrypt 9 0 R >>\nstartxref\n999999\n%%EOF";
        $diagnostics = (new PdfTextExtractor())->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(true, $diagnostics['encrypted']);
        $t->same(['DCTDecode'], $diagnostics['unsupportedFilters']);
        $t->same([999999], $diagnostics['malformedXrefOffsets']);
        $t->same(1, $diagnostics['malformedXrefStreams']);
        $t->same(1, $diagnostics['malformedObjectStreams']);
        $t->same(1, $diagnostics['failedStreams']);
        $t->contains('encrypted', $warningText);
        $t->contains('Unsupported PDF stream filters: DCTDecode.', $warningText);
        $t->contains('Malformed PDF xref data was detected.', $warningText);
        $t->contains('PDF object stream', $warningText);
    },
    'decrypts Standard R2 empty-password content streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($standardR2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted PDF Text) Tj T* (Second encrypted line) Tj ET';
        $pdf = $standardR2EncryptedPdfWithContent($content);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted PDF Text', 'Second encrypted line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted PDF Text', 'Second encrypted line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted PDF Text\nSecond encrypted line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R2-empty-user-password', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R3 128-bit empty-password RC4 content streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($standardR3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R3 PDF Text) Tj T* (Second R3 encrypted line) Tj ET';
        $pdf = $standardR3EncryptedPdfWithContent($content);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R3 PDF Text', 'Second R3 encrypted line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R3 PDF Text', 'Second R3 encrypted line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R3 PDF Text\nSecond R3 encrypted line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R3-empty-user-password-RC4', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R4 V2 crypt-filter content streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($standardR4V2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R4 PDF Text) Tj T* (Second R4 crypt filter line) Tj ET';
        $pdf = $standardR4V2EncryptedPdfWithContent($content);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R4 PDF Text', 'Second R4 crypt filter line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 PDF Text', 'Second R4 crypt filter line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R4 PDF Text\nSecond R4 crypt filter line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-RC4', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R4 AESV2 crypt-filter content streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R4 AES PDF Text) Tj T* (Second R4 AES crypt filter line) Tj ET';
        $pdf = $standardR4AesV2EncryptedPdfWithContent($content);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R4 AES PDF Text', 'Second R4 AES crypt filter line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 AES PDF Text', 'Second R4 AES crypt filter line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R4 AES PDF Text\nSecond R4 AES crypt filter line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-AESV2', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R4 AESV2 crypt-filter content streams with supplied user password' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R4 AES user PDF Text) Tj T* (Second R4 AES user line) Tj ET';
        $pdf = $standardR4AesV2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', 'reader-pass', 'owner-pass');
        $lockedDiagnostics = (new PdfTextExtractor())->diagnostics($pdf);
        $extractor = new PdfTextExtractor(['password' => 'reader-pass']);
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(true, $lockedDiagnostics['encrypted']);
        $t->same(false, $lockedDiagnostics['encryptionDecrypted']);
        $t->same(['Encrypted R4 AES user PDF Text', 'Second R4 AES user line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 AES user PDF Text', 'Second R4 AES user line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R4 AES user PDF Text\nSecond R4 AES user line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-user-password-AESV2', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R4 AESV2 crypt-filter content streams with supplied owner password' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R4 AES owner PDF Text) Tj T* (Second R4 AES owner line) Tj ET';
        $pdf = $standardR4AesV2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', 'reader-pass', 'owner-pass');
        $extractor = new PdfTextExtractor(['password' => 'owner-pass']);
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R4 AES owner PDF Text', 'Second R4 AES owner line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 AES owner PDF Text', 'Second R4 AES owner line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R4 AES owner PDF Text\nSecond R4 AES owner line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-owner-password-AESV2', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R5 AESV3 crypt-filter content streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($standardR5AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R5 AES PDF Text) Tj T* (Second R5 AES crypt filter line) Tj ET';
        $pdf = $standardR5AesV3EncryptedPdfWithContent($content);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R5 AES PDF Text', 'Second R5 AES crypt filter line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R5 AES PDF Text', 'Second R5 AES crypt filter line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R5 AES PDF Text\nSecond R5 AES crypt filter line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R5-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R6 AESV3 crypt-filter content streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R6 AES PDF Text) Tj T* (Second R6 AES crypt filter line) Tj ET';
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R6 AES PDF Text', 'Second R6 AES crypt filter line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R6 AES PDF Text', 'Second R6 AES crypt filter line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R6 AES PDF Text\nSecond R6 AES crypt filter line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reports Standard R6 AESV3 no-copy permission policy while extracting decrypted text' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R6 no copy Text) Tj T* (Permission policy line) Tj ET';
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', false, false, -20);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R6 no copy Text', 'Permission policy line'], $extractor->extractTextLines($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same('empty-user-password', $diagnostics['encryptionPasswordType']);
        $t->same(-20, $diagnostics['encryptionPermissions']['raw']);
        $t->same(4294967276, $diagnostics['encryptionPermissions']['unsigned']);
        $t->same(true, $diagnostics['encryptionPermissions']['print']);
        $t->same(true, $diagnostics['encryptionPermissions']['modify']);
        $t->same(false, $diagnostics['encryptionPermissions']['copy']);
        $t->same(true, $diagnostics['encryptionPermissions']['annotate']);
        $t->same(true, $diagnostics['encryptionPermissions']['fillForms']);
        $t->same(true, $diagnostics['encryptionPermissions']['extractAccessibility']);
        $t->same(true, $diagnostics['encryptionPermissions']['assemble']);
        $t->same(true, $diagnostics['encryptionPermissions']['printHighResolution']);
        $t->same(false, $diagnostics['encryptionAllowsContentExtraction']);
        $t->contains('PDF permissions disallow content copying or extraction.', $warningText);
    },
    'reports older Standard Security no-copy permission policies while extracting decrypted text' => static function (TestRunner $t) use ($standardR2EncryptedPdfWithContent, $standardR3EncryptedPdfWithContent, $standardR4V2EncryptedPdfWithContent, $standardR4AesV2EncryptedPdfWithContent, $standardR5AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Older no copy Text) Tj T* (Older permission line) Tj ET';
        $cases = [
            'Standard-R2-empty-user-password' => $standardR2EncryptedPdfWithContent($content, null, -20),
            'Standard-R3-empty-user-password-RC4' => $standardR3EncryptedPdfWithContent($content, -20),
            'Standard-R4-empty-user-password-RC4' => $standardR4V2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', -20),
            'Standard-R4-empty-user-password-AESV2' => $standardR4AesV2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', -20),
            'Standard-R5-empty-user-password-AESV3' => $standardR5AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', -20),
        ];

        foreach ($cases as $expectedHandler => $pdf) {
            $extractor = new PdfTextExtractor();
            $diagnostics = $extractor->diagnostics($pdf);
            $warningText = implode("\n", $diagnostics['warnings']);

            $t->same(['Older no copy Text', 'Older permission line'], $extractor->extractTextLines($pdf));
            $t->same(true, $diagnostics['encrypted']);
            $t->same(true, $diagnostics['encryptionDecrypted']);
            $t->same($expectedHandler, $diagnostics['encryptionHandler']);
            $t->same('empty-user-password', $diagnostics['encryptionPasswordType']);
            $t->same(-20, $diagnostics['encryptionPermissions']['raw']);
            $t->same(4294967276, $diagnostics['encryptionPermissions']['unsigned']);
            $t->same(false, $diagnostics['encryptionPermissions']['copy']);
            $t->same(false, $diagnostics['encryptionAllowsContentExtraction']);
            $t->contains('PDF permissions disallow content copying or extraction.', $warningText);
        }
    },
    'reports non-copy Standard permission restrictions without blocking content extraction' => static function (TestRunner $t) use ($standardR2EncryptedPdfWithContent, $standardR3EncryptedPdfWithContent, $standardR4V2EncryptedPdfWithContent, $standardR4AesV2EncryptedPdfWithContent, $standardR5AesV3EncryptedPdfWithContent, $standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Restricted but copyable Text) Tj T* (Policy detail line) Tj ET';
        $permissions = -3888;
        $cases = [
            'Standard-R2-empty-user-password' => $standardR2EncryptedPdfWithContent($content, null, $permissions),
            'Standard-R3-empty-user-password-RC4' => $standardR3EncryptedPdfWithContent($content, $permissions),
            'Standard-R4-empty-user-password-RC4' => $standardR4V2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', $permissions),
            'Standard-R4-empty-user-password-AESV2' => $standardR4AesV2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', $permissions),
            'Standard-R5-empty-user-password-AESV3' => $standardR5AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', $permissions),
            'Standard-R6-empty-user-password-AESV3' => $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', false, false, $permissions),
        ];

        foreach ($cases as $expectedHandler => $pdf) {
            $extractor = new PdfTextExtractor();
            $diagnostics = $extractor->diagnostics($pdf);
            $warningText = implode("\n", $diagnostics['warnings']);

            $t->same(['Restricted but copyable Text', 'Policy detail line'], $extractor->extractTextLines($pdf));
            $t->same(true, $diagnostics['encrypted']);
            $t->same(true, $diagnostics['encryptionDecrypted']);
            $t->same($expectedHandler, $diagnostics['encryptionHandler']);
            $t->same('empty-user-password', $diagnostics['encryptionPasswordType']);
            $t->same($permissions, $diagnostics['encryptionPermissions']['raw']);
            $t->same(4294963408, $diagnostics['encryptionPermissions']['unsigned']);
            $t->same(false, $diagnostics['encryptionPermissions']['print']);
            $t->same(false, $diagnostics['encryptionPermissions']['modify']);
            $t->same(true, $diagnostics['encryptionPermissions']['copy']);
            $t->same(false, $diagnostics['encryptionPermissions']['annotate']);
            $t->same(false, $diagnostics['encryptionPermissions']['fillForms']);
            $t->same(false, $diagnostics['encryptionPermissions']['extractAccessibility']);
            $t->same(false, $diagnostics['encryptionPermissions']['assemble']);
            $t->same(false, $diagnostics['encryptionPermissions']['printHighResolution']);
            $t->same(true, $diagnostics['encryptionAllowsContentExtraction']);
            $t->true(!str_contains($warningText, 'PDF permissions disallow content copying or extraction.'));
        }
    },
    'accepts unsigned Standard permission literals while deriving keys and diagnostics' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Unsigned permission Text) Tj T* (Unsigned policy line) Tj ET';
        $permissions = 4294967276;
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', false, false, $permissions);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Unsigned permission Text', 'Unsigned policy line'], $extractor->extractTextLines($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same('empty-user-password', $diagnostics['encryptionPasswordType']);
        $t->same($permissions, $diagnostics['encryptionPermissions']['raw']);
        $t->same($permissions, $diagnostics['encryptionPermissions']['unsigned']);
        $t->same(false, $diagnostics['encryptionPermissions']['copy']);
        $t->same(false, $diagnostics['encryptionAllowsContentExtraction']);
        $t->contains('PDF permissions disallow content copying or extraction.', $warningText);
    },
    'decrypts Standard R6 AESV3 crypt-filter content streams with supplied user password' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R6 AES user PDF Text) Tj T* (Second R6 AES user line) Tj ET';
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', 'reader-pass', 'owner-pass');
        $lockedDiagnostics = (new PdfTextExtractor())->diagnostics($pdf);
        $extractor = new PdfTextExtractor(['password' => 'reader-pass']);
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(true, $lockedDiagnostics['encrypted']);
        $t->same(false, $lockedDiagnostics['encryptionDecrypted']);
        $t->same(['Encrypted R6 AES user PDF Text', 'Second R6 AES user line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R6 AES user PDF Text', 'Second R6 AES user line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R6 AES user PDF Text\nSecond R6 AES user line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R6 AESV3 crypt-filter content streams with supplied owner password' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R6 AES owner PDF Text) Tj T* (Second R6 AES owner line) Tj ET';
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', 'reader-pass', 'owner-pass');
        $extractor = new PdfTextExtractor(['password' => 'owner-pass']);
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R6 AES owner PDF Text', 'Second R6 AES owner line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R6 AES owner PDF Text', 'Second R6 AES owner line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R6 AES owner PDF Text\nSecond R6 AES owner line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-owner-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'treats Standard R6 AESV3 owner password as overriding no-copy permission policy' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R6 owner permission Text) Tj T* (Owner override line) Tj ET';
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', 'reader-pass', 'owner-pass', false, false, -20);
        $extractor = new PdfTextExtractor(['password' => 'owner-pass']);
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R6 owner permission Text', 'Owner override line'], $extractor->extractTextLines($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-owner-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same('owner-password', $diagnostics['encryptionPasswordType']);
        $t->same(false, $diagnostics['encryptionPermissions']['copy']);
        $t->same(true, $diagnostics['encryptionAllowsContentExtraction']);
        $t->true(!str_contains($warningText, 'PDF permissions disallow content copying or extraction.'));
    },
    'treats Standard R4 AESV2 owner password as overriding no-copy permission policy' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R4 owner permission Text) Tj T* (Older owner override line) Tj ET';
        $pdf = $standardR4AesV2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', 'reader-pass', 'owner-pass', -20);
        $extractor = new PdfTextExtractor(['password' => 'owner-pass']);
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R4 owner permission Text', 'Older owner override line'], $extractor->extractTextLines($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-owner-password-AESV2', $diagnostics['encryptionHandler']);
        $t->same('owner-password', $diagnostics['encryptionPasswordType']);
        $t->same(false, $diagnostics['encryptionPermissions']['copy']);
        $t->same(true, $diagnostics['encryptionAllowsContentExtraction']);
        $t->true(!str_contains($warningText, 'PDF permissions disallow content copying or extraction.'));
    },
    'decrypts Standard R4 AESV2 encrypted object streams before diagnostics' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R4 object stream PDF Text) Tj T* (R4 packed page resource line) Tj ET';
        $pdf = $standardR4AesV2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', -4, true);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R4 object stream PDF Text', 'R4 packed page resource line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 object stream PDF Text', 'R4 packed page resource line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R4 object stream PDF Text\nR4 packed page resource line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-AESV2', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(0, $diagnostics['failedStreams']);
        $t->same(0, $diagnostics['malformedObjectStreams']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'decrypts Standard R4 AESV2 encrypted xref streams before selecting encrypted object stream entries' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R4 xref object stream PDF Text) Tj T* (Selected R4 encrypted packed page line) Tj ET';
        $pdf = $standardR4AesV2EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', -4, true, true);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Encrypted R4 xref object stream PDF Text', 'Selected R4 encrypted packed page line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 xref object stream PDF Text', 'Selected R4 encrypted packed page line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R4 xref object stream PDF Text\nSelected R4 encrypted packed page line", $plainText);
        $t->true(!str_contains($plainText, 'Ignored R4 encrypted xref duplicate'));
        $t->true(!str_contains($plainText, 'Ignored R4 encrypted xref object stream stale'));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-AESV2', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(0, $diagnostics['failedStreams']);
        $t->same(0, $diagnostics['malformedXrefStreams']);
        $t->same(0, $diagnostics['malformedObjectStreams']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'xref stream'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'decrypts Standard R5 AESV3 encrypted object streams before diagnostics' => static function (TestRunner $t) use ($standardR5AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R5 object stream PDF Text) Tj T* (R5 packed page resource line) Tj ET';
        $pdf = $standardR5AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', -4, true);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R5 object stream PDF Text', 'R5 packed page resource line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R5 object stream PDF Text', 'R5 packed page resource line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R5 object stream PDF Text\nR5 packed page resource line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R5-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(0, $diagnostics['failedStreams']);
        $t->same(0, $diagnostics['malformedObjectStreams']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'decrypts Standard R5 AESV3 encrypted xref streams before selecting encrypted object stream entries' => static function (TestRunner $t) use ($standardR5AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R5 xref object stream PDF Text) Tj T* (Selected R5 encrypted packed page line) Tj ET';
        $pdf = $standardR5AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', -4, true, true);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Encrypted R5 xref object stream PDF Text', 'Selected R5 encrypted packed page line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R5 xref object stream PDF Text', 'Selected R5 encrypted packed page line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R5 xref object stream PDF Text\nSelected R5 encrypted packed page line", $plainText);
        $t->true(!str_contains($plainText, 'Ignored R5 encrypted xref duplicate'));
        $t->true(!str_contains($plainText, 'Ignored R5 encrypted xref object stream stale'));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R5-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(0, $diagnostics['failedStreams']);
        $t->same(0, $diagnostics['malformedXrefStreams']);
        $t->same(0, $diagnostics['malformedObjectStreams']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'xref stream'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'decrypts Standard R6 AESV3 encrypted object streams before diagnostics' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R6 object stream PDF Text) Tj T* (Packed page resource line) Tj ET';
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', true);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R6 object stream PDF Text', 'Packed page resource line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R6 object stream PDF Text', 'Packed page resource line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R6 object stream PDF Text\nPacked page resource line", $extractor->extractPlainText($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(0, $diagnostics['failedStreams']);
        $t->same(0, $diagnostics['malformedObjectStreams']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'decrypts Standard R6 AESV3 encrypted xref streams before selecting content objects' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R6 xref stream PDF Text) Tj T* (Selected encrypted xref line) Tj ET';
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', false, true);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Encrypted R6 xref stream PDF Text', 'Selected encrypted xref line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R6 xref stream PDF Text', 'Selected encrypted xref line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R6 xref stream PDF Text\nSelected encrypted xref line", $plainText);
        $t->true(!str_contains($plainText, 'Ignored encrypted xref duplicate'));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(0, $diagnostics['failedStreams']);
        $t->same([], $diagnostics['malformedXrefOffsets']);
        $t->same(0, $diagnostics['malformedXrefStreams']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'Malformed PDF xref'));
    },
    'decrypts Standard R6 AESV3 encrypted xref streams before selecting encrypted object stream entries' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R6 xref object stream PDF Text) Tj T* (Selected encrypted packed page line) Tj ET';
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, null, 'StdCF', 'StdCF', '', '', true, true);
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Encrypted R6 xref object stream PDF Text', 'Selected encrypted packed page line'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R6 xref object stream PDF Text', 'Selected encrypted packed page line'], $extractor->extractTextRuns($pdf));
        $t->same("Encrypted R6 xref object stream PDF Text\nSelected encrypted packed page line", $plainText);
        $t->true(!str_contains($plainText, 'Ignored encrypted xref duplicate'));
        $t->true(!str_contains($plainText, 'Ignored encrypted xref object stream stale'));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(0, $diagnostics['failedStreams']);
        $t->same([], $diagnostics['malformedXrefOffsets']);
        $t->same(0, $diagnostics['malformedXrefStreams']);
        $t->same(0, $diagnostics['malformedObjectStreams']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'could not be decoded'));
        $t->true(!str_contains($warningText, 'Malformed PDF xref'));
        $t->true(!str_contains($warningText, 'object stream'));
    },
    'decrypts Standard R2 object string ActualText before custom glyph suppression' => static function (TestRunner $t) use ($standardR2EncryptedPdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = $standardR2EncryptedPdfWithContent($content, 'Encrypted Tagged Text');
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Encrypted Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'decrypts Standard R4 V2 crypt-filter object string ActualText before custom glyph suppression' => static function (TestRunner $t) use ($standardR4V2EncryptedPdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = $standardR4V2EncryptedPdfWithContent($content, 'Encrypted R4 Tagged Text');
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Encrypted R4 Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-RC4', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'decrypts Standard R4 AESV2 crypt-filter object string ActualText before custom glyph suppression' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = $standardR4AesV2EncryptedPdfWithContent($content, 'Encrypted R4 AES Tagged Text');
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Encrypted R4 AES Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 AES Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-AESV2', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'decrypts Standard R5 AESV3 crypt-filter object string ActualText before custom glyph suppression' => static function (TestRunner $t) use ($standardR5AesV3EncryptedPdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = $standardR5AesV3EncryptedPdfWithContent($content, 'Encrypted R5 AES Tagged Text');
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Encrypted R5 AES Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R5 AES Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R5-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'decrypts Standard R6 AESV3 crypt-filter object string ActualText before custom glyph suppression' => static function (TestRunner $t) use ($standardR6AesV3EncryptedPdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = $standardR6AesV3EncryptedPdfWithContent($content, 'Encrypted R6 AES Tagged Text');
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Encrypted R6 AES Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R6 AES Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R6-empty-user-password-AESV3', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'decrypts Standard R4 mixed Identity streams and V2 object strings before ActualText suppression' => static function (TestRunner $t) use ($standardR4V2EncryptedPdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = $standardR4V2EncryptedPdfWithContent($content, 'Encrypted R4 mixed string text', 'Identity', 'StdCF');
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R4 mixed string text'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 mixed string text'], $extractor->extractTextRuns($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-mixed-crypt-filters', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'decrypts Standard R4 mixed AESV2 streams while preserving Identity object strings' => static function (TestRunner $t) use ($standardR4AesV2EncryptedPdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Encrypted R4 AES mixed stream text) Tj T* (Identity strings stay plain) Tj ET';
        $pdf = $standardR4AesV2EncryptedPdfWithContent($content, null, 'StdCF', 'Identity');
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Encrypted R4 AES mixed stream text', 'Identity strings stay plain'], $extractor->extractTextLines($pdf));
        $t->same(['Encrypted R4 AES mixed stream text', 'Identity strings stay plain'], $extractor->extractTextRuns($pdf));
        $t->same(true, $diagnostics['encrypted']);
        $t->same(true, $diagnostics['encryptionDecrypted']);
        $t->same('Standard-R4-empty-user-password-mixed-crypt-filters', $diagnostics['encryptionHandler']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($warningText, 'does not decrypt'));
    },
    'reports page-level partial extraction diagnostics before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithPartialPageExtractionIssues): void {
        $pdf = $pdfWithPartialPageExtractionIssues();
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Readable Page Text', $plainText);
        $t->true(!str_contains($plainText, 'Unsupported Page Text'));
        $t->same(['DCTDecode'], $diagnostics['unsupportedFilters']);
        $t->same(2, $diagnostics['pagesWithExtractionIssues']);
        $t->same([
            [
                'page' => 2,
                'pageObject' => 6,
                'contentReference' => 7,
                'contentObject' => 7,
                'reason' => 'unsupported_content_filter',
                'filters' => ['DCTDecode'],
            ],
            [
                'page' => 3,
                'pageObject' => 8,
                'contentReference' => 99,
                'contentObject' => null,
                'reason' => 'unresolved_content_reference',
                'resolutionReason' => 'content-reference-missing',
                'filters' => [],
            ],
        ], $diagnostics['pageExtractionIssues']);
        $t->contains('PDF page-level extraction issues: 2 page(s) have unreadable or unresolved content streams.', $warningText);
    },
    'reports invoked Form XObject page-level extraction diagnostics before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithFormXObjectPageExtractionIssues): void {
        $pdf = $pdfWithFormXObjectPageExtractionIssues();
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Visible Page Text', $plainText);
        $t->true(!str_contains($plainText, 'Unsupported Form Text'));
        $t->same(['DCTDecode'], $diagnostics['unsupportedFilters']);
        $t->same(1, $diagnostics['failedStreams']);
        $t->same(1, $diagnostics['pagesWithExtractionIssues']);
        $t->same([
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmBad',
                'xObjectObject' => 6,
                'xObjectSubtype' => 'Form',
                'reason' => 'unsupported_form_xobject_filter',
                'filters' => ['DCTDecode'],
            ],
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmBroken',
                'xObjectObject' => 7,
                'xObjectSubtype' => 'Form',
                'reason' => 'failed_form_xobject_decode',
                'filters' => ['FlateDecode'],
            ],
        ], $diagnostics['pageExtractionIssues']);
        $t->contains('PDF page-level extraction issues: 1 page(s) have unreadable or unresolved content streams.', $warningText);
    },
    'reports unresolved XObject page-level extraction diagnostics before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithUnresolvedXObjectPageExtractionIssues): void {
        $pdf = $pdfWithUnresolvedXObjectPageExtractionIssues();
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Visible XObject Resource Text', $plainText);
        $t->same([], $diagnostics['unsupportedFilters']);
        $t->same(0, $diagnostics['failedStreams']);
        $t->same(1, $diagnostics['pagesWithExtractionIssues']);
        $t->same([
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmMissing',
                'xObjectObject' => 99,
                'xObjectSubtype' => 'Unknown',
                'reason' => 'unresolved_xobject_reference',
                'filters' => [],
            ],
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmAbsent',
                'xObjectObject' => null,
                'xObjectSubtype' => 'Unknown',
                'reason' => 'unresolved_xobject_resource',
                'filters' => [],
            ],
            [
                'page' => 1,
                'pageObject' => 3,
                'contentReference' => 5,
                'contentObject' => 5,
                'xObjectName' => 'FmMalformed',
                'xObjectObject' => 6,
                'xObjectSubtype' => 'Form',
                'reason' => 'unresolved_form_xobject_stream',
                'filters' => [],
            ],
        ], $diagnostics['pageExtractionIssues']);
        $t->contains('PDF page-level extraction issues: 1 page(s) have unreadable or unresolved content streams.', $warningText);
    },
    'decodes ToUnicode CMap font dictionaries before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002000300040005> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "2 beginbfchar\n"
            . "<0001> <0057006F0072006400500072006500730073>\n"
            . "<0002> <2014>\n"
            . "endbfchar\n"
            . "1 beginbfrange\n"
            . "<0003> <0005> <0041>\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /Glyphs /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('WordPress' . "\u{2014}" . 'ABC', $extractor->extractPlainText($pdf));
        $t->same(['WordPress' . "\u{2014}" . 'ABC'], $extractor->extractTextRuns($pdf));
    },
    'decodes predictor encoded ToUnicode CMap streams before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0102> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<01> <0057006F0072006400500072006500730073>\n"
            . "<02> <00200050007200650064006900630074006F0072>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PredictorToUnicodeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $columns = 17;
        $encodedRows = '';
        foreach (str_split($cmap, $columns) as $row) {
            $encodedRows .= "\0" . str_pad($row, $columns, ' ');
        }
        $encodedCmap = gzcompress($encodedRows);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredictorToUnicodeSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns {$columns} >> /Length " . strlen($encodedCmap) . " >>\nstream\n{$encodedCmap}endstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['WordPress Predictor'], $extractor->extractTextLines($pdf));
        $t->same(['WordPress Predictor'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->same(0, $diagnostics['failedStreams']);
    },
    'decodes odd-nibble ToUnicode CMap hex strings before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <105070> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<1> <7>\n"
            . "endcodespacerange\n"
            . "3 beginbfchar\n"
            . "<1> <005>\n"
            . "<5> <00440046>\n"
            . "<7> <0020004F004B>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /OddNibbleToUnicodeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OddNibbleSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'decodes ToUnicode CMap bfrange array targets before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002000300040005> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "1 beginbfrange\n"
            . "<0001> <0004> [ <0057006F00720064> <00500072006500730073> <002000410072007200610079> <002000520061006E00670065> ]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /ArrayRangeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ArrayRangeSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('WordPress Array Range', $plainText);
        $t->same(['WordPress Array Range'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, "\x00\x05"));
    },
    'decodes ToUnicode CMap sequential bfrange multi-codepoint targets before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0001000200030004> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "1 beginbfrange\n"
            . "<0001> <0003> <0057006F007200640031>\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /SequentialWideRangeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SequentialWideRangeSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Word1Word2Word3', $plainText);
        $t->same(['Word1Word2Word3'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, "\x00\x04"));
    },
    'ignores ToUnicode CMap bfrange entries with mismatched source widths before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <018000028001> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "2 begincodespacerange\n"
            . "<00> <7F>\n"
            . "<8000> <FFFF>\n"
            . "endcodespacerange\n"
            . "3 beginbfrange\n"
            . "<01> <8000> <0042>\n"
            . "<02> <8001> [ <0043> ]\n"
            . "<8000> <8001> [ <0057006F0072006400500072006500730073> <00200042006C006F0063006B0073> ]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /MismatchedRangeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MismatchedRangeSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('WordPress Blocks', $plainText);
        $t->same(['WordPress Blocks'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, 'BWordPress'));
        $t->true(!str_contains($plainText, 'C Blocks'));
        $t->true(!str_contains($plainText, "\x01"));
        $t->true(!str_contains($plainText, "\x02"));
    },
    'ignores ToUnicode CMap PostScript comments before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0001000200030004> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "% /WMode 1\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "% <00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<0001> <0057006F00720064>\n"
            . "% <0001> <0042>\n"
            . "<0002> <0050007200650073>\n"
            . "% <0002> <0043>\n"
            . "endbfchar\n"
            . "1 beginbfrange\n"
            . "<0003> <0004> [ <007300200043006F006D006D0065> <006E00740073> ]\n"
            . "% <0003> <0004> [ <0020004200610064> <0020004D00610070> ]\n"
            . "endbfrange\n"
            . "% /CMapName /CommentOverrideCMap def\n"
            . "endcmap\n"
            . "CMapName currentdict /CommentSafeCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentedCMapSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('WordPress Comments', $plainText);
        $t->same(['WordPress Comments'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, 'BC'));
        $t->true(!str_contains($plainText, 'Bad Map'));
    },
    'decodes ToUnicode CMap literal string targets before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002000300040005> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "3 beginbfchar\n"
            . "<0001> (Word)\n"
            . "<0002> (Press)\n"
            . "<0003> (\\376\\377\\000!)\n"
            . "endbfchar\n"
            . "1 beginbfrange\n"
            . "<0004> <0005> [( Literal) ( Target)]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralTargetsCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralTargetsSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('WordPress! Literal Target', $plainText);
        $t->same(['WordPress! Literal Target'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, "\0"));
    },
    'decodes ToUnicode CMap literal source operands before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <41424344> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "(A) (Z)\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "(A) <0057006F00720064>\n"
            . "(B) <0050007200650073>\n"
            . "endbfchar\n"
            . "1 beginbfrange\n"
            . "(C) (D) [ <00730020004C00690074> <006500720061006C> ]\n"
            . "endbfrange\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourcesCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralSourcesSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('WordPress Literal', $plainText);
        $t->same(['WordPress Literal'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, 'ABCD'));
    },
    'decodes Unicode CID CMap encodings without ToUnicode before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002000300040005000600070008> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /Adobe-Identity-UCS def\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "5 begincidchar\n"
            . "<0001> 87\n"
            . "<0002> 111\n"
            . "<0003> 114\n"
            . "<0004> 100\n"
            . "<0005> 32\n"
            . "endcidchar\n"
            . "1 begincidrange\n"
            . "<0006> <0008> 65\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /Adobe-Identity-UCS defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnicodeCID /Encoding 5 0 R /DescendantFonts [7 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /UnicodeCID /CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Word ABC'], $extractor->extractTextLines($pdf));
        $t->same(['Word ABC'], $extractor->extractTextRuns($pdf));
        $t->same('Word ABC', $extractor->extractPlainText($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'decodes Unicode CID CMap literal source operands without ToUnicode before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <41424344> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /LiteralSourceUCS def\n"
            . "1 begincodespacerange\n"
            . "(A) (Z)\n"
            . "endcodespacerange\n"
            . "1 begincidrange\n"
            . "(A) (C) 65\n"
            . "endcidrange\n"
            . "1 begincidchar\n"
            . "(D) 33\n"
            . "endcidchar\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourceUCS defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralSourceUCS /Encoding 5 0 R /DescendantFonts [7 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LiteralSourceUCS /CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['ABC!'], $extractor->extractTextLines($pdf));
        $t->same(['ABC!'], $extractor->extractTextRuns($pdf));
        $t->same('ABC!', $extractor->extractPlainText($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'uses vertical CID writing mode for positioned WordPress paragraph rendering' => static function (TestRunner $t): void {
        $buildPdf = static function (string $encoding, string $wModeLine): string {
            $content = 'BT /Fcid 12 Tf 1 0 0 1 100 700 Tm <0001> Tj 1 0 0 1 100 694 Tm <0002> Tj ET';
            $cmap = "/CIDInit /ProcSet findresource begin\n"
                . "12 dict begin\n"
                . "begincmap\n"
                . $wModeLine
                . "1 begincodespacerange\n"
                . "<0000> <FFFF>\n"
                . "endcodespacerange\n"
                . "2 beginbfchar\n"
                . "<0001> <0056006500720074006900630061006C>\n"
                . "<0002> <00200054006500780074>\n"
                . "endbfchar\n"
                . "endcmap\n"
                . "CMapName currentdict /VerticalCIDMap defineresource pop\n"
                . "end\n"
                . "end\n";

            return "%PDF-1.4\n"
                . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
                . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
                . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalCID /Encoding {$encoding} /DescendantFonts [7 0 R] /ToUnicode 5 0 R >>\nendobj\n"
                . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
                . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
                . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>\nendobj\n%%EOF";
        };
        $extractor = new PdfTextExtractor();

        foreach ([$buildPdf('/Identity-H', "/WMode 1\n"), $buildPdf('/Identity-V', '')] as $pdf) {
            $t->same(['Vertical Text'], $extractor->extractTextLines($pdf));
            $t->same('Vertical Text', implode('', $extractor->extractTextRuns($pdf)));
            $t->same('Vertical Text', $extractor->extractPlainText($pdf));
        }
    },
    'uses descendant CIDFont ToUnicode maps for Type0 font resources' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<0001> <00440065007300630065006E00640061006E00740020004300490044>\n"
            . "<0002> <0020004D00610070>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /DescendantCIDMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CompositeSubset /Encoding /Identity-H /DescendantFonts [5 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CompositeSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /ToUnicode 6 0 R >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['Descendant CID Map'], $extractor->extractTextLines($pdf));
        $t->same(['Descendant CID Map'], $extractor->extractTextRuns($pdf));
        $t->same('Descendant CID Map', $extractor->extractPlainText($pdf));
    },
    'suppresses Identity CID fonts without Unicode maps before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj T* /Fvert 12 Tf 72 700 Td <0003> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R /Fvert 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NoUnicodeH /Encoding /Identity-H /DescendantFonts [7 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NoUnicodeV /Encoding /Identity-V /DescendantFonts [8 0 R] >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NoUnicodeH /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NoUnicodeV /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->extractPlainText($pdf));
        $t->same(['Fcid', 'Fvert'], $diagnostics['missingUnicodeFonts']);
        $t->same(['Fcid' => 'Identity-H', 'Fvert' => 'Identity-V'], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(2, $diagnostics['suppressedGlyphRuns']);
        $t->contains('font lacks a Unicode map', $warningText);
    },
    'suppresses embedded CID CMap fonts without Unicode maps before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CMapName /EmbeddedCIDMap def\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "endcmap\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EmbeddedCID /Encoding 5 0 R /DescendantFonts [7 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /EmbeddedCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->extractPlainText($pdf));
        $t->same(['Fcid'], $diagnostics['missingUnicodeFonts']);
        $t->same(['Fcid' => 'EmbeddedCIDMap'], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(1, $diagnostics['suppressedGlyphRuns']);
        $t->contains('font lacks a Unicode map', $warningText);
    },
    'resolves indirect CMap stream filters before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <00010002> Tj T* <0003> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "3 beginbfchar\n"
            . "<0001> <0057006F0072006400500072006500730073>\n"
            . "<0002> <00200049006D0070006F00720074>\n"
            . "<0003> <0043006C00650061006E00200042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $compressedCmap = gzcompress($cmap);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /Glyphs /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Filter 4 0 R /Length " . strlen($compressedCmap) . " >>\nstream\n{$compressedCmap}\nendstream\nendobj\n"
            . "4 0 obj\n/FlateDecode\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['WordPress Import', 'Clean Blocks'], $extractor->extractTextLines($pdf));
        $t->same(['WordPress Import', 'Clean Blocks'], $extractor->extractTextRuns($pdf));
    },
    'decodes PDF name escapes in font resources before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /F#31 12 Tf 72 720 Td <00010002> Tj T* /F#32 12 Tf <576F72645072657373927320636166E9> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "2 beginbfchar\n"
            . "<0001> <0057006F0072006400500072006500730073>\n"
            . "<0002> <00200049006D0070006F00720074>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /F#31 2 0 R /F#32 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EscapedName /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['WordPress Import', "WordPress\u{2019}s caf\u{00E9}"], $extractor->extractTextLines($pdf));
        $t->same(['WordPress Import', "WordPress\u{2019}s caf\u{00E9}"], $extractor->extractTextRuns($pdf));
    },
    'skips inline image bytes before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT /F1 12 Tf 72 720 Td (Before image) Tj ET\n"
            . "q BI /W 2 /H 1 /CS /RGB /BPC 8 ID\n"
            . "(Fake OCR paragraph) Tj 72 720 Td <576F72645072657373> Tj\n"
            . "EI Q\n"
            . "BT /F1 12 Tf 72 704 Td (After image) Tj ET";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Before image', 'After image'], $extractor->extractTextLines($pdfWithContent($content)));
        $t->same(['Before image', 'After image'], $extractor->extractTextRuns($pdfWithContent($content)));
        $t->true(!str_contains($plainText, 'Fake OCR paragraph'));
        $t->true(!str_contains($plainText, 'WordPress'));
    },
    'keeps partial ToUnicode CMap fallbacks aligned before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0001002000020003> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "2 beginbfchar\n"
            . "<0001> <0057006F0072006400500072006500730073>\n"
            . "<0003> <0042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /Glyphs /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('WordPress Blocks', $plainText);
        $t->same(['WordPress Blocks'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, "\x00"));
    },
    'uses ToUnicode CMap codespacerange widths for variable-length WordPress text' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <8141208142> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "2 begincodespacerange\n"
            . "<20> <20>\n"
            . "<8000> <FFFF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<8141> <0057006F0072006400500072006500730073>\n"
            . "<8142> <0042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VariableSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('WordPress Blocks', $extractor->extractPlainText($pdf));
        $t->same(['WordPress Blocks'], $extractor->extractTextRuns($pdf));
    },
    'inherits named ToUnicode UseCMap mappings before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <010203> Tj ET';
        $baseCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<01> <0057006F0072006400500072006500730073>\n"
            . "<02> <00200049006D0070006F00720074>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /BaseImportCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/BaseImportCMap usecmap\n"
            . "1 beginbfchar\n"
            . "<03> <00200042006C006F0063006B0073>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /DerivedImportCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UseCMapSubset /Encoding /Identity-H /ToUnicode 4 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($baseCMap) . " >>\nstream\n{$baseCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same('WordPress Import Blocks', $extractor->extractPlainText($pdf));
        $t->same(['WordPress Import Blocks'], $extractor->extractTextRuns($pdf));
    },
    'reads page and font dictionaries from compressed object streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithCompressedObjectStream): void {
        $pdf = $pdfWithCompressedObjectStream();
        $extractor = new PdfTextExtractor();

        $t->same('Object Stream Text', $extractor->extractPlainText($pdf));
        $t->same(['Object Stream Text'], $extractor->extractTextRuns($pdf));
    },
    'uses newer object-stream revisions over stale regular objects before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithIncrementalObjectStreamUpdate): void {
        $pdf = $pdfWithIncrementalObjectStreamUpdate();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Incremental Object Stream Text', $plainText);
        $t->same(['Incremental Object Stream Text'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Old Revision Text'));
    },
    'uses catalog page tree instead of stale orphan page objects before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithCatalogReachableAndOrphanPages): void {
        $pdf = $pdfWithCatalogReachableAndOrphanPages();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Catalog Reachable Page', $plainText);
        $t->same(['Catalog Reachable Page'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Stale Orphan Page'));
    },
    'uses referenced object generation instead of stale same-number objects before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithGeneratedContentReference): void {
        $pdf = $pdfWithGeneratedContentReference();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Generation One Content', $plainText);
        $t->same(['Generation One Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Generation Zero Content'));
    },
    'skips xref free content objects before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithXrefFreedContentObject): void {
        $pdf = $pdfWithXrefFreedContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Xref Current Content', $plainText);
        $t->same(['Xref Current Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Freed Stale Content'));
    },
    'uses xref in-use offsets instead of later duplicate objects before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithXrefSelectedDuplicateContentObject): void {
        $pdf = $pdfWithXrefSelectedDuplicateContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Xref Selected Content', $plainText);
        $t->same(['Xref Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Later Duplicate Content'));
    },
    'uses earlier valid startxref when a malformed appended update follows before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithEarlierValidStartxrefAfterMalformedAppend): void {
        $pdf = $pdfWithEarlierValidStartxrefAfterMalformedAppend();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Earlier Valid Xref Content', $plainText);
        $t->same(['Earlier Valid Xref Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Bad Append Duplicate'));
        $t->same([999999], $diagnostics['malformedXrefOffsets']);
    },
    'uses xref stream in-use offsets instead of later duplicate objects before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithXrefStreamSelectedDuplicateContentObject): void {
        $pdf = $pdfWithXrefStreamSelectedDuplicateContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Xref Stream Selected Content', $plainText);
        $t->same(['Xref Stream Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Xref Stream Duplicate'));
    },
    'decodes predictor xref streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithPredictorXrefStreamSelectedDuplicateContentObject): void {
        $pdf = $pdfWithPredictorXrefStreamSelectedDuplicateContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Predictor Xref Stream Selected Content', $plainText);
        $t->same(['Predictor Xref Stream Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Predictor Xref Stream Duplicate'));
        $t->same(0, $diagnostics['malformedXrefStreams']);
    },
    'uses xref stream zero-width type and generation defaults before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithZeroWidthXrefStreamSelectedDuplicateContentObject): void {
        $pdf = $pdfWithZeroWidthXrefStreamSelectedDuplicateContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Zero Width Xref Selected Content', $plainText);
        $t->same(['Zero Width Xref Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Zero Width Xref Duplicate'));
        $t->same(0, $diagnostics['malformedXrefStreams']);
    },
    'uses indirect xref stream W and Index arrays before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithIndirectXrefStreamArraysSelectedDuplicateContentObject): void {
        $pdf = $pdfWithIndirectXrefStreamArraysSelectedDuplicateContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Indirect Xref Arrays Selected Content', $plainText);
        $t->same(['Indirect Xref Arrays Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Indirect Xref Arrays Duplicate'));
        $t->same(0, $diagnostics['malformedXrefStreams']);
    },
    'uses latest indirect xref stream W and Index array objects before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithLatestIndirectXrefStreamArraysSelectedDuplicateContentObject): void {
        $pdf = $pdfWithLatestIndirectXrefStreamArraysSelectedDuplicateContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Latest Indirect Xref Arrays Selected Content', $plainText);
        $t->same(['Latest Indirect Xref Arrays Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Latest Indirect Xref Arrays Duplicate'));
        $t->same(0, $diagnostics['malformedXrefStreams']);
    },
    'uses latest indirect xref stream Size object before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithLatestIndirectXrefStreamSizeSelectedDuplicateContentObject): void {
        $pdf = $pdfWithLatestIndirectXrefStreamSizeSelectedDuplicateContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Latest Indirect Xref Size Selected Content', $plainText);
        $t->same(['Latest Indirect Xref Size Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Latest Indirect Xref Size Duplicate'));
        $t->same(0, $diagnostics['malformedXrefStreams']);
    },
    'follows indirect Prev xref offsets before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithIndirectPrevXrefChainSelectedContentObject): void {
        $pdf = $pdfWithIndirectPrevXrefChainSelectedContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Indirect Prev Chain Content', $plainText);
        $t->same(['Indirect Prev Chain Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Indirect Prev Duplicate'));
        $t->same([], $diagnostics['malformedXrefOffsets']);
    },
    'follows Prev xref offsets after nested trailer dictionaries before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithNestedTrailerPrevXrefChainSelectedContentObject): void {
        $pdf = $pdfWithNestedTrailerPrevXrefChainSelectedContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Nested Trailer Prev Content', $plainText);
        $t->same(['Nested Trailer Prev Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Nested Trailer Duplicate'));
        $t->same([], $diagnostics['malformedXrefOffsets']);
    },
    'follows indirect XRefStm offsets before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithIndirectXRefStmHybridSelectedContentObject): void {
        $pdf = $pdfWithIndirectXRefStmHybridSelectedContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Indirect XRefStm Selected Content', $plainText);
        $t->same(['Indirect XRefStm Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Ignored Indirect XRefStm Duplicate'));
        $t->same(0, $diagnostics['malformedXrefStreams']);
    },
    'uses xref stream type two object stream entries before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithXrefStreamCompressedPageSelection): void {
        $pdf = $pdfWithXrefStreamCompressedPageSelection();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Xref Stream Packed Page', $plainText);
        $t->same(['Xref Stream Packed Page'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Xref Stream Stale Packed Page'));
    },
    'decodes predictor object streams before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithPredictorObjectStreamSelection): void {
        $pdf = $pdfWithPredictorObjectStreamSelection();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Predictor ObjStm Packed Page', $plainText);
        $t->same(['Predictor ObjStm Packed Page'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Predictor ObjStm Stale Page'));
        $t->same(0, $diagnostics['failedStreams']);
        $t->same(0, $diagnostics['malformedObjectStreams']);
    },
    'resolves indirect object stream N and First before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithIndirectObjectStreamHeaderSelection): void {
        $pdf = $pdfWithIndirectObjectStreamHeaderSelection();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same('Indirect ObjStm Header Packed Page', $plainText);
        $t->same(['Indirect ObjStm Header Packed Page'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Indirect ObjStm Header Stale Page'));
        $t->same(0, $diagnostics['malformedObjectStreams']);
    },
    'uses hybrid xref stream offsets over stale table offsets before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithHybridXrefStreamSelectedDuplicateContentObject): void {
        $pdf = $pdfWithHybridXrefStreamSelectedDuplicateContentObject();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Hybrid Xref Stream Selected Content', $plainText);
        $t->same(['Hybrid Xref Stream Selected Content'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Hybrid Table Duplicate Content'));
    },
    'uses hybrid xref stream type two object stream entries before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithHybridXrefStreamCompressedPageSelection): void {
        $pdf = $pdfWithHybridXrefStreamCompressedPageSelection();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Hybrid Xref Stream Packed Page', $plainText);
        $t->same(['Hybrid Xref Stream Packed Page'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Hybrid Xref Stream Stale Packed Page'));
    },
    'follows hybrid xref table and stream previous branches before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithHybridXrefStreamAndTablePreviousBranches): void {
        $pdf = $pdfWithHybridXrefStreamAndTablePreviousBranches();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('Hybrid Prev Branch Text', $plainText);
        $t->same(['Hybrid Prev Branch Text'], $extractor->extractTextRuns($pdf));
    },
    'decodes WinAnsi simple font bytes before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fwin 12 Tf 72 720 Td <576F72645072657373927320636166E9> Tj T* [(\223Media\224) 120 ( Import)] TJ ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fwin 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(["WordPress\u{2019}s caf\u{00E9}", "\u{201C}Media\u{201D} Import"], $extractor->extractTextLines($pdf));
        $t->same(["WordPress\u{2019}s caf\u{00E9}", "\u{201C}Media\u{201D} Import"], $extractor->extractTextRuns($pdf));

        $baseEncodingPdf = str_replace('/Encoding /WinAnsiEncoding', '/Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding >>', $pdf);
        $t->same(["WordPress\u{2019}s caf\u{00E9}", "\u{201C}Media\u{201D} Import"], $extractor->extractTextLines($baseEncodingPdf));
    },
    'decodes simple font Differences arrays before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fdiff 12 Tf 72 720 Td <4142434445464748494A4B4C4D4E4F50> Tj T* [(\101\102\103) 120 <4445464748494A4B>] TJ ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdiff 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Subset /Encoding << /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [ 65 /Euro /space /quotedblleft /M /e /d /i /a /quotedblright /space /emdash /space /c /a /f /eacute ] >> >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(["\u{20AC} \u{201C}Media\u{201D} \u{2014} caf\u{00E9}", "\u{20AC} \u{201C}Media\u{201D} \u{2014}"], $extractor->extractTextLines($pdf));
        $t->same(["\u{20AC} \u{201C}Media\u{201D} \u{2014} caf\u{00E9}", "\u{20AC} \u{201C}Media\u{201D} \u{2014}"], $extractor->extractTextRuns($pdf));
    },
    'decodes indirect simple font encoding objects before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Findirect 12 Tf 72 720 Td <4142434445464748494A> Tj T* <576F72645072657373927320636166E9> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Findirect 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /IndirectSubset /Encoding 3 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [ 65 /W /o /r /d /P /r /e /s /s /space ] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['WordPress', "WordPress\u{2019}s caf\u{00E9}"], $extractor->extractTextLines($pdf));
        $t->same(['WordPress ', "WordPress\u{2019}s caf\u{00E9}"], $extractor->extractTextRuns($pdf));
    },
    'decodes MacRoman simple font bytes before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = "BT /Fmac 12 Tf 72 720 Td <576F72645072657373204D6163526F6D616E3A208E2044617461D120DB> Tj "
            . "T* [<D251756F7465D3> 120 <20496D706F7274>] TJ ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fmac 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /MacRomanEncoding >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(["WordPress MacRoman: é Data— €", "\u{201C}Quote\u{201D} Import"], $extractor->extractTextLines($pdf));
        $t->same(["WordPress MacRoman: é Data— €", "\u{201C}Quote\u{201D} Import"], $extractor->extractTextRuns($pdf));
    },
    'decodes Symbol and ZapfDingbats simple font bytes before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fsym 12 Tf 72 720 Td <41426162B3B9BA> Tj T* /Fzap 12 Tf <3334A8AAAB> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsym 2 0 R /Fzap 4 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Symbol /Encoding /SymbolEncoding >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ZapfDingbats /Encoding /ZapfDingbatsEncoding >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $expected = ["\u{0391}\u{0392}\u{03B1}\u{03B2}\u{2265}\u{2260}\u{2261}", "\u{2713}\u{2714}\u{2663}\u{2665}\u{2660}"];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));

        $implicitPdf = str_replace(' /Encoding /SymbolEncoding', '', str_replace(' /Encoding /ZapfDingbatsEncoding', '', $pdf));
        $t->same($expected, $extractor->extractTextLines($implicitPdf));

        $dingbatsAliasPdf = str_replace('/Encoding /ZapfDingbatsEncoding', '/Encoding /DingbatsEncoding', $pdf);
        $t->same($expected, $extractor->extractTextLines($dingbatsAliasPdf));
    },
    'decodes Symbol and ZapfDingbats glyph names in Differences arrays' => static function (TestRunner $t): void {
        $content = 'BT /Fdiff 12 Tf 72 720 Td <414243444546> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdiff 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding << /Type /Encoding /BaseEncoding /SymbolEncoding /Differences [ 65 /Alpha /a19 /greaterequal /space /a20 /notequal ] >> >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(["\u{0391}\u{2713}\u{2265} \u{2714}\u{2260}"], $extractor->extractTextLines($pdf));
        $t->same(["\u{0391}\u{2713}\u{2265} \u{2714}\u{2260}"], $extractor->extractTextRuns($pdf));
    },
    'decodes Type3 differences-only glyph names before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Ftype3 12 Tf 72 720 Td <4142434445> Tj ET';
        $charProc = '0 0 500 700 re f';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Ftype3 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ftype3 /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 69 /Widths [500 500 500 500 500] /Encoding << /Type /Encoding /Differences [ 65 /H /e /l /l /o ] >> /CharProcs << /H 6 0 R /e 6 0 R /l 6 0 R /o 6 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Hello'], $extractor->extractTextLines($pdf));
        $t->same(['Hello'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'decodes Type3 StandardEncoding glyph names before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Ftype3 12 Tf 72 720 Td <414520AE20AF> Tj ET';
        $charProc = '0 0 0 0 0 0 d1';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Ftype3 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ftype3 /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 32 /LastChar 175 /Widths [500] /Encoding /StandardEncoding /CharProcs << /A 6 0 R /E 6 0 R /space 6 0 R /fi 6 0 R /fl 6 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['AE fi fl'], $extractor->extractTextLines($pdf));
        $t->same(['AE fi fl'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, "\u{00AE}"));
        $t->true(!str_contains($plainText, "\u{00AF}"));
    },
    'decodes Type3 explicit base encodings before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = "BT /Fwin 12 Tf 72 720 Td <576F72645072657373927320636166E9> Tj "
            . "T* /Fmac 12 Tf <576F72645072657373204D6163526F6D616E3A208E2044617461D120DB> Tj "
            . "T* /Fsym 12 Tf <41426162B3B9BA> Tj "
            . "T* /Fzap 12 Tf <3334A8AAAB> Tj ET";
        $charProc = '0 0 0 0 0 0 d1';
        $charProcs = '<< /A 9 0 R /B 9 0 R /W 9 0 R /o 9 0 R /r 9 0 R /d 9 0 R /P 9 0 R /e 9 0 R /s 9 0 R /quoteright 9 0 R /c 9 0 R /a 9 0 R /f 9 0 R /eacute 9 0 R /colon 9 0 R /emdash 9 0 R /Euro 9 0 R /quotedblleft 9 0 R /quotedblright 9 0 R /Alpha 9 0 R /Beta 9 0 R /alpha 9 0 R /beta 9 0 R /greaterequal 9 0 R /notequal 9 0 R /equivalence 9 0 R /a19 9 0 R /a20 9 0 R /club 9 0 R /heart 9 0 R /spade 9 0 R >>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fwin 4 0 R /Fmac 5 0 R /Fsym 6 0 R /Fzap 7 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fwin /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 0 /LastChar 255 /Widths [500] /Encoding /WinAnsiEncoding /CharProcs {$charProcs} >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fmac /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 0 /LastChar 255 /Widths [500] /Encoding << /Type /Encoding /BaseEncoding /MacRomanEncoding >> /CharProcs {$charProcs} >>\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fsym /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 0 /LastChar 255 /Widths [500] /Encoding /SymbolEncoding /CharProcs {$charProcs} >>\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Fzap /FontBBox [0 0 500 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 0 /LastChar 255 /Widths [500] /Encoding /ZapfDingbatsEncoding /CharProcs {$charProcs} >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $expected = [
            "WordPress\u{2019}s caf\u{00E9}",
            "WordPress MacRoman: é Data— €",
            "\u{0391}\u{0392}\u{03B1}\u{03B2}\u{2265}\u{2260}\u{2261}",
            "\u{2713}\u{2714}\u{2663}\u{2665}\u{2660}",
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'decodes embedded Type1 font program glyph-name encodings before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <5051527A205354> Tj ET';
        $fontProgram = "%!PS-AdobeFont-1.0: CustomSubset 1.0\n"
            . "/Encoding 256 array\n"
            . "0 1 255 {1 index exch /.notdef put} for\n"
            . "dup 32 /space put\n"
            . "dup 80 /P put\n"
            . "dup 81 /D put\n"
            . "dup 82 /F put\n"
            . "dup 83 /O put\n"
            . "dup 84 /K put\n"
            . "readonly def\n"
            . "eexec\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+CustomSubset /FontFile 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'z'));
    },
    'decodes embedded TrueType cmap encodings before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat4CMap): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <4869204F4B7A> Tj ET';
        $fontProgram = $trueTypeWithFormat4CMap([0x20, 0x48, 0x69, 0x4F, 0x4B]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+TinyTrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+TinyTrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Hi OK'], $extractor->extractTextLines($pdf));
        $t->same(['Hi OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'z'));
    },
    'decodes embedded TrueType post glyph names before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat4CMapAndPostNames): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <0102030405067A> Tj ET';
        $fontProgram = $trueTypeWithFormat4CMapAndPostNames([
            0x01 => 'P',
            0x02 => 'D',
            0x03 => 'F',
            0x04 => 'space',
            0x05 => 'O',
            0x06 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+PostNamedTrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+PostNamedTrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $plainText = $extractor->extractPlainText($pdf);
        $t->true(!str_contains($plainText, 'z'));
        $t->true(!preg_match('/[\x01-\x06]/', $plainText));
    },
    'decodes embedded TrueType post 1.0 standard glyph names before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat4CMapAndPost10Names): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <0102030405067A> Tj ET';
        $fontProgram = $trueTypeWithFormat4CMapAndPost10Names([
            0x01 => 'P',
            0x02 => 'D',
            0x03 => 'F',
            0x04 => 'space',
            0x05 => 'O',
            0x06 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Post10TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Post10TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $plainText = $extractor->extractPlainText($pdf);
        $t->true(!str_contains($plainText, 'z'));
        $t->true(!preg_match('/[\x01-\x06]/', $plainText));
    },
    'decodes embedded TrueType post 2.5 glyph names before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat4CMapAndPost25Names): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <0102030405067A> Tj ET';
        $fontProgram = $trueTypeWithFormat4CMapAndPost25Names([
            0x01 => 'P',
            0x02 => 'D',
            0x03 => 'F',
            0x04 => 'space',
            0x05 => 'O',
            0x06 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Post25TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Post25TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $plainText = $extractor->extractPlainText($pdf);
        $t->true(!str_contains($plainText, 'z'));
        $t->true(!preg_match('/[\x01-\x06]/', $plainText));
    },
    'decodes embedded TrueType format 0 cmap post glyph names before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat0CMapAndPostNames): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <0102030405067A> Tj ET';
        $fontProgram = $trueTypeWithFormat0CMapAndPostNames([
            0x01 => 'P',
            0x02 => 'D',
            0x03 => 'F',
            0x04 => 'space',
            0x05 => 'O',
            0x06 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format0TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format0TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $plainText = $extractor->extractPlainText($pdf);
        $t->true(!str_contains($plainText, 'z'));
        $t->true(!preg_match('/[\x01-\x06]/', $plainText));
    },
    'decodes embedded TrueType format 6 trimmed cmap post glyph names before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat6CMapAndPostNames): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <3031323334357A> Tj ET';
        $fontProgram = $trueTypeWithFormat6CMapAndPostNames([
            0x30 => 'P',
            0x31 => 'D',
            0x32 => 'F',
            0x33 => 'space',
            0x34 => 'O',
            0x35 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format6TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format6TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $plainText = $extractor->extractPlainText($pdf);
        $t->true(!str_contains($plainText, 'z'));
        $t->true(!str_contains($plainText, '012345'));
    },
    'decodes embedded TrueType format 10 trimmed cmap post glyph names before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat10CMapAndPostNames): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <3031323334357A> Tj ET';
        $fontProgram = $trueTypeWithFormat10CMapAndPostNames([
            0x30 => 'P',
            0x31 => 'D',
            0x32 => 'F',
            0x33 => 'space',
            0x34 => 'O',
            0x35 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format10TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format10TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $plainText = $extractor->extractPlainText($pdf);
        $t->true(!str_contains($plainText, 'z'));
        $t->true(!str_contains($plainText, '012345'));
    },
    'decodes embedded TrueType format 8 mixed cmap post glyph names before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat8CMapAndPostNames): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <3031323334357A> Tj ET';
        $fontProgram = $trueTypeWithFormat8CMapAndPostNames([
            0x30 => 'P',
            0x31 => 'D',
            0x32 => 'F',
            0x33 => 'space',
            0x34 => 'O',
            0x35 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format8TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format8TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $plainText = $extractor->extractPlainText($pdf);
        $t->true(!str_contains($plainText, 'z'));
        $t->true(!str_contains($plainText, '012345'));
    },
    'decodes embedded TrueType format 13 many to one cmap post glyph names before WordPress paragraph rendering' => static function (TestRunner $t) use ($trueTypeWithFormat13CMapAndPostNames): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <3031323334357A> Tj ET';
        $fontProgram = $trueTypeWithFormat13CMapAndPostNames([
            0x30 => 'P',
            0x31 => 'D',
            0x32 => 'F',
            0x33 => 'space',
            0x34 => 'O',
            0x35 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Format13TrueType /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+Format13TrueType /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $plainText = $extractor->extractPlainText($pdf);
        $t->true(!str_contains($plainText, 'z'));
        $t->true(!str_contains($plainText, '012345'));
    },
    'decodes embedded CFF Type1C encodings before WordPress paragraph rendering' => static function (TestRunner $t) use ($cffType1CWithFormat0Encoding): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <5051527A205354> Tj ET';
        $fontProgram = $cffType1CWithFormat0Encoding([
            0x50 => 'P',
            0x51 => 'D',
            0x52 => 'F',
            0x20 => 'space',
            0x53 => 'O',
            0x54 => 'K',
        ]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TinyCFF /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+TinyCFF /FontFile3 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Subtype /Type1C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'z'));
    },
    'decodes embedded CFF Type1C predefined encodings before WordPress paragraph rendering' => static function (TestRunner $t) use ($cffType1CWithDefaultStandardEncoding): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <504446204F4B7A> Tj ET';
        $fontProgram = $cffType1CWithDefaultStandardEncoding(50);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+DefaultCFF /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+DefaultCFF /FontFile3 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Subtype /Type1C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'z'));
    },
    'decodes embedded CFF Type1C Expert encodings before WordPress paragraph rendering' => static function (TestRunner $t) use ($cffType1CWithPredefinedCharsetAndEncoding): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <C9CACB2FBD80> Tj ET';
        $fontProgram = $cffType1CWithPredefinedCharsetAndEncoding(1, 1, 180);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ExpertCFF /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+ExpertCFF /FontFile3 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Subtype /Type1C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(["\u{00B9}\u{00B2}\u{00B3}\u{2044}\u{00BD}"], $extractor->extractTextLines($pdf));
        $t->same(["\u{00B9}\u{00B2}\u{00B3}\u{2044}\u{00BD}"], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($extractor->extractPlainText($pdf), "\x80"));
    },
    'decodes embedded CFF Type1C ExpertSubset charsets before WordPress paragraph rendering' => static function (TestRunner $t) use ($cffType1CWithPredefinedCharsetAndEncoding): void {
        $content = 'BT /Fsubset 12 Tf 72 720 Td <C9CACB7A> Tj ET';
        $fontProgram = $cffType1CWithPredefinedCharsetAndEncoding(2, 1, 100);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fsubset 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ExpertSubsetCFF /FontDescriptor 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+ExpertSubsetCFF /FontFile3 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Subtype /Type1C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(["\u{00B9}\u{00B2}\u{00B3}"], $extractor->extractTextLines($pdf));
        $t->same(["\u{00B9}\u{00B2}\u{00B3}"], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'z'));
    },
    'decodes embedded CID-keyed CFF Type0C UCS charsets before WordPress paragraph rendering' => static function (TestRunner $t) use ($cffCidKeyedType0CWithFormat0Charset): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <0050004400460020004F004B007A> Tj ET';
        $fontProgram = $cffCidKeyedType0CWithFormat0Charset([0x50, 0x44, 0x46, 0x20, 0x4F, 0x4B]);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ABCDEF+CidCFF /Encoding /Identity-H /DescendantFonts [6 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType0 /BaseFont /ABCDEF+CidCFF /CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> /FontDescriptor 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+CidCFF /FontFile3 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Subtype /CIDFontType0C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['PDF OK'], $extractor->extractTextLines($pdf));
        $t->same(['PDF OK'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'z'));
    },
    'suppresses embedded CID-keyed CFF Type0C charsets without Unicode ordering' => static function (TestRunner $t) use ($cffCidKeyedType0CWithFormat0Charset): void {
        $content = 'BT /Fcid 12 Tf 72 720 Td <005000440046> Tj ET';
        $fontProgram = $cffCidKeyedType0CWithFormat0Charset([0x50, 0x44, 0x46], 'Identity');
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ABCDEF+IdentityCidCFF /Encoding /Identity-H /DescendantFonts [6 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType0 /BaseFont /ABCDEF+IdentityCidCFF /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /FontDescriptor /FontName /ABCDEF+IdentityCidCFF /FontFile3 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Subtype /CIDFontType0C /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same(['Fcid'], $diagnostics['missingUnicodeFonts']);
        $t->same(['Fcid' => 'Identity-H'], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(1, $diagnostics['suppressedGlyphRuns']);
    },
    'groups adjacent text operators on the same PDF text line' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Heading) Tj T* (First ) Tj (paragraph) Tj 0 -16 Td (Second line) Tj ET';
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $t->same(['Heading', 'First paragraph', 'Second line'], $lines);
    },
    'uses text advance before same-line Tm gap decisions for WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Data) Tj 1 0 0 1 98 720 Tm (base) Tj 1 0 0 1 146 720 Tm (Import) Tj 1 0 0 1 186 720 Tm (er) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));

        $t->same(['Database Importer'], $lines);
        $t->true(!str_contains($extractor->extractPlainText($pdfWithContent($content)), 'Data base'));
    },
    'preserves decoded word spaces across positioned text fragments' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 10 Tf '
            . '1 0 0 1 72 720 Tm (Client ) Tj '
            . '1 0 0 1 111 720 Tm (number: ) Tj '
            . '1 0 0 1 156 720 Tm (CASE-104) Tj '
            . '1 0 0 1 72 704 Tm (Payment ) Tj '
            . '1 0 0 1 112 704 Tm (terms, ) Tj '
            . '1 0 0 1 150 704 Tm (with ) Tj '
            . '1 0 0 1 176 704 Tm (reference ) Tj '
            . '1 0 0 1 235 704 Tm (section. ) Tj '
            . '1 0 0 1 281 704 Tm (1 ) Tj '
            . '1 0 0 1 301 704 Tm (total:) Tj '
            . 'ET';
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Client number: CASE-104', 'Payment terms, with reference section. 1 total:'], $extractor->extractTextLines($pdfWithContent($content)));
        $t->true(!str_contains($plainText, 'Clientnumber'));
        $t->true(!str_contains($plainText, 'Paymentterms'));
        $t->true(!str_contains($plainText, 'referencesection'));
    },
    'uses PDF text-state spacing for same-line Tm gap decisions before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 2 Tc 120 Tz 1 0 0 1 72 720 Tm (Data) Tj 1 0 0 1 112 720 Tm (base) Tj ET '
            . 'BT /F1 12 Tf 100 Tz 16 TL 72 720 Td (Intro) Tj 18 2 (Import Profile) " 1 0 0 1 182 704 Tm (s) Tj ET '
            . 'BT /F1 12 Tf 18 Tw 1 0 0 1 72 688 Tm (Media Import) Tj 1 0 0 1 170 688 Tm (er) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Database', 'Intro', 'Import Profiles', 'Media Importer'], $lines);
        $t->true(!str_contains($plainText, 'Data base'));
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(!str_contains($plainText, 'Import er'));
    },
    'uses simple font Widths before same-line Tm gap decisions for WordPress paragraph rendering' => static function (TestRunner $t): void {
        $widths = array_fill(0, 91, 500);
        foreach ([87, 100, 101, 105] as $code) {
            $widths[$code - 32] = 1000;
        }
        $widthArray = implode(' ', $widths);
        $content = 'BT /Fwide 12 Tf 1 0 0 1 72 720 Tm (Wide) Tj 1 0 0 1 108 720 Tm (Tail) Tj ET '
            . 'BT /Fwide 12 Tf 1 0 0 1 72 704 Tm [(Wide)] TJ 1 0 0 1 108 704 Tm (Tail) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fwide 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 122 /Widths [{$widthArray}] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'uses rendered endpoints for same-line Tm and Td word boundaries without spelling heuristics' => static function (TestRunner $t): void {
        // Every glyph, including a semantic space, advances 12pt. The first
        // two pairs touch after a Tm reset plus Td move; the third has one
        // actual rendered space; the fourth carries its literal space.
        $widthArray = implode(' ', array_fill(0, 91, 1000));
        $content = 'BT /Fedge 12 Tf 1 0 0 1 72 720 Tm (Yo) Tj 1 0 0 1 50 720 Tm 46 0 Td (ur) Tj ET '
            . 'BT /Fedge 12 Tf 1 0 0 1 72 704 Tm (w) Tj 1 0 0 1 50 704 Tm 34 0 Td (ith) Tj ET '
            . 'BT /Fedge 12 Tf 1 0 0 1 72 688 Tm (go) Tj 1 0 0 1 50 688 Tm 58 0 Td (to) Tj ET '
            . 'BT /Fedge 12 Tf 1 0 0 1 72 672 Tm (go ) Tj 1 0 0 1 50 672 Tm 58 0 Td (to) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fedge 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding 6 0 R /FirstChar 32 /LastChar 122 /Widths 7 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Encoding /BaseEncoding /WinAnsiEncoding >>\nendobj\n"
            . "7 0 obj\n[{$widthArray}]\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $runs = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['Your', 'with', 'go to', 'go to'], $extractor->extractTextLines($pdf));
        $t->same(['Yo', 'ur', 'w', 'ith', 'go', 'to', 'go ', 'to'], array_map(
            static fn (array $run): string => $run['text'],
            $runs
        ));
        $t->same(false, $runs[1]['wordBoundaryBefore']);
        $t->same('text-position-continuation', $runs[1]['wordBoundarySource']);
        $t->same(false, $runs[3]['wordBoundaryBefore']);
        $t->same(true, $runs[5]['wordBoundaryBefore']);
        $t->same('text-position-layout', $runs[5]['wordBoundarySource']);
    },
    'uses general endpoint geometry for ordinary word boundaries without embedded font widths' => static function (TestRunner $t): void {
        // These ordinary fragments have deliberately similar spellings. The
        // final rendered positions, rather than their letters or lengths,
        // decide whether each boundary is compact or spaced.
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Yo) Tj 1 0 0 1 85 720 Tm (ur) Tj ET '
            . 'BT /F1 12 Tf 1 0 0 1 72 704 Tm (we) Tj 1 0 0 1 93 704 Tm (are) Tj ET '
            . 'BT /F1 12 Tf 1 0 0 1 72 688 Tm (a) Tj 1 0 0 1 84 688 Tm (way) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $runs = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['Your', 'we are', 'a way'], $extractor->extractTextLines($pdf));
        $t->same(false, $runs[1]['wordBoundaryBefore']);
        $t->same(true, $runs[3]['wordBoundaryBefore']);
        $t->same(true, $runs[5]['wordBoundaryBefore']);
    },
    'falls back conservatively when a partial Widths table misses a painted glyph' => static function (TestRunner $t): void {
        // The partial /Widths array measures ASCII through "f", but not the
        // WinAnsi e-acute in each first fragment. A non-empty width table
        // must not make the extractor treat its generic fallback advance as
        // a precise endpoint measurement.
        $widthArray = implode(' ', array_fill(0, 71, 500));
        $content = 'BT /Fpartial 12 Tf 1 0 0 1 72 720 Tm (Caf\\351) Tj 1 0 0 1 100 720 Tm (tail) Tj ET '
            . 'BT /Fpartial 12 Tf 1 0 0 1 72 704 Tm (Caf\\351) Tj 1 0 0 1 115 704 Tm (tail) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fpartial 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 102 /Widths [{$widthArray}] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $runs = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['Cafétail', 'Café tail'], $extractor->extractTextLines($pdf));
        $t->same(false, $runs[1]['wordBoundaryBefore']);
        $t->same(true, $runs[3]['wordBoundaryBefore']);
    },
    'keeps artifact geometry out of visible positioned word boundaries' => static function (TestRunner $t): void {
        $widthArray = implode(' ', array_fill(0, 91, 1000));
        $content = 'BT /Fedge 12 Tf 1 0 0 1 72 720 Tm (Left) Tj '
            . '/Artifact BMC 1 0 0 1 300 720 Tm (Header) Tj EMC '
            . '1 0 0 1 132 720 Tm (Right) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fedge 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding 6 0 R /FirstChar 32 /LastChar 122 /Widths 7 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Encoding /BaseEncoding /WinAnsiEncoding >>\nendobj\n"
            . "7 0 obj\n[{$widthArray}]\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $runs = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['Left Right'], $extractor->extractTextLines($pdf));
        $t->same(['Left', 'Right'], array_map(static fn (array $run): string => $run['text'], $runs));
        $t->same(true, $runs[1]['wordBoundaryBefore']);
        $t->same('text-position-layout', $runs[1]['wordBoundarySource']);
    },
    'uses CID W widths before same-line Tm gap decisions for WordPress paragraph rendering' => static function (TestRunner $t): void {
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
            . "/CMapName /CidWidthsMap def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<0001> <0057>\n"
            . "<0002> <0069>\n"
            . "<0003> <0064>\n"
            . "<0004> <0065>\n"
            . "<0005> <0054>\n"
            . "<0006> <0061>\n"
            . "<0007> <0069>\n"
            . "<0008> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CidWidthsMap defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
        $content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 108 720 Tm <0005000600070008> Tj ET '
            . 'BT /FcidIndirect 12 Tf 1 0 0 1 72 704 Tm [<0001000200030004>] TJ 1 0 0 1 108 704 Tm <0005000600070008> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R /FcidIndirect 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CidWidths /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CidWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CidWidthsIndirect /Encoding /Identity-H /DescendantFonts [9 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CidWidthsIndirect /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W 10 0 R >>\nendobj\n"
            . "10 0 obj\n{$cidWidths}\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'uses non-Identity CID CMap source mappings before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
            . "/CMapName /NonIdentityWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<0101> <0057>\n"
            . "<0102> <0069>\n"
            . "<0103> <0064>\n"
            . "<0104> <0065>\n"
            . "<0105> <0054>\n"
            . "<0106> <0061>\n"
            . "<0107> <0069>\n"
            . "<0108> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /NonIdentityWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /NonIdentityRangeWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "1 begincidrange\n"
            . "<0101> <0108> 1\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /NonIdentityRangeWidths defineresource pop\n"
            . "end\n"
            . "end";
        $charEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /NonIdentityCharWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "8 begincidchar\n"
            . "<0101> 1\n"
            . "<0102> 2\n"
            . "<0103> 3\n"
            . "<0104> 4\n"
            . "<0105> 5\n"
            . "<0106> 6\n"
            . "<0107> 7\n"
            . "<0108> 8\n"
            . "endcidchar\n"
            . "endcmap\n"
            . "CMapName currentdict /NonIdentityCharWidths defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
        $content = 'BT /Frange 12 Tf 1 0 0 1 72 720 Tm <0101010201030104> Tj 1 0 0 1 108 720 Tm <0105010601070108> Tj ET '
            . 'BT /Fchar 12 Tf 1 0 0 1 72 704 Tm [<0101010201030104>] TJ 1 0 0 1 108 704 Tm <0105010601070108> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Frange 4 0 R /Fchar 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NonIdentityRangeWidths /Encoding 11 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NonIdentityWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NonIdentityCharWidths /Encoding 12 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($charEncodingCMap) . " >>\nstream\n{$charEncodingCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'does not treat unresolved non-Identity source bytes as CID width indexes' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /UnresolvedNonIdentityToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<0001> <0003>\n"
            . "endcodespacerange\n"
            . "3 beginbfchar\n"
            . "<0001> <0041>\n"
            . "<0002> <0042>\n"
            . "<0003> <0020>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /UnresolvedNonIdentityToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        // CID 1 is narrow, while CID 3 is a full space. Without a parsed
        // non-Identity encoding map, raw source 0001 must not be assumed to
        // identify CID 1 just because both happen to be integers.
        $content = 'BT /Funresolved 12 Tf 1 0 0 1 72 720 Tm <0001> Tj 1 0 0 1 82 720 Tm <0002> Tj ET '
            . 'BT /Funresolved 12 Tf 1 0 0 1 72 704 Tm <0001> Tj 1 0 0 1 96 704 Tm <0002> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Funresolved 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnresolvedNonIdentity /Encoding /Missing-CMap-H /DescendantFonts [6 0 R] /ToUnicode 7 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /UnresolvedNonIdentity /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W [1 [250 500 1000]] >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $runs = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['AB', 'A B'], $extractor->extractTextLines($pdf));
        $t->same(false, $runs[1]['wordBoundaryBefore']);
        $t->same(true, $runs[3]['wordBoundaryBefore']);
    },
    'uses explicit positive CMap CID operands before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
            . "/CMapName /PositiveCidOperandsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<0101> <0057>\n"
            . "<0102> <0069>\n"
            . "<0103> <0064>\n"
            . "<0104> <0065>\n"
            . "<0105> <0054>\n"
            . "<0106> <0061>\n"
            . "<0107> <0069>\n"
            . "<0108> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PositiveCidOperandsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /PositiveCidRangeWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "1 begincidrange\n"
            . "<0101> <0108> +1\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /PositiveCidRangeWidths defineresource pop\n"
            . "end\n"
            . "end";
        $charEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /PositiveCidCharWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<0100> <01FF>\n"
            . "endcodespacerange\n"
            . "8 begincidchar\n"
            . "<0101> +1\n"
            . "<0102> +2\n"
            . "<0103> +3\n"
            . "<0104> +4\n"
            . "<0105> +5\n"
            . "<0106> +6\n"
            . "<0107> +7\n"
            . "<0108> +8\n"
            . "endcidchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PositiveCidCharWidths defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
        $content = 'BT /Frange 12 Tf 1 0 0 1 72 720 Tm <0101010201030104> Tj 1 0 0 1 108 720 Tm <0105010601070108> Tj ET '
            . 'BT /Fchar 12 Tf 1 0 0 1 72 704 Tm [<0101010201030104>] TJ 1 0 0 1 108 704 Tm <0105010601070108> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Frange 4 0 R /Fchar 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PositiveCidRangeWidths /Encoding 11 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PositiveCidWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PositiveCidCharWidths /Encoding 12 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($charEncodingCMap) . " >>\nstream\n{$charEncodingCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'uses CID CMap literal source mappings before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
            . "/CMapName /LiteralSourceWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<41> <48>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<41> <0057>\n"
            . "<42> <0069>\n"
            . "<43> <0064>\n"
            . "<44> <0065>\n"
            . "<45> <0054>\n"
            . "<46> <0061>\n"
            . "<47> <0069>\n"
            . "<48> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourceWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /LiteralSourceRangeWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "(A) (H)\n"
            . "endcodespacerange\n"
            . "1 begincidrange\n"
            . "(A) (H) 1\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourceRangeWidths defineresource pop\n"
            . "end\n"
            . "end";
        $charEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /LiteralSourceCharWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "(A) (H)\n"
            . "endcodespacerange\n"
            . "8 begincidchar\n"
            . "(A) 1\n"
            . "(B) 2\n"
            . "(C) 3\n"
            . "(D) 4\n"
            . "(E) 5\n"
            . "(F) 6\n"
            . "(G) 7\n"
            . "(H) 8\n"
            . "endcidchar\n"
            . "endcmap\n"
            . "CMapName currentdict /LiteralSourceCharWidths defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
        $content = 'BT /Frange 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj 1 0 0 1 108 720 Tm <45464748> Tj ET '
            . 'BT /Fchar 12 Tf 1 0 0 1 72 704 Tm [<41424344>] TJ 1 0 0 1 108 704 Tm <45464748> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Frange 4 0 R /Fchar 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralSourceRangeWidths /Encoding 11 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LiteralSourceWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralSourceCharWidths /Encoding 12 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($charEncodingCMap) . " >>\nstream\n{$charEncodingCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'uses CID CMap notdef mappings before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
            . "/CMapName /NotdefWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<01> <08>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<01> <0045>\n"
            . "<02> <0064>\n"
            . "<03> <0067>\n"
            . "<04> <0065>\n"
            . "<05> <0043>\n"
            . "<06> <0061>\n"
            . "<07> <0073>\n"
            . "<08> <0065>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /NotdefWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /NotdefRangeWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<01> <08>\n"
            . "endcodespacerange\n"
            . "1 beginnotdefrange\n"
            . "<01> <04> 30\n"
            . "endnotdefrange\n"
            . "endcmap\n"
            . "CMapName currentdict /NotdefRangeWidths defineresource pop\n"
            . "end\n"
            . "end";
        $charEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /NotdefCharWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<01> <08>\n"
            . "endcodespacerange\n"
            . "4 beginnotdefchar\n"
            . "<01> 30\n"
            . "<02> 30\n"
            . "<03> 30\n"
            . "<04> 30\n"
            . "endnotdefchar\n"
            . "endcmap\n"
            . "CMapName currentdict /NotdefCharWidths defineresource pop\n"
            . "end\n"
            . "end";
        // This CMap has advances but intentionally no encoded U+0020. Small
        // rendered gaps remain continuations, large rendered gaps remain
        // words, and a Tm/Td reset is resolved from its final endpoint.
        $content = 'BT /FnotdefRange 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj 1 0 0 1 126 720 Tm <05060708> Tj ET '
            . 'BT /FnotdefRange 12 Tf 1 0 0 1 72 704 Tm <01020304> Tj 1 0 0 1 180 704 Tm <05060708> Tj ET '
            . 'BT /FnotdefRange 12 Tf 1 0 0 1 72 688 Tm <01020304> Tj 1 0 0 1 50 688 Tm 70 0 Td <05060708> Tj ET '
            . 'BT /FnotdefChar 12 Tf 1 0 0 1 72 672 Tm [<01020304>] TJ 1 0 0 1 126 672 Tm <05060708> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /FnotdefRange 4 0 R /FnotdefChar 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NotdefRangeWidths /Encoding 11 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotdefWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W [30 30 1000] >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NotdefCharWidths /Encoding 12 0 R /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($charEncodingCMap) . " >>\nstream\n{$charEncodingCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['EdgeCase', 'Edge Case', 'EdgeCase', 'EdgeCase'], $extractor->extractTextLines($pdf));
        $t->true(str_contains($plainText, 'Edge Case'));
        $t->same(false, $runs[1]['wordBoundaryBefore']);
        $t->same(true, $runs[3]['wordBoundaryBefore']);
        $t->same('text-position-layout', $runs[3]['wordBoundarySource']);
        $t->same(false, $runs[5]['wordBoundaryBefore']);
    },
    'uses named CID CMap resources before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n"
            . "/CMapName /NamedWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<0200> <03FF>\n"
            . "endcodespacerange\n"
            . "16 beginbfchar\n"
            . "<0201> <0057>\n"
            . "<0202> <0069>\n"
            . "<0203> <0064>\n"
            . "<0204> <0065>\n"
            . "<0205> <0054>\n"
            . "<0206> <0061>\n"
            . "<0207> <0069>\n"
            . "<0208> <006C>\n"
            . "<0301> <0057>\n"
            . "<0302> <0069>\n"
            . "<0303> <0064>\n"
            . "<0304> <0065>\n"
            . "<0305> <0054>\n"
            . "<0306> <0061>\n"
            . "<0307> <0069>\n"
            . "<0308> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /NamedWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $rangeEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /NamedRangeWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<0200> <02FF>\n"
            . "endcodespacerange\n"
            . "1 begincidrange\n"
            . "<0201> <0208> 1\n"
            . "endcidrange\n"
            . "endcmap\n"
            . "CMapName currentdict /NamedRangeWidths defineresource pop\n"
            . "end\n"
            . "end";
        $baseEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /NamedBaseWidths def\n"
            . "/CMapType 1 def\n"
            . "1 begincodespacerange\n"
            . "<0300> <03FF>\n"
            . "endcodespacerange\n"
            . "8 begincidchar\n"
            . "<0301> 1\n"
            . "<0302> 2\n"
            . "<0303> 3\n"
            . "<0304> 4\n"
            . "<0305> 5\n"
            . "<0306> 6\n"
            . "<0307> 7\n"
            . "<0308> 8\n"
            . "endcidchar\n"
            . "endcmap\n"
            . "CMapName currentdict /NamedBaseWidths defineresource pop\n"
            . "end\n"
            . "end";
        $derivedEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> def\n"
            . "/CMapName /NamedDerivedWidths def\n"
            . "/CMapType 1 def\n"
            . "/NamedBaseWidths usecmap\n"
            . "endcmap\n"
            . "CMapName currentdict /NamedDerivedWidths defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[1 [1000 1000 1000 1000] 5 8 500]';
        $content = 'BT /Fnamed 12 Tf 1 0 0 1 72 720 Tm <0201020202030204> Tj 1 0 0 1 108 720 Tm <0205020602070208> Tj ET '
            . 'BT /Fderived 12 Tf 1 0 0 1 72 704 Tm [<0301030203030304>] TJ 1 0 0 1 108 704 Tm <0305030603070308> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fnamed 4 0 R /Fderived 8 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NamedRangeWidths /Encoding /NamedRangeWidths /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NamedWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Custom) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NamedDerivedWidths /Encoding /NamedDerivedWidths /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Length " . strlen($rangeEncodingCMap) . " >>\nstream\n{$rangeEncodingCMap}\nendstream\nendobj\n"
            . "12 0 obj\n<< /Length " . strlen($baseEncodingCMap) . " >>\nstream\n{$baseEncodingCMap}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Length " . strlen($derivedEncodingCMap) . " >>\nstream\n{$derivedEncodingCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'uses bundled predefined CID CMaps before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (GB1) /Supplement 0 >> def\n"
            . "/CMapName /PredefinedGbWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<00> <80>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<21> <0057>\n"
            . "<22> <0069>\n"
            . "<23> <0064>\n"
            . "<24> <0065>\n"
            . "<25> <0054>\n"
            . "<26> <0061>\n"
            . "<27> <0069>\n"
            . "<28> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PredefinedGbWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[814 [1000 1000 1000 1000] 818 821 500]';
        $content = 'BT /Fgb 12 Tf 1 0 0 1 72 720 Tm <21222324> Tj 1 0 0 1 108 720 Tm <25262728> Tj ET '
            . 'BT /Fgb 12 Tf 1 0 0 1 72 704 Tm [<21222324>] TJ 1 0 0 1 108 704 Tm <25262728> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fgb 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedGbWidths /Encoding /GB-EUC-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedGbWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (GB1) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'uses bundled predefined CNS CID CMaps before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> def\n"
            . "/CMapName /PredefinedCnsWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<A140> <A1FE>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<A140> <0057>\n"
            . "<A141> <0069>\n"
            . "<A142> <0064>\n"
            . "<A143> <0065>\n"
            . "<A144> <0054>\n"
            . "<A145> <0061>\n"
            . "<A146> <0069>\n"
            . "<A147> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PredefinedCnsWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[99 [1000 1000 1000 1000] 103 106 500]';
        $content = 'BT /Fcns 12 Tf 1 0 0 1 72 720 Tm <a140a141a142a143> Tj 1 0 0 1 112 720 Tm <a144a145a146a147> Tj ET '
            . 'BT /Fcns 12 Tf 1 0 0 1 72 704 Tm [<a140a141a142a143>] TJ 1 0 0 1 112 704 Tm <a144a145a146a147> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcns 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedCnsWidths /Encoding /B5pc-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedCnsWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'uses bundled predefined CID UseCMap inheritance before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> def\n"
            . "/CMapName /PredefinedInheritedCnsWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<A140> <A1FE>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<A140> <0057>\n"
            . "<A141> <0069>\n"
            . "<A142> <0064>\n"
            . "<A143> <0065>\n"
            . "<A144> <0054>\n"
            . "<A145> <0061>\n"
            . "<A146> <0069>\n"
            . "<A147> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PredefinedInheritedCnsWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[99 [1000 1000 1000 1000] 103 106 500]';
        $content = 'BT /Feten 12 Tf 1 0 0 1 72 720 Tm <a140a141a142a143> Tj 1 0 0 1 112 720 Tm <a144a145a146a147> Tj ET '
            . 'BT /Feten 12 Tf 1 0 0 1 72 704 Tm [<a140a141a142a143>] TJ 1 0 0 1 112 704 Tm <a144a145a146a147> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Feten 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedInheritedCnsWidths /Encoding /ETenms-B5-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedInheritedCnsWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (CNS1) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'uses complete bundled predefined CID CMap collection before W width gap decisions' => static function (TestRunner $t): void {
        $toUnicodeCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 0 >> def\n"
            . "/CMapName /PredefinedJapanWidthsToUnicode def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<00> <80>\n"
            . "endcodespacerange\n"
            . "8 beginbfchar\n"
            . "<20> <0057>\n"
            . "<21> <0069>\n"
            . "<22> <0064>\n"
            . "<23> <0065>\n"
            . "<24> <0054>\n"
            . "<25> <0061>\n"
            . "<26> <0069>\n"
            . "<27> <006C>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PredefinedJapanWidthsToUnicode defineresource pop\n"
            . "end\n"
            . "end";
        $cidWidths = '[231 [1000 1000 1000 1000] 235 238 500]';
        $content = 'BT /Frksj 12 Tf 1 0 0 1 72 720 Tm <20212223> Tj 1 0 0 1 108 720 Tm <24252627> Tj ET '
            . 'BT /Frksj 12 Tf 1 0 0 1 72 704 Tm [<20212223>] TJ 1 0 0 1 108 704 Tm <24252627> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Frksj 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedJapanWidths /Encoding /RKSJ-H /DescendantFonts [5 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedJapanWidths /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 0 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicodeCMap) . " >>\nstream\n{$toUnicodeCMap}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
    },
    'decodes predefined Unicode source CID CMaps without ToUnicode before W width gap decisions' => static function (TestRunner $t): void {
        $cidWidths = '[56 [1000] 69 [1000 1000] 74 [1000]]';
        $content = 'BT /Funi 12 Tf 1 0 0 1 72 720 Tm <0057006900640065> Tj 1 0 0 1 108 720 Tm <005400610069006c> Tj ET '
            . 'BT /Funi 12 Tf 1 0 0 1 72 704 Tm [<0057006900640065>] TJ 1 0 0 1 108 704 Tm <005400610069006c> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Funi 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUnicodeSource /Encoding /UniJIS-UCS2-H /DescendantFonts [5 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUnicodeSource /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 4 >> /DW 500 /W {$cidWidths} >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['WideTail', 'WideTail'], $extractor->extractTextLines($pdf));
        $t->same(['Wide', 'Tail', 'Wide', 'Tail'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'Wide Tail'));
        $t->true(!str_contains($plainText, '8JEF'));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'decodes predefined UTF8 source CID CMap fallback bytes without explicit CID entries' => static function (TestRunner $t): void {
        $content = 'BT /Futf8 12 Tf 72 720 Td <f09f988057696465> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Futf8 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUnicodeUtf8Source /Encoding /UniJIS-UTF8-H /DescendantFonts [5 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUnicodeUtf8Source /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 7 >> /DW 1000 >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $expected = html_entity_decode('&#x1F600;', ENT_QUOTES, 'UTF-8') . 'Wide';
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same([$expected], $extractor->extractTextLines($pdf));
        $t->same($expected, $plainText);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'inherits predefined UTF8 source CID CMap fallback through UseCMap without ToUnicode' => static function (TestRunner $t): void {
        $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 7 >> def\n"
            . "/CMapName /CustomDerivedSource def\n"
            . "/CMapType 1 def\n"
            . "/UniJIS-UTF8-H usecmap\n"
            . "endcmap\n"
            . "CMapName currentdict /CustomDerivedSource defineresource pop\n"
            . "end\n"
            . "end";
        $content = 'BT /Fderived 12 Tf 72 720 Td <f09f988057696465> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fderived 4 0 R >> >> /Contents 7 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DerivedUnicodeUtf8Source /Encoding 5 0 R /DescendantFonts [6 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DerivedUnicodeUtf8Source /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 7 >> /DW 1000 >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $expected = html_entity_decode('&#x1F600;', ENT_QUOTES, 'UTF-8') . 'Wide';
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same([$expected], $extractor->extractTextLines($pdf));
        $t->same($expected, $plainText);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'uses vertical predefined UTF8 source CID CMap inheritance without ToUnicode' => static function (TestRunner $t): void {
        $content = 'BT /Futf8v 12 Tf 1 0 0 1 100 700 Tm <566572746963616c> Tj 1 0 0 1 100 604 Tm <2054657874> Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Futf8v 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PredefinedUnicodeUtf8Vertical /Encoding /UniJIS-UTF8-V /DescendantFonts [5 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /PredefinedUnicodeUtf8Vertical /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 7 >> /DW 1000 >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Vertical Text'], $extractor->extractTextLines($pdf));
        $t->same(['Vertical', ' Text'], $extractor->extractTextRuns($pdf));
        $t->same('Vertical Text', $plainText);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'keeps q Q scoped text state from leaking into later positioned WordPress text' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm q 20 Tc (Data) Tj Q 1 0 0 1 180 720 Tm (Import) Tj 1 0 0 1 235 720 Tm (Tool) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Data Import Tool'], $lines);
        $t->true(!str_contains($plainText, 'ImportTool'));
    },
    'applies TJ numeric positioning adjustments before WordPress Tm gap decisions' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm [(Import ) -1000 (Profile)] TJ 1 0 0 1 178 720 Tm (s) Tj '
            . '1 0 0 1 72 704 Tm [(Site) 1000 (Map)] TJ 1 0 0 1 124 704 Tm (Index) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Import Profiles', 'SiteMap Index'], $lines);
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(str_contains($plainText, 'SiteMap Index'));
    },
    'applies Tm horizontal scaling before WordPress Tm gap decisions' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 1.5 0 0 1 72 720 Tm (Import Profile) Tj 1 0 0 1 204 720 Tm (s) Tj '
            . '0.5 0 0 1 72 704 Tm (SiteMap) Tj 1 0 0 1 106 704 Tm (Index) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Import Profiles', 'SiteMap Index'], $lines);
        $t->true(!str_contains($plainText, 'Profile s'));
        $t->true(str_contains($plainText, 'SiteMap Index'));
    },
    'applies rotated Tm text vectors before WordPress Tm gap decisions' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 0 1 -1 0 100 100 Tm (Data) Tj 0 1 -1 0 100 126 Tm (base) Tj '
            . '0 1 -1 0 100 170 Tm (Tool) Tj 0 1 -1 0 84 100 Tm (Next) Tj 0 1 -1 0 84 126 Tm (Line) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Database Tool', 'NextLine'], $lines);
        $t->true(!str_contains($plainText, "Data\nbase"));
        $t->true(!str_contains($plainText, 'Data base'));
    },
    'applies cm transformation matrices before WordPress Tm gap decisions' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'q 2 0 0 1 0 0 cm BT /F1 12 Tf 1 0 0 1 72 720 Tm (Data) Tj '
            . '1 0 0 1 104 720 Tm (Tool) Tj ET Q '
            . 'BT /F1 12 Tf 1 0 0 1 72 700 Tm (Plain) Tj 1 0 0 1 104 700 Tm (Text) Tj ET';
        $extractor = new PdfTextExtractor();
        $lines = $extractor->extractTextLines($pdfWithContent($content));
        $plainText = $extractor->extractPlainText($pdfWithContent($content));

        $t->same(['Data Tool', 'PlainText'], $lines);
        $t->true(str_contains($plainText, 'Data Tool'));
        $t->true(!str_contains($plainText, 'DataTool'));
        $t->true(str_contains($plainText, 'PlainText'));
        $t->true(!str_contains($plainText, 'Plain Text'));
    },
    'decodes literal continuations and UTF-16BE hex strings' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = "BT (WordPress \\\nimport) Tj T* <FEFF00440061007400610020004C0069006200650072006100740069006F006E> Tj ET";
        $lines = (new PdfTextExtractor())->extractTextLines($pdfWithContent($content));
        $t->same(['WordPress import', 'Data Liberation'], $lines);
    },
    'decodes unknown literal escapes before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $content = 'BT /F1 12 Tf 72 720 Td (WordPress \#1 import) Tj T* (Data \- Liberation) Tj ET';
        $extractor = new PdfTextExtractor();

        $t->same(['WordPress #1 import', 'Data - Liberation'], $extractor->extractTextLines($pdfWithContent($content)));
        $t->same('WordPress #1 import' . "\n" . 'Data - Liberation', $extractor->extractPlainText($pdfWithContent($content)));
    },
    'decodes UTF-16 literal strings before WordPress paragraph rendering' => static function (TestRunner $t) use ($pdfWithContent): void {
        $utf16be = "\xFE\xFF" . mb_convert_encoding('WordPress résumé', 'UTF-16BE', 'UTF-8');
        $utf16le = "\xFF\xFE" . mb_convert_encoding('Data Liberation', 'UTF-16LE', 'UTF-8');
        $content = 'BT (' . $utf16be . ') Tj T* (' . $utf16le . ') Tj ET';
        $extractor = new PdfTextExtractor();

        $t->same(['WordPress résumé', 'Data Liberation'], $extractor->extractTextLines($pdfWithContent($content)));
        $t->same('WordPress résumé' . "\n" . 'Data Liberation', $extractor->extractPlainText($pdfWithContent($content)));
    },
    'ignores non page content streams that look like garbled text operators' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Readable PDF text) Tj ET';
        $fontProgram = "BT /F1 12 Tf 72 720 Td (\x91\x92) Tj T* (?PKEA) Tj T* (\x87\x88\x89\x8A\x8A\x8B\x88\x8C\x8B\x89) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /FontDescriptor 6 0 R /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /FontDescriptor /FontName /Subset /FontFile2 7 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($fontProgram) . " >>\nstream\n{$fontProgram}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Readable PDF text'], $extractor->extractTextLines($pdf));
        $t->same(['Readable PDF text'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, "\u{2018}\u{2019}"));
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'uses marked content ActualText instead of custom glyph bytes' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /ActualText (Quarterly Risk Review) >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /ActualText (Controls and mitigations) >> BDC <87918A8B8C8D8E8F90> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Subset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Quarterly Risk Review', 'Controls and mitigations'], $extractor->extractTextLines($pdf));
        $t->same(['Quarterly Risk Review', 'Controls and mitigations'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'diagnoses partial ToUnicode maps that discard arbitrary leading glyph fragments' => static function (TestRunner $t): void {
        // The two leading CIDs are deliberately absent from the map. Their
        // semantic value cannot be guessed, but silently returning only the
        // suffix would hide data loss from callers.
        $content = 'BT /Fcid 12 Tf 72 720 Td <0001000200030004> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<0003> <00690073>\n"
            . "<0004> <002000720065006100640079>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /PartialPrefixCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PartialPrefixSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['is ready'], $extractor->extractTextRuns($pdf));
        $t->same(1, $diagnostics['partiallyMappedGlyphRuns'] ?? null);
        $t->contains('1 PDF text run(s) lost glyphs through a partial Unicode map.', implode("\n", $diagnostics['warnings']));
    },
    'diagnoses empty ToUnicode targets that erase arbitrary glyph fragments' => static function (TestRunner $t): void {
        // An explicitly empty target is just as lossy as an absent one. It
        // must not make the surviving suffix look like complete source text.
        $content = 'BT /Fcid 12 Tf 72 720 Td <0102> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "2 beginbfchar\n"
            . "<01> <>\n"
            . "<02> <0041>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /EmptyTargetCMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EmptyTargetSubset /Encoding /Identity-H /ToUnicode 5 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same(1, $diagnostics['partiallyMappedGlyphRuns'] ?? null);
        $t->contains('1 PDF text run(s) lost glyphs through a partial Unicode map.', implode("\n", $diagnostics['warnings']));
    },
    'keeps inline ActualText in positioned runs at the painted text location' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm /Span << /ActualText (Cedar) >> BDC <0102030405> Tj EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ArbitrarySubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $positioned = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['Cedar'], $extractor->extractTextRuns($pdf));
        $t->same([
            ['text' => 'Cedar', 'textX1' => 72.0, 'textY1' => 720.0],
        ], array_map(static fn (array $run): array => [
            'text' => $run['text'],
            'textX1' => $run['textX1'],
            'textY1' => $run['textY1'],
        ], $positioned));
        // The painted glyph bytes are intentionally not decodable. Replacement
        // text still needs a non-degenerate inferred span so downstream layout
        // has geometry to work with rather than a zero-width point.
        $t->true(($positioned[0]['textX2'] ?? 0.0) > 72.0);
    },
    'keeps property ActualText in positioned runs at the painted text location' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 96 680 Tm /Span /P1 BDC <111213141516> Tj EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /P1 << /ActualText (Harbor) >> >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /PropertySubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $positioned = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['Harbor'], $extractor->extractTextRuns($pdf));
        $t->same([
            ['text' => 'Harbor', 'textX1' => 96.0, 'textY1' => 680.0],
        ], array_map(static fn (array $run): array => [
            'text' => $run['text'],
            'textX1' => $run['textX1'],
            'textY1' => $run['textY1'],
        ], $positioned));
        $t->true(($positioned[0]['textX2'] ?? 0.0) > 96.0);
    },
    'keeps structure MCID ActualText in positioned runs at the painted text location' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 120 640 Tm /Span << /MCID 0 >> BDC <2122232425> Tj EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /TaggedSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Maple) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $positioned = $extractor->extractPositionedTextRuns($pdf);

        $t->same(['Maple'], $extractor->extractTextRuns($pdf));
        $t->same([
            ['text' => 'Maple', 'textX1' => 120.0, 'textY1' => 640.0],
        ], array_map(static fn (array $run): array => [
            'text' => $run['text'],
            'textX1' => $run['textX1'],
            'textY1' => $run['textY1'],
        ], $positioned));
        $t->true(($positioned[0]['textX2'] ?? 0.0) > 120.0);
    },
    'keeps an ActualText span bounded by every painted text-showing operation' => static function (TestRunner $t): void {
        // Replacement text is one semantic span even when its painted glyphs
        // are emitted through multiple Tj operations with an intervening Tm.
        // Capturing only the first operation makes the semantic run look much
        // narrower than its real visual extent and can misclassify its column.
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm /Span << /ActualText (Cedar) >> BDC (AB) Tj 1 0 0 1 120 720 Tm (CD) Tj EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $positioned = (new PdfTextExtractor())->extractPositionedTextRuns($pdf);

        $t->same(['Cedar'], array_column($positioned, 'text'));
        $t->same(72.0, $positioned[0]['textX1'] ?? null);
        $t->true(($positioned[0]['textX2'] ?? 0.0) > 120.0);
    },
    'uses only the innermost nested ActualText replacement across extraction layers' => static function (TestRunner $t): void {
        // Nested replacement scopes are not two visible copies of the same
        // glyphs. The innermost semantic replacement governs the painted
        // range, so raw, line, and positioned extraction must agree.
        $content = 'BT /F1 12 Tf 72 720 Td /Span << /ActualText (outer) >> BDC /Span << /ActualText (inner) >> BDC (x) Tj EMC EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['inner'], $extractor->extractTextRuns($pdf));
        $t->same(['inner'], $extractor->extractTextLines($pdf));
        $t->same(['inner'], array_column($extractor->extractPositionedTextRuns($pdf), 'text'));
    },
    'does not leak ActualText nested inside artifact content' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td /Artifact BMC /Span << /ActualText (leak) >> BDC (x) Tj EMC EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractPositionedTextRuns($pdf));
    },
    'diagnoses empty ActualText that suppresses painted glyphs without guessing them' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm /Span << /ActualText () >> BDC (Painted glyphs) Tj EMC ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same(1, $diagnostics['unrecoverableActualTextRuns'] ?? null);
        $t->contains('1 marked PDF text run(s) had empty ActualText while hiding painted glyphs.', implode("\n", $diagnostics['warnings']));
    },
    'suppresses marked content artifacts before WordPress paragraph rendering' => static function (TestRunner $t): void {
        $content = "BT /F2 12 Tf 72 720 Td "
            . "/Artifact BMC (Running Header) Tj EMC T* "
            . "/F1 12 Tf /Span << /ActualText (Article Body) >> BDC <3F504B4541> Tj EMC T* "
            . "/F2 12 Tf /Art#69fact << /Type /Pagination >> BDC (Page 1) Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Article Body'], $extractor->extractTextLines($pdf));
        $t->same(['Article Body'], $extractor->extractTextRuns($pdf));
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->true(!str_contains($plainText, 'Running Header'));
        $t->true(!str_contains($plainText, 'Page 1'));
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'uses marked content property ActualText resources instead of custom glyph bytes' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td /Span /P1 BDC <3F504B4541> Tj EMC T* /Span /P2 BDC <3F34402E3541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /P1 << /ActualText (Project scope) >> /P2 6 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Subset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /ActualText (Budget review) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Project scope', 'Budget review'], $extractor->extractTextLines($pdf));
        $t->same(['Project scope', 'Budget review'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?4@.5A'));
    },
    'uses structure tree ParentTree MCID ActualText instead of custom glyph bytes' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Tagged Structure Text) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Second Tagged Line) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Tagged Structure Text', 'Second Tagged Line'], $extractor->extractTextLines($pdf));
        $t->same(['Tagged Structure Text', 'Second Tagged Line'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'uses escaped tagged PDF dictionary names for ParentTree ActualText lookup' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCI#44 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Struct#50arents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /Parent#54ree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nu#6Ds [ 0 [ 9 0 R null ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Actual#54ext (Escaped Key Tagged Text) /#4B << /Type /MCR /MCI#44 1 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Escaped Key Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Escaped Key Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->same(1, substr_count($plainText, 'Escaped Key Tagged Text'));
    },
    'uses escaped StructTreeRoot catalog and type names for ParentTree ActualText lookup' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Struct#54reeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Struct#54reeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Escaped Root Tagged Text) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Escaped Root Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Escaped Root Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->same(1, substr_count($plainText, 'Escaped Root Tagged Text'));
    },
    'records tagged PDF role map and language semantics in diagnostics' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Lang (en-US) /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Lang (fr-CA) /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /Lang (en-GB) /RoleMap 13 0 R /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 11 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /FancyHeading /Lang (de-DE) /ActualText (Role Mapped Heading) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /CustomParagraph /ActualText (Role Mapped Paragraph) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /NestedRole /Lang 12 0 R /ActualText (Role Mapped Nested) >>\nendobj\n"
            . "12 0 obj\n(es-MX)\nendobj\n"
            . "13 0 obj\n<< /FancyHeading /H1 /CustomParagraph /P /NestedRole /FancyHeading >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Role Mapped Heading', 'Role Mapped Paragraph', 'Role Mapped Nested'], $extractor->extractTextLines($pdf));
        $t->same([
            'CustomParagraph' => 'P',
            'FancyHeading' => 'H1',
            'NestedRole' => 'FancyHeading',
        ], $diagnostics['taggedRoleMap']);
        $t->same([
            'CustomParagraph' => 'P',
            'FancyHeading' => 'H1',
            'NestedRole' => 'H1',
        ], $diagnostics['taggedStructureRoles']);
        $t->same(['de-DE', 'en-GB', 'en-US', 'es-MX', 'fr-CA'], $diagnostics['taggedStructureLanguages']);
        $t->same(3, $diagnostics['taggedStructElementCount']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'records tagged PDF ClassMap and attribute semantics in diagnostics' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /RoleMap 13 0 R /ClassMap 14 0 R /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /FancyHeading /C /CaptionClass /A 15 0 R /ActualText (Attribute Heading) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /CustomParagraph /C [ /ListClass ] /A [ 16 0 R << /O /Layout /SpaceAfter 4 >> ] /ActualText (Attribute Paragraph) >>\nendobj\n"
            . "13 0 obj\n<< /FancyHeading /H1 /CustomParagraph /P >>\nendobj\n"
            . "14 0 obj\n<< /CaptionClass << /O /Layout /Placement /Block /SpaceBefore 6 >> /ListClass [ << /O /List /ListNumbering /Decimal >> 0 << /O /Layout /WritingMode /LrTb >> ] >>\nendobj\n"
            . "15 0 obj\n<< /O /Layout /Placement /Block /SpaceAfter 2 /BBox [0 0 200 24] >>\nendobj\n"
            . "16 0 obj\n<< /O /Table /RowSpan 2 /ColSpan 3 >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Attribute Heading', 'Attribute Paragraph'], $extractor->extractTextLines($pdf));
        $t->same([
            'CaptionClass' => [
                [
                    'O' => 'Layout',
                    'Placement' => 'Block',
                    'SpaceBefore' => 6,
                ],
            ],
            'ListClass' => [
                [
                    'ListNumbering' => 'Decimal',
                    'O' => 'List',
                ],
                [
                    'O' => 'Layout',
                    'WritingMode' => 'LrTb',
                ],
            ],
        ], $diagnostics['taggedClassMap']);
        $t->same([
            [
                'role' => 'FancyHeading',
                'resolvedRole' => 'H1',
                'classes' => ['CaptionClass'],
                'attributes' => [
                    [
                        'O' => 'Layout',
                        'Placement' => 'Block',
                        'SpaceBefore' => 6,
                    ],
                    [
                        'BBox' => [0, 0, 200, 24],
                        'O' => 'Layout',
                        'Placement' => 'Block',
                        'SpaceAfter' => 2,
                    ],
                ],
            ],
            [
                'role' => 'CustomParagraph',
                'resolvedRole' => 'P',
                'classes' => ['ListClass'],
                'attributes' => [
                    [
                        'ListNumbering' => 'Decimal',
                        'O' => 'List',
                    ],
                    [
                        'O' => 'Layout',
                        'WritingMode' => 'LrTb',
                    ],
                    [
                        'ColSpan' => 3,
                        'O' => 'Table',
                        'RowSpan' => 2,
                    ],
                    [
                        'O' => 'Layout',
                        'SpaceAfter' => 4,
                    ],
                ],
            ],
        ], $diagnostics['taggedStructureAttributes']);
        $t->same([
            [
                'objectNumber' => 9,
                'role' => 'FancyHeading',
                'resolvedRole' => 'H1',
                'language' => null,
                'classes' => ['CaptionClass'],
                'attributes' => [
                    [
                        'O' => 'Layout',
                        'Placement' => 'Block',
                        'SpaceBefore' => 6,
                    ],
                    [
                        'BBox' => [0, 0, 200, 24],
                        'O' => 'Layout',
                        'Placement' => 'Block',
                        'SpaceAfter' => 2,
                    ],
                ],
                'text' => 'Attribute Heading',
            ],
            [
                'objectNumber' => 10,
                'role' => 'CustomParagraph',
                'resolvedRole' => 'P',
                'language' => null,
                'classes' => ['ListClass'],
                'attributes' => [
                    [
                        'ListNumbering' => 'Decimal',
                        'O' => 'List',
                    ],
                    [
                        'O' => 'Layout',
                        'WritingMode' => 'LrTb',
                    ],
                    [
                        'ColSpan' => 3,
                        'O' => 'Table',
                        'RowSpan' => 2,
                    ],
                    [
                        'O' => 'Layout',
                        'SpaceAfter' => 4,
                    ],
                ],
                'text' => 'Attribute Paragraph',
            ],
        ], $diagnostics['taggedStructureItems']);
        $t->same(['Layout', 'List', 'Table'], $diagnostics['taggedAttributeOwners']);
        $t->same(2, $diagnostics['taggedStructElementCount']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'records tagged PDF table row and cell semantics in diagnostics' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 13 0 R 14 0 R 15 0 R 16 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Table /K [10 0 R 11 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /TR /K [13 0 R 14 0 R] >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /TR /K [15 0 R 16 0 R] >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /TD /ActualText (Apples) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 /RowSpan 1 >> /ActualText (3) /K << /Type /MCR /MCID 3 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Name', 'Count', 'Apples', '3'], $extractor->extractTextLines($pdf));
        $t->same([
            [
                'objectNumber' => 9,
                'rows' => [
                    [
                        [
                            'role' => 'TH',
                            'resolvedRole' => 'TH',
                            'text' => 'Name',
                            'rowSpan' => 1,
                            'colSpan' => 1,
                            'attributes' => [],
                        ],
                        [
                            'role' => 'TH',
                            'resolvedRole' => 'TH',
                            'text' => 'Count',
                            'rowSpan' => 1,
                            'colSpan' => 1,
                            'attributes' => [],
                        ],
                    ],
                    [
                        [
                            'role' => 'TD',
                            'resolvedRole' => 'TD',
                            'text' => 'Apples',
                            'rowSpan' => 1,
                            'colSpan' => 1,
                            'attributes' => [],
                        ],
                        [
                            'role' => 'TD',
                            'resolvedRole' => 'TD',
                            'text' => '3',
                            'rowSpan' => 1,
                            'colSpan' => 2,
                            'attributes' => [
                                [
                                    'ColSpan' => 2,
                                    'O' => 'Table',
                                    'RowSpan' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $diagnostics['taggedTables']);
        $t->same(7, $diagnostics['taggedStructElementCount']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'records tagged PDF table header body and footer sections in diagnostics' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 4 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 5 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 16 0 R 17 0 R 18 0 R 19 0 R 20 0 R 21 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Table /K [10 0 R 11 0 R 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /THead /K 13 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /TBody /K 14 0 R >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /TFoot /K 15 0 R >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /TR /K [16 0 R 17 0 R] >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TR /K [18 0 R 19 0 R] >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /TR /K [20 0 R 21 0 R] >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TH /ID (name-h) /A << /O /Table /Scope /Column >> /ActualText (Name) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TH /ID (count-h) /A << /O /Table /Scope /Column >> /ActualText (Count) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /Headers [(name-h)] >> /ActualText (Apples) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "19 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 /Headers [(count-h)] >> /ActualText (3) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
            . "20 0 obj\n<< /Type /StructElem /S /TH /A << /O /Table /Scope /Row >> /ActualText (Total) /K << /Type /MCR /MCID 4 >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /TD /ActualText (3) /K << /Type /MCR /MCID 5 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);
        $sections = $diagnostics['taggedTables'][0]['sections'] ?? [];

        $t->same(['Name', 'Count', 'Apples', '3', 'Total', '3'], $extractor->extractTextLines($pdf));
        $t->same(13, $diagnostics['taggedStructElementCount']);
        $t->same('3', $diagnostics['taggedTables'][0]['rows'][1][1]['text']);
        $t->same('Total', $diagnostics['taggedTables'][0]['rows'][2][0]['text']);
        $t->same(['THead', 'TBody', 'TFoot'], array_column($sections, 'role'));
        $t->same([10, 11, 12], array_column($sections, 'objectNumber'));
        $t->same('Name', $sections[0]['rows'][0][0]['text']);
        $t->same('name-h', $sections[0]['rows'][0][0]['id']);
        $t->same('Column', $sections[0]['rows'][0][0]['attributes'][0]['Scope']);
        $t->same('Apples', $sections[1]['rows'][0][0]['text']);
        $t->same(['name-h'], $sections[1]['rows'][0][0]['attributes'][0]['Headers']);
        $t->same(2, $sections[1]['rows'][0][1]['colSpan']);
        $t->same(['count-h'], $sections[1]['rows'][0][1]['attributes'][0]['Headers']);
        $t->same('Total', $sections[2]['rows'][0][0]['text']);
        $t->same('Row', $sections[2]['rows'][0][0]['attributes'][0]['Scope']);
        $t->same('TFoot', $diagnostics['taggedStructureBlocks'][0]['sections'][2]['role']);
        $t->same('Total', $diagnostics['taggedStructureBlocks'][0]['sections'][2]['rows'][0][0]['text']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'records tagged PDF table sections through neutral wrappers in diagnostics' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 4 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 5 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 6 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 7 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /RoleMap << /HeaderBand /THead /BodyBand /TBody /SummaryBand /TFoot >> /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 20 0 R 21 0 R 22 0 R 23 0 R 24 0 R 25 0 R 26 0 R 27 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Table /K [10 0 R 11 0 R 12 0 R] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Div /K 13 0 R >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /NonStruct /K [14 0 R 15 0 R] >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /SummaryBand /K 18 0 R >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /HeaderBand /K 16 0 R >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /BodyBand /K 17 0 R >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /BodyBand /K 19 0 R >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TR /K [20 0 R 21 0 R] >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TR /K [22 0 R 23 0 R] >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /TR /K [26 0 R 27 0 R] >>\nendobj\n"
            . "19 0 obj\n<< /Type /StructElem /S /TR /K 28 0 R >>\nendobj\n"
            . "20 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "21 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "22 0 obj\n<< /Type /StructElem /S /TD /ActualText (Apples) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "23 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 >> /ActualText (3) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
            . "24 0 obj\n<< /Type /StructElem /S /TD /ActualText (Oranges) /K << /Type /MCR /MCID 4 >> >>\nendobj\n"
            . "25 0 obj\n<< /Type /StructElem /S /TD /ActualText (5) /K << /Type /MCR /MCID 5 >> >>\nendobj\n"
            . "26 0 obj\n<< /Type /StructElem /S /TH /ActualText (Total) /K << /Type /MCR /MCID 6 >> >>\nendobj\n"
            . "27 0 obj\n<< /Type /StructElem /S /TD /ActualText (8) /K << /Type /MCR /MCID 7 >> >>\nendobj\n"
            . "28 0 obj\n<< /Type /StructElem /S /Div /K [24 0 R 25 0 R] >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);
        $sections = $diagnostics['taggedTables'][0]['sections'] ?? [];

        $t->same(['Name', 'Count', 'Apples', '3', 'Oranges', '5', 'Total', '8'], $extractor->extractTextLines($pdf));
        $t->same(20, $diagnostics['taggedStructElementCount']);
        $t->same('Oranges', $diagnostics['taggedTables'][0]['rows'][2][0]['text']);
        $t->same('Total', $diagnostics['taggedTables'][0]['rows'][3][0]['text']);
        $t->same(['HeaderBand', 'BodyBand', 'BodyBand', 'SummaryBand'], array_column($sections, 'role'));
        $t->same(['THead', 'TBody', 'TBody', 'TFoot'], array_column($sections, 'resolvedRole'));
        $t->same([13, 14, 15, 12], array_column($sections, 'objectNumber'));
        $t->same('Name', $sections[0]['rows'][0][0]['text']);
        $t->same('Apples', $sections[1]['rows'][0][0]['text']);
        $t->same(2, $sections[1]['rows'][0][1]['colSpan']);
        $t->same('Oranges', $sections[2]['rows'][0][0]['text']);
        $t->same('Total', $sections[3]['rows'][0][0]['text']);
        $t->same('BodyBand', $diagnostics['taggedStructureBlocks'][0]['sections'][2]['role']);
        $t->same('Oranges', $diagnostics['taggedStructureBlocks'][0]['sections'][2]['rows'][0][0]['text']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'records mixed tagged PDF block and table order in diagnostics' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 4 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 5 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 6 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K [9 0 R 10 0 R 11 0 R 18 0 R] >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 14 0 R 15 0 R 16 0 R 17 0 R 18 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Mixed Heading) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /P /ActualText (Intro paragraph.) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Table /K [12 0 R 13 0 R] >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /TR /K [14 0 R 15 0 R] >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /TR /K [16 0 R 17 0 R] >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TD /ActualText (Apples) /K << /Type /MCR /MCID 4 >> >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 >> /ActualText (3) /K << /Type /MCR /MCID 5 >> >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /P /ActualText (Closing paragraph.) /K << /Type /MCR /MCID 6 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Mixed Heading', 'Intro paragraph.', 'Name', 'Count', 'Apples', '3', 'Closing paragraph.'], $extractor->extractTextLines($pdf));
        $t->same(10, $diagnostics['taggedStructElementCount']);
        $t->same('Mixed Heading', $diagnostics['taggedStructureBlocks'][0]['text']);
        $t->same('H1', $diagnostics['taggedStructureBlocks'][0]['resolvedRole']);
        $t->same('Intro paragraph.', $diagnostics['taggedStructureBlocks'][1]['text']);
        $t->same('table', $diagnostics['taggedStructureBlocks'][2]['kind']);
        $t->same(11, $diagnostics['taggedStructureBlocks'][2]['objectNumber']);
        $t->same('Name', $diagnostics['taggedStructureBlocks'][2]['rows'][0][0]['text']);
        $t->same('Count', $diagnostics['taggedStructureBlocks'][2]['rows'][0][1]['text']);
        $t->same('Apples', $diagnostics['taggedStructureBlocks'][2]['rows'][1][0]['text']);
        $t->same('3', $diagnostics['taggedStructureBlocks'][2]['rows'][1][1]['text']);
        $t->same(2, $diagnostics['taggedStructureBlocks'][2]['rows'][1][1]['colSpan']);
        $t->same('Closing paragraph.', $diagnostics['taggedStructureBlocks'][3]['text']);
        $t->same('3', $diagnostics['taggedTables'][0]['rows'][1][1]['text']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'records nested partial tagged PDF block order in diagnostics' => static function (TestRunner $t): void {
        $content = "BT /F2 12 Tf 72 720 Td "
            . "(Untagged preface.) Tj T* "
            . "/F1 12 Tf /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 4 >> BDC <3F314749314541> Tj EMC T* "
            . "/F2 12 Tf (Untagged coda.) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 11 0 R 16 0 R 17 0 R 18 0 R 19 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Document /K 10 0 R >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Sect /K [11 0 R 12 0 R] >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Nested Heading) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /Table /K [13 0 R 14 0 R] >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /TR /K [16 0 R 17 0 R] >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TR /K [18 0 R 19 0 R] >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /TD /ActualText (Apples) /K << /Type /MCR /MCID 3 >> >>\nendobj\n"
            . "19 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 >> /ActualText (3) /K << /Type /MCR /MCID 4 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Untagged preface.', 'Nested Heading', 'Name', 'Count', 'Apples', '3', 'Untagged coda.'], $extractor->extractTextLines($pdf));
        $t->same(10, $diagnostics['taggedStructElementCount']);
        $t->same(2, count($diagnostics['taggedStructureBlocks']));
        $t->same('Nested Heading', $diagnostics['taggedStructureBlocks'][0]['text']);
        $t->same('H1', $diagnostics['taggedStructureBlocks'][0]['resolvedRole']);
        $t->same('table', $diagnostics['taggedStructureBlocks'][1]['kind']);
        $t->same(12, $diagnostics['taggedStructureBlocks'][1]['objectNumber']);
        $t->same('Name', $diagnostics['taggedStructureBlocks'][1]['rows'][0][0]['text']);
        $t->same('Count', $diagnostics['taggedStructureBlocks'][1]['rows'][0][1]['text']);
        $t->same('Apples', $diagnostics['taggedStructureBlocks'][1]['rows'][1][0]['text']);
        $t->same('3', $diagnostics['taggedStructureBlocks'][1]['rows'][1][1]['text']);
        $t->same(2, $diagnostics['taggedStructureBlocks'][1]['rows'][1][1]['colSpan']);
        $t->same('3', $diagnostics['taggedTables'][0]['rows'][1][1]['text']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'records partial tagged blocks with duplicate extracted text for safe bridge fallback' => static function (TestRunner $t): void {
        $content = "BT /F2 12 Tf 72 720 Td "
            . "(Repeated paragraph.) Tj T* "
            . "/F1 12 Tf /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/F2 12 Tf (Untagged coda.) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R /K 9 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Repeated paragraph.) /K << /Type /MCR /MCID 0 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Repeated paragraph.', 'Repeated paragraph.', 'Untagged coda.'], $extractor->extractTextLines($pdf));
        $t->same(1, $diagnostics['taggedStructElementCount']);
        $t->same(1, count($diagnostics['taggedStructureBlocks']));
        $t->same('Repeated paragraph.', $diagnostics['taggedStructureBlocks'][0]['text']);
        $t->same('H1', $diagnostics['taggedStructureBlocks'][0]['resolvedRole']);
        $t->true(!isset($diagnostics['taggedStructureBlocks'][0]['sourceProvenance']), 'An unscoped StructElem must not acquire guessed page provenance.');
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'records multi page tagged structure blocks across page parent trees' => static function (TestRunner $t): void {
        $pageOneContent = "BT /F2 12 Tf 72 720 Td "
            . "(Page one preface.) Tj T* "
            . "/F1 12 Tf /Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pageTwoContent = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <87918A8B8C8D8E8F90> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 3 >> BDC <3F314749314541> Tj EMC T* "
            . "/F2 12 Tf (Page two coda.) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 9 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 7 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 1 /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /StructTreeRoot /ParentTree 10 0 R /K 11 0 R >>\nendobj\n"
            . "10 0 obj\n<< /Nums [ 0 [ 12 0 R ] 1 [ 16 0 R 17 0 R 18 0 R 19 0 R ] ] >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Document /K [12 0 R 13 0 R] >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Page One Heading) /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /StructElem /S /Table /K [14 0 R 15 0 R] >>\nendobj\n"
            . "14 0 obj\n<< /Type /StructElem /S /TR /K [16 0 R 17 0 R] >>\nendobj\n"
            . "15 0 obj\n<< /Type /StructElem /S /TR /K [18 0 R 19 0 R] >>\nendobj\n"
            . "16 0 obj\n<< /Type /StructElem /S /TH /ActualText (Name) /K << /Type /MCR /Pg 7 0 R /MCID 0 >> >>\nendobj\n"
            . "17 0 obj\n<< /Type /StructElem /S /TH /ActualText (Count) /K << /Type /MCR /Pg 7 0 R /MCID 1 >> >>\nendobj\n"
            . "18 0 obj\n<< /Type /StructElem /S /TD /ActualText (Bananas) /K << /Type /MCR /Pg 7 0 R /MCID 2 >> >>\nendobj\n"
            . "19 0 obj\n<< /Type /StructElem /S /TD /A << /O /Table /ColSpan 2 >> /ActualText (7) /K << /Type /MCR /Pg 7 0 R /MCID 3 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Page one preface.', 'Page One Heading', 'Name', 'Count', 'Bananas', '7', 'Page two coda.'], $extractor->extractTextLines($pdf));
        $t->same(9, $diagnostics['taggedStructElementCount']);
        $t->same(2, count($diagnostics['taggedStructureBlocks']));
        $t->same('Page One Heading', $diagnostics['taggedStructureBlocks'][0]['text']);
        $t->same('H1', $diagnostics['taggedStructureBlocks'][0]['resolvedRole']);
        $t->same([1], $diagnostics['taggedStructureBlocks'][0]['pageNumbers'] ?? null);
        $t->same([3], $diagnostics['taggedStructureBlocks'][0]['pageObjects'] ?? null);
        $t->same('unique-page', $diagnostics['taggedStructureBlocks'][0]['sourceProvenance']['pageScope'] ?? null);
        $t->same('table', $diagnostics['taggedStructureBlocks'][1]['kind']);
        $t->same(13, $diagnostics['taggedStructureBlocks'][1]['objectNumber']);
        $t->same([2], $diagnostics['taggedStructureBlocks'][1]['pageNumbers'] ?? null);
        $t->same([7], $diagnostics['taggedStructureBlocks'][1]['pageObjects'] ?? null);
        $t->same('Name', $diagnostics['taggedStructureBlocks'][1]['rows'][0][0]['text']);
        $t->same([2], $diagnostics['taggedStructureBlocks'][1]['rows'][0][0]['pageNumbers'] ?? null);
        $t->same(16, $diagnostics['taggedStructureBlocks'][1]['rows'][0][0]['sourceProvenance']['objectNumber'] ?? null);
        $t->same('Count', $diagnostics['taggedStructureBlocks'][1]['rows'][0][1]['text']);
        $t->same('Bananas', $diagnostics['taggedStructureBlocks'][1]['rows'][1][0]['text']);
        $t->same('7', $diagnostics['taggedStructureBlocks'][1]['rows'][1][1]['text']);
        $t->same(2, $diagnostics['taggedStructureBlocks'][1]['rows'][1][1]['colSpan']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'records link annotations matched to visible page text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Read the Docs) Tj 0 -16 Td (Plain coda) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 153 735] /A << /S /URI /URI (https://example.test/docs) >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Read the Docs', 'Plain coda'], $extractor->extractTextLines($pdf));
        $t->same(1, count($diagnostics['linkAnnotations']));
        $t->same(1, $diagnostics['linkAnnotations'][0]['page']);
        $t->same(3, $diagnostics['linkAnnotations'][0]['pageObject']);
        $t->same(6, $diagnostics['linkAnnotations'][0]['annotationObject']);
        $t->same('https://example.test/docs', $diagnostics['linkAnnotations'][0]['uri']);
        $t->same('Read the Docs', $diagnostics['linkAnnotations'][0]['text']);
        $t->same([70.0, 716.0, 153.0, 735.0], $diagnostics['linkAnnotations'][0]['rect']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'uses link annotation quadpoints instead of broad annotation rectangle for visible text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Intro sentence.) Tj 0 -16 Td (Read docs here.) Tj 0 -16 Td (Plain coda.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 684 175 716] /QuadPoints [70 716 163 716 70 700 163 700] /A << /S /URI /URI (https://example.test/docs) >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Intro sentence.', 'Read docs here.', 'Plain coda.'], $extractor->extractTextLines($pdf));
        $t->same(1, count($diagnostics['linkAnnotations']));
        $t->same('Read docs here.', $diagnostics['linkAnnotations'][0]['text']);
        $t->same('https://example.test/docs', $diagnostics['linkAnnotations'][0]['uri']);
        $t->same([70.0, 684.0, 175.0, 716.0], $diagnostics['linkAnnotations'][0]['rect']);
        $t->same([70.0, 716.0, 163.0, 716.0, 70.0, 700.0, 163.0, 700.0], $diagnostics['linkAnnotations'][0]['quadPoints']);
        $t->same([[70.0, 700.0, 163.0, 716.0]], $diagnostics['linkAnnotations'][0]['quadRects']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records text-bearing annotations without injecting them into page text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Reviewed passage.) Tj 0 -16 Td (Plain body.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [70 716 190 735] /QuadPoints [70 735 190 735 70 716 190 716] /Contents 8 0 R /T 9 0 R /M (D:20260619120000Z) /Subj (Review note) >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [70 690 190 710] /Contents <FEFF005300650063006F006E00640020006E006F00740065> /NM (note-1) /Subj (Margin summary) >>\nendobj\n"
            . "8 0 obj\n(Needs follow-up)\nendobj\n"
            . "9 0 obj\n(Reviewer)\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Reviewed passage.', 'Plain body.'], $extractor->extractTextLines($pdf));
        $t->same(2, count($diagnostics['textAnnotations']));
        $t->same(6, $diagnostics['textAnnotations'][0]['annotationObject']);
        $t->same('Highlight', $diagnostics['textAnnotations'][0]['subtype']);
        $t->same('Needs follow-up', $diagnostics['textAnnotations'][0]['contents']);
        $t->same('Reviewer', $diagnostics['textAnnotations'][0]['title']);
        $t->same('D:20260619120000Z', $diagnostics['textAnnotations'][0]['modified']);
        $t->same('Review note', $diagnostics['textAnnotations'][0]['subject']);
        $t->same([70.0, 716.0, 190.0, 735.0], $diagnostics['textAnnotations'][0]['rect']);
        $t->same([70.0, 735.0, 190.0, 735.0, 70.0, 716.0, 190.0, 716.0], $diagnostics['textAnnotations'][0]['quadPoints']);
        $t->same([[70.0, 716.0, 190.0, 735.0]], $diagnostics['textAnnotations'][0]['quadRects']);
        $t->same(7, $diagnostics['textAnnotations'][1]['annotationObject']);
        $t->same('FreeText', $diagnostics['textAnnotations'][1]['subtype']);
        $t->same('Second note', $diagnostics['textAnnotations'][1]['contents']);
        $t->same('note-1', $diagnostics['textAnnotations'][1]['name']);
        $t->same('Margin summary', $diagnostics['textAnnotations'][1]['subject']);
        $t->same([], $diagnostics['linkAnnotations']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records broader markup annotation metadata without injecting comments into page text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Markup body.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [70 716 90 736] /Contents (Sticky note) /Name /Comment /Open true /C [1 0.5 0] /F 36 /CA 0.75 /CreationDate (D:20260619130000Z) /State (Accepted) /StateModel (Review) >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Ink /Rect [70 680 150 710] /Contents (Ink path) /InkList [[70 700 80 710 90 705] [100 700 110 708]] /Border [0 0 2] /BS << /W 2 /S /D /D [3 2] >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /Line /Rect [70 640 150 690] /Contents (Line note) /L [70 660 140 680] /LE [/OpenArrow /ClosedArrow] /IC [0 1 0] /Cap true /LL 4 /LLE 2 /LLO 1 /IT /LineArrow >>\nendobj\n"
            . "9 0 obj\n<< /Type /Annot /Subtype /Polygon /Rect [70 600 120 650] /Contents (Polygon note) /Vertices [70 620 90 640 110 620] /IC [0 0 1] /BE << /S /Cloudy /I 1.5 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Redact /Rect [70 560 170 585] /Contents (Redaction note) /OverlayText (Remove) /Repeat true /Q 1 /DA (/Helv 10 Tf 1 0 0 rg) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Markup body.'], $extractor->extractTextLines($pdf));
        $t->same(5, count($diagnostics['textAnnotations']));
        $t->same('Text', $diagnostics['textAnnotations'][0]['subtype']);
        $t->same('Sticky note', $diagnostics['textAnnotations'][0]['contents']);
        $t->same('Comment', $diagnostics['textAnnotations'][0]['iconName']);
        $t->same(true, $diagnostics['textAnnotations'][0]['open']);
        $t->same([1.0, 0.5, 0.0], $diagnostics['textAnnotations'][0]['color']);
        $t->same(36, $diagnostics['textAnnotations'][0]['flags']);
        $t->same(['print', 'noView'], $diagnostics['textAnnotations'][0]['flagNames']);
        $t->same(0.75, $diagnostics['textAnnotations'][0]['opacity']);
        $t->same('D:20260619130000Z', $diagnostics['textAnnotations'][0]['created']);
        $t->same('Accepted', $diagnostics['textAnnotations'][0]['state']);
        $t->same('Review', $diagnostics['textAnnotations'][0]['stateModel']);
        $t->same('Ink', $diagnostics['textAnnotations'][1]['subtype']);
        $t->same([[70.0, 700.0, 80.0, 710.0, 90.0, 705.0], [100.0, 700.0, 110.0, 708.0]], $diagnostics['textAnnotations'][1]['inkList']);
        $t->same([0.0, 0.0, 2.0], $diagnostics['textAnnotations'][1]['border']);
        $t->same(2.0, $diagnostics['textAnnotations'][1]['borderStyle']['width']);
        $t->same('D', $diagnostics['textAnnotations'][1]['borderStyle']['style']);
        $t->same([3.0, 2.0], $diagnostics['textAnnotations'][1]['borderStyle']['dashPattern']);
        $t->same('Line', $diagnostics['textAnnotations'][2]['subtype']);
        $t->same([70.0, 660.0, 140.0, 680.0], $diagnostics['textAnnotations'][2]['line']);
        $t->same(['OpenArrow', 'ClosedArrow'], $diagnostics['textAnnotations'][2]['lineEndingStyles']);
        $t->same([0.0, 1.0, 0.0], $diagnostics['textAnnotations'][2]['interiorColor']);
        $t->same(true, $diagnostics['textAnnotations'][2]['caption']);
        $t->same(4.0, $diagnostics['textAnnotations'][2]['leaderLineLength']);
        $t->same(2.0, $diagnostics['textAnnotations'][2]['leaderLineExtension']);
        $t->same(1.0, $diagnostics['textAnnotations'][2]['leaderLineOffset']);
        $t->same('LineArrow', $diagnostics['textAnnotations'][2]['intent']);
        $t->same('Polygon', $diagnostics['textAnnotations'][3]['subtype']);
        $t->same([70.0, 620.0, 90.0, 640.0, 110.0, 620.0], $diagnostics['textAnnotations'][3]['vertices']);
        $t->same([0.0, 0.0, 1.0], $diagnostics['textAnnotations'][3]['interiorColor']);
        $t->same('Cloudy', $diagnostics['textAnnotations'][3]['borderEffect']['style']);
        $t->same(1.5, $diagnostics['textAnnotations'][3]['borderEffect']['intensity']);
        $t->same('Redact', $diagnostics['textAnnotations'][4]['subtype']);
        $t->same('Remove', $diagnostics['textAnnotations'][4]['overlayText']);
        $t->same(true, $diagnostics['textAnnotations'][4]['repeat']);
        $t->same(1, $diagnostics['textAnnotations'][4]['quadding']);
        $t->same('/Helv 10 Tf 1 0 0 rg', $diagnostics['textAnnotations'][4]['defaultAppearance']);
        $t->same([], $diagnostics['linkAnnotations']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records common markup subtype geometry metadata without injecting comments into page text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Variant body.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R 13 0 R 14 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Underline /Rect [70 716 190 735] /QuadPoints [70 735 190 735 70 716 190 716] /Contents (Underline note) /C [1 0 0] /CA 0.6 >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Squiggly /Rect [70 690 190 710] /QuadPoints [70 710 190 710 70 690 190 690] /Contents (Squiggly note) /IRT 6 0 R /RT /Group /Popup 14 0 R /F 4 >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /StrikeOut /Rect [70 665 190 685] /QuadPoints [70 685 190 685 70 665 190 665] /Contents (Strike note) /StructParent 2 >>\nendobj\n"
            . "9 0 obj\n<< /Type /Annot /Subtype /Square /Rect [70 625 150 660] /Contents (Square note) /RD [2 3 4 5] /IC [0.9 0.9 0.1] /BE << /S /Cloudy /I 2 >> /BS << /W 1.5 /S /D /D [2 1] >> /Rotate 90 >>\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Circle /Rect [170 625 240 660] /Contents (Circle note) /RD [1 1 1 1] /Border [0 0 1] >>\nendobj\n"
            . "11 0 obj\n<< /Type /Annot /Subtype /PolyLine /Rect [70 585 150 620] /Contents (Polyline note) /Vertices [70 600 90 620 110 600 130 620] /LE [/None /OpenArrow] /IT /PolyLineDimension >>\nendobj\n"
            . "12 0 obj\n<< /Type /Annot /Subtype /Stamp /Rect [70 545 150 575] /Contents (Stamp note) /Name /Approved /StructParents 4 >>\nendobj\n"
            . "13 0 obj\n<< /Type /Annot /Subtype /Caret /Rect [170 545 190 575] /Contents (Caret note) /Sy /P /RD [0 0 5 0] >>\nendobj\n"
            . "14 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [220 650 360 720] /Parent 7 0 R /Open false >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Variant body.'], $extractor->extractTextLines($pdf));
        $t->same(8, count($diagnostics['textAnnotations']));
        $t->same('Underline', $diagnostics['textAnnotations'][0]['subtype']);
        $t->same([70.0, 735.0, 190.0, 735.0, 70.0, 716.0, 190.0, 716.0], $diagnostics['textAnnotations'][0]['quadPoints']);
        $t->same([1.0, 0.0, 0.0], $diagnostics['textAnnotations'][0]['color']);
        $t->same(0.6, $diagnostics['textAnnotations'][0]['opacity']);
        $t->same('Squiggly', $diagnostics['textAnnotations'][1]['subtype']);
        $t->same(6, $diagnostics['textAnnotations'][1]['inReplyToAnnotationObject']);
        $t->same('Group', $diagnostics['textAnnotations'][1]['replyType']);
        $t->same(14, $diagnostics['textAnnotations'][1]['popupAnnotationObject']);
        $t->same(['print'], $diagnostics['textAnnotations'][1]['flagNames']);
        $t->same('StrikeOut', $diagnostics['textAnnotations'][2]['subtype']);
        $t->same(2, $diagnostics['textAnnotations'][2]['structParent']);
        $t->same('Square', $diagnostics['textAnnotations'][3]['subtype']);
        $t->same([2.0, 3.0, 4.0, 5.0], $diagnostics['textAnnotations'][3]['rectDifferences']);
        $t->same([0.9, 0.9, 0.1], $diagnostics['textAnnotations'][3]['interiorColor']);
        $t->same('Cloudy', $diagnostics['textAnnotations'][3]['borderEffect']['style']);
        $t->same(2.0, $diagnostics['textAnnotations'][3]['borderEffect']['intensity']);
        $t->same(1.5, $diagnostics['textAnnotations'][3]['borderStyle']['width']);
        $t->same('D', $diagnostics['textAnnotations'][3]['borderStyle']['style']);
        $t->same([2.0, 1.0], $diagnostics['textAnnotations'][3]['borderStyle']['dashPattern']);
        $t->same(90, $diagnostics['textAnnotations'][3]['rotation']);
        $t->same('Circle', $diagnostics['textAnnotations'][4]['subtype']);
        $t->same([1.0, 1.0, 1.0, 1.0], $diagnostics['textAnnotations'][4]['rectDifferences']);
        $t->same([0.0, 0.0, 1.0], $diagnostics['textAnnotations'][4]['border']);
        $t->same('PolyLine', $diagnostics['textAnnotations'][5]['subtype']);
        $t->same([70.0, 600.0, 90.0, 620.0, 110.0, 600.0, 130.0, 620.0], $diagnostics['textAnnotations'][5]['vertices']);
        $t->same(['None', 'OpenArrow'], $diagnostics['textAnnotations'][5]['lineEndingStyles']);
        $t->same('PolyLineDimension', $diagnostics['textAnnotations'][5]['intent']);
        $t->same('Stamp', $diagnostics['textAnnotations'][6]['subtype']);
        $t->same('Approved', $diagnostics['textAnnotations'][6]['iconName']);
        $t->same(4, $diagnostics['textAnnotations'][6]['structParents']);
        $t->same('Caret', $diagnostics['textAnnotations'][7]['subtype']);
        $t->same('P', $diagnostics['textAnnotations'][7]['symbol']);
        $t->same([0.0, 0.0, 5.0, 0.0], $diagnostics['textAnnotations'][7]['rectDifferences']);
        $t->same(1, count($diagnostics['popupAnnotations']));
        $t->same(7, $diagnostics['popupAnnotations'][0]['parentAnnotationObject']);
        $t->same('Squiggly', $diagnostics['popupAnnotations'][0]['parentSubtype']);
        foreach (['Underline note', 'Squiggly note', 'Strike note', 'Square note', 'Circle note', 'Polyline note', 'Stamp note', 'Caret note'] as $annotationText) {
            $t->true(!in_array($annotationText, $extractor->extractTextLines($pdf), true));
        }
        $t->same([], $diagnostics['linkAnnotations']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records file attachment and popup annotations without injecting them into page text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Attachment body.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [70 716 92 738] /Contents (Attachment note) /T (Reviewer) /M (D:20260619123000Z) /NM (attach-1) /Subj (Source file) /Name /Paperclip /FS << /F (report.csv) /UF (report-unicode.csv) /Desc (Source spreadsheet) >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Popup /Rect [100 650 260 720] /Parent 6 0 R /Open true >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Attachment body.'], $extractor->extractTextLines($pdf));
        $t->same([], $diagnostics['textAnnotations']);
        $t->same([], $diagnostics['linkAnnotations']);
        $t->same(1, count($diagnostics['fileAttachmentAnnotations']));
        $t->same(6, $diagnostics['fileAttachmentAnnotations'][0]['annotationObject']);
        $t->same('FileAttachment', $diagnostics['fileAttachmentAnnotations'][0]['subtype']);
        $t->same('Attachment note', $diagnostics['fileAttachmentAnnotations'][0]['contents']);
        $t->same('Reviewer', $diagnostics['fileAttachmentAnnotations'][0]['title']);
        $t->same('D:20260619123000Z', $diagnostics['fileAttachmentAnnotations'][0]['modified']);
        $t->same('attach-1', $diagnostics['fileAttachmentAnnotations'][0]['name']);
        $t->same('Source file', $diagnostics['fileAttachmentAnnotations'][0]['subject']);
        $t->same('Paperclip', $diagnostics['fileAttachmentAnnotations'][0]['iconName']);
        $t->same([70.0, 716.0, 92.0, 738.0], $diagnostics['fileAttachmentAnnotations'][0]['rect']);
        $t->same('report-unicode.csv', $diagnostics['fileAttachmentAnnotations'][0]['file']);
        $t->same('report.csv', $diagnostics['fileAttachmentAnnotations'][0]['fileSpecification']['portableFile']);
        $t->same('report-unicode.csv', $diagnostics['fileAttachmentAnnotations'][0]['fileSpecification']['unicodeFile']);
        $t->same('Source spreadsheet', $diagnostics['fileAttachmentAnnotations'][0]['fileSpecification']['description']);
        $t->same(1, count($diagnostics['popupAnnotations']));
        $t->same(7, $diagnostics['popupAnnotations'][0]['annotationObject']);
        $t->same('Popup', $diagnostics['popupAnnotations'][0]['subtype']);
        $t->same([100.0, 650.0, 260.0, 720.0], $diagnostics['popupAnnotations'][0]['rect']);
        $t->same(true, $diagnostics['popupAnnotations'][0]['open']);
        $t->same(6, $diagnostics['popupAnnotations'][0]['parentAnnotationObject']);
        $t->same('FileAttachment', $diagnostics['popupAnnotations'][0]['parentSubtype']);
        $t->same('Attachment note', $diagnostics['popupAnnotations'][0]['parentContents']);
        $t->same('Reviewer', $diagnostics['popupAnnotations'][0]['parentTitle']);
        $t->same('attach-1', $diagnostics['popupAnnotations'][0]['parentName']);
        $t->same('D:20260619123000Z', $diagnostics['popupAnnotations'][0]['parentModified']);
        $t->same('Source file', $diagnostics['popupAnnotations'][0]['parentSubject']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records annotation appearance streams without injecting them into page text' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Appearance body.) Tj ET';
        $normalAppearance = 'BT /F1 12 Tf 0 10 Td (Normal appearance) Tj ET';
        $normalAppearanceCompressed = gzcompress($normalAppearance);
        $downAppearance = 'BT /F1 12 Tf 0 10 Td (Down Yes appearance) Tj ET';
        $offAppearance = 'BT /F1 12 Tf 0 10 Td (Off appearance) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [70 716 170 738] /Contents (Widget note) /T (Approval) /AS /Yes /AP << /N 8 0 R /D << /Yes 9 0 R /Off 10 0 R >> >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 24] /Matrix [1 0 0 1 0 0] /Resources << /Font << /F1 4 0 R >> >> /Filter /FlateDecode /Length " . strlen($normalAppearanceCompressed) . " >>\nstream\n{$normalAppearanceCompressed}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 24] /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($downAppearance) . " >>\nstream\n{$downAppearance}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 24] /Resources << /Font << /F1 4 0 R >> >> /Length " . strlen($offAppearance) . " >>\nstream\n{$offAppearance}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Appearance body.'], $extractor->extractTextLines($pdf));
        $t->same(1, count($diagnostics['appearanceAnnotations']));
        $t->same(6, $diagnostics['appearanceAnnotations'][0]['annotationObject']);
        $t->same('Widget', $diagnostics['appearanceAnnotations'][0]['subtype']);
        $t->same('Yes', $diagnostics['appearanceAnnotations'][0]['appearanceState']);
        $t->same('Widget note', $diagnostics['appearanceAnnotations'][0]['contents']);
        $t->same('Approval', $diagnostics['appearanceAnnotations'][0]['title']);
        $t->same([70.0, 716.0, 170.0, 738.0], $diagnostics['appearanceAnnotations'][0]['rect']);
        $t->same(3, count($diagnostics['appearanceAnnotations'][0]['appearanceStreams']));
        $t->same('N', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['appearanceKey']);
        $t->same('normal', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['appearanceType']);
        $t->same(8, $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['objectNumber']);
        $t->same('XObject', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['type']);
        $t->same('Form', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['subtype']);
        $t->same(['FlateDecode'], $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['filters']);
        $t->same(true, $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['decoded']);
        $t->same(strlen($normalAppearance), $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['decodedBytes']);
        $t->same([0.0, 0.0, 100.0, 24.0], $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['bbox']);
        $t->same([1.0, 0.0, 0.0, 1.0, 0.0, 0.0], $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['matrix']);
        $t->same(['Normal appearance'], $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][0]['textLines']);
        $t->same('D', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][1]['appearanceKey']);
        $t->same('down', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][1]['appearanceType']);
        $t->same('Yes', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][1]['state']);
        $t->same('Down Yes appearance', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][1]['text']);
        $t->same('Off', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][2]['state']);
        $t->same('Off appearance', $diagnostics['appearanceAnnotations'][0]['appearanceStreams'][2]['text']);
        $t->same([], $diagnostics['linkAnnotations']);
        $t->same([], $diagnostics['textAnnotations']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records remote and launch link actions without safe apply uris' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Remote manual.) Tj 0 -16 Td (Launch installer.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 170 735] /A << /S /GoToR /F << /F (docs/manual.pdf) /UF (docs/manual-unicode.pdf) >> /D [2 /FitH 720] /NewWindow true >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 182 716] /A << /S /Launch /F 8 0 R /Win << /F (installer.exe) /P (/quiet) /D (C:/Downloads) /O (open) >> >> >>\nendobj\n"
            . "8 0 obj\n(installer.exe)\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Remote manual.', 'Launch installer.'], $extractor->extractTextLines($pdf));
        $t->same(2, count($diagnostics['linkAnnotations']));
        $t->same('gotor', $diagnostics['linkAnnotations'][0]['kind']);
        $t->same('', $diagnostics['linkAnnotations'][0]['uri']);
        $t->same(false, $diagnostics['linkAnnotations'][0]['safeToApply']);
        $t->same('Remote manual.', $diagnostics['linkAnnotations'][0]['text']);
        $t->same('docs/manual-unicode.pdf', $diagnostics['linkAnnotations'][0]['remoteFile']);
        $t->same('docs/manual.pdf', $diagnostics['linkAnnotations'][0]['fileSpecification']['portableFile']);
        $t->same('docs/manual-unicode.pdf', $diagnostics['linkAnnotations'][0]['fileSpecification']['unicodeFile']);
        $t->same(3, $diagnostics['linkAnnotations'][0]['remotePage']);
        $t->same('FitH', $diagnostics['linkAnnotations'][0]['destinationType']);
        $t->same(true, $diagnostics['linkAnnotations'][0]['newWindow']);
        $t->same('launch', $diagnostics['linkAnnotations'][1]['kind']);
        $t->same('', $diagnostics['linkAnnotations'][1]['uri']);
        $t->same(false, $diagnostics['linkAnnotations'][1]['safeToApply']);
        $t->same('Launch installer.', $diagnostics['linkAnnotations'][1]['text']);
        $t->same('installer.exe', $diagnostics['linkAnnotations'][1]['launchFile']);
        $t->same('installer.exe', $diagnostics['linkAnnotations'][1]['fileSpecification']['portableFile']);
        $t->same('/quiet', $diagnostics['linkAnnotations'][1]['launchParameters']);
        $t->same('C:/Downloads', $diagnostics['linkAnnotations'][1]['launchDirectory']);
        $t->same('open', $diagnostics['linkAnnotations'][1]['launchOperation']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records broader report only link actions without safe apply uris' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Named action.) Tj 0 -16 Td (Script action.) Tj 0 -16 Td (Submit form.) Tj 0 -16 Td (Reset form.) Tj 0 -16 Td (Import data.) Tj 0 -16 Td (Hide controls.) Tj ET';
        $javascript = 'app.alert("Review");';
        $javascriptCompressed = gzcompress($javascript);
        if (!is_string($javascriptCompressed)) {
            throw new RuntimeException('Could not build compressed JavaScript fixture.');
        }
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 180 735] /A << /S /Named /N /NextPage >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 180 716] /A << /S /JavaScript /JS 12 0 R /Next << /S /Named /N /Print >> >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 684 180 700] /A << /S /SubmitForm /F << /F (https://example.test/post) /UF (https://example.test/post-unicode) /Desc (Form endpoint) >> /Fields [13 0 R (email)] /Flags 4 >> >>\nendobj\n"
            . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 668 180 684] /A << /S /ResetForm /Fields [(customer-name) 13 0 R] /Flags 1 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 652 180 668] /A << /S /ImportData /F (data.fdf) >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 636 180 652] /A << /S /Hide /T [(customer-field) 13 0 R] /H false >> >>\nendobj\n"
            . "12 0 obj\n<< /Filter /FlateDecode /Length " . strlen($javascriptCompressed) . " >>\nstream\n{$javascriptCompressed}\nendstream\nendobj\n"
            . "13 0 obj\n<< /Subtype /Widget /T (customer-name) /FT /Tx /NM (customer-field) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Named action.', 'Script action.', 'Submit form.', 'Reset form.', 'Import data.', 'Hide controls.'], $extractor->extractTextLines($pdf));
        $t->same(6, count($diagnostics['linkAnnotations']));
        $t->same(['named', 'javascript', 'submitForm', 'resetForm', 'importData', 'hide'], array_column($diagnostics['linkAnnotations'], 'kind'));
        foreach ($diagnostics['linkAnnotations'] as $annotation) {
            $t->same('', $annotation['uri']);
            $t->same(false, $annotation['safeToApply']);
        }
        $t->same('Named', $diagnostics['linkAnnotations'][0]['action']);
        $t->same('NextPage', $diagnostics['linkAnnotations'][0]['namedAction']);
        $t->same('Named action.', $diagnostics['linkAnnotations'][0]['text']);
        $t->same('JavaScript', $diagnostics['linkAnnotations'][1]['action']);
        $t->same($javascript, $diagnostics['linkAnnotations'][1]['javascript']);
        $t->same(12, $diagnostics['linkAnnotations'][1]['javascriptObject']);
        $t->same(true, $diagnostics['linkAnnotations'][1]['javascriptDecoded']);
        $t->same(['FlateDecode'], $diagnostics['linkAnnotations'][1]['javascriptFilters']);
        $t->same(1, count($diagnostics['linkAnnotations'][1]['nextActions']));
        $t->same('Named', $diagnostics['linkAnnotations'][1]['nextActions'][0]['action']);
        $t->same('Print', $diagnostics['linkAnnotations'][1]['nextActions'][0]['namedAction']);
        $t->same('SubmitForm', $diagnostics['linkAnnotations'][2]['action']);
        $t->same('https://example.test/post-unicode', $diagnostics['linkAnnotations'][2]['submitFile']);
        $t->same('https://example.test/post', $diagnostics['linkAnnotations'][2]['fileSpecification']['portableFile']);
        $t->same('Form endpoint', $diagnostics['linkAnnotations'][2]['fileSpecification']['description']);
        $t->same(4, $diagnostics['linkAnnotations'][2]['flags']);
        $t->same(13, $diagnostics['linkAnnotations'][2]['fields'][0]['objectNumber']);
        $t->same('customer-name', $diagnostics['linkAnnotations'][2]['fields'][0]['field']);
        $t->same('customer-field', $diagnostics['linkAnnotations'][2]['fields'][0]['name']);
        $t->same('Widget', $diagnostics['linkAnnotations'][2]['fields'][0]['subtype']);
        $t->same('Tx', $diagnostics['linkAnnotations'][2]['fields'][0]['fieldType']);
        $t->same('email', $diagnostics['linkAnnotations'][2]['fields'][1]['field']);
        $t->same('ResetForm', $diagnostics['linkAnnotations'][3]['action']);
        $t->same(1, $diagnostics['linkAnnotations'][3]['flags']);
        $t->same('customer-name', $diagnostics['linkAnnotations'][3]['fields'][0]['field']);
        $t->same(13, $diagnostics['linkAnnotations'][3]['fields'][1]['objectNumber']);
        $t->same('ImportData', $diagnostics['linkAnnotations'][4]['action']);
        $t->same('data.fdf', $diagnostics['linkAnnotations'][4]['importFile']);
        $t->same('data.fdf', $diagnostics['linkAnnotations'][4]['fileSpecification']['portableFile']);
        $t->same('Hide', $diagnostics['linkAnnotations'][5]['action']);
        $t->same(false, $diagnostics['linkAnnotations'][5]['hide']);
        $t->same('customer-field', $diagnostics['linkAnnotations'][5]['targets'][0]['target']);
        $t->same(13, $diagnostics['linkAnnotations'][5]['targets'][1]['objectNumber']);
        $t->same('customer-name', $diagnostics['linkAnnotations'][5]['targets'][1]['field']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records annotation additional actions as inert metadata' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Docs link.) Tj 0 -16 Td (Extra trigger.) Tj ET';
        $enterJavascript = 'app.alert("hover");';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 160 735] /A << /S /URI /URI (https://example.test/docs) >> /AA << /E << /S /JavaScript /JS <" . bin2hex($enterJavascript) . "> >> /X 12 0 R >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 170 716] /AA << /U << /S /SubmitForm /F (https://example.test/post) /Fields [13 0 R (email)] /Flags 8 >> /Bl << /S /ResetForm /Fields [(email)] /Flags 1 >> >> >>\nendobj\n"
            . "12 0 obj\n<< /S /Named /N /GoBack >>\nendobj\n"
            . "13 0 obj\n<< /Subtype /Widget /T (customer-name) /FT /Tx /NM (customer-field) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Docs link.', 'Extra trigger.'], $extractor->extractTextLines($pdf));
        $t->same(2, count($diagnostics['linkAnnotations']));
        $t->same('uri', $diagnostics['linkAnnotations'][0]['kind']);
        $t->same('https://example.test/docs', $diagnostics['linkAnnotations'][0]['uri']);
        $t->same('Docs link.', $diagnostics['linkAnnotations'][0]['text']);
        $t->same(2, count($diagnostics['linkAnnotations'][0]['additionalActions']));
        $t->same('E', $diagnostics['linkAnnotations'][0]['additionalActions'][0]['trigger']);
        $t->same('enter', $diagnostics['linkAnnotations'][0]['additionalActions'][0]['event']);
        $t->same('JavaScript', $diagnostics['linkAnnotations'][0]['additionalActions'][0]['action']);
        $t->same($enterJavascript, $diagnostics['linkAnnotations'][0]['additionalActions'][0]['javascript']);
        $t->same('X', $diagnostics['linkAnnotations'][0]['additionalActions'][1]['trigger']);
        $t->same('exit', $diagnostics['linkAnnotations'][0]['additionalActions'][1]['event']);
        $t->same('Named', $diagnostics['linkAnnotations'][0]['additionalActions'][1]['action']);
        $t->same(12, $diagnostics['linkAnnotations'][0]['additionalActions'][1]['actionObject']);
        $t->same('GoBack', $diagnostics['linkAnnotations'][0]['additionalActions'][1]['namedAction']);
        $t->same('additionalActions', $diagnostics['linkAnnotations'][1]['kind']);
        $t->same('', $diagnostics['linkAnnotations'][1]['uri']);
        $t->same(false, $diagnostics['linkAnnotations'][1]['safeToApply']);
        $t->same('AdditionalActions', $diagnostics['linkAnnotations'][1]['action']);
        $t->same('Extra trigger.', $diagnostics['linkAnnotations'][1]['text']);
        $t->same(2, count($diagnostics['linkAnnotations'][1]['additionalActions']));
        $t->same('U', $diagnostics['linkAnnotations'][1]['additionalActions'][0]['trigger']);
        $t->same('mouseUp', $diagnostics['linkAnnotations'][1]['additionalActions'][0]['event']);
        $t->same('SubmitForm', $diagnostics['linkAnnotations'][1]['additionalActions'][0]['action']);
        $t->same('https://example.test/post', $diagnostics['linkAnnotations'][1]['additionalActions'][0]['fileSpecification']['portableFile']);
        $t->same(8, $diagnostics['linkAnnotations'][1]['additionalActions'][0]['flags']);
        $t->same(13, $diagnostics['linkAnnotations'][1]['additionalActions'][0]['fields'][0]['objectNumber']);
        $t->same('customer-name', $diagnostics['linkAnnotations'][1]['additionalActions'][0]['fields'][0]['field']);
        $t->same('email', $diagnostics['linkAnnotations'][1]['additionalActions'][0]['fields'][1]['field']);
        $t->same('Bl', $diagnostics['linkAnnotations'][1]['additionalActions'][1]['trigger']);
        $t->same('blur', $diagnostics['linkAnnotations'][1]['additionalActions'][1]['event']);
        $t->same('ResetForm', $diagnostics['linkAnnotations'][1]['additionalActions'][1]['action']);
        $t->same(1, $diagnostics['linkAnnotations'][1]['additionalActions'][1]['flags']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records additional standard report only link actions without safe apply uris' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Embedded jump.) Tj 0 -16 Td (Thread jump.) Tj 0 -16 Td (Sound cue.) Tj 0 -16 Td (Movie action.) Tj 0 -16 Td (Rendition action.) Tj 0 -16 Td (Transition action.) Tj 0 -16 Td (Layer toggle.) Tj 0 -16 Td (View action.) Tj 0 -16 Td (Rich media.) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R 7 0 R 8 0 R 9 0 R 10 0 R 11 0 R 12 0 R 13 0 R 14 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 180 735] /A << /S /GoToE /F << /F (embedded.pdf) /UF (embedded-unicode.pdf) >> /D [0 /Fit] /T << /R /C /N (chapter.pdf) >> /NewWindow false >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 700 170 716] /A << /S /Thread /D 15 0 R /B 16 0 R >> >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 684 150 700] /A << /S /Sound /Sound 17 0 R /Volume 0.5 /Synchronous true /Repeat false /Mix true >> >>\nendobj\n"
            . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 668 170 684] /A << /S /Movie /Annotation 18 0 R /Operation /Play >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 652 190 668] /A << /S /Rendition /OP 0 /R 19 0 R /AN 20 0 R /JS (reviewRendition()) >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 636 190 652] /A << /S /Trans /Trans << /S /Dissolve /D 1.5 /Dm /H /M /I >> >> >>\nendobj\n"
            . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 620 170 636] /A << /S /SetOCGState /State [/ON 21 0 R /Toggle 22 0 R /OFF (Layer A)] /PreserveRB false >> >>\nendobj\n"
            . "13 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 604 160 620] /A << /S /GoTo3DView /TA 23 0 R /V /DefaultView >> >>\nendobj\n"
            . "14 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 588 160 604] /A << /S /RichMediaExecute /TA 24 0 R /CMD << /C (play) /Args [(scene)] >> >> >>\nendobj\n"
            . "15 0 obj\n<< /Type /Thread /I (article-thread) >>\nendobj\n"
            . "16 0 obj\n<< /Type /Bead /T 15 0 R >>\nendobj\n"
            . "17 0 obj\n<< /R 44100 /C 2 /B 16 /E /Signed >>\nendobj\n"
            . "18 0 obj\n<< /Subtype /Movie /T (Trailer clip) >>\nendobj\n"
            . "19 0 obj\n<< /S /MR /N (Rendition Name) >>\nendobj\n"
            . "20 0 obj\n<< /Subtype /Screen /NM (screen-one) >>\nendobj\n"
            . "21 0 obj\n<< /Type /OCG /Name (Layer One) >>\nendobj\n"
            . "22 0 obj\n<< /Type /OCG /Name (Layer Two) >>\nendobj\n"
            . "23 0 obj\n<< /Subtype /3D /NM (model) >>\nendobj\n"
            . "24 0 obj\n<< /Subtype /RichMedia /NM (rich-media) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Embedded jump.', 'Thread jump.', 'Sound cue.', 'Movie action.', 'Rendition action.', 'Transition action.', 'Layer toggle.', 'View action.', 'Rich media.'], $extractor->extractTextLines($pdf));
        $t->same(9, count($diagnostics['linkAnnotations']));
        $t->same(['gotoe', 'thread', 'sound', 'movie', 'rendition', 'transition', 'setOCGState', 'goto3DView', 'richMediaExecute'], array_column($diagnostics['linkAnnotations'], 'kind'));
        foreach ($diagnostics['linkAnnotations'] as $annotation) {
            $t->same('', $annotation['uri']);
            $t->same(false, $annotation['safeToApply']);
        }
        $t->same('GoToE', $diagnostics['linkAnnotations'][0]['action']);
        $t->same('embedded-unicode.pdf', $diagnostics['linkAnnotations'][0]['embeddedFile']);
        $t->same('embedded.pdf', $diagnostics['linkAnnotations'][0]['fileSpecification']['portableFile']);
        $t->same('[0 /Fit]', $diagnostics['linkAnnotations'][0]['destination']);
        $t->same('C', $diagnostics['linkAnnotations'][0]['target']['relationship']);
        $t->same('chapter.pdf', $diagnostics['linkAnnotations'][0]['target']['name']);
        $t->same(false, $diagnostics['linkAnnotations'][0]['newWindow']);
        $t->same('Thread', $diagnostics['linkAnnotations'][1]['action']);
        $t->same(15, $diagnostics['linkAnnotations'][1]['threads'][0]['objectNumber']);
        $t->same(16, $diagnostics['linkAnnotations'][1]['beads'][0]['objectNumber']);
        $t->same('Sound', $diagnostics['linkAnnotations'][2]['action']);
        $t->same(17, $diagnostics['linkAnnotations'][2]['sound']['objectNumber']);
        $t->same(44100.0, $diagnostics['linkAnnotations'][2]['sound']['rate']);
        $t->same(2.0, $diagnostics['linkAnnotations'][2]['sound']['channels']);
        $t->same(16.0, $diagnostics['linkAnnotations'][2]['sound']['bitsPerSample']);
        $t->same('Signed', $diagnostics['linkAnnotations'][2]['sound']['encoding']);
        $t->same(0.5, $diagnostics['linkAnnotations'][2]['volume']);
        $t->same(true, $diagnostics['linkAnnotations'][2]['synchronous']);
        $t->same(false, $diagnostics['linkAnnotations'][2]['repeat']);
        $t->same(true, $diagnostics['linkAnnotations'][2]['mix']);
        $t->same('Movie', $diagnostics['linkAnnotations'][3]['action']);
        $t->same(18, $diagnostics['linkAnnotations'][3]['annotations'][0]['objectNumber']);
        $t->same('Movie', $diagnostics['linkAnnotations'][3]['annotations'][0]['subtype']);
        $t->same('Play', $diagnostics['linkAnnotations'][3]['operation']);
        $t->same('Rendition', $diagnostics['linkAnnotations'][4]['action']);
        $t->same(0, $diagnostics['linkAnnotations'][4]['operation']);
        $t->same(19, $diagnostics['linkAnnotations'][4]['renditions'][0]['objectNumber']);
        $t->same(20, $diagnostics['linkAnnotations'][4]['annotations'][0]['objectNumber']);
        $t->same('screen-one', $diagnostics['linkAnnotations'][4]['annotations'][0]['name']);
        $t->same('reviewRendition()', $diagnostics['linkAnnotations'][4]['javascript']);
        $t->same('Trans', $diagnostics['linkAnnotations'][5]['action']);
        $t->same('Dissolve', $diagnostics['linkAnnotations'][5]['transition']['style']);
        $t->same(1.5, $diagnostics['linkAnnotations'][5]['transition']['duration']);
        $t->same('H', $diagnostics['linkAnnotations'][5]['transition']['dimension']);
        $t->same('I', $diagnostics['linkAnnotations'][5]['transition']['motion']);
        $t->same('SetOCGState', $diagnostics['linkAnnotations'][6]['action']);
        $t->same(false, $diagnostics['linkAnnotations'][6]['preserveRadioButtonState']);
        $t->same('ON', $diagnostics['linkAnnotations'][6]['state'][0]['command']);
        $t->same('Layer One', $diagnostics['linkAnnotations'][6]['state'][1]['name']);
        $t->same('Toggle', $diagnostics['linkAnnotations'][6]['state'][2]['command']);
        $t->same('Layer Two', $diagnostics['linkAnnotations'][6]['state'][3]['name']);
        $t->same('OFF', $diagnostics['linkAnnotations'][6]['state'][4]['command']);
        $t->same('Layer A', $diagnostics['linkAnnotations'][6]['state'][5]['name']);
        $t->same('GoTo3DView', $diagnostics['linkAnnotations'][7]['action']);
        $t->same('model', $diagnostics['linkAnnotations'][7]['targetAnnotations'][0]['name']);
        $t->same('DefaultView', $diagnostics['linkAnnotations'][7]['view']);
        $t->same('RichMediaExecute', $diagnostics['linkAnnotations'][8]['action']);
        $t->same('rich-media', $diagnostics['linkAnnotations'][8]['targetAnnotations'][0]['name']);
        $t->same('play', $diagnostics['linkAnnotations'][8]['command']['command']);
        $t->same(['scene'], $diagnostics['linkAnnotations'][8]['command']['arguments']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records internal goto link annotations matched to target pages' => static function (TestRunner $t): void {
        $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Jump to Details) Tj ET';
        $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Details paragraph) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 7 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 163 735] /A << /S /GoTo /D [7 0 R /Fit] >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Jump to Details', 'Details paragraph'], $extractor->extractTextLines($pdf));
        $t->same(1, count($diagnostics['linkAnnotations']));
        $t->same(1, $diagnostics['linkAnnotations'][0]['page']);
        $t->same(3, $diagnostics['linkAnnotations'][0]['pageObject']);
        $t->same(6, $diagnostics['linkAnnotations'][0]['annotationObject']);
        $t->same('goto', $diagnostics['linkAnnotations'][0]['kind']);
        $t->same('#pdf-page-2', $diagnostics['linkAnnotations'][0]['uri']);
        $t->same('Jump to Details', $diagnostics['linkAnnotations'][0]['text']);
        $t->same(2, $diagnostics['linkAnnotations'][0]['targetPage']);
        $t->same(7, $diagnostics['linkAnnotations'][0]['targetPageObject']);
        $t->same('Fit', $diagnostics['linkAnnotations'][0]['destinationType']);
        $t->same([70.0, 716.0, 163.0, 735.0], $diagnostics['linkAnnotations'][0]['rect']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'records named destination link annotations matched to target pages' => static function (TestRunner $t): void {
        $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Named Details) Tj ET';
        $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Named destination target) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests << /Names [(details-dest) [7 0 R /FitH 720]] >> >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 7 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R /Annots [6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Annot /Subtype /Link /Rect [70 716 153 735] /Dest (details-dest) >>\nendobj\n"
            . "7 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Named Details', 'Named destination target'], $extractor->extractTextLines($pdf));
        $t->same(1, count($diagnostics['linkAnnotations']));
        $t->same('goto', $diagnostics['linkAnnotations'][0]['kind']);
        $t->same('#pdf-page-2', $diagnostics['linkAnnotations'][0]['uri']);
        $t->same('Named Details', $diagnostics['linkAnnotations'][0]['text']);
        $t->same(2, $diagnostics['linkAnnotations'][0]['targetPage']);
        $t->same(7, $diagnostics['linkAnnotations'][0]['targetPageObject']);
        $t->same('FitH', $diagnostics['linkAnnotations'][0]['destinationType']);
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'uses direct ParentTree Kids MCID ActualText instead of custom glyph bytes' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 5 /Resources << /Font << /F1 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree << /Limits [4 5] /Kids [ << /Limits [4 4] /Nums [ 4 [ 11 0 R ] ] >> << /Limits [5 5] /Nums [ 5 [ 9 0 R 10 0 R ] ] >> ] >> >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Direct Kid Tagged Text) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Second Direct Kid Line) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Wrong Parent Key) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Direct Kid Tagged Text', 'Second Direct Kid Line'], $extractor->extractTextLines($pdf));
        $t->same(['Direct Kid Tagged Text', 'Second Direct Kid Line'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
        $t->true(!str_contains($plainText, 'Wrong Parent Key'));
    },
    'uses indirect ParentTree Kids Limits and Nums arrays for MCID ActualText lookup' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 5 /Resources << /Font << /F1 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Kids 14 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Indirect Nums Tagged Text) >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Second Indirect Nums Line) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Wrong Indirect Parent Key) >>\nendobj\n"
            . "14 0 obj\n[ 15 0 R 16 0 R ]\nendobj\n"
            . "15 0 obj\n<< /Limits 17 0 R /Nums 18 0 R >>\nendobj\n"
            . "16 0 obj\n<< /Limits 19 0 R /Nums 20 0 R >>\nendobj\n"
            . "17 0 obj\n[4 4]\nendobj\n"
            . "18 0 obj\n[4 [ 11 0 R ]]\nendobj\n"
            . "19 0 obj\n[5 5]\nendobj\n"
            . "20 0 obj\n[5 [ 9 0 R 10 0 R ]]\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Indirect Nums Tagged Text', 'Second Indirect Nums Line'], $extractor->extractTextLines($pdf));
        $t->same(['Indirect Nums Tagged Text', 'Second Indirect Nums Line'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
        $t->true(!str_contains($plainText, 'Wrong Indirect Parent Key'));
    },
    'uses indirect StructElem K arrays for ParentTree ActualText lookup' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 9 0 R ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Indirect K Array Tagged Text) /K 14 0 R >>\nendobj\n"
            . "14 0 obj\n[ << /Type /MCR /MCID 1 >> ]\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Indirect K Array Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Indirect K Array Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->same(1, substr_count($plainText, 'Indirect K Array Tagged Text'));
    },
    'uses indirect StructElem K integers for ParentTree ActualText lookup' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 9 0 R ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Indirect K Integer Tagged Text) /K 14 0 R >>\nendobj\n"
            . "14 0 obj\n1\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Indirect K Integer Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Indirect K Integer Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->same(1, substr_count($plainText, 'Indirect K Integer Tagged Text'));
    },
    'uses StructTreeRoot K page scoped ActualText when ParentTree is absent' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /K 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /StructElem /S /Document /K [ 9 0 R 10 0 R 11 0 R ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Pg 3 0 R /ActualText (Root K Integer Tagged Text) /K 0 >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Root K MCR Tagged Text) /K << /Type /MCR /Pg 3 0 R /MCID 1 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /Pg 12 0 R /ActualText (Wrong Page Tagged Text) /K 0 >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Root K Integer Tagged Text', 'Root K MCR Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Root K Integer Tagged Text', 'Root K MCR Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
        $t->true(!str_contains($plainText, 'Wrong Page Tagged Text'));
    },
    'uses indirect StructTreeRoot K arrays for page scoped ActualText fallback' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /K 8 0 R >>\nendobj\n"
            . "8 0 obj\n[ 9 0 R ]\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Pg 3 0 R /ActualText (Root Indirect K Array Tagged Text) /K 10 0 R >>\nendobj\n"
            . "10 0 obj\n[ << /Type /MCR /Pg 3 0 R /MCID 1 >> ]\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Root Indirect K Array Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Root Indirect K Array Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->same(1, substr_count($plainText, 'Root Indirect K Array Tagged Text'));
    },
    'uses indirect StructTreeRoot K integers for page scoped ActualText fallback' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 1 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /K 8 0 R >>\nendobj\n"
            . "8 0 obj\n[ 9 0 R ]\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Pg 3 0 R /ActualText (Root Indirect K Integer Tagged Text) /K 10 0 R >>\nendobj\n"
            . "10 0 obj\n1\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Root Indirect K Integer Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Root Indirect K Integer Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->same(1, substr_count($plainText, 'Root Indirect K Integer Tagged Text'));
    },
    'uses escaped StructTreeRoot catalog and type names for K ActualText fallback' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Struct#54reeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Struct#54reeRoot /K 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Type /StructElem /S /Span /Pg 3 0 R /ActualText (Escaped Root K Tagged Text) /K 0 >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Escaped Root K Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Escaped Root K Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->same(1, substr_count($plainText, 'Escaped Root K Tagged Text'));
    },
    'uses grouped StructElem ActualText for multiple MCIDs instead of custom glyph bytes' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText (Grouped Tagged Sentence) /K [ << /Type /MCR /MCID 0 >> << /Type /MCR /MCID 1 >> ] >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Grouped Tagged Sentence'], $extractor->extractTextLines($pdf));
        $t->same(['Grouped Tagged Sentence'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
        $t->same(1, substr_count($plainText, 'Grouped Tagged Sentence'));
    },
    'uses nested StructElem descendant ActualText instead of custom glyph bytes' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 9 0 R ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Sect /K [ 10 0 R 11 0 R ] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /P /ActualText (Nested Tagged First) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /P /K 12 0 R >>\nendobj\n"
            . "12 0 obj\n<< /Type /StructElem /S /Span /ActualText (Nested Tagged Second) /K [ << /Type /MCR /MCID 1 >> ] >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Nested Tagged First', 'Nested Tagged Second'], $extractor->extractTextLines($pdf));
        $t->same(['Nested Tagged First', 'Nested Tagged Second'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'uses StructElem Alt and E fallbacks when ActualText is absent' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 11 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /Alt (Alternate Tagged Text) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /E (Expanded Abbreviation Text) /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Actual Text Wins) /Alt (Ignored Alternate Text) /E (Ignored Expanded Text) /K << /Type /MCR /MCID 2 >> >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Alternate Tagged Text', 'Expanded Abbreviation Text', 'Actual Text Wins'], $extractor->extractTextLines($pdf));
        $t->same(['Alternate Tagged Text', 'Expanded Abbreviation Text', 'Actual Text Wins'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
        $t->true(!str_contains($plainText, 'Ignored Alternate Text'));
        $t->true(!str_contains($plainText, 'Ignored Expanded Text'));
    },
    'keeps mapped caption children after a grouped StructElem Alt prefix' => static function (TestRunner $t): void {
        $content = "BT /Ffallback 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F> Tj EMC ET "
            . "BT /Fmapped 12 Tf 72 700 Td "
            . "/Span << /MCID 1 >> BDC (Caption child remains visible) Tj EMC ET";
        $prefix = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $page = static fn (bool $withParentTree): string => "3 0 obj\n"
            . "<< /Type /Page /Parent 2 0 R"
            . ($withParentTree ? " /StructParents 0" : '')
            . " /Resources << /Font << /Ffallback 4 0 R /Fmapped 6 0 R >> >> /Contents 5 0 R >>\n"
            . "endobj\n";
        $common = "4 0 obj\n"
            . "<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\n"
            . "endobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n"
            . "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\n"
            . "endobj\n";
        $parentTreePdf = $prefix
            . $page(true)
            . $common
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 9 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n"
            . "<< /Type /StructElem /S /Figure /Alt (Figure 1:) "
            . "/K [ << /Type /MCR /MCID 0 >> << /Type /MCR /MCID 1 >> ] >>\n"
            . "endobj\n%%EOF";
        $rootKidPdf = $prefix
            . $page(false)
            . $common
            . "7 0 obj\n<< /Type /StructTreeRoot /K 9 0 R >>\nendobj\n"
            . "9 0 obj\n"
            . "<< /Type /StructElem /S /Figure /Pg 3 0 R /Alt (Figure 1:) "
            . "/K [ << /Type /MCR /Pg 3 0 R /MCID 0 >> "
            . "<< /Type /MCR /Pg 3 0 R /MCID 1 >> ] >>\n"
            . "endobj\n%%EOF";

        foreach (['StructTreeRoot K' => $rootKidPdf, 'ParentTree' => $parentTreePdf] as $path => $pdf) {
            $extractor = new PdfTextExtractor();
            $t->same(
                ['Figure 1:', 'Caption child remains visible'],
                $extractor->extractTextLines($pdf),
                "{$path} grouped Alt fallback must not suppress its mapped caption child."
            );
            $t->contains('Caption child remains visible', $extractor->extractPlainText($pdf));
        }
    },
    'uses indirect StructElem replacement strings before custom glyph suppression' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC T* "
            . "/Span << /MCID 1 >> BDC <3F314749314541> Tj EMC T* "
            . "/Span << /MCID 2 >> BDC <87918A8B8C8D8E8F90> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 7 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /StructTreeRoot /ParentTree 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Nums [ 0 [ 9 0 R 10 0 R 11 0 R ] ] >>\nendobj\n"
            . "9 0 obj\n<< /Type /StructElem /S /Span /ActualText 12 0 R /Alt (Ignored Indirect Alternate Text) /K << /Type /MCR /MCID 0 >> >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /Alt 13 0 R /K << /Type /MCR /MCID 1 >> >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /E 14 0 R /K << /Type /MCR /MCID 2 >> >>\nendobj\n"
            . "12 0 obj\n(Indirect ActualText Tagged Text)\nendobj\n"
            . "13 0 obj\n<496E64697265637420416C74205461676765642054657874>\nendobj\n"
            . "14 0 obj\n(Indirect Expanded Tagged Text)\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $expected = ['Indirect ActualText Tagged Text', 'Indirect Alt Tagged Text', 'Indirect Expanded Tagged Text'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
        $t->true(!str_contains($plainText, 'Ignored Indirect Alternate Text'));
        $t->same(1, substr_count($plainText, 'Indirect ActualText Tagged Text'));
        $t->same(1, substr_count($plainText, 'Indirect Alt Tagged Text'));
        $t->same(1, substr_count($plainText, 'Indirect Expanded Tagged Text'));
    },
    'uses form XObject StructParents MCID ActualText instead of page MCID collisions' => static function (TestRunner $t): void {
        $pageContent = "BT /Fpage 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET q /Fm1 Do Q";
        $formContent = "BT /Fform 12 Tf 20 20 Td "
            . "/Span << /MCID 0 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 8 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /Resources << /Font << /Fpage 4 0 R >> /XObject << /Fm1 6 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+PageSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /StructParents 1 /Resources << /Font << /Fform 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+FormSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "8 0 obj\n<< /Type /StructTreeRoot /ParentTree 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Nums [ 0 [ 10 0 R ] 1 [ 11 0 R ] ] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Page Tagged Text) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Form Tagged Text) >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Page Tagged Text', 'Form Tagged Text'], $extractor->extractTextLines($pdf));
        $t->same(['Page Tagged Text', 'Form Tagged Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->same(1, substr_count($plainText, 'Page Tagged Text'));
        $t->same(1, substr_count($plainText, 'Form Tagged Text'));
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'uses indirect StructParents values for page and form ActualText lookup' => static function (TestRunner $t): void {
        $pageContent = "BT /Fpage 12 Tf 72 720 Td "
            . "/Span << /MCID 0 >> BDC <3F504B4541> Tj EMC ET q /Fm1 Do Q";
        $formContent = "BT /Fform 12 Tf 20 20 Td "
            . "/Span << /MCID 0 >> BDC <3F314749314541> Tj EMC ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 8 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 12 0 R /Resources << /Font << /Fpage 4 0 R >> /XObject << /Fm1 6 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+PageSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /StructParents 13 0 R /Resources << /Font << /Fform 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+FormSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "8 0 obj\n<< /Type /StructTreeRoot /ParentTree 9 0 R >>\nendobj\n"
            . "9 0 obj\n<< /Nums [ 0 [ 10 0 R ] 1 [ 11 0 R ] ] >>\nendobj\n"
            . "10 0 obj\n<< /Type /StructElem /S /Span /ActualText (Page Indirect StructParents Text) >>\nendobj\n"
            . "11 0 obj\n<< /Type /StructElem /S /Span /ActualText (Form Indirect StructParents Text) >>\nendobj\n"
            . "12 0 obj\n0\nendobj\n"
            . "13 0 obj\n1\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['Page Indirect StructParents Text', 'Form Indirect StructParents Text'], $extractor->extractTextLines($pdf));
        $t->same(['Page Indirect StructParents Text', 'Form Indirect StructParents Text'], $extractor->extractTextRuns($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
        $t->same(1, substr_count($plainText, 'Page Indirect StructParents Text'));
        $t->same(1, substr_count($plainText, 'Form Indirect StructParents Text'));
        $t->true(!str_contains($plainText, '?PKEA'));
        $t->true(!str_contains($plainText, '?1GI1EA'));
    },
    'suppresses unmapped custom subset glyph streams instead of emitting garbage text' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td "
            . "(stuvwxyz) Tj T* "
            . "(?PKEA) Tj T* "
            . "<8788898A8A8B888C8B898D8B8A8B8C8A8E8F8F8D888B8987> Tj T* "
            . "(?1GI1EA) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $extractor->extractPlainText($pdf));
        $t->same(['F1'], $diagnostics['missingUnicodeFonts']);
        $t->same(['F1' => 'WinAnsiEncoding'], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(4, $diagnostics['suppressedGlyphRuns']);
        $t->contains('suppressed because their font lacks a Unicode map', $warningText);
    },
    'decodes standard encoded subset TrueType fonts with widths without ToUnicode maps' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (CONTOSO LTD.) Tj T* (INVOICE DATE) Tj ET";
        $widthArray = trim(str_repeat('500 ', 59));
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /ABCDEF+Calibri /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 90 /Widths [{$widthArray}] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['CONTOSO LTD.', 'INVOICE DATE'], $extractor->extractTextLines($pdf));
        $t->same(['CONTOSO LTD.', 'INVOICE DATE'], $extractor->extractTextRuns($pdf));
        $t->contains('CONTOSO LTD.', $extractor->extractPlainText($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'decodes standard encoded subset Type1 fonts with widths without ToUnicode maps' => static function (TestRunner $t): void {
        $content = "BT /F1 12 Tf 72 720 Td (STANDARD TYPE1) Tj T* (FORM TEXT) Tj ET";
        $widthArray = trim(str_repeat('500 ', 59));
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+HelveticaNeue /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 90 /Widths [{$widthArray}] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $diagnostics = $extractor->diagnostics($pdf);

        $t->same(['STANDARD TYPE1', 'FORM TEXT'], $extractor->extractTextLines($pdf));
        $t->same(['STANDARD TYPE1', 'FORM TEXT'], $extractor->extractTextRuns($pdf));
        $t->contains('STANDARD TYPE1', $extractor->extractPlainText($pdf));
        $t->same([], $diagnostics['missingUnicodeFonts']);
        $t->same([], $diagnostics['missingUnicodeFontEncodings']);
        $t->same(0, $diagnostics['suppressedGlyphRuns']);
    },
    'extracts text from form XObjects invoked by page content streams' => static function (TestRunner $t): void {
        $pageContent = 'BT /Fpage 12 Tf 72 720 Td (Before form) Tj ET q /Fm1 Do Q q /Im1 Do Q BT /Fpage 12 Tf 72 680 Td (After form) Tj ET';
        $formContent = 'BT /Fform 12 Tf 20 20 Td (Form XObject text) Tj ET';
        $imagePayload = 'BT /Fform 12 Tf 20 20 Td (Image payload text) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fpage 4 0 R >> /XObject << /Fm1 6 0 R /Im1 8 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /Fform 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $diagnostics = $extractor->diagnostics($pdf);
        $warningText = implode("\n", $diagnostics['warnings']);

        $t->same(['Before form', 'Form XObject text', 'After form'], $extractor->extractTextLines($pdf));
        $t->true(str_contains($plainText, 'Form XObject text'));
        $t->true(!str_contains($plainText, 'Image payload text'));
        $t->same(['Image'], $diagnostics['ignoredXObjectSubtypes']);
        $t->same(1, $diagnostics['ignoredXObjectCount']);
        $t->contains('Ignored 1 non-text PDF XObject(s): Image.', $warningText);
    },
    'keeps form XObject font resources scoped from the invoking page' => static function (TestRunner $t): void {
        $pageContent = 'BT /F1 12 Tf 72 720 Td (Before form) Tj ET q /Fm1 Do Q BT /F1 12 Tf 72 680 Td (After form) Tj ET';
        $formContent = 'BT /F1 12 Tf 20 20 Td <01> Tj ET';
        $cmap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "1 beginbfchar\n"
            . "<01> <00530063006F00700065006400200046006F0072006D>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /XObject << /Fm1 6 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 7 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FormSubset /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n%%EOF";

        $t->same(['Before form', 'Scoped Form', 'After form'], (new PdfTextExtractor())->extractTextLines($pdf));
    },
    'keeps inherited page resources scoped when font resource names repeat across pages' => static function (TestRunner $t): void {
        $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Inherited page text) Tj ET';
        $secondPageContent = 'BT /F1 12 Tf 72 720 Td (stuvwxyz) Tj T* (?PKEA) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 /Resources << /Font << /F1 7 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 6 0 R >>\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
            . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+CustomSubset /Encoding /WinAnsiEncoding >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Inherited page text'], $extractor->extractTextLines($pdf));
        $t->same(['Inherited page text'], $extractor->extractTextRuns($pdf));
        $t->true(!str_contains($plainText, 'stuvwxyz'));
        $t->true(!str_contains($plainText, '?PKEA'));
    },
    'keeps ToUnicode maps scoped when the same font resource name is reused by pages' => static function (TestRunner $t): void {
        $firstPageContent = 'BT /F1 12 Tf 72 720 Td <01> Tj ET';
        $secondPageContent = 'BT /F1 12 Tf 72 720 Td <01> Tj ET';
        $firstCMap = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "1 begincodespacerange\n"
            . "<00> <FF>\n"
            . "endcodespacerange\n"
            . "1 beginbfchar\n"
            . "<01> <00500061006700650020004F006E0065>\n"
            . "endbfchar\n"
            . "endcmap\n"
            . "CMapName currentdict /CMap defineresource pop\n"
            . "end\n"
            . "end\n";
        $secondCMap = str_replace('00500061006700650020004F006E0065', '0050006100670065002000540077006F', $firstCMap);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 6 0 R] /Count 2 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FirstSubset /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 8 0 R >>\nendobj\n"
            . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SecondSubset /Encoding /Identity-H /ToUnicode 10 0 R >>\nendobj\n"
            . "8 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($firstCMap) . " >>\nstream\n{$firstCMap}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Length " . strlen($secondCMap) . " >>\nstream\n{$secondCMap}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();

        $t->same(['Page One', 'Page Two'], $extractor->extractTextLines($pdf));
        $t->same(['Page One', 'Page Two'], $extractor->extractTextRuns($pdf));
    },
    'extracts block-ready lines from a WordPress import fixture' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-import-content.pdf');
        $t->true(is_string($fixture), 'Fixture should be readable');

        $lines = (new PdfTextExtractor())->extractTextLines($fixture);
        $t->same(['WP Migration', 'Clean blocks from PDF imports', 'Media library captions'], $lines);
    },
    'replays upstream naive_get_text page suffix and get_length_of_text trim boundary' => static function (TestRunner $t) use ($pdfWithStreams): void {
        $pdf = $pdfWithStreams([
            'BT (First page) Tj T* (Second line) Tj ET',
            'BT (Second page) Tj ET',
        ]);
        $extractor = new PdfTextExtractor();
        $expectedText = "First page\nSecond line\nSecond page\n";

        $t->same($expectedText, $extractor->naiveGetText($pdf));

        $path = sys_get_temp_dir() . '/markerpdf-text-length-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, $pdf);
        try {
            $t->same(strlen(trim($expectedText)), $extractor->getLengthOfText($path));
        } finally {
            @unlink($path);
        }
    },
    'treats oversized flate decoded streams as failed instead of exhausting memory' => static function (TestRunner $t): void {
        $decoded = str_repeat('Oversized decoded stream. ', 128);
        $encoded = gzcompress($decoded);
        $t->true(is_string($encoded), 'Fixture should deflate');
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($encoded) . " /Filter /FlateDecode >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor(['maxDecodedStreamBytes' => 128]);

        $t->same([], $extractor->extractTextLines($pdf));
        $diagnostics = $extractor->diagnostics($pdf);
        $t->same(1, $diagnostics['failedStreams']);
        $t->contains('1 PDF stream(s) could not be decoded.', implode("\n", $diagnostics['warnings']));
    },
    'skips oversized content stream tokenization instead of building unbounded token arrays' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td (Visible before cap) Tj ET ' . str_repeat('0 0 0 rg ', 512);
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor(['maxTokenizedContentStreamBytes' => 128]);

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same([], $extractor->extractPositionedTextRuns($pdf));
    },
    'bounds positioned text run collection for dense content streams' => static function (TestRunner $t): void {
        $content = 'BT /F1 12 Tf 72 720 Td ' . str_repeat('(x) Tj ', 20) . 'ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor(['maxPositionedTextRuns' => 5]);

        $runs = $extractor->extractPositionedTextRuns($pdf);
        $t->same(5, count($runs));
        $t->same('x', $runs[0]['text']);
        $t->same('x', $runs[4]['text']);
    },
];
