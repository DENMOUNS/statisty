<?php

declare(strict_types=1);

namespace Statisty\Dashboard;

final class DashboardRenderer
{
    public function render(Dashboard $dashboard): mixed
    {
        return $dashboard->toArray();
    }
}
