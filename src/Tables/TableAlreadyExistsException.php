<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Tables;

use RuntimeException;

class TableAlreadyExistsException extends RuntimeException
{
}
