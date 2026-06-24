<?php

declare(strict_types=1);

namespace Statisty\Charts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Statisty\Support\ModelSchema;

final class ChartDataGenerator
{
    /**
     * Génère des données de chart depuis un modèle Eloquent.
     * Retourne ['labels' => [...], 'datasets' => [...]] où datasets sont des arrays Chart.js.
     *
     * Options supportées:
     * - from, to : date range (Y-m-d or any strtotime)
     * - period : day|week|month|year
     * - label : label du dataset
     * - max_categories_for_pie : int
     */
    public function generateFromModel(string $modelClass, ?string $valueField = null, string $dateColumn = 'created_at', array $options = []): array
    {
        if (! ModelSchema::isQueryableModel($modelClass)) {
            throw new \InvalidArgumentException("Model class [{$modelClass}] not found.");
        }

        if (! ModelSchema::isVisibleColumn($modelClass, $dateColumn)) {
            throw new \InvalidArgumentException("Date column [{$dateColumn}] is not available for charts.");
        }

        if ($valueField !== null && ! ModelSchema::isVisibleColumn($modelClass, $valueField)) {
            throw new \InvalidArgumentException("Value column [{$valueField}] is not available for charts.");
        }

        $period = $options['period'] ?? 'day';
        $allowed = ['day', 'week', 'month', 'year'];
        if (! in_array($period, $allowed, true)) {
            $period = 'day';
        }
        $from = $options['from'] ?? null;
        $to = $options['to'] ?? null;
        $label = $options['label'] ?? $this->shortClass($modelClass);
        $maxPie = (int) ($options['max_categories_for_pie'] ?? 12);

        $query = $modelClass::query();

        if ($from !== null) {
            $query->where($dateColumn, '>=', Carbon::parse($from));
        }

        if ($to !== null) {
            $query->where($dateColumn, '<=', Carbon::parse($to));
        }

        // If no value field -> count per period using SQL aggregation
        if ($valueField === null) {
            $rows = $this->aggregateCountByPeriod($query, $dateColumn, $period);
        } else {
            // detect numeric column using a quick sample (DB-level detection could be added)
            $numericSample = false;
            try {
                $sampleQuery = clone $query;
                $sample = $sampleQuery->limit(50)->pluck($valueField)->filter()->values();
                $numericSample = $sample->filter(fn($v) => is_numeric($v))->count() > 0;
            } catch (\Throwable) {
                // column may not exist or pluck failed - treat as non-numeric sample
                $numericSample = false;
            }

            // If percentile or histogram requested, we need raw values per period
            if (! empty($options['percentile']) || ! empty($options['histogram'])) {
                $rows = $query->get()->map(function ($item) use ($dateColumn, $valueField) {
                    return [
                        'date' => Carbon::parse($item->{$dateColumn}),
                        'value' => $item->{$valueField},
                    ];
                });
            } elseif ($numericSample) {
                $rows = $this->aggregateSumByPeriod($query, $dateColumn, $valueField, $period);
            } else {
                $rows = $query->get()->map(function ($item) use ($dateColumn, $valueField) {
                    return [
                        'date' => Carbon::parse($item->{$dateColumn}),
                        'value' => $item->{$valueField},
                    ];
                });
            }
        }

        if (($rows instanceof Collection && $rows->isEmpty()) || (is_array($rows) && count($rows) === 0)) {
            return ['labels' => [], 'datasets' => []];
        }

        // If valueField is null => we count per period
        if ($valueField === null) {
            // rows already aggregated: [bucket => count]
            $grouped = $rows instanceof Collection ? $rows->all() : (array) $rows;

            return $this->buildResponseFromGrouped($label, $grouped, $options);
        }

        // If valueField is present and aggregation returned an array (SQL aggregated)
        if (is_array($rows)) {
            $grouped = $rows;

            return $this->buildResponseFromGrouped($label, $grouped, $options);
        }

        // Otherwise $rows is a Collection of items with 'value'
        $numeric = $rows->pluck('value')->filter(fn($v) => is_numeric($v))->count() > 0;

        if ($numeric) {
            // if rows is a Collection of raw values, group and possibly compute percentile/histogram
            if ($rows instanceof Collection && (! empty($options['percentile']) || ! empty($options['histogram']))) {
                $perBucket = $this->groupValuesByPeriod($rows, $period);

                if (! empty($options['percentile'])) {
                    $percent = (float) $options['percentile'];
                    $grouped = array_map(fn($values) => $this->percentile($values, $percent), $perBucket);
                } else {
                    // histogram requested: compute histogram across all values
                    $bins = (int) ($options['histogram']['bins'] ?? 10);
                    if ($bins <= 0) { $bins = 10; }
                    $hist = $this->histogram($rows->pluck('value')->filter()->values()->all(), $bins);

                    $labels = array_keys($hist);
                    $data = array_values($hist);

                    $ds = new ChartDataSet($label, $data, 'bar', $this->visualOptions($options));
                    return ['labels' => $labels, 'datasets' => [$ds->toChartJs()]];
                }

                $labels = array_keys($grouped);
                $data = array_values($grouped);

                $data = $this->maybeTransform($data, $options);

                $ds = new ChartDataSet($label, $data, 'line', $this->visualOptions($options));
                return ['labels' => $labels, 'datasets' => [$ds->toChartJs()]];
            }

            $grouped = $this->groupByPeriodSum($rows, $period);

            return $this->buildResponseFromGrouped($label, $grouped, $options);
        }

        // Otherwise treat as categories -> pie or bar of categories
        $categories = $rows->pluck('value')->filter()->values()->all();
        $counts = array_count_values($categories);

        // If few categories and small, return pie
        if (count($counts) <= $maxPie) {
            $labels = array_keys($counts);
            $data = array_values($counts);

            $ds = new ChartDataSet($label, $data, 'pie', $this->visualOptions($options));

            return ['labels' => $labels, 'datasets' => [$ds->toChartJs()]];
        }

        // Fallback: return top categories as bar
        arsort($counts);
        $top = array_slice($counts, 0, $maxPie, true);

        $labels = array_keys($top);
        $data = array_values($top);

        $ds = new ChartDataSet($label, $data, 'bar', $this->visualOptions($options));

        return ['labels' => $labels, 'datasets' => [$ds->toChartJs()]];
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

    /**
     * Aggregate counts per period using SQL group by when possible.
     * Returns array [bucket => count]
     */
    private function aggregateCountByPeriod(EloquentBuilder $query, string $dateColumn, string $period): array
    {
        $driver = $query->getConnection()->getDriverName();

        if (str_contains($driver, 'mysql') || str_contains($driver, 'pdo_mysql')) {
            $format = match ($period) {
                'month' => '%Y-%m',
                'week' => '%x-W%v',
                'year' => '%Y',
                default => '%Y-%m-%d',
            };

            $wrappedDateColumn = $query->getQuery()->getGrammar()->wrap($dateColumn);
            $rows = $query->selectRaw("DATE_FORMAT({$wrappedDateColumn}, '{$format}') as bucket, COUNT(*) as cnt")->groupBy('bucket')->orderBy('bucket')->get();

            return $rows->pluck('cnt', 'bucket')->all();
        }

        // Fallback to PHP grouping
        $rows = $query->get()->map(function ($item) use ($dateColumn) {
            return [ 'date' => Carbon::parse($item->{$dateColumn}) ];
        });

        return $this->groupByPeriod($rows, $period);
    }

    /**
     * Aggregate sum of valueField per period using SQL when possible.
     * Returns array [bucket => sum]
     */
    private function aggregateSumByPeriod(EloquentBuilder $query, string $dateColumn, string $valueField, string $period): array
    {
        $driver = $query->getConnection()->getDriverName();

        if (str_contains($driver, 'mysql') || str_contains($driver, 'pdo_mysql')) {
            $format = match ($period) {
                'month' => '%Y-%m',
                'week' => '%x-W%v',
                'year' => '%Y',
                default => '%Y-%m-%d',
            };

            $grammar = $query->getQuery()->getGrammar();
            $wrappedDateColumn = $grammar->wrap($dateColumn);
            $wrappedValueField = $grammar->wrap($valueField);
            $rows = $query->selectRaw("DATE_FORMAT({$wrappedDateColumn}, '{$format}') as bucket, SUM({$wrappedValueField}) as s")->groupBy('bucket')->orderBy('bucket')->get();

            return $rows->pluck('s', 'bucket')->all();
        }

        $rows = $query->get()->map(function ($item) use ($dateColumn, $valueField) {
            return [
                'date' => Carbon::parse($item->{$dateColumn}),
                'value' => $item->{$valueField},
            ];
        });

        return $this->groupByPeriodSum($rows, $period);
    }

    private function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts) ?: $fqcn;
    }

    /**
     * Group rows by period and count items per bucket.
     * Returns [bucketLabel => count]
     */
    private function groupByPeriod($rows, string $period): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $key = $this->bucketKey($row['date'], $period);
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        ksort($buckets);

        return $buckets;
    }

    /**
     * Group rows by period and sum 'value' per bucket.
     * Returns [bucketLabel => sum]
     */
    private function groupByPeriodSum($rows, string $period): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $key = $this->bucketKey($row['date'], $period);
            $val = is_numeric($row['value']) ? $row['value'] + 0 : 0;
            $buckets[$key] = ($buckets[$key] ?? 0) + $val;
        }

        ksort($buckets);

        return $buckets;
    }

    private function bucketKey(Carbon $date, string $period): string
    {
        return match ($period) {
            'week' => $date->format('o') . ' W' . $date->weekOfYear,
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }

    /** Apply optional transforms to a numeric data series */
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
        foreach ($data as $v) {
            $sum += is_numeric($v) ? $v + 0 : 0;
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

    private function groupValuesByPeriod(Collection $rows, string $period): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $key = $this->bucketKey($row['date'], $period);
            $buckets[$key][] = is_numeric($row['value']) ? $row['value'] + 0 : null;
        }

        // ensure keys sorted
        ksort($buckets);

        // remove nulls
        return array_map(fn($arr) => array_values(array_filter($arr, fn($v) => $v !== null)), $buckets);
    }

    private function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $k = ($percentile / 100) * (count($values) - 1);
        $f = floor($k);
        $c = ceil($k);
        if ($f == $c) {
            return $values[(int)$k];
        }

        $d0 = $values[$f] * ($c - $k);
        $d1 = $values[$c] * ($k - $f);

        return $d0 + $d1;
    }

    private function histogram(array $values, int $bins): array
    {
        if (empty($values)) {
            return [];
        }

        $min = min($values);
        $max = max($values);
        if ($min == $max) {
            return ["{$min}" => count($values)];
        }

        $width = ($max - $min) / $bins;
        $buckets = array_fill(0, $bins, 0);

        foreach ($values as $v) {
            $idx = (int) floor(($v - $min) / $width);
            if ($idx >= $bins) { $idx = $bins - 1; }
            if ($idx < 0) { $idx = 0; }
            $buckets[$idx]++;
        }

        $labels = [];
        $out = [];
        for ($i = 0; $i < $bins; $i++) {
            $low = $min + $i * $width;
            $high = $min + ($i + 1) * $width;
            $labels[] = sprintf('%.2f-%.2f', $low, $high);
            $out[$labels[$i]] = $buckets[$i];
        }

        return $out;
    }

    private function detectChartTypeFromNumeric(bool $numeric): string
    {
        return $numeric ? 'line' : 'bar';
    }

    private function buildResponseFromGrouped(string $label, array $grouped, array $options): array
    {
        $labels = array_keys($grouped);
        $data = array_values($grouped);

        $data = $this->maybeTransform($data, $options);

        $ds = new ChartDataSet($label, $data, $this->detectChartTypeFromNumeric(true), $this->visualOptions($options));

        return ['labels' => $labels, 'datasets' => [$ds->toChartJs()]];
    }
}
