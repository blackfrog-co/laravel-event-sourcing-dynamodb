<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Tests;

use AllowDynamicProperties;
use Aws\DynamoDb\DynamoDbClient;
use BlackFrog\LaravelEventSourcingDynamodb\LaravelEventSourcingDynamodbServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

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

        config()->set('database.connections.dynamodb', [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'token' => env('AWS_SESSION_TOKEN', null),
            'endpoint' => env('DYNAMODB_ENDPOINT', null),
            'prefix' => '', // table prefix
            'database' => 'something',
        ]);
        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-event-sourcing-dynamodb_table.php.stub';
        $migration->up();
        */
    }

    protected function getDynamoDbClient(): DynamoDbClient
    {
        return new DynamoDbClient(config('event-sourcing-dynamodb.dynamodb-client'));
    }
}
