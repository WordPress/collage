<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Html5DomFragment
{
    /** @var array<string, true> */
    private const HTML5_BOOLEAN_ATTRIBUTES = [
        'allowfullscreen' => true,
        'async' => true,
        'autofocus' => true,
        'autoplay' => true,
        'checked' => true,
        'controls' => true,
        'credentialless' => true,
        'default' => true,
        'defer' => true,
        'disabled' => true,
        'formnovalidate' => true,
        'hidden' => true,
        'inert' => true,
        'ismap' => true,
        'itemscope' => true,
        'loop' => true,
        'multiple' => true,
        'muted' => true,
        'nomodule' => true,
        'novalidate' => true,
        'open' => true,
        'playsinline' => true,
        'readonly' => true,
        'required' => true,
        'reversed' => true,
        'selected' => true,
        'typemustmatch' => true,
    ];

    /** @var array<string, string> */
    private const HTML_BUTTON_COMMAND_STATES = [
        'toggle-popover' => 'toggle-popover',
        'show-popover' => 'show-popover',
        'hide-popover' => 'hide-popover',
        'close' => 'close',
        'request-close' => 'request-close',
        'show-modal' => 'show-modal',
    ];

    /** @var array<string, true> */
    private const HTML_BUTTON_POPOVER_COMMANDS = [
        'toggle-popover' => true,
        'show-popover' => true,
        'hide-popover' => true,
    ];

    /** @var array<string, true> */
    private const HTML_BUTTON_DIALOG_COMMANDS = [
        'close' => true,
        'request-close' => true,
        'show-modal' => true,
    ];

    /** @var array<string, array<string, true>> */
    private const HTML5_TABLE_ALLOWED_CHILDREN = [
        'table' => [
            'caption' => true,
            'colgroup' => true,
            'script' => true,
            'tbody' => true,
            'template' => true,
            'tfoot' => true,
            'thead' => true,
            'tr' => true,
        ],
        'colgroup' => [
            'col' => true,
            'script' => true,
            'template' => true,
        ],
        'thead' => [
            'script' => true,
            'template' => true,
            'tr' => true,
        ],
        'tbody' => [
            'script' => true,
            'template' => true,
            'tr' => true,
        ],
        'tfoot' => [
            'script' => true,
            'template' => true,
            'tr' => true,
        ],
        'tr' => [
            'script' => true,
            'td' => true,
            'template' => true,
            'th' => true,
        ],
    ];

    /** @var array<string, true> */
    private const HTML_FRAGMENT_INACTIVE_BASE_ANCESTORS = [
        'applet' => true,
        'button' => true,
        'canvas' => true,
        'datalist' => true,
        'form' => true,
        'iframe' => true,
        'math' => true,
        'map' => true,
        'noembed' => true,
        'noframes' => true,
        'noscript' => true,
        'object' => true,
        'optgroup' => true,
        'option' => true,
        'plaintext' => true,
        'select' => true,
        'svg' => true,
        'template' => true,
        'textarea' => true,
        'xmp' => true,
    ];

    /** @var list<array<string, mixed>> */
    private array $nodes;

    /** @var list<array<string, mixed>> */
    private array $diagnostics;

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $diagnostics
     */
    private function __construct(
        private readonly string $mode,
        array $nodes,
        array $diagnostics,
        private readonly int $sourceBytes,
        private readonly string $sourceSha256,
        private readonly ?string $baseUrl = null,
    ) {
        $this->nodes = $nodes;
        $this->diagnostics = $diagnostics;
    }

    public static function fromHtml(string $html, ?string $baseUrl = null): self
    {
        self::assertSafeHtmlSource($html, 'HTML fragment');

        $diagnostics = XmlHtmlDom::htmlRawTextBoundaryDiagnostics($html);
        $wrapper = self::loadHtmlFragmentRoot($html, $diagnostics);

        $resolvedBaseUrl = self::resolveFragmentBaseUrl($wrapper, $baseUrl, $diagnostics);
        $documentMetadataNodes = self::htmlDocumentElementMetadataNodes($html, $diagnostics);
        $baseTargetMetadataNodes = self::htmlBaseTargetMetadataNodes($wrapper, $diagnostics);
        $nodes = [
            ...$documentMetadataNodes,
            ...$baseTargetMetadataNodes,
            ...self::normalizeChildren($wrapper, 'html', $diagnostics, baseUrl: $resolvedBaseUrl),
        ];
        self::markHtmlMicrodataItemRefPropertySummaryMetadata($nodes, $diagnostics);

        return new self(
            'html',
            $nodes,
            $diagnostics,
            strlen($html),
            hash('sha256', $html),
            $resolvedBaseUrl
        );
    }

    public static function fromXml(string $xml): self
    {
        self::assertSafeXmlSource($xml, 'XML fragment');

        $diagnostics = [];
        $dom = self::loadXmlDocument($xml, $diagnostics);
        $wrapper = $dom->documentElement;
        if (!$wrapper instanceof \DOMElement || $wrapper->tagName !== 'pandoc-fragment-root') {
            throw new \InvalidArgumentException('Unable to parse XML fragment wrapper');
        }

        return new self(
            'xml',
            self::normalizeChildren($wrapper, 'xml', $diagnostics),
            $diagnostics,
            strlen($xml),
            hash('sha256', $xml)
        );
    }

    public function serialize(): string
    {
        return $this->serializeNodes($this->nodes);
    }

    public function toRawHtmlAst(array $attrs = []): AstNode
    {
        $html = $this->serialize();
        $rawAttrs = array_merge($attrs, [
            'format' => $this->mode === 'xml' ? 'xml' : 'html',
            'html' => $html,
            'diagnostics' => $this->diagnostics,
            'fragmentProvenance' => $this->provenanceForSerialized($html),
        ]);
        if ($this->baseUrl !== null) {
            $rawAttrs['baseUrl'] = $this->baseUrl;
        }

        return new AstNode('raw_html', $rawAttrs);
    }

    public function textContent(): string
    {
        return $this->textFromNodes($this->nodes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @return array{
     *     sourceFormat:string,
     *     sourceBytes:int,
     *     sourceSha256:string,
     *     serializedBytes:int,
     *     serializedSha256:string,
     *     serializedChanged:bool,
     *     diagnosticCount:int,
     *     diagnosticCodes:list<string>,
     *     baseUrl:string|null
     * }
     */
    public function provenance(): array
    {
        return $this->provenanceForSerialized($this->serialize());
    }

    /**
     * @return list<string>
     */
    public function diagnosticCodes(): array
    {
        return array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $this->diagnostics
        );
    }

    /**
     * @return array{
     *     mode:string,
     *     topLevelNodes:int,
     *     elements:int,
     *     textNodes:int,
     *     comments:int,
     *     diagnostics:int,
     *     baseUrl:string|null,
     *     elementNames:list<string>,
     *     blockedTags:list<string>,
     *     filteredAttributes:list<string>
     * }
     */
    public function summary(): array
    {
        $counts = [
            'elements' => 0,
            'textNodes' => 0,
            'comments' => 0,
            'elementNames' => [],
        ];
        $this->summarizeNodes($this->nodes, $counts);

        $blockedTags = [];
        $filteredAttributes = [];
        foreach ($this->diagnostics as $diagnostic) {
            if (($diagnostic['code'] ?? '') === 'blocked-tag') {
                $blockedTags[] = (string) ($diagnostic['tag'] ?? '');
            }
            if (in_array(($diagnostic['code'] ?? ''), ['unsafe-attribute', 'unsafe-url', 'invalid-srcset-descriptor', 'hidden-content-review', 'inert-content-review', 'dialog-review', 'popover-review', 'editing-state-review', 'translation-state-review', 'focus-navigation-review', 'text-input-hint-review', 'writing-assistance-review', 'revision-metadata-review', 'quote-cite-review', 'language-direction-review', 'aria-metadata-review', 'custom-element-review', 'shadowroot-template-review', 'slot-review', 'figure-metadata-review', 'fieldset-review', 'label-metadata-review', 'form-metadata-review', 'button-metadata-review', 'datalist-review', 'select-metadata-review', 'value-metadata-review', 'output-metadata-review', 'math-annotation-review', 'referrer-policy-review', 'image-resource-policy-review', 'media-resource-policy-review', 'portal-source-review', 'embedded-source-review', 'object-param-review', 'iframe-srcdoc-review', 'link-browsing-review'], true)) {
                $filteredAttributes[] = (string) ($diagnostic['attribute'] ?? '');
            }
        }

        $elementNames = array_keys($counts['elementNames']);
        sort($elementNames);
        $blockedTags = array_values(array_unique(array_filter($blockedTags, static fn (string $tag): bool => $tag !== '')));
        $filteredAttributes = array_values(array_unique(array_filter($filteredAttributes, static fn (string $name): bool => $name !== '')));
        sort($blockedTags);
        sort($filteredAttributes);

        return [
            'mode' => $this->mode,
            'topLevelNodes' => count($this->nodes),
            'elements' => $counts['elements'],
            'textNodes' => $counts['textNodes'],
            'comments' => $counts['comments'],
            'diagnostics' => count($this->diagnostics),
            'baseUrl' => $this->baseUrl,
            'elementNames' => $elementNames,
            'blockedTags' => $blockedTags,
            'filteredAttributes' => $filteredAttributes,
        ];
    }

    /**
     * @return array{
     *     sourceFormat:string,
     *     sourceBytes:int,
     *     sourceSha256:string,
     *     serializedBytes:int,
     *     serializedSha256:string,
     *     serializedChanged:bool,
     *     diagnosticCount:int,
     *     diagnosticCodes:list<string>,
     *     baseUrl:string|null
     * }
     */
    private function provenanceForSerialized(string $serialized): array
    {
        $serializedBytes = strlen($serialized);
        $serializedSha256 = hash('sha256', $serialized);

        return [
            'sourceFormat' => $this->mode === 'xml' ? 'xml' : 'html',
            'sourceBytes' => $this->sourceBytes,
            'sourceSha256' => $this->sourceSha256,
            'serializedBytes' => $serializedBytes,
            'serializedSha256' => $serializedSha256,
            'serializedChanged' => $this->sourceBytes !== $serializedBytes || $this->sourceSha256 !== $serializedSha256,
            'diagnosticCount' => count($this->diagnostics),
            'diagnosticCodes' => $this->diagnosticCodes(),
            'baseUrl' => $this->baseUrl,
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function loadHtmlFragmentRoot(string $html, array &$diagnostics): \DOMElement
    {
        try {
            return Html5Dom::parseHtmlFragment(
                $html,
                protectRawTextContentForParse: false,
                protectTemplateContentForParse: false,
                protectIframeContentForParse: false,
                protectNoscriptContentForParse: false
            );
        } catch (\InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Unable to parse HTML fragment through ' . Html5Dom::htmlFragmentTreeConstructionBackend($html), 0, $exception);
        }
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function loadXmlDocument(string $xml, array &$diagnostics): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<pandoc-fragment-root>' . $xml . '</pandoc-fragment-root>';
        $loaded = $dom->loadXML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($errors as $error) {
            $message = trim($error->message);
            if ($message === '') {
                continue;
            }
            $diagnostics[] = [
                'code' => 'xml-parse-error',
                'level' => $error->level,
                'message' => $message,
                'line' => $error->line,
                'column' => $error->column,
            ];
        }

        if (!$loaded) {
            throw new \InvalidArgumentException('Unable to parse XML fragment');
        }

        return $dom;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function normalizeChildren(
        \DOMNode $parent,
        string $mode,
        array &$diagnostics,
        ?string $foreignContext = null,
        ?string $baseUrl = null
    ): array {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            $normalized = self::normalizeNode($child, $mode, $diagnostics, $foreignContext, $baseUrl);
            if ($normalized === null) {
                continue;
            }
            array_push($nodes, ...$normalized);
        }

        if ($mode === 'html' && $foreignContext === null && !self::isDirectHtmlTableContext($parent)) {
            $nodes = self::wrapOrphanHtmlTableStructure($nodes, $diagnostics);
        }

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeNode(
        \DOMNode $node,
        string $mode,
        array &$diagnostics,
        ?string $foreignContext = null,
        ?string $baseUrl = null
    ): ?array {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return [self::nodeWithSourceLine([
                'type' => 'text',
                'text' => $node->nodeValue ?? '',
            ], $node)];
        }

        if ($node instanceof \DOMComment) {
            return [self::nodeWithSourceLine([
                'type' => 'comment',
                'text' => $node->nodeValue ?? '',
            ], $node)];
        }

        if (!$node instanceof \DOMElement) {
            return null;
        }

        $rawName = self::rawElementName($node, $mode);
        $elementForeignContext = self::elementForeignContext($node, $rawName, $mode, $foreignContext);
        $name = self::normalizedElementName($rawName, $elementForeignContext);
        if (!self::isSafeElementName($name)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

            return null;
        }

        if ($mode === 'html' && self::isUnwrappedElement($name)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

            if ($name === 'iframe' && $node->hasAttribute('srcdoc')) {
                $srcdocNodes = self::normalizeHtmlSrcdocAttribute(
                    $node->getAttribute('srcdoc'),
                    $diagnostics,
                    $node,
                    $baseUrl
                );
                if ($srcdocNodes !== []) {
                    return $srcdocNodes;
                }
            }

            $children = self::normalizeChildren(
                $node,
                $mode,
                $diagnostics,
                self::childForeignContext($node, $rawName, $elementForeignContext),
                $baseUrl
            );

            if ($name === 'iframe') {
                if ($children !== []) {
                    return $children;
                }

                $iframeSource = self::normalizeHtmlIframeSourceElement($node, $diagnostics, $baseUrl);

                return $iframeSource === null ? [] : [$iframeSource];
            }

            if ($name === 'button') {
                $buttonMetadata = self::htmlButtonReviewMetadataNode($node, $children, $diagnostics, $baseUrl);

                return $buttonMetadata === null ? $children : [$buttonMetadata];
            }

            if ($name === 'object') {
                $objectSource = self::normalizeHtmlEmbeddedSourceElement(
                    $node,
                    'object',
                    'data',
                    'data-pandoc-object-data',
                    'Embedded object source',
                    $diagnostics,
                    $baseUrl
                );

                if ($objectSource === null) {
                    return $children;
                }

                $objectParamMetadata = self::htmlObjectParamReviewMetadataNodes($node, $diagnostics, $baseUrl);

                return [$objectSource, ...$objectParamMetadata, ...$children];
            }

            if ($name === 'form') {
                $formMetadata = self::htmlFormReviewMetadataNode($node, $diagnostics, $baseUrl);
                if ($formMetadata !== null) {
                    return [$formMetadata, ...$children];
                }
            }

            if ($name === 'template') {
                return self::withHtmlTemplateShadowRootMetadata($node, $children, $diagnostics);
            }

            if ($name === 'datalist') {
                $datalistMetadata = self::htmlDatalistReviewMetadataNode($node, $diagnostics);

                return $datalistMetadata === null ? $children : [$datalistMetadata];
            }

            if ($name === 'select') {
                $selectMetadata = self::htmlSelectReviewMetadataNode($node, $diagnostics);

                return $selectMetadata === null ? $children : [$selectMetadata, ...$children];
            }

            return self::withVisibleFormChoiceLabel($node, $name, $children);
        }

        if ($mode === 'html' && $name === 'input') {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

            $label = self::visibleInputLabel($node);

            return $label === null ? null : [['type' => 'text', 'text' => $label]];
        }

        if ($mode === 'html' && $name === 'area') {
            return self::normalizeHtmlAreaElement($node, $diagnostics, $baseUrl);
        }

        if ($mode === 'html' && $name === 'link') {
            return self::normalizeHtmlLinkElement($node, $diagnostics, $baseUrl);
        }

        if ($mode === 'html' && $name === 'meta') {
            return self::normalizeHtmlMetaElement($node, $diagnostics, $baseUrl);
        }

        if ($mode === 'html' && $elementForeignContext === null && $name === 'title') {
            return self::normalizeHtmlTitleElement($node, $diagnostics);
        }

        if ($mode === 'html' && $elementForeignContext === null && $name === 'portal') {
            return self::normalizeHtmlPortalElement($node, $diagnostics, $elementForeignContext, $baseUrl);
        }

        if ($mode === 'html' && $elementForeignContext === null && $name === 'embed') {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

            $children = self::normalizeChildren(
                $node,
                $mode,
                $diagnostics,
                self::childForeignContext($node, $rawName, $elementForeignContext),
                $baseUrl
            );
            $embedSource = self::normalizeHtmlEmbeddedSourceElement(
                $node,
                'embed',
                'src',
                'data-pandoc-embed-src',
                'Embedded media source',
                $diagnostics,
                $baseUrl
            );

            return $embedSource === null ? ($children === [] ? null : $children) : [$embedSource, ...$children];
        }

        if ($mode === 'html' && self::isBlockedElement($name)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

            return null;
        }

        $attrs = self::normalizeAttributes($node, $name, $mode, $diagnostics, $elementForeignContext, $baseUrl);
        $children = self::normalizeChildren(
            $node,
            $mode,
            $diagnostics,
            self::childForeignContext($node, $rawName, $elementForeignContext),
            $baseUrl
        );
        if ($mode === 'html' && $name === 'img') {
            $imageAltFallback = self::htmlImageAltFallbackNode($node, $attrs, $diagnostics);
            if ($imageAltFallback !== null) {
                return [$imageAltFallback];
            }
        }
        if ($mode === 'html' && $name === 'details') {
            self::markClosedHtmlDetailsReviewMetadata($node, $attrs, $children, $diagnostics);
        }
        if ($mode === 'html' && $name === 'dialog') {
            self::markHtmlDialogReviewMetadata($node, $attrs, $diagnostics);
            $name = 'div';
        }
        if ($mode === 'html' && $name === 'ruby') {
            self::markHtmlRubyReviewMetadata($node, $attrs, $children, $diagnostics);
        }
        if ($mode === 'html' && $name === 'math') {
            self::markHtmlMathAnnotationReviewMetadata($attrs, $children, $diagnostics);
        }
        if ($mode === 'html' && $name === 'figure') {
            self::markHtmlFigureReviewMetadata($node, $attrs, $children, $diagnostics);
        }
        if ($mode === 'html' && $name === 'fieldset') {
            self::markHtmlFieldsetReviewMetadata($node, $attrs, $children, $diagnostics);
        }
        if ($mode === 'html' && $name === 'label') {
            self::markHtmlLabelReviewMetadata($node, $attrs, $children, $diagnostics);
        }
        if ($mode === 'html' && $elementForeignContext === null && self::isHtmlCustomElementName($name)) {
            $name = self::markHtmlCustomElementReviewMetadata($node, $name, $attrs, $children, $diagnostics);
        }
        if ($mode === 'html' && $elementForeignContext === null && $name === 'slot') {
            self::markHtmlSlotFallbackReviewMetadata($node, $attrs, $diagnostics);
            $name = 'span';
        }
        if ($mode === 'html') {
            self::markHtmlHiddenInertReviewMetadata($node, $name, $attrs, $diagnostics);
        }

        if ($mode === 'html' && $elementForeignContext === null && $name === 'source' && !self::hasHtmlMediaSourceContext($node)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
                'reason' => 'source-element-outside-media-context',
            ], $node);

            return $children === [] ? null : $children;
        }

        if ($mode === 'html' && $elementForeignContext === null && self::isEmptyHtmlMediaSourceElement($node, $name, $attrs)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'empty-source',
                'tag' => $name,
                'reason' => 'missing-src-or-srcset',
            ], $node);

            return $children === [] ? null : $children;
        }
        if ($mode === 'html' && $elementForeignContext === null) {
            self::markHtmlMicrodataValueMetadata($node, $name, $attrs, $children, $diagnostics);
            self::markHtmlMicrodataItemRefMetadata($node, $name, $attrs, $diagnostics);
            self::markHtmlMicrodataItemSummaryMetadata($node, $name, $attrs, $children, $diagnostics);
        }

        $element = [
            'type' => 'element',
            'name' => $name,
            'attrs' => $attrs,
            'children' => $mode === 'html' && self::isHtmlVoidElement($name) ? [] : $children,
        ];
        $element = self::nodeWithSourceLine($element, $node);

        if ($mode === 'html' && self::isHtmlVoidElement($name) && $children !== []) {
            return [$element, ...$children];
        }

        if ($mode === 'html' && $name === 'table') {
            return self::normalizeHtmlTableElement($element, $diagnostics);
        }

        return [$element];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function withHtmlTemplateShadowRootMetadata(
        \DOMElement $element,
        array $children,
        array &$diagnostics
    ): array {
        if (!$element->hasAttribute('shadowrootmode')) {
            return $children;
        }

        $mode = strtolower(self::cleanHtmlMetadataAttribute($element->getAttribute('shadowrootmode')));
        if (!in_array($mode, ['open', 'closed'], true)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'template',
                'attribute' => 'shadowrootmode',
                'value' => $mode,
                'reason' => 'invalid-declarative-shadow-root-mode',
            ], $element);

            return $children;
        }

        $attrs = [
            'data-pandoc-shadowroot-mode' => $mode,
        ];

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'shadowroot-template-review',
            'tag' => 'template',
            'attribute' => 'shadowrootmode',
            'mode' => $mode,
            'reason' => 'declarative-shadow-root-template-unwrapped',
        ], $element);

        foreach ([
            'shadowrootdelegatesfocus' => 'data-pandoc-shadowroot-delegatesfocus',
            'shadowrootclonable' => 'data-pandoc-shadowroot-clonable',
            'shadowrootserializable' => 'data-pandoc-shadowroot-serializable',
        ] as $sourceAttribute => $metadataAttribute) {
            if (!$element->hasAttribute($sourceAttribute)) {
                continue;
            }

            $attrs[$metadataAttribute] = 'true';
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'shadowroot-template-review',
                'tag' => 'template',
                'attribute' => $sourceAttribute,
                'mode' => $mode,
                'reason' => 'declarative-shadow-root-template-flag-preserved',
            ], $element);
        }

        self::addHtmlTemplateShadowRootAccessibilityMetadata($element, $attrs, $diagnostics);

        $metadataNode = self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => 'Shadow root: ' . $mode,
            ]],
        ], $element);

        return [$metadataNode, ...$children];
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlTemplateShadowRootAccessibilityMetadata(
        \DOMElement $element,
        array &$attrs,
        array &$diagnostics
    ): void {
        foreach ([
            'aria-label' => 'data-pandoc-shadowroot-aria-label',
            'aria-description' => 'data-pandoc-shadowroot-aria-description',
        ] as $sourceAttribute => $metadataAttribute) {
            if (!$element->hasAttribute($sourceAttribute)) {
                continue;
            }

            $value = self::normalizeHtmlAriaTextValue($element->getAttribute($sourceAttribute));
            if ($value === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'template',
                    'attribute' => $sourceAttribute,
                    'reason' => 'invalid-declarative-shadow-root-accessibility-metadata',
                ], $element);
                continue;
            }

            $attrs[$metadataAttribute] = $value;
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'shadowroot-template-review',
                'tag' => 'template',
                'attribute' => $sourceAttribute,
                'metadataAttribute' => $metadataAttribute,
                'reason' => 'declarative-shadow-root-accessibility-preserved',
            ], $element);
        }

        foreach ([
            'aria-describedby' => 'data-pandoc-shadowroot-aria-describedby',
            'aria-labelledby' => 'data-pandoc-shadowroot-aria-labelledby',
        ] as $sourceAttribute => $metadataAttribute) {
            if (!$element->hasAttribute($sourceAttribute)) {
                continue;
            }

            $value = self::normalizeHtmlShadowRootAriaIdrefValue(
                $element,
                $sourceAttribute,
                $diagnostics
            );
            if ($value === null) {
                continue;
            }

            $attrs[$metadataAttribute] = $value;
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'shadowroot-template-review',
                'tag' => 'template',
                'attribute' => $sourceAttribute,
                'metadataAttribute' => $metadataAttribute,
                'reason' => 'declarative-shadow-root-accessibility-preserved',
            ], $element);
        }
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlShadowRootAriaIdrefValue(
        \DOMElement $element,
        string $attribute,
        array &$diagnostics
    ): ?string {
        $tokens = self::splitHtmlSemanticTokens($element->getAttribute($attribute));
        if ($tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (!self::isSafeHtmlAriaIdToken($token)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'template',
                    'attribute' => $attribute,
                    'token' => $token,
                    'reason' => 'invalid-declarative-shadow-root-accessibility-metadata',
                ], $element);
                continue;
            }

            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        return $normalized === [] ? null : implode(' ', $normalized);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlSlotFallbackReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array &$diagnostics
    ): void {
        unset($attrs['name']);
        $attrs['data-pandoc-slot-fallback'] = 'true';

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'slot-review',
            'tag' => 'slot',
            'replacement' => 'span',
            'reason' => 'slot-fallback-preserved',
        ], $element);

        if (!$element->hasAttribute('name')) {
            return;
        }

        $slotName = self::normalizeHtmlSlotName($element->getAttribute('name'));
        if ($slotName === null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'slot',
                'attribute' => 'name',
                'reason' => 'invalid-slot-name-metadata',
            ], $element);

            return;
        }

        $attrs['data-pandoc-slot-name'] = $slotName;
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'slot-review',
            'tag' => 'slot',
            'attribute' => 'name',
            'name' => $slotName,
            'replacement' => 'span',
            'reason' => 'slot-name-preserved-as-metadata',
        ], $element);
    }

    private static function normalizeHtmlSlotName(string $name): ?string
    {
        $name = self::cleanHtmlMetadataAttribute($name);
        if ($name === '' || strlen($name) > 128) {
            return null;
        }
        if (preg_match('/[<>"\'`{}]/u', $name) === 1) {
            return null;
        }

        return $name;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlSlotAssignmentAttribute(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?string {
        $slotName = self::cleanHtmlMetadataAttribute($value);
        if (!self::isSafeHtmlSlotAssignmentToken($slotName)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => 'slot',
                'reason' => 'invalid-slot-assignment-metadata',
            ], $element);

            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'slot-review',
            'tag' => $tagName,
            'attribute' => 'slot',
            'metadataAttribute' => 'data-pandoc-slot-assignment',
            'slot' => $slotName,
            'reason' => 'slot-assignment-preserved-as-review-metadata',
        ], $element);

        return $slotName;
    }

    private static function isSafeHtmlSlotAssignmentToken(string $token): bool
    {
        return $token !== ''
            && strlen($token) <= 128
            && preg_match('/[\s<>"\'`=,:\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $token) !== 1;
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlCustomElementReviewMetadata(
        \DOMElement $element,
        string $sourceName,
        array &$attrs,
        array $children,
        array &$diagnostics
    ): string {
        $replacement = self::htmlCustomElementReplacementName($children);
        $attrs['data-pandoc-custom-element'] = $sourceName;

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'custom-element-review',
            'tag' => $sourceName,
            'sourceTag' => $sourceName,
            'replacement' => $replacement,
            'reason' => 'custom-element-preserved-as-inert-metadata',
        ], $element);

        return $replacement;
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    private static function htmlCustomElementReplacementName(array $children): string
    {
        foreach ($children as $child) {
            if (($child['type'] ?? '') !== 'element') {
                continue;
            }
            if (self::isHtmlBlockElementName((string) ($child['name'] ?? ''))) {
                return 'div';
            }
        }

        return 'span';
    }

    private static function isHtmlBlockElementName(string $name): bool
    {
        return in_array(strtolower($name), [
            'address',
            'article',
            'aside',
            'blockquote',
            'div',
            'dl',
            'fieldset',
            'figcaption',
            'figure',
            'footer',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'header',
            'hr',
            'main',
            'nav',
            'ol',
            'p',
            'pre',
            'section',
            'table',
            'ul',
        ], true);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function htmlImageAltFallbackNode(\DOMElement $element, array $attrs, array &$diagnostics): ?array
    {
        if (array_key_exists('src', $attrs) || array_key_exists('srcset', $attrs)) {
            return null;
        }
        if (!$element->hasAttribute('src') && !$element->hasAttribute('srcset')) {
            return null;
        }
        if (!$element->hasAttribute('alt')) {
            return null;
        }

        $alt = str_replace("\0", '', $element->getAttribute('alt'));
        if (trim($alt) === '') {
            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'image-alt-fallback',
            'tag' => 'img',
            'attribute' => 'alt',
            'reason' => 'stripped-image-resource',
        ], $element);

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => [
                'data-pandoc-image-alt-fallback' => 'true',
            ],
            'children' => [[
                'type' => 'text',
                'text' => $alt,
            ]],
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlHiddenInertReviewMetadata(
        \DOMElement $element,
        string $tagName,
        array &$attrs,
        array &$diagnostics
    ): void {
        if ($element->hasAttribute('hidden')) {
            unset($attrs['hidden']);
            $attrs['data-pandoc-hidden-state'] = self::normalizeHtmlHiddenState($element->getAttribute('hidden'));
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'hidden-content-review',
                'tag' => $tagName,
                'attribute' => 'hidden',
                'reason' => 'hidden-content-preserved',
            ], $element);
        }

        if ($element->hasAttribute('inert')) {
            unset($attrs['inert']);
            $attrs['data-pandoc-inert-state'] = 'true';
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'inert-content-review',
                'tag' => $tagName,
                'attribute' => 'inert',
                'reason' => 'inert-content-preserved',
            ], $element);
        }
    }

    private static function normalizeHtmlHiddenState(string $value): string
    {
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));

        return $state === 'until-found' ? 'until-found' : 'hidden';
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlRubyReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array $children,
        array &$diagnostics
    ): void {
        $baseText = self::rubyBaseText($children);
        $annotationText = self::rubyAnnotationText($children);
        $fallbackText = self::rubyFallbackText($children);
        if ($baseText === '' && $annotationText === '' && $fallbackText === '') {
            return;
        }

        if ($baseText !== '') {
            $attrs['data-pandoc-ruby-base'] = $baseText;
        }
        if ($annotationText !== '') {
            $attrs['data-pandoc-ruby-annotation'] = $annotationText;
        }
        if ($fallbackText !== '') {
            $attrs['data-pandoc-ruby-fallback'] = $fallbackText;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'ruby-annotation-review',
            'tag' => 'ruby',
            'reason' => 'ruby-annotation-preserved-as-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    private static function rubyBaseText(array $children): string
    {
        $text = '';
        foreach ($children as $child) {
            if (($child['type'] ?? '') === 'element' && in_array(strtolower((string) ($child['name'] ?? '')), ['rp', 'rt', 'rtc'], true)) {
                continue;
            }

            $text .= self::rubyNodeText($child);
        }

        return self::normalizeRubyMetadataText($text);
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    private static function rubyAnnotationText(array $children): string
    {
        $annotations = [];
        foreach ($children as $child) {
            foreach (self::rubyAnnotationTextsFromNode($child) as $annotation) {
                if ($annotation === '' || in_array($annotation, $annotations, true)) {
                    continue;
                }

                $annotations[] = $annotation;
            }
        }

        return implode(' | ', $annotations);
    }

    /**
     * @param array<string, mixed> $node
     * @return list<string>
     */
    private static function rubyAnnotationTextsFromNode(array $node): array
    {
        if (($node['type'] ?? '') !== 'element') {
            return [];
        }

        $name = strtolower((string) ($node['name'] ?? ''));
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($name === 'rt') {
            return [self::normalizeRubyMetadataText(self::rubyNodeText($node))];
        }

        if ($name !== 'rtc') {
            return [];
        }

        $annotations = [];
        foreach ($children as $child) {
            if (($child['type'] ?? '') === 'element' && strtolower((string) ($child['name'] ?? '')) === 'rt') {
                $annotations[] = self::normalizeRubyMetadataText(self::rubyNodeText($child));
            }
        }

        if ($annotations === []) {
            $annotations[] = self::normalizeRubyMetadataText(self::rubyNodeText($node));
        }

        return $annotations;
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    private static function rubyFallbackText(array $children): string
    {
        $text = '';
        foreach ($children as $child) {
            if (($child['type'] ?? '') !== 'element' || strtolower((string) ($child['name'] ?? '')) !== 'rp') {
                continue;
            }

            $text .= self::rubyNodeText($child);
        }

        return self::normalizeRubyMetadataText($text);
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function rubyNodeText(array $node): string
    {
        if (($node['type'] ?? '') === 'text') {
            return (string) ($node['text'] ?? '');
        }
        if (($node['type'] ?? '') !== 'element' || !is_array($node['children'] ?? null)) {
            return '';
        }

        $text = '';
        foreach ($node['children'] as $child) {
            if (!is_array($child)) {
                continue;
            }

            $text .= self::rubyNodeText($child);
        }

        return $text;
    }

    private static function normalizeRubyMetadataText(string $text): string
    {
        $text = preg_replace('/[\t\r\n\f ]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlMathAnnotationReviewMetadata(array &$attrs, array $children, array &$diagnostics): void
    {
        $sourceAnnotation = null;
        $xmlEncoding = null;
        foreach (self::htmlMathAnnotationNodes($children) as $annotation) {
            $name = strtolower((string) ($annotation['name'] ?? ''));
            $annotationAttrs = is_array($annotation['attrs'] ?? null) ? $annotation['attrs'] : [];
            $encoding = self::normalizeHtmlMathAnnotationEncoding((string) ($annotationAttrs['encoding'] ?? ''));
            if ($encoding === null) {
                continue;
            }

            if ($name === 'annotation' && $sourceAnnotation === null) {
                $source = self::normalizeHtmlMathAnnotationSourceText(self::htmlMathAnnotationNodeText($annotation));
                if ($source !== null) {
                    $sourceAnnotation = [$encoding, $source, $annotation];
                }
                continue;
            }

            if ($name === 'annotation-xml' && $xmlEncoding === null) {
                $xmlEncoding = [$encoding, $annotation];
            }
        }

        if ($sourceAnnotation !== null) {
            [$encoding, $source, $annotation] = $sourceAnnotation;
            $attrs['data-pandoc-math-source-format'] = $encoding;
            $attrs['data-pandoc-math-source'] = $source;
            $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
                'code' => 'math-annotation-review',
                'tag' => 'math',
                'attribute' => 'annotation',
                'metadataAttribute' => 'data-pandoc-math-source',
                'encoding' => $encoding,
                'reason' => 'math-source-annotation-preserved-as-review-metadata',
            ], $annotation);
        }

        if ($xmlEncoding !== null) {
            [$encoding, $annotation] = $xmlEncoding;
            $attrs['data-pandoc-math-annotation-xml-encoding'] = $encoding;
            $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
                'code' => 'math-annotation-review',
                'tag' => 'math',
                'attribute' => 'annotation',
                'metadataAttribute' => 'data-pandoc-math-annotation-xml-encoding',
                'encoding' => $encoding,
                'reason' => 'math-xml-annotation-encoding-preserved-as-review-metadata',
            ], $annotation);
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private static function htmlMathAnnotationNodes(array $nodes, int $depth = 0, bool $insideSemantics = false): array
    {
        if ($depth > 6) {
            return [];
        }

        $annotations = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'element') {
                continue;
            }

            $name = strtolower((string) ($node['name'] ?? ''));
            if ($insideSemantics && ($name === 'annotation' || $name === 'annotation-xml')) {
                $annotations[] = $node;
                continue;
            }

            $children = $node['children'] ?? null;
            if (is_array($children)) {
                array_push(
                    $annotations,
                    ...self::htmlMathAnnotationNodes($children, $depth + 1, $insideSemantics || $name === 'semantics')
                );
            }
        }

        return $annotations;
    }

    private static function normalizeHtmlMathAnnotationEncoding(string $value): ?string
    {
        $encoding = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($encoding === '' || strlen($encoding) > 96) {
            return null;
        }

        return preg_match('/^[a-z0-9][a-z0-9.+-]*\/[a-z0-9][a-z0-9.+-]*(?:\+[a-z0-9.+-]+)?$/', $encoding) === 1
            ? $encoding
            : null;
    }

    private static function normalizeHtmlMathAnnotationSourceText(string $text): ?string
    {
        if (preg_match('/[\x00-\x08\x0B\x0E-\x1F\x7F]/u', $text) === 1) {
            return null;
        }

        $text = trim(preg_replace('/[\t\r\n\f ]+/u', ' ', $text) ?? $text);
        if ($text === '' || strlen($text) > 512) {
            return null;
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function htmlMathAnnotationNodeText(array $node): string
    {
        if (($node['type'] ?? '') === 'text') {
            return (string) ($node['text'] ?? '');
        }
        if (($node['type'] ?? '') !== 'element') {
            return '';
        }

        $text = '';
        $children = $node['children'] ?? null;
        if (!is_array($children)) {
            return $text;
        }
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $text .= self::htmlMathAnnotationNodeText($child);
        }

        return $text;
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlFigureReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array $children,
        array &$diagnostics
    ): void {
        if (array_key_exists('align', $attrs)) {
            $align = self::normalizeHtmlFigureAlignAttribute((string) $attrs['align']);
            unset($attrs['align']);
            if ($align === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'figure',
                    'attribute' => 'align',
                    'reason' => 'invalid-legacy-figure-align',
                ], $element);
            } else {
                $attrs['data-pandoc-figure-align'] = $align;
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'figure-metadata-review',
                    'tag' => 'figure',
                    'attribute' => 'align',
                    'alignment' => $align,
                    'reason' => 'legacy-figure-align-preserved-as-metadata',
                ], $element);
            }
        }

        $caption = self::htmlFigureCaptionMetadata($children);
        if ($caption === null) {
            return;
        }

        $attrs['data-pandoc-figure-caption'] = $caption['text'];
        if (($caption['id'] ?? '') !== '') {
            $attrs['data-pandoc-figure-caption-id'] = (string) $caption['id'];
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'figure-metadata-review',
            'tag' => 'figure',
            'reason' => 'figcaption-text-preserved-as-metadata',
        ], $element);
    }

    private static function normalizeHtmlFigureAlignAttribute(string $value): ?string
    {
        $align = strtolower(self::cleanHtmlMetadataAttribute($value));

        return in_array($align, ['center', 'left', 'right'], true) ? $align : null;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array{text:string, id?:string}|null
     */
    private static function htmlFigureCaptionMetadata(array $children): ?array
    {
        foreach ($children as $child) {
            if (($child['type'] ?? '') !== 'element' || strtolower((string) ($child['name'] ?? '')) !== 'figcaption') {
                continue;
            }

            $text = self::normalizeFigureMetadataText(self::figureNodeText($child));
            if ($text === '') {
                return null;
            }

            $metadata = ['text' => $text];
            $attrs = is_array($child['attrs'] ?? null) ? $child['attrs'] : [];
            $id = self::normalizeHtmlFigureCaptionId((string) ($attrs['id'] ?? ''));
            if ($id !== null) {
                $metadata['id'] = $id;
            }

            return $metadata;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function figureNodeText(array $node): string
    {
        if (($node['type'] ?? '') === 'text') {
            return (string) ($node['text'] ?? '');
        }
        if (($node['type'] ?? '') !== 'element' || !is_array($node['children'] ?? null)) {
            return '';
        }

        $text = '';
        foreach ($node['children'] as $child) {
            if (!is_array($child)) {
                continue;
            }

            $text .= self::figureNodeText($child);
        }

        return $text;
    }

    private static function normalizeFigureMetadataText(string $text): string
    {
        $text = preg_replace('/[\t\r\n\f ]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function normalizeHtmlFigureCaptionId(string $id): ?string
    {
        $id = self::cleanHtmlMetadataAttribute($id);
        if ($id === '' || strlen($id) > 128) {
            return null;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $id) !== 1) {
            return null;
        }

        return $id;
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlFieldsetReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array &$children,
        array &$diagnostics
    ): void {
        if (array_key_exists('disabled', $attrs)) {
            unset($attrs['disabled']);
            $attrs['data-pandoc-fieldset-disabled'] = 'true';
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'fieldset-review',
                'tag' => 'fieldset',
                'attribute' => 'disabled',
                'reason' => 'fieldset-disabled-preserved-as-metadata',
            ], $element);
        }

        if (array_key_exists('name', $attrs)) {
            $name = self::normalizeHtmlFieldsetNameAttribute((string) $attrs['name']);
            unset($attrs['name']);
            if ($name === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'fieldset',
                    'attribute' => 'name',
                    'reason' => 'invalid-fieldset-name-metadata',
                ], $element);
            } else {
                $attrs['data-pandoc-fieldset-name'] = $name;
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'fieldset-review',
                    'tag' => 'fieldset',
                    'attribute' => 'name',
                    'reason' => 'fieldset-name-preserved-as-metadata',
                ], $element);
            }
        }

        if (array_key_exists('form', $attrs)) {
            $form = self::normalizeHtmlFieldsetFormAttribute((string) $attrs['form']);
            unset($attrs['form']);
            if ($form === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'fieldset',
                    'attribute' => 'form',
                    'reason' => 'invalid-fieldset-form-metadata',
                ], $element);
            } else {
                $attrs['data-pandoc-fieldset-form'] = $form;
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'fieldset-review',
                    'tag' => 'fieldset',
                    'attribute' => 'form',
                    'reason' => 'fieldset-form-owner-preserved-as-metadata',
                ], $element);
            }
        }

        $legend = self::htmlFieldsetLegendMetadata($children);
        if ($legend === null) {
            return;
        }

        $attrs['data-pandoc-fieldset-label'] = $legend;
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'fieldset-review',
            'tag' => 'fieldset',
            'source' => 'legend',
            'reason' => 'fieldset-legend-preserved-as-metadata',
        ], $element);
    }

    private static function normalizeHtmlFieldsetNameAttribute(string $value): ?string
    {
        $value = self::cleanHtmlMetadataAttribute($value);
        if ($value === '' || strlen($value) > 128) {
            return null;
        }
        if (preg_match('/[<>{}]/u', $value) === 1) {
            return null;
        }

        return $value;
    }

    private static function normalizeHtmlFieldsetFormAttribute(string $value): ?string
    {
        $value = self::cleanHtmlMetadataAttribute($value);
        if ($value === '' || strlen($value) > 128 || !self::isSafeHtmlAriaIdToken($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    private static function htmlFieldsetLegendMetadata(array &$children): ?string
    {
        foreach ($children as $index => $child) {
            if (($child['type'] ?? '') !== 'element' || strtolower((string) ($child['name'] ?? '')) !== 'legend') {
                continue;
            }

            $text = self::normalizeFieldsetMetadataText(self::fieldsetNodeText($child));
            if ($text === '') {
                return null;
            }

            $attrs = is_array($child['attrs'] ?? null) ? $child['attrs'] : [];
            $attrs['data-pandoc-fieldset-legend'] = 'true';
            $child['attrs'] = $attrs;
            $children[$index] = $child;

            return $text;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function fieldsetNodeText(array $node): string
    {
        if (($node['type'] ?? '') === 'text') {
            return (string) ($node['text'] ?? '');
        }
        if (($node['type'] ?? '') !== 'element' || !is_array($node['children'] ?? null)) {
            return '';
        }

        $text = '';
        foreach ($node['children'] as $child) {
            if (!is_array($child)) {
                continue;
            }

            $text .= self::fieldsetNodeText($child);
        }

        return $text;
    }

    private static function normalizeFieldsetMetadataText(string $text): string
    {
        $text = preg_replace('/[\t\r\n\f ]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlLabelReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array $children,
        array &$diagnostics
    ): void {
        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower($attribute->name);
            if (!str_starts_with($attributeName, 'data-pandoc-label-')) {
                continue;
            }

            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'label',
                'attribute' => $attributeName,
                'reason' => 'reserved-review-metadata-source-spoof',
            ], $element);
        }

        $labelText = self::normalizeHtmlLabelMetadataText(self::textFromNormalizedNodes($children));
        if ($labelText !== null) {
            $attrs['data-pandoc-label-text'] = $labelText;
            self::addHtmlLabelMetadataDiagnostic($diagnostics, $element, 'text', 'data-pandoc-label-text');
        }

        if ($element->hasAttribute('for')) {
            unset($attrs['for']);
            $forId = self::normalizeHtmlLabelForAttribute($element->getAttribute('for'));
            if ($forId === null) {
                self::addHtmlInvalidLabelMetadataDiagnostic($diagnostics, $element, 'for');

                return;
            }

            $attrs['data-pandoc-label-for'] = $forId;
            self::addHtmlLabelMetadataDiagnostic($diagnostics, $element, 'for', 'data-pandoc-label-for');
            $control = self::htmlElementById($element, $forId);
            if (!$control instanceof \DOMElement) {
                $attrs['data-pandoc-label-control-source'] = 'missing-for-target';
                self::addHtmlLabelMetadataDiagnostic($diagnostics, $element, 'for', 'data-pandoc-label-control-source');

                return;
            }

            if (!self::isHtmlLabelableElement($control)) {
                $attrs['data-pandoc-label-control-source'] = 'non-labelable-for-target';
                self::addHtmlLabelMetadataDiagnostic($diagnostics, $element, 'for', 'data-pandoc-label-control-source');

                return;
            }

            self::addHtmlLabelControlMetadata($attrs, $diagnostics, $element, $control, 'for-attribute');

            return;
        }

        $control = self::firstHtmlLabelableDescendant($element);
        if (!$control instanceof \DOMElement) {
            return;
        }

        self::addHtmlLabelControlMetadata($attrs, $diagnostics, $element, $control, 'descendant');
    }

    private static function normalizeHtmlLabelMetadataText(string $value): ?string
    {
        $text = self::cleanHtmlMetadataAttribute($value);
        if ($text === '' || strlen($text) > 256 || preg_match('/[<>{}`]/u', $text) === 1) {
            return null;
        }

        return $text;
    }

    private static function normalizeHtmlLabelForAttribute(string $value): ?string
    {
        $forId = self::cleanHtmlMetadataAttribute($value);
        if ($forId === '' || strlen($forId) > 128 || !self::isSafeHtmlAriaIdToken($forId)) {
            return null;
        }

        return $forId;
    }

    private static function htmlElementById(\DOMElement $context, string $id): ?\DOMElement
    {
        $document = $context->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return null;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->getAttribute('id') === $id) {
                return $element;
            }
        }

        return null;
    }

    private static function firstHtmlLabelableDescendant(\DOMElement $element): ?\DOMElement
    {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof \DOMElement || !self::isHtmlLabelableElement($descendant)) {
                continue;
            }

            return $descendant;
        }

        return null;
    }

    private static function isHtmlLabelableElement(\DOMElement $element): bool
    {
        $name = self::htmlNormalizedElementName($element);
        if ($name === 'input' && self::htmlInputType($element) === 'hidden') {
            return false;
        }

        return in_array($name, ['button', 'input', 'meter', 'output', 'progress', 'select', 'textarea'], true);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlLabelControlMetadata(
        array &$attrs,
        array &$diagnostics,
        \DOMElement $label,
        \DOMElement $control,
        string $source
    ): void {
        $tagName = self::htmlNormalizedElementName($control);
        $attrs['data-pandoc-label-control-source'] = $source;
        $attrs['data-pandoc-label-control'] = $tagName;
        self::addHtmlLabelMetadataDiagnostic($diagnostics, $label, 'control', 'data-pandoc-label-control-source');
        self::addHtmlLabelMetadataDiagnostic($diagnostics, $label, 'control', 'data-pandoc-label-control');

        $id = self::cleanHtmlMetadataAttribute($control->getAttribute('id'));
        if ($id !== '' && strlen($id) <= 128 && self::isSafeHtmlAriaIdToken($id)) {
            $attrs['data-pandoc-label-control-id'] = $id;
            self::addHtmlLabelMetadataDiagnostic($diagnostics, $label, 'control', 'data-pandoc-label-control-id');
        }

        $name = self::normalizeHtmlLabelControlName($control->getAttribute('name'));
        if ($name !== null) {
            $attrs['data-pandoc-label-control-name'] = $name;
            self::addHtmlLabelMetadataDiagnostic($diagnostics, $label, 'control', 'data-pandoc-label-control-name');
        }

        if ($tagName === 'input') {
            $attrs['data-pandoc-label-control-type'] = self::htmlInputType($control);
            self::addHtmlLabelMetadataDiagnostic($diagnostics, $label, 'control', 'data-pandoc-label-control-type');
        } elseif ($tagName === 'button') {
            $attrs['data-pandoc-label-control-type'] = self::htmlButtonType($control);
            self::addHtmlLabelMetadataDiagnostic($diagnostics, $label, 'control', 'data-pandoc-label-control-type');
        }
    }

    private static function normalizeHtmlLabelControlName(string $value): ?string
    {
        $name = self::cleanHtmlMetadataAttribute($value);
        if ($name === '' || strlen($name) > 128 || preg_match('/[<>"\'`{}]/u', $name) === 1) {
            return null;
        }

        return $name;
    }

    private static function htmlInputType(\DOMElement $element): string
    {
        $type = strtolower(self::cleanHtmlMetadataAttribute($element->getAttribute('type')));

        return in_array($type, [
            'button',
            'checkbox',
            'color',
            'date',
            'datetime-local',
            'email',
            'file',
            'hidden',
            'image',
            'month',
            'number',
            'password',
            'radio',
            'range',
            'reset',
            'search',
            'submit',
            'tel',
            'text',
            'time',
            'url',
            'week',
        ], true) ? $type : 'text';
    }

    private static function htmlButtonType(\DOMElement $element): string
    {
        $type = strtolower(self::cleanHtmlMetadataAttribute($element->getAttribute('type')));

        return in_array($type, ['button', 'reset', 'submit'], true) ? $type : 'submit';
    }

    private static function htmlNormalizedElementName(\DOMElement $element): string
    {
        $rawName = self::rawElementName($element, 'html');
        $foreignContext = self::elementForeignContext($element, $rawName, 'html', null);

        return self::normalizedElementName($rawName, $foreignContext);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlLabelMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName,
        string $metadataAttribute
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'label-metadata-review',
            'tag' => 'label',
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'label-control-association-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidLabelMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => 'label',
            'attribute' => $attributeName,
            'reason' => 'invalid-label-control-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function htmlDatalistReviewMetadataNode(
        \DOMElement $element,
        array &$diagnostics
    ): ?array {
        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower($attribute->name);
            if (!str_starts_with($attributeName, 'data-pandoc-datalist-')) {
                continue;
            }

            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'datalist',
                'attribute' => $attributeName,
                'reason' => 'reserved-review-metadata-source-spoof',
            ], $element);
        }

        $attrs = [];
        if ($element->hasAttribute('id')) {
            $id = self::normalizeHtmlDatalistIdAttribute($element->getAttribute('id'));
            if ($id === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'datalist',
                    'attribute' => 'id',
                    'reason' => 'invalid-datalist-id-metadata',
                ], $element);
            } else {
                $attrs['data-pandoc-datalist-id'] = $id;
                self::addHtmlDatalistReviewDiagnostic($diagnostics, $element, 'id', 'data-pandoc-datalist-id');
            }
        }

        $labels = self::htmlDatalistOptionLabels($element, $diagnostics);
        if ($labels !== []) {
            $attrs['data-pandoc-datalist-options'] = implode(' | ', $labels);
            self::addHtmlDatalistReviewDiagnostic($diagnostics, $element, 'label', 'data-pandoc-datalist-options');
        }

        if ($attrs === []) {
            return null;
        }

        $label = $labels === []
            ? 'Datalist suggestions' . (isset($attrs['data-pandoc-datalist-id']) ? ': ' . $attrs['data-pandoc-datalist-id'] : '')
            : 'Datalist suggestions: ' . implode('; ', $labels);

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $label,
            ]],
        ], $element);
    }

    private static function normalizeHtmlDatalistIdAttribute(string $value): ?string
    {
        $id = self::cleanHtmlMetadataAttribute($value);
        if ($id === '' || strlen($id) > 128 || preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $id) !== 1) {
            return null;
        }

        return $id;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<string>
     */
    private static function htmlDatalistOptionLabels(\DOMElement $element, array &$diagnostics): array
    {
        $labels = [];
        foreach ($element->getElementsByTagName('option') as $option) {
            if (!$option instanceof \DOMElement) {
                continue;
            }

            $source = null;
            $sourceAttribute = null;
            if ($option->hasAttribute('label')) {
                $source = $option->getAttribute('label');
                $sourceAttribute = 'label';
            } elseif (trim($option->textContent) !== '') {
                $source = $option->textContent;
            }

            if ($source === null) {
                continue;
            }

            $label = self::normalizeHtmlDatalistOptionLabel($source);
            if ($label === null) {
                if ($sourceAttribute !== null) {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'unsafe-attribute',
                        'tag' => 'option',
                        'attribute' => $sourceAttribute,
                        'reason' => 'invalid-datalist-option-label',
                    ], $option);
                }
                continue;
            }

            if (!in_array($label, $labels, true)) {
                $labels[] = $label;
            }
            if (count($labels) >= 20) {
                break;
            }
        }

        return $labels;
    }

    private static function normalizeHtmlDatalistOptionLabel(string $value): ?string
    {
        $label = self::cleanHtmlMetadataAttribute($value);
        if ($label === '' || strlen($label) > 128 || preg_match('/[<>{}`]/u', $label) === 1) {
            return null;
        }

        return $label;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlDatalistReviewDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName,
        string $metadataAttribute
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'datalist-review',
            'tag' => 'datalist',
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'datalist-suggestions-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function htmlSelectReviewMetadataNode(
        \DOMElement $element,
        array &$diagnostics
    ): ?array {
        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower($attribute->name);
            if (!str_starts_with($attributeName, 'data-pandoc-select-')) {
                continue;
            }

            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'select',
                'attribute' => $attributeName,
                'reason' => 'reserved-review-metadata-source-spoof',
            ], $element);
        }

        $attrs = [];
        foreach ([
            'name' => 'data-pandoc-select-name',
            'form' => 'data-pandoc-select-form',
        ] as $sourceAttribute => $metadataAttribute) {
            if (!$element->hasAttribute($sourceAttribute)) {
                continue;
            }

            $value = self::normalizeHtmlSelectTextMetadataAttribute(
                $sourceAttribute,
                $element->getAttribute($sourceAttribute),
                $diagnostics,
                $element
            );
            if ($value === null) {
                continue;
            }

            $attrs[$metadataAttribute] = $value;
            self::addHtmlSelectMetadataDiagnostic($diagnostics, $element, $sourceAttribute, $metadataAttribute);
        }

        foreach ([
            'multiple' => 'data-pandoc-select-multiple',
            'required' => 'data-pandoc-select-required',
            'disabled' => 'data-pandoc-select-disabled',
        ] as $sourceAttribute => $metadataAttribute) {
            if (!$element->hasAttribute($sourceAttribute)) {
                continue;
            }

            $attrs[$metadataAttribute] = 'true';
            self::addHtmlSelectMetadataDiagnostic($diagnostics, $element, $sourceAttribute, $metadataAttribute);
        }

        if ($element->hasAttribute('size')) {
            $size = self::normalizeHtmlSelectSizeAttribute($element->getAttribute('size'));
            if ($size === null) {
                self::addHtmlInvalidSelectMetadataDiagnostic($diagnostics, $element, 'size');
            } else {
                $attrs['data-pandoc-select-size'] = $size;
                self::addHtmlSelectMetadataDiagnostic($diagnostics, $element, 'size', 'data-pandoc-select-size');
            }
        }

        $selectedLabels = self::htmlSelectSelectedOptionLabels($element, $diagnostics);
        if ($selectedLabels !== []) {
            $attrs['data-pandoc-select-selected'] = implode(' | ', $selectedLabels);
            self::addHtmlSelectMetadataDiagnostic($diagnostics, $element, 'selected', 'data-pandoc-select-selected');
        }

        if ($attrs === []) {
            return null;
        }

        $label = $selectedLabels === []
            ? 'Select field' . (isset($attrs['data-pandoc-select-name']) ? ': ' . $attrs['data-pandoc-select-name'] : '')
            : 'Select: ' . implode('; ', $selectedLabels);

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $label,
            ]],
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlSelectTextMetadataAttribute(
        string $attribute,
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '' || strlen($cleaned) > 128) {
            self::addHtmlInvalidSelectMetadataDiagnostic($diagnostics, $element, $attribute);

            return null;
        }

        if ($attribute === 'form') {
            if (!self::isSafeHtmlAriaIdToken($cleaned)) {
                self::addHtmlInvalidSelectMetadataDiagnostic($diagnostics, $element, $attribute);

                return null;
            }

            return $cleaned;
        }

        if (preg_match('/[<>"\'`{}]/u', $cleaned) === 1) {
            self::addHtmlInvalidSelectMetadataDiagnostic($diagnostics, $element, $attribute);

            return null;
        }

        return $cleaned;
    }

    private static function normalizeHtmlSelectSizeAttribute(string $value): ?string
    {
        $size = self::cleanHtmlMetadataAttribute($value);
        if ($size === '' || preg_match('/^[0-9]+$/', $size) !== 1) {
            return null;
        }

        $integer = (int) $size;
        if ($integer < 1 || $integer > 999) {
            return null;
        }

        return (string) $integer;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<string>
     */
    private static function htmlSelectSelectedOptionLabels(\DOMElement $element, array &$diagnostics): array
    {
        $labels = [];
        $options = [];
        $hasExplicitSelected = false;
        foreach ($element->getElementsByTagName('option') as $option) {
            if (!$option instanceof \DOMElement) {
                continue;
            }

            $options[] = $option;
            if ($option->hasAttribute('selected')) {
                $hasExplicitSelected = true;
            }
        }

        foreach ($options as $index => $option) {
            $isSelected = $option->hasAttribute('selected');
            if (!$isSelected && ($hasExplicitSelected || $index !== 0 || $element->hasAttribute('multiple'))) {
                continue;
            }

            $label = self::htmlSelectOptionLabel($option, $diagnostics);
            if ($label === null) {
                continue;
            }

            if (!in_array($label, $labels, true)) {
                $labels[] = $label;
            }
            if (count($labels) >= 20) {
                break;
            }
        }

        return $labels;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function htmlSelectOptionLabel(\DOMElement $option, array &$diagnostics): ?string
    {
        $source = null;
        $sourceAttribute = null;
        if ($option->hasAttribute('label')) {
            $source = $option->getAttribute('label');
            $sourceAttribute = 'label';
        } elseif (trim($option->textContent) !== '') {
            $source = $option->textContent;
        }

        if ($source === null) {
            return null;
        }

        $label = self::normalizeHtmlSelectOptionLabel($source);
        if ($label !== null) {
            return $label;
        }

        if ($sourceAttribute !== null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'option',
                'attribute' => $sourceAttribute,
                'reason' => 'invalid-select-option-label',
            ], $option);
        }

        return null;
    }

    private static function normalizeHtmlSelectOptionLabel(string $value): ?string
    {
        $label = self::cleanHtmlMetadataAttribute($value);
        if ($label === '' || strlen($label) > 128 || preg_match('/[<>{}`]/u', $label) === 1) {
            return null;
        }

        return $label;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlSelectMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName,
        string $metadataAttribute
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'select-metadata-review',
            'tag' => 'select',
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'select-state-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidSelectMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => 'select',
            'attribute' => $attributeName,
            'reason' => 'invalid-select-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function htmlFormReviewMetadataNode(
        \DOMElement $element,
        array &$diagnostics,
        ?string $baseUrl
    ): ?array {
        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower($attribute->name);
            if (!str_starts_with($attributeName, 'data-pandoc-form-')) {
                continue;
            }

            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'form',
                'attribute' => $attributeName,
                'reason' => 'reserved-review-metadata-source-spoof',
            ], $element);
        }

        $hasExplicitReviewContext = $element->hasAttribute('method')
            || $element->hasAttribute('target')
            || $element->hasAttribute('autocomplete')
            || $element->hasAttribute('name');
        if (!$hasExplicitReviewContext) {
            return null;
        }

        $attrs = [];
        if ($element->hasAttribute('method')) {
            $method = self::normalizeHtmlFormMethodAttribute($element->getAttribute('method'));
            if ($method === null) {
                self::addHtmlInvalidFormMetadataDiagnostic($diagnostics, $element, 'method');
            } else {
                $attrs['data-pandoc-form-method'] = $method;
                self::addHtmlFormMetadataDiagnostic($diagnostics, $element, 'method', 'data-pandoc-form-method');
            }
        }

        if ($element->hasAttribute('action')) {
            $action = self::normalizeHtmlFormActionAttribute($element->getAttribute('action'), $diagnostics, $element, $baseUrl);
            if ($action !== null) {
                $attrs['data-pandoc-form-action'] = $action;
                self::addHtmlFormMetadataDiagnostic($diagnostics, $element, 'action', 'data-pandoc-form-action');
            }
        }

        if ($element->hasAttribute('target')) {
            $target = self::normalizeHtmlFormTargetAttribute($element->getAttribute('target'));
            if ($target === null) {
                self::addHtmlInvalidFormMetadataDiagnostic($diagnostics, $element, 'target');
            } else {
                $attrs['data-pandoc-form-target'] = $target;
                self::addHtmlFormMetadataDiagnostic($diagnostics, $element, 'target', 'data-pandoc-form-target');
            }
        }

        if ($element->hasAttribute('autocomplete')) {
            $autocomplete = self::normalizeHtmlFormAutocompleteAttribute($element->getAttribute('autocomplete'));
            if ($autocomplete === null) {
                self::addHtmlInvalidFormMetadataDiagnostic($diagnostics, $element, 'autocomplete');
            } else {
                $attrs['data-pandoc-form-autocomplete'] = $autocomplete;
                self::addHtmlFormMetadataDiagnostic($diagnostics, $element, 'autocomplete', 'data-pandoc-form-autocomplete');
            }
        }

        if ($element->hasAttribute('name')) {
            $name = self::normalizeHtmlFormNameAttribute($element->getAttribute('name'));
            if ($name === null) {
                self::addHtmlInvalidFormMetadataDiagnostic($diagnostics, $element, 'name');
            } else {
                $attrs['data-pandoc-form-name'] = $name;
                self::addHtmlFormMetadataDiagnostic($diagnostics, $element, 'name', 'data-pandoc-form-name');
            }
        }

        if ($attrs === []) {
            return null;
        }

        $label = isset($attrs['data-pandoc-form-method'])
            ? 'Form submission: ' . $attrs['data-pandoc-form-method']
            : 'Form metadata';

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $label,
            ]],
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function htmlButtonReviewMetadataNode(
        \DOMElement $element,
        array $children,
        array &$diagnostics,
        ?string $baseUrl
    ): ?array {
        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower($attribute->name);
            if (!str_starts_with($attributeName, 'data-pandoc-button-')) {
                continue;
            }

            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'button',
                'attribute' => $attributeName,
                'reason' => 'reserved-review-metadata-source-spoof',
            ], $element);
        }

        $attrs = [];
        $type = 'submit';
        if ($element->hasAttribute('type')) {
            $type = self::normalizeHtmlButtonTypeAttribute($element->getAttribute('type'), $diagnostics, $element) ?? 'submit';
        }
        $attrs['data-pandoc-button-type'] = $type;
        self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'type', 'data-pandoc-button-type');

        foreach ([
            'name' => 'data-pandoc-button-name',
            'value' => 'data-pandoc-button-value',
            'form' => 'data-pandoc-button-form',
        ] as $sourceAttribute => $metadataAttribute) {
            if (!$element->hasAttribute($sourceAttribute)) {
                continue;
            }

            $value = self::normalizeHtmlButtonTextMetadataAttribute(
                $sourceAttribute,
                $element->getAttribute($sourceAttribute),
                $diagnostics,
                $element
            );
            if ($value === null) {
                continue;
            }

            $attrs[$metadataAttribute] = $value;
            self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, $sourceAttribute, $metadataAttribute);
        }

        if ($element->hasAttribute('formaction')) {
            $formAction = self::normalizeHtmlButtonFormActionAttribute(
                $element->getAttribute('formaction'),
                $diagnostics,
                $element,
                $baseUrl
            );
            if ($formAction !== null) {
                $attrs['data-pandoc-button-formaction'] = $formAction;
                self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'formaction', 'data-pandoc-button-formaction');
            }
        }

        if ($element->hasAttribute('formmethod')) {
            $formMethod = self::normalizeHtmlButtonFormMethodAttribute($element->getAttribute('formmethod'), $diagnostics, $element);
            if ($formMethod !== null) {
                $attrs['data-pandoc-button-formmethod'] = $formMethod;
                self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'formmethod', 'data-pandoc-button-formmethod');
            }
        }

        if ($element->hasAttribute('formenctype')) {
            $formEnctype = self::normalizeHtmlButtonFormEnctypeAttribute($element->getAttribute('formenctype'), $diagnostics, $element);
            if ($formEnctype !== null) {
                $attrs['data-pandoc-button-formenctype'] = $formEnctype;
                self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'formenctype', 'data-pandoc-button-formenctype');
            }
        }

        if ($element->hasAttribute('formtarget')) {
            $formTarget = self::normalizeHtmlButtonFormTargetAttribute($element->getAttribute('formtarget'), $diagnostics, $element);
            if ($formTarget !== null) {
                $attrs['data-pandoc-button-formtarget'] = $formTarget;
                self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'formtarget', 'data-pandoc-button-formtarget');
            }
        }

        foreach ([
            'formnovalidate' => 'data-pandoc-button-formnovalidate',
            'disabled' => 'data-pandoc-button-disabled',
        ] as $sourceAttribute => $metadataAttribute) {
            if (!$element->hasAttribute($sourceAttribute)) {
                continue;
            }

            $attrs[$metadataAttribute] = 'true';
            self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, $sourceAttribute, $metadataAttribute);
        }

        if ($element->hasAttribute('command') || $element->hasAttribute('commandfor')) {
            self::addHtmlButtonCommandMetadata($attrs, $diagnostics, $element);
        }

        if ($element->hasAttribute('popovertarget') || $element->hasAttribute('popovertargetaction')) {
            foreach (self::normalizeHtmlPopoverTargetInvokerAttributes($element, 'button', $diagnostics) as $metadataAttribute => $metadataValue) {
                $attrs[$metadataAttribute] = $metadataValue;
            }
        }

        if ($children === []) {
            $children = [[
                'type' => 'text',
                'text' => 'Button: ' . $type,
            ]];
        }

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => $attrs,
            'children' => $children,
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlButtonCommandMetadata(
        array &$attrs,
        array &$diagnostics,
        \DOMElement $element
    ): void {
        $commandRaw = $element->hasAttribute('command') ? $element->getAttribute('command') : null;
        $command = self::htmlButtonCommandState($commandRaw);
        $commandForRaw = $element->hasAttribute('commandfor') ? $element->getAttribute('commandfor') : null;
        $commandFor = self::htmlButtonCommandFor($commandForRaw);
        $target = $commandFor === null ? null : self::htmlElementById($element, $commandFor);
        $issues = [];

        if ($commandRaw === null || trim($commandRaw) === '') {
            $issues[] = 'missing-button-command';
        } elseif ($command['state'] === 'unknown') {
            $issues[] = 'unknown-button-command';
            self::addHtmlInvalidButtonMetadataDiagnostic($diagnostics, $element, 'command');
        }

        if ($commandForRaw === null || trim($commandForRaw) === '') {
            $issues[] = 'missing-button-commandfor';
        } elseif ($commandFor === null) {
            $issues[] = 'invalid-button-commandfor-target';
            self::addHtmlInvalidButtonMetadataDiagnostic($diagnostics, $element, 'commandfor');
        } elseif (!$target instanceof \DOMElement) {
            $issues[] = 'missing-button-command-target';
        }

        if ($target instanceof \DOMElement) {
            $targetName = self::htmlNormalizedElementName($target);
            if (($command['family'] ?? null) === 'popover' && !$target->hasAttribute('popover')) {
                $issues[] = 'non-popover-button-command-target';
            }
            if (($command['family'] ?? null) === 'dialog' && $targetName !== 'dialog') {
                $issues[] = 'non-dialog-button-command-target';
            }
        }

        $attrs['data-pandoc-button-command-state'] = $command['state'];
        self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'command', 'data-pandoc-button-command-state');

        if ($command['command'] !== null) {
            $attrs['data-pandoc-button-command'] = $command['command'];
            self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'command', 'data-pandoc-button-command');
        }

        if ($command['family'] !== null) {
            $attrs['data-pandoc-button-command-family'] = $command['family'];
            self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'command', 'data-pandoc-button-command-family');
        }

        if ($commandFor !== null) {
            $attrs['data-pandoc-button-commandfor'] = $commandFor;
            self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'commandfor', 'data-pandoc-button-commandfor');
        }

        $attrs['data-pandoc-button-command-target'] = self::htmlButtonCommandTargetKind($target, $commandForRaw, $commandFor !== null);
        self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'commandfor', 'data-pandoc-button-command-target');

        if ($target instanceof \DOMElement) {
            foreach (self::htmlButtonCommandTargetMetadata($target) as $metadataAttribute => $metadataValue) {
                $attrs[$metadataAttribute] = $metadataValue;
                self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'commandfor', $metadataAttribute);
            }
        }

        $issues = array_values(array_unique($issues));
        if ($issues !== []) {
            $attrs['data-pandoc-button-command-issues'] = implode(' ', $issues);
            self::addHtmlButtonMetadataDiagnostic($diagnostics, $element, 'command', 'data-pandoc-button-command-issues');
        }
    }

    /**
     * @return array{command:?string, state:string, family:?string}
     */
    private static function htmlButtonCommandState(?string $commandRaw): array
    {
        if ($commandRaw === null || trim($commandRaw) === '') {
            return [
                'command' => null,
                'state' => 'missing',
                'family' => null,
            ];
        }

        $command = strtolower(trim($commandRaw));
        if (isset(self::HTML_BUTTON_COMMAND_STATES[$command])) {
            return [
                'command' => $command,
                'state' => self::HTML_BUTTON_COMMAND_STATES[$command],
                'family' => isset(self::HTML_BUTTON_POPOVER_COMMANDS[$command])
                    ? 'popover'
                    : (isset(self::HTML_BUTTON_DIALOG_COMMANDS[$command]) ? 'dialog' : null),
            ];
        }

        $custom = trim($commandRaw);
        if (preg_match('/^--[A-Za-z0-9._:-]+$/', $custom) === 1) {
            return [
                'command' => $custom,
                'state' => 'custom',
                'family' => 'custom',
            ];
        }

        return [
            'command' => null,
            'state' => 'unknown',
            'family' => null,
        ];
    }

    private static function htmlButtonCommandFor(?string $commandForRaw): ?string
    {
        if ($commandForRaw === null) {
            return null;
        }

        $commandFor = self::cleanHtmlMetadataAttribute($commandForRaw);
        if ($commandFor === '' || strlen($commandFor) > 128 || !self::isSafeHtmlAriaIdToken($commandFor)) {
            return null;
        }

        return $commandFor;
    }

    private static function htmlButtonCommandTargetKind(?\DOMElement $target, ?string $commandForRaw, bool $commandForValid): string
    {
        if (!$target instanceof \DOMElement) {
            if ($commandForRaw === null || trim($commandForRaw) === '') {
                return 'missing-reference';
            }

            return $commandForValid ? 'missing-target' : 'invalid-reference';
        }

        $targetName = self::htmlNormalizedElementName($target);
        if ($targetName === 'dialog') {
            return 'dialog';
        }
        if ($target->hasAttribute('popover')) {
            return 'popover';
        }

        return 'element';
    }

    /**
     * @return array<string, string>
     */
    private static function htmlButtonCommandTargetMetadata(\DOMElement $target): array
    {
        $metadata = [
            'data-pandoc-button-command-target-tag' => self::htmlNormalizedElementName($target),
        ];

        $id = self::cleanHtmlMetadataAttribute($target->getAttribute('id'));
        if ($id !== '' && strlen($id) <= 128 && self::isSafeHtmlAriaIdToken($id)) {
            $metadata['data-pandoc-button-command-target-id'] = $id;
        }

        if (self::htmlNormalizedElementName($target) === 'dialog') {
            $metadata['data-pandoc-button-command-target-dialog-state'] = $target->hasAttribute('open') ? 'open' : 'closed';
        }

        if ($target->hasAttribute('popover')) {
            $state = self::normalizeHtmlPopoverMetadataState($target->getAttribute('popover'));
            if ($state !== null) {
                $metadata['data-pandoc-button-command-target-popover'] = $state;
            }
        }

        return $metadata;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlButtonTypeAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $type = strtolower(self::cleanHtmlMetadataAttribute($value));
        if (in_array($type, ['button', 'reset', 'submit'], true)) {
            return $type;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => 'button',
            'attribute' => 'type',
            'value' => $type,
            'reason' => 'invalid-button-type-metadata',
        ], $element);

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlButtonTextMetadataAttribute(
        string $attribute,
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        $maxLength = $attribute === 'value' ? 512 : 128;
        if ($cleaned === '' || strlen($cleaned) > $maxLength) {
            self::addHtmlInvalidButtonMetadataDiagnostic($diagnostics, $element, $attribute);

            return null;
        }

        if ($attribute === 'form') {
            if (!self::isSafeHtmlAriaIdToken($cleaned)) {
                self::addHtmlInvalidButtonMetadataDiagnostic($diagnostics, $element, $attribute);

                return null;
            }

            return $cleaned;
        }

        if (preg_match('/[<>"\'`{}]/u', $cleaned) === 1) {
            self::addHtmlInvalidButtonMetadataDiagnostic($diagnostics, $element, $attribute);

            return null;
        }

        return $cleaned;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlButtonFormActionAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element,
        ?string $baseUrl
    ): ?string {
        $action = self::normalizeUrlAttributeValue($value);
        if ($action === '' || !self::isSafeFetchUrl($action)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'button',
                'attribute' => 'formaction',
            ], $element);

            return null;
        }

        if ($action !== $value) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => 'button',
                'attribute' => 'formaction',
            ], $element);
        }

        return $baseUrl === null ? $action : self::resolveRelativeUrl($baseUrl, $action);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlButtonFormMethodAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $method = self::normalizeHtmlFormMethodAttribute($value);
        if ($method === null) {
            self::addHtmlInvalidButtonMetadataDiagnostic($diagnostics, $element, 'formmethod');
        }

        return $method;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlButtonFormEnctypeAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $enctype = strtolower(self::cleanHtmlMetadataAttribute($value));
        if (in_array($enctype, ['application/x-www-form-urlencoded', 'multipart/form-data', 'text/plain'], true)) {
            return $enctype;
        }

        self::addHtmlInvalidButtonMetadataDiagnostic($diagnostics, $element, 'formenctype');

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlButtonFormTargetAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $target = self::normalizeHtmlFormTargetAttribute($value);
        if ($target === null) {
            self::addHtmlInvalidButtonMetadataDiagnostic($diagnostics, $element, 'formtarget');
        }

        return $target;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlButtonMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName,
        string $metadataAttribute
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'button-metadata-review',
            'tag' => 'button',
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'button-submission-metadata-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidButtonMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => 'button',
            'attribute' => $attributeName,
            'reason' => 'invalid-button-submission-metadata',
        ], $element);
    }

    private static function normalizeHtmlFormMethodAttribute(string $value): ?string
    {
        $method = strtolower(self::cleanHtmlMetadataAttribute($value));

        return in_array($method, ['get', 'post', 'dialog'], true) ? $method : null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlFormActionAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element,
        ?string $baseUrl
    ): ?string {
        if (!self::isSafeFetchUrl($value)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'form',
                'attribute' => 'action',
            ], $element);

            return null;
        }

        $action = self::normalizeUrlAttributeValue($value);
        if ($action === '') {
            return null;
        }

        return $baseUrl === null ? $action : self::resolveRelativeUrl($baseUrl, $action);
    }

    private static function normalizeHtmlFormTargetAttribute(string $value): ?string
    {
        $target = self::cleanHtmlMetadataAttribute(str_replace("\0", '', $value));
        if ($target === '' || strlen($target) > 128) {
            return null;
        }
        if (preg_match('/[\t\r\n\f<>"\'`{}]/', $target) === 1) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_.:-]+$/', $target) === 1 ? $target : null;
    }

    private static function normalizeHtmlFormAutocompleteAttribute(string $value): ?string
    {
        $autocomplete = strtolower(self::cleanHtmlMetadataAttribute($value));

        return in_array($autocomplete, ['on', 'off'], true) ? $autocomplete : null;
    }

    private static function normalizeHtmlFormNameAttribute(string $value): ?string
    {
        $name = self::cleanHtmlMetadataAttribute($value);
        if ($name === '' || strlen($name) > 128) {
            return null;
        }
        if (preg_match('/[<>"\'`{}]/u', $name) === 1) {
            return null;
        }

        return $name;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlFormMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName,
        string $metadataAttribute
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'form-metadata-review',
            'tag' => 'form',
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'form-submission-metadata-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidFormMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => 'form',
            'attribute' => $attributeName,
            'reason' => 'invalid-form-metadata',
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlDialogReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array &$diagnostics
    ): void {
        $state = $element->hasAttribute('open') ? 'open' : 'closed';
        unset($attrs['open']);
        $attrs['data-pandoc-dialog-state'] = $state;

        $diagnostic = [
            'code' => 'dialog-review',
            'tag' => 'dialog',
            'replacement' => 'div',
            'state' => $state,
            'reason' => 'dialog-content-preserved',
        ];
        if ($state === 'open') {
            $diagnostic['attribute'] = 'open';
        }

        $diagnostics[] = self::diagnosticWithSourceLine($diagnostic, $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markClosedHtmlDetailsReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array &$children,
        array &$diagnostics
    ): void {
        if ($element->hasAttribute('open')) {
            return;
        }

        $attrs['data-pandoc-details-state'] = 'closed';
        foreach ($children as $index => $child) {
            if (($child['type'] ?? '') !== 'element' || strtolower((string) ($child['name'] ?? '')) !== 'summary') {
                continue;
            }

            $childAttrs = is_array($child['attrs'] ?? null) ? $child['attrs'] : [];
            $childAttrs['data-pandoc-details-summary'] = 'true';
            $child['attrs'] = $childAttrs;
            $children[$index] = $child;
            break;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'closed-details-review',
            'tag' => 'details',
            'reason' => 'collapsed-content-preserved',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function normalizeHtmlSrcdocAttribute(
        string $srcdoc,
        array &$diagnostics,
        \DOMElement $element,
        ?string $baseUrl
    ): array {
        $srcdoc = trim($srcdoc);
        if ($srcdoc === '') {
            return self::htmlIframeSrcdocReviewNodes($element, [], self::normalizeCallerBaseUrl($baseUrl), $diagnostics);
        }

        try {
            self::assertSafeHtmlSource($srcdoc, 'iframe srcdoc');
            $srcdocDiagnostics = [];
            $wrapper = self::loadHtmlFragmentRoot($srcdoc, $srcdocDiagnostics);
        } catch (\InvalidArgumentException $error) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'invalid-srcdoc',
                'tag' => 'iframe',
                'attribute' => 'srcdoc',
                'message' => $error->getMessage(),
            ], $element);

            return [];
        }

        foreach ($srcdocDiagnostics as $diagnostic) {
            $diagnostic['context'] ??= 'iframe-srcdoc';
            $diagnostics[] = $diagnostic;
        }

        $srcdocBaseUrl = self::resolveFragmentBaseUrl($wrapper, $baseUrl, $diagnostics);
        $children = self::normalizeChildren($wrapper, 'html', $diagnostics, baseUrl: $srcdocBaseUrl);

        return self::htmlIframeSrcdocReviewNodes($element, $children, $srcdocBaseUrl, $diagnostics);
    }

    /**
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function htmlIframeSrcdocReviewNodes(
        \DOMElement $element,
        array $children,
        ?string $srcdocBaseUrl,
        array &$diagnostics
    ): array {
        $attrs = [
            'data-pandoc-iframe-srcdoc' => 'true',
        ];

        $title = $element->hasAttribute('title')
            ? self::cleanHtmlMetadataAttribute($element->getAttribute('title'))
            : '';
        if ($title !== '') {
            $attrs['title'] = $title;
        }
        if ($srcdocBaseUrl !== null) {
            $attrs['data-pandoc-iframe-srcdoc-base-url'] = $srcdocBaseUrl;
        }

        self::addIframePolicyReviewAttributes($element, $attrs, $diagnostics);
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'iframe-srcdoc-review',
            'tag' => 'iframe',
            'attribute' => 'srcdoc',
            'metadataAttribute' => 'data-pandoc-iframe-srcdoc',
            'reason' => 'iframe-srcdoc-preserved-as-review-container',
        ], $element);

        return [self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'div',
            'attrs' => $attrs,
            'children' => $children,
        ], $element)];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function normalizeHtmlIframeSourceElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        if (!$element->hasAttribute('src')) {
            return null;
        }

        $target = $element->getAttribute('src');
        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'iframe',
                'attribute' => 'src',
            ], $element);

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => 'iframe',
                'attribute' => 'src',
            ], $element);
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;
        $title = $element->hasAttribute('title')
            ? self::cleanHtmlMetadataAttribute($element->getAttribute('title'))
            : '';
        $attrs = [
            'href' => $href,
            'data-pandoc-iframe-src' => 'true',
        ];
        if ($title !== '') {
            $attrs['title'] = $title;
        }
        self::addIframePolicyReviewAttributes($element, $attrs, $diagnostics);

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $title !== '' ? $title : 'Embedded frame source',
            ]],
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function normalizeHtmlEmbeddedSourceElement(
        \DOMElement $element,
        string $tagName,
        string $sourceAttribute,
        string $metadataAttribute,
        string $fallbackLabel,
        array &$diagnostics,
        ?string $baseUrl
    ): ?array {
        if (!$element->hasAttribute($sourceAttribute)) {
            return null;
        }

        $target = $element->getAttribute($sourceAttribute);
        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => $sourceAttribute,
            ], $element);

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => $tagName,
                'attribute' => $sourceAttribute,
            ], $element);
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;
        $title = $element->hasAttribute('title')
            ? self::cleanHtmlMetadataAttribute($element->getAttribute('title'))
            : '';
        $attrs = [
            'href' => $href,
            $metadataAttribute => 'true',
        ];
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'embedded-source-review',
            'tag' => $tagName,
            'attribute' => $sourceAttribute,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'embedded-source-preserved-as-review-link',
        ], $element);

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $title !== '' ? $title : $fallbackLabel,
            ]],
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function htmlObjectParamReviewMetadataNodes(
        \DOMElement $object,
        array &$diagnostics,
        ?string $baseUrl
    ): array {
        $nodes = [];
        foreach ($object->childNodes as $child) {
            if (!$child instanceof \DOMElement || strtolower($child->localName) !== 'param') {
                continue;
            }

            $node = self::htmlObjectParamReviewMetadataNode($child, $diagnostics, $baseUrl);
            if ($node === null) {
                continue;
            }

            $nodes[] = $node;
            if (count($nodes) >= 20) {
                break;
            }
        }

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function htmlObjectParamReviewMetadataNode(
        \DOMElement $param,
        array &$diagnostics,
        ?string $baseUrl
    ): ?array {
        foreach ($param->attributes as $attribute) {
            $attributeName = strtolower($attribute->name);
            if (!str_starts_with($attributeName, 'data-pandoc-object-param-')) {
                continue;
            }

            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'param',
                'attribute' => $attributeName,
                'reason' => 'reserved-review-metadata-source-spoof',
            ], $param);
        }

        if (!$param->hasAttribute('name')) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'param',
                'attribute' => 'name',
                'reason' => 'missing-object-param-name',
            ], $param);

            return null;
        }

        $name = self::normalizeHtmlObjectParamName($param->getAttribute('name'));
        if ($name === null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'param',
                'attribute' => 'name',
                'reason' => 'invalid-object-param-name',
            ], $param);

            return null;
        }

        $attrs = [
            'data-pandoc-object-param-name' => $name,
        ];

        $valueType = null;
        if ($param->hasAttribute('valuetype')) {
            $valueType = self::normalizeHtmlObjectParamValueType($param->getAttribute('valuetype'));
            if ($valueType === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'param',
                    'attribute' => 'valuetype',
                    'reason' => 'invalid-object-param-valuetype',
                ], $param);
            } else {
                $attrs['data-pandoc-object-param-valuetype'] = $valueType;
            }
        }

        if ($param->hasAttribute('type')) {
            $type = self::normalizeHtmlObjectParamType($param->getAttribute('type'));
            if ($type === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'param',
                    'attribute' => 'type',
                    'reason' => 'invalid-object-param-type',
                ], $param);
            } else {
                $attrs['data-pandoc-object-param-type'] = $type;
            }
        }

        $value = null;
        if ($param->hasAttribute('value')) {
            $value = self::normalizeHtmlObjectParamValue(
                $param->getAttribute('value'),
                $name,
                $valueType,
                $param,
                $diagnostics,
                $baseUrl
            );
            if ($value !== null) {
                $attrs['data-pandoc-object-param-value'] = $value;
            }
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'object-param-review',
            'tag' => 'param',
            'attribute' => $value === null ? 'name' : 'value',
            'metadataAttribute' => $value === null ? 'data-pandoc-object-param-name' : 'data-pandoc-object-param-value',
            'name' => $name,
            'reason' => 'object-param-preserved-as-review-metadata',
        ], $param);

        $label = 'Object parameter: ' . $name;
        if ($value !== null) {
            $label .= '=' . $value;
        }

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $label,
            ]],
        ], $param);
    }

    private static function normalizeHtmlObjectParamName(string $value): ?string
    {
        $name = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($name === '' || strlen($name) > 128 || preg_match('/^[a-z0-9][a-z0-9_.:-]*$/', $name) !== 1) {
            return null;
        }

        return $name;
    }

    private static function normalizeHtmlObjectParamValueType(string $value): ?string
    {
        $valueType = strtolower(self::cleanHtmlMetadataAttribute($value));

        return in_array($valueType, ['data', 'ref', 'object'], true) ? $valueType : null;
    }

    private static function normalizeHtmlObjectParamType(string $value): ?string
    {
        $type = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($type === '' || strlen($type) > 128 || preg_match('/^[a-z0-9][a-z0-9.+-]*\/[a-z0-9][a-z0-9.+-]*(?:;\s*[a-z0-9._-]+=[A-Za-z0-9._:+-]+)*$/', $type) !== 1) {
            return null;
        }

        return $type;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlObjectParamValue(
        string $value,
        string $name,
        ?string $valueType,
        \DOMElement $param,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '' || strlen($cleaned) > 512 || preg_match('/[<>{}`]/u', $cleaned) === 1) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'param',
                'attribute' => 'value',
                'reason' => 'invalid-object-param-value',
            ], $param);

            return null;
        }

        $isUrlValue = $valueType === 'ref' || self::isHtmlObjectParamUrlName($name);
        $normalizedUrl = self::normalizeUrlAttributeValue($cleaned);
        if ($isUrlValue) {
            if ($normalizedUrl === '' || !self::isSafeFetchUrl($normalizedUrl)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-url',
                    'tag' => 'param',
                    'attribute' => 'value',
                    'name' => $name,
                ], $param);

                return null;
            }

            if ($normalizedUrl !== $cleaned) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'normalized-url',
                    'tag' => 'param',
                    'attribute' => 'value',
                    'name' => $name,
                ], $param);
            }

            return $baseUrl === null ? $normalizedUrl : self::resolveRelativeUrl($baseUrl, $normalizedUrl);
        }

        if (preg_match('/^(?:javascript|vbscript|data):/i', $normalizedUrl) === 1 || self::hasUnsafePercentDecodedUrlScheme($normalizedUrl)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'param',
                'attribute' => 'value',
                'name' => $name,
            ], $param);

            return null;
        }

        return $cleaned;
    }

    private static function isHtmlObjectParamUrlName(string $name): bool
    {
        return in_array($name, [
            'archive',
            'base',
            'code',
            'codebase',
            'data',
            'file',
            'href',
            'movie',
            'pluginspage',
            'pluginurl',
            'poster',
            'src',
            'url',
            'uri',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function normalizeHtmlPortalElement(
        \DOMElement $element,
        array &$diagnostics,
        ?string $foreignContext,
        ?string $baseUrl
    ): array {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'blocked-tag',
            'tag' => 'portal',
        ], $element);

        $children = self::normalizeChildren(
            $element,
            'html',
            $diagnostics,
            self::childForeignContext($element, 'portal', $foreignContext),
            $baseUrl
        );
        $portalSource = self::normalizeHtmlPortalSourceElement($element, $diagnostics, $baseUrl);

        return $portalSource === null ? $children : [$portalSource, ...$children];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function normalizeHtmlPortalSourceElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        if (!$element->hasAttribute('src')) {
            return null;
        }

        $target = $element->getAttribute('src');
        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'portal',
                'attribute' => 'src',
            ], $element);

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => 'portal',
                'attribute' => 'src',
            ], $element);
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;
        $title = $element->hasAttribute('title')
            ? self::cleanHtmlMetadataAttribute($element->getAttribute('title'))
            : '';
        $attrs = [
            'href' => $href,
            'data-pandoc-portal-src' => 'true',
        ];
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'portal-source-review',
            'tag' => 'portal',
            'attribute' => 'src',
            'reason' => 'portal-source-preserved-as-review-link',
        ], $element);

        if ($element->hasAttribute('referrerpolicy')) {
            $referrerPolicy = self::normalizeHtmlReferrerPolicyAttribute(
                $element->getAttribute('referrerpolicy'),
                'portal',
                $diagnostics,
                $element
            );
            if ($referrerPolicy !== null) {
                $attrs['data-pandoc-portal-referrerpolicy'] = $referrerPolicy;
                self::addHtmlReferrerPolicyReviewDiagnostic($diagnostics, 'portal', $element);
            }
        }

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $title !== '' ? $title : 'Portal source',
            ]],
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addIframePolicyReviewAttributes(\DOMElement $element, array &$attrs, array &$diagnostics): void
    {
        if ($element->hasAttribute('sandbox')) {
            $sandbox = self::normalizeIframeSandboxAttribute($element->getAttribute('sandbox'), $diagnostics, $element);
            if ($sandbox !== null) {
                $attrs['data-pandoc-iframe-sandbox'] = $sandbox;
            }
        }

        if ($element->hasAttribute('allow')) {
            $allow = self::normalizeIframeAllowAttribute($element->getAttribute('allow'));
            if ($allow !== null) {
                $attrs['data-pandoc-iframe-allow'] = $allow;
            }
        }

        if ($element->hasAttribute('referrerpolicy')) {
            $referrerPolicy = self::normalizeIframeReferrerPolicyAttribute(
                $element->getAttribute('referrerpolicy'),
                $diagnostics,
                $element
            );
            if ($referrerPolicy !== null) {
                $attrs['data-pandoc-iframe-referrerpolicy'] = $referrerPolicy;
            }
        }

        if ($element->hasAttribute('allowfullscreen')) {
            $attrs['data-pandoc-iframe-allowfullscreen'] = 'true';
        }
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeIframeSandboxAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $trimmed = strtolower(trim(str_replace("\0", '', $value)));
        if ($trimmed === '') {
            return '';
        }

        $tokens = preg_split('/[\x00-\x20]+/', $trimmed);
        if (!is_array($tokens)) {
            return null;
        }

        $allowedTokens = [
            'allow-downloads' => true,
            'allow-forms' => true,
            'allow-modals' => true,
            'allow-orientation-lock' => true,
            'allow-pointer-lock' => true,
            'allow-popups' => true,
            'allow-popups-to-escape-sandbox' => true,
            'allow-presentation' => true,
            'allow-same-origin' => true,
            'allow-scripts' => true,
            'allow-storage-access-by-user-activation' => true,
            'allow-top-navigation' => true,
            'allow-top-navigation-by-user-activation' => true,
            'allow-top-navigation-to-custom-protocols' => true,
        ];

        $normalized = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            if (!isset($allowedTokens[$token])) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'iframe',
                    'attribute' => 'sandbox',
                    'token' => $token,
                ], $element);
                continue;
            }
            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        return $normalized === [] ? null : implode(' ', $normalized);
    }

    private static function normalizeIframeAllowAttribute(string $value): ?string
    {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '') {
            return null;
        }

        $directives = [];
        foreach (explode(';', $cleaned) as $directive) {
            $directive = trim($directive);
            if ($directive === '') {
                continue;
            }
            $directive = preg_replace('/[\t\r\n\f ]+/u', ' ', $directive) ?? $directive;
            $directives[] = $directive;
        }

        return $directives === [] ? null : implode('; ', $directives);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeIframeReferrerPolicyAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        return self::normalizeHtmlReferrerPolicyAttribute($value, 'iframe', $diagnostics, $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlReferrerPolicyAttribute(
        string $value,
        string $tagName,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $policy = strtolower(self::cleanHtmlMetadataAttribute($value));
        if (in_array($policy, [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url',
        ], true)) {
            return $policy;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => 'referrerpolicy',
            'value' => $policy,
        ], $element);

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlReferrerPolicyReviewDiagnostic(
        array &$diagnostics,
        string $tagName,
        \DOMElement $element
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'referrer-policy-review',
            'tag' => $tagName,
            'attribute' => 'referrerpolicy',
            'reason' => 'referrer-policy-preserved-as-review-metadata',
        ], $element);
    }

    private static function isHtmlElementReferrerPolicyAttribute(string $tagName, string $name, ?string $foreignContext): bool
    {
        return $foreignContext === null
            && $name === 'referrerpolicy'
            && in_array(strtolower($tagName), ['a', 'img'], true);
    }

    private static function isHtmlImageResourcePolicyAttribute(string $tagName, string $name, ?string $foreignContext): bool
    {
        return $foreignContext === null
            && strtolower($tagName) === 'img'
            && in_array(strtolower($name), ['crossorigin', 'decoding', 'fetchpriority', 'loading'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlImageResourcePolicyAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): array {
        $attribute = strtolower($name);
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));

        $allowedStates = match ($attribute) {
            'crossorigin' => ['anonymous', 'use-credentials'],
            'decoding' => ['async', 'sync', 'auto'],
            'fetchpriority' => ['high', 'low', 'auto'],
            'loading' => ['eager', 'lazy'],
            default => [],
        };
        if ($attribute === 'crossorigin' && $state === '') {
            $state = 'anonymous';
        }

        if (!in_array($state, $allowedStates, true)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attribute,
                'value' => $state,
                'reason' => 'invalid-image-resource-policy-metadata',
            ], $element);

            return [];
        }

        $metadataAttribute = 'data-pandoc-image-' . $attribute;
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'image-resource-policy-review',
            'tag' => $tagName,
            'attribute' => $attribute,
            'metadataAttribute' => $metadataAttribute,
            'state' => $state,
            'reason' => 'image-resource-policy-preserved-as-review-metadata',
        ], $element);

        return [$metadataAttribute => $state];
    }

    private static function isHtmlMediaResourcePolicyAttribute(string $tagName, string $name, ?string $foreignContext): bool
    {
        return $foreignContext === null
            && in_array(strtolower($tagName), ['audio', 'video'], true)
            && in_array(strtolower($name), [
                'autoplay',
                'controls',
                'controlslist',
                'crossorigin',
                'disablepictureinpicture',
                'disableremoteplayback',
                'height',
                'loop',
                'muted',
                'playsinline',
                'preload',
                'width',
            ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlMediaResourcePolicyAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): array {
        $attribute = strtolower($name);
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));

        if (in_array($attribute, ['autoplay', 'controls', 'disablepictureinpicture', 'disableremoteplayback', 'loop', 'muted', 'playsinline'], true)) {
            $metadataAttribute = 'data-pandoc-media-' . $attribute;
            self::addHtmlMediaResourcePolicyReviewDiagnostic($diagnostics, $element, $tagName, $attribute, $metadataAttribute, 'true');

            return [$metadataAttribute => 'true'];
        }

        if ($attribute === 'preload') {
            if ($state === '') {
                $state = 'auto';
            }
            if (!in_array($state, ['auto', 'metadata', 'none'], true)) {
                self::addHtmlInvalidMediaResourcePolicyDiagnostic($diagnostics, $element, $tagName, $attribute, $state);

                return [];
            }

            $metadataAttribute = 'data-pandoc-media-preload';
            self::addHtmlMediaResourcePolicyReviewDiagnostic($diagnostics, $element, $tagName, $attribute, $metadataAttribute, $state);

            return [$metadataAttribute => $state];
        }

        if ($attribute === 'crossorigin') {
            if ($state === '') {
                $state = 'anonymous';
            }
            if (!in_array($state, ['anonymous', 'use-credentials'], true)) {
                self::addHtmlInvalidMediaResourcePolicyDiagnostic($diagnostics, $element, $tagName, $attribute, $state);

                return [];
            }

            $metadataAttribute = 'data-pandoc-media-crossorigin';
            self::addHtmlMediaResourcePolicyReviewDiagnostic($diagnostics, $element, $tagName, $attribute, $metadataAttribute, $state);

            return [$metadataAttribute => $state];
        }

        if ($attribute === 'controlslist') {
            $controls = self::normalizeHtmlMediaControlsListAttribute($value);
            if ($controls === null) {
                self::addHtmlInvalidMediaResourcePolicyDiagnostic($diagnostics, $element, $tagName, $attribute, $state);

                return [];
            }

            $metadataAttribute = 'data-pandoc-media-controlslist';
            self::addHtmlMediaResourcePolicyReviewDiagnostic($diagnostics, $element, $tagName, $attribute, $metadataAttribute, $controls);

            return [$metadataAttribute => $controls];
        }

        $dimension = self::normalizeHtmlMediaDimensionAttribute($value);
        if ($dimension === null) {
            self::addHtmlInvalidMediaResourcePolicyDiagnostic($diagnostics, $element, $tagName, $attribute, $state);

            return [];
        }

        $metadataAttribute = 'data-pandoc-media-' . $attribute;
        self::addHtmlMediaResourcePolicyReviewDiagnostic($diagnostics, $element, $tagName, $attribute, $metadataAttribute, $dimension);

        return [$metadataAttribute => $dimension];
    }

    private static function normalizeHtmlMediaControlsListAttribute(string $value): ?string
    {
        $tokens = preg_split('/[\x00-\x20]+/', strtolower(trim(str_replace("\0", '', $value))), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || $tokens === []) {
            return null;
        }

        $allowed = [
            'nodownload' => true,
            'nofullscreen' => true,
            'noremoteplayback' => true,
        ];
        $normalized = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if (!isset($allowed[$token])) {
                return null;
            }
            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        return implode(' ', $normalized);
    }

    private static function normalizeHtmlMediaDimensionAttribute(string $value): ?string
    {
        $dimension = self::cleanHtmlMetadataAttribute($value);
        if ($dimension === '' || preg_match('/^[0-9]+$/', $dimension) !== 1) {
            return null;
        }

        $dimension = ltrim($dimension, '0');

        return $dimension === '' ? '0' : $dimension;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlMediaResourcePolicyReviewDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $tagName,
        string $attributeName,
        string $metadataAttribute,
        string $state
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'media-resource-policy-review',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'state' => $state,
            'reason' => 'media-resource-policy-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidMediaResourcePolicyDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $tagName,
        string $attributeName,
        string $state
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'value' => $state,
            'reason' => 'invalid-media-resource-policy-metadata',
        ], $element);
    }

    /**
     * @param array<string, mixed> $element
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function normalizeHtmlTableElement(array $element, array &$diagnostics): array
    {
        [$fostered, $children] = self::normalizeHtmlTableChildren(
            is_array($element['children'] ?? null) ? $element['children'] : [],
            (string) ($element['name'] ?? ''),
            $diagnostics
        );
        $element['children'] = $children;

        return [...$fostered, $element];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:list<array<string, mixed>>, 1:list<array<string, mixed>>}
     */
    private static function normalizeHtmlTableChildren(array $children, string $context, array &$diagnostics): array
    {
        $fostered = [];
        $cleanChildren = [];
        $pendingCells = [];
        $pendingColumns = [];

        $flushPendingCells = static function () use (&$pendingCells, &$cleanChildren): void {
            if ($pendingCells === []) {
                return;
            }

            $cleanChildren[] = self::generatedHtmlTableRow($pendingCells);
            $pendingCells = [];
        };
        $flushPendingColumns = static function () use (&$pendingColumns, &$cleanChildren): void {
            if ($pendingColumns === []) {
                return;
            }

            $cleanChildren[] = self::generatedHtmlTableColumnGroup($pendingColumns);
            $pendingColumns = [];
        };

        foreach ($children as $child) {
            if (self::isHtmlTableColumnNode($child) && strtolower($context) === 'table') {
                $flushPendingCells();
                $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
                    'code' => 'table-orphan-column-repaired',
                    'context' => 'table',
                    'tag' => 'col',
                    'reason' => 'table-column-wrapped-in-generated-colgroup',
                ], $child);
                $pendingColumns[] = $child;
                continue;
            }

            if (self::isHtmlTableCellNode($child) && self::allowsGeneratedHtmlTableRows($context)) {
                $flushPendingColumns();
                $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
                    'code' => 'table-orphan-cell-repaired',
                    'context' => strtolower($context),
                    'tag' => (string) ($child['name'] ?? ''),
                    'reason' => 'table-cell-wrapped-in-generated-row',
                ], $child);
                $pendingCells[] = $child;
                continue;
            }

            $flushPendingCells();
            $flushPendingColumns();

            if (self::isFosteredHtmlTableNode($child, $context)) {
                $diagnostics[] = self::diagnosticWithNormalizedNodeLine(
                    self::htmlTableFosterDiagnostic($child, $context),
                    $child
                );
                $fostered[] = $child;
                continue;
            }

            if (($child['type'] ?? '') === 'element' && self::isHtmlTableModelContext((string) ($child['name'] ?? ''))) {
                [$nestedFostered, $cleanChild] = self::normalizeHtmlTableElementParts($child, $diagnostics);
                array_push($fostered, ...$nestedFostered);
                $cleanChildren[] = $cleanChild;
                continue;
            }

            $cleanChildren[] = $child;
        }

        $flushPendingCells();
        $flushPendingColumns();

        return [$fostered, $cleanChildren];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function wrapOrphanHtmlTableStructure(array $nodes, array &$diagnostics): array
    {
        $wrapped = [];
        $pendingTableChildren = [];
        $pendingRows = [];
        $pendingCells = [];
        $pendingColumns = [];

        $flushPendingCells = static function () use (&$pendingCells, &$pendingRows): void {
            if ($pendingCells === []) {
                return;
            }

            $pendingRows[] = self::generatedHtmlTableRow($pendingCells);
            $pendingCells = [];
        };

        $flushPendingRows = static function () use (&$pendingRows, &$pendingTableChildren): void {
            if ($pendingRows === []) {
                return;
            }

            array_push($pendingTableChildren, ...$pendingRows);
            $pendingRows = [];
        };
        $flushPendingColumns = static function () use (&$pendingColumns, &$pendingTableChildren): void {
            if ($pendingColumns === []) {
                return;
            }

            $pendingTableChildren[] = self::generatedHtmlTableColumnGroup($pendingColumns);
            $pendingColumns = [];
        };
        $flushPendingTable = static function () use (
            &$pendingTableChildren,
            &$pendingRows,
            &$pendingCells,
            &$pendingColumns,
            &$wrapped,
            $flushPendingCells,
            $flushPendingRows,
            $flushPendingColumns
        ): void {
            $flushPendingColumns();
            $flushPendingCells();
            $flushPendingRows();
            if ($pendingTableChildren === []) {
                return;
            }

            $wrapped[] = self::generatedHtmlTable($pendingTableChildren);
            $pendingTableChildren = [];
        };

        foreach ($nodes as $node) {
            if (self::isHtmlTableColumnNode($node)) {
                $flushPendingCells();
                $flushPendingRows();
                $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
                    'code' => 'table-orphan-column-repaired',
                    'context' => 'fragment',
                    'tag' => 'col',
                    'reason' => 'orphan-table-column-wrapped-in-generated-table-colgroup',
                ], $node);
                $pendingColumns[] = $node;
                continue;
            }

            if (self::isHtmlTableContainerNode($node)) {
                $flushPendingColumns();
                $flushPendingCells();
                $flushPendingRows();
                [$fostered, $cleanNode] = self::normalizeHtmlTableOrphanContainer($node, $diagnostics);
                if ($fostered !== []) {
                    $flushPendingTable();
                    array_push($wrapped, ...$fostered);
                }
                $diagnostics[] = self::diagnosticWithNormalizedNodeLine(
                    self::htmlTableOrphanContainerDiagnostic($cleanNode),
                    $cleanNode
                );
                $pendingTableChildren[] = $cleanNode;
                continue;
            }

            if (self::isHtmlTableRowNode($node)) {
                $flushPendingColumns();
                $flushPendingCells();
                $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
                    'code' => 'table-orphan-row-repaired',
                    'context' => 'fragment',
                    'tag' => 'tr',
                    'reason' => 'orphan-table-row-wrapped-in-generated-table',
                ], $node);
                $pendingRows[] = $node;
                continue;
            }

            if (self::isHtmlTableCellNode($node)) {
                $flushPendingColumns();
                $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
                    'code' => 'table-orphan-cell-repaired',
                    'context' => 'fragment',
                    'tag' => (string) ($node['name'] ?? ''),
                    'reason' => 'orphan-table-cell-wrapped-in-generated-table-row',
                ], $node);
                $pendingCells[] = $node;
                continue;
            }

            $flushPendingTable();
            $wrapped[] = $node;
        }

        $flushPendingTable();

        return $wrapped;
    }

    private static function isDirectHtmlTableContext(\DOMNode $parent): bool
    {
        return $parent instanceof \DOMElement
            && self::isHtmlTableModelContext(strtolower($parent->tagName));
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function isHtmlTableRowNode(array $node): bool
    {
        return ($node['type'] ?? '') === 'element'
            && strtolower((string) ($node['name'] ?? '')) === 'tr';
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function isHtmlTableCellNode(array $node): bool
    {
        if (($node['type'] ?? '') !== 'element') {
            return false;
        }

        return in_array(strtolower((string) ($node['name'] ?? '')), ['td', 'th'], true);
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function isHtmlTableColumnNode(array $node): bool
    {
        return ($node['type'] ?? '') === 'element'
            && strtolower((string) ($node['name'] ?? '')) === 'col';
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function isHtmlTableContainerNode(array $node): bool
    {
        if (($node['type'] ?? '') !== 'element') {
            return false;
        }

        return in_array(
            strtolower((string) ($node['name'] ?? '')),
            ['caption', 'colgroup', 'thead', 'tbody', 'tfoot'],
            true
        );
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, string>
     */
    private static function htmlTableOrphanContainerDiagnostic(array $node): array
    {
        $name = strtolower((string) ($node['name'] ?? ''));

        return match ($name) {
            'caption' => [
                'code' => 'table-orphan-caption-repaired',
                'context' => 'fragment',
                'tag' => 'caption',
                'reason' => 'orphan-table-caption-wrapped-in-generated-table',
            ],
            'colgroup' => [
                'code' => 'table-orphan-column-group-repaired',
                'context' => 'fragment',
                'tag' => 'colgroup',
                'reason' => 'orphan-table-column-group-wrapped-in-generated-table',
            ],
            default => [
                'code' => 'table-orphan-section-repaired',
                'context' => 'fragment',
                'tag' => $name,
                'reason' => 'orphan-table-section-wrapped-in-generated-table',
            ],
        };
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:list<array<string, mixed>>, 1:array<string, mixed>}
     */
    private static function normalizeHtmlTableOrphanContainer(array $node, array &$diagnostics): array
    {
        if (!self::isHtmlTableModelContext((string) ($node['name'] ?? ''))) {
            return [[], $node];
        }

        return self::normalizeHtmlTableElementParts($node, $diagnostics);
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return array<string, mixed>
     */
    private static function generatedHtmlTableRow(array $cells): array
    {
        $row = [
            'type' => 'element',
            'name' => 'tr',
            'attrs' => [],
            'children' => $cells,
        ];
        if (isset($cells[0]['line']) && is_int($cells[0]['line'])) {
            $row['line'] = $cells[0]['line'];
        }

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    private static function generatedHtmlTableColumnGroup(array $columns): array
    {
        $colgroup = [
            'type' => 'element',
            'name' => 'colgroup',
            'attrs' => [],
            'children' => $columns,
        ];
        if (isset($columns[0]['line']) && is_int($columns[0]['line'])) {
            $colgroup['line'] = $columns[0]['line'];
        }

        return $colgroup;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private static function generatedHtmlTable(array $children): array
    {
        $table = [
            'type' => 'element',
            'name' => 'table',
            'attrs' => [],
            'children' => $children,
        ];
        if (isset($children[0]['line']) && is_int($children[0]['line'])) {
            $table['line'] = $children[0]['line'];
        }

        return $table;
    }

    private static function allowsGeneratedHtmlTableRows(string $context): bool
    {
        return in_array(strtolower($context), ['table', 'thead', 'tbody', 'tfoot'], true);
    }

    /**
     * @param array<string, mixed> $element
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:list<array<string, mixed>>, 1:array<string, mixed>}
     */
    private static function normalizeHtmlTableElementParts(array $element, array &$diagnostics): array
    {
        [$fostered, $children] = self::normalizeHtmlTableChildren(
            is_array($element['children'] ?? null) ? $element['children'] : [],
            (string) ($element['name'] ?? ''),
            $diagnostics
        );
        $element['children'] = $children;

        return [$fostered, $element];
    }

    private static function isHtmlTableModelContext(string $name): bool
    {
        return isset(self::HTML5_TABLE_ALLOWED_CHILDREN[strtolower($name)]);
    }

    /**
     * @param array<string, string> $attrs
     */
    private static function isEmptyHtmlMediaSourceElement(\DOMElement $element, string $name, array $attrs): bool
    {
        return self::hasHtmlAncestor($element, 'picture')
            && strtolower($name) === 'source'
            && !array_key_exists('src', $attrs)
            && !array_key_exists('srcset', $attrs);
    }

    private static function hasHtmlMediaSourceContext(\DOMElement $element): bool
    {
        return self::hasHtmlAncestor($element, 'audio')
            || self::hasHtmlAncestor($element, 'picture')
            || self::hasHtmlAncestor($element, 'video');
    }

    private static function hasHtmlAncestor(\DOMElement $element, string $name): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower($parent->tagName) === strtolower($name)) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function isFosteredHtmlTableNode(array $node, string $context): bool
    {
        $allowed = self::HTML5_TABLE_ALLOWED_CHILDREN[strtolower($context)] ?? null;
        if ($allowed === null) {
            return false;
        }

        $type = (string) ($node['type'] ?? '');
        if ($type === 'text') {
            return preg_match('/\S/u', (string) ($node['text'] ?? '')) === 1;
        }
        if ($type !== 'element') {
            return false;
        }

        return !isset($allowed[strtolower((string) ($node['name'] ?? ''))]);
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function htmlTableFosterDiagnostic(array $node, string $context): array
    {
        $diagnostic = [
            'code' => 'table-foster-parented-content',
            'context' => strtolower($context),
            'nodeType' => (string) ($node['type'] ?? ''),
        ];

        if (($node['type'] ?? '') === 'element') {
            $diagnostic['tag'] = (string) ($node['name'] ?? '');
        }

        return $diagnostic;
    }

    private static function rawElementName(\DOMElement $element, string $mode): string
    {
        if ($mode === 'xml') {
            return $element->tagName;
        }

        return strtolower($element->tagName);
    }

    private static function normalizedElementName(string $rawName, ?string $foreignContext): string
    {
        return $foreignContext !== null
            ? XmlHtmlDom::adjustHtmlForeignElementName($rawName, $foreignContext)
            : $rawName;
    }

    private static function elementForeignContext(
        \DOMElement $element,
        string $rawName,
        string $mode,
        ?string $parentForeignContext
    ): ?string
    {
        if ($mode !== 'html') {
            return null;
        }
        if ($rawName === 'svg' || $rawName === 'math') {
            return $rawName;
        }
        if (
            $parentForeignContext === null
            && self::isMathMlTextIntegrationExceptionName($rawName)
            && self::hasMathMlTextIntegrationPointParent($element)
        ) {
            return 'math';
        }

        return $parentForeignContext;
    }

    private static function hasMathMlTextIntegrationPointParent(\DOMElement $element): bool
    {
        $parent = $element->parentNode;
        if (
            !$parent instanceof \DOMElement
            || !self::isMathMlTextIntegrationPointName(strtolower($parent->localName))
        ) {
            return false;
        }

        $node = $parent;
        $isSelf = true;
        while ($node instanceof \DOMElement) {
            $name = strtolower($node->localName);
            if (!$isSelf && self::isHtmlIntegrationPointElement($node)) {
                return false;
            }
            if ($name === 'math') {
                return true;
            }
            if ($name === 'html' || $name === 'body') {
                return false;
            }

            $ancestor = $node->parentNode;
            $node = $ancestor instanceof \DOMElement ? $ancestor : null;
            $isSelf = false;
        }

        return false;
    }

    private static function childForeignContext(\DOMElement $element, string $rawName, ?string $elementForeignContext): ?string
    {
        if ($elementForeignContext === 'svg' && self::isSvgHtmlIntegrationPointName($rawName)) {
            return null;
        }
        if ($elementForeignContext === 'math' && self::isMathMlTextIntegrationPointName($rawName)) {
            return null;
        }
        if ($elementForeignContext === 'math' && $rawName === 'annotation-xml') {
            $encoding = strtolower(trim($element->getAttribute('encoding')));
            if ($encoding === 'text/html' || $encoding === 'application/xhtml+xml') {
                return null;
            }
        }

        return $elementForeignContext;
    }

    private static function isHtmlIntegrationPointElement(\DOMElement $element): bool
    {
        $name = strtolower($element->localName);
        if (self::isSvgHtmlIntegrationPointName($name)) {
            return true;
        }
        if (self::isMathMlTextIntegrationPointName($name)) {
            return true;
        }
        if ($name !== 'annotation-xml') {
            return false;
        }

        $encoding = strtolower(trim($element->getAttribute('encoding')));

        return $encoding === 'text/html' || $encoding === 'application/xhtml+xml';
    }

    private static function isSvgHtmlIntegrationPointName(string $name): bool
    {
        return in_array($name, ['foreignobject', 'desc', 'title'], true);
    }

    private static function isMathMlTextIntegrationPointName(string $name): bool
    {
        return in_array($name, ['mi', 'mn', 'mo', 'ms', 'mtext'], true);
    }

    private static function isMathMlTextIntegrationExceptionName(string $name): bool
    {
        return $name === 'mglyph' || $name === 'malignmark';
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function nodeWithSourceLine(array $node, \DOMNode $sourceNode): array
    {
        $line = $sourceNode->getLineNo();
        if ($line > 0) {
            $node['line'] = $line;
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @return array<string, mixed>
     */
    private static function diagnosticWithSourceLine(array $diagnostic, \DOMNode $sourceNode): array
    {
        $line = $sourceNode->getLineNo();
        if ($line > 0) {
            $diagnostic['line'] = $line;
        }

        return $diagnostic;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function diagnosticWithNormalizedNodeLine(array $diagnostic, array $node): array
    {
        $line = $node['line'] ?? null;
        if (is_int($line) && $line > 0) {
            $diagnostic['line'] = $line;
        }

        return $diagnostic;
    }

    private static function isSafeElementName(string $name): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9:._-]*$/', $name) === 1;
    }

    private static function isBlockedElement(string $name): bool
    {
        return in_array(strtolower($name), [
            'applet',
            'base',
            'button',
            'embed',
            'form',
            'frame',
            'frameset',
            'iframe',
            'input',
            'link',
            'meta',
            'object',
            'option',
            'param',
            'script',
            'select',
            'style',
            'textarea',
        ], true);
    }

    private static function isUnwrappedElement(string $name): bool
    {
        return in_array(strtolower($name), [
            'button',
            'canvas',
            'datalist',
            'form',
            'applet',
            'iframe',
            'map',
            'noembed',
            'noframes',
            'noscript',
            'object',
            'optgroup',
            'option',
            'plaintext',
            'select',
            'textarea',
            'template',
            'xmp',
        ], true);
    }

    private static function visibleInputLabel(\DOMElement $element): ?string
    {
        $type = strtolower(trim($element->getAttribute('type')));
        $attribute = match ($type) {
            'button', 'reset', 'submit' => 'value',
            'image' => 'alt',
            default => null,
        };
        if ($attribute === null || !$element->hasAttribute($attribute)) {
            return null;
        }

        $label = str_replace("\0", '', $element->getAttribute($attribute));

        return trim($label) === '' ? null : $label;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return list<array<string, mixed>>
     */
    private static function withVisibleFormChoiceLabel(\DOMElement $element, string $name, array $children): array
    {
        if ($name !== 'option' && $name !== 'optgroup') {
            return $children;
        }

        $label = self::visibleFormChoiceLabel($element);
        if ($label === null) {
            return $children;
        }

        $labelNode = [
            'type' => 'text',
            'text' => $label,
        ];

        return $name === 'option'
            ? [$labelNode]
            : [$labelNode, ...$children];
    }

    private static function visibleFormChoiceLabel(\DOMElement $element): ?string
    {
        if (!$element->hasAttribute('label')) {
            return null;
        }

        $label = str_replace("\0", '', $element->getAttribute('label'));

        return trim($label) === '' ? null : $label;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeHtmlAreaElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'blocked-tag',
            'tag' => 'area',
        ], $element);

        foreach (['download', 'ping', 'target'] as $attributeName) {
            if ($element->hasAttribute($attributeName)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'area',
                    'attribute' => $attributeName,
                ], $element);
            }
        }

        if (!$element->hasAttribute('href')) {
            return null;
        }

        $target = $element->getAttribute('href');
        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeUrl($normalizedTarget)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'area',
                'attribute' => 'href',
            ], $element);

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => 'area',
                'attribute' => 'href',
            ], $element);
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;
        $attrs = [
            'href' => $href,
            'data-pandoc-image-map-area' => 'true',
        ];

        $mapName = self::nearestHtmlMapName($element);
        if ($mapName !== null) {
            $attrs['data-pandoc-image-map-name'] = $mapName;
        }

        if ($element->hasAttribute('shape')) {
            $shape = self::normalizeHtmlAreaShapeAttribute($element->getAttribute('shape'), $diagnostics, $element);
            if ($shape !== null) {
                $attrs['data-pandoc-image-map-shape'] = $shape;
            }
        }

        if ($element->hasAttribute('coords')) {
            $coords = self::normalizeHtmlAreaCoordsAttribute($element->getAttribute('coords'), $diagnostics, $element);
            if ($coords !== null) {
                $attrs['data-pandoc-image-map-coords'] = $coords;
            }
        }

        $label = null;
        if ($element->hasAttribute('alt')) {
            $alt = self::cleanHtmlMetadataAttribute($element->getAttribute('alt'));
            if ($alt !== '') {
                $attrs['data-pandoc-image-map-alt'] = $alt;
                $label = $alt;
            }
        }

        if ($element->hasAttribute('title')) {
            $title = self::cleanHtmlMetadataAttribute($element->getAttribute('title'));
            if ($title !== '') {
                $attrs['title'] = $title;
                $label ??= $title;
            }
        }

        if ($element->hasAttribute('rel')) {
            $rel = self::normalizeHtmlRelAttribute($element->getAttribute('rel'), 'area', $diagnostics);
            if ($rel !== null) {
                $attrs['rel'] = $rel;
            }
        }

        if ($element->hasAttribute('referrerpolicy')) {
            $referrerPolicy = self::normalizeHtmlReferrerPolicyAttribute(
                $element->getAttribute('referrerpolicy'),
                'area',
                $diagnostics,
                $element
            );
            if ($referrerPolicy !== null) {
                $attrs['data-pandoc-referrerpolicy'] = $referrerPolicy;
                self::addHtmlReferrerPolicyReviewDiagnostic($diagnostics, 'area', $element);
            }
        }

        return [self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $label ?? 'Image map region',
            ]],
        ], $element)];
    }

    private static function nearestHtmlMapName(\DOMElement $element): ?string
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower($parent->tagName) === 'map') {
                foreach (['name', 'id'] as $attributeName) {
                    if (!$parent->hasAttribute($attributeName)) {
                        continue;
                    }

                    $name = self::cleanHtmlMetadataAttribute($parent->getAttribute($attributeName));
                    if ($name !== '') {
                        return $name;
                    }
                }

                return null;
            }

            $parent = $parent->parentNode;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAreaShapeAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $shape = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($shape === '') {
            return null;
        }

        if (in_array($shape, ['circle', 'default', 'poly', 'rect'], true)) {
            return $shape;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => 'area',
            'attribute' => 'shape',
            'value' => $shape,
        ], $element);

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAreaCoordsAttribute(
        string $value,
        array &$diagnostics,
        \DOMElement $element
    ): ?string {
        $cleaned = trim(str_replace("\0", '', $value));
        if ($cleaned === '') {
            return null;
        }

        $parts = preg_split('/[\s,]+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || $parts === []) {
            return null;
        }

        $coords = [];
        foreach ($parts as $part) {
            $coord = self::normalizeHtmlAreaCoord((string) $part);
            if ($coord === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'area',
                    'attribute' => 'coords',
                    'value' => $cleaned,
                ], $element);

                return null;
            }

            $coords[] = $coord;
        }

        return implode(',', $coords);
    }

    private static function normalizeHtmlAreaCoord(string $value): ?string
    {
        $trimmed = trim($value);
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $trimmed) !== 1) {
            return null;
        }

        if (str_contains($trimmed, '.')) {
            [$integer, $fraction] = explode('.', $trimmed, 2);
            $integer = ltrim($integer, '0');
            $fraction = rtrim($fraction, '0');

            return ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);
        }

        $integer = ltrim($trimmed, '0');

        return $integer === '' ? '0' : $integer;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeHtmlLinkElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'blocked-tag',
            'tag' => 'link',
        ], $element);

        $relations = self::htmlLinkRelationTokens($element);
        $reviewRelations = self::reviewableHtmlLinkRelations($relations);
        if ($reviewRelations === [] || self::hasActiveHtmlLinkResourceRelation($relations) || !$element->hasAttribute('href')) {
            return null;
        }

        $target = $element->getAttribute('href');
        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'link',
                'attribute' => 'href',
            ], $element);

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => 'link',
                'attribute' => 'href',
            ], $element);
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;

        $attrs = [
            'href' => $href,
            'data-pandoc-link-rel' => implode(' ', $reviewRelations),
        ];

        foreach (['hreflang', 'type', 'title'] as $attributeName) {
            if (!$element->hasAttribute($attributeName)) {
                continue;
            }

            $value = self::cleanHtmlMetadataAttribute($element->getAttribute($attributeName));
            if ($value !== '') {
                $attrs[$attributeName] = $value;
            }
        }

        if ($element->hasAttribute('referrerpolicy')) {
            $referrerPolicy = self::normalizeHtmlReferrerPolicyAttribute(
                $element->getAttribute('referrerpolicy'),
                'link',
                $diagnostics,
                $element
            );
            if ($referrerPolicy !== null) {
                $attrs['data-pandoc-referrerpolicy'] = $referrerPolicy;
                self::addHtmlReferrerPolicyReviewDiagnostic($diagnostics, 'link', $element);
            }
        }

        return [self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => self::htmlLinkReviewLabel($reviewRelations, $attrs),
            ]],
        ], $element)];
    }

    /**
     * @return list<string>
     */
    private static function htmlLinkRelationTokens(\DOMElement $element): array
    {
        $rel = strtolower(trim($element->getAttribute('rel')));
        if ($rel === '') {
            return [];
        }

        $tokens = preg_split('/[\x00-\x20]+/', $rel);
        if (!is_array($tokens)) {
            return [];
        }

        $relations = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '' || in_array($token, $relations, true)) {
                continue;
            }

            $relations[] = $token;
        }

        return $relations;
    }

    /**
     * @param list<string> $relations
     * @return list<string>
     */
    private static function reviewableHtmlLinkRelations(array $relations): array
    {
        $reviewable = [];
        foreach ([
            'canonical',
            'alternate',
            'shortlink',
            'author',
            'license',
            'help',
            'bookmark',
            'prev',
            'previous',
            'next',
            'contents',
            'index',
            'search',
            'up',
            'start',
            'chapter',
            'section',
            'subsection',
            'appendix',
            'glossary',
        ] as $relation) {
            if (in_array($relation, $relations, true)) {
                $reviewable[] = $relation;
            }
        }

        return $reviewable;
    }

    /**
     * @param list<string> $relations
     */
    private static function hasActiveHtmlLinkResourceRelation(array $relations): bool
    {
        foreach ($relations as $relation) {
            if (in_array($relation, [
                'apple-touch-icon',
                'apple-touch-startup-image',
                'dns-prefetch',
                'icon',
                'import',
                'manifest',
                'mask-icon',
                'modulepreload',
                'preconnect',
                'prefetch',
                'preload',
                'prerender',
                'stylesheet',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private static function cleanHtmlMetadataAttribute(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\t\r\n\f ]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param list<string> $relations
     * @param array<string, string> $attrs
     */
    private static function htmlLinkReviewLabel(array $relations, array $attrs): string
    {
        $title = $attrs['title'] ?? '';
        if ($title !== '') {
            return $title;
        }

        return match ($relations[0] ?? '') {
            'canonical' => 'Canonical source',
            'alternate' => 'Alternate source',
            'shortlink' => 'Shortlink',
            'author' => 'Author source',
            'license' => 'License source',
            'help' => 'Help source',
            'bookmark' => 'Bookmark source',
            'prev', 'previous' => 'Previous source',
            'next' => 'Next source',
            'contents' => 'Contents source',
            'index' => 'Index source',
            'search' => 'Search source',
            'up' => 'Parent source',
            'start' => 'Start source',
            'chapter' => 'Chapter source',
            'section' => 'Section source',
            'subsection' => 'Subsection source',
            'appendix' => 'Appendix source',
            'glossary' => 'Glossary source',
            default => 'Linked source',
        };
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeHtmlMetaElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'blocked-tag',
            'tag' => 'meta',
        ], $element);

        $target = self::htmlMetaRefreshTarget($element);
        if ($target === null) {
            $charsetMetadata = self::htmlMetaCharsetMetadata($element);
            if ($charsetMetadata !== null) {
                [$source, $charset] = $charsetMetadata;

                return [self::nodeWithSourceLine([
                    'type' => 'element',
                    'name' => 'span',
                    'attrs' => [
                        'data-pandoc-meta-charset' => $charset,
                        'data-pandoc-meta-source' => $source,
                    ],
                    'children' => [[
                        'type' => 'text',
                        'text' => 'Charset: ' . $charset,
                    ]],
                ], $element)];
            }

            $urlMetadata = self::htmlMetaUrlMetadata($element);
            if ($urlMetadata !== null) {
                [$kind, $name, $target] = $urlMetadata;
                $normalizedTarget = self::normalizeUrlAttributeValue($target);
                if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'unsafe-url',
                        'tag' => 'meta',
                        'attribute' => 'content',
                    ], $element);

                    return null;
                }

                if ($normalizedTarget !== $target) {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'normalized-url',
                        'tag' => 'meta',
                        'attribute' => 'content',
                    ], $element);
                }

                $href = $baseUrl !== null
                    ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
                    : $normalizedTarget;
                $metadataAttributeName = $kind === 'property'
                    ? 'data-pandoc-meta-property'
                    : 'data-pandoc-meta-name';
                $attrs = [
                    'href' => $href,
                    $metadataAttributeName => $name,
                    'data-pandoc-meta-content' => $href,
                    'data-pandoc-meta-url' => 'true',
                ];
                foreach (self::htmlMetaMicrodataMetadataAttributes($element, $diagnostics, $href) as $microdataName => $microdataValue) {
                    $attrs[$microdataName] = $microdataValue;
                }

                return [self::nodeWithSourceLine([
                    'type' => 'element',
                    'name' => 'a',
                    'attrs' => $attrs,
                    'children' => [[
                        'type' => 'text',
                        'text' => self::htmlMetaReviewLabel($name),
                    ]],
                ], $element)];
            }

            $policyMetadata = self::htmlMetaPolicyMetadata($element, $diagnostics);
            if ($policyMetadata !== null) {
                [$kind, $name, $content] = $policyMetadata;
                $metadataAttributeName = $kind === 'http-equiv'
                    ? 'data-pandoc-meta-http-equiv'
                    : 'data-pandoc-meta-name';
                $attrs = [
                    $metadataAttributeName => $name,
                    'data-pandoc-meta-content' => $content,
                ];
                foreach (self::htmlMetaMicrodataMetadataAttributes($element, $diagnostics, $content) as $microdataName => $microdataValue) {
                    $attrs[$microdataName] = $microdataValue;
                }

                return [self::nodeWithSourceLine([
                    'type' => 'element',
                    'name' => 'span',
                    'attrs' => $attrs,
                    'children' => [[
                        'type' => 'text',
                        'text' => self::htmlMetaReviewLabel($name) . ': ' . $content,
                    ]],
                ], $element)];
            }

            $crawlerMetadata = self::htmlMetaCrawlerMetadata($element, $diagnostics);
            if ($crawlerMetadata !== null) {
                [$kind, $name, $content] = $crawlerMetadata;
                $metadataAttributeName = $kind === 'property'
                    ? 'data-pandoc-meta-property'
                    : 'data-pandoc-meta-name';
                $attrs = [
                    $metadataAttributeName => $name,
                    'data-pandoc-meta-content' => $content,
                ];
                foreach (self::htmlMetaMicrodataMetadataAttributes($element, $diagnostics, $content) as $microdataName => $microdataValue) {
                    $attrs[$microdataName] = $microdataValue;
                }

                return [self::nodeWithSourceLine([
                    'type' => 'element',
                    'name' => 'span',
                    'attrs' => $attrs,
                    'children' => [[
                        'type' => 'text',
                        'text' => self::htmlMetaReviewLabel($name) . ': ' . $content,
                    ]],
                ], $element)];
            }

            $reviewMetadata = self::htmlMetaReviewMetadata($element, $diagnostics);
            if ($reviewMetadata === null) {
                $microdataMetadata = self::htmlMetaMicrodataMetadataNode($element, $diagnostics);
                if ($microdataMetadata !== null) {
                    return [$microdataMetadata];
                }

                return null;
            }

            [$kind, $name, $content, $metadataAttrs] = $reviewMetadata;
            $metadataAttributeName = $kind === 'property'
                ? 'data-pandoc-meta-property'
                : 'data-pandoc-meta-name';
            $attrs = [
                $metadataAttributeName => $name,
                'data-pandoc-meta-content' => $content,
            ];
            foreach ($metadataAttrs as $metadataName => $metadataValue) {
                $attrs[$metadataName] = $metadataValue;
            }
            foreach (self::htmlMetaMicrodataMetadataAttributes($element, $diagnostics, $content) as $microdataName => $microdataValue) {
                $attrs[$microdataName] = $microdataValue;
            }

            return [self::nodeWithSourceLine([
                'type' => 'element',
                'name' => 'span',
                'attrs' => $attrs,
                'children' => [[
                    'type' => 'text',
                    'text' => self::htmlMetaReviewLabel($name) . ': ' . $content,
                ]],
            ], $element)];
        }

        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'meta',
                'attribute' => 'content',
            ], $element);

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => 'meta',
                'attribute' => 'content',
            ], $element);
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;
        $attrs = [
            'href' => $href,
            'data-pandoc-meta-refresh' => 'true',
        ];
        foreach (self::htmlMetaMicrodataMetadataAttributes($element, $diagnostics, $href) as $microdataName => $microdataValue) {
            $attrs[$microdataName] = $microdataValue;
        }

        return [self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [
                [
                    'type' => 'text',
                    'text' => 'Refresh target',
                ],
            ],
        ], $element)];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function htmlMetaMicrodataMetadataNode(\DOMElement $element, array &$diagnostics): ?array
    {
        $attrs = self::htmlMetaMicrodataMetadataAttributes($element, $diagnostics);
        if ($attrs === []) {
            return null;
        }

        $properties = $attrs['data-pandoc-microdata-property'];
        $content = $attrs['data-pandoc-microdata-value'];

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => 'Microdata ' . $properties . ': ' . $content,
            ]],
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function htmlMetaMicrodataMetadataAttributes(
        \DOMElement $element,
        array &$diagnostics,
        ?string $contentValue = null
    ): array {
        if (!$element->hasAttribute('itemprop')) {
            return [];
        }

        $properties = self::normalizeHtmlSemanticTermTokenList(
            $element->getAttribute('itemprop'),
            'meta',
            'itemprop',
            $element,
            $diagnostics
        );
        if ($properties === null) {
            return [];
        }

        $content = $contentValue !== null
            ? self::normalizeHtmlMicrodataItemValue($contentValue)
            : ($element->hasAttribute('content')
                ? self::normalizeHtmlMicrodataItemValue($element->getAttribute('content'))
                : null);
        if ($content === null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'meta',
                'attribute' => 'content',
                'reason' => 'invalid-microdata-meta-content',
            ], $element);

            return [];
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'microdata-value-review',
            'tag' => 'meta',
            'attribute' => 'content',
            'metadataAttribute' => 'data-pandoc-microdata-value',
            'reason' => 'microdata-meta-content-preserved-as-review-metadata',
        ], $element);

        return [
            'data-pandoc-microdata-property' => $properties,
            'data-pandoc-microdata-value' => $content,
            'data-pandoc-microdata-source' => 'meta',
        ];
    }

    private static function htmlMetaRefreshTarget(\DOMElement $element): ?string
    {
        if (strcasecmp(trim($element->getAttribute('http-equiv')), 'refresh') !== 0) {
            return null;
        }
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $content = $element->getAttribute('content');
        if (preg_match('/(?:^|;)\s*url\s*=\s*(.+)\s*$/is', $content, $matches) !== 1) {
            return null;
        }

        $target = trim((string) $matches[1]);
        if (strlen($target) >= 2) {
            $quote = $target[0];
            if (($quote === '"' || $quote === "'") && str_ends_with($target, $quote)) {
                $target = substr($target, 1, -1);
            }
        }

        return $target;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeHtmlTitleElement(\DOMElement $element, array &$diagnostics): ?array
    {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'blocked-tag',
            'tag' => 'title',
        ], $element);

        $title = self::cleanHtmlMetadataAttribute($element->textContent);
        if ($title === '') {
            return null;
        }

        return [self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => [
                'data-pandoc-meta-name' => 'title',
                'data-pandoc-meta-source' => 'title',
                'data-pandoc-meta-content' => $title,
            ],
            'children' => [[
                'type' => 'text',
                'text' => 'Title: ' . $title,
            ]],
        ], $element)];
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private static function htmlMetaCharsetMetadata(\DOMElement $element): ?array
    {
        if ($element->hasAttribute('charset')) {
            $charset = self::normalizeHtmlCharsetLabel($element->getAttribute('charset'));

            return $charset === null ? null : ['charset', $charset];
        }

        if (strcasecmp(self::cleanHtmlMetadataAttribute($element->getAttribute('http-equiv')), 'content-type') !== 0) {
            return null;
        }
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $content = $element->getAttribute('content');
        if (preg_match('/(?:^|;)\s*charset\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^;\s]+))/i', $content, $matches) !== 1) {
            return null;
        }

        $label = '';
        foreach ([1, 2, 3] as $index) {
            if (($matches[$index] ?? '') === '') {
                continue;
            }

            $label = (string) $matches[$index];
            break;
        }
        $charset = self::normalizeHtmlCharsetLabel($label);

        return $charset === null ? null : ['content-type', $charset];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string, 2:string}|null
     */
    private static function htmlMetaPolicyMetadata(\DOMElement $element, array &$diagnostics): ?array
    {
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $httpEquiv = strtolower(self::cleanHtmlMetadataAttribute($element->getAttribute('http-equiv')));
        if ($httpEquiv === 'content-security-policy') {
            $policy = self::normalizeHtmlContentSecurityPolicy($element->getAttribute('content'), $diagnostics, $element);

            return $policy === null ? null : ['http-equiv', 'content-security-policy', $policy];
        }

        $name = self::normalizeHtmlMetaName($element->getAttribute('name'));
        if ($name === 'referrer') {
            $policy = self::normalizeHtmlReferrerMetaPolicy($element->getAttribute('content'), $diagnostics, $element);

            return $policy === null ? null : ['name', 'referrer', $policy];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlContentSecurityPolicy(string $value, array &$diagnostics, \DOMElement $element): ?string
    {
        $content = self::cleanHtmlMetadataAttribute($value);
        if ($content === '') {
            return null;
        }

        $directives = [];
        foreach (explode(';', $content) as $directive) {
            $directive = trim(preg_replace('/[\t\r\n\f ]+/u', ' ', $directive) ?? $directive);
            if ($directive === '') {
                continue;
            }

            [$name, $sources] = self::splitHtmlContentSecurityPolicyDirective($directive);
            if (!self::isReviewableHtmlContentSecurityPolicyDirective($name, $sources)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'meta',
                    'attribute' => 'content',
                    'directive' => $name,
                ], $element);
                continue;
            }

            $directives[] = $name . ($sources === '' ? '' : ' ' . $sources);
        }

        return $directives === [] ? null : implode('; ', $directives);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function splitHtmlContentSecurityPolicyDirective(string $directive): array
    {
        $parts = preg_split('/[\t\r\n\f ]+/', $directive, 2);
        if (!is_array($parts) || $parts === []) {
            return ['', ''];
        }

        $name = strtolower((string) $parts[0]);
        $sources = isset($parts[1])
            ? trim(preg_replace('/[\t\r\n\f ]+/u', ' ', (string) $parts[1]) ?? (string) $parts[1])
            : '';

        return [$name, $sources];
    }

    private static function isReviewableHtmlContentSecurityPolicyDirective(string $name, string $sources): bool
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            return false;
        }

        if (in_array($name, ['report-to', 'report-uri'], true)) {
            return false;
        }

        $lowerCompact = strtolower(preg_replace('/[\x00-\x20]+/', '', $sources) ?? $sources);
        if ($lowerCompact !== '' && preg_match('/(?:java|vb)script:/', $lowerCompact) === 1) {
            return false;
        }

        return preg_match('/[<>{}`]/', $sources) !== 1;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlReferrerMetaPolicy(string $value, array &$diagnostics, \DOMElement $element): ?string
    {
        $policy = strtolower(self::cleanHtmlMetadataAttribute($value));
        if (in_array($policy, [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url',
        ], true)) {
            return $policy;
        }

        if ($policy !== '') {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'meta',
                'attribute' => 'content',
                'value' => $policy,
            ], $element);
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string, 2:string}|null
     */
    private static function htmlMetaCrawlerMetadata(\DOMElement $element, array &$diagnostics): ?array
    {
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $name = self::normalizeHtmlMetaName($element->getAttribute('name'));
        if (!in_array($name, ['robots', 'googlebot', 'bingbot', 'slurp'], true)) {
            return null;
        }

        $content = self::normalizeHtmlCrawlerDirectives($element->getAttribute('content'), $diagnostics, $element);

        return $content === null ? null : ['name', $name, $content];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlCrawlerDirectives(string $value, array &$diagnostics, \DOMElement $element): ?string
    {
        $content = self::cleanHtmlMetadataAttribute($value);
        if ($content === '') {
            return null;
        }

        $directives = [];
        foreach (explode(',', $content) as $directive) {
            $normalized = self::normalizeHtmlCrawlerDirective($directive, $diagnostics, $element);
            if ($normalized === null || in_array($normalized, $directives, true)) {
                continue;
            }

            $directives[] = $normalized;
        }

        return $directives === [] ? null : implode(', ', $directives);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlCrawlerDirective(string $directive, array &$diagnostics, \DOMElement $element): ?string
    {
        $value = strtolower(self::cleanHtmlMetadataAttribute($directive));
        if ($value === '') {
            return null;
        }

        $compact = preg_replace('/[\x00-\x20]+/', '', $value) ?? $value;
        if (preg_match('/[<>{}`]/', $value) === 1 || preg_match('/(?:java|vb)script:/', $compact) === 1) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'meta',
                'attribute' => 'content',
                'directive' => $value,
            ], $element);

            return null;
        }

        if (in_array($value, [
            'all',
            'follow',
            'index',
            'noarchive',
            'nocache',
            'nofollow',
            'noimageindex',
            'noindex',
            'none',
            'nosnippet',
            'notranslate',
        ], true)) {
            return $value;
        }

        if (preg_match('/^(max-snippet|max-video-preview):(-1|0|[1-9][0-9]*)$/', $value, $matches) === 1) {
            return $matches[1] . ':' . $matches[2];
        }

        if (preg_match('/^max-image-preview:(none|standard|large)$/', $value, $matches) === 1) {
            return 'max-image-preview:' . $matches[1];
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => 'meta',
            'attribute' => 'content',
            'directive' => $value,
        ], $element);

        return null;
    }

    /**
     * @return array{0:string, 1:string, 2:string}|null
     */
    private static function htmlMetaUrlMetadata(\DOMElement $element): ?array
    {
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $kind = '';
        $name = '';
        if ($element->hasAttribute('property')) {
            $property = self::normalizeHtmlMetaProperty($element->getAttribute('property'));
            if (in_array($property, [
                'og:image',
                'og:image:secure_url',
                'og:image:url',
                'twitter:image',
                'twitter:image:src',
            ], true)) {
                $kind = 'property';
                $name = $property;
            }
        }

        if ($name === '' && $element->hasAttribute('name')) {
            $metaName = self::normalizeHtmlMetaName($element->getAttribute('name'));
            if (in_array($metaName, ['twitter:image', 'twitter:image:src'], true)) {
                $kind = 'name';
                $name = $metaName;
            }
        }

        if ($name === '') {
            return null;
        }

        $content = str_replace("\0", '', $element->getAttribute('content'));
        $trimmedContent = trim($content);
        if ($trimmedContent === '') {
            return null;
        }

        if (preg_match('/[\x00-\x20]/', $trimmedContent) === 1) {
            $compact = preg_replace('/[\x00-\x20]+/', '', $trimmedContent) ?? $trimmedContent;
            if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $compact) !== 1) {
                return null;
            }
        }

        return [$kind, $name, $content];
    }

    private static function normalizeHtmlCharsetLabel(string $value): ?string
    {
        $label = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($label === '' || strlen($label) > 64) {
            return null;
        }
        if (preg_match('/^[a-z0-9._:-]+$/', $label) !== 1) {
            return null;
        }

        return $label;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string, 2:string, 3:array<string, string>}|null
     */
    private static function htmlMetaReviewMetadata(\DOMElement $element, array &$diagnostics): ?array
    {
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $kind = 'name';
        $name = '';
        if ($element->hasAttribute('name')) {
            $name = self::normalizeHtmlMetaName($element->getAttribute('name'));
            if ($name === 'theme-color') {
                return self::htmlMetaThemeColorMetadata($element, $diagnostics);
            }
            if ($name === 'color-scheme') {
                return self::htmlMetaColorSchemeMetadata($element, $diagnostics);
            }
            if (!in_array($name, ['application-name', 'author', 'description', 'generator', 'keywords'], true)) {
                $name = '';
            }
        }

        if ($name === '' && $element->hasAttribute('property')) {
            $kind = 'property';
            $name = self::normalizeHtmlMetaProperty($element->getAttribute('property'));
            if (!in_array($name, [
                'article:modified_time',
                'article:published_time',
                'og:description',
                'og:title',
                'twitter:description',
                'twitter:title',
            ], true)) {
                return null;
            }
        }

        if ($name === '') {
            return null;
        }

        $content = self::cleanHtmlMetadataAttribute($element->getAttribute('content'));
        if ($content === '') {
            return null;
        }

        return [$kind, $name, $content, []];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string, 2:string, 3:array<string, string>}|null
     */
    private static function htmlMetaThemeColorMetadata(\DOMElement $element, array &$diagnostics): ?array
    {
        $color = self::normalizeHtmlMetaThemeColorContent($element->getAttribute('content'));
        if ($color === null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'meta',
                'attribute' => 'content',
                'name' => 'theme-color',
            ], $element);

            return null;
        }

        $attrs = [];
        if ($element->hasAttribute('media')) {
            $media = self::normalizeHtmlMetaThemeColorMedia($element->getAttribute('media'));
            if ($media === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'meta',
                    'attribute' => 'media',
                    'name' => 'theme-color',
                ], $element);
            } else {
                $attrs['data-pandoc-meta-media'] = $media;
            }
        }

        return ['name', 'theme-color', $color, $attrs];
    }

    private static function normalizeHtmlMetaThemeColorContent(string $value): ?string
    {
        $color = self::normalizeReviewableHtmlStyleValue($value);
        if ($color === null || $color === '') {
            return null;
        }

        if (preg_match('/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/', $color) === 1) {
            return $color;
        }
        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\([A-Za-z0-9#.,%+\-\/ ]+\)$/i', $color) === 1) {
            return $color;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $color) === 1) {
            return strtolower($color);
        }

        return null;
    }

    private static function normalizeHtmlMetaThemeColorMedia(string $value): ?string
    {
        $media = self::normalizeReviewableHtmlStyleValue($value);
        if ($media === null || $media === '') {
            return null;
        }

        return $media;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string, 2:string, 3:array<string, string>}|null
     */
    private static function htmlMetaColorSchemeMetadata(\DOMElement $element, array &$diagnostics): ?array
    {
        $content = strtolower(self::cleanHtmlMetadataAttribute($element->getAttribute('content')));
        if ($content === '') {
            return null;
        }

        $tokens = preg_split('/[\t\r\n\f ]+/u', $content, -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (!in_array($token, ['normal', 'light', 'dark', 'only'], true)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'meta',
                    'attribute' => 'content',
                    'name' => 'color-scheme',
                    'token' => $token,
                ], $element);
                continue;
            }
            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        if ($normalized === []) {
            return null;
        }

        return ['name', 'color-scheme', implode(' ', $normalized), []];
    }

    private static function normalizeHtmlMetaName(string $name): string
    {
        $name = str_replace("\0", '', $name);
        $name = preg_replace('/[\t\r\n\f ]+/u', ' ', $name) ?? $name;

        return strtolower(trim($name));
    }

    private static function normalizeHtmlMetaProperty(string $property): string
    {
        $property = str_replace("\0", '', $property);
        $property = preg_replace('/[\t\r\n\f ]+/u', '', $property) ?? $property;

        return strtolower(trim($property));
    }

    private static function htmlMetaReviewLabel(string $name): string
    {
        return match ($name) {
            'application-name' => 'Application name',
            'article:modified_time' => 'Article modified time',
            'article:published_time' => 'Article published time',
            'author' => 'Author',
            'bingbot' => 'Bingbot',
            'color-scheme' => 'Color scheme',
            'description' => 'Description',
            'generator' => 'Generator',
            'googlebot' => 'Googlebot',
            'keywords' => 'Keywords',
            'content-security-policy' => 'Content security policy',
            'og:image' => 'Open Graph image',
            'og:image:secure_url' => 'Open Graph secure image',
            'og:image:url' => 'Open Graph image',
            'og:description' => 'Open Graph description',
            'og:title' => 'Open Graph title',
            'referrer' => 'Referrer policy',
            'robots' => 'Robots',
            'slurp' => 'Slurp',
            'theme-color' => 'Theme color',
            'twitter:description' => 'Twitter description',
            'twitter:image' => 'Twitter image',
            'twitter:image:src' => 'Twitter image',
            'twitter:title' => 'Twitter title',
            default => 'Metadata',
        };
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function htmlDocumentElementMetadataNodes(string $html, array &$diagnostics): array
    {
        $documentSource = self::htmlDocumentElementSource($html);
        if ($documentSource === null) {
            return [];
        }

        try {
            $dom = Html5Dom::parseHtmlDocumentPreservingSourceLines($documentSource, 'HTML document metadata');
        } catch (\Throwable) {
            return [];
        }

        $htmlElement = $dom->getElementsByTagName('html')->item(0);
        if (!$htmlElement instanceof \DOMElement || strtolower($htmlElement->tagName) !== 'html') {
            return [];
        }

        $nodes = [];
        $language = self::htmlDocumentLanguage($htmlElement, $diagnostics);
        if ($language !== null) {
            $nodes[] = self::htmlDocumentMetadataSpan('language', $language, 'Language');
        }

        $direction = self::htmlDocumentDirection($htmlElement, $diagnostics);
        if ($direction !== null) {
            $nodes[] = self::htmlDocumentMetadataSpan('direction', $direction, 'Direction');
        }

        $bodyElement = $dom->getElementsByTagName('body')->item(0);
        if ($bodyElement instanceof \DOMElement) {
            array_push($nodes, ...self::htmlBodyElementMetadataNodes(
                $bodyElement,
                $diagnostics,
                self::htmlDocumentBodySourceLine($documentSource)
            ));
        }

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function htmlBodyElementMetadataNodes(\DOMElement $bodyElement, array &$diagnostics, ?int $sourceLine = null): array
    {
        $nodes = [];
        $languageAttribute = $bodyElement->hasAttribute('lang') ? 'lang' : ($bodyElement->hasAttribute('xml:lang') ? 'xml:lang' : '');
        if ($languageAttribute !== '') {
            $language = self::normalizeHtmlLanguageTag($bodyElement->getAttribute($languageAttribute));
            if ($language === null) {
                $diagnostics[] = self::metadataDiagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'body',
                    'attribute' => $languageAttribute,
                    'reason' => 'invalid-body-language-metadata',
                ], $bodyElement, $sourceLine);
            } else {
                $diagnostics[] = self::metadataDiagnosticWithSourceLine([
                    'code' => 'document-metadata-review',
                    'tag' => 'body',
                    'attribute' => $languageAttribute,
                    'name' => 'body-language',
                    'reason' => 'body-language-preserved-as-metadata',
                ], $bodyElement, $sourceLine);
                $nodes[] = self::metadataNodeWithSourceLine(
                    self::htmlSourceMetadataSpan('body-language', 'body', $language, 'Body language'),
                    $bodyElement,
                    $sourceLine
                );
            }
        }

        if (!$bodyElement->hasAttribute('dir')) {
            return $nodes;
        }

        $direction = self::normalizeHtmlDirectionValue($bodyElement->getAttribute('dir'));
        if ($direction === null) {
            $sourceDirection = strtolower(self::cleanHtmlMetadataAttribute($bodyElement->getAttribute('dir')));
            if ($sourceDirection !== '') {
                $diagnostics[] = self::metadataDiagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'body',
                    'attribute' => 'dir',
                    'value' => $sourceDirection,
                    'reason' => 'invalid-body-direction-metadata',
                ], $bodyElement, $sourceLine);
            }

            return $nodes;
        }

        $diagnostics[] = self::metadataDiagnosticWithSourceLine([
            'code' => 'document-metadata-review',
            'tag' => 'body',
            'attribute' => 'dir',
            'name' => 'body-direction',
            'reason' => 'body-direction-preserved-as-metadata',
        ], $bodyElement, $sourceLine);
        $nodes[] = self::metadataNodeWithSourceLine(
            self::htmlSourceMetadataSpan('body-direction', 'body', $direction, 'Body direction'),
            $bodyElement,
            $sourceLine
        );

        return $nodes;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @return array<string, mixed>
     */
    private static function metadataDiagnosticWithSourceLine(array $diagnostic, \DOMElement $element, ?int $sourceLine): array
    {
        if ($sourceLine !== null) {
            $diagnostic['line'] = $sourceLine;

            return $diagnostic;
        }

        return self::diagnosticWithSourceLine($diagnostic, $element);
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function metadataNodeWithSourceLine(array $node, \DOMElement $element, ?int $sourceLine): array
    {
        if ($sourceLine !== null) {
            $node['line'] = $sourceLine;

            return $node;
        }

        return self::nodeWithSourceLine($node, $element);
    }

    private static function htmlDocumentElementSource(string $html): ?string
    {
        $offset = 0;
        $length = strlen($html);
        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $html, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                continue;
            }

            if (substr($html, $offset, 4) === '<!--') {
                $end = strpos($html, '-->', $offset + 4);
                if ($end === false) {
                    return null;
                }
                $offset = $end + 3;
                continue;
            }

            break;
        }

        if (strncasecmp(substr($html, $offset, 5), '<html', 5) !== 0) {
            return null;
        }

        $next = $html[$offset + 5] ?? '';
        if ($next !== '' && $next !== '>' && $next !== '/' && preg_match('/\s/', $next) !== 1) {
            return null;
        }

        return substr($html, $offset);
    }

    private static function htmlDocumentBodySourceLine(string $html): ?int
    {
        $offset = stripos($html, '<body');
        if ($offset === false) {
            return null;
        }

        return substr_count(substr($html, 0, $offset), "\n") + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private static function htmlSourceMetadataSpan(string $name, string $source, string $content, string $label): array
    {
        return [
            'type' => 'element',
            'name' => 'span',
            'attrs' => [
                'data-pandoc-meta-name' => $name,
                'data-pandoc-meta-source' => $source,
                'data-pandoc-meta-content' => $content,
            ],
            'children' => [[
                'type' => 'text',
                'text' => $label . ': ' . $content,
            ]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function htmlDocumentLanguage(\DOMElement $htmlElement, array &$diagnostics): ?string
    {
        $attributeName = $htmlElement->hasAttribute('lang') ? 'lang' : ($htmlElement->hasAttribute('xml:lang') ? 'xml:lang' : '');
        if ($attributeName === '') {
            return null;
        }

        $language = self::normalizeHtmlLanguageTag($htmlElement->getAttribute($attributeName));
        if ($language === null) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => 'html',
                'attribute' => $attributeName,
            ];

            return null;
        }

        $diagnostics[] = [
            'code' => 'document-metadata-review',
            'tag' => 'html',
            'attribute' => $attributeName,
            'name' => 'language',
        ];

        return $language;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function htmlDocumentDirection(\DOMElement $htmlElement, array &$diagnostics): ?string
    {
        if (!$htmlElement->hasAttribute('dir')) {
            return null;
        }

        $direction = self::normalizeHtmlDirectionValue($htmlElement->getAttribute('dir'));
        if ($direction === null) {
            $sourceDirection = strtolower(self::cleanHtmlMetadataAttribute($htmlElement->getAttribute('dir')));
            if ($sourceDirection !== '') {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => 'html',
                    'attribute' => 'dir',
                    'value' => $sourceDirection,
                ];
            }

            return null;
        }

        $diagnostics[] = [
            'code' => 'document-metadata-review',
            'tag' => 'html',
            'attribute' => 'dir',
            'name' => 'direction',
        ];

        return $direction;
    }

    private static function normalizeHtmlLanguageTag(string $value): ?string
    {
        $tag = self::cleanHtmlMetadataAttribute($value);
        if ($tag === '' || strlen($tag) > 64 || preg_match('/\s/u', $tag) === 1) {
            return null;
        }

        if (preg_match('/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*$/', $tag) !== 1) {
            return null;
        }

        $parts = explode('-', $tag);
        if (count($parts) === 1 && strlen($parts[0]) === 1) {
            return null;
        }

        $normalized = [];
        foreach ($parts as $index => $part) {
            if ($index === 0 || strtolower($parts[0]) === 'x') {
                $normalized[] = strtolower($part);
                continue;
            }

            if (preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $normalized[] = ucfirst(strtolower($part));
                continue;
            }

            if (preg_match('/^[A-Za-z]{2}$/', $part) === 1) {
                $normalized[] = strtoupper($part);
                continue;
            }

            $normalized[] = strtolower($part);
        }

        return implode('-', $normalized);
    }

    private static function normalizeHtmlDirectionValue(string $value): ?string
    {
        $direction = strtolower(self::cleanHtmlMetadataAttribute($value));

        return in_array($direction, ['ltr', 'rtl', 'auto'], true) ? $direction : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function htmlDocumentMetadataSpan(string $name, string $content, string $label): array
    {
        return [
            'type' => 'element',
            'name' => 'span',
            'attrs' => [
                'data-pandoc-meta-name' => $name,
                'data-pandoc-meta-source' => 'html',
                'data-pandoc-meta-content' => $content,
            ],
            'children' => [[
                'type' => 'text',
                'text' => $label . ': ' . $content,
            ]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function htmlBaseTargetMetadataNodes(\DOMElement $wrapper, array &$diagnostics): array
    {
        $metadataNodes = [];
        $hasActiveTarget = false;

        foreach ($wrapper->getElementsByTagName('base') as $baseElement) {
            if (!$baseElement instanceof \DOMElement || !$baseElement->hasAttribute('target')) {
                continue;
            }
            if (self::isInactiveFragmentBaseElement($baseElement)) {
                continue;
            }

            if ($hasActiveTarget) {
                $diagnostics[] = self::duplicateHtmlBaseDiagnostic($baseElement, 'target');
                continue;
            }

            $target = self::normalizeHtmlBaseTargetValue($baseElement->getAttribute('target'), $baseElement, $diagnostics);
            if ($target === null) {
                continue;
            }

            $hasActiveTarget = true;
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'base-target-review',
                'tag' => 'base',
                'attribute' => 'target',
                'target' => $target,
                'reason' => 'base-target-preserved-as-metadata',
            ], $baseElement);

            $metadataNodes[] = [
                'type' => 'element',
                'name' => 'span',
                'attrs' => [
                    'data-pandoc-meta-name' => 'base-target',
                    'data-pandoc-meta-source' => 'base',
                    'data-pandoc-meta-content' => $target,
                ],
                'children' => [[
                    'type' => 'text',
                    'text' => 'Base target: ' . $target,
                ]],
            ];
        }

        return $metadataNodes;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlBaseTargetValue(string $value, \DOMElement $baseElement, array &$diagnostics): ?string
    {
        $value = str_replace("\0", '', $value);
        if (preg_match('/[\t\r\n\f<]/', $value) === 1) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'base',
                'attribute' => 'target',
                'reason' => 'target-normalized-to-blank',
            ], $baseElement);

            return '_blank';
        }

        $target = self::cleanHtmlMetadataAttribute($value);
        if ($target === '') {
            return null;
        }
        if (strlen($target) > 128 || preg_match('/[>"\'`{}]/', $target) === 1) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'base',
                'attribute' => 'target',
            ], $baseElement);

            return null;
        }
        if (preg_match('/^[A-Za-z0-9_.:-]+$/', $target) !== 1) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'base',
                'attribute' => 'target',
            ], $baseElement);

            return null;
        }

        return $target;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeAttributes(
        \DOMElement $element,
        string $tagName,
        string $mode,
        array &$diagnostics,
        ?string $foreignContext,
        ?string $baseUrl
    ): array {
        if (!$element->hasAttributes() && $mode !== 'xml') {
            return [];
        }

        $attrs = $mode === 'xml' ? self::xmlNamespaceDeclarationAttributes($element) : [];
        foreach ($element->attributes as $attribute) {
            $name = $mode === 'xml'
                ? self::xmlAttributeName($attribute)
                : strtolower($attribute->name);
            if ($foreignContext !== null) {
                $name = XmlHtmlDom::adjustHtmlForeignAttributeName($name);
            }
            $value = str_replace("\0", '', $attribute->value);

            if (!self::isSafeAttributeName($name)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $name,
                ], $element);
                continue;
            }

            if ($mode === 'html' && strtolower($name) === 'style') {
                $style = self::normalizeHtmlStyleAttribute($value, $tagName, $element, $diagnostics);
                if ($style !== null) {
                    $attrs['data-pandoc-style'] = $style;
                }
                continue;
            }

            if ($mode === 'html' && strtolower($name) === 'popover') {
                $attrs['data-pandoc-popover-state'] = self::normalizeHtmlPopoverAttribute(
                    $value,
                    $tagName,
                    $element,
                    $diagnostics
                );
                continue;
            }

            if ($mode === 'html' && self::isHtmlPopoverTargetInvokerAttribute($name)) {
                if (!isset($attrs['data-pandoc-popover-target-kind'])) {
                    foreach (self::normalizeHtmlPopoverTargetInvokerAttributes($element, $tagName, $diagnostics) as $metadataName => $metadataValue) {
                        $attrs[$metadataName] = $metadataValue;
                    }
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlEditingStateAttribute($name)) {
                $editingMetadata = self::normalizeHtmlEditingStateAttribute($name, $value, $tagName, $element, $diagnostics);
                if ($editingMetadata !== null) {
                    [$metadataName, $metadataValue] = $editingMetadata;
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && strtolower($name) === 'translate') {
                $translationState = self::normalizeHtmlTranslationStateAttribute($value, $tagName, $element, $diagnostics);
                if ($translationState !== null) {
                    $attrs['data-pandoc-translate-state'] = $translationState;
                }
                continue;
            }

            if ($mode === 'html' && $foreignContext === null && strtolower($name) === 'slot') {
                $slotAssignment = self::normalizeHtmlSlotAssignmentAttribute($value, $tagName, $element, $diagnostics);
                if ($slotAssignment !== null) {
                    $attrs['data-pandoc-slot-assignment'] = $slotAssignment;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlElementReferrerPolicyAttribute($tagName, $name, $foreignContext)) {
                $referrerPolicy = self::normalizeHtmlReferrerPolicyAttribute($value, strtolower($tagName), $diagnostics, $element);
                if ($referrerPolicy !== null) {
                    $attrs['data-pandoc-referrerpolicy'] = $referrerPolicy;
                    self::addHtmlReferrerPolicyReviewDiagnostic($diagnostics, strtolower($tagName), $element);
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlImageResourcePolicyAttribute($tagName, $name, $foreignContext)) {
                $imageResourcePolicyMetadata = self::normalizeHtmlImageResourcePolicyAttribute(
                    $name,
                    $value,
                    $tagName,
                    $element,
                    $diagnostics
                );
                foreach ($imageResourcePolicyMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlMediaResourcePolicyAttribute($tagName, $name, $foreignContext)) {
                $mediaResourcePolicyMetadata = self::normalizeHtmlMediaResourcePolicyAttribute(
                    $name,
                    $value,
                    $tagName,
                    $element,
                    $diagnostics
                );
                foreach ($mediaResourcePolicyMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlFocusNavigationAttribute($name)) {
                $focusNavigationMetadata = self::normalizeHtmlFocusNavigationAttribute(
                    $name,
                    $value,
                    $tagName,
                    $element,
                    $diagnostics
                );
                foreach ($focusNavigationMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlTextInputHintAttribute($name)) {
                $textInputHintMetadata = self::normalizeHtmlTextInputHintAttribute(
                    $name,
                    $value,
                    $tagName,
                    $element,
                    $diagnostics
                );
                if ($textInputHintMetadata !== null) {
                    [$metadataName, $metadataValue] = $textInputHintMetadata;
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlWritingAssistanceAttribute($name)) {
                $writingAssistanceMetadata = self::normalizeHtmlWritingAssistanceAttribute(
                    $name,
                    $value,
                    $tagName,
                    $element,
                    $diagnostics
                );
                if ($writingAssistanceMetadata !== null) {
                    [$metadataName, $metadataValue] = $writingAssistanceMetadata;
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlRevisionMetadataAttribute($tagName, $name)) {
                $revisionMetadata = self::normalizeHtmlRevisionMetadataAttribute(
                    $name,
                    $value,
                    $tagName,
                    $element,
                    $diagnostics,
                    $baseUrl
                );
                foreach ($revisionMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlQuoteCitationAttribute($tagName, $name)) {
                $cite = self::normalizeHtmlQuoteCitationAttribute($value, $tagName, $element, $diagnostics, $baseUrl);
                if ($cite !== null) {
                    $attrs['data-pandoc-quote-cite'] = $cite;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlLanguageDirectionAttribute($name)) {
                $languageDirectionMetadata = self::normalizeHtmlLanguageDirectionAttribute(
                    $name,
                    $value,
                    $tagName,
                    $element,
                    $diagnostics
                );
                if ($languageDirectionMetadata !== null) {
                    [$metadataName, $metadataValue] = $languageDirectionMetadata;
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlAriaMetadataAttribute($name)) {
                $ariaMetadata = self::normalizeHtmlAriaMetadataAttribute($name, $value, $tagName, $diagnostics);
                foreach ($ariaMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlCustomElementMetadataAttribute($element, $tagName, $name, $foreignContext)) {
                $customMetadata = self::normalizeHtmlCustomElementMetadataAttribute(
                    $name,
                    $value,
                    $tagName,
                    $diagnostics
                );
                foreach ($customMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && $foreignContext === null && strtolower($tagName) === 'a' && self::isHtmlAnchorBrowsingMetadataAttribute($name)) {
                $linkBrowsingMetadata = self::normalizeHtmlAnchorBrowsingMetadataAttribute(
                    $name,
                    $value,
                    $element,
                    $diagnostics
                );
                foreach ($linkBrowsingMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isBlockedAttribute($name, $foreignContext)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $name,
                ], $element);
                continue;
            }

            if ($mode === 'html' && self::isHtmlSemanticMetadataAttribute($name)) {
                $metadataAttrs = self::normalizeHtmlSemanticMetadataAttribute(
                    $name,
                    $value,
                    $tagName,
                    $element,
                    $diagnostics,
                    $baseUrl
                );
                foreach ($metadataAttrs as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && strtolower($tagName) === 'time' && strtolower($name) === 'datetime') {
                $timeMetadata = self::normalizeHtmlTimeDatetimeAttribute($value, $tagName, $element, $diagnostics);
                if ($timeMetadata !== null) {
                    [$kind, $datetime] = $timeMetadata;
                    $attrs['data-pandoc-time-datetime'] = $datetime;
                    $attrs['data-pandoc-time-kind'] = $kind;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlValueMetadataAttribute($tagName, $name)) {
                $valueMetadata = self::normalizeHtmlValueMetadataAttribute($tagName, $name, $value, $element, $diagnostics);
                foreach ($valueMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && self::isHtmlOutputMetadataAttribute($tagName, $name)) {
                $outputMetadata = self::normalizeHtmlOutputMetadataAttribute($name, $value, $element, $diagnostics);
                foreach ($outputMetadata as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && strtolower($tagName) === 'track') {
                $trackAttributeName = strtolower($name);
                if ($trackAttributeName === 'kind') {
                    $kind = self::normalizeHtmlTrackKindAttribute($value, $element, $diagnostics);
                    if ($kind !== null) {
                        $attrs[$name] = $kind;
                    }
                    continue;
                }
                if ($trackAttributeName === 'srclang') {
                    $language = self::normalizeHtmlTrackLanguageAttribute($value, $element, $diagnostics);
                    if ($language !== null) {
                        $attrs[$name] = $language;
                    }
                    continue;
                }
                if ($trackAttributeName === 'label') {
                    $label = self::cleanHtmlMetadataAttribute($value);
                    if ($label !== '') {
                        $attrs[$name] = $label;
                    }
                    continue;
                }
                if ($trackAttributeName === 'default') {
                    $attrs[$name] = '';
                    continue;
                }
            }

            if ($mode === 'html' && strtolower($name) === 'srcset') {
                $srcset = self::normalizeSrcsetAttribute($value, $tagName, $diagnostics, $baseUrl);
                if ($srcset === null) {
                    continue;
                }

                $attrs[$name] = $srcset;
                continue;
            }

            if ($mode === 'html' && self::isHtmlResponsiveImageMetadataAttribute($tagName, $name)) {
                $metadataValue = self::normalizeHtmlResponsiveImageMetadataAttribute($value, $tagName, $name, $diagnostics);
                if ($metadataValue === null) {
                    continue;
                }

                $attrs[$name] = $metadataValue;
                continue;
            }

            if ($mode === 'html' && strtolower($name) === 'rel') {
                $rel = self::normalizeHtmlRelAttribute($value, $tagName, $diagnostics);
                if ($rel === null) {
                    continue;
                }

                $attrs[$name] = $rel;
                continue;
            }

            if ($mode === 'html' && self::isSvgPresentationResourceAttribute($name, $foreignContext)) {
                $resourceValue = self::normalizeSvgPresentationResourceAttribute(
                    $value,
                    $tagName,
                    $name,
                    $diagnostics,
                    $baseUrl
                );
                if ($resourceValue === null) {
                    continue;
                }

                $attrs[$name] = $resourceValue;
                continue;
            }

            if ($mode === 'html' && self::isUrlAttribute($name)) {
                if (!self::isSafeUrlAttributeValue($tagName, $name, $value, $foreignContext)) {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'unsafe-url',
                        'tag' => $tagName,
                        'attribute' => $name,
                    ], $element);
                    continue;
                }

                $normalizedUrl = self::normalizeUrlAttributeValue($value);
                if ($normalizedUrl !== $value) {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'normalized-url',
                        'tag' => $tagName,
                        'attribute' => $name,
                    ], $element);
                }

                $value = $mode === 'html'
                    && $baseUrl !== null
                    && !self::isLocalSvgReferenceUrl($tagName, $name, $normalizedUrl, $foreignContext)
                    && !self::isLocalImageMapReferenceUrl($name, $normalizedUrl)
                    ? self::resolveRelativeUrl($baseUrl, $normalizedUrl)
                    : $normalizedUrl;
            }

            $attrs[$name] = $value;
        }

        return $attrs;
    }

    private static function isHtmlValueMetadataAttribute(string $tagName, string $name): bool
    {
        $tag = strtolower($tagName);
        $attribute = strtolower($name);

        return match ($tag) {
            'data' => $attribute === 'value',
            'meter' => in_array($attribute, ['value', 'min', 'max', 'low', 'high', 'optimum'], true),
            'progress' => in_array($attribute, ['value', 'max'], true),
            default => false,
        };
    }

    private static function isHtmlOutputMetadataAttribute(string $tagName, string $name): bool
    {
        return strtolower($tagName) === 'output'
            && in_array(strtolower($name), ['for', 'form', 'name'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlOutputMetadataAttribute(
        string $name,
        string $value,
        \DOMElement $element,
        array &$diagnostics
    ): array {
        $attribute = strtolower($name);
        if ($attribute === 'for') {
            $tokens = self::splitHtmlSemanticTokens($value);
            if ($tokens === []) {
                self::addHtmlInvalidOutputMetadataDiagnostic($diagnostics, $attribute, $element);

                return [];
            }

            $normalized = [];
            foreach ($tokens as $token) {
                if (!self::isSafeHtmlAriaIdToken($token)) {
                    self::addHtmlInvalidOutputMetadataDiagnostic($diagnostics, $attribute, $element, $token);
                    continue;
                }
                if (!in_array($token, $normalized, true)) {
                    $normalized[] = $token;
                }
            }

            if ($normalized === []) {
                return [];
            }

            self::addHtmlOutputMetadataDiagnostic($diagnostics, $attribute, 'data-pandoc-output-for', $element);

            return ['data-pandoc-output-for' => implode(' ', $normalized)];
        }

        if ($attribute === 'form') {
            $form = self::cleanHtmlMetadataAttribute($value);
            if ($form === '' || strlen($form) > 128 || !self::isSafeHtmlAriaIdToken($form)) {
                self::addHtmlInvalidOutputMetadataDiagnostic($diagnostics, $attribute, $element);

                return [];
            }

            self::addHtmlOutputMetadataDiagnostic($diagnostics, $attribute, 'data-pandoc-output-form', $element);

            return ['data-pandoc-output-form' => $form];
        }

        $outputName = self::cleanHtmlMetadataAttribute($value);
        if ($outputName === '' || strlen($outputName) > 128 || preg_match('/[<>{}`]/u', $outputName) === 1) {
            self::addHtmlInvalidOutputMetadataDiagnostic($diagnostics, $attribute, $element);

            return [];
        }

        self::addHtmlOutputMetadataDiagnostic($diagnostics, $attribute, 'data-pandoc-output-name', $element);

        return ['data-pandoc-output-name' => $outputName];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlOutputMetadataDiagnostic(
        array &$diagnostics,
        string $attributeName,
        string $metadataAttribute,
        \DOMElement $element
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'output-metadata-review',
            'tag' => 'output',
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'calculation-output-association-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidOutputMetadataDiagnostic(
        array &$diagnostics,
        string $attributeName,
        \DOMElement $element,
        ?string $token = null
    ): void {
        $diagnostic = [
            'code' => 'unsafe-attribute',
            'tag' => 'output',
            'attribute' => $attributeName,
            'reason' => 'invalid-output-metadata',
        ];
        if ($token !== null) {
            $diagnostic['token'] = $token;
        }

        $diagnostics[] = self::diagnosticWithSourceLine($diagnostic, $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlValueMetadataAttribute(
        string $tagName,
        string $name,
        string $value,
        \DOMElement $element,
        array &$diagnostics
    ): array {
        $tag = strtolower($tagName);
        $attribute = strtolower($name);
        if ($tag === 'data') {
            $metadataValue = self::normalizeHtmlDataValueAttribute($value);
            if ($metadataValue === null) {
                self::addHtmlInvalidValueMetadataDiagnostic($diagnostics, $tagName, $attribute, $element);

                return [];
            }

            self::addHtmlValueMetadataDiagnostic($diagnostics, $tagName, $attribute, 'data-pandoc-data-value', $element);

            return ['data-pandoc-data-value' => $metadataValue];
        }

        $metadataValue = self::normalizeHtmlNumericValueAttribute($value);
        if (
            $metadataValue === null
            || ($tag === 'progress' && $attribute === 'value' && self::isNegativeHtmlNumericValue($metadataValue))
            || ($tag === 'progress' && $attribute === 'max' && !self::isPositiveHtmlNumericValue($metadataValue))
        ) {
            self::addHtmlInvalidValueMetadataDiagnostic($diagnostics, $tagName, $attribute, $element);

            return [];
        }

        $metadataAttribute = 'data-pandoc-' . $tag . '-' . $attribute;
        self::addHtmlValueMetadataDiagnostic($diagnostics, $tagName, $attribute, $metadataAttribute, $element);

        return [$metadataAttribute => $metadataValue];
    }

    private static function normalizeHtmlDataValueAttribute(string $value): ?string
    {
        $value = self::cleanHtmlMetadataAttribute($value);
        if ($value === '' || strlen($value) > 256 || preg_match('/[<>{}`]/', $value) === 1) {
            return null;
        }

        return $value;
    }

    private static function normalizeHtmlNumericValueAttribute(string $value): ?string
    {
        $value = self::cleanHtmlMetadataAttribute($value);
        if ($value === '' || strlen($value) > 64 || preg_match('/^-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/', $value) !== 1) {
            return null;
        }

        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = substr($value, 1);
        }
        if (str_starts_with($value, '.')) {
            $value = '0' . $value;
        }

        if (str_contains($value, '.')) {
            [$integer, $fraction] = explode('.', $value, 2);
            $integer = ltrim($integer, '0');
            $fraction = rtrim($fraction, '0');
            $value = ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);
        } else {
            $value = ltrim($value, '0');
        }

        if ($value === '') {
            $value = '0';
        }

        return $negative && $value !== '0' ? '-' . $value : $value;
    }

    private static function isNegativeHtmlNumericValue(string $value): bool
    {
        return str_starts_with($value, '-');
    }

    private static function isPositiveHtmlNumericValue(string $value): bool
    {
        return $value !== '0' && !self::isNegativeHtmlNumericValue($value);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlValueMetadataDiagnostic(
        array &$diagnostics,
        string $tagName,
        string $attributeName,
        string $metadataAttribute,
        \DOMElement $element
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'value-metadata-review',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'semantic-value-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidValueMetadataDiagnostic(
        array &$diagnostics,
        string $tagName,
        string $attributeName,
        \DOMElement $element
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'reason' => 'invalid-semantic-value-metadata',
        ], $element);
    }

    private static function isHtmlLanguageDirectionAttribute(string $name): bool
    {
        return in_array(strtolower($name), ['dir', 'lang', 'xml:lang'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string}|null
     */
    private static function normalizeHtmlLanguageDirectionAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?array {
        $attribute = strtolower($name);
        if ($attribute === 'dir') {
            $direction = self::normalizeHtmlDirectionValue($value);
            if ($direction === null) {
                $sourceDirection = strtolower(self::cleanHtmlMetadataAttribute($value));
                if ($sourceDirection !== '') {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'unsafe-attribute',
                        'tag' => $tagName,
                        'attribute' => 'dir',
                        'value' => $sourceDirection,
                    ], $element);
                }

                return null;
            }

            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'language-direction-review',
                'tag' => $tagName,
                'attribute' => 'dir',
                'direction' => $direction,
                'reason' => 'language-direction-preserved-as-metadata',
            ], $element);

            return ['data-pandoc-dir', $direction];
        }

        $language = self::normalizeHtmlLanguageTag($value);
        if ($language === null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attribute,
            ], $element);

            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'language-direction-review',
            'tag' => $tagName,
            'attribute' => $attribute,
            'language' => $language,
            'reason' => 'language-direction-preserved-as-metadata',
        ], $element);

        return ['data-pandoc-lang', $language];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlPopoverAttribute(string $value, string $tagName, \DOMElement $element, array &$diagnostics): string
    {
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($state === '') {
            $state = 'auto';
        }

        if (!in_array($state, ['auto', 'manual', 'hint'], true)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => 'popover',
                'value' => $state,
            ], $element);
            $state = 'manual';
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'popover-review',
            'tag' => $tagName,
            'attribute' => 'popover',
            'state' => $state,
            'reason' => 'popover-content-preserved',
        ], $element);

        return $state;
    }

    private static function isHtmlPopoverTargetInvokerAttribute(string $name): bool
    {
        return in_array(strtolower($name), ['popovertarget', 'popovertargetaction'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlPopoverTargetInvokerAttributes(
        \DOMElement $element,
        string $tagName,
        array &$diagnostics
    ): array {
        $hasTarget = $element->hasAttribute('popovertarget');
        $hasAction = $element->hasAttribute('popovertargetaction');
        $targetRaw = $hasTarget ? $element->getAttribute('popovertarget') : '';
        $targetId = $hasTarget ? self::normalizeHtmlPopoverTargetReference($targetRaw) : null;
        $target = $targetId === null ? null : self::htmlElementById($element, $targetId);
        $actionRaw = $hasAction ? $element->getAttribute('popovertargetaction') : '';
        $action = self::normalizeHtmlPopoverTargetAction($actionRaw);
        $issues = [];

        if (!$hasTarget || trim($targetRaw) === '') {
            $issues[] = 'missing-popover-target-reference';
        } elseif ($targetId === null) {
            $issues[] = 'invalid-popover-target-reference';
            self::addHtmlInvalidPopoverTargetDiagnostic($diagnostics, $tagName, $element, 'popovertarget');
        } elseif (!$target instanceof \DOMElement) {
            $issues[] = 'missing-popover-target';
        }

        if ($hasAction && $action === null) {
            $issues[] = 'invalid-popover-target-action';
            self::addHtmlInvalidPopoverTargetDiagnostic($diagnostics, $tagName, $element, 'popovertargetaction');
        }

        if ($target instanceof \DOMElement) {
            if (!$target->hasAttribute('popover')) {
                $issues[] = 'non-popover-target';
            } elseif (self::normalizeHtmlPopoverMetadataState($target->getAttribute('popover')) === null) {
                $issues[] = 'invalid-popover-target-state';
            }
        }

        $metadata = [
            'data-pandoc-popover-action' => $action ?? 'invalid',
            'data-pandoc-popover-action-defaulted' => $hasAction ? 'false' : 'true',
            'data-pandoc-popover-target-kind' => self::htmlPopoverTargetKind($target, $targetRaw, $targetId),
        ];
        self::addHtmlPopoverTargetReviewDiagnostic($diagnostics, $tagName, $element, 'popovertargetaction', 'data-pandoc-popover-action');
        self::addHtmlPopoverTargetReviewDiagnostic($diagnostics, $tagName, $element, 'popovertargetaction', 'data-pandoc-popover-action-defaulted');
        self::addHtmlPopoverTargetReviewDiagnostic($diagnostics, $tagName, $element, 'popovertarget', 'data-pandoc-popover-target-kind');

        if ($targetId !== null) {
            $metadata['data-pandoc-popover-target'] = $targetId;
            self::addHtmlPopoverTargetReviewDiagnostic($diagnostics, $tagName, $element, 'popovertarget', 'data-pandoc-popover-target');
        }

        if ($target instanceof \DOMElement) {
            foreach (self::htmlPopoverTargetElementMetadata($target) as $metadataAttribute => $metadataValue) {
                $metadata[$metadataAttribute] = $metadataValue;
                self::addHtmlPopoverTargetReviewDiagnostic($diagnostics, $tagName, $element, 'popovertarget', $metadataAttribute);
            }
        }

        $issues = array_values(array_unique($issues));
        if ($issues !== []) {
            $metadata['data-pandoc-popover-target-issues'] = implode(' ', $issues);
            self::addHtmlPopoverTargetReviewDiagnostic($diagnostics, $tagName, $element, 'popovertarget', 'data-pandoc-popover-target-issues');
        }

        return $metadata;
    }

    private static function normalizeHtmlPopoverTargetReference(string $value): ?string
    {
        $target = self::cleanHtmlMetadataAttribute($value);
        if ($target === '' || strlen($target) > 128 || !self::isSafeHtmlAriaIdToken($target)) {
            return null;
        }

        return $target;
    }

    private static function normalizeHtmlPopoverTargetAction(string $value): ?string
    {
        $action = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($action === '') {
            return 'toggle';
        }

        return in_array($action, ['hide', 'show', 'toggle'], true) ? $action : null;
    }

    private static function htmlPopoverTargetKind(?\DOMElement $target, string $targetRaw, ?string $targetId): string
    {
        if (!$target instanceof \DOMElement) {
            if ($targetId === null) {
                return trim($targetRaw) === '' ? 'missing-reference' : 'invalid-reference';
            }

            return 'missing-target';
        }

        return $target->hasAttribute('popover') ? 'popover' : 'element';
    }

    /**
     * @return array<string, string>
     */
    private static function htmlPopoverTargetElementMetadata(\DOMElement $target): array
    {
        $metadata = [
            'data-pandoc-popover-target-tag' => self::htmlNormalizedElementName($target),
        ];

        $id = self::cleanHtmlMetadataAttribute($target->getAttribute('id'));
        if ($id !== '' && strlen($id) <= 128 && self::isSafeHtmlAriaIdToken($id)) {
            $metadata['data-pandoc-popover-target-id'] = $id;
        }

        if ($target->hasAttribute('popover')) {
            $state = self::normalizeHtmlPopoverMetadataState($target->getAttribute('popover'));
            if ($state !== null) {
                $metadata['data-pandoc-popover-target-state'] = $state;
            }
        }

        return $metadata;
    }

    private static function normalizeHtmlPopoverMetadataState(string $value): ?string
    {
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($state === '') {
            $state = 'auto';
        }

        return in_array($state, ['auto', 'manual', 'hint'], true) ? $state : null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlPopoverTargetReviewDiagnostic(
        array &$diagnostics,
        string $tagName,
        \DOMElement $element,
        string $attributeName,
        string $metadataAttribute
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'popover-review',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'popover-target-metadata-preserved',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidPopoverTargetDiagnostic(
        array &$diagnostics,
        string $tagName,
        \DOMElement $element,
        string $attributeName
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'reason' => 'invalid-popover-target-metadata',
        ], $element);
    }

    private static function isHtmlEditingStateAttribute(string $name): bool
    {
        return in_array(strtolower($name), ['contenteditable', 'draggable', 'spellcheck'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string}|null
     */
    private static function normalizeHtmlEditingStateAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?array {
        $attribute = strtolower($name);
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($state === '') {
            $state = 'true';
        }

        $allowedStates = match ($attribute) {
            'contenteditable' => ['true', 'false', 'plaintext-only'],
            'draggable' => ['true', 'false', 'auto'],
            'spellcheck' => ['true', 'false', 'default'],
            default => [],
        };

        if (!in_array($state, $allowedStates, true)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attribute,
                'value' => $state,
            ], $element);

            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'editing-state-review',
            'tag' => $tagName,
            'attribute' => $attribute,
            'state' => $state,
            'reason' => 'live-editing-attribute-preserved-as-metadata',
        ], $element);

        return ['data-pandoc-' . $attribute . '-state', $state];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlTranslationStateAttribute(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?string {
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($state === '') {
            $state = 'yes';
        }

        if (!in_array($state, ['yes', 'no'], true)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => 'translate',
                'value' => $state,
            ], $element);

            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'translation-state-review',
            'tag' => $tagName,
            'attribute' => 'translate',
            'state' => $state,
            'reason' => 'translation-state-preserved-as-metadata',
        ], $element);

        return $state;
    }

    private static function isHtmlFocusNavigationAttribute(string $name): bool
    {
        return in_array(strtolower($name), ['accesskey', 'autofocus', 'tabindex'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlFocusNavigationAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): array {
        $attribute = strtolower($name);
        if ($attribute === 'autofocus') {
            self::addHtmlFocusNavigationDiagnostic(
                $diagnostics,
                $tagName,
                $attribute,
                'data-pandoc-autofocus-state',
                $element
            );

            return ['data-pandoc-autofocus-state' => 'true'];
        }

        if ($attribute === 'tabindex') {
            $tabIndex = self::normalizeHtmlTabIndexAttribute($value);
            if ($tabIndex === null) {
                self::addHtmlInvalidFocusNavigationDiagnostic($diagnostics, $tagName, $attribute, $element);

                return [];
            }

            self::addHtmlFocusNavigationDiagnostic($diagnostics, $tagName, $attribute, 'data-pandoc-tabindex', $element);

            return ['data-pandoc-tabindex' => $tabIndex];
        }

        $accessKeys = self::normalizeHtmlAccessKeyAttribute($value, $tagName, $element, $diagnostics);
        if ($accessKeys === null) {
            return [];
        }

        self::addHtmlFocusNavigationDiagnostic($diagnostics, $tagName, $attribute, 'data-pandoc-accesskey', $element);

        return ['data-pandoc-accesskey' => $accessKeys];
    }

    private static function normalizeHtmlTabIndexAttribute(string $value): ?string
    {
        $value = self::cleanHtmlMetadataAttribute($value);
        if ($value === '' || strlen($value) > 8 || preg_match('/^[+-]?[0-9]+$/', $value) !== 1) {
            return null;
        }

        $integer = (int) $value;
        if ($integer < -32768 || $integer > 32767) {
            return null;
        }

        return (string) $integer;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAccessKeyAttribute(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?string {
        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            self::addHtmlInvalidFocusNavigationDiagnostic($diagnostics, $tagName, 'accesskey', $element);

            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (!self::isSafeHtmlAccessKeyToken($token)) {
                self::addHtmlInvalidFocusNavigationDiagnostic($diagnostics, $tagName, 'accesskey', $element, $token);
                continue;
            }

            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        return $normalized === [] ? null : implode(' ', $normalized);
    }

    private static function isSafeHtmlAccessKeyToken(string $token): bool
    {
        if ($token === '' || preg_match('/[<>"\'`{}]/u', $token) === 1) {
            return false;
        }
        if (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $token) === 1) {
            return false;
        }

        $count = preg_match_all('/./us', $token);

        return $count === 1;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlFocusNavigationDiagnostic(
        array &$diagnostics,
        string $tagName,
        string $attributeName,
        string $metadataAttribute,
        \DOMElement $element
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'focus-navigation-review',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'focus-navigation-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidFocusNavigationDiagnostic(
        array &$diagnostics,
        string $tagName,
        string $attributeName,
        \DOMElement $element,
        ?string $token = null
    ): void {
        $diagnostic = [
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'reason' => 'invalid-focus-navigation-metadata',
        ];
        if ($token !== null) {
            $diagnostic['token'] = $token;
        }

        $diagnostics[] = self::diagnosticWithSourceLine($diagnostic, $element);
    }

    private static function isHtmlTextInputHintAttribute(string $name): bool
    {
        return in_array(strtolower($name), ['inputmode', 'enterkeyhint', 'autocapitalize'], true);
    }

    private static function isHtmlWritingAssistanceAttribute(string $name): bool
    {
        return in_array(strtolower($name), ['autocorrect', 'writingsuggestions', 'virtualkeyboardpolicy'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string}|null
     */
    private static function normalizeHtmlTextInputHintAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?array {
        $attribute = strtolower($name);
        $hint = self::normalizeHtmlTextInputHintToken($attribute, $value);
        if ($hint === null) {
            self::addHtmlInvalidTextInputHintDiagnostic($diagnostics, $tagName, $attribute, $element);

            return null;
        }

        $metadataAttribute = 'data-pandoc-' . $attribute;
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'text-input-hint-review',
            'tag' => $tagName,
            'attribute' => $attribute,
            'metadataAttribute' => $metadataAttribute,
            'hint' => $hint,
            'reason' => 'text-input-hint-preserved-as-review-metadata',
        ], $element);

        return [$metadataAttribute, $hint];
    }

    private static function normalizeHtmlTextInputHintToken(string $attribute, string $value): ?string
    {
        $hint = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($hint === '' || strlen($hint) > 32 || preg_match('/[<>"\'`{}]/u', $hint) === 1) {
            return null;
        }

        if ($attribute === 'autocapitalize') {
            $hint = match ($hint) {
                'none' => 'off',
                'on' => 'sentences',
                default => $hint,
            };
        }

        $allowedHints = match ($attribute) {
            'inputmode' => ['none', 'text', 'tel', 'url', 'email', 'numeric', 'decimal', 'search'],
            'enterkeyhint' => ['enter', 'done', 'go', 'next', 'previous', 'search', 'send'],
            'autocapitalize' => ['off', 'sentences', 'words', 'characters'],
            default => [],
        };

        return in_array($hint, $allowedHints, true) ? $hint : null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlInvalidTextInputHintDiagnostic(
        array &$diagnostics,
        string $tagName,
        string $attributeName,
        \DOMElement $element
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'reason' => 'invalid-text-input-hint-metadata',
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string}|null
     */
    private static function normalizeHtmlWritingAssistanceAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?array {
        $attribute = strtolower($name);
        $state = self::normalizeHtmlWritingAssistanceToken($attribute, $value);
        if ($state === null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attribute,
                'reason' => 'invalid-writing-assistance-metadata',
            ], $element);

            return null;
        }

        $metadataAttribute = 'data-pandoc-' . $attribute;
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'writing-assistance-review',
            'tag' => $tagName,
            'attribute' => $attribute,
            'metadataAttribute' => $metadataAttribute,
            'state' => $state,
            'reason' => 'writing-assistance-preserved-as-review-metadata',
        ], $element);

        return [$metadataAttribute, $state];
    }

    private static function normalizeHtmlWritingAssistanceToken(string $attribute, string $value): ?string
    {
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));
        if (strlen($state) > 32 || preg_match('/[<>"\'`{}]/u', $state) === 1) {
            return null;
        }

        return match ($attribute) {
            'autocorrect' => match ($state) {
                '', 'on' => 'on',
                'off' => 'off',
                default => null,
            },
            'writingsuggestions' => match ($state) {
                '', 'true' => 'true',
                'false' => 'false',
                default => null,
            },
            'virtualkeyboardpolicy' => match ($state) {
                '', 'auto' => 'auto',
                'manual' => 'manual',
                default => null,
            },
            default => null,
        };
    }

    private static function isHtmlRevisionMetadataAttribute(string $tagName, string $name): bool
    {
        return in_array(strtolower($tagName), ['del', 'ins'], true)
            && in_array(strtolower($name), ['cite', 'datetime'], true);
    }

    private static function isHtmlQuoteCitationAttribute(string $tagName, string $name): bool
    {
        return in_array(strtolower($tagName), ['blockquote', 'q'], true)
            && strtolower($name) === 'cite';
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlQuoteCitationAttribute(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $normalized = self::normalizeUrlAttributeValue($value);
        if ($normalized === '' || !self::isSafeFetchUrl($normalized)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => 'cite',
            ], $element);

            return null;
        }

        if ($normalized !== $value) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => $tagName,
                'attribute' => 'cite',
            ], $element);
        }

        if ($baseUrl !== null) {
            $normalized = self::resolveRelativeUrl($baseUrl, $normalized);
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'quote-cite-review',
            'tag' => $tagName,
            'attribute' => 'cite',
            'reason' => 'quote-cite-preserved-as-metadata',
        ], $element);

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlRevisionMetadataAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics,
        ?string $baseUrl
    ): array {
        $attribute = strtolower($name);
        if ($attribute === 'cite') {
            $cite = self::normalizeHtmlRevisionCiteAttribute($value, $tagName, $element, $diagnostics, $baseUrl);

            return $cite === null ? [] : ['data-pandoc-revision-cite' => $cite];
        }

        $datetime = self::normalizeHtmlRevisionDatetimeAttribute($value, $tagName, $element, $diagnostics);

        return $datetime === null
            ? []
            : [
                'data-pandoc-revision-datetime' => $datetime[1],
                'data-pandoc-revision-kind' => $datetime[0],
            ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlRevisionCiteAttribute(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $normalized = self::normalizeUrlAttributeValue($value);
        if ($normalized === '' || !self::isSafeFetchUrl($normalized)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => 'cite',
            ], $element);

            return null;
        }

        if ($normalized !== $value) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'normalized-url',
                'tag' => $tagName,
                'attribute' => 'cite',
            ], $element);
        }

        if ($baseUrl !== null) {
            $normalized = self::resolveRelativeUrl($baseUrl, $normalized);
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'revision-metadata-review',
            'tag' => $tagName,
            'attribute' => 'cite',
            'reason' => 'revision-cite-preserved-as-metadata',
        ], $element);

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string}|null
     */
    private static function normalizeHtmlRevisionDatetimeAttribute(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?array {
        $datetime = self::normalizeHtmlTimeDatetimeValue(self::cleanHtmlMetadataAttribute($value));
        if ($datetime === null || !in_array($datetime[0], ['date', 'global-datetime', 'local-datetime'], true)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => 'datetime',
            ], $element);

            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'revision-metadata-review',
            'tag' => $tagName,
            'attribute' => 'datetime',
            'kind' => $datetime[0],
            'reason' => 'revision-datetime-preserved-as-metadata',
        ], $element);

        return $datetime;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlTrackKindAttribute(
        string $value,
        \DOMElement $element,
        array &$diagnostics
    ): ?string {
        $kind = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($kind === '') {
            return null;
        }

        if (in_array($kind, ['subtitles', 'captions', 'descriptions', 'chapters', 'metadata'], true)) {
            return $kind;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => 'track',
            'attribute' => 'kind',
            'value' => $kind,
        ], $element);

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlTrackLanguageAttribute(
        string $value,
        \DOMElement $element,
        array &$diagnostics
    ): ?string {
        $language = self::cleanHtmlMetadataAttribute($value);
        if ($language === '') {
            return null;
        }

        $parts = explode('-', $language);
        $canonical = [];
        foreach ($parts as $index => $part) {
            if (preg_match('/^[A-Za-z0-9]{1,8}$/', $part) !== 1) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'track',
                    'attribute' => 'srclang',
                    'value' => $language,
                ], $element);

                return null;
            }

            if ($index === 0) {
                if (preg_match('/^(?:[A-Za-z]{2,8}|x)$/', $part) !== 1) {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'unsafe-attribute',
                        'tag' => 'track',
                        'attribute' => 'srclang',
                        'value' => $language,
                    ], $element);

                    return null;
                }

                $canonical[] = strtolower($part);
                continue;
            }

            if (strlen($part) === 4 && preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $canonical[] = ucfirst(strtolower($part));
                continue;
            }
            if (
                (strlen($part) === 2 && preg_match('/^[A-Za-z]{2}$/', $part) === 1)
                || (strlen($part) === 3 && preg_match('/^[0-9]{3}$/', $part) === 1)
            ) {
                $canonical[] = strtoupper($part);
                continue;
            }

            $canonical[] = strtolower($part);
        }

        if ($canonical[0] === 'x' && count($canonical) === 1) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'track',
                'attribute' => 'srclang',
                'value' => $language,
            ], $element);

            return null;
        }

        return implode('-', $canonical);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string}|null
     */
    private static function normalizeHtmlTimeDatetimeAttribute(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?array {
        $datetime = self::normalizeHtmlTimeDatetimeValue(self::cleanHtmlMetadataAttribute($value));
        if ($datetime === null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => 'datetime',
            ], $element);

            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'time-metadata-review',
            'tag' => $tagName,
            'attribute' => 'datetime',
            'kind' => $datetime[0],
        ], $element);

        return $datetime;
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private static function normalizeHtmlTimeDatetimeValue(string $value): ?array
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/[<>{}`]/', $value) === 1) {
            return null;
        }

        $datePattern = '([0-9]{4})-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])';
        $timePattern = '((?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9](?:\.[0-9]{1,3})?)?)';
        $timezonePattern = '([Zz]|[+-](?:[01][0-9]|2[0-3]):?[0-5][0-9])';

        if (preg_match('/^' . $datePattern . '[T ]' . $timePattern . $timezonePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidHtmlDate((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return [
                'global-datetime',
                self::formatHtmlDate((string) $matches[1], (string) $matches[2], (string) $matches[3])
                    . 'T' . (string) $matches[4]
                    . self::normalizeHtmlTimezone((string) $matches[5]),
            ];
        }

        if (preg_match('/^' . $datePattern . '[T ]' . $timePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidHtmlDate((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return [
                'local-datetime',
                self::formatHtmlDate((string) $matches[1], (string) $matches[2], (string) $matches[3])
                    . 'T' . (string) $matches[4],
            ];
        }

        if (preg_match('/^' . $datePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidHtmlDate((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return ['date', self::formatHtmlDate((string) $matches[1], (string) $matches[2], (string) $matches[3])];
        }

        if (preg_match('/^([0-9]{4})-(0[1-9]|1[0-2])$/', $value, $matches) === 1) {
            return ['month', (string) $matches[1] . '-' . (string) $matches[2]];
        }

        if (preg_match('/^([0-9]{4})-W(0[1-9]|[1-4][0-9]|5[0-3])$/', $value, $matches) === 1) {
            $year = (int) $matches[1];
            $week = (int) $matches[2];
            $date = (new \DateTimeImmutable())->setISODate($year, $week, 1);
            if ((int) $date->format('o') !== $year || (int) $date->format('W') !== $week) {
                return null;
            }

            return ['week', (string) $matches[1] . '-W' . (string) $matches[2]];
        }

        if (preg_match('/^[0-9]{4}$/', $value) === 1) {
            return ['year', $value];
        }

        if (preg_match('/^' . $timePattern . '$/', $value, $matches) === 1) {
            return ['time', (string) $matches[1]];
        }

        $duration = self::normalizeHtmlDurationValue($value);
        if ($duration !== null) {
            return ['duration', $duration];
        }

        return null;
    }

    private static function isValidHtmlDate(string $year, string $month, string $day): bool
    {
        return checkdate((int) $month, (int) $day, (int) $year);
    }

    private static function formatHtmlDate(string $year, string $month, string $day): string
    {
        return $year . '-' . $month . '-' . $day;
    }

    private static function normalizeHtmlTimezone(string $timezone): string
    {
        if (strtoupper($timezone) === 'Z') {
            return 'Z';
        }

        if (preg_match('/^([+-])([0-9]{2}):?([0-9]{2})$/', $timezone, $matches) === 1) {
            return (string) $matches[1] . (string) $matches[2] . ':' . (string) $matches[3];
        }

        return $timezone;
    }

    private static function normalizeHtmlDurationValue(string $value): ?string
    {
        $duration = strtoupper($value);
        if ($duration === '' || preg_match('/^[P0-9YMWDTHS.]+$/', $duration) !== 1) {
            return null;
        }

        if (preg_match('/^P[0-9]+W$/', $duration) === 1) {
            return $duration;
        }

        if (str_contains($duration, 'W')) {
            return null;
        }

        if (preg_match('/^P(?=.*[0-9])(?:[0-9]+Y)?(?:[0-9]+M)?(?:[0-9]+D)?(?:T(?:[0-9]+H)?(?:[0-9]+M)?(?:[0-9]+(?:\.[0-9]{1,3})?S)?)?$/', $duration) !== 1) {
            return null;
        }

        if (str_ends_with($duration, 'T')) {
            return null;
        }

        return $duration;
    }

    private static function normalizeUrlAttributeValue(string $value): string
    {
        $withoutControls = preg_replace('/[\x00-\x1F\x7F]+/', '', $value) ?? $value;
        $trimmed = trim($withoutControls);
        $compact = preg_replace('/[\x00-\x20]+/', '', $value) ?? $value;

        if (
            preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $compact) === 1
            && preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) !== 1
        ) {
            return $compact;
        }

        return $trimmed;
    }

    private static function assertSafeHtmlSource(string $html, string $label): void
    {
        self::assertNoNullByte($html, $label);
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        $declarationScanSource = self::sourceForDeclarationScan($preflight);
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function assertSafeXmlSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        $declarationScanSource = self::sourceForDeclarationScan($xml);
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException('XML fragments with DTD or entity declarations are not supported');
        }
    }

    private static function sourceForDeclarationScan(string $source): string
    {
        $length = strlen($source);
        $scan = '';
        $offset = 0;

        while ($offset < $length) {
            if (str_starts_with(substr($source, $offset, 4), '<!--')) {
                $commentEnd = strpos($source, '-->', $offset + 4);
                if ($commentEnd === false) {
                    $scan .= substr($source, $offset);
                    break;
                }

                $commentLength = $commentEnd + 3 - $offset;
                $scan .= str_repeat(' ', $commentLength);
                $offset += $commentLength;
                continue;
            }

            if (str_starts_with(substr($source, $offset, 9), '<![CDATA[')) {
                $cdataEnd = strpos($source, ']]>', $offset + 9);
                if ($cdataEnd === false) {
                    $scan .= substr($source, $offset);
                    break;
                }

                $cdataLength = $cdataEnd + 3 - $offset;
                $scan .= str_repeat(' ', $cdataLength);
                $offset += $cdataLength;
                continue;
            }

            if ($source[$offset] === '<' && self::isHtmlTagStartForDeclarationScan($source, $offset)) {
                [$tagSource, $nextOffset] = self::maskQuotedTagAttributeValuesForDeclarationScan($source, $offset);
                $scan .= $tagSource;
                $offset = $nextOffset;
                continue;
            }

            $scan .= $source[$offset];
            ++$offset;
        }

        return $scan;
    }

    private static function isHtmlTagStartForDeclarationScan(string $source, int $offset): bool
    {
        $length = strlen($source);
        $nameOffset = $offset + 1;
        if ($nameOffset >= $length) {
            return false;
        }

        if ($source[$nameOffset] === '/') {
            ++$nameOffset;
        }

        return $nameOffset < $length && preg_match('/[A-Za-z]/', $source[$nameOffset]) === 1;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function maskQuotedTagAttributeValuesForDeclarationScan(string $source, int $offset): array
    {
        $length = strlen($source);
        $tag = '';

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '"' || $char === "'") {
                $quoteEnd = strpos($source, $char, $offset + 1);
                if ($quoteEnd === false) {
                    $tag .= substr($source, $offset);

                    return [$tag, $length];
                }

                $tag .= $char . str_repeat(' ', $quoteEnd - $offset - 1) . $char;
                $offset = $quoteEnd + 1;
                continue;
            }

            $tag .= $char;
            ++$offset;
            if ($char === '>') {
                break;
            }
        }

        return [$tag, $offset];
    }

    private static function assertNoNullByte(string $source, string $label): void
    {
        if (str_contains($source, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }
    }

    private static function isSafeAttributeName(string $name): bool
    {
        return preg_match('/^(?:[A-Za-z_:][A-Za-z0-9:._-]*|aria-[A-Za-z0-9._-]+|data-[A-Za-z0-9._:-]+)$/', $name) === 1;
    }

    private static function isHtmlAriaMetadataAttribute(string $name): bool
    {
        $attribute = strtolower($name);

        return $attribute === 'role' || str_starts_with($attribute, 'aria-');
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlAriaMetadataAttribute(
        string $name,
        string $value,
        string $tagName,
        array &$diagnostics
    ): array {
        $attribute = strtolower($name);
        if ($attribute === 'role') {
            $roles = self::normalizeHtmlAriaRoleTokens($value, $tagName, $diagnostics);
            if ($roles === null) {
                return [];
            }

            self::addHtmlAriaMetadataDiagnostic($diagnostics, $tagName, 'role');

            return ['data-pandoc-aria-role' => $roles];
        }

        $metadataValue = null;
        if (self::isHtmlAriaTextAttribute($attribute)) {
            $metadataValue = self::normalizeHtmlAriaTextValue($value);
        } elseif (self::isHtmlAriaIdrefAttribute($attribute)) {
            $metadataValue = self::normalizeHtmlAriaIdrefValue($attribute, $value, $tagName, $diagnostics);
        } elseif (self::isHtmlAriaTokenAttribute($attribute)) {
            $metadataValue = self::normalizeHtmlAriaTokenValue($attribute, $value, $tagName, $diagnostics);
        } elseif (self::isHtmlAriaIntegerAttribute($attribute)) {
            $metadataValue = self::normalizeHtmlAriaIntegerValue($attribute, $value, $tagName, $diagnostics);
        } elseif (self::isHtmlAriaNumberAttribute($attribute)) {
            $metadataValue = self::normalizeHtmlAriaNumberValue($attribute, $value, $tagName, $diagnostics);
        } else {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attribute,
                'reason' => 'unsupported-aria-review-attribute',
            ];

            return [];
        }

        if ($metadataValue === null) {
            return [];
        }

        self::addHtmlAriaMetadataDiagnostic($diagnostics, $tagName, $attribute);

        return ['data-pandoc-' . $attribute => $metadataValue];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAriaRoleTokens(string $value, string $tagName, array &$diagnostics): ?string
    {
        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            return null;
        }

        $roles = [];
        foreach ($tokens as $token) {
            $role = strtolower($token);
            if (!self::isReviewableHtmlAriaRole($role)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => 'role',
                    'token' => $token,
                ];
                continue;
            }

            if (!in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }

        return $roles === [] ? null : implode(' ', $roles);
    }

    private static function isReviewableHtmlAriaRole(string $role): bool
    {
        return in_array($role, [
            'alert',
            'alertdialog',
            'application',
            'article',
            'banner',
            'blockquote',
            'button',
            'caption',
            'cell',
            'checkbox',
            'code',
            'columnheader',
            'combobox',
            'command',
            'complementary',
            'contentinfo',
            'definition',
            'deletion',
            'dialog',
            'directory',
            'document',
            'emphasis',
            'feed',
            'figure',
            'form',
            'generic',
            'grid',
            'gridcell',
            'group',
            'heading',
            'img',
            'insertion',
            'link',
            'list',
            'listbox',
            'listitem',
            'log',
            'main',
            'mark',
            'marquee',
            'math',
            'menu',
            'menubar',
            'menuitem',
            'menuitemcheckbox',
            'menuitemradio',
            'meter',
            'navigation',
            'none',
            'note',
            'option',
            'paragraph',
            'presentation',
            'progressbar',
            'radio',
            'radiogroup',
            'region',
            'row',
            'rowgroup',
            'rowheader',
            'scrollbar',
            'search',
            'searchbox',
            'separator',
            'slider',
            'spinbutton',
            'status',
            'strong',
            'subscript',
            'superscript',
            'switch',
            'tab',
            'table',
            'tablist',
            'tabpanel',
            'term',
            'textbox',
            'time',
            'timer',
            'toolbar',
            'tooltip',
            'tree',
            'treegrid',
            'treeitem',
        ], true);
    }

    private static function isHtmlAriaTextAttribute(string $attribute): bool
    {
        return in_array($attribute, [
            'aria-braillelabel',
            'aria-description',
            'aria-keyshortcuts',
            'aria-label',
            'aria-placeholder',
            'aria-roledescription',
            'aria-valuetext',
        ], true);
    }

    private static function normalizeHtmlAriaTextValue(string $value): ?string
    {
        $text = self::cleanHtmlMetadataAttribute($value);
        if ($text === '' || strlen($text) > 512 || preg_match('/[<>{}`]/', $text) === 1) {
            return null;
        }

        return $text;
    }

    private static function isHtmlAriaIdrefAttribute(string $attribute): bool
    {
        return in_array($attribute, [
            'aria-activedescendant',
            'aria-controls',
            'aria-describedby',
            'aria-details',
            'aria-errormessage',
            'aria-flowto',
            'aria-labelledby',
            'aria-owns',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAriaIdrefValue(
        string $attribute,
        string $value,
        string $tagName,
        array &$diagnostics
    ): ?string {
        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (!self::isSafeHtmlAriaIdToken($token)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $attribute,
                    'token' => $token,
                ];
                continue;
            }

            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        return $normalized === [] ? null : implode(' ', $normalized);
    }

    private static function isSafeHtmlAriaIdToken(string $token): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $token) === 1;
    }

    private static function isHtmlAriaTokenAttribute(string $attribute): bool
    {
        return self::htmlAriaTokenStates($attribute) !== null;
    }

    /**
     * @return list<string>|null
     */
    private static function htmlAriaTokenStates(string $attribute): ?array
    {
        return match ($attribute) {
            'aria-autocomplete' => ['inline', 'list', 'both', 'none'],
            'aria-busy',
            'aria-disabled',
            'aria-expanded',
            'aria-grabbed',
            'aria-hidden',
            'aria-modal',
            'aria-multiline',
            'aria-multiselectable',
            'aria-readonly',
            'aria-required',
            'aria-selected' => ['true', 'false'],
            'aria-checked',
            'aria-pressed' => ['true', 'false', 'mixed'],
            'aria-current' => ['false', 'true', 'page', 'step', 'location', 'date', 'time'],
            'aria-dropeffect' => ['copy', 'execute', 'link', 'move', 'none', 'popup'],
            'aria-haspopup' => ['false', 'true', 'menu', 'listbox', 'tree', 'grid', 'dialog'],
            'aria-invalid' => ['false', 'true', 'grammar', 'spelling'],
            'aria-live' => ['off', 'polite', 'assertive'],
            'aria-orientation' => ['horizontal', 'vertical'],
            'aria-relevant' => ['additions', 'removals', 'text', 'all'],
            'aria-sort' => ['none', 'ascending', 'descending', 'other'],
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAriaTokenValue(
        string $attribute,
        string $value,
        string $tagName,
        array &$diagnostics
    ): ?string {
        $allowed = self::htmlAriaTokenStates($attribute);
        if ($allowed === null) {
            return null;
        }

        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            $state = strtolower($token);
            if (!in_array($state, $allowed, true)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $attribute,
                    'value' => $state,
                ];
                continue;
            }

            if (!in_array($state, $normalized, true)) {
                $normalized[] = $state;
            }
        }

        if ($normalized === []) {
            return null;
        }

        return in_array($attribute, ['aria-dropeffect', 'aria-relevant'], true)
            ? implode(' ', $normalized)
            : $normalized[0];
    }

    private static function isHtmlAriaIntegerAttribute(string $attribute): bool
    {
        return in_array($attribute, [
            'aria-colcount',
            'aria-colindex',
            'aria-colspan',
            'aria-level',
            'aria-posinset',
            'aria-rowcount',
            'aria-rowindex',
            'aria-rowspan',
            'aria-setsize',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAriaIntegerValue(
        string $attribute,
        string $value,
        string $tagName,
        array &$diagnostics
    ): ?string {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if (preg_match('/^-?[0-9]+$/', $cleaned) !== 1) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attribute,
            ];

            return null;
        }

        $number = (int) $cleaned;
        $allowsMinusOne = in_array($attribute, ['aria-colcount', 'aria-rowcount', 'aria-setsize'], true);
        if ($number < ($allowsMinusOne ? -1 : 1) || $number === 0) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attribute,
                'value' => $cleaned,
            ];

            return null;
        }

        return (string) $number;
    }

    private static function isHtmlAriaNumberAttribute(string $attribute): bool
    {
        return in_array($attribute, ['aria-valuemax', 'aria-valuemin', 'aria-valuenow'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAriaNumberValue(
        string $attribute,
        string $value,
        string $tagName,
        array &$diagnostics
    ): ?string {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if (preg_match('/^-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/', $cleaned) !== 1) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attribute,
            ];

            return null;
        }

        $normalized = rtrim(rtrim($cleaned, '0'), '.');
        if ($normalized === '' || $normalized === '-') {
            $normalized = '0';
        }
        if (str_starts_with($normalized, '.')) {
            $normalized = '0' . $normalized;
        }
        if (str_starts_with($normalized, '-.')) {
            $normalized = '-0' . substr($normalized, 1);
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlAriaMetadataDiagnostic(array &$diagnostics, string $tagName, string $attributeName): void
    {
        $diagnostics[] = [
            'code' => 'aria-metadata-review',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'reason' => 'aria-attribute-preserved-as-review-metadata',
        ];
    }

    private static function isHtmlCustomElementMetadataAttribute(
        \DOMElement $element,
        string $tagName,
        string $name,
        ?string $foreignContext
    ): bool {
        if ($foreignContext !== null) {
            return false;
        }

        $attribute = strtolower($name);
        if ($attribute === 'is') {
            return true;
        }
        if (!in_array($attribute, ['part', 'exportparts'], true)) {
            return false;
        }

        return self::isHtmlCustomElementName($tagName) || $element->hasAttribute('is');
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlCustomElementMetadataAttribute(
        string $name,
        string $value,
        string $tagName,
        array &$diagnostics
    ): array {
        $attribute = strtolower($name);
        if ($attribute === 'is') {
            $customName = self::normalizeHtmlCustomElementName($value);
            if ($customName === null) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => 'is',
                    'reason' => 'invalid-custom-element-name',
                ];

                return [];
            }

            self::addHtmlCustomElementMetadataDiagnostic($diagnostics, $tagName, 'is');

            return ['data-pandoc-custom-is' => $customName];
        }

        if ($attribute === 'part') {
            $parts = self::normalizeHtmlCustomPartTokenList($value, $tagName, $diagnostics);
            if ($parts === null) {
                return [];
            }

            self::addHtmlCustomElementMetadataDiagnostic($diagnostics, $tagName, 'part');

            return ['data-pandoc-custom-part' => $parts];
        }

        $exportedParts = self::normalizeHtmlCustomExportParts($value, $tagName, $diagnostics);
        if ($exportedParts === null) {
            return [];
        }

        self::addHtmlCustomElementMetadataDiagnostic($diagnostics, $tagName, 'exportparts');

        return ['data-pandoc-custom-exportparts' => $exportedParts];
    }

    private static function normalizeHtmlCustomElementName(string $value): ?string
    {
        $name = strtolower(self::cleanHtmlMetadataAttribute($value));

        return self::isHtmlCustomElementName($name) ? $name : null;
    }

    private static function isHtmlCustomElementName(string $name): bool
    {
        $name = strtolower($name);
        if (preg_match('/^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/', $name) !== 1) {
            return false;
        }

        return !in_array($name, [
            'annotation-xml',
            'color-profile',
            'font-face',
            'font-face-src',
            'font-face-uri',
            'font-face-format',
            'font-face-name',
            'missing-glyph',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlCustomPartTokenList(string $value, string $tagName, array &$diagnostics): ?string
    {
        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (!self::isSafeHtmlCustomPartToken($token)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => 'part',
                    'token' => $token,
                ];
                continue;
            }
            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        return $normalized === [] ? null : implode(' ', $normalized);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlCustomExportParts(string $value, string $tagName, array &$diagnostics): ?string
    {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '') {
            return null;
        }

        $mappings = [];
        foreach (explode(',', $cleaned) as $mapping) {
            $mapping = trim($mapping);
            if ($mapping === '') {
                continue;
            }

            $parts = array_map('trim', explode(':', $mapping, 2));
            $source = $parts[0] ?? '';
            $target = $parts[1] ?? '';
            if (!self::isSafeHtmlCustomPartToken($source) || ($target !== '' && !self::isSafeHtmlCustomPartToken($target))) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => 'exportparts',
                    'token' => $mapping,
                ];
                continue;
            }

            $normalized = $target === '' ? $source : $source . ': ' . $target;
            if (!in_array($normalized, $mappings, true)) {
                $mappings[] = $normalized;
            }
        }

        return $mappings === [] ? null : implode(', ', $mappings);
    }

    private static function isSafeHtmlCustomPartToken(string $token): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $token) === 1;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlCustomElementMetadataDiagnostic(
        array &$diagnostics,
        string $tagName,
        string $attributeName
    ): void {
        $diagnostics[] = [
            'code' => 'custom-element-review',
            'tag' => $tagName,
            'attribute' => $attributeName,
            'reason' => 'custom-element-hook-preserved-as-review-metadata',
        ];
    }

    private static function isHtmlSemanticMetadataAttribute(string $name): bool
    {
        return in_array(strtolower($name), [
            'about',
            'datatype',
            'inlist',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'prefix',
            'property',
            'resource',
            'typeof',
            'vocab',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlSemanticMetadataAttribute(
        string $name,
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics,
        ?string $baseUrl
    ): array {
        $name = strtolower($name);
        if ($name === 'itemscope' || $name === 'inlist') {
            self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $name, $element);

            return [
                $name === 'itemscope' ? 'data-pandoc-microdata-scope' : 'data-pandoc-rdfa-inlist' => 'true',
            ];
        }

        if ($name === 'itemtype') {
            $tokens = self::normalizeHtmlSemanticUrlTokenList($value, $tagName, $name, $element, $diagnostics, $baseUrl);

            return $tokens === null ? [] : ['data-pandoc-microdata-type' => $tokens];
        }

        if ($name === 'itemid') {
            $url = self::normalizeHtmlSemanticUrl($value, $tagName, $name, $element, $diagnostics, $baseUrl);
            if ($url !== null) {
                self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $name, $element);
            }

            return $url === null ? [] : ['data-pandoc-microdata-id' => $url];
        }

        if ($name === 'about' || $name === 'resource' || $name === 'vocab') {
            $url = self::normalizeHtmlSemanticUrl($value, $tagName, $name, $element, $diagnostics, $baseUrl);
            if ($url === null) {
                return [];
            }

            self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $name, $element);

            return ['data-pandoc-rdfa-' . $name => $url];
        }

        if ($name === 'prefix') {
            $prefixes = self::normalizeHtmlRdfaPrefixMap($value, $tagName, $element, $diagnostics, $baseUrl);

            return $prefixes === null ? [] : ['data-pandoc-rdfa-prefix' => $prefixes];
        }

        $targetName = match ($name) {
            'datatype' => 'data-pandoc-rdfa-datatype',
            'itemprop' => 'data-pandoc-microdata-property',
            'itemref' => 'data-pandoc-microdata-ref',
            'property' => 'data-pandoc-rdfa-property',
            'typeof' => 'data-pandoc-rdfa-typeof',
            default => null,
        };
        if ($targetName === null) {
            return [];
        }

        $tokens = self::normalizeHtmlSemanticTermTokenList($value, $tagName, $name, $element, $diagnostics);

        return $tokens === null ? [] : [$targetName => $tokens];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addSemanticMetadataDiagnostic(
        array &$diagnostics,
        string $tagName,
        string $attributeName,
        \DOMElement $element
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'semantic-metadata-review',
            'tag' => $tagName,
            'attribute' => $attributeName,
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addSemanticUnsafeAttributeDiagnostic(
        array &$diagnostics,
        string $tagName,
        string $attributeName,
        \DOMElement $element,
        array $extra = []
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => $attributeName,
            ...$extra,
        ], $element);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addSemanticUrlDiagnostic(
        array &$diagnostics,
        string $code,
        string $tagName,
        string $attributeName,
        \DOMElement $element
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => $code,
            'tag' => $tagName,
            'attribute' => $attributeName,
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlMicrodataValueMetadata(
        \DOMElement $element,
        string $tagName,
        array &$attrs,
        array $children,
        array &$diagnostics
    ): void {
        if (!array_key_exists('data-pandoc-microdata-property', $attrs)) {
            return;
        }
        if (array_key_exists('data-pandoc-microdata-scope', $attrs)) {
            return;
        }

        $value = self::htmlMicrodataItemValue($tagName, $attrs, $children);
        if ($value === null) {
            return;
        }

        $attrs['data-pandoc-microdata-value'] = $value;
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'microdata-value-review',
            'tag' => $tagName,
            'attribute' => 'itemprop',
            'metadataAttribute' => 'data-pandoc-microdata-value',
            'reason' => 'microdata-item-value-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlMicrodataItemSummaryMetadata(
        \DOMElement $element,
        string $tagName,
        array &$attrs,
        array $children,
        array &$diagnostics
    ): void {
        if (!array_key_exists('data-pandoc-microdata-scope', $attrs)) {
            return;
        }

        $summary = self::htmlMicrodataItemPropertySummary($children);
        if ($summary['properties'] === []) {
            return;
        }

        $properties = implode(' ', $summary['properties']);
        if (strlen($properties) <= 512) {
            $attrs['data-pandoc-microdata-properties'] = $properties;
        }
        $attrs['data-pandoc-microdata-property-count'] = (string) $summary['propertyCount'];
        if ($summary['valueCount'] > 0) {
            $attrs['data-pandoc-microdata-value-count'] = (string) $summary['valueCount'];
        }
        if ($summary['nestedItemCount'] > 0) {
            $attrs['data-pandoc-microdata-nested-item-count'] = (string) $summary['nestedItemCount'];
        }
        self::markHtmlMicrodataRepeatedPropertyMetadata(
            $element,
            $tagName,
            $attrs,
            $summary['repeatedProperties'],
            $diagnostics
        );

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'microdata-item-review',
            'tag' => $tagName,
            'attribute' => 'itemscope',
            'metadataAttribute' => 'data-pandoc-microdata-properties',
            'reason' => 'microdata-item-property-summary-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlMicrodataItemRefMetadata(
        \DOMElement $element,
        string $tagName,
        array &$attrs,
        array &$diagnostics
    ): void {
        if (!array_key_exists('data-pandoc-microdata-scope', $attrs)) {
            return;
        }
        if (!isset($attrs['data-pandoc-microdata-ref'])) {
            return;
        }

        $references = self::splitHtmlSemanticTokens($attrs['data-pandoc-microdata-ref']);
        if ($references === []) {
            return;
        }

        $summary = self::htmlMicrodataItemRefSummary($element, $references);
        $attrs['data-pandoc-microdata-ref-count'] = (string) count($references);
        if ($summary['resolved'] !== []) {
            $resolved = implode(' ', $summary['resolved']);
            if (strlen($resolved) <= 512) {
                $attrs['data-pandoc-microdata-ref-resolved'] = $resolved;
            }
            $attrs['data-pandoc-microdata-ref-resolved-count'] = (string) count($summary['resolved']);
        }
        if ($summary['missing'] !== []) {
            $missing = implode(' ', $summary['missing']);
            if (strlen($missing) <= 512) {
                $attrs['data-pandoc-microdata-ref-missing'] = $missing;
            }
            $attrs['data-pandoc-microdata-ref-missing-count'] = (string) count($summary['missing']);
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'microdata-itemref-review',
            'tag' => $tagName,
            'attribute' => 'itemref',
            'metadataAttribute' => 'data-pandoc-microdata-ref-count',
            'referenceCount' => count($references),
            'resolvedCount' => count($summary['resolved']),
            'missingCount' => count($summary['missing']),
            'reason' => 'microdata-itemref-inventory-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param list<string> $references
     * @return array{resolved:list<string>, missing:list<string>}
     */
    private static function htmlMicrodataItemRefSummary(\DOMElement $element, array $references): array
    {
        $resolved = [];
        $missing = [];
        $document = $element->ownerDocument;
        $ids = $document instanceof \DOMDocument ? self::htmlDocumentElementIdSet($document) : [];
        foreach ($references as $reference) {
            if (isset($ids[$reference])) {
                $resolved[] = $reference;
                continue;
            }

            $missing[] = $reference;
        }

        return [
            'resolved' => $resolved,
            'missing' => $missing,
        ];
    }

    /**
     * @return array<string, true>
     */
    private static function htmlDocumentElementIdSet(\DOMDocument $document): array
    {
        $ids = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $id = $element->getAttribute('id');
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlMicrodataItemRefPropertySummaryMetadata(array &$nodes, array &$diagnostics): void
    {
        $nodesById = [];
        self::collectHtmlNormalizedNodesById($nodes, $nodesById);
        if ($nodesById === []) {
            return;
        }

        self::markHtmlMicrodataItemRefPropertySummaryMetadataInNodes($nodes, $nodesById, $diagnostics);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, array<string, mixed>> $nodesById
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlMicrodataItemRefPropertySummaryMetadataInNodes(
        array &$nodes,
        array $nodesById,
        array &$diagnostics
    ): void {
        foreach ($nodes as &$node) {
            if (($node['type'] ?? '') !== 'element') {
                continue;
            }

            self::mergeHtmlMicrodataItemRefPropertySummary($node, $nodesById, $diagnostics);
            if (is_array($node['children'] ?? null)) {
                self::markHtmlMicrodataItemRefPropertySummaryMetadataInNodes($node['children'], $nodesById, $diagnostics);
            }
        }
        unset($node);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, array<string, mixed>> $nodesById
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function mergeHtmlMicrodataItemRefPropertySummary(
        array &$node,
        array $nodesById,
        array &$diagnostics
    ): void {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        if (!isset($attrs['data-pandoc-microdata-scope'], $attrs['data-pandoc-microdata-ref'])) {
            return;
        }

        $references = self::splitHtmlSemanticTokens((string) $attrs['data-pandoc-microdata-ref']);
        if ($references === []) {
            return;
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $descendantIds = self::htmlNormalizedElementIdSet($children);
        $referencedNodes = [];
        $mergedReferences = [];
        foreach ($references as $reference) {
            if (isset($descendantIds[$reference]) || !isset($nodesById[$reference])) {
                continue;
            }

            $referencedNodes[] = $nodesById[$reference];
            $mergedReferences[] = $reference;
        }
        if ($referencedNodes === []) {
            return;
        }

        $summary = self::htmlMicrodataItemPropertySummary($referencedNodes);
        if ($summary['propertyCount'] === 0) {
            return;
        }

        $properties = self::splitHtmlSemanticTokens((string) ($attrs['data-pandoc-microdata-properties'] ?? ''));
        foreach ($summary['properties'] as $property) {
            if (!in_array($property, $properties, true)) {
                $properties[] = $property;
            }
        }
        if ($properties !== []) {
            $propertyMetadata = implode(' ', $properties);
            if (strlen($propertyMetadata) <= 512) {
                $attrs['data-pandoc-microdata-properties'] = $propertyMetadata;
            }
        }

        $attrs['data-pandoc-microdata-property-count'] = (string) (
            self::htmlMicrodataMetadataCounter($attrs, 'data-pandoc-microdata-property-count')
            + $summary['propertyCount']
        );

        $valueCount = self::htmlMicrodataMetadataCounter($attrs, 'data-pandoc-microdata-value-count')
            + $summary['valueCount'];
        if ($valueCount > 0) {
            $attrs['data-pandoc-microdata-value-count'] = (string) $valueCount;
        }

        $nestedItemCount = self::htmlMicrodataMetadataCounter($attrs, 'data-pandoc-microdata-nested-item-count')
            + $summary['nestedItemCount'];
        if ($nestedItemCount > 0) {
            $attrs['data-pandoc-microdata-nested-item-count'] = (string) $nestedItemCount;
        }

        $combinedSummary = self::htmlMicrodataItemPropertySummary($children);
        foreach ($summary['propertyCounts'] as $property => $count) {
            $combinedSummary['propertyCounts'][$property] = ($combinedSummary['propertyCounts'][$property] ?? 0) + $count;
        }
        $repeatedProperties = self::htmlMicrodataRepeatedProperties($combinedSummary['propertyCounts']);
        $previousRepeatedMetadata = $attrs['data-pandoc-microdata-repeated-properties'] ?? null;
        $previousRepeatedCount = $attrs['data-pandoc-microdata-repeated-property-count'] ?? null;
        $repeatedMetadataChanged = false;
        if ($repeatedProperties !== []) {
            $repeatedMetadata = implode(' ', $repeatedProperties);
            if (strlen($repeatedMetadata) <= 512) {
                $attrs['data-pandoc-microdata-repeated-properties'] = $repeatedMetadata;
                $repeatedMetadataChanged = $repeatedMetadata !== $previousRepeatedMetadata;
            }
            $attrs['data-pandoc-microdata-repeated-property-count'] = (string) count($repeatedProperties);
            $repeatedMetadataChanged = $repeatedMetadataChanged
                || (string) count($repeatedProperties) !== $previousRepeatedCount;
        }

        $node['attrs'] = $attrs;
        $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
            'code' => 'microdata-itemref-property-review',
            'tag' => (string) ($node['name'] ?? ''),
            'attribute' => 'itemref',
            'metadataAttribute' => 'data-pandoc-microdata-properties',
            'referenceCount' => count($references),
            'mergedReferenceCount' => count($mergedReferences),
            'propertyCount' => $summary['propertyCount'],
            'valueCount' => $summary['valueCount'],
            'nestedItemCount' => $summary['nestedItemCount'],
            'reason' => 'microdata-itemref-properties-preserved-as-review-metadata',
        ], $node);
        if ($repeatedProperties !== [] && $repeatedMetadataChanged) {
            $diagnostics[] = self::diagnosticWithNormalizedNodeLine([
                'code' => 'microdata-repeated-property-review',
                'tag' => (string) ($node['name'] ?? ''),
                'attribute' => 'itemref',
                'metadataAttribute' => 'data-pandoc-microdata-repeated-properties',
                'repeatedPropertyCount' => count($repeatedProperties),
                'reason' => 'microdata-repeated-properties-preserved-as-review-metadata',
            ], $node);
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, array<string, mixed>> $nodesById
     */
    private static function collectHtmlNormalizedNodesById(array $nodes, array &$nodesById): void
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'element') {
                continue;
            }

            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $id = $attrs['id'] ?? null;
            if (is_string($id) && $id !== '' && !isset($nodesById[$id])) {
                $nodesById[$id] = $node;
            }

            if (is_array($node['children'] ?? null)) {
                self::collectHtmlNormalizedNodesById($node['children'], $nodesById);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return array<string, true>
     */
    private static function htmlNormalizedElementIdSet(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'element') {
                continue;
            }

            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $id = $attrs['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $ids[$id] = true;
            }

            if (is_array($node['children'] ?? null)) {
                $ids += self::htmlNormalizedElementIdSet($node['children']);
            }
        }

        return $ids;
    }

    /**
     * @param array<string, string> $attrs
     */
    private static function htmlMicrodataMetadataCounter(array $attrs, string $name): int
    {
        $value = $attrs[$name] ?? null;

        return is_string($value) && ctype_digit($value) ? (int) $value : 0;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return array{properties:list<string>, propertyCounts:array<string, int>, repeatedProperties:list<string>, propertyCount:int, valueCount:int, nestedItemCount:int}
     */
    private static function htmlMicrodataItemPropertySummary(array $nodes): array
    {
        $summary = [
            'properties' => [],
            'propertyCounts' => [],
            'repeatedProperties' => [],
            'propertyCount' => 0,
            'valueCount' => 0,
            'nestedItemCount' => 0,
        ];
        self::collectHtmlMicrodataItemPropertySummary($nodes, $summary);
        $summary['repeatedProperties'] = self::htmlMicrodataRepeatedProperties($summary['propertyCounts']);

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array{properties:list<string>, propertyCounts:array<string, int>, repeatedProperties:list<string>, propertyCount:int, valueCount:int, nestedItemCount:int} $summary
     */
    private static function collectHtmlMicrodataItemPropertySummary(array $nodes, array &$summary): void
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'element') {
                continue;
            }

            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $hasProperty = isset($attrs['data-pandoc-microdata-property']);
            $propertyCount = 0;
            if ($hasProperty) {
                foreach (self::splitHtmlSemanticTokens((string) $attrs['data-pandoc-microdata-property']) as $property) {
                    if (!in_array($property, $summary['properties'], true)) {
                        $summary['properties'][] = $property;
                    }
                    $summary['propertyCounts'][$property] = ($summary['propertyCounts'][$property] ?? 0) + 1;
                    ++$propertyCount;
                    ++$summary['propertyCount'];
                }
                if (isset($attrs['data-pandoc-microdata-value'])) {
                    $summary['valueCount'] += $propertyCount;
                }
            }

            if (isset($attrs['data-pandoc-microdata-scope'])) {
                if ($hasProperty) {
                    ++$summary['nestedItemCount'];
                }
                continue;
            }

            if (is_array($node['children'] ?? null)) {
                self::collectHtmlMicrodataItemPropertySummary($node['children'], $summary);
            }
        }
    }

    /**
     * @param array<string, int> $propertyCounts
     * @return list<string>
     */
    private static function htmlMicrodataRepeatedProperties(array $propertyCounts): array
    {
        $repeated = [];
        foreach ($propertyCounts as $property => $count) {
            if ($count > 1) {
                $repeated[] = $property;
            }
        }

        return $repeated;
    }

    /**
     * @param array<string, string> $attrs
     * @param list<string> $repeatedProperties
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlMicrodataRepeatedPropertyMetadata(
        \DOMElement $element,
        string $tagName,
        array &$attrs,
        array $repeatedProperties,
        array &$diagnostics
    ): void {
        if ($repeatedProperties === []) {
            return;
        }

        $repeatedMetadata = implode(' ', $repeatedProperties);
        if (strlen($repeatedMetadata) <= 512) {
            $attrs['data-pandoc-microdata-repeated-properties'] = $repeatedMetadata;
        }
        $attrs['data-pandoc-microdata-repeated-property-count'] = (string) count($repeatedProperties);

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'microdata-repeated-property-review',
            'tag' => $tagName,
            'attribute' => 'itemscope',
            'metadataAttribute' => 'data-pandoc-microdata-repeated-properties',
            'repeatedPropertyCount' => count($repeatedProperties),
            'reason' => 'microdata-repeated-properties-preserved-as-review-metadata',
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     */
    private static function htmlMicrodataItemValue(string $tagName, array $attrs, array $children): ?string
    {
        $tag = strtolower($tagName);
        $value = match ($tag) {
            'a', 'area', 'link' => $attrs['href'] ?? null,
            'audio', 'embed', 'iframe', 'img', 'source', 'track', 'video' => $attrs['src'] ?? null,
            'object' => $attrs['data'] ?? null,
            'data' => $attrs['data-pandoc-data-value'] ?? null,
            'meter' => $attrs['data-pandoc-meter-value'] ?? null,
            'time' => $attrs['data-pandoc-time-datetime'] ?? null,
            default => self::textFromNormalizedNodes($children),
        };

        if ($value === null) {
            return null;
        }

        return self::normalizeHtmlMicrodataItemValue($value);
    }

    private static function normalizeHtmlMicrodataItemValue(string $value): ?string
    {
        $value = self::cleanHtmlMetadataAttribute($value);
        if ($value === '' || strlen($value) > 512) {
            return null;
        }

        return $value;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private static function textFromNormalizedNodes(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'text') {
                $text .= (string) ($node['text'] ?? '');
                continue;
            }
            if (($node['type'] ?? '') === 'element' && is_array($node['children'] ?? null)) {
                $text .= self::textFromNormalizedNodes($node['children']);
            }
        }

        return $text;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlSemanticUrl(
        string $value,
        string $tagName,
        string $attributeName,
        \DOMElement $element,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string
    {
        $normalized = self::normalizeUrlAttributeValue($value);
        if ($normalized === '' || !self::isSafeFetchUrl($normalized)) {
            self::addSemanticUrlDiagnostic($diagnostics, 'unsafe-url', $tagName, $attributeName, $element);

            return null;
        }

        if ($normalized !== $value) {
            self::addSemanticUrlDiagnostic($diagnostics, 'normalized-url', $tagName, $attributeName, $element);
        }

        if ($baseUrl !== null) {
            $normalized = self::resolveRelativeUrl($baseUrl, $normalized);
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlSemanticUrlTokenList(
        string $value,
        string $tagName,
        string $attributeName,
        \DOMElement $element,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        if (self::hasCompactUnsafeSemanticScheme($value)) {
            self::addSemanticUrlDiagnostic($diagnostics, 'unsafe-url', $tagName, $attributeName, $element);

            return null;
        }

        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            $url = self::normalizeHtmlSemanticUrl($token, $tagName, $attributeName, $element, $diagnostics, $baseUrl);
            if ($url === null || in_array($url, $normalized, true)) {
                continue;
            }

            $normalized[] = $url;
        }

        if ($normalized === []) {
            return null;
        }

        self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $attributeName, $element);

        return implode(' ', $normalized);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlSemanticTermTokenList(
        string $value,
        string $tagName,
        string $attributeName,
        \DOMElement $element,
        array &$diagnostics
    ): ?string {
        if (self::hasCompactUnsafeSemanticScheme($value)) {
            self::addSemanticUnsafeAttributeDiagnostic($diagnostics, $tagName, $attributeName, $element);

            return null;
        }

        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (!self::isSafeHtmlSemanticTermToken($token)) {
                self::addSemanticUnsafeAttributeDiagnostic($diagnostics, $tagName, $attributeName, $element, [
                    'token' => $token,
                ]);
                continue;
            }

            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        if ($normalized === []) {
            return null;
        }

        self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $attributeName, $element);

        return implode(' ', $normalized);
    }

    /**
     * @return list<string>
     */
    private static function splitHtmlSemanticTokens(string $value): array
    {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '') {
            return [];
        }

        $tokens = preg_split('/[\x00-\x20]+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return [];
        }

        return array_values(array_map(static fn (string $token): string => trim($token), $tokens));
    }

    private static function isSafeHtmlSemanticTermToken(string $token): bool
    {
        if ($token === '' || preg_match('/[<>{}`]/', $token) === 1) {
            return false;
        }

        if (self::isTrustedAbsoluteBaseUrl($token)) {
            return true;
        }

        $scheme = strtolower(strstr($token, ':', true) ?: '');
        if (in_array($scheme, ['javascript', 'vbscript', 'data'], true)) {
            return false;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $token) === 1 && !str_contains($token, '://')) {
            return preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*:[A-Za-z0-9_.-]+$/', $token) === 1;
        }

        return preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $token) === 1;
    }

    private static function hasCompactUnsafeSemanticScheme(string $value): bool
    {
        $compact = strtolower(preg_replace('/[\x00-\x20]+/', '', $value) ?? $value);

        return preg_match('/(?:javascript|vbscript|data):/', $compact) === 1;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlRdfaPrefixMap(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '' || preg_match('/[<>{}`]/', $cleaned) === 1) {
            return null;
        }

        if (preg_match_all('/([A-Za-z][A-Za-z0-9_-]*):\s+(\S+)/', $cleaned, $matches, PREG_SET_ORDER) !== false && $matches !== []) {
            $prefixes = [];
            foreach ($matches as $match) {
                $prefix = strtolower((string) $match[1]);
                $iri = self::normalizeHtmlSemanticUrl((string) $match[2], $tagName, 'prefix', $element, $diagnostics, $baseUrl);
                if ($iri === null) {
                    continue;
                }

                $prefixes[$prefix] = $prefix . ': ' . $iri;
            }

            if ($prefixes === []) {
                return null;
            }

            self::addSemanticMetadataDiagnostic($diagnostics, $tagName, 'prefix', $element);

            return implode(' ', array_values($prefixes));
        }

        self::addSemanticUnsafeAttributeDiagnostic($diagnostics, $tagName, 'prefix', $element);

        return null;
    }

    private static function xmlAttributeName(\DOMAttr $attribute): string
    {
        $prefix = $attribute->prefix;
        if (is_string($prefix) && $prefix !== '') {
            return $prefix . ':' . $attribute->localName;
        }

        return $attribute->name;
    }

    /**
     * @return array<string, string>
     */
    private static function xmlNamespaceDeclarationAttributes(\DOMElement $element): array
    {
        $attrs = [];
        $namespaceUri = $element->namespaceURI;
        $prefix = $element->prefix;

        if (is_string($namespaceUri) && $namespaceUri !== '') {
            if (is_string($prefix) && $prefix !== '') {
                if ($prefix !== 'xml') {
                    $attrs['xmlns:' . $prefix] = $namespaceUri;
                }
            } elseif (self::requiresXmlDefaultNamespaceDeclaration($element, $namespaceUri)) {
                $attrs['xmlns'] = $namespaceUri;
            }
        } elseif (self::requiresXmlDefaultNamespaceReset($element)) {
            $attrs['xmlns'] = '';
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $attributePrefix = $attribute->prefix;
            $attributeNamespace = $attribute->namespaceURI;
            if (
                !is_string($attributePrefix)
                || $attributePrefix === ''
                || $attributePrefix === 'xml'
                || $attributePrefix === 'xmlns'
                || !is_string($attributeNamespace)
                || $attributeNamespace === ''
            ) {
                continue;
            }

            $attrs['xmlns:' . $attributePrefix] ??= $attributeNamespace;
        }

        return $attrs;
    }

    private static function requiresXmlDefaultNamespaceDeclaration(\DOMElement $element, string $namespaceUri): bool
    {
        $parent = $element->parentNode;
        if (!$parent instanceof \DOMElement) {
            return true;
        }

        return ($parent->prefix ?? '') !== ''
            || ($parent->namespaceURI ?? '') !== $namespaceUri;
    }

    private static function requiresXmlDefaultNamespaceReset(\DOMElement $element): bool
    {
        $parent = $element->parentNode;

        return $parent instanceof \DOMElement
            && ($parent->lookupNamespaceURI(null) ?? '') !== '';
    }

    private static function isBlockedAttribute(string $name, ?string $foreignContext): bool
    {
        $lower = strtolower($name);

        return str_starts_with($lower, 'on')
            || $lower === 'download'
            || $lower === 'ping'
            || $lower === 'popovertarget'
            || $lower === 'popovertargetaction'
            || $lower === 'style'
            || $lower === 'srcdoc'
            || $lower === 'target'
            || ($foreignContext === null && ($lower === 'xmlns' || str_starts_with($lower, 'xmlns:')))
            || str_starts_with($lower, 'data-pandoc-');
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlStyleAttribute(
        string $value,
        string $tagName,
        \DOMElement $element,
        array &$diagnostics
    ): ?string {
        $declarations = [];
        foreach (self::splitCssDeclarations($value) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }

            $colon = strpos($declaration, ':');
            if ($colon === false) {
                $diagnostics[] = self::diagnosticWithSourceLine(
                    self::unsafeStyleDiagnostic($tagName),
                    $element
                );
                continue;
            }

            $property = strtolower(trim(substr($declaration, 0, $colon)));
            if (!self::isReviewableHtmlStyleProperty($property)) {
                $diagnostics[] = self::diagnosticWithSourceLine(
                    self::unsafeStyleDiagnostic($tagName, $property),
                    $element
                );
                continue;
            }

            $propertyValue = self::normalizeReviewableHtmlStyleValue(substr($declaration, $colon + 1));
            if ($propertyValue === null) {
                $diagnostics[] = self::diagnosticWithSourceLine(
                    self::unsafeStyleDiagnostic($tagName, $property),
                    $element
                );
                continue;
            }

            $declarations[$property] = $property . ': ' . $propertyValue;
        }

        if ($declarations === []) {
            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'style-review-metadata',
            'tag' => $tagName,
            'attribute' => 'style',
            'declarations' => count($declarations),
        ], $element);

        return implode('; ', array_values($declarations));
    }

    /**
     * @return list<string>
     */
    private static function splitCssDeclarations(string $value): array
    {
        $parts = [];
        $start = 0;
        $quote = null;
        $parenDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }
            if ($char !== ';' || $parenDepth !== 0) {
                continue;
            }

            $parts[] = substr($value, $start, $i - $start);
            $start = $i + 1;
        }

        $parts[] = substr($value, $start);

        return $parts;
    }

    private static function isReviewableHtmlStyleProperty(string $property): bool
    {
        return in_array($property, [
            'background-color',
            'border-color',
            'border-style',
            'border-width',
            'color',
            'direction',
            'font-family',
            'font-size',
            'font-style',
            'font-variant',
            'font-weight',
            'letter-spacing',
            'line-height',
            'margin-left',
            'margin-right',
            'text-align',
            'text-decoration',
            'text-transform',
            'vertical-align',
            'white-space',
        ], true);
    }

    private static function normalizeReviewableHtmlStyleValue(string $value): ?string
    {
        $value = trim(str_replace("\0", '', $value));
        if ($value === '' || strlen($value) > 256 || str_contains($value, '/*') || str_contains($value, '*/')) {
            return null;
        }

        $decoded = self::decodeCssEscapes($value);
        if ($decoded === null) {
            return null;
        }

        $lowerCompact = strtolower(preg_replace('/[\x00-\x20]+/', '', $decoded) ?? $decoded);
        foreach (['url(', 'expression(', '@import', 'javascript:', 'vbscript:', 'data:', '-moz-binding'] as $blockedToken) {
            if (str_contains($lowerCompact, $blockedToken)) {
                return null;
            }
        }
        if (preg_match('/[<>{}`]/', $decoded) === 1) {
            return null;
        }

        $decoded = preg_replace('/[\t\r\n\f ]+/u', ' ', $decoded) ?? $decoded;
        $decoded = preg_replace('/\s*,\s*/u', ', ', $decoded) ?? $decoded;
        $decoded = preg_replace('/\(\s+/u', '(', $decoded) ?? $decoded;
        $decoded = preg_replace('/\s+\)/u', ')', $decoded) ?? $decoded;

        return trim($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private static function unsafeStyleDiagnostic(string $tagName, string $property = ''): array
    {
        $diagnostic = [
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => 'style',
        ];
        if ($property !== '') {
            $diagnostic['property'] = $property;
        }

        return $diagnostic;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlRelAttribute(string $value, string $tagName, array &$diagnostics): ?string
    {
        $tokens = preg_split('/[\x00-\x20]+/', strtolower(trim($value)));
        if (!is_array($tokens)) {
            return null;
        }

        $normalized = [];
        $removedOpener = false;
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            if ($token === 'opener') {
                $removedOpener = true;
                continue;
            }
            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        if ($removedOpener) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => 'rel',
                'token' => 'opener',
            ];
        }

        return $normalized === [] ? null : implode(' ', $normalized);
    }

    private static function isHtmlAnchorBrowsingMetadataAttribute(string $name): bool
    {
        return in_array(strtolower($name), ['download', 'target'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlAnchorBrowsingMetadataAttribute(
        string $name,
        string $value,
        \DOMElement $element,
        array &$diagnostics
    ): array {
        $attribute = strtolower($name);
        if ($attribute === 'target') {
            $target = self::normalizeHtmlFormTargetAttribute($value);
            if ($target === null) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => 'a',
                    'attribute' => 'target',
                    'reason' => 'invalid-link-browsing-target',
                ], $element);

                return [];
            }

            self::addHtmlAnchorBrowsingMetadataDiagnostic($diagnostics, $element, 'target', 'data-pandoc-link-target');

            return ['data-pandoc-link-target' => $target];
        }

        $download = self::normalizeHtmlAnchorDownloadAttribute($value);
        if ($download === null) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-attribute',
                'tag' => 'a',
                'attribute' => 'download',
                'reason' => 'invalid-link-download-filename',
            ], $element);

            return [];
        }

        self::addHtmlAnchorBrowsingMetadataDiagnostic($diagnostics, $element, 'download', 'data-pandoc-link-download');

        return ['data-pandoc-link-download' => $download];
    }

    private static function normalizeHtmlAnchorDownloadAttribute(string $value): ?string
    {
        $download = self::cleanHtmlMetadataAttribute($value);
        if ($download === '') {
            return 'true';
        }
        if (strlen($download) > 180 || preg_match('/[\x00-\x1F\x7F]/u', $download) === 1) {
            return null;
        }
        if (strpbrk($download, '<>"\'`{}\\/') !== false) {
            return null;
        }

        return $download;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addHtmlAnchorBrowsingMetadataDiagnostic(
        array &$diagnostics,
        \DOMElement $element,
        string $attributeName,
        string $metadataAttribute
    ): void {
        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'link-browsing-review',
            'tag' => 'a',
            'attribute' => $attributeName,
            'metadataAttribute' => $metadataAttribute,
            'reason' => 'anchor-browsing-metadata-preserved-as-review-metadata',
        ], $element);
    }

    private static function isHtmlResponsiveImageMetadataAttribute(string $tagName, string $name): bool
    {
        $tag = strtolower($tagName);
        $attribute = strtolower($name);

        return ($tag === 'source' && in_array($attribute, ['media', 'sizes'], true))
            || ($tag === 'img' && $attribute === 'sizes');
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlResponsiveImageMetadataAttribute(
        string $value,
        string $tagName,
        string $name,
        array &$diagnostics
    ): ?string {
        $normalized = self::normalizeReviewableHtmlStyleValue($value);
        if ($normalized === null) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => strtolower($name),
                'reason' => 'invalid-responsive-source-metadata',
            ];

            return null;
        }

        return $normalized === '' ? null : $normalized;
    }

    private static function isUrlAttribute(string $name): bool
    {
        return in_array(strtolower($name), [
            'action',
            'background',
            'cite',
            'codebase',
            'data',
            'dynsrc',
            'formaction',
            'href',
            'longdesc',
            'lowsrc',
            'manifest',
            'poster',
            'profile',
            'src',
            'srcset',
            'usemap',
            'xlink:href',
        ], true);
    }

    private static function isSvgPresentationResourceAttribute(string $name, ?string $foreignContext): bool
    {
        return $foreignContext === 'svg'
            && in_array(strtolower($name), [
                'clip-path',
                'color-profile',
                'cursor',
                'fill',
                'filter',
                'marker',
                'marker-end',
                'marker-mid',
                'marker-start',
                'mask',
                'stroke',
            ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeSvgPresentationResourceAttribute(
        string $value,
        string $tagName,
        string $name,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        if (stripos($value, 'url(') === false) {
            return $value;
        }

        $hasUnsafeUrl = false;
        $matchedUrlFunction = false;
        $normalized = preg_replace_callback(
            '/url\(\s*([^)]+?)\s*\)/i',
            static function (array $matches) use (&$hasUnsafeUrl, &$matchedUrlFunction, $baseUrl): string {
                $matchedUrlFunction = true;
                $url = self::normalizeCssUrlToken((string) $matches[1]);
                if ($url === null || $url === '' || !self::isSafeFetchUrl($url)) {
                    $hasUnsafeUrl = true;

                    return '';
                }
                if ($baseUrl !== null && !str_starts_with($url, '#')) {
                    $url = self::resolveRelativeUrl($baseUrl, $url);
                }

                return 'url(' . $url . ')';
            },
            $value
        );

        if (!is_string($normalized) || !$matchedUrlFunction || $hasUnsafeUrl) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => $name,
            ];

            return null;
        }

        $normalized = trim($normalized);
        if ($normalized !== $value) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => $tagName,
                'attribute' => $name,
            ];
        }

        return $normalized;
    }

    private static function normalizeCssUrlToken(string $url): ?string
    {
        $url = trim($url);
        if (str_contains($url, '/*') || str_contains($url, '*/')) {
            return null;
        }

        if (strlen($url) >= 2) {
            $quote = $url[0];
            if ($quote === '"' || $quote === "'") {
                $url = substr($url, 1);
                if (str_ends_with($url, $quote)) {
                    $url = substr($url, 0, -1);
                }
            }
        }

        $url = self::decodeCssEscapes($url);
        if ($url === null) {
            return null;
        }

        return self::normalizeUrlAttributeValue($url);
    }

    private static function decodeCssEscapes(string $value): ?string
    {
        $invalid = false;
        $decoded = preg_replace_callback(
            '/\\\\(?:([0-9A-Fa-f]{1,6})(?:\r\n|[ \t\r\n\f])?|(.))/su',
            static function (array $matches) use (&$invalid): string {
                if (isset($matches[1]) && $matches[1] !== '') {
                    $codepoint = hexdec((string) $matches[1]);
                    if (!is_int($codepoint) || !self::isValidCssUrlCodepoint($codepoint)) {
                        $invalid = true;

                        return '';
                    }

                    return self::codepointToUtf8($codepoint);
                }

                $escaped = (string) ($matches[2] ?? '');
                if ($escaped === '' || preg_match('/[\r\n\f]/', $escaped) === 1) {
                    $invalid = true;

                    return '';
                }

                return $escaped;
            },
            $value
        );

        if (!is_string($decoded) || $invalid) {
            return null;
        }

        return $decoded;
    }

    private static function isValidCssUrlCodepoint(int $codepoint): bool
    {
        return $codepoint > 0
            && $codepoint <= 0x10FFFF
            && ($codepoint < 0xD800 || $codepoint > 0xDFFF);
    }

    private static function codepointToUtf8(int $codepoint): string
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

    private static function isSafeUrlAttributeValue(string $tagName, string $name, string $value, ?string $foreignContext): bool
    {
        if (strtolower($name) === 'usemap') {
            return self::isSafeImageMapReference($value);
        }

        if (self::isSafeRasterImageSourceAttribute($tagName, $name, $value, $foreignContext)) {
            return true;
        }

        if (self::isSvgResourceReferenceAttribute($tagName, $name, $foreignContext)) {
            return self::isSafeFetchUrl($value);
        }

        if (in_array(strtolower($name), [
            'action',
            'background',
            'codebase',
            'data',
            'dynsrc',
            'formaction',
            'longdesc',
            'lowsrc',
            'manifest',
            'poster',
            'profile',
            'src',
        ], true)) {
            return self::isSafeFetchUrl($value);
        }

        return self::isSafeUrl($value);
    }

    private static function isSafeRasterImageSourceAttribute(string $tagName, string $name, string $value, ?string $foreignContext): bool
    {
        $lowerTagName = strtolower($tagName);
        $lowerName = strtolower($name);
        $isHtmlImageSource = $lowerTagName === 'img' && $lowerName === 'src';
        $isSvgImageResource = $foreignContext === 'svg'
            && in_array($lowerTagName, ['image', 'feimage'], true)
            && in_array($lowerName, ['href', 'xlink:href'], true);

        if (!$isHtmlImageSource && !$isSvgImageResource) {
            return false;
        }

        $normalized = self::normalizeUrlAttributeValue($value);

        return self::isSafeRasterImageDataUrl($normalized);
    }

    private static function isSvgResourceReferenceAttribute(string $tagName, string $name, ?string $foreignContext): bool
    {
        return $foreignContext === 'svg'
            && strtolower($tagName) !== 'a'
            && in_array(strtolower($name), ['href', 'xlink:href'], true);
    }

    private static function isLocalSvgReferenceUrl(string $tagName, string $name, string $value, ?string $foreignContext): bool
    {
        return self::isSvgResourceReferenceAttribute($tagName, $name, $foreignContext)
            && str_starts_with($value, '#');
    }

    private static function isLocalImageMapReferenceUrl(string $name, string $value): bool
    {
        return strtolower($name) === 'usemap' && str_starts_with($value, '#');
    }

    private static function isSafeImageMapReference(string $value): bool
    {
        $normalized = self::normalizeUrlAttributeValue($value);

        return preg_match('/^#[A-Za-z0-9_.:-]+$/', $normalized) === 1;
    }

    private static function isSafeFetchUrl(string $value): bool
    {
        if (self::hasUnsafePercentDecodedUrlScheme($value)) {
            return false;
        }

        $trimmed = trim(preg_replace('/[\x00-\x20]+/', '', $value) ?? $value);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/')) {
            return true;
        }
        if (str_starts_with($trimmed, './') || str_starts_with($trimmed, '../') || str_starts_with($trimmed, '?')) {
            return true;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) !== 1) {
            return true;
        }

        $scheme = strtolower(strstr($trimmed, ':', true) ?: '');

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeSrcsetAttribute(
        string $value,
        string $tagName,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $normalized = [];
        foreach (self::splitSrcsetCandidates($value) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                $diagnostics[] = [
                    'code' => 'invalid-srcset-descriptor',
                    'tag' => $tagName,
                    'attribute' => 'srcset',
                    'descriptor' => '',
                ];
                continue;
            }

            $candidateValue = self::normalizeSrcsetCandidate($candidate, $tagName, $diagnostics, $baseUrl);
            if ($candidateValue !== null) {
                $normalized[] = $candidateValue;
            }
        }

        return $normalized === [] ? null : implode(', ', $normalized);
    }

    /**
     * @return list<string>
     */
    private static function splitSrcsetCandidates(string $value): array
    {
        $candidates = [];
        $start = 0;
        $offset = 0;

        while (($comma = strpos($value, ',', $offset)) !== false) {
            $candidatePrefix = substr($value, $start, $comma - $start);
            if (self::isDataUrlPayloadComma($candidatePrefix)) {
                $offset = $comma + 1;
                continue;
            }

            $candidates[] = $candidatePrefix;
            $start = $comma + 1;
            $offset = $start;
        }

        $candidates[] = substr($value, $start);

        return $candidates;
    }

    private static function isDataUrlPayloadComma(string $candidatePrefix): bool
    {
        $trimmed = trim($candidatePrefix);
        if ($trimmed === '' || preg_match('/[\x00-\x20]/', $trimmed) === 1) {
            return false;
        }

        return preg_match('/^data:[^,]*$/i', $trimmed) === 1;
    }

    private static function isSafeUrl(string $value): bool
    {
        if (self::hasUnsafePercentDecodedUrlScheme($value)) {
            return false;
        }

        $trimmed = trim(preg_replace('/[\x00-\x20]+/', '', $value) ?? $value);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/')) {
            return true;
        }
        if (str_starts_with($trimmed, './') || str_starts_with($trimmed, '../') || str_starts_with($trimmed, '?')) {
            return true;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) !== 1) {
            return true;
        }

        $scheme = strtolower(strstr($trimmed, ':', true) ?: '');

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeSrcsetCandidate(
        string $candidate,
        string $tagName,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $parts = self::parseSrcsetCandidateParts($candidate);
        if ($parts === null) {
            return null;
        }

        [$url, $descriptor, $urlWasNormalized, $descriptorWasInvalid] = $parts;
        if ($url === '' || !self::isSafeImageCandidateUrl($url)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
            ];

            return null;
        }

        if ($descriptorWasInvalid) {
            $diagnostics[] = [
                'code' => 'invalid-srcset-descriptor',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
                'descriptor' => self::invalidSrcsetDescriptorText($candidate),
            ];

            return null;
        }

        if ($urlWasNormalized) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
            ];
        }

        if ($baseUrl !== null) {
            $url = self::resolveRelativeUrl($baseUrl, $url);
        }

        return $descriptor === '' ? $url : $url . ' ' . $descriptor;
    }

    /**
     * @return array{0:string, 1:string, 2:bool, 3:bool}|null
     */
    private static function parseSrcsetCandidateParts(string $candidate): ?array
    {
        $trimmed = trim($candidate);
        if ($trimmed === '') {
            return null;
        }

        $parts = preg_split('/[\x00-\x20]+/', $trimmed);
        if (!is_array($parts) || $parts === []) {
            return null;
        }

        $url = (string) array_shift($parts);
        $normalizedUrl = self::normalizeUrlAttributeValue($url);
        $descriptor = self::normalizeSrcsetDescriptor($parts);
        if (
            $descriptor !== null
            && $normalizedUrl !== ''
            && preg_match('/[\x00-\x20]/', $normalizedUrl) !== 1
        ) {
            return [$normalizedUrl, $descriptor, $normalizedUrl !== $url, false];
        }

        $controlSeparated = self::parseControlSeparatedSrcsetCandidate($trimmed);
        if ($controlSeparated !== null) {
            return $controlSeparated;
        }

        return [$normalizedUrl, '', $normalizedUrl !== $url, $parts !== []];
    }

    /**
     * @return array{0:string, 1:string, 2:bool, 3:bool}|null
     */
    private static function parseControlSeparatedSrcsetCandidate(string $candidate): ?array
    {
        if (preg_match('/^(.*?)[\x00-\x20]+([^\x00-\x20]+)$/s', $candidate, $matches) === 1) {
            $descriptor = self::normalizeSrcsetDescriptor([(string) $matches[2]]);
            if ($descriptor !== null) {
                $rawUrl = (string) $matches[1];
                $url = self::normalizeUrlAttributeValue($rawUrl);
                if ($url !== '' && preg_match('/[\x00-\x20]/', $url) !== 1) {
                    return [$url, $descriptor, $url !== $rawUrl, false];
                }
            }
        }

        $url = self::normalizeUrlAttributeValue($candidate);
        if ($url === $candidate || preg_match('/[\x00-\x20]/', $url) === 1) {
            return null;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url) !== 1) {
            return null;
        }

        return [$url, '', true, false];
    }

    private static function invalidSrcsetDescriptorText(string $candidate): string
    {
        $parts = preg_split('/[\x00-\x20]+/', trim($candidate));
        if (!is_array($parts) || count($parts) <= 1) {
            return '';
        }

        array_shift($parts);

        return implode(' ', $parts);
    }

    /**
     * @param list<string> $descriptors
     */
    private static function normalizeSrcsetDescriptor(array $descriptors): ?string
    {
        if ($descriptors === []) {
            return '';
        }
        if (count($descriptors) !== 1) {
            return null;
        }

        $descriptor = strtolower($descriptors[0]);
        if (preg_match('/^([0-9]+)w$/', $descriptor, $width) === 1) {
            $pixels = (int) $width[1];

            return $pixels > 0 ? $pixels . 'w' : null;
        }

        if (preg_match('/^(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)x$/', $descriptor) === 1) {
            $density = substr($descriptor, 0, -1);
            if ((float) $density <= 0.0) {
                return null;
            }

            return self::normalizeDecimalDescriptor($density) . 'x';
        }

        return null;
    }

    private static function normalizeDecimalDescriptor(string $value): string
    {
        if (str_starts_with($value, '.')) {
            $value = '0' . $value;
        }

        if (str_contains($value, '.')) {
            [$integer, $fraction] = explode('.', $value, 2);
            $integer = ltrim($integer, '0');
            $fraction = rtrim($fraction, '0');
            $value = ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);
        } else {
            $value = ltrim($value, '0');
        }

        return $value === '' ? '0' : $value;
    }

    private static function isSafeImageCandidateUrl(string $value): bool
    {
        $trimmed = trim(preg_replace('/[\x00-\x20]+/', '', $value) ?? $value);
        if (self::isSafeRasterImageDataUrl($trimmed)) {
            return true;
        }
        if (self::hasUnsafePercentDecodedUrlScheme($value)) {
            return false;
        }
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/')) {
            return true;
        }
        if (str_starts_with($trimmed, './') || str_starts_with($trimmed, '../') || str_starts_with($trimmed, '?')) {
            return true;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) !== 1) {
            return true;
        }

        $scheme = strtolower(strstr($trimmed, ':', true) ?: '');

        return in_array($scheme, ['http', 'https'], true);
    }

    private static function isSafeRasterImageDataUrl(string $value): bool
    {
        if (preg_match('/^data:image\/(?:png|gif|jpe?g|webp);base64,([A-Za-z0-9+\/]+={0,2})$/i', $value, $matches) !== 1) {
            return false;
        }

        return base64_decode((string) $matches[1], true) !== false;
    }

    private static function hasUnsafePercentDecodedUrlScheme(string $value): bool
    {
        if (stripos($value, '%') === false) {
            return false;
        }

        $decoded = preg_replace_callback(
            '/%([0-9A-Fa-f]{2})/',
            static fn (array $matches): string => chr(hexdec((string) $matches[1])),
            $value
        );
        if (!is_string($decoded) || $decoded === $value) {
            return false;
        }

        $compact = strtolower(preg_replace('/[\x00-\x20]+/', '', $decoded) ?? $decoded);

        return preg_match('/^(?:javascript|vbscript|data):/', $compact) === 1;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function resolveFragmentBaseUrl(\DOMElement $wrapper, ?string $callerBaseUrl, array &$diagnostics): ?string
    {
        $documentBaseUrl = self::normalizeCallerBaseUrl($callerBaseUrl);
        $resolvedBaseUrl = null;
        $hasActiveHref = false;

        foreach ($wrapper->getElementsByTagName('base') as $baseElement) {
            if (!$baseElement instanceof \DOMElement || !$baseElement->hasAttribute('href')) {
                continue;
            }
            if (self::isInactiveFragmentBaseElement($baseElement)) {
                continue;
            }

            $href = trim($baseElement->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            if ($hasActiveHref) {
                $diagnostics[] = self::duplicateHtmlBaseDiagnostic($baseElement, 'href');
                continue;
            }

            $normalizedHref = self::normalizeUrlAttributeValue($href);
            if ($normalizedHref === '') {
                continue;
            }
            $hasActiveHref = true;
            if ($normalizedHref !== $href) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'normalized-url',
                    'tag' => 'base',
                    'attribute' => 'href',
                ], $baseElement);
            }

            $resolved = self::resolveBaseHref($normalizedHref, $documentBaseUrl, $baseElement, $diagnostics);
            if ($resolved !== null) {
                $resolvedBaseUrl = $resolved;
            }
        }

        return $resolvedBaseUrl ?? $documentBaseUrl;
    }

    /**
     * @return array<string, mixed>
     */
    private static function duplicateHtmlBaseDiagnostic(\DOMElement $element, string $attribute): array
    {
        return self::diagnosticWithSourceLine([
            'code' => 'duplicate-base-ignored',
            'tag' => 'base',
            'attribute' => $attribute,
            'reason' => 'first-active-base-' . $attribute . '-already-used',
        ], $element);
    }

    private static function isInactiveFragmentBaseElement(\DOMElement $element): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            $name = strtolower($parent->localName);
            if (isset(self::HTML_FRAGMENT_INACTIVE_BASE_ANCESTORS[$name])) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }

    private static function normalizeCallerBaseUrl(?string $baseUrl): ?string
    {
        if ($baseUrl === null) {
            return null;
        }

        $trimmed = trim($baseUrl);
        if ($trimmed === '') {
            return null;
        }

        if (!self::isTrustedAbsoluteBaseUrl($trimmed)) {
            throw new \InvalidArgumentException('HTML fragment base URL must be an absolute HTTP(S) URL without credentials');
        }

        return $trimmed;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function resolveBaseHref(
        string $href,
        ?string $documentBaseUrl,
        \DOMElement $baseElement,
        array &$diagnostics
    ): ?string
    {
        if (self::isTrustedAbsoluteBaseUrl($href)) {
            return $href;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'unsafe-url',
                'tag' => 'base',
                'attribute' => 'href',
            ], $baseElement);

            return null;
        }

        if ($documentBaseUrl !== null) {
            return self::resolveRelativeUrl($documentBaseUrl, $href);
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'unresolved-base-url',
            'tag' => 'base',
            'attribute' => 'href',
        ], $baseElement);

        return null;
    }

    private static function isTrustedAbsoluteBaseUrl(string $value): bool
    {
        $parts = parse_url($value);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return isset($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private static function resolveRelativeUrl(string $baseUrl, string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) === 1) {
            return $trimmed;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || !isset($base['scheme'], $base['host'])) {
            return $value;
        }

        $scheme = strtolower((string) $base['scheme']);
        $host = (string) $base['host'];
        $port = isset($base['port']) ? ':' . (string) $base['port'] : '';
        $origin = $scheme . '://' . $host . $port;

        if (str_starts_with($trimmed, '//')) {
            return $scheme . ':' . $trimmed;
        }

        $basePath = (string) ($base['path'] ?? '/');
        if ($basePath === '') {
            $basePath = '/';
        }

        $baseQuery = isset($base['query']) ? '?' . (string) $base['query'] : '';
        if (str_starts_with($trimmed, '#')) {
            return $origin . $basePath . $baseQuery . $trimmed;
        }
        if (str_starts_with($trimmed, '?')) {
            return $origin . $basePath . $trimmed;
        }

        $fragment = '';
        $pathAndQuery = $trimmed;
        $fragmentOffset = strpos($pathAndQuery, '#');
        if ($fragmentOffset !== false) {
            $fragment = substr($pathAndQuery, $fragmentOffset);
            $pathAndQuery = substr($pathAndQuery, 0, $fragmentOffset);
        }

        $query = '';
        $path = $pathAndQuery;
        $queryOffset = strpos($path, '?');
        if ($queryOffset !== false) {
            $query = substr($path, $queryOffset);
            $path = substr($path, 0, $queryOffset);
        }

        $resolvedPath = str_starts_with($path, '/')
            ? $path
            : self::joinBasePath($basePath, $path);

        return $origin . self::normalizeUrlPath($resolvedPath) . $query . $fragment;
    }

    private static function joinBasePath(string $basePath, string $relativePath): string
    {
        $slash = strrpos($basePath, '/');
        $directory = str_ends_with($basePath, '/')
            ? $basePath
            : substr($basePath, 0, $slash === false ? 0 : $slash + 1);

        if ($directory === '') {
            $directory = '/';
        }

        return $directory . $relativePath;
    }

    private static function normalizeUrlPath(string $path): string
    {
        $trailingSlash = str_ends_with($path, '/') && $path !== '/';
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = '/' . implode('/', $segments);
        if ($trailingSlash && $normalized !== '/') {
            $normalized .= '/';
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function serializeNodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->serializeNode($node);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function serializeNode(array $node): string
    {
        $type = (string) ($node['type'] ?? '');
        if ($type === 'text') {
            return htmlspecialchars((string) ($node['text'] ?? ''), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($type === 'comment') {
            return '<!--' . self::safeCommentText((string) ($node['text'] ?? '')) . '-->';
        }
        if ($type !== 'element') {
            return '';
        }

        $name = (string) $node['name'];
        $attrs = $this->serializeAttributes(is_array($node['attrs'] ?? null) ? $node['attrs'] : []);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($this->mode === 'html' && self::isHtmlVoidElement($name)) {
            return '<' . $name . $attrs . '>';
        }
        if ($children === []) {
            if ($this->mode === 'xml') {
                return '<' . $name . $attrs . '/>';
            }
        }

        return '<' . $name . $attrs . '>' . $this->serializeNodes($children) . '</' . $name . '>';
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function serializeAttributes(array $attrs): string
    {
        $serialized = '';
        foreach ($attrs as $name => $value) {
            $lowerName = strtolower((string) $name);
            $stringValue = (string) $value;
            if ($this->mode === 'html' && isset(self::HTML5_BOOLEAN_ATTRIBUTES[$lowerName]) && ($stringValue === '' || strtolower($stringValue) === $lowerName)) {
                $serialized .= ' ' . $name;
                continue;
            }

            $serialized .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $serialized;
    }

    private static function safeCommentText(string $text): string
    {
        while (str_contains($text, '--')) {
            $text = str_replace('--', '- -', $text);
        }

        return str_ends_with($text, '-') ? $text . ' ' : $text;
    }

    private static function isHtmlVoidElement(string $name): bool
    {
        return in_array(strtolower($name), [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function textFromNodes(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'text') {
                $text .= (string) ($node['text'] ?? '');
                continue;
            }
            if (($node['type'] ?? '') === 'element' && is_array($node['children'] ?? null)) {
                $text .= $this->textFromNodes($node['children']);
            }
        }

        return $text;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array{elements:int, textNodes:int, comments:int, elementNames:array<string, bool>} $counts
     */
    private function summarizeNodes(array $nodes, array &$counts): void
    {
        foreach ($nodes as $node) {
            $type = (string) ($node['type'] ?? '');
            if ($type === 'text') {
                $counts['textNodes']++;
                continue;
            }
            if ($type === 'comment') {
                $counts['comments']++;
                continue;
            }
            if ($type !== 'element') {
                continue;
            }

            $counts['elements']++;
            $counts['elementNames'][(string) $node['name']] = true;
            if (is_array($node['children'] ?? null)) {
                $this->summarizeNodes($node['children'], $counts);
            }
        }
    }
}
