<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class StatistySmokeTest extends TestCase
{
    public function test_health_endpoint(): void
    {
        $resp = $this->get('/api/statisty/health');

        $resp->assertStatus(200);
        $resp->assertJson(['status' => 'ok']);
    }
}
