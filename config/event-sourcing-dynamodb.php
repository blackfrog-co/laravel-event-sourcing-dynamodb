<?php

// config for BlackFrog/LaravelEventSourcingDynamodb
return [
    /**
     * The DynamoDB table in which events are kept.
     *
     * @see \BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\Repositories\DynamoDbStoredEventRepository
     */
    'stored-event-table' => 'stored_events',
    /**
     * The DynamoDB table in which snapshots are kept.
     *
     * @see \BlackFrog\LaravelEventSourcingDynamodb\Snapshots\DynamoDbSnapshotRepository
     */
    'snapshot-table' => 'snapshots',
];
