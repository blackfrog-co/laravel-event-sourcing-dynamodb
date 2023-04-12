<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents;

use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\IdGenerator;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Spatie\EventSourcing\EventSerializers\JsonEventSerializer;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

readonly class StoredEventFactory
{
    private array $eventClassMap;

    public function __construct(
        private IdGenerator $idGenerator,
        private JsonEventSerializer $eventSerializer
    ) {
        $this->eventClassMap = config('event-sourcing.event_class_map', []);
    }

    public function createStoredEvent(ShouldBeStored $event, string $uuid = null): StoredEvent
    {
        $id = $this->idGenerator->generateId();
        $createdAt = Carbon::now();

        return new StoredEvent([
            'id' => $id,
            'event_properties' => $this->eventSerializer->serialize(clone $event),
            'aggregate_uuid' => $uuid ?? 'null',
            'aggregate_version' => $event->aggregateRootVersion() ?? 1,
            'event_version' => $event->eventVersion(),
            'event_class' => $this->getEventClass(get_class($event)),
            'meta_data' => new MetaData(
                metaData: $event->metaData(),
                createdAt: $createdAt->toDateTimeString(),
                id: $id
            ),
            'created_at' => $createdAt->getTimestamp(),
        ]);
    }

    public function storedEventFromDynamoItem(array $dynamoItem): StoredEvent
    {
        return new StoredEvent([
            'id' => $dynamoItem['id'],
            'event_properties' => $dynamoItem['event_properties'],
            'aggregate_uuid' => $dynamoItem['aggregate_uuid'] ?? 'null',
            'aggregate_version' => (string) $dynamoItem['aggregate_version'],
            'event_version' => $dynamoItem['event_version'],
            'event_class' => $dynamoItem['event_class'],
            'meta_data' => new MetaData(
                Arr::except($dynamoItem['meta_data'], ['stored-event-id', 'created-at']),
                $dynamoItem['meta_data']['created-at'],
                $dynamoItem['meta_data']['stored-event-id']
            ),
            'created_at' => $dynamoItem['created_at'],
        ]);
    }

    private function getEventClass(string $class): string
    {
        if (! empty($this->eventClassMap) && in_array($class, $this->eventClassMap)) {
            return array_search($class, $this->eventClassMap, true);
        }

        return $class;
    }
}
