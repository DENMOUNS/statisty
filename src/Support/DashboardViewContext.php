<?php

declare(strict_types=1);

namespace Statisty\Support;

use Statisty\Support\ModelName;
use Statisty\Support\ModelSchema;

final class DashboardViewContext
{
    public function dashboardModels(): array
    {
        $configured = (array) config('statisty.models', []);
        $models = [];

        foreach ($configured as $model => $options) {
            if (! is_string($model) || $model === '') {
                continue;
            }

            if (is_array($options) && ($options['enabled'] ?? true) === false) {
                continue;
            }

            if (ModelSchema::isQueryableModel($model)) {
                $models[] = ltrim($model, '\\');
            }
        }

        return array_values(array_unique($models));
    }

    public function shellData(string $active): array
    {
        $prefix = trim((string) config('statisty.routes.web.prefix', 'web/statisty'), '/');

        return [
            'activePage' => $active,
            'statistyNav' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => url($prefix . '/dashboard')],
                ['key' => 'health', 'label' => 'Health', 'url' => url($prefix . '/health')],
                ['key' => 'logs', 'label' => 'Logs', 'url' => url($prefix . '/logs')],
                ['key' => 'jobs', 'label' => 'Jobs', 'url' => url($prefix . '/jobs')],
                ['key' => 'docs', 'label' => 'API Docs', 'url' => url($prefix . '/docs')],
            ],
            'sidebarWorkflows' => array_map(
                fn (string $model): array => [
                    'label' => ModelName::label($model),
                    'class' => $model,
                    'url' => url($prefix . '/workflow/' . str_replace('\\', '%5C', $model)),
                ],
                $this->dashboardModels(),
            ),
        ];
    }

    public function apiUrl(string $endpoint, string $model, array $query = []): string
    {
        $prefix = trim((string) config('statisty.routes.api.prefix', 'api/statisty'), '/');
        $url = url($prefix . '/' . trim($endpoint, '/') . '/' . str_replace('\\', '%5C', $model));

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query);
    }

    public function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        return (string) $value;
    }
}
