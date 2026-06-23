<?php

declare(strict_types=1);

namespace Statisty\Console\Commands;

use Illuminate\Console\Command;
use Statisty\Discovery\ModelProfiler;

final class StatistyDiscoverCommand extends Command
{
    protected $signature = 'statisty:discover {table}';
    protected $description = 'Discover a table schema and print profile';

    public function handle(): int
    {
        $table = $this->argument('table');

        $profiler = app(\Statisty\Discovery\ModelProfiler::class);

        $profile = $profiler->profile($table);

        $this->line(json_encode($profile, JSON_PRETTY_PRINT));

        return 0;
    }
}
