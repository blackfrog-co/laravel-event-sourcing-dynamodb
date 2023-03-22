<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\Commands;

use Illuminate\Console\Command;

class LaravelEventSourcingDynamodbCommand extends Command
{
    public $signature = 'laravel-event-sourcing-dynamodb';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
