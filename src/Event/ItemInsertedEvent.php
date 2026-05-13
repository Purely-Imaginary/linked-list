<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Event;

/**
 * @template T of int|string
 */
final readonly class ItemInsertedEvent implements ListEventInterface
{

    /**
     * @param T $value
     */
    public function __construct(
        public int|string $value,
        public int $newSize,
    )
    {
    }

}
