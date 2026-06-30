<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Statisty\Tests\TestCase;

class AnomalyEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('anomaly_events', function (Blueprint $table) {
            $table->id();
            $table->integer('value')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        // create normal values and one outlier
        DB::table('anomaly_events')->insert([
            ['value' => 10, 'created_at' => now()->subDays(3)],
            ['value' => 12, 'created_at' => now()->subDays(2)],
            ['value' => 11, 'created_at' => now()->subDays(1)],
            ['value' => 1000, 'created_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('anomaly_events');
        parent::tearDown();
    }

    public function test_anomaly_endpoint_detects_outlier()
    {
        $model = new class extends Model {
            protected $table = 'anomaly_events';
            public $timestamps = false;
        };

        $url = '/api/statisty/metrics/' . urlencode(get_class($model));

        $response = $this->getJson($url . '?type=anomaly&field=value&period=day&threshold=3');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('anomalies', $data);
        $this->assertNotEmpty($data['anomalies']);
    }
}
