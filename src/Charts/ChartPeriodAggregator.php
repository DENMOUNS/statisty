<?php

declare(strict_types=1);

namespace Statisty\Charts;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Carbon;

final class ChartPeriodAggregator
{
    public function aggregateCountByPeriod(EloquentBuilder $query, string $dateColumn, string $period): array
    {
        $connection = $query->getConnection();
        if (! $connection instanceof Connection) {
            return $this->aggregateCountByPeriodInPhp($query, $dateColumn, $period);
        }

        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $wrappedDateColumn = $query->getQuery()->getGrammar()->wrap($dateColumn);
            $bucketExpression = $period === 'week'
                ? "DATE_SUB(DATE({$wrappedDateColumn}), INTERVAL WEEKDAY({$wrappedDateColumn}) DAY)"
                : "DATE_FORMAT({$wrappedDateColumn}, '" . ($period === 'month' ? '%Y-%m' : ($period === 'year' ? '%Y' : '%Y-%m-%d')) . "')";

            $rows = $query->selectRaw("{$bucketExpression} as bucket, COUNT(*) as cnt")
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            return $rows->pluck('cnt', 'bucket')->map(fn ($v) => (int) $v)->all();
        }

        if ($driver === 'pgsql') {
            $grammar = $query->getQuery()->getGrammar();
            $wrappedDateColumn = $grammar->wrap($dateColumn);
            $bucketExpression = $period === 'week'
                ? "to_char(date_trunc('week', {$wrappedDateColumn}), 'YYYY-MM-DD')"
                : "to_char({$wrappedDateColumn}, '" . ($period === 'month' ? 'YYYY-MM' : ($period === 'year' ? 'YYYY' : 'YYYY-MM-DD')) . "')";

            $rows = $query->selectRaw("{$bucketExpression} as bucket, COUNT(*) as cnt")
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            return $rows->pluck('cnt', 'bucket')->map(fn ($v) => (int) $v)->all();
        }

        if ($driver === 'sqlite') {
            $grammar = $query->getQuery()->getGrammar();
            $wrappedDateColumn = $grammar->wrap($dateColumn);
            $bucketExpression = $period === 'week'
                ? "date({$wrappedDateColumn}, '-' || ((strftime('%w', {$wrappedDateColumn}) + 6) % 7) || ' days')"
                : "strftime('" . ($period === 'month' ? '%Y-%m' : ($period === 'year' ? '%Y' : '%Y-%m-%d')) . "', {$wrappedDateColumn})";

            $rows = $query->selectRaw("{$bucketExpression} as bucket, COUNT(*) as cnt")
                ->groupByRaw($bucketExpression)
                ->orderBy('bucket')
                ->get();

            return $rows->pluck('cnt', 'bucket')->map(fn ($v) => (int) $v)->all();
        }

        return $this->aggregateCountByPeriodInPhp($query, $dateColumn, $period);
    }

    public function aggregateSumByPeriod(EloquentBuilder $query, string $dateColumn, string $valueField, string $period): array
    {
        $connection = $query->getConnection();
        if (! $connection instanceof Connection) {
            return $this->aggregateSumByPeriodInPhp($query, $dateColumn, $valueField, $period);
        }

        $driver = $connection->getDriverName();
        $grammar = $query->getQuery()->getGrammar();
        $wrappedDateColumn = $grammar->wrap($dateColumn);
        $wrappedValueField = $grammar->wrap($valueField);

        if ($driver === 'mysql') {
            $bucketExpression = $period === 'week'
                ? "DATE_SUB(DATE({$wrappedDateColumn}), INTERVAL WEEKDAY({$wrappedDateColumn}) DAY)"
                : "DATE_FORMAT({$wrappedDateColumn}, '" . ($period === 'month' ? '%Y-%m' : ($period === 'year' ? '%Y' : '%Y-%m-%d')) . "')";

            $rows = $query->selectRaw("{$bucketExpression} as bucket, SUM({$wrappedValueField}) as s")
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            return $rows->pluck('s', 'bucket')->map(fn ($v) => (float) $v)->all();
        }

        if ($driver === 'pgsql') {
            $bucketExpression = $period === 'week'
                ? "to_char(date_trunc('week', {$wrappedDateColumn}), 'YYYY-MM-DD')"
                : "to_char({$wrappedDateColumn}, '" . ($period === 'month' ? 'YYYY-MM' : ($period === 'year' ? 'YYYY' : 'YYYY-MM-DD')) . "')";

            $rows = $query->selectRaw("{$bucketExpression} as bucket, SUM({$wrappedValueField}) as s")
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            return $rows->pluck('s', 'bucket')->map(fn ($v) => (float) $v)->all();
        }

        if ($driver === 'sqlite') {
            $bucketExpression = $period === 'week'
                ? "date({$wrappedDateColumn}, '-' || ((strftime('%w', {$wrappedDateColumn}) + 6) % 7) || ' days')"
                : "strftime('" . ($period === 'month' ? '%Y-%m' : ($period === 'year' ? '%Y' : '%Y-%m-%d')) . "', {$wrappedDateColumn})";

            $rows = $query->selectRaw("{$bucketExpression} as bucket, SUM({$wrappedValueField}) as s")
                ->groupByRaw($bucketExpression)
                ->orderBy('bucket')
                ->get();

            return $rows->pluck('s', 'bucket')->map(fn ($v) => (float) $v)->all();
        }

        return $this->aggregateSumByPeriodInPhp($query, $dateColumn, $valueField, $period);
    }

    public function aggregateCountByPeriodInPhp(EloquentBuilder $query, string $dateColumn, string $period): array
    {
        $dateKey = str_contains($dateColumn, '.') ? last(explode('.', $dateColumn)) : $dateColumn;
        $buckets = [];

        $query->chunk(1000, function ($items) use (&$buckets, $dateKey, $period): void {
            foreach ($items as $item) {
                $key = $this->bucketKey(Carbon::parse($item->{$dateKey}), $period);
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }
        });

        ksort($buckets);

        return $buckets;
    }

    public function aggregateSumByPeriodInPhp(EloquentBuilder $query, string $dateColumn, string $valueField, string $period): array
    {
        $dateKey = str_contains($dateColumn, '.') ? last(explode('.', $dateColumn)) : $dateColumn;
        $valueKey = str_contains($valueField, '.') ? last(explode('.', $valueField)) : $valueField;
        $buckets = [];

        $query->chunk(1000, function ($items) use (&$buckets, $dateKey, $valueKey, $period): void {
            foreach ($items as $item) {
                $key = $this->bucketKey(Carbon::parse($item->{$dateKey}), $period);
                $val = is_numeric($item->{$valueKey}) ? $item->{$valueKey} + 0 : 0;
                $buckets[$key] = ($buckets[$key] ?? 0) + $val;
            }
        });

        ksort($buckets);

        return $buckets;
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
