<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Tests;

use AllowDynamicProperties;
use Aws\DynamoDb\DynamoDbClient;
use BlackFrog\LaravelEventSourcingDynamodb\Commands\CreateTables;
use BlackFrog\LaravelEventSourcingDynamodb\LaravelEventSourcingDynamodbServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\EventSourcing\EventSourcingServiceProvider;

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

        $app->register(EventSourcingServiceProvider::class);
    }

    protected function getDynamoDbClient(): DynamoDbClient
    {
        return new DynamoDbClient(config('event-sourcing-dynamodb.dynamodb-client'));
    }

    protected function resetDynamoTable(string $tableName = null)
    {
        $tableName = $tableName ?? 'stored_events';

        $this->deleteTableIfExists($tableName);
    }

    protected function deleteTableIfExists(string $name): void
    {
        if ($this->tableExists($name)) {
            $this->getDynamoDbClient()->deleteTable(
                ['TableName' => 'stored_events']
            );
            $this->getDynamoDbClient()->waitUntil('TableNotExists', ['TableName' => $name]);
        }
    }

    protected function tableExists(string $name): bool
    {
        $tableNames = $this->getDynamoDbClient()->listTables()->get('TableNames');

        return in_array($name, $tableNames);
    }

    protected function createTable(): void
    {
        $this->deleteTableIfExists('stored_events');
        $this->artisan(CreateTables::class);
    }

    protected function microTimeToInt(array $microTime): int
    {
        return
            intval((int) $microTime[1] * 1E6)
            +
            intval(round((float) $microTime[0] * 1E6));
    }
}
