# SortedLinkedList Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement eight quality improvements: named constructors, sorted early-exit in contains/remove, removeAll(), coverage-guard wiring, redundant readonly cleanup, composer.lock removal, GitHub Actions CI, and README.

**Architecture:** All changes are additive or cosmetic to the existing library in `/home/amadeusz/Projects/recruitment/shipmonk`. No public API is removed. Named constructors (`ofIntegers` / `ofStrings`) replace the test `naturalComparator()` pattern and resolve PHPStan's callable-contravariance workaround. Early exit exploits the sorted invariant: when `comparator(node, target) > 0` the target cannot appear later. `removeAll()` is implemented by looping `remove()` to stay DRY.

**Tech Stack:** PHP 8.3, PHPUnit 11, PHPStan 2 (level 10), shipmonk/coding-standard, shipmonk/coverage-guard, Infection PHP, Docker, GitHub Actions.

---

## File Map

| File | Change |
|---|---|
| `src/SortedLinkedList.php` | Add `ofIntegers()`, `ofStrings()`, `removeAll()`; early-exit `contains()` and `remove()` traversal |
| `src/Event/ItemInsertedEvent.php` | Remove redundant `readonly` from promoted properties |
| `src/Event/ItemRemovedEvent.php` | Remove redundant `readonly` from promoted properties |
| `tests/Unit/SortedLinkedListTest.php` | Replace `intList()` / `stringList()` helpers with named constructors; add tests for `removeAll()`, early-exit, named constructors |
| `tests/Unit/Bridge/Symfony/SortedLinkedListFactoryTest.php` | Update factory tests to use named comparator callables |
| `coverage-guard.php` | Configure 100% method coverage, exclude bridge bundle method |
| `Makefile` | Add `coverage` target (Clover XML); wire `coverage-guard` into `ci` |
| `.gitignore` | Add `composer.lock` |
| `.github/workflows/ci.yml` | Full CI matrix on push/PR |
| `README.md` | Installation, API, events, Symfony bundle docs |

---

## Task 1: Fix Redundant `readonly` on Event Properties

**Files:**
- Modify: `src/Event/ItemInsertedEvent.php`
- Modify: `src/Event/ItemRemovedEvent.php`

`final readonly class` makes all promoted constructor properties implicitly readonly. The explicit `public readonly` keyword on each property is redundant.

- [ ] **Step 1: Remove redundant `readonly` from ItemInsertedEvent**

`src/Event/ItemInsertedEvent.php` — change `public readonly int|string $value` and `public readonly int $newSize` to `public int|string $value` and `public int $newSize`:

```php
<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Event;

/**
 * @template T of int|string
 */
final readonly class ItemInsertedEvent implements ListEventInterface
{

    /**
     * @param T $value
     */
    public function __construct(
        public int|string $value,
        public int $newSize,
    )
    {
    }

}
```

- [ ] **Step 2: Remove redundant `readonly` from ItemRemovedEvent**

`src/Event/ItemRemovedEvent.php`:

```php
<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Event;

/**
 * @template T of int|string
 */
final readonly class ItemRemovedEvent implements ListEventInterface
{

    /**
     * @param T $value
     */
    public function __construct(
        public int|string $value,
        public int $newSize,
    )
    {
    }

}
```

- [ ] **Step 3: Verify tests pass and PHPStan is clean**

```bash
docker compose run --rm php vendor/bin/phpunit
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse
```

Expected: 79 tests pass, PHPStan OK.

- [ ] **Step 4: Commit**

```bash
git add src/Event/ItemInsertedEvent.php src/Event/ItemRemovedEvent.php
git commit -m "style: remove redundant readonly from event promoted properties"
```

---

## Task 2: Remove `composer.lock` from VCS

**Files:**
- Modify: `.gitignore`

Library best practice: do not commit `composer.lock`. Consumers use their own lock. Only applications commit it.

- [ ] **Step 1: Untrack composer.lock and add to .gitignore**

```bash
git rm --cached composer.lock
```

Add `composer.lock` to `.gitignore`:

```
vendor/
coverage/
.phpunit.cache/
infection.log
infection-summary.log
phpstan-first-run.log
composer.lock
```

