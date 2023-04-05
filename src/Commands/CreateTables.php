<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Commands;

use BlackFrog\LaravelEventSourcingDynamodb\Tables\Exceptions\TableAlreadyExists;
use BlackFrog\LaravelEventSourcingDynamodb\Tables\TableManager;
use Illuminate\Console\Command;

class CreateTables extends Command
{
    public $signature = 'event-sourcing-dynamodb:create-tables
                         {--force : Delete the tables if they already exist}';

    public $description = 'Creates the requisite DynamoDb tables for laravel event sourcing.';

    public function __construct(private readonly TableManager $tableManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->info('Creating stored event table...');
            $this->tableManager->createTable(
                config('event-sourcing-dynamodb.stored-event-table'),
                config('event-sourcing-dynamodb.stored-event-table-definition'),
                $this->option('force')
            );
            $this->comment('Stored event table created.');

            $this->info('Creating snapshot table...');
            $this->tableManager->createTable(
                config('event-sourcing-dynamodb.snapshot-table'),
                config('event-sourcing-dynamodb.snapshot-table-definition'),
                $this->option('force')
            );
            $this->comment('Snapshot table created.');

            return self::SUCCESS;
        } catch (TableAlreadyExists $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
