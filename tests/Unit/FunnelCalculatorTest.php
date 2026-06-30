<?php

declare(strict_types=1);

namespace Statisty\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Statisty\Metrics\FunnelCalculator;
use Statisty\Tests\TestCase;

final class FunnelCalculatorTest extends TestCase
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

        // seed simple funnel: user 1 has two events, user 2 only first
        DB::table('events')->insert([
            ['user_id' => 1, 'status' => 'start', 'created_at' => '2023-01-01 10:00:00'],
            ['user_id' => 1, 'status' => 'finish', 'created_at' => '2023-01-01 10:05:00'],
            ['user_id' => 2, 'status' => 'start', 'created_at' => '2023-01-02 11:00:00'],
        ]);
    }

    public function test_run_counts_steps_and_conversion(): void
    {
        $calculator = new FunnelCalculator();

        $steps = [
            ['column' => 'status', 'operator' => '=', 'value' => 'start'],
            ['column' => 'status', 'operator' => '=', 'value' => 'finish'],
        ];

        $result = $calculator->run(\Statisty\Tests\Models\Event::class, $steps, ['distinct_by' => 'user_id']);

        $this->assertArrayHasKey('steps', $result);
        $this->assertCount(2, $result['steps']);
        $this->assertSame(2, $result['steps'][0]['count']);
        $this->assertSame(1, $result['steps'][1]['count']);
    }
}