- [ ] **Step 2: Commit**

```bash
git add .gitignore
git commit -m "chore: remove composer.lock from VCS — library best practice"
```

---

## Task 3: Named Constructors (`ofIntegers`, `ofStrings`) + `from()`

**Files:**
- Modify: `src/SortedLinkedList.php` (add three static methods)
- Modify: `tests/Unit/SortedLinkedListTest.php` (replace helpers, add tests)
- Modify: `tests/Unit/Bridge/Symfony/SortedLinkedListFactoryTest.php`

Named constructors solve two problems at once: discoverable API and PHPStan's callable-contravariance issue that forced the `naturalComparator()` workaround.

- [ ] **Step 1: Write failing tests for named constructors**

Add to `tests/Unit/SortedLinkedListTest.php` (before the closing `}`):

```php
    public function testOfIntegersCreatesEmptyList(): void
    {
        self::assertTrue(SortedLinkedList::ofIntegers()->isEmpty());
    }

    public function testOfIntegersWithValuesSortsOnCreation(): void
    {
        $list = SortedLinkedList::ofIntegers([3, 1, 2]);
        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testOfIntegersAcceptsCustomComparator(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 3, 2], static fn (int $a, int $b): int => $b <=> $a);
        self::assertSame([3, 2, 1], $list->toArray());
    }

    public function testOfStringsCreatesEmptyList(): void
    {
        self::assertTrue(SortedLinkedList::ofStrings()->isEmpty());
    }

    public function testOfStringsWithValuesSortsOnCreation(): void
    {
        $list = SortedLinkedList::ofStrings(['banana', 'apple', 'cherry']);
        self::assertSame(['apple', 'banana', 'cherry'], $list->toArray());
    }

    public function testOfStringsAcceptsCustomComparator(): void
    {
        $list = SortedLinkedList::ofStrings(['a', 'c', 'b'], static fn (string $a, string $b): int => $b <=> $a);
        self::assertSame(['c', 'b', 'a'], $list->toArray());
    }

    public function testOfIntegersEnforcesTypeLockOnSubsequentAdd(): void
    {
        $list = SortedLinkedList::ofIntegers([1]);
        $this->expectException(MixedTypeException::class);
        $list->add('oops'); // @phpstan-ignore argument.type
    }
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testOfIntegers
```

Expected: FAIL — method not found.

- [ ] **Step 3: Implement named constructors in SortedLinkedList**

Add these three static methods after the `__construct` block in `src/SortedLinkedList.php` (add `use function strcmp;` to the imports):

```php
    /**
     * @param list<int> $values
     * @param callable(int, int): int|null $comparator
     * @return self<int>
     */
    public static function ofIntegers(array $values = [], ?callable $comparator = null): self
    {
        /** @var self<int> $list */
        $list = new self($comparator ?? static fn (int $a, int $b): int => $a <=> $b);

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
    public static function ofStrings(array $values = [], ?callable $comparator = null): self
    {
        /** @var self<string> $list */
        $list = new self($comparator ?? static fn (string $a, string $b): int => strcmp($a, $b));

        foreach ($values as $value) {
            $list->add($value);
        }

        return $list;
    }
```

Also add `use function strcmp;` to the imports at the top of `src/SortedLinkedList.php`.

- [ ] **Step 4: Replace test helpers and fix event/logging tests**

In `tests/Unit/SortedLinkedListTest.php`:

**Replace** the `intList()` and `stringList()` helpers:
```php
    /** @return SortedLinkedList<int> */
    private function intList(): SortedLinkedList
    {
        return SortedLinkedList::ofIntegers();
    }

    /** @return SortedLinkedList<string> */
    private function stringList(): SortedLinkedList
    {
        return SortedLinkedList::ofStrings();
    }
```

**Replace** the `naturalComparator()` helper and all its usages with `SortedLinkedList::ofIntegers()`. The event and logging tests need a list with a listener or logger injected — use the constructor directly with a properly typed comparator. Replace every occurrence like:

```php
// OLD:
$list = new SortedLinkedList($this->naturalComparator(), $listener);

// NEW — use ofIntegers default comparator via constructor with typed callable:
$list = new SortedLinkedList(static fn (int $a, int $b): int => $a <=> $b, $listener);
```

