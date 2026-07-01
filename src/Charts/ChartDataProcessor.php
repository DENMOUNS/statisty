<?php

declare(strict_types=1);

namespace Statisty\Charts;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ChartDataProcessor
{
    public function __construct(
        private readonly ChartPeriodAggregator $aggregator = new ChartPeriodAggregator(),
        private readonly ChartDataTransformer $transformer = new ChartDataTransformer(),
    ) {
    }

    public function resolveRows(
        EloquentBuilder $query,
        ?string $valueField,
        string $dateColumn,
        string $dateColumnPrefixed,
        bool $isRelation,
        ?string $relationField,
        ?string $valueFieldToAggregate,
        array $options,
        string $period,
    ): mixed {
        if ($valueField === null) {
            return $this->aggregator->aggregateCountByPeriod($query, $dateColumnPrefixed, $period);
        }

        if ($this->shouldUseRawRows($options)) {
            return $this->collectRawRows($query, $dateColumn, $valueField, $isRelation, $relationField);
        }

        $numericSample = $this->detectNumericSample($query, $valueFieldToAggregate);
        if ($numericSample) {
            return $this->aggregator->aggregateSumByPeriod($query, $dateColumnPrefixed, $valueFieldToAggregate, $period);
        }

        return $this->collectCategoryCounts($query, $dateColumn, $valueField, $isRelation, $relationField);
    }

    public function shouldUseRawRows(array $options): bool
    {
        return ! empty($options['percentile']) || ! empty($options['histogram']);
    }

    public function detectNumericSample(EloquentBuilder $query, ?string $valueFieldToAggregate): bool
    {
        if ($valueFieldToAggregate === null) {
            return false;
        }

        if ($this->isColumnNumeric($query, $valueFieldToAggregate)) {
            return true;
        }

        try {
            $sampleQuery = clone $query;
            $sample = $sampleQuery->whereNotNull($valueFieldToAggregate)
                ->limit(50)
                ->pluck($valueFieldToAggregate)
                ->filter()
                ->values();

            return $sample->filter(fn ($v) => is_numeric($v))->count() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function collectRawRows(EloquentBuilder $query, string $dateColumn, string $valueField, bool $isRelation, ?string $relationField): Collection
    {
        $dateKey = $dateColumn;
        $valueKey = $isRelation ? $relationField : $valueField;
        $rows = new Collection();

        $query->chunk(1000, function ($items) use ($rows, $dateKey, $valueKey): void {
            foreach ($items as $item) {
                $rows->push([
                    'date' => Carbon::parse($item->{$dateKey}),
                    'value' => $item->{$valueKey},
                ]);
            }
        });

        return $rows;
    }

    public function collectCategoryCounts(EloquentBuilder $query, string $dateColumn, string $valueField, bool $isRelation, ?string $relationField): array
    {
        $valueKey = $isRelation ? $relationField : $valueField;
        $counts = [];

        $query->chunk(1000, function ($items) use (&$counts, $valueKey): void {
            foreach ($items as $item) {
                $value = $item->{$valueKey};
                if ($value === null || $value === '') {
                    continue;
                }

                $value = (string) $value;
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        });

        arsort($counts);

        return $counts;
    }

    public function groupValuesByPeriod(Collection $rows, string $period): array
    {
        return $this->transformer->groupValuesByPeriod($rows, $period);
    }

    public function groupByPeriodSum($rows, string $period): array
    {
        return $this->transformer->groupByPeriodSum($rows, $period);
    }

    public function groupByPeriod($rows, string $period): array
    {
        return $this->transformer->groupByPeriod($rows, $period);
    }

    public function percentile(array $values, float $percentile): float
    {
        return $this->transformer->percentile($values, $percentile);
    }

    public function histogram(array $values, int $bins): array
    {
        return $this->transformer->histogram($values, $bins);
    }

    public function cumulative(array $data): array
    {
        return $this->transformer->cumulative($data);
    }

    public function movingAverage(array $data, int $window): array
    {
        return $this->transformer->movingAverage($data, $window);
    }

    private function isColumnNumeric(EloquentBuilder $query, string $valueField): bool
    {
        [$table, $column] = $this->parseQualifiedField($query, $valueField);

        try {
            $connection = $query->getConnection();
            if (! $connection instanceof Connection) {
                return false;
            }

            $schema = $connection->getSchemaBuilder();

            if (method_exists($schema, 'getColumnType')) {
                $type = $schema->getColumnType($table, $column);
                return in_array($type, ['integer', 'bigint', 'smallint', 'decimal', 'float', 'double', 'real', 'numeric'], true);
            }

            if (method_exists($connection, 'getDoctrineColumn')) {
                $doctrineType = $connection->getDoctrineColumn($table, $column)->getType()->getName();
                return in_array($doctrineType, ['integer', 'smallint', 'bigint', 'decimal', 'float', 'double', 'real', 'numeric'], true);
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private function parseQualifiedField(EloquentBuilder $query, string $qualifiedField): array
    {
        if (str_contains($qualifiedField, '.')) {
            return explode('.', $qualifiedField, 2);
        }

        return [$query->getModel()->getTable(), $qualifiedField];
    }
}
