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
use RegexParser\Bridge\Rector\RegexOptimizationRector;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->singleton(OptimizerNodeVisitor::class);
    $rectorConfig->rule(RegexOptimizationRector::class);
};
