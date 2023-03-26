<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\Repositories;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\ResultPaginator;
use BlackFrog\LaravelEventSourcingDynamodb\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\MetaData;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use Spatie\EventSourcing\EventSerializers\JsonEventSerializer;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

class DynamoDbStoredEventRepository implements StoredEventRepository
{
    protected string $table;

    public function __construct(
        private DynamoDbClient $dynamo,
        private IdGenerator $idGenerator,
        private Marshaler $dynamoMarshaler,
    ) {
        $this->table = (string) config(
            'event-sourcing-dynamodb.stored_event_table',
            'stored_events'
        );
    }

    public function find(int $id): StoredEvent
    {
        $dynamoResult = $this->dynamo->getItem([
            'TableName' => $this->table,
            'ConsistentRead' => false, //TODO: Make configurable
            'Key' => [
                'id' => [
                    'N' => $id,
                ],
            ],
        ]);

        $dynamoItem = $this->dynamoMarshaler->unmarshalItem($dynamoResult->get('Item'));

        return $this->storedEventFromDynamoItem($dynamoItem);
    }

    private function storedEventFromDynamoItem(array $dynamoItem): StoredEvent
    {
        return new StoredEvent([
            'id' => $dynamoItem['id'],
            'event_properties' => $dynamoItem['event_properties'],
            'aggregate_uuid' => $dynamoItem['aggregate_uuid'] ?? '',
            'aggregate_version' => (string) $dynamoItem['aggregate_version'],
            'event_version' => $dynamoItem['event_version'],
            'event_class' => $dynamoItem['event_class'],
            'meta_data' => new MetaData(
                Arr::except($dynamoItem['meta_data'], ['stored-event-id', 'created-at']),
                $dynamoItem['meta_data']['created-at'],
                $dynamoItem['meta_data']['stored-event-id']
            ),
            'created_at' => $dynamoItem['created_at'],
        ]);
    }

    public function retrieveAll(string $uuid = null): LazyCollection
    {
        if ($uuid) {
            return $this->retrieveByAggregateRootUuid($uuid);
        }

        $resultPaginator = $this->dynamo->getPaginator('Scan', [
            'TableName' => $this->table,
            'IndexName' => 'id-sort_id-index',
        ]);

        return $this->lazyCollectionFromPaginator($resultPaginator);
    }

    private function retrieveByAggregateRootUuid(string $uuid): LazyCollection
    {
        $resultPaginator = $this->dynamo->getPaginator('Query', [
            'TableName' => $this->table,
            'IndexName' => 'aggregate_uuid-index',
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid',
            'ExpressionAttributeValues' => [':aggregate_uuid' => ['S' => $uuid]],
        ]);

        return $this->lazyCollectionFromPaginator($resultPaginator);
    }

    private function lazyCollectionFromPaginator(ResultPaginator $paginator): LazyCollection
    {
        return LazyCollection::make(
            function () use (&$paginator) {
                while ($result = $paginator->current()) {
                    foreach ($result->get('Items') as $item) {
                        $dynamoItem = $this->dynamoMarshaler->unmarshalItem($item);
                        yield $this->storedEventFromDynamoItem($dynamoItem);
                    }

                    $paginator->next();
                }
            }
        )->remember();
    }

    public function retrieveAllStartingFrom(int $startingFrom, string $uuid = null): LazyCollection
    {
        if ($uuid !== null) {
            return $this->retrieveAllStartingFromByUuid($startingFrom, $uuid);
        }

        $resultPaginator = $this->dynamo->getPaginator('Scan', [
            'TableName' => $this->table,
            'FilterExpression' => 'id >= :id',
            'ExpressionAttributeValues' => [
                ':id' => ['N' => $startingFrom],
            ],
        ]);

        return $this->lazyCollectionFromPaginator($resultPaginator);
    }

    private function retrieveAllStartingFromByUuid(int $startingFrom, string $uuid): LazyCollection
    {
        $resultPaginator = $this->dynamo->getPaginator('Query', [
            'TableName' => $this->table,
            'IndexName' => 'aggregate_uuid-index',
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid AND id >= :id',
            'ExpressionAttributeValues' => [
                ':aggregate_uuid' => ['S' => $uuid],
                ':id' => ['N' => $startingFrom],
            ],
        ]);

        return $this->lazyCollectionFromPaginator($resultPaginator);
    }

    public function retrieveAllAfterVersion(int $aggregateVersion, string $aggregateUuid): LazyCollection
    {
        $resultPaginator = $this->dynamo->getPaginator('Query', [
            'TableName' => $this->table,
            'IndexName' => 'aggregate_uuid-version-index',
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid AND aggregate_version > :aggregate_version',
            'ExpressionAttributeValues' => [
                ':aggregate_uuid' => ['S' => $aggregateUuid],
                ':aggregate_version' => ['N' => $aggregateVersion],
            ],
        ]);

        return $this->lazyCollectionFromPaginator($resultPaginator);
    }

