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
use Rector\Renaming\Rector\FuncCall\RenameFunctionRector;
use Rector\CodeQuality\Rector\FuncCall\SimplifyRegexPatternRector;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/phpstan',
        __DIR__ . '/rector',
        __DIR__ . '/config',
        __DIR__ . '/bin',
    ]);

    $rectorConfig->import(__DIR__ . '/config/rector/regex-parser.php');

    $rectorConfig->phpVersion(PhpVersion::PHP_84);
    $rectorConfig->importShortClasses();
    $rectorConfig->removeUnusedImports();
    $rectorConfig->indent(' ', 4);
    $rectorConfig->cacheDirectory('.cache/rector/');
    $rectorConfig->parallel();
    $rectorConfig->editorUrl('phpstorm://open?file=%file%&line=%line%');

    $rectorConfig->skip([
        RegexDashEscapeRector::class,

        SimplifyRegexPatternRector::class,

        RenameFunctionRector::class => [
            'preg_replace' => 'str_replace',
        ],
    ]);
};
