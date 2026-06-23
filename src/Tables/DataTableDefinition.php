<?php

declare(strict_types=1);

namespace Statisty\Tables;

final class DataTableDefinition
{
    public function __construct(
        public readonly string $model,
        public readonly int $perPage,
        public readonly array $columns = [],
        public readonly array $filters = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'per_page' => $this->perPage,
            'columns' => $this->columns,
            'filters' => $this->filters,
        ];
    }
}
