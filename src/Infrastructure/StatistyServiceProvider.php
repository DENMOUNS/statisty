<?php

declare(strict_types=1);

namespace Statisty\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Statisty\Contracts\CacheRepositoryContract;
use Statisty\Contracts\ChartGeneratorContract;
use Statisty\Contracts\KpiGeneratorContract;
use Statisty\Contracts\KpiCalculatorContract;
use Statisty\Contracts\RelationshipDiscoveryContract;
use Statisty\Contracts\TableBuilderContract;
use Statisty\Core\StatistyManager;
use Statisty\Dashboard\DashboardBuilder;
use Statisty\Discovery\EloquentRelationshipDiscovery;
use Statisty\Graph\AutomaticChartGenerator;
use Statisty\Infrastructure\Cache\LaravelCacheRepository;
use Statisty\Metrics\AutomaticKpiGenerator;
use Statisty\Metrics\EloquentKpiCalculator;
use Statisty\Tables\PaginatedTableBuilder;
use Statisty\Discovery\ModelProfiler;
use Statisty\Cache\ProfilingCache;
use Statisty\Discovery\TableInspector;
use Statisty\Discovery\RelationshipProfile;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

final class StatistyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists('\Illuminate\Foundation\Application')) {
            return;
        }

        $this->mergeConfigFrom(__DIR__ . '/../../config/statisty.php', 'statisty');

        // Core manager + bindings
        $this->app->singleton('statisty', fn (): StatistyManager => new StatistyManager(
            config: config('statisty', []),
            dashboardBuilder: $this->app->make(DashboardBuilder::class),
        ));

        $this->app->singleton(StatistyManager::class, fn (): StatistyManager => $this->app->make('statisty'));

        $this->app->bind(CacheRepositoryContract::class, LaravelCacheRepository::class);
        $this->app->bind(KpiGeneratorContract::class, AutomaticKpiGenerator::class);
        $this->app->bind(KpiCalculatorContract::class, EloquentKpiCalculator::class);
        $this->app->bind(ChartGeneratorContract::class, AutomaticChartGenerator::class);
        $this->app->bind(TableBuilderContract::class, PaginatedTableBuilder::class);
        $this->app->bind(RelationshipDiscoveryContract::class, EloquentRelationshipDiscovery::class);

        // Profiling cache and discovery helpers
        $this->app->singleton(ProfilingCache::class, function ($app) {
            $cache = $app['cache.store'] ?? $app['cache'];
            $prefix = config('statisty.workspace.prefix', 'statisty');

            return new ProfilingCache($cache, $prefix);
        });

        $this->app->singleton(ModelProfiler::class, function ($app) {
            $profiler = new ModelProfiler($app['db']);

            if ($app->bound(ProfilingCache::class)) {
                return new \Statisty\Discovery\CachedModelProfiler($profiler, $app->make(ProfilingCache::class));
            }

            return $profiler;
        });

        $this->app->singleton(TableInspector::class, fn($app) => new TableInspector($app['db']));
        $this->app->singleton(RelationshipProfile::class, fn($app) => new RelationshipProfile());
    }

    public function boot(): void
    {
        // register rate limiter for statisty endpoints
        if (config('statisty.rate_limit.enabled', true)) {
            RateLimiter::for('statisty', function (Request $request) {
                $max = (int) config('statisty.rate_limit.max', 60);

                return Limit::perMinute($max)->by($request->ip() ?: $request->fingerprint());
            });
        }
        // register package middleware alias if router available
        if (class_exists('\Illuminate\Routing\Router')) {
            try {
                $router = $this->app->make(\Illuminate\Routing\Router::class);
                $router->aliasMiddleware('statisty.auth', \Statisty\Http\Middleware\EnsureStatistyAuthorized::class);
            } catch (\Throwable) {
                // ignore if router not resolvable in this environment
            }
        }
        $this->loadRoutesFrom(__DIR__ . '/../../routes/statisty.php');

        // publish config, views and assets
        $this->publishes([
            __DIR__ . '/../../config/statisty.php' => config_path('statisty.php'),
        ], 'statisty-config');

        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/statisty'),
        ], 'statisty-views');

        $this->publishes([
            __DIR__ . '/../../resources/js/statisty.js' => public_path('vendor/statisty/statisty.js'),
            __DIR__ . '/../../resources/css/statisty.css' => public_path('vendor/statisty/statisty.css'),
            __DIR__ . '/../../resources/logo.png' => public_path('vendor/statisty/logo.png'),
            __DIR__ . '/../../resources/mascotte.png' => public_path('vendor/statisty/mascotte.png'),
        ], 'statisty-assets');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'statisty');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Statisty\Console\Commands\StatistyInstallCommand::class,
                \Statisty\Console\Commands\StatistyDiscoverCommand::class,
                \Statisty\Console\Commands\StatistyClearCacheCommand::class,
                \Statisty\Console\Commands\StatistyDoctorCommand::class,
            ]);
        }

        // Listen to SQL Queries to track slow queries
        if (config('statisty.features.slow_queries.enabled', true)) {
            try {
                $db = $this->app->make('db');
                $db->listen(function (\Illuminate\Database\Events\QueryExecuted $query) {
                    $threshold = (float) config('statisty.features.slow_queries.threshold_ms', 100);
                    if ($query->time >= $threshold) {
                        $sql = $query->sql;
                        if (str_contains($sql, 'statisty_slow_queries')) {
                            return;
                        }

                        // Trace where it comes from in user application code
                        $caller = 'Unknown';
                        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
                        foreach ($trace as $step) {
                            if (isset($step['file']) && !str_contains($step['file'], 'vendor\\') && !str_contains($step['file'], 'vendor/')) {
                                $caller = basename($step['file']) . ':' . $step['line'];
                                break;
                            }
                        }

                        $filePath = storage_path('logs/statisty_slow_queries.json');
                        $slowQueries = [];
                        if (file_exists($filePath)) {
                            try {
                                $content = file_get_contents($filePath);
                                $slowQueries = $content ? json_decode($content, true) ?: [] : [];
                            } catch (\Throwable $e) {}
                        }

                        $bindings = [];
                        foreach ($query->bindings as $binding) {
                            if ($binding instanceof \DateTimeInterface) {
                                $bindings[] = $binding->format('Y-m-d H:i:s');
                            } elseif (is_object($binding)) {
                                $bindings[] = get_class($binding);
                            } else {
                                $bindings[] = $binding;
                            }
                        }

                        $slowQueries[] = [
                            'sql' => $sql,
                            'bindings' => $bindings,
                            'time_ms' => round($query->time, 2),
                            'caller' => $caller,
                            'connection' => $query->connectionName,
                            'created_at' => date('Y-m-d H:i:s'),
                        ];

                        if (count($slowQueries) > 100) {
                            array_shift($slowQueries);
                        }

                        file_put_contents($filePath, json_encode($slowQueries, JSON_PRETTY_PRINT));
                    }
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}
