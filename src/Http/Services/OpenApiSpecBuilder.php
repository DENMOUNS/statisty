<?php

declare(strict_types=1);

namespace Statisty\Http\Services;

final class OpenApiSpecBuilder
{
    public function build(array $apiDocs): array
    {
        $paths = [];

        foreach ($apiDocs as $route) {
            $uri = '/' . ltrim((string) preg_replace('/\{([a-zA-Z0-9_]+)\?\}/', '{$1}', $route['uri']), '/');

            $parameters = array_map(static fn (array $p): array => [
                'name' => $p['name'],
                'in' => 'path',
                'required' => $p['required'],
                'schema' => ['type' => 'string'],
            ], $route['params']);

            $requestBody = null;
            if (! empty($route['validation_rules'])) {
                $properties = [];
                $required = [];

                foreach ($route['validation_rules'] as $rule) {
                    $properties[$rule['field']] = [
                        'type' => 'string',
                        'description' => $rule['rules'],
                    ];

                    if ($rule['required']) {
                        $required[] = $rule['field'];
                    }
                }

                $requestBody = [
                    'content' => [
                        'application/json' => [
                            'schema' => array_filter([
                                'type' => 'object',
                                'properties' => $properties,
                                'required' => $required !== [] ? $required : null,
                            ]),
                        ],
                    ],
                ];
            }

            foreach ($route['methods'] as $method) {
                $lowerMethod = strtolower($method);
                if (! in_array($lowerMethod, ['get', 'post', 'put', 'patch', 'delete', 'options'], true)) {
                    continue;
                }

                $operation = array_filter([
                    'summary' => mb_strimwidth($route['description'], 0, 120, '...'),
                    'description' => $route['description'],
                    'operationId' => $this->operationId($lowerMethod, $route['uri']),
                    'tags' => [$this->controllerTag($route['action'])],
                    'deprecated' => $route['is_deprecated'] ?: null,
                    'parameters' => $parameters !== [] ? $parameters : null,
                    'requestBody' => in_array($lowerMethod, ['post', 'put', 'patch'], true) ? $requestBody : null,
                    'responses' => [
                        '200' => ['description' => 'Successful response'],
                        '401' => ['description' => 'Unauthorized'],
                        '404' => ['description' => 'Not found'],
                    ],
                ], static fn ($v) => $v !== null);

                $paths[$uri][$lowerMethod] = $operation;
            }
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => (string) config('app.name', 'Application') . ' API',
                'version' => (string) config('statisty.version', '1.0.0'),
                'description' => 'Documentation API générée automatiquement par Statisty.',
            ],
            'servers' => [
                ['url' => rtrim(url('/'), '/')],
            ],
            'paths' => $paths,
        ];
    }

    private function operationId(string $method, string $uri): string
    {
        $slug = (string) preg_replace('/[^a-zA-Z0-9]+/', '_', trim($uri, '/'));

        return $method . '_' . trim($slug, '_');
    }

    private function controllerTag(string $action): string
    {
        if (! str_contains($action, '@')) {
            return 'default';
        }

        [$controller] = explode('@', $action);
        $parts = explode('\\', $controller);

        return str_replace('Controller', '', end($parts)) ?: 'default';
    }
}
