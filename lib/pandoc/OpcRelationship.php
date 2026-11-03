<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationship
{
    public const TARGET_MODE_INTERNAL = 'Internal';
    public const TARGET_MODE_EXTERNAL = 'External';

    /** @var list<string> */
    private const UNSAFE_EXTERNAL_SCHEMES = ['data', 'file', 'javascript', 'vbscript'];

    public readonly bool $targetModeExplicit;

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $target,
        public readonly string $targetMode = self::TARGET_MODE_INTERNAL,
        ?bool $targetModeExplicit = null,
        bool $validateId = true,
        bool $validateRequiredFields = true,
    ) {
        if ($validateRequiredFields && ($id === '' || $type === '' || $target === '')) {
            throw new \InvalidArgumentException('OPC relationship Id, Type, and Target must be non-empty');
        }

        if ($validateId) {
            self::assertRelationshipId($id);
        }

        if ($targetMode !== self::TARGET_MODE_INTERNAL && $targetMode !== self::TARGET_MODE_EXTERNAL) {
            throw new \InvalidArgumentException('Unsupported OPC relationship TargetMode: ' . $targetMode);
        }

        $this->targetModeExplicit = $targetModeExplicit ?? $targetMode !== self::TARGET_MODE_INTERNAL;
    }

    public function isExternal(): bool
    {
        return $this->targetMode === self::TARGET_MODE_EXTERNAL;
    }

    /**
     * @return array{kind:string, scheme:?string, valid:bool, issues:list<string>}
     */
    public function relationshipTypePreflight(): array
    {
        $scheme = null;
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $this->type, $matches) === 1) {
            $kind = 'absolute-uri';
            $scheme = strtolower($matches[1]);
        } elseif (str_starts_with($this->type, '//')) {
            $kind = 'network-path-reference';
        } elseif (str_starts_with($this->type, '#')) {
            $kind = 'fragment-reference';
        } else {
            $kind = 'relative-reference';
        }

        $issues = [];
        if ($kind !== 'absolute-uri') {
            $issues[] = 'relationship-type-not-absolute-uri';
        }

        if (preg_match('/[\x00-\x20\x7F]/', $this->type) === 1) {
            $issues[] = 'relationship-type-invalid-uri-bytes';
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $this->type) === 1) {
            $issues[] = 'relationship-type-malformed-percent-escape';
        } elseif (self::uriReferenceContainsPercentEncodedControlByte($this->type)) {
            $issues[] = 'relationship-type-unsafe-percent-encoded-byte';
        }

        if ($kind === 'absolute-uri' && substr($this->type, strlen((string) $scheme) + 1) === '') {
            $issues[] = 'relationship-type-empty-uri-body';
        }

        return [
            'kind' => $kind,
            'scheme' => $scheme,
            'valid' => $issues === [],
            'issues' => array_values(array_unique($issues)),
        ];
    }

    /**
     * @return array{kind:string, scheme:?string, allowed:bool, issues:list<string>}
     */
    public function externalTargetPreflight(): array
    {
        if (!$this->isExternal()) {
            throw new \LogicException('OPC external target preflight requires TargetMode="External"');
        }

        $scheme = null;
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $this->target, $matches) === 1) {
            $kind = 'absolute-uri';
            $scheme = strtolower($matches[1]);
        } elseif (str_starts_with($this->target, '//')) {
            $kind = 'network-path-reference';
        } elseif (str_starts_with($this->target, '#')) {
            $kind = 'fragment-reference';
        } else {
            $kind = 'relative-reference';
        }

        $issues = [];
        if (preg_match('/[\x00-\x20\x7F]/', $this->target) === 1) {
            $issues[] = 'external-target-invalid-uri-byte';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $this->target) === 1) {
            $issues[] = 'external-target-control-character';
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $this->target) === 1) {
            $issues[] = 'external-target-malformed-percent-escape';
        } elseif (self::uriReferenceContainsPercentEncodedControlByte($this->target)) {
            $issues[] = 'external-target-unsafe-percent-encoded-byte';
        }

        if ($scheme !== null && in_array($scheme, self::UNSAFE_EXTERNAL_SCHEMES, true)) {
            $issues[] = 'external-target-unsafe-scheme';
        }

        return [
            'kind' => $kind,
            'scheme' => $scheme,
            'allowed' => $issues === [],
            'issues' => $issues,
        ];
    }

    private static function assertRelationshipId(string $id): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $id) !== 1) {
            throw new \InvalidArgumentException('OPC relationship Id must be an XML NCName-style identifier');
        }
    }

    private static function uriReferenceContainsPercentEncodedControlByte(string $target): bool
    {
        if (preg_match_all('/%([0-9A-Fa-f]{2})/', $target, $matches) === 0) {
            return false;
        }

        foreach ($matches[1] as $hex) {
            $byte = hexdec($hex);
            if ($byte < 0x20 || $byte === 0x7F) {
                return true;
            }
        }

        return false;
    }
}
