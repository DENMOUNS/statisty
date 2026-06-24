<?php

declare(strict_types=1);

namespace Tests\Feature;

use Orchestra\Testbench\TestCase;

final class StatistySmokeTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Statisty\Infrastructure\StatistyServiceProvider::class];
    }

    public function test_health_endpoint(): void
    {
        $resp = $this->get('/api/statisty/health');

        $resp->assertStatus(200);
        $resp->assertJson(['status' => 'ok']);
    }
}
