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

use Rector\Config\RectorConfig;
use Rector\Php73\Rector\FuncCall\RegexDashEscapeRector;
use Rector\PHPUnit\CodeQuality\Rector\ClassMethod\AddInstanceofAssertForNullableInstanceRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/config',
        __DIR__.'/bin',
    ]);

    $rectorConfig->skip([
        __DIR__.'/src/Lexer.php',
        __DIR__.'/tests/Fixtures/pcre_patterns.php',
        __DIR__.'/tests/Unit/Bridge/Rector/RegexOptimizationRectorTest.php',
        __DIR__.'/tests/Unit/Bridge/Symfony/Command/RegexParserValidateCommandTest.php',
        __DIR__.'/tests/Unit/Bridge/Symfony/RegexParserBundleTest.php',
        __DIR__.'/tests/Unit/ValidationResultTest.php',
        __DIR__.'/tests/NodeVisitor/LengthRangeNodeVisitorTest.php',
        __DIR__.'/tests/NodeVisitor/TestCaseGeneratorNodeVisitorTest.php',
    ]);

    // $rectorConfig->import(__DIR__.'/config/rector/regex-parser.php');

    $rectorConfig->phpVersion(PhpVersion::PHP_84);
    // $rectorConfig->importNames();
    $rectorConfig->importShortClasses();
    $rectorConfig->removeUnusedImports();
    $rectorConfig->indent(' ', 4);
    $rectorConfig->cacheDirectory('.cache/rector/');
    $rectorConfig->parallel();
    $rectorConfig->editorUrl('phpstorm://open?file=%file%&line=%line%');

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        SetList::PHP_84,

        PHPUnitSetList::PHPUNIT_120,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);

    $rectorConfig->skip([
        RegexDashEscapeRector::class,
        FinalizeTestCaseClassRector::class,
        AddInstanceofAssertForNullableInstanceRector::class,
    ]);
};
