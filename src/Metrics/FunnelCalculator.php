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

        // columns to select for per-step scans
        $cols = [$distinctBy, $dateColumn];
        if (is_string($segmentBy) && ModelSchema::isVisibleColumn($modelClass, $segmentBy)) {
            $cols[] = $segmentBy;
        }

        foreach ($steps as $index => $step) {
            if ($index === 0) {
                $previousCompletions = $this->collectFirstStepCompletions(
                    $modelClass,
                    $cols,
                    $options,
                    $step,
                    $distinctBy,
                    $dateColumn,
                    $segmentBy,
                    $segments
                );

                $completed[] = ['step' => 1, 'count' => count($previousCompletions)];
                continue;
            }

            $current = $this->processStepForIdentities(
                $modelClass,
                $cols,
                $options,
                $step,
                $distinctBy,
                $dateColumn,
                $previousCompletions,
                $windowSeconds,
                $strict,
                $segmentBy,
                $segments
            );

            $previousCompletions = $current;
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

    /**
     * Collect earliest completion per identity for the first step using chunking.
     * Returns array identity => Carbon(timestamp)
     */
    private function collectFirstStepCompletions(string $modelClass, array $cols, array $options, array $step, string $distinctBy, string $dateColumn, ?string $segmentBy, array &$segments): array
    {
        $out = [];

        $query = $modelClass::query();
        $this->applyBaseConstraints($query, $options, $dateColumn);
        if (! $this->applyStep($query, $step)) {
            return [];
        }

        $query->orderBy($distinctBy)->orderBy($dateColumn)->select($cols);

        $query->chunk(1000, function ($items) use (&$out, $distinctBy, $dateColumn, $segmentBy, &$segments) {
            foreach ($items as $row) {
                $id = (string) $row->{$distinctBy};
                if ($id === '' || isset($out[$id])) {
                    continue;
                }

                $out[$id] = Carbon::parse($row->{$dateColumn});
                if ($segmentBy && isset($row->{$segmentBy})) {
                    $segments[(string) $row->{$segmentBy}][] = $id;
                }
            }
        });

        return $out;
    }

    /**
     * Process an intermediate step by scanning rows for the provided identities in chunks.
     * Returns array identity => Carbon(timestamp) for identities that match the step.
     */
    private function processStepForIdentities(string $modelClass, array $cols, array $options, array $step, string $distinctBy, string $dateColumn, array $previousCompletions, int $windowSeconds, bool $strict, ?string $segmentBy, array &$segments): array
    {
        $result = [];

        if (empty($previousCompletions)) {
            return [];
        }

        $ids = array_keys($previousCompletions);

        $query = $modelClass::query();
        $this->applyBaseConstraints($query, $options, $dateColumn);
        if (! $this->applyStep($query, $step)) {
            // optional: carry previous forward
            if (! empty($step['optional'])) {
                return $previousCompletions;
            }

            return [];
        }

        $query->whereIn($distinctBy, $ids)->orderBy($distinctBy)->orderBy($dateColumn)->select($cols);

        $prevMap = $previousCompletions;
        $window = $windowSeconds;

        $query->chunk(1000, function ($items) use (&$result, &$prevMap, $distinctBy, $dateColumn, $segmentBy, $window, &$segments) {
            foreach ($items as $row) {
                $identity = (string) $row->{$distinctBy};
                if (! isset($prevMap[$identity]) || isset($result[$identity])) {
                    continue;
                }

                $dt = Carbon::parse($row->{$dateColumn});
                $after = $prevMap[$identity];

                if (! $dt->gt($after)) {
                    continue;
                }

                if ($window > 0) {
                    $limit = $after->copy()->addSeconds($window);
                    if ($dt->gt($limit)) {
                        continue;
                    }
                }

                $result[$identity] = $dt;
                if ($segmentBy && isset($row->{$segmentBy})) {
                    $segments[(string) $row->{$segmentBy}][] = $identity;
                }
            }
        });

        // optional: carry forward identities that didn't match this step
        if (! empty($step['optional'])) {
            foreach ($previousCompletions as $id => $ts) {
                if (! isset($result[$id])) {
                    $result[$id] = $ts;
                }
            }
        }

        return $result;
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
