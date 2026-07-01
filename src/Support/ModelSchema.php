<?php

declare(strict_types=1);

namespace Statisty\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Statisty\Support\ModelSchemaCache;
use Statisty\Support\ModelSchemaRelationInspector;

final class ModelSchema
{
    public const DEFAULT_SENSITIVE = ['password', 'remember_token', 'tokens', 'api_token', 'token', 'secret', 'secrets'];
    public const STATUS_KEYWORDS = ['status', 'state', 'stage', 'phase', 'type', 'kind', 'category', 'role', 'priority', 'level'];
    public const NUMERIC_KEYWORDS = ['amount', 'total', 'price', 'quantity', 'value', 'points', 'sum', 'score', 'total_amount', 'subtotal', 'revenue', 'cost', 'fee', 'tax', 'discount', 'weight', 'balance', 'salary', 'rate', 'count'];
    public const DISPLAY_FIELD_PRIORITY = ['name', 'title', 'label', 'full_name', 'fullname', 'username', 'email', 'code', 'reference', 'slug'];

    public static function isQueryableModel(string $modelClass): bool
    {
        if (class_exists($modelClass, false)) {
            // Already loaded.
        } elseif (! class_exists($modelClass)) {
            return false;
        }

        return is_subclass_of($modelClass, Model::class)
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
        $class = self::modelClass($model);
        $models = (array) config('statisty.models', []);

        return isset($models[$class]) && is_array($models[$class]) ? $models[$class] : null;
    }

    public static function clearColumnsCache(): void
    {
        ModelSchemaCache::clearColumnsCache();
    }

    public static function clearHiddenColumnsCache(): void
    {
        ModelSchemaCache::clearHiddenColumnsCache();
    }

    public static function clearBelongsToCache(): void
    {
        ModelSchemaCache::clearBelongsToCache();
    }

    public static function columns(string|Model $model): array
    {
        return ModelSchemaCache::columns($model);
    }

    public static function hiddenColumns(string|Model $model): array
    {
        return ModelSchemaCache::hiddenColumns($model);
    }

    public static function isVisibleColumn(string|Model $model, string $column): bool
    {
        return ModelSchemaRelationInspector::isVisibleColumn($model, $column);
    }

    public static function visibleColumns(string|Model $model, ?array $requested = null): array
    {
        return ModelSchemaRelationInspector::visibleColumns($model, $requested);
    }

    public static function primaryKey(string|Model $model): string
    {
        $instance = self::resolveModelInstance($model);

        return $instance instanceof Model ? $instance->getKeyName() : 'id';
    }

    public static function defaultSensitive(): array
    {
        return self::DEFAULT_SENSITIVE;
    }

    public static function displayFieldPriority(): array
    {
        return self::DISPLAY_FIELD_PRIORITY;
    }

    public static function belongsToRelations(string|Model $model): array
    {
        return ModelSchemaCache::belongsToRelations($model);
    }

    public static function foreignKeyColumns(string|Model $model): array
    {
        return ModelSchemaCache::foreignKeyColumns($model);
    }

    public static function relationDisplayField(string $relatedModelClass): ?string
    {
        return ModelSchemaRelationInspector::relationDisplayField($relatedModelClass);
    }

    public static function displayColumns(string|Model $model): array
    {
        return ModelSchemaRelationInspector::displayColumns($model);
    }

    public static function relation(Model $model, string $name): ?Relation
    {
        return ModelSchemaRelationInspector::relation($model, $name);
    }

    public static function isVisibleRelationColumn(Model $model, string $relation, string $column): bool
    {
        return ModelSchemaRelationInspector::isVisibleRelationColumn($model, $relation, $column);
    }

    public static function semanticStatusColumns(string|Model $model): array
    {
        $visible = self::visibleColumns($model);

        return array_values(array_filter($visible, function (string $col): bool {
            return in_array(strtolower($col), self::STATUS_KEYWORDS, true)
                || str_ends_with(strtolower($col), '_status')
                || str_ends_with(strtolower($col), '_state')
                || str_ends_with(strtolower($col), '_type')
                || str_ends_with(strtolower($col), '_stage');
        }));
    }

    public static function semanticNumericColumns(string|Model $model): array
    {
        $visible = self::visibleColumns($model);

        return array_values(array_filter($visible, function (string $col): bool {
            $lower = strtolower($col);
            foreach (self::NUMERIC_KEYWORDS as $keyword) {
                if ($lower === $keyword || str_contains($lower, $keyword)) {
                    return true;
                }
            }

            return false;
        }));
    }

    public static function modelClass(string|Model $model): string
    {
        return is_string($model) ? ltrim($model, '\\') : $model::class;
    }

    public static function resolveModelInstance(string|Model $model): ?Model
    {
        if (is_string($model)) {
            $class = self::modelClass($model);

            return class_exists($class, false) || class_exists($class)
                ? new $class()
                : null;
        }

        return $model;
    }

    public static function shouldBypassColumnsCache(): bool
    {
        if (! function_exists('app')) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return true;
        }

        if (config('statisty.disable_columns_cache', false)) {
            return true;
        }

        return app()->has('octane');
    }
}
