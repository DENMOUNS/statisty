<?php

declare(strict_types=1);

namespace Statisty\Infrastructure\Cache;

use Statisty\Contracts\CacheRepositoryContract;

final class ArrayCacheRepository implements CacheRepositoryContract
{
    private array $items = [];

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($ttl <= 0) {
            return $callback();
        }

        if (array_key_exists($key, $this->items) && $this->items[$key]['expires_at'] >= time()) {
            return $this->items[$key]['value'];
        }

        $value = $callback();

        $this->items[$key] = [
            'expires_at' => time() + $ttl,
            'value' => $value,
        ];

        return $value;
    }
}
