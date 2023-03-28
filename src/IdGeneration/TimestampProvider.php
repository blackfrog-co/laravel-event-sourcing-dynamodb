<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\IdGeneration;

interface TimestampProvider
{
    public function microsecondsTimestamp(): int;
}
