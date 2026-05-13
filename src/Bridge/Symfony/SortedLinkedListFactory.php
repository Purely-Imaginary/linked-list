<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Bridge\Symfony;

use Psr\Log\LoggerInterface;
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;
use ShipMonk\SortedLinkedList\SortedLinkedList;

/**
 * @api
 */
final class SortedLinkedListFactory
{

    /**
     * @param ListEventListenerInterface<int|string>|null $eventListener
     */
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ListEventListenerInterface $eventListener = null,
    )
    {
    }

    /**
     * @param callable(int, int): int $comparator
     * @return SortedLinkedList<int>
     */
    public function createIntList(callable $comparator): SortedLinkedList
    {
        // @phpstan-ignore argument.type, return.type
        return new SortedLinkedList($comparator, $this->eventListener, $this->logger);
    }

    /**
     * @param callable(string, string): int $comparator
     * @return SortedLinkedList<string>
     */
    public function createStringList(callable $comparator): SortedLinkedList
    {
        // @phpstan-ignore argument.type, return.type
        return new SortedLinkedList($comparator, $this->eventListener, $this->logger);
    }

}
