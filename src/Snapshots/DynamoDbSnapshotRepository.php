<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

use Spatie\EventSourcing\Snapshots\Snapshot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;

class DynamoDbSnapshotRepository implements SnapshotRepository
{
    public function retrieve(string $aggregateUuid): ?Snapshot
    {
        // TODO: Implement retrieve() method.
        // This will need to reconstitute the snapshot parts to reform the state.
    }

    public function persist(Snapshot $snapshot): Snapshot
    {
        // TODO: Implement persist() method.
        //This will need to break the snapshot down into parts to work around dynamodb record/request size limitations.
    }


}
