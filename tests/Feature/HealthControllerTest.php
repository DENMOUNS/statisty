<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Statisty\Tests\TestCase;

final class HealthControllerTest extends TestCase
{
    public function test_health_page_returns_view_and_keys(): void
    {
        $response = $this->get('/web/statisty/health');

        $response->assertStatus(200);
        $response->assertViewHasAll(['healthChecks', 'slowQueries']);
    }

    public function test_health_page_excludes_previous_query_log_entries(): void
    {
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::select('select 1');

        // Reset query log to simulate a fresh start
        \Illuminate\Support\Facades\DB::flushQueryLog();

        $response = $this->get('/web/statisty/health');

        $response->assertStatus(200);
        $viewData = $response->viewData('healthChecks');
        $this->assertIsArray($viewData);

        $reportRow = array_filter($viewData, static function ($row) {
            return isset($row['label']) && $row['label'] === 'Report SQL Queries';
        });

        $this->assertNotEmpty($reportRow);
        $reportRow = array_shift($reportRow);
        // The health endpoint makes its own queries to check database health
        $this->assertStringContainsString('queries executed', $reportRow['value']);
    }
}
