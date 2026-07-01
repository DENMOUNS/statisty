<?php

declare(strict_types=1);

namespace Statisty\Metrics;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Statisty\Support\ModelSchema;

final class FunnelQueryBuilder
{
    public function applyBaseConstraints(EloquentBuilder $query, array $options, string $dateColumn): void
    {
        if (ModelSchema::isVisibleColumn($query->getModel(), $dateColumn)) {
            if (! empty($options['date_from'])) {
                $query->where($dateColumn, '>=', $options['date_from']);
            }

            if (! empty($options['date_to'])) {
                $query->where($dateColumn, '<=', $options['date_to']);
            }
        }

        foreach ((array) ($options['filters'] ?? []) as $column => $value) {
            $column = (string) $column;
            if (! ModelSchema::isVisibleColumn($query->getModel(), $column)) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }
    }

    public function applyStep(EloquentBuilder $query, array $step): bool
    {
        $col = $step['column'] ?? null;
        $op = $step['operator'] ?? '=';
        $val = $step['value'] ?? null;

        if (! is_string($col) || ! ModelSchema::isVisibleColumn($query->getModel(), $col)) {
            return false;
        }

        if (strtolower((string) $op) === 'in' && is_array($val)) {
            $query->whereIn($col, $val);
            return true;
        }

        if ($op === '=') {
            $query->where($col, $val);
            return true;
        }

        $allowedOperators = ['!=', '<>', '>', '>=', '<', '<=', 'like'];
        if (is_string($op) && in_array(strtolower($op), $allowedOperators, true)) {
            $query->where($col, $op, $val);
            return true;
        }

        return false;
    }
}
