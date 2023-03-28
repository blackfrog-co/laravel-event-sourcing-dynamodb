<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb;

use Aws\DynamoDb\DynamoDbClient;
use BlackFrog\LaravelEventSourcingDynamodb\Commands\CreateTables;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\Repositories\DynamoDbStoredEventRepository;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelEventSourcingDynamodbServiceProvider extends PackageServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->singleton(IdGenerator::class);

        $dynamoDbClient = function () {
            return new DynamoDbClient(config('event-sourcing-dynamodb.dynamodb-client'));
        };

        $this->app->when(DynamoDbStoredEventRepository::class)
            ->needs(DynamoDbClient::class)
            ->give($dynamoDbClient);

        $this->app->when(CreateTables::class)
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
            ->hasViews()
            ->hasCommand(CreateTables::class);
    }
}
