<?php

declare(strict_types=1);

namespace Statisty\Charts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class ChartQueryContextBuilder
{
    public function buildContext(string $modelClass, ?string $valueField, string $dateColumn): array
    {
        $query = $modelClass::query();
        $mainTable = (new $modelClass())->getTable();
        $isRelation = $valueField !== null && str_contains($valueField, '.');
        $relationField = null;
        $relationValueKey = null;
        $valueFieldToAggregate = $valueField !== null && ! $isRelation ? "{$mainTable}.{$valueField}" : null;

        if ($isRelation) {
            [$relationName, $relationField] = explode('.', $valueField, 2);
            $instance = new $modelClass();
            $relation = $instance->{$relationName}();
            $relatedModel = $relation->getRelated();
            $relatedTable = $relatedModel->getTable();
            $relationValueKey = '__statisty_relation_value';

            if ($relation instanceof HasMany || $relation instanceof HasOne) {
                $foreignKey = $relation->getForeignKeyName();
                $localKey = $relation->getLocalKeyName();
                $query->join($relatedTable, "{$mainTable}.{$localKey}", '=', "{$relatedTable}.{$foreignKey}");
            } elseif ($relation instanceof BelongsTo) {
                $foreignKey = $relation->getForeignKeyName();
                $ownerKey = $relation->getOwnerKeyName();
                $query->join($relatedTable, "{$mainTable}.{$foreignKey}", '=', "{$relatedTable}.{$ownerKey}");
            } else {
                throw new InvalidArgumentException('Unsupported relation type for charts.');
            }

            $query->select("{$mainTable}.*", "{$relatedTable}.{$relationField} as {$relationValueKey}");
            $valueFieldToAggregate = "{$relatedTable}.{$relationField}";
        }

        return [
            'query' => $query,
            'dateColumnPrefixed' => "{$mainTable}.{$dateColumn}",
            'isRelation' => $isRelation,
            'relationField' => $relationField,
            'relationValueKey' => $relationValueKey,
            'valueFieldToAggregate' => $valueFieldToAggregate,
        ];
    }

    public function applyDateFilters(EloquentBuilder $query, ?string $from, ?string $to, string $dateColumnPrefixed): void
    {
        if ($from !== null) {
            $query->where($dateColumnPrefixed, '>=', Carbon::parse($from));
        }

        if ($to !== null) {
            $query->where($dateColumnPrefixed, '<=', Carbon::parse($to));
        }
    }
}
