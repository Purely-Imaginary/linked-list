<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

// Symfony packages are required by the optional Bridge — they are dev-only in this library
// but referenced from src/Bridge/Symfony/ which is an optional integration path.
// service() and tagged_iterator() are Symfony DI helper functions from require-dev;
// the analyser cannot resolve them without the symfony/dependency-injection package in require.
$config->ignoreErrorsOnPath(
    __DIR__ . '/src/Bridge',
    [ErrorType::DEV_DEPENDENCY_IN_PROD, ErrorType::UNKNOWN_FUNCTION],
);

return $config;
