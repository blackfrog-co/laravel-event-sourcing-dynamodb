<?php

use Aws\DynamoDb\Marshaler;
use BlackFrog\LaravelEventSourcingDynamodb\Commands\CreateTables;
use BlackFrog\LaravelEventSourcingDynamodb\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\Repositories\DynamoDbStoredEventRepository;
use Ramsey\Uuid\Uuid;
use Random\Randomizer;

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
    $this->artisan(CreateTables::class);
    $this->storedEventRepository = new DynamoDbStoredEventRepository(
        $this->getDynamoDbClient(),
        new IdGenerator(new Randomizer()),
        new Marshaler()
    );
});

afterEach(function () {
    $this->getDynamoDbClient()->deleteTable(
        ['TableName' => 'stored_events']
    );
});

it('it can store and retrieve an event', function () {
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
