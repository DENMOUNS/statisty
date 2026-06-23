<?php

declare(strict_types=1);

namespace Statisty\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Statisty\Core\Statisty;
use Statisty\Support\StatistyAuthorization;
use Statisty\Tests\TestCase;

final class PriorityHighTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        StatistyAuthorization::authorizeUsing(null);

        Schema::create('priority_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });

        Schema::create('priority_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->nullable();
            $table->integer('amount')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        \DB::table('priority_users')->insert([
            ['id' => 1, 'email' => 'a@example.test', 'password' => 'secret'],
        ]);

        \DB::table('priority_events')->insert([
            ['user_id' => 1, 'status' => 'new', 'amount' => 10, 'created_at' => now()->subDay()],
            ['user_id' => 1, 'status' => 'paid', 'amount' => 20, 'created_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        StatistyAuthorization::authorizeUsing(null);
        Schema::dropIfExists('priority_events');
        Schema::dropIfExists('priority_users');

        parent::tearDown();
    }

    public function test_model_allow_list_blocks_unlisted_models(): void
    {
        config()->set('statisty.allow_unlisted_models', false);
        config()->set('statisty.models', []);

        $this->getJson('/statisty/tables/' . urlencode(PriorityEvent::class))
            ->assertStatus(404)
            ->assertJson(['error' => 'invalid_model']);
    }

    public function test_allow_list_limits_columns_and_relation_columns(): void
    {
        config()->set('statisty.allow_unlisted_models', false);
        config()->set('statisty.models', [
            PriorityEvent::class => [
                'columns' => ['id', 'status', 'created_at', 'user_id'],
                'relations' => [
                    'user' => ['columns' => ['email']],
                ],
            ],
        ]);

        $response = $this->getJson('/statisty/tables/' . urlencode(PriorityEvent::class) . '?columns[]=status&columns[]=amount&columns[]=user.email&columns[]=user.password');

        // debug output to capture server error details during CI run
        file_put_contents('php://stderr', "RESPONSE_BODY:\n" . $response->getContent() . "\n");

        $response->assertStatus(200);
        $row = $response->json('data.0');

        $this->assertArrayHasKey('status', $row);
        $this->assertArrayNotHasKey('amount', $row);
        $this->assertSame('a@example.test', $row['user.email']);
        $this->assertArrayNotHasKey('user.password', $row);
    }

    public function test_policy_and_authorization_callback_can_block_access(): void
    {
        config()->set('statisty.security.enforce_authorization', true);
        Gate::define('viewAny', fn($user = null, string $model = '') => false);

        $this->getJson('/statisty/tables/' . urlencode(PriorityEvent::class))
            ->assertStatus(403)
            ->assertJson(['error' => 'unauthorized']);

        Statisty::authorizeUsing(fn() => true);

        $this->getJson('/statisty/tables/' . urlencode(PriorityEvent::class))
            ->assertStatus(200);
    }

    public function test_configured_kpi_definition_can_drive_endpoint(): void
    {
        config()->set('statisty.definitions.kpis.total_amount', [
            'name' => 'Total Amount',
            'type' => 'sum',
            'model' => PriorityEvent::class,
            'field' => 'amount',
        ]);

        $this->getJson('/statisty/metrics/ignored?type=sum&definition=total_amount')
            ->assertStatus(200)
            ->assertJsonPath('value', 30);
    }

    public function test_builder_can_add_business_definitions(): void
    {
        $dashboard = Statisty::workspace('priority')
            ->models([PriorityEvent::class])
            ->sum(PriorityEvent::class, 'amount', 'Total Amount')
            ->lineChart(PriorityEvent::class, 'amount', 'Amount Trend')
            ->funnel('Lifecycle', PriorityEvent::class, [
                ['column' => 'status', 'value' => 'new'],
                ['column' => 'status', 'value' => 'paid'],
            ])
            ->cohort('Retention', PriorityEvent::class, ['identity_column' => 'user_id'])
            ->build()
            ->toArray();

        $this->assertNotEmpty($dashboard['kpis']);
        $this->assertNotEmpty($dashboard['charts']);
        $this->assertCount(1, $dashboard['funnels']);
        $this->assertCount(1, $dashboard['cohorts']);
    }
}

class PriorityUser extends Model
{
    protected $table = 'priority_users';
    public $timestamps = false;
    protected $hidden = ['password'];
}

class PriorityEvent extends Model
{
    protected $table = 'priority_events';
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(PriorityUser::class, 'user_id');
    }
}
