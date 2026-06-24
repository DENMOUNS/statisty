<?php

use Illuminate\Support\Facades\Route;

$sharedMiddleware = [];
if (config('statisty.rate_limit.enabled', true)) {
    $sharedMiddleware[] = 'throttle:statisty';
}
if (config('statisty.security.enforce_authorization', true)) {
    $sharedMiddleware[] = 'statisty.auth';
}

$apiRoutes = (array) config('statisty.routes.api', []);
if ((bool) ($apiRoutes['enabled'] ?? true)) {
    Route::prefix(trim((string) ($apiRoutes['prefix'] ?? 'api/statisty'), '/'))
        ->middleware(array_values(array_merge((array) ($apiRoutes['middleware'] ?? []), $sharedMiddleware)))
        ->name('statisty.api.')
        ->group(function () {
            Route::get('health', function () {
                return response()->json(['status' => 'ok']);
            })->name('health');

            Route::get('discovery/{table}', [\Statisty\Http\Controllers\DiscoveryController::class, 'show'])->name('discovery.show');
            Route::get('charts/{model}', [\Statisty\Http\Controllers\ChartController::class, 'show'])->name('charts.show');
            Route::get('tables/{model}', [\Statisty\Http\Controllers\TableController::class, 'index'])->name('tables.index');
            Route::get('workspace/{name}', [\Statisty\Http\Controllers\WorkspaceController::class, 'show'])->name('workspace.show');
            Route::get('metrics/{model}', [\Statisty\Http\Controllers\MetricsController::class, 'index'])->name('metrics.index');
        });
}

$webRoutes = (array) config('statisty.routes.web', []);
if ((bool) ($webRoutes['enabled'] ?? true)) {
    Route::prefix(trim((string) ($webRoutes['prefix'] ?? 'web/statisty'), '/'))
        ->middleware(array_values(array_merge((array) ($webRoutes['middleware'] ?? ['web']), $sharedMiddleware)))
        ->name('statisty.web.')
        ->group(function () {
            Route::get('dashboard', [\Statisty\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
            Route::get('workflow/{model}', [\Statisty\Http\Controllers\DashboardController::class, 'workflow'])->name('workflow');
            Route::get('health', [\Statisty\Http\Controllers\DashboardController::class, 'health'])->name('health');
            Route::get('logs', [\Statisty\Http\Controllers\DashboardController::class, 'logs'])->name('logs');
            Route::get('jobs', [\Statisty\Http\Controllers\DashboardController::class, 'jobs'])->name('jobs');
        });
}
