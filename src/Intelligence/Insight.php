<?php

declare(strict_types=1);

namespace Statisty\Intelligence;

final class Insight
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly array $context = [],
    ) {
    }
}
