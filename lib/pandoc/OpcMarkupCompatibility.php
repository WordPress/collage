<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcMarkupCompatibility
{
    public const NAMESPACE_URI = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    private const XMLNS_NAMESPACE_URI = 'http://www.w3.org/2000/xmlns/';

    private function __construct()
    {
    }

    /**
     * @return array<string, true>
     */
    public static function ignorableNamespacesForElement(\DOMElement $element, string $packageNamespace, string $label): array
    {
        $value = $element->getAttributeNS(self::NAMESPACE_URI, 'Ignorable');
        if (trim($value) === '') {
            return [];
        }

        $namespaces = [];
        foreach (preg_split('/\s+/', trim($value)) ?: [] as $prefix) {
            if ($prefix === '' || preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $prefix) !== 1) {
                throw new \InvalidArgumentException($label . ' mc:Ignorable contains invalid prefix: ' . $prefix);
            }

            $namespace = $element->lookupNamespaceURI($prefix);
            if ($namespace === null || $namespace === '') {
                throw new \InvalidArgumentException($label . ' mc:Ignorable references undeclared prefix: ' . $prefix);
            }

            if ($namespace === $packageNamespace || $namespace === self::NAMESPACE_URI || $namespace === self::XMLNS_NAMESPACE_URI) {
                throw new \InvalidArgumentException($label . ' mc:Ignorable cannot ignore core package namespaces: ' . $prefix);
            }

            $namespaces[$namespace] = true;
        }

        return $namespaces;
    }

    public static function isNamespaceDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::XMLNS_NAMESPACE_URI;
    }

    public static function isIgnorableDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::NAMESPACE_URI && $attribute->localName === 'Ignorable';
    }

    public static function isProcessContentDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::NAMESPACE_URI && $attribute->localName === 'ProcessContent';
    }

    public static function isPreserveElementsDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::NAMESPACE_URI && $attribute->localName === 'PreserveElements';
    }

    public static function isPreserveAttributesDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::NAMESPACE_URI && $attribute->localName === 'PreserveAttributes';
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @return array<string, true>
     */
    public static function processContentElementsForElement(\DOMElement $element, array $ignorableNamespaces, string $label): array
    {
        $value = $element->getAttributeNS(self::NAMESPACE_URI, 'ProcessContent');
        if (trim($value) === '') {
            return [];
        }

        $elements = [];
        foreach (preg_split('/\s+/', trim($value)) ?: [] as $name) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):([A-Za-z_][A-Za-z0-9._-]*|\*)$/D', $name, $matches) !== 1) {
                throw new \InvalidArgumentException($label . ' mc:ProcessContent contains invalid QName: ' . $name);
            }

            $namespace = $element->lookupNamespaceURI($matches[1]);
            if ($namespace === null || $namespace === '') {
                throw new \InvalidArgumentException($label . ' mc:ProcessContent references undeclared prefix: ' . $matches[1]);
            }

            if (!isset($ignorableNamespaces[$namespace])) {
                throw new \InvalidArgumentException($label . ' mc:ProcessContent must reference ignorable extension namespaces: ' . $matches[1]);
            }

            $elements[$namespace . "\0" . $matches[2]] = true;
        }

        return $elements;
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @return array<string, true>
     */
    public static function preserveElementsForElement(\DOMElement $element, array $ignorableNamespaces, string $label): array
    {
        return self::preserveQualifiedNamesForElement(
            $element,
            'PreserveElements',
            $ignorableNamespaces,
            $label,
            true,
        );
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @return array<string, true>
     */
    public static function preserveAttributesForElement(\DOMElement $element, array $ignorableNamespaces, string $label): array
    {
        return self::preserveQualifiedNamesForElement(
            $element,
            'PreserveAttributes',
            $ignorableNamespaces,
            $label,
            true,
        );
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @param array<string, true> $processContentElements
     * @return list<\DOMElement>
     */
    public static function packageChildElements(
        \DOMElement $element,
        string $packageNamespace,
        array $ignorableNamespaces,
        array $processContentElements,
        string $unsupportedElementMessage,
        string $textContentMessage,
    ): array {
        $children = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ($child->namespaceURI === $packageNamespace) {
                    $children[] = $child;
                    continue;
                }

                if ($child->namespaceURI === self::NAMESPACE_URI && $child->localName === 'AlternateContent') {
                    array_push(
                        $children,
                        ...self::alternateContentPackageChildElements(
                            $child,
                            $packageNamespace,
                            $ignorableNamespaces,
                            $processContentElements,
                            $unsupportedElementMessage,
                            $textContentMessage,
                        )
                    );
                    continue;
                }

                if (self::isIgnorableExtensionElement($child, $ignorableNamespaces)) {
                    if (self::shouldProcessContent($child, $processContentElements)) {
                        array_push(
                            $children,
                            ...self::packageChildElements(
                                $child,
                                $packageNamespace,
                                $ignorableNamespaces,
                                $processContentElements,
                                $unsupportedElementMessage,
                                $textContentMessage
                            )
                        );
                    }

                    continue;
                }

                throw new \InvalidArgumentException($unsupportedElementMessage);
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException($textContentMessage);
            }
        }

        return $children;
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @param array<string, true> $processContentElements
     * @return list<\DOMElement>
     */
    private static function alternateContentPackageChildElements(
        \DOMElement $alternateContent,
        string $packageNamespace,
        array $ignorableNamespaces,
        array $processContentElements,
        string $unsupportedElementMessage,
        string $textContentMessage,
    ): array {
        self::assertAlternateContentAttributes($alternateContent, 'OPC AlternateContent');

        $selectedChoice = null;
        $fallback = null;
        $choiceSeen = false;
        $fallbackSeen = false;

        foreach ($alternateContent->childNodes as $child) {
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') === '') {
                continue;
            }

            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NAMESPACE_URI) {
                throw new \InvalidArgumentException('OPC AlternateContent may only contain mc:Choice and mc:Fallback children');
            }

            if ($child->localName === 'Choice') {
                if ($fallbackSeen) {
                    throw new \InvalidArgumentException('OPC AlternateContent mc:Choice children must precede mc:Fallback');
                }

                $choiceSeen = true;
                self::assertAlternateContentChoiceAttributes($child);
                if (
                    $selectedChoice === null
                    && self::alternateContentChoiceRequiresSupportedPackageNamespace($child, $packageNamespace)
                ) {
                    $selectedChoice = $child;
                }
                continue;
            }

            if ($child->localName === 'Fallback') {
                if ($fallbackSeen) {
                    throw new \InvalidArgumentException('OPC AlternateContent must not contain more than one mc:Fallback');
                }

                $fallbackSeen = true;
                self::assertAlternateContentAttributes($child, 'OPC AlternateContent mc:Fallback');
                $fallback = $child;
                continue;
            }

            throw new \InvalidArgumentException('OPC AlternateContent may only contain mc:Choice and mc:Fallback children');
        }

        if (!$choiceSeen) {
            throw new \InvalidArgumentException('OPC AlternateContent must contain at least one mc:Choice');
        }

        $selectedBranch = $selectedChoice ?? $fallback;
        if (!$selectedBranch instanceof \DOMElement) {
            throw new \InvalidArgumentException('OPC AlternateContent has no supported mc:Choice and no mc:Fallback');
        }

        return self::packageChildElements(
            $selectedBranch,
            $packageNamespace,
            $ignorableNamespaces,
            $processContentElements,
            $unsupportedElementMessage,
            $textContentMessage,
        );
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    public static function isIgnorableExtensionAttribute(\DOMAttr $attribute, array $ignorableNamespaces): bool
    {
        $namespace = $attribute->namespaceURI ?? '';

        return $namespace !== '' && isset($ignorableNamespaces[$namespace]);
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    public static function isIgnorableExtensionElement(\DOMElement $element, array $ignorableNamespaces): bool
    {
        $namespace = $element->namespaceURI ?? '';

        return $namespace !== '' && isset($ignorableNamespaces[$namespace]);
    }

    /**
     * @param array<string, true> $processContentElements
     */
    private static function shouldProcessContent(\DOMElement $element, array $processContentElements): bool
    {
        $namespace = $element->namespaceURI ?? '';

        return isset($processContentElements[$namespace . "\0" . $element->localName])
            || isset($processContentElements[$namespace . "\0*"]);
    }

    private static function assertAlternateContentAttributes(\DOMElement $element, string $label): void
    {
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (self::isNamespaceDeclaration($attribute)) {
                continue;
            }

            throw new \InvalidArgumentException($label . ' contains unsupported attribute: ' . $attribute->name);
        }
    }

    private static function assertAlternateContentChoiceAttributes(\DOMElement $choice): void
    {
        $hasRequires = false;
        foreach ($choice->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (self::isNamespaceDeclaration($attribute)) {
                continue;
            }

            if (($attribute->namespaceURI ?? '') === '' && $attribute->name === 'Requires') {
                $hasRequires = true;
                continue;
            }

            throw new \InvalidArgumentException('OPC AlternateContent mc:Choice contains unsupported attribute: ' . $attribute->name);
        }

        if (!$hasRequires || trim($choice->getAttribute('Requires')) === '') {
            throw new \InvalidArgumentException('OPC AlternateContent mc:Choice requires a non-empty Requires attribute');
        }
    }

    private static function alternateContentChoiceRequiresSupportedPackageNamespace(\DOMElement $choice, string $packageNamespace): bool
    {
        foreach (preg_split('/\s+/', trim($choice->getAttribute('Requires'))) ?: [] as $prefix) {
            if ($prefix === '' || preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $prefix) !== 1) {
                throw new \InvalidArgumentException('OPC AlternateContent mc:Choice Requires contains invalid prefix: ' . $prefix);
            }

            $namespace = $choice->lookupNamespaceURI($prefix);
            if ($namespace === null || $namespace === '') {
                throw new \InvalidArgumentException('OPC AlternateContent mc:Choice Requires references undeclared prefix: ' . $prefix);
            }

            if ($namespace !== $packageNamespace) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @return array<string, true>
     */
    private static function preserveQualifiedNamesForElement(
        \DOMElement $element,
        string $attributeName,
        array $ignorableNamespaces,
        string $label,
        bool $allowWildcard,
    ): array {
        $value = $element->getAttributeNS(self::NAMESPACE_URI, $attributeName);
        if (trim($value) === '') {
            return [];
        }

        $names = [];
        $localNamePattern = $allowWildcard
            ? '[A-Za-z_][A-Za-z0-9._-]*|\*'
            : '[A-Za-z_][A-Za-z0-9._-]*';
        foreach (preg_split('/\s+/', trim($value)) ?: [] as $name) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):(' . $localNamePattern . ')$/D', $name, $matches) !== 1) {
                throw new \InvalidArgumentException($label . ' mc:' . $attributeName . ' contains invalid QName: ' . $name);
            }

            $namespace = $element->lookupNamespaceURI($matches[1]);
            if ($namespace === null || $namespace === '') {
                throw new \InvalidArgumentException($label . ' mc:' . $attributeName . ' references undeclared prefix: ' . $matches[1]);
            }

            if (!isset($ignorableNamespaces[$namespace])) {
                throw new \InvalidArgumentException($label . ' mc:' . $attributeName . ' must reference ignorable extension namespaces: ' . $matches[1]);
            }

            $names[$namespace . "\0" . $matches[2]] = true;
        }

        return $names;
    }
}
