<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;
use ShipMonk\SortedLinkedList\Event\ListEventInterface;

final class EventsTest extends TestCase
{

    public function testItemInsertedEventImplementsInterface(): void
    {
        $event = new ItemInsertedEvent(42, 1);
        self::assertInstanceOf(ListEventInterface::class, $event);
        self::assertSame(42, $event->value);
        self::assertSame(1, $event->newSize);
    }

    public function testItemInsertedEventWithStringValue(): void
    {
        $event = new ItemInsertedEvent('hello', 3);
        self::assertSame('hello', $event->value);
        self::assertSame(3, $event->newSize);
    }

    public function testItemRemovedEventImplementsInterface(): void
    {
        $event = new ItemRemovedEvent(7, 0);
        self::assertInstanceOf(ListEventInterface::class, $event);
        self::assertSame(7, $event->value);
        self::assertSame(0, $event->newSize);
    }

    public function testListClearedEventImplementsInterface(): void
    {
        $event = new ListClearedEvent(5);
        self::assertInstanceOf(ListEventInterface::class, $event);
        self::assertSame(5, $event->previousSize);
    }

}
