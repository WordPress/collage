<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcContentTypes
{
    public const NAMESPACE_URI = 'http://schemas.openxmlformats.org/package/2006/content-types';

    /** @var array<string, array{extension:string, contentType:string}> */
    private array $defaults = [];

    /** @var array<string, string> */
    private array $overrides = [];

    /** @var array<string, string> */
    private array $overridePartNamesByEquivalenceKey = [];

    public static function fromXml(string $xml): self
    {
        $dom = self::loadXml($xml);
        $root = XmlHtmlDom::rootElement($dom, 'Types', self::NAMESPACE_URI);
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('OPC content-types XML must use the package content-types namespace');
        }

        $ignorableNamespaces = OpcMarkupCompatibility::ignorableNamespacesForElement($root, self::NAMESPACE_URI, 'OPC content-types XML root');
        $processContentElements = OpcMarkupCompatibility::processContentElementsForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
        OpcMarkupCompatibility::preserveElementsForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
        OpcMarkupCompatibility::preserveAttributesForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
        self::assertRootShape($root, $ignorableNamespaces);

        $types = new self();
        foreach (OpcMarkupCompatibility::packageChildElements(
            $root,
            self::NAMESPACE_URI,
            $ignorableNamespaces,
            $processContentElements,
            'OPC content-types children must use the package namespace',
            'OPC content-types XML root may not contain text content'
        ) as $child) {
            if ($child->localName === 'Default') {
                self::assertRecordShape($child, ['Extension', 'ContentType'], 'OPC Default content-type record', $ignorableNamespaces);
                self::assertXmlDefaultExtension($child->getAttribute('Extension'));
                $types->addDefault($child->getAttribute('Extension'), $child->getAttribute('ContentType'));
                continue;
            }

            if ($child->localName === 'Override') {
                self::assertRecordShape($child, ['PartName', 'ContentType'], 'OPC Override content-type record', $ignorableNamespaces);
                self::assertXmlOverridePartName($child->getAttribute('PartName'));
                $types->addOverride($child->getAttribute('PartName'), $child->getAttribute('ContentType'));
                continue;
            }

            throw new \InvalidArgumentException('Unsupported OPC content-types element: ' . $child->localName);
        }

        return $types;
    }

    /**
     * @return array{valid:bool, parseError:?string, recordCount:int, defaultCount:int, overrideCount:int, invalidCount:int, duplicateDefaultExtensionCount:int, duplicateOverridePartNameCount:int, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, duplicateDefaultExtensionGroups:array<string, list<string>>, duplicateOverridePartNameGroups:array<string, list<string>>, issueCounts:array<string, int>, issues:list<string>, records:list<array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}>}
     */
    public static function preflightXml(string $xml): array
    {
        $summary = self::contentTypesPreflightSkeleton();

        try {
            $dom = self::loadXml($xml);
            $root = XmlHtmlDom::rootElement($dom, 'Types', self::NAMESPACE_URI);
            if (!$root instanceof \DOMElement) {
                throw new \InvalidArgumentException('OPC content-types XML must use the package content-types namespace');
            }

            $ignorableNamespaces = OpcMarkupCompatibility::ignorableNamespacesForElement($root, self::NAMESPACE_URI, 'OPC content-types XML root');
            $processContentElements = OpcMarkupCompatibility::processContentElementsForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
            OpcMarkupCompatibility::preserveElementsForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
            OpcMarkupCompatibility::preserveAttributesForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
            self::assertRootShape($root, $ignorableNamespaces);
            $children = OpcMarkupCompatibility::packageChildElements(
                $root,
                self::NAMESPACE_URI,
                $ignorableNamespaces,
                $processContentElements,
                'OPC content-types children must use the package namespace',
                'OPC content-types XML root may not contain text content'
            );
        } catch (\Throwable $exception) {
            $summary['valid'] = false;
            $summary['parseError'] = $exception->getMessage();
            self::appendPreflightIssue($summary, 'content-types-xml-parse-error');

            return $summary;
        }

        foreach ($children as $child) {
            $summary['records'][] = self::preflightRecord($child, count($summary['records']), $ignorableNamespaces);
        }

        self::markDuplicatePreflightRecords($summary);
        self::refreshPreflightSummary($summary);

        return $summary;
    }

    public function addDefault(string $extension, string $contentType): void
    {
        $extension = self::normalizeExtension($extension);
        self::assertContentType($contentType);

        if (isset($this->defaults[strtolower($extension)])) {
            throw new \InvalidArgumentException('Duplicate OPC default content type for extension: ' . $extension);
        }

        $this->defaults[strtolower($extension)] = [
            'extension' => $extension,
            'contentType' => $contentType,
        ];
    }

    public function addOverride(string $partName, string $contentType): void
    {
        $partName = OpcPackagePath::canonicalPartNameFromUri($partName);
        self::assertContentType($contentType);
        $equivalenceKey = self::partNameEquivalenceKey($partName);

        if (isset($this->overridePartNamesByEquivalenceKey[$equivalenceKey])) {
            throw new \InvalidArgumentException('Duplicate OPC override content type for part: ' . $partName);
        }

        $this->overrides[$partName] = $contentType;
        $this->overridePartNamesByEquivalenceKey[$equivalenceKey] = $partName;
    }

    public function contentTypeForPart(string $partName): ?string
    {
        return $this->contentTypeResolutionForPart($partName)['contentType'];
    }

    /**
     * @return array{uriReference:string, partName:string, uriReferenceSuffix:string, uriReferenceQuery:?string, uriReferenceFragment:?string, hasUriReferenceSuffix:bool, contentType:?string, contentTypeSource:string, defaultExtension:?string, overridePartName:?string, overridePartNameExactMatch:bool, overridePartNameEquivalentMatch:bool}
     */
    public function contentTypeResolutionForPart(string $partName): array
    {
        $uriReference = $partName;
        $suffix = self::uriReferenceSuffix($partName);
        $partName = OpcPackagePath::canonicalPartNameFromUri(OpcPackagePath::stripQueryAndFragment($partName));
        $base = self::resolutionBase($uriReference, $partName, $suffix);
        if (isset($this->overrides[$partName])) {
            return [
                ...$base,
                'partName' => $partName,
                'contentType' => $this->overrides[$partName],
                ...self::contentTypeMetadata($this->overrides[$partName]),
                'contentTypeSource' => 'override',
                'defaultExtension' => null,
                'overridePartName' => $partName,
                'overridePartNameExactMatch' => true,
                'overridePartNameEquivalentMatch' => false,
            ];
        }

        $overridePartName = $this->overridePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] ?? null;
        if ($overridePartName !== null) {
            return [
                ...$base,
                'partName' => $partName,
                'contentType' => $this->overrides[$overridePartName],
                ...self::contentTypeMetadata($this->overrides[$overridePartName]),
                'contentTypeSource' => 'override',
                'defaultExtension' => null,
                'overridePartName' => $overridePartName,
                'overridePartNameExactMatch' => false,
                'overridePartNameEquivalentMatch' => true,
            ];
        }

        $basename = basename($partName);
        $dot = strrpos($basename, '.');
        if ($dot === false || $dot === strlen($basename) - 1) {
            return self::missingResolution($partName, $uriReference, $suffix);
        }

        $extension = strtolower(substr($basename, $dot + 1));
        $default = $this->defaults[$extension] ?? null;
        if ($default !== null) {
            return [
                ...$base,
                'partName' => $partName,
                'contentType' => $default['contentType'],
                ...self::contentTypeMetadata($default['contentType']),
                'contentTypeSource' => 'default',
                'defaultExtension' => $default['extension'],
                'overridePartName' => null,
                'overridePartNameExactMatch' => false,
                'overridePartNameEquivalentMatch' => false,
            ];
        }

        return self::missingResolution($partName, $uriReference, $suffix);
    }

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->defaults as $entry) {
            $defaults[$entry['extension']] = $entry['contentType'];
        }

        return $defaults;
    }

    /**
     * @return array<string, string>
     */
    public function overrides(): array
    {
        return $this->overrides;
    }

    public function toXml(): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS(self::NAMESPACE_URI, 'Types');
        $dom->appendChild($root);

        foreach ($this->defaults as $entry) {
            $default = $dom->createElementNS(self::NAMESPACE_URI, 'Default');
            $default->setAttribute('Extension', $entry['extension']);
            $default->setAttribute('ContentType', $entry['contentType']);
            $root->appendChild($default);
        }

        foreach ($this->overrides as $partName => $contentType) {
            $override = $dom->createElementNS(self::NAMESPACE_URI, 'Override');
            $override->setAttribute('PartName', OpcPackagePath::partNameToUri($partName));
            $override->setAttribute('ContentType', $contentType);
            $root->appendChild($override);
        }

        $xml = $dom->saveXML($root);
        if ($xml === false) {
            throw new \RuntimeException('Failed to serialize OPC content-types XML');
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

            throw new \InvalidArgumentException('OPC content-types XML root contains unsupported attribute: ' . $attribute->name);
        }

        foreach ($root->childNodes as $child) {
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException('OPC content-types XML root may not contain text content');
            }
        }
    }

    private static function normalizeExtension(string $extension): string
    {
        $extension = ltrim($extension, '.');
        if (
            $extension === ''
            || preg_match('/[\x00-\x20\x7F]/', $extension) === 1
            || str_contains($extension, '/')
            || str_contains($extension, '\\')
            || str_contains($extension, '?')
            || str_contains($extension, '#')
        ) {
            throw new \InvalidArgumentException('OPC content-type extension must be a simple extension name');
        }

        return $extension;
    }

    private static function assertXmlDefaultExtension(string $extension): void
    {
        if (str_starts_with($extension, '.')) {
            throw new \InvalidArgumentException('OPC Default extension must not include a leading dot');
        }
    }

    private static function assertXmlOverridePartName(string $partName): void
    {
        if (!str_starts_with($partName, '/')) {
            throw new \InvalidArgumentException('OPC Override part name must be an absolute package URI');
        }

        if (preg_match('/[\x00-\x20\x7F]/', $partName) === 1) {
            throw new \InvalidArgumentException('OPC Override part name must not contain raw whitespace or control characters');
        }

        $segments = explode('/', $partName);
        array_shift($segments);
        foreach ($segments as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === '' || $decoded === '.' || $decoded === '..') {
                throw new \InvalidArgumentException('OPC Override part name must not contain empty or dot path segments');
            }

            if (str_ends_with($decoded, '.')) {
                throw new \InvalidArgumentException('OPC Override part name segments must not end with a dot');
            }
        }
    }

    private static function partNameEquivalenceKey(string $partName): string
    {
        return strtolower($partName);
    }

    /**
     * @param array{suffix:string, query:?string, fragment:?string} $suffix
     * @return array{uriReference:string, partName:string, uriReferenceSuffix:string, uriReferenceQuery:?string, uriReferenceFragment:?string, hasUriReferenceSuffix:bool, contentType:null, contentTypeSource:string, defaultExtension:null, overridePartName:null, overridePartNameExactMatch:bool, overridePartNameEquivalentMatch:bool}
     */
    private static function missingResolution(string $partName, string $uriReference, array $suffix): array
    {
        return [
            ...self::resolutionBase($uriReference, $partName, $suffix),
            'partName' => $partName,
            'contentType' => null,
            ...self::emptyContentTypeMetadata(),
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'overridePartNameExactMatch' => false,
            'overridePartNameEquivalentMatch' => false,
        ];
    }

    /**
     * @param array{suffix:string, query:?string, fragment:?string} $suffix
     * @return array{uriReference:string, partName:string, uriReferenceSuffix:string, uriReferenceQuery:?string, uriReferenceFragment:?string, hasUriReferenceSuffix:bool}
     */
    private static function resolutionBase(string $uriReference, string $partName, array $suffix): array
    {
        return [
            'uriReference' => $uriReference,
            'partName' => $partName,
            'uriReferenceSuffix' => $suffix['suffix'],
            'uriReferenceQuery' => $suffix['query'],
            'uriReferenceFragment' => $suffix['fragment'],
            'hasUriReferenceSuffix' => $suffix['suffix'] !== '',
        ];
    }

    /**
     * @return array{suffix:string, query:?string, fragment:?string}
     */
    private static function uriReferenceSuffix(string $uriReference): array
    {
        $suffixOffset = strcspn($uriReference, '?#');
        $suffix = substr($uriReference, $suffixOffset);
        if ($suffix === '') {
            return ['suffix' => '', 'query' => null, 'fragment' => null];
        }

        $query = null;
        $fragment = null;
        if ($suffix[0] === '?') {
            $fragmentOffset = strpos($suffix, '#');
            if ($fragmentOffset === false) {
                $query = substr($suffix, 1);
            } else {
                $query = substr($suffix, 1, $fragmentOffset - 1);
                $fragment = substr($suffix, $fragmentOffset + 1);
            }
        } elseif ($suffix[0] === '#') {
            $fragment = substr($suffix, 1);
        }

        return ['suffix' => $suffix, 'query' => $query, 'fragment' => $fragment];
    }

    public static function isValidContentType(string $contentType): bool
    {
        if ($contentType === '' || preg_match('/[\x00-\x1F\x7F]/', $contentType) === 1) {
            return false;
        }

        $token = '[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+';
        if (preg_match('/\A' . $token . '\/' . $token . '/', $contentType, $matches) !== 1 || $matches[0] === '') {
            return false;
        }

        $rest = substr($contentType, strlen($matches[0]));
        while ($rest !== '') {
            if (preg_match('/\A\s*;\s*' . $token . '\s*=\s*(?:' . $token . '|"(?:[^"\\\\\x00-\x1F\x7F]|\\\\[\x20-\x7E])*")/', $rest, $parameter) !== 1) {
                return false;
            }

            $rest = substr($rest, strlen($parameter[0]));
        }

        return true;
    }

    /**
     * @return array{contentTypeMediaType:?string, contentTypeHasParameters:bool, contentTypeParameterCount:int, contentTypeParameterNames:list<string>, contentTypeParameterMap:array<string, string>, contentTypeParameters:list<array{name:string, normalizedName:string, rawValue:string, value:string, quoted:bool, containsQuotedPair:bool, valueContainsSemicolon:bool}>, contentTypeQuotedParameterCount:int}
     */
    private static function emptyContentTypeMetadata(): array
    {
        return [
            'contentTypeMediaType' => null,
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameterNames' => [],
            'contentTypeParameterMap' => [],
            'contentTypeParameters' => [],
            'contentTypeQuotedParameterCount' => 0,
        ];
    }

    /**
     * @return array{contentTypeMediaType:?string, contentTypeHasParameters:bool, contentTypeParameterCount:int, contentTypeParameterNames:list<string>, contentTypeParameterMap:array<string, string>, contentTypeParameters:list<array{name:string, normalizedName:string, rawValue:string, value:string, quoted:bool, containsQuotedPair:bool, valueContainsSemicolon:bool}>, contentTypeQuotedParameterCount:int}
     */
    private static function contentTypeMetadata(?string $contentType): array
    {
        if ($contentType === null || !self::isValidContentType($contentType)) {
            return self::emptyContentTypeMetadata();
        }

        $token = '[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+';
        if (preg_match('/\A(' . $token . '\/' . $token . ')/', $contentType, $matches) !== 1) {
            return self::emptyContentTypeMetadata();
        }

        $parameters = [];
        $parameterNames = [];
        $parameterMap = [];
        $quotedParameterCount = 0;
        $rest = substr($contentType, strlen($matches[1]));

        while ($rest !== '') {
            if (preg_match('/\A\s*;\s*(' . $token . ')\s*=\s*(' . $token . '|"(?:[^"\\\\\x00-\x1F\x7F]|\\\\[\x20-\x7E])*")/', $rest, $parameter) !== 1) {
                break;
            }

            $name = $parameter[1];
            $normalizedName = strtolower($name);
            $rawValue = $parameter[2];
            $quoted = str_starts_with($rawValue, '"') && str_ends_with($rawValue, '"');
            $containsQuotedPair = false;
            if ($quoted) {
                ++$quotedParameterCount;
                $inner = substr($rawValue, 1, -1);
                $containsQuotedPair = preg_match('/\\\\[\x20-\x7E]/', $inner) === 1;
                $value = preg_replace('/\\\\([\x20-\x7E])/', '$1', $inner) ?? $inner;
            } else {
                $value = $rawValue;
            }

            self::appendPreflightString($parameterNames, $normalizedName);
            $parameterMap[$normalizedName] = $value;
            $parameters[] = [
                'name' => $name,
                'normalizedName' => $normalizedName,
                'rawValue' => $rawValue,
                'value' => $value,
                'quoted' => $quoted,
                'containsQuotedPair' => $containsQuotedPair,
                'valueContainsSemicolon' => str_contains($value, ';'),
            ];

            $rest = substr($rest, strlen($parameter[0]));
        }

        return [
            'contentTypeMediaType' => $matches[1],
            'contentTypeHasParameters' => $parameters !== [],
            'contentTypeParameterCount' => count($parameters),
            'contentTypeParameterNames' => $parameterNames,
            'contentTypeParameterMap' => $parameterMap,
            'contentTypeParameters' => $parameters,
            'contentTypeQuotedParameterCount' => $quotedParameterCount,
        ];
    }

    private static function assertContentType(string $contentType): void
    {
        if (!self::isValidContentType($contentType)) {
            throw new \InvalidArgumentException('OPC content type must be a non-empty MIME type');
        }
    }

    /**
     * @param list<string> $allowedAttributes
     * @param array<string, true> $ignorableNamespaces
     */
    private static function assertRecordShape(\DOMElement $element, array $allowedAttributes, string $label, array $ignorableNamespaces): void
    {
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
                throw new \InvalidArgumentException($label . ' contains unsupported attribute: ' . $attribute->name);
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if (OpcMarkupCompatibility::isIgnorableExtensionElement($child, $ignorableNamespaces)) {
                    continue;
                }

                throw new \InvalidArgumentException($label . ' must be an empty element');
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException($label . ' must be an empty element');
            }
        }
    }

    private static function loadXml(string $xml): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, 'OPC content-types XML');
    }

    /**
     * @return array{valid:bool, parseError:?string, recordCount:int, defaultCount:int, overrideCount:int, invalidCount:int, duplicateDefaultExtensionCount:int, duplicateOverridePartNameCount:int, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, duplicateDefaultExtensionGroups:array<string, list<string>>, duplicateOverridePartNameGroups:array<string, list<string>>, issueCounts:array<string, int>, issues:list<string>, records:list<array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}>}
     */
    private static function contentTypesPreflightSkeleton(): array
    {
        return [
            'valid' => true,
            'parseError' => null,
            'recordCount' => 0,
            'defaultCount' => 0,
            'overrideCount' => 0,
            'invalidCount' => 0,
            'duplicateDefaultExtensionCount' => 0,
            'duplicateOverridePartNameCount' => 0,
            'duplicateDefaultExtensions' => [],
            'duplicateOverridePartNames' => [],
            'duplicateDefaultExtensionGroups' => [],
            'duplicateOverridePartNameGroups' => [],
            'parameterizedContentTypeRecordCount' => 0,
            'contentTypeParameterCount' => 0,
            'contentTypeQuotedParameterCount' => 0,
            'contentTypeParameterNameCounts' => [],
            'issueCounts' => [],
            'issues' => [],
            'records' => [],
        ];
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @return array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}
     */
    private static function preflightRecord(\DOMElement $element, int $recordIndex, array $ignorableNamespaces): array
    {
        $record = [
            'recordIndex' => $recordIndex,
            'kind' => $element->localName,
            'extension' => null,
            'normalizedExtension' => null,
            'partName' => null,
            'normalizedPartName' => null,
            'contentType' => null,
            ...self::emptyContentTypeMetadata(),
            'equivalenceKey' => null,
            'valid' => true,
            'issues' => [],
        ];

        if ($element->localName === 'Default') {
            self::preflightDefaultRecord($element, $record, $ignorableNamespaces);
        } elseif ($element->localName === 'Override') {
            self::preflightOverrideRecord($element, $record, $ignorableNamespaces);
        } else {
            self::appendRecordIssue($record, 'unsupported-content-type-record');
        }

        $record['valid'] = $record['issues'] === [];

        return $record;
    }

    /**
     * @param array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>} $record
     * @param array<string, true> $ignorableNamespaces
     */
    private static function preflightDefaultRecord(\DOMElement $element, array &$record, array $ignorableNamespaces): void
    {
        try {
            self::assertRecordShape($element, ['Extension', 'ContentType'], 'OPC Default content-type record', $ignorableNamespaces);
        } catch (\InvalidArgumentException) {
            self::appendRecordIssue($record, 'content-type-record-shape-error');
        }

        $extension = $element->hasAttribute('Extension') ? $element->getAttribute('Extension') : null;
        $contentType = $element->hasAttribute('ContentType') ? $element->getAttribute('ContentType') : null;
        $record['extension'] = $extension;
        $record['contentType'] = $contentType;

        if ($extension === null || $extension === '') {
            self::appendRecordIssue($record, 'missing-default-extension');
        } else {
            try {
                self::assertXmlDefaultExtension($extension);
                $normalizedExtension = self::normalizeExtension($extension);
                $record['normalizedExtension'] = $normalizedExtension;
                $record['equivalenceKey'] = strtolower($normalizedExtension);
            } catch (\InvalidArgumentException) {
                self::appendRecordIssue($record, 'invalid-default-extension');
            }
        }

        if ($contentType === null || $contentType === '') {
            self::appendRecordIssue($record, 'missing-content-type');
        } elseif (!self::isValidContentType($contentType)) {
            self::appendRecordIssue($record, 'invalid-content-type');
        } else {
            $record = array_replace($record, self::contentTypeMetadata($contentType));
        }
    }

    /**
     * @param array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>} $record
     * @param array<string, true> $ignorableNamespaces
     */
    private static function preflightOverrideRecord(\DOMElement $element, array &$record, array $ignorableNamespaces): void
    {
        try {
            self::assertRecordShape($element, ['PartName', 'ContentType'], 'OPC Override content-type record', $ignorableNamespaces);
        } catch (\InvalidArgumentException) {
            self::appendRecordIssue($record, 'content-type-record-shape-error');
        }

        $partName = $element->hasAttribute('PartName') ? $element->getAttribute('PartName') : null;
        $contentType = $element->hasAttribute('ContentType') ? $element->getAttribute('ContentType') : null;
        $record['partName'] = $partName;
        $record['contentType'] = $contentType;

        if ($partName === null || $partName === '') {
            self::appendRecordIssue($record, 'missing-override-part-name');
        } else {
            try {
                self::assertXmlOverridePartName($partName);
                $normalizedPartName = OpcPackagePath::canonicalPartNameFromUri($partName);
                $record['normalizedPartName'] = $normalizedPartName;
                $record['equivalenceKey'] = self::partNameEquivalenceKey($normalizedPartName);
            } catch (\InvalidArgumentException) {
                self::appendRecordIssue($record, 'invalid-override-part-name');
            }
        }

        if ($contentType === null || $contentType === '') {
            self::appendRecordIssue($record, 'missing-content-type');
        } elseif (!self::isValidContentType($contentType)) {
            self::appendRecordIssue($record, 'invalid-content-type');
        } else {
            $record = array_replace($record, self::contentTypeMetadata($contentType));
        }
    }

    /**
     * @param array{records:list<array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}>, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, duplicateDefaultExtensionGroups:array<string, list<string>>, duplicateOverridePartNameGroups:array<string, list<string>>} $summary
     */
    private static function markDuplicatePreflightRecords(array &$summary): void
    {
        $defaultIndexesByKey = [];
        $overrideIndexesByKey = [];
        foreach ($summary['records'] as $index => $record) {
            if ($record['equivalenceKey'] === null) {
                continue;
            }

            if ($record['kind'] === 'Default') {
                $defaultIndexesByKey[$record['equivalenceKey']][] = $index;
            } elseif ($record['kind'] === 'Override') {
                $overrideIndexesByKey[$record['equivalenceKey']][] = $index;
            }
        }

        foreach ($defaultIndexesByKey as $key => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $extensions = [];
            foreach ($indexes as $index) {
                $extension = $summary['records'][$index]['normalizedExtension'];
                if ($extension !== null) {
                    self::appendPreflightString($extensions, $extension);
                }
                self::appendRecordIssue($summary['records'][$index], 'duplicate-default-extension');
            }
            sort($extensions, SORT_STRING);
            self::appendPreflightString($summary['duplicateDefaultExtensions'], $key);
            $summary['duplicateDefaultExtensionGroups'][$key] = $extensions;
        }

        foreach ($overrideIndexesByKey as $key => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $partNames = [];
            foreach ($indexes as $index) {
                $partName = $summary['records'][$index]['normalizedPartName'];
                if ($partName !== null) {
                    self::appendPreflightString($partNames, $partName);
                }
                self::appendRecordIssue($summary['records'][$index], 'duplicate-override-part-name');
            }
            sort($partNames, SORT_STRING);
            self::appendPreflightString($summary['duplicateOverridePartNames'], $key);
            $summary['duplicateOverridePartNameGroups'][$key] = $partNames;
        }

        sort($summary['duplicateDefaultExtensions'], SORT_STRING);
        sort($summary['duplicateOverridePartNames'], SORT_STRING);
        ksort($summary['duplicateDefaultExtensionGroups'], SORT_STRING);
        ksort($summary['duplicateOverridePartNameGroups'], SORT_STRING);
    }

    /**
     * @param array{valid:bool, recordCount:int, defaultCount:int, overrideCount:int, invalidCount:int, duplicateDefaultExtensionCount:int, duplicateOverridePartNameCount:int, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, issueCounts:array<string, int>, issues:list<string>, records:list<array{recordIndex:int, kind:string, valid:bool, issues:list<string>}>} $summary
     */
    private static function refreshPreflightSummary(array &$summary): void
    {
        $summary['recordCount'] = count($summary['records']);
        $summary['defaultCount'] = 0;
        $summary['overrideCount'] = 0;
        $summary['invalidCount'] = 0;
        $summary['parameterizedContentTypeRecordCount'] = 0;
        $summary['contentTypeParameterCount'] = 0;
        $summary['contentTypeQuotedParameterCount'] = 0;
        $summary['contentTypeParameterNameCounts'] = [];
        $summary['issueCounts'] = [];
        $summary['issues'] = [];

        foreach ($summary['records'] as &$record) {
            $record['issues'] = array_values(array_unique($record['issues']));
            $record['valid'] = $record['issues'] === [];

            if ($record['kind'] === 'Default') {
                $summary['defaultCount']++;
            } elseif ($record['kind'] === 'Override') {
                $summary['overrideCount']++;
            }

            if (!$record['valid']) {
                $summary['invalidCount']++;
            }

            if (($record['contentTypeHasParameters'] ?? false) === true) {
                $summary['parameterizedContentTypeRecordCount']++;
                $summary['contentTypeParameterCount'] += (int) ($record['contentTypeParameterCount'] ?? 0);
                $summary['contentTypeQuotedParameterCount'] += (int) ($record['contentTypeQuotedParameterCount'] ?? 0);
                foreach (is_array($record['contentTypeParameterNames'] ?? null) ? $record['contentTypeParameterNames'] : [] as $name) {
                    if (is_string($name) && $name !== '') {
                        $summary['contentTypeParameterNameCounts'][$name] = ($summary['contentTypeParameterNameCounts'][$name] ?? 0) + 1;
                    }
                }
            }

            foreach ($record['issues'] as $issue) {
                self::appendPreflightIssue($summary, $issue);
            }
        }
        unset($record);

        ksort($summary['contentTypeParameterNameCounts'], SORT_STRING);
        ksort($summary['issueCounts'], SORT_STRING);
        sort($summary['issues'], SORT_STRING);
        $summary['duplicateDefaultExtensionCount'] = count($summary['duplicateDefaultExtensions']);
        $summary['duplicateOverridePartNameCount'] = count($summary['duplicateOverridePartNames']);
        $summary['valid'] = $summary['invalidCount'] === 0;
    }

    /**
     * @param array{issueCounts:array<string, int>, issues:list<string>} $summary
     */
    private static function appendPreflightIssue(array &$summary, string $issue): void
    {
        $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
        self::appendPreflightString($summary['issues'], $issue);
    }

    /**
     * @param array{issues:list<string>} $record
     */
    private static function appendRecordIssue(array &$record, string $issue): void
    {
        self::appendPreflightString($record['issues'], $issue);
    }

    /**
     * @param list<string> $values
     */
    private static function appendPreflightString(array &$values, string $value): void
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }
}
