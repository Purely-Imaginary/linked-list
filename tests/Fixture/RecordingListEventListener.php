<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Tests\Fixture;

use Override;
use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;

/**
 * @implements ListEventListenerInterface<int|string>
 */
final class RecordingListEventListener implements ListEventListenerInterface
{

    /**
     * @var list<ItemInsertedEvent<int|string>>
     */
    public array $insertions = [];

    /**
     * @var list<ItemRemovedEvent<int|string>>
     */
    public array $removals = [];

    /**
     * @var list<ListClearedEvent>
     */
    public array $clears = [];

    /**
     * @param ItemInsertedEvent<int|string> $event
     */
    #[Override]
    public function onInserted(ItemInsertedEvent $event): void
    {
        $this->insertions[] = $event;
    }

    /**
     * @param ItemRemovedEvent<int|string> $event
     */
    #[Override]
    public function onRemoved(ItemRemovedEvent $event): void
    {
        $this->removals[] = $event;
    }

    #[Override]
    public function onCleared(ListClearedEvent $event): void
    {
        $this->clears[] = $event;
    }

}
