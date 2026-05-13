<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\Comparator;
use ShipMonk\SortedLinkedList\SortedLinkedList;

final class ComparatorTest extends TestCase
{

    public function testIntegersAscending(): void
    {
        $list = SortedLinkedList::ofIntegers([3, 1, 2], Comparator::integers());
        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testIntegersDescending(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 3, 2], Comparator::integersDescending());
        self::assertSame([3, 2, 1], $list->toArray());
    }

    public function testStringsAscending(): void
    {
        $list = SortedLinkedList::ofStrings(['c', 'a', 'b'], Comparator::strings());
        self::assertSame(['a', 'b', 'c'], $list->toArray());
    }

    public function testStringsDescending(): void
    {
        $list = SortedLinkedList::ofStrings(['a', 'c', 'b'], Comparator::stringsDescending());
        self::assertSame(['c', 'b', 'a'], $list->toArray());
    }

    public function testStringsIgnoreCase(): void
    {
        $list = SortedLinkedList::ofStrings(['Banana', 'apple', 'Cherry'], Comparator::stringsIgnoreCase());
        self::assertSame(['apple', 'Banana', 'Cherry'], $list->toArray());
    }

    public function testDefaultOfIntegersUsesIntegersComparator(): void
    {
        $listA = SortedLinkedList::ofIntegers([3, 1, 2]);
        $listB = SortedLinkedList::ofIntegers([3, 1, 2], Comparator::integers());
        self::assertSame($listA->toArray(), $listB->toArray());
    }

    public function testDefaultOfStringsUsesStringsComparator(): void
    {
        $listA = SortedLinkedList::ofStrings(['c', 'a', 'b']);
        $listB = SortedLinkedList::ofStrings(['c', 'a', 'b'], Comparator::strings());
        self::assertSame($listA->toArray(), $listB->toArray());
    }

}
