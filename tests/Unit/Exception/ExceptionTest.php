<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShipMonk\SortedLinkedList\Exception\EmptyListException;
use ShipMonk\SortedLinkedList\Exception\MixedTypeException;

final class ExceptionTest extends TestCase
{

    public function testMixedTypeExceptionExtendsRuntimeException(): void
    {
        $exception = new MixedTypeException('mixed types');
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('mixed types', $exception->getMessage());
    }

    public function testEmptyListExceptionExtendsRuntimeException(): void
    {
        $exception = new EmptyListException('list is empty');
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('list is empty', $exception->getMessage());
    }

}
