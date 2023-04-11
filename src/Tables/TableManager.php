<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Tables;

use Aws\DynamoDb\DynamoDbClient;

readonly class TableManager
{
    public function __construct(private DynamoDbClient $dynamoDbClient)
    {
    }

    public function tableExists(string $name): bool
    {
        $tableNames = $this->dynamoDbClient->listTables()->get('TableNames');

        return in_array($name, $tableNames);
    }

    public function deleteTable(string $name): void
    {
        $this->dynamoDbClient->deleteTable(
            ['TableName' => $name]
        );

        $this->dynamoDbClient->waitUntil('TableNotExists', ['TableName' => $name]);
    }

    public function createTable(string $name, array $definition, bool $force = false): void
    {
        $tableExists = $this->tableExists($name);

        if ($tableExists && $force === false) {
            throw new TableAlreadyExistsException("Table '{$name}' already exists.");
        }

        if ($tableExists && $force === true) {
            $this->deleteTable($name);
        }

        $definition['TableName'] = $name;

        $this->dynamoDbClient->createTable($definition);
    }
}
