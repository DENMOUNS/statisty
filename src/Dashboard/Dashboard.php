<?php

declare(strict_types=1);

namespace Statisty\Dashboard;

use Statisty\Workspace\WorkspaceDefinition;

final class Dashboard
{
    public function __construct(
        public readonly WorkspaceDefinition $workspace,
        public readonly array $kpis = [],
        public readonly array $charts = [],
        public readonly array $tables = [],
        public readonly array $relationships = [],
        public readonly array $funnels = [],
        public readonly array $cohorts = [],
    ) {
    }

    public static function empty(WorkspaceDefinition $workspace): self
    {
        return new self(workspace: $workspace);
    }

    public function toArray(): array
    {
        return [
            'workspace' => $this->workspace->toArray(),
            'kpis' => $this->mapDefinitions($this->kpis),
            'charts' => $this->mapDefinitions($this->charts),
            'tables' => $this->mapDefinitions($this->tables),
            'relationships' => $this->relationships,
            'funnels' => $this->mapDefinitions($this->funnels),
            'cohorts' => $this->mapDefinitions($this->cohorts),
        ];
    }

    private function mapDefinitions(array $definitions): array
    {
        return array_map(
            fn (mixed $definition): mixed => method_exists($definition, 'toArray')
                ? $definition->toArray()
                : $definition,
            $definitions,
        );
    }
}
