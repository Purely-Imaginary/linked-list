# Final Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement ten targeted improvements that elevate the library from "excellent" to "exceptional" as a senior PHP developer portfolio piece: serialization safety, O(1) `last()`, `Stringable`, `unique()`, architecture tests, pre-commit hooks, benchmarks, and documentation completeness.

**Architecture:** All changes are additive or optimisations — no public API removed. The tail pointer is the most structurally significant change: a new private `?Node $tail` property maintained across `add()`, `remove()`, `clear()`, and `__clone()`. Everything else is new methods, new tooling, or documentation.

**Tech Stack:** PHP 8.3, PHPUnit 11, PHPBench 1.6, phparkitect 1.0, captainhook/captainhook 5.29, PHPStan 2 (level 10), Psalm, Rector, shipmonk/coding-standard.

---

## File Map

| File | Change |
|---|---|
| `src/SortedLinkedList.php` | Add `$tail` property; update `add/remove/clear/__clone`; add `__serialize/__unserialize`, `__toString`, `unique()` |
| `src/Exception/NotSerializableException.php` | NEW — dedicated exception for serialization attempts |
| `tests/Unit/SortedLinkedListTest.php` | Add tests for serialization, O(1) last, toString, unique |
| `phparkitect.php` | NEW — architecture rule set |
| `captainhook.json` | NEW — pre-commit/pre-push hook config |
| `bench/SortedLinkedListBench.php` | NEW — PHPBench benchmark suite |
| `phpbench.json` | NEW — PHPBench config |
| `composer.json` | Add phparkitect, captainhook, phpbench to require-dev; add suggest block |
| `Makefile` | Add `hooks`, `arch`, `bench` targets; update `help` and `ci` |
| `.github/workflows/ci.yml` | Add `phparkitect` step |
| `coverage-guard.php` | Exclude `NotSerializableException` (private guard) |
| `doc/SortedLinkedList.md` | Add explanations for tail pointer, serialization, Stringable, unique |
| `doc/InterviewGuide.md` | Add Q&A on O(1) last, serialization |
| `doc/ArchitectureOverview.md` | Update complexity table, add new fields |
| `doc/QualityToolchain.md` | Add phparkitect, CaptainHook, PHPBench sections |
| `README.md` | Add `unique()`, `__toString`, `limit/slice`, and toolchain entries |

---

## Task 1: Serialization Safety — `__serialize()` / `__unserialize()`

**Files:**
- Modify: `src/SortedLinkedList.php`
- Modify: `tests/Unit/SortedLinkedListTest.php`

Currently `serialize($list)` crashes with `Exception: Serialization of 'Closure' is not allowed` — a cryptic PHP-internal error with no library context. This must throw a descriptive `\LogicException` instead.

- [ ] **Step 1: Write failing tests**

Add to `tests/Unit/SortedLinkedListTest.php` (before the closing `}`):

```php
    public function testSerializeThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot be serialized');
        serialize(SortedLinkedList::ofIntegers([1, 2, 3]));
    }

    public function testUnserializeThrowsLogicException(): void
    {
        $list = SortedLinkedList::ofIntegers([1]);
        $this->expectException(\LogicException::class);
        $list->__unserialize([]);
    }
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testSerialize 2>&1 | tail -8
```

Expected: FAIL — serialize currently crashes with `Exception: Serialization of 'Closure' is not allowed`.

- [ ] **Step 3: Implement `__serialize()` and `__unserialize()`**

Add to `src/SortedLinkedList.php` (after `__clone()` and before `add()`):

```php
    /**
     * @throws \LogicException always — SortedLinkedList cannot be serialized because the comparator is a Closure.
     *                         Use toArray() for a serializable snapshot of the values.
     * @return never
     */
    public function __serialize(): array
    {
        throw new \LogicException(
            'SortedLinkedList cannot be serialized: the comparator is a Closure, which PHP cannot serialize. '
            . 'Call toArray() to obtain a serializable list<T> snapshot.',
        );
    }

    /**
     * @throws \LogicException always — re-create the list using the constructor or named constructors.
     * @param array<mixed> $data
     * @return never
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException(
            'SortedLinkedList cannot be unserialized. Re-create the list with ofIntegers() or ofStrings().',
        );
    }
```

Also add `use function sprintf;` is already in the file — no new imports needed.

