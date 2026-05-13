<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListBundle;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SortedLinkedListBundleTest extends TestCase
{

    public function testBundleExtendsAbstractBundle(): void
    {
        self::assertInstanceOf(AbstractBundle::class, new SortedLinkedListBundle());
    }

    public function testBundleHasCorrectName(): void
    {
        $bundle = new SortedLinkedListBundle();
        self::assertSame('SortedLinkedListBundle', $bundle->getName());
    }

}
