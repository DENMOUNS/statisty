<?php

declare(strict_types=1);

namespace Statisty\Workspace;

final class WorkspaceOptions
{
    public function __construct(
        public readonly bool $cacheEnabled = true,
        public readonly int $cacheTtl = 300,
        public readonly ?string $timezone = null,
        public readonly ?string $dateColumn = 'created_at',
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly array $filters = [],
        public readonly array $features = [],
        public readonly bool $strictModelValidation = false,
    ) {
    }

    public function merge(array $options): self
    {
        return new self(
            cacheEnabled: $options['cache_enabled'] ?? $this->cacheEnabled,
            cacheTtl: $options['cache_ttl'] ?? $this->cacheTtl,
            timezone: $options['timezone'] ?? $this->timezone,
            dateColumn: $options['date_column'] ?? $this->dateColumn,
            dateFrom: $options['date_from'] ?? $this->dateFrom,
            dateTo: $options['date_to'] ?? $this->dateTo,
            filters: array_merge($this->filters, $options['filters'] ?? []),
            features: array_replace($this->features, $options['features'] ?? []),
            strictModelValidation: $options['strict_model_validation'] ?? $this->strictModelValidation,
        );
    }

    public function withFilter(string $key, mixed $value): self
    {
        return $this->merge([
            'filters' => array_merge($this->filters, [$key => $value]),
        ]);
    }

    public function withFeature(string $feature, bool $enabled): self
    {
        return $this->merge([
            'features' => array_replace($this->features, [$feature => $enabled]),
        ]);
    }

    public function featureEnabled(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? true);
    }

    public function toArray(): array
    {
        return [
            'cache_enabled' => $this->cacheEnabled,
            'cache_ttl' => $this->cacheTtl,
            'timezone' => $this->timezone,
            'date_column' => $this->dateColumn,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'filters' => $this->filters,
            'features' => $this->features,
            'strict_model_validation' => $this->strictModelValidation,
        ];
    }
}
