<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Statisty\Tests\TestCase;

class CohortEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cohort_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // create events across days
        DB::table('cohort_events')->insert([
            ['user_id' => 1, 'created_at' => now()->subDays(6)],
            ['user_id' => 2, 'created_at' => now()->subDays(6)],
            ['user_id' => 3, 'created_at' => now()->subDays(3)],
            ['user_id' => 4, 'created_at' => now()->subDays(2)],
            ['user_id' => 5, 'created_at' => now()->subDays(1)],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('cohort_events');
        parent::tearDown();
    }

    public function test_cohort_endpoint_returns_matrix()
    {
        $model = new class extends Model {
            protected $table = 'cohort_events';
            public $timestamps = false;
        };

        $url = '/api/statisty/metrics/' . urlencode(get_class($model));

        $response = $this->getJson($url . '?type=cohort&period=day&periods=3');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('matrix', $data);
        $this->assertNotEmpty($data['labels']);
        $this->assertSame([2, 0, 0], $data['matrix'][0]);
    }
}
