<?php

declare(strict_types=1);

namespace Statisty\Console\Commands;

use Illuminate\Console\Command;
use Statisty\Core\StatistyManager;

final class StatistyDoctorCommand extends Command
{
    protected $signature = 'statisty:doctor';

    protected $description = 'Verify Statisty configuration, policies, assets and exposed models';

    public function handle(StatistyManager $manager): int
    {
        $this->info('Running Statisty doctor checks...');

        $config = config('statisty', []);

        $ok = true;

        // Check config version
        if (empty($config['version'])) {
            $this->error('Missing statisty.version in config/statisty.php');
            $ok = false;
        } else {
            $this->line('Version: ' . $config['version']);
        }

        // Check authorization callback
        if (empty($config['authorization_callback'])) {
            $this->warn('No authorization callback configured (authorization_callback)');
        } else {
            $this->line('Authorization callback configured');
        }

        // Check exposed models
        $models = $config['models'] ?? [];
        if (empty($models) && ! config('statisty.allow_unlisted_models', true)) {
            $this->warn('No models listed and allow_unlisted_models is false');
        } else {
            $this->line('Model allow-list: ' . (empty($models) ? 'none (allow unlisted)' : count($models) . ' entries'));
        }

        // Check assets
        if (file_exists(public_path('vendor/statisty/statisty.js'))) {
            $this->line('Public assets are published');
        } else {
            $this->warn('Public assets not found. Run: php artisan vendor:publish --tag=statisty-assets');
        }

        // Policies - best-effort check for middleware class
        if (class_exists(\Statisty\Http\Middleware\EnsureStatistyAuthorized::class)) {
            $this->line('Authorization middleware exists');
        } else {
            $this->warn('Authorization middleware \Statisty\Http\Middleware\EnsureStatistyAuthorized not found');
        }

        $this->info($ok ? 'Doctor checks completed.' : 'Doctor found issues.');

        return $ok ? 0 : 2;
    }
}
