<?php

declare(strict_types=1);

namespace Statisty\Cohorts;

final class CohortDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $model,
        public readonly array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->model,
            'options' => $this->options,
        ];
    }
}
