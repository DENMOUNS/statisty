<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statisty\Infrastructure\StatistyServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            StatistyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use in-memory sqlite for tests
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // simple cache driver
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.debug', true);
        // disable authorization enforcement for HTTP feature tests by default
        $app['config']->set('statisty.security.enforce_authorization', false);

        // create a simple items table for feature tests that need it
        $schema = $app['db']->connection()->getSchemaBuilder();
        if (! $schema->hasTable('items')) {
            $schema->create('items', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->nullable();
                $table->string('name')->nullable();
                $table->string('secret')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
    }
}
