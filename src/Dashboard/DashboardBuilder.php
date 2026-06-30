<?php

declare(strict_types=1);

namespace Statisty\Dashboard;

use Illuminate\Contracts\Container\BindingResolutionException;
use Statisty\Contracts\CacheRepositoryContract;
use Statisty\Contracts\ChartGeneratorContract;
use Statisty\Contracts\KpiCalculatorContract;
use Statisty\Contracts\KpiGeneratorContract;
use Statisty\Contracts\RelationshipDiscoveryContract;
use Statisty\Contracts\TableBuilderContract;
use Statisty\Discovery\EloquentRelationshipDiscovery;
use Statisty\Events\DashboardBuilding;
use Statisty\Events\DashboardBuilt;
use Statisty\Graph\AutomaticChartGenerator;
use Statisty\Infrastructure\Cache\ArrayCacheRepository;
use Statisty\Metrics\AutomaticKpiGenerator;
use Statisty\Metrics\EloquentKpiCalculator;
use Statisty\Support\DashboardCacheKey;
use Statisty\Tables\PaginatedTableBuilder;
use Statisty\Workspace\WorkspaceDefinition;

final class DashboardBuilder
{
    public function __construct(
        private readonly KpiGeneratorContract $kpiGenerator = new AutomaticKpiGenerator(),
        private readonly ChartGeneratorContract $chartGenerator = new AutomaticChartGenerator(),
        private readonly TableBuilderContract $tableBuilder = new PaginatedTableBuilder(),
        private readonly RelationshipDiscoveryContract $relationshipDiscovery = new EloquentRelationshipDiscovery(),
        private readonly KpiCalculatorContract $kpiCalculator = new EloquentKpiCalculator(),
        private readonly CacheRepositoryContract $cache = new ArrayCacheRepository(),
    ) {
    }

    public function build(WorkspaceDefinition $workspace): Dashboard
    {
        if (! $workspace->options->cacheEnabled) {
            return $this->buildDashboard($workspace);
        }

        $payload = $this->cache->remember(
            DashboardCacheKey::for($workspace),
            $workspace->options->cacheTtl,
            fn () => $this->buildDashboard($workspace)->toArray(),
        );

        if ($payload instanceof Dashboard) {
            return $payload;
        }

        if (is_array($payload)) {
            return self::dashboardFromArray($payload, $workspace);
        }

        return $this->buildDashboard($workspace);
    }

    private function buildDashboard(WorkspaceDefinition $workspace): Dashboard
    {
        $this->dispatch(new DashboardBuilding($workspace));

        $dashboard = new Dashboard(
            workspace: $workspace,
            kpis: $workspace->featureEnabled('kpis')
                ? $this->calculateKpis($this->kpiGenerator->generate($workspace), $workspace)
                : [],
            charts: $workspace->featureEnabled('charts')
                ? $this->chartGenerator->generate($workspace)
                : [],
            tables: $workspace->featureEnabled('tables')
                ? $this->tableBuilder->build($workspace)
                : [],
            relationships: $workspace->featureEnabled('relationship_discovery')
                ? $this->relationshipDiscovery->discover($workspace)
                : [],
            funnels: $workspace->customFunnels,
            cohorts: $workspace->customCohorts,
        );

        $this->dispatch(new DashboardBuilt($dashboard));

        return $dashboard;
    }

    private static function dashboardFromArray(array $payload, WorkspaceDefinition $workspace): Dashboard
    {
        return new Dashboard(
            workspace: $workspace,
            kpis: $payload['kpis'] ?? [],
            charts: $payload['charts'] ?? [],
            tables: $payload['tables'] ?? [],
            relationships: $payload['relationships'] ?? [],
            funnels: $payload['funnels'] ?? [],
            cohorts: $payload['cohorts'] ?? [],
        );
    }

    private function calculateKpis(array $kpis, WorkspaceDefinition $workspace): array
    {
        return array_map(
            fn (mixed $kpi): mixed => $kpi instanceof \Statisty\Metrics\KpiDefinition
                ? $this->kpiCalculator->calculate($kpi, $workspace)
                : $kpi,
            $kpis,
        );
    }

    private function dispatch(object $event): void
    {
        if (! function_exists('app') || ! function_exists('event')) {
            return;
        }

        try {
            if (! app()->bound('events')) {
                return;
            }

            event($event);
        } catch (BindingResolutionException) {
            return;
        }
    }
}
