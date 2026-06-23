<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statisty\Tables\TableQueryBuilder;
use Statisty\Support\ApiError;
use Statisty\Support\ModelSchema;
use Statisty\Support\StatistyAuthorization;

final class TableController extends Controller
{
    public function index(Request $request, string $model)
    {
        try {
            if (ModelSchema::isDisabledModel($model)) {
                return ApiError::response('model_disabled', 403);
            }

            if (! ModelSchema::isQueryableModel($model)) {
                return ApiError::response('invalid_model', 404);
            }

            if (! StatistyAuthorization::allows($request, $model)) {
                return ApiError::response('unauthorized', 403);
            }

            $qb = new TableQueryBuilder($model::query());

            // apply SQL-level column selection to avoid leaking sensitive fields
            $qb->selectVisible($request->query('columns', null));

            $perPage = min((int) config('statisty.pagination.max', 1000), (int) $request->query('per_page', 50));
            $sort = $request->query('sort');
            $dir = $request->query('dir', 'asc');
            $search = $request->query('q');

            if ($sort) { $qb->applySorting($sort, $dir); }
            if ($search) { $qb->applySearch($request->query('columns', []), $search); }
            $filters = $request->query('filters', []);
            $qb->applyFilters($filters);

            $paginator = $qb->paginate($perPage);
            // Export support: csv or json (csv returns text/csv)
            $export = $request->query('export');
            if ($export === 'csv') {
                $chunk = (int) config('statisty.export.chunk_size', 1000);

                return \Statisty\Support\Exporter::streamCsv($qb->builder(), $request, 'statisty_export.csv', $chunk);
            }

            // Return resource collection; resource will check $request->route('model')
            return \Statisty\Http\Resources\TableRowResource::collection($paginator)->response();
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
            }

            return response()->json(['error' => 'server_error'], 500);
        }
    }
}
