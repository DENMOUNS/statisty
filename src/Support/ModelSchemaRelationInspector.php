<?php

declare(strict_types=1);

namespace Statisty\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Statisty\Support\ModelSchemaCache;

final class ModelSchemaRelationInspector
{
    private const DISPLAY_FIELD_PRIORITY = ['name', 'title', 'label', 'full_name', 'fullname', 'username', 'email', 'code', 'reference', 'slug'];

    public static function isVisibleColumn(string|Model $model, string $column): bool
    {
        $class = ModelSchema::modelClass($model);
        $columns = ModelSchemaCache::columns($class);
        $hiddenColumns = ModelSchemaCache::hiddenColumns($class);
        $configured = ModelSchema::modelConfig($class);

        if ($configured !== null && array_key_exists('columns', $configured)) {
            return in_array($column, (array) $configured['columns'], true)
                && in_array($column, $columns, true)
                && ! in_array($column, $hiddenColumns, true);
        }

        return in_array($column, $columns, true)
            && ! in_array($column, $hiddenColumns, true);
    }

    public static function visibleColumns(string|Model $model, ?array $requested = null): array
    {
        $class = ModelSchema::modelClass($model);
        $columns = $requested === null ? ModelSchemaCache::columns($class) : $requested;

        if ($columns === []) {
            return [];
        }

        $configured = ModelSchema::modelConfig($class);
        if ($configured !== null && array_key_exists('columns', $configured)) {
            $columns = array_values(array_intersect($columns, (array) $configured['columns']));
            if ($columns === []) {
                return [];
            }
        }

        $hiddenColumns = ModelSchemaCache::hiddenColumns($class);

        return array_values(array_filter($columns, function ($column) use ($hiddenColumns): bool {
            return is_string($column)
                && ! str_contains($column, '.')
                && ! in_array($column, $hiddenColumns, true);
        }));
    }

    public static function relation(Model $model, string $name): ?Relation
    {
        if (! method_exists($model, $name)) {
            return null;
        }

        try {
            $relation = $model->{$name}();
        } catch (\Throwable) {
            return null;
        }

        return $relation instanceof Relation ? $relation : null;
    }

    public static function isVisibleRelationColumn(Model $model, string $relation, string $column): bool
    {
        $configured = ModelSchema::modelConfig($model);
        if ($configured !== null) {
            $relations = (array) ($configured['relations'] ?? []);
            if (! isset($relations[$relation])) {
                return false;
            }

            $relationConfig = is_array($relations[$relation]) ? $relations[$relation] : [];
            $allowedColumns = (array) ($relationConfig['columns'] ?? []);
            if ($allowedColumns !== [] && ! in_array($column, $allowedColumns, true)) {
                return false;
            }
        }

        $relationInstance = self::relation($model, $relation);
        if ($relationInstance === null) {
            return false;
        }

        return self::isVisibleColumn($relationInstance->getRelated(), $column);
    }

    public static function relationDisplayField(string $relatedModelClass): ?string
    {
        if (! ModelSchema::isQueryableModel($relatedModelClass) && ! class_exists($relatedModelClass)) {
            return null;
        }

        $visible = self::visibleColumns($relatedModelClass);
        $relatedPk = ModelSchema::primaryKey($relatedModelClass);
        $relatedFks = ModelSchemaCache::foreignKeyColumns($relatedModelClass);

        $candidates = array_values(array_filter(
            $visible,
            fn (string $c): bool => $c !== $relatedPk && ! in_array($c, $relatedFks, true),
        ));

        foreach (self::DISPLAY_FIELD_PRIORITY as $preferred) {
            if (in_array($preferred, $candidates, true)) {
                return $preferred;
            }
        }

        return $candidates[0] ?? null;
    }

    public static function displayColumns(string|Model $model): array
    {
        $class = ModelSchema::modelClass($model);
        $columns = self::visibleColumns($class);

        if ($columns === []) {
            return [];
        }

        $pk = ModelSchema::primaryKey($class);
        $belongsTo = ModelSchemaCache::belongsToRelations($class);
        $fkToRelation = [];

        foreach ($belongsTo as $relationName => $info) {
            $fkToRelation[$info['column']] = $relationName;
        }

        $instance = ModelSchema::resolveModelInstance($class);
        $result = [];
        $injectedRelations = [];

        foreach ($columns as $column) {
            if ($column === $pk) {
                continue;
            }

            if (isset($fkToRelation[$column])) {
                $relationName = $fkToRelation[$column];

                if (isset($injectedRelations[$relationName])) {
                    continue;
                }

                $injectedRelations[$relationName] = true;
                $relatedClass = $belongsTo[$relationName]['related'];
                $field = self::relationDisplayField($relatedClass);

                if ($field !== null && $instance instanceof Model && self::isVisibleRelationColumn($instance, $relationName, $field)) {
                    $result[] = $relationName . '.' . $field;
                }

                continue;
            }

            $result[] = $column;
        }

        return $result;
    }
}
