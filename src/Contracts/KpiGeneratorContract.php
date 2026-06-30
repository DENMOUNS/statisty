<?php

declare(strict_types=1);

namespace Statisty\Contracts;

use Statisty\Workspace\WorkspaceDefinition;

interface KpiGeneratorContract
{
    /**
     * @return array<int, array<string, mixed>|\Statisty\Metrics\KpiDefinition>
     */
    public function generate(WorkspaceDefinition $workspace): array;
}
