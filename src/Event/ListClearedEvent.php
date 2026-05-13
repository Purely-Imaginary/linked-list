<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Event;

/**
 * @api
 */
final readonly class ListClearedEvent implements ListEventInterface
{

    public function __construct(
        public int $previousSize,
    )
    {
    }

}
