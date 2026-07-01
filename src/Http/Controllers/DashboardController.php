<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Statisty\Core\StatistyManager;
use Statisty\Services\DashboardHeatmapService;
use Statisty\Services\DashboardModelCardService;

final class DashboardController extends BaseDashboardController
{
    public function index(
        Request $request,
        StatistyManager $statisty,
        DashboardHeatmapService $heatmapService,
        DashboardModelCardService $cardService,
    ) {
        try {
            $models = $this->dashboardModels();

            if ($models === []) {
                return view('statisty::dashboard', [
                    'appName' => config('app.name'),
                    'version' => config('statisty.version', '1.0.0'),
                    'workspace' => null,
                    'kpis' => [],
                    'models' => [],
                    ...$this->shellData('dashboard'),
                    'emptyMessage' => 'No Statisty models are configured yet.',
                ]);
            }

            $dashboard = $statisty
                ->workspace((string) config('statisty.workspace.default', 'default'))
                ->models($models)
                ->pagination((int) config('statisty.pagination.default', 25))
                ->build();

            $heatmap = $heatmapService->prepareHeatmap($models, (string) $request->query('year', 'recent'));

            return view('statisty::dashboard', [
                'appName' => config('app.name'),
                'version' => config('statisty.version', '1.0.0'),
                'workspace' => $dashboard->workspace,
                'kpis' => $dashboard->kpis,
                'models' => $cardService->buildModelCards($models),
                'heatmapData' => $heatmap['data'],
                'heatmapYears' => $heatmap['years'],
                'selectedHeatmapYear' => $heatmap['selectedYear'],
                'heatmapCaption' => $heatmapService->heatmapCaption($heatmap['selectedYear']),
                ...$this->shellData('dashboard'),
                'emptyMessage' => null,
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
