<?php

declare(strict_types=1);

namespace Statisty\Tables;

use Statisty\Contracts\TableBuilderContract;
use Statisty\Workspace\WorkspaceDefinition;

final class PaginatedTableBuilder implements TableBuilderContract
{
    public function build(WorkspaceDefinition $workspace): array
    {
        return array_map(
            fn (string $model): DataTableDefinition => new DataTableDefinition(
                model: $model,
                perPage: $workspace->pagination,
                filters: $workspace->options->filters,
            ),
            $workspace->models,
        );
    }
}
