<?php

declare(strict_types=1);

namespace Statisty\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepo;
use Statisty\Contracts\CacheRepositoryContract;

final class LaravelCacheRepository implements CacheRepositoryContract
{
    /**
     * Cache repository instance. Annotated with TaggedCache to help static
     * analyzers (Intelephense/PHPStan) understand the runtime taggable API.
     *
     * @var CacheRepo|\Illuminate\Cache\TaggedCache
     */
    private CacheRepo $cache;

    public function __construct(CacheRepo $cache)
    {
        $this->cache = $cache;
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $prefix = (string) config('statisty.cache.prefix', 'statisty');
        $version = (string) config('statisty.cache.version', 'v1');
        $cacheKey = sprintf('%s.%s.%s', $prefix, $version, $key);

        if (method_exists($this->cache, 'tags')) {
            try {
                // Help static analyzers (Intelephense/PHPStan) understand that
                // this runtime value supports the taggable API.
                /** @var \Illuminate\Cache\TaggedCache $taggedCache */
                $taggedCache = $this->cache;

                return $taggedCache->tags([$prefix])->remember($cacheKey, $ttl, $callback);
            } catch (\Throwable) {
                // Some cache stores expose tags but do not support them at runtime.
            }
        }

        return $this->cache->remember($cacheKey, $ttl, $callback);
    }
}
