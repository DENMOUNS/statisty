<?php

declare(strict_types=1);

namespace Statisty\Events;

use Statisty\Workspace\WorkspaceDefinition;

final class DashboardBuilding
{
    public function __construct(public readonly WorkspaceDefinition $workspace)
    {
    }
}
