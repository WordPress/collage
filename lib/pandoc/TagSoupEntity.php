<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TagSoupEntity
{
    /**
     * HTML5 legacy named references that TagSoup resolves without a semicolon.
     * PHP's html_entity_decode intentionally does not decode these forms.
     *
     * @var array<string, string>
     */
    private const LEGACY_NO_SEMICOLON = [
        'AElig' => "\u{00C6}",
        'AMP' => '&',
        'Aacute' => "\u{00C1}",
        'Acirc' => "\u{00C2}",
        'Agrave' => "\u{00C0}",
        'Aring' => "\u{00C5}",
        'Atilde' => "\u{00C3}",
        'Auml' => "\u{00C4}",
        'COPY' => "\u{00A9}",
        'Ccedil' => "\u{00C7}",
        'ETH' => "\u{00D0}",
        'Eacute' => "\u{00C9}",
        'Ecirc' => "\u{00CA}",
        'Egrave' => "\u{00C8}",
        'Euml' => "\u{00CB}",
        'GT' => '>',
        'Iacute' => "\u{00CD}",
        'Icirc' => "\u{00CE}",
        'Igrave' => "\u{00CC}",
        'Iuml' => "\u{00CF}",
        'LT' => '<',
        'Ntilde' => "\u{00D1}",
        'Oacute' => "\u{00D3}",
        'Ocirc' => "\u{00D4}",
        'Ograve' => "\u{00D2}",
        'Oslash' => "\u{00D8}",
        'Otilde' => "\u{00D5}",
        'Ouml' => "\u{00D6}",
        'QUOT' => '"',
        'REG' => "\u{00AE}",
        'THORN' => "\u{00DE}",
        'Uacute' => "\u{00DA}",
        'Ucirc' => "\u{00DB}",
        'Ugrave' => "\u{00D9}",
        'Uuml' => "\u{00DC}",
        'Yacute' => "\u{00DD}",
        'aacute' => "\u{00E1}",
        'acirc' => "\u{00E2}",
        'acute' => "\u{00B4}",
        'aelig' => "\u{00E6}",
        'agrave' => "\u{00E0}",
        'amp' => '&',
        'aring' => "\u{00E5}",
        'atilde' => "\u{00E3}",
        'auml' => "\u{00E4}",
        'brvbar' => "\u{00A6}",
        'ccedil' => "\u{00E7}",
        'cedil' => "\u{00B8}",
        'cent' => "\u{00A2}",
        'copy' => "\u{00A9}",
        'curren' => "\u{00A4}",
        'deg' => "\u{00B0}",
        'divide' => "\u{00F7}",
        'eacute' => "\u{00E9}",
        'ecirc' => "\u{00EA}",
        'egrave' => "\u{00E8}",
        'eth' => "\u{00F0}",
        'euml' => "\u{00EB}",
        'frac12' => "\u{00BD}",
        'frac14' => "\u{00BC}",
        'frac34' => "\u{00BE}",
        'gt' => '>',
        'iacute' => "\u{00ED}",
        'icirc' => "\u{00EE}",
        'iexcl' => "\u{00A1}",
        'igrave' => "\u{00EC}",
        'iquest' => "\u{00BF}",
        'iuml' => "\u{00EF}",
        'laquo' => "\u{00AB}",
        'lt' => '<',
        'macr' => "\u{00AF}",
        'micro' => "\u{00B5}",
        'middot' => "\u{00B7}",
        'nbsp' => "\u{00A0}",
        'not' => "\u{00AC}",
        'ntilde' => "\u{00F1}",
        'oacute' => "\u{00F3}",
        'ocirc' => "\u{00F4}",
        'ograve' => "\u{00F2}",
        'ordf' => "\u{00AA}",
        'ordm' => "\u{00BA}",
        'oslash' => "\u{00F8}",
        'otilde' => "\u{00F5}",
        'ouml' => "\u{00F6}",
        'para' => "\u{00B6}",
        'plusmn' => "\u{00B1}",
        'pound' => "\u{00A3}",
        'quot' => '"',
        'raquo' => "\u{00BB}",
        'reg' => "\u{00AE}",
        'sect' => "\u{00A7}",
        'shy' => "\u{00AD}",
        'sup1' => "\u{00B9}",
        'sup2' => "\u{00B2}",
        'sup3' => "\u{00B3}",
        'szlig' => "\u{00DF}",
        'thorn' => "\u{00FE}",
        'times' => "\u{00D7}",
        'uacute' => "\u{00FA}",
        'ucirc' => "\u{00FB}",
        'ugrave' => "\u{00F9}",
        'uml' => "\u{00A8}",
        'uuml' => "\u{00FC}",
        'yacute' => "\u{00FD}",
        'yen' => "\u{00A5}",
        'yuml' => "\u{00FF}",
    ];

    public static function lookup(string $entity): ?string
    {
        if (str_starts_with($entity, '#')) {
            return self::lookupNumeric(substr($entity, 1));
        }

        if (!str_ends_with($entity, ';') && isset(self::LEGACY_NO_SEMICOLON[$entity])) {
            return self::LEGACY_NO_SEMICOLON[$entity];
        }

        $source = '&' . $entity;
        $decoded = html_entity_decode($source, ENT_HTML5 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $decoded === $source ? null : $decoded;
    }

    public static function escapeXml(string $text): string
    {
        return strtr($text, [
            '&' => '&amp;',
            '"' => '&quot;',
            '<' => '&lt;',
            '>' => '&gt;',
            "'" => '&#39;',
        ]);
    }

    private static function lookupNumeric(string $entity): ?string
    {
        if ($entity === '') {
            return null;
        }

        $base = 10;
        $digits = $entity;
        if ($digits[0] === 'x' || $digits[0] === 'X') {
            $base = 16;
            $digits = substr($digits, 1);
        }

        if ($digits === '' || preg_match($base === 16 ? '/^[0-9a-fA-F]+$/' : '/^[0-9]+$/', $digits) !== 1) {
            return null;
        }

        $codepoint = intval($digits, $base);
        if ($codepoint < 0 || $codepoint > 0x10FFFF) {
            return null;
        }

        if ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) {
            return null;
        }

        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }

        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        return chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F));
    }
}
