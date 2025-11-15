<?php

declare(strict_types=1);

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
    ]);
