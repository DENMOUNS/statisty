<?php

declare(strict_types=1);

namespace Statisty\Contracts;

use Statisty\Workspace\WorkspaceDefinition;

interface TableBuilderContract
{
    public function build(WorkspaceDefinition $workspace): array;
}
