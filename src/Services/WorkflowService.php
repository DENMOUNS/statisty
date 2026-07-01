<?php

declare(strict_types=1);

namespace Statisty\Services;

use Illuminate\Support\Facades\DB;
use Statisty\Discovery\RelationshipProfile;
use Statisty\Support\DisplayRowFetcher;
use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class WorkflowService
{
    public function build(string $modelClass): array
    {
        $columns = ModelSchema::displayColumns($modelClass);
        $numericColumns = array_values(array_filter($columns, function (string $col): bool {
            $lower = strtolower($col);
            foreach (['amount', 'total', 'price', 'quantity', 'value', 'points', 'sum', 'count', 'score', 'total_amount', 'subtotal', 'revenue', 'cost', 'fee', 'tax', 'discount', 'weight', 'balance', 'salary', 'rate'] as $keyword) {
                if ($lower === $keyword || str_contains($lower, $keyword)) {
                    return true;
                }
            }

            return false;
        }));

        $statusColumns = array_values(array_filter($columns, function (string $col): bool {
            $lower = strtolower($col);
            return in_array($lower, ['status', 'state', 'stage', 'phase', 'type', 'kind', 'category', 'role', 'priority', 'level'], true)
                || str_ends_with($lower, '_status')
                || str_ends_with($lower, '_state')
                || str_ends_with($lower, '_type')
                || str_ends_with($lower, '_stage');
        }));

        $tableName = (new $modelClass())->getTable();
        $aggregates = $this->fetchAggregates($modelClass, $numericColumns);
        $totalCount = (int) ($aggregates['_total'] ?? 0);

        $kpis = [
            [
                'label' => 'Total ' . ModelName::label($modelClass),
                'value' => number_format($totalCount),
                'sub' => 'Nombre total d\'enregistrements',
                'type' => 'count',
                'icon' => 'total',
            ],
        ];

        foreach ($numericColumns as $col) {
            $sum = $aggregates['sum_' . $col] ?? 0;
            $avg = $aggregates['avg_' . $col] ?? 0;
            $label = ucwords(str_replace('_', ' ', $col));

            $kpis[] = [
                'label' => 'Total ' . $label,
                'value' => number_format((float) $sum, 2),
                'sub' => 'Somme de ' . $col,
                'type' => 'sum',
                'icon' => 'sum',
            ];
            $kpis[] = [
                'label' => 'Moy. ' . $label,
                'value' => number_format((float) $avg, 2),
                'sub' => 'Moyenne de ' . $col,
                'type' => 'avg',
                'icon' => 'avg',
            ];
        }

        $statusBreakdown = [];
        foreach ($statusColumns as $statusCol) {
            $breakdown = $this->fetchStatusBreakdown(
                $modelClass,
                $statusCol,
                $numericColumns,
                $totalCount,
            );

            foreach ($breakdown as $item) {
                $kpis[] = [
                    'label' => ucfirst(str_replace(['_', '-'], ' ', (string) $item['value'])),
                    'value' => number_format($item['count']),
                    'sub' => $statusCol . ' = ' . $item['value'],
                    'type' => 'status',
                    'status' => (string) $item['value'],
                    'icon' => 'status',
                ];
            }

            if (! empty($breakdown)) {
                $statusBreakdown[$statusCol] = $breakdown;
            }
        }

        $apiPrefix = trim((string) config('statisty.routes.api.prefix', 'api/statisty'), '/');

        return [
            'modelLabel' => ModelName::label($modelClass),
            'modelClass' => $modelClass,
            'kpis' => $kpis,
            'columns' => $columns,
            'rows' => $this->recentRows($modelClass, $columns, 50),
            'chartUrl' => url($apiPrefix . '/charts/' . rawurlencode($modelClass)),
            'relatedPanels' => $this->buildRelatedPanels($modelClass),
            'statusBreakdown' => $statusBreakdown,
            'statusColumns' => $statusColumns,
            'numericColumns' => $numericColumns,
            'chartMetrics' => $this->buildChartMetrics($modelClass, $numericColumns),
        ];
    }

    private function fetchAggregates(string $modelClass, array $numericColumns): array
    {
        $selects = ['COUNT(*) as _total'];
        $grammar = DB::getQueryGrammar();

        foreach ($numericColumns as $col) {
            $quoted = $grammar->wrap($col);
            $selects[] = "SUM({$quoted}) as sum_{$col}";
            $selects[] = "AVG({$quoted}) as avg_{$col}";
        }

        try {
            $row = $modelClass::query()
                ->selectRaw(implode(', ', $selects))
                ->first();

            return $row ? (array) $row->toArray() : ['_total' => 0];
        } catch (\Throwable $e) {
            return ['_total' => 0];
        }
    }

    private function fetchStatusBreakdown(
        string $modelClass,
        string $statusCol,
        array $numericColumns,
        int $totalCount,
    ): array {
        $grammar = DB::getQueryGrammar();
        $quotedStatus = $grammar->wrap($statusCol);

        $selects = [
            "{$quotedStatus} as _status_value",
            'COUNT(*) as _count',
        ];

        foreach ($numericColumns as $col) {
            $quoted = $grammar->wrap($col);
            $selects[] = "SUM({$quoted}) as sum_{$col}";
        }

        try {
            $rows = $modelClass::query()
                ->selectRaw(implode(', ', $selects))
                ->groupBy($statusCol)
                ->orderByDesc('_count')
                ->limit(20)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $breakdown = [];
        foreach ($rows as $row) {
            $statusValue = $row->_status_value ?? 'null';
            $statusCount = (int) ($row->_count ?? 0);

            $numericSums = [];
            foreach ($numericColumns as $col) {
                $numericSums[$col] = number_format((float) ($row->{'sum_' . $col} ?? 0), 2);
            }

            $breakdown[] = [
                'value' => (string) $statusValue,
                'count' => $statusCount,
                'percent' => $totalCount > 0
                    ? round(($statusCount / $totalCount) * 100, 1)
                    : 0,
                'numeric_sums' => $numericSums,
            ];
        }

        return $breakdown;
    }

    private function recentRows(string $modelClass, array $columns, int $limit = 50): array
    {
        return DisplayRowFetcher::fetch($modelClass, $columns, $limit);
    }

    private function buildChartMetrics(string $modelClass, array $numericColumns): array
    {
        $chartMetrics = [[
            'value' => '',
            'label' => 'Nombre de ' . ModelName::label($modelClass) . ' (Volume)',
        ]];

        foreach ($numericColumns as $col) {
            $chartMetrics[] = [
                'value' => $col,
                'label' => 'Somme de ' . ucwords(str_replace('_', ' ', $col)),
            ];
        }

        $configRelations = (array) config('statisty.models.' . $modelClass . '.relations', []);

        foreach ($configRelations as $relationName => $relConfig) {
            $relatedClass = null;
            try {
                $instance = new $modelClass();
                if (method_exists($instance, $relationName)) {
                    $relatedClass = get_class($instance->{$relationName}()->getRelated());
                }
            } catch (\Throwable $e) {
            }

            if (! $relatedClass || ! class_exists($relatedClass)) {
                continue;
            }

            $wantedCols = (array) ($relConfig['columns'] ?? []);
            $availableCols = ModelSchema::visibleColumns($relatedClass);
            $cols = $wantedCols !== []
                ? array_values(array_intersect($wantedCols, $availableCols))
                : $availableCols;

            $numericKeywords = [
                'amount', 'total', 'price', 'quantity', 'value', 'points',
                'sum', 'count', 'score', 'total_amount', 'subtotal', 'revenue',
                'cost', 'fee', 'tax', 'discount', 'weight', 'balance',
            ];

            foreach ($cols as $col) {
                if (in_array(strtolower($col), $numericKeywords, true)) {
                    $chartMetrics[] = [
                        'value' => "{$relationName}.{$col}",
                        'label' => ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName))
                            . ' : Somme de ' . ucwords(str_replace('_', ' ', $col)),
                    ];
                }
            }
        }

        return $chartMetrics;
    }

    private function buildRelatedPanels(string $modelClass): array
    {
        $configRelations = (array) config('statisty.models.' . $modelClass . '.relations', []);
        if ($configRelations === []) {
            return [];
        }

        try {
            $profiler = app(RelationshipProfile::class);
            $profiledRelations = $profiler->profileModel($modelClass);
        } catch (\Throwable $e) {
            $profiledRelations = [];
        }

        $panels = [];
        $webPrefix = trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/');

        foreach ($configRelations as $relationName => $relConfig) {
            if (! isset($profiledRelations[$relationName])) {
                continue;
            }

            $relatedClass = $profiledRelations[$relationName]['related'] ?? null;
            $relationType = $profiledRelations[$relationName]['type'] ?? 'Unknown';

            if (! $relatedClass || ! class_exists($relatedClass)) {
                continue;
            }

            $wantedCols = (array) ($relConfig['columns'] ?? []);
            $availableCols = ModelSchema::displayColumns($relatedClass);
            $cols = $wantedCols !== []
                ? array_values(array_intersect($wantedCols, $availableCols))
                : array_slice($availableCols, 0, 5);

            if ($cols === []) {
                continue;
            }

            try {
                $relatedCount = (int) $relatedClass::query()->count();
                $sample = DisplayRowFetcher::fetch($relatedClass, $cols, 15);
            } catch (\Throwable $e) {
                $relatedCount = 0;
                $sample = [];
            }

            $panels[] = [
                'relationName' => $relationName,
                'label' => ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName)),
                'type' => $relationType,
                'relatedClass' => $relatedClass,
                'relatedLabel' => ModelName::label($relatedClass),
                'columns' => $cols,
                'count' => $relatedCount,
                'sample' => $sample,
                'workflowUrl' => url($webPrefix . '/workflow/' . str_replace('\\', '%5C', $relatedClass)),
            ];
        }

        return $panels;
    }
}