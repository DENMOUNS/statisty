<?php

declare(strict_types=1);

namespace Statisty\Metrics;

use InvalidArgumentException;
use Throwable;
use Statisty\Contracts\KpiCalculatorContract;
use Statisty\Workspace\WorkspaceDefinition;
use Statisty\Support\ModelSchema;

final class EloquentKpiCalculator implements KpiCalculatorContract
{
    public function calculate(KpiDefinition $kpi, WorkspaceDefinition $workspace): KpiDefinition
    {
        if (! $this->canQuery($kpi->model)) {
            return $kpi->pending();
        }

        try {
            $query = $kpi->model::query();
            $query = $this->applyDateRange($query, $workspace);
            $query = $this->applyFilters($query, $workspace);

            $value = $this->aggregate($query, $kpi);

            // previous period comparison support
            $comparison = [];
            if (! empty($kpi->options['compare_previous']) && $workspace->options->dateFrom !== null && $workspace->options->dateTo !== null) {
                $from = \Illuminate\Support\Carbon::parse($workspace->options->dateFrom);
                $to = \Illuminate\Support\Carbon::parse($workspace->options->dateTo);
                $length = $to->getTimestamp() - $from->getTimestamp();

                $prevTo = $from->copy()->subSecond();
                $prevFrom = $from->copy()->subSeconds($length);

                $prevQuery = $kpi->model::query();
                // apply same filters
                $prevQuery = $this->applyFilters($prevQuery, $workspace);
                // apply previous date range
                $prevQuery->where($workspace->options->dateColumn, '>=', $prevFrom->toDateTimeString());
                $prevQuery->where($workspace->options->dateColumn, '<=', $prevTo->toDateTimeString());

                $previous = $this->aggregate($prevQuery, $kpi);
                $delta = is_numeric($value) && is_numeric($previous) ? $value - $previous : null;
                $pct = (is_numeric($previous) && $previous != 0) ? ($delta / $previous) * 100 : null;

                $comparison = ['previous' => $previous, 'delta' => $delta, 'percent_change' => $pct];
            }

            $kpi = $kpi->withExtraOptions(['comparison' => $comparison]);

            // group-by support returns map of group => value when specified
            if (! empty($kpi->options['group_by']) && is_string($kpi->options['group_by']) && ModelSchema::isVisibleColumn($kpi->model, $kpi->options['group_by'])) {
                $group = $kpi->options['group_by'];
                $grouped = $query->groupBy($group)->get()->mapWithKeys(function ($row) use ($group, $kpi) {
                    return [$row->{$group} => match ($kpi->type) {
                        MetricType::COUNT => (int) ($row->count ?? 0),
                        default => $row->{$kpi->field} ?? null,
                    }];
                })->toArray();

                return $kpi->withValue($grouped);
            }

            return $kpi->withValue($value);
        } catch (Throwable $exception) {
            return $kpi->failed($exception->getMessage());
        }
    }

    private function canQuery(string $model): bool
    {
        return ModelSchema::isQueryableModel($model)
            && method_exists($model, 'query');
    }

    private function applyDateRange(mixed $query, WorkspaceDefinition $workspace): mixed
    {
        $column = $workspace->options->dateColumn;

        if ($column === null) {
            return $query;
        }

        if (! ModelSchema::isVisibleColumn($query->getModel(), $column)) {
            throw new InvalidArgumentException("Date column [{$column}] is not available.");
        }

        if ($workspace->options->dateFrom !== null) {
            $query->where($column, '>=', $workspace->options->dateFrom);
        }

        if ($workspace->options->dateTo !== null) {
            $query->where($column, '<=', $workspace->options->dateTo);
        }

        return $query;
    }

    private function applyFilters(mixed $query, WorkspaceDefinition $workspace): mixed
    {
        foreach ($workspace->options->filters as $column => $value) {
            $column = (string) $column;

            if (! ModelSchema::isVisibleColumn($query->getModel(), $column)) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($column, $value);
                continue;
            }

            $query->where($column, $value);
        }

        return $query;
    }

    private function aggregate(mixed $query, KpiDefinition $kpi): mixed
    {
        return match ($kpi->type) {
            MetricType::COUNT => (int) $query->count(),
            MetricType::SUM => $query->sum($this->requiredField($kpi)),
            MetricType::AVERAGE => $query->avg($this->requiredField($kpi)),
            MetricType::MIN => $query->min($this->requiredField($kpi)),
            MetricType::MAX => $query->max($this->requiredField($kpi)),
            default => null,
        };
    }

    private function requiredField(KpiDefinition $kpi): string
    {
        if ($kpi->field === null || trim($kpi->field) === '') {
            throw new InvalidArgumentException("KPI [{$kpi->name}] requires a field.");
        }

        if (! ModelSchema::isVisibleColumn($kpi->model, $kpi->field)) {
            throw new InvalidArgumentException("KPI field [{$kpi->field}] is not available.");
        }

        return $kpi->field;
    }
}
