<?php

declare(strict_types=1);

namespace Statisty\Support;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DisplayRowFetcher
{
    /**
     * Récupère des lignes récentes pour un modèle à partir d'une liste de
     * "colonnes d'affichage" (cf. ModelSchema::displayColumns()), qui peut
     * contenir des entrées en notation pointée du type "relation.champ".
     * La clé primaire et les clés étrangères ne sont jamais incluses dans
     * le résultat retourné, même si elles sont nécessaires en interne pour
     * charger les relations.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fetch(string $modelClass, array $displayColumns, int $limit): array
    {
        if ($displayColumns === [] || ! method_exists($modelClass, 'query')) {
            return [];
        }

        try {
            $model = new $modelClass();
        } catch (\Throwable) {
            return [];
        }

        $relationNames = [];
        $own = [];

        foreach ($displayColumns as $col) {
            if (is_string($col) && str_contains($col, '.')) {
                [$rel, $attr] = explode('.', $col, 2);

                if (! ModelSchema::isVisibleRelationColumn($model, $rel, $attr)) {
                    continue;
                }

                $relation = ModelSchema::relation($model, $rel);
                if ($relation instanceof BelongsTo) {
                    $own[] = $relation->getForeignKeyName();
                }

                $relationNames[$rel] = true;
                continue;
            }

            $own[] = $col;
        }

        // La clé primaire est nécessaire à l'hydratation Eloquent et au
        // eager-loading des relations, même si elle n'apparaît jamais dans
        // le résultat retourné ci-dessous.
        $own[] = $model->getKeyName();
        $own = array_values(array_unique($own));

        try {
            $query = $modelClass::query()->select($own)->limit($limit);

            if (! empty($relationNames)) {
                $query->with(array_keys($relationNames));
            }

            if (in_array('created_at', $own, true)) {
                $query->latest('created_at');
            }

            return $query->get()
                ->map(function ($row) use ($displayColumns): array {
                    $out = [];

                    foreach ($displayColumns as $col) {
                        if (is_string($col) && str_contains($col, '.')) {
                            [$rel, $attr] = explode('.', $col, 2);
                            $related = $row->{$rel} ?? null;
                            $value = is_object($related) ? ($related->{$attr} ?? null) : null;
                            $out[$col] = is_scalar($value) || $value === null ? $value : json_encode($value);
                            continue;
                        }

                        $value = $row->{$col} ?? null;
                        $out[$col] = is_scalar($value) || $value === null ? $value : json_encode($value);
                    }

                    return $out;
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}