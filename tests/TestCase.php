<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Tests;

use AllowDynamicProperties;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use BlackFrog\LaravelEventSourcingDynamodb\LaravelEventSourcingDynamodbServiceProvider;

#[AllowDynamicProperties] class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'BlackFrog\\LaravelEventSourcingDynamodb\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelEventSourcingDynamodbServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-event-sourcing-dynamodb_table.php.stub';
        $migration->up();
        */
    }
}
