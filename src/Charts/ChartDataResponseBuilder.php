<?php

declare(strict_types=1);

namespace Statisty\Charts;

use Illuminate\Support\Collection;

final class ChartDataResponseBuilder
{
    public function build(
        mixed $rows,
        ?string $valueField,
        string $label,
        array $options,
        string $period,
        ChartDataProcessor $processor,
        int $maxPie = 12,
    ): array {
        if (($rows instanceof Collection && $rows->isEmpty()) || (is_array($rows) && count($rows) === 0)) {
            return ['labels' => [], 'datasets' => []];
        }

        if ($valueField === null || is_array($rows)) {
            $grouped = is_array($rows) ? $rows : $rows;

            return $this->buildFromGrouped($label, $grouped, $options);
        }

        $numeric = $rows->pluck('value')->filter(fn ($v) => is_numeric($v))->count() > 0;

        if ($numeric) {
            if ($rows instanceof Collection && (! empty($options['percentile']) || ! empty($options['histogram']))) {
                $perBucket = $processor->groupValuesByPeriod($rows, $period);

                if (! empty($options['percentile'])) {
                    $percent = (float) $options['percentile'];
                    $grouped = array_map(fn ($values) => $processor->percentile($values, $percent), $perBucket);
                } else {
                    $bins = (int) ($options['histogram']['bins'] ?? 10);
                    if ($bins <= 0) {
                        $bins = 10;
                    }

                    $hist = $processor->histogram($rows->pluck('value')->filter()->values()->all(), $bins);
                    $labels = array_keys($hist);
                    $data = array_values($hist);

                    return $this->buildChart($label, $data, $options, 'bar', $labels);
                }

                $labels = array_keys($grouped);
                $data = array_values($grouped);
                $data = $this->maybeTransform($data, $options);

                return $this->buildChart($label, $data, $options, 'line', $labels);
            }

            $grouped = $processor->groupByPeriodSum($rows, $period);

            return $this->buildFromGrouped($label, $grouped, $options);
        }

        $categories = $rows->pluck('value')->filter()->values()->all();
        $counts = array_count_values($categories);

        if (count($counts) <= $maxPie) {
            $labels = array_keys($counts);
            $data = array_values($counts);

            return $this->buildChart($label, $data, $options, 'pie', $labels);
        }

        arsort($counts);
        $top = array_slice($counts, 0, $maxPie, true);

        $labels = array_keys($top);
        $data = array_values($top);

        return $this->buildChart($label, $data, $options, 'bar', $labels);
    }

    private function buildFromGrouped(string $label, array $grouped, array $options): array
    {
        $labels = array_keys($grouped);
        $data = array_values($grouped);
        $data = $this->maybeTransform($data, $options);

        return $this->buildChart($label, $data, $options, 'line', $labels);
    }

    private function buildChart(string $label, array $data, array $options, string $type, array $labels): array
    {
        $ds = new ChartDataSet($label, $data, $type, $this->visualOptions($options));

        return [
            'labels' => $labels,
            'datasets' => [$ds->toChartJs()],
        ];
    }

    private function maybeTransform(array $data, array $options): array
    {
        if (! empty($options['cumulative'])) {
            $data = $this->cumulative($data);
        }

        if (! empty($options['transform']) && $options['transform'] === 'moving_average') {
            $window = (int) ($options['window'] ?? 3);
            $data = $this->movingAverage($data, $window);
        }

        return $data;
    }

    private function cumulative(array $data): array
    {
        $out = [];
        $sum = 0;

        foreach ($data as $value) {
            $sum += is_numeric($value) ? $value + 0 : 0;
            $out[] = $sum;
        }

        return $out;
    }

    private function movingAverage(array $data, int $window): array
    {
        if ($window <= 1) {
            return $data;
        }

        $out = [];
        $len = count($data);

        for ($i = 0; $i < $len; $i++) {
            $start = max(0, $i - $window + 1);
            $slice = array_slice($data, $start, $i - $start + 1);
            $out[] = array_sum($slice) / max(1, count($slice));
        }

        return $out;
    }

    private function visualOptions(array $options): array
    {
        $meta = [];

        if (! empty($options['color'])) {
            $meta['backgroundColor'] = $options['color'];
            $meta['borderColor'] = $options['color'];
        }

        if (! empty($options['borderWidth'])) {
            $meta['borderWidth'] = (int) $options['borderWidth'];
        }

        return $meta;
    }
}
