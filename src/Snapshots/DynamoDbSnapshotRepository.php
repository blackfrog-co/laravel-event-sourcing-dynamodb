<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\IdGenerator;
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
        private StateSerializer $stateSerializer
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

        $idItem = $this->dynamoMarshaler->unmarshalItem($mostRecentSnapshotResult->get('Items')[0]);

        return $this->retrieveById($idItem['id'], $aggregateUuid, $idItem['aggregate_version'], $idItem['parts_count']);
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

        //TODO: Reattempt batch get item implementation.
        $keys->each(function (array $keys) use (&$snapshotParts): void {
            $getItemResult = $this->dynamo->getItem([
                'TableName' => $this->table,
                'Key' => $keys,
            ]);

            $item = $this->dynamoMarshaler->unmarshalItem($getItemResult->get('Item'));
            $snapshotParts->put(
                $item['part'],
                $item['data'],
            );
        });

        $stateData = $this->stateSerializer->combineAndDeserializeState($snapshotParts->sortKeys()->toArray());

        return new Snapshot($aggregateUuid, $aggregateVersion, $stateData);
    }

    public function persist(Snapshot $snapshot): Snapshot
    {
        $id = $this->idGenerator->generateId();
        $aggregateUuid = $snapshot->aggregateUuid;

        $serializedStateParts = $this->stateSerializer->serializeAndSplitState($snapshot->state);

        $snapshotParts = [];
        foreach ($serializedStateParts as $statePart) {
            $snapshotParts[] = [
                'id' => $id,
                'aggregate_uuid' => $aggregateUuid,
                'aggregate_version' => $snapshot->aggregateVersion,
                'data' => $statePart,
            ];
        }
        $snapshotPartsCount = count($snapshotParts);

        $dynamoItems = [];

        foreach ($snapshotParts as $index => $snapshotPart) {
            $snapshotPart['part'] = $index;
            $partForId = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $snapshotPart['id_part'] = "{$id}_{$partForId}";
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
                    $batchRequest['RequestItems'][$this->table][] = ['PutRequest' => ['Item' => $dynamoItem]];
                });

                $response = $this->dynamo->batchWriteItem($batchRequest);
                //TODO: handle unprocessed items in response
            });

        return $snapshot;
    }
}
