<?php

use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\DynamoStoredEventRepository;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

beforeAll(function () {
    class DummyStorableEvent extends ShouldBeStored
    {
        public function __construct(
            public readonly string $message
        ) {
        }
    }
});

beforeEach(function () {
    $this->createTables();

    $this->storedEventRepository = app(DynamoStoredEventRepository::class);
});

afterEach(function () {
    $this->deleteTables();
});

it('persists and retrieves an event', function () {
    $storedEvent = $this->storedEventRepository
        ->persist(new DummyStorableEvent('yahhh'), Uuid::uuid4());

    $retrievedEvent = $this->storedEventRepository->find($storedEvent->id);

    expect($retrievedEvent)->toEqual($storedEvent);

    $storedEvent2 = $this->storedEventRepository
        ->persist(new DummyStorableEvent('blahh'), Uuid::uuid4());

    $retrievedEvent2 = $this->storedEventRepository->find($storedEvent2->id);

    expect($retrievedEvent2)->toEqual($storedEvent2)
        ->and($retrievedEvent2)->not()->toEqual($storedEvent);
});

it('persists and retrieves an event with a null uuid', function () {
    $storedEvent = $this->storedEventRepository
        ->persist(new DummyStorableEvent('blahh'), null);

    $retrievedEvent = $this->storedEventRepository->find($storedEvent->id);

    expect($retrievedEvent)->toEqual($storedEvent)
        ->and($retrievedEvent->aggregate_uuid)->toEqual('null');
});

it('persists many events', function () {
    $aggregateUuid = Uuid::uuid4();
    $eventOne = new DummyStorableEvent('blahh');
    $eventTwo = new DummyStorableEvent('yahhh');

    $persistedEvents = $this->storedEventRepository->persistMany([$eventOne, $eventTwo], $aggregateUuid);

    expect($persistedEvents)->count()->toEqual(2)
        ->and($persistedEvents->first())->toBeInstanceOf(StoredEvent::class);

    $retrievedEvents = $this->storedEventRepository->retrieveAll($aggregateUuid);

    expect($retrievedEvents->count())->toEqual(2)
        ->and($retrievedEvents->first()->event->message)->toEqual($eventOne->message)
        ->and($retrievedEvents->last()->event->message)->toEqual($eventTwo->message);
});

it('retrieves all events in order', function () {
    $eventCount = 900;
    $x = 1;

    //We use a large string to guarantee pagination in response from dynamodb
    $randomString = Str::random(6650);
    $uuids = [Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4()];

    $firstEvent = null;
    $lastEvent = null;

    while ($x <= $eventCount) {
        $event = $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuids[rand(0, 3)]);

        if ($firstEvent === null) {
            $firstEvent = $event;
        }

        if ($x === $eventCount) {
            $lastEvent = $event;
        }

        $x++;
    }

    $storedEvents = $this->storedEventRepository->retrieveAll();

    expect($storedEvents->count())
        ->toEqual($eventCount)
        ->and($storedEvents)
        ->each(fn ($storedEvent) => $storedEvent->toBeInstanceOf(StoredEvent::class))
        //And event order is preserved
        ->and($storedEvents->first()->id)->toEqual($firstEvent->id)
        ->and($storedEvents->last()->id)->toEqual($lastEvent->id);
});

it('retrieves events in order by aggregateUuid', function () {
    //Generate 900 events with random aggregate Uuids
    $eventCount = 900;
    $x = 1;
    //We use a large string to guarantee pagination in responses from dynamodb
    $randomString = Str::random(6650);
    $uuids = [Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4()];

    while ($x <= $eventCount) {
        $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuids[rand(0, 3)]);
        $x++;
    }

    //Generate events with fixed aggregate Uuid
    $x = 1;
    $uuid = (string) Uuid::uuid4();

    $firstEvent = null;
    $lastEvent = null;

    while ($x <= $eventCount) {
        $event = $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuid);

        if ($firstEvent === null) {
            $firstEvent = $event;
        }

        if ($x === $eventCount) {
            $lastEvent = $event;
        }

        $x++;
    }

    $storedEvents = $this->storedEventRepository->retrieveAll($uuid);

    //900 matching events are retrieved
    expect($storedEvents->count())->toEqual($eventCount)
        ->and($storedEvents)->each(fn ($storedEvent) => $storedEvent->toBeInstanceOf(StoredEvent::class))
        //And event order is preserved
        ->and($storedEvents->first()->id)->toEqual($firstEvent->id)
        ->and($storedEvents->last()->id)->toEqual($lastEvent->id);
});

