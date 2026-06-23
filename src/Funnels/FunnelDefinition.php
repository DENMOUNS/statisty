<?php

declare(strict_types=1);

namespace Statisty\Funnels;

final class FunnelDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $model,
        public readonly array $steps,
        public readonly array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->model,
            'steps' => $this->steps,
            'options' => $this->options,
        ];
    }
}
