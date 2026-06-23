<?php

use Illuminate\Support\Facades\Route;

$middleware = [];
if (config('statisty.rate_limit.enabled', true)) {
    $middleware[] = 'throttle:statisty';
}
if (config('statisty.security.enforce_authorization', true)) {
    $middleware[] = 'statisty.auth';
}

Route::prefix('statisty')->middleware($middleware)->group(function () {
    Route::get('health', function () { return response()->json(['status' => 'ok']); });
    Route::get('discovery/{table}', [\Statisty\Http\Controllers\DiscoveryController::class, 'show']);
    Route::get('charts/{model}', [\Statisty\Http\Controllers\ChartController::class, 'show']);
    Route::get('tables/{model}', [\Statisty\Http\Controllers\TableController::class, 'index']);
    Route::get('workspace/{name}', [\Statisty\Http\Controllers\WorkspaceController::class, 'show']);
    Route::get('metrics/{model}', [\Statisty\Http\Controllers\MetricsController::class, 'index']);
    Route::get('dashboard', [\Statisty\Http\Controllers\DashboardController::class, 'index']);
});
