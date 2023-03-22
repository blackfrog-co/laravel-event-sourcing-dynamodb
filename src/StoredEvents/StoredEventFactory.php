<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents;

use Spatie\EventSourcing\StoredEvents\StoredEvent;

class StoredEventFactory
{
    public function makeFromDynamoDbRow(): StoredEvent
    {
    }
}
