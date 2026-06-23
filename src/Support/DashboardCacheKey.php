<?php

declare(strict_types=1);

namespace Statisty\Support;

use Statisty\Workspace\WorkspaceDefinition;

final class DashboardCacheKey
{
    public static function for(WorkspaceDefinition $workspace): string
    {
        $version = config('statisty.cache.version', config('statisty.cache.version', 'v1')) ?? 'v1';

        return sprintf('statisty.%s.dashboard.%s', $version, sha1(json_encode($workspace->toArray()) ?: $workspace->name));
    }
}
