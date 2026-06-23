<?php

declare(strict_types=1);

namespace Statisty\Support;

final class PaginationOptions
{
    public function __construct(
        public readonly int $perPage = 500,
    ) {
    }
}
