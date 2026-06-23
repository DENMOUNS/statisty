<?php

declare(strict_types=1);

namespace Statisty\Support\Builders;

final class CohortBuilder
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

    public function activityDate(string $col): self
    {
        $this->options['activity_date'] = $col;

        return $this;
    }

    public function options(array $opts): self
    {
        $this->options = array_merge($this->options, $opts);

        return $this;
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'options' => $this->options];
    }
}
