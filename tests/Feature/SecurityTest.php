<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Statisty\Tests\Models\Item;
use Statisty\Http\Controllers\TableController;
use Illuminate\Http\Request;

final class SecurityTest extends TestCase
{
    public function test_hidden_columns_are_not_returned(): void
    {
        Item::create(['user_id' => 1, 'name' => 'Secret item', 'secret' => 'TOPSECRET']);

        $controller = new TableController();
        $request = Request::create('/api/statisty/tables', 'GET', ['columns' => ['id', 'name', 'secret']]);

        $response = $controller->index($request, Item::class);

        $data = $response->getData(true);

        $this->assertArrayHasKey('data', $data);
        $row = $data['data'][0];

        // La clé primaire n'est jamais affichée, même demandée explicitement.
        $this->assertArrayNotHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayNotHasKey('secret', $row);
    }

    public function test_disabled_model_is_forbidden(): void
    {
        config(['statisty.disabled_models' => [Item::class]]);

        $controller = new TableController();
        $request = Request::create('/api/statisty/tables', 'GET');

        $response = $controller->index($request, Item::class);

        $this->assertEquals(403, $response->getStatusCode());
    }
}