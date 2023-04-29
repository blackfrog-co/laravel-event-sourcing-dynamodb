<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb;

use Aws\Credentials\Credentials;
use Aws\DynamoDb\DynamoDbClient;
use BlackFrog\LaravelEventSourcingDynamodb\Commands\CreateTables;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\MicroTimestampProvider;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\TimeStampIdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\TimestampProvider;
use BlackFrog\LaravelEventSourcingDynamodb\Snapshots\DynamoSnapshotRepository;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\DynamoStoredEventRepository;
use BlackFrog\LaravelEventSourcingDynamodb\Tables\TableManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelEventSourcingDynamodbServiceProvider extends PackageServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->singleton(
            IdGenerator::class,
            config(
                'event-sourcing-dynamodb.id_generator',
                TimeStampIdGenerator::class
            )
        );

        $this->app->singleton(
            TimestampProvider::class,
            config(
                'event-sourcing-dynamodb.id_timestamp_provider',
                MicroTimestampProvider::class
            )
        );

        $this->app->when([
            DynamoStoredEventRepository::class,
            DynamoSnapshotRepository::class,
            CreateTables::class,
            TableManager::class,
        ])
            ->needs(DynamoDbClient::class)
            ->give(function (): DynamoDbClient {
                return new DynamoDbClient(
                    config(
                        'event-sourcing-dynamodb.dynamodb-client',
                        new Credentials('key', 'secret')
                    )
                );
            });
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