Wait — this re-introduces the contravariance PHPStan issue. The named constructors fix it for the factory pattern but the constructor still has `callable(T, T): int`. For event/logger tests that need to inject a listener/logger alongside the list, the cleanest fix is to still use the `naturalComparator()` helper OR add `ofIntegers` variants that accept a listener/logger.

**Keep `naturalComparator()` for tests that inject listener/logger.** Remove `naturalComparator()` only when tests don't need a listener/logger. Update `intList()` and `stringList()` to use the named constructors:

```php
    /** @return SortedLinkedList<int> */
    private function intList(): SortedLinkedList
    {
        return SortedLinkedList::ofIntegers();
    }

    /** @return SortedLinkedList<string> */
    private function stringList(): SortedLinkedList
    {
        return SortedLinkedList::ofStrings();
    }

    /** @return callable(int|string, int|string): int */
    private function naturalComparator(): callable
    {
        return static function (int|string $a, int|string $b): int {
            if (is_int($a) && is_int($b)) {
                return $a <=> $b;
            }

            return (string) $a <=> (string) $b;
        };
    }
```

- [ ] **Step 5: Run full suite**

```bash
docker compose run --rm php vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 6: Run PHPStan and CS**

```bash
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse
docker compose run --rm php vendor/bin/phpcs
```

Expected: no errors. If PHPStan complains about the `foreach ($values as $value)` + `$list->add($value)` in `ofIntegers`, add a `/** @var list<int> $values */` assertion or cast.

- [ ] **Step 7: Commit**

```bash
git add src/SortedLinkedList.php tests/Unit/SortedLinkedListTest.php
git commit -m "feat: add ofIntegers() and ofStrings() named constructors with optional initial values"
```

---

## Task 4: Early Exit in `contains()` and `remove()` Traversal

**Files:**
- Modify: `src/SortedLinkedList.php`
- Modify: `tests/Unit/SortedLinkedListTest.php` (add early-exit specific tests)

The sorted invariant guarantees that when `comparator(currentNode, target) > 0`, the target cannot appear at or after the current node. Both `contains()` and `remove()`'s non-head traversal can exploit this for early termination.

- [ ] **Step 1: Write failing tests that prove early-exit correctness**

Add to `tests/Unit/SortedLinkedListTest.php`:

```php
    public function testContainsReturnsFalseEarlyWhenPastSortedPosition(): void
    {
        // Value 99 is not in the list; without early exit, the full list would be scanned.
        // With early exit, we stop at 100. Both return false — correctness is the observable check.
        $list = SortedLinkedList::ofIntegers([50, 100, 150]);
        self::assertFalse($list->contains(99));
        self::assertFalse($list->contains(101));
        self::assertFalse($list->contains(0));
    }

    public function testRemoveReturnsFalseEarlyWhenPastSortedPosition(): void
    {
        $list = SortedLinkedList::ofIntegers([10, 20, 30]);
        self::assertFalse($list->remove(15)); // between 10 and 20, not present
        self::assertSame([10, 20, 30], $list->toArray());
        self::assertFalse($list->remove(99)); // past end
        self::assertSame([10, 20, 30], $list->toArray());
    }

    public function testContainsWithDescendingComparatorEarlyExit(): void
    {
        $list = SortedLinkedList::ofIntegers([100, 50, 10], static fn (int $a, int $b): int => $b <=> $a);
        self::assertFalse($list->contains(75)); // between 100 and 50 in desc order
        self::assertTrue($list->contains(50));
    }
```

- [ ] **Step 2: Run to verify tests currently pass (they test correctness, not performance)**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testContainsReturnsFalseEarly
```

Expected: These pass even without the optimization (they just test correctness). That's fine — the tests document the contract and will catch any regression.

- [ ] **Step 3: Implement early exit in `contains()`**

Replace `contains()` in `src/SortedLinkedList.php`:

```php
    /**
     * @param T $value
     */
    public function contains(int|string $value): bool
    {
        $current = $this->head;

        while ($current !== null) {
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
```

