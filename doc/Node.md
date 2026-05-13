# Node — Design Rationale

## Why is `Node` `@internal`?

`Node` is a pure implementation detail. Callers of `SortedLinkedList` never need to reference a `Node` directly — they interact only through the list's public API. Marking it `@internal` communicates this clearly: `shipmonk/dead-code-detector` will still verify it is used within `SortedLinkedList`, but external code that imports `Node` will get a PHPStan warning.

## Why public properties instead of getters/setters? (PHP 8.4+)

The original design used `private $next` with `getNext()` and `setNext()` methods, and `private readonly $value` with `getValue()`. This is idiomatic pre-8.4 PHP: expose state through explicit methods, hide internal fields.

PHP 8.4 makes this boilerplate unnecessary for `@internal` classes:

- **`$value: public readonly`** — set once at construction via constructor promotion, directly readable as `$node->value`. The `readonly` modifier guarantees immutability without a getter.
- **`$next: public`** — directly readable and writable as `$node->next`. The `@internal` annotation on the class prevents external code from accessing it; PHPStan enforces this. Since `SortedLinkedList` is the only consumer, full public access is safe.

This removes 3 methods (`getValue`, `getNext`, `setNext`) and replaces method calls throughout `SortedLinkedList` with simpler property access (`$node->value`, `$node->next = ...`), reducing indirection without sacrificing safety.

## Why `public` for `$next` instead of `public private(set)`?

PHP 8.4's asymmetric visibility `public private(set)` restricts writes to the declaring class only. Since `SortedLinkedList` is a *different* class that needs to mutate `$next`, `private(set)` would forbid it. The options were:
- `public` — readable and writable everywhere, guarded by `@internal`
- `public protected(set)` — writable by Node and subclasses (Node has none, since it's `final`)
- Keep `setNext()` — defeats the purpose of the refactor

`public` with `@internal` is the honest choice: the @internal annotation is the access boundary, not the PHP visibility modifier.

## Why `?self` in the property type?

`self` in a property type refers to the declaring class. In a generic context with PHPStan, `?self` is equivalent to `?Node<T>` without repeating the generic parameter. It reads as "nullable reference to another node of the same type" — exactly what a linked-list node holds.

## Why is `$value` `readonly`?

Once a `Node` is created with a value, that value never changes — if a value needs to change in the list, the containing node is removed and a new node is inserted. `readonly` enforces this at the language level.
