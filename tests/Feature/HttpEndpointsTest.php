<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Statisty\Tests\Models\Item;
use Statisty\Tests\TestCase;

final class HttpEndpointsTest extends TestCase
{
    public function test_health_and_dashboard_endpoints_return_ok(): void
    {
        $resp = $this->get('/api/statisty/health');
        $resp->assertStatus(200)->assertJson(['status' => 'ok']);
        $resp2 = $this->get('/web/statisty/dashboard');
        $resp2->assertStatus(200)
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Statisty Dashboard');
    }

    public function test_dashboard_renders_configured_models(): void
    {
        config(['statisty.models' => [
            Item::class => [
                'enabled' => true,
                'columns' => ['id', 'name', 'status', 'created_at'],
            ],
        ]]);
        Item::create(['name' => 'Dashboard row', 'status' => 'active']);
        $this->get('/web/statisty/dashboard')
            ->assertStatus(200)
            ->assertSee('Item')
            ->assertSee('Explore Workflow');
    }

    public function test_workflow_details_renders_successfully(): void
    {
        config(['statisty.models' => [
            Item::class => [
                'enabled' => true,
                'columns' => ['id', 'name', 'status', 'created_at'],
            ],
        ]]);
        Item::create(['name' => 'Workflow row details', 'status' => 'completed']);
// Test detailed workflow view
        $escapedClass = str_replace('\\', '%5C', Item::class);
        $this->get('/web/statisty/workflow/' . $escapedClass)
            ->assertStatus(200)
            ->assertSee('Item Analysis')
            ->assertSee('Évolution')
            ->assertSee('Données')
            ->assertSee('Workflow row details')
            ->assertSee('completed');
    }

    public function test_new_web_endpoints_render_successfully(): void
    {
        // 1. Test health view
        $this->get('/web/statisty/health')
            ->assertStatus(200)
            ->assertSee('Project Health')
            ->assertSee('Laravel')
            ->assertSee('Environment');

        // 2. Test logs view
        $this->get('/web/statisty/logs')
            ->assertStatus(200)
            ->assertSee('Log Viewer');

        // 3. Test jobs view
        $this->get('/web/statisty/jobs')
            ->assertStatus(200)
            ->assertSee('Queue')
            ->assertSee('Jobs Tracker')
            ->assertSee('Running Jobs')
            ->assertSee('Pending Jobs')
            ->assertSee('Failed Jobs');
    }

    public function test_logs_view_displays_latest_entries_first(): void
    {
        $logDirectory = storage_path('logs');
        if (! is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }

        $logFile = $logDirectory . DIRECTORY_SEPARATOR . 'statisty_test_log.log';
        $payload = implode("\n", [
            '[2026-07-01 12:00:00] local.INFO: Old message',
            '[2026-07-01 13:00:00] local.ERROR: New message',
            '[2026-07-01 14:00:00] local.WARNING: Latest message',
        ]) . "\n";

        try {
            file_put_contents($logFile, $payload);

            $this->get('/web/statisty/logs?file=' . urlencode(basename($logFile)))
                ->assertStatus(200)
                ->assertSeeInOrder([
                    'Latest message',
                    'New message',
                    'Old message',
                ]);
        } finally {
            @unlink($logFile);
        }
    }
}
