# SortedLinkedList Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a PHP 8.3 Composer library providing a type-safe, generic sorted linked list (`SortedLinkedList<T of int|string>`) with PHPStan generics, events, PSR-3 logging, a Symfony Bridge, full unit tests, and mutation tests — all running inside Docker.

**Architecture:** Core is a `final` generic class backed by a singly-linked `Node` with insertion-sort on `add()`. Type homogeneity is enforced statically via PHPStan `@template T of int|string` and at runtime via a type-lock on first insertion. Events and logging are optional injected dependencies with zero overhead when absent. A thin Symfony Bundle provides a factory service and event-listener autoconfiguration tag.

**Tech Stack:** PHP 8.3, PHPUnit 11, PHPStan 2 (level 10), shipmonk/phpstan-rules, shipmonk/dead-code-detector, shipmonk/coding-standard (PHP_CodeSniffer + slevomat), shipmonk/coverage-guard, shipmonk/composer-dependency-analyser, Infection PHP ≥0.29, psr/log ^3, symfony/http-kernel ^7 (bridge only), Docker php:8.3-cli-alpine.

---

## File Map

| Path | Responsibility |
|---|---|
| `src/Node.php` | `@internal` singly-linked node — typed value (readonly) + mutable next pointer |
| `src/SortedLinkedList.php` | Core generic class — insertion-sort, type-lock, Countable/IteratorAggregate |
| `src/Exception/MixedTypeException.php` | Thrown on mismatched type insertion or incompatible merge |
| `src/Exception/EmptyListException.php` | Thrown by `first()`/`last()` on empty list |
| `src/Event/ListEventInterface.php` | Marker interface for all list events |
| `src/Event/ItemInsertedEvent.php` | Carries inserted value + new size |
| `src/Event/ItemRemovedEvent.php` | Carries removed value + new size |
| `src/Event/ListClearedEvent.php` | Carries previous size |
| `src/EventListener/ListEventListenerInterface.php` | Generic observer interface with three typed methods |
| `src/Bridge/Symfony/SortedLinkedListFactory.php` | Autowirable factory — creates typed list instances |
| `src/Bridge/Symfony/SortedLinkedListBundle.php` | AbstractBundle — registers factory + autoconfigures listener tag |
| `tests/Fixture/RecordingListEventListener.php` | Test double recording all dispatched events |
| `tests/Unit/SortedLinkedListTest.php` | Full behavioural coverage of all public methods |
| `tests/Unit/Event/EventsTest.php` | Verifies event readonly properties |
| `tests/Unit/Exception/ExceptionTest.php` | Verifies exception hierarchy and messages |
| `tests/Unit/Bridge/Symfony/SortedLinkedListFactoryTest.php` | Factory method tests |
| `tests/Unit/Bridge/Symfony/SortedLinkedListBundleTest.php` | Bundle instantiation smoke test |
| `docker/php/Dockerfile` | PHP 8.3-cli-alpine with Composer |
| `docker-compose.yml` | Mounts project at `/app` |
| `composer.json` | Package manifest + all dev deps |
| `phpstan.neon` | Level 10, shipmonk rules, dead-code config |
| `phpcs.xml` | shipmonk/coding-standard ruleset |
| `infection.json` | minMSI 90, minCoveredMSI 95 |
| `phpunit.xml` | PHPUnit 11 with coverage source |
| `Makefile` | `install`, `test`, `stan`, `cs`, `cs-fix`, `mutate`, `deps`, `ci` |

---

## Task 1: Project Scaffolding

**Files:**
- Create: `docker/php/Dockerfile`
- Create: `docker-compose.yml`
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `phpstan.neon`
- Create: `phpcs.xml`
- Create: `infection.json`
- Create: `Makefile`
- Create: `src/.gitkeep`, `tests/Unit/.gitkeep`, `tests/Fixture/.gitkeep`

- [ ] **Step 1: Create Docker files**

`docker/php/Dockerfile`:
```dockerfile
FROM php:8.3-cli-alpine

RUN apk add --no-cache git unzip bash \
    && curl -sS https://getcomposer.org/installer \
       | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app
```

`docker-compose.yml`:
```yaml
services:
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - .:/app
    working_dir: /app
```

- [ ] **Step 2: Create `composer.json`**

```json
{
    "name": "amadeusz-opac/sorted-linked-list",
    "description": "A generic sorted linked list that holds either int or string values",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "psr/log": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^2.0",
        "shipmonk/phpstan-rules": "^4.0",
        "shipmonk/dead-code-detector": "^0.9",
        "shipmonk/coding-standard": "^2.0",
        "shipmonk/coverage-guard": "^2.0",
        "shipmonk/composer-dependency-analyser": "^1.7",
        "infection/infection": "^0.29",
        "symfony/http-kernel": "^7.0",
        "symfony/dependency-injection": "^7.0",
        "symfony/config": "^7.0"
    },
    "autoload": {
        "psr-4": {
            "ShipMonk\\SortedLinkedList\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "ShipMonk\\SortedLinkedList\\Tests\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "infection/extension-installer": true,
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
```

- [ ] **Step 3: Create `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
    bootstrap="vendor/autoload.php"
    colors="true"
    cacheDirectory=".phpunit.cache"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 4: Create `phpstan.neon`**

```neon
parameters:
    level: 10
    paths:
        - src
        - tests

includes:
    - vendor/phpstan/phpstan/conf/bleedingEdge.neon
    - vendor/shipmonk/phpstan-rules/rules.neon
    - vendor/shipmonk/dead-code-detector/rules.neon

services:
    -
        class: ShipMonk\DeadCode\Provider\PhpUnitEntrypointProvider
        tags:
            - phpstan.deadCode.entrypointProvider
```

- [ ] **Step 5: Create `phpcs.xml`**

```xml
<?xml version="1.0"?>
<ruleset name="ShipMonkSortedLinkedList">
    <config name="installed_paths" value="../../slevomat/coding-standard,../../shipmonk/coding-standard"/>
    <rule ref="ShipMonk"/>
    <file>src</file>
    <file>tests</file>
    <arg name="extensions" value="php"/>
    <arg name="colors"/>
    <arg value="sp"/>
