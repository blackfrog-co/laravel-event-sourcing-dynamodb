<?php

namespace BlackFrog\LaravelEventSourcingDynamodb\IdGeneration;

interface IdGenerator
{
    public function generateId(): int;
}
