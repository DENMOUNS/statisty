<?php

declare(strict_types=1);

namespace Statisty\Workspace;

final class WorkspaceRegistry
{
    private array $workspaces = [];

    public function add(WorkspaceDefinition $workspace): self
    {
        $this->workspaces[$workspace->name] = $workspace;

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->workspaces);
    }

    public function get(string $name): ?WorkspaceDefinition
    {
        return $this->workspaces[$name] ?? null;
    }

    public function all(): array
    {
        return $this->workspaces;
    }
}
