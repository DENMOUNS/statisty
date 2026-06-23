<?php

declare(strict_types=1);

namespace Statisty\Support\Builders;

final class ChartBuilder
{
    private string $name;
    private array $options = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function model(string $model): self
    {
        $this->options['model'] = $model;

        return $this;
    }

    public function period(string $period): self
    {
        $this->options['period'] = $period;

        return $this;
    }

    public function dateColumn(string $col): self
    {
        $this->options['date_column'] = $col;

        return $this;
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'options' => $this->options];
    }
}
