<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\Repositories;

use Aws\DynamoDb\DynamoDbClient;
use BlackFrog\LaravelEventSourcingDynamodb\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\StoredEventFactory;
use Illuminate\Support\LazyCollection;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

class DynamoDbStoredEventRepository implements StoredEventRepository
{
    protected string $table;

    public function __construct(
        private DynamoDbClient $dynamo,
        private IdGenerator    $idGenerator,
        private StoredEventFactory $storedEventFactory,
    ) {
        $this->table = (string) config(
            'event-sourcing-dynamodb.stored_event_table',
            'stored_events'
        );
    }

    public function find(int $id): StoredEvent
    {
        $dynamoData = $this->dynamo->getItem([
            'TableName' => $this->table,
            'ConsistentRead' => false,
            'Key' => [
                'id' => [
                    'N' => $id,
                ],
            ],
        ]);
    }

    public function retrieveAll(string $uuid = null): LazyCollection
    {
        // TODO: Implement retrieveAll() method.
    }

    public function retrieveAllStartingFrom(int $startingFrom, string $uuid = null): LazyCollection
    {
        // TODO: Implement retrieveAllStartingFrom() method.
    }

    public function retrieveAllAfterVersion(int $aggregateVersion, string $aggregateUuid): LazyCollection
    {
        // TODO: Implement retrieveAllAfterVersion() method.
    }

    public function countAllStartingFrom(int $startingFrom, string $uuid = null): int
    {
        // TODO: Implement countAllStartingFrom() method.
    }

    public function persist(ShouldBeStored $event, string $uuid = null): StoredEvent
    {
        $id = $this->idGenerator->generateId();

    }

    public function persistMany(array $events, string $uuid = null): LazyCollection
    {
        // TODO: Implement persistMany() method.
    }

    public function update(StoredEvent $storedEvent): StoredEvent
    {
        // TODO: Implement update() method.
    }

    public function getLatestAggregateVersion(string $aggregateUuid): int
    {
        // TODO: Implement getLatestAggregateVersion() method.
    }
}
