<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\WriteRequestBatch;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\IdGenerator;
use Illuminate\Support\Collection;
use Spatie\EventSourcing\Snapshots\Snapshot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;

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

        $mostRecentSnapshotItem = $this->dynamoMarshaler->unmarshalItem(
            data: $mostRecentSnapshotResult->get('Items')[0]
        );

        return $this->retrieveById(
            id: $mostRecentSnapshotItem['id'],
            aggregateUuid: $aggregateUuid,
            aggregateVersion: $mostRecentSnapshotItem['aggregate_version'],
            partsCount: $mostRecentSnapshotItem['parts_count']
        );
    }

    private function retrieveById(int $id, string $aggregateUuid, int $aggregateVersion, int $partsCount): Snapshot
    {
        $keys = $this->generateKeysForSnapshotParts(
            id: $id,
            aggregateUuid: $aggregateUuid,
            partsCount: $partsCount
        );

        $snapshotDataParts = [];
        $unprocessedKeys = [];
        $first = true;

        while ($first || ! empty($unprocessedKeys)) {

            $result = $this->dynamo->batchGetItem([
                'RequestItems' => [
                    $this->table => [
                        'Keys' => $first ? $keys : $unprocessedKeys,
                        'ProjectionExpression' => 'part, snapshot_data',
                    ],
                ],

            ]);

            $first = false;

            $unprocessedKeys = $result->get('UnprocessedKeys')[$this->table]['Keys'] ?? [];

            foreach ($result->get('Responses')[$this->table] as $snapshotItem) {
                $item = $this->dynamoMarshaler->unmarshalItem($snapshotItem);
                $snapshotDataParts[$item['part']] = $item['snapshot_data'];
            }
        }

        ksort($snapshotDataParts, SORT_NUMERIC);

        return new Snapshot(
            aggregateUuid: $aggregateUuid,
            aggregateVersion: $aggregateVersion,
            state: $this->stateSerializer->combineAndDeserializeState(
                stateParts: $snapshotDataParts
            )
        );
    }

    private function generateKeysForSnapshotParts(int $id, string $aggregateUuid, int $partsCount): array
    {
        return (new Collection(range(0, $partsCount - 1)))
            ->transform(
                function (int $partNumber) use ($id, $aggregateUuid): array {
                    $partForId = SnapshotPart::partForId($partNumber);

                    return [
                        'aggregate_uuid' => ['S' => $aggregateUuid],
                        'id_part' => ['S' => "{$id}_{$partForId}"],
                    ];
                }
            )->toArray();
    }

    public function persist(Snapshot $snapshot): Snapshot
    {
        $id = $this->idGenerator->generateId();

        $serializedStateParts = $this->stateSerializer->serializeAndSplitState(
            state: $snapshot->state
        );

        $partsCount = count($serializedStateParts);

        $writeRequestBatch = new WriteRequestBatch(
            client: $this->dynamo,
            config: ['table' => $this->table, 'autoflush' => false]
        );

        foreach ($serializedStateParts as $index => $statePart) {

            $snapshotPart = new SnapshotPart(
                id: $id,
                aggregateUuid: $snapshot->aggregateUuid,
                aggregateVersion: $snapshot->aggregateVersion,
                part: $index,
                partsCount: $partsCount,
                snapshotData: $statePart
            );

            $writeRequestBatch->put(
                item: $this->dynamoMarshaler->marshalItem($snapshotPart->toArray())
            );
        }

        $writeRequestBatch->flush();

        return $snapshot;
    }
}
