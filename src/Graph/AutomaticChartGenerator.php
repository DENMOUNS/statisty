<?php

declare(strict_types=1);

namespace Statisty\Graph;

use Statisty\Contracts\ChartGeneratorContract;
use Statisty\Support\ModelName;
use Statisty\Workspace\WorkspaceDefinition;

final class AutomaticChartGenerator implements ChartGeneratorContract
{
    public function generate(WorkspaceDefinition $workspace): array
    {
        $automatic = array_map(
            fn (string $model): ChartDefinition => new ChartDefinition(
                name: ModelName::label($model) . ' Trend',
                type: ChartType::LINE,
                model: $model,
                field: null,
                options: [
                    'x_axis' => $workspace->options->dateColumn,
                    'aggregation' => 'count',
                    'date_from' => $workspace->options->dateFrom,
                    'date_to' => $workspace->options->dateTo,
                    'filters' => $workspace->options->filters,
                    'timezone' => $workspace->options->timezone,
                ],
            ),
            $workspace->models,
        );

        return array_merge($automatic, $workspace->customCharts);
    }
    public function generateFromModel(string $model, ?string $value, string $dateColumn, array $options): array
    {
        // basic DB-agnostic stub: delegate to driver-specific method
        $driver = \DB::getDriverName();

        if ($driver === 'sqlite') {
            return $this->generateForSqlite($model, $value, $dateColumn, $options);
        }

        if ($driver === 'pgsql') {
            return $this->generateForPostgres($model, $value, $dateColumn, $options);
        }

        return $this->generateForMysql($model, $value, $dateColumn, $options);
    }

    private function generateForMysql(string $model, ?string $value, string $dateColumn, array $options): array
    {
        // placeholder: real impl should build SQL per driver
        return ['driver' => 'mysql', 'model' => $model, 'value' => $value, 'date_column' => $dateColumn, 'options' => $options];
    }

    private function generateForPostgres(string $model, ?string $value, string $dateColumn, array $options): array
    {
        return ['driver' => 'pgsql', 'model' => $model, 'value' => $value, 'date_column' => $dateColumn, 'options' => $options];
    }

    private function generateForSqlite(string $model, ?string $value, string $dateColumn, array $options): array
    {
        return ['driver' => 'sqlite', 'model' => $model, 'value' => $value, 'date_column' => $dateColumn, 'options' => $options];
    }
}
