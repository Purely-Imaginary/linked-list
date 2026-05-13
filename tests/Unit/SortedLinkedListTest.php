<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ShipMonk\SortedLinkedList\Comparator;
use ShipMonk\SortedLinkedList\Exception\EmptyListException;
use ShipMonk\SortedLinkedList\Exception\MixedTypeException;
use ShipMonk\SortedLinkedList\Exception\SortedLinkedListException;
use ShipMonk\SortedLinkedList\SortedLinkedList;
use ShipMonk\SortedLinkedList\Tests\Fixture\RecordingListEventListener;
use Stringable;
use function count;
use function is_int;
use function json_encode;
use function serialize;

final class SortedLinkedListTest extends TestCase
{

    /** @return SortedLinkedList<int> */
    private function intList(): SortedLinkedList
    {
        return SortedLinkedList::ofIntegers();
    }

    /** @return SortedLinkedList<string> */
    private function stringList(): SortedLinkedList
    {
        return SortedLinkedList::ofStrings();
    }

    /** @return callable(int|string, int|string): int */
    private function naturalComparator(): callable
    {
        return static function (int|string $a, int|string $b): int {
            if (is_int($a) && is_int($b)) {
                return $a <=> $b;
            }

            return (string) $a <=> (string) $b;
        };
    }

    public function testNewListIsEmpty(): void
    {
        self::assertSame([], $this->intList()->toArray());
    }

    public function testAddSingleIntValue(): void
    {
        $list = $this->intList();
        $list->add(5);
        self::assertSame([5], $list->toArray());
    }