</ruleset>
```

- [ ] **Step 6: Create `infection.json`**

```json
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": ["src"],
        "excludes": ["Bridge"]
    },
    "logs": {
        "text": "infection.log",
        "summary": "infection-summary.log"
    },
    "minMsi": 90,
    "minCoveredMsi": 95,
    "mutators": {
        "@default": true
    }
}
```

- [ ] **Step 7: Create `Makefile`**

```makefile
.PHONY: install test stan cs cs-fix mutate deps ci

DC = docker compose run --rm php

install:
	docker compose build
	$(DC) composer install

test:
	$(DC) vendor/bin/phpunit --coverage-text

stan:
	$(DC) vendor/bin/phpstan analyse

cs:
	$(DC) vendor/bin/phpcs

cs-fix:
	$(DC) vendor/bin/phpcbf

mutate:
	$(DC) vendor/bin/infection --threads=4

deps:
	$(DC) vendor/bin/composer-dependency-analyser

ci: install stan cs test mutate deps
```

- [ ] **Step 8: Create directory placeholders and run install**

```bash
mkdir -p src/Exception src/Event src/EventListener src/Bridge/Symfony \
         tests/Unit/Event tests/Unit/Exception tests/Unit/Bridge/Symfony \
         tests/Fixture
touch src/.gitkeep tests/Unit/.gitkeep tests/Fixture/.gitkeep
```

Run install:
```bash
make install
```

Expected: Composer installs all deps, no errors.

- [ ] **Step 9: Commit**

```bash
git add .
git commit -m "chore: project scaffolding — Docker, Composer, toolchain config"
```

---

## Task 2: Exceptions

**Files:**
- Create: `src/Exception/MixedTypeException.php`
- Create: `src/Exception/EmptyListException.php`
- Create: `tests/Unit/Exception/ExceptionTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Exception/ExceptionTest.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShipMonk\SortedLinkedList\Exception\EmptyListException;
use ShipMonk\SortedLinkedList\Exception\MixedTypeException;

final class ExceptionTest extends TestCase
{
    public function testMixedTypeExceptionExtendsRuntimeException(): void
    {
        $exception = new MixedTypeException('mixed types');
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('mixed types', $exception->getMessage());
    }

    public function testEmptyListExceptionExtendsRuntimeException(): void
    {
        $exception = new EmptyListException('list is empty');
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('list is empty', $exception->getMessage());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/Exception/ExceptionTest.php -v
```

Expected: FAIL — class not found errors.

- [ ] **Step 3: Implement exceptions**

`src/Exception/MixedTypeException.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Exception;

use RuntimeException;

final class MixedTypeException extends RuntimeException
{
}
```

`src/Exception/EmptyListException.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Exception;

use RuntimeException;

final class EmptyListException extends RuntimeException
{
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/Exception/ExceptionTest.php -v
```

Expected: 2 tests, 4 assertions — PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Exception/ tests/Unit/Exception/
git commit -m "feat: add MixedTypeException and EmptyListException"
```

---

## Task 3: Events and Event Listener Interface

**Files:**
- Create: `src/Event/ListEventInterface.php`
- Create: `src/Event/ItemInsertedEvent.php`
- Create: `src/Event/ItemRemovedEvent.php`
- Create: `src/Event/ListClearedEvent.php`
- Create: `src/EventListener/ListEventListenerInterface.php`
- Create: `tests/Unit/Event/EventsTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Event/EventsTest.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;
use ShipMonk\SortedLinkedList\Event\ListEventInterface;

final class EventsTest extends TestCase
{
    public function testItemInsertedEventImplementsInterface(): void
    {
        $event = new ItemInsertedEvent(42, 1);
        self::assertInstanceOf(ListEventInterface::class, $event);
        self::assertSame(42, $event->value);
        self::assertSame(1, $event->newSize);
    }

    public function testItemInsertedEventWithStringValue(): void
    {
        $event = new ItemInsertedEvent('hello', 3);
        self::assertSame('hello', $event->value);
        self::assertSame(3, $event->newSize);
    }

    public function testItemRemovedEventImplementsInterface(): void
    {
        $event = new ItemRemovedEvent(7, 0);
        self::assertInstanceOf(ListEventInterface::class, $event);
        self::assertSame(7, $event->value);
        self::assertSame(0, $event->newSize);
    }

    public function testListClearedEventImplementsInterface(): void
    {
        $event = new ListClearedEvent(5);
        self::assertInstanceOf(ListEventInterface::class, $event);
        self::assertSame(5, $event->previousSize);
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/Event/EventsTest.php -v
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement events**

`src/Event/ListEventInterface.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Event;

interface ListEventInterface
{
}
```

`src/Event/ItemInsertedEvent.php`:
```php
<?php

declare(strict_types=1);

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
        public readonly int|string $value,
        public readonly int $newSize,
    ) {
    }
}
```

`src/Event/ItemRemovedEvent.php`:
```php
<?php

declare(strict_types=1);

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
        public readonly int|string $value,
        public readonly int $newSize,
    ) {
    }
}
```

`src/Event/ListClearedEvent.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Event;

final readonly class ListClearedEvent implements ListEventInterface
{
    public function __construct(
        public readonly int $previousSize,
    ) {
    }
}
```

`src/EventListener/ListEventListenerInterface.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\EventListener;

use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;

/**
 * @template T of int|string
 */
interface ListEventListenerInterface
{
    /**
     * @param ItemInsertedEvent<T> $event
     */
    public function onInserted(ItemInsertedEvent $event): void;

    /**
     * @param ItemRemovedEvent<T> $event
     */
    public function onRemoved(ItemRemovedEvent $event): void;

    public function onCleared(ListClearedEvent $event): void;
}
```

- [ ] **Step 4: Run to verify pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/Event/EventsTest.php -v
```

