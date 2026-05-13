# SortedLinkedList — Design Rationale

## Why a linked list instead of a sorted array?

A sorted PHP array requires O(n) shifting on every insertion (every element after the insertion point must be moved). A singly-linked list only needs to update two next-pointers, making it O(1) to insert once the position is found — the position search is still O(n), but the memory operations are cheaper and there is no copying.

For a library that emphasises correctness and composability over raw throughput, the linked list model also has a cleaner node-based implementation: there is no hidden index arithmetic, and the `Node` abstraction makes the pointer logic explicit and auditable.

## Why `final`?

`SortedLinkedList` is declared `final` because its internal invariants (sorted order, type lock, size counter) are maintained by controlled insertion and removal paths. Allowing subclasses to override `add()` or `remove()` would make it impossible to guarantee these invariants without every subclass re-implementing the same guards. `final` is the safest default for concrete data-structure classes.

## Why `@template T of int|string`?

PHP has no native generics at runtime, but PHPStan understands `@template`. The bound `of int|string` is enforced two ways:

1. **Statically:** PHPStan uses the template to verify callers never mix types at the call site. `SortedLinkedList<int>` from `ofIntegers()` will reject `add(string)` at analysis time.
2. **At runtime:** `lockType()` captures `get_debug_type($value)` on the first insertion and rejects any subsequent value of a different type.

Both layers are necessary: static analysis protects the call site, the runtime guard protects against `mixed`-typed code that bypasses the type system.

## Why `ofIntegers()` and `ofStrings()` named constructors?

The constructor signature `__construct(callable $comparator, ...)` with `callable(T, T): int` hits PHPStan's callable-contravariance limitation: a `callable(int, int): int` is narrower than `callable(int|string, int|string): int`, so PHPStan cannot automatically infer `T = int` from the comparator alone.

Named constructors solve this by using `/** @var self<int> $list */` assertions at the one place they are needed (inside the factory method), exposing a clean API where callers write `SortedLinkedList::ofIntegers()` and get back a properly-typed `self<int>` without any suppress annotations.

The optional `$values` pre-fill parameter avoids the common `$list->add(...); $list->add(...)` boilerplate at construction time.

## Why a `Comparator` helper class with a private constructor?

Users should not instantiate `Comparator` — it is a namespace for factory functions, not a value. The private constructor enforces this at both language and IDE level. PHP does not have `static` classes or namespaced functions with type annotations, so a final class with a private constructor is the idiomatic PHP pattern for a utility namespace.

Providing `Comparator::integers()`, `Comparator::stringsIgnoreCase()` etc. avoids repetitive `fn(int $a, int $b): int => $a <=> $b` boilerplate across callers and gives a single authoritative definition that is easy to test and read.

## Why `Closure::fromCallable($comparator)` in the constructor?

PHP cannot store `callable` as a typed property; only `Closure` is a concrete type. `fromCallable()` converts any callable (function name string, array pair, closure) into a `Closure`, allowing the property to be declared with the PHPStan annotation `@var Closure(T, T): int` rather than the opaque `callable`. This gives PHPStan visibility into the comparator's signature everywhere it is invoked internally.

## Why insertion-sort on `add()`?

Insertion-sort is the natural algorithm for a singly-linked list: walk from the head to the first node whose value is "greater than" the new value (using the comparator), then splice in the new node. There is no cheaper alternative given only forward pointers.

The alternative — appending to the tail and sorting the whole list — would be O(n log n) per insertion and require either a full traversal or a tail pointer. Insertion-sort at O(n) per `add()` is the correct choice for a structure that maintains sorted order incrementally.

## Why early exit in `contains()` and `remove()`?

Because the list is always sorted, once we reach a node whose value is "greater than" the target according to the comparator, the target cannot appear anywhere after that node. Continuing to traverse would be wasted work.

The early-exit check is `comparator(currentNode, target) > 0 → return false/break`. This is valid for any total-order comparator, including descending ones: in descending order, "greater than" in comparator terms means the target would have appeared earlier, not later.

Without early exit, `contains(0)` on a list of a million integers from 1 to 1,000,000 would traverse all million nodes. With early exit, it stops at node 1.

