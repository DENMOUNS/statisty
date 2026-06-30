<?php

use Illuminate\Support\Facades\Route;

$sharedMiddleware = static function (): array {
    $middleware = [];
    if (config('statisty.rate_limit.enabled', true)) {
        $middleware[] = 'throttle:statisty';
    }

    return $middleware;
};

$authMiddleware = static function (): array {
    return config('statisty.security.enforce_authorization', true)
        ? ['statisty.auth']
        : [];
};

$apiRoutes = static function (): array {
    return (array) config('statisty.routes.api', []);
};

$webRoutes = static function (): array {
    return (array) config('statisty.routes.web', []);
};

$modelConstraint = '.*';

$apiRoutesData = $apiRoutes();
if ((bool) ($apiRoutesData['enabled'] ?? true)) {
    $apiPrefix      = trim((string) ($apiRoutesData['prefix'] ?? 'api/statisty'), '/');
    $apiMiddleware  = array_values((array) ($apiRoutesData['middleware'] ?? []));

    Route::prefix($apiPrefix)
        ->middleware(array_values(array_merge($apiMiddleware, $sharedMiddleware())))
        ->name('statisty.api.')
        ->group(function () use ($authMiddleware, $modelConstraint) {
            Route::get('health', function () {
                return response()->json(['status' => 'ok']);
            })->name('health');

            Route::middleware($authMiddleware())->group(function () use ($modelConstraint) {
                Route::get('discovery/{table}', [\Statisty\Http\Controllers\DiscoveryController::class, 'show'])
                    ->where('table', $modelConstraint)
                    ->name('discovery.show');
                Route::get('charts/{model}',    [\Statisty\Http\Controllers\ChartController::class,     'show'])
                    ->where('model', $modelConstraint)
                    ->name('charts.show');
                Route::get('tables/{model}',    [\Statisty\Http\Controllers\TableController::class,     'index'])
                    ->where('model', $modelConstraint)
                    ->name('tables.index');
                Route::get('workspace/{name}',  [\Statisty\Http\Controllers\WorkspaceController::class, 'show'])
                    ->where('name', $modelConstraint)
                    ->name('workspace.show');
                Route::get('metrics/{model}',   [\Statisty\Http\Controllers\MetricsController::class,   'index'])
                    ->where('model', $modelConstraint)
                    ->name('metrics.index');
            });
        });
}

$webRoutesData = $webRoutes();
if ((bool) ($webRoutesData['enabled'] ?? true)) {
    $webPrefix     = trim((string) ($webRoutesData['prefix'] ?? 'web/statisty'), '/');
    $webMiddleware = array_values((array) ($webRoutesData['middleware'] ?? ['web']));

    Route::prefix($webPrefix)
        ->middleware(array_values(array_merge($webMiddleware, $sharedMiddleware())))
        ->name('statisty.web.')
        ->group(function () use ($authMiddleware, $modelConstraint) {
            Route::middleware($authMiddleware())->group(function () use ($modelConstraint) {
                Route::get('dashboard', [\Statisty\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
                Route::get('workflow/{model}', [\Statisty\Http\Controllers\WorkflowController::class, 'workflow'])
                    ->where('model', $modelConstraint)
                    ->name('workflow');
                Route::get('health',  [\Statisty\Http\Controllers\HealthController::class, 'health'])->name('health');
                Route::get('logs',    [\Statisty\Http\Controllers\LogsController::class,   'logs'])->name('logs');
                Route::get('jobs',    [\Statisty\Http\Controllers\JobsController::class,   'jobs'])->name('jobs');
                Route::get('docs',    [\Statisty\Http\Controllers\DocController::class,    'index'])->name('docs');
            });
        });
}