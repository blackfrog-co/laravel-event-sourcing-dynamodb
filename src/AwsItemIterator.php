<?php

namespace BlackFrog\LaravelEventSourcingDynamodb;

use Aws\DynamoDb\Marshaler;
use Aws\ResultPaginator;
use BlackFrog\LaravelEventSourcingDynamodb\StoredEvents\StoredEventFactory;
use Iterator;

class AwsItemIterator implements Iterator
{
    private ?int $itemsOnPage;

    private int $itemNumber = 1;

    private int $itemPageIndex = 0;

    private Marshaler $marshaler;

    private StoredEventFactory $storedEventFactory;

    public function __construct(private ResultPaginator $paginator)
    {
        $this->marshaler = app(Marshaler::class);
        $this->storedEventFactory = app(StoredEventFactory::class);
        $firstResult = $this->paginator->current();
        $this->itemsOnPage = count($firstResult->get('Items'));
    }

    public function current(): mixed
    {
        $result = $this->paginator->current();

        $awsItem = $result->get('Items')[$this->itemPageIndex];

        $dynamoItem = $this->marshaler->unmarshalItem($awsItem);

        $storedEvent = $this->storedEventFactory->storedEventFromDynamoItem($dynamoItem);

        return $storedEvent;
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

    public function key(): mixed
    {
        return $this->valid() ? $this->itemNumber - 1 : null;
    }

    public function valid(): bool
    {
        if ($this->itemsOnPage === null) {
            return false;
        }

        if ($this->itemsOnPage === 0) {
            return false;
        }

        $result = $this->paginator->current();

        return isset($result->get('Items')[$this->itemPageIndex]);
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
