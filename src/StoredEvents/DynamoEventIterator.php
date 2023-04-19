<?php

declare(strict_types=1);

namespace BlackFrog\LaravelEventSourcingDynamodb\StoredEvents;

use Aws\Result;
use Aws\ResultPaginator;
use Closure;
use Iterator;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

class DynamoEventIterator implements Iterator
{
    private ?int $pageItemCount = null;

    private int $itemNumber = 1;

    private int $itemPageIndex = 0;

    public function __construct(
        private readonly ResultPaginator $paginator,
        private readonly Closure $itemProcessor
    ) {
    }

    private function getPageItemCount(): int
    {
        if ($this->pageItemCount === null) {
            $this->pageItemCount = $this->getResult()->get('Count');
        }

        return $this->pageItemCount;
    }

    private function getResult(): Result
    {
        return $this->paginator->current();
    }

    public function current(): StoredEvent|false
    {
        if ($this->valid() === false) {
            return false;
        }

        $result = $this->paginator->current();

        $awsItem = $result->get('Items')[$this->itemPageIndex];

        $itemProcessor = $this->itemProcessor;

        return $itemProcessor($awsItem);
    }

    public function valid(): bool
    {
        if ($this->getPageItemCount() === 0) {
            return false;
        }

        $result = $this->paginator->current();

        return isset($result->get('Items')[$this->itemPageIndex]);
    }

    public function next(): void
    {
        if (($this->itemPageIndex + 1) === $this->getPageItemCount()) {
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
            $this->pageItemCount = 0;

            return;
        }

        $this->pageItemCount = null;
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
        $this->pageItemCount = null;
    }
}
