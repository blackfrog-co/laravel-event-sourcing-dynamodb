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

readonly class DynamoDbSnapshotRepository implements SnapshotRepository
{
    protected string $table;

    protected bool $readConsistency;

    public function __construct(
        private DynamoDbClient $dynamo,
        private IdGenerator $idGenerator,
        private Marshaler $dynamoMarshaler,
        private StateSerializer $stateSerializer
    ) {
        $this->table = config(
            'event-sourcing-dynamodb.snapshot_table',
            'snapshots'
        );
        $this->readConsistency = (bool) config(
            'event-sourcing-dynamodb.read_consistency',
            false
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
            'ConsistentRead' => $this->readConsistency,
        ]);

        if ($mostRecentSnapshotResult->get('Count') === 0) {
            return null;
        }

        $mostRecentSnapshotItem = $this->dynamoMarshaler->unmarshalItem(
            data: $mostRecentSnapshotResult->get('Items')[0]
        );

        if ($mostRecentSnapshotItem['parts_count'] === 1) {
            return new Snapshot(
                aggregateUuid: $aggregateUuid,
                aggregateVersion: $mostRecentSnapshotItem['aggregate_version'],
                state: $this->stateSerializer->deserializeState(
                    $mostRecentSnapshotItem['snapshot_data']
                )
            );
        }

        return $this->retrieveSnapshotParts(
            id: $mostRecentSnapshotItem['id'],
            aggregateUuid: $aggregateUuid,
            aggregateVersion: $mostRecentSnapshotItem['aggregate_version'],
            partsCount: $mostRecentSnapshotItem['parts_count']
        );
    }

    private function retrieveSnapshotParts(
        int $id,
        string $aggregateUuid,
        int $aggregateVersion,
        int $partsCount
    ): Snapshot {
        $keys = $this->generateKeysForSnapshotParts(
            id: $id,
            aggregateUuid: $aggregateUuid,
            partsCount: $partsCount
        );

        $snapshotStateParts = [];

        while (! empty($keys)) {
            $result = $this->dynamo->batchGetItem([
                'RequestItems' => [
                    $this->table => [
                        'Keys' => $keys,
                        'ProjectionExpression' => 'part, snapshot_data',
                        'ConsistentRead' => $this->readConsistency,
                    ],
                ],
            ]);

            foreach ($result->get('Responses')[$this->table] as $snapshotItem) {
                $item = $this->dynamoMarshaler->unmarshalItem($snapshotItem);
                $snapshotStateParts[$item['part']] = $item['snapshot_data'];
            }

            $keys = $result->get('UnprocessedKeys')[$this->table]['Keys'] ?? [];
        }

        ksort($snapshotStateParts, SORT_NUMERIC);

        return new Snapshot(
            aggregateUuid: $aggregateUuid,
            aggregateVersion: $aggregateVersion,
            state: $this->stateSerializer->combineAndDeserializeState(
                stateParts: $snapshotStateParts
            )
        );
    }

    private function generateKeysForSnapshotParts(int $id, string $aggregateUuid, int $partsCount): array
    {
        return (new Collection(range(0, $partsCount - 1)))
            ->transform(
                function (int $partNumber) use ($id, $aggregateUuid): array {
                    return [
                        'aggregate_uuid' => ['S' => $aggregateUuid],
                        'id_part' => ['S' => SnapshotPart::generateIdPart($id, $partNumber)],
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
