<?php

use Aws\DynamoDb\Marshaler;
use BlackFrog\LaravelEventSourcingDynamodb\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\Repositories\DynamoDbStoredEventRepository;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Random\Randomizer;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

beforeAll(function () {
    class DummyStorableEvent extends \Spatie\EventSourcing\StoredEvents\ShouldBeStored
    {
        public function __construct(
          public readonly string $message
        ) {
        }
    }
});
beforeEach(function () {
    $this->createTable();

    $this->storedEventRepository = new DynamoDbStoredEventRepository(
        $this->getDynamoDbClient(),
        new IdGenerator(new Randomizer()),
        new Marshaler()
    );
});

afterEach(function () {
    $this->deleteTableIfExists('stored_events');
});

it('can store and retrieve an event', function () {
    $storedEvent = $this->storedEventRepository
        ->persist(new DummyStorableEvent('yahhh'), Uuid::uuid4());

    $retrievedEvent = $this->storedEventRepository->find($storedEvent->id);

    expect($retrievedEvent)->toEqual($storedEvent);

    $storedEvent2 = $this->storedEventRepository
        ->persist(new DummyStorableEvent('blahh'), Uuid::uuid4());

    $retrievedEvent2 = $this->storedEventRepository->find($storedEvent2->id);

    expect($retrievedEvent2)->toEqual($storedEvent2);
    expect($retrievedEvent2)->not()->toEqual($storedEvent);
});

it('retrieves all events', function () {
    $eventCount = 900;
    $x = 1;

    $randomString = Str::random(6650);
    $uuids = [Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4()];
    while ($x <= $eventCount) {
        $this->storedEventRepository
            ->persist(new DummyStorableEvent($randomString), $uuids[rand(0, 3)]);
        $x++;
    }

    $lazyCollection = $this->storedEventRepository->retrieveAll();

    expect($lazyCollection->count())
        ->toEqual($eventCount)
        ->and($lazyCollection)
        ->each(fn ($storedEvent) => $storedEvent->toBeInstanceOf(StoredEvent::class));
});

it('retrieves events in order by aggregateUuid', function () {
    //Generate 900 events with random aggregate Uuids
    $eventCount = 900;
    $x = 1;
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

    expect($storedEvents->count())->toEqual($eventCount);

    expect($storedEvents->first()->id)->toEqual($firstEvent->id);

    expect($storedEvents->last()->id)->toEqual($lastEvent->id);
});
