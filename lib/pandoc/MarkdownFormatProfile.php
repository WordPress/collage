<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownFormatProfile
{
    /** @var list<string> */
    private const FORMAT_CANDIDATES = [
        'markdown_github',
        'markdown-github',
        'markdown_phpextra',
        'markdown-php-extra',
        'markdown_strict',
        'markdown-strict',
        'markdown_mmd',
        'markdown-mmd',
        'commonmark_x',
        'commonmark-x',
        'commonmark',
        'pandoc',
        'markdown',
        'gfm',
        'md',
    ];

    /** @var array<string, string> */
    private const FORMAT_ALIASES = [
        'md' => 'markdown',
        'pandoc' => 'markdown',
        'markdown_github' => 'gfm',
        'markdown-github' => 'gfm',
        'markdown-php-extra' => 'markdown_phpextra',
        'markdown-strict' => 'markdown_strict',
        'markdown-mmd' => 'markdown_mmd',
        'commonmark-x' => 'commonmark_x',
    ];

    /** @var array<string, string> */
    private const PLUS_FORMAT_ALIASES = [
        'markdown+github' => 'gfm',
        'markdown+strict' => 'markdown_strict',
        'markdown+mmd' => 'markdown_mmd',
        'markdown+multimarkdown' => 'markdown_mmd',
        'markdown+php_extra' => 'markdown_phpextra',
        'markdown+php-extra' => 'markdown_phpextra',
        'markdown+phpextra' => 'markdown_phpextra',
    ];

    /** @var array<string, string> */
    private const EXTENSION_ALIASES = [
        'bracketed_span' => 'bracketed_spans',
        'auto_identifier' => 'auto_identifiers',
        'auto_id' => 'auto_identifiers',
        'auto_ids' => 'auto_identifiers',
        'autolink_bare_uris' => 'bare_uri_autolinks',
        'angle-bracket-escapable' => 'angle_brackets_escapable',
        'angle-brackets-escapable' => 'angle_brackets_escapable',
        'angle_bracket_escapable' => 'angle_brackets_escapable',
        'gfm_auto_identifier' => 'gfm_auto_identifiers',
        'definition_list' => 'definition_lists',
        'emoji_shortcode' => 'emoji_shortcodes',
        'example_list' => 'numbered_examples',
        'example_lists' => 'numbered_examples',
        'fancy_list' => 'fancy_lists',
        'header_attrs' => 'header_attributes',
        'header_attribute' => 'header_attributes',
        'hard-line-break' => 'hard_line_breaks',
        'hard-line-breaks' => 'hard_line_breaks',
        'hard_line_break' => 'hard_line_breaks',
        'ignore-line-break' => 'ignore_line_breaks',
        'ignore-line-breaks' => 'ignore_line_breaks',
        'ignore_line_break' => 'ignore_line_breaks',
        'east-asian-line-break' => 'east_asian_line_breaks',
        'east-asian-line-breaks' => 'east_asian_line_breaks',
        'east_asian_line_break' => 'east_asian_line_breaks',
        'attribute' => 'attributes',
        'inline_note' => 'inline_notes',
        'inline-note' => 'inline_notes',
        'inline-notes' => 'inline_notes',
        'inline_attribute' => 'inline_attributes',
        'inline_code_attribute' => 'inline_code_attributes',
        'inline_code_attrs' => 'inline_code_attributes',
        'link_attribute' => 'link_attributes',
        'link_attrs' => 'link_attributes',
        'native_span' => 'native_spans',
        'native_div' => 'native_divs',
        'numbered_example' => 'numbered_examples',
        'numbered_example_list' => 'numbered_examples',
        'numbered_example_lists' => 'numbered_examples',
        'pandoc_title' => 'pandoc_title_block',
        'title_block' => 'pandoc_title_block',
        'line_block' => 'line_blocks',
        'literate-haskell' => 'lhs',
        'literate_haskell' => 'lhs',
        'multimarkdown_title_block' => 'mmd_title_block',
        'raw_latex' => 'raw_tex',
        'subscripts' => 'subscript',
        'superscripts' => 'superscript',
        'task_list' => 'task_lists',
        'task-list' => 'task_lists',
        'tasklist' => 'task_lists',
        'tasklists' => 'task_lists',
        'table_attribute' => 'table_attributes',
        'tables' => 'pipe_tables',
        'pipe_table' => 'pipe_tables',
        'pipe-table' => 'pipe_tables',
        'simple_table' => 'simple_tables',
        'simple-table' => 'simple_tables',
        'grid_table' => 'grid_tables',
        'grid-table' => 'grid_tables',
        'multiline_table' => 'multiline_tables',
        'multiline-table' => 'multiline_tables',
        'wikilink_title_after_pipe' => 'wikilinks_title_after_pipe',
        'wikilink_title_before_pipe' => 'wikilinks_title_before_pipe',
        'wikilink' => 'wikilinks',
        'wiki_link' => 'wikilinks',
        'wiki_links' => 'wikilinks',
        'yaml_metadata' => 'yaml_metadata_block',
        'yaml_metadata_blocks' => 'yaml_metadata_block',
    ];

    /** @var array<string, array{yamlMetadata:bool, titleBlock:bool, rawAttribute:bool, rawHtml:bool, rawTex:bool, latexMacros:bool, rawMarkdown:bool, definitionLists:bool, footnotes:bool, citations:bool, taskLists:bool, pipeTables:bool, simpleTables:bool, gridTables:bool, multilineTables:bool, tableAttributes:bool, listsWithoutPrecedingBlankline:bool}> */
    private const DEFAULTS = [
        'markdown' => [
            'yamlMetadata' => true,
            'titleBlock' => true,
            'rawAttribute' => true,
            'rawHtml' => true,
            'rawTex' => true,
            'latexMacros' => true,
            'rawMarkdown' => true,
            'definitionLists' => true,
            'footnotes' => true,
            'citations' => true,
            'taskLists' => true,
            'pipeTables' => true,
            'simpleTables' => true,
            'gridTables' => true,
            'multilineTables' => true,
            'tableAttributes' => true,
            'listsWithoutPrecedingBlankline' => false,
        ],
        'commonmark' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'latexMacros' => false,
            'rawMarkdown' => true,
            'definitionLists' => false,
            'footnotes' => false,
            'citations' => false,
            'taskLists' => false,
            'pipeTables' => false,
            'simpleTables' => false,
            'gridTables' => false,
            'multilineTables' => false,
            'tableAttributes' => false,
            'listsWithoutPrecedingBlankline' => true,
        ],
        'commonmark_x' => [
            'yamlMetadata' => true,
            'titleBlock' => false,
            'rawAttribute' => true,
            'rawHtml' => true,
            'rawTex' => false,
            'latexMacros' => false,
            'rawMarkdown' => true,
            'definitionLists' => true,
            'footnotes' => true,
            'citations' => false,
            'taskLists' => true,
            'pipeTables' => true,
            'simpleTables' => false,
            'gridTables' => false,
            'multilineTables' => false,
            'tableAttributes' => false,
            'listsWithoutPrecedingBlankline' => true,
        ],
        'gfm' => [
            'yamlMetadata' => true,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'latexMacros' => false,
            'rawMarkdown' => true,
            'definitionLists' => false,
            'footnotes' => true,
            'citations' => false,
            'taskLists' => true,
            'pipeTables' => true,
            'simpleTables' => false,
            'gridTables' => false,
            'multilineTables' => false,
            'tableAttributes' => false,
            'listsWithoutPrecedingBlankline' => true,
        ],
        'markdown_mmd' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => true,
            'rawHtml' => true,
            'rawTex' => false,
            'latexMacros' => false,
            'rawMarkdown' => true,
            'definitionLists' => true,
            'footnotes' => true,
            'citations' => false,
            'taskLists' => false,
            'pipeTables' => true,
            'simpleTables' => false,
            'gridTables' => false,
            'multilineTables' => false,
            'tableAttributes' => false,
            'listsWithoutPrecedingBlankline' => false,
        ],
        'markdown_phpextra' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'latexMacros' => false,
            'rawMarkdown' => true,
            'definitionLists' => true,
            'footnotes' => true,
            'citations' => false,
            'taskLists' => false,
            'pipeTables' => true,
            'simpleTables' => false,
            'gridTables' => false,
            'multilineTables' => false,
            'tableAttributes' => false,
            'listsWithoutPrecedingBlankline' => false,
        ],
        'markdown_strict' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'latexMacros' => false,
            'rawMarkdown' => true,
            'definitionLists' => false,
            'footnotes' => false,
            'citations' => false,
            'taskLists' => false,
            'pipeTables' => false,
            'simpleTables' => false,
            'gridTables' => false,
            'multilineTables' => false,
            'tableAttributes' => false,
            'listsWithoutPrecedingBlankline' => false,
        ],
    ];

    public static function canonicalFormat(mixed $format): string
    {
        return self::canonicalMarkdownFormat($format) ?? 'markdown';
    }

    public static function canonicalMarkdownFormat(mixed $format): ?string
    {
        if (!is_scalar($format)) {
            return null;
        }

        return self::markdownFormatParts($format)['base'];
    }

    public static function rawFamily(string $format): ?string
    {
        $normalized = strtolower(trim($format));
        $base = str_replace('-', '+', $normalized);
        $base = explode('+', $base, 2)[0];

        if (in_array($base, ['html', 'html4', 'html5', 'xhtml'], true)) {
            return 'html';
        }

        if (in_array($base, ['tex', 'latex', 'context'], true)) {
            return 'tex';
        }

        if (self::canonicalMarkdownFormat($format) !== null) {
            return 'markdown';
        }

        return null;
    }

    /**
     * @return array<string, bool>
     */
    public static function markdownExtensionOverrides(mixed $format): array
    {
        $parts = self::markdownFormatParts($format);
        $suffix = $parts['suffix'];
        if ($suffix === '') {
            return [];
        }

        $overrides = [];
        foreach (self::markdownExtensionTokens($suffix) as [$sign, $extension]) {
            $overrides[self::canonicalExtension($extension)] = $sign === '+';
        }

        return $overrides;
    }

    public static function markdownExtensionOptionSuffix(mixed $extensions): string
    {
        if (is_scalar($extensions)) {
            return trim((string) $extensions);
        }

        if (!is_array($extensions)) {
            return '';
        }

        $tokens = [];
        foreach ($extensions as $name => $value) {
            if (is_int($name)) {
                if (!is_scalar($value)) {
                    continue;
                }

                $token = trim((string) $value);
                if ($token === '') {
                    continue;
                }
                $tokens[] = str_starts_with($token, '+') || str_starts_with($token, '-')
                    ? $token
                    : '+' . $token;
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $tokens[] = (self::extensionOptionEnabled($value) ? '+' : '-') . (string) $name;
        }

        return implode('', $tokens);
    }

    private static function canonicalExtension(string $extension): string
    {
        return self::EXTENSION_ALIASES[$extension] ?? $extension;
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private static function markdownExtensionTokens(string $suffix): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($suffix);
        $known = self::knownExtensionNames();
        while ($offset < $length) {
            $sign = $suffix[$offset];
            if ($sign !== '+' && $sign !== '-') {
                $offset++;
                continue;
            }

            $offset++;
            $extension = null;
            foreach ($known as $candidate) {
                $candidateLength = strlen($candidate);
                if ($candidateLength === 0 || substr($suffix, $offset, $candidateLength) !== $candidate) {
                    continue;
                }

                $next = $suffix[$offset + $candidateLength] ?? '';
                if ($next === '' || $next === '+' || $next === '-') {
                    $extension = $candidate;
                    $offset += $candidateLength;
                    break;
                }
            }

            if ($extension === null) {
                $remaining = substr($suffix, $offset);
                if (preg_match('/^[A-Za-z0-9_]+(?:-[A-Za-z0-9_]+)*/', $remaining, $match) !== 1) {
                    continue;
                }

                $extension = strtolower($match[0]);
                $offset += strlen($match[0]);
            }

            $tokens[] = [$sign, strtolower($extension)];
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private static function knownExtensionNames(): array
    {
        $extensions = array_merge(
            array_keys(self::EXTENSION_ALIASES),
            array_values(self::EXTENSION_ALIASES),
            [
                'ascii_identifiers',
                'alerts',
                'all_symbols_escapable',
                'angle_brackets_escapable',
                'attributes',
                'bare_uri_autolinks',
                'blank_before_blockquote',
                'blank_before_header',
                'bracketed_spans',
                'citations',
                'emoji',
                'emoji_shortcodes',
                'fenced_code_attributes',
                'fenced_divs',
                'fancy_lists',
                'footnotes',
                'gfm_auto_identifiers',
                'hard_line_breaks',
                'header_attributes',
                'ignore_line_breaks',
                'east_asian_line_breaks',
                'implicit_header_references',
                'inline_notes',
                'inline_attributes',
                'inline_code_attributes',
                'intraword_underscores',
                'line_blocks',
                'lists_without_preceding_blankline',
                'lhs',
                'link_attributes',
                'mark',
                'markdown_attribute',
                'markdown_in_html_blocks',
                'mmd_title_block',
                'native_divs',
                'native_spans',
                'numbered_examples',
                'pandoc_title_block',
                'pipe_tables',
                'raw_attribute',
                'raw_html',
                'raw_markdown',
                'raw_tex',
                'latex_macros',
                'short_subsuperscripts',
                'shortcut_reference_links',
                'simple_tables',
                'smart',
                'space_in_atx_header',
                'spaced_reference_links',
                'startnum',
                'strikeout',
                'subscript',
                'superscript',
                'table_attributes',
                'task_lists',
                'tex_math_dollars',
                'tex_math_gfm',
                'grid_tables',
                'multiline_tables',
                'wikilinks',
                'wikilinks_title_after_pipe',
                'wikilinks_title_before_pipe',
                'yaml_metadata_block',
            ]
        );
        $extensions = array_values(array_unique($extensions));
        usort(
            $extensions,
            static fn(string $left, string $right): int => strlen($right) <=> strlen($left) ?: strcmp($left, $right)
        );

        return $extensions;
    }

    /**
     * @return array{base: ?string, suffix: string}
     */
    private static function markdownFormatParts(mixed $format): array
    {
        if (!is_scalar($format)) {
            return ['base' => null, 'suffix' => ''];
        }

        $normalized = strtolower(trim((string) $format));
        if ($normalized === '') {
            return ['base' => null, 'suffix' => ''];
        }

        foreach (self::PLUS_FORMAT_ALIASES as $alias => $canonical) {
            if ($normalized === $alias) {
                return ['base' => $canonical, 'suffix' => ''];
            }

            foreach (['+', '-'] as $delimiter) {
                $prefix = $alias . $delimiter;
                if (str_starts_with($normalized, $prefix)) {
                    return ['base' => $canonical, 'suffix' => substr($normalized, strlen($alias))];
                }
            }
        }

        foreach (self::FORMAT_CANDIDATES as $candidate) {
            if ($normalized === $candidate) {
                return ['base' => self::FORMAT_ALIASES[$candidate] ?? $candidate, 'suffix' => ''];
            }

            foreach (['+', '-'] as $delimiter) {
                $prefix = $candidate . $delimiter;
                if (str_starts_with($normalized, $prefix)) {
                    return [
                        'base' => self::FORMAT_ALIASES[$candidate] ?? $candidate,
                        'suffix' => substr($normalized, strlen($candidate)),
                    ];
                }
            }
        }

        return ['base' => null, 'suffix' => ''];
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function yamlMetadataEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        if (array_key_exists('yamlMetadata', $options)) {
            return self::boolFlag($options['yamlMetadata'], $defaultWithoutFormat);
        }

        if (!array_key_exists('format', $options)) {
            return $defaultWithoutFormat;
        }

        $format = $options['format'];
        $canonical = self::canonicalFormat($format);
        $default = self::isDeprecatedGithubMarkdownAlias($format)
            ? false
            : (self::DEFAULTS[$canonical]['yamlMetadata'] ?? $defaultWithoutFormat);

        return self::extensionFlag($format, 'yaml_metadata_block', $default);
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function titleBlockEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'titleBlock', $defaultWithoutFormat, 'pandoc_title_block');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function rawAttributeEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'rawAttribute', $defaultWithoutFormat, 'raw_attribute');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function rawHtmlEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'rawHtml', $defaultWithoutFormat, 'raw_html');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function rawTexEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'rawTex', $defaultWithoutFormat, 'raw_tex');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function latexMacrosEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'latexMacros', $defaultWithoutFormat, 'latex_macros');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function rawMarkdownEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'rawMarkdown', $defaultWithoutFormat, 'raw_markdown');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function definitionListsEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'definitionLists', $defaultWithoutFormat, 'definition_lists');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function footnotesEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'footnotes', $defaultWithoutFormat, 'footnotes');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function citationsEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'citations', $defaultWithoutFormat, 'citations');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function taskListsEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'taskLists', $defaultWithoutFormat, 'task_lists');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function pipeTablesEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'pipeTables', $defaultWithoutFormat, 'pipe_tables');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function simpleTablesEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'simpleTables', $defaultWithoutFormat, 'simple_tables');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function gridTablesEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'gridTables', $defaultWithoutFormat, 'grid_tables');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function multilineTablesEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'multilineTables', $defaultWithoutFormat, 'multiline_tables');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function tableAttributesEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'tableAttributes', $defaultWithoutFormat, 'table_attributes');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function listsWithoutPrecedingBlanklineEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'listsWithoutPrecedingBlankline', $defaultWithoutFormat, 'lists_without_preceding_blankline');
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function enabled(array $options, string $key, bool $defaultWithoutFormat, string $extension): bool
    {
        if (array_key_exists($key, $options)) {
            return self::boolFlag($options[$key], $defaultWithoutFormat);
        }

        if (!array_key_exists('format', $options)) {
            return $defaultWithoutFormat;
        }

        $format = $options['format'];
        $canonical = self::canonicalFormat($format);
        $default = self::DEFAULTS[$canonical][$key] ?? $defaultWithoutFormat;

        return self::extensionFlag($format, $extension, $default);
    }

    private static function isDeprecatedGithubMarkdownAlias(mixed $format): bool
    {
        if (!is_scalar($format)) {
            return false;
        }

        return preg_match('/^markdown[_-]github(?:[+-]|$)/', strtolower(trim((string) $format))) === 1;
    }

    private static function boolFlag(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    private static function extensionOptionEnabled(mixed $value): bool
    {
        return self::boolFlag($value, (bool) $value);
    }

    private static function extensionFlag(mixed $format, string $extension, bool $default): bool
    {
        if ($extension === 'raw_tex' && self::formatDisallowsRawTexExtension($format)) {
            return $default;
        }

        $overrides = self::markdownExtensionOverrides($format);
        if (array_key_exists($extension, $overrides)) {
            return $overrides[$extension];
        }

        return $default;
    }

    private static function formatDisallowsRawTexExtension(mixed $format): bool
    {
        return in_array(self::canonicalFormat($format), ['commonmark', 'commonmark_x', 'gfm'], true);
    }
}
