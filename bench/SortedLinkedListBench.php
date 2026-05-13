<?php declare(strict_types = 1);

use PhpBench\Attributes as Bench;
use ShipMonk\SortedLinkedList\Comparator;
use ShipMonk\SortedLinkedList\SortedLinkedList;

/**
 * Empirical verification of complexity claims in doc/ArchitectureOverview.md.
 *
 * Run with: make bench
 *
 * Key claims verified:
 *  - last()     is O(1)  — flat cost across 10/100/1000 elements (tail pointer)
 *  - add()      is O(n)  — cost scales linearly with list size
 *  - contains() exits early for absent values — best case ≪ worst case
 */
#[Bench\BeforeMethods(['setUp'])]
#[Bench\OutputTimeUnit('microseconds')]
class SortedLinkedListBench
{

    /** @var SortedLinkedList<int> */
    private SortedLinkedList $list10;

    /** @var SortedLinkedList<int> */
    private SortedLinkedList $list100;

    /** @var SortedLinkedList<int> */
    private SortedLinkedList $list1000;

    public function setUp(): void
    {
        $this->list10 = SortedLinkedList::ofIntegers(range(1, 10), Comparator::integers());
        $this->list100 = SortedLinkedList::ofIntegers(range(1, 100), Comparator::integers());
        $this->list1000 = SortedLinkedList::ofIntegers(range(1, 1_000), Comparator::integers());
    }

    /**
     * last() must be O(1) regardless of list size.
     * All three should show the same flat µs cost (tail pointer proves it).
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
     * contains() for absent values exits early due to sorting.
     * Value 0 exits at node 1 (best case). Value 9999 scans the full list (worst case).
     */
    #[Bench\Subject]
    #[Bench\Revs(5_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['contains'])]
    public function benchContainsAbsentBestCase(): void
    {
        $this->list1000->contains(0);
    }

    #[Bench\Subject]
    #[Bench\Revs(5_000)]
    #[Bench\Iterations(5)]
    #[Bench\Groups(['contains'])]
    public function benchContainsAbsentWorstCase(): void
    {
        $this->list1000->contains(9_999);
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
     * add() is O(n). Worst case = inserting in reverse (every insert scans to head).
     * Cost should scale ~linearly: 100-element ≈ 10× the 10-element time.
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
