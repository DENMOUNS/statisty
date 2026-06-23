<?php

declare(strict_types=1);

namespace Statisty\Console\Commands;

use Illuminate\Console\Command;

final class StatistyInstallCommand extends Command
{
    protected $signature = 'statisty:install';
    protected $description = 'Install Statisty (publish config, assets)';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'statisty-config', '--force' => true]);
        $this->info('Statisty configuration published.');

        return 0;
    }
}
