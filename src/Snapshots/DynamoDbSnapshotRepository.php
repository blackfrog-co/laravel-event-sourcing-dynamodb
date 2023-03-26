<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use BlackFrog\LaravelEventSourcingDynamodb\IdGenerator;
use Illuminate\Support\Collection;
use Spatie\EventSourcing\Snapshots\Snapshot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;

class DynamoDbSnapshotRepository implements SnapshotRepository
{
    protected string $table;

    public function __construct(
        private DynamoDbClient $dynamo,
        private IdGenerator $idGenerator,
        private Marshaler $dynamoMarshaler,
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

        if ($mostRecentSnapshotResult->get('Count') == 0) {
            return null;
        }

        $idItem = $this->dynamoMarshaler->unmarshalItem($mostRecentSnapshotResult->get('Items')[0]);

        return $this->retrieveById($idItem['id'], $aggregateUuid, $idItem['aggregate_version'], $idItem['parts_count']);
    }

    private function retrieveById(int $id, string $aggregateUuid, int $aggregateVersion, int $partsCount): Snapshot
    {
        $keys = (new Collection(range(0, $partsCount - 1)))
            ->transform(function (int $item) use ($id, $aggregateUuid): array {
                return [
                    'aggregate_uuid' => ['S' => $aggregateUuid],
                    'id_part' => ['S' => "{$id}_{$item}"],
                ];
            });

        $snapshotParts = new Collection;

        $keys->chunk(25)
            ->each(function (Collection $keys) use (&$snapshotParts): void {
                $batchGetResult = $this->dynamo->batchGetItem([
                    $this->table => [
                        'Keys' => $keys->toArray(),
                    ],
                ]);

                $items = $batchGetResult->search("Responses.{$this->table}");

                foreach ($items as $item) {
                    $item = $this->dynamoMarshaler->unmarshalItem($item);
                    $snapshotParts->put(
                        $item['part'],
                        $item['data'],
                    );
                }
            });

        $snapshotParts = $snapshotParts->sortKeys();

        $stateDataSerialized = $this->combineSerializedState($snapshotParts->toArray());
        $stateData = $this->deserializeState($stateDataSerialized);

        return new Snapshot($aggregateUuid, $aggregateVersion, $stateData);
    }

    public function persist(Snapshot $snapshot): Snapshot
    {
        $id = $this->idGenerator->generateId();
        $aggregateUuid = $snapshot->aggregateUuid;

        $serializedState = $this->serializeState($snapshot->state);
        $serializedStateParts = $this->splitSerializedState($serializedState);

        $snapshotParts = [];
        foreach ($serializedStateParts as $statePart) {
            $snapshotParts[] = [
                'id' => $id,
                'aggregated_uuid' => $aggregateUuid,
                'data' => $statePart,
            ];
        }
        $snapshotPartsCount = count($snapshotParts);

        $dynamoItems = [];

        foreach ($snapshotParts as $index => $snapshotPart) {
            $snapshotPart['part'] = $index;
            $snapshotPart['id_part'] = "{$id}_{$index}";
            $snapshotPart['parts_count'] = $snapshotPartsCount;
            $dynamoItems[] = $this->dynamoMarshaler->marshalItem($snapshotPart);
        }

        (new Collection($dynamoItems))
            ->chunk(25)
            ->each(function (Collection $dynamoItems): void {
                $batchRequest = ['RequestItems' => [
                    $this->table => [],
                ]];

                $dynamoItems->each(function ($dynamoItem) use (&$batchRequest): void {
                    $batchRequest['requestItems'][$this->table][] = $dynamoItem;
                });

                $this->dynamo->batchWriteItem($batchRequest);
            });

        return $snapshot;
    }

    private function splitSerializedState(string $state): array
    {
        return str_split($state, 380_000);
    }

    private function combineSerializedState(array $stateData)
    {
        return implode('', $stateData);
    }

    private function serializeState(mixed $state): string
    {
        return base64_encode(serialize($state));
    }

    private function deserializeState(string $state): mixed
    {
        return unserialize(base64_decode($state));
    }
}
