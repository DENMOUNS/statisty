<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Statisty\Tests\TestCase;

final class HttpEndpointsTest extends TestCase
{
    public function test_health_and_dashboard_endpoints_return_ok(): void
    {
        $resp = $this->get('/statisty/health');
        $resp->assertStatus(200)->assertJson(['status' => 'ok']);

        $resp2 = $this->get('/statisty/dashboard');
        $resp2->assertStatus(200)->assertJsonStructure(['name', 'statisty_version']);
    }
}
