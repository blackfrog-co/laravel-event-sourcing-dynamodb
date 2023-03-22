<?php

namespace BlackFrog\LaravelEventSourcingDynamodb;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use BlackFrog\LaravelEventSourcingDynamodb\Commands\LaravelEventSourcingDynamodbCommand;

class LaravelEventSourcingDynamodbServiceProvider extends PackageServiceProvider
{
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
            ->hasMigration('create_laravel-event-sourcing-dynamodb_table')
            ->hasCommand(LaravelEventSourcingDynamodbCommand::class);
    }
}
