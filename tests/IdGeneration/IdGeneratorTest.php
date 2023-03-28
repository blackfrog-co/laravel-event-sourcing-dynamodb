<?php

use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\IdGenerator;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\MicroTimeTimestampProvider;
use BlackFrog\LaravelEventSourcingDynamodb\IdGeneration\TimestampProvider;
use Random\Randomizer;

beforeAll(function () {
    class FixedTimestampProvider implements TimestampProvider
    {
        public function __construct(public int $microsecondsTimestamp)
        {
        }

        public function microsecondsTimestamp(): int
        {
            return $this->microsecondsTimestamp;
        }
    }

    class ClockSkewTimestampProvider implements TimestampProvider
    {
        public int $counter = 0;

        public function __construct(protected array $microsecondsTimestamps)
        {
        }

        public function microsecondsTimestamp(): int
        {
            $timestamp = $this->microsecondsTimestamps[$this->counter];
            $this->counter++;

            return $timestamp;
        }
    }
});

beforeEach(function () {
    $this->idGenerator = new IdGenerator(new Randomizer(), new MicroTimeTimestampProvider());
});

afterEach(function () {
});

it('generates one id successfully', function () {
    $id = $this->idGenerator->generateId();
    expect($id)
        ->toBeInt()
        ->toBeGreaterThan(1000000000000000000)
        ->toBeLessThan(PHP_INT_MAX);
});

it('generates a positive fixed length 19 digit integer id', function () {
    $iterations = 100_000;
    $x = 1;

    while ($x <= $iterations) {
        $id = $this->idGenerator->generateId();
        expect($id)
            ->toBeInt()
            ->toBeGreaterThan(1000000000000000000)
            ->toBeLessThan(PHP_INT_MAX);
        $x++;
    }
});

it('generates ids greater than previous ones if one or more microseconds pass between calls', function () {
    $iterations = 100;
    $x = 1;
    $previousId = 0;

    while ($x <= $iterations) {
        //In reality this test passes without the usleep due to execution time but here
        //it illustrates the theoretical requirement for a microsecond to have passed.
        usleep(1);
        $id = $this->idGenerator->generateId();
        expect($id)->toBeGreaterThan($previousId);
        $previousId = $id;
        $x++;
    }
});

it('can generate 1 million ids in under 60 seconds on most systems', function () {
    $iterations = 1_000_000;
    $startExecutionTime = hrtime(true);
    $x = 1;

    while ($x <= $iterations) {
        $this->idGenerator->generateId();
        $x++;
    }

    $totalExecutionTimeSeconds = (hrtime(true) - $startExecutionTime) / 1e+9;

    expect($totalExecutionTimeSeconds)->toBeLessThan(60);
});

it('produces no duplicate ids over 50,000 iterations', function () {
    $iterations = 50_000;
    $generatedIds = [];
    $x = 1;

    $duplicateFound = false;

    while ($x <= $iterations) {
        $id = $this->idGenerator->generateId();
        $duplicateFound = in_array($id, $generatedIds);
        $generatedIds[] = $id;
        $x++;
    }

    expect($duplicateFound)->toBeFalse();
});

it('uses the provided integer timestamp as the start of each id', function () {
    $microTimeStamp = 1679836125000000;
    $this->idGenerator = new IdGenerator(new Randomizer(), new FixedTimestampProvider($microTimeStamp));

    $id = $this->idGenerator->generateId();
    $idAsString = (string) $id;
    expect($idAsString)->toContain((string) $microTimeStamp);

    $firstSixteenDigitsOfId = substr($idAsString, 0, 16);
    expect($firstSixteenDigitsOfId)->toEqual((string) $microTimeStamp);
});

it('never returns the same id twice from the same instance, even in the same microsecond', function () {
    //Fix the current time.
    $microTimeStamp = 1679836125000000;
    $this->idGenerator = new IdGenerator(new Randomizer(), new FixedTimestampProvider($microTimeStamp));
    //At least until the internal limit of unique values per microsecond is exceeded.
    $maxUniqueIntegers = 799;

    $generatedIds = [];
    $x = 1;

    while ($x <= $maxUniqueIntegers) {
        $id = $this->idGenerator->generateId();
        expect($generatedIds)->not()->toContain($id);
        $generatedIds[] = $id;
        $x++;
    }
});