- [ ] **Step 4: Run tests and fix CS**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testSerialize 2>&1 | tail -5
docker compose run --rm php vendor/bin/phpcs 2>&1 | tail -3
docker compose run --rm php vendor/bin/phpcbf; true
```

Expected: 2 tests PASS.

- [ ] **Step 5: Run PHPStan**

```bash
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse 2>&1 | tail -3
```

Expected: `[OK] No errors` — `@return never` satisfies PHPStan's return-type analysis for a method that always throws.

- [ ] **Step 6: Commit**

```bash
git add src/SortedLinkedList.php tests/Unit/SortedLinkedListTest.php
git commit -m "feat: add __serialize/__unserialize — replace cryptic PHP crash with descriptive LogicException"
```

---

## Task 2: Tail Pointer for O(1) `last()`

**Files:**
- Modify: `src/SortedLinkedList.php` (property + `add`, `remove`, `clear`, `__clone`, `last`)
- Modify: `tests/Unit/SortedLinkedListTest.php`

`last()` currently traverses the entire chain — O(n). A `$tail` pointer maintained by mutations gives O(1).

- [ ] **Step 1: Add tests that verify O(1) behaviour (via clone + repeated last)**

Add to `tests/Unit/SortedLinkedListTest.php`:

```php
    public function testLastIsO1AfterManyInsertions(): void
    {
        $list = SortedLinkedList::ofIntegers();
        for ($i = 1; $i <= 1000; $i++) {
            $list->add($i);
        }
        // If last() is O(n) this would be 1000×O(n) = slow; with O(1) tail it is trivially fast.
        // We test correctness — performance is verified by the benchmark.
        self::assertSame(1000, $list->last());
    }

    public function testLastAfterRemovingTailElement(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        $list->remove(3);
        self::assertSame(2, $list->last());
    }

    public function testLastAfterClear(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        $list->clear();
        $this->expectException(\ShipMonk\SortedLinkedList\Exception\EmptyListException::class);
        $list->last();
    }

    public function testLastAfterCloneAndMutateOriginal(): void
    {
        $original = SortedLinkedList::ofIntegers([1, 2, 3]);
        $clone = clone $original;
        $original->remove(3);
        self::assertSame(3, $clone->last()); // clone's tail must be independent
        self::assertSame(2, $original->last());
    }
```

- [ ] **Step 2: Run to verify tests pass (last() is already correct, just slow)**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testLast 2>&1 | tail -5
```

Expected: all PASS (correctness already holds — we are adding the tail optimisation, not fixing a bug).

- [ ] **Step 3: Add the `$tail` property and update ALL mutation methods**

Replace the property block and affected methods in `src/SortedLinkedList.php`. The full updated file sections are shown below.

**Add `$tail` after `$head`:**
```php
    /**
     * @var Node<T>|null
     */
    private ?Node $head = null;

    /**
     * @var Node<T>|null
     */
    private ?Node $tail = null;
```

**Update `add()` — insert just before `$this->size++`:**

Replace the existing `add()` body's insertion block:
```php
        $newNode = new Node($value);

        if (!$this->head instanceof \ShipMonk\SortedLinkedList\Node || ($this->comparator)($value, $this->head->getValue()) <= 0) {
            $newNode->setNext($this->head);
            $this->head = $newNode;
        } else {
            $current = $this->head;

            while (
                $current->getNext() instanceof \ShipMonk\SortedLinkedList\Node
                && ($this->comparator)($value, $current->getNext()->getValue()) > 0
            ) {
                $current = $current->getNext();
            }

            $newNode->setNext($current->getNext());
            $current->setNext($newNode);
        }

        // Update tail: if newNode has no successor it is the new last node.
        if (!$newNode->getNext() instanceof \ShipMonk\SortedLinkedList\Node) {
            $this->tail = $newNode;
        }

        $this->size++;
```

**Update `remove()` — head-removal case:**
```php
        if ($this->head->getValue() === $value) {
            $this->head = $this->head->getNext();
            // If the list is now empty, tail must also be cleared.
            if (!$this->head instanceof \ShipMonk\SortedLinkedList\Node) {
                $this->tail = null;
            }
            assert($this->size > 0);
            $this->size--;
```

**Update `remove()` — traversal-removal case (the block after the while loop):**
```php
        if (!$current->getNext() instanceof \ShipMonk\SortedLinkedList\Node) {
            $this->logger->debug('Item not found in SortedLinkedList', ['value' => $value, 'removed' => false, 'size' => $this->size]);

            return false;
        }

        // If the node being removed is the tail, current becomes the new tail.
        if (!$current->getNext()->getNext() instanceof \ShipMonk\SortedLinkedList\Node) {
            $this->tail = $current;
        }

        $current->setNext($current->getNext()->getNext());
        assert($this->size > 0);
        $this->size--;
```

