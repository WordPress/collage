<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfImageRenderer
{
    private const INLINE_IMAGE_KEY_ABBREVIATIONS = [
        'BPC' => 'BitsPerComponent',
        'CS' => 'ColorSpace',
        'D' => 'Decode',
        'DP' => 'DecodeParms',
        'F' => 'Filter',
        'H' => 'Height',
        'I' => 'Interpolate',
        'IM' => 'ImageMask',
        'W' => 'Width',
    ];
    private const INLINE_IMAGE_VALUE_ABBREVIATIONS = [
        'A85' => 'ASCII85Decode',
        'AHx' => 'ASCIIHexDecode',
        'CCF' => 'CCITTFaxDecode',
        'CMYK' => 'DeviceCMYK',
        'DCT' => 'DCTDecode',
        'Fl' => 'FlateDecode',
        'G' => 'DeviceGray',
        'I' => 'Indexed',
        'LZW' => 'LZWDecode',
        'RGB' => 'DeviceRGB',
        'RL' => 'RunLengthDecode',
    ];
    private const MALFORMED_IMAGE_FILTER_OPERAND = 'MalformedFilterOperand';
    private const UNRESOLVED_IMAGE_FILTER_OPERAND = 'UnresolvedFilterOperand';

    private BboxGeometry $geometry;

    public function __construct(?BboxGeometry $geometry = null)
    {
        $this->geometry = $geometry ?? new BboxGeometry();
    }

    /**
     * Native boundary for marker.pdf.images::render_image scale calculation.
     */
    public function renderScale(float $dpi): float
    {
        if ($dpi <= 0.0) {
            throw new InvalidArgumentException('DPI must be greater than zero.');
        }

        return $dpi / 72.0;
    }

    /**
     * Derives rendered page pixel dimensions from PDF visible-box geometry.
     * Rotation follows page dictionary geometry, and UserUnit compensates for
     * the PDF 1.6 scale factor that PDFium cannot expose through pypdfium.
     *
     * @param list<float|int> $pageBbox
     * @return array{width: int, height: int}
     */
    public function renderedImageSize(array $pageBbox, float $dpi, int $rotation = 0, float $userUnit = 1.0): array
    {
        $bbox = $this->bbox($pageBbox);
        if ($userUnit <= 0.0) {
            throw new InvalidArgumentException('UserUnit must be greater than zero.');
        }

        $scale = $this->renderScale($dpi) * $userUnit;
        $width = max(0.0, $bbox[2] - $bbox[0]);
        $height = max(0.0, $bbox[3] - $bbox[1]);
        if (in_array($this->normalizedRotation($rotation), [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }

        return [
            'width' => (int) round($width * $scale),
            'height' => (int) round($height * $scale),
        ];
    }

    /**
     * Native boundary for marker.pdf.images::render_bbox_image crop scaling.
     *
     * @param list<float|int> $pageBbox
     * @param array{width?: int|float, height?: int|float}|list<int|float> $renderedImageSize
     * @param list<float|int> $bbox
     * @return list<float>
     */
    public function cropBboxForRenderedImage(array $pageBbox, array $renderedImageSize, array $bbox): array
    {
        $pageBbox = $this->bbox($pageBbox);
        $imageBbox = $this->renderedImageBbox($renderedImageSize);

        return $this->geometry->rescaleBbox($pageBbox, $imageBbox, $bbox);
    }

    /**
     * Plans the metadata around marker.pdf.images::render_bbox_image without
     * rasterizing through pypdfium2/PIL.
     *
     * @param list<float|int> $pageBbox
     * @param list<float|int> $bbox
     * @param array{width?: int|float, height?: int|float}|list<int|float>|null $renderedImageSize
     * @return array{dpi: float, scale: float, draw_annots: false, color_mode: string, rendered_image_bbox: list<float>, crop_bbox: list<float>, crop_width: float, crop_height: float}
     */
    public function renderBboxImagePlan(
        array $pageBbox,
        array $bbox,
        float $dpi = 96.0,
        ?array $renderedImageSize = null
    ): array {
        $size = $renderedImageSize ?? $this->renderedImageSize($pageBbox, $dpi);
        $imageBbox = $this->renderedImageBbox($size);
        $cropBbox = $this->cropBboxForRenderedImage($pageBbox, $size, $bbox);

        return [
            'dpi' => $dpi,
            'scale' => $this->renderScale($dpi),
            'draw_annots' => false,
            'color_mode' => 'RGB',
            'rendered_image_bbox' => $imageBbox,
            'crop_bbox' => $cropBbox,
            'crop_width' => max(0.0, $cropBbox[2] - $cropBbox[0]),
            'crop_height' => max(0.0, $cropBbox[3] - $cropBbox[1]),
        ];
    }

    /**
     * Native metadata boundary for PDF DCTDecode image color handling.
     *
     * Upstream renders through pypdfium/PIL and converts the final image to
     * RGB. This keeps the same RGB preview target while making CMYK/YCCK DCT
     * decisions deterministic without rasterizing the whole JPEG stream.
     *
     * @param array<int, string> $objects
     * @return array{filter: string, source_color_space: string, components: int|null, bits_per_component: int, adobe_app14_transform: int|null, decode_parms_color_transform: int|null, decode_parms_color_transform_valid: bool, decode_parms_color_transform_ignored: bool, effective_color_transform: int|null, adobe_marker_overrides_decode_parms: bool, needs_cmyk_to_rgb: bool, uses_ycck_transform: bool, image_decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null, image_decode_applied_before_rgb: bool, image_decode_component_mismatch: bool, output_color_mode: string, notes: list<string>}
     */
    public function dctDecodeImageColorPlan(string $imageDictionary, string $jpegBytes, array $objects = []): array
    {
        $colorSpace = $this->imageColorSpace($imageDictionary) ?? 'DeviceRGB';
        $components = $this->jpegComponentCount($jpegBytes) ?? $this->componentCountForColorSpace($colorSpace);
        $adobeTransform = $this->jpegAdobeApp14Transform($jpegBytes);
        $dctFilter = $this->dctDecodeFilterName($imageDictionary, $objects);
        $hasDctFilter = $dctFilter !== null;
        $decodeParmsTransform = $hasDctFilter ? $this->dctDecodeParmsColorTransform($imageDictionary, $objects) : null;
        $decodeParmsDeclarationDuplicated = $hasDctFilter
            && $this->duplicatePdfNameDeclarationCount($imageDictionary, 'DecodeParms') > 0;
        $decodeParmsAlignmentInvalid = $hasDctFilter
            && $this->dctDecodeParmsAlignmentIsInvalid($imageDictionary, $objects);
        $decodeParmsOperandFailure = $hasDctFilter
            ? $this->dctDecodeParmsOperandFailureInDictionary($imageDictionary, $objects)
            : null;
        $decodeParmsDuplicateColorTransform = $hasDctFilter
            && $this->dctDecodeParmsColorTransformIsDuplicated($imageDictionary, $objects);
        $decodeParmsTransformValid = !$decodeParmsDeclarationDuplicated
            && !$decodeParmsAlignmentInvalid
            && $decodeParmsOperandFailure === null
            && !$decodeParmsDuplicateColorTransform
            && ($decodeParmsTransform === null || in_array($decodeParmsTransform, [0, 1, 2], true));
        $effectiveDecodeParmsTransform = $decodeParmsTransformValid ? $decodeParmsTransform : null;
        $effectiveTransform = $adobeTransform ?? $effectiveDecodeParmsTransform ?? ($components === 3 ? 1 : 0);
        $needsCmykToRgb = $colorSpace === 'DeviceCMYK' || $components === 4;
        $imageDecode = $this->imageDecodeDetails($imageDictionary, $objects, $components);
        $imageDecodeValid = $imageDecode !== null && $imageDecode['valid_for_components'];
        $imageDecodeMismatch = $imageDecode !== null && !$imageDecode['valid_for_components'];
        $notes = [];

        if ($adobeTransform !== null && $decodeParmsTransform !== null) {
            $notes[] = 'adobe_app14_transform_overrides_decodeparms';
        }
        if ($decodeParmsDeclarationDuplicated) {
            $notes[] = 'duplicate_dctdecode_decodeparms_declaration_fail_closed';
        } elseif ($decodeParmsAlignmentInvalid) {
            $notes[] = 'unaligned_dctdecode_decodeparms_fail_closed';
        } elseif ($decodeParmsOperandFailure !== null) {
            $notes[] = $decodeParmsOperandFailure['decode_parms_review'];
        } elseif ($decodeParmsDuplicateColorTransform) {
            $notes[] = 'duplicate_dctdecode_decodeparms_parameter_fail_closed';
        } elseif (!$decodeParmsTransformValid) {
            $notes[] = 'invalid_dctdecode_color_transform_ignored';
        }
        if ($needsCmykToRgb) {
            $notes[] = 'render_rgb_preview_from_cmyk';
        }
        if ($needsCmykToRgb && $effectiveTransform !== null && $effectiveTransform !== 0) {
            $notes[] = 'apply_ycck_to_cmyk_before_rgb';
        }
        if ($imageDecodeValid) {
            $notes[] = 'image_decode_applied_before_rgb_conversion';
            if ($imageDecode['inverted_components'] !== []) {
                $notes[] = 'image_decode_inverts_components_before_rgb';
            }
        } elseif ($imageDecodeMismatch) {
            $notes[] = 'image_decode_component_mismatch';
        }

        return [
            'filter' => $dctFilter ?? $this->imageFilterName($imageDictionary) ?? 'DCTDecode',
            'source_color_space' => $colorSpace,
            'components' => $components,
            'bits_per_component' => $this->imageBitsPerComponent($imageDictionary, $objects) ?? 8,
            'adobe_app14_transform' => $adobeTransform,
            'decode_parms_color_transform' => $decodeParmsTransform,
            'decode_parms_color_transform_valid' => $decodeParmsTransformValid,
            'decode_parms_color_transform_ignored' => !$decodeParmsTransformValid,
            'effective_color_transform' => $effectiveTransform,
            'adobe_marker_overrides_decode_parms' => $adobeTransform !== null && $decodeParmsTransform !== null,
            'needs_cmyk_to_rgb' => $needsCmykToRgb,
            'uses_ycck_transform' => $needsCmykToRgb && $effectiveTransform !== null && $effectiveTransform !== 0,
            'image_decode' => $imageDecode,
            'image_decode_applied_before_rgb' => $imageDecodeValid,
            'image_decode_component_mismatch' => $imageDecodeMismatch,
            'output_color_mode' => 'RGB',
            'notes' => $notes,
        ];
    }

    /**
     * Converts one decoded DCT sample into the RGB preview space used by Marker
     * image extraction. For `uses_ycck_transform`, the four components are
     * expected as Y, Cb, Cr, K and are first converted to CMYK.
     *
     * @param list<int|float> $sample
     * @param array{uses_ycck_transform?: bool, adobe_app14_transform?: int|null, bits_per_component?: int, image_decode?: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null} $plan
     * @return array{red: int, green: int, blue: int}
     */
    public function dctDecodeSampleToRgb(array $sample, array $plan = []): array
    {
        $values = array_map(fn (int|float $value): int => $this->byteValue($value), array_values($sample));
        if (count($values) !== 4) {
            throw new InvalidArgumentException('DCT CMYK sample must contain exactly 4 components.');
        }

        if (($plan['uses_ycck_transform'] ?? false) === true) {
            $values = $this->ycckToCmyk($values);
        } elseif (($plan['adobe_app14_transform'] ?? null) === 0) {
            $values = array_map(static fn (int $value): int => 255 - $value, $values);
        }

        $values = $this->applyDctImageDecode($values, $plan);

        return $this->cmykToRgb($values);
    }

    /**
     * Maps decoded image samples through a PDF image /Decode array before the
     * sample is interpreted in its color space.
     *
     * @param list<int|float> $sample
     * @param array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool} $decodePlan
     * @return list<float>
     */
    public function imageSampleDecodeValues(array $sample, array $decodePlan, int $bitsPerComponent = 8): array
    {
        if (($decodePlan['valid_for_components'] ?? false) !== true || !isset($decodePlan['ranges']) || !is_array($decodePlan['ranges'])) {
            throw new InvalidArgumentException('Image Decode array does not match the image component count.');
        }
        if ($bitsPerComponent <= 0) {
            throw new InvalidArgumentException('BitsPerComponent must be greater than zero.');
        }

        $values = array_values($sample);
        $ranges = array_values($decodePlan['ranges']);
        if (count($values) !== count($ranges)) {
            throw new InvalidArgumentException('Image sample component count does not match Decode ranges.');
        }

        $maxSample = (2 ** min($bitsPerComponent, 30)) - 1;
        $decoded = [];
        foreach ($values as $index => $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Image sample values must be numeric.');
            }

            $range = $ranges[$index];
            $min = (float) ($range['min'] ?? 0.0);
            $max = (float) ($range['max'] ?? 1.0);
            $ratio = max(0.0, min(1.0, (float) $value / $maxSample));
            $decoded[] = $min + (($max - $min) * $ratio);
        }

        return $decoded;
    }

    /**
     * Maps a one-bit /ImageMask sample through the stencil Decode array into
     * the opacity that a future RGB preview compositor should apply.
     *
     * @param array{present?: bool, decode?: array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool}, bits_per_component?: int|null} $imageMaskPlan
     */
    public function imageMaskSampleOpacity(int|float $sample, array $imageMaskPlan): float
    {
        if (($imageMaskPlan['present'] ?? false) !== true || !isset($imageMaskPlan['decode']) || !is_array($imageMaskPlan['decode'])) {
            throw new InvalidArgumentException('ImageMask plan must describe a present stencil mask.');
        }

        $decoded = $this->imageSampleDecodeValues(
            [$sample],
            $imageMaskPlan['decode'],
            max(1, (int) ($imageMaskPlan['bits_per_component'] ?? 1))
        );

        return max(0.0, min(1.0, $decoded[0]));
    }

    /**
     * Maps a soft-mask sample through the mask image /Decode array into the
     * alpha value that the RGB preview compositor should apply.
     *
     * @param array{present?: bool, decode?: array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool}, bits_per_component?: int|null} $softMaskPlan
     */
    public function softMaskSampleOpacity(int|float $sample, array $softMaskPlan): float
    {
        if (($softMaskPlan['present'] ?? false) !== true || !isset($softMaskPlan['decode']) || !is_array($softMaskPlan['decode'])) {
            throw new InvalidArgumentException('Soft mask plan must describe a present image mask.');
        }

        $decoded = $this->imageSampleDecodeValues(
            [$sample],
            $softMaskPlan['decode'],
            max(1, (int) ($softMaskPlan['bits_per_component'] ?? 8))
        );

        return max(0.0, min(1.0, $decoded[0]));
    }

    /**
     * Applies a bounded soft-mask transfer function sample. PDF transfer
     * functions accept one alpha/luminosity input and produce one mask value;
     * unsupported function families remain review-only metadata.
     *
     * @param array{sample_supported?: bool, preview_mode?: string, name?: string|null, function_type?: int|null, domain?: list<float>, range?: list<float>, c0?: list<float>, c1?: list<float>, exponent?: float|null} $transferFunction
     */
    public function softMaskTransferSampleOpacity(int|float $sample, array $transferFunction): float
    {
        if (($transferFunction['sample_supported'] ?? false) !== true) {
            throw new InvalidArgumentException('Soft-mask transfer function is review-only for sample preview.');
        }

        $input = max(0.0, min(1.0, (float) $sample));
        $domain = $transferFunction['domain'] ?? [];
        if (count($domain) >= 2) {
            $domainMin = min((float) $domain[0], (float) $domain[1]);
            $domainMax = max((float) $domain[0], (float) $domain[1]);
            $input = max($domainMin, min($domainMax, $input));
        }

        if (($transferFunction['preview_mode'] ?? null) === 'identity' || ($transferFunction['name'] ?? null) === 'Identity') {
            return max(0.0, min(1.0, $input));
        }

        if (($transferFunction['function_type'] ?? null) !== 2) {
            throw new InvalidArgumentException('Only Identity and FunctionType 2 soft-mask transfer samples are supported.');
        }

        $c0 = $transferFunction['c0'][0] ?? 0.0;
        $c1 = $transferFunction['c1'][0] ?? 1.0;
        $exponent = $transferFunction['exponent'] ?? 1.0;
        $value = (float) $c0 + (pow($input, (float) $exponent) * ((float) $c1 - (float) $c0));
        $range = $transferFunction['range'] ?? [];
        if (count($range) >= 2) {
            $rangeMin = min((float) $range[0], (float) $range[1]);
            $rangeMax = max((float) $range[0], (float) $range[1]);
            $value = max($rangeMin, min($rangeMax, $value));
        }

        return max(0.0, min(1.0, $value));
    }

    /**
     * @param array{soft_mask?: array<string, mixed>|null, soft_mask_transfer_function?: array<string, mixed>|null} $imagePlan
     * @return array{alpha: float, alpha_before_transfer: float, transfer_applied: bool, transfer_function: array<string, mixed>|null}
     */
    private function softMaskAlphaPreview(int|float $sample, array $imagePlan, string $context): array
    {
        $softMask = $imagePlan['soft_mask'] ?? null;
        if (!is_array($softMask)) {
            throw new InvalidArgumentException($context . ' soft-mask preview requires a soft-mask plan.');
        }

        $transferFunction = $imagePlan['soft_mask_transfer_function'] ?? null;
        $transferSupported = is_array($transferFunction) && ($transferFunction['sample_supported'] ?? false) === true;
        $decode = $softMask['decode'] ?? null;
        if (is_array($decode) && ($decode['valid_for_components'] ?? false) === true) {
            $alphaBeforeTransfer = $this->softMaskSampleOpacity($sample, $softMask);
        } elseif ($transferSupported) {
            $alphaBeforeTransfer = max(0.0, min(1.0, (float) $sample));
        } else {
            throw new InvalidArgumentException($context . ' soft-mask preview requires a soft-mask Decode array or supported transfer function.');
        }

        $transferApplied = false;
        $alpha = $alphaBeforeTransfer;
        if ($transferSupported) {
            $alpha = $this->softMaskTransferSampleOpacity($alphaBeforeTransfer, $transferFunction);
            $transferApplied = true;
        }

        return [
            'alpha' => $alpha,
            'alpha_before_transfer' => $alphaBeforeTransfer,
            'transfer_applied' => $transferApplied,
            'transfer_function' => is_array($transferFunction) ? $transferFunction : null,
        ];
    }

    /**
     * Native metadata boundary for PDF image ColorSpace and soft-mask handling.
     *
     * Upstream rasterizes through pypdfium/PIL and always returns an RGB image.
     * This does not rasterize pixels, but records the ICC profile, soft mask,
     * and matte decisions a review UI needs before handing the crop to a future
     * raster backend.
     *
     * @param array<int|string, mixed> $objects
     * @return array{
     *     image_filters: list<string>,
     *     image_filter_details: list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null|list<string>>|null}>,
     *     image_filter_boundary: array{preview_only_filters: list<string>, jbig2_globals_present: bool, native_raster_decode: bool},
     *     dctdecode_filter_boundary: array<string, mixed>|null,
     *     source_color_space: string,
     *     color_space_resource_name: string|null,
     *     color_space_resource_value: string|null,
     *     color_space_resource_source: string|null,
     *     color_space_resolved_from_resources: bool,
     *     components: int|null,
     *     bits_per_component: int,
     *     uses_icc_profile: bool,
     *     icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null,
     *     uses_calibrated_color_space: bool,
     *     calibrated_color_space: array{family: string, dictionary_source: string|null, dictionary_object: int|null, white_point: list<float>, black_point: list<float>, gamma: float|list<float>|null, matrix: list<float>|null, range: list<float>|null, default_decode: list<float>}|null,
     *     uses_alternate_color_space: bool,
     *     alternate_color_space: array{family: string, colorant_names: list<string>, alternate_color_space: string|null, alternate_components: int|null, alternate_uses_icc_profile: bool, tint_transform_source: string|null, tint_transform_object: int|null, tint_transform_function_type: int|null, attributes_present: bool}|null,
     *     uses_indexed_color_space: bool,
     *     indexed_color_space: array{base_color_space: string|null, base_components: int|null, base_uses_icc_profile: bool, base_icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, high_value: int|null, lookup_source: string|null, lookup_length: int|null, expected_lookup_length: int|null, lookup_length_matches: bool, lookup_entry_count: int|null, lookup_preview_hex: string, lookup_bytes: list<int>}|null,
     *     jpx_soft_mask_in_data: array{present: bool, value: int|null, valid_value: bool, filter_is_jpx: bool, uses_embedded_soft_mask: bool, encoded_soft_mask_values: bool, preblended_with_matte: bool, external_soft_mask_present: bool, external_soft_mask_ignored: bool, ignored_without_jpx: bool, review_only: bool}|null,
     *     soft_mask: array{present: bool, subtype: string|null, width: int|null, height: int|null, color_space: string|null, components: int|null, bits_per_component: int|null, decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null, opacity_for_zero: float|null, opacity_for_max: float|null, decode_inverted: bool, decode_component_mismatch: bool, matte: list<float>|null, interpolate: bool|null}|null,
     *     soft_mask_filter_boundary: array{present: bool, source_object: int|null, filters: list<string>, preview_only_filters: list<string>, unsupported_filters: list<string>, raw_length: int|null, decoded_length: int|null, decoded_sha256: string|null, decoded_preview_hex: string|null, decoded_sample_bytes: list<int>, decoded_with_current_filters: bool, decode_failed: bool, uses_current_object_map: bool, native_prefix_decoded?: true, native_prefix_decoded_length?: int, native_prefix_decoded_sha256?: string, native_prefix_decoded_preview_hex?: string, stopped_before_filter?: string|null}|null,
     *     soft_mask_group: array<string, mixed>|null,
     *     soft_mask_transfer_function: array<string, mixed>|null,
     *     soft_mask_is_grayscale: bool|null,
     *     soft_mask_color_space_supported: bool|null,
     *     soft_mask_matte: array{component_count: int, expected_components: int|null, matches_image_components: bool}|null,
     *     image_decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null,
     *     image_decode_applied_before_rgb: bool,
     *     image_decode_component_mismatch: bool,
     *     color_key_mask: array{present: bool, ranges: list<array{min: int, max: int}>, component_count: int, expected_components: int|null, valid_for_components: bool, source: string, compares_before_decode: bool, transparent_when_all_components_match: bool}|null,
     *     color_key_mask_applied_before_rgb: bool,
     *     color_key_mask_suppressed_by_soft_mask: bool,
     *     color_key_mask_component_mismatch: bool,
     *     image_mask: array{present: bool, width: int|null, height: int|null, bits_per_component: int, decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}, opacity_for_zero: float, opacity_for_one: float, inverted: bool}|null,
     *     image_mask_applied_before_rgb: bool,
     *     soft_mask_applied_before_rgb: bool,
     *     soft_mask_decode_applied_before_rgb: bool,
     *     soft_mask_decode_component_mismatch: bool,
     *     soft_mask_transfer_function_applied_before_rgb: bool,
     *     matte_unblending_required: bool,
     *     output_color_mode: string,
     *     alpha_output_mode: string,
     *     notes: list<string>
     * }
     */
    public function imageColorSpaceSoftMaskPlan(string $imageDictionary, array $objects = []): array
    {
        $colorSpace = $this->imageColorSpaceDetails($imageDictionary, $objects);
        $imageFilterSlots = $this->imageFilterValues($imageDictionary, $objects);
        $imageFilterDetails = $this->imageFilterDetails($imageDictionary, $objects);
        $imageFilterValues = array_map(
            static fn (array $filter): string => $filter['filter'],
            $imageFilterDetails
        );
        $imageFilters = $this->publicImageFilterList($imageFilterValues);
        $duplicateFilterDeclarationCount = $this->duplicatePdfNameDeclarationCount($imageDictionary, 'Filter');
        $previewOnlyFilterValues = array_values(array_filter(
            $imageFilterValues,
            fn (string $filter): bool => $this->isPreviewOnlyImageFilter($filter)
        ));
        $previewOnlyFilters = $this->publicImageFilterList($previewOnlyFilterValues);
        $operandBoundaryFilters = $this->imageFilterOperandBoundaryFilters($imageFilterValues);
        $filterExtraOperand = $this->imageFilterExtraOperandBoundary($imageDictionary);
        $jpxSoftMaskInData = $this->jpxSoftMaskInDataDetails($imageDictionary, $imageFilterValues, $objects);
        $jpxEmbeddedSoftMaskPresent = is_array($jpxSoftMaskInData)
            && ($jpxSoftMaskInData['uses_embedded_soft_mask'] ?? false) === true;
        $imageMask = $this->imageMaskDetails($imageDictionary, $objects);
        $imageMaskPresent = $imageMask !== null && $imageMask['present'] === true;
        $bitsPerComponent = $imageMaskPresent ? ($this->imageBitsPerComponent($imageDictionary, $objects) ?? 1) : ($this->imageBitsPerComponent($imageDictionary, $objects) ?? 8);
        $expectedDecodeComponents = $imageMaskPresent ? 1 : $colorSpace['components'];
        $imageDecode = $this->imageDecodeDetails($imageDictionary, $objects, $expectedDecodeComponents, $imageMaskPresent);
        if (
            $imageDecode === null
            && !$imageMaskPresent
            && $colorSpace['uses_indexed_color_space']
            && is_array($colorSpace['indexed_color_space'])
            && is_int($colorSpace['indexed_color_space']['high_value'])
        ) {
            $imageDecode = $this->buildImageDecodeDetails(
                [0.0, (float) $colorSpace['indexed_color_space']['high_value']],
                1,
                'default-indexed'
            );
        }
        if (
            $imageDecode === null
            && !$imageMaskPresent
            && ($colorSpace['uses_calibrated_color_space'] ?? false) === true
            && is_array($colorSpace['calibrated_color_space'] ?? null)
        ) {
            $imageDecode = $this->buildImageDecodeDetails(
                $colorSpace['calibrated_color_space']['default_decode'],
                $colorSpace['components'],
                'default-calibrated'
            );
        }
        if ($imageMaskPresent && $imageDecode !== null) {
            $imageMask = $this->imageMaskDetails($imageDictionary, $objects, $imageDecode);
        }
        $colorKeyMask = $imageMaskPresent ? null : $this->colorKeyMaskDetails($imageDictionary, $objects, $colorSpace['components']);
        $softMask = $jpxEmbeddedSoftMaskPresent ? null : $this->imageSoftMaskDetails($imageDictionary, $objects);
        $softMaskPresent = $softMask !== null && $softMask['present'] === true;
        $colorKeyMaskSuppressedBySoftMask = $colorKeyMask !== null && ($softMaskPresent || $jpxEmbeddedSoftMaskPresent);
        $colorKeyMaskValid = $colorKeyMask !== null
            && $colorKeyMask['valid_for_components']
            && !$colorKeyMaskSuppressedBySoftMask;
        $colorKeyMaskMismatch = $colorKeyMask !== null && !$colorKeyMask['valid_for_components'];
        $softMaskGroup = $jpxEmbeddedSoftMaskPresent ? null : $this->imageSoftMaskGroupDetails($imageDictionary, $objects);
        $softMaskTransferFunction = is_array($softMaskGroup) ? ($softMaskGroup['transfer_function'] ?? null) : null;
        $softMaskFilterBoundary = $softMask !== null ? $this->imageSoftMaskFilterBoundary($imageDictionary, $objects) : null;
        $softMaskIsGrayscale = $softMaskPresent ? $this->softMaskIsGrayscale($softMask) : null;
        $softMaskComposable = $softMaskPresent && $softMaskIsGrayscale === true;
        $softMaskMatte = $this->softMaskMatteDetails($softMask, $colorSpace['components']);
        $matteUnblendingRequired = $softMaskComposable && ($softMaskMatte['matches_image_components'] ?? false);
        $imageDecodeValid = $imageDecode !== null && $imageDecode['valid_for_components'];
        $imageDecodeMismatch = $imageDecode !== null && !$imageDecode['valid_for_components'];
        $ccittDecodeBoundary = $this->ccittFaxDecodeBoundaryReview(
            $imageFilterDetails,
            $this->integerNameValue($imageDictionary, 'Width', $objects),
            $this->integerNameValue($imageDictionary, 'Height', $objects)
        );
        $ccittFilterBoundary = $this->ccittFaxFilterBoundaryReview($imageFilterDetails);
        $ccittCodingBoundary = $this->ccittFaxCodingBoundaryReview($imageFilterDetails);
        $ccittImageMaskPolarityBoundary = $this->ccittFaxImageMaskPolarityBoundary($ccittDecodeBoundary, $imageMask);
        $dctDecodeFilterBoundary = $this->dctDecodeFilterBoundaryReview($imageFilterDetails, $imageFilterSlots);
        $notes = [];

        if ($colorSpace['uses_indexed_color_space']) {
            $notes[] = 'indexed_color_space_palette_before_rgb_conversion';
            if (($colorSpace['indexed_color_space']['base_uses_icc_profile'] ?? false) === true) {
                $notes[] = 'indexed_base_icc_profile_color_space';
            }
            if (($colorSpace['indexed_color_space']['base_uses_alternate_color_space'] ?? false) === true) {
                $baseFamily = strtolower((string) ($colorSpace['indexed_color_space']['base_alternate_color_space']['family'] ?? 'alternate'));
                $notes[] = 'indexed_base_' . $baseFamily . '_tint_transform_review_before_rgb_conversion';
            }
            if (($colorSpace['indexed_color_space']['lookup_length_matches'] ?? true) === false) {
                $notes[] = 'indexed_lookup_length_mismatch';
            }
        }
        if (($colorSpace['uses_alternate_color_space'] ?? false) === true && is_array($colorSpace['alternate_color_space'] ?? null)) {
            $family = strtolower((string) $colorSpace['alternate_color_space']['family']);
            $notes[] = $family . '_tint_transform_review_before_rgb_conversion';
            if (($colorSpace['alternate_color_space']['alternate_uses_icc_profile'] ?? false) === true) {
                $notes[] = 'alternate_icc_profile_color_space';
            }
        }
        if ($colorSpace['uses_icc_profile']) {
            $notes[] = 'icc_profile_color_space';
        }
        if (($colorSpace['uses_calibrated_color_space'] ?? false) === true && is_array($colorSpace['calibrated_color_space'] ?? null)) {
            $notes[] = strtolower((string) $colorSpace['calibrated_color_space']['family']) . '_calibrated_color_space_review_before_rgb_conversion';
            if ($imageDecode !== null && $imageDecode['source'] === 'default-calibrated') {
                $notes[] = 'calibrated_default_decode_applied_before_rgb_conversion';
            }
        }
        if (($colorSpace['color_space_resolved_from_resources'] ?? false) === true) {
            $notes[] = 'image_color_space_resolved_from_current_resources';
        }
        if ($jpxSoftMaskInData !== null) {
            if (($jpxSoftMaskInData['ignored_without_jpx'] ?? false) === true) {
                $notes[] = 'smask_in_data_ignored_without_jpx';
                if (($jpxSoftMaskInData['valid_value'] ?? true) === false) {
                    $notes[] = 'jpx_smaskindata_value_out_of_range_review_only';
                }
            } elseif (($jpxSoftMaskInData['valid_value'] ?? true) === false) {
                $notes[] = 'jpx_smaskindata_value_out_of_range_review_only';
            } elseif (($jpxSoftMaskInData['uses_embedded_soft_mask'] ?? false) === true) {
                $notes[] = 'jpx_embedded_soft_mask_review_before_rgb_conversion';
                if (($jpxSoftMaskInData['preblended_with_matte'] ?? false) === true) {
                    $notes[] = 'jpx_embedded_soft_mask_preblended_matte_review';
                }
                if (($jpxSoftMaskInData['external_soft_mask_ignored'] ?? false) === true) {
                    $notes[] = 'jpx_smaskindata_ignores_external_smask';
                }
            } else {
                $notes[] = 'jpx_smaskindata_zero_ignores_embedded_soft_mask';
            }
        }
        if ($imageDecodeValid) {
            $notes[] = 'image_decode_applied_before_rgb_conversion';
            if ($imageDecode['inverted_components'] !== []) {
                $notes[] = 'image_decode_inverts_components_before_rgb';
            }
        } elseif ($imageDecodeMismatch) {
            $notes[] = 'image_decode_component_mismatch';
        }
        if ($colorKeyMaskValid) {
            $notes[] = 'color_key_mask_applied_before_rgb_conversion';
            $notes[] = 'color_key_mask_compares_raw_samples_before_decode';
        } elseif ($colorKeyMaskSuppressedBySoftMask) {
            $notes[] = 'color_key_mask_suppressed_by_soft_mask';
            $notes[] = 'soft_mask_overrides_color_key_mask';
            if ($jpxEmbeddedSoftMaskPresent) {
                $notes[] = 'jpx_embedded_soft_mask_overrides_color_key_mask';
            }
        } elseif ($colorKeyMaskMismatch) {
            $notes[] = 'color_key_mask_component_mismatch';
        }
        foreach ($previewOnlyFilterValues as $filter) {
            $notes[] = match ($filter) {
                'DCTDecode', 'DCT' => 'dctdecode_image_filter_review_only',
                'JBIG2Decode' => 'jbig2_image_filter_review_only',
                'JPXDecode' => 'jpx_image_filter_review_only',
                'CCITTFaxDecode', 'CCF' => 'ccitt_fax_image_filter_review_only',
                default => 'image_filter_review_only',
            };
        }
        foreach ($imageFilterDetails as $detail) {
            $decodeParmsReview = is_array($detail['decode_parms'] ?? null)
                ? ($detail['decode_parms']['decode_parms_review'] ?? null)
                : null;
            if (is_string($decodeParmsReview) && str_contains($decodeParmsReview, '_dctdecode_decodeparms_')) {
                $notes[] = $decodeParmsReview;
            }
            if ($decodeParmsReview === 'duplicate_ccitt_decodeparms_declaration_fail_closed') {
                $notes[] = $decodeParmsReview;
            }
        }
        foreach ($operandBoundaryFilters as $filter) {
            $notes[] = $filter === self::UNRESOLVED_IMAGE_FILTER_OPERAND
                ? 'unresolved_image_filter_operand_fail_closed'
                : 'malformed_image_filter_operand_fail_closed';
        }
        if ($duplicateFilterDeclarationCount > 0) {
            $notes[] = 'duplicate_image_filter_declarations_fail_closed';
            if ($ccittFilterBoundary !== null) {
                $notes[] = 'ccitt_fax_duplicate_filter_declarations_fail_closed';
            }
        }
        if (
            $dctDecodeFilterBoundary !== null
            && ($dctDecodeFilterBoundary['post_dctdecode_filters_block_native_decode'] ?? false) === true
        ) {
            $notes[] = 'dctdecode_post_filters_block_native_decode';
        }
        if (
            $dctDecodeFilterBoundary !== null
            && ($dctDecodeFilterBoundary['pre_dctdecode_preview_filters_block_native_prefix_decode'] ?? false) === true
        ) {
            $notes[] = 'dctdecode_pre_preview_filters_block_native_prefix_decode';
        }
        if ($imageMaskPresent) {
            $notes[] = 'image_mask_stencil_applied_before_rgb_conversion';
            if (($imageMask['inverted'] ?? false) === true) {
                $notes[] = 'image_mask_decode_inverts_stencil';
            }
            if ($ccittImageMaskPolarityBoundary !== null) {
                $notes[] = 'ccitt_fax_imagemask_polarity_review_before_rgb_conversion';
            }
        }
        if ($softMaskPresent && $softMaskGroup !== null) {
            $notes[] = 'soft_mask_dictionary_review_before_rgb_conversion';
            $subtype = strtolower((string) ($softMaskGroup['subtype'] ?? ''));
            if ($subtype === 'alpha' || $subtype === 'luminosity') {
                $notes[] = 'soft_mask_' . $subtype . '_group_review_before_rgb_conversion';
            }
            if (($softMaskGroup['uses_indexed_color_space'] ?? false) === true) {
                $notes[] = 'soft_mask_group_indexed_color_space_review_before_rgb_conversion';
            }
            if (is_array($softMaskTransferFunction)) {
                $notes[] = ($softMaskTransferFunction['sample_supported'] ?? false) === true
                    ? 'soft_mask_transfer_function_applied_before_rgb_conversion'
                    : 'soft_mask_transfer_function_review_only';
            }
        } elseif ($softMaskPresent) {
            if ($softMaskComposable) {
                $notes[] = 'soft_mask_applied_before_rgb_conversion';
            } else {
                $notes[] = 'soft_mask_color_space_not_grayscale';
            }
            if ($softMaskComposable && ($softMask['decode'] ?? null) !== null && ($softMask['decode']['valid_for_components'] ?? false) === true) {
                $notes[] = 'soft_mask_decode_applied_before_rgb_conversion';
                if (($softMask['decode_inverted'] ?? false) === true) {
                    $notes[] = 'soft_mask_decode_inverts_alpha';
                }
            } elseif (($softMask['decode_component_mismatch'] ?? false) === true) {
                $notes[] = 'soft_mask_decode_component_mismatch';
            }
            if ($softMaskMatte !== null && !$softMaskMatte['matches_image_components']) {
                $notes[] = 'soft_mask_matte_component_mismatch';
            }
            if ($softMaskFilterBoundary !== null && $softMaskFilterBoundary['filters'] !== []) {
                if ($softMaskFilterBoundary['decoded_with_current_filters']) {
                    $notes[] = 'soft_mask_stream_filters_decoded_before_rgb_conversion';
                } elseif ($softMaskFilterBoundary['preview_only_filters'] !== []) {
                    $notes[] = 'soft_mask_stream_filter_preview_only';
                    if (($softMaskFilterBoundary['native_prefix_decoded'] ?? false) === true) {
                        $notes[] = 'soft_mask_stream_native_prefix_decoded_before_preview_only';
                    }
                } elseif ($softMaskFilterBoundary['decode_failed']) {
                    $notes[] = 'soft_mask_stream_filter_decode_failed';
                }
            }
        } elseif ($softMask !== null) {
            $notes[] = 'soft_mask_none';
        }
        if ($matteUnblendingRequired) {
            $notes[] = 'soft_mask_matte_unblend_before_rgb';
        }

        return [
            'image_filters' => $imageFilters,
            'image_filter_details' => $imageFilterDetails,
            'image_filter_boundary' => [
                'preview_only_filters' => $previewOnlyFilters,
                'jbig2_globals_present' => $this->jbig2GlobalsPresent($imageDictionary, $objects),
                'native_raster_decode' => $previewOnlyFilters === [] && $operandBoundaryFilters === [],
                ...$this->imageFilterOperandBoundaryMetadata($operandBoundaryFilters, $filterExtraOperand),
                ...($duplicateFilterDeclarationCount > 0 ? [
                    'duplicate_filter_declaration_count' => $duplicateFilterDeclarationCount,
                    'filter_operand_policy' => 'reject_duplicate_filter_declarations',
                ] : []),
            ],
            'dctdecode_filter_boundary' => $dctDecodeFilterBoundary,
            'ccitt_fax_filter_boundary' => $ccittFilterBoundary,
            'ccitt_fax_decode_boundary' => $ccittDecodeBoundary,
            'ccitt_fax_coding_boundary' => $ccittCodingBoundary,
            'ccitt_fax_imagemask_polarity_boundary' => $ccittImageMaskPolarityBoundary,
            'source_color_space' => $imageMaskPresent ? 'ImageMask' : $colorSpace['source_color_space'],
            'color_space_resource_name' => $imageMaskPresent ? null : ($colorSpace['color_space_resource_name'] ?? null),
            'color_space_resource_value' => $imageMaskPresent ? null : ($colorSpace['color_space_resource_value'] ?? null),
            'color_space_resource_source' => $imageMaskPresent ? null : ($colorSpace['color_space_resource_source'] ?? null),
            'color_space_resolved_from_resources' => !$imageMaskPresent && (($colorSpace['color_space_resolved_from_resources'] ?? false) === true),
            'components' => $imageMaskPresent ? 1 : $colorSpace['components'],
            'bits_per_component' => $bitsPerComponent,
            'uses_icc_profile' => $colorSpace['uses_icc_profile'],
            'icc_profile' => $colorSpace['icc_profile'],
            'uses_calibrated_color_space' => ($colorSpace['uses_calibrated_color_space'] ?? false) === true,
            'calibrated_color_space' => $colorSpace['calibrated_color_space'] ?? null,
            'uses_alternate_color_space' => $colorSpace['uses_alternate_color_space'],
            'alternate_color_space' => $colorSpace['alternate_color_space'],
            'uses_indexed_color_space' => $colorSpace['uses_indexed_color_space'],
            'indexed_color_space' => $colorSpace['indexed_color_space'],
            'jpx_soft_mask_in_data' => $jpxSoftMaskInData,
            'image_decode' => $imageDecode,
            'image_decode_applied_before_rgb' => $imageDecodeValid,
            'image_decode_component_mismatch' => $imageDecodeMismatch,
            'color_key_mask' => $colorKeyMask,
            'color_key_mask_applied_before_rgb' => $colorKeyMaskValid,
            'color_key_mask_suppressed_by_soft_mask' => $colorKeyMaskSuppressedBySoftMask,
            'color_key_mask_component_mismatch' => $colorKeyMaskMismatch,
            'image_mask' => $imageMask,
            'image_mask_applied_before_rgb' => $imageMaskPresent,
            'soft_mask' => $softMask,
            'soft_mask_filter_boundary' => $softMaskFilterBoundary,
            'soft_mask_group' => $softMaskGroup,
            'soft_mask_transfer_function' => $softMaskTransferFunction,
            'soft_mask_is_grayscale' => $softMaskIsGrayscale,
            'soft_mask_color_space_supported' => $softMaskIsGrayscale,
            'soft_mask_matte' => $softMaskMatte,
            'soft_mask_applied_before_rgb' => $softMaskComposable,
            'soft_mask_decode_applied_before_rgb' => $softMaskPresent
                && $softMaskComposable
                && ($softMask['decode'] ?? null) !== null
                && ($softMask['decode']['valid_for_components'] ?? false) === true,
            'soft_mask_decode_component_mismatch' => $softMaskPresent
                && (($softMask['decode_component_mismatch'] ?? false) === true),
            'soft_mask_transfer_function_applied_before_rgb' => is_array($softMaskTransferFunction)
                && ($softMaskTransferFunction['sample_supported'] ?? false) === true,
            'matte_unblending_required' => $matteUnblendingRequired,
            'output_color_mode' => 'RGB',
            'alpha_output_mode' => $softMaskComposable
                ? 'soft_mask_composited_to_rgb_preview'
                : (
                    $imageMaskPresent
                        ? 'image_mask_composited_to_rgb_preview'
                        : (
                            $colorKeyMaskValid
                                ? 'color_key_mask_composited_to_rgb_preview'
                                : (
                                    $jpxEmbeddedSoftMaskPresent
                                        ? 'jpx_embedded_soft_mask_review_only_rgb_preview'
                                        : ($softMaskPresent ? 'soft_mask_review_only_rgb_preview' : 'opaque_rgb_preview')
                                )
                        )
                ),
            'notes' => $notes,
        ];
    }

    /**
     * Adds PDF/A OutputIntent color-management context to JPX image review.
     *
     * Upstream reaches this boundary through PDFium and PIL RGB conversion.
     * The native port keeps JPX raster data review-only, while preserving the
     * document PDF/A profile context that should govern device color spaces.
     *
     * @param array<int|string, mixed> $objects
     * @param array<string, mixed> $documentMetadata Pass PdfMetadataExtractor output or its `pdfa` subarray.
     * @return array<string, mixed>
     */
    public function jpxSoftMaskColorSpacePdfaReviewPlan(
        string $imageObject,
        array $objects = [],
        array $documentMetadata = []
    ): array {
        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        if (!in_array('JPXDecode', $plan['image_filters'], true)) {
            throw new InvalidArgumentException('JPX PDF/A image review requires a JPXDecode image stream.');
        }

        $imageStream = $this->decodedImageStreamPreviewBoundary($dictionary, $imageObject, $objects);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);
        $pdfa = $this->pdfaOutputIntentReviewMetadata($documentMetadata);
        $colorManagement = $this->imagePdfaColorManagementReview($plan, $pdfa);
        $notes = $plan['notes'] ?? [];
        if ($pdfa['present']) {
            $notes[] = 'pdfa_output_intent_review_before_rgb_conversion';
            $notes[] = $colorManagement['pdfa_output_intent_applies_to_rgb_preview']
                ? 'pdfa_output_intent_supplies_device_color_profile'
                : 'pdfa_output_intent_preserved_as_document_color_context';
            if ($colorManagement['profile_source'] === 'image_icc_profile') {
                $notes[] = 'image_icc_profile_precedes_pdfa_output_intent_for_preview';
            }
            if (is_array($plan['jpx_soft_mask_in_data'] ?? null) && ($plan['jpx_soft_mask_in_data']['uses_embedded_soft_mask'] ?? false) === true) {
                $notes[] = 'jpx_embedded_soft_mask_preserved_with_pdfa_output_intent';
            } elseif (is_array($plan['soft_mask'] ?? null) && ($plan['soft_mask']['present'] ?? false) === true) {
                $notes[] = 'external_soft_mask_preserved_with_pdfa_output_intent';
            }
        }
        $notes[] = 'jpx_pdfa_image_stream_review_only_before_rgb_conversion';

        $plan['image_stream'] = $imageStreamMeta;
        $plan['review_only_image_stream'] = $imageStreamMeta['preview_only_filters'] !== [];
        $plan['native_jpx_raster_decode'] = false;
        $plan['pdfa_output_intent'] = $pdfa;
        $plan['color_management'] = $colorManagement;
        $plan['pdfa_output_intent_applies_before_rgb'] = $colorManagement['pdfa_output_intent_applies_to_rgb_preview'];
        $plan['notes'] = array_values(array_unique($notes));

        return $plan;
    }

    /**
     * Dispatches a current PDF image stream through the native color-space,
     * soft-mask, and transfer-function preview path used before Marker's RGB
     * image handoff.
     *
     * @param array<int|string, mixed> $objects
     * @return array<string, mixed>
     */
    public function imageRenderingColorSpaceSoftMaskTransferBundle(
        string $imageObject,
        array $objects = [],
        int $maxPixels = 16
    ): array {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('Image rendering bundle preview requires at least one preview pixel.');
        }

        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        $selectedPreview = null;

        if (($plan['uses_indexed_color_space'] ?? false) === true) {
            $selectedPreview = 'indexed';
            $preview = $this->indexedImageStreamPreviewRows($imageObject, $objects, $maxPixels);
        } elseif (($plan['uses_alternate_color_space'] ?? false) === true) {
            $selectedPreview = 'alternate_colorant';
            $preview = $this->alternateColorantStreamPreviewRows($imageObject, $objects, $maxPixels);
        } elseif (($plan['uses_calibrated_color_space'] ?? false) === true) {
            $selectedPreview = 'calibrated';
            $preview = $this->calibratedImageStreamPreviewRows($imageObject, $objects, $maxPixels);
        } elseif (($plan['source_color_space'] ?? null) === 'ICCBased') {
            $selectedPreview = 'iccbased';
            $preview = $this->iccBasedImageStreamPreviewRows($imageObject, $objects, $maxPixels);
        } elseif (($plan['source_color_space'] ?? null) === 'DeviceGray') {
            $selectedPreview = 'devicegray';
            $preview = $this->deviceGrayImageStreamPreviewRows($imageObject, $objects, $maxPixels);
        } else {
            throw new InvalidArgumentException('Image rendering bundle preview requires an Indexed, alternate, calibrated, ICCBased, or DeviceGray image stream.');
        }

        $preview['render_bundle'] = $this->imageRenderingBundleSummary($selectedPreview, $plan, $preview);
        $preview['notes'] = $this->imageRenderingBundleNotes($selectedPreview, $plan, $preview);

        return $preview;
    }

    /**
     * Native review boundary for PDF content-stream inline images.
     *
     * Inline images use short dictionary names and values, have no object
     * number, and are image payloads inside page content streams. This returns
     * the same RGB-preview metadata as image XObjects after expanding the
     * inline abbreviations, while keeping raster filters such as JBIG2
     * review-only.
     *
     * @param array<int|string, mixed> $objects
     * @return array<string, mixed>
     */
    public function inlineImageReviewPlan(string $inlineImageDictionary, string $payload, array $objects = []): array
    {
        $decodeTrailingOperandInvalid = $this->inlineImageDecodeHasMalformedTrailingOperand($inlineImageDictionary);
        $dictionaryOperandInvalid = $this->inlineImageDictionaryHasMalformedTailOperand($inlineImageDictionary);
        $sourceFilterValues = $this->inlineImageSourceFilterValues($inlineImageDictionary, $objects);
        $canonical = $this->canonicalInlineImageDictionary($inlineImageDictionary);
        $plan = $this->imageColorSpaceSoftMaskPlan($canonical, $objects);
        if ($decodeTrailingOperandInvalid) {
            $expectedComponents = is_array($plan['image_decode'] ?? null)
                ? ($plan['image_decode']['expected_components'] ?? null)
                : null;
            $plan['image_decode'] = $this->buildImageDecodeDetails(
                [],
                is_int($expectedComponents) ? $expectedComponents : null,
                'invalid'
            );
            $plan['image_decode_applied_before_rgb'] = false;
            $plan['image_decode_component_mismatch'] = true;
        }
        $filters = $plan['image_filters'];
        $filterValues = $this->imageFilterValues($canonical, $objects);
        $sourceFilters = $this->publicInlineImageSourceFilterList($sourceFilterValues);
        $sourcePreviewOnlyFilters = $this->publicInlineImageSourceFilterList(array_values(array_filter(
            $sourceFilterValues,
            fn (?string $filter): bool => is_string($filter) && $this->isPreviewOnlyImageFilter($filter)
        )));
        $sourceFilterAliases = $this->inlineImageSourceFilterAliasRows($sourceFilterValues, $filterValues);
        $previewOnlyFilters = $plan['image_filter_boundary']['preview_only_filters'];
        $operandBoundaryFilters = $this->imageFilterOperandBoundaryFilters($filters);
        $unsupportedFilters = $this->unsupportedInlineImageFilters($filterValues, $canonical, $objects);
        $decodeOperandInvalid = ($plan['image_decode_component_mismatch'] ?? false) === true;
        $geometryOperandInvalid = $this->inlineImageGeometryOperandInvalid($canonical, $objects);
        if ($unsupportedFilters !== []) {
            $plan['image_filter_boundary']['unsupported_filters'] = $unsupportedFilters;
            $plan['image_filter_boundary']['native_raster_decode'] = false;
        }
        if ($geometryOperandInvalid) {
            $plan['image_filter_boundary']['native_raster_decode'] = false;
        }
        if ($dictionaryOperandInvalid) {
            $plan['image_filter_boundary']['native_raster_decode'] = false;
        }
        $softMask = is_array($plan['soft_mask'] ?? null) ? $plan['soft_mask'] : null;
        $softMaskBoundary = is_array($plan['soft_mask_filter_boundary'] ?? null) ? $plan['soft_mask_filter_boundary'] : null;
        $jpxSoftMaskInData = is_array($plan['jpx_soft_mask_in_data'] ?? null) ? $plan['jpx_soft_mask_in_data'] : null;

        $plan['inline_image'] = [
            'present' => true,
            'canonical_dictionary' => $canonical,
            'payload_length' => strlen($payload),
            'payload_sha256' => hash('sha256', $payload),
            'payload_preview_hex' => strtoupper(bin2hex(substr($payload, 0, 16))),
            'uses_abbreviations' => trim($canonical) !== trim($inlineImageDictionary),
            'has_object_number' => false,
            'excluded_from_visible_text' => true,
            'source_filters' => $sourceFilters,
            'source_preview_only_filters' => $sourcePreviewOnlyFilters,
            'source_filter_aliases' => $sourceFilterAliases,
            'source_ccitt_alias_used' => in_array('CCF', $sourceFilterValues, true),
            'review_only_filters' => $previewOnlyFilters,
            'unsupported_filters' => $unsupportedFilters,
            'native_raster_decode' => $previewOnlyFilters === []
                && $operandBoundaryFilters === []
                && $unsupportedFilters === []
                && !$decodeOperandInvalid
                && !$geometryOperandInvalid
                && !$dictionaryOperandInvalid,
            'geometry_operand_invalid' => $geometryOperandInvalid,
            'dictionary_operand_invalid' => $dictionaryOperandInvalid,
            'soft_mask_present' => $softMask !== null && ($softMask['present'] ?? false) === true,
            'soft_mask_source_object' => $softMaskBoundary['source_object'] ?? null,
            'soft_mask_uses_current_object_map' => $softMaskBoundary['uses_current_object_map'] ?? null,
            'soft_mask_decoded_with_current_filters' => $softMaskBoundary['decoded_with_current_filters'] ?? null,
            'soft_mask_decode_applied_before_rgb' => ($plan['soft_mask_decode_applied_before_rgb'] ?? false) === true,
            'jpx_soft_mask_in_data_present' => $jpxSoftMaskInData !== null,
            'jpx_embedded_soft_mask_present' => is_array($jpxSoftMaskInData) && ($jpxSoftMaskInData['uses_embedded_soft_mask'] ?? false) === true,
            'jpx_embedded_soft_mask_review_only' => is_array($jpxSoftMaskInData) && ($jpxSoftMaskInData['review_only'] ?? false) === true,
        ];
        $plan['inline_image_abbreviations_expanded'] = $plan['inline_image']['uses_abbreviations'];
        $plan['inline_image_payload_excluded_from_text'] = true;
        $plan['inline_image_geometry_operand_invalid'] = $geometryOperandInvalid;
        $plan['inline_image_dictionary_operand_invalid'] = $dictionaryOperandInvalid;
        $plan['inline_image_review_only'] = $previewOnlyFilters !== []
            || $unsupportedFilters !== []
            || $decodeOperandInvalid
            || $geometryOperandInvalid
            || $dictionaryOperandInvalid;
        $plan['notes'][] = 'inline_image_dictionary_abbreviations_expanded';
        $plan['notes'][] = 'inline_image_payload_excluded_from_visible_text';
        if ($decodeOperandInvalid) {
            $plan['notes'][] = 'inline_image_decode_operand_review_only';
        }
        if ($geometryOperandInvalid) {
            $plan['notes'][] = 'inline_image_geometry_operand_review_only';
        }
        if ($dictionaryOperandInvalid) {
            $plan['notes'][] = 'inline_image_dictionary_operand_review_only';
        }
        if (in_array('JBIG2Decode', $filters, true)) {
            $plan['notes'][] = 'inline_jbig2_image_filter_review_only';
        }
        if (in_array('JPXDecode', $filters, true)) {
            $plan['notes'][] = 'inline_jpx_image_filter_review_only';
        }
        if (in_array('DCTDecode', $filters, true) || in_array('DCT', $filters, true)) {
            $plan['notes'][] = 'inline_dct_image_filter_review_only';
        }
        if (in_array('CCITTFaxDecode', $filters, true) || in_array('CCF', $filters, true)) {
            $plan['notes'][] = 'inline_ccitt_fax_image_filter_review_only';
            if (in_array('CCF', $sourceFilterValues, true)) {
                $plan['notes'][] = 'inline_ccitt_fax_source_alias_preserved';
            }
            if (($plan['ccitt_fax_imagemask_polarity_boundary'] ?? null) !== null) {
                $plan['notes'][] = 'inline_ccitt_fax_imagemask_polarity_review_before_rgb_conversion';
            }
        }
        foreach ($operandBoundaryFilters as $filter) {
            $plan['notes'][] = $filter === self::UNRESOLVED_IMAGE_FILTER_OPERAND
                ? 'inline_unresolved_image_filter_operand_fail_closed'
                : 'inline_malformed_image_filter_operand_fail_closed';
        }
        foreach ($unsupportedFilters as $filter) {
            $plan['notes'][] = 'inline_unsupported_image_filter_review_only';
            $plan['notes'][] = 'inline_' . strtolower($filter) . '_image_filter_review_only';
        }
        if (
            ($plan['inline_image']['soft_mask_present'] ?? false) === true
            && ($plan['inline_image']['soft_mask_uses_current_object_map'] ?? false) === true
            && ($plan['inline_image']['soft_mask_decoded_with_current_filters'] ?? false) === true
        ) {
            $plan['notes'][] = 'inline_image_soft_mask_decoded_from_current_object';
        }

        return $plan;
    }

    /**
     * @param array{valid_for_components?: bool}|null $decode
     */
    private function assertInlineImageDecodeValidForPreview(mixed $decode, string $context): void
    {
        if (!is_array($decode) || ($decode['valid_for_components'] ?? false) === true) {
            return;
        }

        throw new InvalidArgumentException($context . ' Decode array must match the image component count before RGB preview.');
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function assertInlineImageGeometryValidForPreview(array $plan, string $context): void
    {
        if (($plan['inline_image_geometry_operand_invalid'] ?? false) !== true) {
            return;
        }

        throw new InvalidArgumentException($context . ' geometry operands must be bounded positive integers before RGB preview.');
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function assertInlineImageDictionaryValidForPreview(array $plan, string $context): void
    {
        if (($plan['inline_image_dictionary_operand_invalid'] ?? false) !== true) {
            return;
        }

        throw new InvalidArgumentException($context . ' dictionary operands must be well formed before RGB preview.');
    }

    /**
     * @param array<int|string, mixed> $objects
     */
    private function inlineImageGeometryOperandInvalid(string $dictionary, array $objects): bool
    {
        foreach (['Width', 'Height'] as $name) {
            if ($this->extractPdfNameValue($dictionary, $name) !== null) {
                $value = $this->integerNameValue($dictionary, $name, $objects);
                if ($value === null || $value < 1) {
                    return true;
                }
            }
        }

        if ($this->extractPdfNameValue($dictionary, 'BitsPerComponent') !== null) {
            $value = $this->integerNameValue($dictionary, 'BitsPerComponent', $objects);
            if ($value === null || $value < 1 || $value > 30) {
                return true;
            }
        }

        $width = $this->integerNameValue($dictionary, 'Width', $objects);
        $height = $this->integerNameValue($dictionary, 'Height', $objects);
        return is_int($width)
            && is_int($height)
            && $width > 0
            && $height > 0
            && $width > intdiv(PHP_INT_MAX, $height);
    }

    /**
     * Decodes bounded inline `/ImageMask` samples into opacity preview rows.
     *
     * Inline image masks have no indirect stream object, but they still follow
     * the PDF image Decode rules before the Marker RGB preview handoff. This
     * keeps the payload excluded from visible text while making stencil alpha
     * reviewable without pypdfium/PIL raster execution.
     *
     * @param array<int|string, mixed> $objects
     * @return array<string, mixed>
     */
    public function inlineImageMaskPreviewRows(string $inlineImageDictionary, string $payload, array $objects = [], int $maxPixels = 16): array
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('Inline ImageMask preview requires at least one preview pixel.');
        }

        $plan = $this->inlineImageReviewPlan($inlineImageDictionary, $payload, $objects);
        $this->assertInlineImageGeometryValidForPreview($plan, 'Inline ImageMask');
        $this->assertInlineImageDictionaryValidForPreview($plan, 'Inline ImageMask');
        $imageMask = $plan['image_mask'] ?? null;
        if (!is_array($imageMask) || ($imageMask['present'] ?? false) !== true) {
            throw new InvalidArgumentException('Inline ImageMask preview requires /ImageMask true.');
        }
        $this->assertInlineImageDecodeValidForPreview($imageMask['decode'] ?? null, 'Inline ImageMask');

        $width = $imageMask['width'] ?? null;
        $height = $imageMask['height'] ?? null;
        $bitsPerComponent = max(1, (int) ($imageMask['bits_per_component'] ?? 1));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Inline ImageMask preview requires positive Width and Height.');
        }

        $expectedPixelCount = $width * $height;
        $canonical = (string) $plan['inline_image']['canonical_dictionary'];
        $filters = $this->imageFilterNames($canonical, $objects);
        $decodeResult = $this->decodeImageStreamByFilters($canonical, $payload, $objects, true);
        $decoded = $decodeResult['decoded'];
        $unsupportedFilters = $decodeResult['unsupported_filters'];
        $previewOnlyFilters = array_values(array_filter(
            $filters,
            fn (string $filter): bool => $this->isPreviewOnlyStreamFilter($filter)
        ));
        foreach ($previewOnlyFilters as $filter) {
            if (!in_array($filter, $unsupportedFilters, true)) {
                $unsupportedFilters[] = $filter;
            }
        }

        $imageStream = [
            'filters' => $filters,
            'preview_only_filters' => $previewOnlyFilters,
            'unsupported_filters' => array_values($unsupportedFilters),
            'raw_length' => strlen($payload),
            'decoded_length' => $decoded === null ? null : strlen($decoded),
            'decoded_sha256' => $decoded === null ? null : hash('sha256', $decoded),
            'decoded_preview_hex' => $decoded === null ? null : strtoupper(bin2hex(substr($decoded, 0, 16))),
            'decoded_with_current_filters' => $decoded !== null,
            'decode_failed' => (bool) $decodeResult['decode_failed'],
        ];
        $streamNotes = [
            $filters === []
                ? 'inline_image_mask_unfiltered_samples_before_rgb_conversion'
                : 'inline_image_mask_stream_filters_decoded_before_rgb_conversion',
        ];

        if ($decoded === null) {
            if ($previewOnlyFilters === []) {
                throw new InvalidArgumentException('Inline ImageMask filters must be natively decoded before RGB preview.');
            }

            $streamNotes[0] = 'inline_image_mask_preview_only_before_rgb_conversion';

            return [
                'source_color_space' => 'ImageMask',
                'width' => $width,
                'height' => $height,
                'components_per_pixel' => 1,
                'bits_per_component' => $bitsPerComponent,
                'expected_pixel_count' => $expectedPixelCount,
                'preview_pixel_count' => 0,
                'review_only_image_stream' => true,
                'complete_image_sample_data' => false,
                'image_stream' => $imageStream,
                'image_mask' => $imageMask,
                'inline_image' => $plan['inline_image'],
                'inline_image_abbreviations_expanded' => $plan['inline_image_abbreviations_expanded'],
                'inline_image_payload_excluded_from_text' => true,
                'pixels' => [],
                'stream_notes' => $streamNotes,
                'notes' => array_values(array_unique(array_merge(
                    $plan['notes'] ?? [],
                    ['inline_image_mask_preview_only_before_rgb_conversion']
                ))),
                'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
                'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'image_mask_composited_to_rgb_preview'),
            ];
        }

        $samples = $this->packedImagePixelSamples($decoded, 1, $bitsPerComponent, $expectedPixelCount);
        $imageSampleBoundary = $this->imageSampleBoundaryMetadata(
            $samples,
            $expectedPixelCount,
            1,
            $bitsPerComponent,
            $decoded
        );
        if (($imageSampleBoundary['surplus_byte_count'] ?? 0) > 0) {
            $streamNotes[] = 'inline_image_decoded_surplus_samples_review_only';
        }
        if (!$samples['complete']) {
            $streamNotes[] = 'inline_image_mask_sample_data_incomplete';
        }

        $limit = min($maxPixels, count($samples['pixels']));
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $rawSample = $samples['pixels'][$index][0];
            $pixels[] = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'opacity' => $this->imageMaskSampleOpacity($rawSample, $imageMask),
            ];
        }

        return [
            'source_color_space' => 'ImageMask',
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => 1,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => false,
            'complete_image_sample_data' => $samples['complete'],
            'image_sample_boundary' => $imageSampleBoundary,
            'image_stream' => $imageStream,
            'image_mask' => $imageMask,
            'inline_image' => $plan['inline_image'],
            'inline_image_abbreviations_expanded' => $plan['inline_image_abbreviations_expanded'],
            'inline_image_payload_excluded_from_text' => true,
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge(
                $plan['notes'] ?? [],
                ['inline_image_mask_samples_decoded_before_rgb_conversion']
            ))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'image_mask_composited_to_rgb_preview'),
        ];
    }

    /**
     * Decodes bounded inline Indexed image payloads when the filter chain is
     * native, expands palette rows, and attaches ColorKey or soft-mask alpha
     * metadata before the Marker RGB preview handoff.
     *
     * @param array<int|string, mixed> $objects
     * @return array<string, mixed>
     */
    public function inlineIndexedImageStreamPreviewRows(string $inlineImageDictionary, string $payload, array $objects = [], int $maxPixels = 16): array
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('Inline Indexed image preview requires at least one preview pixel.');
        }

        $plan = $this->inlineImageReviewPlan($inlineImageDictionary, $payload, $objects);
        $this->assertInlineImageGeometryValidForPreview($plan, 'Inline Indexed image');
        $this->assertInlineImageDictionaryValidForPreview($plan, 'Inline Indexed image');
        if (($plan['uses_indexed_color_space'] ?? false) !== true) {
            throw new InvalidArgumentException('Inline Indexed image preview requires an Indexed color-space image.');
        }

        $canonical = (string) $plan['inline_image']['canonical_dictionary'];
        $width = $this->integerNameValue($canonical, 'Width', $objects);
        $height = $this->integerNameValue($canonical, 'Height', $objects);
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Inline Indexed image preview requires positive Width and Height.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedInlineImageStreamPreviewBoundary($canonical, $payload, $objects, true);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);

        $softMaskStream = null;
        $softMaskStreamMeta = null;
        $softMaskSamples = null;
        $softMask = $plan['soft_mask'] ?? null;
        $softMaskPresent = is_array($softMask) && ($softMask['present'] ?? false) === true;
        if ($softMaskPresent) {
            if (($plan['soft_mask_applied_before_rgb'] ?? false) !== true) {
                throw new InvalidArgumentException('Inline Indexed image preview requires a grayscale soft-mask image.');
            }
            if (is_int($softMask['width'] ?? null) && $softMask['width'] !== $width) {
                throw new InvalidArgumentException('Inline Indexed soft-mask width must match the image width.');
            }
            if (is_int($softMask['height'] ?? null) && $softMask['height'] !== $height) {
                throw new InvalidArgumentException('Inline Indexed soft-mask height must match the image height.');
            }

            $softMaskStream = $this->decodedSoftMaskStreamPreviewBoundary($canonical, $objects);
            $softMaskStreamMeta = is_array($softMaskStream) ? $this->streamBoundaryPublicMetadata($softMaskStream) : null;
            if (is_array($softMaskStream) && ($softMaskStream['decoded_with_current_filters'] ?? false) === true && is_string($softMaskStream['decoded_bytes'] ?? null)) {
                $softMaskSamples = $this->packedImagePixelSamples(
                    $softMaskStream['decoded_bytes'],
                    1,
                    max(1, (int) ($softMask['bits_per_component'] ?? 8)),
                    $expectedPixelCount
                );
            }
        }

        $streamNotes = [
            $imageStreamMeta['filters'] === []
                ? 'inline_indexed_image_stream_unfiltered_samples_before_rgb_conversion'
                : 'inline_indexed_image_stream_filters_decoded_before_rgb_conversion',
        ];
        if (!$imageStreamMeta['decoded_with_current_filters'] && $imageStreamMeta['preview_only_filters'] !== []) {
            $streamNotes[0] = 'inline_indexed_image_stream_preview_only_before_rgb_conversion';
        }
        if ($softMaskStreamMeta !== null) {
            $streamNotes[] = $softMaskStreamMeta['filters'] === []
                ? 'soft_mask_stream_unfiltered_samples_before_rgb_conversion'
                : 'soft_mask_stream_filters_decoded_before_rgb_conversion';
        }

        if (($imageStream['decoded_with_current_filters'] ?? false) !== true || !is_string($imageStream['decoded_bytes'] ?? null)) {
            if ($imageStreamMeta['preview_only_filters'] === []) {
                throw new InvalidArgumentException('Inline Indexed image filters must be natively decoded before sample preview.');
            }

            $indexedAlternate = $this->indexedAlternateColorSpacePreviewMetadata($plan);

            return [
                'source_color_space' => (string) $plan['source_color_space'],
                'width' => $width,
                'height' => $height,
                'components_per_pixel' => 1,
                'bits_per_component' => $bitsPerComponent,
                'expected_pixel_count' => $expectedPixelCount,
                'preview_pixel_count' => 0,
                'review_only_image_stream' => true,
                'complete_image_sample_data' => false,
                'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
                'image_stream' => $imageStreamMeta,
                'soft_mask_stream' => $softMaskStreamMeta,
                'indexed_color_space' => $plan['indexed_color_space'],
                'indexed_alternate_color_space' => $indexedAlternate,
                'image_decode' => $plan['image_decode'],
                'color_key_mask' => $plan['color_key_mask'],
                'inline_image' => $plan['inline_image'],
                'inline_image_abbreviations_expanded' => $plan['inline_image_abbreviations_expanded'],
                'inline_image_payload_excluded_from_text' => true,
                'pixels' => [],
                'stream_notes' => $streamNotes,
                'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
                'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
                'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
            ];
        }

        $this->assertInlineImageDecodeValidForPreview($plan['image_decode'] ?? null, 'Inline Indexed image');

        $imageSamples = $this->packedImagePixelSamples(
            $imageStream['decoded_bytes'],
            1,
            $bitsPerComponent,
            $expectedPixelCount
        );
        if (!$imageSamples['complete']) {
            throw new InvalidArgumentException('Inline Indexed image stream does not contain complete image sample data.');
        }
        $imageSampleBoundary = $this->imageSampleBoundaryMetadata(
            $imageSamples,
            $expectedPixelCount,
            1,
            $bitsPerComponent,
            $imageStream['decoded_bytes']
        );
        if (($imageSampleBoundary['surplus_byte_count'] ?? 0) > 0) {
            $streamNotes[] = 'inline_image_decoded_surplus_samples_review_only';
        }
        if ($softMaskPresent && $softMaskSamples === null) {
            throw new InvalidArgumentException('Inline Indexed image soft-mask stream filters must be natively decoded before RGB preview.');
        }
        if (is_array($softMaskSamples) && !$softMaskSamples['complete']) {
            throw new InvalidArgumentException('Inline Indexed image soft-mask stream does not contain complete alpha sample data.');
        }

        $limit = min($maxPixels, $expectedPixelCount);
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $rawSample = $imageSamples['pixels'][$index][0];
            $softMaskSample = is_array($softMaskSamples) ? $softMaskSamples['pixels'][$index][0] : null;
            $preview = $this->indexedSamplePreview($rawSample, $plan, $softMaskSample);
            $colorKeyPreview = (($plan['color_key_mask_applied_before_rgb'] ?? false) === true)
                ? $this->indexedColorKeyMaskSamplePreview($rawSample, $plan)
                : null;
            $pixel = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'decoded_index' => $preview['decoded_index'],
                'palette_index' => $preview['palette_index'],
                'clamped_to_hival' => $preview['clamped_to_hival'],
                'base_components' => $preview['base_components'],
                'soft_mask_sample' => $softMaskSample,
                'soft_mask_alpha' => $preview['soft_mask_alpha'],
                'soft_mask_alpha_before_transfer' => $preview['soft_mask_alpha_before_transfer'],
                'soft_mask_transfer_applied' => $preview['soft_mask_transfer_applied'],
            ];
            if (is_array($colorKeyPreview)) {
                $pixel['matches_color_key'] = $colorKeyPreview['matches_color_key'];
                $pixel['color_key_alpha'] = $colorKeyPreview['alpha'];
                $pixel['color_key_mask_ranges'] = $colorKeyPreview['mask_ranges'];
                $pixel['decode_applied_after_color_key'] = $colorKeyPreview['decode_applied_after_color_key'];
                $pixel['palette_transfer_applied_after_color_key'] = $colorKeyPreview['palette_transfer_applied_after_color_key'];
            }
            if ($this->indexedPlanUsesAlternateColorSpace($plan)) {
                $alternatePreview = $this->indexedAlternateColorantSamplePreview($rawSample, $plan, $softMaskSample);
                $pixel['colorant_tints'] = $alternatePreview['colorant_tints'];
                $pixel['tint_values'] = $alternatePreview['tint_values'];
            }

            $pixels[] = $pixel;
        }

        $indexedAlternate = $this->indexedAlternateColorSpacePreviewMetadata($plan);

        return [
            'source_color_space' => (string) $plan['source_color_space'],
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => 1,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => false,
            'complete_image_sample_data' => $imageSamples['complete'],
            'image_sample_boundary' => $imageSampleBoundary,
            'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
            'image_stream' => $imageStreamMeta,
            'soft_mask_stream' => $softMaskStreamMeta,
            'indexed_color_space' => $plan['indexed_color_space'],
            'indexed_alternate_color_space' => $indexedAlternate,
            'image_decode' => $plan['image_decode'],
            'color_key_mask' => $plan['color_key_mask'],
            'inline_image' => $plan['inline_image'],
            'inline_image_abbreviations_expanded' => $plan['inline_image_abbreviations_expanded'],
            'inline_image_payload_excluded_from_text' => true,
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
        ];
    }

    /**
     * Maps supplied decoded JPEG2000 `/ImageMask true` samples through the PDF
     * stencil Decode array without claiming native JPX raster decoding.
     *
     * PDFium handles JPX raster decoding upstream before Marker converts crops
     * to RGB. This helper keeps the same handoff boundary: the JPX stream stays
     * preview-only here, while already-decoded one-bit JPEG2000 mask samples can
     * be reviewed for opacity before a future RGB compositor runs.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    public function jpeg2000ImageMaskPreviewRows(string $imageObject, string $decodedMaskSamples, array $objects = [], int $maxPixels = 16): array
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('JPEG2000 ImageMask preview requires at least one preview pixel.');
        }

        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        if (!in_array('JPXDecode', $plan['image_filters'], true)) {
            throw new InvalidArgumentException('JPEG2000 ImageMask preview requires a JPXDecode image stream.');
        }

        $imageMask = $plan['image_mask'] ?? null;
        if (!is_array($imageMask) || ($imageMask['present'] ?? false) !== true) {
            throw new InvalidArgumentException('JPEG2000 ImageMask preview requires /ImageMask true.');
        }

        $width = $imageMask['width'] ?? null;
        $height = $imageMask['height'] ?? null;
        $bitsPerComponent = max(1, (int) ($imageMask['bits_per_component'] ?? 1));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('JPEG2000 ImageMask preview requires positive Width and Height.');
        }
        if ($bitsPerComponent !== 1) {
            throw new InvalidArgumentException('JPEG2000 ImageMask preview requires one-bit ImageMask samples.');
        }
        if (($imageMask['decode']['valid_for_components'] ?? false) !== true) {
            throw new InvalidArgumentException('JPEG2000 ImageMask Decode array must contain one valid component range.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedImageStreamPreviewBoundary($dictionary, $imageObject, $objects);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);
        $samples = $this->packedImagePixelSamples($decodedMaskSamples, 1, 1, $expectedPixelCount);

        $limit = min($maxPixels, count($samples['pixels']));
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $rawSample = $samples['pixels'][$index][0];
            $pixels[] = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'opacity' => $this->imageMaskSampleOpacity($rawSample, $imageMask),
            ];
        }

        $streamNotes = [
            'jpeg2000_image_stream_review_only_before_rgb_conversion',
            'jpeg2000_image_mask_supplied_samples_decoded_before_rgb_conversion',
        ];
        if (!$samples['complete']) {
            $streamNotes[] = 'jpeg2000_image_mask_sample_data_incomplete';
        }

        return [
            'source_color_space' => 'ImageMask',
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => 1,
            'bits_per_component' => 1,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => true,
            'native_jpeg2000_decode' => false,
            'uses_supplied_jpeg2000_mask_samples' => true,
            'complete_mask_sample_data' => $samples['complete'],
            'image_stream' => $imageStreamMeta,
            'image_filter_boundary' => $plan['image_filter_boundary'],
            'image_mask' => $imageMask,
            'jpx_soft_mask_in_data' => $plan['jpx_soft_mask_in_data'],
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge(
                $plan['notes'] ?? [],
                [
                    'jpeg2000_image_mask_decode_applied_before_rgb_conversion',
                    'jpeg2000_image_mask_supplied_samples_previewed_without_raster_decode',
                ],
                $streamNotes
            ))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'image_mask_composited_to_rgb_preview'),
        ];
    }

    /**
     * Maps supplied decoded JPEG2000 color samples through PDF image Decode
     * and a current grayscale SMask before the Marker RGB output handoff.
     *
     * The JPX codestream remains review-only because this port has no native
     * JPEG2000 raster backend. The supplied samples model the bounded output of
     * PDFium/a future JPX decoder in the image color space, then this method
     * applies the PDF-side color Decode and alpha composition rules.
     *
     * @param list<list<int|float>> $suppliedSamples
     * @param array<int|string, mixed> $objects
     * @param array<string, mixed> $documentMetadata Pass PdfMetadataExtractor output or its `pdfa` subarray.
     * @return array<string, mixed>
     */
    public function jpeg2000ColorSpaceSoftMaskOutputPreviewRows(
        string $imageObject,
        array $suppliedSamples,
        array $objects = [],
        int $maxPixels = 16,
        array $documentMetadata = []
    ): array {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview requires at least one preview pixel.');
        }

        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        if (!in_array('JPXDecode', $plan['image_filters'], true)) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview requires a JPXDecode image stream.');
        }
        if (is_array($plan['image_mask'] ?? null) && ($plan['image_mask']['present'] ?? false) === true) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview does not accept /ImageMask streams.');
        }

        $sourceColorSpace = (string) ($plan['source_color_space'] ?? '');
        $components = $plan['components'] ?? null;
        if (!in_array($sourceColorSpace, ['DeviceRGB', 'DeviceGray'], true)) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview currently requires DeviceRGB or DeviceGray samples.');
        }
        if (!is_int($components) || !in_array($components, [1, 3], true)) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview requires one or three color components.');
        }

        $softMask = $plan['soft_mask'] ?? null;
        if (!is_array($softMask) || ($softMask['present'] ?? false) !== true) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview requires an external /SMask image.');
        }
        if (($plan['soft_mask_applied_before_rgb'] ?? false) !== true) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview requires a grayscale soft-mask image.');
        }

        $width = $this->integerNameValue($dictionary, 'Width');
        $height = $this->integerNameValue($dictionary, 'Height');
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview requires positive Width and Height.');
        }
        if (is_int($softMask['width'] ?? null) && $softMask['width'] !== $width) {
            throw new InvalidArgumentException('JPEG2000 color-space output soft-mask width must match the image width.');
        }
        if (is_int($softMask['height'] ?? null) && $softMask['height'] !== $height) {
            throw new InvalidArgumentException('JPEG2000 color-space output soft-mask height must match the image height.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedImageStreamPreviewBoundary($dictionary, $imageObject, $objects);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);
        if ($imageStreamMeta['preview_only_filters'] === []) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview requires the image stream to remain review-only.');
        }

        $softMaskStream = $this->decodedSoftMaskStreamPreviewBoundary($dictionary, $objects);
        if ($softMaskStream === null || ($softMaskStream['decoded_with_current_filters'] ?? false) !== true || !is_string($softMaskStream['decoded_bytes'] ?? null)) {
            throw new InvalidArgumentException('JPEG2000 color-space output preview requires a natively decoded current soft-mask stream.');
        }
        $softMaskStreamMeta = $this->streamBoundaryPublicMetadata($softMaskStream);
        $softMaskSamples = $this->packedImagePixelSamples(
            $softMaskStream['decoded_bytes'],
            1,
            max(1, (int) ($softMask['bits_per_component'] ?? 8)),
            $expectedPixelCount
        );

        $completeImageSamples = count($suppliedSamples) >= $expectedPixelCount;
        $limit = min($maxPixels, $expectedPixelCount, count($suppliedSamples), count($softMaskSamples['pixels']));
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $values = array_values($suppliedSamples[$index]);
            if (count($values) !== $components) {
                throw new InvalidArgumentException('JPEG2000 supplied color samples must match the image component count.');
            }
            foreach ($values as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new InvalidArgumentException('JPEG2000 supplied color sample values must be numeric.');
                }
            }

            $rawSample = array_map(static fn (int|float $value): float => (float) $value, $values);
            if ($sourceColorSpace === 'DeviceRGB') {
                $decodedComponents = $this->jpeg2000DeviceRgbOutputComponents($rawSample, $plan, $bitsPerComponent);
            } else {
                $grayPreview = $this->deviceGraySamplePreview($rawSample[0], $plan);
                $decodedComponents = $grayPreview['rgb_components'];
            }

            $softMaskSample = $softMaskSamples['pixels'][$index][0];
            $alphaPreview = $this->softMaskAlphaPreview($softMaskSample, $plan, 'JPEG2000 output');
            $pixels[] = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'decoded_components' => $decodedComponents,
                'soft_mask_sample' => $softMaskSample,
                'soft_mask_alpha' => $alphaPreview['alpha'],
                'soft_mask_alpha_before_transfer' => $alphaPreview['alpha_before_transfer'],
                'soft_mask_transfer_applied' => $alphaPreview['transfer_applied'],
                'output_rgba' => $this->rgbOutputPreviewComponents($decodedComponents, $alphaPreview['alpha']),
            ];
        }

        $streamNotes = [
            'jpeg2000_image_stream_review_only_before_rgb_conversion',
            'jpeg2000_supplied_colorspace_samples_before_output_preview',
            $softMaskStreamMeta['filters'] === []
                ? 'soft_mask_stream_unfiltered_samples_before_output_preview'
                : 'soft_mask_stream_filters_decoded_before_output_preview',
        ];
        if (!$completeImageSamples) {
            $streamNotes[] = 'jpeg2000_supplied_sample_data_incomplete';
        }
        if (!$softMaskSamples['complete']) {
            $streamNotes[] = 'jpeg2000_soft_mask_sample_data_incomplete';
        }

        $pdfa = $this->pdfaOutputIntentReviewMetadata($documentMetadata);
        $colorManagement = $this->imagePdfaColorManagementReview($plan, $pdfa);
        $notes = array_merge(
            $plan['notes'] ?? [],
            [
                'jpeg2000_colorspace_smask_output_rows_currentbase',
                'jpeg2000_supplied_samples_previewed_without_native_raster_decode',
                'jpeg2000_soft_mask_alpha_composed_to_rgb_output',
            ],
            $streamNotes
        );
        if ($pdfa['present']) {
            $notes[] = 'jpeg2000_output_preserves_pdfa_output_intent_context';
            $notes[] = $colorManagement['pdfa_output_intent_applies_to_rgb_preview']
                ? 'jpeg2000_output_uses_pdfa_profile_for_device_color_space'
                : 'jpeg2000_output_preserves_pdfa_profile_as_review_context';
        }

        return [
            'source_color_space' => $sourceColorSpace,
            'color_space_resource_name' => $plan['color_space_resource_name'] ?? null,
            'color_space_resource_value' => $plan['color_space_resource_value'] ?? null,
            'color_space_resource_source' => $plan['color_space_resource_source'] ?? null,
            'color_space_resolved_from_resources' => ($plan['color_space_resolved_from_resources'] ?? false) === true,
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => $components,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => true,
            'native_jpx_raster_decode' => false,
            'uses_supplied_jpx_samples' => true,
            'complete_image_sample_data' => $completeImageSamples,
            'complete_soft_mask_sample_data' => $softMaskSamples['complete'],
            'image_filter_boundary' => $plan['image_filter_boundary'],
            'image_stream' => $imageStreamMeta,
            'soft_mask_stream' => $softMaskStreamMeta,
            'soft_mask' => $softMask,
            'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
            'image_decode' => $plan['image_decode'],
            'pdfa_output_intent' => $pdfa,
            'color_management' => $colorManagement,
            'pdfa_output_intent_applies_before_rgb' => $colorManagement['pdfa_output_intent_applies_to_rgb_preview'],
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique($notes)),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'soft_mask_composited_to_rgb_preview'),
        ];
    }

    /**
     * Maps supplied decoded JPEG2000 inline-image samples through ColorKey
     * transparency and Decode metadata without claiming native JPX raster
     * support.
     *
     * Upstream reaches this boundary through PDFium, then Marker/PIL converts
     * the page crop to RGB. The PHP port keeps the inline JPX payload
     * review-only, but can still expose the output rows a future JPX backend
     * or fixture oracle supplies after decoding the JPEG2000 codestream.
     *
     * @param list<list<int|float>> $suppliedSamples
     * @param array<int|string, mixed> $objects
     * @return array<string, mixed>
     */
    public function inlineJpxColorKeyOutputPreviewRows(
        string $inlineImageDictionary,
        string $payload,
        array $suppliedSamples,
        array $objects = [],
        int $maxPixels = 16
    ): array {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('Inline JPX ColorKey preview requires at least one preview pixel.');
        }

        $plan = $this->inlineImageReviewPlan($inlineImageDictionary, $payload, $objects);
        $this->assertInlineImageGeometryValidForPreview($plan, 'Inline JPX ColorKey preview');
        $this->assertInlineImageDictionaryValidForPreview($plan, 'Inline JPX ColorKey preview');
        if (!in_array('JPXDecode', $plan['image_filters'], true)) {
            throw new InvalidArgumentException('Inline JPX ColorKey preview requires a JPXDecode inline image.');
        }
        if (($plan['source_color_space'] ?? null) !== 'DeviceRGB' || ($plan['components'] ?? null) !== 3) {
            throw new InvalidArgumentException('Inline JPX ColorKey preview currently requires DeviceRGB samples.');
        }
        if (($plan['color_key_mask_applied_before_rgb'] ?? false) !== true) {
            throw new InvalidArgumentException('Inline JPX ColorKey preview requires an unsuppressed valid /Mask array.');
        }

        $canonical = (string) $plan['inline_image']['canonical_dictionary'];
        $width = $this->integerNameValue($canonical, 'Width', $objects);
        $height = $this->integerNameValue($canonical, 'Height', $objects);
        $components = (int) $plan['components'];
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Inline JPX ColorKey preview requires positive Width and Height.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedInlineImageStreamPreviewBoundary($canonical, $payload, $objects, true);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);
        if (($imageStreamMeta['decode_failed'] ?? false) === true) {
            throw new InvalidArgumentException('Inline JPX ColorKey image prefix filters must be complete before supplied sample preview.');
        }
        $this->assertInlineImageDecodeValidForPreview($plan['image_decode'] ?? null, 'Inline JPX ColorKey image');
        $complete = count($suppliedSamples) >= $expectedPixelCount;
        $limit = min($maxPixels, $expectedPixelCount, count($suppliedSamples));
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $sample = array_values($suppliedSamples[$index]);
            if (count($sample) !== $components) {
                throw new InvalidArgumentException('Inline JPX ColorKey supplied samples must match the image component count.');
            }
            foreach ($sample as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new InvalidArgumentException('Inline JPX ColorKey supplied sample values must be numeric.');
                }
            }

            $rawSample = array_map(static fn (int|float $value): float => (float) $value, $sample);
            $colorKeyPreview = $this->colorKeyMaskSamplePreview($rawSample, $plan);
            $decoded = array_values($colorKeyPreview['decoded_components']);
            if (($colorKeyPreview['decode_applied_after_color_key'] ?? false) !== true) {
                $decoded = $this->defaultRgbDecodeComponents($rawSample, $bitsPerComponent);
            }

            $pixels[] = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'matches_color_key' => $colorKeyPreview['matches_color_key'],
                'color_key_alpha' => $colorKeyPreview['alpha'],
                'color_key_mask_ranges' => $colorKeyPreview['mask_ranges'],
                'decoded_components' => $decoded,
                'decode_applied_after_color_key' => $colorKeyPreview['decode_applied_after_color_key'],
                'output_rgba' => $this->rgbOutputPreviewComponents($decoded, (float) $colorKeyPreview['alpha']),
            ];
        }

        $streamNotes = [
            'inline_jpx_image_stream_review_only_before_rgb_conversion',
            'inline_jpx_colorkey_supplied_samples_before_output_preview',
        ];
        if (!$complete) {
            $streamNotes[] = 'inline_jpx_colorkey_supplied_sample_data_incomplete';
        }

        return [
            'source_color_space' => 'DeviceRGB',
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => $components,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => true,
            'native_jpx_raster_decode' => false,
            'uses_supplied_jpx_samples' => true,
            'complete_image_sample_data' => $complete,
            'image_filter_boundary' => $plan['image_filter_boundary'],
            'image_stream' => $imageStreamMeta,
            'image_decode' => $plan['image_decode'],
            'color_key_mask' => $plan['color_key_mask'],
            'inline_image' => $plan['inline_image'],
            'inline_image_abbreviations_expanded' => $plan['inline_image_abbreviations_expanded'],
            'inline_image_payload_excluded_from_text' => true,
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge(
                $plan['notes'] ?? [],
                ['inline_jpx_colorkey_supplied_samples_previewed_without_raster_decode'],
                $streamNotes
            ))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'color_key_mask_composited_to_rgb_preview'),
        ];
    }

    /**
     * Builds bounded RGB/RGBA output-preview rows for inline images whose
     * decoded samples are available natively or supplied by a future raster
     * backend.
     *
     * Upstream Marker renders through PDFium/PIL and converts to RGB. This
     * parser-side boundary keeps inline payloads out of visible text, preserves
     * review-only filters such as JPX/JBIG2, and maps available samples through
     * PDF ColorSpace, /Decode, ColorKey /Mask, and current-object /SMask alpha
     * before WordPress media review.
     *
     * @param array<int|string, mixed> $objects
     * @param list<list<int|float>>|null $suppliedSamples
     * @return array<string, mixed>
     */
    public function inlineImageColorSpaceMaskOutputPreviewRows(
        string $inlineImageDictionary,
        string $payload,
        array $objects = [],
        int $maxPixels = 16,
        ?array $suppliedSamples = null
    ): array {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('Inline image output preview requires at least one preview pixel.');
        }

        $plan = $this->inlineImageReviewPlan($inlineImageDictionary, $payload, $objects);
        $this->assertInlineImageGeometryValidForPreview($plan, 'Inline image output preview');
        $this->assertInlineImageDictionaryValidForPreview($plan, 'Inline image output preview');
        if (($plan['image_mask_applied_before_rgb'] ?? false) === true) {
            throw new InvalidArgumentException('Inline image output preview uses inlineImageMaskPreviewRows for /ImageMask stencil previews.');
        }

        $canonical = (string) $plan['inline_image']['canonical_dictionary'];
        $width = $this->integerNameValue($canonical, 'Width', $objects);
        $height = $this->integerNameValue($canonical, 'Height', $objects);
        $components = $plan['components'] ?? null;
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Inline image output preview requires positive Width and Height.');
        }
        if (!is_int($components) || $components < 1) {
            throw new InvalidArgumentException('Inline image output preview requires a positive component count.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedInlineImageStreamPreviewBoundary($canonical, $payload, $objects, true);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);
        $imageStreamDecoded = ($imageStream['decoded_with_current_filters'] ?? false) === true
            && is_string($imageStream['decoded_bytes'] ?? null);
        if (($imageStreamMeta['decode_failed'] ?? false) === true) {
            throw new InvalidArgumentException('Inline image prefix filters must be complete before output preview.');
        }
        $imageStreamReviewOnly = !$imageStreamDecoded && $this->imageStreamBoundaryIsReviewOnly($imageStreamMeta);
        $usesSuppliedSamples = $suppliedSamples !== null;
        if (!$imageStreamDecoded && !$imageStreamReviewOnly) {
            throw new InvalidArgumentException('Inline image filters must be natively decoded before output preview.');
        }

        $softMask = $plan['soft_mask'] ?? null;
        $softMaskPresent = is_array($softMask) && ($softMask['present'] ?? false) === true;
        $softMaskStream = null;
        $softMaskStreamMeta = null;
        $softMaskSamples = null;
        if ($softMaskPresent) {
            if (($plan['soft_mask_applied_before_rgb'] ?? false) !== true) {
                throw new InvalidArgumentException('Inline image output preview requires a grayscale soft-mask image.');
            }
            if (is_int($softMask['width'] ?? null) && $softMask['width'] !== $width) {
                throw new InvalidArgumentException('Inline image soft-mask width must match the image width.');
            }
            if (is_int($softMask['height'] ?? null) && $softMask['height'] !== $height) {
                throw new InvalidArgumentException('Inline image soft-mask height must match the image height.');
            }

            $softMaskStream = $this->decodedSoftMaskStreamPreviewBoundary($canonical, $objects);
            $softMaskStreamMeta = is_array($softMaskStream) ? $this->streamBoundaryPublicMetadata($softMaskStream) : null;
            if (is_array($softMaskStream) && ($softMaskStream['decoded_with_current_filters'] ?? false) === true && is_string($softMaskStream['decoded_bytes'] ?? null)) {
                $softMaskSamples = $this->packedImagePixelSamples(
                    $softMaskStream['decoded_bytes'],
                    1,
                    max(1, (int) ($softMask['bits_per_component'] ?? 8)),
                    $expectedPixelCount
                );
            }

            if (($imageStreamDecoded || $usesSuppliedSamples) && $softMaskSamples === null) {
                throw new InvalidArgumentException('Inline image soft-mask stream filters must be natively decoded before output preview.');
            }
            if (is_array($softMaskSamples) && !$softMaskSamples['complete']) {
                throw new InvalidArgumentException('Inline image soft-mask stream does not contain complete alpha sample data.');
            }
        }

        $streamNotes = [
            $imageStreamMeta['filters'] === []
                ? 'inline_image_stream_unfiltered_samples_before_output_preview'
                : 'inline_image_stream_filters_decoded_before_output_preview',
        ];
        if ($imageStreamReviewOnly) {
            $streamNotes[0] = 'inline_image_stream_review_only_before_output_preview';
        }
        if ($usesSuppliedSamples) {
            $streamNotes[] = 'inline_image_supplied_samples_before_output_preview';
        }
        if ($softMaskStreamMeta !== null) {
            $streamNotes[] = $softMaskStreamMeta['filters'] === []
                ? 'inline_soft_mask_stream_unfiltered_samples_before_output_preview'
                : 'inline_soft_mask_stream_filters_decoded_before_output_preview';
        }

        if (!$imageStreamDecoded && !$usesSuppliedSamples) {
            return [
                'source_color_space' => (string) ($plan['source_color_space'] ?? 'DeviceRGB'),
                'width' => $width,
                'height' => $height,
                'components_per_pixel' => $components,
                'bits_per_component' => $bitsPerComponent,
                'expected_pixel_count' => $expectedPixelCount,
                'preview_pixel_count' => 0,
                'review_only_image_stream' => true,
                'native_raster_decode' => false,
                'uses_supplied_samples' => false,
                'complete_image_sample_data' => false,
                'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
                'image_filter_boundary' => $plan['image_filter_boundary'],
                'image_filter_details' => $plan['image_filter_details'],
                'image_stream' => $imageStreamMeta,
                'soft_mask_stream' => $softMaskStreamMeta,
                'soft_mask' => $softMask,
                'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
                'image_decode' => $plan['image_decode'],
                'color_key_mask' => $plan['color_key_mask'],
                'inline_image' => $plan['inline_image'],
                'inline_image_abbreviations_expanded' => $plan['inline_image_abbreviations_expanded'],
                'inline_image_payload_excluded_from_text' => true,
                'pixels' => [],
                'stream_notes' => $streamNotes,
                'notes' => $this->inlineImageOutputPreviewNotes($plan, $streamNotes, false, false),
                'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
                'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
            ];
        }

        $this->assertInlineImageDecodeValidForPreview($plan['image_decode'] ?? null, 'Inline image');

        if ($usesSuppliedSamples) {
            $imageSamples = $this->normalizeSuppliedImageSampleRows($suppliedSamples, $components, $expectedPixelCount);
            $imageSampleBoundary = $this->imageSampleBoundaryMetadata(
                $imageSamples,
                $expectedPixelCount,
                $components,
                $bitsPerComponent,
                null
            );
        } else {
            $imageSamples = $this->packedImagePixelSamples(
                (string) $imageStream['decoded_bytes'],
                $components,
                $bitsPerComponent,
                $expectedPixelCount
            );
            if (!$imageSamples['complete']) {
                throw new InvalidArgumentException('Inline image stream does not contain complete image sample data.');
            }
            $imageSampleBoundary = $this->imageSampleBoundaryMetadata(
                $imageSamples,
                $expectedPixelCount,
                $components,
                $bitsPerComponent,
                (string) $imageStream['decoded_bytes']
            );
        }
        if (($imageSampleBoundary['surplus_byte_count'] ?? 0) > 0) {
            $streamNotes[] = $usesSuppliedSamples
                ? 'inline_image_supplied_surplus_samples_review_only'
                : 'inline_image_decoded_surplus_samples_review_only';
        }

        $limit = min($maxPixels, count($imageSamples['pixels']));
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $softMaskSample = is_array($softMaskSamples) ? ($softMaskSamples['pixels'][$index][0] ?? null) : null;
            if ($softMaskPresent && $softMaskSample === null) {
                throw new InvalidArgumentException('Inline image output preview requires matching soft-mask samples.');
            }

            $pixel = $this->inlineImageOutputPixelPreview($imageSamples['pixels'][$index], $plan, $softMaskSample);
            $pixel['pixel_index'] = $index;
            $pixel['x'] = $index % $width;
            $pixel['y'] = intdiv($index, $width);
            $pixels[] = $pixel;
        }

        $colorKeyApplied = ($plan['color_key_mask_applied_before_rgb'] ?? false) === true;

        return [
            'source_color_space' => (string) ($plan['source_color_space'] ?? 'DeviceRGB'),
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => $components,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => $imageStreamReviewOnly,
            'native_raster_decode' => $imageStreamDecoded && !$imageStreamReviewOnly,
            'uses_supplied_samples' => $usesSuppliedSamples,
            'complete_image_sample_data' => $imageSamples['complete'],
            'image_sample_boundary' => $imageSampleBoundary,
            'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
            'image_filter_boundary' => $plan['image_filter_boundary'],
            'image_filter_details' => $plan['image_filter_details'],
            'image_stream' => $imageStreamMeta,
            'soft_mask_stream' => $softMaskStreamMeta,
            'soft_mask' => $softMask,
            'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
            'soft_mask_transfer_function' => $plan['soft_mask_transfer_function'],
            'soft_mask_transfer_function_applied_before_rgb' => $plan['soft_mask_transfer_function_applied_before_rgb'],
            'image_decode' => $plan['image_decode'],
            'color_key_mask' => $plan['color_key_mask'],
            'color_key_mask_suppressed_by_soft_mask' => $plan['color_key_mask_suppressed_by_soft_mask'],
            'indexed_color_space' => $plan['indexed_color_space'],
            'inline_image' => $plan['inline_image'],
            'inline_image_abbreviations_expanded' => $plan['inline_image_abbreviations_expanded'],
            'inline_image_payload_excluded_from_text' => true,
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => $this->inlineImageOutputPreviewNotes($plan, $streamNotes, $usesSuppliedSamples, $colorKeyApplied),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
        ];
    }

    /**
     * @param list<list<int|float>> $suppliedSamples
     * @return array{pixels: list<list<float>>, available_pixel_count: int, available_sample_count: int, complete: bool}
     */
    private function normalizeSuppliedImageSampleRows(array $suppliedSamples, int $components, int $expectedPixelCount): array
    {
        $rows = [];
        foreach (array_values($suppliedSamples) as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Supplied inline image samples must be rows of component values.');
            }

            $values = array_values($row);
            if (count($values) !== $components) {
                throw new InvalidArgumentException('Supplied inline image samples must match the image component count.');
            }

            $sample = [];
            foreach ($values as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new InvalidArgumentException('Supplied inline image sample values must be numeric.');
                }

                $sample[] = (float) $value;
            }
            $rows[] = $sample;
        }

        return [
            'pixels' => array_slice($rows, 0, $expectedPixelCount),
            'available_pixel_count' => count($rows),
            'available_sample_count' => count($rows) * $components,
            'complete' => count($rows) >= $expectedPixelCount,
        ];
    }

    /**
     * @param list<float> $rawSample
     * @param array<string, mixed> $imagePlan
     * @return array<string, mixed>
     */
    private function inlineImageOutputPixelPreview(array $rawSample, array $imagePlan, int|float|null $softMaskSample): array
    {
        $sourceColorSpace = (string) ($imagePlan['source_color_space'] ?? 'DeviceRGB');
        $rawSample = array_map(static fn (float $value): float => (float) $value, array_values($rawSample));
        $alpha = 1.0;
        $alphaSource = 'opaque';
        $softMaskAlpha = null;
        $softMaskAlphaBeforeTransfer = null;
        $softMaskTransferApplied = false;
        $softMaskTransferFunction = null;
        $colorKeyPreview = null;

        if ($softMaskSample !== null) {
            $softMaskPreview = $this->softMaskAlphaPreview($softMaskSample, $imagePlan, 'Inline image output');
            $alpha = $softMaskPreview['alpha'];
            $alphaSource = 'soft_mask';
            $softMaskAlpha = $softMaskPreview['alpha'];
            $softMaskAlphaBeforeTransfer = $softMaskPreview['alpha_before_transfer'];
            $softMaskTransferApplied = $softMaskPreview['transfer_applied'];
            $softMaskTransferFunction = $softMaskPreview['transfer_function'];
        }

        $decodedComponents = [];
        $rgbComponents = [];
        $pixel = [
            'source_color_space' => $sourceColorSpace,
            'raw_sample' => $rawSample,
        ];

        if ($sourceColorSpace === 'Indexed') {
            $rawIndex = $rawSample[0] ?? null;
            if (!is_int($rawIndex) && !is_float($rawIndex)) {
                throw new InvalidArgumentException('Indexed inline image output preview requires one sample component.');
            }

            $indexedPreview = $this->indexedSamplePreview($rawIndex, $imagePlan);
            $decodedComponents = $indexedPreview['base_components'];
            $rgbComponents = $this->rgbComponentsFromDecodedComponents($decodedComponents, 'Indexed inline image output preview');
            $pixel += [
                'decoded_index' => $indexedPreview['decoded_index'],
                'palette_index' => $indexedPreview['palette_index'],
                'clamped_to_hival' => $indexedPreview['clamped_to_hival'],
                'base_components' => $indexedPreview['base_components'],
            ];

            if (($imagePlan['color_key_mask_applied_before_rgb'] ?? false) === true && $softMaskSample === null) {
                $colorKeyPreview = $this->indexedColorKeyMaskSamplePreview($rawIndex, $imagePlan);
                $alpha = $colorKeyPreview['alpha'];
                $alphaSource = 'color_key_mask';
            }
        } elseif ($sourceColorSpace === 'DeviceGray') {
            $graySample = $rawSample[0] ?? null;
            if (!is_int($graySample) && !is_float($graySample)) {
                throw new InvalidArgumentException('DeviceGray inline image output preview requires one sample component.');
            }

            $grayPreview = $this->deviceGraySamplePreview($graySample, $imagePlan);
            $decodedComponents = [$grayPreview['decoded_gray']];
            $rgbComponents = $grayPreview['rgb_components'];
            $pixel['decoded_gray'] = $grayPreview['decoded_gray'];

            if (($imagePlan['color_key_mask_applied_before_rgb'] ?? false) === true && $softMaskSample === null) {
                $colorKeyPreview = $this->colorKeyMaskSamplePreview([$graySample], $imagePlan);
                $alpha = $colorKeyPreview['alpha'];
                $alphaSource = 'color_key_mask';
                if (($colorKeyPreview['decode_applied_after_color_key'] ?? false) === true) {
                    $decodedComponents = $colorKeyPreview['decoded_components'];
                    $rgbComponents = $this->rgbComponentsFromDecodedComponents($decodedComponents, 'DeviceGray inline image output preview');
                }
            }
        } elseif ($sourceColorSpace === 'DeviceRGB') {
            $decodedComponents = $this->decodedRgbComponentsForSample($rawSample, $imagePlan);
            $rgbComponents = $decodedComponents;

            if (($imagePlan['color_key_mask_applied_before_rgb'] ?? false) === true && $softMaskSample === null) {
                $colorKeyPreview = $this->colorKeyMaskSamplePreview($rawSample, $imagePlan);
                $alpha = $colorKeyPreview['alpha'];
                $alphaSource = 'color_key_mask';
                if (($colorKeyPreview['decode_applied_after_color_key'] ?? false) === true) {
                    $decodedComponents = $colorKeyPreview['decoded_components'];
                    $rgbComponents = $this->rgbComponentsFromDecodedComponents($decodedComponents, 'DeviceRGB inline image output preview');
                }
            }
        } else {
            throw new InvalidArgumentException('Inline image output preview currently supports Indexed, DeviceGray, and DeviceRGB color spaces.');
        }

        $pixel += [
            'decoded_components' => $decodedComponents,
            'rgb_components' => $rgbComponents,
            'soft_mask_sample' => $softMaskSample === null ? null : (float) $softMaskSample,
            'soft_mask_alpha' => $softMaskAlpha,
            'soft_mask_alpha_before_transfer' => $softMaskAlphaBeforeTransfer,
            'soft_mask_transfer_applied' => $softMaskTransferApplied,
            'soft_mask_transfer_function' => $softMaskTransferFunction,
            'alpha' => $alpha,
            'alpha_source' => $alphaSource,
            'output_rgba' => $this->rgbOutputPreviewComponents($rgbComponents, $alpha),
        ];

        if (is_array($colorKeyPreview)) {
            $pixel['matches_color_key'] = $colorKeyPreview['matches_color_key'];
            $pixel['color_key_alpha'] = $colorKeyPreview['alpha'];
            $pixel['color_key_mask_ranges'] = $colorKeyPreview['mask_ranges'];
            $pixel['decode_applied_after_color_key'] = $colorKeyPreview['decode_applied_after_color_key'];
        }

        return $pixel;
    }

    /**
     * @param list<float> $sample
     * @param array<string, mixed> $imagePlan
     * @return list<float>
     */
    private function decodedRgbComponentsForSample(array $sample, array $imagePlan): array
    {
        if (count($sample) !== 3) {
            throw new InvalidArgumentException('DeviceRGB inline image output preview requires exactly three components.');
        }

        $decode = $imagePlan['image_decode'] ?? null;
        if (is_array($decode)) {
            if (($decode['valid_for_components'] ?? false) !== true) {
                throw new InvalidArgumentException('DeviceRGB Decode array must match three components.');
            }

            return $this->imageSampleDecodeValues(
                $sample,
                $decode,
                max(1, (int) ($imagePlan['bits_per_component'] ?? 8))
            );
        }

        return $this->defaultRgbDecodeComponents($sample, max(1, (int) ($imagePlan['bits_per_component'] ?? 8)));
    }

    /**
     * @param list<float> $components
     * @return list<float>
     */
    private function rgbComponentsFromDecodedComponents(array $components, string $context): array
    {
        $components = array_values($components);
        if (count($components) === 1) {
            $value = max(0.0, min(1.0, (float) $components[0]));

            return [$value, $value, $value];
        }
        if (count($components) === 3) {
            return array_map(static fn (float $value): float => max(0.0, min(1.0, (float) $value)), $components);
        }

        throw new InvalidArgumentException($context . ' requires one or three RGB-compatible decoded components.');
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<string> $streamNotes
     * @return list<string>
     */
    private function inlineImageOutputPreviewNotes(array $plan, array $streamNotes, bool $usesSuppliedSamples, bool $colorKeyApplied): array
    {
        $notes = array_merge(
            is_array($plan['notes'] ?? null) ? $plan['notes'] : [],
            $streamNotes,
            [
                'inline_image_colorspace_mask_output_preview_currentbase',
                'inline_image_output_preview_targets_rgb',
                'inline_image_output_preview_keeps_payload_out_of_visible_text',
            ]
        );

        $source = (string) ($plan['source_color_space'] ?? 'DeviceRGB');
        if ($source === 'Indexed') {
            $notes[] = 'inline_image_output_preview_expands_indexed_palette_to_rgb';
        } elseif ($source === 'DeviceGray') {
            $notes[] = 'inline_image_output_preview_expands_devicegray_to_rgb';
        } elseif ($source === 'DeviceRGB') {
            $notes[] = 'inline_image_output_preview_uses_devicergb_samples';
        }
        if (($plan['soft_mask_applied_before_rgb'] ?? false) === true) {
            $notes[] = 'inline_image_output_preview_applies_soft_mask_alpha';
        }
        if ($colorKeyApplied) {
            $notes[] = 'inline_image_output_preview_applies_color_key_alpha';
        }
        if (($plan['color_key_mask_suppressed_by_soft_mask'] ?? false) === true) {
            $notes[] = 'inline_image_output_preview_soft_mask_suppresses_color_key';
        }
        if ($usesSuppliedSamples) {
            $notes[] = 'inline_image_output_preview_uses_supplied_samples_without_raster_decode';
        }
        if (($plan['inline_image_review_only'] ?? false) === true) {
            $notes[] = 'inline_image_output_preview_preserves_review_only_filter_boundary';
        }

        return array_values(array_unique($notes));
    }

    /**
     * Expands an Indexed color-space sample index into normalized base color
     * components. This mirrors the PDF parser side of the RGB preview boundary
     * without rasterizing pixels.
     *
     * @param array{base_components?: int|null, high_value?: int|null, lookup_length_matches?: bool, lookup_bytes?: list<int>} $indexedPlan
     * @return list<float>
     */
    public function indexedSampleToBaseComponents(int|float $sample, array $indexedPlan): array
    {
        $baseComponents = $indexedPlan['base_components'] ?? null;
        $highValue = $indexedPlan['high_value'] ?? null;
        $lookupBytes = $indexedPlan['lookup_bytes'] ?? null;

        if (!is_int($baseComponents) || $baseComponents <= 0 || !is_int($highValue) || !is_array($lookupBytes)) {
            throw new InvalidArgumentException('Indexed color-space plan must include base components, high value, and lookup bytes.');
        }
        if (($indexedPlan['lookup_length_matches'] ?? false) !== true) {
            throw new InvalidArgumentException('Indexed color-space lookup length does not match the declared high value and base components.');
        }

        $index = (int) round((float) $sample);
        if ($index < 0 || $index > $highValue) {
            throw new InvalidArgumentException('Indexed color-space sample is outside the declared high-value range.');
        }

        $offset = $index * $baseComponents;
        $components = [];
        for ($component = 0; $component < $baseComponents; $component++) {
            $byte = $lookupBytes[$offset + $component] ?? null;
            if (!is_int($byte)) {
                throw new InvalidArgumentException('Indexed color-space lookup table is incomplete.');
            }

            $components[] = max(0.0, min(1.0, $byte / 255));
        }

        return $components;
    }

    /**
     * Applies an Indexed image sample's Decode array, clips the resulting
     * palette index to the declared high value, expands the lookup table, and
     * optionally maps the matching soft-mask sample into alpha preview space.
     *
     * @param array{
     *     bits_per_component?: int,
     *     indexed_color_space?: array{base_components?: int|null, high_value?: int|null, lookup_length_matches?: bool, lookup_bytes?: list<int>},
     *     image_decode?: array{ranges: list<array{min: float, max: float}>, valid_for_components?: bool}|null,
     *     soft_mask?: array{present?: bool, decode?: array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool}, bits_per_component?: int|null}|null,
     *     output_color_mode?: string
     * } $imagePlan
     * @return array{raw_sample: float, decoded_index: float, palette_index: int, clamped_to_hival: bool, base_components: list<float>, soft_mask_alpha: float|null, output_color_mode: string}
     */
    public function indexedSamplePreview(int|float $sample, array $imagePlan, int|float|null $softMaskSample = null): array
    {
        $indexedPlan = $imagePlan['indexed_color_space'] ?? null;
        if (!is_array($indexedPlan)) {
            throw new InvalidArgumentException('Indexed image preview requires an Indexed color-space plan.');
        }

        $highValue = $indexedPlan['high_value'] ?? null;
        if (!is_int($highValue) || $highValue < 0) {
            throw new InvalidArgumentException('Indexed image preview requires a non-negative high value.');
        }

        $decodedIndex = (float) $sample;
        $decode = $imagePlan['image_decode'] ?? null;
        if (is_array($decode) && ($decode['valid_for_components'] ?? false) === true) {
            $decoded = $this->imageSampleDecodeValues(
                [$sample],
                $decode,
                max(1, (int) ($imagePlan['bits_per_component'] ?? 8))
            );
            $decodedIndex = $decoded[0];
        }

        $roundedIndex = (int) round($decodedIndex);
        $paletteIndex = max(0, min($highValue, $roundedIndex));
        $softMaskAlpha = null;
        $softMaskAlphaBeforeTransfer = null;
        $softMaskTransferApplied = false;
        $softMaskTransferFunction = null;
        if ($softMaskSample !== null) {
            $softMaskAlphaPreview = $this->softMaskAlphaPreview($softMaskSample, $imagePlan, 'Indexed');
            $softMaskAlpha = $softMaskAlphaPreview['alpha'];
            $softMaskAlphaBeforeTransfer = $softMaskAlphaPreview['alpha_before_transfer'];
            $softMaskTransferApplied = $softMaskAlphaPreview['transfer_applied'];
            $softMaskTransferFunction = $softMaskAlphaPreview['transfer_function'];
        }

        return [
            'raw_sample' => (float) $sample,
            'decoded_index' => $decodedIndex,
            'palette_index' => $paletteIndex,
            'clamped_to_hival' => $paletteIndex !== $roundedIndex,
            'base_components' => $this->indexedSampleToBaseComponents($paletteIndex, $indexedPlan),
            'soft_mask_alpha' => $softMaskAlpha,
            'soft_mask_alpha_before_transfer' => $softMaskAlphaBeforeTransfer,
            'soft_mask_transfer_applied' => $softMaskTransferApplied,
            'soft_mask_transfer_function' => $softMaskTransferFunction,
            'output_color_mode' => (string) ($imagePlan['output_color_mode'] ?? 'RGB'),
        ];
    }

    /**
     * Maps an Indexed palette entry whose base color space is Separation or
     * DeviceN into named tint values, with optional soft-mask alpha.
     *
     * @param array<string, mixed> $imagePlan
     * @return array{source_color_space: string, base_color_space: string|null, raw_sample: float, decoded_index: float, palette_index: int, clamped_to_hival: bool, colorant_tints: array<string, float>, tint_values: list<float>, alternate_color_space: string|null, alternate_components: int|null, alternate_uses_icc_profile: bool, icc_profile: array<string, mixed>|null, tint_transform_object: int|null, tint_transform_function_type: int|null, tint_transform_preview_mode: string, soft_mask_alpha: float|null, soft_mask_alpha_before_transfer: float|null, soft_mask_transfer_applied: bool, soft_mask_transfer_function: array<string, mixed>|null, output_color_mode: string}
     */
    public function indexedAlternateColorantSamplePreview(int|float $sample, array $imagePlan, int|float|null $softMaskSample = null): array
    {
        $indexedPlan = $imagePlan['indexed_color_space'] ?? null;
        if (!is_array($indexedPlan)) {
            throw new InvalidArgumentException('Indexed alternate colorant preview requires an Indexed image plan.');
        }

        $alternate = $indexedPlan['base_alternate_color_space'] ?? null;
        if (($indexedPlan['base_uses_alternate_color_space'] ?? false) !== true || !is_array($alternate)) {
            throw new InvalidArgumentException('Indexed alternate colorant preview requires a Separation or DeviceN base color space.');
        }

        $indexedPreview = $this->indexedSamplePreview($sample, $imagePlan, $softMaskSample);
        $tints = array_values($indexedPreview['base_components']);
        $colorantNames = array_values(array_filter(
            $alternate['colorant_names'] ?? [],
            static fn (mixed $name): bool => is_string($name) && $name !== ''
        ));
        $expectedComponents = count($colorantNames);
        if ($expectedComponents === 0 && isset($indexedPlan['base_components']) && is_int($indexedPlan['base_components'])) {
            $expectedComponents = $indexedPlan['base_components'];
        }
        if ($expectedComponents < 1 || count($tints) !== $expectedComponents) {
            throw new InvalidArgumentException('Indexed palette entry must match the base colorant count.');
        }

        $namedTints = [];
        for ($index = 0; $index < $expectedComponents; $index++) {
            $name = $colorantNames[$index] ?? 'colorant_' . ($index + 1);
            $namedTints[$name] = $tints[$index];
        }

        $functionType = $alternate['tint_transform_function_type'] ?? null;

        return [
            'source_color_space' => (string) ($imagePlan['source_color_space'] ?? 'Indexed'),
            'base_color_space' => $indexedPlan['base_color_space'] ?? null,
            'raw_sample' => $indexedPreview['raw_sample'],
            'decoded_index' => $indexedPreview['decoded_index'],
            'palette_index' => $indexedPreview['palette_index'],
            'clamped_to_hival' => $indexedPreview['clamped_to_hival'],
            'colorant_tints' => $namedTints,
            'tint_values' => $tints,
            'alternate_color_space' => $alternate['alternate_color_space'] ?? null,
            'alternate_components' => $alternate['alternate_components'] ?? null,
            'alternate_uses_icc_profile' => ($alternate['alternate_uses_icc_profile'] ?? false) === true,
            'icc_profile' => $indexedPlan['base_icc_profile'] ?? null,
            'tint_transform_object' => $alternate['tint_transform_object'] ?? null,
            'tint_transform_function_type' => $functionType,
            'tint_transform_preview_mode' => $functionType === null ? 'none' : 'review_only',
            'soft_mask_alpha' => $indexedPreview['soft_mask_alpha'],
            'soft_mask_alpha_before_transfer' => $indexedPreview['soft_mask_alpha_before_transfer'],
            'soft_mask_transfer_applied' => $indexedPreview['soft_mask_transfer_applied'],
            'soft_mask_transfer_function' => $indexedPreview['soft_mask_transfer_function'],
            'output_color_mode' => $indexedPreview['output_color_mode'],
        ];
    }

    /**
     * Decodes bounded Indexed image stream rows when the filter chain is native,
     * while keeping JPX/JBIG2/CCITT raster streams as review-only boundaries.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    public function indexedImageStreamPreviewRows(string $imageObject, array $objects = [], int $maxPixels = 16): array
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('Indexed image stream preview requires at least one preview pixel.');
        }

        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        if (($plan['uses_indexed_color_space'] ?? false) !== true) {
            throw new InvalidArgumentException('Indexed image stream preview requires an Indexed color-space image.');
        }

        $width = $this->integerNameValue($dictionary, 'Width');
        $height = $this->integerNameValue($dictionary, 'Height');
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Indexed image stream preview requires positive image Width and Height.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedImageStreamPreviewBoundary($dictionary, $imageObject, $objects);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);

        $softMaskStream = null;
        $softMaskStreamMeta = null;
        $softMaskSamples = null;
        $softMask = $plan['soft_mask'] ?? null;
        $softMaskPresent = is_array($softMask) && ($softMask['present'] ?? false) === true;
        if ($softMaskPresent) {
            if (($plan['soft_mask_applied_before_rgb'] ?? false) !== true) {
                throw new InvalidArgumentException('Indexed image stream preview requires a grayscale soft-mask image.');
            }
            if (is_int($softMask['width'] ?? null) && $softMask['width'] !== $width) {
                throw new InvalidArgumentException('Indexed soft-mask width must match the image width.');
            }
            if (is_int($softMask['height'] ?? null) && $softMask['height'] !== $height) {
                throw new InvalidArgumentException('Indexed soft-mask height must match the image height.');
            }

            $softMaskStream = $this->decodedSoftMaskStreamPreviewBoundary($dictionary, $objects);
            $softMaskStreamMeta = is_array($softMaskStream) ? $this->streamBoundaryPublicMetadata($softMaskStream) : null;
            if (is_array($softMaskStream) && ($softMaskStream['decoded_with_current_filters'] ?? false) === true && is_string($softMaskStream['decoded_bytes'] ?? null)) {
                $softMaskSamples = $this->packedImagePixelSamples(
                    $softMaskStream['decoded_bytes'],
                    1,
                    max(1, (int) ($softMask['bits_per_component'] ?? 8)),
                    $expectedPixelCount
                );
            }
        }

        $streamNotes = [
            $imageStreamMeta['filters'] === []
                ? 'indexed_image_stream_unfiltered_samples_before_rgb_conversion'
                : 'indexed_image_stream_filters_decoded_before_rgb_conversion',
        ];
        if (!$imageStreamMeta['decoded_with_current_filters'] && $imageStreamMeta['preview_only_filters'] !== []) {
            $streamNotes[0] = 'indexed_image_stream_preview_only_before_rgb_conversion';
        }
        if ($softMaskStreamMeta !== null) {
            $streamNotes[] = $softMaskStreamMeta['filters'] === []
                ? 'soft_mask_stream_unfiltered_samples_before_rgb_conversion'
                : 'soft_mask_stream_filters_decoded_before_rgb_conversion';
        }

        if (($imageStream['decoded_with_current_filters'] ?? false) !== true || !is_string($imageStream['decoded_bytes'] ?? null)) {
            if ($imageStreamMeta['preview_only_filters'] === []) {
                throw new InvalidArgumentException('Indexed image stream filters must be natively decoded before sample preview.');
            }

            $indexedAlternate = $this->indexedAlternateColorSpacePreviewMetadata($plan);

            return [
                'source_color_space' => (string) $plan['source_color_space'],
                'width' => $width,
                'height' => $height,
                'components_per_pixel' => 1,
                'bits_per_component' => $bitsPerComponent,
                'expected_pixel_count' => $expectedPixelCount,
                'preview_pixel_count' => 0,
                'review_only_image_stream' => true,
                'complete_image_sample_data' => false,
                'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
                'image_stream' => $imageStreamMeta,
                'soft_mask_stream' => $softMaskStreamMeta,
                'indexed_color_space' => $plan['indexed_color_space'],
                'indexed_alternate_color_space' => $indexedAlternate,
                'image_decode' => $plan['image_decode'],
                'color_key_mask' => $plan['color_key_mask'],
                'pixels' => [],
                'stream_notes' => $streamNotes,
                'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
                'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
                'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
            ];
        }

        $imageSamples = $this->packedImagePixelSamples(
            $imageStream['decoded_bytes'],
            1,
            $bitsPerComponent,
            $expectedPixelCount
        );
        if (!$imageSamples['complete']) {
            throw new InvalidArgumentException('Indexed image stream does not contain complete image sample data.');
        }
        if ($softMaskPresent && $softMaskSamples === null) {
            throw new InvalidArgumentException('Indexed image soft-mask stream filters must be natively decoded before RGB preview.');
        }
        if (is_array($softMaskSamples) && !$softMaskSamples['complete']) {
            throw new InvalidArgumentException('Indexed image soft-mask stream does not contain complete alpha sample data.');
        }

        $limit = min($maxPixels, $expectedPixelCount);
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $rawSample = $imageSamples['pixels'][$index][0];
            $softMaskSample = is_array($softMaskSamples) ? $softMaskSamples['pixels'][$index][0] : null;
            $preview = $this->indexedSamplePreview($rawSample, $plan, $softMaskSample);
            $colorKeyPreview = (($plan['color_key_mask_applied_before_rgb'] ?? false) === true)
                ? $this->indexedColorKeyMaskSamplePreview($rawSample, $plan)
                : null;
            $pixel = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'decoded_index' => $preview['decoded_index'],
                'palette_index' => $preview['palette_index'],
                'clamped_to_hival' => $preview['clamped_to_hival'],
                'base_components' => $preview['base_components'],
                'soft_mask_sample' => $softMaskSample,
                'soft_mask_alpha' => $preview['soft_mask_alpha'],
            ];
            if (is_array($colorKeyPreview)) {
                $pixel['matches_color_key'] = $colorKeyPreview['matches_color_key'];
                $pixel['color_key_alpha'] = $colorKeyPreview['alpha'];
                $pixel['color_key_mask_ranges'] = $colorKeyPreview['mask_ranges'];
                $pixel['decode_applied_after_color_key'] = $colorKeyPreview['decode_applied_after_color_key'];
                $pixel['palette_transfer_applied_after_color_key'] = $colorKeyPreview['palette_transfer_applied_after_color_key'];
            }
            if ($this->indexedPlanUsesAlternateColorSpace($plan)) {
                $alternatePreview = $this->indexedAlternateColorantSamplePreview($rawSample, $plan, $softMaskSample);
                $pixel['colorant_tints'] = $alternatePreview['colorant_tints'];
                $pixel['tint_values'] = $alternatePreview['tint_values'];
                $pixel['soft_mask_alpha_before_transfer'] = $alternatePreview['soft_mask_alpha_before_transfer'];
                $pixel['soft_mask_transfer_applied'] = $alternatePreview['soft_mask_transfer_applied'];
            }

            $pixels[] = $pixel;
        }

        $indexedAlternate = $this->indexedAlternateColorSpacePreviewMetadata($plan);

        return [
            'source_color_space' => (string) $plan['source_color_space'],
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => 1,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => false,
            'complete_image_sample_data' => $imageSamples['complete'],
            'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
            'image_stream' => $imageStreamMeta,
            'soft_mask_stream' => $softMaskStreamMeta,
            'indexed_color_space' => $plan['indexed_color_space'],
            'indexed_alternate_color_space' => $indexedAlternate,
            'image_decode' => $plan['image_decode'],
            'color_key_mask' => $plan['color_key_mask'],
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
        ];
    }

    /**
     * @param array<string, mixed> $imagePlan
     * @return array<string, mixed>|null
     */
    private function indexedAlternateColorSpacePreviewMetadata(array $imagePlan): ?array
    {
        if (!$this->indexedPlanUsesAlternateColorSpace($imagePlan)) {
            return null;
        }

        $indexedPlan = $imagePlan['indexed_color_space'];
        $alternate = $indexedPlan['base_alternate_color_space'];
        $functionType = $alternate['tint_transform_function_type'] ?? null;

        return [
            'base_color_space' => $indexedPlan['base_color_space'] ?? null,
            'colorant_names' => array_values(array_filter(
                $alternate['colorant_names'] ?? [],
                static fn (mixed $name): bool => is_string($name) && $name !== ''
            )),
            'alternate_color_space' => $alternate['alternate_color_space'] ?? null,
            'alternate_components' => $alternate['alternate_components'] ?? null,
            'alternate_uses_icc_profile' => ($alternate['alternate_uses_icc_profile'] ?? false) === true,
            'icc_profile' => $indexedPlan['base_icc_profile'] ?? null,
            'tint_transform_object' => $alternate['tint_transform_object'] ?? null,
            'tint_transform_function_type' => $functionType,
            'tint_transform_preview_mode' => $functionType === null ? 'none' : 'review_only',
        ];
    }

    /**
     * @param array<string, mixed> $imagePlan
     */
    private function indexedPlanUsesAlternateColorSpace(array $imagePlan): bool
    {
        $indexedPlan = $imagePlan['indexed_color_space'] ?? null;
        if (!is_array($indexedPlan)) {
            return false;
        }

        return ($indexedPlan['base_uses_alternate_color_space'] ?? false) === true
            && is_array($indexedPlan['base_alternate_color_space'] ?? null);
    }

    /**
     * Applies a color-key `/Mask` array to raw image samples before any image
     * `/Decode` mapping, then exposes the Decode-adjusted components that a
     * future RGB preview compositor would receive for non-transparent pixels.
     *
     * @param list<int|float> $sample
     * @param array{
     *     bits_per_component?: int,
     *     color_key_mask?: array{present?: bool, ranges?: list<array{min: int, max: int}>, valid_for_components?: bool}|null,
     *     color_key_mask_suppressed_by_soft_mask?: bool,
     *     image_decode?: array{ranges: list<array{min: float, max: float}>, valid_for_components?: bool}|null,
     *     output_color_mode?: string
     * } $imagePlan
     * @return array{raw_sample: list<float>, mask_ranges: list<array{min: int, max: int}>, matches_color_key: bool, alpha: float, decoded_components: list<float>, decode_applied_after_color_key: bool, output_color_mode: string}
     */
    public function colorKeyMaskSamplePreview(array $sample, array $imagePlan): array
    {
        $mask = $imagePlan['color_key_mask'] ?? null;
        if (!is_array($mask) || ($mask['present'] ?? false) !== true) {
            throw new InvalidArgumentException('ColorKey mask preview requires a present /Mask array plan.');
        }
        if (($imagePlan['color_key_mask_suppressed_by_soft_mask'] ?? false) === true) {
            throw new InvalidArgumentException('ColorKey mask is suppressed by a present soft mask and must not be applied.');
        }
        if (($mask['valid_for_components'] ?? false) !== true || !isset($mask['ranges']) || !is_array($mask['ranges'])) {
            throw new InvalidArgumentException('ColorKey mask ranges must match the image component count.');
        }

        $values = array_values($sample);
        $ranges = array_values($mask['ranges']);
        if (count($values) !== count($ranges)) {
            throw new InvalidArgumentException('ColorKey mask sample count must match the image component count.');
        }

        $rawSample = [];
        $matches = true;
        foreach ($values as $index => $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('ColorKey mask sample values must be numeric.');
            }

            $raw = (float) $value;
            $rawSample[] = $raw;
            $range = $ranges[$index];
            $min = (float) ($range['min'] ?? 0);
            $max = (float) ($range['max'] ?? -1);
            if ($raw < $min || $raw > $max) {
                $matches = false;
            }
        }

        $decode = $imagePlan['image_decode'] ?? null;
        $decodeApplied = is_array($decode) && ($decode['valid_for_components'] ?? false) === true;
        $decoded = $decodeApplied
            ? $this->imageSampleDecodeValues($values, $decode, max(1, (int) ($imagePlan['bits_per_component'] ?? 8)))
            : $rawSample;

        return [
            'raw_sample' => $rawSample,
            'mask_ranges' => $ranges,
            'matches_color_key' => $matches,
            'alpha' => $matches ? 0.0 : 1.0,
            'decoded_components' => $decoded,
            'decode_applied_after_color_key' => $decodeApplied,
            'output_color_mode' => (string) ($imagePlan['output_color_mode'] ?? 'RGB'),
        ];
    }

    /**
     * Applies an Indexed image ColorKey mask to the raw palette index before
     * Decode, then expands the Decode-adjusted index into palette components.
     *
     * @param array{
     *     bits_per_component?: int,
     *     indexed_color_space?: array{base_components?: int|null, high_value?: int|null, lookup_length_matches?: bool, lookup_bytes?: list<int>},
     *     color_key_mask?: array{present?: bool, ranges?: list<array{min: int, max: int}>, valid_for_components?: bool}|null,
     *     color_key_mask_suppressed_by_soft_mask?: bool,
     *     image_decode?: array{ranges: list<array{min: float, max: float}>, valid_for_components?: bool}|null,
     *     output_color_mode?: string
     * } $imagePlan
     * @return array{raw_sample: list<float>, mask_ranges: list<array{min: int, max: int}>, matches_color_key: bool, alpha: float, decoded_index: float, palette_index: int, clamped_to_hival: bool, base_components: list<float>, decode_applied_after_color_key: bool, palette_transfer_applied_after_color_key: bool, output_color_mode: string}
     */
    public function indexedColorKeyMaskSamplePreview(int|float $sample, array $imagePlan): array
    {
        if (($imagePlan['source_color_space'] ?? null) !== 'Indexed' || ($imagePlan['uses_indexed_color_space'] ?? false) !== true) {
            throw new InvalidArgumentException('Indexed ColorKey preview requires an Indexed image plan.');
        }

        $colorKey = $this->colorKeyMaskSamplePreview([$sample], $imagePlan);
        $indexed = $this->indexedSamplePreview($sample, $imagePlan);

        return [
            'raw_sample' => $colorKey['raw_sample'],
            'mask_ranges' => $colorKey['mask_ranges'],
            'matches_color_key' => $colorKey['matches_color_key'],
            'alpha' => $colorKey['alpha'],
            'decoded_index' => $indexed['decoded_index'],
            'palette_index' => $indexed['palette_index'],
            'clamped_to_hival' => $indexed['clamped_to_hival'],
            'base_components' => $indexed['base_components'],
            'decode_applied_after_color_key' => $colorKey['decode_applied_after_color_key'],
            'palette_transfer_applied_after_color_key' => true,
            'output_color_mode' => $indexed['output_color_mode'],
        ];
    }

    /**
     * @param list<float> $sample
     * @return list<float>
     */
    private function defaultRgbDecodeComponents(array $sample, int $bitsPerComponent): array
    {
        if (count($sample) !== 3) {
            throw new InvalidArgumentException('RGB default Decode requires exactly three components.');
        }

        $maxSample = (2 ** min(max(1, $bitsPerComponent), 30)) - 1;

        return array_map(
            static fn (float $value): float => max(0.0, min(1.0, $value / $maxSample)),
            $sample
        );
    }

    /**
     * @param list<float> $decodedComponents
     * @return array{red: int, green: int, blue: int, alpha: float}
     */
    private function rgbOutputPreviewComponents(array $decodedComponents, float $alpha): array
    {
        if (count($decodedComponents) !== 3) {
            throw new InvalidArgumentException('RGB output preview requires exactly three decoded components.');
        }

        return [
            'red' => $this->normalizedPreviewByte($decodedComponents[0]),
            'green' => $this->normalizedPreviewByte($decodedComponents[1]),
            'blue' => $this->normalizedPreviewByte($decodedComponents[2]),
            'alpha' => max(0.0, min(1.0, $alpha)),
        ];
    }

    /**
     * @param list<float> $sample
     * @param array<string, mixed> $imagePlan
     * @return list<float>
     */
    private function jpeg2000DeviceRgbOutputComponents(array $sample, array $imagePlan, int $bitsPerComponent): array
    {
        if (count($sample) !== 3) {
            throw new InvalidArgumentException('JPEG2000 DeviceRGB output samples must contain exactly three components.');
        }

        $decode = $imagePlan['image_decode'] ?? null;
        if (is_array($decode)) {
            if (($decode['valid_for_components'] ?? false) !== true) {
                throw new InvalidArgumentException('JPEG2000 DeviceRGB Decode array must match three components.');
            }

            return $this->imageSampleDecodeValues($sample, $decode, $bitsPerComponent);
        }

        return $this->defaultRgbDecodeComponents($sample, $bitsPerComponent);
    }

    private function normalizedPreviewByte(int|float $value): int
    {
        return (int) round(max(0.0, min(1.0, (float) $value)) * 255);
    }

    /**
     * Maps one DeviceGray image sample through the image Decode array, expands
     * it to the RGB preview target used by Marker, and applies an optional
     * soft-mask alpha/transfer sample.
     *
     * @param array{
     *     source_color_space?: string,
     *     components?: int|null,
     *     bits_per_component?: int,
     *     image_decode?: array{ranges: list<array{min: float, max: float}>, valid_for_components?: bool}|null,
     *     soft_mask?: array{present?: bool, decode?: array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool}, bits_per_component?: int|null}|null,
     *     soft_mask_transfer_function?: array<string, mixed>|null,
     *     output_color_mode?: string
     * } $imagePlan
     * @return array{source_color_space: string, raw_sample: float, decoded_gray: float, rgb_components: list<float>, soft_mask_alpha: float|null, soft_mask_alpha_before_transfer: float|null, soft_mask_transfer_applied: bool, soft_mask_transfer_function: array<string, mixed>|null, output_color_mode: string}
     */
    public function deviceGraySamplePreview(int|float $sample, array $imagePlan, int|float|null $softMaskSample = null): array
    {
        if (($imagePlan['source_color_space'] ?? null) !== 'DeviceGray' || ($imagePlan['components'] ?? null) !== 1) {
            throw new InvalidArgumentException('DeviceGray preview requires a DeviceGray image plan.');
        }

        if (!is_int($sample) && !is_float($sample)) {
            throw new InvalidArgumentException('DeviceGray sample value must be numeric.');
        }

        $bitsPerComponent = max(1, (int) ($imagePlan['bits_per_component'] ?? 8));
        $raw = (float) $sample;
        $decode = $imagePlan['image_decode'] ?? null;
        if (is_array($decode)) {
            if (($decode['valid_for_components'] ?? false) !== true) {
                throw new InvalidArgumentException('DeviceGray Decode array must match one component.');
            }

            $decodedGray = $this->imageSampleDecodeValues([$raw], $decode, $bitsPerComponent)[0];
        } else {
            $maxSample = (2 ** min($bitsPerComponent, 30)) - 1;
            $decodedGray = $maxSample <= 0 ? 0.0 : $raw / $maxSample;
        }
        $decodedGray = max(0.0, min(1.0, $decodedGray));

        $softMaskAlpha = null;
        $softMaskAlphaBeforeTransfer = null;
        $softMaskTransferApplied = false;
        $softMaskTransferFunction = null;
        if ($softMaskSample !== null) {
            $softMaskAlphaPreview = $this->softMaskAlphaPreview($softMaskSample, $imagePlan, 'DeviceGray');
            $softMaskAlpha = $softMaskAlphaPreview['alpha'];
            $softMaskAlphaBeforeTransfer = $softMaskAlphaPreview['alpha_before_transfer'];
            $softMaskTransferApplied = $softMaskAlphaPreview['transfer_applied'];
            $softMaskTransferFunction = $softMaskAlphaPreview['transfer_function'];
        }

        return [
            'source_color_space' => 'DeviceGray',
            'raw_sample' => $raw,
            'decoded_gray' => $decodedGray,
            'rgb_components' => [$decodedGray, $decodedGray, $decodedGray],
            'soft_mask_alpha' => $softMaskAlpha,
            'soft_mask_alpha_before_transfer' => $softMaskAlphaBeforeTransfer,
            'soft_mask_transfer_applied' => $softMaskTransferApplied,
            'soft_mask_transfer_function' => $softMaskTransferFunction,
            'output_color_mode' => (string) ($imagePlan['output_color_mode'] ?? 'RGB'),
        ];
    }

    /**
     * Decodes bounded DeviceGray image stream rows when the filter chain is
     * native, attaches matching grayscale soft-mask samples, and exposes the
     * RGB preview rows a future raster backend should receive.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    public function deviceGrayImageStreamPreviewRows(string $imageObject, array $objects = [], int $maxPixels = 16): array
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('DeviceGray image stream preview requires at least one preview pixel.');
        }

        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        if (($plan['source_color_space'] ?? null) !== 'DeviceGray' || ($plan['components'] ?? null) !== 1) {
            throw new InvalidArgumentException('DeviceGray image stream preview requires a DeviceGray image.');
        }

        $width = $this->integerNameValue($dictionary, 'Width');
        $height = $this->integerNameValue($dictionary, 'Height');
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('DeviceGray image stream preview requires positive image Width and Height.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedImageStreamPreviewBoundary($dictionary, $imageObject, $objects);
        if (($imageStream['decoded_with_current_filters'] ?? false) !== true || !is_string($imageStream['decoded_bytes'] ?? null)) {
            throw new InvalidArgumentException('DeviceGray image stream filters must be natively decoded before RGB preview.');
        }

        $imageSamples = $this->packedImagePixelSamples(
            $imageStream['decoded_bytes'],
            1,
            $bitsPerComponent,
            $expectedPixelCount
        );
        if (!$imageSamples['complete']) {
            throw new InvalidArgumentException('DeviceGray image stream does not contain complete image sample data.');
        }

        $softMaskSamples = null;
        $softMaskStream = null;
        $softMask = $plan['soft_mask'] ?? null;
        if (is_array($softMask) && ($softMask['present'] ?? false) === true && ($plan['soft_mask_applied_before_rgb'] ?? false) === true) {
            if (is_int($softMask['width'] ?? null) && $softMask['width'] !== $width) {
                throw new InvalidArgumentException('DeviceGray soft-mask width must match the image width.');
            }
            if (is_int($softMask['height'] ?? null) && $softMask['height'] !== $height) {
                throw new InvalidArgumentException('DeviceGray soft-mask height must match the image height.');
            }

            $softMaskStream = $this->decodedSoftMaskStreamPreviewBoundary($dictionary, $objects);
            if ($softMaskStream === null || ($softMaskStream['decoded_with_current_filters'] ?? false) !== true || !is_string($softMaskStream['decoded_bytes'] ?? null)) {
                throw new InvalidArgumentException('DeviceGray soft-mask stream filters must be natively decoded before RGB preview.');
            }

            $softMaskSamples = $this->packedImagePixelSamples(
                $softMaskStream['decoded_bytes'],
                1,
                max(1, (int) ($softMask['bits_per_component'] ?? 8)),
                $expectedPixelCount
            );
            if (!$softMaskSamples['complete']) {
                throw new InvalidArgumentException('DeviceGray soft-mask stream does not contain complete alpha sample data.');
            }
        }

        $limit = min($maxPixels, $expectedPixelCount);
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $rawSample = $imageSamples['pixels'][$index][0];
            $softMaskSample = is_array($softMaskSamples) ? $softMaskSamples['pixels'][$index][0] : null;
            $preview = $this->deviceGraySamplePreview($rawSample, $plan, $softMaskSample);
            $pixels[] = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'decoded_gray' => $preview['decoded_gray'],
                'rgb_components' => $preview['rgb_components'],
                'soft_mask_sample' => $softMaskSample,
                'soft_mask_alpha' => $preview['soft_mask_alpha'],
                'soft_mask_alpha_before_transfer' => $preview['soft_mask_alpha_before_transfer'],
                'soft_mask_transfer_applied' => $preview['soft_mask_transfer_applied'],
            ];
        }

        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);
        $softMaskStreamMeta = is_array($softMaskStream) ? $this->streamBoundaryPublicMetadata($softMaskStream) : null;
        $streamNotes = [
            $imageStreamMeta['filters'] === []
                ? 'devicegray_image_stream_unfiltered_samples_before_rgb_conversion'
                : 'devicegray_image_stream_filters_decoded_before_rgb_conversion',
        ];
        if ($softMaskStreamMeta !== null) {
            $streamNotes[] = $softMaskStreamMeta['filters'] === []
                ? 'soft_mask_stream_unfiltered_samples_before_rgb_conversion'
                : 'soft_mask_stream_filters_decoded_before_rgb_conversion';
        }

        return [
            'source_color_space' => (string) $plan['source_color_space'],
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => 1,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'complete_image_sample_data' => $imageSamples['complete'],
            'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
            'image_stream' => $imageStreamMeta,
            'soft_mask_stream' => $softMaskStreamMeta,
            'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
            'image_decode' => $plan['image_decode'],
            'soft_mask_transfer_function' => $plan['soft_mask_transfer_function'],
            'soft_mask_transfer_function_applied_before_rgb' => $plan['soft_mask_transfer_function_applied_before_rgb'],
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
        ];
    }

    /**
     * Maps Separation/DeviceN image samples into named tint values and applies
     * the matching soft-mask alpha before the Marker RGB preview handoff.
     *
     * @param list<int|float> $sample
     * @param array{
     *     source_color_space?: string,
     *     components?: int|null,
     *     bits_per_component?: int,
     *     uses_alternate_color_space?: bool,
     *     alternate_color_space?: array{
     *         family?: string,
     *         colorant_names?: list<string>,
     *         alternate_color_space?: string|null,
     *         alternate_components?: int|null,
     *         alternate_uses_icc_profile?: bool,
     *         tint_transform_object?: int|null,
     *         tint_transform_function_type?: int|null
     *     }|null,
     *     icc_profile?: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null,
     *     image_decode?: array{ranges: list<array{min: float, max: float}>, valid_for_components?: bool}|null,
     *     soft_mask?: array{present?: bool, decode?: array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool}, bits_per_component?: int|null}|null,
     *     output_color_mode?: string
     * } $imagePlan
     * @return array{source_color_space: string, colorant_tints: array<string, float>, tint_values: list<float>, alternate_color_space: string|null, alternate_components: int|null, alternate_uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, tint_transform_object: int|null, tint_transform_function_type: int|null, tint_transform_preview_mode: string, soft_mask_alpha: float|null, output_color_mode: string}
     */
    public function alternateColorantSamplePreview(array $sample, array $imagePlan, int|float|null $softMaskSample = null): array
    {
        $alternate = $imagePlan['alternate_color_space'] ?? null;
        if (($imagePlan['uses_alternate_color_space'] ?? false) !== true || !is_array($alternate)) {
            throw new InvalidArgumentException('Alternate colorant preview requires a Separation or DeviceN image plan.');
        }

        $colorantNames = array_values(array_filter(
            $alternate['colorant_names'] ?? [],
            static fn (mixed $name): bool => is_string($name) && $name !== ''
        ));
        $expectedComponents = count($colorantNames);
        if ($expectedComponents === 0 && isset($imagePlan['components']) && is_int($imagePlan['components'])) {
            $expectedComponents = $imagePlan['components'];
        }
        if ($expectedComponents < 1) {
            throw new InvalidArgumentException('Alternate colorant preview requires at least one colorant.');
        }

        $values = array_values($sample);
        if (count($values) !== $expectedComponents) {
            throw new InvalidArgumentException('Alternate colorant sample count must match the image colorant count.');
        }
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Alternate colorant sample values must be numeric.');
            }
        }

        $decode = $imagePlan['image_decode'] ?? null;
        $tints = array_map(static fn (int|float $value): float => (float) $value, $values);
        if (is_array($decode)) {
            if (($decode['valid_for_components'] ?? false) !== true) {
                throw new InvalidArgumentException('Alternate colorant Decode array must match the image colorant count.');
            }

            $tints = $this->imageSampleDecodeValues(
                $values,
                $decode,
                max(1, (int) ($imagePlan['bits_per_component'] ?? 8))
            );
        }

        $namedTints = [];
        for ($index = 0; $index < $expectedComponents; $index++) {
            $name = $colorantNames[$index] ?? 'colorant_' . ($index + 1);
            $namedTints[$name] = $tints[$index];
        }

        $softMaskAlpha = null;
        $softMaskAlphaBeforeTransfer = null;
        $softMaskTransferApplied = false;
        $softMaskTransferFunction = null;
        if ($softMaskSample !== null) {
            $softMaskAlphaPreview = $this->softMaskAlphaPreview($softMaskSample, $imagePlan, 'Alternate colorant');
            $softMaskAlpha = $softMaskAlphaPreview['alpha'];
            $softMaskAlphaBeforeTransfer = $softMaskAlphaPreview['alpha_before_transfer'];
            $softMaskTransferApplied = $softMaskAlphaPreview['transfer_applied'];
            $softMaskTransferFunction = $softMaskAlphaPreview['transfer_function'];
        }

        $functionType = $alternate['tint_transform_function_type'] ?? null;

        return [
            'source_color_space' => (string) ($imagePlan['source_color_space'] ?? ($alternate['family'] ?? 'DeviceN')),
            'colorant_tints' => $namedTints,
            'tint_values' => $tints,
            'alternate_color_space' => $alternate['alternate_color_space'] ?? null,
            'alternate_components' => $alternate['alternate_components'] ?? null,
            'alternate_uses_icc_profile' => ($alternate['alternate_uses_icc_profile'] ?? false) === true,
            'icc_profile' => $imagePlan['icc_profile'] ?? null,
            'tint_transform_object' => $alternate['tint_transform_object'] ?? null,
            'tint_transform_function_type' => $functionType,
            'tint_transform_preview_mode' => $functionType === null ? 'none' : 'review_only',
            'soft_mask_alpha' => $softMaskAlpha,
            'soft_mask_alpha_before_transfer' => $softMaskAlphaBeforeTransfer,
            'soft_mask_transfer_applied' => $softMaskTransferApplied,
            'soft_mask_transfer_function' => $softMaskTransferFunction,
            'output_color_mode' => (string) ($imagePlan['output_color_mode'] ?? 'RGB'),
        ];
    }

    /**
     * Decodes a bounded Separation/DeviceN image stream into per-pixel tint rows
     * and attaches matching soft-mask alpha before the Marker RGB preview handoff.
     *
     * This is intentionally a parser-side preview boundary. It only accepts image
     * streams whose filters are natively decoded here and leaves tint-transform
     * and ICC conversion as review metadata for a future raster backend.
     *
     * @param array<int, string> $objects
     * @param list<int|float|list<int|float>>|null $suppliedSoftMaskSamples Already-rasterized alpha/luminosity samples for soft-mask transparency groups.
     * @return array<string, mixed>
     */
    public function alternateColorantStreamPreviewRows(
        string $imageObject,
        array $objects = [],
        int $maxPixels = 16,
        ?array $suppliedSoftMaskSamples = null
    ): array
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('Alternate colorant stream preview requires at least one preview pixel.');
        }

        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        if (($plan['uses_alternate_color_space'] ?? false) !== true) {
            throw new InvalidArgumentException('Alternate colorant stream preview requires a Separation or DeviceN image.');
        }

        $width = $this->integerNameValue($dictionary, 'Width');
        $height = $this->integerNameValue($dictionary, 'Height');
        $components = $plan['components'] ?? null;
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Alternate colorant stream preview requires positive image Width and Height.');
        }
        if (!is_int($components) || $components < 1) {
            throw new InvalidArgumentException('Alternate colorant stream preview requires a positive colorant count.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedImageStreamPreviewBoundary($dictionary, $imageObject, $objects);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);
        $imageStreamDecoded = ($imageStream['decoded_with_current_filters'] ?? false) === true
            && is_string($imageStream['decoded_bytes'] ?? null);
        $imageStreamReviewOnly = !$imageStreamDecoded && $this->imageStreamBoundaryIsReviewOnly($imageStreamMeta);
        if (!$imageStreamDecoded && !$imageStreamReviewOnly) {
            throw new InvalidArgumentException('Alternate colorant image stream filters must be natively decoded before RGB preview.');
        }

        $softMaskSamples = null;
        $softMaskStream = null;
        $softMaskStreamMeta = null;
        $softMask = $plan['soft_mask'] ?? null;
        $softMaskGroup = $plan['soft_mask_group'] ?? null;
        $softMaskIsTransparencyGroup = is_array($softMask)
            && ($softMask['present'] ?? false) === true
            && is_array($softMaskGroup)
            && !is_int($softMask['width'] ?? null)
            && !is_int($softMask['height'] ?? null);
        $usesSuppliedSoftMaskSamples = false;
        if ($suppliedSoftMaskSamples !== null && !$softMaskIsTransparencyGroup) {
            throw new InvalidArgumentException('Supplied alternate colorant soft-mask samples are only supported for transparency-group masks.');
        }
        if (is_array($softMask) && ($softMask['present'] ?? false) === true) {
            if (($plan['soft_mask_applied_before_rgb'] ?? false) !== true) {
                throw new InvalidArgumentException('Alternate colorant stream preview requires a grayscale soft-mask image.');
            }
            if (is_int($softMask['width'] ?? null) && $softMask['width'] !== $width) {
                throw new InvalidArgumentException('Alternate colorant soft-mask width must match the image width.');
            }
            if (is_int($softMask['height'] ?? null) && $softMask['height'] !== $height) {
                throw new InvalidArgumentException('Alternate colorant soft-mask height must match the image height.');
            }

            if ($softMaskIsTransparencyGroup && $suppliedSoftMaskSamples !== null) {
                $softMaskSamples = $this->normalizeSuppliedSoftMaskSamples($suppliedSoftMaskSamples, $expectedPixelCount);
                $usesSuppliedSoftMaskSamples = true;
            }

            if ($softMaskIsTransparencyGroup && !$imageStreamReviewOnly && !$usesSuppliedSoftMaskSamples) {
                throw new InvalidArgumentException('Alternate colorant stream preview requires sampled soft-mask alpha for transparency groups.');
            }

            if (!$softMaskIsTransparencyGroup) {
                $softMaskStream = $this->decodedSoftMaskStreamPreviewBoundary($dictionary, $objects);
                $softMaskStreamMeta = is_array($softMaskStream) ? $this->streamBoundaryPublicMetadata($softMaskStream) : null;
                if (is_array($softMaskStream) && ($softMaskStream['decoded_with_current_filters'] ?? false) === true && is_string($softMaskStream['decoded_bytes'] ?? null)) {
                    $softMaskSamples = $this->packedImagePixelSamples(
                        $softMaskStream['decoded_bytes'],
                        1,
                        max(1, (int) ($softMask['bits_per_component'] ?? 8)),
                        $expectedPixelCount
                    );
                } elseif (!$imageStreamReviewOnly) {
                    throw new InvalidArgumentException('Alternate colorant soft-mask stream filters must be natively decoded before RGB preview.');
                }

                if (is_array($softMaskSamples) && !$softMaskSamples['complete']) {
                    throw new InvalidArgumentException('Alternate colorant soft-mask stream does not contain complete alpha sample data.');
                }
            }
            if ($softMaskIsTransparencyGroup && $imageStreamDecoded && is_array($softMaskSamples) && !$softMaskSamples['complete']) {
                throw new InvalidArgumentException('Alternate colorant supplied soft-mask samples must cover every decoded image pixel.');
            }
        }

        $streamNotes = [
            $imageStreamMeta['filters'] === []
                ? 'image_stream_unfiltered_samples_before_rgb_conversion'
                : 'image_stream_filters_decoded_before_rgb_conversion',
        ];
        if (!$imageStreamMeta['decoded_with_current_filters'] && $imageStreamMeta['preview_only_filters'] !== []) {
            $streamNotes[0] = 'alternate_colorant_image_stream_preview_only_before_rgb_conversion';
        }
        if ($softMaskStreamMeta !== null) {
            $streamNotes[] = $softMaskStreamMeta['filters'] === []
                ? 'soft_mask_stream_unfiltered_samples_before_rgb_conversion'
                : 'soft_mask_stream_filters_decoded_before_rgb_conversion';
        } elseif ($softMaskIsTransparencyGroup && $imageStreamReviewOnly) {
            $streamNotes[] = 'soft_mask_transfer_function_reviewed_without_raster_samples';
        } elseif ($usesSuppliedSoftMaskSamples) {
            $streamNotes[] = 'soft_mask_transparency_group_supplied_samples_before_rgb_conversion';
        }

        $alternate = $plan['alternate_color_space'];
        $softMaskDecodeReview = $this->softMaskDecodeReviewMetadata($plan);

        if (!$imageStreamDecoded) {
            return [
                'source_color_space' => (string) $plan['source_color_space'],
                'width' => $width,
                'height' => $height,
                'components_per_pixel' => $components,
                'bits_per_component' => $bitsPerComponent,
                'expected_pixel_count' => $expectedPixelCount,
                'preview_pixel_count' => 0,
                'review_only_image_stream' => true,
                'complete_image_sample_data' => false,
                'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
                'image_filter_boundary' => $plan['image_filter_boundary'],
                'image_filter_details' => $plan['image_filter_details'],
                'image_stream' => $imageStreamMeta,
                'soft_mask_stream' => $softMaskStreamMeta,
                'soft_mask' => $softMask,
                'soft_mask_group' => $softMaskGroup,
                'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
                'soft_mask_decode_review' => $softMaskDecodeReview,
                'uses_supplied_soft_mask_samples' => $usesSuppliedSoftMaskSamples,
                'alternate_color_space' => is_array($alternate) ? ($alternate['alternate_color_space'] ?? null) : null,
                'alternate_components' => is_array($alternate) ? ($alternate['alternate_components'] ?? null) : null,
                'alternate_uses_icc_profile' => is_array($alternate) && ($alternate['alternate_uses_icc_profile'] ?? false) === true,
                'icc_profile' => $plan['icc_profile'] ?? null,
                'tint_transform_object' => is_array($alternate) ? ($alternate['tint_transform_object'] ?? null) : null,
                'tint_transform_function_type' => is_array($alternate) ? ($alternate['tint_transform_function_type'] ?? null) : null,
                'tint_transform_preview_mode' => is_array($alternate) && ($alternate['tint_transform_function_type'] ?? null) !== null ? 'review_only' : 'none',
                'image_decode' => $plan['image_decode'],
                'soft_mask_transfer_function' => $plan['soft_mask_transfer_function'],
                'soft_mask_transfer_function_applied_before_rgb' => $plan['soft_mask_transfer_function_applied_before_rgb'],
                'pixels' => [],
                'stream_notes' => $streamNotes,
                'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
                'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
                'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
            ];
        }

        $imageSamples = $this->packedImagePixelSamples(
            $imageStream['decoded_bytes'],
            $components,
            $bitsPerComponent,
            $expectedPixelCount
        );
        if (!$imageSamples['complete']) {
            throw new InvalidArgumentException('Alternate colorant image stream does not contain complete image sample data.');
        }
        if (is_array($softMask) && ($softMask['present'] ?? false) === true && !$softMaskIsTransparencyGroup && $softMaskSamples === null) {
            throw new InvalidArgumentException('Alternate colorant soft-mask stream filters must be natively decoded before RGB preview.');
        }

        $limit = min($maxPixels, $expectedPixelCount);
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $rawSample = $imageSamples['pixels'][$index];
            $softMaskSample = is_array($softMaskSamples) ? $softMaskSamples['pixels'][$index][0] : null;
            $preview = $this->alternateColorantSamplePreview($rawSample, $plan, $softMaskSample);
            $pixels[] = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'colorant_tints' => $preview['colorant_tints'],
                'tint_values' => $preview['tint_values'],
                'soft_mask_sample' => $softMaskSample,
                'soft_mask_alpha' => $preview['soft_mask_alpha'],
                'soft_mask_alpha_before_transfer' => $preview['soft_mask_alpha_before_transfer'],
                'soft_mask_transfer_applied' => $preview['soft_mask_transfer_applied'],
            ];
        }

        return [
            'source_color_space' => (string) $plan['source_color_space'],
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => $components,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => false,
            'complete_image_sample_data' => $imageSamples['complete'],
            'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
            'image_filter_boundary' => $plan['image_filter_boundary'],
            'image_filter_details' => $plan['image_filter_details'],
            'image_stream' => $imageStreamMeta,
            'soft_mask_stream' => $softMaskStreamMeta,
            'soft_mask' => $softMask,
            'soft_mask_group' => $softMaskGroup,
            'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
            'soft_mask_decode_review' => $softMaskDecodeReview,
            'uses_supplied_soft_mask_samples' => $usesSuppliedSoftMaskSamples,
            'alternate_color_space' => is_array($alternate) ? ($alternate['alternate_color_space'] ?? null) : null,
            'alternate_components' => is_array($alternate) ? ($alternate['alternate_components'] ?? null) : null,
            'alternate_uses_icc_profile' => is_array($alternate) && ($alternate['alternate_uses_icc_profile'] ?? false) === true,
            'icc_profile' => $plan['icc_profile'] ?? null,
            'tint_transform_object' => is_array($alternate) ? ($alternate['tint_transform_object'] ?? null) : null,
            'tint_transform_function_type' => is_array($alternate) ? ($alternate['tint_transform_function_type'] ?? null) : null,
            'tint_transform_preview_mode' => is_array($alternate) && ($alternate['tint_transform_function_type'] ?? null) !== null ? 'review_only' : 'none',
            'image_decode' => $plan['image_decode'],
            'soft_mask_transfer_function' => $plan['soft_mask_transfer_function'],
            'soft_mask_transfer_function_applied_before_rgb' => $plan['soft_mask_transfer_function_applied_before_rgb'],
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
        ];
    }

    /**
     * Normalizes caller-supplied soft-mask transparency-group samples into the
     * same one-component pixel shape used for decoded soft-mask image streams.
     *
     * @param list<int|float|list<int|float>> $samples
     * @return array{pixels: list<list<float>>, available_pixel_count: int, complete: bool}
     */
    private function normalizeSuppliedSoftMaskSamples(array $samples, int $expectedPixelCount): array
    {
        if ($expectedPixelCount < 0) {
            throw new InvalidArgumentException('Expected soft-mask pixel count must be non-negative.');
        }

        $pixels = [];
        foreach (array_values($samples) as $row) {
            if (is_array($row)) {
                $values = array_values($row);
                if (count($values) !== 1) {
                    throw new InvalidArgumentException('Supplied soft-mask sample rows must contain exactly one alpha value.');
                }

                $value = $values[0];
            } else {
                $value = $row;
            }

            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Supplied soft-mask samples must be numeric alpha values.');
            }

            $pixels[] = [max(0.0, min(1.0, (float) $value))];
        }

        return [
            'pixels' => array_slice($pixels, 0, $expectedPixelCount),
            'available_pixel_count' => count($pixels),
            'complete' => count($pixels) >= $expectedPixelCount,
        ];
    }

    /**
     * Applies calibrated color-space Decode ranges to one image sample before
     * the Marker RGB preview handoff and attaches the matching soft-mask alpha.
     *
     * @param list<int|float> $sample
     * @param array{
     *     source_color_space?: string,
     *     components?: int|null,
     *     bits_per_component?: int,
     *     uses_calibrated_color_space?: bool,
     *     calibrated_color_space?: array{family?: string, white_point?: list<float>, black_point?: list<float>, gamma?: float|list<float>|null, matrix?: list<float>|null, range?: list<float>|null, default_decode?: list<float>}|null,
     *     image_decode?: array{ranges: list<array{min: float, max: float}>, valid_for_components?: bool, source?: string}|null,
     *     soft_mask?: array{present?: bool, decode?: array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool}, bits_per_component?: int|null}|null,
     *     output_color_mode?: string
     * } $imagePlan
     * @return array{source_color_space: string, decoded_components: list<float>, calibrated_components: array<string, float>, white_point: list<float>, black_point: list<float>, gamma: float|list<float>|null, matrix: list<float>|null, range: list<float>|null, decode_source: string|null, uses_default_decode: bool, soft_mask_alpha: float|null, output_color_mode: string}
     */
    public function calibratedColorSamplePreview(array $sample, array $imagePlan, int|float|null $softMaskSample = null): array
    {
        $calibrated = $imagePlan['calibrated_color_space'] ?? null;
        if (($imagePlan['uses_calibrated_color_space'] ?? false) !== true || !is_array($calibrated)) {
            throw new InvalidArgumentException('Calibrated color preview requires a CalGray, CalRGB, or Lab image plan.');
        }

        $expectedComponents = $imagePlan['components'] ?? null;
        if (!is_int($expectedComponents) || $expectedComponents < 1) {
            throw new InvalidArgumentException('Calibrated color preview requires a positive component count.');
        }

        $values = array_values($sample);
        if (count($values) !== $expectedComponents) {
            throw new InvalidArgumentException('Calibrated color sample count must match the image component count.');
        }
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Calibrated color sample values must be numeric.');
            }
        }

        $decode = $imagePlan['image_decode'] ?? null;
        if (!is_array($decode) || ($decode['valid_for_components'] ?? false) !== true) {
            throw new InvalidArgumentException('Calibrated color Decode array must match the image component count.');
        }

        $decoded = $this->imageSampleDecodeValues(
            $values,
            $decode,
            max(1, (int) ($imagePlan['bits_per_component'] ?? 8))
        );

        $family = (string) ($calibrated['family'] ?? ($imagePlan['source_color_space'] ?? 'CalRGB'));
        $labels = match ($family) {
            'CalGray' => ['gray'],
            'Lab' => ['l', 'a', 'b'],
            default => ['red', 'green', 'blue'],
        };
        $components = [];
        foreach ($decoded as $index => $value) {
            $components[$labels[$index] ?? 'component_' . ($index + 1)] = $value;
        }

        $softMaskAlpha = null;
        $softMaskAlphaBeforeTransfer = null;
        $softMaskTransferApplied = false;
        $softMaskTransferFunction = null;
        if ($softMaskSample !== null) {
            $softMaskAlphaPreview = $this->softMaskAlphaPreview($softMaskSample, $imagePlan, 'Calibrated color');
            $softMaskAlpha = $softMaskAlphaPreview['alpha'];
            $softMaskAlphaBeforeTransfer = $softMaskAlphaPreview['alpha_before_transfer'];
            $softMaskTransferApplied = $softMaskAlphaPreview['transfer_applied'];
            $softMaskTransferFunction = $softMaskAlphaPreview['transfer_function'];
        }

        return [
            'source_color_space' => $family,
            'decoded_components' => $decoded,
            'calibrated_components' => $components,
            'white_point' => $calibrated['white_point'] ?? [],
            'black_point' => $calibrated['black_point'] ?? [],
            'gamma' => $calibrated['gamma'] ?? null,
            'matrix' => $calibrated['matrix'] ?? null,
            'range' => $calibrated['range'] ?? null,
            'decode_source' => $decode['source'] ?? null,
            'uses_default_decode' => ($decode['source'] ?? null) === 'default-calibrated',
            'soft_mask_alpha' => $softMaskAlpha,
            'soft_mask_alpha_before_transfer' => $softMaskAlphaBeforeTransfer,
            'soft_mask_transfer_applied' => $softMaskTransferApplied,
            'soft_mask_transfer_function' => $softMaskTransferFunction,
            'output_color_mode' => (string) ($imagePlan['output_color_mode'] ?? 'RGB'),
        ];
    }

    /**
     * Decodes bounded CalGray/CalRGB/Lab image stream rows when the filter
     * chain is native, while leaving JBIG2/JPX/CCITT/DCT raster streams as
     * review-only boundaries and still decoding a supported current soft mask.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    public function calibratedImageStreamPreviewRows(string $imageObject, array $objects = [], int $maxPixels = 16): array
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('Calibrated image stream preview requires at least one preview pixel.');
        }

        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        if (($plan['uses_calibrated_color_space'] ?? false) !== true) {
            throw new InvalidArgumentException('Calibrated image stream preview requires a CalGray, CalRGB, or Lab image.');
        }

        $width = $this->integerNameValue($dictionary, 'Width');
        $height = $this->integerNameValue($dictionary, 'Height');
        $components = $plan['components'] ?? null;
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('Calibrated image stream preview requires positive image Width and Height.');
        }
        if (!is_int($components) || $components < 1) {
            throw new InvalidArgumentException('Calibrated image stream preview requires a positive component count.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedImageStreamPreviewBoundary($dictionary, $imageObject, $objects);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);

        $softMaskStream = null;
        $softMaskStreamMeta = null;
        $softMaskSamples = null;
        $softMask = $plan['soft_mask'] ?? null;
        $softMaskPresent = is_array($softMask) && ($softMask['present'] ?? false) === true;
        if ($softMaskPresent) {
            if (($plan['soft_mask_applied_before_rgb'] ?? false) !== true) {
                throw new InvalidArgumentException('Calibrated image stream preview requires a grayscale soft-mask image.');
            }
            if (is_int($softMask['width'] ?? null) && $softMask['width'] !== $width) {
                throw new InvalidArgumentException('Calibrated soft-mask width must match the image width.');
            }
            if (is_int($softMask['height'] ?? null) && $softMask['height'] !== $height) {
                throw new InvalidArgumentException('Calibrated soft-mask height must match the image height.');
            }

            $softMaskStream = $this->decodedSoftMaskStreamPreviewBoundary($dictionary, $objects);
            $softMaskStreamMeta = is_array($softMaskStream) ? $this->streamBoundaryPublicMetadata($softMaskStream) : null;
            if (is_array($softMaskStream) && ($softMaskStream['decoded_with_current_filters'] ?? false) === true && is_string($softMaskStream['decoded_bytes'] ?? null)) {
                $softMaskSamples = $this->packedImagePixelSamples(
                    $softMaskStream['decoded_bytes'],
                    1,
                    max(1, (int) ($softMask['bits_per_component'] ?? 8)),
                    $expectedPixelCount
                );
            }
        }

        $streamNotes = [
            $imageStreamMeta['filters'] === []
                ? 'calibrated_image_stream_unfiltered_samples_before_rgb_conversion'
                : 'calibrated_image_stream_filters_decoded_before_rgb_conversion',
        ];
        if (!$imageStreamMeta['decoded_with_current_filters'] && $imageStreamMeta['preview_only_filters'] !== []) {
            $streamNotes[0] = 'calibrated_image_stream_preview_only_before_rgb_conversion';
        }
        if ($softMaskStreamMeta !== null) {
            $streamNotes[] = $softMaskStreamMeta['filters'] === []
                ? 'soft_mask_stream_unfiltered_samples_before_rgb_conversion'
                : 'soft_mask_stream_filters_decoded_before_rgb_conversion';
        }

        if (($imageStream['decoded_with_current_filters'] ?? false) !== true || !is_string($imageStream['decoded_bytes'] ?? null)) {
            if ($imageStreamMeta['preview_only_filters'] === []) {
                throw new InvalidArgumentException('Calibrated image stream filters must be natively decoded before sample preview.');
            }

            return [
                'source_color_space' => (string) $plan['source_color_space'],
                'width' => $width,
                'height' => $height,
                'components_per_pixel' => $components,
                'bits_per_component' => $bitsPerComponent,
                'expected_pixel_count' => $expectedPixelCount,
                'preview_pixel_count' => 0,
                'review_only_image_stream' => true,
                'complete_image_sample_data' => false,
                'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
                'image_filter_boundary' => $plan['image_filter_boundary'],
                'image_filter_details' => $plan['image_filter_details'],
                'image_stream' => $imageStreamMeta,
                'soft_mask_stream' => $softMaskStreamMeta,
                'calibrated_color_space' => $plan['calibrated_color_space'],
                'image_decode' => $plan['image_decode'],
                'soft_mask' => $softMask,
                'pixels' => [],
                'stream_notes' => $streamNotes,
                'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
                'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
                'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
            ];
        }

        $imageSamples = $this->packedImagePixelSamples(
            $imageStream['decoded_bytes'],
            $components,
            $bitsPerComponent,
            $expectedPixelCount
        );
        if (!$imageSamples['complete']) {
            throw new InvalidArgumentException('Calibrated image stream does not contain complete image sample data.');
        }
        if ($softMaskPresent && $softMaskSamples === null) {
            throw new InvalidArgumentException('Calibrated image soft-mask stream filters must be natively decoded before RGB preview.');
        }
        if (is_array($softMaskSamples) && !$softMaskSamples['complete']) {
            throw new InvalidArgumentException('Calibrated image soft-mask stream does not contain complete alpha sample data.');
        }

        $limit = min($maxPixels, $expectedPixelCount);
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $rawSample = $imageSamples['pixels'][$index];
            $softMaskSample = is_array($softMaskSamples) ? $softMaskSamples['pixels'][$index][0] : null;
            $preview = $this->calibratedColorSamplePreview($rawSample, $plan, $softMaskSample);
            $pixels[] = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'decoded_components' => $preview['decoded_components'],
                'calibrated_components' => $preview['calibrated_components'],
                'soft_mask_sample' => $softMaskSample,
                'soft_mask_alpha' => $preview['soft_mask_alpha'],
                'soft_mask_alpha_before_transfer' => $preview['soft_mask_alpha_before_transfer'],
                'soft_mask_transfer_applied' => $preview['soft_mask_transfer_applied'],
            ];
        }

        return [
            'source_color_space' => (string) $plan['source_color_space'],
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => $components,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => false,
            'complete_image_sample_data' => $imageSamples['complete'],
            'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
            'image_filter_boundary' => $plan['image_filter_boundary'],
            'image_filter_details' => $plan['image_filter_details'],
            'image_stream' => $imageStreamMeta,
            'soft_mask_stream' => $softMaskStreamMeta,
            'calibrated_color_space' => $plan['calibrated_color_space'],
            'image_decode' => $plan['image_decode'],
            'soft_mask' => $softMask,
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
        ];
    }

    /**
     * Maps one ICCBased image sample through the image Decode array before the
     * Marker RGB preview handoff and attaches the matching soft-mask alpha.
     *
     * The ICC transform itself remains review metadata for a future raster
     * backend; this boundary makes the parsed component and transparency rows
     * deterministic without running pypdfium/PIL.
     *
     * @param list<int|float> $sample
     * @param array<string, mixed> $imagePlan
     * @return array<string, mixed>
     */
    public function iccBasedSamplePreview(array $sample, array $imagePlan, int|float|null $softMaskSample = null): array
    {
        $iccProfile = $imagePlan['icc_profile'] ?? null;
        if (($imagePlan['source_color_space'] ?? null) !== 'ICCBased' || ($imagePlan['uses_icc_profile'] ?? false) !== true || !is_array($iccProfile)) {
            throw new InvalidArgumentException('ICCBased preview requires an ICCBased image plan.');
        }

        $expectedComponents = $imagePlan['components'] ?? null;
        if (!is_int($expectedComponents) || $expectedComponents < 1) {
            throw new InvalidArgumentException('ICCBased preview requires a positive component count.');
        }

        $values = array_values($sample);
        if (count($values) !== $expectedComponents) {
            throw new InvalidArgumentException('ICCBased sample count must match the image component count.');
        }
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('ICCBased sample values must be numeric.');
            }
        }

        $bitsPerComponent = max(1, (int) ($imagePlan['bits_per_component'] ?? 8));
        $decode = $imagePlan['image_decode'] ?? null;
        $decodeSource = null;
        $usesProfileRangeDecode = false;
        if (is_array($decode)) {
            if (($decode['valid_for_components'] ?? false) !== true) {
                throw new InvalidArgumentException('ICCBased Decode array must match the image component count.');
            }

            $decoded = $this->imageSampleDecodeValues($values, $decode, $bitsPerComponent);
            $decodeSource = $decode['source'] ?? null;
        } else {
            $profileRange = $iccProfile['range'] ?? [];
            $useProfileRange = is_array($profileRange) && count($profileRange) === $expectedComponents * 2;
            $maxSample = (2 ** min($bitsPerComponent, 30)) - 1;
            $decoded = [];
            foreach ($values as $index => $value) {
                $ratio = $maxSample <= 0 ? 0.0 : max(0.0, min(1.0, (float) $value / $maxSample));
                if ($useProfileRange) {
                    $min = (float) $profileRange[$index * 2];
                    $max = (float) $profileRange[($index * 2) + 1];
                    $decoded[] = $min + (($max - $min) * $ratio);
                    continue;
                }

                $decoded[] = $ratio;
            }

            $decodeSource = $useProfileRange ? 'default-icc-profile-range' : 'default-normalized';
            $usesProfileRangeDecode = $useProfileRange;
        }

        $softMaskAlpha = null;
        $softMaskAlphaBeforeTransfer = null;
        $softMaskTransferApplied = false;
        $softMaskTransferFunction = null;
        if ($softMaskSample !== null) {
            $softMaskAlphaPreview = $this->softMaskAlphaPreview($softMaskSample, $imagePlan, 'ICCBased');
            $softMaskAlpha = $softMaskAlphaPreview['alpha'];
            $softMaskAlphaBeforeTransfer = $softMaskAlphaPreview['alpha_before_transfer'];
            $softMaskTransferApplied = $softMaskAlphaPreview['transfer_applied'];
            $softMaskTransferFunction = $softMaskAlphaPreview['transfer_function'];
        }

        return [
            'source_color_space' => 'ICCBased',
            'raw_sample' => array_map(static fn (int|float $value): float => (float) $value, $values),
            'decoded_components' => $decoded,
            'icc_profile' => $iccProfile,
            'profile_components' => $iccProfile['components'] ?? null,
            'alternate_color_space' => $iccProfile['alternate_color_space'] ?? null,
            'profile_range' => $iccProfile['range'] ?? [],
            'decode_source' => $decodeSource,
            'uses_profile_range_decode' => $usesProfileRangeDecode,
            'soft_mask_alpha' => $softMaskAlpha,
            'soft_mask_alpha_before_transfer' => $softMaskAlphaBeforeTransfer,
            'soft_mask_transfer_applied' => $softMaskTransferApplied,
            'soft_mask_transfer_function' => $softMaskTransferFunction,
            'output_color_mode' => (string) ($imagePlan['output_color_mode'] ?? 'RGB'),
        ];
    }

    /**
     * Decodes bounded ICCBased image stream rows when the filter chain is
     * native, applies image Decode ranges, and attaches matching soft-mask alpha
     * rows before the Marker RGB preview handoff.
     *
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    public function iccBasedImageStreamPreviewRows(string $imageObject, array $objects = [], int $maxPixels = 16): array
    {
        if ($maxPixels < 1) {
            throw new InvalidArgumentException('ICCBased image stream preview requires at least one preview pixel.');
        }

        $dictionary = $this->streamDictionaryFromValue($imageObject) ?? trim($imageObject);
        $plan = $this->imageColorSpaceSoftMaskPlan($dictionary, $objects);
        if (($plan['source_color_space'] ?? null) !== 'ICCBased' || ($plan['uses_icc_profile'] ?? false) !== true) {
            throw new InvalidArgumentException('ICCBased image stream preview requires an ICCBased image.');
        }

        $width = $this->integerNameValue($dictionary, 'Width');
        $height = $this->integerNameValue($dictionary, 'Height');
        $components = $plan['components'] ?? null;
        $bitsPerComponent = max(1, (int) ($plan['bits_per_component'] ?? 8));
        if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1) {
            throw new InvalidArgumentException('ICCBased image stream preview requires positive image Width and Height.');
        }
        if (!is_int($components) || $components < 1) {
            throw new InvalidArgumentException('ICCBased image stream preview requires a positive component count.');
        }

        $expectedPixelCount = $width * $height;
        $imageStream = $this->decodedImageStreamPreviewBoundary($dictionary, $imageObject, $objects);
        $imageStreamMeta = $this->streamBoundaryPublicMetadata($imageStream);
        $imageStreamDecoded = ($imageStream['decoded_with_current_filters'] ?? false) === true
            && is_string($imageStream['decoded_bytes'] ?? null);
        $imageStreamReviewOnly = !$imageStreamDecoded && $this->imageStreamBoundaryIsReviewOnly($imageStreamMeta);
        if (!$imageStreamDecoded && !$imageStreamReviewOnly) {
            throw new InvalidArgumentException('ICCBased image stream filters must be natively decoded before RGB preview.');
        }

        $softMaskSamples = null;
        $softMaskStream = null;
        $softMaskStreamMeta = null;
        $softMask = $plan['soft_mask'] ?? null;
        $softMaskGroup = $plan['soft_mask_group'] ?? null;
        $softMaskIsTransparencyGroup = is_array($softMask)
            && ($softMask['present'] ?? false) === true
            && is_array($softMaskGroup)
            && !is_int($softMask['width'] ?? null)
            && !is_int($softMask['height'] ?? null);
        if (is_array($softMask) && ($softMask['present'] ?? false) === true) {
            if (($plan['soft_mask_applied_before_rgb'] ?? false) !== true) {
                throw new InvalidArgumentException('ICCBased image stream preview requires a grayscale soft-mask image.');
            }
            if (is_int($softMask['width'] ?? null) && $softMask['width'] !== $width) {
                throw new InvalidArgumentException('ICCBased soft-mask width must match the image width.');
            }
            if (is_int($softMask['height'] ?? null) && $softMask['height'] !== $height) {
                throw new InvalidArgumentException('ICCBased soft-mask height must match the image height.');
            }
            if ($softMaskIsTransparencyGroup && !$imageStreamReviewOnly) {
                throw new InvalidArgumentException('ICCBased image stream preview requires sampled soft-mask alpha for transparency groups.');
            }

            if (!$softMaskIsTransparencyGroup) {
                $softMaskStream = $this->decodedSoftMaskStreamPreviewBoundary($dictionary, $objects);
                $softMaskStreamMeta = is_array($softMaskStream) ? $this->streamBoundaryPublicMetadata($softMaskStream) : null;
                if (is_array($softMaskStream) && ($softMaskStream['decoded_with_current_filters'] ?? false) === true && is_string($softMaskStream['decoded_bytes'] ?? null)) {
                    $softMaskSamples = $this->packedImagePixelSamples(
                        $softMaskStream['decoded_bytes'],
                        1,
                        max(1, (int) ($softMask['bits_per_component'] ?? 8)),
                        $expectedPixelCount
                    );
                } elseif (!$imageStreamReviewOnly) {
                    throw new InvalidArgumentException('ICCBased soft-mask stream filters must be natively decoded before RGB preview.');
                }

                if (is_array($softMaskSamples) && !$softMaskSamples['complete']) {
                    throw new InvalidArgumentException('ICCBased soft-mask stream does not contain complete alpha sample data.');
                }
            }
        }

        $streamNotes = [
            $imageStreamMeta['filters'] === []
                ? 'iccbased_image_stream_unfiltered_samples_before_rgb_conversion'
                : 'iccbased_image_stream_filters_decoded_before_rgb_conversion',
        ];
        if (!$imageStreamMeta['decoded_with_current_filters'] && $this->imageStreamBoundaryIsReviewOnly($imageStreamMeta)) {
            $streamNotes[0] = 'iccbased_image_stream_preview_only_before_rgb_conversion';
        }
        if ($softMaskStreamMeta !== null) {
            $streamNotes[] = $softMaskStreamMeta['filters'] === []
                ? 'soft_mask_stream_unfiltered_samples_before_rgb_conversion'
                : 'soft_mask_stream_filters_decoded_before_rgb_conversion';
        } elseif ($softMaskIsTransparencyGroup && $imageStreamReviewOnly) {
            $streamNotes[] = 'soft_mask_transfer_function_reviewed_without_raster_samples';
        }

        if (!$imageStreamDecoded) {
            return [
                'source_color_space' => (string) $plan['source_color_space'],
                'width' => $width,
                'height' => $height,
                'components_per_pixel' => $components,
                'bits_per_component' => $bitsPerComponent,
                'expected_pixel_count' => $expectedPixelCount,
                'preview_pixel_count' => 0,
                'review_only_image_stream' => true,
                'complete_image_sample_data' => false,
                'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
                'image_filter_boundary' => $plan['image_filter_boundary'],
                'image_filter_details' => $plan['image_filter_details'],
                'image_stream' => $imageStreamMeta,
                'soft_mask_stream' => $softMaskStreamMeta,
                'soft_mask' => $softMask,
                'soft_mask_group' => $softMaskGroup,
                'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
                'icc_profile' => $plan['icc_profile'],
                'image_decode' => $plan['image_decode'],
                'soft_mask_transfer_function' => $plan['soft_mask_transfer_function'],
                'soft_mask_transfer_function_applied_before_rgb' => $plan['soft_mask_transfer_function_applied_before_rgb'],
                'pixels' => [],
                'stream_notes' => $streamNotes,
                'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
                'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
                'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
            ];
        }

        $imageSamples = $this->packedImagePixelSamples(
            $imageStream['decoded_bytes'],
            $components,
            $bitsPerComponent,
            $expectedPixelCount
        );
        if (!$imageSamples['complete']) {
            throw new InvalidArgumentException('ICCBased image stream does not contain complete image sample data.');
        }
        if (is_array($softMask) && ($softMask['present'] ?? false) === true && !$softMaskIsTransparencyGroup && $softMaskSamples === null) {
            throw new InvalidArgumentException('ICCBased soft-mask stream filters must be natively decoded before RGB preview.');
        }

        $limit = min($maxPixels, $expectedPixelCount);
        $pixels = [];
        for ($index = 0; $index < $limit; $index++) {
            $rawSample = $imageSamples['pixels'][$index];
            $softMaskSample = is_array($softMaskSamples) ? $softMaskSamples['pixels'][$index][0] : null;
            $preview = $this->iccBasedSamplePreview($rawSample, $plan, $softMaskSample);
            $pixels[] = [
                'pixel_index' => $index,
                'x' => $index % $width,
                'y' => intdiv($index, $width),
                'raw_sample' => $rawSample,
                'decoded_components' => $preview['decoded_components'],
                'decode_source' => $preview['decode_source'],
                'uses_profile_range_decode' => $preview['uses_profile_range_decode'],
                'soft_mask_sample' => $softMaskSample,
                'soft_mask_alpha' => $preview['soft_mask_alpha'],
                'soft_mask_alpha_before_transfer' => $preview['soft_mask_alpha_before_transfer'],
                'soft_mask_transfer_applied' => $preview['soft_mask_transfer_applied'],
            ];
        }

        return [
            'source_color_space' => (string) $plan['source_color_space'],
            'width' => $width,
            'height' => $height,
            'components_per_pixel' => $components,
            'bits_per_component' => $bitsPerComponent,
            'expected_pixel_count' => $expectedPixelCount,
            'preview_pixel_count' => count($pixels),
            'review_only_image_stream' => false,
            'complete_image_sample_data' => $imageSamples['complete'],
            'complete_soft_mask_sample_data' => $softMaskSamples === null ? null : $softMaskSamples['complete'],
            'image_filter_boundary' => $plan['image_filter_boundary'],
            'image_filter_details' => $plan['image_filter_details'],
            'image_stream' => $imageStreamMeta,
            'soft_mask_stream' => $softMaskStreamMeta,
            'soft_mask' => $softMask,
            'soft_mask_group' => $softMaskGroup,
            'soft_mask_filter_boundary' => $plan['soft_mask_filter_boundary'],
            'icc_profile' => $plan['icc_profile'],
            'image_decode' => $plan['image_decode'],
            'soft_mask_transfer_function' => $plan['soft_mask_transfer_function'],
            'soft_mask_transfer_function_applied_before_rgb' => $plan['soft_mask_transfer_function_applied_before_rgb'],
            'pixels' => $pixels,
            'stream_notes' => $streamNotes,
            'notes' => array_values(array_unique(array_merge($plan['notes'] ?? [], $streamNotes))),
            'output_color_mode' => (string) ($plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
        ];
    }

    /**
     * @param array<string, mixed> $documentMetadata
     * @return array{present: bool, source: string, output_condition_identifiers: list<string>, profile_sha256: list<string>, profile_count: int, review_only: true, payload_included: false}
     */
    private function pdfaOutputIntentReviewMetadata(array $documentMetadata): array
    {
        $pdfa = is_array($documentMetadata['pdfa'] ?? null)
            ? $documentMetadata['pdfa']
            : $documentMetadata;
        $identifiers = array_values(array_unique(array_filter(
            $pdfa['output_condition_identifiers'] ?? [],
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        )));
        $hashes = array_values(array_unique(array_filter(
            $pdfa['profile_sha256'] ?? [],
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        )));
        $present = ($pdfa['has_output_intent'] ?? false) === true || $identifiers !== [] || $hashes !== [];

        return [
            'present' => $present,
            'source' => $present ? 'document_metadata_pdfa_output_intents' : 'none',
            'output_condition_identifiers' => $identifiers,
            'profile_sha256' => $hashes,
            'profile_count' => count($hashes),
            'review_only' => true,
            'payload_included' => false,
        ];
    }

    /**
     * @param array<string, mixed> $imagePlan
     * @param array{present: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>} $pdfa
     * @return array{source_color_space: string, profile_source: string, pdfa_output_intent_present: bool, pdfa_output_intent_applies_to_rgb_preview: bool, image_uses_icc_profile: bool, image_uses_calibrated_color_space: bool, image_uses_alternate_color_space: bool, image_uses_indexed_color_space: bool, output_condition_identifiers: list<string>, profile_sha256: list<string>, review_only: true}
     */
    private function imagePdfaColorManagementReview(array $imagePlan, array $pdfa): array
    {
        $profileSource = $this->imagePdfaProfileSource($imagePlan, $pdfa);
        $pdfaApplies = str_starts_with($profileSource, 'pdfa_output_intent');

        return [
            'source_color_space' => (string) ($imagePlan['source_color_space'] ?? 'DeviceRGB'),
            'profile_source' => $profileSource,
            'pdfa_output_intent_present' => $pdfa['present'],
            'pdfa_output_intent_applies_to_rgb_preview' => $pdfaApplies,
            'image_uses_icc_profile' => ($imagePlan['uses_icc_profile'] ?? false) === true,
            'image_uses_calibrated_color_space' => ($imagePlan['uses_calibrated_color_space'] ?? false) === true,
            'image_uses_alternate_color_space' => ($imagePlan['uses_alternate_color_space'] ?? false) === true,
            'image_uses_indexed_color_space' => ($imagePlan['uses_indexed_color_space'] ?? false) === true,
            'output_condition_identifiers' => $pdfa['output_condition_identifiers'],
            'profile_sha256' => $pdfa['profile_sha256'],
            'review_only' => true,
        ];
    }

    /**
     * @param array<string, mixed> $imagePlan
     * @param array{present: bool} $pdfa
     */
    private function imagePdfaProfileSource(array $imagePlan, array $pdfa): string
    {
        if (!$pdfa['present']) {
            return 'image_color_space';
        }

        if (($imagePlan['uses_icc_profile'] ?? false) === true) {
            if (($imagePlan['uses_indexed_color_space'] ?? false) === true) {
                return 'image_indexed_base_icc_profile';
            }
            if (($imagePlan['uses_alternate_color_space'] ?? false) === true) {
                return 'image_alternate_icc_profile';
            }

            return 'image_icc_profile';
        }

        if (($imagePlan['uses_calibrated_color_space'] ?? false) === true) {
            return 'image_calibrated_color_space';
        }

        if (($imagePlan['uses_indexed_color_space'] ?? false) === true && is_array($imagePlan['indexed_color_space'] ?? null)) {
            $base = $imagePlan['indexed_color_space']['base_color_space'] ?? null;

            return is_string($base) && $this->pdfaOutputIntentCanProfileDeviceSpace($base)
                ? 'pdfa_output_intent_for_indexed_base_color_space'
                : 'image_indexed_color_space';
        }

        if (($imagePlan['uses_alternate_color_space'] ?? false) === true && is_array($imagePlan['alternate_color_space'] ?? null)) {
            $alternate = $imagePlan['alternate_color_space']['alternate_color_space'] ?? null;

            return is_string($alternate) && $this->pdfaOutputIntentCanProfileDeviceSpace($alternate)
                ? 'pdfa_output_intent_for_alternate_color_space'
                : 'image_alternate_color_space';
        }

        $source = (string) ($imagePlan['source_color_space'] ?? 'DeviceRGB');

        return $this->pdfaOutputIntentCanProfileDeviceSpace($source)
            ? 'pdfa_output_intent'
            : 'image_color_space';
    }

    private function pdfaOutputIntentCanProfileDeviceSpace(string $colorSpace): bool
    {
        return in_array($colorSpace, ['DeviceGray', 'DeviceRGB', 'DeviceCMYK'], true);
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function imageRenderingBundleSummary(string $selectedPreview, array $plan, array $preview): array
    {
        $imageStream = is_array($preview['image_stream'] ?? null) ? $preview['image_stream'] : null;
        $softMaskStream = is_array($preview['soft_mask_stream'] ?? null) ? $preview['soft_mask_stream'] : null;
        $softMaskBoundary = is_array($preview['soft_mask_filter_boundary'] ?? null)
            ? $preview['soft_mask_filter_boundary']
            : (is_array($plan['soft_mask_filter_boundary'] ?? null) ? $plan['soft_mask_filter_boundary'] : null);
        $softMask = is_array($preview['soft_mask'] ?? null)
            ? $preview['soft_mask']
            : (is_array($plan['soft_mask'] ?? null) ? $plan['soft_mask'] : null);
        $transferFunction = is_array($preview['soft_mask_transfer_function'] ?? null)
            ? $preview['soft_mask_transfer_function']
            : (is_array($plan['soft_mask_transfer_function'] ?? null) ? $plan['soft_mask_transfer_function'] : null);

        return [
            'source' => 'marker.pdf.images.render_image_rgb',
            'selected_preview' => $selectedPreview,
            'source_color_space' => (string) ($preview['source_color_space'] ?? $plan['source_color_space'] ?? 'DeviceRGB'),
            'color_space_resource_name' => $plan['color_space_resource_name'] ?? null,
            'color_space_resource_source' => $plan['color_space_resource_source'] ?? null,
            'color_space_resolved_from_resources' => ($plan['color_space_resolved_from_resources'] ?? false) === true,
            'image_stream_decoded' => $imageStream['decoded_with_current_filters'] ?? null,
            'image_stream_review_only' => ($preview['review_only_image_stream'] ?? false) === true,
            'soft_mask_present' => is_array($softMask) && ($softMask['present'] ?? false) === true,
            'soft_mask_source_object' => $softMaskBoundary['source_object'] ?? null,
            'soft_mask_uses_current_object_map' => $softMaskBoundary['uses_current_object_map'] ?? null,
            'soft_mask_stream_decoded' => $softMaskStream['decoded_with_current_filters']
                ?? ($softMaskBoundary['decoded_with_current_filters'] ?? null),
            'soft_mask_transfer_present' => is_array($transferFunction) && ($transferFunction['present'] ?? false) === true,
            'soft_mask_transfer_applied_before_rgb' => ($preview['soft_mask_transfer_function_applied_before_rgb'] ?? $plan['soft_mask_transfer_function_applied_before_rgb'] ?? false) === true,
            'soft_mask_transfer_sample_supported' => is_array($transferFunction) && ($transferFunction['sample_supported'] ?? false) === true,
            'output_color_mode' => (string) ($preview['output_color_mode'] ?? $plan['output_color_mode'] ?? 'RGB'),
            'alpha_output_mode' => (string) ($preview['alpha_output_mode'] ?? $plan['alpha_output_mode'] ?? 'opaque_rgb_preview'),
            'executes_python_or_models' => false,
            'executes_pypdfium_or_pil' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $preview
     * @return list<string>
     */
    private function imageRenderingBundleNotes(string $selectedPreview, array $plan, array $preview): array
    {
        $summary = is_array($preview['render_bundle'] ?? null) ? $preview['render_bundle'] : [];
        $notes = array_merge(
            is_array($plan['notes'] ?? null) ? $plan['notes'] : [],
            is_array($preview['notes'] ?? null) ? $preview['notes'] : [],
            [
                'image_rendering_colorspace_softmask_transfer_bundle_currentbase',
                'image_rendering_bundle_dispatches_' . $selectedPreview . '_preview',
            ]
        );

        if (($summary['color_space_resolved_from_resources'] ?? false) === true) {
            $notes[] = 'image_rendering_bundle_resolves_current_color_space_resource';
        }
        if (($summary['image_stream_decoded'] ?? false) === true) {
            $notes[] = 'image_rendering_bundle_decodes_image_stream_before_rgb_conversion';
        } elseif (($summary['image_stream_review_only'] ?? false) === true) {
            $notes[] = 'image_rendering_bundle_keeps_preview_only_image_stream_review_only';
        }
        if (($summary['soft_mask_present'] ?? false) === true) {
            $notes[] = 'image_rendering_bundle_preserves_soft_mask_before_rgb_conversion';
        }
        if (($summary['soft_mask_stream_decoded'] ?? false) === true) {
            $notes[] = 'image_rendering_bundle_decodes_soft_mask_stream_before_rgb_conversion';
        }
        if (($summary['soft_mask_transfer_present'] ?? false) === true) {
            $notes[] = 'image_rendering_bundle_preserves_soft_mask_transfer_function';
        }
        if (($summary['soft_mask_transfer_applied_before_rgb'] ?? false) === true) {
            $notes[] = 'image_rendering_bundle_applies_soft_mask_transfer_before_rgb_conversion';
        }

        return array_values(array_unique($notes));
    }

    /**
     * @param array{width?: int|float, height?: int|float}|list<int|float> $renderedImageSize
     * @return list<float>
     */
    private function renderedImageBbox(array $renderedImageSize): array
    {
        $width = $renderedImageSize['width'] ?? $renderedImageSize[0] ?? null;
        $height = $renderedImageSize['height'] ?? $renderedImageSize[1] ?? null;

        if ((!is_int($width) && !is_float($width)) || (!is_int($height) && !is_float($height))) {
            throw new InvalidArgumentException('Rendered image size must include numeric width and height.');
        }
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Rendered image width and height must be greater than zero.');
        }

        return [0.0, 0.0, (float) $width, (float) $height];
    }

    private function canonicalInlineImageDictionary(string $dictionary): string
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<') && str_ends_with($body, '>>')) {
            $body = trim(substr($body, 2, -2));
        }

        $entries = [];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $offset = $this->skipPdfWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $readKey = $this->readPdfValueWithOffset($body, $offset);
            if ($readKey === null || !str_starts_with(trim($readKey['value']), '/')) {
                break;
            }

            $readValue = $this->readPdfValueWithOffset($body, $readKey['next']);
            if ($readValue === null) {
                break;
            }

            $canonicalKey = $this->canonicalInlineImageKey($readKey['value']);
            $value = $readValue['value'];
            $nextOffset = $readValue['next'];
            if ($canonicalKey === '/Filter') {
                $extraOperandEnd = $this->imageDirectFilterExtraOperandEndAfterValue($body, $readValue['next']);
                if ($extraOperandEnd !== null) {
                    $valueStart = $this->skipPdfWhitespace($body, $readKey['next']);
                    $value = substr($body, $valueStart, $extraOperandEnd - $valueStart);
                    $nextOffset = $extraOperandEnd;
                }
            } elseif ($canonicalKey === '/Decode') {
                $extraOperandEnd = $this->inlineImageDecodeExtraOperandEndAfterValue($body, $readValue['next']);
                if ($extraOperandEnd !== null) {
                    $nextOffset = $extraOperandEnd;
                }
            } elseif ($canonicalKey === '/DecodeParms') {
                $extraOperandEnd = $this->imageDecodeParmsExtraOperandEndAfterValue($body, $readValue['next']);
                if ($extraOperandEnd !== null) {
                    $valueStart = $this->skipPdfWhitespace($body, $readKey['next']);
                    $value = substr($body, $valueStart, $extraOperandEnd - $valueStart);
                    $nextOffset = $extraOperandEnd;
                }
            }

            $entries[] = $canonicalKey
                . ' '
                . $this->canonicalInlineImageValue($value, $canonicalKey);
            $offset = $nextOffset;
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @param array<int|string, mixed> $objects
     * @return list<string|null>
     */
    private function inlineImageSourceFilterValues(string $dictionary, array $objects): array
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $read = $this->readBalancedDictionary($body, 0);
            if ($read !== null) {
                $body = trim(substr($read['value'], 2, -2));
            }
        }

        $values = [];
        $hasMalformedExtraOperand = false;
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $offset = $this->skipPdfWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $readKey = $this->readPdfValueWithOffset($body, $offset);
            if ($readKey === null || !str_starts_with(trim($readKey['value']), '/')) {
                break;
            }

            $readValue = $this->readPdfValueWithOffset($body, $readKey['next']);
            if ($readValue === null) {
                break;
            }

            $canonicalKey = $this->canonicalInlineImageKey($readKey['value']);
            $value = $readValue['value'];
            $nextOffset = $readValue['next'];
            if ($canonicalKey === '/Filter') {
                $extraOperandEnd = $this->imageDirectFilterExtraOperandEndAfterValue($body, $readValue['next']);
                if ($extraOperandEnd !== null) {
                    $valueStart = $this->skipPdfWhitespace($body, $readKey['next']);
                    $value = substr($body, $valueStart, $extraOperandEnd - $valueStart);
                    $nextOffset = $extraOperandEnd;
                    $hasMalformedExtraOperand = true;
                }
                $values[] = $value;
            }

            $offset = $nextOffset;
        }

        if ($values === []) {
            return [];
        }

        if (count($values) > 1) {
            $filters = [self::MALFORMED_IMAGE_FILTER_OPERAND];
            foreach ($values as $value) {
                foreach ($this->imageFilterValuesFromValue($value, $objects) as $filter) {
                    $filters[] = $filter;
                }
            }

            return $filters;
        }

        $filters = $this->imageFilterValuesFromValue($values[0], $objects);
        if ($hasMalformedExtraOperand && !in_array(self::MALFORMED_IMAGE_FILTER_OPERAND, $filters, true)) {
            array_unshift($filters, self::MALFORMED_IMAGE_FILTER_OPERAND);
        }

        return $filters;
    }

    /**
     * @param list<string|null> $filters
     * @return list<string>
     */
    private function publicInlineImageSourceFilterList(array $filters): array
    {
        return array_values(array_filter(
            $filters,
            static fn (?string $filter): bool => is_string($filter)
        ));
    }

    /**
     * @param list<string|null> $sourceFilters
     * @param list<string|null> $canonicalFilters
     * @return list<array{index: int, source: string, canonical: string}>
     */
    private function inlineImageSourceFilterAliasRows(array $sourceFilters, array $canonicalFilters): array
    {
        $rows = [];
        foreach ($sourceFilters as $index => $sourceFilter) {
            if (!is_string($sourceFilter)) {
                continue;
            }

            $canonical = $this->canonicalInlineImageFilterName($sourceFilter);
            if ($canonical === $sourceFilter) {
                continue;
            }

            $rows[] = [
                'index' => $index,
                'source' => $sourceFilter,
                'canonical' => is_string($canonicalFilters[$index] ?? null)
                    ? (string) $canonicalFilters[$index]
                    : $canonical,
            ];
        }

        return $rows;
    }

    private function inlineImageDecodeHasMalformedTrailingOperand(string $dictionary): bool
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $read = $this->readBalancedDictionary($body, 0);
            if ($read !== null) {
                $body = trim(substr($read['value'], 2, -2));
            }
        }

        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $key = $this->readPdfValueWithOffset($body, $offset);
            if ($key === null || !str_starts_with(trim($key['value']), '/')) {
                break;
            }

            $value = $this->readPdfValueWithOffset($body, $key['next']);
            if ($value === null) {
                break;
            }

            $keyName = $this->pdfNameValue($key['value']);
            $canonicalKey = $keyName === null
                ? null
                : (self::INLINE_IMAGE_KEY_ABBREVIATIONS[$keyName] ?? $keyName);
            if ($canonicalKey === 'Decode') {
                $tailOffset = $this->skipPdfWhitespace($body, $value['next']);
                if (
                    $tailOffset < $length
                    && substr($body, $tailOffset, 2) !== '>>'
                    && ($body[$tailOffset] ?? '') !== '/'
                ) {
                    return true;
                }
            }
            $offset = $this->pdfDictionaryScanOffsetAfterValue($body, $key['value'], $value['next']);
        }

        return false;
    }

    private function inlineImageDecodeExtraOperandEndAfterValue(string $body, int $offset): ?int
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        if ($offset >= strlen($body) || substr($body, $offset, 2) === '>>' || ($body[$offset] ?? '') === ']') {
            return null;
        }

        if (($body[$offset] ?? '') === '/') {
            return null;
        }

        $extra = $this->readPdfValueWithOffset($body, $offset);
        if ($extra === null || $extra['next'] <= $offset) {
            $end = $offset + strcspn($body, " \t\r\n\f[]()<>{}/%", $offset);

            return $end > $offset ? $end : $offset + 1;
        }

        $afterExtra = $this->skipPdfWhitespace($body, $extra['next']);
        if (preg_match('/\G[+-]?\d+\s+R\b/s', $body, $match, 0, $afterExtra) === 1) {
            return $afterExtra + strlen($match[0]);
        }

        return $extra['next'];
    }

    private function inlineImageDictionaryHasMalformedTailOperand(string $dictionary): bool
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $read = $this->readBalancedDictionary($body, 0);
            if ($read !== null) {
                $body = trim(substr($read['value'], 2, -2));
            }
        }

        $offset = 0;
        $length = strlen($body);
        $sawImageKey = false;
        while ($offset < $length) {
            $key = $this->readPdfValueWithOffset($body, $offset);
            if ($key === null) {
                $tail = $this->inlineImageMalformedClosingDelimiterTailTokenAt($body, $offset);
                if ($tail !== null) {
                    return $sawImageKey;
                }

                $tail = $this->inlineImageBareTailTokenAt($body, $offset);

                return $tail !== null && $sawImageKey && $this->inlineImageMalformedDictionaryTailOperandToken($tail);
            }

            $keyValue = trim($key['value']);
            if (!str_starts_with($keyValue, '/')) {
                return $sawImageKey && $this->inlineImageMalformedDictionaryTailOperandToken($keyValue);
            }

            $keyName = $this->pdfNameValue($keyValue);
            $canonicalKey = $keyName === null
                ? null
                : (self::INLINE_IMAGE_KEY_ABBREVIATIONS[$keyName] ?? $keyName);
            if (in_array($canonicalKey, [
                'Width',
                'Height',
                'ColorSpace',
                'BitsPerComponent',
                'ImageMask',
                'Filter',
                'Decode',
                'DecodeParms',
            ], true)) {
                $sawImageKey = true;
            }

            $value = $this->readPdfValueWithOffset($body, $key['next']);
            if ($value === null) {
                return $sawImageKey;
            }
            if (
                $canonicalKey === 'DecodeParms'
                && $this->imageDecodeParmsExtraOperandEndAfterValue($body, $value['next']) !== null
            ) {
                return true;
            }

            $offset = $this->pdfDictionaryScanOffsetAfterValue($body, $key['value'], $value['next']);
        }

        return false;
    }

    private function inlineImageMalformedClosingDelimiterTailTokenAt(string $body, int $offset): ?string
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === ']') {
            return ']';
        }

        if ($body[$offset] === '>' && $offset + 1 < strlen($body) && $body[$offset + 1] === '>') {
            return '>>';
        }

        return null;
    }

    private function inlineImageBareTailTokenAt(string $body, int $offset): ?string
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        $length = strcspn($body, " \t\r\n\f[]()<>{}/%", $offset);
        if ($length <= 0) {
            return null;
        }

        return substr($body, $offset, $length);
    }

    private function inlineImageMalformedDictionaryTailOperandToken(string $token): bool
    {
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $token) === 1) {
            return true;
        }

        return $token === 'R'
            || $token === 'null'
            || $token === 'true'
            || $token === 'false'
            || str_starts_with($token, '[')
            || str_starts_with($token, '<<')
            || str_starts_with($token, '(')
            || (str_starts_with($token, '<') && !str_starts_with($token, '<<'))
            || $this->inlineImageMalformedDictionaryBareTailOperandToken($token);
    }

    private function inlineImageMalformedDictionaryBareTailOperandToken(string $token): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $token) === 1
            && !in_array($token, ['BI', 'ID', 'EI'], true);
    }

    private function canonicalInlineImageKey(string $token): string
    {
        $name = $this->pdfNameValue($token);
        if ($name === null) {
            return $token;
        }

        return '/' . (self::INLINE_IMAGE_KEY_ABBREVIATIONS[$name] ?? $name);
    }

    private function canonicalInlineImageValue(string $token, ?string $canonicalKey = null): string
    {
        $trimmed = trim($token);
        if ($canonicalKey === '/Decode') {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '/')) {
            $name = $this->pdfNameValue($trimmed);
            return $name === null ? $trimmed : '/' . (self::INLINE_IMAGE_VALUE_ABBREVIATIONS[$name] ?? $name);
        }

        if (!str_starts_with($trimmed, '[')) {
            return $trimmed;
        }

        if ($canonicalKey === '/ColorSpace') {
            return $this->canonicalInlineImageColorSpaceArrayValue($trimmed);
        }

        return $this->canonicalInlineImageArrayValue($trimmed);
    }

    private function canonicalInlineImageFilterName(string $filter): string
    {
        return self::INLINE_IMAGE_VALUE_ABBREVIATIONS[$filter] ?? $filter;
    }

    private function canonicalInlineImageColorSpaceArrayValue(string $value): string
    {
        $array = $this->readBalancedArray($value, 0);
        if ($array === null) {
            return $this->canonicalInlineImageArrayValue($value);
        }

        $items = $this->pdfArrayValues($array['value']);
        if ($items === []) {
            return $value;
        }

        $family = $this->canonicalInlineImageColorSpaceValue($items[0]);
        $canonical = [$family];
        if ($family === '/Indexed') {
            if (isset($items[1])) {
                $canonical[] = $this->canonicalInlineImageColorSpaceValue($items[1]);
            }
            for ($index = 2, $count = count($items); $index < $count; $index++) {
                $canonical[] = $items[$index];
            }

            return '[' . implode(' ', $canonical) . ']';
        }

        if ($family === '/Separation') {
            if (isset($items[1])) {
                $canonical[] = $items[1];
            }
            if (isset($items[2])) {
                $canonical[] = $this->canonicalInlineImageColorSpaceValue($items[2]);
            }
            for ($index = 3, $count = count($items); $index < $count; $index++) {
                $canonical[] = $items[$index];
            }

            return '[' . implode(' ', $canonical) . ']';
        }

        if ($family === '/DeviceN') {
            if (isset($items[1])) {
                $canonical[] = $items[1];
            }
            if (isset($items[2])) {
                $canonical[] = $this->canonicalInlineImageColorSpaceValue($items[2]);
            }
            for ($index = 3, $count = count($items); $index < $count; $index++) {
                $canonical[] = $items[$index];
            }

            return '[' . implode(' ', $canonical) . ']';
        }

        for ($index = 1, $count = count($items); $index < $count; $index++) {
            $canonical[] = $items[$index];
        }

        return '[' . implode(' ', $canonical) . ']';
    }

    private function canonicalInlineImageColorSpaceValue(string $token): string
    {
        $trimmed = trim($token);
        if (str_starts_with($trimmed, '/')) {
            $name = $this->pdfNameValue($trimmed);
            return $name === null ? $trimmed : '/' . (self::INLINE_IMAGE_VALUE_ABBREVIATIONS[$name] ?? $name);
        }

        if (str_starts_with($trimmed, '[')) {
            return $this->canonicalInlineImageColorSpaceArrayValue($trimmed);
        }

        return $trimmed;
    }

    private function canonicalInlineImageArrayValue(string $value): string
    {
        $canonical = '';
        $index = 0;
        $length = strlen($value);

        while ($index < $length) {
            $char = $value[$index];
            if ($char === '(') {
                $next = $this->skipPdfLiteralString($value, $index);
                $canonical .= substr($value, $index, $next - $index);
                $index = $next;
                continue;
            }

            if ($char === '%') {
                $next = $index + strcspn($value, "\r\n", $index);
                $canonical .= substr($value, $index, $next - $index);
                $index = $next;
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $dictionary = $this->readBalancedDictionary($value, $index);
                if ($dictionary !== null) {
                    $canonical .= $dictionary['value'];
                    $index = $dictionary['next'];
                    continue;
                }
            }

            if ($char === '<') {
                $next = strpos($value, '>', $index + 1);
                if ($next === false) {
                    $canonical .= substr($value, $index);
                    break;
                }

                $canonical .= substr($value, $index, $next - $index + 1);
                $index = $next + 1;
                continue;
            }

            if ($char === '/') {
                $next = $this->pdfNameTokenEndOffset($value, $index);
                $name = $this->decodePdfName(substr($value, $index + 1, $next - $index - 1));
                $canonical .= '/' . (self::INLINE_IMAGE_VALUE_ABBREVIATIONS[$name] ?? $name);
                $index = $next;
                continue;
            }

            $canonical .= $char;
            $index++;
        }

        return $canonical;
    }

    private function imageFilterName(string $dictionary): ?string
    {
        if (preg_match('/\/Filter\s+(?:\/([^\s\[\]()<>{}\/%]+)|\[\s*\/([^\s\[\]()<>{}\/%]+))/s', $dictionary, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1] !== '' ? $match[1] : $match[2]);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function imageFilterNames(string $dictionary, array $objects): array
    {
        return array_values(array_filter(
            $this->imageFilterValues($dictionary, $objects),
            static fn (?string $filter): bool => is_string($filter)
        ));
    }

    /**
     * @param list<string> $filters
     * @return list<string>
     */
    private function publicImageFilterList(array $filters): array
    {
        return array_values(array_map(
            static fn (string $filter): string => $filter === 'DCT' ? 'DCTDecode' : $filter,
            $filters
        ));
    }

    private function canonicalNativeImageStreamFilterName(string $filter): string
    {
        return match ($filter) {
            'AHx' => 'ASCIIHexDecode',
            'A85' => 'ASCII85Decode',
            'RL' => 'RunLengthDecode',
            'Fl' => 'FlateDecode',
            'LZW' => 'LZWDecode',
            default => $filter,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return list<string|null>
     */
    private function imageFilterValues(string $dictionary, array $objects): array
    {
        $values = $this->pdfDictionaryValuesForName($dictionary, 'Filter');
        if ($values === []) {
            return [];
        }

        $hasMalformedExtraOperand = $this->imageFilterHasMalformedExtraOperand($dictionary);
        if (count($values) > 1) {
            $filters = [self::MALFORMED_IMAGE_FILTER_OPERAND];
            foreach ($values as $value) {
                foreach ($this->imageFilterValuesFromValue($value, $objects) as $filter) {
                    $filters[] = $filter;
                }
            }

            return $filters;
        }

        $filters = $this->imageFilterValuesFromValue($values[0], $objects);
        if ($hasMalformedExtraOperand && !in_array(self::MALFORMED_IMAGE_FILTER_OPERAND, $filters, true)) {
            array_unshift($filters, self::MALFORMED_IMAGE_FILTER_OPERAND);
        }

        return $filters;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string|null>
     */
    private function imageFilterValuesFromValue(string $value, array $objects): array
    {
        $resolved = trim($this->resolvePdfValue($value, $objects));
        if (str_starts_with($resolved, '[')) {
            $array = $this->readBalancedArray($resolved, 0);
            if ($array === null) {
                return [self::MALFORMED_IMAGE_FILTER_OPERAND];
            }

            $filters = [];
            foreach ($this->imageFilterArrayValues($array['value']) as $entry) {
                $entry = trim($this->resolvePdfValue($entry, $objects));
                if ($entry === 'null') {
                    $filters[] = null;
                    continue;
                }

                if (str_starts_with($entry, '/')) {
                    $end = $this->pdfNameTokenEndOffset($entry, 0);
                    $name = $this->decodePdfName(substr($entry, 1, $end - 1));
                    if (
                        $this->skipPdfWhitespace($entry, $end) !== strlen($entry)
                        && !in_array(self::MALFORMED_IMAGE_FILTER_OPERAND, $filters, true)
                    ) {
                        $filters[] = self::MALFORMED_IMAGE_FILTER_OPERAND;
                    }
                    $filters[] = $name;
                    continue;
                }

                $filters[] = $this->imageFilterOperandFallbackName($entry);
            }

            if (
                $this->skipPdfWhitespace($resolved, $array['next']) !== strlen($resolved)
                && !in_array(self::MALFORMED_IMAGE_FILTER_OPERAND, $filters, true)
            ) {
                array_unshift($filters, self::MALFORMED_IMAGE_FILTER_OPERAND);
            }

            return $filters;
        }

        $name = $this->pdfNameValue($resolved);
        if ($name === null && $resolved === 'null') {
            return [];
        }

        if (str_starts_with($resolved, '/')) {
            $end = $this->pdfNameTokenEndOffset($resolved, 0);
            $name = $this->decodePdfName(substr($resolved, 1, $end - 1));
            if ($this->skipPdfWhitespace($resolved, $end) !== strlen($resolved)) {
                return [self::MALFORMED_IMAGE_FILTER_OPERAND, $name];
            }

            return [$name];
        }

        return $name === null ? [$this->imageFilterOperandFallbackName($resolved)] : [$name];
    }

    /**
     * @return list<string>
     */
    private function imageFilterArrayValues(string $array): array
    {
        $body = trim($array);
        if (!str_starts_with($body, '[') || !str_ends_with($body, ']')) {
            return [];
        }

        $values = [];
        $offset = 1;
        $end = strlen($body) - 1;
        while ($offset < $end) {
            $read = $this->readPdfValueWithOffset($body, $offset);
            if ($read !== null && $read['next'] > $offset) {
                $values[] = $read['value'];
                $offset = $read['next'];
                continue;
            }

            $malformed = $this->readMalformedImageFilterArrayOperand($body, $offset, $end);
            if ($malformed === null || $malformed['next'] <= $offset) {
                break;
            }

            $values[] = $malformed['value'];
            $offset = $malformed['next'];
        }

        return $values;
    }

    /**
     * @return array{value: string, next: int}|null
     */
    private function readMalformedImageFilterArrayOperand(string $source, int $offset, int $end): ?array
    {
        $offset = $this->skipPdfWhitespace($source, $offset);
        if ($offset >= $end || $source[$offset] === ']') {
            return null;
        }

        $start = $offset;
        while ($offset < $end && !$this->isPdfBareTokenDelimiter($source[$offset])) {
            $offset++;
        }
        if ($offset === $start) {
            $offset++;
        }

        return [
            'value' => substr($source, $start, $offset - $start),
            'next' => $offset,
        ];
    }

    private function imageFilterOperandFallbackName(string $resolved): string
    {
        return preg_match('/^\d+\s+\d+\s+R$/', trim($resolved)) === 1
            ? self::UNRESOLVED_IMAGE_FILTER_OPERAND
            : self::MALFORMED_IMAGE_FILTER_OPERAND;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null|list<int>|list<string>>|null}>
     */
    private function imageFilterDetails(string $dictionary, array $objects): array
    {
        $filters = $this->imageFilterValues($dictionary, $objects);
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        $details = [];

        foreach ($filters as $index => $filter) {
            if (!is_string($filter)) {
                continue;
            }

            $decodeParmsIndex = $this->decodeParmsIndexForImageFilterIndex($filters, $decodeParms, $index);
            $decodeParmsValue = $decodeParmsIndex === null ? null : ($decodeParms[$decodeParmsIndex] ?? null);
            $details[] = [
                'filter' => $filter,
                'preview_only' => $this->isPreviewOnlyImageFilter($filter),
                'decode_parms' => $this->dctDecodeDuplicateDecodeParmsDeclarationReview($filter, $dictionary, $decodeParmsValue, $objects)
                    ?? $this->ccittFaxDuplicateDecodeParmsDeclarationReview($filter, $dictionary)
                    ?? $this->nativeImageDuplicateDecodeParmsDeclarationReview($filter, $dictionary)
                    ?? $this->dctDecodeUnappliedDecodeParmsReview($filter, $filters, $decodeParms)
                    ?? $this->ccittFaxUnappliedDecodeParmsReview($filter, $filters, $decodeParms)
                    ?? $this->dctDecodeParmsOperandFailureReview($filter, $decodeParmsValue, $objects)
                    ?? $this->imageFilterDecodeParms($filter, $decodeParmsValue, $objects)
                    ?? $this->nativeImageUnappliedDecodeParmsReview($filter, $filters, $decodeParms)
                    ?? $this->dctDecodeUnalignedDecodeParmsReview($filter, $filters, $decodeParms, $decodeParmsIndex)
                    ?? $this->ccittFaxUnalignedDecodeParmsReview($filter, $filters, $decodeParms, $decodeParmsIndex),
            ];
        }

        return $details;
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     */
    private function decodeParmsValueForImageFilterIndex(array $filters, array $decodeParms, int $index): ?string
    {
        $decodeParmsIndex = $this->decodeParmsIndexForImageFilterIndex($filters, $decodeParms, $index);

        return $decodeParmsIndex === null ? null : ($decodeParms[$decodeParmsIndex] ?? null);
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     */
    private function decodeParmsIndexForImageFilterIndex(array $filters, array $decodeParms, int $index): ?int
    {
        $nonNullFilterIndexes = [];
        foreach ($filters as $filterIndex => $filter) {
            if ($this->imageFilterCanCarryDecodeParms($filter)) {
                $nonNullFilterIndexes[] = $filterIndex;
            }
        }
        if (!$this->imageFilterCanCarryDecodeParms($filters[$index] ?? null)) {
            return null;
        }

        if ($this->decodeParmsUseCompactNonNullFilterIndexes($filters, count($decodeParms), $nonNullFilterIndexes)) {
            $compactPosition = array_search($index, $nonNullFilterIndexes, true);
            if ($compactPosition !== false) {
                $decodeParmsIndexes = array_keys($decodeParms);
                $decodeParmsIndex = $decodeParmsIndexes[$compactPosition] ?? null;

                return is_int($decodeParmsIndex) ? $decodeParmsIndex : null;
            }
        }

        if (array_key_exists($index, $decodeParms)) {
            return $index;
        }

        if (count($decodeParms) !== 1 || $nonNullFilterIndexes !== [$index]) {
            return null;
        }

        $decodeParmsIndex = array_key_first($decodeParms);

        return is_int($decodeParmsIndex) ? $decodeParmsIndex : null;
    }

    /**
     * @param list<string|null> $filters
     * @param list<int>|null $nonNullFilterIndexes
     */
    private function decodeParmsUseCompactNonNullFilterIndexes(
        array $filters,
        int $decodeParmsCount,
        ?array $nonNullFilterIndexes = null
    ): bool {
        if ($nonNullFilterIndexes === null) {
            $nonNullFilterIndexes = [];
            foreach ($filters as $filterIndex => $filter) {
                if ($this->imageFilterCanCarryDecodeParms($filter)) {
                    $nonNullFilterIndexes[] = $filterIndex;
                }
            }
        }

        return $decodeParmsCount === count($nonNullFilterIndexes)
            && count($filters) !== $decodeParmsCount;
    }

    private function imageFilterCanCarryDecodeParms(?string $filter): bool
    {
        return is_string($filter)
            && !in_array($filter, [self::MALFORMED_IMAGE_FILTER_OPERAND, self::UNRESOLVED_IMAGE_FILTER_OPERAND], true);
    }

    private function isPreviewOnlyImageFilter(string $filter): bool
    {
        return in_array($filter, ['DCTDecode', 'DCT', 'JPXDecode', 'JBIG2Decode', 'CCITTFaxDecode', 'CCF'], true);
    }

    /**
     * @param list<string|null> $filters
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function unsupportedInlineImageFilters(array $filters, string $dictionary, array $objects): array
    {
        $unsupported = [];
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        $duplicateDecodeParmsDeclarations = $this->duplicatePdfNameDeclarationCount($dictionary, 'DecodeParms') > 0;
        $unappliedDecodeParmsSlots = $this->unappliedNonNullDecodeParmsSlots($filters, $decodeParms);
        foreach ($filters as $index => $filter) {
            if ($filter === null) {
                continue;
            }

            $decodeParmsValue = $this->decodeParmsValueForImageFilterIndex($filters, $decodeParms, $index);
            $resolvedDecodeParms = $this->resolvedDecodeParmsDictionary($decodeParmsValue, $objects);

            if (
                $this->isPreviewOnlyStreamFilter($filter)
                || in_array($filter, [self::MALFORMED_IMAGE_FILTER_OPERAND, self::UNRESOLVED_IMAGE_FILTER_OPERAND], true)
            ) {
                continue;
            }

            if ($this->isNativeImageStreamFilter($filter)) {
                if (
                    $duplicateDecodeParmsDeclarations
                    || $this->nativeImageDuplicateDecodeParmsDeclarationReview($filter, $dictionary) !== null
                    || $unappliedDecodeParmsSlots !== []
                    || $this->imageDecodeParmsValueIsMalformed($decodeParmsValue, $objects)
                    || !$this->canApplyImageDecodeParms($filter, $resolvedDecodeParms, $objects)
                ) {
                    $unsupported[] = $filter;
                }

                continue;
            }

            if ($filter === 'Crypt' && $this->cryptIdentityFilterIsSupported($resolvedDecodeParms, $objects)) {
                continue;
            }

            $unsupported[] = $filter;
        }

        return $unsupported;
    }

    /**
     * @param list<string> $filters
     * @return list<string>
     */
    private function imageFilterOperandBoundaryFilters(array $filters): array
    {
        return array_values(array_filter(
            $filters,
            static fn (string $filter): bool => in_array(
                $filter,
                [self::MALFORMED_IMAGE_FILTER_OPERAND, self::UNRESOLVED_IMAGE_FILTER_OPERAND],
                true
            )
        ));
    }

    /**
     * @param list<string> $filters
     * @return array<string, int|string>
     */
    private function imageFilterOperandBoundaryMetadata(array $filters, ?array $extraOperand = null): array
    {
        if ($filters === []) {
            return [];
        }

        $malformedCount = 0;
        $unresolvedCount = 0;
        foreach ($filters as $filter) {
            if ($filter === self::MALFORMED_IMAGE_FILTER_OPERAND) {
                $malformedCount++;
            } elseif ($filter === self::UNRESOLVED_IMAGE_FILTER_OPERAND) {
                $unresolvedCount++;
            }
        }

        return [
            'filter_operand_policy' => $malformedCount > 0
                ? 'reject_malformed_filter_operands'
                : 'reject_unresolved_filter_operands',
            'malformed_filter_operand_count' => $malformedCount,
            'unresolved_filter_operand_count' => $unresolvedCount,
            ...($extraOperand !== null && ($extraOperand['after_comment'] ?? false) === true ? [
                'extra_filter_operand_after_comment' => true,
            ] : []),
        ];
    }

    /**
     * @param list<string> $filters
     * @param array<int, string> $objects
     * @return array{present: bool, value: int|null, valid_value: bool, filter_is_jpx: bool, uses_embedded_soft_mask: bool, encoded_soft_mask_values: bool, preblended_with_matte: bool, external_soft_mask_present: bool, external_soft_mask_ignored: bool, ignored_without_jpx: bool, review_only: bool}|null
     */
    private function jpxSoftMaskInDataDetails(string $dictionary, array $filters, array $objects): ?array
    {
        $value = $this->extractPdfNameValue($dictionary, 'SMaskInData');
        if ($value === null) {
            return null;
        }

        $integer = $this->integerFromPdfValue($value, $objects);
        $validValue = is_int($integer) && in_array($integer, [0, 1, 2], true);
        $filterIsJpx = in_array('JPXDecode', $filters, true);
        $usesEmbeddedSoftMask = $filterIsJpx && $validValue && $integer !== 0;
        $softMaskValue = $this->extractPdfNameValue($dictionary, 'SMask');
        $externalSoftMaskPresent = $softMaskValue !== null && $this->pdfNameValue($softMaskValue) !== 'None';

        return [
            'present' => true,
            'value' => $integer,
            'valid_value' => $validValue,
            'filter_is_jpx' => $filterIsJpx,
            'uses_embedded_soft_mask' => $usesEmbeddedSoftMask,
            'encoded_soft_mask_values' => $usesEmbeddedSoftMask && $integer === 1,
            'preblended_with_matte' => $usesEmbeddedSoftMask && $integer === 2,
            'external_soft_mask_present' => $externalSoftMaskPresent,
            'external_soft_mask_ignored' => $usesEmbeddedSoftMask && $externalSoftMaskPresent,
            'ignored_without_jpx' => !$filterIsJpx,
            'review_only' => $usesEmbeddedSoftMask || ($filterIsJpx && !$validValue),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string|null>
     */
    private function imageDecodeParmsValues(string $dictionary, array $objects): array
    {
        $value = $this->imageDecodeParmsValueWithTopLevelTail($dictionary)
            ?? $this->imageDecodeParmsValueAfterRejectedFilterOperand($dictionary);
        if ($value === null) {
            return [];
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if ($resolved === 'null') {
            return [];
        }

        if (str_starts_with($resolved, '[')) {
            $array = $this->readBalancedArray($resolved, 0);
            if ($array === null || $this->skipPdfWhitespace($resolved, $array['next']) !== strlen($resolved)) {
                return [$value];
            }

            return array_map(
                static fn (string $entry): ?string => trim($entry) === 'null' ? null : $entry,
                $this->pdfArrayValues($array['value'])
            );
        }

        return [$value];
    }

    private function imageDecodeParmsValueWithTopLevelTail(string $dictionary): ?string
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $read = $this->readBalancedDictionary($body, 0);
            if ($read !== null) {
                $body = trim(substr($read['value'], 2, -2));
            }
        }

        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $key = $this->readPdfValueWithOffset($body, $offset);
            if ($key === null || !str_starts_with(trim($key['value']), '/')) {
                break;
            }

            $valueStart = $this->skipPdfWhitespace($body, $key['next']);
            $value = $this->readPdfValueWithOffset($body, $valueStart);
            if ($value === null) {
                break;
            }

            if ($this->pdfNameValue($key['value']) === 'DecodeParms') {
                $extraOperandEnd = $this->imageDecodeParmsExtraOperandEndAfterValue($body, $value['next']);
                if ($extraOperandEnd !== null) {
                    return substr($body, $valueStart, $extraOperandEnd - $valueStart);
                }

                return $value['value'];
            }

            $offset = $this->pdfDictionaryScanOffsetAfterValue($body, $key['value'], $value['next']);
        }

        return null;
    }

    private function imageDecodeParmsExtraOperandEndAfterValue(string $body, int $offset): ?int
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        if ($offset >= strlen($body) || substr($body, $offset, 2) === '>>' || ($body[$offset] ?? '') === ']') {
            return null;
        }

        if (($body[$offset] ?? '') === '/') {
            return null;
        }

        $extra = $this->readPdfValueWithOffset($body, $offset);
        if ($extra !== null && $extra['next'] > $offset) {
            return $extra['next'];
        }

        $end = $offset + strcspn($body, " \t\r\n\f[]()<>{}/%", $offset);

        return $end > $offset ? $end : $offset + 1;
    }

    private function imageDecodeParmsValueAfterRejectedFilterOperand(string $dictionary): ?string
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $read = $this->readBalancedDictionary($body, 0);
            if ($read !== null) {
                $body = trim(substr($read['value'], 2, -2));
            }
        }

        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $key = $this->readPdfValueWithOffset($body, $offset);
            if ($key === null || !str_starts_with(trim($key['value']), '/')) {
                break;
            }

            $value = $this->readPdfValueWithOffset($body, $key['next']);
            if ($value === null) {
                break;
            }

            if ($this->pdfNameValue($key['value']) === 'Filter') {
                $extraOperandEnd = $this->imageDirectFilterExtraOperandEndAfterValue($body, $value['next']);
                if ($extraOperandEnd !== null) {
                    return $this->pdfDictionaryValueForNameFromOffset($body, 'DecodeParms', $extraOperandEnd);
                }
            }

            $offset = $value['next'];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int|bool|string|null|list<string>>|null
     */
    private function imageFilterDecodeParms(string $filter, ?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if ($resolved === 'null') {
            return null;
        }

        if ($filter === 'CCITTFaxDecode' || $filter === 'CCF') {
            if (!str_starts_with($resolved, '<<')) {
                return $this->ccittFaxDecodeParmsOperandFailureReview($resolved);
            }
            $dictionaryOperand = $this->readBalancedDictionary($resolved, 0);
            if (
                $dictionaryOperand === null
                || $this->skipPdfWhitespace($resolved, $dictionaryOperand['next']) !== strlen($resolved)
            ) {
                return $this->ccittFaxDecodeParmsOperandFailureReview($resolved);
            }

            return $this->ccittFaxDecodeParmsReview($resolved, $objects);
        }

        if (!str_starts_with($resolved, '<<')) {
            return null;
        }

        if ($filter === 'JBIG2Decode') {
            $globals = $this->jbig2GlobalsMetadata($resolved, $objects);

            return [
                'type' => 'JBIG2Decode',
                'jbig2_globals_present' => $globals['present'],
                'jbig2_globals_source' => $globals['source'],
                'jbig2_globals_object' => $globals['object'],
                'jbig2_globals_length' => $globals['length'],
                'jbig2_globals_sha256' => $globals['sha256'],
                'jbig2_globals_preview_hex' => $globals['preview_hex'],
            ];
        }

        if ($filter === 'DCTDecode' || $filter === 'DCT') {
            $colorTransform = $this->integerNameValue($resolved, 'ColorTransform');
            $duplicateColorTransform = count($this->pdfDictionaryValuesForName($resolved, 'ColorTransform')) > 1;

            $review = [
                'type' => 'DCTDecode',
                'color_transform' => $colorTransform,
                'valid_color_transform' => !$duplicateColorTransform
                    && ($colorTransform === null || in_array($colorTransform, [0, 1, 2], true)),
            ];
            if ($duplicateColorTransform) {
                $review['invalid_decode_parms_fields'] = ['color_transform'];
                $review['duplicate_decode_parms_fields'] = ['color_transform'];
                $review['decode_parms_review'] = 'duplicate_dctdecode_decodeparms_parameter_fail_closed';
            }

            return $review;
        }

        if ($filter === 'Crypt') {
            $name = $this->pdfNameValue($this->extractPdfNameValue($resolved, 'Name') ?? '');

            return [
                'type' => 'Crypt',
                'name' => $name,
                'identity' => $name === 'Identity',
            ];
        }

        $nativeDecodeParms = $this->nativeImageDecodeParmsReview($filter, $resolved, $objects);
        if ($nativeDecodeParms !== null) {
            return $nativeDecodeParms;
        }

        return ['type' => $filter];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int|bool|string|null|list<string>>|null
     */
    private function nativeImageDecodeParmsReview(string $filter, string $decodeParms, array $objects): ?array
    {
        if (!in_array($filter, ['FlateDecode', 'Fl', 'LZWDecode', 'LZW'], true)) {
            return null;
        }

        $fields = [
            'Predictor' => 'predictor',
            'Columns' => 'columns',
            'Colors' => 'colors',
            'BitsPerComponent' => 'bits_per_component',
            'EarlyChange' => 'early_change',
        ];
        $hasNativeDecodeParms = false;
        $review = ['type' => $filter];
        $invalidFields = [];
        $resolvedDecodeParms = trim($decodeParms);

        foreach ($fields as $name => $key) {
            $hasField = $this->decodeParmsHasName($decodeParms, $name);
            $hasNativeDecodeParms = $hasNativeDecodeParms || $hasField;
            $value = $this->decodeParmsInt($decodeParms, $name, $objects);
            $review[$key] = $value;
            if ($hasField && $value === null) {
                $invalidFields[] = $key;
            }
        }

        if (!$hasNativeDecodeParms) {
            return null;
        }

        if (str_starts_with($resolvedDecodeParms, '<<')) {
            $dictionaryOperand = $this->readBalancedDictionary($resolvedDecodeParms, 0);
            if (
                $dictionaryOperand === null
                || $this->skipPdfWhitespace($resolvedDecodeParms, $dictionaryOperand['next']) !== strlen($resolvedDecodeParms)
            ) {
                $invalidFields[] = 'decode_parms_operand';
            }
        }

        $predictor = $review['predictor'];
        if (
            is_int($predictor)
            && $predictor !== 1
            && $predictor !== 2
            && ($predictor < 10 || $predictor > 15)
        ) {
            $invalidFields[] = 'predictor';
        }

        foreach (['columns', 'colors', 'bits_per_component'] as $key) {
            $value = $review[$key];
            if (is_int($value) && $value < 1) {
                $invalidFields[] = $key;
            }
        }

        $earlyChange = $review['early_change'];
        if (
            is_int($earlyChange)
            && (
                !in_array($earlyChange, [0, 1], true)
                || ($earlyChange !== 1 && !in_array($filter, ['LZWDecode', 'LZW'], true))
            )
        ) {
            $invalidFields[] = 'early_change';
        }

        $invalidFields = array_values(array_unique($invalidFields));
        $review['valid_decode_parms'] = $invalidFields === [];
        if ($invalidFields !== []) {
            $review['invalid_decode_parms_fields'] = $invalidFields;
            $review['decode_parms_review'] = 'invalid_native_decodeparms_fail_closed';
        }

        return $review;
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     * @return array<string, int|bool|string|null|list<int>|list<string>>|null
     */
    private function ccittFaxUnappliedDecodeParmsReview(string $filter, array $filters, array $decodeParms): ?array
    {
        if ($filter !== 'CCITTFaxDecode' && $filter !== 'CCF') {
            return null;
        }

        $unappliedSlots = $this->unappliedNonNullDecodeParmsSlots($filters, $decodeParms);
        if ($unappliedSlots === []) {
            return null;
        }

        return [
            'type' => 'CCITTFaxDecode',
            'k' => null,
            'columns' => null,
            'rows' => null,
            'black_is_1' => null,
            'encoded_byte_align' => null,
            'end_of_line' => null,
            'end_of_block' => null,
            'damaged_rows_before_error' => null,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_alignment'],
            'decode_parms_review' => 'unaligned_ccitt_decodeparms_fail_closed',
            'decode_parms_alignment' => 'unapplied_filter_slot',
            'filter_slot_count' => count($filters),
            'decode_parms_slot_count' => count($decodeParms),
            'unapplied_decode_parms_slots' => $unappliedSlots,
        ];
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     * @return array<string, int|bool|string|null|list<int>|list<string>>|null
     */
    private function dctDecodeUnappliedDecodeParmsReview(string $filter, array $filters, array $decodeParms): ?array
    {
        if ($filter !== 'DCTDecode' && $filter !== 'DCT') {
            return null;
        }

        $unappliedSlots = $this->unappliedNonNullDecodeParmsSlots($filters, $decodeParms);
        if ($unappliedSlots === []) {
            return null;
        }

        return [
            'type' => 'DCTDecode',
            'color_transform' => null,
            'valid_color_transform' => false,
            'invalid_decode_parms_fields' => ['decode_parms_alignment'],
            'decode_parms_review' => 'unaligned_dctdecode_decodeparms_fail_closed',
            'decode_parms_alignment' => 'unapplied_filter_slot',
            'filter_slot_count' => count($filters),
            'decode_parms_slot_count' => count($decodeParms),
            'unapplied_decode_parms_slots' => $unappliedSlots,
        ];
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     * @return list<int>
     */
    private function unappliedNonNullDecodeParmsSlots(array $filters, array $decodeParms): array
    {
        $nonNullFilterIndexes = [];
        foreach ($filters as $filterIndex => $filter) {
            if ($this->imageFilterCanCarryDecodeParms($filter)) {
                $nonNullFilterIndexes[] = $filterIndex;
            }
        }

        if ($this->decodeParmsUseCompactNonNullFilterIndexes($filters, count($decodeParms), $nonNullFilterIndexes)) {
            return [];
        }

        $slots = [];
        foreach ($decodeParms as $decodeParmsIndex => $value) {
            if ($value === null || trim($value) === '') {
                continue;
            }

            if (array_key_exists($decodeParmsIndex, $filters) && $filters[$decodeParmsIndex] === null) {
                continue;
            }

            if (
                array_key_exists($decodeParmsIndex, $filters)
                && (
                    $filters[$decodeParmsIndex] === null
                    || $this->imageFilterCanCarryDecodeParms($filters[$decodeParmsIndex])
                )
            ) {
                continue;
            }

            $slots[] = $decodeParmsIndex;
        }

        return $slots;
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     * @return array<string, int|bool|string|null|list<string>>|null
     */
    private function ccittFaxUnalignedDecodeParmsReview(
        string $filter,
        array $filters,
        array $decodeParms,
        ?int $decodeParmsIndex
    ): ?array {
        if (($filter !== 'CCITTFaxDecode' && $filter !== 'CCF') || $decodeParmsIndex !== null) {
            return null;
        }

        $hasDeclaredNonNullDecodeParms = false;
        foreach ($decodeParms as $value) {
            if ($value !== null && trim($value) !== '') {
                $hasDeclaredNonNullDecodeParms = true;
                break;
            }
        }
        if (!$hasDeclaredNonNullDecodeParms) {
            return null;
        }

        $filterSlots = count($filters);
        $decodeParmsSlots = count($decodeParms);

        return [
            'type' => 'CCITTFaxDecode',
            'k' => null,
            'columns' => null,
            'rows' => null,
            'black_is_1' => null,
            'encoded_byte_align' => null,
            'end_of_line' => null,
            'end_of_block' => null,
            'damaged_rows_before_error' => null,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_alignment'],
            'decode_parms_review' => 'unaligned_ccitt_decodeparms_fail_closed',
            'decode_parms_alignment' => $decodeParmsSlots < $filterSlots ? 'missing_filter_slot' : 'unapplied_filter_slot',
            'filter_slot_count' => $filterSlots,
            'decode_parms_slot_count' => $decodeParmsSlots,
        ];
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     * @return array<string, int|bool|string|null|list<string>>|null
     */
    private function dctDecodeUnalignedDecodeParmsReview(
        string $filter,
        array $filters,
        array $decodeParms,
        ?int $decodeParmsIndex
    ): ?array {
        if (($filter !== 'DCTDecode' && $filter !== 'DCT') || $decodeParmsIndex !== null) {
            return null;
        }

        $hasDeclaredNonNullDecodeParms = false;
        foreach ($decodeParms as $value) {
            if ($value !== null && trim($value) !== '') {
                $hasDeclaredNonNullDecodeParms = true;
                break;
            }
        }
        if (!$hasDeclaredNonNullDecodeParms) {
            return null;
        }

        $filterSlots = count($filters);
        $decodeParmsSlots = count($decodeParms);

        return [
            'type' => 'DCTDecode',
            'color_transform' => null,
            'valid_color_transform' => false,
            'invalid_decode_parms_fields' => ['decode_parms_alignment'],
            'decode_parms_review' => 'unaligned_dctdecode_decodeparms_fail_closed',
            'decode_parms_alignment' => $decodeParmsSlots < $filterSlots ? 'missing_filter_slot' : 'unapplied_filter_slot',
            'filter_slot_count' => $filterSlots,
            'decode_parms_slot_count' => $decodeParmsSlots,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int|bool|string|null|list<string>>|null
     */
    private function dctDecodeDuplicateDecodeParmsDeclarationReview(
        string $filter,
        string $dictionary,
        ?string $decodeParmsValue,
        array $objects
    ): ?array {
        if ($filter !== 'DCTDecode' && $filter !== 'DCT') {
            return null;
        }

        $duplicateCount = $this->duplicatePdfNameDeclarationCount($dictionary, 'DecodeParms');
        if ($duplicateCount < 1) {
            return null;
        }

        return [
            'type' => 'DCTDecode',
            'color_transform' => $this->dctDecodeParmsColorTransformFromValue($decodeParmsValue, $objects),
            'valid_color_transform' => false,
            'invalid_decode_parms_fields' => ['decode_parms_declaration'],
            'decode_parms_review' => 'duplicate_dctdecode_decodeparms_declaration_fail_closed',
            'duplicate_decode_parms_declaration_count' => $duplicateCount,
            'decode_parms_declaration_policy' => 'reject_duplicate_decodeparms_declarations',
        ];
    }

    /**
     * @return array<string, int|bool|string|list<string>>|null
     */
    private function nativeImageDuplicateDecodeParmsDeclarationReview(string $filter, string $dictionary): ?array
    {
        if (!$this->isNativeImageStreamFilter($filter)) {
            return null;
        }

        $duplicateCount = $this->duplicatePdfNameDeclarationCount($dictionary, 'DecodeParms');
        if ($duplicateCount < 1) {
            return null;
        }

        return [
            'type' => $filter,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_declaration'],
            'decode_parms_review' => 'duplicate_native_decodeparms_declaration_fail_closed',
            'duplicate_decode_parms_declaration_count' => $duplicateCount,
            'decode_parms_declaration_policy' => 'reject_duplicate_decodeparms_declarations',
        ];
    }

    /**
     * @return array<string, int|bool|string|null|list<string>>|null
     */
    private function ccittFaxDuplicateDecodeParmsDeclarationReview(string $filter, string $dictionary): ?array
    {
        if ($filter !== 'CCITTFaxDecode' && $filter !== 'CCF') {
            return null;
        }

        $duplicateCount = $this->duplicatePdfNameDeclarationCount($dictionary, 'DecodeParms');
        if ($duplicateCount < 1) {
            return null;
        }

        return [
            'type' => 'CCITTFaxDecode',
            'k' => null,
            'columns' => null,
            'rows' => null,
            'black_is_1' => null,
            'encoded_byte_align' => null,
            'end_of_line' => null,
            'end_of_block' => null,
            'damaged_rows_before_error' => null,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_declaration'],
            'decode_parms_review' => 'duplicate_ccitt_decodeparms_declaration_fail_closed',
            'duplicate_decode_parms_declaration_count' => $duplicateCount,
            'decode_parms_declaration_policy' => 'reject_duplicate_decodeparms_declarations',
        ];
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     * @return array<string, int|bool|string|list<int>|list<string>>|null
     */
    private function nativeImageUnappliedDecodeParmsReview(string $filter, array $filters, array $decodeParms): ?array
    {
        if (!$this->isNativeImageStreamFilter($filter)) {
            return null;
        }

        $unappliedSlots = $this->unappliedNonNullDecodeParmsSlots($filters, $decodeParms);
        if ($unappliedSlots === []) {
            return null;
        }

        return [
            'type' => $filter,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_alignment'],
            'decode_parms_review' => 'unaligned_native_decodeparms_fail_closed',
            'decode_parms_alignment' => 'unapplied_filter_slot',
            'filter_slot_count' => count($filters),
            'decode_parms_slot_count' => count($decodeParms),
            'unapplied_decode_parms_slots' => $unappliedSlots,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, bool|string|null|list<string>>|null
     */
    private function dctDecodeParmsOperandFailureInDictionary(string $dictionary, array $objects): ?array
    {
        $filters = $this->imageFilterValues($dictionary, $objects);
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        foreach ($filters as $index => $filter) {
            if ($filter !== 'DCTDecode' && $filter !== 'DCT') {
                continue;
            }

            return $this->dctDecodeParmsOperandFailureReviewFromValue(
                $this->decodeParmsValueForImageFilterIndex($filters, $decodeParms, $index),
                $objects
            );
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, bool|string|null|list<string>>|null
     */
    private function dctDecodeParmsOperandFailureReview(string $filter, ?string $value, array $objects): ?array
    {
        if ($filter !== 'DCTDecode' && $filter !== 'DCT') {
            return null;
        }

        return $this->dctDecodeParmsOperandFailureReviewFromValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, bool|string|null|list<string>>|null
     */
    private function dctDecodeParmsOperandFailureReviewFromValue(?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || strtolower($trimmed) === 'null') {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($trimmed, $objects));
        if (str_starts_with($resolved, '<<')) {
            return null;
        }

        $operand = preg_match('/^\d+\s+\d+\s+R$/', $resolved) === 1
            ? 'unresolved_reference'
            : 'malformed_operand';

        $details = $this->dctDecodeParmsOperandFailureDetails($resolved);

        return [
            'type' => 'DCTDecode',
            'color_transform' => null,
            'valid_color_transform' => false,
            'invalid_decode_parms_fields' => ['decode_parms_operand'],
            'decode_parms_review' => $operand === 'unresolved_reference'
                ? 'unresolved_dctdecode_decodeparms_fail_closed'
                : 'malformed_dctdecode_decodeparms_fail_closed',
            'decode_parms_operand' => $operand,
            ...$details,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dctDecodeParmsOperandFailureDetails(string $resolved): array
    {
        $trimmed = trim($resolved);
        if (!str_starts_with($trimmed, '[')) {
            return [];
        }

        $array = $this->readBalancedArray($trimmed, 0);
        if ($array === null) {
            return [
                'decode_parms_operand_detail' => 'malformed_array_operand',
                'decode_parms_array_policy' => 'reject_malformed_decodeparms_array',
            ];
        }

        if ($this->skipPdfWhitespace($trimmed, $array['next']) !== strlen($trimmed)) {
            return [
                'decode_parms_operand_detail' => 'array_with_trailing_operands',
                'decode_parms_array_policy' => 'reject_top_level_decodeparms_array_tail',
            ];
        }

        return [];
    }

    /**
     * @return array<string, int|bool|string|null|list<string>>
     */
    private function ccittFaxDecodeParmsOperandFailureReview(string $resolvedOperand): array
    {
        $operand = preg_match('/^\d+\s+\d+\s+R$/', trim($resolvedOperand)) === 1
            ? 'unresolved_reference'
            : 'malformed_operand';
        $details = $this->ccittFaxDecodeParmsDictionaryOperandFailureDetails($resolvedOperand);

        return [
            'type' => 'CCITTFaxDecode',
            'k' => null,
            'columns' => null,
            'rows' => null,
            'black_is_1' => null,
            'encoded_byte_align' => null,
            'end_of_line' => null,
            'end_of_block' => null,
            'damaged_rows_before_error' => null,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_operand'],
            'decode_parms_review' => $operand === 'unresolved_reference'
                ? 'unresolved_ccitt_decodeparms_fail_closed'
                : 'malformed_ccitt_decodeparms_fail_closed',
            'decode_parms_operand' => $operand,
            ...$details,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ccittFaxDecodeParmsDictionaryOperandFailureDetails(string $resolved): array
    {
        $trimmed = trim($resolved);
        if (str_starts_with($trimmed, '[')) {
            $array = $this->readBalancedArray($trimmed, 0);
            if ($array === null) {
                return [
                    'decode_parms_operand_detail' => 'malformed_array_operand',
                    'decode_parms_array_policy' => 'reject_malformed_decodeparms_array',
                ];
            }

            if ($this->skipPdfWhitespace($trimmed, $array['next']) !== strlen($trimmed)) {
                return [
                    'decode_parms_operand_detail' => 'array_with_trailing_operands',
                    'decode_parms_array_policy' => 'reject_top_level_decodeparms_array_tail',
                ];
            }

            return [];
        }

        if (!str_starts_with($trimmed, '<<')) {
            return [];
        }

        $dictionary = $this->readBalancedDictionary($trimmed, 0);
        if ($dictionary === null) {
            return [
                'decode_parms_operand_detail' => 'malformed_dictionary_operand',
                'decode_parms_dictionary_policy' => 'reject_malformed_decodeparms_dictionary',
            ];
        }

        if ($this->skipPdfWhitespace($trimmed, $dictionary['next']) !== strlen($trimmed)) {
            return [
                'decode_parms_operand_detail' => 'dictionary_with_trailing_operands',
                'decode_parms_dictionary_policy' => 'reject_top_level_decodeparms_dictionary_tail',
            ];
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int|bool|string|null|list<string>>
     */
    private function ccittFaxDecodeParmsReview(string $decodeParms, array $objects): array
    {
        $duplicateFields = $this->duplicateCcittDecodeParmsFields($decodeParms);
        $details = [
            'type' => 'CCITTFaxDecode',
            'k' => $this->decodeParmsInt($decodeParms, 'K', $objects),
            'columns' => $this->decodeParmsInt($decodeParms, 'Columns', $objects),
            'rows' => $this->decodeParmsInt($decodeParms, 'Rows', $objects),
            'black_is_1' => $this->decodeParmsBool($decodeParms, 'BlackIs1', $objects),
            'encoded_byte_align' => $this->decodeParmsBool($decodeParms, 'EncodedByteAlign', $objects),
            'end_of_line' => $this->decodeParmsBool($decodeParms, 'EndOfLine', $objects),
            'end_of_block' => $this->decodeParmsBool($decodeParms, 'EndOfBlock', $objects),
            'damaged_rows_before_error' => $this->decodeParmsInt($decodeParms, 'DamagedRowsBeforeError', $objects),
        ];

        $invalidFields = [];
        foreach (['K' => 'k'] as $pdfName => $field) {
            if ($this->decodeParmsHasName($decodeParms, $pdfName) && $details[$field] === null) {
                $invalidFields[$field] = true;
            }
        }

        foreach (['Columns' => 'columns'] as $pdfName => $field) {
            if (
                $this->decodeParmsHasName($decodeParms, $pdfName)
                && (!is_int($details[$field]) || $details[$field] < 1)
            ) {
                $invalidFields[$field] = true;
            }
        }

        foreach (['Rows' => 'rows', 'DamagedRowsBeforeError' => 'damaged_rows_before_error'] as $pdfName => $field) {
            if (
                $this->decodeParmsHasName($decodeParms, $pdfName)
                && (!is_int($details[$field]) || $details[$field] < 0)
            ) {
                $invalidFields[$field] = true;
            }
        }

        foreach ([
            'BlackIs1' => 'black_is_1',
            'EncodedByteAlign' => 'encoded_byte_align',
            'EndOfLine' => 'end_of_line',
            'EndOfBlock' => 'end_of_block',
        ] as $pdfName => $field) {
            if ($this->decodeParmsHasName($decodeParms, $pdfName) && !is_bool($details[$field])) {
                $invalidFields[$field] = true;
            }
        }

        foreach ($duplicateFields as $field) {
            $invalidFields[$field] = true;
        }

        if ($invalidFields !== []) {
            $details['valid_decode_parms'] = false;
            $details['invalid_decode_parms_fields'] = array_values(array_filter(
                [
                    'k',
                    'columns',
                    'rows',
                    'black_is_1',
                    'encoded_byte_align',
                    'end_of_line',
                    'end_of_block',
                    'damaged_rows_before_error',
                ],
                static fn (string $field): bool => isset($invalidFields[$field])
            ));
            if ($duplicateFields !== []) {
                $details['duplicate_decode_parms_fields'] = $duplicateFields;
            }
            $details['decode_parms_review'] = $duplicateFields !== []
                ? 'duplicate_ccitt_decodeparms_parameter_fail_closed'
                : 'invalid_ccitt_decodeparms_fail_closed';
        }

        return $details;
    }

    /**
     * @return list<string>
     */
    private function duplicateCcittDecodeParmsFields(string $decodeParms): array
    {
        $duplicates = [];
        foreach ([
            'K' => 'k',
            'Columns' => 'columns',
            'Rows' => 'rows',
            'BlackIs1' => 'black_is_1',
            'EncodedByteAlign' => 'encoded_byte_align',
            'EndOfLine' => 'end_of_line',
            'EndOfBlock' => 'end_of_block',
            'DamagedRowsBeforeError' => 'damaged_rows_before_error',
        ] as $pdfName => $field) {
            if (count($this->pdfDictionaryValuesForName($decodeParms, $pdfName)) > 1) {
                $duplicates[] = $field;
            }
        }

        return $duplicates;
    }

    /**
     * @param list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null|list<string>>|null}> $filterDetails
     * @return array<string, mixed>|null
     */
    private function ccittFaxDecodeBoundaryReview(array $filterDetails, ?int $dictionaryWidth, ?int $dictionaryHeight): ?array
    {
        $detail = null;
        foreach ($filterDetails as $candidate) {
            if (($candidate['filter'] ?? null) === 'CCITTFaxDecode' || ($candidate['filter'] ?? null) === 'CCF') {
                $detail = $candidate;
                break;
            }
        }

        if ($detail === null) {
            return null;
        }

        $decodeParms = is_array($detail['decode_parms'] ?? null) ? $detail['decode_parms'] : null;
        $invalidFields = is_array($decodeParms)
            ? array_values(array_filter(
                $decodeParms['invalid_decode_parms_fields'] ?? [],
                static fn (mixed $field): bool => is_string($field)
            ))
            : [];
        $invalidLookup = array_fill_keys($invalidFields, true);

        $effective = [
            'k' => $this->ccittEffectiveInt($decodeParms, 'k', 0, $invalidLookup),
            'columns' => $this->ccittEffectivePositiveInt($decodeParms, 'columns', 1728, $invalidLookup),
            'rows' => $this->ccittEffectiveNonNegativeInt($decodeParms, 'rows', 0, $invalidLookup),
            'black_is_1' => $this->ccittEffectiveBool($decodeParms, 'black_is_1', false, $invalidLookup),
            'encoded_byte_align' => $this->ccittEffectiveBool($decodeParms, 'encoded_byte_align', false, $invalidLookup),
            'end_of_line' => $this->ccittEffectiveBool($decodeParms, 'end_of_line', false, $invalidLookup),
            'end_of_block' => $this->ccittEffectiveBool($decodeParms, 'end_of_block', true, $invalidLookup),
            'damaged_rows_before_error' => $this->ccittEffectiveNonNegativeInt($decodeParms, 'damaged_rows_before_error', 0, $invalidLookup),
        ];
        $defaultsApplied = $this->ccittDefaultsApplied($decodeParms, $invalidLookup, [
            'k',
            'columns',
            'rows',
            'black_is_1',
            'encoded_byte_align',
            'end_of_line',
            'end_of_block',
            'damaged_rows_before_error',
        ]);

        $dictionaryWidthIsValid = $dictionaryWidth !== null && $dictionaryWidth >= 1;
        $dictionaryHeightIsValid = $dictionaryHeight !== null && $dictionaryHeight >= 0;
        $effectiveWidth = $dictionaryWidthIsValid ? $dictionaryWidth : $effective['columns'];
        $effectiveHeight = $dictionaryHeightIsValid ? $dictionaryHeight : ($effective['rows'] > 0 ? $effective['rows'] : null);
        $columnsMatchWidth = $dictionaryWidth === null ? null : $dictionaryWidth === $effective['columns'];
        $rowsMatchHeight = $dictionaryHeight === null || $effective['rows'] === 0
            ? null
            : $dictionaryHeight === $effective['rows'];
        $widthSource = $dictionaryWidthIsValid
            ? 'image_dictionary'
            : ($decodeParms !== null && !isset($invalidLookup['columns']) && is_int($decodeParms['columns'] ?? null) ? 'decodeparms_columns' : 'decodeparms_columns_default');
        $heightSource = $dictionaryHeightIsValid
            ? 'image_dictionary'
            : ($effective['rows'] > 0 ? 'decodeparms_rows' : 'unbounded_rows');

        return [
            'filter' => (string) $detail['filter'],
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => $decodeParms !== null,
            'invalid_decode_parms' => $invalidFields !== [],
            'invalid_decode_parms_fields' => $invalidFields,
            'effective_decode_parms' => $effective,
            'defaults_applied' => $defaultsApplied,
            'dictionary_width' => $dictionaryWidth,
            'dictionary_height' => $dictionaryHeight,
            'effective_width' => $effectiveWidth,
            'effective_height' => $effectiveHeight,
            'width_source' => $widthSource,
            'height_source' => $heightSource,
            'columns_match_width' => $columnsMatchWidth,
            'rows_match_height' => $rowsMatchHeight,
            'dimension_mismatch' => $columnsMatchWidth === false || $rowsMatchHeight === false,
        ];
    }

    /**
     * @param list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null|list<string>>|null}> $filterDetails
     * @return array<string, mixed>|null
     */
    private function ccittFaxFilterBoundaryReview(array $filterDetails): ?array
    {
        $filters = [];
        $previewOnly = [];
        $nativePrefix = [];
        $canonicalNativePrefix = [];
        foreach ($filterDetails as $detailIndex => $detail) {
            $filter = $detail['filter'] ?? null;
            if (!is_string($filter)) {
                continue;
            }

            if ($filter === 'CCITTFaxDecode' || $filter === 'CCF') {
                $filtersAfterCcitt = [];
                $nativeFiltersAfterCcitt = [];
                $previewOnlyFiltersAfterCcitt = [];
                for ($afterIndex = $detailIndex + 1, $count = count($filterDetails); $afterIndex < $count; $afterIndex++) {
                    $afterFilter = $filterDetails[$afterIndex]['filter'] ?? null;
                    if (!is_string($afterFilter)) {
                        continue;
                    }

                    $filtersAfterCcitt[] = $afterFilter;
                    if (($filterDetails[$afterIndex]['preview_only'] ?? false) === true) {
                        $previewOnlyFiltersAfterCcitt[] = $afterFilter;
                    } else {
                        $nativeFiltersAfterCcitt[] = $afterFilter;
                    }
                }

                return [
                    'declared_filter' => $filter,
                    'canonical_filter' => 'CCITTFaxDecode',
                    'alias_used' => $filter === 'CCF',
                    'non_null_filter_index' => count($filters),
                    'filters_before_ccitt' => $filters,
                    'native_prefix_filters' => $nativePrefix,
                    ...($canonicalNativePrefix !== $nativePrefix ? [
                        'canonical_native_prefix_filters' => $canonicalNativePrefix,
                    ] : []),
                    'preview_only_filters_before_ccitt' => $previewOnly,
                    ...($previewOnly !== [] ? [
                        'pre_ccitt_preview_filters_block_native_prefix_decode' => true,
                    ] : []),
                    'filters_after_ccitt' => $filtersAfterCcitt,
                    'native_filters_after_ccitt' => $nativeFiltersAfterCcitt,
                    'preview_only_filters_after_ccitt' => $previewOnlyFiltersAfterCcitt,
                    'ccitt_is_terminal_filter' => $filtersAfterCcitt === [],
                    'post_ccitt_filters_present' => $filtersAfterCcitt !== [],
                    'post_ccitt_filters_block_native_decode' => $filtersAfterCcitt !== [],
                    'source_filter_preserved' => true,
                    'review_only' => true,
                    'native_raster_decode' => false,
                ];
            }

            $filters[] = $filter;
            if (($detail['preview_only'] ?? false) === true) {
                $previewOnly[] = $filter;
            } else {
                $nativePrefix[] = $filter;
                $canonicalNativePrefix[] = $this->canonicalNativeImageStreamFilterName($filter);
            }
        }

        return null;
    }

    /**
     * @param list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null|list<string>>|null}> $filterDetails
     * @param list<string|null>|null $filterSlots
     * @return array<string, mixed>|null
     */
    private function dctDecodeFilterBoundaryReview(array $filterDetails, ?array $filterSlots = null): ?array
    {
        $filters = [];
        $previewOnly = [];
        $nativePrefix = [];
        $canonicalNativePrefix = [];
        foreach ($filterDetails as $detailIndex => $detail) {
            $filter = $detail['filter'] ?? null;
            if (!is_string($filter)) {
                continue;
            }

            if ($filter === 'DCTDecode' || $filter === 'DCT') {
                $filtersAfterDctDecode = [];
                $nativeFiltersAfterDctDecode = [];
                $previewOnlyFiltersAfterDctDecode = [];
                for ($afterIndex = $detailIndex + 1, $count = count($filterDetails); $afterIndex < $count; $afterIndex++) {
                    $afterFilter = $filterDetails[$afterIndex]['filter'] ?? null;
                    if (!is_string($afterFilter)) {
                        continue;
                    }

                    $filtersAfterDctDecode[] = $afterFilter;
                    if (($filterDetails[$afterIndex]['preview_only'] ?? false) === true) {
                        $previewOnlyFiltersAfterDctDecode[] = $afterFilter;
                    } else {
                        $nativeFiltersAfterDctDecode[] = $afterFilter;
                    }
                }

                return [
                    'declared_filter' => $filter,
                    'canonical_filter' => 'DCTDecode',
                    'alias_used' => $filter === 'DCT',
                    'non_null_filter_index' => count($filters),
                    ...$this->dctDecodeFilterSlotBoundaryMetadata($filterSlots, $detailIndex),
                    'filters_before_dctdecode' => $filters,
                    'native_prefix_filters' => $nativePrefix,
                    ...($canonicalNativePrefix !== $nativePrefix ? [
                        'canonical_native_prefix_filters' => $canonicalNativePrefix,
                    ] : []),
                    'preview_only_filters_before_dctdecode' => $previewOnly,
                    ...($previewOnly !== [] ? [
                        'pre_dctdecode_preview_filters_block_native_prefix_decode' => true,
                    ] : []),
                    'filters_after_dctdecode' => $filtersAfterDctDecode,
                    'native_filters_after_dctdecode' => $nativeFiltersAfterDctDecode,
                    'preview_only_filters_after_dctdecode' => $previewOnlyFiltersAfterDctDecode,
                    'dctdecode_is_terminal_filter' => $filtersAfterDctDecode === [],
                    'post_dctdecode_filters_present' => $filtersAfterDctDecode !== [],
                    'post_dctdecode_filters_block_native_decode' => $filtersAfterDctDecode !== [],
                    'source_filter_preserved' => true,
                    'review_only' => true,
                    'native_raster_decode' => false,
                ];
            }

            $filters[] = $filter;
            if (($detail['preview_only'] ?? false) === true) {
                $previewOnly[] = $filter;
            } else {
                $nativePrefix[] = $filter;
                $canonicalNativePrefix[] = $this->canonicalNativeImageStreamFilterName($filter);
            }
        }

        return null;
    }

    /**
     * @param list<string|null>|null $filterSlots
     * @return array<string, int>
     */
    private function dctDecodeFilterSlotBoundaryMetadata(?array $filterSlots, int $detailIndex): array
    {
        if ($filterSlots === null) {
            return [];
        }

        $rawSlotIndex = null;
        $nonNullIndex = 0;
        foreach ($filterSlots as $slotIndex => $slotFilter) {
            if (!is_string($slotFilter)) {
                continue;
            }

            if ($nonNullIndex === $detailIndex) {
                $rawSlotIndex = $slotIndex;
                break;
            }

            $nonNullIndex++;
        }
        if (!is_int($rawSlotIndex)) {
            return [];
        }

        $nullBefore = 0;
        for ($slotIndex = 0; $slotIndex < $rawSlotIndex; $slotIndex++) {
            if (array_key_exists($slotIndex, $filterSlots) && $filterSlots[$slotIndex] === null) {
                $nullBefore++;
            }
        }
        if ($nullBefore === 0) {
            return [];
        }

        return [
            'raw_filter_slot_index' => $rawSlotIndex,
            'filter_stack_slot_count' => count($filterSlots),
            'null_filter_slot_count_before_dctdecode' => $nullBefore,
        ];
    }

    /**
     * @param list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null|list<string>>|null}> $filterDetails
     * @return array<string, mixed>|null
     */
    private function ccittFaxCodingBoundaryReview(array $filterDetails): ?array
    {
        $boundary = $this->ccittFaxDecodeBoundaryReview($filterDetails, null, null);
        if ($boundary === null) {
            return null;
        }

        $effective = is_array($boundary['effective_decode_parms'] ?? null)
            ? $boundary['effective_decode_parms']
            : [];
        $k = is_int($effective['k'] ?? null) ? $effective['k'] : 0;
        $endOfBlock = is_bool($effective['end_of_block'] ?? null) ? $effective['end_of_block'] : true;

        return [
            'filter' => (string) ($boundary['filter'] ?? 'CCITTFaxDecode'),
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => ($boundary['decode_parms_present'] ?? false) === true,
            'invalid_decode_parms' => ($boundary['invalid_decode_parms'] ?? false) === true,
            'effective_k' => $k,
            'coding_mode' => $this->ccittFaxCodingMode($k),
            'uses_two_dimensional_coding' => $k !== 0,
            'two_dimensional_line_interval' => $k > 0 ? $k : null,
            'end_of_block' => $endOfBlock,
            'end_of_block_marker' => $this->ccittFaxEndOfBlockMarkerName($k, $endOfBlock),
        ];
    }

    /**
     * @param array<string, mixed>|null $ccittBoundary
     * @param array{present: bool, decode: array{source: string, inverted_components: list<int>}, opacity_for_zero: float, opacity_for_one: float}|null $imageMask
     * @return array<string, mixed>|null
     */
    private function ccittFaxImageMaskPolarityBoundary(?array $ccittBoundary, ?array $imageMask): ?array
    {
        if ($ccittBoundary === null || $imageMask === null || ($imageMask['present'] ?? false) !== true) {
            return null;
        }

        $effective = is_array($ccittBoundary['effective_decode_parms'] ?? null)
            ? $ccittBoundary['effective_decode_parms']
            : [];
        $blackIs1 = is_bool($effective['black_is_1'] ?? null) ? $effective['black_is_1'] : false;
        $blackSampleValue = $blackIs1 ? 1 : 0;
        $whiteSampleValue = $blackIs1 ? 0 : 1;
        $opacityForZero = is_numeric($imageMask['opacity_for_zero'] ?? null)
            ? (float) $imageMask['opacity_for_zero']
            : null;
        $opacityForOne = is_numeric($imageMask['opacity_for_one'] ?? null)
            ? (float) $imageMask['opacity_for_one']
            : null;
        $blackOpacity = $blackSampleValue === 1 ? $opacityForOne : $opacityForZero;
        $whiteOpacity = $whiteSampleValue === 1 ? $opacityForOne : $opacityForZero;
        $decode = is_array($imageMask['decode'] ?? null) ? $imageMask['decode'] : null;

        return [
            'filter' => (string) ($ccittBoundary['filter'] ?? 'CCITTFaxDecode'),
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => $blackIs1,
            'black_sample_value' => $blackSampleValue,
            'white_sample_value' => $whiteSampleValue,
            'image_mask_decode_source' => $decode['source'] ?? null,
            'decode_inverts_stencil' => is_array($decode) && ($decode['inverted_components'] ?? []) !== [],
            'black_sample_opacity' => $blackOpacity,
            'white_sample_opacity' => $whiteOpacity,
            'black_sample_is_visible' => $blackOpacity === null ? null : $blackOpacity > 0.0,
            'white_sample_is_visible' => $whiteOpacity === null ? null : $whiteOpacity > 0.0,
        ];
    }

    private function ccittFaxCodingMode(int $k): string
    {
        if ($k < 0) {
            return 'group4_two_dimensional';
        }

        if ($k > 0) {
            return 'group3_mixed_two_dimensional';
        }

        return 'group3_one_dimensional';
    }

    private function ccittFaxEndOfBlockMarkerName(int $k, bool $endOfBlock): ?string
    {
        if (!$endOfBlock) {
            return null;
        }

        return $k < 0 ? 'eofb' : 'rtc';
    }

    /**
     * @param array<string, int|bool|string|null|list<string>>|null $decodeParms
     * @param array<string, true> $invalidLookup
     */
    private function ccittEffectiveInt(?array $decodeParms, string $field, int $default, array $invalidLookup): int
    {
        $value = $decodeParms[$field] ?? null;

        return is_int($value) && !isset($invalidLookup[$field]) ? $value : $default;
    }

    /**
     * @param array<string, int|bool|string|null|list<string>>|null $decodeParms
     * @param array<string, true> $invalidLookup
     */
    private function ccittEffectivePositiveInt(?array $decodeParms, string $field, int $default, array $invalidLookup): int
    {
        $value = $this->ccittEffectiveInt($decodeParms, $field, $default, $invalidLookup);

        return $value >= 1 ? $value : $default;
    }

    /**
     * @param array<string, int|bool|string|null|list<string>>|null $decodeParms
     * @param array<string, true> $invalidLookup
     */
    private function ccittEffectiveNonNegativeInt(?array $decodeParms, string $field, int $default, array $invalidLookup): int
    {
        $value = $this->ccittEffectiveInt($decodeParms, $field, $default, $invalidLookup);

        return $value >= 0 ? $value : $default;
    }

    /**
     * @param array<string, int|bool|string|null|list<string>>|null $decodeParms
     * @param array<string, true> $invalidLookup
     */
    private function ccittEffectiveBool(?array $decodeParms, string $field, bool $default, array $invalidLookup): bool
    {
        $value = $decodeParms[$field] ?? null;

        return is_bool($value) && !isset($invalidLookup[$field]) ? $value : $default;
    }

    /**
     * @param array<string, int|bool|string|null|list<string>>|null $decodeParms
     * @param array<string, true> $invalidLookup
     * @param list<string> $fields
     * @return list<string>
     */
    private function ccittDefaultsApplied(?array $decodeParms, array $invalidLookup, array $fields): array
    {
        $defaults = [];
        foreach ($fields as $field) {
            if ($decodeParms === null || !array_key_exists($field, $decodeParms) || $decodeParms[$field] === null || isset($invalidLookup[$field])) {
                $defaults[] = $field;
            }
        }

        return $defaults;
    }

    private function imageColorSpace(string $dictionary): ?string
    {
        if (preg_match('/\/ColorSpace\s+(?:\/([^\s\[\]()<>{}\/%]+)|\[\s*\/([^\s\[\]()<>{}\/%]+))/s', $dictionary, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1] !== '' ? $match[1] : $match[2]);
    }

    private function imageBitsPerComponent(string $dictionary, array $objects = []): ?int
    {
        $value = $this->extractPdfNameValue($dictionary, 'BitsPerComponent')
            ?? $this->extractPdfNameValue($dictionary, 'BPC');
        if ($value === null) {
            return null;
        }

        $integer = $this->integerFromPdfValue($value, $objects);
        return $integer === null ? null : max(1, $integer);
    }

    /**
     * @param array<int|string, mixed> $objects
     * @return array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null
     */
    private function imageDecodeDetails(string $dictionary, array $objects, ?int $expectedComponents, bool $defaultIfMissing = false): ?array
    {
        if ($this->duplicatePdfNameDeclarationCount($dictionary, 'Decode') > 0) {
            return $this->buildImageDecodeDetails([], $expectedComponents, 'duplicate');
        }

        $value = $this->extractPdfNameValue($dictionary, 'Decode');
        if ($value === null) {
            if (!$defaultIfMissing) {
                return null;
            }

            return $this->buildImageDecodeDetails([0.0, 1.0], $expectedComponents, 'default');
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if ($resolved === 'null') {
            if (!$defaultIfMissing) {
                return null;
            }

            return $this->buildImageDecodeDetails([0.0, 1.0], $expectedComponents, 'default');
        }

        if (!str_starts_with($resolved, '[')) {
            return $this->buildImageDecodeDetails([], $expectedComponents, 'invalid');
        }

        $numbers = $this->strictNumericArrayValue($resolved, $objects);
        if ($numbers === null) {
            return $this->buildImageDecodeDetails([], $expectedComponents, 'invalid');
        }

        return $this->buildImageDecodeDetails($numbers, $expectedComponents, 'explicit');
    }

    /**
     * @param list<float> $values
     * @return array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}
     */
    private function buildImageDecodeDetails(array $values, ?int $expectedComponents, string $source): array
    {
        $ranges = [];
        $inverted = [];
        $pairCount = intdiv(count($values), 2);
        for ($index = 0; $index < $pairCount; $index++) {
            $min = (float) $values[$index * 2];
            $max = (float) $values[($index * 2) + 1];
            $ranges[] = ['min' => $min, 'max' => $max];
            if ($min > $max) {
                $inverted[] = $index;
            }
        }

        $validPairs = count($values) > 0 && count($values) % 2 === 0;
        $validComponents = $expectedComponents === null || $pairCount === $expectedComponents;
        $identity = $ranges !== [];
        foreach ($ranges as $range) {
            if (abs($range['min']) > 0.000001 || abs($range['max'] - 1.0) > 0.000001) {
                $identity = false;
                break;
            }
        }

        return [
            'ranges' => $ranges,
            'component_count' => $pairCount,
            'expected_components' => $expectedComponents,
            'valid_for_components' => $validPairs && $validComponents,
            'identity' => $identity,
            'inverted_components' => $inverted,
            'source' => $source,
        ];
    }

    /**
     * @param array<int|string, mixed> $objects
     * @return list<float>|null
     */
    private function strictNumericArrayValue(string $value, array $objects = []): ?array
    {
        $trimmed = trim($this->resolvePdfValue($value, $objects));
        if (!str_starts_with($trimmed, '[') || !str_ends_with($trimmed, ']')) {
            return null;
        }

        $numbers = [];
        $offset = 1;
        $end = strlen($trimmed) - 1;
        while ($offset < $end) {
            $offset = $this->skipPdfWhitespace($trimmed, $offset);
            if ($offset >= $end) {
                break;
            }

            $read = $this->readPdfValueWithOffset($trimmed, $offset);
            if ($read === null || $read['next'] <= $offset || $read['next'] > $end) {
                return null;
            }

            $resolved = trim($this->resolvePdfValue($read['value'], $objects));
            if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $resolved) !== 1) {
                return null;
            }

            $numbers[] = (float) $resolved;
            $offset = $read['next'];
        }

        return $numbers;
    }

    /**
     * @param array<int|string, mixed> $objects
     * @param array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null $decodePlan
     * @return array{present: bool, width: int|null, height: int|null, bits_per_component: int, decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}, opacity_for_zero: float, opacity_for_one: float, inverted: bool}|null
     */
    private function imageMaskDetails(string $dictionary, array $objects, ?array $decodePlan = null): ?array
    {
        $imageMask = $this->booleanNameValue($dictionary, 'ImageMask');
        if ($imageMask !== true) {
            return null;
        }

        $bitsPerComponent = $this->imageBitsPerComponent($dictionary, $objects) ?? 1;
        $decode = $decodePlan ?? $this->imageDecodeDetails($dictionary, $objects, 1, true);
        if ($decode === null) {
            $decode = $this->buildImageDecodeDetails([0.0, 1.0], 1, 'default');
        }

        $opacityForZero = 0.0;
        $opacityForOne = 1.0;
        if ($decode['valid_for_components']) {
            $opacityForZero = $this->imageSampleDecodeValues([0], $decode, $bitsPerComponent)[0];
            $opacityForOne = $this->imageSampleDecodeValues([(2 ** min($bitsPerComponent, 30)) - 1], $decode, $bitsPerComponent)[0];
        }

        return [
            'present' => true,
            'width' => $this->integerNameValue($dictionary, 'Width', $objects),
            'height' => $this->integerNameValue($dictionary, 'Height', $objects),
            'bits_per_component' => $bitsPerComponent,
            'decode' => $decode,
            'opacity_for_zero' => max(0.0, min(1.0, $opacityForZero)),
            'opacity_for_one' => max(0.0, min(1.0, $opacityForOne)),
            'inverted' => $decode['valid_for_components'] && $decode['inverted_components'] !== [],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{present: bool, ranges: list<array{min: int, max: int}>, component_count: int, expected_components: int|null, valid_for_components: bool, source: string, compares_before_decode: bool, transparent_when_all_components_match: bool}|null
     */
    private function colorKeyMaskDetails(string $dictionary, array $objects, ?int $expectedComponents): ?array
    {
        $value = $this->extractPdfNameValue($dictionary, 'Mask');
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if (!str_starts_with($resolved, '[')) {
            return null;
        }

        $values = [];
        $allIntegers = true;
        foreach ($this->pdfArrayValues($resolved) as $entry) {
            $entry = trim($this->resolvePdfValue($entry, $objects));
            if (preg_match('/^[+-]?\d+$/', $entry) !== 1) {
                $allIntegers = false;
                break;
            }

            $values[] = (int) $entry;
        }

        $ranges = [];
        $pairCount = $allIntegers ? intdiv(count($values), 2) : 0;
        for ($index = 0; $index < $pairCount; $index++) {
            $ranges[] = [
                'min' => $values[$index * 2],
                'max' => $values[($index * 2) + 1],
            ];
        }

        $validPairs = $allIntegers && count($values) > 0 && count($values) % 2 === 0;
        $validComponents = $expectedComponents === null || $pairCount === $expectedComponents;

        return [
            'present' => true,
            'ranges' => $ranges,
            'component_count' => $pairCount,
            'expected_components' => $expectedComponents,
            'valid_for_components' => $validPairs && $validComponents,
            'source' => 'explicit',
            'compares_before_decode' => true,
            'transparent_when_all_components_match' => true,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctDecodeFilterName(string $dictionary, array $objects): ?string
    {
        foreach ($this->imageFilterValues($dictionary, $objects) as $filter) {
            if ($filter === 'DCTDecode' || $filter === 'DCT') {
                return $filter;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctDecodeParmsColorTransform(string $dictionary, array $objects = []): ?int
    {
        $filters = $this->imageFilterValues($dictionary, $objects);
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        foreach ($filters as $index => $filter) {
            if ($filter !== 'DCTDecode' && $filter !== 'DCT') {
                continue;
            }

            return $this->dctDecodeParmsColorTransformFromValue(
                $this->decodeParmsValueForImageFilterIndex($filters, $decodeParms, $index),
                $objects
            );
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctDecodeParmsAlignmentIsInvalid(string $dictionary, array $objects = []): bool
    {
        $filters = $this->imageFilterValues($dictionary, $objects);
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        if ($decodeParms === []) {
            return false;
        }

        $hasDeclaredNonNullDecodeParms = false;
        foreach ($decodeParms as $value) {
            if ($value !== null && trim($value) !== '') {
                $hasDeclaredNonNullDecodeParms = true;
                break;
            }
        }
        if (!$hasDeclaredNonNullDecodeParms) {
            return false;
        }

        $dctFilterIndexes = [];
        foreach ($filters as $index => $filter) {
            if ($filter !== 'DCTDecode' && $filter !== 'DCT') {
                continue;
            }

            $dctFilterIndexes[] = $index;
        }

        if ($dctFilterIndexes === []) {
            return false;
        }

        if ($this->unappliedNonNullDecodeParmsSlots($filters, $decodeParms) !== []) {
            return true;
        }

        foreach ($dctFilterIndexes as $index) {
            return $this->decodeParmsIndexForImageFilterIndex($filters, $decodeParms, $index) === null;
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctDecodeParmsColorTransformFromValue(?string $value, array $objects): ?int
    {
        if ($value === null) {
            return null;
        }

        $decodeParms = trim($this->resolvePdfValue($value, $objects));
        if (str_starts_with($decodeParms, '[')) {
            foreach ($this->pdfArrayValues($decodeParms) as $entry) {
                $colorTransform = $this->dctDecodeParmsColorTransformFromValue($entry, $objects);
                if ($colorTransform !== null) {
                    return $colorTransform;
                }
            }

            return null;
        }

        if (!str_starts_with($decodeParms, '<<')) {
            return null;
        }

        $colorTransform = $this->integerNameValue($decodeParms, 'ColorTransform');
        if ($colorTransform === null) {
            return null;
        }

        return $colorTransform;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctDecodeParmsColorTransformIsDuplicated(string $dictionary, array $objects = []): bool
    {
        $filters = $this->imageFilterValues($dictionary, $objects);
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        foreach ($filters as $index => $filter) {
            if ($filter !== 'DCTDecode' && $filter !== 'DCT') {
                continue;
            }

            return $this->dctDecodeParmsColorTransformIsDuplicatedInValue(
                $this->decodeParmsValueForImageFilterIndex($filters, $decodeParms, $index),
                $objects
            );
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctDecodeParmsColorTransformIsDuplicatedInValue(?string $value, array $objects): bool
    {
        if ($value === null) {
            return false;
        }

        $decodeParms = trim($this->resolvePdfValue($value, $objects));
        if (str_starts_with($decodeParms, '[')) {
            foreach ($this->pdfArrayValues($decodeParms) as $entry) {
                if ($this->dctDecodeParmsColorTransformIsDuplicatedInValue($entry, $objects)) {
                    return true;
                }
            }

            return false;
        }

        if (!str_starts_with($decodeParms, '<<')) {
            return false;
        }

        return count($this->pdfDictionaryValuesForName($decodeParms, 'ColorTransform')) > 1;
    }

    private function componentCountForColorSpace(string $colorSpace): ?int
    {
        return match ($colorSpace) {
            'DeviceGray', 'CalGray' => 1,
            'DeviceRGB', 'CalRGB', 'Lab' => 3,
            'DeviceCMYK' => 4,
            default => null,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array{source_color_space: string, components: int|null, uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, uses_indexed_color_space: bool, indexed_color_space: array{base_color_space: string|null, base_components: int|null, base_uses_icc_profile: bool, base_icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, high_value: int|null, lookup_source: string|null, lookup_length: int|null, expected_lookup_length: int|null, lookup_length_matches: bool, lookup_entry_count: int|null, lookup_preview_hex: string, lookup_bytes: list<int>}|null}
     */
    private function imageColorSpaceDetails(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfNameValue($dictionary, 'ColorSpace')
            ?? $this->extractPdfNameValue($dictionary, 'CS');

        if ($value === null) {
            return [
                'source_color_space' => 'DeviceRGB',
                'components' => 3,
                'uses_icc_profile' => false,
                'icc_profile' => null,
                'uses_calibrated_color_space' => false,
                'calibrated_color_space' => null,
                'uses_alternate_color_space' => false,
                'alternate_color_space' => null,
                'uses_indexed_color_space' => false,
                'indexed_color_space' => null,
            ];
        }

        return $this->colorSpaceDetailsFromValue($value, $objects);
    }

    /**
     * @param array<int|string, mixed> $objects
     * @param array<int, true> $seenObjects
     * @return array{source_color_space: string, components: int|null, uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, uses_indexed_color_space: bool, indexed_color_space: array{base_color_space: string|null, base_components: int|null, base_uses_icc_profile: bool, base_icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, high_value: int|null, lookup_source: string|null, lookup_length: int|null, expected_lookup_length: int|null, lookup_length_matches: bool, lookup_entry_count: int|null, lookup_preview_hex: string, lookup_bytes: list<int>}|null}
     */
    private function colorSpaceDetailsFromValue(string $value, array $objects, array $seenObjects = [], array $seenColorSpaces = []): array
    {
        $resolvedValue = $this->resolvePdfValueWithSeen($value, $objects, $seenObjects);
        $resolved = $resolvedValue['value'];
        $seenObjects = $resolvedValue['seen'];

        if (str_starts_with($resolved, '[')) {
            $values = $this->pdfArrayValues($resolved);
            $family = isset($values[0]) ? $this->pdfNameValue($values[0]) : null;
            $family = $family === null ? 'DeviceRGB' : $this->normalizeColorSpaceName($family);

            if ($family === 'ICCBased') {
                $profile = isset($values[1]) ? trim($this->resolvePdfValue($values[1], $objects, $seenObjects)) : '';
                $components = $this->integerNameValue($profile, 'N');
                $alternateValue = $this->extractPdfNameValue($profile, 'Alternate');
                $alternate = $alternateValue === null ? null : $this->colorSpaceNameFromValue($alternateValue, $objects, $seenObjects);
                $rangeValue = $this->extractPdfNameValue($profile, 'Range');

                return [
                    'source_color_space' => 'ICCBased',
                    'components' => $components ?? ($alternate === null ? null : $this->componentCountForColorSpace($alternate)),
                    'uses_icc_profile' => true,
                    'icc_profile' => [
                        'components' => $components,
                        'alternate_color_space' => $alternate,
                        'range' => $this->numericArrayValue($rangeValue),
                        'length' => $this->integerNameValue($profile, 'Length'),
                    ],
                    'uses_calibrated_color_space' => false,
                    'calibrated_color_space' => null,
                    'uses_alternate_color_space' => false,
                    'alternate_color_space' => null,
                    'uses_indexed_color_space' => false,
                    'indexed_color_space' => null,
                ];
            }

            if ($family === 'Separation' || $family === 'DeviceN') {
                return $this->alternateColorSpaceDetails($family, $values, $objects, $seenObjects);
            }

            if ($family === 'Indexed') {
                return $this->indexedColorSpaceDetails($values, $objects, $seenObjects);
            }

            if (in_array($family, ['CalGray', 'CalRGB', 'Lab'], true)) {
                return $this->calibratedColorSpaceDetails($family, $values, $objects, $seenObjects);
            }

            return [
                'source_color_space' => $family,
                'components' => $this->componentCountForColorSpace($family),
                'uses_icc_profile' => false,
                'icc_profile' => null,
                'uses_calibrated_color_space' => false,
                'calibrated_color_space' => null,
                'uses_alternate_color_space' => false,
                'alternate_color_space' => null,
                'uses_indexed_color_space' => false,
                'indexed_color_space' => null,
            ];
        }

        $name = $this->pdfNameValue($resolved);
        $colorSpace = $name === null ? 'DeviceRGB' : $this->normalizeColorSpaceName($name);
        if ($name !== null && $this->isNamedColorSpaceResourceCandidate($colorSpace)) {
            $resource = $this->colorSpaceResourceValue($name, $objects);
            if ($resource !== null && !isset($seenColorSpaces[$name])) {
                $seenColorSpaces[$name] = true;
                $details = $this->colorSpaceDetailsFromValue($resource['value'], $objects, $seenObjects, $seenColorSpaces);
                $details['color_space_resource_name'] = $name;
                $details['color_space_resource_value'] = $resource['value'];
                $details['color_space_resource_source'] = $resource['source'];
                $details['color_space_resolved_from_resources'] = true;

                return $details;
            }
        }

        return [
            'source_color_space' => $colorSpace,
            'components' => $this->componentCountForColorSpace($colorSpace),
            'uses_icc_profile' => false,
            'icc_profile' => null,
            'uses_calibrated_color_space' => false,
            'calibrated_color_space' => null,
            'uses_alternate_color_space' => false,
            'alternate_color_space' => null,
            'uses_indexed_color_space' => false,
            'indexed_color_space' => null,
        ];
    }

    /**
     * @param list<string> $values
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{source_color_space: string, components: int, uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, uses_alternate_color_space: bool, alternate_color_space: array{family: string, colorant_names: list<string>, alternate_color_space: string|null, alternate_components: int|null, alternate_uses_icc_profile: bool, tint_transform_source: string|null, tint_transform_object: int|null, tint_transform_function_type: int|null, attributes_present: bool}, uses_indexed_color_space: bool, indexed_color_space: null}
     */
    private function alternateColorSpaceDetails(string $family, array $values, array $objects, array $seenObjects): array
    {
        $colorantNames = isset($values[1]) ? $this->colorantNamesFromValue($values[1], $objects, $seenObjects) : [];
        $alternateIndex = 2;
        $tintTransformIndex = 3;
        $attributesIndex = $family === 'DeviceN' ? 4 : null;
        $alternate = isset($values[$alternateIndex])
            ? $this->colorSpaceDetailsFromValue($values[$alternateIndex], $objects, $seenObjects)
            : [
                'source_color_space' => null,
                'components' => null,
                'uses_icc_profile' => false,
                'icc_profile' => null,
                'uses_calibrated_color_space' => false,
                'calibrated_color_space' => null,
                'uses_alternate_color_space' => false,
                'alternate_color_space' => null,
                'uses_indexed_color_space' => false,
                'indexed_color_space' => null,
            ];
        $tintTransform = $values[$tintTransformIndex] ?? null;
        $resolvedTintTransform = $tintTransform === null ? '' : trim($this->resolvePdfValue($tintTransform, $objects, $seenObjects));

        return [
            'source_color_space' => $family,
            'components' => $family === 'Separation' ? 1 : count($colorantNames),
            'uses_icc_profile' => (bool) $alternate['uses_icc_profile'],
            'icc_profile' => $alternate['icc_profile'],
            'uses_alternate_color_space' => true,
            'alternate_color_space' => [
                'family' => $family,
                'colorant_names' => $colorantNames,
                'alternate_color_space' => $alternate['source_color_space'],
                'alternate_components' => $alternate['components'],
                'alternate_uses_icc_profile' => (bool) $alternate['uses_icc_profile'],
                'tint_transform_source' => $this->pdfValueSource($tintTransform),
                'tint_transform_object' => $tintTransform === null ? null : $this->objectReferenceNumber($tintTransform),
                'tint_transform_function_type' => $resolvedTintTransform === '' ? null : $this->integerNameValue($resolvedTintTransform, 'FunctionType'),
                'attributes_present' => $attributesIndex !== null && isset($values[$attributesIndex]) && trim($values[$attributesIndex]) !== 'null',
            ],
            'uses_indexed_color_space' => false,
            'indexed_color_space' => null,
        ];
    }

    /**
     * @param list<string> $values
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{source_color_space: string, components: int, uses_icc_profile: false, icc_profile: null, uses_calibrated_color_space: true, calibrated_color_space: array{family: string, dictionary_source: string|null, dictionary_object: int|null, white_point: list<float>, black_point: list<float>, gamma: float|list<float>|null, matrix: list<float>|null, range: list<float>|null, default_decode: list<float>}, uses_alternate_color_space: false, alternate_color_space: null, uses_indexed_color_space: false, indexed_color_space: null}
     */
    private function calibratedColorSpaceDetails(string $family, array $values, array $objects, array $seenObjects): array
    {
        $dictionaryValue = $values[1] ?? null;
        $dictionary = $dictionaryValue === null ? '' : trim($this->resolvePdfValue($dictionaryValue, $objects, $seenObjects));
        $whitePoint = $this->numericArrayValue($this->extractPdfNameValue($dictionary, 'WhitePoint'));
        $blackPoint = $this->numericArrayValue($this->extractPdfNameValue($dictionary, 'BlackPoint'));
        $range = $this->numericArrayValue($this->extractPdfNameValue($dictionary, 'Range'));

        if (count($blackPoint) !== 3) {
            $blackPoint = [0.0, 0.0, 0.0];
        }
        if ($family === 'Lab' && count($range) !== 4) {
            $range = [-100.0, 100.0, -100.0, 100.0];
        }

        $gamma = null;
        $matrix = null;
        $defaultDecode = [0.0, 1.0];
        if ($family === 'CalRGB') {
            $gamma = $this->numericArrayValue($this->extractPdfNameValue($dictionary, 'Gamma'));
            if (!is_array($gamma) || count($gamma) !== 3) {
                $gamma = [1.0, 1.0, 1.0];
            }
            $matrix = $this->numericArrayValue($this->extractPdfNameValue($dictionary, 'Matrix'));
            if (count($matrix) !== 9) {
                $matrix = [1.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 1.0];
            }
            $defaultDecode = [0.0, 1.0, 0.0, 1.0, 0.0, 1.0];
        } elseif ($family === 'Lab') {
            $defaultDecode = [0.0, 100.0, $range[0], $range[1], $range[2], $range[3]];
        } else {
            $gammaValue = $this->floatNameValue($dictionary, 'Gamma');
            $gamma = $gammaValue ?? 1.0;
        }

        return [
            'source_color_space' => $family,
            'components' => $this->componentCountForColorSpace($family) ?? 1,
            'uses_icc_profile' => false,
            'icc_profile' => null,
            'uses_calibrated_color_space' => true,
            'calibrated_color_space' => [
                'family' => $family,
                'dictionary_source' => $this->pdfValueSource($dictionaryValue),
                'dictionary_object' => $dictionaryValue === null ? null : $this->objectReferenceNumber($dictionaryValue),
                'white_point' => $whitePoint,
                'black_point' => $blackPoint,
                'gamma' => $gamma,
                'matrix' => $matrix,
                'range' => $range === [] ? null : $range,
                'default_decode' => $defaultDecode,
            ],
            'uses_alternate_color_space' => false,
            'alternate_color_space' => null,
            'uses_indexed_color_space' => false,
            'indexed_color_space' => null,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return list<string>
     */
    private function colorantNamesFromValue(string $value, array $objects, array $seenObjects): array
    {
        $resolved = trim($this->resolvePdfValue($value, $objects, $seenObjects));
        if (str_starts_with($resolved, '[')) {
            $names = [];
            foreach ($this->pdfArrayValues($resolved) as $entry) {
                $name = $this->pdfNameValue($this->resolvePdfValue($entry, $objects, $seenObjects));
                if ($name !== null) {
                    $names[] = $name;
                }
            }

            return $names;
        }

        $name = $this->pdfNameValue($resolved);

        return $name === null ? [] : [$name];
    }

    private function objectReferenceNumber(string $value): ?int
    {
        return preg_match('/^(\d+)\s+\d+\s+R$/', trim($value), $match) === 1 ? (int) $match[1] : null;
    }

    private function pdfValueSource(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($this->objectReferenceNumber($trimmed) !== null) {
            return 'object_ref';
        }
        if (str_starts_with($trimmed, '<<')) {
            return 'dictionary';
        }
        if (str_starts_with($trimmed, '[')) {
            return 'array';
        }

        return 'direct';
    }

    /**
     * @param list<string> $values
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{source_color_space: string, components: int, uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, uses_indexed_color_space: bool, indexed_color_space: array{base_color_space: string|null, base_components: int|null, base_uses_icc_profile: bool, base_icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, high_value: int|null, lookup_source: string|null, lookup_length: int|null, expected_lookup_length: int|null, lookup_length_matches: bool, lookup_entry_count: int|null, lookup_preview_hex: string, lookup_bytes: list<int>}}
     */
    private function indexedColorSpaceDetails(array $values, array $objects, array $seenObjects): array
    {
        $base = isset($values[1])
            ? $this->colorSpaceDetailsFromValue($values[1], $objects, $seenObjects)
            : [
                'source_color_space' => null,
                'components' => null,
                'uses_icc_profile' => false,
                'icc_profile' => null,
                'uses_calibrated_color_space' => false,
                'calibrated_color_space' => null,
                'uses_alternate_color_space' => false,
                'alternate_color_space' => null,
                'uses_indexed_color_space' => false,
                'indexed_color_space' => null,
            ];
        $highValue = isset($values[2]) ? $this->integerFromPdfValue($values[2], $objects, $seenObjects) : null;
        $lookup = isset($values[3]) ? $this->pdfBytesFromValue($values[3], $objects, $seenObjects) : null;
        $baseComponents = $base['components'];
        $lookupBytes = $lookup['bytes'] ?? '';
        $lookupLength = $lookup === null ? null : strlen($lookupBytes);
        $expectedLength = is_int($highValue) && is_int($baseComponents) && $baseComponents > 0
            ? ($highValue + 1) * $baseComponents
            : null;
        $lookupLengthMatches = $lookupLength !== null && $expectedLength !== null && $lookupLength === $expectedLength;

        return [
            'source_color_space' => 'Indexed',
            'components' => 1,
            'uses_icc_profile' => $base['uses_icc_profile'],
            'icc_profile' => $base['icc_profile'],
            'uses_calibrated_color_space' => false,
            'calibrated_color_space' => null,
            'uses_alternate_color_space' => false,
            'alternate_color_space' => null,
            'uses_indexed_color_space' => true,
            'indexed_color_space' => [
                'base_color_space' => $base['source_color_space'],
                'base_components' => $baseComponents,
                'base_uses_icc_profile' => $base['uses_icc_profile'],
                'base_icc_profile' => $base['icc_profile'],
                'base_uses_alternate_color_space' => ($base['uses_alternate_color_space'] ?? false) === true,
                'base_alternate_color_space' => $base['alternate_color_space'] ?? null,
                'high_value' => $highValue,
                'lookup_source' => $lookup['source'] ?? null,
                'lookup_length' => $lookupLength,
                'expected_lookup_length' => $expectedLength,
                'lookup_length_matches' => $lookupLengthMatches,
                'lookup_entry_count' => $lookupLength !== null && is_int($baseComponents) && $baseComponents > 0
                    ? intdiv($lookupLength, $baseComponents)
                    : null,
                'lookup_preview_hex' => strtoupper(bin2hex(substr($lookupBytes, 0, 24))),
                'lookup_bytes' => $this->byteList($lookupBytes),
            ],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function colorSpaceNameFromValue(string $value, array $objects, array $seenObjects = []): ?string
    {
        $resolvedValue = $this->resolvePdfValueWithSeen($value, $objects, $seenObjects);
        $resolved = $resolvedValue['value'];
        $seenObjects = $resolvedValue['seen'];
        if (str_starts_with($resolved, '[')) {
            $values = $this->pdfArrayValues($resolved);
            $name = isset($values[0]) ? $this->pdfNameValue($values[0]) : null;

            return $name === null ? null : $this->normalizeColorSpaceName($name);
        }

        $name = $this->pdfNameValue($resolved);

        return $name === null ? null : $this->normalizeColorSpaceName($name);
    }

    private function normalizeColorSpaceName(string $name): string
    {
        return match ($name) {
            'G' => 'DeviceGray',
            'RGB' => 'DeviceRGB',
            'CMYK' => 'DeviceCMYK',
            'I' => 'Indexed',
            default => $name,
        };
    }

    private function isNamedColorSpaceResourceCandidate(string $colorSpace): bool
    {
        return !in_array($colorSpace, [
            'DeviceGray',
            'DeviceRGB',
            'DeviceCMYK',
            'CalGray',
            'CalRGB',
            'Lab',
            'ICCBased',
            'Indexed',
            'Separation',
            'DeviceN',
            'Pattern',
        ], true);
    }

    /**
     * @param array<int|string, mixed> $objects
     * @return array{value: string, source: string}|null
     */
    private function colorSpaceResourceValue(string $name, array $objects): ?array
    {
        foreach (['ColorSpace', 'ColorSpaces', 'color_space', 'color_spaces', 'colorSpace', 'colorSpaces'] as $mapKey) {
            if (!array_key_exists($mapKey, $objects)) {
                continue;
            }

            $value = $this->colorSpaceResourceMapEntry($objects[$mapKey], $name, $objects);
            if ($value !== null) {
                return ['value' => $value, 'source' => $mapKey];
            }
        }

        foreach (['Resources', 'resources'] as $resourceKey) {
            if (!isset($objects[$resourceKey]) || !is_string($objects[$resourceKey])) {
                continue;
            }

            $resources = trim($this->resolvePdfValue($objects[$resourceKey], $objects));
            $colorSpacesValue = $this->pdfDictionaryValueForName($resources, 'ColorSpace');
            if ($colorSpacesValue === null) {
                continue;
            }

            $colorSpaces = trim($this->resolvePdfValue($colorSpacesValue, $objects));
            $value = $this->pdfDictionaryValueForName($colorSpaces, $name);
            if ($value !== null) {
                return ['value' => trim($value), 'source' => $resourceKey . '.ColorSpace'];
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $objects
     */
    private function colorSpaceResourceMapEntry(mixed $map, string $name, array $objects): ?string
    {
        if (is_array($map)) {
            foreach ([$name, '/' . $name] as $key) {
                if (array_key_exists($key, $map)) {
                    return $this->pdfResourceEntryToString($map[$key]);
                }
            }

            return null;
        }

        if (is_string($map)) {
            $dictionary = trim($this->resolvePdfValue($map, $objects));

            return $this->pdfDictionaryValueForName($dictionary, $name);
        }

        return null;
    }

    private function pdfResourceEntryToString(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $entry) {
                if (!is_string($entry) && !is_int($entry) && !is_float($entry)) {
                    return null;
                }

                $parts[] = (string) $entry;
            }

            return '[' . implode(' ', $parts) . ']';
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{present: bool, subtype: string|null, width: int|null, height: int|null, color_space: string|null, components: int|null, bits_per_component: int|null, decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null, opacity_for_zero: float|null, opacity_for_max: float|null, decode_inverted: bool, decode_component_mismatch: bool, matte: list<float>|null, interpolate: bool|null}|null
     */
    private function imageSoftMaskDetails(string $dictionary, array $objects): ?array
    {
        $value = $this->extractPdfNameValue($dictionary, 'SMask');
        if ($value === null) {
            return null;
        }

        if ($this->pdfNameValue($value) === 'None') {
            return [
                'present' => false,
                'subtype' => null,
                'width' => null,
                'height' => null,
                'color_space' => null,
                'components' => null,
                'bits_per_component' => null,
                'decode' => null,
                'opacity_for_zero' => null,
                'opacity_for_max' => null,
                'decode_inverted' => false,
                'decode_component_mismatch' => false,
                'matte' => null,
                'interpolate' => null,
            ];
        }

        $maskDictionary = trim($this->resolvePdfValue($value, $objects));
        if ($this->isSoftMaskTransparencyDictionary($maskDictionary)) {
            $groupDetails = $this->imageSoftMaskGroupDetails($dictionary, $objects);

            return [
                'present' => true,
                'subtype' => $this->dictionaryNameValue($maskDictionary, 'S'),
                'width' => null,
                'height' => null,
                'color_space' => is_array($groupDetails) ? ($groupDetails['group_color_space'] ?? null) : null,
                'components' => is_array($groupDetails) ? ($groupDetails['group_components'] ?? null) : null,
                'bits_per_component' => null,
                'decode' => null,
                'opacity_for_zero' => null,
                'opacity_for_max' => null,
                'decode_inverted' => false,
                'decode_component_mismatch' => false,
                'matte' => null,
                'interpolate' => null,
            ];
        }

        $colorSpace = $this->imageColorSpaceDetails($maskDictionary, $objects);
        $bitsPerComponent = $this->imageBitsPerComponent($maskDictionary);
        $decode = $this->imageDecodeDetails($maskDictionary, $objects, $colorSpace['components'], true);
        $matte = $this->numericArrayValue($this->extractPdfNameValue($maskDictionary, 'Matte'));
        $alphaCompatible = ($colorSpace['components'] ?? null) === 1
            && in_array($colorSpace['source_color_space'], ['DeviceGray', 'CalGray', 'ICCBased'], true);
        $opacityForZero = null;
        $opacityForMax = null;
        if ($alphaCompatible && $decode !== null && $decode['valid_for_components']) {
            $maskBits = max(1, $bitsPerComponent ?? 8);
            $opacityForZero = $this->imageSampleDecodeValues([0], $decode, $maskBits)[0];
            $opacityForMax = $this->imageSampleDecodeValues([(2 ** min($maskBits, 30)) - 1], $decode, $maskBits)[0];
        }

        return [
            'present' => true,
            'subtype' => $this->dictionaryNameValue($maskDictionary, 'Subtype'),
            'width' => $this->integerNameValue($maskDictionary, 'Width'),
            'height' => $this->integerNameValue($maskDictionary, 'Height'),
            'color_space' => $colorSpace['source_color_space'],
            'components' => $colorSpace['components'],
            'bits_per_component' => $bitsPerComponent,
            'decode' => $decode,
            'opacity_for_zero' => $opacityForZero === null ? null : max(0.0, min(1.0, $opacityForZero)),
            'opacity_for_max' => $opacityForMax === null ? null : max(0.0, min(1.0, $opacityForMax)),
            'decode_inverted' => $decode !== null && $decode['valid_for_components'] && $decode['inverted_components'] !== [],
            'decode_component_mismatch' => $decode !== null && !$decode['valid_for_components'],
            'matte' => $matte === [] ? null : $matte,
            'interpolate' => $this->booleanNameValue($maskDictionary, 'Interpolate'),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{
     *     present: true,
     *     subtype: string|null,
     *     source_object: int|null,
     *     group_object: int|null,
     *     group_subtype: string|null,
     *     group_bbox: list<float>,
     *     group_color_space: string|null,
     *     group_components: int|null,
     *     group_is_isolated: bool|null,
     *     group_is_knockout: bool|null,
     *     uses_indexed_color_space: bool,
     *     indexed_color_space: array<string, mixed>|null,
     *     backdrop_color: list<float>,
     *     backdrop_component_count: int,
     *     backdrop_matches_group_components: bool,
     *     transfer_function: array<string, mixed>,
     *     review_only: true
     * }|null
     */
    private function imageSoftMaskGroupDetails(string $dictionary, array $objects): ?array
    {
        $value = $this->extractPdfNameValue($dictionary, 'SMask');
        if ($value === null || $this->pdfNameValue($value) === 'None') {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if (!$this->isSoftMaskTransparencyDictionary($resolved)) {
            return null;
        }

        $sourceObject = $this->objectReferenceNumber($value);
        $groupValue = $this->extractPdfNameValue($resolved, 'G');
        $groupObject = $groupValue === null ? null : $this->objectReferenceNumber($groupValue);
        $groupResolved = $groupValue === null ? '' : trim($this->resolvePdfValue($groupValue, $objects));
        $groupDictionary = $this->streamDictionaryFromValue($groupResolved) ?? $groupResolved;
        $groupAttributesValue = $this->extractPdfNameValue($groupDictionary, 'Group');
        $groupAttributes = $groupAttributesValue === null ? '' : trim($this->resolvePdfValue($groupAttributesValue, $objects));
        $groupColorSpaceValue = $groupAttributes === '' ? null : $this->extractPdfNameValue($groupAttributes, 'CS');
        $groupColorSpace = $groupColorSpaceValue === null
            ? null
            : $this->colorSpaceDetailsFromValue($groupColorSpaceValue, $objects);
        $groupComponents = is_array($groupColorSpace) ? $groupColorSpace['components'] : null;
        $backdropColor = $this->numericArrayNameValue($resolved, 'BC', $objects);

        return [
            'present' => true,
            'subtype' => $this->dictionaryNameValue($resolved, 'S'),
            'source_object' => $sourceObject,
            'group_object' => $groupObject,
            'group_subtype' => $this->dictionaryNameValue($groupDictionary, 'Subtype'),
            'group_bbox' => $this->numericArrayNameValue($groupDictionary, 'BBox', $objects),
            'group_color_space' => is_array($groupColorSpace) ? $groupColorSpace['source_color_space'] : null,
            'group_components' => $groupComponents,
            'group_is_isolated' => $groupAttributes === '' ? null : $this->booleanNameValue($groupAttributes, 'I'),
            'group_is_knockout' => $groupAttributes === '' ? null : $this->booleanNameValue($groupAttributes, 'K'),
            'uses_indexed_color_space' => is_array($groupColorSpace) && ($groupColorSpace['uses_indexed_color_space'] ?? false) === true,
            'indexed_color_space' => is_array($groupColorSpace) ? ($groupColorSpace['indexed_color_space'] ?? null) : null,
            'backdrop_color' => $backdropColor,
            'backdrop_component_count' => count($backdropColor),
            'backdrop_matches_group_components' => is_int($groupComponents) && count($backdropColor) === $groupComponents,
            'transfer_function' => $this->softMaskTransferFunctionDetails(
                $this->extractPdfNameValue($resolved, 'TR'),
                $objects
            ),
            'review_only' => true,
        ];
    }

    private function isSoftMaskTransparencyDictionary(string $dictionary): bool
    {
        $subtype = $this->dictionaryNameValue($dictionary, 'S');

        return $this->extractPdfNameValue($dictionary, 'G') !== null
            && ($subtype === 'Alpha' || $subtype === 'Luminosity' || $this->dictionaryNameValue($dictionary, 'Type') === 'Mask');
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function softMaskTransferFunctionDetails(?string $value, array $objects): array
    {
        if ($value === null) {
            return [
                'present' => false,
                'source' => 'default',
                'object' => null,
                'name' => 'Identity',
                'function_type' => null,
                'domain' => [0.0, 1.0],
                'range' => [0.0, 1.0],
                'c0' => [],
                'c1' => [],
                'exponent' => null,
                'output_components' => 1,
                'sample_supported' => true,
                'preview_mode' => 'identity',
            ];
        }

        $name = $this->pdfNameValue($value);
        if ($name !== null) {
            return [
                'present' => true,
                'source' => $this->pdfValueSource($value),
                'object' => null,
                'name' => $name,
                'function_type' => null,
                'domain' => [0.0, 1.0],
                'range' => [0.0, 1.0],
                'c0' => [],
                'c1' => [],
                'exponent' => null,
                'output_components' => $name === 'Identity' ? 1 : null,
                'sample_supported' => $name === 'Identity',
                'preview_mode' => $name === 'Identity' ? 'identity' : 'review_only',
            ];
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        $functionDictionary = $this->streamDictionaryFromValue($resolved) ?? $resolved;
        $functionType = $this->integerNameValue($functionDictionary, 'FunctionType');
        $c0 = $this->numericArrayNameValue($functionDictionary, 'C0', $objects);
        $c1 = $this->numericArrayNameValue($functionDictionary, 'C1', $objects);
        $outputComponents = max(count($c0), count($c1));
        if ($outputComponents === 0) {
            $range = $this->numericArrayNameValue($functionDictionary, 'Range', $objects);
            $outputComponents = count($range) >= 2 ? intdiv(count($range), 2) : null;
        }
        $exponent = $this->floatNameValue($functionDictionary, 'N');
        $sampleSupported = $functionType === 2 && $exponent !== null && $outputComponents === 1;

        return [
            'present' => true,
            'source' => $this->pdfValueSource($value),
            'object' => $this->objectReferenceNumber($value),
            'name' => null,
            'function_type' => $functionType,
            'domain' => $this->numericArrayNameValue($functionDictionary, 'Domain', $objects),
            'range' => $this->numericArrayNameValue($functionDictionary, 'Range', $objects),
            'c0' => $c0,
            'c1' => $c1,
            'exponent' => $exponent,
            'output_components' => $outputComponents,
            'sample_supported' => $sampleSupported,
            'preview_mode' => $sampleSupported ? 'type2_exponential' : 'review_only',
        ];
    }

    /**
     * @param array{present?: bool, color_space?: string|null, components?: int|null} $softMask
     */
    private function softMaskIsGrayscale(array $softMask): bool
    {
        if (($softMask['present'] ?? false) !== true || ($softMask['components'] ?? null) !== 1) {
            return false;
        }

        return in_array($softMask['color_space'] ?? null, ['DeviceGray', 'CalGray', 'ICCBased'], true);
    }

    /**
     * @param array{present?: bool, matte?: list<float>|null}|null $softMask
     * @return array{component_count: int, expected_components: int|null, matches_image_components: bool}|null
     */
    private function softMaskMatteDetails(?array $softMask, ?int $expectedComponents): ?array
    {
        if ($softMask === null || ($softMask['present'] ?? false) !== true || !is_array($softMask['matte'] ?? null)) {
            return null;
        }

        $componentCount = count($softMask['matte']);

        return [
            'component_count' => $componentCount,
            'expected_components' => $expectedComponents,
            'matches_image_components' => is_int($expectedComponents) && $componentCount === $expectedComponents,
        ];
    }

    /**
     * @param array<string, mixed> $imagePlan
     * @return array{present: bool, source_object: int|null, uses_current_object_map: bool|null, decoded_with_current_filters: bool|null, decode_source: string|null, opacity_for_zero: float|null, opacity_for_max: float|null, inverted: bool, component_mismatch: bool, applied_before_rgb: bool}
     */
    private function softMaskDecodeReviewMetadata(array $imagePlan): array
    {
        $softMask = is_array($imagePlan['soft_mask'] ?? null) ? $imagePlan['soft_mask'] : null;
        $boundary = is_array($imagePlan['soft_mask_filter_boundary'] ?? null) ? $imagePlan['soft_mask_filter_boundary'] : null;
        $decode = is_array($softMask['decode'] ?? null) ? $softMask['decode'] : null;

        return [
            'present' => $softMask !== null && ($softMask['present'] ?? false) === true,
            'source_object' => $boundary['source_object'] ?? null,
            'uses_current_object_map' => $boundary['uses_current_object_map'] ?? null,
            'decoded_with_current_filters' => $boundary['decoded_with_current_filters'] ?? null,
            'decode_source' => $decode['source'] ?? null,
            'opacity_for_zero' => $softMask['opacity_for_zero'] ?? null,
            'opacity_for_max' => $softMask['opacity_for_max'] ?? null,
            'inverted' => ($softMask['decode_inverted'] ?? false) === true,
            'component_mismatch' => ($softMask['decode_component_mismatch'] ?? false) === true,
            'applied_before_rgb' => ($imagePlan['soft_mask_decode_applied_before_rgb'] ?? false) === true,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{present: bool, source_object: int|null, filters: list<string>, preview_only_filters: list<string>, unsupported_filters: list<string>, raw_length: int|null, decoded_length: int|null, decoded_sha256: string|null, decoded_preview_hex: string|null, decoded_sample_bytes: list<int>, decoded_with_current_filters: bool, decode_failed: bool, uses_current_object_map: bool, native_prefix_decoded?: true, native_prefix_decoded_length?: int, native_prefix_decoded_sha256?: string, native_prefix_decoded_preview_hex?: string, stopped_before_filter?: string|null}|null
     */
    private function imageSoftMaskFilterBoundary(string $dictionary, array $objects): ?array
    {
        $value = $this->extractPdfNameValue($dictionary, 'SMask');
        if ($value === null) {
            return null;
        }

        $sourceObject = $this->objectReferenceNumber($value);
        $usesCurrentObjectMap = $sourceObject !== null && isset($objects[$sourceObject]);

        if ($this->pdfNameValue($value) === 'None') {
            return [
                'present' => false,
                'source_object' => $sourceObject,
                'filters' => [],
                'preview_only_filters' => [],
                'unsupported_filters' => [],
                'raw_length' => null,
                'decoded_length' => null,
                'decoded_sha256' => null,
                'decoded_preview_hex' => null,
                'decoded_sample_bytes' => [],
                'decoded_with_current_filters' => false,
                'decode_failed' => false,
                'uses_current_object_map' => $usesCurrentObjectMap,
            ];
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        $maskDictionary = $this->streamDictionaryFromValue($resolved) ?? $resolved;
        $stream = $this->streamPayloadBytes($resolved, $objects);
        $filters = $this->imageFilterNames($maskDictionary, $objects);
        $decoded = null;
        $unsupportedFilters = [];
        $decodeFailed = false;
        $nativePrefixDecodedBytes = null;
        $stoppedBeforeFilter = null;

        if ($stream !== null) {
            $decodeResult = $this->decodeImageStreamByFilters($maskDictionary, $stream, $objects, false, true);
            $decoded = $decodeResult['decoded'];
            $unsupportedFilters = $decodeResult['unsupported_filters'];
            $decodeFailed = $decodeResult['decode_failed'];
            if (is_string($decodeResult['native_prefix_decoded_bytes'] ?? null)) {
                $nativePrefixDecodedBytes = $decodeResult['native_prefix_decoded_bytes'];
                $stoppedBeforeFilter = is_string($decodeResult['stopped_before_filter'] ?? null)
                    ? $decodeResult['stopped_before_filter']
                    : null;
            }
        } elseif ($filters !== []) {
            $decodeFailed = true;
        }

        $previewOnlyFilters = array_values(array_filter(
            $filters,
            fn (string $filter): bool => $this->isPreviewOnlyStreamFilter($filter)
        ));
        foreach ($previewOnlyFilters as $filter) {
            if (!in_array($filter, $unsupportedFilters, true)) {
                $unsupportedFilters[] = $filter;
            }
        }

        $boundary = [
            'present' => true,
            'source_object' => $sourceObject,
            'filters' => $this->publicImageFilterList($filters),
            'preview_only_filters' => $this->publicImageFilterList($previewOnlyFilters),
            'unsupported_filters' => $this->publicImageFilterList(array_values($unsupportedFilters)),
            'raw_length' => $stream === null ? null : strlen($stream),
            'decoded_length' => $decoded === null ? null : strlen($decoded),
            'decoded_sha256' => $decoded === null ? null : hash('sha256', $decoded),
            'decoded_preview_hex' => $decoded === null ? null : strtoupper(bin2hex(substr($decoded, 0, 16))),
            'decoded_sample_bytes' => $decoded === null ? [] : $this->byteList(substr($decoded, 0, 16)),
            'decoded_with_current_filters' => $decoded !== null,
            'decode_failed' => $decodeFailed,
            'uses_current_object_map' => $usesCurrentObjectMap,
        ];

        if (is_string($nativePrefixDecodedBytes)) {
            $boundary['native_prefix_decoded'] = true;
            $boundary['native_prefix_decoded_length'] = strlen($nativePrefixDecodedBytes);
            $boundary['native_prefix_decoded_sha256'] = hash('sha256', $nativePrefixDecodedBytes);
            $boundary['native_prefix_decoded_preview_hex'] = strtoupper(bin2hex(substr($nativePrefixDecodedBytes, 0, 16)));
            $boundary['stopped_before_filter'] = $stoppedBeforeFilter;
        }

        return $boundary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function decodedImageStreamPreviewBoundary(string $dictionary, string $imageObject, array $objects): array
    {
        $stream = $this->streamPayloadBytes($imageObject, $objects);
        if ($stream === null) {
            return $this->decodedInlineImageStreamPreviewBoundary($dictionary, null, $objects);
        }

        $boundary = $this->decodedInlineImageStreamPreviewBoundary($dictionary, $stream, $objects, false, true);
        $dctPayload = $this->dctPreviewStreamPayloadBytes($imageObject, $objects);
        $inferredMissingFilterDctPreviewBoundary = $this->imageStreamCanInferMissingDctFilterBoundary(
            $dictionary,
            $boundary['filters'],
            $stream
        );
        if (
            $dctPayload !== null
            && (
                $this->imageFilterOperandBoundaryFilters($boundary['filters']) !== []
                || $inferredMissingFilterDctPreviewBoundary
            )
        ) {
            $boundary['raw_dct_preview_boundary'] = true;
        }
        if ($inferredMissingFilterDctPreviewBoundary) {
            $boundary['decoded_length'] = null;
            $boundary['decoded_sha256'] = null;
            $boundary['decoded_preview_hex'] = null;
            $boundary['decoded_with_current_filters'] = false;
            $boundary['decoded_bytes'] = null;
        }
        $dctMetadataStream = $this->directDctDeclaredStreamPayloadForBoundaryMetadata(
            $dictionary,
            $imageObject,
            $stream,
            $objects
        ) ?? $stream;
        $dctBoundary = $this->dctPreviewStreamBoundaryReviewForFilters(
            $dictionary,
            $dctMetadataStream,
            $objects,
            $boundary['filters'],
            $stream
        );
        if ($dctBoundary !== null) {
            $boundary['dctdecode_stream_boundary'] = $dctBoundary;
        }

        return $boundary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function decodedInlineImageStreamPreviewBoundary(
        string $dictionary,
        ?string $stream,
        array $objects,
        bool $requireExplicitFilterEndMarkers = false,
        bool $recordNativePrefixBoundary = false
    ): array
    {
        $filters = $this->imageFilterNames($dictionary, $objects);
        $decoded = null;
        $unsupportedFilters = [];
        $decodeFailed = false;
        $nativePrefixDecodedBytes = null;
        $stoppedBeforeFilter = null;

        if ($stream !== null) {
            $decodeResult = $this->decodeImageStreamByFilters(
                $dictionary,
                $stream,
                $objects,
                $requireExplicitFilterEndMarkers,
                $requireExplicitFilterEndMarkers || $recordNativePrefixBoundary
            );
            $decoded = $decodeResult['decoded'];
            $unsupportedFilters = $decodeResult['unsupported_filters'];
            $decodeFailed = $decodeResult['decode_failed'];
            if (is_string($decodeResult['native_prefix_decoded_bytes'] ?? null)) {
                $nativePrefixDecodedBytes = $decodeResult['native_prefix_decoded_bytes'];
                $stoppedBeforeFilter = is_string($decodeResult['stopped_before_filter'] ?? null)
                    ? $decodeResult['stopped_before_filter']
                    : null;
            }
        } elseif ($filters !== []) {
            $decodeFailed = true;
        }

        $previewOnlyFilters = array_values(array_filter(
            $filters,
            fn (string $filter): bool => $this->isPreviewOnlyStreamFilter($filter)
        ));
        foreach ($previewOnlyFilters as $filter) {
            if (!in_array($filter, $unsupportedFilters, true)) {
                $unsupportedFilters[] = $filter;
            }
        }

        $filterDetails = $this->imageFilterDetails($dictionary, $objects);
        $boundary = [
            'filters' => $this->publicImageFilterList($filters),
            'filter_details' => $filterDetails,
            'public_filter_details' => $requireExplicitFilterEndMarkers
                && $this->filterDetailsContainDecodeParms($filterDetails),
            'preview_only_filters' => $this->publicImageFilterList($previewOnlyFilters),
            'unsupported_filters' => $this->publicImageFilterList(array_values($unsupportedFilters)),
            'raw_length' => $stream === null ? null : strlen($stream),
            'decoded_length' => $decoded === null ? null : strlen($decoded),
            'decoded_sha256' => $decoded === null ? null : hash('sha256', $decoded),
            'decoded_preview_hex' => $decoded === null ? null : strtoupper(bin2hex(substr($decoded, 0, 16))),
            'decoded_with_current_filters' => $decoded !== null,
            'decode_failed' => $decodeFailed,
            'decoded_bytes' => $decoded,
        ];

        if (is_string($nativePrefixDecodedBytes)) {
            $boundary['native_prefix_decoded'] = true;
            $boundary['native_prefix_decoded_length'] = strlen($nativePrefixDecodedBytes);
            $boundary['native_prefix_decoded_sha256'] = hash('sha256', $nativePrefixDecodedBytes);
            $boundary['native_prefix_decoded_preview_hex'] = strtoupper(bin2hex(substr($nativePrefixDecodedBytes, 0, 16)));
            $boundary['stopped_before_filter'] = $stoppedBeforeFilter;
        }

        return $boundary;
    }

    /**
     * @param list<array{filter: string, preview_only: bool, decode_parms: mixed}> $filterDetails
     */
    private function filterDetailsContainDecodeParms(array $filterDetails): bool
    {
        foreach ($filterDetails as $detail) {
            if (($detail['decode_parms'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function decodedSoftMaskStreamPreviewBoundary(string $dictionary, array $objects): ?array
    {
        $value = $this->extractPdfNameValue($dictionary, 'SMask');
        if ($value === null || $this->pdfNameValue($value) === 'None') {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        $maskDictionary = $this->streamDictionaryFromValue($resolved) ?? $resolved;

        return $this->decodedImageStreamPreviewBoundary($maskDictionary, $resolved, $objects);
    }

    /**
     * @param array<string, mixed> $boundary
     * @return array<string, mixed>
     */
    private function streamBoundaryPublicMetadata(array $boundary): array
    {
        $metadata = [
            'filters' => $boundary['filters'],
            'preview_only_filters' => $boundary['preview_only_filters'],
            'unsupported_filters' => $boundary['unsupported_filters'],
            'raw_length' => $boundary['raw_length'],
            'decoded_length' => $boundary['decoded_length'],
            'decoded_sha256' => $boundary['decoded_sha256'],
            'decoded_preview_hex' => $boundary['decoded_preview_hex'],
            'decoded_with_current_filters' => $boundary['decoded_with_current_filters'],
            'decode_failed' => $boundary['decode_failed'],
        ];

        if (($boundary['public_filter_details'] ?? false) === true && is_array($boundary['filter_details'] ?? null)) {
            $metadata['filter_details'] = $boundary['filter_details'];
        }

        if (($boundary['raw_dct_preview_boundary'] ?? false) === true) {
            $metadata['raw_dct_preview_boundary'] = true;
        }

        if (is_array($boundary['dctdecode_stream_boundary'] ?? null)) {
            $metadata['dctdecode_stream_boundary'] = $boundary['dctdecode_stream_boundary'];
        }

        if (($boundary['native_prefix_decoded'] ?? false) === true) {
            $metadata['native_prefix_decoded'] = true;
            $metadata['native_prefix_decoded_length'] = $boundary['native_prefix_decoded_length'] ?? null;
            $metadata['native_prefix_decoded_sha256'] = $boundary['native_prefix_decoded_sha256'] ?? null;
            $metadata['native_prefix_decoded_preview_hex'] = $boundary['native_prefix_decoded_preview_hex'] ?? null;
            $metadata['stopped_before_filter'] = $boundary['stopped_before_filter'] ?? null;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function imageStreamBoundaryIsReviewOnly(array $metadata): bool
    {
        return ($metadata['preview_only_filters'] ?? []) !== []
            || ($metadata['raw_dct_preview_boundary'] ?? false) === true;
    }

    /**
     * @return array{pixels: list<list<float>>, available_pixel_count: int, available_sample_count: int, complete: bool}
     */
    private function packedImagePixelSamples(string $bytes, int $components, int $bitsPerComponent, int $pixelCount): array
    {
        if ($components < 1 || $bitsPerComponent < 1 || $bitsPerComponent > 30 || $pixelCount < 0) {
            throw new InvalidArgumentException('Image sample packing parameters are invalid.');
        }

        $requiredSamples = $pixelCount * $components;
        $availableSamples = intdiv(strlen($bytes) * 8, $bitsPerComponent);
        $availablePixels = intdiv($availableSamples, $components);
        $readPixels = min($pixelCount, $availablePixels);
        $pixels = [];
        $bitOffset = 0;

        for ($pixel = 0; $pixel < $readPixels; $pixel++) {
            $sample = [];
            for ($component = 0; $component < $components; $component++) {
                $sample[] = (float) $this->readPackedBits($bytes, $bitOffset, $bitsPerComponent);
                $bitOffset += $bitsPerComponent;
            }
            $pixels[] = $sample;
        }

        return [
            'pixels' => $pixels,
            'available_pixel_count' => $availablePixels,
            'available_sample_count' => $availableSamples,
            'complete' => $availableSamples >= $requiredSamples,
        ];
    }

    /**
     * @param array{available_pixel_count: int, available_sample_count: int, complete: bool} $samples
     * @return array{expected_pixel_count: int, available_pixel_count: int, expected_sample_count: int, available_sample_count: int, surplus_sample_count: int, bits_per_component: int, expected_byte_count: int, decoded_byte_count: int|null, surplus_byte_count: int|null, complete: bool, truncated_to_declared_samples: bool}
     */
    private function imageSampleBoundaryMetadata(
        array $samples,
        int $expectedPixelCount,
        int $components,
        int $bitsPerComponent,
        ?string $decodedBytes
    ): array {
        $expectedSampleCount = $expectedPixelCount * $components;
        $availableSampleCount = $samples['available_sample_count'];
        $expectedByteCount = intdiv(($expectedSampleCount * $bitsPerComponent) + 7, 8);
        $decodedByteCount = $decodedBytes === null ? null : strlen($decodedBytes);
        $surplusSampleCount = max(0, $availableSampleCount - $expectedSampleCount);
        $surplusByteCount = $decodedByteCount === null ? null : max(0, $decodedByteCount - $expectedByteCount);

        return [
            'expected_pixel_count' => $expectedPixelCount,
            'available_pixel_count' => $samples['available_pixel_count'],
            'expected_sample_count' => $expectedSampleCount,
            'available_sample_count' => $availableSampleCount,
            'surplus_sample_count' => $surplusSampleCount,
            'bits_per_component' => $bitsPerComponent,
            'expected_byte_count' => $expectedByteCount,
            'decoded_byte_count' => $decodedByteCount,
            'surplus_byte_count' => $surplusByteCount,
            'complete' => $samples['complete'],
            'truncated_to_declared_samples' => ($surplusByteCount ?? 0) > 0,
        ];
    }

    private function readPackedBits(string $bytes, int $bitOffset, int $bitCount): int
    {
        $value = 0;
        for ($bit = 0; $bit < $bitCount; $bit++) {
            $absoluteBit = $bitOffset + $bit;
            $byteIndex = intdiv($absoluteBit, 8);
            $shift = 7 - ($absoluteBit % 8);
            $value = ($value << 1) | ((ord($bytes[$byteIndex]) >> $shift) & 1);
        }

        return $value;
    }

    private function streamDictionaryFromValue(string $resolved): ?string
    {
        $offset = $this->skipPdfWhitespace($resolved, 0);
        $read = $this->readBalancedDictionary($resolved, $offset);

        return $read['value'] ?? null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function decodeImageStreamByFilters(
        string $dictionary,
        string $stream,
        array $objects,
        bool $requireExplicitFilterEndMarkers = false,
        bool $recordPreviewOnlyPrefix = false
    ): array
    {
        $filters = $this->imageFilterValues($dictionary, $objects);
        $hasConcreteFilter = false;
        foreach ($filters as $filter) {
            if (is_string($filter)) {
                $hasConcreteFilter = true;
                break;
            }
        }
        if (!$hasConcreteFilter) {
            return [
                'decoded' => $stream,
                'unsupported_filters' => [],
                'decode_failed' => false,
            ];
        }

        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        $duplicateDecodeParmsDeclarations = $this->duplicatePdfNameDeclarationCount($dictionary, 'DecodeParms') > 0;
        $unappliedDecodeParmsSlots = $this->unappliedNonNullDecodeParmsSlots($filters, $decodeParms);
        $unsupportedFilters = [];
        $decodedNativeFilterCount = 0;

        foreach ($filters as $index => $filter) {
            if (!is_string($filter)) {
                continue;
            }

            $decodeParmsValue = $this->decodeParmsValueForImageFilterIndex($filters, $decodeParms, $index);
            $resolvedDecodeParms = $this->resolvedDecodeParmsDictionary($decodeParmsValue, $objects);
            $isPreviewOnlyFilter = $this->isPreviewOnlyStreamFilter($filter);
            if (
                !$isPreviewOnlyFilter
                && $this->isNativeImageStreamFilter($filter)
                && ($duplicateDecodeParmsDeclarations || $unappliedDecodeParmsSlots !== [])
            ) {
                $unsupportedFilters[] = $filter;

                return [
                    'decoded' => null,
                    'unsupported_filters' => $unsupportedFilters,
                    'decode_failed' => true,
                ];
            }

            if ($isPreviewOnlyFilter || !$this->canApplyImageDecodeParms($filter, $resolvedDecodeParms, $objects)) {
                $unsupportedFilters[] = $filter;
                $previewBoundaryFailed = $isPreviewOnlyFilter
                    && $requireExplicitFilterEndMarkers
                    && !$this->previewOnlyImageFilterInputHasCleanBoundary(
                        $filter,
                        $stream,
                        $resolvedDecodeParms,
                        $objects,
                        $this->integerNameValue($dictionary, 'Height', $objects)
                    );
                $result = [
                    'decoded' => null,
                    'unsupported_filters' => $unsupportedFilters,
                    'decode_failed' => !$isPreviewOnlyFilter || $previewBoundaryFailed,
                ];
                if ($recordPreviewOnlyPrefix && $decodedNativeFilterCount > 0) {
                    $result['native_prefix_decoded_bytes'] = $stream;
                    $result['stopped_before_filter'] = $filter;
                }

                return $result;
            }

            if (
                (
                    $requireExplicitFilterEndMarkers
                    || ($recordPreviewOnlyPrefix && $this->nativeFilterPrefixStopsBeforeCcitt($filters, $index))
                )
                && !$this->streamFilterInputHasExplicitEndMarker($filter, $stream, $resolvedDecodeParms, $objects)
            ) {
                $unsupportedFilters[] = $filter;

                return [
                    'decoded' => null,
                    'unsupported_filters' => $unsupportedFilters,
                    'decode_failed' => true,
                ];
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => (
                    $recordPreviewOnlyPrefix
                    && $this->nativeFilterPrefixStopsBeforeDct($filters, $index)
                )
                    ? $this->decodeDctAscii85NativePrefixStream($stream)
                    : $this->decodeAscii85Stream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $resolvedDecodeParms, $objects),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $resolvedDecodeParms, $objects),
                'Crypt' => $this->decodeCryptIdentityStream($stream, $resolvedDecodeParms, $objects),
                default => null,
            };

            if ($decoded === null) {
                $unsupportedFilters[] = $filter;
                $result = [
                    'decoded' => null,
                    'unsupported_filters' => $unsupportedFilters,
                    'decode_failed' => true,
                ];
                if ($recordPreviewOnlyPrefix && $decodedNativeFilterCount > 0) {
                    $result['native_prefix_decoded_bytes'] = $stream;
                    $result['stopped_before_filter'] = $filter;
                }

                return $result;
            }

            $stream = $decoded;
            $decodedNativeFilterCount++;
        }

        return [
            'decoded' => $stream,
            'unsupported_filters' => [],
            'decode_failed' => false,
        ];
    }

    /**
     * @param array<int, string|null> $filters
     */
    private function nativeFilterPrefixStopsBeforeCcitt(array $filters, int $index): bool
    {
        $count = count($filters);
        for ($next = $index + 1; $next < $count; $next++) {
            $filter = $filters[$next];
            if (!is_string($filter)) {
                continue;
            }

            if ($filter === 'CCITTFaxDecode' || $filter === 'CCF') {
                return true;
            }

            if ($this->isPreviewOnlyStreamFilter($filter)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param array<int, string|null> $filters
     */
    private function nativeFilterPrefixStopsBeforeDct(array $filters, int $index): bool
    {
        $count = count($filters);
        for ($next = $index + 1; $next < $count; $next++) {
            $filter = $filters[$next];
            if (!is_string($filter)) {
                continue;
            }

            if ($filter === 'DCTDecode' || $filter === 'DCT') {
                return true;
            }

            if ($this->isPreviewOnlyStreamFilter($filter)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamFilterInputHasExplicitEndMarker(
        string $filter,
        string $stream,
        ?string $decodeParms = null,
        array $objects = []
    ): bool
    {
        return match ($filter) {
            'ASCIIHexDecode', 'AHx' => (($offset = strpos($stream, '>')) !== false)
                && $this->streamHasOnlyWhitespaceAfterOffset($stream, $offset + 1),
            'ASCII85Decode', 'A85' => (($offset = strpos($stream, '~>')) !== false)
                && $this->streamHasOnlyWhitespaceAfterOffset($stream, $offset + 2),
            'RunLengthDecode', 'RL' => (($offset = $this->runLengthExplicitEndOffset($stream)) !== null)
                && $this->streamHasOnlyWhitespaceAfterOffset($stream, $offset + 1),
            'FlateDecode', 'Fl' => (($offset = $this->flateExplicitEndByteOffset($stream)) !== null)
                && $this->streamHasOnlyWhitespaceAfterOffset($stream, $offset),
            'LZWDecode', 'LZW' => (($offset = $this->lzwExplicitEndByteOffset($stream, $decodeParms, $objects)) !== null)
                && $this->streamHasOnlyWhitespaceAfterOffset($stream, $offset),
            default => true,
        };
    }

    /**
     * @param array<int, string> $objects
     */
    private function previewOnlyImageFilterInputHasCleanBoundary(
        string $filter,
        string $stream,
        ?string $decodeParms = null,
        array $objects = [],
        ?int $imageHeight = null
    ): bool
    {
        return match ($filter) {
            'JPXDecode' => $this->jpxPreviewInputHasCleanEocBoundary($stream),
            'CCITTFaxDecode', 'CCF' => $this->ccittFaxPreviewInputHasCleanBoundary(
                $stream,
                $decodeParms,
                $objects,
                $imageHeight
            ),
            default => true,
        };
    }

    /**
     * @param array<int, string> $objects
     */
    private function ccittFaxPreviewInputHasCleanBoundary(
        string $stream,
        ?string $decodeParms,
        array $objects,
        ?int $imageHeight
    ): bool {
        $bytes = rtrim($stream, "\x00\t\n\f\r ");
        if ($bytes === '') {
            return false;
        }

        $rowCount = $this->ccittFaxPreviewDecodeParmsUsesRowEndOwnership($decodeParms, $objects)
            ? $this->ccittFaxPreviewEndOfLineRowCountForOwnership($decodeParms, $objects, $imageHeight)
            : null;
        if ($rowCount !== null) {
            $rowEndMarker = "\x00\x10\x01";

            return str_ends_with($bytes, $rowEndMarker)
                && substr_count($bytes, $rowEndMarker) >= $rowCount;
        }

        $markers = $this->ccittFaxPreviewEndOfBlockMarkersForOwnership($decodeParms, $objects, $imageHeight);
        foreach ($markers as $marker) {
            if (str_ends_with($bytes, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     */
    private function ccittFaxPreviewDecodeParmsUsesEndOfBlock(?string $decodeParms, array $objects): bool
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return true;
        }

        if ($this->decodeParmsHasName($decodeParms, 'EndOfBlock')) {
            $endOfBlock = $this->decodeParmsBool($decodeParms, 'EndOfBlock', $objects);
            if ($endOfBlock !== true) {
                return false;
            }
        }

        if ($this->decodeParmsHasName($decodeParms, 'K') && $this->decodeParmsInt($decodeParms, 'K', $objects) === null) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     */
    private function ccittFaxPreviewDecodeParmsUsesRowEndOwnership(?string $decodeParms, array $objects): bool
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return false;
        }

        if (!$this->decodeParmsHasName($decodeParms, 'EndOfBlock')) {
            return false;
        }

        return $this->decodeParmsBool($decodeParms, 'EndOfBlock', $objects) === false;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function ccittFaxPreviewEndOfBlockMarkers(?string $decodeParms, array $objects): array
    {
        $k = $this->decodeParmsInt($decodeParms, 'K', $objects) ?? 0;
        $eolPair = "\x00\x10\x01";

        if ($k < 0) {
            return [$eolPair];
        }

        return [$eolPair . $eolPair . $eolPair];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function ccittFaxPreviewEndOfBlockMarkersForOwnership(
        ?string $decodeParms,
        array $objects,
        ?int $imageHeight = null
    ): array {
        if ($decodeParms !== null && $this->decodeParmsHasName($decodeParms, 'EndOfBlock')) {
            $endOfBlock = $this->decodeParmsBool($decodeParms, 'EndOfBlock', $objects);
            if ($endOfBlock === false) {
                return $this->ccittFaxPreviewEndOfLineMarkersForOwnership($decodeParms, $objects, $imageHeight);
            }
        }

        if ($this->ccittFaxPreviewDecodeParmsUsesEndOfBlock($decodeParms, $objects)) {
            return $this->ccittFaxPreviewEndOfBlockMarkers($decodeParms, $objects);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function ccittFaxPreviewEndOfLineMarkersForOwnership(
        ?string $decodeParms,
        array $objects,
        ?int $imageHeight = null
    ): array {
        return $this->ccittFaxPreviewEndOfLineRowCountForOwnership($decodeParms, $objects, $imageHeight) === null
            ? []
            : ["\x00\x10\x01"];
    }

    /**
     * @param array<int, string> $objects
     */
    private function ccittFaxPreviewEndOfLineRowCountForOwnership(
        ?string $decodeParms,
        array $objects,
        ?int $imageHeight = null
    ): ?int {
        if ($decodeParms === null || !$this->decodeParmsHasName($decodeParms, 'EndOfLine')) {
            return null;
        }

        if ($this->decodeParmsBool($decodeParms, 'EndOfLine', $objects) !== true) {
            return null;
        }

        $k = $this->decodeParmsInt($decodeParms, 'K', $objects) ?? 0;
        if ($k < 0 || $this->ccittFaxPreviewExplicitColumnsAreInvalidForOwnership($decodeParms, $objects)) {
            return null;
        }

        if ($this->decodeParmsHasName($decodeParms, 'Rows')) {
            $rows = $this->decodeParmsInt($decodeParms, 'Rows', $objects);

            return $rows !== null && $rows > 0 ? $rows : null;
        }

        return $imageHeight !== null && $imageHeight > 0 ? $imageHeight : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function ccittFaxPreviewExplicitColumnsAreInvalidForOwnership(?string $decodeParms, array $objects): bool
    {
        if ($decodeParms === null || !$this->decodeParmsHasName($decodeParms, 'Columns')) {
            return false;
        }

        $columns = $this->decodeParmsInt($decodeParms, 'Columns', $objects);

        return $columns === null || $columns < 1;
    }

    private function jpxPreviewInputHasCleanEocBoundary(string $stream): bool
    {
        $start = 0;
        $length = strlen($stream);
        while ($start < $length && $this->isPdfWhitespace($stream[$start])) {
            $start++;
        }

        if (substr($stream, $start, 2) !== "\xff\x4f") {
            return true;
        }

        $eocOffset = strrpos($stream, "\xff\xd9");
        if ($eocOffset === false) {
            return true;
        }

        return $this->streamHasOnlyWhitespaceAfterOffset($stream, $eocOffset + 2);
    }

    private function streamHasOnlyWhitespaceAfterOffset(string $stream, int $offset): bool
    {
        $length = strlen($stream);
        for ($index = $offset; $index < $length;) {
            if ($this->isPdfWhitespace($stream[$index])) {
                $index++;
                continue;
            }

            if ($stream[$index] === '%') {
                $lineLength = strcspn($stream, "\r\n", $index);
                if ($index + $lineLength >= $length) {
                    return false;
                }
                $index += $lineLength;
                continue;
            }

            return false;
        }

        return true;
    }

    private function runLengthExplicitEndOffset(string $stream): ?int
    {
        $length = strlen($stream);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($stream[$offset]);
            if ($control === 128) {
                return $offset;
            }
            if ($control <= 127) {
                $literalLength = $control + 1;
                if ($offset + $literalLength >= $length) {
                    return null;
                }
                $offset += $literalLength;
                continue;
            }
            if ($offset + 1 >= $length) {
                return null;
            }
            $offset++;
        }

        return null;
    }

    private function flateExplicitEndByteOffset(string $stream): ?int
    {
        if (
            !function_exists('inflate_init')
            || !function_exists('inflate_add')
            || !function_exists('inflate_get_status')
            || !function_exists('inflate_get_read_len')
        ) {
            return null;
        }

        $encodings = [];
        foreach (['ZLIB_ENCODING_DEFLATE', 'ZLIB_ENCODING_RAW', 'ZLIB_ENCODING_GZIP'] as $constant) {
            if (defined($constant)) {
                $encodings[] = constant($constant);
            }
        }

        $finish = defined('ZLIB_FINISH') ? constant('ZLIB_FINISH') : 4;
        $streamEnd = defined('ZLIB_STREAM_END') ? constant('ZLIB_STREAM_END') : 1;
        foreach (array_unique($encodings) as $encoding) {
            $context = @inflate_init($encoding);
            if ($context === false) {
                continue;
            }

            $decoded = @inflate_add($context, $stream, $finish);
            if ($decoded === false || @inflate_get_status($context) !== $streamEnd) {
                continue;
            }

            $readLength = @inflate_get_read_len($context);
            if (is_int($readLength) && $readLength > 0) {
                return $readLength;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function lzwExplicitEndByteOffset(string $stream, ?string $decodeParms = null, array $objects = []): ?int
    {
        $earlyChange = ($this->decodeParmsInt($decodeParms, 'EarlyChange', $objects) ?? 1) === 0 ? 0 : 1;
        $bitOffset = 0;
        $dictionary = [];
        $nextCode = 258;
        $codeSize = 9;

        $resetDictionary = static function () use (&$dictionary, &$nextCode, &$codeSize): void {
            $dictionary = [];
            for ($code = 0; $code < 256; $code++) {
                $dictionary[$code] = chr($code);
            }
            $nextCode = 258;
            $codeSize = 9;
        };
        $resetDictionary();

        $previous = null;
        while (($code = $this->readLzwCode($stream, $bitOffset, $codeSize)) !== null) {
            if ($code === 256) {
                $resetDictionary();
                $previous = null;
                continue;
            }

            if ($code === 257) {
                return intdiv($bitOffset + 7, 8);
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $previous !== null) {
                $entry = $previous . $previous[0];
            } else {
                return null;
            }

            if ($previous !== null && $nextCode < 4096) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;
                if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
                    $codeSize++;
                }
            }
            $previous = $entry;
        }

        return null;
    }

    private function isPreviewOnlyStreamFilter(string $filter): bool
    {
        return $this->isPreviewOnlyImageFilter($filter) || in_array($filter, ['DCTDecode', 'DCT'], true);
    }

    private function isNativeImageStreamFilter(string $filter): bool
    {
        return in_array($filter, [
            'ASCIIHexDecode',
            'AHx',
            'ASCII85Decode',
            'A85',
            'RunLengthDecode',
            'RL',
            'FlateDecode',
            'Fl',
            'LZWDecode',
            'LZW',
        ], true);
    }

    /**
     * @param array<int, string> $objects
     */
    private function resolvedDecodeParmsDictionary(?string $value, array $objects): ?string
    {
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if ($resolved === '' || $resolved === 'null' || !str_starts_with($resolved, '<<')) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param array<int, string> $objects
     */
    private function imageDecodeParmsValueIsMalformed(?string $value, array $objects): bool
    {
        if ($value === null) {
            return false;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if ($resolved === '' || $resolved === 'null') {
            return false;
        }

        if (!str_starts_with($resolved, '<<')) {
            return true;
        }

        $dictionary = $this->readBalancedDictionary($resolved, 0);

        return $dictionary === null || $this->skipPdfWhitespace($resolved, $dictionary['next']) !== strlen($resolved);
    }

    /**
     * @param array<int, string> $objects
     */
    private function canApplyImageDecodeParms(string $filter, ?string $decodeParms, array $objects): bool
    {
        if ($decodeParms === null) {
            return true;
        }

        foreach (['Predictor', 'Columns', 'Colors', 'BitsPerComponent', 'EarlyChange'] as $name) {
            if (
                $this->extractPdfNameValue($decodeParms, $name) !== null
                && $this->decodeParmsInt($decodeParms, $name, $objects) === null
            ) {
                return false;
            }
        }

        $predictor = $this->decodeParmsInt($decodeParms, 'Predictor', $objects);
        if (
            $predictor !== null
            && $predictor !== 1
            && $predictor !== 2
            && ($predictor < 10 || $predictor > 15)
        ) {
            return false;
        }
        if (
            $predictor !== null
            && $predictor !== 1
            && !in_array($filter, ['FlateDecode', 'Fl', 'LZWDecode', 'LZW'], true)
        ) {
            return false;
        }

        foreach (['Columns', 'Colors', 'BitsPerComponent'] as $name) {
            $value = $this->decodeParmsInt($decodeParms, $name, $objects);
            if ($value !== null && $value < 1) {
                return false;
            }
        }

        $earlyChange = $this->decodeParmsInt($decodeParms, 'EarlyChange', $objects);
        if (
            $earlyChange !== null
            && (
                !in_array($earlyChange, [0, 1], true)
                || ($earlyChange !== 1 && !in_array($filter, ['LZWDecode', 'LZW'], true))
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeCryptIdentityStream(string $stream, ?string $decodeParms, array $objects = []): ?string
    {
        return $this->cryptIdentityFilterIsSupported($decodeParms, $objects) ? $stream : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function cryptIdentityFilterIsSupported(?string $decodeParms, array $objects = []): bool
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return false;
        }
        if ($this->duplicatePdfNameDeclarationCount($decodeParms, 'Name') > 0) {
            return false;
        }

        $value = $this->pdfDictionaryValueForName($decodeParms, 'Name');
        if ($value === null) {
            return false;
        }

        return $this->pdfNameValue(trim($this->resolvePdfValue($value, $objects))) === 'Identity';
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeParmsInt(?string $decodeParms, string $name, array $objects): ?int
    {
        if ($decodeParms === null) {
            return null;
        }

        $value = $this->pdfDictionaryValueForName($decodeParms, $name);
        if ($value === null) {
            return null;
        }

        return $this->integerFromPdfValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeParmsBool(?string $decodeParms, string $name, array $objects): ?bool
    {
        if ($decodeParms === null) {
            return null;
        }

        $value = $this->pdfDictionaryValueForName($decodeParms, $name);
        if ($value === null) {
            return null;
        }

        return $this->booleanFromPdfValue($value, $objects);
    }

    private function decodeParmsHasName(?string $decodeParms, string $name): bool
    {
        return $decodeParms !== null && $this->pdfDictionaryValueForName($decodeParms, $name) !== null;
    }

    /**
     * @param array<int|string, mixed> $objects
     * @param array<string, true> $seenObjects
     */
    private function booleanFromPdfValue(string $value, array $objects, array $seenObjects = []): ?bool
    {
        $trimmed = trim($value);
        if ($trimmed === 'true') {
            return true;
        }
        if ($trimmed === 'false') {
            return false;
        }

        $reference = $this->pdfIndirectReferenceTokenAt($trimmed, 0);
        if ($reference === null || $this->skipPdfWhitespace($trimmed, $reference['endOffset']) !== strlen($trimmed)) {
            return null;
        }

        $objectNumber = $reference['objectNumber'];
        $generation = $reference['generation'];
        $seenKey = $objectNumber . ':' . $generation;
        $objectBody = $this->pdfObjectMapValueForReference($objects, $objectNumber, $generation);
        if (isset($seenObjects[$seenKey]) || $objectBody === null) {
            return null;
        }

        $seenObjects[$seenKey] = true;

        return $this->booleanFromPdfValue(trim($objectBody), $objects, $seenObjects);
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $body = strstr($stream, '>', true);
        if ($body === false) {
            $body = $stream;
        }

        $hex = preg_replace('/[\x00\s]+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);

        return $decoded === false ? null : $decoded;
    }

    private function decodeAscii85Stream(string $stream): ?string
    {
        $body = trim($stream);
        if (str_starts_with($body, '<~')) {
            $body = substr($body, 2);
        }

        $terminator = strpos($body, '~>');
        if ($terminator !== false) {
            $body = substr($body, 0, $terminator);
        }

        $out = '';
        $group = [];
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $char = $body[$index];
            if ($this->isPdfWhitespace($char)) {
                continue;
            }

            if ($char === 'z') {
                if ($group !== []) {
                    return null;
                }
                $out .= "\0\0\0\0";
                continue;
            }

            $ord = ord($char);
            if ($ord < 33 || $ord > 117) {
                return null;
            }

            $group[] = $ord - 33;
            if (count($group) === 5) {
                $out .= $this->decodeAscii85Group($group, 4);
                $group = [];
            }
        }

        if ($group !== []) {
            $groupLength = count($group);
            if ($groupLength === 1) {
                return null;
            }
            while (count($group) < 5) {
                $group[] = 84;
            }
            $out .= $this->decodeAscii85Group($group, $groupLength - 1);
        }

        return $out;
    }

    /**
     * @param list<int> $group
     */
    private function decodeAscii85Group(array $group, int $bytesToReturn): string
    {
        $value = 0;
        foreach ($group as $digit) {
            $value = ($value * 85) + $digit;
        }

        $bytes = '';
        for ($shift = 24; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($value >> $shift) & 0xff);
        }

        return substr($bytes, 0, $bytesToReturn);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeFlateStream(string $stream, ?string $decodeParms = null, array $objects = []): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }
        if ($inflated === false) {
            return null;
        }

        return $this->applyDecodeParmsPredictor($inflated, $decodeParms, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeLzwStream(string $stream, ?string $decodeParms = null, array $objects = []): ?string
    {
        $earlyChange = ($this->decodeParmsInt($decodeParms, 'EarlyChange', $objects) ?? 1) === 0 ? 0 : 1;
        $bitOffset = 0;
        $dictionary = [];
        $nextCode = 258;
        $codeSize = 9;

        $resetDictionary = static function () use (&$dictionary, &$nextCode, &$codeSize): void {
            $dictionary = [];
            for ($code = 0; $code < 256; $code++) {
                $dictionary[$code] = chr($code);
            }
            $nextCode = 258;
            $codeSize = 9;
        };
        $resetDictionary();

        $out = '';
        $previous = null;
        while (($code = $this->readLzwCode($stream, $bitOffset, $codeSize)) !== null) {
            if ($code === 256) {
                $resetDictionary();
                $previous = null;
                continue;
            }

            if ($code === 257) {
                return $this->applyDecodeParmsPredictor($out, $decodeParms, $objects);
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $previous !== null) {
                $entry = $previous . $previous[0];
            } else {
                return null;
            }

            $out .= $entry;
            if ($previous !== null && $nextCode < 4096) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;
                if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
                    $codeSize++;
                }
            }
            $previous = $entry;
        }

        return null;
    }

    private function readLzwCode(string $bytes, int &$bitOffset, int $codeSize): ?int
    {
        $totalBits = strlen($bytes) * 8;
        if ($bitOffset + $codeSize > $totalBits) {
            return null;
        }

        $code = 0;
        for ($index = 0; $index < $codeSize; $index++) {
            $absoluteBit = $bitOffset + $index;
            $byte = ord($bytes[intdiv($absoluteBit, 8)]);
            $shift = 7 - ($absoluteBit % 8);
            $code = ($code << 1) | (($byte >> $shift) & 1);
        }
        $bitOffset += $codeSize;

        return $code;
    }

    /**
     * @param array<int, string> $objects
     */
    private function applyDecodeParmsPredictor(string $bytes, ?string $decodeParms, array $objects = []): ?string
    {
        $predictor = $this->decodeParmsInt($decodeParms, 'Predictor', $objects) ?? 1;
        if ($predictor === 1) {
            return $bytes;
        }

        $colors = max(1, $this->decodeParmsInt($decodeParms, 'Colors', $objects) ?? 1);
        $bitsPerComponent = max(1, $this->decodeParmsInt($decodeParms, 'BitsPerComponent', $objects) ?? 8);
        $columns = max(1, $this->decodeParmsInt($decodeParms, 'Columns', $objects) ?? 1);
        $rowLength = intdiv(($colors * $columns * $bitsPerComponent) + 7, 8);
        $bytesPerPixel = max(1, intdiv(($colors * $bitsPerComponent) + 7, 8));

        if ($predictor === 2) {
            return $this->applyTiffPredictor($bytes, $rowLength, $bytesPerPixel);
        }

        if ($predictor < 10 || $predictor > 15) {
            return null;
        }

        return $this->applyPngPredictor($bytes, $rowLength, $bytesPerPixel);
    }

    private function applyTiffPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        if ($rowLength < 1 || strlen($bytes) % $rowLength !== 0) {
            return null;
        }

        $out = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $rowLength) {
            $row = substr($bytes, $offset, $rowLength);
            for ($index = $bytesPerPixel; $index < $rowLength; $index++) {
                $row[$index] = chr((ord($row[$index]) + ord($row[$index - $bytesPerPixel])) & 0xff);
            }
            $out .= $row;
        }

        return $out;
    }

    private function applyPngPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        $stride = $rowLength + 1;
        if ($rowLength < 1 || strlen($bytes) % $stride !== 0) {
            return null;
        }

        $out = '';
        $previous = str_repeat("\0", $rowLength);
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $stride) {
            $filter = ord($bytes[$offset]);
            $row = substr($bytes, $offset + 1, $rowLength);
            if ($filter > 4) {
                return null;
            }

            for ($index = 0; $index < $rowLength; $index++) {
                $left = $index >= $bytesPerPixel ? ord($row[$index - $bytesPerPixel]) : 0;
                $up = ord($previous[$index]);
                $upperLeft = $index >= $bytesPerPixel ? ord($previous[$index - $bytesPerPixel]) : 0;
                $encoded = ord($row[$index]);
                $row[$index] = chr(($encoded + $this->pngPredictorValue($filter, $left, $up, $upperLeft)) & 0xff);
            }

            $out .= $row;
            $previous = $row;
        }

        return $out;
    }

    private function pngPredictorValue(int $filter, int $left, int $up, int $upperLeft): int
    {
        return match ($filter) {
            0 => 0,
            1 => $left,
            2 => $up,
            3 => intdiv($left + $up, 2),
            4 => $this->paethPredictor($left, $up, $upperLeft),
        };
    }

    private function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upperLeftDistance = abs($estimate - $upperLeft);

        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }
        if ($upDistance <= $upperLeftDistance) {
            return $up;
        }

        return $upperLeft;
    }

    private function decodeRunLengthStream(string $stream): ?string
    {
        $out = '';
        $length = strlen($stream);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($stream[$offset]);
            if ($control === 128) {
                return $out;
            }

            if ($control <= 127) {
                $copyLength = $control + 1;
                if ($offset + $copyLength >= $length) {
                    return null;
                }
                $out .= substr($stream, $offset + 1, $copyLength);
                $offset += $copyLength;
                continue;
            }

            if ($offset + 1 >= $length) {
                return null;
            }
            $out .= str_repeat($stream[$offset + 1], 257 - $control);
            $offset++;
        }

        return null;
    }

    private function dictionaryNameValue(string $dictionary, string $name): ?string
    {
        $value = $this->extractPdfNameValue($dictionary, $name);

        return $value === null ? null : $this->pdfNameValue($value);
    }

    private function integerNameValue(string $dictionary, string $name, array $objects = []): ?int
    {
        $value = $this->extractPdfNameValue($dictionary, $name);
        if ($value === null) {
            return null;
        }

        return $this->integerFromPdfValue($value, $objects);
    }

    private function floatNameValue(string $dictionary, string $name): ?float
    {
        $value = $this->extractPdfNameValue($dictionary, $name);
        if ($value === null || preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', trim($value), $match) !== 1) {
            return null;
        }

        return (float) $match[0];
    }

    private function booleanNameValue(string $dictionary, string $name): ?bool
    {
        $value = $this->extractPdfNameValue($dictionary, $name);
        if ($value === null) {
            return null;
        }

        return match (trim($value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return list<float>
     */
    private function numericArrayValue(?string $value, array $objects = [], array $seenObjects = []): array
    {
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);
        if ($objects !== []) {
            $trimmed = trim($this->resolvePdfValue($trimmed, $objects, $seenObjects));
        }

        if (!str_starts_with($trimmed, '[')) {
            if (preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $trimmed, $matches) === 0) {
                return [];
            }

            return array_map(static fn (string $number): float => (float) $number, $matches[0]);
        }

        $numbers = [];
        foreach ($this->pdfArrayValues($trimmed) as $entry) {
            $entry = trim($this->resolvePdfValue(trim($entry), $objects, $seenObjects));
            if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $entry) !== 1) {
                return [];
            }

            $numbers[] = (float) $entry;
        }

        return $numbers;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return list<float>
     */
    private function numericArrayNameValue(string $dictionary, string $name, array $objects, array $seenObjects = []): array
    {
        $value = $this->extractPdfNameValue($dictionary, $name);
        if ($value === null) {
            return [];
        }

        return $this->numericArrayValue($value, $objects, $seenObjects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function integerFromPdfValue(string $value, array $objects, array $seenObjects = []): ?int
    {
        $resolved = trim($this->resolvePdfValue($value, $objects, $seenObjects));
        if (preg_match('/^[+-]?\d+/', $resolved, $match) !== 1) {
            return null;
        }
        if ($this->skipPdfWhitespace($resolved, strlen($match[0])) !== strlen($resolved)) {
            return null;
        }

        return $this->boundedPdfIntegerToken($match[0]);
    }

    private function boundedPdfIntegerToken(string $token): ?int
    {
        $token = trim($token);
        if (preg_match('/^[+-]?\d+$/', $token) !== 1) {
            return null;
        }

        $digits = $token;
        if ($digits[0] === '+' || $digits[0] === '-') {
            $digits = substr($digits, 1);
        }

        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $max = $token[0] === '-' ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        if (
            strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)
        ) {
            return null;
        }

        return (int) $token;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{bytes: string, source: string}|null
     */
    private function pdfBytesFromValue(string $value, array $objects, array $seenObjects = []): ?array
    {
        $resolved = trim($this->resolvePdfValue($value, $objects, $seenObjects));
        $stream = $this->streamPayloadBytes($resolved, $objects);
        if ($stream !== null) {
            return ['bytes' => $stream, 'source' => 'stream'];
        }

        if (preg_match('/^<([0-9A-Fa-f\s]*)>$/s', $resolved, $match) === 1) {
            $hex = preg_replace('/\s+/', '', $match[1]) ?? '';
            if ($hex === '') {
                return ['bytes' => '', 'source' => 'hex_string'];
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);

            return ['bytes' => $bytes === false ? '' : $bytes, 'source' => 'hex_string'];
        }

        if (str_starts_with($resolved, '(')) {
            return ['bytes' => $this->literalStringBytes($resolved), 'source' => 'literal_string'];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamPayloadBytes(string $resolved, array $objects = []): ?string
    {
        $dctPayload = $this->dctPreviewStreamPayloadBytes($resolved, $objects);
        if ($dctPayload !== null) {
            return $dctPayload;
        }

        if (preg_match('/stream(?:\r\n|\r|\n)(.*?)(?:\r\n|\r|\n)?endstream/s', $resolved, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/stream(.*?)endstream/s', $resolved, $match) === 1) {
            return ltrim($match[1], "\r\n");
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctPreviewStreamPayloadBytes(string $resolved, array $objects): ?string
    {
        $dictionaryOffset = $this->skipPdfWhitespace($resolved, 0);
        $dictionary = $this->readBalancedDictionary($resolved, $dictionaryOffset);
        if ($dictionary === null) {
            return null;
        }

        $filters = $this->imageFilterValues($dictionary['value'], $objects);
        $hasDctFilter = false;
        foreach ($filters as $filter) {
            if ($filter === 'DCTDecode' || $filter === 'DCT') {
                $hasDctFilter = true;
                break;
            }
        }
        if (!$hasDctFilter && !$this->imageFilterStackCanUseRawDctBoundary($dictionary['value'], $filters)) {
            return null;
        }

        $streamKeywordOffset = $this->skipPdfWhitespace($resolved, $dictionary['next']);
        if (substr($resolved, $streamKeywordOffset, 6) !== 'stream') {
            return null;
        }

        $streamStart = $streamKeywordOffset + 6;
        if (substr($resolved, $streamStart, 2) === "\r\n") {
            $streamStart += 2;
        } elseif (($resolved[$streamStart] ?? '') === "\n" || ($resolved[$streamStart] ?? '') === "\r") {
            $streamStart++;
        }

        $terminator = $hasDctFilter
            ? $this->dctPreviewStreamTerminatorOffset(
                $resolved,
                $streamStart,
                $dictionary['value'],
                $objects,
                $filters
            )
            : $this->rawDctPreviewStreamTerminatorOffset($resolved, $streamStart);

        if ($terminator !== null) {
            return $this->stripStreamTerminatingLineEnding(substr($resolved, $streamStart, $terminator - $streamStart));
        }

        return $hasDctFilter
            ? $this->dctPreviewStreamPayloadBytesWithPostEoiSurplus($resolved, $streamStart, $filters)
            : null;
    }

    /**
     * @param list<string|null> $filters
     */
    private function imageFilterStackCanUseRawDctBoundary(string $dictionary, array $filters): bool
    {
        $filterNames = array_values(array_filter($filters, static fn (?string $filter): bool => is_string($filter)));
        if ($this->pdfNameValue($this->extractPdfNameValue($dictionary, 'Subtype') ?? '') !== 'Image') {
            return false;
        }

        if ($filterNames === [] && $this->pdfDictionaryValuesForName($dictionary, 'Filter') === []) {
            return true;
        }

        return $this->imageFilterOperandBoundaryFilters($filterNames) !== [];
    }

    /**
     * @param array<int, string> $objects
     * @param list<string|null> $filters
     */
    private function dctPreviewStreamTerminatorOffset(
        string $value,
        int $streamStart,
        string $dictionary,
        array $objects,
        array $filters
    ): ?int {
        $firstFilter = null;
        $firstFilterIndex = null;
        foreach ($filters as $index => $filter) {
            if ($filter !== null) {
                $firstFilter = $filter;
                $firstFilterIndex = $index;
                break;
            }
        }

        if ($firstFilter === null || $firstFilterIndex === null) {
            return null;
        }

        if (
            $firstFilter === self::MALFORMED_IMAGE_FILTER_OPERAND
            && (($filters[$firstFilterIndex + 1] ?? null) === 'DCTDecode' || ($filters[$firstFilterIndex + 1] ?? null) === 'DCT')
        ) {
            return $this->rawDctPreviewStreamTerminatorOffset($value, $streamStart);
        }

        if ($firstFilter === 'DCTDecode' || $firstFilter === 'DCT') {
            return $this->rawDctPreviewStreamTerminatorOffset($value, $streamStart);
        }

        $dctFilterIndex = null;
        for ($index = $firstFilterIndex + 1, $count = count($filters); $index < $count; $index++) {
            $filter = $filters[$index];
            if ($filter === 'DCTDecode' || $filter === 'DCT') {
                $dctFilterIndex = $index;
                break;
            }
        }
        if ($dctFilterIndex === null) {
            return null;
        }

        $candidateTerminators = [];
        $payloadMarker = match ($firstFilter) {
            'ASCIIHexDecode', 'AHx' => '>',
            'ASCII85Decode', 'A85' => '~>',
            'RunLengthDecode', 'RL' => chr(128),
            default => null,
        };
        if ($payloadMarker !== null) {
            $offset = $streamStart;
            while (($markerOffset = strpos($value, $payloadMarker, $offset)) !== false) {
                $terminator = $this->skipPdfWhitespace($value, $markerOffset + strlen($payloadMarker));
                if ($this->streamEndTerminatorAt($value, $terminator, $streamStart)) {
                    $candidateTerminators[] = $terminator;
                }
                $offset = $markerOffset + 1;
            }
        }

        $offset = $streamStart;
        while (($terminator = strpos($value, 'endstream', $offset)) !== false) {
            if ($this->streamEndTerminatorAt($value, $terminator, $streamStart)) {
                $candidateTerminators[] = $terminator;
            }
            $offset = $terminator + strlen('endstream');
        }

        $candidateTerminators = array_values(array_unique($candidateTerminators));
        sort($candidateTerminators, SORT_NUMERIC);
        $lastCompleteTerminator = null;
        $fallbackTerminator = null;
        $sawIncompleteDctPreviewCandidate = false;
        foreach ($candidateTerminators as $terminator) {
            $payload = $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $terminator - $streamStart));
            $jpegBytes = $this->decodeImageStreamBeforeFilter(
                $dictionary,
                $payload,
                $objects,
                $filters,
                $dctFilterIndex
            );
            if ($jpegBytes === null) {
                $jpegBytes = $this->decodeImageStreamBeforeUnsupportedFilterBeforeDct(
                    $dictionary,
                    $payload,
                    $objects,
                    $filters,
                    $firstFilterIndex,
                    $dctFilterIndex
                );
            }
            if ($jpegBytes !== null && $this->dctPreviewBytesAreCompleteJpeg($jpegBytes)) {
                $lastCompleteTerminator = $terminator;
                continue;
            }
            if ($jpegBytes !== null) {
                $sawIncompleteDctPreviewCandidate = true;
            }
            if ($this->dctPrefixFirstFilterHasBoundedEndBeforeTerminator($dictionary, $payload, $objects, $filters, $firstFilterIndex)) {
                $fallbackTerminator = $terminator;
            }
        }

        return $lastCompleteTerminator
            ?? $fallbackTerminator
            ?? ($sawIncompleteDctPreviewCandidate
                ? $this->dctPrefixIncompletePreviewStreamTerminatorOffset($value, $streamStart)
                : null);
    }

    private function dctPrefixIncompletePreviewStreamTerminatorOffset(string $value, int $streamStart): ?int
    {
        $closingTerminator = null;
        $offset = $streamStart;
        while (($candidate = strpos($value, 'endstream', $offset)) !== false) {
            $offset = $candidate + strlen('endstream');
            if ($this->streamEndTerminatorAt($value, $candidate, $streamStart)) {
                $closingTerminator = $candidate;
            }
        }

        return $closingTerminator;
    }

    /**
     * @param array<int, string> $objects
     * @param list<string|null> $filters
     */
    private function dctPrefixFirstFilterHasBoundedEndBeforeTerminator(
        string $dictionary,
        string $payload,
        array $objects,
        array $filters,
        int $firstFilterIndex
    ): bool {
        $firstFilter = $filters[$firstFilterIndex] ?? null;
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        $decodeParmsValue = $this->decodeParmsValueForImageFilterIndex($filters, $decodeParms, $firstFilterIndex);
        $resolvedDecodeParms = $this->resolvedDecodeParmsDictionary($decodeParmsValue, $objects);
        if ($firstFilter === 'ASCII85Decode' || $firstFilter === 'A85') {
            if (
                $this->imageDecodeParmsValueIsMalformed($decodeParmsValue, $objects)
                || !$this->canApplyImageDecodeParms($firstFilter, $resolvedDecodeParms, $objects)
            ) {
                return false;
            }

            return $this->dctPrefixAscii85MemberCompletesAtTerminator($payload);
        }

        if ($firstFilter !== 'LZWDecode' && $firstFilter !== 'LZW') {
            return false;
        }

        if (!$this->canApplyImageDecodeParms($firstFilter, $resolvedDecodeParms, $objects)) {
            return false;
        }

        return $this->dctPrefixLzwMemberCompletesAtTerminator($payload, $resolvedDecodeParms, $objects);
    }

    private function dctPrefixAscii85MemberCompletesAtTerminator(string $payload): bool
    {
        $candidateStarts = [0];
        $offset = 0;
        while (($candidateStart = strpos($payload, '<~', $offset)) !== false) {
            $candidateStarts[] = $candidateStart;
            $offset = $candidateStart + 2;
        }

        foreach (array_values(array_unique($candidateStarts)) as $candidateStart) {
            $tail = substr($payload, $candidateStart);
            $eodOffset = strpos($tail, '~>');
            if ($eodOffset === false) {
                continue;
            }

            $endOffset = $eodOffset + 2;
            if (!$this->streamHasOnlyWhitespaceAfterOffset($tail, $endOffset)) {
                continue;
            }

            $decoded = $this->decodeAscii85Stream(substr($tail, 0, $endOffset));
            if ($decoded !== null && $this->dctPreviewBytesAreCompleteJpeg($decoded)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctPrefixLzwMemberCompletesAtTerminator(string $payload, ?string $decodeParms, array $objects): bool
    {
        $candidateStarts = [0];
        $offset = 0;
        while (($candidateStart = strpos($payload, "\x80", $offset)) !== false) {
            $candidateStarts[] = $candidateStart;
            $offset = $candidateStart + 1;
        }

        foreach (array_values(array_unique($candidateStarts)) as $candidateStart) {
            $tail = substr($payload, $candidateStart);
            $endOffset = $this->lzwExplicitEndByteOffset($tail, $decodeParms, $objects);
            if ($endOffset === null || !$this->streamHasOnlyWhitespaceAfterOffset($tail, $endOffset)) {
                continue;
            }

            $decoded = $this->decodeLzwStream(substr($tail, 0, $endOffset), $decodeParms, $objects);
            if ($decoded !== null && $this->dctPreviewBytesAreCompleteJpeg($decoded)) {
                return true;
            }
        }

        return false;
    }

    private function rawDctPreviewStreamTerminatorOffset(string $value, int $streamStart): ?int
    {
        $jpegStart = $this->dctPreviewSoiOffset($value, $streamStart);
        if ($jpegStart === null) {
            return null;
        }

        $lastCompleteTerminator = null;
        foreach ($this->dctPreviewEoiEndOffsets($value, $jpegStart) as $eoiEnd) {
            $terminator = $this->skipDctPreviewTerminatorPadding($value, $eoiEnd);
            if ($this->streamEndTerminatorAt($value, $terminator, $streamStart)) {
                $lastCompleteTerminator = $terminator;
            }
        }

        return $lastCompleteTerminator;
    }

    /**
     * @param list<string|null> $filters
     */
    private function dctPreviewStreamPayloadBytesWithPostEoiSurplus(
        string $value,
        int $streamStart,
        array $filters
    ): ?string {
        $firstFilter = null;
        foreach ($filters as $filter) {
            if (is_string($filter)) {
                $firstFilter = $filter;
                break;
            }
        }
        if ($firstFilter !== 'DCTDecode' && $firstFilter !== 'DCT') {
            return null;
        }

        $payload = null;
        $offset = $streamStart;
        while (($terminator = strpos($value, 'endstream', $offset)) !== false) {
            $offset = $terminator + strlen('endstream');
            if (!$this->streamEndTerminatorAt($value, $terminator, $streamStart)) {
                continue;
            }

            $candidate = $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $terminator - $streamStart));
            $reviewBytes = $this->rawDctPreviewPayloadBytesForReview($filters, $candidate);
            if ($reviewBytes !== null && strlen($reviewBytes) < strlen($candidate)) {
                $payload = $reviewBytes;
            }
        }

        return $payload;
    }

    /**
     * @param array<int, string> $objects
     */
    private function directDctDeclaredStreamPayloadForBoundaryMetadata(
        string $dictionary,
        string $imageObject,
        string $reviewStream,
        array $objects
    ): ?string {
        $filters = $this->imageFilterValues($dictionary, $objects);
        $firstFilter = null;
        foreach ($filters as $filter) {
            if (is_string($filter)) {
                $firstFilter = $filter;
                break;
            }
        }
        if ($firstFilter !== 'DCTDecode' && $firstFilter !== 'DCT') {
            return null;
        }

        $dictionaryOffset = $this->skipPdfWhitespace($imageObject, 0);
        $parsedDictionary = $this->readBalancedDictionary($imageObject, $dictionaryOffset);
        if ($parsedDictionary === null) {
            return null;
        }

        $streamKeywordOffset = $this->skipPdfWhitespace($imageObject, $parsedDictionary['next']);
        if (substr($imageObject, $streamKeywordOffset, 6) !== 'stream') {
            return null;
        }

        $streamStart = $streamKeywordOffset + 6;
        if (substr($imageObject, $streamStart, 2) === "\r\n") {
            $streamStart += 2;
        } elseif (($imageObject[$streamStart] ?? '') === "\n" || ($imageObject[$streamStart] ?? '') === "\r") {
            $streamStart++;
        }

        $payload = null;
        $offset = $streamStart;
        while (($terminator = strpos($imageObject, 'endstream', $offset)) !== false) {
            $offset = $terminator + strlen('endstream');
            if (!$this->streamEndTerminatorAt($imageObject, $terminator, $streamStart)) {
                continue;
            }

            $candidate = $this->stripStreamTerminatingLineEnding(
                substr($imageObject, $streamStart, $terminator - $streamStart)
            );
            if (
                strlen($candidate) <= strlen($reviewStream)
                || !str_starts_with($candidate, $reviewStream)
                || $this->rawJpegPreviewPayloadBytesForReview($candidate) !== $reviewStream
            ) {
                continue;
            }

            $payload = $candidate;
        }

        return $payload;
    }

    /**
     * @param list<string|null> $filters
     */
    private function rawDctPreviewPayloadBytesForReview(array $filters, string $stream): ?string
    {
        $firstFilter = null;
        foreach ($filters as $filter) {
            if (is_string($filter)) {
                if ($firstFilter === null && $filter === self::MALFORMED_IMAGE_FILTER_OPERAND) {
                    continue;
                }

                $firstFilter = $filter;
                break;
            }
        }
        if ($firstFilter !== 'DCTDecode' && $firstFilter !== 'DCT') {
            return null;
        }

        return $this->rawJpegPreviewPayloadBytesForReview($stream);
    }

    private function rawJpegPreviewPayloadBytesForReview(string $stream): ?string
    {
        $eoiEnd = null;
        foreach ($this->dctPreviewEoiEndOffsets($stream) as $candidateEnd) {
            $eoiEnd = $candidateEnd;
        }
        if ($eoiEnd === null) {
            return null;
        }

        $payloadEnd = $this->skipDctPreviewPadding($stream, $eoiEnd);
        if ($payloadEnd >= strlen($stream)) {
            return $stream;
        }

        return substr($stream, 0, $eoiEnd);
    }

    /**
     * @param list<string> $resolvedFilters
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function dctPreviewStreamBoundaryReviewForFilters(
        string $dictionary,
        string $stream,
        array $objects,
        array $resolvedFilters,
        ?string $reviewStreamOverride = null
    ): ?array {
        $reviewStream = $reviewStreamOverride ?? $stream;
        $direct = $this->dctPreviewStreamBoundaryReview($resolvedFilters, $stream, $reviewStream);
        if ($direct !== null) {
            return $direct;
        }
        if ($this->imageStreamCanInferMissingDctFilterBoundary($dictionary, $resolvedFilters, $stream)) {
            return $this->dctPreviewRawJpegStreamBoundaryReview($stream, $stream, [
                'inferred_from_raw_image_stream' => true,
                'declared_filter_missing' => true,
            ]);
        }

        $dctFilterIndex = null;
        $nativePrefixFilters = [];
        foreach ($resolvedFilters as $index => $filter) {
            if ($filter === 'DCTDecode' || $filter === 'DCT') {
                $dctFilterIndex = $index;
                break;
            }

            $nativePrefixFilters[] = $filter;
        }

        if (!is_int($dctFilterIndex)) {
            return null;
        }
        if ($dctFilterIndex === 0 || $nativePrefixFilters === []) {
            return $this->dctPreviewUnverifiedStreamBoundaryReview($resolvedFilters, $stream, $reviewStream);
        }

        $decodedPrefix = $this->decodeImageStreamBeforeFilter(
            $dictionary,
            $stream,
            $objects,
            $resolvedFilters,
            $dctFilterIndex
        );
        if ($decodedPrefix === null) {
            return null;
        }

        $decodedPrefixReview = $this->rawJpegPreviewPayloadBytesForReview($decodedPrefix) ?? $decodedPrefix;
        $prefixBoundary = $this->dctPreviewStreamBoundaryReview(
            $resolvedFilters,
            $stream,
            $decodedPrefixReview,
            true,
            $nativePrefixFilters,
            $resolvedFilters[$dctFilterIndex] ?? 'DCTDecode'
        );

        return $prefixBoundary ?? $this->dctPreviewUnverifiedStreamBoundaryReview(
            $resolvedFilters,
            $stream,
            $decodedPrefix,
            true,
            $nativePrefixFilters,
            $resolvedFilters[$dctFilterIndex] ?? 'DCTDecode'
        );
    }

    /**
     * @param list<string> $resolvedFilters
     * @param list<string> $nativePrefixFilters
     * @return array<string, mixed>|null
     */
    private function dctPreviewStreamBoundaryReview(
        array $resolvedFilters,
        string $stream,
        string $reviewStream,
        bool $decodedFromNativePrefix = false,
        array $nativePrefixFilters = [],
        ?string $stoppedBeforeFilter = null
    ): ?array
    {
        if (!in_array('DCTDecode', $resolvedFilters, true) && !in_array('DCT', $resolvedFilters, true)) {
            return null;
        }

        return $this->dctPreviewRawJpegStreamBoundaryReview(
            $stream,
            $reviewStream,
            [],
            $decodedFromNativePrefix,
            $nativePrefixFilters,
            $stoppedBeforeFilter
        );
    }

    /**
     * @param array<string, mixed> $extraMetadata
     * @param list<string> $nativePrefixFilters
     * @return array<string, mixed>|null
     */
    private function dctPreviewRawJpegStreamBoundaryReview(
        string $stream,
        string $reviewStream,
        array $extraMetadata = [],
        bool $decodedFromNativePrefix = false,
        array $nativePrefixFilters = [],
        ?string $stoppedBeforeFilter = null
    ): ?array {
        $length = strlen($reviewStream);
        $start = $this->dctPreviewSoiOffset($reviewStream);
        if ($start === null) {
            return null;
        }

        $eoiEnd = null;
        foreach ($this->dctPreviewEoiEndOffsets($reviewStream, $start) as $candidateEnd) {
            if ($this->skipDctPreviewPadding($reviewStream, $candidateEnd) === $length) {
                $eoiEnd = $candidateEnd;
                break;
            }
        }
        if ($eoiEnd === null) {
            return null;
        }

        $jpegBytes = substr($reviewStream, $start, $eoiEnd - $start);
        $paddingEnd = $this->skipDctPreviewPadding($reviewStream, $eoiEnd);
        $postEoiSurplusMetadata = [];
        if (strlen($reviewStream) < strlen($stream) && str_starts_with($stream, $reviewStream)) {
            $postEoiSurplusBytes = substr($stream, strlen($reviewStream));
            if ($postEoiSurplusBytes !== '') {
                $postEoiSurplusMetadata = [
                    'post_jpeg_eoi_surplus_byte_count' => strlen($postEoiSurplusBytes),
                    'post_jpeg_eoi_surplus_sha256' => hash('sha256', $postEoiSurplusBytes),
                    'post_jpeg_eoi_surplus_preview_hex' => bin2hex(substr($postEoiSurplusBytes, 0, 32)),
                ];
            }
        }

        $jpegMarkerMetadata = $this->dctPreviewJpegMarkerReviewMetadata($jpegBytes);

        return [
            'source' => 'dctdecode_jpeg_marker_boundary',
            'jpeg_soi_offset' => $start,
            'jpeg_marker_fill_byte_count' => $this->dctPreviewMarkerFillByteCount($reviewStream, $start),
            'jpeg_eoi_end_offset' => $eoiEnd,
            'raw_stream_length' => strlen($stream),
            'review_stream_length' => strlen($reviewStream),
            'padding_byte_count' => max(0, $paddingEnd - $eoiEnd),
            'stream_trimmed_to_jpeg_eoi' => strlen($reviewStream) < strlen($stream),
            ...$postEoiSurplusMetadata,
            ...$extraMetadata,
            ...($decodedFromNativePrefix ? [
                'review_stream_decoded_from_native_prefix' => true,
                'native_prefix_filters' => $nativePrefixFilters,
                'stopped_before_filter' => $stoppedBeforeFilter,
            ] : []),
            ...$jpegMarkerMetadata,
            'jpeg_marker_framing_used' => true,
            'payload_in_visible_text' => false,
            'review_only' => true,
            'native_raster_decode' => false,
        ];
    }

    /**
     * @param list<string> $resolvedFilters
     */
    private function imageStreamCanInferMissingDctFilterBoundary(
        string $dictionary,
        array $resolvedFilters,
        string $stream
    ): bool {
        return $resolvedFilters === []
            && $this->pdfDictionaryValuesForName($dictionary, 'Filter') === []
            && $this->pdfNameValue($this->extractPdfNameValue($dictionary, 'Subtype') ?? '') === 'Image'
            && $this->dctPreviewBytesAreCompleteJpeg($stream);
    }

    /**
     * @param list<string> $resolvedFilters
     * @param list<string> $nativePrefixFilters
     * @return array<string, mixed>|null
     */
    private function dctPreviewUnverifiedStreamBoundaryReview(
        array $resolvedFilters,
        string $stream,
        string $reviewStream,
        bool $decodedFromNativePrefix = false,
        array $nativePrefixFilters = [],
        ?string $stoppedBeforeFilter = null
    ): ?array
    {
        if (!in_array('DCTDecode', $resolvedFilters, true) && !in_array('DCT', $resolvedFilters, true)) {
            return null;
        }

        $start = 0;
        $length = strlen($reviewStream);
        while ($start < $length) {
            if (str_contains("\x00\t\n\f\r ", $reviewStream[$start])) {
                $start++;
                continue;
            }

            if ($start + 3 <= $length && substr($reviewStream, $start, 3) === "\xef\xbb\xbf") {
                $start += 3;
                continue;
            }

            break;
        }
        if ($start >= $length) {
            return null;
        }

        $soiOffset = $this->dctPreviewSoiOffset($reviewStream);
        $laterSoiOffset = null;
        if ($soiOffset === null) {
            $laterSoiOffset = $this->dctPreviewLaterSoiOffset($reviewStream, $start + 1);
        }
        $hasSoi = $soiOffset !== null && $soiOffset === $start;
        $hasPreSoiBytes = !$hasSoi && $laterSoiOffset !== null;
        if ($hasSoi) {
            $start = $soiOffset;
        } elseif ($hasPreSoiBytes) {
            $start = $laterSoiOffset;
        }
        $eoiEnd = null;
        $paddingEnd = null;
        if ($hasSoi || $hasPreSoiBytes) {
            foreach ($this->dctPreviewEoiEndOffsets($reviewStream, $start) as $candidateEnd) {
                $eoiEnd = $candidateEnd;
                $paddingEnd = $this->skipDctPreviewPadding($reviewStream, $candidateEnd);
                break;
            }
        }

        $invalidReason = $hasPreSoiBytes
            ? 'pre_jpeg_soi_non_padding_bytes'
            : (!$hasSoi
            ? 'missing_jpeg_soi'
            : ($eoiEnd === null ? 'missing_jpeg_eoi' : 'post_jpeg_eoi_non_padding_bytes'));
        $preSoiBytes = $start > 0 ? substr($reviewStream, 0, $start) : '';
        $jpegMarkerMetadata = ($hasSoi || $hasPreSoiBytes)
            ? $this->dctPreviewJpegMarkerReviewMetadata($reviewStream, $start, $eoiEnd)
            : $this->dctPreviewJpegMarkerReviewMetadata($reviewStream);

        return [
            'source' => 'dctdecode_jpeg_marker_boundary_unverified',
            'valid_jpeg_marker_boundary' => false,
            'invalid_reason' => $invalidReason,
            'jpeg_soi_offset' => ($hasSoi || $hasPreSoiBytes) ? $start : null,
            'jpeg_marker_fill_byte_count' => ($hasSoi || $hasPreSoiBytes) ? $this->dctPreviewMarkerFillByteCount($reviewStream, $start) : 0,
            'jpeg_eoi_end_offset' => $eoiEnd,
            'raw_stream_length' => strlen($stream),
            'review_stream_length' => strlen($reviewStream),
            'padding_byte_count' => $eoiEnd === null || $paddingEnd === null ? 0 : max(0, $paddingEnd - $eoiEnd),
            'stream_trimmed_to_jpeg_eoi' => false,
            'stream_trimmed_to_jpeg_soi' => false,
            'pre_jpeg_soi_byte_count' => strlen($preSoiBytes),
            'pre_jpeg_soi_sha256' => $preSoiBytes === '' ? null : hash('sha256', $preSoiBytes),
            'pre_jpeg_soi_payload_in_visible_text' => false,
            ...($decodedFromNativePrefix ? [
                'review_stream_decoded_from_native_prefix' => true,
                'native_prefix_filters' => $nativePrefixFilters,
                'stopped_before_filter' => $stoppedBeforeFilter,
            ] : []),
            ...$jpegMarkerMetadata,
            'jpeg_marker_framing_used' => false,
            'payload_in_visible_text' => false,
            'review_only' => true,
            'native_raster_decode' => false,
        ];
    }

    /**
     * @return array{sos_marker_seen: bool, dri_marker_seen: bool, jpeg_restart_interval: int|null, byte_stuffed_ff00_seen: bool, restart_marker_seen: bool, sof_marker_seen: bool, jpeg_sof_marker: string|null, jpeg_precision: int|null, jpeg_width: int|null, jpeg_height: int|null, jpeg_component_count: int|null}
     */
    private function dctPreviewJpegMarkerReviewMetadata(
        string $bytes,
        int $startOffset = 0,
        ?int $limitOffset = null
    ): array {
        $metadata = [
            'sos_marker_seen' => false,
            'dri_marker_seen' => false,
            'jpeg_restart_interval' => null,
            'byte_stuffed_ff00_seen' => false,
            'restart_marker_seen' => false,
            'sof_marker_seen' => false,
            'jpeg_sof_marker' => null,
            'jpeg_precision' => null,
            'jpeg_width' => null,
            'jpeg_height' => null,
            'jpeg_component_count' => null,
        ];
        $limit = min($limitOffset ?? strlen($bytes), strlen($bytes));
        $start = $this->dctPreviewSoiOffset($bytes, $startOffset, $limit);
        if ($start === null) {
            return $metadata;
        }

        $offset = $start + 2;
        $insideScanData = false;
        while ($offset < $limit) {
            $markerStart = strpos($bytes, "\xff", $offset);
            if ($markerStart === false || $markerStart + 1 >= $limit) {
                break;
            }

            $markerOffset = $markerStart + 1;
            while ($markerOffset < $limit && $bytes[$markerOffset] === "\xff") {
                $markerOffset++;
            }
            if ($markerOffset >= $limit) {
                break;
            }

            $marker = ord($bytes[$markerOffset]);
            if ($marker === 0x00) {
                if ($insideScanData) {
                    $metadata['byte_stuffed_ff00_seen'] = true;
                }
                $offset = $markerOffset + 1;
                continue;
            }
            if ($marker >= 0xd0 && $marker <= 0xd7) {
                if ($insideScanData) {
                    $metadata['restart_marker_seen'] = true;
                }
                $offset = $markerOffset + 1;
                continue;
            }
            if ($marker === 0xd9) {
                break;
            }
            if ($marker === 0xd8 || $marker === 0x01) {
                $offset = $markerOffset + 1;
                continue;
            }

            $lengthOffset = $markerOffset + 1;
            if ($lengthOffset + 2 > $limit) {
                break;
            }

            $segmentLength = (ord($bytes[$lengthOffset]) << 8) | ord($bytes[$lengthOffset + 1]);
            if ($segmentLength < 2) {
                break;
            }

            $payloadStart = $lengthOffset + 2;
            $segmentEnd = $payloadStart + ($segmentLength - 2);
            if ($segmentEnd > $limit) {
                break;
            }

            if ($this->dctPreviewSofMarkerHasDimensions($marker) && $segmentLength >= 8) {
                $metadata['sof_marker_seen'] = true;
                $metadata['jpeg_sof_marker'] = $this->dctPreviewSofMarkerName($marker);
                $metadata['jpeg_precision'] = ord($bytes[$payloadStart]);
                $metadata['jpeg_height'] = (ord($bytes[$payloadStart + 1]) << 8) | ord($bytes[$payloadStart + 2]);
                $metadata['jpeg_width'] = (ord($bytes[$payloadStart + 3]) << 8) | ord($bytes[$payloadStart + 4]);
                $metadata['jpeg_component_count'] = ord($bytes[$payloadStart + 5]);
            }

            if ($marker === 0xdd) {
                $metadata['dri_marker_seen'] = true;
                if ($segmentLength >= 4) {
                    $metadata['jpeg_restart_interval'] = (ord($bytes[$payloadStart]) << 8)
                        | ord($bytes[$payloadStart + 1]);
                }
            } elseif ($marker === 0xda) {
                $metadata['sos_marker_seen'] = true;
                $insideScanData = true;
            }

            $offset = $segmentEnd;
        }

        return $metadata;
    }

    private function dctPreviewSofMarkerHasDimensions(int $marker): bool
    {
        return in_array($marker, [0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf], true);
    }

    private function dctPreviewSofMarkerName(int $marker): string
    {
        return match ($marker) {
            0xc0 => 'SOF0',
            0xc1 => 'SOF1',
            0xc2 => 'SOF2',
            0xc3 => 'SOF3',
            0xc5 => 'SOF5',
            0xc6 => 'SOF6',
            0xc7 => 'SOF7',
            0xc9 => 'SOF9',
            0xca => 'SOF10',
            0xcb => 'SOF11',
            0xcd => 'SOF13',
            0xce => 'SOF14',
            0xcf => 'SOF15',
            default => sprintf('SOF0x%02X', $marker),
        };
    }

    private function dctPreviewLaterSoiOffset(string $bytes, int $startOffset): ?int
    {
        $offset = max(0, $startOffset);
        while (($candidate = strpos($bytes, "\xff", $offset)) !== false) {
            $soiOffset = $this->dctPreviewSoiOffset($bytes, $candidate);
            if ($soiOffset !== null) {
                return $soiOffset;
            }

            $offset = $candidate + 1;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param list<string|null> $filters
     */
    private function decodeImageStreamBeforeFilter(
        string $dictionary,
        string $stream,
        array $objects,
        array $filters,
        int $stopBeforeIndex
    ): ?string {
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);

        for ($index = 0; $index < $stopBeforeIndex; $index++) {
            $filter = $filters[$index] ?? null;
            if ($filter === null) {
                continue;
            }

            $decodeParmsValue = $this->decodeParmsValueForImageFilterIndex($filters, $decodeParms, $index);
            $resolvedDecodeParms = $this->resolvedDecodeParmsDictionary($decodeParmsValue, $objects);
            if (!$this->canApplyImageDecodeParms($filter, $resolvedDecodeParms, $objects)) {
                return null;
            }
            if (!$this->streamFilterInputHasExplicitEndMarker($filter, $stream, $resolvedDecodeParms, $objects)) {
                return null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeDctAscii85NativePrefixStream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $resolvedDecodeParms, $objects),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $resolvedDecodeParms, $objects),
                'Crypt' => $this->decodeCryptIdentityStream($stream, $resolvedDecodeParms, $objects),
                default => null,
            };
            if ($decoded === null) {
                return null;
            }

            $stream = $decoded;
        }

        return $stream;
    }

    private function decodeDctAscii85NativePrefixStream(string $stream): ?string
    {
        $decoded = $this->decodeAscii85Stream($stream);
        if ($decoded !== null) {
            return $decoded;
        }

        $body = trim($stream, "\0\t\n\f\r ");
        if (!str_starts_with($body, "\xef\xbb\xbf<~")) {
            return null;
        }

        return $this->decodeAscii85Stream(substr($body, 3));
    }

    /**
     * @param array<int, string> $objects
     * @param list<string|null> $filters
     */
    private function decodeImageStreamBeforeUnsupportedFilterBeforeDct(
        string $dictionary,
        string $stream,
        array $objects,
        array $filters,
        int $firstFilterIndex,
        int $dctFilterIndex
    ): ?string {
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        $decodedNativeFilterCount = 0;

        for ($index = $firstFilterIndex; $index < $dctFilterIndex; $index++) {
            $filter = $filters[$index] ?? null;
            if ($filter === null) {
                continue;
            }

            $decodeParmsValue = $this->decodeParmsValueForImageFilterIndex($filters, $decodeParms, $index);
            $resolvedDecodeParms = $this->resolvedDecodeParmsDictionary($decodeParmsValue, $objects);
            if (
                !$this->canApplyImageDecodeParms($filter, $resolvedDecodeParms, $objects)
                || !$this->streamFilterInputHasExplicitEndMarker($filter, $stream)
            ) {
                return $decodedNativeFilterCount > 0 ? $stream : null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeDctAscii85NativePrefixStream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $resolvedDecodeParms, $objects),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $resolvedDecodeParms, $objects),
                'Crypt' => $this->decodeCryptIdentityStream($stream, $resolvedDecodeParms, $objects),
                default => null,
            };
            if ($decoded === null) {
                return $decodedNativeFilterCount > 0 ? $stream : null;
            }

            $stream = $decoded;
            $decodedNativeFilterCount++;
        }

        return null;
    }

    private function dctPreviewSoiOffset(string $bytes, int $startOffset = 0, ?int $limitOffset = null): ?int
    {
        $limit = min($limitOffset ?? strlen($bytes), strlen($bytes));
        $start = $startOffset;
        while ($start < $limit) {
            if (str_contains("\x00\t\n\f\r ", $bytes[$start])) {
                $start++;
                continue;
            }

            if ($start + 3 <= $limit && substr($bytes, $start, 3) === "\xef\xbb\xbf") {
                $start += 3;
                continue;
            }

            break;
        }
        if ($start >= $limit || $bytes[$start] !== "\xff") {
            return null;
        }

        $markerOffset = $start + 1;
        while ($markerOffset < $limit && $bytes[$markerOffset] === "\xff") {
            $markerOffset++;
        }
        if ($markerOffset >= $limit || ord($bytes[$markerOffset]) !== 0xd8) {
            return null;
        }

        return $start;
    }

    private function dctPreviewMarkerFillByteCount(string $bytes, int $soiOffset): int
    {
        $count = 0;
        $offset = $soiOffset + 1;
        $length = strlen($bytes);
        while ($offset < $length && $bytes[$offset] === "\xff") {
            $count++;
            $offset++;
        }

        return $count;
    }

    private function dctPreviewBytesAreCompleteJpeg(string $bytes): bool
    {
        $length = strlen($bytes);
        $start = $this->dctPreviewSoiOffset($bytes);
        if ($start === null) {
            return false;
        }

        foreach ($this->dctPreviewEoiEndOffsets($bytes, $start) as $eoiEnd) {
            if ($this->skipDctPreviewPadding($bytes, $eoiEnd) === $length) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function dctPreviewEoiEndOffsets(string $bytes, int $startOffset = 0): array
    {
        $limit = strlen($bytes);
        $start = $this->dctPreviewSoiOffset($bytes, $startOffset, $limit);
        if ($start === null) {
            return [];
        }

        $offset = $start + 2;
        while ($offset < $limit) {
            $markerStart = strpos($bytes, "\xff", $offset);
            if ($markerStart === false || $markerStart + 1 >= $limit) {
                return [];
            }

            $markerOffset = $markerStart + 1;
            while ($markerOffset < $limit && $bytes[$markerOffset] === "\xff") {
                $markerOffset++;
            }
            if ($markerOffset >= $limit) {
                return [];
            }

            $marker = ord($bytes[$markerOffset]);
            if ($marker === 0x00) {
                $offset = $markerOffset + 1;
                continue;
            }
            if ($marker === 0xd9) {
                return [$markerOffset + 1];
            }
            if ($marker === 0xd8 || $marker === 0x01 || ($marker >= 0xd0 && $marker <= 0xd7)) {
                $offset = $markerOffset + 1;
                continue;
            }

            $lengthOffset = $markerOffset + 1;
            if ($lengthOffset + 2 > $limit) {
                return [];
            }

            $segmentLength = (ord($bytes[$lengthOffset]) << 8) | ord($bytes[$lengthOffset + 1]);
            if ($segmentLength < 2) {
                return $this->dctPreviewLenientEoiEndOffsets($bytes, $markerOffset + 1);
            }

            $segmentEnd = $lengthOffset + 2 + ($segmentLength - 2);
            if ($segmentEnd > $limit) {
                if (ord($bytes[$lengthOffset]) <= 0x0f) {
                    return [];
                }

                return $this->dctPreviewLenientEoiEndOffsets($bytes, $markerOffset + 1);
            }

            $offset = $segmentEnd;
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function dctPreviewLenientEoiEndOffsets(string $bytes, int $offset): array
    {
        $offsets = [];
        while (($eoi = strpos($bytes, "\xff\xd9", $offset)) !== false) {
            $offsets[] = $eoi + 2;
            $offset = $eoi + 2;
        }

        return $offsets;
    }

    private function skipDctPreviewPadding(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length && str_contains("\x00\t\n\f\r ", $value[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private function skipDctPreviewTerminatorPadding(string $value, int $offset): int
    {
        return $this->skipPdfWhitespace($value, $this->skipDctPreviewPadding($value, $offset));
    }

    private function streamEndKeywordAt(string $value, int $offset): bool
    {
        if (substr($value, $offset, 9) !== 'endstream') {
            return false;
        }

        $after = $offset + 9;
        return $after >= strlen($value) || $this->isPdfWhitespace($value[$after]);
    }

    private function isPdfWhitespace(string $char): bool
    {
        return $char === "\0" || ctype_space($char);
    }

    private function streamEndTerminatorAt(string $value, int $offset, int $streamStart): bool
    {
        if (!$this->streamEndKeywordAt($value, $offset)) {
            return false;
        }

        if ($offset <= $streamStart) {
            return true;
        }

        $previous = $value[$offset - 1] ?? '';
        return $previous === "\n" || $previous === "\r";
    }

    private function stripStreamTerminatingLineEnding(string $stream): string
    {
        if (str_ends_with($stream, "\r\n")) {
            return substr($stream, 0, -2);
        }
        if (str_ends_with($stream, "\n") || str_ends_with($stream, "\r")) {
            return substr($stream, 0, -1);
        }

        return $stream;
    }

    private function literalStringBytes(string $literal): string
    {
        if (!str_starts_with($literal, '(')) {
            return '';
        }

        $out = '';
        $length = strlen($literal);
        $depth = 0;
        for ($index = 0; $index < $length; $index++) {
            $char = $literal[$index];
            if ($char === '(') {
                if ($depth > 0) {
                    $out .= $char;
                }
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth <= 0) {
                    break;
                }
                $out .= $char;
                continue;
            }
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }

            $next = $literal[$index + 1] ?? '';
            if ($next === "\r" || $next === "\n") {
                $index++;
                if ($next === "\r" && ($literal[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                continue;
            }
            if ($next !== '' && preg_match('/[0-7]/', $next) === 1) {
                $octal = $next;
                for ($extra = 0; $extra < 2; $extra++) {
                    $candidate = $literal[$index + 2 + $extra] ?? '';
                    if ($candidate === '' || preg_match('/[0-7]/', $candidate) !== 1) {
                        break;
                    }
                    $octal .= $candidate;
                }
                $out .= chr(octdec($octal) & 0xff);
                $index += strlen($octal);
                continue;
            }

            $out .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(', ')', '\\' => $next,
                default => $next,
            };
            $index++;
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private function byteList(string $bytes): array
    {
        if ($bytes === '') {
            return [];
        }

        return array_values(unpack('C*', $bytes));
    }

    /**
     * @param array<int, string> $objects
     */
    private function jbig2GlobalsPresent(string $dictionary, array $objects): bool
    {
        $value = $this->extractPdfNameValue($dictionary, 'DecodeParms');
        if ($value === null) {
            return false;
        }

        return $this->pdfValueContainsName($value, 'JBIG2Globals', $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array{present: bool, source: string|null, object: int|null, length: int|null, sha256: string|null, preview_hex: string|null}
     */
    private function jbig2GlobalsMetadata(string $decodeParms, array $objects): array
    {
        $value = $this->extractPdfNameValue($decodeParms, 'JBIG2Globals');
        if ($value === null) {
            return [
                'present' => false,
                'source' => null,
                'object' => null,
                'length' => null,
                'sha256' => null,
                'preview_hex' => null,
            ];
        }

        $bytes = $this->pdfBytesFromValue($value, $objects);
        $payload = $bytes['bytes'] ?? null;

        return [
            'present' => true,
            'source' => $this->pdfValueSource($value),
            'object' => $this->objectReferenceNumber($value),
            'length' => is_string($payload) ? strlen($payload) : null,
            'sha256' => is_string($payload) ? hash('sha256', $payload) : null,
            'preview_hex' => is_string($payload) ? strtoupper(bin2hex(substr($payload, 0, 16))) : null,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function pdfValueContainsName(string $value, string $name, array $objects, array $seenObjects = []): bool
    {
        $resolvedValue = $this->resolvePdfValueWithSeen($value, $objects, $seenObjects);
        $resolved = $resolvedValue['value'];
        $seenObjects = $resolvedValue['seen'];
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/', $resolved) === 1) {
            return true;
        }
        if (!str_starts_with($resolved, '[')) {
            return false;
        }

        foreach ($this->pdfArrayValues($resolved) as $entry) {
            if ($this->pdfValueContainsName($entry, $name, $objects, $seenObjects)) {
                return true;
            }
        }

        return false;
    }

    private function extractPdfNameValue(string $dictionary, string $name): ?string
    {
        $offset = $this->skipPdfWhitespace($dictionary, 0);
        if (substr($dictionary, $offset, 2) === '<<') {
            $read = $this->readBalancedDictionary($dictionary, $offset);
            if ($read !== null) {
                return $this->pdfDictionaryValueForName($read['value'], $name);
            }
        }

        return $this->pdfDictionaryValueForName($dictionary, $name);
    }

    private function pdfDictionaryValueForName(string $dictionary, string $name): ?string
    {
        return $this->pdfDictionaryValuesForName($dictionary, $name)[0] ?? null;
    }

    private function duplicatePdfNameDeclarationCount(string $dictionary, string $name): int
    {
        return max(0, count($this->pdfDictionaryValuesForName($dictionary, $name)) - 1);
    }

    private function imageFilterHasMalformedExtraOperand(string $dictionary): bool
    {
        return $this->imageFilterExtraOperandBoundary($dictionary) !== null;
    }

    /**
     * @return array{after_comment: bool}|null
     */
    private function imageFilterExtraOperandBoundary(string $dictionary): ?array
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $read = $this->readBalancedDictionary($body, 0);
            if ($read !== null) {
                $body = trim(substr($read['value'], 2, -2));
            }
        }

        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $key = $this->readPdfValueWithOffset($body, $offset);
            if ($key === null || !str_starts_with(trim($key['value']), '/')) {
                break;
            }

            $value = $this->readPdfValueWithOffset($body, $key['next']);
            if ($value === null) {
                break;
            }

            if (
                $this->pdfNameValue($key['value']) === 'Filter'
            ) {
                return $this->imageFilterExtraOperandAfterValueBoundary($body, $value['next']);
            }

            $offset = $value['next'];
        }

        return null;
    }

    private function imageFilterExtraOperandAfterValue(string $body, int $offset): bool
    {
        return $this->imageFilterExtraOperandAfterValueBoundary($body, $offset) !== null;
    }

    /**
     * @return array{after_comment: bool}|null
     */
    private function imageFilterExtraOperandAfterValueBoundary(string $body, int $offset): ?array
    {
        $extraOperandAfterComment = false;
        $skippedComment = false;
        $offset = $this->skipPdfWhitespaceTrackingComments($body, $offset, $skippedComment);
        $extraOperandAfterComment = $extraOperandAfterComment || $skippedComment;
        $length = strlen($body);
        while ($offset < $length) {
            if (substr($body, $offset, 2) === '>>' || ($body[$offset] ?? '') === ']') {
                return null;
            }

            if (($body[$offset] ?? '') !== '/') {
                return ['after_comment' => $extraOperandAfterComment];
            }

            $nameEnd = $this->pdfNameTokenEndOffset($body, $offset);
            if ($nameEnd <= $offset + 1) {
                return null;
            }

            $name = $this->decodePdfName(substr($body, $offset + 1, $nameEnd - $offset - 1));
            if ($this->imageFilterNameLooksLikeDecoder($name) || $this->imageFilterUnknownNamePrecedesLength($body, $nameEnd)) {
                return ['after_comment' => $extraOperandAfterComment];
            }

            $value = $this->readPdfValueWithOffset($body, $nameEnd);
            if ($value === null || $value['next'] <= $nameEnd) {
                return ['after_comment' => $extraOperandAfterComment];
            }

            if ($name === 'DecodeParms') {
                $extraOperandEnd = $this->imageDecodeParmsExtraOperandEndAfterValue($body, $value['next']);
                if ($extraOperandEnd !== null) {
                    $skippedComment = false;
                    $offset = $this->skipPdfWhitespaceTrackingComments($body, $extraOperandEnd, $skippedComment);
                    $extraOperandAfterComment = $extraOperandAfterComment || $skippedComment;
                    continue;
                }
            }

            $skippedComment = false;
            $offset = $this->skipPdfWhitespaceTrackingComments($body, $value['next'], $skippedComment);
            $extraOperandAfterComment = $extraOperandAfterComment || $skippedComment;
        }

        return null;
    }

    private function imageFilterUnknownNamePrecedesLength(string $body, int $offset): bool
    {
        $next = $this->skipPdfWhitespace($body, $offset);
        if ($next >= strlen($body) || ($body[$next] ?? '') !== '/') {
            return false;
        }

        $nextEnd = $this->pdfNameTokenEndOffset($body, $next);
        if ($nextEnd <= $next + 1) {
            return false;
        }

        if ($this->decodePdfName(substr($body, $next + 1, $nextEnd - $next - 1)) !== 'Length') {
            return false;
        }

        return $this->readPdfValueWithOffset($body, $nextEnd) !== null;
    }

    private function imageDirectFilterExtraOperandEndAfterValue(string $body, int $offset): ?int
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        if ($offset >= strlen($body) || substr($body, $offset, 2) === '>>' || ($body[$offset] ?? '') === ']') {
            return null;
        }

        if (($body[$offset] ?? '') !== '/') {
            $extra = $this->readPdfValueWithOffset($body, $offset);

            return $extra['next'] ?? null;
        }

        $nameEnd = $this->pdfNameTokenEndOffset($body, $offset);
        if ($nameEnd <= $offset + 1) {
            return null;
        }

        $name = $this->decodePdfName(substr($body, $offset + 1, $nameEnd - $offset - 1));
        if ($this->imageFilterNameLooksLikeDecoder($name) || $this->imageFilterUnknownNamePrecedesLength($body, $nameEnd)) {
            return $nameEnd;
        }

        return null;
    }

    private function pdfDictionaryValueForNameFromOffset(string $body, string $name, int $offset): ?string
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        $length = strlen($body);
        while ($offset < $length) {
            $key = $this->readPdfValueWithOffset($body, $offset);
            if ($key === null || !str_starts_with(trim($key['value']), '/')) {
                break;
            }

            $value = $this->readPdfValueWithOffset($body, $key['next']);
            if ($value === null) {
                break;
            }

            if ($this->pdfNameValue($key['value']) === $name) {
                return $value['value'];
            }

            $offset = $value['next'];
        }

        return null;
    }

    private function imageFilterNameLooksLikeDecoder(string $name): bool
    {
        return in_array($name, [
            'ASCIIHexDecode',
            'AHx',
            'ASCII85Decode',
            'A85',
            'RunLengthDecode',
            'RL',
            'LZWDecode',
            'LZW',
            'FlateDecode',
            'Fl',
            'Crypt',
            'DCTDecode',
            'DCT',
            'CCITTFaxDecode',
            'CCF',
            'JPXDecode',
            'JBIG2Decode',
        ], true);
    }

    /**
     * @return list<string>
     */
    private function pdfDictionaryValuesForName(string $dictionary, string $name): array
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $dictionary = $this->readBalancedDictionary($body, 0);
            if ($dictionary !== null) {
                $body = trim(substr($dictionary['value'], 2, -2));
            }
        }

        $offset = 0;
        $length = strlen($body);
        $values = [];
        while ($offset < $length) {
            $key = $this->readPdfValueWithOffset($body, $offset);
            if ($key === null || !str_starts_with(trim($key['value']), '/')) {
                break;
            }

            $value = $this->readPdfValueWithOffset($body, $key['next']);
            if ($value === null) {
                break;
            }

            if ($this->pdfNameValue($key['value']) === $name) {
                $values[] = $value['value'];
            }

            $offset = $this->pdfDictionaryScanOffsetAfterValue($body, $key['value'], $value['next']);
        }

        return $values;
    }

    private function pdfDictionaryScanOffsetAfterValue(string $body, string $keyValue, int $valueNext): int
    {
        $keyName = $this->pdfNameValue($keyValue);
        if ($keyName === null) {
            return $valueNext;
        }

        $canonicalKey = self::INLINE_IMAGE_KEY_ABBREVIATIONS[$keyName] ?? $keyName;
        if ($canonicalKey !== 'DecodeParms') {
            return $valueNext;
        }

        return $this->imageDecodeParmsExtraOperandEndAfterValue($body, $valueNext) ?? $valueNext;
    }

    private function readPdfValueAt(string $source, int $offset): ?string
    {
        $read = $this->readPdfValueWithOffset($source, $offset);

        return $read['value'] ?? null;
    }

    /**
     * @return array{value: string, next: int}|null
     */
    private function readPdfValueWithOffset(string $source, int $offset): ?array
    {
        $offset = $this->skipPdfWhitespace($source, $offset);
        $length = strlen($source);
        if ($offset >= $length) {
            return null;
        }

        if (substr($source, $offset, 2) === '<<') {
            return $this->readBalancedDictionary($source, $offset);
        }
        if ($source[$offset] === '[') {
            return $this->readBalancedArray($source, $offset);
        }
        if ($source[$offset] === '/') {
            if (preg_match('/\G\/[^\s\[\]()<>{}\/%]+/s', $source, $match, 0, $offset) !== 1) {
                return null;
            }

            return ['value' => $match[0], 'next' => $offset + strlen($match[0])];
        }
        if ($source[$offset] === '(') {
            $next = $this->skipPdfLiteralString($source, $offset);

            return ['value' => substr($source, $offset, $next - $offset), 'next' => $next];
        }
        if ($source[$offset] === '<') {
            $next = strpos($source, '>', $offset + 1);
            if ($next === false) {
                return null;
            }

            return ['value' => substr($source, $offset, $next - $offset + 1), 'next' => $next + 1];
        }
        $reference = $this->pdfIndirectReferenceTokenAt($source, $offset);
        if ($reference !== null) {
            return ['value' => $reference['token'], 'next' => $reference['endOffset']];
        }
        if (preg_match('/\G(?:true|false|null)\b/s', $source, $match, 0, $offset) === 1) {
            return ['value' => $match[0], 'next' => $offset + strlen($match[0])];
        }
        if (preg_match('/\G[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/s', $source, $match, 0, $offset) === 1) {
            $next = $offset + strlen($match[0]);
            if ($next < $length && !$this->isPdfBareTokenDelimiter($source[$next])) {
                return null;
            }

            return ['value' => $match[0], 'next' => $next];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function pdfArrayValues(string $array): array
    {
        $body = trim($array);
        if (!str_starts_with($body, '[') || !str_ends_with($body, ']')) {
            return [];
        }

        $values = [];
        $offset = 1;
        $end = strlen($body) - 1;
        while ($offset < $end) {
            $read = $this->readPdfValueWithOffset($body, $offset);
            if ($read === null || $read['next'] <= $offset) {
                break;
            }

            $values[] = $read['value'];
            $offset = $read['next'];
        }

        return $values;
    }

    private function pdfNameValue(string $value): ?string
    {
        $value = trim($value);
        if (!str_starts_with($value, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($value, 1));
    }

    private function pdfNameTokenEndOffset(string $body, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($body) && !str_contains(" \t\r\n\f[]()<>{}/%", $body[$end])) {
            $end++;
        }

        return $end;
    }

    /**
     * @param array<int|string, mixed> $objects
     * @param array<string, true> $seenObjects
     */
    private function resolvePdfValue(string $value, array $objects, array $seenObjects = []): string
    {
        return $this->resolvePdfValueWithSeen($value, $objects, $seenObjects)['value'];
    }

    /**
     * @param array<int|string, mixed> $objects
     * @param array<string, true> $seenObjects
     * @return array{value: string, seen: array<string, true>}
     */
    private function resolvePdfValueWithSeen(string $value, array $objects, array $seenObjects = []): array
    {
        $trimmed = $this->trimPdfValueBoundary($value);
        $reference = $this->pdfIndirectReferenceTokenAt($trimmed, 0);
        if ($reference === null || $this->skipPdfWhitespace($trimmed, $reference['endOffset']) !== strlen($trimmed)) {
            return ['value' => $trimmed, 'seen' => $seenObjects];
        }

        $objectNumber = $reference['objectNumber'];
        $generation = $reference['generation'];
        $seenKey = $objectNumber . ':' . $generation;
        $objectBody = $this->pdfObjectMapValueForReference($objects, $objectNumber, $generation);
        if (isset($seenObjects[$seenKey]) || $objectBody === null) {
            return ['value' => $trimmed, 'seen' => $seenObjects];
        }

        $seenObjects[$seenKey] = true;

        return $this->resolvePdfValueWithSeen($objectBody, $objects, $seenObjects);
    }

    private function trimPdfValueBoundary(string $value): string
    {
        $trimmed = trim($value);
        $start = $this->skipPdfWhitespace($trimmed, 0);

        return $start <= 0 ? $trimmed : ltrim(substr($trimmed, $start));
    }

    /**
     * @param array<int|string, mixed> $objects
     */
    private function pdfObjectMapValueForReference(array $objects, int $objectNumber, int $generation): ?string
    {
        foreach ([$objectNumber . ':' . $generation, $objectNumber . ' ' . $generation] as $key) {
            if (array_key_exists($key, $objects)) {
                return $this->pdfObjectMapScalarString($objects[$key]);
            }
        }

        if ($generation === 0 && array_key_exists($objectNumber, $objects)) {
            return $this->pdfObjectMapScalarString($objects[$objectNumber]);
        }

        return null;
    }

    private function pdfObjectMapScalarString(mixed $value): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * PDF comments are whitespace, including inside indirect-reference operands.
     *
     * @return array{token: string, objectNumber: int, generation: int, endOffset: int}|null
     */
    private function pdfIndirectReferenceTokenAt(string $source, int $offset): ?array
    {
        $length = strlen($source);
        $start = $this->skipPdfWhitespace($source, $offset);
        if ($start >= $length || preg_match('/\G\d+/s', $source, $objectMatch, 0, $start) !== 1) {
            return null;
        }

        $afterObject = $start + strlen($objectMatch[0]);
        $generationOffset = $this->skipPdfWhitespace($source, $afterObject);
        if (
            $generationOffset <= $afterObject
            || $generationOffset >= $length
            || preg_match('/\G\d+/s', $source, $generationMatch, 0, $generationOffset) !== 1
        ) {
            return null;
        }

        $afterGeneration = $generationOffset + strlen($generationMatch[0]);
        $referenceOffset = $this->skipPdfWhitespace($source, $afterGeneration);
        if (
            $referenceOffset <= $afterGeneration
            || ($source[$referenceOffset] ?? '') !== 'R'
        ) {
            return null;
        }

        $endOffset = $referenceOffset + 1;
        if ($endOffset < $length && !$this->isPdfBareTokenDelimiter($source[$endOffset])) {
            return null;
        }

        return [
            'token' => (int) $objectMatch[0] . ' ' . (int) $generationMatch[0] . ' R',
            'objectNumber' => (int) $objectMatch[0],
            'generation' => (int) $generationMatch[0],
            'endOffset' => $endOffset,
        ];
    }

    private function isPdfBareTokenDelimiter(string $char): bool
    {
        return str_contains(" \t\r\n\f[]()<>{}/%\0", $char);
    }

    /**
     * @return array{value: string, next: int}|null
     */
    private function readBalancedArray(string $source, int $offset): ?array
    {
        $length = strlen($source);
        $depth = 0;
        for ($index = $offset; $index < $length; $index++) {
            $char = $source[$index];
            if ($char === '%') {
                $index += strcspn($source, "\r\n", $index);
                continue;
            }
            if ($char === '(') {
                $index = $this->skipPdfLiteralString($source, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($source, $index, 2) !== '<<') {
                $end = strpos($source, '>', $index + 1);
                if ($end === false) {
                    return null;
                }
                $index = $end;
                continue;
            }
            if ($char === '[') {
                $depth++;
                continue;
            }
            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return ['value' => substr($source, $offset, $index - $offset + 1), 'next' => $index + 1];
            }
        }

        return null;
    }

    /**
     * @return array{value: string, next: int}|null
     */
    private function readBalancedDictionary(string $source, int $offset): ?array
    {
        $length = strlen($source);
        $depth = 0;
        for ($index = $offset; $index < $length; $index++) {
            if ($source[$index] === '%') {
                $index += strcspn($source, "\r\n", $index);
                continue;
            }
            if ($source[$index] === '(') {
                $index = $this->skipPdfLiteralString($source, $index) - 1;
                continue;
            }
            if ($source[$index] === '<' && substr($source, $index, 2) === '<<') {
                $depth++;
                $index++;
                continue;
            }
            if ($source[$index] === '<') {
                $end = strpos($source, '>', $index + 1);
                if ($end === false) {
                    return null;
                }
                $index = $end;
                continue;
            }
            if ($source[$index] !== '>' || substr($source, $index, 2) !== '>>') {
                continue;
            }

            $depth--;
            $index++;
            if ($depth === 0) {
                return ['value' => substr($source, $offset, $index - $offset + 1), 'next' => $index + 1];
            }
        }

        return null;
    }

    private function skipPdfLiteralString(string $source, int $offset): int
    {
        $length = strlen($source);
        $depth = 0;
        for ($index = $offset; $index < $length; $index++) {
            $char = $source[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return $length;
    }

    private function skipPdfWhitespace(string $source, int $offset): int
    {
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '%') {
                $lineEnd = strcspn($source, "\r\n", $offset);
                $offset += $lineEnd;
                continue;
            }
            if (!str_contains(" \t\r\n\f\0", $char)) {
                break;
            }
            $offset++;
        }

        return $offset;
    }

    private function skipPdfWhitespaceTrackingComments(string $source, int $offset, bool &$skippedComment): int
    {
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '%') {
                $skippedComment = true;
                $lineEnd = strcspn($source, "\r\n", $offset);
                $offset += $lineEnd;
                continue;
            }
            if (!str_contains(" \t\r\n\f\0", $char)) {
                break;
            }
            $offset++;
        }

        return $offset;
    }

    private function jpegComponentCount(string $jpegBytes): ?int
    {
        foreach ($this->jpegMarkerSegments($jpegBytes) as $segment) {
            $marker = $segment['marker'];
            if (
                $this->dctPreviewSofMarkerHasDimensions($marker)
                && strlen($segment['payload']) >= 6
            ) {
                return ord($segment['payload'][5]);
            }
        }

        return null;
    }

    private function jpegAdobeApp14Transform(string $jpegBytes): ?int
    {
        foreach ($this->jpegMarkerSegments($jpegBytes) as $segment) {
            if ($segment['marker'] === 0xee && strlen($segment['payload']) >= 12 && str_starts_with($segment['payload'], 'Adobe')) {
                return ord($segment['payload'][11]);
            }
        }

        return null;
    }

    /**
     * @return list<array{marker: int, payload: string}>
     */
    private function jpegMarkerSegments(string $jpegBytes): array
    {
        $segments = [];
        $length = strlen($jpegBytes);
        $soiOffset = $this->dctPreviewSoiOffset($jpegBytes);
        $offset = 0;
        if ($soiOffset !== null) {
            $offset = $soiOffset + 1;
            while ($offset < $length && $jpegBytes[$offset] === "\xff") {
                $offset++;
            }

            if ($offset < $length && ord($jpegBytes[$offset]) === 0xd8) {
                $offset++;
            } else {
                $offset = 0;
            }
        }

        while ($offset + 4 <= $length) {
            if ($jpegBytes[$offset] !== "\xff") {
                $offset++;
                continue;
            }

            while ($offset < $length && $jpegBytes[$offset] === "\xff") {
                $offset++;
            }
            if ($offset >= $length) {
                break;
            }

            $marker = ord($jpegBytes[$offset]);
            $offset++;
            if ($marker === 0xda || $marker === 0xd9) {
                break;
            }
            if ($marker === 0x01 || ($marker >= 0xd0 && $marker <= 0xd7)) {
                continue;
            }
            if ($offset + 2 > $length) {
                break;
            }

            $segmentLength = unpack('n', substr($jpegBytes, $offset, 2))[1];
            if (!is_int($segmentLength) || $segmentLength < 2 || $offset + $segmentLength > $length) {
                break;
            }

            $segments[] = [
                'marker' => $marker,
                'payload' => substr($jpegBytes, $offset + 2, $segmentLength - 2),
            ];
            $offset += $segmentLength;
        }

        return $segments;
    }

    /**
     * @param list<int> $ycck
     * @return list<int>
     */
    private function ycckToCmyk(array $ycck): array
    {
        [$y, $cb, $cr, $k] = $ycck;
        $red = $this->byteValue($y + (1.402 * ($cr - 128)));
        $green = $this->byteValue($y - (0.34414 * ($cb - 128)) - (0.71414 * ($cr - 128)));
        $blue = $this->byteValue($y + (1.772 * ($cb - 128)));

        return [255 - $red, 255 - $green, 255 - $blue, $k];
    }

    /**
     * @param list<int> $cmyk
     * @return array{red: int, green: int, blue: int}
     */
    private function cmykToRgb(array $cmyk): array
    {
        [$cyan, $magenta, $yellow, $black] = $cmyk;
        $blackFactor = (255 - $black) / 255;

        return [
            'red' => $this->byteValue((255 - $cyan) * $blackFactor),
            'green' => $this->byteValue((255 - $magenta) * $blackFactor),
            'blue' => $this->byteValue((255 - $yellow) * $blackFactor),
        ];
    }

    /**
     * @param list<int> $values
     * @param array{bits_per_component?: int, image_decode?: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null} $plan
     * @return list<int>
     */
    private function applyDctImageDecode(array $values, array $plan): array
    {
        $decode = $plan['image_decode'] ?? null;
        if (!is_array($decode) || ($decode['valid_for_components'] ?? false) !== true) {
            return $values;
        }

        $decoded = $this->imageSampleDecodeValues(
            $values,
            $decode,
            max(1, (int) ($plan['bits_per_component'] ?? 8))
        );

        return array_map(fn (float $value): int => $this->byteValue($value * 255), $decoded);
    }

    private function byteValue(int|float $value): int
    {
        return max(0, min(255, (int) round($value)));
    }

    private function normalizedRotation(int $rotation): int
    {
        $rotation %= 360;
        if ($rotation < 0) {
            $rotation += 360;
        }

        return in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback(
            '/#([0-9a-fA-F]{2})/',
            static fn (array $match): string => chr(hexdec($match[1])),
            $name
        ) ?? $name;
    }

    /**
     * @param array<mixed> $bbox
     * @return list<float>
     */
    private function bbox(array $bbox): array
    {
        $values = array_values($bbox);
        if (count($values) !== 4) {
            throw new InvalidArgumentException('bbox must have 4 elements');
        }

        foreach ($values as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new InvalidArgumentException('bbox values must be numeric');
            }
        }

        return array_map(static fn (float|int $value): float => (float) $value, $values);
    }
}
