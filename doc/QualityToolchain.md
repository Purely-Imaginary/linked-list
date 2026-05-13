# Quality Toolchain — Design Rationale

## Why PHPStan at level 10?

Level 10 is the maximum strictness: no `mixed` types may flow silently through the codebase, all template/generic types must be consistent, and dead code is flagged. ShipMonk's own packages use this level. Without maximum strictness, generic template bugs (e.g., `SortedLinkedList<int>` accepted a `string`) would only surface at runtime.

`shipmonk/phpstan-rules` adds ~40 additional rules the ShipMonk team found valuable. `shipmonk/dead-code-detector` prevents API surface from growing uncontrolled.

## Why both PHPStan and Psalm?

PHPStan and Psalm use different type inference engines. Code that passes one occasionally fails the other, catching real bugs. Known divergence (e.g., `ArrayIterator<int, T>` vs `ArrayIterator<int<0, max>, T>`) is suppressed with explanatory comments in `psalm.xml`; the suppressions document the disagreement rather than hiding it.

## Why Rector?

Rector automates code modernisation. The CI gate runs `rector --dry-run` and fails if any changes are detected. This ensures the codebase always uses the most idiomatic PHP 8.3 patterns (e.g., `instanceof` checks over `!== null`, `new self()` in `final` classes) without relying on humans to remember every rule.

## Why `shipmonk/coding-standard` over PSR-12?

ShipMonk's coding standard extends Slevomat's with ~150 additional sniffs covering things PSR-12 ignores: multi-line method signatures, duplicate spaces, alphabetical use statements, etc. Using it in this library signals familiarity with ShipMonk's internal tooling and results in code that would merge cleanly into their repos without style changes.

## Why `shipmonk/coverage-guard` at 100% per-method?

Percentage-based coverage (`>= 80% lines covered`) hides which specific methods have no tests. Per-method coverage enforcement catches the case where 10 trivially-covered methods mask 1 untested critical method. Every public method in `src/` (except the unreachable `Comparator::__construct` and the DI-container-only `SortedLinkedListBundle::loadExtension`) has at least one dedicated test.

## Why Infection PHP at 97% MSI?

Line coverage alone does not prove that tests actually assert correct behaviour. A test can execute a line without checking its result. Mutation testing creates modified ("mutated") versions of the code and verifies that tests fail on each mutation. 97% MSI means 97% of all possible single-statement mutations are detected by at least one test assertion.

The 3% that escape are equivalent mutants — mutations that produce identical behaviour despite different code (e.g., `assert($size > 0)` vs `assert($size >= 0)` when `$size` is always positive at that point). These are documented in the infection log.

## Why `shipmonk/composer-dependency-analyser`?

Shadow dependencies (using a class from a transitive dependency without declaring it directly) are fragile: if the intermediate package removes the class, the build breaks without warning. The analyser catches these at CI time. The Symfony bridge classes are excluded from the "dev-dependency-in-prod" check because they intentionally reference optional runtime packages.

## Why `reportUnmatchedIgnoredErrors: true` in `phpstan.neon`?

`@phpstan-ignore` comments in code can become stale when the underlying issue is fixed. Without this setting, stale ignores silently persist. With it, PHPStan flags any ignore that does not match an actual error, forcing periodic cleanup and preventing a false sense of security.

## Why phparkitect?

phparkitect enforces architectural boundaries as machine-checked rules that run in CI. The three rules in `phparkitect.php` enforce:
1. All classes in `Event/` implement `ListEventInterface` — catches a new event class that forgets the marker interface.
2. All exceptions extend `SortedLinkedListException` — catches a new exception that breaks the catch-all hierarchy.
3. Core namespaces do not import from `Bridge/` — prevents Symfony from leaking into the core library.

Without these rules, these constraints are only social conventions. With phparkitect, violating them breaks the CI build.

## Why CaptainHook?

CaptainHook installs git hooks that run locally before code leaves the developer's machine. The pre-commit hook runs CS and PHPStan; the pre-push hook runs the full test suite. This shortens the feedback loop from "push → CI fails → fix → push again" (minutes) to "commit → hook fails → fix → commit" (seconds). Senior developers wire quality gates into the local workflow, not just the pipeline.

Install once per clone with `make hooks`.

## Why PHPBench?

Performance claims in documentation are easy to make and easy to forget. PHPBench makes them executable: `make bench` runs the benchmark suite and reports µs timings. The results empirically verify:
- `last()` is O(1) — benchLast10/100/1000 all show ~0.12µs (tail pointer)
- `add()` is O(n) — benchAddWorstCase10/100/1000 show 6.6/63/619µs (~10× per 10× elements)
- `contains()` early exit — best case (0.23µs) vs worst case (182µs) for absent values
