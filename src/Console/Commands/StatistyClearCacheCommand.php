<?php

declare(strict_types=1);

namespace Statisty\Console\Commands;

use Illuminate\Console\Command;
use Statisty\Cache\ProfilingCache;

final class StatistyClearCacheCommand extends Command
{
    protected $signature = 'statisty:clear-cache {model}';
    protected $description = 'Clear profiling cache for a model';

    public function handle(ProfilingCache $cache): int
    {
        $model = $this->argument('model');
        $cache->forget($model);

        $this->info("Cleared profiling cache for {$model}");

        return 0;
    }
}
