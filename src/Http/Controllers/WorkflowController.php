<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class WorkflowController extends BaseDashboardController
{
    public function workflow(Request $request, string $model)
    {
        // Unescape backslashes in model class namespace
        $modelClass = str_replace('%5C', '\\', $model);
        if (!str_starts_with($modelClass, '\\')) {
            $modelClass = '\\' . $modelClass;
        }
        $modelClass = ltrim($modelClass, '\\');

        if (ModelSchema::isDisabledModel($modelClass)) {
            abort(403, 'Model is disabled.');
        }

        if (!ModelSchema::isQueryableModel($modelClass)) {
            abort(404, 'Model is not queryable.');
        }

        try {
            $totalCount = (int) $modelClass::query()->count();
            $columns    = ModelSchema::visibleColumns($modelClass);

            // ── Colonnes sémantiques détectées automatiquement
            $numericColumns = ModelSchema::semanticNumericColumns($modelClass);
            $statusColumns  = ModelSchema::semanticStatusColumns($modelClass);

            // ── KPI de base : total
            $kpis = [
                [
                    'label' => 'Total ' . ModelName::label($modelClass),
                    'value' => number_format($totalCount),
                    'sub'   => 'Nombre total d\'enregistrements',
                    'type'  => 'count',
                    'icon'  => 'total',
                ]
            ];

            // ── KPIs numériques globaux (sum + avg)
            foreach ($numericColumns as $col) {
                try {
                    $sum = $modelClass::query()->sum($col);
                    $avg = $modelClass::query()->avg($col);
                    $label = ucwords(str_replace('_', ' ', $col));

                    $kpis[] = [
                        'label' => 'Total ' . $label,
                        'value' => is_numeric($sum) ? number_format((float) $sum, 2) : '0.00',
                        'sub'   => 'Somme de ' . $col,
                        'type'  => 'sum',
                        'icon'  => 'sum',
                    ];
                    $kpis[] = [
                        'label' => 'Moy. ' . $label,
                        'value' => is_numeric($avg) ? number_format((float) $avg, 2) : '0.00',
                        'sub'   => 'Moyenne de ' . $col,
                        'type'  => 'avg',
                        'icon'  => 'avg',
                    ];
                } catch (\Throwable $e) {
                    // Ignore transient SQL errors on calculation
                }
            }

            // ── Analyse par statut : répartition + métriques croisées
            $statusBreakdown = [];
            foreach ($statusColumns as $statusCol) {
                try {
                    $groups = $modelClass::query()
                        ->select($statusCol, \DB::raw('COUNT(*) as _count'))
                        ->groupBy($statusCol)
                        ->orderByDesc('_count')
                        ->limit(20)
                        ->get();

                    $breakdown = [];
                    foreach ($groups as $row) {
                        $statusValue = $row->{$statusCol} ?? 'null';
                        $statusCount = (int) ($row->_count ?? 0);

                        // KPI count par valeur de statut
                        $kpis[] = [
                            'label'  => ucfirst(str_replace(['_', '-'], ' ', (string) $statusValue)),
                            'value'  => number_format($statusCount),
                            'sub'    => $statusCol . ' = ' . $statusValue,
                            'type'   => 'status',
                            'status' => (string) $statusValue,
                            'icon'   => 'status',
                        ];

                        // KPIs numériques par valeur de statut
                        $numericByStatus = [];
                        foreach ($numericColumns as $numCol) {
                            try {
                                $filteredSum = (float) $modelClass::query()
                                    ->where($statusCol, $statusValue)
                                    ->sum($numCol);
                                $numericByStatus[$numCol] = number_format($filteredSum, 2);
                            } catch (\Throwable $e) {
                                $numericByStatus[$numCol] = null;
                            }
                        }

                        $breakdown[] = [
                            'value'         => (string) $statusValue,
                            'count'         => $statusCount,
                            'percent'       => $totalCount > 0 ? round(($statusCount / $totalCount) * 100, 1) : 0,
                            'numeric_sums'  => $numericByStatus,
                        ];
                    }

                    $statusBreakdown[$statusCol] = $breakdown;
                } catch (\Throwable $e) {
                    // Ignore si la colonne est inaccessible
                }
            }

            // ── Fetch last 50 rows for the Datatable
            $recentLimit = 50;
            $rows = [];
            if (method_exists($modelClass, 'query')) {
                $query = $modelClass::query()->select($columns)->limit($recentLimit);
                if (in_array('created_at', $columns, true)) {
                    $query->latest('created_at');
                }
                $rows = $query->get()
                    ->map(fn (mixed $row): array => collect($row->toArray())
                        ->only($columns)
                        ->map(fn (mixed $val): mixed => is_scalar($val) || $val === null ? $val : json_encode($val))
                        ->all())
                    ->all();
            }

            // ── Build panels for relations declared in config
            $relatedPanels = $this->buildRelatedPanels($modelClass);

            // ── Compiler les métriques disponibles pour les graphiques (Champs locaux + Relations numériques)
            $chartMetrics = [
                [
                    'value' => '',
                    'label' => 'Nombre de ' . ModelName::label($modelClass) . ' (Volume)',
                ]
            ];

            // Colonnes numériques locales
            foreach ($numericColumns as $col) {
                $chartMetrics[] = [
                    'value' => $col,
                    'label' => 'Somme de ' . ucwords(str_replace('_', ' ', $col)),
                ];
            }

            // Colonnes numériques des relations configurées
            $configRelations = (array) config('statisty.models.' . $modelClass . '.relations', []);
            foreach ($configRelations as $relationName => $relConfig) {
                $relatedClass = null;
                try {
                    $profiler = app(\Statisty\Discovery\RelationshipProfile::class);
                    $profiledRelations = $profiler->profileModel($modelClass);
                    $relatedClass = $profiledRelations[$relationName]['related'] ?? null;
                } catch (\Throwable $e) {}

                if (!$relatedClass) {
                    try {
                        $instance = new $modelClass();
                        if (method_exists($instance, $relationName)) {
                            $relatedClass = get_class($instance->{$relationName}()->getRelated());
                        }
                    } catch (\Throwable $e) {}
                }

                if ($relatedClass && class_exists($relatedClass)) {
                    $wantedCols = (array) ($relConfig['columns'] ?? []);
                    $availableCols = ModelSchema::visibleColumns($relatedClass);
                    $cols = $wantedCols !== []
                        ? array_values(array_intersect($wantedCols, $availableCols))
                        : $availableCols;

                    $relatedNumericCols = [];
                    foreach ($cols as $col) {
                        if (in_array(strtolower($col), ['amount', 'total', 'price', 'quantity', 'value', 'points', 'sum', 'count', 'score', 'total_amount', 'subtotal', 'revenue', 'cost', 'fee', 'tax', 'discount', 'weight', 'balance'])) {
                            $relatedNumericCols[] = $col;
                        }
                    }

                    foreach ($relatedNumericCols as $col) {
                        $chartMetrics[] = [
                            'value' => "{$relationName}.{$col}",
                            'label' => ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName)) . ' : Somme de ' . ucwords(str_replace('_', ' ', $col)),
                        ];
                    }
                }
            }

            return view('statisty::workflow', [
                'appName'         => config('app.name'),
                'version'         => config('statisty.version', '1.0.0'),
                'modelLabel'      => ModelName::label($modelClass),
                'modelClass'      => $modelClass,
                'kpis'            => $kpis,
                'columns'         => $columns,
                'rows'            => $rows,
                'chartUrl'        => $this->apiUrl('charts', $modelClass),
                'relatedPanels'   => $relatedPanels,
                'statusBreakdown' => $statusBreakdown,
                'statusColumns'   => $statusColumns,
                'numericColumns'  => $numericColumns,
                'chartMetrics'    => $chartMetrics,
                ...$this->shellData('dashboard'),
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                abort(500, $e->getMessage());
            }
            abort(500, 'Server error');
        }
    }

    /**
     * Resolve related model panels from config + RelationshipProfile introspection.
     * Returns an array of panels, each with: relationName, label, type, relatedClass,
     * relatedLabel, columns, count, sample rows, workflowUrl.
     */
    private function buildRelatedPanels(string $modelClass): array
    {
        $configRelations = (array) config('statisty.models.' . $modelClass . '.relations', []);

        if ($configRelations === []) {
            return [];
        }

        $profiler = app(\Statisty\Discovery\RelationshipProfile::class);
        try {
            $profiledRelations = $profiler->profileModel($modelClass);
        } catch (\Throwable $e) {
            $profiledRelations = [];
        }

        $panels    = [];
        $webPrefix = trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/');

        foreach ($configRelations as $relationName => $relConfig) {
            if (!isset($profiledRelations[$relationName])) {
                continue;
            }

            $relatedClass = $profiledRelations[$relationName]['related'] ?? null;
            $relationType = $profiledRelations[$relationName]['type'] ?? 'Unknown';

            if (!$relatedClass || !class_exists($relatedClass)) {
                continue;
            }

            $wantedCols   = (array) ($relConfig['columns'] ?? []);
            $availableCols = ModelSchema::visibleColumns($relatedClass);
            $cols = $wantedCols !== []
                ? array_values(array_intersect($wantedCols, $availableCols))
                : array_slice($availableCols, 0, 5);

            if ($cols === []) {
                continue;
            }

            try {
                $relatedCount = (int) $relatedClass::query()->count();

                $sampleQuery = $relatedClass::query()->select($cols)->limit(15);
                if (in_array('created_at', $cols, true)) {
                    $sampleQuery->latest('created_at');
                }
                $sample = $sampleQuery->get()
                    ->map(fn (mixed $row): array => collect($row->toArray())
                        ->only($cols)
                        ->map(fn (mixed $v): mixed => is_scalar($v) || $v === null ? $v : json_encode($v))
                        ->all())
                    ->all();
            } catch (\Throwable $e) {
                $relatedCount = 0;
                $sample       = [];
            }

            $panels[] = [
                'relationName' => $relationName,
                'label'        => ucwords(str_replace(['_', 'Items'], [' ', ' Items'], $relationName)),
                'type'         => $relationType,
                'relatedClass' => $relatedClass,
                'relatedLabel' => ModelName::label($relatedClass),
                'columns'      => $cols,
                'count'        => $relatedCount,
                'sample'       => $sample,
                'workflowUrl'  => url($webPrefix . '/workflow/' . str_replace('\\', '%5C', $relatedClass)),
            ];
        }

        return $panels;
    }
}
