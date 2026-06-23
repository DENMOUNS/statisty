<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Statisty\Tests\Models\Item;
use Statisty\Http\Controllers\TableController;
use Illuminate\Http\Request;

final class CsvExportTest extends TestCase
{
    public function test_csv_export_streams_and_contains_headers(): void
    {
        Item::create(['user_id' => 1, 'name' => 'A', 'secret' => 'X']);
        Item::create(['user_id' => 2, 'name' => 'B', 'secret' => 'Y']);

        $controller = new TableController();
        $request = Request::create('/statisty/tables', 'GET', ['export' => 'csv']);

        $response = $controller->index($request, Item::class);

        $this->assertEquals(200, $response->getStatusCode());
        $headers = $response->headers->all();
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertStringContainsString('text/csv', $headers['content-type'][0]);
    }
}
