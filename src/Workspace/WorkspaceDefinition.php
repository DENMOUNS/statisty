<?php

declare(strict_types=1);

namespace Statisty\Workspace;

final class WorkspaceDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly array $models,
        public readonly int $pagination,
        public readonly WorkspaceOptions $options = new WorkspaceOptions(),
        public readonly array $customKpis = [],
        public readonly array $customCharts = [],
        public readonly array $customFunnels = [],
        public readonly array $customCohorts = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'models' => $this->models,
            'pagination' => $this->pagination,
            'options' => $this->options->toArray(),
            'custom_kpis' => array_map(
                fn (mixed $kpi): mixed => method_exists($kpi, 'toArray') ? $kpi->toArray() : $kpi,
                $this->customKpis,
            ),
            'custom_charts' => $this->mapDefinitions($this->customCharts),
            'custom_funnels' => $this->mapDefinitions($this->customFunnels),
            'custom_cohorts' => $this->mapDefinitions($this->customCohorts),
        ];
    }

    public function featureEnabled(string $feature): bool
    {
        return $this->options->featureEnabled($feature);
    }

    private function mapDefinitions(array $definitions): array
    {
        return array_map(
            fn (mixed $definition): mixed => method_exists($definition, 'toArray') ? $definition->toArray() : $definition,
            $definitions,
        );
    }
}
