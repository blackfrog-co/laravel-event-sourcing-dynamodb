<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

use Illuminate\Contracts\Support\Arrayable;

readonly class SnapshotPart implements Arrayable
{
    public string $idPart;

    public function __construct(
        public int $id,
        public string $aggregateUuid,
        public int $aggregateVersion,
        public int $part,
        public int $partsCount,
        public mixed $snapshotData,
    ) {
        $partForId = str_pad((string) $this->part, 2, '0', STR_PAD_LEFT);
        $this->idPart = "{$this->id}_{$partForId}";
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'id_part' => $this->idPart,
            'aggregate_uuid' => $this->aggregateUuid,
            'aggregate_version' => $this->aggregateVersion,
            'part' => $this->part,
            'parts_count' => $this->partsCount,
            'snapshot_data' => $this->snapshotData,
        ];
    }
}
