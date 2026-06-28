<?php

declare(strict_types=1);

namespace Statisty\Discovery;

use Statisty\Contracts\RelationshipDiscoveryContract;
use Statisty\Workspace\WorkspaceDefinition;


final class EloquentRelationshipDiscovery implements RelationshipDiscoveryContract
{
    public function __construct(
        private readonly RelationshipProfile $profiler = new RelationshipProfile(),
    ) {}

    public function discover(WorkspaceDefinition $workspace): array
    {
        $relationships = [];

        foreach ($workspace->models as $model) {
            $relationships[$model] = $this->discoverForModel($model);
        }

        return $relationships;
    }

    private function discoverForModel(string $model): array
    {
        if (! class_exists($model)) {
            return [];
        }

        try {
            return $this->profiler->profileModel($model);
        } catch (\Throwable $e) {
            return [];
        }
    }
}