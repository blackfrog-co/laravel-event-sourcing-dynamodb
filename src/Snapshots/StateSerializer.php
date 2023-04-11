<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\Snapshots;

class StateSerializer
{
    public function serializeAndSplitState(mixed $state): array
    {
        return $this->splitSerializedState($this->serializeState($state));
    }

    public function combineAndDeserializeState(array $stateParts): mixed
    {
        return $this->deserializeState($this->combineSerializedState($stateParts));
    }

    private function splitSerializedState(string $state): array
    {
        return str_split($state, 380_000);
    }

    private function combineSerializedState(array $stateData): string
    {
        return implode('', $stateData);
    }

    private function serializeState(mixed $state): string
    {
        return base64_encode(serialize($state));
    }

    public function deserializeState(string $state): mixed
    {
        return unserialize(base64_decode($state));
    }
}
