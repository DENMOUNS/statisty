<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Statisty\Tables\TableQueryBuilder;
use Statisty\Tests\TestCase;

class TableQueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->string('secret')->nullable();
            $table->timestamps();
        });

        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });

        \DB::table('test_users')->insert([
            ['id' => 1, 'email' => 'a@example.test', 'password' => 'hidden'],
            ['id' => 2, 'email' => 'b@example.test', 'password' => 'hidden'],
        ]);

        // insert some rows
        \DB::table('test_models')->insert([
            ['user_id' => 1, 'name' => 'a', 'password' => 'x', 'secret' => 's', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 2, 'name' => 'b', 'password' => 'y', 'secret' => 't', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_models');
        Schema::dropIfExists('test_users');
        parent::tearDown();
    }

    public function test_select_visible_excludes_sensitive_columns()
    {
        $model = new class extends Model {
            protected $table = 'test_models';
            public $timestamps = true;
        };

        $query = $model::query();
        $qb = new TableQueryBuilder($query);
        $qb->selectVisible();

        $columns = $query->getQuery()->columns;

        $this->assertIsArray($columns);
        $this->assertNotContains('password', $columns);
        $this->assertNotContains('secret', $columns);
        $this->assertContains('name', $columns);
    }

    public function test_table_endpoint_does_not_expose_hidden_relation_columns()
    {
        $url = '/api/statisty/tables/' . urlencode(StatistyTableEntry::class);

        $response = $this->getJson($url . '?columns[]=user.email&columns[]=user.password');

        $response->assertStatus(200);
        $row = $response->json('data.0');

        $this->assertSame('a@example.test', $row['user.email']);
        $this->assertArrayNotHasKey('user.password', $row);
    }
}

class StatistyTableUser extends Model
{
    protected $table = 'test_users';
    public $timestamps = false;
    protected $hidden = ['password'];
}

class StatistyTableEntry extends Model
{
    protected $table = 'test_models';

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StatistyTableUser::class, 'user_id');
    }
}
