<?php

declare(strict_types=1);

namespace Statisty\Contracts;

use Statisty\Metrics\KpiDefinition;
use Statisty\Workspace\WorkspaceDefinition;

interface KpiCalculatorContract
{
    public function calculate(KpiDefinition $kpi, WorkspaceDefinition $workspace): KpiDefinition;
}
