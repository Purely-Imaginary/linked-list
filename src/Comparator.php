<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList;

use function strcasecmp;
use function strcmp;

/**
 * Factory for common comparator callables used with SortedLinkedList.
 *
 * @api
 */
final class Comparator
{

    private function __construct()
    {
    }

    /**
     * @return callable(int, int): int
     */
    public static function integers(): callable
    {
        return static fn (int $a, int $b): int => $a <=> $b;
    }

    /**
     * @return callable(int, int): int
     */
    public static function integersDescending(): callable
    {
        return static fn (int $a, int $b): int => $b <=> $a;
    }

    /**
     * @return callable(string, string): int
     */
    public static function strings(): callable
    {
        return static fn (string $a, string $b): int => strcmp($a, $b);
    }

    /**
     * @return callable(string, string): int
     */
    public static function stringsDescending(): callable
    {
        return static fn (string $a, string $b): int => strcmp($b, $a);
    }

    /**
     * @return callable(string, string): int
     */
    public static function stringsIgnoreCase(): callable
    {
        return static fn (string $a, string $b): int => strcasecmp($a, $b);
    }

}