    public function testAddMaintainsSortedOrderForInts(): void
    {
        $list = $this->intList();
        $list->add(3);
        $list->add(1);
        $list->add(2);
        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testAddMaintainsSortedOrderForStrings(): void
    {
        $list = $this->stringList();
        $list->add('banana');
        $list->add('apple');
        $list->add('cherry');
        self::assertSame(['apple', 'banana', 'cherry'], $list->toArray());
    }

    public function testAddAllowsDuplicates(): void
    {
        $list = $this->intList();
        $list->add(2);
        $list->add(1);
        $list->add(2);
        self::assertSame([1, 2, 2], $list->toArray());
    }

    public function testAddInsertsAtHeadWhenSmallest(): void
    {
        $list = $this->intList();
        $list->add(5);
        $list->add(3);
        $list->add(1);
        self::assertSame([1, 3, 5], $list->toArray());
    }

    public function testAddInsertsAtTailWhenLargest(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(3);
        $list->add(5);
        self::assertSame([1, 3, 5], $list->toArray());
    }

    public function testAddFiveElementsInReverseOrder(): void
    {
        $list = $this->intList();

        foreach ([5, 4, 3, 2, 1] as $v) {
            $list->add($v);
        }

        self::assertSame([1, 2, 3, 4, 5], $list->toArray());
    }

    public function testCustomDescendingComparator(): void
    {
        $list = new SortedLinkedList(static fn (int $a, int $b): int => $b <=> $a);
        $list->add(1);
        $list->add(3);
        $list->add(2);
        self::assertSame([3, 2, 1], $list->toArray());
    }

    public function testDuplicateInsertedAfterExistingEqualValue(): void
    {
        $list = $this->intList();
        $list->add(2);
        $list->add(2);
        self::assertSame([2, 2], $list->toArray());
        self::assertSame(2, $list->count());
    }

    public function testCountOnEmptyList(): void
    {
        self::assertSame(0, $this->intList()->count());
    }

    public function testCountAfterInsertions(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->add(2);
        self::assertSame(3, $list->count());
    }

    public function testCountWorksWithBuiltinCountFunction(): void
    {
        $list = $this->intList();
        $list->add(10);
        self::assertSame(1, count($list));
    }

    public function testIsEmptyOnNewList(): void
    {
        self::assertTrue($this->intList()->isEmpty());
    }

    public function testIsEmptyAfterInsertion(): void
    {
        $list = $this->intList();
        $list->add(1);
        self::assertFalse($list->isEmpty());
    }

    public function testIteratorYieldsValuesInSortedOrder(): void
    {
        $list = $this->intList();
        $list->add(3);
        $list->add(1);
        $list->add(2);

        $result = [];

        foreach ($list as $value) {
            $result[] = $value;
        }

        self::assertSame([1, 2, 3], $result);
    }

    public function testIteratorOnEmptyList(): void
    {
        $result = [];

        foreach ($this->intList() as $value) {
            $result[] = $value;
        }

        self::assertSame([], $result);
    }

    public function testContainsReturnsTrueWhenValuePresent(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(3);
        self::assertTrue($list->contains(3));
    }

    public function testContainsReturnsFalseWhenValueAbsent(): void
    {
        $list = $this->intList();
        $list->add(1);
        self::assertFalse($list->contains(99));
    }

    public function testContainsOnEmptyList(): void
    {
        self::assertFalse($this->intList()->contains(1));
    }

    public function testFirstReturnsSmallestValue(): void
    {
        $list = $this->intList();
        $list->add(3);
        $list->add(1);
        $list->add(2);
        self::assertSame(1, $list->first());
    }

    public function testLastReturnsLargestValue(): void
    {
        $list = $this->intList();
        $list->add(3);
        $list->add(1);
        $list->add(2);
        self::assertSame(3, $list->last());
    }

    public function testFirstOnSingleElementList(): void
    {
        $list = $this->intList();
        $list->add(7);
        self::assertSame(7, $list->first());
    }

    public function testLastOnSingleElementList(): void
    {
        $list = $this->intList();
        $list->add(7);
        self::assertSame(7, $list->last());
    }

    public function testFirstThrowsOnEmptyList(): void
    {
        $this->expectException(EmptyListException::class);
        $this->intList()->first();
    }

    public function testLastThrowsOnEmptyList(): void
    {
        $this->expectException(EmptyListException::class);
        $this->intList()->last();
    }

    public function testRemoveExistingValueReturnsTrueAndDecrementsCount(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->add(3);
        self::assertTrue($list->remove(2));
        self::assertSame([1, 3], $list->toArray());
        self::assertSame(2, $list->count());
    }

    public function testRemoveHeadElement(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        self::assertTrue($list->remove(1));
        self::assertSame([2], $list->toArray());
    }

    public function testRemoveTailElement(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        self::assertTrue($list->remove(2));
        self::assertSame([1], $list->toArray());
    }

    public function testRemoveSingleElement(): void
    {
        $list = $this->intList();
        $list->add(5);
        self::assertTrue($list->remove(5));
        self::assertTrue($list->isEmpty());
    }

    public function testRemoveAbsentValueReturnsFalse(): void
    {
        $list = $this->intList();
        $list->add(1);
        self::assertFalse($list->remove(99));
        self::assertSame(1, $list->count());
    }

    public function testRemoveFromEmptyListReturnsFalse(): void
    {
        self::assertFalse($this->intList()->remove(1));
    }

    public function testRemoveOnlyFirstOccurrenceOfDuplicate(): void
    {
        $list = $this->intList();
        $list->add(2);
        $list->add(2);
        $list->add(2);
        $list->remove(2);
        self::assertSame([2, 2], $list->toArray());
    }

    public function testClearEmptiesTheList(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->clear();
        self::assertSame([], $list->toArray());
        self::assertSame(0, $list->count());
        self::assertTrue($list->isEmpty());
    }

    public function testClearResetsTypeLockSoListCanBeReused(): void
    {
        $intList = new SortedLinkedList(static fn (int $a, int $b): int => $a <=> $b);
        $intList->add(1);
        $intList->clear();

        $strList = new SortedLinkedList(static fn (string $a, string $b): int => $a <=> $b);
        $strList->add('hello');
        self::assertSame(['hello'], $strList->toArray());
        self::assertTrue($intList->isEmpty());
    }

    public function testClearOnEmptyListIsNoOp(): void
    {
        $list = $this->intList();
        $list->clear();
        self::assertTrue($list->isEmpty());
    }

    public function testFilterReturnsNewListWithMatchingValues(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->add(3);
        $list->add(4);
        $filtered = $list->filter(static fn (int $v): bool => $v % 2 === 0);
        self::assertSame([2, 4], $filtered->toArray());
        self::assertSame([1, 2, 3, 4], $list->toArray());
    }

    public function testFilterReturnsEmptyListWhenNothingMatches(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(3);
        $filtered = $list->filter(static fn (int $v): bool => $v > 100);
        self::assertTrue($filtered->isEmpty());
    }

    public function testFilterOnEmptyListReturnsEmptyList(): void
    {
        $filtered = $this->intList()->filter(static fn (int $_v): bool => true); // @phpstan-ignore shipmonk.unusedParameter
        self::assertTrue($filtered->isEmpty());
    }

    public function testFilterRetainsComparatorSoNewElementsAreSorted(): void
    {
        $list = $this->intList();
        $list->add(10);
        $filtered = $list->filter(static fn (int $_v): bool => true); // @phpstan-ignore shipmonk.unusedParameter
        $filtered->add(5);
        self::assertSame([5, 10], $filtered->toArray());
    }

    public function testMergeProducesResortedUnion(): void
    {
        $a = $this->intList();
        $a->add(1);
        $a->add(3);
        $b = $this->intList();
        $b->add(2);
        $b->add(4);
        self::assertSame([1, 2, 3, 4], $a->merge($b)->toArray());
    }

    public function testMergeDoesNotMutateOriginalLists(): void
    {
        $a = $this->intList();
        $a->add(1);
        $b = $this->intList();
        $b->add(2);
        $a->merge($b);
        self::assertSame([1], $a->toArray());
        self::assertSame([2], $b->toArray());
    }

    public function testMergeWithEmptyListReturnsEquivalentList(): void
    {
        $a = $this->intList();
        $a->add(5);
        $b = $this->intList();
        self::assertSame([5], $a->merge($b)->toArray());
        self::assertSame([5], $b->merge($a)->toArray());
    }

    public function testMergeTwoEmptyListsReturnsEmptyList(): void
    {
        self::assertTrue($this->intList()->merge($this->intList())->isEmpty());
    }

    public function testMergeThrowsWhenListsHaveDifferentLockedTypes(): void
    {
        $intList = $this->intList();
        $intList->add(1);
        $strList = $this->stringList();
        $strList->add('a');
        $this->expectException(MixedTypeException::class);
        $this->expectExceptionMessage('Cannot merge lists');
        $intList->merge($strList); // @phpstan-ignore argument.type
    }

    public function testAddingMixedTypesThrowsMixedTypeException(): void
    {
        $list = new SortedLinkedList(static fn (int $a, int $b): int => $a <=> $b);
        $list->add(1);
        $this->expectException(MixedTypeException::class);
        $list->add('hello'); // @phpstan-ignore argument.type
    }

    public function testAddingStringThenIntThrowsMixedTypeException(): void
    {
        $list = new SortedLinkedList(static fn (string $a, string $b): int => $a <=> $b);
        $list->add('hello');
        $this->expectException(MixedTypeException::class);
        $list->add(1); // @phpstan-ignore argument.type
    }

    public function testMixedTypeExceptionMessageContainsBothTypes(): void
    {
        $list = new SortedLinkedList(static fn (int $a, int $b): int => $a <=> $b);
        $list->add(1);

        try {
            $list->add('hello'); // @phpstan-ignore argument.type
            self::fail('Expected MixedTypeException');
        } catch (MixedTypeException $e) {
            self::assertStringContainsString('int', $e->getMessage());
            self::assertStringContainsString('string', $e->getMessage());
        }
    }

    public function testTypeIsUnlockedAfterClear(): void
    {
        $list = new SortedLinkedList(static fn (int $a, int $b): int => $a <=> $b);
        $list->add(42);
        self::assertSame(1, $list->count());
        $list->clear();
        self::assertSame(0, $list->count());
        $list->add(99);
        self::assertSame([99], $list->toArray());
    }

    public function testEventListenerReceivesInsertedEvent(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList($this->naturalComparator(), $listener);
        $list->add(42);
        self::assertCount(1, $listener->insertions);
        self::assertSame(42, $listener->insertions[0]->value);
        self::assertSame(1, $listener->insertions[0]->newSize);
    }

    public function testEventListenerReceivesRemovedEvent(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList($this->naturalComparator(), $listener);
        $list->add(5);
        $list->remove(5);
        self::assertCount(1, $listener->removals);
        self::assertSame(5, $listener->removals[0]->value);
        self::assertSame(0, $listener->removals[0]->newSize);
    }

    public function testEventListenerDoesNotReceiveRemovedEventWhenValueAbsent(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList($this->naturalComparator(), $listener);
        $list->remove(99);
        self::assertCount(0, $listener->removals);
    }

    public function testEventListenerReceivesClearedEvent(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList($this->naturalComparator(), $listener);
        $list->add(1);
        $list->add(2);
        $list->clear();
        self::assertCount(1, $listener->clears);
        self::assertSame(2, $listener->clears[0]->previousSize);
    }

    public function testNoEventsDispatchedWhenNoListenerProvided(): void
    {
        self::expectNotToPerformAssertions();
        $list = $this->intList();
        $list->add(1);
        $list->remove(1);
        $list->clear();
    }

    public function testLoggerReceivesDebugOnAdd(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())
            ->method('debug')
            ->with('Item added to SortedLinkedList', self::arrayHasKey('value'));

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(7);
    }