**Update `clear()`:**
```php
    public function clear(): void
    {
        $previousSize = $this->size;
        $this->head = null;
        $this->tail = null;
        $this->size = 0;
        $this->lockedType = null;
        $this->logger->debug('SortedLinkedList cleared', ['previousSize' => $previousSize]);
        $this->eventListener?->onCleared(new ListClearedEvent($previousSize));
    }
```

**Update `__clone()` — add tail tracking at the end:**
```php
    public function __clone()
    {
        if (!$this->head instanceof \ShipMonk\SortedLinkedList\Node) {
            return;
        }

        $newHead = new Node($this->head->getValue());
        $newCurrent = $newHead;
        $oldCurrent = $this->head->getNext();

        while ($oldCurrent instanceof \ShipMonk\SortedLinkedList\Node) {
            $newNode = new Node($oldCurrent->getValue());
            $newCurrent->setNext($newNode);
            $newCurrent = $newNode;
            $oldCurrent = $oldCurrent->getNext();
        }

        $this->head = $newHead;
        $this->tail = $newCurrent; // $newCurrent is the last node created
    }
```

**Update `last()` — replace O(n) traversal with O(1) tail access:**
```php
    /**
     * @return T
     *
     * @throws EmptyListException
     */
    public function last(): int|string
    {
        if (!$this->tail instanceof \ShipMonk\SortedLinkedList\Node) {
            throw new EmptyListException('Cannot get last element of an empty list.');
        }

        return $this->tail->getValue();
    }
```

- [ ] **Step 4: Run full test suite**

```bash
docker compose run --rm php vendor/bin/phpunit 2>&1 | tail -5
```

Expected: all tests PASS.

- [ ] **Step 5: PHPStan + CS**

```bash
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse 2>&1 | tail -3
docker compose run --rm php vendor/bin/phpcs 2>&1 | tail -3
docker compose run --rm php vendor/bin/phpcbf; true
docker compose run --rm php vendor/bin/phpunit 2>&1 | grep "^OK"
```

Expected: PHPStan OK, CS clean, tests green.

- [ ] **Step 6: Commit**

```bash
git add src/SortedLinkedList.php tests/Unit/SortedLinkedListTest.php
git commit -m "perf: add tail pointer — last() is now O(1) instead of O(n)"
```

---

## Task 3: `Stringable` + `__toString()`

**Files:**
- Modify: `src/SortedLinkedList.php`
- Modify: `tests/Unit/SortedLinkedListTest.php`

- [ ] **Step 1: Write failing tests**

```php
    public function testToStringOnEmptyList(): void
    {
        self::assertSame('SortedLinkedList<empty>[]', (string) SortedLinkedList::ofIntegers());
    }

    public function testToStringOnIntList(): void
    {
        $list = SortedLinkedList::ofIntegers([3, 1, 2]);
        self::assertSame('SortedLinkedList<int>[1, 2, 3]', (string) $list);
    }

    public function testToStringOnStringList(): void
    {
        $list = SortedLinkedList::ofStrings(['cherry', 'apple']);
        self::assertSame('SortedLinkedList<string>[apple, cherry]', (string) $list);
    }

    public function testToStringImplementsStringable(): void
    {
        $list = SortedLinkedList::ofIntegers([1]);
        self::assertInstanceOf(\Stringable::class, $list);
    }
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testToString 2>&1 | tail -5
```

Expected: FAIL — `SortedLinkedList` does not implement `Stringable`.

- [ ] **Step 3: Implement `__toString()` and add `Stringable` to implements**

In `src/SortedLinkedList.php`:

Change the class declaration:
```php
final class SortedLinkedList implements Countable, IteratorAggregate, JsonSerializable, \Stringable
```

Add `use function implode;` and `use function array_map;` to imports if not already present.

Add method (after `jsonSerialize()`):
```php
    /**
     * Returns a human-readable representation for debugging.
     * Format: SortedLinkedList<type>[val1, val2, ...]
     */
    #[Override]
    public function __toString(): string
    {
        $type = $this->lockedType ?? 'empty';
        $values = implode(', ', array_map(static fn (int|string $v): string => (string) $v, $this->toArray()));

        return sprintf('SortedLinkedList<%s>[%s]', $type, $values);
    }
```

