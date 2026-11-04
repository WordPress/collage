<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlHtml5Dom
{
    public static function parseHtmlDocument(string $html): ?\DOMDocument
    {
        try {
            return Html5Dom::parseHtmlDocument($html);
        } catch (\RuntimeException) {
            return null;
        }
    }

    public static function parseHtmlDocumentBody(string $html): ?\DOMElement
    {
        $document = self::parseHtmlDocument($html);

        return $document instanceof \DOMDocument ? self::htmlBody($document) : null;
    }

    public static function parseHtmlFragment(string $html): ?\DOMDocument
    {
        try {
            $body = Html5Dom::parseHtmlFragment($html);
        } catch (\RuntimeException) {
            return null;
        }

        return $body->ownerDocument instanceof \DOMDocument ? $body->ownerDocument : null;
    }

    public static function parseHtmlFragmentBody(string $html): ?\DOMElement
    {
        $document = self::parseHtmlFragment($html);

        return $document instanceof \DOMDocument ? self::htmlBody($document) : null;
    }

    public static function htmlBody(\DOMDocument $document): ?\DOMElement
    {
        $body = $document->getElementsByTagName('body')->item(0);

        return $body instanceof \DOMElement ? $body : null;
    }

    public static function parseXmlDocument(string $xml, string $label = 'XML'): \DOMDocument
    {
        self::assertNoXmlStylesheetProcessingInstruction($xml, $label);

        return XmlHtmlDom::loadXmlDocument($xml, $label);
    }

    /**
     * The compatibility XML loader intentionally strips ordinary processing
     * instructions after parsing so package readers can recover harmless
     * office-document metadata.  An xml-stylesheet instruction is different:
     * it names an external stylesheet and must be rejected by this hardened
     * facade before libxml gets a chance to repair or discard it.
     *
     * Declaration-looking text inside a closed comment or CDATA section is
     * data, not a processing instruction.
     */
    private static function assertNoXmlStylesheetProcessingInstruction(string $xml, string $label): void
    {
        $length = strlen($xml);
        $offset = 0;

        while ($offset < $length) {
            if (substr_compare($xml, '<!--', $offset, 4) === 0) {
                $commentEnd = strpos($xml, '-->', $offset + 4);
                if ($commentEnd === false) {
                    return;
                }

                $offset = $commentEnd + 3;
                continue;
            }

            if (substr_compare($xml, '<![CDATA[', $offset, 9) === 0) {
                $cdataEnd = strpos($xml, ']]>', $offset + 9);
                if ($cdataEnd === false) {
                    return;
                }

                $offset = $cdataEnd + 3;
                continue;
            }

            if (substr_compare($xml, '<?xml-stylesheet', $offset, 16, true) === 0) {
                $next = $xml[$offset + 16] ?? '';
                if ($next === '' || ctype_space($next) || $next === '?') {
                    throw new \InvalidArgumentException($label . ' must not include xml-stylesheet processing instructions');
                }
            }

            ++$offset;
        }
    }

    public static function serializeHtmlFragment(\DOMNode $node): string
    {
        if ($node instanceof \DOMDocument) {
            $body = self::htmlBody($node);

            return $body instanceof \DOMElement ? self::serializeHtmlFragment($body) : '';
        }

        $document = $node->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \InvalidArgumentException('HTML fragment node must belong to a DOMDocument');
        }

        if ($node instanceof \DOMElement && strtolower($node->localName) === 'body') {
            return Html5Dom::serializeHtmlChildren($node);
        }

        return XmlHtmlDom::serializeHtmlNode($node);
    }
}
