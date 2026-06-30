<?php

declare(strict_types=1);

namespace Statisty\Contracts;

use Statisty\Workspace\WorkspaceDefinition;

interface ChartGeneratorContract
{
    /**
     * @return array<int, array<string, mixed>|\Statisty\Graph\ChartDefinition>
     */
    public function generate(WorkspaceDefinition $workspace): array;
}
