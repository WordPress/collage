<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpenDocumentPackageByteHandoff
{
    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    public static function summarize(ZipPackage $package, array $parts, string $pathKey): array
    {
        $requests = [];
        foreach ($parts as $part) {
            if (($part['canExposeBytes'] ?? false) !== true) {
                continue;
            }

            $path = $part[$pathKey] ?? ($part['path'] ?? ($part['part'] ?? null));
            if (!is_string($path) || $path === '') {
                continue;
            }

            $requests[] = [
                'name' => $path,
                'required' => true,
                'kind' => 'file',
                'role' => self::role($part),
            ];
        }

        $handoff = $package->entryHandoffPreflight($requests);
        $selectedSourceReviewFieldBytes = (int) $handoff['selectedSourceLocalReviewFieldBytes']
            + (int) $handoff['selectedSourceCentralDirectoryReviewFieldBytes'];

        return [
            'format' => 'odt',
            'requestCount' => count($requests),
            'requestedEntryCount' => $handoff['requestedEntryCount'],
            'selectedUniqueEntryCount' => $handoff['selectedUniqueEntryCount'],
            'handoffEntryCount' => $handoff['handoffEntryCount'],
            'failedEntryCount' => $handoff['failedEntryCount'],
            'missingEntryCount' => $handoff['missingEntryCount'],
            'isSupportedByBoundedReader' => $handoff['isSupportedByBoundedReader'],
            'issues' => $handoff['issues'],
            'selectedSourceManifestVersion' => $handoff['selectedSourceManifestVersion'],
            'selectedSourceManifestSha256' => $handoff['selectedSourceManifestSha256'],
            'selectedSourceManifest' => $handoff['selectedSourceManifest'],
            'selectedSourceHasArchiveTrailer' => $handoff['selectedSourceHasArchiveTrailer'],
            'selectedSourceArchiveTrailerOffset' => $handoff['selectedSourceArchiveTrailerOffset'],
            'selectedSourceArchiveTrailerBytes' => $handoff['selectedSourceArchiveTrailerBytes'],
            'selectedSourceArchiveTrailerEnd' => $handoff['selectedSourceArchiveTrailerEnd'],
            'selectedSourceArchiveTrailerSha256' => $handoff['selectedSourceArchiveTrailerSha256'],
            'selectedSourceArchiveTrailerReviewFieldBytes' => $handoff['selectedSourceArchiveTrailerReviewFieldBytes'],
            'selectedSourceArchiveTrailerByteExposurePolicy' => $handoff['selectedSourceArchiveTrailerByteExposurePolicy'],
            'selectedSourceArchiveTrailerCanExposeBytes' => $handoff['selectedSourceArchiveTrailerCanExposeBytes'],
            'selectedSourceEndOfCentralDirectoryOffset' => $handoff['selectedSourceEndOfCentralDirectoryOffset'],
            'selectedSourceEndOfCentralDirectoryBytes' => $handoff['selectedSourceEndOfCentralDirectoryBytes'],
            'selectedSourceEndOfCentralDirectorySha256' => $handoff['selectedSourceEndOfCentralDirectorySha256'],
            'selectedSourceEndOfCentralDirectoryFixedHeaderBytes' =>
                $handoff['selectedSourceEndOfCentralDirectoryFixedHeaderBytes'],
            'selectedSourceEndOfCentralDirectoryFixedHeaderSha256' =>
                $handoff['selectedSourceEndOfCentralDirectoryFixedHeaderSha256'],
            'selectedSourcePackageCommentOffset' => $handoff['selectedSourcePackageCommentOffset'],
            'selectedSourcePackageCommentBytes' => $handoff['selectedSourcePackageCommentBytes'],
            'selectedSourcePackageCommentEnd' => $handoff['selectedSourcePackageCommentEnd'],
            'selectedSourcePackageCommentSha256' => $handoff['selectedSourcePackageCommentSha256'],
            'selectedSourcePackageCommentPreviewHex' => $handoff['selectedSourcePackageCommentPreviewHex'],
            'selectedSourcePackageCommentPreviewByteCount' => $handoff['selectedSourcePackageCommentPreviewByteCount'],
            'selectedSourcePackageCommentByteExposurePolicy' => $handoff['selectedSourcePackageCommentByteExposurePolicy'],
            'selectedSourceCanExposePackageCommentBytes' => $handoff['selectedSourceCanExposePackageCommentBytes'],
            'selectedSourceHasPackageComment' => $handoff['selectedSourceHasPackageComment'],
            'selectedSourceArchiveTrailer' => $handoff['selectedSourceArchiveTrailer'],
            'selectedHandoffManifestVersion' => $handoff['selectedHandoffManifestVersion'],
            'selectedHandoffManifestSha256' => $handoff['selectedHandoffManifestSha256'],
            'selectedHandoffManifest' => $handoff['selectedHandoffManifest'],
            'selectedSourceLocalRecordBytes' => $handoff['selectedSourceLocalRecordBytes'],
            'selectedSourceLocalHeaderBytes' => $handoff['selectedSourceLocalHeaderBytes'],
            'selectedSourceLocalHeaderVariableFieldBytes' => $handoff['selectedSourceLocalHeaderVariableFieldBytes'],
            'selectedSourceLocalRawNameBytes' => $handoff['selectedSourceLocalRawNameBytes'],
            'selectedSourceLocalExtraFieldBytes' => $handoff['selectedSourceLocalExtraFieldBytes'],
            'selectedSourceLocalReviewFieldBytes' => $handoff['selectedSourceLocalReviewFieldBytes'],
            'selectedSourceCompressedDataBytes' => $handoff['selectedSourceCompressedDataBytes'],
            'selectedSourceDataDescriptorBytes' => $handoff['selectedSourceDataDescriptorBytes'],
            'selectedSourceCentralDirectoryRecordBytes' => $handoff['selectedSourceCentralDirectoryRecordBytes'],
            'selectedSourceCentralDirectoryFixedHeaderBytes' => $handoff['selectedSourceCentralDirectoryFixedHeaderBytes'],
            'selectedSourceCentralDirectoryVariableFieldBytes' => $handoff['selectedSourceCentralDirectoryVariableFieldBytes'],
            'selectedSourceCentralDirectoryRawNameBytes' => $handoff['selectedSourceCentralDirectoryRawNameBytes'],
            'selectedSourceCentralDirectoryExtraFieldBytes' => $handoff['selectedSourceCentralDirectoryExtraFieldBytes'],
            'selectedSourceCentralDirectoryRawCommentBytes' => $handoff['selectedSourceCentralDirectoryRawCommentBytes'],
            'selectedSourceCentralDirectoryReviewFieldBytes' => $handoff['selectedSourceCentralDirectoryReviewFieldBytes'],
            'selectedSourceReviewFieldBytes' => $selectedSourceReviewFieldBytes,
            'selectedSourceTotalRecordBytes' => $handoff['selectedSourceTotalRecordBytes'],
            'selectedSourceByteSpanBucketCount' => $handoff['selectedSourceByteSpanBucketCount'],
            'selectedSourceByteSpanBuckets' => $handoff['selectedSourceByteSpanBuckets'],
            'roleSummaries' => $handoff['roleSummaries'],
            'handoffEntries' => $handoff['handoffEntries'],
            'byteExposurePolicy' => 'odf-selected-package-byte-handoff-metadata-only',
            'canExposeBytes' => false,
        ];
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function role(array $part): string
    {
        $roles = is_array($part['roles'] ?? null)
            ? array_values(array_filter($part['roles'], static fn (mixed $role): bool => is_string($role) && $role !== ''))
            : [];
        foreach (['odf-content', 'odf-styles', 'odf-meta', 'odf-settings', 'media-resource'] as $preferredRole) {
            if (in_array($preferredRole, $roles, true)) {
                return $preferredRole;
            }
        }

        return is_string($roles[0] ?? null) ? $roles[0] : 'package-bytes-exposable';
    }
}
