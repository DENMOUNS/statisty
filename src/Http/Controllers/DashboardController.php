<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
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
                    'appName' => config('app.name'),
                    'version' => config('statisty.version', '1.0.0'),
                    'workspace' => null,
                    'kpis' => [],
                    'models' => [],
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

            return view('statisty::dashboard', [
                'appName' => config('app.name'),
                'version' => config('statisty.version', '1.0.0'),
                'workspace' => $dashboard->workspace,
                'kpis' => $dashboard->kpis,
                'models' => $this->modelCards($models),
                ...$this->shellData('dashboard'),
                'emptyMessage' => null,
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
            $rows = $this->recentRows($model, $columns);

            // Compiler la liste des métriques détectées (locales + relations)
            $numericCols = ModelSchema::semanticNumericColumns($model);
            $metricsList = ['Volume'];
            foreach ($numericCols as $col) {
                $metricsList[] = ucwords(str_replace('_', ' ', $col));
            }

            $configRelations = (array) config('statisty.models.' . $model . '.relations', []);
            foreach ($configRelations as $relationName => $relConfig) {
                $relatedClass = null;
                try {
                    $instance = new $model();
                    if (method_exists($instance, $relationName)) {
                        $relatedClass = get_class($instance->{$relationName}()->getRelated());
                    }
                } catch (\Throwable $e) {}

                if ($relatedClass && class_exists($relatedClass)) {
                    $wantedCols = (array) ($relConfig['columns'] ?? []);
                    $availableCols = ModelSchema::visibleColumns($relatedClass);
                    $cols = $wantedCols !== []
                        ? array_values(array_intersect($wantedCols, $availableCols))
                        : $availableCols;

                    foreach ($cols as $col) {
                        if (in_array(strtolower($col), ['amount', 'total', 'price', 'quantity', 'value', 'points', 'sum', 'count', 'score', 'total_amount', 'subtotal', 'revenue', 'cost', 'fee', 'tax', 'discount', 'weight', 'balance'])) {
                            $metricsList[] = ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName)) . ' (' . ucwords(str_replace('_', ' ', $col)) . ')';
                        }
                    }
                }
            }

            return [
                'class' => $model,
                'label' => ModelName::label($model),
                'columns' => $columns,
                'rows' => $rows,
                'count' => $this->safeCount($model),
                'table_url' => $this->apiUrl('tables', $model),
                'metrics_url' => $this->apiUrl('metrics', $model, ['type' => 'count']),
                'chart_url' => $this->apiUrl('charts', $model),
                'metrics_list' => $metricsList,
            ];
        }, $models);
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
                    ->map(fn (mixed $value): mixed => is_scalar($value) || $value === null ? $value : json_encode($value))
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
}
