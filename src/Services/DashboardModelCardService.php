<?php

declare(strict_types=1);

namespace Statisty\Services;

use Statisty\Support\DisplayRowFetcher;
use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class DashboardModelCardService
{
    public function buildModelCards(array $models): array
    {
        return array_map([$this, 'buildModelCard'], $models);
    }

    private function buildModelCard(string $model): array
    {
        $columns = array_slice(ModelSchema::displayColumns($model), 0, 6);

        return [
            'class' => $model,
            'label' => ModelName::label($model),
            'columns' => $columns,
            'rows' => $this->recentRows($model, $columns),
            'count' => $this->safeCount($model),
            'table_url' => $this->apiUrl('tables', $model),
            'chart_url' => $this->apiUrl('charts', $model),
            'metrics_url' => $this->apiUrl('metrics', $model),
            'metrics_list' => $this->buildMetricsList($model),
        ];
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
        } catch (\Throwable) {
            return [];
        }

        if (! class_exists($relatedClass)) {
            return [];
        }

        $availableCols = ModelSchema::visibleColumns($relatedClass);
        $cols = $wantedCols !== [] ? array_values(array_intersect($wantedCols, $availableCols)) : $availableCols;
        $metrics = [];

        foreach ($cols as $col) {
            if (in_array(strtolower($col), [
                'amount', 'total', 'price', 'quantity', 'value', 'points',
                'sum', 'count', 'score', 'total_amount', 'subtotal', 'revenue',
                'cost', 'fee', 'tax', 'discount', 'weight', 'balance',
            ], true)) {
                $metrics[] = ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName))
                    . ' (' . ucwords(str_replace('_', ' ', $col)) . ')';
            }
        }

        return $metrics;
    }

    private function recentRows(string $model, array $columns): array
    {
        return DisplayRowFetcher::fetch($model, $columns, 8);
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
