<?php

declare(strict_types=1);

namespace Statisty\Metrics;

final class KpiDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $model,
        public readonly ?string $field = null,
        public readonly array $options = [],
        public readonly mixed $value = null,
        public readonly string $status = 'pending',
        public readonly ?string $error = null,
    ) {
    }

    public function pending(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            model: $this->model,
            field: $this->field,
            options: $this->options,
            value: $this->value,
            status: 'pending',
            error: null,
        );
    }

    public function withValue(mixed $value): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            model: $this->model,
            field: $this->field,
            options: $this->options,
            value: $value,
            status: 'ready',
            error: null,
        );
    }

    public function withExtraOptions(array $extra): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            model: $this->model,
            field: $this->field,
            options: array_merge($this->options, $extra),
            value: $this->value,
            status: $this->status,
            error: $this->error,
        );
    }

    public function failed(string $error): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            model: $this->model,
            field: $this->field,
            options: $this->options,
            value: $this->value,
            status: 'failed',
            error: $error,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'model' => $this->model,
            'field' => $this->field,
            'options' => $this->options,
            'value' => $this->value,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }
}
