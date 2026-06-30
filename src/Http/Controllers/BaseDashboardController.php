<?php

declare(strict_types=1);

namespace Statisty\Http\Controllers;

use Illuminate\Routing\Controller;
use Statisty\Support\DashboardViewContext;

abstract class BaseDashboardController extends Controller
{
    protected readonly DashboardViewContext $viewContext;

    public function __construct(DashboardViewContext $viewContext)
    {
        $this->viewContext = $viewContext;
    }

    protected function dashboardModels(): array
    {
        return $this->viewContext->dashboardModels();
    }

    protected function shellData(string $active): array
    {
        return $this->viewContext->shellData($active);
    }

    protected function apiUrl(string $endpoint, string $model, array $query = []): string
    {
        return $this->viewContext->apiUrl($endpoint, $model, $query);
    }

    protected function timestamp(mixed $value): ?string
    {
        return $this->viewContext->timestamp($value);
    }
}
