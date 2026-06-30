<?php

declare(strict_types=1);

namespace Statisty\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Statisty\Metrics\CohortAnalyzer;
use Statisty\Tests\TestCase;

final class CohortAnalyzerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $schema = $this->app['db']->connection()->getSchemaBuilder();
        if (! $schema->hasTable('events')) {
            $schema->create('events', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        // two users in two different weeks
        DB::table('events')->insert([
            ['user_id' => 1, 'created_at' => '2023-01-02 09:00:00'],
            ['user_id' => 1, 'created_at' => '2023-01-09 09:00:00'],
            ['user_id' => 2, 'created_at' => '2023-01-03 10:00:00'],
            ['user_id' => 2, 'created_at' => '2023-01-10 11:00:00'],
        ]);
    }

    public function test_analyze_returns_labels_and_matrix(): void
    {
        $analyzer = new CohortAnalyzer();

        $out = $analyzer->analyze(\Statisty\Tests\Models\Event::class, 'created_at', 'week', 2);

        $this->assertArrayHasKey('labels', $out);
        $this->assertArrayHasKey('matrix', $out);
        $this->assertNotEmpty($out['labels']);
        $this->assertNotEmpty($out['matrix']);
    }
}