- [ ] **Step 4: Run tests + PHPStan + CS**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testToString 2>&1 | tail -5
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse 2>&1 | tail -3
docker compose run --rm php vendor/bin/phpcbf; true
```

Expected: 4 tests PASS, PHPStan OK.

- [ ] **Step 5: Commit**

```bash
git add src/SortedLinkedList.php tests/Unit/SortedLinkedListTest.php
git commit -m "feat: implement Stringable/__toString() — SortedLinkedList<type>[values]"
```

---

## Task 4: `unique()` Method

**Files:**
- Modify: `src/SortedLinkedList.php`
- Modify: `tests/Unit/SortedLinkedListTest.php`

- [ ] **Step 1: Write failing tests**

```php
    public function testUniqueRemovesAdjacentDuplicates(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 2, 2, 3]);
        self::assertSame([1, 2, 3], $list->unique()->toArray());
    }

    public function testUniqueOnListWithNoDuplicates(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 2, 3]);
        self::assertSame([1, 2, 3], $list->unique()->toArray());
    }

    public function testUniqueOnEmptyList(): void
    {
        self::assertTrue(SortedLinkedList::ofIntegers()->unique()->isEmpty());
    }

    public function testUniqueDoesNotMutateOriginal(): void
    {
        $list = SortedLinkedList::ofIntegers([1, 1, 2]);
        $list->unique();
        self::assertSame([1, 1, 2], $list->toArray());
    }

    public function testUniqueOnStrings(): void
    {
        $list = SortedLinkedList::ofStrings(['a', 'a', 'b', 'c', 'c']);
        self::assertSame(['a', 'b', 'c'], $list->unique()->toArray());
    }

    public function testUniqueWithCustomComparatorUsesComparatorEquality(): void
    {
        // Case-insensitive: 'Apple' and 'apple' are comparator-equal → one survives
        $list = SortedLinkedList::ofStrings(
            ['apple', 'Apple', 'Banana'],
            \ShipMonk\SortedLinkedList\Comparator::stringsIgnoreCase(),
        );
        self::assertSame(2, $list->unique()->count());
    }
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testUnique 2>&1 | tail -5
```

Expected: FAIL — method not found.

- [ ] **Step 3: Implement `unique()`**

Add after `filter()` in `src/SortedLinkedList.php`:

```php
    /**
     * Returns a new list with consecutive comparator-equal values deduplicated.
     * Because the list is always sorted, equal values are always adjacent,
     * so a single pass is sufficient.
     *
     * @return static<T>
     */
    public function unique(): static
    {
        /** @var static<T> $new */
        $new = new self($this->comparator);
        /** @var T|null $prev */
        $prev = null;
        $hasPrev = false;

        foreach ($this->toArray() as $value) {
            if (!$hasPrev || ($this->comparator)($value, $prev) !== 0) {
                $new->add($value);
                $prev = $value;
                $hasPrev = true;
            }
        }

        return $new;
    }
```

- [ ] **Step 4: Run tests + PHPStan + CS**

```bash
docker compose run --rm php vendor/bin/phpunit --filter testUnique 2>&1 | tail -5
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse 2>&1 | tail -3
docker compose run --rm php vendor/bin/phpcbf; true
docker compose run --rm php vendor/bin/phpunit 2>&1 | grep "^OK"
```

Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/SortedLinkedList.php tests/Unit/SortedLinkedListTest.php
git commit -m "feat: add unique() — deduplicates using comparator equality in O(n)"
```

---

## Task 5: Method Descriptions for `remove()`, `removeAll()`, `remove()` Never-Throws

**Files:**
- Modify: `src/SortedLinkedList.php`

The absence of `@throws` on `remove()` / `removeAll()` is ambiguous — reviewers may wonder whether these throw. Add explicit "never throws, returns false/0" descriptions.

- [ ] **Step 1: Update docblocks**

