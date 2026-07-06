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

    'response_compression' => [
        'enabled' => env('STATISTY_RESPONSE_COMPRESSION', true),
        'level' => env('STATISTY_RESPONSE_COMPRESSION_LEVEL', 6),
    ],

    'disable_columns_cache' => env('STATISTY_DISABLE_COLUMNS_CACHE', false),

    'features' => [
        'kpis' => true,
        'charts' => true,
        'tables' => true,
        'relationship_discovery' => true,
        'api_stats' => env('STATISTY_API_STATS', true),
        'slow_queries' => [
            'enabled' => env('STATISTY_SLOW_QUERIES_ENABLED', true),
            'threshold_ms' => env('STATISTY_SLOW_QUERIES_THRESHOLD', 100), // ms
            'retention_hours' => env('STATISTY_SLOW_QUERIES_RETENTION_HOURS', 24),
        ],
    ],

    'allow_unlisted_models' => env('STATISTY_ALLOW_UNLISTED_MODELS', true),

    'models' => [
        // App\Models\Order::class => [
        //     'enabled' => true,
        //     'columns' => ['id', 'status', 'total', 'created_at'],
        //     'relations' => [
        //         'user' => ['columns' => ['id', 'email']],
        //     ],
        //     // Colonnes en "xxx_id" à NE PAS masquer malgré la convention
        //     // de nommage (ex: un identifiant externe qui n'est pas une FK) :
        //     'expose_id_columns' => ['external_id'],
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
        // Masque toute colonne se terminant par "_id" (hors clé primaire)
        // qui n'est rattachée à aucune relation Eloquent résolue. Empêche
        // la fuite de clés étrangères "orphelines" (sans belongsTo() déclaré).
        'hide_unmapped_foreign_keys' => env('STATISTY_HIDE_UNMAPPED_FK', true),
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

    /*
     * Détermine quelle source de vues a la priorité lorsque des vues package
     * ont été publiées dans l'application (resources/views/vendor/statisty).
     * - false (par défaut) : utiliser les vues fournies par le package dans
     *   `vendor/statisty` / `resources/views` du package (PRÉFÉRÉ pour les
     *   environnements de test ou CI où l'on ne veut pas que les copies
     *   publiées remplacent automatiquement les vues du package).
     * - true : préférer les vues publiées dans l'application (comportement
     *   Laravel par défaut quand on publie des vues).
     */
    'prefer_published_views' => env('STATISTY_PREFER_PUBLISHED_VIEWS', false),
];