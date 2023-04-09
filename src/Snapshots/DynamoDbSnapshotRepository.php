<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\WriteRequestBatch;
use Aws\ResultPaginator;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\MetaData;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Spatie\EventSourcing\Snapshots\Snapshot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

class DynamoDbSnapshotRepository implements SnapshotRepository
{
    protected string $table;

    public function __construct(
        private readonly DynamoDbClient $dynamo,
        private readonly IdGenerator $idGenerator,
        private readonly Marshaler $dynamoMarshaler,
        private readonly StateSerializer $stateSerializer
    ) {
        $this->table = config(
            'event-sourcing-dynamodb.snapshot_table',
            'snapshots'
        );
    }

    public function retrieve(string $aggregateUuid): ?Snapshot
    {
        $mostRecentSnapshotResult = $this->dynamo->query([
            'TableName' => $this->table,
            'KeyConditionExpression' => 'aggregate_uuid = :aggregate_uuid',
            'ExpressionAttributeValues' => [':aggregate_uuid' => ['S' => $aggregateUuid]],
            'ScanIndexForward' => false,
            'Limit' => 1,
            'ProjectionExpression' => 'id, parts_count, aggregate_version',
        ]);

        if ($mostRecentSnapshotResult->get('Count') === 0) {
            return null;
        }

        //TODO: in the case that parts_count is 0 we could avoid making further requests.

        $idItem = $this->dynamoMarshaler->unmarshalItem(
            data: $mostRecentSnapshotResult->get('Items')[0]
        );

        return $this->retrieveById(
            id: $idItem['id'],
            aggregateUuid: $aggregateUuid,
            aggregateVersion: $idItem['aggregate_version'],
            partsCount: $idItem['parts_count']
        );
    }

    private function retrieveById(int $id, string $aggregateUuid, int $aggregateVersion, int $partsCount): Snapshot
    {
        $keys = (new Collection(range(0, $partsCount - 1)))
            ->transform(function (int $item) use ($id, $aggregateUuid): array {
                $partForId = str_pad((string) $item, 2, '0', STR_PAD_LEFT);

                return [
                    'aggregate_uuid' => ['S' => $aggregateUuid],
                    'id_part' => ['S' => "{$id}_{$partForId}"],
                ];
            });

        $snapshotParts = new Collection;

        $first = true;
        $unprocessedKeys = [];

        while ($first || ! empty($unprocessedKeys)) {

            $request = [
                'RequestItems' => [
                    $this->table => ['Keys' => $first ? $keys->toArray() : $unprocessedKeys],
                ],
            ];

            $result = $this->dynamo->batchGetItem($request);

            $first = false;

            $unprocessedKeys = $result->get('UnprocessedKeys')[$this->table]['Keys'] ?? [];

            $snapshotItems = collect($result->get('Responses')[$this->table]);

            $snapshotItems->each(
                function (array $item) use (&$snapshotParts): void {
                    $item = $this->dynamoMarshaler->unmarshalItem($item);

                    $snapshotParts->put(
                        $item['part'],
                        $item['data'],
                    );
                }
            );
        }

        $stateData = $this->stateSerializer->combineAndDeserializeState(
            stateParts: $snapshotParts->sortKeys()->toArray()
        );

        return new Snapshot($aggregateUuid, $aggregateVersion, $stateData);
    }

    public function persist(Snapshot $snapshot): Snapshot
    {
        $id = $this->idGenerator->generateId();

        $serializedStateParts = $this->stateSerializer->serializeAndSplitState($snapshot->state);

        $partsCount = count($serializedStateParts);

        $batch = new WriteRequestBatch($this->dynamo, ['table' => $this->table, 'autoflush' => false]);

        foreach ($serializedStateParts as $index => $statePart) {
            $snapshotPart = new SnapshotPart(
                id: $id,
                aggregateUuid: $snapshot->aggregateUuid,
                aggregateVersion: $snapshot->aggregateVersion,
                part: $index,
                partsCount: $partsCount,
                data: $statePart
            );
            $batch->put($this->dynamoMarshaler->marshalItem($snapshotPart->toArray()));
        }

        $batch->flush(untilEmpty: true);

        return $snapshot;
    }

    private function retrieveSnapshotParts(string $uuid): LazyCollection
    {
        $resultPaginator = $this->dynamo->batchGetItem([

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
}
