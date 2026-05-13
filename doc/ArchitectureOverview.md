# Architecture Overview

## What this is

A standalone PHP 8.3 Composer library providing `SortedLinkedList<T of int|string>` — a singly-linked list that keeps values in caller-defined sorted order. The library has no framework runtime dependency; an optional Symfony 7 bridge adds DI integration.

## Directory Structure

```
src/
├── SortedLinkedList.php          — core class (~530 lines)
├── Node.php                      — @internal linked-list node
├── Comparator.php                — static factory for common comparators
├── Exception/
│   ├── SortedLinkedListException.php  — base exception (catch-all)
│   ├── MixedTypeException.php         — mixed int+string insertion
│   └── EmptyListException.php         — first()/last() on empty list
├── Event/
│   ├── ListEventInterface.php         — marker interface
│   ├── ItemInsertedEvent.php          — value + newSize (readonly, generic)
│   ├── ItemRemovedEvent.php           — value + newSize (readonly, generic)
│   └── ListClearedEvent.php           — previousSize (readonly)
├── EventListener/
│   ├── ListEventListenerInterface.php — generic typed observer
│   └── ListenerChain.php             — composite: dispatches to N listeners
└── Bridge/Symfony/
    ├── SortedLinkedListBundle.php     — AbstractBundle, wires ListenerChain
    └── SortedLinkedListFactory.php    — autowirable factory
```

## Core Invariants

1. **Sorted order:** The node chain is always ordered by the comparator. `comparator(node_i, node_i+1) ≤ 0` for all adjacent pairs.
2. **Type homogeneity:** Once the first value is inserted, `$lockedType` is set. All subsequent insertions must match this type. `clear()` resets the lock.
3. **Size accuracy:** `$size` always equals the number of nodes in the chain.
4. **Tail accuracy:** `$tail` always points to the last node in the chain, or `null` for an empty list. Maintained by `add()`, `remove()`, `clear()`, and `__clone()`.

## Key Data Flow: `add(value)`

```
1. lockType(value)          — set or verify $lockedType
2. new Node(value)          — allocate node
3. find insertion point     — linear scan with comparator
4. splice node into chain   — update two next-pointers
5. if newNode.next === null: $tail = newNode  ← tail pointer maintenance
6. $size++
7. logger->debug(...)
8. eventListener->onInserted(new ItemInsertedEvent(value, $size))
```

## Key Data Flow: `remove(value)`

```
1. head === null? → log not-found, return false (empty list path)
2. head.value === value? → remove head, log, event, return true (head path)
3. Walk chain with early exit:
   - comparator(next.value, target) === 0 → break (found position)
   - comparator(next.value, target)  > 0 → log not-found, return false (early exit)
   - else → advance current
4. next === null after walk? → log not-found, return false (not present)
5. splice out next node → update one next-pointer
6. $size--, log, event, return true
```

## Type Safety Architecture

```
Compile time (PHPStan):
  SortedLinkedList<int>  -- ofIntegers() returns this
  add(int $value)        -- PHPStan sees this signature for <int> instance
  add('hello')           -- PHPStan error: Argument of type string ...

Runtime:
  lockType() captures get_debug_type() on first add()
  Subsequent calls with different type → MixedTypeException
```

## Event Flow

```
SortedLinkedList<T>
  └── ?ListEventListenerInterface<T>
        ↕ (could be)
      ListenerChain<T>
        ├── AuditListener<T>
        ├── MetricsListener<T>
        └── NotificationListener<T>
```

In Symfony, the bundle auto-collects all `sorted_linked_list.listener`-tagged services into a `ListenerChain` and injects it into the factory.

## Static Analysis Stack

```
PHPStan level 10
  + shipmonk/phpstan-rules   (~40 extra rules)
  + shipmonk/dead-code-detector
  + reportUnmatchedIgnoredErrors: true

Psalm level 5
  + Override attribute enforcement
  + Parameter name matching
  + Property mutability analysis

Rector (PHP_83 + CODE_QUALITY)
  → instanceof checks
  → new self() in final classes
  → runs in --dry-run in CI (fails if anything to change)

phparkitect 1.0
  → Event classes must implement ListEventInterface
  → Exception classes must extend SortedLinkedListException
  → Core namespaces must not import from Bridge/

shipmonk/coding-standard (PHP_CodeSniffer + Slevomat)
  → 150+ formatting rules
  → multiline signatures, import ordering, etc.

CaptainHook (git hooks)
  → pre-commit: phpcs + phpstan
  → pre-push: phpunit --no-coverage

PHPBench 1.6 (bench/)
  → Empirical verification: O(1) last(), O(n) add(), early-exit contains()
```

## Testing Stack

```
PHPUnit 11
  → 151 tests, 222+ assertions
  → coverage-xml + clover + junit for tool integration

shipmonk/coverage-guard
  → 100% per-method coverage (not percentage)
  → excluded: Comparator::__construct (unreachable private guard)
  → excluded: SortedLinkedListBundle::loadExtension (needs live DI container)

Infection PHP
  → 97% MSI (Mutation Score Indicator)
  → 97% Covered Code MSI
  → 6 equivalent escaped mutants (documented as false positives)
  → 2 timed-out mutants (Break_→continue = infinite loop, correctly detected)
```

## Public API Surface

Classes marked `@api` (stable contract):
- `SortedLinkedList` with all public methods
- `Comparator` with all static methods
- `SortedLinkedListException`, `MixedTypeException`, `EmptyListException`
- `ListEventInterface`, `ItemInsertedEvent`, `ItemRemovedEvent`, `ListClearedEvent`
- `ListEventListenerInterface`, `ListenerChain`
- `SortedLinkedListFactory`, `SortedLinkedListBundle`

Classes marked `@internal` (implementation detail):
- `Node` — never reference this from outside the library

## Complexity Reference

| Operation | Time |
|---|---|
| `add(value)` | O(n) — linear scan + O(1) splice |
| `remove(value)` | O(n) — with early exit for absent values |
| `removeAll(value)` | O(n × k) where k = duplicate count |
| `contains(value)` | O(n) — with early exit |
| `first()` | O(1) — head pointer |
| `last()` | **O(1)** — tail pointer (empirically verified in PHPBench) |
| `toArray()` | O(n) |
| `unique()` | O(n) — single pass with comparator equality check |
| `filter()` | O(n²) — n insertions into a new sorted list |
| `merge(other)` | O((n+m)²) — insert each element of combined lists |
| `limit(n)` | O(n²) — n insertions into sorted list |
| `slice(offset, length)` | O(n²) |
| `clone` | O(n) — full chain copy + tail pointer update |
| `equals(other)` | O(n) — size check then toArray comparison |
| `reduce(cb, init)` | O(n) |
| `__toString()` | O(n) — iterates to build string |
| `__serialize()` | O(1) — immediately throws LogicException |
| `jsonSerialize()` | O(n) — delegates to toArray() |
