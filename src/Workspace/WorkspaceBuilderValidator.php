<?php

declare(strict_types=1);

namespace Statisty\Workspace;

final class WorkspaceBuilderValidator
{
    public static function normalizeModels(array $models): array
    {
        $normalized = [];

        foreach ($models as $model) {
            if (! is_string($model) || trim($model) === '') {
                throw new InvalidWorkspaceConfiguration('Workspace models must be non-empty class strings.');
            }

            $normalized[] = ltrim(trim($model), '\\');
        }

        return array_values(array_unique($normalized));
    }

    public static function validate(string $name, array $models, WorkspaceOptions $options): void
    {
        if (trim($name) === '') {
            throw new InvalidWorkspaceConfiguration('Workspace name cannot be empty.');
        }

        if ($models === []) {
            throw new InvalidWorkspaceConfiguration('Workspace must contain at least one model.');
        }

        if (! $options->strictModelValidation) {
            return;
        }

        foreach ($models as $model) {
            if (! class_exists($model)) {
                throw new InvalidWorkspaceConfiguration("Workspace model [{$model}] does not exist.");
            }

            if (
                class_exists('Illuminate\\Database\\Eloquent\\Model') &&
                ! is_subclass_of($model, 'Illuminate\\Database\\Eloquent\\Model')
            ) {
                throw new InvalidWorkspaceConfiguration("Workspace model [{$model}] must extend Eloquent Model.");
            }
        }
    }

    public static function metricName(string $model, string $suffix): string
    {
        $parts = explode('\\', ltrim($model, '\\'));
        $short = end($parts) ?: $model;
        $label = trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $short));

        return $label . ' ' . $suffix;
    }
}
