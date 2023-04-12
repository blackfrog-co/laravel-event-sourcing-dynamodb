<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb;

use Aws\DynamoDb\DynamoDbClient;
use BlackFrog\LaravelEventSourcingDynamodb\Commands\CreateTables;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\MicroTimestampProvider;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\TimestampProvider;
use BlackFrog\LaravelEventSourcingDynamodb\Snapshots\DynamoDbSnapshotRepository;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\DynamoDbStoredEventRepository;
use BlackFrog\LaravelEventSourcingDynamodb\Tables\TableManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelEventSourcingDynamodbServiceProvider extends PackageServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->singleton(IdGenerator::class);

        $this->app->bind(
            TimestampProvider::class,
            config(
                'event-sourcing-dynamodb.id_timestamp_provider',
                MicroTimestampProvider::class
            )
        );

        $dynamoDbClient = function (): DynamoDbClient {
            return new DynamoDbClient(config('event-sourcing-dynamodb.dynamodb-client'));
        };

        $this->app->when([
            DynamoDbStoredEventRepository::class,
            DynamoDbSnapshotRepository::class,
            CreateTables::class,
            TableManager::class,
        ])
            ->needs(DynamoDbClient::class)
            ->give($dynamoDbClient);
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-event-sourcing-dynamodb')
            ->hasConfigFile()
            ->hasCommand(CreateTables::class);
    }
}
