<?php

declare(strict_types=1);

$header = <<<'EOF'
This file is part of the RegexParser package.
 
(c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 
For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

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
        '@PSR12'                      => true,
        '@PSR12:risky'                => true,
        '@Symfony'                    => true,
        '@Symfony:risky'              => true,
        '@PHPUnit10x0Migration:risky' => true,
        'header_comment'              => ['header' => $header],
        'declare_strict_types'        => true,
    ])
    ->setFinder($finder)
    ->setCacheFile('.cache/php-cs-fixer/cache.json');
