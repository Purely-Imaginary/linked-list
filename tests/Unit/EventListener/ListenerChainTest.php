<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\EventListener\ListenerChain;
use ShipMonk\SortedLinkedList\SortedLinkedList;
use ShipMonk\SortedLinkedList\Tests\Fixture\RecordingListEventListener;

final class ListenerChainTest extends TestCase
{

    public function testChainDispatchesToAllListeners(): void
    {
        $a = new RecordingListEventListener();
        $b = new RecordingListEventListener();

        /** @var ListenerChain<int> $chain */
        $chain = new ListenerChain([$a, $b]);
        $list = new SortedLinkedList(static fn (int $x, int $y): int => $x <=> $y, $chain);
        $list->add(1);

        self::assertCount(1, $a->insertions);
        self::assertCount(1, $b->insertions);
        self::assertSame(1, $a->insertions[0]->value);
        self::assertSame(1, $b->insertions[0]->value);
    }

    public function testChainDispatchesRemoveToAllListeners(): void
    {
        $a = new RecordingListEventListener();
        $b = new RecordingListEventListener();

        /** @var ListenerChain<int> $chain */
        $chain = new ListenerChain([$a, $b]);
        $list = new SortedLinkedList(static fn (int $x, int $y): int => $x <=> $y, $chain);
        $list->add(5);
        $list->remove(5);

        self::assertCount(1, $a->removals);
        self::assertCount(1, $b->removals);
    }

    public function testChainDispatchesClearToAllListeners(): void
    {
        $a = new RecordingListEventListener();
        $b = new RecordingListEventListener();

        /** @var ListenerChain<int> $chain */
        $chain = new ListenerChain([$a, $b]);
        $list = new SortedLinkedList(static fn (int $x, int $y): int => $x <=> $y, $chain);
        $list->add(1);
        $list->clear();

        self::assertCount(1, $a->clears);
        self::assertCount(1, $b->clears);
        self::assertSame(1, $a->clears[0]->previousSize);
    }

    public function testEmptyChainIsNoOp(): void
    {
        /** @var ListenerChain<int> $chain */
        $chain = new ListenerChain([]);
        $list = new SortedLinkedList(static fn (int $x, int $y): int => $x <=> $y, $chain);
        $list->add(1);
        $list->remove(1);
        $list->clear();
        self::assertTrue($list->isEmpty());
    }

}
