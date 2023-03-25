<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb;

use Random\Randomizer;
use RuntimeException;

class IdGenerator
{
    /**
     * We ensure that we don't generate a number with more digits than can
     * fit in an integer (PHP_INT_MAX) on 64bit PHP.
     *
     * @see PHP_INT_MAX
     */
    const TARGET_ID_LENGTH = 19;

    /**
     * We track the last timestamp used to generate an id, so we can
     * avoid an instance of this class returning the same $id value twice.
     */
    private ?int $timestamp = null;

    /**
     * We track the random integers used for the current microsecond timestamp, so we can
     * avoid one instance of this class returning the same $id value twice.
     */
    private array $randomIntsUsed = [];

    /**
     * Total possible unique values in a given microsecond but as we approach this limit
     * the likelihood of generating a unique one decreases and therefore
     * the cost grows exponentially. We set this number lower to avoid this problem.
     */
    private int $maxUniqueInts;

    /**
     * @var int
     * We use the initTimestamp to offer us some basic protection
     * against the system clock changing backwards during the
     * life of this instance. It is set on first id generation.
     */
    private int $initTimestamp;

    public function __construct(
        private readonly Randomizer $randomizer,
        private readonly int $clockSkewWaitMicroseconds = 2_000_000 //2 seconds
    ) {
    }

    public function generateId(): int
    {
        $timestamp = $this->microsecondsTimestamp();
        $this->initTimestamp ??= $timestamp;

        //If the clock has been changed backwards since the last id was generated, wait for it to catch up
        if ($timestamp < $this->timestamp) {
            $this->handleClockSkew($timestamp);
        }

        //We want enough random digits to create an integer id that fits inside INT_MAX on 64bit PHP.
        $randomIntDigits = static::TARGET_ID_LENGTH - strlen((string) $timestamp);
        $randomInt = $this->randomIntAsZeroFilledString($randomIntDigits);

        //If we've generated an id for this timestamp already, we need to check for collisions.
        if ($timestamp === $this->timestamp) {
            $this->exceptIfNoUniqueValuesRemain($randomIntDigits);
            //If we've generated this int before for the current timestamp, we try again until we get a unique value.
            while (in_array($randomInt, $this->randomIntsUsed)) {
                //If we've exhausted the number of values we want to generate per timestamp, throw an exception.
                $this->exceptIfNoUniqueValuesRemain($randomIntDigits);
                $randomInt = $this->randomIntAsZeroFilledString($randomIntDigits);
            }
        } else {
            //The timestamp has changed since the last call, so we can reset state.
            $this->randomIntsUsed = [];
            unset($this->maxUniqueInts);
        }

        $this->timestamp = $timestamp;
        $this->randomIntsUsed[] = $randomInt;

        $id = (int) ($timestamp.$randomInt);

        $this->exceptIfIntIsNegative($id);
        $this->exceptIfIntMaxIsReached($id);

        return $id;
    }

    private function handleClockSkew(int $timestamp): void
    {
        $skewMicroseconds = $this->timestamp - $timestamp;

        if ($skewMicroseconds > $this->clockSkewWaitMicroseconds) {
            throw new \RuntimeException(
                "Backwards system clock change greater than {$this->clockSkewWaitMicroseconds} microseconds detected."
            );
        }

        usleep($skewMicroseconds);
    }

    private function microsecondsTimestamp(): int
    {
        $microTime = explode(' ', microtime());

        return
            intval((int) $microTime[1] * 1E6)
            +
            intval(round((float) $microTime[0] * 1E6));
    }

    private function randomIntAsZeroFilledString(int $digits): string
    {
        $max = pow(10, $digits) - 1;

        $int = $this->randomizer->getInt(0, $max);

        return sprintf("%0{$digits}d", $int);
    }

    private function exceptIfNoUniqueValuesRemain(int $randomIntDigits): void
    {
        if (isset($this->maxUniqueInts) === false) {
            $maxValue = pow(10, $randomIntDigits) - 1;
            //We cap this to 80% of the total available space as performance degrades rapidly as we approach
            //the true mathematical limit on possible unique values as we have to regenerate too many times.
            $this->maxUniqueInts = (int) ((80 / 100) * $maxValue);
        }
        if (count($this->randomIntsUsed) > $this->maxUniqueInts) {
            throw new RuntimeException('Unable to generate a unique value.');
        }
    }

    private function exceptIfIntMaxIsReached(int $id): void
    {
        //If PHP_INT_MAX is reached we have possible integer overflow and cannot trust the id generated.
        if ($id === PHP_INT_MAX) {
            throw new RuntimeException('PHP_INT_MAX limit reached.');
        }
    }

    private function exceptIfIntIsNegative(int $id): void
    {
        if ($id < 0) {
            throw new RuntimeException('Negative Id generated.');
        }
    }
}
