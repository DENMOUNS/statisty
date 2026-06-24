<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statisty\Charts\ChartDataGenerator;
use Statisty\Support\ApiError;
use Statisty\Support\BusinessDefinitionRepository;
use Statisty\Support\ModelSchema;
use Statisty\Support\StatistyAuthorization;

final class ChartController extends Controller
{
    public function show(Request $request, string $model, ChartDataGenerator $generator)
    {
        try {
            // main logic
        
            $definitionName = $request->query('definition');
            $definition = is_string($definitionName) ? BusinessDefinitionRepository::get('charts', $definitionName) : null;
            if ($definition !== null) {
                $model = (string) ($definition['model'] ?? $model);
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
            $value = $definitionOptions['field'] ?? $request->query('value');
            $period = $request->query('period', $definitionOptions['period'] ?? 'day');
            $dateColumn = $definitionOptions['date_column'] ?? config('statisty.charts.default_date_column', 'created_at');

            $from = $request->query('date_from', $definitionOptions['from'] ?? null);
            $to = $request->query('date_to', $definitionOptions['to'] ?? null);

            $result = $generator->generateFromModel($model, $value ?: null, $dateColumn, array_merge($definitionOptions, [
                'period' => $period,
                'from' => $from,
                'to' => $to,
            ]));

            return response()->json($result);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
