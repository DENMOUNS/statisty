<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Statisty\Tests\TestCase;

class FunnelEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('funnel_events', function (Blueprint $table) {
            $table->id();
            $table->string('step')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // user 1: steps A then B
        \DB::table('funnel_events')->insert([
            ['user_id' => 1, 'step' => 'A', 'created_at' => now()->subDays(3)],
            ['user_id' => 1, 'step' => 'B', 'created_at' => now()->subDays(2)],
            // user 2: only A
            ['user_id' => 2, 'step' => 'A', 'created_at' => now()->subDays(2)],
            // user 3: A then B
            ['user_id' => 3, 'step' => 'A', 'created_at' => now()->subDays(4)],
            ['user_id' => 3, 'step' => 'B', 'created_at' => now()->subDays(1)],
            // user 4: B only, should not count as completing step 2
            ['user_id' => 4, 'step' => 'B', 'created_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('funnel_events');
        parent::tearDown();
    }

    public function test_funnel_endpoint_counts_steps()
    {
        $model = new class extends Model {
            protected $table = 'funnel_events';
            public $timestamps = false;
        };

        $steps = [
            ['column' => 'step', 'operator' => '=', 'value' => 'A'],
            ['column' => 'step', 'operator' => '=', 'value' => 'B'],
        ];

        $url = '/statisty/metrics/' . urlencode(get_class($model));

        $response = $this->getJson($url . '?type=funnel&steps=' . urlencode(json_encode($steps)));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('steps', $data);
        $this->assertCount(2, $data['steps']);
        $this->assertEquals(3, $data['steps'][0]['count']); // three A's
        $this->assertEquals(2, $data['steps'][1]['count']); // two users completed A then B
    }
}
