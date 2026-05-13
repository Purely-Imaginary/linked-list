.PHONY: install test stan psalm rector arch cs cs-fix mutate coverage-guard deps hooks bench ci help

DC = docker compose run --rm php

help:
	@echo "Available targets:"
	@echo "  install        Build Docker image and install Composer dependencies"
	@echo "  test           PHPUnit with coverage (Clover + XML)"
	@echo "  stan           PHPStan level 10 + shipmonk/phpstan-rules"
	@echo "  psalm          Psalm static analysis"
	@echo "  rector         Rector dry-run (verify no changes needed)"
	@echo "  arch           phparkitect architecture rule enforcement"
	@echo "  hooks          Install git pre-commit/pre-push hooks via CaptainHook"
	@echo "  bench          PHPBench performance benchmarks (verifies O(1) last())"
	@echo "  cs             Code style check (shipmonk/coding-standard)"
	@echo "  cs-fix         Code style auto-fix"
	@echo "  coverage-guard Per-method 100%% coverage enforcement"
	@echo "  mutate         Infection mutation tests (minMSI 95%%)"
	@echo "  deps           Dependency graph analysis"
	@echo "  ci             Full quality gate"

install:
	docker compose build
	$(DC) composer install

test:
	$(DC) vendor/bin/phpunit --coverage-clover=coverage/clover.xml --coverage-xml=coverage/xml --log-junit=coverage/junit.xml

stan:
	$(DC) php -d memory_limit=512M vendor/bin/phpstan analyse

psalm:
	$(DC) vendor/bin/psalm --no-progress

rector:
	$(DC) vendor/bin/rector process --dry-run

arch:
	$(DC) vendor/bin/phparkitect check --config phparkitect.php

hooks:
	$(DC) vendor/bin/captainhook install --force --skip-existing

bench:
	$(DC) vendor/bin/phpbench run --report=aggregate

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

ci: stan psalm rector arch cs test coverage-guard mutate deps
