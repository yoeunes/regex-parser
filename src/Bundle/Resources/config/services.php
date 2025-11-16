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
    $container->services()
        ->set('regex_parser.visitor.explain', ExplainVisitor::class)

        ->set('regex_parser.visitor.complexity_score', ComplexityScoreVisitor::class)

        ->set('regex_parser.visitor.html_explain', HtmlExplainVisitor::class)

        ->set('regex_parser.visitor.optimizer', OptimizerNodeVisitor::class)

        ->set('regex_parser.visitor.sample_generator', SampleGeneratorVisitor::class);
};
