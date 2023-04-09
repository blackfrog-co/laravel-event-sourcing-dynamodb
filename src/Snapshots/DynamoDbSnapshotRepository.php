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

        //TODO: Reattempt a batch get item implementation.
        $keys->each(
            function (array $keys) use (&$snapshotParts): void {
                $getItemResult = $this->dynamo->getItem([
                    'TableName' => $this->table,
                    'Key' => $keys,
                ]);

                $item = $this->dynamoMarshaler->unmarshalItem($getItemResult->get('Item'));

                $snapshotParts->put(
                    $item['part'],
                    $item['data'],
                );
            }
        );

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
}
