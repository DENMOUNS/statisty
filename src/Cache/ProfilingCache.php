<?php

declare(strict_types=1);

namespace Statisty\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class ProfilingCache
{
    public function __construct(private CacheRepository $cache, private string $workspacePrefix = 'workspace')
    {
    }

    public function key(string $model): string
    {
        return sprintf('%s:profiling:%s', $this->workspacePrefix, ltrim($model, '\\'));
    }

    public function remember(string $model, callable $callback, int $ttl = 300)
    {
        $key = $this->key($model);

        if ($ttl <= 0) {
            return $this->cache->rememberForever($key, $callback);
        }

        return $this->cache->remember($key, $ttl, $callback);
    }

    public function forget(string $model): void
    {
        $this->cache->forget($this->key($model));
    }

    public function setWorkspacePrefix(string $prefix): void
    {
        $this->workspacePrefix = $prefix;
    }
}
