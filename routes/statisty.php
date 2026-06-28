<?php

use Illuminate\Support\Facades\Route;

$sharedMiddleware = [];
if (config('statisty.rate_limit.enabled', true)) {
    $sharedMiddleware[] = 'throttle:statisty';
}

$authMiddleware = config('statisty.security.enforce_authorization', true)
    ? ['statisty.auth']
    : [];

$apiRoutes = (array) config('statisty.routes.api', []);
if ((bool) ($apiRoutes['enabled'] ?? true)) {
    $apiPrefix      = trim((string) ($apiRoutes['prefix'] ?? 'api/statisty'), '/');
    $apiMiddleware  = array_values((array) ($apiRoutes['middleware'] ?? []));

    Route::prefix($apiPrefix)
        ->middleware(array_values(array_merge($apiMiddleware, $sharedMiddleware)))
        ->name('statisty.api.')
        ->group(function () use ($authMiddleware) {
            Route::get('health', function () {
                return response()->json(['status' => 'ok']);
            })->name('health');

            Route::middleware($authMiddleware)->group(function () {
                Route::get('discovery/{table}', [\Statisty\Http\Controllers\DiscoveryController::class, 'show'])->name('discovery.show');
                Route::get('charts/{model}',    [\Statisty\Http\Controllers\ChartController::class,     'show'])->name('charts.show');
                Route::get('tables/{model}',    [\Statisty\Http\Controllers\TableController::class,     'index'])->name('tables.index');
                Route::get('workspace/{name}',  [\Statisty\Http\Controllers\WorkspaceController::class, 'show'])->name('workspace.show');
                Route::get('metrics/{model}',   [\Statisty\Http\Controllers\MetricsController::class,   'index'])->name('metrics.index');
            });
        });
}

$webRoutes = (array) config('statisty.routes.web', []);
if ((bool) ($webRoutes['enabled'] ?? true)) {
    $webPrefix     = trim((string) ($webRoutes['prefix'] ?? 'web/statisty'), '/');
    $webMiddleware = array_values((array) ($webRoutes['middleware'] ?? ['web']));

    Route::prefix($webPrefix)
        ->middleware(array_values(array_merge($webMiddleware, $sharedMiddleware)))
        ->name('statisty.web.')
        ->group(function () use ($authMiddleware) {
            Route::middleware($authMiddleware)->group(function () {
                Route::get('dashboard', [\Statisty\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
                Route::get('workflow/{model}', [\Statisty\Http\Controllers\WorkflowController::class, 'workflow'])->name('workflow');
                Route::get('health',  [\Statisty\Http\Controllers\HealthController::class, 'health'])->name('health');
                Route::get('logs',    [\Statisty\Http\Controllers\LogsController::class,   'logs'])->name('logs');
                Route::get('jobs',    [\Statisty\Http\Controllers\JobsController::class,   'jobs'])->name('jobs');
                Route::get('docs',    [\Statisty\Http\Controllers\DocController::class,    'index'])->name('docs');
            });
        });
}