it('may generate duplicate ids if two instances are used at the same microsecond', function () {
    //Fix the current microsecond time.
    $microTimeStamp = 1679836125000000;
    $timestampProvider = new FixedTimestampProvider($microTimeStamp);

    //450 iterations in one microsecond guarantees at least some collisions between two instances.
    $iterations = 450;
    $idGenerator1 = new IdGenerator(new Randomizer(), $timestampProvider);
    $idsGenerated1 = [];
    $idGenerator2 = new IdGenerator(new Randomizer(), $timestampProvider);
    $idsGenerated2 = [];

    $x = 1;
    while ($x <= $iterations) {
        $idsGenerated1[] = $idGenerator1->generateId();
        $idsGenerated2[] = $idGenerator2->generateId();
        $x++;
    }

    $countOfDuplicateIds = count(array_intersect($idsGenerated1, $idsGenerated2));
    expect($countOfDuplicateIds)->toBeInt()->toBeGreaterThan(0);
});

it('throws RuntimeException if the maximum unique values per microsecond is exceeded', function () {
    //Fix the current microsecond time.
    $microTimeStamp = 1679836125000000;
    $timestampProvider = new FixedTimestampProvider($microTimeStamp);
    $this->idGenerator = new IdGenerator(new Randomizer(), $timestampProvider);

    //Run 801 iterations (the internal limit is 800 values per microsecond)
    //In year 2286 this limit will drop to around 80 per microsecond.
    $iterations = 802;

    $x = 1;
    while ($x <= $iterations) {
        $this->idGenerator->generateId();
        $x++;
    }
})->throws(RuntimeException::class);

it('generates ids even when the system date is a unix date early in the epoch', function () {
    //Fix the current time to 1 microsecond since the start of unix epoch.
    $microTimeStamp = 1;
    $timestampProvider = new FixedTimestampProvider($microTimeStamp);
    $this->idGenerator = new IdGenerator(new Randomizer(), $timestampProvider);

    $id = $this->idGenerator->generateId();

    expect($id)
        ->toBeInt()
        ->toBeGreaterThan(1000000000000000000)
        ->toBeLessThan(PHP_INT_MAX);
});

it('generates ids with reduced entropy when system date is 2286-11-20 onwards', function () {
    //From 2286-11-20 17:46:40 forward we roll over to get an extra digit in our micro time integer (10000000000000000)
    //The id generator soldiers on with reduced entropy by generating one less random digit to append.
    $microTimeStamp = 10000000000000000;
    $timestampProvider = new FixedTimestampProvider($microTimeStamp);
    $this->idGenerator = new IdGenerator(new Randomizer(), $timestampProvider);

    //The max iterations per microsecond falls to 79 due to fewer available random numbers.
    $iterations = 79;
    $x = 1;

    while ($x <= $iterations) {
        $id = $this->idGenerator->generateId();
        expect($id)
            ->toBeInt()
            ->toBeGreaterThan(999999999999999999)
            ->toBeLessThan(PHP_INT_MAX);

        $idAsString = (string) $id;
        expect($idAsString)->toContain((string) $microTimeStamp);

        //The first 17 digits of the id are now the timestamp instead of the first 16
        $firstSeventeenDigitsOfId = substr($idAsString, 0, 17);
        expect($firstSeventeenDigitsOfId)->toEqual((string) $microTimeStamp);
        $x++;
    }
});

it('throws an exception if the clock skews backwards by more than 2 seconds', function () {
    $timestampProvider = new ClockSkewTimestampProvider([1679836125000000, 1579836125000000]);
    $idGenerator = new IdGenerator(new Randomizer(), $timestampProvider);
    $idGenerator->generateId();
    $idGenerator->generateId();
})->throws(RuntimeException::class);

it('it waits for time to catch up if the clock skews backwards by less than 2 seconds', function () {
    $timestampProvider = new ClockSkewTimestampProvider([1679836125000000, 1679836124000000, 1679836125000000]);
    $idGenerator = new IdGenerator(new Randomizer(), $timestampProvider);
    $idGenerator->generateId();
    $idGenerator->generateId();
});