## Why `assert($this->size > 0)` before decrement?

`$this->size` is annotated as `int<0, max>` for PHPStan. When we decrement it with `$this->size--`, PHPStan's type system widens the result to `int<-1, max>`, which no longer satisfies the `int<0, max>` constraint. The `assert` narrows the type back to `int<1, max>` at that point, making `$this->size-- → int<0, max>` valid again.

The assert is unreachable in practice (we only call decrement after confirming a node exists and was removed), so it has no runtime cost in production (`assert` is a no-op when `zend.assertions = -1`).

## Why `__clone()` with manual node deep-copy?

PHP's default `clone` performs a shallow copy: all properties are copied by value, but object properties are copied by reference. Since `$head` is a `Node` object reference, a shallow clone would produce two `SortedLinkedList` instances sharing the same node chain. Any mutation (remove, add at a position) would corrupt both lists.

`__clone()` rebuilds the node chain by walking the original and creating fresh `Node` instances. The caller gets a fully independent copy — the standard semantics PHP developers expect from `clone`.

## Why `#[\Override]` on interface implementations?

PHP 8.3 introduced the `#[\Override]` attribute. When present on a method, PHP throws a compile-time error if the method does not actually override a parent or interface method. This catches typos in method names and protects against interface evolution breaking concrete implementations silently. Psalm enforces this as a rule; we apply it throughout so every interface implementation is explicitly marked.

## Why `JsonSerializable`?

`json_encode($list)` without `JsonSerializable` would serialize the list's private properties rather than its logical content. Implementing `jsonSerialize(): array` returns `toArray()`, which is the user-visible sorted content. This makes the list a drop-in replacement for arrays in JSON APIs without forcing callers to call `toArray()` manually.

## Why `equals(self $other): bool`?

PHP's `==` and `===` operators compare objects by identity or by value (including all private properties, object IDs, etc.), not by logical equality. Two lists with the same sorted content would not be `==` unless they are the same instance. `equals()` provides deterministic, content-based comparison.

## Why `reduce(callable $callback, mixed $initial): mixed`?

`reduce` is the fundamental fold operation on a sequence. It composes cleanly with the existing `filter()` and `merge()` to form a complete functional API: filter the list to a subset, then reduce it to a scalar. The `@template TResult` PHPStan annotation allows callers to keep full type inference (`$sum = $list->reduce(fn(int $c, int $v) => $c + $v, 0)` correctly infers `$sum: int`).

The implementation uses PHP 8.5's pipe operator to express the transformation as a data pipeline:

```php
return $this->toArray()
    |> (fn (array $arr): mixed => array_reduce($arr, $callback, $initial));
```

This makes the data-flow direction explicit: the list's values are piped into `array_reduce`. The alternative (nested function calls reading inside-out) is technically equivalent but harder to scan.

## Why `limit()` and `slice()`?

These are the most common subsequence operations on ordered sequences. `limit(n)` is used everywhere a "top N" view is needed (leaderboards, priority queues, pagination). `slice()` is the generalisation. Both are non-mutating (return new lists) for the same reason `filter()` and `merge()` are: a sorted list is a value-like object, and callers should not be surprised by mutations when they only asked for a view.

Negative arguments throw `InvalidArgumentException` rather than silently returning empty or wrapping around, because silent wrong-direction behaviour is a common source of bugs that are hard to debug.

## Why `ListenerChain`?

The original design injected a single `ListEventListenerInterface`. In real applications — especially Symfony apps — multiple independent listeners are common: an audit log, a metrics exporter, a real-time notifier. Without `ListenerChain`, callers would have to build their own dispatch loop or register only one listener.

`ListenerChain` implements `ListEventListenerInterface` itself, so it is a drop-in replacement from the `SortedLinkedList`'s perspective. The Symfony bundle auto-collects all tagged listener services into a chain, making multi-listener setups zero-configuration.

## Why `SortedLinkedListException` base class?

Without a base exception, callers who want to handle any library error must catch `MixedTypeException | EmptyListException`, which is verbose and breaks when new exception types are added. A base class `SortedLinkedListException` lets callers write `catch (SortedLinkedListException $e)` today and still catch future exceptions automatically.

