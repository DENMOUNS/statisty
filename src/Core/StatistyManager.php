<?php

declare(strict_types=1);

namespace Statisty\Core;

use Statisty\Dashboard\DashboardBuilder;
use Statisty\Workspace\WorkspaceBuilder;

final class StatistyManager
{
    public function __construct(
        private readonly array $config = [],
        private readonly ?DashboardBuilder $dashboardBuilder = null,
    ) {
    }

    public function workspace(?string $name = null): WorkspaceBuilder
    {
        return new WorkspaceBuilder(
            name: $this->resolveWorkspaceName($name),
            config: $this->config,
            dashboardBuilder: $this->dashboardBuilder,
        );
    }

    private function resolveWorkspaceName(?string $name): string
    {
        return $name ?? $this->config['workspace']['default'] ?? 'default';
    }
}
