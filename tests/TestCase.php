<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Tests;

use AllowDynamicProperties;
use Aws\DynamoDb\DynamoDbClient;
use BlackFrog\LaravelEventSourcingDynamodb\Commands\CreateTables;
use BlackFrog\LaravelEventSourcingDynamodb\LaravelEventSourcingDynamodbServiceProvider;
use BlackFrog\LaravelEventSourcingDynamodb\Tables\TableManager;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\EventSourcing\EventSourcingServiceProvider;

#[AllowDynamicProperties] class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelEventSourcingDynamodbServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app->register(EventSourcingServiceProvider::class);
    }

    protected function getDynamoDbClient(): DynamoDbClient
    {
        return new DynamoDbClient(config('event-sourcing-dynamodb.dynamodb-client'));
    }

    protected function getTableManager(): TableManager
    {
        return new TableManager($this->getDynamoDbClient());
    }

    protected function deleteTableIfExists(string $name): void
    {
        if ($this->getTableManager()->tableExists($name)) {
            $this->getTableManager()->deleteTable($name);
        }
    }

    protected function createTables(bool $force = true): void
    {
        $this->artisan(CreateTables::class, ['--force' => $force]);
    }

    protected function deleteTables(): void
    {
        $this->deleteTableIfExists('stored_events');
        $this->deleteTableIfExists('snapshots');
    }

    protected function microTimeToInt(array $microTime): int
    {
        return
            intval((int) $microTime[1] * 1E6)
            +
            intval(round((float) $microTime[0] * 1E6));
    }
}
