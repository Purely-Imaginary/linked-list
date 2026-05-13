# Symfony Bridge — Design Rationale

## Why a bridge at all?

`SortedLinkedList` has no Symfony dependency in `src/`. The bridge lives in `src/Bridge/Symfony/` and is only needed by applications that use Symfony's DI container. Keeping it separate means vanilla PHP users pay zero cost for Symfony functionality they don't use, while Symfony users get first-class autowiring.

## Why `SortedLinkedListFactory` instead of registering the list as a service?

`SortedLinkedList` requires a comparator at construction time. Comparators are application-specific logic (ascending, descending, case-insensitive, domain-defined) that cannot be guessed by the DI container. A factory lets Symfony manage the singleton factory (with logger and event listener autowired) while callers supply the comparator at the moment of list creation.

## Why does the bundle collect listeners into a `ListenerChain`?

Symfony applications routinely register multiple services implementing the same interface. Without a chain, only one listener can be injected into the factory (the last one autowired wins, or it fails with "multiple candidates"). The bundle uses `tagged_iterator('sorted_linked_list.listener')` to collect all tagged listener services into a single `ListenerChain`, which the factory then uses. This is zero-configuration: any service implementing `ListEventListenerInterface` is automatically included.

## Why `sorted_linked_list.listener` as the tag name?

`ListenerChain` itself implements `ListEventListenerInterface`. If we used autoconfiguration on `ListEventListenerInterface` alone, the `ListenerChain` service would be tagged and then added to itself, creating a circular dependency. Using a distinct tag `sorted_linked_list.listener` means only user-defined implementations are collected into the chain. `ListenerChain` is registered separately as a service, not via autoconfiguration.

## Why `#[\Override]` on `loadExtension()`?

`AbstractBundle::loadExtension()` is the extension point provided by Symfony. Without `#[\Override]`, a typo in the method name (e.g., `loadExtensions`) would compile fine and silently skip all DI registration. The attribute turns that into a compile-time error.

## Why are parameter names `$configurator` and `$container` (not `$container` and `$builder`)?

These are the names used in Symfony's own `AbstractBundle::loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container)`. Using the same names avoids `ParamNameMismatch` warnings from Psalm and makes the signature immediately recognisable to Symfony developers. Our earlier version used `$container` and `$builder` (a common confusion since `ContainerBuilder` is often called "the container"), which Psalm flagged.
