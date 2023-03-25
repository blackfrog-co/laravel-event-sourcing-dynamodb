<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\Repositories;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
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
            'ConsistentRead' => false,
            'Key' => [
                'id' => [
                    'N' => $id,
                ],
            ],
        ]);

        $dynamoItem = $this->dynamoMarshaler->unmarshalItem($dynamoResult->get('Item'));

        return new StoredEvent([
            'id' => $dynamoItem['id'],
            //TODO: make this better
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
            'created_at' => $createdAt->timestamp,
        ]);

        $storedEventArray = $storedEvent->toArray();

        //Fix an incorrect type issue in StoredEvent.
        $storedEventArray['aggregate_version'] = (int) $storedEventArray['aggregate_version'];

        $putItemRequest = [
            'TableName' => config('event-sourcing-dynamodb.stored-event-table'),
            'Item' => $this->dynamoMarshaler->marshalItem($storedEventArray),
        ];

        $this->dynamo->putItem($putItemRequest);

        return $storedEvent;
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

    private function getEventClass(string $class): string
    {
        $map = config('event-sourcing.event_class_map', []);

        if (! empty($map) && in_array($class, $map)) {
            return array_search($class, $map, true);
        }

        return $class;
    }
}
