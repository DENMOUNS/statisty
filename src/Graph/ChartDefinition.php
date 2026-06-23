<?php

declare(strict_types=1);

namespace Statisty\Graph;

final class ChartDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $model,
        public readonly ?string $field = null,
        public readonly array $options = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'model' => $this->model,
            'field' => $this->field,
            'options' => $this->options,
        ];
    }
}
