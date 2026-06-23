<?php

declare(strict_types=1);

namespace Statisty\Metrics;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Carbon;
use Statisty\Support\ModelSchema;

final class FunnelCalculator
{
    /**
     * Steps: array of ['column' => ..., 'operator' => '=', 'value' => ...]
     * Returns array of step counts and conversion rates.
     */
    public function run(string $modelClass, array $steps, array $options = []): array
    {
        if (! ModelSchema::isQueryableModel($modelClass)) {
            throw new \InvalidArgumentException("Model {$modelClass} not found");
        }

        $distinctBy = $this->distinctColumn($modelClass, $options);
        $dateColumn = (string) ($options['date_column'] ?? 'created_at');

        if ($distinctBy !== null && ModelSchema::isVisibleColumn($modelClass, $dateColumn)) {
            return $this->runSequential($modelClass, $steps, $distinctBy, $dateColumn, $options);
        }

        $results = [];

        foreach ($steps as $i => $step) {
            $q = $modelClass::query();
            $this->applyBaseConstraints($q, $options, $dateColumn);
            if (! $this->applyStep($q, $step)) {
                $results[] = ['step' => $i + 1, 'count' => 0];
                continue;
            }

            if ($distinctBy) {
                $count = (int) $q->distinct($distinctBy)->count($distinctBy);
            } else {
                $count = (int) $q->count();
            }

            $results[] = ['step' => $i + 1, 'count' => $count];
        }

        // compute conversion rates relative to first step
        $base = $results[0]['count'] ?? 0;
        foreach ($results as &$r) {
            $r['conversion_rate'] = $base > 0 ? ($r['count'] / $base) * 100 : 0.0;
        }

        return ['steps' => $results];
    }

    private function runSequential(string $modelClass, array $steps, string $distinctBy, string $dateColumn, array $options): array
    {
        $completed = [];
        $previousCompletions = [];

        $windowSeconds = isset($options['conversion_window']) ? (int) $options['conversion_window'] : 0;
        $strict = ! empty($options['strict_order']);
        $segmentBy = $options['segment_by'] ?? null;
        $segments = [];

        foreach ($steps as $index => $step) {
            if ($index === 0) {
                $query = $modelClass::query();
                $this->applyBaseConstraints($query, $options, $dateColumn);
                if (! $this->applyStep($query, $step)) {
                    $completed[] = ['step' => 1, 'count' => 0];
                    $previousCompletions = [];
                    continue;
                }

                $cols = [$distinctBy, $dateColumn];
                if (is_string($segmentBy) && ModelSchema::isVisibleColumn($modelClass, $segmentBy)) {
                    $cols[] = $segmentBy;
                }

                $rows = $query->orderBy($dateColumn)->get($cols);
                foreach ($rows as $row) {
                    $identity = $row->{$distinctBy};
                    if ($identity === null || isset($previousCompletions[(string) $identity])) {
                        continue;
                    }

                    $previousCompletions[(string) $identity] = Carbon::parse($row->{$dateColumn});

                    if (isset($row->{$segmentBy})) {
                        $segments[(string) $row->{$segmentBy}][] = (string) $identity;
                    }
                }

                $completed[] = ['step' => 1, 'count' => count($previousCompletions)];
                continue;
            }

            $currentCompletions = [];
            foreach ($previousCompletions as $identity => $after) {
                $query = $modelClass::query()->where($distinctBy, $identity);

                // apply ordering constraint
                if ($strict) {
                    $query->where($dateColumn, '>', $after);
                } else {
                    $query->where($dateColumn, '>=', $after);
                }

                // apply conversion window if set
                if ($windowSeconds > 0) {
                    $limit = $after->copy()->addSeconds($windowSeconds);
                    $query->where($dateColumn, '<=', $limit);
                }

                $this->applyBaseConstraints($query, $options, $dateColumn);
                if (! $this->applyStep($query, $step)) {
                    // optional step handling
                    if (! empty($step['optional'])) {
                        // carry forward identity without changing timestamp
                        $currentCompletions[$identity] = $after;
                    }

                    continue;
                }

                $cols = [$distinctBy, $dateColumn];
                if (is_string($segmentBy) && ModelSchema::isVisibleColumn($modelClass, $segmentBy)) {
                    $cols[] = $segmentBy;
                }

                $row = $query->orderBy($dateColumn)->first($cols);
                if ($row !== null) {
                    $currentCompletions[$identity] = Carbon::parse($row->{$dateColumn});
                    if (isset($row->{$segmentBy})) {
                        $segments[(string) $row->{$segmentBy}][] = (string) $identity;
                    }
                }
            }

            $previousCompletions = $currentCompletions;
            $completed[] = ['step' => $index + 1, 'count' => count($previousCompletions)];
        }

        $base = $completed[0]['count'] ?? 0;
        foreach ($completed as &$result) {
            $result['conversion_rate'] = $base > 0 ? ($result['count'] / $base) * 100 : 0.0;
        }

        $out = ['steps' => $completed];
        if (! empty($segments)) {
            $out['segments'] = array_map('count', $segments);
        }

        return $out;
    }

    private function distinctColumn(string $modelClass, array $options): ?string
    {
        $requested = $options['distinct_by'] ?? null;
        if (is_string($requested) && ModelSchema::isVisibleColumn($modelClass, $requested)) {
            return $requested;
        }

        return ModelSchema::isVisibleColumn($modelClass, 'user_id') ? 'user_id' : null;
    }

    private function applyBaseConstraints(EloquentBuilder $query, array $options, string $dateColumn): void
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

    private function applyStep(EloquentBuilder $query, array $step): bool
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