`MixedTypeException` and `EmptyListException` are `final` because their meaning is precise and fixed — no subclassing should change what "the list is locked to a different type" means.

## Why `@api` and `@internal`?

`@api` marks the stable public surface that callers depend on and that semantic versioning guarantees stability for. `@internal` marks `Node` as an implementation detail that callers must not reference directly. Without these annotations, every class is ambiguously "maybe public, maybe private" — a library reviewer cannot tell which parts constitute the API.

ShipMonk's own packages use these annotations consistently for exactly this reason.

## Why the `$tail` pointer?

The original `last()` traversed the entire chain every call — O(n). For a priority-queue pattern where callers frequently check the largest element, this is expensive.

A `$tail` property pointing to the last node makes `last()` O(1). It must be maintained on every mutation:
- `add()`: if the new node has no successor, it is the new tail.
- `remove()` (head path): if the list becomes empty, tail = null.
- `remove()` (traversal path): if the node being removed has no successor, its predecessor becomes the new tail.
- `clear()`: tail = null.
- `__clone()`: the last new node created in the copied chain becomes the clone's tail.

The PHPBench suite verifies this empirically: `benchLast10/100/1000` all show ~0.12µs flat.

## Why `__serialize()` throws instead of silently failing?

`serialize($list)` previously crashed with `Exception: Serialization of 'Closure' is not allowed` — a cryptic PHP-internal message with no library context. A caller had no idea why serialization failed or what to do about it.

`__serialize()` throws `\LogicException` with a message that names the cause (the comparator Closure) and suggests the solution (`toArray()`). `__unserialize()` throws for the same reason. This turns a confusing crash into a deliberate, debuggable refusal.

## Why `__toString()` and `Stringable`?

`(string) $list` previously threw because `SortedLinkedList` wasn't `Stringable`. Implementing `Stringable` with format `SortedLinkedList<type>[val1, val2, ...]` makes the class:
- Drop-in compatible with string interpolation in log messages
- Immediately readable in var_dump, xdebug, and error traces
- Consistent with how PHP itself renders collections

The format includes the locked type, making it clear at a glance whether you are looking at an int or string list.

## Why the pipe operator `|>` in `__toString()` and `reduce()`?

PHP 8.5 introduced the pipe operator `|>`, which passes the left-hand side as the first argument to a parenthesized callable on the right. It turns nested, inside-out function calls into a readable top-to-bottom pipeline.

`__toString()` previously read:
```php
$values = implode(', ', array_map(static fn (int|string $v): string => (string) $v, $this->toArray()));
```

To understand this, you have to read from the innermost call outward: `toArray()` → `array_map()` → `implode()`. With pipe:
```php
$values = $this->toArray()
    |> (fn (array $a): array => array_map(static fn (int|string $v): string => (string) $v, $a))
    |> (fn (array $a): string => implode(', ', $a));
```

The data flows left-to-right, top-to-bottom — in the same order you read it.

Note: PHP 8.5 requires arrow functions on the right side of `|>` to be parenthesized.

## Why use `^8.5` as the minimum PHP version?

The pipe operator is a PHP 8.5 feature; it cannot be lowered to a syntax-compatible polyfill. The Node simplification (PHP 8.4's `public readonly` on promoted constructor properties, removing all getters/setters) is also a 8.4+ feature. Requiring `^8.5` is the honest minimum that reflects actual syntax used.

For libraries targeting wider compatibility, a `^8.3` build that avoids 8.5 syntax would be equally valid — but would forfeit the expressiveness gains.

## Why `unique()` uses the comparator for equality?

`unique()` deduplicates by comparing consecutive elements (the list is sorted, so duplicates are always adjacent). Using `$value !== $prev` (strict equality) would be wrong for custom comparators: `Comparator::stringsIgnoreCase()` treats `"Apple"` and `"apple"` as equal, but they are `!==`. Using `($this->comparator)($value, $prev) !== 0` respects the caller's definition of equality — the same definition used to sort the list in the first place.
