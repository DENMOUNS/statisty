<?php

declare(strict_types=1);

namespace Statisty\Support\Builders;

final class FunnelBuilder
{
    private string $name;
    private array $steps = [];
    private array $options = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function step(string $id, array $definition): self
    {
        $this->steps[$id] = $definition;

        return $this;
    }

    public function options(array $opts): self
    {
        $this->options = array_merge($this->options, $opts);

        return $this;
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'steps' => $this->steps, 'options' => $this->options];
    }
}
