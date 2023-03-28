<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\IdGeneration;

interface TimestampProvider
{
    public function microsecondsTimestamp(): int;
}
