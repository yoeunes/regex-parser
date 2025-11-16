<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->publicFalse();

    $services->set(ExplainVisitor::class);
    $services->set(ComplexityScoreVisitor::class);
    $services->set(HtmlExplainVisitor::class);
    $services->set(OptimizerNodeVisitor::class);
    $services->set(SampleGeneratorVisitor::class);
};
