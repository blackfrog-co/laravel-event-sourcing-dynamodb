<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\IdGeneration;

class MicroTimeTimestampProvider implements TimestampProvider
{
    public function microsecondsTimestamp(): int
    {
        $microTime = explode(' ', microtime());

        return
            intval((int) $microTime[1] * 1E6)
            +
            intval(round((float) $microTime[0] * 1E6));
    }
}
