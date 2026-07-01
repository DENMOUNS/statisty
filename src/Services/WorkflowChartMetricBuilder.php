<?php

declare(strict_types=1);

namespace Statisty\Services;

use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class WorkflowChartMetricBuilder
{
    public function buildChartMetrics(string $modelClass, array $numericColumns): array
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
            } catch (\Throwable) {
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
}
