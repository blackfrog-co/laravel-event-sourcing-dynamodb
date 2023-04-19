<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents;

use Aws\ResultPaginator;
use Closure;
use Iterator;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

class DynamoEventIterator implements Iterator
{
    private ?int $itemsOnPage;

    private int $itemNumber = 1;

    private int $itemPageIndex = 0;

    public function __construct(
        private readonly ResultPaginator $paginator,
        private readonly Closure $itemProcessor
    ) {
        $firstResult = $this->paginator->current();
        $this->itemsOnPage = count($firstResult->get('Items'));
    }

    public function current(): StoredEvent|false
    {
        if ($this->valid() === false) {
            return false;
        }

        $result = $this->paginator->current();

        $awsItem = $result->get('Items')[$this->itemPageIndex];

        $func = $this->itemProcessor;

        return $func($awsItem);
    }

    public function valid(): bool
    {
        if ($this->itemsOnPage === null || $this->itemsOnPage === 0) {
            return false;
        }

        $result = $this->paginator->current();

        return isset($result->get('Items')[$this->itemPageIndex]);
    }

    public function next(): void
    {
        if (($this->itemPageIndex + 1) === $this->itemsOnPage) {
            $this->nextPage();

            return;
        }

        $this->itemNumber++;
        $this->itemPageIndex++;
    }

    private function nextPage(): void
    {
        $this->paginator->next();

        $currentPage = $this->paginator->current();

        if ($currentPage === false) {
            $this->itemsOnPage = null;

            return;
        }

        $result = $this->paginator->current();
        $this->itemsOnPage = count($result->get('Items'));

        $this->itemPageIndex = 0;
        $this->itemNumber++;
    }

    public function key(): ?int
    {
        return $this->valid() ? $this->itemNumber - 1 : null;
    }

    public function rewind(): void
    {
        $this->paginator->rewind();
        $this->itemNumber = 1;
        $this->itemPageIndex = 0;

        $result = $this->paginator->current();
        $this->itemsOnPage = count($result->get('Items'));
    }
}
