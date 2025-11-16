<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return Rector\Config\RectorConfig::configure()
    ->withRootFiles()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withPhpVersion(Rector\ValueObject\PhpVersion::PHP_82)
    ->withAttributesSets(phpunit: true)
    ->withComposerBased(phpunit: true)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withIndent()
    ->withCache(cacheDirectory: '.cache/rector/')
    ->withEditorUrl('phpstorm://open?file=%file%&line=%line%')
    ->withParallel()
    ->withComposerBased(phpunit: true)
    ->withSets([
        Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_84,
    ])
    ->withSkip([
        Rector\Php73\Rector\FuncCall\RegexDashEscapeRector::class,
    ]);
