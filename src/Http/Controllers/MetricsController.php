<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statisty\Metrics\KpiDefinition;
use Statisty\Workspace\WorkspaceDefinition;
use Statisty\Workspace\WorkspaceOptions;
use Statisty\Contracts\KpiCalculatorContract;
use Statisty\Cache\ProfilingCache;
use Statisty\Metrics\FunnelCalculator;
use Statisty\Metrics\CohortAnalyzer;
use Statisty\Charts\ChartDataGenerator;
use Statisty\Metrics\AnomalyDetector;
use Statisty\Support\ApiError;
use Statisty\Support\BusinessDefinitionRepository;
use Statisty\Support\ModelSchema;
use Statisty\Support\StatistyAuthorization;

final class MetricsController extends Controller
{
    public function index(Request $request, string $model, KpiCalculatorContract $calculator, ?ProfilingCache $cache = null)
    {
        try {
            // If caller requested a live KPI type, calculate on the fly
            $type = $request->query('type');
            $definitionName = $request->query('definition');

            if ($type) {
                $definition = null;
                if (is_string($definitionName)) {
                    $bucket = match ($type) {
                        'funnel' => 'funnels',
                        'cohort' => 'cohorts',
                        default => 'kpis',
                    };
                    $definition = BusinessDefinitionRepository::get($bucket, $definitionName);
                    if ($definition === null) {
                        return ApiError::response('invalid_definition', 404);
                    }
                    $model = (string) ($definition['model'] ?? $model);
                    $type = (string) ($definition['type'] ?? $type);
                }

                if (ModelSchema::isDisabledModel($model)) {
                    return ApiError::response('model_disabled', 403);
                }

                if (! ModelSchema::isQueryableModel($model)) {
                    return ApiError::response('invalid_model', 404);
                }

                if (! StatistyAuthorization::allows($request, $model)) {
                    return ApiError::response('unauthorized', 403);
                }

                $definitionOptions = (array) ($definition['options'] ?? []);
                $options = [
                    'date_column' => $definitionOptions['date_column'] ?? $request->query('date_column', 'created_at'),
                    'date_from' => $definitionOptions['date_from'] ?? $request->query('date_from'),
                    'date_to' => $definitionOptions['date_to'] ?? $request->query('date_to'),
                    'filters' => $definitionOptions['filters'] ?? $request->query('filters', []),
                ];

                $workspaceOptions = new WorkspaceOptions(
                    cacheEnabled: true,
                    cacheTtl: (int) config('statisty.cache.ttl', 300),
                    timezone: null,
                    dateColumn: $options['date_column'] ?? 'created_at',
                    dateFrom: $options['date_from'] ?? null,
                    dateTo: $options['date_to'] ?? null,
                    filters: $options['filters'] ?? [],
                );

                $workspace = new WorkspaceDefinition($model, [$model], (int) config('statisty.pagination.default', 50), $workspaceOptions);

                if ($type === 'funnel') {
                    $steps = $definition['steps'] ?? json_decode((string) $request->query('steps', '[]'), true);
                    if (! is_array($steps) || count($steps) === 0) {
                        return ApiError::response('invalid_steps', 400);
                    }

                    $fc = new FunnelCalculator();
                    $res = $fc->run($model, $steps, [
                        'date_column' => $workspace->options->dateColumn,
                        'date_from' => $workspace->options->dateFrom,
                        'date_to' => $workspace->options->dateTo,
                        'filters' => $workspace->options->filters,
                        'distinct_by' => $definitionOptions['distinct_by'] ?? $request->query('distinct_by'),
                    ]);

                    return response()->json($res);
                }

                if ($type === 'cohort') {
                    $period = $definitionOptions['period'] ?? $request->query('period', 'week');
                    $periods = (int) ($definitionOptions['periods'] ?? $request->query('periods', 4));

                    $ca = new CohortAnalyzer();
                    $res = $ca->analyze($model, $request->query('date_column', 'created_at'), $period, $periods, [
                        'identity_column' => $definitionOptions['identity_column'] ?? $request->query('identity_column', 'user_id'),
                    ]);

                    return response()->json($res);
                }

                if ($type === 'anomaly') {
                    $field = $request->query('field', null);
                    $period = $request->query('period', 'day');
                    $threshold = (float) $request->query('threshold', 3.0);

                    $g = new ChartDataGenerator();
                    $series = $g->generateFromModel($model, $field, $request->query('date_column', 'created_at'), ['period' => $period]);

                    $labels = $series['labels'] ?? [];
                    $data = [];
                    if (! empty($series['datasets']) && isset($series['datasets'][0]['data'])) {
                        $data = $series['datasets'][0]['data'];
                    }

                    $det = new AnomalyDetector();
                    $anoms = $det->detect($labels, $data, $threshold);

                    // optionally persist alert if requested
                    if ($request->query('alert') == '1' && $cache !== null) {
                        $cacheKey = $model . ':anomaly:' . md5(serialize([$field, $period, $threshold]));
                        $cache->remember($cacheKey, fn() => $anoms, $workspace->options->cacheTtl);
                    }

                    return response()->json(['anomalies' => $anoms, 'series' => ['labels' => $labels, 'data' => $data]]);
                }
                $field = $definition['field'] ?? $definitionOptions['field'] ?? $request->query('field');

                $kpi = new KpiDefinition(name: ucfirst($type) . ' of ' . $model, type: $type, model: $model, field: $field, options: $options);

                // caching when enabled in workspace options and ProfilingCache available
                $ttl = $workspace->options->cacheTtl;
                $cacheKeyModel = $model . ':' . md5(serialize([$type, $field, $options]));

                if ($workspace->options->cacheEnabled && $cache !== null) {
                    $value = $cache->remember($cacheKeyModel, fn() => $calculator->calculate($kpi, $workspace), $ttl);
                    return response()->json($value->toArray());
                }

                $calculated = $calculator->calculate($kpi, $workspace);

                return response()->json($calculated->toArray());
            }

            // Otherwise return configured KPIs for model
            $kpis = config('statisty.kpis', []);

            $modelKpis = array_values(array_filter($kpis, fn($k) => ($k['model'] ?? null) === $model));

            return response()->json($modelKpis);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
