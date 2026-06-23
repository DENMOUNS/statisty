<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Statisty\Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('events_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->integer('value')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        \DB::table('events_metrics')->insert([
            ['category' => 'a', 'value' => 10, 'created_at' => now()->subDays(2)],
            ['category' => 'b', 'value' => 5, 'created_at' => now()->subDays(1)],
            ['category' => 'a', 'value' => 7, 'created_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('events_metrics');
        parent::tearDown();
    }

    public function test_metrics_count_endpoint_returns_value()
    {
        $model = new class extends Model {
            protected $table = 'events_metrics';
            public $timestamps = false;
        };

        $url = '/statisty/metrics/' . urlencode(get_class($model));

        $response = $this->getJson($url . '?type=count');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('value', $data);
        $this->assertEquals(3, $data['value']);
    }

    public function test_metrics_endpoint_respects_disabled_models()
    {
        $model = new class extends Model {
            protected $table = 'events_metrics';
            public $timestamps = false;
        };

        config()->set('statisty.disabled_models', [get_class($model)]);

        $url = '/statisty/metrics/' . urlencode(get_class($model));

        $this->getJson($url . '?type=count')
            ->assertStatus(403)
            ->assertJson(['error' => 'model_disabled']);
    }
}
