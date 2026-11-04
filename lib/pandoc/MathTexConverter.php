<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MathTexConverter
{
    private const TEX_FUNCTION_OPERATOR_ATTRIBUTE = 'data-tex-function-operator="true"';

    /** @var array<string, string> */
    private const IDENTIFIER_COMMANDS = [
        'aleph' => 'ℵ',
        'Alpha' => 'Α',
        'alpha' => 'α',
        'Beta' => 'Β',
        'beta' => 'β',
        'beth' => 'ℶ',
        'Chi' => 'Χ',
        'chi' => 'χ',
        'daleth' => 'ℸ',
        'Delta' => 'Δ',
        'delta' => 'δ',
        'digamma' => 'ϝ',
        'Epsilon' => 'Ε',
        'epsilon' => 'ϵ',
        'eth' => 'ð',
        'eta' => 'η',
        'Eta' => 'Η',
        'Finv' => 'Ⅎ',
        'Gamma' => 'Γ',
        'gamma' => 'γ',
        'Game' => '⅁',
        'gimel' => 'ℷ',
        'hbar' => 'ℏ',
        'ell' => 'ℓ',
        'Im' => 'ℑ',
        'imath' => 'ı',
        'infty' => '∞',
        'Iota' => 'Ι',
        'iota' => 'ι',
        'jmath' => 'ȷ',
        'Kappa' => 'Κ',
        'kappa' => 'κ',
        'Lambda' => 'Λ',
        'theta' => 'θ',
        'lambda' => 'λ',
        'Mu' => 'Μ',
        'mu' => 'μ',
        'Nu' => 'Ν',
        'nu' => 'ν',
        'Omega' => 'Ω',
        'Omicron' => 'Ο',
        'omicron' => 'ο',
        'Phi' => 'Φ',
        'phi' => 'ϕ',
        'Pi' => 'Π',
        'pi' => 'π',
        'Psi' => 'Ψ',
        'psi' => 'ψ',
        'Re' => 'ℜ',
        'Rho' => 'Ρ',
        'rho' => 'ρ',
        'Sigma' => 'Σ',
        'sigma' => 'σ',
        'Tau' => 'Τ',
        'tau' => 'τ',
        'Theta' => 'Θ',
        'Upsilon' => 'Υ',
        'upsilon' => 'υ',
        'upUpsilon' => 'ϒ',
        'varDelta' => '𝛥',
        'varGamma' => '𝛤',
        'varLambda' => '𝛬',
        'varOmega' => '𝛺',
        'varPhi' => '𝛷',
        'varPi' => '𝛱',
        'varPsi' => '𝛹',
        'varrho' => '𝜚',
        'varSigma' => '𝛴',
        'varsigma' => '𝜍',
        'varTheta' => '𝛩',
        'varUpsilon' => '𝛶',
        'varXi' => '𝛯',
        'varepsilon' => 'ε',
        'varphi' => 'φ',
        'vartheta' => 'ϑ',
        'omega' => 'ω',
        'wp' => '℘',
        'Xi' => 'Ξ',
        'xi' => 'ξ',
        'Zeta' => 'Ζ',
        'zeta' => 'ζ',
    ];

    /** @var array<string, string> */
    private const OPERATOR_COMMANDS = [
        'AC' => '⏦',
        'amalg' => '∐',
        'angle' => '∠',
        'approx' => '≈',
        'approxeq' => '≊',
        'asymp' => '≍',
        'ast' => '*',
        'backslash' => '∖',
        'backprime' => '‵',
        'backcong' => '≌',
        'barwedge' => '⌅',
        'because' => '∵',
        'backepsilon' => '϶',
        'backsim' => '∽',
        'backsimeq' => '⋍',
        'bigcap' => '⋂',
        'bigcirc' => '○',
        'bigcup' => '⋃',
        'bigodot' => '⨀',
        'bigoplus' => '⨁',
        'bigotimes' => '⨂',
        'bigsqcup' => '⨆',
        'bigvee' => '⋁',
        'bigwedge' => '⋀',
        'blacklozenge' => '⬧',
        'blacksquare' => '■',
        'blacktriangle' => '▴',
        'blacktriangleleft' => '◂',
        'blacktriangleright' => '▸',
        'bot' => '⊥',
        'boxdot' => '⊡',
        'boxminus' => '⊟',
        'boxplus' => '⊞',
        'boxtimes' => '⊠',
        'bowtie' => '⋈',
        'Box' => '□',
        'bullet' => '∙',
        'Bumpeq' => '≎',
        'bumpeq' => '≏',
        'cap' => '∩',
        'cdots' => '⋯',
        'cdot' => '⋅',
        'circ' => '∘',
        'colon' => ':',
        'cong' => '≅',
        'coprod' => '∐',
        'cup' => '∪',
        'curlyvee' => '⋎',
        'curlywedge' => '⋏',
        'curlyeqprec' => '⋞',
        'curlyeqsucc' => '⋟',
        'dag' => '†',
        'dashv' => '⊣',
        'ddag' => '‡',
        'ddots' => '⋱',
        'Diamond' => '◇',
        'diamond' => '⋄',
        'div' => '÷',
        'divideontimes' => '⋇',
        'doteq' => '≐',
        'Doteq' => '≑',
        'doteqdot' => '≑',
        'dotplus' => '∔',
        'dots' => '…',
        'dotsb' => '⋯',
        'dotsc' => '…',
        'dotsi' => '⋯',
        'dotsm' => '⋯',
        'dotso' => '…',
        'emptyset' => '∅',
        'eqcolon' => '≕',
        'eqcirc' => '≖',
        'eqsim' => '≂',
        'eqslantgtr' => '⋝',
        'eqslantless' => '⋜',
        'equiv' => '≡',
        'exists' => '∃',
        'fallingdotseq' => '≒',
        'forall' => '∀',
        'frown' => '⌢',
        'ge' => '≥',
        'geqless' => '⋛',
        'geq' => '≥',
        'geqq' => '≧',
        'geqslant' => '≥',
        'gneq' => '⪈',
        'gneqq' => '≩',
        'gnsim' => '⋧',
        'gg' => '≫',
        'ggg' => '⋙',
        'gggtr' => '⋙',
        'gtrdot' => '⋗',
        'gtreqless' => '⋛',
        'gtreqqless' => '⪌',
        'gtrapprox' => '⪆',
        'gtrless' => '≷',
        'gtrsim' => '≳',
        'gt' => '>',
        'hdots' => '…',
        'hookleftarrow' => '↩',
        'hookrightarrow' => '↪',
        'iff' => '⇔',
        'in' => '∈',
        'iint' => '∬',
        'iiint' => '∭',
        'int' => '∫',
        'Join' => '⋈',
        'land' => '∧',
        'ldots' => '…',
        'le' => '≤',
        'leqgtr' => '⋚',
        'leq' => '≤',
        'leqq' => '≦',
        'leqslant' => '≤',
        'leadsto' => '⤳',
        'leftarrow' => '←',
        'leftharpoondown' => '↽',
        'leftharpoonup' => '↼',
        'leftrightarrow' => '↔',
        'leftrightharpoons' => '⇋',
        'lessapprox' => '⪅',
        'lessdot' => '⋖',
        'lesseqgtr' => '⋚',
        'lesseqqgtr' => '⪋',
        'lessgtr' => '≶',
        'lesssim' => '≲',
        'lhd' => '⊲',
        'lll' => '⋘',
        'llless' => '⋘',
        'lneq' => '⪇',
        'lneqq' => '≨',
        'lnsim' => '⋦',
        'lnot' => '¬',
        'lim' => 'lim',
        'lor' => '∨',
        'Longleftarrow' => '⇐',
        'Longleftrightarrow' => '⇔',
        'Longrightarrow' => '⇒',
        'longleftarrow' => '←',
        'longleftrightarrow' => '↔',
        'longmapsto' => '⟼',
        'longrightarrow' => '→',
        'lozenge' => '◊',
        'll' => '≪',
        'lt' => '<',
        'mapsto' => '↦',
        'mid' => '∣',
        'models' => '⊨',
        'mp' => '∓',
        'multimap' => '⊸',
        'nabla' => '∇',
        'napprox' => '≉',
        'ncong' => '≇',
        'nearrow' => '↗',
        'neg' => '¬',
        'ne' => '≠',
        'neq' => '≠',
        'nexists' => '∄',
        'ngeq' => '≱',
        'ngeqq' => '≱',
        'ngeqslant' => '≱',
        'ngtr' => '≯',
        'nleftarrow' => '↚',
        'nleftrightarrow' => '↮',
        'nleq' => '≰',
        'nleqq' => '≰',
        'nleqslant' => '≰',
        'nless' => '≮',
        'nmid' => '∤',
        'nparallel' => '∦',
        'nprec' => '⊀',
        'npreceq' => '⋠',
        'nrightarrow' => '↛',
        'nsubset' => '⊄',
        'nsubseteq' => '⊈',
        'nsucc' => '⊁',
        'nsucceq' => '⋡',
        'nsupset' => '⊅',
        'nsupseteq' => '⊉',
        'nvdash' => '⊬',
        'nvDash' => '⊭',
        'nVdash' => '⊮',
        'nVDash' => '⊯',
        'nwarrow' => '↖',
        'nRightarrow' => '⇏',
        'odot' => '⊙',
        'ominus' => '⊖',
        'notin' => '∉',
        'oint' => '∮',
        'oiint' => '∯',
        'oiiint' => '∰',
        'oplus' => '⊕',
        'oslash' => '⊘',
        'otimes' => '⊗',
        'parallel' => '∥',
        'partial' => '∂',
        'perp' => '⊥',
        'pitchfork' => '⋔',
        'pm' => '±',
        'prec' => '≺',
        'precapprox' => '⪷',
        'preccurlyeq' => '≼',
        'preceq' => '≼',
        'precsim' => '≾',
        'propto' => '∝',
        'prime' => '′',
        'prod' => '∏',
        'rhd' => '⊳',
        'rightarrow' => '→',
        'Rightarrow' => '⇒',
        'risingdotseq' => '≓',
        'rightharpoondown' => '⇁',
        'rightharpoonup' => '⇀',
        'rightleftharpoons' => '⇌',
        'rightsquigarrow' => '⇝',
        'searrow' => '↘',
        'setminus' => '∖',
        'simeq' => '≃',
        'sim' => '∼',
        'smallsetminus' => '∖',
        'smile' => '⌣',
        'sqsubset' => '⊏',
        'sqsubseteq' => '⊑',
        'square' => '□',
        'sqsupset' => '⊐',
        'sqsupseteq' => '⊒',
        'star' => '⋆',
        'subset' => '⊂',
        'subseteq' => '⊆',
        'subsetneq' => '⊊',
        'subsetneqq' => '⫋',
        'succ' => '≻',
        'succapprox' => '⪸',
        'succcurlyeq' => '≽',
        'succeq' => '≽',
        'succsim' => '≿',
        'sum' => '∑',
        'supset' => '⊃',
        'supseteq' => '⊇',
        'supsetneq' => '⊋',
        'supsetneqq' => '⫌',
        'swarrow' => '↙',
        'therefore' => '∴',
        'thickapprox' => '≈',
        'thicksim' => '∼',
        'top' => '⊤',
        'times' => '×',
        'triangleq' => '≜',
        'to' => '→',
        'triangle' => '△',
        'triangleleft' => '◁',
        'triangleright' => '▷',
        'twoheadleftarrow' => '↞',
        'twoheadrightarrow' => '↠',
        'unlhd' => '⊴',
        'unrhd' => '⊵',
        'varnothing' => '⌀',
        'varpropto' => '∝',
        'vee' => '∨',
        'vdash' => '⊢',
        'vdots' => '⋮',
        'Vvdash' => '⊪',
        'wedge' => '∧',
        'wr' => '≀',
    ];

    /** @var array<string, string> */
    private const NOT_RELATION_COMMANDS = [
        '=' => '≠',
        'approx' => '≉',
        'equiv' => '≢',
        'ge' => '≱',
        'geq' => '≱',
        'geqslant' => '≱',
        'gt' => '≯',
        'in' => '∉',
        'le' => '≰',
        'leq' => '≰',
        'leqslant' => '≰',
        'leftarrow' => '↚',
        'leftrightarrow' => '↮',
        'lt' => '≮',
        'Rightarrow' => '⇏',
        'rightarrow' => '↛',
        'subset' => '⊄',
        'subseteq' => '⊈',
        'supset' => '⊅',
        'supseteq' => '⊉',
        'to' => '↛',
    ];

    /** @var array<string, string> */
    private const NOT_RELATION_TOKENS = [
        '=' => '≠',
        '<' => '≮',
        '>' => '≯',
    ];

    /** @var array<string, array{lineThickness?: string, open?: string, close?: string}> */
    private const INFIX_FRACTION_COMMANDS = [
        'atop' => ['lineThickness' => '0'],
        'bangle' => ['lineThickness' => '0', 'open' => '⟨', 'close' => '⟩'],
        'brace' => ['lineThickness' => '0', 'open' => '{', 'close' => '}'],
        'brack' => ['lineThickness' => '0', 'open' => '[', 'close' => ']'],
        'choose' => ['lineThickness' => '0', 'open' => '(', 'close' => ')'],
        'over' => [],
    ];

    /** @var array<string, string> */
    private const FUNCTION_COMMANDS = [
        'arg' => 'arg',
        'arccos' => 'arccos',
        'arcsin' => 'arcsin',
        'arctan' => 'arctan',
        'cos' => 'cos',
        'cosh' => 'cosh',
        'cot' => 'cot',
        'coth' => 'coth',
        'csc' => 'csc',
        'deg' => 'deg',
        'det' => 'det',
        'dim' => 'dim',
        'exp' => 'exp',
        'gcd' => 'gcd',
        'hom' => 'hom',
        'inf' => 'inf',
        'ker' => 'ker',
        'lg' => 'lg',
        'liminf' => 'liminf',
        'limsup' => 'limsup',
        'ln' => 'ln',
        'log' => 'log',
        'max' => 'max',
        'min' => 'min',
        'Pr' => 'Pr',
        'sec' => 'sec',
        'sin' => 'sin',
        'sinh' => 'sinh',
        'sup' => 'sup',
        'tan' => 'tan',
        'tanh' => 'tanh',
    ];

    /** @var array<string, string|null> */
    private const TEXT_MODE_COMMANDS = [
        'emph' => 'italic',
        'mbox' => null,
        'text' => null,
        'textbf' => 'bold',
        'textit' => 'italic',
        'textmd' => 'normal',
        'textnormal' => 'normal',
        'textrm' => 'normal',
        'textsf' => 'sans-serif',
        'texttt' => 'monospace',
        'textup' => 'normal',
    ];

    /** @var array<string, string> */
    private const TEXT_MODE_NAMED_GLYPHS = [
        'AA' => 'Å',
        'aa' => 'å',
        'AE' => 'Æ',
        'ae' => 'æ',
        'L' => 'Ł',
        'l' => 'ł',
        'O' => 'Ø',
        'o' => 'ø',
        'OE' => 'Œ',
        'oe' => 'œ',
        'ss' => 'ß',
    ];

    /** @var array<string, array<string, string>> */
    private const TEXT_MODE_ACCENT_GLYPHS = [
        '"' => [
            'A' => 'Ä',
            'E' => 'Ë',
            'I' => 'Ï',
            'O' => 'Ö',
            'U' => 'Ü',
            'Y' => 'Ÿ',
            'a' => 'ä',
            'e' => 'ë',
            'i' => 'ï',
            'o' => 'ö',
            'u' => 'ü',
            'y' => 'ÿ',
        ],
    ];

    /** @var array<string, string> */
    private const SPACING_COMMANDS = [
        ' ' => '0.3333em',
        '!' => '-0.1667em',
        ',' => '0.1667em',
        ':' => '0.2222em',
        ';' => '0.2778em',
        '>' => '0.2222em',
        'enspace' => '0.5em',
        'medspace' => '0.2222em',
        'negmedspace' => '-0.2222em',
        'negthickspace' => '-0.2778em',
        'negthinspace' => '-0.1667em',
        'quad' => '1em',
        'qquad' => '2em',
        'thickspace' => '0.2778em',
        'thinspace' => '0.1667em',
    ];

    /** @var array<string, string> */
    private const ESCAPED_SYMBOL_COMMANDS = [
        '#' => '#',
        '$' => '$',
        '%' => '%',
        '&' => '&',
        '_' => '_',
        'textbackslash' => '\\',
    ];

    /** @var array<string, string> */
    private const SI_UNIT_COMMANDS = [
        'A' => 'A',
        'ampere' => 'A',
        'amu' => 'u',
        'angstrom' => 'Å',
        'arcmin' => '′',
        'arcminute' => '′',
        'arcsecond' => '″',
        'as' => 'as',
        'astronomicalunit' => 'ua',
        'atomicmassunit' => 'u',
        'atto' => 'a',
        'bar' => 'bar',
        'barn' => 'b',
        'becquerel' => 'Bq',
        'bel' => 'B',
        'candela' => 'cd',
        'celsius' => '°C',
        'centi' => 'c',
        'cm' => 'cm',
        'coulomb' => 'C',
        'dB' => 'dB',
        'day' => 'd',
        'dalton' => 'Da',
        'deca' => 'd',
        'deci' => 'd',
        'decibel' => 'db',
        'degree' => '°',
        'degreeCelsius' => '°C',
        'deka' => 'd',
        'dm' => 'dm',
        'electronvolt' => 'eV',
        'exa' => 'E',
        'farad' => 'F',
        'fF' => 'fF',
        'fg' => 'fg',
        'femto' => 'f',
        'fmol' => 'fmol',
        'fs' => 'fs',
        'g' => 'g',
        'GeV' => 'GeV',
        'GHz' => 'GHz',
        'giga' => 'G',
        'gram' => 'g',
        'GPa' => 'GPa',
        'gray' => 'Gy',
        'GW' => 'GW',
        'h' => 'h',
        'hectare' => 'ha',
        'hecto' => 'h',
        'henry' => 'H',
        'hertz' => 'Hz',
        'hl' => 'hl',
        'hL' => 'hL',
        'hour' => 'h',
        'Hz' => 'Hz',
        'joule' => 'J',
        'K' => 'K',
        'katal' => 'kat',
        'kelvin' => 'K',
        'kA' => 'kA',
        'kg' => 'kg',
        'kilogram' => 'kg',
        'keV' => 'keV',
        'kHz' => 'kHz',
        'kJ' => 'kJ',
        'kilo' => 'k',
        'km' => 'km',
        'kmol' => 'kmol',
        'kN' => 'kN',
        'knot' => 'kn',
        'kohm' => 'kΩ',
        'kPa' => 'kPa',
        'kV' => 'kV',
        'kW' => 'kW',
        'kWh' => 'kWh',
        'L' => 'L',
        'l' => 'l',
        'liter' => 'L',
        'litre' => 'l',
        'lumen' => 'lm',
        'lux' => 'lx',
        'm' => 'm',
        'mA' => 'mA',
        'mega' => 'M',
        'meter' => 'm',
        'metre' => 'm',
        'MeV' => 'MeV',
        'micro' => 'μ',
        'meV' => 'meV',
        'mg' => 'mg',
        'MHz' => 'MHz',
        'mHz' => 'mHz',
        'milli' => 'm',
        'mJ' => 'mJ',
        'mL' => 'mL',
        'ml' => 'ml',
        'minute' => 'min',
        'mm' => 'mm',
        'mmHg' => 'mmHg',
        'mmol' => 'mmol',
        'mN' => 'mN',
        'MN' => 'MN',
        'Mohm' => 'MΩ',
        'mol' => 'mol',
        'mole' => 'mol',
        'mohm' => 'mΩ',
        'MPa' => 'MPa',
        'ms' => 'ms',
        'mV' => 'mV',
        'MW' => 'MW',
        'mW' => 'mW',
        'nA' => 'nA',
        'nano' => 'n',
        'nauticalmile' => 'M',
        'ng' => 'ng',
        'nm' => 'nm',
        'nmol' => 'nmol',
        'neper' => 'Np',
        'newton' => 'N',
        'ns' => 'ns',
        'nV' => 'nV',
        'ohm' => 'Ω',
        'pA' => 'pA',
        'pascal' => 'Pa',
        'pF' => 'pF',
        'peta' => 'P',
        'pg' => 'pg',
        'pico' => 'p',
        'pm' => 'pm',
        'pmol' => 'pmol',
        'per' => '/',
        'percent' => '%',
        'ps' => 'ps',
        'pV' => 'pV',
        'radian' => 'rad',
        's' => 's',
        'second' => 's',
        'siemens' => 'S',
        'sievert' => 'Sv',
        'steradian' => 'sr',
        'tesla' => 'T',
        'tera' => 'T',
        'TeV' => 'TeV',
        'THz' => 'THz',
        'tonne' => 't',
        'uA' => 'μA',
        'ug' => 'μg',
        'uJ' => 'μJ',
        'uL' => 'μL',
        'ul' => 'μl',
        'um' => 'μm',
        'umol' => 'μmol',
        'us' => 'μs',
        'uV' => 'μV',
        'uW' => 'μW',
        'V' => 'V',
        'volt' => 'V',
        'W' => 'W',
        'watt' => 'W',
        'weber' => 'Wb',
        'yocto' => 'y',
        'yotta' => 'Y',
        'zepto' => 'z',
        'zetta' => 'Z',
    ];

    /** @var array<string, string> */
    private const SI_UNIT_POWER_COMMANDS = [
        'cubed' => '3',
        'squared' => '2',
    ];

    /** @var array<string, true> */
    private const ARRAY_HOOK_COMMANDS = [
        ' ' => true,
        '!' => true,
        ',' => true,
        ':' => true,
        ';' => true,
        '>' => true,
        'enspace' => true,
        'hspace' => true,
        'kern' => true,
        'mbox' => true,
        'medspace' => true,
        'mkern' => true,
        'mspace' => true,
        'negmedspace' => true,
        'negthickspace' => true,
        'negthinspace' => true,
        'quad' => true,
        'qquad' => true,
        'text' => true,
        'thickspace' => true,
        'thinspace' => true,
    ];

    /** @var array<string, string> */
    private const OVER_ACCENT_COMMANDS = [
        'acute' => '´',
        'bar' => '¯',
        'breve' => '˘',
        'check' => 'ˇ',
        'DDDot' => "\u{20DB}",
        'ddddot' => "\u{20DC}",
        'dddot' => "\u{20DB}",
        'ddot' => '¨',
        'dot' => '˙',
        'grave' => '`',
        'hat' => '^',
        'mathring' => '˚',
        'overbar' => '¯',
        'overline' => '‾',
        'tilde' => '~',
        'vec' => '→',
        'widehat' => '^',
        'widetilde' => '~',
    ];

    /** @var array<string, string> */
    private const UNDER_ACCENT_COMMANDS = [
        'underbar' => "\u{0331}",
        'underline' => '_',
        'utilde' => "\u{0330}",
        'wideutilde' => "\u{0330}",
    ];

    /** @var array<string, string> */
    private const EXTENSIBLE_ARROW_COMMANDS = [
        'xleftarrow' => '←',
        'xleftharpoondown' => '↽',
        'xleftharpoonup' => '↼',
        'xleftrightarrow' => '↔',
        'xlongequal' => '=',
        'xLeftarrow' => '⇐',
        'xRightarrow' => '⇒',
        'xLeftrightarrow' => '⇔',
        'xleftrightharpoons' => '⇋',
        'xmapsto' => '↦',
        'xhookleftarrow' => '↩',
        'xhookrightarrow' => '↪',
        'xrightleftharpoons' => '⇌',
        'xrightharpoondown' => '⇁',
        'xrightharpoonup' => '⇀',
        'xrightarrow' => '→',
        'xtwoheadleftarrow' => '↞',
        'xtwoheadrightarrow' => '↠',
    ];

    /** @var array<string, array{glyph: string, position: 'over'|'under'}> */
    private const ARROW_ACCENT_COMMANDS = [
        'overleftarrow' => ['glyph' => '←', 'position' => 'over'],
        'overrightarrow' => ['glyph' => '→', 'position' => 'over'],
        'overleftrightarrow' => ['glyph' => '↔', 'position' => 'over'],
        'underleftarrow' => ['glyph' => '←', 'position' => 'under'],
        'underrightarrow' => ['glyph' => '→', 'position' => 'under'],
        'underleftrightarrow' => ['glyph' => '↔', 'position' => 'under'],
    ];

    /** @var array<string, string> */
    private const CANCEL_COMMANDS = [
        'bcancel' => 'downdiagonalstrike',
        'cancel' => 'updiagonalstrike',
        'xcancel' => 'updiagonalstrike downdiagonalstrike',
    ];

    /** @var array<string, array{width: string, lspace?: string}> */
    private const OVERLAP_BOX_COMMANDS = [
        'clap' => ['width' => '0', 'lspace' => '-0.5width'],
        'llap' => ['width' => '0', 'lspace' => '-1width'],
        'mathclap' => ['width' => '0', 'lspace' => '-0.5width'],
        'mathllap' => ['width' => '0', 'lspace' => '-1width'],
        'mathrlap' => ['width' => '0'],
        'rlap' => ['width' => '0'],
    ];

    /** @var array<string, string> */
    private const MATH_VARIANT_COMMANDS = [
        'bm' => 'bold',
        'boldsymbol' => 'bold',
        'mathbf' => 'bold',
        'mathbfcal' => 'bold-script',
        'mathbfup' => 'bold',
        'mathbb' => 'double-struck',
        'mathbffrak' => 'bold-fraktur',
        'mathbfit' => 'bold-italic',
        'mathbfscr' => 'bold-script',
        'mathbfsfit' => 'sans-serif-bold-italic',
        'mathbfsfup' => 'bold-sans-serif',
        'mathbold' => 'bold',
        'mathcal' => 'script',
        'mathds' => 'double-struck',
        'mathfrak' => 'fraktur',
        'mathit' => 'italic',
        'mathsfit' => 'sans-serif-italic',
        'mathscr' => 'script',
        'mathsf' => 'sans-serif',
        'mathsfup' => 'sans-serif',
        'mathtt' => 'monospace',
        'mathup' => 'normal',
        'pmb' => 'bold',
        'mathrm' => 'normal',
        'symbf' => 'bold',
    ];

    /** @var array<string, string> */
    private const MATH_CLASS_COMMANDS = [
        'mathbin' => 'binary',
        'mathclose' => 'close',
        'mathinner' => 'inner',
        'mathop' => 'operator',
        'mathopen' => 'open',
        'mathord' => 'ordinary',
        'mathpunct' => 'punctuation',
        'mathrel' => 'relation',
    ];

    /** @var list<string> */
    private const MATH_ATOM_CATEGORY_ORDER = ['Ord', 'Op', 'Bin', 'Rel', 'Open', 'Close', 'Pun', 'Inner'];

    /** @var array<string, string> */
    private const MATH_CLASS_ATOM_CATEGORIES = [
        'binary' => 'Bin',
        'close' => 'Close',
        'inner' => 'Inner',
        'open' => 'Open',
        'operator' => 'Op',
        'ordinary' => 'Ord',
        'punctuation' => 'Pun',
        'relation' => 'Rel',
    ];

    /** @var array<string, true> */
    private const MATH_OPEN_ATOM_TOKENS = [
        '(' => true,
        '[' => true,
        '{' => true,
        '⌈' => true,
        '⌊' => true,
        '⌜' => true,
        '⟦' => true,
        '⟨' => true,
        '⟮' => true,
        '⎰' => true,
        '〔' => true,
        '〘' => true,
    ];

    /** @var array<string, true> */
    private const MATH_CLOSE_ATOM_TOKENS = [
        ')' => true,
        ']' => true,
        '}' => true,
        '⌉' => true,
        '⌋' => true,
        '⌝' => true,
        '⟧' => true,
        '⟩' => true,
        '⟯' => true,
        '⎱' => true,
        '〕' => true,
        '〙' => true,
    ];

    /** @var array<string, true> */
    private const MATH_PUNCTUATION_ATOM_TOKENS = [
        ',' => true,
        ';' => true,
        ':' => true,
    ];

    /** @var array<string, true> */
    private const MATH_BINARY_ATOM_TOKENS = [
        '+' => true,
        '-' => true,
        '*' => true,
        '/' => true,
        '±' => true,
        '∓' => true,
        '∖' => true,
        '∩' => true,
        '∪' => true,
        '∧' => true,
        '∨' => true,
        '×' => true,
        '⋅' => true,
        '∘' => true,
        '⊕' => true,
        '⊖' => true,
        '⊗' => true,
        '⊘' => true,
        '⊙' => true,
    ];

    /** @var array<string, true> */
    private const MATH_RELATION_ATOM_TOKENS = [
        '=' => true,
        '<' => true,
        '>' => true,
        '≤' => true,
        '≥' => true,
        '≠' => true,
        '≈' => true,
        '≉' => true,
        '≊' => true,
        '≅' => true,
        '≇' => true,
        '≡' => true,
        '≢' => true,
        '≺' => true,
        '≼' => true,
        '≻' => true,
        '≽' => true,
        '⊂' => true,
        '⊄' => true,
        '⊆' => true,
        '⊈' => true,
        '⊃' => true,
        '⊅' => true,
        '⊇' => true,
        '⊉' => true,
        '⊢' => true,
        '⊨' => true,
        '⊬' => true,
        '⊭' => true,
        '∈' => true,
        '∉' => true,
        '∋' => true,
        '∌' => true,
        '∣' => true,
        '∤' => true,
        '∥' => true,
        '∦' => true,
        '∝' => true,
        '∼' => true,
        '≃' => true,
        '≍' => true,
        '≲' => true,
        '≳' => true,
        '≪' => true,
        '≫' => true,
        '→' => true,
        '←' => true,
        '↔' => true,
        '↚' => true,
        '↛' => true,
        '↮' => true,
        '⇒' => true,
        '⇐' => true,
        '⇔' => true,
        '⇏' => true,
    ];

    /** @var array<string, true> */
    private const MATH_OPERATOR_ATOM_TOKENS = [
        '∂' => true,
        '∇' => true,
        '∑' => true,
        '∏' => true,
        '∐' => true,
        '∫' => true,
        '∬' => true,
        '∭' => true,
        '∮' => true,
        '∯' => true,
        '∰' => true,
        '⋃' => true,
        '⋂' => true,
        '⋀' => true,
        '⋁' => true,
        '⨀' => true,
        '⨁' => true,
        '⨂' => true,
        '⨆' => true,
    ];

    /** @var array<string, string> */
    private const DELIMITER_COMMANDS = [
        '|' => '‖',
        '{' => '{',
        '}' => '}',
        'arrowvert' => '|',
        'Arrowvert' => '‖',
        'bracevert' => '⎪',
        'downarrow' => '↓',
        'Downarrow' => '⇓',
        'Lbrbrak' => '〘',
        'langle' => '⟨',
        'lbrack' => '[',
        'lbrbrak' => '〔',
        'lbrace' => '{',
        'lceil' => '⌈',
        'lfloor' => '⌊',
        'lgroup' => '⟮',
        'llbracket' => '⟦',
        'lmoustache' => '⎰',
        'lparen' => '(',
        'lvert' => '|',
        'lVert' => '‖',
        'Rbrbrak' => '〙',
        'rangle' => '⟩',
        'rbrack' => ']',
        'rbrbrak' => '〕',
        'rbrace' => '}',
        'rceil' => '⌉',
        'rfloor' => '⌋',
        'rgroup' => '⟯',
        'rrbracket' => '⟧',
        'rmoustache' => '⎱',
        'rparen' => ')',
        'rvert' => '|',
        'rVert' => '‖',
        'ulcorner' => '⌜',
        'uparrow' => '↑',
        'Uparrow' => '⇑',
        'updownarrow' => '↕',
        'Updownarrow' => '⇕',
        'urcorner' => '⌝',
        'vert' => '|',
        'Vert' => '‖',
    ];

    /** @var array<string, array{size: string, separator?: bool}> */
    private const SIZED_DELIMITER_COMMANDS = [
        'big' => ['size' => '1.2em'],
        'bigl' => ['size' => '1.2em'],
        'bigr' => ['size' => '1.2em'],
        'bigm' => ['size' => '1.2em', 'separator' => true],
        'Big' => ['size' => '1.8em'],
        'Bigl' => ['size' => '1.8em'],
        'Bigr' => ['size' => '1.8em'],
        'Bigm' => ['size' => '1.8em', 'separator' => true],
        'bigg' => ['size' => '2.4em'],
        'biggl' => ['size' => '2.4em'],
        'biggr' => ['size' => '2.4em'],
        'biggm' => ['size' => '2.4em', 'separator' => true],
        'Bigg' => ['size' => '3em'],
        'Biggl' => ['size' => '3em'],
        'Biggr' => ['size' => '3em'],
        'Biggm' => ['size' => '3em', 'separator' => true],
    ];

    /** @var array<string, array{open?: string, close?: string, columnalign?: string, displaystyle?: bool}> */
    private const MATRIX_ENVIRONMENTS = [
        'aligned' => ['columnalign' => 'right left'],
        'bmatrix' => ['open' => '[', 'close' => ']'],
        'Bmatrix' => ['open' => '{', 'close' => '}'],
        'cases' => ['open' => '{', 'columnalign' => 'left left'],
        'dcases' => ['open' => '{', 'columnalign' => 'left left', 'displaystyle' => true],
        'drcases' => ['close' => '}', 'columnalign' => 'left left', 'displaystyle' => true],
        'matrix' => [],
        'pmatrix' => ['open' => '(', 'close' => ')'],
        'rcases' => ['close' => '}', 'columnalign' => 'left left'],
        'vmatrix' => ['open' => '|', 'close' => '|'],
        'Vmatrix' => ['open' => '‖', 'close' => '‖'],
    ];

    /** @var array<string, string> */
    private const MATRIX_COMMAND_ENVIRONMENTS = [
        'matrix' => 'matrix',
        'pmatrix' => 'pmatrix',
        'bmatrix' => 'bmatrix',
        'Bmatrix' => 'Bmatrix',
        'vmatrix' => 'vmatrix',
        'Vmatrix' => 'Vmatrix',
        'cases' => 'cases',
    ];

    /** @var array<string, array{columnalign: string, columns: int}> */
    private const PLAIN_ALIGNMENT_COMMANDS = [
        'displaylines' => ['columnalign' => 'center', 'columns' => 1],
        'eqalign' => ['columnalign' => 'right left', 'columns' => 2],
    ];

    /** @var array<string, array{columnalign: string, columns: int}> */
    private const AMS_ROW_ENVIRONMENTS = [
        'align' => ['columnalign' => 'right left', 'columns' => 2],
        'align*' => ['columnalign' => 'right left', 'columns' => 2],
        'gather' => ['columnalign' => 'center', 'columns' => 1],
        'gather*' => ['columnalign' => 'center', 'columns' => 1],
        'gathered' => ['columnalign' => 'center', 'columns' => 1],
        'multline' => ['columnalign' => 'center', 'columns' => 1],
        'multline*' => ['columnalign' => 'center', 'columns' => 1],
        'multlined' => ['columnalign' => 'center', 'columns' => 1],
        'split' => ['columnalign' => 'right left', 'columns' => 2],
    ];

    /** @var array<string, true> */
    private const AMS_ALIGNEDAT_ENVIRONMENTS = [
        'alignat' => true,
        'alignat*' => true,
        'alignedat' => true,
        'alignedat*' => true,
    ];

    /** @var array<string, true> */
    private const AMS_FLUSH_ALIGNED_ENVIRONMENTS = [
        'flalign' => true,
        'flalign*' => true,
        'flaligned' => true,
        'flaligned*' => true,
    ];

    /** @var array<string, true> */
    private const AMS_INTERTEXT_ENVIRONMENTS = [
        'align' => true,
        'align*' => true,
        'alignat' => true,
        'alignat*' => true,
        'flalign' => true,
        'flalign*' => true,
    ];

    private const INTERTEXT_ROW_MARKER = '__portlibs_tex_intertext_row__';

    /** @var array<string, true> */
    private const AMS_OPTIONAL_POSITION_ENVIRONMENTS = [
        'aligned' => true,
        'alignedat' => true,
        'alignedat*' => true,
        'flaligned' => true,
        'flaligned*' => true,
        'gathered' => true,
        'multlined' => true,
    ];

    /** @var array<string, true> */
    private const EQNARRAY_ENVIRONMENTS = [
        'eqnarray' => true,
        'eqnarray*' => true,
    ];

    /** @var array<string, bool> */
    private const EQUATION_WRAPPER_ENVIRONMENTS = [
        'equation' => true,
        'equation*' => false,
    ];

    /** @var array<string, string> */
    private const ACCESSIBILITY_TOKEN_TEXT = [
        '+' => 'plus',
        '-' => 'minus',
        '–' => 'to',
        '=' => 'equals',
        '<' => 'less than',
        '>' => 'greater than',
        '/' => 'slash',
        '\\' => 'backslash',
        '#' => 'number sign',
        '$' => 'dollar sign',
        '%' => 'percent sign',
        '&' => 'ampersand',
        ',' => 'comma',
        ':' => 'colon',
        ';' => 'semicolon',
        '(' => 'left parenthesis',
        ')' => 'right parenthesis',
        '[' => 'left bracket',
        ']' => 'right bracket',
        '{' => 'left brace',
        '}' => 'right brace',
        '⌈' => 'left ceiling',
        '⌉' => 'right ceiling',
        '⌊' => 'left floor',
        '⌋' => 'right floor',
        '⟦' => 'left double bracket',
        '⟧' => 'right double bracket',
        '〔' => 'left tortoise shell bracket',
        '〕' => 'right tortoise shell bracket',
        '〘' => 'left white tortoise shell bracket',
        '〙' => 'right white tortoise shell bracket',
        '⌜' => 'upper left corner',
        '⌝' => 'upper right corner',
        '|' => 'vertical bar',
        '↑' => 'up arrow',
        '↓' => 'down arrow',
        '↕' => 'up down arrow',
        '⇑' => 'double up arrow',
        '⇓' => 'double down arrow',
        '⇕' => 'double up down arrow',
        'α' => 'alpha',
        'β' => 'beta',
        'γ' => 'gamma',
        'δ' => 'delta',
        'ε' => 'epsilon',
        'ϵ' => 'epsilon',
        'ζ' => 'zeta',
        'η' => 'eta',
        'θ' => 'theta',
        'ϑ' => 'theta',
        'ι' => 'iota',
        'κ' => 'kappa',
        'λ' => 'lambda',
        'μ' => 'mu',
        'ν' => 'nu',
        'ξ' => 'xi',
        'ο' => 'omicron',
        'π' => 'pi',
        'ρ' => 'rho',
        'ϱ' => 'rho',
        'σ' => 'sigma',
        'τ' => 'tau',
        'υ' => 'upsilon',
        'φ' => 'phi',
        'ϕ' => 'phi',
        'χ' => 'chi',
        'ψ' => 'psi',
        'ω' => 'omega',
        'ϝ' => 'digamma',
        'Α' => 'alpha',
        'Β' => 'beta',
        'Χ' => 'chi',
        'Γ' => 'gamma',
        'Δ' => 'delta',
        'Ε' => 'epsilon',
        'Ζ' => 'zeta',
        'Η' => 'eta',
        'Θ' => 'theta',
        'Ι' => 'iota',
        'Κ' => 'kappa',
        'Λ' => 'lambda',
        'Μ' => 'mu',
        'Ν' => 'nu',
        'Ξ' => 'xi',
        'Ο' => 'omicron',
        'Π' => 'pi',
        'Ρ' => 'rho',
        'Σ' => 'sigma',
        'Τ' => 'tau',
        'Υ' => 'upsilon',
        'Φ' => 'phi',
        'Ψ' => 'psi',
        'Ω' => 'omega',
        'ℶ' => 'beth',
        'ℷ' => 'gimel',
        'ℸ' => 'daleth',
        'ð' => 'eth',
        'Ⅎ' => 'turned F',
        '⅁' => 'turned G',
        'ı' => 'dotless i',
        'ȷ' => 'dotless j',
        'ℏ' => 'h bar',
        '⏦' => 'AC current',
        '∞' => 'infinity',
        '⁡' => 'of',
        '≈' => 'approximately equals',
        '≊' => 'approximately equal or equal to',
        '϶' => 'reversed epsilon',
        '≍' => 'asymptotically equal to',
        '∽' => 'reverse similar',
        '⋍' => 'reverse similar or equal',
        '≎' => 'bumpy equals',
        '≏' => 'bumpy equals',
        '∔' => 'dot plus',
        '⋎' => 'curly vee',
        '⋏' => 'curly wedge',
        '⋇' => 'divide on times',
        '⋀' => 'big wedge',
        '⋁' => 'big vee',
        '⋂' => 'big intersection',
        '⋃' => 'big union',
        '∩' => 'intersection',
        '⋅' => 'dot',
        '∙' => 'bullet operator',
        '∘' => 'circle operator',
        '⋆' => 'star operator',
        '⋄' => 'diamond operator',
        '⋯' => 'center dots',
        '⋮' => 'vertical dots',
        '…' => 'ellipsis',
        '∪' => 'union',
        '∅' => 'empty set',
        '⌀' => 'empty set',
        '÷' => 'divided by',
        '†' => 'dagger',
        '‡' => 'double dagger',
        '⌅' => 'bar wedge',
        '≀' => 'wreath product',
        '≡' => 'equivalent',
        '≕' => 'equals colon',
        '≐' => 'dotted equals',
        '≑' => 'dotted equals dot',
        '≒' => 'falling dots equals',
        '≓' => 'rising dots equals',
        '≖' => 'ring in equals',
        '≂' => 'minus tilde',
        '≜' => 'triangle equals',
        '⋜' => 'equal slanted less',
        '⋝' => 'equal slanted greater',
        '∃' => 'there exists',
        '∀' => 'for all',
        '≦' => 'less than over equal',
        '≧' => 'greater than over equal',
        '≨' => 'less than but not equal',
        '≩' => 'greater than but not equal',
        '≶' => 'less than or greater than',
        '≷' => 'greater than or less than',
        '⋚' => 'less equal greater',
        '⋛' => 'greater equal less',
        '⪋' => 'less double equal greater',
        '⪌' => 'greater double equal less',
        '⋘' => 'very much less than',
        '⋙' => 'very much greater than',
        '⋖' => 'less dot',
        '⋗' => 'greater dot',
        '≥' => 'greater than or equal to',
        '⇔' => 'if and only if',
        '⇐' => 'left double arrow',
        '∈' => 'in',
        '∬' => 'double integral',
        '∭' => 'triple integral',
        '∮' => 'contour integral',
        '∯' => 'surface integral',
        '∰' => 'volume integral',
        '∫' => 'integral',
        '∧' => 'and',
        '≤' => 'less than or equal to',
        '←' => 'left arrow',
        '↔' => 'left right arrow',
        '↖' => 'north west arrow',
        '↗' => 'north east arrow',
        '↘' => 'south east arrow',
        '↙' => 'south west arrow',
        '↞' => 'left two headed arrow',
        '↠' => 'right two headed arrow',
        '↩' => 'left hook arrow',
        '↪' => 'right hook arrow',
        '↼' => 'left harpoon up',
        '↽' => 'left harpoon down',
        '∣' => 'divides',
        '∨' => 'or',
        '¬' => 'not',
        '≠' => 'not equal',
        '∉' => 'not in',
        '≮' => 'not less than',
        '≯' => 'not greater than',
        '≰' => 'not less than or equal to',
        '≱' => 'not greater than or equal to',
        '≉' => 'not approximately equal to',
        '≇' => 'not congruent to',
        '≢' => 'not equivalent',
        '∤' => 'does not divide',
        '∦' => 'not parallel',
        '⊀' => 'does not precede',
        '⋠' => 'does not precede or equal',
        '⊁' => 'does not succeed',
        '⋡' => 'does not succeed or equal',
        '↚' => 'not left arrow',
        '↛' => 'not right arrow',
        '↮' => 'not left right arrow',
        '⇏' => 'not implies',
        '⇀' => 'right harpoon up',
        '⇁' => 'right harpoon down',
        '⇋' => 'left harpoon over right',
        '⇌' => 'right harpoon over left',
        '⊄' => 'not subset',
        '⊈' => 'not subset or equal',
        '⊅' => 'not superset',
        '⊉' => 'not superset or equal',
        '∐' => 'coproduct',
        '⨀' => 'big circled dot',
        '⨁' => 'big circled plus',
        '⨂' => 'big circled times',
        '⨆' => 'big square union',
        '⊕' => 'circled plus',
        '⊖' => 'circled minus',
        '⊗' => 'circled times',
        '⊘' => 'circled slash',
        '⊙' => 'circled dot',
        '∂' => 'partial',
        '±' => 'plus or minus',
        '∓' => 'minus or plus',
        '≪' => 'much less than',
        '≫' => 'much greater than',
        '≺' => 'precedes',
        '≼' => 'precedes or equal',
        '≾' => 'precedes or similar to',
        '≻' => 'succeeds',
        '≽' => 'succeeds or equal',
        '≿' => 'succeeds or similar to',
        '∏' => 'product',
        '→' => 'to',
        '⇒' => 'implies',
        '⟼' => 'long maps to',
        '𝛤' => 'gamma',
        '𝛥' => 'delta',
        '𝛩' => 'theta',
        '𝛬' => 'lambda',
        '𝛯' => 'xi',
        '𝛱' => 'pi',
        '𝛴' => 'sigma',
        '𝛶' => 'upsilon',
        '𝛷' => 'phi',
        '𝛹' => 'psi',
        '𝛺' => 'omega',
        '𝜍' => 'sigma',
        '𝜚' => 'rho',
        "\u{0331}" => 'underbar',
        "\u{0330}" => 'tilde below',
        '_' => 'underbar',
        '∖' => 'set minus',
        '∼' => 'similar to',
        '≲' => 'less than or similar to',
        '≳' => 'greater than or similar to',
        '⪅' => 'less than or approximately equal to',
        '⪆' => 'greater than or approximately equal to',
        '∴' => 'therefore',
        '⇝' => 'right squiggle arrow',
        '⊨' => 'models',
        '⋈' => 'bowtie',
        '⊢' => 'proves',
        '⊣' => 'dashv',
        '⌣' => 'smile',
        '⌢' => 'frown',
        '⊂' => 'subset',
        '⊆' => 'subset or equal',
        '⊊' => 'proper subset',
        '⊃' => 'superset',
        '⊇' => 'superset or equal',
        '⊋' => 'proper superset',
        '⊏' => 'square subset',
        '⊐' => 'square superset',
        '⊑' => 'square subset or equal',
        '⊒' => 'square superset or equal',
        '∑' => 'sum',
        '×' => 'times',
        '△' => 'triangle',
        '□' => 'square',
        '◇' => 'diamond',
        '◊' => 'lozenge',
        '⬧' => 'black lozenge',
        '■' => 'black square',
        '◂' => 'black left triangle',
        '▸' => 'black right triangle',
        '○' => 'circle',
        '⊞' => 'box plus',
        '⊟' => 'box minus',
        '⊠' => 'box times',
        '⊡' => 'box dot',
        '⋞' => 'curly equal precedes',
        '⋟' => 'curly equal succeeds',
        '◁' => 'left triangle',
        '▷' => 'right triangle',
        '⊲' => 'left triangle',
        '⊳' => 'right triangle',
        '⊴' => 'left triangle or equal',
        '⊵' => 'right triangle or equal',
        '⟨' => 'left angle bracket',
        '⟩' => 'right angle bracket',
        '⟮' => 'left group',
        '⟯' => 'right group',
        '⎰' => 'left moustache',
        '⎱' => 'right moustache',
        '⎪' => 'brace extender',
        '‖' => 'double vertical bar',
        '⏜' => 'over parenthesis',
        '⏝' => 'under parenthesis',
        '⏞' => 'over brace',
        '⏟' => 'under brace',
        '⏠' => 'over group',
        '⏡' => 'under group',
        '⎴' => 'over bracket',
        '⎵' => 'under bracket',
        '´' => 'acute',
        '¯' => 'bar',
        '′' => 'prime',
        '″' => 'double prime',
        '‴' => 'triple prime',
        '⁗' => 'quadruple prime',
        '‵' => 'back prime',
        '‾' => 'overline',
        '`' => 'grave',
        '˙' => 'dot',
        '¨' => 'double dot',
        "\u{20DB}" => 'triple dot',
        "\u{20DC}" => 'quadruple dot',
        '˘' => 'breve',
        'ˇ' => 'check',
        '˚' => 'ring',
        '~' => 'tilde',
        'ℵ' => 'aleph',
        'ℓ' => 'ell',
        '℘' => 'Weierstrass p',
        'ℑ' => 'imaginary part',
        'ℜ' => 'real part',
        '∠' => 'angle',
        '∵' => 'because',
        '⊸' => 'multimap',
        '⋔' => 'pitchfork',
        '⤳' => 'leads to',
        '∇' => 'nabla',
        '≅' => 'congruent to',
        '≃' => 'similar or equal to',
        '∝' => 'proportional to',
        '∥' => 'parallel',
        '⊥' => 'perpendicular',
        '⊤' => 'top',
        '⋱' => 'diagonal dots',
    ];

    private int $activeLeftFenceDepth = 0;

    /** @var array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> */
    private array $equationReferenceLabels = [];

    private string $mathChoiceStyle = 'text';

    public function latexFor(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');

        if ($node->attr('display') === true) {
            return '\\[' . $text . '\\]';
        }

        return '$' . $text . '$';
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}> $macros
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     */
    public function mathMlFor(AstNode $node, array $macros = [], array $referenceLabels = []): string
    {
        return $this->texToMathMl((string) $node->attr('text', ''), $node->attr('display') === true, $macros, $referenceLabels);
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}> $macros
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     */
    public function accessibleMathMlFor(AstNode $node, array $macros = [], array $referenceLabels = []): string
    {
        return $this->texToMathMl((string) $node->attr('text', ''), $node->attr('display') === true, $macros, $referenceLabels, true);
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}> $macros
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     */
    public function texToAccessibleMathMl(string $tex, bool $display = false, array $macros = [], array $referenceLabels = []): string
    {
        return $this->texToMathMl($tex, $display, $macros, $referenceLabels, true);
    }

    /**
     * Prototype a TexMath-like atom category stream from the converter's generated MathML.
     *
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}> $macros
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     * @return array{tex:string, display:bool, atomCount:int, atomCategories:list<string>, atomCategoryCounts:array<string, int>, atoms:list<array{category:string, element:string, text:string, source:string, mathClass:?string}>}
     */
    public function texAtomCategorySummary(string $tex, bool $display = false, array $macros = [], array $referenceLabels = []): array
    {
        return $this->mathMlAtomCategorySummary(
            $this->texToMathMl($tex, $display, $macros, $referenceLabels),
            $tex,
            $display
        );
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}> $macros
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     */
    public function texToMathMl(string $tex, bool $display = false, array $macros = [], array $referenceLabels = [], bool $includeAccessibility = false): string
    {
        $previousReferenceLabels = $this->equationReferenceLabels;
        $previousMathChoiceStyle = $this->mathChoiceStyle;
        $this->equationReferenceLabels = $this->normalizeEquationReferenceLabels($referenceLabels);
        $this->mathChoiceStyle = $display ? 'display' : 'text';

        try {
            $definitions = $this->normalizeMacroDefinitions($macros);
            $preprocessedTex = $this->extractRawTexEnvironmentDefinitions($tex, $definitions['environments']);
            $expandedTex = $this->expandRawTexMathMacros($preprocessedTex, $definitions['commands'], $definitions['environments']);
            $equation = $this->extractEquationMetadata($expandedTex);
            $this->activeLeftFenceDepth = 0;
            $offset = 0;
            $children = $this->parseExpression($equation['tex'], $offset, null);
            $this->skipWhitespace($equation['tex'], $offset);

            if ($offset < strlen($equation['tex'])) {
                throw new \InvalidArgumentException('Unsupported TeX token at offset ' . $offset);
            }

            if ($this->activeLeftFenceDepth !== 0) {
                throw new \InvalidArgumentException('Unclosed TeX \\left fence');
            }

            $displayMode = $display ? 'block' : 'inline';
            $body = $this->renderEquationBody($children, $equation);
            $mathAttributes = 'display="' . $displayMode . '"';
            $annotations = '<annotation encoding="application/x-tex">' . $this->esc($tex) . '</annotation>';
            if ($equation['label'] !== null) {
                $annotations .= '<annotation encoding="application/x-tex-label">' . $this->esc($equation['label']) . '</annotation>';
            }
            if ($includeAccessibility) {
                $accessibility = $this->mathMlAccessibilityMetadata($body);
                $mathAttributes .= ' alttext="' . $this->esc($accessibility['alttext']) . '" intent="' . $this->esc($accessibility['intent']) . '"';
                $annotations .= '<annotation encoding="application/x-portlibs-math-alttext">' . $this->esc($accessibility['alttext']) . '</annotation>'
                    . '<annotation encoding="application/x-portlibs-math-intent">' . $this->esc($accessibility['intent']) . '</annotation>';
            }

            return '<math xmlns="http://www.w3.org/1998/Math/MathML" ' . $mathAttributes . '>'
                . '<semantics>'
                . $body
                . $annotations
                . '</semantics>'
                . '</math>';
        } finally {
            $this->equationReferenceLabels = $previousReferenceLabels;
            $this->mathChoiceStyle = $previousMathChoiceStyle;
        }
    }

    /**
     * @return array{alttext:string, intent:string}
     */
    private function mathMlAccessibilityMetadata(string $mathml): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadXML(
            '<math-accessibility-root>' . $mathml . '</math-accessibility-root>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        if (!$loaded || !$dom->documentElement instanceof \DOMElement) {
            throw new \InvalidArgumentException('Generated MathML accessibility handoff is not well-formed');
        }

        $altText = $this->normalizeAccessibilityText($this->mathMlNodeAltText($dom->documentElement));
        $intent = $this->mathMlNodeIntent($dom->documentElement);

        return [
            'alttext' => $altText !== '' ? $altText : 'math expression',
            'intent' => $intent !== '' ? $intent : 'math',
        ];
    }

    /**
     * @return array{tex:string, display:bool, atomCount:int, atomCategories:list<string>, atomCategoryCounts:array<string, int>, atoms:list<array{category:string, element:string, text:string, source:string, mathClass:?string}>}
     */
    private function mathMlAtomCategorySummary(string $mathml, string $tex, bool $display): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadXML($mathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$loaded || !$dom->documentElement instanceof \DOMElement) {
            throw new \InvalidArgumentException('Generated MathML atom handoff is not well-formed');
        }

        $atoms = [];
        $this->collectMathMlAtomCategories($dom->documentElement, $atoms);
        $counts = [];
        foreach ($atoms as $atom) {
            $category = $atom['category'];
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        $categories = [];
        foreach (self::MATH_ATOM_CATEGORY_ORDER as $category) {
            if (isset($counts[$category])) {
                $categories[] = $category;
            }
        }
        foreach (array_keys($counts) as $category) {
            if (!in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }

        $orderedCounts = [];
        foreach ($categories as $category) {
            $orderedCounts[$category] = $counts[$category];
        }

        return [
            'tex' => $tex,
            'display' => $display,
            'atomCount' => count($atoms),
            'atomCategories' => $categories,
            'atomCategoryCounts' => $orderedCounts,
            'atoms' => $atoms,
        ];
    }

    /**
     * @param list<array{category:string, element:string, text:string, source:string, mathClass:?string}> $atoms
     */
    private function collectMathMlAtomCategories(\DOMNode $node, array &$atoms): void
    {
        if (!$node instanceof \DOMElement) {
            foreach ($node->childNodes as $child) {
                $this->collectMathMlAtomCategories($child, $atoms);
            }

            return;
        }

        if ($node->localName === 'annotation' || $node->localName === 'annotation-xml') {
            return;
        }

        $mathClass = $node->getAttribute('data-tex-math-class');
        if ($mathClass !== '') {
            $category = self::MATH_CLASS_ATOM_CATEGORIES[$mathClass] ?? null;
            if ($category !== null) {
                $atoms[] = [
                    'category' => $category,
                    'element' => $node->localName,
                    'text' => $this->mathMlAtomText($node),
                    'source' => 'explicit-math-class',
                    'mathClass' => $mathClass,
                ];
            }

            return;
        }

        $category = $this->mathMlTokenAtomCategory($node);
        if ($category !== null) {
            $atoms[] = [
                'category' => $category,
                'element' => $node->localName,
                'text' => $this->mathMlAtomText($node),
                'source' => 'mathml-token',
                'mathClass' => null,
            ];

            return;
        }

        foreach ($node->childNodes as $child) {
            $this->collectMathMlAtomCategories($child, $atoms);
        }
    }

    private function mathMlTokenAtomCategory(\DOMElement $node): ?string
    {
        $name = $node->localName;
        $text = $this->mathMlAtomText($node);

        if ($name === 'mn' || $name === 'mtext') {
            return 'Ord';
        }

        if ($name === 'mi') {
            return $this->mathMlIdentifierAtomCategory($text);
        }

        if ($name !== 'mo') {
            return null;
        }

        if ($node->getAttribute('separator') === 'true') {
            return 'Pun';
        }

        if ($node->getAttribute('fence') === 'true') {
            if (isset(self::MATH_CLOSE_ATOM_TOKENS[$text])) {
                return 'Close';
            }

            return 'Open';
        }

        if (isset(self::MATH_PUNCTUATION_ATOM_TOKENS[$text])) {
            return 'Pun';
        }

        if (isset(self::MATH_RELATION_ATOM_TOKENS[$text])) {
            return 'Rel';
        }

        if (isset(self::MATH_BINARY_ATOM_TOKENS[$text])) {
            return 'Bin';
        }

        if (isset(self::MATH_OPERATOR_ATOM_TOKENS[$text])) {
            return 'Op';
        }

        if (isset(self::MATH_OPEN_ATOM_TOKENS[$text])) {
            return 'Open';
        }

        if (isset(self::MATH_CLOSE_ATOM_TOKENS[$text])) {
            return 'Close';
        }

        return 'Op';
    }

    private function mathMlIdentifierAtomCategory(string $text): string
    {
        if (in_array($text, self::FUNCTION_COMMANDS, true)) {
            return 'Op';
        }

        return $this->singleUtf8Codepoint($text) === null ? 'Op' : 'Ord';
    }

    private function mathMlAtomText(\DOMNode $node): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($node->textContent));

        return is_string($text) ? $text : trim($node->textContent);
    }

    private function mathMlNodeAltText(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return $node->wholeText;
        }

        if (!$node instanceof \DOMElement) {
            return $this->joinAccessibilityText($this->mathMlChildAltTexts($node));
        }

        $name = $node->localName;
        if ($name === 'mi' || $name === 'mn' || $name === 'mo' || $name === 'mtext') {
            return $this->accessibilityTokenText($node->textContent);
        }

        $children = $this->mathMlElementChildren($node);

        return match ($name) {
            'mfrac' => 'fraction ' . $this->mathMlChildAltText($children, 0) . ' over ' . $this->mathMlChildAltText($children, 1),
            'msqrt' => 'square root of ' . $this->joinAccessibilityText($this->mathMlChildAltTexts($node)),
            'mroot' => $this->mathMlChildAltText($children, 1) . ' root of ' . $this->mathMlChildAltText($children, 0),
            'msub' => $this->mathMlChildAltText($children, 0) . ' sub ' . $this->mathMlChildAltText($children, 1),
            'msup' => $this->mathMlChildAltText($children, 0) . ' superscript ' . $this->mathMlChildAltText($children, 1),
            'msubsup' => $this->mathMlChildAltText($children, 0) . ' sub ' . $this->mathMlChildAltText($children, 1) . ' superscript ' . $this->mathMlChildAltText($children, 2),
            'munder' => $this->mathMlChildAltText($children, 0) . ' under ' . $this->mathMlChildAltText($children, 1),
            'mover' => $this->mathMlChildAltText($children, 0) . ' over ' . $this->mathMlChildAltText($children, 1),
            'munderover' => $this->mathMlChildAltText($children, 0) . ' under ' . $this->mathMlChildAltText($children, 1) . ' over ' . $this->mathMlChildAltText($children, 2),
            'mmultiscripts' => $this->mathMlMultiScriptsAltText($children),
            'mtable' => 'table ' . implode('; ', array_map(fn (\DOMElement $child): string => $this->mathMlNodeAltText($child), $children)),
            'mtr', 'mlabeledtr' => 'row ' . implode(', ', array_map(fn (\DOMElement $child): string => $this->mathMlNodeAltText($child), $children)),
            'mtd' => $this->joinAccessibilityText($this->mathMlChildAltTexts($node)),
            'mspace' => 'space',
            'menclose' => 'enclosed ' . $this->joinAccessibilityText($this->mathMlChildAltTexts($node)),
            'annotation', 'annotation-xml' => '',
            default => $this->joinAccessibilityText($this->mathMlChildAltTexts($node)),
        };
    }

    private function mathMlNodeIntent(\DOMNode $node): string
    {
        if (!$node instanceof \DOMElement) {
            return $this->joinIntentText($this->mathMlChildIntents($node));
        }

        $name = $node->localName;
        if ($name === 'mi' || $name === 'mn' || $name === 'mo' || $name === 'mtext') {
            return $this->accessibilityIntentToken($node->textContent);
        }

        $children = $this->mathMlElementChildren($node);

        return match ($name) {
            'mfrac' => $this->intentCall('fraction', $children),
            'msqrt' => $this->intentCall('sqrt', $children),
            'mroot' => $this->intentCall('root', $children),
            'msub' => $this->intentCall('subscript', $children),
            'msup' => $this->intentCall('superscript', $children),
            'msubsup' => $this->intentCall('subsup', $children),
            'munder' => $this->intentCall('under', $children),
            'mover' => $this->intentCall('over', $children),
            'munderover' => $this->intentCall('underover', $children),
            'mmultiscripts' => $this->mathMlMultiScriptsIntent($children),
            'mtable' => $this->intentCall('table', $children),
            'mtr', 'mlabeledtr' => $this->intentCall('row', $children),
            'mtd' => $this->joinIntentText($this->mathMlChildIntents($node)),
            'mspace' => 'space',
            'menclose' => $this->intentCall('enclose', $children),
            'annotation', 'annotation-xml' => '',
            default => $this->intentRow($this->mathMlChildIntents($node)),
        };
    }

    /**
     * @return list<\DOMElement>
     */
    private function mathMlElementChildren(\DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @param list<\DOMElement> $children
     */
    private function mathMlMultiScriptsAltText(array $children): string
    {
        if (!isset($children[0])) {
            return '';
        }

        $parts = [$this->normalizeAccessibilityText($this->mathMlNodeAltText($children[0]))];
        foreach ($this->mathMlMultiScriptParts($children) as $part) {
            if ($part['text'] !== '') {
                $parts[] = $part['position'] . ' ' . $part['text'];
            }
        }

        return $this->joinAccessibilityText($parts);
    }

    /**
     * @param list<\DOMElement> $children
     */
    private function mathMlMultiScriptsIntent(array $children): string
    {
        if (!isset($children[0])) {
            return '';
        }

        $parts = [$this->mathMlNodeIntent($children[0])];
        foreach ($this->mathMlMultiScriptParts($children) as $part) {
            if ($part['intent'] !== '') {
                $parts[] = str_replace('-', '', $part['position']) . '(' . $part['intent'] . ')';
            }
        }

        return 'multiscripts(' . implode(',', array_filter($parts, static fn (string $part): bool => $part !== '')) . ')';
    }

    /**
     * @param list<\DOMElement> $children
     * @return list<array{position:string, text:string, intent:string}>
     */
    private function mathMlMultiScriptParts(array $children): array
    {
        $parts = [];
        $phase = 'post';
        for ($index = 1; $index < count($children);) {
            $child = $children[$index];
            if ($child->localName === 'mprescripts') {
                $phase = 'pre';
                $index++;
                continue;
            }

            $subscript = $children[$index] ?? null;
            $superscript = $children[$index + 1] ?? null;
            $index += 2;

            if ($subscript instanceof \DOMElement && $subscript->localName !== 'none') {
                $parts[] = [
                    'position' => $phase . '-sub',
                    'text' => $this->normalizeAccessibilityText($this->mathMlNodeAltText($subscript)),
                    'intent' => $this->mathMlNodeIntent($subscript),
                ];
            }

            if ($superscript instanceof \DOMElement && $superscript->localName !== 'none') {
                $parts[] = [
                    'position' => $phase . '-sup',
                    'text' => $this->normalizeAccessibilityText($this->mathMlNodeAltText($superscript)),
                    'intent' => $this->mathMlNodeIntent($superscript),
                ];
            }
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function mathMlChildAltTexts(\DOMNode $node): array
    {
        $texts = [];
        foreach ($node->childNodes as $child) {
            $text = $this->normalizeAccessibilityText($this->mathMlNodeAltText($child));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @return list<string>
     */
    private function mathMlChildIntents(\DOMNode $node): array
    {
        $intents = [];
        foreach ($node->childNodes as $child) {
            $intent = $this->mathMlNodeIntent($child);
            if ($intent !== '') {
                $intents[] = $intent;
            }
        }

        return $intents;
    }

    /**
     * @param list<\DOMElement> $children
     */
    private function mathMlChildAltText(array $children, int $index): string
    {
        return isset($children[$index]) ? $this->normalizeAccessibilityText($this->mathMlNodeAltText($children[$index])) : '';
    }

    /**
     * @param list<\DOMElement> $children
     */
    private function intentCall(string $name, array $children): string
    {
        return $name . '(' . implode(',', $this->mathMlElementChildIntents($children)) . ')';
    }

    /**
     * @param list<\DOMElement> $children
     * @return list<string>
     */
    private function mathMlElementChildIntents(array $children): array
    {
        $intents = [];
        foreach ($children as $child) {
            $intent = $this->mathMlNodeIntent($child);
            if ($intent !== '') {
                $intents[] = $intent;
            }
        }

        return $intents;
    }

    /**
     * @param list<string> $intents
     */
    private function intentRow(array $intents): string
    {
        if (count($intents) === 0) {
            return '';
        }

        if (count($intents) === 1) {
            return $intents[0];
        }

        return 'row(' . implode(',', $intents) . ')';
    }

    /**
     * @param list<string> $parts
     */
    private function joinAccessibilityText(array $parts): string
    {
        return $this->normalizeAccessibilityText(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param list<string> $parts
     */
    private function joinIntentText(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function accessibilityTokenText(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $mathVariantText = $this->mathVariantAccessibilityText($token);
        if ($mathVariantText !== null) {
            return $mathVariantText;
        }

        return self::ACCESSIBILITY_TOKEN_TEXT[$token] ?? $token;
    }

    private function accessibilityIntentToken(string $token): string
    {
        $text = $this->accessibilityTokenText($token);
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'token';
    }

    private function normalizeAccessibilityText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return $text;
    }

    /**
     * @return array{tex:string, label:?string, labelId:?string, tag:?string, tagStarred:bool, suppressNumbering:bool}
     */
    private function extractEquationMetadata(string $source): array
    {
        $output = '';
        $label = null;
        $labelId = null;
        $tag = null;
        $tagStarred = false;
        $suppressNumbering = false;
        $depth = 0;
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $command = $this->readCommandName($source, $commandOffset);
                if ($depth === 0 && $command === 'begin') {
                    $environmentOffset = $commandOffset;
                    $environment = $this->readRequiredGroupText($source, $environmentOffset);
                    $this->readEnvironmentContent($source, $environmentOffset, $environment);
                    $output .= substr($source, $offset, $environmentOffset - $offset);
                    $offset = $environmentOffset;
                    continue;
                }

                if ($depth === 0 && ($command === 'notag' || $command === 'nonumber')) {
                    $suppressNumbering = true;
                    $offset = $commandOffset;
                    continue;
                }

                if ($depth === 0 && ($command === 'label' || $command === 'tag')) {
                    $cursor = $commandOffset;
                    $starred = false;
                    if ($command === 'tag' && ($source[$cursor] ?? '') === '*') {
                        $starred = true;
                        $cursor++;
                    }

                    $this->skipWhitespace($source, $cursor);
                    $argument = $this->readTexBraceArgument($source, $cursor);
                    if ($argument === null) {
                        throw new \InvalidArgumentException('Expected TeX \\' . $command . ' group at offset ' . $cursor);
                    }

                    $value = trim($argument['value']);
                    if ($value === '') {
                        throw new \InvalidArgumentException('Expected TeX \\' . $command . ' content at offset ' . $cursor);
                    }

                    if ($command === 'label') {
                        if ($label !== null) {
                            throw new \InvalidArgumentException('Duplicate TeX equation label at offset ' . $offset);
                        }

                        $label = $value;
                        $labelId = $this->normalizeEquationLabelId($value);
                    } else {
                        if ($tag !== null) {
                            throw new \InvalidArgumentException('Duplicate TeX equation tag at offset ' . $offset);
                        }

                        $tag = $value;
                        $tagStarred = $starred;
                    }

                    $offset = $argument['next'];
                    continue;
                }

                $output .= $char;
                $offset++;
                if (($source[$offset] ?? '') !== '' && !ctype_alpha($source[$offset])) {
                    $output .= $source[$offset];
                    $offset++;
                }
                continue;
            }

            if ($char === '{') {
                $depth++;
                $output .= $char;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $output .= $char;
                $offset++;
                continue;
            }

            $output .= $char;
            $offset++;
        }

        if (($label !== null || $tag !== null) && trim($output) === '') {
            throw new \InvalidArgumentException('Expected TeX math content before equation metadata');
        }

        return [
            'tex' => $output,
            'label' => $label,
            'labelId' => $labelId,
            'tag' => $tag,
            'tagStarred' => $tagStarred,
            'suppressNumbering' => $suppressNumbering,
        ];
    }

    private function normalizeEquationLabelId(string $label): string
    {
        $id = preg_replace('/[^A-Za-z0-9_.:-]+/', '-', trim($label)) ?? '';
        $id = trim($id, '-');
        if ($id === '') {
            throw new \InvalidArgumentException('Unsupported TeX equation label ' . $label);
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $id) !== 1) {
            $id = 'math-' . $id;
        }

        return $id;
    }

    /**
     * @param list<string> $children
     * @param array{tex:string, label:?string, labelId:?string, tag:?string, tagStarred:bool, suppressNumbering?:bool} $equation
     */
    private function renderEquationBody(array $children, array $equation): string
    {
        $body = $this->stripTransientMathMlMetadata($this->row($children));
        if ($equation['tag'] !== null) {
            $tagText = $equation['tagStarred'] ? $equation['tag'] : '(' . $equation['tag'] . ')';
            $labelAttribute = $equation['labelId'] !== null ? ' id="' . $this->esc($equation['labelId']) . '"' : '';

            return '<mtable><mlabeledtr>'
                . '<mtd><mtext>' . $this->esc($tagText) . '</mtext></mtd>'
                . '<mtd' . $labelAttribute . '>' . $body . '</mtd>'
                . '</mlabeledtr></mtable>';
        }

        if ($equation['labelId'] !== null) {
            return $this->withMathMlId($body, $equation['labelId']);
        }

        return $body;
    }

    private function stripTransientMathMlMetadata(string $mathml): string
    {
        return str_replace(' ' . self::TEX_FUNCTION_OPERATOR_ATTRIBUTE, '', $mathml);
    }

    private function withMathMlId(string $mathml, string $id): string
    {
        $withId = preg_replace('/^<([A-Za-z][A-Za-z0-9]*)(?=[\s>])/', '<$1 id="' . $this->esc($id) . '"', $mathml, 1);
        if (is_string($withId)) {
            return $withId;
        }

        return '<mrow id="' . $this->esc($id) . '">' . $mathml . '</mrow>';
    }

    /**
     * @return array<string, array{arity:int, template?:string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}>
     */
    public function macroDefinitionsFromDocument(AstNode $node): array
    {
        $macros = [];
        $this->collectMacroDefinitions($node, $macros);

        return $macros;
    }

    /**
     * @return array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}>
     */
    public function equationReferenceLabelsFromDocument(AstNode $node): array
    {
        $labels = [];
        $nextAutomaticNumber = 1;
        $this->collectEquationReferenceLabelsFromDocument($node, $labels, $nextAutomaticNumber);

        return $labels;
    }

    /**
     * @param array<string, array{arity:int, template?:string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}> $macros
     */
    private function collectMacroDefinitions(AstNode $node, array &$macros): void
    {
        if ($node->type === 'raw_tex') {
            $macro = $this->readRawTexMacroDefinition((string) $node->attr('tex', ''));
            if ($macro !== null) {
                if (($macro['environment'] ?? false) === true) {
                    $definition = [
                        'environment' => true,
                        'arity' => $macro['arity'],
                        'opener' => $macro['opener'],
                        'closer' => $macro['closer'],
                    ];
                } else {
                    $definition = [
                        'arity' => $macro['arity'],
                        'template' => $macro['template'],
                    ];
                }
                if (array_key_exists('optionalDefault', $macro)) {
                    $definition['optionalDefault'] = $macro['optionalDefault'];
                }

                $macros[$macro['name']] = $definition;
            }
        }

        foreach ($node->children as $child) {
            $this->collectMacroDefinitions($child, $macros);
        }
    }

    /**
     * @return array{name:string, arity:int, template?:string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}|null
     */
    private function readRawTexMacroDefinition(string $tex): ?array
    {
        $source = trim($tex);
        $environment = $this->readRawTexEnvironmentDefinition($source);
        if ($environment !== null) {
            return $environment;
        }

        $declaredOperator = $this->readRawTexDeclaredMathOperator($source);
        if ($declaredOperator !== null) {
            return $declaredOperator;
        }

        $pairedDelimiterXpp = $this->readRawTexDeclaredPairedDelimiterXpp($source);
        if ($pairedDelimiterXpp !== null) {
            return $pairedDelimiterXpp;
        }

        $pairedDelimiterX = $this->readRawTexDeclaredPairedDelimiterX($source);
        if ($pairedDelimiterX !== null) {
            return $pairedDelimiterX;
        }

        $pairedDelimiter = $this->readRawTexDeclaredPairedDelimiter($source);
        if ($pairedDelimiter !== null) {
            return $pairedDelimiter;
        }

        if (preg_match('/^\\\\(?:(?:re)?newcommand|providecommand)/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $this->skipWhitespace($source, $offset);
        $name = $this->readTexBraceArgument($source, $offset);
        if ($name === null || preg_match('/^\\\\([A-Za-z]+)$/', $name['value'], $nameMatch) !== 1) {
            return null;
        }
        $offset = $name['next'];

        $arity = null;
        $optionalDefault = null;
        $this->skipWhitespace($source, $offset);
        $arityArgument = $this->readTexBracketArgument($source, $offset);
        if ($arityArgument !== null) {
            if (preg_match('/^[0-9]$/', trim($arityArgument['value'])) !== 1) {
                return null;
            }

            $arity = (int) trim($arityArgument['value']);
            $offset = $arityArgument['next'];
            $defaultArgument = $this->readTexBracketArgument($source, $offset);
            if ($defaultArgument !== null) {
                $optionalDefault = $defaultArgument['value'];
                $offset = $defaultArgument['next'];
            }
        }

        $this->skipWhitespace($source, $offset);
        $template = $this->readTexBraceArgument($source, $offset);
        if ($template === null) {
            return null;
        }
        $offset = $template['next'];
        $this->skipWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        $definition = [
            'name' => $nameMatch[1],
            'arity' => $arity ?? $this->inferMacroArity($template['value']),
            'template' => $template['value'],
        ];
        if ($optionalDefault !== null) {
            if ($definition['arity'] < 1) {
                return null;
            }

            $definition['optionalDefault'] = $optionalDefault;
        }

        return $definition;
    }

    /**
     * @return array{name:string, arity:int, optionalDefault?: string, environment:bool, opener:string, closer:string}|null
     */
    private function readRawTexEnvironmentDefinition(string $source): ?array
    {
        $parsed = $this->readRawTexEnvironmentDefinitionAt($source, 0);
        if ($parsed === null) {
            return null;
        }

        $offset = $parsed['next'];
        $this->skipWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        return $parsed['definition'];
    }

    /**
     * @return array{definition:array{name:string, arity:int, optionalDefault?: string, environment:bool, opener:string, closer:string}, next:int}|null
     */
    private function readRawTexEnvironmentDefinitionAt(string $source, int $offset): ?array
    {
        if (preg_match('/\G\\\\((?:re)?newenvironment)/', $source, $m, 0, $offset) !== 1) {
            return null;
        }

        $cursor = $offset + strlen($m[0]);
        $this->skipWhitespace($source, $cursor);
        if (($source[$cursor] ?? '') === '*') {
            $cursor++;
        }

        $this->skipWhitespace($source, $cursor);
        $name = $this->readTexBraceArgument($source, $cursor);
        if ($name === null) {
            return null;
        }
        $environment = trim($name['value']);
        if (preg_match('/^[A-Za-z][A-Za-z0-9*_-]*$/', $environment) !== 1) {
            return null;
        }
        $cursor = $name['next'];

        $arity = 0;
        $optionalDefault = null;
        $this->skipWhitespace($source, $cursor);
        $arityArgument = $this->readTexBracketArgument($source, $cursor);
        if ($arityArgument !== null) {
            if (preg_match('/^[0-9]$/', trim($arityArgument['value'])) !== 1) {
                return null;
            }

            $arity = (int) trim($arityArgument['value']);
            $cursor = $arityArgument['next'];
            if ($arity > 0) {
                $this->skipWhitespace($source, $cursor);
                $defaultArgument = $this->readTexBracketArgument($source, $cursor);
                if ($defaultArgument !== null) {
                    $optionalDefault = $defaultArgument['value'];
                    $cursor = $defaultArgument['next'];
                }
            }
        }

        $this->skipWhitespace($source, $cursor);
        $opener = $this->readTexBraceArgument($source, $cursor);
        if ($opener === null) {
            return null;
        }
        $cursor = $opener['next'];

        $this->skipWhitespace($source, $cursor);
        $closer = $this->readTexBraceArgument($source, $cursor);
        if ($closer === null) {
            return null;
        }
        $cursor = $closer['next'];

        $definition = [
            'name' => $environment,
            'environment' => true,
            'arity' => $arity,
            'opener' => $opener['value'],
            'closer' => $closer['value'],
        ];
        if ($optionalDefault !== null) {
            $definition['optionalDefault'] = $optionalDefault;
        }

        return [
            'definition' => $definition,
            'next' => $cursor,
        ];
    }

    /**
     * @return array{name:string, arity:int, template:string}|null
     */
    private function readRawTexDeclaredMathOperator(string $source): ?array
    {
        if (preg_match('/^\\\\DeclareMathOperator(\*)?/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $this->skipWhitespace($source, $offset);
        $name = $this->readTexBraceArgument($source, $offset);
        if ($name === null || preg_match('/^\\\\([A-Za-z]+)$/', trim($name['value']), $nameMatch) !== 1) {
            throw new \InvalidArgumentException('Expected TeX declared math operator macro name at offset ' . $offset);
        }
        $offset = $name['next'];

        $this->skipWhitespace($source, $offset);
        $operatorName = $this->readTexBraceArgument($source, $offset);
        if ($operatorName === null) {
            throw new \InvalidArgumentException('Expected TeX declared math operator name at offset ' . $offset);
        }
        $offset = $operatorName['next'];

        $this->skipWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            throw new \InvalidArgumentException('Unexpected TeX declared math operator trailing content at offset ' . $offset);
        }

        $normalizedOperatorName = $this->normalizeMathOperatorNameText($operatorName['value']);
        if ($normalizedOperatorName === '') {
            throw new \InvalidArgumentException('Expected non-empty TeX declared math operator name');
        }

        return [
            'name' => $nameMatch[1],
            'arity' => 0,
            'template' => '\\operatorname' . (($m[1] ?? '') === '*' ? '*' : '') . '{' . $normalizedOperatorName . '}',
        ];
    }

    /**
     * @return array{name:string, arity:int, template:string}|null
     */
    private function readRawTexDeclaredPairedDelimiter(string $source): ?array
    {
        if (preg_match('/^\\\\DeclarePairedDelimiter(?![A-Za-z])/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $name = $this->readRawTexMacroNameReference($source, $offset, 'paired delimiter');

        $this->skipWhitespace($source, $offset);
        $openDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($openDelimiter === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter opening delimiter at offset ' . $offset);
        }
        $offset = $openDelimiter['next'];

        $this->skipWhitespace($source, $offset);
        $closeDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($closeDelimiter === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter closing delimiter at offset ' . $offset);
        }
        $offset = $closeDelimiter['next'];

        $this->skipWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            throw new \InvalidArgumentException('Unexpected TeX paired delimiter trailing content at offset ' . $offset);
        }

        return [
            'name' => $name,
            'arity' => 1,
            'template' => '\\left' . $this->normalizePairedDelimiterSource($openDelimiter['value'], 'opening')
                . ' #1 \\right' . $this->normalizePairedDelimiterSource($closeDelimiter['value'], 'closing'),
        ];
    }

    /**
     * @return array{name:string, arity:int, template:string}|null
     */
    private function readRawTexDeclaredPairedDelimiterX(string $source): ?array
    {
        if (preg_match('/^\\\\DeclarePairedDelimiterX(?![A-Za-z])/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $name = $this->readRawTexMacroNameReference($source, $offset, 'paired delimiter X');

        $declaredArity = null;
        $this->skipWhitespace($source, $offset);
        $arityArgument = $this->readTexBracketArgument($source, $offset);
        if ($arityArgument !== null) {
            if (preg_match('/^[1-9]$/', trim($arityArgument['value'])) !== 1) {
                throw new \InvalidArgumentException('Expected TeX paired delimiter X arity from 1 through 9 at offset ' . $offset);
            }

            $declaredArity = (int) trim($arityArgument['value']);
            $offset = $arityArgument['next'];
        }

        $this->skipWhitespace($source, $offset);
        $openDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($openDelimiter === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter X opening delimiter at offset ' . $offset);
        }
        $offset = $openDelimiter['next'];

        $this->skipWhitespace($source, $offset);
        $closeDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($closeDelimiter === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter X closing delimiter at offset ' . $offset);
        }
        $offset = $closeDelimiter['next'];

        $this->skipWhitespace($source, $offset);
        $body = $this->readTexBraceArgument($source, $offset);
        if ($body === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter X body template at offset ' . $offset);
        }
        $offset = $body['next'];

        $this->skipWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            throw new \InvalidArgumentException('Unexpected TeX paired delimiter X trailing content at offset ' . $offset);
        }

        $normalizedBody = $this->normalizePairedDelimiterBodyTemplate($body['value'], $declaredArity, $name);

        return [
            'name' => $name,
            'arity' => $normalizedBody['arity'],
            'template' => '\\left' . $this->normalizePairedDelimiterSource($openDelimiter['value'], 'opening')
                . ' ' . $normalizedBody['template'] . ' \\right' . $this->normalizePairedDelimiterSource($closeDelimiter['value'], 'closing'),
        ];
    }

    /**
     * @return array{name:string, arity:int, template:string}|null
     */
    private function readRawTexDeclaredPairedDelimiterXpp(string $source): ?array
    {
        if (preg_match('/^\\\\DeclarePairedDelimiterXPP(?![A-Za-z])/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $name = $this->readRawTexMacroNameReference($source, $offset, 'paired delimiter XPP');

        $declaredArity = null;
        $this->skipWhitespace($source, $offset);
        $arityArgument = $this->readTexBracketArgument($source, $offset);
        if ($arityArgument !== null) {
            if (preg_match('/^[1-9]$/', trim($arityArgument['value'])) !== 1) {
                throw new \InvalidArgumentException('Expected TeX paired delimiter XPP arity from 1 through 9 at offset ' . $offset);
            }

            $declaredArity = (int) trim($arityArgument['value']);
            $offset = $arityArgument['next'];
        }

        $this->skipWhitespace($source, $offset);
        $prefix = $this->readTexBraceArgument($source, $offset);
        if ($prefix === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter XPP prefix template at offset ' . $offset);
        }
        $offset = $prefix['next'];

        $this->skipWhitespace($source, $offset);
        $openDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($openDelimiter === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter XPP opening delimiter at offset ' . $offset);
        }
        $offset = $openDelimiter['next'];

        $this->skipWhitespace($source, $offset);
        $closeDelimiter = $this->readTexBraceArgument($source, $offset);
        if ($closeDelimiter === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter XPP closing delimiter at offset ' . $offset);
        }
        $offset = $closeDelimiter['next'];

        $this->skipWhitespace($source, $offset);
        $suffix = $this->readTexBraceArgument($source, $offset);
        if ($suffix === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter XPP suffix template at offset ' . $offset);
        }
        $offset = $suffix['next'];

        $this->skipWhitespace($source, $offset);
        $body = $this->readTexBraceArgument($source, $offset);
        if ($body === null) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter XPP body template at offset ' . $offset);
        }
        $offset = $body['next'];

        $this->skipWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            throw new \InvalidArgumentException('Unexpected TeX paired delimiter XPP trailing content at offset ' . $offset);
        }

        $normalizedPrefix = $this->normalizePairedDelimiterAffixTemplate($prefix['value'], $name, 'prefix');
        $normalizedBody = $this->normalizePairedDelimiterBodyTemplate($body['value'], $declaredArity, $name);
        $normalizedSuffix = $this->normalizePairedDelimiterAffixTemplate($suffix['value'], $name, 'suffix');

        $template = '';
        if ($normalizedPrefix !== '') {
            $template .= $normalizedPrefix . ' ';
        }
        $template .= '\\left' . $this->normalizePairedDelimiterSource($openDelimiter['value'], 'opening')
            . ' ' . $normalizedBody['template'] . ' \\right' . $this->normalizePairedDelimiterSource($closeDelimiter['value'], 'closing');
        if ($normalizedSuffix !== '') {
            $template .= ' ' . $normalizedSuffix;
        }

        return [
            'name' => $name,
            'arity' => $normalizedBody['arity'],
            'template' => $template,
        ];
    }

    private function readRawTexMacroNameReference(string $source, int &$offset, string $label): string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '{') {
            $name = $this->readTexBraceArgument($source, $offset);
            if ($name === null || preg_match('/^\\\\([A-Za-z]+)$/', trim($name['value']), $nameMatch) !== 1) {
                throw new \InvalidArgumentException('Expected TeX ' . $label . ' macro name at offset ' . $offset);
            }

            $offset = $name['next'];

            return $nameMatch[1];
        }

        if (($source[$offset] ?? '') !== '\\') {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' macro name at offset ' . $offset);
        }

        $offset++;
        $name = $this->readCommandName($source, $offset);
        if (preg_match('/^[A-Za-z]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' macro name at offset ' . $offset);
        }

        return $name;
    }

    private function normalizePairedDelimiterSource(string $delimiter, string $side): string
    {
        $delimiter = trim($delimiter);
        if ($delimiter === '') {
            throw new \InvalidArgumentException('Expected TeX paired delimiter ' . $side . ' delimiter');
        }

        if ($delimiter === '.') {
            return '.';
        }

        if (strlen($delimiter) === 1 && str_contains('()[]|/<>', $delimiter)) {
            return $delimiter;
        }

        if (($delimiter[0] ?? '') === '\\') {
            $offset = 1;
            $command = $this->readCommandName($delimiter, $offset);
            $this->skipWhitespace($delimiter, $offset);
            if ($offset === strlen($delimiter) && isset(self::DELIMITER_COMMANDS[$command])) {
                return '\\' . $command;
            }
        }

        throw new \InvalidArgumentException('Unsupported TeX paired delimiter ' . $side . ' delimiter ' . $delimiter);
    }

    /**
     * @return array{arity:int, template:string}
     */
    private function normalizePairedDelimiterBodyTemplate(string $template, ?int $declaredArity, string $name): array
    {
        $template = trim($template);
        if ($template === '') {
            throw new \InvalidArgumentException('Expected non-empty TeX paired delimiter X body for \\' . $name);
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $template) === 1) {
            throw new \InvalidArgumentException('Unsupported TeX paired delimiter X body control character for \\' . $name);
        }

        if (preg_match('/#(?:$|0|[^1-9])/', $template) === 1) {
            throw new \InvalidArgumentException('Unsupported TeX paired delimiter X placeholder for \\' . $name);
        }

        $maxPlaceholder = $this->inferMacroArity($template);
        if ($maxPlaceholder < 1) {
            throw new \InvalidArgumentException('Expected TeX paired delimiter X body placeholder for \\' . $name);
        }

        $arity = $declaredArity ?? $maxPlaceholder;
        if ($arity < $maxPlaceholder) {
            throw new \InvalidArgumentException('TeX paired delimiter X body references argument #' . $maxPlaceholder . ' beyond declared arity for \\' . $name);
        }

        return [
            'arity' => $arity,
            'template' => $template,
        ];
    }

    private function normalizePairedDelimiterAffixTemplate(string $template, string $name, string $label): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $template) === 1) {
            throw new \InvalidArgumentException('Unsupported TeX paired delimiter XPP ' . $label . ' control character for \\' . $name);
        }

        if (str_contains($template, '#')) {
            throw new \InvalidArgumentException('Unsupported TeX paired delimiter XPP ' . $label . ' placeholder for \\' . $name);
        }

        return $template;
    }

    private function normalizeMathOperatorNameText(string $text): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($text);
        while ($offset < $length) {
            $char = $text[$offset];
            if ($char !== '\\') {
                if ($char === '{' || $char === '}') {
                    throw new \InvalidArgumentException('Unsupported TeX math operator name grouping');
                }

                $output .= $char;
                $offset++;
                continue;
            }

            $offset++;
            $escaped = $text[$offset] ?? '';
            if ($escaped === '') {
                throw new \InvalidArgumentException('Unsupported TeX math operator name escape');
            }

            if (in_array($escaped, [',', ':', ';', ' ', '!', '>'], true)) {
                $output .= ' ';
                $offset++;
                continue;
            }

            if (in_array($escaped, ['&', '%', '$', '#', '_', '{', '}'], true)) {
                $output .= $escaped;
                $offset++;
                continue;
            }

            if (ctype_alpha($escaped)) {
                $commandStart = $offset;
                while ($offset < $length && ctype_alpha($text[$offset])) {
                    $offset++;
                }
                $command = substr($text, $commandStart, $offset - $commandStart);
                $output .= match ($command) {
                    'thinspace', 'medspace', 'thickspace', 'quad', 'qquad', 'enspace',
                    'negthinspace', 'negmedspace', 'negthickspace' => ' ',
                    'dots', 'ldots' => '...',
                    'TeX' => 'TeX',
                    'LaTeX' => 'LaTeX',
                    default => throw new \InvalidArgumentException('Unsupported TeX math operator name command \\' . $command),
                };
                continue;
            }

            throw new \InvalidArgumentException('Unsupported TeX math operator name escape \\' . $escaped);
        }

        $normalized = preg_replace('/\s+/', ' ', trim($output));
        if (!is_string($normalized) || $normalized === '') {
            return '';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1) {
            throw new \InvalidArgumentException('Unsupported TeX math operator name control character');
        }

        return $normalized;
    }

    private function inferMacroArity(string $template): int
    {
        if (preg_match_all('/#([1-9])/', $template, $m) !== false && $m[1] !== []) {
            return max(array_map('intval', $m[1]));
        }

        return 0;
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string, environment?: bool, opener?: string, closer?: string}> $macros
     * @return array{commands:array<string, array{arity:int, template:string, optionalDefault?: string}>, environments:array<string, array{arity:int, opener:string, closer:string, optionalDefault?: string}>}
     */
    private function normalizeMacroDefinitions(array $macros): array
    {
        $commands = [];
        $environments = [];
        foreach ($macros as $name => $definition) {
            if (!is_array($definition)) {
                throw new \InvalidArgumentException('Expected TeX macro definition for ' . $name);
            }

            if (($definition['environment'] ?? false) === true) {
                $environmentName = $this->normalizeRawTexEnvironmentName((string) $name);
                if (!isset($definition['opener']) || !is_string($definition['opener']) || !isset($definition['closer']) || !is_string($definition['closer'])) {
                    throw new \InvalidArgumentException('Expected TeX environment opener and closer for ' . $environmentName);
                }

                $arity = $definition['arity'] ?? $this->inferMacroArity($definition['opener'] . $definition['closer']);
                if (!is_int($arity) || $arity < 0 || $arity > 9) {
                    throw new \InvalidArgumentException('Unsupported TeX environment arity for ' . $environmentName);
                }

                $environment = [
                    'arity' => $arity,
                    'opener' => $definition['opener'],
                    'closer' => $definition['closer'],
                ];

                if (array_key_exists('optionalDefault', $definition)) {
                    if (!is_string($definition['optionalDefault'])) {
                        throw new \InvalidArgumentException('Expected TeX optional environment default for ' . $environmentName);
                    }

                    if ($arity < 1) {
                        throw new \InvalidArgumentException('Unsupported TeX optional environment arity for ' . $environmentName);
                    }

                    $environment['optionalDefault'] = $definition['optionalDefault'];
                }

                $environments[$environmentName] = $environment;
                continue;
            }

            $macroName = ltrim((string) $name, '\\');
            if (preg_match('/^[A-Za-z]+$/', $macroName) !== 1) {
                throw new \InvalidArgumentException('Unsupported TeX macro name ' . $name);
            }

            if (!isset($definition['template']) || !is_string($definition['template'])) {
                throw new \InvalidArgumentException('Expected TeX macro template for \\' . $macroName);
            }

            $arity = $definition['arity'] ?? $this->inferMacroArity($definition['template']);
            if (!is_int($arity) || $arity < 0 || $arity > 9) {
                throw new \InvalidArgumentException('Unsupported TeX macro arity for \\' . $macroName);
            }

            $macro = [
                'arity' => $arity,
                'template' => $definition['template'],
            ];

            if (array_key_exists('optionalDefault', $definition)) {
                if (!is_string($definition['optionalDefault'])) {
                    throw new \InvalidArgumentException('Expected TeX optional macro default for \\' . $macroName);
                }

                if ($arity < 1) {
                    throw new \InvalidArgumentException('Unsupported TeX optional macro arity for \\' . $macroName);
                }

                $macro['optionalDefault'] = $definition['optionalDefault'];
            }

            $commands[$macroName] = $macro;
        }

        return [
            'commands' => $commands,
            'environments' => $environments,
        ];
    }

    /**
     * @param array<string, array{arity:int, opener:string, closer:string, optionalDefault?: string}> $environments
     */
    private function extractRawTexEnvironmentDefinitions(string $math, array &$environments): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($math);

        while ($offset < $length) {
            if (($math[$offset] ?? '') !== '\\') {
                $output .= $math[$offset];
                $offset++;
                continue;
            }

            $definition = $this->readRawTexEnvironmentDefinitionAt($math, $offset);
            if ($definition === null) {
                $output .= $math[$offset];
                $offset++;
                continue;
            }

            $parsed = $definition['definition'];
            $environments[$parsed['name']] = [
                'arity' => $parsed['arity'],
                'opener' => $parsed['opener'],
                'closer' => $parsed['closer'],
            ];
            if (array_key_exists('optionalDefault', $parsed)) {
                $environments[$parsed['name']]['optionalDefault'] = $parsed['optionalDefault'];
            }
            $offset = $definition['next'];
        }

        return $output;
    }

    /**
     * @param array<string, array{arity:int, template:string, optionalDefault?: string}> $macros
     * @param array<string, array{arity:int, opener:string, closer:string, optionalDefault?: string}> $environments
     */
    private function expandRawTexMathMacros(string $math, array $macros, array $environments): string
    {
        if ($macros === [] && $environments === []) {
            return $math;
        }

        $expanded = $math;
        $limit = max(5, (2 * (count($macros) + count($environments))) + 1);
        for ($iteration = 0; $iteration < $limit; $iteration++) {
            $next = $this->expandRawTexMathMacrosOnce($expanded, $macros, $environments);
            if ($next === $expanded) {
                break;
            }
            $expanded = $next;
        }

        return $expanded;
    }

    /**
     * @param array<string, array{arity:int, template:string, optionalDefault?: string}> $macros
     * @param array<string, array{arity:int, opener:string, closer:string, optionalDefault?: string}> $environments
     */
    private function expandRawTexMathMacrosOnce(string $math, array $macros, array $environments): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($math);

        while ($offset < $length) {
            if (($math[$offset] ?? '') === '\\' && preg_match('/\G\\\\begin(?![A-Za-z])/', $math, $m, 0, $offset) === 1) {
                $expandedEnvironment = $this->expandRawTexEnvironmentInvocation($math, $offset + strlen($m[0]), $environments);
                if ($expandedEnvironment !== null) {
                    $output .= $expandedEnvironment['tex'];
                    $offset = $expandedEnvironment['next'];
                    continue;
                }
            }

            if (
                ($math[$offset] ?? '') === '\\'
                && preg_match('/\G\\\\([A-Za-z]+)/', $math, $m, 0, $offset) === 1
                && isset($macros[$m[1]])
            ) {
                $macro = $macros[$m[1]];
                $cursor = $offset + strlen($m[0]);
                $pairedDelimiter = $this->pairedDelimiterMacroDelimiters($macro);
                if ($pairedDelimiter !== null) {
                    $pairedExpansion = $this->expandPairedDelimiterMacroInvocation($math, $cursor, $m[1], $macro, $pairedDelimiter);
                    if ($pairedExpansion !== null) {
                        $output .= $pairedExpansion['tex'];
                        $offset = $pairedExpansion['next'];
                        continue;
                    }
                }

                $args = [];
                $requiredArity = $macro['arity'];
                if (array_key_exists('optionalDefault', $macro)) {
                    $optionalOffset = $cursor;
                    $this->skipWhitespace($math, $optionalOffset);
                    $optionalArgument = $this->readTexBracketArgument($math, $optionalOffset);
                    if ($optionalArgument !== null) {
                        $args[] = $optionalArgument['value'];
                        $cursor = $optionalArgument['next'];
                    } else {
                        $args[] = $macro['optionalDefault'];
                    }
                    $requiredArity--;
                }

                for ($argument = 0; $argument < $requiredArity; $argument++) {
                    $this->skipWhitespace($math, $cursor);
                    $parsed = $this->readTexBraceArgument($math, $cursor);
                    if ($parsed === null) {
                        break;
                    }
                    $args[] = $parsed['value'];
                    $cursor = $parsed['next'];
                }

                if (count($args) === $macro['arity']) {
                    $output .= $this->renderRawTexMacroTemplate($macro['template'], $args);
                    $offset = $cursor;
                    continue;
                }
            }

            $output .= $math[$offset];
            $offset++;
        }

        return $output;
    }

    /**
     * @param array<string, array{arity:int, opener:string, closer:string, optionalDefault?: string}> $environments
     * @return array{tex:string, next:int}|null
     */
    private function expandRawTexEnvironmentInvocation(string $math, int $cursor, array $environments): ?array
    {
        if ($environments === []) {
            return null;
        }

        $this->skipWhitespace($math, $cursor);
        $nameArgument = $this->readTexBraceArgument($math, $cursor);
        if ($nameArgument === null) {
            return null;
        }

        $name = $this->normalizeRawTexEnvironmentName($nameArgument['value']);
        if (!isset($environments[$name])) {
            return null;
        }

        $environment = $environments[$name];
        $cursor = $nameArgument['next'];
        $args = [];
        $requiredArity = $environment['arity'];
        if (array_key_exists('optionalDefault', $environment)) {
            $optionalOffset = $cursor;
            $this->skipWhitespace($math, $optionalOffset);
            $optionalArgument = $this->readTexBracketArgument($math, $optionalOffset);
            if ($optionalArgument !== null) {
                $args[] = $optionalArgument['value'];
                $cursor = $optionalArgument['next'];
            } else {
                $args[] = $environment['optionalDefault'];
            }
            $requiredArity--;
        }

        for ($argument = 0; $argument < $requiredArity; $argument++) {
            $this->skipWhitespace($math, $cursor);
            $parsed = $this->readTexBraceArgument($math, $cursor);
            if ($parsed === null) {
                throw new \InvalidArgumentException('Expected TeX environment argument ' . ($argument + 1) . ' for ' . $name . ' at offset ' . $cursor);
            }
            $args[] = $parsed['value'];
            $cursor = $parsed['next'];
        }

        $bodyOffset = $cursor;
        $body = $this->readEnvironmentContent($math, $bodyOffset, $name);

        return [
            'tex' => $this->renderRawTexMacroTemplate($environment['opener'] . $body . $environment['closer'], $args),
            'next' => $bodyOffset,
        ];
    }

    private function normalizeRawTexEnvironmentName(string $name): string
    {
        $environment = trim($name);
        if (preg_match('/^[A-Za-z][A-Za-z0-9*_-]*$/', $environment) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX environment macro name ' . $name);
        }

        return $environment;
    }

    /**
     * @param array{arity:int, template:string, optionalDefault?: string} $macro
     * @return array{open:string, close:string, bodyTemplate:string, prefix:string, suffix:string}|null
     */
    private function pairedDelimiterMacroDelimiters(array $macro): ?array
    {
        if ($macro['arity'] < 1 || array_key_exists('optionalDefault', $macro)) {
            return null;
        }

        $template = trim($macro['template']);
        $leftOffset = $this->findPairedDelimiterLeftCommand($template, 0);
        if ($leftOffset === null) {
            return null;
        }

        $prefix = trim(substr($template, 0, $leftOffset));
        $offset = $leftOffset + strlen('\\left');
        $open = $this->readPairedDelimiterTemplateToken($template, $offset);
        if ($open === null) {
            return null;
        }

        $this->skipWhitespace($template, $offset);
        $bodyOffset = $offset;
        $rightOffset = $this->findPairedDelimiterRightCommand($template, $bodyOffset);
        if ($rightOffset === null) {
            return null;
        }

        $bodyTemplate = trim(substr($template, $bodyOffset, $rightOffset - $bodyOffset));
        if ($bodyTemplate === '') {
            return null;
        }

        $offset = $rightOffset;
        $offset += strlen('\\right');

        $close = $this->readPairedDelimiterTemplateToken($template, $offset);
        if ($close === null) {
            return null;
        }

        $suffix = trim(substr($template, $offset));

        return [
            'open' => $open,
            'close' => $close,
            'bodyTemplate' => $bodyTemplate,
            'prefix' => $prefix,
            'suffix' => $suffix,
        ];
    }

    private function findPairedDelimiterLeftCommand(string $template, int $offset): ?int
    {
        $length = strlen($template);
        $depth = 0;
        while ($offset < $length) {
            $char = $template[$offset];
            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $command = $this->readCommandName($template, $commandOffset);
                if ($depth === 0 && $command === 'left') {
                    return $offset;
                }

                $offset = $commandOffset;
                continue;
            }

            if ($char === '{') {
                $depth++;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $offset++;
                continue;
            }

            $offset++;
        }

        return null;
    }

    private function findPairedDelimiterRightCommand(string $template, int $offset): ?int
    {
        $length = strlen($template);
        $depth = 0;
        while ($offset < $length) {
            $char = $template[$offset];
            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $command = $this->readCommandName($template, $commandOffset);
                if ($depth === 0 && $command === 'right') {
                    return $offset;
                }

                $offset = $commandOffset;
                continue;
            }

            if ($char === '{') {
                $depth++;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $offset++;
                continue;
            }

            $offset++;
        }

        return null;
    }

    private function readPairedDelimiterTemplateToken(string $template, int &$offset): ?string
    {
        $char = $template[$offset] ?? '';
        if ($char === '') {
            return null;
        }

        if ($char === '\\') {
            $start = $offset;
            $offset++;
            $command = $this->readCommandName($template, $offset);
            if (!isset(self::DELIMITER_COMMANDS[$command])) {
                return null;
            }

            return substr($template, $start, $offset - $start);
        }

        if ($char === '.' || str_contains('()[]{}|/<>', $char)) {
            $offset++;

            return $char;
        }

        return null;
    }

    /**
     * @param array{arity:int, template:string, optionalDefault?: string} $macro
     * @param array{open:string, close:string, bodyTemplate:string, prefix:string, suffix:string} $delimiters
     * @return array{tex:string, next:int}|null
     */
    private function expandPairedDelimiterMacroInvocation(string $math, int $cursor, string $name, array $macro, array $delimiters): ?array
    {
        $this->skipWhitespace($math, $cursor);
        $sizeCommand = null;
        $sawModifier = false;

        if (($math[$cursor] ?? '') === '*') {
            $sawModifier = true;
            $cursor++;
            $this->skipWhitespace($math, $cursor);
            if (($math[$cursor] ?? '') === '[') {
                throw new \InvalidArgumentException('Unsupported TeX paired delimiter size option after starred \\' . $name . ' at offset ' . $cursor);
            }
        } elseif (($math[$cursor] ?? '') === '[') {
            $sawModifier = true;
            $argument = $this->readTexBracketArgument($math, $cursor);
            if ($argument === null) {
                throw new \InvalidArgumentException('Unterminated TeX paired delimiter size option for \\' . $name . ' at offset ' . $cursor);
            }

            $sizeCommand = $this->normalizePairedDelimiterSizeCommand($argument['value'], $name);
            $cursor = $argument['next'];
        }

        if (!$sawModifier) {
            return null;
        }

        $arguments = [];
        for ($argument = 0; $argument < $macro['arity']; $argument++) {
            $this->skipWhitespace($math, $cursor);
            $parsed = $this->readTexBraceArgument($math, $cursor);
            if ($parsed === null) {
                throw new \InvalidArgumentException('Expected TeX paired delimiter argument ' . ($argument + 1) . ' for \\' . $name . ' at offset ' . $cursor);
            }

            $arguments[] = $parsed['value'];
            $cursor = $parsed['next'];
        }

        if ($sizeCommand === null) {
            return [
                'tex' => $this->renderRawTexMacroTemplate($macro['template'], $arguments),
                'next' => $cursor,
            ];
        }

        $body = $this->renderRawTexMacroTemplate($delimiters['bodyTemplate'], $arguments);
        $tex = '';
        if ($delimiters['prefix'] !== '') {
            $tex .= $delimiters['prefix'] . ' ';
        }
        $tex .= '\\' . $sizeCommand . 'l' . $delimiters['open']
            . ' ' . $body . ' \\' . $sizeCommand . 'r' . $delimiters['close'];
        if ($delimiters['suffix'] !== '') {
            $tex .= ' ' . $delimiters['suffix'];
        }

        return [
            'tex' => $tex,
            'next' => $cursor,
        ];
    }

    private function normalizePairedDelimiterSizeCommand(string $argument, string $name): string
    {
        $argument = trim($argument);
        if ($argument === '' || ($argument[0] ?? '') !== '\\') {
            throw new \InvalidArgumentException('Expected TeX paired delimiter size command for \\' . $name);
        }

        $offset = 1;
        $command = $this->readCommandName($argument, $offset);
        $this->skipWhitespace($argument, $offset);
        if ($offset !== strlen($argument)) {
            throw new \InvalidArgumentException('Unexpected TeX paired delimiter size option content for \\' . $name . ' at offset ' . $offset);
        }

        return match ($command) {
            'big', 'bigl', 'bigr', 'bigm' => 'big',
            'Big', 'Bigl', 'Bigr', 'Bigm' => 'Big',
            'bigg', 'biggl', 'biggr', 'biggm' => 'bigg',
            'Bigg', 'Biggl', 'Biggr', 'Biggm' => 'Bigg',
            default => throw new \InvalidArgumentException('Unsupported TeX paired delimiter size command \\' . $command . ' for \\' . $name),
        };
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readTexBraceArgument(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '{') {
            return null;
        }

        $depth = 0;
        $length = strlen($text);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '{') {
                $depth++;
                continue;
            }

            if ($text[$cursor] !== '}') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return [
                    'value' => substr($text, $offset + 1, $cursor - $offset - 1),
                    'next' => $cursor + 1,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readTexBracketArgument(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $length = strlen($text);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '{') {
                $depth++;
                continue;
            }

            if ($text[$cursor] === '}' && $depth > 0) {
                $depth--;
                continue;
            }

            if ($text[$cursor] === ']' && $depth === 0) {
                return [
                    'value' => substr($text, $offset + 1, $cursor - $offset - 1),
                    'next' => $cursor + 1,
                ];
            }
        }

        return null;
    }

    private function skipOptionalBracketArguments(string $source, int &$offset, string $command): void
    {
        while (true) {
            $this->skipWhitespace($source, $offset);
            if (($source[$offset] ?? '') !== '[') {
                return;
            }

            $argument = $this->readTexBracketArgument($source, $offset);
            if ($argument === null) {
                throw new \InvalidArgumentException('Unterminated TeX \\' . $command . ' option at offset ' . $offset);
            }

            $offset = $argument['next'];
        }
    }

    /**
     * @param list<string> $args
     */
    private function renderRawTexMacroTemplate(string $template, array $args): string
    {
        foreach ($args as $index => $argument) {
            $template = str_replace('#' . ($index + 1), $argument, $template);
        }

        return $template;
    }

    /**
     * @return list<string>
     */
    private function parseExpression(string $source, int &$offset, ?string $stopChar): array
    {
        $nodes = [];
        $length = strlen($source);

        while ($offset < $length) {
            $this->skipWhitespace($source, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($stopChar !== null && $source[$offset] === $stopChar) {
                break;
            }

            $colorDeclaration = $this->readColorDeclarationCommand($source, $offset);
            if ($colorDeclaration !== null) {
                $tail = $this->parseExpression($source, $offset, $stopChar);
                if ($tail === []) {
                    throw new \InvalidArgumentException('Expected TeX color declaration content at offset ' . $offset);
                }

                $nodes[] = '<mstyle mathcolor="' . $this->esc($colorDeclaration) . '">' . $this->row($tail) . '</mstyle>';

                return $nodes;
            }

            $infixOffset = $offset;
            $infixCommand = $this->readInfixFractionCommand($source, $infixOffset);
            if ($infixCommand !== null) {
                if ($nodes === []) {
                    throw new \InvalidArgumentException('Expected TeX infix numerator before \\' . $infixCommand['command'] . ' at offset ' . $offset);
                }

                $offset = $infixOffset;
                $denominator = $this->parseExpression($source, $offset, $stopChar);
                if ($denominator === []) {
                    throw new \InvalidArgumentException('Expected TeX infix denominator after \\' . $infixCommand['command'] . ' at offset ' . $offset);
                }

                return [$this->renderInfixFractionCommand($nodes, $denominator, $infixCommand)];
            }

            $defaultScriptPlacement = null;
            $base = $this->parseAtom($source, $offset, $defaultScriptPlacement);
            $scriptPlacement = $this->readScriptPlacementCommand($source, $offset);
            if (
                $scriptPlacement === null
                && $defaultScriptPlacement !== null
                && $this->nextNonWhitespaceIsScriptMarker($source, $offset)
            ) {
                $scriptPlacement = $defaultScriptPlacement;
            }
            $this->appendExpressionNode($nodes, $this->applyScripts($source, $offset, $base, $scriptPlacement));
        }

        return $nodes;
    }

    /**
     * @param list<string> $nodes
     */
    private function appendExpressionNode(array &$nodes, string $node): void
    {
        if ($node === '') {
            $nodes[] = $node;

            return;
        }

        $previousIndex = $this->previousNonSpacingExpressionNodeIndex($nodes);
        if (
            $previousIndex !== null
            && $this->mathMlNodeHasFunctionOperatorHead($nodes[$previousIndex])
            && $this->mathMlNodeCanStartFunctionArgument($node)
            && !$this->shouldSuppressAutomaticFunctionApplication($nodes, $previousIndex, $node)
        ) {
            $nodes[] = '<mo>⁡</mo>';
        }

        $nodes[] = $node;
    }

    /**
     * @param list<string> $nodes
     */
    private function shouldSuppressAutomaticFunctionApplication(array $nodes, int $previousIndex, string $node): bool
    {
        if (preg_match('/^<mo\b[^>]*>(?:\[|\{)<\/mo>$/u', $node) === 1) {
            return true;
        }

        if ($previousIndex !== 0 || preg_match('/^<mi\b/', $node) !== 1) {
            return false;
        }

        $attribute = preg_quote(self::TEX_FUNCTION_OPERATOR_ATTRIBUTE, '/');

        return preg_match(
            '/^<m(?:under|over|underover)\b[^>]*><mi\b[^>]*' . $attribute . '[^>]*>[^<]*\s[^<]*<\/mi>/u',
            $nodes[$previousIndex]
        ) === 1;
    }

    /**
     * @param list<string> $nodes
     */
    private function previousNonSpacingExpressionNodeIndex(array $nodes): ?int
    {
        for ($index = count($nodes) - 1; $index >= 0; $index--) {
            if ($nodes[$index] === '' || $this->mathMlNodeIsSpacing($nodes[$index])) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function mathMlNodeHasFunctionOperatorHead(string $node): bool
    {
        $attribute = preg_quote(self::TEX_FUNCTION_OPERATOR_ATTRIBUTE, '/');

        if (preg_match('/^<mi\b[^>]*' . $attribute . '[^>]*>/', $node) === 1) {
            return true;
        }

        return preg_match('/^<m(?:sub|sup|subsup|under|over|underover)\b[^>]*><mi\b[^>]*' . $attribute . '[^>]*>/', $node) === 1;
    }

    private function mathMlNodeCanStartFunctionArgument(string $node): bool
    {
        if ($node === '' || $this->mathMlNodeIsSpacing($node)) {
            return false;
        }

        if ($this->mathMlNodeHasFunctionOperatorHead($node)) {
            return false;
        }

        if (preg_match('/^<(?:mi|mn|mtext|mfrac|msqrt|mroot|mrow|msub|msup|msubsup|munder|mover|munderover|mmultiscripts|menclose|mtable)\b/', $node) === 1) {
            return true;
        }

        return preg_match('/^<mo\b[^>]*>(?:\(|\[|\{)<\/mo>$/u', $node) === 1;
    }

    private function mathMlNodeIsSpacing(string $node): bool
    {
        return preg_match('/^<mspace\b[^>]*><\/mspace>$/', $node) === 1;
    }

    private function parseAtom(string $source, int &$offset, ?string &$defaultScriptPlacement = null): string
    {
        $defaultScriptPlacement = null;
        $char = $source[$offset] ?? '';
        if ($char === '') {
            throw new \InvalidArgumentException('Unexpected end of TeX input');
        }

        if ($char === '{') {
            $offset++;
            $children = $this->parseExpression($source, $offset, '}');
            $this->expectGroupEnd($source, $offset);

            return $this->row($children);
        }

        if ($char === '\\') {
            return $this->parseCommand($source, $offset, $defaultScriptPlacement);
        }

        if (ctype_digit($char)) {
            $start = $offset;
            $offset++;
            while ($offset < strlen($source) && (ctype_digit($source[$offset]) || $source[$offset] === '.')) {
                $offset++;
            }

            return '<mn>' . $this->esc(substr($source, $start, $offset - $start)) . '</mn>';
        }

        if (ctype_alpha($char)) {
            $offset++;

            return '<mi>' . $this->esc($char) . '</mi>';
        }

        $offset++;

        return '<mo>' . $this->esc($char) . '</mo>';
    }

    private function parseCommand(string $source, int &$offset, ?string &$defaultScriptPlacement = null): string
    {
        $offset++;
        $command = $this->readCommandName($source, $offset);

        if ($command === 'frac') {
            return $this->parseFractionCommand($source, $offset, null);
        }

        if ($command === 'dfrac') {
            return $this->parseFractionCommand($source, $offset, true);
        }

        if ($command === 'tfrac') {
            return $this->parseFractionCommand($source, $offset, false);
        }

        if ($command === 'genfrac') {
            return $this->parseGeneralizedFractionCommand($source, $offset);
        }

        if ($command === 'sqrt') {
            $degree = $this->parseOptionalRootDegree($source, $offset);
            $radicand = $this->parseRequiredTexToken($source, $offset, 'sqrt radicand', true);

            if ($degree !== null) {
                return '<mroot>' . $radicand . $degree . '</mroot>';
            }

            return '<msqrt>' . $radicand . '</msqrt>';
        }

        if ($command === 'root') {
            return $this->parsePlainRootCommand($source, $offset);
        }

        if ($command === 'surd') {
            return '<msqrt>'
                . $this->parseRequiredAtomOrGroup($source, $offset, 'surd radicand')
                . '</msqrt>';
        }

        if ($command === 'binom') {
            return $this->parseBinomialCommand($source, $offset, null);
        }

        if ($command === 'tbinom') {
            return $this->parseBinomialCommand($source, $offset, false);
        }

        if ($command === 'dbinom') {
            return $this->parseBinomialCommand($source, $offset, true);
        }

        if (isset(self::MATRIX_COMMAND_ENVIRONMENTS[$command])) {
            return $this->parsePlainMatrixCommand($source, $offset, $command);
        }

        if (isset(self::PLAIN_ALIGNMENT_COMMANDS[$command])) {
            return $this->parsePlainAlignmentCommand($source, $offset, $command);
        }

        if (array_key_exists($command, self::TEXT_MODE_COMMANDS)) {
            return $this->parseTextModeCommand($source, $offset, $command);
        }

        if ($command === 'operatorname' || $command === 'operatornamewithlimits') {
            if ($command === 'operatornamewithlimits') {
                $defaultScriptPlacement = 'limits';
            } elseif (($source[$offset] ?? '') === '*') {
                $defaultScriptPlacement = 'limits';
                $offset++;
            }

            $operatorName = $this->readMathOperatorNameArgument($source, $offset);

            return $this->functionOperatorIdentifier($operatorName, $this->operatorNameCanApply($operatorName));
        }

        if ($command === 'ref' || $command === 'eqref') {
            return $this->parseEquationReferenceCommand($source, $offset, $command);
        }

        if ($command === 'hyperref') {
            return $this->parseHyperrefCommand($source, $offset);
        }

        if ($command === 'href') {
            return $this->parseHrefCommand($source, $offset);
        }

        if ($command === 'url') {
            return $this->parseUrlCommand($source, $offset);
        }

        if (in_array($command, ['num', 'numrange', 'numlist', 'si', 'unit', 'SI', 'qty', 'SIrange', 'qtyrange', 'ang'], true)) {
            return $this->parseSiunitxCommand($source, $offset, $command);
        }

        if ($command === 'not') {
            return $this->parseNotCommand($source, $offset);
        }

        if ($command === 'limits' || $command === 'nolimits' || $command === 'displaylimits') {
            throw new \InvalidArgumentException('Unexpected TeX \\' . $command . ' without previous math base at offset ' . $offset);
        }

        if ($command === 'substack') {
            return $this->parseSubstackCommand($source, $offset);
        }

        if ($command === 'ensuremath') {
            return $this->parseRequiredNonEmptyGroup($source, $offset, 'ensuremath');
        }

        if ($command === 'mathchoice') {
            return $this->parseMathChoiceCommand($source, $offset);
        }

        if ($command === 'buildrel') {
            return $this->parseBuildrelCommand($source, $offset);
        }

        if ($command === 'stackrel') {
            $above = $this->parseRequiredAtomOrGroup($source, $offset, 'stackrel above');
            $base = $this->parseRequiredAtomOrGroup($source, $offset, 'stackrel base');

            return '<mover>' . $base . $above . '</mover>';
        }

        if ($command === 'sideset') {
            return $this->parseSidesetCommand($source, $offset);
        }

        if ($command === 'prescript') {
            return $this->parsePrescriptCommand($source, $offset);
        }

        if ($command === 'overset') {
            $above = $this->parseRequiredTexToken($source, $offset, 'overset above');
            $base = $this->parseRequiredTexToken($source, $offset, 'overset base');

            return '<mover>' . $base . $above . '</mover>';
        }

        if ($command === 'underset') {
            $below = $this->parseRequiredTexToken($source, $offset, 'underset below');
            $base = $this->parseRequiredTexToken($source, $offset, 'underset base');

            return '<munder>' . $base . $below . '</munder>';
        }

        if ($command === 'overunderset') {
            $above = $this->parseRequiredTexToken($source, $offset, 'overunderset above');
            $below = $this->parseRequiredTexToken($source, $offset, 'overunderset below');
            $base = $this->parseRequiredTexToken($source, $offset, 'overunderset base');

            return '<munderover>' . $base . $below . $above . '</munderover>';
        }

        if ($command === 'underoverset') {
            $below = $this->parseRequiredTexToken($source, $offset, 'underoverset below');
            $above = $this->parseRequiredTexToken($source, $offset, 'underoverset above');
            $base = $this->parseRequiredTexToken($source, $offset, 'underoverset base');

            return '<munderover>' . $base . $below . $above . '</munderover>';
        }

        if ($command === 'overbrace') {
            return '<mover>'
                . $this->parseRequiredTexToken($source, $offset, 'overbrace base')
                . '<mo>⏞</mo>'
                . '</mover>';
        }

        if ($command === 'underbrace') {
            return '<munder>'
                . $this->parseRequiredTexToken($source, $offset, 'underbrace base')
                . '<mo>⏟</mo>'
                . '</munder>';
        }

        if ($command === 'overbracket') {
            return '<mover>'
                . $this->parseRequiredTexToken($source, $offset, 'overbracket base')
                . '<mo>⎴</mo>'
                . '</mover>';
        }

        if ($command === 'underbracket') {
            return '<munder>'
                . $this->parseRequiredTexToken($source, $offset, 'underbracket base')
                . '<mo>⎵</mo>'
                . '</munder>';
        }

        if ($command === 'overparen') {
            return '<mover>'
                . $this->parseRequiredTexToken($source, $offset, 'overparen base')
                . '<mo>⏜</mo>'
                . '</mover>';
        }

        if ($command === 'underparen') {
            return '<munder>'
                . $this->parseRequiredTexToken($source, $offset, 'underparen base')
                . '<mo>⏝</mo>'
                . '</munder>';
        }

        if ($command === 'overgroup') {
            return '<mover>'
                . $this->parseRequiredTexToken($source, $offset, 'overgroup base')
                . '<mo>⏠</mo>'
                . '</mover>';
        }

        if ($command === 'undergroup') {
            return '<munder>'
                . $this->parseRequiredTexToken($source, $offset, 'undergroup base')
                . '<mo>⏡</mo>'
                . '</munder>';
        }

        if (in_array($command, ['displaystyle', 'textstyle', 'scriptstyle', 'scriptscriptstyle'], true)) {
            return $this->parseStyleCommand($source, $offset, $command);
        }

        if ($command === 'boxed') {
            return '<menclose notation="box">'
                . $this->parseRequiredTexToken($source, $offset, 'boxed content')
                . '</menclose>';
        }

        if ($command === 'color' || $command === 'textcolor') {
            return $this->parseColorCommand($source, $offset, $command);
        }

        if ($command === 'colorbox' || $command === 'fcolorbox') {
            return $this->parseColorBoxCommand($source, $offset, $command);
        }

        if ($command === 'phantom' || $command === 'hphantom' || $command === 'vphantom') {
            return $this->parsePhantomCommand($source, $offset, $command);
        }

        if ($command === 'smash') {
            return $this->parseSmashCommand($source, $offset);
        }

        if (isset(self::OVERLAP_BOX_COMMANDS[$command])) {
            return $this->parseOverlapBoxCommand($source, $offset, $command);
        }

        if ($command === 'cancelto') {
            return $this->parseCancelToCommand($source, $offset);
        }

        if (isset(self::CANCEL_COMMANDS[$command])) {
            return $this->parseCancelCommand($source, $offset, $command);
        }

        if (isset(self::MATH_VARIANT_COMMANDS[$command])) {
            return $this->parseMathVariantCommand($source, $offset, $command);
        }

        if (isset(self::MATH_CLASS_COMMANDS[$command])) {
            return $this->parseMathClassCommand($source, $offset, $command);
        }

        if ($command === 'hspace' || $command === 'mspace') {
            return $this->parseExplicitSpaceCommand($source, $offset, $command);
        }

        if ($command === 'kern' || $command === 'mkern') {
            return $this->parseKernCommand($source, $offset, $command);
        }

        if ($command === 'allowbreak') {
            $placementOffset = $offset;
            $placement = $this->readScriptPlacementCommand($source, $placementOffset);
            if ($placement !== null) {
                throw new \InvalidArgumentException('Unexpected TeX \\' . $placement . ' after \\allowbreak at offset ' . $placementOffset);
            }

            $scriptOffset = $offset;
            $this->skipWhitespace($source, $scriptOffset);
            $marker = $source[$scriptOffset] ?? '';
            if ($marker === '_' || $marker === '^' || $marker === "'") {
                throw new \InvalidArgumentException('Unexpected TeX script marker after \\allowbreak at offset ' . $scriptOffset);
            }

            return '';
        }

        if ($command === 'mod' || $command === 'bmod' || $command === 'pmod' || $command === 'pod') {
            return $this->parseModuloCommand($source, $offset, $command);
        }

        if (isset(self::SPACING_COMMANDS[$command])) {
            return '<mspace width="' . self::SPACING_COMMANDS[$command] . '"></mspace>';
        }

        if (isset(self::ESCAPED_SYMBOL_COMMANDS[$command])) {
            return '<mo>' . $this->esc(self::ESCAPED_SYMBOL_COMMANDS[$command]) . '</mo>';
        }

        if (isset(self::EXTENSIBLE_ARROW_COMMANDS[$command])) {
            return $this->parseExtensibleArrowCommand($source, $offset, $command);
        }

        if (isset(self::ARROW_ACCENT_COMMANDS[$command])) {
            return $this->parseArrowAccentCommand($source, $offset, $command);
        }

        if ($command === 'begin') {
            return $this->parseEnvironment($source, $offset);
        }

        if ($command === 'end') {
            throw new \InvalidArgumentException('Unexpected TeX environment end at offset ' . $offset);
        }

        if ($command === 'middle') {
            return $this->parseMiddleFenceCommand($source, $offset);
        }

        if ($command === 'left' || $command === 'right') {
            return $this->parseFenceCommand($source, $offset, $command);
        }

        if (isset(self::SIZED_DELIMITER_COMMANDS[$command])) {
            return $this->parseSizedDelimiterCommand($source, $offset, $command);
        }

        if (isset(self::OVER_ACCENT_COMMANDS[$command])) {
            return '<mover accent="true">'
                . $this->parseAccentArgument($source, $offset, $command)
                . '<mo>' . $this->esc(self::OVER_ACCENT_COMMANDS[$command]) . '</mo>'
                . '</mover>';
        }

        if (isset(self::UNDER_ACCENT_COMMANDS[$command])) {
            return '<munder accentunder="true">'
                . $this->parseAccentArgument($source, $offset, $command)
                . '<mo>' . $this->esc(self::UNDER_ACCENT_COMMANDS[$command]) . '</mo>'
                . '</munder>';
        }

        if (isset(self::IDENTIFIER_COMMANDS[$command])) {
            return '<mi>' . self::IDENTIFIER_COMMANDS[$command] . '</mi>';
        }

        if (isset(self::FUNCTION_COMMANDS[$command])) {
            return $this->functionOperatorIdentifier(self::FUNCTION_COMMANDS[$command]);
        }

        if (isset(self::OPERATOR_COMMANDS[$command])) {
            return '<mo>' . self::OPERATOR_COMMANDS[$command] . '</mo>';
        }

        if (isset(self::DELIMITER_COMMANDS[$command])) {
            return '<mo>' . $this->esc(self::DELIMITER_COMMANDS[$command]) . '</mo>';
        }

        return '<mi>' . $this->esc('\\' . $command) . '</mi>';
    }

    private function readMathOperatorNameArgument(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '{') {
            $operatorName = $this->normalizeMathOperatorNameText($this->readRequiredGroupText($source, $offset));
        } else {
            $operatorName = $this->normalizeMathOperatorNameText($this->readMathOperatorNameTokenText($source, $offset));
        }

        if ($operatorName === '') {
            throw new \InvalidArgumentException('Expected TeX operator name at offset ' . $offset);
        }

        return $operatorName;
    }

    private function functionOperatorIdentifier(string $operatorName, bool $canApply = true): string
    {
        $attribute = $canApply ? ' ' . self::TEX_FUNCTION_OPERATOR_ATTRIBUTE : '';

        return '<mi' . $attribute . '>' . $this->esc($operatorName) . '</mi>';
    }

    private function operatorNameCanApply(string $operatorName): bool
    {
        return preg_match('/[\p{L}\p{N}]/u', $operatorName) === 1;
    }

    private function readMathOperatorNameTokenText(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '{' || $char === '}' || $char === '_' || $char === '^' || $char === "'") {
            throw new \InvalidArgumentException('Expected TeX operator name at offset ' . $offset);
        }

        if ($char === '\\') {
            $offset++;
            $command = $this->readCommandName($source, $offset);
            if (isset(self::IDENTIFIER_COMMANDS[$command])) {
                return self::IDENTIFIER_COMMANDS[$command];
            }

            if (isset(self::FUNCTION_COMMANDS[$command])) {
                return self::FUNCTION_COMMANDS[$command];
            }

            if (isset(self::OPERATOR_COMMANDS[$command])) {
                return self::OPERATOR_COMMANDS[$command];
            }

            if (isset(self::DELIMITER_COMMANDS[$command])) {
                return self::DELIMITER_COMMANDS[$command];
            }

            throw new \InvalidArgumentException('Unsupported TeX operator name command \\' . $command . ' at offset ' . $start);
        }

        $remaining = substr($source, $offset);
        if (preg_match('/\A./us', $remaining, $m) === 1) {
            $offset += strlen($m[0]);

            return $m[0];
        }

        $offset++;

        return $char;
    }

    private function parseEnvironment(string $source, int &$offset): string
    {
        $environment = $this->readRequiredGroupText($source, $offset);
        if (isset(self::EQUATION_WRAPPER_ENVIRONMENTS[$environment])) {
            return $this->parseEquationWrapperEnvironment($source, $offset, $environment);
        }

        $matrixEnvironment = $this->normalizeStarredMatrixEnvironment($environment);

        if ($matrixEnvironment === 'smallmatrix') {
            return $this->parseSmallMatrixEnvironment($source, $offset, $environment);
        }

        if ($environment === 'subarray') {
            return $this->parseSubarrayEnvironment($source, $offset);
        }

        if ($environment === 'array') {
            return $this->parseArrayEnvironment($source, $offset);
        }

        if (isset(self::AMS_ROW_ENVIRONMENTS[$environment])) {
            return $this->parseAmsRowEnvironment($source, $offset, $environment);
        }

        if (isset(self::AMS_ALIGNEDAT_ENVIRONMENTS[$environment])) {
            return $this->parseAmsAlignedAtEnvironment($source, $offset, $environment);
        }

        if (isset(self::AMS_FLUSH_ALIGNED_ENVIRONMENTS[$environment])) {
            return $this->parseAmsFlushAlignedEnvironment($source, $offset, $environment);
        }

        if (isset(self::EQNARRAY_ENVIRONMENTS[$environment])) {
            return $this->parseEqnarrayEnvironment($source, $offset, $environment);
        }

        if (!isset(self::MATRIX_ENVIRONMENTS[$matrixEnvironment])) {
            throw new \InvalidArgumentException('Unsupported TeX environment ' . $environment . ' at offset ' . $offset);
        }

        $positionAttributes = $this->readOptionalAmsEnvironmentPositionAttributes($source, $offset, $matrixEnvironment);
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        $splitRows = $this->splitAlignmentRowsWithSpacing($content, $matrixEnvironment);
        $rows = $splitRows['rows'];
        $spec = self::MATRIX_ENVIRONMENTS[$matrixEnvironment];
        $attributes = '';
        if (isset($spec['columnalign'])) {
            $attributes = ' columnalign="' . $this->esc($spec['columnalign']) . '"';
        }
        $attributes .= $positionAttributes;
        $attributes .= $this->environmentRowSpacingAttributes($splitRows['rowSpacing'], count($rows));

        return $this->matrixTableForRows($rows, $matrixEnvironment, $attributes);
    }

    private function parsePlainMatrixCommand(string $source, int &$offset, string $command): string
    {
        $matrixEnvironment = self::MATRIX_COMMAND_ENVIRONMENTS[$command];
        $content = $this->readRequiredGroupText($source, $offset);
        $normalizedContent = $this->normalizePlainMatrixCommandRows($content, $command);
        $splitRows = $this->splitAlignmentRowsWithSpacing($normalizedContent, $command);
        $rows = $splitRows['rows'];
        $spec = self::MATRIX_ENVIRONMENTS[$matrixEnvironment];
        $attributes = '';
        if (isset($spec['columnalign'])) {
            $attributes = ' columnalign="' . $this->esc($spec['columnalign']) . '"';
        }
        $attributes .= $this->environmentRowSpacingAttributes($splitRows['rowSpacing'], count($rows));

        return $this->matrixTableForRows($rows, $matrixEnvironment, $attributes);
    }

    private function parsePlainAlignmentCommand(string $source, int &$offset, string $command): string
    {
        $content = $this->readRequiredGroupText($source, $offset);
        $normalizedContent = $this->normalizePlainMatrixCommandRows($content, $command);
        $splitRows = $this->splitAlignmentRowsWithSpacing($normalizedContent, $command);
        $rows = $splitRows['rows'];
        $spec = self::PLAIN_ALIGNMENT_COMMANDS[$command];
        $this->validateAmsRowEnvironmentRows($rows, $command, $spec['columns']);
        $attributes = ' columnalign="' . $this->esc($spec['columnalign']) . '"'
            . $this->environmentRowSpacingAttributes($splitRows['rowSpacing'], count($rows));

        return $this->environmentTable($rows, $attributes);
    }

    private function normalizePlainMatrixCommandRows(string $content, string $command): string
    {
        $normalized = '';
        $depth = 0;
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $char = $content[$offset];
            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $rowCommand = $this->readCommandName($content, $commandOffset);
                if ($depth === 0 && ($rowCommand === 'cr' || $rowCommand === 'crcr')) {
                    $normalized .= '\\\\';
                    $offset = $commandOffset;
                    continue;
                }

                $normalized .= substr($content, $offset, $commandOffset - $offset);
                $offset = $commandOffset;
                continue;
            }

            if ($char === '%') {
                $this->skipTexLineComment($content, $offset);
                if ($normalized !== '' && !ctype_space(substr($normalized, -1))) {
                    $normalized .= ' ';
                }
                continue;
            }

            if ($char === '{') {
                $depth++;
                $normalized .= $char;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth === 0) {
                    throw new \InvalidArgumentException('Unexpected TeX group end in \\' . $command . ' command at offset ' . $offset);
                }
                $depth--;
                $normalized .= $char;
                $offset++;
                continue;
            }

            $normalized .= $char;
            $offset++;
        }

        if ($depth !== 0) {
            throw new \InvalidArgumentException('Unclosed TeX group in \\' . $command . ' command');
        }

        return $normalized;
    }

    /**
     * @param list<list<string>> $rows
     */
    private function matrixTableForRows(array $rows, string $matrixEnvironment, string $attributes): string
    {
        $spec = self::MATRIX_ENVIRONMENTS[$matrixEnvironment];
        $table = $this->environmentTable($rows, $attributes);
        if (($spec['displaystyle'] ?? false) === true) {
            $table = '<mstyle displaystyle="true">' . $table . '</mstyle>';
        }

        if (isset($spec['open']) || isset($spec['close'])) {
            $wrapped = '<mrow>';
            if (isset($spec['open'])) {
                $wrapped .= '<mo fence="true" stretchy="true">' . $this->esc($spec['open']) . '</mo>';
            }
            $wrapped .= $table;
            if (isset($spec['close'])) {
                $wrapped .= '<mo fence="true" stretchy="true">' . $this->esc($spec['close']) . '</mo>';
            }

            return $wrapped . '</mrow>';
        }

        return $table;
    }

    private function normalizeStarredMatrixEnvironment(string $environment): string
    {
        if (!str_ends_with($environment, '*')) {
            return $environment;
        }

        $baseEnvironment = substr($environment, 0, -1);
        if ($baseEnvironment === 'smallmatrix' || isset(self::MATRIX_ENVIRONMENTS[$baseEnvironment])) {
            return $baseEnvironment;
        }

        return $environment;
    }

    private function parseEquationWrapperEnvironment(string $source, int &$offset, string $environment): string
    {
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        if (trim($content) === '') {
            throw new \InvalidArgumentException('Empty TeX environment ' . $environment);
        }

        $this->assertEquationWrapperContent($content, $environment);
        $parsed = $this->stripEnvironmentCellRowMetadata($content, $environment, 0);
        $bodySource = trim($parsed['cell']);
        if ($bodySource === '') {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' content');
        }

        $body = $this->parseTexFragment($bodySource, $environment . ' content');

        return $this->renderEquationBody([$body], [
            'label' => $parsed['label'],
            'labelId' => $parsed['label'] !== null ? $this->normalizeEquationLabelId($parsed['label']) : null,
            'tag' => $parsed['tag'],
            'tagStarred' => $parsed['tagStarred'],
            'suppressNumbering' => $parsed['suppressNumbering'],
        ]);
    }

    private function assertEquationWrapperContent(string $content, string $environment): void
    {
        $depth = 0;
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $char = $content[$offset];
            if ($char === '\\') {
                if ($depth === 0 && ($content[$offset + 1] ?? '') === '\\') {
                    throw new \InvalidArgumentException('Unexpected TeX row separator in ' . $environment . ' environment at offset ' . $offset);
                }

                $commandOffset = $offset + 1;
                $command = $this->readCommandName($content, $commandOffset);
                if ($depth === 0 && $command === 'begin') {
                    $environmentOffset = $commandOffset;
                    $nestedEnvironment = $this->readRequiredGroupText($content, $environmentOffset);
                    $this->readEnvironmentContent($content, $environmentOffset, $nestedEnvironment);
                    $offset = $environmentOffset;
                    continue;
                }

                $offset = $commandOffset;
                continue;
            }

            if ($char === '{') {
                $depth++;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $offset++;
                continue;
            }

            if ($depth === 0 && $char === '&') {
                throw new \InvalidArgumentException('Unexpected TeX alignment marker in ' . $environment . ' environment at offset ' . $offset);
            }

            $offset++;
        }
    }

    private function parseBinomialCommand(string $source, int &$offset, ?bool $displaystyle): string
    {
        $numerator = $this->parseRequiredTexToken($source, $offset, 'binomial numerator');
        $denominator = $this->parseRequiredTexToken($source, $offset, 'binomial denominator');
        $binomial = '<mrow>'
            . '<mo fence="true" stretchy="true">(</mo>'
            . '<mfrac linethickness="0">' . $numerator . $denominator . '</mfrac>'
            . '<mo fence="true" stretchy="true">)</mo>'
            . '</mrow>';

        if ($displaystyle === null) {
            return $binomial;
        }

        return '<mstyle displaystyle="' . ($displaystyle ? 'true' : 'false') . '">' . $binomial . '</mstyle>';
    }

    private function parseFractionCommand(string $source, int &$offset, ?bool $displaystyle): string
    {
        $fraction = '<mfrac>'
            . $this->parseRequiredTexToken($source, $offset, 'fraction numerator', true)
            . $this->parseRequiredTexToken($source, $offset, 'fraction denominator', true)
            . '</mfrac>';

        if ($displaystyle === null) {
            return $fraction;
        }

        return '<mstyle displaystyle="' . ($displaystyle ? 'true' : 'false') . '">' . $fraction . '</mstyle>';
    }

    private function parseGeneralizedFractionCommand(string $source, int &$offset): string
    {
        $left = $this->normalizeGeneralizedFractionDelimiter($this->readRequiredGroupText($source, $offset), 'left');
        $right = $this->normalizeGeneralizedFractionDelimiter($this->readRequiredGroupText($source, $offset), 'right');
        $lineThickness = $this->normalizeGeneralizedFractionLineThickness($this->readRequiredGroupText($source, $offset));
        $style = $this->normalizeGeneralizedFractionStyle($this->readRequiredGroupText($source, $offset));
        $fraction = '<mfrac'
            . ($lineThickness !== null ? ' linethickness="' . $this->esc($lineThickness) . '"' : '')
            . '>'
            . $this->parseRequiredNonEmptyGroup($source, $offset, 'genfrac numerator')
            . $this->parseRequiredNonEmptyGroup($source, $offset, 'genfrac denominator')
            . '</mfrac>';

        if ($left !== '' || $right !== '') {
            $fraction = '<mrow>'
                . ($left !== '' ? '<mo fence="true" stretchy="true">' . $this->esc($left) . '</mo>' : '')
                . $fraction
                . ($right !== '' ? '<mo fence="true" stretchy="true">' . $this->esc($right) . '</mo>' : '')
                . '</mrow>';
        }

        if ($style === null) {
            return $fraction;
        }

        return '<mstyle' . $style . '>' . $fraction . '</mstyle>';
    }

    /**
     * @param list<string> $numerator
     * @param list<string> $denominator
     * @param array{command:string, lineThickness?:string, open?:string, close?:string} $spec
     */
    private function renderInfixFractionCommand(array $numerator, array $denominator, array $spec): string
    {
        $fraction = '<mfrac'
            . (isset($spec['lineThickness']) ? ' linethickness="' . $this->esc($spec['lineThickness']) . '"' : '')
            . '>'
            . $this->row($numerator)
            . $this->row($denominator)
            . '</mfrac>';

        $open = $spec['open'] ?? '';
        $close = $spec['close'] ?? '';
        if ($open === '' && $close === '') {
            return $fraction;
        }

        return '<mrow>'
            . ($open !== '' ? '<mo fence="true" stretchy="true">' . $this->esc($open) . '</mo>' : '')
            . $fraction
            . ($close !== '' ? '<mo fence="true" stretchy="true">' . $this->esc($close) . '</mo>' : '')
            . '</mrow>';
    }

    private function normalizeGeneralizedFractionDelimiter(string $delimiter, string $side): string
    {
        $delimiter = trim($delimiter);
        if ($delimiter === '' || $delimiter === '.') {
            return '';
        }

        if (strlen($delimiter) === 1 && str_contains('()[]{}|/<>', $delimiter)) {
            return $delimiter;
        }

        if ($delimiter[0] === '\\') {
            $offset = 1;
            $command = $this->readCommandName($delimiter, $offset);
            if ($offset === strlen($delimiter) && isset(self::DELIMITER_COMMANDS[$command])) {
                return self::DELIMITER_COMMANDS[$command];
            }
        }

        throw new \InvalidArgumentException('Unsupported TeX genfrac ' . $side . ' delimiter ' . $delimiter);
    }

    private function normalizeGeneralizedFractionLineThickness(string $lineThickness): ?string
    {
        $lineThickness = trim($lineThickness);
        if ($lineThickness === '') {
            return null;
        }

        if (preg_match('/^(?:0+(?:\.0+)?)(?:pt|em|ex|px)?$/', $lineThickness) === 1) {
            return '0';
        }

        if (preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:pt|em|ex|px)$/', $lineThickness) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX genfrac line thickness ' . $lineThickness);
        }

        return $lineThickness;
    }

    private function normalizeGeneralizedFractionStyle(string $style): ?string
    {
        $style = trim($style);

        return match ($style) {
            '' => null,
            '0' => ' displaystyle="true"',
            '1' => ' displaystyle="false"',
            '2' => ' scriptlevel="1"',
            '3' => ' scriptlevel="2"',
            default => throw new \InvalidArgumentException('Unsupported TeX genfrac style ' . $style),
        };
    }

    private function parseArrayEnvironment(string $source, int &$offset): string
    {
        $columnSpec = $this->arrayColumnSpec($this->readRequiredGroupText($source, $offset));
        $columnAttributes = $this->arrayColumnAttributesFromSpec($columnSpec);
        $splitRows = $this->splitAlignmentRowsWithSpacing($this->readEnvironmentContent($source, $offset, 'array'), 'array');
        $rowRules = $this->stripArrayRowRules($splitRows['rows'], $columnSpec['columns']);
        $rowSpacingAttributes = $this->environmentRowSpacingAttributes($splitRows['rowSpacing'], count($rowRules['rows']));

        return $this->environmentTable($rowRules['rows'], $columnAttributes . $rowRules['attributes'] . $rowSpacingAttributes, false, 'array', $columnSpec['columns']);
    }

    private function parseSmallMatrixEnvironment(string $source, int &$offset, string $environment = 'smallmatrix'): string
    {
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
        }

        $rows = $this->splitAlignmentRows($content, 'smallmatrix');

        return '<mstyle scriptlevel="1">'
            . $this->environmentTable($rows, ' rowspacing="0.1em" columnspacing="0.2778em"')
            . '</mstyle>';
    }

    private function parseSubarrayEnvironment(string $source, int &$offset): string
    {
        $columnAlign = $this->arrayColumnAlign($this->readRequiredGroupText($source, $offset));
        $content = $this->readEnvironmentContent($source, $offset, 'subarray');
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX subarray row content at final row');
        }

        $rows = $this->splitAlignmentRows($content, 'subarray');
        $this->validateAmsRowEnvironmentRows($rows, 'subarray', count(explode(' ', $columnAlign)));

        return $this->environmentTable($rows, ' columnalign="' . $this->esc($columnAlign) . '" rowspacing="0.1em"');
    }

    private function parseAmsRowEnvironment(string $source, int &$offset, string $environment): string
    {
        $positionAttributes = $this->readOptionalAmsEnvironmentPositionAttributes($source, $offset, $environment);
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
        }

        $splitRows = $this->splitAlignmentRowsWithSpacing($content, $environment);
        $rows = $splitRows['rows'];
        $spec = self::AMS_ROW_ENVIRONMENTS[$environment];
        $this->validateAmsRowEnvironmentRows($rows, $environment, $spec['columns']);
        $rowSpacingAttributes = $this->environmentRowSpacingAttributes($splitRows['rowSpacing'], count($rows));

        return $this->environmentTable($rows, ' columnalign="' . $this->esc($spec['columnalign']) . '"' . $positionAttributes . $rowSpacingAttributes, true, $environment, null, $spec['columns']);
    }

    private function parseAmsAlignedAtEnvironment(string $source, int &$offset, string $environment): string
    {
        $positionAttributes = $this->readOptionalAmsEnvironmentPositionAttributes($source, $offset, $environment);
        $pairs = $this->normalizeAmsAlignedAtPairCount($this->readRequiredGroupText($source, $offset), $environment);
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
        }

        $splitRows = $this->splitAlignmentRowsWithSpacing($content, $environment);
        $rows = $splitRows['rows'];
        $this->validateAmsRowEnvironmentRows($rows, $environment, $pairs * 2);
        $rowSpacingAttributes = $this->environmentRowSpacingAttributes($splitRows['rowSpacing'], count($rows));

        return $this->environmentTable($rows, ' columnalign="' . $this->esc(implode(' ', array_fill(0, $pairs, 'right left'))) . '"' . $positionAttributes . $rowSpacingAttributes, true, $environment, null, $pairs * 2);
    }

    private function parseAmsFlushAlignedEnvironment(string $source, int &$offset, string $environment): string
    {
        $positionAttributes = $this->readOptionalAmsEnvironmentPositionAttributes($source, $offset, $environment);
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
        }

        $splitRows = $this->splitAlignmentRowsWithSpacing($content, $environment);
        $rows = $splitRows['rows'];
        $columns = $this->validateAmsFlushAlignedRows($rows, $environment);
        $rowSpacingAttributes = $this->environmentRowSpacingAttributes($splitRows['rowSpacing'], count($rows));

        return $this->environmentTable($rows, ' columnalign="' . $this->esc($this->flushAlignedColumnAlign($columns)) . '"' . $positionAttributes . $rowSpacingAttributes, true, $environment, null, $columns);
    }

    private function parseEqnarrayEnvironment(string $source, int &$offset, string $environment): string
    {
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
        }

        $splitRows = $this->splitAlignmentRowsWithSpacing($content, $environment);
        $rows = $splitRows['rows'];
        $this->validateAmsRowEnvironmentRows($rows, $environment, 3);
        $rowSpacingAttributes = $this->environmentRowSpacingAttributes($splitRows['rowSpacing'], count($rows));

        return $this->environmentTable($rows, ' columnalign="right center left"' . $rowSpacingAttributes, true, $environment, null, 3);
    }

    private function parseTextModeCommand(string $source, int &$offset, string $command): string
    {
        $variant = self::TEXT_MODE_COMMANDS[$command];

        return $this->row($this->parseTextModeCommandContent($source, $offset, $command, $variant));
    }

    /**
     * @return list<string>
     */
    private function parseTextModeCommandContent(string $source, int &$offset, string $command, ?string $variant): array
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '{') {
            $content = $this->readRequiredGroupText($source, $offset);

            return $this->parseTextModeGroupContent($content, $variant);
        }

        return [$this->textModeTextNode($this->readTextModeTokenContent($source, $offset, $command), $variant)];
    }

    /**
     * @return list<string>
     */
    private function parseTextModeGroupContent(string $source, ?string $variant): array
    {
        $children = [];
        $buffer = '';
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $innerMath = $this->readTextModeInnerMath($source, $offset);
            if ($innerMath !== null) {
                $this->flushTextModeBuffer($children, $buffer, $variant);
                $children[] = $innerMath;
                continue;
            }

            $char = $source[$offset] ?? '';
            if ($char === '{') {
                $this->flushTextModeBuffer($children, $buffer, $variant);
                $content = $this->readRequiredGroupText($source, $offset);
                $children[] = $this->row($this->parseTextModeGroupContent($content, $variant));
                continue;
            }

            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $textCommand = $this->readCommandName($source, $commandOffset);
                if (array_key_exists($textCommand, self::TEXT_MODE_COMMANDS)) {
                    $this->flushTextModeBuffer($children, $buffer, $variant);
                    $offset = $commandOffset;
                    $children[] = $this->parseTextModeCommandAfterName($source, $offset, $textCommand);
                    continue;
                }

                if (isset(self::TEXT_MODE_NAMED_GLYPHS[$textCommand])) {
                    $buffer .= self::TEXT_MODE_NAMED_GLYPHS[$textCommand];
                    $offset = $commandOffset;
                    $this->skipTextModeControlWordSpace($source, $offset, $textCommand);
                    continue;
                }

                if (isset(self::TEXT_MODE_ACCENT_GLYPHS[$textCommand])) {
                    $offset = $commandOffset;
                    $buffer .= $this->readTextModeAccentGlyph($source, $offset, $textCommand);
                    continue;
                }

                if ($textCommand !== ' ' && isset(self::SPACING_COMMANDS[$textCommand])) {
                    $this->flushTextModeBuffer($children, $buffer, $variant);
                    $offset = $commandOffset;
                    $children[] = '<mspace width="' . self::SPACING_COMMANDS[$textCommand] . '"></mspace>';
                    continue;
                }

                $normalizedCommand = $this->normalizeTextModeGroupCommand($textCommand);
                $buffer .= $normalizedCommand;
                $offset = $commandOffset;
                if ($normalizedCommand !== '\\' . $textCommand) {
                    $this->skipTextModeControlWordSpace($source, $offset, $textCommand);
                }
                continue;
            }

            $remaining = substr($source, $offset);
            if (preg_match('/\A./us', $remaining, $m) === 1) {
                $buffer .= $m[0];
                $offset += strlen($m[0]);
                continue;
            }

            $buffer .= $char;
            $offset++;
        }

        $this->flushTextModeBuffer($children, $buffer, $variant);
        if ($children === []) {
            $children[] = $this->textModeTextNode('', $variant);
        }

        return $children;
    }

    private function parseTextModeCommandAfterName(string $source, int &$offset, string $command): string
    {
        $variant = self::TEXT_MODE_COMMANDS[$command];

        return $this->row($this->parseTextModeCommandContent($source, $offset, $command, $variant));
    }

    /**
     * @param list<string> $children
     */
    private function flushTextModeBuffer(array &$children, string &$buffer, ?string $variant): void
    {
        if ($buffer === '') {
            return;
        }

        $children[] = $this->textModeTextNode($buffer, $variant);
        $buffer = '';
    }

    private function textModeTextNode(string $text, ?string $variant): string
    {
        $text = $this->normalizeTextModeLigatures($text);
        $node = '<mtext>' . $this->esc($text) . '</mtext>';
        if ($variant === null) {
            return $node;
        }

        return '<mstyle mathvariant="' . $this->esc($variant) . '">' . $node . '</mstyle>';
    }

    private function readTextModeAccentGlyph(string $source, int &$offset, string $command): string
    {
        $target = $this->readTextModeAccentTarget($source, $offset, $command);

        return self::TEXT_MODE_ACCENT_GLYPHS[$command][$target] ?? $command . $target;
    }

    private function readTextModeAccentTarget(string $source, int &$offset, string $command): string
    {
        if (($source[$offset] ?? '') === '{') {
            $target = $this->normalizeTextModeContent($this->readRequiredGroupText($source, $offset));
        } elseif (($source[$offset] ?? '') === '\\') {
            $offset++;
            $targetCommand = $this->readCommandName($source, $offset);
            $target = self::TEXT_MODE_NAMED_GLYPHS[$targetCommand] ?? $this->normalizeTextModeGroupCommand($targetCommand);
        } else {
            $remaining = substr($source, $offset);
            if (preg_match('/\A./us', $remaining, $m) !== 1) {
                throw new \InvalidArgumentException('Expected TeX text accent \\' . $command . ' target at offset ' . $offset);
            }
            $target = $m[0];
            $offset += strlen($m[0]);
        }

        if (preg_match('/\A./us', $target, $m) !== 1) {
            throw new \InvalidArgumentException('Expected TeX text accent \\' . $command . ' target at offset ' . $offset);
        }

        return $m[0];
    }

    private function normalizeTextModeLigatures(string $text): string
    {
        return strtr($text, [
            '---' => '—',
            '--' => '–',
            '``' => '“',
            "''" => '”',
            '`' => '‘',
            "'" => '’',
        ]);
    }

    private function skipTextModeControlWordSpace(string $source, int &$offset, string $command): void
    {
        if ($command === '' || !ctype_alpha($command[0] ?? '') || ($source[$offset] ?? '') !== ' ') {
            return;
        }

        $offset++;
    }

    private function readTextModeInnerMath(string $source, int &$offset): ?string
    {
        foreach ([['$$', '$$'], ['\\[', '\\]'], ['\\(', '\\)'], ['$', '$']] as [$opener, $closer]) {
            if (substr_compare($source, $opener, $offset, strlen($opener)) !== 0) {
                continue;
            }

            $innerStart = $offset + strlen($opener);
            $end = $this->findTextModeInnerMathDelimiter($source, $innerStart, $closer);
            if ($end === null) {
                return null;
            }

            $math = substr($source, $innerStart, $end - $innerStart);
            $offset = $end + strlen($closer);

            return $this->parseTexFragment($math, 'text-mode inner math');
        }

        return null;
    }

    private function findTextModeInnerMathDelimiter(string $source, int $offset, string $delimiter): ?int
    {
        while (($position = strpos($source, $delimiter, $offset)) !== false) {
            if ($position > 0 && $source[$position - 1] === '\\') {
                $offset = $position + strlen($delimiter);
                continue;
            }

            return $position;
        }

        return null;
    }

    private function normalizeTextModeGroupCommand(string $command): string
    {
        return match ($command) {
            '&', '%', '$', '#', '_', '{', '}' => $command,
            ' ' => ' ',
            'LaTeX' => 'LaTeX',
            'TeX' => 'TeX',
            'dots', 'ldots' => '…',
            'textbackslash' => '\\',
            default => ctype_alpha($command[0] ?? '') ? '\\' . $command : $command,
        };
    }

    private function readTextModeTokenContent(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === "\n" || $char === "\t" || $char === "\r" || $char === '{' || $char === '}' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX ' . $command . ' text token at offset ' . $offset);
        }

        if ($char === '\\') {
            $offset++;
            $tokenCommandOffset = $offset;
            $tokenCommand = $this->readCommandName($source, $offset);

            return $this->normalizeTextModeTokenCommand($tokenCommand, $tokenCommandOffset);
        }

        $remaining = substr($source, $offset);
        if (preg_match('/\A./us', $remaining, $m) === 1) {
            $offset += strlen($m[0]);

            return $m[0];
        }

        throw new \InvalidArgumentException('Expected TeX ' . $command . ' text token at offset ' . $start);
    }

    private function normalizeTextModeTokenCommand(string $command, int $offset): string
    {
        return match ($command) {
            '&', '%', '$', '#', '_', '{', '}' => $command,
            ' ' => ' ',
            'LaTeX' => 'LaTeX',
            'TeX' => 'TeX',
            'dots', 'ldots' => '…',
            'textbackslash' => '\\',
            default => throw new \InvalidArgumentException('Unsupported TeX text token command \\' . $command . ' at offset ' . $offset),
        };
    }

    private function normalizeTextModeContent(string $text): string
    {
        $output = '';
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            if ($char !== '\\') {
                $output .= $char;
                continue;
            }

            $offset++;
            $escaped = $text[$offset] ?? '';
            if ($escaped === '') {
                $output .= '\\';
                break;
            }

            $special = match ($escaped) {
                '&', '%', '$', '#', '_', '{', '}' => $escaped,
                ' ' => ' ',
                default => null,
            };
            if ($special !== null) {
                $output .= $special;
                continue;
            }

            if (ctype_alpha($escaped)) {
                $commandStart = $offset;
                while ($offset < $length && ctype_alpha($text[$offset])) {
                    $offset++;
                }
                $command = substr($text, $commandStart, $offset - $commandStart);
                $normalizedCommand = match ($command) {
                    'AA' => 'Å',
                    'aa' => 'å',
                    'AE' => 'Æ',
                    'ae' => 'æ',
                    'LaTeX' => 'LaTeX',
                    'L' => 'Ł',
                    'l' => 'ł',
                    'O' => 'Ø',
                    'o' => 'ø',
                    'OE' => 'Œ',
                    'oe' => 'œ',
                    'ss' => 'ß',
                    'TeX' => 'TeX',
                    'dots', 'ldots' => '…',
                    'textbackslash' => '\\',
                    default => '\\' . $command,
                };
                if ($normalizedCommand !== '\\' . $command) {
                    $this->skipTextModeControlWordSpace($text, $offset, $command);
                }
                $offset--;
                $output .= $normalizedCommand;
                continue;
            }

            $output .= $escaped;
        }

        return $output;
    }

    private function normalizeAmsAlignedAtPairCount(string $pairCount, string $environment): int
    {
        $pairCount = trim($pairCount);
        if (preg_match('/^[1-9][0-9]*$/', $pairCount) !== 1) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' positive column pair count');
        }

        $pairs = (int) $pairCount;
        if ($pairs > 4) {
            throw new \InvalidArgumentException('Unsupported TeX ' . $environment . ' column pair count ' . $pairCount);
        }

        return $pairs;
    }

    private function readOptionalAmsEnvironmentPositionAttributes(string $source, int &$offset, string $environment): string
    {
        if (!isset(self::AMS_OPTIONAL_POSITION_ENVIRONMENTS[$environment])) {
            return '';
        }

        $this->skipWhitespace($source, $offset);
        $argument = $this->readTexBracketArgument($source, $offset);
        if ($argument === null) {
            return '';
        }

        $position = trim($argument['value']);
        $align = match ($position) {
            't' => 'top',
            'b' => 'bottom',
            'c' => 'center',
            default => null,
        };

        if ($align === null) {
            throw new \InvalidArgumentException('Unsupported TeX ' . $environment . ' position ' . $position);
        }

        $offset = $argument['next'];

        return ' align="' . $this->esc($align) . '" data-tex-env-position="' . $this->esc($align) . '"';
    }

    private function parseColorCommand(string $source, int &$offset, string $command): string
    {
        $color = $this->readMathColorArgument($source, $offset, $command)['color'];
        $content = $this->parseRequiredTexToken($source, $offset, $command . ' content');

        return '<mstyle mathcolor="' . $this->esc($color) . '">' . $content . '</mstyle>';
    }

    private function parseColorBoxCommand(string $source, int &$offset, string $command): string
    {
        $firstColor = $this->readMathColorArgument($source, $offset, $command);
        if ($command === 'colorbox') {
            $content = $this->parseRequiredTexToken($source, $offset, 'colorbox content');

            return '<mstyle mathbackground="' . $this->esc($firstColor['color']) . '">' . $content . '</mstyle>';
        }

        $backgroundColor = $this->readMathColorArgument($source, $offset, $command, $firstColor['model'])['color'];
        $content = $this->parseRequiredTexToken($source, $offset, 'fcolorbox content');

        return '<menclose notation="box" mathbackground="' . $this->esc($backgroundColor) . '" data-tex-framecolor="' . $this->esc($firstColor['color']) . '">'
            . $content
            . '</menclose>';
    }

    private function readColorDeclarationCommand(string $source, int &$offset): ?string
    {
        $cursor = $offset;
        if (($source[$cursor] ?? '') !== '\\') {
            return null;
        }

        $cursor++;
        if ($this->readCommandName($source, $cursor) !== 'color') {
            return null;
        }

        try {
            $color = $this->readMathColorArgument($source, $cursor, 'color')['color'];
        } catch (\InvalidArgumentException) {
            return null;
        }

        $after = $cursor;
        $this->skipWhitespace($source, $after);

        if (($source[$after] ?? '') === '{') {
            return null;
        }

        $marker = $source[$after] ?? '';
        if ($marker === '_' || $marker === '^' || $marker === "'") {
            throw new \InvalidArgumentException('Expected TeX color declaration content at offset ' . $after);
        }

        $offset = $cursor;

        return $color;
    }

    /**
     * @return array{color:string, model:?string}
     */
    private function readMathColorArgument(string $source, int &$offset, string $command, ?string $inheritedModel = null): array
    {
        $this->skipWhitespace($source, $offset);
        $model = $inheritedModel;
        if (($source[$offset] ?? '') === '[') {
            if ($inheritedModel !== null) {
                throw new \InvalidArgumentException('Unexpected TeX \\' . $command . ' repeated color model at offset ' . $offset);
            }

            $argument = $this->readTexBracketArgument($source, $offset);
            if ($argument === null) {
                throw new \InvalidArgumentException('Expected TeX \\' . $command . ' color model at offset ' . $offset);
            }

            $model = trim($argument['value']);
            if ($model === '') {
                throw new \InvalidArgumentException('Expected TeX \\' . $command . ' color model at offset ' . $offset);
            }

            $offset = $argument['next'];
        }

        $color = $this->readRequiredGroupText($source, $offset);

        $normalized = $model === null
            ? $this->normalizeMathColor($color)
            : $this->normalizeMathColorModel($model, $color, $command);

        return ['color' => $normalized, 'model' => $model];
    }

    private function normalizeMathColorModel(string $model, string $color, string $command): string
    {
        return match ($model) {
            'HTML', 'html' => $this->normalizeMathHexColor($color, $command),
            'RGB' => $this->normalizeMathRgbColor($color, $command, true),
            'rgb' => $this->normalizeMathRgbColor($color, $command, false),
            'gray' => $this->normalizeMathGrayColor($color, $command),
            'named' => $this->normalizeMathColor($color),
            default => throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' color model ' . $model),
        };
    }

    private function normalizeMathHexColor(string $color, string $command): string
    {
        $color = trim($color);
        if (str_starts_with($color, '#')) {
            $color = substr($color, 1);
        }

        if (preg_match('/^[0-9A-Fa-f]{6}$/', $color) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' HTML color ' . $color);
        }

        return '#' . strtolower($color);
    }

    private function normalizeMathRgbColor(string $color, string $command, bool $integerComponents): string
    {
        $components = array_map('trim', explode(',', trim($color)));
        if (count($components) !== 3) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' RGB color ' . $color);
        }

        $bytes = [];
        foreach ($components as $component) {
            $bytes[] = $integerComponents
                ? $this->normalizeMathIntegerColorComponent($component, $command, $color)
                : $this->normalizeMathUnitColorComponent($component, $command, $color);
        }

        return $this->mathColorHexTriplet($bytes[0], $bytes[1], $bytes[2]);
    }

    private function normalizeMathGrayColor(string $color, string $command): string
    {
        $component = $this->normalizeMathUnitColorComponent(trim($color), $command, $color);

        return $this->mathColorHexTriplet($component, $component, $component);
    }

    private function normalizeMathIntegerColorComponent(string $component, string $command, string $color): int
    {
        if (preg_match('/^\d+$/', $component) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' RGB color ' . $color);
        }

        $value = (int) $component;
        if ($value < 0 || $value > 255) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' RGB color ' . $color);
        }

        return $value;
    }

    private function normalizeMathUnitColorComponent(string $component, string $command, string $color): int
    {
        if (preg_match('/^(?:0(?:\.\d+)?|1(?:\.0+)?|\.\d+)$/', $component) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' rgb color ' . $color);
        }

        $value = (float) $component;
        if ($value < 0.0 || $value > 1.0) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' rgb color ' . $color);
        }

        return (int) round($value * 255);
    }

    private function mathColorHexTriplet(int $red, int $green, int $blue): string
    {
        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    private function normalizeMathColor(string $color): string
    {
        $color = trim($color);
        if ($color === '') {
            throw new \InvalidArgumentException('Expected TeX math color');
        }

        if (preg_match('/^#[0-9A-Fa-f]{3}(?:[0-9A-Fa-f]{3})?$/', $color) === 1) {
            return $color;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,31}$/', $color) === 1) {
            return $color;
        }

        throw new \InvalidArgumentException('Unsupported TeX math color ' . $color);
    }

    private function parsePhantomCommand(string $source, int &$offset, string $command): string
    {
        $content = '<mphantom>'
            . $this->parseRequiredTexToken($source, $offset, $command . ' content')
            . '</mphantom>';

        if ($command === 'hphantom') {
            return '<mpadded height="0" depth="0">' . $content . '</mpadded>';
        }

        if ($command === 'vphantom') {
            return '<mpadded width="0">' . $content . '</mpadded>';
        }

        return $content;
    }

    private function parseSmashCommand(string $source, int &$offset): string
    {
        $attributes = $this->smashPaddingAttributes($this->readOptionalSmashPosition($source, $offset));

        return '<mpadded' . $attributes . '>'
            . $this->parseRequiredTexToken($source, $offset, 'smash content')
            . '</mpadded>';
    }

    private function readOptionalSmashPosition(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        $argument = $this->readTexBracketArgument($source, $offset);
        if ($argument === null) {
            return null;
        }

        $position = trim($argument['value']);
        if ($position !== 't' && $position !== 'b') {
            throw new \InvalidArgumentException('Unsupported TeX \\smash position ' . $position);
        }

        $offset = $argument['next'];

        return $position;
    }

    private function smashPaddingAttributes(?string $position): string
    {
        if ($position === 't') {
            return ' height="0"';
        }

        if ($position === 'b') {
            return ' depth="0"';
        }

        return ' height="0" depth="0"';
    }

    private function parseOverlapBoxCommand(string $source, int &$offset, string $command): string
    {
        $attributes = '';
        foreach (self::OVERLAP_BOX_COMMANDS[$command] as $name => $value) {
            $attributes .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return '<mpadded' . $attributes . '>'
            . $this->parseRequiredTexToken($source, $offset, $command . ' content')
            . '</mpadded>';
    }

    private function parseCancelCommand(string $source, int &$offset, string $command): string
    {
        return '<menclose notation="' . self::CANCEL_COMMANDS[$command] . '">'
            . $this->parseRequiredTexToken($source, $offset, $command . ' content')
            . '</menclose>';
    }

    private function parseCancelToCommand(string $source, int &$offset): string
    {
        $target = $this->parseRequiredTexToken($source, $offset, 'cancelto target');
        $content = $this->parseRequiredTexToken($source, $offset, 'cancelto content');

        return '<mover>'
            . '<menclose notation="updiagonalstrike">' . $content . '</menclose>'
            . $target
            . '</mover>';
    }

    private function parseMathVariantCommand(string $source, int &$offset, string $command): string
    {
        $variant = self::MATH_VARIANT_COMMANDS[$command];

        return '<mstyle mathvariant="' . $variant . '">'
            . $this->rewriteMathVariantIdentifiers($this->parseMathVariantArgument($source, $offset, $command), $variant)
            . '</mstyle>';
    }

    private function parseMathClassCommand(string $source, int &$offset, string $command): string
    {
        return '<mrow data-tex-math-class="' . self::MATH_CLASS_COMMANDS[$command] . '">'
            . $this->parseMathClassArgument($source, $offset, $command)
            . '</mrow>';
    }

    private function parseMathClassArgument(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX math class argument for \\' . $command . ' at offset ' . $offset);
        }

        if ($char === '{') {
            return $this->parseRequiredNonEmptyGroup($source, $offset, 'math class');
        }

        $defaultScriptPlacement = null;

        return $this->parseAtom($source, $offset, $defaultScriptPlacement);
    }

    private function rewriteMathVariantIdentifiers(string $mathml, string $variant): string
    {
        $rewritten = preg_replace_callback('/<mi>([^<]*)<\/mi>/u', function (array $matches) use ($variant): string {
            $character = $this->singleUtf8Codepoint($matches[1]) !== null
                ? ($this->mathVariantUnicodeCharacter($variant, $matches[1]) ?? $matches[1])
                : $matches[1];

            return '<mi>' . $this->esc($character) . '</mi>';
        }, $mathml);

        if (!is_string($rewritten)) {
            return $mathml;
        }

        $withNumbers = preg_replace_callback('/<mn>([0-9]+)<\/mn>/', function (array $matches) use ($variant): string {
            $digits = '';
            for ($offset = 0; $offset < strlen($matches[1]); $offset++) {
                $digit = $matches[1][$offset];
                $digits .= $this->mathVariantUnicodeCharacter($variant, $digit) ?? $digit;
            }

            return '<mn>' . $this->esc($digits) . '</mn>';
        }, $rewritten);

        return is_string($withNumbers) ? $withNumbers : $rewritten;
    }

    private function mathVariantUnicodeCharacter(string $variant, string $character): ?string
    {
        $codepoint = $this->mathVariantUnicodeCodepoint($variant, $character);
        if ($codepoint === null) {
            return null;
        }

        return $this->utf8FromCodepoint($codepoint);
    }

    private function mathVariantUnicodeCodepoint(string $variant, string $character): ?int
    {
        $codepoint = $this->singleUtf8Codepoint($character);
        if ($codepoint === null) {
            return null;
        }

        $greekCodepoint = $this->mathGreekVariantUnicodeCodepoint($variant, $codepoint);
        if ($greekCodepoint !== null) {
            return $greekCodepoint;
        }

        if ($codepoint > 0x7F) {
            return null;
        }

        $ord = $codepoint;

        return match ($variant) {
            'bold' => $this->mathVariantOffsetCodepoint($ord, 0x1D400, 0x1D41A, 0x1D7CE),
            'bold-fraktur' => $this->mathVariantOffsetCodepoint($ord, 0x1D56C, 0x1D586, null),
            'bold-italic' => $this->mathVariantOffsetCodepoint($ord, 0x1D468, 0x1D482, null),
            'bold-sans-serif' => $this->mathVariantOffsetCodepoint($ord, 0x1D5D4, 0x1D5EE, 0x1D7EC),
            'bold-script' => $this->mathVariantOffsetCodepoint($ord, 0x1D4D0, 0x1D4EA, null),
            'double-struck' => $this->mathDoubleStruckCodepoint($ord, $character),
            'fraktur' => $this->mathFrakturCodepoint($ord, $character),
            'italic' => $this->mathItalicCodepoint($ord, $character),
            'monospace' => $this->mathVariantOffsetCodepoint($ord, 0x1D670, 0x1D68A, 0x1D7F6),
            'sans-serif' => $this->mathVariantOffsetCodepoint($ord, 0x1D5A0, 0x1D5BA, 0x1D7E2),
            'sans-serif-bold-italic' => $this->mathVariantOffsetCodepoint($ord, 0x1D63C, 0x1D656, null),
            'sans-serif-italic' => $this->mathVariantOffsetCodepoint($ord, 0x1D608, 0x1D622, null),
            'script' => $this->mathScriptCodepoint($ord, $character),
            default => null,
        };
    }

    private function mathGreekVariantUnicodeCodepoint(string $variant, int $codepoint): ?int
    {
        $offset = $this->mathGreekAlphabetOffset($codepoint);
        if ($offset === null) {
            return null;
        }

        $bases = match ($variant) {
            'bold' => ['upper' => 0x1D6A8, 'lower' => 0x1D6C2, 'symbol' => 0x1D6DB],
            'italic' => ['upper' => 0x1D6E2, 'lower' => 0x1D6FC, 'symbol' => 0x1D715],
            'bold-italic' => ['upper' => 0x1D71C, 'lower' => 0x1D736, 'symbol' => 0x1D74F],
            'bold-sans-serif' => ['upper' => 0x1D756, 'lower' => 0x1D770, 'symbol' => 0x1D789],
            'sans-serif-bold-italic' => ['upper' => 0x1D790, 'lower' => 0x1D7AA, 'symbol' => 0x1D7C3],
            default => null,
        };
        if ($bases === null) {
            return null;
        }

        return $bases[$offset['range']] + $offset['offset'];
    }

    /**
     * @return array{range:'upper'|'lower'|'symbol', offset:int}|null
     */
    private function mathGreekAlphabetOffset(int $codepoint): ?array
    {
        $upper = [
            0x0391 => 0, 0x0392 => 1, 0x0393 => 2, 0x0394 => 3, 0x0395 => 4,
            0x0396 => 5, 0x0397 => 6, 0x0398 => 7, 0x0399 => 8, 0x039A => 9,
            0x039B => 10, 0x039C => 11, 0x039D => 12, 0x039E => 13, 0x039F => 14,
            0x03A0 => 15, 0x03A1 => 16, 0x03F4 => 17, 0x03A3 => 18, 0x03A4 => 19,
            0x03A5 => 20, 0x03A6 => 21, 0x03A7 => 22, 0x03A8 => 23, 0x03A9 => 24,
        ];
        if (isset($upper[$codepoint])) {
            return ['range' => 'upper', 'offset' => $upper[$codepoint]];
        }

        $lower = [
            0x03B1 => 0, 0x03B2 => 1, 0x03B3 => 2, 0x03B4 => 3, 0x03B5 => 4,
            0x03B6 => 5, 0x03B7 => 6, 0x03B8 => 7, 0x03B9 => 8, 0x03BA => 9,
            0x03BB => 10, 0x03BC => 11, 0x03BD => 12, 0x03BE => 13, 0x03BF => 14,
            0x03C0 => 15, 0x03C1 => 16, 0x03C2 => 17, 0x03C3 => 18, 0x03C4 => 19,
            0x03C5 => 20, 0x03C6 => 21, 0x03C7 => 22, 0x03C8 => 23, 0x03C9 => 24,
        ];
        if (isset($lower[$codepoint])) {
            return ['range' => 'lower', 'offset' => $lower[$codepoint]];
        }

        $symbols = [
            0x2202 => 0,
            0x03F5 => 1,
            0x03D1 => 2,
            0x03F0 => 3,
            0x03D5 => 4,
            0x03F1 => 5,
            0x03D6 => 6,
        ];
        if (isset($symbols[$codepoint])) {
            return ['range' => 'symbol', 'offset' => $symbols[$codepoint]];
        }

        return null;
    }

    private function mathVariantOffsetCodepoint(int $ord, int $uppercaseBase, int $lowercaseBase, ?int $digitBase): ?int
    {
        if ($ord >= 65 && $ord <= 90) {
            return $uppercaseBase + ($ord - 65);
        }

        if ($ord >= 97 && $ord <= 122) {
            return $lowercaseBase + ($ord - 97);
        }

        if ($digitBase !== null && $ord >= 48 && $ord <= 57) {
            return $digitBase + ($ord - 48);
        }

        return null;
    }

    private function mathDoubleStruckCodepoint(int $ord, string $character): ?int
    {
        $exceptions = [
            'C' => 0x2102,
            'H' => 0x210D,
            'N' => 0x2115,
            'P' => 0x2119,
            'Q' => 0x211A,
            'R' => 0x211D,
            'Z' => 0x2124,
        ];
        if (isset($exceptions[$character])) {
            return $exceptions[$character];
        }

        return $this->mathVariantOffsetCodepoint($ord, 0x1D538, 0x1D552, 0x1D7D8);
    }

    private function mathFrakturCodepoint(int $ord, string $character): ?int
    {
        $exceptions = [
            'C' => 0x212D,
            'H' => 0x210C,
            'I' => 0x2111,
            'R' => 0x211C,
            'Z' => 0x2128,
        ];
        if (isset($exceptions[$character])) {
            return $exceptions[$character];
        }

        return $this->mathVariantOffsetCodepoint($ord, 0x1D504, 0x1D51E, null);
    }

    private function mathItalicCodepoint(int $ord, string $character): ?int
    {
        if ($character === 'h') {
            return 0x210E;
        }

        return $this->mathVariantOffsetCodepoint($ord, 0x1D434, 0x1D44E, null);
    }

    private function mathScriptCodepoint(int $ord, string $character): ?int
    {
        $exceptions = [
            'B' => 0x212C,
            'E' => 0x2130,
            'F' => 0x2131,
            'H' => 0x210B,
            'I' => 0x2110,
            'L' => 0x2112,
            'M' => 0x2133,
            'R' => 0x211B,
            'e' => 0x212F,
            'g' => 0x210A,
            'o' => 0x2134,
        ];
        if (isset($exceptions[$character])) {
            return $exceptions[$character];
        }

        return $this->mathVariantOffsetCodepoint($ord, 0x1D49C, 0x1D4B6, null);
    }

    private function mathVariantAccessibilityText(string $token): ?string
    {
        $codepoints = $this->utf8Codepoints($token);
        if ($codepoints === null || count($codepoints) === 0) {
            return null;
        }

        $parts = [];
        $asciiAlphanumeric = true;
        foreach ($codepoints as $codepoint) {
            $base = $this->mathVariantBaseCharacter($codepoint);
            if ($base === null) {
                return null;
            }

            if (preg_match('/^[A-Za-z0-9]$/', $base) !== 1) {
                $asciiAlphanumeric = false;
            }
            $parts[] = self::ACCESSIBILITY_TOKEN_TEXT[$base] ?? $base;
        }

        return $asciiAlphanumeric ? implode('', $parts) : implode(' ', $parts);
    }

    private function mathVariantBaseCharacter(int $codepoint): ?string
    {
        static $latinDigitBaseByCodepoint = null;

        if ($latinDigitBaseByCodepoint === null) {
            $latinDigitBaseByCodepoint = [];
            $variants = [
                'bold',
                'bold-fraktur',
                'bold-italic',
                'bold-sans-serif',
                'bold-script',
                'double-struck',
                'fraktur',
                'italic',
                'monospace',
                'sans-serif',
                'sans-serif-bold-italic',
                'sans-serif-italic',
                'script',
            ];
            $characters = array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9'));

            foreach ($variants as $variant) {
                foreach ($characters as $character) {
                    $variantCodepoint = $this->mathVariantUnicodeCodepoint($variant, $character);
                    if ($variantCodepoint !== null) {
                        $latinDigitBaseByCodepoint[$variantCodepoint] = $character;
                    }
                }
            }
        }

        return $latinDigitBaseByCodepoint[$codepoint] ?? $this->mathVariantGreekBaseCharacter($codepoint);
    }

    private function mathVariantGreekBaseCharacter(int $codepoint): ?string
    {
        $sets = [
            ['upper' => 0x1D6A8, 'lower' => 0x1D6C2, 'symbol' => 0x1D6DB],
            ['upper' => 0x1D6E2, 'lower' => 0x1D6FC, 'symbol' => 0x1D715],
            ['upper' => 0x1D71C, 'lower' => 0x1D736, 'symbol' => 0x1D74F],
            ['upper' => 0x1D756, 'lower' => 0x1D770, 'symbol' => 0x1D789],
            ['upper' => 0x1D790, 'lower' => 0x1D7AA, 'symbol' => 0x1D7C3],
        ];
        $base = [
            'upper' => [
                0x0391, 0x0392, 0x0393, 0x0394, 0x0395,
                0x0396, 0x0397, 0x0398, 0x0399, 0x039A,
                0x039B, 0x039C, 0x039D, 0x039E, 0x039F,
                0x03A0, 0x03A1, 0x03F4, 0x03A3, 0x03A4,
                0x03A5, 0x03A6, 0x03A7, 0x03A8, 0x03A9,
            ],
            'lower' => [
                0x03B1, 0x03B2, 0x03B3, 0x03B4, 0x03B5,
                0x03B6, 0x03B7, 0x03B8, 0x03B9, 0x03BA,
                0x03BB, 0x03BC, 0x03BD, 0x03BE, 0x03BF,
                0x03C0, 0x03C1, 0x03C2, 0x03C3, 0x03C4,
                0x03C5, 0x03C6, 0x03C7, 0x03C8, 0x03C9,
            ],
            'symbol' => [0x2202, 0x03F5, 0x03D1, 0x03F0, 0x03D5, 0x03F1, 0x03D6],
        ];

        foreach ($sets as $set) {
            foreach ($base as $range => $baseCodepoints) {
                $offset = $codepoint - $set[$range];
                if (isset($baseCodepoints[$offset])) {
                    return $this->utf8FromCodepoint($baseCodepoints[$offset]);
                }
            }
        }

        return null;
    }

    /**
     * @return list<int>|null
     */
    private function utf8Codepoints(string $text): ?array
    {
        $codepoints = [];
        $offset = 0;
        while ($offset < strlen($text)) {
            $codepoint = $this->readUtf8Codepoint($text, $offset);
            if ($codepoint === null) {
                return null;
            }
            $codepoints[] = $codepoint;
        }

        return $codepoints;
    }

    private function singleUtf8Codepoint(string $text): ?int
    {
        $codepoints = $this->utf8Codepoints($text);
        if ($codepoints === null || count($codepoints) !== 1) {
            return null;
        }

        return $codepoints[0];
    }

    private function readUtf8Codepoint(string $text, int &$offset): ?int
    {
        $first = ord($text[$offset] ?? "\0");
        $offset++;

        if ($first <= 0x7F) {
            return $first;
        }

        if (($first & 0xE0) === 0xC0) {
            $second = $this->readUtf8ContinuationCodepoint($text, $offset);
            if ($second === null) {
                return null;
            }
            $codepoint = (($first & 0x1F) << 6) | $second;

            return $codepoint >= 0x80 ? $codepoint : null;
        }

        if (($first & 0xF0) === 0xE0) {
            $second = $this->readUtf8ContinuationCodepoint($text, $offset);
            $third = $this->readUtf8ContinuationCodepoint($text, $offset);
            if ($second === null || $third === null) {
                return null;
            }
            $codepoint = (($first & 0x0F) << 12) | ($second << 6) | $third;
            if ($codepoint < 0x800 || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
                return null;
            }

            return $codepoint;
        }

        if (($first & 0xF8) === 0xF0) {
            $second = $this->readUtf8ContinuationCodepoint($text, $offset);
            $third = $this->readUtf8ContinuationCodepoint($text, $offset);
            $fourth = $this->readUtf8ContinuationCodepoint($text, $offset);
            if ($second === null || $third === null || $fourth === null) {
                return null;
            }
            $codepoint = (($first & 0x07) << 18) | ($second << 12) | ($third << 6) | $fourth;
            if ($codepoint < 0x10000 || $codepoint > 0x10FFFF) {
                return null;
            }

            return $codepoint;
        }

        return null;
    }

    private function readUtf8ContinuationCodepoint(string $text, int &$offset): ?int
    {
        if ($offset >= strlen($text)) {
            return null;
        }

        $byte = ord($text[$offset]);
        $offset++;
        if (($byte & 0xC0) !== 0x80) {
            return null;
        }

        return $byte & 0x3F;
    }

    private function utf8FromCodepoint(int $codepoint): string
    {
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

    private function parseExplicitSpaceCommand(string $source, int &$offset, string $command): string
    {
        $attributes = '';
        if ($command === 'hspace' && ($source[$offset] ?? '') === '*') {
            $attributes = ' linebreak="nobreak"';
            $offset++;
        }

        $width = $this->normalizeMathSpaceDimension($this->readRequiredGroupText($source, $offset), $command);

        return '<mspace width="' . $this->esc($width) . '"' . $attributes . '></mspace>';
    }

    private function parseKernCommand(string $source, int &$offset, string $command): string
    {
        $width = $this->readMathSpaceDimensionArgument($source, $offset, $command);

        return '<mspace width="' . $this->esc($width) . '"></mspace>';
    }

    private function parseModuloCommand(string $source, int &$offset, string $command): string
    {
        $argument = $this->parseRequiredScriptedAtomOrGroup($source, $offset, $command . ' argument');
        $thinSpace = '<mspace width="0.2222em"></mspace>';

        if ($command === 'mod') {
            return '<mspace width="0.4444em"></mspace><mi>mod</mi>' . $thinSpace . $argument;
        }

        if ($command === 'bmod') {
            return $thinSpace . '<mi>mod</mi>' . $thinSpace . $argument;
        }

        if ($command === 'pmod') {
            return $thinSpace . '<mo>(</mo><mi>mod</mi>' . $thinSpace . $argument . '<mo>)</mo>';
        }

        return $thinSpace . '<mo>(</mo>' . $argument . '<mo>)</mo>';
    }

    private function parseBuildrelCommand(string $source, int &$offset): string
    {
        $above = $this->parseRequiredScriptedAtomOrGroup($source, $offset, 'buildrel above');
        $this->skipWhitespace($source, $offset);

        if (($source[$offset] ?? '') !== '\\') {
            throw new \InvalidArgumentException('Expected TeX \\buildrel \\over at offset ' . $offset);
        }

        $overOffset = $offset + 1;
        $command = $this->readCommandName($source, $overOffset);
        if ($command !== 'over') {
            throw new \InvalidArgumentException('Expected TeX \\buildrel \\over at offset ' . $offset);
        }

        $offset = $overOffset;
        $base = $this->parseRequiredScriptedAtomOrGroup($source, $offset, 'buildrel base');

        return '<mover>' . $base . $above . '</mover>';
    }

    private function parseRequiredScriptedAtomOrGroup(string $source, int &$offset, string $label): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^' || $char === '}' || $char === '&') {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' content at offset ' . $offset);
        }

        if ($char === '{') {
            return $this->parseRequiredNonEmptyGroup($source, $offset, $label);
        }

        $defaultScriptPlacement = null;
        $base = $this->parseAtom($source, $offset, $defaultScriptPlacement);
        $scriptPlacement = $this->readScriptPlacementCommand($source, $offset);
        if (
            $scriptPlacement === null
            && $defaultScriptPlacement !== null
            && $this->nextNonWhitespaceIsScriptMarker($source, $offset)
        ) {
            $scriptPlacement = $defaultScriptPlacement;
        }

        return $this->applyScripts($source, $offset, $base, $scriptPlacement);
    }

    private function parseExtensibleArrowCommand(string $source, int &$offset, string $command): string
    {
        $below = $this->parseOptionalNonEmptyBracketArgument($source, $offset, $command . ' lower label');
        $above = $this->parseRequiredTexToken($source, $offset, $command . ' upper label');
        $arrow = '<mo stretchy="true">' . $this->esc(self::EXTENSIBLE_ARROW_COMMANDS[$command]) . '</mo>';

        if ($below !== null) {
            return '<munderover>' . $arrow . $below . $above . '</munderover>';
        }

        return '<mover>' . $arrow . $above . '</mover>';
    }

    private function parseEquationReferenceCommand(string $source, int &$offset, string $command): string
    {
        $label = trim($this->readRequiredGroupText($source, $offset));
        if ($label === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' label at offset ' . $offset);
        }

        $targetId = $this->normalizeEquationLabelId($label);
        $referenceText = $this->equationReferenceLabels[$targetId]['reference'] ?? $label;
        $reference = '<mtext href="#' . $this->esc($targetId) . '">' . $this->esc($referenceText) . '</mtext>';
        if ($command === 'eqref') {
            return '<mrow><mo>(</mo>' . $reference . '<mo>)</mo></mrow>';
        }

        return $reference;
    }

    private function parseHyperrefCommand(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '[') {
            $argument = $this->readTexBracketArgument($source, $offset);
            if ($argument === null) {
                throw new \InvalidArgumentException('Unterminated TeX hyperref target at offset ' . $offset);
            }

            $offset = $argument['next'];
        }

        return $this->parseRequiredNonEmptyGroup($source, $offset, 'hyperref content');
    }

    private function parseHrefCommand(string $source, int &$offset): string
    {
        $href = $this->readSafeMathHrefTarget($source, $offset, 'href target');
        $content = $this->parseRequiredNonEmptyGroup($source, $offset, 'href content');

        return '<mrow href="' . $this->esc($href) . '">' . $content . '</mrow>';
    }

    private function parseUrlCommand(string $source, int &$offset): string
    {
        $href = $this->readSafeMathHrefTarget($source, $offset, 'url target');

        return '<mtext href="' . $this->esc($href) . '">' . $this->esc($href) . '</mtext>';
    }

    private function readSafeMathHrefTarget(string $source, int &$offset, string $label): string
    {
        $target = $this->normalizeMathHrefTarget($this->readRequiredGroupText($source, $offset));
        if ($target === '') {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' at offset ' . $offset);
        }

        if (preg_match('/[\x00-\x20\x7F]/', $target) === 1) {
            throw new \InvalidArgumentException('Unsupported TeX ' . $label . ' with whitespace or control characters at offset ' . $offset);
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $target, $matches) === 1) {
            $scheme = strtolower($matches[1]);
            if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
                throw new \InvalidArgumentException('Unsupported TeX ' . $label . ' scheme "' . $scheme . '" at offset ' . $offset);
            }

            return $target;
        }

        if (str_starts_with($target, '//')) {
            throw new \InvalidArgumentException('Unsupported TeX ' . $label . ' protocol-relative URL at offset ' . $offset);
        }

        if (
            $target[0] === '#'
            || $target[0] === '/'
            || str_starts_with($target, './')
            || str_starts_with($target, '../')
        ) {
            return $target;
        }

        throw new \InvalidArgumentException('Unsupported TeX ' . $label . ' target at offset ' . $offset);
    }

    private function normalizeMathHrefTarget(string $target): string
    {
        $target = trim($target);

        return (string) preg_replace('/\\\\([#$%&_{}])/', '$1', $target);
    }

    private function parseSiunitxCommand(string $source, int &$offset, string $command): string
    {
        if ($command === 'num') {
            $this->skipOptionalBracketArguments($source, $offset, $command);

            return $this->parseSiNumberGroup($source, $offset, $command);
        }

        if ($command === 'numrange') {
            $this->skipOptionalBracketArguments($source, $offset, $command);

            return $this->parseSiNumberRangeCommand($source, $offset, $command);
        }

        if ($command === 'numlist') {
            $this->skipOptionalBracketArguments($source, $offset, $command);

            return $this->parseSiNumberListCommand($source, $offset, $command);
        }

        if ($command === 'si' || $command === 'unit') {
            $this->skipOptionalBracketArguments($source, $offset, $command);

            return $this->parseSiUnitGroup($source, $offset, $command);
        }

        if ($command === 'ang') {
            $this->skipOptionalBracketArguments($source, $offset, $command);

            return $this->parseSiAngleGroup($source, $offset);
        }

        $this->skipOptionalBracketArguments($source, $offset, $command);
        $number = $this->parseSiNumberGroup($source, $offset, $command);
        if ($command === 'SIrange' || $command === 'qtyrange') {
            $endNumber = $this->parseSiNumberGroup($source, $offset, $command);
            $prefix = $this->parseOptionalQuantityPrefix($source, $offset, $command);
            $unit = $this->parseSiUnitGroup($source, $offset, $command);
            $separator = '<mspace width="0.2222em"></mspace>';
            $nodes = [$number, $separator, '<mo>–</mo>', $separator, $endNumber, $separator, $unit];

            if ($prefix !== null) {
                array_unshift($nodes, $separator);
                array_unshift($nodes, $prefix);
            }

            return $this->row($nodes);
        }

        $prefix = $this->parseOptionalQuantityPrefix($source, $offset, $command);
        $unit = $this->parseSiUnitGroup($source, $offset, $command);
        $separator = '<mspace width="0.2222em"></mspace>';

        if ($prefix !== null) {
            return $this->row([$prefix, $separator, $number, $separator, $unit]);
        }

        return $this->row([$number, $separator, $unit]);
    }

    private function parseSiNumberRangeCommand(string $source, int &$offset, string $command): string
    {
        $startNumber = $this->parseSiNumberGroup($source, $offset, $command);
        $endNumber = $this->parseSiNumberGroup($source, $offset, $command);
        $separator = '<mspace width="0.2222em"></mspace>';

        return $this->row([$startNumber, $separator, '<mo>–</mo>', $separator, $endNumber]);
    }

    private function parseSiNumberListCommand(string $source, int &$offset, string $command): string
    {
        $list = trim($this->readRequiredGroupText($source, $offset));
        if ($list === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' number list');
        }

        $numbers = array_map('trim', explode(';', $list));
        if (count($numbers) < 2) {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' number list with at least two entries');
        }

        $nodes = [];
        $lastIndex = count($numbers) - 1;
        foreach ($numbers as $index => $number) {
            if ($number === '') {
                throw new \InvalidArgumentException('Expected TeX \\' . $command . ' number at list item ' . ($index + 1));
            }

            if ($index > 0) {
                if ($index === $lastIndex) {
                    $nodes[] = '<mspace width="0.2222em"></mspace>';
                    $nodes[] = '<mtext>and</mtext>';
                    $nodes[] = '<mspace width="0.2222em"></mspace>';
                } else {
                    $nodes[] = '<mo>,</mo>';
                    $nodes[] = '<mspace width="0.2222em"></mspace>';
                }
            }

            $nodes[] = $this->siNumberMathMl($number, $command);
        }

        return $this->row($nodes);
    }

    private function parseSiNumberGroup(string $source, int &$offset, string $command): string
    {
        $number = trim($this->readRequiredGroupText($source, $offset));
        if ($number === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' number');
        }

        return $this->siNumberMathMl($number, $command);
    }

    private function siNumberMathMl(string $number, string $command): string
    {
        $compact = str_replace([' ', "\t", "\n", "\r", ','], '', $number);
        if ($compact === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' number');
        }

        if (preg_match('/^([+\\-]?(?:\\d+(?:\\.\\d+)?|\\.\\d+))[eE]([+\\-]?\\d+)$/', $compact, $matches) === 1) {
            $base = $this->normalizeSiNumberToken($matches[1], $command);
            $exponent = $this->normalizeSiIntegerToken($matches[2], $command);

            return '<mn>' . $this->esc($base) . '</mn>'
                . '<mo>×</mo>'
                . '<msup><mn>10</mn><mn>' . $this->esc($exponent) . '</mn></msup>';
        }

        return '<mn>' . $this->esc($this->normalizeSiNumberToken($compact, $command)) . '</mn>';
    }

    private function normalizeSiNumberToken(string $number, string $command): string
    {
        if (preg_match('/^[+\\-]?(?:\\d+(?:\\.\\d+)?|\\.\\d+)$/', $number) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' number ' . $number);
        }

        return str_starts_with($number, '+') ? substr($number, 1) : $number;
    }

    private function normalizeSiIntegerToken(string $number, string $command): string
    {
        if (preg_match('/^[+\\-]?\\d+$/', $number) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' exponent ' . $number);
        }

        return str_starts_with($number, '+') ? substr($number, 1) : $number;
    }

    private function parseOptionalQuantityPrefix(string $source, int &$offset, string $command): ?string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '[') {
            return null;
        }

        $argument = $this->readTexBracketArgument($source, $offset);
        if ($argument === null) {
            throw new \InvalidArgumentException('Unterminated TeX \\' . $command . ' quantity prefix at offset ' . $offset);
        }

        $offset = $argument['next'];
        if (trim($argument['value']) === '') {
            return null;
        }

        return $this->parseTexFragment($argument['value'], $command . ' quantity prefix');
    }

    private function parseSiUnitGroup(string $source, int &$offset, string $command): string
    {
        $unit = trim($this->readRequiredGroupText($source, $offset));
        if ($unit === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' unit');
        }

        return $this->parseSiUnitSequence($unit, $command);
    }

    private function parseSiUnitSequence(string $unit, string $command): string
    {
        $nodes = [];
        $offset = 0;
        $length = strlen($unit);

        while ($offset < $length) {
            while (($unit[$offset] ?? '') !== '' && ctype_space($unit[$offset])) {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $char = $unit[$offset];
            if ($char === '\\') {
                $offset++;
                $unitCommand = $this->readCommandName($unit, $offset);
                if (isset(self::SI_UNIT_POWER_COMMANDS[$unitCommand])) {
                    if ($nodes === []) {
                        throw new \InvalidArgumentException('Expected TeX siunitx unit before \\' . $unitCommand);
                    }

                    $base = array_pop($nodes);
                    $nodes[] = '<msup>' . $base . '<mn>' . self::SI_UNIT_POWER_COMMANDS[$unitCommand] . '</mn></msup>';
                    continue;
                }

                if ($unitCommand === 'tothe') {
                    if ($nodes === []) {
                        throw new \InvalidArgumentException('Expected TeX siunitx unit before \\tothe');
                    }

                    $power = $this->normalizeSiIntegerToken(trim($this->readRequiredGroupText($unit, $offset)), $command);
                    $base = array_pop($nodes);
                    $nodes[] = '<msup>' . $base . '<mn>' . $this->esc($power) . '</mn></msup>';
                    continue;
                }

                if (!isset(self::SI_UNIT_COMMANDS[$unitCommand])) {
                    throw new \InvalidArgumentException('Unsupported TeX siunitx unit \\' . $unitCommand);
                }

                $nodes[] = '<mtext>' . $this->esc(self::SI_UNIT_COMMANDS[$unitCommand]) . '</mtext>';
                continue;
            }

            if (str_contains('/.%', $char)) {
                $offset++;
                $nodes[] = '<mtext>' . $this->esc($char) . '</mtext>';
                continue;
            }

            if (ctype_alnum($char)) {
                $start = $offset;
                while (($unit[$offset] ?? '') !== '' && ctype_alnum($unit[$offset])) {
                    $offset++;
                }

                $nodes[] = '<mtext>' . $this->esc(substr($unit, $start, $offset - $start)) . '</mtext>';
                continue;
            }

            throw new \InvalidArgumentException('Unsupported TeX siunitx unit token at offset ' . $offset);
        }

        if ($nodes === []) {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' unit');
        }

        return $this->row($nodes);
    }

    private function parseSiAngleGroup(string $source, int &$offset): string
    {
        $angle = trim($this->readRequiredGroupText($source, $offset));
        if ($angle === '') {
            throw new \InvalidArgumentException('Expected TeX \\ang angle');
        }

        $parts = array_map('trim', explode(';', $angle));
        if (count($parts) > 3) {
            throw new \InvalidArgumentException('Unsupported TeX \\ang component count');
        }

        $symbols = ['°', '′', '″'];
        $nodes = [];
        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            $nodes[] = $this->siNumberMathMl($part, 'ang');
            $nodes[] = '<mtext>' . $this->esc($symbols[$index]) . '</mtext>';
        }

        if ($nodes === []) {
            throw new \InvalidArgumentException('Expected TeX \\ang angle component');
        }

        return $this->row($nodes);
    }

    private function parseNotCommand(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX relation after \\not at offset ' . $offset);
        }

        if ($char === '{') {
            $groupOffset = $offset;
            $relation = $this->readNotRelationGroup($source, $groupOffset);
            if ($relation !== null) {
                $offset = $groupOffset;

                return '<mo>' . $relation . '</mo>';
            }
        }

        if ($char === '\\') {
            $commandOffset = $offset + 1;
            $command = $this->readCommandName($source, $commandOffset);
            if (isset(self::NOT_RELATION_COMMANDS[$command])) {
                $offset = $commandOffset;

                return '<mo>' . self::NOT_RELATION_COMMANDS[$command] . '</mo>';
            }
        }

        if (isset(self::NOT_RELATION_TOKENS[$char])) {
            $offset++;

            return '<mo>' . self::NOT_RELATION_TOKENS[$char] . '</mo>';
        }

        $defaultScriptPlacement = null;

        return '<menclose notation="updiagonalstrike">'
            . $this->parseAtom($source, $offset, $defaultScriptPlacement)
            . '</menclose>';
    }

    private function readNotRelationGroup(string $source, int &$offset): ?string
    {
        $group = trim($this->readRequiredGroupText($source, $offset));
        if ($group === '') {
            throw new \InvalidArgumentException('Expected TeX relation after \\not at offset ' . $offset);
        }

        if (strlen($group) === 1 && isset(self::NOT_RELATION_TOKENS[$group])) {
            return self::NOT_RELATION_TOKENS[$group];
        }

        if (preg_match('/^\\\\([A-Za-z]+|.)$/', $group, $matches) === 1) {
            $command = $matches[1];
            if (isset(self::NOT_RELATION_COMMANDS[$command])) {
                return self::NOT_RELATION_COMMANDS[$command];
            }
        }

        return null;
    }

    private function parseArrowAccentCommand(string $source, int &$offset, string $command): string
    {
        $spec = self::ARROW_ACCENT_COMMANDS[$command];
        $base = $this->parseRequiredTexToken($source, $offset, $command . ' base');
        $arrow = '<mo stretchy="true">' . $this->esc($spec['glyph']) . '</mo>';

        if ($spec['position'] === 'over') {
            return '<mover accent="true">' . $base . $arrow . '</mover>';
        }

        return '<munder accentunder="true">' . $base . $arrow . '</munder>';
    }

    private function normalizeMathSpaceDimension(string $dimension, string $command): string
    {
        $dimension = trim($dimension);
        if ($dimension === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' dimension');
        }

        if (preg_match('/^[+\\-]?(?:\\d+(?:\\.\\d+)?|\\.\\d+)(?:em|ex|px|pt|pc|in|cm|mm|mu)$/', $dimension) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' dimension ' . $dimension);
        }

        return str_starts_with($dimension, '+') ? substr($dimension, 1) : $dimension;
    }

    private function readMathSpaceDimensionArgument(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        $tail = substr($source, $offset);

        if (preg_match('/^[+\\-]?(?:\\d+(?:\\.\\d+)?|\\.\\d+)\\s*(?:em|ex|px|pt|pc|in|cm|mm|mu)/', $tail, $matches) !== 1) {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' dimension at offset ' . $start);
        }

        $rawDimension = $matches[0];
        $offset += strlen($rawDimension);
        $dimension = preg_replace('/\\s+/', '', $rawDimension);

        return $this->normalizeMathSpaceDimension(is_string($dimension) ? $dimension : $rawDimension, $command);
    }

    private function parseSubstackCommand(string $source, int &$offset): string
    {
        $content = $this->readRequiredGroupText($source, $offset);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX substack row content at final row');
        }

        $rows = $this->splitAlignmentRows($content, 'substack');
        foreach ($rows as $rowIndex => $row) {
            if (count($row) !== 1) {
                throw new \InvalidArgumentException('Expected one-column TeX substack row at row ' . ($rowIndex + 1));
            }

            if (trim($row[0]) === '') {
                throw new \InvalidArgumentException('Expected TeX substack row content at row ' . ($rowIndex + 1));
            }
        }

        return $this->environmentTable($rows, ' columnalign="center" rowspacing="0.1em"');
    }

    /**
     * @param list<list<string>> $rows
     */
    private function validateAmsRowEnvironmentRows(array $rows, string $environment, int $columns): void
    {
        foreach ($rows as $rowIndex => $row) {
            if ($this->validateIntertextRowPosition($rows, $rowIndex, $environment)) {
                continue;
            }

            if (count($row) !== $columns) {
                throw new \InvalidArgumentException('Expected ' . $columns . '-column TeX ' . $environment . ' row at row ' . ($rowIndex + 1));
            }

            $hasContent = false;
            foreach ($row as $cell) {
                if (trim($cell) !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if (!$hasContent) {
                throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at row ' . ($rowIndex + 1));
            }
        }
    }

    /**
     * @param list<list<string>> $rows
     */
    private function validateAmsFlushAlignedRows(array $rows, string $environment): int
    {
        $columns = 0;
        foreach ($rows as $rowIndex => $row) {
            if ($this->validateIntertextRowPosition($rows, $rowIndex, $environment)) {
                continue;
            }

            $rowColumns = count($row);
            if ($rowColumns < 2) {
                throw new \InvalidArgumentException('Expected TeX ' . $environment . ' alignment markers at row ' . ($rowIndex + 1));
            }

            if ($rowColumns > 8) {
                throw new \InvalidArgumentException('Unsupported TeX ' . $environment . ' column count ' . $rowColumns . ' at row ' . ($rowIndex + 1));
            }

            $hasContent = false;
            foreach ($row as $cell) {
                if (trim($cell) !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if (!$hasContent) {
                throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at row ' . ($rowIndex + 1));
            }

            $columns = max($columns, $rowColumns);
        }

        return $columns;
    }

    /**
     * @param list<list<string>> $rows
     */
    private function validateIntertextRowPosition(array $rows, int $rowIndex, string $environment): bool
    {
        $row = $rows[$rowIndex] ?? [];
        if (!self::isIntertextRow($row)) {
            return false;
        }

        $rowNumber = $rowIndex + 1;
        if (
            $rowIndex === 0
            || $rowIndex === count($rows) - 1
            || self::isIntertextRow($rows[$rowIndex - 1] ?? [])
            || self::isIntertextRow($rows[$rowIndex + 1] ?? [])
        ) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' intertext between equation rows at row ' . $rowNumber);
        }

        return true;
    }

    /**
     * @param list<string> $row
     */
    private static function isIntertextRow(array $row): bool
    {
        return count($row) === 3 && ($row[0] ?? '') === self::INTERTEXT_ROW_MARKER;
    }

    private function flushAlignedColumnAlign(int $columns): string
    {
        $alignments = [];
        for ($index = 0; $index < $columns; $index++) {
            $alignments[] = $index % 2 === 0 ? 'left' : 'right';
        }

        return implode(' ', $alignments);
    }

    private function endsWithTopLevelRowSeparator(string $content): bool
    {
        $depth = 0;
        $separatorIsLastSignificantToken = false;
        $offset = 0;
        $length = strlen($content);
        while ($offset < $length) {
            $char = $content[$offset];
            if ($char === '\\') {
                if ($depth === 0 && ($content[$offset + 1] ?? '') === '\\') {
                    $separatorIsLastSignificantToken = true;
                    $offset += 2;
                    $this->skipOptionalAlignmentRowSpacingArgument($content, $offset);
                    continue;
                }

                $separatorIsLastSignificantToken = false;
                $offset++;
                if (($content[$offset] ?? '') !== '') {
                    $offset++;
                }
                continue;
            }

            if ($char === '%') {
                $this->skipTexLineComment($content, $offset);
                continue;
            }

            if ($char === '{') {
                $depth++;
                $separatorIsLastSignificantToken = false;
                $offset++;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $separatorIsLastSignificantToken = false;
                $offset++;
                continue;
            }

            if (!ctype_space($char)) {
                $separatorIsLastSignificantToken = false;
            }

            $offset++;
        }

        return $separatorIsLastSignificantToken;
    }

    private function parseMathVariantArgument(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX math variant argument for \\' . $command . ' at offset ' . $offset);
        }

        if ($char === '{') {
            return $this->parseRequiredNonEmptyGroup($source, $offset, 'math variant');
        }

        return $this->parseAtom($source, $offset);
    }

    private function arrayColumnAlign(string $columnSpec): string
    {
        return $this->arrayColumnSpec($columnSpec, false)['columnalign'];
    }

    private function arrayColumnAttributes(string $columnSpec): string
    {
        $spec = $this->arrayColumnSpec($columnSpec);

        return $this->arrayColumnAttributesFromSpec($spec);
    }

    /**
     * @param array{columnalign:string, columnhooks:list<string>, columnlines:list<string>, columnwidths:list<string>, columnvaligns:list<string>, columns:int} $spec
     */
    private function arrayColumnAttributesFromSpec(array $spec): string
    {
        $attributes = ' columnalign="' . $this->esc($spec['columnalign']) . '"';
        if (in_array(false, array_map(static fn (string $width): bool => $width === 'auto', $spec['columnwidths']), true)) {
            $attributes .= ' columnwidth="' . $this->esc(implode(' ', $spec['columnwidths'])) . '"';
        }
        if (in_array(false, array_map(static fn (string $valign): bool => $valign === 'baseline', $spec['columnvaligns']), true)) {
            $attributes .= ' data-tex-column-valign="' . $this->esc(implode(' ', $spec['columnvaligns'])) . '"';
        }
        if ($spec['columnlines'] !== []) {
            $attributes .= ' columnlines="' . $this->esc(implode(' ', $spec['columnlines'])) . '"';
        }
        if ($spec['columnhooks'] !== []) {
            $attributes .= ' data-tex-column-hooks="' . $this->esc(implode(' | ', $spec['columnhooks'])) . '"';
        }

        return $attributes;
    }

    private function expandArrayColumnRepeats(string $columnSpec, int $depth = 0): string
    {
        if ($depth > 4) {
            throw new \InvalidArgumentException('Unsupported nested TeX array repeated-column preamble');
        }

        $expanded = '';
        $length = strlen($columnSpec);
        for ($offset = 0; $offset < $length;) {
            $char = $columnSpec[$offset];
            if ($char === '{') {
                $argument = $this->readTexBraceArgument($columnSpec, $offset);
                if ($argument === null) {
                    throw new \InvalidArgumentException('Unclosed TeX array column preamble group at offset ' . $offset);
                }

                $expanded .= substr($columnSpec, $offset, $argument['next'] - $offset);
                $offset = $argument['next'];
                continue;
            }

            if ($char !== '*') {
                $expanded .= $char;
                $offset++;
                continue;
            }

            $countOffset = $offset + 1;
            $this->skipWhitespace($columnSpec, $countOffset);
            $countArgument = $this->readTexBraceArgument($columnSpec, $countOffset);
            if ($countArgument === null) {
                throw new \InvalidArgumentException('Expected TeX array repeated-column count at offset ' . ($offset + 1));
            }

            $countText = trim($countArgument['value']);
            if (preg_match('/^[1-9][0-9]*$/', $countText) !== 1) {
                throw new \InvalidArgumentException('Unsupported TeX array repeated-column count ' . $countText);
            }

            $count = (int) $countText;
            if ($count > 8) {
                throw new \InvalidArgumentException('Unsupported TeX array repeated-column count ' . $countText);
            }

            $bodyOffset = $countArgument['next'];
            $this->skipWhitespace($columnSpec, $bodyOffset);
            $bodyArgument = $this->readTexBraceArgument($columnSpec, $bodyOffset);
            if ($bodyArgument === null) {
                throw new \InvalidArgumentException('Expected TeX array repeated-column preamble at offset ' . $bodyOffset);
            }

            $body = $this->expandArrayColumnRepeats($bodyArgument['value'], $depth + 1);
            if (trim($body) === '') {
                throw new \InvalidArgumentException('Expected TeX array repeated-column preamble content at offset ' . $bodyOffset);
            }

            $expanded .= str_repeat($body, $count);
            if (strlen($expanded) > 256) {
                throw new \InvalidArgumentException('Unsupported TeX array repeated-column preamble expansion');
            }

            $offset = $bodyArgument['next'];
        }

        return $expanded;
    }

    /**
     * @return array{columnalign:string, columnhooks:list<string>, columnlines:list<string>, columnwidths:list<string>, columnvaligns:list<string>, columns:int}
     */
    private function arrayColumnSpec(string $columnSpec, bool $allowWidthColumns = true): array
    {
        $columnSpec = $this->expandArrayColumnRepeats($columnSpec);
        $alignments = [];
        $columnHooks = [];
        $columnLines = [];
        $columnWidths = [];
        $columnVerticalAlignments = [];
        $pendingColumnHooks = [];
        $lineBeforeNextColumn = false;
        $length = strlen($columnSpec);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $columnSpec[$offset];
            if (ctype_space($char)) {
                continue;
            }

            if ($char === '|') {
                if ($alignments !== []) {
                    $lineBeforeNextColumn = true;
                }
                continue;
            }

            if ($char === '>' || $char === '<' || $char === '@' || $char === '!') {
                if (!$allowWidthColumns) {
                    throw new \InvalidArgumentException('Unsupported TeX array column specifier ' . $char . ' at offset ' . $offset);
                }

                $hook = $this->readArrayPreambleHook($columnSpec, $offset, $char);
                if ($char === '>') {
                    $pendingColumnHooks[] = $hook['source'];
                } elseif ($char === '<') {
                    if ($alignments === []) {
                        throw new \InvalidArgumentException('Expected TeX array column before post-column hook at offset ' . $offset);
                    }

                    $columnHooks[] = 'post-' . count($alignments) . ':' . $hook['source'];
                } elseif ($char === '@') {
                    if ($alignments === []) {
                        $columnHooks[] = 'gap-before-1:' . $hook['source'];
                    } else {
                        $columnHooks[] = 'gap-after-' . count($alignments) . ':' . $hook['source'];
                    }
                } elseif ($alignments === []) {
                    $columnHooks[] = 'separator-before-1:' . $hook['source'];
                } else {
                    $columnHooks[] = 'separator-after-' . count($alignments) . ':' . $hook['source'];
                }

                $offset = $hook['next'] - 1;
                continue;
            }

            if ($char === 'l') {
                $this->appendArrayColumnSpec($alignments, $columnLines, $columnWidths, $columnVerticalAlignments, $lineBeforeNextColumn, 'left', 'auto', 'baseline');
                $this->flushPendingArrayColumnHooks($columnHooks, $pendingColumnHooks, count($alignments));
                $lineBeforeNextColumn = false;
                continue;
            }

            if ($char === 'c') {
                $this->appendArrayColumnSpec($alignments, $columnLines, $columnWidths, $columnVerticalAlignments, $lineBeforeNextColumn, 'center', 'auto', 'baseline');
                $this->flushPendingArrayColumnHooks($columnHooks, $pendingColumnHooks, count($alignments));
                $lineBeforeNextColumn = false;
                continue;
            }

            if ($char === 'r') {
                $this->appendArrayColumnSpec($alignments, $columnLines, $columnWidths, $columnVerticalAlignments, $lineBeforeNextColumn, 'right', 'auto', 'baseline');
                $this->flushPendingArrayColumnHooks($columnHooks, $pendingColumnHooks, count($alignments));
                $lineBeforeNextColumn = false;
                continue;
            }

            if ($char === 'p' || $char === 'm' || $char === 'b') {
                if (!$allowWidthColumns) {
                    throw new \InvalidArgumentException('Unsupported TeX array column specifier ' . $char . ' at offset ' . $offset);
                }

                $argumentOffset = $offset + 1;
                $this->skipWhitespace($columnSpec, $argumentOffset);
                $argument = $this->readTexBraceArgument($columnSpec, $argumentOffset);
                if ($argument === null) {
                    throw new \InvalidArgumentException('Expected TeX array ' . $char . '-column width at offset ' . ($offset + 1));
                }

                $width = $this->normalizeArrayColumnWidth($argument['value']);
                $verticalAlignment = match ($char) {
                    'p' => 'top',
                    'm' => 'middle',
                    'b' => 'bottom',
                };
                $this->appendArrayColumnSpec($alignments, $columnLines, $columnWidths, $columnVerticalAlignments, $lineBeforeNextColumn, 'left', $width, $verticalAlignment);
                $this->flushPendingArrayColumnHooks($columnHooks, $pendingColumnHooks, count($alignments));
                $lineBeforeNextColumn = false;
                $offset = $argument['next'] - 1;
                continue;
            }

            throw new \InvalidArgumentException('Unsupported TeX array column specifier ' . $char . ' at offset ' . $offset);
        }

        if ($alignments === []) {
            throw new \InvalidArgumentException('Expected TeX array column specifier');
        }

        if ($pendingColumnHooks !== []) {
            throw new \InvalidArgumentException('Expected TeX array column after pre-column hook');
        }

        if (!in_array('solid', $columnLines, true)) {
            $columnLines = [];
        }

        return [
            'columnalign' => implode(' ', $alignments),
            'columnhooks' => $columnHooks,
            'columnlines' => $columnLines,
            'columnwidths' => $columnWidths,
            'columnvaligns' => $columnVerticalAlignments,
            'columns' => count($alignments),
        ];
    }

    /**
     * @return array{source:string, next:int}
     */
    private function readArrayPreambleHook(string $columnSpec, int $offset, string $specifier): array
    {
        $argumentOffset = $offset + 1;
        $this->skipWhitespace($columnSpec, $argumentOffset);
        $argument = $this->readTexBraceArgument($columnSpec, $argumentOffset);
        if ($argument === null) {
            throw new \InvalidArgumentException('Expected TeX array ' . $specifier . '-hook group at offset ' . ($offset + 1));
        }

        return [
            'source' => $this->normalizeArrayHookSource($argument['value'], $specifier),
            'next' => $argument['next'],
        ];
    }

    private function normalizeArrayHookSource(string $source, string $specifier): string
    {
        $source = trim($source);
        if ($source === '') {
            throw new \InvalidArgumentException('Expected TeX array ' . $specifier . '-hook content');
        }

        if (strlen($source) > 96) {
            throw new \InvalidArgumentException('Unsupported TeX array ' . $specifier . '-hook length');
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $source) === 1) {
            throw new \InvalidArgumentException('Unsupported TeX array ' . $specifier . '-hook control character');
        }

        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '&') {
                throw new \InvalidArgumentException('Unsupported TeX array ' . $specifier . '-hook alignment separator');
            }

            if ($char === '{' || $char === '}') {
                throw new \InvalidArgumentException('Unsupported TeX array ' . $specifier . '-hook group');
            }

            if ($char !== '\\') {
                $offset++;
                continue;
            }

            if (($source[$offset + 1] ?? '') === '\\') {
                throw new \InvalidArgumentException('Unsupported TeX array ' . $specifier . '-hook row separator');
            }

            $commandOffset = $offset + 1;
            $command = $this->readCommandName($source, $commandOffset);
            if (!isset(self::ARRAY_HOOK_COMMANDS[$command])) {
                throw new \InvalidArgumentException('Unsupported TeX array hook command \\' . $command);
            }

            $offset = $commandOffset;
            if ($command === 'hspace' || $command === 'mspace') {
                $dimension = $this->readRequiredGroupText($source, $offset);
                $this->normalizeMathSpaceDimension($dimension, $command);
                continue;
            }

            if ($command === 'kern' || $command === 'mkern') {
                $this->readMathSpaceDimensionArgument($source, $offset, $command);
                continue;
            }

            if ($command === 'mbox' || $command === 'text') {
                $text = $this->readRequiredGroupText($source, $offset);
                if (trim($text) === '') {
                    throw new \InvalidArgumentException('Expected TeX array hook text content');
                }
                if (str_contains($text, '&') || str_contains($text, '\\\\')) {
                    throw new \InvalidArgumentException('Unsupported TeX array hook text content');
                }
            }
        }

        $normalized = preg_replace('/\s+/', ' ', $source);

        return is_string($normalized) ? $normalized : $source;
    }

    /**
     * @param list<string> $columnHooks
     * @param list<string> $pendingColumnHooks
     */
    private function flushPendingArrayColumnHooks(array &$columnHooks, array &$pendingColumnHooks, int $columnIndex): void
    {
        foreach ($pendingColumnHooks as $hook) {
            $columnHooks[] = 'pre-' . $columnIndex . ':' . $hook;
        }

        $pendingColumnHooks = [];
    }

    /**
     * @param list<string> $alignments
     * @param list<string> $columnLines
     * @param list<string> $columnWidths
     * @param list<string> $columnVerticalAlignments
     */
    private function appendArrayColumnSpec(
        array &$alignments,
        array &$columnLines,
        array &$columnWidths,
        array &$columnVerticalAlignments,
        bool $lineBeforeNextColumn,
        string $alignment,
        string $width,
        string $verticalAlignment
    ): void {
        if ($alignments !== []) {
            $columnLines[] = $lineBeforeNextColumn ? 'solid' : 'none';
        }

        $alignments[] = $alignment;
        $columnWidths[] = $width;
        $columnVerticalAlignments[] = $verticalAlignment;
    }

    private function normalizeArrayColumnWidth(string $width): string
    {
        $width = trim($width);
        if ($width === '') {
            throw new \InvalidArgumentException('Expected TeX array column width');
        }

        if (preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:em|ex|px|pt|pc|in|cm|mm)$/', $width) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX array column width ' . $width);
        }

        return $width;
    }

    /**
     * @param list<list<string>> $rows
     * @return array{rows:list<list<string>>, attributes:string}
     */
    private function stripArrayRowRules(array $rows, int $columns): array
    {
        $strippedRows = [];
        $lineBeforeRow = [];
        $clineAfterRows = [];
        $topClines = [];
        $topLine = false;
        $bottomLine = false;
        $lastRowIndex = count($rows) - 1;

        foreach ($rows as $rowIndex => $row) {
            if ($row === []) {
                continue;
            }

            $hlineCount = 0;
            $clineRanges = [];
            $row[0] = $this->stripLeadingArrayRules($row[0], $hlineCount, $clineRanges, $columns);
            if ($hlineCount > 0) {
                if ($rowIndex === 0) {
                    $topLine = true;
                } else {
                    $lineBeforeRow[count($strippedRows)] = true;
                }
            }
            if ($clineRanges !== []) {
                if ($rowIndex === 0) {
                    $topClines = array_merge($topClines, $clineRanges);
                } else {
                    $afterRow = count($strippedRows);
                    $clineAfterRows[$afterRow] = array_merge($clineAfterRows[$afterRow] ?? [], $clineRanges);
                }
            }

            if ($this->arrayRowIsEmpty($row)) {
                if ($hlineCount > 0 && $rowIndex === $lastRowIndex) {
                    $bottomLine = true;
                    continue;
                }

                if ($clineRanges !== [] && $rowIndex === $lastRowIndex && $strippedRows !== []) {
                    continue;
                }

                throw new \InvalidArgumentException('Expected TeX array row content at row ' . ($rowIndex + 1));
            }

            $strippedRows[] = $row;
        }

        if ($strippedRows === []) {
            throw new \InvalidArgumentException('Empty TeX environment array');
        }

        $attributes = '';
        if ($topLine) {
            $attributes .= ' data-tex-topline="solid"';
        }
        if ($topClines !== []) {
            $attributes .= ' data-tex-topclines="' . $this->esc(implode(',', $topClines)) . '"';
        }

        $rowLines = [];
        for ($rowIndex = 1; $rowIndex < count($strippedRows); $rowIndex++) {
            $rowLines[] = ($lineBeforeRow[$rowIndex] ?? false) ? 'solid' : 'none';
        }
        if (in_array('solid', $rowLines, true)) {
            $attributes .= ' rowlines="' . $this->esc(implode(' ', $rowLines)) . '"';
        }
        if ($clineAfterRows !== []) {
            $attributes .= ' data-tex-clines="' . $this->esc($this->formatArrayClineMetadata($clineAfterRows)) . '"';
        }

        if ($bottomLine) {
            $attributes .= ' data-tex-bottomline="solid"';
        }

        return [
            'rows' => $strippedRows,
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $clineRanges
     */
    private function stripLeadingArrayRules(string $cell, int &$hlineCount, array &$clineRanges, int $columns): string
    {
        $hlineCount = 0;
        $clineRanges = [];
        $offset = 0;
        $length = strlen($cell);

        while ($offset < $length) {
            $this->skipWhitespace($cell, $offset);
            if (($cell[$offset] ?? '') !== '\\') {
                break;
            }

            $commandOffset = $offset + 1;
            $command = $this->readCommandName($cell, $commandOffset);
            if ($command === 'hline') {
                $hlineCount++;
                $offset = $commandOffset;
                continue;
            }

            if ($command === 'cline') {
                $clineRanges[] = $this->normalizeArrayClineRange(
                    $this->readRequiredGroupText($cell, $commandOffset),
                    $columns
                );
                $offset = $commandOffset;
                continue;
            }

            break;
        }

        return ltrim(substr($cell, $offset));
    }

    private function normalizeArrayClineRange(string $range, int $columns): string
    {
        $range = trim($range);
        if (preg_match('/^([1-9][0-9]*)\s*-\s*([1-9][0-9]*)$/', $range, $matches) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX \\cline range ' . $range);
        }

        $start = (int) $matches[1];
        $end = (int) $matches[2];
        if ($start > $end || $end > $columns) {
            throw new \InvalidArgumentException('Unsupported TeX \\cline range ' . $range);
        }

        return $start . '-' . $end;
    }

    /**
     * @param array<int, list<string>> $clineAfterRows
     */
    private function formatArrayClineMetadata(array $clineAfterRows): string
    {
        ksort($clineAfterRows);
        $segments = [];
        foreach ($clineAfterRows as $rowNumber => $ranges) {
            if ($rowNumber < 1 || $ranges === []) {
                continue;
            }

            $segments[] = 'after-row-' . $rowNumber . ':' . implode(',', $ranges);
        }

        return implode(' ', $segments);
    }

    /**
     * @param list<string> $row
     */
    private function arrayRowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<list<string>> $rows
     */
    private function environmentTable(array $rows, string $attributes, bool $allowRowMetadata = false, string $environment = '', ?int $arrayColumnCount = null, ?int $intertextColumnCount = null): string
    {
        $table = '<mtable' . $attributes . '>';
        foreach ($rows as $rowIndex => $row) {
            if (self::isIntertextRow($row)) {
                $table .= $this->renderIntertextRow($row, $intertextColumnCount);
                continue;
            }

            $metadata = [
                'labelId' => null,
                'tag' => null,
                'tagStarred' => false,
                'suppressNumbering' => false,
            ];

            if ($allowRowMetadata) {
                $parsed = $this->extractEnvironmentRowMetadata($row, $environment, $rowIndex);
                $row = $parsed['cells'];
                $metadata = [
                    'labelId' => $parsed['labelId'],
                    'tag' => $parsed['tag'],
                    'tagStarred' => $parsed['tagStarred'],
                    'suppressNumbering' => $parsed['suppressNumbering'],
                ];
            }

            if ($metadata['tag'] !== null) {
                $tagText = $metadata['tagStarred'] ? $metadata['tag'] : '(' . $metadata['tag'] . ')';
                $table .= '<mlabeledtr' . ($metadata['labelId'] !== null ? ' id="' . $this->esc($metadata['labelId']) . '"' : '') . '>'
                    . '<mtd><mtext>' . $this->esc($tagText) . '</mtext></mtd>';
            } else {
                $table .= '<mtr' . ($metadata['labelId'] !== null ? ' id="' . $this->esc($metadata['labelId']) . '"' : '') . '>';
            }

            $occupiedColumns = 0;
            $rowHasArrayMulticolumn = false;
            foreach ($row as $cellIndex => $cell) {
                if ($arrayColumnCount !== null) {
                    $multicolumn = $this->parseArrayMulticolumnCell($cell, $rowIndex, $cellIndex, $arrayColumnCount, $occupiedColumns + 1);
                    if ($multicolumn !== null) {
                        $rowHasArrayMulticolumn = true;
                        $occupiedColumns += $multicolumn['span'];
                        $table .= '<mtd' . $multicolumn['attributes'] . '>' . $multicolumn['content'] . '</mtd>';
                        continue;
                    }

                    if ($rowHasArrayMulticolumn && $occupiedColumns + 1 > $arrayColumnCount) {
                        throw new \InvalidArgumentException(
                            'TeX array row exceeds declared columns after multicolumn at row ' . ($rowIndex + 1)
                                . ', cell ' . ($cellIndex + 1)
                        );
                    }
                }

                $occupiedColumns++;
                $table .= '<mtd>' . $this->parseEnvironmentCell($cell) . '</mtd>';
            }
            $table .= $metadata['tag'] !== null ? '</mlabeledtr>' : '</mtr>';
        }

        return $table . '</mtable>';
    }

    /**
     * @param list<string> $row
     */
    private function renderIntertextRow(array $row, ?int $columnCount): string
    {
        $kind = ($row[1] ?? '') === 'shortintertext' ? 'short' : 'normal';
        $columnSpan = $columnCount !== null && $columnCount > 0 ? ' columnspan="' . $columnCount . '"' : '';

        return '<mtr data-tex-intertext="' . $kind . '"><mtd' . $columnSpan . '><mtext>' . $this->esc($row[2] ?? '') . '</mtext></mtd></mtr>';
    }

    /**
     * @return array{attributes:string, content:string, span:int}|null
     */
    private function parseArrayMulticolumnCell(string $cell, int $rowIndex, int $cellIndex, int $arrayColumnCount, int $nextColumn): ?array
    {
        $source = trim($cell);
        if ($source === '' || !str_starts_with($source, '\\multicolumn')) {
            return null;
        }

        $offset = 1;
        if ($this->readCommandName($source, $offset) !== 'multicolumn') {
            return null;
        }

        $span = $this->normalizeArrayMulticolumnSpan($this->readRequiredGroupText($source, $offset));
        if ($nextColumn + $span - 1 > $arrayColumnCount) {
            throw new \InvalidArgumentException(
                'TeX array multicolumn span exceeds declared columns at row ' . ($rowIndex + 1)
                    . ', cell ' . ($cellIndex + 1)
            );
        }

        $columnSpec = $this->arrayMulticolumnColumnSpec($this->readRequiredGroupText($source, $offset));
        $contentSource = trim($this->readRequiredGroupText($source, $offset));
        if ($contentSource === '') {
            throw new \InvalidArgumentException('Expected TeX array multicolumn content at row ' . ($rowIndex + 1) . ', cell ' . ($cellIndex + 1));
        }

        $this->skipWhitespace($source, $offset);
        if ($offset < strlen($source)) {
            throw new \InvalidArgumentException('Unsupported TeX token after array multicolumn at offset ' . $offset);
        }

        return [
            'attributes' => $this->arrayMulticolumnCellAttributes($span, $columnSpec),
            'content' => $this->parseTexFragment($contentSource, 'array multicolumn content'),
            'span' => $span,
        ];
    }

    private function normalizeArrayMulticolumnSpan(string $span): int
    {
        $span = trim($span);
        if (preg_match('/^[1-9][0-9]*$/', $span) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX array multicolumn span ' . $span);
        }

        $value = (int) $span;
        if ($value > 8) {
            throw new \InvalidArgumentException('Unsupported TeX array multicolumn span ' . $span);
        }

        return $value;
    }

    /**
     * @return array{spec:array{columnalign:string, columnhooks:list<string>, columnlines:list<string>, columnwidths:list<string>, columnvaligns:list<string>, columns:int}, lines:list<string>}
     */
    private function arrayMulticolumnColumnSpec(string $columnSpec): array
    {
        $source = trim($columnSpec);
        if ($source === '') {
            throw new \InvalidArgumentException('Expected TeX array multicolumn column specifier');
        }

        $leftLine = false;
        while (str_starts_with($source, '|')) {
            $leftLine = true;
            $source = ltrim(substr($source, 1));
        }

        $rightLine = false;
        while (str_ends_with($source, '|')) {
            $rightLine = true;
            $source = rtrim(substr($source, 0, -1));
        }

        $spec = $this->arrayColumnSpec($source);
        if ($spec['columns'] !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX array multicolumn column specifier ' . $columnSpec);
        }

        $lines = [];
        if ($leftLine) {
            $lines[] = 'left';
        }
        if ($rightLine) {
            $lines[] = 'right';
        }

        return [
            'spec' => $spec,
            'lines' => $lines,
        ];
    }

    /**
     * @param array{spec:array{columnalign:string, columnhooks:list<string>, columnlines:list<string>, columnwidths:list<string>, columnvaligns:list<string>, columns:int}, lines:list<string>} $columnSpec
     */
    private function arrayMulticolumnCellAttributes(int $span, array $columnSpec): string
    {
        $spec = $columnSpec['spec'];
        $attributes = ' columnspan="' . $span . '" columnalign="' . $this->esc($spec['columnalign']) . '"';

        $width = $spec['columnwidths'][0] ?? 'auto';
        if ($width !== 'auto') {
            $attributes .= ' columnwidth="' . $this->esc($width) . '"';
        }

        $verticalAlignment = $spec['columnvaligns'][0] ?? 'baseline';
        if ($verticalAlignment !== 'baseline') {
            $attributes .= ' data-tex-column-valign="' . $this->esc($verticalAlignment) . '"';
        }

        if ($spec['columnhooks'] !== []) {
            $attributes .= ' data-tex-column-hooks="' . $this->esc(implode(' | ', $spec['columnhooks'])) . '"';
        }

        if ($columnSpec['lines'] !== []) {
            $attributes .= ' data-tex-column-lines="' . $this->esc(implode(' ', $columnSpec['lines'])) . '"';
        }

        return $attributes;
    }

    /**
     * @param list<string> $row
     * @return array{cells:list<string>, label:?string, labelId:?string, tag:?string, tagStarred:bool, suppressNumbering:bool}
     */
    private function extractEnvironmentRowMetadata(array $row, string $environment, int $rowIndex): array
    {
        $label = null;
        $labelId = null;
        $tag = null;
        $tagStarred = false;
        $suppressNumbering = false;
        $cells = [];

        foreach ($row as $cell) {
            $parsed = $this->stripEnvironmentCellRowMetadata($cell, $environment, $rowIndex);
            $cells[] = trim($parsed['cell']);
            $suppressNumbering = $suppressNumbering || $parsed['suppressNumbering'];

            if ($parsed['label'] !== null) {
                if ($label !== null) {
                    throw new \InvalidArgumentException('Duplicate TeX ' . $environment . ' row label at row ' . ($rowIndex + 1));
                }

                $label = $parsed['label'];
                $labelId = $this->normalizeEquationLabelId($parsed['label']);
            }

            if ($parsed['tag'] !== null) {
                if ($tag !== null) {
                    throw new \InvalidArgumentException('Duplicate TeX ' . $environment . ' row tag at row ' . ($rowIndex + 1));
                }

                $tag = $parsed['tag'];
                $tagStarred = $parsed['tagStarred'];
            }
        }

        $hasContent = false;
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                $hasContent = true;
                break;
            }
        }

        if (!$hasContent) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at row ' . ($rowIndex + 1));
        }

        return [
            'cells' => $cells,
            'label' => $label,
            'labelId' => $labelId,
            'tag' => $tag,
            'tagStarred' => $tagStarred,
            'suppressNumbering' => $suppressNumbering,
        ];
    }

    /**
     * @return array{cell:string, label:?string, tag:?string, tagStarred:bool, suppressNumbering:bool}
     */
    private function stripEnvironmentCellRowMetadata(string $source, string $environment, int $rowIndex): array
    {
        $output = '';
        $label = null;
        $tag = null;
        $tagStarred = false;
        $suppressNumbering = false;
        $depth = 0;
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $command = $this->readCommandName($source, $commandOffset);
                if ($depth === 0 && $command === 'begin') {
                    $environmentOffset = $commandOffset;
                    $nestedEnvironment = $this->readRequiredGroupText($source, $environmentOffset);
                    $this->readEnvironmentContent($source, $environmentOffset, $nestedEnvironment);
                    $output .= substr($source, $offset, $environmentOffset - $offset);
                    $offset = $environmentOffset;
                    continue;
                }

                if ($depth === 0 && ($command === 'notag' || $command === 'nonumber')) {
                    $suppressNumbering = true;
                    $offset = $commandOffset;
                    continue;
                }

                if ($depth === 0 && ($command === 'label' || $command === 'tag')) {
                    $cursor = $commandOffset;
                    $starred = false;
                    if ($command === 'tag' && ($source[$cursor] ?? '') === '*') {
                        $starred = true;
                        $cursor++;
                    }

                    $this->skipWhitespace($source, $cursor);
                    $argument = $this->readTexBraceArgument($source, $cursor);
                    if ($argument === null) {
                        throw new \InvalidArgumentException('Expected TeX \\' . $command . ' group in ' . $environment . ' row ' . ($rowIndex + 1));
                    }

                    $value = trim($argument['value']);
                    if ($value === '') {
                        throw new \InvalidArgumentException('Expected TeX \\' . $command . ' content in ' . $environment . ' row ' . ($rowIndex + 1));
                    }

                    if ($command === 'label') {
                        if ($label !== null) {
                            throw new \InvalidArgumentException('Duplicate TeX ' . $environment . ' row label at row ' . ($rowIndex + 1));
                        }

                        $label = $value;
                    } else {
                        if ($tag !== null) {
                            throw new \InvalidArgumentException('Duplicate TeX ' . $environment . ' row tag at row ' . ($rowIndex + 1));
                        }

                        $tag = $value;
                        $tagStarred = $starred;
                    }

                    $offset = $argument['next'];
                    continue;
                }

                $output .= $char;
                $offset++;
                if (($source[$offset] ?? '') !== '' && !ctype_alpha($source[$offset])) {
                    $output .= $source[$offset];
                    $offset++;
                }
                continue;
            }

            if ($char === '{') {
                $depth++;
                $output .= $char;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $output .= $char;
                $offset++;
                continue;
            }

            $output .= $char;
            $offset++;
        }

        return [
            'cell' => $output,
            'label' => $label,
            'tag' => $tag,
            'tagStarred' => $tagStarred,
            'suppressNumbering' => $suppressNumbering,
        ];
    }

    /**
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function collectEquationReferenceLabelsFromDocument(AstNode $node, array &$labels, int &$nextAutomaticNumber): void
    {
        if ($node->type === 'math') {
            $this->collectEquationReferenceLabelsFromTex(
                (string) $node->attr('text', ''),
                $labels,
                $nextAutomaticNumber,
                $node->attr('display') === true
            );
        }

        foreach ($node->children as $child) {
            $this->collectEquationReferenceLabelsFromDocument($child, $labels, $nextAutomaticNumber);
        }
    }

    /**
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function collectEquationReferenceLabelsFromTex(string $source, array &$labels, int &$nextAutomaticNumber, bool $numberUntagged): void
    {
        $equation = $this->extractEquationMetadata($source);
        if ($equation['label'] !== null) {
            $automaticReference = null;
            if ($numberUntagged && $equation['tag'] === null && !$equation['suppressNumbering']) {
                $automaticReference = (string) $nextAutomaticNumber;
                $nextAutomaticNumber++;
            }

            $this->registerEquationReferenceLabel($labels, $equation['label'], $equation['tag'], $equation['tagStarred'], $automaticReference);
        }

        $this->collectEnvironmentEquationReferenceLabelsFromTex($source, $labels, $nextAutomaticNumber, $numberUntagged);
    }

    /**
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function collectEnvironmentEquationReferenceLabelsFromTex(string $source, array &$labels, int &$nextAutomaticNumber, bool $numberUntagged): void
    {
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            if ($source[$offset] !== '\\') {
                $offset++;
                continue;
            }

            $commandOffset = $offset + 1;
            $command = $this->readCommandName($source, $commandOffset);
            if ($command !== 'begin') {
                $offset++;
                continue;
            }

            $environmentOffset = $commandOffset;
            $environment = $this->readRequiredGroupText($source, $environmentOffset);
            $contentOffset = $environmentOffset;
            $this->readOptionalAmsEnvironmentPositionAttributes($source, $contentOffset, $environment);
            $alignedAtPairs = null;
            if (isset(self::AMS_ALIGNEDAT_ENVIRONMENTS[$environment])) {
                $alignedAtPairs = $this->normalizeAmsAlignedAtPairCount($this->readRequiredGroupText($source, $contentOffset), $environment);
            }

            $content = $this->readEnvironmentContent($source, $contentOffset, $environment);
            if (isset(self::EQUATION_WRAPPER_ENVIRONMENTS[$environment])) {
                $this->assertEquationWrapperContent($content, $environment);
                $this->collectEquationReferenceLabelsFromTex(
                    $content,
                    $labels,
                    $nextAutomaticNumber,
                    $numberUntagged && self::EQUATION_WRAPPER_ENVIRONMENTS[$environment]
                );
            } elseif (isset(self::AMS_ROW_ENVIRONMENTS[$environment])) {
                if ($this->endsWithTopLevelRowSeparator($content)) {
                    throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
                }

                $rows = $this->splitAlignmentRows($content, $environment);
                $this->validateAmsRowEnvironmentRows($rows, $environment, self::AMS_ROW_ENVIRONMENTS[$environment]['columns']);
                $this->collectEquationReferenceLabelsFromEnvironmentRows($rows, $environment, $labels, $nextAutomaticNumber, $numberUntagged);
            } elseif (isset(self::AMS_FLUSH_ALIGNED_ENVIRONMENTS[$environment])) {
                if ($this->endsWithTopLevelRowSeparator($content)) {
                    throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
                }

                $rows = $this->splitAlignmentRows($content, $environment);
                $this->validateAmsFlushAlignedRows($rows, $environment);
                $this->collectEquationReferenceLabelsFromEnvironmentRows($rows, $environment, $labels, $nextAutomaticNumber, $numberUntagged);
            } elseif (isset(self::EQNARRAY_ENVIRONMENTS[$environment])) {
                if ($this->endsWithTopLevelRowSeparator($content)) {
                    throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
                }

                $rows = $this->splitAlignmentRows($content, $environment);
                $this->validateAmsRowEnvironmentRows($rows, $environment, 3);
                $this->collectEquationReferenceLabelsFromEnvironmentRows($rows, $environment, $labels, $nextAutomaticNumber, $numberUntagged);
            } elseif ($alignedAtPairs !== null) {
                if ($this->endsWithTopLevelRowSeparator($content)) {
                    throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
                }

                $rows = $this->splitAlignmentRows($content, $environment);
                $this->validateAmsRowEnvironmentRows($rows, $environment, $alignedAtPairs * 2);
                $this->collectEquationReferenceLabelsFromEnvironmentRows($rows, $environment, $labels, $nextAutomaticNumber, $numberUntagged);
            }

            if (!isset(self::EQUATION_WRAPPER_ENVIRONMENTS[$environment])) {
                $this->collectEnvironmentEquationReferenceLabelsFromTex($content, $labels, $nextAutomaticNumber, $numberUntagged);
            }
            $offset = $contentOffset;
        }
    }

    /**
     * @param list<list<string>> $rows
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function collectEquationReferenceLabelsFromEnvironmentRows(array $rows, string $environment, array &$labels, int &$nextAutomaticNumber, bool $numberUntagged): void
    {
        foreach ($rows as $rowIndex => $row) {
            if (self::isIntertextRow($row)) {
                continue;
            }

            $parsed = $this->extractEnvironmentRowMetadata($row, $environment, $rowIndex);
            if ($parsed['label'] !== null) {
                $automaticReference = null;
                if ($numberUntagged && $parsed['tag'] === null && !$parsed['suppressNumbering']) {
                    $automaticReference = (string) $nextAutomaticNumber;
                    $nextAutomaticNumber++;
                }

                $this->registerEquationReferenceLabel($labels, $parsed['label'], $parsed['tag'], $parsed['tagStarred'], $automaticReference);
            }
        }
    }

    /**
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function registerEquationReferenceLabel(array &$labels, string $label, ?string $tag, bool $tagStarred, ?string $automaticReference = null): void
    {
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Expected TeX equation label content');
        }

        $id = $this->normalizeEquationLabelId($label);
        if (isset($labels[$id])) {
            throw new \InvalidArgumentException('Duplicate TeX equation label ' . $label);
        }

        $reference = $tag !== null ? trim($tag) : ($automaticReference ?? $label);
        if ($reference === '') {
            throw new \InvalidArgumentException('Expected TeX equation reference text for ' . $label);
        }

        $labels[$id] = [
            'label' => $label,
            'id' => $id,
            'reference' => $reference,
            'tag' => $tag,
            'tagStarred' => $tagStarred,
        ];
    }

    /**
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     * @return array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}>
     */
    private function normalizeEquationReferenceLabels(array $referenceLabels): array
    {
        $normalized = [];
        foreach ($referenceLabels as $key => $entry) {
            $tag = null;
            $tagStarred = false;

            if (is_string($entry)) {
                $label = (string) $key;
                $reference = trim($entry);
            } elseif (is_array($entry)) {
                $labelEntry = $entry['label'] ?? $entry['id'] ?? (string) $key;
                if (!is_string($labelEntry)) {
                    throw new \InvalidArgumentException('Expected TeX equation reference label');
                }
                $label = $labelEntry;

                $referenceEntry = $entry['reference'] ?? $entry['tag'] ?? $label;
                if (!is_string($referenceEntry)) {
                    throw new \InvalidArgumentException('Expected TeX equation reference text for ' . $label);
                }
                $reference = trim($referenceEntry);

                if (array_key_exists('tag', $entry)) {
                    if ($entry['tag'] !== null && !is_string($entry['tag'])) {
                        throw new \InvalidArgumentException('Expected TeX equation reference tag for ' . $label);
                    }
                    $tag = $entry['tag'];
                }
                $tagStarred = (bool) ($entry['tagStarred'] ?? false);
            } else {
                throw new \InvalidArgumentException('Expected TeX equation reference label map entry');
            }

            if ($reference === '') {
                throw new \InvalidArgumentException('Expected TeX equation reference text for ' . $label);
            }

            $id = $this->normalizeEquationLabelId($label);
            if (isset($normalized[$id])) {
                throw new \InvalidArgumentException('Duplicate TeX equation reference label ' . $label);
            }

            $normalized[$id] = [
                'label' => $label,
                'id' => $id,
                'reference' => $reference,
                'tag' => $tag,
                'tagStarred' => $tagStarred,
            ];
        }

        return $normalized;
    }

    private function readEnvironmentContent(string $source, int &$offset, string $environment): string
    {
        $start = $offset;
        $length = strlen($source);
        $depth = 1;

        while ($offset < $length) {
            if ($source[$offset] === '%') {
                $this->skipTexLineComment($source, $offset);
                continue;
            }

            if ($source[$offset] !== '\\') {
                $offset++;
                continue;
            }

            $commandOffset = $offset + 1;
            $command = $this->readCommandName($source, $commandOffset);
            if ($command !== 'begin' && $command !== 'end') {
                $offset = $commandOffset;
                continue;
            }

            $groupOffset = $commandOffset;
            try {
                $name = $this->readRequiredGroupText($source, $groupOffset);
            } catch (\InvalidArgumentException) {
                $offset++;
                continue;
            }

            if ($name !== $environment) {
                $offset = $groupOffset;
                continue;
            }

            if ($command === 'begin') {
                $depth++;
                $offset = $groupOffset;
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $content = substr($source, $start, $offset - $start);
                $offset = $groupOffset;

                return $content;
            }

            $offset = $groupOffset;
        }

        throw new \InvalidArgumentException('Unclosed TeX environment ' . $environment . ' at offset ' . $start);
    }

    /**
     * @return list<list<string>>
     */
    private function splitAlignmentRows(string $content, string $environment): array
    {
        return $this->splitAlignmentRowsWithSpacing($content, $environment)['rows'];
    }

    /**
     * @return array{rows:list<list<string>>, rowSpacing:array<int, string>}
     */
    private function splitAlignmentRowsWithSpacing(string $content, string $environment): array
    {
        $rows = [];
        $rowSpacing = [];
        $row = [];
        $cell = '';
        $depth = 0;
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $char = $content[$offset];
            if ($char === '\\') {
                $next = $content[$offset + 1] ?? '';
                if ($depth === 0 && $next === '\\') {
                    $row[] = trim($cell);
                    $cell = '';
                    $rows[] = $row;
                    $row = [];
                    $offset += 2;
                    $spacing = $this->readOptionalAlignmentRowSpacingArgument($content, $offset);
                    if ($spacing !== null) {
                        $rowSpacing[count($rows)] = $spacing;
                    }
                    continue;
                }

                $intertext = $depth === 0 ? $this->readIntertextRowCommand($content, $offset, $environment) : null;
                if ($intertext !== null) {
                    if (trim($cell) !== '' || $row !== [] || $rows === [] || self::isIntertextRow($rows[count($rows) - 1])) {
                        throw new \InvalidArgumentException('Expected TeX ' . $environment . ' intertext after row separator at offset ' . $offset);
                    }

                    $rows[] = [self::INTERTEXT_ROW_MARKER, $intertext['command'], $intertext['text']];
                    $cell = '';
                    $offset = $intertext['next'];
                    continue;
                }

                $cell .= $char;
                $offset++;
                if (($content[$offset] ?? '') !== '' && !ctype_alpha($content[$offset])) {
                    $cell .= $content[$offset];
                    $offset++;
                }
                continue;
            }

            if ($char === '%') {
                $this->skipTexLineComment($content, $offset);
                if ($cell !== '' && !ctype_space(substr($cell, -1))) {
                    $cell .= ' ';
                }
                continue;
            }

            if ($char === '{') {
                $depth++;
                $cell .= $char;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth === 0) {
                    throw new \InvalidArgumentException('Unexpected TeX group end in ' . $environment . ' environment at offset ' . $offset);
                }
                $depth--;
                $cell .= $char;
                $offset++;
                continue;
            }

            if ($depth === 0 && $char === '&') {
                $row[] = trim($cell);
                $cell = '';
                $offset++;
                continue;
            }

            $cell .= $char;
            $offset++;
        }

        if ($depth !== 0) {
            throw new \InvalidArgumentException('Unclosed TeX group in ' . $environment . ' environment');
        }

        if (trim($cell) !== '' || $row !== []) {
            $row[] = trim($cell);
            $rows[] = $row;
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('Empty TeX environment ' . $environment);
        }

        return [
            'rows' => $rows,
            'rowSpacing' => $rowSpacing,
        ];
    }

    /**
     * @return array{command:string, text:string, next:int}|null
     */
    private function readIntertextRowCommand(string $content, int $offset, string $environment): ?array
    {
        if (!isset(self::AMS_INTERTEXT_ENVIRONMENTS[$environment]) || ($content[$offset] ?? '') !== '\\') {
            return null;
        }

        $cursor = $offset + 1;
        try {
            $command = $this->readCommandName($content, $cursor);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if ($command !== 'intertext' && $command !== 'shortintertext') {
            return null;
        }

        $this->skipWhitespace($content, $cursor);
        $argument = $this->readTexBraceArgument($content, $cursor);
        if ($argument === null) {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' text group at offset ' . $cursor);
        }

        return [
            'command' => $command,
            'text' => $this->normalizeIntertextContent($argument['value'], $command),
            'next' => $argument['next'],
        ];
    }

    private function normalizeIntertextContent(string $text, string $command): string
    {
        $this->assertIntertextTextSource($text, $command);
        $normalized = trim(preg_replace('/\s+/', ' ', $this->normalizeTextModeContent($text)) ?? '');
        if ($normalized === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' text content');
        }

        return $normalized;
    }

    private function assertIntertextTextSource(string $text, string $command): void
    {
        $offset = 0;
        $length = strlen($text);
        while ($offset < $length) {
            $char = $text[$offset];
            if ($char === '&') {
                throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' alignment marker');
            }

            if ($char !== '\\') {
                $offset++;
                continue;
            }

            $offset++;
            $escaped = $text[$offset] ?? '';
            if ($escaped === '\\') {
                throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' row separator');
            }

            if ($escaped !== '' && !ctype_alpha($escaped)) {
                $offset++;
                continue;
            }

            $commandStart = $offset;
            while ($offset < $length && ctype_alpha($text[$offset])) {
                $offset++;
            }

            $textCommand = substr($text, $commandStart, $offset - $commandStart);
            if (in_array($textCommand, ['begin', 'end', 'label', 'tag', 'notag', 'nonumber', 'intertext', 'shortintertext'], true)) {
                throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' structural command \\' . $textCommand);
            }
        }
    }

    private function skipOptionalAlignmentRowSpacingArgument(string $content, int &$offset): void
    {
        $this->readOptionalAlignmentRowSpacingArgument($content, $offset);
    }

    private function readOptionalAlignmentRowSpacingArgument(string $content, int &$offset): ?string
    {
        if (($content[$offset] ?? '') !== '[') {
            return null;
        }

        $argument = $this->readTexBracketArgument($content, $offset);
        if ($argument === null) {
            throw new \InvalidArgumentException('Unclosed TeX row-spacing argument at offset ' . $offset);
        }

        $offset = $argument['next'];

        return $this->normalizeMathSpaceDimension($argument['value'], 'rowspacing');
    }

    /**
     * @param array<int, string> $rowSpacing
     */
    private function environmentRowSpacingAttributes(array $rowSpacing, int $rowCount): string
    {
        if ($rowSpacing === [] || $rowCount < 2) {
            return '';
        }

        ksort($rowSpacing);
        $spacing = [];
        for ($rowNumber = 1; $rowNumber < $rowCount; $rowNumber++) {
            $spacing[] = $rowSpacing[$rowNumber] ?? 'normal';
        }

        $metadata = [];
        foreach ($rowSpacing as $rowNumber => $dimension) {
            if ($rowNumber >= 1 && $rowNumber < $rowCount) {
                $metadata[] = 'after-row-' . $rowNumber . ':' . $dimension;
            }
        }

        if ($metadata === []) {
            return '';
        }

        return ' rowspacing="' . $this->esc(implode(' ', $spacing)) . '" data-tex-rowspacing="' . $this->esc(implode(' ', $metadata)) . '"';
    }

    private function parseEnvironmentCell(string $cell): string
    {
        if ($cell === '') {
            return '';
        }

        $offset = 0;
        $children = $this->parseExpression($cell, $offset, null);
        $this->skipWhitespace($cell, $offset);
        if ($offset < strlen($cell)) {
            throw new \InvalidArgumentException('Unsupported TeX token in environment cell at offset ' . $offset);
        }

        return implode('', $children);
    }

    private function parseOptionalNonEmptyBracketArgument(string $source, int &$offset, string $label): ?string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        $argument = $this->readTexBracketArgument($source, $offset);
        if ($argument === null) {
            return null;
        }

        if (trim($argument['value']) === '') {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' content at offset ' . $start);
        }

        $offset = $argument['next'];

        return $this->parseTexFragment($argument['value'], $label);
    }

    private function parseTexFragment(string $fragment, string $label): string
    {
        $offset = 0;
        $children = $this->parseExpression($fragment, $offset, null);
        if ($children === []) {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' content');
        }

        $this->skipWhitespace($fragment, $offset);
        if ($offset < strlen($fragment)) {
            throw new \InvalidArgumentException('Unsupported TeX token in ' . $label . ' at offset ' . $offset);
        }

        return $this->row($children);
    }

    private function parseFenceCommand(string $source, int &$offset, string $command): string
    {
        $delimiter = $this->readFenceDelimiter($source, $offset);
        if ($command === 'left') {
            $this->activeLeftFenceDepth++;
        } elseif ($this->activeLeftFenceDepth <= 0) {
            throw new \InvalidArgumentException('Expected TeX \\right inside \\left...\\right at offset ' . $offset);
        } else {
            $this->activeLeftFenceDepth--;
        }

        if ($delimiter === '') {
            return '';
        }

        return '<mo fence="true" stretchy="true">' . $this->esc($delimiter) . '</mo>';
    }

    private function parseMiddleFenceCommand(string $source, int &$offset): string
    {
        if ($this->activeLeftFenceDepth <= 0) {
            throw new \InvalidArgumentException('Expected TeX \\middle inside \\left...\\right at offset ' . $offset);
        }

        $delimiter = $this->readFenceDelimiter($source, $offset);
        if ($delimiter === '') {
            return '';
        }

        return '<mo fence="true" stretchy="true" separator="true">' . $this->esc($delimiter) . '</mo>';
    }

    private function parseSizedDelimiterCommand(string $source, int &$offset, string $command): string
    {
        $delimiter = $this->readFenceDelimiter($source, $offset);
        if ($delimiter === '') {
            return '';
        }

        $spec = self::SIZED_DELIMITER_COMMANDS[$command];
        $attributes = ' fence="true" stretchy="true"';
        if (($spec['separator'] ?? false) === true) {
            $attributes .= ' separator="true"';
        }
        $attributes .= ' minsize="' . $this->esc($spec['size']) . '" maxsize="' . $this->esc($spec['size']) . '"';

        return '<mo' . $attributes . '>' . $this->esc($delimiter) . '</mo>';
    }

    private function parseStyleCommand(string $source, int &$offset, string $command): string
    {
        $style = $this->mathChoiceStyleForStyleCommand($command);
        $base = $this->withMathChoiceStyle(
            $style,
            function () use ($source, &$offset, $command): string {
                return $this->parseStyleArgument($source, $offset, $command);
            }
        );
        $attributes = match ($command) {
            'displaystyle' => ' displaystyle="true"',
            'textstyle' => ' displaystyle="false"',
            'scriptstyle' => ' scriptlevel="1"',
            'scriptscriptstyle' => ' scriptlevel="2"',
        };

        return '<mstyle' . $attributes . '>' . $base . '</mstyle>';
    }

    private function parseMathChoiceCommand(string $source, int &$offset): string
    {
        $branchSources = [];
        foreach (['display', 'text', 'script', 'scriptscript'] as $style) {
            $this->skipWhitespace($source, $offset);
            $start = $offset;
            $argument = $this->readTexBraceArgument($source, $offset);
            if ($argument === null) {
                throw new \InvalidArgumentException('Expected TeX mathchoice ' . $style . ' group at offset ' . $offset);
            }

            if (trim($argument['value']) === '') {
                throw new \InvalidArgumentException('Expected TeX mathchoice ' . $style . ' content at offset ' . $start);
            }

            $branchSources[$style] = $argument['value'];
            $offset = $argument['next'];
        }

        $branches = [];
        foreach ($branchSources as $style => $fragment) {
            $branches[$style] = $this->withMathChoiceStyle(
                $style,
                fn (): string => $this->parseTexFragment($fragment, 'mathchoice ' . $style)
            );
        }

        return $branches[$this->mathChoiceStyle] ?? $branches['text'];
    }

    private function parseStyleArgument(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX style argument for \\' . $command . ' at offset ' . $offset);
        }

        $defaultScriptPlacement = null;
        $base = $this->parseAtom($source, $offset, $defaultScriptPlacement);
        $scriptPlacement = $this->readScriptPlacementCommand($source, $offset);
        if (
            $scriptPlacement === null
            && $defaultScriptPlacement !== null
            && $this->nextNonWhitespaceIsScriptMarker($source, $offset)
        ) {
            $scriptPlacement = $defaultScriptPlacement;
        }

        return $this->applyScripts($source, $offset, $base, $scriptPlacement);
    }

    private function parseSidesetCommand(string $source, int &$offset): string
    {
        $left = $this->parseSidesetScriptGroup($this->readRequiredGroupText($source, $offset), 'left');
        $right = $this->parseSidesetScriptGroup($this->readRequiredGroupText($source, $offset), 'right');
        $base = $this->parseRequiredAtomOrGroup($source, $offset, 'sideset base');

        if (!$left['hasScripts'] && !$right['hasScripts']) {
            return $base;
        }

        $mathml = '<mmultiscripts>' . $base;
        if ($right['hasScripts']) {
            $mathml .= ($right['subscript'] ?? '<none/>') . ($right['superscript'] ?? '<none/>');
        }

        if ($left['hasScripts']) {
            $mathml .= '<mprescripts/>'
                . ($left['subscript'] ?? '<none/>')
                . ($left['superscript'] ?? '<none/>');
        }

        return $mathml . '</mmultiscripts>';
    }

    private function parsePrescriptCommand(string $source, int &$offset): string
    {
        $superscript = $this->parsePrescriptScriptGroup($source, $offset, 'superscript');
        $subscript = $this->parsePrescriptScriptGroup($source, $offset, 'subscript');
        if ($superscript === null && $subscript === null) {
            throw new \InvalidArgumentException('Expected TeX prescript subscript or superscript at offset ' . $offset);
        }

        $base = $this->parseRequiredNonEmptyGroup($source, $offset, 'prescript base');

        return '<mmultiscripts>' . $base
            . '<mprescripts/>'
            . ($subscript ?? '<none/>')
            . ($superscript ?? '<none/>')
            . '</mmultiscripts>';
    }

    private function parsePrescriptScriptGroup(string $source, int &$offset, string $label): ?string
    {
        $this->skipWhitespace($source, $offset);
        $argument = $this->readTexBraceArgument($source, $offset);
        if ($argument === null) {
            throw new \InvalidArgumentException('Expected TeX prescript ' . $label . ' group at offset ' . $offset);
        }

        $offset = $argument['next'];
        if (trim($argument['value']) === '') {
            return null;
        }

        return $this->withMathChoiceStyle(
            $this->nestedMathChoiceScriptStyle(),
            fn (): string => $this->parseTexFragment($argument['value'], 'prescript ' . $label)
        );
    }

    /**
     * @return array{subscript:?string, superscript:?string, hasScripts:bool}
     */
    private function parseSidesetScriptGroup(string $source, string $label): array
    {
        $subscript = null;
        $superscript = null;
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $this->skipWhitespace($source, $offset);
            if ($offset >= $length) {
                break;
            }

            $marker = $source[$offset] ?? '';
            if ($marker === '_') {
                if ($subscript !== null) {
                    throw new \InvalidArgumentException('Duplicate TeX sideset ' . $label . ' subscript at offset ' . $offset);
                }

                $offset++;
                $subscript = $this->parseSidesetScriptArgument($source, $offset, $label . ' subscript');
                continue;
            }

            if ($marker === '^') {
                if ($superscript !== null) {
                    throw new \InvalidArgumentException('Duplicate TeX sideset ' . $label . ' superscript at offset ' . $offset);
                }

                $offset++;
                $superscript = $this->parseSidesetScriptArgument($source, $offset, $label . ' superscript');
                continue;
            }

            if ($marker === "'") {
                if ($superscript !== null) {
                    throw new \InvalidArgumentException('Duplicate TeX sideset ' . $label . ' superscript at offset ' . $offset);
                }

                $superscript = $this->parsePrimeShorthand($source, $offset);
                continue;
            }

            $primeSuperscript = $this->parseSidesetPrimeCommandRun($source, $offset);
            if ($primeSuperscript !== null) {
                if ($superscript !== null) {
                    throw new \InvalidArgumentException('Duplicate TeX sideset ' . $label . ' superscript at offset ' . $offset);
                }

                $superscript = $primeSuperscript;
                continue;
            }

            throw new \InvalidArgumentException('Expected TeX sideset ' . $label . ' script marker at offset ' . $offset);
        }

        return [
            'subscript' => $subscript,
            'superscript' => $superscript,
            'hasScripts' => $subscript !== null || $superscript !== null,
        ];
    }

    private function parseSidesetScriptArgument(string $source, int &$offset, string $label): string
    {
        $start = $offset;
        $argument = $this->parseScriptArgument($source, $offset);
        if ($argument === '<mrow></mrow>') {
            throw new \InvalidArgumentException('Expected TeX sideset ' . $label . ' content at offset ' . $start);
        }

        return $argument;
    }

    private function parseSidesetPrimeCommandRun(string $source, int &$offset): ?string
    {
        $cursor = $offset;
        $count = 0;
        while (substr($source, $cursor, 6) === '\\prime' && !ctype_alpha($source[$cursor + 6] ?? '')) {
            $count++;
            $cursor += 6;
        }

        if ($count === 0) {
            return null;
        }

        $offset = $cursor;

        return $this->primeMathMl($count);
    }

    private function applyScripts(string $source, int &$offset, string $base, ?string $scriptPlacement = null): string
    {
        $subscript = null;
        $superscript = null;

        while (true) {
            $this->skipWhitespace($source, $offset);
            $marker = $source[$offset] ?? '';
            if ($marker !== '_' && $marker !== '^' && $marker !== "'") {
                break;
            }

            if ($marker === "'") {
                $prime = $this->parsePrimeShorthand($source, $offset);
                $superscript = $superscript === null ? $prime : $this->row([$superscript, $prime]);
                continue;
            }

            $offset++;
            $argument = $this->parseScriptArgument($source, $offset);
            if ($marker === '_') {
                $subscript = $argument;
            } else {
                $superscript = $argument;
            }
        }

        if ($scriptPlacement !== null && $subscript === null && $superscript === null) {
            throw new \InvalidArgumentException('Expected TeX \\' . $scriptPlacement . ' subscript or superscript at offset ' . $offset);
        }

        if ($scriptPlacement === 'limits' || $scriptPlacement === 'displaylimits') {
            if ($subscript !== null && $superscript !== null) {
                return '<munderover>' . $base . $subscript . $superscript . '</munderover>';
            }

            if ($subscript !== null) {
                return '<munder>' . $base . $subscript . '</munder>';
            }

            if ($superscript !== null) {
                return '<mover>' . $base . $superscript . '</mover>';
            }
        }

        if ($subscript !== null && $superscript !== null) {
            return '<msubsup>' . $base . $subscript . $superscript . '</msubsup>';
        }

        if ($subscript !== null) {
            return '<msub>' . $base . $subscript . '</msub>';
        }

        if ($superscript !== null) {
            return '<msup>' . $base . $superscript . '</msup>';
        }

        return $base;
    }

    private function parsePrimeShorthand(string $source, int &$offset): string
    {
        $count = 0;
        while (($source[$offset] ?? '') === "'") {
            $count++;
            $offset++;
        }

        if ($count === 0) {
            throw new \InvalidArgumentException('Expected TeX prime shorthand at offset ' . $offset);
        }

        return $this->primeMathMl($count);
    }

    private function primeMathMl(int $count): string
    {
        return match ($count) {
            1 => '<mo>′</mo>',
            2 => '<mo>″</mo>',
            3 => '<mo>‴</mo>',
            4 => '<mo>⁗</mo>',
            default => $this->row($this->primeRunMathMlParts($count)),
        };
    }

    /**
     * @return list<string>
     */
    private function primeRunMathMlParts(int $count): array
    {
        $parts = [];
        while ($count >= 4) {
            $parts[] = '<mo>⁗</mo>';
            $count -= 4;
        }

        if ($count > 0) {
            $parts[] = $this->primeMathMl($count);
        }

        return $parts;
    }

    private function readScriptPlacementCommand(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $commandOffset = $offset + 1;
        $command = $this->readCommandName($source, $commandOffset);
        if ($command !== 'limits' && $command !== 'nolimits' && $command !== 'displaylimits') {
            return null;
        }

        $offset = $commandOffset;

        return $command;
    }

    private function nextNonWhitespaceIsScriptMarker(string $source, int $offset): bool
    {
        $cursor = $offset;
        $this->skipWhitespace($source, $cursor);
        $marker = $source[$cursor] ?? '';

        return $marker === '_' || $marker === '^';
    }

    private function parseScriptArgument(string $source, int &$offset): string
    {
        return $this->withMathChoiceStyle($this->nestedMathChoiceScriptStyle(), function () use ($source, &$offset): string {
            $this->skipWhitespace($source, $offset);
            if (($source[$offset] ?? '') === '{') {
                $offset++;
                $children = $this->parseExpression($source, $offset, '}');
                $this->expectGroupEnd($source, $offset);

                return $this->row($children);
            }

            return $this->parseAtom($source, $offset);
        });
    }

    private function mathChoiceStyleForStyleCommand(string $command): string
    {
        return match ($command) {
            'displaystyle' => 'display',
            'textstyle' => 'text',
            'scriptstyle' => 'script',
            'scriptscriptstyle' => 'scriptscript',
        };
    }

    private function nestedMathChoiceScriptStyle(): string
    {
        if ($this->mathChoiceStyle === 'script' || $this->mathChoiceStyle === 'scriptscript') {
            return 'scriptscript';
        }

        return 'script';
    }

    /**
     * @param callable(): string $callback
     */
    private function withMathChoiceStyle(string $style, callable $callback): string
    {
        $previousStyle = $this->mathChoiceStyle;
        $this->mathChoiceStyle = $style;

        try {
            return $callback();
        } finally {
            $this->mathChoiceStyle = $previousStyle;
        }
    }

    private function parseAccentArgument(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX accent argument for \\' . $command . ' at offset ' . $offset);
        }

        return $this->parseScriptArgument($source, $offset);
    }

    private function parseRequiredAtomOrGroup(string $source, int &$offset, string $label): string
    {
        return $this->parseRequiredTexToken($source, $offset, $label);
    }

    private function parseRequiredTexToken(string $source, int &$offset, string $label, bool $allowEmptyGroup = false): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^' || $char === '}' || $char === '&') {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' content at offset ' . $offset);
        }

        if ($char === '{') {
            return $allowEmptyGroup
                ? $this->parseRequiredGroup($source, $offset)
                : $this->parseRequiredNonEmptyGroup($source, $offset, $label);
        }

        if (ctype_digit($char)) {
            $offset++;

            return '<mn>' . $this->esc($char) . '</mn>';
        }

        return $this->parseAtom($source, $offset);
    }

    private function parseRequiredGroup(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '{') {
            throw new \InvalidArgumentException('Expected TeX group at offset ' . $offset);
        }

        $offset++;
        $children = $this->parseExpression($source, $offset, '}');
        $this->expectGroupEnd($source, $offset);

        return $this->row($children);
    }

    private function parseRequiredNonEmptyGroup(string $source, int &$offset, string $label): string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        if (($source[$offset] ?? '') !== '{') {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' group at offset ' . $offset);
        }

        $offset++;
        $children = $this->parseExpression($source, $offset, '}');
        if ($children === []) {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' content at offset ' . $start);
        }

        $this->expectGroupEnd($source, $offset);

        return $this->row($children);
    }

    private function parseOptionalRootDegree(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '[') {
            return null;
        }

        $offset++;
        $children = $this->parseExpression($source, $offset, ']');
        if ($children === []) {
            throw new \InvalidArgumentException('Expected TeX root degree at offset ' . $offset);
        }

        if (($source[$offset] ?? '') !== ']') {
            throw new \InvalidArgumentException('Unclosed TeX root degree at offset ' . $offset);
        }

        $offset++;

        return $this->row($children);
    }

    private function parsePlainRootCommand(string $source, int &$offset): string
    {
        $degreeSource = $this->readPlainRootDegreeSource($source, $offset);
        $degree = $this->parseTexFragment($degreeSource, 'root degree');
        $radicand = $this->parseRequiredAtomOrGroup($source, $offset, 'root radicand');

        return '<mroot>' . $radicand . $degree . '</mroot>';
    }

    private function readPlainRootDegreeSource(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        $depth = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $command = $this->readCommandName($source, $commandOffset);
                if ($depth === 0 && $command === 'of') {
                    $degreeSource = trim(substr($source, $start, $offset - $start));
                    if ($degreeSource === '') {
                        throw new \InvalidArgumentException('Expected TeX root degree before \\of at offset ' . $offset);
                    }

                    $offset = $commandOffset;

                    return $degreeSource;
                }

                $offset = $commandOffset;
                continue;
            }

            if ($char === '{') {
                $depth++;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth === 0) {
                    break;
                }
                $depth--;
                $offset++;
                continue;
            }

            $offset++;
        }

        throw new \InvalidArgumentException('Expected TeX \\of after \\root degree at offset ' . $offset);
    }

    private function readRequiredGroupText(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '{') {
            throw new \InvalidArgumentException('Expected TeX text group at offset ' . $offset);
        }

        $offset++;
        $start = $offset;
        $depth = 1;
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $text = substr($source, $start, $offset - $start);
                    $offset++;

                    return $text;
                }
            }
            $offset++;
        }

        throw new \InvalidArgumentException('Unclosed TeX text group at offset ' . $start);
    }

    /**
     * @return array{command:string, lineThickness?:string, open?:string, close?:string}|null
     */
    private function readInfixFractionCommand(string $source, int &$offset): ?array
    {
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $commandOffset = $offset + 1;
        $command = $this->readCommandName($source, $commandOffset);
        if (isset(self::INFIX_FRACTION_COMMANDS[$command])) {
            $offset = $commandOffset;

            return ['command' => $command] + self::INFIX_FRACTION_COMMANDS[$command];
        }

        if (!in_array($command, ['overwithdelims', 'atopwithdelims', 'abovewithdelims'], true)) {
            return null;
        }

        $withDelimsOffset = $commandOffset;
        $spec = [
            'command' => $command,
            'open' => $this->readFenceDelimiter($source, $withDelimsOffset),
            'close' => $this->readFenceDelimiter($source, $withDelimsOffset),
        ];

        if ($command === 'atopwithdelims') {
            $spec['lineThickness'] = '0';
        } elseif ($command === 'abovewithdelims') {
            $spec['lineThickness'] = $this->readAboveWithDelimsLineThickness($source, $withDelimsOffset);
        }

        $offset = $withDelimsOffset;

        return $spec;
    }

    private function readAboveWithDelimsLineThickness(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if (ctype_space($char) || $char === '{' || $char === '}' || $char === '\\' || $char === '_' || $char === '^') {
                break;
            }
            $offset++;
        }

        if ($offset === $start) {
            throw new \InvalidArgumentException('Expected TeX abovewithdelims line thickness at offset ' . $offset);
        }

        $lineThickness = $this->normalizeGeneralizedFractionLineThickness(substr($source, $start, $offset - $start));
        if ($lineThickness === null) {
            throw new \InvalidArgumentException('Expected TeX abovewithdelims line thickness at offset ' . $start);
        }

        return $lineThickness;
    }

    private function readCommandName(string $source, int &$offset): string
    {
        $start = $offset;
        while ($offset < strlen($source) && ctype_alpha($source[$offset])) {
            $offset++;
        }

        if ($offset > $start) {
            return substr($source, $start, $offset - $start);
        }

        if (($source[$offset] ?? '') !== '') {
            return $source[$offset++];
        }

        throw new \InvalidArgumentException('Expected TeX command name at offset ' . $offset);
    }

    private function readFenceDelimiter(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '') {
            throw new \InvalidArgumentException('Expected TeX fence delimiter at offset ' . $offset);
        }

        if ($char === '\\') {
            $offset++;
            $command = $this->readCommandName($source, $offset);
            if (isset(self::DELIMITER_COMMANDS[$command])) {
                return self::DELIMITER_COMMANDS[$command];
            }

            throw new \InvalidArgumentException('Unsupported TeX fence delimiter command \\' . $command . ' at offset ' . $offset);
        }

        $offset++;
        if ($char === '.') {
            return '';
        }

        if (str_contains('()[]{}|/<>', $char)) {
            return $char;
        }

        throw new \InvalidArgumentException('Unsupported TeX fence delimiter at offset ' . ($offset - 1));
    }

    private function expectGroupEnd(string $source, int &$offset): void
    {
        if (($source[$offset] ?? '') !== '}') {
            throw new \InvalidArgumentException('Unclosed TeX group at offset ' . $offset);
        }

        $offset++;
    }

    private function skipWhitespace(string $source, int &$offset): void
    {
        while (true) {
            while (($source[$offset] ?? '') !== '' && ctype_space($source[$offset])) {
                $offset++;
            }

            if (($source[$offset] ?? '') !== '%') {
                return;
            }

            $this->skipTexLineComment($source, $offset);
        }
    }

    private function skipTexLineComment(string $source, int &$offset): void
    {
        if (($source[$offset] ?? '') !== '%') {
            return;
        }

        $offset++;
        while (($source[$offset] ?? '') !== '' && $source[$offset] !== "\n" && $source[$offset] !== "\r") {
            $offset++;
        }

        if (($source[$offset] ?? '') === "\r" && ($source[$offset + 1] ?? '') === "\n") {
            $offset += 2;
            return;
        }

        if (($source[$offset] ?? '') === "\n" || ($source[$offset] ?? '') === "\r") {
            $offset++;
        }
    }

    /**
     * @param list<string> $children
     */
    private function row(array $children): string
    {
        if (count($children) === 1) {
            return $children[0];
        }

        return '<mrow>' . implode('', $children) . '</mrow>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