Find the `remove()` docblock (currently has only `@param T $value` and `@throws MixedTypeException` from a previous change — verify it's not there):

```bash
grep -n -A 5 "public function remove" src/SortedLinkedList.php | head -15
```

Replace the `remove()` docblock with:

```php
    /**
     * Removes the first occurrence of $value and returns true if found.
     * Returns false if the value is not in the list. Never throws.
     * Uses early exit when traversal passes the sorted position.
     *
     * @param T $value
     */
    public function remove(int|string $value): bool
```

Replace the `removeAll()` docblock description line:

```php
    /**
     * Removes all occurrences of $value and returns the count removed.
     * Returns 0 if the value is not present. Never throws.
     *
     * @param T $value
     * @return int<0, max>
     */
    public function removeAll(int|string $value): int
```

- [ ] **Step 2: Run CS and verify no new violations**

```bash
docker compose run --rm php vendor/bin/phpcs 2>&1 | tail -3
docker compose run --rm php vendor/bin/phpunit 2>&1 | grep "^OK"
```

Expected: clean.

- [ ] **Step 3: Commit**

```bash
git add src/SortedLinkedList.php
git commit -m "docs: document remove/removeAll never-throw contract explicitly"
```

---

## Task 6: `phparkitect` Architecture Tests

**Files:**
- Create: `phparkitect.php`
- Modify: `composer.json`
- Modify: `Makefile`
- Modify: `.github/workflows/ci.yml`

- [ ] **Step 1: Install phparkitect**

```bash
docker compose run --rm php composer require --dev phparkitect/phparkitect:^1.0
```

Expected: installs cleanly.

- [ ] **Step 2: Create `phparkitect.php`**

```php
<?php declare(strict_types = 1);

use Arkitect\ClassSet;
use Arkitect\CLI\Check;
use Arkitect\Expression\ForClasses\Extend;
use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\Implement;
use Arkitect\Expression\ForClasses\NotDependOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotHaveNameMatching;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Check $check): void {
    $src = ClassSet::fromDir(__DIR__ . '/src');

    $check->expects([
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('ShipMonk\SortedLinkedList\Event'))
            ->andThat(new NotHaveNameMatching('ListEventInterface'))
            ->should(new Implement('ShipMonk\SortedLinkedList\Event\ListEventInterface'))
            ->because('all event value objects must be tagged with ListEventInterface'),

        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('ShipMonk\SortedLinkedList\Exception'))
            ->andThat(new NotHaveNameMatching('SortedLinkedListException'))
            ->should(new Extend('ShipMonk\SortedLinkedList\Exception\SortedLinkedListException'))
            ->because('every library exception must extend the base SortedLinkedListException so callers can catch all errors with one catch clause'),

        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces(
                'ShipMonk\SortedLinkedList\Event',
                'ShipMonk\SortedLinkedList\EventListener',
                'ShipMonk\SortedLinkedList\Exception',
            ))
            ->should(new NotDependOnTheseNamespaces('ShipMonk\SortedLinkedList\Bridge'))
            ->because('the core library must not depend on the optional Symfony bridge'),
    ])->in($src);
};
```

- [ ] **Step 3: Run phparkitect to verify rules pass**

```bash
docker compose run --rm php vendor/bin/phparkitect check --config phparkitect.php 2>&1
```

Expected: all rules pass with no violations.

- [ ] **Step 4: Add Makefile target and CI step**

In `Makefile`, add `arch` target and update `ci`:

```makefile
arch:
	$(DC) vendor/bin/phparkitect check --config phparkitect.php
```

Update `ci` line:
```makefile
ci: stan psalm rector arch cs test coverage-guard mutate deps
```

Update `help`:
```makefile
	@echo "  arch           phparkitect architecture rule enforcement"
```

In `.github/workflows/ci.yml`, add after the Rector step:
```yaml
      - name: Architecture (phparkitect)
        run: vendor/bin/phparkitect check --config phparkitect.php
```

- [ ] **Step 5: Run CI gate**

```bash
docker compose run --rm php vendor/bin/phparkitect check --config phparkitect.php 2>&1 | tail -5
```

Expected: `[OK]` or similar success output.

- [ ] **Step 6: Commit**

```bash
git add phparkitect.php composer.json Makefile .github/workflows/ci.yml
git commit -m "ci: add phparkitect architecture tests — enforce core/bridge boundary and exception hierarchy"
```

---

## Task 7: CaptainHook Pre-Commit Hooks

**Files:**
- Create: `captainhook.json`
- Modify: `composer.json`
- Modify: `Makefile`
- Modify: `README.md`

- [ ] **Step 1: Install captainhook**

```bash
docker compose run --rm php composer require --dev captainhook/captainhook:^5.29
```

- [ ] **Step 2: Create `captainhook.json`**

```json
{
    "commit-msg": {
        "enabled": false,
        "actions": []
    },
    "pre-commit": {
        "enabled": true,
        "actions": [
            {
                "action": "vendor/bin/phpcs",
                "label": "Code style (shipmonk/coding-standard)"
            },
            {
                "action": "php -d memory_limit=512M vendor/bin/phpstan analyse",
                "label": "PHPStan level 10"
            }
        ]
    },
    "pre-push": {
        "enabled": true,
        "actions": [
            {
                "action": "vendor/bin/phpunit --no-coverage",
                "label": "PHPUnit (no coverage)"
            }
        ]
    }
}
```

- [ ] **Step 3: Add Makefile `hooks` target**

Add to Makefile:
```makefile
hooks:
	$(DC) vendor/bin/captainhook install --force --skip-existing
```

And update `help`:
```makefile
	@echo "  hooks          Install git pre-commit / pre-push hooks via CaptainHook"
```

- [ ] **Step 4: Verify CaptainHook installs without error**

```bash
docker compose run --rm php vendor/bin/captainhook install --force 2>&1 | tail -5
```

Expected: hooks installed in `.git/hooks/`.

- [ ] **Step 5: Add `.gitignore` entry for captainhook cache if needed**

```bash
echo ".captainhook/" >> .gitignore
```

- [ ] **Step 6: Commit**

```bash
git add captainhook.json composer.json Makefile .gitignore
git commit -m "ci: add CaptainHook pre-commit (CS+PHPStan) and pre-push (PHPUnit) git hooks"
```

---

## Task 8: PHPBench Benchmarks

**Files:**
- Create: `bench/SortedLinkedListBench.php`
- Create: `phpbench.json`
- Modify: `composer.json`
- Modify: `Makefile`

- [ ] **Step 1: Install PHPBench**

```bash
docker compose run --rm php composer require --dev phpbench/phpbench:^1.6
```

- [ ] **Step 2: Create `phpbench.json`**

```json
{
    "$schema": "vendor/phpbench/phpbench/phpbench.schema.json",
    "runner.bootstrap": "vendor/autoload.php",
    "runner.path": "bench"
}
```

- [ ] **Step 3: Create `bench/SortedLinkedListBench.php`**

```php
<?php declare(strict_types = 1);

use PhpBench\Attributes as Bench;
use ShipMonk\SortedLinkedList\Comparator;
use ShipMonk\SortedLinkedList\SortedLinkedList;

/**
 * Benchmarks for SortedLinkedList.
 * Run with: make bench
 *
 * Key claims verified:
 *  - last() is O(1) with the tail pointer
 *  - contains() exits early for absent values (better than O(n) average)
 *  - add() is O(n) — cost grows linearly with list size
 */
#[Bench\OutputTimeUnit('microseconds')]
class SortedLinkedListBench
{

    /** @var SortedLinkedList<int> */
    private SortedLinkedList $list10;

    /** @var SortedLinkedList<int> */
    private SortedLinkedList $list100;

    /** @var SortedLinkedList<int> */
    private SortedLinkedList $list1000;

    #[Bench\BeforeEach]
    public function setUp(): void
    {
        $this->list10 = SortedLinkedList::ofIntegers(range(1, 10), Comparator::integers());
        $this->list100 = SortedLinkedList::ofIntegers(range(1, 100), Comparator::integers());
        $this->list1000 = SortedLinkedList::ofIntegers(range(1, 1_000), Comparator::integers());
    }

    /**
     * last() must be O(1) regardless of list size.
     * All three variants should show the same flat cost.
     */
    #[Bench\Subject]
    #[Bench\Revs(10_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['last'])]
    public function benchLast10(): void
    {
        $this->list10->last();
    }

    #[Bench\Subject]
    #[Bench\Revs(10_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['last'])]
    public function benchLast100(): void
    {
        $this->list100->last();
    }

    #[Bench\Subject]
    #[Bench\Revs(10_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['last'])]
    public function benchLast1000(): void
    {
        $this->list1000->last();
    }

    /**
     * contains() for an absent value exits early due to sorting.
     * Value 9999 is larger than any element — worst case (full scan without early-exit).
     * Value 0 exits immediately at the first node — best case.
     */
    #[Bench\Subject]
    #[Bench\Revs(5_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['contains'])]
    public function benchContainsAbsentBestCase(): void
    {
        $this->list1000->contains(0); // exits at node 1
    }

    #[Bench\Subject]
    #[Bench\Revs(5_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['contains'])]
    public function benchContainsAbsentWorstCase(): void
    {
        $this->list1000->contains(9_999); // scans full list
    }

    #[Bench\Subject]
    #[Bench\Revs(5_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['contains'])]
    public function benchContainsPresent(): void
    {
        $this->list1000->contains(500);
    }

    /**
     * add() is O(n) — cost should scale roughly linearly with list size.
     */
    #[Bench\Subject]
    #[Bench\Revs(1_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['add'])]
    public function benchAddWorstCase10(): void
    {
        SortedLinkedList::ofIntegers(range(10, 1, -1), Comparator::integers());
    }

    #[Bench\Subject]
    #[Bench\Revs(100)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['add'])]
    public function benchAddWorstCase100(): void
    {
        SortedLinkedList::ofIntegers(range(100, 1, -1), Comparator::integers());
    }

    #[Bench\Subject]
    #[Bench\Revs(10)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['add'])]
    public function benchAddWorstCase1000(): void
    {
        SortedLinkedList::ofIntegers(range(1_000, 1, -1), Comparator::integers());
    }

}
```

- [ ] **Step 4: Add Makefile `bench` target**

```makefile
bench:
	$(DC) vendor/bin/phpbench run --report=aggregate
```

Update `help`:
```makefile
	@echo "  bench          PHPBench performance benchmarks"
```

- [ ] **Step 5: Run benchmarks to verify they work**

```bash
docker compose run --rm php vendor/bin/phpbench run --report=aggregate 2>&1 | tail -20
```

Expected: benchmark output showing µs timings. `last10/100/1000` should show flat/similar timings (O(1) proof).

- [ ] **Step 6: Commit**

```bash
git add bench/ phpbench.json composer.json Makefile
git commit -m "feat: add PHPBench benchmarks — verifies O(1) last() and O(n) add() empirically"
```

---

## Task 9: `composer.json` `suggest` Block

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add `suggest` block to `composer.json`**

Add after `"license"`:

```json
    "suggest": {
        "symfony/http-kernel": "^7.0 — required to use SortedLinkedListBundle",
        "symfony/dependency-injection": "^7.0 — required to use SortedLinkedListBundle",
        "symfony/config": "^7.0 — required to use SortedLinkedListBundle"
    },
```

- [ ] **Step 2: Verify composer validates**

```bash
docker compose run --rm php composer validate 2>&1 | tail -3
```

Expected: `./composer.json is valid`.

- [ ] **Step 3: Commit**

```bash
git add composer.json
git commit -m "chore: add composer suggest block for optional Symfony bridge dependencies"
```

---

## Task 10: Full Documentation Update

**Files:**
- Modify: `doc/SortedLinkedList.md`
- Modify: `doc/InterviewGuide.md`
- Modify: `doc/ArchitectureOverview.md`
- Modify: `doc/QualityToolchain.md`
- Modify: `README.md`

- [ ] **Step 1: Update `doc/ArchitectureOverview.md` complexity table**

Replace the `last()` row in the complexity table:

```markdown
| `last()` | O(1) — tail pointer |
```

Add `__toString()`, `unique()`, `__serialize()` to the table:

```markdown
| `__toString()` | O(n) — iterates to build string |
| `unique()` | O(n) — single pass with comparator check |
| `__serialize()` | O(1) — immediately throws |
```

Update the property list in "Key Data Flow" to include `$tail`.

- [ ] **Step 2: Add Q&A entries to `doc/InterviewGuide.md`**

Add after the existing Q&A sections:

```markdown
## 18. Why is `last()` O(1)?

**Question:** "Your ArchitectureOverview says `last()` is O(1). How?"

**Answer:**
The original `last()` traversed the entire node chain from `$head` — O(n). 

A `$tail` pointer is now maintained alongside `$head`. Every mutation that could change the last element updates it:
- `add()`: if the new node has no successor, it is the new tail.
- `remove()`: if the removed node is the tail, the previous node becomes the new tail.
- `clear()`: tail is set to null.
- `__clone()`: the last new node created in the chain copy becomes the clone's tail.

`last()` now simply reads `$this->tail->getValue()` — O(1).

The benchmark in `bench/SortedLinkedListBench.php` empirically verifies this: `benchLast10`, `benchLast100`, and `benchLast1000` all show the same flat cost regardless of list size.

## 19. Why does `serialize($list)` throw a `LogicException`?

**Question:** "What happens if you try to serialize a SortedLinkedList?"

**Answer:**
Before the fix, `serialize($list)` crashed with `Exception: Serialization of 'Closure' is not allowed` — a cryptic PHP-internal error with no library context. A caller with no knowledge of the internals would have no idea why serialization failed.

`__serialize()` now throws a `\LogicException` with an explicit message explaining that the comparator `Closure` is the cause and suggesting `toArray()` as an alternative.

This is the difference between a "surprise crash" and a "deliberate refusal with guidance". Both prevent the operation, but only one helps the caller fix their code.

## 20. Why does `unique()` use the comparator instead of `===`?

**Question:** "`unique()` checks `($this->comparator)($value, $prev) !== 0` instead of `$value !== $prev`. Why?"

**Answer:**
Consider a case-insensitive string comparator (`Comparator::stringsIgnoreCase()`). To this comparator, `"Apple"` and `"apple"` are equal — `comparator("Apple", "apple") === 0`. But `"Apple" !== "apple"` strictly. Using `!==` would keep both "Apple" and "apple" in the uniqued list, which contradicts the comparator's definition of equality.

Using `comparator($value, $prev) !== 0` is consistent with how the list defines order and equality throughout: the comparator is the single source of truth for element relationship.
```

- [ ] **Step 3: Update `doc/QualityToolchain.md`**

Add:

```markdown
## Why phparkitect?

phparkitect enforces architectural boundaries as machine-checked rules that run in CI. The three rules in `phparkitect.php` enforce:
1. All classes in `Event/` implement `ListEventInterface` — catches a new event class that forgets the marker interface.
2. All exceptions extend `SortedLinkedListException` — catches a new exception that breaks the catch-all hierarchy.
3. Core namespaces do not import from `Bridge/` — prevents Symfony from leaking into the core library.

Without these rules, these constraints are only social conventions ("remember to..."). With phparkitect, violating them breaks the CI build.

## Why CaptainHook?

CaptainHook installs git hooks that run locally before code leaves the developer's machine. The pre-commit hook runs CS and PHPStan; the pre-push hook runs the full test suite. This shortens the feedback loop from "push → CI fails → fix → push again" (minutes) to "commit → hook fails → fix → commit" (seconds). Senior developers wire quality gates into the local workflow, not just the pipeline.

## Why PHPBench?

Performance claims in documentation are easy to make and easy to forget. PHPBench makes them executable: `make bench` runs the benchmark suite and reports µs timings. The `benchLast10/100/1000` suite empirically verifies the O(1) tail pointer claim — all three show flat cost. `benchAdd` shows O(n) scaling as expected.
```

- [ ] **Step 4: Update README method table**

Add rows to the API table:
```markdown
| `unique()` | `static<T>` | New list with consecutive comparator-equal values removed |
| `__toString()` | `string` | `SortedLinkedList<type>[val1, val2, ...]` — implements `Stringable` |
| `__serialize()` | `never` | Always throws `LogicException` — use `toArray()` instead |
```

Update performance notes:
```markdown
- **`last()`:** O(1) — tail pointer maintained on every mutation
```

Update dev commands:
```markdown
make arch           # phparkitect architecture rule enforcement
make bench          # PHPBench performance benchmarks (verifies O(1) last())
make hooks          # Install git pre-commit / pre-push hooks via CaptainHook
```

- [ ] **Step 5: Run full quality gate**

```bash
docker compose run --rm php php -d memory_limit=512M vendor/bin/phpstan analyse 2>&1 | tail -2
docker compose run --rm php vendor/bin/psalm --no-progress 2>&1 | grep "No errors"
docker compose run --rm php vendor/bin/rector process --dry-run 2>&1 | tail -2
docker compose run --rm php vendor/bin/phpcs 2>&1 | tail -2
docker compose run --rm php vendor/bin/phpunit --coverage-clover=coverage/clover.xml --coverage-xml=coverage/xml --log-junit=coverage/junit.xml 2>&1 | grep "^OK"
docker compose run --rm php vendor/bin/coverage-guard check coverage/clover.xml --config coverage-guard.php 2>&1 | tail -2
docker compose run --rm php vendor/bin/infection --threads=4 --coverage=coverage 2>&1 | grep "MSI"
docker compose run --rm php vendor/bin/phparkitect check --config phparkitect.php 2>&1 | tail -3
```

Expected: all green, MSI ≥ 97%.

- [ ] **Step 6: Final commit**

```bash
git add doc/ README.md
git commit -m "docs: update all documentation for tail pointer, serialization, unique, phparkitect, CaptainHook, PHPBench"
```
