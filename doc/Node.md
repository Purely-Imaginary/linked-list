# Node — Design Rationale

## Why is `Node` `@internal`?

`Node` is a pure implementation detail. Callers of `SortedLinkedList` never need to reference a `Node` directly — they interact only through the list's public API. Marking it `@internal` communicates this clearly: `shipmonk/dead-code-detector` will still verify it is used within `SortedLinkedList`, but external code that imports `Node` will get a PHPStan warning.

## Why getters/setters instead of public properties?

`shipmonk/coding-standard` (and good OOP practice) prohibit public mutable properties. A public `$next` property would allow any code — including code outside `SortedLinkedList` — to corrupt the linked list structure. Getters and setters give `SortedLinkedList` full control over how the chain is modified, even though `Node` itself is `@internal`.

## Why `?self` instead of `?Node` in `getNext()`?

`self` in the return type refers to the same class, which is `Node`. In a generic context with PHPStan, `?self` is equivalent to `?Node<T>` without needing to repeat the generic parameter. `?Node` (without `self`) would also work but `?self` is idiomatic PHP for recursive linked-list node types.

## Why is `$value` `readonly`?

Once a `Node` is created with a value, that value never changes — if a value needs to change in the list, the containing node is removed and a new node with the new value is inserted. `readonly` enforces this immutability at the property level, preventing accidental mutation of values already placed in the sorted chain.
