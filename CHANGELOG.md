# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-05-14

### Added

#### Core Library

- `SortedLinkedList<T of int|string>` — generic sorted linked list with insertion-sort
- `SortedLinkedList::ofIntegers(array $values = [], ?callable $comparator = null): self<int>` — named constructor
- `SortedLinkedList::ofStrings(array $values = [], ?callable $comparator = null): self<string>` — named constructor
- `Comparator` — static factory: `integers()`, `integersDescending()`, `strings()`, `stringsDescending()`, `stringsIgnoreCase()`
- **Mutation methods:** `add()`, `remove()`, `removeAll()`, `clear()`
- **Query methods:** `contains()`, `first()`, `last()`, `count()`, `isEmpty()`, `equals()`
- **Functional methods:** `filter()`, `merge()`, `limit()`, `slice()`, `reduce()`
- **Standard PHP interfaces:** `Countable`, `IteratorAggregate`, `JsonSerializable`, `Stringable`
- `unique()` — deduplicates using comparator equality in O(n) single pass
- `__clone()` — deep copy of the node chain; mutations on clone and original are independent
- Sorted early-exit in `contains()` and `remove()` — stops traversal once past the target's sorted position
- **O(1) `last()`** — tail pointer maintained across `add()`, `remove()`, `clear()`, `__clone()`
- **Serialization safety** — `__serialize()`/`__unserialize()` throw `LogicException` with actionable message instead of cryptic PHP crash
- `__toString()` — `SortedLinkedList<type>[val1, val2, ...]` format for debug readability

#### Event System

- `ListEventInterface` — marker interface for all events
- `ItemInsertedEvent<T>`, `ItemRemovedEvent<T>`, `ListClearedEvent`
- `ListEventListenerInterface<T>` — typed observer interface
- `ListenerChain<T>` — dispatches events to multiple listeners in order

#### Exception Hierarchy

- `SortedLinkedListException` — base exception; catch-all for library errors
- `MixedTypeException extends SortedLinkedListException` — mixed int+string insertion
- `EmptyListException extends SortedLinkedListException` — `first()`/`last()` on empty list

#### Symfony Bridge

- `SortedLinkedListFactory` — autowirable factory for typed list creation
- `SortedLinkedListBundle` — `AbstractBundle` that auto-collects `ListEventListenerInterface` services into a `ListenerChain`

#### PSR-3 Logging

- Optional `LoggerInterface` injection; uses `NullLogger` when absent (zero overhead)
- `DEBUG` on every mutation; `WARNING` before throwing `MixedTypeException`

#### PHP 8.5 Features

- **Pipe operator `|>`** — used in `reduce()` and `__toString()` to express transformation pipelines top-to-bottom instead of inside-out
- **`Node` simplified** — PHP 8.4's `public readonly` constructor promotion eliminates all getters/setters; `@internal` protects the public mutable `$next` from external misuse
- Minimum PHP version: `^8.5`

#### Toolchain

- PHPStan level 10 + `shipmonk/phpstan-rules` + `shipmonk/dead-code-detector`
- Psalm (vimeo/psalm) with `#[\Override]` enforcement
- Rector (rector/rector) with PHP_83 + CODE_QUALITY set
- phparkitect — enforces core/bridge boundary and exception hierarchy as machine-checked rules
- `shipmonk/coding-standard` (PHP_CodeSniffer + Slevomat)
- `shipmonk/coverage-guard` — 100% per-method coverage enforcement
- `shipmonk/composer-dependency-analyser`
- CaptainHook — pre-commit (CS+PHPStan) and pre-push (PHPUnit) git hooks
- PHPBench — benchmark suite empirically verifying O(1) `last()` and O(n) `add()`
- Infection PHP — 97%+ mutation score
- GitHub Actions CI on PHP 8.3 and 8.4
- `@api` / `@internal` annotations throughout
