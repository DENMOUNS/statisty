<?php

declare(strict_types=1);

return [
    'version' => env('STATISTY_VERSION', '1.0.0'),

    'workspace' => [
        'default' => 'default',
        'prefix' => env('STATISTY_WORKSPACE_PREFIX', 'statisty'),
    ],

    'routes' => [
        'api' => [
            'enabled' => env('STATISTY_API_ENABLED', true),
            'prefix' => env('STATISTY_API_PREFIX', 'api/statisty'),
            'middleware' => [],
        ],
        'web' => [
            'enabled' => env('STATISTY_WEB_ENABLED', true),
            'prefix' => env('STATISTY_WEB_PREFIX', 'web/statisty'),
            'middleware' => ['web'],
        ],
    ],

    'pagination' => [
        'default' => 500,
        'max' => 1000,
    ],

    'cache' => [
        'enabled' => env('STATISTY_CACHE', true),
        'ttl' => env('STATISTY_CACHE_TTL', 300),
        'prefix' => env('STATISTY_CACHE_PREFIX', 'statisty'),
        'version' => env('STATISTY_CACHE_VERSION', 'v1'),
    ],

    'features' => [
        'kpis' => true,
        'charts' => true,
        'tables' => true,
        'relationship_discovery' => true,
        'api_stats' => env('STATISTY_API_STATS', true),
    ],

    'allow_unlisted_models' => env('STATISTY_ALLOW_UNLISTED_MODELS', true),

    'models' => [
        // App\Models\Order::class => [
        //     'enabled' => true,
        //     'columns' => ['id', 'status', 'total', 'created_at'],
        //     'relations' => [
        //         'user' => ['columns' => ['id', 'email']],
        //     ],
        // ],
    ],

    'definitions' => [
        'kpis' => [],
        'charts' => [],
        'funnels' => [],
        'cohorts' => [],
    ],

    'hidden_columns' => [
        'password',
        'remember_token',
        'tokens',
        'api_token',
        'token',
        'secret',
        'secrets',
    ],

    'security' => [
        'enforce_authorization' => env('STATISTY_ENFORCE_AUTH', true),
        'hidden_columns' => [
            'password', 'remember_token', 'api_token', 'token', 'secret',
        ],
    ],

    'disabled_models' => [
        // App\Models\SensitiveModel::class,
    ],

    'charts' => [
        'default_type' => 'line',
        'default_date_column' => 'created_at',
    ],

    'rate_limit' => [
        'enabled' => env('STATISTY_RATE_ENABLED', true),
        'max' => env('STATISTY_RATE_MAX', 60),
        'minutes' => env('STATISTY_RATE_MINUTES', 1),
    ],

    // Authorization callback: either null, a callable, or a container-resolvable reference
    'authorization_callback' => env('STATISTY_AUTH_CALLBACK', null),

    // Export settings
    'export' => [
        'stream' => env('STATISTY_EXPORT_STREAM', true),
        'chunk_size' => env('STATISTY_EXPORT_CHUNK', 1000),
    ],

    // Default timezone for bucketing and date generation (null = app timezone)
    'timezone' => env('STATISTY_TIMEZONE', null),

    // Enable request validators (Request objects exist to be used by controllers)
    'validators' => [
        'enabled' => env('STATISTY_VALIDATORS', true),
    ],
];
