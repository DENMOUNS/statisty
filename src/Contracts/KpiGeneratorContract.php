<?php

declare(strict_types=1);

namespace Statisty\Contracts;

use Statisty\Workspace\WorkspaceDefinition;

interface KpiGeneratorContract
{
    public function generate(WorkspaceDefinition $workspace): array;
}
