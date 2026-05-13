# SortedLinkedList

A PHP 8.5 library providing a generic `SortedLinkedList<T of int|string>` — a singly-linked list that keeps values in caller-defined sort order. Designed to ShipMonk's engineering standards: PHPStan level 10 + Psalm, 100% per-method coverage, 95%+ mutation score, Rector on CI.

[![CI](https://github.com/Purely-Imaginary/linked-list/actions/workflows/ci.yml/badge.svg)](https://github.com/Purely-Imaginary/linked-list/actions)

---

## Installation

```bash
composer require purely-imaginary/linked-list
```

**Requirements:** PHP 8.5+, `psr/log` ^3.0

---

## Quick Start

```php
use ShipMonk\SortedLinkedList\Comparator;
use ShipMonk\SortedLinkedList\SortedLinkedList;

// Named constructors — ascending natural order by default
$list = SortedLinkedList::ofIntegers([3, 1, 2]);
$list->toArray(); // [1, 2, 3]

$list = SortedLinkedList::ofStrings(['banana', 'apple', 'cherry']);
$list->toArray(); // ['apple', 'banana', 'cherry']

// Comparator helpers
$list = SortedLinkedList::ofIntegers([], Comparator::integersDescending());
$list->add(1); $list->add(3); $list->add(2);
$list->toArray(); // [3, 2, 1]

// JSON serialization
json_encode($list); // "[3,2,1]"

// Clone — deep copy, mutations are independent
$copy = clone $list;
$copy->remove(2);
$list->toArray(); // unchanged
```

---

## Type Safety

The list locks its value type on first insertion. Mixing types throws `MixedTypeException` both statically (PHPStan generics) and at runtime.

```php
$list = SortedLinkedList::ofIntegers([1, 2]);
$list->add('oops'); // throws MixedTypeException

// Catch any library error via base type
try {
    $list->first(); // empty list
} catch (\ShipMonk\SortedLinkedList\Exception\SortedLinkedListException $e) {
    // catches MixedTypeException and EmptyListException
}
```

Call `clear()` to reset the type lock.

---

## Comparator

`Comparator` provides factory methods for common orderings:

```php
use ShipMonk\SortedLinkedList\Comparator;

Comparator::integers()           // ascending  (default for ofIntegers)
Comparator::integersDescending()
Comparator::strings()            // ascending  (default for ofStrings)
Comparator::stringsDescending()
Comparator::stringsIgnoreCase()  // case-insensitive ascending
```

---

## API Reference

### Named Constructors

| Method | Description |
|---|---|
| `SortedLinkedList::ofIntegers(array $values = [], ?callable $comparator = null): self<int>` | Integer list |
| `SortedLinkedList::ofStrings(array $values = [], ?callable $comparator = null): self<string>` | String list |

### Instance Methods

| Method | Returns | Description |
|---|---|---|
| `add(T $value)` | `void` | Insert maintaining sorted order |
| `remove(T $value)` | `bool` | Remove first occurrence; `true` if found |
| `removeAll(T $value)` | `int<0,max>` | Remove all occurrences; returns count |
| `contains(T $value)` | `bool` | `true` if value is present |
| `first()` | `T` | Smallest element; throws `EmptyListException` if empty |
| `last()` | `T` | Largest element; throws `EmptyListException` if empty |
| `count()` | `int` | Element count; also works with `count($list)` |
| `isEmpty()` | `bool` | `true` if list has no elements |
| `clear()` | `void` | Remove all elements and reset type lock |
| `toArray()` | `list<T>` | All elements as a sorted PHP array |
| `jsonSerialize()` | `list<T>` | Implements `JsonSerializable`; enables `json_encode($list)` |
| `__toString()` | `string` | `SortedLinkedList<type>[v1, v2, ...]` — implements `Stringable` |
| `__serialize()` | `never` | Always throws `LogicException` — use `toArray()` instead |
| `filter(callable(T): bool)` | `static<T>` | New list with matching values only |
| `merge(self<T>)` | `static<T>` | New re-sorted union of both lists |
| `unique()` | `static<T>` | New list with consecutive comparator-equal values removed |
| `limit(int $n)` | `static<T>` | First N elements; throws `InvalidArgumentException` if negative |
| `slice(int $offset, int $length)` | `static<T>` | Subsequence; throws `InvalidArgumentException` if negative |
| `equals(self<T>)` | `bool` | Deep equality comparison |
| `reduce(callable, mixed $initial)` | `TResult` | Fold to a single value |
| `getIterator()` | `ArrayIterator<int,T>` | Implements `IteratorAggregate` for `foreach` |

### Performance

- **Insertion:** O(n) insertion-sort on `add()`
- **`last()`:** **O(1)** — tail pointer maintained on every mutation. Empirically verified: 0.12µs on 10, 100, and 1000 elements.
- **`contains()` / `remove()`:** Early exit when traversal passes the sorted position — 800× faster than full-scan for absent values near the start.
- **`clone`:** O(n) deep copy of the node chain — fully independent mutations
- **`unique()`:** O(n) — single pass exploiting sorted order (duplicates are always adjacent)

---

## Events

```php
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;
use ShipMonk\SortedLinkedList\EventListener\ListenerChain;
use ShipMonk\SortedLinkedList\Event\{ItemInsertedEvent, ItemRemovedEvent, ListClearedEvent};

class AuditListener implements ListEventListenerInterface
{
    #[\Override]
    public function onInserted(ItemInsertedEvent $event): void
    {
        printf("Inserted %s — size: %d\n", $event->value, $event->newSize);
    }

    #[\Override]
    public function onRemoved(ItemRemovedEvent $event): void { ... }

    #[\Override]
    public function onCleared(ListClearedEvent $event): void { ... }
}

// Single listener
$list = new SortedLinkedList(Comparator::integers(), new AuditListener());

// Multiple listeners via ListenerChain
$chain = new ListenerChain([new AuditListener(), new MetricsListener()]);
$list = new SortedLinkedList(Comparator::integers(), $chain);
```

---

## Logging

```php
use Monolog\Logger;

$list = new SortedLinkedList(Comparator::integers(), null, new Logger('list'));
```

- `DEBUG` — every `add`, `remove`, and `clear`
- `WARNING` — type mismatch before throwing `MixedTypeException`

---

## Symfony Integration

`config/bundles.php`:

```php
return [
    ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListBundle::class => ['all' => true],
];
```

```php
use ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListFactory;

class WarehouseService
{
    public function __construct(private readonly SortedLinkedListFactory $lists) {}

    public function getPriorities(): array
    {
        $list = $this->lists->createIntList(Comparator::integers());
        // ...
        return $list->toArray();
    }
}
```

All services implementing `ListEventListenerInterface` are auto-tagged `sorted_linked_list.listener` and collected into a `ListenerChain` injected into the factory.

---

## Development

```bash
make install        # docker build + composer install
make hooks          # install git pre-commit/pre-push hooks (run once per clone)
make test           # PHPUnit with coverage
make stan           # PHPStan level 10 + shipmonk/phpstan-rules
make psalm          # Psalm static analysis
make rector         # Rector (verify no changes needed)
make arch           # phparkitect architecture rules
make cs             # Code style (shipmonk/coding-standard)
make cs-fix         # Code style auto-fix
make bench          # PHPBench benchmarks (verifies O(1) last(), O(n) add())
make coverage-guard # Per-method 100% coverage enforcement
make mutate         # Infection mutation tests (minMSI 97%)
make deps           # Dependency graph analysis
make ci             # Full quality gate: stan → psalm → rector → arch → cs → test → coverage-guard → mutate → deps
```

---

## License

MIT
