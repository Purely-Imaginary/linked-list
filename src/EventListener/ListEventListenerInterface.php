<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\EventListener;

use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;

/**
 * @template T of int|string
 *
 * @api
 */
interface ListEventListenerInterface
{

    /**
     * @param ItemInsertedEvent<T> $event
     */
    public function onInserted(ItemInsertedEvent $event): void;

    /**
     * @param ItemRemovedEvent<T> $event
     */
    public function onRemoved(ItemRemovedEvent $event): void;

    public function onCleared(ListClearedEvent $event): void;

}
