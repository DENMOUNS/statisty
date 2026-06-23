<?php

declare(strict_types=1);

namespace Statisty\Graph;

interface ChartDataGeneratorInterface
{
    public function generateFromModel(string $model, ?string $value, string $dateColumn, array $options): array;
}
