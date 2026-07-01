<?php

declare(strict_types=1);

namespace Statisty\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class DashboardHeatmapService
{
    public function prepareHeatmap(array $models, string $selectedYear): array
    {
        $years = $this->availableHeatmapYears($models);
        $selectedYear = trim($selectedYear);
        $data = $this->getActivityHeatmapData($models, $selectedYear);

        if ($selectedYear === 'recent' && empty($data)) {
            foreach ($years as $yearKey => $yearLabel) {
                if (! ctype_digit($yearKey)) {
                    continue;
                }

                $candidateData = $this->getActivityHeatmapData($models, $yearKey);
                if (! empty($candidateData)) {
                    $selectedYear = $yearKey;
                    $data = $candidateData;
                    break;
                }
            }
        }

        return [
            'selectedYear' => $selectedYear,
            'data' => $data,
            'years' => $years,
        ];
    }

    public function getActivityHeatmapData(array $models, string $selectedYear): array
    {
        $data = [];
        $now = Carbon::now()->endOfDay();
        $selectedYear = trim($selectedYear);

        if ($selectedYear === 'recent') {
            $startDate = Carbon::now()->subWeeks(25)->startOfWeek();
            $endDate = $now;
        } elseif (ctype_digit($selectedYear)) {
            $year = (int) $selectedYear;
            if ($year === Carbon::now()->year) {
                $startDate = Carbon::now()->startOfYear()->startOfWeek();
                $endDate = $now;
            } else {
                $startDate = Carbon::create($year, 1, 1)->startOfWeek();
                $endDate = Carbon::create($year, 12, 31)->endOfWeek();
            }
        } else {
            $startDate = Carbon::now()->subWeeks(25)->startOfWeek();
            $endDate = $now;
        }

        $query = $this->buildActivityHeatmapQuery($models, $startDate, $endDate);
        if ($query !== null) {
            try {
                foreach ($query->get() as $row) {
                    $date = $row->date;
                    $data[$date] = ($data[$date] ?? 0) + (int) $row->count;
                }
            } catch (\Throwable) {
            }
        }

        $heatmapSeries = [];
        $current = $startDate->copy();
        $weekIndex = 0;

        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            $count = $data[$dateStr] ?? 0;
            $y = $current->dayOfWeekIso - 1;

            $heatmapSeries[] = [$weekIndex, $y, $count, $dateStr];
            if ($y === 6) {
                $weekIndex++;
            }

            $current->addDay();
        }

        return $heatmapSeries;
    }

    public function availableHeatmapYears(array $models): array
    {
        $years = ['recent' => 'Dernières 26 semaines'];
        $earliestYear = null;

        foreach ($models as $model) {
            if (! method_exists($model, 'query')) {
                continue;
            }

            try {
                $firstDate = $model::query()
                    ->whereNotNull('created_at')
                    ->orderBy('created_at', 'asc')
                    ->value('created_at');

                if ($firstDate) {
                    $year = Carbon::parse($firstDate)->year;
                    if ($earliestYear === null || $year < $earliestYear) {
                        $earliestYear = $year;
                    }
                }
            } catch (\Throwable) {
            }
        }

        $currentYear = Carbon::now()->year;
        if ($earliestYear === null || $earliestYear > $currentYear) {
            $earliestYear = $currentYear;
        }

        for ($year = $currentYear; $year >= $earliestYear; $year--) {
            $years[(string) $year] = 'Année ' . $year;
        }

        return $years;
    }

    public function heatmapCaption(string $selectedYear): string
    {
        if ($selectedYear === 'recent') {
            return 'Heatmap des créations d\'enregistrements sur les 26 dernières semaines';
        }

        if (ctype_digit($selectedYear) && (int) $selectedYear === Carbon::now()->year) {
            return 'Heatmap des créations d\'enregistrements depuis le début de l\'année ' . $selectedYear;
        }

        return 'Heatmap des créations d\'enregistrements sur l\'année ' . $selectedYear;
    }

    private function buildActivityHeatmapQuery(array $models, Carbon $startDate, Carbon $endDate): ?QueryBuilder
    {
        $union = null;

        foreach ($models as $model) {
            if (! method_exists($model, 'query')) {
                continue;
            }

            try {
                $instance = new $model();
                if (! Schema::hasColumn($instance->getTable(), 'created_at')) {
                    continue;
                }

                $builder = $model::query()
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->groupBy('date')
                    ->toBase();

                $union = $union === null ? $builder : $union->unionAll($builder);
            } catch (\Throwable) {
            }
        }

        if ($union === null) {
            return null;
        }

        return DB::query()
            ->fromSub($union, 'activity_heatmap')
            ->selectRaw('date, SUM(count) as count')
            ->groupBy('date')
            ->orderBy('date');
    }
}
