<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList;

use ArrayIterator;
use Closure;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;
use ShipMonk\SortedLinkedList\Exception\EmptyListException;
use ShipMonk\SortedLinkedList\Exception\MixedTypeException;
use Stringable;
use function array_map;
use function assert;
use function get_debug_type;
use function implode;
use function sprintf;

/**
 * @template T of int|string
 * @implements IteratorAggregate<int, T>
 *
 * @api
 */
final class SortedLinkedList implements Countable, IteratorAggregate, JsonSerializable, Stringable
{

    /**
     * @var Node<T>|null
     */
    private ?Node $head = null;

    /**
     * @var Node<T>|null
     */
    private ?Node $tail = null;

    /**
     * @var int<0, max>
     */
    private int $size = 0;

    private ?string $lockedType = null;

    /**
     * @var Closure(T, T): int
     */
    private readonly Closure $comparator;

    private readonly LoggerInterface $logger;

    /**
     * @param callable(T, T): int $comparator
     * @param ListEventListenerInterface<T>|null $eventListener
     */
    public function __construct(
        callable $comparator,
        private readonly ?ListEventListenerInterface $eventListener = null,
        ?LoggerInterface $logger = null,
    )
    {
        $this->comparator = Closure::fromCallable($comparator);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param list<int> $values
     * @param callable(int, int): int|null $comparator
     * @return self<int>
     */
    public static function ofIntegers(
        array $values = [],
        ?callable $comparator = null,
    ): self
    {
        /** @var self<int> $list */
        $list = new self($comparator ?? Comparator::integers());

        foreach ($values as $value) {
            $list->add($value);
        }

        return $list;
    }

    /**
     * @param list<string> $values
     * @param callable(string, string): int|null $comparator
     * @return self<string>
     */
    public static function ofStrings(
        array $values = [],
        ?callable $comparator = null,
    ): self
    {
        /** @var self<string> $list */
        $list = new self($comparator ?? Comparator::strings());

        foreach ($values as $value) {
            $list->add($value);
        }

        return $list;
    }

    /**
     * @return never
     *
     * @throws LogicException always — the comparator Closure cannot be serialized.
     *                         Use toArray() for a serializable snapshot of the values.
     */
    public function __serialize(): array
    {
        throw new LogicException(
            'SortedLinkedList cannot be serialized: the comparator is a Closure, which PHP cannot serialize. '
            . 'Call toArray() to obtain a serializable list<T> snapshot.',
        );
    }

    /**
     * @param array<mixed> $_data
     *
     * @throws LogicException always — re-create the list with ofIntegers() or ofStrings().
     */
    public function __unserialize(array $_data): never
    {
        throw new LogicException(
            'SortedLinkedList cannot be unserialized. Re-create the list with ofIntegers() or ofStrings().',
        );
    }

    /**
     * Deep-copies the node chain so the clone and original are fully independent.
     * Mutations on either list do not affect the other after cloning.
     */
    public function __clone()
    {
        if (!$this->head instanceof Node) {
            return;
        }

        // Deep-copy the node chain so mutations on either list do not affect the other.
        $newHead = new Node($this->head->getValue());
        $newCurrent = $newHead;
        $oldCurrent = $this->head->getNext();

        while ($oldCurrent instanceof Node) {
            $newNode = new Node($oldCurrent->getValue());
            $newCurrent->setNext($newNode);
            $newCurrent = $newNode;
            $oldCurrent = $oldCurrent->getNext();
        }

        $this->head = $newHead;
        $this->tail = $newCurrent; // last node in the copied chain
    }

    /**
     * @param T $value
     *
     * @throws MixedTypeException when a value of a different type than the locked type is inserted
     */
    public function add(int|string $value): void
    {
        $this->lockType($value);

        $newNode = new Node($value);

        if (!$this->head instanceof Node || ($this->comparator)($value, $this->head->getValue()) <= 0) {
            $newNode->setNext($this->head);
            $this->head = $newNode;
        } else {
            $current = $this->head;

            while (
                $current->getNext() instanceof Node
                && ($this->comparator)($value, $current->getNext()->getValue()) > 0
            ) {
                $current = $current->getNext();
            }

            $newNode->setNext($current->getNext());
            $current->setNext($newNode);
        }

        // If the new node has no successor it is the new last node.
        if (!$newNode->getNext() instanceof Node) {
            $this->tail = $newNode;
        }

        $this->size++;
        $this->logger->debug('Item added to SortedLinkedList', ['value' => $value, 'size' => $this->size]);

        if ($this->eventListener instanceof ListEventListenerInterface) {
            $event = new ItemInsertedEvent($value, $this->size);
            $this->eventListener->onInserted($event);
        }
    }

    /**
     * Removes the first occurrence of $value and returns true if found.
     * Returns false if the value is not in the list. Never throws.
     * Uses early exit when traversal passes the sorted position.
     *
     * @param T $value
     */
    public function remove(int|string $value): bool
    {
        if (!$this->head instanceof Node) {
            $this->logger->debug('Item not found in SortedLinkedList', ['value' => $value, 'removed' => false, 'size' => $this->size]);

            return false;
        }

        if ($this->head->getValue() === $value) {
            $this->head = $this->head->getNext();
            // If the list is now empty, tail must also be cleared.
            if (!$this->head instanceof Node) {
                $this->tail = null;
            }

            assert($this->size > 0);
            $this->size--;
            $this->logger->debug('Item removed from SortedLinkedList', ['value' => $value, 'removed' => true, 'size' => $this->size]);

            if ($this->eventListener instanceof ListEventListenerInterface) {
                /** @var ItemRemovedEvent<T> $event */
                $event = new ItemRemovedEvent($value, $this->size);
                $this->eventListener->onRemoved($event);
            }

            return true;
        }

        $current = $this->head;

        while ($current->getNext() instanceof Node) {
            $cmp = ($this->comparator)($current->getNext()->getValue(), $value);

            if ($cmp === 0) {
                break;
            }

            if ($cmp > 0) {
                $this->logger->debug('Item not found in SortedLinkedList', ['value' => $value, 'removed' => false, 'size' => $this->size]);

                return false;
            }

            $current = $current->getNext();
        }

        if (!$current->getNext() instanceof Node) {
            $this->logger->debug('Item not found in SortedLinkedList', ['value' => $value, 'removed' => false, 'size' => $this->size]);

            return false;
        }

        // If the node being removed is the tail, current becomes the new tail.
        if (!$current->getNext()->getNext() instanceof Node) {
            $this->tail = $current;
        }

        $current->setNext($current->getNext()->getNext());
        assert($this->size > 0);
        $this->size--;
        $this->logger->debug('Item removed from SortedLinkedList', ['value' => $value, 'removed' => true, 'size' => $this->size]);

        if ($this->eventListener instanceof ListEventListenerInterface) {
            $event = new ItemRemovedEvent($value, $this->size);
            $this->eventListener->onRemoved($event);
        }

        return true;
    }

    /**
     * Removes all occurrences of $value and returns the count removed.
     * Returns 0 if the value is not present. Never throws.
     *
     * @param T $value
     * @return int<0, max>
     */
    public function removeAll(int|string $value): int
    {
        $removed = 0;

        while ($this->remove($value)) {
            $removed++;
        }

        return $removed;
    }

    /**
     * @param T $value
     */
    public function contains(int|string $value): bool
    {
        $current = $this->head;

        while ($current instanceof Node) {
            if ($current->getValue() === $value) {
                return true;
            }

            if (($this->comparator)($current->getValue(), $value) > 0) {
                return false;
            }

            $current = $current->getNext();
        }

        return false;
    }

    /**
     * @return T
     *
     * @throws EmptyListException
     */
    public function first(): int|string
    {
        if (!$this->head instanceof Node) {
            throw new EmptyListException('Cannot get first element of an empty list.');
        }

        return $this->head->getValue();
    }

    /**
     * @return T
     *
     * @throws EmptyListException
     */
    public function last(): int|string
    {
        if (!$this->tail instanceof Node) {
            throw new EmptyListException('Cannot get last element of an empty list.');
        }

        return $this->tail->getValue();
    }

    #[Override]
    public function count(): int
    {
        return $this->size;
    }

    public function isEmpty(): bool
    {
        return $this->size === 0;
    }

    /**
     * Removes all elements and resets the type lock.
     * After clear(), the list accepts values of a different type than it previously held.
     */
    public function clear(): void
    {
        $previousSize = $this->size;
        $this->head = null;
        $this->tail = null;
        $this->size = 0;
        $this->lockedType = null;
        $this->logger->debug('SortedLinkedList cleared', ['previousSize' => $previousSize]);
        $this->eventListener?->onCleared(new ListClearedEvent($previousSize));
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        /** @var list<T> $result */
        $result = [];
        $current = $this->head;

        while ($current instanceof Node) {
            $result[] = $current->getValue();
            $current = $current->getNext();
        }

        return $result;
    }

    /**
     * @return ArrayIterator<int, T>
     */
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * @return list<T>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns a human-readable representation for debugging.
     * Format: SortedLinkedList<type>[val1, val2, ...]
     */
    #[Override]
    public function __toString(): string
    {
        $type = $this->lockedType ?? 'empty';
        $values = implode(', ', array_map(static fn (int|string $v): string => (string) $v, $this->toArray()));

        return sprintf('SortedLinkedList<%s>[%s]', $type, $values);
    }

    /**
     * Return a new list containing only the first $n elements.
     *
     * @return static<T>
     *
     * @throws InvalidArgumentException when $n is negative
     */
    public function limit(int $n): static
    {
        if ($n < 0) {
            throw new InvalidArgumentException(sprintf('Limit must be non-negative, %d given.', $n));
        }

        /** @var static<T> $new */
        $new = new self($this->comparator);
        $current = $this->head;
        $count = 0;

        while ($current instanceof Node && $count < $n) {
            $new->add($current->getValue());
            $current = $current->getNext();
            $count++;
        }

        return $new;
    }

    /**
     * Return a new list containing $length elements starting at $offset (0-based).
     *
     * @return static<T>
     *
     * @throws InvalidArgumentException when $offset or $length is negative
     */
    public function slice(
        int $offset,
        int $length,
    ): static
    {
        if ($offset < 0) {
            throw new InvalidArgumentException(sprintf('Offset must be non-negative, %d given.', $offset));
        }

        if ($length < 0) {
            throw new InvalidArgumentException(sprintf('Length must be non-negative, %d given.', $length));
        }

        /** @var static<T> $new */
        $new = new self($this->comparator);
        $current = $this->head;
        $pos = 0;

        while ($current instanceof Node && $pos < $offset + $length) {
            if ($pos >= $offset) {
                $new->add($current->getValue());
            }

            $current = $current->getNext();
            $pos++;
        }

        return $new;
    }

    /**
     * Returns a new list containing only values matching $predicate.
     * The returned list does not inherit the event listener or logger of the original.
     *
     * @param callable(T): bool $predicate
     * @return static<T>
     */
    public function filter(callable $predicate): static
    {
        /** @var static<T> $new */
        $new = new self($this->comparator);

        foreach ($this->toArray() as $value) {
            if ($predicate($value)) {
                $new->add($value);
            }
        }

        return $new;
    }

    /**
     * Returns a new list with consecutive comparator-equal values deduplicated.
     * Because the list is always sorted, equal values are always adjacent,
     * so a single O(n) pass is sufficient.
     * Uses comparator equality (not ===) so custom comparators are respected.
     *
     * @return static<T>
     */
    public function unique(): static
    {
        /** @var static<T> $new */
        $new = new self($this->comparator);
        /** @var T|null $prev */
        $prev = null;

        foreach ($this->toArray() as $value) {
            // $prev === null means this is the first element (always unique).
            // After the first iteration $prev is T, so PHPStan can narrow for the comparator call.
            if ($prev === null || ($this->comparator)($value, $prev) !== 0) {
                $new->add($value);
                $prev = $value;
            }
        }

        return $new;
    }

    /**
     * Returns a new re-sorted union of both lists using this list's comparator.
     * The returned list does not inherit the event listener or logger of either operand.
     *
     * @param self<T> $other
     * @return static<T>
     *
     * @throws MixedTypeException when both lists are non-empty and have different locked types
     */
    public function merge(self $other): static
    {
        if (
            $this->lockedType !== null
            && $other->lockedType !== null
            && $this->lockedType !== $other->lockedType
        ) {
            throw new MixedTypeException(sprintf(
                'Cannot merge lists of different types: "%s" and "%s".',
                $this->lockedType,
                $other->lockedType,
            ));
        }

        /** @var static<T> $new */
        $new = new self($this->comparator);

        foreach ($this->toArray() as $value) {
            $new->add($value);
        }

        foreach ($other->toArray() as $value) {
            $new->add($value);
        }

        return $new;
    }

    /**
     * Returns true if both lists contain the same values in the same order.
     *
     * @param self<T> $other
     */
    public function equals(self $other): bool
    {
        if ($this->size !== $other->size) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    /**
     * Fold the list to a single value by applying $callback to each element.
     *
     * @param callable(TResult, T): TResult $callback
     * @param TResult $initial
     * @return TResult
     *
     * @template TResult
     */
    public function reduce(
        callable $callback,
        mixed $initial,
    ): mixed
    {
        $carry = $initial;

        foreach ($this->toArray() as $value) {
            $carry = $callback($carry, $value);
        }

        return $carry;
    }

    /**
     * @param T $value
     */
    private function lockType(int|string $value): void
    {
        $type = get_debug_type($value);

        if ($this->lockedType === null) {
            $this->lockedType = $type;

            return;
        }

        if ($this->lockedType !== $type) {
            $message = sprintf(
                'SortedLinkedList is locked to type "%s", cannot insert value of type "%s".',
                $this->lockedType,
                $type,
            );
            $this->logger->warning($message);

            throw new MixedTypeException($message);
        }
    }

}
