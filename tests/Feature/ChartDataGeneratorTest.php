<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Statisty\Charts\ChartDataGenerator;
use Statisty\Tests\TestCase;

class ChartDataGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->integer('value')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        DB::table('events')->insert([
            ['category' => 'a', 'value' => 10, 'created_at' => now()->subDays(2)],
            ['category' => 'b', 'value' => 5, 'created_at' => now()->subDays(1)],
            ['category' => 'a', 'value' => 7, 'created_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('events');
        parent::tearDown();
    }

    public function test_generate_count_by_period_returns_dataset()
    {
        $model = new class extends Model {
            protected $table = 'events';
            public $timestamps = false;
        };

        $g = new ChartDataGenerator();

        $result = $g->generateFromModel(get_class($model), null, 'created_at', ['period' => 'day']);

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertNotEmpty($result['labels']);
        $this->assertCount(1, $result['datasets']);
    }

    public function test_generate_rejects_unknown_value_column()
    {
        $model = new class extends Model {
            protected $table = 'events';
            public $timestamps = false;
        };

        $this->expectException(\InvalidArgumentException::class);

        (new ChartDataGenerator())->generateFromModel(get_class($model), 'value) from events --', 'created_at');
    }

    public function test_sqlite_uses_native_grouping_for_period_count()
    {
        $model = new class extends Model {
            protected $table = 'events';
            public $timestamps = false;
        };

        $generator = new ChartDataGenerator();
        $query = $model->newQuery();

        $method = new \ReflectionMethod(ChartDataGenerator::class, 'aggregateCountByPeriod');
        $method->setAccessible(true);
        $method->invoke($generator, $query, 'events.created_at', 'day');

        $this->assertStringContainsString('strftime', $query->toSql());
    }
}
