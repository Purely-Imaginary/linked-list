<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList;

/**
 * @template T of int|string
 *
 * @internal
 */
final class Node
{

    /**
     * @var Node<T>|null
     */
    private ?self $next = null;

    /**
     * @param T $value
     */
    public function __construct(private readonly int|string $value)
    {
    }

    /**
     * @return T
     */
    public function getValue(): int|string
    {
        return $this->value;
    }

    /**
     * @return Node<T>|null
     */
    public function getNext(): ?self
    {
        return $this->next;
    }

    /**
     * @param Node<T>|null $next
     */
    public function setNext(?self $next): void
    {
        $this->next = $next;
    }

}