it('counts all events starting from an event id', function () {
    $eventCount = 900;
    $x = 1;

    //We use a large string to guarantee pagination in response from dynamodb
    $randomString = Str::random(6650);
    $uuids = [Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4()];

    $countFromEvent = null;

    while ($x <= $eventCount) {
        $event = $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuids[rand(0, 3)]);

        if ($x === 350) {
            $countFromEvent = $event;
        }

        $x++;
    }

    $countedEvents = $this->storedEventRepository->countAllStartingFrom($countFromEvent->id);
    expect($countedEvents)->toEqual(551);
});

it('counts all events for an aggregate root uuid starting from an event id', function () {
    $eventCount = 900;
    $x = 1;

    //We use a large string to guarantee pagination in responses from dynamodb
    $randomString = Str::random(6650);
    $uuids = [Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4()];

    //900 events for other aggregate roots
    while ($x <= $eventCount) {
        $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuids[rand(0, 3)]);
        $x++;
    }

    //900 events for our target aggregate root
    $uuid = Uuid::uuid4();
    $x = 1;
    $countFromEvent = null;
    while ($x <= $eventCount) {
        $event = $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuid);

        if ($x === 350) {
            $countFromEvent = $event;
        }

        $x++;
    }

    $countedEvents = $this->storedEventRepository->countAllStartingFrom($countFromEvent->id, $uuid);
    expect($countedEvents)->toEqual(551);
});

it('retrieves all events starting from an event id', function () {
    $eventCount = 900;
    $x = 1;

    //We use a large string to guarantee pagination in response from dynamodb
    $randomString = Str::random(6650);
    $uuids = [Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4()];

    $countFromEvent = null;

    while ($x <= $eventCount) {
        $event = $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuids[rand(0, 3)]);

        if ($x === 350) {
            $countFromEvent = $event;
        }

        $x++;
    }

    $events = $this->storedEventRepository->retrieveAllStartingFrom($countFromEvent->id);
    expect($events->count())->toEqual(551);
});

it('retrieves all events for an aggregate root uuid starting from an event id', function () {
    $eventCount = 900;
    $x = 1;

    //We use a large string to guarantee pagination in response from dynamodb
    $randomString = Str::random(6650);
    $uuids = [Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4()];

    //900 events for other aggregate roots
    while ($x <= $eventCount) {
        $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuids[rand(0, 3)]);
        $x++;
    }

    //900 events for our target aggregate root
    $uuid = Uuid::uuid4();
    $x = 1;
    $countFromEvent = null;
    while ($x <= $eventCount) {
        $event = $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuid);

        if ($x === 350) {
            $countFromEvent = $event;
        }

        $x++;
    }

    $events = $this->storedEventRepository->retrieveAllStartingFrom($countFromEvent->id, $uuid);
    expect($events->count())->toEqual(551);
});

it('gets the latest aggregate version for an aggregate root uuid', function () {
    $aggregateRootUuid = Uuid::uuid4();
    $event = new DummyStorableEvent('yahhh');

    $event->setAggregateRootVersion(1);
    $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $event->setAggregateRootVersion(3);
    $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $event->setAggregateRootVersion(5);
    $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $latestAggregateVersion = $this->storedEventRepository->getLatestAggregateVersion($aggregateRootUuid);
    expect($latestAggregateVersion)->toBeInt()->toEqual(5);
});

it('returns 0 for latest aggregate version if no events exist', function () {
    $aggregateRootUuid = Uuid::uuid4();
    $latestAggregateVersion = $this->storedEventRepository->getLatestAggregateVersion($aggregateRootUuid);
    expect($latestAggregateVersion)->toBeInt()->toEqual(0);
});

it('retrieves events after a version for an aggregate root uuid', function () {
    $aggregateRootUuid = Uuid::uuid4();
    $event = new DummyStorableEvent('yahhh');

    $event->setAggregateRootVersion(1);
    $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $event->setAggregateRootVersion(3);
    $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $event->setAggregateRootVersion(3);
    $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $event->setAggregateRootVersion(3);
    $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $event->setAggregateRootVersion(4);
    $firstEvent = $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $event->setAggregateRootVersion(4);
    $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $event->setAggregateRootVersion(5);
    $lastEvent = $this->storedEventRepository
        ->persist($event, $aggregateRootUuid);

    $events = $this->storedEventRepository->retrieveAllAfterVersion(3, $aggregateRootUuid);

    expect($events->count())->toEqual(3)
        ->and($events->first()->id)->toEqual($firstEvent->id)
        ->and($events->last()->id)->toEqual($lastEvent->id);
});

it('returns an empty collection when no events after a version for an aggregate root uuid', function () {
    $aggregateRootUuid = Uuid::uuid4();

    $events = $this->storedEventRepository->retrieveAllAfterVersion(3, $aggregateRootUuid);

    expect($events->count())->toEqual(0)->and($events->first())->toBeNull();
});

it('returns an empty collection when fetching all events when no events exists', function () {
    $events = $this->storedEventRepository->retrieveAll();

    expect($events->count())->toEqual(0)->and($events->first())->toBeNull();
});
