<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Statisty\Facades\Statisty;
use Statisty\Tests\TestCase;

final class WorkspaceBuildTest extends TestCase
{
    public function test_it_builds_a_serializable_workspace_dashboard(): void
    {
        $dashboard = Statisty::workspace('business')
            ->models(['App\\Models\\User', 'App\\Models\\Order'])
            ->build();

        $payload = $dashboard->toArray();

        $this->assertCount(2, $payload['kpis']);

        $this->assertSame('business', $payload['workspace']['name']);
        $this->assertSame(250, $payload['workspace']['pagination']);
        $this->assertCount(2, $payload['kpis']);
        $this->assertCount(2, $payload['charts']);
        $this->assertCount(2, $payload['tables']);
    }
}
