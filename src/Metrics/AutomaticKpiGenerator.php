<?php

declare(strict_types=1);

namespace Statisty\Metrics;

use Statisty\Contracts\KpiGeneratorContract;
use Statisty\Support\ModelName;
use Statisty\Workspace\WorkspaceDefinition;

final class AutomaticKpiGenerator implements KpiGeneratorContract
{
    public function generate(WorkspaceDefinition $workspace): array
    {
        $automatic = array_map(
            fn (string $model): KpiDefinition => new KpiDefinition(
                name: ModelName::label($model) . ' Count',
                type: MetricType::COUNT,
                model: $model,
                options: [
                    'date_column' => $workspace->options->dateColumn,
                    'date_from' => $workspace->options->dateFrom,
                    'date_to' => $workspace->options->dateTo,
                    'filters' => $workspace->options->filters,
                    'timezone' => $workspace->options->timezone,
                ],
            ),
            $workspace->models,
        );

        return array_merge($automatic, $workspace->customKpis);
    }
}
