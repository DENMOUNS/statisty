<?php

declare(strict_types=1);

namespace Statisty\Discovery;

use Statisty\Cache\ProfilingCache;

final class CachedModelProfiler
{
    private ModelProfiler $profiler;
    private ProfilingCache $cache;

    public function __construct(ModelProfiler $profiler, ProfilingCache $cache)
    {
        $this->profiler = $profiler;
        $this->cache = $cache;
    }

    public function profile(string $table): array
    {
        return $this->cache->remember($table, fn() => $this->profiler->profile($table), config('statisty.cache.ttl', 300));
    }
}
