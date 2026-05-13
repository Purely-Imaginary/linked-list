<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

// Symfony packages are required by the optional Bridge — they are dev-only in this library
// but referenced from src/Bridge/Symfony/ which is an optional integration path.
$config->ignoreErrorsOnPath(
    __DIR__ . '/src/Bridge',
    [ErrorType::DEV_DEPENDENCY_IN_PROD],
);

return $config;
