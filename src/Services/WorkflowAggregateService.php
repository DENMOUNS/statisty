<?php

declare(strict_types=1);

namespace Statisty\Services;

use Illuminate\Support\Facades\DB;
use Statisty\Support\ModelSchema;

final class WorkflowAggregateService
{
    public function fetchAggregates(string $modelClass, array $numericColumns): array
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
        } catch (\Throwable) {
            return ['_total' => 0];
        }
    }

    public function fetchStatusBreakdown(
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
        } catch (\Throwable) {
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
                'percent' => $totalCount > 0 ? round(($statusCount / $totalCount) * 100, 1) : 0,
                'numeric_sums' => $numericSums,
            ];
        }

        return $breakdown;
    }
}
