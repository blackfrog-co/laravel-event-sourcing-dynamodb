<?php

use Aws\DynamoDb\Marshaler;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\MicroTimestampProvider;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\TimeStampIdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\Snapshots\DynamoDbSnapshotRepository;
use BlackFrog\LaravelEventSourcingDynamodb\Snapshots\StateSerializer;
use Random\Randomizer;
use Spatie\EventSourcing\Snapshots\Snapshot;

beforeEach(function () {
    $this->faker = Faker\Factory::create();

    $this->stateData = [
        'text' => $this->faker->paragraph(3),
        'int' => $this->faker->numberBetween(),
        'float' => $this->faker->randomFloat(),
        'int_64' => $this->faker->numberBetween(214748648, PHP_INT_MAX),
        'std_object' => (object) [
            'name' => $this->faker->name(),
            'uuid' => $this->faker->uuid(),
        ],
        'another_object' => new Snapshot('adsdasdassd', 4, ['blah' => 'bloo']),
    ];
    $this->createTables();

    $this->snapshotRepository = new DynamoDbSnapshotRepository(
        $this->getDynamoDbClient(),
        new TimeStampIdGenerator(new Randomizer(), new MicroTimestampProvider()),
        new Marshaler(),
        new StateSerializer()
    );
});

afterEach(function () {
    $this->deleteTables();
});

it('can store and retrieve a snapshot with huge state data', function () {
    $uuid = $this->faker->uuid();
    $stateData = $this->stateData;
    //Add enough data to guarantee exceeding dynamo record size limitations and trigger
    //our mechanics for breaking the data up into parts for storage.
    $stateData['text'] = $this->faker->paragraph(350000);
    $snapshot = new Snapshot(
        $uuid,
        1,
        $stateData
    );

    $this->snapshotRepository->persist($snapshot);

    $snapshot = $this->snapshotRepository->retrieve($uuid);

    expect($snapshot)
        ->toBeInstanceOf(Snapshot::class)
        ->and($snapshot->aggregateUuid)->toEqual($uuid)
        ->and($snapshot->aggregateVersion)->toEqual(1)
        ->and($snapshot->state)->toEqual($stateData);
});

it('can store and retrieve a snapshot with small state data', function () {
    $uuid = $this->faker->uuid();

    $snapshot = new Snapshot(
        $uuid,
        1,
        $this->stateData
    );

    $this->snapshotRepository->persist($snapshot);

    $snapshot = $this->snapshotRepository->retrieve($uuid);

    expect($snapshot)
        ->toBeInstanceOf(Snapshot::class)
        ->and($snapshot->aggregateUuid)->toEqual($uuid)
        ->and($snapshot->aggregateVersion)->toEqual(1)
        ->and($snapshot->state)->toEqual($this->stateData);
});

it('can retrieve the correct snapshot when multiple snapshots exists', function () {
    $targetUuid = $this->faker->uuid();

    $this->snapshotRepository->persist(new Snapshot(
        $this->faker->uuid(),
        2,
        $this->stateData
    ));

    $this->snapshotRepository->persist(new Snapshot(
        $this->faker->uuid(),
        4,
        $this->stateData
    ));

    $this->snapshotRepository->persist(new Snapshot(
        $targetUuid,
        1,
        $this->stateData
    ));

    $this->snapshotRepository->persist(new Snapshot(
        $this->faker->uuid(),
        3,
        $this->stateData
    ));

    $snapshot = $this->snapshotRepository->retrieve($targetUuid);

    expect($snapshot)
        ->toBeInstanceOf(Snapshot::class)
        ->and($snapshot->aggregateUuid)->toEqual($targetUuid)
        ->and($snapshot->aggregateVersion)->toEqual(1)
        ->and($snapshot->state)->toEqual($this->stateData);
});
