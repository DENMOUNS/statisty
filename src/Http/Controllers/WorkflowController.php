<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Http\Request;
use Statisty\Services\WorkflowService;
use Statisty\Support\DashboardViewContext;
use Statisty\Support\ModelSchema;

final class WorkflowController extends BaseDashboardController
{
    private readonly WorkflowService $workflowService;

    public function __construct(DashboardViewContext $viewContext, WorkflowService $workflowService)
    {
        parent::__construct($viewContext);

        $this->workflowService = $workflowService;
    }

    public function workflow(Request $request, string $model)
    {
        $modelClass = rawurldecode($model);
        if (! str_starts_with($modelClass, '\\')) {
            $modelClass = '\\' . $modelClass;
        }
        $modelClass = ltrim($modelClass, '\\');

        if (ModelSchema::isDisabledModel($modelClass)) {
            abort(403, 'Model is disabled.');
        }

        if (! ModelSchema::isQueryableModel($modelClass)) {
            abort(404, 'Model is not queryable.');
        }

        try {
            $workflowData = $this->workflowService->build($modelClass);

            return view('statisty::workflow', [
                'appName' => config('app.name'),
                'version' => config('statisty.version', '1.0.0'),
                ...$workflowData,
                ...$this->shellData('dashboard'),
            ]);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                abort(500, $e->getMessage());
            }

            abort(500, 'Server error');
        }
    }
}
