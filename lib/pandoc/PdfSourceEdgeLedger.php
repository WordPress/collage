<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Build stable public PDF source-edge identities and digests.
 */
final class PdfSourceEdgeLedger
{
    /** @var array<string,true> */
    private const OUTPUT_DISPOSITIONS = [
        'emitted' => true,
        'boundary-repair' => true,
        'semantic-structure' => true,
    ];

    /** @return array<string,mixed> */
    public static function sourceEdgeForOccurrence(
        string $sourceId,
        int $page,
        string $disposition,
        string $mappingStatus,
        mixed $mappingMode,
        array $nodeIds,
        array $inlineIds,
        mixed $orderScopeId
    ): array {
        $outputDisposition = isset(self::OUTPUT_DISPOSITIONS[$disposition]);
        $target = $disposition === 'unresolved'
            ? 'unresolved'
            : ($outputDisposition && $mappingStatus === 'output'
                ? 'output'
                : (!$outputDisposition && $mappingStatus === 'disposition'
                    ? 'disposition'
                    : 'unresolved'));
        if ($target !== 'output') {
            $nodeIds = [];
            $inlineIds = [];
        }
        $identityDigest = self::sourceEdgeIdentityDigest(
            $sourceId,
            $page,
            $disposition,
            $target,
            $mappingMode,
            $nodeIds,
            $inlineIds,
            $orderScopeId
        );

        return [
            'id' => 'pdf-source-edge-' . substr($identityDigest, 0, 32),
            'sourceOccurrenceId' => $sourceId,
            'page' => $page,
            'disposition' => $disposition,
            'target' => $target,
            'mappingMode' => $mappingMode,
            'destinationNodeIds' => $nodeIds,
            'destinationInlineIds' => $inlineIds,
            'orderScopeId' => $orderScopeId,
        ];
    }

    /**
     * Hash the established JSON source-edge identity without retaining its
     * complete encoded destination vectors beside the returned edge.
     *
     * @param list<string> $nodeIds
     * @param list<string> $inlineIds
     */
    private static function sourceEdgeIdentityDigest(
        string $sourceId,
        int $page,
        string $disposition,
        string $target,
        mixed $mappingMode,
        array $nodeIds,
        array $inlineIds,
        mixed $orderScopeId
    ): string {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $scalars = [
            $sourceId,
            $disposition,
            $target,
            $mappingMode,
            $orderScopeId,
        ];
        $encodedScalars = [];
        foreach ($scalars as $scalar) {
            $encoded = json_encode($scalar, $flags);
            if (!is_string($encoded)) {
                return hash('sha256', serialize([
                    'sourceOccurrenceId' => $sourceId,
                    'page' => $page,
                    'disposition' => $disposition,
                    'target' => $target,
                    'mappingMode' => $mappingMode,
                    'destinationNodeIds' => $nodeIds,
                    'destinationInlineIds' => $inlineIds,
                    'orderScopeId' => $orderScopeId,
                ]));
            }
            $encodedScalars[] = $encoded;
        }

        $digest = hash_init('sha256');
        hash_update(
            $digest,
            '{"sourceOccurrenceId":' . $encodedScalars[0]
                . ',"page":' . $page
                . ',"disposition":' . $encodedScalars[1]
                . ',"target":' . $encodedScalars[2]
                . ',"mappingMode":' . $encodedScalars[3]
                . ',"destinationNodeIds":['
        );
        foreach ($nodeIds as $index => $nodeId) {
            $encoded = json_encode($nodeId, $flags);
            if (!is_string($encoded)) {
                return hash('sha256', serialize([
                    'sourceOccurrenceId' => $sourceId,
                    'page' => $page,
                    'disposition' => $disposition,
                    'target' => $target,
                    'mappingMode' => $mappingMode,
                    'destinationNodeIds' => $nodeIds,
                    'destinationInlineIds' => $inlineIds,
                    'orderScopeId' => $orderScopeId,
                ]));
            }
            hash_update($digest, ($index > 0 ? ',' : '') . $encoded);
        }
        hash_update($digest, '],"destinationInlineIds":[');
        foreach ($inlineIds as $index => $inlineId) {
            $encoded = json_encode($inlineId, $flags);
            if (!is_string($encoded)) {
                return hash('sha256', serialize([
                    'sourceOccurrenceId' => $sourceId,
                    'page' => $page,
                    'disposition' => $disposition,
                    'target' => $target,
                    'mappingMode' => $mappingMode,
                    'destinationNodeIds' => $nodeIds,
                    'destinationInlineIds' => $inlineIds,
                    'orderScopeId' => $orderScopeId,
                ]));
            }
            hash_update($digest, ($index > 0 ? ',' : '') . $encoded);
        }
        hash_update($digest, '],"orderScopeId":' . $encodedScalars[4] . '}');

        return hash_final($digest);
    }

    /** @param list<array<string,mixed>> $edges */
    public static function sourceEdgeDigest(array $edges): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $digest = hash_init('sha256');
        hash_update($digest, '[');
        foreach ($edges as $index => $edge) {
            $encoded = json_encode($edge, $flags);
            if (!is_string($encoded)) {
                // Match the prior whole-list fallback exactly for malformed
                // UTF-8 or another value JSON cannot represent.
                return hash('sha256', serialize($edges));
            }
            if ($index > 0) {
                hash_update($digest, ',');
            }
            hash_update($digest, $encoded);
        }
        hash_update($digest, ']');

        return hash_final($digest);
    }
}
