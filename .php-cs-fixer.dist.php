<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        '.cache/',
        'tools/',
        'vendor/',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHPUnit10x0Migration:risky' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile('.cache/php-cs-fixer/cache.json');
