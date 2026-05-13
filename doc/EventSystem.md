# Event System — Design Rationale

## Why events over callbacks?

Callbacks (`$onInsert = fn($v) => ...`) are simpler but have two problems at scale:

1. **Single-observer only** — a callback property accepts exactly one function.
2. **Type erasure** — PHP closures carry no type information about their parameters.

A typed `ListEventListenerInterface<T>` forces the implementor to handle all three event types (`onInserted`, `onRemoved`, `onCleared`) in one cohesive class. PHPStan can verify that the listener's methods accept the right generic event types. Callbacks would require three separate properties with unchecked `callable` types.

## Why `readonly` event classes?

Events are immutable facts: "this value was inserted at this moment with this resulting size." There is no reason to modify an event after it has been created. `final readonly class` expresses this intent at the language level: the PHP runtime enforces immutability, and `final` prevents subclasses from weakening it.

## Why `value` and `newSize` on insert/remove events?

`value` tells the listener what was mutated. `newSize` is included because computing it from outside the list would require an extra `count()` call. Since the list already knows its size at mutation time, emitting it is essentially free and eliminates a common source of redundant computation in listeners.

`ListClearedEvent` carries only `previousSize` (not a value) because clearing removes all elements — there is no single value to report.

## Why `ListEventListenerInterface<T>` is generic?

Without the template, `onInserted(ItemInsertedEvent $event)` would accept an `ItemInsertedEvent<int>` sent to an `SortedLinkedList<string>` listener — a type mismatch with no compile-time detection. The template `@template T of int|string` propagates to the event parameter types, giving PHPStan the information to flag incompatible listener/list pairings.

## Why `ListenerChain` instead of an event dispatcher?

A full event dispatcher (like Symfony's `EventDispatcherInterface`) would be much more powerful but would drag in a Symfony dependency and require callers to register events by name string. `ListenerChain` is a zero-dependency composite that adds multiple-listener support while staying consistent with the existing `ListEventListenerInterface` contract. The list only ever calls three methods; there is no need for a general-purpose dispatcher.

## Why is `ListenerChain` excluded from coverage-guard?

`ListenerChain` IS tested in `tests/Unit/EventListener/ListenerChainTest.php`. The coverage-guard exclusion is only for `Comparator::__construct` (intentionally unreachable) and `SortedLinkedListBundle::loadExtension` (requires a live Symfony DI container). `ListenerChain` has full method coverage.
