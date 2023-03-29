<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

use Illuminate\Contracts\Support\Arrayable;

class SnapshotPart implements Arrayable
{
    public readonly string $idPart;

    public function __construct(
        public readonly int $id,
        public readonly string $aggregateUuid,
        public readonly int $aggregateVersion,
        public readonly int $part,
        public readonly int $partsCount,
        public readonly mixed $data,
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
            'data' => $this->data,
        ];
    }
}
