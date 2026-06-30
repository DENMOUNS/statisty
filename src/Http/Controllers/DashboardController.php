<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statisty\Core\StatistyManager;
use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class DashboardController extends BaseDashboardController
{
    public function index(Request $request, StatistyManager $statisty)
    {
        try {
            $models = $this->dashboardModels();

            if ($models === []) {
                return view('statisty::dashboard', [
                    'appName'      => config('app.name'),
                    'version'      => config('statisty.version', '1.0.0'),
                    'workspace'    => null,
                    'kpis'         => [],
                    'models'       => [],
                    ...$this->shellData('dashboard'),
                    'emptyMessage' => 'No Statisty models are configured yet.',
                ]);
            }

            $dashboard = $statisty
                ->workspace((string) config('statisty.workspace.default', 'default'))
                ->models($models)
                ->pagination((int) config('statisty.pagination.default', 25))
                ->withoutCache()
                ->build();

            $selectedHeatmapYear = (string) $request->query('year', 'recent');
            $heatmapYears        = $this->availableHeatmapYears($models);
            $heatmapData         = $this->getActivityHeatmapData($models, $selectedHeatmapYear);

            if ($selectedHeatmapYear === 'recent' && empty($heatmapData)) {
                foreach ($heatmapYears as $yearKey => $yearLabel) {
                    if (! ctype_digit($yearKey)) {
                        continue;
                    }

                    $candidateData = $this->getActivityHeatmapData($models, $yearKey);
                    if (! empty($candidateData)) {
                        $selectedHeatmapYear = $yearKey;
                        $heatmapData         = $candidateData;
                        break;
                    }
                }
            }

            return view('statisty::dashboard', [
                'appName'              => config('app.name'),
                'version'              => config('statisty.version', '1.0.0'),
                'workspace'            => $dashboard->workspace,
                'kpis'                 => $dashboard->kpis,
                'models'               => $this->modelCards($models),
                'heatmapData'          => $heatmapData,
                'heatmapYears'         => $heatmapYears,
                'selectedHeatmapYear'  => $selectedHeatmapYear,
                'heatmapCaption'       => $this->heatmapCaption($selectedHeatmapYear),
                ...$this->shellData('dashboard'),
                'emptyMessage'         => null,
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }

    private function modelCards(array $models): array
    {
        return array_map(function (string $model): array {
            $columns = array_slice(ModelSchema::visibleColumns($model), 0, 6);

            return [
                'class'        => $model,
                'label'        => ModelName::label($model),
                'columns'      => $columns,
                'rows'         => $this->recentRows($model, $columns),
                'count'        => $this->safeCount($model),
                'table_url'    => $this->apiUrl('tables', $model),
                'chart_url'    => $this->apiUrl('charts', $model),
                'metrics_url'  => $this->apiUrl('metrics', $model),
                'metrics_list' => $this->buildMetricsList($model),
            ];
        }, $models);
    }

    private function buildMetricsList(string $model): array
    {
        $metrics = ['Volume'];

        foreach (ModelSchema::semanticNumericColumns($model) as $col) {
            $metrics[] = ucwords(str_replace('_', ' ', $col));
        }

        $configRelations = (array) config('statisty.models.' . $model . '.relations', []);

        foreach ($configRelations as $relationName => $relConfig) {
            foreach ($this->metricsForRelation($model, $relationName, (array) ($relConfig['columns'] ?? [])) as $metric) {
                $metrics[] = $metric;
            }
        }

        return $metrics;
    }

    private function metricsForRelation(string $model, string $relationName, array $wantedCols): array
    {
        try {
            $instance = new $model();
            if (! method_exists($instance, $relationName)) {
                return [];
            }

            $relatedClass = get_class($instance->{$relationName}()->getRelated());
        } catch (\Throwable $e) {
            return [];
        }

        if (! class_exists($relatedClass)) {
            return [];
        }

        $availableCols = ModelSchema::visibleColumns($relatedClass);
        $cols = $wantedCols !== [] ? array_values(array_intersect($wantedCols, $availableCols)) : $availableCols;
        $metrics = [];

        foreach ($cols as $col) {
            if (
                in_array(strtolower($col), [
                'amount', 'total', 'price', 'quantity', 'value', 'points',
                'sum', 'count', 'score', 'total_amount', 'subtotal', 'revenue',
                'cost', 'fee', 'tax', 'discount', 'weight', 'balance',
                ], true)
            ) {
                $metrics[] = ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName))
                    . ' (' . ucwords(str_replace('_', ' ', $col)) . ')';
            }
        }

        return $metrics;
    }

    private function recentRows(string $model, array $columns): array
    {
        if ($columns === [] || ! method_exists($model, 'query')) {
            return [];
        }

        try {
            $query = $model::query()->select($columns)->limit(8);

            if (in_array('created_at', $columns, true)) {
                $query->latest('created_at');
            }

            return $query->get()
                ->map(fn (mixed $row): array => collect($row->toArray())
                    ->only($columns)
                    ->map(fn (mixed $value): mixed => is_scalar($value) || $value === null
                        ? $value
                        : json_encode($value))
                    ->all())
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function safeCount(string $model): int|string
    {
        try {
            return (int) $model::query()->count();
        } catch (\Throwable $e) {
            return 'Unavailable';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Heatmap
    // ─────────────────────────────────────────────────────────────────────────

    private function getActivityHeatmapData(array $models, string $selectedYear)
    {
        $data = [];
        $now  = Carbon::now()->endOfDay();
        $selectedYear = trim($selectedYear);

        if ($selectedYear === 'recent') {
            $startDate = Carbon::now()->subWeeks(25)->startOfWeek();
            $endDate   = $now;
        } elseif (ctype_digit($selectedYear)) {
            $year = (int) $selectedYear;
            if ($year === Carbon::now()->year) {
                $startDate = Carbon::now()->startOfYear()->startOfWeek();
                $endDate   = $now;
            } else {
                $startDate = Carbon::create($year, 1, 1)->startOfWeek();
                $endDate   = Carbon::create($year, 12, 31)->endOfWeek();
            }
        } else {
            $startDate = Carbon::now()->subWeeks(25)->startOfWeek();
            $endDate   = $now;
        }

        $query = $this->buildActivityHeatmapQuery($models, $startDate, $endDate);

        if ($query !== null) {
            try {
                foreach ($query->get() as $row) {
                    $date = $row->date;
                    $data[$date] = ($data[$date] ?? 0) + (int) $row->count;
                }
            } catch (\Throwable $e) {
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

    private function buildActivityHeatmapQuery(array $models, Carbon $startDate, Carbon $endDate)
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

                if ($union === null) {
                    $union = $builder;
                } else {
                    $union = $union->unionAll($builder);
                }
            } catch (\Throwable $e) {
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

    private function availableHeatmapYears(array $models): array
    {
        $years       = ['recent' => 'Dernières 26 semaines'];
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
            } catch (\Throwable $e) {
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

    private function heatmapCaption(string $selectedYear): string
    {
        if ($selectedYear === 'recent') {
            return 'Heatmap des créations d\'enregistrements sur les 26 dernières semaines';
        }

        if (ctype_digit($selectedYear) && (int) $selectedYear === Carbon::now()->year) {
            return 'Heatmap des créations d\'enregistrements depuis le début de l\'année ' . $selectedYear;
        }

        return 'Heatmap des créations d\'enregistrements sur l\'année ' . $selectedYear;
    }
}