- [ ] **Step 4: Implement early exit in `remove()` traversal (non-head path)**

Replace the traversal while-loop block in `remove()`:

```php
        $current = $this->head;

        while ($current->getNext() !== null) {
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

        if ($current->getNext() === null) {
            $this->logger->debug('Item not found in SortedLinkedList', ['value' => $value, 'removed' => false, 'size' => $this->size]);

            return false;
        }
```

- [ ] **Step 5: Run full suite**

```bash
docker compose run --rm php vendor/bin/phpunit
```

Expected: all tests pass. If any logger exact-context tests fail, check whether the early-exit path changed which debug call is at which index.

- [ ] **Step 6: Run PHPStan and CS**

```bash
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse
docker compose run --rm php vendor/bin/phpcs
```

Fix any violations reported.

- [ ] **Step 7: Commit**

```bash
git add src/SortedLinkedList.php tests/Unit/SortedLinkedListTest.php
git commit -m "perf: early exit in contains() and remove() when traversal passes sorted position"
```

---

## Task 5: `removeAll()` Method

**Files:**
- Modify: `src/SortedLinkedList.php`
- Modify: `tests/Unit/SortedLinkedListTest.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Unit/SortedLinkedListTest.php`:

```php
    public function testRemoveAllReturnsZeroWhenValueAbsent(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertSame(0, $list->removeAll(99));
        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testRemoveAllReturnCountOfRemovedOccurrences(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 2, 2, 3]);
        self::assertSame(3, $list->removeAll(2));
        self::assertSame([1, 3], $list->toArray());
    }

    public function testRemoveAllOnSingleOccurrenceReturnsOne(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertSame(1, $list->removeAll(2));
        self::assertSame([1, 3], $list->toArray());
    }

    public function testRemoveAllOnEmptyListReturnsZero(): void
    {
        self::assertSame(0, SortedLinkedList::ofIntegers()->removeAll(1));
    }

    public function testRemoveAllWorksWithStrings(): void
    {
        $list = SortedLinkedList::ofStrings(['a', 'b', 'b', 'c']);
        self::assertSame(2, $list->removeAll('b'));
        self::assertSame(['a', 'c'], $list->toArray());
    }

    public function testRemoveAllDispatchesEventPerRemoval(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList($this->naturalComparator(), $listener);
        $list->add(5);
        $list->add(5);
        $list->add(5);
        $list->removeAll(5);
        self::assertCount(3, $listener->removals);
    }
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testRemoveAll
```

Expected: FAIL — method not found.

- [ ] **Step 3: Implement `removeAll()` in `src/SortedLinkedList.php`**

Add after `remove()`:

```php
    /**
     * Removes all occurrences of the given value and returns the count removed.
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
```

- [ ] **Step 4: Run full suite**

```bash
docker compose run --rm php vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 5: PHPStan and CS**

```bash
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse
docker compose run --rm php vendor/bin/phpcs
```

Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/SortedLinkedList.php tests/Unit/SortedLinkedListTest.php
git commit -m "feat: add removeAll() — removes all occurrences, returns count"
```

---

## Task 6: Configure `coverage-guard` and Wire into CI

**Files:**
- Modify: `coverage-guard.php`
- Modify: `Makefile`

- [ ] **Step 1: Configure coverage-guard.php**

Replace `coverage-guard.php` with:

```php
<?php declare(strict_types = 1);

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;

$config = new Config();
$config->addRule(
    (new EnforceCoverageForMethodsRule(
        requiredCoveragePercentage: 100,
        minExecutableLines: 1,
    ))->excludeClasses([
        // Bundle loadExtension requires a live DI container — integration tested externally
        \ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListBundle::class,
    ]),
);

return $config;
```

- [ ] **Step 2: Wire into Makefile**

Update the `Makefile` `test` target to also produce Clover XML, and add a `coverage-guard` target wired into `ci`:

