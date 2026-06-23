<?php

declare(strict_types=1);

namespace Statisty\Contracts;

interface CacheRepositoryContract
{
    public function remember(string $key, int $ttl, callable $callback): mixed;
}
