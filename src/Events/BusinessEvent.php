<?php

declare(strict_types=1);

namespace Statisty\Events;

final class BusinessEvent
{
    public function __construct(
        public readonly string $name,
        public readonly array $payload = [],
    ) {
    }
}
