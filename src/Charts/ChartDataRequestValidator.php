<?php

declare(strict_types=1);

namespace Statisty\Charts;

use InvalidArgumentException;
use Statisty\Support\ModelSchema;

final class ChartDataRequestValidator
{
    public function validate(string $modelClass, ?string $valueField, string $dateColumn): void
    {
        if (! ModelSchema::isQueryableModel($modelClass)) {
            throw new InvalidArgumentException("Model class [{$modelClass}] not found.");
        }

        if (! ModelSchema::isVisibleColumn($modelClass, $dateColumn)) {
            throw new InvalidArgumentException("Date column [{$dateColumn}] is not available for charts.");
        }

        if ($valueField === null) {
            return;
        }

        if (str_contains($valueField, '.')) {
            [$relationName, $relationColumn] = explode('.', $valueField, 2);
            $instance = new $modelClass();

            if (! ModelSchema::isVisibleRelationColumn($instance, $relationName, $relationColumn)) {
                throw new InvalidArgumentException("Relation column [{$valueField}] is not available for charts.");
            }

            return;
        }

        if (! ModelSchema::isVisibleColumn($modelClass, $valueField)) {
            throw new InvalidArgumentException("Value column [{$valueField}] is not available for charts.");
        }
    }
}
