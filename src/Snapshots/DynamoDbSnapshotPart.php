<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

use Illuminate\Contracts\Support\Arrayable;

class DynamoDbSnapshotPart implements Arrayable
{
    public function __construct(
        public readonly int $id,
        public readonly string $aggregateUuid,
        public readonly string $statePart
    ) {
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'aggregate_uuid' => $this->aggregateUuid,
            'data' => $this->statePart,
        ];
    }
}
