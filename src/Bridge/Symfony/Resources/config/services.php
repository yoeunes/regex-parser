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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ComplexityScoreNodeVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Parser;
use RegexParser\Regex;

/*
 * Base services for the RegexParser library.
 *
 * These services are always loaded when the bundle is enabled.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->private();

    // Core parser (stateless, operates on TokenStream)
    $services->set('regex_parser.parser', Parser::class);

    // Node visitors
    $services->set('regex_parser.visitor.validator', ValidatorNodeVisitor::class);
    $services->set('regex_parser.visitor.explain', ExplainNodeVisitor::class);
    $services->set('regex_parser.visitor.complexity_score', ComplexityScoreNodeVisitor::class);
    $services->set('regex_parser.visitor.html_explain', HtmlExplainNodeVisitor::class);
    $services->set('regex_parser.visitor.optimizer', OptimizerNodeVisitor::class);
    $services->set('regex_parser.visitor.sample_generator', SampleGeneratorNodeVisitor::class);
    $services->set('regex_parser.visitor.dumper', DumperNodeVisitor::class);
    $services->set('regex_parser.visitor.compiler', CompilerNodeVisitor::class);

    // Main Regex facade service (orchestrates Lexer and Parser)
    $services->set('regex_parser.regex', Regex::class)
        ->arg('$validator', service('regex_parser.visitor.validator'))
        ->arg('$explainer', service('regex_parser.visitor.explain'))
        ->arg('$generator', service('regex_parser.visitor.sample_generator'))
        ->arg('$optimizer', service('regex_parser.visitor.optimizer'))
        ->arg('$dumper', service('regex_parser.visitor.dumper'))
        ->arg('$scorer', service('regex_parser.visitor.complexity_score'))
        ->arg('$maxPatternLength', param('regex_parser.max_pattern_length'))
        ->public();

    // Aliases for autowiring
    $services->alias(Regex::class, 'regex_parser.regex')
        ->public();

    $services->alias(Parser::class, 'regex_parser.parser');
    $services->alias(ExplainNodeVisitor::class, 'regex_parser.visitor.explain');
    $services->alias(ComplexityScoreNodeVisitor::class, 'regex_parser.visitor.complexity_score');
};
