<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statisty\Core\StatistyManager;
use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class DashboardController extends Controller
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
                'emptyMessage' => null,
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }

    private function dashboardModels(): array
    {
        $configured = (array) config('statisty.models', []);
        $models = [];

        foreach ($configured as $model => $options) {
            if (! is_string($model) || $model === '') {
                continue;
            }

            if (is_array($options) && ($options['enabled'] ?? true) === false) {
                continue;
            }

            if (ModelSchema::isQueryableModel($model)) {
                $models[] = ltrim($model, '\\');
            }
        }

        return array_values(array_unique($models));
    }

    private function modelCards(array $models): array
    {
        return array_map(function (string $model): array {
            $columns = array_slice(ModelSchema::visibleColumns($model), 0, 6);
            $rows = $this->recentRows($model, $columns);

            return [
                'class' => $model,
                'label' => ModelName::label($model),
                'columns' => $columns,
                'rows' => $rows,
                'count' => $this->safeCount($model),
                'table_url' => $this->apiUrl('tables', $model),
                'metrics_url' => $this->apiUrl('metrics', $model, ['type' => 'count']),
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
        } catch (\Throwable) {
            return [];
        }
    }

    private function safeCount(string $model): int|string
    {
        try {
            return (int) $model::query()->count();
        } catch (\Throwable) {
            return 'Unavailable';
        }
    }

    private function apiUrl(string $endpoint, string $model, array $query = []): string
    {
        $prefix = trim((string) config('statisty.routes.api.prefix', 'api/statisty'), '/');
        $url = url($prefix . '/' . trim($endpoint, '/') . '/' . str_replace('\\', '%5C', $model));

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query);
    }
}
