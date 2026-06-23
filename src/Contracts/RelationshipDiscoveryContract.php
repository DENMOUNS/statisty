<?php

declare(strict_types=1);

namespace Statisty\Contracts;

use Statisty\Workspace\WorkspaceDefinition;

interface RelationshipDiscoveryContract
{
    public function discover(WorkspaceDefinition $workspace): array;
}
