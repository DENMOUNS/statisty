<?php
namespace Statisty\Http\Controllers;

use Statisty\Support\ModelSchema;

class ModelsController extends BaseDashboardController
{
    public function index()
    {
        $models = $this->dashboardModels();
        $details = [];
        foreach ($models as $model) {
            try {
                $instance = new $model;
                $details[] = [
                    'class' => $model,
                    'table' => $instance->getTable(),
                    'count' => $model::query()->count(),
                    'columns' => count(ModelSchema::columns($model)),
                ];
            } catch (\Throwable $e) {}
        }
        return view('statisty::models', array_merge($this->shellData('models'), [
            'models' => $details,
        ]));
    }
}
