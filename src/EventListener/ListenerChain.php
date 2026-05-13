<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\EventListener;

use Override;
use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;

/**
 * Dispatches events to multiple listeners in order. Use this when you need
 * more than one listener on the same list without changing the list's constructor.
 *
 * @template T of int|string
 * @implements ListEventListenerInterface<T>
 *
 * @api
 */
final class ListenerChain implements ListEventListenerInterface
{

    /**
     * @param list<ListEventListenerInterface<T>> $listeners
     */
    public function __construct(private readonly array $listeners)
    {
    }

    /**
     * @param ItemInsertedEvent<T> $event
     */
    #[Override]
    public function onInserted(ItemInsertedEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->onInserted($event);
        }
    }

    /**
     * @param ItemRemovedEvent<T> $event
     */
    #[Override]
    public function onRemoved(ItemRemovedEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->onRemoved($event);
        }
    }

    #[Override]
    public function onCleared(ListClearedEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->onCleared($event);
        }
    }

}