Expected: 4 tests, 10 assertions — PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Event/ src/EventListener/ tests/Unit/Event/
git commit -m "feat: add events, ListEventInterface, ListEventListenerInterface"
```

---

## Task 4: Node (internal class)

**Files:**
- Create: `src/Node.php`

No separate test — `Node` is `@internal` and fully exercised via `SortedLinkedList`.

- [ ] **Step 1: Implement Node**

`src/Node.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList;

/**
 * @template T of int|string
 * @internal
 */
final class Node
{
    /** @var Node<T>|null */
    private ?Node $next = null;

    /**
     * @param T $value
     */
    public function __construct(private readonly int|string $value)
    {
    }

    /**
     * @return T
     */
    public function getValue(): int|string
    {
        return $this->value;
    }

    /**
     * @return Node<T>|null
     */
    public function getNext(): ?self
    {
        return $this->next;
    }

    /**
     * @param Node<T>|null $next
     */
    public function setNext(?self $next): void
    {
        $this->next = $next;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Node.php
git commit -m "feat: add internal Node class"
```

---

## Task 5: SortedLinkedList — Core Insertion and Traversal

**Files:**
- Create: `src/SortedLinkedList.php`
- Create: `tests/Unit/SortedLinkedListTest.php` (initial coverage: add, toArray, count, isEmpty, getIterator)

- [ ] **Step 1: Write failing tests**

`tests/Unit/SortedLinkedListTest.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\SortedLinkedList;

final class SortedLinkedListTest extends TestCase
{
    /** @return SortedLinkedList<int> */
    private function intList(): SortedLinkedList
    {
        return new SortedLinkedList(static fn (int|string $a, int|string $b): int => $a <=> $b);
    }

    /** @return SortedLinkedList<string> */
    private function stringList(): SortedLinkedList
    {
        return new SortedLinkedList(static fn (int|string $a, int|string $b): int => $a <=> $b);
    }

    // ── add + toArray ──────────────────────────────────────────────────────────

    public function testNewListIsEmpty(): void
    {
        self::assertSame([], $this->intList()->toArray());
    }

    public function testAddSingleIntValue(): void
    {
        $list = $this->intList();
        $list->add(5);
        self::assertSame([5], $list->toArray());
    }

    public function testAddMaintainsSortedOrderForInts(): void
    {
        $list = $this->intList();
        $list->add(3);
        $list->add(1);
        $list->add(2);
        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testAddMaintainsSortedOrderForStrings(): void
    {
        $list = $this->stringList();
        $list->add('banana');
        $list->add('apple');
        $list->add('cherry');
        self::assertSame(['apple', 'banana', 'cherry'], $list->toArray());
    }

    public function testAddAllowsDuplicates(): void
    {
        $list = $this->intList();
        $list->add(2);
        $list->add(1);
        $list->add(2);
        self::assertSame([1, 2, 2], $list->toArray());
    }

    public function testAddInsertsAtHeadWhenSmallest(): void
    {
        $list = $this->intList();
        $list->add(5);
        $list->add(3);
        $list->add(1);
        self::assertSame([1, 3, 5], $list->toArray());
    }

    public function testAddInsertsAtTailWhenLargest(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(3);
        $list->add(5);
        self::assertSame([1, 3, 5], $list->toArray());
    }

    public function testCustomDescendingComparator(): void
    {
        $list = new SortedLinkedList(static fn (int|string $a, int|string $b): int => $b <=> $a);
        $list->add(1);
        $list->add(3);
        $list->add(2);
        self::assertSame([3, 2, 1], $list->toArray());
    }

    // ── count + isEmpty ────────────────────────────────────────────────────────

    public function testCountOnEmptyList(): void
    {
        self::assertSame(0, $this->intList()->count());
    }

    public function testCountAfterInsertions(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->add(2);
        self::assertSame(3, $list->count());
    }

    public function testCountWorksWithBuiltinCountFunction(): void
    {
        $list = $this->intList();
        $list->add(10);
        self::assertSame(1, count($list));
    }

    public function testIsEmptyOnNewList(): void
    {
        self::assertTrue($this->intList()->isEmpty());
    }

    public function testIsEmptyAfterInsertion(): void
    {
        $list = $this->intList();
        $list->add(1);
        self::assertFalse($list->isEmpty());
    }

    // ── getIterator ────────────────────────────────────────────────────────────

    public function testIteratorYieldsValuesInSortedOrder(): void
    {
        $list = $this->intList();
        $list->add(3);
        $list->add(1);
        $list->add(2);

        $result = [];
        foreach ($list as $value) {
            $result[] = $value;
        }
        self::assertSame([1, 2, 3], $result);
    }

    public function testIteratorOnEmptyList(): void
    {
        $result = [];
        foreach ($this->intList() as $value) {
            $result[] = $value;
        }
        self::assertSame([], $result);
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/SortedLinkedListTest.php -v
```

Expected: FAIL — `SortedLinkedList` class not found.

- [ ] **Step 3: Implement SortedLinkedList (core — no remove/clear/filter/merge/events yet)**

`src/SortedLinkedList.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;
use ShipMonk\SortedLinkedList\Exception\EmptyListException;
use ShipMonk\SortedLinkedList\Exception\MixedTypeException;

/**
 * @template T of int|string
 * @implements IteratorAggregate<int, T>
 */
final class SortedLinkedList implements Countable, IteratorAggregate
{
    /** @var Node<T>|null */
    private ?Node $head = null;

    private int $size = 0;

    private ?string $lockedType = null;

    /** @var \Closure(T, T): int */
    private readonly \Closure $comparator;

    private readonly LoggerInterface $logger;

    /**
     * @param callable(T, T): int                $comparator
     * @param ListEventListenerInterface<T>|null $eventListener
     */
    public function __construct(
        callable $comparator,
        private readonly ?ListEventListenerInterface $eventListener = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->comparator = \Closure::fromCallable($comparator);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param T $value
     */
    public function add(int|string $value): void
    {
        $this->lockType($value);

        /** @var Node<T> $newNode */
        $newNode = new Node($value);

        if ($this->head === null || ($this->comparator)($value, $this->head->getValue()) <= 0) {
            $newNode->setNext($this->head);
            $this->head = $newNode;
        } else {
            $current = $this->head;
            while (
                $current->getNext() !== null
                && ($this->comparator)($value, $current->getNext()->getValue()) > 0
            ) {
                $current = $current->getNext();
            }
            $newNode->setNext($current->getNext());
            $current->setNext($newNode);
        }

        $this->size++;
        $this->logger->debug('Item added to SortedLinkedList', ['value' => $value, 'size' => $this->size]);
        $this->eventListener?->onInserted(new ItemInsertedEvent($value, $this->size));
    }

    /**
     * @param T $value
     */
    public function remove(int|string $value): bool
    {
        if ($this->head === null) {
            $this->logger->debug('Item not found in SortedLinkedList', ['value' => $value, 'removed' => false, 'size' => $this->size]);
            return false;
        }

        if ($this->head->getValue() === $value) {
            $this->head = $this->head->getNext();
            $this->size--;
            $this->logger->debug('Item removed from SortedLinkedList', ['value' => $value, 'removed' => true, 'size' => $this->size]);
            $this->eventListener?->onRemoved(new ItemRemovedEvent($value, $this->size));
            return true;
        }

        $current = $this->head;
        while ($current->getNext() !== null && $current->getNext()->getValue() !== $value) {
            $current = $current->getNext();
        }

        if ($current->getNext() === null) {
            $this->logger->debug('Item not found in SortedLinkedList', ['value' => $value, 'removed' => false, 'size' => $this->size]);
            return false;
        }

        $current->setNext($current->getNext()->getNext());
        $this->size--;
        $this->logger->debug('Item removed from SortedLinkedList', ['value' => $value, 'removed' => true, 'size' => $this->size]);
        $this->eventListener?->onRemoved(new ItemRemovedEvent($value, $this->size));
        return true;
    }

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

            $current = $current->getNext();
        }

        return false;
    }

    /**
     * @return T
     * @throws EmptyListException
     */
    public function first(): int|string
    {
        if ($this->head === null) {
            throw new EmptyListException('Cannot get first element of an empty list.');
        }

        return $this->head->getValue();
    }

    /**
     * @return T
     * @throws EmptyListException
     */
    public function last(): int|string
    {
        if ($this->head === null) {
            throw new EmptyListException('Cannot get last element of an empty list.');
        }

        $current = $this->head;
        while ($current->getNext() !== null) {
            $current = $current->getNext();
        }

        return $current->getValue();
    }

    public function count(): int
    {
        return $this->size;
    }

    public function isEmpty(): bool
    {
        return $this->size === 0;
    }

    public function clear(): void
    {
        $previousSize = $this->size;
        $this->head = null;
        $this->size = 0;
        $this->lockedType = null;
        $this->logger->debug('SortedLinkedList cleared', ['previousSize' => $previousSize]);
        $this->eventListener?->onCleared(new ListClearedEvent($previousSize));
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        /** @var list<T> $result */
        $result = [];
        $current = $this->head;
        while ($current !== null) {
            $result[] = $current->getValue();
            $current = $current->getNext();
        }

        return $result;
    }

    /**
     * @return ArrayIterator<int, T>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * @param callable(T): bool $predicate
     * @return static<T>
     */
    public function filter(callable $predicate): static
    {
        /** @var static<T> $new */
        $new = new static($this->comparator);
        foreach ($this->toArray() as $value) {
            if ($predicate($value)) {
                $new->add($value);
            }
        }

        return $new;
    }

    /**
     * @param self<T> $other
     * @return static<T>
     */
    public function merge(self $other): static
    {
        if (
            $this->lockedType !== null
            && $other->lockedType !== null
            && $this->lockedType !== $other->lockedType
        ) {
            throw new MixedTypeException(sprintf(
                'Cannot merge lists of different types: "%s" and "%s".',
                $this->lockedType,
                $other->lockedType,
            ));
        }

        /** @var static<T> $new */
        $new = new static($this->comparator);
        foreach ($this->toArray() as $value) {
            $new->add($value);
        }

        foreach ($other->toArray() as $value) {
            $new->add($value);
        }

        return $new;
    }

    /**
     * @param T $value
     */
    private function lockType(int|string $value): void
    {
        $type = get_debug_type($value);
        if ($this->lockedType === null) {
            $this->lockedType = $type;
            return;
        }

        if ($this->lockedType !== $type) {
            $message = sprintf(
                'SortedLinkedList is locked to type "%s", cannot insert value of type "%s".',
                $this->lockedType,
                $type,
            );
            $this->logger->warning($message);
            throw new MixedTypeException($message);
        }
    }
}
```

- [ ] **Step 4: Run core tests**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/SortedLinkedListTest.php -v
```

Expected: all tests defined so far — PASS.

- [ ] **Step 5: Commit**

```bash
git add src/SortedLinkedList.php tests/Unit/SortedLinkedListTest.php
git commit -m "feat: implement SortedLinkedList core — add, toArray, count, isEmpty, getIterator"
```

---

## Task 6: SortedLinkedList — Query Methods

**Files:**
- Modify: `tests/Unit/SortedLinkedListTest.php` (append test methods)

- [ ] **Step 1: Append tests for contains, first, last**

Add to `tests/Unit/SortedLinkedListTest.php` (inside the class, after existing methods):
```php
    // ── contains ──────────────────────────────────────────────────────────────

    public function testContainsReturnsTrueWhenValuePresent(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(3);
        self::assertTrue($list->contains(3));
    }

    public function testContainsReturnsFalseWhenValueAbsent(): void
    {
        $list = $this->intList();
        $list->add(1);
        self::assertFalse($list->contains(99));
    }

    public function testContainsOnEmptyList(): void
    {
        self::assertFalse($this->intList()->contains(1));
    }

    // ── first + last ───────────────────────────────────────────────────────────

    public function testFirstReturnsSmallestValue(): void
    {
        $list = $this->intList();
        $list->add(3);
        $list->add(1);
        $list->add(2);
        self::assertSame(1, $list->first());
    }

    public function testLastReturnsLargestValue(): void
    {
        $list = $this->intList();
        $list->add(3);
        $list->add(1);
        $list->add(2);
        self::assertSame(3, $list->last());
    }

    public function testFirstOnSingleElementList(): void
    {
        $list = $this->intList();
        $list->add(7);
        self::assertSame(7, $list->first());
    }

    public function testLastOnSingleElementList(): void
    {
        $list = $this->intList();
        $list->add(7);
        self::assertSame(7, $list->last());
    }

    public function testFirstThrowsOnEmptyList(): void
    {
        $this->expectException(\ShipMonk\SortedLinkedList\Exception\EmptyListException::class);
        $this->intList()->first();
    }

    public function testLastThrowsOnEmptyList(): void
    {
        $this->expectException(\ShipMonk\SortedLinkedList\Exception\EmptyListException::class);
        $this->intList()->last();
    }
```

- [ ] **Step 2: Run to verify pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/SortedLinkedListTest.php -v
```

Expected: all tests PASS (implementation already written in Task 5).

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/SortedLinkedListTest.php
git commit -m "test: add coverage for contains, first, last"
```

---

## Task 7: SortedLinkedList — Mutation Methods (remove, clear)

**Files:**
- Modify: `tests/Unit/SortedLinkedListTest.php` (append)

- [ ] **Step 1: Append tests**

```php
    // ── remove ────────────────────────────────────────────────────────────────

    public function testRemoveExistingValueReturnsTrueAndDecrementsCount(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->add(3);
        self::assertTrue($list->remove(2));
        self::assertSame([1, 3], $list->toArray());
        self::assertSame(2, $list->count());
    }

    public function testRemoveHeadElement(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        self::assertTrue($list->remove(1));
        self::assertSame([2], $list->toArray());
    }

    public function testRemoveTailElement(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        self::assertTrue($list->remove(2));
        self::assertSame([1], $list->toArray());
    }

    public function testRemoveSingleElement(): void
    {
        $list = $this->intList();
        $list->add(5);
        self::assertTrue($list->remove(5));
        self::assertTrue($list->isEmpty());
    }

    public function testRemoveAbsentValueReturnsFalse(): void
    {
        $list = $this->intList();
        $list->add(1);
        self::assertFalse($list->remove(99));
        self::assertSame(1, $list->count());
    }

    public function testRemoveFromEmptyListReturnsFalse(): void
    {
        self::assertFalse($this->intList()->remove(1));
    }

    public function testRemoveOnlyFirstOccurrenceOfDuplicate(): void
    {
        $list = $this->intList();
        $list->add(2);
        $list->add(2);
        $list->add(2);
        $list->remove(2);
        self::assertSame([2, 2], $list->toArray());
    }

    // ── clear ─────────────────────────────────────────────────────────────────

    public function testClearEmptiesTheList(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->clear();
        self::assertSame([], $list->toArray());
        self::assertSame(0, $list->count());
        self::assertTrue($list->isEmpty());
    }

    public function testClearResetsTypeLockSoListCanBeReused(): void
    {
        $list = new SortedLinkedList(static fn (int|string $a, int|string $b): int => $a <=> $b);
        $list->add(1);
        $list->clear();
        $list->add('hello');
        self::assertSame(['hello'], $list->toArray());
    }

    public function testClearOnEmptyListIsNoOp(): void
    {
        $list = $this->intList();
        $list->clear();
        self::assertTrue($list->isEmpty());
    }
```

- [ ] **Step 2: Run to verify pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/SortedLinkedListTest.php -v
```

Expected: all tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/SortedLinkedListTest.php
git commit -m "test: add coverage for remove and clear"
```

---

## Task 8: SortedLinkedList — Functional Methods (filter, merge)

**Files:**
- Modify: `tests/Unit/SortedLinkedListTest.php` (append)

- [ ] **Step 1: Append tests**

```php
    // ── filter ────────────────────────────────────────────────────────────────

    public function testFilterReturnsNewListWithMatchingValues(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(2);
        $list->add(3);
        $list->add(4);
        $filtered = $list->filter(static fn (int|string $v): bool => $v % 2 === 0);
        self::assertSame([2, 4], $filtered->toArray());
        self::assertSame([1, 2, 3, 4], $list->toArray()); // original unchanged
    }

    public function testFilterReturnsEmptyListWhenNothingMatches(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->add(3);
        $filtered = $list->filter(static fn (int|string $v): bool => $v > 100);
        self::assertTrue($filtered->isEmpty());
    }

    public function testFilterOnEmptyListReturnsEmptyList(): void
    {
        $filtered = $this->intList()->filter(static fn (int|string $v): bool => true);
        self::assertTrue($filtered->isEmpty());
    }

    public function testFilterReturnsSameGenericType(): void
    {
        $list = $this->intList();
        $list->add(10);
        $filtered = $list->filter(static fn (int|string $v): bool => true);
        $filtered->add(5);
        self::assertSame([5, 10], $filtered->toArray());
    }

    // ── merge ─────────────────────────────────────────────────────────────────

    public function testMergeProducesResortedUnion(): void
    {
        $a = $this->intList();
        $a->add(1);
        $a->add(3);
        $b = $this->intList();
        $b->add(2);
        $b->add(4);
        $merged = $a->merge($b);
        self::assertSame([1, 2, 3, 4], $merged->toArray());
    }

    public function testMergeDoesNotMutateOriginalLists(): void
    {
        $a = $this->intList();
        $a->add(1);
        $b = $this->intList();
        $b->add(2);
        $a->merge($b);
        self::assertSame([1], $a->toArray());
        self::assertSame([2], $b->toArray());
    }

    public function testMergeWithEmptyListReturnsEquivalentList(): void
    {
        $a = $this->intList();
        $a->add(5);
        $b = $this->intList();
        self::assertSame([5], $a->merge($b)->toArray());
        self::assertSame([5], $b->merge($a)->toArray());
    }

    public function testMergeTwoEmptyListsReturnsEmptyList(): void
    {
        self::assertTrue($this->intList()->merge($this->intList())->isEmpty());
    }

    public function testMergeThrowsWhenListsHaveDifferentLockedTypes(): void
    {
        $intList = $this->intList();
        $intList->add(1);

        $strList = $this->stringList();
        $strList->add('a');

        $this->expectException(\ShipMonk\SortedLinkedList\Exception\MixedTypeException::class);
        $intList->merge($strList);
    }
```

- [ ] **Step 2: Run to verify pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/SortedLinkedListTest.php -v
```

Expected: all tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/SortedLinkedListTest.php
git commit -m "test: add coverage for filter and merge"
```

---

## Task 9: SortedLinkedList — Type Locking

**Files:**
- Modify: `tests/Unit/SortedLinkedListTest.php` (append)

- [ ] **Step 1: Append tests**

```php
    // ── type locking ──────────────────────────────────────────────────────────

    public function testAddingMixedTypesThrowsMixedTypeException(): void
    {
        $list = new SortedLinkedList(static fn (int|string $a, int|string $b): int => $a <=> $b);
        $list->add(1);
        $this->expectException(\ShipMonk\SortedLinkedList\Exception\MixedTypeException::class);
        $list->add('hello');
    }

    public function testAddingStringThenIntThrowsMixedTypeException(): void
    {
        $list = new SortedLinkedList(static fn (int|string $a, int|string $b): int => $a <=> $b);
        $list->add('hello');
        $this->expectException(\ShipMonk\SortedLinkedList\Exception\MixedTypeException::class);
        $list->add(1);
    }

    public function testMixedTypeExceptionMessageContainsBothTypes(): void
    {
        $list = new SortedLinkedList(static fn (int|string $a, int|string $b): int => $a <=> $b);
        $list->add(1);
        try {
            $list->add('hello');
            self::fail('Expected MixedTypeException');
        } catch (\ShipMonk\SortedLinkedList\Exception\MixedTypeException $e) {
            self::assertStringContainsString('int', $e->getMessage());
            self::assertStringContainsString('string', $e->getMessage());
        }
    }

    public function testTypeIsUnlockedAfterClear(): void
    {
        $list = new SortedLinkedList(static fn (int|string $a, int|string $b): int => $a <=> $b);
        $list->add(42);
        $list->clear();
        $list->add('now a string');
        self::assertSame(['now a string'], $list->toArray());
    }
```

- [ ] **Step 2: Run to verify pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/SortedLinkedListTest.php -v
```

Expected: all tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/SortedLinkedListTest.php
git commit -m "test: add type locking coverage"
```

---

## Task 10: Events and Logging Wiring

**Files:**
- Create: `tests/Fixture/RecordingListEventListener.php`
- Modify: `tests/Unit/SortedLinkedListTest.php` (append)

- [ ] **Step 1: Create the recording test fixture**

`tests/Fixture/RecordingListEventListener.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Tests\Fixture;

use ShipMonk\SortedLinkedList\Event\ItemInsertedEvent;
use ShipMonk\SortedLinkedList\Event\ItemRemovedEvent;
use ShipMonk\SortedLinkedList\Event\ListClearedEvent;
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;

/**
 * @implements ListEventListenerInterface<int|string>
 */
final class RecordingListEventListener implements ListEventListenerInterface
{
    /** @var list<ItemInsertedEvent<int|string>> */
    public array $insertions = [];

    /** @var list<ItemRemovedEvent<int|string>> */
    public array $removals = [];

    /** @var list<ListClearedEvent> */
    public array $clears = [];

    /**
     * @param ItemInsertedEvent<int|string> $event
     */
    public function onInserted(ItemInsertedEvent $event): void
    {
        $this->insertions[] = $event;
    }

    /**
     * @param ItemRemovedEvent<int|string> $event
     */
    public function onRemoved(ItemRemovedEvent $event): void
    {
        $this->removals[] = $event;
    }

    public function onCleared(ListClearedEvent $event): void
    {
        $this->clears[] = $event;
    }
}
```

- [ ] **Step 2: Append event + logging tests**

Add to `tests/Unit/SortedLinkedListTest.php` (add imports at top: `use ShipMonk\SortedLinkedList\Tests\Fixture\RecordingListEventListener;`, `use Psr\Log\LoggerInterface;`):
```php
    // ── events ────────────────────────────────────────────────────────────────

    public function testEventListenerReceivesInsertedEvent(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList(
            static fn (int|string $a, int|string $b): int => $a <=> $b,
            $listener,
        );
        $list->add(42);
        self::assertCount(1, $listener->insertions);
        self::assertSame(42, $listener->insertions[0]->value);
        self::assertSame(1, $listener->insertions[0]->newSize);
    }

    public function testEventListenerReceivesRemovedEvent(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList(
            static fn (int|string $a, int|string $b): int => $a <=> $b,
            $listener,
        );
        $list->add(5);
        $list->remove(5);
        self::assertCount(1, $listener->removals);
        self::assertSame(5, $listener->removals[0]->value);
        self::assertSame(0, $listener->removals[0]->newSize);
    }

    public function testEventListenerDoesNotReceiveRemovedEventWhenValueAbsent(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList(
            static fn (int|string $a, int|string $b): int => $a <=> $b,
            $listener,
        );
        $list->remove(99);
        self::assertCount(0, $listener->removals);
    }

    public function testEventListenerReceivesClearedEvent(): void
    {
        $listener = new RecordingListEventListener();
        $list = new SortedLinkedList(
            static fn (int|string $a, int|string $b): int => $a <=> $b,
            $listener,
        );
        $list->add(1);
        $list->add(2);
        $list->clear();
        self::assertCount(1, $listener->clears);
        self::assertSame(2, $listener->clears[0]->previousSize);
    }

    public function testNoEventsDispatchedWhenNoListenerProvided(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->remove(1);
        $list->clear();
        // No assertion needed — test passes if no exception is thrown
        self::assertTrue(true);
    }

    // ── logging ───────────────────────────────────────────────────────────────

    public function testLoggerReceivesDebugOnAdd(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with('Item added to SortedLinkedList', self::arrayHasKey('value'));

        $list = new SortedLinkedList(
            static fn (int|string $a, int|string $b): int => $a <=> $b,
            null,
            $logger,
        );
        $list->add(7);
    }

    public function testLoggerReceivesDebugOnRemove(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))
            ->method('debug');

        $list = new SortedLinkedList(
            static fn (int|string $a, int|string $b): int => $a <=> $b,
            null,
            $logger,
        );
        $list->add(7);
        $list->remove(7);
    }

    public function testLoggerReceivesDebugOnClear(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())
            ->method('debug');

        $list = new SortedLinkedList(
            static fn (int|string $a, int|string $b): int => $a <=> $b,
            null,
            $logger,
        );
        $list->add(1);
        $list->clear();
    }

    public function testLoggerReceivesWarningOnTypeMismatch(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContainsString('locked'));

        $list = new SortedLinkedList(
            static fn (int|string $a, int|string $b): int => $a <=> $b,
            null,
            $logger,
        );
        $list->add(1);
        try {
            $list->add('bad');
        } catch (\ShipMonk\SortedLinkedList\Exception\MixedTypeException) {
            // expected
        }
    }

    public function testNoLoggerRequiredByDefault(): void
    {
        $list = $this->intList();
        $list->add(1);
        $list->remove(1);
        $list->clear();
        self::assertTrue(true); // no NullLogger errors
    }
```

- [ ] **Step 3: Run full test suite**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/SortedLinkedListTest.php -v
```

Expected: all tests PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Fixture/ tests/Unit/SortedLinkedListTest.php
git commit -m "test: add event and logging wiring coverage"
```

---

## Task 11: Symfony Bridge

**Files:**
- Create: `src/Bridge/Symfony/SortedLinkedListFactory.php`
- Create: `src/Bridge/Symfony/SortedLinkedListBundle.php`
- Create: `tests/Unit/Bridge/Symfony/SortedLinkedListFactoryTest.php`
- Create: `tests/Unit/Bridge/Symfony/SortedLinkedListBundleTest.php`

- [ ] **Step 1: Write failing tests**

`tests/Unit/Bridge/Symfony/SortedLinkedListFactoryTest.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListFactory;

final class SortedLinkedListFactoryTest extends TestCase
{
    public function testCreateIntListReturnsSortedList(): void
    {
        $factory = new SortedLinkedListFactory();
        $list = $factory->createIntList(static fn (int|string $a, int|string $b): int => $a <=> $b);
        $list->add(3);
        $list->add(1);
        self::assertSame([1, 3], $list->toArray());
    }

    public function testCreateStringListReturnsSortedList(): void
    {
        $factory = new SortedLinkedListFactory();
        $list = $factory->createStringList(static fn (int|string $a, int|string $b): int => $a <=> $b);
        $list->add('banana');
        $list->add('apple');
        self::assertSame(['apple', 'banana'], $list->toArray());
    }

    public function testCreateIntListEnforcesTypeLock(): void
    {
        $factory = new SortedLinkedListFactory();
        $list = $factory->createIntList(static fn (int|string $a, int|string $b): int => $a <=> $b);
        $list->add(1);
        $this->expectException(\ShipMonk\SortedLinkedList\Exception\MixedTypeException::class);
        $list->add('oops');
    }
}
```

`tests/Unit/Bridge/Symfony/SortedLinkedListBundleTest.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListBundle;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SortedLinkedListBundleTest extends TestCase
{
    public function testBundleExtendsAbstractBundle(): void
    {
        self::assertInstanceOf(AbstractBundle::class, new SortedLinkedListBundle());
    }

    public function testBundleHasCorrectName(): void
    {
        $bundle = new SortedLinkedListBundle();
        self::assertSame('SortedLinkedListBundle', $bundle->getName());
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/Bridge/ -v
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement factory and bundle**

`src/Bridge/Symfony/SortedLinkedListFactory.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Bridge\Symfony;

use Psr\Log\LoggerInterface;
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;
use ShipMonk\SortedLinkedList\SortedLinkedList;

final class SortedLinkedListFactory
{
    /**
     * @param ListEventListenerInterface<int|string>|null $eventListener
     */
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ListEventListenerInterface $eventListener = null,
    ) {
    }

    /**
     * @param callable(int|string, int|string): int $comparator
     * @return SortedLinkedList<int>
     */
    public function createIntList(callable $comparator): SortedLinkedList
    {
        return new SortedLinkedList($comparator, $this->eventListener, $this->logger);
    }

    /**
     * @param callable(int|string, int|string): int $comparator
     * @return SortedLinkedList<string>
     */
    public function createStringList(callable $comparator): SortedLinkedList
    {
        return new SortedLinkedList($comparator, $this->eventListener, $this->logger);
    }
}
```

`src/Bridge/Symfony/SortedLinkedListBundle.php`:
```php
<?php

declare(strict_types=1);

namespace ShipMonk\SortedLinkedList\Bridge\Symfony;

use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SortedLinkedListBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(SortedLinkedListFactory::class)
            ->autowire()
            ->autoconfigure();

        $builder->registerForAutoconfiguration(ListEventListenerInterface::class)
            ->addTag('sorted_linked_list.event_listener');
    }
}
```

- [ ] **Step 4: Run to verify pass**

```bash
docker compose run --rm php vendor/bin/phpunit tests/Unit/Bridge/ -v
```

Expected: all bridge tests PASS.

- [ ] **Step 5: Run full suite**

```bash
docker compose run --rm php vendor/bin/phpunit -v
```

Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Bridge/ tests/Unit/Bridge/
git commit -m "feat: add Symfony Bridge — SortedLinkedListFactory + SortedLinkedListBundle"
```

---

## Task 12: PHPStan Pass

**Files:**
- Modify: `phpstan.neon` (tune as needed after first run)

- [ ] **Step 1: Run PHPStan and capture errors**

```bash
docker compose run --rm php vendor/bin/phpstan analyse 2>&1 | tee phpstan-first-run.log
```

- [ ] **Step 2: Fix common issues**

Run PHPStan and fix any errors. Common issues and their fixes:

**Issue: "shipmonk/dead-code-detector provider class not found"**
Check exact class name after install:
```bash
docker compose run --rm php grep -r "EntrypointProvider" vendor/shipmonk/dead-code-detector/src --include="*.php" -l
```
Update `phpstan.neon` with correct class name found above.

**Issue: "Return type of getIterator() is ArrayIterator but IteratorAggregate requires Traversable"**
Already handled — `ArrayIterator` implements `Iterator extends Traversable`.

**Issue: "Call to an undefined method ... getNext()"** 
Already handled in Task 4 with getter/setter on Node.

**Issue: Dead code on ListEventInterface** 
Add to `phpstan.neon`:
```neon
parameters:
    shipmonkDeadCode:
        excludedNamespaces:
            - ShipMonk\SortedLinkedList\Bridge\Symfony
```

**Issue: any shipmonk/phpstan-rules rule violations (e.g. requiring explicit mixed catch, array typehints)**
Fix per error — each error message is self-explanatory at level 10.

- [ ] **Step 3: Confirm clean run**

```bash
docker compose run --rm php vendor/bin/phpstan analyse
```

Expected:
```
 [OK] No errors
```

- [ ] **Step 4: Clean up temp log and commit**

```bash
rm -f phpstan-first-run.log
git add phpstan.neon src/ tests/
git commit -m "fix: resolve all PHPStan level 10 violations"
```

---

## Task 13: Code Style Pass

**Files:**
- Modify: any files flagged by phpcs
- Possibly modify: `phpcs.xml`

- [ ] **Step 1: Run phpcs and capture violations**

```bash
docker compose run --rm php vendor/bin/phpcs 2>&1 | head -80
```

- [ ] **Step 2: Auto-fix where possible**

```bash
docker compose run --rm php vendor/bin/phpcbf
```

- [ ] **Step 3: Fix remaining manual violations**

Common issues with `shipmonk/coding-standard`:
- Missing blank line before `return`
- Incorrect spacing around operators
- Doc comment formatting
- `declare(strict_types=1)` placement (must be first statement after `<?php`)

Fix any remaining violations reported by phpcs that phpcbf could not auto-fix.

- [ ] **Step 4: Verify clean**

```bash
docker compose run --rm php vendor/bin/phpcs
```

Expected: no violations output (exit code 0).

- [ ] **Step 5: Commit**

```bash
git add src/ tests/
git commit -m "style: apply shipmonk/coding-standard formatting"
```

---

## Task 14: Mutation Tests Pass

**Files:**
- Modify: `infection.json` (tune if needed)
- Modify: tests if MSI < 90

- [ ] **Step 1: Generate PHPUnit coverage data first**

```bash
docker compose run --rm php vendor/bin/phpunit --coverage-xml=coverage/xml --log-junit=coverage/junit.xml
```

- [ ] **Step 2: Run Infection**

```bash
docker compose run --rm php vendor/bin/infection --threads=4 --coverage=coverage
```

- [ ] **Step 3: Review escaped mutants**

```bash
docker compose run --rm php cat infection.log | grep "Escaped" | head -30
```

Common escaped mutants and the tests to add:

**Escaped: `$a <=> $b` → `$b <=> $a` in test comparators**
Add descending comparator tests (already added in Task 5).

**Escaped: boundary condition in `add()` `<= 0` → `< 0`**
Add test: inserting equal value respects duplicate ordering:
```php
public function testDuplicateInsertedBeforeExistingEqualValue(): void
{
    $list = $this->intList();
    $list->add(2);
    $list->add(2);
    self::assertSame([2, 2], $list->toArray());
    self::assertSame(2, $list->count());
}
```

**Escaped: `!== null` → `=== null` in traversal loops**
Add test with a list of 4+ elements to force multi-hop traversal:
```php
public function testAddFiveElementsInReverseOrder(): void
{
    $list = $this->intList();
    foreach ([5, 4, 3, 2, 1] as $v) {
        $list->add($v);
    }
    self::assertSame([1, 2, 3, 4, 5], $list->toArray());
}
```

**Escaped: `$this->size--` incremented instead of decremented**
Already covered by count assertions in remove tests.

**Escaped: `return false` in remove → `return true`**
Add assertion on return value of missing-value remove (already in Task 7).

**Escaped: `lockedType !== $type` → `lockedType === $type`**
Already covered by type-locking tests.

Add any missing targeted tests to `tests/Unit/SortedLinkedListTest.php`, then re-run:
```bash
docker compose run --rm php vendor/bin/phpunit --coverage-xml=coverage/xml --log-junit=coverage/junit.xml && \
docker compose run --rm php vendor/bin/infection --threads=4 --coverage=coverage
```

- [ ] **Step 4: Confirm MSI ≥ 90**

Expected output contains:
```
Mutation Score Indicator (MSI): 90% or higher
Covered Code MSI: 95% or higher
```

If below 90%, review `infection.log`, add targeted tests for escaped mutants, and repeat Step 3.

- [ ] **Step 5: Run full CI gate**

```bash
make ci
```

Expected: all steps pass (install → stan → cs → test → mutate → deps).

- [ ] **Step 6: Final commit**

```bash
git add .
git commit -m "test: achieve mutation testing targets (MSI ≥ 90)"
```

---

## Done

At this point `make ci` runs cleanly:
- PHPUnit: all tests green
- PHPStan: level 10, no errors
- phpcs: no violations
- Infection: MSI ≥ 90, CoveredMSI ≥ 95
- composer-dependency-analyser: no issues
