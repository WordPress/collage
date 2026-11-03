<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class UnicodeText
{
    private const REPLACEMENT = "\xEF\xBF\xBD";
    private const TAB_STOP_COLUMNS = 4;

    /** @var array<string, string> */
    private const CANONICAL_DECOMPOSITIONS = [
        "\u{00C0}" => "A\u{0300}",
        "\u{00C1}" => "A\u{0301}",
        "\u{00C2}" => "A\u{0302}",
        "\u{00C3}" => "A\u{0303}",
        "\u{00C4}" => "A\u{0308}",
        "\u{00C5}" => "A\u{030A}",
        "\u{00C7}" => "C\u{0327}",
        "\u{00C9}" => "E\u{0301}",
        "\u{00D1}" => "N\u{0303}",
        "\u{00D3}" => "O\u{0301}",
        "\u{00D6}" => "O\u{0308}",
        "\u{00DC}" => "U\u{0308}",
        "\u{00DD}" => "Y\u{0301}",
        "\u{00E0}" => "a\u{0300}",
        "\u{00E1}" => "a\u{0301}",
        "\u{00E2}" => "a\u{0302}",
        "\u{00E3}" => "a\u{0303}",
        "\u{00E4}" => "a\u{0308}",
        "\u{00E5}" => "a\u{030A}",
        "\u{00E7}" => "c\u{0327}",
        "\u{00E9}" => "e\u{0301}",
        "\u{00F1}" => "n\u{0303}",
        "\u{00F3}" => "o\u{0301}",
        "\u{00F6}" => "o\u{0308}",
        "\u{00FC}" => "u\u{0308}",
        "\u{00FD}" => "y\u{0301}",
        "\u{0100}" => "A\u{0304}",
        "\u{0101}" => "a\u{0304}",
        "\u{0104}" => "A\u{0328}",
        "\u{0105}" => "a\u{0328}",
        "\u{0106}" => "C\u{0301}",
        "\u{0107}" => "c\u{0301}",
        "\u{010C}" => "C\u{030C}",
        "\u{010D}" => "c\u{030C}",
        "\u{010E}" => "D\u{030C}",
        "\u{010F}" => "d\u{030C}",
        "\u{0112}" => "E\u{0304}",
        "\u{0113}" => "e\u{0304}",
        "\u{0118}" => "E\u{0328}",
        "\u{0119}" => "e\u{0328}",
        "\u{011A}" => "E\u{030C}",
        "\u{011B}" => "e\u{030C}",
        "\u{012A}" => "I\u{0304}",
        "\u{012B}" => "i\u{0304}",
        "\u{0143}" => "N\u{0301}",
        "\u{0144}" => "n\u{0301}",
        "\u{0147}" => "N\u{030C}",
        "\u{0148}" => "n\u{030C}",
        "\u{014C}" => "O\u{0304}",
        "\u{014D}" => "o\u{0304}",
        "\u{0150}" => "O\u{030B}",
        "\u{0151}" => "o\u{030B}",
        "\u{0158}" => "R\u{030C}",
        "\u{0159}" => "r\u{030C}",
        "\u{015A}" => "S\u{0301}",
        "\u{015B}" => "s\u{0301}",
        "\u{0160}" => "S\u{030C}",
        "\u{0161}" => "s\u{030C}",
        "\u{016A}" => "U\u{0304}",
        "\u{016B}" => "u\u{0304}",
        "\u{016E}" => "U\u{030A}",
        "\u{016F}" => "u\u{030A}",
        "\u{0170}" => "U\u{030B}",
        "\u{0171}" => "u\u{030B}",
        "\u{0179}" => "Z\u{0301}",
        "\u{017A}" => "z\u{0301}",
        "\u{017B}" => "Z\u{0307}",
        "\u{017C}" => "z\u{0307}",
        "\u{017D}" => "Z\u{030C}",
        "\u{017E}" => "z\u{030C}",
        "\u{0218}" => "S\u{0326}",
        "\u{0219}" => "s\u{0326}",
        "\u{021A}" => "T\u{0326}",
        "\u{021B}" => "t\u{0326}",
        "\u{2126}" => "\u{03A9}",
        "\u{212A}" => 'K',
        "\u{212B}" => "A\u{030A}",
        "\u{1E0B}" => "d\u{0307}",
        "\u{1E0C}" => "D\u{0323}",
        "\u{1E0D}" => "d\u{0323}",
    ];

    /** @var array<string, string> */
    private const COMPOSITIONS = [
        "A\u{0300}" => "\u{00C0}",
        "A\u{0301}" => "\u{00C1}",
        "A\u{0302}" => "\u{00C2}",
        "A\u{0303}" => "\u{00C3}",
        "A\u{0304}" => "\u{0100}",
        "A\u{0308}" => "\u{00C4}",
        "A\u{030A}" => "\u{00C5}",
        "A\u{0328}" => "\u{0104}",
        "C\u{0301}" => "\u{0106}",
        "C\u{030C}" => "\u{010C}",
        "C\u{0327}" => "\u{00C7}",
        "D\u{030C}" => "\u{010E}",
        "D\u{0323}" => "\u{1E0C}",
        "E\u{0301}" => "\u{00C9}",
        "E\u{0304}" => "\u{0112}",
        "E\u{030C}" => "\u{011A}",
        "E\u{0328}" => "\u{0118}",
        "I\u{0304}" => "\u{012A}",
        "N\u{0301}" => "\u{0143}",
        "N\u{030C}" => "\u{0147}",
        "N\u{0303}" => "\u{00D1}",
        "O\u{0301}" => "\u{00D3}",
        "O\u{0304}" => "\u{014C}",
        "O\u{0308}" => "\u{00D6}",
        "O\u{030B}" => "\u{0150}",
        "R\u{030C}" => "\u{0158}",
        "S\u{0301}" => "\u{015A}",
        "S\u{030C}" => "\u{0160}",
        "S\u{0326}" => "\u{0218}",
        "T\u{0326}" => "\u{021A}",
        "U\u{0304}" => "\u{016A}",
        "U\u{030A}" => "\u{016E}",
        "U\u{0308}" => "\u{00DC}",
        "U\u{030B}" => "\u{0170}",
        "Y\u{0301}" => "\u{00DD}",
        "Z\u{0301}" => "\u{0179}",
        "Z\u{0307}" => "\u{017B}",
        "Z\u{030C}" => "\u{017D}",
        "a\u{0300}" => "\u{00E0}",
        "a\u{0301}" => "\u{00E1}",
        "a\u{0302}" => "\u{00E2}",
        "a\u{0303}" => "\u{00E3}",
        "a\u{0304}" => "\u{0101}",
        "a\u{0308}" => "\u{00E4}",
        "a\u{030A}" => "\u{00E5}",
        "a\u{0328}" => "\u{0105}",
        "c\u{0301}" => "\u{0107}",
        "c\u{030C}" => "\u{010D}",
        "c\u{0327}" => "\u{00E7}",
        "d\u{030C}" => "\u{010F}",
        "d\u{0307}" => "\u{1E0B}",
        "d\u{0323}" => "\u{1E0D}",
        "e\u{0301}" => "\u{00E9}",
        "e\u{0304}" => "\u{0113}",
        "e\u{030C}" => "\u{011B}",
        "e\u{0328}" => "\u{0119}",
        "i\u{0304}" => "\u{012B}",
        "n\u{0301}" => "\u{0144}",
        "n\u{030C}" => "\u{0148}",
        "n\u{0303}" => "\u{00F1}",
        "o\u{0301}" => "\u{00F3}",
        "o\u{0304}" => "\u{014D}",
        "o\u{0308}" => "\u{00F6}",
        "o\u{030B}" => "\u{0151}",
        "r\u{030C}" => "\u{0159}",
        "s\u{0301}" => "\u{015B}",
        "s\u{030C}" => "\u{0161}",
        "s\u{0326}" => "\u{0219}",
        "t\u{0326}" => "\u{021B}",
        "u\u{0304}" => "\u{016B}",
        "u\u{030A}" => "\u{016F}",
        "u\u{0308}" => "\u{00FC}",
        "u\u{030B}" => "\u{0171}",
        "y\u{0301}" => "\u{00FD}",
        "z\u{0301}" => "\u{017A}",
        "z\u{0307}" => "\u{017C}",
        "z\u{030C}" => "\u{017E}",
    ];

    /** @var array<string, string> */
    private const COMPATIBILITY_DECOMPOSITIONS = [
        "\u{00A0}" => ' ',
        "\u{212B}" => "A\u{030A}",
        "\u{2460}" => '1',
        "\u{2461}" => '2',
        "\u{2462}" => '3',
        "\u{FB00}" => 'ff',
        "\u{FB01}" => 'fi',
        "\u{FB02}" => 'fl',
        "\u{FB03}" => 'ffi',
        "\u{FB04}" => 'ffl',
    ];

    /** @var array<int, int> */
    private const WINDOWS_1252_CONTROLS = [
        0x80 => 0x20ac,
        0x82 => 0x201a,
        0x83 => 0x0192,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x88 => 0x02c6,
        0x89 => 0x2030,
        0x8a => 0x0160,
        0x8b => 0x2039,
        0x8c => 0x0152,
        0x8e => 0x017d,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x98 => 0x02dc,
        0x99 => 0x2122,
        0x9a => 0x0161,
        0x9b => 0x203a,
        0x9c => 0x0153,
        0x9e => 0x017e,
        0x9f => 0x0178,
    ];

    /** @var array<int, int> */
    private const ISO_8859_15_REPLACEMENTS = [
        0xa4 => 0x20ac,
        0xa6 => 0x0160,
        0xa8 => 0x0161,
        0xb4 => 0x017d,
        0xb8 => 0x017e,
        0xbc => 0x0152,
        0xbd => 0x0153,
        0xbe => 0x0178,
    ];

    /** @var array<int, int> */
    private const WINDOWS_1250_REPLACEMENTS = [
        0x80 => 0x20ac,
        0x82 => 0x201a,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x89 => 0x2030,
        0x8a => 0x0160,
        0x8b => 0x2039,
        0x8c => 0x015a,
        0x8d => 0x0164,
        0x8e => 0x017d,
        0x8f => 0x0179,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x99 => 0x2122,
        0x9a => 0x0161,
        0x9b => 0x203a,
        0x9c => 0x015b,
        0x9d => 0x0165,
        0x9e => 0x017e,
        0x9f => 0x017a,
        0xa1 => 0x02c7,
        0xa2 => 0x02d8,
        0xa3 => 0x0141,
        0xa5 => 0x0104,
        0xaa => 0x015e,
        0xaf => 0x017b,
        0xb2 => 0x02db,
        0xb3 => 0x0142,
        0xb9 => 0x0105,
        0xba => 0x015f,
        0xbc => 0x013d,
        0xbd => 0x02dd,
        0xbe => 0x013e,
        0xbf => 0x017c,
        0xc0 => 0x0154,
        0xc3 => 0x0102,
        0xc5 => 0x0139,
        0xc6 => 0x0106,
        0xc8 => 0x010c,
        0xca => 0x0118,
        0xcc => 0x011a,
        0xcf => 0x010e,
        0xd0 => 0x0110,
        0xd1 => 0x0143,
        0xd2 => 0x0147,
        0xd5 => 0x0150,
        0xd8 => 0x0158,
        0xd9 => 0x016e,
        0xdb => 0x0170,
        0xde => 0x0162,
        0xe0 => 0x0155,
        0xe3 => 0x0103,
        0xe5 => 0x013a,
        0xe6 => 0x0107,
        0xe8 => 0x010d,
        0xea => 0x0119,
        0xec => 0x011b,
        0xef => 0x010f,
        0xf0 => 0x0111,
        0xf1 => 0x0144,
        0xf2 => 0x0148,
        0xf5 => 0x0151,
        0xf8 => 0x0159,
        0xf9 => 0x016f,
        0xfb => 0x0171,
        0xfe => 0x0163,
        0xff => 0x02d9,
    ];

    /** @var array<int, int> */
    private const WINDOWS_1251_REPLACEMENTS = [
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

    /** @var array<int, int> */
    private const WINDOWS_1253_REPLACEMENTS = [
        0x80 => 0x20ac,
        0x82 => 0x201a,
        0x83 => 0x0192,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x89 => 0x2030,
        0x8b => 0x2039,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x99 => 0x2122,
        0x9b => 0x203a,
        0xa1 => 0x0385,
        0xa2 => 0x0386,
        0xaf => 0x2015,
        0xb4 => 0x0384,
        0xb8 => 0x0388,
        0xb9 => 0x0389,
        0xba => 0x038a,
        0xbc => 0x038c,
        0xbe => 0x038e,
        0xbf => 0x038f,
    ];

    /** @var array<int, true> */
    private const WINDOWS_1253_UNDEFINED = [
        0x81 => true,
        0x88 => true,
        0x8a => true,
        0x8c => true,
        0x8d => true,
        0x8e => true,
        0x8f => true,
        0x90 => true,
        0x98 => true,
        0x9a => true,
        0x9c => true,
        0x9d => true,
        0x9e => true,
        0x9f => true,
        0xaa => true,
        0xd2 => true,
        0xff => true,
    ];

    /** @var array<int, true> */
    private const WINDOWS_1254_UNDEFINED = [
        0x81 => true,
        0x8d => true,
        0x8e => true,
        0x8f => true,
        0x90 => true,
        0x9d => true,
        0x9e => true,
    ];

    /** @var array<int, int> */
    private const WINDOWS_1255_REPLACEMENTS = [
        0x80 => 0x20ac,
        0x82 => 0x201a,
        0x83 => 0x0192,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x88 => 0x02c6,
        0x89 => 0x2030,
        0x8b => 0x2039,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x98 => 0x02dc,
        0x99 => 0x2122,
        0x9b => 0x203a,
        0xa4 => 0x20aa,
        0xaa => 0x00d7,
        0xba => 0x00f7,
        0xc0 => 0x05b0,
        0xc1 => 0x05b1,
        0xc2 => 0x05b2,
        0xc3 => 0x05b3,
        0xc4 => 0x05b4,
        0xc5 => 0x05b5,
        0xc6 => 0x05b6,
        0xc7 => 0x05b7,
        0xc8 => 0x05b8,
        0xc9 => 0x05b9,
        0xca => 0x05ba,
        0xcb => 0x05bb,
        0xcc => 0x05bc,
        0xcd => 0x05bd,
        0xce => 0x05be,
        0xcf => 0x05bf,
        0xd0 => 0x05c0,
        0xd1 => 0x05c1,
        0xd2 => 0x05c2,
        0xd3 => 0x05c3,
        0xd4 => 0x05f0,
        0xd5 => 0x05f1,
        0xd6 => 0x05f2,
        0xd7 => 0x05f3,
        0xd8 => 0x05f4,
        0xe0 => 0x05d0,
        0xe1 => 0x05d1,
        0xe2 => 0x05d2,
        0xe3 => 0x05d3,
        0xe4 => 0x05d4,
        0xe5 => 0x05d5,
        0xe6 => 0x05d6,
        0xe7 => 0x05d7,
        0xe8 => 0x05d8,
        0xe9 => 0x05d9,
        0xea => 0x05da,
        0xeb => 0x05db,
        0xec => 0x05dc,
        0xed => 0x05dd,
        0xee => 0x05de,
        0xef => 0x05df,
        0xf0 => 0x05e0,
        0xf1 => 0x05e1,
        0xf2 => 0x05e2,
        0xf3 => 0x05e3,
        0xf4 => 0x05e4,
        0xf5 => 0x05e5,
        0xf6 => 0x05e6,
        0xf7 => 0x05e7,
        0xf8 => 0x05e8,
        0xf9 => 0x05e9,
        0xfa => 0x05ea,
        0xfd => 0x200e,
        0xfe => 0x200f,
    ];

    /** @var array<int, true> */
    private const WINDOWS_1255_UNDEFINED = [
        0x81 => true,
        0x8a => true,
        0x8c => true,
        0x8d => true,
        0x8e => true,
        0x8f => true,
        0x90 => true,
        0x9a => true,
        0x9c => true,
        0x9d => true,
        0x9e => true,
        0x9f => true,
        0xd9 => true,
        0xda => true,
        0xdb => true,
        0xdc => true,
        0xdd => true,
        0xde => true,
        0xdf => true,
        0xfb => true,
        0xfc => true,
        0xff => true,
    ];

    /** @var array<int, int> */
    private const WINDOWS_1256_REPLACEMENTS = [
        0x80 => 0x20ac,
        0x81 => 0x067e,
        0x82 => 0x201a,
        0x83 => 0x0192,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x88 => 0x02c6,
        0x89 => 0x2030,
        0x8a => 0x0679,
        0x8b => 0x2039,
        0x8c => 0x0152,
        0x8d => 0x0686,
        0x8e => 0x0698,
        0x8f => 0x0688,
        0x90 => 0x06af,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x98 => 0x06a9,
        0x99 => 0x2122,
        0x9a => 0x0691,
        0x9b => 0x203a,
        0x9c => 0x0153,
        0x9d => 0x200c,
        0x9e => 0x200d,
        0x9f => 0x06ba,
        0xa1 => 0x060c,
        0xaa => 0x06be,
        0xba => 0x061b,
        0xbf => 0x061f,
        0xc0 => 0x06c1,
        0xc1 => 0x0621,
        0xc2 => 0x0622,
        0xc3 => 0x0623,
        0xc4 => 0x0624,
        0xc5 => 0x0625,
        0xc6 => 0x0626,
        0xc7 => 0x0627,
        0xc8 => 0x0628,
        0xc9 => 0x0629,
        0xca => 0x062a,
        0xcb => 0x062b,
        0xcc => 0x062c,
        0xcd => 0x062d,
        0xce => 0x062e,
        0xcf => 0x062f,
        0xd0 => 0x0630,
        0xd1 => 0x0631,
        0xd2 => 0x0632,
        0xd3 => 0x0633,
        0xd4 => 0x0634,
        0xd5 => 0x0635,
        0xd6 => 0x0636,
        0xd8 => 0x0637,
        0xd9 => 0x0638,
        0xda => 0x0639,
        0xdb => 0x063a,
        0xdc => 0x0640,
        0xdd => 0x0641,
        0xde => 0x0642,
        0xdf => 0x0643,
        0xe1 => 0x0644,
        0xe3 => 0x0645,
        0xe4 => 0x0646,
        0xe5 => 0x0647,
        0xe6 => 0x0648,
        0xec => 0x0649,
        0xed => 0x064a,
        0xf0 => 0x064b,
        0xf1 => 0x064c,
        0xf2 => 0x064d,
        0xf3 => 0x064e,
        0xf5 => 0x064f,
        0xf6 => 0x0650,
        0xf8 => 0x0651,
        0xfa => 0x0652,
        0xfd => 0x200e,
        0xfe => 0x200f,
        0xff => 0x06d2,
    ];

    /** @var array<int, int> */
    private const WINDOWS_1257_REPLACEMENTS = [
        0x80 => 0x20ac,
        0x82 => 0x201a,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x89 => 0x2030,
        0x8b => 0x2039,
        0x8d => 0x00a8,
        0x8e => 0x02c7,
        0x8f => 0x00b8,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x99 => 0x2122,
        0x9b => 0x203a,
        0x9d => 0x00af,
        0x9e => 0x02db,
        0xa8 => 0x00d8,
        0xaa => 0x0156,
        0xaf => 0x00c6,
        0xb8 => 0x00f8,
        0xba => 0x0157,
        0xbf => 0x00e6,
        0xc0 => 0x0104,
        0xc1 => 0x012e,
        0xc2 => 0x0100,
        0xc3 => 0x0106,
        0xc6 => 0x0118,
        0xc7 => 0x0112,
        0xc8 => 0x010c,
        0xca => 0x0179,
        0xcb => 0x0116,
        0xcc => 0x0122,
        0xcd => 0x0136,
        0xce => 0x012a,
        0xcf => 0x013b,
        0xd0 => 0x0160,
        0xd1 => 0x0143,
        0xd2 => 0x0145,
        0xd4 => 0x014c,
        0xd8 => 0x0172,
        0xd9 => 0x0141,
        0xda => 0x015a,
        0xdb => 0x016a,
        0xdd => 0x017b,
        0xde => 0x017d,
        0xe0 => 0x0105,
        0xe1 => 0x012f,
        0xe2 => 0x0101,
        0xe3 => 0x0107,
        0xe6 => 0x0119,
        0xe7 => 0x0113,
        0xe8 => 0x010d,
        0xea => 0x017a,
        0xeb => 0x0117,
        0xec => 0x0123,
        0xed => 0x0137,
        0xee => 0x012b,
        0xef => 0x013c,
        0xf0 => 0x0161,
        0xf1 => 0x0144,
        0xf2 => 0x0146,
        0xf4 => 0x014d,
        0xf8 => 0x0173,
        0xf9 => 0x0142,
        0xfa => 0x015b,
        0xfb => 0x016b,
        0xfd => 0x017c,
        0xfe => 0x017e,
        0xff => 0x02d9,
    ];

    /** @var array<int, true> */
    private const WINDOWS_1257_UNDEFINED = [
        0xa1 => true,
        0xa5 => true,
    ];

    /** @var array<int, int> */
    private const WINDOWS_1258_REPLACEMENTS = [
        0x80 => 0x20ac,
        0x81 => 0x0081,
        0x82 => 0x201a,
        0x83 => 0x0192,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x88 => 0x02c6,
        0x89 => 0x2030,
        0x8a => 0x008a,
        0x8b => 0x2039,
        0x8c => 0x0152,
        0x8d => 0x008d,
        0x8e => 0x008e,
        0x8f => 0x008f,
        0x90 => 0x0090,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x98 => 0x02dc,
        0x99 => 0x2122,
        0x9a => 0x009a,
        0x9b => 0x203a,
        0x9c => 0x0153,
        0x9d => 0x009d,
        0x9e => 0x009e,
        0x9f => 0x0178,
        0xc3 => 0x0102,
        0xcc => 0x0300,
        0xd0 => 0x0110,
        0xd2 => 0x0309,
        0xd5 => 0x01a0,
        0xdd => 0x01af,
        0xde => 0x0303,
        0xec => 0x0301,
        0xf0 => 0x0111,
        0xf2 => 0x0323,
        0xf5 => 0x01a1,
        0xfd => 0x01b0,
        0xfe => 0x20ab,
    ];

    /** @var array<int, int> */
    private const WINDOWS_874_CONTROLS = [
        0x80 => 0x20ac,
        0x85 => 0x2026,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
    ];

    /** @var array<int, int> */
    private const CP165_OVERRIDES = [
        0x24 => 0x066a,
        0x9b => 0xfef9,
        0x9c => 0xfefa,
        0x9f => 0xfe73,
        0xa6 => 0xfe87,
        0xa7 => 0xfe88,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const KOI8_R_REPLACEMENTS = [
        0x80 => 0x2500,
        0x81 => 0x2502,
        0x82 => 0x250c,
        0x83 => 0x2510,
        0x84 => 0x2514,
        0x85 => 0x2518,
        0x86 => 0x251c,
        0x87 => 0x2524,
        0x88 => 0x252c,
        0x89 => 0x2534,
        0x8a => 0x253c,
        0x8b => 0x2580,
        0x8c => 0x2584,
        0x8d => 0x2588,
        0x8e => 0x258c,
        0x8f => 0x2590,
        0x90 => 0x2591,
        0x91 => 0x2592,
        0x92 => 0x2593,
        0x93 => 0x2320,
        0x94 => 0x25a0,
        0x95 => 0x2219,
        0x96 => 0x221a,
        0x97 => 0x2248,
        0x98 => 0x2264,
        0x99 => 0x2265,
        0x9a => 0x00a0,
        0x9b => 0x2321,
        0x9c => 0x00b0,
        0x9d => 0x00b2,
        0x9e => 0x00b7,
        0x9f => 0x00f7,
        0xa0 => 0x2550,
        0xa1 => 0x2551,
        0xa2 => 0x2552,
        0xa3 => 0x0451,
        0xa4 => 0x2553,
        0xa5 => 0x2554,
        0xa6 => 0x2555,
        0xa7 => 0x2556,
        0xa8 => 0x2557,
        0xa9 => 0x2558,
        0xaa => 0x2559,
        0xab => 0x255a,
        0xac => 0x255b,
        0xad => 0x255c,
        0xae => 0x255d,
        0xaf => 0x255e,
        0xb0 => 0x255f,
        0xb1 => 0x2560,
        0xb2 => 0x2561,
        0xb3 => 0x0401,
        0xb4 => 0x2562,
        0xb5 => 0x2563,
        0xb6 => 0x2564,
        0xb7 => 0x2565,
        0xb8 => 0x2566,
        0xb9 => 0x2567,
        0xba => 0x2568,
        0xbb => 0x2569,
        0xbc => 0x256a,
        0xbd => 0x256b,
        0xbe => 0x256c,
        0xbf => 0x00a9,
        0xc0 => 0x044e,
        0xc1 => 0x0430,
        0xc2 => 0x0431,
        0xc3 => 0x0446,
        0xc4 => 0x0434,
        0xc5 => 0x0435,
        0xc6 => 0x0444,
        0xc7 => 0x0433,
        0xc8 => 0x0445,
        0xc9 => 0x0438,
        0xca => 0x0439,
        0xcb => 0x043a,
        0xcc => 0x043b,
        0xcd => 0x043c,
        0xce => 0x043d,
        0xcf => 0x043e,
        0xd0 => 0x043f,
        0xd1 => 0x044f,
        0xd2 => 0x0440,
        0xd3 => 0x0441,
        0xd4 => 0x0442,
        0xd5 => 0x0443,
        0xd6 => 0x0436,
        0xd7 => 0x0432,
        0xd8 => 0x044c,
        0xd9 => 0x044b,
        0xda => 0x0437,
        0xdb => 0x0448,
        0xdc => 0x044d,
        0xdd => 0x0449,
        0xde => 0x0447,
        0xdf => 0x044a,
        0xe0 => 0x042e,
        0xe1 => 0x0410,
        0xe2 => 0x0411,
        0xe3 => 0x0426,
        0xe4 => 0x0414,
        0xe5 => 0x0415,
        0xe6 => 0x0424,
        0xe7 => 0x0413,
        0xe8 => 0x0425,
        0xe9 => 0x0418,
        0xea => 0x0419,
        0xeb => 0x041a,
        0xec => 0x041b,
        0xed => 0x041c,
        0xee => 0x041d,
        0xef => 0x041e,
        0xf0 => 0x041f,
        0xf1 => 0x042f,
        0xf2 => 0x0420,
        0xf3 => 0x0421,
        0xf4 => 0x0422,
        0xf5 => 0x0423,
        0xf6 => 0x0416,
        0xf7 => 0x0412,
        0xf8 => 0x042c,
        0xf9 => 0x042b,
        0xfa => 0x0417,
        0xfb => 0x0428,
        0xfc => 0x042d,
        0xfd => 0x0429,
        0xfe => 0x0427,
        0xff => 0x042a,
    ];

    /** @var array<int, int> */
    private const KOI8_U_OVERRIDES = [
        0xa4 => 0x0454,
        0xa6 => 0x0456,
        0xa7 => 0x0457,
        0xad => 0x0491,
        0xb4 => 0x0404,
        0xb6 => 0x0406,
        0xb7 => 0x0407,
        0xbd => 0x0490,
    ];

    /** @var array<int, int> */
    private const KOI8_RU_OVERRIDES = [
        0xa4 => 0x0454,
        0xa6 => 0x0456,
        0xa7 => 0x0457,
        0xad => 0x0491,
        0xae => 0x045e,
        0xb4 => 0x0404,
        0xb6 => 0x0406,
        0xb7 => 0x0407,
        0xbd => 0x0490,
        0xbe => 0x040e,
    ];

    /** @var array<int, int> */
    private const KOI8_T_REPLACEMENTS = [
        0x80 => 0x049b,
        0x81 => 0x0493,
        0x82 => 0x201a,
        0x83 => 0x0492,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x89 => 0x2030,
        0x8a => 0x04b3,
        0x8b => 0x2039,
        0x8c => 0x04b2,
        0x8d => 0x04b7,
        0x8e => 0x04b6,
        0x90 => 0x049a,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x99 => 0x2122,
        0x9b => 0x203a,
        0xa1 => 0x04ef,
        0xa2 => 0x04ee,
        0xa3 => 0x0451,
        0xa4 => 0x00a4,
        0xa5 => 0x04e3,
        0xa6 => 0x00a6,
        0xa7 => 0x00a7,
        0xab => 0x00ab,
        0xac => 0x00ac,
        0xad => 0x00ad,
        0xae => 0x00ae,
        0xb0 => 0x00b0,
        0xb1 => 0x00b1,
        0xb2 => 0x00b2,
        0xb3 => 0x0401,
        0xb5 => 0x04e2,
        0xb6 => 0x00b6,
        0xb7 => 0x00b7,
        0xb9 => 0x2116,
        0xbb => 0x00bb,
        0xbf => 0x00a9,
    ];

    /** @var array<int, int> */
    private const IBM437_REPLACEMENTS = [
        0x80 => 0x00c7,
        0x81 => 0x00fc,
        0x82 => 0x00e9,
        0x83 => 0x00e2,
        0x84 => 0x00e4,
        0x85 => 0x00e0,
        0x86 => 0x00e5,
        0x87 => 0x00e7,
        0x88 => 0x00ea,
        0x89 => 0x00eb,
        0x8a => 0x00e8,
        0x8b => 0x00ef,
        0x8c => 0x00ee,
        0x8d => 0x00ec,
        0x8e => 0x00c4,
        0x8f => 0x00c5,
        0x90 => 0x00c9,
        0x91 => 0x00e6,
        0x92 => 0x00c6,
        0x93 => 0x00f4,
        0x94 => 0x00f6,
        0x95 => 0x00f2,
        0x96 => 0x00fb,
        0x97 => 0x00f9,
        0x98 => 0x00ff,
        0x99 => 0x00d6,
        0x9a => 0x00dc,
        0x9b => 0x00a2,
        0x9c => 0x00a3,
        0x9d => 0x00a5,
        0x9e => 0x20a7,
        0x9f => 0x0192,
        0xa0 => 0x00e1,
        0xa1 => 0x00ed,
        0xa2 => 0x00f3,
        0xa3 => 0x00fa,
        0xa4 => 0x00f1,
        0xa5 => 0x00d1,
        0xa6 => 0x00aa,
        0xa7 => 0x00ba,
        0xa8 => 0x00bf,
        0xa9 => 0x2310,
        0xaa => 0x00ac,
        0xab => 0x00bd,
        0xac => 0x00bc,
        0xad => 0x00a1,
        0xae => 0x00ab,
        0xaf => 0x00bb,
        0xb0 => 0x2591,
        0xb1 => 0x2592,
        0xb2 => 0x2593,
        0xb3 => 0x2502,
        0xb4 => 0x2524,
        0xb5 => 0x2561,
        0xb6 => 0x2562,
        0xb7 => 0x2556,
        0xb8 => 0x2555,
        0xb9 => 0x2563,
        0xba => 0x2551,
        0xbb => 0x2557,
        0xbc => 0x255d,
        0xbd => 0x255c,
        0xbe => 0x255b,
        0xbf => 0x2510,
        0xc0 => 0x2514,
        0xc1 => 0x2534,
        0xc2 => 0x252c,
        0xc3 => 0x251c,
        0xc4 => 0x2500,
        0xc5 => 0x253c,
        0xc6 => 0x255e,
        0xc7 => 0x255f,
        0xc8 => 0x255a,
        0xc9 => 0x2554,
        0xca => 0x2569,
        0xcb => 0x2566,
        0xcc => 0x2560,
        0xcd => 0x2550,
        0xce => 0x256c,
        0xcf => 0x2567,
        0xd0 => 0x2568,
        0xd1 => 0x2564,
        0xd2 => 0x2565,
        0xd3 => 0x2559,
        0xd4 => 0x2558,
        0xd5 => 0x2552,
        0xd6 => 0x2553,
        0xd7 => 0x256b,
        0xd8 => 0x256a,
        0xd9 => 0x2518,
        0xda => 0x250c,
        0xdb => 0x2588,
        0xdc => 0x2584,
        0xdd => 0x258c,
        0xde => 0x2590,
        0xdf => 0x2580,
        0xe0 => 0x03b1,
        0xe1 => 0x00df,
        0xe2 => 0x0393,
        0xe3 => 0x03c0,
        0xe4 => 0x03a3,
        0xe5 => 0x03c3,
        0xe6 => 0x00b5,
        0xe7 => 0x03c4,
        0xe8 => 0x03a6,
        0xe9 => 0x0398,
        0xea => 0x03a9,
        0xeb => 0x03b4,
        0xec => 0x221e,
        0xed => 0x03c6,
        0xee => 0x03b5,
        0xef => 0x2229,
        0xf0 => 0x2261,
        0xf1 => 0x00b1,
        0xf2 => 0x2265,
        0xf3 => 0x2264,
        0xf4 => 0x2320,
        0xf5 => 0x2321,
        0xf6 => 0x00f7,
        0xf7 => 0x2248,
        0xf8 => 0x00b0,
        0xf9 => 0x2219,
        0xfa => 0x00b7,
        0xfb => 0x221a,
        0xfc => 0x207f,
        0xfd => 0x00b2,
        0xfe => 0x25a0,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const IBM737_REPLACEMENTS = [
        0x80 => 0x0391,
        0x81 => 0x0392,
        0x82 => 0x0393,
        0x83 => 0x0394,
        0x84 => 0x0395,
        0x85 => 0x0396,
        0x86 => 0x0397,
        0x87 => 0x0398,
        0x88 => 0x0399,
        0x89 => 0x039a,
        0x8a => 0x039b,
        0x8b => 0x039c,
        0x8c => 0x039d,
        0x8d => 0x039e,
        0x8e => 0x039f,
        0x8f => 0x03a0,
        0x90 => 0x03a1,
        0x91 => 0x03a3,
        0x92 => 0x03a4,
        0x93 => 0x03a5,
        0x94 => 0x03a6,
        0x95 => 0x03a7,
        0x96 => 0x03a8,
        0x97 => 0x03a9,
        0x98 => 0x03b1,
        0x99 => 0x03b2,
        0x9a => 0x03b3,
        0x9b => 0x03b4,
        0x9c => 0x03b5,
        0x9d => 0x03b6,
        0x9e => 0x03b7,
        0x9f => 0x03b8,
        0xa0 => 0x03b9,
        0xa1 => 0x03ba,
        0xa2 => 0x03bb,
        0xa3 => 0x03bc,
        0xa4 => 0x03bd,
        0xa5 => 0x03be,
        0xa6 => 0x03bf,
        0xa7 => 0x03c0,
        0xa8 => 0x03c1,
        0xa9 => 0x03c3,
        0xaa => 0x03c2,
        0xab => 0x03c4,
        0xac => 0x03c5,
        0xad => 0x03c6,
        0xae => 0x03c7,
        0xaf => 0x03c8,
        0xb0 => 0x2591,
        0xb1 => 0x2592,
        0xb2 => 0x2593,
        0xb3 => 0x2502,
        0xb4 => 0x2524,
        0xb5 => 0x2561,
        0xb6 => 0x2562,
        0xb7 => 0x2556,
        0xb8 => 0x2555,
        0xb9 => 0x2563,
        0xba => 0x2551,
        0xbb => 0x2557,
        0xbc => 0x255d,
        0xbd => 0x255c,
        0xbe => 0x255b,
        0xbf => 0x2510,
        0xc0 => 0x2514,
        0xc1 => 0x2534,
        0xc2 => 0x252c,
        0xc3 => 0x251c,
        0xc4 => 0x2500,
        0xc5 => 0x253c,
        0xc6 => 0x255e,
        0xc7 => 0x255f,
        0xc8 => 0x255a,
        0xc9 => 0x2554,
        0xca => 0x2569,
        0xcb => 0x2566,
        0xcc => 0x2560,
        0xcd => 0x2550,
        0xce => 0x256c,
        0xcf => 0x2567,
        0xd0 => 0x2568,
        0xd1 => 0x2564,
        0xd2 => 0x2565,
        0xd3 => 0x2559,
        0xd4 => 0x2558,
        0xd5 => 0x2552,
        0xd6 => 0x2553,
        0xd7 => 0x256b,
        0xd8 => 0x256a,
        0xd9 => 0x2518,
        0xda => 0x250c,
        0xdb => 0x2588,
        0xdc => 0x2584,
        0xdd => 0x258c,
        0xde => 0x2590,
        0xdf => 0x2580,
        0xe0 => 0x03c9,
        0xe1 => 0x03ac,
        0xe2 => 0x03ad,
        0xe3 => 0x03ae,
        0xe4 => 0x03ca,
        0xe5 => 0x03af,
        0xe6 => 0x03cc,
        0xe7 => 0x03cd,
        0xe8 => 0x03cb,
        0xe9 => 0x03ce,
        0xea => 0x0386,
        0xeb => 0x0388,
        0xec => 0x0389,
        0xed => 0x038a,
        0xee => 0x038c,
        0xef => 0x038e,
        0xf0 => 0x038f,
        0xf1 => 0x00b1,
        0xf2 => 0x2265,
        0xf3 => 0x2264,
        0xf4 => 0x03aa,
        0xf5 => 0x03ab,
        0xf6 => 0x00f7,
        0xf7 => 0x2248,
        0xf8 => 0x00b0,
        0xf9 => 0x2219,
        0xfa => 0x00b7,
        0xfb => 0x221a,
        0xfc => 0x207f,
        0xfd => 0x00b2,
        0xfe => 0x25a0,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const IBM775_REPLACEMENTS = [
        0x80 => 0x0106,
        0x81 => 0x00fc,
        0x82 => 0x00e9,
        0x83 => 0x0101,
        0x84 => 0x00e4,
        0x85 => 0x0123,
        0x86 => 0x00e5,
        0x87 => 0x0107,
        0x88 => 0x0142,
        0x89 => 0x0113,
        0x8a => 0x0156,
        0x8b => 0x0157,
        0x8c => 0x012b,
        0x8d => 0x0179,
        0x8e => 0x00c4,
        0x8f => 0x00c5,
        0x90 => 0x00c9,
        0x91 => 0x00e6,
        0x92 => 0x00c6,
        0x93 => 0x014d,
        0x94 => 0x00f6,
        0x95 => 0x0122,
        0x96 => 0x00a2,
        0x97 => 0x015a,
        0x98 => 0x015b,
        0x99 => 0x00d6,
        0x9a => 0x00dc,
        0x9b => 0x00f8,
        0x9c => 0x00a3,
        0x9d => 0x00d8,
        0x9e => 0x00d7,
        0x9f => 0x00a4,
        0xa0 => 0x0100,
        0xa1 => 0x012a,
        0xa2 => 0x00f3,
        0xa3 => 0x017b,
        0xa4 => 0x017c,
        0xa5 => 0x017a,
        0xa6 => 0x201d,
        0xa7 => 0x00a6,
        0xa8 => 0x00a9,
        0xa9 => 0x00ae,
        0xaa => 0x00ac,
        0xab => 0x00bd,
        0xac => 0x00bc,
        0xad => 0x0141,
        0xae => 0x00ab,
        0xaf => 0x00bb,
        0xb0 => 0x2591,
        0xb1 => 0x2592,
        0xb2 => 0x2593,
        0xb3 => 0x2502,
        0xb4 => 0x2524,
        0xb5 => 0x0104,
        0xb6 => 0x010c,
        0xb7 => 0x0118,
        0xb8 => 0x0116,
        0xb9 => 0x2563,
        0xba => 0x2551,
        0xbb => 0x2557,
        0xbc => 0x255d,
        0xbd => 0x012e,
        0xbe => 0x0160,
        0xbf => 0x2510,
        0xc0 => 0x2514,
        0xc1 => 0x2534,
        0xc2 => 0x252c,
        0xc3 => 0x251c,
        0xc4 => 0x2500,
        0xc5 => 0x253c,
        0xc6 => 0x0172,
        0xc7 => 0x016a,
        0xc8 => 0x255a,
        0xc9 => 0x2554,
        0xca => 0x2569,
        0xcb => 0x2566,
        0xcc => 0x2560,
        0xcd => 0x2550,
        0xce => 0x256c,
        0xcf => 0x017d,
        0xd0 => 0x0105,
        0xd1 => 0x010d,
        0xd2 => 0x0119,
        0xd3 => 0x0117,
        0xd4 => 0x012f,
        0xd5 => 0x0161,
        0xd6 => 0x0173,
        0xd7 => 0x016b,
        0xd8 => 0x017e,
        0xd9 => 0x2518,
        0xda => 0x250c,
        0xdb => 0x2588,
        0xdc => 0x2584,
        0xdd => 0x258c,
        0xde => 0x2590,
        0xdf => 0x2580,
        0xe0 => 0x00d3,
        0xe1 => 0x00df,
        0xe2 => 0x014c,
        0xe3 => 0x0143,
        0xe4 => 0x00f5,
        0xe5 => 0x00d5,
        0xe6 => 0x00b5,
        0xe7 => 0x0144,
        0xe8 => 0x0136,
        0xe9 => 0x0137,
        0xea => 0x013b,
        0xeb => 0x013c,
        0xec => 0x0146,
        0xed => 0x0112,
        0xee => 0x0145,
        0xef => 0x2019,
        0xf0 => 0x00ad,
        0xf1 => 0x00b1,
        0xf2 => 0x201c,
        0xf3 => 0x00be,
        0xf4 => 0x00b6,
        0xf5 => 0x00a7,
        0xf6 => 0x00f7,
        0xf7 => 0x201e,
        0xf8 => 0x00b0,
        0xf9 => 0x2219,
        0xfa => 0x00b7,
        0xfb => 0x00b9,
        0xfc => 0x00b3,
        0xfd => 0x00b2,
        0xfe => 0x25a0,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const IBM850_REPLACEMENTS = [
        0x80 => 0x00c7,
        0x81 => 0x00fc,
        0x82 => 0x00e9,
        0x83 => 0x00e2,
        0x84 => 0x00e4,
        0x85 => 0x00e0,
        0x86 => 0x00e5,
        0x87 => 0x00e7,
        0x88 => 0x00ea,
        0x89 => 0x00eb,
        0x8a => 0x00e8,
        0x8b => 0x00ef,
        0x8c => 0x00ee,
        0x8d => 0x00ec,
        0x8e => 0x00c4,
        0x8f => 0x00c5,
        0x90 => 0x00c9,
        0x91 => 0x00e6,
        0x92 => 0x00c6,
        0x93 => 0x00f4,
        0x94 => 0x00f6,
        0x95 => 0x00f2,
        0x96 => 0x00fb,
        0x97 => 0x00f9,
        0x98 => 0x00ff,
        0x99 => 0x00d6,
        0x9a => 0x00dc,
        0x9b => 0x00f8,
        0x9c => 0x00a3,
        0x9d => 0x00d8,
        0x9e => 0x00d7,
        0x9f => 0x0192,
        0xa0 => 0x00e1,
        0xa1 => 0x00ed,
        0xa2 => 0x00f3,
        0xa3 => 0x00fa,
        0xa4 => 0x00f1,
        0xa5 => 0x00d1,
        0xa6 => 0x00aa,
        0xa7 => 0x00ba,
        0xa8 => 0x00bf,
        0xa9 => 0x00ae,
        0xaa => 0x00ac,
        0xab => 0x00bd,
        0xac => 0x00bc,
        0xad => 0x00a1,
        0xae => 0x00ab,
        0xaf => 0x00bb,
        0xb0 => 0x2591,
        0xb1 => 0x2592,
        0xb2 => 0x2593,
        0xb3 => 0x2502,
        0xb4 => 0x2524,
        0xb5 => 0x00c1,
        0xb6 => 0x00c2,
        0xb7 => 0x00c0,
        0xb8 => 0x00a9,
        0xb9 => 0x2563,
        0xba => 0x2551,
        0xbb => 0x2557,
        0xbc => 0x255d,
        0xbd => 0x00a2,
        0xbe => 0x00a5,
        0xbf => 0x2510,
        0xc0 => 0x2514,
        0xc1 => 0x2534,
        0xc2 => 0x252c,
        0xc3 => 0x251c,
        0xc4 => 0x2500,
        0xc5 => 0x253c,
        0xc6 => 0x00e3,
        0xc7 => 0x00c3,
        0xc8 => 0x255a,
        0xc9 => 0x2554,
        0xca => 0x2569,
        0xcb => 0x2566,
        0xcc => 0x2560,
        0xcd => 0x2550,
        0xce => 0x256c,
        0xcf => 0x00a4,
        0xd0 => 0x00f0,
        0xd1 => 0x00d0,
        0xd2 => 0x00ca,
        0xd3 => 0x00cb,
        0xd4 => 0x00c8,
        0xd5 => 0x0131,
        0xd6 => 0x00cd,
        0xd7 => 0x00ce,
        0xd8 => 0x00cf,
        0xd9 => 0x2518,
        0xda => 0x250c,
        0xdb => 0x2588,
        0xdc => 0x2584,
        0xdd => 0x00a6,
        0xde => 0x00cc,
        0xdf => 0x2580,
        0xe0 => 0x00d3,
        0xe1 => 0x00df,
        0xe2 => 0x00d4,
        0xe3 => 0x00d2,
        0xe4 => 0x00f5,
        0xe5 => 0x00d5,
        0xe6 => 0x00b5,
        0xe7 => 0x00fe,
        0xe8 => 0x00de,
        0xe9 => 0x00da,
        0xea => 0x00db,
        0xeb => 0x00d9,
        0xec => 0x00fd,
        0xed => 0x00dd,
        0xee => 0x00af,
        0xef => 0x00b4,
        0xf0 => 0x00ad,
        0xf1 => 0x00b1,
        0xf2 => 0x2017,
        0xf3 => 0x00be,
        0xf4 => 0x00b6,
        0xf5 => 0x00a7,
        0xf6 => 0x00f7,
        0xf7 => 0x00b8,
        0xf8 => 0x00b0,
        0xf9 => 0x00a8,
        0xfa => 0x00b7,
        0xfb => 0x00b9,
        0xfc => 0x00b3,
        0xfd => 0x00b2,
        0xfe => 0x25a0,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const IBM857_REPLACEMENTS = [
        0x8d => 0x0131,
        0x98 => 0x0130,
        0x9e => 0x015e,
        0x9f => 0x015f,
        0xa6 => 0x011e,
        0xa7 => 0x011f,
        0xd0 => 0x00ba,
        0xd1 => 0x00aa,
        0xe8 => 0x00d7,
        0xec => 0x00ec,
        0xed => 0x00ff,
    ];

    /** @var array<int, true> */
    private const IBM857_UNDEFINED = [
        0xd5 => true,
        0xe7 => true,
        0xf2 => true,
    ];

    /** @var array<int, int> */
    private const IBM852_REPLACEMENTS = [
        0x80 => 0x00c7,
        0x81 => 0x00fc,
        0x82 => 0x00e9,
        0x83 => 0x00e2,
        0x84 => 0x00e4,
        0x85 => 0x016f,
        0x86 => 0x0107,
        0x87 => 0x00e7,
        0x88 => 0x0142,
        0x89 => 0x00eb,
        0x8a => 0x0150,
        0x8b => 0x0151,
        0x8c => 0x00ee,
        0x8d => 0x0179,
        0x8e => 0x00c4,
        0x8f => 0x0106,
        0x90 => 0x00c9,
        0x91 => 0x0139,
        0x92 => 0x013a,
        0x93 => 0x00f4,
        0x94 => 0x00f6,
        0x95 => 0x013d,
        0x96 => 0x013e,
        0x97 => 0x015a,
        0x98 => 0x015b,
        0x99 => 0x00d6,
        0x9a => 0x00dc,
        0x9b => 0x0164,
        0x9c => 0x0165,
        0x9d => 0x0141,
        0x9e => 0x00d7,
        0x9f => 0x010d,
        0xa0 => 0x00e1,
        0xa1 => 0x00ed,
        0xa2 => 0x00f3,
        0xa3 => 0x00fa,
        0xa4 => 0x0104,
        0xa5 => 0x0105,
        0xa6 => 0x017d,
        0xa7 => 0x017e,
        0xa8 => 0x0118,
        0xa9 => 0x0119,
        0xaa => 0x00ac,
        0xab => 0x017a,
        0xac => 0x010c,
        0xad => 0x015f,
        0xae => 0x00ab,
        0xaf => 0x00bb,
        0xb0 => 0x2591,
        0xb1 => 0x2592,
        0xb2 => 0x2593,
        0xb3 => 0x2502,
        0xb4 => 0x2524,
        0xb5 => 0x00c1,
        0xb6 => 0x00c2,
        0xb7 => 0x011a,
        0xb8 => 0x015e,
        0xb9 => 0x2563,
        0xba => 0x2551,
        0xbb => 0x2557,
        0xbc => 0x255d,
        0xbd => 0x017b,
        0xbe => 0x017c,
        0xbf => 0x2510,
        0xc0 => 0x2514,
        0xc1 => 0x2534,
        0xc2 => 0x252c,
        0xc3 => 0x251c,
        0xc4 => 0x2500,
        0xc5 => 0x253c,
        0xc6 => 0x0102,
        0xc7 => 0x0103,
        0xc8 => 0x255a,
        0xc9 => 0x2554,
        0xca => 0x2569,
        0xcb => 0x2566,
        0xcc => 0x2560,
        0xcd => 0x2550,
        0xce => 0x256c,
        0xcf => 0x00a4,
        0xd0 => 0x0111,
        0xd1 => 0x0110,
        0xd2 => 0x010e,
        0xd3 => 0x00cb,
        0xd4 => 0x010f,
        0xd5 => 0x0147,
        0xd6 => 0x00cd,
        0xd7 => 0x00ce,
        0xd8 => 0x011b,
        0xd9 => 0x2518,
        0xda => 0x250c,
        0xdb => 0x2588,
        0xdc => 0x2584,
        0xdd => 0x0162,
        0xde => 0x016e,
        0xdf => 0x2580,
        0xe0 => 0x00d3,
        0xe1 => 0x00df,
        0xe2 => 0x00d4,
        0xe3 => 0x0143,
        0xe4 => 0x0144,
        0xe5 => 0x0148,
        0xe6 => 0x0160,
        0xe7 => 0x0161,
        0xe8 => 0x0154,
        0xe9 => 0x00da,
        0xea => 0x0155,
        0xeb => 0x0170,
        0xec => 0x00fd,
        0xed => 0x00dd,
        0xee => 0x0163,
        0xef => 0x00b4,
        0xf0 => 0x00ad,
        0xf1 => 0x02dd,
        0xf2 => 0x02db,
        0xf3 => 0x02c7,
        0xf4 => 0x02d8,
        0xf5 => 0x00a7,
        0xf6 => 0x00f7,
        0xf7 => 0x00b8,
        0xf8 => 0x00b0,
        0xf9 => 0x00a8,
        0xfa => 0x02d9,
        0xfb => 0x0171,
        0xfc => 0x0158,
        0xfd => 0x0159,
        0xfe => 0x25a0,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const IBM860_REPLACEMENTS = [
        0x80 => 0x00c7,
        0x81 => 0x00fc,
        0x82 => 0x00e9,
        0x83 => 0x00e2,
        0x84 => 0x00e3,
        0x85 => 0x00e0,
        0x86 => 0x00c1,
        0x87 => 0x00e7,
        0x88 => 0x00ea,
        0x89 => 0x00ca,
        0x8a => 0x00e8,
        0x8b => 0x00cd,
        0x8c => 0x00d4,
        0x8d => 0x00ec,
        0x8e => 0x00c3,
        0x8f => 0x00c2,
        0x90 => 0x00c9,
        0x91 => 0x00c0,
        0x92 => 0x00c8,
        0x93 => 0x00f4,
        0x94 => 0x00f5,
        0x95 => 0x00f2,
        0x96 => 0x00da,
        0x97 => 0x00f9,
        0x98 => 0x00cc,
        0x99 => 0x00d5,
        0x9a => 0x00dc,
        0x9b => 0x00a2,
        0x9c => 0x00a3,
        0x9d => 0x00d9,
        0x9e => 0x20a7,
        0x9f => 0x00d3,
        0xa0 => 0x00e1,
        0xa1 => 0x00ed,
        0xa2 => 0x00f3,
        0xa3 => 0x00fa,
        0xa4 => 0x00f1,
        0xa5 => 0x00d1,
        0xa6 => 0x00aa,
        0xa7 => 0x00ba,
        0xa8 => 0x00bf,
        0xa9 => 0x00d2,
        0xaa => 0x00ac,
        0xab => 0x00bd,
        0xac => 0x00bc,
        0xad => 0x00a1,
        0xae => 0x00ab,
        0xaf => 0x00bb,
    ];

    /** @var array<int, int> */
    private const IBM861_REPLACEMENTS = [
        0x80 => 0x00c7,
        0x81 => 0x00fc,
        0x82 => 0x00e9,
        0x83 => 0x00e2,
        0x84 => 0x00e4,
        0x85 => 0x00e0,
        0x86 => 0x00e5,
        0x87 => 0x00e7,
        0x88 => 0x00ea,
        0x89 => 0x00eb,
        0x8a => 0x00e8,
        0x8b => 0x00d0,
        0x8c => 0x00f0,
        0x8d => 0x00de,
        0x8e => 0x00c4,
        0x8f => 0x00c5,
        0x90 => 0x00c9,
        0x91 => 0x00e6,
        0x92 => 0x00c6,
        0x93 => 0x00f4,
        0x94 => 0x00f6,
        0x95 => 0x00fe,
        0x96 => 0x00fb,
        0x97 => 0x00dd,
        0x98 => 0x00fd,
        0x99 => 0x00d6,
        0x9a => 0x00dc,
        0x9b => 0x00f8,
        0x9c => 0x00a3,
        0x9d => 0x00d8,
        0x9e => 0x20a7,
        0x9f => 0x0192,
        0xa0 => 0x00e1,
        0xa1 => 0x00ed,
        0xa2 => 0x00f3,
        0xa3 => 0x00fa,
        0xa4 => 0x00c1,
        0xa5 => 0x00cd,
        0xa6 => 0x00d3,
        0xa7 => 0x00da,
    ];

    /** @var array<int, int> */
    private const IBM862_REPLACEMENTS = [
        0x80 => 0x05d0,
        0x81 => 0x05d1,
        0x82 => 0x05d2,
        0x83 => 0x05d3,
        0x84 => 0x05d4,
        0x85 => 0x05d5,
        0x86 => 0x05d6,
        0x87 => 0x05d7,
        0x88 => 0x05d8,
        0x89 => 0x05d9,
        0x8a => 0x05da,
        0x8b => 0x05db,
        0x8c => 0x05dc,
        0x8d => 0x05dd,
        0x8e => 0x05de,
        0x8f => 0x05df,
        0x90 => 0x05e0,
        0x91 => 0x05e1,
        0x92 => 0x05e2,
        0x93 => 0x05e3,
        0x94 => 0x05e4,
        0x95 => 0x05e5,
        0x96 => 0x05e6,
        0x97 => 0x05e7,
        0x98 => 0x05e8,
        0x99 => 0x05e9,
        0x9a => 0x05ea,
    ];

    /** @var array<int, int> */
    private const IBM863_REPLACEMENTS = [
        0x80 => 0x00c7,
        0x81 => 0x00fc,
        0x82 => 0x00e9,
        0x83 => 0x00e2,
        0x84 => 0x00c2,
        0x85 => 0x00e0,
        0x86 => 0x00b6,
        0x87 => 0x00e7,
        0x88 => 0x00ea,
        0x89 => 0x00eb,
        0x8a => 0x00e8,
        0x8b => 0x00ef,
        0x8c => 0x00ee,
        0x8d => 0x2017,
        0x8e => 0x00c0,
        0x8f => 0x00a7,
        0x90 => 0x00c9,
        0x91 => 0x00c8,
        0x92 => 0x00ca,
        0x93 => 0x00f4,
        0x94 => 0x00cb,
        0x95 => 0x00cf,
        0x96 => 0x00fb,
        0x97 => 0x00f9,
        0x98 => 0x00a4,
        0x99 => 0x00d4,
        0x9a => 0x00dc,
        0x9b => 0x00a2,
        0x9c => 0x00a3,
        0x9d => 0x00d9,
        0x9e => 0x00db,
        0x9f => 0x0192,
        0xa0 => 0x00a6,
        0xa1 => 0x00b4,
        0xa2 => 0x00f3,
        0xa3 => 0x00fa,
        0xa4 => 0x00a8,
        0xa5 => 0x00b8,
        0xa6 => 0x00b3,
        0xa7 => 0x00af,
        0xa8 => 0x00ce,
        0xa9 => 0x2310,
        0xaa => 0x00ac,
        0xab => 0x00bd,
        0xac => 0x00bc,
        0xad => 0x00be,
        0xae => 0x00ab,
        0xaf => 0x00bb,
    ];

    /** @var array<int, int> */
    private const IBM864_REPLACEMENTS = [
        0x80 => 0x00b0,
        0x81 => 0x00b7,
        0x82 => 0x2219,
        0x83 => 0x221a,
        0x84 => 0x2592,
        0x85 => 0x2500,
        0x86 => 0x2502,
        0x87 => 0x253c,
        0x88 => 0x2524,
        0x89 => 0x252c,
        0x8a => 0x251c,
        0x8b => 0x2534,
        0x8c => 0x2510,
        0x8d => 0x250c,
        0x8e => 0x2514,
        0x8f => 0x2518,
        0x90 => 0x03b2,
        0x91 => 0x221e,
        0x92 => 0x03c6,
        0x93 => 0x00b1,
        0x94 => 0x00bd,
        0x95 => 0x00bc,
        0x96 => 0x2248,
        0x97 => 0x00ab,
        0x98 => 0x00bb,
        0x99 => 0xfef7,
        0x9a => 0xfef8,
        0x9d => 0xfefb,
        0x9e => 0xfefc,
        0xa0 => 0x00a0,
        0xa1 => 0x00ad,
        0xa2 => 0xfe82,
        0xa3 => 0x00a3,
        0xa4 => 0x00a4,
        0xa5 => 0xfe84,
        0xa8 => 0xfe8e,
        0xa9 => 0xfe8f,
        0xaa => 0xfe95,
        0xab => 0xfe99,
        0xac => 0x060c,
        0xad => 0xfe9d,
        0xae => 0xfea1,
        0xaf => 0xfea5,
        0xb0 => 0x0660,
        0xb1 => 0x0661,
        0xb2 => 0x0662,
        0xb3 => 0x0663,
        0xb4 => 0x0664,
        0xb5 => 0x0665,
        0xb6 => 0x0666,
        0xb7 => 0x0667,
        0xb8 => 0x0668,
        0xb9 => 0x0669,
        0xba => 0xfed1,
        0xbb => 0x061b,
        0xbc => 0xfeb1,
        0xbd => 0xfeb5,
        0xbe => 0xfeb9,
        0xbf => 0x061f,
        0xc0 => 0x00a2,
        0xc1 => 0xfe80,
        0xc2 => 0xfe81,
        0xc3 => 0xfe83,
        0xc4 => 0xfe85,
        0xc5 => 0xfeca,
        0xc6 => 0xfe8b,
        0xc7 => 0xfe8d,
        0xc8 => 0xfe91,
        0xc9 => 0xfe93,
        0xca => 0xfe97,
        0xcb => 0xfe9b,
        0xcc => 0xfe9f,
        0xcd => 0xfea3,
        0xce => 0xfea7,
        0xcf => 0xfea9,
        0xd0 => 0xfeab,
        0xd1 => 0xfead,
        0xd2 => 0xfeaf,
        0xd3 => 0xfeb3,
        0xd4 => 0xfeb7,
        0xd5 => 0xfebb,
        0xd6 => 0xfebf,
        0xd7 => 0xfec1,
        0xd8 => 0xfec5,
        0xd9 => 0xfecb,
        0xda => 0xfecf,
        0xdb => 0x00a6,
        0xdc => 0x00ac,
        0xdd => 0x00f7,
        0xde => 0x00d7,
        0xdf => 0xfec9,
        0xe0 => 0x0640,
        0xe1 => 0xfed3,
        0xe2 => 0xfed7,
        0xe3 => 0xfedb,
        0xe4 => 0xfedf,
        0xe5 => 0xfee3,
        0xe6 => 0xfee7,
        0xe7 => 0xfeeb,
        0xe8 => 0xfeed,
        0xe9 => 0xfeef,
        0xea => 0xfef3,
        0xeb => 0xfebd,
        0xec => 0xfecc,
        0xed => 0xfece,
        0xee => 0xfecd,
        0xef => 0xfee1,
        0xf0 => 0xfe7d,
        0xf1 => 0x0651,
        0xf2 => 0xfee5,
        0xf3 => 0xfee9,
        0xf4 => 0xfeec,
        0xf5 => 0xfef0,
        0xf6 => 0xfef2,
        0xf7 => 0xfed0,
        0xf8 => 0xfed5,
        0xf9 => 0xfef5,
        0xfa => 0xfef6,
        0xfb => 0xfedd,
        0xfc => 0xfed9,
        0xfd => 0xfef1,
        0xfe => 0x25a0,
    ];

    /** @var array<int, true> */
    private const IBM864_UNDEFINED = [
        0x9b => true,
        0x9c => true,
        0x9f => true,
        0xa6 => true,
        0xa7 => true,
        0xff => true,
    ];

    /** @var array<int, int> */
    private const IBM865_REPLACEMENTS = [
        0xaf => 0x00a4,
    ];

    /** @var array<int, int> */
    private const IBM855_REPLACEMENTS = [
        0x80 => 0x0452,
        0x81 => 0x0402,
        0x82 => 0x0453,
        0x83 => 0x0403,
        0x84 => 0x0451,
        0x85 => 0x0401,
        0x86 => 0x0454,
        0x87 => 0x0404,
        0x88 => 0x0455,
        0x89 => 0x0405,
        0x8a => 0x0456,
        0x8b => 0x0406,
        0x8c => 0x0457,
        0x8d => 0x0407,
        0x8e => 0x0458,
        0x8f => 0x0408,
        0x90 => 0x0459,
        0x91 => 0x0409,
        0x92 => 0x045a,
        0x93 => 0x040a,
        0x94 => 0x045b,
        0x95 => 0x040b,
        0x96 => 0x045c,
        0x97 => 0x040c,
        0x98 => 0x045e,
        0x99 => 0x040e,
        0x9a => 0x045f,
        0x9b => 0x040f,
        0x9c => 0x044e,
        0x9d => 0x042e,
        0x9e => 0x044a,
        0x9f => 0x042a,
        0xa0 => 0x0430,
        0xa1 => 0x0410,
        0xa2 => 0x0431,
        0xa3 => 0x0411,
        0xa4 => 0x0446,
        0xa5 => 0x0426,
        0xa6 => 0x0434,
        0xa7 => 0x0414,
        0xa8 => 0x0435,
        0xa9 => 0x0415,
        0xaa => 0x0444,
        0xab => 0x0424,
        0xac => 0x0433,
        0xad => 0x0413,
        0xae => 0x00ab,
        0xaf => 0x00bb,
        0xb0 => 0x2591,
        0xb1 => 0x2592,
        0xb2 => 0x2593,
        0xb3 => 0x2502,
        0xb4 => 0x2524,
        0xb5 => 0x0445,
        0xb6 => 0x0425,
        0xb7 => 0x0438,
        0xb8 => 0x0418,
        0xb9 => 0x2563,
        0xba => 0x2551,
        0xbb => 0x2557,
        0xbc => 0x255d,
        0xbd => 0x0439,
        0xbe => 0x0419,
        0xbf => 0x2510,
        0xc0 => 0x2514,
        0xc1 => 0x2534,
        0xc2 => 0x252c,
        0xc3 => 0x251c,
        0xc4 => 0x2500,
        0xc5 => 0x253c,
        0xc6 => 0x043a,
        0xc7 => 0x041a,
        0xc8 => 0x255a,
        0xc9 => 0x2554,
        0xca => 0x2569,
        0xcb => 0x2566,
        0xcc => 0x2560,
        0xcd => 0x2550,
        0xce => 0x256c,
        0xcf => 0x00a4,
        0xd0 => 0x043b,
        0xd1 => 0x041b,
        0xd2 => 0x043c,
        0xd3 => 0x041c,
        0xd4 => 0x043d,
        0xd5 => 0x041d,
        0xd6 => 0x043e,
        0xd7 => 0x041e,
        0xd8 => 0x043f,
        0xd9 => 0x2518,
        0xda => 0x250c,
        0xdb => 0x2588,
        0xdc => 0x2584,
        0xdd => 0x041f,
        0xde => 0x044f,
        0xdf => 0x2580,
        0xe0 => 0x042f,
        0xe1 => 0x0440,
        0xe2 => 0x0420,
        0xe3 => 0x0441,
        0xe4 => 0x0421,
        0xe5 => 0x0442,
        0xe6 => 0x0422,
        0xe7 => 0x0443,
        0xe8 => 0x0423,
        0xe9 => 0x0436,
        0xea => 0x0416,
        0xeb => 0x0432,
        0xec => 0x0412,
        0xed => 0x044c,
        0xee => 0x042c,
        0xef => 0x2116,
        0xf0 => 0x00ad,
        0xf1 => 0x044b,
        0xf2 => 0x042b,
        0xf3 => 0x0437,
        0xf4 => 0x0417,
        0xf5 => 0x0448,
        0xf6 => 0x0428,
        0xf7 => 0x044d,
        0xf8 => 0x042d,
        0xf9 => 0x0449,
        0xfa => 0x0429,
        0xfb => 0x0447,
        0xfc => 0x0427,
        0xfd => 0x00a7,
        0xfe => 0x25a0,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const IBM866_REPLACEMENTS = [
        0x80 => 0x0410,
        0x81 => 0x0411,
        0x82 => 0x0412,
        0x83 => 0x0413,
        0x84 => 0x0414,
        0x85 => 0x0415,
        0x86 => 0x0416,
        0x87 => 0x0417,
        0x88 => 0x0418,
        0x89 => 0x0419,
        0x8a => 0x041a,
        0x8b => 0x041b,
        0x8c => 0x041c,
        0x8d => 0x041d,
        0x8e => 0x041e,
        0x8f => 0x041f,
        0x90 => 0x0420,
        0x91 => 0x0421,
        0x92 => 0x0422,
        0x93 => 0x0423,
        0x94 => 0x0424,
        0x95 => 0x0425,
        0x96 => 0x0426,
        0x97 => 0x0427,
        0x98 => 0x0428,
        0x99 => 0x0429,
        0x9a => 0x042a,
        0x9b => 0x042b,
        0x9c => 0x042c,
        0x9d => 0x042d,
        0x9e => 0x042e,
        0x9f => 0x042f,
        0xa0 => 0x0430,
        0xa1 => 0x0431,
        0xa2 => 0x0432,
        0xa3 => 0x0433,
        0xa4 => 0x0434,
        0xa5 => 0x0435,
        0xa6 => 0x0436,
        0xa7 => 0x0437,
        0xa8 => 0x0438,
        0xa9 => 0x0439,
        0xaa => 0x043a,
        0xab => 0x043b,
        0xac => 0x043c,
        0xad => 0x043d,
        0xae => 0x043e,
        0xaf => 0x043f,
        0xb0 => 0x2591,
        0xb1 => 0x2592,
        0xb2 => 0x2593,
        0xb3 => 0x2502,
        0xb4 => 0x2524,
        0xb5 => 0x2561,
        0xb6 => 0x2562,
        0xb7 => 0x2556,
        0xb8 => 0x2555,
        0xb9 => 0x2563,
        0xba => 0x2551,
        0xbb => 0x2557,
        0xbc => 0x255d,
        0xbd => 0x255c,
        0xbe => 0x255b,
        0xbf => 0x2510,
        0xc0 => 0x2514,
        0xc1 => 0x2534,
        0xc2 => 0x252c,
        0xc3 => 0x251c,
        0xc4 => 0x2500,
        0xc5 => 0x253c,
        0xc6 => 0x255e,
        0xc7 => 0x255f,
        0xc8 => 0x255a,
        0xc9 => 0x2554,
        0xca => 0x2569,
        0xcb => 0x2566,
        0xcc => 0x2560,
        0xcd => 0x2550,
        0xce => 0x256c,
        0xcf => 0x2567,
        0xd0 => 0x2568,
        0xd1 => 0x2564,
        0xd2 => 0x2565,
        0xd3 => 0x2559,
        0xd4 => 0x2558,
        0xd5 => 0x2552,
        0xd6 => 0x2553,
        0xd7 => 0x256b,
        0xd8 => 0x256a,
        0xd9 => 0x2518,
        0xda => 0x250c,
        0xdb => 0x2588,
        0xdc => 0x2584,
        0xdd => 0x258c,
        0xde => 0x2590,
        0xdf => 0x2580,
        0xe0 => 0x0440,
        0xe1 => 0x0441,
        0xe2 => 0x0442,
        0xe3 => 0x0443,
        0xe4 => 0x0444,
        0xe5 => 0x0445,
        0xe6 => 0x0446,
        0xe7 => 0x0447,
        0xe8 => 0x0448,
        0xe9 => 0x0449,
        0xea => 0x044a,
        0xeb => 0x044b,
        0xec => 0x044c,
        0xed => 0x044d,
        0xee => 0x044e,
        0xef => 0x044f,
        0xf0 => 0x0401,
        0xf1 => 0x0451,
        0xf2 => 0x0404,
        0xf3 => 0x0454,
        0xf4 => 0x0407,
        0xf5 => 0x0457,
        0xf6 => 0x040e,
        0xf7 => 0x045e,
        0xf8 => 0x00b0,
        0xf9 => 0x2219,
        0xfa => 0x00b7,
        0xfb => 0x221a,
        0xfc => 0x2116,
        0xfd => 0x00a4,
        0xfe => 0x25a0,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const IBM869_REPLACEMENTS = [
        0x86 => 0x0386,
        0x88 => 0x00b7,
        0x89 => 0x00ac,
        0x8a => 0x00a6,
        0x8b => 0x2018,
        0x8c => 0x2019,
        0x8d => 0x0388,
        0x8e => 0x2015,
        0x8f => 0x0389,
        0x90 => 0x038a,
        0x91 => 0x03aa,
        0x92 => 0x038c,
        0x95 => 0x038e,
        0x96 => 0x03ab,
        0x97 => 0x00a9,
        0x98 => 0x038f,
        0x99 => 0x00b2,
        0x9a => 0x00b3,
        0x9b => 0x03ac,
        0x9c => 0x00a3,
        0x9d => 0x03ad,
        0x9e => 0x03ae,
        0x9f => 0x03af,
        0xa0 => 0x03ca,
        0xa1 => 0x0390,
        0xa2 => 0x03cc,
        0xa3 => 0x03cd,
        0xa4 => 0x0391,
        0xa5 => 0x0392,
        0xa6 => 0x0393,
        0xa7 => 0x0394,
        0xa8 => 0x0395,
        0xa9 => 0x0396,
        0xaa => 0x0397,
        0xab => 0x00bd,
        0xac => 0x0398,
        0xad => 0x0399,
        0xae => 0x00ab,
        0xaf => 0x00bb,
        0xb0 => 0x2591,
        0xb1 => 0x2592,
        0xb2 => 0x2593,
        0xb3 => 0x2502,
        0xb4 => 0x2524,
        0xb5 => 0x039a,
        0xb6 => 0x039b,
        0xb7 => 0x039c,
        0xb8 => 0x039d,
        0xb9 => 0x2563,
        0xba => 0x2551,
        0xbb => 0x2557,
        0xbc => 0x255d,
        0xbd => 0x039e,
        0xbe => 0x039f,
        0xbf => 0x2510,
        0xc0 => 0x2514,
        0xc1 => 0x2534,
        0xc2 => 0x252c,
        0xc3 => 0x251c,
        0xc4 => 0x2500,
        0xc5 => 0x253c,
        0xc6 => 0x03a0,
        0xc7 => 0x03a1,
        0xc8 => 0x255a,
        0xc9 => 0x2554,
        0xca => 0x2569,
        0xcb => 0x2566,
        0xcc => 0x2560,
        0xcd => 0x2550,
        0xce => 0x256c,
        0xcf => 0x03a3,
        0xd0 => 0x03a4,
        0xd1 => 0x03a5,
        0xd2 => 0x03a6,
        0xd3 => 0x03a7,
        0xd4 => 0x03a8,
        0xd5 => 0x03a9,
        0xd6 => 0x03b1,
        0xd7 => 0x03b2,
        0xd8 => 0x03b3,
        0xd9 => 0x2518,
        0xda => 0x250c,
        0xdb => 0x2588,
        0xdc => 0x2584,
        0xdd => 0x03b4,
        0xde => 0x03b5,
        0xdf => 0x2580,
        0xe0 => 0x03b6,
        0xe1 => 0x03b7,
        0xe2 => 0x03b8,
        0xe3 => 0x03b9,
        0xe4 => 0x03ba,
        0xe5 => 0x03bb,
        0xe6 => 0x03bc,
        0xe7 => 0x03bd,
        0xe8 => 0x03be,
        0xe9 => 0x03bf,
        0xea => 0x03c0,
        0xeb => 0x03c1,
        0xec => 0x03c3,
        0xed => 0x03c2,
        0xee => 0x03c4,
        0xef => 0x0384,
        0xf0 => 0x00ad,
        0xf1 => 0x00b1,
        0xf2 => 0x03c5,
        0xf3 => 0x03c6,
        0xf4 => 0x03c7,
        0xf5 => 0x00a7,
        0xf6 => 0x03c8,
        0xf7 => 0x0385,
        0xf8 => 0x00b0,
        0xf9 => 0x00a8,
        0xfa => 0x03c9,
        0xfb => 0x03cb,
        0xfc => 0x03b0,
        0xfd => 0x03ce,
        0xfe => 0x25a0,
        0xff => 0x00a0,
    ];

    /** @var array<int, int> */
    private const ISO_8859_2_REPLACEMENTS = [
        0xa1 => 0x0104,
        0xa2 => 0x02d8,
        0xa3 => 0x0141,
        0xa5 => 0x013d,
        0xa6 => 0x015a,
        0xa9 => 0x0160,
        0xaa => 0x015e,
        0xab => 0x0164,
        0xac => 0x0179,
        0xae => 0x017d,
        0xaf => 0x017b,
        0xb1 => 0x0105,
        0xb2 => 0x02db,
        0xb3 => 0x0142,
        0xb5 => 0x013e,
        0xb6 => 0x015b,
        0xb7 => 0x02c7,
        0xb9 => 0x0161,
        0xba => 0x015f,
        0xbb => 0x0165,
        0xbc => 0x017a,
        0xbd => 0x02dd,
        0xbe => 0x017e,
        0xbf => 0x017c,
        0xc0 => 0x0154,
        0xc3 => 0x0102,
        0xc5 => 0x0139,
        0xc6 => 0x0106,
        0xc8 => 0x010c,
        0xca => 0x0118,
        0xcc => 0x011a,
        0xcf => 0x010e,
        0xd0 => 0x0110,
        0xd1 => 0x0143,
        0xd2 => 0x0147,
        0xd5 => 0x0150,
        0xd8 => 0x0158,
        0xd9 => 0x016e,
        0xdb => 0x0170,
        0xde => 0x0162,
        0xe0 => 0x0155,
        0xe3 => 0x0103,
        0xe5 => 0x013a,
        0xe6 => 0x0107,
        0xe8 => 0x010d,
        0xea => 0x0119,
        0xec => 0x011b,
        0xef => 0x010f,
        0xf0 => 0x0111,
        0xf1 => 0x0144,
        0xf2 => 0x0148,
        0xf5 => 0x0151,
        0xf8 => 0x0159,
        0xf9 => 0x016f,
        0xfb => 0x0171,
        0xfe => 0x0163,
        0xff => 0x02d9,
    ];

    /** @var array<int, int> */
    private const ISO_8859_3_REPLACEMENTS = [
        0xa1 => 0x0126,
        0xa2 => 0x02d8,
        0xa6 => 0x0124,
        0xa9 => 0x0130,
        0xaa => 0x015e,
        0xab => 0x011e,
        0xac => 0x0134,
        0xaf => 0x017b,
        0xb1 => 0x0127,
        0xb6 => 0x0125,
        0xb9 => 0x0131,
        0xba => 0x015f,
        0xbb => 0x011f,
        0xbc => 0x0135,
        0xbf => 0x017c,
        0xc5 => 0x010a,
        0xc6 => 0x0108,
        0xd5 => 0x0120,
        0xd8 => 0x011c,
        0xdd => 0x016c,
        0xde => 0x015c,
        0xe5 => 0x010b,
        0xe6 => 0x0109,
        0xf5 => 0x0121,
        0xf8 => 0x011d,
        0xfd => 0x016d,
        0xfe => 0x015d,
        0xff => 0x02d9,
    ];

    /** @var array<int, true> */
    private const ISO_8859_3_UNDEFINED = [
        0xa5 => true,
        0xae => true,
        0xbe => true,
        0xc3 => true,
        0xd0 => true,
        0xe3 => true,
        0xf0 => true,
    ];

    /** @var array<int, int> */
    private const ISO_8859_4_REPLACEMENTS = [
        0xa1 => 0x0104,
        0xa2 => 0x0138,
        0xa3 => 0x0156,
        0xa5 => 0x0128,
        0xa6 => 0x013b,
        0xa9 => 0x0160,
        0xaa => 0x0112,
        0xab => 0x0122,
        0xac => 0x0166,
        0xae => 0x017d,
        0xb1 => 0x0105,
        0xb2 => 0x02db,
        0xb3 => 0x0157,
        0xb5 => 0x0129,
        0xb6 => 0x013c,
        0xb7 => 0x02c7,
        0xb9 => 0x0161,
        0xba => 0x0113,
        0xbb => 0x0123,
        0xbc => 0x0167,
        0xbd => 0x014a,
        0xbe => 0x017e,
        0xbf => 0x014b,
        0xc0 => 0x0100,
        0xc7 => 0x012e,
        0xc8 => 0x010c,
        0xca => 0x0118,
        0xcc => 0x0116,
        0xcf => 0x012a,
        0xd0 => 0x0110,
        0xd1 => 0x0145,
        0xd2 => 0x014c,
        0xd3 => 0x0136,
        0xd9 => 0x0172,
        0xdd => 0x0168,
        0xde => 0x016a,
        0xe0 => 0x0101,
        0xe7 => 0x012f,
        0xe8 => 0x010d,
        0xea => 0x0119,
        0xec => 0x0117,
        0xef => 0x012b,
        0xf0 => 0x0111,
        0xf1 => 0x0146,
        0xf2 => 0x014d,
        0xf3 => 0x0137,
        0xf9 => 0x0173,
        0xfd => 0x0169,
        0xfe => 0x016b,
        0xff => 0x02d9,
    ];

    /** @var array<int, int> */
    private const ISO_8859_10_REPLACEMENTS = [
        0xa1 => 0x0104,
        0xa2 => 0x0112,
        0xa3 => 0x0122,
        0xa4 => 0x012a,
        0xa5 => 0x0128,
        0xa6 => 0x0136,
        0xa8 => 0x013b,
        0xa9 => 0x0110,
        0xaa => 0x0160,
        0xab => 0x0166,
        0xac => 0x017d,
        0xae => 0x016a,
        0xaf => 0x014a,
        0xb1 => 0x0105,
        0xb2 => 0x0113,
        0xb3 => 0x0123,
        0xb4 => 0x012b,
        0xb5 => 0x0129,
        0xb6 => 0x0137,
        0xb8 => 0x013c,
        0xb9 => 0x0111,
        0xba => 0x0161,
        0xbb => 0x0167,
        0xbc => 0x017e,
        0xbd => 0x2015,
        0xbe => 0x016b,
        0xbf => 0x014b,
        0xc0 => 0x0100,
        0xc7 => 0x012e,
        0xc8 => 0x010c,
        0xca => 0x0118,
        0xcc => 0x0116,
        0xd1 => 0x0145,
        0xd2 => 0x014c,
        0xd7 => 0x0168,
        0xd9 => 0x0172,
        0xe0 => 0x0101,
        0xe7 => 0x012f,
        0xe8 => 0x010d,
        0xea => 0x0119,
        0xec => 0x0117,
        0xf1 => 0x0146,
        0xf2 => 0x014d,
        0xf7 => 0x0169,
        0xf9 => 0x0173,
        0xff => 0x0138,
    ];

    /** @var array<int, int> */
    private const ISO_8859_13_REPLACEMENTS = [
        0xa1 => 0x201d,
        0xa5 => 0x201e,
        0xa8 => 0x00d8,
        0xaa => 0x0156,
        0xaf => 0x00c6,
        0xb4 => 0x201c,
        0xb8 => 0x00f8,
        0xba => 0x0157,
        0xbf => 0x00e6,
        0xc0 => 0x0104,
        0xc1 => 0x012e,
        0xc2 => 0x0100,
        0xc3 => 0x0106,
        0xc6 => 0x0118,
        0xc7 => 0x0112,
        0xc8 => 0x010c,
        0xca => 0x0179,
        0xcb => 0x0116,
        0xcc => 0x0122,
        0xcd => 0x0136,
        0xce => 0x012a,
        0xcf => 0x013b,
        0xd0 => 0x0160,
        0xd1 => 0x0143,
        0xd2 => 0x0145,
        0xd4 => 0x014c,
        0xd8 => 0x0172,
        0xd9 => 0x0141,
        0xda => 0x015a,
        0xdb => 0x016a,
        0xdd => 0x017b,
        0xde => 0x017d,
        0xe0 => 0x0105,
        0xe1 => 0x012f,
        0xe2 => 0x0101,
        0xe3 => 0x0107,
        0xe6 => 0x0119,
        0xe7 => 0x0113,
        0xe8 => 0x010d,
        0xea => 0x017a,
        0xeb => 0x0117,
        0xec => 0x0123,
        0xed => 0x0137,
        0xee => 0x012b,
        0xef => 0x013c,
        0xf0 => 0x0161,
        0xf1 => 0x0144,
        0xf2 => 0x0146,
        0xf4 => 0x014d,
        0xf8 => 0x0173,
        0xf9 => 0x0142,
        0xfa => 0x015b,
        0xfb => 0x016b,
        0xfd => 0x017c,
        0xfe => 0x017e,
        0xff => 0x2019,
    ];

    /** @var array<int, int> */
    private const ISO_8859_14_REPLACEMENTS = [
        0xa1 => 0x1e02,
        0xa2 => 0x1e03,
        0xa4 => 0x010a,
        0xa5 => 0x010b,
        0xa6 => 0x1e0a,
        0xa8 => 0x1e80,
        0xaa => 0x1e82,
        0xab => 0x1e0b,
        0xac => 0x1ef2,
        0xaf => 0x0178,
        0xb0 => 0x1e1e,
        0xb1 => 0x1e1f,
        0xb2 => 0x0120,
        0xb3 => 0x0121,
        0xb4 => 0x1e40,
        0xb5 => 0x1e41,
        0xb7 => 0x1e56,
        0xb8 => 0x1e81,
        0xb9 => 0x1e57,
        0xba => 0x1e83,
        0xbb => 0x1e60,
        0xbc => 0x1ef3,
        0xbd => 0x1e84,
        0xbe => 0x1e85,
        0xbf => 0x1e61,
        0xd0 => 0x0174,
        0xd7 => 0x1e6a,
        0xde => 0x0176,
        0xf0 => 0x0175,
        0xf7 => 0x1e6b,
        0xfe => 0x0177,
    ];

    /** @var array<int, int> */
    private const ISO_8859_16_REPLACEMENTS = [
        0xa1 => 0x0104,
        0xa2 => 0x0105,
        0xa3 => 0x0141,
        0xa4 => 0x20ac,
        0xa5 => 0x201e,
        0xa6 => 0x0160,
        0xa8 => 0x0161,
        0xaa => 0x0218,
        0xac => 0x0179,
        0xae => 0x017a,
        0xaf => 0x017b,
        0xb2 => 0x010c,
        0xb3 => 0x0142,
        0xb4 => 0x017d,
        0xb5 => 0x201d,
        0xb8 => 0x017e,
        0xb9 => 0x010d,
        0xba => 0x0219,
        0xbc => 0x0152,
        0xbd => 0x0153,
        0xbe => 0x0178,
        0xbf => 0x017c,
        0xc3 => 0x0102,
        0xc5 => 0x0106,
        0xd0 => 0x0110,
        0xd1 => 0x0143,
        0xd5 => 0x0150,
        0xd7 => 0x015a,
        0xd8 => 0x0170,
        0xdd => 0x0118,
        0xde => 0x021a,
        0xe3 => 0x0103,
        0xe5 => 0x0107,
        0xf0 => 0x0111,
        0xf1 => 0x0144,
        0xf5 => 0x0151,
        0xf7 => 0x015b,
        0xf8 => 0x0171,
        0xfd => 0x0119,
        0xfe => 0x021b,
    ];

    /** @var array<int, int> */
    private const ISO_8859_6_REPLACEMENTS = [
        0xa0 => 0x00a0,
        0xa4 => 0x00a4,
        0xac => 0x060c,
        0xad => 0x00ad,
        0xbb => 0x061b,
        0xbf => 0x061f,
        0xc1 => 0x0621,
        0xc2 => 0x0622,
        0xc3 => 0x0623,
        0xc4 => 0x0624,
        0xc5 => 0x0625,
        0xc6 => 0x0626,
        0xc7 => 0x0627,
        0xc8 => 0x0628,
        0xc9 => 0x0629,
        0xca => 0x062a,
        0xcb => 0x062b,
        0xcc => 0x062c,
        0xcd => 0x062d,
        0xce => 0x062e,
        0xcf => 0x062f,
        0xd0 => 0x0630,
        0xd1 => 0x0631,
        0xd2 => 0x0632,
        0xd3 => 0x0633,
        0xd4 => 0x0634,
        0xd5 => 0x0635,
        0xd6 => 0x0636,
        0xd7 => 0x0637,
        0xd8 => 0x0638,
        0xd9 => 0x0639,
        0xda => 0x063a,
        0xe0 => 0x0640,
        0xe1 => 0x0641,
        0xe2 => 0x0642,
        0xe3 => 0x0643,
        0xe4 => 0x0644,
        0xe5 => 0x0645,
        0xe6 => 0x0646,
        0xe7 => 0x0647,
        0xe8 => 0x0648,
        0xe9 => 0x0649,
        0xea => 0x064a,
        0xeb => 0x064b,
        0xec => 0x064c,
        0xed => 0x064d,
        0xee => 0x064e,
        0xef => 0x064f,
        0xf0 => 0x0650,
        0xf1 => 0x0651,
        0xf2 => 0x0652,
    ];

    /** @var array<int, int> */
    private const ISO_8859_7_REPLACEMENTS = [
        0xa0 => 0x00a0,
        0xa1 => 0x2018,
        0xa2 => 0x2019,
        0xa3 => 0x00a3,
        0xa4 => 0x20ac,
        0xa5 => 0x20af,
        0xa6 => 0x00a6,
        0xa7 => 0x00a7,
        0xa8 => 0x00a8,
        0xa9 => 0x00a9,
        0xaa => 0x037a,
        0xab => 0x00ab,
        0xac => 0x00ac,
        0xad => 0x00ad,
        0xaf => 0x2015,
        0xb0 => 0x00b0,
        0xb1 => 0x00b1,
        0xb2 => 0x00b2,
        0xb3 => 0x00b3,
        0xb4 => 0x0384,
        0xb5 => 0x0385,
        0xb6 => 0x0386,
        0xb7 => 0x00b7,
        0xb8 => 0x0388,
        0xb9 => 0x0389,
        0xba => 0x038a,
        0xbb => 0x00bb,
        0xbc => 0x038c,
        0xbd => 0x00bd,
        0xbe => 0x038e,
        0xbf => 0x038f,
        0xc0 => 0x0390,
        0xc1 => 0x0391,
        0xc2 => 0x0392,
        0xc3 => 0x0393,
        0xc4 => 0x0394,
        0xc5 => 0x0395,
        0xc6 => 0x0396,
        0xc7 => 0x0397,
        0xc8 => 0x0398,
        0xc9 => 0x0399,
        0xca => 0x039a,
        0xcb => 0x039b,
        0xcc => 0x039c,
        0xcd => 0x039d,
        0xce => 0x039e,
        0xcf => 0x039f,
        0xd0 => 0x03a0,
        0xd1 => 0x03a1,
        0xd3 => 0x03a3,
        0xd4 => 0x03a4,
        0xd5 => 0x03a5,
        0xd6 => 0x03a6,
        0xd7 => 0x03a7,
        0xd8 => 0x03a8,
        0xd9 => 0x03a9,
        0xda => 0x03aa,
        0xdb => 0x03ab,
        0xdc => 0x03ac,
        0xdd => 0x03ad,
        0xde => 0x03ae,
        0xdf => 0x03af,
        0xe0 => 0x03b0,
        0xe1 => 0x03b1,
        0xe2 => 0x03b2,
        0xe3 => 0x03b3,
        0xe4 => 0x03b4,
        0xe5 => 0x03b5,
        0xe6 => 0x03b6,
        0xe7 => 0x03b7,
        0xe8 => 0x03b8,
        0xe9 => 0x03b9,
        0xea => 0x03ba,
        0xeb => 0x03bb,
        0xec => 0x03bc,
        0xed => 0x03bd,
        0xee => 0x03be,
        0xef => 0x03bf,
        0xf0 => 0x03c0,
        0xf1 => 0x03c1,
        0xf2 => 0x03c2,
        0xf3 => 0x03c3,
        0xf4 => 0x03c4,
        0xf5 => 0x03c5,
        0xf6 => 0x03c6,
        0xf7 => 0x03c7,
        0xf8 => 0x03c8,
        0xf9 => 0x03c9,
        0xfa => 0x03ca,
        0xfb => 0x03cb,
        0xfc => 0x03cc,
        0xfd => 0x03cd,
        0xfe => 0x03ce,
    ];

    /** @var array<int, int> */
    private const ISO_8859_8_REPLACEMENTS = [
        0xa0 => 0x00a0,
        0xa2 => 0x00a2,
        0xa3 => 0x00a3,
        0xa4 => 0x00a4,
        0xa5 => 0x00a5,
        0xa6 => 0x00a6,
        0xa7 => 0x00a7,
        0xa8 => 0x00a8,
        0xa9 => 0x00a9,
        0xaa => 0x00d7,
        0xab => 0x00ab,
        0xac => 0x00ac,
        0xad => 0x00ad,
        0xae => 0x00ae,
        0xaf => 0x00af,
        0xb0 => 0x00b0,
        0xb1 => 0x00b1,
        0xb2 => 0x00b2,
        0xb3 => 0x00b3,
        0xb4 => 0x00b4,
        0xb5 => 0x00b5,
        0xb6 => 0x00b6,
        0xb7 => 0x00b7,
        0xb8 => 0x00b8,
        0xb9 => 0x00b9,
        0xba => 0x00f7,
        0xbb => 0x00bb,
        0xbc => 0x00bc,
        0xbd => 0x00bd,
        0xbe => 0x00be,
        0xdf => 0x2017,
        0xe0 => 0x05d0,
        0xe1 => 0x05d1,
        0xe2 => 0x05d2,
        0xe3 => 0x05d3,
        0xe4 => 0x05d4,
        0xe5 => 0x05d5,
        0xe6 => 0x05d6,
        0xe7 => 0x05d7,
        0xe8 => 0x05d8,
        0xe9 => 0x05d9,
        0xea => 0x05da,
        0xeb => 0x05db,
        0xec => 0x05dc,
        0xed => 0x05dd,
        0xee => 0x05de,
        0xef => 0x05df,
        0xf0 => 0x05e0,
        0xf1 => 0x05e1,
        0xf2 => 0x05e2,
        0xf3 => 0x05e3,
        0xf4 => 0x05e4,
        0xf5 => 0x05e5,
        0xf6 => 0x05e6,
        0xf7 => 0x05e7,
        0xf8 => 0x05e8,
        0xf9 => 0x05e9,
        0xfa => 0x05ea,
        0xfd => 0x200e,
        0xfe => 0x200f,
    ];

    /** @var array<int, int> */
    private const ISO_8859_9_REPLACEMENTS = [
        0xd0 => 0x011e,
        0xdd => 0x0130,
        0xde => 0x015e,
        0xf0 => 0x011f,
        0xfd => 0x0131,
        0xfe => 0x015f,
    ];

    /** @var array<int, int> */
    private const TIS_620_REPLACEMENTS = [
        0xa0 => 0x00a0,
        0xa1 => 0x0e01,
        0xa2 => 0x0e02,
        0xa3 => 0x0e03,
        0xa4 => 0x0e04,
        0xa5 => 0x0e05,
        0xa6 => 0x0e06,
        0xa7 => 0x0e07,
        0xa8 => 0x0e08,
        0xa9 => 0x0e09,
        0xaa => 0x0e0a,
        0xab => 0x0e0b,
        0xac => 0x0e0c,
        0xad => 0x0e0d,
        0xae => 0x0e0e,
        0xaf => 0x0e0f,
        0xb0 => 0x0e10,
        0xb1 => 0x0e11,
        0xb2 => 0x0e12,
        0xb3 => 0x0e13,
        0xb4 => 0x0e14,
        0xb5 => 0x0e15,
        0xb6 => 0x0e16,
        0xb7 => 0x0e17,
        0xb8 => 0x0e18,
        0xb9 => 0x0e19,
        0xba => 0x0e1a,
        0xbb => 0x0e1b,
        0xbc => 0x0e1c,
        0xbd => 0x0e1d,
        0xbe => 0x0e1e,
        0xbf => 0x0e1f,
        0xc0 => 0x0e20,
        0xc1 => 0x0e21,
        0xc2 => 0x0e22,
        0xc3 => 0x0e23,
        0xc4 => 0x0e24,
        0xc5 => 0x0e25,
        0xc6 => 0x0e26,
        0xc7 => 0x0e27,
        0xc8 => 0x0e28,
        0xc9 => 0x0e29,
        0xca => 0x0e2a,
        0xcb => 0x0e2b,
        0xcc => 0x0e2c,
        0xcd => 0x0e2d,
        0xce => 0x0e2e,
        0xcf => 0x0e2f,
        0xd0 => 0x0e30,
        0xd1 => 0x0e31,
        0xd2 => 0x0e32,
        0xd3 => 0x0e33,
        0xd4 => 0x0e34,
        0xd5 => 0x0e35,
        0xd6 => 0x0e36,
        0xd7 => 0x0e37,
        0xd8 => 0x0e38,
        0xd9 => 0x0e39,
        0xda => 0x0e3a,
        0xdf => 0x0e3f,
        0xe0 => 0x0e40,
        0xe1 => 0x0e41,
        0xe2 => 0x0e42,
        0xe3 => 0x0e43,
        0xe4 => 0x0e44,
        0xe5 => 0x0e45,
        0xe6 => 0x0e46,
        0xe7 => 0x0e47,
        0xe8 => 0x0e48,
        0xe9 => 0x0e49,
        0xea => 0x0e4a,
        0xeb => 0x0e4b,
        0xec => 0x0e4c,
        0xed => 0x0e4d,
        0xee => 0x0e4e,
        0xef => 0x0e4f,
        0xf0 => 0x0e50,
        0xf1 => 0x0e51,
        0xf2 => 0x0e52,
        0xf3 => 0x0e53,
        0xf4 => 0x0e54,
        0xf5 => 0x0e55,
        0xf6 => 0x0e56,
        0xf7 => 0x0e57,
        0xf8 => 0x0e58,
        0xf9 => 0x0e59,
        0xfa => 0x0e5a,
        0xfb => 0x0e5b,
    ];

    /** @var array<int, int> */
    private const MAC_ROMAN_REPLACEMENTS = [
        0x80 => 0x00c4,
        0x81 => 0x00c5,
        0x82 => 0x00c7,
        0x83 => 0x00c9,
        0x84 => 0x00d1,
        0x85 => 0x00d6,
        0x86 => 0x00dc,
        0x87 => 0x00e1,
        0x88 => 0x00e0,
        0x89 => 0x00e2,
        0x8a => 0x00e4,
        0x8b => 0x00e3,
        0x8c => 0x00e5,
        0x8d => 0x00e7,
        0x8e => 0x00e9,
        0x8f => 0x00e8,
        0x90 => 0x00ea,
        0x91 => 0x00eb,
        0x92 => 0x00ed,
        0x93 => 0x00ec,
        0x94 => 0x00ee,
        0x95 => 0x00ef,
        0x96 => 0x00f1,
        0x97 => 0x00f3,
        0x98 => 0x00f2,
        0x99 => 0x00f4,
        0x9a => 0x00f6,
        0x9b => 0x00f5,
        0x9c => 0x00fa,
        0x9d => 0x00f9,
        0x9e => 0x00fb,
        0x9f => 0x00fc,
        0xa0 => 0x2020,
        0xa1 => 0x00b0,
        0xa2 => 0x00a2,
        0xa3 => 0x00a3,
        0xa4 => 0x00a7,
        0xa5 => 0x2022,
        0xa6 => 0x00b6,
        0xa7 => 0x00df,
        0xa8 => 0x00ae,
        0xa9 => 0x00a9,
        0xaa => 0x2122,
        0xab => 0x00b4,
        0xac => 0x00a8,
        0xad => 0x2260,
        0xae => 0x00c6,
        0xaf => 0x00d8,
        0xb0 => 0x221e,
        0xb1 => 0x00b1,
        0xb2 => 0x2264,
        0xb3 => 0x2265,
        0xb4 => 0x00a5,
        0xb5 => 0x00b5,
        0xb6 => 0x2202,
        0xb7 => 0x2211,
        0xb8 => 0x220f,
        0xb9 => 0x03c0,
        0xba => 0x222b,
        0xbb => 0x00aa,
        0xbc => 0x00ba,
        0xbd => 0x03a9,
        0xbe => 0x00e6,
        0xbf => 0x00f8,
        0xc0 => 0x00bf,
        0xc1 => 0x00a1,
        0xc2 => 0x00ac,
        0xc3 => 0x221a,
        0xc4 => 0x0192,
        0xc5 => 0x2248,
        0xc6 => 0x2206,
        0xc7 => 0x00ab,
        0xc8 => 0x00bb,
        0xc9 => 0x2026,
        0xca => 0x00a0,
        0xcb => 0x00c0,
        0xcc => 0x00c3,
        0xcd => 0x00d5,
        0xce => 0x0152,
        0xcf => 0x0153,
        0xd0 => 0x2013,
        0xd1 => 0x2014,
        0xd2 => 0x201c,
        0xd3 => 0x201d,
        0xd4 => 0x2018,
        0xd5 => 0x2019,
        0xd6 => 0x00f7,
        0xd7 => 0x25ca,
        0xd8 => 0x00ff,
        0xd9 => 0x0178,
        0xda => 0x2044,
        0xdb => 0x20ac,
        0xdc => 0x2039,
        0xdd => 0x203a,
        0xde => 0xfb01,
        0xdf => 0xfb02,
        0xe0 => 0x2021,
        0xe1 => 0x00b7,
        0xe2 => 0x201a,
        0xe3 => 0x201e,
        0xe4 => 0x2030,
        0xe5 => 0x00c2,
        0xe6 => 0x00ca,
        0xe7 => 0x00c1,
        0xe8 => 0x00cb,
        0xe9 => 0x00c8,
        0xea => 0x00cd,
        0xeb => 0x00ce,
        0xec => 0x00cf,
        0xed => 0x00cc,
        0xee => 0x00d3,
        0xef => 0x00d4,
        0xf0 => 0xf8ff,
        0xf1 => 0x00d2,
        0xf2 => 0x00da,
        0xf3 => 0x00db,
        0xf4 => 0x00d9,
        0xf5 => 0x0131,
        0xf6 => 0x02c6,
        0xf7 => 0x02dc,
        0xf8 => 0x00af,
        0xf9 => 0x02d8,
        0xfa => 0x02d9,
        0xfb => 0x02da,
        0xfc => 0x00b8,
        0xfd => 0x02dd,
        0xfe => 0x02db,
        0xff => 0x02c7,
    ];

    /** @var array<int, int> */
    private const MAC_SYMBOL_REPLACEMENTS = [
        0x20 => 0x0020,
        0x21 => 0x0021,
        0x22 => 0x2200,
        0x23 => 0x0023,
        0x24 => 0x2203,
        0x25 => 0x0025,
        0x26 => 0x0026,
        0x27 => 0x220d,
        0x28 => 0x0028,
        0x29 => 0x0029,
        0x2a => 0x2217,
        0x2b => 0x002b,
        0x2c => 0x002c,
        0x2d => 0x2212,
        0x2e => 0x002e,
        0x2f => 0x002f,
        0x30 => 0x0030,
        0x31 => 0x0031,
        0x32 => 0x0032,
        0x33 => 0x0033,
        0x34 => 0x0034,
        0x35 => 0x0035,
        0x36 => 0x0036,
        0x37 => 0x0037,
        0x38 => 0x0038,
        0x39 => 0x0039,
        0x3a => 0x003a,
        0x3b => 0x003b,
        0x3c => 0x003c,
        0x3d => 0x003d,
        0x3e => 0x003e,
        0x3f => 0x003f,
        0x40 => 0x2245,
        0x41 => 0x0391,
        0x42 => 0x0392,
        0x43 => 0x03a7,
        0x44 => 0x0394,
        0x45 => 0x0395,
        0x46 => 0x03a6,
        0x47 => 0x0393,
        0x48 => 0x0397,
        0x49 => 0x0399,
        0x4a => 0x03d1,
        0x4b => 0x039a,
        0x4c => 0x039b,
        0x4d => 0x039c,
        0x4e => 0x039d,
        0x4f => 0x039f,
        0x50 => 0x03a0,
        0x51 => 0x0398,
        0x52 => 0x03a1,
        0x53 => 0x03a3,
        0x54 => 0x03a4,
        0x55 => 0x03a5,
        0x56 => 0x03c2,
        0x57 => 0x03a9,
        0x58 => 0x039e,
        0x59 => 0x03a8,
        0x5a => 0x0396,
        0x5b => 0x005b,
        0x5c => 0x2234,
        0x5d => 0x005d,
        0x5e => 0x22a5,
        0x5f => 0x005f,
        0x60 => 0xf8e5,
        0x61 => 0x03b1,
        0x62 => 0x03b2,
        0x63 => 0x03c7,
        0x64 => 0x03b4,
        0x65 => 0x03b5,
        0x66 => 0x03c6,
        0x67 => 0x03b3,
        0x68 => 0x03b7,
        0x69 => 0x03b9,
        0x6a => 0x03d5,
        0x6b => 0x03ba,
        0x6c => 0x03bb,
        0x6d => 0x03bc,
        0x6e => 0x03bd,
        0x6f => 0x03bf,
        0x70 => 0x03c0,
        0x71 => 0x03b8,
        0x72 => 0x03c1,
        0x73 => 0x03c3,
        0x74 => 0x03c4,
        0x75 => 0x03c5,
        0x76 => 0x03d6,
        0x77 => 0x03c9,
        0x78 => 0x03be,
        0x79 => 0x03c8,
        0x7a => 0x03b6,
        0x7b => 0x007b,
        0x7c => 0x007c,
        0x7d => 0x007d,
        0x7e => 0x223c,
        0x7f => 0x007f,
        0xa1 => 0x03d2,
        0xa2 => 0x2032,
        0xa3 => 0x2264,
        0xa4 => 0x2044,
        0xa5 => 0x221e,
        0xa6 => 0x0192,
        0xa7 => 0x2663,
        0xa8 => 0x2666,
        0xa9 => 0x2665,
        0xaa => 0x2660,
        0xab => 0x2194,
        0xac => 0x2190,
        0xad => 0x2191,
        0xae => 0x2192,
        0xaf => 0x2193,
        0xb0 => 0x00b0,
        0xb1 => 0x00b1,
        0xb2 => 0x2033,
        0xb3 => 0x2265,
        0xb4 => 0x00d7,
        0xb5 => 0x221d,
        0xb6 => 0x2202,
        0xb7 => 0x2022,
        0xb8 => 0x00f7,
        0xb9 => 0x2260,
        0xba => 0x2261,
        0xbb => 0x2248,
        0xbc => 0x2026,
        0xbd => 0xf8e6,
        0xbe => 0xf8e7,
        0xbf => 0x21b5,
        0xc0 => 0x2135,
        0xc1 => 0x2111,
        0xc2 => 0x211c,
        0xc3 => 0x2118,
        0xc4 => 0x2297,
        0xc5 => 0x2295,
        0xc6 => 0x2205,
        0xc7 => 0x2229,
        0xc8 => 0x222a,
        0xc9 => 0x2283,
        0xca => 0x2287,
        0xcb => 0x2284,
        0xcc => 0x2282,
        0xcd => 0x2286,
        0xce => 0x2208,
        0xcf => 0x2209,
        0xd0 => 0x2220,
        0xd1 => 0x2207,
        0xd2 => 0x00ae,
        0xd3 => 0x00a9,
        0xd4 => 0x2122,
        0xd5 => 0x220f,
        0xd6 => 0x221a,
        0xd7 => 0x22c5,
        0xd8 => 0x00ac,
        0xd9 => 0x2227,
        0xda => 0x2228,
        0xdb => 0x21d4,
        0xdc => 0x21d0,
        0xdd => 0x21d1,
        0xde => 0x21d2,
        0xdf => 0x21d3,
        0xe0 => 0x22c4,
        0xe1 => 0x2329,
        0xe2 => 0xf8e8,
        0xe3 => 0xf8e9,
        0xe4 => 0xf8ea,
        0xe5 => 0x2211,
        0xe6 => 0xf8eb,
        0xe7 => 0xf8ec,
        0xe8 => 0xf8ed,
        0xe9 => 0xf8ee,
        0xea => 0xf8ef,
        0xeb => 0xf8f0,
        0xec => 0xf8f1,
        0xed => 0xf8f2,
        0xee => 0xf8f3,
        0xef => 0xf8f4,
        0xf0 => 0xf8ff,
        0xf1 => 0x232a,
        0xf2 => 0x222b,
        0xf3 => 0x2320,
        0xf4 => 0xf8f5,
        0xf5 => 0x2321,
        0xf6 => 0xf8f6,
        0xf7 => 0xf8f7,
        0xf8 => 0xf8f8,
        0xf9 => 0xf8f9,
        0xfa => 0xf8fa,
        0xfb => 0xf8fb,
        0xfc => 0xf8fc,
        0xfd => 0xf8fd,
        0xfe => 0xf8fe,
    ];

    /** @var array<int, int> */
    private const MAC_DINGBATS_REPLACEMENTS = [
        0x20 => 0x0020,
        0x21 => 0x2701,
        0x22 => 0x2702,
        0x23 => 0x2703,
        0x24 => 0x2704,
        0x25 => 0x260e,
        0x26 => 0x2706,
        0x27 => 0x2707,
        0x28 => 0x2708,
        0x29 => 0x2709,
        0x2a => 0x261b,
        0x2b => 0x261e,
        0x2c => 0x270c,
        0x2d => 0x270d,
        0x2e => 0x270e,
        0x2f => 0x270f,
        0x30 => 0x2710,
        0x31 => 0x2711,
        0x32 => 0x2712,
        0x33 => 0x2713,
        0x34 => 0x2714,
        0x35 => 0x2715,
        0x36 => 0x2716,
        0x37 => 0x2717,
        0x38 => 0x2718,
        0x39 => 0x2719,
        0x3a => 0x271a,
        0x3b => 0x271b,
        0x3c => 0x271c,
        0x3d => 0x271d,
        0x3e => 0x271e,
        0x3f => 0x271f,
        0x40 => 0x2720,
        0x41 => 0x2721,
        0x42 => 0x2722,
        0x43 => 0x2723,
        0x44 => 0x2724,
        0x45 => 0x2725,
        0x46 => 0x2726,
        0x47 => 0x2727,
        0x48 => 0x2605,
        0x49 => 0x2729,
        0x4a => 0x272a,
        0x4b => 0x272b,
        0x4c => 0x272c,
        0x4d => 0x272d,
        0x4e => 0x272e,
        0x4f => 0x272f,
        0x50 => 0x2730,
        0x51 => 0x2731,
        0x52 => 0x2732,
        0x53 => 0x2733,
        0x54 => 0x2734,
        0x55 => 0x2735,
        0x56 => 0x2736,
        0x57 => 0x2737,
        0x58 => 0x2738,
        0x59 => 0x2739,
        0x5a => 0x273a,
        0x5b => 0x273b,
        0x5c => 0x273c,
        0x5d => 0x273d,
        0x5e => 0x273e,
        0x5f => 0x273f,
        0x60 => 0x2740,
        0x61 => 0x2741,
        0x62 => 0x2742,
        0x63 => 0x2743,
        0x64 => 0x2744,
        0x65 => 0x2745,
        0x66 => 0x2746,
        0x67 => 0x2747,
        0x68 => 0x2748,
        0x69 => 0x2749,
        0x6a => 0x274a,
        0x6b => 0x274b,
        0x6c => 0x25cf,
        0x6d => 0x274d,
        0x6e => 0x25a0,
        0x6f => 0x274f,
        0x70 => 0x2750,
        0x71 => 0x2751,
        0x72 => 0x2752,
        0x73 => 0x25b2,
        0x74 => 0x25bc,
        0x75 => 0x25c6,
        0x76 => 0x2756,
        0x77 => 0x25d7,
        0x78 => 0x2758,
        0x79 => 0x2759,
        0x7a => 0x275a,
        0x7b => 0x275b,
        0x7c => 0x275c,
        0x7d => 0x275d,
        0x7e => 0x275e,
        0x7f => 0x007f,
        0x80 => 0xf8d7,
        0x81 => 0xf8d8,
        0x82 => 0xf8d9,
        0x83 => 0xf8da,
        0x84 => 0xf8db,
        0x85 => 0xf8dc,
        0x86 => 0xf8dd,
        0x87 => 0xf8de,
        0x88 => 0xf8df,
        0x89 => 0xf8e0,
        0x8a => 0xf8e1,
        0x8b => 0xf8e2,
        0x8c => 0xf8e3,
        0x8d => 0xf8e4,
        0xa1 => 0x2761,
        0xa2 => 0x2762,
        0xa3 => 0x2763,
        0xa4 => 0x2764,
        0xa5 => 0x2765,
        0xa6 => 0x2766,
        0xa7 => 0x2767,
        0xa8 => 0x2663,
        0xa9 => 0x2666,
        0xaa => 0x2665,
        0xab => 0x2660,
        0xac => 0x2460,
        0xad => 0x2461,
        0xae => 0x2462,
        0xaf => 0x2463,
        0xb0 => 0x2464,
        0xb1 => 0x2465,
        0xb2 => 0x2466,
        0xb3 => 0x2467,
        0xb4 => 0x2468,
        0xb5 => 0x2469,
        0xb6 => 0x2776,
        0xb7 => 0x2777,
        0xb8 => 0x2778,
        0xb9 => 0x2779,
        0xba => 0x277a,
        0xbb => 0x277b,
        0xbc => 0x277c,
        0xbd => 0x277d,
        0xbe => 0x277e,
        0xbf => 0x277f,
        0xc0 => 0x2780,
        0xc1 => 0x2781,
        0xc2 => 0x2782,
        0xc3 => 0x2783,
        0xc4 => 0x2784,
        0xc5 => 0x2785,
        0xc6 => 0x2786,
        0xc7 => 0x2787,
        0xc8 => 0x2788,
        0xc9 => 0x2789,
        0xca => 0x278a,
        0xcb => 0x278b,
        0xcc => 0x278c,
        0xcd => 0x278d,
        0xce => 0x278e,
        0xcf => 0x278f,
        0xd0 => 0x2790,
        0xd1 => 0x2791,
        0xd2 => 0x2792,
        0xd3 => 0x2793,
        0xd4 => 0x2794,
        0xd5 => 0x2192,
        0xd6 => 0x2194,
        0xd7 => 0x2195,
        0xd8 => 0x2798,
        0xd9 => 0x2799,
        0xda => 0x279a,
        0xdb => 0x279b,
        0xdc => 0x279c,
        0xdd => 0x279d,
        0xde => 0x279e,
        0xdf => 0x279f,
        0xe0 => 0x27a0,
        0xe1 => 0x27a1,
        0xe2 => 0x27a2,
        0xe3 => 0x27a3,
        0xe4 => 0x27a4,
        0xe5 => 0x27a5,
        0xe6 => 0x27a6,
        0xe7 => 0x27a7,
        0xe8 => 0x27a8,
        0xe9 => 0x27a9,
        0xea => 0x27aa,
        0xeb => 0x27ab,
        0xec => 0x27ac,
        0xed => 0x27ad,
        0xee => 0x27ae,
        0xef => 0x27af,
        0xf1 => 0x27b1,
        0xf2 => 0x27b2,
        0xf3 => 0x27b3,
        0xf4 => 0x27b4,
        0xf5 => 0x27b5,
        0xf6 => 0x27b6,
        0xf7 => 0x27b7,
        0xf8 => 0x27b8,
        0xf9 => 0x27b9,
        0xfa => 0x27ba,
        0xfb => 0x27bb,
        0xfc => 0x27bc,
        0xfd => 0x27bd,
        0xfe => 0x27be,
    ];

    /** @var array<int, int> */
    private const MAC_ARABIC_REPLACEMENTS = [
        0x80 => 0x00c4,
        0x81 => 0x00a0,
        0x82 => 0x00c7,
        0x83 => 0x00c9,
        0x84 => 0x00d1,
        0x85 => 0x00d6,
        0x86 => 0x00dc,
        0x87 => 0x00e1,
        0x88 => 0x00e0,
        0x89 => 0x00e2,
        0x8a => 0x00e4,
        0x8b => 0x06ba,
        0x8c => 0x00ab,
        0x8d => 0x00e7,
        0x8e => 0x00e9,
        0x8f => 0x00e8,
        0x90 => 0x00ea,
        0x91 => 0x00eb,
        0x92 => 0x00ed,
        0x93 => 0x2026,
        0x94 => 0x00ee,
        0x95 => 0x00ef,
        0x96 => 0x00f1,
        0x97 => 0x00f3,
        0x98 => 0x00bb,
        0x99 => 0x00f4,
        0x9a => 0x00f6,
        0x9b => 0x00f7,
        0x9c => 0x00fa,
        0x9d => 0x00f9,
        0x9e => 0x00fb,
        0x9f => 0x00fc,
        0xa0 => 0x0020,
        0xa1 => 0x0021,
        0xa2 => 0x0022,
        0xa3 => 0x0023,
        0xa4 => 0x0024,
        0xa5 => 0x066a,
        0xa6 => 0x0026,
        0xa7 => 0x0027,
        0xa8 => 0x0028,
        0xa9 => 0x0029,
        0xaa => 0x002a,
        0xab => 0x002b,
        0xac => 0x060c,
        0xad => 0x002d,
        0xae => 0x002e,
        0xaf => 0x002f,
        0xb0 => 0x0660,
        0xb1 => 0x0661,
        0xb2 => 0x0662,
        0xb3 => 0x0663,
        0xb4 => 0x0664,
        0xb5 => 0x0665,
        0xb6 => 0x0666,
        0xb7 => 0x0667,
        0xb8 => 0x0668,
        0xb9 => 0x0669,
        0xba => 0x003a,
        0xbb => 0x061b,
        0xbc => 0x003c,
        0xbd => 0x003d,
        0xbe => 0x003e,
        0xbf => 0x061f,
        0xc0 => 0x274a,
        0xc1 => 0x0621,
        0xc2 => 0x0622,
        0xc3 => 0x0623,
        0xc4 => 0x0624,
        0xc5 => 0x0625,
        0xc6 => 0x0626,
        0xc7 => 0x0627,
        0xc8 => 0x0628,
        0xc9 => 0x0629,
        0xca => 0x062a,
        0xcb => 0x062b,
        0xcc => 0x062c,
        0xcd => 0x062d,
        0xce => 0x062e,
        0xcf => 0x062f,
        0xd0 => 0x0630,
        0xd1 => 0x0631,
        0xd2 => 0x0632,
        0xd3 => 0x0633,
        0xd4 => 0x0634,
        0xd5 => 0x0635,
        0xd6 => 0x0636,
        0xd7 => 0x0637,
        0xd8 => 0x0638,
        0xd9 => 0x0639,
        0xda => 0x063a,
        0xdb => 0x005b,
        0xdc => 0x005c,
        0xdd => 0x005d,
        0xde => 0x005e,
        0xdf => 0x005f,
        0xe0 => 0x0640,
        0xe1 => 0x0641,
        0xe2 => 0x0642,
        0xe3 => 0x0643,
        0xe4 => 0x0644,
        0xe5 => 0x0645,
        0xe6 => 0x0646,
        0xe7 => 0x0647,
        0xe8 => 0x0648,
        0xe9 => 0x0649,
        0xea => 0x064a,
        0xeb => 0x064b,
        0xec => 0x064c,
        0xed => 0x064d,
        0xee => 0x064e,
        0xef => 0x064f,
        0xf0 => 0x0650,
        0xf1 => 0x0651,
        0xf2 => 0x0652,
        0xf3 => 0x067e,
        0xf4 => 0x0679,
        0xf5 => 0x0686,
        0xf6 => 0x06d5,
        0xf7 => 0x06a4,
        0xf8 => 0x06af,
        0xf9 => 0x0688,
        0xfa => 0x0691,
        0xfb => 0x007b,
        0xfc => 0x007c,
        0xfd => 0x007d,
        0xfe => 0x0698,
        0xff => 0x06d2,
    ];

    /** @var array<int, int> */
    private const MAC_CROATIAN_REPLACEMENTS = [
        0xa9 => 0x0160,
        0xae => 0x017d,
        0xb4 => 0x2206,
        0xb9 => 0x0161,
        0xbe => 0x017e,
        0xc6 => 0x0106,
        0xc8 => 0x010c,
        0xd0 => 0x0110,
        0xd8 => 0xf8ff,
        0xd9 => 0x00a9,
        0xde => 0x00c6,
        0xdf => 0x00bb,
        0xe0 => 0x2013,
        0xe6 => 0x0107,
        0xe8 => 0x010d,
        0xf0 => 0x0111,
        0xf9 => 0x03c0,
        0xfa => 0x00cb,
        0xfd => 0x00ca,
        0xfe => 0x00e6,
    ];

    /** @var array<int, int> */
    private const MAC_THAI_REPLACEMENTS = [
        0x80 => 0x00ab,
        0x81 => 0x00bb,
        0x82 => 0x2026,
        0x83 => 0xf88c,
        0x84 => 0xf88f,
        0x85 => 0xf892,
        0x86 => 0xf895,
        0x87 => 0xf898,
        0x88 => 0xf88b,
        0x89 => 0xf88e,
        0x8a => 0xf891,
        0x8b => 0xf894,
        0x8c => 0xf897,
        0x8d => 0x201c,
        0x8e => 0x201d,
        0x8f => 0xf899,
        0x91 => 0x2022,
        0x92 => 0xf884,
        0x93 => 0xf889,
        0x94 => 0xf885,
        0x95 => 0xf886,
        0x96 => 0xf887,
        0x97 => 0xf888,
        0x98 => 0xf88a,
        0x99 => 0xf88d,
        0x9a => 0xf890,
        0x9b => 0xf893,
        0x9c => 0xf896,
        0x9d => 0x2018,
        0x9e => 0x2019,
        0xa0 => 0x00a0,
        0xa1 => 0x0e01,
        0xa2 => 0x0e02,
        0xa3 => 0x0e03,
        0xa4 => 0x0e04,
        0xa5 => 0x0e05,
        0xa6 => 0x0e06,
        0xa7 => 0x0e07,
        0xa8 => 0x0e08,
        0xa9 => 0x0e09,
        0xaa => 0x0e0a,
        0xab => 0x0e0b,
        0xac => 0x0e0c,
        0xad => 0x0e0d,
        0xae => 0x0e0e,
        0xaf => 0x0e0f,
        0xb0 => 0x0e10,
        0xb1 => 0x0e11,
        0xb2 => 0x0e12,
        0xb3 => 0x0e13,
        0xb4 => 0x0e14,
        0xb5 => 0x0e15,
        0xb6 => 0x0e16,
        0xb7 => 0x0e17,
        0xb8 => 0x0e18,
        0xb9 => 0x0e19,
        0xba => 0x0e1a,
        0xbb => 0x0e1b,
        0xbc => 0x0e1c,
        0xbd => 0x0e1d,
        0xbe => 0x0e1e,
        0xbf => 0x0e1f,
        0xc0 => 0x0e20,
        0xc1 => 0x0e21,
        0xc2 => 0x0e22,
        0xc3 => 0x0e23,
        0xc4 => 0x0e24,
        0xc5 => 0x0e25,
        0xc6 => 0x0e26,
        0xc7 => 0x0e27,
        0xc8 => 0x0e28,
        0xc9 => 0x0e29,
        0xca => 0x0e2a,
        0xcb => 0x0e2b,
        0xcc => 0x0e2c,
        0xcd => 0x0e2d,
        0xce => 0x0e2e,
        0xcf => 0x0e2f,
        0xd0 => 0x0e30,
        0xd1 => 0x0e31,
        0xd2 => 0x0e32,
        0xd3 => 0x0e33,
        0xd4 => 0x0e34,
        0xd5 => 0x0e35,
        0xd6 => 0x0e36,
        0xd7 => 0x0e37,
        0xd8 => 0x0e38,
        0xd9 => 0x0e39,
        0xda => 0x0e3a,
        0xdb => 0xfeff,
        0xdc => 0x200b,
        0xdd => 0x2013,
        0xde => 0x2014,
        0xdf => 0x0e3f,
        0xe0 => 0x0e40,
        0xe1 => 0x0e41,
        0xe2 => 0x0e42,
        0xe3 => 0x0e43,
        0xe4 => 0x0e44,
        0xe5 => 0x0e45,
        0xe6 => 0x0e46,
        0xe7 => 0x0e47,
        0xe8 => 0x0e48,
        0xe9 => 0x0e49,
        0xea => 0x0e4a,
        0xeb => 0x0e4b,
        0xec => 0x0e4c,
        0xed => 0x0e4d,
        0xee => 0x2122,
        0xef => 0x0e4f,
        0xf0 => 0x0e50,
        0xf1 => 0x0e51,
        0xf2 => 0x0e52,
        0xf3 => 0x0e53,
        0xf4 => 0x0e54,
        0xf5 => 0x0e55,
        0xf6 => 0x0e56,
        0xf7 => 0x0e57,
        0xf8 => 0x0e58,
        0xf9 => 0x0e59,
        0xfa => 0x00ae,
        0xfb => 0x00a9,
    ];

    /** @var array<int, int> */
    private const MAC_TURKISH_REPLACEMENTS = [
        0xda => 0x011e,
        0xdb => 0x011f,
        0xdc => 0x0130,
        0xdd => 0x0131,
        0xde => 0x015e,
        0xdf => 0x015f,
        0xf5 => 0xf8a0,
    ];

    /** @var array<int, int> */
    private const MAC_ICELAND_REPLACEMENTS = [
        0xa0 => 0x00dd,
        0xdc => 0x00d0,
        0xdd => 0x00f0,
        0xde => 0x00de,
        0xdf => 0x00fe,
        0xe0 => 0x00fd,
    ];

    /** @var array<int, int> */
    private const MAC_ROMANIA_REPLACEMENTS = [
        0xae => 0x0102,
        0xaf => 0x015e,
        0xbd => 0x2126,
        0xbe => 0x0103,
        0xbf => 0x015f,
        0xdb => 0x00a4,
        0xde => 0x0162,
        0xdf => 0x0163,
    ];

    /** @var array<int, int> */
    private const MAC_CENTRAL_EUROPE_REPLACEMENTS = [
        0x80 => 0x00c4,
        0x81 => 0x0100,
        0x82 => 0x0101,
        0x83 => 0x00c9,
        0x84 => 0x0104,
        0x85 => 0x00d6,
        0x86 => 0x00dc,
        0x87 => 0x00e1,
        0x88 => 0x0105,
        0x89 => 0x010c,
        0x8a => 0x00e4,
        0x8b => 0x010d,
        0x8c => 0x0106,
        0x8d => 0x0107,
        0x8e => 0x00e9,
        0x8f => 0x0179,
        0x90 => 0x017a,
        0x91 => 0x010e,
        0x92 => 0x00ed,
        0x93 => 0x010f,
        0x94 => 0x0112,
        0x95 => 0x0113,
        0x96 => 0x0116,
        0x97 => 0x00f3,
        0x98 => 0x0117,
        0x99 => 0x00f4,
        0x9a => 0x00f6,
        0x9b => 0x00f5,
        0x9c => 0x00fa,
        0x9d => 0x011a,
        0x9e => 0x011b,
        0x9f => 0x00fc,
        0xa0 => 0x2020,
        0xa1 => 0x00b0,
        0xa2 => 0x0118,
        0xa3 => 0x00a3,
        0xa4 => 0x00a7,
        0xa5 => 0x2022,
        0xa6 => 0x00b6,
        0xa7 => 0x00df,
        0xa8 => 0x00ae,
        0xa9 => 0x00a9,
        0xaa => 0x2122,
        0xab => 0x0119,
        0xac => 0x00a8,
        0xad => 0x2260,
        0xae => 0x0123,
        0xaf => 0x012e,
        0xb0 => 0x012f,
        0xb1 => 0x012a,
        0xb2 => 0x2264,
        0xb3 => 0x2265,
        0xb4 => 0x012b,
        0xb5 => 0x0136,
        0xb6 => 0x2202,
        0xb7 => 0x2211,
        0xb8 => 0x0142,
        0xb9 => 0x013b,
        0xba => 0x013c,
        0xbb => 0x013d,
        0xbc => 0x013e,
        0xbd => 0x0139,
        0xbe => 0x013a,
        0xbf => 0x0145,
        0xc0 => 0x0146,
        0xc1 => 0x0143,
        0xc2 => 0x00ac,
        0xc3 => 0x221a,
        0xc4 => 0x0144,
        0xc5 => 0x0147,
        0xc6 => 0x2206,
        0xc7 => 0x00ab,
        0xc8 => 0x00bb,
        0xc9 => 0x2026,
        0xca => 0x00a0,
        0xcb => 0x0148,
        0xcc => 0x0150,
        0xcd => 0x00d5,
        0xce => 0x0151,
        0xcf => 0x014c,
        0xd0 => 0x2013,
        0xd1 => 0x2014,
        0xd2 => 0x201c,
        0xd3 => 0x201d,
        0xd4 => 0x2018,
        0xd5 => 0x2019,
        0xd6 => 0x00f7,
        0xd7 => 0x25ca,
        0xd8 => 0x014d,
        0xd9 => 0x0154,
        0xda => 0x0155,
        0xdb => 0x0158,
        0xdc => 0x2039,
        0xdd => 0x203a,
        0xde => 0x0159,
        0xdf => 0x0156,
        0xe0 => 0x0157,
        0xe1 => 0x0160,
        0xe2 => 0x201a,
        0xe3 => 0x201e,
        0xe4 => 0x0161,
        0xe5 => 0x015a,
        0xe6 => 0x015b,
        0xe7 => 0x00c1,
        0xe8 => 0x0164,
        0xe9 => 0x0165,
        0xea => 0x00cd,
        0xeb => 0x017d,
        0xec => 0x017e,
        0xed => 0x016a,
        0xee => 0x00d3,
        0xef => 0x00d4,
        0xf0 => 0x016b,
        0xf1 => 0x016e,
        0xf2 => 0x00da,
        0xf3 => 0x016f,
        0xf4 => 0x0170,
        0xf5 => 0x0171,
        0xf6 => 0x0172,
        0xf7 => 0x0173,
        0xf8 => 0x00dd,
        0xf9 => 0x00fd,
        0xfa => 0x0137,
        0xfb => 0x017b,
        0xfc => 0x0141,
        0xfd => 0x017c,
        0xfe => 0x0122,
        0xff => 0x02c7,
    ];

    /** @var array<int, int> */
    private const MAC_CYRILLIC_REPLACEMENTS = [
        0x80 => 0x0410,
        0x81 => 0x0411,
        0x82 => 0x0412,
        0x83 => 0x0413,
        0x84 => 0x0414,
        0x85 => 0x0415,
        0x86 => 0x0416,
        0x87 => 0x0417,
        0x88 => 0x0418,
        0x89 => 0x0419,
        0x8a => 0x041a,
        0x8b => 0x041b,
        0x8c => 0x041c,
        0x8d => 0x041d,
        0x8e => 0x041e,
        0x8f => 0x041f,
        0x90 => 0x0420,
        0x91 => 0x0421,
        0x92 => 0x0422,
        0x93 => 0x0423,
        0x94 => 0x0424,
        0x95 => 0x0425,
        0x96 => 0x0426,
        0x97 => 0x0427,
        0x98 => 0x0428,
        0x99 => 0x0429,
        0x9a => 0x042a,
        0x9b => 0x042b,
        0x9c => 0x042c,
        0x9d => 0x042d,
        0x9e => 0x042e,
        0x9f => 0x042f,
        0xa0 => 0x2020,
        0xa1 => 0x00b0,
        0xa2 => 0x0490,
        0xa3 => 0x00a3,
        0xa4 => 0x00a7,
        0xa5 => 0x2022,
        0xa6 => 0x00b6,
        0xa7 => 0x0406,
        0xa8 => 0x00ae,
        0xa9 => 0x00a9,
        0xaa => 0x2122,
        0xab => 0x0402,
        0xac => 0x0452,
        0xad => 0x2260,
        0xae => 0x0403,
        0xaf => 0x0453,
        0xb0 => 0x221e,
        0xb1 => 0x00b1,
        0xb2 => 0x2264,
        0xb3 => 0x2265,
        0xb4 => 0x0456,
        0xb5 => 0x00b5,
        0xb6 => 0x0491,
        0xb7 => 0x0408,
        0xb8 => 0x0404,
        0xb9 => 0x0454,
        0xba => 0x0407,
        0xbb => 0x0457,
        0xbc => 0x0409,
        0xbd => 0x0459,
        0xbe => 0x040a,
        0xbf => 0x045a,
        0xc0 => 0x0458,
        0xc1 => 0x0405,
        0xc2 => 0x00ac,
        0xc3 => 0x221a,
        0xc4 => 0x0192,
        0xc5 => 0x2248,
        0xc6 => 0x2206,
        0xc7 => 0x00ab,
        0xc8 => 0x00bb,
        0xc9 => 0x2026,
        0xca => 0x00a0,
        0xcb => 0x040b,
        0xcc => 0x045b,
        0xcd => 0x040c,
        0xce => 0x045c,
        0xcf => 0x0455,
        0xd0 => 0x2013,
        0xd1 => 0x2014,
        0xd2 => 0x201c,
        0xd3 => 0x201d,
        0xd4 => 0x2018,
        0xd5 => 0x2019,
        0xd6 => 0x00f7,
        0xd7 => 0x201e,
        0xd8 => 0x040e,
        0xd9 => 0x045e,
        0xda => 0x040f,
        0xdb => 0x045f,
        0xdc => 0x2116,
        0xdd => 0x0401,
        0xde => 0x0451,
        0xdf => 0x044f,
        0xe0 => 0x0430,
        0xe1 => 0x0431,
        0xe2 => 0x0432,
        0xe3 => 0x0433,
        0xe4 => 0x0434,
        0xe5 => 0x0435,
        0xe6 => 0x0436,
        0xe7 => 0x0437,
        0xe8 => 0x0438,
        0xe9 => 0x0439,
        0xea => 0x043a,
        0xeb => 0x043b,
        0xec => 0x043c,
        0xed => 0x043d,
        0xee => 0x043e,
        0xef => 0x043f,
        0xf0 => 0x0440,
        0xf1 => 0x0441,
        0xf2 => 0x0442,
        0xf3 => 0x0443,
        0xf4 => 0x0444,
        0xf5 => 0x0445,
        0xf6 => 0x0446,
        0xf7 => 0x0447,
        0xf8 => 0x0448,
        0xf9 => 0x0449,
        0xfa => 0x044a,
        0xfb => 0x044b,
        0xfc => 0x044c,
        0xfd => 0x044d,
        0xfe => 0x044e,
        0xff => 0x20ac,
    ];

    /** @var array<int, int> */
    private const MAC_UKRAINE_REPLACEMENTS = [
        0xff => 0x00a4,
    ];

    /** @var array<int, int> */
    private const MAC_GREEK_REPLACEMENTS = [
        0x80 => 0x00c4,
        0x81 => 0x00b9,
        0x82 => 0x00b2,
        0x83 => 0x00c9,
        0x84 => 0x00b3,
        0x85 => 0x00d6,
        0x86 => 0x00dc,
        0x87 => 0x0385,
        0x88 => 0x00e0,
        0x89 => 0x00e2,
        0x8a => 0x00e4,
        0x8b => 0x0384,
        0x8c => 0x00a8,
        0x8d => 0x00e7,
        0x8e => 0x00e9,
        0x8f => 0x00e8,
        0x90 => 0x00ea,
        0x91 => 0x00eb,
        0x92 => 0x00a3,
        0x93 => 0x2122,
        0x94 => 0x00ee,
        0x95 => 0x00ef,
        0x96 => 0x2022,
        0x97 => 0x00bd,
        0x98 => 0x2030,
        0x99 => 0x00f4,
        0x9a => 0x00f6,
        0x9b => 0x00a6,
        0x9c => 0x00ad,
        0x9d => 0x00f9,
        0x9e => 0x00fb,
        0x9f => 0x00fc,
        0xa0 => 0x2020,
        0xa1 => 0x0393,
        0xa2 => 0x0394,
        0xa3 => 0x0398,
        0xa4 => 0x039b,
        0xa5 => 0x039e,
        0xa6 => 0x03a0,
        0xa7 => 0x00df,
        0xa8 => 0x00ae,
        0xa9 => 0x00a9,
        0xaa => 0x03a3,
        0xab => 0x03aa,
        0xac => 0x00a7,
        0xad => 0x2260,
        0xae => 0x00b0,
        0xaf => 0x00b7,
        0xb0 => 0x0391,
        0xb1 => 0x00b1,
        0xb2 => 0x2264,
        0xb3 => 0x2265,
        0xb4 => 0x00a5,
        0xb5 => 0x0392,
        0xb6 => 0x0395,
        0xb7 => 0x0396,
        0xb8 => 0x0397,
        0xb9 => 0x0399,
        0xba => 0x039a,
        0xbb => 0x039c,
        0xbc => 0x03a6,
        0xbd => 0x03ab,
        0xbe => 0x03a8,
        0xbf => 0x03a9,
        0xc0 => 0x03ac,
        0xc1 => 0x039d,
        0xc2 => 0x00ac,
        0xc3 => 0x039f,
        0xc4 => 0x03a1,
        0xc5 => 0x2248,
        0xc6 => 0x03a4,
        0xc7 => 0x00ab,
        0xc8 => 0x00bb,
        0xc9 => 0x2026,
        0xca => 0x00a0,
        0xcb => 0x03a5,
        0xcc => 0x03a7,
        0xcd => 0x0386,
        0xce => 0x0388,
        0xcf => 0x0153,
        0xd0 => 0x2013,
        0xd1 => 0x2015,
        0xd2 => 0x201c,
        0xd3 => 0x201d,
        0xd4 => 0x2018,
        0xd5 => 0x2019,
        0xd6 => 0x00f7,
        0xd7 => 0x0389,
        0xd8 => 0x038a,
        0xd9 => 0x038c,
        0xda => 0x038e,
        0xdb => 0x03ad,
        0xdc => 0x03ae,
        0xdd => 0x03af,
        0xde => 0x03cc,
        0xdf => 0x038f,
        0xe0 => 0x03cd,
        0xe1 => 0x03b1,
        0xe2 => 0x03b2,
        0xe3 => 0x03c8,
        0xe4 => 0x03b4,
        0xe5 => 0x03b5,
        0xe6 => 0x03c6,
        0xe7 => 0x03b3,
        0xe8 => 0x03b7,
        0xe9 => 0x03b9,
        0xea => 0x03be,
        0xeb => 0x03ba,
        0xec => 0x03bb,
        0xed => 0x03bc,
        0xee => 0x03bd,
        0xef => 0x03bf,
        0xf0 => 0x03c0,
        0xf1 => 0x03ce,
        0xf2 => 0x03c1,
        0xf3 => 0x03c3,
        0xf4 => 0x03c4,
        0xf5 => 0x03b8,
        0xf6 => 0x03c9,
        0xf7 => 0x03c2,
        0xf8 => 0x03c7,
        0xf9 => 0x03c5,
        0xfa => 0x03b6,
        0xfb => 0x03ca,
        0xfc => 0x03cb,
        0xfd => 0x0390,
        0xfe => 0x03b0,
        0xff => 0xf8a0,
    ];

    /** @var array<int, int> */
    private const MAC_JAPAN_SINGLE_REPLACEMENTS = [
        0xa0 => 0x00a0,
        0xfd => 0x00a9,
        0xfe => 0x2122,
        0xff => 0x2026,
    ];

    /** @var array<int, int> */
    private const MAC_JAPAN_PUNCTUATION_PAIRS = [
        0x40 => 0x3000,
        0x41 => 0x3001,
        0x42 => 0x3002,
        0x43 => 0xff0c,
        0x44 => 0xff0e,
        0x45 => 0x30fb,
        0x46 => 0xff1a,
        0x47 => 0xff1b,
        0x48 => 0xff1f,
        0x49 => 0xff01,
        0x4a => 0x309b,
        0x4b => 0x309c,
        0x4c => 0x00b4,
        0x4d => 0xff40,
        0x4e => 0x00a8,
        0x4f => 0xff3e,
        0x50 => 0x203e,
        0x51 => 0xff3f,
        0x52 => 0x30fd,
        0x53 => 0x30fe,
        0x54 => 0x309d,
        0x55 => 0x309e,
        0x56 => 0x3003,
        0x57 => 0x4edd,
        0x58 => 0x3005,
        0x59 => 0x3006,
        0x5a => 0x3007,
        0x5b => 0x30fc,
        0x5c => 0x2014,
        0x5d => 0x2010,
        0x5e => 0xff0f,
        0x5f => 0xff3c,
        0x60 => 0x301c,
        0x61 => 0x2016,
        0x62 => 0xff5c,
        0x63 => 0x22ef,
        0x64 => 0x2025,
        0x65 => 0x2018,
        0x66 => 0x2019,
        0x67 => 0x201c,
        0x68 => 0x201d,
        0x69 => 0xff08,
        0x6a => 0xff09,
        0x6b => 0x3014,
        0x6c => 0x3015,
        0x6d => 0xff3b,
        0x6e => 0xff3d,
        0x6f => 0xff5b,
        0x70 => 0xff5d,
        0x71 => 0x3008,
        0x72 => 0x3009,
        0x73 => 0x300a,
        0x74 => 0x300b,
        0x75 => 0x300c,
        0x76 => 0x300d,
        0x77 => 0x300e,
        0x78 => 0x300f,
        0x79 => 0x3010,
        0x7a => 0x3011,
        0x7b => 0xff0b,
        0x7c => 0x2212,
        0x7d => 0x00b1,
        0x7e => 0x00d7,
        0x80 => 0x00f7,
        0x81 => 0xff1d,
        0x82 => 0x2260,
        0x83 => 0xff1c,
        0x84 => 0xff1e,
        0x85 => 0x2266,
        0x86 => 0x2267,
        0x87 => 0x221e,
        0x88 => 0x2234,
        0x89 => 0x2642,
        0x8a => 0x2640,
        0x8b => 0x00b0,
        0x8c => 0x2032,
        0x8d => 0x2033,
        0x8e => 0x2103,
        0x8f => 0xffe5,
        0x90 => 0xff04,
        0x91 => 0x00a2,
        0x92 => 0x00a3,
        0x93 => 0xff05,
        0x94 => 0xff03,
        0x95 => 0xff06,
        0x96 => 0xff0a,
        0x97 => 0xff20,
        0x98 => 0x00a7,
        0x99 => 0x2606,
        0x9a => 0x2605,
        0x9b => 0x25cb,
        0x9c => 0x25cf,
        0x9d => 0x25ce,
        0x9e => 0x25c7,
        0x9f => 0x25c6,
        0xa0 => 0x25a1,
        0xa1 => 0x25a0,
        0xa2 => 0x25b3,
        0xa3 => 0x25b2,
        0xa4 => 0x25bd,
        0xa5 => 0x25bc,
        0xa6 => 0x203b,
        0xa7 => 0x3012,
        0xa8 => 0x2192,
        0xa9 => 0x2190,
        0xaa => 0x2191,
        0xab => 0x2193,
        0xac => 0x3013,
        0xb8 => 0x2208,
        0xb9 => 0x220b,
        0xba => 0x2286,
        0xbb => 0x2287,
        0xbc => 0x2282,
        0xbd => 0x2283,
        0xbe => 0x222a,
        0xbf => 0x2229,
        0xc8 => 0x2227,
        0xc9 => 0x2228,
        0xca => 0x00ac,
        0xcb => 0x21d2,
        0xcc => 0x21d4,
        0xcd => 0x2200,
        0xce => 0x2203,
        0xda => 0x2220,
        0xdb => 0x22a5,
        0xdc => 0x2312,
        0xdd => 0x2202,
        0xde => 0x2207,
        0xdf => 0x2261,
        0xe0 => 0x2252,
        0xe1 => 0x226a,
        0xe2 => 0x226b,
        0xe3 => 0x221a,
        0xe4 => 0x223d,
        0xe5 => 0x221d,
        0xe6 => 0x2235,
        0xe7 => 0x222b,
        0xe8 => 0x222c,
        0xf0 => 0x212b,
        0xf1 => 0x2030,
        0xf2 => 0x266f,
        0xf3 => 0x266d,
        0xf4 => 0x266a,
        0xf5 => 0x2020,
        0xf6 => 0x2021,
        0xf7 => 0x00b6,
        0xfc => 0x25ef,
    ];

    /** @var array<int, int> */
    private const JIS0208_POINTERS = [
        1 => 0x3001,
        2 => 0x3002,
        28 => 0x2015,
        32 => 0xff5e,
        60 => 0xff0d,
        78 => 0xffe5,
        321 => 0x3068,
        1128 => 0x2460,
        1669 => 0x753b,
        1740 => 0x89d2,
        1846 => 0x4e38,
        2122 => 0x8a08,
        2423 => 0x5d0e,
        3611 => 0x6ce2,
        3695 => 0x534a,
        3877 => 0x6587,
        4007 => 0x672c,
        11091 => 0x9ad9,
    ];

    /** @var array<int, int> */
    private const JIS0212_PAIRS = [
        0xa6f1 => 0x03ac,
        0xa6f7 => 0x03cc,
        0xa7c4 => 0x0404,
        0xa7f4 => 0x0454,
        0xa9a1 => 0x00c6,
        0xa9ad => 0x0152,
    ];

    /** @var array<int, int> */
    private const BIG5_PAIRS = [
        0xa140 => 0x3000,
        0xa141 => 0xff0c,
        0xa142 => 0x3001,
        0xa143 => 0x3002,
        0xa144 => 0xff0e,
        0xa145 => 0x2022,
        0xa146 => 0xff1b,
        0xa147 => 0xff1a,
        0xa148 => 0xff1f,
        0xa149 => 0xff01,
        0xa171 => 0x3008,
        0xa172 => 0x3009,
        0xa175 => 0x300c,
        0xa176 => 0x300d,
        0xa1a5 => 0x2018,
        0xa1a6 => 0x2019,
        0xa1a7 => 0x201c,
        0xa1a8 => 0x201d,
        0xa1b0 => 0x203b,
        0xa1b1 => 0x00a7,
        0xa1b2 => 0x3003,
        0xa2af => 0xff10,
        0xa2b0 => 0xff11,
        0xa2b1 => 0xff12,
        0xa344 => 0x0391,
        0xa350 => 0x039d,
        0xa35b => 0x03a9,
        0xa35c => 0x03b1,
        0xa373 => 0x03c9,
        0xa374 => 0x3105,
        0xa375 => 0x3106,
        0xa37e => 0x310f,
        0xa4a4 => 0x4e2d,
        0xa4e5 => 0x6587,
        0xadbb => 0x9999,
        0xb4e4 => 0x6e2f,
        0xb4fa => 0x6e2c,
        0xb8d5 => 0x8a66,
        0xc6a1 => 0x30fe,
        0xc6a2 => 0x309d,
        0xc6a3 => 0x309e,
        0xc6a4 => 0x3005,
        0xc6a5 => 0x3041,
        0xc6a6 => 0x3042,
    ];

    /** @var array<int, string> */
    private const BIG5_PAIR_SEQUENCES = [
        0x8862 => "\u{00CA}\u{0304}",
        0x8864 => "\u{00CA}\u{030C}",
        0x88a3 => "\u{00EA}\u{0304}",
        0x88a5 => "\u{00EA}\u{030C}",
    ];

    /** @var array<int, int> */
    private const CP950_PAIRS = [
        0xa145 => 0x2027,
        0xa1c2 => 0x00af,
        0xa1e3 => 0xff5e,
        0xa240 => 0xff3c,
        0xa3e1 => 0x20ac,
        0xf9d6 => 0x7881,
        0xf9d7 => 0x92b9,
        0xf9dd => 0x2554,
        0xf9de => 0x2566,
        0xf9df => 0x2557,
    ];

    /** @var array<int, int> */
    private const CNS11643_PLANE1_PAIRS = [
        0xa1a1 => 0x4e28,
        0xa1a2 => 0x4e36,
        0xa1a3 => 0x4e3f,
        0xa1b0 => 0x4e46,
        0xa1b1 => 0x4e8f,
        0xa1b2 => 0x4ebc,
        0xa2a1 => 0x5322,
        0xa2a2 => 0x5304,
        0xa2a3 => 0x5303,
        0xa3a1 => 0x4f64,
        0xa3a2 => 0x51e8,
        0xa3a3 => 0x4f67,
    ];

    /** @var array<int, int> */
    private const GBK_PAIRS = [
        0xa1a1 => 0x3000,
        0xa1a2 => 0x3001,
        0xa1a3 => 0x3002,
        0xa2b1 => 0x2488,
        0xa2b2 => 0x2489,
        0xa2b3 => 0x248a,
        0xa2c5 => 0x2474,
        0xa2c6 => 0x2475,
        0xa2c7 => 0x2476,
        0xa2d9 => 0x2460,
        0xa2da => 0x2461,
        0xa2e3 => 0x20ac,
        0xa2e5 => 0x3220,
        0xa2e6 => 0x3221,
        0xa2f1 => 0x2160,
        0xa2f2 => 0x2161,
        0xa3ac => 0xff0c,
        0xa3b0 => 0xff10,
        0xa3c1 => 0xff21,
        0xa3e1 => 0xff41,
        0xa4a2 => 0x3042,
        0xa4a4 => 0x3044,
        0xa5a2 => 0x30a2,
        0xa6a1 => 0x0391,
        0xa6c1 => 0x03b1,
        0xa9a4 => 0x2500,
        0xa9a5 => 0x2501,
        0xa9a6 => 0x2502,
        0xa9a7 => 0x2503,
        0xa9a8 => 0x2504,
        0xa9a9 => 0x2505,
        0xb1b1 => 0x5317,
        0xb2e2 => 0x6d4b,
        0xbcf2 => 0x7b80,
        0xbea9 => 0x4eac,
        0xcad4 => 0x8bd5,
        0xcce5 => 0x4f53,
        0xcec4 => 0x6587,
        0xd6d0 => 0x4e2d,
    ];

    /** @var array<int, int> */
    private const GB12345_PAIRS = [
        0xa1a1 => 0x3000,
        0xa1a2 => 0x3001,
        0xa1a3 => 0x3002,
        0xa2b1 => 0x2488,
        0xa2b2 => 0x2489,
        0xa2b3 => 0x248a,
        0xa2c5 => 0x2474,
        0xa2c6 => 0x2475,
        0xa2c7 => 0x2476,
        0xa2d9 => 0x2460,
        0xa2da => 0x2461,
        0xa2e5 => 0x3220,
        0xa2e6 => 0x3221,
        0xa2f1 => 0x2160,
        0xa2f2 => 0x2161,
        0xa3ac => 0xff0c,
        0xa3b0 => 0xff10,
        0xa3c1 => 0xff21,
        0xa3e1 => 0xff41,
        0xa4a2 => 0x3042,
        0xa4a4 => 0x3044,
        0xa5a2 => 0x30a2,
        0xa6a1 => 0x0391,
        0xa6c1 => 0x03b1,
        0xa9a4 => 0x2500,
        0xa9a5 => 0x2501,
        0xa9a6 => 0x2502,
        0xa9a7 => 0x2503,
        0xa9a8 => 0x2504,
        0xa9a9 => 0x2505,
        0xb1b1 => 0x5317,
        0xb2e2 => 0x6e2c,
        0xbcf2 => 0x7c21,
        0xbea9 => 0x4eac,
        0xcad4 => 0x8a66,
        0xcce5 => 0x9ad4,
        0xcec4 => 0x6587,
        0xd6d0 => 0x4e2d,
    ];

    /** @var list<array{0:int, 1:int}> */
    private const GB18030_RANGES = [
        [0, 0x0080],
        [36, 0x00a5],
        [38, 0x00a9],
        [45, 0x00b2],
        [50, 0x00b8],
        [81, 0x00d8],
        [89, 0x00e2],
        [95, 0x00eb],
        [96, 0x00ee],
        [100, 0x00f4],
        [103, 0x00f8],
        [104, 0x00fb],
        [105, 0x00fd],
        [109, 0x0102],
        [126, 0x0114],
        [133, 0x011c],
        [148, 0x012c],
        [172, 0x0145],
        [175, 0x0149],
        [179, 0x014e],
        [208, 0x016c],
        [306, 0x01cf],
        [307, 0x01d1],
        [308, 0x01d3],
        [309, 0x01d5],
        [310, 0x01d7],
        [311, 0x01d9],
        [312, 0x01db],
        [313, 0x01dd],
        [341, 0x01fa],
        [428, 0x0252],
        [443, 0x0262],
        [544, 0x02c8],
        [545, 0x02cc],
        [558, 0x02da],
        [741, 0x03a2],
        [742, 0x03aa],
        [749, 0x03c2],
        [750, 0x03ca],
        [805, 0x0402],
        [819, 0x0450],
        [820, 0x0452],
        [7922, 0x2011],
        [7924, 0x2017],
        [7925, 0x201a],
        [7927, 0x201e],
        [7934, 0x2027],
        [7943, 0x2031],
        [7944, 0x2034],
        [7945, 0x2036],
        [7950, 0x203c],
        [8062, 0x20ad],
        [8148, 0x2104],
        [8149, 0x2106],
        [8152, 0x210a],
        [8164, 0x2117],
        [8174, 0x2122],
        [8236, 0x216c],
        [8240, 0x217a],
        [8262, 0x2194],
        [8264, 0x219a],
        [8374, 0x2209],
        [8380, 0x2210],
        [8381, 0x2212],
        [8384, 0x2216],
        [8388, 0x221b],
        [8390, 0x2221],
        [8392, 0x2224],
        [8393, 0x2226],
        [8394, 0x222c],
        [8396, 0x222f],
        [8401, 0x2238],
        [8406, 0x223e],
        [8416, 0x2249],
        [8419, 0x224d],
        [8424, 0x2253],
        [8437, 0x2262],
        [8439, 0x2268],
        [8445, 0x2270],
        [8482, 0x2296],
        [8485, 0x229a],
        [8496, 0x22a6],
        [8521, 0x22c0],
        [8603, 0x2313],
        [8936, 0x246a],
        [8946, 0x249c],
        [9046, 0x254c],
        [9050, 0x2574],
        [9063, 0x2590],
        [9066, 0x2596],
        [9076, 0x25a2],
        [9092, 0x25b4],
        [9100, 0x25be],
        [9108, 0x25c8],
        [9111, 0x25cc],
        [9113, 0x25d0],
        [9131, 0x25e6],
        [9162, 0x2607],
        [9164, 0x260a],
        [9218, 0x2641],
        [9219, 0x2643],
        [11329, 0x2e82],
        [11331, 0x2e85],
        [11334, 0x2e89],
        [11336, 0x2e8d],
        [11346, 0x2e98],
        [11361, 0x2ea8],
        [11363, 0x2eab],
        [11366, 0x2eaf],
        [11370, 0x2eb4],
        [11372, 0x2eb8],
        [11375, 0x2ebc],
        [11389, 0x2ecb],
        [11682, 0x2ffc],
        [11686, 0x3004],
        [11687, 0x3018],
        [11692, 0x301f],
        [11694, 0x302a],
        [11714, 0x303f],
        [11716, 0x3094],
        [11723, 0x309f],
        [11725, 0x30f7],
        [11730, 0x30ff],
        [11736, 0x312a],
        [11982, 0x322a],
        [11989, 0x3232],
        [12102, 0x32a4],
        [12336, 0x3390],
        [12348, 0x339f],
        [12350, 0x33a2],
        [12384, 0x33c5],
        [12393, 0x33cf],
        [12395, 0x33d3],
        [12397, 0x33d6],
        [12510, 0x3448],
        [12553, 0x3474],
        [12851, 0x359f],
        [12962, 0x360f],
        [12973, 0x361b],
        [13738, 0x3919],
        [13823, 0x396f],
        [13919, 0x39d1],
        [13933, 0x39e0],
        [14080, 0x3a74],
        [14298, 0x3b4f],
        [14585, 0x3c6f],
        [14698, 0x3ce1],
        [15583, 0x4057],
        [15847, 0x4160],
        [16318, 0x4338],
        [16434, 0x43ad],
        [16438, 0x43b2],
        [16481, 0x43de],
        [16729, 0x44d7],
        [17102, 0x464d],
        [17122, 0x4662],
        [17315, 0x4724],
        [17320, 0x472a],
        [17402, 0x477d],
        [17418, 0x478e],
        [17859, 0x4948],
        [17909, 0x497b],
        [17911, 0x497e],
        [17915, 0x4984],
        [17916, 0x4987],
        [17936, 0x499c],
        [17939, 0x49a0],
        [17961, 0x49b8],
        [18664, 0x4c78],
        [18703, 0x4ca4],
        [18814, 0x4d1a],
        [18962, 0x4daf],
        [19043, 0x9fa6],
        [33469, 0xe76c],
        [33470, 0xe7c8],
        [33471, 0xe7e7],
        [33484, 0xe815],
        [33485, 0xe819],
        [33490, 0xe81f],
        [33497, 0xe827],
        [33501, 0xe82d],
        [33505, 0xe833],
        [33513, 0xe83c],
        [33520, 0xe844],
        [33536, 0xe856],
        [33550, 0xe865],
        [37845, 0xf92d],
        [37921, 0xf97a],
        [37948, 0xf996],
        [38029, 0xf9e8],
        [38038, 0xf9f2],
        [38064, 0xfa10],
        [38065, 0xfa12],
        [38066, 0xfa15],
        [38069, 0xfa19],
        [38075, 0xfa22],
        [38076, 0xfa25],
        [38078, 0xfa2a],
        [39108, 0xfe32],
        [39109, 0xfe45],
        [39113, 0xfe53],
        [39114, 0xfe58],
        [39115, 0xfe67],
        [39116, 0xfe6c],
        [39265, 0xff5f],
        [39394, 0xffe6],
        [189000, 0x10000],
    ];

    /** @var array<int, int> */
    private const EUC_KR_PAIRS = [
        0xb1db => 0xae00,
        0xbcad => 0xc11c,
        0xbdba => 0xc2a4,
        0xbfef => 0xc6b8,
        0xc5d7 => 0xd14c,
        0xc6ae => 0xd2b8,
        0xc7d1 => 0xd55c,
    ];

    /** @var array<int, int> */
    private const WINDOWS_949_EXTENSION_PAIRS = [
        0x8141 => 0xac02,
        0x8142 => 0xac03,
        0x8143 => 0xac05,
        0x8151 => 0xac26,
        0x8152 => 0xac27,
        0x81a1 => 0xac7e,
        0x81a2 => 0xac7f,
    ];

    /** @var list<int> */
    private const EAST_ASIAN_AMBIGUOUS_SINGLE_CODEPOINTS = [
        0x00a1, 0x00a4, 0x00aa, 0x00c6, 0x00d0, 0x00d7, 0x00d8, 0x00e6,
        0x00f0, 0x00fc, 0x00fe, 0x0101, 0x0111, 0x0113, 0x011b, 0x012b,
        0x0138, 0x0144, 0x014d, 0x016b, 0x01ce, 0x01d0, 0x01d2, 0x01d4,
        0x01d6, 0x01d8, 0x01da, 0x01dc, 0x0251, 0x0261, 0x02c4, 0x02c7,
        0x02cd, 0x02d0, 0x02dd, 0x02df, 0x0401, 0x0451, 0x2010, 0x2030,
        0x2035, 0x203b, 0x203e, 0x2074, 0x207f, 0x20ac, 0x2103, 0x2105,
        0x2109, 0x2113, 0x2116, 0x2126, 0x212b, 0x2189, 0x21d2, 0x21d4,
        0x21e7, 0x2200, 0x220b, 0x220f, 0x2211, 0x2215, 0x221a, 0x2223,
        0x2225, 0x222e, 0x2248, 0x224c, 0x2252, 0x2295, 0x2299, 0x22a5,
        0x22bf, 0x2312, 0x25cb, 0x25ef, 0x2609, 0x261e, 0x2642, 0x266f,
        0x26e3, 0x26fe, 0x2776, 0x2777,
    ];

    /** @var list<array{0:int, 1:int}> */
    private const EAST_ASIAN_AMBIGUOUS_RANGES = [
        [0x00a7, 0x00a8], [0x00ad, 0x00ae], [0x00b0, 0x00b4], [0x00b6, 0x00ba],
        [0x00bc, 0x00bf], [0x00de, 0x00e1], [0x00e8, 0x00ea], [0x00ec, 0x00ed],
        [0x00f2, 0x00f3], [0x00f7, 0x00fa], [0x0126, 0x0127], [0x0131, 0x0133],
        [0x013f, 0x0142], [0x0148, 0x014b], [0x0152, 0x0153], [0x0166, 0x0167],
        [0x02c9, 0x02cb], [0x02d8, 0x02db], [0x0391, 0x03a1], [0x03a3, 0x03a9],
        [0x03b1, 0x03c1], [0x03c3, 0x03c9], [0x0410, 0x044f], [0x2013, 0x2016],
        [0x2018, 0x2019], [0x201c, 0x201d], [0x2020, 0x2022], [0x2024, 0x2027],
        [0x2032, 0x2033], [0x2081, 0x2084], [0x2121, 0x2122], [0x2153, 0x2154],
        [0x215b, 0x215e], [0x2160, 0x216b], [0x2170, 0x2179], [0x2190, 0x2199],
        [0x21b8, 0x21b9], [0x2202, 0x2203], [0x2207, 0x2208], [0x221d, 0x2220],
        [0x2227, 0x222c], [0x2234, 0x2237], [0x223c, 0x223d], [0x2260, 0x2261],
        [0x2264, 0x2267], [0x226a, 0x226b], [0x226e, 0x226f], [0x2282, 0x2283],
        [0x2286, 0x2287], [0x2460, 0x24e9], [0x24eb, 0x254b], [0x2550, 0x2573],
        [0x2580, 0x258f], [0x2592, 0x2595], [0x25a0, 0x25a1], [0x25a3, 0x25a9],
        [0x25b2, 0x25b3], [0x25b6, 0x25b7], [0x25bc, 0x25bd], [0x25c0, 0x25c1],
        [0x25c6, 0x25c8], [0x25ce, 0x25d1], [0x25e2, 0x25e5], [0x2605, 0x2606],
        [0x260e, 0x260f], [0x261c, 0x261c], [0x2640, 0x2640], [0x2660, 0x2661],
        [0x2663, 0x2665], [0x2667, 0x266a], [0x266c, 0x266d], [0x269e, 0x269f],
        [0x26bf, 0x26bf], [0x26c6, 0x26cd], [0x26cf, 0x26d3], [0x26d5, 0x26e1],
        [0x26e8, 0x26e9], [0x26eb, 0x26f1], [0x26f4, 0x26f4], [0x26f6, 0x26f9],
        [0x26fb, 0x26fc], [0x273d, 0x273d], [0x2778, 0x277f], [0x2b56, 0x2b59],
        [0xe000, 0xf8ff], [0xfffd, 0xfffd],
    ];

    /**
     * @return array{text:string, encoding:string, bom:string|null, repairs:int, lineEndings:array{normalized:bool, crlf:int, cr:int, conversions:int}, diagnostics?:list<string>, normalization?:array{form:string, changed:bool, implementation:string}}
     */
    public static function decodeBytes(string $bytes, ?string $encoding = null, ?string $normalizationForm = null): array
    {
        $requestedEncoding = self::normalizeEncoding($encoding);
        $normalized = $requestedEncoding;
        $diagnostics = self::requestedEncodingDiagnostics($encoding, $requestedEncoding);
        $bom = null;
        $bomInfo = self::byteOrderMarkEncoding($bytes);
        if ($bomInfo !== null) {
            $bom = $bomInfo['encoding'];
            $bytes = substr($bytes, $bomInfo['length']);
            if ($requestedEncoding !== null && !self::isBomCompatibleEncoding($requestedEncoding, $bomInfo['encoding'])) {
                $diagnostics[] = 'byte-order-mark-overrode-encoding:' . $requestedEncoding;
            }
            $normalized = $bomInfo['encoding'];
        }

        $normalized ??= 'utf-8';
        if ($normalized === 'utf-16') {
            $normalized = $bom === 'utf-16be' ? 'utf-16be' : 'utf-16le';
        }
        if ($normalized === 'utf-32') {
            $normalized = $bom === 'utf-32le' ? 'utf-32le' : 'utf-32be';
        }

        if ($normalized === 'utf-32le' || $normalized === 'utf-32be') {
            [$text, $repairs] = self::decodeUtf32($bytes, $normalized === 'utf-32le');

            return self::decodedResult($text, $normalized, $bom, $repairs, $normalizationForm, $diagnostics);
        }

        if ($normalized === 'utf-16le' || $normalized === 'utf-16be') {
            [$text, $repairs] = self::decodeUtf16($bytes, $normalized === 'utf-16le');

            return self::decodedResult($text, $normalized, $bom, $repairs, $normalizationForm, $diagnostics);
        }

        if ($normalized === 'windows-1252'
            || $normalized === 'windows-1250'
            || $normalized === 'windows-1251'
            || $normalized === 'windows-1253'
            || $normalized === 'windows-1254'
            || $normalized === 'windows-1255'
            || $normalized === 'windows-1256'
            || $normalized === 'windows-1257'
            || $normalized === 'windows-1258'
            || $normalized === 'windows-874'
            || $normalized === 'cp165'
            || $normalized === 'koi8-r'
            || $normalized === 'koi8-u'
            || $normalized === 'koi8-ru'
            || $normalized === 'koi8-t'
            || $normalized === 'ibm437'
            || $normalized === 'ibm737'
            || $normalized === 'ibm775'
            || $normalized === 'ibm850'
            || $normalized === 'ibm857'
            || $normalized === 'ibm852'
            || $normalized === 'ibm860'
            || $normalized === 'ibm861'
            || $normalized === 'ibm862'
            || $normalized === 'ibm863'
            || $normalized === 'ibm864'
            || $normalized === 'ibm865'
            || $normalized === 'ibm855'
            || $normalized === 'ibm866'
            || $normalized === 'ibm869'
            || $normalized === 'iso-8859-1'
            || $normalized === 'iso-8859-2'
            || $normalized === 'iso-8859-3'
            || $normalized === 'iso-8859-4'
            || $normalized === 'iso-8859-5'
            || $normalized === 'iso-8859-6'
            || $normalized === 'iso-8859-7'
            || $normalized === 'iso-8859-8'
            || $normalized === 'iso-8859-9'
            || $normalized === 'iso-8859-10'
            || $normalized === 'iso-8859-13'
            || $normalized === 'iso-8859-14'
            || $normalized === 'iso-8859-16'
            || $normalized === 'tis-620'
            || $normalized === 'iso-8859-15'
            || $normalized === 'gb1988'
            || $normalized === 'macintosh'
            || $normalized === 'mac-symbol'
            || $normalized === 'mac-dingbats'
            || $normalized === 'mac-arabic'
            || $normalized === 'mac-croatian'
            || $normalized === 'mac-thai'
            || $normalized === 'mac-turkish'
            || $normalized === 'mac-iceland'
            || $normalized === 'mac-romania'
            || $normalized === 'mac-central-europe'
            || $normalized === 'mac-cyrillic'
            || $normalized === 'mac-ukrainian'
            || $normalized === 'mac-greek'
            || $normalized === 'x-user-defined'
        ) {
            [$text, $repairs] = self::decodeSingleByte($bytes, $normalized);

            return self::decodedResult($text, $normalized, $bom, $repairs, $normalizationForm, $diagnostics);
        }
        if ($normalized === 'shift_jis'
            || $normalized === 'euc-jp'
            || $normalized === 'iso-2022-jp'
            || $normalized === 'mac-japan'
            || $normalized === 'big5'
            || $normalized === 'cp950'
            || $normalized === 'euc-tw'
            || $normalized === 'gbk'
            || $normalized === 'gb12345'
            || $normalized === 'gb18030'
            || $normalized === 'euc-kr'
            || $normalized === 'windows-949'
            || $normalized === 'iso-2022-kr'
            || $normalized === 'iso-2022-cn'
            || $normalized === 'hz-gb-2312'
        ) {
            [$text, $repairs] = match ($normalized) {
                'shift_jis' => self::decodeShiftJis($bytes),
                'euc-jp' => self::decodeEucJp($bytes),
                'iso-2022-jp' => self::decodeIso2022Jp($bytes),
                'mac-japan' => self::decodeMacJapanese($bytes),
                'big5' => self::decodeBig5($bytes),
                'cp950' => self::decodeBig5($bytes, true),
                'euc-tw' => self::decodeEucTw($bytes),
                'gbk' => self::decodeGbk($bytes),
                'gb12345' => self::decodeGb12345($bytes),
                'gb18030' => self::decodeGb18030($bytes),
                'euc-kr' => self::decodeEucKr($bytes),
                'windows-949' => self::decodeEucKr($bytes, true),
                'iso-2022-kr' => self::decodeIso2022Kr($bytes),
                'iso-2022-cn' => self::decodeIso2022Cn($bytes),
                default => self::decodeHzGb2312($bytes),
            };

            return self::decodedResult($text, $normalized, $bom, $repairs, $normalizationForm, $diagnostics);
        }

        [$text, $repairs] = self::repairUtf8($bytes);
        if ($repairs > 0) {
            $diagnostics[] = 'invalid-utf8-repaired:' . $repairs;
        }

        return self::decodedResult($text, $repairs === 0 ? 'utf-8' : 'utf-8-repaired', $bom, $repairs, $normalizationForm, $diagnostics);
    }

    /**
     * @return array{encoding:string|null, label:string|null, source:string|null, offset:int|null, diagnostics:list<string>}
     */
    public static function declaredCharset(string $bytes, ?string $contentType = null): array
    {
        $bomInfo = self::byteOrderMarkEncoding($bytes);
        if ($bomInfo !== null) {
            return self::declaredCharsetResult(
                $bomInfo['encoding'],
                'byte-order-mark',
                0,
                self::ignoredBomCharsetDiagnostics($bytes, $contentType, $bomInfo['encoding'], $bomInfo['length'])
            );
        }

        if ($contentType !== null) {
            $candidate = self::contentTypeCharsetCandidate($contentType);
            if ($candidate !== null) {
                return self::declaredCharsetResult($candidate['label'], 'content-type', $candidate['offset']);
            }
        }

        $candidate = self::inDocumentCharsetCandidate($bytes);
        if ($candidate !== null) {
            return self::declaredCharsetResult($candidate['label'], $candidate['source'], $candidate['offset']);
        }

        return [
            'encoding' => null,
            'label' => null,
            'source' => null,
            'offset' => null,
            'diagnostics' => [],
        ];
    }

    /**
     * @return array{encoding:string, length:int}|null
     */
    private static function byteOrderMarkEncoding(string $bytes): ?array
    {
        if (str_starts_with($bytes, "\xFF\xFE\x00\x00")) {
            return ['encoding' => 'utf-32le', 'length' => 4];
        }
        if (str_starts_with($bytes, "\x00\x00\xFE\xFF")) {
            return ['encoding' => 'utf-32be', 'length' => 4];
        }
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return ['encoding' => 'utf-8', 'length' => 3];
        }
        if (str_starts_with($bytes, "\xFF\xFE")) {
            return ['encoding' => 'utf-16le', 'length' => 2];
        }
        if (str_starts_with($bytes, "\xFE\xFF")) {
            return ['encoding' => 'utf-16be', 'length' => 2];
        }

        return null;
    }

    public static function repair(string $text): string
    {
        return self::repairUtf8($text)[0];
    }

    /**
     * @return array{text:string, form:string, changed:bool, implementation:string}
     */
    public static function normalize(string $text, string $form = 'nfc', string $implementation = 'auto'): array
    {
        $form = self::normalizeNormalizationForm($form);
        $implementation = self::normalizeNormalizationImplementation($implementation);
        $text = self::repair($text);
        $normalized = null;
        $usedImplementation = 'fallback';
        if ($implementation !== 'fallback') {
            $normalized = self::normalizeWithIntl($text, $form);
            if ($normalized !== null) {
                $usedImplementation = 'intl';
            }
        }
        if ($normalized === null) {
            $normalized = self::normalizeWithFallback($text, $form);
        }

        return [
            'text' => $normalized,
            'form' => $form,
            'changed' => $normalized !== $text,
            'implementation' => $usedImplementation,
        ];
    }

    /**
     * @return list<string>
     */
    public static function characters(string $text): array
    {
        $text = self::repair($text);
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $chars === false ? str_split($text) : $chars;
    }

    /**
     * @return list<string>
     */
    public static function graphemes(string $text): array
    {
        $clusters = [];
        $joinNext = false;
        $indicViramaJoinNext = false;
        $regionalIndicatorRun = 0;
        $prependPrefix = '';
        foreach (self::characters($text) as $char) {
            $codepoint = self::codepoint($char);
            if (self::isBoundedPrependedFormatControl($codepoint)) {
                $prependPrefix .= $char;
                $joinNext = false;
                $indicViramaJoinNext = false;
                $regionalIndicatorRun = 0;
                continue;
            }

            $combiningOrZeroWidth = self::isCombiningOrZeroWidth($codepoint);
            $emojiSkinToneModifier = self::isEmojiSkinToneModifier($codepoint);
            $clusterExtender = $combiningOrZeroWidth || self::isBoundedGraphemeSpacingMark($codepoint);
            if ($prependPrefix !== '' && $clusterExtender) {
                $prependPrefix .= $char;
                $joinNext = false;
                $indicViramaJoinNext = false;
                $regionalIndicatorRun = 0;
                continue;
            }

            $regionalIndicator = self::isRegionalIndicator($codepoint);
            $indicConsonant = self::isBoundedIndicConsonant($codepoint);
            $hasPrependPrefix = $prependPrefix !== '';
            $clusterText = $hasPrependPrefix ? $prependPrefix . $char : $char;
            $prependPrefix = '';
            $append = !$hasPrependPrefix
                && $clusters !== []
                && (
                    $joinNext
                    || $clusterExtender
                    || ($emojiSkinToneModifier && self::canAppendEmojiSkinToneModifier($clusters[count($clusters) - 1]))
                    || ($indicViramaJoinNext && $indicConsonant)
                    || ($regionalIndicator && $regionalIndicatorRun === 1)
                );
            $hadIndicViramaJoinNext = $indicViramaJoinNext;

            if (!$append) {
                $clusters[] = $clusterText;
                $regionalIndicatorRun = $regionalIndicator ? 1 : 0;
            } else {
                $clusters[count($clusters) - 1] .= $clusterText;
                if ($regionalIndicator) {
                    $regionalIndicatorRun = min(2, $regionalIndicatorRun + 1);
                } elseif (!$clusterExtender && $codepoint !== 0x200d) {
                    $regionalIndicatorRun = 0;
                }
            }
            $joinNext = $codepoint === 0x200d
                && $clusters !== []
                && self::canZeroWidthJoinNext($clusters[count($clusters) - 1]);
            $indicViramaJoinNext = self::isBoundedIndicVirama($codepoint)
                || ($codepoint === 0x200d && $hadIndicViramaJoinNext);
        }
        if ($prependPrefix !== '') {
            if ($clusters === []) {
                $clusters[] = $prependPrefix;
            } else {
                $clusters[count($clusters) - 1] .= $prependPrefix;
            }
        }

        return $clusters;
    }

    public static function displayWidth(string $text, string $ambiguousWidth = 'narrow'): int
    {
        $ambiguousColumns = self::ambiguousWidthColumns($ambiguousWidth);
        $width = 0;
        foreach (self::graphemes($text) as $cluster) {
            $width += self::graphemeDisplayWidthAtColumn($cluster, $ambiguousColumns, $width);
        }

        return $width;
    }

    private static function displayWidthFromColumn(string $text, int $ambiguousColumns, int $column): int
    {
        $width = 0;
        foreach (self::graphemes($text) as $cluster) {
            $clusterWidth = self::graphemeDisplayWidthAtColumn($cluster, $ambiguousColumns, $column + $width);
            $width += $clusterWidth;
        }

        return $width;
    }

    /**
     * @return array{0:string, 1:string}
     */
    public static function splitAtDisplayWidth(string $text, int $width, string $ambiguousWidth = 'narrow'): array
    {
        $ambiguousColumns = self::ambiguousWidthColumns($ambiguousWidth);
        $text = self::repair($text);
        if ($width <= 0 || $text === '') {
            return ['', $text];
        }

        $head = '';
        $usedWidth = 0;
        foreach (self::graphemes($text) as $cluster) {
            $clusterWidth = self::graphemeDisplayWidthAtColumn($cluster, $ambiguousColumns, $usedWidth);
            $head .= $cluster;
            $usedWidth += $clusterWidth;

            if ($usedWidth >= $width) {
                return [$head, substr($text, strlen($head))];
            }
        }

        return [$text, ''];
    }

    /**
     * Split text at absolute display-width breakpoints.
     *
     * @param list<int> $breakpoints
     * @return list<string>
     */
    public static function splitByDisplayBreakpoints(string $text, array $breakpoints, string $ambiguousWidth = 'narrow'): array
    {
        $segments = [];
        $remaining = self::repair($text);
        $previous = 0;

        foreach ($breakpoints as $breakpoint) {
            $relativeWidth = max(0, $breakpoint - $previous);
            [$segment, $remaining] = self::splitAtDisplayWidth($remaining, $relativeWidth, $ambiguousWidth);
            $segments[] = $segment;
            $previous = $breakpoint;
        }

        $segments[] = $remaining;

        return $segments;
    }

    /**
     * Wrap text to display columns without splitting grapheme clusters.
     *
     * This is intentionally bounded: horizontal whitespace and selected
     * Unicode space separators are soft break opportunities, while Unicode hard
     * line separators reset indentation.
     *
     * @return list<string>
     */
    public static function wrapByDisplayWidth(string $text, int $width, string $subsequentIndent = '', string $ambiguousWidth = 'narrow'): array
    {
        [$text] = self::normalizeLineEndings(self::repair($text));
        if ($width <= 0) {
            return explode("\n", $text);
        }

        $physicalLines = preg_split('/\R/u', $text);
        if ($physicalLines === false) {
            $physicalLines = explode("\n", $text);
        }

        $wrapped = [];
        foreach ($physicalLines as $line) {
            foreach (self::wrapDisplayLine($line, $width, $subsequentIndent, $ambiguousWidth) as $wrappedLine) {
                $wrapped[] = $wrappedLine;
            }
        }

        return $wrapped;
    }

    /**
     * Report bounded Unicode line-break opportunities using the same
     * display-width and separator rules as wrapByDisplayWidth().
     *
     * @return array{
     *   opportunityCount:int,
     *   softBreakCount:int,
     *   hardBreakCount:int,
     *   protectedSeparatorCount:int,
     *   lineEndings:array{normalized:bool, crlf:int, cr:int, conversions:int},
     *   opportunities:list<array{type:string, break:string, codepoint:string, text:string, byteOffset:int, column:int, columnAfter:int, emitted:string}>,
     *   protectedSeparators:list<array{type:string, codepoint:string, text:string, byteOffset:int, column:int, columnAfter:int}>
     * }
     */
    public static function lineBreakOpportunities(string $text, string $ambiguousWidth = 'narrow'): array
    {
        $ambiguousColumns = self::ambiguousWidthColumns($ambiguousWidth);
        [$text, $lineEndings] = self::normalizeLineEndings(self::repair($text));

        $opportunities = [];
        $protectedSeparators = [];
        $softBreaks = 0;
        $hardBreaks = 0;
        $column = 0;
        $byteOffset = 0;
        $buffer = '';
        $flushBuffer = static function () use (&$buffer, &$column, $ambiguousColumns): void {
            if ($buffer === '') {
                return;
            }

            $column += self::displayWidthFromColumn($buffer, $ambiguousColumns, $column);
            $buffer = '';
        };

        foreach (self::characters($text) as $char) {
            $codepoint = self::codepoint($char);
            $break = self::lineBreakOpportunityForCodepoint($codepoint, $char);
            $protectedType = $break === null ? self::protectedLineBreakSeparatorType($codepoint) : null;

            if ($break === null && $protectedType === null) {
                $buffer .= $char;
                $byteOffset += strlen($char);
                continue;
            }

            $flushBuffer();
            $columnAfter = $column + self::graphemeDisplayWidthAtColumn($char, $ambiguousColumns, $column);
            if ($break !== null) {
                $opportunities[] = [
                    'type' => $break['type'],
                    'break' => $break['break'],
                    'codepoint' => self::codepointLabel($codepoint),
                    'text' => $char,
                    'byteOffset' => $byteOffset,
                    'column' => $column,
                    'columnAfter' => $columnAfter,
                    'emitted' => $break['emitted'],
                ];
                if ($break['break'] === 'hard') {
                    ++$hardBreaks;
                    $column = 0;
                } else {
                    ++$softBreaks;
                    $column = $columnAfter;
                }
            } else {
                $protectedSeparators[] = [
                    'type' => $protectedType,
                    'codepoint' => self::codepointLabel($codepoint),
                    'text' => $char,
                    'byteOffset' => $byteOffset,
                    'column' => $column,
                    'columnAfter' => $columnAfter,
                ];
                $column = $columnAfter;
            }

            $byteOffset += strlen($char);
        }

        return [
            'opportunityCount' => count($opportunities),
            'softBreakCount' => $softBreaks,
            'hardBreakCount' => $hardBreaks,
            'protectedSeparatorCount' => count($protectedSeparators),
            'lineEndings' => $lineEndings,
            'opportunities' => $opportunities,
            'protectedSeparators' => $protectedSeparators,
        ];
    }

    public static function padDisplay(string $text, int $width, string $alignment = 'left', string $ambiguousWidth = 'narrow'): string
    {
        $padding = max(0, $width - self::displayWidth($text, $ambiguousWidth));

        return match ($alignment) {
            'right' => str_repeat(' ', $padding) . $text,
            'center' => str_repeat(' ', intdiv($padding, 2)) . $text . str_repeat(' ', $padding - intdiv($padding, 2)),
            default => $text . str_repeat(' ', $padding),
        };
    }

    /**
     * @return array{0:string, 1:array{normalized:bool, crlf:int, cr:int, conversions:int}}
     */
    public static function normalizeLineEndings(string $text): array
    {
        $crlf = substr_count($text, "\r\n");
        $normalized = str_replace("\r\n", "\n", $text);
        $cr = substr_count($normalized, "\r");
        if ($cr > 0) {
            $normalized = str_replace("\r", "\n", $normalized);
        }

        return [
            $normalized,
            [
                'normalized' => $crlf > 0 || $cr > 0,
                'crlf' => $crlf,
                'cr' => $cr,
                'conversions' => $crlf + $cr,
            ],
        ];
    }

    /**
     * @param list<string> $diagnostics
     * @return array{text:string, encoding:string, bom:string|null, repairs:int, lineEndings:array{normalized:bool, crlf:int, cr:int, conversions:int}, diagnostics?:list<string>, normalization?:array{form:string, changed:bool, implementation:string}}
     */
    private static function decodedResult(
        string $text,
        string $encoding,
        ?string $bom,
        int $repairs,
        ?string $normalizationForm,
        array $diagnostics = []
    ): array
    {
        [$text, $lineEndings] = self::normalizeLineEndings($text);
        $result = [
            'text' => $text,
            'encoding' => $encoding,
            'bom' => $bom,
            'repairs' => $repairs,
            'lineEndings' => $lineEndings,
        ];

        if ($diagnostics !== []) {
            $result['diagnostics'] = array_values(array_unique($diagnostics));
        }

        if ($normalizationForm !== null && trim($normalizationForm) !== '') {
            $normalization = self::normalize($text, $normalizationForm);
            $result['text'] = $normalization['text'];
            $result['normalization'] = [
                'form' => $normalization['form'],
                'changed' => $normalization['changed'],
                'implementation' => $normalization['implementation'],
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function requestedEncodingDiagnostics(?string $label, ?string $encoding): array
    {
        if ($label === null || trim($label) === '') {
            return [];
        }

        if ($encoding === 'utf-8' && !self::isExplicitUtf8EncodingLabel($label)) {
            return ['unknown-charset-label-defaulted-to-utf-8'];
        }

        return [];
    }

    private static function isBomCompatibleEncoding(string $requestedEncoding, string $bomEncoding): bool
    {
        if ($requestedEncoding === $bomEncoding) {
            return true;
        }

        if ($requestedEncoding === 'utf-16') {
            return $bomEncoding === 'utf-16le' || $bomEncoding === 'utf-16be';
        }

        if ($requestedEncoding === 'utf-32') {
            return $bomEncoding === 'utf-32le' || $bomEncoding === 'utf-32be';
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function wrapDisplayLine(string $line, int $width, string $subsequentIndent, string $ambiguousWidth): array
    {
        $fragments = self::wrapFragments($line);
        if ($fragments === []) {
            return [''];
        }

        $lines = [];
        $current = '';
        foreach ($fragments as $fragment) {
            $text = $fragment['text'];
            if ($current === '') {
                [$lines, $current] = self::startWrappedToken($lines, $text, $width, $subsequentIndent, $ambiguousWidth);
                continue;
            }

            $candidate = $current . self::wrapSeparatorText($fragment['separator'], $fragment['separatorText']) . $text;
            if (self::displayWidth($candidate, $ambiguousWidth) <= self::wrapContentWidth(count($lines), $width, $subsequentIndent, $ambiguousWidth)) {
                $current = $candidate;
                continue;
            }

            $lines[] = self::wrapLinePrefix(count($lines), $subsequentIndent)
                . self::wrapBreakLineText($current, $fragment['separator'], count($lines), $width, $subsequentIndent, $ambiguousWidth);
            [$lines, $current] = self::startWrappedToken($lines, $text, $width, $subsequentIndent, $ambiguousWidth);
        }

        if ($current !== '') {
            $lines[] = self::wrapLinePrefix(count($lines), $subsequentIndent) . $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * @return list<array{text:string, separator:string, separatorText:string}>
     */
    private static function wrapFragments(string $line): array
    {
        $line = trim($line);
        if ($line === '') {
            return [];
        }

        $fragments = [];
        $buffer = '';
        $separator = 'none';
        $separatorText = '';
        foreach (self::characters($line) as $char) {
            $codepoint = self::codepoint($char);
            if (self::isWrapWhitespace($codepoint)) {
                self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);
                $buffer = '';
                $separator = 'space';
                $separatorText = self::wrapWhitespaceSeparatorText($codepoint, $char);
                continue;
            }
            if ($codepoint === 0x200b) {
                self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);
                $buffer = '';
                $separator = 'soft';
                $separatorText = '';
                continue;
            }
            if ($codepoint === 0x00ad) {
                self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);
                $buffer = '';
                $separator = 'soft-hyphen';
                $separatorText = '';
                continue;
            }
            if (self::isVisibleBreakAfterSeparator($codepoint)) {
                $buffer .= $char;
                self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);
                $buffer = '';
                $separator = 'soft';
                $separatorText = '';
                continue;
            }

            $buffer .= $char;
        }
        self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);

        return $fragments;
    }

    /**
     * @param list<array{text:string, separator:string, separatorText:string}> $fragments
     */
    private static function appendWrapFragment(array &$fragments, string $separator, string $separatorText, string $buffer): void
    {
        if ($buffer === '') {
            return;
        }

        $fragments[] = [
            'text' => $buffer,
            'separator' => $separator,
            'separatorText' => $separatorText,
        ];
    }

    private static function wrapSeparatorText(string $separator, string $separatorText): string
    {
        return $separator === 'space' ? $separatorText : '';
    }

    private static function wrapWhitespaceSeparatorText(int $codepoint, string $char): string
    {
        return $codepoint <= 0x20 ? ' ' : $char;
    }

    /**
     * @return array{type:string, break:string, emitted:string}|null
     */
    private static function lineBreakOpportunityForCodepoint(int $codepoint, string $char): ?array
    {
        if ($codepoint === 0x000a) {
            return ['type' => 'line-feed', 'break' => 'hard', 'emitted' => "\n"];
        }
        if ($codepoint === 0x2028) {
            return ['type' => 'line-separator', 'break' => 'hard', 'emitted' => "\n"];
        }
        if ($codepoint === 0x2029) {
            return ['type' => 'paragraph-separator', 'break' => 'hard', 'emitted' => "\n\n"];
        }
        if (self::isWrapWhitespace($codepoint)) {
            return [
                'type' => self::lineBreakWhitespaceType($codepoint),
                'break' => 'soft',
                'emitted' => self::wrapWhitespaceSeparatorText($codepoint, $char),
            ];
        }
        if ($codepoint === 0x200b) {
            return ['type' => 'zero-width-space', 'break' => 'soft', 'emitted' => ''];
        }
        if ($codepoint === 0x00ad) {
            return ['type' => 'soft-hyphen', 'break' => 'soft', 'emitted' => '-'];
        }
        if (self::isVisibleBreakAfterSeparator($codepoint)) {
            return ['type' => 'visible-break-after', 'break' => 'soft-after', 'emitted' => $char];
        }

        return null;
    }

    private static function lineBreakWhitespaceType(int $codepoint): string
    {
        return match ($codepoint) {
            0x0009 => 'tab',
            0x0020 => 'space',
            0x1680 => 'ogham-space-mark',
            0x2000 => 'en-quad',
            0x2001 => 'em-quad',
            0x2002 => 'en-space',
            0x2003 => 'em-space',
            0x2004 => 'three-per-em-space',
            0x2005 => 'four-per-em-space',
            0x2006 => 'six-per-em-space',
            0x2008 => 'punctuation-space',
            0x2009 => 'thin-space',
            0x200a => 'hair-space',
            0x205f => 'medium-mathematical-space',
            0x3000 => 'ideographic-space',
            default => 'unicode-space',
        };
    }

    private static function protectedLineBreakSeparatorType(int $codepoint): ?string
    {
        return match ($codepoint) {
            0x00a0 => 'no-break-space',
            0x2007 => 'figure-space',
            0x202f => 'narrow-no-break-space',
            0x2060 => 'word-joiner',
            default => null,
        };
    }

    private static function isVisibleBreakAfterSeparator(int $codepoint): bool
    {
        return $codepoint === 0x0f0b;
    }

    private static function wrapBreakLineText(
        string $line,
        string $separator,
        int $lineIndex,
        int $width,
        string $subsequentIndent,
        string $ambiguousWidth
    ): string {
        if ($separator !== 'soft-hyphen') {
            return $line;
        }

        $hyphenated = $line . '-';
        if (self::displayWidth($hyphenated, $ambiguousWidth) <= self::wrapContentWidth($lineIndex, $width, $subsequentIndent, $ambiguousWidth)) {
            return $hyphenated;
        }

        return $line;
    }

    private static function isWrapWhitespace(int $codepoint): bool
    {
        return $codepoint === 0x20
            || $codepoint === 0x09
            || $codepoint === 0x0c
            || $codepoint === 0x0b
            || $codepoint === 0x1680
            || ($codepoint >= 0x2000 && $codepoint <= 0x200a && $codepoint !== 0x2007)
            || $codepoint === 0x205f
            || $codepoint === 0x3000;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:string}
     */
    private static function startWrappedToken(array $lines, string $token, int $width, string $subsequentIndent, string $ambiguousWidth): array
    {
        while ($token !== '') {
            $limit = self::wrapContentWidth(count($lines), $width, $subsequentIndent, $ambiguousWidth);
            if (self::displayWidth($token, $ambiguousWidth) <= $limit) {
                return [$lines, $token];
            }

            [$segment, $token] = self::splitAtDisplayWidth($token, $limit, $ambiguousWidth);
            if ($segment === '') {
                [$segment, $token] = self::splitAtDisplayWidth($token, 1, $ambiguousWidth);
            }
            $lines[] = self::wrapLinePrefix(count($lines), $subsequentIndent) . $segment;
        }

        return [$lines, ''];
    }

    private static function wrapContentWidth(int $lineIndex, int $width, string $subsequentIndent, string $ambiguousWidth): int
    {
        if ($lineIndex === 0) {
            return max(1, $width);
        }

        return max(1, $width - self::displayWidth($subsequentIndent, $ambiguousWidth));
    }

    private static function wrapLinePrefix(int $lineIndex, string $subsequentIndent): string
    {
        return $lineIndex === 0 ? '' : $subsequentIndent;
    }

    private static function normalizeEncoding(?string $encoding): ?string
    {
        if ($encoding === null || trim($encoding) === '') {
            return null;
        }

        $key = strtolower(str_replace(['-', '_', ' '], '', trim($encoding)));

        return match ($key) {
            'utf8' => 'utf-8',
            'utf16' => 'utf-16',
            'utf16le' => 'utf-16le',
            'utf16be' => 'utf-16be',
            'ucs2' => 'utf-16',
            'ucs2le' => 'utf-16le',
            'ucs2be' => 'utf-16be',
            'utf32', 'ucs4' => 'utf-32',
            'utf32le', 'ucs4le' => 'utf-32le',
            'utf32be', 'ucs4be' => 'utf-32be',
            'windows1252', 'cp1252', 'msansi' => 'windows-1252',
            'windows1250', 'cp1250', 'microsoftcp1250' => 'windows-1250',
            'windows1251', 'cp1251', 'microsoftcp1251', 'ms1251', 'xcp1251' => 'windows-1251',
            'windows1253', 'cp1253', 'microsoftcp1253', 'ms1253', 'xcp1253' => 'windows-1253',
            'windows1254', 'cp1254', 'microsoftcp1254', 'ms1254', 'xcp1254' => 'windows-1254',
            'windows1255', 'cp1255', 'microsoftcp1255', 'ms1255', 'xcp1255' => 'windows-1255',
            'windows1256', 'cp1256', 'microsoftcp1256', 'ms1256', 'xcp1256' => 'windows-1256',
            'windows1257', 'cp1257', 'microsoftcp1257', 'ms1257', 'xcp1257' => 'windows-1257',
            'windows1258', 'cp1258', 'microsoftcp1258', 'ms1258', 'xcp1258' => 'windows-1258',
            'windows874', 'cp874', 'microsoftcp874', 'ms874', 'xcp874', 'dos874' => 'windows-874',
            'cp165', 'ibm165', 'dos165', 'xcp165', 'csibm165' => 'cp165',
            'koi8r', 'cskoi8r', 'koi8' => 'koi8-r',
            'koi8u', 'cskoi8u' => 'koi8-u',
            'koi8ru', 'cskoi8ru', 'koi8russianukrainian' => 'koi8-ru',
            'koi8t', 'cskoi8t', 'koi8tajik' => 'koi8-t',
            '437', 'cp437', 'ibm437', 'dos437', 'xcp437', 'oem437', 'cspc8codepage437', 'csibm437' => 'ibm437',
            '737', 'cp737', 'ibm737', 'dos737', 'xcp737', 'oem737', 'csibm737' => 'ibm737',
            '775', 'cp775', 'ibm775', 'dos775', 'xcp775', 'oem775', 'csibm775' => 'ibm775',
            '850', 'cp850', 'ibm850', 'dos850', 'xcp850', 'oem850', 'cspc850multilingual', 'csibm850' => 'ibm850',
            '857', 'cp857', 'ibm857', 'dos857', 'xcp857', 'oem857', 'csibm857' => 'ibm857',
            '852', 'cp852', 'ibm852', 'dos852', 'xcp852', 'oem852', 'cspc852', 'cspcp852', 'csibm852' => 'ibm852',
            '860', 'cp860', 'ibm860', 'dos860', 'xcp860', 'oem860', 'csibm860' => 'ibm860',
            '861', 'cp861', 'ibm861', 'dos861', 'xcp861', 'oem861', 'cpis', 'csibm861' => 'ibm861',
            '862', 'cp862', 'ibm862', 'dos862', 'xcp862', 'oem862', 'cspc862latinhebrew', 'csibm862' => 'ibm862',
            '863', 'cp863', 'ibm863', 'dos863', 'xcp863', 'oem863', 'csibm863' => 'ibm863',
            '864', 'cp864', 'ibm864', 'dos864', 'xcp864', 'oem864', 'csibm864' => 'ibm864',
            '865', 'cp865', 'ibm865', 'dos865', 'xcp865', 'oem865', 'csibm865' => 'ibm865',
            '855', 'cp855', 'ibm855', 'dos855', 'xcp855', 'oem855', 'csibm855' => 'ibm855',
            '866', 'cp866', 'csibm866', 'dos866', 'ibm866', 'xcp866' => 'ibm866',
            '869', 'cp869', 'csibm869', 'dos869', 'ibm869', 'oem869', 'xcp869', 'cpgr' => 'ibm869',
            'iso88591', 'latin1', 'latin-1' => 'iso-8859-1',
            'iso88592', 'iso88592:1987', 'latin2', 'latin-2', 'l2', 'isoir101', 'csisolatin2' => 'iso-8859-2',
            'iso88593', 'iso88593:1988', 'latin3', 'latin-3', 'l3', 'isoir109', 'csisolatin3' => 'iso-8859-3',
            'iso88594', 'iso88594:1988', 'latin4', 'latin-4', 'l4', 'isoir110', 'csisolatin4' => 'iso-8859-4',
            'iso88595', 'iso88595:1988', 'latin5cyrillic', 'isoir144', 'cyrillic', 'csisolatincyrillic' => 'iso-8859-5',
            'iso88596', 'iso88596:1987', 'latin6arabic', 'isoir127', 'arabic', 'asmo708', 'ecma114', 'csisolatinarabic' => 'iso-8859-6',
            'iso88597', 'iso88597:1987', 'latin7greek', 'isoir126', 'greek', 'greek8', 'elot928', 'ecma118', 'csisolatingreek' => 'iso-8859-7',
            'iso88598', 'iso885981999', 'latinhebrew', 'isoir138', 'hebrew', 'csisolatinhebrew',
            'iso88598i', 'iso88598e', 'logical', 'visual' => 'iso-8859-8',
            'iso88599', 'iso88599:1989', 'latin5', 'latin-5', 'l5', 'isoir148', 'turkish', 'csisolatin5' => 'iso-8859-9',
            'iso885910', 'iso885910:1992', 'latin6', 'latin-6', 'l6', 'isoir157', 'csisolatin6' => 'iso-8859-10',
            'iso885913', 'iso885913:1998', 'latin7', 'latin-7', 'l7', 'isoir179', 'csisolatin7' => 'iso-8859-13',
            'iso885914', 'iso885914:1998', 'latin8', 'latin-8', 'l8', 'isoir199', 'isoceltic' => 'iso-8859-14',
            'iso885916', 'iso885916:2001', 'latin10', 'latin-10', 'l10', 'isoir226', 'csisolatin10' => 'iso-8859-16',
            'tis620', 'tis6202533', 'cstis620', 'isoir166', 'iso885911', 'iso8859112001', 'thai' => 'tis-620',
            'iso885915', 'iso8859151999', 'latin9', 'latin-9' => 'iso-8859-15',
            'gb1988', 'gb198880', 'gb1988:1980', 'gb19881980', 'gb1988-80', 'gb-1988-80',
            'cn', 'isoir57', 'csiso57gb1988' => 'gb1988',
            'macintosh', 'macroman', 'mac-roman', 'xmacroman', 'x-mac-roman', 'mac' => 'macintosh',
            'symbol', 'macsymbol', 'mac-symbol', 'xmacsymbol', 'x-mac-symbol',
            'cssymbol' => 'mac-symbol',
            'macdingbats', 'mac-dingbats', 'xmacdingbats', 'x-mac-dingbats',
            'csmacdingbats' => 'mac-dingbats',
            'macarabic', 'mac-arabic', 'xmacarabic', 'x-mac-arabic',
            'csmacarabic', 'arabicmac', 'arabic-mac' => 'mac-arabic',
            'maccroatian', 'mac-croatian', 'xmaccroatian', 'x-mac-croatian',
            'csmaccroatian' => 'mac-croatian',
            'macthai', 'mac-thai', 'xmacthai', 'x-mac-thai', 'xmac-thai', 'csmacthai' => 'mac-thai',
            'macturkish', 'mac-turkish', 'xmacturkish', 'x-mac-turkish', 'xmac-turkish', 'turkishmac',
            'turkish-mac', 'csmacturkish' => 'mac-turkish',
            'maciceland', 'mac-iceland', 'xmaciceland', 'x-mac-iceland',
            'macicelandic', 'mac-icelandic', 'xmacicelandic', 'x-mac-icelandic' => 'mac-iceland',
            'macromania', 'mac-romania', 'xmacromania', 'x-mac-romania',
            'macromanian', 'mac-romanian', 'xmacromanian', 'x-mac-romanian',
            'csmacintoshromanian' => 'mac-romania',
            'maccenteuro', 'xmaccenteuro', 'maccenteurope', 'xmaccenteurope',
            'maccentraleurope', 'xmaccentraleurope', 'maccentraleuropean', 'xmaccentraleuropean',
            'macce', 'xmacce' => 'mac-central-europe',
            'maccyrillic', 'mac-cyrillic', 'xmaccyrillic', 'x-mac-cyrillic' => 'mac-cyrillic',
            'macukraine', 'xmacukraine', 'macukrainian', 'mac-ukrainian',
            'xmacukrainian', 'x-mac-ukrainian', 'csmacukrainian' => 'mac-ukrainian',
            'macgreek', 'mac-greek', 'xmacgreek', 'x-mac-greek' => 'mac-greek',
            'macjapan', 'mac-japan', 'xmacjapan', 'x-mac-japan',
            'macjapanese', 'mac-japanese', 'xmacjapanese', 'x-mac-japanese',
            'csmacjapanese' => 'mac-japan',
            'xuserdefined', 'userdefined' => 'x-user-defined',
            'csshiftjis', 'ms932', 'mskanji', 'shiftjis', 'sjis', 'windows31j', 'xsjis', 'cp932' => 'shift_jis',
            'cseucpkdfmtjapanese', 'eucjp', 'xeucjp' => 'euc-jp',
            'iso2022jp', 'csiso2022jp' => 'iso-2022-jp',
            'big5', 'big5hkscs', 'big5hk', 'cnbig5', 'csbig5', 'xxbig5' => 'big5',
            'cp950', 'windows950', 'ms950', 'xcp950', 'big5cp950' => 'cp950',
            'euctw', 'xeuctw', 'cseuctw' => 'euc-tw',
            'gb18030' => 'gb18030',
            'gbk', 'gb2312', 'gb2312:1980', 'csgb2312', 'csiso58gb231280',
            'cp936', 'ms936', 'windows936', 'xgbk', 'xcp936', 'euccn' => 'gbk',
            'gb12345', 'gb1234590', 'gb12345:1990', 'csgb12345' => 'gb12345',
            'euckr', 'cseuckr', 'csksc56011987', 'korean', 'isoir149', 'ksc5601', 'ksc56011987',
            'ksc56011989' => 'euc-kr',
            'windows949', 'cp949', 'ms949', 'uhc' => 'windows-949',
            'iso2022kr', 'csiso2022kr' => 'iso-2022-kr',
            'iso2022cn', 'csiso2022cn' => 'iso-2022-cn',
            'hzgb2312', 'hz' => 'hz-gb-2312',
            default => 'utf-8',
        };
    }

    /**
     * @return array{label:string, offset:int}|null
     */
    private static function contentTypeCharsetCandidate(string $contentType): ?array
    {
        if (preg_match('/(?:^|;)\s*charset\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^;\s]+))/i', $contentType, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        foreach ([1, 2, 3] as $index) {
            if (($matches[$index][1] ?? -1) < 0) {
                continue;
            }

            $label = trim($matches[$index][0]);
            if ($label !== '') {
                return ['label' => $label, 'offset' => $matches[$index][1]];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function ignoredBomCharsetDiagnostics(string $bytes, ?string $contentType, string $bomEncoding, int $bomLength): array
    {
        $diagnostics = [];
        if ($contentType !== null) {
            $candidate = self::contentTypeCharsetCandidate($contentType);
            if ($candidate !== null && self::isIgnoredBomCharsetLabel($candidate['label'], $bomEncoding)) {
                $diagnostics[] = 'ignored-content-type-charset:' . $candidate['label'];
            }
        }

        if ($bomEncoding === 'utf-8') {
            $candidate = self::inDocumentCharsetCandidate($bytes, $bomLength);
            if ($candidate !== null && self::isIgnoredBomCharsetLabel($candidate['label'], $bomEncoding)) {
                $diagnostics[] = 'ignored-' . $candidate['source'] . ':' . $candidate['label'];
            }
        }

        return $diagnostics;
    }

    private static function isIgnoredBomCharsetLabel(string $label, string $bomEncoding): bool
    {
        $encoding = self::normalizeEncoding($label);

        return $encoding !== $bomEncoding
            || ($encoding === 'utf-8' && !self::isExplicitUtf8EncodingLabel($label));
    }

    /**
     * @return array{label:string, source:string, offset:int}|null
     */
    private static function inDocumentCharsetCandidate(string $bytes, int $baseOffset = 0): ?array
    {
        $probe = substr($bytes, $baseOffset, 4096);
        if (preg_match('/<\?xml\b[^>]{0,512}\bencoding\s*=\s*(?:"([^"]+)"|\'([^\']+)\')/i', $probe, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $label = $matches[1][1] >= 0 ? $matches[1][0] : $matches[2][0];
            $offset = $matches[1][1] >= 0 ? $matches[1][1] : $matches[2][1];

            return ['label' => $label, 'source' => 'xml-declaration', 'offset' => $baseOffset + $offset];
        }

        if (preg_match_all('/<meta\b[^>]{0,1024}>/i', $probe, $metaMatches, PREG_OFFSET_CAPTURE) <= 0) {
            return null;
        }

        foreach ($metaMatches[0] as [$tag, $tagOffset]) {
            $attribute = self::htmlAttributeValue($tag, 'charset');
            if ($attribute !== null) {
                return [
                    'label' => $attribute['value'],
                    'source' => 'html-meta-charset',
                    'offset' => $baseOffset + $tagOffset + $attribute['offset'],
                ];
            }
        }

        foreach ($metaMatches[0] as [$tag, $tagOffset]) {
            $httpEquiv = self::htmlAttributeValue($tag, 'http-equiv');
            if ($httpEquiv === null || strtolower(trim($httpEquiv['value'])) !== 'content-type') {
                continue;
            }

            $content = self::htmlAttributeValue($tag, 'content');
            if ($content === null) {
                continue;
            }

            $candidate = self::contentTypeCharsetCandidate($content['value']);
            if ($candidate !== null) {
                return [
                    'label' => $candidate['label'],
                    'source' => 'html-meta-http-equiv',
                    'offset' => $baseOffset + $tagOffset + $content['offset'] + $candidate['offset'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{value:string, offset:int}|null
     */
    private static function htmlAttributeValue(string $tag, string $name): ?array
    {
        if (preg_match('/^<\s*[a-z0-9:-]+/i', $tag, $matches) !== 1) {
            return null;
        }

        $target = strtolower($name);
        $length = strlen($tag);
        $offset = strlen($matches[0]);
        while ($offset < $length) {
            while ($offset < $length && (ctype_space($tag[$offset]) || $tag[$offset] === '/')) {
                ++$offset;
            }
            if ($offset >= $length || $tag[$offset] === '>') {
                break;
            }

            $attributeNameOffset = $offset;
            while ($offset < $length && !ctype_space($tag[$offset]) && $tag[$offset] !== '=' && $tag[$offset] !== '/' && $tag[$offset] !== '>') {
                ++$offset;
            }
            $attributeName = strtolower(substr($tag, $attributeNameOffset, $offset - $attributeNameOffset));
            while ($offset < $length && ctype_space($tag[$offset])) {
                ++$offset;
            }
            if ($offset >= $length || $tag[$offset] !== '=') {
                continue;
            }

            ++$offset;
            while ($offset < $length && ctype_space($tag[$offset])) {
                ++$offset;
            }
            if ($offset >= $length) {
                break;
            }

            $quote = $tag[$offset];
            if ($quote === '"' || $quote === "'") {
                ++$offset;
                $valueOffset = $offset;
                while ($offset < $length && $tag[$offset] !== $quote) {
                    ++$offset;
                }
                $value = substr($tag, $valueOffset, $offset - $valueOffset);
                if ($offset < $length) {
                    ++$offset;
                }
            } else {
                $valueOffset = $offset;
                while ($offset < $length && !ctype_space($tag[$offset]) && $tag[$offset] !== '>') {
                    ++$offset;
                }
                $value = substr($tag, $valueOffset, $offset - $valueOffset);
                if ($offset < $length && $tag[$offset] === '>' && str_ends_with($value, '/')) {
                    $value = substr($value, 0, -1);
                }
            }

            if ($attributeName === $target) {
                return ['value' => trim($value), 'offset' => $valueOffset];
            }
        }

        return null;
    }

    /**
     * @return array{encoding:string|null, label:string|null, source:string|null, offset:int|null, diagnostics:list<string>}
     */
    private static function declaredCharsetResult(string $label, string $source, int $offset, array $diagnostics = []): array
    {
        $label = trim($label);
        $encoding = self::normalizeEncoding($label);
        if ($encoding === 'utf-8' && !self::isExplicitUtf8EncodingLabel($label)) {
            $diagnostics[] = 'unknown-charset-label-defaulted-to-utf-8';
        }

        return [
            'encoding' => $encoding,
            'label' => $label,
            'source' => $source,
            'offset' => $offset,
            'diagnostics' => $diagnostics,
        ];
    }

    private static function isExplicitUtf8EncodingLabel(string $label): bool
    {
        return strtolower(str_replace(['-', '_', ' '], '', trim($label))) === 'utf8';
    }

    private static function normalizeNormalizationForm(string $form): string
    {
        $key = strtolower(str_replace(['-', '_', ' '], '', trim($form)));

        return match ($key) {
            'nfc', 'c', 'formc', 'composed', 'canonicalcomposition' => 'nfc',
            'nfd', 'd', 'formd', 'decomposed', 'canonicaldecomposition' => 'nfd',
            'nfkc', 'kc', 'formkc', 'compatibilitycomposition' => 'nfkc',
            'nfkd', 'kd', 'formkd', 'compatibilitydecomposition' => 'nfkd',
            default => throw new \InvalidArgumentException("Unsupported Unicode normalization form: {$form}"),
        };
    }

    private static function normalizeNormalizationImplementation(string $implementation): string
    {
        $key = strtolower(str_replace(['-', '_', ' '], '', trim($implementation)));

        return match ($key) {
            '', 'auto', 'native', 'default' => 'auto',
            'intl', 'normalizer' => 'intl',
            'fallback', 'php', 'boundedphp' => 'fallback',
            default => throw new \InvalidArgumentException("Unsupported Unicode normalization implementation: {$implementation}"),
        };
    }

    private static function normalizeWithIntl(string $text, string $form): ?string
    {
        if (!class_exists(\Normalizer::class)) {
            return null;
        }

        $constant = match ($form) {
            'nfc' => \Normalizer::FORM_C,
            'nfd' => \Normalizer::FORM_D,
            'nfkc' => \Normalizer::FORM_KC,
            'nfkd' => \Normalizer::FORM_KD,
        };
        $normalized = \Normalizer::normalize($text, $constant);

        return $normalized === false ? null : $normalized;
    }

    private static function normalizeWithFallback(string $text, string $form): string
    {
        $compatibility = $form === 'nfkc' || $form === 'nfkd';
        $decomposed = self::decomposeFallback($text, $compatibility);
        if ($form === 'nfd' || $form === 'nfkd') {
            return $decomposed;
        }

        return self::composeFallback($decomposed);
    }

    private static function decomposeFallback(string $text, bool $compatibility): string
    {
        $decomposed = '';
        foreach (self::characters($text) as $char) {
            if ($compatibility && isset(self::COMPATIBILITY_DECOMPOSITIONS[$char])) {
                $decomposed .= self::decomposeFallback(self::COMPATIBILITY_DECOMPOSITIONS[$char], true);
                continue;
            }

            $decomposed .= self::CANONICAL_DECOMPOSITIONS[$char] ?? $char;
        }

        return self::orderCanonicalCombiningMarks($decomposed);
    }

    private static function composeFallback(string $text): string
    {
        $out = [];
        $starterIndex = null;
        $lastCombiningClass = 0;
        foreach (self::characters($text) as $char) {
            $combiningClass = self::canonicalCombiningClass(self::codepoint($char));
            if ($starterIndex !== null && $combiningClass > 0) {
                $candidate = $out[$starterIndex] . $char;
                if (isset(self::COMPOSITIONS[$candidate]) && ($lastCombiningClass === 0 || $lastCombiningClass < $combiningClass)) {
                    $out[$starterIndex] = self::COMPOSITIONS[$candidate];
                    continue;
                }
            }

            $out[] = $char;
            if ($combiningClass === 0) {
                $starterIndex = count($out) - 1;
                $lastCombiningClass = 0;
            } else {
                $lastCombiningClass = $combiningClass;
            }
        }

        return implode('', $out);
    }

    private static function orderCanonicalCombiningMarks(string $text): string
    {
        $out = '';
        $starter = '';
        $marks = [];
        foreach (self::characters($text) as $char) {
            $combiningClass = self::canonicalCombiningClass(self::codepoint($char));
            if ($combiningClass === 0) {
                $out .= self::orderedCanonicalCluster($starter, $marks);
                $starter = $char;
                $marks = [];
                continue;
            }

            $marks[] = ['char' => $char, 'class' => $combiningClass, 'order' => count($marks)];
        }

        return $out . self::orderedCanonicalCluster($starter, $marks);
    }

    /**
     * @param list<array{char:string, class:int, order:int}> $marks
     */
    private static function orderedCanonicalCluster(string $starter, array $marks): string
    {
        if ($marks === []) {
            return $starter;
        }

        usort($marks, static fn (array $a, array $b): int => $a['class'] <=> $b['class'] ?: $a['order'] <=> $b['order']);
        $cluster = $starter;
        foreach ($marks as $mark) {
            $cluster .= $mark['char'];
        }

        return $cluster;
    }

    private static function canonicalCombiningClass(int $codepoint): int
    {
        return match ($codepoint) {
            0x0315, 0x031B, 0x0321, 0x0322, 0x0327, 0x0328 => 202,
            0x0316, 0x0317, 0x0318, 0x0319, 0x031C, 0x031D, 0x031E, 0x031F,
            0x0320, 0x0323, 0x0324, 0x0325, 0x0326, 0x0329, 0x032A, 0x032B, 0x032C,
            0x032D, 0x032E, 0x032F, 0x0330, 0x0331, 0x0332, 0x0333, 0x0339,
            0x033A, 0x033B, 0x033C, 0x0345 => 220,
            0x0300, 0x0301, 0x0302, 0x0303, 0x0304, 0x0305, 0x0306, 0x0307,
            0x0308, 0x0309, 0x030A, 0x030B, 0x030C, 0x030D, 0x030E, 0x030F,
            0x0310, 0x0311, 0x0312, 0x0313, 0x0314, 0x033D, 0x033E, 0x033F,
            0x0340, 0x0341, 0x0342, 0x0343, 0x0344, 0x0346, 0x034A, 0x034B,
            0x034C, 0x0350, 0x0351, 0x0352, 0x0357 => 230,
            default => 0,
        };
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function repairUtf8(string $bytes): array
    {
        if ($bytes === '' || preg_match('//u', $bytes) === 1) {
            return [$bytes, 0];
        }

        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length;) {
            $first = ord($bytes[$offset]);
            if ($first <= 0x7f) {
                $out .= $bytes[$offset];
                $offset++;
                continue;
            }

            $sequenceLength = match (true) {
                $first >= 0xc2 && $first <= 0xdf => 2,
                $first >= 0xe0 && $first <= 0xef => 3,
                $first >= 0xf0 && $first <= 0xf4 => 4,
                default => 0,
            };

            if ($sequenceLength === 0 || $offset + $sequenceLength > $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $bytesAt = [];
            for ($index = 0; $index < $sequenceLength; $index++) {
                $bytesAt[] = ord($bytes[$offset + $index]);
            }

            if (!self::isValidUtf8Sequence($bytesAt)) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset += self::hasUtf8ContinuationBytes($bytesAt) ? $sequenceLength : 1;
                continue;
            }

            $out .= substr($bytes, $offset, $sequenceLength);
            $offset += $sequenceLength;
        }

        return [$out, $repairs];
    }

    /**
     * @param list<int> $bytes
     */
    private static function hasUtf8ContinuationBytes(array $bytes): bool
    {
        foreach (array_slice($bytes, 1) as $byte) {
            if ($byte < 0x80 || $byte > 0xbf) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $bytes
     */
    private static function isValidUtf8Sequence(array $bytes): bool
    {
        if (!self::hasUtf8ContinuationBytes($bytes)) {
            return false;
        }

        $first = $bytes[0];
        if (count($bytes) === 3) {
            $second = $bytes[1];
            if ($first === 0xe0 && $second < 0xa0) {
                return false;
            }
            if ($first === 0xed && $second > 0x9f) {
                return false;
            }
        }
        if (count($bytes) === 4) {
            $second = $bytes[1];
            if ($first === 0xf0 && $second < 0x90) {
                return false;
            }
            if ($first === 0xf4 && $second > 0x8f) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeUtf32(string $bytes, bool $littleEndian): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 4) {
            if ($offset + 3 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                break;
            }

            $codepoint = self::u32($bytes, $offset, $littleEndian);
            if ($codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $out .= self::fromCodepoint($codepoint);
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeUtf16(string $bytes, bool $littleEndian): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            if ($offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                break;
            }

            $unit = self::u16($bytes, $offset, $littleEndian);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 3 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $trail = self::u16($bytes, $offset + 2, $littleEndian);
                if ($trail < 0xdc00 || $trail > 0xdfff) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $out .= self::fromCodepoint(0x10000 + (($unit - 0xd800) << 10) + ($trail - 0xdc00));
                $offset += 2;
                continue;
            }

            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $out .= self::fromCodepoint($unit);
        }

        return [$out, $repairs];
    }

    private static function u16(string $bytes, int $offset, bool $littleEndian): int
    {
        $first = ord($bytes[$offset]);
        $second = ord($bytes[$offset + 1]);

        return $littleEndian ? ($first | ($second << 8)) : (($first << 8) | $second);
    }

    private static function u32(string $bytes, int $offset, bool $littleEndian): int
    {
        $first = ord($bytes[$offset]);
        $second = ord($bytes[$offset + 1]);
        $third = ord($bytes[$offset + 2]);
        $fourth = ord($bytes[$offset + 3]);

        if ($littleEndian) {
            return $first | ($second << 8) | ($third << 16) | ($fourth << 24);
        }

        return ($first << 24) | ($second << 16) | ($third << 8) | $fourth;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeSingleByte(string $bytes, string $encoding): array
    {
        $out = '';
        $repairs = 0;
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($encoding === 'windows-1252' && $byte >= 0x80 && $byte <= 0x9f) {
                if (isset(self::WINDOWS_1252_CONTROLS[$byte])) {
                    $out .= self::fromCodepoint(self::WINDOWS_1252_CONTROLS[$byte]);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }
            if ($encoding === 'iso-8859-15' && isset(self::ISO_8859_15_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_15_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'gb1988') {
                if ($byte === 0x24) {
                    $out .= "\u{00A5}";
                    continue;
                }
                if ($byte === 0x7e) {
                    $out .= "\u{203E}";
                    continue;
                }
                if ($byte >= 0xa1 && $byte <= 0xdf) {
                    $out .= self::fromCodepoint(0xff61 + ($byte - 0xa1));
                    continue;
                }
                if ($byte >= 0x80) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
            }
            if ($encoding === 'windows-1250' && isset(self::WINDOWS_1250_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::WINDOWS_1250_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'windows-1251') {
                if ($byte >= 0xc0) {
                    $out .= self::fromCodepoint(0x0410 + ($byte - 0xc0));
                    continue;
                }
                if ($byte >= 0x80) {
                    if (isset(self::WINDOWS_1251_REPLACEMENTS[$byte])) {
                        $out .= self::fromCodepoint(self::WINDOWS_1251_REPLACEMENTS[$byte]);
                    } else {
                        $out .= self::REPLACEMENT;
                        $repairs++;
                    }
                    continue;
                }
            }
            if ($encoding === 'windows-1253' && $byte >= 0x80) {
                if (isset(self::WINDOWS_1253_UNDEFINED[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                if (isset(self::WINDOWS_1253_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::WINDOWS_1253_REPLACEMENTS[$byte]);
                    continue;
                }
                if ($byte >= 0xc0 && isset(self::ISO_8859_7_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::ISO_8859_7_REPLACEMENTS[$byte]);
                    continue;
                }
                if ($byte >= 0xa0) {
                    $out .= self::fromCodepoint($byte);
                    continue;
                }

                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }
            if ($encoding === 'windows-1254' && $byte >= 0x80) {
                if (isset(self::WINDOWS_1254_UNDEFINED[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                if ($byte <= 0x9f) {
                    $out .= self::fromCodepoint(self::WINDOWS_1252_CONTROLS[$byte]);
                    continue;
                }
                if (isset(self::ISO_8859_9_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::ISO_8859_9_REPLACEMENTS[$byte]);
                    continue;
                }

                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($encoding === 'windows-1255' && $byte >= 0x80) {
                if (isset(self::WINDOWS_1255_UNDEFINED[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                if (isset(self::WINDOWS_1255_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::WINDOWS_1255_REPLACEMENTS[$byte]);
                    continue;
                }

                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($encoding === 'windows-1256' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::WINDOWS_1256_REPLACEMENTS[$byte] ?? $byte);
                continue;
            }
            if ($encoding === 'windows-1257' && $byte >= 0x80) {
                if (isset(self::WINDOWS_1257_UNDEFINED[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                if (isset(self::WINDOWS_1257_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::WINDOWS_1257_REPLACEMENTS[$byte]);
                    continue;
                }

                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($encoding === 'windows-1258' && $byte >= 0x80) {
                if (isset(self::WINDOWS_1258_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::WINDOWS_1258_REPLACEMENTS[$byte]);
                    continue;
                }

                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($encoding === 'windows-874' && $byte >= 0x80) {
                if ($byte <= 0x9f) {
                    $out .= self::fromCodepoint(self::WINDOWS_874_CONTROLS[$byte] ?? $byte);
                    continue;
                }
                if (!isset(self::TIS_620_REPLACEMENTS[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $out .= self::fromCodepoint(self::TIS_620_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'cp165') {
                if (isset(self::CP165_OVERRIDES[$byte])) {
                    $out .= self::fromCodepoint(self::CP165_OVERRIDES[$byte]);
                    continue;
                }
                if ($byte >= 0x80) {
                    $out .= self::fromCodepoint(self::IBM864_REPLACEMENTS[$byte]);
                    continue;
                }
            }
            if ($encoding === 'koi8-t' && $byte >= 0x80) {
                if (isset(self::KOI8_T_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::KOI8_T_REPLACEMENTS[$byte]);
                    continue;
                }
                if ($byte >= 0xc0) {
                    $out .= self::fromCodepoint(self::KOI8_R_REPLACEMENTS[$byte]);
                    continue;
                }

                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }
            if (($encoding === 'koi8-r' || $encoding === 'koi8-u' || $encoding === 'koi8-ru') && $byte >= 0x80) {
                if ($encoding === 'koi8-u' && isset(self::KOI8_U_OVERRIDES[$byte])) {
                    $out .= self::fromCodepoint(self::KOI8_U_OVERRIDES[$byte]);
                    continue;
                }
                if ($encoding === 'koi8-ru' && isset(self::KOI8_RU_OVERRIDES[$byte])) {
                    $out .= self::fromCodepoint(self::KOI8_RU_OVERRIDES[$byte]);
                    continue;
                }

                $out .= self::fromCodepoint(self::KOI8_R_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm855' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM855_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm866' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM866_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm869' && $byte >= 0x80) {
                if (isset(self::IBM869_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::IBM869_REPLACEMENTS[$byte]);
                    continue;
                }

                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }
            if ($encoding === 'ibm437' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM437_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm737' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM737_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm775' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM775_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm850' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM850_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm857' && $byte >= 0x80) {
                if (isset(self::IBM857_UNDEFINED[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $out .= self::fromCodepoint(self::IBM857_REPLACEMENTS[$byte] ?? self::IBM850_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm852' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM852_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm860' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM860_REPLACEMENTS[$byte] ?? self::IBM437_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm861' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM861_REPLACEMENTS[$byte] ?? self::IBM437_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm862' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM862_REPLACEMENTS[$byte] ?? self::IBM437_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm863' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM863_REPLACEMENTS[$byte] ?? self::IBM437_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm864' && $byte >= 0x80) {
                if (isset(self::IBM864_UNDEFINED[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $out .= self::fromCodepoint(self::IBM864_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'ibm865' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::IBM865_REPLACEMENTS[$byte] ?? self::IBM850_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-2' && isset(self::ISO_8859_2_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_2_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-3' && $byte >= 0xa0) {
                if (isset(self::ISO_8859_3_UNDEFINED[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                if (isset(self::ISO_8859_3_REPLACEMENTS[$byte])) {
                    $out .= self::fromCodepoint(self::ISO_8859_3_REPLACEMENTS[$byte]);
                    continue;
                }
            }
            if ($encoding === 'iso-8859-4' && isset(self::ISO_8859_4_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_4_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-10' && isset(self::ISO_8859_10_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_10_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-13' && isset(self::ISO_8859_13_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_13_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-14' && isset(self::ISO_8859_14_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_14_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-16' && isset(self::ISO_8859_16_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_16_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-6' && $byte >= 0xa0) {
                if (!isset(self::ISO_8859_6_REPLACEMENTS[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $out .= self::fromCodepoint(self::ISO_8859_6_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-7' && $byte >= 0xa0) {
                if (!isset(self::ISO_8859_7_REPLACEMENTS[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $out .= self::fromCodepoint(self::ISO_8859_7_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-8' && $byte >= 0xa0) {
                if (!isset(self::ISO_8859_8_REPLACEMENTS[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $out .= self::fromCodepoint(self::ISO_8859_8_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-9' && isset(self::ISO_8859_9_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_9_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'tis-620' && $byte >= 0xa0) {
                if (!isset(self::TIS_620_REPLACEMENTS[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $out .= self::fromCodepoint(self::TIS_620_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-5' && $byte >= 0xa1) {
                if ($byte === 0xad) {
                    $out .= "\u{00AD}";
                    continue;
                }
                if ($byte >= 0xb0 && $byte <= 0xef) {
                    $out .= self::fromCodepoint(0x0410 + ($byte - 0xb0));
                    continue;
                }
                $out .= self::fromCodepoint(match ($byte) {
                    0xa1 => 0x0401,
                    0xa2 => 0x0402,
                    0xa3 => 0x0403,
                    0xa4 => 0x0404,
                    0xa5 => 0x0405,
                    0xa6 => 0x0406,
                    0xa7 => 0x0407,
                    0xa8 => 0x0408,
                    0xa9 => 0x0409,
                    0xaa => 0x040a,
                    0xab => 0x040b,
                    0xac => 0x040c,
                    0xae => 0x040e,
                    0xaf => 0x040f,
                    0xf0 => 0x2116,
                    0xf1 => 0x0451,
                    0xf2 => 0x0452,
                    0xf3 => 0x0453,
                    0xf4 => 0x0454,
                    0xf5 => 0x0455,
                    0xf6 => 0x0456,
                    0xf7 => 0x0457,
                    0xf8 => 0x0458,
                    0xf9 => 0x0459,
                    0xfa => 0x045a,
                    0xfb => 0x045b,
                    0xfc => 0x045c,
                    0xfd => 0x00a7,
                    0xfe => 0x045e,
                    0xff => 0x045f,
                });
                continue;
            }
            if ($encoding === 'macintosh' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_ROMAN_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-symbol' && $byte >= 0x20) {
                if (!isset(self::MAC_SYMBOL_REPLACEMENTS[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $out .= self::fromCodepoint(self::MAC_SYMBOL_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-dingbats' && $byte >= 0x20) {
                if (!isset(self::MAC_DINGBATS_REPLACEMENTS[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $out .= self::fromCodepoint(self::MAC_DINGBATS_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-arabic' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_ARABIC_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-croatian' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_CROATIAN_REPLACEMENTS[$byte] ?? self::MAC_ROMAN_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-thai' && $byte >= 0x80) {
                if (!isset(self::MAC_THAI_REPLACEMENTS[$byte])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $out .= self::fromCodepoint(self::MAC_THAI_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-turkish' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_TURKISH_REPLACEMENTS[$byte] ?? self::MAC_ROMAN_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-iceland' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_ICELAND_REPLACEMENTS[$byte] ?? self::MAC_ROMAN_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-romania' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_ROMANIA_REPLACEMENTS[$byte] ?? self::MAC_ROMAN_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-central-europe' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_CENTRAL_EUROPE_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-cyrillic' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_CYRILLIC_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-ukrainian' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_UKRAINE_REPLACEMENTS[$byte] ?? self::MAC_CYRILLIC_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'mac-greek' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_GREEK_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'x-user-defined' && $byte >= 0x80) {
                $out .= self::fromCodepoint(0xf780 + ($byte - 0x80));
                continue;
            }

            $out .= self::fromCodepoint($byte);
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeMacJapanese(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte >= 0xa1 && $byte <= 0xdf) {
                $out .= self::fromCodepoint(0xff61 + $byte - 0xa1);
                continue;
            }
            if (isset(self::MAC_JAPAN_SINGLE_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::MAC_JAPAN_SINGLE_REPLACEMENTS[$byte]);
                continue;
            }
            if (self::isMacJapaneseLeadByte($byte)) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $trail = ord($bytes[$offset + 1]);
                $codepoint = self::macJapaneseCodepoint($byte, $trail);
                if ($codepoint === null) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if (($trail >= 0x40 && $trail <= 0x7e) || ($trail >= 0x80 && $trail <= 0xfc)) {
                        $offset++;
                    }
                    continue;
                }

                $out .= self::fromCodepoint($codepoint);
                $offset++;
                continue;
            }

            $out .= self::REPLACEMENT;
            $repairs++;
        }

        return [$out, $repairs];
    }

    private static function isMacJapaneseLeadByte(int $byte): bool
    {
        return ($byte >= 0x81 && $byte <= 0x9f) || ($byte >= 0xe0 && $byte <= 0xed);
    }

    private static function macJapaneseCodepoint(int $lead, int $trail): ?int
    {
        if ($lead === 0x81) {
            return self::MAC_JAPAN_PUNCTUATION_PAIRS[$trail] ?? null;
        }
        if ($lead === 0x82) {
            if ($trail >= 0x4f && $trail <= 0x58) {
                return 0xff10 + $trail - 0x4f;
            }
            if ($trail >= 0x60 && $trail <= 0x79) {
                return 0xff21 + $trail - 0x60;
            }
            if ($trail >= 0x81 && $trail <= 0x9a) {
                return 0xff41 + $trail - 0x81;
            }
            if ($trail >= 0x9f && $trail <= 0xf1) {
                return 0x3041 + $trail - 0x9f;
            }

            return null;
        }
        if ($lead === 0x83) {
            if ($trail >= 0x40 && $trail <= 0x7e) {
                return 0x30a1 + $trail - 0x40;
            }
            if ($trail >= 0x80 && $trail <= 0x96) {
                return 0x30e0 + $trail - 0x80;
            }
            if ($trail >= 0x9f && $trail <= 0xaf) {
                return 0x0391 + $trail - 0x9f;
            }
            if ($trail >= 0xb0 && $trail <= 0xb6) {
                return 0x03a3 + $trail - 0xb0;
            }
            if ($trail >= 0xbf && $trail <= 0xcf) {
                return 0x03b1 + $trail - 0xbf;
            }
            if ($trail >= 0xd0 && $trail <= 0xd6) {
                return 0x03c3 + $trail - 0xd0;
            }
        }

        return null;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeShiftJis(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f || $byte === 0x80) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte >= 0xa1 && $byte <= 0xdf) {
                $out .= self::fromCodepoint(0xff61 + $byte - 0xa1);
                continue;
            }
            if (($byte >= 0x81 && $byte <= 0x9f) || ($byte >= 0xe0 && $byte <= 0xfc)) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $trail = ord($bytes[$offset + 1]);
                $pointer = self::shiftJisPointer($byte, $trail);
                if ($pointer === null) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($trail > 0x7f) {
                        $offset++;
                    }
                    continue;
                }
                if (!isset(self::JIS0208_POINTERS[$pointer])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset++;
                    continue;
                }

                $out .= self::fromCodepoint(self::JIS0208_POINTERS[$pointer]);
                $offset++;
                continue;
            }

            $out .= self::REPLACEMENT;
            $repairs++;
        }

        return [$out, $repairs];
    }

    private static function shiftJisPointer(int $leading, int $trailing): ?int
    {
        if (!(($trailing >= 0x40 && $trailing <= 0x7e) || ($trailing >= 0x80 && $trailing <= 0xfc))) {
            return null;
        }

        $offset = $trailing < 0x7f ? 0x40 : 0x41;
        $leadingOffset = $leading < 0xa0 ? 0x81 : 0xc1;

        return (($leading - $leadingOffset) * 188) + $trailing - $offset;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeEucJp(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte === 0x8e) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $trail = ord($bytes[$offset + 1]);
                if ($trail < 0xa1 || $trail > 0xdf) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($trail > 0x7f) {
                        $offset++;
                    }
                    continue;
                }

                $out .= self::fromCodepoint(0xff61 + $trail - 0xa1);
                $offset++;
                continue;
            }
            if ($byte === 0x8f) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $lead = ord($bytes[$offset + 1]);
                if ($lead < 0xa1 || $lead > 0xfe) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($lead > 0x7f) {
                        $offset++;
                    }
                    continue;
                }

                if ($offset + 2 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset++;
                    continue;
                }

                $trail = ord($bytes[$offset + 2]);
                if ($trail < 0xa1 || $trail > 0xfe) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset++;
                    if ($trail > 0x7f) {
                        $offset++;
                    }
                    continue;
                }

                $pair = ($lead << 8) | $trail;
                if (!isset(self::JIS0212_PAIRS[$pair])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset += 2;
                    continue;
                }

                $out .= self::fromCodepoint(self::JIS0212_PAIRS[$pair]);
                $offset += 2;
                continue;
            }
            if ($byte >= 0xa1 && $byte <= 0xfe) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $trail = ord($bytes[$offset + 1]);
                if ($trail < 0xa1 || $trail > 0xfe) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($trail > 0x7f) {
                        $offset++;
                    }
                    continue;
                }

                $pointer = (($byte - 0xa1) * 94) + $trail - 0xa1;
                if (!isset(self::JIS0208_POINTERS[$pointer])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset++;
                    continue;
                }

                $out .= self::fromCodepoint(self::JIS0208_POINTERS[$pointer]);
                $offset++;
                continue;
            }

            $out .= self::REPLACEMENT;
            $repairs++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeIso2022Jp(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $state = 'ascii';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte === 0x1b) {
                $escape = self::iso2022JpEscapeState($bytes, $offset);
                if ($escape === null) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset += min(2, $length - $offset - 1);
                    $state = 'ascii';
                    continue;
                }

                $state = $escape['state'];
                $offset += $escape['length'] - 1;
                continue;
            }

            if ($state === 'ascii') {
                if ($byte <= 0x7f) {
                    $out .= self::fromCodepoint($byte);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            if ($state === 'roman') {
                if ($byte <= 0x7f) {
                    $out .= match ($byte) {
                        0x5c => "\u{00A5}",
                        0x7e => "\u{203E}",
                        default => self::fromCodepoint($byte),
                    };
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            if ($state === 'katakana') {
                if ($byte >= 0x21 && $byte <= 0x5f) {
                    $out .= self::fromCodepoint(0xff61 + $byte - 0x21);
                } elseif ($byte <= 0x20) {
                    $out .= self::fromCodepoint($byte);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($byte <= 0x7f) {
                        $state = 'ascii';
                    }
                }
                continue;
            }

            if ($byte <= 0x20) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte < 0x21 || $byte > 0x7e || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail < 0x21 || $trail > 0x7e) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                } else {
                    $state = 'ascii';
                }
                continue;
            }

            if ($state === 'jis0212') {
                $pair = (($byte + 0x80) << 8) | ($trail + 0x80);
                if (!isset(self::JIS0212_PAIRS[$pair])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset++;
                    continue;
                }

                $out .= self::fromCodepoint(self::JIS0212_PAIRS[$pair]);
                $offset++;
                continue;
            }

            $pointer = (($byte - 0x21) * 94) + $trail - 0x21;
            if (!isset(self::JIS0208_POINTERS[$pointer])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::JIS0208_POINTERS[$pointer]);
            $offset++;
        }

        if ($state !== 'ascii') {
            $out .= self::REPLACEMENT;
            $repairs++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeBig5(string $bytes, bool $useCp950 = false): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte < 0x81 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if (!(($trail >= 0x40 && $trail <= 0x7e) || ($trail >= 0xa1 && $trail <= 0xfe))) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = ($byte << 8) | $trail;
            if ($useCp950 && isset(self::CP950_PAIRS[$pair])) {
                $out .= self::fromCodepoint(self::CP950_PAIRS[$pair]);
                $offset++;
                continue;
            }

            if (isset(self::BIG5_PAIR_SEQUENCES[$pair])) {
                $out .= self::BIG5_PAIR_SEQUENCES[$pair];
                $offset++;
                continue;
            }

            if (!isset(self::BIG5_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::BIG5_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeEucTw(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte === 0x8e) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $plane = ord($bytes[$offset + 1]);
                if ($plane < 0xa1 || $plane > 0xb0) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($plane > 0x7f) {
                        $offset++;
                    }
                    continue;
                }

                if ($offset + 3 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset = $length;
                    continue;
                }

                $lead = ord($bytes[$offset + 2]);
                $trail = ord($bytes[$offset + 3]);
                if ($lead < 0xa1 || $lead > 0xfe || $trail < 0xa1 || $trail > 0xfe) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset++;
                    if ($lead > 0x7f) {
                        $offset++;
                    }
                    if ($trail > 0x7f) {
                        $offset++;
                    }
                    continue;
                }

                $out .= self::REPLACEMENT;
                $repairs++;
                $offset += 3;
                continue;
            }

            if ($byte < 0xa1 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail < 0xa1 || $trail > 0xfe) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = ($byte << 8) | $trail;
            if (!isset(self::CNS11643_PLANE1_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::CNS11643_PLANE1_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeGbk(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte < 0x81 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if (!(($trail >= 0x40 && $trail <= 0x7e) || ($trail >= 0x80 && $trail <= 0xfe))) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = ($byte << 8) | $trail;
            if (!isset(self::GBK_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::GBK_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeGb12345(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte < 0xa1 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail < 0xa1 || $trail > 0xfe) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $pair = ($byte << 8) | $trail;
            if (!isset(self::GB12345_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::GB12345_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeGb18030(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte < 0x81 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $second = ord($bytes[$offset + 1]);
            if ($second >= 0x30 && $second <= 0x39) {
                if ($offset + 3 < $length) {
                    $third = ord($bytes[$offset + 2]);
                    $fourth = ord($bytes[$offset + 3]);
                    if ($third >= 0x81 && $third <= 0xfe && $fourth >= 0x30 && $fourth <= 0x39) {
                        $pointer = self::gb18030Pointer($byte, $second, $third, $fourth);
                        $codepoint = self::gb18030RangeCodepoint($pointer);
                        if ($codepoint !== null) {
                            $out .= self::fromCodepoint($codepoint);
                        } else {
                            $out .= self::REPLACEMENT;
                            $repairs++;
                        }
                        $offset += 3;
                        continue;
                    }
                }

                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            if (!(($second >= 0x40 && $second <= 0x7e) || ($second >= 0x80 && $second <= 0xfe))) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($second > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = ($byte << 8) | $second;
            if (!isset(self::GBK_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::GBK_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    private static function gb18030Pointer(int $first, int $second, int $third, int $fourth): int
    {
        return (($first - 0x81) * 10 * 126 * 10)
            + (($second - 0x30) * 126 * 10)
            + (($third - 0x81) * 10)
            + ($fourth - 0x30);
    }

    private static function gb18030RangeCodepoint(int $pointer): ?int
    {
        if (($pointer > 39419 && $pointer < 189000) || $pointer > 1237575) {
            return null;
        }
        if ($pointer === 7457) {
            return 0xe7c7;
        }

        $rangePointer = null;
        $rangeCodepoint = null;
        foreach (self::GB18030_RANGES as [$candidatePointer, $candidateCodepoint]) {
            if ($candidatePointer > $pointer) {
                break;
            }

            $rangePointer = $candidatePointer;
            $rangeCodepoint = $candidateCodepoint;
        }
        if ($rangePointer === null || $rangeCodepoint === null) {
            return null;
        }

        $codepoint = $rangeCodepoint + $pointer - $rangePointer;
        if ($codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
            return null;
        }

        return $codepoint;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeEucKr(string $bytes, bool $allowWindows949Extensions = false): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte < 0x81 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail < 0x41 || $trail === 0x7f || $trail > 0xfe) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = ($byte << 8) | $trail;
            $codepoint = self::EUC_KR_PAIRS[$pair] ?? (
                $allowWindows949Extensions ? (self::WINDOWS_949_EXTENSION_PAIRS[$pair] ?? null) : null
            );
            if ($codepoint === null) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint($codepoint);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeIso2022Kr(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $state = 'ascii';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte === 0x1b) {
                if (
                    $offset + 3 < $length
                    && ord($bytes[$offset + 1]) === 0x24
                    && ord($bytes[$offset + 2]) === 0x29
                    && ord($bytes[$offset + 3]) === 0x43
                ) {
                    $state = 'ascii';
                    $offset += 3;
                    continue;
                }

                $out .= self::REPLACEMENT;
                $repairs++;
                $offset += min(2, $length - $offset - 1);
                $state = 'ascii';
                continue;
            }

            if ($byte === 0x0e) {
                $state = 'ksx1001';
                continue;
            }
            if ($byte === 0x0f) {
                $state = 'ascii';
                continue;
            }

            if ($state === 'ascii') {
                if ($byte <= 0x7f) {
                    $out .= self::fromCodepoint($byte);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            if ($byte <= 0x20) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte < 0x21 || $byte > 0x7e || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail < 0x21 || $trail > 0x7e) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = (($byte + 0x80) << 8) | ($trail + 0x80);
            if (!isset(self::EUC_KR_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::EUC_KR_PAIRS[$pair]);
            $offset++;
        }

        if ($state !== 'ascii') {
            $out .= self::REPLACEMENT;
            $repairs++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeIso2022Cn(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $state = 'ascii';
        $soDesignation = null;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte === 0x1b) {
                if ($offset + 3 < $length && ord($bytes[$offset + 1]) === 0x24) {
                    $kind = ord($bytes[$offset + 2]);
                    $final = ord($bytes[$offset + 3]);
                    if ($kind === 0x29 && $final === 0x41) {
                        $soDesignation = 'gb2312';
                        $offset += 3;
                        continue;
                    }
                    if ($kind === 0x29 && $final === 0x47) {
                        $soDesignation = 'unsupported';
                        $out .= self::REPLACEMENT;
                        $repairs++;
                        $offset += 3;
                        continue;
                    }
                    if (($kind === 0x2a || $kind === 0x2b) && $final >= 0x40 && $final <= 0x7e) {
                        $out .= self::REPLACEMENT;
                        $repairs++;
                        $offset += 3;
                        continue;
                    }
                }
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset += min(2, $length - $offset - 1);
                $state = 'ascii';
                continue;
            }

            if ($byte === 0x0e) {
                if ($soDesignation === 'gb2312') {
                    $state = 'gb2312';
                    continue;
                }

                $state = 'unsupported';
                continue;
            }
            if ($byte === 0x0f) {
                $state = 'ascii';
                continue;
            }

            if ($state === 'ascii') {
                if ($byte <= 0x7f) {
                    $out .= self::fromCodepoint($byte);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            if ($byte <= 0x20) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte < 0x21 || $byte > 0x7e || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail < 0x21 || $trail > 0x7e) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            if ($state !== 'gb2312') {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $pair = (($byte + 0x80) << 8) | ($trail + 0x80);
            if (!isset(self::GBK_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::GBK_PAIRS[$pair]);
            $offset++;
        }

        if ($state !== 'ascii') {
            $out .= self::REPLACEMENT;
            $repairs++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeHzGb2312(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $state = 'ascii';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte === 0x7e) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $next = ord($bytes[$offset + 1]);
                if ($next === 0x7b) {
                    $state = 'gb';
                    $offset++;
                    continue;
                }
                if ($next === 0x7d) {
                    $state = 'ascii';
                    $offset++;
                    continue;
                }
                if ($next === 0x7e) {
                    $out .= '~';
                    $offset++;
                    continue;
                }
                if ($next === 0x0a) {
                    $offset++;
                    continue;
                }
                if ($next === 0x0d) {
                    if ($offset + 2 < $length && ord($bytes[$offset + 2]) === 0x0a) {
                        $offset += 2;
                        continue;
                    }
                    $offset++;
                    continue;
                }

                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            if ($state === 'ascii') {
                if ($byte <= 0x7f) {
                    $out .= self::fromCodepoint($byte);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            if ($byte <= 0x20) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte < 0x21 || $byte > 0x7e || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail === 0x7e && $offset + 2 < $length && ord($bytes[$offset + 2]) === 0x7d) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }
            if ($trail < 0x21 || $trail > 0x7e) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $pair = (($byte + 0x80) << 8) | ($trail + 0x80);
            if (!isset(self::GBK_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::GBK_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{state:string, length:int}|null
     */
    private static function iso2022JpEscapeState(string $bytes, int $offset): ?array
    {
        if ($offset + 2 >= strlen($bytes)) {
            return null;
        }

        $first = ord($bytes[$offset + 1]);
        $second = ord($bytes[$offset + 2]);

        if ($first === 0x24 && ($second === 0x40 || $second === 0x42)) {
            return ['state' => 'jis0208', 'length' => 3];
        }
        if ($first === 0x24 && $second === 0x28) {
            if ($offset + 3 >= strlen($bytes)) {
                return null;
            }

            return ord($bytes[$offset + 3]) === 0x44
                ? ['state' => 'jis0212', 'length' => 4]
                : null;
        }
        if ($first !== 0x28) {
            return null;
        }

        return match ($second) {
            0x42 => ['state' => 'ascii', 'length' => 3],
            0x49 => ['state' => 'katakana', 'length' => 3],
            0x4a => ['state' => 'roman', 'length' => 3],
            default => null,
        };
    }

    private static function fromCodepoint(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
            return self::REPLACEMENT;
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

    private static function codepoint(string $char): int
    {
        $bytes = array_values(unpack('C*', $char) ?: []);
        $first = $bytes[0] ?? 0;
        if ($first <= 0x7f) {
            return $first;
        }
        if ($first >= 0xc2 && $first <= 0xdf && isset($bytes[1])) {
            return (($first & 0x1f) << 6) | ($bytes[1] & 0x3f);
        }
        if ($first >= 0xe0 && $first <= 0xef && isset($bytes[2])) {
            return (($first & 0x0f) << 12) | (($bytes[1] & 0x3f) << 6) | ($bytes[2] & 0x3f);
        }
        if ($first >= 0xf0 && $first <= 0xf4 && isset($bytes[3])) {
            return (($first & 0x07) << 18) | (($bytes[1] & 0x3f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);
        }

        return 0xfffd;
    }

    private static function codepointLabel(int $codepoint): string
    {
        return sprintf('U+%04X', $codepoint);
    }

    private static function ambiguousWidthColumns(string $ambiguousWidth): int
    {
        $key = strtolower(str_replace(['-', '_', ' '], '', trim($ambiguousWidth)));

        return match ($key) {
            'narrow', 'na', 'neutral', 'single', 'singlecolumn', '1' => 1,
            'wide', 'w', 'cjk', 'eastasian', 'eastasianwide', 'double', 'doublecolumn', '2' => 2,
            default => throw new \InvalidArgumentException("Unsupported East Asian ambiguous-width policy: {$ambiguousWidth}"),
        };
    }

    private static function codepointDisplayWidth(int $codepoint, int $ambiguousColumns): int
    {
        if ($codepoint === 0 || $codepoint < 32 || ($codepoint >= 0x7f && $codepoint < 0xa0)) {
            return 0;
        }
        if ($codepoint === 0x2028 || $codepoint === 0x2029) {
            return 0;
        }
        if (self::isEmojiSkinToneModifier($codepoint)) {
            return 2;
        }
        if (self::isCombiningOrZeroWidth($codepoint)) {
            return 0;
        }
        if (self::isWideCodepoint($codepoint)) {
            return 2;
        }
        if (self::isAmbiguousWidthCodepoint($codepoint)) {
            return $ambiguousColumns;
        }

        return 1;
    }

    private static function graphemeDisplayWidth(string $cluster, int $ambiguousColumns): int
    {
        $width = 0;
        $hasJoiner = false;
        $hasWide = false;
        $hasEmojiVariation = false;
        $hasEmojiVariationBase = false;
        $hasEmojiSkinToneModifier = false;
        $hasEmojiModifierBase = false;
        $hasKeycap = false;
        $hasKeycapBase = false;
        foreach (self::characters($cluster) as $char) {
            $codepoint = self::codepoint($char);
            $charWidth = self::codepointDisplayWidth($codepoint, $ambiguousColumns);
            $width += $charWidth;
            $hasJoiner = $hasJoiner || $codepoint === 0x200d;
            $hasWide = $hasWide || $charWidth === 2;
            $hasEmojiVariation = $hasEmojiVariation || self::isEmojiVariationSelector($codepoint);
            $hasEmojiVariationBase = $hasEmojiVariationBase || self::isEmojiVariationBase($codepoint);
            $hasEmojiSkinToneModifier = $hasEmojiSkinToneModifier || self::isEmojiSkinToneModifier($codepoint);
            $hasEmojiModifierBase = $hasEmojiModifierBase || self::isEmojiModifierBase($codepoint);
            $hasKeycap = $hasKeycap || $codepoint === 0x20e3;
            $hasKeycapBase = $hasKeycapBase || self::isKeycapBase($codepoint);
        }

        if ($hasKeycap && $hasKeycapBase) {
            return 2;
        }
        if ($hasEmojiSkinToneModifier && $hasEmojiModifierBase) {
            return 2;
        }
        if ($hasJoiner && ($hasWide || $hasEmojiVariation || $hasEmojiVariationBase)) {
            return 2;
        }
        if ($hasEmojiVariation && $hasEmojiVariationBase) {
            return max(2, $width);
        }
        if (self::isBoundedIndicViramaCluster($cluster)) {
            return 1;
        }

        return $width;
    }

    private static function graphemeDisplayWidthAtColumn(string $cluster, int $ambiguousColumns, int $column): int
    {
        if ($cluster === "\t") {
            return self::tabDisplayAdvance($column);
        }

        return self::graphemeDisplayWidth($cluster, $ambiguousColumns);
    }

    private static function tabDisplayAdvance(int $column): int
    {
        $remainder = $column % self::TAB_STOP_COLUMNS;

        return $remainder === 0 ? self::TAB_STOP_COLUMNS : self::TAB_STOP_COLUMNS - $remainder;
    }

    private static function isCombiningOrZeroWidth(int $codepoint): bool
    {
        return self::isUnicodeCombiningMark($codepoint)
            || self::isUnicodeFormatControl($codepoint)
            || self::isBoundedIndicVirama($codepoint)
            || ($codepoint >= 0x0300 && $codepoint <= 0x036f)
            || $codepoint === 0x00ad
            || ($codepoint >= 0x0483 && $codepoint <= 0x0489)
            || ($codepoint >= 0x0591 && $codepoint <= 0x05bd)
            || $codepoint === 0x05bf
            || ($codepoint >= 0x05c1 && $codepoint <= 0x05c2)
            || ($codepoint >= 0x05c4 && $codepoint <= 0x05c5)
            || $codepoint === 0x05c7
            || ($codepoint >= 0x0610 && $codepoint <= 0x061a)
            || ($codepoint >= 0x064b && $codepoint <= 0x065f)
            || $codepoint === 0x0670
            || ($codepoint >= 0x06d6 && $codepoint <= 0x06dc)
            || ($codepoint >= 0x06df && $codepoint <= 0x06e4)
            || ($codepoint >= 0x06e7 && $codepoint <= 0x06e8)
            || ($codepoint >= 0x06ea && $codepoint <= 0x06ed)
            || ($codepoint >= 0x0711 && $codepoint <= 0x0711)
            || ($codepoint >= 0x0730 && $codepoint <= 0x074a)
            || ($codepoint >= 0x07a6 && $codepoint <= 0x07b0)
            || ($codepoint >= 0x07eb && $codepoint <= 0x07f3)
            || ($codepoint >= 0x0816 && $codepoint <= 0x0819)
            || ($codepoint >= 0x081b && $codepoint <= 0x0823)
            || ($codepoint >= 0x0825 && $codepoint <= 0x0827)
            || ($codepoint >= 0x0829 && $codepoint <= 0x082d)
            || ($codepoint >= 0x0859 && $codepoint <= 0x085b)
            || ($codepoint >= 0x08d3 && $codepoint <= 0x08ff)
            || ($codepoint >= 0x0900 && $codepoint <= 0x0902)
            || $codepoint === 0x093a
            || $codepoint === 0x093c
            || ($codepoint >= 0x0941 && $codepoint <= 0x0948)
            || $codepoint === 0x094d
            || ($codepoint >= 0x0951 && $codepoint <= 0x0957)
            || ($codepoint >= 0x0962 && $codepoint <= 0x0963)
            || ($codepoint >= 0x0981 && $codepoint <= 0x0981)
            || $codepoint === 0x09bc
            || ($codepoint >= 0x09c1 && $codepoint <= 0x09c4)
            || $codepoint === 0x09cd
            || ($codepoint >= 0x09e2 && $codepoint <= 0x09e3)
            || ($codepoint >= 0x0e31 && $codepoint <= 0x0e31)
            || ($codepoint >= 0x0e34 && $codepoint <= 0x0e3a)
            || ($codepoint >= 0x0e47 && $codepoint <= 0x0e4e)
            || ($codepoint >= 0x1160 && $codepoint <= 0x11ff)
            || ($codepoint >= 0x1ab0 && $codepoint <= 0x1aff)
            || ($codepoint >= 0x1dc0 && $codepoint <= 0x1dff)
            || ($codepoint >= 0x200b && $codepoint <= 0x200f)
            || ($codepoint >= 0x202a && $codepoint <= 0x202e)
            || ($codepoint >= 0x2060 && $codepoint <= 0x206f)
            || ($codepoint >= 0x20d0 && $codepoint <= 0x20ff)
            || ($codepoint >= 0xd7b0 && $codepoint <= 0xd7c6)
            || ($codepoint >= 0xd7cb && $codepoint <= 0xd7fb)
            || ($codepoint >= 0xe0020 && $codepoint <= 0xe007f)
            || ($codepoint >= 0xfe00 && $codepoint <= 0xfe0f)
            || ($codepoint >= 0xfe20 && $codepoint <= 0xfe2f)
            || $codepoint === 0xfeff
            || ($codepoint >= 0xe0100 && $codepoint <= 0xe01ef);
    }

    private static function isUnicodeCombiningMark(int $codepoint): bool
    {
        if (class_exists(\IntlChar::class)) {
            $category = \IntlChar::charType($codepoint);

            return $category === \IntlChar::CHAR_CATEGORY_NON_SPACING_MARK
                || $category === \IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK
                || $category === \IntlChar::CHAR_CATEGORY_ENCLOSING_MARK;
        }

        return self::isBoundedIndicSpacingMark($codepoint);
    }

    private static function isBoundedGraphemeSpacingMark(int $codepoint): bool
    {
        return $codepoint === 0x0e33
            || $codepoint === 0x0eb3;
    }

    private static function isBoundedIndicViramaCluster(string $cluster): bool
    {
        $hasVirama = false;
        $consonants = 0;
        foreach (self::characters($cluster) as $char) {
            $codepoint = self::codepoint($char);
            if (self::isBoundedIndicConsonant($codepoint)) {
                $consonants++;
            }
            if (self::isBoundedIndicVirama($codepoint)) {
                $hasVirama = true;
            }
        }

        return $hasVirama && $consonants >= 2;
    }

    private static function isBoundedIndicVirama(int $codepoint): bool
    {
        return $codepoint === 0x094d
            || $codepoint === 0x09cd
            || $codepoint === 0x0a4d
            || $codepoint === 0x0acd
            || $codepoint === 0x0b4d
            || $codepoint === 0x0bcd
            || $codepoint === 0x0c4d
            || $codepoint === 0x0ccd
            || $codepoint === 0x0d4d
            || $codepoint === 0x0dca
            || $codepoint === 0x1b44
            || $codepoint === 0x1baa
            || $codepoint === 0x1039
            || $codepoint === 0x17d2
            || $codepoint === 0xa9c0;
    }

    private static function isBoundedIndicConsonant(int $codepoint): bool
    {
        return ($codepoint >= 0x0915 && $codepoint <= 0x0939)
            || ($codepoint >= 0x0958 && $codepoint <= 0x095f)
            || ($codepoint >= 0x0995 && $codepoint <= 0x09b9)
            || ($codepoint >= 0x0a15 && $codepoint <= 0x0a39)
            || ($codepoint >= 0x0a59 && $codepoint <= 0x0a5e)
            || ($codepoint >= 0x0a95 && $codepoint <= 0x0ab9)
            || ($codepoint >= 0x0b15 && $codepoint <= 0x0b39)
            || ($codepoint >= 0x0b5c && $codepoint <= 0x0b5f)
            || ($codepoint >= 0x0b95 && $codepoint <= 0x0bb9)
            || ($codepoint >= 0x0c15 && $codepoint <= 0x0c39)
            || ($codepoint >= 0x0c95 && $codepoint <= 0x0cb9)
            || ($codepoint >= 0x0d15 && $codepoint <= 0x0d39)
            || ($codepoint >= 0x0d9a && $codepoint <= 0x0dc6)
            || ($codepoint >= 0x1b13 && $codepoint <= 0x1b33)
            || ($codepoint >= 0x1b45 && $codepoint <= 0x1b4c)
            || ($codepoint >= 0x1b8a && $codepoint <= 0x1ba0)
            || ($codepoint >= 0x1000 && $codepoint <= 0x102a)
            || ($codepoint >= 0x1780 && $codepoint <= 0x17a2)
            || ($codepoint >= 0xa98f && $codepoint <= 0xa9b2);
    }

    private static function isUnicodeFormatControl(int $codepoint): bool
    {
        if (class_exists(\IntlChar::class)) {
            return \IntlChar::charType($codepoint) === \IntlChar::CHAR_CATEGORY_FORMAT_CHAR;
        }

        return self::isBoundedZeroWidthFormatControl($codepoint);
    }

    private static function isBoundedPrependedFormatControl(int $codepoint): bool
    {
        return ($codepoint >= 0x0600 && $codepoint <= 0x0605)
            || $codepoint === 0x06dd
            || $codepoint === 0x070f
            || ($codepoint >= 0x0890 && $codepoint <= 0x0891)
            || $codepoint === 0x08e2
            || $codepoint === 0x110bd
            || $codepoint === 0x110cd
            || ($codepoint >= 0x13430 && $codepoint <= 0x1343f);
    }

    private static function isBoundedZeroWidthFormatControl(int $codepoint): bool
    {
        return ($codepoint >= 0x0600 && $codepoint <= 0x0605)
            || $codepoint === 0x061c
            || $codepoint === 0x06dd
            || $codepoint === 0x070f
            || ($codepoint >= 0x0890 && $codepoint <= 0x0891)
            || $codepoint === 0x08e2
            || $codepoint === 0x180e
            || ($codepoint >= 0x200b && $codepoint <= 0x200f)
            || ($codepoint >= 0x202a && $codepoint <= 0x202e)
            || ($codepoint >= 0x2060 && $codepoint <= 0x206f)
            || $codepoint === 0xfeff
            || $codepoint === 0x110bd
            || $codepoint === 0x110cd
            || ($codepoint >= 0x13430 && $codepoint <= 0x1343f);
    }

    private static function isBoundedIndicSpacingMark(int $codepoint): bool
    {
        return $codepoint === 0x0903
            || $codepoint === 0x093b
            || ($codepoint >= 0x093e && $codepoint <= 0x0940)
            || ($codepoint >= 0x0949 && $codepoint <= 0x094c)
            || ($codepoint >= 0x094e && $codepoint <= 0x094f)
            || ($codepoint >= 0x0982 && $codepoint <= 0x0983)
            || ($codepoint >= 0x09be && $codepoint <= 0x09c0)
            || ($codepoint >= 0x09c7 && $codepoint <= 0x09c8)
            || ($codepoint >= 0x09cb && $codepoint <= 0x09cc)
            || $codepoint === 0x09d7
            || $codepoint === 0x0a03
            || ($codepoint >= 0x0a3e && $codepoint <= 0x0a40)
            || ($codepoint >= 0x0a83 && $codepoint <= 0x0a83)
            || ($codepoint >= 0x0abe && $codepoint <= 0x0ac0)
            || $codepoint === 0x0ac9
            || ($codepoint >= 0x0acb && $codepoint <= 0x0acc)
            || ($codepoint >= 0x0b02 && $codepoint <= 0x0b03)
            || $codepoint === 0x0b3e
            || $codepoint === 0x0b40
            || ($codepoint >= 0x0b47 && $codepoint <= 0x0b48)
            || ($codepoint >= 0x0b4b && $codepoint <= 0x0b4c)
            || $codepoint === 0x0b57
            || ($codepoint >= 0x0bbe && $codepoint <= 0x0bbf)
            || ($codepoint >= 0x0bc1 && $codepoint <= 0x0bc2)
            || ($codepoint >= 0x0bc6 && $codepoint <= 0x0bc8)
            || ($codepoint >= 0x0bca && $codepoint <= 0x0bcc)
            || $codepoint === 0x0bd7
            || ($codepoint >= 0x0c01 && $codepoint <= 0x0c03)
            || ($codepoint >= 0x0c3e && $codepoint <= 0x0c44)
            || ($codepoint >= 0x0c46 && $codepoint <= 0x0c48)
            || ($codepoint >= 0x0c4a && $codepoint <= 0x0c4d)
            || ($codepoint >= 0x0c55 && $codepoint <= 0x0c56)
            || ($codepoint >= 0x0c62 && $codepoint <= 0x0c63)
            || ($codepoint >= 0x0c82 && $codepoint <= 0x0c83)
            || $codepoint === 0x0cbc
            || $codepoint === 0x0cbe
            || ($codepoint >= 0x0cc0 && $codepoint <= 0x0cc4)
            || ($codepoint >= 0x0cc6 && $codepoint <= 0x0cc8)
            || ($codepoint >= 0x0cca && $codepoint <= 0x0ccd)
            || ($codepoint >= 0x0cd5 && $codepoint <= 0x0cd6)
            || ($codepoint >= 0x0ce2 && $codepoint <= 0x0ce3)
            || ($codepoint >= 0x0d02 && $codepoint <= 0x0d03)
            || ($codepoint >= 0x0d3e && $codepoint <= 0x0d44)
            || ($codepoint >= 0x0d46 && $codepoint <= 0x0d48)
            || ($codepoint >= 0x0d4a && $codepoint <= 0x0d4d)
            || $codepoint === 0x0d57
            || ($codepoint >= 0x0d62 && $codepoint <= 0x0d63)
            || ($codepoint >= 0x0d82 && $codepoint <= 0x0d83)
            || $codepoint === 0x0dca
            || ($codepoint >= 0x0dcf && $codepoint <= 0x0dd4)
            || $codepoint === 0x0dd6
            || ($codepoint >= 0x0dd8 && $codepoint <= 0x0ddf)
            || $codepoint === 0x0eb1
            || ($codepoint >= 0x0eb4 && $codepoint <= 0x0ebc)
            || ($codepoint >= 0x0ec8 && $codepoint <= 0x0ecd);
    }

    private static function isAmbiguousWidthCodepoint(int $codepoint): bool
    {
        if (in_array($codepoint, self::EAST_ASIAN_AMBIGUOUS_SINGLE_CODEPOINTS, true)) {
            return true;
        }

        foreach (self::EAST_ASIAN_AMBIGUOUS_RANGES as [$start, $end]) {
            if ($codepoint >= $start && $codepoint <= $end) {
                return true;
            }
        }

        return false;
    }

    private static function isWideCodepoint(int $codepoint): bool
    {
        return ($codepoint >= 0x1100 && $codepoint <= 0x115f)
            || ($codepoint >= 0x231a && $codepoint <= 0x231b)
            || $codepoint === 0x2329
            || $codepoint === 0x232a
            || ($codepoint >= 0x23e9 && $codepoint <= 0x23ec)
            || $codepoint === 0x23f0
            || $codepoint === 0x23f3
            || ($codepoint >= 0x25fd && $codepoint <= 0x25fe)
            || ($codepoint >= 0x2614 && $codepoint <= 0x2615)
            || ($codepoint >= 0x2630 && $codepoint <= 0x2637)
            || ($codepoint >= 0x2648 && $codepoint <= 0x2653)
            || $codepoint === 0x267f
            || ($codepoint >= 0x268a && $codepoint <= 0x268f)
            || $codepoint === 0x2693
            || $codepoint === 0x26a1
            || ($codepoint >= 0x26aa && $codepoint <= 0x26ab)
            || ($codepoint >= 0x26bd && $codepoint <= 0x26be)
            || ($codepoint >= 0x26c4 && $codepoint <= 0x26c5)
            || $codepoint === 0x26ce
            || $codepoint === 0x26d4
            || $codepoint === 0x26ea
            || ($codepoint >= 0x26f2 && $codepoint <= 0x26f3)
            || $codepoint === 0x26f5
            || $codepoint === 0x26fa
            || $codepoint === 0x26fd
            || $codepoint === 0x2705
            || ($codepoint >= 0x270a && $codepoint <= 0x270b)
            || $codepoint === 0x2728
            || $codepoint === 0x274c
            || $codepoint === 0x274e
            || ($codepoint >= 0x2753 && $codepoint <= 0x2755)
            || $codepoint === 0x2757
            || ($codepoint >= 0x2795 && $codepoint <= 0x2797)
            || $codepoint === 0x27b0
            || $codepoint === 0x27bf
            || (
                $codepoint >= 0x2e80
                && $codepoint <= 0xa4cf
                && $codepoint !== 0x303f
                && ($codepoint < 0x4dc0 || $codepoint > 0x4dff)
            )
            || ($codepoint >= 0x2b1b && $codepoint <= 0x2b1c)
            || $codepoint === 0x2b50
            || $codepoint === 0x2b55
            || ($codepoint >= 0xa960 && $codepoint <= 0xa97c)
            || ($codepoint >= 0xac00 && $codepoint <= 0xd7a3)
            || ($codepoint >= 0xf900 && $codepoint <= 0xfaff)
            || ($codepoint >= 0xfe10 && $codepoint <= 0xfe19)
            || ($codepoint >= 0xfe30 && $codepoint <= 0xfe6f)
            || ($codepoint >= 0xff00 && $codepoint <= 0xff60)
            || ($codepoint >= 0xffe0 && $codepoint <= 0xffe6)
            || ($codepoint >= 0x16fe0 && $codepoint <= 0x16fe4)
            || ($codepoint >= 0x16ff0 && $codepoint <= 0x16ff1)
            || ($codepoint >= 0x1aff0 && $codepoint <= 0x1afff)
            || ($codepoint >= 0x17000 && $codepoint <= 0x187f7)
            || ($codepoint >= 0x18800 && $codepoint <= 0x18aff)
            || ($codepoint >= 0x18b00 && $codepoint <= 0x18cff)
            || ($codepoint >= 0x18d00 && $codepoint <= 0x18d8f)
            || ($codepoint >= 0x1b000 && $codepoint <= 0x1b122)
            || $codepoint === 0x1b132
            || ($codepoint >= 0x1b150 && $codepoint <= 0x1b152)
            || $codepoint === 0x1b155
            || ($codepoint >= 0x1b164 && $codepoint <= 0x1b167)
            || ($codepoint >= 0x1b170 && $codepoint <= 0x1b2fb)
            || ($codepoint >= 0x1d300 && $codepoint <= 0x1d356)
            || ($codepoint >= 0x1d360 && $codepoint <= 0x1d376)
            || $codepoint === 0x1f004
            || $codepoint === 0x1f0cf
            || $codepoint === 0x1f18e
            || ($codepoint >= 0x1f191 && $codepoint <= 0x1f19a)
            || ($codepoint >= 0x1f200 && $codepoint <= 0x1f202)
            || ($codepoint >= 0x1f210 && $codepoint <= 0x1f23b)
            || ($codepoint >= 0x1f240 && $codepoint <= 0x1f248)
            || ($codepoint >= 0x1f250 && $codepoint <= 0x1f251)
            || ($codepoint >= 0x1f260 && $codepoint <= 0x1f265)
            || ($codepoint >= 0x1f300 && $codepoint <= 0x1f64f)
            || ($codepoint >= 0x1f680 && $codepoint <= 0x1f6ff)
            || ($codepoint >= 0x1f7e0 && $codepoint <= 0x1f7eb)
            || $codepoint === 0x1f7f0
            || ($codepoint >= 0x1fa70 && $codepoint <= 0x1faff)
            || ($codepoint >= 0x1f900 && $codepoint <= 0x1f9ff)
            || ($codepoint >= 0x20000 && $codepoint <= 0x3fffd);
    }

    private static function isRegionalIndicator(int $codepoint): bool
    {
        return $codepoint >= 0x1f1e6 && $codepoint <= 0x1f1ff;
    }

    private static function isKeycapBase(int $codepoint): bool
    {
        return ($codepoint >= 0x30 && $codepoint <= 0x39)
            || $codepoint === 0x23
            || $codepoint === 0x2a;
    }

    private static function isEmojiVariationSelector(int $codepoint): bool
    {
        return $codepoint === 0xfe0e || $codepoint === 0xfe0f;
    }

    private static function isEmojiVariationBase(int $codepoint): bool
    {
        return $codepoint === 0x00a9
            || $codepoint === 0x00ae
            || $codepoint === 0x203c
            || $codepoint === 0x2049
            || $codepoint === 0x2122
            || $codepoint === 0x2139
            || ($codepoint >= 0x2194 && $codepoint <= 0x21aa)
            || ($codepoint >= 0x231a && $codepoint <= 0x231b)
            || $codepoint === 0x2328
            || $codepoint === 0x23cf
            || ($codepoint >= 0x23e9 && $codepoint <= 0x23f3)
            || ($codepoint >= 0x23f8 && $codepoint <= 0x23fa)
            || $codepoint === 0x24c2
            || ($codepoint >= 0x25aa && $codepoint <= 0x25ab)
            || $codepoint === 0x25b6
            || $codepoint === 0x25c0
            || ($codepoint >= 0x25fb && $codepoint <= 0x25fe)
            || ($codepoint >= 0x2600 && $codepoint <= 0x27bf)
            || ($codepoint >= 0x2934 && $codepoint <= 0x2935)
            || ($codepoint >= 0x2b05 && $codepoint <= 0x2b55)
            || $codepoint === 0x3030
            || $codepoint === 0x303d
            || $codepoint === 0x3297
            || $codepoint === 0x3299;
    }

    private static function isEmojiSkinToneModifier(int $codepoint): bool
    {
        return $codepoint >= 0x1f3fb && $codepoint <= 0x1f3ff;
    }

    private static function isEmojiModifierClusterBase(string $cluster): bool
    {
        $hasModifierBase = false;
        foreach (self::characters($cluster) as $char) {
            $codepoint = self::codepoint($char);
            if (self::isEmojiSkinToneModifier($codepoint)) {
                return false;
            }
            if (self::isEmojiModifierBase($codepoint)) {
                $hasModifierBase = true;
            }
        }

        return $hasModifierBase;
    }

    private static function canAppendEmojiSkinToneModifier(string $cluster): bool
    {
        return self::isEmojiModifierClusterBase($cluster)
            || self::endsWithEmojiModifierBase($cluster);
    }

    private static function canZeroWidthJoinNext(string $cluster): bool
    {
        $characters = self::characters($cluster);
        for ($index = count($characters) - 1; $index >= 0; $index--) {
            $codepoint = self::codepoint($characters[$index]);
            if (self::isCombiningOrZeroWidth($codepoint) || self::isEmojiSkinToneModifier($codepoint)) {
                continue;
            }

            return self::isEmojiZwjBase($codepoint);
        }

        return false;
    }

    private static function isEmojiZwjBase(int $codepoint): bool
    {
        return self::isEmojiVariationBase($codepoint)
            || self::isEmojiModifierBase($codepoint)
            || $codepoint === 0x1f004
            || $codepoint === 0x1f0cf
            || $codepoint === 0x1f18e
            || ($codepoint >= 0x1f191 && $codepoint <= 0x1f19a)
            || ($codepoint >= 0x1f200 && $codepoint <= 0x1f202)
            || ($codepoint >= 0x1f210 && $codepoint <= 0x1f23b)
            || ($codepoint >= 0x1f240 && $codepoint <= 0x1f248)
            || ($codepoint >= 0x1f250 && $codepoint <= 0x1f251)
            || ($codepoint >= 0x1f260 && $codepoint <= 0x1f265)
            || ($codepoint >= 0x1f300 && $codepoint <= 0x1f64f)
            || ($codepoint >= 0x1f680 && $codepoint <= 0x1f6ff)
            || ($codepoint >= 0x1f7e0 && $codepoint <= 0x1f7eb)
            || $codepoint === 0x1f7f0
            || ($codepoint >= 0x1f900 && $codepoint <= 0x1f9ff)
            || ($codepoint >= 0x1fa70 && $codepoint <= 0x1faff);
    }

    private static function endsWithEmojiModifierBase(string $cluster): bool
    {
        $characters = self::characters($cluster);
        for ($index = count($characters) - 1; $index >= 0; $index--) {
            $codepoint = self::codepoint($characters[$index]);
            if (self::isCombiningOrZeroWidth($codepoint)) {
                continue;
            }

            return self::isEmojiModifierBase($codepoint);
        }

        return false;
    }

    private static function isEmojiModifierBase(int $codepoint): bool
    {
        return $codepoint === 0x261d
            || ($codepoint >= 0x270a && $codepoint <= 0x270d)
            || $codepoint === 0x1f385
            || ($codepoint >= 0x1f3c2 && $codepoint <= 0x1f3c4)
            || $codepoint === 0x1f3c7
            || ($codepoint >= 0x1f3ca && $codepoint <= 0x1f3cc)
            || ($codepoint >= 0x1f442 && $codepoint <= 0x1f443)
            || ($codepoint >= 0x1f446 && $codepoint <= 0x1f450)
            || ($codepoint >= 0x1f466 && $codepoint <= 0x1f469)
            || $codepoint === 0x1f46e
            || ($codepoint >= 0x1f470 && $codepoint <= 0x1f478)
            || $codepoint === 0x1f47c
            || ($codepoint >= 0x1f481 && $codepoint <= 0x1f483)
            || ($codepoint >= 0x1f485 && $codepoint <= 0x1f487)
            || $codepoint === 0x1f4aa
            || ($codepoint >= 0x1f574 && $codepoint <= 0x1f575)
            || $codepoint === 0x1f57a
            || $codepoint === 0x1f590
            || ($codepoint >= 0x1f595 && $codepoint <= 0x1f596)
            || ($codepoint >= 0x1f645 && $codepoint <= 0x1f647)
            || ($codepoint >= 0x1f64b && $codepoint <= 0x1f64f)
            || $codepoint === 0x1f6a3
            || ($codepoint >= 0x1f6b4 && $codepoint <= 0x1f6b6)
            || $codepoint === 0x1f6c0
            || $codepoint === 0x1f6cc
            || ($codepoint >= 0x1f90c && $codepoint <= 0x1f93a)
            || ($codepoint >= 0x1f93c && $codepoint <= 0x1f945)
            || ($codepoint >= 0x1f9b5 && $codepoint <= 0x1f9b6)
            || ($codepoint >= 0x1f9b8 && $codepoint <= 0x1f9b9)
            || ($codepoint >= 0x1f9cd && $codepoint <= 0x1f9cf)
            || ($codepoint >= 0x1f9d1 && $codepoint <= 0x1f9dd)
            || ($codepoint >= 0x1fac3 && $codepoint <= 0x1fac5)
            || ($codepoint >= 0x1faf0 && $codepoint <= 0x1faf8);
    }
}
