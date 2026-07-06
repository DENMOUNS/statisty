<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Statisty\Charts\ChartDataGenerator;
use Statisty\Tests\Models\Event;
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

    public function test_sqlite_count_by_period_returns_expected_buckets()
    {
        $model = new class extends Model {
            protected $table = 'events';
            public $timestamps = false;
        };

        $result = (new ChartDataGenerator())->generateFromModel(get_class($model), null, 'created_at', ['period' => 'day']);

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertCount(3, $result['labels']);
        $this->assertEquals([1, 1, 1], $result['datasets'][0]['data']);
    }

    public function test_relation_value_field_sum_by_period_aggregates_related_columns()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('price')->default(0);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable();
        });

        DB::table('categories')->insert([['name' => 'A', 'price' => 10], ['name' => 'B', 'price' => 5]]);

        $categoryA = DB::table('categories')->where('name', 'A')->first();
        $categoryB = DB::table('categories')->where('name', 'B')->first();

        DB::table('events')->insert([
            ['category' => 'a', 'value' => 10, 'category_id' => $categoryA->id, 'created_at' => now()->subDays(2)],
            ['category' => 'b', 'value' => 5, 'category_id' => $categoryB->id, 'created_at' => now()->subDays(1)],
            ['category' => 'a', 'value' => 7, 'category_id' => $categoryA->id, 'created_at' => now()],
        ]);

        $categoryModel = new class extends Model {
            protected $table = 'categories';
            public $timestamps = false;
        };
        $categoryClass = get_class($categoryModel);

        $eventModel = new class extends Model {
            protected $table = 'events';
            public $timestamps = false;
            private static string $categoryClass;

            public static function setCategoryClass(string $class): void
            {
                self::$categoryClass = $class;
            }

            public function category()
            {
                return $this->belongsTo(self::$categoryClass, 'category_id');
            }
        };
        $eventModel::setCategoryClass($categoryClass);

        $result = (new ChartDataGenerator())->generateFromModel(get_class($eventModel), 'category.price', 'created_at', ['period' => 'day']);

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertCount(3, $result['labels']);
        $this->assertEquals([10, 5, 10], $result['datasets'][0]['data']);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('categories');
    }

    public function test_order_item_belongs_to_relation_value_field_works()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('price')->default(0);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer('total_amount')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(0);
            $table->integer('price')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        DB::table('products')->insert([
            ['name' => 'Widget', 'price' => 12],
            ['name' => 'Gadget', 'price' => 8],
        ]);

        $widget = DB::table('products')->where('name', 'Widget')->first();
        $gadget = DB::table('products')->where('name', 'Gadget')->first();

        DB::table('orders')->insert([
            ['total_amount' => 24, 'created_at' => now()->subDays(2)],
            ['total_amount' => 16, 'created_at' => now()->subDays(1)],
        ]);

        $orderA = DB::table('orders')->where('total_amount', 24)->first();
        $orderB = DB::table('orders')->where('total_amount', 16)->first();

        DB::table('order_items')->insert([
            ['order_id' => $orderA->id, 'product_id' => $widget->id, 'quantity' => 1, 'price' => 100, 'created_at' => now()->subDays(2)],
            ['order_id' => $orderB->id, 'product_id' => $gadget->id, 'quantity' => 2, 'price' => 105, 'created_at' => now()->subDays(1)],
            ['order_id' => $orderA->id, 'product_id' => $widget->id, 'quantity' => 3, 'price' => 110, 'created_at' => now()],
        ]);

        $productModel = new class extends Model {
            protected $table = 'products';
            public $timestamps = false;
        };
        $productClass = get_class($productModel);

        $orderModel = new class extends Model {
            protected $table = 'orders';
            public $timestamps = false;
        };
        $orderClass = get_class($orderModel);

        $orderItemModel = new class extends Model {
            protected $table = 'order_items';
            public $timestamps = false;
            private static string $productClass;
            private static string $orderClass;

            public static function setProductClass(string $class): void
            {
                self::$productClass = $class;
            }

            public static function setOrderClass(string $class): void
            {
                self::$orderClass = $class;
            }

            public function product()
            {
                return $this->belongsTo(self::$productClass, 'product_id');
            }

            public function order()
            {
                return $this->belongsTo(self::$orderClass, 'order_id');
            }
        };

        $orderItemModel::setProductClass($productClass);
        $orderItemModel::setOrderClass($orderClass);

        $result = (new ChartDataGenerator())->generateFromModel(get_class($orderItemModel), 'product.price', 'created_at', ['period' => 'day']);

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertCount(3, $result['labels']);
        $this->assertEquals([12, 8, 12], $result['datasets'][0]['data']);

        $orderResult = (new ChartDataGenerator())->generateFromModel(get_class($orderItemModel), 'order.total_amount', 'created_at', ['period' => 'day']);

        $this->assertArrayHasKey('labels', $orderResult);
        $this->assertArrayHasKey('datasets', $orderResult);
        $this->assertCount(3, $orderResult['labels']);
        $this->assertEquals([24, 16, 24], $orderResult['datasets'][0]['data']);

        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
}
