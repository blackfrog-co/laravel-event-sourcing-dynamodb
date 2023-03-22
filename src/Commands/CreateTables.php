<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Commands;

use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Console\Command;

class CreateTables extends Command
{
    public $signature = 'laravel-event-sourcing-dynamodb:create-tables';

    public $description = 'Creates the requisite DynamoDb tables for event sourcing.';

    public function __construct(private readonly DynamoDbClient $dynamoDbClient)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $createEventsTableRequest = config('event-sourcing-dynamodb.stored-event-table-definition');
        $tableName = config('event-sourcing-dynamodb.stored-event-table');
        $createEventsTableRequest['TableName'] = $tableName;

        if($this->tableAlreadyExists($tableName)){
            $this->error("Table {$tableName} already exists.");
            return self::FAILURE;
        }

        $this->info("Creating stored events table '{$tableName}'");

        $this->dynamoDbClient->createTable($createEventsTableRequest);

        $this->info("Waiting for table creation to finish...");

        $this->dynamoDbClient->waitUntil('TableExists', ['TableName' => $tableName]);

        $this->comment("Table creation finished.");

        return self::SUCCESS;
    }

    private function tableAlreadyExists(string $name): bool
    {
        $tableNames = $this->dynamoDbClient->listTables()->get('TableNames');
        return in_array($name, $tableNames);
    }
}
