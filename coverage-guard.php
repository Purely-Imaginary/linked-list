<?php declare(strict_types = 1);

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;
use ShipMonk\CoverageGuard\Rule\InspectionContext;
use ShipMonk\SortedLinkedList\Bridge\Symfony\SortedLinkedListBundle;
use ShipMonk\SortedLinkedList\Comparator;

// Bundle loadExtension requires a live Symfony DI container — excluded from per-method enforcement.
// Comparator::__construct is a private guard against instantiation and is intentionally unreachable.
$excluded = [SortedLinkedListBundle::class, Comparator::class];

$inner = new EnforceCoverageForMethodsRule(
    requiredCoveragePercentage: 100,
    minExecutableLines: 1,
);

$rule = new class ($inner, $excluded) implements CoverageRule {

    /**
     * @param list<class-string> $excluded
     */
    public function __construct(
        private readonly EnforceCoverageForMethodsRule $inner,
        private readonly array $excluded,
    )
    {
    }

    public function inspect(CodeBlock $codeBlock, InspectionContext $context): ?CoverageError
    {
        if (in_array($context->getClassName(), $this->excluded, true)) {
            return null;
        }

        return $this->inner->inspect($codeBlock, $context);
    }

};

$config = new Config();
$config->addRule($rule);

return $config;
