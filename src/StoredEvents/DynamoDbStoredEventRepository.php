<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\WriteRequestBatch;
use Aws\ResultPaginator;
use Carbon\CarbonInterface;
use Illuminate\Support\LazyCollection;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

readonly class DynamoDbStoredEventRepository implements StoredEventRepository
{
    protected string $table;

    protected bool $readConsistency;

    public function __construct(
        private DynamoDbClient $dynamo,
        private Marshaler $dynamoMarshaler,
        private StoredEventFactory $storedEventFactory,
    ) {
        $this->table = (string) config(
            'event-sourcing-dynamodb.stored_event_table',
            'stored_events'
        );
        $this->readConsistency = (bool) config(
            'event-sourcing-dynamodb.read_consistency',
            false
        );
    }

    public function find(int $id): StoredEvent
    {
        $result = $this->dynamo->query([
            'TableName' => $this->table,
            'IndexName' => 'id-sort_id-index',
            'KeyConditionExpression' => 'id = :id',
            'ExpressionAttributeValues' => [
                ':id' => ['N' => $id],
            ],
            'Limit' => 1,
        ]);

        $item = $this->dynamoMarshaler->unmarshalItem(
            $result->get('Items')[0]
        );

        return $this->storedEventFactory->storedEventFromDynamoItem($item);
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
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid',
            'ExpressionAttributeValues' => [':aggregate_uuid' => ['S' => $uuid]],
            'ConsistentRead' => $this->readConsistency,
        ]);

        return $this->lazyCollectionFromPaginator($resultPaginator);
    }

    private function lazyCollectionFromPaginator(ResultPaginator $paginator): LazyCollection
    {
        return LazyCollection::make(
            new DynamoEventIterator(
                $paginator,
                function (array $awsItem): StoredEvent {
                    $dynamoItem = $this->dynamoMarshaler->unmarshalItem($awsItem);

                    return $this->storedEventFactory->storedEventFromDynamoItem($dynamoItem);
                }
            )
        );
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
            'ConsistentRead' => $this->readConsistency,
        ]);

        return $this->lazyCollectionFromPaginator($resultPaginator);
    }

    private function retrieveAllStartingFromByUuid(int $startingFrom, string $uuid): LazyCollection
    {
        $resultPaginator = $this->dynamo->getPaginator('Query', [
            'TableName' => $this->table,
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid AND id >= :id',
            'ExpressionAttributeValues' => [
                ':aggregate_uuid' => ['S' => $uuid],
                ':id' => ['N' => $startingFrom],
            ],
            'ConsistentRead' => $this->readConsistency,
        ]);

        return $this->lazyCollectionFromPaginator($resultPaginator);
    }

    public function retrieveAllAfterVersion(int $aggregateVersion, string $aggregateUuid): LazyCollection
    {
        //For this version and this aggregate uuid get the most recent event id.

        $lastEventIdForAggregateVersion = $this->lastEventIdForAggregateVersion($aggregateUuid, $aggregateVersion);

        $resultPaginator = $this->dynamo->getPaginator('Query', [
            'TableName' => $this->table,
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid AND id > :id',
            'ExpressionAttributeValues' => [
                ':aggregate_uuid' => ['S' => $aggregateUuid],
                ':id' => ['N' => $lastEventIdForAggregateVersion],
            ],
            'ConsistentRead' => $this->readConsistency,
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
            'Select' => 'COUNT',
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
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid AND id >= :id',
            'ExpressionAttributeValues' => [
                ':aggregate_uuid' => ['S' => $uuid],
                ':id' => ['N' => $startingFrom],
            ],
            'Select' => 'COUNT',
            'ConsistentRead' => $this->readConsistency,
        ]);

        $count = 0;

        while ($result = $resultPaginator->current()) {
            $count += $result->get('Count');
            $resultPaginator->next();
        }

        return $count;
    }

    public function persist(ShouldBeStored $event, string $uuid = null): StoredEvent
    {
        return $this->writeStoredEventToDynamo(
            $this->storedEventFactory->createStoredEvent(
                event: $event,
                uuid: $uuid
            )
        );
    }

    private function writeStoredEventToDynamo(StoredEvent $storedEvent): StoredEvent
    {
        $putItemRequest = [
            'TableName' => config('event-sourcing-dynamodb.stored-event-table'),
            'Item' => $this->storedEventToDynamoDbItem($storedEvent),
        ];

        $this->dynamo->putItem($putItemRequest);

        return $storedEvent;
    }

    private function storedEventToDynamoDbItem(StoredEvent $storedEvent): array
    {
        $storedEventArray = $storedEvent->toArray();

        //Fix an incorrect types in StoredEvent that upset DynamoDb.
        $storedEventArray['aggregate_version'] = (int) $storedEventArray['aggregate_version'];
        $storedEventArray['created_at'] = (int) $storedEventArray['created_at'];

        //Duplicate id to work around dynamo indexing limitations, allowing consistent ordering.
        $storedEventArray['sort_id'] = $storedEventArray['id'];
        $storedEventArray['version_uuid'] = $storedEventArray['aggregate_version'].'_'.$storedEventArray['aggregate_uuid'];

        //Format carbon object for storage. Temporary bodge, this needs more work.
        $metaDataCreatedAt = $storedEventArray['meta_data']['created-at'];
        if ($metaDataCreatedAt instanceof CarbonInterface) {
            $metaDataCreatedAt = (string) $metaDataCreatedAt->timestamp;
        }
        $storedEventArray['meta_data']['created-at'] = $metaDataCreatedAt;

        return $this->dynamoMarshaler->marshalItem($storedEventArray);
    }

    public function persistMany(array $events, string $uuid = null): LazyCollection
    {
        $batch = new WriteRequestBatch($this->dynamo, ['table' => $this->table, 'autoflush' => false]);
        $storedEvents = [];

        /** @var ShouldBeStored $event */
        foreach ($events as $event) {
            $storedEvent = $this->storedEventFactory->createStoredEvent(
                event: $event,
                uuid: $uuid
            );
            $storedEvents[] = $storedEvent;
            $batch->put($this->storedEventToDynamoDbItem($storedEvent));
        }

        $batch->flush();

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
            'ConsistentRead' => $this->readConsistency,
        ]);

        if ($result->get('Count') === 0) {
            return 0;
        }

        $item = $this->dynamoMarshaler->unmarshalItem(
            $result->get('Items')[0]
        );

        return $item['aggregate_version'];
    }

    private function lastEventIdForAggregateVersion(string $uuid, int $version): int
    {
        $result = $this->dynamo->query([
            'TableName' => $this->table,
            'IndexName' => 'version_uuid-id-index',
            'KeyConditionExpression' => 'version_uuid = :version_uuid',
            'ExpressionAttributeValues' => [
                ':version_uuid' => ['S' => $version.'_'.$uuid],
            ],
            'ProjectionExpression' => 'id',
            'ScanIndexForward' => false,
            'Limit' => 1,
            'ConsistentRead' => $this->readConsistency,
        ]);

        if ($result->get('Count') === 0) {
            return 0;
        }

        $item = $this->dynamoMarshaler->unmarshalItem(
            $result->get('Items')[0]
        );

        return $item['id'];
    }
}
