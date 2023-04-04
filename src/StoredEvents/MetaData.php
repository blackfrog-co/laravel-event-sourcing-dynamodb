<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents;

use Illuminate\Contracts\Support\Arrayable;

readonly class MetaData implements Arrayable
{
    public function __construct(
        public array $metaData,
        public string $createdAt,
        public int $id,
    ) {
    }

    public function toArray()
    {
        return $this->metaData + [
            \Spatie\EventSourcing\Enums\MetaData::CREATED_AT => $this->createdAt,
            \Spatie\EventSourcing\Enums\MetaData::STORED_EVENT_ID => $this->id,
        ];
    }
}
