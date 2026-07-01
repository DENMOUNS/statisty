<?php

declare(strict_types=1);

namespace Statisty\Charts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ChartDataTransformer
{
    public function groupValuesByPeriod(Collection $rows, string $period): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $key = $this->bucketKey($row['date'], $period);
            $buckets[$key][] = is_numeric($row['value']) ? $row['value'] + 0 : null;
        }

        ksort($buckets);

        return array_map(fn ($arr) => array_values(array_filter($arr, fn ($v) => $v !== null)), $buckets);
    }

    public function groupByPeriodSum($rows, string $period): array
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

    public function groupByPeriod($rows, string $period): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $key = $this->bucketKey($row['date'], $period);
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        ksort($buckets);

        return $buckets;
    }

    public function cumulative(array $data): array
    {
        $out = [];
        $sum = 0;

        foreach ($data as $value) {
            $sum += is_numeric($value) ? $value + 0 : 0;
            $out[] = $sum;
        }

        return $out;
    }

    public function movingAverage(array $data, int $window): array
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

    public function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $k = ($percentile / 100) * (count($values) - 1);
        $f = floor($k);
        $c = ceil($k);

        if ($f === $c) {
            return $values[(int) $k];
        }

        $d0 = $values[(int) $f] * ($c - $k);
        $d1 = $values[(int) $c] * ($k - $f);

        return $d0 + $d1;
    }

    public function histogram(array $values, int $bins): array
    {
        if (empty($values)) {
            return [];
        }

        $min = min($values);
        $max = max($values);

        if ($min === $max) {
            return ["{$min}" => count($values)];
        }

        $width = ($max - $min) / $bins;
        $buckets = array_fill(0, $bins, 0);

        foreach ($values as $value) {
            $idx = (int) floor(($value - $min) / $width);
            if ($idx >= $bins) {
                $idx = $bins - 1;
            }
            if ($idx < 0) {
                $idx = 0;
            }
            $buckets[$idx]++;
        }

        $out = [];
        for ($i = 0; $i < $bins; $i++) {
            $low = $min + $i * $width;
            $high = $min + ($i + 1) * $width;
            $out[sprintf('%.2f-%.2f', $low, $high)] = $buckets[$i];
        }

        return $out;
    }

    private function bucketKey(Carbon $date, string $period): string
    {
        return match ($period) {
            'week' => $date->copy()->startOfWeek()->format('Y-m-d'),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }
}
