<?php

declare(strict_types=1);

namespace Statisty\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ModelSchema
{
    private const DEFAULT_SENSITIVE = ['password', 'remember_token', 'tokens', 'api_token', 'token', 'secret', 'secrets'];

    public static function isQueryableModel(string $modelClass): bool
    {
        return class_exists($modelClass)
            && is_subclass_of($modelClass, Model::class)
            && method_exists($modelClass, 'query')
            && self::isExposedModel($modelClass);
    }

    public static function isExposedModel(string $modelClass): bool
    {
        if (in_array($modelClass, (array) config('statisty.disabled_models', []), true)) {
            return false;
        }

        $configured = self::modelConfig($modelClass);

        if ($configured !== null) {
            return (bool) ($configured['enabled'] ?? true);
        }

        return (bool) config('statisty.allow_unlisted_models', true);
    }

    public static function isDisabledModel(string $modelClass): bool
    {
        return in_array($modelClass, (array) config('statisty.disabled_models', []), true);
    }

    public static function modelConfig(string|Model $model): ?array
    {
        $class = is_string($model) ? ltrim($model, '\\') : $model::class;
        $models = (array) config('statisty.models', []);

        return isset($models[$class]) && is_array($models[$class]) ? $models[$class] : null;
    }

    public static function columns(string|Model $model): array
    {
        $instance = is_string($model) ? new $model() : $model;

        if (! $instance instanceof Model) {
            return [];
        }

        try {
            return $instance->getConnection()->getSchemaBuilder()->getColumnListing($instance->getTable());
        } catch (\Throwable) {
            return [];
        }
    }

    public static function hiddenColumns(string|Model $model): array
    {
        $instance = is_string($model) ? new $model() : $model;
        $modelHidden = $instance instanceof Model && method_exists($instance, 'getHidden')
            ? (array) $instance->getHidden()
            : [];

        return array_values(array_unique(array_merge(
            self::DEFAULT_SENSITIVE,
            (array) config('statisty.hidden_columns', []),
            (array) config('statisty.security.hidden_columns', []),
            $modelHidden,
        )));
    }

    public static function isVisibleColumn(string|Model $model, string $column): bool
    {
        $configured = self::modelConfig($model);
        if ($configured !== null && array_key_exists('columns', $configured)) {
            return in_array($column, (array) $configured['columns'], true)
                && in_array($column, self::columns($model), true)
                && ! in_array($column, self::hiddenColumns($model), true);
        }

        return in_array($column, self::columns($model), true)
            && ! in_array($column, self::hiddenColumns($model), true);
    }

    public static function visibleColumns(string|Model $model, ?array $requested = null): array
    {
        $columns = $requested === null ? self::columns($model) : $requested;

        return array_values(array_filter($columns, function ($column) use ($model) {
            return is_string($column)
                && ! str_contains($column, '.')
                && self::isVisibleColumn($model, $column);
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
        $configured = self::modelConfig($model);
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
}
