<?php

declare(strict_types=1);

namespace Statisty\Metrics;

use Illuminate\Support\Carbon;

final class FunnelStepProcessor
{
    public function collectFirstStepCompletions(
        string $modelClass,
        array $cols,
        array $options,
        array $step,
        string $distinctBy,
        string $dateColumn,
        ?string $segmentBy,
        array &$segments,
    ): array {
        $out = [];

        $query = $modelClass::query();
        (new FunnelQueryBuilder())->applyBaseConstraints($query, $options, $dateColumn);
        if (! (new FunnelQueryBuilder())->applyStep($query, $step)) {
            return [];
        }

        $query->orderBy($distinctBy)->orderBy($dateColumn)->select($cols);

        $query->chunk(1000, function ($items) use (&$out, $distinctBy, $dateColumn, $segmentBy, &$segments): void {
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

    public function processStepForIdentities(
        string $modelClass,
        array $cols,
        array $options,
        array $step,
        string $distinctBy,
        string $dateColumn,
        array $previousCompletions,
        int $windowSeconds,
        bool $strict,
        ?string $segmentBy,
        array &$segments,
    ): array {
        $result = [];

        if (empty($previousCompletions)) {
            return [];
        }

        $ids = array_keys($previousCompletions);
        $query = $modelClass::query();
        (new FunnelQueryBuilder())->applyBaseConstraints($query, $options, $dateColumn);
        if (! (new FunnelQueryBuilder())->applyStep($query, $step)) {
            if (! empty($step['optional'])) {
                return $previousCompletions;
            }

            return [];
        }

        $query->whereIn($distinctBy, $ids)->orderBy($distinctBy)->orderBy($dateColumn)->select($cols);
        $prevMap = $previousCompletions;
        $window = $windowSeconds;

        $query->chunk(1000, function ($items) use (&$result, &$prevMap, $distinctBy, $dateColumn, $segmentBy, $window, &$segments): void {
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

        if (! empty($step['optional'])) {
            foreach ($previousCompletions as $id => $ts) {
                if (! isset($result[$id])) {
                    $result[$id] = $ts;
                }
            }
        }

        return $result;
    }
}
