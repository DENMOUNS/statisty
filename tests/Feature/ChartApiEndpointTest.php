<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Statisty\Tests\Models\Item;
use Statisty\Tests\TestCase;

final class ChartApiEndpointTest extends TestCase
{
    public function test_chart_api_endpoint_returns_chart_payload(): void
    {
        config(['statisty.models' => [
            Item::class => [
                'enabled' => true,
                'columns' => ['id', 'name', 'status', 'created_at'],
            ],
        ]]);

        Item::create(['name' => 'First', 'status' => 'active']);
        Item::create(['name' => 'Second', 'status' => 'inactive']);

        $url = '/api/statisty/charts/' . urlencode(Item::class) . '?period=day';

        $response = $this->getJson($url);

        $response->assertStatus(200)
            ->assertJsonStructure(['labels', 'datasets'])
            ->assertJsonCount(1, 'datasets');

        $this->assertIsArray($response->json('datasets.0.data'));
        $this->assertNotEmpty($response->json('labels'));
    }
}
