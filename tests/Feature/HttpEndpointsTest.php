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
            ->assertSee('Dashboard row')
            ->assertSee('active');
    }
}
