<?php

declare(strict_types=1);

namespace Statisty\Tables;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

        $relationNames = [];

        if ($columns === null) {
            $columns = ModelSchema::visibleColumns($model);
        } else {
            // detect relation columns like "relation.field" and collect relations to eager load
            $own = [];
            foreach ($columns as $c) {
                if (is_string($c) && str_contains($c, '.')) {
                    [$rel, $attr] = explode('.', $c, 2);
                    if (ModelSchema::isVisibleRelationColumn($model, $rel, $attr)) {
                        $relationNames[] = $rel;
                        $relation = ModelSchema::relation($model, $rel);
                        if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                            $own[] = $relation->getForeignKeyName();
                        }
                    }
                    continue;
                }

                $own[] = $c;
            }

            $columns = ModelSchema::visibleColumns($model, $own);
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
        return $this->query->paginate(max(1, $perPage));
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