```makefile
.PHONY: install test stan cs cs-fix mutate deps coverage-guard ci

DC = docker compose run --rm php

install:
	docker compose build
	$(DC) composer install

test:
	$(DC) vendor/bin/phpunit --coverage-clover=coverage/clover.xml --coverage-xml=coverage/xml --log-junit=coverage/junit.xml

stan:
	$(DC) php -d memory_limit=512M vendor/bin/phpstan analyse

cs:
	$(DC) vendor/bin/phpcs

cs-fix:
	$(DC) vendor/bin/phpcbf; true

mutate:
	$(DC) vendor/bin/infection --threads=4 --coverage=coverage

coverage-guard:
	$(DC) vendor/bin/coverage-guard check coverage/clover.xml --config coverage-guard.php

deps:
	$(DC) vendor/bin/composer-dependency-analyser --config composer-dependency-analyser.php

ci: stan cs test coverage-guard mutate deps
```

- [ ] **Step 3: Verify coverage-guard passes**

```bash
docker compose run --rm php vendor/bin/phpunit --coverage-clover=coverage/clover.xml
docker compose run --rm php vendor/bin/coverage-guard check coverage/clover.xml --config coverage-guard.php
```

Expected output: no failures. If the bundle `loadExtension` appears, it is now excluded. If other methods fail, investigate whether they need tests.

- [ ] **Step 4: Commit**

```bash
git add coverage-guard.php Makefile
git commit -m "ci: configure coverage-guard at 100% per-method, wire into make ci"
```

---

## Task 7: GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Create the workflow directory**

```bash
mkdir -p .github/workflows
```

- [ ] **Step 2: Create `.github/workflows/ci.yml`**

```yaml
name: CI

on:
  push:
    branches: ["**"]
  pull_request:
    branches: ["**"]

jobs:
  ci:
    name: PHP ${{ matrix.php }}
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: ["8.3", "8.4"]

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: pcov
          coverage: pcov

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: PHPStan
        run: php -d memory_limit=512M vendor/bin/phpstan analyse

      - name: Code Style
        run: vendor/bin/phpcs

      - name: Tests + Coverage
        run: >
          vendor/bin/phpunit
          --coverage-clover=coverage/clover.xml
          --coverage-xml=coverage/xml
          --log-junit=coverage/junit.xml

      - name: Coverage Guard
        run: vendor/bin/coverage-guard check coverage/clover.xml --config coverage-guard.php

      - name: Mutation Tests
        run: vendor/bin/infection --threads=4 --coverage=coverage

      - name: Dependency Analysis
        run: vendor/bin/composer-dependency-analyser --config composer-dependency-analyser.php
```

Note: This runs **without Docker** on GitHub's runners, which is standard for PHP CI. The Dockerfile stays for local development. PCOV is installed via `shivammathur/setup-php`.

- [ ] **Step 3: Commit**

```bash
git add .github/
git commit -m "ci: add GitHub Actions workflow — PHP 8.3 + 8.4 matrix"
```

---

## Task 8: README.md

**Files:**
- Create: `README.md`

- [ ] **Step 1: Create README.md**

```markdown
# SortedLinkedList

A PHP 8.3 library providing a generic `SortedLinkedList<T of int|string>` — a singly-linked list that keeps values in caller-defined sort order. Designed for ShipMonk's engineering standards: PHPStan level 10, full mutation testing, and strict code style.

## Installation

```bash
composer require amadeusz-opac/sorted-linked-list
```

## Requirements

- PHP 8.3+
- `psr/log` ^3.0 (optional — only if you want debug logging)

## Usage

### Named constructors (recommended)

```php
use ShipMonk\SortedLinkedList\SortedLinkedList;

// Integer list — natural ascending order
$list = SortedLinkedList::ofIntegers();
$list->add(3);
$list->add(1);
$list->add(2);
$list->toArray(); // [1, 2, 3]

// Integer list pre-filled
$list = SortedLinkedList::ofIntegers([3, 1, 2]);
$list->toArray(); // [1, 2, 3]

// String list — lexicographic order
$list = SortedLinkedList::ofStrings(['banana', 'apple', 'cherry']);
$list->toArray(); // ['apple', 'banana', 'cherry']

