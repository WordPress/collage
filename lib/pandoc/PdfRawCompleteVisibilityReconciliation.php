<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Build the canonical no-op receipt for already complete raw visibility. */
final class PdfRawCompleteVisibilityReconciliation
{
    /** @return array<string,mixed> */
    public static function result(): array
    {
        $payload = [
            'version' => 1,
            'policy' => 'exact-whole-source-later-image-artifact-v1',
            'rawComplete' => true,
            'complete' => true,
            'reconciled' => false,
            'riskCount' => 0,
            'reconciledRiskCount' => 0,
            'unresolvedRiskCount' => 0,
            'riskIds' => [],
            'mediaProofDigests' => [],
            'failureReason' => null,
        ];
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );

        return $payload + [
            'proofDigest' => hash(
                'sha256',
                is_string($encoded) ? $encoded : serialize($payload)
            ),
        ];
    }
}
