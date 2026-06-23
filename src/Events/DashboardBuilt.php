<?php

declare(strict_types=1);

namespace Statisty\Events;

use Statisty\Dashboard\Dashboard;

final class DashboardBuilt
{
    public function __construct(public readonly Dashboard $dashboard)
    {
    }
}
