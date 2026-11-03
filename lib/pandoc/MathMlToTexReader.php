<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MathMlToTexReader
{
    /** @var array<string, string> */
    private const IDENTIFIER_COMMANDS = [
        'Α' => '\\Alpha',
        'α' => '\\alpha',
        'Β' => '\\Beta',
        'β' => '\\beta',
        'Γ' => '\\Gamma',
        'γ' => '\\gamma',
        'Δ' => '\\Delta',
        'δ' => '\\delta',
        'Ε' => '\\Epsilon',
        'ε' => '\\varepsilon',
        'ϵ' => '\\epsilon',
        'Ζ' => '\\Zeta',
        'ζ' => '\\zeta',
        'Η' => '\\Eta',
        'η' => '\\eta',
        'Θ' => '\\Theta',
        'θ' => '\\theta',
        'ϑ' => '\\vartheta',
        'Ι' => '\\Iota',
        'ι' => '\\iota',
        'Κ' => '\\Kappa',
        'κ' => '\\kappa',
        'Λ' => '\\Lambda',
        'λ' => '\\lambda',
        'Μ' => '\\Mu',
        'μ' => '\\mu',
        'Ν' => '\\Nu',
        'ν' => '\\nu',
        'Ξ' => '\\Xi',
        'ξ' => '\\xi',
        'Ο' => '\\Omicron',
        'ο' => '\\omicron',
        'Π' => '\\Pi',
        'π' => '\\pi',
        'Ρ' => '\\Rho',
        'ρ' => '\\rho',
        'Σ' => '\\Sigma',
        'σ' => '\\sigma',
        'ς' => '\\varsigma',
        'Τ' => '\\Tau',
        'τ' => '\\tau',
        'Υ' => '\\Upsilon',
        'υ' => '\\upsilon',
        'Φ' => '\\Phi',
        'φ' => '\\varphi',
        'ϕ' => '\\phi',
        'Χ' => '\\Chi',
        'χ' => '\\chi',
        'Ψ' => '\\Psi',
        'ψ' => '\\psi',
        'Ω' => '\\Omega',
        'ω' => '\\omega',
        '∞' => '\\infty',
        'ℵ' => '\\aleph',
        'ℓ' => '\\ell',
    ];

    /** @var array<string, string> */
    private const OPERATOR_COMMANDS = [
        '−' => '-',
        '±' => '\\pm',
        '∓' => '\\mp',
        '×' => '\\times',
        '÷' => '\\div',
        '⋅' => '\\cdot',
        '∗' => '\\ast',
        '∫' => '\\int',
        '∑' => '\\sum',
        '∏' => '\\prod',
        '∞' => '\\infty',
        'π' => '\\pi',
        '∂' => '\\partial',
        '∇' => '\\nabla',
        '≤' => '\\leq',
        '≥' => '\\geq',
        '≠' => '\\neq',
        '≈' => '\\approx',
        '≃' => '\\simeq',
        '≅' => '\\cong',
        '∈' => '\\in',
        '∉' => '\\notin',
        '∋' => '\\ni',
        '∩' => '\\cap',
        '∪' => '\\cup',
        '⊂' => '\\subset',
        '⊃' => '\\supset',
        '⊆' => '\\subseteq',
        '⊇' => '\\supseteq',
        '∧' => '\\wedge',
        '∨' => '\\vee',
        '¬' => '\\neg',
        '→' => '\\rightarrow',
        '←' => '\\leftarrow',
        '↔' => '\\leftrightarrow',
        '⇒' => '\\Rightarrow',
        '⇐' => '\\Leftarrow',
        '⇔' => '\\Leftrightarrow',
        '…' => '\\ldots',
        '⋯' => '\\cdots',
    ];

    /** @var array<string, string> */
    private const FENCE_COMMANDS = [
        '(' => '(',
        ')' => ')',
        '[' => '\\lbrack',
        ']' => '\\rbrack',
        '{' => '\\lbrace',
        '}' => '\\rbrace',
        '|' => '\\vert',
        '‖' => '\\Vert',
        '<' => '\\langle',
        '>' => '\\rangle',
        '〈' => '\\langle',
        '〉' => '\\rangle',
        '.' => '.',
    ];

    /** @var array<string, true> */
    private const BINARY_OR_RELATION_OPERATORS = [
        '+' => true,
        '-' => true,
        '=' => true,
        '<' => true,
        '>' => true,
        ':' => true,
        '−' => true,
        '±' => true,
        '∓' => true,
        '×' => true,
        '÷' => true,
        '⋅' => true,
        '∗' => true,
        '≤' => true,
        '≥' => true,
        '≠' => true,
        '≈' => true,
        '≃' => true,
        '≅' => true,
        '∈' => true,
        '∉' => true,
        '∋' => true,
        '∩' => true,
        '∪' => true,
        '⊂' => true,
        '⊃' => true,
        '⊆' => true,
        '⊇' => true,
        '∧' => true,
        '∨' => true,
        '→' => true,
        '←' => true,
        '↔' => true,
        '⇒' => true,
        '⇐' => true,
        '⇔' => true,
    ];

    /** @var array<string, true> */
    private const PREFIX_OPERATORS = [
        '∫' => true,
        '\\int' => true,
    ];

    /** @var array<string, true> */
    private const PUNCTUATION_OPERATORS = [
        ',' => true,
        ';' => true,
    ];

    public function texFromString(string $mathml): ?string
    {
        $mathml = trim($mathml);
        if ($mathml === '' || !str_starts_with(strtolower($mathml), '<math')) {
            return null;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadXML($this->normalizeXmlEntities($mathml), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded || !$dom->documentElement instanceof \DOMElement) {
            return null;
        }

        return $this->texFromElement($dom->documentElement);
    }

    public function texFromElement(\DOMElement $math): ?string
    {
        $piece = $this->renderElementPiece($math);
        if ($piece === null) {
            return null;
        }

        $tex = $this->normalizeTex($piece['text']);

        return $tex === '' ? null : $tex;
    }

    /**
     * @return array{text:string, role:string}|null
     */
    private function renderElementPiece(\DOMElement $element): ?array
    {
        $name = strtolower($element->localName);

        return match ($name) {
            'math', 'mrow', 'mstyle', 'mpadded', 'mphantom', 'maction' => $this->ordinaryPiece($this->renderChildren($element)),
            'semantics' => $this->ordinaryPiece($this->renderSemantics($element)),
            'mi' => $this->ordinaryPiece($this->identifierText($element)),
            'mn' => $this->ordinaryPiece($this->tokenText($element)),
            'mo' => $this->operatorPiece($element),
            'mtext', 'ms' => $this->ordinaryPiece($this->textCommand($element)),
            'mspace' => $this->ordinaryPiece('\\;'),
            'msup' => $this->ordinaryPiece($this->renderScript($element, 'sup')),
            'msub' => $this->ordinaryPiece($this->renderScript($element, 'sub')),
            'msubsup' => $this->ordinaryPiece($this->renderScript($element, 'subsup')),
            'mfrac' => $this->ordinaryPiece($this->renderFraction($element)),
            'msqrt' => $this->ordinaryPiece($this->renderSqrt($element)),
            'mroot' => $this->ordinaryPiece($this->renderRoot($element)),
            'mfenced' => $this->ordinaryPiece($this->renderFenced($element)),
            'mtable' => $this->ordinaryPiece($this->renderTable($element)),
            'mtr', 'mlabeledtr', 'mtd' => $this->ordinaryPiece($this->renderChildren($element)),
            'munder' => $this->ordinaryPiece($this->renderUnderOver($element, 'under')),
            'mover' => $this->ordinaryPiece($this->renderUnderOver($element, 'over')),
            'munderover' => $this->ordinaryPiece($this->renderUnderOver($element, 'underover')),
            'menclose' => $this->ordinaryPiece($this->renderChildren($element)),
            'annotation', 'annotation-xml' => null,
            default => null,
        };
    }

    private function renderSemantics(\DOMElement $element): ?string
    {
        foreach ($this->childElements($element) as $child) {
            $name = strtolower($child->localName);
            if ($name === 'annotation' || $name === 'annotation-xml') {
                continue;
            }
            $piece = $this->renderElementPiece($child);

            return $piece['text'] ?? null;
        }

        return $this->renderChildren($element);
    }

    private function renderScript(\DOMElement $element, string $kind): ?string
    {
        $children = $this->childElements($element);
        $base = isset($children[0]) ? $this->renderElementText($children[0]) : null;
        $sub = isset($children[1]) ? $this->renderElementText($children[1]) : null;
        $sup = isset($children[2]) ? $this->renderElementText($children[2]) : null;
        if ($base === null || $base === '') {
            return null;
        }
        if ($kind === 'sub' && $sub !== null && $sub !== '') {
            return $base . '_{' . $sub . '}';
        }
        if ($kind === 'sup' && $sub !== null && $sub !== '') {
            return $base . '^{' . $sub . '}';
        }
        if ($kind === 'subsup' && $sub !== null && $sub !== '' && $sup !== null && $sup !== '') {
            return $base . '_{' . $sub . '}^{' . $sup . '}';
        }

        return null;
    }

    private function renderFraction(\DOMElement $element): ?string
    {
        $children = $this->childElements($element);
        $numerator = isset($children[0]) ? $this->renderElementText($children[0]) : null;
        $denominator = isset($children[1]) ? $this->renderElementText($children[1]) : null;
        if ($numerator === null || $denominator === null || $numerator === '' || $denominator === '') {
            return null;
        }

        return '\\frac{' . $numerator . '}{' . $denominator . '}';
    }

    private function renderSqrt(\DOMElement $element): ?string
    {
        $body = $this->renderChildren($element);

        return $body === null || $body === '' ? null : '\\sqrt{' . $body . '}';
    }

    private function renderRoot(\DOMElement $element): ?string
    {
        $children = $this->childElements($element);
        $body = isset($children[0]) ? $this->renderElementText($children[0]) : null;
        $index = isset($children[1]) ? $this->renderElementText($children[1]) : null;
        if ($body === null || $index === null || $body === '' || $index === '') {
            return null;
        }

        return '\\sqrt[' . $index . ']{' . $body . '}';
    }

    private function renderFenced(\DOMElement $element): ?string
    {
        $open = $element->hasAttribute('open') ? $element->getAttribute('open') : '(';
        $close = $element->hasAttribute('close') ? $element->getAttribute('close') : ')';
        $separators = $element->hasAttribute('separators') ? $element->getAttribute('separators') : ',';
        $separator = $separators === '' ? ',' : mb_substr($separators, 0, 1, 'UTF-8');
        $parts = [];
        foreach ($this->childElements($element) as $child) {
            $part = $this->renderElementText($child);
            if ($part !== null && $part !== '') {
                $parts[] = $part;
            }
        }
        if ($parts === []) {
            return null;
        }

        return '\\left' . $this->fenceCommand($open)
            . ' {' . implode($separator, $parts) . '} '
            . '\\right' . $this->fenceCommand($close);
    }

    private function renderTable(\DOMElement $element): ?string
    {
        $rows = [];
        foreach ($this->childElements($element) as $row) {
            $name = strtolower($row->localName);
            if ($name !== 'mtr' && $name !== 'mlabeledtr') {
                continue;
            }
            $cells = [];
            foreach ($this->childElements($row) as $cell) {
                if (strtolower($cell->localName) !== 'mtd') {
                    continue;
                }
                $cellTex = $this->renderChildren($cell);
                $cells[] = $cellTex ?? '';
            }
            $rows[] = implode(' & ', $cells);
        }
        if ($rows === []) {
            return null;
        }

        return "\\begin{matrix}\n" . implode(" \\\\\n", $rows) . "\n\\end{matrix}";
    }

    private function renderUnderOver(\DOMElement $element, string $kind): ?string
    {
        $children = $this->childElements($element);
        $base = isset($children[0]) ? $this->renderElementText($children[0]) : null;
        $under = isset($children[1]) ? $this->renderElementText($children[1]) : null;
        $over = isset($children[2]) ? $this->renderElementText($children[2]) : null;
        if ($base === null || $base === '') {
            return null;
        }

        if ($kind === 'over' && $under !== null && $under !== '') {
            return match ($under) {
                '^', 'ˆ' => '\\hat{' . $base . '}',
                '¯', '‾', '-' => '\\bar{' . $base . '}',
                '→', '\\rightarrow' => '\\vec{' . $base . '}',
                default => '\\overset{' . $under . '}{' . $base . '}',
            };
        }
        if ($kind === 'under' && $under !== null && $under !== '') {
            return $this->operatorTakesLimits($base)
                ? $base . '\\limits_{' . $under . '}'
                : '\\underset{' . $under . '}{' . $base . '}';
        }
        if ($kind === 'underover' && $under !== null && $under !== '' && $over !== null && $over !== '') {
            return $this->operatorTakesLimits($base)
                ? $base . '\\limits_{' . $under . '}^{' . $over . '}'
                : '\\overset{' . $over . '}{\\underset{' . $under . '}{' . $base . '}}';
        }

        return null;
    }

    private function renderElementText(\DOMElement $element): ?string
    {
        $piece = $this->renderElementPiece($element);

        return $piece === null ? null : $this->normalizeTex($piece['text']);
    }

    private function renderChildren(\DOMElement $element): ?string
    {
        $pieces = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text = $this->normalizeTokenText($child->textContent);
                if ($text !== '') {
                    $pieces[] = ['text' => $text, 'role' => 'ordinary'];
                }
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $piece = $this->renderElementPiece($child);
            if ($piece !== null && $piece['text'] !== '') {
                $pieces[] = $piece;
            }
        }

        return $this->joinPieces($pieces);
    }

    /**
     * @param list<array{text:string, role:string}> $pieces
     */
    private function joinPieces(array $pieces): ?string
    {
        $text = '';
        $previousRole = null;
        foreach ($pieces as $piece) {
            $part = $piece['text'];
            $role = $piece['role'];
            if ($part === '') {
                continue;
            }
            $needsSpace = $text !== ''
                && (
                    in_array($role, ['binary', 'relation'], true)
                    || in_array($previousRole, ['binary', 'relation', 'prefix'], true)
                );
            if ($needsSpace && !str_ends_with($text, ' ')) {
                $text .= ' ';
            }
            $text .= $part;
            $previousRole = $role;
        }

        return $text === '' ? null : $this->normalizeTex($text);
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMElement $element): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @return array{text:string, role:string}|null
     */
    private function ordinaryPiece(?string $text): ?array
    {
        $text = $text === null ? '' : $this->normalizeTex($text);

        return $text === '' ? null : ['text' => $text, 'role' => 'ordinary'];
    }

    /**
     * @return array{text:string, role:string}|null
     */
    private function operatorPiece(\DOMElement $element): ?array
    {
        $raw = $this->tokenText($element);
        if ($raw === '') {
            return null;
        }
        $text = self::OPERATOR_COMMANDS[$raw] ?? $raw;

        return [
            'text' => $text,
            'role' => $this->operatorRole($raw, $text),
        ];
    }

    private function operatorRole(string $raw, string $text): string
    {
        if (isset(self::PREFIX_OPERATORS[$raw]) || isset(self::PREFIX_OPERATORS[$text])) {
            return 'prefix';
        }
        if (isset(self::PUNCTUATION_OPERATORS[$raw])) {
            return 'punctuation';
        }
        if (isset(self::BINARY_OR_RELATION_OPERATORS[$raw])) {
            return 'binary';
        }
        if ($raw === '(' || $raw === '[' || $raw === '{') {
            return 'open';
        }
        if ($raw === ')' || $raw === ']' || $raw === '}') {
            return 'close';
        }

        return 'ordinary';
    }

    private function identifierText(\DOMElement $element): string
    {
        $text = $this->tokenText($element);

        return self::IDENTIFIER_COMMANDS[$text] ?? $text;
    }

    private function tokenText(\DOMElement $element): string
    {
        return $this->normalizeTokenText($element->textContent);
    }

    private function textCommand(\DOMElement $element): string
    {
        $text = $this->normalizeTokenText($element->textContent);

        return $text === '' ? '' : '\\text{' . $this->escapeText($text) . '}';
    }

    private function fenceCommand(string $fence): string
    {
        $fence = $fence === '' ? '.' : mb_substr($fence, 0, 1, 'UTF-8');

        return self::FENCE_COMMANDS[$fence] ?? $fence;
    }

    private function operatorTakesLimits(string $base): bool
    {
        return in_array($base, ['\\sum', '\\prod', '\\int', '\\lim'], true);
    }

    private function normalizeXmlEntities(string $xml): string
    {
        return str_replace('&nbsp;', '&#160;', $xml);
    }

    private function normalizeTokenText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function normalizeTex(string $tex): string
    {
        $tex = str_replace(["\r\n", "\r"], "\n", $tex);
        $tex = preg_replace('/[ \t]+/u', ' ', $tex) ?? $tex;
        $tex = preg_replace('/ *\n */u', "\n", $tex) ?? $tex;

        return trim($tex);
    }

    private function escapeText(string $text): string
    {
        return str_replace(
            ['\\', '{', '}'],
            ['\\textbackslash{}', '\\{', '\\}'],
            $text
        );
    }
}
