<?php

declare(strict_types=1);

namespace Statisty\Tables;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Statisty\Support\ModelSchema;

final class TableQueryBuilder
{
    private array $withRelations = [];

    public function __construct(private EloquentBuilder $query)
    {
    }

    public function excludeSensitive(array $columns): array
    {
        return ModelSchema::visibleColumns($this->query->getModel(), $columns);
    }

    public function visibleColumns(array $columns): array
    {
        // simple passthrough for now
        return $this->excludeSensitive($columns);
    }

    public function selectVisible(?array $columns = null): self
    {
        $model = $this->query->getModel();

        // Aucune colonne demandée explicitement : on part de la liste
        // "d'affichage" (sans id, sans clé étrangère brute) plutôt que de
        // toutes les colonnes visibles.
        if ($columns === null) {
            $columns = ModelSchema::displayColumns($model);
        }

        $relationNames = [];
        $own = [];

        foreach ($columns as $c) {
            if (is_string($c) && str_contains($c, '.')) {
                [$rel, $attr] = explode('.', $c, 2);
                if (ModelSchema::isVisibleRelationColumn($model, $rel, $attr)) {
                    $relationNames[] = $rel;
                    $relation = ModelSchema::relation($model, $rel);
                    if ($relation instanceof BelongsTo) {
                        $own[] = $relation->getForeignKeyName();
                    }
                }
                continue;
            }

            $own[] = $c;
        }

        $columns = ModelSchema::visibleColumns($model, $own);

        // La clé primaire doit toujours être sélectionnée en SQL : elle est
        // nécessaire à l'hydratation Eloquent et au eager-loading des
        // relations. Elle sera ensuite systématiquement masquée de la
        // sortie par TableRowResource, quel que soit le moyen par lequel
        // elle a été sélectionnée.
        $primaryKey = $model->getKeyName();
        if (! in_array($primaryKey, $columns, true)) {
            $columns[] = $primaryKey;
        }

        if (empty($columns)) {
            // fallback to select all — resource layer will still hide sensitive fields
            $this->query->select($model->getTable() . '.*');
        } else {
            $this->query->select($columns);
        }

        if (! empty($relationNames)) {
            $this->withRelations = array_values(array_unique($relationNames));
            try {
                $this->query->with($this->withRelations);
            } catch (\Throwable) {
                // ignore if relation names invalid
            }
        }

        return $this;
    }

    public function applySorting(string $column = null, string $direction = 'asc'): self
    {
        if ($column !== null && ModelSchema::isVisibleColumn($this->query->getModel(), $column)) {
            $this->query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
        }

        return $this;
    }

    public function applyFilters(array $filters): self
    {
        foreach ($filters as $col => $val) {
            if ($val === null || $val === '') {
                continue;
            }

            $model = $this->query->getModel();

            if (is_string($col) && str_contains($col, '.')) {
                [$rel, $attr] = explode('.', $col, 2);
                if (ModelSchema::isVisibleRelationColumn($model, $rel, $attr)) {
                    $this->query->whereHas($rel, fn($q) => $q->where($attr, $val));
                }
            } elseif (is_string($col) && ModelSchema::isVisibleColumn($model, $col)) {
                $this->query->where($col, $val);
            }
        }

        return $this;
    }

    public function applySearch(array $columns, ?string $term): self
    {
        if (! $term) {
            return $this;
        }

        $model = $this->query->getModel();
        $validColumns = array_values(array_filter($columns, function ($col) use ($model) {
            if (! is_string($col)) {
                return false;
            }

            if (str_contains($col, '.')) {
                [$rel, $attr] = explode('.', $col, 2);

                return ModelSchema::isVisibleRelationColumn($model, $rel, $attr);
            }

            return ModelSchema::isVisibleColumn($model, $col);
        }));

        if (empty($validColumns)) {
            return $this;
        }

        $this->query->where(function ($q) use ($validColumns, $term) {
            foreach ($validColumns as $col) {
                if (is_string($col) && str_contains($col, '.')) {
                    [$rel, $attr] = explode('.', $col, 2);
                    $q->orWhereHas($rel, fn($rq) => $rq->where($attr, 'like', "%{$term}%"));
                } else {
                    $q->orWhere($col, 'like', "%{$term}%");
                }
            }
        });

        return $this;
    }

    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        if (empty($this->query->getQuery()->orders)) {
            $this->applyDefaultSort();
        }

        return $this->query->paginate(max(1, $perPage));
    }

    private function applyDefaultSort(): void
    {
        $model = $this->query->getModel();
        $primaryKey = $model->getKeyName();
        $table = $model->getTable();

        if (Schema::hasColumn($table, 'created_at')) {
            $this->query->latest('created_at');
            return;
        }

        $this->query->latest($primaryKey);
    }

    public function get()
    {
        return $this->query->get();
    }

    public function builder(): EloquentBuilder
    {
        return $this->query;
    }
}