// Custom comparator (descending)
$list = SortedLinkedList::ofIntegers([], static fn(int $a, int $b): int => $b <=> $a);
$list->add(1); $list->add(3); $list->add(2);
$list->toArray(); // [3, 2, 1]
```

### Direct constructor

```php
// Requires callable(T, T): int — T inferred from the comparator
$list = new SortedLinkedList(static fn(int $a, int $b): int => $a <=> $b);
```

### Type lock

A list locks its value type on first insertion. Attempting to insert a mismatched type throws `MixedTypeException` at runtime — enforced both statically by PHPStan generics and dynamically.

```php
$list = SortedLinkedList::ofIntegers([1, 2]);
$list->add('oops'); // throws MixedTypeException
```

Call `$list->clear()` to reset the lock and reuse the list with a different type.

## API Reference

| Method | Description |
|---|---|
| `ofIntegers(array $values = [], ?callable $comparator = null): self<int>` | Named constructor for int lists |
| `ofStrings(array $values = [], ?callable $comparator = null): self<string>` | Named constructor for string lists |
| `add(T $value): void` | Insert a value, maintaining sorted order |
| `remove(T $value): bool` | Remove first occurrence; returns true if found |
| `removeAll(T $value): int` | Remove all occurrences; returns count removed |
| `contains(T $value): bool` | Returns true if value is present |
| `first(): T` | Smallest element; throws `EmptyListException` if empty |
| `last(): T` | Largest element; throws `EmptyListException` if empty |
| `count(): int` | Number of elements (also works with `count($list)`) |
| `isEmpty(): bool` | Returns true if list has no elements |
| `clear(): void` | Remove all elements and reset type lock |
| `toArray(): list<T>` | Return all elements as a sorted array |
| `filter(callable(T): bool $predicate): static<T>` | Return new list with matching values |
| `merge(self<T> $other): static<T>` | Return new re-sorted union of both lists |
| `getIterator(): ArrayIterator<int, T>` | Implements `IteratorAggregate` for `foreach` |

## Events

Inject a `ListEventListenerInterface<T>` to react to mutations:

```php
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;
use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;

class MyListener implements ListEventListenerInterface
{
    public function onInserted(ItemInsertedEvent $event): void
    {
        echo "Inserted {$event->value}, size now {$event->newSize}";
    }

    public function onRemoved(ItemRemovedEvent $event): void { ... }
    public function onCleared(ListClearedEvent $event): void { ... }
}

$list = new SortedLinkedList(
    static fn(int $a, int $b): int => $a <=> $b,
    new MyListener(),
);
```

## Logging

Inject a PSR-3 `LoggerInterface` for debug-level operation logs:

```php
use Monolog\Logger;

$list = new SortedLinkedList(
    static fn(int $a, int $b): int => $a <=> $b,
    null,
    new Logger('list'),
);
```

Each `add`, `remove`, and `clear` writes a `DEBUG` entry. Type mismatches write a `WARNING` before throwing.

## Symfony Integration

Register the bundle in `config/bundles.php`:

```php
return [
    ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListBundle::class => ['all' => true],
];
```

Then inject `SortedLinkedListFactory` via autowiring:

```php
use ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListFactory;

class MyService
{
    public function __construct(private readonly SortedLinkedListFactory $factory) {}

    public function run(): void
    {
        $list = $this->factory->createIntList(static fn(int $a, int $b): int => $a <=> $b);
        $list->add(42);
    }
}
```

Any service implementing `ListEventListenerInterface` is automatically tagged as `sorted_linked_list.event_listener`.

## Development

```bash
make install        # build Docker + install deps
make test           # PHPUnit with coverage
make stan           # PHPStan level 10
make cs             # Code style check
make cs-fix         # Code style auto-fix
make mutate         # Infection mutation tests
make coverage-guard # Per-method coverage enforcement
make deps           # Dependency graph analysis
make ci             # Full quality gate
```
```

- [ ] **Step 2: Run final full CI gate**

```bash
make ci
```

Expected: all steps pass.

- [ ] **Step 3: Run mutation tests to ensure they still pass after all changes**

```bash
make mutate
```

Expected: MSI ≥ 95%.

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "docs: add README with installation, API reference, events, Symfony integration"
```

---

## Done

`make ci` should complete cleanly. Final git log should show 8 improvement commits on top of the original implementation.
