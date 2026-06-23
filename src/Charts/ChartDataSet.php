<?php

declare(strict_types=1);

namespace Statisty\Charts;

final class ChartDataSet
{
    public function __construct(
        private readonly string $label,
        private readonly array $data,
        private readonly ?string $type = null,
        private readonly array $meta = [],
    ) {
    }

    public function label(): string
    {
        return $this->label;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function toChartJs(): array
    {
        $dataset = array_merge([
            'label' => $this->label,
            'data' => array_values($this->data),
        ], $this->meta);

        if ($this->type !== null) {
            $dataset['type'] = $this->type;
        }

        return $dataset;
    }

    public function toApex(): array
    {
        // Apex expects series: [{ name: label, data: [...] }]
        return [
            'name' => $this->label,
            'data' => array_values($this->data),
        ];
    }
}
