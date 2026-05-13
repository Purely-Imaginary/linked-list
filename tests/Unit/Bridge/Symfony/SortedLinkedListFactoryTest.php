<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListFactory;
use ShipMonk\SortedLinkedList\Exception\MixedTypeException;

final class SortedLinkedListFactoryTest extends TestCase
{

    public function testCreateIntListReturnsSortedList(): void
    {
        $factory = new SortedLinkedListFactory();
        $list = $factory->createIntList(static fn (int $a, int $b): int => $a <=> $b);
        $list->add(3);
        $list->add(1);
        self::assertSame([1, 3], $list->toArray());
    }

    public function testCreateStringListReturnsSortedList(): void
    {
        $factory = new SortedLinkedListFactory();
        $list = $factory->createStringList(static fn (string $a, string $b): int => $a <=> $b);
        $list->add('banana');
        $list->add('apple');
        self::assertSame(['apple', 'banana'], $list->toArray());
    }

    public function testCreateIntListEnforcesTypeLock(): void
    {
        $factory = new SortedLinkedListFactory();
        $list = $factory->createIntList(static fn (int $a, int $b): int => $a <=> $b);
        $list->add(1);
        $this->expectException(MixedTypeException::class);
        // @phpstan-ignore argument.type (intentionally testing runtime type guard)
        $list->add('oops');
    }

}
