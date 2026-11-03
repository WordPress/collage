<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfXrefFreeObjectMap
{
    /**
     * @return array<int, true>
     */
    public static function freeObjectNumbers(string $pdfBytes): array
    {
        $offset = self::startxrefOffsetWithClassicRebuild($pdfBytes);
        if ($offset === null) {
            return [];
        }

        $freeObjects = [];
        foreach (self::xrefEntriesFromOffsetChain($pdfBytes, $offset) as $objectNumber => $entry) {
            if (($entry['state'] ?? null) === 'f') {
                $freeObjects[(int) $objectNumber] = true;
            }
        }

        return $freeObjects;
    }

    /**
     * @return array{offset: int, tokenOffset: int}|null
     */
    private static function latestStartxrefEntry(string $pdfBytes): ?array
    {
        if (preg_match_all('/\bstartxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1] ?? null;
            if (
                !is_int($tokenOffset)
                || !self::keywordAt($pdfBytes, $tokenOffset, 'startxref')
                || self::tokenStartsInCommentLine($pdfBytes, $tokenOffset)
                || self::tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset)
            ) {
                continue;
            }

            $operandBytes = substr($pdfBytes, $tokenOffset + strlen('startxref'), 64);
            $declaredOffset = self::startxrefDeclaredOffsetFromOperand($operandBytes);
            if ($declaredOffset !== null) {
                $declaredOffset = max(0, $declaredOffset);
                if (self::xrefTableStartsAfterMalformedHexSelfStartxrefTail($pdfBytes, $declaredOffset)) {
                    continue;
                }

                return [
                    'offset' => $declaredOffset,
                    'tokenOffset' => $tokenOffset,
                ];
            }
        }

        return null;
    }

    private static function startxrefDeclaredOffsetFromOperand(string $operandBytes): ?int
    {
        $offset = 0;
        $length = strlen($operandBytes);
        while ($offset < $length && self::isPdfWhitespace($operandBytes[$offset])) {
            $offset++;
        }

        if (preg_match('/\G[+-]?\d+/s', $operandBytes, $match, 0, $offset) !== 1) {
            return 0;
        }

        $after = $offset + strlen($match[0]);
        while ($after < $length) {
            $char = $operandBytes[$after];
            if ($char === "\n" || $char === "\r" || $char === '%') {
                return (int) $match[0];
            }
            if (!self::isPdfWhitespace($char)) {
                return null;
            }

            $after++;
        }

        return (int) $match[0];
    }

    /**
     * Damaged incremental writers can append a valid classic xref table while
     * leaving the final startxref operand pointed at garbage or an older table.
     * Rebuild only to classic tables; malformed xref streams remain fail-closed.
     */
    private static function startxrefOffsetWithClassicRebuild(string $pdfBytes): ?int
    {
        $entry = self::latestStartxrefEntry($pdfBytes);
        $boundary = self::classicRebuildBoundaryOffset($pdfBytes, $entry);
        if ($entry === null) {
            return self::latestClassicXrefTableOffset($pdfBytes, $boundary);
        }

        return self::classicRebuildOffsetForStartxref($pdfBytes, $entry['offset'], $boundary) ?? $entry['offset'];
    }

    /**
     * @param array{offset: int, tokenOffset: int}|null $entry
     */
    private static function classicRebuildBoundaryOffset(string $pdfBytes, ?array $entry): ?int
    {
        $boundary = $entry['tokenOffset'] ?? null;
        $ignoredBoundary = self::latestIgnoredStartxrefRebuildBoundaryOffset($pdfBytes);
        $eofBoundary = self::latestTopLevelEofOffset($pdfBytes);
        $entryEofBoundary = $entry === null
            ? null
            : self::firstTopLevelEofOffsetAfter($pdfBytes, $entry['tokenOffset']);
        if ($entry !== null && !self::startxrefEntryHasNumericOperand($pdfBytes, $entry)) {
            if ($entryEofBoundary !== null) {
                $eofBoundary = $entryEofBoundary;
            }
        }
        $invalidStartxrefBoundary = self::latestInvalidStartxrefRebuildBoundaryOffset($pdfBytes);
        if ($invalidStartxrefBoundary !== null && ($boundary === null || $invalidStartxrefBoundary > $boundary)) {
            $invalidEofBoundary = self::firstTopLevelEofOffsetAfter($pdfBytes, $invalidStartxrefBoundary);
            if ($invalidEofBoundary !== null) {
                $eofBoundary = $invalidEofBoundary;
            }
        }
        if ($boundary === null) {
            if ($ignoredBoundary !== null && ($eofBoundary === null || $ignoredBoundary < $eofBoundary)) {
                $latestBeforeEof = $eofBoundary === null
                    ? null
                    : self::latestClassicXrefTableOffset($pdfBytes, $eofBoundary);

                return $latestBeforeEof !== null
                    && $latestBeforeEof > $ignoredBoundary
                    && !self::hasTopLevelStartxrefTokenBetweenOffsets($pdfBytes, $latestBeforeEof, $eofBoundary)
                    ? $eofBoundary
                    : $ignoredBoundary;
            }

            return $eofBoundary;
        }

        if ($ignoredBoundary !== null && $ignoredBoundary > $boundary) {
            $latestBeforeEof = $eofBoundary === null
                ? null
                : self::latestClassicXrefTableOffset($pdfBytes, $eofBoundary);
            if (
                $latestBeforeEof !== null
                && $latestBeforeEof > $ignoredBoundary
                && self::classicXrefTableHasPreviousOffset($pdfBytes, $latestBeforeEof, $entry['offset'])
            ) {
                return $eofBoundary;
            }
            if (
                $latestBeforeEof === null
                || $latestBeforeEof <= $ignoredBoundary
                || self::hasTopLevelStartxrefTokenBetweenOffsets($pdfBytes, $latestBeforeEof, $eofBoundary)
            ) {
                return $ignoredBoundary;
            }
        }

        if ($eofBoundary !== null && $eofBoundary > $boundary) {
            $latestBeforeEof = self::latestClassicXrefTableOffset($pdfBytes, $eofBoundary);
            if (
                $entryEofBoundary !== null
                && $latestBeforeEof !== null
                && $latestBeforeEof > $entryEofBoundary
                && self::xrefTableSectionAt($pdfBytes, $entry['offset']) === null
                && !self::hasTopLevelStartxrefTokenBetweenOffsets($pdfBytes, $latestBeforeEof, $eofBoundary)
            ) {
                return $boundary;
            }
            if (
                $latestBeforeEof !== null
                && $latestBeforeEof > $boundary
                && self::classicXrefTableHasPreviousOffset($pdfBytes, $latestBeforeEof, $entry['offset'])
            ) {
                return $eofBoundary;
            }
            if (
                $latestBeforeEof !== null
                && $latestBeforeEof > $boundary
                && !self::hasTopLevelStartxrefTokenBetweenOffsets($pdfBytes, $latestBeforeEof, $eofBoundary)
            ) {
                return $eofBoundary;
            }
        }

        return $boundary;
    }

    /**
     * @param array{offset: int, tokenOffset: int} $entry
     */
    private static function startxrefEntryHasNumericOperand(string $pdfBytes, array $entry): bool
    {
        $offset = $entry['tokenOffset'] + strlen('startxref');
        $length = strlen($pdfBytes);
        while ($offset < $length && self::isPdfWhitespace($pdfBytes[$offset])) {
            $offset++;
        }

        return preg_match('/\G[+-]?\d+/s', $pdfBytes, $match, 0, $offset) === 1;
    }

    private static function firstTopLevelEofOffsetAfter(string $pdfBytes, int $afterOffset): ?int
    {
        if (preg_match_all('/%%EOF/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        foreach ($matches[0] as $match) {
            $tokenOffset = $match[1] ?? null;
            if (!is_int($tokenOffset) || $tokenOffset <= $afterOffset) {
                continue;
            }

            if (self::tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset)) {
                continue;
            }

            return $tokenOffset;
        }

        return null;
    }

    private static function latestTopLevelEofOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/%%EOF/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1] ?? null;
            if (!is_int($tokenOffset) || self::tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset)) {
                continue;
            }

            return $tokenOffset;
        }

        return null;
    }

    private static function hasTopLevelStartxrefTokenBetweenOffsets(
        string $pdfBytes,
        int $afterOffset,
        ?int $beforeOffset
    ): bool {
        if ($beforeOffset === null || preg_match_all('/\bstartxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return false;
        }

        foreach ($matches[0] as $match) {
            $tokenOffset = $match[1] ?? null;
            if (!is_int($tokenOffset) || $tokenOffset <= $afterOffset || $tokenOffset >= $beforeOffset) {
                continue;
            }

            if (self::tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private static function classicXrefTableHasPreviousOffset(
        string $pdfBytes,
        int $xrefOffset,
        int $previousOffset
    ): bool {
        $table = self::xrefTableSectionAt($pdfBytes, $xrefOffset);
        if ($table === null) {
            return false;
        }

        return self::integerValueAfterName($table['trailer'], 'Prev') === $previousOffset;
    }

    private static function latestIgnoredStartxrefRebuildBoundaryOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/\bstartxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1] ?? null;
            if (!is_int($tokenOffset) || !self::keywordAt($pdfBytes, $tokenOffset, 'startxref')) {
                continue;
            }

            if (self::tokenStartsInCommentLine($pdfBytes, $tokenOffset)) {
                continue;
            }

            if (self::tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset)) {
                return $tokenOffset;
            }
        }

        return null;
    }

    private static function latestInvalidStartxrefRebuildBoundaryOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/\bstartxref\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            $tokenOffset = $matches[0][$index][1] ?? null;
            if (
                !is_int($tokenOffset)
                || !self::keywordAt($pdfBytes, $tokenOffset, 'startxref')
                || self::tokenStartsInCommentLine($pdfBytes, $tokenOffset)
                || self::tokenStartsInsidePdfCompositeToken($pdfBytes, $tokenOffset)
            ) {
                continue;
            }

            if (self::startxrefDeclaredOffsetFromOperand(substr($pdfBytes, $tokenOffset + strlen('startxref'), 64)) === null) {
                return $tokenOffset;
            }
        }

        return null;
    }

    private static function classicRebuildOffsetForStartxref(
        string $pdfBytes,
        int $offset,
        ?int $candidateBeforeOffset = null
    ): ?int {
        if (self::xrefStreamObjectStartsAt($pdfBytes, $offset)) {
            return null;
        }

        $latestClassicOffset = self::latestClassicXrefTableOffset($pdfBytes, $candidateBeforeOffset);
        if ($latestClassicOffset === null) {
            return null;
        }

        if (self::xrefTableSectionAt($pdfBytes, $offset) === null) {
            if (
                $candidateBeforeOffset !== null
                && $offset < $candidateBeforeOffset
                && $latestClassicOffset < $candidateBeforeOffset
            ) {
                return $latestClassicOffset;
            }

            if ($offset < strlen($pdfBytes) && $latestClassicOffset <= $offset) {
                return null;
            }

            return $latestClassicOffset;
        }

        return $latestClassicOffset > $offset ? $latestClassicOffset : null;
    }

    private static function xrefStreamObjectStartsAt(string $pdfBytes, int $offset): bool
    {
        $definition = self::directObjectAtOffset($pdfBytes, $offset);

        return $definition !== null && preg_match('/\/Type\s*\/XRef\b/', $definition['body']) === 1;
    }

    private static function latestClassicXrefTableOffset(string $pdfBytes, ?int $beforeOffset = null): ?int
    {
        $offsets = self::xrefTableKeywordOffsets($pdfBytes);
        for ($index = count($offsets) - 1; $index >= 0; $index--) {
            $offset = $offsets[$index];
            if ($beforeOffset !== null && $offset > $beforeOffset) {
                continue;
            }

            if (self::xrefTableSectionAt($pdfBytes, $offset) !== null) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private static function xrefTableKeywordOffsets(string $pdfBytes): array
    {
        $offsets = [];
        $length = strlen($pdfBytes);
        $offset = 0;
        while ($offset < $length) {
            $objectEnd = self::directObjectTokenEndAt($pdfBytes, $offset);
            if ($objectEnd !== null) {
                $offset = $objectEnd;
                continue;
            }
            if (self::malformedDirectObjectBodyStartAt($pdfBytes, $offset) !== null) {
                break;
            }

            $char = $pdfBytes[$offset];
            if ($char === '%') {
                self::skipComment($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                self::skipLiteralString($pdfBytes, $offset);
                continue;
            }

            $compositeEnd = self::skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null) {
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $hexEnd = self::skipPdfHexStringBoundaryAt($pdfBytes, $offset);
                if ($hexEnd !== null) {
                    $offset = $hexEnd;
                    continue;
                }
            }

            if (
                self::keywordAt($pdfBytes, $offset, 'xref')
                && !self::xrefTableStartsAfterMalformedHexSelfStartxrefTail($pdfBytes, $offset)
            ) {
                $offsets[] = $offset;
                $offset += strlen('xref');
                continue;
            }

            $offset++;
        }

        return $offsets;
    }

    private static function tokenStartsInsidePdfCompositeToken(string $pdfBytes, int $tokenOffset): bool
    {
        $length = strlen($pdfBytes);
        $offset = 0;
        while ($offset < $tokenOffset && $offset < $length) {
            $objectEnd = self::directObjectTokenEndAt($pdfBytes, $offset);
            if ($objectEnd !== null) {
                if ($tokenOffset > $offset && $tokenOffset < $objectEnd) {
                    return true;
                }
                $offset = $objectEnd;
                continue;
            }
            $malformedObjectBodyStart = self::malformedDirectObjectBodyStartAt($pdfBytes, $offset);
            if ($malformedObjectBodyStart !== null) {
                return $tokenOffset >= $malformedObjectBodyStart;
            }

            $char = $pdfBytes[$offset];
            if ($char === '%') {
                $start = $offset;
                self::skipComment($pdfBytes, $offset);
                if ($tokenOffset > $start && $tokenOffset < $offset) {
                    return true;
                }
                continue;
            }

            if ($char === '(') {
                $start = $offset;
                self::skipLiteralString($pdfBytes, $offset);
                if ($tokenOffset > $start && $tokenOffset < $offset) {
                    return true;
                }
                continue;
            }

            $compositeEnd = self::skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null) {
                if ($tokenOffset > $offset && $tokenOffset < $compositeEnd) {
                    return true;
                }
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $hexEnd = self::skipPdfHexStringBoundaryAt($pdfBytes, $offset);
                if ($hexEnd !== null) {
                    if ($tokenOffset > $offset && $tokenOffset < $hexEnd) {
                        return true;
                    }
                    $offset = $hexEnd;
                    continue;
                }
            }

            $offset++;
        }

        return false;
    }

    private static function directObjectTokenEndAt(string $pdfBytes, int $offset): ?int
    {
        if (preg_match('/\G\d+\s+\d+\s+obj\b/s', $pdfBytes, $match, 0, $offset) !== 1) {
            return null;
        }

        $bodyStart = $offset + strlen($match[0]);
        $bodyEnd = self::directObjectEndOffset($pdfBytes, $bodyStart);

        return $bodyEnd === null ? null : $bodyEnd + strlen('endobj');
    }

    private static function malformedDirectObjectBodyStartAt(string $pdfBytes, int $offset): ?int
    {
        if (!ctype_digit($pdfBytes[$offset] ?? '')) {
            return null;
        }

        if (preg_match('/\G\d+\s+\d+\s+obj\b/s', $pdfBytes, $match, 0, $offset) !== 1) {
            return null;
        }

        $bodyStart = $offset + strlen($match[0]);

        return self::directObjectEndOffset($pdfBytes, $bodyStart) === null ? $bodyStart : null;
    }

    private static function directObjectEndOffset(string $pdfBytes, int $offset): ?int
    {
        $length = strlen($pdfBytes);
        $lastDictionary = null;
        $lastDictionaryEnd = null;
        while ($offset < $length) {
            $char = $pdfBytes[$offset];

            if ($char === '%') {
                self::skipComment($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                self::skipLiteralString($pdfBytes, $offset);
                continue;
            }

            if (substr($pdfBytes, $offset, 2) === '<<') {
                $dictionary = self::readDictionaryAt($pdfBytes, $offset);
                if ($dictionary !== null) {
                    $offset += strlen($dictionary);
                    $lastDictionary = $dictionary;
                    $lastDictionaryEnd = $offset;
                    continue;
                }
            }

            if ($char === '[') {
                $array = self::readArrayAt($pdfBytes, $offset);
                if ($array !== null) {
                    $offset += strlen($array);
                    continue;
                }
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                self::skipHexString($pdfBytes, $offset);
                continue;
            }

            if (self::keywordAt($pdfBytes, $offset, 'stream')) {
                $streamDictionary = $lastDictionaryEnd !== null
                    && self::skipWhitespace($pdfBytes, $lastDictionaryEnd) === $offset
                    ? $lastDictionary
                    : null;
                $streamEnd = self::directObjectStreamEndOffset($pdfBytes, $offset, $streamDictionary);
                if ($streamEnd !== null) {
                    $offset = $streamEnd + strlen('endstream');
                    continue;
                }
            }

            if (self::keywordAt($pdfBytes, $offset, 'endobj')) {
                return $offset;
            }

            $offset++;
        }

        return null;
    }

    private static function directObjectStreamEndOffset(
        string $pdfBytes,
        int $streamKeywordOffset,
        ?string $dictionary = null
    ): ?int
    {
        $streamStart = $streamKeywordOffset + strlen('stream');
        if (substr($pdfBytes, $streamStart, 2) === "\r\n") {
            $streamStart += 2;
        } elseif (($pdfBytes[$streamStart] ?? '') === "\n" || ($pdfBytes[$streamStart] ?? '') === "\r") {
            $streamStart++;
        }

        $declaredLength = $dictionary === null ? null : self::directStreamLengthFromDictionary($dictionary);
        if ($declaredLength !== null && $declaredLength >= 0) {
            $declaredEnd = $streamStart + $declaredLength;
            $declaredTerminator = self::streamTerminatorOffsetAfterDeclaredLength($pdfBytes, $declaredEnd);
            if ($declaredTerminator !== null) {
                return $declaredTerminator;
            }
        }

        $streamEnd = strpos($pdfBytes, 'endstream', $streamStart);

        return is_int($streamEnd) ? $streamEnd : null;
    }

    private static function directStreamLengthFromDictionary(string $dictionary): ?int
    {
        $offset = self::valueOffsetAfterName($dictionary, 'Length');
        if ($offset === null || preg_match('/\G([+-]?\d+)\b/s', $dictionary, $match, 0, $offset) !== 1) {
            return null;
        }

        $afterInteger = self::skipWhitespace($dictionary, $offset + strlen($match[1]));
        if (preg_match('/\G\d+\s+R\b/s', $dictionary, $referenceMatch, 0, $afterInteger) === 1) {
            return null;
        }

        return (int) $match[1];
    }

    private static function streamTerminatorOffsetAfterDeclaredLength(string $pdfBytes, int $declaredEnd): ?int
    {
        if ($declaredEnd < 0 || $declaredEnd > strlen($pdfBytes)) {
            return null;
        }

        $candidates = [$declaredEnd];
        if (substr($pdfBytes, $declaredEnd, 2) === "\r\n") {
            $candidates[] = $declaredEnd + 2;
        } elseif (($pdfBytes[$declaredEnd] ?? '') === "\n" || ($pdfBytes[$declaredEnd] ?? '') === "\r") {
            $candidates[] = $declaredEnd + 1;
        }

        foreach ($candidates as $candidate) {
            if (self::keywordAt($pdfBytes, $candidate, 'endstream')) {
                return $candidate;
            }
        }

        return null;
    }

    private static function skipPdfCompositeTokenAt(string $pdfBytes, int $offset): ?int
    {
        if (($pdfBytes[$offset] ?? '') === '[') {
            $array = self::readArrayAt($pdfBytes, $offset);

            return $array === null ? null : $offset + strlen($array);
        }

        if (substr($pdfBytes, $offset, 2) === '<<') {
            $dictionary = self::readDictionaryAt($pdfBytes, $offset);

            return $dictionary === null ? null : $offset + strlen($dictionary);
        }

        return null;
    }

    private static function skipPdfHexStringTokenAt(string $pdfBytes, int $offset): ?int
    {
        if (($pdfBytes[$offset] ?? '') !== '<' || ($pdfBytes[$offset + 1] ?? '') === '<') {
            return null;
        }

        for ($index = $offset + 1, $length = strlen($pdfBytes); $index < $length; $index++) {
            $char = $pdfBytes[$index];
            if ($char === '>') {
                return $index + 1;
            }

            if (ctype_xdigit($char) || self::isPdfWhitespace($char)) {
                continue;
            }

            return null;
        }

        return null;
    }

    private static function skipPdfHexStringBoundaryAt(string $pdfBytes, int $offset): ?int
    {
        $strictEnd = self::skipPdfHexStringTokenAt($pdfBytes, $offset);
        if ($strictEnd !== null) {
            return $strictEnd;
        }

        return strpos($pdfBytes, '>', $offset + 1) === false ? strlen($pdfBytes) : null;
    }

    private static function xrefTableStartsAfterMalformedHexSelfStartxrefTail(string $pdfBytes, int $xrefOffset): bool
    {
        if ($xrefOffset < 0 || $xrefOffset >= strlen($pdfBytes) || !self::keywordAt($pdfBytes, $xrefOffset, 'xref')) {
            return false;
        }

        if (self::malformedHexStringOpenerBeforeToken($pdfBytes, $xrefOffset) === null) {
            return false;
        }

        $eofOffset = strpos($pdfBytes, '%%EOF', $xrefOffset);
        $tail = $eofOffset === false
            ? substr($pdfBytes, $xrefOffset)
            : substr($pdfBytes, $xrefOffset, $eofOffset - $xrefOffset);
        if (preg_match('/\bstartxref\b\s*([+-]?\d+)/s', $tail, $match) !== 1) {
            return false;
        }

        return (int) $match[1] === $xrefOffset;
    }

    private static function malformedHexStringOpenerBeforeToken(string $pdfBytes, int $tokenOffset): ?int
    {
        $length = strlen($pdfBytes);
        $offset = 0;
        while ($offset < $tokenOffset && $offset < $length) {
            $objectEnd = self::directObjectTokenEndAt($pdfBytes, $offset);
            if ($objectEnd !== null) {
                $offset = $objectEnd;
                continue;
            }

            $malformedObjectBodyStart = self::malformedDirectObjectBodyStartAt($pdfBytes, $offset);
            if ($malformedObjectBodyStart !== null) {
                return null;
            }

            $char = $pdfBytes[$offset];
            if ($char === '%') {
                self::skipComment($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                self::skipLiteralString($pdfBytes, $offset);
                continue;
            }

            $compositeEnd = self::skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null) {
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $strictEnd = self::skipPdfHexStringTokenAt($pdfBytes, $offset);
                if ($strictEnd !== null) {
                    $offset = $strictEnd;
                    continue;
                }

                $closeOffset = strpos($pdfBytes, '>', $offset + 1);
                if ($closeOffset === false || $closeOffset > $tokenOffset) {
                    return $offset;
                }

                $offset = $closeOffset + 1;
                continue;
            }

            $offset++;
        }

        return null;
    }

    private static function tokenStartsInCommentLine(string $pdfBytes, int $tokenOffset): bool
    {
        $before = substr($pdfBytes, 0, $tokenOffset);
        $lastLineFeed = strrpos($before, "\n");
        $lastCarriageReturn = strrpos($before, "\r");
        $lineStart = max($lastLineFeed === false ? -1 : $lastLineFeed, $lastCarriageReturn === false ? -1 : $lastCarriageReturn) + 1;
        $offset = 0;
        while ($offset < $tokenOffset) {
            $objectEnd = self::directObjectTokenEndAt($pdfBytes, $offset);
            if ($objectEnd !== null) {
                $offset = $objectEnd;
                continue;
            }

            $char = $pdfBytes[$offset] ?? '';
            if ($char === '%') {
                if ($offset >= $lineStart) {
                    return true;
                }

                self::skipComment($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                $end = $offset;
                self::skipLiteralString($pdfBytes, $end);
                if ($end > $offset) {
                    $offset = $end;
                    continue;
                }
            }

            $compositeEnd = self::skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null && $compositeEnd > $offset) {
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $end = $offset;
                self::skipHexString($pdfBytes, $end);
                if ($end > $offset) {
                    $offset = $end;
                    continue;
                }
            }

            $offset++;
        }

        return false;
    }

    /**
     * @param array<int, true> $seenOffsets
     * @return array<int, array{state: string, generation: int, offset: int}>
     */
    private static function xrefEntriesFromOffsetChain(string $pdfBytes, int $offset, array $seenOffsets = []): array
    {
        $offset = self::skipWhitespace($pdfBytes, $offset);
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $section = self::xrefTableSectionAt($pdfBytes, $offset)
            ?? self::xrefStreamSectionAt($pdfBytes, $offset);
        if ($section === null) {
            return [];
        }

        $entries = $section['entries'];
        $previousOffset = self::previousXrefOffsetForSectionBody($pdfBytes, $section['trailer'], $offset);
        $entries = self::repairCurrentUpdateOffsetOwnerRows($pdfBytes, $entries, $previousOffset, $offset);
        if ($previousOffset !== null && $previousOffset >= 0) {
            foreach (self::xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $seenOffsets) as $objectNumber => $entry) {
                if (!isset($entries[$objectNumber])) {
                    $entries[$objectNumber] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{state: string, generation: int, offset: int}> $entries
     * @return array<int, array{state: string, generation: int, offset: int}>
     */
    private static function repairCurrentUpdateOffsetOwnerRows(
        string $pdfBytes,
        array $entries,
        ?int $previousOffset,
        int $currentOffset
    ): array {
        if ($previousOffset === null || $previousOffset < 0 || $previousOffset >= $currentOffset) {
            return $entries;
        }

        foreach ($entries as $objectNumber => $entry) {
            if (($entry['state'] ?? null) !== 'n') {
                continue;
            }

            $offset = $entry['offset'] ?? null;
            if (!is_int($offset)) {
                continue;
            }

            $owner = self::directObjectAtOffset($pdfBytes, $offset);
            if (
                $owner === null
                || $owner['offset'] <= $previousOffset
                || $owner['offset'] >= $currentOffset
            ) {
                continue;
            }

            $generation = (int) ($entry['generation'] ?? 0);
            if ($owner['object'] === (int) $objectNumber && $owner['generation'] === $generation) {
                continue;
            }

            unset($entries[$objectNumber]);
            $entries[$owner['object']] ??= [
                'state' => 'n',
                'generation' => $owner['generation'],
                'offset' => $owner['offset'],
            ];
        }

        return $entries;
    }

    /**
     * @return array{entries: array<int, array{state: string, generation: int, offset: int}>, trailer: string}|null
     */
    private static function xrefTableSectionAt(string $pdfBytes, int $offset): ?array
    {
        if (
            !self::keywordAt($pdfBytes, $offset, 'xref')
            || self::tokenStartsInsidePdfCompositeToken($pdfBytes, $offset)
        ) {
            return null;
        }

        $sectionBodyOffset = $offset + strlen('xref');
        if ($sectionBodyOffset >= strlen($pdfBytes)) {
            return null;
        }

        $afterKeyword = $pdfBytes[$sectionBodyOffset];
        if ($afterKeyword !== '%' && !self::isPdfWhitespace($afterKeyword)) {
            return null;
        }

        $trailerOffset = self::xrefTableTrailerKeywordOffset($pdfBytes, $sectionBodyOffset);
        if ($trailerOffset === null) {
            return null;
        }

        $entries = self::xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset));
        if ($entries === null) {
            return null;
        }

        $trailerBodyOffset = self::skipWhitespace($pdfBytes, $trailerOffset + strlen('trailer'));
        $trailer = substr($pdfBytes, $trailerBodyOffset, 512);
        if (substr($pdfBytes, $trailerBodyOffset, 2) === '<<') {
            $dictionary = self::readDictionaryAt($pdfBytes, $trailerBodyOffset);
            if ($dictionary !== null) {
                $trailer = $dictionary;
            }
        }

        return [
            'entries' => $entries,
            'trailer' => $trailer,
        ];
    }

    /**
     * @return array<int, array{state: string, generation: int, offset: int}>|null
     */
    private static function xrefTableRows(string $sectionBody): ?array
    {
        $sectionBody = str_replace(["\0", "\f"], ' ', $sectionBody);
        $lines = preg_split('/\r\n|\r|\n/', $sectionBody);
        if (!is_array($lines)) {
            return null;
        }

        $entries = [];
        $index = 0;
        $lineCount = count($lines);
        $foundSection = false;
        while ($index < $lineCount) {
            $line = trim($lines[$index]);
            if ($line === '' || str_starts_with($line, '%')) {
                $index++;
                continue;
            }

            if (preg_match('/^(\+?\d+)\s+(\+?\d+)(?:\s*(?:%.*)?)$/', $line, $header) !== 1) {
                if ($foundSection) {
                    if (preg_match('/^\d+\s+\d+\s+obj\b/s', $line) === 1) {
                        return $entries;
                    }

                    return null;
                }

                return null;
            }

            $foundSection = true;
            $startObject = (int) $header[1];
            $count = (int) $header[2];
            $index++;
            $row = 0;
            while ($row < $count && $index < $lineCount) {
                $rowLine = trim($lines[$index]);
                $index++;
                if ($rowLine === '' || str_starts_with($rowLine, '%')) {
                    continue;
                }

                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])(?:\s*(?:%.*)?)$/', $rowLine, $match) !== 1) {
                    return $entries === [] ? null : $entries;
                }

                $entries[$startObject + $row] = [
                    'state' => $match[3],
                    'generation' => (int) $match[2],
                    'offset' => (int) $match[1],
                ];
                $row++;
            }
        }

        return $foundSection ? $entries : null;
    }

    private static function xrefTableTrailerKeywordOffset(string $pdfBytes, int $offset): ?int
    {
        $length = strlen($pdfBytes);
        while ($offset < $length) {
            $char = $pdfBytes[$offset];

            if (substr($pdfBytes, $offset, 5) === '%%EOF' || self::keywordAt($pdfBytes, $offset, 'startxref')) {
                return null;
            }

            if ($char === '%') {
                self::skipComment($pdfBytes, $offset);
                continue;
            }

            if ($char === '(') {
                self::skipLiteralString($pdfBytes, $offset);
                continue;
            }

            $compositeEnd = self::skipPdfCompositeTokenAt($pdfBytes, $offset);
            if ($compositeEnd !== null) {
                $offset = $compositeEnd;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$offset + 1] ?? '') !== '<') {
                $hexEnd = self::skipPdfHexStringBoundaryAt($pdfBytes, $offset);
                if ($hexEnd !== null) {
                    $offset = $hexEnd;
                    continue;
                }
            }

            if (self::keywordAt($pdfBytes, $offset, 'trailer')) {
                $dictionaryOffset = self::skipWhitespace($pdfBytes, $offset + strlen('trailer'));
                if (substr($pdfBytes, $dictionaryOffset, 2) === '<<') {
                    return $offset;
                }
            }

            $offset++;
        }

        return null;
    }

    /**
     * @return array{entries: array<int, array{state: string, generation: int, offset: int}>, trailer: string}|null
     */
    private static function xrefStreamSectionAt(string $pdfBytes, int $offset): ?array
    {
        $definition = self::directObjectAtOffset($pdfBytes, $offset);
        if ($definition === null || preg_match('/\/Type\s*\/XRef\b/', $definition['body']) !== 1) {
            return null;
        }

        $dictionaryOffset = strpos($definition['body'], '<<');
        $dictionary = is_int($dictionaryOffset) ? self::readDictionaryAt($definition['body'], $dictionaryOffset) : null;
        if ($dictionary === null) {
            return null;
        }

        $stream = self::streamPayload($definition['body']);
        if ($stream === null) {
            return null;
        }

        $decoded = self::decodedStreamPayload($dictionary, $stream);
        if ($decoded === null) {
            return null;
        }

        $entries = self::xrefStreamRows($dictionary, $decoded, $pdfBytes, $offset);
        if ($entries === null) {
            return null;
        }

        return [
            'entries' => $entries,
            'trailer' => $dictionary,
        ];
    }

    /**
     * @return array{object: int, generation: int, offset: int, body: string}|null
     */
    private static function directObjectAtOffset(string $pdfBytes, int $offset): ?array
    {
        $offset = self::skipWhitespace($pdfBytes, $offset);
        $slice = substr($pdfBytes, $offset);
        if (preg_match('/^(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $slice, $match) !== 1) {
            return null;
        }

        return [
            'object' => (int) $match[1],
            'generation' => (int) $match[2],
            'offset' => $offset,
            'body' => $match[3],
        ];
    }

    private static function streamPayload(string $body): ?string
    {
        $streamOffset = strpos($body, 'stream');
        if ($streamOffset === false) {
            return null;
        }

        $payloadOffset = $streamOffset + strlen('stream');
        if (substr($body, $payloadOffset, 2) === "\r\n") {
            $payloadOffset += 2;
        } elseif (($body[$payloadOffset] ?? '') === "\n" || ($body[$payloadOffset] ?? '') === "\r") {
            $payloadOffset++;
        }

        $endOffset = strpos($body, 'endstream', $payloadOffset);
        if ($endOffset === false) {
            return null;
        }

        $payload = substr($body, $payloadOffset, $endOffset - $payloadOffset);
        return rtrim($payload, "\r\n");
    }

    private static function decodedStreamPayload(string $dictionary, string $stream): ?string
    {
        $filters = self::streamFilterNames($dictionary);
        if ($filters === null) {
            return null;
        }

        $decoded = $stream;
        foreach ($filters as $filter) {
            if ($filter === 'ASCIIHexDecode' || $filter === 'AHx') {
                $decoded = self::decodeAsciiHexStream($decoded);
                if ($decoded === null) {
                    return null;
                }

                continue;
            }

            if ($filter === 'ASCII85Decode' || $filter === 'A85') {
                $decoded = self::decodeAscii85Stream($decoded);
                if ($decoded === null) {
                    return null;
                }

                continue;
            }

            if ($filter === 'FlateDecode' || $filter === 'Fl') {
                $inflated = @gzuncompress($decoded);
                if (!is_string($inflated)) {
                    return null;
                }
                $decoded = $inflated;

                continue;
            }

            return null;
        }

        return $decoded;
    }

    private static function decodeAscii85Stream(string $stream): ?string
    {
        $body = self::trimPdfWhitespace($stream);
        if (str_starts_with($body, '<~')) {
            $body = substr($body, 2);
        }

        $terminator = strpos($body, '~>');
        if ($terminator === false) {
            return null;
        }
        $body = substr($body, 0, $terminator);

        $out = '';
        $group = [];
        for ($index = 0, $length = strlen($body); $index < $length; $index++) {
            $char = $body[$index];
            if (self::isPdfWhitespace($char)) {
                continue;
            }

            if ($char === 'z') {
                if ($group !== []) {
                    return null;
                }
                $out .= "\0\0\0\0";
                continue;
            }

            $ord = ord($char);
            if ($ord < 33 || $ord > 117) {
                return null;
            }

            $group[] = $ord - 33;
            if (count($group) === 5) {
                $decodedGroup = self::decodeAscii85Group($group, 4);
                if ($decodedGroup === null) {
                    return null;
                }

                $out .= $decodedGroup;
                $group = [];
            }
        }

        if ($group !== []) {
            $groupLength = count($group);
            if ($groupLength === 1) {
                return null;
            }
            while (count($group) < 5) {
                $group[] = 84;
            }

            $decodedGroup = self::decodeAscii85Group($group, $groupLength - 1);
            if ($decodedGroup === null) {
                return null;
            }

            $out .= $decodedGroup;
        }

        return $out;
    }

    private static function trimPdfWhitespace(string $value): string
    {
        $start = 0;
        $end = strlen($value);
        while ($start < $end && self::isPdfWhitespace($value[$start])) {
            $start++;
        }
        while ($end > $start && self::isPdfWhitespace($value[$end - 1])) {
            $end--;
        }

        return substr($value, $start, $end - $start);
    }

    /**
     * @param list<int> $group
     */
    private static function decodeAscii85Group(array $group, int $bytesToReturn): ?string
    {
        $value = 0;
        foreach ($group as $digit) {
            $value = ($value * 85) + $digit;
        }
        if ($value > 0xffffffff) {
            return null;
        }

        $bytes = '';
        for ($shift = 24; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($value >> $shift) & 0xff);
        }

        return substr($bytes, 0, $bytesToReturn);
    }

    /**
     * @return list<string>|null
     */
    private static function streamFilterNames(string $dictionary): ?array
    {
        $offset = self::valueOffsetAfterName($dictionary, 'Filter');
        if ($offset === null) {
            return [];
        }

        $char = $dictionary[$offset] ?? '';
        if ($char === '/') {
            $name = self::readNameAt($dictionary, $offset);
            return $name === null ? null : [$name['name']];
        }

        if ($char === '[') {
            $array = self::readArrayAt($dictionary, $offset);
            return $array === null ? null : self::filterNamesFromArray($array);
        }

        if (substr($dictionary, $offset, 4) === 'null' && !self::isPdfNameDataChar($dictionary[$offset + 4] ?? '')) {
            return [];
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private static function filterNamesFromArray(string $array): ?array
    {
        $filters = [];
        $offset = 1;
        $length = strlen($array) - 1;
        while ($offset < $length) {
            $offset = self::skipWhitespace($array, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($array[$offset] ?? '') === '%') {
                self::skipComment($array, $offset);
                continue;
            }

            if (($array[$offset] ?? '') === '/') {
                $name = self::readNameAt($array, $offset);
                if ($name === null) {
                    return null;
                }
                $filters[] = $name['name'];
                $offset = $name['end'];
                continue;
            }

            if (substr($array, $offset, 4) === 'null' && !self::isPdfNameDataChar($array[$offset + 4] ?? '')) {
                $offset += 4;
                continue;
            }

            return null;
        }

        return $filters;
    }

    private static function decodeAsciiHexStream(string $stream): ?string
    {
        $hex = '';
        $terminated = false;
        for ($index = 0, $length = strlen($stream); $index < $length; $index++) {
            $char = $stream[$index];
            if ($char === '>') {
                $terminated = true;
                break;
            }

            if (self::isPdfWhitespace($char)) {
                continue;
            }

            if (ctype_xdigit($char)) {
                $hex .= $char;
                continue;
            }

            return null;
        }

        if (!$terminated) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = '';
        for ($index = 0, $length = strlen($hex); $index < $length; $index += 2) {
            $decoded .= chr(hexdec(substr($hex, $index, 2)));
        }

        return $decoded;
    }

    private static function valueOffsetAfterName(string $dictionary, string $name): ?int
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '(?![A-Za-z0-9_.#-])/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return self::skipWhitespace($dictionary, $match[0][1] + strlen($match[0][0]));
    }

    /**
     * @return array<int, array{state: string, generation: int, offset: int}>|null
     */
    private static function xrefStreamRows(string $dictionary, string $decoded, string $pdfBytes, int $beforeOffset): ?array
    {
        $widths = self::integerArrayValueAfterName($dictionary, 'W', $pdfBytes, $beforeOffset);
        if ($widths === null || count($widths) < 3) {
            return null;
        }

        $widths = array_slice($widths, 0, 3);
        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return null;
        }

        $decodedEntryCount = strlen($decoded) % $entryWidth === 0 ? intdiv(strlen($decoded), $entryWidth) : null;
        $ranges = self::xrefIndexRanges($dictionary, $decodedEntryCount, $pdfBytes, $beforeOffset);
        if ($ranges === []) {
            return null;
        }

        $entries = [];
        $offset = 0;
        foreach ($ranges as $range) {
            [$startObject, $count] = $range;
            for ($index = 0; $index < $count; $index++) {
                if ($offset + $entryWidth > strlen($decoded)) {
                    break 2;
                }

                $fieldOffset = $offset;
                $type = $widths[0] === 0 ? 1 : self::xrefFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = self::xrefFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = self::xrefFieldValue($decoded, $fieldOffset, $widths[2]);
                $objectNumber = $startObject + $index;
                if (isset($entries[$objectNumber])) {
                    $offset += $entryWidth;
                    continue;
                }

                $entries[$objectNumber] = [
                    'state' => ($type === 0 || $type > 2) ? 'f' : 'n',
                    'generation' => $fieldThree,
                    'offset' => $fieldTwo,
                ];
                $offset += $entryWidth;
            }
        }

        return $entries;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private static function xrefIndexRanges(
        string $dictionary,
        ?int $decodedEntryCount,
        string $pdfBytes,
        int $beforeOffset
    ): array
    {
        $indexValues = self::integerArrayValueAfterName($dictionary, 'Index', $pdfBytes, $beforeOffset);
        if ($indexValues !== null && count($indexValues) >= 2) {
            $ranges = [];
            for ($index = 0; $index + 1 < count($indexValues); $index += 2) {
                $start = $indexValues[$index];
                $count = $indexValues[$index + 1];
                if ($start < 0 || $count < 0) {
                    return [];
                }
                $ranges[] = [$start, $count];
            }

            return $ranges;
        }

        $size = self::integerValueAfterName($dictionary, 'Size') ?? $decodedEntryCount;
        return $size === null || $size < 0 ? [] : [[0, $size]];
    }

    private static function xrefFieldValue(string $bytes, int &$offset, int $width): int
    {
        $value = 0;
        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) + ord($bytes[$offset + $index] ?? "\0");
        }
        $offset += $width;

        return $value;
    }

    /**
     * @return list<int>|null
     */
    private static function integerArrayValueAfterName(
        string $dictionary,
        string $name,
        ?string $pdfBytes = null,
        ?int $beforeOffset = null
    ): ?array
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\[([^\]]*)\]/s', $dictionary, $match) !== 1) {
            if ($pdfBytes === null || $beforeOffset === null) {
                return null;
            }

            $reference = self::objectReferenceAfterName($dictionary, $name);
            if ($reference === null) {
                return null;
            }

            $body = self::directObjectBodyForReferenceBeforeOffset(
                $pdfBytes,
                $reference['object'],
                $reference['generation'],
                $beforeOffset
            );
            if ($body === null || preg_match('/^\s*\[([^\]]*)\]\s*\z/s', $body, $match) !== 1) {
                return null;
            }
        }

        if (preg_match_all('/[+-]?\d+/', $match[1], $numbers) < 1) {
            return [];
        }

        return array_map('intval', $numbers[0]);
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private static function objectReferenceAfterName(string $dictionary, string $name): ?array
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+(\d+)\s+(\d+)\s+R\b/s', $dictionary, $match) !== 1) {
            return null;
        }

        return [
            'object' => (int) $match[1],
            'generation' => (int) $match[2],
        ];
    }

    private static function directObjectBodyForReferenceBeforeOffset(
        string $pdfBytes,
        int $objectNumber,
        int $generation,
        int $beforeOffset
    ): ?string
    {
        $object = self::directObjectForReferenceBeforeOffset($pdfBytes, $objectNumber, $generation, $beforeOffset);

        return $object['body'] ?? null;
    }

    /**
     * @return array{body: string, offset: int}|null
     */
    private static function directObjectForReferenceBeforeOffset(
        string $pdfBytes,
        int $objectNumber,
        int $generation,
        int $beforeOffset
    ): ?array
    {
        if ($objectNumber <= 0 || $generation < 0 || $beforeOffset <= 0) {
            return null;
        }

        $selected = null;
        $offset = 0;
        while (preg_match('/(\d+)\s+(\d+)\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $objectOffset = $match[0][1];
            if ($objectOffset >= $beforeOffset) {
                break;
            }

            $bodyStart = $objectOffset + strlen($match[0][0]);
            $bodyEnd = strpos($pdfBytes, 'endobj', $bodyStart);
            if ($bodyEnd === false) {
                break;
            }

            if ((int) $match[1][0] === $objectNumber && (int) $match[2][0] === $generation) {
                $selected = [
                    'body' => substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart),
                    'offset' => $objectOffset,
                ];
            }

            $offset = $bodyEnd + strlen('endobj');
        }

        return $selected;
    }

    /**
     * @return array{body: string, carrierOffset: int}|null
     */
    private static function compressedObjectStreamHelperForReferenceBeforeOffset(
        string $pdfBytes,
        int $objectNumber,
        int $generation,
        int $beforeOffset
    ): ?array {
        if ($objectNumber <= 0 || $generation !== 0 || $beforeOffset <= 0) {
            return null;
        }

        $selected = null;
        $offset = 0;
        while (preg_match('/(\d+)\s+(\d+)\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $objectOffset = $match[0][1];
            if ($objectOffset >= $beforeOffset) {
                break;
            }

            $bodyStart = $objectOffset + strlen($match[0][0]);
            $bodyEnd = strpos($pdfBytes, 'endobj', $bodyStart);
            if ($bodyEnd === false) {
                break;
            }

            $body = substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart);
            $memberBody = self::objectStreamMemberBody($body, $objectNumber);
            if ($memberBody !== null) {
                $selected = [
                    'body' => $memberBody,
                    'carrierOffset' => $objectOffset,
                ];
            }

            $offset = $bodyEnd + strlen('endobj');
        }

        return $selected;
    }

    private static function objectStreamMemberBody(string $objectBody, int $objectNumber): ?string
    {
        if (preg_match('/\/Type\s*\/ObjStm\b/s', $objectBody) !== 1) {
            return null;
        }

        $dictionaryOffset = strpos($objectBody, '<<');
        $dictionary = is_int($dictionaryOffset) ? self::readDictionaryAt($objectBody, $dictionaryOffset) : null;
        if ($dictionary === null) {
            return null;
        }

        $stream = self::streamPayload($objectBody);
        if ($stream === null) {
            return null;
        }

        $decoded = self::decodedStreamPayload($dictionary, $stream);
        if ($decoded === null) {
            return null;
        }

        $memberCount = self::integerValueAfterName($dictionary, 'N');
        $first = self::integerValueAfterName($dictionary, 'First');
        if ($memberCount === null || $memberCount < 1 || $first === null || $first <= 0 || $first >= strlen($decoded)) {
            return null;
        }

        $header = substr($decoded, 0, $first);
        if (preg_match_all('/(\d+)\s+(\d+)/', $header, $matches, PREG_SET_ORDER) < 1) {
            return null;
        }

        $members = [];
        foreach (array_slice($matches, 0, $memberCount) as $index => $member) {
            $members[] = [
                'object' => (int) $member[1],
                'offset' => (int) $member[2],
                'index' => $index,
            ];
        }

        foreach ($members as $index => $member) {
            if ($member['object'] !== $objectNumber) {
                continue;
            }

            $objectDataLength = strlen($decoded) - $first;
            $start = $member['offset'];
            $end = $objectDataLength;
            for ($nextIndex = $index + 1; $nextIndex < count($members); $nextIndex++) {
                $nextOffset = $members[$nextIndex]['offset'];
                if ($nextOffset > $start) {
                    $end = $nextOffset;
                    break;
                }
            }

            if ($start < 0 || $end <= $start || $end > $objectDataLength) {
                return null;
            }

            return trim(substr($decoded, $first + $start, $end - $start));
        }

        return null;
    }

    private static function previousXrefOffsetForSectionBody(string $pdfBytes, string $sectionBody, int $beforeOffset): ?int
    {
        $reference = self::objectReferenceAfterName($sectionBody, 'Prev');
        if ($reference === null) {
            $previousOffset = self::integerValueAfterName($sectionBody, 'Prev');
        } else {
            $direct = self::directObjectForReferenceBeforeOffset(
                $pdfBytes,
                $reference['object'],
                $reference['generation'],
                $beforeOffset
            );
            $compressed = self::compressedObjectStreamHelperForReferenceBeforeOffset(
                $pdfBytes,
                $reference['object'],
                $reference['generation'],
                $beforeOffset
            );

            $directBody = $direct['body'] ?? null;
            $compressedBody = $compressed['body'] ?? null;
            $body = null;
            if (
                is_string($compressedBody)
                && self::xrefPrevHelperBodyIsSafe($compressedBody)
                && ($direct === null || $compressed['carrierOffset'] > $direct['offset'])
            ) {
                $body = $compressedBody;
            } elseif (is_string($directBody) && self::xrefPrevHelperBodyIsSafe($directBody)) {
                $body = $directBody;
            } elseif (is_string($compressedBody) && self::xrefPrevHelperBodyIsSafe($compressedBody)) {
                $body = $compressedBody;
            }

            if ($body === null || preg_match('/^\s*([+-]?\d+)\s*\z/s', $body, $match) !== 1) {
                return null;
            }

            $previousOffset = (int) $match[1];
        }

        if ($previousOffset === null || $previousOffset < 0) {
            return $previousOffset;
        }

        if ($previousOffset >= $beforeOffset) {
            return self::latestXrefSectionOffsetBefore($pdfBytes, $beforeOffset);
        }

        if (!self::xrefSectionExistsAtOffset($pdfBytes, $previousOffset)) {
            return self::latestXrefSectionOffsetBefore($pdfBytes, $previousOffset + 1)
                ?? self::latestXrefSectionOffsetBefore($pdfBytes, $beforeOffset);
        }

        return $previousOffset;
    }

    private static function xrefPrevHelperBodyIsSafe(string $body): bool
    {
        return preg_match('/^\s*[+-]?\d+\s*\z/s', $body) === 1
            && preg_match('/\b(?:obj|endobj|stream|endstream|xref|trailer|startxref)\b/s', $body) !== 1;
    }

    private static function xrefSectionExistsAtOffset(string $pdfBytes, int $offset): bool
    {
        $offset = self::skipWhitespace($pdfBytes, $offset);

        return self::xrefTableSectionAt($pdfBytes, $offset) !== null
            || self::xrefStreamSectionAt($pdfBytes, $offset) !== null;
    }

    private static function latestXrefSectionOffsetBefore(string $pdfBytes, int $beforeOffset): ?int
    {
        $offsets = [];
        foreach (self::xrefTableKeywordOffsets($pdfBytes) as $offset) {
            if ($offset >= $beforeOffset) {
                continue;
            }

            if (self::xrefTableSectionAt($pdfBytes, $offset) !== null) {
                $offsets[] = $offset;
            }
        }

        if (preg_match_all('/\b\d+\s+\d+\s+obj\b/s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE) >= 1) {
            foreach ($matches[0] as $match) {
                $offset = $match[1] ?? null;
                if (!is_int($offset) || $offset >= $beforeOffset) {
                    continue;
                }

                if (self::xrefStreamSectionAt($pdfBytes, $offset) !== null) {
                    $offsets[] = $offset;
                }
            }
        }

        rsort($offsets, SORT_NUMERIC);

        return $offsets[0] ?? null;
    }

    private static function integerValueAfterName(string $dictionary, string $name): ?int
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+([+-]?\d+)\b/', $dictionary, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private static function keywordAt(string $pdfBytes, int $offset, string $keyword): bool
    {
        $length = strlen($keyword);
        if (substr($pdfBytes, $offset, $length) !== $keyword) {
            return false;
        }

        if ($offset > 0) {
            $before = $pdfBytes[$offset - 1];
            if ($before === '/' || (!self::isPdfWhitespace($before) && !str_contains('[]()<>{}%', $before))) {
                return false;
            }
        }

        $afterOffset = $offset + $length;
        if ($afterOffset >= strlen($pdfBytes)) {
            return true;
        }

        $after = $pdfBytes[$afterOffset];
        return self::isPdfWhitespace($after) || str_contains('[]()<>{}/%', $after);
    }

    /**
     * @return array{name: string, end: int}|null
     */
    private static function readNameAt(string $bytes, int $offset): ?array
    {
        if (($bytes[$offset] ?? '') !== '/') {
            return null;
        }

        $start = $offset + 1;
        $offset = $start;
        $length = strlen($bytes);
        while ($offset < $length && self::isPdfNameDataChar($bytes[$offset])) {
            $offset++;
        }

        if ($offset === $start) {
            return null;
        }

        return [
            'name' => self::decodePdfName(substr($bytes, $start, $offset - $start)),
            'end' => $offset,
        ];
    }

    private static function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    private static function isPdfNameDataChar(string $char): bool
    {
        return $char !== ''
            && !self::isPdfWhitespace($char)
            && !str_contains('()<>[]{}/%', $char);
    }

    private static function isPdfWhitespace(string $char): bool
    {
        return $char === "\0" || $char === "\t" || $char === "\n" || $char === "\f" || $char === "\r" || $char === ' ';
    }

    private static function skipWhitespace(string $bytes, int $offset): int
    {
        $length = strlen($bytes);
        while ($offset < $length) {
            $char = $bytes[$offset];
            if (!self::isPdfWhitespace($char)) {
                if ($char !== '%') {
                    break;
                }

                self::skipComment($bytes, $offset);
                continue;
            }
            $offset++;
        }

        return $offset;
    }

    private static function readDictionaryAt(string $bytes, int $offset): ?string
    {
        if (substr($bytes, $offset, 2) !== '<<') {
            return null;
        }

        $start = $offset;
        $length = strlen($bytes);
        $depth = 0;
        while ($offset < $length - 1) {
            $char = $bytes[$offset];
            if ($char === '%') {
                self::skipComment($bytes, $offset);
                continue;
            }

            if ($char === '(') {
                self::skipLiteralString($bytes, $offset);
                continue;
            }

            if ($char === '<' && ($bytes[$offset + 1] ?? '') !== '<') {
                self::skipHexString($bytes, $offset);
                continue;
            }

            if (substr($bytes, $offset, 2) === '<<') {
                $depth++;
                $offset += 2;
                continue;
            }

            if (substr($bytes, $offset, 2) === '>>') {
                $depth--;
                $offset += 2;
                if ($depth === 0) {
                    return substr($bytes, $start, $offset - $start);
                }
                continue;
            }

            $offset++;
        }

        return null;
    }

    private static function readArrayAt(string $bytes, int $offset): ?string
    {
        if (($bytes[$offset] ?? '') !== '[') {
            return null;
        }

        $start = $offset;
        $length = strlen($bytes);
        $depth = 0;
        while ($offset < $length) {
            $char = $bytes[$offset];
            if ($char === '%') {
                self::skipComment($bytes, $offset);
                continue;
            }

            if ($char === '(') {
                self::skipLiteralString($bytes, $offset);
                continue;
            }

            if ($char === '<' && ($bytes[$offset + 1] ?? '') !== '<') {
                self::skipHexString($bytes, $offset);
                continue;
            }

            if (substr($bytes, $offset, 2) === '<<') {
                $dictionary = self::readDictionaryAt($bytes, $offset);
                if ($dictionary === null) {
                    return null;
                }
                $offset += strlen($dictionary);
                continue;
            }

            if ($char === '[') {
                $depth++;
                $offset++;
                continue;
            }

            if ($char === ']') {
                $depth--;
                $offset++;
                if ($depth === 0) {
                    return substr($bytes, $start, $offset - $start);
                }
                continue;
            }

            $offset++;
        }

        return null;
    }

    private static function skipComment(string $bytes, int &$offset): void
    {
        $length = strlen($bytes);
        while ($offset < $length && $bytes[$offset] !== "\n" && $bytes[$offset] !== "\r") {
            $offset++;
        }
    }

    private static function skipLiteralString(string $bytes, int &$offset): void
    {
        $depth = 1;
        $offset++;
        $length = strlen($bytes);
        while ($offset < $length && $depth > 0) {
            $char = $bytes[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            $offset++;
        }
    }

    private static function skipHexString(string $bytes, int &$offset): void
    {
        $end = strpos($bytes, '>', $offset + 1);
        $offset = $end === false ? strlen($bytes) : $end + 1;
    }
}
