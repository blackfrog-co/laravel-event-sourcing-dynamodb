<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\IdGeneration;

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

    public function __construct(
        private readonly Randomizer $randomizer,
        private readonly TimestampProvider $timestampProvider,
    ) {
    }

    public function generateId(): int
    {
        $timestamp = $this->timestamp();

        //We want enough random digits to create an integer id that fits inside INT_MAX on 64bit PHP.
        $randomIntDigits = static::TARGET_ID_LENGTH - strlen((string) $timestamp);
        $randomInt = $this->randomIntAsZeroFilledString($randomIntDigits);

        $id = (int) ($timestamp.$randomInt);

        $this->exceptIfIntIsNegative($id);
        $this->exceptIfIntMaxIsReached($id);

        return $id;
    }

    private function timestamp(): int
    {
        return $this->timestampProvider->microsecondsTimestamp();
    }

    private function randomIntAsZeroFilledString(int $digits): string
    {
        $max = pow(10, $digits) - 1;

        $int = $this->randomizer->getInt(0, $max);

        return sprintf("%0{$digits}d", $int);
    }

    private function exceptIfIntMaxIsReached(int $id): void
    {
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
