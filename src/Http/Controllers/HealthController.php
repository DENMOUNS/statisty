<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;

final class HealthController extends BaseDashboardController
{
    public function health(Request $request)
    {
        try {
            \DB::connection()->enableQueryLog();
        } catch (\Throwable $e) {}

        $checks = $this->healthChecks();

        try {
            $queriesCount = count(\DB::connection()->getQueryLog());
            $checks[] = $this->healthCheck('Report SQL Queries', $queriesCount . ' queries executed', 'ready');
        } catch (\Throwable $e) {}

        return view('statisty::health', [
            'appName' => config('app.name'),
            'version' => config('statisty.version', '1.0.0'),
            'healthChecks' => $checks,
            ...$this->shellData('health'),
        ]);
    }

    private function healthChecks(): array
    {
        $apiEnabled = config('statisty.routes.api.enabled', true);
        $apiStatsEnabled = config('statisty.features.api_stats', true);
        $totalRequests = $apiStatsEnabled
            ? (int) \Illuminate\Support\Facades\Cache::get('statisty:total_requests_executed', 0)
            : 0;

        return [
            $this->healthCheck('Laravel', app()->version(), 'ready'),
            $this->healthCheck('Environment', (string) config('app.env', 'unknown'), config('app.debug') ? 'warning' : 'ready'),
            $this->healthCheck('Debug mode', config('app.debug') ? 'Enabled' : 'Disabled', config('app.debug') ? 'warning' : 'ready'),
            $this->databaseHealth(),
            $this->healthCheck('Cache driver', (string) config('cache.default', 'unknown'), 'ready'),
            $this->healthCheck('Queue connection', (string) config('queue.default', 'sync'), 'ready'),
            $this->storageHealth(),
            $this->healthCheck('Statisty models', (string) count($this->dashboardModels()) . ' active', count($this->dashboardModels()) > 0 ? 'ready' : 'warning'),
            $this->healthCheck('API Endpoints', $apiEnabled ? 'Enabled' : 'Disabled', $apiEnabled ? 'ready' : 'warning'),
            $this->healthCheck(
                'HTTP Requests Served',
                $apiStatsEnabled ? number_format($totalRequests) . ' requests' : 'Disabled',
                $apiStatsEnabled ? 'ready' : 'warning'
            ),
        ];
    }

    private function healthCheck(string $label, string $value, string $status = 'ready', ?string $detail = null): array
    {
        return compact('label', 'value', 'status', 'detail');
    }

    private function databaseHealth(): array
    {
        try {
            \DB::connection()->getPdo();

            return $this->healthCheck('Database', (string) config('database.default'), 'ready');
        } catch (\Throwable $exception) {
            return $this->healthCheck('Database', 'Unavailable', 'failed', $exception->getMessage());
        }
    }

    private function storageHealth(): array
    {
        $path = storage_path('logs');

        return $this->healthCheck(
            'Log storage',
            is_writable($path) ? 'Writable' : 'Not writable',
            is_writable($path) ? 'ready' : 'failed',
            $path,
        );
    }
}
