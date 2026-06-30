<?php

declare(strict_types=1);

namespace Statisty\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Statisty\Metrics\FunnelCalculator;
use Statisty\Tests\TestCase;

final class FunnelSequentialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $schema = $this->app['db']->connection()->getSchemaBuilder();
        if (! $schema->hasTable('events')) {
            $schema->create('events', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        DB::table('events')->truncate();

        $base = strtotime('2023-01-01 00:00:00');
        // user 1 completes within 60s window
        DB::table('events')->insert([
            ['user_id' => 1, 'status' => 'start', 'created_at' => date('Y-m-d H:i:s', $base + 0)],
            ['user_id' => 1, 'status' => 'finish', 'created_at' => date('Y-m-d H:i:s', $base + 30)],
            // user 2 finishes outside window
            ['user_id' => 2, 'status' => 'start', 'created_at' => date('Y-m-d H:i:s', $base + 0)],
            ['user_id' => 2, 'status' => 'finish', 'created_at' => date('Y-m-d H:i:s', $base + 3600)],
        ]);
    }

    public function test_sequential_with_window_and_strict_order(): void
    {
        $calculator = new FunnelCalculator();

        $steps = [
            ['column' => 'status', 'operator' => '=', 'value' => 'start'],
            ['column' => 'status', 'operator' => '=', 'value' => 'finish'],
        ];

        $out = $calculator->run(\Statisty\Tests\Models\Event::class, $steps, ['distinct_by' => 'user_id', 'conversion_window' => 60, 'strict_order' => true]);

        $this->assertArrayHasKey('steps', $out);
        $this->assertSame(2, $out['steps'][0]['count']);
        $this->assertSame(1, $out['steps'][1]['count']);
    }
}
