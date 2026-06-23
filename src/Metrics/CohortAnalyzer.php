<?php

declare(strict_types=1);

namespace Statisty\Metrics;

use Illuminate\Support\Carbon;
use Statisty\Support\ModelSchema;

final class CohortAnalyzer
{
    /**
     * Simple cohort analysis: groups records by period of $dateColumn and
     * computes counts for the cohort and subsequent periods up to $periods.
     * Returns ['labels' => [...], 'matrix' => [[counts...], ...]] where matrix rows correspond to cohorts.
     */
    public function analyze(string $modelClass, string $dateColumn = 'created_at', string $period = 'week', int $periods = 4, array $options = []): array
    {
        if (! ModelSchema::isQueryableModel($modelClass)) {
            throw new \InvalidArgumentException("Model {$modelClass} not found");
        }

        if (! ModelSchema::isVisibleColumn($modelClass, $dateColumn)) {
            throw new \InvalidArgumentException("Date column [{$dateColumn}] is not available");
        }

        $identityColumn = (string) ($options['identity_column'] ?? 'user_id');
        $activityDateColumn = (string) ($options['activity_date'] ?? $dateColumn);
        $zeroFill = ! empty($options['zero_fill']);
        $includeRetention = ! empty($options['retention_percent']);
        if (! ModelSchema::isVisibleColumn($modelClass, $identityColumn)) {
            return $this->analyzeCountsOnly($modelClass, $dateColumn, $period, $periods);
        }

        $all = $modelClass::query()->orderBy($activityDateColumn)->get()->map(function ($item) use ($activityDateColumn, $identityColumn) {
            return [
                'identity' => $item->{$identityColumn} ?? null,
                'date' => Carbon::parse($item->{$activityDateColumn}),
            ];
        })->filter(fn($row) => $row['identity'] !== null)->values();

        if ($all->isEmpty()) {
            return ['labels' => [], 'matrix' => [], 'sizes' => []];
        }

        $firstSeenByIdentity = [];
        foreach ($all as $row) {
            $key = (string) $row['identity'];
            if (! isset($firstSeenByIdentity[$key]) || $row['date']->lessThan($firstSeenByIdentity[$key])) {
                $firstSeenByIdentity[$key] = $row['date'];
            }
        }

        $cohorts = [];
        foreach ($firstSeenByIdentity as $identity => $firstSeen) {
            $cohorts[$this->bucketKey($firstSeen, $period)][] = $identity;
        }

        ksort($cohorts);

        $activityByIdentity = [];
        foreach ($all as $row) {
            $activityByIdentity[(string) $row['identity']][$this->bucketKey($row['date'], $period)] = true;
        }

        $labels = array_keys($cohorts);
        $matrix = [];
        $sizes = [];

        foreach ($labels as $label) {
            $members = $cohorts[$label];
            $sizes[$label] = count($members);
            $start = $this->periodStart($firstSeenByIdentity[$members[0]], $period);

            $rowCounts = [];
            for ($p = 0; $p < max(1, $periods); $p++) {
                $bucket = $this->bucketKey($this->periodStart($start, $period, $p), $period);
                $count = 0;

                foreach ($members as $identity) {
                    if (isset($activityByIdentity[$identity][$bucket])) {
                        $count++;
                    }
                }

                $rowCounts[] = $count;
            }

            if ($zeroFill) {
                // ensure row has exactly $periods entries
                while (count($rowCounts) < $periods) {
                    $rowCounts[] = 0;
                }
            }

            if ($includeRetention) {
                $rowCounts = array_map(function ($c, $idx) use ($sizes, $label) {
                    $size = $sizes[$label] ?? 0;
                    $pct = $size > 0 ? ($c / $size) * 100 : 0.0;
                    return ['count' => $c, 'retention' => $pct, 'period' => $idx];
                }, $rowCounts, array_keys($rowCounts));
            }

            $matrix[] = $rowCounts;
        }

        return ['labels' => $labels, 'matrix' => $matrix, 'sizes' => $sizes];
    }

    private function analyzeCountsOnly(string $modelClass, string $dateColumn, string $period, int $periods): array
    {
        $all = $modelClass::query()->orderBy($dateColumn)->get()->map(function ($item) use ($dateColumn) {
            return ['date' => Carbon::parse($item->{$dateColumn})];
        });

        if ($all->isEmpty()) {
            return ['labels' => [], 'matrix' => []];
        }

        // build cohort buckets
        $buckets = [];
        foreach ($all as $row) {
            $key = $this->bucketKey($row['date'], $period);
            $buckets[$key][] = $row['date'];
        }

        ksort($buckets);

        $labels = array_keys($buckets);
        $matrix = [];

        // For each cohort, compute counts in cohort and next N-1 periods
        foreach ($labels as $label) {
            $cohortStartDates = $buckets[$label];
            $rowCounts = [];
            $start = reset($cohortStartDates);

            for ($p = 0; $p < max(1, $periods); $p++) {
                $startPeriod = $this->periodStart($start, $period, $p);
                $endPeriod = $this->periodStart($start, $period, $p + 1);

                $count = $all->filter(fn($r) => $r['date']->greaterThanOrEqualTo($startPeriod) && $r['date']->lessThan($endPeriod))->count();
                $rowCounts[] = $count;
            }

            $matrix[] = $rowCounts;
        }

        return ['labels' => $labels, 'matrix' => $matrix];
    }

    private function bucketKey(Carbon $date, string $period): string
    {
        return match ($period) {
            'month' => $date->format('Y-m'),
            'week' => $date->format('o-\W') . ' W' . $date->weekOfYear,
            default => $date->format('Y-m-d'),
        };
    }

    private function periodStart(Carbon $date, string $period, int $offset = 0): Carbon
    {
        return match ($period) {
            'month' => $date->copy()->startOfMonth()->addMonths($offset),
            'week' => $date->copy()->startOfWeek()->addWeeks($offset),
            default => $date->copy()->startOfDay()->addDays($offset),
        };
    }
}
