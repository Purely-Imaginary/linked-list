<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList;

/**
 * Singly-linked node using PHP 8.4 features:
 * - $value: public readonly — set once at construction, readable without a getter
 * - $next: public — writable by SortedLinkedList; @internal protects from outside
 *
 * Both properties are accessed directly (no getters/setters), reducing indirection.
 *
 * @template T of int|string
 *
 * @internal
 */
final class Node
{

    /**
     * @var Node<T>|null
     */
    public ?self $next = null;

    /**
     * @param T $value
     */
    public function __construct(public readonly int|string $value)
    {
    }

}