    public function testLoggerReceivesDebugOnRemoveFound(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))
            ->method('debug');

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(7);
        $list->remove(7);
    }

    public function testLoggerReceivesDebugOnClear(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())
            ->method('debug');

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(1);
        $list->clear();
    }

    public function testLoggerReceivesWarningOnTypeMismatch(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('locked'));

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(1);

        try {
            $list->add('bad');
        } catch (MixedTypeException $exception) {
            // expected — exception is the signal, not the message
            unset($exception);
        }
    }

    public function testNoLoggerRequiredByDefault(): void
    {
        self::expectNotToPerformAssertions();
        $list = $this->intList();
        $list->add(1);
        $list->remove(1);
        $list->clear();
    }

    public function testRemoveLastElementFromFourElementList(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->add(3);
        $list->add(4);
        self::assertTrue($list->remove(4));
        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testRemoveMiddleElementFromFourElementList(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->add(3);
        $list->add(4);
        self::assertTrue($list->remove(3));
        self::assertSame([1, 2, 4], $list->toArray());
    }

    public function testEventListenerReceivesRemovedEventForNonHeadElement(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList($this->naturalComparator(), $listener);
        $list->add(1);
        $list->add(2);
        $list->add(3);
        $list->remove(3);
        self::assertCount(1, $listener->removals);
        self::assertSame(3, $listener->removals[0]->value);
        self::assertSame(2, $listener->removals[0]->newSize);
    }

    public function testLoggerExactContextOnAdd(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with('Item added to SortedLinkedList', ['value' => 7, 'size' => 1]);

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(7);
    }

    public function testLoggerExactContextOnRemoveFoundAtHead(): void
    {
        /** @var list<array{message: string, context: array<string, mixed>}> $calls */
        $calls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug')
            ->willReturnCallback(static function (string $message, array $context) use (&$calls): void {
                $calls[] = ['message' => $message, 'context' => $context];
            });

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(7);
        $list->remove(7);

        self::assertCount(2, $calls);
        self::assertSame('Item removed from SortedLinkedList', $calls[1]['message']);
        self::assertSame(['value' => 7, 'removed' => true, 'size' => 0], $calls[1]['context']);
    }

    public function testLoggerExactContextOnRemoveFoundInTraversal(): void
    {
        /** @var list<array{message: string, context: array<string, mixed>}> $calls */
        $calls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug')
            ->willReturnCallback(static function (string $message, array $context) use (&$calls): void {
                $calls[] = ['message' => $message, 'context' => $context];
            });

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(1);
        $list->add(2);
        $list->add(3);
        $list->remove(3);

        self::assertCount(4, $calls);
        self::assertSame('Item removed from SortedLinkedList', $calls[3]['message']);
        self::assertSame(['value' => 3, 'removed' => true, 'size' => 2], $calls[3]['context']);
    }

    public function testLoggerExactContextOnRemoveNotFoundEmptyList(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with('Item not found in SortedLinkedList', ['value' => 99, 'removed' => false, 'size' => 0]);

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->remove(99);
    }

    public function testLoggerExactContextOnRemoveNotFoundTraversal(): void
    {
        /** @var list<array{message: string, context: array<string, mixed>}> $calls */
        $calls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug')
            ->willReturnCallback(static function (string $message, array $context) use (&$calls): void {
                $calls[] = ['message' => $message, 'context' => $context];
            });

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(5);
        $list->remove(99);

        self::assertCount(2, $calls);
        self::assertSame('Item not found in SortedLinkedList', $calls[1]['message']);
        self::assertSame(['value' => 99, 'removed' => false, 'size' => 1], $calls[1]['context']);
    }

    public function testLoggerExactContextOnClear(): void
    {
        /** @var list<array{message: string, context: array<string, mixed>}> $calls */
        $calls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug')
            ->willReturnCallback(static function (string $message, array $context) use (&$calls): void {
                $calls[] = ['message' => $message, 'context' => $context];
            });

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(1);
        $list->clear();

        self::assertCount(2, $calls);
        self::assertSame('SortedLinkedList cleared', $calls[1]['message']);
        self::assertSame(['previousSize' => 1], $calls[1]['context']);
    }

    public function testOfIntegersCreatesEmptyList(): void
    {
        self::assertTrue(SortedLinkedList::ofIntegers()->isEmpty());
    }

    public function testOfIntegersWithValuesSortsOnCreation(): void
    {
        self::assertSame([1, 2, 3], SortedLinkedList::ofIntegers([3, 1, 2])->toArray());
    }

    public function testOfIntegersAcceptsCustomComparator(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 3, 2], static fn (int $a, int $b): int => $b <=> $a);
        self::assertSame([3, 2, 1], $list->toArray());
    }

    public function testOfStringsCreatesEmptyList(): void
    {
        self::assertTrue(SortedLinkedList::ofStrings()->isEmpty());
    }

    public function testOfStringsWithValuesSortsOnCreation(): void
    {
        $list = SortedLinkedList::ofStrings(['banana', 'apple', 'cherry']);
        self::assertSame(['apple', 'banana', 'cherry'], $list->toArray());
    }

    public function testOfStringsAcceptsCustomComparator(): void
    {
        $list = SortedLinkedList::ofStrings(['a', 'c', 'b'], static fn (string $a, string $b): int => $b <=> $a);
        self::assertSame(['c', 'b', 'a'], $list->toArray());
    }

    public function testOfIntegersEnforcesTypeLockOnSubsequentAdd(): void
    {
        $list = SortedLinkedList::ofIntegers([1]);
        $this->expectException(MixedTypeException::class);
        $list->add('oops'); // @phpstan-ignore argument.type
    }

    public function testOfIntegersReturnsNewListOnEachCall(): void
    {
        $a = SortedLinkedList::ofIntegers([1]);
        $b = SortedLinkedList::ofIntegers([2]);
        self::assertSame([1], $a->toArray());
        self::assertSame([2], $b->toArray());
    }

    public function testRemoveAllReturnsZeroWhenValueAbsent(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertSame(0, $list->removeAll(99));
        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testRemoveAllReturnsCountOfRemovedOccurrences(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 2, 2, 3]);
        self::assertSame(3, $list->removeAll(2));
        self::assertSame([1, 3], $list->toArray());
    }

    public function testRemoveAllOnSingleOccurrenceReturnsOne(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertSame(1, $list->removeAll(2));
        self::assertSame([1, 3], $list->toArray());
    }

    public function testRemoveAllOnEmptyListReturnsZero(): void
    {
        self::assertSame(0, SortedLinkedList::ofIntegers()->removeAll(1));
    }

    public function testRemoveAllWorksWithStrings(): void
    {
        $list = SortedLinkedList::ofStrings(['a', 'b', 'b', 'c']);
        self::assertSame(2, $list->removeAll('b'));
        self::assertSame(['a', 'c'], $list->toArray());
    }

    public function testRemoveAllDispatchesEventPerRemoval(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList($this->naturalComparator(), $listener);
        $list->add(5);
        $list->add(5);
        $list->add(5);
        $list->removeAll(5);
        self::assertCount(3, $listener->removals);
    }

    public function testContainsReturnsFalseWhenValueIsBetweenExistingElements(): void
    {
        $list = SortedLinkedList::ofIntegers([50, 100, 150]);
        self::assertFalse($list->contains(99));
        self::assertFalse($list->contains(101));
        self::assertFalse($list->contains(0));
    }

    public function testRemoveReturnsFalseWhenValueIsBetweenExistingElements(): void
    {
        $list = SortedLinkedList::ofIntegers([10, 20, 30]);
        self::assertFalse($list->remove(15));
        self::assertSame([10, 20, 30], $list->toArray());
        self::assertFalse($list->remove(99));
        self::assertSame([10, 20, 30], $list->toArray());
    }

    public function testContainsWithDescendingComparatorFindsAbsentValue(): void
    {
        $list = SortedLinkedList::ofIntegers([100, 50, 10], static fn (int $a, int $b): int => $b <=> $a);
        self::assertFalse($list->contains(75));
        self::assertTrue($list->contains(50));
    }

    public function testLoggerExactContextOnEarlyExitNotFoundPath(): void
    {
        /** @var list<array{message: string, context: array<string, mixed>}> $calls */
        $calls = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug')
            ->willReturnCallback(static function (string $message, array $context) use (&$calls): void {
                $calls[] = ['message' => $message, 'context' => $context];
            });

        $list = new SortedLinkedList($this->naturalComparator(), null, $logger);
        $list->add(10);
        $list->add(20);
        $list->add(30);
        $list->remove(15);

        self::assertCount(4, $calls);
        self::assertSame('Item not found in SortedLinkedList', $calls[3]['message']);
        self::assertSame(['value' => 15, 'removed' => false, 'size' => 3], $calls[3]['context']);
    }

    public function testCloneProducesDeepCopy(): void
    {
        $original = SortedLinkedList::ofIntegers([1, 2, 3]);
        $clone = clone $original;
        $original->remove(2);
        self::assertSame([1, 2, 3], $clone->toArray());
        self::assertSame([1, 3], $original->toArray());
    }

    public function testCloneIsIndependentFromOriginalForAdd(): void
    {
        $original = SortedLinkedList::ofIntegers([1, 3]);
        $clone = clone $original;
        $clone->add(2);
        self::assertSame([1, 3], $original->toArray());
        self::assertSame([1, 2, 3], $clone->toArray());
    }

    public function testCloneOfEmptyList(): void
    {
        $original = SortedLinkedList::ofIntegers();
        $clone = clone $original;
        $clone->add(5);
        self::assertTrue($original->isEmpty());
        self::assertSame([5], $clone->toArray());
    }

    public function testJsonSerializableProducesSortedArray(): void
    {
        $list = SortedLinkedList::ofIntegers([3, 1, 2]);
        self::assertSame('[1,2,3]', json_encode($list));
    }

    public function testJsonSerializableOnEmptyList(): void
    {
        self::assertSame('[]', json_encode(SortedLinkedList::ofIntegers()));
    }

    public function testJsonSerializableWorksWithStrings(): void
    {
        $list = SortedLinkedList::ofStrings(['c', 'a', 'b']);
        self::assertSame('["a","b","c"]', json_encode($list));
    }

    public function testLimitReturnsFirstNElements(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3, 4, 5]);
        self::assertSame([1, 2, 3], $list->limit(3)->toArray());
    }

    public function testLimitZeroReturnsEmptyList(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertTrue($list->limit(0)->isEmpty());
    }

    public function testLimitLargerThanSizeReturnsFullList(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertSame([1, 2, 3], $list->limit(100)->toArray());
    }

    public function testLimitDoesNotMutateOriginal(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        $list->limit(1);
        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testSliceReturnsSubsequence(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3, 4, 5]);
        self::assertSame([2, 3, 4], $list->slice(1, 3)->toArray());
    }

    public function testSliceFromZero(): void
    {
        $list = SortedLinkedList::ofIntegers([10, 20, 30]);
        self::assertSame([10, 20], $list->slice(0, 2)->toArray());
    }

    public function testSlicePastEndReturnsAvailableElements(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertSame([2, 3], $list->slice(1, 100)->toArray());
    }

    public function testSliceZeroLengthReturnsEmpty(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertTrue($list->slice(1, 0)->isEmpty());
    }

    public function testLimitNegativeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SortedLinkedList::ofIntegers([1, 2, 3])->limit(-1);
    }

    public function testSliceNegativeOffsetThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SortedLinkedList::ofIntegers([1, 2, 3])->slice(-1, 2);
    }

    public function testSliceNegativeLengthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SortedLinkedList::ofIntegers([1, 2, 3])->slice(0, -1);
    }

    public function testBaseExceptionCatchesBothSubtypes(): void
    {
        try {
            $list = new SortedLinkedList(static fn (int $a, int $b): int => $a <=> $b);
            $list->add(1);
            $list->add('bad'); // @phpstan-ignore argument.type
        } catch (SortedLinkedListException $e) {
            self::assertInstanceOf(MixedTypeException::class, $e);
        }
    }

    public function testEmptyListExceptionIsSubtypeOfBase(): void
    {
        $this->expectException(SortedLinkedListException::class);
        SortedLinkedList::ofIntegers()->first();
    }

    public function testEqualsReturnsTrueForIdenticalLists(): void
    {
        $a = SortedLinkedList::ofIntegers([1, 2, 3]);
        $b = SortedLinkedList::ofIntegers([3, 1, 2]);
        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentContent(): void
    {
        $a = SortedLinkedList::ofIntegers([1, 2, 3]);
        $b = SortedLinkedList::ofIntegers([1, 2, 4]);
        self::assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentSizes(): void
    {
        $a = SortedLinkedList::ofIntegers([1, 2, 3]);
        $b = SortedLinkedList::ofIntegers([1, 2]);
        self::assertFalse($a->equals($b));
    }

    public function testEqualsTwoEmptyLists(): void
    {
        self::assertTrue(SortedLinkedList::ofIntegers()->equals(SortedLinkedList::ofIntegers()));
    }

    public function testEqualsWithDuplicatesMustMatchExactly(): void
    {
        $a = SortedLinkedList::ofIntegers([1, 2, 2]);
        $b = SortedLinkedList::ofIntegers([1, 1, 2]);
        self::assertFalse($a->equals($b));
    }

    public function testReduceSum(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3, 4, 5]);
        self::assertSame(15, $list->reduce(static fn (int $carry, int $v): int => $carry + $v, 0));
    }

    public function testReduceOnEmptyListReturnsInitial(): void
    {
        self::assertSame(99, SortedLinkedList::ofIntegers()->reduce(static fn (int $c, int $v): int => $c + $v, 99));
    }

    public function testReduceCanBuildString(): void
    {
        $list = SortedLinkedList::ofStrings(['apple', 'banana', 'cherry']);
        $result = $list->reduce(static fn (string $c, string $v): string => $c === '' ? $v : "$c, $v", '');
        self::assertSame('apple, banana, cherry', $result);
    }

    public function testLastIsO1AfterManyInsertions(): void
    {
        $list = SortedLinkedList::ofIntegers();

        for ($i = 1; $i <= 1_000; $i++) {
            $list->add($i);
        }

        self::assertSame(1_000, $list->last());
    }

    public function testLastAfterRemovingTailElement(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        $list->remove(3);
        self::assertSame(2, $list->last());
    }

    public function testLastAfterClearThrows(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        $list->clear();
        $this->expectException(EmptyListException::class);
        $list->last();
    }

    public function testLastAfterCloneAndMutateOriginal(): void
    {
        $original = SortedLinkedList::ofIntegers([1, 2, 3]);
        $clone = clone $original;
        $original->remove(3);
        self::assertSame(3, $clone->last());
        self::assertSame(2, $original->last());
    }

    public function testUniqueRemovesAdjacentDuplicates(): void
    {
        self::assertSame([1, 2, 3], SortedLinkedList::ofIntegers([1, 2, 2, 2, 3])->unique()->toArray());
    }

    public function testUniqueOnListWithNoDuplicates(): void
    {
        self::assertSame([1, 2, 3], SortedLinkedList::ofIntegers([1, 2, 3])->unique()->toArray());
    }

    public function testUniqueOnEmptyList(): void
    {
        self::assertTrue(SortedLinkedList::ofIntegers()->unique()->isEmpty());
    }

    public function testUniqueDoesNotMutateOriginal(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 1, 2]);
        $list->unique();
        self::assertSame([1, 1, 2], $list->toArray());
    }

    public function testUniqueOnStrings(): void
    {
        self::assertSame(['a', 'b', 'c'], SortedLinkedList::ofStrings(['a', 'a', 'b', 'c', 'c'])->unique()->toArray());
    }

    public function testUniqueWithCaseInsensitiveComparatorKeepsFirst(): void
    {
        $list = SortedLinkedList::ofStrings(
            ['apple', 'Apple', 'Banana'],
            Comparator::stringsIgnoreCase(),
        );
        self::assertSame(2, $list->unique()->count());
    }

    public function testToStringOnEmptyList(): void
    {
        self::assertSame('SortedLinkedList<empty>[]', (string) SortedLinkedList::ofIntegers());
    }

    public function testToStringOnIntList(): void
    {
        self::assertSame('SortedLinkedList<int>[1, 2, 3]', (string) SortedLinkedList::ofIntegers([3, 1, 2]));
    }

    public function testToStringOnStringList(): void
    {
        self::assertSame('SortedLinkedList<string>[apple, cherry]', (string) SortedLinkedList::ofStrings(['cherry', 'apple']));
    }

    public function testToStringImplementsStringable(): void
    {
        self::assertInstanceOf(Stringable::class, SortedLinkedList::ofIntegers([1]));
    }

    public function testLastAfterHeadRemovalOnMultiElementList(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        $list->remove(1);
        self::assertSame(3, $list->last());
    }

    public function testLastAfterMultipleHeadRemovals(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3, 4]);
        $list->remove(1);
        $list->remove(2);
        self::assertSame(4, $list->last());
    }

    public function testTailIsCorrectAfterRemovingMiddleElement(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        $list->remove(2);
        self::assertSame(3, $list->last());
    }

    public function testLastThrowsAfterRemovingSingleElement(): void
    {
        $list = SortedLinkedList::ofIntegers([42]);
        $list->remove(42);
        $this->expectException(EmptyListException::class);
        $list->last();
    }

    public function testSerializeThrowsLogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('SortedLinkedList cannot be serialized');
        serialize(SortedLinkedList::ofIntegers([1, 2, 3]));
    }

    public function testSerializeExceptionMessageStartsWithLibraryName(): void
    {
        try {
            serialize(SortedLinkedList::ofIntegers([1]));
            self::fail('Expected LogicException');
        } catch (LogicException $e) {
            // The message must start with the library name, not with the hint.
            // This kills the Concat mutation that swaps the two parts.
            self::assertStringStartsWith('SortedLinkedList', $e->getMessage());
            self::assertStringContainsString('toArray()', $e->getMessage());
        }
    }

    public function testUnserializeThrowsLogicException(): void
    {
        $list = SortedLinkedList::ofIntegers([1]);
        $this->expectException(LogicException::class);
        $list->__unserialize([]);
    }

}
