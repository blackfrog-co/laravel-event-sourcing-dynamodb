<?php

use BlackFrog\LaravelEventSourcingDynamodb\Commands\CreateTables;

beforeEach(function () {
    $this->deleteTableIfExists('stored_events');
    $this->deleteTableIfExists('potato_events');
    $this->deleteTableIfExists('snapshots');
    $this->deleteTableIfExists('potato_snapshots');
});

afterEach(function () {
    $this->deleteTableIfExists('stored_events');
    $this->deleteTableIfExists('potato_events');
    $this->deleteTableIfExists('snapshots');
    $this->deleteTableIfExists('potato_snapshots');
});

it('creates the stored events table', function () {
    $this->artisan(CreateTables::class)->assertOk();

    $tableNames = $this->getDynamoDbClient()->listTables()->get('TableNames');

    expect($tableNames)->toBeArray()->toContain('stored_events');
});

it('respects the stored events table name set in config', function () {
    $this->app['config']->set('event-sourcing-dynamodb.stored-event-table', 'potato_events');

    $this->artisan(CreateTables::class)->assertOk();

    $tableNames = $this->getDynamoDbClient()->listTables()->get('TableNames');

    expect($tableNames)->toBeArray()->toContain('potato_events');
});

it('creates the snapshots table', function () {
    $this->artisan(CreateTables::class)->assertOk();

    $tableNames = $this->getDynamoDbClient()->listTables()->get('TableNames');

    expect($tableNames)->toBeArray()->toContain('snapshots');
});

it('respects the snapshots table name set in config', function () {
    $this->app['config']->set('event-sourcing-dynamodb.snapshot-table', 'potato_snapshots');

    $this->artisan(CreateTables::class)->assertOk();

    $tableNames = $this->getDynamoDbClient()->listTables()->get('TableNames');

    expect($tableNames)->toBeArray()->toContain('potato_snapshots');
});

it('returns an error if the tables already exist', function () {
    //Create the first time.
    $this->artisan(CreateTables::class)->assertOk();

    //Fail the second
    $this->artisan(CreateTables::class)->assertFailed();
});

it('deletes the tables and recreates them if the force option is set', function () {
    //Create the first time.
    $this->artisan(CreateTables::class)->assertOk();

    //Fail the second
    $this->artisan(CreateTables::class, ['--force' => true])->assertOk();
});
