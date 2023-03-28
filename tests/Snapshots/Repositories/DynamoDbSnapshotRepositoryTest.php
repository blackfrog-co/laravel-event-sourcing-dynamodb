<?php

use Aws\DynamoDb\Marshaler;
use BlackFrog\LaravelEventSourcingDynamodb\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\Snapshots\DynamoDbSnapshotRepository;
use Random\Randomizer;
use Spatie\EventSourcing\Snapshots\Snapshot;

beforeAll(function () {
});

beforeEach(function () {
    $this->faker = Faker\Factory::create();

    $this->largeStateData = [
        'text' => $this->faker->paragraph(350000),    //100000 gives 19 parts
        'int' => $this->faker->numberBetween(),
        'float' => $this->faker->randomFloat(),
        'int_64' => $this->faker->numberBetween(214748648, PHP_INT_MAX),
        'std_object' => (object) [
            'name' => $this->faker->name(),
            'uuid' => $this->faker->uuid(),
        ],
    ];
    $this->createTables();

    $this->snapshotRepository = new DynamoDbSnapshotRepository(
        $this->getDynamoDbClient(),
        new IdGenerator(new Randomizer()),
        new Marshaler()
    );
});

afterEach(function () {
    $this->deleteTables();
});

it('can store and retrieve a snapshot', function () {
    $uuid = $this->faker->uuid();
    $snapshot = new Snapshot(
        $uuid,
        1,
        $this->largeStateData
    );

    $this->snapshotRepository->persist($snapshot);

    $snapshot = $this->snapshotRepository->retrieve($uuid);
    //TODO: assertions.
});
