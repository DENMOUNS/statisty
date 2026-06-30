<?php

declare(strict_types=1);

namespace Statisty\Workspace;

use Statisty\Dashboard\Dashboard;
use Statisty\Dashboard\DashboardBuilder;
use Statisty\Cohorts\CohortDefinition;
use Statisty\Funnels\FunnelDefinition;
use Statisty\Graph\ChartDefinition;
use Statisty\Graph\ChartType;
use Statisty\Metrics\KpiDefinition;
use Statisty\Metrics\MetricType;

final class WorkspaceBuilder
{
    private array $models = [];

    private int $pagination;

    private WorkspaceOptions $options;

    private array $customKpis = [];
    private array $customCharts = [];
    private array $customFunnels = [];
    private array $customCohorts = [];

    public function __construct(
        private readonly string $name,
        private readonly array $config = [],
        private readonly ?DashboardBuilder $dashboardBuilder = null,
    ) {
        $this->pagination = (int) ($config['pagination']['default'] ?? 500);
        $this->options = new WorkspaceOptions(
            cacheEnabled: (bool) ($config['cache']['enabled'] ?? true),
            cacheTtl: (int) ($config['cache']['ttl'] ?? 300),
            timezone: $config['workspace']['timezone'] ?? null,
            dateColumn: $config['charts']['default_date_column'] ?? 'created_at',
            features: $config['features'] ?? [],
        );
    }

    public function models(array $models): self
    {
        $this->models = $this->normalizeModels($models);

        return $this;
    }

    public function pagination(int $perPage): self
    {
        $max = (int) ($this->config['pagination']['max'] ?? 1000);
        $this->pagination = max(1, min($perPage, $max));

        return $this;
    }

    public function options(array $options): self
    {
        $this->options = $this->options->merge($options);

        return $this;
    }

    public function configure(callable $callback): self
    {
        $callback($this);

        return $this;
    }

    public function cache(bool $enabled = true): self
    {
        $this->options = $this->options->merge(['cache_enabled' => $enabled]);

        return $this;
    }

    public function withoutCache(): self
    {
        return $this->cache(false);
    }

    public function cacheFor(int $seconds): self
    {
        $this->options = $this->options->merge([
            'cache_enabled' => true,
            'cache_ttl' => max(0, $seconds),
        ]);

        return $this;
    }

    public function timezone(string $timezone): self
    {
        $this->options = $this->options->merge(['timezone' => $timezone]);

        return $this;
    }

    public function dateRange(?string $from = null, ?string $to = null, string $column = 'created_at'): self
    {
        $this->options = $this->options->merge([
            'date_from' => $from,
            'date_to' => $to,
            'date_column' => $column,
        ]);

        return $this;
    }

    public function filters(array $filters): self
    {
        $this->options = $this->options->merge(['filters' => $filters]);

        return $this;
    }

    public function filter(string $key, mixed $value): self
    {
        $this->options = $this->options->withFilter($key, $value);

        return $this;
    }

    public function feature(string $feature, bool $enabled = true): self
    {
        $this->options = $this->options->withFeature($feature, $enabled);

        return $this;
    }

    public function validateModels(bool $strict = true): self
    {
        $this->options = $this->options->merge(['strict_model_validation' => $strict]);

        return $this;
    }

    public function kpi(string $name, string $type, string $model, ?string $field = null, array $options = []): self
    {
        $this->customKpis[] = new KpiDefinition(
            name: $name,
            type: $type,
            model: ltrim($model, '\\'),
            field: $field,
            options: $options,
        );

        return $this;
    }

    public function count(string $model, ?string $name = null): self
    {
        return $this->kpi(
            name: $name ?? $this->metricName($model, 'Count'),
            type: MetricType::COUNT,
            model: $model,
        );
    }

    public function sum(string $model, string $field, ?string $name = null): self
    {
        return $this->kpi(
            name: $name ?? $this->metricName($model, 'Total ' . $field),
            type: MetricType::SUM,
            model: $model,
            field: $field,
        );
    }

    public function average(string $model, string $field, ?string $name = null): self
    {
        return $this->kpi(
            name: $name ?? $this->metricName($model, 'Average ' . $field),
            type: MetricType::AVERAGE,
            model: $model,
            field: $field,
        );
    }

    public function min(string $model, string $field, ?string $name = null): self
    {
        return $this->kpi(
            name: $name ?? $this->metricName($model, 'Minimum ' . $field),
            type: MetricType::MIN,
            model: $model,
            field: $field,
        );
    }

    public function max(string $model, string $field, ?string $name = null): self
    {
        return $this->kpi(
            name: $name ?? $this->metricName($model, 'Maximum ' . $field),
            type: MetricType::MAX,
            model: $model,
            field: $field,
        );
    }

    public function chart(string $name, string $type, string $model, ?string $field = null, array $options = []): self
    {
        $this->customCharts[] = new ChartDefinition($name, $type, ltrim($model, '\\'), $field, $options);

        return $this;
    }

    public function lineChart(string $model, ?string $field = null, ?string $name = null, array $options = []): self
    {
        return $this->chart($name ?? $this->metricName($model, 'Trend'), ChartType::LINE, $model, $field, $options);
    }

    public function funnel(string $name, string $model, array $steps, array $options = []): self
    {
        $this->customFunnels[] = new FunnelDefinition($name, ltrim($model, '\\'), $steps, $options);

        return $this;
    }

    public function cohort(string $name, string $model, array $options = []): self
    {
        $this->customCohorts[] = new CohortDefinition($name, ltrim($model, '\\'), $options);

        return $this;
    }

    public function definition(): WorkspaceDefinition
    {
        $this->validate();

        return new WorkspaceDefinition(
            name: $this->name,
            models: $this->models,
            pagination: $this->pagination,
            options: $this->options,
            customKpis: $this->customKpis,
            customCharts: $this->customCharts,
            customFunnels: $this->customFunnels,
            customCohorts: $this->customCohorts,
        );
    }

    public function build(): Dashboard
    {
        return ($this->dashboardBuilder ?? new DashboardBuilder())->build($this->definition());
    }

    private function validate(): void
    {
        if (trim($this->name) === '') {
            throw new InvalidWorkspaceConfiguration('Workspace name cannot be empty.');
        }

        if ($this->models === []) {
            throw new InvalidWorkspaceConfiguration('Workspace must contain at least one model.');
        }

        if (! $this->options->strictModelValidation) {
            return;
        }

        foreach ($this->models as $model) {
            if (! class_exists($model)) {
                throw new InvalidWorkspaceConfiguration("Workspace model [{$model}] does not exist.");
            }

            if (
                class_exists('Illuminate\\Database\\Eloquent\\Model')
                && ! is_subclass_of($model, 'Illuminate\\Database\\Eloquent\\Model')
            ) {
                throw new InvalidWorkspaceConfiguration("Workspace model [{$model}] must extend Eloquent Model.");
            }
        }
    }

    private function normalizeModels(array $models): array
    {
        $normalized = [];

        foreach ($models as $model) {
            if (! is_string($model) || trim($model) === '') {
                throw new InvalidWorkspaceConfiguration('Workspace models must be non-empty class strings.');
            }

            $normalized[] = ltrim(trim($model), '\\');
        }

        return array_values(array_unique($normalized));
    }

    private function metricName(string $model, string $suffix): string
    {
        $parts = explode('\\', ltrim($model, '\\'));
        $short = end($parts) ?: $model;
        $label = trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $short));

        return $label . ' ' . $suffix;
    }
}