    public function countAllStartingFrom(int $startingFrom, string $uuid = null): int
    {
        if ($uuid !== null) {
            return $this->countStartingFromByUuid($startingFrom, $uuid);
        }

        $resultPaginator = $this->dynamo->getPaginator('Scan', [
            'TableName' => $this->table,
            'FilterExpression' => 'id >= :id',
            'ExpressionAttributeValues' => [
                ':id' => ['N' => $startingFrom],
            ],
            'ProjectionExpression' => 'id',
        ]);

        $count = 0;

        while ($result = $resultPaginator->current()) {
            $count += $result->get('Count');
            $resultPaginator->next();
        }

        return $count;
    }

    private function countStartingFromByUuid(int $startingFrom, string $uuid): int
    {
        $resultPaginator = $this->dynamo->getPaginator('Query', [
            'TableName' => $this->table,
            'IndexName' => 'aggregate_uuid-index',
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid AND id >= :id',
            'ExpressionAttributeValues' => [
                ':aggregate_uuid' => ['S' => $uuid],
                ':id' => ['N' => $startingFrom],
            ],
            'ProjectionExpression' => 'id',
        ]);

        $count = 0;

        while ($result = $resultPaginator->current()) {
            $count += $result->get('ScannedCount');
            $resultPaginator->next();
        }

        return $count;
    }

    public function persist(ShouldBeStored $event, string $uuid = null): StoredEvent
    {
        $id = $this->idGenerator->generateId();
        $createdAt = Carbon::now();

        $eventSerializer = new JsonEventSerializer();

        $storedEvent = new StoredEvent([
            'id' => $id,
            'event_properties' => $eventSerializer->serialize(clone $event),
            'aggregate_uuid' => $uuid,
            'aggregate_version' => $event->aggregateRootVersion() ?? 1,
            'event_version' => $event->eventVersion(),
            'event_class' => $this->getEventClass(get_class($event)),
            'meta_data' => new MetaData(
                metaData: $event->metaData(),
                createdAt: $createdAt->toDateTimeString(),
                id: $id
            ),
            'created_at' => $createdAt->getTimestamp(),
        ]);

        $this->writeStoredEventToDynamo($storedEvent);

        return $storedEvent;
    }

    private function writeStoredEventToDynamo(StoredEvent $storedEvent): void
    {
        $storedEventArray = $storedEvent->toArray();

        //Fix an incorrect types in StoredEvent that upset DynamoDb.
        $storedEventArray['aggregate_version'] = (int) $storedEventArray['aggregate_version'];
        $storedEventArray['created_at'] = (int) $storedEventArray['created_at'];

        //Duplicate id to work around dynamo indexing limitations, allowing consistent ordering.
        $storedEventArray['sort_id'] = $storedEventArray['id'];

        $putItemRequest = [
            'TableName' => config('event-sourcing-dynamodb.stored-event-table'),
            'Item' => $this->dynamoMarshaler->marshalItem($storedEventArray),
        ];

        $this->dynamo->putItem($putItemRequest);
    }

    public function persistMany(array $events, string $uuid = null): LazyCollection
    {
        //TODO: Test coverage
        // TODO: A more efficient DynamoDb implementation is possible with batching.
        $storedEvents = [];

        /** @var ShouldBeStored $event */
        foreach ($events as $event) {
            $storedEvents[] = $this->persist($event, $uuid);
        }

        return new LazyCollection($storedEvents);
    }

    public function update(StoredEvent $storedEvent): StoredEvent
    {
        $this->writeStoredEventToDynamo($storedEvent);

        return $storedEvent;
    }

    public function getLatestAggregateVersion(string $aggregateUuid): int
    {
        $result = $this->dynamo->query([
            'TableName' => $this->table,
            'IndexName' => 'aggregate_uuid-version-index',
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid',
            'ExpressionAttributeValues' => [
                ':aggregate_uuid' => ['S' => $aggregateUuid],
            ],
            'ProjectionExpression' => 'aggregate_version',
            'ScanIndexForward' => false,
            'Limit' => 1,
        ]);

        $item = $result->get('Items')[0];

        $item = $this->dynamoMarshaler->unmarshalItem($item);

        return $item['aggregate_version'];
    }

    private function getEventClass(string $class): string
    {
        $map = config('event-sourcing.event_class_map', []);

        if (! empty($map) && in_array($class, $map)) {
            return array_search($class, $map, true);
        }

        return $class;
    }
}
