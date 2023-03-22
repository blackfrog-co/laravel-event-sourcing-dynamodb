<?php

use BlackFrog\LaravelEventSourcingDynamodb\Commands\CreateTables;

it('creates the stored events table', function () {
    $this->artisan(CreateTables::class)->assertOk();

    $tableNames = $this->getDynamoDbClient()->listTables()->get('TableNames');

    expect($tableNames)->toBeArray()->toContain('stored_events');

    $this->getDynamoDbClient()->deleteTable(
        ['TableName' => 'stored_events']
    );
});

it('respects the stored events table name set in config', function () {
    $this->app['config']->set('event-sourcing-dynamodb.stored-event-table', 'potato_events');

    $this->artisan(CreateTables::class)->assertOk();

    $tableNames = $this->getDynamoDbClient()->listTables()->get('TableNames');

    expect($tableNames)->toBeArray()->toContain('potato_events');

    $this->getDynamoDbClient()->deleteTable(
        ['TableName' => 'potato_events']
    );
});

it('returns an error if the table already exists', function () {
    //Create it the first time.
    $this->artisan(CreateTables::class)->assertOk();

    //Fail the second
    $this->artisan(CreateTables::class)->assertFailed();

    $this->getDynamoDbClient()->deleteTable(
        ['TableName' => 'stored_events']
    );
});
