<?php

declare(strict_types=1);

namespace Statisty\Discovery;

use Statisty\Contracts\RelationshipDiscoveryContract;
use Statisty\Workspace\WorkspaceDefinition;

final class EloquentRelationshipDiscovery implements RelationshipDiscoveryContract
{
    public function discover(WorkspaceDefinition $workspace): array
    {
        $relationships = [];

        foreach ($workspace->models as $model) {
            $relationships[$model] = [];
        }

        return $relationships;
    }
}
