# SortedLinkedList Library — Design Spec

**Date:** 2026-05-13
**Author:** Amadeusz Opac
**Status:** Approved

---

## Overview

A standalone PHP 8.3 Composer package providing a generic `SortedLinkedList<T of int|string>` that keeps inserted values in caller-defined sort order. The type constraint (homogeneous: strings only OR integers only, never mixed) is enforced both statically via PHPStan generics and at runtime via a type-lock guard. An optional thin Symfony Bundle wraps the core library for DI autowiring.

---

## Project Structure

```
shipmonk/
├── src/
│   ├── SortedLinkedList.php
│   ├── Node.php                             # internal — not part of public API
│   ├── Exception/
│   │   ├── MixedTypeException.php
│   │   └── EmptyListException.php
│   ├── Event/
│   │   ├── ListEventInterface.php
│   │   ├── ItemInsertedEvent.php
│   │   ├── ItemRemovedEvent.php
│   │   └── ListClearedEvent.php
│   ├── EventListener/
│   │   └── ListEventListenerInterface.php
│   └── Bridge/
│       └── Symfony/
│           └── SortedLinkedListBundle.php
├── tests/
│   ├── Unit/
│   │   ├── SortedLinkedListTest.php
│   │   ├── Event/
│   │   └── Exception/
│   │   └── Bridge/Symfony/
│   └── Mutation/                            # placeholder for future custom mutators (not required now)
├── docker/
│   └── php/Dockerfile
├── docker-compose.yml
├── composer.json
├── phpstan.neon
├── phpcs.xml
├── infection.json
├── phpunit.xml
└── Makefile
```

---

## Core API

### `SortedLinkedList<T of int|string>`

Implements `Countable`, `IteratorAggregate`.

```php
/**
 * @template T of int|string
 */
final class SortedLinkedList implements Countable, IteratorAggregate
{
    /**
     * @param callable(T, T): int                $comparator
     * @param ListEventListenerInterface<T>|null $eventListener
     */
    public function __construct(
        callable $comparator,
        ?ListEventListenerInterface $eventListener = null,
        ?LoggerInterface $logger = null,
    );

    /** @param T $value */
    public function add(int|string $value): void;

    /** @param T $value — returns true if at least one occurrence was removed, false if value was not present */
    public function remove(int|string $value): bool;

    /** @param T $value */
    public function contains(int|string $value): bool;

    /** @return T @throws EmptyListException */
    public function first(): int|string;

    /** @return T @throws EmptyListException */
    public function last(): int|string;

    public function count(): int;
    public function isEmpty(): bool;
    public function clear(): void;

    /** @return list<T> */
    public function toArray(): array;

    /** @return Traversable<int, T> */
    public function getIterator(): Traversable;

    /** @param callable(T): bool $predicate @return static<T> */
    public function filter(callable $predicate): static;

    /** @return static<T> — re-sorted union; both lists must share the same locked type or one must be empty */
    public function merge(self $other): static;
}
```

### Behaviours

- **Duplicates:** allowed — `[3,1,3,2]` inserts as `[1,2,3,3]`.
- **Type locking:** `add()` captures `get_debug_type($value)` on first insertion. Any subsequent insertion of a different type throws `MixedTypeException`.
- **Comparator contract:** callers always supply the comparator explicitly (e.g. `fn($a, $b) => $a <=> $b`). No hidden default.
- **Immutable helpers:** `filter()` and `merge()` return new instances and do not mutate the receiver.
- **`Countable` + `IteratorAggregate`:** works with `count()`, `foreach`, spread operator.

---

## Events & Logging

### Event system

```php
/** @template T of int|string */
interface ListEventListenerInterface {
    /** @param ItemInsertedEvent<T> $event */
    public function onInserted(ItemInsertedEvent $event): void;
    /** @param ItemRemovedEvent<T> $event */
    public function onRemoved(ItemRemovedEvent $event): void;
    /** @param ListClearedEvent<T> $event */
    public function onCleared(ListClearedEvent $event): void;
}

/** @template T of int|string */
final readonly class ItemInsertedEvent {
    /** @param T $value */
    public function __construct(
        public readonly int|string $value,
        public readonly int $newSize,
    ) {}
}
```

`ItemRemovedEvent` carries `value` + `newSize`. `ListClearedEvent` carries `previousSize`.

### PSR-3 logging

- Injected as optional `?LoggerInterface $logger`. Internally uses `NullLogger` when `null` — no null-checks in business logic.
- `add()` → `DEBUG` with `['value', 'size']`
- `remove()` → `DEBUG` with `['value', 'removed', 'size']`
- `clear()` → `DEBUG` with `['previousSize']`
- `MixedTypeException` → `WARNING` before throw

---

## Symfony Bridge

`SortedLinkedListBundle` extends `AbstractBundle`. It:
- Registers `SortedLinkedList` as a prototype-scoped service
- Autowires `LoggerInterface` automatically
- Exposes `ListEventListenerInterface` as a Symfony service tag

No business logic lives in the bridge. The bundle is optional — the library works without Symfony.

---

## Toolchain

| Tool | Config | Purpose |
|---|---|---|
| PHPStan level 10 | `phpstan.neon` | Static analysis |
| `shipmonk/phpstan-rules` | `phpstan.neon` | ~40 extra strict rules |
| `shipmonk/dead-code-detector` | `phpstan.neon` | Unused code detection |
| `shipmonk/phpstan-dev` | dev | PHPStan rule test harness |
| `shipmonk/coding-standard` | `phpcs.xml` | CS Fixer + Slevomat rules |
| `shipmonk/coverage-guard` | CI | Per-method coverage enforcement |
| `shipmonk/composer-dependency-analyser` | Makefile | Shadow/dead dependency detection |
| Infection PHP | `infection.json` | Mutation testing |
| `phpstan/mutant-killer-infection-runner` | `infection.json` | PHPStan filters escaped mutants |
| PHPUnit 11 | `phpunit.xml` | Unit tests |

---

## Testing Strategy

### Unit tests

Coverage enforced per-method via `shipmonk/coverage-guard` (not by percentage):
- All `SortedLinkedList` public methods: 100%
- All event classes: 100%
- All exception paths: 100%

Test groups: core behaviour, event dispatch, exception types/messages, Symfony bundle wiring.

### Mutation tests (Infection PHP)

```json
{
  "minMsi": 90,
  "minCoveredMsi": 95,
  "mutators": { "@default": true, "UnwrapArrayFilter": true, "CastInt": true, "CastString": true }
}
```

Escaped mutants are passed through `phpstan/mutant-killer-infection-runner` — if PHPStan catches them statically they do not count against the MSI score.

---

## Docker

PHP 8.3-cli-alpine container. `docker-compose.yml` mounts the project at `/app`.

```makefile
make install     # composer install in container
make test        # PHPUnit
make stan        # PHPStan
make cs          # CS check
make cs-fix      # CS fix
make mutate      # Infection PHP
make deps        # composer-dependency-analyser
make ci          # install + stan + cs + test + mutate + deps
```

---

## Constraints & Non-Goals

- No Symfony application — this is a library, not a bundle-only package.
- No persistence, serialization, or external I/O in the core library.
- No sorting algorithm beyond insertion-sort on `add()` — the linked list structure makes O(n) insertion natural; a balanced BST is out of scope.
- PHP 8.3 minimum — no polyfills for older versions.
