<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/** Staged renderer for PPTX chart fallback HTML. */
final class WordPressPptxChartRenderer
{
    public function __construct(private readonly WordPressBlockWriter $writer)
    {
    }

    /** @param array<string, mixed> $chart */
    public function render(AstNode $node, array $chart): string
    {
        $title = (string) ($chart['title'] ?? '');
        if ($title === '') {
            $title = 'PPTX chart';
        }
        $type = (string) ($chart['chartType'] ?? 'unknown');
        $partName = (string) ($chart['partName'] ?? '');
        $placeholder = (string) $node->attr('text', '');
        $series = $this->normalizedSeries($chart);
        $categories = $this->categories($series);

        $html = '<figure class="pandoc-pptx-chart" data-pandoc-source="pptx-chart"'
            . ($partName === '' ? '' : ' data-pptx-chart-part="' . $this->esc($partName) . '"')
            . ' data-pptx-chart-type="' . $this->esc($type) . '">';
        $html .= '<figcaption><strong>' . $this->esc($title) . '</strong>';
        if ($placeholder !== '') {
            $html .= ' <span class="pandoc-pptx-chart-placeholder">' . $this->esc($placeholder) . '</span>';
        }
        $html .= '</figcaption>';

        $html .= '<table><thead><tr><th>Category</th>';
        foreach ($series as $item) {
            $html .= '<th>' . $this->esc($item['name']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        $rowCount = max(1, count($categories));
        for ($row = 0; $row < $rowCount; $row++) {
            $html .= '<tr><td>' . $this->esc((string) ($categories[$row] ?? 'Point ' . ($row + 1))) . '</td>';
            foreach ($series as $item) {
                $html .= '<td>' . $this->esc((string) ($item['rawValues'][$row] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= $this->renderBars($series);
        $html .= '</figure>';

        return $html;
    }

    /**
     * @param array<string, mixed> $chart
     * @return list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}>
     */
    private function normalizedSeries(array $chart): array
    {
        $normalized = [];
        foreach (array_values(is_array($chart['series'] ?? null) ? $chart['series'] : []) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $categories = array_values(array_map('strval', is_array($item['categories'] ?? null) ? $item['categories'] : []));
            $rawValues = array_values(array_map('strval', is_array($item['values'] ?? null) ? $item['values'] : []));
            $values = array_map(static fn (string $value): float => is_numeric($value) ? (float) $value : 0.0, $rawValues);
            $normalized[] = [
                'name' => (string) ($item['name'] ?? 'Series ' . ($index + 1)),
                'categories' => $categories,
                'rawValues' => $rawValues,
                'values' => $values,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}> $series
     * @return list<string>
     */
    private function categories(array $series): array
    {
        foreach ($series as $item) {
            if ($item['categories'] !== []) {
                return $item['categories'];
            }
        }

        return [];
    }

    /** @param list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}> $series */
    private function renderBars(array $series): string
    {
        $max = 0.0;
        foreach ($series as $item) {
            foreach ($item['values'] as $value) {
                $max = max($max, abs($value));
            }
        }
        if ($max <= 0.0) {
            return '';
        }

        $html = '<div class="pandoc-pptx-chart-bars">';
        foreach ($series as $item) {
            foreach ($item['values'] as $index => $value) {
                $label = $item['name'] . ' / ' . (string) ($item['categories'][$index] ?? 'Point ' . ($index + 1));
                $width = (int) round((abs($value) / $max) * 100);
                $html .= '<div class="pandoc-pptx-chart-bar" style="display:grid;grid-template-columns:minmax(8rem,1fr) 3fr 3rem;gap:.5rem;align-items:center;margin:.25rem 0">'
                    . '<span>' . $this->esc($label) . '</span>'
                    . '<span style="display:block;background:#eef1f5;height:.8rem"><span style="display:block;background:#2563eb;height:.8rem;width:' . $width . '%"></span></span>'
                    . '<span>' . $this->esc((string) ($item['rawValues'][$index] ?? $value)) . '</span>'
                    . '</div>';
            }
        }

        return $html . '</div>';
    }

    private function esc(string $value): string
    {
        return $this->writer->escape($value);
    }
}
