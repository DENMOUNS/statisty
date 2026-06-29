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

        $firstSeenByIdentity = [];
        $activityByIdentity = [];

        $query = $modelClass::query()
            ->select([$identityColumn, $activityDateColumn])
            ->orderBy($activityDateColumn);

        foreach ($query->cursor() as $item) {
            $identity = $item->{$identityColumn} ?? null;
            if ($identity === null) {
                continue;
            }

            $date = Carbon::parse($item->{$activityDateColumn});
            $key = (string) $identity;

            if (! isset($firstSeenByIdentity[$key]) || $date->lessThan($firstSeenByIdentity[$key])) {
                $firstSeenByIdentity[$key] = $date;
            }

            $activityByIdentity[$key][$this->bucketKey($date, $period)] = true;
        }

        if ($firstSeenByIdentity === []) {
            return ['labels' => [], 'matrix' => [], 'sizes' => []];
        }

        $cohorts = [];
        foreach ($firstSeenByIdentity as $identity => $firstSeen) {
            $cohorts[$this->bucketKey($firstSeen, $period)][] = $identity;
        }

        ksort($cohorts);

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
        $buckets = [];
        $starts = [];

        $query = $modelClass::query()
            ->select([$dateColumn])
            ->orderBy($dateColumn);

        foreach ($query->cursor() as $item) {
            $date = Carbon::parse($item->{$dateColumn});
            $key = $this->bucketKey($date, $period);
            $buckets[$key][] = $date;
            if (! isset($starts[$key])) {
                $starts[$key] = $date;
            }
        }

        if ($buckets === []) {
            return ['labels' => [], 'matrix' => []];
        }

        ksort($buckets);

        $labels = array_keys($buckets);
        $matrix = [];

        // For each cohort, compute counts in cohort and next N-1 periods
        foreach ($labels as $label) {
            $cohortDates = $buckets[$label];
            $rowCounts = [];
            $start = reset($cohortDates);

            for ($p = 0; $p < max(1, $periods); $p++) {
                $startPeriod = $this->periodStart($start, $period, $p);
                $endPeriod = $this->periodStart($start, $period, $p + 1);

                $count = 0;
                foreach ($cohortDates as $date) {
                    if ($date->greaterThanOrEqualTo($startPeriod) && $date->lessThan($endPeriod)) {
                        $count++;
                    }
                }

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
