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

use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Parser;
use RegexParser\Regex;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('regex_parser.parser', Parser::class)
            ->arg('$options', [
                'max_pattern_length' => '%regex_parser.max_pattern_length%',
            ])

        ->set('regex_parser.regex', Regex::class)
            ->arg('$parser', service('regex_parser.parser'))
            ->arg('$validator', service('regex_parser.visitor.validator'))
            ->arg('$explainer', service('regex_parser.visitor.explain'))
            ->arg('$generator', service('regex_parser.visitor.sample_generator'))
            ->arg('$optimizer', service('regex_parser.visitor.optimizer'))
            ->arg('$dumper', service('regex_parser.visitor.dumper'))
            ->arg('$scorer', service('regex_parser.visitor.complexity_score'))

        ->set('regex_parser.visitor.validator', ValidatorNodeVisitor::class)
        ->set('regex_parser.visitor.explain', ExplainVisitor::class)
        ->set('regex_parser.visitor.complexity_score', ComplexityScoreVisitor::class)
        ->set('regex_parser.visitor.html_explain', HtmlExplainVisitor::class)
        ->set('regex_parser.visitor.optimizer', OptimizerNodeVisitor::class)
        ->set('regex_parser.visitor.sample_generator', SampleGeneratorVisitor::class)
        ->set('regex_parser.visitor.dumper', DumperNodeVisitor::class)
        ->set('regex_parser.visitor.compiler', CompilerNodeVisitor::class)
    ;
};
