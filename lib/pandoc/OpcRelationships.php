<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationships
{
    public const NAMESPACE_URI = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const RELATIONSHIP_PART_CONTENT_TYPE = 'application/vnd.openxmlformats-package.relationships+xml';

    /** @var list<OpcRelationship> */
    private array $relationships = [];

    /** @var array<string, OpcRelationship> */
    private array $byId = [];

    public function __construct(private readonly string $sourcePartName = '/')
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        self::assertRelationshipSourcePartName($sourcePartName);
    }

    public static function fromXml(string $xml, string $sourcePartName = '/'): self
    {
        $dom = self::loadXml($xml);
        $root = XmlHtmlDom::rootElement($dom, 'Relationships', self::NAMESPACE_URI);
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('OPC relationships XML must use the package relationships namespace');
        }

        $ignorableNamespaces = OpcMarkupCompatibility::ignorableNamespacesForElement($root, self::NAMESPACE_URI, 'OPC relationships XML root');
        $processContentElements = OpcMarkupCompatibility::processContentElementsForElement($root, $ignorableNamespaces, 'OPC relationships XML root');
        OpcMarkupCompatibility::preserveElementsForElement($root, $ignorableNamespaces, 'OPC relationships XML root');
        OpcMarkupCompatibility::preserveAttributesForElement($root, $ignorableNamespaces, 'OPC relationships XML root');
        self::assertRootShape($root, $ignorableNamespaces);

        $relationships = new self($sourcePartName);
        foreach (OpcMarkupCompatibility::packageChildElements(
            $root,
            self::NAMESPACE_URI,
            $ignorableNamespaces,
            $processContentElements,
            'OPC relationships XML may only contain Relationship children',
            'OPC relationships XML root may not contain text content'
        ) as $child) {
            if ($child->localName !== 'Relationship') {
                throw new \InvalidArgumentException('OPC relationships XML may only contain Relationship children');
            }

            self::assertRelationshipElementShape($child, $ignorableNamespaces);
            self::assertRelationshipRequiredAttributes($child);

            $relationships->add(new OpcRelationship(
                $child->getAttribute('Id'),
                $child->getAttribute('Type'),
                $child->getAttribute('Target'),
                $child->hasAttribute('TargetMode') ? $child->getAttribute('TargetMode') : OpcRelationship::TARGET_MODE_INTERNAL,
                $child->hasAttribute('TargetMode'),
            ));
        }

        return $relationships;
    }

    public static function fromPackage(ZipPackage $package, string $sourcePartName = '/'): self
    {
        return self::fromPackageWithReadLimit($package, $sourcePartName, null);
    }

    public static function fromPackageBounded(
        ZipPackage $package,
        string $sourcePartName,
        int $maxUncompressedBytes
    ): self {
        self::assertNonNegativeMaxUncompressedBytes($maxUncompressedBytes);

        return self::fromPackageWithReadLimit($package, $sourcePartName, $maxUncompressedBytes);
    }

    public static function packageHasRelationshipsForSource(ZipPackage $package, string $sourcePartName = '/'): bool
    {
        return self::relationshipPartsForSourceInPackage($package, $sourcePartName) !== [];
    }

    public static function packageHasRelationshipsForSourceBounded(
        ZipPackage $package,
        string $sourcePartName,
        int $maxUncompressedBytes
    ): bool {
        self::assertNonNegativeMaxUncompressedBytes($maxUncompressedBytes);

        return self::relationshipPartsForSourceInPackage($package, $sourcePartName, $maxUncompressedBytes) !== [];
    }

    private static function fromPackageWithReadLimit(
        ZipPackage $package,
        string $sourcePartName,
        ?int $maxUncompressedBytes
    ): self {
        $relationshipPart = self::relationshipPartForSourceInPackage($package, $sourcePartName, $maxUncompressedBytes);
        if ($relationshipPart === null) {
            throw new \RuntimeException('OPC relationship part not found: ' . self::relationshipPartNameForSource($sourcePartName));
        }

        return self::fromXml(
            self::readPackagePart($package, $relationshipPart['relationshipPartName'], $maxUncompressedBytes),
            $relationshipPart['sourcePartName']
        );
    }

    public static function relationshipPartNameForSource(string $sourcePartName): string
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        self::assertRelationshipSourcePartName($sourcePartName);
        if ($sourcePartName === '/') {
            return '/_rels/.rels';
        }

        $sourceUri = OpcPackagePath::partNameToUri($sourcePartName);
        $dir = dirname($sourceUri);
        $base = basename($sourceUri);

        return ($dir === '/' ? '' : $dir) . '/_rels/' . $base . '.rels';
    }

    public static function sourcePartNameForRelationshipPart(string $relationshipPartName): string
    {
        self::assertRelationshipPartNameRawSegments($relationshipPartName);
        $relationshipPartName = OpcPackagePath::canonicalPartName($relationshipPartName);
        if ($relationshipPartName === '/_rels/.rels') {
            return '/';
        }

        if (!str_ends_with($relationshipPartName, '.rels')) {
            throw new \InvalidArgumentException('OPC relationship part names must end with .rels');
        }

        $marker = '/_rels/';
        $position = strrpos($relationshipPartName, $marker);
        if ($position === false) {
            throw new \InvalidArgumentException('OPC relationship part names must contain a _rels segment');
        }

        $dir = substr($relationshipPartName, 0, $position);
        $base = substr($relationshipPartName, $position + strlen($marker), -5);
        if ($base === '') {
            throw new \InvalidArgumentException('OPC relationship part names must identify a source part');
        }

        if (str_contains($base, '/')) {
            throw new \InvalidArgumentException('OPC relationship part names must store a single .rels file inside a _rels directory');
        }

        return OpcPackagePath::canonicalPartNameFromUri(($dir === '' ? '/' : $dir . '/') . $base);
    }

    public static function isRelationshipPartName(string $partName): bool
    {
        try {
            self::assertRelationshipPartNameRawSegments($partName);
            $partName = OpcPackagePath::canonicalPartName($partName);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $partName === '/_rels/.rels'
            || (str_ends_with($partName, '.rels') && str_contains($partName, '/_rels/'));
    }

    public function add(OpcRelationship $relationship): void
    {
        if (isset($this->byId[$relationship->id])) {
            throw new \InvalidArgumentException('Duplicate OPC relationship Id: ' . $relationship->id);
        }

        $this->relationships[] = $relationship;
        $this->byId[$relationship->id] = $relationship;
    }

    /**
     * @return list<OpcRelationship>
     */
    public function all(): array
    {
        return $this->relationships;
    }

    public function byId(string $id): ?OpcRelationship
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * @return list<OpcRelationship>
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->relationships,
            static fn (OpcRelationship $relationship): bool => $relationship->type === $type,
        ));
    }

    public function firstOfType(string $type): ?OpcRelationship
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->type === $type) {
                return $relationship;
            }
        }

        return null;
    }

    /**
     * @param list<string> $types
     */
    public function firstOfTypes(array $types): ?OpcRelationship
    {
        foreach ($this->relationships as $relationship) {
            if (in_array($relationship->type, $types, true)) {
                return $relationship;
            }
        }

        return null;
    }

    public function relationshipPartName(): string
    {
        return self::relationshipPartNameForSource($this->sourcePartName);
    }

    public function resolveTarget(OpcRelationship|string $relationship): string
    {
        if (is_string($relationship)) {
            $resolved = $this->byId($relationship);
            if (!$resolved instanceof OpcRelationship) {
                throw new \InvalidArgumentException('Unknown OPC relationship Id: ' . $relationship);
            }
            $relationship = $resolved;
        }

        if ($relationship->isExternal()) {
            return $relationship->target;
        }

        return OpcPackagePath::resolveInternalTarget($this->sourcePartName, $relationship->target);
    }

    public function toXml(): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS(self::NAMESPACE_URI, 'Relationships');
        $dom->appendChild($root);

        foreach ($this->relationships as $relationship) {
            $element = $dom->createElementNS(self::NAMESPACE_URI, 'Relationship');
            $element->setAttribute('Id', $relationship->id);
            $element->setAttribute('Type', $relationship->type);
            $element->setAttribute('Target', self::targetForXmlAttribute($relationship, $this->sourcePartName));
            if ($relationship->targetMode !== OpcRelationship::TARGET_MODE_INTERNAL) {
                $element->setAttribute('TargetMode', $relationship->targetMode);
            }
            $root->appendChild($element);
        }

        $xml = $dom->saveXML($root);
        if ($xml === false) {
            throw new \RuntimeException('Failed to serialize OPC relationships XML');
        }

        return $xml;
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    private static function assertRootShape(\DOMElement $root, array $ignorableNamespaces): void
    {
        foreach ($root->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (
                OpcMarkupCompatibility::isNamespaceDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableDeclaration($attribute)
                || OpcMarkupCompatibility::isProcessContentDeclaration($attribute)
                || OpcMarkupCompatibility::isPreserveElementsDeclaration($attribute)
                || OpcMarkupCompatibility::isPreserveAttributesDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableExtensionAttribute($attribute, $ignorableNamespaces)
            ) {
                continue;
            }

            throw new \InvalidArgumentException('OPC relationships XML root contains unsupported attribute: ' . $attribute->name);
        }

        foreach ($root->childNodes as $child) {
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException('OPC relationships XML root may not contain text content');
            }
        }
    }

    private static function loadXml(string $xml): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, 'OPC relationships XML');
    }

    /**
     * @return array{relationshipPartName:string, sourcePartName:string}|null
     */
    private static function relationshipPartForSourceInPackage(
        ZipPackage $package,
        string $sourcePartName,
        ?int $maxUncompressedBytes = null
    ): ?array
    {
        $relationshipParts = self::relationshipPartsForSourceInPackage($package, $sourcePartName, $maxUncompressedBytes);
        if ($relationshipParts === []) {
            return null;
        }

        if (count($relationshipParts) > 1) {
            throw new \RuntimeException(
                'Duplicate OPC relationship parts for source: '
                . implode(', ', array_column($relationshipParts, 'relationshipPartName'))
            );
        }

        return $relationshipParts[0];
    }

    /**
     * @return list<array{relationshipPartName:string, sourcePartName:string}>
     */
    private static function relationshipPartsForSourceInPackage(
        ZipPackage $package,
        string $sourcePartName,
        ?int $maxUncompressedBytes = null
    ): array
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        self::assertRelationshipSourcePartName($sourcePartName);
        $sourceEquivalenceKey = self::partNameEquivalenceKey($sourcePartName);
        $contentTypes = self::contentTypesForPackage($package, $maxUncompressedBytes);
        $relationshipParts = [];

        foreach ($package->names() as $packageName) {
            if (str_ends_with($packageName, '/')) {
                continue;
            }

            try {
                $relationshipPartName = OpcPackagePath::canonicalPartName($packageName);
                if (!self::isRelationshipPartName($relationshipPartName)) {
                    continue;
                }

                $representedSourcePartName = self::sourcePartNameForRelationshipPart($relationshipPartName);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (self::partNameEquivalenceKey($representedSourcePartName) !== $sourceEquivalenceKey) {
                continue;
            }

            if (
                $contentTypes instanceof OpcContentTypes
                && !self::contentTypeMatches($contentTypes->contentTypeForPart($relationshipPartName), self::RELATIONSHIP_PART_CONTENT_TYPE)
            ) {
                continue;
            }

            $relationshipParts[] = [
                'relationshipPartName' => $relationshipPartName,
                'sourcePartName' => $representedSourcePartName,
            ];
        }

        usort(
            $relationshipParts,
            static fn (array $left, array $right): int => $left['relationshipPartName'] <=> $right['relationshipPartName'],
        );

        return $relationshipParts;
    }

    private static function contentTypesForPackage(ZipPackage $package, ?int $maxUncompressedBytes = null): ?OpcContentTypes
    {
        $contentTypesItemName = self::contentTypesItemNameInPackage($package);
        if ($contentTypesItemName === null) {
            return null;
        }

        return OpcContentTypes::fromXml(self::readPackagePart($package, $contentTypesItemName, $maxUncompressedBytes));
    }

    private static function readPackagePart(
        ZipPackage $package,
        string $partName,
        ?int $maxUncompressedBytes
    ): string {
        if ($maxUncompressedBytes === null) {
            return $package->read($partName);
        }

        return $package->readBounded($partName, $maxUncompressedBytes);
    }

    private static function assertNonNegativeMaxUncompressedBytes(int $maxUncompressedBytes): void
    {
        if ($maxUncompressedBytes < 0) {
            throw new \InvalidArgumentException('OPC relationship read limit must not be negative');
        }
    }

    private static function contentTypeMatches(?string $actual, string $expected): bool
    {
        if ($actual === null) {
            return false;
        }

        return self::contentTypeComparisonKey($actual) === self::contentTypeComparisonKey($expected);
    }

    private static function contentTypeComparisonKey(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    private static function partNameEquivalenceKey(string $partName): string
    {
        return strtolower($partName);
    }

    private static function assertRelationshipSourcePartName(string $sourcePartName): void
    {
        if ($sourcePartName !== '/' && self::isRelationshipPartName($sourcePartName)) {
            throw new \InvalidArgumentException('OPC relationship parts must not be relationship sources');
        }

        if ($sourcePartName !== '/' && self::isContentTypesItemName($sourcePartName)) {
            throw new \InvalidArgumentException('OPC content types item must not be a relationship source');
        }
    }

    private static function isContentTypesItemName(string $partName): bool
    {
        return strtolower(OpcPackagePath::canonicalPartName($partName)) === '/[content_types].xml';
    }

    private static function contentTypesItemNameInPackage(ZipPackage $package): ?string
    {
        foreach ($package->names() as $name) {
            if (str_ends_with($name, '/')) {
                continue;
            }

            try {
                if (self::isContentTypesItemName($name)) {
                    return $name;
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return null;
    }

    private static function assertRelationshipPartNameRawSegments(string $relationshipPartName): void
    {
        $path = str_starts_with($relationshipPartName, '/') ? $relationshipPartName : '/' . $relationshipPartName;
        $segments = explode('/', $path);
        array_shift($segments);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('OPC relationship part names must not contain empty or dot path segments');
            }
        }
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    private static function assertRelationshipElementShape(\DOMElement $element, array $ignorableNamespaces): void
    {
        $allowedAttributes = ['Id', 'Type', 'Target', 'TargetMode'];
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (
                OpcMarkupCompatibility::isNamespaceDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableExtensionAttribute($attribute, $ignorableNamespaces)
            ) {
                continue;
            }

            if (($attribute->namespaceURI ?? '') !== '' || !in_array($attribute->name, $allowedAttributes, true)) {
                throw new \InvalidArgumentException('OPC Relationship record contains unsupported attribute: ' . $attribute->name);
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if (OpcMarkupCompatibility::isIgnorableExtensionElement($child, $ignorableNamespaces)) {
                    continue;
                }

                throw new \InvalidArgumentException('OPC Relationship record must be an empty element');
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException('OPC Relationship record must be an empty element');
            }
        }
    }

    private static function assertRelationshipRequiredAttributes(\DOMElement $element): void
    {
        foreach (['Id', 'Type', 'Target'] as $attributeName) {
            if (!$element->hasAttribute($attributeName) || $element->getAttribute($attributeName) === '') {
                throw new \InvalidArgumentException('OPC Relationship record is missing required ' . $attributeName . ' attribute');
            }
        }
    }

    private static function targetForXmlAttribute(OpcRelationship $relationship, string $sourcePartName): string
    {
        if ($relationship->isExternal()) {
            self::assertSerializableExternalTarget($relationship);

            return $relationship->target;
        }

        $split = strcspn($relationship->target, '?#');
        $path = substr($relationship->target, 0, $split);
        $suffix = substr($relationship->target, $split);
        if ($path === '') {
            self::assertSerializableInternalTarget($relationship->target, $sourcePartName);

            return $relationship->target;
        }

        $target = self::encodeInternalTargetPath($path) . $suffix;
        self::assertSerializableInternalTarget($target, $sourcePartName);

        return $target;
    }

    private static function encodeInternalTargetPath(string $path): string
    {
        $encoded = '';
        $length = strlen($path);
        for ($index = 0; $index < $length; $index++) {
            $byte = ord($path[$index]);
            $char = $path[$index];

            if ($char === '%') {
                if ($index + 2 >= $length || !ctype_xdigit($path[$index + 1]) || !ctype_xdigit($path[$index + 2])) {
                    throw new \InvalidArgumentException('OPC relationship target contains malformed percent escape');
                }

                $encoded .= '%' . strtoupper(substr($path, $index + 1, 2));
                $index += 2;
                continue;
            }

            if (
                ($byte >= 0x41 && $byte <= 0x5A)
                || ($byte >= 0x61 && $byte <= 0x7A)
                || ($byte >= 0x30 && $byte <= 0x39)
                || str_contains("-._~!$&'()*+,;=:@/", $char)
            ) {
                $encoded .= $char;
                continue;
            }

            $encoded .= sprintf('%%%02X', $byte);
        }

        return $encoded;
    }

    private static function assertSerializableInternalTarget(string $target, string $sourcePartName): void
    {
        try {
            OpcPackagePath::resolveInternalTarget($sourcePartName, $target);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException(
                'OPC relationship target cannot be serialized as a valid internal URI reference: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    private static function assertSerializableExternalTarget(OpcRelationship $relationship): void
    {
        $preflight = $relationship->externalTargetPreflight();
        if ($preflight['issues'] === []) {
            return;
        }

        throw new \InvalidArgumentException(
            'OPC external relationship target cannot be serialized safely: '
            . implode(', ', $preflight['issues'])
        );
    }
}
