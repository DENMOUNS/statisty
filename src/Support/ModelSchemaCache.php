<?php

declare(strict_types=1);

namespace Statisty\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ModelSchemaCache
{
    private static array $columnsCache = [];
    private static array $hiddenColumnsCache = [];
    private static array $belongsToCache = [];
    private static array $foreignKeyCache = [];

    public static function clearColumnsCache(): void
    {
        self::$columnsCache = [];
    }

    public static function clearHiddenColumnsCache(): void
    {
        self::$hiddenColumnsCache = [];
    }

    public static function clearBelongsToCache(): void
    {
        self::$belongsToCache = [];
        self::$foreignKeyCache = [];
    }

    public static function columns(string|Model $model): array
    {
        $class = ModelSchema::modelClass($model);

        if (! ModelSchema::shouldBypassColumnsCache() && isset(self::$columnsCache[$class])) {
            return self::$columnsCache[$class];
        }

        $instance = ModelSchema::resolveModelInstance($model);
        if ($instance === null) {
            if (! ModelSchema::shouldBypassColumnsCache()) {
                self::$columnsCache[$class] = [];
            }

            return [];
        }

        try {
            $columns = $instance->getConnection()->getSchemaBuilder()->getColumnListing($instance->getTable());
        } catch (\Throwable) {
            $columns = [];
        }

        if (! ModelSchema::shouldBypassColumnsCache()) {
            self::$columnsCache[$class] = $columns;
        }

        return $columns;
    }

    public static function hiddenColumns(string|Model $model): array
    {
        $class = ModelSchema::modelClass($model);

        if (isset(self::$hiddenColumnsCache[$class])) {
            return self::$hiddenColumnsCache[$class];
        }

        $instance = ModelSchema::resolveModelInstance($model);
        $modelHidden = $instance instanceof Model ? (array) $instance->getHidden() : [];

        return self::$hiddenColumnsCache[$class] = array_values(array_unique(array_merge(
            ['password', 'remember_token', 'tokens', 'api_token', 'token', 'secret', 'secrets'],
            (array) config('statisty.hidden_columns', []),
            (array) config('statisty.security.hidden_columns', []),
            $modelHidden,
        )));
    }

    /**
     * Détecte les relations BelongsTo déclarées sur un modèle, y compris
     * celles héritées d'une classe parente ou définies dans un trait.
     * On n'exclut plus que les méthodes internes du framework Eloquent.
     */
    public static function belongsToRelations(string|Model $model): array
    {
        $class = ModelSchema::modelClass($model);

        if (! ModelSchema::shouldBypassColumnsCache() && isset(self::$belongsToCache[$class])) {
            return self::$belongsToCache[$class];
        }

        $instance = ModelSchema::resolveModelInstance($model);
        if (! $instance instanceof Model) {
            return [];
        }

        $results = [];

        try {
            $ref = new \ReflectionClass($instance);

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $declaringClass = $method->getDeclaringClass()->getName();

                // On exclut uniquement les méthodes internes du framework
                // (Illuminate\Database\Eloquent\Model et consorts), plus
                // les relations héritées d'une classe parente ou définies
                // dans un trait restent détectées.
                if (str_starts_with($declaringClass, 'Illuminate\\')) {
                    continue;
                }

                if ($method->getNumberOfRequiredParameters() > 0 || $method->isStatic() || $method->isAbstract()) {
                    continue;
                }

                $relation = ModelSchema::relation($instance, $method->getName());

                if (! $relation instanceof BelongsTo) {
                    continue;
                }

                $results[$method->getName()] = [
                    'column' => $relation->getForeignKeyName(),
                    'related' => get_class($relation->getRelated()),
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        if (! ModelSchema::shouldBypassColumnsCache()) {
            self::$belongsToCache[$class] = $results;
        }

        return $results;
    }

    /**
     * Retourne toutes les colonnes considérées comme des clés étrangères :
     * - celles rattachées à une relation BelongsTo résolue (fiable),
     * - + par défaut, celles qui suivent la convention "xxx_id" mais sans
     *   relation Eloquent déclarée (garde-fou pour éviter toute fuite de
     *   FK "orpheline"). Désactivable via statisty.security.hide_unmapped_foreign_keys.
     */
    public static function foreignKeyColumns(string|Model $model): array
    {
        $class = ModelSchema::modelClass($model);

        if (! ModelSchema::shouldBypassColumnsCache() && isset(self::$foreignKeyCache[$class])) {
            return self::$foreignKeyCache[$class];
        }

        $relationBased = array_values(array_column(self::belongsToRelations($class), 'column'));

        if (! config('statisty.security.hide_unmapped_foreign_keys', true)) {
            $result = array_values(array_unique($relationBased));

            if (! ModelSchema::shouldBypassColumnsCache()) {
                self::$foreignKeyCache[$class] = $result;
            }

            return $result;
        }

        $pk = ModelSchema::primaryKey($class);
        $exposed = (array) (ModelSchema::modelConfig($class)['expose_id_columns'] ?? []);

        $heuristic = array_values(array_filter(self::columns($class), function (string $col) use ($pk, $exposed): bool {
            if ($col === $pk || $col === 'id' || in_array($col, $exposed, true)) {
                return false;
            }

            return (bool) preg_match('/_id$/', $col);
        }));

        $result = array_values(array_unique(array_merge($relationBased, $heuristic)));

        if (! ModelSchema::shouldBypassColumnsCache()) {
            self::$foreignKeyCache[$class] = $result;
        }

        return $result;
    }
}